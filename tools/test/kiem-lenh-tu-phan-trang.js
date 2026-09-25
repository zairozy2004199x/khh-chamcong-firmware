/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG 📜 LỆNH TẠM ỨNG: 5 LỆNH MỘT TRANG, Ở CẢ HAI TAB.
 *
 * Anh Thắng 23/09/2026: *"Hiện 5 đơn 1 trang thôi cho gọn, vì nó ít khi dùng"* (ảnh tab Duyệt,
 * 34 tờ lệnh xếp dài kín màn), rồi *"bên trang quyết toán nó cũng vậy"*.
 *
 * 🔴 CHẠY THẬT `_veLenhTU` với DOM giả, không soi chữ. Soi `slice(` thì `slice(0)` vẫn xanh.
 *    Ba bẫy cần bắt bằng hành vi:
 *    · badge phải là TỔNG THẬT (34), không phải 5 — không thì hai màn nói hai con số cho một sổ;
 *    · số trang nhớ RIÊNG từng bảng — lật tab Quyết toán không kéo tab Duyệt đi theo;
 *    · KẸP trang: đang ở trang 7 mà sổ chỉ còn 2 trang → phải hiện trang cuối, không trắng.
 *
 * Chạy: node tools/test/kiem-lenh-tu-phan-trang.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };

t('⚠️ bốc được `_veLenhTU`', ham('_veLenhTU').length > 400);
t('⚠️ bốc được `_qtPagerHtml`', ham('_qtPagerHtml').length > 400);
t('⚠️ bốc được `lenhTUTrang`', ham('lenhTUTrang').length > 50);
const mVar = HTML.match(/\n  var LENH_MOI_TRANG=(\d+), LENH_TRANG=\{[^\n]*\};/);
t('⚠️ có dòng khai `LENH_MOI_TRANG` + ô nhớ trang `LENH_TRANG`', !!mVar, mVar && mVar[0]);
t('🔴 5 lệnh một trang như anh Thắng chốt', !!mVar && mVar[1] === '5', mVar && mVar[1]);

/* Markup: hai bảng, mỗi bảng một chỗ để vẽ thanh trang, đặt SAU bảng và trước khối chẩn đoán. */
for (const [body, pager] of [['lenhTUBody', 'lenhTUPager'], ['lenhTUBodyKT', 'lenhTUPagerKT']]) {
  const iB = HTML.indexOf('id="' + body + '"'), iP = HTML.indexOf('id="' + pager + '"');
  t('🔴 có chỗ vẽ thanh trang `' + pager + '` ngay dưới bảng `' + body + '`', iB > 0 && iP > iB && iP - iB < 800, { iB, iP });
}

/* ── DOM giả ─────────────────────────────────────────────────────────────────────────────── */
const DOM = {};
function el(id) { if (!(id in DOM)) DOM[id] = { innerHTML: '', textContent: '', style: {}, scrollIntoView() { DOM[id].cuon = (DOM[id].cuon || 0) + 1; } }; return DOM[id]; }
const esc = (x) => String(x == null ? '' : x);
const money = (x) => String(Number(x || 0));
const G = {};
let goiRender = 0;
const SRC = mVar[0] + '\n  var QT_MOI_TRANG=15;\n' + ham('_qtPagerHtml') + ham('_veLenhTU') + ham('lenhTUTrang')
  + '\n  function renderLenhTU(){ G.render++; _veLenhTU("lenhTUBody","lenhTUSo","lenhTUEmpty"); _veLenhTU("lenhTUBodyKT","lenhTUSoKT","lenhTUEmptyKT"); }'
  + '\n  return { ve: _veLenhTU, trang: lenhTUTrang, render: renderLenhTU, TRANG: LENH_TRANG, moi: function(){ return LENH_MOI_TRANG; } };';
const lenh = (n) => { const r = []; for (let i = 1; i <= n; i++) r.push({ luc: 'L' + i, nguoi: 'N', ky: 'K', tong: i * 1000, soDon: 1, soCoso: 1, chiTiet: [{ coso: 'CS', tien: i * 1000, soDon: 1, maDons: ['M' + i] }] }); return r; };
G.LENH_TU = lenh(34); G.render = 0;
const M = new Function('el', 'esc', 'money', 'G', 'var LENH_TU=G.LENH_TU;' + SRC.replace(/LENH_TU\.length/g, 'G.LENH_TU.length').replace(/LENH_TU\.slice/g, 'G.LENH_TU.slice'))(el, esc, money, G);
const demDong = (id) => (el(id).innerHTML.match(/<tr>/g) || []).length;
const dongDau = (id) => { const m = el(id).innerHTML.match(/<td style="white-space:nowrap">([^<]*)<\/td>/); return m ? m[1] : ''; };

/* ── 1. Trang đầu: 5 dòng, badge = 34 ─────────────────────────────────────────────────────── */
M.render();
teq('🔴 tab Duyệt: trang 1 chỉ vẽ 5 dòng', 5, demDong('lenhTUBody'));
teq('🔴 tab Quyết toán: trang 1 chỉ vẽ 5 dòng', 5, demDong('lenhTUBodyKT'));
teq('🔴 badge tab Duyệt vẫn là TỔNG THẬT 34, không phải 5', '34', String(el('lenhTUSo').textContent));
teq('🔴 badge tab Quyết toán cũng 34', '34', String(el('lenhTUSoKT').textContent));
teq('   trang 1 bắt đầu từ lệnh MỚI NHẤT (giữ thứ tự sổ máy chủ trả về)', 'L1', dongDau('lenhTUBody'));
teq('   ô "chưa có lệnh" ẩn khi có lệnh', 'none', el('lenhTUEmpty').style.display);
t('🔴 có thanh chuyển trang dưới bảng tab Duyệt', /lenhTUTrang\('lenhTUBody',2\)/.test(el('lenhTUPager').innerHTML), el('lenhTUPager').innerHTML);
t('🔴 thanh trang tab Quyết toán trỏ đúng bảng CỦA MÌNH', /lenhTUTrang\('lenhTUBodyKT',2\)/.test(el('lenhTUPagerKT').innerHTML) && !/lenhTUTrang\('lenhTUBody',/.test(el('lenhTUPagerKT').innerHTML));
t('   thanh nói "Lệnh 1–5 trên 34" (chữ Lệnh, không phải Đơn)', el('lenhTUPager').innerHTML.indexOf('Lệnh <b>1–5</b> trên <b>34</b>') >= 0, el('lenhTUPager').innerHTML);
t('   34 lệnh → 7 trang, có nút về trang 7', /lenhTUTrang\('lenhTUBody',7\)">7</.test(el('lenhTUPager').innerHTML));
t('   trang 1: nút Trước bị khoá', /disabled[^>]*>‹ Trước/.test(el('lenhTUPager').innerHTML));

/* ── 2. Lật trang ở MỘT bảng, bảng kia đứng yên ──────────────────────────────────────────── */
G.render = 0;
M.trang('lenhTUBodyKT', 7);
teq('   đổi trang thì vẽ lại một lượt', 1, G.render);
teq('🔴 tab Quyết toán trang cuối (7): còn 4 dòng (34 = 6×5 + 4)', 4, demDong('lenhTUBodyKT'));
teq('   trang cuối bắt đầu từ lệnh thứ 31', 'L31', dongDau('lenhTUBodyKT'));
teq('🔴 tab Duyệt KHÔNG bị kéo theo, vẫn 5 dòng trang 1', ['5', 'L1'], [String(demDong('lenhTUBody')), dongDau('lenhTUBody')]);
t('   trang cuối: nút Sau bị khoá', /disabled[^>]*>Sau ›/.test(el('lenhTUPagerKT').innerHTML));
t('   trang đang xem tô đậm', /class="btn b-p" onclick="lenhTUTrang\('lenhTUBodyKT',7\)">7</.test(el('lenhTUPagerKT').innerHTML));
t('   cuộn về đầu card của đúng tab', (el('lenhTUCardKT').cuon || 0) === 1 && !el('lenhTUCard').cuon);
M.trang('lenhTUBody', 3);
teq('   tab Duyệt sang trang 3: bắt đầu từ lệnh 11', ['5', 'L11'], [String(demDong('lenhTUBody')), dongDau('lenhTUBody')]);
teq('   tab Quyết toán vẫn ở trang 7', 'L31', dongDau('lenhTUBodyKT'));

/* ── 3. KẸP: sổ co lại còn 7 lệnh (2 trang) trong khi đang đứng trang 7 ──────────────────── */
G.LENH_TU = lenh(7);
M.render();
teq('🔴 đang trang 7, sổ chỉ còn 2 trang → KẸP về trang cuối, KHÔNG trắng màn', 2, demDong('lenhTUBodyKT'));
teq('   trang cuối ấy là trang 2 (lệnh 6–7)', 'L6', dongDau('lenhTUBodyKT'));
teq('   badge theo sổ mới: 7', '7', String(el('lenhTUSoKT').textContent));
M.trang('lenhTUBody', -4);
teq('   số trang âm kẹp về 1', 'L1', dongDau('lenhTUBody'));

/* ── 4. Dưới một trang: không vẽ thanh; sổ rỗng: hiện câu "chưa có" ─────────────────────── */
G.LENH_TU = lenh(4);
M.render();
teq('   4 lệnh → đủ 4 dòng', 4, demDong('lenhTUBody'));
teq('🔴 dưới 1 trang thì KHÔNG vẽ thanh trang cho rối', '', el('lenhTUPager').innerHTML);
G.LENH_TU = [];
M.render();
teq('   sổ rỗng: không dòng nào', 0, demDong('lenhTUBody'));
teq('   sổ rỗng: hiện lại câu "Chưa có lệnh"', '', el('lenhTUEmpty').style.display);
teq('   sổ rỗng: badge 0', '0', String(el('lenhTUSo').textContent));

/* ── 5. Tờ lệnh vẫn trỏ được vào đơn (không mất gì khi cắt trang) ────────────────────────── */
G.LENH_TU = lenh(34); M.TRANG.lenhTUBody = 2; M.render();
t('   dòng trên trang 2 vẫn có mã đơn bấm mở được', /viewDon\('M6'\)/.test(el('lenhTUBody').innerHTML));

/* ── 6. `_qtPagerHtml` cũ KHÔNG đổi nết khi gọi 4 tham số như bảng Quyết toán ───────────── */
const PG = new Function('QT_MOI_TRANG', ham('_qtPagerHtml') + '; return _qtPagerHtml;')(15);
const p2 = PG('cho', 40, 2, 3);
t('🔴 đối chứng: bảng QT gọi kiểu cũ vẫn ra 15 đơn/trang, nút `qtTrang`, chữ "Đơn"',
  p2.indexOf('Đơn <b>16–30</b> trên <b>40</b>') >= 0 && /qtTrang\('cho',3\)/.test(p2) && !/lenhTUTrang/.test(p2), p2);

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: bảng Lệnh tạm ứng 5 lệnh/trang ở cả hai tab, badge là tổng thật, số trang nhớ riêng và có kẹp.');
