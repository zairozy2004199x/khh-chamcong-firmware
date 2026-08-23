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

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GÓI TIN MÁY CHỦ GIẢ — PHẢI GIÀU, KHÔNG ĐƯỢC RỖNG.
 *
 * 🔴 BÀI HỌC 23/08/2026. Bản đầu của tệp này dùng fixture rỗng: `ca: null`, ghế `chot: null`,
 *    không lượt chốt nào. Nên nó KHÔNG chạy vào nhánh dựng khối "Báo cáo ca" và khối chỉ số
 *    trên thẻ ghế — đúng hai chỗ có `Lf(...)`, một hàm KHÔNG TỒN TẠI ở tệp này. Phép thử xanh,
 *    còn trang thật thì trắng trơn ngay lúc dựng.
 *
 *    Một phép thử chỉ bảo vệ được những dòng nó CHẠY QUA. Fixture rỗng là fixture chỉ chạy qua
 *    nhánh "chưa có gì" — mà nhánh đó thì bao giờ cũng đơn giản nhất và ít hỏng nhất.
 *
 * ⚠️ Nên: mọi danh sách đều CÓ PHẦN TỬ, mọi ô tuỳ chọn đều CÓ GIÁ TRỊ, và chạy hai lượt — một
 *    lượt vai người thu, một lượt vai quản trị mở qua TẤT CẢ các tab.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const CHOT_MAU = {
  id: 7, ma_may: 'AMTP01', nguoi: 'Trương Tấn Hiếu', chi_so: 120, chi_so_truoc: 100,
  don_vi: 10000, theo_may: 200000, theo_he_thong: 180000, tien_dem: 195000,
  lech_dem: -5000, lech_may: 20000, lan_dau: 0, tu_id: 1, den_id: 9,
  tu_luc: '2026-08-22 08:00:00', tao_luc: '2026-08-23 14:00:00', ghi_chu: '', nop_id: 0,
  lap_lai: 0, canh_bao: 'Ngăn THIẾU 5.000đ so với máy đếm.',
};
const QUY_DAY = {
  toi: { nguoi: 'Trương Tấn Hiếu', tong: 245000, tu_ghe: 195000, tu_quay: 50000, so_dong: 2 },
  ca: { nguoi: 'Trương Tấn Hiếu', so_ghe: 1, tien_dem: 195000, theo_may: 200000,
        theo_he_thong: 180000, lech_dem: -5000, lech_may: 20000, tu_quay: 50000,
        tong: 245000, tu_luc: '2026-08-23 14:00:00', ds: [CHOT_MAU] },
  toi_la: 'Trương Tấn Hiếu', don_vi: 10000,
  chot: [CHOT_MAU], nop: [{ id: 3, nguoi: 'Trương Tấn Hiếu', so_tien: 100000, so_tien_nhan: 100000,
                            so_dong: 1, trang_thai: 'da_nhan', tao_luc: '2026-08-23 10:00:00',
                            nhan_luc: '2026-08-23 10:05:00', nhan_ai: 'Sếp', ghi_chu: '' }],
  cam: [{ nguoi: 'Trương Tấn Hiếu', tu_ghe: 195000, tu_quay: 50000, so_dong: 2, tong: 245000 }],
  cho: [{ id: 4, nguoi: 'Chị Hoa', so_tien: 80000, so_dong: 1, tao_luc: '2026-08-23 13:00:00', ghi_chu: 'ca sáng' }],
  nguoi: [{ nguoi: 'Trương Tấn Hiếu', tu_ghe: 195000, tu_quay: 50000, so_lan_chot: 1,
            lech_dem: -5000, lech_may: 20000, da_nop: 100000, lech_nop: 0, so_lan_nop: 1,
            dang_cam: 245000 }],
  tong: { tren_tay: 245000, cho_xac_nhan: 80000, so_cho: 1, chot_ky: 195000,
          lech_may: 20000, lech_dem: -5000 },
  quyen_nhan: 1,
};
const GHE_MAU = {
  ma: 'AMTP01', coso: 'Aeon Tân Phú', song: true, tt: 'running', con_lai: 1750, cho: 0,
  gia: 10000, phut: 6, tm: '', tm_cu: '', chot: CHOT_MAU,
};
function soLieu(quyen) {
  return {
    ok: true, ky: 'today', ai: { name: 'Trương Tấn Hiếu', role: 'Nhân viên', coso: '' },
    may: [GHE_MAU], quy: QUY_DAY, quyen: quyen, luc: '14:00:00',
    cho: [{ luc: '2026-08-23 13:59:00', ma_may: 'AMTP01', so_tien: 10000, ma_lenh: 'K7M2P' }],
    gd: [{ luc: '2026-08-23 13:00:00', may: 'AMTP01', nguon: 'sepay', so_tien: 10000, noi_dung: 'GHEAMTP01 K7M2P' }],
    choGan: [{ mac: 'AA:BB:CC:DD:EE:01', ma: '?DD9858', song: true }],
    coso: [{ id: 1, ten: 'Aeon Tân Phú' }],
    goi: [{ menh_gia: 10000, ten: 'Gói thử', mo_ta: '', vip: false, giam_pt: 0, gia_ban: 10000, phut: 6 }],
    tong: { tong: 500000, so_luot: 12, qr: 400000, qr_luot: 9, tien_mat: 100000, tien_mat_luot: 3,
            coso: [{ coso: 'Aeon Tân Phú', so_luot: 12, qr: 400000, tien_mat: 100000, tong: 500000 }] },
    thu: { ds: [{ luc: '2026-08-23 12:00:00', ma_may: 'AMTP01', so_tien: 20000, kieu: 'ghe', nguoi: '' },
                { luc: '2026-08-23 12:30:00', ma_may: 'AMTP01', so_tien: 50000, kieu: 'nguoi', nguoi: 'Trương Tấn Hiếu' }],
           may: [{ may: 'AMTP01', coso: 'Aeon Tân Phú', mat_ghe: 20000, mat_nguoi: 50000, qr: 400000,
                   tong: 470000, so_lan_thu: 1, cong_doi: true }] },
    bat: { ky: { so: 2, phut: 12 }, thang: { so: 5, phut: 30 }, ngay: [], may: [],
           ds: [{ luc: '2026-08-23 09:00:00', ma: 'AMTP01', phut: 6, nguoi: 'Sếp', ly_do: 'khách phàn nàn', da_gui: true }] },
    ma: { tong: { so: 3, tien: 150000 }, no: 50000, quyen_huy: 1,
          ds: [{ ma: 'ABCD-EFGH', menh_gia: 50000, sdt: '0909***111', da_dung: 0, huy: 0,
                 tao_luc: '2026-08-20 10:00:00', dung_luc: '', dung_tu: '', con_cho: 0 }] },
    vi: { no: 300000, co_ban: 1,
          ds: [{ sdt: '0909***111', so_du_dung: 200000, so_du_cho: 0, tich: 3, khoa: 0,
                 sua_luc: '2026-08-23 11:00:00' }] },
    qua: { bat: 1, tong: { so: 2, tien: 20000, cho: 1 },
           cho: [{ id: 5, sdt: '0909***111', kieu: 'ca_hai', moc: 10, gia_tri: 10000,
                   ghi_chu: 'Quà tri ân', tao_luc: '2026-08-23 09:00:00' }] },
  };
}
let QUYEN = { quan_tri: 0, giup_khach: 0, chot_doanh_so: 0 };
const TRALOI = {
  login:    { ok: true, token: 'tok', name: 'Trương Tấn Hiếu', role: 'Nhân viên' },
  get so_lieu() { return soLieu(QUYEN); },
  chot_xem: { ok: true, ma_may: 'AMTP01', coso: 'Aeon Tân Phú', song: 1, lan_dau: 0,
              chi_so_truoc: 100, chot_truoc_luc: '2026-08-22 08:00:00', chot_truoc_ai: 'Chị Hoa',
              don_vi: 10000, tu_id: 1, den_id: 9, theo_he_thong: 180000 },
  chot_luu: Object.assign({ ok: true, thong_bao: 'Đã chốt ghế AMTP01' }, CHOT_MAU),
  so_may:   { ok: true, ma_may: 'AMTP01', coso: 'Aeon Tân Phú', gia: 10000,
              hom_nay: { tien_mat: 20000, qr: 400000, tong: 420000, so_luot: 9 },
              tuan: { tien_mat: 0, qr: 0, tong: 0, so_luot: 0 },
              thang: { tien_mat: 100000, qr: 400000, tong: 500000, so_luot: 12 },
              tat_ca: { tien_mat: 0, qr: 0, tong: 0, so_luot: 0 } },
  quy_toi:  { ok: true, cam: QUY_DAY.toi },
  nop_tao:  { ok: true, id: 9, so_tien: 245000, so_dong: 2, thong_bao: 'Đã nộp 245.000đ' },
  nop_nhan: { ok: true, thong_bao: 'Đã nhận đủ' },
  nop_huy:  { ok: true, thong_bao: 'Đã huỷ' },
  ch_xem:   { ok: true, toi_la: 'Sếp', nguon: 'rieng', don_vi: 10000,
              nguoi: [{ i: 0, ten: 'Sếp', vai_tro: 'Admin', coso: '', pin_dai: 6 },
                      { i: 1, ten: 'Trương Tấn Hiếu', vai_tro: 'Nhân viên', coso: 'Aeon Tân Phú', pin_dai: 6 }],
              coso: ['Aeon Tân Phú', 'Nha Trang'],
              vai_tro: ['Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Cửa hàng trưởng', 'Hotline', 'Nhân viên'],
              vao: ['Admin', 'Quản lý'], chot: ['Admin'], giup: ['Admin', 'Hotline'] },
  bat: { ok: true, thong_bao: 'Đã bật' },
  tat: { ok: true, thong_bao: 'Đã tắt' },
  ma_tra: { ok: true, co_vi: 1, so_du: { dung: 0, cho: 0, con_cho: 0 }, chua_dung: [], da_dung: [] },
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
/* Bấm một nút, và NÉM LỖI THÌ ĐƯA QUA `window.onerror` — y như trình duyệt thật. Nhờ vậy dải
   báo lỗi của trang tự hiện lên, và phép thử cuối bắt được nó. */
function bam(el) {
  try { el.onclick(); }
  catch (e) { if (global.window.onerror) global.window.onerror(e.message, 'js', 0); }
}
function t(ten, dung, them) {
  if (dung) { DAT++; } else { TRUOT.push(ten + (them ? ' → ' + them : '')); }
}

const oPin = DOC.getElementById('pin'), bVao = DOC.getElementById('vao');
t('màn đăng nhập có ô PIN và nút Vào', !!oPin && !!bVao);
if (!oPin || !bVao) { xong(); }
oPin.value = '1234';
bam(bVao);
t('đăng nhập rồi thì hỏi số liệu', GOI.indexOf('so_lieu') >= 0);

/* 🔴 Người thu chỉ có tab Quỹ, và tab đó phải có lối vào chốt ca. */
const bChot = DOC.getElementById('quet-di');
t('🔴 tab Quỹ có nút Chốt', !!bChot);
t('và có ô gõ mã ghế', !!DOC.getElementById('quet-tay'));
t('và có nút quét QR', !!DOC.getElementById('quet-mo'));

if (bChot) {
  DOC.getElementById('quet-tay').value = 'AMTP01';
  const truoc = GOI.length;
  bam(bChot);
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
  bam(bOk);
  t('🔴 chưa gõ chỉ số thì KHÔNG gửi lên máy chủ', GOI.slice(truoc0).indexOf('chot_luu') < 0);
  t('và nói ra vì sao', LOG.some(l => l.indexOf('Chưa nhập chỉ số') >= 0), LOG.join(' | '));

  DOC.getElementById('chot-cs').value = '100';
  DOC.getElementById('chot-tien').value = '50000';
  const truoc = GOI.length;
  bam(bOk);
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

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LƯỢT HAI: VAI QUẢN TRỊ, MỞ QUA TẤT CẢ CÁC TAB.
 *
 * 🔴 Người thu chỉ thấy MỘT tab. Chạy mỗi vai đó là bỏ qua toàn bộ mã dựng của sáu tab còn lại
 *    — trong đó có thẻ ghế ở tab Điều khiển, đúng chỗ có lời gọi `Lf(...)` thứ hai. Một phép
 *    thử chỉ bảo vệ được những dòng nó CHẠY QUA.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
QUYEN = { quan_tri: 1, giup_khach: 1, chot_doanh_so: 1 };
const TAB_ADMIN = ['doi-soat', 'thu-tien', 'quy', 'kich-hoat', 'ma', 'dieu-khien', 'cau-hinh'];
/* Bấm ↻ để tải lại số liệu với bộ quyền mới. */
const bMoi = DOC.getElementById('lam-moi');
t('có nút tải lại', !!bMoi);
if (bMoi) bam(bMoi);

TAB_ADMIN.forEach(function (ten) {
  const nut = DOC.querySelectorAll('[data-tab="' + ten + '"]')[0];
  t('quản trị có tab ' + ten, !!nut);
  if (!nut) return;
  bam(nut);
  const dai = DOC.getElementById('bao-loi');
  t('🔴 dựng tab ' + ten + ' không ném lỗi JS nào', !dai, dai ? dai.textContent : '');
  if (dai) dai.remove();
});

/* Thẻ ghế ở tab Điều khiển phải có nút Chốt ca và ô chỉ số cũ → mới. */
const nutDk = DOC.querySelectorAll('[data-tab="dieu-khien"]')[0];
if (nutDk) {
  bam(nutDk);
  t('🔴 thẻ ghế có nút Chốt ca', DOC.querySelectorAll('[data-mat]').length > 0);
  t('và có nút Bật / Tắt', DOC.querySelectorAll('[data-bat]').length > 0
    && DOC.querySelectorAll('[data-tat]').length > 0);
}

/* Tab Cấu hình phải dựng được bảng nhân sự và ba nhóm ô tích. */
const nutCh = DOC.querySelectorAll('[data-tab="cau-hinh"]')[0];
if (nutCh) {
  bam(nutCh);
  t('🔴 tab Cấu hình có ô thêm người', !!DOC.getElementById('ch-them'));
  t('và có nút lưu phân quyền', !!DOC.getElementById('ch-luu-vt'));
  t('và có ô khai đơn vị chỉ số', !!DOC.getElementById('ch-dv'));
  t('🔴 dựng tab Cấu hình không ném lỗi', !DOC.getElementById('bao-loi'),
    DOC.getElementById('bao-loi') ? DOC.getElementById('bao-loi').textContent : '');
}

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
