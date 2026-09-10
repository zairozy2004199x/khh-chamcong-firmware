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
