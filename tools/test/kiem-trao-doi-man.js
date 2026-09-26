/**
 * "TRAO ĐỔI" — GIAO DIỆN: Ô HỎI/ĐÁP DƯỚI GHI CHÚ, VÀ KHOÁ "ĐÃ NỘP TIỀN" KHÔNG KHOÁ Ô NÀY.
 *
 * Anh Thắng 26/09/2026: *"Anh muốn nút ghi chú này là dạng ghi chú và kèm hỏi, khi nhân viên nhập
 * ghi chú, kế toán có thể phản hồi nút đó (dạng trao đổi qua lại nếu chưa rõ thông tin giữa 2
 * bên)"*. Bài PHP (`kiem-trao-doi.php`) canh máy chủ; bài NÀY canh đúng phần giao diện — ô mới
 * không lẫn với `bc_ghi_chu` cũ, và vòng khoá input khi "Đã nộp tiền" KHÔNG khoá luôn ô hỏi đáp
 * (nhu cầu hỏi thêm sau khi khoá vẫn còn, khoá riêng theo mất-quyền-ghi mà thôi).
 *
 * Chạy: node tools/test/kiem-trao-doi-man.js
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

t('có ô hiển thị luồng trao đổi (#bcTraoDoi), ô gõ mới (#bcTraoDoiMoi) và nút gửi (#bcTraoDoiGui)',
  /id="bcTraoDoi"/.test(boCC) && /id="bcTraoDoiMoi"/.test(boCC) && /id="bcTraoDoiGui"/.test(boCC));
t('🔴 vẫn còn nguyên ô Ghi chú cũ (#bc_ghi_chu) — không thay thế, chỉ thêm mới bên cạnh',
  /id="bc_ghi_chu"/.test(boCC));
t('nút Gửi bấm gọi guiTraoDoi(), và Enter trong ô gõ cũng gửi luôn',
  /bcTraoDoiGui'\)\.addEventListener\('click', guiTraoDoi\)/.test(boCC)
  && /bcTraoDoiMoi'\)\.addEventListener\('keydown', function \(ev\) \{ if \(ev\.key === 'Enter'\)/.test(boCC));
t('napBaoCao() vẽ lại luồng trao đổi từ r.trao_doi mỗi lần tải', /veTraoDoi\(r\.trao_doi \|\| \[\]\)/.test(boCC));
t('guiTraoDoi() gửi đúng ba trường: ngay, cua_hang, noi_dung, gọi đúng cửa trao-doi',
  /fd\.append\('ngay', q\('#bcNgay'\)\.value\);\s*fd\.append\('cua_hang', q\('#bcCH'\)\.value\);\s*fd\.append\('noi_dung', nd\);\s*api\('trao-doi'/.test(boCC));
t('không gửi khi ô trống (nd rỗng thì return sớm, không gọi api)',
  /var o = q\('#bcTraoDoiMoi'\), nd = o\.value\.trim\(\);\s*if \(!nd\) return;/.test(boCC));

t('🔴 vòng khoá input khi "Đã nộp tiền"/hết quyền KHÔNG khoá #bcTraoDoiMoi / #bcTraoDoiGui',
  /if \(e\.id !== 'bcNgay' && e\.id !== 'bcCH' && e\.id !== 'bcGoKhoa' && e\.id !== 'bcTraoDoiMoi' && e\.id !== 'bcTraoDoiGui'\) e\.disabled = khoa;/.test(boCC));
t('🔴 hai ô trao đổi khoá RIÊNG theo mất quyền ghi (khoaTraoDoi = !r.duoc_ghi) — KHÔNG kèm daNop',
  /var khoaTraoDoi = !r\.duoc_ghi;\s*\n\s*q\('#bcTraoDoiMoi'\)\.disabled = khoaTraoDoi;\s*\n\s*q\('#bcTraoDoiGui'\)\.disabled = khoaTraoDoi;/.test(boCC));

/* Bài học "nút lúc ẩn lúc hiện" (ba lượt báo riêng của anh Thắng 26/09/2026) — khối tự sửa cơ sở
   phải đứng NGAY ĐẦU .then(), trước bất kỳ .hidden/DOM nào của lượt render, không phải cuối hàm.
   🔴 Lấy đúng thân hàm napBaoCao() (đánh dấu bởi "function napBaoCao() {" ... "\n  }" ở cỡ thụt lề
   2 dấu cách của khai báo hàm cấp module) — không lấy nhầm .then() của hàm api() dùng chung, vốn
   xuất hiện TRƯỚC trong tệp và cũng khớp mẫu ".then(function (r) {". */
const thanNap = (boCC.match(/function napBaoCao\(\) \{[\s\S]*?\n {2}\}/) || [''])[0];
const viTriTuSua = thanNap.indexOf("if (r.cua_toi && q('#bcCH').value !== r.cua_toi");
const viTriHidden = thanNap.indexOf("q('#bcTaiAnh').hidden");
t('🔴 khối tự sửa cơ sở đứng TRƯỚC mọi .hidden của lượt render (không còn kẽ hở "lúc ẩn lúc hiện")',
  viTriTuSua > -1 && viTriHidden > -1 && viTriTuSua < viTriHidden);
t('khối tự sửa cơ sở chỉ xuất hiện ĐÚNG MỘT LẦN trong napBaoCao() (không còn bản sao cũ ở cuối hàm)',
  (thanNap.match(/r\.cua_toi && q\('#bcCH'\)\.value !== r\.cua_toi/g) || []).length === 1);

t('có chữ nhỏ "Chốt báo cáo ngày: ..." dưới hàng nút, cập nhật ngay đầu napBaoCao()',
  /id="bcXacNhanNgay"/.test(boCC) && /S\.nhapNgay = ngay; S\.nhapCH = ch;\s*\n\s*veXacNhanNgay\(\);\s*\n\s*if \(!ngay \|\| !ch\) return;/.test(boCC));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: ô trao đổi tách riêng ghi_chu, không bị khoá "Đã nộp tiền" chặn, khối tự sửa cơ sở đứng đầu hàm.');
