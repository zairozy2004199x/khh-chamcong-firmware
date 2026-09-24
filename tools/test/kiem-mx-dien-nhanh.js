/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🧰 ĐIỀN NHANH TRÊN BẢNG MÃ TK NỢ — chép cột (loại → loại) · chép hàng (mảng → mảng), CHỈ ô trống.
 * Anh Thắng 24/09/2026, ảnh ma trận Miền Nam: cột "Chi phí cơ sở" trống 12 mảng, hàng EVENT FZ MN
 * hụt sáu ô — *"đang bị thiếu thật"*.
 * 🔴 CHẠY THẬT `mxChepCot` / `mxChepHang` / `_mxChep` với DOM giả. Chạy: node tools/test/kiem-mx-dien-nhanh.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['_mxChep', 'mxChepCot', 'mxChepHang', '_mxNhanhO', '_mxOMa', '_mxCssGt'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 40, n));

/* Bảng giả: loại × mảng → ô. */
function be(cells, sel, lock) {
  const o = []; Object.keys(cells).forEach((k) => { const [loai, pll] = k.split('|'); o.push({ attrs: { 'data-loai': loai, 'data-pll': pll }, value: cells[k], getAttribute(a) { return this.attrs[a]; } }); });
  const toasts = [], picked = [];
  const F = new Function('MX_LOCK', 'toast', 'document', 'onPickTk',
    ham('_mxCssGt') + ham('_mxNhanhO') + ham('_mxOMa') + ham('_mxChep') + ham('mxChepCot') + ham('mxChepHang') + '\nreturn { cot: mxChepCot, hang: mxChepHang, chep: _mxChep };')(
    !!lock, (k, m) => toasts.push(k + ':' + m),
    { querySelector(q) {
        if (/data-mx-nhanh/.test(q)) { const m = q.match(/data-o="(\w+)"/); return { value: sel[m[1]] || '' }; }
        if (/\.mxNoBody\[data-dv="mn"\]/.test(q)) return { querySelectorAll() { return o; } };
        return null; } },
    (x) => picked.push(x.attrs['data-loai'] + '|' + x.attrs['data-pll']));
  const doc = () => { const r = {}; o.forEach((x) => { r[x.attrs['data-loai'] + '|' + x.attrs['data-pll']] = x.value; }); return r; };
  return { F, toasts, picked, doc };
}
const CELLS = () => ({
  'Chi phí chung VP|EVENT FZ MN': '64196', 'Chi phí chung VP|FARM MN': '64166', 'Chi phí chung VP|FZ MN': '64126', 'Chi phí chung VP|FUNFEST': '',
  'Chi phí cơ sở|EVENT FZ MN': '', 'Chi phí cơ sở|FARM MN': '', 'Chi phí cơ sở|FZ MN': '64199', 'Chi phí cơ sở|FUNFEST': '',
  'Chi phí NVL|EVENT FZ MN': '', 'Chi phí NVL|EVENT GHOST MN': '6329', 'Chi phí hoạt náo|EVENT FZ MN': '', 'Chi phí hoạt náo|EVENT GHOST MN': '64196',
  'Chi phí setup|EVENT FZ MN': '64125', 'Chi phí setup|EVENT GHOST MN': '64125',
});
/* ── 1. 🔴 Chép cột: "Chi phí chung VP" → "Chi phí cơ sở", chỉ ô trống ─────────────────── */
{
  const b = be(CELLS(), { cotTu: 'Chi phí chung VP', cotSang: 'Chi phí cơ sở' });
  const n = b.F.cot('mn'); const d = b.doc();
  teq('🔴 điền 2 ô trống (EVENT, FARM)', 2, n);
  teq('   EVENT FZ MN nhận 64196', '64196', d['Chi phí cơ sở|EVENT FZ MN']);
  teq('   FARM MN nhận 64166', '64166', d['Chi phí cơ sở|FARM MN']);
  teq('🔴 ô ĐÃ CÓ mã (FZ MN 64199) giữ nguyên, không bị đè', '64199', d['Chi phí cơ sở|FZ MN']);
  teq('   nguồn trống (FUNFEST) → đích vẫn trống', '', d['Chi phí cơ sở|FUNFEST']);
  teq('   cột nguồn không đổi', '64196', d['Chi phí chung VP|EVENT FZ MN']);
  t('🔴 câu báo kể số ô chép và số ô giữ, nhắc Lưu', b.toasts.some((x) => /^ok:Chép 2 ô trống từ "Chi phí chung VP" sang "Chi phí cơ sở" · giữ nguyên 1 ô đã có mã\. Soát lại rồi bấm 💾 Lưu\./.test(x)), b.toasts);
  teq('   ô vừa điền được báo cho onPickTk (tô tên tài khoản)', ['Chi phí cơ sở|EVENT FZ MN', 'Chi phí cơ sở|FARM MN'], b.picked.sort());
}
/* ── 2. 🔴 Chép hàng: EVENT GHOST MN → EVENT FZ MN, chỉ ô trống ────────────────────────── */
{
  const b = be(CELLS(), { hangTu: 'EVENT GHOST MN', hangSang: 'EVENT FZ MN' });
  const n = b.F.hang('mn'); const d = b.doc();
  teq('🔴 điền 2 ô trống của EVENT FZ MN (NVL, hoạt náo)', 2, n);
  teq('   NVL 6329', '6329', d['Chi phí NVL|EVENT FZ MN']);
  teq('   hoạt náo 64196', '64196', d['Chi phí hoạt náo|EVENT FZ MN']);
  teq('🔴 setup đã có 64125 → giữ', '64125', d['Chi phí setup|EVENT FZ MN']);
  teq('   ô mà hàng nguồn không có (Chi phí chung VP|EVENT GHOST MN không tồn tại) → giữ nguyên', '64196', d['Chi phí chung VP|EVENT FZ MN']);
}
/* ── 3. Cửa chối ───────────────────────────────────────────────────────────────────────── */
{
  const b = be(CELLS(), { cotTu: 'Chi phí chung VP', cotSang: 'Chi phí cơ sở' }, true);
  teq('🔴 đang khoá 🔒 → không chép, nhắc mở khoá', 0, b.F.cot('mn'));
  t('   câu nhắc', b.toasts.some((x) => /warn:.*mở khoá/.test(x)), b.toasts);
  teq('   … và không ô nào đổi', '', b.doc()['Chi phí cơ sở|EVENT FZ MN']);
  const b2 = be(CELLS(), { cotTu: 'Chi phí cơ sở', cotSang: 'Chi phí cơ sở' });
  teq('   trùng nguồn/đích → 0, báo', 0, b2.F.cot('mn')); t('   câu báo trùng', b2.toasts.some((x) => /trùng/.test(x)), b2.toasts);
  const b3 = be(CELLS(), { cotTu: '', cotSang: 'Chi phí cơ sở' });
  teq('   chưa chọn đủ → 0', 0, b3.F.cot('mn'));
  const b4 = be(CELLS(), { cotTu: 'Chi phí NVL', cotSang: 'Chi phí cơ sở' });
  teq('   nguồn không có mã ở mảng nào chung với đích → 0 (không đè gì)', 0, b4.F.cot('mn'));
  const b5 = be({ 'A|X': '', 'B|X': '' }, { cotTu: 'A', cotSang: 'B' });
  teq('   nguồn trống hoàn toàn → 0, báo "chưa có mã nào để chép"', 0, b5.F.cot('mn')); t('   câu báo', b5.toasts.some((x) => /chưa có mã nào/.test(x)), b5.toasts);
}
/* ── 4. Dải Điền nhanh có mặt trên bảng, nút không bị khoá theo bảng ──────────────────── */
{
  const R = ham('renderTkNoMatrix');
  t('🔴 mỗi bảng khối có dải `data-mx-nhanh` với 4 ô chọn + 2 nút', /data-mx-nhanh=/.test(R) && /oSel\('cotTu'/.test(R) && /oSel\('cotSang'/.test(R) && /oSel\('hangTu'/.test(R) && /oSel\('hangSang'/.test(R) && /onclick="mxChepCot\(/.test(R) && /onclick="mxChepHang\(/.test(R));
  t('   nút và ô chọn mang data-khong-khoa (cái khoá 🔒 chừa chúng ra)', (R.match(/data-khong-khoa="1"[^>]*onclick="mxChep/g) || []).length === 2 && /select data-o="'\+o\+'" data-khong-khoa="1"/.test(R));
  t('   nói rõ "chỉ ô trống"', /Điền nhanh \(chỉ ô trống\)/.test(R));
  t('   tên mảng/loại có dấu nháy được escape khi dựng bộ chọn', /_mxCssGt\(dv\)/.test(ham('_mxOMa')) && /replace\(\/"\/g/.test(ham('_mxCssGt')));
}
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: chép cột / chép hàng chỉ điền ô trống, giữ ô đã có mã, chối khi khoá hay chọn thiếu.');
