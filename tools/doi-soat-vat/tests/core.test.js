/*
 * Test cho lõi đối soát bản JavaScript (web/js/core.js).
 *
 * Chạy: `node tests/core.test.js` từ thư mục tools/doi-soat-vat.
 * Không cần cài gì thêm.
 *
 * Các mốc số ở đây phải khớp với tests/test_vatrec.py — hai bản lõi chạy cùng
 * quy tắc, lệch nhau là lỗi.
 */
'use strict';

global.self = global;
require('../web/js/core.js');
var V = global.VatRec;

var passed = 0;
var failed = [];

function check(name, condition, detail) {
  if (condition) passed += 1;
  else failed.push(name + (detail ? ': ' + detail : ''));
}

function eq(name, actual, expected) {
  check(name, actual === expected, 'nhận ' + JSON.stringify(actual) +
    ', mong đợi ' + JSON.stringify(expected));
}

/* ------------------------------------------------------------- chuẩn hoá */

eq('ngày kiểu VN có giờ', V.toDate('02-08-2026 22:49:40'), '2026-08-02');
eq('ngày kiểu VN gạch chéo', V.toDate('02/08/2026 21:35:05'), '2026-08-02');
eq('ngày kiểu ISO', V.toDate('2026-08-02 21:49:56'), '2026-08-02');
eq('Date sẵn', V.toDate(new Date(2026, 7, 2)), '2026-08-02');
eq('ô trống', V.toDate(''), null);
eq('chữ không phải ngày', V.toDate('PaymentForOrder'), null);
eq('ngày vô lý bị loại', V.toDate('45-13-2026 10:00:00'), null);

eq('số nguyên', V.toInt(20000), 20000);
eq('phân cách nghìn kiểu VN', V.toInt('1.234.567'), 1234567);
eq('phân cách nghìn kiểu Anh', V.toInt('1,234,567'), 1234567);
eq('một dấu chấm là phân cách nghìn', V.toInt('20.000 ₫'), 20000);
eq('ô lỗi công thức', V.toInt('#REF!'), 0);
eq('ô trống', V.toInt(null), 0);
eq('số thực làm tròn', V.toInt(17474008.4), 17474008);

eq('gộp khoảng trắng', V.cleanText('AM BD   KVCM'), 'AM BD KVCM');
eq('dấu gạch coi như rỗng', V.cleanText('-'), '');
check('khoá bỏ hoa thường', V.keyText('AM BD  KVCM') === V.keyText('am bd kvcm'));

/* ------------------------------------------------------------------ Excel */

var sheet = [
  ['Báo cáo', null, null, null],
  [null, null, 'tổng', 123],
  ['Mã cửa hàng', 'mã điểm xuất hóa đơn', 'Tổng', 'Ngày'],
  ['ABC', 'Điểm A', 100, 100]
];
eq('dò đúng dòng tiêu đề', V.findHeader(sheet, ['Mã cửa hàng', 'mã điểm xuất hóa đơn']), 2);
eq('dò không phân biệt hoa thường', V.findHeader(sheet, ['MÃ CỬA HÀNG']), 2);
eq('thiếu cột trả về -1', V.findHeader(sheet, ['Cột không tồn tại']), -1);

var ix = V.columnIndex(sheet[2], ['Mã cửa hàng', 'Tổng', 'Không có']);
check('ánh xạ cột', ix['Mã cửa hàng'] === 0 && ix['Tổng'] === 2 && ix['Không có'] === -1,
  JSON.stringify(ix));

/* -------------------------------------------------- nhận diện loại sheet */

function headerSheet(names) {
  return [['ghi chú'], names, ['dữ liệu']];
}

eq('nhận ra sao kê QR',
  V.detectSheet(headerSheet(['Thời gian TT', 'Số tiền đến (VND)', 'Trạng thái', 'Mã cửa hàng'])).kind,
  'qr');
eq('nhận ra sao kê Payoo',
  V.detectSheet(headerSheet(['Cửa hàng', 'Ngày giao dịch', 'Hình thức thanh toán', 'Số tiền thanh toán (₫)'])).kind,
  'payoo');
eq('nhận ra sao kê VNPay',
  V.detectSheet(headerSheet(['Mã điểm thu', 'Thời gian GD', 'Số tiền sau KM', 'Trạng thái'])).kind,
  'vnpay');
// Sao kê VNPay cũng có "Tên điểm xuất hóa đơn" và "Chi nhánh" — không được nhận nhầm là danh mục.
eq('sao kê VNPay đủ cột vẫn không bị nhận nhầm là danh mục',
  V.detectSheet(headerSheet(['Mã điểm thu', 'Thời gian GD', 'Số tiền sau KM', 'Trạng thái',
    'Chi nhánh', 'Tên điểm xuất hóa đơn'])).kind,
  'vnpay');
eq('nhận ra danh mục VNPay',
  V.detectSheet(headerSheet(['Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế', 'Mã điểm thu'])).kind,
  'catalog_vnpay');
eq('nhận ra danh mục Payoo',
  V.detectSheet(headerSheet(['Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế', 'Chi nhánh'])).kind,
  'catalog_payoo');
eq('nhận ra danh mục mã cửa hàng',
  V.detectSheet(headerSheet(['Mã cửa hàng', 'mã điểm xuất hóa đơn'])).kind,
  'catalog_store');
eq('nhận ra sao kê MoMo',
  V.detectSheet(headerSheet(['Thời gian', 'Mã đơn hàng', 'Trạng thái', 'Số tiền', 'Mã cửa hàng'])).kind,
  'momo');
eq('nhận ra danh mục MoMo',
  V.detectSheet(headerSheet(['Mã KH', 'Gian', 'Mã cửa hàng', 'Tên điểm xuất hóa đơn'])).kind,
  'catalog_momo');
eq('nhận ra bảng thông tin điểm',
  V.detectSheet(headerSheet(['Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế', 'Khu vực'])).kind,
  'catalog_diem');
// Sao kê QR cũng có "Mã cửa hàng" và "Mã đơn hàng" — không được nhận nhầm là MoMo.
eq('sao kê QR không bị nhận nhầm là MoMo',
  V.detectSheet(headerSheet(['Thời gian TT', 'Số tiền đến (VND)', 'Trạng thái', 'Mã cửa hàng',
    'Mã đơn hàng'])).kind,
  'qr');
eq('sheet lạ thì bỏ qua', V.detectSheet(headerSheet(['A', 'B', 'C'])), null);

/* -------------------------------------------------------------- đọc sao kê */

var qrRows = [
  ['tổng'],
  ['Thời gian TT', 'Số tiền đến (VND)', 'Trạng thái', 'Mã cửa hàng', 'Mã tham chiếu'],
  ['01-08-2026 10:00:00', 20000, 'Thành công', 'SHOP1', 'FT1'],
  ['01-08-2026 11:00:00', 50000, 'Thất bại', 'SHOP1', 'FT2'],
  ['02-08-2026 12:00:00', 100000, 'Thành công', '-', 'FT3'],
  [null, null, null, null, null]
];
var qrTxns = V.READERS.qr(qrRows, 1, 'QR');
eq('chỉ lấy giao dịch thành công', qrTxns.length, 2);
eq('giữ giao dịch vãng lai', qrTxns[1].code, '');
eq('đọc đúng số tiền', qrTxns[0].soTien, 20000);

var payooRows = [
  ['tổng'],
  ['Cửa hàng', 'Ngày giao dịch', 'Hình thức thanh toán', 'Số tiền thanh toán (₫)', 'Mã giao dịch Payoo'],
  ['GH1', '2026-08-01 10:00:00', 'Quét mã QR', 20000, 'P1'],
  ['GH1', '2026-08-01 11:00:00', 'Thẻ', 30000, 'P2']
];
var payooTxns = V.READERS.payoo(payooRows, 1, 'Payoo');
eq('Payoo tách luồng QR', payooTxns[0].stream, 'Payoo - Quét mã QR');
eq('Payoo tách luồng thẻ', payooTxns[1].stream, 'Payoo - Thẻ');

var zaloRows = [
  ['tổng'],
  ['Mã đơn hàng', 'Ngày đặt hàng', 'Tổng tiền phải trả', 'Trạng thái thanh toán',
    'Trạng thái đơn hàng', 'Tên sản phẩm'],
  ['#1', '2026-08-01 10:00:00', 200000, 'Đã thanh toán', 'Đã giao', 'VINCOM TIMES CITY - Vé nhà ma'],
  ['#1', '2026-08-01 10:00:00', 200000, 'Đã thanh toán', 'Đã giao', 'VINCOM TIMES CITY - Vé khác'],
  ['#2', '2026-08-01 11:00:00', 500000, 'Chờ xử lý', 'Chờ xác nhận', 'ĐÀ NẴNG - Vé'],
  ['#3', '2026-08-01 12:00:00', 700000, 'Đã thanh toán', 'Đã hủy', 'ĐÀ NẴNG - Vé']
];
var zaloTxns = V.READERS.zalo(zaloRows, 1, 'Zalo');
eq('một đơn nhiều dòng sản phẩm chỉ tính một lần', zaloTxns.length, 1);
eq('cắt đúng tên gian hàng', zaloTxns[0].code, 'VINCOM TIMES CITY');
eq('đơn chưa thanh toán và đơn huỷ bị loại', zaloTxns[0].soTien, 200000);

var momoRows = [
  ['tổng'],
  ['Thời gian', 'Mã đơn hàng', 'Trạng thái', 'Số tiền', 'Mã cửa hàng', 'Tên cửa hàng'],
  ['02-08-2026 21:38:56', 'MD1', 'Thành công', 160000, 'ABC123', 'VR FUN'],
  ['02-08-2026 21:33:34', 'MD2', 'Thất bại', 400000, 'ABC123', 'VR FUN'],
  ['03-08-2026 10:00:00', 'MD3', 'Thành công', 50000, '', 'không rõ']
];
var momoTxns = V.READERS.momo(momoRows, 1, 'MoMo');
eq('MoMo chỉ lấy giao dịch thành công', momoTxns.length, 1);
eq('MoMo đọc đúng số tiền', momoTxns[0].soTien, 160000);
eq('MoMo đọc đúng ngày', momoTxns[0].ngay, '2026-08-02');
eq('MoMo giữ mã đơn làm mã tham chiếu', momoTxns[0].ref, 'MD1');
eq('MoMo lấy đúng kênh', momoTxns[0].channel, 'momo');

// Danh mục MoMo: bảng phí để trống tên điểm, dòng tổng để trống mã cửa hàng.
var momoCatRows = [
  ['ghi chú'],
  ['Mã KH', 'Gian', 'Mã cửa hàng', 'Tên điểm xuất hóa đơn'],
  ['KH Cũ', 'FZ A', 'ABC123', 'Điểm A'],
  ['KH cũ', 'FZ B', 'DEF456', 'Điểm B'],
  ['KH Cũ', '', 'ABC123', ''],
  ['', '', 'Tổng theo ngày', '']
];
var momoCat = new V.Catalog();
V.loadMomoCatalog(momoCatRows, 1, momoCat);
eq('danh mục MoMo bỏ bảng phí và dòng tổng', Object.keys(momoCat.byChannel.momo).length, 2);
eq('danh mục MoMo tra đúng điểm', momoCat.lookup('momo', 'ABC123').tenDiem, 'Điểm A');
eq('danh mục MoMo lấy pháp nhân từ Mã KH', momoCat.lookup('momo', 'DEF456').phapNhan, 'KH cũ');

// Bảng thông tin điểm bù thông tin còn trống, không tạo mã tra cứu mới.
V.loadPointInfo([
  ['ghi chú'],
  ['Tên điểm xuất hóa đơn', 'Mã điểm trên misa thuế', 'Khu vực', 'Dịch vụ', 'Pháp nhân'],
  ['Điểm A', 'MISA-A', 'HCM', 'KVC', 'KH cũ']
], 1, momoCat);
eq('thông tin điểm bù được mã misa', momoCat.points[V.keyText('Điểm A')].maMisa, 'MISA-A');
eq('thông tin điểm bù được khu vực', momoCat.points[V.keyText('Điểm A')].khuVuc, 'HCM');
eq('thông tin điểm không thêm mã tra cứu', Object.keys(momoCat.byChannel.momo).length, 2);

/* ------------------------------------------------------------------- VAT */

check('tách VAT theo file mẫu',
  V.splitVat(37750000, 0.08)[0] === 34953704 && V.splitVat(37750000, 0.08)[1] === 2796296);
var lech = 0;
for (var value = 1; value <= 200000; value += 7) {
  var split = V.splitVat(value, 0.08);
  if (split[0] + split[1] !== value) lech += 1;
}
eq('cộng lại đúng bằng tổng', lech, 0);
check('số 0', V.splitVat(0, 0.08)[0] === 0 && V.splitVat(0, 0.08)[1] === 0);

/* ------------------------------------------------------------- tổng hợp */

function catalog() {
  var c = new V.Catalog();
  c.add('qr', 'SHOP1', V.makePoint({ tenDiem: 'Điểm A', khuVuc: 'Hà Nội', phapNhan: 'KH cũ' }));
  c.add('qr', 'SHOP2', V.makePoint({ tenDiem: 'Điểm B', khuVuc: 'HCM', phapNhan: 'KH mới' }));
  return c;
}

function txn(code, ngay, soTien, ref, stream, channel, nguon) {
  return {
    channel: channel || 'qr', stream: stream || 'QR', code: code,
    ngay: ngay, soTien: soTien, ref: ref || '', nguon: nguon || 'a.xlsx'
  };
}

var KY = { kyTu: '2026-08-01', kyDen: '2026-08-31' };

var basic = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'r1'),
  txn('SHOP1', '2026-08-02', 200, 'r2'),
  txn('SHOP2', '2026-08-01', 50, 'r3')
], catalog(), KY);
eq('tổng đúng', V.totalOf(basic), 350);
eq('hai điểm', Object.keys(basic.points).length, 2);
eq('cộng theo ngày', V.rowOf(basic, V.keyText('Điểm A'), 'QR')['2026-08-02'], 200);

var ngoaiKy = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'r1'),
  txn('SHOP1', '2026-07-31', 999, 'r9')
], catalog(), KY);
eq('loại giao dịch ngoài kỳ', V.totalOf(ngoaiKy), 100);
eq('báo lại số tiền ngoài kỳ', ngoaiKy.ngoaiKy, 999);

var vangLai = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'r1'),
  txn('', '2026-08-01', 300, 'r2'),
  txn('-', '2026-08-02', 200, 'r3')
], catalog(), KY);
eq('vãng lai không vào doanh thu điểm', V.totalOf(vangLai), 100);
eq('cộng đúng tiền vãng lai', vangLai.vangLai, 500);
eq('đếm đúng giao dịch vãng lai', vangLai.vangLaiSoGiaoDich, 2);

var chuaMap = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'r1'),
  txn('LA1', '2026-08-01', 700, 'r2'),
  txn('LA1', '2026-08-02', 300, 'r3')
], catalog(), KY);
eq('mã lạ không lọt vào doanh thu', V.totalOf(chuaMap), 100);
eq('gom mã lạ', chuaMap.chuaMap.length, 1);
eq('cộng đúng tiền mã lạ', chuaMap.chuaMap[0].soTien, 1000);
eq('đếm đúng giao dịch mã lạ', chuaMap.chuaMap[0].soGiaoDich, 2);
eq('giữ nguyên mã lạ để tra ngược', chuaMap.chuaMap[0].code, 'LA1');

// Cùng một mã giao dịch đọc từ hai sheet chỉ được tính một lần.
var trung = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'FT001', 'Sheet kỳ này'),
  txn('SHOP1', '2026-08-01', 100, 'FT001', 'Sheet kỳ cũ'),
  txn('SHOP1', '2026-08-02', 50, 'FT002')
], catalog(), KY);
eq('không cộng hai lần', V.totalOf(trung), 150);
eq('báo lại số tiền trùng', trung.trungLap, 100);
eq('đếm đúng giao dịch trùng', trung.trungLapSoGiaoDich, 1);
eq('giữ ví dụ để tra ngược', trung.viDuTrungLap[0].ref, 'FT001');

// Hai cổng khác nhau tình cờ trùng mã thì vẫn là hai giao dịch thật.
var khacKenh = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'X1', 'QR', 'qr'),
  txn('SHOP1', '2026-08-01', 70, 'X1', 'Payoo', 'payoo')
], catalog(), KY);
eq('không nhầm giữa hai cổng', khacKenh.trungLap, 0);

var locPhapNhan = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'r1'),
  txn('SHOP2', '2026-08-01', 900, 'r2')
], catalog(), { kyTu: KY.kyTu, kyDen: KY.kyDen, phapNhan: 'KH cũ' });
eq('chỉ lấy pháp nhân đã chọn', V.totalOf(locPhapNhan), 100);

// Danh mục của cổng ghi pháp nhân tắt; lọc phải theo bản đã bù thông tin.
var catBu = new V.Catalog();
catBu.add('momo', 'SHOP9', V.makePoint({ tenDiem: 'Điểm C', phapNhan: 'KH Mới TK CTy' }));
catBu.addPoint(V.makePoint({ tenDiem: 'Điểm C', maMisa: 'MISA-C', khuVuc: 'HCM',
  dichVu: 'KVC', phapNhan: 'KH mới' }));
var tnMomo = txn('SHOP9', '2026-08-01', 100, 'm1', 'MoMo', 'momo');
eq('lọc khớp theo pháp nhân đã bù',
  V.totalOf(V.aggregate([tnMomo], catBu, { kyTu: KY.kyTu, kyDen: KY.kyDen, phapNhan: 'KH mới' })), 100);
var boPhapNhan = V.aggregate([tnMomo], catBu, { kyTu: KY.kyTu, kyDen: KY.kyDen, phapNhan: 'KH cũ' });
eq('pháp nhân khác thì loại', V.totalOf(boPhapNhan), 0);
eq('báo lại tiền bị loại vì pháp nhân', boPhapNhan.loaiKhacPhapNhan, 100);

var khongNgay = V.aggregate([txn('SHOP1', null, 100, 'r1')], catalog(), KY);
eq('đếm giao dịch không đọc được ngày', khongNgay.khongCoNgay, 1);
eq('không tính vào doanh thu', V.totalOf(khongNgay), 0);

/* ---------------------------------------------------------- tách theo file */

var theoFile = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'a1', null, null, 'a.xlsx'),
  txn('SHOP1', '2026-08-02', 200, 'a2', null, null, 'a.xlsx'),
  txn('SHOP2', '2026-08-01', 50, 'b1', null, null, 'b.xlsx'),
  txn('LA9', '2026-08-01', 700, 'b2', null, null, 'b.xlsx'),
  txn('', '2026-08-01', 300, 'b3', null, null, 'b.xlsx')
], catalog(), KY);

check('liệt kê đúng các file',
  theoFile.nguonList.length === 2 && theoFile.nguonList[0] === 'a.xlsx',
  JSON.stringify(theoFile.nguonList));
eq('file a cộng riêng đúng', theoFile.nguonStats['a.xlsx'].soTien, 300);
eq('file a đếm đúng số điểm', theoFile.nguonStats['a.xlsx'].soDiem, 1);
eq('file b cộng riêng đúng', theoFile.nguonStats['b.xlsx'].soTien, 50);
eq('mã lạ tính vào đúng file', theoFile.nguonStats['b.xlsx'].chuaMapSoTien, 700);
eq('vãng lai tính vào đúng file', theoFile.nguonStats['b.xlsx'].vangLai, 300);
eq('file a không dính cảnh báo của file b', theoFile.nguonStats['a.xlsx'].chuaMapSoTien, 0);
eq('tổng các file bằng tổng chung',
  theoFile.nguonStats['a.xlsx'].soTien + theoFile.nguonStats['b.xlsx'].soTien,
  V.totalOf(theoFile));

var diemA = V.pointsOfNguon(theoFile, 'a.xlsx');
eq('điểm của riêng file a', diemA[V.keyText('Điểm A')], 300);
eq('file b không thấy điểm của file a',
  V.pointsOfNguon(theoFile, 'b.xlsx')[V.keyText('Điểm A')], undefined);
var dongA = V.rowOfNguon(theoFile, 'a.xlsx', V.keyText('Điểm A'));
eq('dòng theo ngày của riêng file a', dongA['2026-08-02'], 200);

// Ngoài kỳ và trùng mã cũng phải quy về đúng file.
var fileCanhBao = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'x1', null, null, 'a.xlsx'),
  txn('SHOP1', '2026-07-01', 900, 'x9', null, null, 'b.xlsx'),
  txn('SHOP1', '2026-08-01', 100, 'x1', null, null, 'b.xlsx')
], catalog(), KY);
eq('ngoài kỳ tính vào đúng file', fileCanhBao.nguonStats['b.xlsx'].ngoaiKy, 900);
eq('trùng mã tính vào đúng file', fileCanhBao.nguonStats['b.xlsx'].trungLap, 100);
eq('file a giữ nguyên', fileCanhBao.nguonStats['a.xlsx'].soTien, 100);
eq('không cộng hai lần', V.totalOf(fileCanhBao), 100);

/* --------------------------------------------------------------- hoá đơn */

var forInvoice = V.aggregate([
  txn('SHOP1', '2026-08-01', 37750000, 'r1'),
  txn('SHOP2', '2026-08-01', 0, 'r2')
], catalog(), KY);
var invoices = V.buildInvoices(forInvoice, 0.08);
eq('bỏ điểm không doanh thu', invoices.length, 1);
eq('có VAT đúng', invoices[0].coVat, 37750000);
eq('chưa VAT đúng', invoices[0].chuaVat, 34953704);
eq('VAT đúng', invoices[0].vat, 2796296);
eq('giữ chi tiết theo luồng', invoices[0].chiTietLuong.QR, 37750000);

eq('liệt kê đủ ngày trong kỳ', V.periodDates('2026-08-01', '2026-08-31').length, 31);

/* ------------------------------------------------------- hoá đơn theo ngày */

var nhieuNgay = V.aggregate([
  txn('SHOP1', '2026-08-01', 100, 'r1'),
  txn('SHOP1', '2026-08-02', 200, 'r2'),
  txn('SHOP2', '2026-08-02', 50, 'r3')
], catalog(), KY);

var theoNgay = V.buildInvoices(nhieuNgay, 0.08, true);
eq('mỗi điểm mỗi ngày một dòng', theoNgay.length, 3);
eq('ngày hoá đơn là ngày phát sinh', theoNgay[0].ngay, '2026-08-01');
check('xếp theo ngày tăng dần',
  theoNgay[0].ngay === '2026-08-01' && theoNgay[1].ngay === '2026-08-02' &&
  theoNgay[2].ngay === '2026-08-02');
eq('tổng không đổi so với gộp kỳ',
  theoNgay.reduce(function (a, i) { return a + i.coVat; }, 0), 350);

var gopKy = V.buildInvoices(nhieuNgay, 0.08, false);
eq('gộp kỳ vẫn ra 2 dòng', gopKy.length, 2);
eq('gộp kỳ cùng tổng', gopKy.reduce(function (a, i) { return a + i.coVat; }, 0), 350);
eq('gộp kỳ không gắn ngày', gopKy[0].ngay, null);

var theoNgayBang = V.totalsByDate(nhieuNgay);
eq('cộng đúng theo ngày', theoNgayBang['2026-08-02'].tong, 250);
eq('ngày không phát sinh thì không có khoá', theoNgayBang['2026-08-03'], undefined);
eq('tách được theo luồng trong ngày', theoNgayBang['2026-08-01'].theoLuong.QR, 100);

/* --------------------------------------- đề xuất điểm cho mã mới */

function catalogGoiY() {
  var cat = new V.Catalog();
  [['FARM AEON HUẾ', 'FARM AEON HUE', 'Hà Nội'],
   ['FUNFEST AEON MALL BÌNH TÂN', 'AE BT KVCM', 'HCM'],
   ['Funzone IPH Cầu Giấy', 'FUNZONE IPH KVCN', 'Hà Nội'],
   ['Funzone Adventure SC Vivo', 'SC VIVO KVCM', 'HCM'],
   ['Eco Kids farm Hải Phòng', 'EKF HP', 'Hà Nội']
  ].forEach(function (p) {
    cat.addPoint(V.makePoint({ tenDiem: p[0], maMisa: p[1], khuVuc: p[2] }));
  });
  return cat;
}

eq('bỏ dấu và emoji', V.tachTu('🌸 THE LOOP (IPH)').join(','), 'loop,iph');
eq('cắt giữa chữ và số', V.tachTu('FZ2_IPH').join(','), 'fz,iph');
eq('bỏ chữ đ có dấu', V.tachTu('ĐÀ NẴNG').join(','), 'da,nang');
eq('bỏ từ mô tả khuyến mãi', V.tachTu('COMBO MUA 4 TẶNG 2').join(','), '');
check('bỏ loại hình mặt bằng', V.tachTu('AEON MALL HUẾ').indexOf('mall') < 0);

var catGoiY = catalogGoiY();
var canGoiY = V.trongSo(catGoiY);

// "mall" bị bỏ nên "Huế" mới là chỗ phân biệt, không để khớp nhầm sang Bình Tân.
var gAeonHue = V.goiYDiem('AEON MALL HUẾ', 'zalo', catGoiY, null, 3, canGoiY);
eq('chọn đúng điểm cùng địa danh', gAeonHue.length && gAeonHue[0].point.tenDiem, 'FARM AEON HUẾ');

var gVivo = V.goiYDiem('🌸 SC VivoCity', 'zalo', catGoiY, null, 3, canGoiY);
eq('nhận ra tên gian hàng có emoji', gVivo.length && gVivo[0].point.tenDiem,
  'Funzone Adventure SC Vivo');

// Đúng đường đi thật: tiền tố chung của cả lô bị loại trước khi so.
var maPayoo = ['DVGIAITRIKH_FZ_IPH', 'DVGIAITRIKH_FARM_VC', 'DVGIAITRIKH_AE_HUE'];
var gPayoo = V.goiYDiem('DVGIAITRIKH_FZ_IPH', 'payoo', catGoiY, V.tuPhoBien(maPayoo), 3, canGoiY);
eq('nhận ra mã kỹ thuật của Payoo', gPayoo.length && gPayoo[0].point.tenDiem,
  'Funzone IPH Cầu Giấy');

eq('không đề xuất bừa cho chữ không phải địa danh',
  V.goiYDiem('COMBO MUA 4 TẶNG 2', 'zalo', catGoiY, null, 3, canGoiY).length, 0);

// Mã đã khai ở cổng khác là bằng chứng thật, không phải phỏng đoán.
catGoiY.add('vnpay', 'SHOP9', V.makePoint({ tenDiem: 'FARM AEON HUẾ' }));
var gChac = V.goiYDiem('SHOP9', 'zalo', catGoiY);
eq('mã đã có ở kênh khác thì chắc chắn', gChac.length && gChac[0].diem, 1);
check('nói rõ vì sao chắc', gChac.length && gChac[0].lyDo.indexOf('đã khai ở kênh') >= 0);

var dayDu = V.goiYDiem('Eco Kids farm Hải Phòng', 'zalo', catGoiY, null, 3, canGoiY);
var motPhan = V.goiYDiem('AEON MALL HẢI PHÒNG', 'zalo', catGoiY, null, 3, canGoiY);
eq('khớp đủ chữ thì điểm tuyệt đối', dayDu.length && dayDu[0].diem, 1);
check('khớp một phần thì thấp hơn hẳn', motPhan.length && motPhan[0].diem < dayDu[0].diem,
  motPhan.length ? String(motPhan[0].diem) : '(rỗng)');

var boPhoBien = V.tuPhoBien(maPayoo);
check('bỏ tiền tố có ở mọi mã', !!boPhoBien.dvgiaitrikh);
check('giữ lại phần phân biệt', !boPhoBien.iph && !boPhoBien.farm);
eq('ít mã quá thì không kết luận', Object.keys(V.tuPhoBien(['A_X', 'A_Y'])).length, 0);

// Payoo ghi hai mã cho một cửa hàng — khai theo mã nào cũng tra ra được.
var catMaPhu = new V.Catalog();
catMaPhu.add('payoo', '00003182', V.makePoint({ tenDiem: 'Funzone IPH Cầu Giấy' }));
var maPhu = V.aggregate([{
  channel: 'payoo', stream: 'Payoo - Thẻ', nguon: 'p.xlsx', code: 'DVGIAITRIKH_FZ_IPH',
  codePhu: '00003182', ngay: '2026-08-02', soTien: 5000, phi: 0, nhom: 'Thẻ', ref: 'r1'
}], catMaPhu, KY);
eq('tra được qua mã cửa hàng', V.totalOf(maPhu), 5000);
eq('không rơi vào mã chưa map', maPhu.chuaMap.length, 0);

/* ------------------------------------------------ bảng lọc từng cổng */

function payooTxn(code, ngay, tien, phi, nhom, ref) {
  return { channel: 'payoo', stream: 'Payoo - ' + nhom, nguon: 'p.xlsx', code: code,
    ngay: ngay, soTien: tien, phi: phi, nhom: nhom, ref: ref };
}

var payooRows = V.locView([
  payooTxn('SHOP_B', '2026-08-02', 1000, 10, 'Thẻ', 'r1'),
  payooTxn('SHOP_B', '2026-08-02', 2000, 20, 'Quét mã QR', 'r2'),
  payooTxn('SHOP_B', '2026-08-03', 500, 5, 'Quét mã QR', 'r3'),
  payooTxn('SHOP_A', '2026-08-02', 700, 7, 'Quét mã QR', 'r4')
], 'payoo', null);

eq('mỗi cửa hàng × hình thức một dòng', payooRows.length, 3);
eq('giữ thứ tự cửa hàng như trong file gốc', payooRows[0].code, 'SHOP_B');
eq('QR xếp trước Thẻ', payooRows[0].nhom, 'Quét mã QR');
eq('cộng đúng theo ngày', payooRows[0].tien['2026-08-02'], 2000);
eq('cộng đúng cả kỳ', payooRows[0].tongTien, 2500);
eq('cộng đúng phí', payooRows[0].tongPhi, 25);
eq('dòng thẻ tách riêng', payooRows[1].nhom, 'Thẻ');
eq('cửa hàng gặp sau xếp sau', payooRows[2].code, 'SHOP_A');
eq('chưa có danh mục thì để trống tên điểm', payooRows[0].tenDiem, '');

var payooCat = new V.Catalog();
payooCat.add('payoo', 'SHOP_B', V.makePoint({ tenDiem: 'Điểm B', maMisa: 'MISA B' }));
var coDanhMuc = V.locView([payooTxn('SHOP_B', '2026-08-02', 1000, 10, 'Thẻ', 'r1')], 'payoo', payooCat);
eq('có danh mục thì điền tên điểm', coDanhMuc[0].tenDiem, 'Điểm B');
eq('có danh mục thì điền mã misa', coDanhMuc[0].maMisa, 'MISA B');

var payooNgay = V.locDates(payooRows, '2026-08-01', '2026-08-03');
eq('đủ ngày trong kỳ', payooNgay.length, 3);
eq('ngày xếp tăng dần', payooNgay[0], '2026-08-01');
// Ngày ngoài kỳ không bị cắt mà nối ở cuối, để nhìn ra ngay khi tải nhầm khoảng ngày.
var lacNgay = V.locDates(V.locView([payooTxn('S', '2026-09-09', 1, 0, 'Thẻ', 'x')], 'payoo', null),
  '2026-08-01', '2026-08-02');
eq('ngày ngoài kỳ nối ở cuối', lacNgay[lacNgay.length - 1], '2026-09-09');

/*
 * Một điểm có nhiều mã điểm bán thì các dòng của nó phải nằm liền nhau. Kênh QR
 * hay gặp cảnh này; xếp rời ra là STT nhảy lại và ô STT gộp trong file Excel
 * gộp nhầm sang điểm khác.
 */
var catQr = new V.Catalog();
catQr.add('qr', 'MA1', V.makePoint({ tenDiem: 'Điểm A' }));
catQr.add('qr', 'MA2', V.makePoint({ tenDiem: 'Điểm B' }));
catQr.add('qr', 'MA3', V.makePoint({ tenDiem: 'Điểm A' }));
function qrTxn(code, ref) {
  return { channel: 'qr', stream: 'QR', nguon: 'q.xlsx', code: code,
    ngay: '2026-08-02', soTien: 100, ref: ref };
}
var gomDiem = V.locView([qrTxn('MA1', 'a'), qrTxn('MA2', 'b'), qrTxn('MA3', 'c')], 'qr', catQr);
eq('hai mã cùng điểm nằm liền nhau',
  gomDiem.map(function (r) { return r.tenDiem; }).join(','), 'Điểm A,Điểm A,Điểm B');
eq('giữ thứ tự điểm gặp trước', gomDiem[0].code, 'MA1');

eq('tên cổng hiện thành chữ dễ đọc', V.tenKenh('zalo'), 'Zalo Mini App');
eq('cổng lạ thì giữ nguyên mã', V.tenKenh('abc'), 'abc');

/* ------------------------------------------------- cột của file đầu ra */

/*
 * Cột đầu ra phải chép đúng file VAT mẫu, kể cả chỗ tên cột không khớp nội dung
 * ("Số hợp đồng (nếu có)" chứa hình thức hợp tác, "Mã điểm nội bộ" chứa tên
 * điểm). Khoá cứng ở đây để không ai đổi tên cột cho "dễ hiểu" rồi làm hỏng
 * bước dán vào Misa.
 */
global.XLSX = {};
require('../web/js/report.js');
var R = global.VatRecReport;

eq('DS xuất HĐ MTT đúng 22 cột', R.DS_HEADER.length, 22);
eq('DS xuất HĐ MTT đúng tên cột', R.DS_HEADER.join('|'),
  'STT|Ngày HĐ|Số HĐ|Tên khách hàng|Mã số thuế khách hàng|Địa chỉ khách hàng|' +
  'Email nhận hóa đơn|Nội dung xuất hóa đơn|Số lượng|ĐVT|Thành tiền|Chưa VAT|VAT|' +
  'Có VAT|Khu vực|Dịch vụ|Số hợp đồng (nếu có)|Mã điểm nội bộ|Mã điểm misa|Ghi chú||Địa chỉ');

eq('bản kê đúng 22 cột cố định', R.KE_HEADER.length, 22);
eq('bản kê đúng tên cột', R.KE_HEADER.join('|'),
  'STT|Tháng|Ngày HĐ|lọc trùng|Số HĐ|Tên khách hàng|Mã số thuế khách hàng|' +
  'Địa chỉ khách hàng|Email nhận hóa đơn|Nội dung hóa đơn|Tổng TT HĐ htoan Misa|' +
  'đã xuất hóa đơn|Khu vực|Dịch vụ|Số hợp đồng|Mã đối tượng nội bộ|' +
  'Mã điểm ghi chú HT Misa|Mã NCC HT Misa|ghi chú|Dịch vụ thu hộ|' +
  'Những lưu ý khác (thời hạn hợp đồng, …)|Pháp nhân');

eq('cột tiền của bản kê nằm ở "Tổng TT HĐ htoan Misa"',
  R.KE_HEADER[10], 'Tổng TT HĐ htoan Misa');
eq('cột Chưa VAT / VAT / Có VAT liền nhau',
  R.DS_HEADER.slice(11, 14).join('|'), 'Chưa VAT|VAT|Có VAT');

/* ----------------------------------------------------------------- kết quả */

console.log(passed + ' kiểm tra đạt, ' + failed.length + ' lỗi');
failed.forEach(function (name) { console.log('  ✗ ' + name); });
process.exit(failed.length ? 1 : 0);
