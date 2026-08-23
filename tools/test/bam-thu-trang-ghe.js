/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẤM THẬT VÀO TRANG /ghe — CHẠY JS TRONG NODE VỚI MỘT DOM GIẢ
 *
 * 🔴 VÌ SAO CẦN, TRONG KHI ĐÃ CÓ 2000 PHÉP THỬ.
 *
 *    Anh Thắng 23/08/2026, hai lần liền: *"chưa chốt ca được"*, rồi *"chốt ca vẫn không thấy
 *    phản hồi gì"*. Lỗi thật là `lam()` khai lộn phạm vi hàm — nút bấm gọi vào một hàm nó không
 *    nhìn thấy.
 *
 *    MỌI phép thử giao diện của bộ này đều canh CHUỖI trong mã nguồn:
 *        strpos( $html, "lam('chot_luu'" ) !== false
 *    Chuỗi đó CÓ trong mã. Nó chỉ không chạy được. Canh chuỗi thì không bao giờ phân biệt được
 *    "mã có mặt" với "mã chạy được" — và khoảng cách giữa hai thứ đó đúng bằng một buổi làm
 *    việc của người đang đứng ở cửa hàng với một ngăn tiền đã mở.
 *
 *    Phép thử này BẤM THẬT: nạp đoạn JS, đăng nhập, mở tab Quỹ, gõ mã ghế, bấm Chốt, điền hai ô
 *    số, bấm Xác nhận — rồi đòi máy chủ giả phải nhận đúng `chot_xem` rồi `chot_luu`.
 *
 * ⚠️ DOM giả này CỐ Ý THÔ. Nó không dựng cây DOM thật, chỉ quét `id=` và `data-*` trong chuỗi
 *    HTML vừa gán để biết có những phần tử nào. Đủ để bắt lỗi phạm vi, lỗi tên hàm, lỗi thứ tự
 *    gọi — là những thứ đã cắn thật. Không đủ để bắt lỗi CSS hay bố cục, và không định bắt.
 *
 * Chạy: node tools/test/bam-thu-trang-ghe.js <đường dẫn class-vhg-trang.php>
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync(process.argv[2], 'utf8');
const i = src.indexOf("<<<'JS'");
const js = src.slice(src.indexOf("\n", i) + 1, src.indexOf("\nJS;", i));

let LOG = [];
function El(id, tag) {
  this.id = id || ''; this.tagName = (tag || 'div').toUpperCase();
  this._html = ''; this.value = ''; this.textContent = ''; this.onclick = null;
  this.onkeydown = null; this.disabled = false; this.style = {}; this.className = '';
  this.children = []; this.parentNode = null; this.dataset = {};
  this.attrs = {};
}
Object.defineProperty(El.prototype, 'innerHTML', {
  get() { return this._html; },
  set(v) { this._html = String(v); DOC._quet(this); }
});
El.prototype.setAttribute = function (k, v) { this.attrs[k] = v; };
El.prototype.getAttribute = function (k) { return this.attrs[k] != null ? this.attrs[k] : null; };
El.prototype.appendChild = function (c) { c.parentNode = this; this.children.push(c); DOC._quet(c); return c; };
El.prototype.insertBefore = function (c) { return this.appendChild(c); };
El.prototype.remove = function () { DOC._xoa(this); };
El.prototype.querySelectorAll = function (sel) { return DOC.querySelectorAll(sel, this); };
El.prototype.querySelector = function (sel) { return DOC.querySelectorAll(sel, this)[0] || null; };
El.prototype.addEventListener = function (t, f) { if (t === 'input') this.oninput = f; if (t==='focus') this.onfocus = f; };
El.prototype.focus = function () {};

const DOC = {
  _ds: {},          // id -> El
  _tatca: [],
  body: null,
  hidden: false,
  createElement(t) { const e = new El('', t); this._tatca.push(e); return e; },
  getElementById(id) { return this._ds[id] || null; },
  /* Quét HTML vừa gán để dựng ra các phần tử có id / data-* — đủ để bắt được handler. */
  _quet(el) {
    const h = el._html || '';
    let m, re = /id="([^"]+)"/g;
    while ((m = re.exec(h))) {
      const e = new El(m[1], 'button');
      /* Đoán thẻ: nếu id xuất hiện trong một <input ...> thì coi là input. */
      const k = h.indexOf('id="' + m[1] + '"');
      const tr = h.lastIndexOf('<', k);
      const the = h.slice(tr + 1, tr + 8).split(/[\s>]/)[0];
      e.tagName = the.toUpperCase();
      const mv = /value="([^"]*)"/.exec(h.slice(tr, k + 200));
      if (mv && the === 'input') e.value = mv[1];
      this._ds[m[1]] = e; this._tatca.push(e);
    }
    re = /data-([a-z-]+)="([^"]*)"/g;
    while ((m = re.exec(h))) {
      const e = new El('', 'button');
      e.attrs['data-' + m[1]] = m[2];
      this._tatca.push(e);
    }
  },
  _xoa(el) { if (el.id) delete this._ds[el.id]; this._tatca = this._tatca.filter(x => x !== el); },
  querySelectorAll(sel) {
    const m = /^\[data-([a-z-]+)(?:="([^"]*)")?\]$/.exec(sel.trim());
    if (m) return this._tatca.filter(e => e.attrs['data-' + m[1]] != null
      && (m[2] == null || e.attrs['data-' + m[1]] === m[2]));
    if (sel.trim() === 'button') return this._tatca.filter(e => e.tagName === 'BUTTON');
    const m2 = /^\.([a-z-]+)$/.exec(sel.trim());
    if (m2) return this._tatca.filter(e => (e.className || '').split(' ').indexOf(m2[1]) >= 0);
    return [];
  },
  execCommand() { return true; },
  addEventListener(t, f) { this['on' + t] = f; },
};
DOC.body = new El('body', 'body');
DOC._ds['app'] = new El('app', 'div');

// ---- gói tin máy chủ giả
const TRALOI = {
  login:    { ok: true, token: 'tok', name: 'Trương Tấn Hiếu', role: 'Nhân viên' },
  so_lieu:  { ok: true, ky: 'today', ai: { name: 'Trương Tấn Hiếu', role: 'Nhân viên', coso: '' },
              may: [{ ma: 'AMTP01', coso: 'Aeon Tân Phú', song: true, tt: 'idle', con_lai: 0, tm: '', chot: null }],
              cho: [], gd: [], choGan: [], coso: [],
              quy: { toi: { tong: 0, tu_ghe: 0, tu_quay: 0, so_dong: 0 }, ca: null, toi_la: 'Trương Tấn Hiếu',
                     don_vi: 10000, chot: [], nop: [], cam: [], cho: [], nguoi: [],
                     tong: { tren_tay: 0, cho_xac_nhan: 0, so_cho: 0, chot_ky: 0, lech_may: 0, lech_dem: 0 },
                     quyen_nhan: 0 },
              quyen: { quan_tri: 0, giup_khach: 0, chot_doanh_so: 0 }, luc: '14:00:00' },
  chot_xem: { ok: true, ma_may: 'AMTP01', coso: 'Aeon Tân Phú', song: 1, lan_dau: 1,
              chi_so_truoc: 0, chot_truoc_luc: '', chot_truoc_ai: '', don_vi: 10000,
              tu_id: 0, den_id: 0, theo_he_thong: 0 },
  chot_luu: { ok: true, thong_bao: 'Đã chốt ghế AMTP01', nguoi: 'Trương Tấn Hiếu' },
};
const GOI = []; const THAN = {};
function XHR() {}
XHR.prototype.open = function (m, u) { this._u = u; };
XHR.prototype.setRequestHeader = function () {};
XHR.prototype.send = function (b) {
  const viec = /api=([a-z_]+)/.exec(this._u)[1];
  GOI.push(viec);
  try { THAN[viec] = JSON.parse(b); } catch (e) {}
  this.readyState = 4;
  this.responseText = JSON.stringify(TRALOI[viec] || { ok: false, error: 'không rõ việc ' + viec });
  if (this.onreadystatechange) this.onreadystatechange();
};

global.document = DOC;
global.window = { VHG_API: '/ghe?', VHG_TEN: 'POSH', addEventListener(){}, scrollTo(){} };
global.localStorage = { getItem: () => null, setItem() {}, removeItem() {} };
global.XMLHttpRequest = XHR;
global.alert = (m) => LOG.push('ALERT: ' + m);
global.confirm = () => true;
global.prompt = () => 'x';
global.setInterval = () => 0; global.clearInterval = () => {};
/* ⚠️ `setTimeout` KHÔNG chạy hàm ngay: trang tự hẹn tải lại mỗi 10 giây, chạy ngay là đệ quy
   vô hạn. Phép thử này soi lượt bấm, không soi bộ hẹn giờ. */
global.setTimeout = () => 0;
global.clearTimeout = () => {};
global.navigator = { language: 'vi' };
global.BarcodeDetector = undefined;

try { eval(js); } catch (e) { console.log('NỔ LÚC NẠP:', e.message); process.exit(1); }

// ─────────────────────────────────────────────────────────────────── các phép bấm
let DAT = 0; const TRUOT = [];
function t(ten, dung, them) {
  if (dung) { DAT++; } else { TRUOT.push(ten + (them ? ' → ' + them : '')); }
}

const oPin = DOC.getElementById('pin'), bVao = DOC.getElementById('vao');
t('màn đăng nhập có ô PIN và nút Vào', !!oPin && !!bVao);
if (!oPin || !bVao) { xong(); }
oPin.value = '1234';
try { bVao.onclick(); } catch (e) { t('bấm Vào không nổ', false, e.message); }
t('đăng nhập rồi thì hỏi số liệu', GOI.indexOf('so_lieu') >= 0);

/* 🔴 Người thu chỉ có tab Quỹ, và tab đó phải có lối vào chốt ca. */
const bChot = DOC.getElementById('quet-di');
t('🔴 tab Quỹ có nút Chốt', !!bChot);
t('và có ô gõ mã ghế', !!DOC.getElementById('quet-tay'));
t('và có nút quét QR', !!DOC.getElementById('quet-mo'));

if (bChot) {
  DOC.getElementById('quet-tay').value = 'AMTP01';
  const truoc = GOI.length;
  try { bChot.onclick(); } catch (e) { t('bấm Chốt không nổ', false, e.constructor.name + ': ' + e.message); }
  t('🔴 bấm Chốt thì hỏi máy chủ mốc chốt ca',
    GOI.slice(truoc).indexOf('chot_xem') >= 0, GOI.slice(truoc).join(',') || '(không gọi gì)');
}

/* 🔴 Bảng chốt ca phải MỞ RA, với đúng HAI ô: chỉ số máy đếm, và tiền đếm được. */
const bOk = DOC.getElementById('chot-ok');
t('🔴 bảng chốt ca mở ra', !!bOk);
t('🔴 có ô nhập CHỈ SỐ máy đếm', !!DOC.getElementById('chot-cs'));
t('🔴 có ô nhập TIỀN đếm được', !!DOC.getElementById('chot-tien'));
t('có ô ghi chú', !!DOC.getElementById('chot-gc'));

if (bOk) {
  /* Chưa gõ chỉ số thì phải chặn lại — KHÔNG được ghi một lượt chốt thiếu mốc. */
  const truoc0 = GOI.length;
  try { bOk.onclick(); } catch (e) { t('bấm khi chưa gõ gì không nổ', false, e.message); }
  t('🔴 chưa gõ chỉ số thì KHÔNG gửi lên máy chủ', GOI.slice(truoc0).indexOf('chot_luu') < 0);
  t('và nói ra vì sao', LOG.some(l => l.indexOf('Chưa nhập chỉ số') >= 0), LOG.join(' | '));

  DOC.getElementById('chot-cs').value = '100';
  DOC.getElementById('chot-tien').value = '50000';
  const truoc = GOI.length;
  try { bOk.onclick(); } catch (e) { t('bấm Xác nhận không nổ', false, e.constructor.name + ': ' + e.message); }
  t('🔴 gõ đủ hai ô thì GỬI LÊN MÁY CHỦ',
    GOI.slice(truoc).indexOf('chot_luu') >= 0, GOI.slice(truoc).join(',') || '(không gọi gì)');
  t('🔴 và mang đủ ba thứ: ghế, chỉ số, tiền đếm được',
    THAN.chot_luu && THAN.chot_luu.ma_may === 'AMTP01'
    && Number(THAN.chot_luu.chi_so) === 100 && Number(THAN.chot_luu.tien_dem) === 50000,
    JSON.stringify(THAN.chot_luu || null));
  t('và báo lại cho người bấm', LOG.some(l => l.indexOf('Đã chốt ghế') >= 0), LOG.join(' | '));
}

/* 🔴 KHÔNG ĐƯỢC CÓ DẢI BÁO LỖI. Trang tự dựng dải đỏ khi có lỗi JS chưa bắt được — có nó nghĩa
   là vừa nổ một chỗ nào đó, kể cả khi mọi phép bấm ở trên vẫn xanh. */
const dai = DOC.getElementById('bao-loi');
t('🔴 không có lỗi JavaScript nào trong cả lượt', !dai, dai ? dai.textContent : '');

xong();
function xong() {
  if (TRUOT.length) {
    console.log('HỎNG: ' + TRUOT.length);
    TRUOT.forEach(x => console.log('  ✗ ' + x));
    console.log('ĐẠT: ' + DAT);
    process.exit(1);
  }
  console.log('✓ ĐẠT ' + DAT + ' phép bấm — nút Chốt ca gọi thật tới máy chủ.');
  process.exit(0);
}
