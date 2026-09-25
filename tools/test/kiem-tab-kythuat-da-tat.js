/**
 * TAB KỸ THUẬT ĐÃ TẮT — VỚI MỌI VAI, VÀ KHÔNG GỠ LẠM
 * =============================================================================================
 *
 * Anh Thắng 22/09/2026: *"Bỏ cái chi phí kỹ thuật đi"*, rồi chốt *"bỏ hẳn luôn đi"*.
 *
 * 🔴 TẮT TAB, KHÔNG XOÁ DỮ LIỆU — đúng nếp năm tab trước (mkt · congtac · setup · sochi ·
 *    vhtuan). Sổ dự án vẫn còn nguyên, có dòng CHƯA XUẤT MISA; tab Xuất MISA và Tra theo mã
 *    vẫn đọc tới chúng.
 *
 * 🔴 VÀ PHẢI TẮT CẢ QUYỀN, KHÔNG CHỈ ẨN NÚT. `BO_TAB` chỉ giấu cái nút trên thanh tab;
 *    `QUYEN_TAB` thì vẫn nhận `vis` nguyên vẹn, nên `showPage('duan')` gõ thẳng từ thanh địa
 *    chỉ vẫn vào được — và `defaultPageFor()` còn có thể NÉM người dùng vào đúng trang vừa bỏ.
 *
 * ⚠️ CHẠY THẬT `applyPerms()` CHO TỪNG VAI, không soi chữ. Phép soi chữ xanh suốt cả ca "ẩn
 *    nút mà quên hạ quyền": chữ `duan:1` vẫn nằm trong bảng `vis`, `BO_TAB` vẫn có `duan`.
 *
 * Chạy: node tools/test/kiem-tab-kythuat-da-tat.js
 */
const fs = require('fs'), path = require('path'), vm = require('vm');
const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

let dat = 0; const truot = [];
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot.push(ten + (them === undefined ? '' : '\n      → ' + String(them).slice(0, 220)));
}

/* Vai nào cũng phải bị chặn. "Admin cũng không" là chốt quan trọng nhất: Admin xem hết mọi
   tab để giám sát, nên nếu tắt hụt thì chính Admin là người đầu tiên lọt vào. */
const VAI = ['Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên'];
/* Năm tab đã tắt từ trước — canh chiều NGƯỢC LẠI: đừng bật nhầm chúng lên trong lượt này. */
const TAT_TU_TRUOC = ['mkt', 'congtac', 'setup', 'sochi', 'vhtuan'];
/* Và mấy tab phải CÒN SỐNG — gỡ lạm ở đây là app mất đường làm việc chính. */
const PHAI_CON = { 'Admin': ['tongquan', 'don', 'duyet', 'qt', 'xuat', 'cauhinh'],
                   'Nhân viên': ['tongquan', 'don'] };

BAN.forEach(function (b) {
  const f = path.join(GOC, 'wordpress', b, 'templates/app.html');
  if (!fs.existsSync(f)) { t('có ' + b + '/app.html', false); return; }
  const h = fs.readFileSync(f, 'utf8');
  function than(ten) {
    const i = h.indexOf('  function ' + ten + '(');
    if (i < 0) return '';
    let j = h.indexOf('{', i), d = 0;
    for (let k = j; k < h.length; k++) {
      if (h[k] === '{') d++;
      else if (h[k] === '}') { d--; if (!d) return h.slice(i, k + 1); }
    }
    return '';
  }

  VAI.forEach(function (vai) {
    /* Trang giả: mỗi `el(id)` trả một object có `.style` và `.textContent`. */
    const O = {};
    const ctx = {
      el: id => (O[id] = O[id] || { style: {}, textContent: '', classList: { add() {}, remove() {} } }),
      CURUSER: { role: vai, roleGoc: vai, boPhan: '', name: 'X' },
      GL_THAT: null,
      _vaiLuat: () => vai,
      _bpCuaToi: () => '',
      _vaiGoc: () => vai,
      BP_VAO_DUAN: ['Văn phòng', 'Kỹ thuật'],
      _vaoDonCoSo: () => true,
      canDo: () => false,
      coCauHinh: () => vai === 'Admin',
      esc: x => String(x == null ? '' : x),
      glVeBar: () => {},
      QUYEN_TAB: null,
      document: { querySelectorAll: () => [] },
      /* Mấy hàm `applyPerms()` gọi mà bài này không đo — trả giá trị vô hại để nó chạy trọn.
         ⚠️ `_xuatMisaDuoc` trả TRUE: trả false là tab Xuất MISA tắt theo, rồi phép "tab phải
            CÒN SỐNG" đỏ vì BỆ ĐỠ chứ không vì mã hỏng. */
      _xuatMisaDuoc: () => true,
      _daChot: () => false,
      _kyTuDo: () => true,
      toast: () => {},
      _veNutCheHetPin: () => {},
    };
    ctx.window = ctx;
    vm.createContext(ctx);
    let no = '';
    try { vm.runInContext(than('applyPerms'), ctx); ctx.applyPerms(); }
    catch (e) { no = String(e && e.message); }
    t(b + ' · ' + vai + ': `applyPerms()` chạy được', no === '', no);
    if (no) return;

    const q = ctx.QUYEN_TAB || {};
    t('🔴 ' + b + ' · ' + vai + ': KHÔNG vào được tab Kỹ thuật (`duan`) nữa',
      !q.duan, JSON.stringify(q));
    TAT_TU_TRUOC.forEach(function (k) {
      t('   ' + b + ' · ' + vai + ': tab `' + k + '` vẫn tắt như trước', !q[k], k + '=' + q[k]);
    });
    (PHAI_CON[vai] || []).forEach(function (k) {
      t('🔴 ' + b + ' · ' + vai + ': tab `' + k + '` VẪN SỐNG — gỡ lạm là mất đường làm việc',
        !!q[k], k + '=' + q[k]);
    });
  });

  /* Lối vào phụ: nút "➜ Vào tab Kỹ thuật" ở khối tổng quan. Để lại là một cái nút dẫn vào
     trang không còn mở. */
  t('🔴 ' + b + ': gỡ nút "➜ Vào tab Kỹ thuật" ở khối tổng quan',
    h.indexOf('>➜ Vào tab Kỹ thuật<') < 0);
  /* ⚠️ NHƯNG KHỐI TỔNG QUAN THÌ GIỮ — nó chỉ ĐỌC, và số Setup/Tháo dỡ vẫn là số thật cần nhìn. */
  t('   nhưng khối tổng quan Kỹ thuật VẪN còn (nó chỉ đọc)', h.indexOf('id="tqKtCard"') >= 0);
  /* Dữ liệu không được xoá theo. */
  t('🔴 ' + b + ': sổ dự án KHÔNG bị xoá — tab Xuất MISA còn đọc tới nó',
    h.indexOf('da_line') >= 0 || h.indexOf('duAn') >= 0 || h.indexOf('maDuAn') >= 0);
});

if (truot.length) {
  console.log('HỎNG: ' + truot.length);
  truot.forEach(x => console.log('  ✗ ' + x));
  console.log('ĐẠT: ' + dat);
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép — tab Kỹ thuật tắt với mọi vai, mấy tab kia còn nguyên, dữ liệu không mất.');
