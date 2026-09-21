/**
 * PHÂN TRANG: 20 DÒNG MỘT TRANG, VÀ ĐỪNG ĐỂ MÀN TRẮNG.
 *
 * Anh Thắng 18/09/2026: *"hiện 10 giao dịch cho 1 trang cho gọn nhé"*, rồi *"trang này cũng
 * vậy"* (bảng đối soát cơ sở), rồi chốt lại *"Làm gọn 20 giao dịch 1 trang"*.
 *
 * Bốn bảng dùng CHUNG một bộ: đối soát cơ sở (217 dòng), ba bảng lệch giao dịch MoMo (383 dòng),
 * và bảng mã nộp tiền lạ (61 mã). Viết bốn bản là sớm muộn một bên đổi số dòng mà ba bên kia
 * không đổi.
 *
 * 🔴 CA NGUY NHẤT: KẸP LẠI TRONG KHOẢNG HỢP LỆ.
 *    Đang xem trang 9, đổi kỳ sang một khoảng chỉ còn 2 trang — không kẹp thì `slice` trả mảng
 *    rỗng và màn trắng trơn. Người dùng không thấy lỗi, chỉ thấy "mất hết dữ liệu", rồi đi báo
 *    hỏng. Không có gì trên màn dẫn về nguyên nhân là số trang cũ còn nhớ.
 *
 * ⚠️ VÀ SỐ TRANG PHẢI NHỚ THEO TỪNG BẢNG: ba bảng lệch nằm cùng một màn, dùng chung một ô nhớ
 *    thì bấm sang trang 3 ở bảng dài là hai bảng ngắn trống trơn.
 *
 * Chạy: node tools/test/kiem-phan-trang.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const TEP = path.join(__dirname, '..', '..', 'wordpress', 'khh-doanh-thu', 'assets', 'doanh-thu.js');
const src = fs.readFileSync(TEP, 'utf8');

function boc(ten) {
  const i = src.indexOf('  function ' + ten + '(');
  if (i < 0) { console.error('KHÔNG tìm thấy ' + ten); process.exit(1); }
  const j = src.indexOf('\n  }\n', i);
  return src.slice(i, j + 4);
}
const mMoi = src.match(/var MOI_TRANG = (\d+);/);
if (!mMoi) { console.error('KHÔNG tìm thấy MOI_TRANG'); process.exit(1); }

const S = {};
const esc = (x) => String(x);
const nguyen = (x) => String(x);
const ctx = new Function('S', 'esc', 'nguyen',
  'var MOI_TRANG = ' + mMoi[1] + ';\n' + boc('catTrang') + boc('thanhTrang') +
  '\nreturn { catTrang: catTrang, thanhTrang: thanhTrang, MOI_TRANG: MOI_TRANG };')(S, esc, nguyen);

let dat = 0; const hong = [];
function t(ten, dk) { if (dk) dat++; else hong.push(ten); }

const ds = [];
for (let i = 1; i <= 45; i++) ds.push('d' + i);

t('mỗi trang 20 dòng như anh Thắng chốt', ctx.MOI_TRANG === 20);

S.trang = {};
let p = ctx.catTrang('a', ds);
t('mặc định là trang 1', p.trang === 1);
t('trang 1 lấy 20 dòng đầu', p.dong.length === 20 && p.dong[0] === 'd1' && p.dong[19] === 'd20');
t('đếm đúng số trang (45 dòng -> 3)', p.so_trang === 3);
t('giữ tổng số dòng để in ra', p.tong === 45);

S.trang = { a: 3 };
p = ctx.catTrang('a', ds);
t('trang cuối lấy phần dư (5 dòng)', p.dong.length === 5 && p.dong[0] === 'd41');

/* 🔴 Ca kẹp: đang ở trang 9 mà danh sách chỉ còn 2 trang. */
S.trang = { a: 9 };
p = ctx.catTrang('a', ds.slice(0, 30));
t('🔴 số trang quá lớn thì KẸP về trang cuối, không trả mảng rỗng', p.dong.length > 0);
t('kẹp đúng về trang cuối', p.trang === 2);
S.trang = { a: -5 };
p = ctx.catTrang('a', ds);
t('số trang âm cũng kẹp về 1', p.trang === 1 && p.dong[0] === 'd1');

/* Số trang nhớ riêng từng bảng. */
S.trang = { a: 2, b: 1 };
t('bảng a ở trang 2', ctx.catTrang('a', ds).dong[0] === 'd21');
t('🔴 bảng b KHÔNG bị kéo theo', ctx.catTrang('b', ds).dong[0] === 'd1');

/* Danh sách ngắn hơn một trang: đừng vẽ thanh trang cho rối. */
S.trang = {};
const it = ctx.catTrang('c', ds.slice(0, 7));
t('ít hơn một trang thì vẫn trả đủ dòng', it.dong.length === 7);
t('và KHÔNG vẽ thanh phân trang', ctx.thanhTrang('c', it) === '');
t('danh sách rỗng cũng không vỡ', ctx.catTrang('d', []).dong.length === 0);

/* Thanh phân trang: nút đầu/cuối phải tắt, kẻo bấm ra trang 0 hoặc trang 4. */
S.trang = {};
let th = ctx.thanhTrang('a', ctx.catTrang('a', ds));
t('trang 1: nút Trước bị tắt', /data-so="0"[^>]*disabled/.test(th));
t('trang 1: nút Sau còn bấm được', /data-so="2"(?![^>]*disabled)/.test(th));
S.trang = { a: 3 };
th = ctx.thanhTrang('a', ctx.catTrang('a', ds));
t('trang cuối: nút Sau bị tắt', /data-so="4"[^>]*disabled/.test(th));
t('thanh trang nói ra tổng số dòng', th.indexOf('45') > -1);

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((x) => console.log('   · 🔴 ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: 20 dòng một trang, kẹp đúng, mỗi bảng nhớ trang riêng.');
