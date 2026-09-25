/**
 * KHỐI "🗂 BỘ PHẬN" ĐÃ GỠ KHỎI CẤU HÌNH — VÀ DỮ LIỆU KHÔNG ĐƯỢC MẤT THEO
 * =============================================================================================
 *
 * Anh Thắng 22/09/2026, gửi ảnh khối ấy: *"bỏ bộ phận đi"*.
 *
 * Nó là mảnh cuối của trục Bộ phận. Cột Bộ phận rời bảng Loại chi phí hôm 21/09 khi anh chốt
 * *"bỏ tích bộ phận đi, mà tích theo vai trò"*; từ đó khối này chỉ còn dùng để khai một danh
 * mục mà không còn bảng nào đọc tới — bấm Lưu xong không thấy gì đổi, vì thật sự không có gì.
 *
 * 🔴 BÀI NÀY CANH HAI CHIỀU, và chiều thứ hai mới là chiều nguy:
 *      · GỠ RỒI  — không còn nút, không còn hàm, không còn id mồ côi trong menu;
 *      · NHƯNG DỮ LIỆU CÒN — `saveCfgTkNoMx()` vẫn chép cột `boPhan` nguyên vẹn, và ô Bộ phận
 *        ở bảng 🔐 Người dùng vẫn chạy. Gỡ nhầm sang chiều ấy là mỗi lượt Lưu bảng Loại chi
 *        phí xoá sạch cột boPhan của cả trăm dòng — im lặng, và không lấy lại được.
 *
 * Chạy: node tools/test/kiem-go-khoi-bo-phan.js
 */
const fs = require('fs'), path = require('path');
const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

let dat = 0; const truot = [];
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot.push(ten + (them === undefined ? '' : '\n      → ' + JSON.stringify(them).slice(0, 300)));
}

/* Tước chú thích trước khi soi — bia mộ của chính lượt gỡ này có nhắc tên mấy hàm đã bỏ, soi
   cả tệp là bắt phải nó rồi kết luận ngược. Đã mắc đúng lỗi ấy ba lần trong phiên 22/09. */
function chiMa(h) {
  return h.replace(/<!--[\s\S]*?-->/g, '')
          .replace(/\/\*[\s\S]*?\*\//g, '')
          .replace(/(^|[^:])\/\/(?!\/)[^\n]*/g, '$1');
}

BAN.forEach(ban => {
  const f = path.join(GOC, 'wordpress', ban, 'templates/app.html');
  if (!fs.existsSync(f)) { t(`có ${ban}/app.html`, false); return; }
  const ma = chiMa(fs.readFileSync(f, 'utf8'));

  // ── 1. GỠ RỒI ───────────────────────────────────────────────────────────────────────────
  t(`🔴 ${ban}: không còn nút "＋ Thêm bộ phận"`, ma.indexOf('Thêm bộ phận') < 0);
  t(`🔴 ${ban}: không còn nút "💾 Lưu bộ phận"`, ma.indexOf('Lưu bộ phận') < 0);
  ['renderCfgBp', 'addCfgBp', 'saveCfgBp'].forEach(h => {
    t(`${ban}: không còn hàm ${h}()`, ma.indexOf('function ' + h) < 0);
  });
  t(`🔴 ${ban}: không còn chỗ nào GỌI mấy hàm ấy — gọi hàm không còn là màn chết đứng`,
    ma.indexOf('renderCfgBp()') < 0 && ma.indexOf('saveCfgBp()') < 0, ma.length);
  t(`${ban}: không còn bảng cfgBpBody`, ma.indexOf('cfgBpBody') < 0);
  /* Menu Cấu hình đi tìm từng id; để lại một id mồ côi là nó âm thầm bỏ qua và không ai biết
     nhóm ấy từng có bốn khối. */
  t(`🔴 ${ban}: menu Cấu hình KHÔNG còn trỏ vào 'bpCard'`, ma.indexOf("'bpCard'") < 0, ma.length);

  // ── 2. 🔴 NHƯNG DỮ LIỆU KHÔNG MẤT ───────────────────────────────────────────────────────
  const i = ma.indexOf('function saveCfgTkNoMx');
  const luu = i < 0 ? '' : ma.slice(i, i + 3000);
  t(`🔴 ${ban}: lượt Lưu bảng Loại chi phí VẪN chép cột boPhan — bỏ là xoá sạch cả trăm dòng`,
    /boPhan\s*:/.test(luu), luu.slice(0, 400));
  /* ⚠️ CÓ DẤU `(` TRONG PHÉP SOI. Không có nó thì đổi tên hàm thành `_bpSelDaGo` vẫn khớp —
     lượt đục "gỡ nhầm cả ô Bộ phận" đi lọt đúng bằng cửa ấy. Soi tên hàm bao giờ cũng phải
     kèm dấu mở ngoặc, không thì mọi tên DÀI HƠN đều tính là khớp. */
  t(`🔴 ${ban}: ô Bộ phận ở bảng Người dùng vẫn còn — nó còn bó tầm nhìn kế toán`,
    ma.indexOf('function _bpSel(') >= 0);
  t(`${ban}: và danh sách bộ phận vẫn đọc được`, ma.indexOf('function _bpDs(') >= 0);
});

if (truot.length) {
  console.log('HỎNG: ' + truot.length);
  truot.forEach(x => console.log('  ✗ ' + x));
  console.log('ĐẠT: ' + dat);
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép — khối Bộ phận đã gỡ ở cả bốn bản, và dữ liệu boPhan còn nguyên.');
