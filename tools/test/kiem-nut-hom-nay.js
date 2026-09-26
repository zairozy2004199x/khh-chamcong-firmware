/**
 * NÚT "HÔM NAY" / "HÔM QUA" Ở TAB NHẬP BÁO CÁO NGÀY.
 *
 * Anh Thắng 26/09/2026: "Bổ sung chỗ này: ngày hôm nay chứ nhân viên cứ chọn lộn ngày" — ô lịch
 * gõ tay (spinner tháng/ngày/năm riêng từng ô) dễ bấm nhầm. Hai nút chọn thẳng theo NHÃN thay cho
 * mò lịch, và tô đậm đúng nút khớp ngày đang chọn để nhìn một cái biết ngay đang xem ngày nào.
 *
 * Chạy: node tools/test/kiem-nut-hom-nay.js
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

t('có nút "Hôm qua" cạnh ô ngày', /id="bcNgayQua"/.test(boCC));
t('có nút "Hôm nay" cạnh ô ngày', /id="bcNgayNay"/.test(boCC));
t('🔴 bấm "Hôm qua" đặt đúng ngày hôm qua và tải lại báo cáo',
  /bcNgayQua'\)\.addEventListener\('click', function \(\) \{ q\('#bcNgay'\)\.value = homQua\(\); veNutNgay\(\); napBaoCao\(\); \}\)/.test(boCC));
t('🔴 bấm "Hôm nay" đặt đúng ngày hôm nay và tải lại báo cáo',
  /bcNgayNay'\)\.addEventListener\('click', function \(\) \{ q\('#bcNgay'\)\.value = homNay\(\); veNutNgay\(\); napBaoCao\(\); \}\)/.test(boCC));
t('🔴 đổi ngày bằng ô lịch cũng cập nhật lại nút tô đậm (không chỉ hai nút mới cập nhật)',
  /bcNgay'\)\.addEventListener\('change', function \(\) \{ veNutNgay\(\); napBaoCao\(\); \}\)/.test(boCC));
t('vẽ nút đúng ngay lúc mở màn (không đợi bấm gì mới thấy tô đậm)',
  /veNutNgay\(\);\s*\n\s*sel\.addEventListener/.test(boCC));
t('🔴 hàm tô đậm so đúng giá trị ô ngày với homQua()/homNay(), không đoán bừa',
  /function veNutNgay\(\) \{[\s\S]*?v === homQua\(\)[\s\S]*?v === homNay\(\)[\s\S]*?\n  \}/.test(boCC));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: nút "Hôm nay"/"Hôm qua" chọn ngày theo nhãn, tô đậm đúng ngày đang xem.');
