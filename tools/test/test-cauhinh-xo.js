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
t('giao diện vẫn khoá dòng tài khoản Admin cho người khác',
  /var _laAdmin=\(CURUSER&&CURUSER\.role==='Admin'\)/.test(HTML) && /khoa=\(!_laAdmin && String\(u\.vaiTro\|\|''\)==='Admin'\)/.test(HTML));

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

// ---------------------------------------------------------------- kết
if (hong.length) {
  console.error('\nĐẠT: ' + dat + ' phép thử');
  console.error('HỎNG: ' + hong.length);
  hong.forEach(h => console.error('  ✗ ' + h));
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép thử');
console.log('Tất cả phép thử đều đạt.');
