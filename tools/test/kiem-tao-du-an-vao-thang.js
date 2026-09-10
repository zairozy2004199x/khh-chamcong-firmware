/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TẠO DỰ ÁN XONG LÀ VÀO THẲNG CHỖ NHẬP.
 *
 * Anh Thắng 10/09/2026: *"tạo dự án thì tự ra đơn để nhập luôn"*.
 *
 * =============================================================================================
 * 🔴 KHỐI CHI TIẾT NẰM NGAY DƯỚI BẢNG DANH SÁCH. Danh sách dài chục dòng thì tạo xong người
 *    dùng chẳng thấy gì đổi ngoài một dòng toast ở góc phải — tưởng chưa được nên bấm "Tạo dự
 *    án" lần nữa. Ảnh anh Thắng gửi có "ADV GO! AN LẠC" BA dòng và "FUNZONE VŨNG TÀU" HAI dòng
 *    cùng loại, đúng vì lẽ đó. Và dọn thì không dọn nổi: hai dòng giống hệt nhau, mỗi dòng đã
 *    nhập vài khoản, không ai dám xoá dòng nào.
 *
 * 🔴 KÉO MỘT LẦN LÀ CHƯA ĐỦ. `createDuAnUI()` gọi `loadDuAn()` và `openDuAn()` SONG SONG; danh
 *    sách vẽ lại sau thì khối chi tiết trôi xuống, cú kéo lúc nãy thành kéo hụt. Phải kéo lại
 *    khi danh sách vẽ xong — và chỉ MỘT lần, kẻo đổi bộ lọc cũng bị giật xuống dưới.
 *
 * ⚠️ CHẠY THẬT các hàm bốc từ mã nguồn, không dò chuỗi.
 *
 * Chạy: node tools/test/kiem-tao-du-an-vao-thang.js
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
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

/* ── BỆ ĐỠ ─────────────────────────────────────────────────────────────────────────────────
   Ghi lại MỌI thứ hàm thật chạm vào: cuộn mấy lần, cuộn tới đâu, con trỏ đặt ở đâu, gọi máy
   chủ với tham số gì. Không hàm nào bị thay bằng bản giả — thay là bỏ mất đúng chỗ cần soi. */
function dungBe() {
  const NK = { cuon: [], focus: [], tao: null, mo: [], toast: [], confirm: [], hienChiTiet: true };
  const O = id => ({
    _id: id, style: { display: id === 'daDetailCard' ? (NK.hienChiTiet ? '' : 'none') : '' },
    value: '', textContent: '', innerHTML: '',
    focus(opt) { NK.focus.push({ id, preventScroll: !!(opt && opt.preventScroll) }); },
    scrollIntoView(opt) { NK.cuon.push({ id, block: (opt && opt.block) || '' }); },
  });
  const KHO = {};
  const moi = {
    DA_CUR: null, DA_ITEMS: [], DA_CHO_KEO: false,
    CURUSER: { name: 'KT', role: 'Nhân viên', boPhan: 'Kỹ thuật' },
    el: id => (KHO[id] = KHO[id] || O(id)),
    loading: () => {},
    toast: (k, m) => NK.toast.push([k, m]),
    confirm: m => { NK.confirm.push(m); return moi._dongY; },
    _dongY: true,
    _log: () => {},
    loadDuAn: () => { NK.taiLai = (NK.taiLai || 0) + 1; },
    renderDuAnDetail: () => {},
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; },
      withFailureHandler() { return this; },
      createDuAn(loai, ten) { NK.tao = { loai, ten }; this._ok({ success: true, maDA: 'DA9', ten }); },
      getDuAn(maDA) { NK.moDuoc = maDA; this._ok({ success: true, maDA }); },
    } } },
    NK,
  };
  moi.window = moi;
  return { moi, NK, KHO };
}
const nap = (moi, tens) => new Function('moi', `with(moi){ ${tens.map(boc).join('\n')}
  return { ${tens.join(', ')} }; }`)(moi);

/* ── 1. TẠO XONG LÀ MỞ, VÀ MỞ ĐÚNG DỰ ÁN VỪA TẠO ───────────────────────────────────────── */
{
  const { moi, NK, KHO } = dungBe();
  const F = nap(moi, ['_daKeoToi', 'openDuAn', 'createDuAnUI']);
  KHO['daTen'] = KHO['daTen'] || moi.el('daTen'); moi.el('daTen').value = ' Aeon Bình Tân ';
  moi.el('daLoai').value = 'Setup lắp đặt';
  F.createDuAnUI();
  t('🔴 tạo xong MỞ LUÔN dự án vừa tạo', NK.moDuoc === 'DA9', NK);
  t('   gọi máy chủ đúng tên đã gõ (đã cắt khoảng trắng)',
    NK.tao && NK.tao.ten === 'Aeon Bình Tân', NK.tao);
  t('🔴 và KÉO màn hình tới khối chi tiết', NK.cuon.some(x => x.id === 'daDetailCard'), NK.cuon);
  t('   kéo tới ĐẦU khối (thấy tên dự án), không phải giữa form',
    NK.cuon.every(x => x.block === 'start'), NK.cuon);
  /* Ô đầu tiên phải điền là Nội dung (anh Thắng 10/09/2026: *"Thay vào chỗ là Nội dung đơn"*). */
  t('🔴 đặt sẵn con trỏ ở ô đầu tiên phải điền', NK.focus.some(x => x.id === 'da_nd'), NK.focus);
  /* 🔴 focus KHÔNG được kéo theo ô của nó: kéo thì màn dừng giữa form, mất dòng tên dự án ở
     trên — người dùng không biết mình đang nhập cho dự án nào. */
  t('🔴 con trỏ KHÔNG tự kéo màn theo ô (preventScroll)',
    NK.focus.length > 0 && NK.focus.every(x => x.preventScroll), NK.focus);
  t('   ô tên dự án được dọn sạch cho lần sau', moi.el('daTen').value === '', moi.el('daTen').value);
  t('   toast nói rõ nhập ở đâu', NK.toast.some(x => /bên dưới/.test(x[1])), NK.toast);
  t('   vẫn tải lại danh sách', NK.taiLai === 1, NK.taiLai);
}

/* ── 2. KÉO LẠI KHI DANH SÁCH VẼ XONG — VÀ CHỈ MỘT LẦN ─────────────────────────────────
   ⚠️ CHẠY THẬT `renderDuAnList()`. Bản trước của bài này tự viết lại đoạn `if(DA_CHO_KEO)`
      bằng new Function — tức là CHÉP mã chứ không chạy mã, nên đục thẳng chốt ấy trong
      app.html bài kiểm vẫn xanh. Phá thử bắt được, và đây là cách sửa. */
{
  const { moi, NK } = dungBe();
  Object.assign(moi, {
    DA_TRANG: 1, DA_MOI_TRANG: 10,
    DA_ITEMS: [{ maDA: 'DA9', ten: 'Aeon', loai: 'Setup lắp đặt', trangThai: 'Đang làm', tongDuToan: 0, tongThucTe: 0, chenh: 0 }],
    esc: x => String(x == null ? '' : x), money: x => String(x), daBadge: () => '',
  });
  const F = nap(moi, ['_daKeoToi', 'openDuAn', '_daVePager', 'daDoiTrang', 'renderDuAnList']);
  moi.renderDuAnList = F.renderDuAnList; moi._daKeoToi = F._daKeoToi;
  F.openDuAn('DA9', true);
  t('🔴 mở kèm cờ nhập luôn → còn nợ một cú kéo', moi.DA_CHO_KEO === true);
  const truoc = NK.cuon.filter(x => x.id === 'daDetailCard').length;
  F.renderDuAnList();          // danh sách vẽ lại SAU (createDuAnUI gọi song song)
  t('🔴 danh sách vẽ xong thì KÉO LẠI (cú kéo trước đã hụt)',
    NK.cuon.filter(x => x.id === 'daDetailCard').length === truoc + 1, NK.cuon);
  t('🔴 và chỉ một lần — cờ đã hạ', moi.DA_CHO_KEO === false);
  const sau = NK.cuon.filter(x => x.id === 'daDetailCard').length;
  F.renderDuAnList();          // đổi bộ lọc chẳng hạn
  t('🔴 lần vẽ sau KHÔNG giật xuống nữa',
    NK.cuon.filter(x => x.id === 'daDetailCard').length === sau, NK.cuon);
}
/* Bấm "Mở" ở danh sách: kéo tới nơi, nhưng KHÔNG cướp con trỏ — người ta có thể chỉ muốn xem. */
{
  const { moi, NK } = dungBe();
  const F = nap(moi, ['_daKeoToi', 'openDuAn']);
  F.openDuAn('DA3');
  t('bấm "Mở" cũng kéo tới khối chi tiết', NK.cuon.some(x => x.id === 'daDetailCard'), NK.cuon);
  t('🔴 nhưng KHÔNG cướp con trỏ (chỉ xem thì đừng bắt nhập)', NK.focus.length === 0, NK.focus);
  t('   và không nợ cú kéo nào', moi.DA_CHO_KEO === false);
}
/* Khối đang đóng thì đừng kéo tới chỗ trống. */
{
  const { moi, NK } = dungBe();
  NK.hienChiTiet = false;
  const F = nap(moi, ['_daKeoToi']);
  F._daKeoToi(true);
  t('khối chi tiết đang đóng → không kéo, không nổ', NK.cuon.length === 0 && NK.focus.length === 0);
}

/* ── 3. 🔴 CHẶN NHÂN BẢN DỰ ÁN ─────────────────────────────────────────────────────────── */
const dungTrung = () => {
  const be = dungBe();
  be.moi.DA_ITEMS = [{ maDA: 'DA1', ten: 'ADV GO! AN LẠC', loai: 'Setup lắp đặt', trangThai: 'Đang làm' }];
  be.F = nap(be.moi, ['_daKeoToi', 'openDuAn', 'createDuAnUI']);
  be.gõ = (ten, loai) => { be.moi.el('daTen').value = ten; be.moi.el('daLoai').value = loai || 'Setup lắp đặt'; };
  return be;
};
{
  const b = dungTrung(); b.moi._dongY = false;
  b.gõ('ADV GO! AN LẠC'); b.F.createDuAnUI();
  t('🔴 trùng tên + trùng loại → HỎI LẠI', b.NK.confirm.length === 1, b.NK.confirm);
  t('🔴 bấm Cancel thì KHÔNG tạo gì cả', b.NK.tao === null, b.NK.tao);
  t('   câu hỏi chỉ đường sang dòng có sẵn', /Mở/.test(b.NK.confirm[0] || ''), b.NK.confirm);
}
{
  const b = dungTrung(); b.moi._dongY = true;
  b.gõ('adv go! an lạc');   // khác hoa thường, vẫn là một
  b.F.createDuAnUI();
  t('🔴 khác hoa/thường vẫn tính là trùng', b.NK.confirm.length === 1, b.NK.confirm);
  t('   nhưng bấm OK thì vẫn tạo được (một gian làm hai đợt là chuyện thật)',
    b.NK.tao !== null, b.NK.tao);
}
{
  const b = dungTrung();
  b.gõ('ADV GO! AN LẠC', 'Tháo dỡ'); b.F.createDuAnUI();
  t('🔴 cùng tên nhưng KHÁC loại → không hỏi (Setup rồi Tháo dỡ là hai đợt thật)',
    b.NK.confirm.length === 0 && b.NK.tao !== null, b.NK);
}
{
  const b = dungTrung();
  b.moi.DA_ITEMS[0].trangThai = 'Đã đóng';
  b.gõ('ADV GO! AN LẠC'); b.F.createDuAnUI();
  t('🔴 dự án cũ ĐÃ ĐÓNG → không hỏi (đợt cũ xong rồi, đợt mới là đợt mới)',
    b.NK.confirm.length === 0 && b.NK.tao !== null, b.NK);
}
{
  const b = dungTrung();
  b.gõ('SNOW NHÀ TUYẾT TÂN PHÚ'); b.F.createDuAnUI();
  t('tên chưa có → tạo thẳng, không hỏi', b.NK.confirm.length === 0 && b.NK.tao !== null, b.NK);
}
{
  const b = dungTrung();
  b.gõ('   '); b.F.createDuAnUI();
  t('tên để trống → chối, không gọi máy chủ', b.NK.tao === null && b.NK.toast.length > 0, b.NK);
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: tạo xong vào thẳng chỗ nhập, và không đẻ ra dự án trùng.');
