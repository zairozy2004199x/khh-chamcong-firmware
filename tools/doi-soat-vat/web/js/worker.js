/*
 * Web Worker: đọc và xử lý Excel ở luồng nền.
 *
 * File sao kê QR to nhất trong bộ dữ liệu thật là 23 MB / 75.000 dòng và mất
 * khoảng 20 giây để đọc. Làm việc đó ở luồng chính thì trình duyệt đứng hình,
 * nên toàn bộ phần nặng nằm ở đây và báo tiến độ về qua postMessage.
 */
/* global importScripts, XLSX, VatRec, VatRecReport */
'use strict';

// Truyền tiếp chuỗi truy vấn phiên bản của chính file worker sang các file con,
// để cập nhật không bị trình duyệt giữ lại bản cũ trong bộ đệm.
var VERSION_QUERY = self.location.search || '';
importScripts(
  '../vendor/xlsx.full.min.js' + VERSION_QUERY,
  'core.js' + VERSION_QUERY,
  'report.js' + VERSION_QUERY
);

var state = {
  // tên file -> { sheets: [{ name, kind, label, headerRow, rows }] }
  files: Object.create(null)
};

var HEADER_SCAN_ROWS = 30;

/**
 * Đọc sheet thành mảng dòng, giới hạn ở vùng thật sự có dữ liệu.
 *
 * Excel hay khai `!ref` rộng hơn dữ liệu thật (có file khai tới 16.000 cột),
 * nên bám theo `!data` của chế độ dense để không dựng ra hàng triệu ô rỗng.
 *
 * @param {object} worksheet
 * @param {number} [maxRows] chỉ đọc bấy nhiêu dòng đầu (dùng khi dò tiêu đề)
 */
function readRows(worksheet, maxRows) {
  if (!worksheet || !worksheet['!ref']) return [];
  var range = XLSX.utils.decode_range(worksheet['!ref']);
  var lastRow = range.e.r;
  if (worksheet['!data']) lastRow = Math.min(lastRow, worksheet['!data'].length - 1);
  if (maxRows) lastRow = Math.min(lastRow, range.s.r + maxRows - 1);
  if (lastRow < range.s.r) return [];
  var clamped = XLSX.utils.encode_range({
    s: { r: range.s.r, c: range.s.c },
    e: { r: lastRow, c: range.e.c }
  });
  return XLSX.utils.sheet_to_json(worksheet, {
    header: 1, raw: true, defval: null, blankrows: true, range: clamped
  });
}

self.onmessage = function (event) {
  var message = event.data || {};
  try {
    if (message.type === 'scan') scan(message);
    else if (message.type === 'run') run(message);
    else if (message.type === 'reset') { state.files = Object.create(null); }
    else post('error', { message: 'Lệnh không hợp lệ: ' + message.type });
  } catch (err) {
    post('error', { message: (err && err.message) || String(err), stack: err && err.stack });
  }
};

function post(type, payload, transfer) {
  payload = payload || {};
  payload.type = type;
  // `transfer` chuyển quyền sở hữu ArrayBuffer sang luồng chính thay vì sao chép -
  // file .xlsx vài MB thì đây là khác biệt thấy được.
  if (transfer) self.postMessage(payload, transfer);
  else self.postMessage(payload);
}

/**
 * Đọc một file và phân loại từng sheet trong đó.
 * Sheet không nhận ra loại thì bỏ qua - file mẫu có rất nhiều sheet phụ.
 */
function scan(message) {
  var name = message.name;
  post('progress', { name: name, phase: 'Đang mở file...' });

  var book = XLSX.read(new Uint8Array(message.buffer), {
    type: 'array', cellDates: true, dense: true, sheetStubs: false
  });

  var sheets = [];
  book.SheetNames.forEach(function (sheetName, i) {
    post('progress', {
      name: name,
      phase: 'Đang đọc sheet ' + (i + 1) + '/' + book.SheetNames.length + ': ' + sheetName
    });
    var worksheet = book.Sheets[sheetName];

    // Nhận diện chỉ cần vài dòng đầu. Đọc trước một phần rồi mới đọc hết sheet
    // đúng loại: các file mẫu có sheet rộng tới 16.000 cột, đọc hết mọi sheet
    // vừa chậm vừa tốn bộ nhớ vô ích.
    var detected = VatRec.detectSheet(readRows(worksheet, HEADER_SCAN_ROWS));
    if (!detected) return;

    var rows = readRows(worksheet);
    sheets.push({
      name: sheetName,
      kind: detected.kind,
      label: detected.label,
      headerRow: detected.headerRow,
      soDong: Math.max(0, rows.length - detected.headerRow - 1),
      rows: rows
    });
  });

  state.files[name] = { sheets: sheets };
  post('scanned', {
    name: name,
    sheets: sheets.map(function (sheet) {
      return {
        name: sheet.name, kind: sheet.kind, label: sheet.label,
        headerRow: sheet.headerRow, soDong: sheet.soDong
      };
    })
  });
}

/**
 * Chạy tổng hợp trên các sheet người dùng đã chọn.
 *
 * `message.chon` là mảng { file, sheet, dung, luong } - `dung` cho bật/tắt từng
 * sheet, `luong` là tên luồng tiền hiển thị trên báo cáo.
 */
function run(message) {
  post('progress', { phase: 'Đang dựng danh mục điểm...' });

  var catalog = new VatRec.Catalog();
  var chuaGanDiem = [];
  var chosen = message.chon.filter(function (item) { return item.dung; });

  chosen.forEach(function (item) {
    var sheet = findSheet(item);
    if (!sheet) return;
    if (sheet.kind === 'catalog_store') {
      chuaGanDiem = chuaGanDiem.concat(VatRec.loadStoreCatalog(sheet.rows, sheet.headerRow, catalog));
    } else if (sheet.kind === 'catalog_vnpay') {
      VatRec.loadPointCatalog(sheet.rows, sheet.headerRow, 'vnpay', 'Mã điểm thu', catalog);
    } else if (sheet.kind === 'catalog_payoo') {
      VatRec.loadPointCatalog(sheet.rows, sheet.headerRow, 'payoo', 'Chi nhánh', catalog);
    } else if (sheet.kind === 'catalog_momo') {
      VatRec.loadMomoCatalog(sheet.rows, sheet.headerRow, catalog);
    }
  });

  // Bảng thông tin điểm nạp sau cùng để bù khu vực / mã misa / pháp nhân cho
  // những điểm mà danh mục của cổng bỏ trống.
  chosen.forEach(function (item) {
    var sheet = findSheet(item);
    if (sheet && sheet.kind === 'catalog_diem') {
      VatRec.loadPointInfo(sheet.rows, sheet.headerRow, catalog);
    }
  });

  // Danh mục người dùng tự thêm trong giao diện - dùng cho gian hàng Zalo và
  // các mã mới chưa kịp cập nhật vào file danh mục.
  (message.danhMucThem || []).forEach(function (item) {
    if (!item.channel || !item.code || !item.tenDiem) return;
    catalog.add(item.channel, item.code, VatRec.makePoint(item));
  });

  post('progress', { phase: 'Đang đọc giao dịch...' });
  var txns = [];
  chosen.forEach(function (item) {
    var sheet = findSheet(item);
    if (!sheet) return;
    var reader = VatRec.READERS[sheet.kind];
    if (!reader) return;
    post('progress', { phase: 'Đang đọc ' + item.file + ' / ' + sheet.name });
    // Gắn tên file vào từng giao dịch để phần báo cáo tách được theo file.
    txns = txns.concat(reader(sheet.rows, sheet.headerRow, item.luong || sheet.name, item.file));
  });

  post('progress', { phase: 'Đang tổng hợp ' + txns.length.toLocaleString('vi-VN') + ' giao dịch...' });
  var result = VatRec.aggregate(txns, catalog, {
    kyTu: message.kyTu, kyDen: message.kyDen, phapNhan: message.phapNhan || null
  });
  var invoices = VatRec.buildInvoices(result, message.rate, message.theoNgay);
  // Mỗi cổng một bảng lọc riêng, dựng thẳng từ giao dịch thô chứ không qua bước
  // tra danh mục, nên vẫn ra số kể cả khi file chỉ có mỗi sheet dữ liệu gốc.
  var loc = bangLocTungKenh(txns, catalog, message);

  post('progress', { phase: 'Đang dựng file Excel...' });
  var options = {
    coSo: message.coSo,
    result: result,
    invoices: invoices,
    kyTu: message.kyTu,
    kyDen: message.kyDen,
    ngayHoaDon: message.ngayHoaDon,
    noiDung: message.noiDung,
    tenKhach: message.tenKhach,
    rate: message.rate,
    theoNgay: message.theoNgay,
    loc: loc
  };
  var book = VatRecReport.buildWorkbook(options);
  var buffer = XLSX.write(book, { bookType: 'xlsx', type: 'array', cellDates: true });

  post('done', {
    file: buffer,
    tongTheoLuong: result.streams.map(function (stream) {
      return { luong: stream, soTien: VatRec.totalOf(result, stream) };
    }),
    tong: VatRec.totalOf(result),
    soGiaoDich: txns.length,
    soDiem: invoices.length,
    theoFile: result.nguonList.map(function (nguon) {
      var tk = result.nguonStats[nguon];
      return {
        nguon: nguon, luong: tk.luong, soGiaoDich: tk.soGiaoDich, soDiem: tk.soDiem,
        soTien: tk.soTien, chuaMapSoTien: tk.chuaMapSoTien, vangLai: tk.vangLai,
        ngoaiKy: tk.ngoaiKy, trungLap: tk.trungLap, loaiKhacPhapNhan: tk.loaiKhacPhapNhan,
        khongCoNgay: tk.khongCoNgay
      };
    }),
    theoNgay: !!message.theoNgay,
    loc: loc,
    theoNgayBang: bangTheoNgay(result, message),
    chuaVat: invoices.reduce(function (a, i) { return a + i.chuaVat; }, 0),
    vat: invoices.reduce(function (a, i) { return a + i.vat; }, 0),
    coVat: invoices.reduce(function (a, i) { return a + i.coVat; }, 0),
    chuaMap: demXuat(result.chuaMap, catalog),
    chuaGanDiem: chuaGanDiem.slice(0, 500),
    soChuaGanDiem: chuaGanDiem.length,
    vangLai: result.vangLai,
    vangLaiSoGiaoDich: result.vangLaiSoGiaoDich,
    ngoaiKy: result.ngoaiKy,
    khongCoNgay: result.khongCoNgay,
    loaiKhacPhapNhan: result.loaiKhacPhapNhan,
    trungLap: result.trungLap,
    trungLapSoGiaoDich: result.trungLapSoGiaoDich,
    diem: invoices.map(function (invoice) {
      return {
        ngay: invoice.ngay,
        tenDiem: invoice.diem.tenDiem,
        maMisa: invoice.diem.maMisa,
        khuVuc: invoice.diem.khuVuc,
        coVat: invoice.coVat,
        chuaVat: invoice.chuaVat,
        vat: invoice.vat
      };
    })
  }, [buffer]);
}

/** Bảng doanh thu từng ngày để hiện ngay trên trang, không phải mở file mới thấy. */
/**
 * Một bảng lọc cho mỗi cổng có phát sinh, theo thứ tự gặp trong dữ liệu.
 * Trả về mảng { channel, ten, rows, ngay, coPhi } để giao diện dựng từng tab.
 */
function bangLocTungKenh(txns, catalog, message) {
  var kenh = [];
  txns.forEach(function (txn) {
    if (txn.channel && kenh.indexOf(txn.channel) < 0) kenh.push(txn.channel);
  });
  return kenh.map(function (channel) {
    var rows = VatRec.locView(txns, channel, catalog);
    return {
      channel: channel,
      ten: VatRec.tenKenh(channel),
      rows: rows,
      ngay: rows.length ? VatRec.locDates(rows, message.kyTu, message.kyDen) : [],
      coPhi: rows.some(function (row) { return row.tongPhi; })
    };
  }).filter(function (item) { return item.rows.length; });
}

/*
 * Gắn đề xuất điểm xuất hoá đơn cho từng mã chưa có trong danh mục.
 *
 * Tính một lần cho cả lô: bảng cân theo độ hiếm và danh sách từ quá phổ biến
 * đều phụ thuộc vào cả lô chứ không phải từng mã, nên tính sẵn rồi dùng lại.
 */
function demXuat(chuaMap, catalog) {
  if (!chuaMap.length) return chuaMap;
  var can = VatRec.trongSo(catalog);
  var boQua = VatRec.tuPhoBien(chuaMap.map(function (item) { return item.code; }));
  return chuaMap.map(function (item) {
    var goi = VatRec.goiYDiem(item.code, item.channel, catalog, boQua, 3, can);
    return {
      channel: item.channel, code: item.code,
      soGiaoDich: item.soGiaoDich, soTien: item.soTien,
      goiY: goi.map(function (g) {
        return {
          diem: g.diem, lyDo: g.lyDo,
          tenDiem: g.point.tenDiem, maMisa: g.point.maMisa, khuVuc: g.point.khuVuc,
          dichVu: g.point.dichVu, hinhThucHopTac: g.point.hinhThucHopTac,
          phapNhan: g.point.phapNhan
        };
      })
    };
  });
}

function bangTheoNgay(result, message) {
  var byDate = VatRec.totalsByDate(result);
  return VatRec.periodDates(message.kyTu, message.kyDen).map(function (date) {
    var entry = byDate[date] || { tong: 0 };
    return { ngay: date, tong: entry.tong };
  });
}

function findSheet(item) {
  var file = state.files[item.file];
  if (!file) return null;
  for (var i = 0; i < file.sheets.length; i += 1) {
    if (file.sheets[i].name === item.sheet) return file.sheets[i];
  }
  return null;
}
