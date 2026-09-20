/**
 * MÀN SỔ KHO: Ô TRỐNG PHẢI KHÁC Ô ĐÃ ĐẾM, VÀ ĐỌC ĐÚNG CÔNG THỨC COMBO.
 *
 * Phần máy chủ do `kiem-kho.php` khoá. Bài này chạy THẬT hai hàm của màn hình — chỗ dò chuỗi
 * trong mã không bắt được:
 *
 *   1. 🔴 `oLech`: chưa khai (null) phải hiện "—", không phải "0" xanh lét. Một ô chưa ai đếm
 *      mà trông như đã soát xong và khớp thì cả sổ đọc ra điều ngược lại với sự thật.
 *   2. 🔴 `docThanhPhan`: gõ "Nước suối x2, Kẹo cầu vồng x1" phải ra đúng công thức. Đọc sai
 *      là trừ nhầm kho HÀNG LOẠT mà không dòng nào sai, và không ai lần ra vì sao tồn lệch.
 *
 * Chạy: node tools/test/kiem-kho-man.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const TEP = path.join(__dirname, '..', '..', 'wordpress', 'khh-doanh-thu', 'assets', 'doanh-thu.js');
const src = fs.readFileSync(TEP, 'utf8');

let dat = 0;
const hong = [];
function t(ten, dk) { if (dk) { dat++; } else { hong.push(ten); } }

function boc(ten) {
  const i = src.indexOf('  function ' + ten + '(');
  if (i < 0) { console.error('KHÔNG tìm thấy ' + ten); process.exit(1); }
  const j = src.indexOf('\n  }\n', i);
  return src.slice(i, j + 4);
}

const F = new Function('nguyen',
  boc('oLech') + boc('docThanhPhan') + boc('soKho') +
  '\nreturn { oLech: oLech, docThanhPhan: docThanhPhan, soKho: soKho };'
)((x) => String(x));

/* ── 1. ô lệch ────────────────────────────────────────────────────────────────────── */
t('🔴 chưa khai thì hiện "—", KHÔNG phải 0', /—/.test(F.oLech(null)) && !/>0</.test(F.oLech(null)));
t('chưa khai thì cũng không tô màu tốt/xấu', !/--tot|--xau/.test(F.oLech(null)));
t('undefined cũng coi là chưa khai', /—/.test(F.oLech(undefined)));
t('🔴 khai và khớp thì hiện 0 màu TỐT', />0</.test(F.oLech(0)) && /--tot/.test(F.oLech(0)));
t('lệch âm thì màu xấu và có dấu trừ', /--xau/.test(F.oLech(-5)) && /-5/.test(F.oLech(-5)));
t('lệch dương thì có dấu cộng', /\+5/.test(F.oLech(5)) && /--xau/.test(F.oLech(5)));

/* ── 2. soKho: 0 là số thật, null là trống ────────────────────────────────────────── */
t('🔴 soKho(0) ra "0", không ra rỗng', '0' === F.soKho(0));
t('soKho(null) ra rỗng', '' === F.soKho(null));
t('soKho(undefined) ra rỗng', '' === F.soKho(undefined));

/* ── 3. đọc công thức combo ───────────────────────────────────────────────────────── */
let c = F.docThanhPhan('Nước suối x2, Kẹo cầu vồng x1');
t('🔴 đọc đúng hai thành phần', 2 === Object.keys(c).length);
t('đúng số lượng', 2 === c['Nước suối'] && 1 === c['Kẹo cầu vồng']);
t('tên món giữ nguyên dấu tiếng Việt', undefined !== c['Kẹo cầu vồng']);

c = F.docThanhPhan('Nước suối');
t('🔴 không ghi "xN" thì hiểu là 1 — người ta hay gõ mỗi tên món', 1 === c['Nước suối']);

c = F.docThanhPhan('Nước suối X3');
t('chữ X hoa cũng được', 3 === c['Nước suối']);
c = F.docThanhPhan('Nước suối *3');
t('dấu * cũng được', 3 === c['Nước suối']);
c = F.docThanhPhan('  Nước suối  x2  ,  , Kẹo x1 ');
t('bỏ khoảng trắng thừa và mẩu rỗng', 2 === Object.keys(c).length && 2 === c['Nước suối']);
t('chuỗi rỗng ra bảng rỗng (chính là lệnh xoá combo)', 0 === Object.keys(F.docThanhPhan('')).length);

/* 🔴 Tên món CÓ SẴN chữ x — "Combo x2 người" — không được cắt nhầm thành "Combo" x2.
   Chỉ "xN" ở CUỐI mới là số lượng. */
c = F.docThanhPhan('Bánh x lát');
t('🔴 chữ x giữa tên không bị hiểu là số lượng', 1 === c['Bánh x lát']);

/* 🔴 Gõ dở "Nước suối x" (quên số) KHÔNG được ra NaN. NaN vào công thức combo là mọi phép trừ
   kho của mặt hàng ấy thành NaN, cột tồn tính trống trơn, và không có gì nói vì sao. */
c = F.docThanhPhan('Nước suối x');
Object.keys(c).forEach(function (k) {
  t('🔴 quên số sau "x" không ra NaN (' + k + ')', !Number.isNaN(c[k]) && c[k] > 0);
});
t('và vẫn giữ lại món ấy chứ không nuốt mất', 1 === Object.keys(c).length);

/* ── 4. màn hình phải dùng đúng mấy chốt của máy chủ ──────────────────────────────── */
const boCC = src.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/^\s*\/\/.*$/gm, ' ');
t('có tab Kho hàng hoá', /data-tab="kho"/.test(boCC));
t('màn kho gọi đúng đường REST', /api\('kho\?ngay=/.test(boCC));
t('🔴 ô ngày của màn kho đi qua noiONgay, không nối thẳng vào change',
  /noiONgay\(k\.querySelector\('#khoNgay'\)/.test(boCC));
t('đổi thành phần combo thì NẠP LẠI cả sổ', /api\('kho-combo'[\s\S]{0,120}then\(taiKho\)/.test(boCC));
t('bày cả hai cột máy: bán lẻ và theo combo',
  /Máy bán lẻ/.test(boCC) && /Theo combo/.test(boCC));
t('bày cả hai cột lệch', /Lệch khai/.test(boCC) && /Lệch kho/.test(boCC));
t('🔴 nhắc combo chưa khai thành phần', /combo_nghi/.test(boCC));
t('mặt hàng chưa từng đếm tay thì gắn nhãn "chưa có mốc"', /chưa có mốc/.test(boCC));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: ô trống khác ô đã đếm, và công thức combo đọc đúng.');
