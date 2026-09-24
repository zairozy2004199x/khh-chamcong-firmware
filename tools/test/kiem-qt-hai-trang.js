/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HAI TRANG QUYẾT TOÁN · THANH KHỐI KHÔNG BÀY KHỐI RỖNG · KẾ TOÁN TỰ THÊM MÃ TK NỢ.
 * Anh Thắng 24/09/2026: *"bỏ nè"* (ảnh KHỐI: Khu vui chơi 125 · Văn phòng 0) · *"Tách ra là Chờ Quyết
 * Toán · Đã Quyết Toán"* · *"Ra 2 trang riêng cho gọn"* · *"Tạo mã này hay, để kế toán tự gán… nếu
 * thiếu có thể thêm mã để kế toán tự tạo số mới đúng"*. Chạy: node tools/test/kiem-qt-hai-trang.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const dong = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); };
['_khoiBay', 'veMenuKhoi', '_tabDuoc', 'renderQTList', '_oTkNoDong', 'saveLineTkNo', 'showPage'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 40, n));

/* ── 1. Hàng tab: hai tab Quyết toán, không còn menu ▾ khối ─────────────────────────────── */
t('🔴 tab "Chờ quyết toán" → showPage(\'qt\')', /id="tab-qt" onclick="showPage\('qt'\)">🧾 Chờ quyết toán</.test(HTML));
t('🔴 tab "Đã quyết toán" → showPage(\'qtxong\')', /id="tab-qtxong" onclick="showPage\('qtxong'\)"[^>]*>✅ Đã quyết toán</.test(HTML));
t('   không còn vỏ ▾ khối ở Quyết toán', !/id="tm-qt"/.test(HTML) && !/id="tmBox-qt"/.test(HTML) && !/bapMenuKhoi\('qt'/.test(HTML));
const SP = ham('showPage');
t('🔴 showPage đặt QT_MAN theo tab', /if\(p==='qt'\|\|p==='qtxong'\)\{ QT_MAN=\(p==='qtxong'\)\?'xong':'cho';/.test(SP));
t('   và hai tab dùng chung #page-qt', /\(p==='qtxong'\?'qt':p\)/.test(SP));
t('   cả hai tab đều nạp Quyết toán', /if\(p==='qt'\|\|p==='qtxong'\) loadQT\(\);/.test(SP));
t('   thanh khối hiện ở cả trang Đã quyết toán', /\['don','duan','duyet','qt','qtxong'\]\.indexOf\(p\)/.test(SP));
t('   `tab-qtxong` có trong vòng tô sáng tab', /'duyet','qt','qtxong','tattoan'/.test(SP));
/* Dải luồng: bước đã chốt dẫn sang trang Đã quyết toán */
const LD = HTML.slice(HTML.indexOf('  var LUONG_DIEN={'), HTML.indexOf('};', HTML.indexOf('  var LUONG_DIEN={')));
t('🔴 dải luồng: "Chờ quyết toán" → tab qt', /'Chờ quyết toán':\s*\{tab:'qt'/.test(LD));
t('🔴 dải luồng: "Đã quyết toán" và "Đã thanh toán" → tab qtxong', /'Đã quyết toán':\s*\{tab:'qtxong'/.test(LD) && /'Đã thanh toán':\s*\{tab:'qtxong'/.test(LD));
/* Cửa quyền: trang Đã quyết toán dùng quyền của tab Quyết toán */
{
  const td = (q, p) => new Function('QUYEN_TAB', ham('_tabDuoc') + '\nreturn _tabDuoc(' + JSON.stringify(p) + ');')(q);
  teq('🔴 _tabDuoc(qtxong) = quyền của qt (có)', true, td({ qt: 1 }, 'qtxong'));
  teq('   _tabDuoc(qtxong) = quyền của qt (không)', false, td({ qt: 0, qtxong: 1 }, 'qtxong'));
  t('   vis.qtxong chép từ vis.qt', /vis\.qtxong=vis\.qt;/.test(HTML) && /show\('qtxong',canDo\('xacNhanQT'\)/.test(HTML));
}

/* ── 2. renderQTList: mỗi trang bày đúng thẻ của nó ─────────────────────────────────────── */
function chayQT(man, xem) {
  const O = {};
  const o = (id) => (O[id] = O[id] || { style: {}, textContent: '', innerHTML: '', className: '', value: '' });
  const the = ['qtCardCho', 'qtLenhCard', 'qtCardChuaNop', 'qtCardTT', 'qtCardXong', 'qtCardTuan', 'qtTieuDe', 'qtXemBang', 'qtXemTuan', 'qtChkHead', 'qtChkAll', 'qtBody', 'qtListEmpty'];
  const el = (id) => (the.indexOf(id) >= 0 ? o(id) : null);
  const kh = () => {};
  new Function('el', 'BOOT', 'QT_XEM', 'QT_MAN', 'QT_THE', 'canDo', 'renderBpBanner', '_napLocDon', '_napKyRieng', '_qtVeBang', '_qtVeBangTT', '_qtVeChuaNop',
    '_qtEmptyXongText', 'qtUpdateBar', '_laChim', '_kyVal', '_hopKhoi', '_daQT', '_anVaoMo', '_qtTrongMan', '_qtLoc', '_qtLocXong', '_qtChoTT', '_qtSums', '_qtSumHtml',
    '_qtReconHtml', '_reconTablesHtml', '_qtRowHtml', 'qtGroupToggle', 'viewDon', 'money', 'esc',
    dong('QT_THE') + '\n' + ham('renderQTList') + '\nrenderQTList();')(
    el, { dons: [] }, xem || 'bang', man, undefined, () => false, kh, kh, kh, kh, kh, kh, () => '', kh, () => false, () => '', () => true, () => false, () => false,
    () => false, () => false, () => false, () => false, () => ({}), () => '', () => '', () => '', () => '', kh, kh, (n) => String(n), (s) => String(s));
  const hien = the.filter((id) => O[id] && O[id].style.display === '').sort();
  return { hien, tieuDe: O.qtTieuDe.textContent };
}
{
  const c = chayQT('cho');
  teq('🔴 trang Chờ: bày Chờ · Lệnh dự án · Đã cấp chưa nộp; giấu Đã/Chờ TT/tuần',
    ['qtCardCho', 'qtCardChuaNop', 'qtLenhCard'], c.hien.filter((x) => /Card|Lenh/.test(x)));
  teq('   tiêu đề trang Chờ', '🧾 Chờ quyết toán', c.tieuDe);
  const x = chayQT('xong');
  teq('🔴 trang Đã: bày Chờ thanh toán · Đã quyết toán; giấu ba thẻ việc', ['qtCardTT', 'qtCardXong'], x.hien.filter((x) => /Card|Lenh/.test(x)));
  teq('   tiêu đề trang Đã', '✅ Đã quyết toán', x.tieuDe);
  const tu = chayQT('xong', 'tuan');
  teq('   xem theo tuần: chỉ thẻ tuần, ở trang nào cũng vậy', ['qtCardTuan'], tu.hien.filter((x) => /Card|Lenh/.test(x)));
}

/* ── 3. _khoiBay: khối mở mà chưa có đơn thì không bày ───────────────────────────────────── */
function bay(B) {
  const NEN = ['  var KHOI_DS=[{ma:"kvc",ten:"Khu vui chơi"},{ma:"mtd",ten:"Máy tự động"},{ma:"vp",ten:"Văn phòng"}];', "  var KHOI_MO=['kvc','vp'];",
    ham('_khoiMo'), ham('_khoiLuuTru'), ham('_khoiBay')].join('\n');
  return new Function('window', 'BOOT', NEN + '\nreturn _khoiBay().map(function(x){ return x.ma; });')({ BOOT: B }, B);
}
teq('🔴 bản gốc: 125 đơn kvc, 0 đơn vp → chỉ bày kvc (nút "Văn phòng 0" biến mất)', ['kvc'], bay({ khoiBan: 'kvc', khoiCoDon: ['kvc'], khoiXem: null }));
teq('🔴 vp có đơn thật → nút vp tự mọc lại', ['kvc', 'vp'], bay({ khoiBan: 'kvc', khoiCoDon: ['kvc', 'vp'], khoiXem: null }));
teq('   NV Văn phòng chưa có đơn vẫn thấy nút của mình', ['kvc', 'vp'], bay({ khoiBan: 'kvc', khoiCoDon: ['kvc'], khoiXem: ['vp'] }));
teq('   khối của chính bản luôn bày, kể cả kho trống', ['kvc'], bay({ khoiBan: 'kvc', khoiCoDon: [], khoiXem: null }));
teq('   khối lưu trữ còn sổ (mtd) vẫn bày để đọc/xuất nốt', ['kvc', 'mtd'], bay({ khoiBan: 'kvc', khoiCoDon: ['kvc', 'mtd'], khoiXem: null }));
teq('⚠️ gói khởi động cũ (không khoiBan) → bày như trước, không lọc', ['kvc', 'vp'], bay({ khoiCoDon: [] }));
/* veMenuKhoi giấu mũi ▾ khi còn một khối */
{
  function menu(bayDs) {
    const nut = { style: {} }, box = { innerHTML: '' };
    new Function('el', '_khoiBay', '_khoiDuoc', 'KHOI_DANG', 'esc', '_demMan', '_tmDatCho',
      ham('veMenuKhoi') + "\nveMenuKhoi('duyet');")(
      (id) => (id === 'tmBox-duyet' ? box : (id === 'tm-duyet' ? { querySelector: () => nut } : null)),
      () => bayDs, () => bayDs, 'kvc', (s) => String(s), () => 0, () => {});
    return nut.style.display;
  }
  teq('🔴 còn một khối → mũi ▾ trên tab Duyệt bị giấu', 'none', menu([{ ma: 'kvc', ten: 'KVC' }]));
  teq('   hai khối → mũi ▾ hiện', '', menu([{ ma: 'kvc', ten: 'KVC' }, { ma: 'vp', ten: 'VP' }]));
}

/* ── 4. Ô TK Nợ của dòng: "＋ Mã khác" + mã đã dùng trong đơn ───────────────────────────── */
{
  const oTk = (l, lines) => new Function('CUR', 'BOOT', 'esc', '_tenTk', 'TK_THEM', ham('_tkNoCuaLoai') + ham('_tkNoTatCa') + ham('_tkOpt') + ham('_oTkNoDong') + '\nreturn _oTkNoDong(l);'.replace('l)', JSON.stringify(l) + ')'))(
    { lines: lines || [] }, { tkNoMx: { 'chi phí chung': { 'EVENT': ['64196'] }, 'chi phí cơ sở': { 'FARM': ['64166'] } } }, (s) => String(s), () => '', '__them__');
  const h = oTk({ id: 'L1', nhom: 'Chi phí chung', tkNo: '' });
  t('🔴 có mục "＋ Mã khác — kế toán tự thêm…" mang giá trị TK_THEM', /<option value="__them__">＋ Mã khác — kế toán tự thêm…<\/option><\/select>$/.test(h), h);
  t('   mã của loại lên trước, mã khác đã khai sau', /Mã của loại này"><option value="64196"/.test(h) && /Mã khác đã khai"><option value="64166"/.test(h), h);
  const h2 = oTk({ id: 'L1', nhom: 'Chi phí chung', tkNo: '' }, [{ id: 'L2', tkNo: '64211' }]);
  t('🔴 mã kế toán vừa tự thêm ở dòng khác của đơn hiện trong "Mã khác đã khai"', /Mã khác đã khai">[^]*?<option value="64211"/.test(h2), h2);
  t('   không nhân đôi mã đã có trong ma trận', (h2.match(/value="64166"/g) || []).length === 1);
}
/* saveLineTkNo: hộp hỏi → soi số → gửi kèm cờ them */
{
  function luu(val, goi) {
    const goiDi = [], toasts = []; let lai = 0;
    const run = { withSuccessHandler() { return run; }, withFailureHandler() { return run; }, setLineTkNo(id, v, them) { goiDi.push([id, v, them]); } };
    new Function('CUR', 'openDon', 'loading', 'google', 'toast', '_log', 'prompt', 'TK_THEM', ham('saveLineTkNo') + "\nsaveLineTkNo('L1', " + JSON.stringify(val) + ');')(
      { don: { maDon: 'D1' } }, () => { lai++; }, () => {}, { script: { run } }, (k, m) => toasts.push(k + ':' + m), () => {}, () => goi, '__them__');
    return { goiDi, toasts, lai };
  }
  teq('🔴 chọn ＋ Mã khác, gõ 64211 → gửi setLineTkNo(id, "64211", them=1)', [['L1', '64211', 1]], luu('__them__', ' 64211 ').goiDi);
  const xau = luu('__them__', '64a1');
  t('🔴 gõ không phải số → báo, KHÔNG gửi, vẽ lại dòng', xau.goiDi.length === 0 && /3–10 chữ số/.test(xau.toasts[0]) && xau.lai === 1, xau);
  const huy = luu('__them__', null);
  t('   bấm Huỷ → không gửi gì', huy.goiDi.length === 0 && huy.toasts.length === 0, huy);
  teq('   chọn mã có sẵn → gửi như cũ, them=0', [['L1', '64196', 0]], luu('64196', 'x').goiDi);
  teq('   chọn "— tự động —" → gửi rỗng, them=0', [['L1', '', 0]], luu('', 'x').goiDi);
}
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: hai tab Quyết toán mỗi trang đúng thẻ; khối rỗng không bày; kế toán tự thêm mã TK qua hộp hỏi có soi số.');
