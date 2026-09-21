/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MENU XỔ KHỐI TRÊN TAB DUYỆT TẠM ỨNG / QUYẾT TOÁN — BỐC HÀM THẬT RA CHẠY.
 *
 * Anh Thắng 21/09/2026: *"chỗ duyệt tạm ứng, rê chuột vào nó xổ ra bộ phận được không"*, nối
 * tiếp 20/09: *"khi kế toán rê chuột vào duyệt quyết toán hoặc duyệt tạm ứng, có nó ra 3 đơn vị,
 * để xem từng trang đơn vị, tránh duyệt lộn đơn vị"*.
 *
 * =============================================================================================
 * 🔴 CÁI SAI DỄ MẮC NHẤT Ở ĐÂY LÀ SỐ ĐẾM, VÀ NÓ KHÔNG BAO GIỜ BÁO LỖI
 * =============================================================================================
 * Cả màn duyệt lọc bằng `_hopKhoi()` = "thuộc khối ĐANG CHỌN". Viết menu mà tiện tay gọi lại
 * hàm ấy thì hai khối kia luôn hiện **0** — menu vẫn xổ ra, vẫn ba dòng, vẫn bấm được, chỉ là
 * hai con số nói dối. Kế toán đọc số 0 rồi kết luận bên Máy Tự Động không có đơn nào phải
 * duyệt, và mấy đơn ấy nằm đó cho tới lúc có người đi hỏi.
 *
 * Nên bài này gieo đơn ở CẢ BA khối, đặt khối đang chọn là `kvc`, rồi đòi đúng con số của
 * `mtd` và `vp`. Và soi thẳng mã: `_donTrongMan()` KHÔNG được gọi `_hopKhoi()`.
 *
 * 🔴 CÁI SAI THỨ HAI: RÊ CHUỘT KHÔNG CÓ TRÊN iPHONE. Anh Thắng duyệt đơn bằng Safari iPhone —
 *    đã một lần ba hộp thoại nối nhau chết câm trên iOS (`kiem-doi-coso-don.js`). Menu chỉ gắn
 *    `:hover` thì trên điện thoại cái mũi ▾ là một nút bấm vào không ra gì. Bài này đòi CẢ HAI
 *    đường mở: luật `:hover` phải nằm trong `@media (hover:hover)`, và phải có đường bấm.
 *
 * Chạy: node tools/test/kiem-menu-khoi-tab.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const CSS  = fs.readFileSync('wordpress/vhcp-chi-phi/assets/css/vhcp.css', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}

/* 🔴 GỠ CHÚ THÍCH TRƯỚC KHI DÒ TÊN HÀM. Bài này đỏ oan ngay lượt viết đầu: ngay trong
   `_donTrongMan()` có một dòng chú thích *"cùng luật với `_hopKhoi()`"*, và phép "không được
   gọi `_hopKhoi()`" bắt đúng mấy chữ ấy. Xanh hay đỏ nhờ LỜI VĂN thì không canh gì cả — cùng
   bài học đã ghi ở `kiem-dong-dau-khoi.php`, lần đó hai đột biến sống sót vì lý do ngược lại. */
function bocSach(ten) {
  return bocHam(ten).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' ');
}

const TEN = ['_donTrongMan', '_demMan', '_tmDatCho', 'veMenuKhoi', 'veThanhKhoi', 'diKhoiMan', 'dongMenuKhoi', 'bapMenuKhoi', '_tmTheoCho'];
/* 🔴 BỐC CẢ HAI TRÌNH NGHE RA CHẠY THẬT, ĐỪNG DÒ CHỮ. Bản đầu kiểm bằng một biểu thức
   `addEventListener('click' … dongMenuKhoi()` với cửa sổ 300 ký tự — và đột biến "bấm ra ngoài
   KHÔNG đóng menu" vẫn XANH, vì trong cửa sổ ấy còn trình nghe phím Esc nằm ngay dưới, cũng gọi
   đúng chữ ấy. Y hệt cửa sổ 400 ký tự của `test-flows.php` hôm qua: đếm ký tự thì sớm muộn cũng
   với sang đoạn mã bên cạnh.

   Bản thứ hai đi bốc theo chuỗi `document.addEventListener('click',` — cũng hụt, vì trang có
   sẵn một trình nghe click khác (mở ảnh chứng từ) đứng trước. Nên hai trình nghe ấy nay CÓ TÊN,
   và bốc bằng đúng cơ chế như mọi hàm khác. */
const NGHE = ['_tmBamNgoai', '_tmPhim'];
NGHE.forEach(function (x) { t('bốc được trình nghe `' + x + '`', bocHam(x).length > 30, x); });
t('🔴 và chúng được GẮN thật vào trang', /addEventListener\('click', _tmBamNgoai\)/.test(HTML) && /addEventListener\('keydown', _tmPhim\)/.test(HTML));

const MOI = TEN.concat(NGHE).map(bocHam).join('\n');
TEN.forEach(function (x) { t('bốc được `' + x + '`', bocHam(x).length > 40, x); });

/* ═══ 1. 🔴 SOI MÃ: ĐẾM THEO KHỐI ĐANG XÉT, KHÔNG THEO KHỐI ĐANG CHỌN ═════════════ */
t('đối chứng: gỡ chú thích rồi vẫn còn mã để soi', bocSach('_donTrongMan').indexOf('_khoiCua') >= 0, bocSach('_donTrongMan'));
t('🔴 `_donTrongMan()` KHÔNG gọi `_hopKhoi()` (nó lọc theo khối ĐANG CHỌN)',
  !/_hopKhoi\s*\(/.test(bocSach('_donTrongMan')), bocSach('_donTrongMan'));
t('   và `_demMan()` cũng không',
  !/_hopKhoi\s*\(/.test(bocSach('_demMan')));
t('   `_donTrongMan()` có nhận và dùng tham số khối',
  /function _donTrongMan\(\s*man\s*,\s*ma\s*,\s*d\s*\)/.test(bocSach('_donTrongMan')));

/* ── bệ đỡ tối thiểu ──────────────────────────────────────────────────────────────
   Dựng đúng mấy thứ bốn hàm kia đụng tới, không hơn: thêm bệ đỡ là thêm chỗ cho bài kiểm
   tự bịa ra một thế giới dễ tính hơn đời thật. */
function oGia() { return { innerHTML: '', style: { display: '' }, offsetWidth: 0 }; }
function chay(o) {
  o = o || {};
  const KHO = { 'tmBox-duyet': oGia(), 'tmBox-qt': oGia(), khoiBar: oGia() };
  const vet = [];
  const ctx = {
    BOOT: { dons: o.dons || [] },
    KHOI_DS: [{ ma: 'kvc', ten: 'Khu vui chơi' }, { ma: 'mtd', ten: 'Máy tự động' }, { ma: 'vp', ten: 'Văn phòng' }],
    KHOI_DANG: o.dang || 'kvc',
    TM_KHAU_TU: ['Chờ duyệt tạm ứng', 'Chờ cấp tạm ứng', 'Đã cấp tạm ứng'],
    _AN_MO: !!o.anMo,
    el: function (id) { return KHO[id] || null; },
    esc: function (s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]; }); },
    _khoiCua: function (d) { return String((d && d.khoi) || '').trim().toLowerCase(); },
    _anVaoMo: function (d) { return ctx._AN_MO && d && d.bpMo; },
    _daQT: function (d) { return d && (d.trangThai === 'Đã quyết toán' || d.trangThai === 'Đã xuất MISA'); },
    _khoiDuoc: function () { return ctx.KHOI_DS.filter(function (x) { return (o.duoc || ['kvc', 'mtd', 'vp']).indexOf(x.ma) >= 0; }); },
    doiKhoi: function (m) { vet.push('doiKhoi:' + m); },
    showPage: function (p) { vet.push('showPage:' + p); },
    vet: vet, KHO: KHO,
  };
  /* Vỏ bọc giả: `dongMenuKhoi()` tìm `.tabMenu.mo` rồi gỡ lớp `mo` — dựng đủ để nó làm được
     việc ấy thật, và để bài kiểm đọc ra được là nó ĐÃ làm. */
  const VO = ['duyet', 'qt'].map(function (m) {
    return {
      man: m, mo: false,
      classList: {
        contains: function (c) { return c === 'mo' && VO_TRA(m).mo; },
        add: function (c) { if (c === 'mo') VO_TRA(m).mo = true; },
        remove: function (c) { if (c === 'mo') VO_TRA(m).mo = false; },
      },
      querySelector: function () { return { setAttribute: function () {} }; },
    };
  });
  function VO_TRA(m) { return VO.filter(function (v) { return v.man === m; })[0]; }
  /* Vỏ bọc phải ĐO ĐƯỢC: `_tmDatCho()` lấy `getBoundingClientRect()` của nó để đặt hộp. */
  VO.forEach(function (v) { v.getBoundingClientRect = function () { return (o.oTab || {})[v.man] || { left: 100, bottom: 60, right: 240 }; }; });
  ctx.window = { innerWidth: o.manRong || 1366, addEventListener: function (l, fn) { ctx.NGHE_W = ctx.NGHE_W || {}; ctx.NGHE_W[l] = fn; } };
  ctx.VO = VO;
  ctx.KHO['tm-duyet'] = VO[0];
  ctx.KHO['tm-qt'] = VO[1];
  ctx.document = {
    querySelectorAll: function (sel) { return sel === '.tabMenu.mo' ? VO.filter(function (v) { return v.mo; }) : []; },
  };
  const vm = require('vm');
  vm.createContext(ctx);
  vm.runInContext(MOI, ctx);
  return ctx;
}

/* ═══ 2. ĐẾM ĐÚNG TỪNG KHỐI, DÙ ĐANG ĐỨNG Ở KHỐI KHÁC ════════════════════════════ */
const DONS = [
  /* KVC — 2 đơn khâu tạm ứng, 1 đơn khâu quyết toán */
  { maDon: 'K1', khoi: 'kvc', trangThai: 'Chờ duyệt tạm ứng' },
  { maDon: 'K2', khoi: 'kvc', trangThai: 'Chờ cấp tạm ứng' },
  { maDon: 'K3', khoi: 'kvc', trangThai: 'Chờ quyết toán' },
  /* MTĐ — 3 đơn khâu tạm ứng. 🔴 Đây là con số phải hiện đúng dù đang đứng ở KVC. */
  { maDon: 'M1', khoi: 'mtd', trangThai: 'Chờ duyệt tạm ứng' },
  { maDon: 'M2', khoi: 'mtd', trangThai: 'Đã cấp tạm ứng' },
  { maDon: 'M3', khoi: 'mtd', trangThai: 'Chờ cấp tạm ứng' },
  /* VP — 1 đơn đã quyết toán (chỉ đếm ở màn quyết toán) */
  { maDon: 'V1', khoi: 'vp', trangThai: 'Đã quyết toán' },
  /* Đơn CHƯA đóng dấu khối — hiện ở mọi khối, cùng luật với `_hopKhoi()` */
  { maDon: 'X1', khoi: '', trangThai: 'Chờ duyệt tạm ứng' },
  /* Đơn đã tất toán — không thuộc màn nào trong hai màn này */
  { maDon: 'Z1', khoi: 'kvc', trangThai: 'Đã tất toán' },
];
let c = chay({ dons: DONS, dang: 'kvc' });

teq('duyệt · KVC = 2 đơn của mình + 1 đơn chưa đóng dấu', 3, c._demMan('duyet', 'kvc'));
teq('🔴 duyệt · MTĐ đếm đúng 3+1 DÙ đang đứng ở KVC', 4, c._demMan('duyet', 'mtd'));
teq('🔴 duyệt · VP chỉ còn đơn chưa đóng dấu', 1, c._demMan('duyet', 'vp'));
teq('quyết toán · KVC = "Chờ quyết toán"', 1, c._demMan('qt', 'kvc'));
teq('quyết toán · MTĐ = đơn "Đã cấp tạm ứng" (treo tiền, chưa quyết toán)', 1, c._demMan('qt', 'mtd'));
teq('quyết toán · VP = đơn "Đã quyết toán"', 1, c._demMan('qt', 'vp'));
/* Đối chứng: đứng ở khối khác thì con số KHÔNG được đổi. Đây chính là phép bắt lỗi `_hopKhoi`. */
const c2 = chay({ dons: DONS, dang: 'vp' });
teq('🔴 đổi khối đang chọn KHÔNG làm đổi số của khối khác', c._demMan('duyet', 'mtd'), c2._demMan('duyet', 'mtd'));

/* ═══ 3. ĐÚNG KHÂU — MÀN NÀO ĐẾM ĐƠN CỦA MÀN ẤY ══════════════════════════════════ */
t('🔴 "Đã tất toán" không lọt vào màn duyệt', !c._donTrongMan('duyet', 'kvc', { khoi: 'kvc', trangThai: 'Đã tất toán' }));
t('   cũng không lọt vào màn quyết toán', !c._donTrongMan('qt', 'kvc', { khoi: 'kvc', trangThai: 'Đã tất toán' }));
t('"Đã xuất MISA" vẫn nằm ở màn quyết toán (còn tra lại được)', c._donTrongMan('qt', 'kvc', { khoi: 'kvc', trangThai: 'Đã xuất MISA' }));
t('🔴 "Chờ quyết toán" KHÔNG lọt vào màn duyệt tạm ứng', !c._donTrongMan('duyet', 'kvc', { khoi: 'kvc', trangThai: 'Chờ quyết toán' }));

/* ═══ 4. ĐƠN BỊ "ẨN VÀO MỜ" KHÔNG ĐƯỢC ĐẾM ═══════════════════════════════════════
 * Bảng không bày nó ra thì con số trên menu cũng không được cộng nó — lệch một đơn là kế toán
 * bấm vào rồi đi tìm cái đơn không có ở đó. */
const cAn = chay({ dons: [{ maDon: 'B1', khoi: 'kvc', trangThai: 'Chờ duyệt tạm ứng', bpMo: 1 }], dang: 'kvc', anMo: true });
teq('🔴 đơn bị ẩn-vào-mờ không được cộng vào số trên menu', 0, cAn._demMan('duyet', 'kvc'));
const cHien = chay({ dons: [{ maDon: 'B1', khoi: 'kvc', trangThai: 'Chờ duyệt tạm ứng', bpMo: 1 }], dang: 'kvc', anMo: false });
teq('   (đối chứng: tắt chế độ ẩn thì đếm lại)', 1, cHien._demMan('duyet', 'kvc'));

/* ═══ 5. VẼ MENU — BA MỤC, MỤC KHÔNG THUỘC THÌ HIỆN MÀ KHÔNG BẤM ĐƯỢC ════════════
 * Anh Thắng 20/09/2026: *"không thuộc thì chỉ hiện chứ không bấm được"*. */
const cQ = chay({ dons: DONS, dang: 'kvc', duoc: ['kvc'] });
cQ.veMenuKhoi('duyet');
const h = cQ.KHO['tmBox-duyet'].innerHTML;
teq('menu có đúng 3 mục', 3, (h.match(/class="mk/g) || []).length);
teq('🔴 hai mục không thuộc bị khoá', 2, (h.match(/disabled/g) || []).length);
teq('   và mang ổ khoá 🔒', 2, (h.match(/🔒/g) || []).length);
teq('🔴 mục bị khoá KHÔNG có đường bấm', 1, (h.match(/onclick="diKhoiMan/g) || []).length);
t('   mục đang đứng được đánh dấu', /class="mk dang"/.test(h), h.slice(0, 200));
t('🔴 mục bị khoá VẪN hiện số đơn của khối ấy (4) — không giấu đi', /🔒[\s\S]*?>4</.test(h), h);
/* Được cả ba thì cả ba bấm được. */
const cCa = chay({ dons: DONS, dang: 'kvc' });
cCa.veMenuKhoi('duyet');
teq('nhà mẹ: cả 3 mục đều bấm được', 3, (cCa.KHO['tmBox-duyet'].innerHTML.match(/onclick="diKhoiMan/g) || []).length);
teq('   và không mục nào bị khoá', 0, (cCa.KHO['tmBox-duyet'].innerHTML.match(/disabled/g) || []).length);

/* ═══ 6. 🔴 ĐỔI KHỐI TRƯỚC, MỞ MÀN SAU ═══════════════════════════════════════════
 * Ngược thứ tự thì màn vẽ xong bằng khối CŨ rồi mới đổi khối — kế toán nhìn một bảng của đơn
 * vị khác đúng cái tab vừa bấm để tránh nhìn nhầm đơn vị. */
const cD = chay({ dons: DONS, dang: 'kvc' });
cD.diKhoiMan('duyet', 'mtd');
teq('🔴 bấm một mục: đổi khối TRƯỚC rồi mới mở màn', ['doiKhoi:mtd', 'showPage:duyet'], cD.vet);
const cD2 = chay({ dons: DONS, dang: 'kvc' });
cD2.diKhoiMan('qt', 'vp');
teq('   và mở đúng màn của mục vừa bấm', ['doiKhoi:vp', 'showPage:qt'], cD2.vet);

/* ═══ 7. 🔴 MỞ ĐƯỢC BẰNG CẢ RÊ CHUỘT LẪN BẤM (iPHONE KHÔNG CÓ RÊ CHUỘT) ══════════ */
t('🔴 luật `:hover` nằm TRONG `@media (hover:hover)`',
  /@media\s*\(hover:hover\)\s*\{[^}]*\.tabMenu:hover\s+\.tabMenuBox\s*\{\s*display:\s*block/.test(CSS.replace(/\s+/g, ' ').replace(/ \{/g, '{').replace(/\{ /g, '{')) ||
  /@media \(hover:hover\)\{ \.tabMenu:hover \.tabMenuBox\{ display:block \}/.test(CSS.replace(/\s+/g, ' ')), 'không thấy');
t('🔴 có đường mở KHÔNG cần rê chuột (class `mo`)', /\.tabMenu\.mo\s+\.tabMenuBox\s*\{\s*display:\s*block/.test(CSS));
t('   và mũi ▾ có đường bấm', /class="tabMenuNut"[\s\S]{0,260}?onclick="bapMenuKhoi\('duyet'/.test(HTML));
t('   cho cả tab quyết toán', /class="tabMenuNut"[\s\S]{0,260}?onclick="bapMenuKhoi\('qt'/.test(HTML));
/* ─── chạy thật hai trình nghe ───────────────────────────────────────────────── */
function moSan() {
  const cc = chay({ dons: DONS, dang: 'kvc' });
  cc.bapMenuKhoi('duyet', { preventDefault: function () {}, stopPropagation: function () {} });
  return cc;
}
let cm = moSan();
teq('🔴 bấm mũi ▾ thì menu MỞ ra', true, cm.VO[0].mo);
cm.bapMenuKhoi('duyet', { preventDefault: function () {}, stopPropagation: function () {} });
teq('   bấm lần nữa thì đóng lại', false, cm.VO[0].mo);
/* Mở menu này rồi bấm mũi kia: không được mở hai cái cùng lúc, chúng chồng lên nhau. */
cm = moSan();
cm.bapMenuKhoi('qt', { preventDefault: function () {}, stopPropagation: function () {} });
teq('🔴 mở menu kia thì menu này phải đóng', [false, true], [cm.VO[0].mo, cm.VO[1].mo]);

cm = moSan();
cm._tmBamNgoai({ target: { closest: function () { return null; } } });
teq('🔴 bấm RA NGOÀI thì menu đóng', false, cm.VO[0].mo);

cm = moSan();
cm._tmBamNgoai({ target: { closest: function (sel) { return sel === '.tabMenu' ? cm.VO[0] : null; } } });
teq('   bấm BÊN TRONG menu thì KHÔNG đóng (còn bấm mục được)', true, cm.VO[0].mo);

cm = moSan();
cm._tmPhim({ key: 'Escape' });
teq('🔴 phím Esc đóng menu', false, cm.VO[0].mo);
cm = moSan();
cm._tmPhim({ key: 'a' });
teq('   phím khác thì không đụng tới', true, cm.VO[0].mo);

cm = moSan();
cm.diKhoiMan('duyet', 'mtd');
teq('🔴 bấm một mục thì menu tự đóng', false, cm.VO[0].mo);

/* ═══ 7b. 🔴 HỘP PHẢI THOÁT KHỎI HÀNG TAB BỊ CẮT, VÀ PHẢI NẰM TRONG MÀN ══════════
 * Lỗi thật, chỉ lòi ra khi mở Chromium ở bề ngang 390px mà nhìn (21/09/2026): trên màn hẹp
 * `#tabbar` mang `overflow-x:auto` để cuộn ngang, mà `overflow-x:auto` + `overflow-y:visible`
 * thì trình duyệt tính ra `overflow-y:auto` — nó CẮT theo chiều dọc. Hộp `absolute` treo trong
 * đó bị xén còn một vạch trắng: menu xổ ra mà không đọc được chữ nào. Kho mã đã ghi sẵn đúng
 * bài học này hai lần cho lớp phủ trong bảng, mà em vẫn dẫm lại.
 *
 * ⚠️ Bài kiểm CHỮ không thấy được cái này — nó chỉ thấy được sau khi đã biết. Nên chốt ở đây
 *    canh cái đã biết: hộp phải `fixed`, và toạ độ phải do `_tmDatCho()` đặt, kẹp trong màn. */
t('🔴 hộp menu là `position:fixed`, không phải `absolute`',
  /\.tabMenuBox\{[^}]*position:\s*fixed/.test(CSS.replace(/\s*\n\s*/g, '')), 'không thấy');
t('   và KHÔNG còn `position:absolute`', !/\.tabMenuBox\{[^}]*position:\s*absolute/.test(CSS.replace(/\s*\n\s*/g, '')));
t('🔴 `veMenuKhoi()` đặt chỗ cho hộp sau khi vẽ', /_tmDatCho\(man\)/.test(bocSach('veMenuKhoi')));
t('   và `bapMenuKhoi()` đặt LẠI sau khi hiện (lúc ẩn đo ra 0)', /_tmDatCho\(man\)/.test(bocSach('bapMenuKhoi')));

/* Tab nằm giữa màn rộng: hộp bám mép trái của tab. */
let cP = chay({ dons: DONS, dang: 'kvc', manRong: 1366, oTab: { duyet: { left: 600, bottom: 70, right: 760 } } });
cP.veMenuKhoi('duyet');
teq('hộp bám mép trái của tab', '600px', cP.KHO['tmBox-duyet'].style.left);
teq('   và nằm ngay dưới tab (chừa 6px)', '76px', cP.KHO['tmBox-duyet'].style.top);

/* 🔴 Tab sát mép PHẢI trên màn hẹp: phải kéo hộp vào, không thì mấy con số bị cắt. */
cP = chay({ dons: DONS, dang: 'kvc', manRong: 390, oTab: { duyet: { left: 330, bottom: 70, right: 386 } } });
cP.veMenuKhoi('duyet');
const traiP = parseInt(cP.KHO['tmBox-duyet'].style.left, 10);
t('🔴 tab sát mép phải: hộp bị kéo vào trong màn', traiP < 330, cP.KHO['tmBox-duyet'].style.left);
t('   và hộp (rộng 236) vẫn lọt hẳn trong màn 390', traiP + 236 <= 390, traiP + 236);

/* 🔴 Màn hẹp hơn cả cái hộp: thà chạm mép trái còn hơn trôi ra ngoài bên trái. */
cP = chay({ dons: DONS, dang: 'kvc', manRong: 200, oTab: { duyet: { left: 150, bottom: 70, right: 190 } } });
cP.veMenuKhoi('duyet');
t('🔴 màn hẹp hơn hộp: hộp KHÔNG trôi ra ngoài mép trái', parseInt(cP.KHO['tmBox-duyet'].style.left, 10) >= 8, cP.KHO['tmBox-duyet'].style.left);

/* Hộp `fixed` không tự đi theo trang — phải tính lại chỗ lúc cuộn / xoay máy. */
t('🔴 có bắt lượt cuộn để đặt lại chỗ', /addEventListener\('scroll', _tmTheoCho, true\)/.test(HTML), 'không thấy');
t('   và cờ `true` để bắt cả lượt cuộn NGANG của hàng tab', /_tmTheoCho, true\)/.test(HTML));
t('   lượt đổi cỡ màn cũng vậy', /addEventListener\('resize', _tmTheoCho\)/.test(HTML));
cP = chay({ dons: DONS, dang: 'kvc', manRong: 1366, oTab: { duyet: { left: 600, bottom: 70, right: 760 }, qt: { left: 800, bottom: 70, right: 900 } } });
cP._tmTheoCho();
teq('🔴 đặt lại chỗ cho CẢ HAI menu, không sót cái nào',
  ['600px', '800px'], [cP.KHO['tmBox-duyet'].style.left, cP.KHO['tmBox-qt'].style.left]);

/* ═══ 7c. 🔴 SỐ TRÊN NÚT KHỐI LÀ THỨ DUY NHẤT CÒN NÓI "KHỐI NÀY RỖNG" ════════════
 * Anh Thắng 21/09/2026: *"chọn phía trên rồi, phía dưới bỏ đi cho gọn"* — dải nhắc vàng
 * "Khối Văn phòng chưa có đơn nào trong kho này…" đã bỏ.
 *
 * Dải ấy sinh ra để trả lời *"mở khối ra thấy trắng, hỏng à?"*. Bỏ nó đi thì câu trả lời chỉ
 * còn nằm ở CON SỐ trên từng nút khối. Nên con số ấy từ nay là thứ chịu lực: mất nó là màn
 * hình rỗng không còn lời giải thích nào, và người ta sẽ báo hỏng. Phép dưới canh đúng chỗ đó. */
t('🔴 dải nhắc vàng đã gỡ khỏi trang', !/id="khoiNhac"/.test(HTML));
t('   và không còn mã nào đụng tới nó', !/khoiNhac/.test(HTML.replace(/<!--[\s\S]*?-->/g, '')));
const cB = chay({ dons: DONS, dang: 'kvc', duoc: ['kvc', 'mtd'] });
cB.veThanhKhoi();
const hb = cB.KHO.khoiBar.innerHTML;
teq('thanh khối vẽ đủ 3 nút', 3, (hb.match(/<button/g) || []).length);
/* DONS: 4 đơn kvc (K1·K2·K3·Z1), 3 đơn mtd, 1 đơn vp, 1 đơn chưa đóng dấu.
   Số trên nút là TỔNG đơn của khối ấy — không lọc theo màn nào. */
t('🔴 nút "Khu vui chơi" mang số đơn của nó', /Khu vui chơi[\s\S]{0,80}?>4</.test(hb), hb);
t('🔴 nút "Máy tự động" mang số của nó', /Máy tự động[\s\S]{0,80}?>3</.test(hb), hb);
/* 🔴 Nút BỊ KHOÁ cũng phải mang số. Lượt viết đầu nó không có, và thanh khối nói khác menu ▾
   (menu thì có) — hai con số khác nhau cho cùng một khối thì người ta tin cái nào? Mà từ lúc
   bỏ dải nhắc vàng, đây là chỗ duy nhất nói khối ấy rỗng hay không. */
t('🔴 nút khối BỊ KHOÁ vẫn mang số đơn của nó', /🔒 Văn phòng[\s\S]{0,80}?>1</.test(hb), hb);
t('   và thanh khối khớp với menu ▾ ở cùng con số', /🔒 Văn phòng[\s\S]{0,80}?>1</.test(hb) && cB._demMan('qt', 'vp') === 1);

/* ═══ 8. 🔴 ẨN TAB THÌ ẨN CẢ VỎ BỌC, KHÔNG CHỈ CÁI NÚT ═══════════════════════════
 * Kế toán NCC không có tab Duyệt tạm ứng. Ẩn mỗi nút thì mũi ▾ còn trơ lại giữa hàng tab: một
 * nút không tên, bấm vào ra menu của đúng cái tab vừa bị ẩn. */
t('🔴 `applyPerms()` ẩn cả vỏ bọc `tm-…`', /el\('tm-'\+x\)/.test(HTML), 'không thấy');
['duyet', 'qt'].forEach(function (x) {
  t('   vỏ bọc `tm-' + x + '` có thật trong trang', new RegExp('id="tm-' + x + '"').test(HTML));
  t('   và hộp `tmBox-' + x + '` có thật', new RegExp('id="tmBox-' + x + '"').test(HTML));
});
/* Nút tab cũ phải GIỮ NGUYÊN hành vi — menu là thứ thêm vào, không thay thế. */
t('🔴 nút tab vẫn vào thẳng màn như cũ', /id="tab-duyet" onclick="showPage\('duyet'\)"/.test(HTML));
t('   (quyết toán cũng vậy)', /id="tab-qt" onclick="showPage\('qt'\)"/.test(HTML));

/* ═══ 9. MENU VẼ LẠI MỖI LƯỢT VẼ THANH KHỐI ══════════════════════════════════════
 * Hai chỗ cùng nói một chuyện; lệch nhau thì người ta tin cái nào? */
t('🔴 `veThanhKhoi()` vẽ lại cả hai menu', /veMenuKhoi\('duyet'\);\s*veMenuKhoi\('qt'\)/.test(bocHam('veThanhKhoi')), 'không thấy');

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: menu xổ đúng ba khối, số đếm không lệ thuộc khối đang chọn, và mở được cả trên iPhone.');
