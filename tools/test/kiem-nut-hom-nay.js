/**
 * NÚT "HÔM NAY" Ở TAB NHẬP BÁO CÁO NGÀY.
 *
 * Anh Thắng 26/09/2026: "Bổ sung chỗ này: ngày hôm nay chứ nhân viên cứ chọn lộn ngày" — ô lịch
 * gõ tay (spinner tháng/ngày/năm riêng từng ô) dễ bấm nhầm. Thêm nút chọn thẳng theo nhãn thay
 * mò lịch. Bản đầu có cả "Hôm qua" lẫn "Hôm nay"; anh Thắng phản hồi ngay sau đó: *"lúc được, lúc
 * không. chỉ hiện nút hôm nay thôi, với nút to lên tí"* — bớt "Hôm qua" (ô ngày vốn đã mặc định
 * hôm qua, đủ dùng), chỉ giữ "Hôm nay" cho ca hay cần (đang xem hôm qua mà có việc phải nhập luôn
 * hôm nay), và làm nút TO hơn bản trước (bản trước lỡ tay thu nhỏ hơn cả cỡ mặc định của .vien).
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

t('🔴 CHỈ còn nút "Hôm nay" — không còn "Hôm qua" (anh Thắng: "chỉ hiện nút hôm nay thôi")',
  /id="bcNgayNay"/.test(boCC) && !/id="bcNgayQua"/.test(boCC) && !/homQua\(\); veNutNgay/.test(boCC));
t('🔴 bấm "Hôm nay" đặt đúng ngày hôm nay và tải lại báo cáo',
  /bcNgayNay'\)\.addEventListener\('click', function \(\) \{ q\('#bcNgay'\)\.value = homNay\(\); veNutNgay\(\); napBaoCao\(\); \}\)/.test(boCC));
t('🔴 nút to hơn cỡ trước (font-size 14px, không còn 12px thu nhỏ)',
  /function veNutNgay\(\) \{[\s\S]*?font-size:14px;padding:7px 16px/.test(boCC));
t('đổi ngày bằng ô lịch cũng cập nhật lại nút tô đậm (không chỉ bấm nút mới cập nhật)',
  /bcNgay'\)\.addEventListener\('change', function \(\) \{ veNutNgay\(\); napBaoCao\(\); \}\)/.test(boCC));
t('vẽ nút đúng ngay lúc mở màn (không đợi bấm gì mới thấy tô đậm)',
  /veNutNgay\(\);\s*\n\s*sel\.addEventListener/.test(boCC));
const veNutNgayThan = (boCC.match(/function veNutNgay\(\) \{[\s\S]*?\n  \}/) || [''])[0];
t('🔴 hàm tô đậm chỉ so với homNay(), không còn nhắc tới homQua() (bớt gọn theo đúng ý)',
  /homNay\(\)/.test(veNutNgayThan) && !/homQua\(\)/.test(veNutNgayThan));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: chỉ còn nút "Hôm nay", to hơn, tô đậm đúng lúc đang xem hôm nay.');
