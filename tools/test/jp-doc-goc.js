/*
 * CẦU NỐI CHO CÁC BÀI KIỂM JP — chạy CHÍNH mấy hàm trong mã gốc JP.
 *
 * 🔴 Nó NẠP THẲNG mã gốc ở `goc/jp-capsule-v2/`, không chép hàm sang đây. Chép sang là từ
 *    lúc ấy có hai bản sự thật: mã gốc đổi mà bản chép không đổi thì bài kiểm vẫn xanh, trong
 *    khi bản PHP đã lệch khỏi thứ đang chạy thật.
 *
 * `Utilities` của Apps Script không có ở node, nên dựng bản giả CHỈ cho `formatDate`, và chỉ
 * đủ hai khuôn mà mấy hàm này dùng. Múi giờ ghim 'Asia/Ho_Chi_Minh' y bản gốc.
 *
 * ---------------------------------------------------------------------------------------------
 * HAI CÁI GIẢ, VÀ VÌ SAO ĐẶT SEAM ĐÚNG CHỖ ẤY
 * ---------------------------------------------------------------------------------------------
 * Mấy hàm đọc báo cáo (`jpGetReport`, `jpMyReports`, `jpPrevClosing_`…) không thuần: chúng đọc
 * sổ và đòi người đăng nhập. Muốn chạy được bằng node thì phải giả hai thứ ấy — nhưng giả ở
 * chỗ nào là chuyện sống còn của bài kiểm:
 *
 *   · Giả `jpVals_` (một hàm, trả mảng hai chiều thô) ⇒ `jpRows_` · `jpFind_` · `jpFindOne_`
 *     VẪN LÀ MÃ GỐC THẬT. Lọc theo chuỗi, bỏ dòng rỗng, dựng object mới mỗi lượt — tất cả
 *     những nết ấy vẫn được đối chiếu.
 *     Giả thẳng `jpFind_` thì mất hết, mà `jpFind_` đúng là chỗ bản gốc từng sửa vì hiệu năng.
 *
 *   · Giả `jpAuth_` (trả sẵn người dùng) ⇒ `jpNeedNV_` · `jpNeedLoc_` · `jpIsKT_` · `jpIsNV_`
 *     vẫn là mã gốc. Phần thẻ phiên / PIN đã có bài kiểm riêng (`kiem-jp-auth.php`), lặp lại
 *     ở đây chỉ tốn chỗ; còn LUẬT QUYỀN thì phải chạy thật vì nó quyết định ai đọc được gì.
 *
 * Dùng:  node jp-doc-goc.js <tên hàm> <mảng các bộ tham số> [dữ liệu sổ] [người dùng]
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

/* Nạp CẢ Config lẫn Core: mấy quy tắc "rơi về" (`jpCoDhTrung_`, `jpGiaTuMa_`…) nằm ở Config,
   còn Config lại dùng `jpStr_`/`jpNum_` của Core. Các tệp đều chỉ khai hàm và biến nên nạp
   chung một ngữ cảnh là đủ — không câu nào tự chạy lúc nạp. */
const thu_muc = path.join(__dirname, '..', '..', 'goc', 'jp-capsule-v2');
const ds_tep = [
  'JP2_01_Core.gs', 'JP2_00_Config.gs', 'JP2_02_Auth.gs', 'JP2_04_TinhToan.gs',
  'JP2_05_BaoCao.gs', 'JP2_06_Duyet.gs', 'JP2_07_Anh.gs', 'JP2_10_Sua24h_NopTien.gs',
].map(t => path.join(thu_muc, t));

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
  CacheService: {}, UrlFetchApp: {}, HtmlService: {}, ScriptApp: {}, MailApp: {},
};
vm.createContext(hop);
for (const t of ds_tep) vm.runInContext(fs.readFileSync(t, 'utf8'), hop, { filename: t });

const ten = process.argv[2];
const vao = JSON.parse(process.argv[3]);
const so  = process.argv[4] ? JSON.parse(process.argv[4]) : null;
const ai  = process.argv[5] ? JSON.parse(process.argv[5]) : null;

/*
 * SỔ GIẢ — thay đúng `jpVals_`, giữ nguyên mọi hàm đọc phía trên nó.
 *
 * Dựng mảng hai chiều y như Sheets trả về: dòng đầu là header lấy từ `tabDef.cols` (đúng thứ
 * bản gốc tự tạo khi tab còn rỗng), các dòng sau xếp theo đúng thứ tự cột ấy.
 *
 * ⚠️ Ô thiếu để '' chứ không `undefined`: Sheets không bao giờ trả `undefined`, và `jpRows_`
 *    đếm ô rỗng để bỏ dòng trắng — trả `undefined` là đổi luôn phép đếm ấy.
 */
if (so) {
  hop.jpVals_ = function (tabDef) {
    const cols = tabDef.cols;
    const v = [cols.slice()];
    (so[tabDef.name] || []).forEach(function (o) {
      v.push(cols.map(function (c) { return (o[c] === undefined || o[c] === null) ? '' : o[c]; }));
    });
    hop.JP__HDR[tabDef.name] = cols.slice();
    return v;
  };
}

/* NGƯỜI DÙNG GIẢ — `jpAuth_` là cửa duy nhất, mọi hàm quyền phía sau vẫn chạy thật. */
if (ai) { hop.jpAuth_ = function () { return ai; }; }

/*
 * BẢNG CẢNH BÁO của mã gốc — không phải hàm, nên lấy riêng.
 *
 * 🔴 Có mặt ở đây để `kiem-jp-tinh.php` đối chiếu ĐỦ CẢ BẢNG, không chỉ mấy mã mà phép tính
 *    tình cờ đi qua. Câu chữ của cảnh báo là thứ kế toán ĐỌC để quyết định ký hay trả về —
 *    lệch một câu là lệch cái người ta dựa vào, mà không phép tính nào đỏ.
 */
if (ten === 'warn_def') { process.stdout.write(JSON.stringify(hop.JP_WARN)); process.exit(0); }

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
  bao_cao: 'jpCalcReport_', canh_bao_dau: 'jpCanhBaoHead_', hoan_tong: 'jpHoanTong_',
  /* Đọc báo cáo — xem `class-vhjp-bao-cao.php`. Mấy hàm này cần `sổ` (và vài hàm cần
     `người dùng`), nếu không truyền thì chúng sẽ đọc `jpVals_` thật và chết ở SpreadsheetApp. */
  pub_head: 'jpPubHead_', pub_khu: 'jpPubZone_', pub_dong: 'jpPubRow_', pub_anh: 'jpPubPhoto_',
  chu_ky: 'jpChuKy_', anh_url: 'jpAnhUrl_', mo_ton_dau: 'jpMoTonDau_',
  sua_duoc: 'jpCanEditReport_', ton_ky_truoc: 'jpPrevClosing_',
  ky_chong_nhau: 'jpKyChongNhau_', trung_nguoi_khac: 'jpTrungNguoiKhac_',
  cua_toi: 'jpMyReports', lay_bao_cao: 'jpGetReport',
}[ten];
if (!ham) { console.error('không biết hàm ' + ten); process.exit(2); }
if (typeof hop[ham] !== 'function') { console.error('mã gốc không có ' + ham); process.exit(3); }

process.stdout.write(JSON.stringify(vao.map(a => hop[ham].apply(null, a))));
