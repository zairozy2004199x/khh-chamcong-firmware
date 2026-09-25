/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CƠ SỞ GÁN RIÊNG TỪNG NGƯỜI — MÀN PHẢI NỐI ĐÚNG VÀO LÕI.
 *
 * Anh Thắng 25/09/2026: *"tách riêng nhân viên, nó gộp dẫn đến nhân viên chung cơ sở"*. `kiem-co-so-rieng.php`
 * chạy thật máy chủ. Bài này canh tab Quản trị:
 *   · bảng Phân quyền PIN có cột "Cơ sở riêng" — mỗi người một ô tích (data-cs-rieng-o), tích sẵn theo coso_rieng;
 *   · nút Lưu gửi vai + co_so (JSON các ô đã tích; [] = về theo mã);
 *   · bảng Ghép cơ sở nói rõ người nào đang gán riêng (không theo bảng ấy).
 *
 * Chạy: node tools/test/kiem-co-so-rieng-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('wordpress/khh-doanh-thu/assets/doanh-thu.js', 'utf8');
const js = src.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/(^|[^:'"])\/\/[^\n]*/g, '$1');
let dat = 0; const hong = [];
function t(ten, dk) { if (dk) dat++; else hong.push(ten); }
function boc(ten) {
  const i = js.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = js.indexOf('\n  }\n', i);
  return js.slice(i, j + 4);
}
const vq = boc('veQuanTri');
t('🔴 bảng PIN có cột "Cơ sở riêng — tích để đè bảng ghép"', /<th>Cơ sở riêng — tích để đè bảng ghép<\/th>/.test(vq));
t('mỗi người một khối ghep-chon data-cs-rieng, ô tích data-cs-rieng-o mang mã NV, tích sẵn theo coso_rieng (so lỏng)', /data-cs-rieng="' \+ esc\(x\.ma_nv\)/.test(vq) && /data-cs-rieng-o="' \+ esc\(x\.ma_nv\) \+ '" value="' \+ esc\(t\)/.test(vq) && /rieng\.indexOf\(t\) >= 0 \|\| rieng\.some/.test(vq));
t('nhắc: đang gán riêng N quán / chưa gán riêng — đang theo mã', /Đang gán riêng ' \+ rieng\.length \+ ' quán/.test(vq) && /Chưa gán riêng — đang theo mã/.test(vq));
t('vai duyệt thì khối mờ (mo-nhat) vì xem tổng', /x\.vai === 'duyet' \? ' mo-nhat' : ''\) \+ '" data-cs-rieng/.test(vq));
t('🔴 Lưu gửi vai + co_so = JSON các ô đã tích của ĐÚNG dòng ấy', /tr\.querySelectorAll\('input\[data-cs-rieng-o\]'\)/.test(vq) && /fd\.append\('co_so', JSON\.stringify\(csRieng\)\)/.test(vq) && /api\('nguoi-vai', \{ method: 'POST', body: fd \}\)/.test(vq));
t('chú thích giải thích cơ sở riêng đè bảng ghép', /<b>Cơ sở riêng<\/b>: người cùng mã nhân sự vốn dùng chung bảng ghép/.test(vq));
const vg = boc('veGhep');
t('bảng Ghép cơ sở ghi "gán riêng N quán — không theo bảng này" cạnh tên người', /gán riêng ' \+ x\.coso_rieng\.length \+ ' quán — không theo bảng này/.test(vg));

/* chạy thật veQuanTri với hai người cùng mã, một người gán riêng */
const than = vq + "\n  var quyens = [];\n  function esc(s) { return String(s == null ? '' : s).replace(/[&<>\"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;' }[c]; }); }\n  return veQuanTri;";
let ve = null;
try { ve = new Function(than)(); } catch (e) { hong.push('không nạp được veQuanTri: ' + e.message); }
if (ve) {
  const o = { innerHTML: '', querySelectorAll: function () { return []; }, querySelector: function () { return null; } };
  const FZ = 'FUNZONE ADVENTURE GO AN LẠC', ES = 'Tutu Train -  Estella';
  ve(o, { ds: [], cua_hang: [FZ, ES], cong: {}, pin: [
    { ma_nv: 'NV171', ho_ten: 'Trí', coso_ds: ['FZ_ADV_TP'], coso_ten: [FZ, ES], coso_rieng: [], vai: 'nhap', co_pin: true },
    { ma_nv: 'NV173', ho_ten: 'Thảo', coso_ds: ['FZ_ADV_TP'], coso_ten: [FZ, ES], coso_rieng: ['Tutu Train - Estella'], vai: 'nhap', co_pin: true },
  ] });
  const h = o.innerHTML;
  t('🔴 Thảo: ô Estella tích sẵn (tên gõ một dấu cách vẫn khớp), ô Funzone không; nhắc "Đang gán riêng 1 quán"', /data-cs-rieng-o="NV173" value="Tutu Train -  Estella" checked/.test(h) && !/data-cs-rieng-o="NV173" value="FUNZONE ADVENTURE GO AN LẠC" checked/.test(h) && /Đang gán riêng 1 quán/.test(h));
  t('Trí: không ô nào tích, nhắc "theo mã (2 quán)"', !/data-cs-rieng-o="NV171"[^>]*checked/.test(h) && /Chưa gán riêng — đang theo mã \(2 quán\)/.test(h));
}

console.log(hong.length ? '✗ HỎNG ' + hong.length + ' / ' + (dat + hong.length) + ' phép:\n  · ' + hong.join('\n  · ')
  : '✓ SẠCH — ' + dat + ' phép: cột Cơ sở riêng nối đúng cổng nguoi-vai.');
process.exit(hong.length ? 1 : 0);
