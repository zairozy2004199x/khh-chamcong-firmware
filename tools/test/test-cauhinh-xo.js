/**
 * Trang CẤU HÌNH: dạng xổ xuống + ai được vào.
 *
 * Hai thứ dễ hỏng âm thầm mà nhìn màn hình không ra ngay:
 *  1) Gập/mở: bấm vào tiêu đề thì gập, nhưng bấm nút "💾 Lưu" nằm NGAY TRONG tiêu đề thì
 *     phải lưu chứ không được gập mất bảng đang sửa.
 *  2) Danh sách vai trò được vào Cấu hình ở giao diện phải KHỚP danh sách máy chủ đang
 *     cho (VHCP_Api::required_roles). Lệch nhau thì kế toán mở ra thấy trắng, hoặc thấy
 *     bảng nhưng bấm Lưu lại bị máy chủ chối.
 *
 * Lấy thẳng hàm trong app.html ra chạy, không chép lại luật ở đây.
 *   node tools/test/test-cauhinh-xo.js
 */
const fs = require('fs');
const path = require('path');

const GOC  = path.join(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const API  = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/includes/class-vhcp-api.php'), 'utf8');
const CSS  = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/assets/css/vhcp.css'), 'utf8');

let dat = 0; const hong = [];
function t(ten, dieu, nhan) { if (dieu) { dat++; return; } hong.push(ten + (nhan === undefined ? '' : ' → nhận được: ' + JSON.stringify(nhan))); }
function teq(ten, mong, nhan) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(nhan), nhan); }

// ---------------------------------------------------------------- 1. đọc tiêu đề thẻ THẬT
const pg = HTML.match(/<div id="page-cauhinh"[\s\S]*?\n  <!-- =+ TRANG:/);
if (!pg) { console.error('HỎNG: không cắt được khối #page-cauhinh trong app.html'); process.exit(1); }
const TIEU_DE = [...pg[0].matchAll(/<div class="ch"[^>]*>\s*<h2>([\s\S]*?)<\/h2>/g)].map(m => m[1].replace(/<[^>]*>/g, '').trim());
t('trang cấu hình có nhiều thẻ để gập (≥8)', TIEU_DE.length >= 8, TIEU_DE.length);
t('không còn thẻ nào gắn nhãn "(chỉ Admin)"', !TIEU_DE.some(x => /chỉ Admin/i.test(x)), TIEU_DE.filter(x => /chỉ Admin/i.test(x)));

// ---------------------------------------------------------------- 2. DOM tối giản
function El(tag, cls) {
  const e = {
    tagName: tag, _cls: new Set(cls ? cls.split(' ') : []), children: [], parentNode: null,
    textContent: '', _ev: {},
    classList: {
      add: c => e._cls.add(c), remove: c => e._cls.delete(c), contains: c => e._cls.has(c),
    },
    appendChild(c) { c.parentNode = e; e.children.push(c); return c; },
    addEventListener(k, f) { (e._ev[k] = e._ev[k] || []).push(f); },
    _all() { return e.children.reduce((a, c) => a.concat([c], c._all()), []); },
    querySelector(sel) { return e.querySelectorAll(sel)[0] || null; },
    querySelectorAll(sel) {
      return e._all().filter(c => sel.split(' ').every(() => true) && khop(c, sel));
    },
    bam(target) { (e._ev.click || []).forEach(f => f({ target: target || e })); },
  };
  return e;
}
// chỉ cần đúng mấy bộ chọn app.html dùng: '.card', '.card.xo', '.ch', '.ch h2'
function khop(node, sel) {
  const cuoi = sel.trim().split(/\s+/).pop();
  const phan = cuoi.split('.').filter(Boolean);
  const tag  = cuoi.startsWith('.') ? null : phan.shift();
  if (tag && node.tagName !== tag) return false;
  if (!phan.every(c => node._cls.has(c))) return false;
  if (sel.trim().includes(' ')) {                       // 'X Y' -> Y phải nằm trong một X
    const cha = sel.trim().split(/\s+/)[0];
    let p = node.parentNode;
    while (p) { if (khop(p, cha)) return true; p = p.parentNode; }
    return false;
  }
  return true;
}

const page = El('div', 'wrap');
const the  = TIEU_DE.map(tieu => {
  const card = page.appendChild(El('div', 'card'));
  const ch   = card.appendChild(El('div', 'ch'));
  const h2   = ch.appendChild(El('h2')); h2.textContent = tieu;
  ch.appendChild(El('button')).textContent = '💾 Lưu';
  card.appendChild(El('div', 'tw'));                    // phần thân bảng
  return card;
});

const kho = {};
global.localStorage = { getItem: k => (k in kho ? kho[k] : null), setItem: (k, v) => { kho[k] = String(v); } };
global.el = id => (id === 'page-cauhinh' ? page : null);

// ---------------------------------------------------------------- 3. lấy hàm thật ra chạy
function layHam(ten) {
  const re = new RegExp('\\n  function ' + ten + '\\([\\s\\S]*?\\n  \\}');
  const m = HTML.match(re);
  if (!m) { console.error('HỎNG: không tìm thấy hàm ' + ten + ' trong app.html'); process.exit(1); }
  return m[0];
}
const nguon = ['_xoKey', '_xoDat', 'xoTatCa', 'dungXoCauHinh'].map(layHam).join('\n')
  + '\n  var _XO_XONG=false;\n  return { xoTatCa: xoTatCa, dung: dungXoCauHinh, dat: _xoDat };';
const M = new Function('el', 'localStorage', nguon)(global.el, global.localStorage);

// ---------------------------------------------------------------- 4. gập / mở
M.dung();
t('mọi thẻ đều thành thẻ xổ được', the.every(c => c._cls.has('xo')), the.filter(c => !c._cls.has('xo')).length);
t('lần đầu: thẻ đầu MỞ', !the[0]._cls.has('dong'));
t('lần đầu: các thẻ sau GẬP hết', the.slice(1).every(c => c._cls.has('dong')));

const ch0 = the[0].querySelector('.ch');
ch0.bam(ch0.querySelector('h2'));
t('bấm tiêu đề thẻ đang mở -> gập lại', the[0]._cls.has('dong'));
ch0.bam(ch0.querySelector('h2'));
t('bấm lần nữa -> mở ra', !the[0]._cls.has('dong'));

// NÚT TRONG TIÊU ĐỀ: bấm Lưu thì KHÔNG được gập mất bảng đang sửa.
const nut = ch0.children.find(c => c.tagName === 'button');
ch0.bam(nut);
t('bấm nút 💾 Lưu trong tiêu đề thì thẻ vẫn MỞ', !the[0]._cls.has('dong'));

M.xoTatCa(false);
t('Gập tất cả: gập hết', the.every(c => c._cls.has('dong')));
M.xoTatCa(true);
t('Mở tất cả: mở hết', the.every(c => !c._cls.has('dong')));

// nhớ trạng thái: dựng lại trang mới, đọc từ localStorage ra
M.xoTatCa(false);
M.dat(the[2], true);
const page2 = El('div', 'wrap');
const the2 = TIEU_DE.map(tieu => {
  const card = page2.appendChild(El('div', 'card'));
  const ch = card.appendChild(El('div', 'ch'));
  ch.appendChild(El('h2')).textContent = tieu;
  return card;
});
global.el = id => (id === 'page-cauhinh' ? page2 : null);
const M2 = new Function('el', 'localStorage', nguon)(global.el, global.localStorage);
M2.dung();
t('mở lại trang: thẻ đã mở vẫn mở', !the2[2]._cls.has('dong'));
t('mở lại trang: thẻ đã gập vẫn gập', the2[0]._cls.has('dong') && the2[1]._cls.has('dong'));

// ---------------------------------------------------------------- 5. CSS có thật
t('CSS có luật ẩn thân thẻ khi gập', /\.card\.xo\.dong\s*>\s*\.ch\s*~\s*\*\s*\{[^}]*display:\s*none/.test(CSS));
t('CSS đổi con trỏ ở tiêu đề thẻ xổ được', /\.card\.xo\s*>\s*\.ch\s*\{[^}]*cursor:\s*pointer/.test(CSS));

// ---------------------------------------------------------------- 6. vai trò vào Cấu hình
const mJs = HTML.match(/var VAI_CAU_HINH=\[([^\]]*)\]/);
t('app.html khai VAI_CAU_HINH', !!mJs);
const vaiJs = mJs ? mJs[1].split(',').map(s => s.trim().replace(/^'|'$/g, '')) : [];
const mPhp = API.match(/if \( in_array\( \$fn, \$cau_hinh, true \) \)\s*\{ return array\(([^)]*)\)/);
t('máy chủ khai danh sách vai trò cho Cấu hình', !!mPhp);
const vaiPhp = mPhp ? mPhp[1].split(',').map(s => s.trim().replace(/^'|'$/g, '')).filter(Boolean) : [];
teq('giao diện và máy chủ CÙNG một danh sách vai trò vào Cấu hình', vaiPhp, vaiJs);
t('Quản lý được vào Cấu hình', vaiJs.includes('Quản lý'), vaiJs);
t('Kế toán được vào Cấu hình', vaiJs.includes('Kế toán cá nhân') && vaiJs.includes('Kế toán NCC'), vaiJs);
t('Nhân viên KHÔNG được vào Cấu hình', !vaiJs.includes('Nhân viên'), vaiJs);
// ⚠️ Biến ở đây TÊN LÀ `laAdminNay`, không phải `_laAdmin`. Đặt tên `_laAdmin` là trùng với
// hàm toàn cục `_laAdmin()` — `var` được kéo lên đầu hàm nên cái tên đó chết ngay từ dòng
// một, và bảng Người dùng & Phân quyền hiện ra TRẮNG TRƠN (đã xảy ra 25/08/2026).
t('giao diện vẫn khoá dòng tài khoản Admin cho người khác',
  /var laAdminNay=\(CURUSER&&CURUSER\.role==='Admin'\)/.test(HTML)
  && /khoa=\(!laAdminNay && String\(u\.vaiTro\|\|''\)==='Admin'\)/.test(HTML));
t('KHÔNG dùng lại tên _laAdmin làm biến', !/var _laAdmin\s*=/.test(HTML));

// ---------------------------------------------------------------- 7. ô cảnh báo xuất MISA
// GẬP SẴN + TẮT ĐƯỢC. Xuất một lượt 169 dòng thì cảnh báo dài hơn cả bảng, đẩy bảng
// xuống dưới màn hình — cái để giúp lại che mất việc chính.
const kho2 = {};
const W = { hien: '', html: '' };
const nutBat = { style: { display: 'none' } };
const oCanhBao = { style: { display: 'none' }, set innerHTML(v) { W.html = v; }, get innerHTML() { return W.html; } };
const moiTruong = {
  el: id => (id === 'xuatWarn' ? oCanhBao : (id === 'xuatWarnBtn' ? nutBat : null)),
  localStorage: { getItem: k => (k in kho2 ? kho2[k] : null), setItem: (k, v) => { kho2[k] = String(v); } },
  toast: () => {},
  esc: v => String(v),
};
const nguonW = ['xuatWarnMo', 'xuatWarnTat', 'xuatWarnBat', 'renderXuatWarn'].map(layHam).join('\n')
  + "\n  var XUAT_W_MO=false, XUAT_W_TAT=(localStorage.getItem('vhcp_tat_canhbao')==='1');"
  + '\n  return { mo: xuatWarnMo, tat: xuatWarnTat, bat: xuatWarnBat, ve: renderXuatWarn };';
function dungW(warn) {
  global.XUAT = { warn: warn };
  return new Function('el', 'localStorage', 'toast', 'esc', 'XUAT', nguonW)(
    moiTruong.el, moiTruong.localStorage, moiTruong.toast, moiTruong.esc, global.XUAT);
}
const DS = Array.from({ length: 169 }, (_, i) => 'Ngày vô lý "22/08/4622" — đơn D_' + i + ' rất dài '.repeat(3));
let Wm = dungW(DS);
Wm.ve();
t('có cảnh báo thì hiện ô', oCanhBao.style.display === 'block', oCanhBao.style.display);
t('gập sẵn: chỉ một dòng tóm tắt, KHÔNG in cả 169 câu',
  W.html.indexOf('169 cảnh báo') >= 0 && W.html.indexOf('D_168') < 0, W.html.length);
t('gập sẵn: có mũi tên ▸', W.html.indexOf('▸') >= 0);
Wm.mo();
t('bấm vào: xổ ra đủ 169 câu', W.html.indexOf('D_168') >= 0 && W.html.indexOf('▾') >= 0, W.html.length);
Wm.mo();
t('bấm lần nữa: gập lại', W.html.indexOf('D_168') < 0);
Wm.tat();
t('bấm ✕ Tắt: ẩn hẳn ô cảnh báo', oCanhBao.style.display === 'none', oCanhBao.style.display);
t('tắt rồi thì hiện nút 🔔 để bật lại', nutBat.style.display === 'inline-block', nutBat.style.display);
t('lựa chọn tắt được nhớ lại', kho2['vhcp_tat_canhbao'] === '1', kho2);
// mở lại trang: vẫn tắt
Wm = dungW(DS);
Wm.ve();
t('mở lại trang: vẫn đang tắt', oCanhBao.style.display === 'none', oCanhBao.style.display);
Wm.bat();
t('bấm 🔔: hiện lại', oCanhBao.style.display === 'block' && nutBat.style.display === 'none');
// không có cảnh báo thì không có gì cả
Wm = dungW([]);
Wm.ve();
t('không có cảnh báo: ẩn ô lẫn nút 🔔',
  oCanhBao.style.display === 'none' && nutBat.style.display === 'none');
t('nút 🔔 có thật trong trang Xuất MISA', /id="xuatWarnBtn"[^>]*onclick="xuatWarnBat\(\)"/.test(HTML));

// ---------------------------------------------------------------- 6. BẢNG NGƯỜI DÙNG: cột có lệch không
/*
 * 🔴 `_readRows()` ĐỌC THEO VỊ TRÍ. Anh Thắng 26/08/2026: *"Chỗ đơn vị anh gõ rồi vẫn trống"*.
 *    Gõ xong, lưu, quay lại rỗng — mà máy chủ thì ghi đúng.
 *
 *    Hộp chọn cơ sở thu gọn đẻ ra BA thứ trong một ô: `<select multiple>` ẩn (giá trị thật),
 *    một đám checkbox, và một Ô GÕ ĐỂ LỌC. Checkbox đã bị loại từ trước; ô gõ để lọc thêm vào
 *    sau và không ai loại. Từ lúc ấy mọi cột sau "Cơ sở" lệch đúng một nhịp — TK Có bị xoá
 *    trắng mỗi lần lưu, Mã đối tượng nuốt giá trị của TK Có, và Đơn vị thì luôn rỗng.
 *
 * ⚠️ CÁI BẪY LÀ "ĐỌC THEO VỊ TRÍ", KHÔNG PHẢI CÁI Ô LỌC. Thêm bất kỳ `<input>` nào vào giữa
 *    bảng là lệch lại. Nên phép dưới đây ĐẾM Ô THẬT của từng cột — đọc từ chính mã của các hàm
 *    dựng ô — rồi so với chỉ số mà `saveCfgUsers` đang dùng. Không gõ tay con số nào.
 */
function thanHam(ten) {
  const i = HTML.indexOf('function ' + ten + '(');
  if (i < 0) return '';
  let d = 0, bat = -1;
  for (let k = i; k < HTML.length; k++) {
    if (HTML[k] === '{') { if (d === 0) bat = k; d++; }
    else if (HTML[k] === '}') { d--; if (d === 0) return HTML.slice(bat, k + 1); }
  }
  return '';
}
/* Bao nhiêu ô mà `_readRows` sẽ ĐẾM trong đoạn HTML do hàm này sinh ra. Luật loại trừ đọc
   THẲNG từ chuỗi selector trong `_readRows`, không chép lại ở đây. */
/* 🔴 BỐC TRONG THÂN `_readRows()`, ĐỪNG QUÉT CẢ TỆP. Bản trước lấy `tr.querySelectorAll('…')`
   ĐẦU TIÊN gặp trong app.html — tức bám vào thứ tự các hàm trong tệp, chứ không bám vào hàm
   cần soi. Cắn thật 22/09/2026: thêm `_khoaDongKhoiLa()` (đứng trước `_readRows` trong tệp) là
   bài này đỏ bốn phép, mà `_readRows()` không hề đụng tới. */
const SELECTOR = (thanHam('_readRows').match(/tr\.querySelectorAll\('([^']+)'\)/) || [])[1] || '';
t('đọc được chuỗi selector của _readRows', SELECTOR.indexOf('input') >= 0, SELECTOR);
const BO_CHECKBOX = SELECTOR.indexOf(':not([type=checkbox])') >= 0;
const BO_CSTIM    = SELECTOR.indexOf(':not(.cs-tim)') >= 0;
/* 🔴 Ô LÁI `.vai-cha` (21/09/2026) — anh Thắng: *"chọn cha thì chỉ ra con của cha"*. Ô VAI TRÒ
   nay là HAI ô chồng nhau: ô trên chọn nhóm, ô dưới mang giá trị. Ô trên không được đếm, y như
   ô gõ-để-lọc `.cs-tim`. Suy từ chính bộ chọn của `_readRows()` chứ không gõ tay: hai bên lệch
   nhau là phép này canh một thế giới khác với thế giới app đang chạy. */
const BO_VAICHA   = SELECTOR.indexOf(':not(.vai-cha)') >= 0;
t('🔴 _readRows loại ô gõ-để-lọc của hộp chọn cơ sở (.cs-tim)', BO_CSTIM, SELECTOR);
t('và vẫn loại checkbox như trước', BO_CHECKBOX, SELECTOR);
t('🔴 _readRows loại ô lái nhóm vai trò (.vai-cha)', BO_VAICHA, SELECTOR);

function demO(than) {
  let n = 0;
  for (const m of than.matchAll(/<(input|select)\b([^>]*)>/g)) {
    const the = m[1], thuoc = m[2];
    if (the === 'input' && BO_CHECKBOX && /type="checkbox"/.test(thuoc)) continue;
    if (BO_CSTIM && /class="[^"]*\bcs-tim\b/.test(thuoc)) continue;
    if (the === 'select' && BO_VAICHA && /class="[^"]*\bvai-cha\b/.test(thuoc)) continue;
    n++;
  }
  return n;
}
/* Hàng người dùng dựng bằng những hàm nào, theo đúng thứ tự trong mã. */
/* ⚠️ NEO VÀO `esc(u.ten`, KHÔNG GHIM `'<tr><td>'`. (Trước 10/09/2026 neo là `_inp(u.ten`; ô Tên
   nay khoá lại nên viết thẳng `<input readonly>` chứ không qua `_inp` nữa — neo vào TÊN CỘT thì
   đổi cách dựng ô không làm bài kiểm đỏ oan.) Ngày 09/09/2026 thẻ `<tr>` được thêm thuộc
   tính (tô nền hàng khai lệch nhà/tầm nhìn) và bài này đỏ với năm dòng "mong undefined" — trông
   y như mã hỏng, mà thật ra chỉ là cái neo ghim vào một thứ KHÔNG liên quan tới phép đang canh.
   Phép này canh CHỈ SỐ Ô; thẻ mở hàng trông thế nào thì mặc kệ. */
/* `[^\n]*?` chứ không phải `[\s\S]*?`: hàng CHỈ ĐỌC (tài khoản bị khoá) cũng mở bằng
   `return '<tr` ở một dòng trên. Cho phép vắt qua dòng là nó ngoạm luôn cả hàng ấy — 86 ô thay
   vì 9, và bài kiểm lại đỏ vì chính cái neo của mình. */
const HANG = (HTML.match(/return '<tr[^\n]*?esc\(u\.ten[\s\S]*?_delBtn\(\)\+'<\/tr>';/) || [])[0] || '';
t('cắt được dòng dựng hàng người dùng', HANG.length > 50, HANG.slice(0, 60));
/* ⚠️ CÓ Ô VIẾT THẲNG TRONG HÀNG, không qua hàm dựng — ô PIN là một `<input>` gõ tay ngay
   trong chuỗi. Chỉ quét tên hàm là bỏ sót đúng nó, và mọi cột sau đó lệch một nhịp trong
   CHÍNH BÀI KIỂM — một lỗi của bài kiểm trông y như lỗi của mã. Nên quét CẢ HAI, theo đúng
   thứ tự chúng xuất hiện. */
/* ⚠️ THÊM HÀM DỰNG Ô MỚI THÌ PHẢI KHAI VÀO ĐÂY. Ngày 08/09/2026 ô "Xem đơn vị" đổi từ `_inp`
   (gõ tay) sang `_xemDvSel` (hộp tích), và bài này đỏ ngay — đúng việc của nó: nó đang canh
   "số ô dựng ra bằng đúng số cột dữ liệu", mà `_readRows()` đọc theo CHỈ SỐ nên lệch một ô là
   mọi cột sau đó đọc trượt sang cột bên cạnh. Sửa bằng cách dạy nó tên hàm mới, KHÔNG phải
   bằng cách nới con số cho qua. */
const GOI = [...HANG.matchAll(/(_inp|_roleSel|_bpSel|_cosoSel|_khoiTichNguoi)\(|<(input|select)\b([^>]*)>/g)]
  .filter(m => {
    if (m[1]) return true;
    if (BO_CHECKBOX && /type="checkbox"/.test(m[3] || '')) return false;
    if (BO_CSTIM && /class="[^"]*\bcs-tim\b/.test(m[3] || '')) return false;
    return true;
  })
  .map(m => m[1] || ('<' + m[2] + '>'));
t('hàng người dùng dựng đủ ô cho mọi cột (≥8)', GOI.length >= 8, GOI);

/* 🔴 HAI CỘT ĐÃ BỎ 12/09/2026 — anh Thắng: *"Bỏ cột tài khoản có"* và *"Đơn vị với xem đơn vị
   là 1"*. `tkCo` và `xemDonVi` không còn ô nào trên màn, nên cũng không còn chỉ số; chúng vẫn
   được gửi lên dưới dạng chuỗi RỖNG CỨNG để máy chủ ghi đúng số cột của sổ.
   ⚠️ Phép dưới cùng canh đúng chỗ ấy: hai khoá đó phải là hằng '' trong `saveCfgUsers`, KHÔNG
      được là `r[n]` — lỡ ai đó trả lại chỉ số cho chúng là mọi cột sau lại trượt một nhịp. */
/* Cột "Mã NV" thêm 13/09/2026 — anh Thắng: *"nếu đẩy từ nhân sự sang, mà nhân viên này trùng
   với nhân viên tạo trực tiếp trên trang chi phí thì sao"*. Nó nằm NGAY SAU Tên, nên mọi cột
   phía sau dịch đúng một nhịp — và đó chính là loại thay đổi phép này sinh ra để canh. */
/* Cột "Đơn vị" đổi thành "Khối" 21/09/2026 — anh Thắng: *"chỗ đơn vị thay bằng khối —
   tích nếu 1 người làm 2 khối thì chọn 2"*. 🔴 Ô ẤY SINH RA HAI Ô ẨN chứ không phải một:
   khối trước, rồi đơn vị cũ đi theo — đơn vị vẫn là cổng quyền "đọc được sổ nhà nào",
   gửi rỗng lên là cả công ty về nhà mẹ. Nên danh sách cột dài thêm đúng một tên, và chính
   phép đếm dưới đây là thứ canh cho hai ô ấy không bao giờ đảo chỗ cho nhau. */
const COT = ['ten', 'maNv', 'pin', 'vaiTro', 'boPhan', 'coso', 'maDt', 'khoi', 'donVi'];
/* 🔴 MỘT HÀM DỰNG Ô KHÔNG CÒN BẮT BUỘC LÀ MỘT CỘT. `_khoiTichNguoi()` sinh HAI ô đọc được
   (khối, rồi đơn vị cũ đi kèm), nên phép cũ "số HÀM bằng số cột" đếm hụt đúng một nhịp.
   Nay đếm số Ô THẬT và phát tên cột theo đúng số ô từng hàm sinh ra — chặt hơn bản cũ,
   vì nó còn bắt được cả trường hợp một hàm lặng lẽ thêm bớt ô bên trong thân nó. */
const CHI_SO = {};
let dem = 0, iCot = 0;
GOI.forEach((ten) => {
  /* Ô viết thẳng trong hàng thì đúng một ô; ô dựng bằng hàm thì đếm trong thân hàm ấy. */
  const soO = (ten[0] === '<') ? 1 : demO(thanHam(ten));
  for (let k = 0; k < soO; k++) { if (COT[iCot]) { CHI_SO[COT[iCot]] = dem + k; } iCot++; }
  dem += soO;
});
teq('số ô hàng dựng ra bằng đúng số cột dữ liệu', COT.length, dem);
/* Chỉ số mà `saveCfgUsers` ĐANG dùng — đọc từ chính mã, không chép lại. */
const SAVE = thanHam('saveCfgUsers');
const DUNG = {};
for (const m of SAVE.matchAll(/(\w+):\((?:r\[(\d+)\])\|\|''\)/g)) { DUNG[m[1]] = Number(m[2]); }
for (const m of SAVE.matchAll(/(\w+):r\[(\d+)\]\|\|''/g))          { DUNG[m[1]] = Number(m[2]); }
t('đọc được chỉ số saveCfgUsers đang dùng', Object.keys(DUNG).length >= 6, DUNG);
COT.forEach(k => {
  if (!(k in DUNG)) return;
  teq('cột "' + k + '" — chỉ số saveCfgUsers khớp số ô thật', CHI_SO[k], DUNG[k]);
});
t('🔴 "tkCo" không còn chỉ số ô nào — cột đã bỏ',     !('tkCo' in DUNG), DUNG);
t('🔴 "xemDonVi" không còn chỉ số ô nào — cột đã bỏ', !('xemDonVi' in DUNG), DUNG);
t('   nhưng vẫn gửi lên dưới dạng rỗng, khỏi lệch cột sổ',
  /tkCo:''/.test(SAVE) && /xemDonVi:''/.test(SAVE), SAVE.slice(0, 200));

/* 🔴 GOM TỪ MỌI BẢNG. Bảng người dùng tách theo vai trò (12/09/2026), nên `saveCfgUsers` phải
   quét hết các tbody chứ không đọc một id cố định. Sót một bảng = những người trong đó không
   nằm trong gói gửi lên, mà lượt Lưu ghi đè cả danh sách — tức XOÁ họ khỏi sổ, im lặng. */
t('🔴 lưu người dùng gom từ MỌI bảng vai trò, không phải một id cố định',
  /_uMoiHang\(\)/.test(SAVE) && /data-user-body/.test(thanHam('_uMoiHang')), SAVE.slice(0, 120));


// ---------------------------------------------------------------- kết
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * TÊN VÀ PIN KHOÁ LẠI — LẤY TỪ HỆ THỐNG NHÂN SỰ
 *
 * Anh Thắng 10/09/2026: *"Chỗ tên người dùng được lấy từ hệ thống nhân sự, nên bên trang chi
 * phí không cho sửa tên, sửa dễ sai hệ thống.. Cả mã pin cũng vậy"*.
 *
 * 🔴 TÊN LÀ KHOÁ NỐI HAI BÊN. Mọi đơn đã lập mang TÊN người lập, nên gõ lệch một chữ ở đây là
 *    người ấy mất sạch đơn cũ của mình — không có câu lỗi nào, chỉ là màn của họ trống.
 *
 * 🔴 KHOÁ NHƯNG VẪN PHẢI GỬI LÊN. `readonly` giữ nguyên `.value`, nên hàng cũ lưu lại không
 *    mất tên/PIN. Nếu ai đó đổi sang `disabled` thì trình duyệt BỎ QUA ô ấy — lưu một cái là
 *    mọi người mất tên và PIN, và họ không đăng nhập được nữa.
 * ═══════════════════════════════════════════════════════════════════════════════════════ */
const O_TEN = (HANG.match(/<input value="'\+esc\(u\.ten[^>]*>/) || [])[0] || '';
/* 🔴 Ô PIN nay là `type="password"` — anh Thắng 14/09/2026 gửi ảnh bảng bày ra hàng chục PIN
   (`1000`, `1122`, `2233`…). Trang này chạy ngoài internet: ai đứng sau lưng, ai xem một ảnh
   chụp màn, ai mở "xem mã nguồn trang" đều đọc được. Mẫu dò phải theo, nếu không phép kiểm
   canh một ô không còn tồn tại và trượt cả bốn phép dưới. */
/* 🔴 TỪ 1.271.0 Ô PIN HIỆN THẲNG VỚI ADMIN THẬT — anh Thắng yêu cầu lần thứ hai: *"làm hiện
   pin luôn đi em"*. Nên kiểu ô nay là một biểu thức, không còn là chuỗi `type="password"`. */
const O_PIN = (HANG.match(/<input type="'\+\(_laAdminThat\(\)\?'text':'password'\)\+'" value="'\+esc\(u\.pin[^>]*>/) || [])[0] || '';
t('🔴 ô Tên khoá lại (readonly)', /\breadonly\b/.test(O_TEN), O_TEN);
t('🔴 ô PIN cũng khoá lại',      /\breadonly\b/.test(O_PIN), O_PIN);
t('🔴 KHÔNG dùng disabled — disabled thì trình duyệt bỏ qua ô, lưu một cái là mất sạch tên/PIN',
  !/\bdisabled\b/.test(O_TEN) && !/\bdisabled\b/.test(O_PIN), [O_TEN, O_PIN]);
t('   và vẫn mang giá trị thật lên (readonly giữ nguyên .value)',
  /value="'\+esc\(u\.ten/.test(O_TEN) && /value="'\+esc\(u\.pin/.test(O_PIN), [O_TEN, O_PIN]);
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHÔNG IN PIN RA MÀN HÌNH — luật cứng của cả hệ, và bảng này từng vi phạm nó.
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * ⚠️ CHE BẰNG `type="password"`, KHÔNG thay giá trị bằng dấu chấm: bảng lưu bằng cách đọc lại
 *    MỌI ô trên hàng, nên thay giá trị là lượt Lưu kế tiếp ghi đè PIN thật bằng chuỗi che —
 *    khoá tài khoản của cả bảng cùng lúc.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 LUẬT "KHÔNG IN PIN RA MÀN" ĐÃ ĐỔI — ANH THẮNG CHỐT 22/09/2026: *"làm hiện pin luôn đi em"*.
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Ghi lại đây để sau này không ai "sửa lại cho an toàn" mà không biết mình đang lật một quyết
 * định của người chủ hệ.
 *
 * ⚠️ VÀ PHẢI NÓI RÕ VÌ SAO NÓ KHÔNG NỚI THÊM GÌ: `getUsers()` VỐN ĐÃ gửi PIN thật xuống trình
 *    duyệt, và hàng người dùng đặt nó vào `value`. Ai mở F12 là đọc được PIN cả bảng, từ
 *    trước tới giờ. Lớp `type="password"` cũ chưa bao giờ giấu được gì — nó chỉ khiến người
 *    nhìn TƯỞNG là có giấu, và cái tưởng ấy mới là thứ nguy.
 *
 * 🔴 CHỖ HỎNG THẬT VẪN NGUYÊN, và bài này KHÔNG canh được nó vì nó nằm ở máy chủ:
 *      · `getUsers()` gửi PIN xuống màn — lẽ ra chỉ nên gửi CÓ/KHÔNG;
 *      · PIN lưu dạng CHỮ THƯỜNG, không băm.
 *    Cả hai đổi đường đăng nhập của mọi người nên phải anh Thắng chốt riêng.
 *
 * ⚠️ NHƯNG VẪN CÒN HAI CHỐT, và bài này canh đúng hai cái đó:
 *      · chỉ ADMIN THẬT mới thấy — vai đang giả lập thì không;
 *      · vẫn che lại được, và che HẾT trong một nhịp trước khi chia sẻ màn hình.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 ô PIN hiện thẳng CHỈ khi là Admin THẬT, còn lại vẫn che',
  /_laAdminThat\(\)\?'text':'password'/.test(O_PIN), O_PIN);
t('⚠️ và KHÔNG thay giá trị bằng dấu chấm (lưu một cái là mất sạch PIN)',
  /value="'\+esc\(u\.pin\|\|''\)\+'"/.test(O_PIN), O_PIN);
/* 🔴 CHE LẠI ĐƯỢC, VÀ CHE HẾT TRONG MỘT NHỊP. Bảng bày PIN của toàn công ty; sắp chia sẻ màn
   hình mà phải bấm che từng dòng thì không ai bấm. */
t('🔴 có nút che HẾT PIN trong một nhịp', /onclick="pinCheHet\(\)"/.test(HTML), '');
const F_CHE = (HTML.match(/function pinCheHet\(\)\{[\s\S]*?\n  \}/) || [])[0] || '';
/* Bảng người dùng chia NHIỀU `<tbody>`, mỗi vai một cái. Bộ chọn theo một id là trúng đúng
   một nhóm vai, mấy nhóm còn lại vẫn phơi PIN — mà nút vẫn báo "đã che" kèm một con số nghe
   có vẻ hợp lý. */
t('🔴 che hết quét MỌI nhóm vai, không chỉ một `<tbody>`',
  /\[data-user-body\]/.test(F_CHE) && !/#cfgUserBody['"\s]/.test(F_CHE), F_CHE.slice(0, 300));
t('   và nút ấy chỉ bày cho Admin thật', /_laAdminThat\(\)/.test(HTML.slice(HTML.indexOf('function _veNutCheHetPin'), HTML.indexOf('function _veNutCheHetPin') + 250)), '');
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 NÚT 👁 HIỆN PIN — anh Thắng 22/09/2026: *"Cho hiện Pin"*.
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Luật "không in PIN ra màn" ở trên VẪN NGUYÊN: ô mặc định vẫn `type="password"`. Nút này chỉ
 * mở ra theo từng lượt bấm, và phải giữ đủ ba chốt giảm hại — bảng này bày PIN của TOÀN CÔNG
 * TY trên một màn.
 *
 * ⚠️ VÀ PHẢI NÓI RA ĐIỀU NÀY, vì nó đổi hẳn cách đọc mấy phép ở trên: lớp che `type="password"`
 *    CHƯA BAO GIỜ giấu được gì. `getUsers()` gửi PIN thật xuống trình duyệt và hàng này đặt nó
 *    vào `value`; ai mở F12 là đọc được PIN cả bảng, không cần nút nào. Nút 👁 không mở thêm
 *    cửa — nó chỉ thôi giả vờ rằng cửa đang đóng. Chỗ hỏng thật là máy chủ GỬI PIN xuống, và
 *    PIN lưu dạng chữ thường. Hai cái ấy phải anh Thắng chốt vì chúng đổi đường đăng nhập của
 *    mọi người.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
t('🎯 có nút hiện PIN', /onclick="pinHien\(this\)"/.test(HTML), '');
const F_PIN = (HTML.match(/function pinHien\(btn\)\{[\s\S]*?\n  \}/) || [])[0] || '';
/* ⚠️ SOI CHÍNH CÁI CỔNG TRƯỚC NÚT, không soi mỗi "có chữ `_laAdminThat` ở đâu đó". Hàm
   `pinHien()` vẫn hỏi nó, nên phép lỏng vẫn xanh trong khi CÁI NÚT đã bày cho mọi vai —
   bày ra một cái nút bấm vào thì bị chối là vừa vô duyên vừa mời người ta thử. */
t('🔴 chỉ ADMIN THẬT xem được — đang giả lập vai khác thì không thấy nút',
  /_laAdminThat\(\)\s*\n?\s*\?\s*'<button/.test(HTML) && /_laAdminThat\(\)/.test(F_PIN),
  F_PIN.slice(0, 200));
/* ⚠️ KHÔNG CÒN "tự ẩn sau 10 giây": ô nay HIỆN SẴN với Admin thật, nên một cái hẹn giờ che
   lại sau mười giây là nó chống lại chính điều anh Thắng vừa yêu cầu. Đường che nay là bấm
   tay — nút từng dòng, hoặc nút che hết. */
t('🔴 nút từng dòng đổi được CẢ HAI CHIỀU (che rồi hiện lại)',
  /o\.type=dangHien\?'password':'text'/.test(F_PIN), F_PIN.slice(0, 300));
t('   và dọn hẹn giờ cũ của bản trước, kẻo nó nổ muộn và che lại đúng lúc vừa bấm hiện',
  /clearTimeout\(o\._pinHen\)/.test(F_PIN), F_PIN.slice(0, 300));
/* Hẹn giờ phải gắn vào CHÍNH Ô ẤY. Một biến chung thì bấm 👁 ba dòng liền nhau là lượt đếm sau
   xoá lượt trước — hai dòng đầu phơi PIN mãi, mà người bấm tưởng đã tự ẩn hết vì thấy dòng
   cuối ẩn đi. */
t('   nút đọc đúng trạng thái đang hiện của chính ô ấy', /o\.type==='text'/.test(F_PIN), F_PIN.slice(0, 300));
/* 🔴 KHÔNG CÓ "HIỆN TẤT CẢ". Một lần chụp màn không được lộ cả bảng PIN của công ty. */
t('🔴 KHÔNG có nút hiện tất cả PIN cùng lúc',
  !/pinHienTatCa|hienTatCaPin|pinHienHet/.test(HTML), '');
t('   và người KHÔNG phải Admin thật thì vẫn che',
  /'password'/.test(O_PIN), O_PIN);

/* Hàng THÊM MỚI cũng phải che: người khai gõ PIN cho người khác, ngay giữa văn phòng. */
t('🔴 ô PIN của hàng thêm mới cũng che',
  /<input type="password" maxlength="8"/.test(HTML), '');
t('🔴 rê chuột vào nói rõ phải sửa ở đâu (khoá mà không nói thì người ta tưởng hỏng)',
  /title="[^"]*nhân sự/.test(O_TEN) && /title="[^"]*nhân sự/.test(O_PIN), [O_TEN, O_PIN]);
t('   nhìn cũng biết là khoá, không phải ô gõ được', /background:#f4f1ec/.test(O_TEN), O_TEN);
t('   tiêu đề cột nói rõ nguồn', HTML.indexOf('>Tên <span style="font-weight:400;color:#8c8781">(từ nhân sự)</span></th>') >= 0);
t('   và có câu giải thích dưới bảng', HTML.indexOf('lấy từ <b>hệ thống nhân sự</b> nên khoá ở đây') >= 0);
/* Các cột KHÁC vẫn phải sửa được — khoá quá tay thì bảng thành chỉ để ngắm. */
t('🔴 vai trò · bộ phận · cơ sở · khối vẫn sửa được',
  !/readonly/.test(thanHam('_roleSel')) && !/readonly|disabled/.test(thanHam('_khoiTichNguoi')), null);

if (hong.length) {
  console.error('\nĐẠT: ' + dat + ' phép thử');
  console.error('HỎNG: ' + hong.length);
  hong.forEach(h => console.error('  ✗ ' + h));
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép thử');
console.log('Tất cả phép thử đều đạt.');
