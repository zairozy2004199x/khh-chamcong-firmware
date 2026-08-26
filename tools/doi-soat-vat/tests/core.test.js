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

function txn(code, ngay, soTien, ref, stream, channel) {
  return {
    channel: channel || 'qr', stream: stream || 'QR', code: code,
    ngay: ngay, soTien: soTien, ref: ref || ''
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

var khongNgay = V.aggregate([txn('SHOP1', null, 100, 'r1')], catalog(), KY);
eq('đếm giao dịch không đọc được ngày', khongNgay.khongCoNgay, 1);
eq('không tính vào doanh thu', V.totalOf(khongNgay), 0);

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

/* ----------------------------------------------------------------- kết quả */

console.log(passed + ' kiểm tra đạt, ' + failed.length + ' lỗi');
failed.forEach(function (name) { console.log('  ✗ ' + name); });
process.exit(failed.length ? 1 : 0);
