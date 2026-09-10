/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TÍCH HẠNG MỤC → MỘT LỆNH TẠM ỨNG · ĐÍNH ẢNH / HỒ SƠ NGAY TRÊN HÀNG · RÊ CHUỘT PHÓNG TO BILL.
 *
 * Anh Thắng: *"cho tích để bấm xin đơn 1 lần cho nhanh"*,
 * *"cho phép thêm ảnh và đính kèm cả hồ sơ trực tiếp tại trang, tính năng rê chuột vào bill ảnh
 * tự phóng to"*.
 *
 * =============================================================================================
 * 🔴 TỔNG TIỀN LẤY TỪ Ô TÍCH, KHÔNG DÒ NGƯỢC BẢNG. Mỗi ô tích mang sẵn tiền của hạng mục
 *    (`data-tien`). Dò lại từ ô trên màn là đọc chuỗi đã có dấu chấm rồi lại phải bóc ra — thêm
 *    một chỗ để lệch, mà lệch ở đây là kế toán chuẩn bị sai số tiền.
 *
 * 🔴 GỬI ĐI SỐ DÒNG, KHÔNG GỬI SỐ TIỀN. Máy chủ tự cộng lại từ sổ. Tin con số của trình duyệt
 *    là ai sửa được HTML thì xin bao nhiêu cũng được.
 *
 * 🔴 ĐÍNH TỆP ĐỔI ĐÚNG MỘT Ô. Đi qua `updateDuAnLine` là ghi lại CẢ DÒNG: ô nào màn đọc thiếu
 *    thì bị xoá trắng, im lặng.
 *
 * 🔴 HỒ SƠ CỘNG THÊM, ẢNH THAY. Một dòng có nhiều hồ sơ nhưng một tấm bill; đính hồ sơ thứ hai
 *    mà đè mất cái thứ nhất là mất chứng từ.
 *
 * ⚠️ CHẠY THẬT các hàm bốc từ mã nguồn.
 *
 * Chạy: node tools/test/kiem-tich-xin-tam-ung.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) return '';
  const k = HTML.indexOf('\n', i);
  const mo = (HTML.slice(i, k).match(/\{/g) || []).length;
  const dong = (HTML.slice(i, k).match(/\}/g) || []).length;
  return mo === dong ? HTML.slice(i, k) : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

/* Bệ đỡ: mấy ô tích thật, mỗi ô mang tiền của hạng mục. */
function be(oTich, opt) {
  opt = opt || {};
  const NK = { gui: null, toast: [], O: {} };
  const O = {
    daXinSo: { textContent: '' }, daXinTong: { textContent: '' },
    daXinBtn: { disabled: false }, daXinBar: { style: { display: 'none' } },
    daXinForm: { style: { display: 'none' }, innerHTML: '', scrollIntoView() {} },
    daXinGhiChu: { value: opt.ghiChu || '' },
  };
  NK.O = O;
  const lich = opt.lich || {};
  const moi = {
    DA_CUR: { maDA: 'DA1', ten: 'Aeon' },
    el: id => O[id] || null,
    esc: x => String(x == null ? '' : x),
    money: x => String(x),
    toast: (k, m) => NK.toast.push([k, m]),
    loading: () => {}, _log: () => {}, openDuAn: () => {}, loadDuAn: () => {},
    _tienSo: v => { const s = String(v == null ? '' : v).replace(/[^0-9-]/g, ''); return s === '' || s === '-' ? '' : String(Number(s)); },
    document: { querySelectorAll: sel => (/\[data-xtu\]/.test(sel) ? oTich : []),
      querySelector: sel => {
        const m = sel.match(/data-(lnd|lst)="(\d)"/);
        if (!m) return null;
        const k = m[1] + m[2];
        return (k in lich) ? { value: lich[k] } : { value: '' };
      } },
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; },
      withFailureHandler() { return this; },
      xinTamUngDuAn(ma, rows, lc, gc) { NK.gui = { ma, rows, lc, gc };
        this._ok({ success: true, dot: { dot: 1 }, so: rows.length, soTien: 1 }); },
    } } },
  };
  const src = `${boc('_daOTich')}\n${boc('daTichDoi')}\n${boc('daTichHet')}
    ${boc('daXinBarDoi')}\n${boc('daMoXinTU')}\n${boc('daGuiXinTU')}
    return { doi: daTichDoi, het: daTichHet, bar: daXinBarDoi, mo: daMoXinTU, gui: daGuiXinTU };`;
  return { NK, O, F: new Function('moi', `with(moi){ ${src} }`)(moi) };
}
const o = (row, tien, checked) => ({ checked: !!checked, value: '',
  getAttribute: k => (k === 'data-tien' ? String(tien) : String(row)) });

/* ── 1. TỔNG TIỀN ─────────────────────────────────────────────────────────────────────── */
{
  const b = be([o(2, 13000000, true), o(5, 2300000, true), o(7, 50000, false)]);
  b.F.doi();
  t('🔴 tổng chỉ cộng hạng mục ĐÃ TÍCH', b.O.daXinTong.textContent === '15300000', b.O.daXinTong);
  t('   và đếm đúng số hạng mục', b.O.daXinSo.textContent === '2', b.O.daXinSo);
  t('   nút gửi mở ra khi có ít nhất một hạng mục', b.O.daXinBtn.disabled === false);
}
{
  const b = be([o(2, 13000000, false), o(5, 2300000, false)]);
  b.F.doi();
  t('🔴 chưa tích gì → tổng 0 và nút gửi KHOÁ (bấm vào là gửi một lệnh rỗng)',
    b.O.daXinTong.textContent === '0' && b.O.daXinBtn.disabled === true, b.O);
}
{
  const ds = [o(2, 13000000, false), o(5, 2300000, false)];
  const b = be(ds);
  b.F.het(true);
  t('"Tích hết" tích mọi hạng mục và cộng lại',
    ds.every(x => x.checked) && b.O.daXinTong.textContent === '15300000', b.O.daXinTong);
  b.F.het(false);
  t('"Bỏ tích" gỡ hết', ds.every(x => !x.checked) && b.O.daXinTong.textContent === '0', b.O.daXinTong);
}
{
  const b = be([]);
  b.F.bar();
  t('🔴 không có hạng mục nào còn nháp → GIẤU thanh (bày một nút chết là mời bấm vào chỗ trống)',
    b.O.daXinBar.style.display === 'none', b.O.daXinBar.style);
}
{
  const b = be([o(2, 100, false)]);
  b.F.bar();
  t('có hạng mục để tích → hiện thanh', b.O.daXinBar.style.display === 'flex', b.O.daXinBar.style);
}

/* ── 2. MỞ Ô NHẬP ─────────────────────────────────────────────────────────────────────── */
{
  const b = be([o(2, 13000000, true), o(5, 2300000, true)]);
  b.F.mo();
  const f = b.O.daXinForm.innerHTML;
  t('🔴 nói rõ cả mấy hạng mục này đi thành MỘT lệnh', /MỘT lệnh/.test(f), f);
  t('   và cho thấy số hạng mục lẫn tổng tiền', /2 hạng mục/.test(f) && /15300000/.test(f), f);
  t('   có ba dòng lịch đi nhận tiền', (f.match(/data-lnd="/g) || []).length === 3, f);
  t('   ô tiền của lịch có dấu chấm hàng nghìn', /data-lst=[^]{0,200}tienVao\(this\)/.test(f), f);
  t('   và một ô ghi chú cho kế toán', /daXinGhiChu/.test(f), f);
}
{
  const b = be([o(2, 100, false)]);
  b.F.mo();
  t('🔴 chưa tích gì mà bấm → chối, không mở ô nhập',
    b.O.daXinForm.style.display === 'none' && b.NK.toast.some(x => /Tích ít nhất/.test(x[1])), b.NK.toast);
}

/* ── 3. GỬI ĐI ────────────────────────────────────────────────────────────────────────── */
{
  const b = be([o(2, 13000000, true), o(5, 2300000, true), o(7, 50000, false)],
    { lich: { lnd1: '2026-09-12', lst1: '10.000.000', lnd3: '2026-09-20' }, ghiChu: 'Vật tư đợt đầu' });
  b.F.gui();
  t('🔴 gửi đi SỐ DÒNG của các hạng mục đã tích',
    b.NK.gui && JSON.stringify(b.NK.gui.rows) === '[2,5]', b.NK.gui);
  t('🔴 KHÔNG gửi số tiền (máy chủ tự cộng lại từ sổ — tin trình duyệt là ai cũng xin bao nhiêu cũng được)',
    b.NK.gui && !/15300000/.test(JSON.stringify(b.NK.gui)), b.NK.gui);
  t('   kèm đúng mã dự án', b.NK.gui && b.NK.gui.ma === 'DA1', b.NK.gui);
  t('🔴 lịch giữ hai dòng có ngày, bỏ dòng thiếu ngày',
    b.NK.gui && b.NK.gui.lc.length === 2 && b.NK.gui.lc[0].ngay === '2026-09-12', b.NK.gui && b.NK.gui.lc);
  t('🔴 số tiền của lịch BỎ dấu chấm trước khi gửi',
    b.NK.gui && b.NK.gui.lc[0].soTien === '10000000', b.NK.gui && b.NK.gui.lc);
  t('   và kèm ghi chú', b.NK.gui && b.NK.gui.gc === 'Vật tư đợt đầu', b.NK.gui);
  t('   gửi xong thì đóng ô nhập', b.O.daXinForm.style.display === 'none', b.O.daXinForm.style);
}
{
  const b = be([o(2, 100, false)]);
  b.F.gui();
  t('không tích gì → không gửi', b.NK.gui === null, b.NK.gui);
}

/* ── 4. 📎 ĐÍNH ẢNH / HỒ SƠ NGAY TRÊN HÀNG ────────────────────────────────────────────── */
t('🔴 hàng có nút đính ảnh', /daDinhAnh\(/.test(HTML));
t('🔴 hàng có nút đính hồ sơ', /daDinhHoSo\(/.test(HTML));
t('   và nút gỡ ảnh (đính nhầm thì phải gỡ được)', /daGoAnh\(/.test(HTML));
t('🔴 đính ảnh đi đường RIÊNG, không ghi lại cả dòng qua updateDuAnLine',
  /datAnhDuAnLine/.test(HTML) && /themHoSoDuAnLine/.test(HTML));
{
  const NK = { gui: null, tai: null, toast: [] };
  const moi = {
    DA_CUR: { maDA: 'DA1', ten: 'Aeon' },
    el: () => ({ value: '', files: [{ name: 'bill.jpg', type: 'image/jpeg' }],
                 onchange: null, click() { this.onchange(); } }),
    loading: () => {}, toast: (k, m) => NK.toast.push([k, m]), _log: () => {}, openDuAn: () => {},
    FileReader: function () {
      this.readAsDataURL = () => this.onload({ target: { result: 'data:image/jpeg;base64,QUJD' } });
    },
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; },
      withFailureHandler() { return this; },
      uploadImage(d, ma) { NK.tai = { d, ma, cach: 'anh' }; this._ok({ success: true, url: 'https://kho/bill.jpg' }); },
      uploadDuAnDoc(d, ma) { NK.tai = { d, ma, cach: 'doc' }; this._ok({ success: true, url: 'https://kho/hs.pdf' }); },
      datAnhDuAnLine(ma, row, url) { NK.gui = { ham: 'anh', ma, row, url }; this._ok({ success: true }); },
      themHoSoDuAnLine(ma, row, url) { NK.gui = { ham: 'hoso', ma, row, url }; this._ok({ success: true }); },
    } } },
  };
  const src = `${boc('_daTaiTep')}\n${boc('_daGhiTep')}\n${boc('daDinhAnh')}\n${boc('daDinhHoSo')}
    return { anh: daDinhAnh, hoso: daDinhHoSo };`;
  const F = new Function('moi', `with(moi){ ${src} }`)(moi);
  F.anh(7);
  t('🔴 đính ảnh: tải lên đúng dự án rồi ghi vào ĐÚNG DÒNG',
    NK.tai && NK.tai.ma === 'DA1' && NK.gui && NK.gui.ham === 'anh' && NK.gui.row === 7
    && NK.gui.url === 'https://kho/bill.jpg', { tai: NK.tai, gui: NK.gui });
  t('   ảnh đi đường tải ẢNH (nén, xoay đúng chiều), không đi đường tài liệu',
    NK.tai.cach === 'anh', NK.tai);
  NK.gui = null; NK.tai = null;
  F.hoso(9);
  t('🔴 đính hồ sơ: đi đường tài liệu (giữ nguyên .pdf/.docx) và cộng thêm vào dòng',
    NK.tai && NK.tai.cach === 'doc' && NK.gui && NK.gui.ham === 'hoso' && NK.gui.row === 9,
    { tai: NK.tai, gui: NK.gui });
  t('   gửi kèm TÊN TỆP (kho cần tên để giữ đuôi)', NK.tai.d.name === 'bill.jpg', NK.tai.d);
}

/* ── 4b. 🔴 DUYỆT / CẤP TIỀN NGAY TRONG TRANG DỰ ÁN ───────────────────────────────────
 * Anh Thắng: *"cho nút duyệt trực tiếp trong trang luôn"*.
 * Kế toán đang mở dự án để soi từng dòng thì bấm ngay tại chỗ, khỏi nhảy sang màn Duyệt rồi
 * phải tìm lại đúng lệnh ấy giữa danh sách của mọi dự án.
 *
 * 🔴 KHOÁ HÀNG Ô NHẬP PHẢI KHÁC MÀN DUYỆT. Hai bảng cùng nằm trong trang (một cái đang ẩn);
 *    trùng khoá là bấm bên này mở ô nhập bên kia — và người ta gõ uỷ nhiệm chi vào một ô không
 *    ai nhìn thấy.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
function veLenhDA(role, lenh) {
  const NK = {};
  const moi = {
    CURUSER: { role: role },
    LENH_ITEMS: [],
    LENH_NHAN: null,
    esc: x => String(x == null ? '' : x), money: x => String(x), _dmy: x => String(x),
    el: id => (NK[id] = NK[id] || { innerHTML: '', style: {} }),
  };
  const src = `${(() => { const i = HTML.indexOf('var LENH_NHAN='); return HTML.slice(i, HTML.indexOf('};', i) + 2); })()}
    ${boc('_hmLaKT')}\n${boc('_hmLaDuyet')}\n${boc('lenhNhan')}
    ${boc('lenhNutChung')}\n${boc('renderDaLenh')}
    return renderDaLenh;`;
  new Function('moi', `with(moi){ ${src} }`)(moi)({ maDA: 'DA1', ten: 'Aeon', lenh: lenh });
  return { html: NK.daLenhBody.innerHTML, box: NK.daLenhBox };
}
const L1 = [
  { dot: 1, tt: 'xin', rows: [2, 5], tenHM: ['Mua đồ điện', 'Thợ bốc vác'], soTien: 15300000,
    unc: '', lyDo: '', lich: [], moc: { xin: '10/09 · NV' } },
  { dot: 2, tt: 'duyet', rows: [7], tenHM: ['Đơn linh tinh'], soTien: 50000,
    unc: '', lyDo: '', lich: [], moc: {} },
  { dot: 3, tt: 'ung', rows: [9], tenHM: ['Xe cẩu'], soTien: 900000,
    unc: 'UNC-9', lyDo: '', lich: [], moc: {} },
];
{
  const r = veLenhDA('Kế toán cá nhân', L1);
  t('🔴 lệnh chờ duyệt → duyệt được NGAY TRONG TRANG dự án',
    /lenhDat\('DA1',1,'duyet',\{\},_lenhSau\('QDA1_1'\)\)/.test(r.html), r.html.slice(0, 400));
  t('🔴 lệnh đã duyệt → cấp tiền được ngay tại đây',
    /lenhMoCap\('QDA1_2','DA1',2\)/.test(r.html), r.html);
  t('   trả lại được, kèm khoá của trang dự án', /lenhTra\('DA1',1,'QDA1_1'\)/.test(r.html), r.html);
  t('🔴 khoá hàng ô nhập mang tiền tố Q — KHÁC màn Duyệt (tiền tố L)',
    /data-hmf="QDA1_1"/.test(r.html) && !/data-hmf="LDA1_1"/.test(r.html), r.html);
  t('   hàng ô nhập trải hết 7 cột của bảng này', /data-hmf="QDA1_1"[^]{0,60}colspan="7"/.test(r.html), r.html);
  t('🔴 lệnh đã cấp tiền → không bày nút gì nữa', !/QDA1_3/.test(r.html.replace(/data-hmf="QDA1_3"/, '')), r.html);
  t('   và bảng hiện ra khi có lệnh', r.box.style.display === '', r.box.style);
}
{
  const r = veLenhDA('Nhân viên', L1);
  t('🔴 nhân viên mở dự án của mình cũng KHÔNG duyệt / cấp tiền được',
    !/lenhDat\(/.test(r.html) && !/lenhMoCap/.test(r.html) && !/lenhTra/.test(r.html), r.html);
  t('   nhưng vẫn thấy đợt nào tới đâu',
    /Chờ duyệt/.test(r.html) && /Đã cấp tiền/.test(r.html) && /UNC-9/.test(r.html), r.html);
}
{
  const r = veLenhDA('Quản lý', L1);
  t('🔴 quản lý duyệt và trả được, nhưng KHÔNG cấp tiền',
    /lenhDat\('DA1',1,'duyet'/.test(r.html) && /lenhTra/.test(r.html) && !/lenhMoCap/.test(r.html), r.html);
}
{
  const r = veLenhDA('Kế toán cá nhân', []);
  t('chưa có lệnh nào → giấu cả bảng', r.box.style.display === 'none', r.box.style);
}
t('🔴 hai màn dùng CHUNG một hàm dựng nút (viết hai bộ là hai nơi lệch nhau)',
  (HTML.match(/lenhNutChung\(/g) || []).length >= 3);
t('   và nạp lại đúng màn đang đứng sau khi bấm',
  HTML.indexOf("function _lenhSau(k){ return (String(k).charAt(0)==='Q')") >= 0);
t('🔴 bảng hạng mục của màn Duyệt không nổ khi chưa mở màn ấy',
  HTML.indexOf("if(!el('hmBody')) return;") >= 0);

/* ── 4c. 💵 THẺ BA CON SỐ TẠM ỨNG ─────────────────────────────────────────────────────
 * Anh Thắng: *"chỗ này sẽ hiện (Số tiền đã xin tạm ứng / Số tiền kế toán đã chi tạm ứng)"* và
 * *"Dự kiến tạm ứng tổng đơn"*.
 * Ô "Tổng dự toán" cũ đứng đó với con số 0đ suốt vì phần lớn dự án chẳng ai gõ dự toán — một ô
 * to đùng không nói được gì.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
{
  const moi = { money: x => String(x) };
  const the = new Function('moi', `with(moi){ ${boc('_tqCardTU')}\n return _tqCardTU; }`)(moi);
  const h = the({ duKienTU: 25410999, daXinTU: 25299999, daChiTU: 15300000, tongDuToan: 0 }, 0);
  t('🔴 hiện đủ ba nhãn anh Thắng gọi tên',
    /Dự kiến tạm ứng tổng đơn/.test(h) && /Số tiền đã xin tạm ứng/.test(h)
    && /Số tiền kế toán đã chi tạm ứng/.test(h), h);
  /* 🔴 CANH CẶP NHÃN–SỐ, không canh rời. Ba con số cùng có mặt mà đổi chỗ cho nhau thì phép
     "có đủ ba số" vẫn xanh — và kế toán đọc "đã chi 25 triệu" trong khi mới chi 15. */
  const cap = (nhan) => {
    const i = h.indexOf(nhan);
    return i < 0 ? '' : (h.slice(i, i + 220).match(/>([\d]+)đ</) || [])[1];
  };
  t('🔴 mỗi nhãn đi với ĐÚNG con số của nó', cap('Dự kiến tạm ứng tổng đơn') === '25410999', cap('Dự kiến tạm ứng tổng đơn'));
  t('   "đã xin" là 25.299.999', cap('Số tiền đã xin tạm ứng') === '25299999', cap('Số tiền đã xin tạm ứng'));
  t('   "đã chi" là 15.300.000', cap('Số tiền kế toán đã chi tạm ứng') === '15300000', cap('Số tiền kế toán đã chi tạm ứng'));
  t('🔴 nói rõ còn bao nhiêu chưa gửi xin', /còn <b>111000đ<\/b> chưa gửi xin/.test(h), h);
  t('   dự toán không mất hẳn, lui xuống dòng phụ', /dự toán 0đ/.test(h), h);
}
{
  const moi = { money: x => String(x) };
  const the = new Function('moi', `with(moi){ ${boc('_tqCardTU')}\n return _tqCardTU; }`)(moi);
  const h = the({ duKienTU: 10000000, daXinTU: 12000000, daChiTU: 0, tongDuToan: 0 }, 0);
  /* 🔴 Xin nhiều hơn dự kiến là chuyện có thật (phát sinh thêm sau khi gửi). Bày
     "-2.000.000đ còn phải xin" thì đọc ra vô nghĩa — phải nói thẳng là đã xin vượt. */
  t('🔴 xin VƯỢT dự kiến → nói thẳng là vượt, không in số âm',
    /đã xin vượt dự kiến <b>2000000đ<\/b>/.test(h) && !/-2000000/.test(h), h);
}
{
  const moi = { money: x => String(x) };
  const the = new Function('moi', `with(moi){ ${boc('_tqCardTU')}\n return _tqCardTU; }`)(moi);
  const h = the({ duKienTU: 5000000, daXinTU: 5000000, daChiTU: 5000000, tongDuToan: 0 }, 0);
  t('xin hết rồi → báo đã gửi xong', /đã gửi xin hết/.test(h), h);
}
t('🔴 thẻ này thay chỗ ô "Tổng dự toán" cũ', HTML.indexOf("_tqCardTU(r, hmDT)+") >= 0);

/* ── 5. 🔍 RÊ CHUỘT VÀO BILL THÌ PHÓNG TO ─────────────────────────────────────────────── */
/* Lớp phủ đã có sẵn (`_billZoomInit`) và nghe ở `document` theo thuộc tính `data-bill`; bảng dự
   án trước nay chỉ có thẻ 📷 trơn nên rê chuột chẳng ra gì. */
{
  const i = HTML.indexOf('function daLineCells(');
  const fn = HTML.slice(i, HTML.indexOf('\n    }', i) + 6);
  t('bốc được daLineCells()', i >= 0 && fn.length > 400, fn.length);
  t('🔴 ảnh trong bảng dự án mang data-bill → rê chuột là phóng to',
    /data-bill="'\+esc\(l\.anh\)\+'"/.test(fn), fn.slice(0, 600));
  t('🔴 hồ sơ cũng vậy (bản chụp hoá đơn hay nằm ở hồ sơ)',
    /data-bill="'\+esc\(u\)\+'"/.test(fn), fn.slice(0, 900));
}
t('   và lớp phủ phóng to vẫn nghe ở document, không gắn vào từng thẻ',
  /document\.addEventListener\('mouseover'/.test(HTML));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: tích nhiều hạng mục gửi một lệnh, đính tệp ngay trên hàng.');
