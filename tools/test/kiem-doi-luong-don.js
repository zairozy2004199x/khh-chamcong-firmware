/* Nút "🔁 Đổi luồng" trên đầu đơn — anh Thắng 25/09/2026: "Đơn này sai luôn, anh muốn chỉnh cho đơn đó
 * lại luồng khác được không". Chạy: node tools/test/kiem-doi-luong-don.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const dong = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i) + 1); };
const bocMang = (n) => { const i = HTML.indexOf('  var ' + n + '=['); return HTML.slice(i, HTML.indexOf('\n  ];', i) + 5); };

t('⚠️ bốc được _doiLuongDuoc / _oDoiLuong / doiLuongDon', ham('_doiLuongDuoc').length > 40 && ham('_oDoiLuong').length > 40 && ham('doiLuongDon').length > 40);
t('🔴 đầu đơn (openDon) chèn _oDoiLuong ngay sau thanh bước', /_thanhBuoc\(st, \(CUR&&CUR\.don&&CUR\.don\.khoi\)\|\|undefined, _luongDon\(CUR&&CUR\.don\)\)\s*\+_oDoiLuong\(st, CUR&&CUR\.don\)\+'<\/div>';/.test(HTML));

const TT_LUONG = ['Nháp', 'Chờ duyệt tạm ứng', 'Chờ cấp tạm ứng', 'Đã cấp tạm ứng', 'Chờ quyết toán', 'Đã quyết toán', 'Đã thanh toán', 'Đã xuất MISA'];
const TT_CHOT = ['Đã quyết toán', 'Đã thanh toán', 'Đã xuất MISA'];
const SRC = ham('_luongDon') + ham('_laTrucTiep') + ham('_daChot') + ham('_doiLuongDuoc') + ham('_oDoiLuong') + bocMang('ND_LUONG_DS');
function duoc(st, d, quyen) {
  return new Function('canDo', 'TT_LUONG', 'TT_CHOT', 'esc', SRC + '\nreturn _doiLuongDuoc(st, d);'.replace('st, d', JSON.stringify(st) + ',' + JSON.stringify(d)))(() => quyen !== false, TT_LUONG, TT_CHOT, (s) => String(s));
}
/* Ca của anh: đơn tt ở "Chờ quyết toán" → chưa cấp đồng nào → đổi được */
t('🔴 đơn TRỰC TIẾP ở "Chờ quyết toán" → hiện nút (tiền chưa ra két)', duoc('Chờ quyết toán', { luong: 'tt' }) === true);
t('🔴 đơn QUA TẠM ỨNG ở "Chờ quyết toán" → KHÔNG (tiền đã ra)', duoc('Chờ quyết toán', { luong: 'gt' }) === false);
t('   Nháp / Chờ duyệt / Chờ cấp → hiện', duoc('Nháp', { luong: 'gt' }) && duoc('Chờ duyệt tạm ứng', { luong: 'gt' }) && duoc('Chờ cấp tạm ứng', { luong: 'dc' }));
t('   Đã cấp tạm ứng → không', duoc('Đã cấp tạm ứng', { luong: 'gt' }) === false);
t('   đã chốt (Đã quyết toán / Đã thanh toán / Đã xuất MISA) → không', !duoc('Đã quyết toán', { luong: 'tt' }) && !duoc('Đã thanh toán', { luong: 'tt' }) && !duoc('Đã xuất MISA', { luong: 'gt' }));
t('🔴 không có quyền doiLuong → không, dù trạng thái cho', duoc('Nháp', { luong: 'gt' }, false) === false);
t('   không có đơn → không', duoc('Nháp', null) === false);

/* _oDoiLuong: ô chọn bỏ luồng đang đi, nút gọi doiLuongDon */
{
  const html = new Function('canDo', 'TT_LUONG', 'TT_CHOT', 'esc', SRC + '\nreturn _oDoiLuong("Chờ quyết toán", {luong:"tt"});')(() => true, TT_LUONG, TT_CHOT, (s) => String(s));
  t('🔴 ô chọn KHÔNG có luồng đang đi (tt), còn đủ gt · dc', !/value="tt"/.test(html) && /value="gt"/.test(html) && /value="dc"/.test(html), html);
  t('   có nút gọi doiLuongDon() và câu nói rõ hậu quả (về Nháp, giữ hạng mục + tạm ứng xin)', /onclick="doiLuongDon\(\)"/.test(html) && /về Nháp/.test(html) && /tạm ứng xin giữ nguyên/.test(html), html);
  const rong = new Function('canDo', 'TT_LUONG', 'TT_CHOT', 'esc', SRC + '\nreturn _oDoiLuong("Đã cấp tạm ứng", {luong:"gt"});')(() => true, TT_LUONG, TT_CHOT, (s) => String(s));
  teq('   không đổi được → không vẽ gì', '', rong);
}

/* doiLuongDon(): huỷ prompt → không gọi; gọi đúng 3 tham số; thành công → openDon */
{
  const src = ham('doiLuongDon') + bocMang('ND_LUONG_DS');
  let goi = null, mo = null, toasts = [], logs = [];
  const run = { withSuccessHandler(f) { this._ok = f; return this; }, withFailureHandler() { return this; }, doiLuongDon(...a) { goi = a; this._ok({ success: true, luongCu: 'tt', luongMoi: 'gt', goDuyet: false }); } };
  const mk = (promptRet) => new Function('CUR', 'el', 'prompt', 'loading', 'google', 'toast', '_log', 'openDon', 'esc', src + '\ndoiLuongDon();');
  mk()( { don: { maDon: 'D_x' } }, (id) => ({ doiLuongSel: { value: 'gt' } })[id], () => null, () => {}, { script: { run } }, (k, m) => toasts.push(k), () => {}, (m) => { mo = m; }, (s) => s);
  t('   bấm Huỷ ở hộp lý do → không gọi máy chủ', goi === null);
  mk()( { don: { maDon: 'D_x' } }, (id) => ({ doiLuongSel: { value: 'gt' } })[id], () => ' nhầm luồng ', () => {}, { script: { run } }, (k, m) => toasts.push(k), (a, m, c) => logs.push(a), (m) => { mo = m; }, (s) => s);
  teq('🔴 gọi doiLuongDon(mã đơn, luồng mới, lý do đã cắt khoảng trắng)', ['D_x', 'gt', 'nhầm luồng'], goi);
  t('   thành công → toast ok, ghi _log, mở lại đơn', toasts[0] === 'ok' && logs[0] === 'ĐỔI LUỒNG ĐƠN' && mo === 'D_x', [toasts, logs, mo]);
  goi = null;
  const run2 = { withSuccessHandler(f) { this._ok = f; return this; }, withFailureHandler() { return this; }, doiLuongDon(...a) { goi = a; this._ok({ success: false, error: 'tiền đã cấp' }); } };
  toasts = [];
  mk()( { don: { maDon: 'D_x' } }, (id) => ({ doiLuongSel: { value: 'gt' } })[id], () => '', () => {}, { script: { run: run2 } }, (k, m) => toasts.push(k + ':' + m), () => {}, () => { mo = 'KHONG'; }, (s) => s);
  t('   máy chủ chối → toast err nguyên câu, không mở lại đơn', toasts[0] === 'err:tiền đã cấp' && mo !== 'KHONG', [toasts, mo]);
}

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: nút Đổi luồng chỉ bày khi máy chủ sẽ nhận, gọi đúng, đổi xong mở lại đơn.');
