/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HỘP CHỌN CƠ SỞ HAI CỘT — nhóm cha (phân loại lớn) bên trái, cơ sở con bên phải.
 *
 * Anh Thắng 23/09/2026: *"Tách ra 2 cột theo khối, chọn khối cha ra rồi con, chứ nó đang chung
 * không chọn được"* — danh mục gần trăm gian (POSH MN 67), một danh sách phẳng thì không chọn nổi.
 *
 * 🔴 CHẠY THẬT `_cosoSel` rồi `_csNhom` / `_csLoc` / `_csAll` / `_csDone` / `_csDem` trên một DOM giả
 *    dựng lại từ chính HTML vừa vẽ. Điều phải giữ bằng mọi giá: đổi nhóm KHÔNG làm mất ô đã tích ở
 *    nhóm khác, và `_csDone` vẫn đọc đủ mọi ô — kể cả ô đang ẩn.
 *
 * Chạy: node tools/test/kiem-chon-coso-hai-cot.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['_csNhomCua', '_csLoc', '_csNhom', '_csDem', '_cosoSel', '_csTim', '_csAll', '_csDone', '_csWrap', '_csLabel', '_csPhu', '_csNhan', '_bd']
  .forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 30, n));

/* ── bệ đỡ: danh mục ba nhóm ─────────────────────────────────────────────────────────────── */
const CFG = { coso: [
  { ten: 'Khu Vui Chơi MN', phanLoaiLon: 'KVC MN', donVi: 'K&H' }, { ten: 'Văn Phòng KVC MN', phanLoaiLon: 'KVC MN', donVi: 'K&H' },
  { ten: 'Máy Tự Động MN', phanLoaiLon: 'MTĐ MN', donVi: 'POSH' },
  { ten: 'YOKID BUÔN MÊ THUỘT', phanLoaiLon: 'POSH MN', donVi: 'POSH', tenMisa: 'POSH MN YOKID' }, { ten: 'AEON MALL BÌNH DƯƠNG', phanLoaiLon: 'POSH MN', donVi: 'POSH' }, { ten: 'AEON MALL TÂN PHÚ', phanLoaiLon: 'POSH MN', donVi: 'POSH' },
] };
const BOOT = { dons: [{ coso: 'GIAN NGOÀI DANH MỤC' }], donVi: ['K&H', 'POSH'], donViMe: 'K&H' };
const SRC = "var CS_NHOM_LA='(Ngoài danh mục)';\n" + ['_bd', '_csPhu', '_csNhan', '_csLabel', '_csNhomCua', '_csWrap', '_csLoc', '_csNhom', '_csDem', '_csTim', '_csAll', '_csDone', '_cosoSel'].map(ham).join('\n');
function ve(v) {
  return new Function('CFG', 'BOOT', 'esc', '_cosoTheoDv', '_laDvMe', '_dvChuan', SRC + '\nreturn _cosoSel(v);'.replace('v)', JSON.stringify(v) + ')'))(
    CFG, BOOT, (x) => String(x == null ? '' : x), () => [], () => true, (x) => String(x));
}
/* DOM giả từ HTML: nhãn (label + input), nút nhóm, ô tìm, select ẩn. */
function dom(h) {
  const labels = []; const re = /<label data-tim="([^"]*)" data-nhom="([^"]*)" style="display:(flex|none)[^"]*"><input type="checkbox" data-i="(\d+)" onchange="_csDem\(this\)"( checked)?>/g; let m;
  while ((m = re.exec(h))) { const L = { style: { display: m[3] }, thuoc: { 'data-tim': m[1], 'data-nhom': m[2] }, chk: { checked: !!m[5], thuoc: { 'data-i': m[4] } } };
    L.getAttribute = (k) => (k in L.thuoc ? L.thuoc[k] : null); L.querySelector = () => L.chk; L.chk.getAttribute = (k) => L.chk.thuoc[k]; labels.push(L); }
  const nuts = []; const rn = /<button type="button" class="cs-nh" data-nhom="([^"]*)"[^>]*><span>([^<]*)<\/span><span class="cs-dem"[^>]*>([^<]*)<\/span>/g;
  while ((m = rn.exec(h))) { const B = { thuoc: { 'data-nhom': m[1] }, ten: m[2], dem: { textContent: m[3] }, style: {} }; B.getAttribute = (k) => B.thuoc[k]; B.querySelector = () => B.dem; nuts.push(B); }
  const opts = []; const ro = /<option value="([^"]*)"( selected)?>/g; while ((m = ro.exec(h))) opts.push({ value: m[1], selected: !!m[2] });
  const W = { thuoc: { 'data-nhom': (h.match(/<div class="csw" data-nhom="([^"]*)"/) || [, '*'])[1] }, className: 'csw', tim: { value: '' }, btn: { textContent: '' }, pop: { style: { display: 'none' } }, sel: { options: opts } };
  W.getAttribute = (k) => (k in W.thuoc ? W.thuoc[k] : null); W.setAttribute = (k, v) => { W.thuoc[k] = v; };
  W.querySelector = (s) => (s === '.cs-tim' ? W.tim : s === '.cs-btn' ? W.btn : s === '.cs-pop' ? W.pop : s === 'select' ? W.sel : null);
  W.querySelectorAll = (s) => (s === '.cs-list label' ? labels : s === '.cs-list input' ? labels.map((l) => l.chk) : s === '.cs-nh' ? nuts : []);
  labels.forEach((l) => { l.parentNode = W; l.chk.parentNode = l; }); nuts.forEach((b) => { b.parentNode = W; }); W.tim.parentNode = W; W.btn.parentNode = W;
  const F = new Function('CFG', 'BOOT', 'esc', SRC + '\nreturn { loc: _csLoc, nhom: _csNhom, dem: _csDem, tim: _csTim, all: _csAll, done: _csDone };')(CFG, BOOT, (x) => String(x));
  return { W, labels, nuts, opts, F, hien: () => labels.filter((l) => l.style.display !== 'none').map((l) => opts[+l.chk.thuoc['data-i']].value) };
}

/* ── 1. 🔴 Vẽ ra hai cột, nhóm cha đúng, số đếm đúng ───────────────────────────────────── */
{
  const h = ve('AEON MALL TÂN PHÚ');
  const d = dom(h);
  teq('🔴 cột trái: Tất cả nhóm + 4 nhóm (3 phân loại lớn + ngoài danh mục)', ['*', 'KVC MN', 'MTĐ MN', 'POSH MN', '(Ngoài danh mục)'], d.nuts.map((b) => b.thuoc['data-nhom']));
  teq('   số đếm đã tích/tổng từng nhóm', ['1/7', '0/2', '0/1', '1/3', '0/1'], d.nuts.map((b) => b.dem.textContent));
  teq('🔴 nhóm mở sẵn = nhóm của cơ sở ĐÃ TÍCH (POSH MN)', 'POSH MN', d.W.thuoc['data-nhom']);
  teq('   cột phải chỉ hiện cơ sở POSH MN', ['YOKID BUÔN MÊ THUỘT', 'AEON MALL BÌNH DƯƠNG', 'AEON MALL TÂN PHÚ'], d.hien());
  teq('🔴 nhưng MỌI cơ sở đều có ô tích trong hộp (ẩn, không mất)', 7, d.labels.length);
  t('   ô đã tích giữ nguyên trạng thái', d.labels.filter((l) => l.chk.checked).length === 1 && d.opts.filter((o) => o.selected).length === 1);
  t('   nút Chọn hết / Bỏ hết nói rõ là theo NHÓM', /Chọn hết nhóm/.test(h) && /Bỏ hết nhóm/.test(h));
  const h0 = ve('');
  teq('   chưa tích gì → mở nhóm đầu danh mục', 'KVC MN', (h0.match(/<div class="csw" data-nhom="([^"]*)"/) || [])[1]);
}
/* ── 2. 🔴 Đổi nhóm: hiện đúng nhóm, KHÔNG mất ô đã tích ở nhóm khác ────────────────────── */
{
  const d = dom(ve('AEON MALL TÂN PHÚ'));
  d.F.nhom(d.nuts[1]);            // bấm "KVC MN"
  teq('🔴 bấm KVC MN → chỉ hiện 2 cơ sở KVC', ['Khu Vui Chơi MN', 'Văn Phòng KVC MN'], d.hien());
  t('🔴 ô AEON MALL TÂN PHÚ (nhóm khác, đang ẩn) VẪN tích', d.labels.find((l) => d.opts[+l.chk.thuoc['data-i']].value === 'AEON MALL TÂN PHÚ').chk.checked === true);
  teq('   nhóm đang chọn được ghi lên .csw', 'KVC MN', d.W.thuoc['data-nhom']);
  t('   nút nhóm đang chọn tô đậm, nút khác không', d.nuts[1].style.fontWeight === '700' && d.nuts[3].style.fontWeight === '400');
  d.F.nhom(d.nuts[0]);            // "Tất cả nhóm"
  teq('   Tất cả nhóm → hiện đủ 7', 7, d.hien().length);
}
/* ── 3. Chọn hết chỉ áp cho nhóm đang thấy; số đếm cập nhật; Xong đọc đủ mọi ô ─────────── */
{
  const d = dom(ve('AEON MALL TÂN PHÚ'));
  d.F.nhom(d.nuts[1]);            // KVC MN
  d.F.all(d.nuts[1], 1);          // Chọn hết nhóm
  teq('🔴 Chọn hết nhóm: tích đúng 2 cơ sở KVC + 1 đã có = 3', 3, d.labels.filter((l) => l.chk.checked).length);
  teq('   số đếm KVC MN → 2/2, POSH MN vẫn 1/3, Tất cả 3/7', ['3/7', '2/2', '0/1', '1/3', '0/1'], d.nuts.map((b) => b.dem.textContent));
  d.F.done(d.nuts[1]);
  teq('🔴 Xong ghi đủ 3 cơ sở vào select ẩn (kể cả ô đang ẩn ở POSH MN)', ['Khu Vui Chơi MN', 'Văn Phòng KVC MN', 'AEON MALL TÂN PHÚ'], d.opts.filter((o) => o.selected).map((o) => o.value));
  t('   nhãn nút cập nhật', /3 cơ sở/.test(d.W.btn.textContent), d.W.btn.textContent);
  teq('   hộp đóng lại', 'none', d.W.pop.style.display);
  d.F.all(d.nuts[1], 0);          // Bỏ hết nhóm (đang ở KVC MN)
  teq('   Bỏ hết nhóm: chỉ bỏ KVC, POSH còn 1', ['AEON MALL TÂN PHÚ'], d.labels.filter((l) => l.chk.checked).map((l) => d.opts[+l.chk.thuoc['data-i']].value));
}
/* ── 4. Tìm xuyên nhóm ────────────────────────────────────────────────────────────────── */
{
  const d = dom(ve('AEON MALL TÂN PHÚ'));   // đang ở POSH MN
  d.W.tim.value = 'khu vui';
  d.F.tim(d.W.tim);
  teq('🔴 gõ tìm → thấy cơ sở ở NHÓM KHÁC (KVC), không bị nhóm đang chọn che', ['Khu Vui Chơi MN'], d.hien());
  t('   đang tìm thì không nút nhóm nào tô đậm', d.nuts.every((b) => b.style.fontWeight === '400'));
  d.W.tim.value = '';
  d.F.tim(d.W.tim);
  teq('   xoá chữ tìm → về lại nhóm đang chọn', 3, d.hien().length);
  d.W.tim.value = 'aeon';          // đang gõ tìm (2 kết quả POSH)…
  d.F.tim(d.W.tim);
  d.F.nhom(d.nuts[2]);            // …rồi bấm thẳng sang nhóm MTĐ: ô tìm phải bị xoá, không thì nhóm mới trống trơn
  teq('🔴 đổi nhóm XOÁ ô tìm và hiện đúng nhóm mới (không trống vì chữ tìm cũ)', ['Máy Tự Động MN'], d.hien());
  teq('   ô tìm đã trống', '', d.W.tim.value);
}
/* ── 5. Cơ sở ngoài danh mục (lấy từ đơn) về nhóm riêng ──────────────────────────────── */
{
  const d = dom(ve(''));
  d.F.nhom(d.nuts[4]);
  teq('   nhóm "(Ngoài danh mục)" chứa gian chỉ có trên đơn', ['GIAN NGOÀI DANH MỤC'], d.hien());
}
/* ── 6. Danh mục chỉ MỘT nhóm → không vẽ cột trái, hiện hết ─────────────────────────── */
{
  const CFG1 = { coso: [{ ten: 'A', phanLoaiLon: 'X' }, { ten: 'B', phanLoaiLon: 'X' }] };
  const h1 = new Function('CFG', 'BOOT', 'esc', '_cosoTheoDv', '_laDvMe', '_dvChuan', SRC + "\nreturn _cosoSel('');")(CFG1, { dons: [] }, String, () => [], () => true, String);
  t('   một nhóm → không có cột nhóm, mọi dòng hiện, nút Chọn hết thường', !/class="cs-nh"/.test(h1) && !/display:none;gap/.test(h1) && /Chọn hết<\/button>/.test(h1), h1.slice(0, 300));
}
/* ── 7. Cột trái không lọt vào `_readRows` (đọc ô theo thứ tự) ───────────────────────── */
t('🔴 nút nhóm là <button>, không phải <input>/<select> — `_readRows` không đọc nhầm', /<button type="button" class="cs-nh"/.test(ve('')) && !/<input[^>]*class="cs-nh"/.test(ve('')));

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: hộp chọn cơ sở hai cột — chọn nhóm cha rồi con, đổi nhóm không mất ô đã tích, tìm xuyên nhóm.');
