/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KHUNG TRỤC PHÂN TÍCH — PHÍA MÀN, CHẠY THẬT.
 *
 * Anh Thắng 22/09/2026: *"Nếu tích vào thì gọi là chi phí setup ban đầu. Còn ko tích thì mặc
 * định và chi phí vận hành"*, rồi sau lượt khảo mã nguồn mở: *"Em làm thử anh xem"*.
 *
 * =============================================================================================
 * 🔴 HAI THỨ BÀI NÀY PHẢI CHỨNG MINH
 * =============================================================================================
 * 1. **Ô tích không được đảo nghĩa.** Nó quyết một khoản tiền nằm ở nhóm nào khi gom báo cáo.
 *    Đảo nghĩa thì chẳng có gì kêu: đơn vẫn lưu, màn vẫn vẽ, chỉ là tiền setup nằm trong cột
 *    vận hành. Và nó hỏng theo kiểu KHÔNG AI BẤM GÌ CẢ: kế toán mở một dòng cũ (rỗng) ra sửa ô
 *    khác, ô tích tự sáng vì màn đọc rỗng khác máy chủ, họ bấm Lưu — vận hành hoá thành setup.
 *
 * 2. **Thêm một trục chỉ là thêm một dòng khai.** Đó là cả điểm của khung. Bài này bơm vào một
 *    trục THỨ HAI (kiểu ô chọn) qua gói khởi động giả và đòi: form tự mọc thêm ô · lượt Lưu tự
 *    mang giá trị đi · mở dòng cũ tự đổ lại — mà KHÔNG sửa một dòng nào trong `app.html`.
 *    Không có phép này thì "khung" chỉ là một cách viết khác của cùng một trục gõ cứng.
 *
 * ⚠️ BỐC HÀM THẬT TỪ `app.html` RA CHẠY, và đối chiếu với HẰNG THẬT trong PHP.
 *
 * Chạy: node tools/test/kiem-o-tich-setup.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path'), vm = require('vm');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? ('\n      → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
/* 🔴 DÒ THUỘC TÍNH `checked` CỦA THẺ, ĐỪNG DÒ CHỮ "checked" TRONG CẢ CHUỖI: lời gọi
   `onchange="trucDoi('giaiDoan',this.checked)"` chứa đúng bảy chữ ấy. */
function dangSang(h) { return /<input[^>]*\schecked/.test(String(h || '')); }

const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

BAN.forEach(function (ban) {
  const fH = path.join(GOC, 'wordpress', ban, 'templates/app.html');
  if (!fs.existsSync(fH)) { t('có ' + ban + '/app.html', false); return; }
  const HTML = fs.readFileSync(fH, 'utf8');
  const PHP = fs.readFileSync(path.join(GOC, 'wordpress', ban, 'includes/class-vhcp-don.php'), 'utf8');

  const boc = function (ten) {
    const i = HTML.indexOf('function ' + ten + '(');
    t(ban + ': bốc được ' + ten + '()', i >= 0);
    return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
  };
  const HAM = ['_gdSetup', '_gdVh', '_trucDs', '_truc', '_trucDoc', 'trucVeMacDinh',
    'trucTuDong', 'trucGui', 'veTrucs', 'trucDoi', 'trucChon'].map(boc).join('\n');

  /* ═══ 1. 🔴 HAI CHUỖI PHẢI KHỚP MÁY CHỦ TỪNG KÝ TỰ ═══════════════════════════════
   * `VHCP_Truc::chuan()` so chuỗi. Lệch một dấu là mọi dòng mới ngã về rỗng — đơn lưu bình
   * thường, không câu lỗi nào, và cột giai đoạn trống trơn. */
  const mS = PHP.match(/const GIAI_DOAN_SETUP = '([^']*)';/);
  const mV = PHP.match(/const GIAI_DOAN_VH    = '([^']*)';/);
  t(ban + ': đọc được hai hằng bên máy chủ', !!(mS && mV), [mS && mS[1], mV && mV[1]]);
  const SETUP = mS ? mS[1] : 'Setup';
  const VH = mV ? mV[1] : 'Vận hành';

  /* Bệ đỡ KHÔNG có BOOT — đúng cảnh gói khởi động của bản CŨ. Lúc ấy màn phải rơi về đúng trục
     Giai đoạn với đúng hai chuỗi máy chủ đang dùng, không phải một chuỗi tự chế. */
  function be(BOOT) {
    const c = { BOOT: BOOT, KHO: {},
      esc: function (x) { return String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); } };
    c.el = function (id) { return (c.KHO[id] = c.KHO[id] || { innerHTML: '' }); };
    vm.createContext(c);
    vm.runInContext(HAM, c);
    return c;
  }
  {
    const c = be(undefined);
    const ds = c._trucDs();
    teq('🔴 ' + ban + ': gói khởi động bản CŨ → vẫn dựng lại đúng trục Giai đoạn', 1, ds.length);
    teq('   với đúng hai chuỗi của máy chủ', [SETUP, VH], ds[0].gtri);
    teq('   và mặc định là Vận hành', VH, ds[0].macDinh);
    teq('   tích vào = Setup', SETUP, ds[0].bat);
  }

  /* ═══ 2. 🔴 RỖNG = MẶC ĐỊNH, HAI BÊN CÙNG LUẬT ══════════════════════════════════ */
  const c1 = be({ trucDs: [{ ma: 'giaiDoan', khoa: 'giaiDoan', nhan: 'Giai đoạn', kieu: 'tich',
    gtri: [SETUP, VH], macDinh: VH, bat: SETUP, nhanBat: '🛠 setup', phu: 'không tích = vận hành' }] });
  const tr1 = c1._truc('giaiDoan');
  teq('🔴 ' + ban + ': dòng cũ (rỗng) ĐỌC ra VẬN HÀNH — ô tích phải TỐI', VH, c1._trucDoc(tr1, ''));
  teq('   `null` cũng vậy', VH, c1._trucDoc(tr1, null));
  teq('   `undefined` cũng vậy', VH, c1._trucDoc(tr1, undefined));
  teq('🔴 ' + ban + ': dòng đã khai Setup thì ĐỌC ra Setup', SETUP, c1._trucDoc(tr1, SETUP));
  teq('   khoảng trắng thừa vẫn nhận', SETUP, c1._trucDoc(tr1, '  ' + SETUP + ' '));
  teq('   khác hoa thường vẫn nhận', SETUP, c1._trucDoc(tr1, SETUP.toUpperCase()));
  teq('🔴 ' + ban + ': giá trị lạ về MẶC ĐỊNH — không có nhóm thứ ba nào cả', VH, c1._trucDoc(tr1, 'Bảo trì'));
  teq('   và "Set up" rời chữ KHÔNG phải setup', VH, c1._trucDoc(tr1, 'Set up'));

  /* ═══ 3. VẼ VÀ BẤM THẬT ═════════════════════════════════════════════════════════ */
  c1.trucVeMacDinh(); c1.veTrucs();
  teq('🔴 ' + ban + ': mặc định là VẬN HÀNH', { giaiDoan: VH }, c1.TRUC);
  const h0 = c1.KHO.f_trucBox.innerHTML;
  t('🔴 ' + ban + ': ô tích vẽ ra ở trạng thái TỐI', !dangSang(h0), h0);
  t('   là một ô tích THẬT, không phải nút giả', /<input type="checkbox" data-truc="giaiDoan"/.test(h0), h0);
  t('   nhãn bọc cả ô — chạm vào chữ cũng tích được', /<label[^>]*>[\s\S]*<input type="checkbox"/.test(h0), h0);
  t('   và có dòng nhắc nói rõ không tích nghĩa là gì', h0.indexOf('không tích = vận hành') >= 0, h0);

  c1.trucDoi('giaiDoan', true);
  teq('🔴 ' + ban + ': tích vào → SETUP', { giaiDoan: SETUP }, c1.TRUC);
  t('   và ô vẽ lại ở trạng thái SÁNG', dangSang(c1.KHO.f_trucBox.innerHTML), c1.KHO.f_trucBox.innerHTML);
  c1.trucDoi('giaiDoan', false);
  teq('🔴 ' + ban + ': bỏ tích → VẬN HÀNH, không quay về rỗng', { giaiDoan: VH }, c1.TRUC);
  t('   và ô vẽ lại ở trạng thái TỐI', !dangSang(c1.KHO.f_trucBox.innerHTML), c1.KHO.f_trucBox.innerHTML);

  /* ═══ 4. ĐI TRỌN VÒNG: dòng cũ rỗng → mở ra → gửi lên ═══════════════════════════
   * Đây là ca anh Thắng sẽ gặp thật tuần này: hàng trăm dòng cũ đều rỗng. */
  c1.trucTuDong({ giaiDoan: '' });
  teq('🔴 ' + ban + ': mở dòng cũ (rỗng) → lưu lại vẫn là VẬN HÀNH', { giaiDoan: VH }, c1.trucGui());
  c1.trucDoi('giaiDoan', true);
  teq('   tích vào rồi lưu thì thành SETUP', { giaiDoan: SETUP }, c1.trucGui());
  c1.trucTuDong({ giaiDoan: SETUP });
  teq('🔴 ' + ban + ': mở lại dòng đã khai Setup → ô tích SÁNG, lưu lại vẫn Setup',
    { giaiDoan: SETUP }, c1.trucGui());
  t('   và ô vẽ ra đúng trạng thái ấy', (c1.veTrucs(), dangSang(c1.KHO.f_trucBox.innerHTML)), '');

  /* ═══ 5. 🔴 THÊM MỘT TRỤC CHỈ LÀ THÊM MỘT DÒNG KHAI ═════════════════════════════
   * Cả điểm của khung nằm ở đây. Bơm một trục THỨ HAI qua gói khởi động — không sửa một dòng
   * nào trong `app.html` — rồi đòi form tự mọc ô, lượt Lưu tự mang đi, mở dòng cũ tự đổ lại.
   * Không có phép này thì "khung" chỉ là một cách viết khác của cùng một trục gõ cứng. */
  {
    const c = be({ trucDs: [
      { ma: 'giaiDoan', khoa: 'giaiDoan', nhan: 'Giai đoạn', kieu: 'tich',
        gtri: [SETUP, VH], macDinh: VH, bat: SETUP, nhanBat: '🛠 setup', phu: '' },
      { ma: 'nguonTien', khoa: 'nguonTien', nhan: 'Nguồn tiền', kieu: 'chon',
        gtri: ['Tiền mặt', 'Chuyển khoản', 'Thẻ'], macDinh: 'Chuyển khoản', bat: '', phu: '' },
    ] });
    c.trucVeMacDinh(); c.veTrucs();
    const h = c.KHO.f_trucBox.innerHTML;
    teq('🔴 ' + ban + ': trục thứ hai → form tự mọc đủ HAI ô, không sửa app.html', 2,
      (h.match(/data-truc="/g) || []).length);
    t('   trục kiểu "tích" vẽ ra ô tích', /<input type="checkbox" data-truc="giaiDoan"/.test(h), h);
    t('   trục kiểu "chọn" vẽ ra ô CHỌN, không phải ô tích', /<select data-truc="nguonTien"/.test(h), h);
    t('   ô chọn bày đủ mọi giá trị', (h.match(/<option /g) || []).length === 3, h);
    t('   và chọn sẵn đúng giá trị mặc định', /<option value="Chuyển khoản" selected>/.test(h), h);
    t('   mỗi trục một nhãn riêng', h.indexOf('>Giai đoạn</label>') >= 0 && h.indexOf('>Nguồn tiền</label>') >= 0, h);

    teq('🔴 ' + ban + ': lượt Lưu mang ĐỦ HAI trục đi',
      { giaiDoan: VH, nguonTien: 'Chuyển khoản' }, c.trucGui());
    c.trucChon('nguonTien', 'Tiền mặt');
    teq('   đổi ô chọn thì lượt Lưu đổi theo', 'Tiền mặt', c.trucGui().nguonTien);
    t('   và KHÔNG đụng trục kia', c.trucGui().giaiDoan === VH, c.trucGui());
    /* 🔴 Giá trị lạ trên ô chọn (người ta sửa DOM bằng công cụ lập trình) về mặc định, không
       lọt xuống sổ thành một nhóm thứ tư. */
    c.trucChon('nguonTien', 'Vàng miếng');
    teq('🔴 ' + ban + ': giá trị lạ trên ô chọn về MẶC ĐỊNH', 'Chuyển khoản', c.trucGui().nguonTien);

    c.trucTuDong({ giaiDoan: SETUP, nguonTien: 'Thẻ' });
    teq('🔴 ' + ban + ': mở dòng cũ → đổ lại ĐỦ HAI trục',
      { giaiDoan: SETUP, nguonTien: 'Thẻ' }, c.trucGui());
    c.trucVeMacDinh();
    teq('   dọn form → cả hai về mặc định của CHÍNH nó',
      { giaiDoan: VH, nguonTien: 'Chuyển khoản' }, c.trucGui());
    /* ⚠️ Trục lạ bấm vào không được làm chết gì — một móc `vhcp_truc_ds` khai hỏng thì cùng
       lắm là ô ấy không ăn, không phải cả form chết. */
    t('⚠️ ' + ban + ': bấm một trục không có thì im lặng bỏ qua, không ném lỗi',
      (function () { try { c.trucDoi('khong-co', true); c.trucChon('khong-co', 'x'); return true; }
                     catch (e) { return false; } })(), '');
  }
});

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: ô tích không đảo nghĩa, và thêm một trục chỉ là thêm một dòng khai.');
