/**
 * SỐ "THỰC NỘP" PHẢI HIỆN THẲNG RA BẢNG, KHÔNG GIẤU TRONG TOOLTIP.
 *
 * Anh Thắng 26/09/2026, sau khi thấy cột Đếm két chỉ có một dấu nhỏ "·nộp" cần rê chuột mới đọc
 * được số: *"chứ kế toán sao biết được"*. Kế toán soát cả trăm dòng một lượt, không rê chuột từng
 * ô — số phải nằm ngay trên mặt bảng. Phần tính đúng số (đếm két=0 mà nộp quỹ>0 thì lấy nộp quỹ)
 * đã có bài chạy thật ở `tools/test/kiem-dem-ket-tu-nop.php`; bài này chỉ khoá phần HIỂN THỊ.
 *
 * Chạy: node tools/test/kiem-dem-ket-tu-nop-man.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const TEP = path.join(__dirname, '..', '..', 'wordpress', 'khh-doanh-thu', 'assets', 'doanh-thu.js');
const src = fs.readFileSync(TEP, 'utf8');
const boCC = src.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/^\s*\/\/.*$/gm, ' ');

let dat = 0;
const hong = [];
function t(ten, dk) { if (dk) { dat++; } else { hong.push(ten); } }

t('🔴 khi dem_tu_nop bật, số "thực nộp" hiện THẲNG ra bảng (không phải chỉ trong thuộc tính title)',
  /x\.dem_tu_nop \? '<br><span class="nho">thực nộp ' \+ tien\(x\.nop\)/.test(boCC));
t('🔴 KHÔNG còn kiểu giấu số vào tooltip (title="...đang lấy theo Tiền thực nộp...")',
  !/title="Đếm két để trống[^"]*đang lấy theo Tiền thực nộp/.test(boCC));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: số thực nộp hiện thẳng ra bảng, không giấu trong tooltip.');
