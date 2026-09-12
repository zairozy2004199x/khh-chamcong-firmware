/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN CẤU HÌNH XẾP THÀNH NHÓM — và KHÔNG ĐÁNH RƠI KHỐI NÀO.
 *
 * Anh Thắng 12/09/2026: *"nhớ đừng chạm đến chi phí nhé, giờ sắp xếp và phân loại trong cấu
 * hình trước để anh xem"*.
 *
 * =============================================================================================
 * 🔴 CHỖ NGUY NHẤT LÀ ĐÁNH RƠI KHỐI. `xepCauHinh()` dời các thẻ vào một khung mới theo bảng
 *    `CH_NHOM`. Khối nào không khai trong bảng ấy mà bị bỏ qua thì nó nằm lại chỗ cũ hoặc biến
 *    mất khỏi màn — dữ liệu còn nguyên trên máy chủ, chỉ là không ai mở ra được nữa. Đó đúng
 *    kiểu hỏng đã xảy ra ngày 25/08/2026 với bảng người dùng. Phần 2 canh thẳng vào đó.
 *
 * 🔴 MỌI ID KHAI TRONG `CH_NHOM` PHẢI CÓ THẬT TRONG app.html. Gõ sai một id là khối ấy im lặng
 *    rơi vào nhóm "Khác" — nhóm vẫn hiện nên nhìn qua không thấy gì lạ, mà khối thì nằm sai
 *    chỗ mãi mãi. Phần 1 đối chiếu hai bên.
 *
 * ⚠️ CHẠY THẬT `xepCauHinh()` bốc từ app.html, trên một DOM giả đủ dùng.
 *
 * Chạy: node tools/test/kiem-xep-nhom-cau-hinh.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC  = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

function boc(ten) {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) { console.log('\n✗ Không bốc được — dừng.'); process.exit(1); }
  const j = HTML.indexOf('\n  }', i);
  return HTML.slice(i, j + 4);
}
const iN = HTML.indexOf('var CH_NHOM=[');
t('bốc được bảng CH_NHOM', iN >= 0);
if (iN < 0) { console.log('\n✗ Không bốc được — dừng.'); process.exit(1); }
const SRC_NHOM = HTML.slice(iN, HTML.indexOf('\n  ];', iN) + 5);

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 MỌI ID KHAI TRONG BẢNG PHẢI CÓ THẬT TRONG TRANG
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
const CH_NHOM = new Function(SRC_NHOM + '; return CH_NHOM;')();
t('bảng có ít nhất 4 nhóm', CH_NHOM.length >= 4, CH_NHOM.length);
const thieu = [];
CH_NHOM.forEach(g => g.id.forEach(i => {
  if (HTML.indexOf('id="' + i + '"') < 0) thieu.push(i);
}));
teq('🔴 mọi id trong CH_NHOM đều có thật trong app.html', [], thieu);
/* Chiều ngược: khối nào trong tab Cấu hình mà chưa khai nhóm thì phải biết — nó sẽ rơi vào
   "Khác". Không bắt lỗi, chỉ kể tên, vì thêm khối mới rồi khai sau là chuyện thường. */
const iTab = HTML.indexOf('<div id="page-cauhinh"');
const iHet = HTML.indexOf('<div id="page-log"', iTab);
const trongTab = [...HTML.slice(iTab, iHet).matchAll(/<div class="card" id="([^"]+)"/g)].map(m => m[1]);
const daKhai = {}; CH_NHOM.forEach(g => g.id.forEach(i => { daKhai[i] = 1; }));
teq('🔴 mọi khối trong tab Cấu hình đều đã xếp nhóm', [], trongTab.filter(i => !daKhai[i]));
t('   và tab có đủ khối (≥10)', trongTab.length >= 10, trongTab);
/* Không khai trùng: một khối nằm hai nhóm thì `appendChild` dời nó đi, nhóm trước mất khối. */
const dem = {}; const trung = [];
CH_NHOM.forEach(g => g.id.forEach(i => { dem[i] = (dem[i] || 0) + 1; if (dem[i] === 2) trung.push(i); }));
teq('🔴 không khối nào khai ở hai nhóm', [], trung);
CH_NHOM.forEach(g => {
  t('nhóm "' + g.ten + '" có lời mô tả', String(g.mo || '').length > 10, g.mo);
});

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 2. CHẠY THẬT — DOM giả
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 BỆ ĐỠ PHẢI TRUNG THỰC Ở ĐÚNG HAI CHỖ, vì cả bài xoay quanh chúng:
   · `el()` = `getElementById`, tức CHỈ tìm thấy phần tử còn NẰM TRONG CÂY. Bệ đỡ tra một bảng
     phẳng thì phần tử đã bị gỡ vẫn "tìm thấy", và lỗi xoá trắng màn Cấu hình ở lượt xếp thứ
     hai sẽ không bao giờ lộ ra — đúng cái bài này sinh ra để bắt.
   · `classList.contains('card')`, vì mã thật phân biệt dải tiêu đề với khối bằng chính nó. */
function O(id, laCard) {
  return { id, card: !!laCard, style: { cssText: '', display: '' }, innerHTML: '',
    children: [], parentNode: null,
    classList: { contains: c => c === 'card' && !!laCard },
    appendChild(c) { if (c.parentNode) { const k = c.parentNode.children.indexOf(c); if (k >= 0) c.parentNode.children.splice(k, 1); }
                     c.parentNode = this; this.children.push(c); return c; },
    removeChild(c) { const k = this.children.indexOf(c); if (k >= 0) this.children.splice(k, 1); c.parentNode = null; return c; },
    querySelectorAll(sel) { return /> \.card/.test(sel) ? this.children.filter(c => c.card) : []; } };
}
function trongCay(goc, id) {          // getElementById: chỉ thấy thứ còn nối vào gốc
  if (goc.id === id) return goc;
  for (const c of goc.children) { const r = trongCay(c, id); if (r) return r; }
  return null;
}
function dungBe(ids, an) {
  const box = O('page-cauhinh');
  ids.forEach(id => { const c = O(id, true); if ((an || []).indexOf(id) >= 0) c.style.display = 'none'; box.appendChild(c); });
  const moi = {
    el: id => trongCay(box, id),
    esc: x => String(x == null ? '' : x),
    document: {
      createElement: () => O(''),
      /* `document.querySelectorAll('#page-cauhinh > .card')` và '#chNhomWrap > .card' */
      querySelectorAll: sel => {
        const m = /#([\w-]+) > \.card/.exec(sel);
        const cha = m ? trongCay(box, m[1]) : null;
        return cha ? cha.children.filter(c => c.card) : [];
      } },
    box,
  };
  moi.window = moi;
  const chay = () => new Function('moi', `with(moi){ ${SRC_NHOM}\n${boc('xepCauHinh')}\n xepCauHinh(); }`)(moi);
  chay();
  return { box, chay };
}
/* Thứ tự các khối sau khi xếp — chỉ lấy thẻ có id (dải tiêu đề không có). */
function thuTu(box) {
  const w = box.children[box.children.length - 1];   // chNhomWrap
  return w.children.filter(c => c.card).map(c => c.id);
}
const IDS = trongTab.slice();
let r = dungBe(IDS);
teq('🔴 KHÔNG đánh rơi khối nào', IDS.length, thuTu(r.box).length);
teq('   và đúng tập khối ấy, không thừa không thiếu',
  IDS.slice().sort(), thuTu(r.box).slice().sort());
/* Thứ tự phải theo bảng nhóm, không theo thứ tự viết trong HTML. */
const mongDoi = [];
CH_NHOM.forEach(g => g.id.forEach(i => { if (IDS.indexOf(i) >= 0) mongDoi.push(i); }));
teq('🔴 xếp đúng theo thứ tự bảng nhóm', mongDoi, thuTu(r.box));
t('   khung chNhomWrap được dựng', r.box.children.some(c => c.id === 'chNhomWrap'),
  r.box.children.map(c => c.id));

/* 🔴 KHỐI LẠ (chưa khai nhóm) VẪN PHẢI CÒN — rơi vào "Khác" ở cuối, không được biến mất. */
r = dungBe(IDS.concat(['khoiMoiChuaKhai']));
const sau = thuTu(r.box);
t('🔴 khối chưa khai nhóm KHÔNG biến mất', sau.indexOf('khoiMoiChuaKhai') >= 0, sau);
teq('   và nằm ở CUỐI', 'khoiMoiChuaKhai', sau[sau.length - 1]);
teq('   không khối nào khác bị rơi', IDS.length + 1, sau.length);
/* 🔴 VÀ VẪN CÒN Ở LƯỢT XẾP THỨ HAI. Lượt đầu đã dời khối lạ VÀO khung, nên lượt sau phải quét
   khối lạ ở cả trong khung lẫn ngoài — chỉ quét ngoài là nó rơi lại đúng lúc `renderCfg()`
   chạy lần hai, tức ngay sau lượt Lưu đầu tiên. */
r.chay();
const sauHai = thuTu(r.box);
t('🔴 khối lạ vẫn còn sau lượt xếp THỨ HAI', sauHai.indexOf('khoiMoiChuaKhai') >= 0, sauHai);
teq('   và vẫn ở cuối', 'khoiMoiChuaKhai', sauHai[sauHai.length - 1]);
teq('   tổng khối không đổi', IDS.length + 1, sauHai.length);

/* ⚠️ Nhóm mà mọi khối đều đang ẩn thì không bày dải tiêu đề trống — người xem sẽ tưởng mất
   khối. Nhưng chính các khối ấy vẫn phải còn trong trang. */
const nhomBaoTri = CH_NHOM[CH_NHOM.length - 1];
r = dungBe(IDS, nhomBaoTri.id);
const sau2 = thuTu(r.box);
teq('🔴 nhóm toàn khối ẩn: khối vẫn còn đủ', IDS.length, sau2.length);
nhomBaoTri.id.forEach(i => t('   ' + i + ' vẫn nằm trong trang', sau2.indexOf(i) >= 0, sau2));
const soDai = r.box.children[r.box.children.length - 1].children.filter(c => !c.card).length;
teq('   và nhóm ấy KHÔNG dựng dải tiêu đề', CH_NHOM.length - 1, soDai);

/* Gọi hai lần (renderCfg chạy lại) không được nhân đôi gì. */
/* 🔴 GỌI LẠI LẦN HAI — `renderCfg()` chạy lại sau MỖI lượt Lưu, nên đây là đường đi thường
   ngày chứ không phải ca hiếm. Bản đầu gỡ khung cũ rồi dựng khung mới: mọi khối đã nằm trong
   khung ấy nên rời khỏi cây DOM, `el()` không tìm lại được, và màn Cấu hình TRẮNG TRƠN ngay
   sau khi người dùng vừa bấm Lưu. */
r = dungBe(IDS);
r.chay();
teq('🔴 gọi lại lần hai KHÔNG mất khối nào', IDS.length, thuTu(r.box).length);
teq('   và vẫn đúng thứ tự nhóm', mongDoi, thuTu(r.box));
r.chay(); r.chay();
teq('   gọi bốn lần vẫn nguyên', IDS.length, thuTu(r.box).length);
teq('   và chỉ có MỘT khung chNhomWrap', 1,
  r.box.children.filter(c => c.id === 'chNhomWrap').length);

/* ─────────────────────────────────────────────────────────────────────────────────────── */
console.log('');
if (TRUOT.length) {
  console.log('❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(x => console.log('   • ' + x));
  process.exit(1);
}
console.log('✅ ĐẠT ' + DAT + ' / ' + DAT + ' — màn Cấu hình xếp thành nhóm, không đánh rơi khối nào');
