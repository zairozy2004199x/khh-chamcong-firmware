/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GỬI QUYẾT TOÁN — đơn TRỰC TIẾP gửi thẳng từ Nháp không bị chối bởi khối Quyết toán đang ẩn;
 * câu chối của luồng tạm ứng nói đúng chỗ thiếu; hộp Sửa dòng giữ ô Phân loại lớn.
 * Anh Thắng 24/09/2026 (ảnh toast "Chưa có dòng đã mua — nhập ở bảng bên dưới (hoặc bấm 🚫 Không
 * dùng)" cạnh nút Gửi quyết toán; ảnh hộp Sửa dòng chi ô Phân loại lớn trống): *"Sao bấm sửa cái
 * phân loại nó mất, có ảnh hưởng gì không"*. Chạy: node tools/test/kiem-gui-qt-truc-tiep.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['guiQT', '_guiQtTrucTiep', '_tongChiDong', '_tienSo', 'fillDauMuc'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 40, n));
t('🔴 câu chối cũ trỏ vào "bảng bên dưới" đã bỏ', !/nhập ở bảng bên dưới/.test(HTML));

/* Bệ chạy guiQT với một đơn giả */
function chay(don, lines, opts) {
  opts = opts || {};
  const goi = { qt: 0, save: 0 }, toasts = [], logs = [];
  const O = { qtThucMua: { value: opts.thucMua || '' }, qtHTTT: { value: opts.httt || '' } };
  const run = {
    withSuccessHandler(cb) { run.cb = cb; return run; },
    withFailureHandler() { return run; },
    guiQuyetToan() { goi.qt++; },
    saveQuyetToan() { goi.save++; },
  };
  new Function('CUR', 'el', 'toast', 'confirm', 'loading', 'google', '_log', 'money', 'boot', 'openDon', 'QT', '_laTrucTiep',
    ham('_tienSo') + ham('_tongChiDong') + ham('_guiQtTrucTiep') + ham('guiQT') + '\nguiQT();')(
    { don: don, lines: lines }, (id) => O[id], (k, m) => toasts.push(k + ':' + m), (m) => { goi.hoi = m; return opts.confirm !== false; },
    () => {}, { script: { run: run } }, (a, b, c) => logs.push([a, b, c]), (n) => String(n), () => {}, () => {}, {}, (d) => d && d.luong === 'tt');
  return { goi, toasts, logs };
}
const D2 = [{ id: 'a', thanhTien: 3325200, thucMua: null }, { id: 'b', thanhTien: 100000, thucMua: 0 }];

/* ── 1. Đơn TRỰC TIẾP ở Nháp: có hạng mục → gửi thẳng, không đọc khối Quyết toán ────────── */
{
  const r = chay({ maDon: 'D1', trangThai: 'Nháp', luong: 'tt' }, D2, { thucMua: '' });
  teq('🔴 gọi guiQuyetToan dù ô qtThucMua rỗng (khối Quyết toán ẩn ở Nháp)', 1, r.goi.qt);
  teq('   không đi qua saveQuyetToan (không có gì để lưu ở bước ấy)', 0, r.goi.save);
  t('   câu hỏi nói TRỰC TIẾP, 2 hạng mục, tổng 3325200 (dòng gõ Thực chi 0 tính 0)', /TRỰC TIẾP/.test(r.goi.hoi) && /2 hạng mục/.test(r.goi.hoi) && /3325200/.test(r.goi.hoi), r.goi.hoi);
  teq('   không toast warn nào', [], r.toasts.filter((x) => /^warn/.test(x)));
}
/* ── 2. Đơn TRỰC TIẾP ở Nháp mà chưa có hạng mục → chối, không nhắc 🚫 Không dùng ─────────── */
{
  const r = chay({ maDon: 'D1', trangThai: 'Nháp', luong: 'tt' }, [], {});
  teq('🔴 không gọi máy chủ', 0, r.goi.qt);
  t('   câu chối nói "hạng mục", không nhắc "Không dùng" (nút ấy không có trong luồng trực tiếp)',
    r.toasts.length === 1 && /hạng mục/.test(r.toasts[0]) && !/Không dùng/.test(r.toasts[0]), r.toasts);
}
/* ── 3. Bấm Huỷ ở hộp hỏi → không gửi ─────────────────────────────────────────────────────── */
{
  const r = chay({ maDon: 'D1', trangThai: 'Nháp', luong: 'tt' }, D2, { confirm: false });
  teq('   huỷ hộp hỏi → không gọi máy chủ', 0, r.goi.qt);
}
/* ── 4. Luồng tạm ứng ở "Đã cấp tạm ứng": vẫn soi ô Thực chi như cũ, câu chối đúng chỗ ───── */
{
  const r0 = chay({ maDon: 'D2', trangThai: 'Đã cấp tạm ứng', luong: 'gt' }, [], { thucMua: '' });
  t('🔴 chưa có dòng → chối, nhắc thêm ở bảng hạng mục + 🚫 Không dùng', r0.goi.qt === 0 && /Chưa có hạng mục/.test(r0.toasts[0]) && /Không dùng/.test(r0.toasts[0]), r0.toasts);
  const r1 = chay({ maDon: 'D2', trangThai: 'Đã cấp tạm ứng', luong: 'gt' }, D2, { thucMua: '0' });
  t('🔴 có 2 dòng mà tổng thực chi 0 → câu chối nói "Có 2 hạng mục … = 0"', r1.goi.qt === 0 && /Có 2 hạng mục/.test(r1.toasts[0]) && /= 0/.test(r1.toasts[0]), r1.toasts);
  const r2 = chay({ maDon: 'D2', trangThai: 'Đã cấp tạm ứng', luong: 'gt' }, D2, { thucMua: '3.325.200', httt: '' });
  t('   có thực chi mà chưa chọn hình thức trả → chối đúng câu cũ', r2.goi.qt === 0 && /hình thức trả/.test(r2.toasts[0]), r2.toasts);
  const r3 = chay({ maDon: 'D2', trangThai: 'Đã cấp tạm ứng', luong: 'gt' }, D2, { thucMua: '3.325.200', httt: 'Tiền mặt' });
  teq('   đủ → đi đường cũ: saveQuyetToan trước', 1, r3.goi.save);
  /* Đơn tạm ứng ("gt") đang ở Nháp bấm nhầm guiQT → KHÔNG được lọt cửa trực tiếp */
  const r4 = chay({ maDon: 'D3', trangThai: 'Nháp', luong: 'gt' }, D2, { thucMua: '' });
  teq('⚠️ đơn qua tạm ứng ở Nháp không lọt cửa trực tiếp', 0, r4.goi.qt);
}
/* ── 5. Hộp Sửa dòng: đầu mục của loại trên dòng vẫn bày dù loại ấy đang bị ẩn ───────────── */
{
  function chayDm(lineId, cur) {
    const O = { f_dauMuc: { innerHTML: '', value: '' }, fldDauMuc: { style: {} }, lblNhom: {}, f_dauMucVi: {}, lineId: { value: lineId } };
    const ket = {};
    new Function('el', '_tn', '_dmDs', 'esc', '_dmCoSoCua', '_tenKhoi', '_oCoSoTheoDauMuc', 'ket', 'DM_CUR',
      ham('fillDauMuc') + "\nfillDauMuc(" + JSON.stringify(cur) + '); ket.dm=DM_CUR; ket.sel=el("f_dauMuc").value; ket.html=el("f_dauMuc").innerHTML;')(
      (id) => O[id], () => true, () => ['Chi Phí Cơ Sở KVC', 'Chi phí chung'], (x) => String(x), () => '*', (k) => k, () => {}, ket, '');
    return ket;
  }
  const s = chayDm('L9', 'Chi Phí Chung VP');
  t('🔴 sửa dòng: đầu mục "Chi Phí Chung VP" không có trong danh sách xổ vẫn được thêm và chọn',
    s.dm === 'Chi Phí Chung VP' && s.sel === 'Chi Phí Chung VP' && /<option value="Chi Phí Chung VP">/.test(s.html), s);
  const m = chayDm('', 'Chi Phí Chung VP');
  t('   thêm dòng mới: đầu mục lạ KHÔNG bị nhét vào (về "— chọn —")', m.dm === '' && !/Chi Phí Chung VP/.test(m.html), m);
  const c = chayDm('L9', 'Chi phí chung');
  t('   sửa dòng, đầu mục có sẵn → không nhân đôi option', c.dm === 'Chi phí chung' && c.html.split('value="Chi phí chung"').length === 2, c.html);
}
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: đơn trực tiếp gửi thẳng từ Nháp; câu chối luồng tạm ứng đúng chỗ; Sửa dòng giữ Phân loại lớn.');
