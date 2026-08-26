/*
 * Dựng workbook .xlsx đầu ra bằng SheetJS.
 *
 * Bố cục theo đúng file VAT mẫu: danh sách hoá đơn để nhập Misa, bản kê chi tiết,
 * một sheet pivot cho mỗi luồng tiền, và một sheet đối soát.
 */
(function (root) {
  'use strict';

  var TIEN = '#,##0';
  var NGAY = 'dd/mm/yyyy';

  var DS_HEADER = ['STT', 'Ngày HĐ', 'Số HĐ', 'Tên khách hàng', 'Mã số thuế khách hàng',
    'Địa chỉ khách hàng', 'Email nhận hóa đơn', 'Nội dung xuất hóa đơn', 'Số lượng', 'ĐVT',
    'Đơn giá', 'Chưa VAT', 'VAT', 'Có VAT', 'Khu vực', 'Dịch vụ', 'Hình thức hợp tác',
    'Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế'];

  var DS_WIDTHS = [5, 12, 9, 24, 17, 17, 17, 25, 8, 6, 11, 15, 13, 15, 11, 9, 15, 32, 24];

  /**
   * Dựng workbook hoàn chỉnh.
   *
   * @param {object} opts - coSo, result, invoices, kyTu, kyDen, ngayHoaDon, noiDung, tenKhach
   * @returns {object} workbook của SheetJS, sẵn sàng cho XLSX.write
   */
  function buildWorkbook(opts) {
    var XLSXLib = root.XLSX;
    var book = XLSXLib.utils.book_new();

    addSheet(XLSXLib, book, 'DS xuất HĐ MTT', sheetDanhSach(opts), DS_WIDTHS);
    addSheet(XLSXLib, book, 'kê ds xuất HĐ MTT', sheetBanKe(opts));

    opts.result.streams.forEach(function (stream) {
      var built = sheetPivot(opts, stream);
      if (built.rows.length > 1) addSheet(XLSXLib, book, safeTitle(stream, book), built);
    });

    addSheet(XLSXLib, book, 'Tổng theo ngày', sheetTheoNgay(opts));
    addSheet(XLSXLib, book, 'Đối soát', sheetDoiSoat(opts));
    return book;
  }

  /**
   * Ghi mảng dòng vào một sheet mới, kèm định dạng số và khoá dòng tiêu đề.
   * `built.formats` là bản đồ "hàng,cột" -> mã định dạng (0-based, tính cả tiêu đề).
   */
  function addSheet(XLSXLib, book, name, built, widths) {
    var sheet = XLSXLib.utils.aoa_to_sheet(built.rows, { cellDates: true });
    Object.keys(built.formats || {}).forEach(function (key) {
      var parts = key.split(',');
      var address = XLSXLib.utils.encode_cell({ r: +parts[0], c: +parts[1] });
      if (sheet[address]) sheet[address].z = built.formats[key];
    });
    var columnCount = built.rows.reduce(function (max, row) { return Math.max(max, row.length); }, 0);
    sheet['!cols'] = [];
    for (var c = 0; c < columnCount; c += 1) {
      sheet['!cols'].push({ wch: (widths && widths[c]) || built.widths && built.widths[c] || 14 });
    }
    sheet['!freeze'] = { xSplit: 0, ySplit: 1 };
    XLSXLib.utils.book_append_sheet(book, sheet, name);
    return sheet;
  }

  /** Sheet nhập vào Misa - đúng thứ tự cột của file mẫu. */
  function sheetDanhSach(opts) {
    var rows = [DS_HEADER.slice()];
    var formats = {};
    opts.invoices.forEach(function (invoice, i) {
      var r = i + 1;
      rows.push([
        invoice.stt,
        excelDate(invoice.ngay || opts.ngayHoaDon),
        null, // Số HĐ do Misa cấp
        opts.tenKhach,
        null, null, null,
        opts.noiDung,
        1,
        'Kỳ',
        null,
        invoice.chuaVat,
        invoice.vat,
        invoice.coVat,
        invoice.diem.khuVuc,
        invoice.diem.dichVu,
        invoice.diem.hinhThucHopTac,
        invoice.diem.tenDiem,
        invoice.diem.maMisa
      ]);
      formats[r + ',1'] = NGAY;
      [11, 12, 13].forEach(function (c) { formats[r + ',' + c] = TIEN; });
    });
    pushTotal(rows, formats, [11, 12, 13], DS_HEADER.length);
    return { rows: rows, formats: formats };
  }

  /** Bản kê chi tiết: thêm cột tách theo từng luồng tiền để đối chiếu ngược. */
  function sheetBanKe(opts) {
    var streams = [];
    opts.invoices.forEach(function (invoice) {
      Object.keys(invoice.chiTietLuong).forEach(function (stream) {
        if (streams.indexOf(stream) < 0) streams.push(stream);
      });
    });
    streams.sort();

    var header = ['STT', 'Tháng', 'Ngày HĐ', 'Số HĐ', 'Tên khách hàng', 'Nội dung xuất hóa đơn',
      'Tổng TT HĐ htoan Misa', 'Khu vực', 'Dịch vụ', 'Hình thức hợp tác',
      'Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế', 'Pháp nhân', 'Kỳ'].concat(streams);
    var rows = [header];
    var formats = {};
    var moneyColumns = [6].concat(streams.map(function (_, i) { return 14 + i; }));
    var ky = viDate(opts.kyTu) + ' - ' + viDate(opts.kyDen);

    opts.invoices.forEach(function (invoice, i) {
      var r = i + 1;
      rows.push([
        invoice.stt,
        Number((invoice.ngay || opts.ngayHoaDon).slice(5, 7)),
        excelDate(invoice.ngay || opts.ngayHoaDon), null, opts.tenKhach, opts.noiDung,
        invoice.coVat, invoice.diem.khuVuc, invoice.diem.dichVu, invoice.diem.hinhThucHopTac,
        invoice.diem.tenDiem, invoice.diem.maMisa, invoice.diem.phapNhan, ky
      ].concat(streams.map(function (stream) { return invoice.chiTietLuong[stream] || 0; })));
      formats[r + ',2'] = NGAY;
      moneyColumns.forEach(function (c) { formats[r + ',' + c] = TIEN; });
    });
    pushTotal(rows, formats, moneyColumns, header.length);
    return { rows: rows, formats: formats, widths: [5, 7, 12, 9, 24, 25, 18, 11, 9, 15, 32, 24, 12, 22] };
  }

  /** Một sheet cho mỗi luồng tiền: điểm xuất hoá đơn x ngày. */
  function sheetPivot(opts, stream) {
    var V = root.VatRec;
    var dates = V.periodDates(opts.kyTu, opts.kyDen);
    var header = ['STT', 'Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế', 'Khu vực', 'Dịch vụ', 'Tổng']
      .concat(dates.map(function (date) { return date.slice(8) + '/' + date.slice(5, 7); }));
    var rows = [header];
    var formats = {};
    var moneyColumns = [];
    for (var c = 5; c < header.length; c += 1) moneyColumns.push(c);

    var keys = Object.keys(opts.result.points).sort(function (a, b) {
      return (opts.result.points[a].tenDiem || '').localeCompare(opts.result.points[b].tenDiem || '', 'vi');
    });
    keys.forEach(function (key) {
      var perDate = V.rowOf(opts.result, key, stream);
      var total = 0;
      dates.forEach(function (date) { total += perDate[date] || 0; });
      if (!total) return;
      var point = opts.result.points[key];
      var r = rows.length;
      rows.push([rows.length, point.tenDiem, point.maMisa, point.khuVuc, point.dichVu, total]
        .concat(dates.map(function (date) { return perDate[date] || 0; })));
      moneyColumns.forEach(function (c) { formats[r + ',' + c] = TIEN; });
    });
    pushTotal(rows, formats, moneyColumns, header.length);
    return { rows: rows, formats: formats, widths: [5, 32, 24, 11, 9, 15] };
  }

  /**
   * Doanh thu từng ngày trong kỳ, tách theo luồng tiền.
   * Sao kê về theo ngày nên đây là bảng để theo dõi hằng ngày và phát hiện ngay
   * ngày nào hụt số hay thiếu file.
   */
  function sheetTheoNgay(opts) {
    var V = root.VatRec;
    var dates = V.periodDates(opts.kyTu, opts.kyDen);
    var byDate = V.totalsByDate(opts.result);
    var streams = opts.result.streams;

    var header = ['Ngày', 'Thứ', 'Tổng có VAT', 'Chưa VAT', 'VAT', 'Số điểm phát sinh'].concat(streams);
    var rows = [header];
    var formats = {};
    var moneyColumns = [2, 3, 4];
    streams.forEach(function (_, i) { moneyColumns.push(6 + i); });

    var soDiemTheoNgay = demDiemTheoNgay(opts.result);
    dates.forEach(function (date, i) {
      var entry = byDate[date] || { tong: 0, theoLuong: {} };
      var split = V.splitVat(entry.tong, opts.rate);
      var r = i + 1;
      rows.push([excelDate(date), thuTrongTuan(date), entry.tong, split[0], split[1],
        soDiemTheoNgay[date] || 0
      ].concat(streams.map(function (stream) { return entry.theoLuong[stream] || 0; })));
      formats[r + ',0'] = NGAY;
      moneyColumns.forEach(function (c) { formats[r + ',' + c] = TIEN; });
    });
    pushTotal(rows, formats, moneyColumns, header.length);
    return { rows: rows, formats: formats, widths: [12, 8, 16, 16, 14, 15] };
  }

  /** Số điểm có phát sinh doanh thu trong từng ngày. */
  function demDiemTheoNgay(result) {
    var V = root.VatRec;
    var theoNgay = Object.create(null);
    Object.keys(result.cells).forEach(function (cellKey) {
      if (!result.cells[cellKey]) return;
      var parts = cellKey.split(V.SEP);
      if (!theoNgay[parts[2]]) theoNgay[parts[2]] = Object.create(null);
      theoNgay[parts[2]][parts[0]] = true;
    });
    var out = Object.create(null);
    Object.keys(theoNgay).forEach(function (date) { out[date] = Object.keys(theoNgay[date]).length; });
    return out;
  }

  var THU = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

  function thuTrongTuan(iso) {
    return THU[new Date(iso + 'T00:00:00Z').getUTCDay()];
  }

  /** Bảng đối soát: tổng theo luồng, số hoá đơn, cảnh báo, mã chưa map. */
  function sheetDoiSoat(opts) {
    var V = root.VatRec;
    var result = opts.result;
    var rows = [];
    var formats = {};

    function line(a, b, c, d, money) {
      var r = rows.length;
      rows.push([a === undefined ? null : a, b === undefined ? null : b,
        c === undefined ? null : c, d === undefined ? null : d]);
      if (money) { formats[r + ',2'] = TIEN; formats[r + ',3'] = TIEN; }
      return r;
    }

    line('ĐỐI SOÁT - cơ sở ' + opts.coSo);
    line('Kỳ báo cáo', viDate(opts.kyTu) + ' -> ' + viDate(opts.kyDen));
    line('Ngày hoá đơn', viDate(opts.ngayHoaDon));
    line('Thuế suất', (opts.rate * 100).toFixed(0) + '%');
    line('Kiểu xuất hoá đơn', opts.theoNgay ? 'Theo từng ngày' : 'Gộp cả kỳ');
    line();

    line('Tổng theo luồng tiền', '', 'Số tiền');
    result.streams.forEach(function (stream) {
      line('', stream, V.totalOf(result, stream), null, true);
    });
    line('', 'TỔNG CỘNG', V.totalOf(result), null, true);
    line();

    var tongCoVat = opts.invoices.reduce(function (a, i) { return a + i.coVat; }, 0);
    line('Hoá đơn', '', 'Số tiền');
    line('', 'Số điểm xuất hoá đơn', opts.invoices.length);
    line('', 'Chưa VAT', opts.invoices.reduce(function (a, i) { return a + i.chuaVat; }, 0), null, true);
    line('', 'VAT', opts.invoices.reduce(function (a, i) { return a + i.vat; }, 0), null, true);
    line('', 'Có VAT', tongCoVat, null, true);
    line('', 'Lệch so với tổng luồng tiền', V.totalOf(result) - tongCoVat, null, true);
    line();

    line('Cảnh báo', '', 'Số lượng', 'Số tiền');
    line('', 'Giao dịch không đọc được ngày', result.khongCoNgay);
    line('', 'Tiền của giao dịch ngoài kỳ (đã loại)', null, result.ngoaiKy, true);
    line('', 'Tiền vãng lai (không có mã điểm bán)', result.vangLaiSoGiaoDich, result.vangLai, true);
    line('', 'Tiền của điểm thuộc pháp nhân khác (đã loại)', null, result.loaiKhacPhapNhan, true);
    line('', 'Giao dịch trùng mã (đã bỏ bản thứ hai)', result.trungLapSoGiaoDich, result.trungLap, true);
    line();

    line('Mã điểm bán chưa có trong danh mục', '', 'Số GD', 'Số tiền');
    if (!result.chuaMap.length) {
      line('', '(không có - mọi mã đều tra được)');
    } else {
      result.chuaMap.forEach(function (item) {
        line(item.channel, item.code, item.soGiaoDich, item.soTien, true);
      });
    }

    if (result.trungLapSoGiaoDich) {
      line();
      line('Giao dịch trùng mã đã bị bỏ', '', '', 'Số tiền');
      line('', 'Thường do chọn nhầm sheet của kỳ cũ trong cùng một file.');
      result.viDuTrungLap.forEach(function (item) {
        line(item.channel + ' / ' + item.stream, item.ref, null, item.soTien, true);
      });
      if (result.trungLapSoGiaoDich > result.viDuTrungLap.length) {
        line('', 'và ' + (result.trungLapSoGiaoDich - result.viDuTrungLap.length) + ' giao dịch nữa');
      }
    }

    return { rows: rows, formats: formats, widths: [34, 42, 18, 18] };
  }

  /** Dòng TỔNG cuối bảng, dùng công thức SUM để người dùng sửa số vẫn đúng. */
  function pushTotal(rows, formats, moneyColumns, width) {
    if (rows.length <= 1) return;
    var lastData = rows.length; // 1-based: dòng dữ liệu cuối cùng
    var total = new Array(width).fill(null);
    total[0] = 'TỔNG';
    rows.push(total);
    var r = rows.length - 1;
    moneyColumns.forEach(function (c) {
      var letter = columnLetter(c);
      rows[r][c] = { f: 'SUM(' + letter + '2:' + letter + lastData + ')' };
      formats[r + ',' + c] = TIEN;
    });
  }

  function columnLetter(index) {
    var letter = '';
    var n = index;
    while (n >= 0) {
      letter = String.fromCharCode(65 + (n % 26)) + letter;
      n = Math.floor(n / 26) - 1;
    }
    return letter;
  }

  /** Chuỗi ISO -> Date để SheetJS ghi thành ô ngày thật, không phải chữ. */
  function excelDate(iso) {
    var parts = iso.split('-');
    return new Date(+parts[0], +parts[1] - 1, +parts[2]);
  }

  function viDate(iso) {
    var parts = iso.split('-');
    return parts[2] + '/' + parts[1] + '/' + parts[0];
  }

  /** Tên sheet Excel: tối đa 31 ký tự, không chứa []:*?/\, không trùng. */
  function safeTitle(name, book) {
    var cleaned = name.replace(/[[\]:*?/\\]/g, ' ').trim().slice(0, 31) || 'Luồng';
    if (book.SheetNames.indexOf(cleaned) < 0) return cleaned;
    for (var suffix = 2; suffix < 100; suffix += 1) {
      var candidate = cleaned.slice(0, 31 - String(suffix).length - 1) + ' ' + suffix;
      if (book.SheetNames.indexOf(candidate) < 0) return candidate;
    }
    return cleaned.slice(0, 28) + '~';
  }

  root.VatRecReport = { buildWorkbook: buildWorkbook, safeTitle: safeTitle };
}(typeof self !== 'undefined' ? self : this));
