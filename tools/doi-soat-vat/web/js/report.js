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
  // Ô không phát sinh hiện dấu gạch cho dễ dò mắt, giống bảng đang làm tay.
  var TIEN_GACH = '#,##0;-#,##0;"-"';

  /*
   * Thứ tự và tên cột chép đúng theo file VAT mẫu, kể cả chỗ tên cột không khớp
   * nội dung: "Số hợp đồng (nếu có)" trong file gốc đang chứa hình thức hợp tác
   * (CSE), còn "Mã điểm nội bộ" chứa tên điểm xuất hoá đơn. Giữ nguyên như vậy để
   * dán thẳng vào quy trình cũ được, không phải sắp lại cột.
   */
  var DS_HEADER = ['STT', 'Ngày HĐ', 'Số HĐ', 'Tên khách hàng', 'Mã số thuế khách hàng',
    'Địa chỉ khách hàng', 'Email nhận hóa đơn', 'Nội dung xuất hóa đơn', 'Số lượng', 'ĐVT',
    'Thành tiền', 'Chưa VAT', 'VAT', 'Có VAT', 'Khu vực', 'Dịch vụ', 'Số hợp đồng (nếu có)',
    'Mã điểm nội bộ', 'Mã điểm misa', 'Ghi chú', '', 'Địa chỉ'];

  /*
   * 22 cột đầu của bản kê cũng chép đúng file mẫu (21 cột gốc + "Pháp nhân");
   * phần tách theo từng luồng tiền nối thêm phía sau, để dán 21 cột đầu vào quy
   * trình cũ mà vẫn giữ chỗ đối chiếu ngược.
   */
  var KE_HEADER = ['STT', 'Tháng', 'Ngày HĐ', 'lọc trùng', 'Số HĐ', 'Tên khách hàng',
    'Mã số thuế khách hàng', 'Địa chỉ khách hàng', 'Email nhận hóa đơn', 'Nội dung hóa đơn',
    'Tổng TT HĐ htoan Misa', 'đã xuất hóa đơn', 'Khu vực', 'Dịch vụ', 'Số hợp đồng',
    'Mã đối tượng nội bộ', 'Mã điểm ghi chú HT Misa', 'Mã NCC HT Misa', 'ghi chú',
    'Dịch vụ thu hộ', 'Những lưu ý khác (thời hạn hợp đồng, …)', 'Pháp nhân'];

  var DS_WIDTHS = [5, 12, 9, 24, 17, 17, 17, 25, 8, 6, 13, 15, 13, 15, 11, 10, 16, 32, 24, 12, 4, 14];

  /**
   * Dựng workbook hoàn chỉnh.
   *
   * @param {object} opts - coSo, result, invoices, kyTu, kyDen, ngayHoaDon, noiDung, tenKhach
   * @returns {object} workbook của SheetJS, sẵn sàng cho XLSX.write
   */
  function buildWorkbook(opts) {
    var XLSXLib = root.XLSX;
    var book = XLSXLib.utils.book_new();

    // Trang tổng hợp đứng trước, các tab kiểm từng file đứng sau.
    addSheet(XLSXLib, book, 'DS xuất HĐ MTT', sheetDanhSach(opts), DS_WIDTHS);
    addSheet(XLSXLib, book, 'kê ds xuất HĐ MTT', sheetBanKe(opts));

    opts.result.streams.forEach(function (stream) {
      var built = sheetPivot(opts, stream);
      if (built.rows.length > 1) addSheet(XLSXLib, book, safeTitle(stream, book), built);
    });

    addSheet(XLSXLib, book, 'Tổng theo ngày', sheetTheoNgay(opts));

    addSheet(XLSXLib, book, 'Đối soát', sheetDoiSoat(opts));

    // Mỗi file đầu vào một tab riêng, để so thẳng với chính file gốc rồi mới tin
    // số tổng. Bảng lọc dựng từ giao dịch thô nên hiện cả mã chưa có danh mục.
    addSheet(XLSXLib, book, 'Đối soát theo file', sheetTheoFile(opts));
    (opts.loc || []).forEach(function (kenh, i) {
      addSheet(XLSXLib, book, safeTitle('F' + (i + 1) + ' ' + tenNgan(kenh.nguon), book),
        sheetLoc(opts, kenh));
    });

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
    if (built.merges && built.merges.length) sheet['!merges'] = built.merges;
    sheet['!freeze'] = { xSplit: 0, ySplit: built.freezeRow || 1 };
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
        null,                            // Số HĐ - Misa cấp khi nhập
        opts.tenKhach,
        null,                            // Mã số thuế khách hàng
        null,                            // Địa chỉ khách hàng
        null,                            // Email nhận hóa đơn
        opts.noiDung,
        1,
        'Kỳ',
        null,                            // Thành tiền - để trống như file mẫu
        invoice.chuaVat,
        invoice.vat,
        invoice.coVat,
        invoice.diem.khuVuc,
        invoice.diem.dichVu,
        invoice.diem.hinhThucHopTac,     // cột "Số hợp đồng (nếu có)"
        invoice.diem.tenDiem,            // cột "Mã điểm nội bộ"
        invoice.diem.maMisa,
        null,                            // Ghi chú
        null,
        null                             // Địa chỉ
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

    var header = KE_HEADER.concat(streams);
    var rows = [header];
    var formats = {};
    var moneyColumns = [10].concat(streams.map(function (_, i) {
      return KE_HEADER.length + i;
    }));

    opts.invoices.forEach(function (invoice, i) {
      var r = i + 1;
      var ngay = invoice.ngay || opts.ngayHoaDon;
      rows.push([
        invoice.stt,
        Number(ngay.slice(5, 7)),
        excelDate(ngay),
        null,                              // lọc trùng
        null,                              // Số HĐ - Misa cấp khi nhập
        opts.tenKhach,
        null, null, null,                  // MST / địa chỉ / email khách hàng
        opts.noiDung,
        invoice.coVat,
        null,                              // đã xuất hóa đơn
        invoice.diem.khuVuc,
        invoice.diem.dichVu,
        invoice.diem.hinhThucHopTac,       // cột "Số hợp đồng"
        invoice.diem.tenDiem,              // cột "Mã đối tượng nội bộ"
        invoice.diem.maMisa,
        null,                              // Mã NCC HT Misa
        't' + ngay.slice(5, 7) + '/' + ngay.slice(2, 4),
        Object.keys(invoice.chiTietLuong).join(', '),
        null                               // Những lưu ý khác
      ].concat([invoice.diem.phapNhan])
       .concat(streams.map(function (stream) { return invoice.chiTietLuong[stream] || 0; })));
      formats[r + ',2'] = NGAY;
      moneyColumns.forEach(function (c) { formats[r + ',' + c] = TIEN; });
    });
    pushTotal(rows, formats, moneyColumns, header.length);
    return {
      rows: rows, formats: formats,
      widths: [5, 7, 12, 9, 9, 24, 17, 17, 17, 25, 18, 12, 11, 10, 15, 32, 24, 14, 10, 24, 26, 12]
    };
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

  /**
   * Bảng đối soát theo từng file đầu vào.
   *
   * Số tổng chỉ đáng tin khi từng file đã đúng, nên bảng này đặt cạnh nhau: file,
   * luồng tiền đọc được, số giao dịch, số tiền vào hoá đơn, và các phần bị tách
   * riêng của chính file đó.
   */
  function sheetTheoFile(opts) {
    var result = opts.result;
    var header = ['#', 'File', 'Luồng tiền đọc được', 'Số GD', 'Số điểm', 'Vào hoá đơn',
      'Chưa có danh mục', 'Vãng lai', 'Ngoài kỳ', 'Trùng mã', 'Pháp nhân khác', 'Không đọc được ngày'];
    var rows = [header];
    var formats = {};
    var moneyColumns = [5, 6, 7, 8, 9, 10];

    result.nguonList.forEach(function (nguon, i) {
      var tk = result.nguonStats[nguon];
      var r = rows.length;
      rows.push([
        i + 1, nguon, tk.luong.join(', '), tk.soGiaoDich, tk.soDiem, tk.soTien,
        tk.chuaMapSoTien, tk.vangLai, tk.ngoaiKy, tk.trungLap, tk.loaiKhacPhapNhan, tk.khongCoNgay
      ]);
      moneyColumns.forEach(function (c) { formats[r + ',' + c] = TIEN; });
      formats[r + ',3'] = TIEN;
    });
    pushTotal(rows, formats, [3].concat(moneyColumns), header.length);
    return {
      rows: rows, formats: formats,
      widths: [4, 44, 30, 10, 9, 16, 16, 14, 14, 13, 15, 17]
    };
  }

  /** Tên file rút gọn để đặt tên tab (Excel giới hạn 31 ký tự). */
  function tenNgan(nguon) {
    var ten = String(nguon).replace(/\.[^.]+$/, '');
    return ten.length > 24 ? ten.slice(0, 24) : ten;
  }

  /*
   * Bảng lọc dữ liệu của một file - chọn một ngày là ra số xuất hoá đơn của từng mã.
   *
   * Phần đầu ghi lại tên file và các số bị tách riêng, để nhìn một chỗ là biết
   * file đó đã đọc đủ chưa. Nguồn có thu phí (Payoo) thì nối thêm hai khối cột
   * đúng như bảng đang làm tay: phí cổng thu, và tiền cổng thực trả về tài khoản
   * (= số xuất hoá đơn trừ phí). Nguồn không có cột phí thì chỉ một khối, khỏi
   * rác cột toàn số 0.
   */
  function sheetLoc(opts, kenh) {
    var rows = kenh.rows;
    var dates = kenh.ngay;
    var nhanNgay = dates.map(viDate);
    var tk = kenh.thongKe;

    var out = [];
    var formats = {};
    var merges = [];

    function ghiChu(nhan, giaTri, laTien) {
      var r = out.length;
      out.push([nhan, giaTri]);
      if (laTien) formats[r + ',1'] = TIEN;
    }

    ghiChu('File', kenh.nguon);
    ghiChu('Luồng tiền đọc được', kenh.luong.join(', '));
    ghiChu('Kỳ', viDate(opts.kyTu) + ' - ' + viDate(opts.kyDen));
    ghiChu('Số giao dịch tính vào hoá đơn', tk.soGiaoDich);
    ghiChu('Số tiền vào hoá đơn', tk.soTien, true);
    ghiChu('Chưa có trong danh mục', tk.chuaMapSoTien, true);
    ghiChu('Vãng lai (không mã điểm bán)', tk.vangLai, true);
    ghiChu('Ngoài kỳ (đã loại)', tk.ngoaiKy, true);
    ghiChu('Trùng mã (đã bỏ bản thứ hai)', tk.trungLap, true);
    ghiChu('Điểm thuộc pháp nhân khác (đã loại)', tk.loaiKhacPhapNhan, true);
    out.push([]);

    var headerRow = out.length;
    var header = ['STT', 'Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế', 'Mã điểm bán', 'Nhóm']
      .concat(nhanNgay, ['Tổng xuất hóa đơn']);
    if (kenh.coPhi) {
      header = header.concat(nhanNgay, ['Tổng tiền phí'])
        .concat(nhanNgay, ['Tổng tiền cổng phải trả']);
    }
    out.push(header);

    var moneyColumns = [];
    for (var c = 5; c < header.length; c += 1) moneyColumns.push(c);

    var stt = 0;
    var dauDiem = headerRow + 1;
    var diemTruoc = null;
    rows.forEach(function (row, i) {
      var r = headerRow + 1 + i;
      var nhanDiem = row.tenDiem || row.code;
      if (nhanDiem !== diemTruoc) {
        if (diemTruoc !== null && r - dauDiem > 1) {
          merges.push({ s: { r: dauDiem, c: 0 }, e: { r: r - 1, c: 0 } });
        }
        stt += 1;
        dauDiem = r;
        diemTruoc = nhanDiem;
      }
      // STT chỉ ghi ở dòng đầu của mỗi điểm, các dòng còn lại gộp ô vào dòng đó.
      var line = [r === dauDiem ? stt : null, row.tenDiem, row.maMisa, row.code, row.nhom]
        .concat(dates.map(function (d) { return row.tien[d] || 0; }), [row.tongTien]);
      if (kenh.coPhi) {
        line = line
          .concat(dates.map(function (d) { return row.phi[d] || 0; }), [row.tongPhi])
          .concat(dates.map(function (d) { return (row.tien[d] || 0) - (row.phi[d] || 0); }),
            [row.tongTien - row.tongPhi]);
      }
      out.push(line);
      moneyColumns.forEach(function (c) { formats[r + ',' + c] = TIEN_GACH; });
    });
    var dongCuoi = headerRow + rows.length;
    if (rows.length && dongCuoi - dauDiem > 0) {
      merges.push({ s: { r: dauDiem, c: 0 }, e: { r: dongCuoi, c: 0 } });
    }

    if (rows.length) {
      var totalRow = new Array(header.length).fill(null);
      totalRow[0] = 'TỔNG';
      out.push(totalRow);
      var tr = out.length - 1;
      moneyColumns.forEach(function (c) {
        var letter = columnLetter(c);
        out[tr][c] = { f: 'SUM(' + letter + (headerRow + 2) + ':' + letter + tr + ')' };
        formats[tr + ',' + c] = TIEN_GACH;
      });
    }

    return {
      rows: out, formats: formats, merges: merges,
      widths: [5, 30, 24, 30, 18], freezeRow: headerRow + 1
    };
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

  /**
   * Workbook chỉ có đúng bảng lọc của một file đầu vào.
   *
   * Mỗi đối soát tải riêng được một file, khỏi phải mở file tổng hợp rồi mò
   * đúng tab — gửi cho người khác kiểm cũng chỉ gửi đúng phần của họ.
   */
  function buildOneWorkbook(opts, kenh) {
    var XLSXLib = root.XLSX;
    var book = XLSXLib.utils.book_new();
    addSheet(XLSXLib, book, safeTitle('Lọc ' + tenNgan(kenh.nguon), book), sheetLoc(opts, kenh));
    return book;
  }

  root.VatRecReport = {
    buildWorkbook: buildWorkbook, buildOneWorkbook: buildOneWorkbook, safeTitle: safeTitle,
    DS_HEADER: DS_HEADER, KE_HEADER: KE_HEADER
  };
}(typeof self !== 'undefined' ? self : this));
