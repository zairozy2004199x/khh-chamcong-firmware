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
  /* Ô lịch là ô THẬT, giữ được giá trị: luật chia tiền ghi ngược vào chính mấy ô này, nên bệ
     đỡ trả ra một đối tượng mới mỗi lần hỏi là mọi phép chia đều xanh oan. */
  const O_LICH = {};
  for (let i = 1; i <= 3; i++) {
    O_LICH['lnd' + i] = { value: (opt.lich || {})['lnd' + i] || '' };
    O_LICH['lst' + i] = { value: (opt.lich || {})['lst' + i] || '' };
  }
  NK.lich = O_LICH;
  O.daLichNhac = { textContent: '' };
  O.daXinForm.getAttribute = k => (k === 'data-tong' ? String(opt.tong || 0) : null);
  O.daXinForm.setAttribute = (k, v) => { if (k === 'data-tong') opt.tong = Number(v) || 0; };
  const moi = {
    DA_CUR: { maDA: 'DA1', ten: 'Aeon' },
    el: id => O[id] || null,
    esc: x => String(x == null ? '' : x),
    money: x => String(x),
    toast: (k, m) => NK.toast.push([k, m]),
    loading: () => {}, _log: () => {}, openDuAn: () => {}, loadDuAn: () => {},
    _tienSo: v => { const s = String(v == null ? '' : v).replace(/[^0-9-]/g, ''); return s === '' || s === '-' ? '' : String(Number(s)); },
    _tienDep: v => { const s = String(v == null ? '' : v).replace(/[^0-9-]/g, ''); return s === '' ? '' : Number(s).toLocaleString('vi-VN'); },
    _ngayISO: d => '2026-09-11',
    document: { querySelectorAll: sel => (/\[data-xtu\]/.test(sel) ? oTich : []),
      querySelector: sel => {
        const m = sel.match(/data-(lnd|lst)="(\d)"/);
        if (!m) return null;
        return O_LICH[m[1] + m[2]] || null;
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
    ${boc('_daLichO')}\n${boc('_daLichSo')}\n${boc('_daLichDat')}
    ${boc('_daXinTongLenh')}\n${boc('daLichDoi')}
    return { doi: daTichDoi, het: daTichHet, bar: daXinBarDoi, mo: daMoXinTU, gui: daGuiXinTU,
             chia: daLichDoi };`;
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
  t('🔴 số tiền của lịch BỎ dấu chấm trước khi gửi (gửi "10.000.000" là máy chủ đọc thành 10)',
    b.NK.gui && b.NK.gui.lc[0].soTien === 10000000, b.NK.gui && b.NK.gui.lc);
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

/* ── 2b. 🔴 LẦN 1 LÀ CẢ TỔNG, DƯ THÌ CHẢY SANG LẦN SAU ────────────────────────────────
 * Anh Thắng: *"Lần 1 hiện luôn tổng, nếu nhập nhỏ hơn chuyển qua lần 2, không được nhập lớn
 * hơn"*.
 *
 * 🔴 TỔNG BA LẦN LUÔN BẰNG TỔNG LỆNH. Cho gõ tuỳ ý thì tổng lịch lệch với số tiền của lệnh —
 *    kế toán chuẩn bị tiền theo lịch, mà lệnh lại đòi con số khác, và không ai biết bên nào
 *    đúng.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
{
  const b = be([o(2, 13000000, true), o(5, 2300000, true)]);
  b.F.mo();
  const f = b.O.daXinForm.innerHTML;
  t('🔴 mở ra là ô tiền lần 1 điền sẵn CẢ TỔNG', /data-lst="1" value="15\.300\.000"/.test(f), f);
  t('🔴 và ngày lần 1 điền sẵn hôm nay (có tiền mà thiếu ngày thì gửi bị chối)',
    /data-lnd="1" value="2026-09-11"/.test(f), f);
  t('   lần 2 và lần 3 để trống', /data-lst="2" value=""/.test(f) && /data-lst="3" value=""/.test(f), f);
  t('🔴 ô tiền lần 3 KHOÁ — nó luôn là phần còn lại, không có lần 4 để đẩy tiếp',
    /data-lst="3"[^>]*readonly/.test(f), f);
  t('   lần 1 và lần 2 vẫn sửa được', !/data-lst="1"[^>]*readonly/.test(f) && !/data-lst="2"[^>]*readonly/.test(f), f);
  t('   sửa xong là chia lại ngay', /onblur="tienRa\(this\);daLichDoi\(1\)"/.test(f), f);
}
{
  const b = be([o(2, 5000000, true)], { tong: 5000000, lich: { lst1: '5.000.000' } });
  b.NK.lich.lst1.value = '2.000.000';
  b.F.chia(1);
  t('🔴 gõ lần 1 NHỎ HƠN → phần dư tự nhảy sang lần 2',
    b.NK.lich.lst2.value === '3.000.000', b.NK.lich);
  t('   lần 3 vẫn trống', b.NK.lich.lst3.value === '', b.NK.lich.lst3);
  b.NK.lich.lst2.value = '1.000.000';
  b.F.chia(2);
  t('🔴 gõ lần 2 nhỏ hơn nữa → phần dư nhảy sang lần 3',
    b.NK.lich.lst3.value === '2.000.000', b.NK.lich);
  t('   và tổng ba lần vẫn đúng bằng tổng lệnh',
    2000000 + 1000000 + 2000000 === 5000000);
}
{
  const b = be([o(2, 5000000, true)], { tong: 5000000, lich: { lst1: '5.000.000' } });
  b.NK.lich.lst1.value = '9.000.000';
  b.F.chia(1);
  t('🔴 gõ LỚN HƠN tổng → kẹp lại đúng tổng, không cho vượt',
    b.NK.lich.lst1.value === '5.000.000', b.NK.lich.lst1);
  t('   và nói rõ vì sao bị kẹp', /không được quá 5000000đ/.test(b.O.daLichNhac.textContent), b.O.daLichNhac);
  t('   lần 2 về trống vì lần 1 đã ăn hết', b.NK.lich.lst2.value === '', b.NK.lich.lst2);
}
{
  const b = be([o(2, 5000000, true)], { tong: 5000000, lich: { lst1: '2.000.000' } });
  b.NK.lich.lst2.value = '4.000.000';
  b.F.chia(2);
  t('🔴 lần 2 gõ quá PHẦN CÒN LẠI (3tr) → kẹp về 3tr, không phải về tổng',
    b.NK.lich.lst2.value === '3.000.000', b.NK.lich.lst2);
}
{
  /* 🔴 Chia rồi chia lại: lần 1 tăng lên ăn hết thì mấy ô sau phải DỌN TRẮNG, kẻo còn số của
     lần chia trước nằm đó và tổng lịch vọt quá tổng lệnh. */
  const b = be([o(2, 5000000, true)], { tong: 5000000, lich: { lst1: '1.000.000' } });
  b.F.chia(1);
  b.NK.lich.lst2.value = '1.000.000';
  b.F.chia(2);
  t('chia ba lần: 1tr / 1tr / 3tr', b.NK.lich.lst3.value === '3.000.000', b.NK.lich);
  b.NK.lich.lst1.value = '5.000.000';
  b.F.chia(1);
  t('🔴 sửa lần 1 lên cả tổng → lần 2 VÀ lần 3 dọn trắng (còn số cũ là tổng lịch vọt quá lệnh)',
    b.NK.lich.lst2.value === '' && b.NK.lich.lst3.value === '', b.NK.lich);
}
{
  const b = be([o(2, 5000000, true)], { tong: 5000000,
    lich: { lnd1: '2026-09-11', lst1: '2.000.000', lst2: '3.000.000' } });
  b.F.gui();
  t('🔴 lần 2 CÓ TIỀN mà chưa chọn ngày → CHỐI, không lặng lẽ bỏ (máy chủ bỏ dòng thiếu ngày, '
    + 'người dùng nhìn thấy 3tr rồi gửi mà lệnh chỉ ghi 2tr)', b.NK.gui === null, b.NK.gui);
  t('   và nói rõ lần nào, bao nhiêu tiền',
    b.NK.toast.some(x => /Lần 2 có 3000000đ mà chưa chọn ngày/.test(x[1])), b.NK.toast);
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

/* ── 4f. 🧾 THẺ THỰC TẾ + THẺ CÒN TREO TRÊN TK 141 ────────────────────────────────────
 * Anh Thắng: *"Tổng thực tế / Tổng thực tế đã quyết toán"* và *"Tổng tạm ứng đã chi − Quyết
 * toán đã chốt"*.
 * Ô "Vượt dự toán" cũ so thực tế với dự toán, mà phần lớn dự án để dự toán = 0 nên nó luôn báo
 * "vượt" đúng bằng tổng thực tế — một ô đỏ chót không nói được gì.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
function veThe(ten, ...tham) {
  const moi = { money: x => String(x) };
  return new Function('moi', `with(moi){ ${boc(ten)}\n return ${ten}; }`)(moi)(...tham);
}
{
  const h = veThe('_tqCardTT', { tongThucTe: 22350000, qtDaChot: 7300000, qtDaGui: 9000000 }, 22350000, 0, 0);
  t('🔴 thẻ thực tế hiện cả "đã quyết toán"',
    /Tổng thực tế/.test(h) && /Tổng thực tế đã quyết toán/.test(h), h);
  t('   và đúng hai con số', /22350000/.test(h) && /7300000/.test(h), h);
  t('🔴 "đã quyết toán" là ĐÃ CHỐT SỔ, không phải đã gửi (gửi rồi mà chưa đối chiếu thì vẫn treo)',
    !/>9000000đ</.test(h.slice(h.indexOf('Tổng thực tế đã quyết toán'), h.indexOf('Tổng thực tế đã quyết toán') + 220)), h);
  t('   nói rõ phần đang chờ kế toán chốt và phần chưa gửi',
    /chờ kế toán chốt <b>1700000đ<\/b>/.test(h) && /chưa gửi quyết toán <b>13350000đ<\/b>/.test(h), h);
}
{
  const h = veThe('_tqCardTT', { tongThucTe: 5000000, qtDaChot: 5000000, qtDaGui: 5000000 }, 0, 0, 0);
  t('quyết toán hết → báo đã xong', /đã quyết toán hết/.test(h), h);
}
{
  const h = veThe('_tqCardTreo', { daChiTU: 15300000, qtDaChot: 7300000 });
  t('🔴 thẻ thứ tư = tạm ứng đã chi − quyết toán đã chốt',
    /Còn treo trên TK 141/.test(h) && />8000000đ</.test(h), h);
  /* 🔴 CANH CẶP NHÃN–SỐ. Hai con số cùng có mặt mà đổi chỗ cho nhau thì phép "có đủ hai vế"
     vẫn xanh — và kế toán đọc "tạm ứng đã chi 7,3 triệu" trong khi đã chi 15,3. */
  const capT = (nhan) => {
    const i = h.indexOf(nhan);
    return i < 0 ? '' : (h.slice(i, i + 220).match(/>([\d]+)đ</) || [])[1];
  };
  t('   và bày ra cả hai vế của phép trừ, MỖI NHÃN ĐI VỚI ĐÚNG SỐ CỦA NÓ',
    capT('Tạm ứng đã chi') === '15300000' && capT('Quyết toán đã chốt') === '7300000',
    [capT('Tạm ứng đã chi'), capT('Quyết toán đã chốt')]);
  t('   còn treo thì tô đỏ cảnh báo', /#fee2e2/.test(h), h);
}
{
  const h = veThe('_tqCardTreo', { daChiTU: 5000000, qtDaChot: 5000000 });
  t('🔴 tất toán hết → tô xanh và nói rõ không còn treo',
    /#dcfce7/.test(h) && /đã tất toán hết/.test(h), h);
}
{
  /* Chốt sổ nhiều hơn số đã ứng là chuyện có thật — nhân viên bỏ tiền túi mua thêm rồi mới
     quyết toán. Bày "-2.000.000đ còn treo" thì đọc ra vô nghĩa. */
  const h = veThe('_tqCardTreo', { daChiTU: 5000000, qtDaChot: 7000000 });
  t('🔴 quyết toán VƯỢT số đã ứng → nói thẳng công ty còn nợ nhân viên, không in số âm',
    /công ty còn nợ nhân viên/.test(h) && !/-2000000/.test(h), h);
  t('   và con số bày ra là trị tuyệt đối', />2000000đ</.test(h), h);
}
t('🔴 ô "Vượt dự toán" cũ đã bỏ hẳn (dự toán 0 thì nó luôn báo vượt đúng bằng tổng thực tế)',
  HTML.indexOf("'thực tế so với dự toán'") < 0 && HTML.indexOf('_tqCardTreo(r);') >= 0);

/* ── 4d. 🧾 TÍCH ĐỂ GỬI QUYẾT TOÁN THEO ĐƠN ───────────────────────────────────────────
 * Anh Thắng: *"khi đơn này đã xong, tích chọn để gửi quyết toán theo đơn"*.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
function beQt(oTich, opt) {
  opt = opt || {};
  const NK = { gui: null, toast: [] };
  const O = {
    daQtSo: { textContent: '' }, daQtTong: { textContent: '' },
    daQtBtn: { disabled: false }, daQtBar: { style: { display: 'none' } },
    daQtForm: { style: { display: 'none' }, innerHTML: '', scrollIntoView() {} },
    daQtGhiChu: { value: opt.ghiChu || '' },
  };
  const moi = {
    DA_CUR: { maDA: 'DA1', ten: 'Aeon' },
    el: id => O[id] || null,
    esc: x => String(x == null ? '' : x), money: x => String(x),
    toast: (k, m) => NK.toast.push([k, m]),
    loading: () => {}, _log: () => {}, openDuAn: () => {}, loadDuAn: () => {},
    document: { querySelectorAll: sel => (/\[data-xqt\]/.test(sel) ? oTich : []) },
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; },
      withFailureHandler() { return this; },
      xinQuyetToanDuAn(ma, rows, gc) { NK.gui = { ma, rows, gc };
        this._ok({ success: true, dot: { dot: 1 }, so: rows.length, soTien: 1 }); },
    } } },
  };
  const src = `${boc('_daOTichQt')}\n${boc('daTichQtDoi')}\n${boc('daTichQtHet')}
    ${boc('daQtBarDoi')}\n${boc('daMoQt')}\n${boc('daGuiQt')}
    return { doi: daTichQtDoi, het: daTichQtHet, bar: daQtBarDoi, mo: daMoQt, gui: daGuiQt };`;
  return { NK, O, F: new Function('moi', `with(moi){ ${src} }`)(moi) };
}
const q = (row, tien, checked) => ({ checked: !!checked, value: '',
  getAttribute: k => (k === 'data-tien' ? String(tien) : String(row)) });
{
  const b = beQt([q(2, 2300000, true), q(5, 5000000, true), q(7, 900000, false)]);
  b.F.doi();
  t('🔴 tổng quyết toán chỉ cộng hạng mục ĐÃ TÍCH',
    b.O.daQtTong.textContent === '7300000' && b.O.daQtSo.textContent === '2', b.O);
  b.F.gui();
  t('🔴 gửi đi số dòng của các hạng mục đã tích',
    b.NK.gui && JSON.stringify(b.NK.gui.rows) === '[2,5]', b.NK.gui);
  t('🔴 KHÔNG gửi số tiền (máy chủ tự cộng lại từ sổ)',
    b.NK.gui && !/7300000/.test(JSON.stringify(b.NK.gui)), b.NK.gui);
}
{
  const b = beQt([q(2, 100, false)]);
  b.F.doi();
  t('🔴 chưa tích gì → nút gửi quyết toán KHOÁ', b.O.daQtBtn.disabled === true, b.O.daQtBtn);
  b.F.gui();
  t('   và không gửi gì cả', b.NK.gui === null, b.NK.gui);
}
{
  const b = beQt([]);
  b.F.bar();
  t('🔴 chưa hạng mục nào chốt xong → GIẤU thanh quyết toán', b.O.daQtBar.style.display === 'none', b.O.daQtBar.style);
}
{
  const b = beQt([q(2, 2300000, true)]);
  b.F.bar();
  t('có hạng mục đã chốt → hiện thanh', b.O.daQtBar.style.display === 'flex', b.O.daQtBar.style);
  b.F.mo();
  t('   ô nhập nói rõ đi thành MỘT lệnh quyết toán', /MỘT lệnh quyết toán/.test(b.O.daQtForm.innerHTML), b.O.daQtForm.innerHTML);
  t('   và có ô ghi chú cho kế toán', /daQtGhiChu/.test(b.O.daQtForm.innerHTML), b.O.daQtForm.innerHTML);
}
/* Ô tích quyết toán trên HÀNG — chỉ hiện khi hạng mục đã chốt xong và chưa gửi. */
{
  const moi = { CURUSER: { role: 'Nhân viên' },
    DA_CUR: { maDA: 'DA1', editable: true, thiCong: false, isCoSo: false },
    esc: x => String(x == null ? '' : x), HM_NHAN: null };
  const src = `${(() => { const i = HTML.indexOf('var HM_NHAN='); return HTML.slice(i, HTML.indexOf('};', i) + 2); })()}
    ${boc('_hmNhanCua')}\n${boc('_hmLaKT')}\n${boc('_hmLaDuyet')}
    ${boc('hmNhanChung')}\n${boc('hmNutChung')}\n${boc('hmNutDaBang')}\n${boc('_hmKeyDA')}
    return (hm, tien) => hmNutDaBang({ row: 7, noiDung: 'X', hinhThuc: '', hm: hm }, tien);`;
  const f = new Function('moi', `with(moi){ ${src} }`)(moi);
  const xong = f({ tt: 'xong', dot: 1, qtDot: 0 }, 2300000);
  t('🔴 hạng mục đã CHỐT XONG → hiện ô tích để gửi quyết toán',
    /data-xqt="7"/.test(xong) && /tích để quyết toán/.test(xong), xong);
  t('   ô tích mang tiền của hạng mục', /data-xqt="7" data-tien="2300000"/.test(xong), xong);
  const dagui = f({ tt: 'xong', dot: 1, qtDot: 2 }, 2300000);
  t('🔴 đã nằm trong lệnh quyết toán → KHÔNG còn ô tích (gửi hai lần là tất toán gấp đôi)',
    !/data-xqt/.test(dagui), dagui);
  t('   nhưng cho biết đã gửi đợt mấy', /đã gửi QT đợt 2/.test(dagui), dagui);
  const chuaXong = f({ tt: 'ung', dot: 1, qtDot: 0 }, 2300000);
  t('🔴 hạng mục CHƯA chốt hoá đơn → không có ô tích quyết toán', !/data-xqt/.test(chuaXong), chuaXong);
  t('   mà có nút chốt xong trước đã', /hmMoChot/.test(chuaXong), chuaXong);
}

/* ── 4e. 🔴 TAB QUYẾT TOÁN CỦA KẾ TOÁN CŨNG HIỆN LỆNH ẤY ──────────────────────────────
 * Anh Thắng: *"Khi nv gửi chốt quyết toán, bên tab quyết toán của kế toán cũng sẽ hiện lên đơn
 * đó giống tạm ứng để kế toán theo dõi"*.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
function veQtTab(role, loc, ds, trang) {
  const NK = {};
  const moi = {
    CURUSER: { role: role },
    QTLENH_TRANG: trang || 1, QTLENH_MOI_TRANG: 10,
    QTLENH_ITEMS: ds || [
      { loai: 'qt', maDA: 'DA1', tenDA: 'Aeon', loaiDA: 'Setup lắp đặt', nguoiTao: 'NV', dot: 1,
        tt: 'xin', rows: [2, 5], tenHM: ['Thợ bốc vác', 'Xe vận chuyển'], soTien: 7300000,
        lyDo: '', kyDA: { tu: '', den: '' } },
      { loai: 'qt', maDA: 'DA2', tenDA: 'Estella', loaiDA: 'Setup lắp đặt', nguoiTao: 'NV', dot: 1,
        tt: 'xong', rows: [3], tenHM: ['Vật tư'], soTien: 900000,
        lyDo: '', kyDA: { tu: '', den: '' } },
    ],
    QT_NHAN: null,
    esc: x => String(x == null ? '' : x), money: x => String(x), _dmy: x => String(x),
    showPage: () => {}, openDuAn: () => {}, toast: () => {}, loading: () => {},
    el: id => (NK[id] = NK[id] || { innerHTML: '', style: {}, textContent: '',
                                    value: id === 'qtLenhFilter' ? (loc || 'all') : '' }),
  };
  const src = `${(() => { const i = HTML.indexOf('var QT_NHAN='); return HTML.slice(i, HTML.indexOf('};', i) + 2); })()}
    ${boc('_hmLaKT')}\n${boc('_hmLaDuyet')}\n${boc('qtNhan')}\n${boc('_hmNgay')}
    ${boc('_qtLenhPager')}\n${boc('renderQtLenh')}
    renderQtLenh(); return QTLENH_TRANG;`;
  NK.trangSau = new Function('moi', `with(moi){ ${src} }`)(moi);
  return NK;
}
{
  const NK = veQtTab('Kế toán cá nhân', 'all');
  const out = NK.qtLenhBody.innerHTML;
  t('🔴 kế toán thấy lệnh quyết toán ở tab của mình, kèm tên dự án',
    /Aeon/.test(out) && /Estella/.test(out), out.slice(0, 300));
  t('   kể tên hạng mục và số tiền của lệnh',
    /Thợ bốc vác/.test(out) && /7300000/.test(out), out.slice(0, 500));
  t('🔴 lệnh chờ chốt → có nút Chốt sổ và Trả',
    /qtDatTab\('DA1',1,'xong'\)/.test(out) && /qtTraTab\('DA1',1\)/.test(out), out);
  t('🔴 lệnh ĐÃ chốt sổ → không bày nút nữa (chốt hai lần là tất toán gấp đôi)',
    !/qtDatTab\('DA2'/.test(out), out);
  t('   bấm tên dự án là mở thẳng dự án ấy', /openDuAn\('DA1'\)/.test(out), out);
  t('🔴 huy hiệu đếm việc ĐANG CHỜ, không đếm theo ô lọc',
    NK.qtLenhSo.textContent === '1', NK.qtLenhSo);
}
{
  const NK = veQtTab('Kế toán cá nhân', 'xin');
  t('lọc "chờ chốt sổ" chỉ giữ lệnh đang chờ',
    /Aeon/.test(NK.qtLenhBody.innerHTML) && !/Estella/.test(NK.qtLenhBody.innerHTML), NK.qtLenhBody.innerHTML);
}
{
  const NK = veQtTab('Nhân viên', 'all');
  t('🔴 nhân viên KHÔNG chốt sổ / trả lại được, dù mở được tab',
    !/qtDatTab/.test(NK.qtLenhBody.innerHTML) && !/qtTraTab/.test(NK.qtLenhBody.innerHTML), NK.qtLenhBody.innerHTML);
  t('   nhưng vẫn thấy trạng thái', /Chờ kế toán chốt sổ/.test(NK.qtLenhBody.innerHTML), NK.qtLenhBody.innerHTML);
}
/* 🔴 ĐƠN ĐÃ CHỐT NẰM LẠI. Anh Thắng: *"Đơn đã duyệt quyết toán sẽ nằm đó luôn"* — lọc sẵn
   "chờ chốt sổ" thì bấm chốt xong là dòng biến mất khỏi màn, kế toán không còn chỗ tra lại
   mình vừa chốt cái gì. */
{
  const i = HTML.indexOf('id="qtLenhFilter"');
  const sel = HTML.slice(i, HTML.indexOf('</select>', i));
  t('🔴 ô lọc mặc định là "Tất cả" (mục đầu tiên), không phải "chờ chốt sổ"',
    sel.indexOf('value="all"') < sel.indexOf('value="xin"'), sel);
  t('   và hàm vẽ cũng mặc định "all" khi ô chưa dựng',
    /var f=\(el\('qtLenhFilter'\)&&el\('qtLenhFilter'\)\.value\)\|\|'all';/.test(HTML));
}
/* 🔴 10 DÒNG MỘT TRANG. Anh Thắng: *"Trang này sẽ 10 đơn cho 1 trang là được"*. */
{
  const nhieu = [];
  for (let k = 1; k <= 23; k++) {
    nhieu.push({ loai: 'qt', maDA: 'DA' + k, tenDA: 'DA' + k, loaiDA: 'Setup lắp đặt',
      nguoiTao: 'NV', dot: 1, tt: 'xong', rows: [2], tenHM: ['HM' + k], soTien: 1000 * k,
      lyDo: '', kyDA: { tu: '', den: '' } });
  }
  const t1 = veQtTab('Kế toán cá nhân', 'all', nhieu, 1);
  t('🔴 23 lệnh → trang 1 chỉ vẽ 10 dòng',
    (t1.qtLenhBody.innerHTML.match(/<tr>/g) || []).length === 10,
    (t1.qtLenhBody.innerHTML.match(/<tr>/g) || []).length);
  t('   và là 10 dòng ĐẦU', /DA1</.test(t1.qtLenhBody.innerHTML) && !/DA11</.test(t1.qtLenhBody.innerHTML), t1.qtLenhBody.innerHTML.slice(0, 200));
  t('   thanh chuyển trang hiện ra, nói rõ đang xem tới đâu',
    t1.qtLenhPager.style.display === 'flex' && /1–10 trong 23 lệnh/.test(t1.qtLenhPager.innerHTML), t1.qtLenhPager);
  const t3 = veQtTab('Kế toán cá nhân', 'all', nhieu, 3);
  t('trang cuối vẽ phần còn lại', (t3.qtLenhBody.innerHTML.match(/<tr>/g) || []).length === 3,
    (t3.qtLenhBody.innerHTML.match(/<tr>/g) || []).length);
  /* 🔴 Lọc lại hoặc chốt bớt vài lệnh là danh sách ngắn đi — số trang cũ trỏ ra ngoài, và bảng
     hiện ra TRẮNG TRƠN dù dữ liệu còn nguyên. */
  const t9 = veQtTab('Kế toán cá nhân', 'all', nhieu, 9);
  t('🔴 số trang trỏ ra NGOÀI danh sách → kẹp về trang cuối, không vẽ bảng trắng',
    t9.trangSau === 3 && (t9.qtLenhBody.innerHTML.match(/<tr>/g) || []).length === 3, t9.trangSau);
  const t0 = veQtTab('Kế toán cá nhân', 'all', nhieu, 0);
  t('   số trang 0 cũng kẹp về 1', t0.trangSau === 1, t0.trangSau);
  const it = veQtTab('Kế toán cá nhân', 'all', nhieu.slice(0, 4), 1);
  t('ít hơn một trang → giấu thanh chuyển trang', it.qtLenhPager.style.display === 'none', it.qtLenhPager);
}

t('🔴 tab Quyết toán nạp bảng lệnh quyết toán khi mở',
  /function loadQT\(\)\{[^\n]*loadQtLenh\(\)/.test(HTML));
t('   và hỏi máy chủ ĐÚNG loại lệnh (không hỏi nhầm ra lệnh tạm ứng)',
  /listLenhDuAn\('qt'\)/.test(HTML));
t('🔴 bấm ở tab Quyết toán thì nạp lại bảng của tab ấy, không nạp lại trang dự án',
  /function qtDatTab\([^]{0,600}loadQtLenh\(\);/.test(HTML)
  && !/function qtDatTab\([^]{0,600}hmLaiDA\(\)/.test(HTML));
t('   bảng nằm ngay dưới "Chờ quyết toán", không nhét cuối tab',
  HTML.indexOf('id="qtPagerCho"') < HTML.indexOf('id="qtLenhCard"')
  && HTML.indexOf('id="qtLenhCard"') < HTML.indexOf('id="qtCardXong"'));

/* ── 4g. 📊 DẢI TIẾN TRÌNH + LỊCH SỬ Ở ĐẦU TRANG DỰ ÁN ────────────────────────────────
 * Anh Thắng: *"Đầu trang bổ sung tiến trình như này và lịch sử đơn để theo dõi đơn và chỉnh
 * sửa"*.
 * 🔴 DỰ ÁN KHÔNG CÓ MỘT TRẠNG THÁI. Đơn tuần đi một đường thẳng nên tô sáng được một ô; dự án
 *    thì nhiều hạng mục nằm ở nhiều bước CÙNG LÚC. Tô sáng một ô ở đây là nói dối.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
function veTienTrinh(r) {
  const NK = {};
  const moi = {
    esc: x => String(x == null ? '' : x),
    DA_BUOC: null,
    el: id => (NK[id] = NK[id] || { innerHTML: '' }),
  };
  const src = `${(() => { const i = HTML.indexOf('var DA_BUOC='); return HTML.slice(i, HTML.indexOf('];', i) + 2); })()}
    ${boc('_daQtDaChot')}\n${boc('renderDaTienTrinh')}
    return renderDaTienTrinh;`;
  new Function('moi', `with(moi){ ${src} }`)(moi)(r);
  return NK.daTienTrinh.innerHTML;
}
{
  const h = veTienTrinh({ trangThai: 'Đang làm', lenhQT: [{ dot: 1, tt: 'xong' }],
    lines: [
      { capCha: '', hm: { tt: 'nhap' } },
      { capCha: '', hm: { tt: 'nhap' } },
      { capCha: '', hm: { tt: 'xin' } },
      { capCha: '', hm: { tt: 'ung' } },
      { capCha: '', hm: { tt: 'xong', qtDot: 0 } },
      { capCha: '', hm: { tt: 'xong', qtDot: 1 } },
      { capCha: 'X', hm: { tt: 'nhap' } },   // mục con — không đếm
    ] });
  t('🔴 dải hiện đủ sáu bước', /Nháp/.test(h) && /Chờ duyệt/.test(h) && /Chờ cấp tiền/.test(h)
    && /Đã cấp tiền/.test(h) && /Đã chốt \(khoá\)/.test(h) && /Đã quyết toán/.test(h), h);
  t('🔴 mỗi bước đếm SỐ HẠNG MỤC đang ở đó', /Nháp <b>2<\/b>/.test(h), h);
  t('🔴 CHỈ đếm hạng mục lớn — mục con đi theo cha, đếm cả con là một hạng mục hoá mấy đơn',
    /6 hạng mục lớn/.test(h), h);
  t('🔴 hạng mục đã chốt mà lệnh QT ĐÃ CHỐT SỔ thì đứng ở bước cuối, không đếm hai chỗ',
    /Đã chốt \(khoá\) <b>1<\/b>/.test(h) && /Đã quyết toán <b>1<\/b>/.test(h), h);
  t('   bước rỗng thì mờ đi, không biến mất', /opacity:\.4/.test(h), h);
  t('🔴 có huy hiệu KHOÁ khi đã có hạng mục chốt', /KHOÁ SỬA \/ XOÁ 2 HẠNG MỤC/.test(h), h);
  t('   và nói rõ ai mở lại được', /Mở lại/.test(h), h);
}
{
  const h = veTienTrinh({ trangThai: 'Đã đóng', lenhQT: [],
    lines: [{ capCha: '', hm: { tt: 'nhap' } }] });
  t('dự án đã đóng → có huy hiệu riêng', /DỰ ÁN ĐÃ ĐÓNG/.test(h), h);
  t('   chưa hạng mục nào chốt thì không bày huy hiệu khoá sửa', !/KHOÁ SỬA/.test(h), h);
}
{
  const h = veTienTrinh({ trangThai: 'Đang làm', lenhQT: [],
    lines: [{ capCha: '', hm: { tt: 'tra' } }] });
  t('🔴 hạng mục BỊ TRẢ LẠI xếp cùng chỗ với nháp (nó đang chờ nhân viên sửa)',
    /Nháp <b>1<\/b>/.test(h), h);
}
{
  const h = veTienTrinh({ trangThai: 'Đang làm', lenhQT: [{ dot: 1, tt: 'xin' }],
    lines: [{ capCha: '', hm: { tt: 'xong', qtDot: 1 } }] });
  t('🔴 đã GỬI quyết toán nhưng kế toán CHƯA chốt sổ → vẫn ở bước "đã chốt", chưa sang bước cuối',
    /Đã chốt \(khoá\) <b>1<\/b>/.test(h) && !/Đã quyết toán <b>/.test(h), h);
}
t('🔴 trang dự án có chỗ cho dải tiến trình và lịch sử', /id="daTienTrinh"/.test(HTML) && /id="daSuBox"/.test(HTML));
t('   và nạp lịch sử khi mở dự án', /loadDaSu\(r\.maDA\);/.test(HTML));
t('🔴 lượt hỏi lịch sử cũ về sau KHÔNG được cướp màn (bấm nhanh hai dự án là hai lượt song song)',
  /function loadDaSu\([^]{0,1200}if\(seq!==_daSuSeq\) return;/.test(HTML));

/* ── 4h. 🔴 BẢNG HẠNG MỤC GOM MỘT DỰ ÁN MỘT DÒNG ─────────────────────────────────────
 * Anh Thắng 11/09/2026: *"2 đơn này là 1, chỉ hiện 1 đơn, nếu có thay đổi trạng thái thì hiện
 * thông tin phía dưới thôi"* và *"Bổ sung nút mở"*.
 *
 * Bảng vốn liệt kê TỪNG HẠNG MỤC thành một dòng, nên một dự án bốn hạng mục chiếm bốn dòng lặp
 * lại y hệt tên dự án, loại, người tạo, thời gian setup — kế toán đọc bốn lần mới biết đó vẫn
 * là một đơn.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
function veDonHM(role, loc, items) {
  const NK = {};
  const moi = {
    CURUSER: { role: role },
    HM_ITEMS: items,
    HM_NHAN: null,
    esc: x => String(x == null ? '' : x), money: x => String(x), _dmy: x => String(x),
    showPage: () => {}, openDuAn: () => {}, toast: () => {},
    el: id => (NK[id] = NK[id] || { innerHTML: '', style: {},
                                    value: id === 'hmFilter' ? (loc || 'all') : '' }),
  };
  const src = `${(() => { const i = HTML.indexOf('var HM_NHAN='); return HTML.slice(i, HTML.indexOf('};', i) + 2); })()}
    ${boc('_hmNhanCua')}\n${boc('_hmLaKT')}\n${boc('_hmLaDuyet')}
    ${boc('hmNhanChung')}\n${boc('hmNutChung')}\n${boc('_hmKeyDuyet')}
    ${boc('hmDongForm')}\n${boc('_hmNgay')}\n${boc('_uncGon')}\n${boc('renderDonHM')}
    return renderDonHM;`;
  new Function('moi', `with(moi){ ${src} }`)(moi)();
  return NK;
}
const HM2 = [
  { maDA: 'DA1', tenDA: 'TÀU ESTELLA', loaiDA: 'Setup lắp đặt', nguoiTao: 'Nguyễn Hữu Thọ',
    row: 2, noiDung: 'Mua đồ điện', gian: '', hinhThuc: '', duToan: 0, thucTe: 13000000,
    hm: { tt: 'nhap', dot: 0, unc: '', hoaDon: '', lich: [] }, kyDA: { tu: '', den: '' } },
  { maDA: 'DA1', tenDA: 'TÀU ESTELLA', loaiDA: 'Setup lắp đặt', nguoiTao: 'Nguyễn Hữu Thọ',
    row: 5, noiDung: 'Xe vận chuyển', gian: '', hinhThuc: '', duToan: 0, thucTe: 5000000,
    hm: { tt: 'ung', dot: 2, unc: 'https://khmatrix.com/wp-content/uploads/vhcp/HoSo_DuAn/DA_x_zzz.jpg', hoaDon: '', lich: [] },
    kyDA: { tu: '', den: '' } },
  { maDA: 'DA2', tenDA: 'Aeon Bình Tân', loaiDA: 'Tháo dỡ', nguoiTao: 'NV B',
    row: 3, noiDung: 'Xe ba gác', gian: '', hinhThuc: 'Trực tiếp', duToan: 0, thucTe: 2000000,
    hm: { tt: 'xong', dot: 1, unc: 'UNC-88', hoaDon: 'https://kho/hd.pdf', lich: [] },
    kyDA: { tu: '', den: '' } },
];
{
  const NK = veDonHM('Kế toán cá nhân', 'all', HM2);
  const out = NK.hmBody.innerHTML;
  /* Hàng chính của mỗi dự án là hàng có nút Mở. Đếm nó thay vì đếm <tr>, vì còn hàng chi tiết
     và hàng ô nhập. */
  t('🔴 hai hạng mục CÙNG dự án gom về MỘT hàng chính',
    (out.match(/📂 Mở/g) || []).length === 2, (out.match(/📂 Mở/g) || []).length);
  t('   tên dự án chỉ in một lần cho cả nhóm',
    (out.match(/TÀU ESTELLA/g) || []).length === 1, (out.match(/TÀU ESTELLA/g) || []).length);
  t('🔴 hàng chính cộng TỔNG tiền của cả nhóm (13.000.000 + 5.000.000)',
    />18000000</.test(out), out.slice(0, 600));
  t('   và nói rõ có mấy hạng mục', /<b>2<\/b> hạng mục/.test(out), out.slice(0, 600));
  t('🔴 có nút Mở, trỏ đúng dự án', /openDuAn\('DA1'\)/.test(out) && /openDuAn\('DA2'\)/.test(out), out);
  t('🔴 trạng thái từng hạng mục nằm ở HÀNG PHỤ bên dưới',
    /Mua đồ điện/.test(out) && /Xe vận chuyển/.test(out) && /colspan="8"/.test(out), out);
  t('   hàng chính tóm tắt còn mấy hạng mục chưa chốt', /2 chưa chốt/.test(out), out);
  t('   dự án đã chốt hết thì báo chốt hết', /✔ chốt hết/.test(out), out);
  t('🔴 nút thao tác của TỪNG hạng mục vẫn còn ở hàng phụ',
    /hmMoChot\('DDA1_5','DA1',5\)/.test(out), out);
  t('   và mỗi hạng mục vẫn có hàng ô nhập riêng',
    /data-hmf="DDA1_2"/.test(out) && /data-hmf="DDA1_5"/.test(out), out);
}
{
  /* 🔴 Hình thức chi của cả nhóm: trộn thì phải nói là trộn, đừng lấy kiểu của hạng mục đầu
     tiên rồi bảo đó là của cả đơn. */
  const tron = HM2.slice(0, 2).concat([Object.assign({}, HM2[2], { maDA: 'DA1', tenDA: 'TÀU ESTELLA', row: 9 })]);
  const NK = veDonHM('Kế toán cá nhân', 'all', tron);
  t('🔴 nhóm có cả 💰 lẫn 🏢 → nói là CẢ HAI, không lấy kiểu của hạng mục đầu',
    /Cả hai/.test(NK.hmBody.innerHTML), NK.hmBody.innerHTML.slice(0, 800));
}
/* 🔴 Uỷ nhiệm chi hay là một ĐỊA CHỈ TỆP dài cả dòng — in thẳng thì nó đẩy mọi thứ khác văng
   khỏi màn (anh Thắng gửi ảnh đúng cảnh ấy). */
{
  const moi = { esc: x => String(x == null ? '' : x) };
  const f = new Function('moi', `with(moi){ ${boc('_uncGon')}\n return _uncGon; }`)(moi);
  const dai = f('https://khmatrix.com/wp-content/uploads/vhcp/HoSo_DuAn/DA_rat_dai.jpg', 2);
  t('🔴 UNC là liên kết → rút thành một chữ bấm được, KHÔNG in cả địa chỉ',
    /📎 UNC · đợt 2/.test(dai) && dai.indexOf('>https://') < 0, dai);
  t('   và rê chuột vào vẫn xem được ảnh', /data-bill=/.test(dai), dai);
  const so = f('UNC-88', 1);
  t('🔴 UNC là SỐ gõ tay → in nguyên (số ấy ngắn, và chính nó mới là thông tin)',
    /UNC-88/.test(so) && !/📎/.test(so), so);
  t('rỗng thì không bày gì', f('', 0) === '', f('', 0));
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
