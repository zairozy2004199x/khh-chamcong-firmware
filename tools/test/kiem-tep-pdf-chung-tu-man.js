/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô CHỨNG TỪ TRÊN DÒNG: ảnh → thumbnail phóng to được · PDF → huy hiệu 📄 rê hiện trang 1, bấm mở tab mới.
 * Anh Thắng 24/09/2026: *"Cho upload cả file pdf nhé"* rồi *"Cho tính năng rê chuột vào PDF để để hiện
 * ảnh thẳng lên luôn"* (phần rê chuột chạy thật ở kiem-pdf-re-chuot.js; đây chỉ canh dấu trên thẻ).
 * 🔴 CHẠY THẬT `_chungTuHtml` — và đòi hai bảng dùng chung nó, không tự vẽ <img>.
 * Chạy: node tools/test/kiem-tep-pdf-chung-tu-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
t('⚠️ bốc được `_chungTuHtml`', ham('_chungTuHtml').length > 100);
const F = new Function('esc', ham('_laPdf') + ham('_chungTuHtml') + '\nreturn { pdf: _laPdf, ve: _chungTuHtml };')((x) => String(x == null ? '' : x).replace(/"/g, '&quot;'));

/* ── PDF ─────────────────────────────────────────────────────────────────────────────────── */
const P = F.ve('https://khmatrix.com/wp-content/uploads/vhcp/VP/CP_D1_1.pdf', 28);
t('🔴 PDF → huy hiệu 📄, không có <img>', /📄 PDF/.test(P) && !/<img/.test(P), P);
t('🔴 PDF KHÔNG mang data-bill="…" (lớp phủ phóng to là <img>, đưa thẳng .pdf vào là ô trống)', !/data-bill="/.test(P), P);
t('🔴 PDF mang data-bill-pdf="url" để lớp phủ dựng trang 1 khi rê', /data-bill-pdf="https:\/\/khmatrix\.com\/wp-content\/uploads\/vhcp\/VP\/CP_D1_1\.pdf"/.test(P), P);
t('   title nói rê để xem trang 1', /title="Rê chuột để xem trang 1/.test(P), P);
t('   mở tab mới, có rel=noopener', /target="_blank"/.test(P) && /rel="noopener"/.test(P));
t('   link trỏ đúng tệp', /href="https:\/\/khmatrix\.com\/wp-content\/uploads\/vhcp\/VP\/CP_D1_1\.pdf"/.test(P));
t('   nhận .PDF viết hoa và có ?query', F.pdf('a/b.PDF') && F.pdf('a/b.pdf?x=1') && F.pdf('a/b.pdf#p2'));
t('   không nhận tên chỉ CHỨA chữ pdf', !F.pdf('a/pdf-hoa-don.jpg') && !F.pdf(''));
/* ── Ảnh ─────────────────────────────────────────────────────────────────────────────────── */
const A = F.ve('https://x/y/CP_1.jpg', 34);
t('🔴 ảnh → <img> có data-bill để rê chuột phóng to', /<img src="https:\/\/x\/y\/CP_1\.jpg" data-bill="https:\/\/x\/y\/CP_1\.jpg"/.test(A), A);
t('   ảnh: bấm mở gốc, cao theo tham số', /target="_blank"/.test(A) && /height:34px/.test(A));
t('   rỗng → rỗng', F.ve('', 28) === '' && F.ve(null, 28) === '');
t('   URL có dấu nháy bị escape', !/"javascript/.test(F.ve('a".jpg', 28)) && /&quot;/.test(F.ve('a".jpg', 28)));
/* ── Hai bảng dùng chung ──────────────────────────────────────────────────────────────────── */
t('🔴 bảng dòng đơn (renderLines) vẽ chứng từ qua `_chungTuHtml`', /_chungTuHtml\(l\.anh, 28\)/.test(HTML));
t('🔴 bảng dòng ở Quyết toán vẽ qua `_chungTuHtml`', /_chungTuHtml\(l\.anh, 34\)/.test(HTML));
t('🔴 không còn chỗ nào tự vẽ <img … data-bill="…l.anh…"> ngoài hàm chung', (HTML.match(/<img src="'\+esc\(l\.anh\)/g) || []).length === 0);

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: ảnh ra thumbnail phóng to được, PDF ra huy hiệu mang data-bill-pdf mở tab mới, hai bảng dùng chung một hàm.');
