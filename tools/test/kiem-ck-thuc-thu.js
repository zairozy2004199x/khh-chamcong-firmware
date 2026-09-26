/**
 * CHUYỂN KHOẢN THỰC THU — MỖI CHỖ ĐỘNG TỚI `tien_mat_dem` PHẢI CÓ BẠN `ck_thuc_thu` ĐI KÈM.
 *
 * Anh Thắng 26/09/2026: *"nhiều khi lệch ngược giữa chuyển khoản và tiền mặt… cần nhân viên nhập
 * số thực"*. Ô "Chuyển khoản thực thu" là bản sao của ô "Tiền mặt đếm trong két" đã có sẵn, nhưng
 * chép một luật ra NHIỀU chỗ (ô nhập, mảng tải lại, mảng gửi lên, tính lệch, cột bảng, cảnh báo,
 * ảnh báo cáo) là mỗi chỗ một dịp bỏ sót — sửa một chỗ mà quên một chỗ khác thì ô mới hiện được ở
 * màn nhập nhưng lặng câm ở màn Đối soát, hoặc ngược lại. Bài này khoá từng chỗ một; phần tính lệch
 * thật (không phải soi chữ) đã có ở `tools/test/kiem-lech-chuyen-khoan.php`.
 *
 * Chạy: node tools/test/kiem-ck-thuc-thu.js
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

t('🔴 có ô nhập "Chuyển khoản thực thu"', /o_nhap\('ck_thuc_thu', 'Chuyển khoản thực thu'/.test(boCC));
t('🔴 mảng TẢI LẠI giá trị đã lưu có ck_thuc_thu (kẻo ô nhập luôn trống lúc mở lại ngày cũ)',
  /\['tien_mat_dem', 'ck_thuc_thu', 'tien_nop'[^\]]*\]\s*\n\s*\.forEach/.test(boCC));
t('🔴 mảng GỬI LÊN máy chủ lúc Lưu báo cáo có ck_thuc_thu (kẻo gõ xong mà không gửi)',
  /\['tien_mat_dem', 'ck_thuc_thu', 'tien_nop'[^\]]*\]\s*\n\s*\.forEach\(function \(k\) \{ fd\.append/.test(boCC));
t('🔴 tinhLech() đọc ck_thuc_thu và so với p.ck (POS)', /soNhap\('ck_thuc_thu'\)/.test(boCC) && /ck - Math\.round\(p\.ck\)/.test(boCC));
t('🔴 dongBC\\(\\) (ảnh báo cáo + tóm tắt Zalo) cũng so ck_thuc_thu với p.ck', /so\(b\.ck_thuc_thu\)/.test(boCC) && /ck - Math\.round\(p\.ck \|\| 0\)/.test(boCC));

t('🔴 bảng Đối soát có cột "Lệch CK" riêng, không gộp vào cột "Lệch"', /<th>Lệch<\/th><th>Lệch CK<\/th>/.test(boCC));
t('🔴 dòng bảng đọc x.lech_ck (không phải lây lại x.lech_tm)', /x\.lech_ck \? \(x\.lech_ck > 0/.test(boCC));
t('🔴 canhBao() xét cả lệch_ck khi quyết định tô đỏ một dòng',
  /Math\.max\(m, Math\.abs\(x\.lech_tm \|\| 0\), Math\.abs\(x\.lech_ck \|\| 0\)/.test(boCC));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: Chuyển khoản thực thu có mặt đủ ở mọi chỗ đã có Tiền mặt đếm két.');
