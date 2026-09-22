/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô TÍCH "CHI PHÍ SETUP BAN ĐẦU" — CHẠY THẬT, VÀ CANH HAI BÊN CÙNG LUẬT.
 *
 * Anh Thắng 22/09/2026: *"Nếu tích vào thì gọi là chi phí setup ban đầu. Còn ko tích thì mặc
 * định và chi phí vận hành"*.
 *
 * =============================================================================================
 * 🔴 CÁI HỎNG NGUY NHẤT Ở ĐÂY LÀ "TÍCH NGƯỢC"
 * =============================================================================================
 * Ô này quyết một khoản tiền nằm ở nhóm nào khi gom báo cáo. Đảo nghĩa của nó — vì lấy giá trị
 * theo CHỖ ĐỨNG trong danh sách, hay vì hai bên máy chủ/màn hiểu ô rỗng khác nhau — thì không
 * có gì kêu cả: đơn vẫn lưu, màn vẫn vẽ, chỉ là tiền setup nằm trong cột vận hành.
 *
 * Và nó hỏng theo kiểu KHÔNG AI BẤM GÌ CẢ: kế toán mở một dòng cũ (rỗng) ra sửa một ô khác, ô
 * tích tự sáng vì màn đọc rỗng khác máy chủ, họ bấm Lưu — khoản vận hành hoá thành setup.
 *
 * ⚠️ BỐC HÀM THẬT TỪ `app.html` RA CHẠY, và ĐỐI CHIẾU với hằng thật trong PHP.
 *
 * Chạy: node tools/test/kiem-o-tich-setup.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path'), vm = require('vm');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? ('\n      → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
/* 🔴 DÒ THUỘC TÍNH `checked` CỦA THẺ, ĐỪNG DÒ CHỮ "checked" TRONG CẢ CHUỖI. Cắn ngay lượt viết
   này: `onchange="doiGiaiDoan(this.checked)"` chứa đúng bảy chữ ấy, nên phép "ô đang tối" đỏ
   oan ở cả bốn bản — và nếu viết ngược lại thì nó xanh vĩnh viễn. */
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

  /* ═══ 1. 🔴 HAI CHUỖI PHẢI KHỚP MÁY CHỦ TỪNG KÝ TỰ ═══════════════════════════════
   * `giai_doan_chuan()` so chuỗi. Lệch một dấu là mọi dòng mới ngã về rỗng — đơn lưu bình
   * thường, không câu lỗi nào, và cột giai đoạn trống trơn. */
  const mS = PHP.match(/const GIAI_DOAN_SETUP = '([^']*)';/);
  const mV = PHP.match(/const GIAI_DOAN_VH    = '([^']*)';/);
  t(ban + ': đọc được hai hằng bên máy chủ', !!(mS && mV), [mS && mS[1], mV && mV[1]]);
  const SETUP = mS ? mS[1] : 'Setup';
  const VH = mV ? mV[1] : 'Vận hành';

  /* Bệ đỡ: KHÔNG có BOOT — đúng cảnh gói khởi động của bản CŨ. Lúc ấy màn phải rơi về đúng hai
     chuỗi mà máy chủ đang dùng, không phải một chuỗi tự chế. */
  const ctxLui = {};
  vm.createContext(ctxLui);
  vm.runInContext(boc('_gdSetup') + '\n' + boc('_gdVh') + '\n' + boc('_giaiDoanDoc'), ctxLui);
  teq('🔴 ' + ban + ': đường lui của màn khớp hằng máy chủ (chuỗi "setup")', SETUP, ctxLui._gdSetup());
  teq('🔴 ' + ban + ': và chuỗi "vận hành"', VH, ctxLui._gdVh());

  /* ═══ 2. 🔴 LẤY ĐÍCH DANH, KHÔNG LẤY THEO CHỖ ĐỨNG ═══════════════════════════════
   * Đảo hai phần tử trong `giaiDoanDs` một cái là mọi dòng mới đóng dấu ngược, im lặng. */
  {
    const c = { BOOT: { giaiDoanDs: [VH, SETUP], giaiDoanSetup: SETUP, giaiDoanVh: VH } };
    vm.createContext(c);
    vm.runInContext(boc('_gdSetup') + '\n' + boc('_gdVh'), c);
    teq('🔴 ' + ban + ': đảo thứ tự `giaiDoanDs` KHÔNG làm đảo nghĩa ô tích', [SETUP, VH],
      [c._gdSetup(), c._gdVh()]);
  }

  /* ═══ 3. 🔴 HAI BÊN CÙNG LUẬT ĐỌC: RỖNG = VẬN HÀNH ══════════════════════════════
   * Lệch một vế là dòng cũ mở ra thấy ô tích SÁNG, người ta lưu lại một cái và khoản vận hành
   * hoá thành chi phí setup — không ai bấm gì cả. */
  const ctx = { BOOT: { giaiDoanSetup: SETUP, giaiDoanVh: VH } };
  vm.createContext(ctx);
  vm.runInContext(boc('_gdSetup') + '\n' + boc('_gdVh') + '\n' + boc('_giaiDoanDoc'), ctx);
  const doc = ctx._giaiDoanDoc;
  teq('🔴 ' + ban + ': dòng cũ (rỗng) ĐỌC ra VẬN HÀNH — ô tích phải TỐI', VH, doc(''));
  teq('   `null` cũng vậy', VH, doc(null));
  teq('   `undefined` cũng vậy', VH, doc(undefined));
  teq('🔴 ' + ban + ': dòng đã khai Setup thì ĐỌC ra Setup', SETUP, doc(SETUP));
  teq('   khoảng trắng thừa vẫn nhận', SETUP, doc('  ' + SETUP + ' '));
  teq('   khác hoa thường vẫn nhận', SETUP, doc(SETUP.toUpperCase()));
  teq('🔴 ' + ban + ': giá trị lạ về VẬN HÀNH — không có nhóm thứ ba nào cả', VH, doc('Bảo trì'));
  teq('   và "Set up" rời chữ KHÔNG phải setup', VH, doc('Set up'));

  /* ═══ 4. VẼ VÀ BẤM THẬT ═════════════════════════════════════════════════════════ */
  const c2 = {
    BOOT: { giaiDoanSetup: SETUP, giaiDoanVh: VH },
    KHO: {},
  };
  c2.el = function (id) { return (c2.KHO[id] = c2.KHO[id] || { innerHTML: '' }); };
  vm.createContext(c2);
  vm.runInContext(boc('_gdSetup') + '\n' + boc('_gdVh') + '\n' + boc('_giaiDoanDoc') + '\n'
    + 'var GIAI_DOAN=_gdVh();\n' + boc('veGiaiDoan') + '\n' + boc('doiGiaiDoan')
    + '\nveGiaiDoan();', c2);

  /* 🔴 MẶC ĐỊNH LÀ VẬN HÀNH, VÀ Ô TÍCH PHẢI TỐI. Vẽ ra một ô đang sáng trong khi biến nói
     "vận hành" là màn nói một đằng, dữ liệu gửi đi một nẻo. */
  teq('🔴 ' + ban + ': mặc định là VẬN HÀNH', VH, c2.GIAI_DOAN);
  const h0 = c2.KHO.f_giaiDoan.innerHTML;
  t('🔴 ' + ban + ': và ô tích vẽ ra ở trạng thái TỐI', !dangSang(h0), h0);
  t('   là một ô tích THẬT, không phải nút giả', /<input type="checkbox" id="f_gdSetup"/.test(h0), h0);
  t('   nhãn bọc cả ô — chạm vào chữ cũng tích được', /<label[^>]*>[\s\S]*<input type="checkbox"/.test(h0), h0);
  t('   và nói rõ không tích nghĩa là gì', h0.indexOf('vận hành') >= 0, h0);

  c2.doiGiaiDoan(true);
  teq('🔴 ' + ban + ': tích vào → SETUP', SETUP, c2.GIAI_DOAN);
  t('   và ô vẽ lại ở trạng thái SÁNG', dangSang(c2.KHO.f_giaiDoan.innerHTML), c2.KHO.f_giaiDoan.innerHTML);
  c2.doiGiaiDoan(false);
  teq('🔴 ' + ban + ': bỏ tích → VẬN HÀNH, không quay về rỗng', VH, c2.GIAI_DOAN);
  t('   và ô vẽ lại ở trạng thái TỐI', !dangSang(c2.KHO.f_giaiDoan.innerHTML), c2.KHO.f_giaiDoan.innerHTML);

  /* ═══ 5. 🔴 ĐI TRỌN VÒNG: dòng cũ rỗng → mở ra sửa → gửi lên ═════════════════════
   * Đây là ca mà anh Thắng sẽ gặp thật trong tuần này: hàng trăm dòng cũ đều rỗng. */
  {
    const gd = doc('');                      // máy chủ/màn đọc dòng cũ
    teq('🔴 ' + ban + ': dòng cũ mở ra thì ô tích TỐI, và lưu lại vẫn là VẬN HÀNH', VH, gd);
    /* Và nếu kế toán tích vào rồi lưu, nó thành Setup — đúng một chiều, không tự đảo. */
    c2.GIAI_DOAN = gd; c2.doiGiaiDoan(true);
    teq('   tích vào rồi lưu thì thành SETUP', SETUP, c2.GIAI_DOAN);
  }
});

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: một ô tích, không tích = vận hành, và hai bên cùng một luật đọc.');
