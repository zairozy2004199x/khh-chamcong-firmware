/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG BÁO CÁO GHẾ PHẢI LUÔN ĐỦ CỘT — KHÔNG CÒN CHẾ ĐỘ "GỌN"
 *
 * Anh Thắng 31/08/2026: *"bỏ tính năng rút gọn, rút gọn nó làm mất cột nhập liệu"*.
 *
 * 🔴 VÌ SAO ĐÁNG MỘT BÀI KIỂM RIÊNG. Chế độ Gọn ra đời với ý tốt (27/08: điện thoại ít thông tin
 *    thôi), và chính vì ý tốt ấy mà nó dễ quay lại. Nhưng thứ nó bớt đi lại đúng là mấy cột
 *    NGƯỜI TA PHẢI ĐIỀN — QR, thực thu tiền mặt, ghi chú. Người chốt ca trên điện thoại gõ xong
 *    thấy bảng "đủ", gửi đi, và số thiếu; không có gì trên màn nói rằng còn cột nữa ở đâu đó.
 *    Một chế độ xem mà giấu mất ô nhập thì không phải chế độ xem, nó là một cái bẫy.
 *
 * ⚠️ SOI CHÍNH ĐOẠN JS trong tệp PHP, không soi màn hình đã dựng: bài này chạy không cần trình
 *    duyệt, và thứ cần canh là MÃ có còn nhánh giấu cột hay không.
 *
 * Chạy: node tools/test/kiem-bang-du-cot.js vhcp-ghe/includes/class-vhg-trang.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const duong = process.argv[2] || 'vhcp-ghe/includes/class-vhg-trang.php';
const src = fs.readFileSync(duong, 'utf8');
const i = src.indexOf("<<<'JS'");
const js = src.slice(src.indexOf('\n', i) + 1, src.indexOf('\nJS;', i));

let dat = 0; const truot = [];
function t(ten, ok, chi) { if (ok) { dat++; } else { truot.push(ten + (chi ? ' → ' + String(chi).slice(0, 200) : '')); } }

/* 1. Không còn một dấu vết nào của cờ GON. Còn cờ là còn đường quay lại. */
t('không còn biến GON', !/\bGON\b/.test(js));
t('không còn hàm datGon', !/datGon/.test(js));
t('không còn nhớ lựa chọn trong localStorage', !/bc_gon/.test(js));
t('không còn nút "Gọn" / "Đầy đủ"', !/📱 Gọn|🖥 Đầy đủ/.test(js));

/* 2. Bảng ghế phải khai ĐỦ MƯỜI cột, trong đó có ba cột nhập từng bị Gọn giấu mất. */
const dau = js.match(/tb\.innerHTML\s*=\s*([\s\S]{0,600}?);/);
t('tìm được nơi dựng đầu bảng', !!dau, js.slice(0, 200));
const html = dau ? dau[1] : '';
for (const cot of ['Ghế', 'Chỉ số trước', 'Chỉ số sau', 'Actual', 'Tiền mặt', 'QR',
                   'Thực thu tiền mặt', 'Ghi chú', 'Chỉ số', 'Vệ sinh']) {
  t('đầu bảng có cột "' + cot + '"', html.indexOf('>' + cot + '<') !== -1
    || html.indexOf(cot + '</th>') !== -1, html);
}
/* 🔴 BA CỘT NÀY LÀ Ô NHẬP, không phải cột đọc — mất chúng là mất số, không phải mất chỗ nhìn. */
t('🔴 đầu bảng KHÔNG có nhánh nào ít cột hơn',
  !/\?[\s\S]{0,400}<thead/.test(html), html);

/* 3. Bảng luôn mang lớp `full` (ép rộng tối thiểu rồi cuộn ngang), không bao giờ `gon`. */
t('bảng luôn là .full', /el\('table','bc-t full'\)/.test(js), js.slice(0, 200));
t('không còn lớp .gon nào trong JS', !/bc-t\.gon|'gon'/.test(js));

/* 4. Bốn ô tổng cuối bảng cũng phải đủ — Gọn từng bớt xuống còn hai. */
const tot = js.match(/tot\.innerHTML\s*=\s*([\s\S]{0,700}?);/);
t('tìm được nơi dựng ô tổng', !!tot);
const th = tot ? tot[1] : '';
for (const o of ['Actual', 'Tiền mặt phải nộp', 'QR', 'Doanh thu ngày']) {
  t('ô tổng có "' + o + '"', th.indexOf(o) !== -1, th);
}
t('🔴 ô tổng KHÔNG có nhánh nào ít ô hơn', !/\?[\s\S]{0,300}bc-tt/.test(th), th);

/* 5. Và mã vẫn chạy được — một bài chỉ soi chuỗi mà mã hỏng cú pháp thì soi gì cũng vô nghĩa. */
let cu_phap = true;
try { new Function(js); } catch (e) { cu_phap = false; truot.push('JS hỏng cú pháp: ' + e.message); }
t('JS còn hợp lệ sau khi gỡ', cu_phap);

console.log('');
for (const x of truot) { console.log('  ✗ ' + x); }
if (truot.length) { console.log('🔴 TRƯỢT: ' + truot.length + ' | ĐẠT: ' + dat); process.exit(1); }
console.log('✓ ĐẠT ' + dat + ' phép — bảng báo cáo ghế luôn đủ cột, không còn chế độ Gọn.');
