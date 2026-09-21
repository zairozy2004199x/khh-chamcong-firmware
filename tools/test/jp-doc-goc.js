/*
 * CẦU NỐI CHO `kiem-jp-doc.php` — chạy CHÍNH mấy hàm trong mã gốc JP.
 *
 * 🔴 Nó NẠP THẲNG `goc/jp-capsule-v2/JP2_01_Core.gs`, không chép hàm sang đây. Chép sang là từ
 *    lúc ấy có hai bản sự thật: mã gốc đổi mà bản chép không đổi thì bài kiểm vẫn xanh, trong
 *    khi bản PHP đã lệch khỏi thứ đang chạy thật.
 *
 * `Utilities` của Apps Script không có ở node, nên dựng bản giả CHỈ cho `formatDate`, và chỉ
 * đủ hai khuôn mà mấy hàm này dùng. Múi giờ ghim 'Asia/Ho_Chi_Minh' y bản gốc.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

/* Nạp CẢ Config lẫn Core: mấy quy tắc "rơi về" (`jpCoDhTrung_`, `jpGiaTuMa_`…) nằm ở Config,
   còn Config lại dùng `jpStr_`/`jpNum_` của Core. Cả hai chỉ khai hàm và biến nên nạp chung
   một ngữ cảnh là đủ — không câu nào tự chạy lúc nạp. */
const thu_muc = path.join(__dirname, '..', '..', 'goc', 'jp-capsule-v2');
const ds_tep = ['JP2_01_Core.gs', 'JP2_00_Config.gs', 'JP2_04_TinhToan.gs'].map(t => path.join(thu_muc, t));
const tep = ds_tep[0];

function hai_so(n) { return ('0' + n).slice(-2); }
const Utilities = {
  formatDate(d, tz, fmt) {
    const p = new Intl.DateTimeFormat('en-GB', {
      timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hour12: false,
    }).formatToParts(d).reduce((a, x) => (a[x.type] = x.value, a), {});
    const ngay = `${p.year}-${p.month}-${p.day}`;
    return fmt === 'yyyy-MM-dd HH:mm' ? `${ngay} ${p.hour}:${p.minute}` : ngay;
  },
};

/* Mấy lớp Apps Script khác chỉ cần TỒN TẠI để tệp nạp trôi — không hàm nào dưới đây gọi tới. */
const hop = {
  Utilities, console,
  SpreadsheetApp: {}, PropertiesService: {}, DriveApp: {}, LockService: {}, Session: {},
};
vm.createContext(hop);
for (const t of ds_tep) vm.runInContext(fs.readFileSync(t, 'utf8'), hop, { filename: t });

const ten = process.argv[2];
const vao = JSON.parse(process.argv[3]);
const ham = {
  num: 'jpNum_', str: 'jpStr_', blank: 'jpBlank_', num_hoac_trong: 'jpNumOrBlank_',
  so_anh: 'jpSoAnh_', ngay: 'jpDate_', ngay_gio: 'jpNgayGio_', dmy: 'jpDMY_',
  norm: 'jpNorm_', money: 'jpMoney_',
  /* Quy tắc "rơi về" của danh mục — xem `class-vhjp-cau-hinh.php`. */
  bc_mau: 'jpBcMau_', co_dh_trung: 'jpCoDhTrung_', chon_gia_xung: 'jpChonGiaXung_',
  gia_tu_ma: 'jpGiaTuMa_', dvt: 'jpDvt_',
  /* Tính tiền — xem `class-vhjp-tinh.php`. */
  gia_xung: 'jpGiaXung_', gia_dong: 'jpGiaDong_', lech_tm: 'jpLechTM_',
  hoan_theo_ma: 'jpHoanTheoMa_', dong_may_tien: 'jpCalcMoneyRow_',
  dong_may_xu: 'jpCalcCoinRow_', dong_ton_xu: 'jpCalcStockRow_',
  dong_kho_ngoai: 'jpCalcNgoaiRow_', dong_may_tach: 'jpCalcMayRow_',
  dong_hang_tach: 'jpCalcHangRow_', may_go_tay: 'jpMayGoTay_', dong: 'jpCalcRow_',
}[ten];
if (!ham) { console.error('không biết hàm ' + ten); process.exit(2); }
if (typeof hop[ham] !== 'function') { console.error('mã gốc không có ' + ham); process.exit(3); }

process.stdout.write(JSON.stringify(vao.map(a => hop[ham].apply(null, a))));
