/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🧪 CỜ TÍNH NĂNG TRÊN MÀN — cờ tắt thì form nhập y như bản cũ; cờ bật thì đường mới; thẻ Cấu hình.
 * Anh Thắng 24/09/2026: *"giao diện đó admin sẽ xem trước, rồi admin cấu hình xong bấm thay đổi thì
 * nó áp dụng luôn, chứ nạp lên, nó thay đổi danh mục, các nhân viên đang đăng nhập nó mất và chưa
 * kịp set"*.
 * 🔴 CHẠY THẬT `_tn`, `renderNhomCp`, `fillDauMuc`, `_dmCoSoCua`, `_oCoSoTheoDauMuc`, `renderTinhNang`,
 *    `saveCfgTinhNang` với BOOT.tinhNang giả. Chạy: node tools/test/kiem-co-tinh-nang-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const dong = (n) => { const m = HTML.match(new RegExp('\\n  var ' + n + '=[^\\n]*')); return m ? m[0] : ''; };

/* ── 1. `_tn`: hỏi gói, mã lạ = bật, thiếu gói = bật ───────────────────────────────────── */
{
  const F = (boot) => new Function('BOOT', ham('_tn') + '\nreturn _tn;')(boot);
  t('🔴 cờ false trong gói → tắt', F({ tinhNang: { phanLoaiHaiBac: false } })('phanLoaiHaiBac') === false);
  t('🔴 cờ true → bật', F({ tinhNang: { phanLoaiHaiBac: true } })('phanLoaiHaiBac') === true);
  t('🔴 mã KHÔNG có trong gói → BẬT (cờ giữ đường cũ, không phải cổng chặn)', F({ tinhNang: {} })('gìĐóLạ') === true);
  t('   gói cũ không có `tinhNang` → bật', F({})('phanLoaiHaiBac') === true && F(null)('phanLoaiHaiBac') === true);
}
/* ── 2. Form theo cờ ──────────────────────────────────────────────────────────────────── */
function be(co) {
  const KHO = {};
  const sel = (id) => (KHO[id] = KHO[id] || { _id: id, value: '', style: {}, textContent: '', disabled: false, options: [],
    set innerHTML(h) { this._h = h; this.options = (h.match(/<option value="([^"]*)"/g) || []).map((x) => ({ value: x.replace(/.*value="([^"]*)"/, '$1') })); },
    get innerHTML() { return this._h || ''; } });
  const box = sel('f_nhomCp'); box.parentNode = sel('fldNhomCp');
  const moi = {
    BOOT: { tinhNang: { phanLoaiHaiBac: co }, dauMucDs: ['Chi Phí Chung'], dauMucCoSo: { 'Chi Phí Chung': '' },
      loaiChiPhi: [{ ten: 'Chi Phí Chung KVC', dauMuc: 'Chi Phí Chung', boPhan: 'Văn phòng' }, { ten: 'Chi phí cơ sở', dauMuc: 'Chi Phí Chung', boPhan: '' }] },
    KHOI_DS: [{ ma: 'kvc', ten: 'Khu vui chơi' }], KHOI_DANG: 'kvc', NHOM_CP: '', DM_CUR: '',
    el: sel, esc: (x) => String(x == null ? '' : x), window: { _COSO_OPTS_GOC: ['AEON'] },
    _loaiCpList: () => moi.BOOT.loaiChiPhi.slice(), _cacNhomCp: () => ['(cơ sở)', 'Văn phòng'], _nhanNhomCp: (k) => 'nhãn ' + k,
    _gianHopKhoi: () => true, _khoiCuaGian: () => 'kvc', _donNhieuCoSo: () => false, _khoaCoSoHint: () => {},
    fillNoiDungList: () => {}, showTkNhom: () => {}, _veLoaiCpVi: () => {}, _lnDongThem: () => '', _tenKhoi: () => 'Khu vui chơi',
  };
  new Function('moi', 'with(moi){' + ['_tn', 'renderNhomCp', '_dmCoSoCua', '_dmDs', 'fillDauMuc', 'onDauMucPick', '_oCoSoTheoDauMuc', 'fillNhom', '_dauMucCua', 'opts', 'loaiOf'].map(ham).join('\n')
    + '\nmoi.F={nhom:renderNhomCp, dm:fillDauMuc, co:_dmCoSoCua, oCo:_oCoSoTheoDauMuc, fillNhom:fillNhom, pick:onDauMucPick}; moi.layNhom=function(){return NHOM_CP;}; moi.layDm=function(){return DM_CUR;}; }')(moi);
  sel('f_pltt').value = ''; sel('lineId').value = '';
  return { moi, sel, F: moi.F };
}
{
  const b = be(false);
  b.F.nhom();
  t('🔴 cờ TẮT: dải nút bộ phận HIỆN lại y bản cũ (hai nút, nút đầu chọn sẵn)', b.sel('fldNhomCp').style.display === '' && /nhãn \(cơ sở\)/.test(b.sel('f_nhomCp').innerHTML) && /nhãn Văn phòng/.test(b.sel('f_nhomCp').innerHTML) && b.moi.layNhom() === '(cơ sở)', [b.sel('fldNhomCp').style.display, b.moi.layNhom()]);
  b.F.dm('Chi Phí Chung');
  t('🔴 cờ TẮT: ô Phân loại lớn ẨN, DM_CUR rỗng, nhãn ô loại về "Loại chi phí"', b.sel('fldDauMuc').style.display === 'none' && b.moi.layDm() === '' && b.sel('lblNhom').textContent === 'Loại chi phí', [b.sel('fldDauMuc').style.display, b.moi.layDm(), b.sel('lblNhom').textContent]);
  teq('🔴 cờ TẮT: đầu mục "không có cơ sở" vẫn ra "*" → ô Cơ sở như cũ, vẫn bắt buộc', '*', b.F.co('Chi Phí Chung'));
  b.sel('fldCoso').style.display = ''; b.sel('f_coso').innerHTML = '<option value="">—</option><option value="AEON">AEON</option>'; b.sel('f_coso').value = 'AEON';
  b.moi.window._COSO_OPTS_GOC = ['AEON', 'KHAC']; b.sel('lblCoso').textContent = 'Nhãn do _khoaCoSo đặt';
  b.F.oCo();
  t('🔴 cờ TẮT: `_oCoSoTheoDauMuc` KHÔNG đụng ô Cơ sở (không dựng lại danh sách, không đổi nhãn) — việc ấy của `_khoaCoSo` như cũ',
    b.sel('fldCoso').style.display === '' && b.sel('f_coso').value === 'AEON' && b.sel('f_coso').options.length === 2 && b.sel('lblCoso').textContent === 'Nhãn do _khoaCoSo đặt',
    [b.sel('f_coso').options.map((o) => o.value), b.sel('lblCoso').textContent]);
  b.F.fillNhom('');
  teq('   cờ TẮT: ô loại bày đủ, không lọc theo đầu mục', 2, b.sel('f_nhom').options.filter((o) => o.value).length);
}
{
  const b = be(true);
  b.moi.NHOM_CP = 'Văn phòng'; b.F.nhom();
  t('🔴 cờ BẬT: dải nút ẩn, NHOM_CP rỗng', b.sel('fldNhomCp').style.display === 'none' && b.moi.layNhom() === '');
  b.F.dm('Chi Phí Chung');
  t('🔴 cờ BẬT: ô Phân loại lớn hiện, nhãn "Phân loại nhỏ (loại chi phí)"', b.sel('fldDauMuc').style.display === '' && b.moi.layDm() === 'Chi Phí Chung' && b.sel('lblNhom').textContent === 'Phân loại nhỏ (loại chi phí)');
  teq('🔴 cờ BẬT: đầu mục không cơ sở → "" (ẩn ô)', '', b.F.co('Chi Phí Chung'));
  t('   cờ BẬT: ô Cơ sở ẩn', b.sel('fldCoso').style.display === 'none');
}
/* ── 3. Thẻ Cấu hình 🧪 ───────────────────────────────────────────────────────────────── */
{
  const KHO = {}; const toasts = []; let luu = null; let hoi = null;
  const sel = (id) => (KHO[id] = KHO[id] || { style: {}, innerHTML: '', querySelectorAll() { return this._rows || []; } });
  const F = new Function('CFG', 'el', 'esc', '_laAdmin', 'toast', 'confirm', '_saveCfg', dong('TN_TRANG_THAI') + ham('renderTinhNang') + ham('saveCfgTinhNang') + '\nreturn {ve:renderTinhNang, luu:saveCfgTinhNang};');
  const cfg = { tinhNangDs: [{ ma: 'phanLoaiHaiBac', ten: 'Form hai bậc', mo: 'mô tả', ban: '1.306.0', macDinh: 'admin', trangThai: 'admin' }] };
  const R = F(cfg, sel, (x) => String(x), () => true, (k, m) => toasts.push(k + ':' + m), (m) => { hoi = m; return true; }, (p) => { luu = p; });
  R.ve();
  const h = sel('cfgTinhNangBody').innerHTML;
  t('🔴 mỗi tính năng một dòng: tên · mô tả · bản · ô chọn ba mức, chọn sẵn trạng thái hiện tại', /Form hai bậc/.test(h) && /mô tả/.test(h) && /1\.306\.0/.test(h) && /value="admin" selected/.test(h) && /value="tat"/.test(h) && /value="bat"/.test(h), h);
  t('   chưa bật cho mọi người thì nhắc "Nhân viên đang thấy đường cũ"', /Nhân viên đang thấy đường cũ/.test(h));
  sel('cfgTinhNangBody')._rows = [{ getAttribute: () => 'phanLoaiHaiBac', querySelector: () => ({ value: 'bat' }) }];
  R.luu();
  teq('🔴 Áp dụng gửi {tinhNang:{mã: trạng thái}}', { tinhNang: { phanLoaiHaiBac: 'bat' } }, luu);
  t('🔴 bật cho MỌI NGƯỌI thì hỏi xác nhận, nhắc khai đủ danh mục', /MỌI NGƯỚI 1 tính năng/.test(hoi) && /khai đủ danh mục/.test(hoi), hoi);
  hoi = null; luu = null; sel('cfgTinhNangBody')._rows = [{ getAttribute: () => 'phanLoaiHaiBac', querySelector: () => ({ value: 'admin' }) }]; R.luu();
  t('   đổi về "chỉ Admin" thì KHÔNG hỏi (không ảnh hưởng nhân viên)', hoi === null && luu && luu.tinhNang.phanLoaiHaiBac === 'admin');
  const R2 = F({ tinhNangDs: [] }, sel, (x) => String(x), () => true, () => {}, () => true, () => {}); R2.ve();
  t('   bản không có tính năng nào → nói rõ, không để bảng trống', /không có thay đổi giao diện/.test(sel('cfgTinhNangBody').innerHTML));
  const KHO2 = {}; const sel2 = (id) => (KHO2[id] = KHO2[id] || { style: {}, innerHTML: '' });
  F(cfg, sel2, (x) => String(x), () => false, () => {}, () => true, () => {}).ve();
  t('   không phải Admin → thẻ ẩn, không vẽ', sel2('tinhNangCard').style.display === 'none' && sel2('cfgTinhNangBody').innerHTML === '');
}
t('🔴 thẻ 🧪 đứng ĐẦU danh sách nhóm Cấu hình', /var CH_NHOM=\[[\s\S]{0,400}id:\['tinhNangCard'\]/.test(HTML));
t('   loadCfg vẽ thẻ', /renderTinhNang\(\);/.test(HTML));
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: cờ tắt thì form y bản cũ, cờ bật thì đường mới; thẻ 🧪 áp dụng đúng.');
