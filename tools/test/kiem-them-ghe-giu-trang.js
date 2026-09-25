/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * THÊM GHẾ GIỮ NGUYÊN TRANG · Ô TÌM ĐỊA ĐIỂM DÒ CẢ KHỐI GẬP · BÁO TRÙNG MÃ NÓI RÕ CHỖ (2.147.0)
 *
 * Anh Thắng 25/09/2026: *"Bấm thêm ghế mới thì giữ nguyên trang đó chứ không phải là nhảy trang
 * và quay lại từ đầu"* — trước đây nút Thêm ghế đi qua lam() → tai() → ve(): vẽ lại CẢ TRANG, cuộn
 * về đầu, khối ghế mở lại ở trang 1. Rồi *"check vấn đề nằm đâu"* (gõ VINCOM QUANG TRUNG, máy báo
 * "đã có" mà ô tìm ra 0/65 — cơ sở 0 ghế nằm trong khối gập) và *"Mã cũ của nó tại sao lại trùng…
 * Check thì không thấy cơ sở nào trùng"* (ô tìm địa điểm không dò theo mã ghế).
 *
 * ⚠️ SOI CHÍNH ĐOẠN JS trong tệp PHP rồi gọi thẳng qlThemTaiCho_() / csHangCapNhat_() / csGoiYMa_()
 *    với DOM giả — không cần trình duyệt. Phần còn lại (csMoKhoi, loc trong ve()) soi nguồn.
 *
 * Chạy: node tools/test/kiem-them-ghe-giu-trang.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const duong = 'vhcp-ghe/includes/class-vhg-trang.php';
if (!fs.existsSync(duong)) { console.error('✗ Không thấy ' + duong + ' — chạy từ gốc kho.'); process.exit(2); }
const src = fs.readFileSync(duong, 'utf8');
const may = fs.readFileSync('vhcp-ghe/includes/class-vhg-may.php', 'utf8');
const vqr = fs.readFileSync('vhcp-ghe/includes/class-vhg-vietqr.php', 'utf8');
let js = '', p = 0;
while (true) {
  const a = src.indexOf("<<<'JS'", p); if (a < 0) break;
  const b = src.indexOf('\nJS;', a); if (b < 0) break;
  js += src.slice(src.indexOf('\n', a) + 1, b) + '\n'; p = b + 3;
}
function lay(ten) {
  const i = js.indexOf('function ' + ten + '(');
  if (i < 0) throw new Error('không thấy hàm ' + ten);
  let d = 0;
  for (let k = js.indexOf('{', i); k < js.length; k++) {
    if (js[k] === '{') d++;
    else if (js[k] === '}' && --d === 0) return js.slice(i, k + 1);
  }
  throw new Error('hàm ' + ten + ' không đóng ngoặc');
}
/* Trang có HAI hàm ve() (bc-app và trang chính, hai IIFE) — lấy bản có chứa dấu hiệu `dau`. */
function layChua(ten, dau) {
  let p = 0;
  while (true) {
    const i = js.indexOf('function ' + ten + '(', p); if (i < 0) throw new Error('không thấy hàm ' + ten + ' chứa ' + dau);
    let d = 0, than = '';
    for (let k = js.indexOf('{', i); k < js.length; k++) {
      if (js[k] === '{') d++;
      else if (js[k] === '}' && --d === 0) { than = js.slice(i, k + 1); break; }
    }
    if (than.indexOf(dau) >= 0) return than;
    p = i + 1;
  }
}
let dat = 0, truot = [];
function t(ten, ok, them) {
  if (ok) { dat++; console.log('  ✓ ' + ten); return; }
  truot.push(ten); console.log('  ✗ ' + ten + (them !== undefined ? ' → ' + JSON.stringify(them).slice(0, 320) : ''));
}

console.log('── 1. Nguồn: đường đi của nút Thêm ghế ─────────────────────────');
const wire = lay('qlKhoiWire');
t("qlKhoiWire KHÔNG còn đi qua lam('may_them') (vẽ lại cả trang)", wire.indexOf("lam('may_them'") < 0);
t("… mà gọi thẳng goi('may_them') rồi qlThemTaiCho_", wire.indexOf("goi('may_them'") >= 0 && wire.indexOf('qlThemTaiCho_(r, m, csId, tenMoi)') >= 0);
t('lỗi (trùng mã…) vẫn alert', /alert\(\(r && r\.error\)/.test(wire));
const render = lay('qlGheRender');
t('qlGheRender nhảy tới trang có QL_NHAY_MA rồi xoá cờ', render.indexOf('if (QL_NHAY_MA)') >= 0 && render.indexOf('QL_PG = Math.floor(_k / QL_PER)') >= 0 && render.indexOf("QL_NHAY_MA = '';") >= 0);
const mo = lay('csMoKhoi');
t('csMoKhoi giữ QL_PG khi mở lại CÙNG cơ sở (chỉ về trang 1 khi sang cơ sở khác)', mo.indexOf('if (QL_LOC !== ten) QL_PG = 0;') >= 0 && mo.indexOf('QL_PG = 0; QL_SEL = {}') < 0);
t('csMoKhoi mở <details> đang gập bao quanh hàng', mo.indexOf("closest('details')") >= 0 && mo.indexOf('gap.open = true') >= 0);
const ve = layChua('noi', 'cs-tim');   // gán sự kiện của tab (noi() chạy sau ve())
t('csMoKhoi(…, cuon) cuộn tới hàng địa điểm; noi() (sau ve()) gọi với true', mo.indexOf('scrollIntoView') >= 0 && ve.indexOf('csMoKhoi(CS_MO, true)') >= 0);
t('bấm tay (không cuon) thì không cuộn', /if \(cuon\) \{ try \{ tr\.scrollIntoView/.test(mo));

console.log('── 2. Nguồn: ô tìm địa điểm ────────────────────────────────────');
t('dò cả 4 bảng: chính + chưa có ghế + đóng cửa + chỉ còn ghế ẩn', ve.indexOf('#cs-bang-rong tr[data-cstim]') >= 0 && ve.indexOf('#cs-bang-dong tr[data-cstim]') >= 0 && ve.indexOf('#cs-bang-an tr[data-cstim]') >= 0);
t('khối gập có hàng khớp thì tự mở, đánh dấu data-tumo để gập lại khi xoá ô tìm', ve.indexOf("setAttribute('data-tumo', '1')") >= 0 && ve.indexOf("removeAttribute('data-tumo')") >= 0);
t('đếm n / tổng trên cả bốn bảng + gợi ý mã ghế', ve.indexOf("con + ' / ' + tong + csGoiYMa_(q, con)") >= 0);

console.log('── 3. Nguồn PHP ────────────────────────────────────────────────');
t('them_may trả ma + ten (đã chuẩn hoá) + coso_id để chèn tại chỗ', may.indexOf("'ok' => true, 'ma' => $ma, 'ten' => $ten_khai, 'coso_id' => (int) $coso_id, 'thong_bao' => 'Đã thêm ghế '") >= 0);
t('gan_ma: báo trùng nói rõ mã đang ở cơ sở nào / đang ẩn, và KHÔNG đổi câu kiểm cũ', may.indexOf("đã có ghế khác dùng' . $noi . $an") >= 0 && may.indexOf('"SELECT id FROM $bang WHERE ma=%s LIMIT 1", $ma_moi') >= 0);
t('coso_co_ghe() có (GROUP BY coso_id, kể cả ghế ẩn) và kho VietQR lọc theo nó', /public static function coso_co_ghe\(\)/.test(may) && /WHERE coso_id > 0 GROUP BY coso_id/.test(may) && vqr.indexOf('VHG_May::coso_co_ghe()') >= 0);
t('luu_coso "đã có": nói rõ 0 ghế nằm trong khối gập + đã cập nhật gì', may.indexOf('CHƯA CÓ GHẾ nào, nên nó nằm trong khối gập') >= 0 && may.indexOf("' Đã cập nhật ' . implode( ', ', $da )") >= 0);
const vh = fs.readFileSync('vhcp-ghe/vhcp-ghe.php', 'utf8'); const ver = (vh.match(/define\( 'VHG_VERSION', '([\d.]+)' \)/) || [])[1] || '';
const ge = (a, b) => { const x = a.split('.').map(Number), y = b.split('.').map(Number); for (let i = 0; i < 3; i++) { if ((x[i] || 0) !== (y[i] || 0)) return (x[i] || 0) > (y[i] || 0); } return true; };
t('phiên bản ≥ 2.147.0 và header = VHG_VERSION = BAN (không neo cứng — bản sau vẫn phải qua)', ge(ver, '2.147.0') && vh.indexOf(' * Version:           ' + ver) >= 0 && fs.readFileSync('vhcp-ghe/includes/class-vhg-baocao.php', 'utf8').indexOf("const BAN = '" + ver + "'") >= 0, ver);

console.log('── 4. Chạy qlThemTaiCho_ + csHangCapNhat_ với DOM giả ──────────');
global.L = vi => vi;
global.esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
global.QL_BAO = ''; global.QL_NHAY_MA = '';
global.D = { coso: [{ id: 5, ten: 'GO THỦ DẦU MỘT' }], may: [{ ma: '80016', ten: 'GO-TDM-1', coso: 'GO THỦ DẦU MỘT', an: 0 }] };
let veLai = [];
global.qlGheRender = () => { veLai.push(global.QL_NHAY_MA); };
const inp = { 'ma-moi': { value: '80020', focus() { this.daFocus = true; } }, 'ma-ten': { value: 'GO-TDM-5' } };
const td1 = { firstChild: { nodeType: 3, nodeValue: '2 ' }, textContent: '2 (+1 ẩn)' };
const td4 = { innerHTML: '<span class="mut">—</span>' };
const tr = { children: [{}, td1, {}, {}, td4] };
const a = { getAttribute: () => 'GO THỦ DẦU MỘT', closest: () => tr };
global.document = { getElementById: id => inp[id] || null, querySelectorAll: () => [a] };
eval(lay('qlThemTaiCho_')); eval(lay('csHangCapNhat_'));
qlThemTaiCho_({ ok: true, ma: '80020', ten: 'GO-TDM-5', thong_bao: 'Đã thêm ghế 80020 (GO-TDM-5).' }, '80020', '5', 'GO-TDM-5');
t('D.may có thêm ghế mới đủ khoá (ma, ten, coso, an=0, ten_goi, anLuc…)', D.may.length === 2 && D.may[1].ma === '80020' && D.may[1].ten === 'GO-TDM-5' && D.may[1].coso === 'GO THỦ DẦU MỘT' && D.may[1].an === 0 && 'ten_goi' in D.may[1] && 'anLuc' in D.may[1] && 'tm_chu' in D.may[1], D.may[1]);
t('vẽ lại bảng ghế ĐÚNG MỘT lần, lúc đó QL_NHAY_MA = mã mới (nhảy tới trang của nó)', veLai.length === 1 && veLai[0] === '80020', veLai);
t('không alert — câu báo nằm ở QL_BAO (dải xanh trong bảng)', QL_BAO === 'Đã thêm ghế 80020 (GO-TDM-5).', QL_BAO);
t('ô mã + ô tên trống lại, con trỏ về ô mã', inp['ma-moi'].value === '' && inp['ma-ten'].value === '' && inp['ma-moi'].daFocus === true);
t('hàng địa điểm: Số ghế 2 → 3 tại chỗ (giữ khoảng trắng trước "(+1 ẩn)")', td1.firstChild.nodeValue === '3 ', td1.firstChild.nodeValue);
t('hàng địa điểm: cột Mã ghế từ "—" thành ô mã 80020 kèm tên', /<code[^>]*>80020<\/code>/.test(td4.innerHTML) && td4.innerHTML.indexOf('GO-TDM-5') >= 0 && td4.innerHTML.indexOf('—') < 0, td4.innerHTML);
inp['ma-moi'].value = '80021';
qlThemTaiCho_({ ok: true, ma: '80021', ten: '', thong_bao: 'Đã thêm ghế 80021.' }, '80021', '5', '');
t('ghế thứ hai: NỐI thêm ô mã (giữ 80020), Số ghế 4', /80020/.test(td4.innerHTML) && /80021/.test(td4.innerHTML) && td1.firstChild.nodeValue === '4 ' && D.may.length === 3, td4.innerHTML);
global.document.querySelectorAll = () => [{ getAttribute: () => '__none__', closest: () => tr }];
qlThemTaiCho_({ ok: true, ma: '9999', ten: '' }, '9999', '0', '');
t('cơ sở id 0 → coso rỗng (chưa gán), sửa hàng data-csxem="__none__"', D.may[3].coso === '' && /9999/.test(td4.innerHTML));
t('máy chủ không trả thong_bao → tự ghép câu', QL_BAO === 'Đã thêm ghế 9999.', QL_BAO);
qlThemTaiCho_({ ok: true, ma: '80022', ten: 'GO TDM 7' }, '80022', '5', 'go tdm 7');
t('lấy TÊN máy chủ trả về (đã chuẩn hoá), không lấy bản gõ thô', D.may[4].ten === 'GO TDM 7');
global.document.querySelectorAll = () => [];
qlThemTaiCho_({ ok: true, ma: '80023', ten: '' }, '80023', '5', '');
t('không thấy hàng địa điểm (tab khác) → vẫn ghi D.may, không nổ', D.may[5].ma === '80023');
const td1b = { firstChild: null, textContent: '7' }; const trb = { children: [{}, td1b, {}, {}, { innerHTML: '' }] };
global.document.querySelectorAll = () => [{ getAttribute: () => 'GO THỦ DẦU MỘT', closest: () => trb }];
qlThemTaiCho_({ ok: true, ma: '80024', ten: '' }, '80024', '5', '');
t('ô Số ghế không có nút văn bản → cộng theo textContent', td1b.textContent === '8', td1b.textContent);

console.log('── 5. csGoiYMa_ — gõ mã ghế vào ô tìm địa điểm ─────────────────');
eval(lay('kdJS')); eval(lay('csGoiYMa_'));
D.may.push({ ma: '80822', ten: 'VC-LVV-9', coso: 'VINCOM LÊ VĂN VIỆT', an: 1 });
const gy = csGoiYMa_('80822', 0);
t('nói ghế đang ở đâu + [đã ẩn] + chỉ sang ô Tìm ghế', gy.indexOf('80822 (VC-LVV-9) → VINCOM LÊ VĂN VIỆT [đã ẩn]') >= 0 && gy.indexOf('Tìm ghế theo mã / tên') >= 0, gy);
t('có hàng địa điểm khớp (con>0) thì chỉ kể ghế, không lặp lời hướng dẫn', csGoiYMa_('80822', 1).indexOf('Tìm ghế theo') < 0 && csGoiYMa_('80822', 1).indexOf('80822') >= 0);
t('không mã nào khớp → chuỗi rỗng', csGoiYMa_('zzz', 0) === '' && csGoiYMa_('', 0) === '');
t('tìm theo tên ghế cũng ra', csGoiYMa_(kdJS('vc-lvv-9'), 0).indexOf('80822') >= 0);
D.may.push({ ma: '?ABC', ten: '', coso: '', an: 0 });
t('ghế tự hiện (mã bắt đầu bằng ?) bị bỏ qua', csGoiYMa_('abc', 0) === '');
for (let i = 0; i < 5; i++) D.may.push({ ma: '7770' + i, ten: '', coso: 'X', an: 0 });
t('tối đa 3 ghế', (csGoiYMa_('7770', 0).match(/→/g) || []).length === 3);

console.log('───────────────────────────────────────────────────────────────');
if (truot.length) { console.log('✗ HỎNG ' + truot.length + ' / ' + (dat + truot.length)); process.exit(1); }
console.log('✓ SẠCH — ' + dat + ' phép');
