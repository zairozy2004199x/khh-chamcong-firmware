/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * XUẤT .CSV CỦA BÁO CÁO TỔNG: Ở CHẾ ĐỘ QR PHẢI RA SỐ VIETQR THỰC (SỐ ĐỎ)
 *
 * Anh Thắng 22/09/2026: *"chỗ xuất QR lấy theo số thực tức QR số màu đỏ"*, rồi gửi kèm tệp vừa
 * tải: cả bảng chỉ có số đen, số đỏ mất sạch — *"chưa được"*.
 *
 * 🔴 GỐC. Màn hình xếp HAI LỚP chồng nhau trong một ô: đen = số nhân viên đọc trên máy, đỏ = tiền
 *    THẬT về ngân hàng (sao kê). Tệp CSV chỉ có một lớp, mà bản cũ lấy đúng lớp ĐEN. Tức người ta
 *    mở màn hình ra để nhìn số đỏ, bấm Xuất, rồi nhận về đúng thứ mình không định lấy — và trong
 *    tệp không có gì nói nó là lớp nào.
 *
 * ⚠️ LOẠI HỎNG KHÔNG KÊU TIẾNG NÀO. Tệp tải về đủ dòng, đủ cột, số nào cũng là số thật — chỉ là
 *    thật của lớp kia. Không có thông báo lỗi, không có ô trống. Chỉ có người ngồi đối chiếu với
 *    sao kê mới phát hiện, và lúc ấy đã dán vào sổ rồi.
 *
 * ⚠️ BÀI NÀY CHẠY THẬT HÀM DỰNG TỆP rồi đọc lại từng ô của CSV. Dò chuỗi trong mã nguồn không nói
 *    được "ô ngày 11/09 của AEON có đúng bằng số đỏ không".
 *
 * Chạy: node tools/test/kiem-bct-xuat-vietqr.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const NGUON = 'vhcp-ghe/includes/class-vhg-trang.php';
if (!fs.existsSync(NGUON)) { console.error('✗ Không thấy ' + NGUON + ' — chạy từ gốc kho.'); process.exit(2); }
const src = fs.readFileSync(NGUON, 'utf8');

let hong = 0;
function t(ten, ok, them) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + ten + (ok || them === undefined ? '' : ' → ' + JSON.stringify(them)));
  if (!ok) hong++;
}
function than(ten) {
  const i = src.indexOf('function ' + ten + '(');
  if (i < 0) return '';
  let d = 0;
  for (let k = src.indexOf('{', i); k < src.length; k++) {
    if (src[k] === '{') d++;
    else if (src[k] === '}' && --d === 0) return src.slice(i, k + 1);
  }
  return '';
}
const ma = than('bctXuat');
t('bốc được hàm bctXuat', '' !== ma);
if (!ma) { console.log('\n🔴 dừng'); process.exit(1); }

/* ── Bệ đỡ tí hon: bắt lại nội dung tệp thay vì thật sự tải về ───────────────────────────────── */
function chay(data) {
  let noiDung = null;
  function Blob(phan) { noiDung = phan.join(''); }
  const URL = { createObjectURL: () => 'blob:x', revokeObjectURL() {} };
  const the = { style: {}, click() {}, remove() {}, href: '', download: '' };
  const document = { createElement: () => the, body: { appendChild() {} } };
  const f = new Function('BCT_DATA', 'Blob', 'URL', 'document', 'L', 'alert', 'setTimeout',
    /* 2.144.0: bctXuat đi qua bctApDungLoc(BCT_DATA, BCT_LOC) — ô lọc rỗng thì trả nguyên bảng. */
    'var BCT_LOC = "";\n' + than('bctKd') + '\n' + than('bctApDungLoc') + '\n' + ma + '\nreturn bctXuat;')(data, Blob, URL, document, (a) => a, () => {}, () => {});
  f();
  return { csv: String(noiDung || '').replace(/^﻿/, ''), ten: the.download };
}
/* Đọc CSV về mảng ô (dữ liệu bài này không có dấu phẩy trong ô). */
function bang(csv) { return csv.split('\n').map((d) => d.split(',')); }

/* ── Dữ liệu: đúng dàn ảnh anh Thắng gửi (AEON MALL BÌNH DƯƠNG, chế độ QR) ───────────────────── */
const NGAY = ['2026-09-09', '2026-09-10', '2026-09-11'];
function dl(them) {
  return Object.assign({
    tu: '2026-09-09', den: '2026-09-11', muc: 'coso', cot: 'qr', ngay: NGAY,
    soGhe: 32, tong: 8850000, tongCot: [3650000, 0, 5200000],
    vqCo: true, vqTong: 7580000, vqTongCot: [3280000, 2350000, 1950000],
    hang: [
      { coso: 'AEON MALL BÌNH DƯƠNG', maKH: 'KH00108', soGhe: 12, tong: 3900000, so: [1700000, 0, 2200000],
        vqTong: 3500000, vq: [1400000, 960000, 1140000] },
      { coso: 'AEON MALL BÌNH TÂN', maKH: 'KH00129', soGhe: 20, tong: 4950000, so: [1950000, 0, 3000000],
        vqTong: 4080000, vq: [1880000, 1390000, 810000] },
    ],
  }, them || {});
}

console.log('── Chế độ QR + có sao kê: số xuất ra là VietQR THỰC ─────────');
let r = chay(dl());
let b = bang(r.csv);
t('🔴 khối ĐẦU dán nhãn rõ là VietQR THỰC', /VIETQR THỰC/.test(b[0][0]), b[0][0]);
t('tiêu đề cột giữ đúng thứ tự màn hình (Tổng đứng sau Số ghế)',
  b[1][0] === 'Tên cơ sở' && b[1][1] === 'Mã KH' && b[1][2] === 'Số ghế' && b[1][3] === 'Tổng'
  && b[1][4] === '2026-09-09', b[1]);
t('🔴 ô từng ngày là SỐ ĐỎ (VietQR thực), không phải số nhân viên nhập',
  b[2].slice(4).join('|') === '1400000|960000|1140000', b[2]);
t('🔴 cột Tổng của dòng cũng là VietQR thực (3.500.000, không phải 3.900.000)',
  b[2][3] === '3500000', b[2][3]);
t('cơ sở thứ hai cũng vậy', b[3].slice(4).join('|') === '1880000|1390000|810000', b[3]);
t('🔴 dòng TỔNG cuối khối lấy tổng VietQR thực, không lấy tổng số nhập',
  b[4][0] === 'TỔNG' && b[4][3] === '7580000' && b[4].slice(4).join('|') === '3280000|2350000|1950000', b[4]);

console.log('── Lớp nhân viên nhập vẫn còn, thành khối thứ hai ───────────');
/* Bỏ hẳn lớp nhập thì tệp hết đường tra vì sao lệch — hai lớp tồn tại là để đối chiếu. */
const iNhap = b.findIndex((d) => /NHÂN VIÊN NHẬP/.test(d[0] || ''));
t('🔴 có khối thứ hai, dán nhãn "số QR nhân viên nhập"', iNhap > 4, iNhap);
t('và nói rõ nó KHÔNG phải tiền về ngân hàng', /KHÔNG phải tiền về ngân hàng/.test(b[iNhap][0] || ''), b[iNhap] && b[iNhap][0]);
t('khối hai mang đúng số nhân viên nhập',
  iNhap > 0 && b[iNhap + 2].slice(4).join('|') === '1700000|0|2200000', iNhap > 0 ? b[iNhap + 2] : null);
t('khối hai có dòng TỔNG của chính nó', iNhap > 0 && b[iNhap + 4][0] === 'TỔNG' && b[iNhap + 4][3] === '8850000',
  iNhap > 0 ? b[iNhap + 4] : null);
t('có dòng trống ngăn hai khối', iNhap > 0 && '' === (b[iNhap - 1] || []).join(''), iNhap > 0 ? b[iNhap - 1] : null);
t('tên tệp nói rõ đây là bản VietQR thực', /_vietqr-thuc\.csv$/.test(r.ten), r.ten);

console.log('── Các chế độ KHÁC giữ nguyên như cũ (một khối, số đang hiện) ');
r = chay(dl({ cot: 'tong' })); b = bang(r.csv);
t('chế độ Tổng: vẫn một khối, tiêu đề ngay dòng đầu', b[0][0] === 'Tên cơ sở', b[0]);
t('chế độ Tổng: lấy số đang hiện (TỔNG vốn đã gộp VietQR thực từ 2.113.0)',
  b[1].slice(4).join('|') === '1700000|0|2200000', b[1]);
t('chế độ Tổng: tên tệp không gắn đuôi vietqr', !/_vietqr-thuc/.test(r.ten), r.ten);

r = chay(dl({ vqCo: false })); b = bang(r.csv);
t('🔴 chưa đọc được sao kê: KHÔNG dựng khối rỗng, giữ nguyên bảng một khối',
  b[0][0] === 'Tên cơ sở' && b[1].slice(4).join('|') === '1700000|0|2200000', b[0]);

r = chay(dl({ muc: 'ghe', hang: [{ coso: 'A', maKH: 'K1', tenGhe: 'GHẾ 1', maGhe: 'G1', soGhe: 1, tong: 5, so: [5, 0, 0] }], vqCo: false }));
b = bang(r.csv);
t('gộp theo TỪNG GHẾ: có thêm cột Ghế, không có lớp VietQR (sao kê chỉ quy được về cơ sở)',
  b[0][2] === 'Ghế' && b[1][2] === 'GHẾ 1', b[0]);

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
