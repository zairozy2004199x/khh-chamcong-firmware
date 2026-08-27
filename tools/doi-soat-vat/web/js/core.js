/*
 * Lõi đối soát: đọc sao kê -> quy về điểm xuất hoá đơn -> tổng hợp -> tách VAT.
 *
 * Cùng quy tắc với bản Python trong ../vatrec, và đã đối chiếu khớp tuyệt đối
 * với các file mẫu KH705 / KH989.
 *
 * Chạy được cả trong Web Worker lẫn trên trang chính: mọi thứ gắn vào self.VatRec.
 */
(function (root) {
  'use strict';

  /* ---------------------------------------------------------------- chuẩn hoá */

  var DATE_PATTERNS = [
    // "02-08-2026 22:49:40", "02/08/2026 21:35:05"
    { re: /^(\d{1,2})[-/](\d{1,2})[-/](\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/, order: 'dmy' },
    // "2026-08-02 21:49:56"
    { re: /^(\d{4})[-/](\d{1,2})[-/](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/, order: 'ymd' }
  ];

  /** Ngày dạng YYYY-MM-DD từ bất kỳ kiểu ô nào Excel đưa ra, hoặc null. */
  function toDate(value) {
    if (value === null || value === undefined || value === '') return null;
    if (value instanceof Date) {
      if (isNaN(value.getTime())) return null;
      return isoDate(value.getFullYear(), value.getMonth() + 1, value.getDate());
    }
    if (typeof value === 'number') {
      // Serial number của Excel, gốc là 1899-12-30.
      if (value < 1 || value > 200000) return null;
      var d = new Date(Date.UTC(1899, 11, 30) + Math.floor(value) * 86400000);
      return isoDate(d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate());
    }
    var text = String(value).trim();
    if (!text) return null;
    for (var i = 0; i < DATE_PATTERNS.length; i += 1) {
      var m = text.match(DATE_PATTERNS[i].re);
      if (!m) continue;
      var y, mo, da;
      if (DATE_PATTERNS[i].order === 'dmy') { da = +m[1]; mo = +m[2]; y = +m[3]; }
      else { y = +m[1]; mo = +m[2]; da = +m[3]; }
      if (mo < 1 || mo > 12 || da < 1 || da > 31) return null;
      return isoDate(y, mo, da);
    }
    return null;
  }

  function isoDate(y, m, d) {
    return String(y).padStart(4, '0') + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
  }

  /** Số tiền về số nguyên. Ô lỗi, ô trống, chữ đều thành 0. */
  function toInt(value) {
    if (value === null || value === undefined || value === '' || typeof value === 'boolean') return 0;
    if (typeof value === 'number') return isFinite(value) ? Math.round(value) : 0;
    var text = String(value).replace(/[^\d\-,.]/g, '');
    if (!text || /^[-.,]$/.test(text)) return 0;
    var n = parseFloat(normalizeSeparators(text));
    return isFinite(n) ? Math.round(n) : 0;
  }

  /**
   * Đưa chuỗi số về dạng parseFloat đọc được, xử lý dấu phân cách nhập nhằng.
   *
   * Tiền VND trong các file này viết "1.234.567" hoặc "1,234,567" - dấu chấm và
   * dấu phẩy đều là phân cách nghìn. Chỗ khó là một dấu duy nhất: "20.000" là hai
   * mươi nghìn chứ không phải hai mươi phẩy không. Quy ước: nhóm sau dấu cuối cùng
   * dài đúng 3 chữ số thì đó là phân cách nghìn, ngược lại là dấu thập phân.
   */
  function normalizeSeparators(text) {
    var dots = (text.match(/\./g) || []).length;
    var commas = (text.match(/,/g) || []).length;
    if (dots && commas) {
      return text.lastIndexOf('.') > text.lastIndexOf(',')
        ? text.replace(/,/g, '')
        : text.replace(/\./g, '').replace(',', '.');
    }
    if (dots > 1) return text.replace(/\./g, '');
    if (commas > 1) return text.replace(/,/g, '');
    if (dots === 1 || commas === 1) {
      var separator = dots ? '.' : ',';
      var after = text.split(separator)[1];
      if (after.length === 3 && /^\d{3}$/.test(after)) return text.split(separator).join('');
      return text.replace(',', '.');
    }
    return text;
  }

  var ERROR_CELLS = { '#N/A': 1, '#REF!': 1, '#VALUE!': 1, '#DIV/0!': 1, '#NAME?': 1, '-': 1 };

  /** Chuỗi đã bỏ khoảng trắng thừa; ô lỗi coi như rỗng. */
  function cleanText(value) {
    if (value === null || value === undefined) return '';
    var text = String(value).trim();
    if (ERROR_CELLS[text]) return '';
    return text.replace(/\s+/g, ' ');
  }

  /** Khoá so khớp - tên điểm gõ tay hay lệch hoa thường và khoảng trắng. */
  function keyText(value) {
    var text = cleanText(value);
    return text ? text.normalize('NFC').toLowerCase() : '';
  }

  /* ------------------------------------------------------------------- Excel */

  /** Chỉ số dòng tiêu đề - dòng đầu tiên chứa đủ các cột yêu cầu, hoặc -1. */
  function findHeader(rows, required, maxScan) {
    var wanted = required.map(keyText);
    var limit = Math.min(rows.length, maxScan || 30);
    for (var i = 0; i < limit; i += 1) {
      var present = Object.create(null);
      var row = rows[i] || [];
      for (var c = 0; c < row.length; c += 1) {
        var key = keyText(row[c]);
        if (key) present[key] = true;
      }
      var ok = true;
      for (var w = 0; w < wanted.length; w += 1) {
        if (!present[wanted[w]]) { ok = false; break; }
      }
      if (ok) return i;
    }
    return -1;
  }

  /** Ánh xạ tên cột -> chỉ số. Thiếu cột nào thì chỉ số là -1. */
  function columnIndex(header, names) {
    var byKey = Object.create(null);
    for (var c = 0; c < header.length; c += 1) {
      var key = keyText(header[c]);
      if (key && !(key in byKey)) byKey[key] = c;
    }
    var out = {};
    names.forEach(function (name) {
      var index = byKey[keyText(name)];
      out[name] = index === undefined ? -1 : index;
    });
    return out;
  }

  function at(row, index) {
    return index >= 0 && index < row.length ? row[index] : null;
  }

  /* --------------------------------------------------- nhận diện loại sheet */

  /*
   * Mỗi cổng có một bộ cột đặc trưng không đụng nhau, nên nhìn tiêu đề là biết
   * sheet đó của cổng nào. Danh mục xét trước sao kê, vì sheet danh mục QR cũng
   * có cột "Mã cửa hàng" và sẽ bị sao kê nhận nhầm nếu xét sau.
   */
  var SHEET_KINDS = [
    { kind: 'catalog_store', label: 'Danh mục mã cửa hàng (QR)',
      required: ['Mã cửa hàng', 'mã điểm xuất hóa đơn'] },
    // Sao kê VNPay cũng có cột "Tên điểm xuất hóa đơn" và "Chi nhánh", nên phải
    // đòi thêm "Mã điểm trên misa thuế" - cột chỉ bảng danh mục mới có.
    { kind: 'catalog_vnpay', label: 'Danh mục điểm VNPay',
      required: ['Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế', 'Mã điểm thu'] },
    { kind: 'catalog_payoo', label: 'Danh mục điểm Payoo',
      required: ['Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế', 'Chi nhánh'] },
    { kind: 'catalog_momo', label: 'Danh mục điểm MoMo',
      required: ['Mã cửa hàng', 'Tên điểm xuất hóa đơn'] },
    // Bảng chỉ có thông tin điểm, không kèm mã của cổng nào. Dùng để bù khu vực,
    // mã misa và pháp nhân cho những điểm mà danh mục của cổng bỏ trống.
    { kind: 'catalog_diem', label: 'Danh mục điểm (thông tin chung)',
      required: ['Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế'] },
    { kind: 'qr', label: 'Sao kê QR VietQR',
      required: ['Thời gian TT', 'Số tiền đến (VND)', 'Trạng thái', 'Mã cửa hàng'] },
    { kind: 'payoo', label: 'Sao kê Payoo',
      required: ['Cửa hàng', 'Ngày giao dịch', 'Hình thức thanh toán', 'Số tiền thanh toán (₫)'] },
    { kind: 'vnpay', label: 'Sao kê VNPay',
      required: ['Mã điểm thu', 'Thời gian GD', 'Số tiền sau KM', 'Trạng thái'] },
    { kind: 'zalo', label: 'Đơn Zalo Mini App',
      required: ['Mã đơn hàng', 'Ngày đặt hàng', 'Tổng tiền phải trả', 'Trạng thái thanh toán'] },
    { kind: 'momo', label: 'Sao kê MoMo',
      required: ['Thời gian', 'Mã đơn hàng', 'Trạng thái', 'Số tiền', 'Mã cửa hàng'] }
  ];

  /** Đoán loại của một sheet. Trả về null nếu không phải sheet ta quan tâm. */
  function detectSheet(rows) {
    for (var i = 0; i < SHEET_KINDS.length; i += 1) {
      var spec = SHEET_KINDS[i];
      var headerRow = findHeader(rows, spec.required);
      if (headerRow >= 0) return { kind: spec.kind, label: spec.label, headerRow: headerRow };
    }
    return null;
  }

  /* ------------------------------------------------------------- đọc sao kê */

  var SUCCESS = 'Thành công';

  /**
   * Sao kê QR VietQR. Chỉ lấy giao dịch "Thành công" - đúng điều kiện các file
   * mẫu đang dùng (tổng khớp tuyệt đối với khối "Tổng QR ...").
   */
  function readQr(rows, headerRow, stream, nguon) {
    var ix = columnIndex(rows[headerRow],
      ['Thời gian TT', 'Số tiền đến (VND)', 'Trạng thái', 'Mã cửa hàng', 'Mã tham chiếu']);
    var out = [];
    for (var r = headerRow + 1; r < rows.length; r += 1) {
      var row = rows[r] || [];
      var trangThai = cleanText(at(row, ix['Trạng thái']));
      if (trangThai !== SUCCESS) continue;
      // Mã cửa hàng trống (hay chỉ là dấu "-") là tiền chuyển khoản vãng lai vào
      // tài khoản: có tiền thật nhưng không thuộc điểm bán nào. Vẫn phải đưa vào
      // để phần tổng hợp đếm riêng, chứ bỏ ở đây thì tiền biến mất không dấu vết.
      var code = cleanText(at(row, ix['Mã cửa hàng']));
      out.push({
        channel: 'qr',
        stream: stream,
        nguon: nguon || '',
        code: code,
        ngay: toDate(at(row, ix['Thời gian TT'])),
        soTien: toInt(at(row, ix['Số tiền đến (VND)'])),
        ref: cleanText(at(row, ix['Mã tham chiếu']))
      });
    }
    return out;
  }

  /**
   * Sao kê Payoo. Lấy "Số tiền thanh toán" (tiền khách trả, chưa trừ phí) và
   * tách riêng hai luồng "Quét mã QR" / "Thẻ".
   */
  function readPayoo(rows, headerRow, stream, nguon) {
    var ix = columnIndex(rows[headerRow], ['Cửa hàng', 'Ngày giao dịch', 'Hình thức thanh toán',
      'Số tiền thanh toán (₫)', 'Mã giao dịch Payoo']);
    var out = [];
    for (var r = headerRow + 1; r < rows.length; r += 1) {
      var row = rows[r] || [];
      var code = cleanText(at(row, ix['Cửa hàng']));
      if (!code) continue;
      var hinhThuc = cleanText(at(row, ix['Hình thức thanh toán']));
      out.push({
        channel: 'payoo',
        stream: hinhThuc ? stream + ' - ' + hinhThuc : stream,
        nguon: nguon || '',
        code: code,
        ngay: toDate(at(row, ix['Ngày giao dịch'])),
        soTien: toInt(at(row, ix['Số tiền thanh toán (₫)'])),
        ref: cleanText(at(row, ix['Mã giao dịch Payoo']))
      });
    }
    return out;
  }

  /**
   * Sao kê VNPay. Doanh thu xuất hoá đơn là "Số tiền sau KM" - không phải
   * "Số tiền hạch toán thu hộ", cột đó lệch vì có giao dịch trả sang kỳ sau.
   */
  function readVnpay(rows, headerRow, stream, nguon) {
    var ix = columnIndex(rows[headerRow],
      ['Mã điểm thu', 'Thời gian GD', 'Số tiền sau KM', 'Trạng thái', 'Mã giao dịch']);
    var out = [];
    for (var r = headerRow + 1; r < rows.length; r += 1) {
      var row = rows[r] || [];
      var code = cleanText(at(row, ix['Mã điểm thu']));
      if (!code) continue;
      if (cleanText(at(row, ix['Trạng thái'])) !== SUCCESS) continue;
      out.push({
        channel: 'vnpay',
        stream: stream,
        nguon: nguon || '',
        code: code,
        ngay: toDate(at(row, ix['Thời gian GD'])),
        soTien: toInt(at(row, ix['Số tiền sau KM'])),
        ref: cleanText(at(row, ix['Mã giao dịch']))
      });
    }
    return out;
  }

  /**
   * Đơn Zalo Mini App. File này không có mã điểm bán: mỗi dòng là một dòng sản
   * phẩm của đơn, gian hàng nằm ở đầu tên sản phẩm ("VINCOM TIMES CITY - ...").
   * Nên gom về đơn (một mã đơn tính một lần) rồi cắt lấy tên gian hàng.
   */
  function readZalo(rows, headerRow, stream, nguon) {
    var ix = columnIndex(rows[headerRow], ['Mã đơn hàng', 'Ngày đặt hàng', 'Tổng tiền phải trả',
      'Trạng thái thanh toán', 'Trạng thái đơn hàng', 'Tên sản phẩm', 'Chi nhánh']);
    var seen = Object.create(null);
    var out = [];
    for (var r = headerRow + 1; r < rows.length; r += 1) {
      var row = rows[r] || [];
      var maDon = cleanText(at(row, ix['Mã đơn hàng']));
      if (!maDon || seen[maDon]) continue;
      if (cleanText(at(row, ix['Trạng thái thanh toán'])) !== 'Đã thanh toán') continue;
      if (cleanText(at(row, ix['Trạng thái đơn hàng'])) === 'Đã hủy') continue;
      seen[maDon] = true;
      out.push({
        channel: 'zalo',
        stream: stream,
        nguon: nguon || '',
        code: gianHang(row, ix),
        ngay: toDate(at(row, ix['Ngày đặt hàng'])),
        soTien: toInt(at(row, ix['Tổng tiền phải trả'])),
        ref: maDon
      });
    }
    return out;
  }

  function gianHang(row, ix) {
    var tenSanPham = cleanText(at(row, ix['Tên sản phẩm']));
    if (tenSanPham.indexOf(' - ') > 0) return tenSanPham.split(' - ')[0].trim();
    return tenSanPham || cleanText(at(row, ix['Chi nhánh']));
  }

  /**
   * Sao kê MoMo.
   *
   * File MoMo hay đặt hai khối cạnh nhau trên cùng một sheet: khối "MS.…" tổng
   * hợp và khối xuất thẳng từ cổng. Dò tiêu đề theo tên cột nên tự bắt đúng khối
   * xuất thẳng — khối kia không có bộ cột này.
   *
   * Đã đối chiếu: tổng khớp tuyệt đối 1.042.790.000 và không lệch một ô nào trên
   * 17 điểm x 31 ngày của bảng "Tổng Momo T8".
   */
  function readMomo(rows, headerRow, stream, nguon) {
    var ix = columnIndex(rows[headerRow],
      ['Thời gian', 'Mã đơn hàng', 'Trạng thái', 'Số tiền', 'Mã cửa hàng']);
    var out = [];
    for (var r = headerRow + 1; r < rows.length; r += 1) {
      var row = rows[r] || [];
      var code = cleanText(at(row, ix['Mã cửa hàng']));
      if (!code) continue;
      if (cleanText(at(row, ix['Trạng thái'])) !== SUCCESS) continue;
      out.push({
        channel: 'momo',
        stream: stream,
        nguon: nguon || '',
        code: code,
        ngay: toDate(at(row, ix['Thời gian'])),
        soTien: toInt(at(row, ix['Số tiền'])),
        ref: cleanText(at(row, ix['Mã đơn hàng']))
      });
    }
    return out;
  }

  var READERS = { qr: readQr, payoo: readPayoo, vnpay: readVnpay, zalo: readZalo, momo: readMomo };

  /* -------------------------------------------------------------- danh mục */

  /** Bảng tra mã kỹ thuật của từng cổng -> điểm xuất hoá đơn. */
  function Catalog() {
    this.byChannel = Object.create(null);
    this.points = Object.create(null);
  }

  Catalog.prototype.add = function (channel, code, point) {
    var codeKey = keyText(code);
    if (!codeKey || !point.tenDiem) return;
    if (!this.byChannel[channel]) this.byChannel[channel] = Object.create(null);
    if (!(codeKey in this.byChannel[channel])) this.byChannel[channel][codeKey] = point;
    // Bản ghi đầy đủ nhất thắng: sheet phụ hay bỏ trống khu vực / pháp nhân.
    var key = pointKey(point);
    var current = this.points[key];
    if (!current || filledScore(point) > filledScore(current)) this.points[key] = point;
  };

  /** Ghi nhận thông tin một điểm mà không gắn với mã của cổng nào. */
  Catalog.prototype.addPoint = function (point) {
    if (!point.tenDiem) return;
    var key = pointKey(point);
    var current = this.points[key];
    if (!current || filledScore(point) > filledScore(current)) this.points[key] = point;
  };

  Catalog.prototype.lookup = function (channel, code) {
    var table = this.byChannel[channel];
    return table ? table[keyText(code)] || null : null;
  };

  function pointKey(point) {
    return keyText(point.tenDiem) || keyText(point.maMisa);
  }

  function filledScore(point) {
    var n = 0;
    [point.maMisa, point.khuVuc, point.dichVu, point.phapNhan].forEach(function (v) { if (v) n += 1; });
    return n;
  }

  function makePoint(fields) {
    return {
      tenDiem: fields.tenDiem || '',
      maMisa: fields.maMisa || '',
      khuVuc: fields.khuVuc || '',
      dichVu: fields.dichVu || '',
      hinhThucHopTac: fields.hinhThucHopTac || '',
      phapNhan: fields.phapNhan || ''
    };
  }

  /** Danh mục QR, đọc từ sheet "chia theo mã cửa hàng". */
  function loadStoreCatalog(rows, headerRow, catalog) {
    var ix = columnIndex(rows[headerRow],
      ['Mã cửa hàng', 'mã điểm xuất hóa đơn', 'Khu vực', 'Tên cửa hàng']);
    var chuaGan = [];
    for (var r = headerRow + 1; r < rows.length; r += 1) {
      var row = rows[r] || [];
      var maCuaHang = cleanText(at(row, ix['Mã cửa hàng']));
      if (!maCuaHang) continue;
      var tenDiem = cleanText(at(row, ix['mã điểm xuất hóa đơn']));
      if (!tenDiem) {
        chuaGan.push({ maCuaHang: maCuaHang, tenCuaHang: cleanText(at(row, ix['Tên cửa hàng'])) });
        continue;
      }
      catalog.add('qr', maCuaHang,
        makePoint({ tenDiem: tenDiem, khuVuc: cleanText(at(row, ix['Khu vực'])) }));
    }
    return chuaGan;
  }

  /** Danh mục điểm của VNPay / Payoo - cùng bố cục, chỉ khác cột mã. */
  function loadPointCatalog(rows, headerRow, channel, codeColumn, catalog) {
    var ix = columnIndex(rows[headerRow], ['Tên điểm xuất hóa đơn', codeColumn,
      'Mã điểm trên misa thuế', 'Khu vực', 'Dịch vụ', 'Hình thức hợp tác', 'Pháp nhân']);
    for (var r = headerRow + 1; r < rows.length; r += 1) {
      var row = rows[r] || [];
      var code = cleanText(at(row, ix[codeColumn]));
      var tenDiem = cleanText(at(row, ix['Tên điểm xuất hóa đơn']));
      if (!code || !tenDiem) continue;
      catalog.add(channel, code, makePoint({
        tenDiem: tenDiem,
        maMisa: cleanText(at(row, ix['Mã điểm trên misa thuế'])),
        khuVuc: cleanText(at(row, ix['Khu vực'])),
        dichVu: cleanText(at(row, ix['Dịch vụ'])),
        hinhThucHopTac: cleanText(at(row, ix['Hình thức hợp tác'])),
        phapNhan: cleanText(at(row, ix['Pháp nhân']))
      }));
    }
  }

  /**
   * Danh mục MoMo: mã cửa hàng -> điểm xuất hoá đơn.
   *
   * Sheet tổng hợp của MoMo xếp nhiều bảng nối nhau (doanh thu, rồi phí, rồi
   * dòng tổng). Chỉ dòng vừa có mã cửa hàng vừa có tên điểm mới là dòng danh mục;
   * bảng phí để trống tên điểm và dòng tổng để trống mã cửa hàng.
   */
  function loadMomoCatalog(rows, headerRow, catalog) {
    var ix = columnIndex(rows[headerRow], ['Mã cửa hàng', 'Tên điểm xuất hóa đơn', 'Mã KH']);
    var daThay = Object.create(null);
    for (var r = headerRow + 1; r < rows.length; r += 1) {
      var row = rows[r] || [];
      var code = cleanText(at(row, ix['Mã cửa hàng']));
      var tenDiem = cleanText(at(row, ix['Tên điểm xuất hóa đơn']));
      if (!code || !tenDiem || daThay[keyText(code)]) continue;
      daThay[keyText(code)] = true;
      catalog.add('momo', code, makePoint({
        tenDiem: tenDiem,
        phapNhan: cleanText(at(row, ix['Mã KH']))
      }));
    }
  }

  /** Bảng chỉ có thông tin điểm — bù khu vực, mã misa, pháp nhân. */
  function loadPointInfo(rows, headerRow, catalog) {
    var ix = columnIndex(rows[headerRow], ['Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế',
      'Khu vực', 'Dịch vụ', 'Hình thức hợp tác', 'Pháp nhân']);
    for (var r = headerRow + 1; r < rows.length; r += 1) {
      var row = rows[r] || [];
      var tenDiem = cleanText(at(row, ix['Tên điểm xuất hóa đơn']));
      if (!tenDiem) continue;
      catalog.addPoint(makePoint({
        tenDiem: tenDiem,
        maMisa: cleanText(at(row, ix['Mã điểm trên misa thuế'])),
        khuVuc: cleanText(at(row, ix['Khu vực'])),
        dichVu: cleanText(at(row, ix['Dịch vụ'])),
        hinhThucHopTac: cleanText(at(row, ix['Hình thức hợp tác'])),
        phapNhan: cleanText(at(row, ix['Pháp nhân']))
      }));
    }
  }

  /* ------------------------------------------------------------- tổng hợp */

  // Ký tự ngăn khoá ghép: unit separator (U+001F). Không bao giờ xuất hiện trong
  // tên điểm hay mã giao dịch, nên tách khoá ngược lại luôn đúng.
  var SEP = String.fromCharCode(31);

  /**
   * Quy giao dịch về điểm xuất hoá đơn rồi cộng theo ngày.
   *
   * Giao dịch có mã không tra được không bị cộng vào đâu cả - nó vào danh sách
   * chuaMap để người dùng bổ sung, chứ không im lặng bỏ qua.
   */
  function aggregate(txns, catalog, options) {
    var kyTu = options.kyTu, kyDen = options.kyDen, phapNhan = options.phapNhan;
    var cells = Object.create(null);
    var points = Object.create(null);
    var streams = [];
    var streamSeen = Object.create(null);
    var dates = Object.create(null);
    var chuaMapCount = Object.create(null);
    var chuaMapAmount = Object.create(null);
    var seenRef = Object.create(null);
    var ngoaiKy = 0, khongCoNgay = 0, loaiKhacPhapNhan = 0;
    var vangLai = 0, vangLaiSoGiaoDich = 0;
    var trungLap = 0, trungLapSoGiaoDich = 0;
    var viDuTrungLap = [];

    // Số liệu tách theo từng file đầu vào, để đối chiếu ngược từng file một.
    var nguonList = [];
    var nguonSeen = Object.create(null);
    var nguonStats = Object.create(null);
    var nguonCells = Object.create(null);

    function thongKe(nguon) {
      var ten = nguon || '(không rõ file)';
      if (!nguonSeen[ten]) {
        nguonSeen[ten] = true;
        nguonList.push(ten);
        nguonStats[ten] = {
          nguon: ten, soGiaoDich: 0, soTien: 0, luong: [], luongSeen: Object.create(null),
          chuaMapSoGiaoDich: 0, chuaMapSoTien: 0, vangLai: 0, vangLaiSoGiaoDich: 0,
          ngoaiKy: 0, trungLap: 0, trungLapSoGiaoDich: 0, khongCoNgay: 0,
          loaiKhacPhapNhan: 0, diem: Object.create(null)
        };
      }
      return nguonStats[ten];
    }

    txns.forEach(function (txn) {
      if (!streamSeen[txn.stream]) { streamSeen[txn.stream] = true; streams.push(txn.stream); }
      var tk = thongKe(txn.nguon);
      if (!tk.luongSeen[txn.stream]) { tk.luongSeen[txn.stream] = true; tk.luong.push(txn.stream); }

      if (!txn.ngay) { khongCoNgay += 1; tk.khongCoNgay += 1; return; }
      if ((kyTu && txn.ngay < kyTu) || (kyDen && txn.ngay > kyDen)) {
        ngoaiKy += txn.soTien; tk.ngoaiKy += txn.soTien; return;
      }
      // Cùng một mã giao dịch xuất hiện hai lần nghĩa là nó bị đọc từ hai sheet
      // (file sao kê thường kèm cả sheet của kỳ cũ). Cộng cả hai thì doanh thu
      // bị đội lên, nên bỏ bản thứ hai và báo lại số đã bỏ.
      if (txn.ref) {
        var refKey = txn.channel + SEP + txn.ref;
        if (seenRef[refKey]) {
          trungLap += txn.soTien;
          trungLapSoGiaoDich += 1;
          tk.trungLap += txn.soTien;
          tk.trungLapSoGiaoDich += 1;
          if (viDuTrungLap.length < 50) {
            viDuTrungLap.push({ channel: txn.channel, ref: txn.ref, stream: txn.stream, soTien: txn.soTien });
          }
          return;
        }
        seenRef[refKey] = true;
      }
      // Reader đã lọc, nhưng vẫn kiểm lại ở đây để mọi nguồn mới đều an toàn.
      if (!cleanText(txn.code)) {
        vangLai += txn.soTien;
        vangLaiSoGiaoDich += 1;
        tk.vangLai += txn.soTien;
        tk.vangLaiSoGiaoDich += 1;
        return;
      }
      var point = catalog.lookup(txn.channel, txn.code);
      if (!point) {
        var missKey = txn.channel + SEP + txn.code;
        chuaMapCount[missKey] = (chuaMapCount[missKey] || 0) + 1;
        chuaMapAmount[missKey] = (chuaMapAmount[missKey] || 0) + txn.soTien;
        tk.chuaMapSoGiaoDich += 1;
        tk.chuaMapSoTien += txn.soTien;
        return;
      }
      // Lọc theo bản ghi đã bù thông tin, đúng bản dùng để hiển thị: danh mục của
      // cổng hay ghi pháp nhân tắt ("KH Mới TK CTy"), bảng thông tin điểm mới có
      // tên pháp nhân chuẩn.
      var key = pointKey(point);
      var full = catalog.points[key] || point;
      if (phapNhan && full.phapNhan && keyText(full.phapNhan) !== keyText(phapNhan)) {
        loaiKhacPhapNhan += txn.soTien;
        tk.loaiKhacPhapNhan += txn.soTien;
        return;
      }
      if (!points[key]) points[key] = full;
      dates[txn.ngay] = true;
      var cellKey = key + SEP + txn.stream + SEP + txn.ngay;
      cells[cellKey] = (cells[cellKey] || 0) + txn.soTien;

      tk.soGiaoDich += 1;
      tk.soTien += txn.soTien;
      tk.diem[key] = true;
      var nguonKey = tk.nguon + SEP + key + SEP + txn.ngay;
      nguonCells[nguonKey] = (nguonCells[nguonKey] || 0) + txn.soTien;
    });

    nguonList.forEach(function (ten) {
      var tk = nguonStats[ten];
      tk.soDiem = Object.keys(tk.diem).length;
      delete tk.diem;
      delete tk.luongSeen;
    });

    var chuaMap = Object.keys(chuaMapCount).map(function (key) {
      var parts = key.split(SEP);
      return {
        channel: parts[0], code: parts[1],
        soGiaoDich: chuaMapCount[key], soTien: chuaMapAmount[key]
      };
    }).sort(function (a, b) { return b.soTien - a.soTien; });

    return {
      cells: cells,
      points: points,
      streams: streams,
      dates: Object.keys(dates).sort(),
      chuaMap: chuaMap,
      nguonList: nguonList,
      nguonStats: nguonStats,
      nguonCells: nguonCells,
      trungLap: trungLap,
      trungLapSoGiaoDich: trungLapSoGiaoDich,
      viDuTrungLap: viDuTrungLap,
      ngoaiKy: ngoaiKy,
      vangLai: vangLai,
      vangLaiSoGiaoDich: vangLaiSoGiaoDich,
      khongCoNgay: khongCoNgay,
      loaiKhacPhapNhan: loaiKhacPhapNhan,
      kyTu: kyTu,
      kyDen: kyDen
    };
  }

  /** Tổng theo điểm; truyền stream để chỉ lấy một luồng. */
  function totalsByPoint(result, stream) {
    var out = Object.create(null);
    Object.keys(result.cells).forEach(function (key) {
      var parts = key.split(SEP);
      if (stream && parts[1] !== stream) return;
      out[parts[0]] = (out[parts[0]] || 0) + result.cells[key];
    });
    return out;
  }

  function totalOf(result, stream) {
    var sum = 0;
    Object.keys(result.cells).forEach(function (key) {
      if (stream && key.split(SEP)[1] !== stream) return;
      sum += result.cells[key];
    });
    return sum;
  }

  /** Một dòng pivot của riêng một file: điểm -> {ngày: số tiền}. */
  function rowOfNguon(result, nguon, pointKeyValue) {
    var out = Object.create(null);
    Object.keys(result.nguonCells).forEach(function (key) {
      var parts = key.split(SEP);
      if (parts[0] === nguon && parts[1] === pointKeyValue) out[parts[2]] = result.nguonCells[key];
    });
    return out;
  }

  /** Các điểm có doanh thu trong một file, kèm tổng của từng điểm. */
  function pointsOfNguon(result, nguon) {
    var out = Object.create(null);
    Object.keys(result.nguonCells).forEach(function (key) {
      var parts = key.split(SEP);
      if (parts[0] === nguon) out[parts[1]] = (out[parts[1]] || 0) + result.nguonCells[key];
    });
    return out;
  }

  /** Một dòng pivot: điểm x luồng -> {ngày: số tiền}. */
  function rowOf(result, pointKeyValue, stream) {
    var out = Object.create(null);
    Object.keys(result.cells).forEach(function (key) {
      var parts = key.split(SEP);
      if (parts[0] === pointKeyValue && parts[1] === stream) out[parts[2]] = result.cells[key];
    });
    return out;
  }

  /* ----------------------------------------------------------------- VAT */

  /**
   * Tách tổng tiền đã gồm thuế thành [chưa VAT, VAT].
   * VAT lấy phần dư để hai số cộng lại đúng bằng coVat - không lệch 1 đồng
   * vì làm tròn hai lần.
   */
  function splitVat(coVat, rate) {
    if (!coVat) return [0, 0];
    var chuaVat = Math.round(coVat / (1 + rate));
    return [chuaVat, coVat - chuaVat];
  }

  /**
   * Dựng danh sách hoá đơn.
   *
   * `theoNgay = false`: gộp cả kỳ, mỗi điểm một dòng.
   * `theoNgay = true`: mỗi điểm mỗi ngày một dòng, ngày hoá đơn là ngày phát sinh.
   * Sao kê về theo ngày nên chế độ này cho phép xuất hoá đơn hằng ngày thay vì
   * đợi hết kỳ.
   */
  function buildInvoices(result, rate, theoNgay) {
    var pointOrder = Object.keys(result.points).sort(function (a, b) {
      var pa = result.points[a], pb = result.points[b];
      return (pa.khuVuc || '').localeCompare(pb.khuVuc || '', 'vi')
        || (pa.tenDiem || '').localeCompare(pb.tenDiem || '', 'vi');
    });

    // Gom sẵn: khoá gộp -> { coVat, chiTietLuong }. Khoá là điểm, hoặc điểm+ngày.
    var buckets = Object.create(null);
    Object.keys(result.cells).forEach(function (cellKey) {
      var parts = cellKey.split(SEP);
      var groupKey = theoNgay ? parts[0] + SEP + parts[2] : parts[0];
      var bucket = buckets[groupKey];
      if (!bucket) {
        bucket = buckets[groupKey] = { pointKey: parts[0], ngay: theoNgay ? parts[2] : null,
          coVat: 0, chiTietLuong: {} };
      }
      bucket.coVat += result.cells[cellKey];
      bucket.chiTietLuong[parts[1]] = (bucket.chiTietLuong[parts[1]] || 0) + result.cells[cellKey];
    });

    var groups = Object.keys(buckets).map(function (key) { return buckets[key]; });
    groups.sort(function (a, b) {
      if (theoNgay && a.ngay !== b.ngay) return a.ngay < b.ngay ? -1 : 1;
      return pointOrder.indexOf(a.pointKey) - pointOrder.indexOf(b.pointKey);
    });

    var invoices = [];
    groups.forEach(function (group) {
      if (!group.coVat) return;
      var split = splitVat(group.coVat, rate);
      // Bỏ luồng có số 0 để bản kê chỉ hiện luồng thật sự phát sinh.
      var chiTiet = {};
      Object.keys(group.chiTietLuong).forEach(function (stream) {
        if (group.chiTietLuong[stream]) chiTiet[stream] = group.chiTietLuong[stream];
      });
      invoices.push({
        stt: invoices.length + 1,
        pointKey: group.pointKey,
        ngay: group.ngay,
        diem: result.points[group.pointKey],
        coVat: group.coVat,
        chuaVat: split[0],
        vat: split[1],
        chiTietLuong: chiTiet
      });
    });
    return invoices;
  }

  /** Tổng doanh thu theo ngày, tách theo luồng tiền. */
  function totalsByDate(result) {
    var out = Object.create(null);
    Object.keys(result.cells).forEach(function (cellKey) {
      var parts = cellKey.split(SEP);
      var ngay = parts[2], stream = parts[1];
      if (!out[ngay]) out[ngay] = { tong: 0, theoLuong: Object.create(null) };
      out[ngay].tong += result.cells[cellKey];
      out[ngay].theoLuong[stream] = (out[ngay].theoLuong[stream] || 0) + result.cells[cellKey];
    });
    return out;
  }

  /** Toàn bộ ngày trong kỳ, kể cả ngày không phát sinh. */
  function periodDates(kyTu, kyDen) {
    var out = [];
    var current = new Date(kyTu + 'T00:00:00Z');
    var end = new Date(kyDen + 'T00:00:00Z');
    while (current <= end && out.length < 400) {
      out.push(current.toISOString().slice(0, 10));
      current = new Date(current.getTime() + 86400000);
    }
    return out;
  }

  root.VatRec = {
    SEP: SEP,
    toDate: toDate,
    toInt: toInt,
    cleanText: cleanText,
    keyText: keyText,
    findHeader: findHeader,
    columnIndex: columnIndex,
    detectSheet: detectSheet,
    SHEET_KINDS: SHEET_KINDS,
    READERS: READERS,
    Catalog: Catalog,
    makePoint: makePoint,
    pointKey: pointKey,
    loadStoreCatalog: loadStoreCatalog,
    loadPointCatalog: loadPointCatalog,
    loadMomoCatalog: loadMomoCatalog,
    loadPointInfo: loadPointInfo,
    aggregate: aggregate,
    totalsByPoint: totalsByPoint,
    totalOf: totalOf,
    rowOf: rowOf,
    rowOfNguon: rowOfNguon,
    pointsOfNguon: pointsOfNguon,
    splitVat: splitVat,
    buildInvoices: buildInvoices,
    totalsByDate: totalsByDate,
    periodDates: periodDates
  };
}(typeof self !== 'undefined' ? self : this));
