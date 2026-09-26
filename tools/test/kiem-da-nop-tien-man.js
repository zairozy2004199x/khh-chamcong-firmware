/**
 * NÚT "ĐÃ NỘP TIỀN" Ở GIAO DIỆN — KHOÁ TOÀN BỘ FORM, GỠ KHOÁ RIÊNG CHO VĂN PHÒNG.
 *
 * Anh Thắng 26/09/2026: "Bổ sung nút đã nộp tiền (Khi đã nộp thì khóa ô nhập lại)". Phần LOGIC
 * khoá thật (chặn ở REST, không cho lưu đè) đã có bài chạy thật ở `kiem-da-nop-tien.php`; bài
 * này chỉ khoá phần HIỂN THỊ — nút có mặt, khoá đúng như biến `khoa` cũ (đã dùng cho "hết quyền
 * sửa"), và nút Gỡ khoá không bị chính cái khoá nó gỡ khoá đè lên.
 *
 * Chạy: node tools/test/kiem-da-nop-tien-man.js
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

t('có nút "Đã nộp tiền" trong khung nút báo cáo ngày', /id="bcDaNop"/.test(boCC));
t('có nút "Gỡ khoá đã nộp" (ẩn mặc định — chỉ hiện cho văn phòng/quản trị)', /id="bcGoKhoa"[^>]*hidden/.test(boCC));
t('🔴 khoá TOÀN BỘ form khi da_nop, y như lúc hết quyền sửa (var khoa = !r.duoc_ghi || daNop)',
  /var khoa = !r\.duoc_ghi \|\| daNop;/.test(boCC));
t('🔴 nút Gỡ khoá KHÔNG bị khoá đè lên chính nó (không thì không ai bấm gỡ được nữa)',
  /e\.id !== 'bcNgay' && e\.id !== 'bcCH' && e\.id !== 'bcGoKhoa'/.test(boCC));
t('nút "Đã nộp tiền" chỉ hiện khi đã lưu báo cáo, CHƯA khoá, và có quyền ghi',
  /bcDaNop'\)\.hidden = !\(daLuu && !daNop && r\.duoc_ghi\)/.test(boCC));
t('🔴 nút "Gỡ khoá" chỉ hiện cho ai có S.cf.duoc_nap (cùng cửa với permission_callback máy chủ)',
  /bcGoKhoa'\)\.hidden = !\(daNop && S\.cf && S\.cf\.duoc_nap\)/.test(boCC));
t('bấm "Đã nộp tiền" phải xác nhận trước (hành động khó tự lùi lại)',
  /function danNopTien\(\) \{\s*if \(!window\.confirm\(/.test(boCC));
t('bấm "Gỡ khoá" cũng phải xác nhận trước', /function goKhoaDaNop\(\) \{\s*if \(!window\.confirm\(/.test(boCC));
t('nút gọi đúng hai cửa REST mới (da-nop / go-khoa-da-nop)',
  /api\('da-nop', \{ method: 'POST', body: fd \}\)/.test(boCC) && /api\('go-khoa-da-nop', \{ method: 'POST', body: fd \}\)/.test(boCC));
t('trạng thái báo rõ "đã nộp tiền" kèm ai/lúc nào khi khoá',
  /'💰 đã nộp tiền — bởi ' \+ b\.da_nop_boi/.test(boCC));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: nút "Đã nộp tiền" khoá toàn bộ form, gỡ khoá riêng cho văn phòng/quản trị.');
