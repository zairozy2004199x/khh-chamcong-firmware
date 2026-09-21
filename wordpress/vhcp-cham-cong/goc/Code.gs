/* ⚠️ BẢN ĐÃ BÔI ĐEN — repo này CÔNG KHAI.
 * Nguyên bản Code.gs của app Chấm công, giữ lại để lập bản đồ nghiệp vụ khi port sang
 * WordPress + MySQL. Năm chỗ đã thay bằng chỗ giữ chỗ <<...>>:
 *   ID_THU_MUC_GOC · ID_THU_MUC_ANH_CHAM_CONG · LINK_FIREBASE_RTDB · ID_BANG_TINH
 *   PIN_ADMIN_MAC_DINH
 * ⚠️ ĐỪNG dán lại giá trị thật vào đây. Repo firmware bị CI chặn nếu .bin chứa
 *    'default-rtdb' / 'firebaseio', và luật đang chạy là: repo này KHÔNG chứa bí mật,
 *    kể cả link Firebase. Giá trị thật nằm ở Script Property của project Apps Script.
 */
//  DASHBOARD CHẤM CÔNG (bound trong sheet <<ID_BANG_TINH>>) — BẢN GỘP
//  Giữ nguyên: nhận chấm công ESP32 (doPost), lệnh backfill, dashboard.
//  Thêm mới: đăng nhập PIN + phân quyền theo cửa hàng, quản lý nhân viên +
//            hàng đợi đẩy ảnh mặt vào máy Hikvision, comment "cần admin kiểm tra".
// ============================================================================

// ID Thư mục Gốc trên Drive
const ROOT_FOLDER_ID = "<<ID_THU_MUC_GOC>>";
// ---- Thư mục lưu ẢNH CHẤM CÔNG (tách riêng khỏi ROOT_FOLDER_ID từ 30/07/2026) ----
// ROOT_FOLDER_ID vẫn dùng cho HỒ SƠ NHÂN VIÊN: 'AnhNhanVien_Hikvision' (ảnh mặt đẩy vào máy)
// và 'HoSoNhanVien' (CCCD, hợp đồng) — CỐ Ý không chuyển, để 2 thư mục đó không bị tách đôi.
// Cấu trúc trong thư mục ảnh chấm công: <Tên cơ sở>/Tháng MM-yyyy/<mãNV>_<ngày>_<giờ>.jpg
var ATT_FOLDER_ID    = "<<ID_THU_MUC_ANH_CHAM_CONG>>";   // ẢNH MỚI ghi vào đây
var ATT_FOLDER_ID_CU = "<<ID_THU_MUC_GOC>>";   // ảnh CŨ: chỉ ĐỌC, đừng xoá
// Đổi thư mục lần nữa thì: đưa ID hiện tại xuống mảng dưới, rồi đặt ID mới ở ATT_FOLDER_ID.
function _attFolderIds(){
  var a = [ATT_FOLDER_ID, ATT_FOLDER_ID_CU], out = [], seen = {};
  a.forEach(function(id){ id = String(id || '').trim(); if (id && !seen[id]) { seen[id] = 1; out.push(id); } });
  return out;
}
/* MÚI GIỜ — CHỐT CỨNG giờ Việt Nam, KHÔNG lấy theo Sheet.
 * Trước đây TZ = múi giờ của Sheet. Sheet MỚI TẠO thường để mặc định múi giờ Mỹ; lúc đó
 * "hôm nay" lệch 14 tiếng -> trigger 7h tạo nhầm cột ngày, bù dữ liệu sai khoảng, chấm công
 * rơi nhầm ngày. Đúng lúc chuyển sang Sheet mới là dính. Việt Nam không có giờ mùa hè nên
 * cố định hoàn toàn an toàn.
 * Còn 6 chỗ trong file trước đây gõ thẳng "GMT+7" — nay dùng chung TZ, MỘT định nghĩa duy nhất. */
var TZ = 'Asia/Ho_Chi_Minh';
// Múi giờ Sheet chỉ để CHẨN ĐOÁN (báo nếu lệch), không dùng để tính toán.
function _tzSheet(){ try { return SpreadsheetApp.getActiveSpreadsheet().getSpreadsheetTimeZone() || ''; } catch(e){ return ''; } }

/* ===== Cấu hình phần MỚI =====
 * TOKEN cho ESP32 — CHỈ lấy từ Script Property `EMP_TOKEN`.
 *
 * 🔴 07/08/2026 — ĐÃ GỠ HẰNG SỐ DỰ PHÒNG. Trước đây có `EMP_TOKEN_DUPHONG = 'khhcm-…'` nằm
 *    ngay trong file này để máy không chết lúc chuyển đổi. Nhưng repo đọc được là ai cũng gọi
 *    được `?action=peek / heal / runAuto` — bí mật nằm trong mã nguồn thì không còn là bí mật.
 *    Anh Thắng 07/08: *"anh hiểu rõ về token rồi, loại bỏ nó giúp anh"*, và đã xác nhận
 *    Script Property ĐANG ĐƯỢC DÙNG (màn Xem tình trạng báo "Script Property").
 *
 * ⚠️ GÁC CỨNG: chưa đặt property thì trả '' và `_nhanToken` TỪ CHỐI mọi lệnh. Tuyệt đối không
 *    được để "không có token = cho qua" — đó là cửa mở toang, mà lại im lặng.
 * ⚠️ Chuỗi cũ đã nằm trong LỊCH SỬ GIT nên xoá khỏi file KHÔNG làm nó hết lộ. Muốn sạch thật
 *    thì phải ĐỔI token: bấm "Tạo token mới" (token cũ còn chạy song song 14 ngày để đi đổi
 *    từng máy), rồi "Khoá token cũ ngay" khi mọi cửa hàng đã đổi xong.
 */
function _empToken(){
  var t = '';
  try { t = PropertiesService.getScriptProperties().getProperty('EMP_TOKEN') || ''; } catch (e) {}
  return t;
}

/* ---------------------------------------------------------------------------
 *  ĐỔI TOKEN MÁY CHẤM CÔNG — có GIAI ĐOẠN CHUYỂN TIẾP
 * ---------------------------------------------------------------------------
 *  ⚠️ Token nằm ở HAI đầu: Script Property của web app, và NVS của TỪNG máy ESP32.
 *     Đổi một đầu là đầu kia gãy ngay. Mà máy thì ở nhiều cửa hàng, không đổi
 *     cùng lúc được. Nên trong lúc chuyển: NHẬN CẢ token cũ lẫn token mới, có
 *     hạn. Hết hạn (hoặc anh bấm khoá sớm) thì token cũ chết hẳn.
 *  ⚠️ Máy nào còn dùng token cũ thì ghi dấu lại (`tokcu_<cửa hàng>`) để biết
 *     CHÍNH XÁC còn máy nào chưa đổi — đừng đoán, đừng khoá mù.
 * ------------------------------------------------------------------------- */
var TOK_CU     = 'EMP_TOKEN_CU';
var TOK_CU_HAN = 'EMP_TOKEN_CU_HAN';   // mốc thời gian (ms) token cũ hết hiệu lực
var TOK_DAU    = 'tokcu_';             // tiền tố Script Property đánh dấu cửa hàng còn dùng token cũ

/** Token cũ còn hiệu lực không -> {co, giaTri, han} */
function _tokenCu(){
  var P = PropertiesService.getScriptProperties();
  var v = P.getProperty(TOK_CU) || '', h = Number(P.getProperty(TOK_CU_HAN) || 0);
  if (!v) return { co:false };
  if (!h || (new Date()).getTime() > h) return { co:false, hetHan:true, han:h };
  return { co:true, giaTri:v, han:h };
}

/**
 * Cổng token cho MỌI lệnh ESP32 gọi. Nhận token mới; trong hạn chuyển tiếp thì
 * nhận cả token cũ và ĐÁNH DẤU cửa hàng đó chưa đổi.
 */
function _nhanToken(p){
  var t = String((p && p.token) || '');
  if (!t) return false;
  var moi = _empToken();
  /* 🔴 CHƯA ĐẶT Script Property -> TỪ CHỐI HẾT, không so sánh gì thêm.
     Thiếu dòng này thì `t === moi` với moi='' vẫn false nên tạm ổn, nhưng chốt thẳng ở đây mới
     là điều KHÔNG PHỤ THUỘC vào cách viết bên dưới: sau này ai thêm một nhánh so sánh khác mà
     quên xét token rỗng là mở cửa toang mà không ai thấy. */
  if (!moi) return false;
  if (t === moi){ _boDauTokenCu(p && p.station); return true; }
  var cu = _tokenCu();
  if (cu.co && t === cu.giaTri){ _ghiDauTokenCu(p && p.station); return true; }
  return false;
}
function _ghiDauTokenCu(station){
  station = String(station || '').trim(); if (!station) return;
  try { PropertiesService.getScriptProperties().setProperty(TOK_DAU + station, _now()); } catch(e){}
}
function _boDauTokenCu(station){
  station = String(station || '').trim(); if (!station) return;
  try {
    var P = PropertiesService.getScriptProperties();
    if (P.getProperty(TOK_DAU + station)) P.deleteProperty(TOK_DAU + station);
  } catch(e){}
}
function _dsConTokenCu(){
  var out = [];
  try {
    var all = PropertiesService.getScriptProperties().getProperties();
    Object.keys(all).forEach(function(k){
      if (k.indexOf(TOK_DAU) === 0) out.push({ cuaHang:k.substring(TOK_DAU.length), lanCuoi:all[k] });
    });
  } catch(e){}
  out.sort(function(a,b){ return a.cuaHang < b.cuaHang ? -1 : 1; });
  return out;
}
var SH_ROLE   = 'PhanQuyen';             // PIN | Họ tên | Vai trò | Cửa hàng phụ trách (phẩy)
/* Cột 5-6 thêm 01/08/2026 cho CHẤM CÔNG ONLINE (việc D).
 * Anh Thắng: danh sách này có 2 loại người — *"nhân viên cơ sở (cửa hàng trưởng) để theo dõi
 * chấm công"* và *"nhân viên văn phòng vừa theo dõi vừa chấm công online"*.
 * ⚠️ Phân biệt bằng chính 2 cột này chứ KHÔNG thêm vai trò mới: vai trò đang gác quyền cho cả
 *    app, đụng vào là phải rà lại mọi chỗ kiểm quyền. Có mã NV = được chấm công online. */
var PQ_H = ['PIN', 'Họ tên', 'Vai trò', 'Cửa hàng phụ trách (cách nhau dấu phẩy)',
            'Mã NV chấm công online', 'Cơ sở chấm công online'];
var SH_NV     = 'NhanVien';              // 23 cột: xem NV_HEADERS
var SH_QUEUE  = 'Queue';                 // opId | action | mã | tên | pin | photoFileId | cửa hàng | status | createdAt | result
var SH_FLAG   = 'GhiChuChamCong';        // flagId | cửa hàng | ngày | mã | tên | ghi chú | người gắn | status | tạo | xử lý
/* ===== MÃ CHẠY SONG SONG (06/08/2026) =====================================================
 * Anh Thắng: *"Có một số nhân viên có 2 mã cũ và mới, hiện có 1 số hệ thống chưa kết nối hoặc
 * chưa đẩy xuống được thì anh vẫn muốn chạy song song 2 mã đó."*
 * Máy cũ chưa nhận được lệnh đổi mã nên nó vẫn ghi công theo MÃ CŨ, còn web/máy mới dùng MÃ MỚI.
 * Ép đổi ngay là mất công đang chấm ở máy cũ; nên phải cho hai mã sống song song CÓ KHAI BÁO.
 * 🔴 KHAI BÁO chứ không đoán: hệ thống tuyệt đối không được tự suy "hai mã này chắc là một
 *    người" từ tên — tên người Việt trùng rất nhiều, đoán sai là gộp lương hai người khác nhau. */
var SH_MA_SS  = 'MaSongSong';
var MA_SS_H   = ['Mã A', 'Mã B', 'Họ tên', 'Lý do', 'Người khai', 'Tạo lúc'];
var NV_PHOTO_FOLDER = 'AnhNhanVien_Hikvision';
var NV_DOC_FOLDER = 'HoSoNhanVien';      // ảnh CCCD, hợp đồng...

/* 🔴 09/08/2026 — thêm KE_TOAN. Anh Thắng: *"Admin với kế toán thôi nhé em"* — ai được vào app
 * Lương (tách riêng khỏi Chấm công). `role` trong PhanQuyen vốn là CHUỖI TỰ DO, không hề bị
 * chặn danh sách (`saveRole` chỉ `.toUpperCase()` rồi ghi thẳng) — thêm giá trị này KHÔNG đụng
 * một dòng permission nào đang chạy: mọi chỗ đang so `=== ROLE.ADMIN/QUAN_LY/CHT` vẫn y nguyên,
 * KE_TOAN chỉ khớp đúng chỗ MỚI thêm cho nó, giống hệt cách `SSO_AUTO_ROLES` (dưới) đã dự phòng
 * sẵn dòng ghi chú "KE_TOAN/khác -> vẫn đăng nhập bằng PIN" từ trước.
 * ⚠️ CỐ Ý không cho KE_TOAN quyền gì thêm TRONG Chấm công (không isAdmin, không all, không
 *    _canSalary/_canSuaHoSo/_canQuanTriNV) — việc của họ nằm bên app Lương, ở đây họ chỉ cần
 *    ĐĂNG NHẬP ĐƯỢC để app kia nhận diện qua cùng PIN, không hơn. */
var ROLE = { ADMIN:'ADMIN', QUAN_LY:'QUAN_LY', CHT:'CUA_HANG_TRUONG', NHAN_VIEN:'NHAN_VIEN', KE_TOAN:'KE_TOAN' };
/* NHAN_VIEN thêm 01/08/2026 (việc D). Anh Thắng: danh sách phân quyền có 2 loại người —
 * cửa hàng trưởng để THEO DÕI chấm công, và nhân viên văn phòng để CHẤM CÔNG ONLINE.
 * ⚠️ Vai trò này là THẤP NHẤT: chỉ chấm công online cho chính mình, KHÔNG xem được chấm công của
 *    ai khác. Chặn tại _canStation() — một chỗ duy nhất, vì mọi hàm đọc dữ liệu cửa hàng
 *    (getSheetData, getSheetsList, getMyStations, getPhotoDay…) đều đi qua đó. Chặn ở từng hàm
 *    là kiểu gì cũng sót một hàm. */
// PIN Admin seed khi sheet PhanQuyen còn trống. KHÔNG phải bí mật (nằm trong repo) -> ĐỔI NGAY
// sau lần đăng nhập đầu. Trước đây sheet trống thì MỌI PIN đều thành Admin, giờ chỉ PIN này.
var ADMIN_PIN_MAC_DINH = '<<PIN_ADMIN_MAC_DINH>>';

// Cột NhanVien: 1-7 giữ nguyên (tương thích máy chấm công), 8-23 là hồ sơ mở rộng.
var NV_HEADERS = ['Mã NV','Họ tên','Cửa hàng','PIN máy','photoFileId','Trạng thái đồng bộ','Cập nhật',
  'SĐT','Ngày sinh','Giới tính','CCCD','Địa chỉ','Người liên hệ khẩn','SĐT khẩn cấp',
  'Chức vụ','Ngày vào làm','Trạng thái làm việc','Loại hợp đồng',
  'Lương cơ bản','Số tài khoản','Ngân hàng','Ảnh CCCD (fileId)','Hợp đồng (fileId)','Nhiệm vụ',
  /* 🔴 07/08/2026 — anh Thắng: *"bổ sung thêm 1 cột là cơ sở phụ (để NV nào làm từ 2 cơ sở trở lên
     thì ghi tất cả tên cơ sở làm vào cột đó để phân quyền)"*.
     Ghi NHIỀU cơ sở, cách nhau dấu phẩy: `TUTU_BT, POSH_HCM`.
     ⚠️ Cột MỚI nằm CUỐI. Chèn vào giữa là lệch toàn bộ hồ sơ đang có (vòng đọc/ghi dùng `7 + k`). */
  'Cơ sở phụ',
  /* 🔴 07/08/2026 — anh Thắng: *"xuất PIN ra tab Nhân Viên, để tôi xoá hết các sheet NV_"*.
     PIN đăng nhập web NGUỒN THẬT vẫn là sheet `PhanQuyen` (đó là chỗ `loginByPin` đọc). Cột này
     là BẢN SAO để anh nhìn / sửa hàng loạt trong Sheet rồi bấm "Nạp PIN từ sheet" — đúng vai mà
     hai cột PIN trong `NV_<cơ sở>` từng làm.
     ⚠️ Đây là MẬT KHẨU ĐĂNG NHẬP nằm trong sổ nhân sự. Ai mở được sheet là đăng nhập được tài
        khoản người khác. Anh đã biết và vẫn chọn cách này. */
  'PIN đăng nhập'];
// Khóa (theo thứ tự) cho các cột hồ sơ mở rộng (cột 8 trở đi)
// ⚠️ Cột MỚI phải thêm vào CUỐI cả hai mảng. Chèn vào giữa là lệch toàn bộ hồ sơ đang có:
//    vòng đọc/ghi ở dưới đều dùng `7 + k`, chèn giữa là số điện thoại nhảy sang ô CCCD.
var NV_EXTRA = ['phone','dob','gender','cccd','address','emgName','emgPhone',
  'position','startDate','workStatus','contractType',
  'baseSalary','bankAccount','bankName','cccdFileId','contractFileId','nhiemVu','coSoPhu',
  'pinDangNhap'];

/* ---- NHIỆM VỤ NHÂN VIÊN (02/08/2026) ----------------------------------------
   Anh Thắng: bộ phận Posh / JP có 2 mảng việc — *thu tiền* (tính theo công) và
   *vệ sinh / trực ghế* (tính theo giờ), đơn giá khác nhau.
   ⚠️ CHƯA KHAI = "Thu Tiền", tính công bình thường như xưa nay. Toàn bộ nhân viên
      đang có đều ô trống, nên mặc định này phải đúng bằng hành vi cũ — đổi mặc
      định là đổi lương của tất cả mọi người trong im lặng.
   ⚠️ Chỉ nhận đúng chuỗi trong danh sách. Gõ sai chính tả một chữ là người đó rơi
      ra khỏi mọi phép lọc/tính tiền mà không có gì báo. */
/* ---------------------------------------------------------------------------
 *  NHÓM CƠ SỞ  (03/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"Phân nhóm chỗ này: TUTU_… (Nhóm Tàu), Nhóm máy tự động (Posh, JP)"*
 *  và *"bộ phận Máy Tự Động (Posh, JP) cũng sẽ tính lương theo giờ"*.
 *
 *  Nhóm quyết định hai thứ, nên phải khai MỘT chỗ duy nhất:
 *    · cơ sở nào **tính lương theo giờ** (mặc định bật phân việc / Giờ & Lương)
 *    · cơ sở nào **có khái niệm Nhiệm vụ** (thu tiền / trực ghế) — nơi khác không có
 *  ⚠️ `Index.html` có bản sao `CC_NHOM` cho phần ẩn/hiện — sửa bên này phải sửa bên kia.
 *  ⚠️ Dùng `^` và `(_|$)`: khớp lỏng kiểu `indexOf('JP')` là dính cả `FZ_JPX` sau này. */
var NHOM_CS = [
  { ma:'TAU', ten:'Nhóm Tàu',         mau:/^TUTU(_|$)/i },
  { ma:'MTD', ten:'Nhóm Máy Tự Động', mau:/^(POSH|JP)(_|$)/i }
];
/** Nhóm của một cơ sở -> {ma, ten}; không thuộc nhóm nào -> null */
function _nhomCoSo(station){
  var s = String(station == null ? '' : station).replace(/^CS_/, '').trim();
  if (!s) return null;
  for (var i = 0; i < NHOM_CS.length; i++)
    if (NHOM_CS[i].mau.test(s)) return { ma: NHOM_CS[i].ma, ten: NHOM_CS[i].ten };
  /* 🔴 07/08/2026 — cơ sở được TÍCH "tính theo giờ" thì thuộc luôn Nhóm Máy Tự Động, dù tên
     không bắt đầu bằng POSH/JP.
     Vì sao phải có: anh Thắng định tạo sheet riêng cho *vệ sinh ghế*. Nhóm vốn xét bằng CHÍNH
     CÁI TÊN (`^(POSH|JP)(_|$)`), nên đặt tên `CS_VE_SINH_GHE` là bảng lương từ chối thẳng — anh
     sẽ phải đặt tên `CS_POSH_...` cho lọt, tức là để cách đặt tên quyết định cách tính tiền.
     Đằng nào "tính theo giờ" cũng chỉ có nghĩa bên trong nhóm này, nên tích là vào nhóm. */
  if (_coSoTinhTheoGio(s)) return { ma:'MTD', ten:'Nhóm Máy Tự Động' };
  return null;
}
/** Cơ sở thuộc Nhóm Máy Tự Động? -> nơi DUY NHẤT có khái niệm Nhiệm vụ. */
function _laMayTuDong(station){ var n = _nhomCoSo(station); return !!n && n.ma === 'MTD'; }
/** Mọi cơ sở có sheet CS_, kèm nhóm: {station: tên nhóm}. Không thuộc nhóm -> không có khoá. */
function _nhomTheoCoSo(){
  var m = {};
  SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(s){
    var n = s.getName(); if (n.indexOf('CS_') !== 0) return;
    var st = n.replace(/^CS_/, ''), nh = _nhomCoSo(st);
    if (nh) m[st] = nh.ten;
  });
  return m;
}

/* ===========================================================================
 *  SHEET NHÂN SỰ CỐ ĐỊNH THEO CƠ SỞ:  NV_<cơ sở>          (03/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"sheet chấm công nó bị nhảy theo tháng, nếu tháng trước NV không
 *  chấm công thì sang tháng sau danh sách NV đó bị ẩn… còn khi có sheet cố định,
 *  mình thêm nhiệm vụ vào đó là được lâu dài"*.
 *
 *  🔴 Đúng gốc vấn đề: `CS_<cơ sở>` là BẢNG CHẤM CÔNG, xếp theo khối tháng — ai
 *     tháng này không chấm thì không có hàng, nên lấy DANH SÁCH NHÂN SỰ từ đó là
 *     lấy nhầm nguồn. Danh sách người phải nằm ở chỗ KHÔNG đổi theo tháng.
 *  · `NV_<cơ sở>` = ai làm ở cơ sở này + nhiệm vụ của họ TẠI cơ sở này.
 *  · `NhanVien`   = hồ sơ chi tiết (SĐT, CCCD, lương, hợp đồng) — vẫn giữ.
 *  ⚠️ Nhiệm vụ khai RIÊNG TỪNG CƠ SỞ (anh Thắng chốt): một người ở Posh có thể
 *     Thu Tiền, ở JP lại Trực Ghế. Đừng suy nhiệm vụ từ hồ sơ chung nữa.
 *  ⚠️ ĐỌC CỘT THEO TÊN TIÊU ĐỀ, không đếm vị trí — sheet do anh tự tạo tay nên
 *     thứ tự cột không đoán được, và thêm cột sau này cũng không làm lệch.
 * =========================================================================== */
var NVCS_TIEN_TO = 'NV_';
var NVCS_H       = ['Mã NV', 'Họ tên', 'Nhiệm vụ', 'Ghi chú', 'PIN', 'PIN máy'];
/** Các cách viết tiêu đề đã gặp -> khoá chuẩn. So bằng `_chuanMa` (bỏ hoa/thường, khoảng trắng). */
var NVCS_BIET_TEN = {
  'ma nv':'ma', 'manv':'ma', 'ma':'ma', 'id':'ma', 'ma nhan vien':'ma', 'ma so':'ma',
  'ho ten':'ten', 'ho va ten':'ten', 'ten':'ten', 'ho, ten':'ten',
  'nhiem vu':'nhiemVu', 'nhiemvu':'nhiemVu', 'cong viec':'nhiemVu',
  'ghi chu':'ghiChu',
  /* 🔴 06/08/2026 — HAI cột PIN, TÁCH HẲN. Anh Thắng: *"Mã pin nhân viên… anh muốn nó lưu vào
     cột F luôn, để đồng bộ backup sau này"*, rồi sau khi nghe lý do: *"mỗi nhân viên là 1 mã pin
     riêng, theo ID nhân viên… vậy tách 2 pin"*.
       `pin`     = PIN ĐĂNG NHẬP WEB  -> sheet PhanQuyen. BẮT BUỘC DUY NHẤT TOÀN CHUỖI.
       `pinMay`  = PIN MÁY CHẤM CÔNG  -> sheet NhanVien cột 4. Không cần duy nhất toàn chuỗi.
     Vì sao KHÔNG gộp một số cho cả hai: `_loginResolve` tìm PIN trong PhanQuyen và lấy DÒNG ĐẦU
     TIÊN khớp. Gộp lại thì PIN máy thành mật khẩu đăng nhập, mà PIN máy hai cơ sở trùng nhau là
     chuyện thường -> người này đăng nhập vào tài khoản người kia. SAI DANH TÍNH.
     ⚠️ Đọc theo TÊN TIÊU ĐỀ nên anh để hai cột ở đâu cũng được — cột `PIN` anh đang để ở F,
        app tự tìm ra, không phải sắp lại cột.
     ⚠️ Thứ tự khai ở đây KHÔNG quan trọng, nhưng chuỗi phải khớp sau khi bỏ dấu: "PIN máy" ->
        "pin may". Đừng để "pin may" trỏ nhầm về `pin` — đó là đưa PIN máy lên làm mật khẩu. */
  'pin':'pin', 'ma pin':'pin', 'mapin':'pin', 'ma pin nhan vien':'pin',
  'pin dang nhap':'pin', 'pin web':'pin', 'pin cham cong online':'pin',
  'pin may':'pinMay', 'pinmay':'pinMay', 'ma pin may':'pinMay',
  'pin may cham cong':'pinMay', 'pin tren may':'pinMay'
};
function _khongDau(s){
  return String(s == null ? '' : s).toLowerCase().trim()
    .replace(/[àáạảãâầấậẩẫăằắặẳẵ]/g,'a').replace(/[èéẹẻẽêềếệểễ]/g,'e')
    .replace(/[ìíịỉĩ]/g,'i').replace(/[òóọỏõôồốộổỗơờớợởỡ]/g,'o')
    .replace(/[ùúụủũưừứựửữ]/g,'u').replace(/[ỳýỵỷỹ]/g,'y').replace(/đ/g,'d')
    .replace(/\s+/g,' ');
}
function _nvcsTen(coSo){ return NVCS_TIEN_TO + String(coSo || '').replace(/^CS_/, '').trim(); }
/** Sheet NV_<cơ sở>. `taoNeuThieu` = dựng sheet mới với tiêu đề chuẩn. */
function _nvcsSheet(coSo, taoNeuThieu){
  var ten = _nvcsTen(coSo); if (ten === NVCS_TIEN_TO) return null;
  var sh = _sheet(ten);
  if (sh || !taoNeuThieu) return sh;
  sh = SpreadsheetApp.getActiveSpreadsheet().insertSheet(ten);
  sh.getRange(1, 1, 1, NVCS_H.length).setValues([NVCS_H])
    .setFontWeight('bold').setBackground('#0f172a').setFontColor('#38bdf8');
  sh.setFrozenRows(1);
  return sh;
}
/* Số hàng đầu sheet được phép dò tìm hàng tiêu đề.
   🔴 03/08 — TRƯỚC ĐÂY CHỈ ĐỌC HÀNG 1. Sheet `NV_POSH_HCM` / `NV_JP_HCM` anh Thắng tự tạo có
      HÀNG 1 TRỐNG, tiêu đề "Họ và Tên | ID" nằm ở HÀNG 2, dữ liệu từ hàng 3 (chép khuôn từ sheet
      `CS_` vốn có 2 hàng tiêu đề). Hậu quả: không nhận ra cột mã -> coi như sheet hỏng -> rơi về
      `CS_`, tức là hai sheet anh tạo CHƯA HỀ ĐƯỢC DÙNG. Đúng câu anh hỏi "nhảy vào sheet NV chưa".
      Nay dò vài hàng đầu để tìm hàng tiêu đề thật. */
var NVCS_DO_TOI_HANG = 6;
/** Bản đồ cột sheet NV_: {ma, ten, nhiemVu, ghiChu} 1-based (0 = không có) + `hangTieuDe`. */
function _nvcsCot(sh){
  var trong = { ma:0, ten:0, nhiemVu:0, ghiChu:0, pin:0, pinMay:0, hangTieuDe:0 };
  var lc = sh.getLastColumn(), lr = sh.getLastRow();
  if (lc < 1 || lr < 1) return trong;
  var het = Math.min(NVCS_DO_TOI_HANG, lr);
  var v = sh.getRange(1, 1, het, lc).getDisplayValues();
  for (var r = 0; r < het; r++){
    var m = { ma:0, ten:0, nhiemVu:0, ghiChu:0, pin:0, pinMay:0, hangTieuDe:r + 1 };
    for (var i = 0; i < v[r].length; i++){
      var k = NVCS_BIET_TEN[_khongDau(v[r][i])];
      if (k && !m[k]) m[k] = i + 1;
    }
    // Hàng tiêu đề thật phải có cột MÃ. Hàng trống / hàng ghi chú lướt qua, không nhận bừa.
    if (m.ma) return m;
  }
  /* Sheet anh tự tạo chỉ có Mã + Họ tên. Không đoán bừa cột nào là mã: nếu tiêu đề không
     nhận ra được thì để 0, chỗ gọi sẽ báo rõ thay vì đọc nhầm cột. */
  return trong;
}
/* 🔴 06/08/2026 — THIẾU CỘT "HỌ TÊN" LÀ PHẢI DỪNG, KHÔNG ĐƯỢC ĐỌC TIẾP.
 * Sheet `NV_JP_HCM` của anh Thắng: dữ liệu là `A = Mã, B = Tên`, nhưng tiêu đề lại ghi `ID` ở
 * cột **B** (đúng chỗ cột TÊN) còn A1 không có tiêu đề nào nhận ra được. `NVCS_BIET_TEN` map
 * `'id' -> 'ma'`, nên app lấy **cột B (toàn TÊN NGƯỜI) làm cột mã** và `cot.ten = 0`.
 * Hậu quả anh đã thấy: 6 người ĐANG NẰM trong sheet vẫn bị báo *"KHÔNG có trong NV_JP_HCM"*.
 * 🔴 Vì sao bộ dò đảo cột không cứu được: `_nvcsBiDao(ma, ten)` cần CẢ HAI ô. `cot.ten = 0` ⇒
 *    `ten = ''` ⇒ `_laDangMa('')` sai ⇒ không hàng nào bị coi là đảo. Thiếu cột họ tên vừa làm
 *    sai dữ liệu vừa TẮT LUÔN cái lưới an toàn — nên chỉ còn cách dừng và báo.
 * Thà bảng trống kèm một dòng đỏ nói rõ phải sửa gì, còn hơn một bảng đầy dữ liệu sai im lặng. */
function _nvcsThieuCot(coSo, cot){
  var t = _nvcsTen(coSo);
  if (!cot || !cot.ma)
    return 'Sheet ' + t + ' không có cột "Mã NV" (hoặc "ID") trong ' + NVCS_DO_TOI_HANG
         + ' hàng đầu. Sửa lại tiêu đề rồi thử lại.';
  return 'Sheet ' + t + ' KHÔNG có cột "Họ tên" — app đang lấy cột ' + _cotChu(cot.ma)
       + ' (tiêu đề ở hàng ' + cot.hangTieuDe + ') làm cột MÃ. Nếu cột đó thật ra là HỌ TÊN thì mọi'
       + ' mã đọc lên đều sai, nên app dừng lại chứ không đoán. Sửa hàng tiêu đề cho khớp dữ liệu'
       + ' bên dưới — thường là đặt "Mã NV" trên cột chứa mã và "Họ tên" trên cột chứa tên.';
}
/** 1 -> A, 2 -> B … để câu báo lỗi chỉ đúng cột anh cần nhìn. */
function _cotChu(n){
  var s = ''; n = Number(n) || 0;
  while (n > 0){ var d = (n - 1) % 26; s = String.fromCharCode(65 + d) + s; n = (n - 1 - d) / 26; }
  return s || '?';
}

/* Chính mấy chữ tiêu đề này lọt vào danh sách là đẻ ra "nhân viên" tên HỌ VÀ TÊN, mã ID —
   anh Thắng đã thấy đúng dòng đó trong bảng nhân viên. Chặn cả khi sheet có 2 hàng tiêu đề. */
function _nvcsLaTieuDe(ma, ten){
  var a = _khongDau(ma), b = _khongDau(ten);
  return NVCS_BIET_TEN[a] === 'ma' || NVCS_BIET_TEN[b] === 'ten';
}
/**
 * Bổ sung cột còn thiếu vào CUỐI sheet (không chèn giữa — chèn giữa là lệch dữ liệu đang có).
 * 🔴 06/08/2026 — CHỈ THÊM ĐÚNG CỘT ĐANG CẦN, truyền qua `chiCan`.
 * Anh Thắng: *"tạo cột H ra làm gì"*. Anh chỉ bấm ghi PIN, mà hàm này thêm luôn cả `Nhiệm vụ`
 * và `Ghi chú` vào sheet của anh — app tự ý đẻ cột, đúng thứ mâu thuẫn với luật *"sheet NV_ là
 * cố định, chỉ có từ nó đi ra thôi"* dựng cùng ngày.
 * `chiCan` = mảng khoá ('nhiemVu' | 'ghiChu' | 'pin' | 'pinMay'). Bỏ trống = thêm đủ, CHỈ dùng
 * cho `taoSheetNvCoSo` (đang dựng sheet mới nên phải đủ khuôn chuẩn).
 * ⚠️ Đừng gọi hàm này trên đường CHỈ ĐỌC. Đọc mà ghi vào sheet là kiểu tác dụng phụ giấu mặt.
 */
function _nvcsBoSungCot(sh, chiCan){
  var m = _nvcsCot(sh), them = [];
  var can = function(k){ return !chiCan || chiCan.indexOf(k) >= 0; };
  if (!m.nhiemVu && can('nhiemVu')) them.push('Nhiệm vụ');
  if (!m.ghiChu  && can('ghiChu'))  them.push('Ghi chú');
  if (!m.pin     && can('pin'))     them.push('PIN');
  if (!m.pinMay  && can('pinMay'))  them.push('PIN máy');
  if (!them.length) return m;
  var lc = Math.max(1, sh.getLastColumn());
  if (sh.getMaxColumns() < lc + them.length) sh.insertColumnsAfter(sh.getMaxColumns(), lc + them.length - sh.getMaxColumns());
  // Ghi vào ĐÚNG hàng tiêu đề đang dùng, không mặc định hàng 1 (sheet của anh tiêu đề ở hàng 2).
  sh.getRange(m.hangTieuDe || 1, lc + 1, 1, them.length).setValues([them])
    .setFontWeight('bold').setBackground('#0f172a').setFontColor('#38bdf8');
  return _nvcsCot(sh);
}

var NHIEM_VU_DS      = ['Thu Tiền - Vệ Sinh', 'Trực Ghế Posh - JP'];
var NHIEM_VU_MAC_DINH = 'Thu Tiền';        // ô trống hiểu là cái này
/* MỘT người có thể kiêm NHIỀU nhiệm vụ — anh Thắng: *"có ngày thì thu tiền, có ngày thì vệ sinh
   ghế"*. Ô "Nhiệm vụ" trong hồ sơ vì thế chứa DANH SÁCH, ngăn nhau bằng dấu phẩy.
      ''                                          -> mặc định Thu Tiền, tính công như cũ
      'Trực Ghế Posh - JP'                        -> cố định, chấm công không hỏi
      'Thu Tiền - Vệ Sinh, Trực Ghế Posh - JP'    -> chấm công HỎI chọn nhiệm vụ hôm nay
   ⚠️ Tên nhiệm vụ có sẵn dấu '-' nên KHÔNG được dùng '-' làm dấu ngăn. Dùng dấu phẩy. */
function _chuanNhiemVu(v){
  v = String(v == null ? '' : v).trim();
  if (!v) return '';
  for (var i = 0; i < NHIEM_VU_DS.length; i++)
    if (_chuanMa(NHIEM_VU_DS[i]) === _chuanMa(v)) return NHIEM_VU_DS[i];   // về đúng chính tả gốc
  return null;                                                            // không hợp lệ
}
/** Chuỗi/mảng -> mảng nhiệm vụ hợp lệ, KHÔNG trùng, đúng thứ tự NHIEM_VU_DS. null = có mục sai. */
function _chuanNhiemVuList(v){
  var tho = Array.isArray(v) ? v : String(v == null ? '' : v).split(',');
  var co = {}, xau = false;
  tho.forEach(function(x){
    var s = String(x || '').trim(); if (!s) return;
    var c = _chuanNhiemVu(s);
    if (c === null) { xau = true; return; }
    if (c) co[c] = 1;
  });
  if (xau) return null;
  // Giữ theo thứ tự khai báo gốc để chuỗi lưu xuống Sheet luôn ổn định (khai kiểu gì cũng ra một chuỗi)
  return NHIEM_VU_DS.filter(function(x){ return co[x]; });
}
function _nhiemVuChuoi(list){ return (list || []).join(', '); }

/* ===== 🔴 `NV_<cơ sở>` LÀ MỘT CHIỀU — APP KHÔNG BAO GIỜ THÊM DÒNG (06/08/2026) =============
 *
 * Anh Thắng: *"sao giờ lại tạo ngược từ sheet CS sang sheet NV. Bỏ tính năng đó đi, vì sheet NV_
 * là cố định, chỉ có từ nó đi ra thôi."*
 *
 * ĐÃ BỎ HẲN, đừng viết lại:
 *   · `_nvcsThem(coSo, ds)`        — thêm người vào `NV_` (dùng chung cho 3 đường bên dưới)
 *   · `themNvVaoSheetCoSo(...)`    — nút "➕ Bổ sung N người vào sheet nhân sự" trên 3 dòng đối chiếu
 *   · phần mồi người từ `CS_` trong `taoSheetNvCoSo` — nay chỉ tạo sheet RỖNG đúng tiêu đề
 *   · `upsertEmployee` tự thêm vào `NV_<cơ sở>` — nay chỉ BÁO, không ghi
 *
 * `NV_` là **nguồn gốc duy nhất** xác định ai thuộc cơ sở nào; `CS_` là dữ liệu MÁY chấm công,
 * đầy mã máy tự sinh ("5", "Nhat", "19"). Cho phép chảy ngược `CS_` → `NV_` là để máy quyết định
 * danh sách nhân sự — sai chiều phụ thuộc, và mọi thứ tính lương đều bám vào `NV_`.
 *
 * ⚠️ VẪN ĐƯỢC PHÉP ghi vào `NV_`: cột PIN / PIN máy trên dòng ĐÃ CÓ (`_nvcsGhiPin`) — anh Thắng
 *    yêu cầu riêng việc đó (*"chuyển hết pin về sheet cho anh"*). Ranh giới là: **sửa ô của người
 *    đã có thì được, ĐẺ THÊM DÒNG thì không.**
 *
 * Ba dòng đối chiếu (CS_ có mà NV_ không có · máy có mà NV_ không có · hồ sơ ghi cửa hàng này mà
 * NV_ không có) **vẫn giữ** — chúng chỉ ĐỌC và báo, để anh tự vào Sheet sửa.
 * ------------------------------------------------------------------------------------------- */

/** Danh sách nhân sự của MỘT cơ sở, đọc từ `NV_<cơ sở>`. [{ma, ten, nhiemVu:[…], hang}] */
/* ===== HÀNG BỊ ĐẢO CỘT MÃ ↔ HỌ TÊN (06/08/2026) ==========================================
 * Anh Thắng gửi ảnh sheet: hàng 13–36 là `Tên | Mã`, từ hàng 37 trở đi là `Mã | Tên` — dán vào
 * ngược cột. App đọc theo tiêu đề nên những hàng đó vào ngược, đẻ ra hồ sơ có "Mã NV" là TÊN
 * NGƯỜI (đúng cái anh thấy trên bảng: cột MÃ ghi "Trần Kiều Di").
 *
 * 🔴 Vì sao SỬA LÚC ĐỌC chứ không chỉ cảnh báo: không sửa thì mọi thứ phía sau (`dsMaNv`, đề xuất
 *    đổi mã, tạo hồ sơ hàng loạt, nạp PIN) đều nhận TÊN làm mã và đẻ thêm rác. Sửa lúc đọc thì
 *    một chỗ đúng là cả chuỗi đúng.
 * 🔴 `daoCot` vẫn được dựng để chẩn đoán, nhưng KHÔNG còn đẩy lên màn hình và KHÔNG còn nút
 *    sửa sheet nào (bỏ 06/08/2026 — anh Thắng sửa tay). Xem chú thích ở chỗ bỏ `suaDaoCotNvSheet`.
 * (cũ) trả `daoCot` để màn hình nói ra và có nút sửa hẳn trong
 *    sheet. Sửa ngầm mãi là sheet cứ bẩn dần mà không ai biết.
 * 🔴 CHỈ đảo khi CHẮC CHẮN cả hai ô đều ngược kiểu. Nghi ngờ thì để nguyên — đảo nhầm là gán mã
 *    của người này cho người kia, tệ hơn hẳn việc để nguyên cho anh nhìn thấy. */
function _laDangMa(v){
  var t = String(v == null ? '' : v).trim();
  if (!t || /\s/.test(t)) return false;                    // mã không có khoảng trắng
  if (!/[0-9]/.test(t)) return false;                      // mã luôn có chữ số
  return _khongDau(t) === t.toLowerCase();                 // và không có dấu tiếng Việt
}
function _laDangTen(v){
  var t = String(v == null ? '' : v).trim();
  if (!t || /[0-9]/.test(t)) return false;                 // tên người không có chữ số
  return /\s/.test(t) || _khongDau(t) !== t.toLowerCase(); // có khoảng trắng HOẶC có dấu
}
/** Ô "mã" đang chứa tên VÀ ô "tên" đang chứa mã -> đúng là bị đảo. */
function _nvcsBiDao(ma, ten){ return _laDangTen(ma) && _laDangMa(ten); }

function _nvcsDoc(coSo){
  var sh = _nvcsSheet(coSo, false);
  if (!sh) return { co:false, list:[], cot:null };
  var cot = _nvcsCot(sh);
  if (!cot.ma || !cot.ten) return { co:true, list:[], cot:cot, loi:_nvcsThieuCot(coSo, cot) };
  // Dữ liệu bắt đầu NGAY SAU hàng tiêu đề tìm được — không mặc định hàng 2.
  var d1 = cot.hangTieuDe + 1;
  var lr = sh.getLastRow(); if (lr < d1) return { co:true, list:[], cot:cot };
  var lc = sh.getLastColumn();
  var v = sh.getRange(d1, 1, lr - d1 + 1, lc).getDisplayValues();
  var out = [], da = {}, dao = [], trung = [];
  for (var i = 0; i < v.length; i++){
    var ma = String(v[i][cot.ma - 1] || '').trim(); if (!ma) continue;
    var ten = cot.ten ? String(v[i][cot.ten - 1] || '').trim() : '';
    // Sheet có 2 hàng tiêu đề thì hàng thứ hai lọt vào đây thành "nhân viên" tên HỌ VÀ TÊN, mã ID.
    if (_nvcsLaTieuDe(ma, ten)) continue;
    // Hàng dán ngược cột -> đọc cho đúng, và ghi lại để màn hình còn báo.
    if (_nvcsBiDao(ma, ten)) { var _t = ma; ma = ten; ten = _t; dao.push({ hang:i + d1, ma:ma, ten:ten }); }
    /* 🔴 KHAI TRÙNG TRONG SHEET LÀ CHUYỆN BÌNH THƯỜNG — LẤY DÒNG ĐẦU, MỘT NGƯỜI MỘT LẦN.
       Anh Thắng: *"trong 1 sheet nhân viên, có nhân viên bị lặp lại (việc lặp lại bên sheet
       nhân viên kệ), khi đồng bộ qua sheet CS thì chỉ lấy 1 giá trị"*.
       Đây chính là chỗ bảo đảm lời đó: `_nvcsDoc` là NGUỒN DUY NHẤT mọi thứ đọc danh sách người
       (tạo hồ sơ hàng loạt · ghi dòng sang `CS_` · nạp PIN · đề xuất đổi mã). Lọc trùng ở đây
       thì mọi đường phía sau đều chỉ thấy MỘT.
       ⚠️ Đừng bỏ bộ lọc này rồi đi lọc lại ở từng nơi gọi — sót một nơi là người đó có hai dòng
          trong `CS_`, tức là công chấm bị tách đôi.
       `trungMa` vẫn trả về để CHẨN ĐOÁN khi cần, nhưng màn hình KHÔNG bày ra: cảnh báo cho một
       chuyện anh đã bảo kệ là làm người ta quen bỏ qua cảnh báo. */
    var k = _chuanMa(ma);
    if (da[k]) { trung.push({ hang:i + d1, ma:ma, ten:ten, hangDauTien:da[k] }); continue; }
    da[k] = i + d1;
    out.push({ ma: ma,
               ten: ten,
               nhiemVu: cot.nhiemVu ? (_chuanNhiemVuList(String(v[i][cot.nhiemVu - 1] || '')) || []) : [],
               nhiemVuTho: cot.nhiemVu ? String(v[i][cot.nhiemVu - 1] || '').trim() : '',
               /* Hai PIN đọc THÔ, không lọc ký tự ở đây: chỗ nạp cần biết anh gõ gì để báo đúng
                  lý do ("có chữ", "thiếu số"). Lọc sạch từ đây thì "12ab34" thành "1234" — nạp một
                  PIN anh không hề định đặt, mà chẳng có gì cảnh báo. */
               pin:    cot.pin    ? String(v[i][cot.pin    - 1] || '').trim() : '',
               pinMay: cot.pinMay ? String(v[i][cot.pinMay - 1] || '').trim() : '',
               hang: i + d1 });
  }
  return { co:true, list:out, cot:cot, daoCot:dao, trungMa:trung };
}

/**
 * Sửa HẲN trong sheet những hàng bị dán ngược cột Mã ↔ Họ tên.
 * 🔴 Chỉ đụng đúng những hàng `_nvcsBiDao` nhận ra — hàng nào không chắc thì để nguyên.
 * 🔴 Đọc lại sheet ngay trước khi ghi, không tin danh sách client giữ: sheet có thể đã đổi giữa
 *    hai lần bấm, ghi theo danh sách cũ là hoán nhầm hàng khác.
 */
/**
 * Tìm HỒ SƠ trong `NhanVien` bị đảo: cột "Mã NV" chứa TÊN NGƯỜI, cột "Họ tên" chứa MÃ.
 *
 * Anh Thắng gửi ảnh bảng nhân viên: cột MÃ ghi "Trần Kiều Di", cột HỌ TÊN ghi "MNNV2MTD0026",
 * và nói *"dữ liệu nv đang ngược dliệu CS"*. `CS_` xếp `Họ và Tên | ID`, sheet `NV_` của anh có
 * một đoạn dán ngược lại — những hàng đó đã tạo thành hồ sơ có mã là tên người.
 *
 * 🔴 CHỈ TÌM VÀ BÁO, KHÔNG TỰ SỬA. Sửa mã là ghi lại lịch sử chấm công ở 4 chỗ, không lùi được —
 *    phải anh bấm từng người. Nút Sửa gọi `doiMaNhanVien` (đường đã có, đã kiểm), KHÔNG viết
 *    đường ghi thứ hai: hai đường ghi cùng một thứ là kiểu gì cũng lệch nhau một chỗ.
 */
function timHoSoDaoCot(pin, coSo){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền xem.' };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  /* 🔴 Mức 1 chỉ soi được ĐÚNG cửa hàng mình. Thiếu dòng này thì cửa hàng trưởng bỏ trống `coSo`
     là thấy hồ sơ TOÀN CHUỖI — chính cái lỗ mà "phân quyền theo mức" sinh ra: hàm này trước kia
     chỉ Admin/Quản lý gọi được nên không cần lọc, mở quyền mà quên lọc là rò ngay. */
  if (!_canQuanTriNV(u) && !(coSo && _canStation(u, coSo)))
    return { ok:false, error:'Chỉ xem được cửa hàng mình phụ trách.' };
  var sh = _nvSheet(), out = [], maCu = {};
  if (!sh || sh.getLastRow() < 2) return { ok:true, list:[] };
  var v = sh.getRange(2, 1, sh.getLastRow() - 1, 3).getValues();
  // Mọi mã đang có hồ sơ (TOÀN BỘ, không riêng cơ sở này) — để biết mã đúng đã có ai giữ chưa.
  var daCoMa = {};
  for (var q = 0; q < v.length; q++){ var _m = String(v[q][0] || '').trim(); if (_m) daCoMa[_chuanMa(_m)] = 1; }
  for (var i = 0; i < v.length; i++){
    var ma = String(v[i][0] || '').trim(), ten = String(v[i][1] || '').trim();
    if (!ma || !ten) continue;
    var cs = String(v[i][2] || '').replace(/^CS_/, '').trim();
    if (coSo && _chuanMa(cs) !== _chuanMa(coSo)) continue;   // chỉ cơ sở đang xem
    if (!_nvcsBiDao(ma, ten)) continue;
    out.push({ maHienTai:ma, tenHienTai:ten, coSo:cs, maDung:ten, tenDung:ma });
    maCu[_chuanMa(ma)] = out.length - 1;
  }
  /* 🔴 PHÂN LOẠI: mã đúng ĐÃ CÓ hồ sơ chưa?
     Anh Thắng: *"anh xóa rồi, nhưng trong wed vẫn có"* — anh xoá dòng trong sheet, nhưng hồ sơ
     rác vẫn nằm ở `NhanVien`. Và người thật hầu hết ĐÃ CÓ hồ sơ đúng ở trên.
       · đã có hồ sơ đúng  -> hồ sơ này là RÁC, phải XOÁ. Đổi mã vào là đụng mã người ta đang giữ.
       · chưa có           -> đây mới là hồ sơ thật bị đảo, ĐỔI MÃ cho đúng.
     Chọn nhầm việc ở đây là hỏng hồ sơ thật, nên phải nói rõ ra chứ đừng để anh tự đoán. */
  out.forEach(function(x){ x.daCoHoSoDung = !!daCoMa[_chuanMa(x.maDung)]; });
  return { ok:true, coSo:coSo, list:out };
}

/* 🔴 ĐÃ BỎ `suaDaoCotNvSheet(pin, coSo)` — 06/08/2026.
 * Anh Thắng: *"loại bỏ cái này, anh đổi thủ công nên không cần nữa"*.
 * ĐỪNG DỰNG LẠI. Hai lý do, lý do thứ hai mới là lý do nặng:
 *   1. `NV_` là một chiều — app không sửa nội dung sheet nhân sự của anh.
 *   2. Sheet `NV_` có thể do MỘT CÔNG THỨC MẢNG sinh ra. `NV_JP_HCM` có
 *      `=UNIQUE(QUERY(IMPORTRANGE(…)))` ở ô A2, trải ra cả vùng `A:C`. Hàm này `setValue` vào
 *      đúng hai cột đó ⇒ **phá công thức**, và cả danh sách nhân sự của cơ sở đó biến mất.
 *      Không có cách nào lùi bằng một nút.
 * Phần ĐỌC vẫn giữ: `_nvcsBiDao` trong `_nvcsDoc` đọc đúng hàng dán ngược, nên trong lúc anh
 * chưa sửa tay xong thì dữ liệu app đọc lên vẫn đúng.
 */

/** Nhiệm vụ của một người TẠI một cơ sở. Không có sheet NV_ -> [] (không đoán từ hồ sơ chung). */
function _nhiemVuTaiCoSo(coSo, maNV){
  var d = _nvcsDoc(coSo); if (!d.co) return [];
  var k = _chuanMa(maNV);
  for (var i = 0; i < d.list.length; i++) if (_chuanMa(d.list[i].ma) === k) return d.list[i].nhiemVu;
  return [];
}
/** {mã: [nhiệm vụ]} của cả cơ sở — đọc MỘT lần, dùng cho vòng lặp tính lương. */
function _bangNhiemVuCoSo(coSo){
  var m = {}, d = _nvcsDoc(coSo);
  d.list.forEach(function(x){ m[_chuanMa(x.ma)] = x.nhiemVu; });
  return m;
}

/** Danh sách nhân sự của cơ sở cho màn hình (kèm cờ đã có tài khoản chấm công online chưa). */
function getNvCoSo(pin, coSo){
  var u = _requireAuth(pin);
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!coSo) return { ok:false, error:'Thiếu cơ sở.', list:[] };
  if (!u.isAdmin && !u.all && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cơ sở này.', list:[] };
  var d = _nvcsDoc(coSo);
  if (!d.co) return { ok:true, coSo:coSo, coSheet:false, tenSheet:_nvcsTen(coSo), list:[],
                      laMayTuDong:_laMayTuDong(coSo) };
  if (d.loi) return { ok:false, error:d.loi, coSo:coSo, coSheet:true, tenSheet:_nvcsTen(coSo), list:[] };
  return { ok:true, coSo:coSo, coSheet:true, tenSheet:_nvcsTen(coSo),
           coCotNhiemVu: !!d.cot.nhiemVu, laMayTuDong:_laMayTuDong(coSo),
           suaDuoc: !!(u.isAdmin || u.role === ROLE.QUAN_LY || u.role === ROLE.CHT),
           list: d.list.map(function(x){ return { ma:x.ma, ten:x.ten, nhiemVu:x.nhiemVu, hang:x.hang }; }) };
}

/** Đặt nhiệm vụ cho MỘT người TẠI MỘT cơ sở — ghi thẳng vào sheet `NV_<cơ sở>`. */
function datNhiemVuCoSo(pin, coSo, maNV, nhiemVu){
  var u = _requireAuth(pin);
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  maNV = String(maNV || '').trim();
  if (!coSo || !maNV) return { ok:false, error:'Thiếu cơ sở hoặc mã NV.' };
  if (!u.isAdmin && u.role !== ROLE.QUAN_LY && !_canStation(u, coSo))
    return { ok:false, error:'Không có quyền cơ sở này.' };
  /* Nhiệm vụ là khái niệm của NHÓM MÁY TỰ ĐỘNG. Cơ sở khác khai vào là vô nghĩa mà lại làm
     tách hàng trong sheet chấm công của cơ sở đó -> chặn, và nói rõ lý do. */
  var list = _chuanNhiemVuList(nhiemVu);
  if (list === null) return { ok:false, error:'Nhiệm vụ không hợp lệ. Chọn: ' + NHIEM_VU_DS.join(' / ') + ' hoặc để trống.' };
  if (list.length && !_laMayTuDong(coSo))
    return { ok:false, error:'Nhiệm vụ chỉ áp dụng cho Nhóm Máy Tự Động (Posh, JP). Cơ sở "' + coSo + '" không thuộc nhóm này.' };

  var lock = LockService.getScriptLock(); try { lock.waitLock(15000); } catch(e){}
  try {
    var sh = _nvcsSheet(coSo, true);
    var cot = _nvcsBoSungCot(sh, ['nhiemVu']);   // đang GHI nhiệm vụ -> chỉ thêm đúng cột đó
    if (!cot.ma) return { ok:false, error:'Sheet ' + _nvcsTen(coSo) + ' không có cột "Mã NV". Sửa tiêu đề hàng 1.' };
    var d = _nvcsDoc(coSo), hang = 0, k = _chuanMa(maNV);
    for (var i = 0; i < d.list.length; i++) if (_chuanMa(d.list[i].ma) === k) { hang = d.list[i].hang; break; }
    if (!hang) return { ok:false, error:'Không thấy mã "' + maNV + '" trong sheet ' + _nvcsTen(coSo)
                               + '. Thêm người đó vào sheet trước đã.' };
    sh.getRange(hang, cot.nhiemVu).setValue(_nhiemVuChuoi(list));
    return { ok:true, coSo:coSo, maNV:maNV, nhiemVu:_nhiemVuChuoi(list) };
  } catch(err){ return { ok:false, error:String(err) }; }
  finally { lock.releaseLock(); }
}

/**
 * Tạo sheet `NV_<cơ sở>` RỖNG, đúng tiêu đề chuẩn. KHÔNG mồi người từ `CS_`.
 * 🔴 06/08/2026 — trước đây hàm này đổ sẵn người đang có trong bảng chấm công. ĐÃ BỎ:
 *    anh Thắng *"sheet NV_ là cố định, chỉ có từ nó đi ra thôi"*. `CS_` là dữ liệu MÁY, để nó
 *    quyết định danh sách nhân sự là sai chiều phụ thuộc.
 *    Giữ lại việc TẠO sheet vì đó chính là chỗ hay sai nhất: dựng bằng `NVCS_H` thì tiêu đề
 *    luôn đúng thứ tự `Mã NV | Họ tên | …`, không lặp lại cảnh cột `ID` nằm trên cột TÊN.
 */
function taoSheetNvCoSo(pin, coSo){
  var u = _requireAuth(pin);
  if (!u.isAdmin && u.role !== ROLE.QUAN_LY) return { ok:false, error:'Chỉ Admin / Quản lý được tạo sheet nhân sự.' };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!coSo) return { ok:false, error:'Thiếu cơ sở.' };
  var lock = LockService.getScriptLock(); try { lock.waitLock(20000); } catch(e){}
  try {
    var daCoRoi = !!_sheet(_nvcsTen(coSo));
    var sh = _nvcsSheet(coSo, true);
    if (!sh) return { ok:false, error:'Không tạo được sheet ' + _nvcsTen(coSo) };
    var cot = _nvcsBoSungCot(sh);
    if (!cot.ma || !cot.ten) return { ok:false, error:_nvcsThieuCot(coSo, cot) };
    return { ok:true, coSo:coSo, tenSheet:_nvcsTen(coSo), daCoRoi:daCoRoi };
  } catch(err){ return { ok:false, error:String(err) }; }
  finally { lock.releaseLock(); }
}

/* ---------------------------------------------------------------------------
 *  KIÊM ≥2 NHIỆM VỤ  =  ≥2 HÀNG trong sheet CS_        (03/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"1 nhân viên từ 2 nhiệm vụ trở lên thì tách hàng… hàng đầu là chấm
 *  công mặc định, hàng 2 là việc thêm"*, và hàng 2 **chèn ngay dưới** hàng người đó:
 *
 *      Trần Thị Thúy Vy               TUTP01      <- hàng 1, MÁY chấm công ghi vào đây
 *      Trần Thị Thúy Vy — Trực Ghế    TUTP01-TG   <- hàng 2, CHỈ chấm công online
 *
 *  ⚠️ Vì sao đánh dấu bằng ĐUÔI MÃ chứ không phải cột mới: máy Hikvision đẩy lên
 *     MÃ TRẦN (`TUTP01`) và `findOrCreateEmpRow` so BẰNG NHAU tuyệt đối, nên máy
 *     không bao giờ chạm được hàng 2. Đây là lý do kỹ thuật, không phải cho đẹp —
 *     và cũng là lý do KHÔNG được đổi sang so kiểu "bắt đầu bằng".
 *  ⚠️ Hàng 1 = nhiệm vụ ĐẦU TIÊN theo thứ tự `NHIEM_VU_DS`. Đổi thứ tự mảng đó là
 *     đổi hàng nào là hàng 1 của TẤT CẢ mọi người, trong im lặng. Đừng đổi.
 *  ⚠️ Kiêm 0 hoặc 1 nhiệm vụ -> KHÔNG tách hàng, y hệt xưa nay.
 */
var NHIEM_VU_DUOI = { 'Thu Tiền - Vệ Sinh': 'TT', 'Trực Ghế Posh - JP': 'TG' };
var NHIEM_VU_NGAN = { 'Thu Tiền - Vệ Sinh': 'Thu Tiền', 'Trực Ghế Posh - JP': 'Trực Ghế' };
var NV_NGAN_CACH  = ' — ';    // gạch DÀI ngăn tên hàng phụ, không đụng dấu '-' của mã

/* Hậu tố của bộ phận VĂN PHÒNG — hàng tách theo CA, không phải "nhiệm vụ" của Nhóm Máy Tự Động.
   ⚠️ Khai ở ĐÂY, cạnh `NHIEM_VU_DUOI`, để `_tachMaNhiemVu` là MỘT ĐỊNH NGHĨA DUY NHẤT cho mọi
      hậu tố. Trước đó em định thêm hàm `_laHangPhu()` riêng, nhưng có tới 7 chỗ đang hỏi
      "mã này có phải hàng phụ không" — thêm hàm thứ hai là chắc chắn có chỗ quên gọi, và chỗ quên
      đó sẽ hiện hàng `NV01-CD` ra như MỘT CON NGƯỜI trong danh sách nhân viên. */
var VP_DUOI_NHAN = { 'CT': 'Công tối', 'CD': 'Tăng ca / Đêm',
  /* 🔴 06/08/2026 — TĂNG CƯỜNG: nhân viên CƠ SỞ KHÁC sang làm thêm.
     Anh Thắng: *"Dữ liệu nhân viên là để xác định nhân viên đó có làm việc tại cơ sở đó ban đầu.
     Sau đó nhân viên đó có làm thêm tại cơ sở khác… lúc này bên sheet NV cơ sở khác thì không
     đụng chạm gì, còn bên sheet CS thì sẽ hiện tên nhân viên đó kèm ID có hậu tố, để xác định
     cơ sở gốc. Vì bên CS phải có để chấm công và tính lương."*
     Khai bằng cách gõ thẳng một hàng vào `CS_<cơ sở cần tăng cường>`: `<Tên> | <Mã>-TC`.
     `NV_<cơ sở đó>` KHÔNG đụng tới — `NV_` vẫn chỉ nói ai thuộc cơ sở đó TỪ ĐẦU.

     ⚠️ Anh Thắng ban đầu nói hậu tố "-TG". KHÔNG DÙNG ĐƯỢC: `NHIEM_VU_DUOI` đã gán '-TG' cho
        'Trực Ghế Posh - JP'. Một hậu tố hai nghĩa thì `_tachMaNhiemVu` đọc ra nhiệm vụ sai, và
        ĐƠN GIÁ GIỜ TRỰC GHẾ bị áp cho người chỉ sang làm thêm — sai tiền lương.
        Đây là LẦN THỨ HAI hậu tố '-TG' bị đề nghị cho một nghĩa khác (lần trước là ca đêm, đã
        chốt '-CD'). Anh chốt lần này: **'-TC'**.
     ⚠️ Khai ở ĐÂY chứ đừng viết hàm nhận dạng riêng: `_tachMaNhiemVu` là MỘT ĐỊNH NGHĨA DUY NHẤT,
        có 7 chỗ đang hỏi "mã này có phải hàng phụ không". Thêm hàm thứ hai là chắc chắn có chỗ
        quên gọi, và chỗ đó sẽ hiện `MNNV0007-TC` ra như MỘT CON NGƯỜI trong danh sách nhân viên. */
  'TC': 'Tăng cường (cơ sở khác)' };
var DUOI_TANG_CUONG = 'TC';
/* ⚠️ KHÔNG dùng '-TG' cho ca đêm dù anh Thắng viết "ID-TG": `NHIEM_VU_DUOI` đã gán '-TG' cho
   'Trực Ghế Posh - JP'. Một hậu tố hai nghĩa thì `_tachMaNhiemVu` đọc ra nhiệm vụ sai. Anh đã
   chốt dùng '-CD'. Hai hằng dưới nằm ĐÚNG cạnh bảng nhãn trên để không bao giờ lệch nhau. */
var VP_DUOI_TOI = 'CT';   // CŨ, không còn ghi mới — giữ để hàng -CT lỡ tạo vẫn được
                          // nhận là hàng phụ, không hiện ra như một con người.
var VP_DUOI_DEM = 'CD';   // Công Đêm  (ca online 22:00–06:00)

/** Mã cột B là HÀNG PHỤ? -> {ma, duoi, nhiemVu, nhan}; không phải -> null.
    `nhiemVu` chỉ có giá trị với hàng nhiệm vụ (Nhóm Máy Tự Động); hàng ca Văn phòng để rỗng và
    dùng `nhan` — gán bừa một nhiệm vụ vào đó là làm lệch bảng lương Máy Tự Động. */
function _tachMaNhiemVu(ma){
  var s = String(ma == null ? '' : ma).trim();
  for (var i = 0; i < NHIEM_VU_DS.length; i++){
    var d = NHIEM_VU_DUOI[NHIEM_VU_DS[i]]; if (!d) continue;
    var hau = '-' + d;
    if (s.length > hau.length && s.slice(-hau.length).toUpperCase() === hau)
      return { ma: s.slice(0, s.length - hau.length), duoi: d, nhiemVu: NHIEM_VU_DS[i],
               nhan: NHIEM_VU_NGAN[NHIEM_VU_DS[i]] || NHIEM_VU_DS[i] };
  }
  var ds = Object.keys(VP_DUOI_NHAN);
  for (var j = 0; j < ds.length; j++){
    var h2 = '-' + ds[j];
    if (s.length > h2.length && s.slice(-h2.length).toUpperCase() === h2)
      return { ma: s.slice(0, s.length - h2.length), duoi: ds[j], nhiemVu: '',
               nhan: VP_DUOI_NHAN[ds[j]] };
  }
  return null;
}
/** Mã của HÀNG ứng với (người, nhiệm vụ). Nhiệm vụ của hàng 1 -> trả mã trần. */
function _maChoNhiemVu(maNV, nhiemVu, dsNv){
  var ma = String(maNV == null ? '' : maNV).trim();
  var nv = _chuanNhiemVu(nhiemVu);
  if (!nv) return ma;                                   // '' hoặc sai chính tả -> hàng chính
  dsNv = dsNv || [];
  if (dsNv.length < 2 || nv === dsNv[0]) return ma;     // kiêm 1 việc, hoặc đúng việc của hàng 1
  var d = NHIEM_VU_DUOI[nv];
  return d ? (ma + '-' + d) : ma;
}
/** Tên hiển thị của hàng đó: hàng phụ thì kèm tên việc cho dễ nhìn trong Sheet. */
function _tenChoNhiemVu(hoTen, maNV, nhiemVu, dsNv){
  var ten = String(hoTen || maNV || '').trim();
  if (_maChoNhiemVu(maNV, nhiemVu, dsNv) === String(maNV == null ? '' : maNV).trim()) return ten;
  var nv = _chuanNhiemVu(nhiemVu);
  return ten + NV_NGAN_CACH + (NHIEM_VU_NGAN[nv] || nv);
}
/** Nhiệm vụ ứng với một hàng, suy từ mã + danh sách nhiệm vụ khai trong hồ sơ. */
function _nhiemVuCuaHang(ma, dsNv){
  var t = _tachMaNhiemVu(ma);
  if (t) return t.nhiemVu;
  dsNv = dsNv || [];
  return dsNv.length ? dsNv[0] : '';                    // hàng chính = việc đầu danh sách
}
/** Bảng {mã NV: nhiệm vụ} để màn hình Chấm công hiện cột Nhiệm vụ. Nhẹ, chỉ 2 trường. */
/* 🔴 03/08 — nhiệm vụ nay khai RIÊNG TỪNG CƠ SỞ ở sheet `NV_<cơ sở>`, không còn ở hồ sơ chung.
   Nên hàm này BẮT BUỘC biết đang xem cơ sở nào. Gọi thiếu `coSo` -> trả rỗng (client tự hiểu là
   mặc định) chứ KHÔNG rơi về hồ sơ chung: rơi về là hiện nhiệm vụ của cơ sở khác. */
function getNhiemVuNV(pin, coSo){
  var u = _requireAuth(pin);
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  var m = {};
  if (!coSo) return m;
  if (!(u.isAdmin || u.all) && !_canStation(u, coSo)) return m;
  _nvcsDoc(coSo).list.forEach(function(x){
    var s = _nhiemVuChuoi(x.nhiemVu);
    if (s) m[x.ma] = s;                       // ô trống -> không gửi, client tự hiểu là mặc định
  });
  return m;
}

/* ===========================================================================
 *  NHIỆM VỤ THEO TỪNG NGÀY  —  sheet `ChamCongNhiemVu`
 * ---------------------------------------------------------------------------
 *  Người kiêm ≥2 nhiệm vụ thì mỗi ngày làm một việc khác nhau, nên phải ghi
 *  nhiệm vụ CỦA NGÀY ĐÓ, không thể suy từ hồ sơ.
 *
 *  ⚠️ Vì sao KHÔNG ghi vào sheet CS_: bố cục CS_ là A=Họ tên · B=ID · từ C mỗi
 *     ngày 5 cột, và công thức `cột = 3 + ngày × 5` nằm cứng ở 8 chỗ, gồm cả
 *     đường doPost mà máy chấm công ghi vào. Chèn thêm cột là máy ghi giờ vào
 *     đúng ô nhiệm vụ mà vẫn trả SUCCESS — sai im lặng. Nên để sheet RIÊNG.
 *  ⚠️ Khoá là bộ ba (ngày, cơ sở, mã NV): một người có thể làm 2 cơ sở trong
 *     cùng một ngày, mỗi nơi một nhiệm vụ.
 * =========================================================================== */
/* 🔴 03/08/2026 — `_fmt` ĐƯỢC GỌI Ở HAI CHỖ DƯỚI ĐÂY MÀ CHƯA HỀ ĐƯỢC ĐỊNH NGHĨA.
   Hậu quả: `_datNhiemVuNgay` và `getNhiemVuNgay` VĂNG NGAY khi sheet đã có ≥1 dòng dữ liệu —
   tức là lỗi chỉ xuất hiện từ lượt ghi THỨ HAI trở đi, lượt đầu (sheet còn trống) vẫn chạy ngon.
   Đúng kiểu bẫy tệ nhất: thử một phát thấy chạy, tưởng xong.
   Test `test_nhiemvu.js` mục (D2) bắt được. Ô ngày trong Sheet có thể là Date hoặc chuỗi nên
   phải nắn cả hai về 'yyyy-MM-dd', không thì khoá so sánh không bao giờ khớp -> ghi trùng dòng. */
function _fmt(v){
  if (v instanceof Date) return Utilities.formatDate(v, TZ, 'yyyy-MM-dd');
  return String(v == null ? '' : v).trim();
}
var SH_CCNV   = 'ChamCongNhiemVu';
var CCNV_H    = ['Ngày', 'Cơ sở', 'Mã NV', 'Nhiệm vụ', 'Ghi lúc', 'Người ghi'];
function _ccnvSheet(){ return _ensureSheet(SH_CCNV, CCNV_H); }
function _ccnvKhoa(ngay, coSo, maNV){
  return String(ngay || '').trim() + '|' + String(coSo || '').replace(/^CS_/, '').trim()
       + '|' + String(maNV || '').trim();
}
/** Ghi nhiệm vụ của MỘT ngày. Trả {ok, nhiemVu}. nhiemVu rỗng = xoá về mặc định. */
function _datNhiemVuNgay(ngay, coSo, maNV, nhiemVu, nguoiGhi){
  ngay  = String(ngay || '').trim();
  coSo  = String(coSo || '').replace(/^CS_/, '').trim();
  maNV  = String(maNV || '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(ngay) || !coSo || !maNV) return { ok:false, error:'Thiếu ngày / cơ sở / mã NV.' };
  var nv = _chuanNhiemVu(nhiemVu);
  if (nv === null) return { ok:false, error:'Nhiệm vụ không hợp lệ.' };
  var sh = _ccnvSheet(), khoa = _ccnvKhoa(ngay, coSo, maNV);
  var lock = LockService.getScriptLock();
  try { lock.waitLock(10000); } catch (e) {}
  try {
    var hang = 0;
    if (sh.getLastRow() >= 2) {
      var v = sh.getRange(2, 1, sh.getLastRow() - 1, 3).getValues();
      for (var i = 0; i < v.length; i++)
        if (_ccnvKhoa(_fmt(v[i][0]), v[i][1], v[i][2]) === khoa) { hang = i + 2; break; }
    }
    var dong = [ngay, coSo, maNV, nv, _now(), String(nguoiGhi || '')];
    if (hang) sh.getRange(hang, 1, 1, CCNV_H.length).setValues([dong]);   // ghi ĐÈ, không thêm dòng thứ hai
    else      sh.appendRow(dong);
    return { ok:true, nhiemVu:nv };
  } finally { lock.releaseLock(); }
}
/** Người dùng đặt nhiệm vụ cho 1 ngày (Admin/Quản lý/CHT của cơ sở đó). */
function datNhiemVuNgay(pin, ngay, coSo, maNV, nhiemVu){
  var u = _requireAuth(pin);
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!u.isAdmin && !u.all && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cơ sở này.' };
  return _datNhiemVuNgay(ngay, coSo, maNV, nhiemVu, u.name || '');
}
/** Bảng {ngay|maNV: nhiệm vụ} của MỘT cơ sở, lọc theo tháng 'yyyy-MM' nếu có. */
function getNhiemVuNgay(pin, coSo, thang){
  var u = _requireAuth(pin);
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!coSo) return {};
  if (!u.isAdmin && !u.all && !_canStation(u, coSo)) return {};
  var sh = _sheet(SH_CCNV), m = {};
  if (!sh || sh.getLastRow() < 2) return m;
  thang = String(thang || '').trim();               // '' = lấy hết
  var v = sh.getRange(2, 1, sh.getLastRow() - 1, CCNV_H.length).getValues();
  for (var i = 0; i < v.length; i++){
    var ngay = _fmt(v[i][0]);
    if (String(v[i][1] || '').trim() !== coSo) continue;
    if (thang && ngay.slice(0, 7) !== thang) continue;
    var nv = String(v[i][3] || '').trim();
    if (nv) m[ngay + '|' + String(v[i][2] || '').trim()] = nv;
  }
  return m;
}
var NV_SENSITIVE = { baseSalary:1, bankAccount:1, bankName:1 };   // chỉ Admin/Quản lý xem


// ============================ ĐIỂM VÀO WEB APP ============================
function doGet(e) {
  var p = (e && e.parameter) ? e.parameter : {};
  var action = p.action || '';

  // ESP32 hỏi lệnh "tải lại"
  if (action === 'getCmd') return _handleGetCmd(p.station);

  // ESP32 đồng bộ nhân viên (queue). Bảo vệ bằng token.
  if (action === 'pending' || action === 'photo' || action === 'ack') {
    if (!_nhanToken(p)) return _json({ ok:false, error:'bad_token' });
    if (action === 'pending') return _empPending(p.station);
    if (action === 'photo')   return _empPhoto(p.opId);
    if (action === 'ack')     return _empAck(p.opId, p.status, p.msg);
  }

  // Kích hoạt tạo tab từ xa (1 lần, có token). Idempotent - gọi lại không hại.
  if (action === 'setup') {
    if (!_nhanToken(p)) return _json({ ok:false, error:'bad_token' });
    _nangCap();   // token đã xác thực ở trên; wrapper public chỉ dành cho editor
    return _json({ ok:true, sheets: SpreadsheetApp.getActiveSpreadsheet().getSheets().map(function(s){ return s.getName(); }) });
  }

  // Tạo 3 trigger tự động (1 lần, có token). Nếu lỗi quyền -> chạy setupAutoTriggers() từ editor.
  if (action === 'setupTriggers') {
    if (!_nhanToken(p)) return _json({ ok:false, error:'bad_token' });
    try { return _json(_taoTrigger()); } catch (te) { return _json({ ok:false, error:String(te), hint:'Mở editor Apps Script, chạy hàm setupAutoTriggers() để cấp quyền.' }); }
  }
  // Chạy thử từng tác vụ tự động (có token): ?action=runAuto&which=addday|backfill|warn
  // Gọi THÂN hàm (_…) chứ không gọi wrapper: wrapper chỉ cho trigger/editor, còn ở đây token đã xác thực.
  if (action === 'runAuto') {
    if (!_nhanToken(p)) return _json({ ok:false, error:'bad_token' });
    if (p.which === 'addday')   return _json(_capNhatCotNgay());
    // ?action=runAuto&which=backfill[&ngay=1..7] — mặc định 2 ngày (như lượt sáng)
    if (p.which === 'backfill') return _json(_backfillTatCa(Number(p.ngay) || 2));
    if (p.which === 'warn')     return _json({ ok:true, missing: _canhBaoChuaCheckin() });
    return _json({ ok:false, error:'which=addday|backfill|warn' });
  }

  // Xem nhanh dữ liệu để chẩn đoán (có token). ?action=peek  hoặc  ?action=peek&sheet=CS_TUTU_BT
  if (action === 'peek') {
    if (!_nhanToken(p)) return _json({ ok:false, error:'bad_token' });
    var _ss = SpreadsheetApp.getActiveSpreadsheet();
    if (!p.sheet) return _json({ sheets: _ss.getSheets().map(function(s){ return { name:s.getName(), rows:s.getLastRow(), cols:s.getLastColumn() }; }) });
    var _sh = _ss.getSheetByName(p.sheet);
    if (!_sh) return _json({ error:'no_sheet' });
    var _lc = _sh.getLastColumn(), _lr = _sh.getLastRow();
    var _hdr = _sh.getRange(1, 1, Math.min(2, _lr), _lc).getDisplayValues();
    var _row = (_lr >= 3) ? _sh.getRange(3, 1, 1, _lc).getDisplayValues()[0] : [];
    /* `true` = đọc THÔ, KHÔNG gộp mã cũ về mã chính. Máy suy nghĩ bằng mã của MÁY: trả mã chính
       về cho nó là nó tưởng lượt đó đã ghi rồi và bỏ qua lượt thật. */
    var _d = _docSheetData(p.sheet, '', true);   // doGet đã gác bằng token ở trên
    return _json({ sheet:p.sheet, cols:_lc, rows:_lr, rowCount:(_d.rows||[]).length, dates:_d.dates,
                   header1:_hdr[0], header2:_hdr[1] || [], firstDataRow:_row });
  }

  // MÁY HỎI "tôi ở cửa hàng nào?" (có token): ?action=whoami&serial=...&mac=...
  // Firmware cần TÊN cửa hàng để biết đường Firebase (/queue/<trạm>, /hb/<trạm>, /roster/<trạm>)
  // nên không thể để server tự suy hết — máy hỏi 1 lần lúc khởi động rồi nhớ vào Preferences.
  // Máy lạ -> tự vào bảng MayChamCong với cửa hàng TRỐNG, hiện lên web app để anh gán.
  if (action === 'whoami') {
    if (!_nhanToken(p)) return _json({ ok:false, error:'bad_token' });
    var _wm = _giaiMaTram(p.serial || '', p.mac || '', p.station || '', p.model || '');
    if (_wm.choGan) return _json({ ok:false, choGan:true, station:'',
      error:'May chua duoc gan cua hang — vao web app, tab May cham cong, chon cua hang cho may nay.' });
    return _json({ ok:true, station:_wm.station, nguon:_wm.nguon, lech:_wm.lech });
  }

  // Chẩn đoán lịch/ca (có token): ?action=scheddbg&station=TUTU_BT&date=2026-07-05&emp=TUBT2
  if (action === 'scheddbg') {
    if (!_nhanToken(p)) return _json({ ok:false, error:'bad_token' });
    var _st = p.station || '', _asg = [];
    var _lsh = _lichSheet();
    if (_lsh.getLastRow() >= 2) {
      var _lv = _lsh.getRange(2, 1, _lsh.getLastRow() - 1, 6).getValues();
      _lv.forEach(function(r){
        if (String(r[0]) !== String(_st)) return;
        var day = (r[1] instanceof Date) ? Utilities.formatDate(r[1], TZ, 'yyyy-MM-dd') : String(r[1]);
        if (p.date && day !== p.date) return;
        if (p.emp && String(r[2]) !== String(p.emp)) return;
        _asg.push({ date:day, emp:String(r[2]||''), name:String(r[3]||''), ca:String(r[4]||''), jobs:String(r[5]||'') });
      });
    }
    return _json({ station:_st, date:p.date||'', emp:p.emp||'', isWeekend:_isWkendD(p.date||''), shifts:_shiftsOf(_st), assign:_asg });
  }

  // Vá sheet bị lệch cột: quét giờ theo khối ngày rồi dựng lại 5 cột/ngày chuẩn.
  // ?action=heal&sheet=CS_TUTU_BT&token=..  (xem trước)  | thêm &dry=0 để GHI THẬT.
  if (action === 'heal') {
    if (!_nhanToken(p)) return _json({ ok:false, error:'bad_token' });
    if (!p.sheet) return _json({ ok:false, error:'no_sheet' });
    if (!SpreadsheetApp.getActiveSpreadsheet().getSheetByName(p.sheet))
      return _json({ ok:false, error:'Khong co sheet "' + p.sheet + '"' });
    var _chan = _csChanHealNhieuKhoi(p.sheet);       // sheet nhiều khối tháng -> từ chối, có lý do
    if (_chan) return _json({ ok:false, error:_chan });
    var _hd = _healExtract(p.sheet);
    if (p.dry === '0') {
      /* ⚠️ ĐÂY LÀ LỆNH XOÁ SẠCH SHEET RỒI DỰNG LẠI. Token KHÔNG đủ để cho phép:
         token nằm trong firmware của MỌI máy, và từng nằm trong file .bin commit lên repo.
         Bắt thêm PIN Admin + gõ đúng tên sheet để không thể lỡ tay bằng 1 đường link. */
      var _u = loginByPin(p.pin || '');
      if (_u.ok === false || !_u.isAdmin)
        return _json({ ok:false, error:'Lenh nay XOA SACH sheet roi dung lai. Can them &pin=<PIN Admin>.' });
      if (p.confirm !== p.sheet)
        return _json({ ok:false, error:'Can them &confirm=' + p.sheet + ' (go dung ten sheet) de xac nhan.' });
      var _rb = _rebuildAttendanceSheet(p.sheet, _hd);
      return _json({ ok:true, wrote:true, rebuilt:_rb, banSaoLuu:_rb.banSaoLuu,
                     canhBao:_hd.canhBao, boQuaLuot:_hd.boQuaLuot, giuAnh:_hd.soAnh });
    }
    return _json({ ok:true, dryRun:true, empCount:_hd.emps.length, dateCount:_hd.dates.length,
                   recordCount:_hd.recs.length, giuAnh:_hd.soAnh, boQuaLuot:_hd.boQuaLuot,
                   canhBao:_hd.canhBao, records:_hd.recs });
  }

  /* ---------------------------------------------------------------------
   *  TRANG CHẤM CÔNG NHẸ  —  ?cc=1        (06/08/2026)
   * ---------------------------------------------------------------------
   *  Anh Thắng: *"nhiều điện thoại yếu không vào được"*. Trang đầy đủ nặng ~484 KB HTML cho
   *  một người chỉ cần chụp ảnh rồi bấm Lưu — máy yếu / 3G tải xong đã hết kiên nhẫn.
   *  `ChamCong.html` chỉ chứa đúng phần chấm công, không bảng, không tab, không quản trị.
   *
   *  ⚠️ CÙNG deployment, CÙNG link `/exec`, chỉ thêm `?cc=1`. KHÔNG tạo bản triển khai thứ hai:
   *     hai link là hai chỗ phải nhớ cập nhật, và link cũ sẽ chạy code cũ mãi mãi.
   *  ⚠️ Phân nhánh Ở ĐÂY, TRƯỚC khi dựng `Index`. Nếu để phía trình duyệt tự ẩn thì máy vẫn phải
   *     tải trọn 484 KB rồi mới ẩn — tức là không chữa được gì.
   *  ⚠️ Trang nhẹ KHÔNG có đường ghi riêng: vẫn gọi đúng `chamCongOnline` mà trang đầy đủ gọi.
   *     Dựng đường ghi thứ hai là hai bộ luật chấm công, sớm muộn lệch nhau. */
  if (p.cc === '1' || p.cc === 'yes') {
    var _tc = HtmlService.createTemplateFromFile('ChamCong');
    _tc.SSO_USER = _ssoNhung(e);
    return _tc.evaluate()
      .setTitle('Chấm công')
      .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL)
      .addMetaTag('viewport', 'width=device-width, initial-scale=1');
  }

  var _t = HtmlService.createTemplateFromFile('Index');
  _t.SSO_USER = _ssoNhung(e);
  return _t.evaluate()
    .setTitle('Dashboard Chấm Công Chuỗi Cửa Hàng')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL)
    .addMetaTag('viewport', 'width=device-width, initial-scale=1');
}
/** Danh tính SSO nhúng vào trang, dạng chuỗi JSON (hoặc 'null'). Một định nghĩa cho CẢ HAI trang —
 *  chép tay hai lần là kiểu gì cũng có ngày một bên quên sửa. */
function _ssoNhung(e){
  var u = _ssoLogin((e && e.parameter && e.parameter.sso) || '');
  return u ? JSON.stringify({ name:u.name, role:u.role, isAdmin:u.isAdmin, token:u.pin }) : 'null';
}


// ===== LỆNH TẢI LẠI (BACKFILL) TỪ XA =====
// ⚠️ GÁC QUYỀN BẮT BUỘC: đây là hàm GHI (ra lệnh cho máy chấm công). Trước đây `if (pin)` nên
// không truyền pin là ra lệnh được cho BẤT KỲ cơ sở nào mà không cần đăng nhập.
// Trigger tự động (autoBackfillAll) dùng _raLenhBackfill() — không qua người dùng nên không gác.
function requestBackfill(station, startTime, endTime, withImage, empNo, pin) {
  if (!station) return { status: 'ERROR', message: 'Thiếu tên trạm.' };
  var u = loginByPin(pin);
  if (u.ok === false) return { status:'ERROR', message:'Phiên đăng nhập không hợp lệ, hãy đăng nhập lại.' };
  if (!u.isAdmin && !u.all && !_canStation(u, station)) return { status:'ERROR', message:'Bạn không có quyền cửa hàng này.' };
  return _raLenhBackfill(station, startTime, endTime, withImage, empNo);
}
/* ===========================================================================
 *  TẢI LẠI DÀI NGÀY -> CẮT THÀNH TỪNG NGÀY MỘT
 * ---------------------------------------------------------------------------
 *  Anh Thắng 02/08/2026: *"tải nguyên tháng thì không được, nhưng tải 1 ngày thì lên"*.
 *  Đọc firmware (esp32_hik_chamcong_full.ino:1753 backfillRange) thì rõ vì sao — MỘT lệnh
 *  cả tháng chạy trong MỘT vòng: mỗi trang 20 lượt, mỗi lượt là 1 HTTP POST lên Apps Script
 *  rồi delay(50). Vài trăm lượt là hàng chục phút kẹt trong 1 vòng loop, kéo theo:
 *     · phiên tìm của đầu đọc Hikvision (searchID) hết hạn giữa chừng -> hikPost trả rỗng ->
 *       `break` -> lặng lẽ dừng ở giữa, KHÔNG báo lỗi
 *     · không kịp gửi heartbeat -> web tưởng máy offline
 *     · máy tự khởi động lại là mất trắng, vì op trong /queue đã bị xoá TRƯỚC khi chạy
 *       (dòng 1691 .ino) nên không có gì chạy lại
 *  Một ngày thì vài chục lượt, xong trong tích tắc — nên "1 ngày thì lên".
 *
 *  Cách chữa KHÔNG cần nạp lại firmware: web cắt dải dài thành nhiều lệnh 1 ngày. Firmware
 *  lấy hàng đợi bằng `orderBy="$key"&limitToFirst=1` (dòng 1505 .ino) nên nó rút TỪNG lệnh
 *  một, chạy xong mới lấy lệnh kế — đúng thứ mình cần, không phải sửa gì bên máy.
 *
 *  ⚠️ Khoá op đặt tiền tố 'op-zbf-' để SẮP CUỐI hàng đợi. Khoá cũ là hex ngẫu nhiên, mà hàng
 *     đợi chạy theo thứ tự khoá — 31 lệnh tải lại nằm giữa hàng thì lệnh gấp (thêm/xoá nhân
 *     viên) phải xếp sau cả tháng backfill. 'z' lớn hơn mọi ký tự hex nên lệnh thường luôn
 *     chen lên trước.
 *  ⚠️ Ngày xếp trong khoá (op-zbf-YYYYMMDD-…) để chạy từ ngày cũ tới ngày mới, không lộn xộn.
 *
 *  🔴🔴 03/08/2026 23h25 — anh Thắng: *"đơ lệnh rồi"*. Hàng đợi FZ_SC_VIVO_T4 nằm im 3 lệnh
 *     (op-zbf-20260801-000 / -20260802-001 / -20260803-002), máy ONLINE, poll 10 giây/lần mà
 *     4 phút không rút lệnh nào. LỖI Ở KHOÁ CỦA HÀM NÀY, không phải ở máy:
 *       · khoá cũ = 'op-zbf-' + ngày + '-' + thứ-tự  →  LẶP LẠI Y NGUYÊN mỗi lần xếp lại
 *         cùng dải ngày. Xếp lại 01→03/08 là sinh đúng 3 khoá y như lần trước.
 *       · firmware giữ 8 khoá vừa xử lý trong RAM (`g_doneOps`, .ino:1480) và BỎ QUA khoá đã
 *         xử lý: `if(opDone(opId)) continue;` (.ino:1515).
 *       · nhưng nó đọc bằng `limitToFirst=1` nên vòng lặp CHỈ CÓ MỘT lượt — `continue` là hết
 *         vòng, hàm trả "" = "hàng đợi rỗng".
 *     ⇒ Xếp lại một ngày ĐÃ CHẠY trong 8 lệnh gần nhất là lệnh đó nằm chết ở đầu hàng, và
 *       chặn sạch mọi lệnh phía sau — kể cả thêm/xoá nhân viên. Máy vẫn báo online, vẫn chấm
 *       công bình thường (chấm công không qua hàng đợi) nên nhìn như "máy hỏng".
 *     Chữa: khoá mang thêm đuôi NGẪU NHIÊN → không lần nào trùng lần nào, `opDone` không bao
 *     giờ khớp. Ngày vẫn đứng đầu khoá nên thứ tự chạy cũ→mới không đổi.
 *     (Bên firmware còn một lỗ nữa: `fbDelete` không kiểm kết quả, xoá hỏng là cũng kẹt y vậy —
 *      đã sửa trong nguồn .ino, chờ OTA được thì nạp.)
 * =========================================================================== */
var BF_TOI_DA_NGAY = 62;    // quá 2 tháng thì bắt thu hẹp lại — đừng để lỡ tay bơm cả năm lệnh
/* Cắt dải thành từng ngày, và CHẶN NGÀY CHƯA TỚI.
 * 🔴 03/08/2026 — anh Thắng: *"tại sao lại lệnh của ngày chưa tới là sao"*. Chọn "Tháng 08-2026"
 *    lúc mới ngày 03/08 thì client gửi `2026-08-01 → 2026-08-31`, hàm này cắt ra đủ 31 ngày và
 *    xếp **28 lệnh cho ngày chưa xảy ra**. Máy đi hỏi đầu đọc từng ngày rỗng — mất mấy chục phút
 *    vô ích, hàng đợi dài ra, mà lệnh gấp (thêm/xoá NV) thì phải chờ.
 *    Nay cắt ngọn ở HÔM NAY. So chuỗi 'yyyy-MM-dd' là đủ, không cần dựng Date.
 * Trả: null = dải không đọc được · [] = TOÀN BỘ dải nằm ở ngày chưa tới. */
function _bfNgayList(startISO, endISO){
  var s = String(startISO || '').slice(0, 10), e = String(endISO || '').slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(s) || !/^\d{4}-\d{2}-\d{2}$/.test(e)) return null;
  var hnay = _bfHomNay();
  if (e > hnay) e = hnay;                              // cắt ngọn: không tải lại ngày chưa tới
  if (s > e) return [];                                // cả dải ở tương lai
  var t0 = Date.parse(s + 'T00:00:00Z'), t1 = Date.parse(e + 'T00:00:00Z');
  if (isNaN(t0) || isNaN(t1) || t1 < t0) return null;
  var out = [];
  for (var t = t0; t <= t1; t += 86400000){
    out.push(new Date(t).toISOString().slice(0, 10));
    if (out.length > BF_TOI_DA_NGAY) return out;      // trả dư 1 để bên ngoài biết là vượt
  }
  return out;
}
function _raLenhBackfill(station, startTime, endTime, withImage, empNo) {
  if (!station) return { status: 'ERROR', message: 'Thiếu tên trạm.' };
  var _ngay = _bfNgayList(startTime, endTime);
  if (_ngay && _ngay.length > BF_TOI_DA_NGAY)
    return { status:'ERROR', message:'Dải quá dài (' + _ngay.length + ' ngày). Tải lại tối đa '
             + BF_TOI_DA_NGAY + ' ngày mỗi lần — chọn từng tháng cho chắc.' };
  /* Cả dải ở tương lai -> KHÔNG xếp lệnh nào, và NÓI RA. Trả im lặng "OK" là anh Thắng tưởng đã
     gửi rồi ngồi chờ mãi. */
  if (_ngay && !_ngay.length)
    return { status:'ERROR', message:'Khoảng đã chọn toàn là ngày CHƯA TỚI (hôm nay mới '
             + _bfHomNay() + ') — không có gì để tải lại.' };
  if (_ngay && _ngay.length > 1) return _raLenhBackfillNhieuNgay(station, _ngay, withImage, empNo);
  /* 🔴 Chỉ còn MỘT ngày sau khi cắt ngọn -> phải dùng ĐÚNG ngày đó, KHÔNG dùng lại startTime/
     endTime người gọi gửi. Dùng lại là xếp nguyên dải cả tháng vào một lệnh — đúng thứ đã làm
     đầu đọc hết phiên tìm giữa chừng (02/08). */
  if (_ngay && _ngay.length === 1) {
    startTime = _ngay[0] + 'T00:00:00+07:00';
    endTime   = _ngay[0] + 'T23:59:59+07:00';
  }
  // Nhớ khoảng đã xin -> bộ đếm mới phân biệt được "lượt của đợt này" với lượt bù định kỳ.
  _bfDatKhoang(station, startTime, endTime);
  /* Cờ DỪNG còn treo thì lệnh vừa xếp bị máy giết ngay ở trang đầu. Xoá trước khi ra lệnh mới —
     đây là một trong hai chỗ xoá cờ (chỗ kia là chính firmware lúc nó dừng). */
  _bfXoaCoDung(station);
  var lock = LockService.getScriptLock();
  try { lock.waitLock(10000); } catch (le) {}
  try {
    // Đường CŨ (?action=getCmd): firmware hiện KHÔNG còn dùng (USE_FIREBASE=true, đọc lệnh qua
    // Firebase). Giữ lại để firmware cũ chưa nạp lại vẫn nhận được lệnh, nhưng CÓ HẠN DÙNG:
    // trước đây ghi rồi không ai đọc -> đọng vĩnh viễn, và nếu 1 máy cũ online lại sau nhiều tháng
    // thì nó chạy luôn lệnh tải lại đã quá hạn.
    PropertiesService.getScriptProperties().setProperty('cmd_' + station, JSON.stringify({
      cmd: 'backfill', station: station, startTime: startTime, endTime: endTime,
      image: (withImage !== false),
      emp: empNo || "",
      at: (new Date()).getTime()
    }));
    // Máy 4G KHÔNG đọc getCmd -> đẩy lệnh backfill qua Firebase /queue để chúng nhận được
    try {
      var _op = 'op-' + Utilities.getUuid().substring(0, 8);
      _fbPut('/queue/' + station + '/' + _op, {
        action: 'backfill', startTime: startTime, endTime: endTime,
        image: (withImage !== false), employeeNo: empNo || '', createdAt: _now()
      });
    } catch (e) {}
    return { status: 'OK', message: 'Đã gửi lệnh tải lại cho máy "' + station + '"'
             + (empNo ? ' (NV ' + empNo + ')' : ' (tất cả NV)')
             + (withImage === false ? ' - chỉ giờ' : ' - giờ (+ ảnh nếu máy nối WiFi; máy 4G chỉ giờ)')
             + '. Máy sẽ đồng bộ trong ~20 giây tới.' };
  } finally {
    lock.releaseLock();
  }
}
/** Xếp NHIỀU lệnh 1-ngày vào hàng đợi. Máy rút từng lệnh, chạy xong mới lấy lệnh kế. */
function _raLenhBackfillNhieuNgay(station, ngayList, withImage, empNo){
  if (ngayList && ngayList.length)
    _bfDatKhoang(station, ngayList[0], ngayList[ngayList.length - 1]);
  _bfXoaCoDung(station);            // xem ghi chú ở `_raLenhBackfill`
  var lock = LockService.getScriptLock();
  try { lock.waitLock(10000); } catch (le) {}
  try {
    var xong = 0, hong = 0;
    ngayList.forEach(function(ngay, i){
      /* 🔴 Đuôi ngẫu nhiên là BẮT BUỘC — xem khối ghi chú trên đầu mục này. Khoá trùng khoá cũ
         là firmware coi lệnh "đã xử lý" rồi bỏ qua, mà `limitToFirst=1` nên bỏ qua = báo hàng
         đợi rỗng → lệnh nằm chết ở đầu hàng, chặn hết phía sau. NGÀY vẫn phải đứng trước đuôi
         để thứ tự chạy còn là cũ→mới. */
      var key = 'op-zbf-' + ngay.replace(/-/g, '') + '-' + ('00' + i).slice(-3)
              + '-' + Utilities.getUuid().substring(0, 8);
      var ok = false;
      try {
        ok = _fbPut('/queue/' + station + '/' + key, {
          action:'backfill', startTime: ngay + 'T00:00:00+07:00', endTime: ngay + 'T23:59:59+07:00',
          image: (withImage !== false), employeeNo: empNo || '', createdAt: _now()
        });
      } catch (e) { ok = false; }
      if (ok) xong++; else hong++;
    });
    // Đường CŨ (?action=getCmd) chỉ có MỘT ô nhớ -> chỉ nhét được ngày đầu. Firmware đang chạy
    // đọc lệnh qua Firebase nên không sao; ghi ngày đầu để bản firmware cũ vẫn nhúc nhích được.
    if (ngayList.length) {
      try {
        PropertiesService.getScriptProperties().setProperty('cmd_' + station, JSON.stringify({
          cmd:'backfill', station:station,
          startTime: ngayList[0] + 'T00:00:00+07:00', endTime: ngayList[0] + 'T23:59:59+07:00',
          image:(withImage !== false), emp: empNo || '', at:(new Date()).getTime() }));
      } catch (e) {}
    }
    // ⚠️ Ghi hỏng thì PHẢI báo. Nói "đã gửi" trong khi Firebase từ chối là đúng kiểu hứa suông
    //    đã mất công một lần với chuyện "đã đồng bộ" mà thật ra chưa.
    if (!xong) return { status:'ERROR', message:'KHÔNG xếp được lệnh nào lên Firebase (kiểm FB_SECRET). '
             + 'Máy sẽ không tải lại gì cả.' };
    return { status:'OK', message:'Đã xếp ' + xong + ' lệnh tải lại cho máy "' + station + '" — mỗi ngày một lệnh'
             + (hong ? (', ' + hong + ' ngày KHÔNG xếp được (kiểm FB_SECRET)') : '')
             + (empNo ? (' · NV ' + empNo) : ' · tất cả NV')
             + (withImage === false ? ' · chỉ giờ' : ' · giờ (+ ảnh nếu máy nối WiFi)')
             + '. Máy chạy lần lượt từng ngày, cả ' + xong + ' ngày mất khoảng '
             + Math.max(1, Math.round(xong * 0.7)) + '–' + (xong * 2) + ' phút — cứ để máy chạy, '
             + 'xem lại sau. Tải cả tháng trong MỘT lệnh thì đầu đọc hết phiên tìm giữa chừng '
             + 'nên trước đây không lên.' };
  } finally { lock.releaseLock(); }
}

/* ===========================================================================
 *  ĐẾM LƯỢT MÁY ĐẨY LÊN: MỚI hay TRÙNG  —  _bfDem / getThongKeDay
 * ---------------------------------------------------------------------------
 *  Anh Thắng 02/08/2026: *"thấy nó lên ở lượt đồng bộ 225, vậy khả năng đồng bộ
 *  TRÙNG lượt chứ không đồng bộ nối tiếp"*. Câu này không đoán được — phải đếm.
 *
 *  Vì sao con số trên MÀN HÌNH MÁY không trả lời được: firmware tăng bộ đếm khi
 *  máy chủ trả SUCCESS, mà lượt TRÙNG cũng trả SUCCESS (bắt buộc — trả khác là
 *  firmware coi như thất bại rồi đẩy lại mãi, xem .ino:483). Nên "225 lượt" có
 *  thể là 225 lượt mới, mà cũng có thể là 225 lượt trùng lặp đi lặp lại.
 *  Chỉ MÁY CHỦ mới phân biệt được, vì chính nó so với ô đang có trong Sheet.
 *
 *  ⚠️ Đây là ĐƯỜNG NÓNG (mỗi lượt chấm công) -> dùng CacheService, KHÔNG ghi
 *     Script Property / Sheet. Mất số đếm cũng chẳng sao, nó chỉ để chẩn đoán.
 *  ⚠️ Bọc try/catch: hỏng chỗ đếm TUYỆT ĐỐI không được làm rơi lượt chấm công.
 * =========================================================================== */
var BF_STAT_TTL = 6 * 3600;      // giữ 6 tiếng, đủ để soi một đợt tải lại
function _bfDem(station, loai, moc){
  try {
    var c = CacheService.getScriptCache(); if (!c) return;
    var k = 'bfstat_' + String(station || '').replace(/[^A-Za-z0-9_-]/g, '');
    var s = {};
    try { s = JSON.parse(c.get(k) || '{}') || {}; } catch (e) { s = {}; }
    s.tong = (s.tong || 0) + 1;
    if (loai === 'trung' || loai === 'giua') s.trung = (s.trung || 0) + 1;
    else                                     s.moi   = (s.moi   || 0) + 1;
    if (!s.batDau) s.batDau = _now();
    s.ganNhat = _now();
    moc = String(moc || '');
    if (moc){ if (!s.mocSom || moc < s.mocSom) s.mocSom = moc;
              if (!s.mocMuon || moc > s.mocMuon) s.mocMuon = moc; }
    c.put(k, JSON.stringify(s), BF_STAT_TTL);
  } catch (e) {}
}
/* ===========================================================================
 *  KHOẢNG NGÀY ĐÃ YÊU CẦU — để bộ đếm nói thật về "đợt này"
 * ---------------------------------------------------------------------------
 *  🔴 07/08/2026, anh Thắng: *"chọn đồng bộ ngày 06/08, máy lại nhận ngày 20/07"*.
 *
 *  Đọc lại thì bộ đếm KHÔNG hề biết anh vừa xin ngày nào. `_bfDem` đếm MỌI lượt máy đẩy về
 *  cửa hàng đó — lượt quẹt trực tiếp, lượt bù lúc khởi động, và **lượt bù ĐỊNH KỲ 30 phút**
 *  (firmware .ino:3114 `backfillRange(lastSyncTime → 2037)`). Cái nhãn "trong đợt này" của
 *  giao diện là do web tự gán, không có gì bảo đảm.
 *
 *  Nên chuyện xảy ra rất bình thường: lệnh 06/08 còn đang nằm trong hàng đợi (chưa chạy), mà
 *  đúng lúc đó lượt bù định kỳ đẩy về một lượt cũ ngày 20/07 → web ghi "đợt này nhận 20/07".
 *  Web nói sai, không phải máy chọn nhầm ngày.
 *
 *  Chữa: nhớ lại KHOẢNG ĐÃ XIN mỗi lần ra lệnh, rồi đối chiếu với mốc lượt nhận được. Chỉ kết
 *  luận "ngoài khoảng" khi CẢ mốc sớm nhất LẪN mốc muộn nhất đều nằm ngoài — có bấy nhiêu dữ
 *  liệu thì chỉ được nói bấy nhiêu.
 * =========================================================================== */
function _bfKhoaWin(station){ return 'bfwin_' + String(station || '').replace(/[^A-Za-z0-9_-]/g, ''); }
function _bfDatKhoang(station, tuISO, denISO){
  try {
    var c = CacheService.getScriptCache(); if (!c) return;
    var tu = String(tuISO || '').slice(0, 10), den = String(denISO || '').slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(tu) || !/^\d{4}-\d{2}-\d{2}$/.test(den)) return;
    c.put(_bfKhoaWin(station), tu + '|' + den, BF_STAT_TTL);
  } catch (e) {}
}
/** Xem máy đang đẩy lượt MỚI hay đẩy lại lượt CŨ. CHỈ ĐỌC. */
function getThongKeDay(pin, station){
  var u = loginByPin(pin);
  if (u.ok === false) return { ok:false, error:'Chưa đăng nhập.' };
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Thiếu tên cơ sở.' };
  if (!u.isAdmin && !u.all && !_canStation(u, station)) return { ok:false, error:'Không có quyền cơ sở này.' };
  var s = {}, win = '';
  try { s = JSON.parse(CacheService.getScriptCache().get(
          'bfstat_' + station.replace(/[^A-Za-z0-9_-]/g, '')) || '{}') || {}; } catch (e) { s = {}; }
  try { win = String(CacheService.getScriptCache().get(_bfKhoaWin(station)) || ''); } catch (e) { win = ''; }
  var tong = Number(s.tong) || 0, trung = Number(s.trung) || 0, moi = Number(s.moi) || 0;
  var w = win.split('|'), khTu = w[0] || '', khDen = w[1] || '';
  /* NGOÀI KHOẢNG = mọi lượt nhận được đều không thuộc khoảng đã xin. Dùng min/max nên chỉ
     khẳng định được khi cả hai đầu cùng lệch về một phía — đúng bằng chứng đang có, không hơn. */
  var mSom = String(s.mocSom || '').slice(0, 10), mMuon = String(s.mocMuon || '').slice(0, 10);
  var ngoaiKhoang = !!(tong && khTu && khDen && mSom && mMuon
                       && (mMuon < khTu || mSom > khDen));
  return { ok:true, station:station, tong:tong, moi:moi, trung:trung,
           batDau:s.batDau || '', ganNhat:s.ganNhat || '',
           mocSom:s.mocSom || '', mocMuon:s.mocMuon || '',
           khoangTu:khTu, khoangDen:khDen, ngoaiKhoang:ngoaiKhoang,
           // Đẩy nhiều mà gần như toàn trùng = đang quét lại vùng đã có, không phải chạy tiếp.
           nghiLapLai: (tong >= 20 && moi * 10 < tong) };
}
function xoaThongKeDay(pin, station){
  var u = loginByPin(pin);
  if (u.ok === false) return { ok:false, error:'Chưa đăng nhập.' };
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!u.isAdmin && !u.all && !_canStation(u, station)) return { ok:false, error:'Không có quyền cơ sở này.' };
  try { CacheService.getScriptCache().remove('bfstat_' + station.replace(/[^A-Za-z0-9_-]/g, '')); } catch (e) {}
  return { ok:true };
}

/* Còn bao nhiêu lệnh tải lại đang chờ máy chạy — để xem TIẾN ĐỘ từ web.
   ⚠️ Vì sao cần: cắt tháng thành 31 lệnh 1-ngày rồi thì màn hình máy gọi showBackfillProgress(0)
      ở ĐẦU MỖI LỆNH, nên bộ đếm trên máy **về 0 mỗi khi sang ngày mới**. Anh Thắng nhìn thấy
      "chạy được 10 lượt rồi tự quay lại từ đầu" — máy vẫn chạy đúng, chỉ là cái đếm nó reset.
      Thêm nữa, ngày nào đã đẩy rồi thì máy chủ trả "duplicate ignored" nên **không có gì mới hiện
      lên Sheet** — cũng dễ tưởng là treo. Nhìn số lệnh còn lại thì hết phải đoán.
   CHỈ ĐỌC. */
function getHangDoiTaiLai(pin, station){
  var u = loginByPin(pin);
  if (u.ok === false) return { ok:false, error:'Chưa đăng nhập.' };
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Thiếu tên cơ sở.' };
  if (!u.isAdmin && !u.all && !_canStation(u, station)) return { ok:false, error:'Không có quyền cơ sở này.' };
  var q = _fbGet('/queue/' + station);
  if (q === undefined) return { ok:false, error:'Không đọc được hàng đợi (kiểm FB_SECRET).' };
  /* 🔴 07/08/2026 — PHÂN LOẠI THEO `action`, KHÔNG THEO TIỀN TỐ KHOÁ.
     Bản cũ đếm `k.indexOf('op-zbf-') === 0`, mà lệnh tải lại MỘT ngày (`_raLenhBackfill`) mang
     khoá `op-<hex>` -> nó rơi vào `khac`, `conLai` = 0. Giao diện thấy `conLai=0` là vẽ ngay
     "✅ Máy đã chạy hết lệnh tải lại" — **một giây sau khi vừa xếp lệnh, máy còn chưa rút**.
     Đúng ca anh Thắng gặp: chọn 06/08, bấm Tải lại, màn hình báo xong liền, rồi con số hiện lên
     lại là lượt ngày 20/07 (lượt bù định kỳ của máy). Web nói dối hai lần trong cùng một dải.
     Tệ hơn: hàng đợi ĐƠ mà lệnh đầu là `op-<hex>` thì `conLai=0` -> nhánh "✅" chạy trước, nuốt
     luôn cảnh báo ĐƠ. Đây đúng bài học đã ghi ở `getQueueMay` mà quên áp sang đây. */
  var ngay = [], khac = 0;
  Object.keys(q || {}).forEach(function(k){
    var o = q[k] || {};
    if (String(o.action || '') === 'backfill') ngay.push(String(o.startTime || '').slice(0, 10));
    else khac++;
  });
  ngay.sort();
  /* 🔴 Dải tiến độ PHẢI phân biệt được "đang chạy" với "ĐƠ". Bản cũ chỉ đếm số lệnh còn lại nên
     hàng đợi nằm chết vẫn hiện "⏳ còn 3 ngày phải tải lại" — anh Thắng chờ, không có gì xảy ra.
     Lệnh đầu hàng chờ quá LENH_DO_PHUT là đơ: máy poll 10 giây/lần, và lệnh tải lại được xoá
     TRƯỚC khi chạy (.ino:1691) nên nó còn nằm đó = máy chưa hề rút. */
  var kDau = Object.keys(q || {}).sort()[0] || '';
  var phutDau = kDau ? _phutTuLuc(q[kDau] && q[kDau].createdAt) : -1;
  /* 🔴 07/08/2026 — ĐANG BÙ thì hàng đợi ĐỨNG IM LÀ ĐÚNG, không phải đơ.
     `backfillRange` là vòng CHẶN: suốt lúc chạy, `loop()` không quay nên máy không hỏi hàng đợi.
     Bản cũ chỉ nhìn số phút chờ nên kêu "ĐƠ" đúng lúc máy đang làm việc — anh Thắng đọc ra là
     "nghẽn", rồi đi xoá lệnh, mà xoá cũng không dừng được cái đang chạy. Nay phân biệt hẳn:
     máy báo `dangBu` -> ĐANG BẬN; chỉ khi KHÔNG bận mà lệnh đầu vẫn nằm quá lâu mới là ĐƠ.
     Firmware cũ không gửi `dangBu` (null) -> giữ nguyên cách cũ, đừng suy bừa là "rảnh". */
  var hb = _mayDangBu(station);
  var dangBu = hb.dangBu;
  return { ok:true, station:station, conLai:ngay.length, lenhKhac:khac,
           tongLenh:(ngay.length + khac),
           /* ⚠️ `q` là NULL khi hàng đợi rỗng (Firebase trả `null`, không phải `{}`). `q[kDau]`
              lúc đó ném TypeError và cả hàm chết — bộ `test_bfngay` bắt được đúng chỗ này. */
           dauHang:kDau, dauHangAction:String(((q && kDau && q[kDau]) || {}).action || ''),
           dauHangPhut:phutDau, doPhut:LENH_DO_PHUT,
           dangBu:dangBu, buDaDay:(hb ? hb.buDaDay : null), buNgay:(hb ? hb.buNgay : ''),
           nghiDo:(!dangBu && phutDau >= LENH_DO_PHUT),
           ngayDau:(ngay[0] || ''), ngayCuoi:(ngay[ngay.length - 1] || ''), ngay:ngay.slice(0, 40) };
}

var CMD_HAN_MS = 10 * 60 * 1000;   // lệnh tải lại quá 10 phút coi như hết hạn (backfill để lâu là vô nghĩa)
function _handleGetCmd(station) {
  // heartbeat: firmware HIỆN TẠI không gọi getCmd nữa (đọc lệnh qua Firebase, xem hbSend ghi /hb).
  // Vẫn giữ để bản firmware cũ còn chạy được.
  _touchStation(station);
  var out = { cmd: 'none' };
  if (station) {
    var lock = LockService.getScriptLock();
    try { lock.waitLock(10000); } catch (le) {}
    try {
      var props = PropertiesService.getScriptProperties();
      var key = 'cmd_' + station;
      var raw = props.getProperty(key);
      if (raw) {
        props.deleteProperty(key);                       // lấy 1 lần rồi bỏ, dù còn hạn hay không
        var c = null; try { c = JSON.parse(raw); } catch (pe) {}
        var at = c && Number(c.at) ? Number(c.at) : 0;
        // Không có mốc thời gian = lệnh ghi trước bản này -> coi như cũ, KHÔNG chạy.
        if (c && at && ((new Date()).getTime() - at) <= CMD_HAN_MS) out = c;
        else Logger.log('Bỏ lệnh cmd_' + station + ' đã hết hạn/không rõ mốc thời gian.');
      }
    } finally {
      lock.releaseLock();
    }
  }
  return ContentService.createTextOutput(JSON.stringify(out)).setMimeType(ContentService.MimeType.JSON);
}
// Dọn các lệnh cmd_<trạm> còn đọng (do đường getCmd không còn ai đọc). Chạy tay trong editor,
// hoặc gọi trong donRacDinhKy(). An toàn: lệnh backfill để lâu là vô nghĩa, bỏ không mất dữ liệu gì.
function donCmdCu(pin){
  _chiQuanTri('donCmdCu()', pin);
  var props = PropertiesService.getScriptProperties(), all = props.getProperties();
  var now = (new Date()).getTime(), xoa = [], giu = [];
  Object.keys(all).forEach(function(k){
    if (k.indexOf('cmd_') !== 0) return;
    var at = 0; try { at = Number(JSON.parse(all[k]).at) || 0; } catch (e) {}
    if (!at || (now - at) > CMD_HAN_MS) { props.deleteProperty(k); xoa.push(k); }
    else giu.push(k);
  });
  var kq = { xoa: xoa, giu: giu, soXoa: xoa.length };
  Logger.log(JSON.stringify(kq, null, 2));
  return kq;
}


// ===== TỰ ĐỘNG (trigger theo giờ): tạo cột ngày · 10h tải lại · cảnh báo chưa check-in =====
function _allStations(){
  return SpreadsheetApp.getActiveSpreadsheet().getSheets()
    .map(function(s){ return s.getName(); })
    .filter(function(n){ return n.indexOf('CS_') === 0; })
    .map(function(n){ return n.replace(/^CS_/, ''); });
}
function _findDateBlockCol(sheet, dateStr){   // như findOrCreateDateBlock nhưng CHỈ đọc (không tạo)
  // ⚠️ Phải quét MỌI khối tháng, không chỉ hàng 1. Chỉ quét hàng 1 là từ tháng 8 trở đi không
  //    bao giờ tìm thấy ngày nào -> cảnh báo "chưa ai check-in" bắn sai cho toàn bộ cơ sở.
  /* 🔴 07/08/2026 — ĐỌC HÀNG NGÀY THEO LÔ, không đọc từng ô.
     Bản cũ gọi `getRange(hdr, col).getValue()` trong vòng lặp: mỗi ô là MỘT vòng đi–về sang dịch
     vụ Sheets (~20–50ms). Sheet 31 ngày × 3 khối tháng = gần 100 lượt gọi chỉ để tìm một cột —
     mà chỉ cần MỘT `getValues()` cho cả hàng rồi dò trong bộ nhớ. Đo được: một lượt mở trang chấm
     công từ 165 lượt gọi dịch vụ xuống còn 25.
     ⚠️ Giữ NGUYÊN ngữ nghĩa: vẫn bước 5 cột, vẫn DỪNG ở ô trống đầu tiên, vẫn so cùng cách. */
  var lastCol = sheet.getLastColumn(), ds = _csKhoi(sheet);
  if (lastCol < 3) return -1;
  for (var k = 0; k < ds.length; k++){
    var hang = sheet.getRange(ds[k].hdr, 3, 1, lastCol - 2).getValues()[0];
    for (var i = 0; i < hang.length; i += 5){
      var hv = hang[i];
      if (hv === "" || hv === null) break;
      var hvStr = (hv instanceof Date) ? Utilities.formatDate(hv, TZ, "yyyy-MM-dd") : String(hv);
      if (hvStr === dateStr) return 3 + i;
    }
  }
  return -1;
}
// (3) Tạo sẵn cột NGÀY HÔM NAY cho mọi cơ sở (khỏi chờ check-in đầu tiên mới sinh cột)
function autoAddTodayBlocks(e){ _chiTriggerHoacChu('autoAddTodayBlocks()', e); return _capNhatCotNgay(); }
function _capNhatCotNgay(){
  var today = Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd');
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var log = [], errs = [];
  ss.getSheets().forEach(function(s){
    if (s.getName().indexOf('CS_') !== 0) return;
    try {
      var before = s.getLastColumn(), maxc = s.getMaxColumns();
      var _k = findOrCreateDateBlock(s, today);
      log.push(s.getName() + ':' + before + '->' + s.getLastColumn() + '(max ' + maxc + ',col ' + _k.col
               + ',khối hàng ' + _k.khoi.hdr + ')');
    } catch(e){ errs.push(s.getName() + ': ' + e); }
  });
  return { ok:true, date:today, log:log, errs:errs };
}
// (2) 10h: ép TẤT CẢ cơ sở tải lại dữ liệu HÔM NAY (kèm ảnh)
/* ===== TẢI LẠI TỰ ĐỘNG =====================================================================
 * 🔴 03/08/2026 — CHỖ HỞ THẬT, sửa sau khi soi ca FZ_SC_VIVO_T4.
 *    Bản cũ: MỘT lượt duy nhất lúc 10h sáng, và chỉ quét NGÀY HÔM NAY. Nghĩa là nó chỉ vớt được
 *    lượt quẹt TRƯỚC 10h. Chỗ nào chạy ca chiều tối (Funzone, VIVO…) thì cái lưới đó gần như
 *    không vớt được gì: máy mất mạng buổi chiều, hoặc firmware đọc sổ hỏng, là ngày đó MẤT TRẮNG
 *    mà không có gì tự lấy lại.
 *    Bằng chứng: `CS_FZ_SC_VIVO_T4` tháng 8 chỉ có 1 dòng (tháng 7 có 25 NV), và lượt duy nhất
 *    lọt về là 13:54 — tức sau 10h, nên KHÔNG do lượt tải lại tự động.
 *
 * Nay: 4 lượt/ngày. Lượt sáng quét CẢ HÔM QUA (vớt ca đêm + những gì hỏng qua đêm), 3 lượt còn
 * lại chỉ quét hôm nay cho rẻ.
 * ⚠️ Đẩy trùng là VÔ HẠI: `_ghiGioVaoRa` chỉ nới rộng khoảng [vào sớm nhất, ra muộn nhất], không
 *    bao giờ thu hẹp, và bản trùng bị bỏ qua. Nên chạy nhiều lượt không làm sai giờ.
 * ⚠️ Mỗi lượt = 1 lệnh/ngày/cửa hàng. Đừng nâng lên hàng chục lượt: máy 4G chạy từng lệnh một,
 *    xếp quá nhiều là hàng đợi dài ra rồi lệnh gấp (thêm/xoá NV) phải chờ.
 */
var BF_TU_DONG_GIO = [10, 14, 18, 22];   // giờ chạy; lượt ĐẦU quét kèm hôm qua

function autoBackfillAll(e){ _chiTriggerHoacChu('autoBackfillAll()', e); return _backfillTatCa(2); }
/** Lượt trong ngày: chỉ hôm nay, cho rẻ. */
function autoBackfillHomNay(e){ _chiTriggerHoacChu('autoBackfillHomNay()', e); return _backfillTatCa(1); }
/** soNgay = 1 -> chỉ hôm nay · 2 -> hôm qua + hôm nay. */
function _backfillTatCa(soNgay){
  soNgay = Math.max(1, Math.min(7, Number(soNgay) || 1));   // chặn trên: đừng bơm cả tuần lệnh
  var nay = new Date();
  var today = Utilities.formatDate(nay, TZ, 'yyyy-MM-dd');
  var dau = Utilities.formatDate(new Date(nay.getTime() - (soNgay - 1) * 86400000), TZ, 'yyyy-MM-dd');
  var start = dau + 'T00:00:00+07:00', end = today + 'T23:59:59+07:00';
  var n = 0, hong = 0;
  _allStations().forEach(function(st){
    try { _raLenhBackfill(st, start, end, true, ''); n++; } catch(e){ hong++; }
  });
  return { ok:true, count:n, hong:hong, soNgay:soNgay, tuNgay:dau, date:today };
}
// (4) Sau 10h: cơ sở nào CHƯA có ai check-in hôm nay -> lưu để dashboard cảnh báo (banner đỏ)
function warnNoCheckin(e){ _chiTriggerHoacChu('warnNoCheckin()', e); return _canhBaoChuaCheckin(); }
function _canhBaoChuaCheckin(){
  var today = Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd');
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var missing = [];
  ss.getSheets().forEach(function(s){
    var nm = s.getName(); if (nm.indexOf('CS_') !== 0) return;
    var station = nm.replace(/^CS_/, '');
    var col = _findDateBlockCol(s, today), hasCheckin = false;
    if (col > 0){
      var lastRow = s.getLastRow();
      if (lastRow >= 3){
        var vals = s.getRange(3, col, lastRow - 2, 1).getValues();   // cột Giờ vào của khối hôm nay
        for (var i = 0; i < vals.length; i++){ if (String(vals[i][0]).trim() !== ''){ hasCheckin = true; break; } }
      }
    }
    if (!hasCheckin) missing.push(station);
  });
  PropertiesService.getScriptProperties().setProperty('warn_nocheckin', JSON.stringify({
    date: today, stations: missing, at: Utilities.formatDate(new Date(), TZ, 'HH:mm')
  }));
  return missing;
}
// Dashboard gọi để lấy cảnh báo (lọc theo cơ sở người dùng được xem)
function getNoCheckinWarning(pin){
  var raw = PropertiesService.getScriptProperties().getProperty('warn_nocheckin');
  if (!raw) return { stations:[] };
  var w; try { w = JSON.parse(raw); } catch(e){ return { stations:[] }; }
  var today = Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd');
  if (w.date !== today) return { stations:[], date:w.date };   // cảnh báo hôm khác -> bỏ qua
  var u = loginByPin(pin);           // ⚠️ BẮT BUỘC: trước đây không pin là thấy cảnh báo của MỌI cơ sở
  if (u.ok === false) return { stations:[] };
  var list = w.stations || [];
  if (!u.isAdmin && !u.all) list = list.filter(function(st){ return _canStation(u, st); });
  return { date:w.date, at:w.at, stations:list };
}
// Chạy 1 LẦN để tạo 3 trigger theo giờ (chạy từ editor để cấp quyền ScriptApp, hoặc ?action=setupTriggers&token=..)
function setupAutoTriggers(pin){ _chiQuanTri('setupAutoTriggers()', pin); return _taoTrigger(); }
function _taoTrigger(){
  /* ⚠️ `fns` phải kể ĐỦ tên hàm cần dọn, kể cả tên MỚI. Thiếu một tên là chạy lại hàm này sẽ để
     lại trigger cũ rồi tạo thêm cái mới -> mỗi lần bấm "tạo lại trigger" là nhân đôi số lượt
     tải lại, hàng đợi phình ra mà không ai hiểu vì sao. */
  var fns = ['autoAddTodayBlocks','autoBackfillAll','autoBackfillHomNay','warnNoCheckin'];
  ScriptApp.getProjectTriggers().forEach(function(t){ if (fns.indexOf(t.getHandlerFunction()) >= 0) ScriptApp.deleteTrigger(t); });
  ScriptApp.newTrigger('autoAddTodayBlocks').timeBased().atHour(7).everyDays(1).create();
  // Lượt ĐẦU (BF_TU_DONG_GIO[0]) quét kèm HÔM QUA; các lượt sau chỉ hôm nay.
  ScriptApp.newTrigger('autoBackfillAll').timeBased().atHour(BF_TU_DONG_GIO[0]).everyDays(1).create();
  for (var i = 1; i < BF_TU_DONG_GIO.length; i++)
    ScriptApp.newTrigger('autoBackfillHomNay').timeBased().atHour(BF_TU_DONG_GIO[i]).everyDays(1).create();
  ScriptApp.newTrigger('warnNoCheckin').timeBased().atHour(11).everyDays(1).create();
  return { ok:true, msg:'Đã tạo trigger: 7h tạo cột ngày · tải lại lúc '
           + BF_TU_DONG_GIO.join('h, ') + 'h (lượt ' + BF_TU_DONG_GIO[0]
           + 'h quét kèm hôm qua) · 11h cảnh báo chưa check-in.' };
}

/*
 * ĐỊNH DẠNG NGANG:  A = Họ và Tên, B = ID (mỗi NV 1 hàng)
 *   Từ cột C: mỗi NGÀY là 1 khối 5 cột [Giờ vào][Ảnh vào][Giờ ra][Ảnh ra][Chuẩn]
 */
function doPost(e) {
  var lock = LockService.getScriptLock();
  try { lock.waitLock(20000); } catch (le) {}
  try {
    var data = JSON.parse(e.postData.contents);
    var empNo = data.employeeNo;
    var name = data.name;
    var eventTime = data.time;
    var base64Image = data.image;

    // Cửa hàng lấy theo MÃ THIẾT BỊ (serial đầu đọc -> MAC bo), KHÔNG tin tên máy tự khai.
    var _gm = _giaiMaTram(data.hikSerial, data.macAddress, data.stationName, data.hikModel);
    var stationName = _gm.station;
    if (_gm.choGan){
      // Máy chưa gán cửa hàng: gửi tạm, TUYỆT ĐỐI không tạo sheet cửa hàng mới từ lời khai của máy.
      // Vẫn trả SUCCESS vì firmware coi khác SUCCESS là thất bại rồi đẩy lại mãi (dòng 483 .ino).
      var _luu = _luuChoGan(data.hikSerial, data.macAddress, data.stationName, empNo, name, eventTime, !!base64Image);
      return ContentService.createTextOutput(JSON.stringify({ status: 'SUCCESS', choGan: true, luu: _luu,
        note: 'May chua gan cua hang — da gui tam vao ' + SH_CHOGAN + ', vao web app gan cua hang cho may nay.'
      })).setMimeType(ContentService.MimeType.JSON);
    }
    if (_gm.lech) _fbGhiLoi('MAY_KHAI_LECH', 'máy khai "' + data.stationName + '" nhưng bảng ' + SH_MAY + ' gán "' + stationName + '" -> dùng bảng');
    _touchStation(stationName);   // heartbeat: có lượt đẩy chấm công -> máy đang online
    // TỰ CHỮA: máy đã được gán TRƯỚC khi có tính năng này thì không ai bấm Gán lại nữa,
    // nên công bố ngay tại đây. Máy khai lệch tên (_gm.lech) chính là ca cần chữa nhất.
    // ⚠️ Đây là ĐƯỜNG NÓNG (mỗi lượt chấm công) -> dồn 1 giờ/máy, đừng gọi Firebase mỗi lượt.
    if (_gm.lech || String(data.stationName || '') !== stationName) _fbCongBoMayThrottle(data.macAddress, stationName);

    /* ===========================================================================
     *  🔴 CHẶN GÓI KHÔNG PHẢI CHẤM CÔNG — 04/08/2026
     * ---------------------------------------------------------------------------
     *  Anh Thắng: *"khi rút điện ra gắn lại, nó tạo ra lệnh test"*, và sheet cơ sở mọc ra một
     *  KHỐI THÁNG tên **"test"** với dòng `AT-HTTP test / TEST4G`.
     *  Nguồn: firmware, mỗi lần 4G kết nối lại (tức MỖI LẦN BẬT MÁY) đẩy một gói thử đường
     *  4G→Google với `employeeNo:"TEST4G"`, `name:"AT-HTTP test"`, `time:"test"` — đẩy vào
     *  ĐÚNG đường ghi chấm công. `"test".split(" ")` ra `dateStr = "test"`, và
     *  `findOrCreateDateBlock(sheet, "test")` tạo thật một khối tên "test" trong sheet TIỀN LƯƠNG.
     *
     *  Chặn ở ĐÂY, không chỉ ở firmware, vì:
     *    · sửa firmware phải OTA từng máy — mà mọi máy đang chạy (kể cả 01c) đều đẩy gói này;
     *    · máy chủ deploy một lần là mọi máy sạch ngay.
     *  Trả `SUCCESS` (không phải lỗi) vì firmware coi khác SUCCESS là thất bại rồi ĐẨY LẠI MÃI
     *  (xem .ino dòng 483) — gói rác thì phải BỎ, không phải bắt nó thử lại vô hạn.
     *
     *  ⚠️ Kiểm KHUÔN NGÀY GIỜ, không chỉ chặn đúng chữ "TEST4G": chặn theo tên là lần sau ai đổi
     *     chữ trong gói thử là lọt tiếp. Chỉ nhận đúng 'yyyy-MM-dd HH:mm:ss'.
     * =========================================================================== */
    if (data.selftest === true || String(empNo || '').toUpperCase() === 'TEST4G') {
      _fbGhiLoi('GOI_THU_DUONG', 'máy ' + stationName + ' đẩy gói thử đường truyền -> bỏ qua, KHÔNG ghi sheet');
      return ContentService.createTextOutput(JSON.stringify({ status:'SUCCESS', boQua:true,
        note:'Goi THU DUONG TRUYEN — duong 4G/WiFi -> Google CHAY. Khong ghi vao sheet cham cong.'
      })).setMimeType(ContentService.MimeType.JSON);
    }
    var parts = String(eventTime == null ? '' : eventTime).trim().split(" ");
    var dateStr = parts[0];
    var timeStr = parts[1];
    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(dateStr)) || !/^\d{2}:\d{2}(:\d{2})?$/.test(String(timeStr || ''))) {
      _fbGhiLoi('GIO_SAI_KHUON', 'máy ' + stationName + ' gửi time="' + eventTime + '" (NV ' + empNo
              + ') -> bỏ qua, KHÔNG tạo khối lạ trong sheet');
      return ContentService.createTextOutput(JSON.stringify({ status:'SUCCESS', boQua:true,
        note:'time="' + eventTime + '" khong dung khuon yyyy-MM-dd HH:mm:ss -> bo qua.'
      })).setMimeType(ContentService.MimeType.JSON);
    }

    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName("CS_" + stationName) || _taoSheetCuaHang(stationName);

    var _kb      = findOrCreateDateBlock(sheet, dateStr);
    var blockCol = _kb.col;
    var empRow   = findOrCreateEmpRow(sheet, _kb.khoi, empNo, name);

    var inTimeCell  = sheet.getRange(empRow, blockCol);
    var inImgCell   = sheet.getRange(empRow, blockCol + 1);
    var outTimeCell = sheet.getRange(empRow, blockCol + 2);
    var outImgCell  = sheet.getRange(empRow, blockCol + 3);
    var stdCell     = sheet.getRange(empRow, blockCol + 4);
    sheet.setRowHeight(empRow, 62);

    var curInVal  = inTimeCell.getValue();
    var curOutVal = outTimeCell.getValue();

    if (toTimeStr(curInVal) === timeStr || toTimeStr(curOutVal) === timeStr) {
      _bfDem(stationName, 'trung', dateStr + ' ' + timeStr);
      return ContentService.createTextOutput(JSON.stringify({ status: "SUCCESS", note: "duplicate ignored" }))
                           .setMimeType(ContentService.MimeType.JSON);
    }

    var imageFormula = "";
    var imgNote = "no-img";
    if (base64Image && base64Image.length > 100) {
      try {
        var d0 = new Date(dateStr);
        var monthYearStr = "Tháng " + ("0" + (d0.getMonth() + 1)).slice(-2) + "-" + d0.getFullYear();
        var rootFolder = DriveApp.getFolderById(ATT_FOLDER_ID);   // ảnh chấm công -> thư mục riêng
        var sf = rootFolder.getFoldersByName(stationName);
        var stationFolder = sf.hasNext() ? sf.next() : rootFolder.createFolder(stationName);
        var mf = stationFolder.getFoldersByName(monthYearStr);
        var monthFolder = mf.hasNext() ? mf.next() : stationFolder.createFolder(monthYearStr);
        var blob = Utilities.newBlob(Utilities.base64Decode(base64Image), "image/jpeg",
                     empNo + "_" + dateStr + "_" + timeStr.replace(/:/g, "-") + ".jpg");
        var file = monthFolder.createFile(blob);
        imgNote = "ok:" + file.getId();
        imageFormula = '=IMAGE("https://drive.google.com/thumbnail?id=' + file.getId() + '&sz=w300")';
        try { file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW); } catch (se) {}
      } catch (de) {
        // Lưu ảnh lỗi (hay nhất là SAI QUYỀN thư mục mới) -> vẫn ghi GIỜ, chỉ mất ảnh.
        // Trước đây lỗi này im lặng hoàn toàn; nay ghi lại để kiemTraFolderAnhChamCong() đọc ra.
        imageFormula = ""; imgNote = "ERR:" + de;
        try {
          console.error('Lưu ảnh chấm công LỖI (' + stationName + ' ' + dateStr + '): ' + de);
          PropertiesService.getScriptProperties().setProperty('last_img_err',
            _now() + ' | ' + stationName + ' | ' + dateStr + ' | ' + de);
        } catch (_e) {}
      }
    }

    var _kq = _ghiGioVaoRa(sheet, empRow, blockCol, timeStr, imageFormula);
    _bfDem(stationName, (_kq && _kq.loai) || 'moi', dateStr + ' ' + timeStr);

    return ContentService.createTextOutput(JSON.stringify({ status: "SUCCESS", img: imgNote })).setMimeType(ContentService.MimeType.JSON);
  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({ status: "ERROR", message: String(error) })).setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}

/* ===================== KHỐI THÁNG TRONG SHEET CỬA HÀNG =====================
 * Yêu cầu của anh Thắng (01/08/2026): *"tháng 7 vẫn giữ nguyên, tháng 8 sẽ nằm DƯỚI tháng 7,
 * chứ không đi tiếp sau ngày 31"* — mỗi tháng một KHỐI xếp dọc, dùng lại cùng cột ngày, cách
 * nhau 2 hàng trống. Sheet dài xuống thay vì dài ngang, dễ nhìn và dễ theo dõi.
 *
 * Một KHỐI gồm:
 *      hàng hdr   : ngày (mỗi ngày gộp 5 cột, bắt đầu từ cột C)
 *      hàng sub   : "Giờ Vào / Checkin" · "Ảnh Checkin" · ... (5 cột mỗi ngày)
 *      hàng r1..r2: nhân viên (cột A tên, cột B mã)
 *
 * ⚠️ CÁCH NHẬN RA KHỐI: cột C của hàng `sub` LUÔN là chuỗi "Giờ Vào / Checkin", vì mọi khối đều
 *    bắt đầu ngày đầu tiên ở cột C. Không nhớ vị trí khối ở đâu khác (Script Property, ghi chú ô)
 *    — nhớ ở chỗ khác là sớm muộn lệch với sheet thật, và lệch thì hỏng IM LẶNG.
 *
 * ⚠️ TƯƠNG THÍCH NGƯỢC: sheet cũ có ĐÚNG MỘT khối ở hàng 1-2, nên hàm này trả về 1 khối và mọi
 *    thứ chạy y như trước. KHÔNG dàn lại dữ liệu cũ — đó là chấm công thật của tháng 7.
 */
var CS_SUB = ["Giờ Vào / Checkin", "Ảnh Checkin", "Giờ Ra / CheckOut", "Ảnh Checkout", "Thời gian trong ngày"];
var CS_CACH_HANG = 2;      // số hàng TRỐNG giữa hai khối tháng

/** Mọi khối tháng của sheet, theo thứ tự từ trên xuống. [{hdr, sub, r1, r2}] */
function _csKhoi(sheet){
  var lr = sheet.getLastRow();
  if (lr < 2 || sheet.getLastColumn() < 3) return [];
  var colC = sheet.getRange(1, 3, lr, 1).getDisplayValues();
  var ds = [];
  for (var i = 0; i < colC.length; i++){
    if (String(colC[i][0]).trim() === CS_SUB[0]) ds.push({ sub: i + 1 });   // getDisplayValues 0-based
  }
  // ⚠️ PHẢI hai lượt. Gộp một lượt là lúc tính r2 của khối k thì hdr của khối k+1 CHƯA gán ->
  //    undefined - 1 - 2 = NaN, mà `NaN < x` luôn false nên cái chốt bên dưới không cứu được.
  //    Hậu quả: mọi lượt chấm công ghi vào THÁNG CŨ đều nhảy sai hàng. Test bắt được lỗi này.
  for (var k = 0; k < ds.length; k++){
    ds[k].hdr = ds[k].sub - 1;
    ds[k].r1  = ds[k].sub + 1;
  }
  for (var k2 = 0; k2 < ds.length; k2++){
    // Khối cuối chạy tới hết sheet; khối giữa dừng trước 2 hàng trống của khối sau.
    ds[k2].r2 = (k2 + 1 < ds.length) ? (ds[k2 + 1].hdr - 1 - CS_CACH_HANG) : lr;
    if (!(ds[k2].r2 >= ds[k2].r1 - 1)) ds[k2].r2 = ds[k2].r1 - 1;   // chưa có nhân viên / số lạ
  }
  return ds;
}

/** Tháng của một khối, dạng 'yyyy-MM'. Đọc từ hàng ngày của CHÍNH khối đó. */
function _csThangKhoi(sheet, k){
  var lc = sheet.getLastColumn();
  if (lc < 3) return '';
  var hv = sheet.getRange(k.hdr, 3, 1, lc - 2).getValues()[0];
  for (var i = 0; i < hv.length; i++){
    var v = hv[i];
    if (v === '' || v == null) continue;
    var ds = (v instanceof Date) ? Utilities.formatDate(v, TZ, 'yyyy-MM-dd') : String(v).trim();
    if (/^\d{4}-\d{2}/.test(ds)) return ds.substring(0, 7);
  }
  return '';
}

/** Tạo khối tháng MỚI ở dưới cùng, cách khối trước CS_CACH_HANG hàng trống. */
function _csTaoKhoi(sheet){
  var daCo = _csKhoi(sheet).length;
  var hdr  = daCo ? (sheet.getLastRow() + CS_CACH_HANG + 1) : 1;
  var can  = hdr + 2;                                     // cần tới hàng nhân viên đầu tiên
  if (sheet.getMaxRows() < can) sheet.insertRowsAfter(sheet.getMaxRows(), can - sheet.getMaxRows());
  // Nhãn A/B giống khối đầu (xem _taoSheetCuaHang) để khối mới trông y khối cũ.
  sheet.getRange(hdr, 1, 2, 1).merge().setValue('Họ và Tên');
  sheet.getRange(hdr, 2, 2, 1).merge().setValue('ID');
  sheet.getRange(hdr, 1, 2, 2).setBackground('#0f172a').setFontColor('#38bdf8')
       .setFontWeight('bold').setVerticalAlignment('middle');
  return { hdr: hdr, sub: hdr + 1, r1: hdr + 2, r2: hdr + 1 };
}

/** Khối tháng ứng với `dateStr` (yyyy-MM-dd). Chưa có thì TẠO MỚI ở dưới. */
function _csKhoiChoNgay(sheet, dateStr){
  var thang = String(dateStr || '').substring(0, 7);
  var ds = _csKhoi(sheet);
  for (var i = 0; i < ds.length; i++){
    if (_csThangKhoi(sheet, ds[i]) === thang) return ds[i];
  }
  /* 🔴 06/08/2026 — DÙNG LẠI KHỐI CHƯA CÓ CỘT NGÀY, ĐỪNG ĐẺ KHỐI MỚI.
     `_csTaoKhoi` chỉ dựng hai nhãn "Họ và Tên | ID", KHÔNG ghi cột ngày. Nên `_csThangKhoi` của
     khối vừa tạo trả '' — lần lưu sau không nhận ra nó, lại tạo thêm một khối nữa. Cứ mỗi lần
     lưu hồ sơ là một khối một dòng: sheet `CS_` phình ra, mỗi người nằm một khối riêng, và mọi
     phép đếm/đối chiếu theo khối đều sai. Đúng cái anh Thắng nói: *"lấy đủ qua sheet CS thì dẫn
     đến bị trùng lặp và báo sai"*.
     Khối chưa có cột ngày = chưa ai chấm công trong đó = chưa thuộc về tháng nào -> nhận làm
     khối của tháng này. Lấy khối CUỐI CÙNG (mới nhất) nếu lỡ có nhiều cái.
     ⚠️ CHỈ nhận khối RỖNG NGÀY. Khối đã có ngày của tháng khác thì tuyệt đối không đụng —
        ghi vào đó là giờ chấm công của tháng này rơi vào bảng tháng trước. */
  for (var j = ds.length - 1; j >= 0; j--){
    if (_csThangKhoi(sheet, ds[j]) === '') return ds[j];
  }
  return _csTaoKhoi(sheet);
}

/* ===========================================================================
 *  DỌN "KHỐI TEST" CÒN SÓT TRONG SHEET CƠ SỞ — 07/08/2026
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"loại bỏ khối test khi thêm cơ sở mới (nếu có nó có ảnh hưởng
 *  hàng nhân viên thêm không)"*.
 *
 *  CÓ, nó ảnh hưởng thật — và đây là lý do phải dọn bằng MÁY chứ đừng xoá tay:
 *    · Gói thử đường truyền (`time:"test"`) đẻ ra một CỘT NGÀY nhãn `test` và hàng
 *      `AT-HTTP test / TEST4G`. Nhãn `test` không khớp `yyyy-MM`, nên `_csThangKhoi`
 *      trả '' -> `_csKhoiChoNgay` coi khối đó là "khối chưa thuộc tháng nào" và
 *      **DÙNG LẠI NÓ** cho tháng kế tiếp.
 *    · `_ganDongCuaHang` (thêm nhân viên từ web) cũng gọi `_csKhoiChoNgay(homNay)`.
 *      Nên hàng nhân viên anh thêm vào **nằm ngay trong khối `test` đó**.
 *  Tức là khối `test` và hàng nhân viên mới là MỘT KHỐI. Xoá cả khối = xoá luôn
 *  người thật. Nên hàm này KHÔNG xoá khối, chỉ làm hai việc hẹp:
 *
 *    (1) xoá đúng những HÀNG do gói thử đẻ ra (mã `TEST4G` / tên `AT-HTTP test`);
 *    (2) xoá NỘI DUNG nhóm 5 cột có nhãn rác — nhưng CHỈ khi bên phải nó không còn
 *        cột ngày thật nào.
 *
 *  🔴 Vì sao (2) phải có điều kiện "bên phải không còn ngày thật":
 *     `findOrCreateDateBlock` quét cột 3, 8, 13… và **DỪNG Ở NHÓM TRỐNG ĐẦU TIÊN**.
 *     Xoá nhãn ở cột 3 trong khi cột 8 đang giữ ngày thật là mọi ngày từ cột 8 trở đi
 *     trở thành VÔ HÌNH: lượt chấm công hôm sau ghi đè lên cột 3, rồi ngày cũ bị tạo
 *     lại thành cột trùng — công của cả khối tách làm đôi. Nhóm rác nằm ở CUỐI thì
 *     xoá vô hại: khối thành "chưa có ngày nào", ngày thật đầu tiên sẽ vào đúng chỗ đó.
 *  🔴 KHÔNG xoá CỘT (deleteColumns): lưới cột 3/8/13… là CHUNG cho MỌI khối trong
 *     sheet. Xoá 5 cột là mọi khối tháng khác cụt mất ngày đầu tiên.
 *  🔴 KHÔNG đụng hàng phụ (`khoi.sub`): `_csKhoi` nhận ra khối bằng ô cột C của hàng
 *     đó. Xoá nó là cả khối biến mất khỏi mắt app — sheet còn nguyên mà app mù.
 * =========================================================================== */

/** Các nhóm cột NGÀY đang SỐNG của một khối. Quét đúng luật của `findOrCreateDateBlock`
 *  (bước 5, dừng ở nhóm trống đầu tiên) — quét khác luật là báo cáo một đằng, app đọc một nẻo. */
function _csNhomCot(sheet, k){
  var out = [], lc = sheet.getLastColumn();
  if (lc < 3) return out;
  var hv = sheet.getRange(k.hdr, 1, 1, lc).getValues()[0];
  for (var col = 3; col <= lc; col += 5){
    var v = hv[col - 1];
    if (v === '' || v === null) break;
    var nhan = (v instanceof Date) ? Utilities.formatDate(v, TZ, 'yyyy-MM-dd') : String(v).trim();
    out.push({ col:col, nhan:nhan, laNgay: /^\d{4}-\d{2}-\d{2}$/.test(nhan) });
  }
  return out;
}

/** Hàng này do GÓI THỬ ĐƯỜNG TRUYỀN đẻ ra? Nhận cả hai dấu vết vì firmware từng đổi nội dung gói. */
function _csHangGoiThu(ten, ma){
  var m = String(ma == null ? '' : ma).trim().toUpperCase();
  var t = String(ten == null ? '' : ten).trim().toLowerCase();
  return m === 'TEST4G' || t === 'at-http test';
}

/**
 * Soát (và dọn, nếu `chayThat`) rác gói thử trong MỘT sheet `CS_`.
 * ⚠️ Đi TỪ KHỐI CUỐI LÊN và trong mỗi khối xoá TỪ HÀNG DƯỚI LÊN — xoá từ trên xuống là
 *    mọi chỉ số bên dưới tụt một hàng, lần xoá kế tiếp trúng người khác.
 */
function _csSoatKhoiTest(sheet, chayThat){
  var bao = { sheet:sheet.getName(), hang:[], cot:[], daXoaHang:0, daXoaCot:0 };
  var ds = _csKhoi(sheet);
  for (var i = ds.length - 1; i >= 0; i--){
    var k = ds[i], cot = _csNhomCot(sheet, k);
    var iNgayCuoi = -1;
    for (var c = 0; c < cot.length; c++) if (cot[c].laNgay) iNgayCuoi = c;

    // ---- (1) HÀNG do gói thử đẻ ra ----
    var soXoa = 0;
    if (k.r2 >= k.r1){
      var v = sheet.getRange(k.r1, 1, k.r2 - k.r1 + 1, 2).getValues();
      for (var r = v.length - 1; r >= 0; r--){
        if (!_csHangGoiThu(v[r][0], v[r][1])) continue;
        var hang = k.r1 + r, coGio = false;
        /* Trùng tên với gói thử mà lại CÓ GIỜ trong một cột ngày thật thì đó không phải rác —
           giữ lại và báo, đừng xoá. Xoá một hàng chấm công là xoá tiền của người ta. */
        for (var c2 = 0; c2 <= iNgayCuoi && !coGio; c2++){
          if (!cot[c2].laNgay) continue;
          var o = sheet.getRange(hang, cot[c2].col, 1, 5).getValues()[0];
          for (var q = 0; q < o.length; q++) if (String(o[q] == null ? '' : o[q]).trim() !== ''){ coGio = true; break; }
        }
        bao.hang.push({ khoi:i + 1, hang:hang, ten:String(v[r][0] || ''),
                        ma:String(v[r][1] || ''), giuLai:coGio });
        if (coGio) continue;
        if (chayThat){ sheet.deleteRow(hang); soXoa++; bao.daXoaHang++; }
      }
    }
    var r2 = k.r2 - soXoa;

    // ---- (2) NHÓM CỘT có nhãn RÁC ----
    for (var c3 = cot.length - 1; c3 >= 0; c3--){
      if (cot[c3].laNgay) continue;
      var xoaDuoc = (c3 > iNgayCuoi);          // bên phải không còn ngày thật nào
      bao.cot.push({ khoi:i + 1, col:cot[c3].col, nhan:cot[c3].nhan, xoaDuoc:xoaDuoc });
      if (!xoaDuoc || !chayThat) continue;
      sheet.getRange(k.hdr, cot[c3].col, 1, 5).clearContent().setBackground(null);
      if (r2 >= k.r1) sheet.getRange(k.r1, cot[c3].col, r2 - k.r1 + 1, 5).clearContent();
      bao.daXoaCot++;
    }
  }
  return bao;
}

function _khoiTestGac(pin, coSo, chayThat){
  var u = _requireAuth(pin);
  if (chayThat ? !_canQuanTriNV(u) : !_canSuaHoSo(u))
    return { ok:false, error: chayThat ? ('Dọn sheet chấm công đụng vào bảng tiền lương — ' + _QT_LOI)
                                       : 'Không có quyền xem.' };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!coSo) return { ok:false, error:'Thiếu cơ sở.' };
  if (!u.isAdmin && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cơ sở này.' };
  var sh = _sheet('CS_' + coSo);
  if (!sh) return { ok:false, error:'Chưa có sheet CS_' + coSo + '.', chuaCoSheet:true };
  return { sh:sh, coSo:coSo };
}

/** CHỈ XEM — bày ra đúng những gì sẽ bị đụng tới, không sửa gì. */
function xemKhoiTest(pin, coSo){
  var g = _khoiTestGac(pin, coSo, false);
  if (g.ok === false) return g;
  var b = _csSoatKhoiTest(g.sh, false);
  return { ok:true, coSo:g.coSo, hang:b.hang, cot:b.cot,
           coViec: !!(b.hang.filter(function(x){ return !x.giuLai; }).length
                      || b.cot.filter(function(x){ return x.xoaDuoc; }).length) };
}

/** DỌN THẬT. Không lùi được -> giao diện phải hỏi lại trước khi gọi. */
function donKhoiTest(pin, coSo){
  var g = _khoiTestGac(pin, coSo, true);
  if (g.ok === false) return g;
  var lock = LockService.getScriptLock(); try { lock.waitLock(15000); } catch(e){}
  try {
    var b = _csSoatKhoiTest(g.sh, true);
    return { ok:true, coSo:g.coSo, xoaHang:b.daXoaHang, xoaCot:b.daXoaCot,
             conLai: b.cot.filter(function(x){ return !x.xoaDuoc; })
                      .concat(b.hang.filter(function(x){ return x.giuLai; })) };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

/* ===== GHI GIỜ VÀO / GIỜ RA — MỘT ĐƯỜNG DUY NHẤT =====
 * Tách ra từ doPost (01/08/2026) để đường CHẤM CÔNG ONLINE dùng chung. ⚠️ TUYỆT ĐỐI không chép
 * logic này thành bản thứ hai: hai bản lệch nhau là hai kiểu tính giờ trên cùng một sheet, mà
 * lệch giờ là lệch TIỀN LƯƠNG. Toàn bộ test cũ của doPost đang canh đúng hàm này.
 *
 * Quy tắc (giữ NGUYÊN như cũ, không đổi một dấu chấm):
 *   · ô Giờ vào còn trống          -> ghi VÀO
 *   · đã có Giờ vào, lượt mới >= vào -> ghi RA
 *   · đã có Giờ vào, lượt mới <  vào -> lượt mới thành VÀO, giờ vào cũ đẩy xuống RA (quẹt lộn thứ tự)
 * Trả { loai:'vao'|'ra'|'daoThuTu' } để chỗ gọi báo lại cho người dùng.
 */
function _ghiGioVaoRa(sheet, empRow, blockCol, timeStr, imageFormula) {
  var inTimeCell  = sheet.getRange(empRow, blockCol);
  var inImgCell   = sheet.getRange(empRow, blockCol + 1);
  var outTimeCell = sheet.getRange(empRow, blockCol + 2);
  var outImgCell  = sheet.getRange(empRow, blockCol + 3);
  var stdCell     = sheet.getRange(empRow, blockCol + 4);
  var curInVal    = inTimeCell.getValue();
  var curOutVal   = outTimeCell.getValue();

  /* ⚠️ 02/08/2026 — QUY TẮC PHẢI KHÔNG PHỤ THUỘC THỨ TỰ VÀ CHẠY LẠI ĐƯỢC.
     Ô Giờ vào / Giờ ra là CẶP [sớm nhất, muộn nhất] của ngày. Trước đây bất kỳ lượt nào ≥ giờ vào
     cũng GHI ĐÈ giờ ra, kể cả khi nó SỚM HƠN giờ ra đang có. Ngày thường thì không sao vì lượt tới
     theo thứ tự tăng dần, nhưng lúc TẢI LẠI cả tháng thì hỏng thật:

        ngày có 3 lượt 08:00 · 12:00 · 17:00 -> sheet đang đúng (vào 08:00, ra 17:00)
        tải lại: 08:00 bị bỏ (trùng) · 12:00 GHI ĐÈ ra = 12:00  <-- 17:00 MẤT
                 17:00 ghi lại ra = 17:00 -> đúng lại

     Tức là giữa chừng số liệu SAI, và nếu máy khởi động lại đúng lúc đó (đúng ca anh Thắng gặp:
     "chạy được 10 lượt thì tự quay lại từ đầu") thì ngày đó **đứng luôn ở giờ ra 12:00**.
     Nay chỉ nới rộng khoảng, không bao giờ thu hẹp -> chạy lại bao nhiêu lần, theo thứ tự nào,
     đứt ở đâu cũng ra cùng một kết quả. */
  var curInStr0  = (curInVal  === "" || curInVal  === null) ? "" : toTimeStr(curInVal);
  var curOutStr0 = (curOutVal === "" || curOutVal === null) ? "" : toTimeStr(curOutVal);
  if (timeStr === curInStr0 || timeStr === curOutStr0) return { loai: 'trung' };   // đã có -> bỏ qua

  if (curInStr0 === "") {
    inTimeCell.setNumberFormat("@").setValue(timeStr);
    if (imageFormula) inImgCell.setFormula(imageFormula);
    stdCell.setNumberFormat("@").setValue(hhmm(timeStr));
    return { loai: 'vao' };
  }
  var curInStr = curInStr0;
  if (secOf(timeStr) >= secOf(curInStr)) {
    // Lượt nằm GIỮA giờ vào và giờ ra -> khoảng đã phủ rồi, không đụng vào (không thu hẹp).
    if (curOutStr0 !== "" && secOf(timeStr) < secOf(curOutStr0)) return { loai: 'giua' };
    outTimeCell.setNumberFormat("@").setValue(timeStr);
    if (imageFormula) outImgCell.setFormula(imageFormula);
    stdCell.setNumberFormat("@").setValue(hhmm(curInStr) + " " + hhmm(timeStr));
    return { loai: 'ra' };
  }
  /* Lượt SỚM hơn giờ vào -> nó thành giờ vào mới.
     ⚠️ Giờ ra CHỈ nhận giờ-vào-cũ khi ô giờ ra còn TRỐNG. Trước đây ghi đè vô điều kiện, nên lượt
        sớm nhất mà tới SAU CÙNG (rất hay gặp khi tải lại: đầu đọc trả trang không theo thứ tự) là
        xoá mất giờ ra thật:
              10:00 -> vào 10:00 · 14:20 -> ra 14:20 · 22:05 -> ra 22:05
              06:30 -> vào 06:30, ra bị đè thành 10:00   <-- MẤT 22:05
        Giờ ra luôn ≥ giờ vào cũ, nên khi đã có giờ ra thì GIỮ NGUYÊN mới đúng "muộn nhất trong
        ngày"; giờ-vào-cũ lúc này chỉ là một lượt ở giữa. Test 24 hoán vị bắt đúng ca này. */
  var oldImg = inImgCell.getFormula();
  inTimeCell.setNumberFormat("@").setValue(timeStr);
  if (imageFormula) inImgCell.setFormula(imageFormula);
  var raMoi = curInStr;
  if (curOutStr0 === "") {
    outTimeCell.setNumberFormat("@").setValue(curInStr);
    if (oldImg) outImgCell.setFormula(oldImg);
  } else raMoi = curOutStr0;                       // đã có giờ ra muộn hơn -> không đụng vào
  stdCell.setNumberFormat("@").setValue(hhmm(timeStr) + " " + hhmm(raMoi));
  return { loai: 'daoThuTu' };
}

function findOrCreateDateBlock(sheet, dateStr) {
  // Trả { col, khoi }. `col` là cột "Giờ vào" của ngày đó TRONG KHỐI THÁNG của nó.
  // ⚠️ Ngày của tháng mới KHÔNG còn mọc tiếp sang phải nữa — nó xuống khối mới ở dưới.
  var khoi = _csKhoiChoNgay(sheet, dateStr);
  var lastCol = sheet.getLastColumn();
  var blockCount = 0;
  for (var col = 3; col <= lastCol; col += 5) {
    var hv = sheet.getRange(khoi.hdr, col).getValue();
    if (hv === "" || hv === null) break;
    var hvStr = (hv instanceof Date) ? Utilities.formatDate(hv, TZ, "yyyy-MM-dd") : String(hv);
    if (hvStr === dateStr) return { col: col, khoi: khoi };
    blockCount++;
  }
  var newCol = 3 + blockCount * 5;
  // Sheet hết cột -> CHÈN THÊM cột (sửa lỗi "range out of bounds" làm mất dữ liệu ngày mới)
  var need = newCol + 4, maxc = sheet.getMaxColumns();
  if (maxc < need) sheet.insertColumnsAfter(maxc, need - maxc);
  var color = (blockCount % 2 === 0) ? "#fff176" : "#a5d6a7";
  sheet.getRange(khoi.hdr, newCol, 1, 5).merge()
       .setNumberFormat("@").setValue(dateStr)
       .setHorizontalAlignment("center").setFontWeight("bold")
       .setBackground(color);
  sheet.getRange(khoi.sub, newCol, 1, 5).setValues([CS_SUB])
       .setFontWeight("bold").setBackground("#e8f5e9").setWrap(true);
  sheet.setColumnWidth(newCol + 1, 60);
  sheet.setColumnWidth(newCol + 3, 60);
  return { col: newCol, khoi: khoi };
}

function findOrCreateEmpRow(sheet, khoi, empNo, name) {
  // ⚠️ CHỈ tìm trong phạm vi khối tháng. Tìm cả sheet là lấy nhầm dòng của THÁNG KHÁC, và mọi
  //    giờ chấm công của tháng này sẽ ghi vào hàng của tháng trước — sai im lặng, rất khó thấy.
  var _tc = -1;
  if (khoi.r2 >= khoi.r1) {
    var ids = sheet.getRange(khoi.r1, 2, khoi.r2 - khoi.r1 + 1, 1).getValues();
    for (var i = 0; i < ids.length; i++) {
      var _id = String(ids[i][0]).trim();
      if (_id === String(empNo)) return khoi.r1 + i;
      /* 🔴 06/08/2026 — HÀNG `-TC` LÀ HÀNG CỦA CHÍNH NGƯỜI NÀY TẠI CƠ SỞ NÀY.
         Anh Thắng: *"nhân viên làm cơ sở thứ 2 thì sẽ thêm mã -TC, sau đó trong web chấm công
         online sẽ hiện cơ sở chấm, bạn chấm bên cơ sở nào nó sẽ nhảy cho cơ sở đó"*.
         Không nhận hàng này thì chấm công ở cơ sở 2 sẽ ĐẺ MỘT HÀNG MỚI mang mã trần, còn hàng
         `-TC` anh tạo nằm trơ ra rỗng — hai hàng cho một người, công tách đôi.
         ⚠️ CHỈ nhận `-TC`. `-TT` / `-TG` / `-CD` là hàng VIỆC THÊM, chúng nằm CẠNH hàng chính
            chứ không thay hàng chính — nhận bừa là giờ của hàng chính ghi đè lên hàng việc thêm.
         ⚠️ Ưu tiên khớp tuyệt đối: vòng lặp trả ngay khi `_id === empNo`, chỉ NHỚ hàng `-TC` để
            dùng khi đi hết mà không thấy hàng chính. */
      if (_tc < 0){
        var _t = _tachMaNhiemVu(_id);
        if (_t && _t.duoi === DUOI_TANG_CUONG && _t.ma === String(empNo)) _tc = khoi.r1 + i;
      }
    }
  }
  if (_tc >= 0) return _tc;
  var newRow = Math.max(khoi.r2 + 1, khoi.r1);
  if (sheet.getMaxRows() < newRow) sheet.insertRowsAfter(sheet.getMaxRows(), newRow - sheet.getMaxRows());
  sheet.getRange(newRow, 1).setValue(name);
  sheet.getRange(newRow, 2).setValue(empNo);
  sheet.setRowHeight(newRow, 62);
  khoi.r2 = newRow;                 // khối vừa dài ra 1 hàng
  return newRow;
}

/**
 * Hàng để ghi giờ cho cặp (người, nhiệm vụ).
 *   · nhiệm vụ của hàng 1 (hoặc kiêm ≤1 việc) -> đúng hàng chính, y như xưa nay
 *   · nhiệm vụ thêm                            -> hàng phụ `mã-ĐUÔI`, CHÈN NGAY DƯỚI hàng chính
 *
 * ⚠️ `insertRowAfter` đẩy MỌI hàng bên dưới tụt xuống 1 -> khối phải dài ra theo
 *    (`khoi.r2 + 1`). Quên là lần ghi sau tìm trong phạm vi cũ, trượt mất hàng cuối
 *    rồi tạo thêm hàng trùng — đúng kiểu hỏng im lặng của sheet này.
 * ⚠️ Hàng mới thừa hưởng ĐỊNH DẠNG hàng trên (đúng ý, trông liền mạch) nhưng phải
 *    xoá sạch nội dung + ghi chú, kẻo tưởng đã chấm công rồi.
 */
function _hangChoNhiemVu(sheet, khoi, maNV, hoTen, nhiemVu, dsNv){
  var maChinh = String(maNV == null ? '' : maNV).trim();
  var maHang  = _maChoNhiemVu(maChinh, nhiemVu, dsNv);
  var hangChinh = findOrCreateEmpRow(sheet, khoi, maChinh, hoTen || maChinh);
  if (maHang === maChinh) return hangChinh;

  if (khoi.r2 >= khoi.r1){
    var ids = sheet.getRange(khoi.r1, 2, khoi.r2 - khoi.r1 + 1, 1).getValues();
    for (var i = 0; i < ids.length; i++)
      if (String(ids[i][0]).trim() === maHang) return khoi.r1 + i;
  }
  /* Tên hàng phụ lấy theo TÊN Ở HÀNG CHÍNH của chính sheet này, không lấy tên trong Phân quyền:
     Phân quyền hay ghi tên gọi tắt ("Thuý Vy") còn bảng công ghi tên đầy đủ ("Trần Thị Thúy Vy"),
     hai hàng cạnh nhau mà khác tên thì nhìn tưởng hai người. */
  var tenGoc = String(sheet.getRange(hangChinh, 1).getValue() || '').trim() || String(hoTen || maChinh);
  var moi = hangChinh + 1;
  sheet.insertRowAfter(hangChinh);
  try {
    sheet.getRange(moi, 1, 1, Math.max(2, sheet.getLastColumn())).clearContent().clearNote();
  } catch(e){}
  sheet.getRange(moi, 1).setValue(_tenChoNhiemVu(tenGoc, maChinh, nhiemVu, dsNv));
  sheet.getRange(moi, 2).setValue(maHang);
  try { sheet.setRowHeight(moi, 62); } catch(e){}
  khoi.r2 = khoi.r2 + 1;
  return moi;
}

/* 🔴 ĐÃ BỎ `_nhiemVuHoSo(maNV)` (03/08). Nó đọc nhiệm vụ từ HỒ SƠ CHUNG `NhanVien`, mà anh Thắng
   đã chốt nhiệm vụ khai RIÊNG TỪNG CƠ SỞ ở `NV_<cơ sở>`: cùng một người ở Posh có thể Thu Tiền,
   ở JP lại Trực Ghế. Dùng lại hàm cũ là lấy nhiệm vụ của cơ sở khác -> tách hàng sai, tính lương
   sai. Cần nhiệm vụ thì gọi `_nhiemVuTaiCoSo(coSo, maNV)` hoặc `_bangNhiemVuCoSo(coSo)`.
   Cột 'Nhiệm vụ' trong sheet `NhanVien` để lại làm DI SẢN, không đọc nữa. */


// ==================== DASHBOARD: DANH SÁCH SHEET + DỮ LIỆU ====================
// getSheetsList(pin): Admin thấy tất cả; CHT/Quản lý chỉ thấy CS_ của cửa hàng mình.
function getSheetsList(pin) {
  var names = SpreadsheetApp.getActiveSpreadsheet().getSheets().map(function(s){ return s.getName(); });
  var u = loginByPin(pin);          // ⚠️ BẮT BUỘC có phiên. Trước đây không truyền pin = mặc định ADMIN.
  if (u.ok === false) return [];
  if (u.isAdmin || u.all) return names;
  return names.filter(function(n){
    return n.indexOf('CS_') === 0 && _canStation(u, n.replace(/^CS_/, ''));
  });
}

// ⚠️ GÁC QUYỀN BẮT BUỘC. Trước đây `if (pin) {...}`: không truyền pin là BỎ QUA kiểm tra
// -> app đang ANYONE_ANONYMOUS nên ai có link cũng đọc được chấm công MỌI cơ sở, không cần PIN.
// Phần đọc thuần tách sang _docSheetData() cho đường token của ESP32 (?action=peek) dùng.
/* ===========================================================================
 *  GỘP LỜI GỌI LÚC MỞ TRANG — cắt chuỗi chờ
 * ---------------------------------------------------------------------------
 *  🔴 03/08/2026, anh Thắng: *"hiện thấy đang bị lag khá chậm"*. Bảng chấm công phải chờ MỘT
 *  CHUỖI 4 lượt gọi máy chủ NỐI TIẾP nhau mới hiện:
 *      getBoPhanCoSo -> getSheetsList -> getSheetData -> getFlags
 *  Nối tiếp nên KHÔNG chồng lấn được: mỗi lượt tốn thêm phần khởi động Apps Script + gác PIN
 *  (đọc sheet PhanQuyen). Hai hàm dưới gộp thành 2 lượt.
 *
 *  ⚠️ Mỗi phần bọc try/catch RIÊNG. Gộp mà một phần ném lỗi là kéo sập cả trang — đắt hơn hẳn
 *     cái lợi tốc độ. Phần nào hỏng thì trả về rỗng kèm `loi`, phần còn lại vẫn dùng được.
 *  ⚠️ KHÔNG bỏ hàm cũ: bản web đang mở trong máy anh Thắng vẫn gọi tên cũ cho tới khi tải lại
 *     trang. Bỏ là trang đang mở hỏng ngay lúc deploy.
 * =========================================================================== */
function getBoPhanVaSheets(pin){
  var out = { bp:null, sheets:[], loi:'' };
  try { out.bp = getBoPhanCoSo(pin); } catch (e) { out.loi += 'boPhan: ' + e + ' · '; }
  try { out.sheets = getSheetsList(pin); } catch (e) { out.loi += 'sheets: ' + e + ' · '; }
  return out;
}
/* Dữ liệu bảng + cờ kiểm tra của CÙNG một cơ sở — hai thứ luôn đi đôi, gọi riêng là chờ hai lượt.
   `thang` rỗng = mọi tháng (giữ đường cũ). */
function getSheetDataVaFlags(sheetName, pin, thang){
  var station = String(sheetName || '').replace(/^CS_/, '');
  var out = { rows:[], dates:[], thangs:[], thang:'', flags:[], loi:'' };
  try {
    var d = getSheetData(sheetName, pin, thang) || {};
    out.rows = d.rows || []; out.dates = d.dates || [];
    out.thangs = d.thangs || []; out.thang = d.thang || '';
  } catch (e) { out.loi += 'duLieu: ' + e + ' · '; }
  try { out.flags = getFlags(pin, station, true) || []; } catch (e) { out.loi += 'co: ' + e + ' · '; }
  return out;
}

function getSheetData(sheetName, pin, thang) {
  var u = loginByPin(pin);
  if (u.ok === false) return { rows: [], dates: [], thangs: [] };
  var station = String(sheetName || '').replace(/^CS_/, '');
  if (!u.isAdmin && !u.all && !_canStation(u, station)) return { rows: [], dates: [], thangs: [] };
  return _docSheetData(sheetName, thang);
}
/* Chuẩn hoá tham số tháng về 'yyyy-MM'. Nhận 'Tháng 08-2026' (khuôn client), '2026-08',
   '2026-08-15', hoặc rỗng = LẤY HẾT (giữ nguyên cách cũ). */
function _thangISO(t){
  var s = String(t == null ? '' : t).trim();
  if (!s) return '';
  var m = s.match(/^Tháng\s*(\d{1,2})\s*-\s*(\d{4})$/);
  if (m) return m[2] + '-' + ('0' + m[1]).slice(-2);
  m = s.match(/^(\d{4})-(\d{1,2})(?:$|-)/);
  if (m) return m[1] + '-' + ('0' + m[2]).slice(-2);
  return '';
}

/* ===========================================================================
 *  ĐỌC CHẤM CÔNG — CHỈ KHỐI THÁNG ĐANG XEM
 * ---------------------------------------------------------------------------
 *  🔴 03/08/2026, anh Thắng: *"hiện thấy đang bị lag khá chậm"*. Đây là chỗ nặng nhất:
 *  bản cũ đọc CẢ SHEET (mọi khối tháng) bằng BA lượt getDisplayValues + getFormulas +
 *  getValues, rồi trả HẾT về máy — trong khi màn hình chỉ dùng MỘT tháng (mọi chỗ dùng
 *  `allRows` đều lọc `r[0] === month`). Sheet cơ sở mỗi tháng thêm một khối, nên
 *  **app tự chậm dần theo thời gian** — khớp đúng cảm giác "dạo này chậm".
 *  Sheet 157 cột × 26 người: 1 tháng ≈ 4.400 ô × 3 lượt; tới tháng 12 là gấp 6.
 *
 *  Nay: `_csKhoi` (chỉ đọc cột C — rẻ) tìm các khối, mỗi khối đọc RIÊNG một hàng ngày để
 *  biết nó là tháng nào (1 hàng, rẻ), rồi chỉ đọc grid của khối được chọn.
 *  Trả thêm `thangs` = danh sách MỌI tháng, để ô chọn tháng vẫn đủ dù chỉ tải một tháng.
 *
 *  ⚠️ `thang` rỗng = ĐỌC HẾT, đúng như cũ. Ba chỗ gọi khác (peek của ESP32, tổng hợp
 *     nhiều cơ sở) không truyền gì nên hành vi không đổi một chút nào.
 * =========================================================================== */
function _docSheetData(sheetName, thang, thoMaCu) {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(sheetName);
  if (!sheet) return { rows: [], dates: [], thangs: [] };
  var lastRow = sheet.getLastRow();
  var lastCol = sheet.getLastColumn();
  if (lastRow < 2 || lastCol < 3) return { rows: [], dates: [], thangs: [] };

  // ⚠️ Từ 01/08/2026 sheet có NHIỀU KHỐI THÁNG xếp dọc. Phải đi qua TỪNG khối và đọc hàng ngày
  //    CỦA CHÍNH KHỐI ĐÓ. Chỉ đọc hàng 1 như trước là mọi tháng sau tháng đầu MẤT SẠCH khỏi
  //    Giờ & Lương — mà không báo lỗi gì, chỉ là bảng trống. Sheet cũ có 1 khối -> chạy y như cũ.
  //    Kết quả trả về giữ NGUYÊN dạng phẳng [tháng, ngày, mã, tên, vào, ảnh, ra, ảnh] nên phần
  //    tính lương phía trên không phải sửa một dòng nào.
  var khois = _csKhoi(sheet);
  if (!khois.length) khois = [{ hdr:1, sub:2, r1:3, r2:lastRow }];   // sheet lạ khuôn -> hiểu như cũ

  var thangs = [];
  khois.forEach(function(k){
    k.thang = _csThangKhoi(sheet, k);
    if (k.thang && thangs.indexOf(k.thang) < 0) thangs.push(k.thang);
  });
  thangs.sort(); thangs.reverse();                      // mới nhất lên đầu

  var loc = _thangISO(thang);
  /* Tháng không đọc được (khối lạ khuôn, hàng ngày trống) thì KHÔNG được loại bỏ khi lọc —
     loại đi là mất dữ liệu thật mà bảng chỉ hiện trống, không báo gì. Giữ lại cho chắc. */
  var canDoc = loc ? khois.filter(function(k){ return !k.thang || k.thang === loc; }) : khois;

  var out = [], ngay = {};
  canDoc.forEach(function(k){
    var soHang = k.r2 - k.hdr + 1;
    if (soHang < 1) return;
    var rng = sheet.getRange(k.hdr, 1, soHang, lastCol);
    var display  = rng.getDisplayValues();
    var formulas = rng.getFormulas();
    var values   = rng.getValues();      // giữ Date để định dạng ngày cho đúng
    var lech = k.hdr - 1;                // hàng thật (1-based) = lech + 1 + chỉ số mảng

    var blocks = [];
    for (var c = 2; c < lastCol; c += 5) {
      var hv = values[k.hdr - 1 - lech][c];
      if (hv === "" || hv == null) break;
      var ds = (hv instanceof Date) ? Utilities.formatDate(hv, TZ, "yyyy-MM-dd") : String(hv);
      blocks.push({ i: c, date: ds });
      ngay[ds] = true;
    }
    if (!blocks.length) return;
    for (var r = k.r1 - 1 - lech; r <= k.r2 - 1 - lech && r < display.length; r++) {
      var name = String(display[r][0] || "");
      var id   = String(display[r][1] || "");
      if (!id && !name) continue;
      for (var b = 0; b < blocks.length; b++) {
        var ci = blocks[b].i;
        var inT  = display[r][ci];
        var outT = display[r][ci + 2];
        if ((inT === "" || inT == null) && (outT === "" || outT == null)) continue;
        var d = new Date(blocks[b].date);
        var monthYear = "Tháng " + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + d.getFullYear();
        out.push([
          monthYear,
          blocks[b].date,
          id,
          name,
          toTimeStr(inT),
          extractImageUrl(formulas[r][ci + 1]),
          toTimeStr(outT),
          extractImageUrl(formulas[r][ci + 3])
        ]);
      }
    }
  });
  return { rows: (thoMaCu ? out : _gopMaCuKhiDoc(out)),
           dates: Object.keys(ngay).sort(), thangs: thangs, thang: loc };
}

/* ===========================================================================
 *  GỘP MÃ CŨ VỀ MÃ CHÍNH — KHI ĐỌC, KHÔNG SỬA SHEET  (07/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"mã cũ dính với máy chấm công, nên trong sheet CS nó có và mã mới thì đi với
 *  nhân viên và hệ thống online, nên tra dữ liệu ra sẽ là 1"*.
 *
 *  Đúng vậy: một người đang có tối đa HAI hàng trong cùng khối tháng —
 *    · hàng mã cũ  (TUBT1, TUTP02…) do ĐẦU ĐỌC ghi, vì máy chưa nhận lệnh đổi mã;
 *    · hàng mã mới (MNNV2KVC0092…) do hồ sơ web + chấm công online ghi.
 *  Trước bản này, hai hàng ra hai dòng lương -> nhìn như hai người, mà người thật thì thiếu công.
 *
 *  🔴 CHỈ GỘP KHI ĐỌC. Sheet giữ nguyên hai hàng — đó là bằng chứng gốc của đầu đọc, và đầu đọc
 *     vẫn đang ghi tiếp vào hàng mã cũ. Gộp bằng cách sửa sheet là vừa mất dấu vết vừa bị máy
 *     ghi lại hàng cũ ngay lượt chấm công kế tiếp.
 *  🔴 CHỈ GỘP CẶP ĐÃ KHAI ở cột "Mã cũ" (bảng `MaSongSong`). KHÔNG đoán theo tên — đoán tên là
 *     thứ vừa bỏ sót vừa nhận nhầm, và ở đây nhận nhầm nghĩa là trả lương của người này cho
 *     người kia.
 *  🔴 CHỈ GỘP KHI CÓ ĐÚNG MỘT ĐẦU LÀ MÃ CHÍNH (mã dài). Cặp ngắn↔ngắn thì không biết đâu là mã
 *     giữ lại; mã tạm dính hai mã chính là mập mờ. Cả hai ca đều BỎ QUA, không đoán bừa.
 *  🔴 GỘP THEO QUY TẮC [SỚM NHẤT, MUỘN NHẤT], TUYỆT ĐỐI KHÔNG CỘNG GIỜ HAI HÀNG. Ô Giờ vào /
 *     Giờ ra vốn là cặp sớm-nhất/muộn-nhất của ngày (xem `_ghiGioVaoRa`). Người quẹt máy lúc
 *     8:00 rồi chấm online lúc 17:30 mà đem cộng "2 ca" là ra số giờ không có thật.
 *  ⚠️ Đường `?action=peek` của ESP32 gọi với `thoMaCu = true`: máy suy nghĩ bằng mã của MÁY,
 *     trả mã chính về cho nó là nó tưởng đã ghi rồi rồi bỏ qua lượt thật.
 * =========================================================================== */

/** {mã tạm (đã chuẩn hoá) -> mã chính}. Nhớ trong MỘT lượt chạy để khỏi đọc lại sheet nhiều lần
 *  (`getLichSuChamCongOnline` đọc nhiều cơ sở, `_vpDocThang` đọc hai tháng).
 *  ⚠️ Chỉ là bộ nhớ của một lượt chạy Apps Script — khai/bỏ khai ở lượt khác nên không bị cũ. */
var _MACU_LUOT = null;
function _maCuVeMaChinh(){
  if (_MACU_LUOT) return _MACU_LUOT;
  var map = _maSongSongMap(), doi = {}, co = false;
  Object.keys(map).forEach(function(k){
    if (_laMaDai(k)) return;                      // mã chính thì KHÔNG bao giờ bị đổi đi
    var dai = map[k].filter(function(x){ return _laMaDai(x.ma); });
    if (dai.length !== 1) return;                 // 0 = cặp ngắn↔ngắn · >1 = mập mờ -> không đoán
    doi[k] = dai[0].ma; co = true;
  });
  return (_MACU_LUOT = { co:co, map:doi });
}

/** Mọi mã của MỘT người: mã chính + các mã cũ đã khai. Dùng cho chỗ dò hàng trong sheet. */
function _dsMaCuaNguoi(maChinh){
  var out = {}, can = _chuanMa(maChinh);
  if (!can) return out;
  out[can] = 1;
  var d = _maCuVeMaChinh();
  if (d.co) Object.keys(d.map).forEach(function(k){ if (_chuanMa(d.map[k]) === can) out[k] = 1; });
  return out;
}

/** Gộp hai lượt CÙNG NGÀY CÙNG NGƯỜI thành một: sớm nhất làm giờ vào, muộn nhất làm giờ ra. */
function _gopHaiLuot(a, b){
  var moc = [];
  function them(gio, anh){
    var s = String(gio == null ? '' : gio).trim();
    if (!s) return;
    moc.push({ s:s, giay:secOf(s), anh:String(anh == null ? '' : anh) });
  }
  them(a[4], a[5]); them(a[6], a[7]); them(b[4], b[5]); them(b[6], b[7]);
  if (!moc.length) return a;
  var som = moc[0], muon = moc[0];
  for (var i = 1; i < moc.length; i++){
    if (moc[i].giay < som.giay)  som  = moc[i];
    if (moc[i].giay > muon.giay) muon = moc[i];
  }
  var r = a.slice();
  r[4] = som.s;  r[5] = som.anh;
  // Chỉ MỘT mốc trong ngày = mới có giờ vào, chưa có giờ ra. Ghi giờ ra bằng chính giờ vào là
  // bịa ra một ca dài 0 phút mà bảng lại hiện "đã ra".
  if (muon.giay > som.giay){ r[6] = muon.s; r[7] = muon.anh; }
  else { r[6] = ''; r[7] = ''; }
  return r;
}

function _gopMaCuKhiDoc(rows){
  var d = _maCuVeMaChinh();
  if (!d.co) return rows;                 // chưa khai cặp nào -> trả nguyên, không tốn gì thêm
  var out = [], viTri = {}, tenChinh = {};
  for (var i = 0; i < rows.length; i++){
    var g = rows[i];
    var chinh = d.map[_chuanMa(g[2])] || '';
    var r = g.slice();
    if (chinh) r[2] = chinh;
    else tenChinh[_chuanMa(r[2])] = tenChinh[_chuanMa(r[2])] || String(r[3] || '');
    var khoa = String(r[1] || '') + '|' + _chuanMa(r[2]);
    var j = viTri[khoa];
    if (j === undefined){ viTri[khoa] = out.length; out.push(r); }
    else out[j] = _gopHaiLuot(out[j], r);
  }
  /* Tên lấy từ hàng mang MÃ CHÍNH ở BẤT KỲ ngày nào trong dữ liệu, không chỉ ngày này. Lấy tên
     của hàng mã cũ là bảng hiện tên do đầu đọc đặt (hay thiếu dấu, viết tắt) — nhìn như người
     khác. Không có hàng mã chính nào thì giữ tên cũ, còn hơn để trống. */
  return out.map(function(r){
    var t = tenChinh[_chuanMa(r[2])];
    if (t) r[3] = t;
    return r;
  });
}

function extractImageUrl(formula) {
  if (!formula) return "";
  var m = formula.match(/IMAGE\("([^"]+)"/i);
  return m ? m[1] : "";
}

// Đọc ảnh chấm công 1 ngày. Quét thư mục MỚI trước, rồi thư mục CŨ — đổi thư mục lưu KHÔNG được
// làm ảnh của những ngày trước biến mất. Ảnh ở thư mục mới thắng nếu trùng tên file.
function getPhotoDay(station, dateStr, pin) {
  var map = {};
  var u = loginByPin(pin);           // ⚠️ Trước đây KHÔNG có tham số pin -> ai cũng lấy được link ảnh chấm công
  if (u.ok === false) return map;
  station = String(station || '').replace(/^CS_/, '');
  if (!u.isAdmin && !u.all && !_canStation(u, station)) return map;
  var p = String(dateStr).split('-');
  if (p.length < 3) return map;
  var monthYear = "Tháng " + p[1] + "-" + p[0];
  _attFolderIds().forEach(function(fid){
    try {
      var root = DriveApp.getFolderById(fid);
      var sf = root.getFoldersByName(station);
      if (!sf.hasNext()) return;
      var mf = sf.next().getFoldersByName(monthYear);
      if (!mf.hasNext()) return;
      var files = mf.next().searchFiles('title contains "' + dateStr + '"');
      while (files.hasNext()) {
        var f = files.next(), nm = f.getName();
        if (map[nm]) continue;                     // đã có ở thư mục mới -> không ghi đè bằng bản cũ
        map[nm] = 'https://lh3.googleusercontent.com/d/' + f.getId() + '=w300';
      }
    } catch (e) {}                                 // 1 thư mục lỗi quyền thì vẫn đọc thư mục còn lại
  });
  return map;
}

function hhmm(t) {
  if (!t) return "";
  if (t instanceof Date) return Utilities.formatDate(t, TZ, "HH:mm");
  var s = String(t);
  return s.length >= 5 ? s.substring(0, 5) : s;
}
function toTimeStr(t) {
  if (t instanceof Date) return Utilities.formatDate(t, TZ, "HH:mm:ss");
  return String(t);
}
function secOf(t) {
  var s = (t instanceof Date) ? Utilities.formatDate(t, TZ, "HH:mm:ss") : String(t);
  var p = s.split(":");
  return (parseInt(p[0], 10) || 0) * 3600 + (parseInt(p[1], 10) || 0) * 60 + (parseInt(p[2], 10) || 0);
}


// ============================================================================
//  PHẦN MỚI 1 — ĐĂNG NHẬP PIN + PHÂN QUYỀN THEO CỬA HÀNG
// ============================================================================
// ĐĂNG NHẬP: cache 60s (bỏ đọc sheet PhanQuyen ở MỖI lệnh server) + chấp nhận token SSO từ trang tổng.
/* ---------- CHẶN DÒ PIN ------------------------------------------------------------------
 * PIN chỉ 6 số = 1 triệu khả năng, mà web app mở ẩn danh: ai có link `/exec` là gọi
 * `loginByPin` bao nhiêu lần cũng được. Dò hết 1 triệu bằng máy là chuyện của vài giờ.
 *
 * Apps Script KHÔNG cho biết IP người gọi, nên không khoá theo IP được. Cũng KHÔNG khoá cứng
 * theo PIN: kẻ dò thử mỗi PIN đúng một lần nên khoá-theo-PIN chẳng cản được gì, mà lại thành
 * đường cho người xấu khoá tài khoản của người khác.
 *
 * Cách dùng ở đây: đếm số lần SAI trong 10 phút gần nhất (toàn hệ thống) và BẮT CHỜ khi vượt
 * ngưỡng — chờ càng lâu khi càng nhiều lần sai. Chỉ phạt lượt SAI:
 *   · anh gõ nhầm một lần thì gần như không thấy gì,
 *   · máy dò thì mỗi lượt tốn vài giây -> 1 triệu khả năng thành hàng tháng trời,
 *   · đăng nhập ĐÚNG không bị chậm, nên đây KHÔNG phải đường để ai đó khoá cả hệ thống.
 * ⚠️ Chờ tối đa 8 giây: Apps Script giới hạn 6 phút/lượt chạy và có hạn mức chạy song song. */
var PIN_DO_KHOA   = 'pin_sai_gan_day';
var PIN_DO_CUA_SO = 600;      // giây — cửa sổ đếm
var PIN_DO_NGUONG = 8;        // sai quá số này mới bắt đầu phạt
var PIN_DO_MOI_LAN = 700;     // ms cộng thêm cho mỗi lần sai vượt ngưỡng
var PIN_DO_TOI_DA = 8000;     // ms — trần

function _pinDoSoLan(){
  try { return Number(CacheService.getScriptCache().get(PIN_DO_KHOA) || 0) || 0; } catch (e) { return 0; }
}
function _pinDoGhiSai(){
  try {
    var c = CacheService.getScriptCache();
    c.put(PIN_DO_KHOA, String(_pinDoSoLan() + 1), PIN_DO_CUA_SO);
  } catch (e) {}
}
function _pinDoXoa(){ try { CacheService.getScriptCache().remove(PIN_DO_KHOA); } catch (e) {} }
/** Chờ bao nhiêu ms cho lượt SAI tiếp theo. Tách hàm để test được mà không phải ngồi đợi thật. */
function _pinDoChoBaoLau(soLanSai){
  var thua = (Number(soLanSai) || 0) - PIN_DO_NGUONG;
  if (thua <= 0) return 0;
  return Math.min(thua * PIN_DO_MOI_LAN, PIN_DO_TOI_DA);
}

function loginByPin(pin) {
  pin = String(pin || '').trim();
  if (!pin) return { ok:false, error:'Thiếu mã PIN' };
  var cache = CacheService.getScriptCache(), ck = _authKey(pin);
  var hit = cache.get(ck);
  if (hit) { try { return JSON.parse(hit); } catch(e){} }
  var u = _loginResolve(pin);
  if (u && u.ok) {
    try { cache.put(ck, JSON.stringify(u), 60); } catch(e){}
    /* ⚠️ KHÔNG xoá bộ đếm khi đăng nhập đúng. Kẻ dò chỉ cần một tài khoản hợp lệ (hoặc chờ một
       nhân viên đăng nhập) là bộ đếm về 0 và lại dò thoải mái. Bộ đếm tự hết sau 10 phút. */
    return u;
  }
  // Chỉ phạt lượt SAI, và phạt SAU khi đã tính ra kết quả (không giữ khoá/không đụng sheet).
  var cho = _pinDoChoBaoLau(_pinDoSoLan());
  _pinDoGhiSai();
  if (cho > 0) { try { Utilities.sleep(cho); } catch (e) {} }
  return u;
}
/**
 * Đăng nhập cho app LƯƠNG (tách riêng khỏi Chấm công, 09/08/2026) — anh Thắng:
 * *"Admin với kế toán thôi nhé em"*, *"Đăng nhập riêng"* (không SSO với Chấm công).
 *
 * ⚠️ ĐI QUA `loginByPin`, KHÔNG viết đường kiểm PIN thứ hai: dò PIN sai, khoá tạm thời, cache —
 *    tất cả đã đúng đắn ở đó, chép lại là sớm muộn một bên sửa mà bên kia không theo (đúng bài
 *    học đã ghi khắp file này). Hàm này CHỈ thêm đúng một việc: PIN đúng nhưng KHÔNG phải
 *    Admin/Kế toán thì vẫn bị từ chối — không phải "sai PIN", để người dùng hiểu đúng lý do.
 * ⚠️ CỐ Ý dùng ROLE.ADMIN / ROLE.KE_TOAN viết thẳng, không lấy danh sách từ đâu khác: đây là
 *    CHỐT DUY NHẤT quyết định ai vào được app tiền lương của cả chuỗi, phải nhìn thấy ngay tại
 *    chỗ, không được nằm gián tiếp qua một biến có thể bị sửa vì lý do khác.
 */
function loginLuong(pin) {
  var u = loginByPin(pin);
  if (!u.ok) return u;                              // giữ nguyên lý do gốc (sai PIN, đang khoá…)
  if (u.role !== ROLE.ADMIN && u.role !== ROLE.KE_TOAN)
    return { ok:false, error:'Tài khoản này không có quyền vào app Lương (chỉ Admin / Kế toán).' };
  return u;
}
/**
 * TÊN để chào trên màn hình.
 *
 * 🔴 07/08/2026 — anh Thắng: *"chỗ phần hiển thị tên lấy nhầm Mã NV"*. Màn hình chào
 *    "Xin chào, MNNV2MTD0026". Nguyên nhân: hai chỗ CẤP TÀI KHOẢN HÀNG LOẠT viết
 *    `name: <tên> || <mã>` — hàng nào trong sổ nhân sự bỏ trống ô tên thì app ghi thẳng MÃ vào
 *    cột `Họ tên` của `PhanQuyen`, rồi từ đó chào bằng mã. (Đã bỏ cái `|| mã` đó, xem
 *    `capPinHangLoat` / `dongBoPinTheoSheet`.)
 *
 * Hàm này chữa phần ĐANG HIỂN THỊ, kể cả với những dòng đã lỡ ghi sai từ trước — anh không phải
 * đi sửa tay từng dòng trong sheet:
 *   · cột `Họ tên` trống, HOẶC đang chính là mã NV  ->  tra TÊN THẬT trong hồ sơ `NhanVien`
 *   · hồ sơ cũng không có tên  ->  trả lại nguyên trạng, KHÔNG bịa
 *
 * ⚠️ Chỉ tra khi CẦN. `loginByPin` chạy ở mọi lượt gọi, quét thêm cả sổ nhân sự mỗi lượt là làm
 *    app chậm lại đúng chỗ vừa mất công tối ưu. Bản đồ mã→tên có cache 5 phút, dùng chung mọi
 *    người, và tài khoản có tên đàng hoàng thì không đọc gì thêm.
 */
function _pqTenHienThi(ten, maNV){
  ten = String(ten == null ? '' : ten).trim();
  maNV = String(maNV == null ? '' : maNV).trim();
  if (!maNV) return ten;
  if (ten && _chuanMa(ten) !== _chuanMa(maNV)) return ten;      // đã có tên thật -> khỏi tra
  var that = _nvTenTheoMa()[_chuanMa(maNV)];
  return that ? that : ten;
}
/** Mã NV -> Họ tên, đọc `NhanVien` MỘT lượt, cache 5 phút (dùng chung mọi tài khoản). */
function _nvTenTheoMa(){
  var cache = null, khoa = 'nvten1';
  try { cache = CacheService.getScriptCache(); } catch(e){}
  if (cache){ var hit = cache.get(khoa); if (hit){ try { return JSON.parse(hit); } catch(e){} } }
  var out = {};
  try {
    var sh = _sheet(SH_NV);
    if (sh && sh.getLastRow() >= 2){
      sh.getRange(2, 1, sh.getLastRow() - 1, 2).getValues().forEach(function(r){
        var m = _chuanMa(r[0]), t = String(r[1] == null ? '' : r[1]).trim();
        /* Hồ sơ cũng ghi mã vào ô tên thì coi như KHÔNG có tên — nhận vào là chào bằng mã tiếp,
           chỉ đổi chỗ lấy chứ không chữa được gì. */
        if (m && t && _chuanMa(t) !== m) out[m] = t;
      });
    }
  } catch(e){}
  if (cache){ try { cache.put(khoa, JSON.stringify(out), 300); } catch(e){} }
  return out;
}
function _authKey(pin){ return 'auth1_' + Utilities.base64EncodeWebSafe(Utilities.computeDigest(Utilities.DigestAlgorithm.MD5, pin)); }
function _clearAuthCache(pin){ try { if (pin) CacheService.getScriptCache().remove(_authKey(String(pin).trim())); } catch(e){} }
function _loginResolve(pin) {
  if (pin.indexOf('.') > 0) { var s = _ssoLogin(pin); if (s) return s; }   // token SSO -> đăng nhập không cần PIN
  // ⚠️ CỬA HẬU ĐÃ BỎ (30/07/2026): trước đây sheet PhanQuyen trống/mất là trả ADMIN cho MỌI PIN.
  // Chỉ cần ai đó xoá vài dòng trong PhanQuyen là toàn bộ phân quyền biến mất mà app không báo gì.
  // Nay: tự tạo sheet + seed 1 Admin mặc định, rồi BẮT nhập đúng PIN đó — không cấp Admin vô điều kiện.
  var sh = _ensureSheet(SH_ROLE);
  if (sh.getLastRow() < 2) {
    sh.appendRow([ADMIN_PIN_MAC_DINH, 'Admin', ROLE.ADMIN, '']);
    try { console.error('PhanQuyen trống -> đã seed Admin mặc định. ĐĂNG NHẬP RỒI ĐỔI PIN NGAY.'); } catch(e){}
    if (pin !== ADMIN_PIN_MAC_DINH) return { ok:false, error:'Chưa cấu hình người dùng. Đăng nhập bằng PIN mặc định rồi đổi ngay (xem tab Phân quyền).' };
    return { ok:true, pin:pin, name:'Admin', role:ROLE.ADMIN, stations:[], isAdmin:true, isCHT:false, pinYeu:true };
  }
  /* Đọc ĐỦ 6 cột (trước chỉ 4) để lấy thêm `Mã NV chấm công online` — cần nó mới tra được TÊN
     THẬT trong hồ sơ khi cột `Họ tên` bỏ trống hoặc lỡ bị ghi bằng chính mã NV. */
  var v = sh.getRange(2, 1, sh.getLastRow() - 1, PQ_H.length).getValues();
  for (var i = 0; i < v.length; i++) {
    if (String(v[i][0]).trim() === pin) {
      var role = String(v[i][2] || ROLE.CHT).trim().toUpperCase();
      var stations = String(v[i][3] || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean);
      var maNV = String(v[i][4] || '').trim();
      return {
        ok:true, pin:pin, name:_pqTenHienThi(String(v[i][1] || ''), maNV), maNV:maNV,
        role:role, stations:stations,
        isAdmin: role === ROLE.ADMIN,
        isCHT:   role === ROLE.CHT || role === ROLE.QUAN_LY,
        // Web app mở ẩn danh + PIN này nằm trong mã nguồn = ai có link cũng vào được.
        // Bật cờ để giao diện nhắc đổi; chỉ hiện SAU khi đã đăng nhập, không lộ ra ngoài.
        /* PIN yếu = giao diện nhắc đổi. Không chỉ PIN mặc định: sổ phân quyền thật đang có PIN
           "0" cho một tài khoản QUẢN LÝ, và mấy PIN kiểu 999999 — dò một phát là ra.
           ⚠️ Chỉ trả cờ SAU khi đã đăng nhập đúng, không lộ gì ra ngoài. */
        pinYeu:  _pinYeu(pin)
      };
    }
  }
  return { ok:false, error:'Sai mã PIN' };
}

// ===== SSO: nhận danh tính đã xác thực từ trang tổng K&H (qua ?sso=<token>) =====
var SSO_AUTO_ROLES = ['ADMIN','QUAN_LY','CUA_HANG_TRUONG'];   // KE_TOAN/khác -> vẫn đăng nhập bằng PIN
function _ssoSecret_(){ return PropertiesService.getScriptProperties().getProperty('SSO_SECRET') || ''; }
function _verifySso(token){
  try{
    if(!token) return null;
    var parts = String(token).split('.'); if(parts.length !== 2) return null;
    var secret = _ssoSecret_(); if(!secret) return null;
    var expect = Utilities.base64EncodeWebSafe(Utilities.computeHmacSha256Signature(parts[0], secret));
    if(expect !== parts[1]) return null;
    var obj = JSON.parse(Utilities.newBlob(Utilities.base64DecodeWebSafe(parts[0])).getDataAsString('UTF-8'));
    if(!obj || !obj.x || (new Date()).getTime() > Number(obj.x)) return null;
    return obj;
  }catch(e){ return null; }
}
// token -> user object giống loginByPin. Admin & Quản lý xem MỌI cửa hàng (all:true); CHT theo 'branches' của trang tổng.
function _ssoLogin(token){
  var o = _verifySso(token); if(!o) return null;
  var role = String(o.r||'').trim().toUpperCase();
  if(SSO_AUTO_ROLES.indexOf(role) < 0) return null;
  var br = Array.isArray(o.b) ? o.b : String(o.b||'').split(',');
  var stations = br.map(function(s){ return String(s).trim(); }).filter(Boolean);
  return {
    ok:true, sso:true, pin:token, name:String(o.n||''), role:role, stations:stations,
    isAdmin: role === ROLE.ADMIN,
    isCHT:   role === ROLE.CHT || role === ROLE.QUAN_LY,
    all:     role === ROLE.ADMIN || role === ROLE.QUAN_LY
  };
}

function _canStation(u, station) {
  if (!u || u.ok === false) return false;
  // ⚠️ NHÂN VIÊN không xem chấm công của ai — kể cả cửa hàng ghi trong dòng phân quyền của họ.
  //    Cửa hàng đó chỉ để biết chấm công online ghi vào sheet nào, KHÔNG phải quyền xem.
  if (u.role === ROLE.NHAN_VIEN) return false;
  if (u.isAdmin || u.all) return true;   // Admin (và Quản lý qua SSO) thấy mọi cửa hàng
  if (!u.stations || u.stations.length === 0) return false;
  return u.stations.indexOf(station) !== -1;
}
function _requireAuth(pin) {
  var u = loginByPin(pin);
  if (!u.ok) throw new Error('Phiên đăng nhập không hợp lệ, hãy đăng nhập lại.');
  return u;
}


// ============================================================================
//  PHẦN MỚI 2 — QUẢN LÝ NHÂN VIÊN + HÀNG ĐỢI ĐẨY HIKVISION
// ============================================================================
// Danh sách NV (lọc theo cửa hàng nếu là CHT)
// Sheet NhanVien luôn đủ 23 cột + header đúng (tự nới cho sheet cũ 7 cột).
function _nvSheet() {
  var sh = _ensureSheet(SH_NV);
  var need = NV_HEADERS.length;
  if (sh.getMaxColumns() < need) sh.insertColumnsAfter(sh.getMaxColumns(), need - sh.getMaxColumns());
  var cur = sh.getRange(1, 1, 1, need).getValues()[0], changed = false;
  for (var i = 0; i < need; i++) { if (String(cur[i] || '') !== NV_HEADERS[i]) { cur[i] = NV_HEADERS[i]; changed = true; } }
  if (changed) sh.getRange(1, 1, 1, need).setValues([cur]);
  return sh;
}
function _canSalary(u) { return !!u.isAdmin || u.role === ROLE.QUAN_LY; }   // xem lương/ngân hàng

/* ===========================================================================
 *  QUYỀN NHÂN SỰ CHIA HAI MỨC  —  anh Thắng 07/08/2026
 * ---------------------------------------------------------------------------
 *  Anh: *"quyền quản lý nhân viên cửa hàng anh sẽ bàn giao cho cửa đó luôn"*, rồi chốt
 *  *"chọn cht phân quyền theo mức"*. Trước đây chỉ có MỘT cửa `_canEditNV` = Admin/Quản lý,
 *  nên Cửa hàng trưởng không sửa nổi một cái số điện thoại của nhân viên mình.
 *
 *  MỨC 1 — `_canSuaHoSo`: việc HẰNG NGÀY của cửa hàng. CHT làm được.
 *      sửa hồ sơ · cho nghỉ việc · khai/khoá tăng cường · quét máy · dọn lệnh máy kẹt
 *  MỨC 2 — `_canQuanTriNV`: việc ảnh hưởng RA NGOÀI cửa hàng. Chỉ Admin / Quản lý.
 *      đổi mã NV · xoá hồ sơ · mã song song · duyệt yêu cầu thêm người
 *
 *  🔴 VÌ SAO XOÁ và ĐỔI MÃ nằm ở mức 2, dù nghe cũng là "việc của cửa hàng":
 *     · Xoá hồ sơ = xoá luôn khuôn mặt đã đăng ký trên máy chấm công, KHÔNG dựng lại được. Và nó
 *       để lại PIN đăng nhập sống trong `PhanQuyen` — mà CHT không có quyền vào đó dọn, nên xoá
 *       xong là còn một tài khoản mồ côi đăng nhập được mà chẳng ai biết.
 *     · Đổi mã NV = xoá người trên máy rồi tạo lại + upload lại ảnh khuôn mặt, đụng cả Firebase
 *       lẫn hàng đợi lệnh. Mã còn là khoá tra ở `PhanQuyen`, `CS_`, `NV_`, `MaSongSong`.
 *     Hai việc này hỏng thì hậu quả nằm ngoài cửa hàng, nên để người nhìn được toàn chuỗi làm.
 *
 *  ⚠️ `_canSuaHoSo` CHỈ trả lời "vai này có được sửa hồ sơ không", KHÔNG trả lời "được sửa của ai".
 *     Giới hạn theo cửa hàng vẫn phải do `_canStation(u, cơ sở)` ở từng hàm — và mọi hàm mức 1
 *     đều đã có sẵn chốt đó. Bỏ chốt kia là CHT sửa được hồ sơ cả chuỗi.
 * =========================================================================== */
function _canSuaHoSo(u)   { return !!u.isAdmin || u.role === ROLE.QUAN_LY || u.role === ROLE.CHT; }
function _canQuanTriNV(u) { return !!u.isAdmin || u.role === ROLE.QUAN_LY; }
var _QT_LOI = 'Việc này chỉ Admin / Quản lý làm được (ảnh hưởng ngoài phạm vi cửa hàng).';
function _driveViewUrl(id) { return id ? ('https://drive.google.com/file/d/' + id + '/view') : ''; }

function getEmployees(pin) {
  var u = _requireAuth(pin);
  try {   // throttle: chỉ đối chiếu Firebase tối đa ~mỗi 3 phút (tránh gọi mạng + ghi sheet ở mỗi lần mở tab)
    var _rc = CacheService.getScriptCache();
    if (!_rc.get('reconcile_lock')) { _rc.put('reconcile_lock', '1', 180); _reconcileFromFirebase(getMyStations(pin)); }
  } catch(e){}   // ESP xóa Firebase khi xong -> web tự chuyển 'synced'
  var sh = _nvSheet();
  var last = sh.getLastRow(), n = NV_HEADERS.length;
  var out = [], canSal = _canSalary(u);
  if (last < 2) return out;
  var v = sh.getRange(2, 1, last - 1, n).getValues();
  var csPhu = _coSoPhuMap();     // đọc `TangCuong` MỘT lần cho cả vòng lặp
  for (var i = 0; i < v.length; i++) {
    var emp = String(v[i][0] || '');
    if (!emp) continue;
    var station = String(v[i][2] || '');
    /* 🔴 07/08/2026 — PHẢI XÉT CẢ CƠ SỞ PHỤ.
       Trước đây chỉ xét ô "Cửa hàng" (cơ sở KÝ). Người được tích làm thêm ở cơ sở 2 thì cửa hàng
       trưởng cơ sở 2 KHÔNG nhận được hồ sơ về máy — bảng bên đó trống trơn, mà chẳng có lỗi nào
       báo ra, nên nhìn y như "chưa thêm người". Anh Thắng gặp đúng cảnh này. */
    /* Hợp HAI nguồn: ô "Cơ sở phụ" trong hồ sơ (anh gõ tay) + lượt tăng cường còn hiệu lực. */
    var phu = _coSoPhuTachO(v[i][7 + NV_EXTRA.indexOf('coSoPhu')]);
    (csPhu[_chuanMa(emp)] || []).forEach(function(cs){ if (phu.indexOf(cs) < 0) phu.push(cs); });
    if (!u.isAdmin && !_canStation(u, station) && !_quaCoSoPhu(u, phu)) continue;
    var o = {
      employeeNo: emp,
      name:       String(v[i][1] || ''),
      station:    station,
      stationPhu: phu.slice(),
      machinePin: String(v[i][3] || ''),
      hasPhoto:   !!v[i][4],
      faceStatus: String(v[i][5] || ''),
      updatedAt:  String(v[i][6] || '')
    };
    for (var k = 0; k < NV_EXTRA.length; k++) {
      var key = NV_EXTRA[k];
      if (NV_SENSITIVE[key] && !canSal) continue;   // ẩn lương/ngân hàng với CHT
      o[key] = String(v[i][7 + k] || '');
    }
    o.cccdUrl = _driveViewUrl(o.cccdFileId || '');
    o.contractUrl = _driveViewUrl(o.contractFileId || '');
    out.push(o);
  }
  return out;
}
/* ===========================================================================
 *  CHUẨN HOÁ SHEET `NhanVien`  (07/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"làm lại cho anh sheet Nhân Viên đi, với bổ sung các cột và thông tin nhân viên
 *  như nãy anh yêu cầu"*.
 *
 *  🔴 "LÀM LẠI" Ở ĐÂY = DỰNG LẠI HÀNG TIÊU ĐỀ + ĐỊNH DẠNG, **KHÔNG XOÁ MỘT DÒNG DỮ LIỆU NÀO**.
 *     Sheet này đang giữ ảnh thẻ, trạng thái đồng bộ, PIN máy, lương — xoá là mất thật, mà repo
 *     GitHub chỉ có CODE, không có bản sao dữ liệu.
 *  🔴 KHÔNG TỰ HOÁN / XOÁ CỘT. App đọc–ghi theo **số thứ tự cột** (`7 + k`), nên đổi chỗ một cột
 *     là số điện thoại nhảy sang ô CCCD, lương nhảy sang số tài khoản — sai im lặng, trên tiền.
 *     Tiêu đề nào lệch thì GHI LẠI cho đúng và **BÁO RA** để anh soát dữ liệu bên dưới; cột thừa
 *     ở cuối thì để nguyên, chỉ đếm và báo.
 *  ⚠️ Ép định dạng VĂN BẢN cho `PIN máy` và `CCCD`: hai cột đó toàn số và **hay có số 0 ở đầu**.
 *     Để Sheet tự hiểu là số thì `049304007231` thành `49304007231` — ô "Quên mã PIN?" khớp tuyệt
 *     đối cả 12 số nên sẽ tra không bao giờ ra, mà nhìn sheet thì vẫn thấy "có số".
 * =========================================================================== */
function chuanHoaSheetNhanVien(pin){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Sửa cấu trúc sheet nhân sự — ' + _QT_LOI };
  var lock = LockService.getScriptLock(); try { lock.waitLock(20000); } catch(e){}
  try {
    var n = NV_HEADERS.length;
    var sh0 = _ensureSheet(SH_NV, NV_HEADERS);       // chưa có sheet thì dựng luôn, đủ cột
    var lcCu = sh0.getLastColumn();
    /* 🔴 ĐỌC TIÊU ĐỀ CŨ **TRƯỚC** khi gọi `_nvSheet()`. Chính `_nvSheet()` là hàm đang âm thầm
       sửa tiêu đề lệch ở mọi lượt mở tab; gọi nó xong mới đọc thì lúc nào cũng thấy "không có
       gì đổi" — báo cáo hoá ra nói dối. So SÁT TỪNG KÝ TỰ đúng như `_nvSheet` so, để `doiTieuDe`
       là danh sách THẬT những ô nó vừa ghi đè, không phải phỏng đoán. */
    /* ⚠️ Chặn trên là `getMaxColumns()`, KHÔNG phải `Math.max(lcCu, n)`. Sheet bị cắt còn 24 cột
       mà đòi đọc 25 ô là GAS ném lỗi ngay tại đây — chưa kịp nới cột. Cột chưa tồn tại thì coi
       như ô rỗng, và như vậy nó vào đúng danh sách "tiêu đề sẽ được ghi thêm". */
    var doc = Math.min(Math.max(lcCu, n), sh0.getMaxColumns());
    var hdCu = sh0.getRange(1, 1, 1, doc).getValues()[0];
    var doi = [], du = [];
    for (var i = 0; i < n; i++){
      var cu = (i < doc) ? String(hdCu[i] == null ? '' : hdCu[i]) : '';
      if (cu === NV_HEADERS[i]) continue;
      doi.push({ cot:_cotChu(i + 1), cu:cu, moi:NV_HEADERS[i] });
    }
    for (var j = n; j < doc; j++)                    // cột lạ nằm sau cột 25 — chỉ NÊU TÊN, không đụng
      du.push({ cot:_cotChu(j + 1), ten:String(hdCu[j] == null ? '' : hdCu[j]) });

    /* Nới bảng + ghi tiêu đề bằng ĐÚNG hàm mọi chỗ khác đang dùng. Không tự viết lại: sheet nào
       bị cắt còn 24 cột mà ghi thẳng 25 ô là VĂNG LỖI ngay, và hai luật ghi tiêu đề song song
       thì sớm muộn cũng lệch nhau. */
    var sh = _nvSheet();
    sh.getRange(1, 1, 1, n)
      .setFontWeight('bold').setBackground('#0f172a').setFontColor('#38bdf8')
      .setVerticalAlignment('middle').setWrap(true);
    sh.setFrozenRows(1);
    try { sh.setFrozenColumns(2); } catch(e){}       // Mã NV + Họ tên luôn nhìn thấy

    // Ép dạng VĂN BẢN cho hai cột số-có-số-0-đầu (xem chú thích trên).
    var iCccd = 8 + NV_EXTRA.indexOf('cccd'), iCsp = 8 + NV_EXTRA.indexOf('coSoPhu');
    var maxR = sh.getMaxRows();
    [4, iCccd].forEach(function(c){
      try { sh.getRange(2, c, Math.max(1, maxR - 1), 1).setNumberFormat('@'); } catch(e){}
    });
    [[1,150],[2,220],[3,140],[4,90],[8,120],[iCccd,140],[iCsp,200]].forEach(function(x){
      try { sh.setColumnWidth(x[0], x[1]); } catch(e){}
    });

    /* Cột nào KHÔNG một hồ sơ nào điền — trả lời thẳng câu "bỏ bớt cột thừa" bằng SỐ LIỆU thay vì
       hỏi lại. Chỉ liệt kê để anh quyết ẩn/không, tuyệt đối không tự xoá: cột trống hôm nay
       (Hợp đồng, Ngày vào làm…) là cột sẽ dùng tháng sau, mà xoá thì lệch hết `7 + k`. */
    var soDong = Math.max(0, sh.getLastRow() - 1), trong = [];
    if (soDong > 0){
      var v = sh.getRange(2, 1, soDong, n).getDisplayValues(), co = [];
      for (var c = 0; c < n; c++) co.push(0);
      for (var r = 0; r < v.length; r++)
        for (var c2 = 0; c2 < n; c2++) if (String(v[r][c2] == null ? '' : v[r][c2]).trim() !== '') co[c2]++;
      for (var c3 = 0; c3 < n; c3++)
        if (!co[c3]) trong.push({ cot:_cotChu(c3 + 1), ten:NV_HEADERS[c3] });
    }

    return { ok:true, soCot:n, tieuDe:NV_HEADERS.slice(), doiTieuDe:doi, cotLa:du,
             cotThua:du.length, cotTrong:trong, soDong:soDong,
             ghiChu:'Chỉ dựng lại hàng tiêu đề + định dạng. KHÔNG xoá, KHÔNG hoán, KHÔNG đụng '
                  + 'một dòng dữ liệu nào.' };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

// Quyền của người đang đăng nhập trên tab Hồ sơ NV (client dùng để ẩn/hiện)
/* Giao diện cần BIẾT CẢ HAI MỨC, không gộp được một cờ: CHT phải thấy nút Sửa hồ sơ nhưng
   KHÔNG được thấy nút Xoá / Đổi mã. Giữ `canEdit` đúng nghĩa cũ (= mức quản trị) để mọi chỗ
   client đang dùng nó cho nút nguy hiểm vẫn an toàn; thêm `canSua` cho mức 1. */
function getNvPerms(pin) {
  var u = _requireAuth(pin);
  return { canEdit: _canQuanTriNV(u), canSua: _canSuaHoSo(u), canSalary: _canSalary(u),
           vaiTro: u.role || '' };
}

/* ===== CƠ SỞ TÍNH THEO NGÀY CÔNG (8h = 1 công) =====
 * Anh Thắng (01/08/2026): *"Đối với cơ sở VP_KH-HCM thì tính theo ngày công (8h 1 công), còn cơ
 * sở khác tính theo h"*.
 * ⚠️ KHÔNG ghi cứng tên cơ sở vào mã: sau này thêm cơ sở nữa thì phải sửa code, mà đây là chỗ
 *    tính tiền — càng ít phải sửa code càng ít rủi ro. Để ở Script Property:
 *      CS_TINH_CONG  = 'VP_KH-HCM'   (nhiều cơ sở thì cách nhau dấu phẩy)
 *      GIO_MOT_CONG  = '8'
 *    Chưa khai thì dùng mặc định dưới đây, đúng ý anh yêu cầu hôm nay.
 */
var CONG_MAC_DINH = 'VP_KH-HCM';
var GIO_MOT_CONG_MAC_DINH = 8;
function getCauHinhCong(pin){
  var u = loginByPin(pin);
  if (u.ok === false) return { congStations: [], gioMotCong: GIO_MOT_CONG_MAC_DINH };
  var pr = PropertiesService.getScriptProperties();
  var raw = pr.getProperty('CS_TINH_CONG');
  if (raw === null || String(raw).trim() === '') raw = CONG_MAC_DINH;
  var gio = Number(pr.getProperty('GIO_MOT_CONG'));
  if (!(gio > 0)) gio = GIO_MOT_CONG_MAC_DINH;       // 0, âm, rỗng, chữ -> về mặc định
  return {
    congStations: String(raw).split(',').map(function(x){ return String(x).trim().replace(/^CS_/, ''); })
                             .filter(function(x){ return x !== ''; }),
    gioMotCong: gio
  };
}

// ===== TÌNH TRẠNG MÁY CHẤM CÔNG (online / offline) =====
var MACHINE_ONLINE_SEC = 120;   // không liên lạc quá 120s -> coi là OFFLINE (ESP poll getCmd ~20s)
// Bảng "Máy chấm công" rộng tay hơn: máy 4G đập heartbeat 60s/lần, sóng 4G rớt 1-2 nhịp là
// thường — 2 phút thì nhấp nháy đỏ suốt dù máy vẫn tốt. Vẫn hiện SỐ PHÚT bên cạnh nhãn.
var MAY_ONLINE_SEC = 15 * 60;
function _touchStation(station){   // ghi "heartbeat" mỗi khi ESP của cơ sở liên lạc tới server
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return;
  try { CacheService.getScriptCache().put('hb_' + station, String(Date.now()), 21600); } catch(e){}
}
/**
 * Mốc liên lạc gần nhất của TỪNG cửa hàng, gộp CẢ HAI nguồn:
 *   · CacheService 'hb_<tên>' — máy đi WiFi: doPost / ?action=pending có chạm _touchStation
 *   · Firebase '/hb/<tên>'    — máy đi 4G: hbSend() ghi mỗi 60s và KHÔNG hề gọi Apps Script
 *
 * ⚠️ Vì sao phải gộp vào MỘT hàm: bảng "Máy chấm công" trước đây chỉ đọc cache, nên máy 4G
 *    đang chạy tốt vẫn hiện OFFLINE, còn tab "Cập nhật FW" (đọc thêm Firebase) lại hiện online
 *    — hai màn hình nói hai kiểu về cùng một máy, không biết tin cái nào. Một định nghĩa duy
 *    nhất thì không lệch được nữa.
 * Trả { '<tên cửa hàng>': { ms:<mốc, ms>, fw:'<bản firmware>' } }
 */
function _hbTatCa(){
  var out = {}, cache = CacheService.getScriptCache();
  var fbhb = {}; try { fbhb = _fbGet('/hb') || {}; } catch(e){}   // Firebase server timestamp (ms)
  Object.keys(fbhb).forEach(function(st){
    var h = fbhb[st]; if (!h) return;
    var t = Number(h.t || 0); if (!t) return;
    /* Chẩn đoán đầu đọc do firmware ≥ 2026-08-03g gửi kèm. Bản cũ không có -> để undefined,
       KHÔNG đặt 0/'' — 0 với '' đọc ra là "không có ai nối AP", tức là bịa ra một kết luận từ
       chỗ không có dữ liệu. `coCd` phân biệt "firmware chưa gửi" với "đã gửi và bằng 0". */
    out[st] = { ms:t, fw:String(h.fw || ''),
                coCd:(h.hikIp !== undefined || h.apSo !== undefined),
                hikIp:String(h.hikIp || ''), hikOk:(Number(h.hikOk || 0) === 1),
                /* Mã HTTP lượt ISAPI gần nhất (firmware ≥ 03j). `null` = firmware chưa gửi —
                   KHÔNG đặt 0, vì 0 có nghĩa riêng là "gọi mà không tới được". */
                hikHttp:(h.hikHttp === undefined ? null : Number(h.hikHttp)),
                hikSn:String(h.hikSn || ''), hikModel:String(h.hikModel || ''),
                /* Kết quả lượt ĐỌC SỔ gần nhất (firmware ≥ 2026-08-04a) — để trả lời câu
                   "chấm lúc máy đang mở thì không lên" mà không phải cắm USB tại cửa hàng. */
                soTu:String(h.soTu || ''),
                soTong:(h.soTong === undefined ? null : Number(h.soTong)),
                soSo:(h.soSo === undefined ? null : Number(h.soSo)),
                soPhut:(h.soPhut === undefined ? null : Number(h.soPhut)),
                soChot:String(h.soChot || ''),          // 'serial' | 'thoi-gian' | '' (fw cũ)
                apSo:(h.apSo === undefined ? null : Number(h.apSo)),
                apIp:String(h.apIp || ''),
                /* Máy ĐANG chạy lượt bù (firmware ≥ 2026-08-07a). Cần để đừng kêu "hàng đợi ĐƠ"
                   trong khi máy làm việc tử tế: lúc bù thì `loop()` không quay nên hàng đợi
                   đứng im là ĐÚNG, không phải hỏng. `null` = firmware cũ, chưa gửi. */
                dangBu:(h.dangBu === undefined ? null : (Number(h.dangBu) === 1)),
                buDaDay:(h.buDaDay === undefined ? null : Number(h.buDaDay)),
                buNgay:String(h.buNgay || ''),
                /* MAC (firmware ≥ 2026-08-07b). Thứ DUY NHẤT phân biệt được máy khi nó chưa có
                   tên cửa hàng — xem ghi chú ở `getDanhSachMay`. */
                mac:String(h.mac || ''),
                /* MAC của sóng AP (firmware ≥ 2026-08-07d). MÁY TRẠM chỉ nhìn thấy cái này (nó dò
                   sóng nên chỉ đọc được BSSID), còn Sheet lưu MAC bo — hai số lệch nhau đúng 1 ở
                   nhóm cuối. Không có nó thì anh cầm số máy trạm hiện mà dò trong bảng không ra. */
                macAp:String(h.macAp || ''),
                /* Múi giờ đầu đọc đóng vào lượt chấm công (firmware ≥ 2026-08-07c). '' = đầu đọc
                   không kèm múi giờ (bình thường), null = firmware cũ chưa gửi. */
                tzDoc:(h.tzDoc === undefined ? null : String(h.tzDoc || '')),
                sim:String(h.sim || '') };
  });
  _allStations().forEach(function(st){
    var raw = cache.get('hb_' + st); if (!raw) return;
    var t = Number(raw); if (!t) return;
    if (!out[st]) out[st] = { ms:t, fw:'' };
    else if (t > out[st].ms) out[st].ms = t;                      // lấy mốc GẦN NHẤT trong 2 nguồn
  });
  return out;
}
// Dashboard gọi: trả tình trạng online/offline từng cơ sở (lọc theo quyền người dùng).
function getMachineStatus(pin){
  var u = loginByPin(pin);           // ⚠️ Trước đây không truyền pin = mặc định ADMIN, thấy hết cơ sở
  if (u.ok === false) return [];
  var names = SpreadsheetApp.getActiveSpreadsheet().getSheets()
    .map(function(s){ return s.getName(); })
    .filter(function(n){ return n.indexOf('CS_') === 0; })
    .map(function(n){ return n.replace(/^CS_/, ''); });
  if (!(u.isAdmin || u.all)) names = names.filter(function(n){ return _canStation(u, n); });
  var hb = _hbTatCa(), now = Date.now();
  return names.sort().map(function(st){
    var h = hb[st];
    var ago = h ? Math.round((now - h.ms) / 1000) : null;
    var r = { station: st, online: (ago != null && ago <= MACHINE_ONLINE_SEC), agoSec: ago,
              fw: (h && h.fw) ? h.fw : '', nguon: 'may' };
    if (ago != null) return r;
    // ⚠️ Cơ sở KHÔNG có máy chấm công (bộ phận Máy tự động, Văn phòng…) thì heartbeat không bao
    //    giờ có -> chip đỏ "chưa liên lạc" VĨNH VIỄN, nhìn như cơ sở chết trong khi vẫn chạy.
    //    Với những cơ sở đó, lấy LƯỢT CHẤM CÔNG gần nhất làm dấu hiệu còn hoạt động.
    var cc = _ccCuoiCache(st);
    if (cc && cc.ms){
      var t = Math.round((now - cc.ms) / 1000);
      r.nguon = 'chamcong'; r.ccSec = t; r.ccNgay = cc.ngay; r.ccGio = cc.gio;
      r.online = (t <= CC_HOATDONG_SEC);
    } else {
      r.nguon = 'khong';
    }
    return r;
  });
}

/* ---------------------------------------------------------------------------
 *  Cơ sở KHÔNG có máy chấm công — biết còn hoạt động nhờ lượt chấm công cuối
 * ------------------------------------------------------------------------ */
var CC_HOATDONG_SEC = 36 * 3600;   // có chấm công trong 36 giờ -> coi là đang hoạt động
                                   // (36h chứ không phải 24h: ca đêm + ngày nghỉ luân phiên)

/** 'yyyy-MM-dd' + 'HH:mm:ss' -> mốc ms. Giờ trong sheet là giờ VN (TZ = Asia/Ho_Chi_Minh, +07). */
function _ccMs(ngay, gio){
  var g = String(gio == null ? '' : gio).trim();
  var m = /(\d{1,2}):(\d{2})(?::(\d{2}))?\s*$/.exec(g);   // ô có thể là '08:30 13:23:38' -> lấy mốc CUỐI
  if (!m) return 0;
  var hh = ('0' + m[1]).slice(-2), mm = m[2], ss = m[3] || '00';
  var d = new Date(ngay + 'T' + hh + ':' + mm + ':' + ss + '+07:00');
  var ms = d.getTime();
  return isNaN(ms) ? 0 : ms;
}

/**
 * Lượt chấm công GẦN NHẤT trong một sheet cơ sở -> {ngay, gio, ms} hoặc null.
 *
 * Đi từ khối tháng CUỐI, quét cột ngày từ PHẢI sang TRÁI, dừng ở cột đầu tiên có giờ.
 * ⚠️ KHÔNG lấy thẳng cột phải cùng: trigger hằng ngày tạo sẵn cột của HÔM NAY nên cột đó
 *    thường còn TRỐNG cho tới lượt quẹt đầu tiên — lấy nó là kết luận "cơ sở chết" oan.
 * ⚠️ Cũng không dừng ở khối tháng cuối nếu khối đó rỗng (đầu tháng): lùi tiếp khối trước.
 */
function _ccCuoi(sheet){
  var ds = _csKhoi(sheet); if (!ds.length) return null;
  var lc = sheet.getLastColumn(); if (lc < 3) return null;
  for (var k = ds.length - 1; k >= 0; k--){
    var kh = ds[k];
    if (kh.r2 < kh.r1) continue;                                  // khối chưa có nhân viên nào
    var rong = lc - 2;
    var hdr  = sheet.getRange(kh.hdr, 3, 1, rong).getValues()[0];
    var cot  = [];
    for (var i = 0; i < hdr.length; i += 5){                      // cột ngày: 3, 8, 13…
      var v = hdr[i]; if (v === '' || v == null) continue;
      var ngay = (v instanceof Date) ? Utilities.formatDate(v, TZ, 'yyyy-MM-dd') : String(v).trim();
      if (/^\d{4}-\d{2}-\d{2}$/.test(ngay)) cot.push({ i: i, ngay: ngay });
    }
    if (!cot.length) continue;
    var vals = sheet.getRange(kh.r1, 3, kh.r2 - kh.r1 + 1, rong).getDisplayValues();
    for (var c = cot.length - 1; c >= 0; c--){
      var gio = '';
      for (var r = 0; r < vals.length; r++){
        var vao = String(vals[r][cot[c].i]     || '').trim();     // Giờ vào
        var ra  = String(vals[r][cot[c].i + 2] || '').trim();     // Giờ ra
        if (vao && vao > gio) gio = vao;
        if (ra  && ra  > gio) gio = ra;
      }
      if (gio) return { ngay: cot[c].ngay, gio: gio, ms: _ccMs(cot[c].ngay, gio) };
    }
  }
  return null;
}

/**
 * Bọc cache 10 phút. Hàm `_ccCuoi` đọc cả khối tháng nên KHÔNG được chạy mỗi lần vẽ trang
 * (dải chip tự làm mới 5 phút/lần × 19 cơ sở). Cache cả kết quả RỖNG để cơ sở chưa có dữ
 * liệu không bị quét lại liên tục.
 */
function _ccCuoiCache(st){
  var cache = null, khoa = 'cccuoi_' + st;
  try { cache = CacheService.getScriptCache(); } catch(e){}
  if (cache){
    var s = cache.get(khoa);
    if (s){ try { return JSON.parse(s); } catch(e){} }
  }
  var sh = _sheet('CS_' + st), kq = null;
  if (sh){ try { kq = _ccCuoi(sh); } catch(e){ kq = null; } }
  if (cache){ try { cache.put(khoa, JSON.stringify(kq), 600); } catch(e){} }
  return kq;
}

/* ===========================================================================
 *  BỘ PHẬN CỦA CƠ SỞ  (02/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng mở thêm bộ phận "Máy tự động"; trước đó chỉ có Khu vui chơi (các
 *  sheet CS_ cũ) và Văn phòng. Danh sách cơ sở ở tab Chấm công đã tới ~19 mục
 *  nên cần lọc theo bộ phận trước khi chọn cơ sở.
 *
 *  ⚠️ KHÔNG suy bộ phận từ TÊN sheet. Tên cơ sở do người gõ ở portal máy chấm
 *     công (FARM_NT, TUTU_BD, FZ_SC_VIVO_T4…) — không có quy luật nào bền, đoán
 *     theo tiền tố là kiểu gì cũng xếp nhầm mà KHÔNG BÁO GÌ. Bảng tra để ở sheet
 *     `BoPhanCoSo`, anh sửa được bằng tay hoặc bằng nút trong web app.
 *
 *  Cơ sở CHƯA có trong bảng thì là 'Chưa xếp' — cố ý hiện ra để thấy mà xếp,
 *  KHÔNG âm thầm nhét vào Khu vui chơi.
 * =========================================================================== */
var SH_BOPHAN  = 'BoPhanCoSo';
var BOPHAN_H   = ['Cửa hàng', 'Bộ phận'];
var BO_PHAN_DS = ['Máy tự động', 'Khu vui chơi', 'Văn phòng'];
var BP_CHUA_XEP = 'Chưa xếp';
var BP_CACHE_KEY = 'bophan_map_v1';

/**
 * Sheet `BoPhanCoSo`, tạo lần đầu thì GIEO SẴN các cơ sở đang có:
 *   VP… -> Văn phòng · còn lại -> Khu vui chơi
 * (đúng lời anh Thắng: "Khu Vui Chơi = các sheet tạo cũ"). Gieo CHỈ MỘT LẦN, lúc
 * sheet còn trống. Cơ sở sinh ra SAU đó không được đoán — để 'Chưa xếp'.
 */
function _bpEnsure(){
  var chuaCo = !_sheet(SH_BOPHAN);
  var sh = _ensureSheet(SH_BOPHAN);
  if (chuaCo && sh.getLastRow() < 2){
    var ds = _allStations().sort();
    if (ds.length){
      sh.getRange(2, 1, ds.length, 2).setValues(ds.map(function(st){
        return [st, /^VP[_\-]/i.test(st) ? 'Văn phòng' : 'Khu vui chơi'];
      }));
    }
    sh.setColumnWidth(1, 200); sh.setColumnWidth(2, 160);
  }
  // Ô chọn trong Sheet để gõ tay không sinh bộ phận ma
  try {
    var rule = SpreadsheetApp.newDataValidation()
      .requireValueInList(BO_PHAN_DS.concat([BP_CHUA_XEP]), true).setAllowInvalid(true).build();
    sh.getRange(2, 2, Math.max(sh.getMaxRows() - 1, 1), 1).setDataValidation(rule);
  } catch(e){}
  return sh;
}

/** {tênCơSở: bộ phận} — cache 5 phút. Giá trị lạ / bỏ trống đều thành 'Chưa xếp'. */
function _bpMap(){
  var cache = null;
  try { cache = CacheService.getScriptCache(); } catch(e){}
  if (cache){
    var s = cache.get(BP_CACHE_KEY);
    if (s) { try { return JSON.parse(s); } catch(e){} }
  }
  var sh = _bpEnsure(), lr = sh.getLastRow(), map = {};
  if (lr >= 2){
    var v = sh.getRange(2, 1, lr - 1, 2).getValues();
    for (var i = 0; i < v.length; i++){
      var st = String(v[i][0] == null ? '' : v[i][0]).replace(/^CS_/, '').trim();
      if (!st) continue;
      var bp = String(v[i][1] == null ? '' : v[i][1]).trim();
      map[st] = (BO_PHAN_DS.indexOf(bp) >= 0) ? bp : BP_CHUA_XEP;
    }
  }
  if (cache){ try { cache.put(BP_CACHE_KEY, JSON.stringify(map), 300); } catch(e){} }
  return map;
}
function _bpXoaCache(){ try { CacheService.getScriptCache().remove(BP_CACHE_KEY); } catch(e){} }

/**
 * Bảng bộ phận cho web app — CHỈ những cơ sở người này được xem (dùng đúng
 * `_canStation` như getSheetsList/getMachineStatus, không mở thêm cửa nào).
 */
function getBoPhanCoSo(pin){
  var u = loginByPin(pin);
  if (u.ok === false) return { ds: BO_PHAN_DS.slice(), chuaXep: BP_CHUA_XEP, map: {}, quanTri: false };
  var names = _allStations();
  if (!(u.isAdmin || u.all)) names = names.filter(function(n){ return _canStation(u, n); });
  var m = _bpMap(), out = {};
  names.sort().forEach(function(st){ out[st] = m[st] || BP_CHUA_XEP; });
  return { ds: BO_PHAN_DS.slice(), chuaXep: BP_CHUA_XEP, map: out, quanTri: !!u.isAdmin };
}

/**
 * Xếp bộ phận cho một hoặc nhiều cơ sở. CHỈ Admin.
 * `list` = [{station, boPhan}, …]. Cơ sở không có sheet `CS_` thì BỎ QUA và báo
 * tên ra — không tự tạo cơ sở từ đây (chỉ `ganMayVaoCuaHang` được mở cửa hàng mới).
 */
function setBoPhanCoSo(pin, list){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok: false, error: 'Chỉ Admin xếp được bộ phận.' };
  list = list || [];
  if (!list.length) return { ok: true, soDoi: 0, boQua: [] };

  var hopLe = {}; _allStations().forEach(function(st){ hopLe[st] = true; });
  var sh = _bpEnsure(), lr = sh.getLastRow();
  var cur = (lr >= 2) ? sh.getRange(2, 1, lr - 1, 2).getValues() : [];
  var viTri = {};
  for (var i = 0; i < cur.length; i++){
    var k = String(cur[i][0] == null ? '' : cur[i][0]).replace(/^CS_/, '').trim();
    if (k) viTri[k] = i + 2;
  }

  var soDoi = 0, boQua = [], them = [];
  for (var j = 0; j < list.length; j++){
    var st = String((list[j] && list[j].station) || '').replace(/^CS_/, '').trim();
    var bp = String((list[j] && list[j].boPhan) || '').trim();
    if (!st || !hopLe[st]){ boQua.push(st || '(trống)'); continue; }
    if (bp !== BP_CHUA_XEP && BO_PHAN_DS.indexOf(bp) < 0){ boQua.push(st + ' (bộ phận lạ)'); continue; }
    if (viTri[st]) { sh.getRange(viTri[st], 2).setValue(bp); }
    else           { them.push([st, bp]); }
    soDoi++;
  }
  if (them.length) sh.getRange(sh.getLastRow() + 1, 1, them.length, 2).setValues(them);
  _bpXoaCache();
  return { ok: true, soDoi: soDoi, boQua: boQua };
}

/**
 * Chẩn đoán hệ thống cho tab "Cập nhật FW" — gộp 2 hàm chẩn đoán lại, chạy bằng PIN đang đăng nhập.
 * Có hàm này thì KHÔNG phải vào editor Apps Script nữa (và không vướng chuyện Session.getActiveUser()
 * trả rỗng vì manifest chưa khai scope email).
 */
function chanDoanHeThong(pin){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin xem được chẩn đoán.' };
  var kq = { ok:true, vanDe:[] };
  function loi(s){ kq.vanDe.push('🔴 ' + s); }
  function nhac(s){ kq.vanDe.push('🟠 ' + s); }

  try { kq.biMat = kiemTraBiMat(pin); }             catch(e){ kq.biMat = { loi:String(e) }; }
  try { kq.thuMucAnh = kiemTraFolderAnhChamCong(pin); } catch(e){ kq.thuMucAnh = { loi:String(e) }; }
  try {
    var P = PropertiesService.getScriptProperties();
    kq.dauVet = { loiFirebase:P.getProperty('last_fb_err') || null,
                  loiAnh:P.getProperty('last_img_err') || null,
                  tuChoiGanNhat:P.getProperty('last_gac_tuchoi') || null };
  } catch(e){}
  // Múi giờ: app tính theo TZ cố định; nếu Sheet để múi khác thì mọi thứ anh NHÌN trong Sheet
  // (định dạng ngày/giờ, hàm NOW/TODAY) lệch so với số app ghi -> phải báo, nhất là khi đổi Sheet mới.
  var tzS = _tzSheet();
  kq.muiGio = { appDung:TZ, cuaSheet:tzS || '(không đọc được)', khop:(tzS === TZ) };
  if (tzS && tzS !== TZ)
    kq.muiGio.canhBao = 'Sheet đang để múi giờ "' + tzS + '" khác "' + TZ + '". App vẫn tính đúng giờ VN, '
      + 'nhưng nên vào Sheet > File > Cài đặt > Múi giờ đổi về Việt Nam cho khỏi lệch khi xem.';

  try { kq.pinMacDinh  = _cdPinMacDinh(); } catch(e){ kq.pinMacDinh  = { loi:String(e) }; }
  try { kq.bang        = _cdBang(); }       catch(e){ kq.bang        = { loi:String(e) }; }
  try { kq.trigger     = _cdTrigger(); }    catch(e){ kq.trigger     = { loi:String(e) }; }
  try { kq.firmware    = _cdFirmware(); }   catch(e){ kq.firmware    = { loi:String(e) }; }
  try { kq.token       = xemToken(pin); }   catch(e){ kq.token       = { loi:String(e) }; }

  // ---- Gom thành danh sách VIỆC CẦN LÀM, để khỏi phải tự soi cả khối JSON ----
  if (kq.pinMacDinh && kq.pinMacDinh.conDung)
    loi('PIN Admin vẫn là PIN mặc định ' + ADMIN_PIN_MAC_DINH + ' (nằm trong mã nguồn). Web app cho phép '
      + 'truy cập ẩn danh nên AI CÓ LINK CŨNG VÀO ĐƯỢC QUYỀN ADMIN. Đổi ngay ở tab Phân quyền.');
  if (kq.biMat && kq.biMat.FB_SECRET && !kq.biMat.FB_SECRET.oScriptProperties)
    loi('Chưa đặt Script Property FB_SECRET -> Firebase trả 401, mất heartbeat / OTA / hàng đợi đồng bộ '
      + 'nhân viên / ảnh. Chấm công vẫn ghi thẳng vào Sheet nên không mất dữ liệu.');
  // Có secret rồi mà vẫn không gọi được thì hoặc secret sai project, hoặc host sai project.
  // Hai đầu lệch project là lỗi IM LẶNG, nên phải in nguyên văn link đang dùng ra đây.
  if (kq.biMat && kq.biMat.FB_SECRET && kq.biMat.FB_SECRET.oScriptProperties
      && kq.biMat.firebase && !(kq.biMat.firebase.ghiDuoc && kq.biMat.firebase.docDuoc))
    loi('Đã có FB_SECRET nhưng Firebase vẫn KHÔNG gọi được. Web app đang gọi: '
      + ((kq.biMat.FB_HOST && kq.biMat.FB_HOST.dangDung) || '(không rõ)')
      + ' — kiểm secret có đúng là của project này (Project settings > Service accounts > '
      + 'Database secrets), và máy ESP32 có cùng link đó. Xem thêm loiFirebaseGanNhat.');
  if (kq.biMat && kq.biMat.EMP_TOKEN && !kq.biMat.EMP_TOKEN.oScriptProperties)
    /* 07/08/2026: hằng số dự phòng đã gỡ -> chưa đặt property KHÔNG còn nghĩa "bí mật nằm trong
       repo" mà là "máy bị chặn hết". Nói sai hướng thì anh đi sửa nhầm chỗ. */
    nhac('🔴 CHƯA đặt Script Property EMP_TOKEN -> app đang TỪ CHỐI mọi lệnh từ máy chấm công '
       + '(đồng bộ nhân viên, trích ảnh, hỏi cơ sở). Chấm công vẫn ghi được vì đi đường khác. '
       + 'Bấm "Tạo token mới" ở mục Token máy chấm công rồi khai token đó vào từng máy.');
  if (kq.token && kq.token.dangChuyenTiep){
    var _cc = kq.token.conDungTokenCu || [];
    if (_cc.length) nhac('Đang đổi token: còn ' + _cc.length + ' cửa hàng gọi bằng token CŨ ('
        + _cc.map(function(x){ return x.cuaHang; }).join(', ') + '). Token cũ hết hạn '
        + kq.token.tokenCuHetHan + ' — chưa đổi kịp là máy đó mất đồng bộ.');
    else nhac('Đang đổi token, chưa thấy máy nào dùng token cũ. Đổi hết rồi thì bấm "Khoá token cũ ngay" '
        + '(token cũ tự hết hạn ' + kq.token.tokenCuHetHan + ').');
  }
  if (kq.thuMucAnh && kq.thuMucAnh.moi && kq.thuMucAnh.moi.ghiDuoc === false)
    loi('Không ghi được vào thư mục ảnh chấm công -> chấm công vẫn ghi GIỜ nhưng MẤT ẢNH.');
  if (kq.muiGio && kq.muiGio.canhBao) nhac(kq.muiGio.canhBao);
  if (kq.bang && kq.bang.thieu && kq.bang.thieu.length)
    nhac('Chưa có bảng: ' + kq.bang.thieu.join(', ') + '. Bình thường — app tự tạo khi dùng tới.');
  if (kq.trigger && kq.trigger.thieu && kq.trigger.thieu.length)
    loi('Thiếu trigger tự động: ' + kq.trigger.thieu.join(', ')
      + '. Bấm nút "Tạo lại trigger tự động" ngay bên cạnh. (Sheet mới thì luôn thiếu.)');
  // Danh tính YẾU: chưa đọc được serial đầu đọc -> đang nhận máy theo MAC bo. Thay bo là mất gán.
  try {
    var _my = _mayRows().rows || [], _yeu = [];
    for (var _i = 0; _i < _my.length; _i++)
      if (!_chuanMa(_my[_i][0]) && _chuanMa(_my[_i][1]))
        _yeu.push(String(_my[_i][2] || _my[_i][1]));
    if (_yeu.length) nhac(_yeu.length + ' máy chưa đọc được SERIAL đầu đọc nên đang nhận theo MAC bo ESP32 ('
      + _yeu.join(', ') + '). Thay bo là MẤT gán cửa hàng. Nguyên nhân thường là ESP32 không với '
      + 'tới đầu đọc (log máy hiện "Lỗi HTTP: -1") — kiểm đầu đọc còn nối WiFi CHAM_CONG với IP 192.168.4.50.');
  } catch(e){}
  if (kq.firmware && !kq.firmware.coLink)
    nhac('Không dựng được link firmware. Đặt Script Property FW_REPO ("chu-repo/ten-repo") '
       + 'hoặc FW_LATEST_URL (link latest.json đầy đủ).');
  if (!kq.vanDe.length) kq.vanDe.push('🟢 Không thấy vấn đề nào.');
  return kq;
}

/** PIN mặc định còn nằm trong PhanQuyen không — đây là lỗ hổng thật vì web app mở ẩn danh. */
function _cdPinMacDinh(){
  var sh = _sheet(SH_ROLE);
  if (!sh || sh.getLastRow() < 2) return { conDung:false, ghiChu:'Bảng ' + SH_ROLE + ' chưa có dòng nào.' };
  var v = sh.getRange(2, 1, sh.getLastRow() - 1, 3).getValues();
  for (var i = 0; i < v.length; i++)
    if (String(v[i][0]).trim() === ADMIN_PIN_MAC_DINH)
      return { conDung:true, dong:i + 2, ten:String(v[i][1] || ''), vaiTro:String(v[i][2] || '') };
  return { conDung:false };
}

/** Bảng nào đã có / còn thiếu — nhìn phát biết Sheet mới đã dựng tới đâu. */
function _cdBang(){
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var can = [SH_ROLE, SH_NV, SH_QUEUE, SH_FLAG, SH_MAY, SH_CHOGAN];
  var co = [], thieu = [];
  can.forEach(function(n){
    var sh = ss.getSheetByName(n);
    if (sh) co.push(n + ' (' + Math.max(0, sh.getLastRow() - 1) + ' dòng)'); else thieu.push(n);
  });
  var cs = ss.getSheets().map(function(s){ return s.getName(); })
              .filter(function(n){ return n.indexOf('CS_') === 0; });
  return { co:co, thieu:thieu, soCuaHang:cs.length, cuaHang:cs };
}

/** Trigger theo giờ: Sheet/dự án mới thì KHÔNG có sẵn, phải bấm tạo lại. */
function _cdTrigger(){
  var can = ['autoAddTodayBlocks','autoBackfillAll','autoBackfillHomNay','warnNoCheckin'];
  var dangCo = ScriptApp.getProjectTriggers().map(function(t){ return t.getHandlerFunction(); });
  var thieu = can.filter(function(f){ return dangCo.indexOf(f) < 0; });
  return { can:can, dangCo:dangCo, thieu:thieu };
}

function _cdFirmware(){
  var P = null, tay = '', repo = '';
  try { P = PropertiesService.getScriptProperties();
        tay  = (P.getProperty('FW_LATEST_URL') || '').trim();
        repo = (P.getProperty('FW_REPO') || '').trim(); } catch(e){}
  var link = _fwLinkLatest();
  return { coLink: !!link, link: link,
           nguon: tay ? 'FW_LATEST_URL' : (repo ? 'FW_REPO' : 'mặc định trong code'),
           banToiThieu: FW_BAN_A };
}
/** Dọn lệnh cũ + tạo lại trigger — cũng chạy được từ web app, khỏi vào editor. */
function donLenhCu(pin){
  var u = _requireAuth(pin); if (!u.isAdmin) return { ok:false, error:'Chỉ Admin' };
  try { return { ok:true, kq: donCmdCu(pin) }; } catch(e){ return { ok:false, error:String(e) }; }
}
function taoLaiTrigger(pin){
  var u = _requireAuth(pin); if (!u.isAdmin) return { ok:false, error:'Chỉ Admin' };
  try { return { ok:true, kq: setupAutoTriggers(pin) }; } catch(e){ return { ok:false, error:String(e) }; }
}

/* ===========================================================================
 *  ĐỔI TOKEN MÁY CHẤM CÔNG từ web app — khỏi vào Cài đặt dự án gõ tay
 * ---------------------------------------------------------------------------
 *  Đặt Script Property EMP_TOKEN bằng tay có 2 cái dở: dễ gõ nhầm, và đổi phát
 *  là TOÀN BỘ máy mất kết nối tới khi anh đi hết các cửa hàng. Nên ở đây:
 *  sinh token ngẫu nhiên đủ dài, lưu luôn, và GIỮ token cũ chạy song song 14
 *  ngày để anh đổi từng máy — app tự đếm còn máy nào chưa đổi.
 *
 *  ⚠️ Token mới CHỈ hiện MỘT LẦN, ngay lúc bấm. Không có hàm nào đọc lại được
 *     (kiemTraBiMat chỉ in 4 ký tự đầu). Chép xong mới đóng bảng.
 * ========================================================================= */
var TOK_HAN_NGAY = 14;

function _sinhToken(){
  // 2 UUID = ~244 bit ngẫu nhiên, thừa sức cho token dán vào URL.
  var a = Utilities.getUuid().replace(/-/g, ''), b = Utilities.getUuid().replace(/-/g, '');
  return 'kh_' + a + b.substring(0, 16);
}

/** Tình trạng token hiện thời + còn cửa hàng nào chưa đổi. KHÔNG trả giá trị token. */
function xemToken(pin){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin' };
  var P = PropertiesService.getScriptProperties();
  var daDat = !!(P.getProperty('EMP_TOKEN') || '');
  var cu = _tokenCu();
  var conCu = _dsConTokenCu();
  var kq = { ok:true, daDatScriptProperty:daDat, dangChuyenTiep:!!cu.co, conDungTokenCu:conCu };
  if (cu.co) kq.tokenCuHetHan = Utilities.formatDate(new Date(cu.han), TZ, 'yyyy-MM-dd HH:mm:ss');
  /* 🔴 07/08/2026 — câu này phải đổi theo việc gỡ hằng số dự phòng. Nói "app đang dùng hằng số
     trong mã nguồn" là SAI SỰ THẬT kể từ nay, mà lại còn trấn an nhầm: thực tế là MỌI máy đang
     bị chặn. Sai kiểu này nguy hơn im lặng — anh đọc xong tưởng vẫn chạy. */
  if (!daDat) kq.canhBao = '🔴 CHƯA đặt Script Property EMP_TOKEN — app đang TỪ CHỐI mọi lệnh từ '
            + 'máy chấm công (đồng bộ nhân viên, trích ảnh, hỏi cơ sở đều chặn). Chấm công vẫn '
            + 'ghi được vì đi đường khác. Bấm "Tạo token mới" rồi khai token đó vào từng máy.';
  else if (cu.co && !conCu.length)
    kq.goiY = 'Chưa thấy máy nào gọi bằng token cũ. Nếu đã đổi hết máy thì bấm "Khoá token cũ ngay".';
  else if (cu.co)
    kq.goiY = 'Còn ' + conCu.length + ' cửa hàng gọi bằng token cũ — đổi xong hết rồi hãy khoá.';
  return kq;
}

/**
 * Sinh token mới + lưu Script Property. Token CŨ vẫn nhận thêm TOK_HAN_NGAY ngày.
 * @return {{ok:boolean, token:string}} token chỉ trả về LẦN NÀY.
 */
function taoTokenMoi(pin){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin' };
  var lock = LockService.getScriptLock();
  try { lock.waitLock(10000); } catch(e){ return { ok:false, error:'Hệ thống đang bận, thử lại sau vài giây.' }; }
  try {
    var P = PropertiesService.getScriptProperties();
    var cu = _empToken();                       // gồm cả trường hợp đang xài hằng số dự phòng
    var moi = _sinhToken();
    var han = (new Date()).getTime() + TOK_HAN_NGAY * 24 * 3600 * 1000;
    P.setProperty('EMP_TOKEN', moi);
    P.setProperty(TOK_CU, cu);
    P.setProperty(TOK_CU_HAN, String(han));
    _dsConTokenCu().forEach(function(x){ try { P.deleteProperty(TOK_DAU + x.cuaHang); } catch(e){} });
    return { ok:true, token:moi,
      hetHanTokenCu: Utilities.formatDate(new Date(han), TZ, 'yyyy-MM-dd HH:mm:ss'),
      soNgay: TOK_HAN_NGAY };
  } finally { try { lock.releaseLock(); } catch(e){} }
}

/** Cắt hiệu lực token cũ NGAY. Máy nào chưa đổi sẽ mất đồng bộ tới khi khai token mới. */
function khoaTokenCu(pin){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin' };
  var conCu = _dsConTokenCu();
  var P = PropertiesService.getScriptProperties();
  try { P.deleteProperty(TOK_CU); P.deleteProperty(TOK_CU_HAN); } catch(e){}
  conCu.forEach(function(x){ try { P.deleteProperty(TOK_DAU + x.cuaHang); } catch(e){} });
  return { ok:true, daKhoa:true, conDungTokenCuLucKhoa:conCu.map(function(x){ return x.cuaHang; }) };
}

/* ===========================================================================
 *  BẢN FIRMWARE MỚI NHẤT do GitHub Actions build — tự điền sẵn, khỏi copy link
 * ---------------------------------------------------------------------------
 *  CI build .bin rồi phát hành kèm `latest.json` ở repo công khai. Đặt link file
 *  đó vào Script Property `FW_LATEST_URL` một lần; từ đó tab Cập nhật FW tự có
 *  phiên bản + link, anh chỉ bấm 1 nút.
 *
 *  31/07/2026 — KHỎI PHẢI ĐẶT GÌ CẢ. Repo firmware công khai thì link latest.json
 *  là cố định và đoán được, nên app tự dựng. Thứ tự ưu tiên:
 *      1) Script Property `FW_LATEST_URL`  — link đầy đủ, muốn trỏ đâu cũng được
 *      2) Script Property `FW_REPO`        — chỉ cần "chu-repo/ten-repo"
 *      3) FW_REPO_MACDINH dưới đây         — mặc định, không cần đặt gì
 *
 *  ⚠️ CHẶN AN TOÀN: bản CI KHÔNG chứa bí mật — máy lấy từ NVS. Máy chưa từng chạy
 *     bản 2026-07-30c (bản chép bí mật vào NVS) mà nhận bản CI thì MẤT CẤU HÌNH,
 *     phải nạp USB lại. Nên hàm này nêu rõ máy nào chưa đủ điều kiện.
 * =========================================================================== */
var FW_BAN_A = '2026-07-30c';   // mốc: từ bản này máy mới giữ bí mật trong NVS

/** So phiên bản dạng 'yyyy-MM-dd' + hậu tố chữ: đủ dùng vì bản nào cũng bắt đầu bằng ngày. */
function _fwDuDieuKien(fw){
  fw = String(fw || '').trim();
  if (!fw) return false;                       // chưa có heartbeat -> không dám kết luận là đủ
  return fw.substring(0, FW_BAN_A.length) >= FW_BAN_A;
}
var FW_REPO_MACDINH = 'zairozy2004199x/khh-chamcong-firmware';   // repo firmware CÔNG KHAI

/** Link latest.json. Tự dựng từ tên repo nên bình thường không phải cấu hình gì. */
function _fwLinkLatest(){
  var P;
  try { P = PropertiesService.getScriptProperties(); } catch(e){ return ''; }
  var full = (P.getProperty('FW_LATEST_URL') || '').trim();
  if (full) return full;
  var repo = (P.getProperty('FW_REPO') || '').trim() || FW_REPO_MACDINH;
  // ⚠️ Bóc dấu "/" thừa TRƯỚC rồi mới bóc ".git" — làm ngược thì "…/fw.git/" còn nguyên ".git".
  repo = repo.replace(/^https?:\/\/github\.com\//i, '').replace(/^\/+|\/+$/g, '').replace(/\.git$/i, '');
  if (repo.split('/').length !== 2) return '';
  // tag 'latest' cố định -> link không đổi qua mọi lần phát hành
  return 'https://github.com/' + repo + '/releases/download/latest/latest.json';
}

function getFwMoiNhat(pin){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin xem được phần cập nhật firmware.' };
  var kq = { ok:false, ver:'', url:'', size:0, chuaDu:[], khongRoFw:[] };
  // Máy nào chưa đủ điều kiện nhận bản CI
  getMachineStatus(pin).forEach(function(m){
    if (!m.fw) { if (m.online) kq.khongRoFw.push(m.station); return; }
    if (!_fwDuDieuKien(m.fw)) kq.chuaDu.push(m.station + ' (' + m.fw + ')');
  });
  var link = _fwLinkLatest();
  if (!link) { kq.error = 'Chưa có nơi lấy firmware. Đặt Script Property FW_REPO ("chu-repo/ten-repo") '
             + 'hoặc FW_LATEST_URL (link latest.json đầy đủ).'; return kq; }
  kq.link = link;   // luôn cho thấy đang gọi link nào — báo lỗi trống rỗng thì không biết đường đâu mà lần
  try {
    var r = UrlFetchApp.fetch(link, { muteHttpExceptions:true, followRedirects:true });
    var code = r.getResponseCode();
    if (code < 200 || code >= 300) {
      // 404 hầu như luôn là "repo firmware chưa tạo" hoặc "chưa phát hành lần nào",
      // KHÔNG phải hỏng mạng. Nói thẳng ra, đừng để phải đoán từ con số.
      kq.error = (code === 404)
        ? 'Chưa có bản phát hành nào (HTTP 404). Repo firmware chưa được tạo, hoặc GitHub Actions '
          + 'chưa build lần nào. Link đang gọi: ' + link
        : 'Không đọc được latest.json (HTTP ' + code + '). Link đang gọi: ' + link;
      return kq;
    }
    var o = JSON.parse(r.getContentText());
    // ⚠️ CHẶN NHẦM LOẠI: repo firmware phát hành 2 dòng — máy chính (`latest.json`) và máy trạm
    //    (`latest-tram.json`). Trỏ nhầm là đẩy firmware máy trạm cho TOÀN BỘ máy chấm công.
    var loai = String(o.loai || '');
    if (loai && loai !== 'may-chinh') {
      kq.error = 'File này là firmware "' + loai + '", KHÔNG phải của máy chấm công. '
               + 'Kiểm lại link — máy chính phải đọc latest.json, không phải latest-tram.json.';
      return kq;
    }
    kq.loai = loai || '(bản cũ chưa ghi loại)';
    kq.ver = String(o.ver || ''); kq.url = String(o.url || ''); kq.size = Number(o.size || 0);
    if (!kq.ver || !kq.url) { kq.error = 'latest.json thiếu ver hoặc url.'; return kq; }
    kq.ok = true;
    return kq;
  } catch (e) { kq.error = 'Lỗi đọc latest.json: ' + e; return kq; }
}

/* ===== HÀNG ĐỢI LỆNH CỦA MỘT MÁY — XEM VÀ XOÁ LỆNH KẸT ==================================
 * Anh Thắng 03/08/2026: máy mới ở FZ_SC_VIVO_T4 *"chỉ đồng bộ 2 ngày, xong giờ đồng bộ ngày hay
 * tháng đều không lên nữa, có cần xóa fiber lệnh không"*.
 *
 * 🔴 Vì sao hàng đợi KẸT ĐƯỢC, và vì sao kẹt một lệnh là chết cả hàng:
 *   · firmware lấy lệnh bằng `orderBy="$key"&limitToFirst=1` (.ino:1505) -> CHỈ đọc lệnh ĐẦU;
 *   · `.ino:1707`  if (hasPhoto && ESP.getFreeHeap() < MIN_HEAP_FOR_PHOTO) return;
 *     -> lệnh CÓ ẢNH mà RAM < 120 KB thì KHÔNG xoá, hẹn vòng sau. RAM không bao giờ đủ thì lệnh
 *     đó nằm mãi ở đầu hàng;
 *   · lệnh Tải lại cố tình mang tiền tố `op-zbf-` để xếp CUỐI, nên một lệnh thường bị kẹt là
 *     chặn sạch mọi lệnh tải lại phía sau.
 * Trước đây web KHÔNG có đường nào xoá lệnh kẹt — phải mở Firebase console. Nay xoá tại đây.
 *
 * ⚠️ Xoá lệnh là mất lệnh đó, KHÔNG mất dữ liệu chấm công: chấm công đi đường khác (doPost trực
 *    tiếp), không qua hàng đợi. Lệnh thêm/sửa NV bị xoá thì lưu lại hồ sơ là nó xếp lại.
 */
function getQueueMay(pin, station){
  var u = _requireAuth(pin);
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Thiếu tên cơ sở.' };
  if (!u.isAdmin && !u.all && !_canStation(u, station))
    return { ok:false, error:'Không có quyền cơ sở này.' };
  var q;
  try { q = _fbGet('/queue/' + station); }
  catch (e) { return { ok:false, error:'Không đọc được hàng đợi: ' + e }; }
  if (q === undefined) return { ok:false, error:'Không đọc được hàng đợi (kiểm FB_SECRET).' };
  /* Sắp theo KHOÁ — đúng thứ tự firmware sẽ chạy (`orderBy="$key"`). Sắp theo thời gian tạo là
     nói dối về thứ tự: lệnh `op-zbf-` tạo trước vẫn chạy sau mọi lệnh `op-<hex>`. */
  var keys = Object.keys(q || {}).sort();
  /* Đọc heartbeat MỘT lần trước vòng lặp — trong vòng thì mỗi dòng lại gọi `_hbTatCa()` là mỗi
     dòng một lượt đọc Firebase. */
  var _qmHb = _mayDangBu(station);
  var _qmDangBu = _qmHb.dangBu;
  var ds = keys.map(function(k, i){
    var o = q[k] || {};
    return { opId:k, thuTu:i + 1,
             action:String(o.action || ''), maNV:String(o.employeeNo || ''),
             ten:String(o.name || ''), coAnh:!!o.hasPhoto,
             tuNgay:String(o.startTime || '').slice(0, 10), denNgay:String(o.endTime || '').slice(0, 10),
             taoLuc:String(o.createdAt || ''),
             /* 🔴 Phân loại theo ACTION, không theo tiền tố khoá. Bản đầu chỉ nhận `op-zbf-` là
                tải lại, nhưng lệnh tải lại MỘT ngày (`_raLenhBackfill`) dùng khoá `op-<hex>` —
                nên nó bị xếp là "lệnh thường", màn hình ẩn mất khoảng ngày và hiện "—", nhìn như
                lệnh rỗng. Tiền tố `op-zbf-` chỉ dùng để biết lệnh đó XẾP CUỐI hàng. */
             laTaiLai:(String(o.action || '') === 'backfill'),
             xepCuoi:(k.indexOf('op-zbf-') === 0),
             // Lệnh tải lại của ngày CHƯA TỚI -> vô ích, máy vẫn mất mấy phút đi hỏi đầu đọc.
             chuaToi:(String(o.action || '') === 'backfill'
                      && /^\d{4}-\d{2}-\d{2}$/.test(String(o.startTime || '').slice(0, 10))
                      && String(o.startTime).slice(0, 10) > _bfHomNay()),
             phutCho:_phutTuLuc(o.createdAt),
             /* Nghi kẹt = lệnh ĐẦU hàng, và một trong hai:
                  · CÓ ẢNH  -> đúng điều kiện `return` không ack ở .ino:1707 (RAM thiếu);
                  · CHỜ QUÁ LÂU -> máy poll 10 giây/lần, và lệnh tải lại được XOÁ TRƯỚC khi chạy
                    (.ino:1691) nên một lệnh tải lại còn nằm ở đầu hàng sau 5 phút là máy CHƯA
                    HỀ rút nó, không phải "đang chạy lâu".
                🔴 Thêm nhánh "chờ quá lâu" sau ca 03/08: 3 lệnh nằm im, không lệnh nào có ảnh,
                   nên bản cũ báo `nghiKet=false` — màn hình im lặng đúng lúc cần nói to nhất.
                Chỉ "nghi", không kết luận: máy mất mạng thì lệnh đầu cũng nằm đó mà không kẹt —
                nên giao diện phải đối chiếu với mốc online của máy. */
             /* 🔴 07/08: máy ĐANG chạy lượt bù thì hàng đợi đứng im là đúng (vòng bù CHẶN
                `loop()`), không được gọi là kẹt. Xem ghi chú dài ở `getHangDoiTaiLai`. */
             nghiKet:(i === 0 && !_qmDangBu && (!!o.hasPhoto || _phutTuLuc(o.createdAt) >= LENH_DO_PHUT)) };
  });
  return { ok:true, station:station, tong:ds.length, homNay:_bfHomNay(),
           doPhut:LENH_DO_PHUT,
           dauHang:(ds[0] ? ds[0].opId : ''), dauHangPhut:(ds[0] ? ds[0].phutCho : -1),
           dangBu:_qmDangBu, buDaDay:(_qmHb ? _qmHb.buDaDay : null), buNgay:(_qmHb ? _qmHb.buNgay : ''),
           nghiDo:!!(ds[0] && ds[0].nghiKet),
           soTaiLai:ds.filter(function(x){ return x.laTaiLai; }).length,
           soThuong:ds.filter(function(x){ return !x.laTaiLai; }).length,
           soChuaToi:ds.filter(function(x){ return x.chuaToi; }).length,
           list:ds.slice(0, 60), catBot:Math.max(0, ds.length - 60) };
}

/* Dọn các lệnh TẢI LẠI của ngày CHƯA TỚI.
 * Sinh ra vì lỗi cũ: chọn "Tháng 08" lúc mới ngày 03/08 là xếp 28 lệnh cho ngày chưa xảy ra
 * (`_bfNgayList` nay đã cắt ngọn, nhưng lệnh đã nằm trong hàng đợi thì phải có đường dọn).
 * ⚠️ CHỈ xoá lệnh `action='backfill'` có ngày > hôm nay. KHÔNG đụng lệnh thêm/xoá nhân viên,
 *    không đụng lệnh tải lại của ngày đã qua — đó là việc thật đang chờ. */
function xoaLenhTaiLai(pin, station, pham){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền dọn lệnh máy.' };
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Thiếu tên cơ sở.' };
  if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Không có quyền cơ sở này.' };
  /* Chỉ nhận đúng hai phạm vi. Giá trị lạ -> về 'chua-toi' (hẹp hơn), đừng mặc định thành xoá
     nhiều hơn ý người bấm. */
  pham = (String(pham || '') === 'tat-ca') ? 'tat-ca' : 'chua-toi';
  var q;
  try { q = _fbGet('/queue/' + station); }
  catch (e) { return { ok:false, error:'Không đọc được hàng đợi: ' + e }; }
  if (q === undefined) return { ok:false, error:'Không đọc được hàng đợi (kiểm FB_SECRET).' };
  var hnay = _bfHomNay();
  var xoa = 0, hong = 0, ngay = [];
  Object.keys(q || {}).forEach(function(k){
    var o = q[k] || {};
    /* 🔴 CHỈ lệnh tải lại. Lệnh thêm/xoá nhân viên và lệnh quét máy là việc THẬT đang chờ — xoá
       chúng là người đó không lên được máy, hoặc mất luôn bảng danh sách vừa yêu cầu. */
    if (String(o.action || '') !== 'backfill') return;
    var d = String(o.startTime || '').slice(0, 10);
    if (pham === 'chua-toi'){
      if (!/^\d{4}-\d{2}-\d{2}$/.test(d) || d <= hnay) return;
    }
    if (_fbDelete('/queue/' + station + '/' + k)) { xoa++; if (d) ngay.push(d); } else hong++;
  });
  ngay.sort();
  return { ok:true, station:station, pham:pham, xoa:xoa, hong:hong, homNay:hnay,
           ngayDau:(ngay[0] || ''), ngayCuoi:(ngay[ngay.length - 1] || '') };
}
/** Giữ tên cũ cho khỏi hụt chỗ gọi nào — chỉ là `xoaLenhTaiLai` với phạm vi hẹp. */
function xoaLenhTuongLai(pin, station){ return xoaLenhTaiLai(pin, station, 'chua-toi'); }

/* ===========================================================================
 *  DỪNG ĐỒNG BỘ — kể cả lượt ĐANG CHẠY và lượt CHẠY NGẦM
 * ---------------------------------------------------------------------------
 *  🔴 07/08/2026, anh Thắng: *"Đồng bộ anh thấy vẫn chạy, dù ngắt nó vẫn chạy ngầm, không ngăn
 *  cản được, dẫn đến tình trạng nghẽn"*.
 *
 *  Anh nói đúng. Xoá lệnh trong hàng đợi CHỈ chặn được lệnh máy CHƯA rút. Ba đường còn lại thì
 *  không đường nào chặn được:
 *    1. Lệnh máy ĐÃ rút — firmware xoá khỏi Firebase TRƯỚC khi chạy (.ino ~1825), nên lúc anh
 *       bấm xoá thì trong hàng đợi chẳng còn gì để xoá, mà máy vẫn đang chạy nó.
 *    2. Lượt bù ĐỊNH KỲ 30 phút (.ino:3115) — máy tự chạy, KHÔNG có lệnh nào trong hàng đợi.
 *    3. Lượt bù lúc KHỞI ĐỘNG (.ino:3043) — cũng vậy.
 *  Và vì `backfillRange` là vòng CHẶN, suốt lúc đó `loop()` không quay: máy không hỏi hàng đợi,
 *  không gửi heartbeat -> lệnh mới nằm chết ở đầu hàng, web đọc ra "ĐƠ". Đó là cái NGHẼN.
 *
 *  Nay đặt cờ `/stop/<trạm>`; firmware ≥ 2026-08-07a liếc cờ này mỗi trang (20 lượt) và thoát
 *  ngay. Ngắt được cả ba đường trên.
 *
 *  ⚠️ Cờ phải được XOÁ, không thì mọi lượt bù về sau chết câm. Hai bên cùng xoá: máy xoá khi nó
 *     dừng, web xoá mỗi lần ra lệnh tải lại mới (`_bfXoaCoDung` trong `_raLenhBackfill*`).
 *  ⚠️ Máy chạy firmware CŨ thì cờ này nó không đọc — phải NÓI RA, đừng để anh tưởng đã ngắt.
 * =========================================================================== */
/* Máy CÓ đang chạy lượt bù không — đọc heartbeat của ĐÚNG một trạm.
   ⚠️ KHÔNG dùng `_hbTatCa()` ở đây: hàm đó đọc `/hb` của MỌI cửa hàng cộng thêm một vòng liệt kê
      sheet. Dải tiến độ hỏi lại 30 giây/lần, nên đặt cái đó vào đây là tự làm chậm app — đúng
      thứ vừa mất công đi sửa (việc "Web Chấm công đang lag"). Một khoá nhỏ là đủ. */
function _mayDangBu(station){
  var h = null;
  try { h = _fbGet('/hb/' + station); } catch (e) { return { dangBu:false, buDaDay:null, buNgay:'' }; }
  if (!h) return { dangBu:false, buDaDay:null, buNgay:'' };
  return { dangBu:(Number(h.dangBu || 0) === 1),          // firmware cũ không gửi -> false, không đoán
           buDaDay:(h.buDaDay === undefined ? null : Number(h.buDaDay)),
           buNgay:String(h.buNgay || ''),
           fw:String(h.fw || '') };
}
function _bfDatCoDung(station, ai){
  return _fbPut('/stop/' + station, { t:_now(), ai:String(ai || '') });
}
function _bfXoaCoDung(station){
  try { return _fbDelete('/stop/' + station); } catch (e) { return false; }
}
var FW_CO_DUNG = '2026-08-07a';   // bản firmware đầu tiên biết đọc cờ /stop
function dungTaiLai(pin, station){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền dừng đồng bộ.' };
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Thiếu tên cơ sở.' };
  if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Không có quyền cơ sở này.' };

  /* Đặt cờ TRƯỚC khi xoá hàng đợi. Ngược lại thì giữa hai bước máy kịp rút thêm một lệnh nữa —
     xoá xong mà nó vẫn chạy, đúng thứ anh Thắng đang phàn nàn. */
  var datCo = _bfDatCoDung(station, u.name || '');
  var xoa = xoaLenhTaiLai(pin, station, 'tat-ca');
  /* Đường CŨ `cmd_<trạm>`: firmware hiện không đọc, nhưng còn ô nhớ là còn một lệnh tải lại nằm
     chờ. Dừng mà để lại thì không gọi là dừng. */
  var xoaCmd = false;
  try { PropertiesService.getScriptProperties().deleteProperty('cmd_' + station); xoaCmd = true; } catch (e) {}

  /* Máy có đọc được cờ không — nói theo bản firmware nó đang chạy, đừng hứa suông. */
  var hb = _mayDangBu(station);
  var fw = String(hb.fw || '');
  var fwHieu = !!(fw && fw.slice(0, 10) >= FW_CO_DUNG.slice(0, 10));
  return { ok:!!datCo, station:station,
           datCo:!!datCo, xoaLenh:(xoa && xoa.ok ? xoa.xoa : 0), xoaCmd:xoaCmd,
           fw:fw, fwHieuCoDung:fwHieu, fwCanBan:FW_CO_DUNG,
           dangBu:!!(hb && hb.dangBu),
           error: datCo ? '' : 'Không đặt được cờ dừng lên Firebase (kiểm FB_SECRET) — lượt bù đang chạy sẽ KHÔNG dừng.' };
}
/** Còn cờ dừng nào treo không — để web cảnh báo, kẻo lệnh tải lại sau bị giết ngay. CHỈ ĐỌC. */
function xemCoDung(pin, station){
  var u = _requireAuth(pin);
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Thiếu tên cơ sở.' };
  if (!u.isAdmin && !u.all && !_canStation(u, station)) return { ok:false, error:'Không có quyền cơ sở này.' };
  var c; try { c = _fbGet('/stop/' + station); } catch (e) { return { ok:false, error:'Không đọc được: ' + e }; }
  if (c === undefined) return { ok:false, error:'Không đọc được cờ dừng (kiểm FB_SECRET).' };
  return { ok:true, station:station, co:!!c, datLuc:(c && c.t) || '', ai:(c && c.ai) || '' };
}

/** Xoá MỘT lệnh khỏi hàng đợi. opId='' -> không làm gì (đừng để xoá cả nhánh vì tham số rỗng). */
function xoaLenhQueue(pin, station, opId){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền xoá lệnh máy.' };
  station = String(station || '').replace(/^CS_/, '').trim();
  opId = String(opId || '').trim();
  if (!station) return { ok:false, error:'Thiếu tên cơ sở.' };
  /* 🔴 CHẶN opId rỗng và opId có '/'. `_fbDelete('/queue/POSH_HCM/')` là xoá SẠCH hàng đợi của
     cửa hàng đó — một tham số rỗng lọt qua là mất hết lệnh đang chờ. */
  if (!opId) return { ok:false, error:'Thiếu mã lệnh (opId).' };
  if (opId.indexOf('/') >= 0) return { ok:false, error:'Mã lệnh không hợp lệ.' };
  if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Không có quyền cơ sở này.' };
  if (!_fbDelete('/queue/' + station + '/' + opId))
    return { ok:false, error:'Xoá trên Firebase THẤT BẠI — lệnh vẫn còn. Xem last_fb_err.' };
  /* Dòng tương ứng trong sheet `Queue` đang ở 'pending' -> đánh dấu 'huy'. Không làm thì cột
     "Trên máy chấm công" của người đó chờ mãi một lệnh không còn tồn tại. */
  var danhDau = false;
  try {
    var sh = _sheet(SH_QUEUE);
    if (sh) {
      var r = _findRow(sh, 1, opId);
      if (r && String(r.data[7] || '') === 'pending') {
        sh.getRange(r.row, 8).setValue('huy');
        sh.getRange(r.row, 10).setValue('Người dùng xoá lệnh kẹt @' + _now());
        danhDau = true;
      }
    }
  } catch (e) {}
  return { ok:true, opId:opId, station:station, danhDauQueue:danhDau };
}

// ===== OTA TỪ XA: đặt / xem phiên bản firmware đích (lưu Firebase /ota; ESP tự tải) =====
function setOtaTarget(pin, ver, url){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin được đẩy cập nhật firmware.' };
  ver = String(ver || '').trim(); url = String(url || '').trim();
  if (!ver || !url) return { ok:false, error:'Thiếu phiên bản hoặc link .bin' };
  if (!_fbPut('/ota', { ver:ver, url:url, by:(u.name||''), at:_now() }))
    return { ok:false, error:'Ghi lệnh cập nhật lên Firebase THẤT BẠI — máy sẽ không nhận được. Xem last_fb_err.' };
  return { ok:true };
}
function getOtaTarget(pin){
  _requireAuth(pin);
  var o = null; try { o = _fbGet('/ota'); } catch(e){}
  return o || {};
}
// Gỡ lệnh OTA (không đẩy cập nhật nữa)
function clearOtaTarget(pin){
  var u = _requireAuth(pin); if (!u.isAdmin) return { ok:false, error:'Chỉ Admin' };
  try { _fbPut('/ota', { ver:'', url:'' }); } catch(e){ return { ok:false, error:String(e) }; }
  return { ok:true };
}

// Danh sách cửa hàng người dùng được phép chọn (cho dropdown form)
function getMyStations(pin) {
  var u = _requireAuth(pin);
  var names = SpreadsheetApp.getActiveSpreadsheet().getSheets()
    .map(function(s){ return s.getName(); })
    .filter(function(n){ return n.indexOf('CS_') === 0; })
    .map(function(n){ return n.replace(/^CS_/, ''); });
  if (u.isAdmin || u.all) return names;
  return names.filter(function(n){ return _canStation(u, n); });
}

/**
 * Bảo đảm nhân viên CÓ dòng trong sheet cửa hàng `CS_<station>` (cột A = Họ và Tên, cột B = ID).
 *
 * ⚠️ Vì sao phải có hàm này: trước bản này, thêm nhân viên chỉ ghi vào sheet `NhanVien` rồi đẩy
 * lệnh xuống máy chấm công. Dòng ở sheet cửa hàng CHỈ sinh ra khi có LƯỢT CHẤM CÔNG ĐẦU TIÊN
 * (doPost -> findOrCreateEmpRow). Nên vừa thêm xong, mở sheet cửa hàng KHÔNG thấy người đó —
 * trông y như thêm thất bại, dù máy đã nhận. Nay thêm là thấy ngay.
 *
 * Dùng lại ĐÚNG findOrCreateEmpRow của doPost, không viết quy tắc tìm dòng thứ hai — hai quy tắc
 * lệch nhau là sinh 2 dòng cho cùng 1 người, tách đôi chấm công của họ.
 */
function _ganDongCuaHang(station, empNo, name, daNghiViec) {
  station = String(station || '').replace(/^CS_/, '').trim();
  empNo   = String(empNo || '').trim();
  if (!station || !empNo) return { ok:false, ly:'thiếu cửa hàng hoặc mã NV' };
  var sh = _sheet('CS_' + station);
  // KHÔNG tự tạo sheet cửa hàng ở đây: tên cửa hàng do người dùng chọn từ danh sách sẵn có,
  // chưa có sheet nghĩa là có gì sai — tạo bừa là sinh cửa hàng ma (đúng bài học _giaiMaTram).
  if (!sh) return { ok:false, ly:'chưa có sheet CS_' + station };
  try {
    // Thêm nhân viên giữa tháng -> dòng phải nằm ở khối của THÁNG HIỆN TẠI, không phải tháng cũ.
    var homNay = Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd');
    var khoi   = _csKhoiChoNgay(sh, homNay);
    // Người đã nghỉ việc: KHÔNG tạo dòng mới. Dòng cũ (nếu có) vẫn để nguyên cho tháng này.
    if (daNghiViec) {
      var coRoi = false;
      if (khoi.r2 >= khoi.r1) {
        var _ids = sh.getRange(khoi.r1, 2, khoi.r2 - khoi.r1 + 1, 1).getValues();
        for (var _i = 0; _i < _ids.length; _i++) if (String(_ids[_i][0]) === empNo) { coRoi = true; break; }
      }
      if (!coRoi) return { ok:false, ly:'đã nghỉ việc -> không tạo dòng mới', nghiViec:true };
    }
    var truoc  = khoi.r2;
    var row    = findOrCreateEmpRow(sh, khoi, empNo, name);
    var taoMoi = (row > truoc);
    var doiTen = false;
    if (!taoMoi && String(name || '').trim()) {
      var cu = String(sh.getRange(row, 1).getValue() || '').trim();
      if (cu !== String(name).trim()) { sh.getRange(row, 1).setValue(name); doiTen = true; }
    }
    return { ok:true, row:row, taoMoi:taoMoi, doiTen:doiTen };
  } catch (e) {
    return { ok:false, ly:String(e) };
  }
}

// Thêm/sửa NV (hồ sơ đầy đủ). obj = {employeeNo, name, station, machinePin, photoDataUrl, mode,
//   phone,dob,gender,cccd,address,emgName,emgPhone, position,startDate,workStatus,contractType,
//   baseSalary,bankAccount,bankName, cccdDataUrl,contractDataUrl}
function upsertEmployee(pin, obj) {
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền sửa hồ sơ nhân viên.' };
  if (!obj || !obj.employeeNo) return { ok:false, error:'Thiếu Mã NV' };
  var empNo   = String(obj.employeeNo).trim();
  var name    = String(obj.name || '').trim();
  var station = String(obj.station || '').trim();
  /* 🔴 09/08/2026 — BỎ Ô NHẬP "PIN máy", GIỮ NGUYÊN CỘT VÀ DỮ LIỆU.
   *  Anh Thắng: *"bỏ pin trong máy cho tránh đè nhầm"* (chuỗi chấm công bằng KHUÔN MẶT; PIN máy
   *  chỉ đi xuống đầu đọc Hikvision làm trường `password` — mã bấm phím thay cho khuôn mặt).
   *  ⚠️ Client nay KHÔNG gửi `machinePin` nữa. `undefined` phải hiểu là **KHÔNG ĐỔI**, không phải
   *     rỗng: coi như '' thì mỗi lần sửa số điện thoại là XOÁ TRẮNG PIN máy của người đó, và
   *     `_nvcsGhiPin` bên dưới ghi luôn số rỗng ấy sang sheet `NV_` — mất cả bản trong sheet.
   *     Đúng cái "đè nhầm" anh muốn tránh, chỉ khác là do máy chủ chứ không do tay ai. */
  var _mpinGui = (obj.machinePin === undefined || obj.machinePin === null)
                   ? null : String(obj.machinePin).trim();
  var mpin    = _mpinGui || '';
  var mode    = (obj.mode === 'edit') ? 'edit' : 'add';
  if (!station) return { ok:false, error:'Chưa chọn cửa hàng' };
  /* 🔴 Chặn HÀNG TIÊU ĐỀ trở thành hồ sơ. Anh Thắng đã có đúng một dòng "Mã = ID · Họ tên =
     HỌ VÀ TÊN" nằm trong `NhanVien`, sinh ra hồi quét sheet còn vớ nhầm hàng tiêu đề. Đã sửa chỗ
     đọc, nhưng phải chặn cả ở chỗ GHI: đây là cửa cuối trước khi rác nằm vĩnh viễn trong sheet. */
  if (_nvcsLaTieuDe(empNo, name))
    return { ok:false, error:'"' + empNo + ' / ' + name + '" là HÀNG TIÊU ĐỀ của sheet, không phải nhân viên.' };
  if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Bạn không quản lý cửa hàng này' };
  /* 🔴 07/08/2026 — KIỂM CẢ CỬA HÀNG CŨ, không chỉ cửa hàng mới.
     Từ hôm nay Cửa hàng trưởng sửa được hồ sơ, và chốt ngay trên chỉ xét cửa hàng ĐANG GỬI LÊN.
     Nghĩa là CHT của A mở hồ sơ một người đang ở cửa hàng B, đổi ô cửa hàng thành A rồi lưu —
     chốt kia thấy "A, hợp lệ" và cho qua. Người của B bị KÉO SANG A, mà quản lý B không hay biết.
     Chuyển người giữa hai cửa hàng là việc vượt phạm vi một cửa hàng -> để Admin / Quản lý làm.

     ⚠️ Chốt theo HỒ SƠ CÓ THẬT TRONG SHEET, tuyệt đối KHÔNG theo `obj.mode`. `mode` là cờ do
     CLIENT gửi lên: bản đầu em viết `mode === 'edit'` thì chỉ cần gửi thiếu cờ đó là chốt không
     chạy, mà hàm vẫn ghi đè hồ sơ cũ như thường — đi vòng qua bằng đúng một dòng. Bảng ma trận
     quyền bắt được, không phải em đọc lại mà thấy. Hồ sơ chưa có thì `_csCu` rỗng, cả hai chốt
     tự bỏ qua — thêm người mới không bị vướng. */
  if (!u.isAdmin) {
    var _cu = _findRow(_nvSheet(), 1, empNo);
    var _csCu = _cu ? String(_cu.data[2] || '').replace(/^CS_/, '').trim() : '';
    /* 🔴 TẠO HỒ SƠ MỚI vẫn là việc MỨC 2, dù cửa hàng trưởng đã sửa được hồ sơ.
       Mã NV là định danh dùng chung CẢ CHUỖI: mỗi cửa hàng tự đặt mã là sớm muộn trùng mã, mà
       trùng mã thì hai người dùng chung một hàng chấm công — hỏng lương, gỡ rất lâu. Anh Thắng
       cũng đã chốt luồng riêng cho việc này: cửa hàng trưởng GỬI YÊU CẦU, Admin gán mã rồi duyệt
       (`themYeuCauNV` / `duyetYeuCauNV`). Mở chỗ này là bỏ luồng đó.
       ⚠️ Xét bằng HỒ SƠ CÓ TRONG SHEET (`_cu`), không bằng `obj.mode` — cùng bài học ở dưới. */
    if (!_cu && !_canQuanTriNV(u))
      return { ok:false, error:'Tạo hồ sơ mới cần cấp Mã NV — mã dùng chung cả chuỗi nên ' + _QT_LOI
             + ' Bạn gửi Yêu cầu thêm nhân viên, Admin gán mã rồi duyệt.' };
    if (_csCu && !_canStation(u, _csCu))
      return { ok:false, error:'Hồ sơ này đang thuộc cửa hàng "' + _csCu + '" — bạn không quản lý '
             + 'cửa hàng đó nên không sửa được.' };
    if (_csCu && _khongDau(_csCu) !== _khongDau(station) && !_canQuanTriNV(u))
      return { ok:false, error:'Chuyển người từ "' + _csCu + '" sang "' + station + '" là việc giữa '
             + 'hai cửa hàng — ' + _QT_LOI + ' Cần người này làm thêm ở cửa hàng bạn thì dùng '
             + 'Cửa hàng làm việc (tăng cường), không đổi cửa hàng gốc.' };
  }
  var canSal = _canSalary(u);
  /* Nhiệm vụ: chỉ nhận ô trống hoặc đúng một mục trong danh sách. Gõ sai chính tả là người đó rơi
     khỏi mọi phép lọc/tính tiền mà chẳng có gì báo -> chặn tại đây, đừng để lọt vào Sheet.
     ⚠️ PHÂN BIỆT "không gửi" với "gửi ô trống". Mọi trường khác đều theo luật: client không gửi thì
        GIỮ NGUYÊN giá trị cũ. Nếu quy cả hai về chuỗi rỗng thì bất kỳ màn hình nào lưu hồ sơ mà
        không kèm nhiệm vụ sẽ XOÁ TRẮNG nhiệm vụ đã khai — mất cấu hình lương trong im lặng. */
  var _nvHopLe;                                   // undefined = không gửi -> giữ nguyên ô cũ
  if (obj.nhiemVu !== undefined && obj.nhiemVu !== null) {
    var _nvList = _chuanNhiemVuList(obj.nhiemVu);
    if (_nvList === null) return { ok:false, error:'Nhiệm vụ không hợp lệ. Chọn: ' + NHIEM_VU_DS.join(' / ') + ' hoặc để trống (mặc định ' + NHIEM_VU_MAC_DINH + ').' };
    /* Nhiệm vụ là khái niệm của NHÓM MÁY TỰ ĐỘNG (Posh, JP) — anh Thắng chốt 03/08. Cửa hàng khác
       khai vào là vô nghĩa mà lại làm tách hàng trong sheet cơ sở đó. Chặn tại đây, KHÔNG âm thầm
       bỏ qua: bỏ qua thì người khai tưởng đã lưu được. */
    if (_nvList.length && !_laMayTuDong(station))
      return { ok:false, error:'Nhiệm vụ chỉ áp dụng cho Nhóm Máy Tự Động (Posh, JP). Cửa hàng "' + station + '" không thuộc nhóm này.' };
    _nvHopLe = _nhiemVuChuoi(_nvList);
  }

  var lock = LockService.getScriptLock();
  try { lock.waitLock(15000); } catch (le) {}
  try {
    var sh = _nvSheet();
    var found = _findRow(sh, 1, empNo);
    var prev = found ? found.data : [];

    var photoFileId = '';
    var hasNewPhoto = obj.photoDataUrl && String(obj.photoDataUrl).indexOf('base64,') >= 0;
    if (hasNewPhoto) photoFileId = _saveNvPhoto(empNo, obj.photoDataUrl);
    else if (found) photoFileId = String(prev[4] || '');

    // File hồ sơ: có file mới thì lưu Drive, không thì giữ cũ
    var cccdFileId = (obj.cccdDataUrl && String(obj.cccdDataUrl).indexOf('base64,') >= 0)
      ? _saveNvFile(empNo, obj.cccdDataUrl, 'cccd') : String(prev[21] || '');
    var contractFileId = (obj.contractDataUrl && String(obj.contractDataUrl).indexOf('base64,') >= 0)
      ? _saveNvFile(empNo, obj.contractDataUrl, 'hopdong') : String(prev[22] || '');

    /* Ô "Đồng bộ xuống máy chấm công". Anh Thắng: *"có chỗ có máy chấm công và không, đẩy vào dễ
       dư lệnh, thì mình bổ sung ô (bổ sung máy chấm công)"*. Cơ sở không có máy mà vẫn xếp lệnh
       thì lệnh nằm mãi trong `Queue` ở trạng thái chờ, kéo theo trạng thái đồng bộ đỏ giả.
       ⚠️ KHÔNG gửi cờ = ĐẨY (đúng hành vi cũ). Mặc định thành "không đẩy" là mọi màn hình cũ
          lặng lẽ ngừng đồng bộ máy — hỏng mà chẳng có gì báo. */
    var dongBoMay = (obj.dongBoMay === undefined || obj.dongBoMay === null) ? true : !!obj.dongBoMay;
    /* 🔴 06/08/2026 — KHÔNG đẩy máy thì GIỮ NGUYÊN trạng thái đồng bộ cũ, đừng ghi đè.
       Từ hôm nay ô "Đẩy hồ sơ xuống máy chấm công" mặc định BỎ TRỐNG (anh Thắng: *"mặc định
       dấu tích máy chấm công là để trống, chứ đừng tích sẵn rất dễ lỗi"*). Nếu vẫn ghi đè
       'khong-day-may' như trước thì chỉ sửa một số điện thoại là người ĐANG CÓ trên máy bị
       đánh dấu "cố ý không đẩy xuống máy" — bảng nói sai về thực tế của cái máy.
       Không đẩy = KHÔNG có gì thay đổi ở máy, nên trạng thái cũ vẫn đúng. Chỉ người MỚI
       (chưa có dòng) mới ghi 'khong-day-may': họ thật sự chưa hề lên máy. */
    var _ttCu = found ? String(prev[5] || '').trim() : '';
    var _tt = dongBoMay ? 'pending' : (_ttCu || 'khong-day-may');
    /* Không gửi PIN máy -> lấy lại đúng số đang có trong hồ sơ. Phải đặt Ở ĐÂY chứ không đặt lúc
       đọc `obj`: `prev` chỉ có sau khi tìm được hàng, mà tìm hàng thì nằm trong khoá. */
    if (_mpinGui === null) mpin = found ? String(prev[3] || '').trim() : '';
    var row = [empNo, name, station, mpin, photoFileId, _tt, _now()];
    // cột 8..23 theo NV_EXTRA
    var extraVal = {
      phone:obj.phone, dob:obj.dob, gender:obj.gender, cccd:obj.cccd, address:obj.address,
      emgName:obj.emgName, emgPhone:obj.emgPhone, position:obj.position, startDate:obj.startDate,
      workStatus:obj.workStatus, contractType:obj.contractType,
      baseSalary:obj.baseSalary, bankAccount:obj.bankAccount, bankName:obj.bankName,
      cccdFileId:cccdFileId, contractFileId:contractFileId,
      nhiemVu:_nvHopLe
    };
    for (var k = 0; k < NV_EXTRA.length; k++) {
      var key = NV_EXTRA[k];
      var val;
      if (key === 'cccdFileId') val = cccdFileId;
      else if (key === 'contractFileId') val = contractFileId;
      else if (NV_SENSITIVE[key] && !canSal) val = String(prev[7 + k] || '');   // CHT không sửa được lương -> giữ cũ (phòng hờ)
      else val = (extraVal[key] === undefined || extraVal[key] === null) ? String(prev[7 + k] || '') : String(extraVal[key]);
      row.push(val);
    }
    if (found) sh.getRange(found.row, 1, 1, NV_HEADERS.length).setValues([row]);
    else sh.appendRow(row);

    // Dòng trong sheet cửa hàng: ghi NGAY, không đợi lượt chấm công đầu tiên.
    // Đặt TRƯỚC _enqueue để Firebase hỏng cũng không làm mất bước này.
    var _ds = _ganDongCuaHang(station, empNo, name, _laNghiViec(obj.workStatus));

    /* 🔴 06/08/2026 — TRƯỚC ĐÂY chỗ này TỰ THÊM người vào `NV_<cơ sở>`. ĐÃ BỎ.
       Anh Thắng: *"sheet NV_ là cố định, chỉ có từ nó đi ra thôi"*. Nay chỉ ĐỌC để BÁO: người vừa
       lưu hồ sơ mà chưa có tên trong `NV_X` thì sẽ KHÔNG hiện ở bảng nhân viên của cơ sở đó, phải
       nói ra ngay — im lặng là anh lưu xong không thấy người đâu, tưởng lưu hỏng.
       ⚠️ Chỉ đọc, và bọc try: sheet nhân sự lỗi KHÔNG được làm mất hồ sơ vừa lưu. */
    var _nvcs = null;
    try {
      var _dc = _nvcsDoc(station);
      if (!_dc.co) _nvcs = { thieuSheet:true };
      else if (_dc.loi) _nvcs = { loiCot:_dc.loi };
      else _nvcs = { coTen:_dc.list.some(function(x){ return _chuanMa(x.ma) === _chuanMa(empNo); }) };
    } catch (e2) { _nvcs = { loi:String(e2) }; }

    var photoB64 = hasNewPhoto ? String(obj.photoDataUrl).split('base64,')[1].replace(/\+/g,'-').replace(/\//g,'_') : '';
    var opId = dongBoMay
      ? _enqueue(mode, empNo, name, mpin, hasNewPhoto ? photoFileId : '', station, obj.gender, photoB64)
      : '';

    var ghiChu = '';
    if (!_ds.ok) ghiChu = '⚠️ CHƯA ghi được dòng vào sheet CS_' + station + ' (' + _ds.ly +
                          '). Dòng sẽ tự sinh khi có lượt chấm công đầu tiên.';
    else if (_ds.taoMoi) ghiChu = 'Đã thêm dòng vào sheet CS_' + station + '.';
    else if (_ds.doiTen) ghiChu = 'Đã cập nhật tên trong sheet CS_' + station + '.';
    /* App KHÔNG ghi vào `NV_` nữa — chỉ nói rõ việc anh cần tự làm trong Sheet. */
    var _tenNvSheet = _nvcsTen(station);
    if (_nvcs && _nvcs.thieuSheet)
      ghiChu += ' ⚠️ Chưa có sheet ' + _tenNvSheet + ' — người này sẽ KHÔNG hiện ở bảng nhân viên'
              + ' của ' + station + ' cho tới khi sheet đó có tên họ.';
    else if (_nvcs && _nvcs.loiCot)
      ghiChu += ' ⚠️ Sheet ' + _tenNvSheet + ': ' + _nvcs.loiCot;
    else if (_nvcs && _nvcs.coTen === false)
      ghiChu += ' ⚠️ ' + empNo + ' CHƯA có trong ' + _tenNvSheet + ' — thêm một dòng vào sheet đó'
              + ' thì bảng nhân viên mới hiện (app không tự thêm: sheet nhân sự do anh giữ).';
    else if (_nvcs && _nvcs.loi)
      ghiChu += ' ⚠️ Chưa đọc được sheet nhân sự (' + _nvcs.loi + ').';
    if (!dongBoMay) ghiChu = 'KHÔNG đẩy xuống máy chấm công (đã bỏ tích). ' + ghiChu;
    /* 🔴 06/08/2026 — PIN MÁY ĐỔI MÀ KHÔNG ĐẨY thì phải NÓI RA.
       Từ hôm nay ô "Đẩy hồ sơ xuống máy" mặc định BỎ TRỐNG (anh Thắng: *"rất dễ lỗi"*). Với
       hầu hết trường sửa (số điện thoại, địa chỉ) thì không đẩy là đúng. Nhưng đổi PIN MÁY mà
       không đẩy thì hồ sơ ghi số mới còn ĐẦU ĐỌC VẪN GIỮ SỐ CŨ — nhân viên bấm số mới không vào
       được, mà nhìn sheet lại thấy đúng. Đây là kiểu sai im lặng tệ nhất, nên phải cảnh báo. */
    var _mpCu = found ? String(prev[3] || '').trim() : '';
    if (!dongBoMay && found && String(mpin || '').trim() && String(mpin).trim() !== _mpCu)
      ghiChu = '⚠️ PIN MÁY đổi ' + (_mpCu || '(trống)') + ' → ' + mpin
             + ' nhưng CHƯA đẩy xuống máy — đầu đọc vẫn dùng PIN cũ. '
             + 'Tích ô "Đẩy hồ sơ xuống máy chấm công" rồi Lưu lại. ' + ghiChu;
    /* Sửa trên web thì sheet cơ sở phải theo ngay — không thì bản sao lưu là số cũ.
       Bọc trong hàm riêng, lỗi ở đó KHÔNG làm hỏng việc lưu hồ sơ. */
    /* ⚠️ KHÔNG gửi PIN máy thì KHÔNG đụng vào sheet `NV_`. Ghi lại "số cũ" nghe có vẻ vô hại,
       nhưng số cũ trong hồ sơ và số trong `NV_` có thể khác nhau (anh sửa tay bên sheet) — ghi đè
       là lặng lẽ xoá mất bản anh vừa gõ. Không đổi thì không ghi. */
    if (_mpinGui !== null) _nvcsGhiPin(station, empNo, null, String(mpin || ''));
    var stCu = found ? String(prev[2] || '').replace(/^CS_/, '').trim() : '';
    if (stCu && stCu !== station) {
      ghiChu += ' Đổi cửa hàng: dòng cũ ở CS_' + stCu + ' GIỮ NGUYÊN để không mất chấm công đã có.';
    }
    return { ok:true, opId:opId, dongCuaHang:_ds, ghiChu:ghiChu };
  } catch (err) {
    return { ok:false, error:String(err) };
  } finally {
    lock.releaseLock();
  }
}
// Lưu file hồ sơ (ảnh/pdf) lên Drive, trả fileId
function _saveNvFile(empNo, dataUrl, tag) {
  var parts = String(dataUrl).split('base64,'); if (parts.length < 2) return '';
  var mime = (parts[0].match(/data:([^;]+)/) || [])[1] || 'application/octet-stream';
  var ext = mime.indexOf('pdf') >= 0 ? 'pdf' : (mime.indexOf('png') >= 0 ? 'png' : 'jpg');
  var bytes = Utilities.base64Decode(parts[1]);
  var name = 'nv_' + empNo + '_' + tag + '.' + ext;
  var folder = _nvDocFolder();
  var old = folder.getFilesByName(name); while (old.hasNext()) old.next().setTrashed(true);
  return folder.createFile(Utilities.newBlob(bytes, mime, name)).getId();
}
function _nvDocFolder() {
  var root = DriveApp.getFolderById(ROOT_FOLDER_ID);
  var it = root.getFoldersByName(NV_DOC_FOLDER);
  return it.hasNext() ? it.next() : root.createFolder(NV_DOC_FOLDER);
}

/* ===== ĐỔI MÃ NV =========================================================================
 * Anh Thắng 03/08/2026: *"Mã ID trên máy chấm công họ không cho sửa, chỉ có xóa đi. Vậy thì anh
 * có phương án như nhau này: khi bấm nút sửa sẽ hiện ra ô Nhập ID mới · hệ thống lưu thì sẽ xóa
 * nhân viên đó đi (để tránh sai nhân viên chấm công) · sau đó làm thêm 1 lệnh mới là tạo lại
 * nhân viên"*.
 *
 * Nghe như "đổi một ô", thực ra mã NV là KHOÁ dùng ở 4 chỗ. Bỏ sót chỗ nào là hỏng lương:
 *   1. `NhanVien` cột A                — hồ sơ. Bỏ sót -> đẻ hồ sơ thứ hai, một người hai mã.
 *   2. `CS_<cơ sở>` cột B, MỌI khối tháng — lịch sử chấm công. Bỏ sót -> lương tháng trước tra
 *      theo mã mới ra 0, mà hàng cũ vẫn nằm đó nên tổng cộng lại vẫn "trông đủ".
 *   3. Hàng nhiệm vụ thêm `<mã>-TT` / `<mã>-TG` — phải đổi kèm và GIỮ đuôi. Bỏ sót -> hàng công
 *      của Thu Tiền / Trực Ghế mồ côi.
 *   4. `PhanQuyen` cột `Mã NV`         — tài khoản web. Bỏ sót -> mất đường chấm công online.
 *
 * ⚠️ MÁY NHẬN DIỆN BẰNG ẢNH (`_enqueue` đẩy ảnh qua Firebase, không có vân tay). Xoá người khỏi
 *    máy là xoá luôn mặt đã đăng ký. Dựng lại được CHỈ KHI ảnh còn trong Drive. Ai chưa có ảnh
 *    trên web thì xoá đi là phải ra máy đăng ký tay -> CHẶN, không xoá rồi mới báo.
 * ⚠️ Xếp lệnh `delete` TRƯỚC `add`: `_empPending` trả hàng pending ĐẦU TIÊN nên thứ tự hàng đợi
 *    chính là thứ tự máy chạy. Ngược lại là tạo mã mới rồi xoá — máy có thể xoá cả người vừa tạo.
 * ⚠️ `delete` FAIL mà `add` xong thì máy còn CẢ HAI mã: mã cũ vẫn gửi giờ về, `findOrCreateEmpRow`
 *    lại đẻ hàng mới mang mã cũ. Không tự sửa được ở đây -> trả `opIdXoa` để màn hình theo dõi.
 */

/* Ô ảnh trong hồ sơ -> fileId Drive. Anh Thắng 03/08: *"trong sheet nhân viên sẽ có ô ảnh nhân đã
   gán sẵn, em lấy ảnh đó đưa vào máy chấm công luôn"*. Ô đó thường là id trần, nhưng cũng có thể
   là link Drive hoặc công thức `=IMAGE("…")` do dán tay — `getFileById` với chuỗi đó là văng lỗi,
   mà lỗi ở đây nghĩa là tạo lại người trên máy MÀ KHÔNG CÓ MẶT. Bóc id ra trước. */
function _anhFileId(v){
  var s = String(v == null ? '' : v).trim();
  if (!s) return '';
  var m = s.match(/[-\w]{25,}/);          // id Drive trong link / trong =IMAGE("…")
  return m ? m[0] : s;
}
/** Ảnh hồ sơ -> base64 cho Firebase. Không có / đọc không được -> '' (máy còn đường `?photo=`). */
function _anhB64(fileId){
  var id = _anhFileId(fileId); if (!id) return '';
  try {
    return Utilities.base64Encode(DriveApp.getFileById(id).getBlob().getBytes())
                    .replace(/\+/g, '-').replace(/\//g, '_');
  } catch (e) { return ''; }
}
/** Đuôi nhiệm vụ của một mã hàng phụ (`-TT` / `-TG`), '' nếu là mã trần. */
function _doiMaDuoi(ma, maGoc){
  var s = String(ma == null ? '' : ma).trim(), g = String(maGoc == null ? '' : maGoc).trim();
  if (_chuanMa(s) === _chuanMa(g)) return '';
  var t = _tachMaNhiemVu(s);
  return (t && _chuanMa(t.ma) === _chuanMa(g)) ? ('-' + t.duoi) : null;   // null = không liên quan
}

/**
 * KẾ HOẠCH đổi mã — MỘT định nghĩa duy nhất, dùng cho CẢ xem trước VÀ lúc ghi.
 * Hai hàm riêng là hai bản dễ lệch, mà lệch ở đây nghĩa là màn hình hứa một đằng ghi một nẻo.
 * KHÔNG ghi gì cả, chỉ đọc.
 *
 * 🔴 QUÉT TOÀN BỘ CHUỖI, không chỉ một cơ sở. Bản đầu chỉ quét `CS_<coSo>` + `NV_<coSo>` — SAI:
 *    mã NV là khoá của `NhanVien`, một sheet dùng chung cả chuỗi. Anh Thắng đã chốt *"1 nhân viên
 *    nằm ở 2 sheet nhân viên (cùng ID) thì mình tự hiểu là bạn làm ở 2 cửa hàng"*. Đổi mã ở POSH
 *    mà bỏ JP là lịch sử chấm công JP mồ côi mã cũ -> LƯƠNG BỊ CHIA ĐÔI trong im lặng.
 *    `coSo` giờ chỉ còn là cơ sở đang mở trên màn hình (dùng để gác quyền + làm nơi gửi lệnh máy
 *    khi người đó chưa có tên trong sheet `NV_` nào).
 */
function _doiMaKeHoach(maCu, maMoi, coSo){
  maCu  = String(maCu  || '').trim();
  maMoi = String(maMoi || '').trim();
  coSo  = String(coSo  || '').replace(/^CS_/, '').trim();
  var kh = { maCu:maCu, maMoi:maMoi, coSo:coSo, loi:'', canh:[],
             hoSo:null, cs:[], nvcs:[], pq:[], mayCoSo:[], coSoLienQuan:[] };
  if (!maCu || !maMoi) { kh.loi = 'Thiếu mã cũ hoặc mã mới.'; return kh; }
  if (_chuanMa(maCu) === _chuanMa(maMoi)) { kh.loi = 'Mã mới trùng mã cũ, không có gì để đổi.'; return kh; }
  if (!coSo) { kh.loi = 'Thiếu cửa hàng.'; return kh; }
  if (_tachMaNhiemVu(maMoi))
    { kh.loi = '"' + maMoi + '" là mã HÀNG NHIỆM VỤ THÊM, không dùng làm mã nhân viên.'; return kh; }
  if (_nvcsLaTieuDe(maMoi, ''))
    { kh.loi = '"' + maMoi + '" là chữ tiêu đề của sheet, không phải mã nhân viên.'; return kh; }

  // 1. Hồ sơ
  var shNv = _nvSheet();
  var f = _findRow(shNv, 1, maCu);
  if (!f) { kh.loi = 'Không có hồ sơ nào mang mã "' + maCu + '".'; return kh; }
  var d = _findRow(shNv, 1, maMoi);
  if (d && d.row !== f.row) {
    kh.loi = 'Mã "' + maMoi + '" đã là của ' + (String(d.data[1] || '') || 'một hồ sơ khác')
           + '. Đổi sang mã này là GỘP hai người thành một — không cho.';
    return kh;
  }
  kh.hoSo = { row:f.row, ten:String(f.data[1] || ''), coSoHoSo:String(f.data[2] || '').replace(/^CS_/, '').trim(),
              pinMay:String(f.data[3] || ''), anh:String(f.data[4] || ''), tt:String(f.data[5] || '') };
  if (!kh.hoSo.anh)
    kh.canh.push('Hồ sơ CHƯA CÓ ẢNH trên web. Xoá khỏi máy là mất khuôn mặt đã đăng ký và '
               + 'KHÔNG dựng lại được — người này phải ra máy đăng ký tay.');
  if (kh.hoSo.coSoHoSo && _chuanMa(kh.hoSo.coSoHoSo) !== _chuanMa(coSo))
    kh.canh.push('Hồ sơ ghi cửa hàng "' + kh.hoSo.coSoHoSo + '" chứ không phải "' + coSo + '".');

  var dsSheet = [];
  try { dsSheet = SpreadsheetApp.getActiveSpreadsheet().getSheets(); } catch (e) { dsSheet = []; }
  var lienQuan = {};

  // 2 + 3. MỌI sheet CS_, mọi khối tháng, kèm hàng nhiệm vụ thêm
  dsSheet.forEach(function(shCs){
    var ten = shCs.getName();
    if (ten.indexOf('CS_') !== 0) return;
    var kt = { tenSheet:ten, coSo:ten.slice(3), hang:[] };
    _csKhoi(shCs).forEach(function(k){
      if (!(k.r2 >= k.r1)) return;
      var v = shCs.getRange(k.r1, 1, k.r2 - k.r1 + 1, 2).getValues();
      for (var i = 0; i < v.length; i++){
        var maO = String(v[i][1] || '').trim(); if (!maO) continue;
        var duoi = _doiMaDuoi(maO, maCu);
        if (duoi === null) continue;                       // mã của người khác
        kt.hang.push({ row:k.r1 + i, maCu:maO, maMoi:maMoi + duoi,
                       ten:String(v[i][0] || ''), duoi:duoi });
      }
      // Mã MỚI đã có hàng riêng trong cùng khối -> đổi vào là HAI hàng cùng mã, giờ công cộng đôi.
      for (var j = 0; j < v.length; j++){
        var m2 = String(v[j][1] || '').trim(); if (!m2) continue;
        if (_doiMaDuoi(m2, maMoi) !== null)
          kh.canh.push('Sheet ' + ten + ' hàng ' + (k.r1 + j) + ' ĐÃ mang mã "' + m2
                     + '". Đổi vào là hai hàng cùng mã trong một tháng -> giờ công cộng đôi.');
      }
    });
    if (kt.hang.length) { kh.cs.push(kt); lienQuan[kt.coSo] = 1; }
  });

  // 4. MỌI sheet nhân sự cố định `NV_<cơ sở>`
  dsSheet.forEach(function(shN){
    var ten = shN.getName();
    if (ten.indexOf(NVCS_TIEN_TO) !== 0) return;
    var cot = _nvcsCot(shN); if (!cot.ma) return;
    var kt = { tenSheet:ten, coSo:ten.slice(NVCS_TIEN_TO.length), hang:[] };
    var lr = shN.getLastRow(), d1 = cot.hangTieuDe + 1;
    if (lr >= d1) {
      var vv = shN.getRange(d1, 1, lr - d1 + 1, Math.max(cot.ma, cot.ten || 1)).getValues();
      for (var q = 0; q < vv.length; q++){
        var mq = String(vv[q][cot.ma - 1] || '').trim(); if (!mq) continue;
        var du = _doiMaDuoi(mq, maCu);
        if (du === null) continue;
        kt.hang.push({ row:d1 + q, cot:cot.ma, maCu:mq, maMoi:maMoi + du });
      }
    }
    if (kt.hang.length) {
      kh.nvcs.push(kt); lienQuan[kt.coSo] = 1;
      kh.mayCoSo.push(kt.coSo);      // có tên trong NV_<cơ sở> = làm ở cơ sở đó -> phải sửa cả máy đó
    }
  });

  // 5. Tài khoản web trỏ vào mã cũ
  var shPq = _pqNoiCot(), lrp = shPq.getLastRow();
  if (lrp >= 2) {
    var vp = shPq.getRange(2, 1, lrp - 1, PQ_H.length).getValues();
    for (var p = 0; p < vp.length; p++){
      if (_chuanMa(vp[p][4]) !== _chuanMa(maCu)) continue;
      kh.pq.push({ row:p + 2, pin:String(vp[p][0] || ''), ten:String(vp[p][1] || '') });
    }
  }

  /* Nơi gửi lệnh máy: các cơ sở người đó CÓ TÊN trong `NV_`. Chưa có tên ở đâu cả thì lấy cơ sở
     đang mở trên màn hình — chứ không im lặng bỏ qua máy, vì lúc đó máy giữ mã cũ mãi. */
  if (!kh.mayCoSo.length) kh.mayCoSo = [coSo];
  lienQuan[coSo] = 1;
  kh.coSoLienQuan = Object.keys(lienQuan);
  return kh;
}

/* ===== YÊU CẦU THÊM NHÂN VIÊN: CHT gửi, Admin gán mã rồi duyệt =========================
 * Anh Thắng 03/08/2026: *"Cửa hàng trưởng sẽ gửi lệnh yêu cầu cập nhật nhân viên mới · trong tab
 * yêu cầu sẽ có thông tin như họ và tên, mã pin, ảnh khuôn mặt upload sẵn, cửa hàng, giới tính ·
 * xong thì bấm lưu. Lúc này bên nhân [viên] và phân quyền sẽ hiện thông báo bổ sung nhân viên của
 * cửa hàng đó · Admin sẽ vào xem, thông tin đúng hết chưa và đối chiếu ID để bổ sung ID và bấm cập
 * nhật nếu [đầy] đủ, còn nếu thiếu thì bổ sung thêm"*.
 *
 * Cùng gốc với việc đổi mã: **mã NV do Admin quyết**. CHT không được tự đặt mã — đặt tay là sinh
 * ra đúng mấy mã rác ("1", "15", "24") mà cả buổi nay đang phải đi sửa.
 *
 * ⚠️ Yêu cầu CHƯA phải hồ sơ. Nó nằm ở sheet riêng `YeuCauNV`, không đụng `NhanVien`, nên CHT gửi
 *    sai cũng không làm bẩn danh sách nhân sự. Chỉ lúc Admin duyệt mới tạo hồ sơ thật.
 * ⚠️ Ảnh gửi lúc chưa có mã -> lưu Drive theo MÃ YÊU CẦU. Lúc duyệt đọc lại ảnh đó rồi đi qua
 *    ĐÚNG đường `upsertEmployee` (tạo hồ sơ + thêm vào NV_ + xếp lệnh máy), không viết đường thứ hai.
 */
var SH_YCNV = 'YeuCauNV';
var YCNV_H = ['id', 'Cửa hàng', 'Họ tên', 'PIN máy', 'Giới tính', 'photoFileId', 'Ghi chú',
              'Trạng thái', 'PIN người gửi', 'Người gửi', 'Tạo lúc',
              'Mã NV được gán', 'Người duyệt', 'Duyệt lúc', 'Kết quả',
              /* ⚠️ Cột MỚI phải nằm CUỐI. Chèn vào giữa là mọi `appendRow` / chỉ số cột cứng ở
                 `_ycnvDoc` / `duyetYeuCauNV` lệch hết — tên người nhảy sang ô trạng thái. */
              'CCCD'];
var YCNV_CHO = 'cho', YCNV_DUYET = 'duyet', YCNV_TUCHOI = 'tu-choi';

/** Hôm nay theo múi giờ VN, dạng 'yyyy-MM-dd'. MỘT chỗ duy nhất để so ngày. */
function _bfHomNay(){ return Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd'); }

/* Đã chờ bao nhiêu PHÚT kể từ mốc `createdAt` của lệnh (dạng '_now()' = 'yyyy-MM-dd HH:mm:ss').
 * Trả -1 nếu mốc không đọc được — chỗ gọi phải hiểu -1 là "không biết", đừng coi là 0 phút.
 * ⚠️ Dựng Date.UTC cho CẢ HAI mốc rồi trừ: hai mốc cùng một múi giờ nên hiệu số đúng, khỏi phụ
 *    thuộc vào việc engine hiểu chuỗi 'yyyy-MM-dd HH:mm:ss' là giờ máy hay giờ UTC (V8 và Rhino
 *    hiểu khác nhau — chính chỗ dễ ra số âm hoặc lệch 7 tiếng). */
var LENH_DO_PHUT = 5;               // lệnh đầu hàng chờ quá 5 phút -> nghi ĐƠ (máy poll 10 giây/lần)
function _phutTuLuc(s){
  var m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
  if (!m) return -1;
  var n = _now().match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
  if (!n) return -1;
  var a = Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6]);
  var b = Date.UTC(+n[1], +n[2] - 1, +n[3], +n[4], +n[5], +n[6]);
  return Math.floor((b - a) / 60000);
}
function _ycnvSheet(){ return _ensureSheet(SH_YCNV, YCNV_H); }
/** Đọc mọi yêu cầu -> mảng {row, ...}. Chỉ đọc. */
function _ycnvDoc(){
  var sh = _ycnvSheet(), lr = sh.getLastRow();
  if (lr < 2) return [];
  var v = sh.getRange(2, 1, lr - 1, YCNV_H.length).getValues(), out = [];
  for (var i = 0; i < v.length; i++){
    var id = String(v[i][0] || '').trim(); if (!id) continue;
    out.push({ row:i + 2, id:id, coSo:String(v[i][1] || '').replace(/^CS_/, '').trim(),
               ten:String(v[i][2] || '').trim(), pinMay:String(v[i][3] || '').trim(),
               gender:String(v[i][4] || '').trim(), anh:String(v[i][5] || '').trim(),
               ghiChu:String(v[i][6] || '').trim(), trangThai:String(v[i][7] || YCNV_CHO).trim(),
               pinGui:String(v[i][8] || ''), nguoiGui:String(v[i][9] || ''),
               taoLuc:String(v[i][10] || ''), maGan:String(v[i][11] || '').trim(),
               nguoiDuyet:String(v[i][12] || ''), duyetLuc:String(v[i][13] || ''),
               ketQua:String(v[i][14] || ''), cccd:String(v[i][15] || '').trim() });
  }
  return out;
}

/** CHT (hoặc Quản lý/Admin) gửi yêu cầu. obj = {coSo, ten, pinMay, gender, photoDataUrl, ghiChu} */
/* Ai được gửi yêu cầu: CHT / Quản lý / Admin. Nhân viên thường thì không. */
function _ycnvDuocGui(u){
  return !!(u.isAdmin || u.role === ROLE.QUAN_LY || u.role === ROLE.CHT);
}
/* ===========================================================================
 *  THÊM MỘT YÊU CẦU — một chỗ duy nhất, dùng cho cả gửi LẺ và gửi LOẠT
 * ---------------------------------------------------------------------------
 *  🔴 03/08/2026 anh Thắng chốt 3 trường BẮT BUỘC: **ảnh thẻ · họ và tên · cơ sở**.
 *  Bản đầu của em cho gửi THIẾU ẢNH rồi ghi chú "Admin sẽ phải tải ảnh lên" — sai ý, và sai cả
 *  về việc: không có ảnh thì đẩy xuống máy chấm công người đó KHÔNG quẹt mặt được, tức là yêu
 *  cầu đó vô dụng mà vẫn nằm trong danh sách chờ như thật. Nay thiếu ảnh là CHẶN ngay cửa vào.
 *  Ảnh nhận HAI đường: `photoDataUrl` (CHT chụp/tải lên) hoặc `photoFileId` (ô ảnh đã gán sẵn
 *  trong sheet — đúng cách anh nói: *"trong sheet nhân viên sẽ có ô ảnh nhân đã gán sẵn"*).
 *  Gọi trong `_ycnvThem` để gửi lẻ và gửi loạt KHÔNG BAO GIỜ lệch luật nhau.
 * =========================================================================== */
function _ycnvThem(u, obj, tuGui){
  obj = obj || {};
  var coSo = String(obj.coSo || '').replace(/^CS_/, '').trim();
  var ten  = String(obj.ten || '').trim();
  if (!coSo) return { ok:false, error:'Thiếu cửa hàng.' };
  if (!ten)  return { ok:false, error:'Thiếu họ tên.' };
  /* 🔴 `tuGui` = nhân viên TỰ gửi từ trang chấm công online, chưa có tài khoản nên KHÔNG có
     `_canStation` để xét. Bỏ chốt cửa hàng ở đây KHÔNG phải là mở quyền: yêu cầu vẫn rơi vào
     hàng CHỜ, Admin gán mã rồi duyệt thì mới có hồ sơ và mới xuống máy. Cái phải siết là:
       · cửa hàng phải CÓ THẬT (`CS_<tên>` tồn tại) — không thì một cú gõ tay đẻ ra cửa hàng ma;
       · ghi rõ nguồn gửi để Admin biết dòng này do người ngoài gửi vào.
     Đường của CHT (`guiYeuCauNV`) giữ nguyên chốt cũ. */
  if (tuGui){
    if (!_sheet('CS_' + coSo))
      return { ok:false, error:'Cơ sở "' + coSo + '" không có trong hệ thống — chọn lại từ danh sách.' };
  } else if (!u.isAdmin && !_canStation(u, coSo)) {
    return { ok:false, error:'Bạn không phụ trách cửa hàng này.' };
  }
  /* Hàng tiêu đề lọt vào đây là rác nằm vĩnh viễn trong sheet — chặn ngay cửa vào, giống
     `upsertEmployee`. */
  if (_nvcsLaTieuDe('', ten)) return { ok:false, error:'"' + ten + '" là chữ tiêu đề, không phải tên người.' };

  /* ẢNH THẺ BẮT BUỘC. Kiểm TRƯỚC khi khoá + ghi sheet: chặn muộn là đã có dòng rác. */
  var laDataUrl = !!(obj.photoDataUrl && String(obj.photoDataUrl).indexOf('base64,') >= 0);
  var idAnhSan  = obj.photoFileId ? _anhFileId(obj.photoFileId) : '';
  if (!laDataUrl && !idAnhSan)
    return { ok:false, error:'Thiếu ẢNH THẺ — bắt buộc phải có, không thì đẩy xuống máy người đó '
           + 'không quẹt mặt được. Chọn ảnh rồi gửi lại.' };
  /* Ảnh gán sẵn phải ĐỌC ĐƯỢC thật. Không kiểm thì một ô ảnh hỏng/không có quyền vẫn lọt vào
     danh sách chờ, tới lúc duyệt mới vỡ — mà lúc đó hồ sơ đã tạo dở. */
  if (!laDataUrl && idAnhSan) {
    try { DriveApp.getFileById(idAnhSan).getBlob(); }
    catch (eA) { return { ok:false, error:'Không đọc được ảnh đã gán (' + idAnhSan
                        + ') — kiểm quyền chia sẻ của file ảnh.' }; }
  }

  var lock = LockService.getScriptLock();
  try { lock.waitLock(15000); } catch (e) {}
  try {
    /* Trùng yêu cầu ĐANG CHỜ cho cùng người ở cùng cửa hàng -> chặn. Không chặn thì CHT bấm hai
       lần là Admin thấy hai dòng y nhau rồi duyệt cả hai, thành hai hồ sơ cho một người. */
    var da = _ycnvDoc().filter(function(y){
      return y.trangThai === YCNV_CHO && _chuanMa(y.coSo) === _chuanMa(coSo)
             && _chuanTenNguoi(y.ten) === _chuanTenNguoi(ten); });
    if (da.length) return { ok:false, error:'Đã có yêu cầu đang chờ cho "' + ten + '" ở ' + coSo
                          + ' (gửi lúc ' + da[0].taoLuc + '). Chờ Admin duyệt, đừng gửi lại.' };
    /* Đã có HỒ SƠ cùng tên ở cửa hàng đó -> báo, đừng để Admin duyệt xong mới thấy trùng người.
       Chỉ CẢNH BÁO chứ không chặn: trùng tên là chuyện có thật, người quyết là Admin. */
    var canh = '';
    try {
      var trungTen = getEmployees(u.pin || '').filter(function(e){
        return _chuanTenNguoi(e.name) === _chuanTenNguoi(ten); });
      if (trungTen.length) canh = 'Đã có hồ sơ cùng tên: '
        + trungTen.map(function(e){ return e.employeeNo + ' (' + (e.station || '?') + ')'; }).join(', ')
        + ' — Admin kiểm có phải cùng một người không.';
    } catch (eT) {}

    var id = 'yc-' + Utilities.getUuid().substring(0, 8);
    var anhId = idAnhSan;
    if (laDataUrl) {
      // Chưa có mã NV nên đặt tên file theo MÃ YÊU CẦU; lúc duyệt sẽ lưu lại theo mã thật.
      try { anhId = _saveNvFile(id, obj.photoDataUrl, 'yeucau'); } catch (e2) { anhId = ''; }
      if (!anhId) return { ok:false, error:'Lưu ảnh vào Drive thất bại — thử lại, đừng gửi thiếu ảnh.' };
    }
    _ycnvSheet().appendRow([id, coSo, ten, String(obj.pinMay || '').trim(),
      String(obj.gender || '').trim(), anhId, String(obj.ghiChu || '').trim(),
      YCNV_CHO, String(u.pin || ''), String(u.name || ''), _now(), '', '', '', '',
      _chuanCccd(obj.cccd)]);
    return { ok:true, id:id, ten:ten, coSo:coSo, coAnh:true, canh:canh };
  } catch (err) { return { ok:false, error:String(err) }; }
  finally { lock.releaseLock(); }
}

/* ===========================================================================
 *  TRANG CHẤM CÔNG ONLINE — HAI CỬA KHÔNG CẦN ĐĂNG NHẬP  (07/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"bổ sung thêm tìm mã pin theo Căn cước công dân. Nhân viên chỉ cần gõ căn cước
 *  công dân là hiện mã pin lên"* và *"thêm chỗ bổ sung thông tin nhân viên vào máy chấm công:
 *  Họ và Tên, Số CCCD, Chọn Cơ Sở Làm Việc, Chụp Ảnh 3x4"*.
 *
 *  🔴🔴 PHẢI NÓI THẲNG RỦI RO CỦA CỬA THỨ NHẤT. Trang chấm công online là web app CÔNG KHAI —
 *  ai có link đều mở được, không cần đăng nhập. Một ô "gõ CCCD ra PIN" chính là biến số CCCD
 *  thành mật khẩu, mà CCCD KHÔNG PHẢI bí mật: nó nằm trên thẻ căn cước, người ta đưa cho chủ
 *  trọ, ngân hàng, khách sạn. Ai chụp được thẻ của một nhân viên là **đăng nhập được tài khoản
 *  người đó** và chấm công thay họ.
 *  Không bỏ tính năng (anh cần nó thật — quên PIN là không chấm công được), nhưng phải có:
 *    1. KHỚP TUYỆT ĐỐI cả 12 số. Không dò theo 4 số cuối, không khớp một phần.
 *    2. CHẶN DÒ: mỗi số CCCD tối đa `TRA_PIN_MOI_SO` lượt / 10 phút; toàn hệ thống tối đa
 *       `TRA_PIN_TONG` lượt HỎNG / 10 phút (một cửa hàng vài chục người thì ngưỡng này rất rộng,
 *       chạm ngưỡng nghĩa là có người đang quét).
 *    3. GHI NHẬT KÝ mọi lượt tra — và ghi CCCD ĐÃ CHE, tuyệt đối không ghi PIN vào sheet nhật ký.
 *    4. Không tiết lộ gì thêm khi tra trượt: chỉ "không tìm thấy", không nói "có người tên này".
 *
 *  Cửa thứ hai (nhân viên tự gửi thông tin) an toàn hơn hẳn vì nó chỉ ĐẨY VÀO HÀNG CHỜ —
 *  Admin gán mã rồi duyệt thì mới thành hồ sơ và mới xuống máy chấm công.
 * =========================================================================== */
var SH_TRA_PIN   = 'NhatKyTraPin';
var TRA_PIN_H    = ['Lúc', 'CCCD (che)', 'Kết quả', 'Mã NV', 'Ghi chú'];
var TRA_PIN_MOI_SO = 5;      // mỗi số CCCD, trong 10 phút
var TRA_PIN_TONG   = 30;     // tổng số lượt TRA TRƯỢT của cả hệ thống, trong 10 phút
var TRA_PIN_CUA    = 600;    // 10 phút

/** Chỉ giữ chữ số. CCCD 12 số (mẫu mới) hoặc CMND 9 số (mẫu cũ). */
function _chuanCccd(v){ return String(v == null ? '' : v).replace(/\D+/g, ''); }
/** Che giữa, chỉ để 3 số đầu và 3 số cuối — đủ để đối chiếu nhật ký, không đủ để dùng lại. */
function _cheCccd(s){
  s = _chuanCccd(s);
  if (s.length < 7) return s ? (s.charAt(0) + '***') : '';
  return s.slice(0, 3) + '***' + s.slice(-3);
}
function _ghiNhatKyTraPin(cccd, ketQua, maNV, ghiChu){
  try {
    _ensureSheet(SH_TRA_PIN, TRA_PIN_H)
      .appendRow([_now(), _cheCccd(cccd), String(ketQua || ''), String(maNV || ''), String(ghiChu || '')]);
  } catch (e) {}
}
function _traPinDem(khoa, tran){
  var c;
  try { c = CacheService.getScriptCache(); } catch (e) { return { qua:false, so:0 }; }
  if (!c) return { qua:false, so:0 };
  var so = Number(c.get(khoa) || 0) + 1;
  c.put(khoa, String(so), TRA_PIN_CUA);
  return { qua: so > tran, so: so };
}

/**
 * Tra MÃ PIN đăng nhập theo số CCCD. KHÔNG cần đăng nhập — xem khối cảnh báo phía trên.
 * Trả { ok, pin, ten, coSo } hoặc { ok:false, error }.
 */
function traPinTheoCccd(cccd){
  var so = _chuanCccd(cccd);
  if (so.length < 9 || so.length > 12)
    return { ok:false, error:'Số căn cước phải đủ 12 số (hoặc 9 số nếu là CMND cũ).' };

  // Chặn dò theo TỪNG SỐ trước, để một người gõ nhầm vài lần không làm khoá cả hệ thống.
  if (_traPinDem('trapin_so_' + so, TRA_PIN_MOI_SO).qua){
    _ghiNhatKyTraPin(so, 'chan-qua-nhieu-lan', '', 'quá ' + TRA_PIN_MOI_SO + ' lượt/10 phút cho cùng một số');
    return { ok:false, error:'Số này đã tra quá nhiều lần. Chờ 10 phút hoặc hỏi quản lý cửa hàng.' };
  }
  /* Ngưỡng TOÀN HỆ THỐNG chỉ đếm lượt TRƯỢT. Đếm cả lượt đúng thì một buổi sáng đông người
     quên PIN là tự khoá nhau. */
  try {
    var c0 = CacheService.getScriptCache();
    if (c0 && Number(c0.get('trapin_hong') || 0) > TRA_PIN_TONG){
      _ghiNhatKyTraPin(so, 'chan-toan-he-thong', '', 'quá ' + TRA_PIN_TONG + ' lượt hỏng/10 phút');
      return { ok:false, error:'Hệ thống đang tạm khoá tra cứu (có quá nhiều lượt tra sai). '
             + 'Nhờ quản lý cửa hàng lấy giúp mã PIN.' };
    }
  } catch (e) {}

  var iCccd = 7 + NV_EXTRA.indexOf('cccd');       // cột CCCD trong `NhanVien`
  var sh = _nvSheet(), last = sh ? sh.getLastRow() : 0, ma = '', ten = '';
  if (last >= 2){
    var v = sh.getRange(2, 1, last - 1, NV_HEADERS.length).getValues();
    for (var i = 0; i < v.length; i++){
      if (_chuanCccd(v[i][iCccd]) !== so) continue;   // KHỚP TUYỆT ĐỐI, không khớp một phần
      ma = String(v[i][0] || '').trim(); ten = String(v[i][1] || '').trim(); break;
    }
  }
  if (!ma){
    _traPinDem('trapin_hong', TRA_PIN_TONG);
    _ghiNhatKyTraPin(so, 'khong-thay', '', '');
    return { ok:false, error:'Không tìm thấy số căn cước này trong hệ thống. '
           + 'Nếu anh/chị chưa có hồ sơ thì dùng ô "Gửi thông tin vào máy chấm công" bên dưới.' };
  }

  // Mã NV -> tài khoản đăng nhập (PhanQuyen cột E = "Mã NV chấm công online").
  var pq = _ensureSheet(SH_ROLE), lr = pq.getLastRow(), pin = '', coSo = '';
  if (lr >= 2){
    var r = pq.getRange(2, 1, lr - 1, PQ_H.length).getValues();
    for (var j = 0; j < r.length; j++){
      if (_chuanMa(r[j][4]) !== _chuanMa(ma)) continue;
      pin  = String(r[j][0] == null ? '' : r[j][0]).trim();
      coSo = String(r[j][5] || '').replace(/^CS_/, '').trim();
      break;
    }
  }
  if (!pin){
    /* CÓ hồ sơ mà CHƯA có tài khoản chấm công online — nói đúng chuyện đó, đừng để họ tưởng
       gõ sai số rồi gõ đi gõ lại. Không đếm là lượt hỏng vì số căn cước hoàn toàn đúng. */
    _ghiNhatKyTraPin(so, 'chua-co-tai-khoan', ma, '');
    return { ok:false, error:'Anh/chị đã có hồ sơ (' + ma + ') nhưng CHƯA được cấp tài khoản chấm '
           + 'công online. Báo quản lý cửa hàng bật giúp.' };
  }
  _ghiNhatKyTraPin(so, 'thay', ma, '');      // ⚠️ KHÔNG ghi PIN vào nhật ký
  return { ok:true, pin:pin, ten:ten, maNV:ma, coSo:coSo };
}

/**
 * Nhân viên TỰ gửi thông tin để được thêm vào máy chấm công. KHÔNG cần đăng nhập.
 * obj = { hoTen, cccd, coSo, photoDataUrl }
 * Chỉ đẩy vào hàng CHỜ — Admin gán mã rồi duyệt thì mới có hồ sơ và mới xuống máy.
 */
function guiThongTinNvOnline(obj){
  obj = obj || {};
  var ten  = String(obj.hoTen || '').trim().replace(/\s+/g, ' ');
  var so   = _chuanCccd(obj.cccd);
  var coSo = String(obj.coSo || '').replace(/^CS_/, '').trim();
  if (ten.length < 3)  return { ok:false, error:'Họ và tên quá ngắn — ghi đầy đủ họ tên.' };
  if (so.length < 9 || so.length > 12)
    return { ok:false, error:'Số căn cước phải đủ 12 số (hoặc 9 số nếu là CMND cũ).' };
  if (!coSo)           return { ok:false, error:'Chưa chọn cơ sở làm việc.' };
  if (!(obj.photoDataUrl && String(obj.photoDataUrl).indexOf('base64,') >= 0))
    return { ok:false, error:'Chưa có ảnh 3×4. Máy chấm công cần ảnh để nhận diện khuôn mặt.' };

  // Chặn gửi dồn: cùng một số căn cước tối đa 3 lượt / 10 phút.
  if (_traPinDem('guinv_so_' + so, 3).qua)
    return { ok:false, error:'Số này vừa gửi rồi. Chờ quản lý duyệt, đừng gửi lại nhiều lần.' };

  /* ĐÃ CÓ HỒ SƠ thì đừng tạo yêu cầu nữa — duyệt vào là hai hồ sơ cho một người, mà hai hồ sơ
     là hai dòng lương. Chỉ ra đúng ô cần dùng thay vì báo lỗi cụt. */
  var iCccd = 7 + NV_EXTRA.indexOf('cccd');
  var shNv = _nvSheet(), lastNv = shNv ? shNv.getLastRow() : 0;
  if (lastNv >= 2){
    var vn = shNv.getRange(2, 1, lastNv - 1, NV_HEADERS.length).getValues();
    for (var i = 0; i < vn.length; i++){
      if (_chuanCccd(vn[i][iCccd]) !== so) continue;
      return { ok:false, daCoHoSo:true,
               error:'Số căn cước này đã có hồ sơ trong hệ thống. Dùng ô "Quên mã PIN?" phía trên '
                   + 'để lấy lại mã đăng nhập.' };
    }
  }
  /* Trùng với một yêu cầu ĐANG CHỜ (cùng số căn cước) -> chặn, kể cả khi gõ tên khác đi.
     `_ycnvThem` đã chặn trùng theo TÊN + cửa hàng, nhưng ở đây số căn cước mới là thứ chắc. */
  try {
    var cho = _ycnvDoc().filter(function(y){
      return y.trangThai === YCNV_CHO && y.cccd && _chuanCccd(y.cccd) === so; });
    if (cho.length)
      return { ok:false, error:'Số căn cước này đã có yêu cầu đang chờ duyệt (gửi lúc '
             + cho[0].taoLuc + '). Chờ quản lý duyệt.' };
  } catch (e) {}

  var gia = { isAdmin:false, pin:'', name:'Nhân viên tự gửi (chấm công online)' };
  var r = _ycnvThem(gia, {
    coSo: coSo, ten: ten, cccd: so, photoDataUrl: obj.photoDataUrl,
    ghiChu: 'Nhân viên tự gửi từ trang chấm công online · CCCD ' + _cheCccd(so)
  }, true);
  if (r && r.ok) r.thongBao = 'Đã gửi. Quản lý sẽ kiểm thông tin rồi thêm anh/chị vào máy chấm công.';
  return r;
}

/** Danh sách cơ sở cho ô chọn ở trang chấm công online (công khai, chỉ TÊN cơ sở, không gì khác). */
function dsCoSoChoNvGui(){
  var out = [];
  try {
    SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(sh){
      var n = sh.getName();
      if (n.indexOf('CS_') === 0) out.push(n.slice(3));
    });
  } catch (e) {}
  out.sort();
  return { ok:true, list:out };
}

function guiYeuCauNV(pin, obj){
  var u = _requireAuth(pin);
  // CHT gửi được cho ĐÚNG cửa hàng mình phụ trách. Không mở cho Nhân viên thường.
  if (!_ycnvDuocGui(u)) return { ok:false, error:'Chỉ Cửa hàng trưởng / Quản lý / Admin gửi được yêu cầu.' };
  return _ycnvThem(u, obj);
}

/* ===========================================================================
 *  GỬI LOẠT TỪ MỘT GOOGLE SHEET
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"nếu cửa hàng muốn bổ sung hàng loạt bằng google sheet thì sẽ bổ sung bằng link
 *  để đẩy 1 loạt"*.
 *  Cột nhận theo TÊN TIÊU ĐỀ (không phân biệt dấu / hoa thường), tìm trong 6 hàng đầu — dùng
 *  lại đúng khuôn `_nvcsCot` của sheet NV_ để CHT không phải học thêm cách bày cột:
 *      Họ tên (bắt buộc) · Ảnh (bắt buộc) · PIN máy · Giới tính · Ghi chú
 *  Ô Ảnh nhận: id Drive · link Drive · công thức =IMAGE("…") — `_anhFileId` bóc được cả ba.
 *
 *  ⚠️ CHỈ ĐỌC sheet của anh, KHÔNG ghi gì vào đó. Mọi thứ ghi ra đều vào sheet YeuCauNV của app.
 *  ⚠️ Dòng nào thiếu / sai thì BỎ RIÊNG dòng đó kèm LÝ DO, các dòng còn lại vẫn gửi — chặn cả lô
 *     vì một dòng thiếu ảnh là CHT phải dò tay giữa mấy chục dòng.
 *  ⚠️ Mỗi dòng đi qua `_ycnvThem` y như gửi lẻ, nên luật (ảnh bắt buộc, chặn trùng, gác quyền
 *     cửa hàng) KHÔNG BAO GIỜ lệch giữa hai đường.
 * =========================================================================== */
/* ===========================================================================
 *  SHEET MẪU — để nhân viên nhập ĐÚNG CỘT ngay từ đầu
 * ---------------------------------------------------------------------------
 *  Anh Thắng 07/08/2026: *"bổ sung sheet mẫu để nhân viên nhập đúng cột"*.
 *  Cột nhận theo TÊN TIÊU ĐỀ, mà mỗi cửa hàng tự bày một kiểu thì lô nào cũng có dòng bị loại,
 *  rồi CHT phải dò tay giữa mấy chục dòng. Phát sẵn một file đúng khuôn là hết chuyện đoán.
 *
 *  ⚠️ Tiêu đề ở đây phải NẰM TRONG bảng nhận dạng (`NVCS_BIET_TEN` + `YCNV_BIET_COT`), không thì
 *     chính sheet mẫu của mình lại không đọc được. Có phép kiểm ghim đúng chuyện đó.
 *  ⚠️ File tạo ra thuộc tài khoản đang chạy app, nên anh mở được ngay, khỏi phải chia sẻ.
 *  ⚠️ Mỗi lần bấm là MỘT file mới trong Drive — nói rõ trên giao diện để khỏi bấm bừa rồi rác.
 * =========================================================================== */
var YCNV_MAU_COT = ['Họ tên', 'Ảnh', 'PIN máy', 'Giới tính', 'Ghi chú'];
function taoSheetMauNv(pin, coSo){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền tạo sheet mẫu.' };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  try {
    var ten = 'Mẫu thêm nhân viên' + (coSo ? (' - ' + coSo) : '') + ' - ' + _now().slice(0, 10);
    var ss = SpreadsheetApp.create(ten);
    var sh = ss.getSheets()[0];
    sh.setName('Danh sách');
    sh.getRange(1, 1, 1, YCNV_MAU_COT.length).setValues([YCNV_MAU_COT])
      .setFontWeight('bold').setBackground('#0f172a').setFontColor('#38bdf8');
    /* Hai dòng ví dụ, ghi rõ là VÍ DỤ — để trống thì người ta không biết điền dạng gì, mà điền
       sẵn dữ liệu giả không nhãn thì có ngày bị gửi luôn lên hệ thống. */
    sh.getRange(2, 1, 2, YCNV_MAU_COT.length).setValues([
      ['(VÍ DỤ - xoá dòng này) Nguyễn Văn A', '1AbCdEfGhIjKlMnOpQrStUvWxYz012345', '123456', 'Nam', 'Ca sáng'],
      ['(VÍ DỤ - xoá dòng này) Trần Thị B', 'https://drive.google.com/file/d/1AbC…/view', '', 'Nữ', '']
    ]).setFontColor('#94a3b8').setFontStyle('italic');
    sh.setFrozenRows(1);
    sh.setColumnWidth(1, 220); sh.setColumnWidth(2, 340); sh.setColumnWidth(5, 200);

    var hd = ss.insertSheet('Hướng dẫn');
    hd.getRange(1, 1, 9, 1).setValues([
      ['CÁCH DÙNG'],
      ['1. Điền vào tab "Danh sách". XOÁ 2 dòng ví dụ màu xám trước khi gửi.'],
      ['2. Bắt buộc: cột "Họ tên" và cột "Ảnh". Thiếu một trong hai thì dòng đó bị bỏ qua (có báo lý do).'],
      ['3. Ô "Ảnh" nhận: id Drive · link Drive · hoặc công thức =IMAGE("…"). Ảnh phải rõ mặt, chụp thẳng.'],
      ['4. "PIN máy" để trống cũng được — hệ thống tự cấp.'],
      ['5. ĐỪNG đổi tên các cột ở hàng 1, đổi là app không nhận ra cột nữa.'],
      ['6. Chia sẻ file này cho tài khoản chạy web app (ít nhất quyền Xem).'],
      ['7. Dán link file vào ô "Link Google Sheet" trong app rồi bấm "Đọc & gửi loạt".'],
      ['App chỉ ĐỌC file này, không ghi gì vào đây.']
    ]);
    hd.getRange(1, 1).setFontWeight('bold');
    hd.setColumnWidth(1, 700);

    return { ok:true, url:ss.getUrl(), id:ss.getId(), ten:ten };
  } catch (e) {
    // Nói rõ hỏng ở đâu — "tạo được/không" là thứ anh phải biết ngay, đừng trả im lặng.
    return { ok:false, error:'Không tạo được sheet mẫu: ' + e };
  }
}

var YCNV_LOAT_TOI_DA = 200;      // quá số này thì bắt chia nhỏ — Apps Script có hạn 6 phút/lượt
/* Bóc id bảng tính từ link. Nhận cả link đầy đủ lẫn id trần. */
function _ssIdTuLink(v){
  var s = String(v == null ? '' : v).trim();
  if (!s) return '';
  var m = s.match(/\/spreadsheets\/d\/([-\w]{20,})/);
  if (m) return m[1];
  m = s.match(/^[-\w]{20,}$/);
  return m ? m[0] : '';
}
var YCNV_BIET_COT = {
  'anh':'anh', 'anh the':'anh', 'anh khuon mat':'anh', 'hinh':'anh', 'hinh anh':'anh', 'photo':'anh',
  'pin may':'pinMay', 'pin':'pinMay', 'ma pin':'pinMay',
  'gioi tinh':'gender', 'phai':'gender'
};
/** Bản đồ cột cho sheet gửi loạt: dùng `_nvcsCot` (ten/ghiChu) + bổ sung anh/pinMay/gender. */
function _ycnvCot(sh){
  var lc = sh.getLastColumn(), lr = sh.getLastRow();
  var trong = { ten:0, anh:0, pinMay:0, gender:0, ghiChu:0, hangTieuDe:0 };
  if (lc < 1 || lr < 1) return trong;
  var het = Math.min(NVCS_DO_TOI_HANG, lr);
  var v = sh.getRange(1, 1, het, lc).getDisplayValues();
  for (var r = 0; r < het; r++){
    var m = { ten:0, anh:0, pinMay:0, gender:0, ghiChu:0, hangTieuDe:r + 1 };
    for (var i = 0; i < v[r].length; i++){
      var kh = _khongDau(v[r][i]);
      var k = (NVCS_BIET_TEN[kh] === 'ten' || NVCS_BIET_TEN[kh] === 'ghiChu')
              ? NVCS_BIET_TEN[kh] : YCNV_BIET_COT[kh];
      if (k && !m[k]) m[k] = i + 1;
    }
    if (m.ten) return m;              // hàng tiêu đề thật phải có cột HỌ TÊN
  }
  return trong;
}
/**
 * Đọc bảng tính rồi gửi một loạt yêu cầu cho MỘT cửa hàng.
 * Trả {ok, coSo, tong, gui, bo:[{dong, ten, ly}], ds:[{dong, ten, id}]}
 */
/* ===========================================================================
 *  NHẬP NHÂN SỰ CHUẨN TỪ FILE CÔNG TY  (07/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"anh sẽ xoá hết nhân viên trong sheet nhân viên và bổ sung dữ liệu nhân viên chuẩn
 *  từ công ty"*, *"từ 2 sheet này bao gồm cột A, C, F, J"*, *"cửa hàng là cột L"*,
 *  *"còn danh sách Văn phòng anh sẽ gõ tay"*.
 *
 *  🔴 KHÔNG XOÁ AI. Đây là điểm khác quan trọng nhất so với "xoá hết rồi dán lại":
 *     sheet `NhanVien` không chỉ có tên + cửa hàng. Nó còn giữ **photoFileId** (ảnh thẻ đã đẩy
 *     xuống máy chấm công), **trạng thái đồng bộ**, **PIN máy**, lương, ngân hàng… File công ty
 *     KHÔNG có mấy thứ đó. Xoá sạch rồi dán là **mất ảnh thẻ của cả chuỗi** — ảnh vẫn nằm trên
 *     Drive nhưng hồ sơ không còn trỏ tới, muốn đẩy lại xuống máy phải đi chụp lại từng người.
 *     Nên hàm này CẬP NHẬT theo Mã NV:
 *       · mã ĐÃ CÓ  -> chỉ ghi đè 3 ô: Họ tên · Cửa hàng · CCCD (+ Chức vụ). Mọi ô khác GIỮ NGUYÊN.
 *       · mã MỚI    -> thêm dòng.
 *       · hồ sơ hiện có mà file KHÔNG có -> **để nguyên**, chỉ báo. Đó có thể là Văn phòng anh gõ
 *         tay, hoặc người đã nghỉ còn phải tính lương tháng cuối.
 *
 *  🔴 CHẶN TRƯỚC KHI GHI, không sửa nửa chừng:
 *     · MÃ TRÙNG trong file (ảnh anh gửi đang có `MNNV2MTD0007` và `MNNV2MTD0009` hai dòng) —
 *       trùng mã là hai người dùng chung MỘT hàng chấm công, hỏng lương;
 *     · CỬA HÀNG LẠ — cột L là tên chữ ("Posh Go Dĩ An"), phải khớp đúng tên sheet `CS_…`.
 *       Ghi một tên không có sheet nào là người đó **không hiện ở đâu cả**, vì ô "Cửa hàng" nay là
 *       nguồn DUY NHẤT. Nên phải quy đổi hết mới cho ghi.
 *
 *  ⚠️ Đọc theo VỊ TRÍ CỘT (A·C·F·J·L) đúng như anh chỉ, KHÔNG dò theo tên tiêu đề. Bù lại phần
 *     xem trước trả về nguyên hàng tiêu đề của 5 cột đó để anh nhìn phát biết có lấy đúng không.
 * =========================================================================== */
var NNS_COT = { ma:1, ten:3, phongBan:6, cccd:10, cuaHang:12 };   // A · C · F · J · L
var NNS_TOI_DA = 2000;

/** Chuẩn hoá tên cơ sở để so khớp: bỏ dấu, bỏ mọi ký tự không phải chữ/số, viết thường. */
function _nnsKhoaCoSo(s){
  return _khongDau(String(s == null ? '' : s)).toLowerCase().replace(/[^a-z0-9]+/g, '');
}
/** Mọi cơ sở app đang có (theo sheet `CS_`) -> {khoá chuẩn: tên thật}. */
function _nnsBangCoSo(){
  var m = {};
  SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(sh){
    var n = sh.getName();
    if (n.indexOf('CS_') !== 0) return;
    var t = n.slice(3);
    m[_nnsKhoaCoSo(t)] = t;
  });
  return m;
}

/** Đọc 5 cột từ NHIỀU tab của một bảng tính ngoài. Trả {ok, tieuDe:[…], dong:[…]}. */
function _nnsDoc(link, dsTab){
  var id = _ssIdTuLink(link);
  if (!id) return { ok:false, error:'Link Google Sheet không đọc được. Dán cả link dạng '
                  + 'docs.google.com/spreadsheets/d/…, hoặc dán riêng phần id.' };
  var ss;
  try { ss = SpreadsheetApp.openById(id); }
  catch (e) { return { ok:false, error:'Không mở được bảng tính đó. Chia sẻ file cho tài khoản '
                     + 'chạy app (ít nhất quyền Xem) rồi thử lại. (' + e + ')' }; }
  var tabs = (dsTab || []).map(function(x){ return String(x || '').trim(); }).filter(Boolean);
  if (!tabs.length) return { ok:false, error:'Chưa ghi tên tab nào.' };
  var out = [], tieuDe = [];
  for (var t = 0; t < tabs.length; t++){
    var sh = ss.getSheetByName(tabs[t]);
    if (!sh) return { ok:false, error:'Không thấy tab "' + tabs[t] + '" trong bảng tính đó. '
                    + 'Các tab đang có: ' + ss.getSheets().map(function(x){ return x.getName(); }).join(' · ') };
    var lr = sh.getLastRow(), lc = sh.getLastColumn();
    if (lc < NNS_COT.cuaHang)
      return { ok:false, error:'Tab "' + tabs[t] + '" chỉ có ' + lc + ' cột — cần ít nhất '
             + NNS_COT.cuaHang + ' cột (tới cột L).' };
    if (lr < 2) continue;
    if (lr - 1 > NNS_TOI_DA)
      return { ok:false, error:'Tab "' + tabs[t] + '" có ' + (lr - 1) + ' dòng, quá ' + NNS_TOI_DA + '.' };
    var hd = sh.getRange(1, 1, 1, lc).getDisplayValues()[0];
    tieuDe.push({ tab:tabs[t], ma:hd[NNS_COT.ma - 1], ten:hd[NNS_COT.ten - 1],
                  phongBan:hd[NNS_COT.phongBan - 1], cccd:hd[NNS_COT.cccd - 1],
                  cuaHang:hd[NNS_COT.cuaHang - 1] });
    var v = sh.getRange(2, 1, lr - 1, lc).getDisplayValues();
    for (var i = 0; i < v.length; i++){
      var r = v[i];
      var ma = String(r[NNS_COT.ma - 1] || '').trim();
      var ten = String(r[NNS_COT.ten - 1] || '').trim().replace(/\s+/g, ' ');
      if (!ma && !ten) continue;                       // dòng trống
      out.push({ tab:tabs[t], hang:i + 2, ma:ma, ten:ten,
                 phongBan:String(r[NNS_COT.phongBan - 1] || '').trim(),
                 cccd:_chuanCccd(r[NNS_COT.cccd - 1]),
                 cuaHangGoc:String(r[NNS_COT.cuaHang - 1] || '').trim() });
    }
  }
  return { ok:true, tieuDe:tieuDe, dong:out };
}

/**
 * Gộp mã trùng: GIỮ DÒNG CUỐI (bản khai mới nhất), bỏ các dòng trước — và trả về ĐÚNG dòng nào bị
 * bỏ để giao diện bày ra. `bat=false` thì không đụng gì.
 *
 * 🔴 07/08/2026 — anh Thắng: *"anh không sửa file gốc được, mình tự lọc file trung thôi"*.
 *    Lọc được, NHƯNG không tự gộp âm thầm: hai dòng trùng mã trong ảnh anh gửi khai HAI ĐƠN VỊ
 *    LÀM VIỆC KHÁC NHAU, nên chọn giữ dòng nào là **chọn người đó tính lương ở cửa hàng nào**.
 *    Đó là quyết định về tiền, không phải mẹo dọn dữ liệu -> mặc định TẮT, anh tự bật, và phần
 *    xem trước liệt kê rõ giữ dòng nào · bỏ dòng nào, tô riêng ca lệch cửa hàng.
 * ⚠️ Giữ dòng CUỐI chứ không phải dòng đầu: người ta khai lại là để sửa cái đã khai sai.
 */
function _nnsGopTrung(dong, bat){
  if (!bat) return { bat:false, dong:dong, daGop:[] };
  var nhom = {}, thuTu = [];
  dong.forEach(function(r){
    if (!r.ma) { thuTu.push({ le:r }); return; }        // thiếu mã -> giữ nguyên, phần sau bắt
    var k = _chuanMa(r.ma);
    if (!(k in nhom)) thuTu.push({ k:k });
    (nhom[k] = nhom[k] || []).push(r);
  });
  var ra = [], daGop = [];
  thuTu.forEach(function(x){
    if (x.le) { ra.push(x.le); return; }
    var ds = nhom[x.k], giu = ds[ds.length - 1];
    ra.push(giu);
    if (ds.length > 1)
      daGop.push({ ma:giu.ma, giu:_nnsMoTa(giu), bo:ds.slice(0, -1).map(_nnsMoTa),
                   lechCoSo: ds.some(function(y){
                     return _nnsKhoaCoSo(y.cuaHangGoc) !== _nnsKhoaCoSo(giu.cuaHangGoc); }) });
  });
  return { bat:true, dong:ra, daGop:daGop };
}
function _nnsMoTa(r){ return r.tab + '!' + r.hang + ' · ' + r.ten + ' · ' + r.cuaHangGoc; }

/* ===== QUY ƯỚC TÊN CỬA HÀNG — nhớ lâu dài, không phải gõ lại mỗi lần ======================
 * Anh Thắng: *"không lạ đâu, chỉ là khác tên thôi, nên mình sẽ quy ước trong app theo cửa hàng
 * có sẵn"*. Đúng — nên bảng quy đổi phải NẰM TRONG SHEET, không phải giữ tạm trên trình duyệt:
 * mỗi lần công ty cập nhật file là anh nhập lại, gõ lại vài chục dòng là kiểu gì cũng có dòng
 * gõ khác đi -> cùng một điểm bán rơi vào hai cơ sở khác nhau giữa hai lần nhập.
 * ⚠️ Khoá tra là tên ĐÃ CHUẨN HOÁ (bỏ dấu, bỏ ký tự lạ) — "Posh Go Dĩ An" và "POSH GO DI AN"
 *    phải trúng cùng một dòng, không thì bảng phình ra mà vẫn báo lạ. */
var SH_QUYDOI_CS = 'QuyDoiCoSo';
var QUYDOI_CS_H  = ['Tên trong file', 'Cơ sở của app', 'Người khai', 'Tạo lúc'];
/**
 * Bảng quy ước. Một dòng có thể là:
 *   · TÊN ĐẦY ĐỦ   — "Posh Go Dĩ An"  -> khớp đúng tên đó
 *   · LUẬT TIỀN TỐ — "Posh*"          -> khớp MỌI tên bắt đầu bằng "Posh"
 *
 * 🔴 07/08/2026 — anh Thắng: *"Cơ sở Posh và JP sẽ chung cơ sở (như Posh Cần Thơ hay Posh Phú Quốc
 *    vẫn là quy ước Posh theo web, JP cũng vậy)"*. Nên phải có luật tiền tố: gõ tay vài chục điểm
 *    bán là kiểu gì cũng sót một cái, mà sót là người đó không hiện ở đâu cả.
 * ⚠️ TÊN ĐẦY ĐỦ luôn thắng LUẬT TIỀN TỐ, và tiền tố DÀI HƠN thắng tiền tố ngắn hơn. Không có thứ
 *    tự ưu tiên rõ ràng thì thêm một luật là một chỗ cũ đổi nghĩa mà không ai biết.
 */
function _nnsBanDoLuu(){
  var m = { dung:{}, tienTo:[] };
  try {
    var sh = _sheet(SH_QUYDOI_CS); if (!sh || sh.getLastRow() < 2) return m;
    var v = sh.getRange(2, 1, sh.getLastRow() - 1, 2).getDisplayValues();
    for (var i = 0; i < v.length; i++){
      var t = String(v[i][0] || '').trim(), c = String(v[i][1] || '').replace(/^CS_/, '').trim();
      if (!t || !c) continue;
      if (t.charAt(t.length - 1) === '*'){
        var k = _nnsKhoaCoSo(t.slice(0, -1));
        if (k) m.tienTo.push({ k:k, coSo:c });
      } else m.dung[_nnsKhoaCoSo(t)] = c;              // dòng sau ghi đè dòng trước = sửa được
    }
    m.tienTo.sort(function(a, b){ return b.k.length - a.k.length; });   // dài hơn thắng
  } catch(e){}
  return m;
}
/**
 * Trộn bảng anh vừa chọn trên màn hình vào bảng đã lưu.
 * 🔴 SẮP LẠI THEO ĐỘ DÀI sau khi trộn. Luật gửi từ màn hình đi theo thứ tự khoá của đối tượng —
 *    dựa vào thứ tự đó là "Posh Go*" thắng "Posh*" chỉ nhờ MAY, đổi thứ tự gõ một cái là ngoại lệ
 *    im lặng ngừng ăn. Bộ phá bắt được đúng chỗ này.
 */
function _nnsTronBanDo(bd, banDo){
  Object.keys(banDo || {}).forEach(function(t){
    var c = String(banDo[t] || '').trim(); if (!c) return;
    if (String(t).slice(-1) === '*') bd.tienTo.push({ k:_nnsKhoaCoSo(String(t).slice(0, -1)), coSo:c });
    else bd.dung[_nnsKhoaCoSo(t)] = c;
  });
  bd.tienTo.sort(function(a, b){ return b.k.length - a.k.length; });
  return bd;
}

/** Tra một tên trong file ra cơ sở của app. Trả '' nếu chưa có luật nào khớp. */
function _nnsTra(bd, ten){
  var k = _nnsKhoaCoSo(ten);
  if (!k) return '';
  if (bd.dung[k]) return bd.dung[k];
  for (var i = 0; i < bd.tienTo.length; i++)
    if (k.indexOf(bd.tienTo[i].k) === 0) return bd.tienTo[i].coSo;
  return '';
}
/** Ghi quy ước vào sheet để lần nhập sau tự khớp. Chỉ nhận cơ sở CÓ SHEET `CS_`. */
function luuQuyDoiCoSo(pin, banDo){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Quy ước cơ sở dùng cho cả chuỗi — ' + _QT_LOI };
  var bangCs = _nnsBangCoSo(), sh = _ensureSheet(SH_QUYDOI_CS, QUYDOI_CS_H);
  var cu = {}, lr = sh.getLastRow();
  if (lr >= 2){
    var v = sh.getRange(2, 1, lr - 1, 1).getDisplayValues();
    for (var i = 0; i < v.length; i++) cu[_nnsKhoaCoSo(v[i][0])] = i + 2;
  }
  var them = 0, sua = 0, bo = [];
  Object.keys(banDo || {}).forEach(function(t){
    var ten = String(t || '').trim(), c = String(banDo[t] || '').replace(/^CS_/, '').trim();
    if (!ten || !c) return;
    /* Dòng tiền tố ("Posh*") lưu y nguyên cả dấu sao — `_nnsBanDoLuu` nhận ra nhờ dấu đó. */
    /* Quy đổi sang cơ sở KHÔNG có sheet `CS_` là ghi vào hồ sơ một cửa hàng không tồn tại — người
       đó không hiện ở đâu cả. Chặn ngay chỗ lưu, đừng để nó nằm trong bảng quy ước rồi lây mãi. */
    var that = bangCs[_nnsKhoaCoSo(c)];
    if (!that) { bo.push(ten + ' → ' + c + ' (không có sheet CS_' + c + ')'); return; }
    var h = cu[_nnsKhoaCoSo(ten)];
    if (h) { sh.getRange(h, 2).setValue(that); sua++; }
    else { sh.appendRow([ten, that, String(u.name || ''), _now()]); them++; }
  });
  return { ok:true, them:them, sua:sua, boQua:bo };
}
/** Bảng quy ước đang lưu — để giao diện bày ra sửa. */
function getQuyDoiCoSo(pin){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Không có quyền.', list:[] };
  var sh = _sheet(SH_QUYDOI_CS), out = [];
  if (sh && sh.getLastRow() >= 2){
    var v = sh.getRange(2, 1, sh.getLastRow() - 1, QUYDOI_CS_H.length).getDisplayValues();
    for (var i = 0; i < v.length; i++)
      if (String(v[i][0] || '').trim()) out.push({ ten:v[i][0], coSo:v[i][1], nguoi:v[i][2], tao:v[i][3] });
  }
  return { ok:true, list:out, dsCoSoApp: Object.keys(_nnsBangCoSo()).map(function(k){
             return _nnsBangCoSo()[k]; }).sort() };
}

/** CHỈ XEM — không ghi một ô nào. `banDo` = {tên trong file: tên cơ sở của app} anh tự điền. */
function xemTruocNhapNhanSu(pin, link, dsTab, banDo, opt){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Nhập nhân sự toàn chuỗi — ' + _QT_LOI };
  var d = _nnsDoc(link, dsTab);
  if (!d.ok) return d;
  var gop = _nnsGopTrung(d.dong, !!(opt && opt.gopTrung));
  d.dong = gop.dong;

  /* Bản đồ ĐÃ LƯU trước, cái anh vừa chọn trên màn hình đè lên sau. */
  var bangCs = _nnsBangCoSo(), bd = _nnsBanDoLuu();
  _nnsTronBanDo(bd, banDo);

  /* Hồ sơ đang có -> để biết ai MỚI, ai CẬP NHẬT, ai không nằm trong file. */
  var shNv = _nvSheet(), lastNv = shNv.getLastRow(), daCoMa = {}, tenCu = {};
  if (lastNv >= 2){
    var vn = shNv.getRange(2, 1, lastNv - 1, 3).getValues();
    for (var q = 0; q < vn.length; q++){
      var mq = String(vn[q][0] || '').trim(); if (!mq) continue;
      daCoMa[_chuanMa(mq)] = { ma:mq, ten:String(vn[q][1] || ''), coSo:String(vn[q][2] || '') };
    }
  }

  var demMa = {}, trungMa = [], thieu = [], coSoLa = {}, moi = [], capNhat = [], trongFile = {};
  d.dong.forEach(function(r){
    if (!r.ma || !r.ten){ thieu.push(r); return; }
    var k = _chuanMa(r.ma);
    demMa[k] = (demMa[k] || 0) + 1;
    trongFile[k] = 1;
    /* Cửa hàng: khớp thẳng tên sheet `CS_` trước, không khớp thì tra bảng quy đổi anh điền. */
    /* 🔴 Quy đổi cũng PHẢI ra một cơ sở CÓ SHEET `CS_`. Nhận bừa tên anh gõ trong bảng quy đổi
       là ghi vào hồ sơ một cửa hàng không tồn tại — mà ô "Cửa hàng" nay là nguồn DUY NHẤT, nên
       người đó **không hiện ở đâu cả**, im lặng. Bộ kiểm bắt được đúng chỗ này. */
    var kc = _nnsKhoaCoSo(r.cuaHangGoc), _q = _nnsTra(bd, r.cuaHangGoc);
    r.cuaHang = bangCs[kc] || (_q ? (bangCs[_nnsKhoaCoSo(_q)] || '') : '');
    if (!r.cuaHang){
      coSoLa[r.cuaHangGoc] = (coSoLa[r.cuaHangGoc] || 0) + 1;
      return;
    }
    (daCoMa[k] ? capNhat : moi).push(r);
  });
  Object.keys(demMa).forEach(function(k){
    if (demMa[k] < 2) return;
    trungMa.push({ ma:k, so:demMa[k],
                   dong: d.dong.filter(function(x){ return _chuanMa(x.ma) === k; })
                               .map(function(x){ return x.tab + '!' + x.hang + ' · ' + x.ten
                                                      + ' · ' + x.cuaHangGoc; }) });
  });
  /* Gom tên lạ theo TỪ ĐẦU ("Posh", "JP") — để anh gán MỘT lần cho cả nhóm thay vì vài chục lần. */
  var nhom = {};
  Object.keys(coSoLa).forEach(function(t){
    var tu = String(t).trim().split(/\s+/)[0] || t;
    nhom[tu] = nhom[tu] || { tu:tu, so:0, ten:[] };
    nhom[tu].so += coSoLa[t];
    if (nhom[tu].ten.length < 8) nhom[tu].ten.push(t);
  });
  var khongCoTrongFile = [];
  Object.keys(daCoMa).forEach(function(k){ if (!trongFile[k]) khongCoTrongFile.push(daCoMa[k]); });

  return { ok:true, tieuDe:d.tieuDe, tong:d.dong.length,
           gopTrung:gop.bat, daGop:gop.daGop,
           moi:moi.length, capNhat:capNhat.length,
           trungMa:trungMa, thieu:thieu.slice(0, 50), soThieu:thieu.length,
           coSoLa: Object.keys(coSoLa).map(function(t){ return { ten:t, so:coSoLa[t] }; })
                         .sort(function(a, b){ return b.so - a.so; }),
           nhomLa: Object.keys(nhom).map(function(k){ return nhom[k]; })
                         .sort(function(a, b){ return b.so - a.so; }),
           dsCoSoApp: Object.keys(bangCs).map(function(k){ return bangCs[k]; }).sort(),
           khongCoTrongFile: khongCoTrongFile,
           ghiDuoc: !trungMa.length && !Object.keys(coSoLa).length && !thieu.length };
}

/** GHI THẬT. Cập nhật theo Mã NV, KHÔNG xoá ai. Chặn nếu phần xem trước chưa sạch. */
function nhapNhanSu(pin, link, dsTab, banDo, opt){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Nhập nhân sự toàn chuỗi — ' + _QT_LOI };
  var xem = xemTruocNhapNhanSu(pin, link, dsTab, banDo, opt);
  if (!xem.ok) return xem;
  /* 🔴 Dựng lại kế hoạch từ máy chủ rồi mới ghi, KHÔNG tin cờ client gửi lên — và chặn khi còn
     bẩn. Ghi nửa chừng rồi báo lỗi là sheet nhân sự ở trạng thái dở dang, gỡ rất lâu. */
  if (!xem.ghiDuoc)
    return { ok:false, error:'Còn việc phải xử lý trước khi ghi: '
           + (xem.trungMa.length ? (xem.trungMa.length + ' mã trùng · ') : '')
           + (xem.coSoLa.length ? (xem.coSoLa.length + ' tên cửa hàng chưa quy đổi · ') : '')
           + (xem.soThieu ? (xem.soThieu + ' dòng thiếu mã/tên') : ''), xem:xem };

  var d = _nnsDoc(link, dsTab);
  if (!d.ok) return d;
  d.dong = _nnsGopTrung(d.dong, !!(opt && opt.gopTrung)).dong;   // CÙNG luật với phần xem trước
  var bangCs = _nnsBangCoSo(), bd = _nnsBanDoLuu();
  _nnsTronBanDo(bd, banDo);

  var lock = LockService.getScriptLock(); try { lock.waitLock(30000); } catch(e){}
  try {
    var sh = _nvSheet(), last = sh.getLastRow();
    var hang = {};                                   // mã chuẩn -> số hàng thật
    if (last >= 2){
      var v = sh.getRange(2, 1, last - 1, 1).getValues();
      for (var i = 0; i < v.length; i++){
        var m = String(v[i][0] || '').trim(); if (m) hang[_chuanMa(m)] = i + 2;
      }
    }
    var iCccd = 8 + NV_EXTRA.indexOf('cccd');        // cột (1-based) của CCCD
    var iCv   = 8 + NV_EXTRA.indexOf('position');    // cột Chức vụ — chứa "Phòng ban" của file
    var soMoi = 0, soSua = 0, them = [];
    d.dong.forEach(function(r){
      if (!r.ma || !r.ten) return;
      var kc = _nnsKhoaCoSo(r.cuaHangGoc), _q = _nnsTra(bd, r.cuaHangGoc);
      var cs = bangCs[kc] || (_q ? (bangCs[_nnsKhoaCoSo(_q)] || '') : '');
      if (!cs) return;      // cùng luật với phần xem trước — xem chú thích ở `xemTruocNhapNhanSu`
      var k = _chuanMa(r.ma), h = hang[k];
      if (h){
        /* 🔴 Ghi ĐÚNG 4 Ô. `setValues` cả hàng là xoá trắng ảnh thẻ + trạng thái + PIN máy. */
        sh.getRange(h, 2).setValue(r.ten);
        sh.getRange(h, 3).setValue(cs);
        if (r.cccd)     sh.getRange(h, iCccd).setValue("'" + r.cccd);   // giữ số 0 đầu
        if (r.phongBan) sh.getRange(h, iCv).setValue(r.phongBan);
        soSua++;
      } else {
        var d1 = new Array(NV_HEADERS.length).fill('');
        d1[0] = r.ma; d1[1] = r.ten; d1[2] = cs;
        if (r.cccd)     d1[iCccd - 1] = "'" + r.cccd;
        if (r.phongBan) d1[iCv - 1] = r.phongBan;
        d1[6] = _now();
        them.push(d1);
        hang[k] = -1;                                 // chặn trùng ngay trong cùng lô
        soMoi++;
      }
    });
    if (them.length) sh.getRange(sh.getLastRow() + 1, 1, them.length, NV_HEADERS.length).setValues(them);
    return { ok:true, moi:soMoi, capNhat:soSua, giuNguyen:xem.khongCoTrongFile.length,
             ghiChu:'Đã cập nhật theo Mã NV. KHÔNG xoá ai: ' + xem.khongCoTrongFile.length
                  + ' hồ sơ không có trong file vẫn giữ nguyên (Văn phòng gõ tay, người đã nghỉ…). '
                  + 'Ảnh thẻ / trạng thái đồng bộ / PIN máy của người cũ giữ nguyên.' };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

function guiYeuCauNVLoat(pin, coSo, link, tenSheet){
  var u = _requireAuth(pin);
  if (!_ycnvDuocGui(u)) return { ok:false, error:'Chỉ Cửa hàng trưởng / Quản lý / Admin gửi được yêu cầu.' };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!coSo) return { ok:false, error:'Chưa chọn cửa hàng.' };
  if (!u.isAdmin && !_canStation(u, coSo)) return { ok:false, error:'Bạn không phụ trách cửa hàng này.' };
  var id = _ssIdTuLink(link);
  if (!id) return { ok:false, error:'Link Google Sheet không đọc được. Dán cả link dạng '
                  + 'docs.google.com/spreadsheets/d/…, hoặc dán riêng phần id.' };
  var ss;
  try { ss = SpreadsheetApp.openById(id); }
  catch (e) { return { ok:false, error:'Không mở được bảng tính đó. Chia sẻ file cho tài khoản '
                     + 'chạy app (ít nhất quyền Xem) rồi thử lại. (' + e + ')' }; }
  var sh = tenSheet ? ss.getSheetByName(String(tenSheet)) : ss.getSheets()[0];
  if (!sh) return { ok:false, error:'Không thấy sheet "' + tenSheet + '" trong bảng tính đó.' };

  var cot = _ycnvCot(sh);
  if (!cot.ten) return { ok:false, error:'Sheet "' + sh.getName() + '" không có cột "Họ tên" trong '
                       + NVCS_DO_TOI_HANG + ' hàng đầu. Đặt tiêu đề "Họ tên" rồi thử lại.' };
  if (!cot.anh) return { ok:false, error:'Sheet "' + sh.getName() + '" không có cột "Ảnh". Ảnh thẻ là '
                       + 'BẮT BUỘC — thêm cột "Ảnh" (id/link Drive hoặc =IMAGE("…")) rồi thử lại.' };

  var lr = sh.getLastRow(), lc = sh.getLastColumn();
  var d1 = cot.hangTieuDe + 1;
  if (lr < d1) return { ok:true, coSo:coSo, tong:0, gui:0, bo:[], ds:[],
                        ghiChu:'Sheet chưa có dòng nào dưới tiêu đề.' };
  var soDong = lr - d1 + 1;
  if (soDong > YCNV_LOAT_TOI_DA)
    return { ok:false, error:'Sheet có ' + soDong + ' dòng — quá ' + YCNV_LOAT_TOI_DA
           + '. Chia nhỏ ra rồi gửi từng lô (Apps Script chỉ chạy được 6 phút mỗi lượt).' };
  var rng = sh.getRange(d1, 1, soDong, lc);
  var hien = rng.getDisplayValues(), ct = rng.getFormulas();
  var gui = 0, bo = [], ds = [];
  for (var i = 0; i < hien.length; i++){
    var dong = d1 + i;
    var lay = function(c){ return c ? String(hien[i][c - 1] || '').trim() : ''; };
    var ten = lay(cot.ten);
    /* Ô ảnh: công thức =IMAGE("…") thì `getDisplayValues` trả RỖNG (Sheets vẽ ảnh, không có chữ)
       -> phải đọc thêm `getFormulas`. Thiếu chỗ này là mọi dòng dùng =IMAGE đều bị báo
       "thiếu ảnh" trong khi ảnh đang hiện rõ trên sheet. */
    var oAnh = lay(cot.anh) || (cot.anh ? String(ct[i][cot.anh - 1] || '').trim() : '');
    if (!ten && !oAnh) continue;                                    // dòng trống -> bỏ im lặng
    if (!ten) { bo.push({ dong:dong, ten:'', ly:'thiếu Họ tên' }); continue; }
    var r = _ycnvThem(u, { coSo:coSo, ten:ten, photoFileId:oAnh,
                           pinMay:lay(cot.pinMay), gender:lay(cot.gender), ghiChu:lay(cot.ghiChu) });
    if (r && r.ok) { gui++; ds.push({ dong:dong, ten:ten, id:r.id, canh:r.canh || '' }); }
    else bo.push({ dong:dong, ten:ten, ly:(r && r.error) || 'không rõ' });
  }
  return { ok:true, coSo:coSo, sheet:sh.getName(), tong:(gui + bo.length), gui:gui,
           bo:bo.slice(0, 60), ds:ds.slice(0, 60), boBot:Math.max(0, bo.length - 60) };
}

/** Danh sách yêu cầu. `trangThai` rỗng = chỉ lấy đang chờ. */
function dsYeuCauNV(pin, trangThai){
  var u = _requireAuth(pin);
  var loc = String(trangThai || YCNV_CHO).trim();
  var ds = _ycnvDoc().filter(function(y){
    if (loc !== 'tat-ca' && y.trangThai !== loc) return false;
    return u.isAdmin || u.all || _canStation(u, y.coSo);
  });
  // Mới nhất lên trước — Admin cần thấy cái vừa gửi, không phải cuộn xuống đáy.
  ds.sort(function(a, b){ return String(b.taoLuc).localeCompare(String(a.taoLuc)); });
  return { ok:true, list:ds.slice(0, 80), tong:ds.length, quyenDuyet:_canQuanTriNV(u) };
}
/** Đếm yêu cầu đang chờ (cho chấm đỏ trên tab). Rẻ, gọi được mỗi lần mở tab. */
function demYeuCauNVCho(pin){
  var u = _requireAuth(pin);
  var n = _ycnvDoc().filter(function(y){
    return y.trangThai === YCNV_CHO && (u.isAdmin || u.all || _canStation(u, y.coSo)); }).length;
  return { ok:true, so:n, quyenDuyet:_canQuanTriNV(u) };
}

/**
 * Admin duyệt: gán mã rồi tạo hồ sơ thật. obj = {id, maNV, ten?, pinMay?, gender?, coSo?,
 *   photoDataUrl?, dongBoMay?} — mọi trường bỏ trống thì lấy theo yêu cầu.
 */
function duyetYeuCauNV(pin, obj){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Duyệt yêu cầu là gán MÃ NV mới cho cả chuỗi — ' + _QT_LOI };
  obj = obj || {};
  var id  = String(obj.id || '').trim();
  var maNV = String(obj.maNV || '').trim();
  if (!id) return { ok:false, error:'Thiếu mã yêu cầu.' };
  if (!maNV) return { ok:false, error:'Chưa gán Mã NV — đây là việc của Admin, CHT không đặt mã.' };

  var lock = LockService.getScriptLock();
  try { lock.waitLock(20000); } catch (e) {}
  try {
    var y = null, ds = _ycnvDoc();
    for (var i = 0; i < ds.length; i++) if (ds[i].id === id) { y = ds[i]; break; }
    if (!y) return { ok:false, error:'Không thấy yêu cầu "' + id + '".' };
    if (y.trangThai !== YCNV_CHO)
      return { ok:false, error:'Yêu cầu này đã ' + (y.trangThai === YCNV_DUYET ? 'được duyệt' : 'bị từ chối')
             + ' rồi (' + y.duyetLuc + ').' };
    var coSo = String(obj.coSo || y.coSo).replace(/^CS_/, '').trim();
    if (!u.isAdmin && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cửa hàng này.' };

    /* 🔴 MÃ ĐÃ CÓ HỒ SƠ -> CHẶN. `upsertEmployee` thấy mã đã tồn tại thì GHI ĐÈ dòng đó, nên duyệt
       bằng mã của người khác là xoá trắng hồ sơ người ta mà chẳng có gì báo. */
    var shNv = _nvSheet(), trung = _findRow(shNv, 1, maNV);
    if (trung) return { ok:false, error:'Mã "' + maNV + '" đã là của "' + String(trung.data[1] || '')
                      + '". Chọn mã khác — duyệt bằng mã này là ghi đè hồ sơ người ta.' };
    if (_tachMaNhiemVu(maNV))
      return { ok:false, error:'"' + maNV + '" là mã hàng nhiệm vụ thêm, không dùng làm mã nhân viên.' };

    var ten = String(obj.ten || y.ten).trim();
    /* Ảnh: ưu tiên ảnh Admin vừa tải lên; không thì đọc lại ảnh CHT đã gửi từ Drive rồi dựng
       data URL, để đi qua ĐÚNG đường `upsertEmployee` (nó tự lưu ảnh + xếp lệnh máy). */
    var anhData = String(obj.photoDataUrl || '');
    if (!anhData && y.anh) {
      try {
        var b = DriveApp.getFileById(_anhFileId(y.anh)).getBlob();
        anhData = 'data:' + (b.getContentType() || 'image/jpeg') + ';base64,'
                + Utilities.base64Encode(b.getBytes());
      } catch (e3) { anhData = ''; }
    }
    var kq = upsertEmployee(pin, {
      employeeNo:maNV, name:ten, station:coSo,
      machinePin:(obj.pinMay !== undefined ? obj.pinMay : y.pinMay),
      gender:(obj.gender !== undefined ? obj.gender : y.gender),
      photoDataUrl:anhData, mode:'add',
      /* 🔴 CCCD: Admin gửi gì thì lấy cái đó; không gửi thì lấy SỐ NHÂN VIÊN ĐÃ TỰ KHAI trong
         yêu cầu. Bỏ qua là số căn cước họ vừa gõ biến mất khỏi hồ sơ, rồi ô "Quên mã PIN?" tra
         mãi không ra — đúng người vừa đăng ký lại là người không tra được. */
      phone:obj.phone, dob:obj.dob, cccd:(obj.cccd !== undefined ? obj.cccd : y.cccd),
      address:obj.address,
      position:obj.position, startDate:obj.startDate, workStatus:obj.workStatus || 'Đang làm',
      contractType:obj.contractType,
      dongBoMay:(obj.dongBoMay === undefined ? true : !!obj.dongBoMay)
    });
    /* Tạo hồ sơ hỏng thì KHÔNG được đánh dấu đã duyệt — đánh dấu là yêu cầu biến mất khỏi danh
       sách chờ mà hồ sơ không có, người đó rơi vào khoảng trống không ai thấy. */
    if (!kq || !kq.ok) return { ok:false, error:'Không tạo được hồ sơ: ' + ((kq && kq.error) || 'không rõ') };

    var sh = _ycnvSheet();
    sh.getRange(y.row, 8).setValue(YCNV_DUYET);
    sh.getRange(y.row, 12).setValue(maNV);
    sh.getRange(y.row, 13).setValue(String(u.name || ''));
    sh.getRange(y.row, 14).setValue(_now());
    sh.getRange(y.row, 15).setValue(kq.ghiChu || 'Đã tạo hồ sơ.');
    return { ok:true, id:id, maNV:maNV, ten:ten, coSo:coSo, opId:kq.opId || '',
             ghiChu:kq.ghiChu || '', khongCoAnh:!anhData };
  } catch (err) { return { ok:false, error:String(err) }; }
  finally { lock.releaseLock(); }
}

/** Từ chối yêu cầu (kèm lý do). KHÔNG xoá dòng — còn để tra lại ai gửi, vì sao bị từ chối. */
function tuChoiYeuCauNV(pin, id, ly){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Chỉ Admin / Quản lý xử lý yêu cầu thêm người.' };
  id = String(id || '').trim();
  if (!id) return { ok:false, error:'Thiếu mã yêu cầu.' };
  var lock = LockService.getScriptLock();
  try { lock.waitLock(15000); } catch (e) {}
  try {
    var y = null, ds = _ycnvDoc();
    for (var i = 0; i < ds.length; i++) if (ds[i].id === id) { y = ds[i]; break; }
    if (!y) return { ok:false, error:'Không thấy yêu cầu "' + id + '".' };
    if (y.trangThai !== YCNV_CHO) return { ok:false, error:'Yêu cầu này đã xử lý rồi.' };
    if (!u.isAdmin && !_canStation(u, y.coSo)) return { ok:false, error:'Không có quyền cửa hàng này.' };
    var sh = _ycnvSheet();
    sh.getRange(y.row, 8).setValue(YCNV_TUCHOI);
    sh.getRange(y.row, 13).setValue(String(u.name || ''));
    sh.getRange(y.row, 14).setValue(_now());
    sh.getRange(y.row, 15).setValue(String(ly || '').trim() || 'Không nêu lý do');
    return { ok:true, id:id };
  } catch (err) { return { ok:false, error:String(err) }; }
  finally { lock.releaseLock(); }
}

/* ===== ĐỀ XUẤT ĐỔI MÃ HÀNG LOẠT (tên trùng, ID khác) ====================================
 * Anh Thắng 03/08/2026: *"Để cập nhật hàng loạt nv nếu nhiều, tôi sẽ bổ sung tên nhân viên và id
 * trong sheet NV của cơ sở đó, xong bạn tự so sánh tên cùng nhưng ID khác, thì báo lên đề xuất,
 * tôi bấm nút đồng bộ"*.
 *
 * Chiều dữ liệu: mã MỚI lấy từ `NV_<cơ sở>` (anh gõ tay), mã CŨ là mã trong hồ sơ `NhanVien`.
 *
 * 🔴 GHÉP THEO TÊN LÀ CHỖ DỄ GÂY HỎNG NHẤT. Tên người Việt trùng nhiều: ghép sai là đổi mã của
 *    NGƯỜI KHÁC, kéo theo cả lịch sử chấm công sang người đó — mất lương của cả hai.
 *    Nên chỉ đề xuất khi tên đó ứng với ĐÚNG MỘT hồ sơ VÀ ĐÚNG MỘT dòng trong `NV_`. Mọi ca còn
 *    lại trả riêng ở `khongTuQuyet` kèm lý do, để anh tự xử bằng nút đổi mã từng người.
 * 🔴 Chỉ xét hồ sơ ghi ĐÚNG cửa hàng này. Quét cả `NhanVien` là kéo người ở cửa hàng khác vào,
 *    mà trùng tên giữa hai cửa hàng thì càng dễ.
 */
function _chuanTenNguoi(s){
  return _khongDau(s).replace(/\s+/g, ' ').trim();
}

/* ===== MÃ CHẠY SONG SONG — khai báo, đọc, bỏ khai ==========================================
 * Anh Thắng: *"Có một số nhân viên có 2 mã cũ và mới, hiện có 1 số hệ thống chưa kết nối hoặc
 * chưa đẩy xuống được thì anh vẫn muốn chạy song song 2 mã đó."*
 *
 * 🔴 CẶP LÀ ĐỐI XỨNG: khai A↔B thì tra B cũng phải ra A. Lưu một chiều rồi tra một chiều là
 *    nửa số lần tra trả về "chưa khai" — cảnh báo lại hiện, đúng thứ anh muốn tắt.
 * 🔴 KHAI ≠ GỘP. Khai song song chỉ TẮT CẢNH BÁO và nói rõ hai mã là một người. Nó KHÔNG cộng
 *    công/lương của hai mã lại — hai hồ sơ vẫn là hai dòng lương riêng. Muốn gộp lương là việc
 *    khác hẳn, phải đối chiếu số cũ/số mới trên dữ liệu thật trước khi làm.
 */
function _maSsSheet(){ return _ensureSheet(SH_MA_SS, MA_SS_H); }

/* ===== MÃ CHÍNH (dài) vs MÃ TẠM (ngắn) ====================================================
 * Anh Thắng: *"mã chính là mã dài, mã ngắn làm tạm, mã dài thì 1 nhân viên chỉ 1 mã thôi"*.
 *     mã chính (dài): MNNV2KVC0104 · MNNV2MTD0007 …  -> MỖI NGƯỜI ĐÚNG MỘT
 *     mã tạm (ngắn) : TUTP02 · TP15 …                 -> chỉ sống trên máy cũ chưa đồng bộ
 * Phân biệt bằng ĐỘ DÀI vì đó là thứ chắc chắn đúng với dữ liệu đang có: mã chính 12 ký tự, mã
 * tạm ≤ 6 — khoảng cách rộng nên ngưỡng 10 không chạm vào ca nào.
 * ⚠️ Đừng đổi sang "dò tiền tố MNNV": chuỗi này có nhiều đợt đặt mã, tiền tố không chắc bằng.
 * ⚠️ Nếu sau này có mã chính kiểu khác thì SỬA ĐÚNG HÀM NÀY — đừng viết lại phép so ở chỗ khác. */
var MA_DAI_TU_KY_TU = 10;
function _laMaDai(ma){ return String(ma == null ? '' : ma).trim().length >= MA_DAI_TU_KY_TU; }

/** {mã chuẩn -> [ {ma, ten, lyDo} … ]} — mọi mã đang được khai là cùng một người. */
function _maSongSongMap(){
  var m = {};
  try {
    var sh = _sheet(SH_MA_SS); if (!sh || sh.getLastRow() < 2) return m;
    var v = sh.getRange(2, 1, sh.getLastRow() - 1, MA_SS_H.length).getValues();
    for (var i = 0; i < v.length; i++){
      var a = String(v[i][0] || '').trim(), b = String(v[i][1] || '').trim();
      if (!a || !b) continue;
      var ka = _chuanMa(a), kb = _chuanMa(b);
      if (ka === kb) continue;
      var ten = String(v[i][2] || '').trim(), ly = String(v[i][3] || '').trim();
      (m[ka] = m[ka] || []).push({ ma:b, ten:ten, lyDo:ly });      // hai chiều
      (m[kb] = m[kb] || []).push({ ma:a, ten:ten, lyDo:ly });
    }
  } catch(e){}
  return m;
}
/** Hai mã này đã được khai là một người chưa? */
function _laMaSongSong(map, ma1, ma2){
  var ds = (map || {})[_chuanMa(ma1)] || [], k = _chuanMa(ma2);
  for (var i = 0; i < ds.length; i++) if (_chuanMa(ds[i].ma) === k) return true;
  return false;
}

/** Khai một cặp mã là CÙNG MỘT NGƯỜI, cố ý chạy song song. */
function khaiMaSongSong(pin, obj){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Mã song song ảnh hưởng cả chuỗi — ' + _QT_LOI };
  obj = obj || {};
  var a = String(obj.maA || '').trim(), b = String(obj.maB || '').trim();
  if (!a || !b) return { ok:false, error:'Thiếu mã.' };
  if (_chuanMa(a) === _chuanMa(b)) return { ok:false, error:'Hai mã giống nhau, không có gì để khai.' };
  /* 🔴 Không khai hàng nhiệm vụ thêm (`…-TG`): nó vốn ĐÃ là một dòng phụ của cùng người, khai
     nữa là mô tả một quan hệ không có thật rồi báo cáo đọc theo. */
  if (_tachMaNhiemVu(a) || _tachMaNhiemVu(b))
    return { ok:false, error:'Mã có đuôi nhiệm vụ (-TT/-TG) vốn đã là dòng phụ của cùng người, không cần khai.' };
  /* 🔴 HAI MÃ DÀI = HAI NGƯỜI. Anh Thắng: *"mã dài thì 1 nhân viên chỉ 1 mã thôi"*. Khai hai mã
     chính là một người tức là một trong hai mã chính bị cấp sai — phải sửa chỗ cấp mã, không
     phải khai song song để che đi. Cho khai là từ đó mọi báo cáo hiểu sai vĩnh viễn. */
  if (_laMaDai(a) && _laMaDai(b))
    return { ok:false, error:'"' + a + '" và "' + b + '" đều là MÃ CHÍNH (mã dài). '
           + 'Mỗi người chỉ được MỘT mã chính — nếu đúng là một người thì một trong hai mã đã cấp '
           + 'nhầm, phải xoá/đổi mã đó chứ không khai song song. '
           + 'Khai song song chỉ dành cho cặp mã chính (dài) ↔ mã tạm (ngắn).' };
  var lock = LockService.getScriptLock(); try { lock.waitLock(15000); } catch(e){}
  try {
    var sh = _maSsSheet();
    var map0 = _maSongSongMap();
    if (_laMaSongSong(map0, a, b))
      return { ok:false, error:'Cặp này đã khai rồi.', daCo:true };
    /* Mã TẠM chỉ được thuộc về MỘT mã chính. Gắn nó vào mã chính thứ hai là công chấm bằng mã
       tạm đó không biết về ai — và hai người cùng nhận. */
    var _ngan = _laMaDai(a) ? b : (_laMaDai(b) ? a : ''), _dai = _laMaDai(a) ? a : b;
    if (_ngan && _laMaDai(_dai)){
      var daGan = (map0[_chuanMa(_ngan)] || []).filter(function(x){ return _laMaDai(x.ma); });
      if (daGan.length && _chuanMa(daGan[0].ma) !== _chuanMa(_dai))
        return { ok:false, error:'Mã tạm "' + _ngan + '" đang gắn với mã chính "' + daGan[0].ma
               + '". Một mã tạm chỉ thuộc về một người — bỏ khai cặp cũ trước.' };
    }
    sh.appendRow([a, b, String(obj.ten || '').trim(), String(obj.lyDo || '').trim(),
                  String((u && u.name) || ''), _now()]);
    _MACU_LUOT = null;      // bộ nhớ 1 lượt chạy đã cũ — xem `_maCuVeMaChinh`
    return { ok:true, maA:a, maB:b };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

/** Bỏ khai (hai máy đã đồng bộ xong, không cần song song nữa). */
function boMaSongSong(pin, maA, maB){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Mã song song ảnh hưởng cả chuỗi — ' + _QT_LOI };
  var ka = _chuanMa(maA), kb = _chuanMa(maB);
  if (!ka || !kb) return { ok:false, error:'Thiếu mã.' };
  var lock = LockService.getScriptLock(); try { lock.waitLock(15000); } catch(e){}
  try {
    var sh = _sheet(SH_MA_SS); if (!sh || sh.getLastRow() < 2) return { ok:false, error:'Chưa khai cặp nào.' };
    var v = sh.getRange(2, 1, sh.getLastRow() - 1, MA_SS_H.length).getValues();
    // Xoá TỪ DƯỚI LÊN: xoá từ trên xuống là mọi chỉ số phía dưới tụt một dòng -> xoá nhầm.
    var xoa = 0;
    for (var i = v.length - 1; i >= 0; i--){
      var x = _chuanMa(v[i][0]), y = _chuanMa(v[i][1]);
      if ((x === ka && y === kb) || (x === kb && y === ka)) { sh.deleteRow(i + 2); xoa++; }
    }
    if (!xoa) return { ok:false, error:'Không thấy cặp này trong bảng.' };
    _MACU_LUOT = null;      // bộ nhớ 1 lượt chạy đã cũ — xem `_maCuVeMaChinh`
    return { ok:true, xoa:xoa };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

/* ===== DỌN TÀI KHOẢN TRÙNG — MỘT NGƯỜI MỘT MÃ ĐĂNG NHẬP (06/08/2026) ======================
 * Anh Thắng: *"NGUYEN HUU THO đang có 3 tài khoản cùng mã: dọn lại cho anh, ai 2 mã cũng dọn lại
 * về 1 mã, 1 mã đăng nhập, rồi vào chọn cơ sở chấm công. Nên 1 nhân viên lấy 1 mã thôi."*
 *
 * VÌ SAO TRƯỚC ĐÂY SINH RA TRÙNG: mỗi dòng `PhanQuyen` chỉ khai được MỘT "Cơ sở chấm công online",
 * nên người làm 2 nơi buộc phải có 2 tài khoản.
 * 🔴 Nay gộp được là vì `chamCongOnline` ĐÃ nhận `coSoChon` và đối chiếu bằng `_dsCoSoCuaNv` —
 *    danh sách cơ sở lấy từ NƠI HỌ THẬT SỰ CÓ DÒNG trong các sheet `CS_`, không phải từ ô
 *    "Cơ sở chấm công online". Nên một tài khoản vẫn chấm được ở mọi cơ sở của họ.
 *    ⚠️ Nếu ai đó sau này sửa `chamCongOnline` về lấy đúng ô `coSoOnline`, thì gộp tài khoản
 *       thành CẮT MẤT chỗ chấm công. Hai thứ này ràng nhau, đừng đụng một cái mà quên cái kia.
 */

/** Các mã NV đang có NHIỀU HƠN MỘT dòng trong `PhanQuyen`. */
function timTaiKhoanTrung(pin){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin xem được danh sách tài khoản trùng.' };
  var sh = _pqNoiCot(), last = sh.getLastRow();
  if (last < 2) return { ok:true, list:[] };
  var v = sh.getRange(2, 1, last - 1, PQ_H.length).getValues();
  var theoMa = {}, thuTu = [];
  for (var i = 0; i < v.length; i++){
    var p = String(v[i][0] || '').trim(); if (!p) continue;
    var ma = String(v[i][4] || '').trim(); if (!ma) continue;   // không gắn NV -> không xét
    var k = _chuanMa(ma);
    if (!theoMa[k]) { theoMa[k] = []; thuTu.push(k); }
    theoMa[k].push({ hang:i + 2, pin:p, ten:String(v[i][1] || ''), vaiTro:String(v[i][2] || ''),
                     cuaHang:String(v[i][3] || ''), maNV:ma,
                     coSoOnline:String(v[i][5] || '').replace(/^CS_/, '').trim() });
  }
  var out = [];
  thuTu.forEach(function(k){
    var ds = theoMa[k]; if (ds.length < 2) return;
    /* Vai trò KHÁC NHAU giữa các dòng là chuyện phải nói ra: gộp lại nghĩa là chọn MỘT vai, tức
       là người đó lên hoặc xuống quyền. Không được để nó xảy ra âm thầm. */
    var vai = {}; ds.forEach(function(x){ vai[String(x.vaiTro).toUpperCase()] = 1; });
    out.push({ maNV:ds[0].maNV, ten:ds[0].ten, soTaiKhoan:ds.length,
               lechVaiTro:(Object.keys(vai).length > 1), taiKhoan:ds });
  });
  return { ok:true, list:out };
}

/**
 * Gộp mọi tài khoản của MỘT mã NV về đúng một dòng.
 * 🔴 GIỮ dòng anh chọn, HỢP NHẤT "Cửa hàng phụ trách" của tất cả các dòng vào đó — không thì
 *    gộp xong người ta mất quyền xem cửa hàng mà tài khoản kia đang có.
 * 🔴 Xoá TỪ DƯỚI LÊN, và xoá cache phiên của những PIN bị bỏ để chúng hết hiệu lực NGAY
 *    (không thì PIN cũ còn đăng nhập được tới 60 giây nữa).
 */
function gopTaiKhoan(pin, obj){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin mới gộp tài khoản.' };
  obj = obj || {};
  var maNV = String(obj.maNV || '').trim();
  var giu  = String(obj.pinGiuLai || '').trim();
  if (!maNV || !giu) return { ok:false, error:'Thiếu mã NV hoặc PIN muốn giữ.' };
  var lock = LockService.getScriptLock(); try { lock.waitLock(20000); } catch(e){}
  try {
    var sh = _pqNoiCot(), last = sh.getLastRow();
    if (last < 2) return { ok:false, error:'Bảng phân quyền trống.' };
    var v = sh.getRange(2, 1, last - 1, PQ_H.length).getValues();
    var k = _chuanMa(maNV), hangGiu = 0, boDi = [], ch = {}, coSoDs = {};
    for (var i = 0; i < v.length; i++){
      if (_chuanMa(v[i][4]) !== k) continue;
      var p = String(v[i][0] || '').trim();
      String(v[i][3] || '').split(',').forEach(function(x){ x = x.trim(); if (x) ch[x] = 1; });
      var cs = String(v[i][5] || '').replace(/^CS_/, '').trim(); if (cs) coSoDs[cs] = 1;
      if (p === giu && !hangGiu) hangGiu = i + 2; else boDi.push({ hang:i + 2, pin:p });
    }
    if (!hangGiu) return { ok:false, error:'Không thấy PIN "' + giu + '" gắn với mã ' + maNV + '.' };
    if (!boDi.length) return { ok:true, xoa:0, ghiChu:'Mã này chỉ có một tài khoản, không cần gộp.' };

    // Hợp nhất cửa hàng phụ trách vào dòng giữ lại. Cơ sở chấm công online lấy cái của dòng giữ,
    // thiếu thì lấy cái đầu tiên tìm được — dù sao ô đó nay chỉ còn là MẶC ĐỊNH, người dùng chọn
    // cơ sở ngay lúc chấm công (`chamCongOnline` đối chiếu bằng `_dsCoSoCuaNv`).
    var chList = Object.keys(ch).sort().join(', ');
    sh.getRange(hangGiu, 4).setValue(chList);
    var csGiu = String(sh.getRange(hangGiu, 6).getValue() || '').replace(/^CS_/, '').trim();
    if (!csGiu){ var ds0 = Object.keys(coSoDs); if (ds0.length) sh.getRange(hangGiu, 6).setValue(ds0[0]); }

    boDi.sort(function(a, b){ return b.hang - a.hang; });     // TỪ DƯỚI LÊN
    boDi.forEach(function(x){ sh.deleteRow(x.hang); _clearAuthCache(x.pin); });
    _clearAuthCache(giu);
    return { ok:true, maNV:maNV, pinGiuLai:giu, xoa:boDi.length,
             pinDaXoa:boDi.map(function(x){ return x.pin; }), cuaHang:chList };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

/** Danh sách cặp đã khai — để giao diện bày ra cho anh xem / bỏ khai. */
function getMaSongSong(pin){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Không có quyền.' };   // bảng mã song song là
  // của CẢ CHUỖI, không phải việc của một cửa hàng -> giữ ở mức quản trị, kể cả chỉ ĐỌC.
  var sh = _sheet(SH_MA_SS), out = [];
  if (sh && sh.getLastRow() >= 2){
    var v = sh.getRange(2, 1, sh.getLastRow() - 1, MA_SS_H.length).getValues();
    for (var i = 0; i < v.length; i++){
      var a = String(v[i][0] || '').trim(), b = String(v[i][1] || '').trim();
      if (!a || !b) continue;
      out.push({ maA:a, maB:b, ten:String(v[i][2] || ''), lyDo:String(v[i][3] || ''),
                 nguoiKhai:String(v[i][4] || ''), tao:String(v[i][5] || '') });
    }
  }
  return { ok:true, list:out };
}
function deXuatDoiMa(pin, coSo){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Chỉ Admin / Quản lý xem được đề xuất đổi mã.' };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!coSo) return { ok:false, error:'Thiếu cửa hàng.' };
  if (!u.isAdmin && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cửa hàng này.' };

  var nvcs = _nvcsDoc(coSo);
  if (!nvcs.co) return { ok:false, error:'Chưa có sheet ' + _nvcsTen(coSo) + '.', tenSheetCan:_nvcsTen(coSo) };
  if (nvcs.loi) return { ok:false, error:nvcs.loi };

  // Hồ sơ: mã -> {ten, coSo}; và tên -> danh sách mã (chỉ cửa hàng này)
  var sh = _nvSheet(), hoSoTheoMa = {}, theoTenHs = {}, maToanBo = {};
  if (sh && sh.getLastRow() >= 2){
    var v = sh.getRange(2, 1, sh.getLastRow() - 1, 3).getValues();
    for (var i = 0; i < v.length; i++){
      var ma = String(v[i][0] || '').trim(); if (!ma) continue;
      var ten = String(v[i][1] || '').trim();
      var cs  = String(v[i][2] || '').replace(/^CS_/, '').trim();
      maToanBo[_chuanMa(ma)] = ten || ma;                      // để biết mã mới có bị người khác dùng
      if (_chuanMa(cs) !== _chuanMa(coSo)) continue;
      hoSoTheoMa[_chuanMa(ma)] = { ma:ma, ten:ten };
      var k = _chuanTenNguoi(ten); if (!k) continue;
      (theoTenHs[k] = theoTenHs[k] || []).push({ ma:ma, ten:ten });
    }
  }
  // Sheet NV_: tên -> danh sách mã (bỏ hàng -TG)
  var theoTenNv = {}, maTrongNv = {};
  nvcs.list.forEach(function(x){
    if (_tachMaNhiemVu(x.ma)) return;
    maTrongNv[_chuanMa(x.ma)] = 1;
    var k = _chuanTenNguoi(x.ten); if (!k) return;
    (theoTenNv[k] = theoTenNv[k] || []).push({ ma:x.ma, ten:x.ten });
  });

  /* Cặp mã ĐÃ KHAI chạy song song thì không phải "xung đột" nữa — đừng bày cảnh báo đỏ mỗi
     lần dò. Cảnh báo lặp mãi cho một chuyện anh đã quyết là kiểu làm người ta quen bỏ qua cảnh
     báo, rồi bỏ qua luôn cái cảnh báo THẬT. Vẫn liệt kê ở `songSong` để không biến mất. */
  var maSs = _maSongSongMap();
  var deXuat = [], khongTuQuyet = [], songSong = [];
  Object.keys(theoTenNv).forEach(function(k){
    var ben2 = theoTenNv[k], ben1 = theoTenHs[k] || [];
    /* Mã đã khớp -> không có việc gì (đây là phần lớn danh sách, đừng bày ra cho rối).
       🔴 PHẢI kiểm mã đó là của CHÍNH NGƯỜI NÀY, không chỉ "mã có tồn tại trong hồ sơ". Bản đầu
          chỉ kiểm tồn tại, nên khi mã mới đang là của NGƯỜI KHÁC thì bị coi là "đã khớp" rồi
          IM LẶNG bỏ qua — che mất đúng cái xung đột nguy hiểm nhất (đổi vào là gộp hai người).
          Test (B) bắt được. */
    var daKhop = ben2.some(function(b){
      var h = hoSoTheoMa[_chuanMa(b.ma)];
      return h && _chuanTenNguoi(h.ten) === k;
    });
    if (daKhop && ben2.length === 1) return;
    if (!ben1.length) return;      // chỉ có trong NV_, chưa có hồ sơ -> việc của "chưa có hồ sơ"
    if (ben1.length > 1 || ben2.length > 1){
      khongTuQuyet.push({ ten:ben2[0].ten,
        maHoSo:ben1.map(function(x){ return x.ma; }), maSheet:ben2.map(function(x){ return x.ma; }),
        ly:'Tên này ứng với ' + ben1.length + ' hồ sơ và ' + ben2.length + ' dòng trong sheet — '
           + 'không tự quyết được ai là ai. Dùng nút đổi mã trong hồ sơ từng người.' });
      return;
    }
    var cu = ben1[0], moi = ben2[0];
    if (_chuanMa(cu.ma) === _chuanMa(moi.ma)) return;          // đã đúng mã rồi
    // Mã mới đã là của người KHÁC -> đổi vào là gộp hai người. Không đề xuất, báo riêng.
    var chu = maToanBo[_chuanMa(moi.ma)];
    if (chu && _chuanMa(moi.ma) !== _chuanMa(cu.ma)){
      // Anh đã khai đây là một người, cố ý chạy song song -> không cảnh báo nữa.
      if (_laMaSongSong(maSs, cu.ma, moi.ma)){
        songSong.push({ ten:moi.ten, maCu:cu.ma, maMoi:moi.ma });
        return;
      }
      khongTuQuyet.push({ ten:moi.ten, maHoSo:[cu.ma], maSheet:[moi.ma], choSongSong:true,
        ly:'Mã "' + moi.ma + '" đã là của hồ sơ "' + chu + '" — đổi vào là gộp hai người.' });
      return;
    }
    /* 🔴 KHÔNG đề xuất đổi MÃ CHÍNH (dài) THÀNH MÃ TẠM (ngắn). Anh Thắng: *"mã chính là mã dài,
       mã ngắn làm tạm"*. Hồ sơ đang mang mã chính mà sheet ghi mã ngắn thì gần như chắc là anh
       gõ tạm trong sheet, chứ không phải muốn hạ mã chính xuống. Đổi vào là mất mã chính của
       người đó — mà đổi mã ghi lại cả lịch sử chấm công, không lùi được. */
    if (_laMaDai(cu.ma) && !_laMaDai(moi.ma)){
      khongTuQuyet.push({ ten:moi.ten, maHoSo:[cu.ma], maSheet:[moi.ma], choSongSong:true,
        ly:'Hồ sơ đang mang MÃ CHÍNH "' + cu.ma + '", còn sheet ghi mã ngắn "' + moi.ma
           + '" — không hạ mã chính xuống mã tạm. Nếu máy cũ vẫn dùng mã ngắn thì khai chạy song song.' });
      return;
    }
    deXuat.push({ maCu:cu.ma, maMoi:moi.ma, tenHoSo:cu.ten, tenSheet:moi.ten,
                  lechTen:(_chuanTenNguoi(cu.ten) !== _chuanTenNguoi(moi.ten)) });
  });
  /* Người có hồ sơ ở cửa hàng này mà KHÔNG có tên nào khớp trong sheet -> nêu ra, đừng im lặng:
     có thể anh gõ tên khác (viết tắt) nên không ghép được, chứ không phải người đó đã xong. */
  var khongThayTrongSheet = [];
  Object.keys(hoSoTheoMa).forEach(function(mk){
    var h = hoSoTheoMa[mk];
    if (maTrongNv[mk]) return;                                  // mã đã có trong sheet -> ổn
    var k = _chuanTenNguoi(h.ten);
    if (k && theoTenNv[k]) return;                              // đã vào nhóm đề xuất / không tự quyết
    /* Mã này đã khai song song với một mã ĐANG có trong sheet -> người đó KHÔNG bị bỏ sót,
       chỉ là đang chấm bằng mã kia. Kêu "thiếu trong sheet" là bắt anh đi thêm một dòng thừa. */
    var _ss = maSs[mk] || [];
    if (_ss.some(function(x){ return !!maTrongNv[_chuanMa(x.ma)]; })) return;
    khongThayTrongSheet.push({ ma:h.ma, ten:h.ten });
  });

  return { ok:true, coSo:coSo, tenSheet:_nvcsTen(coSo),
           deXuat:deXuat, khongTuQuyet:khongTuQuyet, songSong:songSong,
           khongThayTrongSheet:khongThayTrongSheet,
           soDongSheet:nvcs.list.length };
}

/** Xem trước: trả ĐÚNG kế hoạch sẽ ghi, để hộp xác nhận không phải đoán. Không ghi gì. */
function xemTruocDoiMa(pin, obj){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Đổi mã là xoá rồi tạo lại người trên MÁY CHẤM CÔNG — ' + _QT_LOI };
  obj = obj || {};
  var coSo = String(obj.coSo || '').replace(/^CS_/, '').trim();
  if (!u.isAdmin && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cửa hàng này.' };
  try {
    var kh = _doiMaKeHoach(obj.maCu, obj.maMoi, coSo);
    return { ok:!kh.loi, error:kh.loi, ke:kh };
  } catch (e) { return { ok:false, error:String(e) }; }
}

/**
 * Đổi mã thật. obj = { maCu, maMoi, coSo, tenMoi?, dongBoMay?, boQuaAnh? }
 * `boQuaAnh` = anh Thắng đã đọc cảnh báo "chưa có ảnh" mà vẫn muốn xoá khỏi máy.
 */
function doiMaNhanVien(pin, obj){
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Đổi mã là xoá rồi tạo lại người trên MÁY CHẤM CÔNG — ' + _QT_LOI };
  obj = obj || {};
  var coSo = String(obj.coSo || '').replace(/^CS_/, '').trim();
  if (!u.isAdmin && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cửa hàng này.' };
  var dongBoMay = (obj.dongBoMay === undefined || obj.dongBoMay === null) ? true : !!obj.dongBoMay;

  var lock = LockService.getScriptLock();
  try { lock.waitLock(20000); } catch (le) {}
  try {
    var kh = _doiMaKeHoach(obj.maCu, obj.maMoi, coSo);
    if (kh.loi) return { ok:false, error:kh.loi };
    var maCu = kh.maCu, maMoi = kh.maMoi;
    var tenMoi = (obj.tenMoi === undefined || obj.tenMoi === null || !String(obj.tenMoi).trim())
                 ? kh.hoSo.ten : String(obj.tenMoi).trim();
    if (_nvcsLaTieuDe(maMoi, tenMoi))
      return { ok:false, error:'"' + maMoi + ' / ' + tenMoi + '" là hàng tiêu đề, không phải nhân viên.' };
    if (dongBoMay && !kh.hoSo.anh && !obj.boQuaAnh)
      return { ok:false, canAnh:true, error:'Hồ sơ chưa có ảnh: xoá khỏi máy là mất khuôn mặt đã '
             + 'đăng ký, không dựng lại được. Tải ảnh lên trước, hoặc xác nhận vẫn đổi.' };
    /* 🔴 Người làm NHIỀU cửa hàng: đổi mã phải sửa CẢ các cửa hàng kia, nên người chỉ quản một
       cửa hàng thì không đủ quyền. Cho qua là Quản lý POSH ghi được vào sheet của JP. */
    if (!u.isAdmin) {
      var ngoai = kh.coSoLienQuan.filter(function(c){ return !_canStation(u, c); });
      if (ngoai.length)
        return { ok:false, error:'Người này còn có mặt ở ' + ngoai.join(', ')
               + ' — đổi mã phải sửa cả những nơi đó, nên chỉ Admin làm được.' };
    }

    /* Thứ tự ghi: HÀNG ĐỢI MÁY TRƯỚC, sheet sau.
       Xếp lệnh chỉ là appendRow + _fbPut, hỏng thì hỏng ngay và chưa sheet nào bị đụng. Nếu ghi
       sheet trước rồi xếp lệnh hỏng thì dữ liệu đã mang mã mới mà máy vẫn giữ mã cũ — lệch âm
       thầm, đúng kiểu khó thấy nhất. */
    var opXoa = [], opTao = [], anhId = _anhFileId(kh.hoSo.anh), anhB64 = _anhB64(kh.hoSo.anh);
    if (dongBoMay) kh.mayCoSo.forEach(function(cs){
      opXoa.push(_enqueue('delete', maCu, kh.hoSo.ten, '', '', cs));
      opTao.push(_enqueue('add', maMoi, tenMoi, kh.hoSo.pinMay, anhId, cs, obj.gender, anhB64));
    });

    // 1. Hồ sơ: đổi mã (+ tên nếu gửi kèm), đặt lại trạng thái đồng bộ
    var shNv = _nvSheet();
    shNv.getRange(kh.hoSo.row, 1).setValue(maMoi);
    if (tenMoi !== kh.hoSo.ten) shNv.getRange(kh.hoSo.row, 2).setValue(tenMoi);
    shNv.getRange(kh.hoSo.row, 6).setValue(dongBoMay ? 'pending' : kh.hoSo.tt);
    shNv.getRange(kh.hoSo.row, 7).setValue(_now());

    /* 2 + 3. Lịch sử chấm công ở MỌI cửa hàng. CHỈ ghi cột A (tên) và B (mã) — TUYỆT ĐỐI không
       đụng cột C trở đi: từ cột C là giờ vào/ra theo công thức `3 + ngày*5`. */
    var soHang = 0, dsSheetCs = [];
    kh.cs.forEach(function(kt){
      var sh = _sheet(kt.tenSheet); if (!sh) return;
      kt.hang.forEach(function(h){
        sh.getRange(h.row, 2).setValue(h.maMoi);
        // Hàng phụ giữ hậu tố tên (" — Trực Ghế"), chỉ đổi phần tên người ở đầu.
        var tenO = String(sh.getRange(h.row, 1).getValue() || '');
        var vt = tenO.indexOf(NV_NGAN_CACH);
        sh.getRange(h.row, 1).setValue(vt > 0 ? (tenMoi + tenO.slice(vt)) : tenMoi);
        soHang++;
      });
      dsSheetCs.push(kt.tenSheet + ' (' + kt.hang.length + ')');
    });

    // 4. MỌI sheet nhân sự cố định
    var soNvcs = 0, dsSheetNv = [];
    kh.nvcs.forEach(function(kt){
      var sh = _sheet(kt.tenSheet); if (!sh) return;
      kt.hang.forEach(function(h){ sh.getRange(h.row, h.cot).setValue(h.maMoi); soNvcs++; });
      dsSheetNv.push(kt.tenSheet + ' (' + kt.hang.length + ')');
    });

    // 5. Tài khoản web
    var shPq = _pqNoiCot(), soPq = 0;
    kh.pq.forEach(function(t){
      shPq.getRange(t.row, 5).setValue(maMoi);
      _clearAuthCache(t.pin); soPq++;
    });

    return { ok:true, maCu:maCu, maMoi:maMoi, opIdXoa:opXoa, opIdTao:opTao,
             soHangCs:soHang, soHangNv:soNvcs, soTaiKhoan:soPq,
             tenSheetCs:dsSheetCs.join(', '), tenSheetNv:dsSheetNv.join(', '),
             mayCoSo:kh.mayCoSo, coSoLienQuan:kh.coSoLienQuan, canh:kh.canh,
             ghiChu: dongBoMay
               ? ('Đã xếp ' + (opXoa.length * 2) + ' lệnh (xoá "' + maCu + '" rồi tạo "' + maMoi
                  + '") trên máy: ' + kh.mayCoSo.join(', ')
                  + '. Máy nhận trong ~30 giây; kiểm cột "Trên máy chấm công" sau đó.')
               : 'KHÔNG gửi lệnh xuống máy (đã bỏ tích) — máy vẫn giữ mã cũ.' };
  } catch (err) {
    return { ok:false, error:String(err) };
  } finally { lock.releaseLock(); }
}

/* ===== NGHỈ VIỆC =====
 * Yêu cầu anh Thắng (01/08/2026): *"chọn nhân viên nghỉ việc (để ẩn xuống dưới); nếu trong tháng
 * thì vẫn hiện bên ngày công, còn sang tháng sẽ ẩn nhân viên đó đi, không hiện trong danh sách
 * bảng công nữa"*.
 *
 * Nhờ bố cục KHỐI THÁNG (làm trước đó), việc này gần như tự có:
 *   · dòng của họ ở khối THÁNG NÀY vẫn còn -> ngày công đã làm vẫn hiện đủ, KHÔNG xoá gì;
 *   · khối THÁNG SAU chỉ sinh dòng khi có người quẹt, mà họ không quẹt nữa -> tự nhiên không có.
 * Việc phải làm thêm chỉ là: (1) đánh dấu, (2) GỠ KHỎI MÁY để họ không quẹt được nữa,
 * (3) đừng tạo dòng mới cho họ.
 *
 * ⚠️ TUYỆT ĐỐI KHÔNG xoá dòng cũ và KHÔNG xoá hồ sơ: còn phải tính lương tháng cuối, và còn
 *    phải tra lại về sau. "Nghỉ việc" là một TRẠNG THÁI, không phải lệnh xoá.
 */
function _laNghiViec(ws){
  var t = String(ws || '').toLowerCase();
  return t.indexOf('nghỉ việc') >= 0 || t.indexOf('nghi viec') >= 0 || t.indexOf('đã nghỉ') >= 0;
}

/**
 * Đánh dấu nghỉ việc / nhận lại làm. nghi=true -> ghi 'Đã nghỉ việc' + gỡ khỏi máy chấm công.
 * Trả { ok, opId, ghiChu }.
 */
function datNghiViec(pin, employeeNo, nghi, goKhoiMay){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền đổi trạng thái nhân viên.' };
  var empNo = String(employeeNo || '').trim();
  if (!empNo) return { ok:false, error:'Thiếu Mã NV' };
  var lock = LockService.getScriptLock();
  try { lock.waitLock(15000); } catch (le) {}
  try {
    var sh = _nvSheet();
    var found = _findRow(sh, 1, empNo);
    if (!found) return { ok:false, error:'Không thấy nhân viên ' + empNo };
    var station = String(found.data[2] || '').trim();
    if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Bạn không quản lý cửa hàng này' };

    var iWs = 7 + NV_EXTRA.indexOf('workStatus');          // cột trạng thái trong NV_HEADERS
    if (NV_EXTRA.indexOf('workStatus') < 0) return { ok:false, error:'Thiếu cột trạng thái trong NV_HEADERS' };
    sh.getRange(found.row, iWs + 1).setValue(nghi ? 'Đã nghỉ việc' : 'Đang làm');

    var opId = '', ghiChu = '';
    if (nghi) {
      ghiChu = 'Đã ghi "Đã nghỉ việc". Dòng chấm công tháng này VẪN GIỮ để tính lương; '
             + 'sang tháng sau họ không còn hiện trong bảng công.';
      // Gỡ khỏi máy: không gỡ thì họ vẫn quẹt được và tháng sau lại mọc dòng mới.
      if (goKhoiMay !== false) {
        try {
          opId = _enqueue('delete', empNo, String(found.data[1] || ''), '', '', station);
          ghiChu += ' Đã gửi lệnh gỡ khỏi máy chấm công (' + opId + ').';
        } catch (e) {
          ghiChu += ' ⚠️ CHƯA gỡ được khỏi máy (' + e + ') — họ vẫn quẹt được, cần thử lại.';
        }
      } else {
        ghiChu += ' CHƯA gỡ khỏi máy theo yêu cầu — họ vẫn quẹt được.';
      }
    } else {
      ghiChu = 'Đã nhận lại làm. Cần bấm Sửa → Lưu để đẩy lại xuống máy chấm công.';
    }
    return { ok:true, opId:opId, ghiChu:ghiChu };
  } catch (err) {
    return { ok:false, error:String(err) };
  } finally {
    lock.releaseLock();
  }
}

function deleteEmployee(pin, employeeNo) {
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Xoá hồ sơ là mất khuôn mặt trên máy và để lại PIN đăng nhập mồ côi — ' + _QT_LOI };
  var empNo = String(employeeNo || '').trim();
  if (!empNo) return { ok:false, error:'Thiếu Mã NV' };
  var lock = LockService.getScriptLock();
  try { lock.waitLock(15000); } catch (le) {}
  try {
    var sh = _nvSheet();
    var found = _findRow(sh, 1, empNo);
    var station = found ? String(found.data[2] || '') : '';
    if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Bạn không quản lý cửa hàng này' };
    var opId = _enqueue('delete', empNo, found ? String(found.data[1]||'') : '', '', '', station);
    if (found) sh.deleteRow(found.row);
    return { ok:true, opId:opId, taiKhoanConLai:_pqPinTheoMa(empNo) };
  } catch (err) {
    return { ok:false, error:String(err) };
  } finally {
    lock.releaseLock();
  }
}

// Xóa NHIỀU nhân viên 1 lần (lặp deleteEmployee cho từng mã). Trả {ok, done, total, fail:[]}.
function deleteEmployees(pin, empNos) {
  var u = _requireAuth(pin);
  if (!_canQuanTriNV(u)) return { ok:false, error:'Xoá hồ sơ là mất khuôn mặt trên máy và để lại PIN đăng nhập mồ côi — ' + _QT_LOI };
  var list = (empNos || []).map(function(s){ return String(s || '').trim(); }).filter(Boolean);
  if (!list.length) return { ok:true, done:0, total:0, fail:[] };

  /* Trước đây hàm này gọi deleteEmployee() trong vòng lặp -> mỗi nhân viên LẤY VÀ NHẢ KHOÁ
     một lần, và quét lại cả sheet một lần. Xoá 20 người là 20 lượt khoá; giữa 2 lượt thì
     doPost (máy chấm công đẩy dữ liệu) chen vào được, vừa chậm vừa dễ tranh chấp.
     Nay: LẤY KHOÁ MỘT LẦN cho cả lô, tìm hết trước rồi XOÁ TỪ DƯỚI LÊN để chỉ số hàng
     không xê dịch. */
  var lock = LockService.getScriptLock();
  try { lock.waitLock(30000); } catch (le) {}
  try {
    var sh = _nvSheet();
    var done = 0, fail = [], rows = [], thay = {};
    list.forEach(function(emp){
      if (thay[emp]) return;                      // gửi trùng mã 2 lần thì chỉ xoá 1
      thay[emp] = 1;
      var found = _findRow(sh, 1, emp);
      if (!found) { fail.push(emp); return; }
      var station = String(found.data[2] || '');
      if (!u.isAdmin && !_canStation(u, station)) { fail.push(emp); return; }
      rows.push({ row:found.row, emp:emp, name:String(found.data[1] || ''), station:station });
    });
    rows.sort(function(a, b){ return b.row - a.row; });   // dưới lên
    var conTk = [];
    rows.forEach(function(r){
      try { _enqueue('delete', r.emp, r.name, '', '', r.station); sh.deleteRow(r.row); done++;
            _pqPinTheoMa(r.emp).forEach(function(p){ conTk.push({ maNV:r.emp, pin:p }); }); }
      catch (e) { fail.push(r.emp); }
    });
    return { ok:true, done:done, total:list.length, fail:fail, taiKhoanConLai:conTk };
  } catch (err) {
    return { ok:false, error:String(err) };
  } finally {
    lock.releaseLock();
  }
}

function _enqueue(action, empNo, name, mpin, photoFileId, station, gender, photoB64) {
  var sh = _ensureSheet(SH_QUEUE);
  var opId = 'op-' + Utilities.getUuid().substring(0, 8);
  sh.appendRow([opId, action, empNo, name, mpin, photoFileId || '', station, 'pending', _now(), '']);
  // ĐẨY LÊN FIREBASE để ESP32 (4G) đọc: /queue/<station>/<opId> (+ /photo nếu có ảnh)
  if (station) {
    var hasPhoto = !!(photoB64 && photoB64.length > 0);
    if (hasPhoto) _fbPut('/photo/' + station + '/' + opId, photoB64);
    _fbPut('/queue/' + station + '/' + opId, {
      action: action, employeeNo: empNo, name: name, pin: mpin || '',
      gender: _genderMachine(gender), hasPhoto: hasPhoto, createdAt: _now()
    });
  }
  return opId;
}

function _saveNvPhoto(empNo, dataUrl) {
  var b64 = String(dataUrl).split('base64,')[1];
  var bytes = Utilities.base64Decode(b64);
  var blob = Utilities.newBlob(bytes, 'image/jpeg', 'nv_' + empNo + '.jpg');
  var folder = _nvPhotoFolder();
  var old = folder.getFilesByName('nv_' + empNo + '.jpg');
  while (old.hasNext()) old.next().setTrashed(true);
  return folder.createFile(blob).getId();
}
function _nvPhotoFolder() {
  var root = DriveApp.getFolderById(ROOT_FOLDER_ID);
  var it = root.getFoldersByName(NV_PHOTO_FOLDER);
  return it.hasNext() ? it.next() : root.createFolder(NV_PHOTO_FOLDER);
}

// ---- API cho ESP32 (station-scoped) ----
function _empPending(station) {
  station = String(station || '');
  _touchStation(station);   // heartbeat: ESP 4G poll hàng đợi -> máy đang online
  var sh = _sheet(SH_QUEUE);
  if (!sh || sh.getLastRow() < 2) return _json({ empty:true });
  var lock = LockService.getScriptLock();
  try { lock.waitLock(10000); } catch (le) {}
  try {
    var v = sh.getRange(2, 1, sh.getLastRow() - 1, 10).getValues();
    for (var i = 0; i < v.length; i++) {
      if (String(v[i][7]) !== 'pending') continue;
      if (station && String(v[i][6]) !== station) continue;
      return _json({
        empty:false,
        opId:       String(v[i][0]),
        action:     String(v[i][1]),
        employeeNo: String(v[i][2]),
        name:       String(v[i][3]),
        pin:        String(v[i][4] || ''),
        station:    String(v[i][6] || ''),
        hasPhoto:   !!v[i][5] && String(v[i][1]) !== 'delete'
      });
    }
    return _json({ empty:true });
  } finally { lock.releaseLock(); }
}

function _empPhoto(opId) {
  if (!opId) return _text('ERR:no_opId');
  var r = _findRow(_ensureSheet(SH_QUEUE), 1, opId);
  if (!r) return _text('ERR:op_not_found');
  var fileId = String(r.data[5] || '');
  if (!fileId) return _text('ERR:no_photo');
  try {
    var b64 = Utilities.base64Encode(DriveApp.getFileById(fileId).getBlob().getBytes());
    return _text(b64);
  } catch (e) { return _text('ERR:' + e); }
}

function _empAck(opId, status, msg) {
  if (!opId) return _json({ ok:false, error:'no_opId' });
  var lock = LockService.getScriptLock();
  try { lock.waitLock(10000); } catch (le) {}
  try {
    var sh = _ensureSheet(SH_QUEUE);
    var r = _findRow(sh, 1, opId);
    if (!r) return _json({ ok:false, error:'op_not_found' });
    var st = (String(status) === 'done') ? 'done' : 'fail';
    sh.getRange(r.row, 8).setValue(st);
    sh.getRange(r.row, 10).setValue(String(msg || '') + ' @' + _now());
    // cập nhật faceStatus của NV
    var action = String(r.data[1]);
    var empNo  = String(r.data[2]);
    if (action !== 'delete') {
      var nv = _findRow(_ensureSheet(SH_NV), 1, empNo);
      if (nv) {
        _ensureSheet(SH_NV).getRange(nv.row, 6).setValue(st === 'done' ? 'synced' : ('error: ' + (msg || '')));
        _ensureSheet(SH_NV).getRange(nv.row, 7).setValue(_now());
      }
    }
    return _json({ ok:true });
  } finally { lock.releaseLock(); }
}

// Đối chiếu Firebase: lệnh ESP đã xử lý xong (biến mất khỏi /queue) -> cập nhật Queue + faceStatus NV.
// Gọi từ getEmployees vì ESP qua 4G XÓA Firebase chứ không gọi _empAck cũ.
function _reconcileFromFirebase(stations) {
  try {
    var qsh = _sheet(SH_QUEUE);
    if (!qsh || qsh.getLastRow() < 2) return;
    // ⚠️ CHỈ nhận trạm nào ĐỌC ĐƯỢC. _fbGet trả undefined = gọi thất bại -> KHÔNG đưa vào map,
    // để vòng dưới bỏ qua trạm đó. Trước đây `|| {}` biến "gọi thất bại" thành "Firebase rỗng"
    // -> đánh dấu hết thành 'synced' dù chưa đẩy được gì. Đó là lỗi báo thành công KHỐNG.
    var fbByStation = {}, tramLoi = [];
    (stations || []).forEach(function(st){
      if (!st || fbByStation[st] !== undefined || tramLoi.indexOf(st) >= 0) return;
      var q = _fbGet('/queue/' + st);
      if (q === undefined) { tramLoi.push(st); return; }          // thất bại -> bỏ qua trạm này
      fbByStation[st] = q || {};                                  // null = rỗng thật -> coi như đã xử lý hết
    });
    if (tramLoi.length) _fbGhiLoi('RECONCILE_BO_QUA', 'không đọc được /queue của: ' + tramLoi.join(', '));
    var v = qsh.getRange(2, 1, qsh.getLastRow() - 1, 10).getValues();
    var nvsh = _ensureSheet(SH_NV);
    for (var i = 0; i < v.length; i++) {
      if (String(v[i][7]) !== 'pending') continue;
      var st = String(v[i][6] || ''), opId = String(v[i][0]);
      if (fbByStation[st] === undefined) continue;                 // không quét trạm này -> bỏ
      if (fbByStation[st][opId]) continue;                         // còn trong Firebase -> chưa xử lý
      qsh.getRange(i + 2, 8).setValue('done');
      qsh.getRange(i + 2, 10).setValue('đã đồng bộ @' + _now());
      if (String(v[i][1]) !== 'delete') {
        var nv = _findRow(nvsh, 1, String(v[i][2]));
        if (nv) { nvsh.getRange(nv.row, 6).setValue('synced'); nvsh.getRange(nv.row, 7).setValue(_now()); }
      }
    }
  } catch (e) {}
}


// ---- MÁY -> WEB: đối chiếu danh sách NV đang có trên máy chấm công ----
// Yêu cầu ESP quét danh sách NV trên máy (đẩy lệnh 'scan' vào Firebase -> ESP đọc UserInfo/Search -> ghi /roster/<station>).
function requestMachineScan(pin, station) {
  var u = _requireAuth(pin);
  station = String(station || '').trim();
  if (!station) return { ok:false, error:'Chưa chọn cửa hàng' };
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền ra lệnh quét máy.' };
  if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Bạn không quản lý cửa hàng này' };
  var opId = _enqueue('scan', '0', 'scan', '', '', station);
  return { ok:true, opId:opId };
}

// ---- TRÍCH ẢNH CHẤM CÔNG THEO YÊU CẦU (chống gian lận) ----
// Quản lý/Admin bấm nút trên 1 dòng -> ghi lệnh getphoto vào Firebase /queue/<station>/<opId>.
// ESP tìm sự kiện trên máy theo (mã NV + giờ), tải ảnh, PUT lên /photoresp/<station>/<opId>.
// KHÔNG ghi sheet Queue (tránh _reconcileFromFirebase chạm nhầm faceStatus NV).
function requestAttPhoto(pin, station, empNo, date, time, which) {
  var u = _requireAuth(pin);
  if (!(u.isAdmin || u.role === ROLE.QUAN_LY)) return { ok:false, error:'Chỉ Quản lý / Admin được trích ảnh.' };
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Chưa chọn cửa hàng' };
  if (!_canStation(u, station)) return { ok:false, error:'Bạn không quản lý cửa hàng này' };
  empNo = String(empNo || '').trim();
  date  = String(date || '').trim();
  time  = String(time || '').trim();
  if (!empNo || !date || !time) return { ok:false, error:'Thiếu mã NV / ngày / giờ' };
  var opId = 'op-' + Utilities.getUuid().substring(0, 8);
  _fbPut('/queue/' + station + '/' + opId, {
    action: 'getphoto', employeeNo: empNo, name: '', pin: '', gender: '',
    date: date, time: time, which: String(which || ''), createdAt: _now()
  });
  return { ok:true, opId:opId };
}
// Client poll: đọc ảnh ESP đã đẩy lên /photoresp/<station>/<opId>. Có ảnh -> trả data URI + xóa node.
function getAttPhoto(pin, station, opId) {
  var u = _requireAuth(pin);
  if (!(u.isAdmin || u.role === ROLE.QUAN_LY)) return { ok:false, error:'Không có quyền' };
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!_canStation(u, station)) return { ok:false, error:'Không có quyền cửa hàng này' };
  if (!opId) return { ok:false, error:'Thiếu opId' };
  var r = _fbGet('/photoresp/' + station + '/' + opId);
  if (!r) return { ok:true, pending:true };
  _fbDelete('/photoresp/' + station + '/' + opId);           // đọc xong dọn luôn cho gọn RTDB
  if (r.img) return { ok:true, img: 'data:image/jpeg;base64,' + r.img };
  return { ok:true, error: r.err || 'Không lấy được ảnh' };
}

// Lấy danh sách NV trên máy (Firebase /roster/<station>) + đối chiếu sheet NhanVien (cùng cửa hàng).
// Trả {ok, station, roster:[{employeeNo,name,hasFace,onWeb,webName,nameMismatch}], missingOnWeb, notOnMachine, countMachine}.
function getMachineRoster(pin, station) {
  var u = _requireAuth(pin);
  station = String(station || '').trim();
  if (!station) return { ok:false, error:'Chưa chọn cửa hàng' };
  if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Bạn không quản lý cửa hàng này' };
  var out = { ok:true, station:station, roster:[], missingOnWeb:[], notOnMachine:[], countMachine:0 };
  var fb = _fbGet('/roster/' + station) || {};
  // NV trên web THUỘC cửa hàng này
  var webNos = {};
  var sh = _nvSheet(), last = sh.getLastRow();
  if (last >= 2) {
    var v = sh.getRange(2, 1, last - 1, 3).getValues();   // Mã | Họ tên | Cửa hàng
    for (var i = 0; i < v.length; i++) {
      var e = String(v[i][0] || ''); if (!e) continue;
      if (String(v[i][2] || '').trim() !== station) continue;
      webNos[e] = String(v[i][1] || '');
    }
  }
  for (var no in fb) {
    if (String(no).charAt(0) === '_') continue;              // node phụ (nếu có) -> bỏ
    var rec = fb[no], nm, hasFace;
    if (rec && typeof rec === 'object') { nm = String(rec.n || ''); hasFace = !!rec.f; }
    else { nm = String(rec || ''); hasFace = null; }         // format cũ (chuỗi tên): chưa biết ảnh
    var onWeb = Object.prototype.hasOwnProperty.call(webNos, no);
    var wn = onWeb ? webNos[no] : '';
    out.roster.push({ employeeNo:no, name:nm, hasFace:hasFace, onWeb:onWeb, webName:wn,
                      nameMismatch:(onWeb && nm && wn && nm !== wn) });
    if (!onWeb) out.missingOnWeb.push({ employeeNo:no, name:nm });
  }
  out.roster.sort(function(a, b){ return (a.employeeNo < b.employeeNo) ? -1 : (a.employeeNo > b.employeeNo ? 1 : 0); });
  out.countMachine = out.roster.length;
  for (var w in webNos) { if (!Object.prototype.hasOwnProperty.call(fb, w)) out.notOnMachine.push({ employeeNo:w, name:webNos[w] }); }
  return out;
}

// Thêm NV từ MÁY vào web (đã có sẵn trên máy -> KHÔNG đẩy lệnh xuống máy, chỉ ghi sheet). faceStatus='trên máy'.
function addFromMachine(pin, station, employeeNo, name) {
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền thêm nhân viên.' };
  station = String(station || '').trim();
  var empNo = String(employeeNo || '').trim();
  if (!station) return { ok:false, error:'Chưa chọn cửa hàng' };
  if (!empNo) return { ok:false, error:'Thiếu Mã NV' };
  // Cùng lý do với `upsertEmployee`: đừng để hàng tiêu đề thành một con người trong sheet.
  if (_nvcsLaTieuDe(empNo, name))
    return { ok:false, error:'"' + empNo + ' / ' + String(name || '') + '" là HÀNG TIÊU ĐỀ của sheet, không phải nhân viên.' };
  if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Bạn không quản lý cửa hàng này' };
  var lock = LockService.getScriptLock();
  try { lock.waitLock(15000); } catch (le) {}
  try {
    var sh = _nvSheet();
    if (_findRow(sh, 1, empNo)) return { ok:false, error:'Mã ' + empNo + ' đã có trên web' };
    var row = [empNo, String(name || ''), station, '', '', 'trên máy', _now()];
    while (row.length < NV_HEADERS.length) row.push('');   // đủ 23 cột
    sh.appendRow(row);
    return { ok:true };
  } catch (err) {
    return { ok:false, error:String(err) };
  } finally {
    lock.releaseLock();
  }
}


// ============================================================================
//  PHẦN MỚI 3 — COMMENT / CỜ "CẦN ADMIN KIỂM TRA"
// ============================================================================
function saveFlag(pin, station, dateStr, employeeNo, name, note) {
  var u = _requireAuth(pin);
  station = String(station || '');
  if (!u.isAdmin && !_canStation(u, station)) return { ok:false, error:'Bạn không quản lý cửa hàng này' };
  var sh = _ensureSheet(SH_FLAG);
  var flagId = 'fl-' + Utilities.getUuid().substring(0, 8);
  sh.appendRow([flagId, station, String(dateStr||''), String(employeeNo||''), String(name||''),
                String(note||''), u.name, 'pending', _now(), '']);
  return { ok:true, flagId:flagId };
}

// getFlags(pin, station?, onlyPending?) -> mảng cờ (admin: tất cả; CHT: cửa hàng mình)
function getFlags(pin, station, onlyPending) {
  var u = _requireAuth(pin);
  var sh = _ensureSheet(SH_FLAG);
  var last = sh.getLastRow();
  var out = [];
  if (last < 2) return out;
  var v = sh.getRange(2, 1, last - 1, 10).getValues();
  for (var i = 0; i < v.length; i++) {
    var st = String(v[i][1] || '');
    if (!u.isAdmin && !_canStation(u, st)) continue;
    if (station && st !== String(station)) continue;
    if (onlyPending && String(v[i][7]) !== 'pending') continue;
    out.push({
      flagId: String(v[i][0]), station: st, date: String(v[i][2]),
      employeeNo: String(v[i][3]), name: String(v[i][4]), note: String(v[i][5]),
      by: String(v[i][6]), status: String(v[i][7]), createdAt: String(v[i][8]), resolvedAt: String(v[i][9])
    });
  }
  return out.reverse();
}

/* ===========================================================================
 *  TÔ MÀU Ô BỊ GẮN CỜ VÀO SHEET `CS_`   —  anh Thắng 07/08/2026
 * ---------------------------------------------------------------------------
 *  Anh: *"Nếu gắn cờ ngày nào thì trong sheet sẽ đổi màu ô đó để vào kiểm tra dễ nhất… Chỉ áp
 *  dụng tk admin. Mấy tk khác khỏi. Để khỏi nặng máy."*
 *
 *  Trên web đã có tooltip (rê chuột ra giờ vào–ra). Cái thiếu là khi anh mở THẲNG Google Sheet
 *  thì không biết ngày nào đang bị gắn cờ — phải mở song song hai màn hình mà dò.
 *
 *  🔴 BA ĐIỀU BẮT BUỘC, sai cái nào cũng phá dữ liệu của anh:
 *
 *  1. CHỈ XOÁ MÀU DO CHÍNH APP TÔ. Anh Thắng tô tay rất nhiều ô trong sheet (đánh dấu riêng).
 *     Quét sạch cả vùng rồi tô lại là XOÁ MẤT dấu tay của anh, không lùi được. Nên chỉ ô nào
 *     đang mang ĐÚNG mã màu `CO_MAU` mới bị trả về không-tô.
 *  2. ĐỌC/GHI MỘT LƯỢT cho cả khối (`getBackgrounds`/`setBackgrounds`). Tô từng ô là mỗi ô một
 *     vòng đi–về Sheets (~20–50ms); một tháng vài chục cờ là treo. Đúng lý do anh dặn "khỏi nặng".
 *  3. CHỈ ADMIN, và CHỈ KHI BẤM NÚT. Không gắn vào `getFlags` hay lúc mở tab — mở tab mà ghi
 *     sheet thì ai vào xem cũng làm chậm, đúng thứ anh muốn tránh.
 */
var CO_MAU = '#ffd7a6';          // cam nhạt — DẤU RIÊNG CỦA APP, đừng dùng màu này để tô tay
var CO_MAU_TRANG = '#ffffff';    // GAS trả '#ffffff' cho ô không tô

/** Vị trí các hàng của MỘT người trong khối (gồm cả hàng phụ `-CD`, `-TC`). */
function _coHangCuaNguoi(sheet, k, maNV){
  var out = [];
  if (k.r2 < k.r1) return out;
  var ids = sheet.getRange(k.r1, 2, k.r2 - k.r1 + 1, 1).getDisplayValues();
  /* Gồm cả MÃ CŨ đã khai (07/08/2026): web đã gộp hai mã thành một dòng, nên cờ gắn cho dòng đó
     phải nhuộm CẢ hàng mã cũ trong sheet. Chỉ nhuộm hàng mã chính là mở Sheet ra thấy ngày bị
     gắn cờ vẫn trắng — đúng thứ nút này sinh ra để tránh. */
  var cacMa = _dsMaCuaNguoi(maNV);
  for (var i = 0; i < ids.length; i++){
    var s = String(ids[i][0] || '').trim(); if (!s) continue;
    /* Bóc hết hậu tố để `MNNV01`, `MNNV01-CD`, `MNNV01-TC` cùng khớp một người. Cờ gắn cho NGÀY
       của NGƯỜI đó, nên cả ca ngày lẫn ca đêm của họ đều phải nhuộm. */
    var g = s, t;
    for (var v = 0; v < 3 && (t = _tachMaNhiemVu(g)); v++) g = t.ma;
    if (cacMa[_chuanMa(g)]) out.push(k.r1 + i);
  }
  return out;
}

/**
 * Tô lại TOÀN BỘ dấu cờ vào các sheet `CS_`. Chạy lại bao nhiêu lần cũng ra cùng kết quả:
 * xoá hết dấu cũ của app rồi tô theo danh sách cờ ĐANG CHỜ tại thời điểm bấm.
 * Nhờ vậy cờ đã xử lý (`resolved`) thì màu tự biến mất — không phải đi xoá tay.
 */
function toMauCoVaoSheet(pin){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin chạy được việc này (ghi màu vào sheet).' };
  var shCo = _ensureSheet(SH_FLAG), last = shCo.getLastRow();
  if (last < 2) return { ok:true, to:0, xoa:0, coSo:0, ghiChu:'Chưa có cờ nào.' };
  var v = shCo.getRange(2, 1, last - 1, 10).getValues();

  /* Gom theo (cơ sở, tháng). Lấy CẢ cờ đã xử lý — không phải để tô, mà để biết chỗ nào cần XOÁ
     dấu cũ. Bỏ qua cờ resolved thì màu của nó nằm lại vĩnh viễn, và anh đi kiểm một ô đã xong. */
  var nhom = {};
  for (var i = 0; i < v.length; i++){
    var cs = String(v[i][1] || '').replace(/^CS_/, '').trim();
    var ng = String(v[i][2] || '').trim();
    if (!cs || !/^\d{4}-\d{2}-\d{2}$/.test(ng)) continue;
    var key = cs + '|' + ng.substring(0, 7);
    nhom[key] = nhom[key] || { coSo:cs, thang:ng.substring(0, 7), cho:[] };
    if (String(v[i][7]) === 'pending') nhom[key].cho.push({ ngay:ng, ma:String(v[i][3] || '') });
  }

  var tong = { to:0, xoa:0, coSo:0, boQua:[] };
  Object.keys(nhom).forEach(function(key){
    var g = nhom[key], sheet = _sheet('CS_' + g.coSo);
    if (!sheet){ tong.boQua.push('CS_' + g.coSo + ' (không có sheet)'); return; }
    var ds = _csKhoi(sheet), k = null;
    for (var j = 0; j < ds.length; j++) if (_csThangKhoi(sheet, ds[j]) === g.thang) { k = ds[j]; break; }
    if (!k || k.r2 < k.r1){ tong.boQua.push('CS_' + g.coSo + ' tháng ' + g.thang + ' (chưa có khối)'); return; }
    tong.coSo++;

    var lastCol = sheet.getLastColumn(); if (lastCol < 3) return;
    var soCot = lastCol - 2;
    /* Cột của từng ngày: đọc hàng tiêu đề MỘT lượt rồi dò trong bộ nhớ. */
    var hang = sheet.getRange(k.hdr, 3, 1, soCot).getValues()[0], cot = {};
    for (var c = 0; c < hang.length; c += 5){
      var hv = hang[c]; if (hv === '' || hv == null) break;
      cot[(hv instanceof Date) ? Utilities.formatDate(hv, TZ, 'yyyy-MM-dd') : String(hv).trim()] = c;
    }
    var soHang = k.r2 - k.r1 + 1;
    var cu = sheet.getRange(k.r1, 3, soHang, soCot).getBackgrounds();
    /* 🔴 KHÔNG ghi lại `#ffffff` cho ô vốn KHÔNG TÔ.
       `getBackgrounds` trả '#ffffff' cho cả ô trống lẫn ô tô trắng thật. Viết nguyên mảng đó
       ngược vào sheet là đặt NỀN TRẮNG CỨNG lên từng ô của khối — đè mất kiểu kẻ sọc / định dạng
       nền của anh, mà nhìn màn hình thì y hệt nên không ai phát hiện. `null` mới là "trả về mặc
       định". Nên dựng mảng mới: mặc định `null`, chỉ điền màu ở ô thật sự phải giữ hoặc phải tô. */
    var nen = [];
    for (var a = 0; a < soHang; a++){
      nen.push([]);
      for (var b = 0; b < soCot; b++){
        var m = String(cu[a][b] || '').toLowerCase();
        if (m === CO_MAU) nen[a].push(null);                                 // dấu cũ của app -> bỏ
        else if (m && m !== CO_MAU_TRANG) nen[a].push(cu[a][b]);             // màu anh tô tay -> GIỮ
        else nen[a].push(null);                                              // vốn không tô -> để yên
      }
    }

    // tô cờ đang chờ
    g.cho.forEach(function(f){
      if (!(f.ngay in cot)) return;                       // ngày đó chưa có cột trong khối
      var hangDs = _coHangCuaNguoi(sheet, k, f.ma);
      hangDs.forEach(function(hr){
        for (var q = 0; q < 5 && (cot[f.ngay] + q) < soCot; q++){
          nen[hr - k.r1][cot[f.ngay] + q] = CO_MAU; tong.to++;
        }
      });
    });

    /* 🔴 So BẢN CUỐI với BẢN GỐC, đừng bật cờ "đã đổi" dọc đường.
       Bản đầu em bật `doi = true` ngay lúc xoá dấu cũ — nhưng ô đó thường được TÔ LẠI ĐÚNG MÀU
       ĐÓ ngay sau (cờ vẫn đang chờ). Kết quả y hệt mà vẫn ghi: bấm nút lần hai vẫn tốn một lượt
       đi–về Sheets, đúng cái "nặng máy" anh dặn tránh. `null` và '#ffffff' là MỘT (đều = không
       tô) nên phải quy về cùng một dạng trước khi so, không thì lượt nào cũng thấy "khác". */
    var chuan = function(x){ var y = String(x == null ? '' : x).toLowerCase();
                             return (y === '' || y === CO_MAU_TRANG) ? '' : y; };
    var doi = false;
    for (var a2 = 0; a2 < soHang && !doi; a2++)
      for (var b2 = 0; b2 < soCot; b2++)
        if (chuan(nen[a2][b2]) !== chuan(cu[a2][b2])){ doi = true; break; }
    /* `xoa` đếm ô THẬT SỰ mất dấu, không đếm ô bị tô lại đúng màu cũ — báo số xoá phồng lên thì
       anh tưởng vừa dọn được nhiều mà thực ra không dọn gì. */
    for (var a3 = 0; a3 < soHang; a3++)
      for (var b3 = 0; b3 < soCot; b3++)
        if (String(cu[a3][b3] || '').toLowerCase() === CO_MAU
            && String(nen[a3][b3] || '').toLowerCase() !== CO_MAU) tong.xoa++;

    if (doi) sheet.getRange(k.r1, 3, soHang, soCot).setBackgrounds(nen);
  });

  return { ok:true, to:tong.to, xoa:tong.xoa, coSo:tong.coSo, boQua:tong.boQua, mau:CO_MAU,
           ghiChu: 'Đã tô ' + tong.to + ' ô cho cờ đang chờ, xoá ' + tong.xoa + ' ô dấu cũ, '
                 + 'trên ' + tong.coSo + ' khối tháng.'
                 + (tong.boQua.length ? ' Bỏ qua: ' + tong.boQua.join(' · ') : '') };
}

function resolveFlag(pin, flagId) {
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin mới xử lý cờ' };
  var sh = _ensureSheet(SH_FLAG);
  var r = _findRow(sh, 1, flagId);
  if (!r) return { ok:false, error:'not_found' };
  sh.getRange(r.row, 8).setValue('resolved');
  sh.getRange(r.row, 10).setValue(_now());
  return { ok:true };
}


// ============================================================================
//  PHẦN MỚI 4 — CẤU HÌNH PHÂN QUYỀN NGAY TRÊN WEB (chỉ ADMIN)
// ============================================================================
function getRoles(pin) {
  var u = _requireAuth(pin);
  if (!u.isAdmin) throw new Error('Chỉ Admin mới xem phân quyền');
  var sh = _ensureSheet(SH_ROLE);
  var last = sh.getLastRow();
  var out = [];
  if (last < 2) return out;
  var v = sh.getRange(2, 1, last - 1, PQ_H.length).getValues();
  for (var i = 0; i < v.length; i++) {
    if (!String(v[i][0])) continue;
    out.push({ pin:String(v[i][0]), name:String(v[i][1] || ''), role:String(v[i][2] || ''),
               stations:String(v[i][3] || ''), maNV:String(v[i][4] || '').trim(),
               coSoOnline:String(v[i][5] || '').replace(/^CS_/, '').trim() });
  }
  return out;
}

/**
 * Mã NV nào ĐÃ được gán chấm công online — để tab Quản lý nhân viên đánh dấu, khỏi gán trùng
 * hai PIN cho cùng một người.
 *
 * ⚠️ KHÔNG trả PIN, chỉ trả tên tài khoản. Hàm này hiện trên tab Quản lý nhân viên mà tab đó
 *    Cửa hàng trưởng cũng vào được — trả PIN ra đây là mở toang phân quyền.
 * ⚠️ Không đủ quyền thì trả map RỖNG chứ KHÔNG ném lỗi: nó chạy nền lúc vẽ bảng, ném lỗi là
 *    hiện thông báo lỗi vô cớ cho Cửa hàng trưởng.
 */
function getNvCoChamCongOnline(pin){
  var u = loginByPin(pin);
  if (u.ok === false || !(u.isAdmin || u.role === ROLE.QUAN_LY)) return { ok:true, map:{} };
  var sh = _ensureSheet(SH_ROLE), last = sh.getLastRow();
  var map = {};
  if (last >= 2){
    var v = sh.getRange(2, 1, last - 1, PQ_H.length).getValues();
    for (var i = 0; i < v.length; i++){
      var ma = String(v[i][4] == null ? '' : v[i][4]).trim();
      if (!ma) continue;
      map[ma] = { ten: String(v[i][1] || ''), coSo: String(v[i][5] || '').replace(/^CS_/, '').trim() };
    }
  }
  return { ok:true, map:map };
}

/* ===== TỰ ĐỔI MẬT KHẨU (PIN) =====
 * Anh Thắng (01/08/2026): *"bổ sung tính năng đổi mật khẩu"*.
 *
 * ⚠️ Vì sao KHÔNG dùng saveRole(): hàm đó chỉ Admin gọi được và sửa được cả vai trò + cửa hàng.
 *    Đổi mật khẩu thì AI CŨNG phải tự đổi được của MÌNH, nhưng TUYỆT ĐỐI không được nhân dịp đó
 *    sửa vai trò hay cửa hàng của mình. Nên hàm riêng, chỉ ghi ĐÚNG MỘT Ô là cột PIN.
 *
 * ⚠️ PIN ở đây vừa là mật khẩu đăng nhập web, vừa là khoá tra trong sheet PhanQuyen. Đổi PIN là
 *    đổi luôn khoá, nên phải: (1) kiểm PIN mới chưa ai dùng, (2) xoá cache phiên của CẢ PIN cũ và
 *    PIN mới — không xoá thì PIN cũ còn đăng nhập được tới 60 giây nữa.
 */
/* 🔴 06/08/2026 — CHỐNG DÒ PIN NGƯỜI KHÁC QUA CHÍNH Ô ĐỔI MẬT KHẨU.
 * Anh Thắng hỏi: *"vậy tính năng đổi pin khả năng nguy hiểm, nếu nhân viên đổi pin trùng người
 * khác thì sao"*. Trùng thì đã bị từ chối sẵn (xem `dupe` bên dưới) — chỗ đó vẫn tốt.
 * NHƯNG câu từ chối "Mật khẩu này đã có người dùng" chính là một CÂU TRẢ LỜI: thử số nào cũng
 * biết ngay số đó có người hay không. `loginByPin` có phạt chờ khi sai, còn hàm này thì KHÔNG —
 * nên người đã đăng nhập cứ bấm mãi là dò ra PIN của người khác, rồi đăng xuất và vào bằng số đó.
 * Nay: đếm số lần đụng-trùng trong 10 phút, quá ngưỡng thì chặn hẳn một lúc.
 * ⚠️ CHỈ đếm lượt ĐỤNG TRÙNG. Đếm cả lượt gõ sai định dạng là người dùng thật gõ nhầm 5 lần
 *    (thiếu số, hai ô không khớp) đã bị khoá — phiền vô cớ mà chẳng chặn được ai. */
var DOIPIN_NGUONG = 5;        // số lần đụng PIN người khác cho phép trong một cửa sổ
var DOIPIN_CUA_SO = 600;      // 10 phút (giây)
function _doiPinKhoa(pin){
  return 'dpin1_' + Utilities.base64EncodeWebSafe(
    Utilities.computeDigest(Utilities.DigestAlgorithm.MD5, String(pin || '')));
}
function _doiPinSoLan(pin){
  try { return Number(CacheService.getScriptCache().get(_doiPinKhoa(pin)) || 0) || 0; } catch(e){ return 0; }
}
function _doiPinGhiTrung(pin){
  try { CacheService.getScriptCache().put(_doiPinKhoa(pin), String(_doiPinSoLan(pin) + 1), DOIPIN_CUA_SO); } catch(e){}
}
/** Tách hàm thuần để test được mà không phải ngồi chờ hết cửa sổ 10 phút. */
function _doiPinConDuoc(soLan){ return (Number(soLan) || 0) < DOIPIN_NGUONG; }

function doiPin(pinHienTai, pinMoi, pinMoiLai){
  var u = _requireAuth(pinHienTai);          // sai PIN hiện tại là ném lỗi ngay tại đây
  var cu  = String(pinHienTai || '').trim();
  var moi = String(pinMoi || '').trim();
  var lai = String(pinMoiLai || '').trim();

  if (!moi) return { ok:false, error:'Chưa nhập mật khẩu mới.' };
  if (moi !== lai) return { ok:false, error:'Hai lần nhập mật khẩu mới KHÔNG giống nhau.' };
  if (moi === cu)  return { ok:false, error:'Mật khẩu mới trùng mật khẩu đang dùng.' };
  if (!/^[0-9]{6}$/.test(moi)) return { ok:false, error:'Mật khẩu phải là ĐÚNG 6 chữ số (0-9).' };
  // Mấy dãy dễ đoán nhất — chặn ngay, vì đây là chìa khoá vào toàn bộ chấm công của chuỗi.
  if (/^(\d)\1{5}$/.test(moi)) return { ok:false, error:'Mật khẩu không được là 6 chữ số giống nhau (111111…).' };
  if ('012345678901234567890'.indexOf(moi) >= 0 || '098765432109876543210'.indexOf(moi) >= 0)
    return { ok:false, error:'Mật khẩu không được là dãy liên tiếp (123456, 654321…).' };

  /* Chặn TRƯỚC khi đụng sheet: quá ngưỡng thì không cho biết thêm gì nữa về số nào có người. */
  if (!_doiPinConDuoc(_doiPinSoLan(cu)))
    return { ok:false, error:'Bạn đã thử quá nhiều mật khẩu đang có người dùng. '
           + 'Chờ ít phút rồi thử lại, hoặc nhờ Admin cấp giúp một mật khẩu.' };

  var lock = LockService.getScriptLock(); try { lock.waitLock(15000); } catch (e) {}
  try {
    var sh = _ensureSheet(SH_ROLE);
    var found = _findRow(sh, 1, cu);
    if (!found) return { ok:false, error:'Không thấy tài khoản đang đăng nhập trong bảng phân quyền.' };
    var dupe = _findRow(sh, 1, moi);
    if (dupe && dupe.row !== found.row) {
      _doiPinGhiTrung(cu);
      /* Nói "đã có người dùng" là cần thiết — không thì người dùng thật không hiểu vì sao không
         đổi được. Cái phải chặn là DÒ HÀNG LOẠT, nên chặn bằng số lần chứ không bằng cách giấu
         lý do. Đúng một lần nữa cái bẫy quen: giấu thông báo chỉ làm người ngay khó chịu, kẻ dò
         vẫn phân biệt được thành công/thất bại. */
      return { ok:false, error:'Mật khẩu này đã có người dùng, chọn số khác.' };
    }

    sh.getRange(found.row, 1).setValue(moi);      // CHỈ ghi cột PIN, không đụng vai trò / cửa hàng
    _clearAuthCache(cu); _clearAuthCache(moi);    // PIN cũ mất hiệu lực NGAY, không chờ cache 60s
    return { ok:true, pinMoi: moi,
             ghiChu: 'Đã đổi mật khẩu. Lần sau đăng nhập bằng mật khẩu mới. '
                   + 'Mật khẩu cũ đã mất hiệu lực ngay.' };
  } catch (err) {
    return { ok:false, error:String(err) };
  } finally { lock.releaseLock(); }
}

/** Danh sách NV của một cơ sở, chỉ mã + tên — để ô "Mã NV chấm công online" chọn theo TÊN.
 *  Anh Thắng: *"mã nhân viên sẽ lấy theo họ tên người, để tránh bị nhập sai"*.
 *  ⚠️ Hàm NHẸ, chỉ 2 cột. Không dùng getEmployees() vì hàm đó trả cả hồ sơ (lương, số tài khoản,
 *     CCCD) — mở form phân quyền mà kéo về cả đống dữ liệu nhạy cảm là thừa và rủi ro. */
function getNvChonTheoCoSo(pin, station){
  var u = _requireAuth(pin);
  if (!(u.isAdmin || u.role === ROLE.QUAN_LY)) return [];
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return [];
  var sh = _sheet(SH_NV), last = sh ? sh.getLastRow() : 0;
  if (!sh || last < 2) return [];
  var iWs = 7 + NV_EXTRA.indexOf('workStatus');
  var v = sh.getRange(2, 1, last - 1, NV_HEADERS.length).getValues();
  var out = [];
  for (var i = 0; i < v.length; i++){
    var ma = String(v[i][0] || '').trim();
    if (!ma) continue;
    if (String(v[i][2] || '').replace(/^CS_/, '').trim() !== station) continue;
    out.push({ ma: ma, ten: String(v[i][1] || ''), nghi: _laNghiViec(v[i][iWs]) });
  }
  out.sort(function(a, b){ return a.ten < b.ten ? -1 : (a.ten > b.ten ? 1 : 0); });
  return out;
}

/**
 * Danh sách người có mặt trong SHEET của một cơ sở — đọc thẳng cột A (Họ và Tên) + B (ID).
 *
 * ⚠️ Vì sao KHÔNG dùng `getNvChonTheoCoSo`: hàm đó đọc sheet `NhanVien`. Rất nhiều người chỉ
 *    tồn tại ở sheet cơ sở (máy chấm công tự sinh dòng lúc quẹt lần đầu) mà CHƯA có hồ sơ trong
 *    `NhanVien` — họ không hiện ở tab Quản lý nhân viên, nên không có đường nào cấp PIN cho họ.
 *    Hàm này lấy đúng cái bảng anh Thắng đang nhìn trong Google Sheet.
 *
 * Trùng mã giữa các khối tháng thì gộp làm một. Kèm cờ đã có tài khoản PIN chưa (KHÔNG trả PIN).
 */
function dsNvTuSheetCoSo(pin, coSo){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin xem được danh sách này.', list:[] };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();

  /* 🔴 03/08 — ƯU TIÊN sheet nhân sự CỐ ĐỊNH `NV_<cơ sở>`. Anh Thắng: *"sheet chấm công nó bị
     nhảy theo tháng, tháng trước NV không chấm công thì sang tháng sau danh sách NV đó bị ẩn"*.
     Đúng: `CS_` là bảng chấm công, không phải danh sách nhân sự. Chưa có sheet `NV_` thì mới rơi
     về `CS_` như cũ — có đường lùi, không bắt anh tạo sheet mới cho đủ 20 cơ sở rồi mới dùng được. */
  var _nv = _nvcsDoc(coSo);
  if (_nv.co){
    if (_nv.loi) return { ok:false, error:_nv.loi, coSo:coSo, list:[], nguon:'NV_' };
    var _q = {};
    try { _q = getNvCoChamCongOnline(pin).map || {}; } catch(e){}
    var _out = _nv.list.map(function(x){
      var o = { ma:x.ma, ten:x.ten, nhiemVu:x.nhiemVu, hang:x.hang };
      var g = _q[x.ma];
      if (g){ o.daCo = true; o.tenTk = g.ten || ''; o.tkCoSo = g.coSo || ''; }
      return o;
    });
    _out.sort(function(a, b){ return a.ten < b.ten ? -1 : (a.ten > b.ten ? 1 : 0); });
    return { ok:true, coSo:coSo, list:_out, nguon:'NV_', tenSheet:_nvcsTen(coSo),
             coCotNhiemVu: !!_nv.cot.nhiemVu };
  }

  var sh = coSo ? _sheet('CS_' + coSo) : null;
  if (!sh) return { ok:true, coSo:coSo, list:[], nguon:'CS_', tenSheet:'CS_' + coSo };
  var lr = sh.getLastRow();
  if (lr < 1) return { ok:true, coSo:coSo, list:[], nguon:'CS_', tenSheet:'CS_' + coSo };

  var v = sh.getRange(1, 1, lr, 2).getDisplayValues(), da = {}, out = [], phu = [];
  for (var i = 0; i < v.length; i++){
    var ten = String(v[i][0] == null ? '' : v[i][0]).trim();
    var ma  = String(v[i][1] == null ? '' : v[i][1]).trim();
    if (!ma || ma === 'ID') continue;                 // bỏ hàng tiêu đề của từng khối tháng
    /* 🔴 Hàng NHIỆM VỤ THÊM (`…-TG`) KHÔNG phải một con người — anh Thắng: *"gộp nv này lại,
       tránh cấp nhầm sau này, dựa vào ID trùng nhau"*. Để nó thành dòng riêng là có nút
       "Cấp PIN" riêng, bấm vào là tạo TÀI KHOẢN CHẤM CÔNG THỨ HAI cho cùng một người.
       Gom sang lượt sau vì hàng phụ có thể nằm TRƯỚC hàng chính (sheet sửa tay được). */
    var t = _tachMaNhiemVu(ma);
    /* `nhiemVu` TRƯỚC, `nhan` chỉ là đường lùi: hàng ca Văn phòng (-CT/-CD) có `nhiemVu` rỗng nên
       cần `nhan` để không hiện ra dòng trắng. ⚠️ Đảo thứ tự là hàng `-TG` trả TÊN NGẮN
       ("Trực Ghế") thay cho tên đầy đủ ("Trực Ghế Posh - JP") -> `test_nhomcs` đỏ 2 phép. */
    if (t){ phu.push({ goc:t.ma, nhiemVu:(t.nhiemVu || t.nhan), ten:ten }); continue; }
    if (da[ma]) { if (!da[ma].ten && ten) da[ma].ten = ten; continue; }
    da[ma] = { ma: ma, ten: ten };
    out.push(da[ma]);
  }
  phu.forEach(function(p){
    var g = da[p.goc];
    if (!g){                                          // chỉ có hàng phụ (hiếm) -> vẫn quy về mã gốc
      g = da[p.goc] = { ma:p.goc, ten:String(p.ten || '').split(NV_NGAN_CACH)[0].trim() };
      out.push(g);
    }
    if (!g.nhiemVuThem) g.nhiemVuThem = [];
    if (g.nhiemVuThem.indexOf(p.nhiemVu) < 0) g.nhiemVuThem.push(p.nhiemVu);
  });
  var q = {};
  try { q = getNvCoChamCongOnline(pin).map || {}; } catch(e){}
  out.forEach(function(x){
    var g = q[x.ma];
    if (g){ x.daCo = true; x.tenTk = g.ten || ''; x.tkCoSo = g.coSo || ''; }
  });
  out.sort(function(a, b){ return a.ten < b.ten ? -1 : (a.ten > b.ten ? 1 : 0); });
  return { ok:true, coSo:coSo, list:out, nguon:'CS_', tenSheet:'CS_' + coSo };
}

/* ===================== CHẤM CÔNG ONLINE (việc D — 01/08/2026) =====================
 * Anh Thắng: *"làm thêm 1 tab chấm công online cho cơ sở VP_KH-HCM bằng điện thoại… nút chụp ảnh
 * bằng camera có ngày giờ, sau đó bấm lưu thì app lấy ngày giờ đó làm giờ check"*, và
 * *"lấy giờ trên máy chủ cũng được, nhưng kèm GPS đính kèm nhỏ theo để tránh gian lận vị trí"*.
 *
 * 🔴 GIỜ LẤY Ở MÁY CHỦ, KHÔNG LẤY Ở ĐIỆN THOẠI. Lấy giờ điện thoại thì chỉnh đồng hồ máy là chấm
 *    công gian được — mà đây là giờ để trả lương. Ảnh vẫn ĐÓNG DẤU đúng giờ đó (client hỏi
 *    getGioMayChu ngay trước khi chụp) nên nhìn ảnh vẫn đối chiếu được.
 *
 * 🔴 GHI BẰNG ĐÚNG ĐƯỜNG CỦA MÁY HIKVISION: findOrCreateDateBlock + findOrCreateEmpRow +
 *    _ghiGioVaoRa. Không có đường ghi thứ hai. Lệch hai đường là lệch tiền lương.
 */
/* ============================================================================================
 * HAI PIN KHAI TRONG SHEET `NV_<cơ sở>` (06/08/2026)
 *
 * Anh Thắng: *"Mã pin nhân viên hiện tại đang lưu ở đâu. Anh muốn nó lưu vào cột F luôn, để
 * đồng bộ backup sau này, chuyển hết pin về sheet cho anh"*.
 * Thoạt đầu anh muốn GỘP một số cho cả hai việc; nghe lý do xong anh chốt lại:
 * *"mỗi nhân viên là 1 mã pin riêng, theo ID nhân viên… vậy tách 2 pin"*.
 *
 * 🔴 VÌ SAO KHÔNG GỘP — ghi lại để đừng ai gộp lại lần nữa:
 *    `_loginResolve` tìm PIN trong `PhanQuyen` và lấy DÒNG ĐẦU TIÊN khớp => PIN đăng nhập BẮT
 *    BUỘC DUY NHẤT TOÀN CHUỖI. PIN máy thì KHÔNG: nó là mã trên từng đầu đọc, hai cơ sở cùng có
 *    người PIN "1234" là bình thường. Gộp lại = PIN máy thành mật khẩu web, hai người trùng số
 *    là người này ĐĂNG NHẬP VÀO TÀI KHOẢN NGƯỜI KIA. Sai danh tính, không phải phiền nhẹ.
 *
 *    cột `PIN`      -> `PhanQuyen` (đăng nhập web / chấm công online) — kiểm TRÙNG toàn chuỗi
 *    cột `PIN máy`  -> `NhanVien` cột 4 (mã trên đầu đọc)             — không kiểm trùng toàn chuỗi
 *
 * Hai chiều, tách hẳn, KHÔNG cái nào tự chạy:
 *    xuatPinRaSheet(coSo)  — PIN đang có -> ghi xuống sheet (việc "chuyển hết pin về sheet").
 *                            Chỉ ĐIỀN Ô TRỐNG; ô anh đã gõ thì KHÔNG đè.
 *    napPinTuSheet(coSo)   — đọc sheet -> đặt PIN thật. Dòng nào trượt thì GIỮ LẠI KÈM LÝ DO.
 * ==========================================================================================*/

/** PIN hợp lệ: chỉ chữ số, 4–6 ký tự. Trả {ok, pin, ly, yeu}. */
function _pinChung(v){
  var t = String(v == null ? '' : v).trim();
  if (!t) return { ok:false, ly:'ô để trống' };
  if (!/^[0-9]+$/.test(t)) return { ok:false, ly:'"' + t + '" có ký tự không phải số' };
  if (t.length < 4) return { ok:false, ly:'"' + t + '" ngắn quá (cần 4–6 số)' };
  if (t.length > 6) return { ok:false, ly:'"' + t + '" dài quá (cần 4–6 số)' };
  return { ok:true, pin:t, yeu:(t.length < 6 || _pinYeu(t)) };
}

/**
 * Ghi PIN xuống sheet `NV_<cơ sở>` cho MỘT người — để sửa trên web là sheet theo ngay.
 * Anh Thắng: *"pin máy là khi cập nhật trên web là nó đẩy vào máy và đẩy vào sheet à"* — trước
 * đó KHÔNG: cột PIN trong sheet cơ sở đứng im cho tới khi bấm tay "Xuất PIN ra sheet".
 * Sheet để sao lưu mà đứng im thì sao lưu ra số cũ.
 * Truyền `null` cho PIN nào không muốn đụng.
 * 🔴 Ở ĐÂY ĐÈ LÊN ô cũ là ĐÚNG — khác hẳn `xuatPinRaSheet`. Vì đây là anh VỪA đổi số trên web,
 *    ý mới đè ý cũ. Còn `xuatPinRaSheet` là ghi hàng loạt lúc anh không nhìn từng dòng, nên nó
 *    mới phải chừa ô anh đã gõ.
 * 🔴 Người chưa có trong sheet thì KHÔNG tự thêm dòng. Đây là ranh giới của luật *"NV_ chỉ đi ra"*:
 *    SỬA ô của người ĐÃ CÓ thì được (anh Thắng yêu cầu riêng việc lưu PIN xuống sheet), còn ĐẺ
 *    THÊM DÒNG thì không — mọi đường thêm dòng đã bị bỏ ngày 06/08/2026.
 */
function _nvcsGhiPin(coSo, ma, pinDangNhap, pinMay){
  try {
    coSo = String(coSo || '').replace(/^CS_/, '').trim();
    if (!coSo || !ma) return false;
    if (pinDangNhap == null && pinMay == null) return false;
    var sh = _nvcsSheet(coSo, false); if (!sh) return false;
    var cot = _nvcsBoSungCot(sh, ['pin','pinMay']); if (!cot.ma) return false;
    var d = _nvcsDoc(coSo); if (!d.co || d.loi) return false;
    var k = _chuanMa(ma), hang = 0;
    for (var i = 0; i < d.list.length; i++){ if (_chuanMa(d.list[i].ma) === k) { hang = d.list[i].hang; break; } }
    if (!hang) return false;
    if (pinDangNhap != null && cot.pin)    sh.getRange(hang, cot.pin).setValue(String(pinDangNhap));
    if (pinMay      != null && cot.pinMay) sh.getRange(hang, cot.pinMay).setValue(String(pinMay));
    return true;
  } catch (e) { return false; }     // sheet cơ sở hỏng KHÔNG được làm hỏng việc lưu chính
}

/** Dòng PhanQuyen theo MÃ NV chấm công online. null nếu chưa có tài khoản. */
function _pqTheoMaNV(sh, ma){
  var last = sh.getLastRow(); if (last < 2) return null;
  var k = _chuanMa(ma); if (!k) return null;
  var v = sh.getRange(2, 1, last - 1, PQ_H.length).getValues();
  for (var i = 0; i < v.length; i++){
    if (_chuanMa(v[i][4]) === k) return { row:i + 2, data:v[i] };
  }
  return null;
}

/* 🔴 06/08/2026 — MỌI PIN đang gắn với một mã NV trong `PhanQuyen`.
 * Vì sao cần: `deleteEmployee` xoá hồ sơ trong `NhanVien` và xếp lệnh xoá xuống máy, nhưng
 * KHÔNG đụng `PhanQuyen`. Nên xoá xong, PIN của người đó VẪN ĐĂNG NHẬP ĐƯỢC — một tài khoản
 * trỏ vào mã không còn hồ sơ. Anh Thắng đang dọn 6 hồ sơ rác ở JP_HCM, mỗi hồ sơ rác đó mang
 * một tài khoản riêng (`378539`, `170412`…); xoá hồ sơ mà không nói ra là để lại 6 PIN sống.
 * ⚠️ CHỈ BÁO, KHÔNG TỰ XOÁ tài khoản: xoá tài khoản là việc riêng, phải anh quyết từng cái —
 *    có ca mã sai nhưng con người thật và tài khoản đó vẫn đang dùng. */
function _pqPinTheoMa(ma){
  try {
    var sh = _pqNoiCot(), last = sh.getLastRow(); if (last < 2) return [];
    var k = _chuanMa(ma); if (!k) return [];
    var v = sh.getRange(2, 1, last - 1, PQ_H.length).getValues(), out = [];
    for (var i = 0; i < v.length; i++){
      if (_chuanMa(v[i][4]) === k){ var p = String(v[i][0] || '').trim(); if (p) out.push(p); }
    }
    return out;
  } catch (e){ return []; }
}

/**
 * Ghi PIN đang có xuống sheet `NV_<cơ sở>` — việc "chuyển hết pin về sheet".
 * 🔴 KHÔNG ĐÈ ô đã có chữ: số anh gõ tay có thể là số anh CHỦ Ý đặt, đè lên là mất ý đó mà
 *    chẳng có gì báo. Ô đã có mà khác số hiện tại thì liệt kê ra để anh tự quyết.
 */
function xuatPinRaSheet(tok, coSo){
  var u = _requireAuth(tok);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin mới xuất PIN ra sheet.' };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!coSo) return { ok:false, error:'Thiếu cơ sở.' };
  var lock = LockService.getScriptLock(); try { lock.waitLock(20000); } catch(e){}
  try {
    var sh = _nvcsSheet(coSo, false);
    if (!sh) return { ok:false, error:'Chưa có sheet ' + _nvcsTen(coSo) + '.' };
    var cot = _nvcsBoSungCot(sh, ['pin','pinMay']);   // đang ghi PIN -> chỉ thêm 2 cột PIN
    if (!cot.ma) return { ok:false, error:'Sheet ' + _nvcsTen(coSo) + ' không nhận ra cột "Mã NV".' };
    if (!cot.pin || !cot.pinMay)
      return { ok:false, error:'Không tạo được cột PIN / PIN máy trong ' + _nvcsTen(coSo) + '.' };
    var d = _nvcsDoc(coSo);
    if (!d.co)  return { ok:false, error:'Không đọc được sheet ' + _nvcsTen(coSo) + '.' };
    if (d.loi)  return { ok:false, error:d.loi };

    var shPq = _pqNoiCot(), shNv = _ensureSheet(SH_NV);
    var ghiDn = 0, ghiMay = 0, giuNguyen = [], khong = 0;
    d.list.forEach(function(x){
      var tk    = _pqTheoMaNV(shPq, x.ma);
      var pinDn = tk ? String(tk.data[0] || '').trim() : '';
      var hs    = _findRow(shNv, 1, x.ma);
      var pinMay= hs ? String(hs.data[3] || '').trim() : '';
      if (!pinDn && !pinMay) { khong++; return; }
      // Ô đã có chữ -> GIỮ NGUYÊN. Khác số hiện tại thì báo, đừng lặng lẽ.
      var oDn = String(x.pin || '').trim(), oMay = String(x.pinMay || '').trim();
      if (pinDn) {
        if (oDn) { if (oDn !== pinDn) giuNguyen.push({ ma:x.ma, ten:x.ten, cot:'PIN', trongSheet:oDn, hienTai:pinDn }); }
        else { sh.getRange(x.hang, cot.pin).setValue(pinDn); ghiDn++; }
      }
      if (pinMay) {
        if (oMay) { if (oMay !== pinMay) giuNguyen.push({ ma:x.ma, ten:x.ten, cot:'PIN máy', trongSheet:oMay, hienTai:pinMay }); }
        else { sh.getRange(x.hang, cot.pinMay).setValue(pinMay); ghiMay++; }
      }
    });
    return { ok:true, coSo:coSo, tenSheet:_nvcsTen(coSo),
             cotPin:cot.pin, cotPinMay:cot.pinMay,
             ghiPin:ghiDn, ghiPinMay:ghiMay, khongCoPin:khong, giuNguyen:giuNguyen };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

/**
 * Đọc sheet `NV_<cơ sở>` -> đặt PIN thật. Hai cột XỬ LÝ ĐỘC LẬP: khai cột nào thì đặt cột đó.
 * 🔴 PIN đăng nhập trùng với BẤT KỲ ai trong toàn chuỗi -> TỪ CHỐI dòng đó, nói tên người đang giữ.
 * 🔴 Chỉ xếp lệnh xuống máy cho người PIN máy THẬT SỰ ĐỔI và ĐANG CÓ trên máy — đừng dư lệnh.
 */
function napPinTuSheet(tok, coSo){
  var u = _requireAuth(tok);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin mới nạp PIN (cột PIN là mật khẩu đăng nhập).' };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!coSo) return { ok:false, error:'Thiếu cơ sở.' };
  var lock = LockService.getScriptLock(); try { lock.waitLock(20000); } catch(e){}
  try {
    var d = _nvcsDoc(coSo);
    if (!d.co) return { ok:false, error:'Chưa có sheet ' + _nvcsTen(coSo) + '.' };
    if (d.loi) return { ok:false, error:d.loi };
    if (!d.cot || (!d.cot.pin && !d.cot.pinMay))
      return { ok:false, error:'Sheet ' + _nvcsTen(coSo) + ' chưa có cột "PIN" hay "PIN máy". '
             + 'Bấm "Xuất PIN ra sheet" một lần để app tạo cột.' };

    var shPq = _pqNoiCot(), shNv = _ensureSheet(SH_NV);
    /* Bản đồ PIN đăng nhập -> người đang giữ, lấy TOÀN BỘ PhanQuyen (không riêng cơ sở này):
       đăng nhập không phân theo cơ sở, nên trùng với người cơ sở khác cũng là trùng. */
    var giu = {}, lastPq = shPq.getLastRow();
    if (lastPq >= 2){
      shPq.getRange(2, 1, lastPq - 1, PQ_H.length).getValues().forEach(function(r, i){
        var p = String(r[0] || '').trim(); if (!p) return;
        if (!giu[p]) giu[p] = { hang:i + 2, ten:String(r[1] || ''), ma:String(r[4] || '').trim() };
      });
    }

    var doiTk = 0, doiMay = 0, lenh = [], boQua = [], pinYeu = [], trongLo = {};

    d.list.forEach(function(x){
      var maK = _chuanMa(x.ma);

      // ---------- (1) PIN ĐĂNG NHẬP -> PhanQuyen ----------
      var oDn = String(x.pin || '').trim();
      if (oDn) {
        var kt = _pinChung(oDn);
        if (!kt.ok) boQua.push({ hang:x.hang, ma:x.ma, ten:x.ten, cot:'PIN', ly:kt.ly });
        else if (trongLo[kt.pin] && trongLo[kt.pin] !== maK)
          boQua.push({ hang:x.hang, ma:x.ma, ten:x.ten, cot:'PIN',
            ly:'PIN ' + kt.pin + ' bị khai hai lần ngay trong sheet này' });
        else {
          var ng = giu[kt.pin];
          if (ng && _chuanMa(ng.ma) !== maK)
            boQua.push({ hang:x.hang, ma:x.ma, ten:x.ten, cot:'PIN',
              ly:'PIN ' + kt.pin + ' đang là PIN của ' + (ng.ten || ng.ma || 'một tài khoản khác')
                 + ' — trùng PIN thì người này đăng nhập vào tài khoản người kia' });
          else {
            trongLo[kt.pin] = maK;
            if (kt.yeu) pinYeu.push({ ma:x.ma, ten:x.ten, pin:kt.pin });
            var tk = _pqTheoMaNV(shPq, x.ma);
            if (tk) {
              var pinCu = String(tk.data[0] || '').trim();
              if (pinCu !== kt.pin) {
                shPq.getRange(tk.row, 1).setValue(kt.pin);
                delete giu[pinCu]; giu[kt.pin] = { hang:tk.row, ten:x.ten, ma:x.ma };
                _clearAuthCache(pinCu); _clearAuthCache(kt.pin);
                doiTk++;
              }
            } else {
              /* Chưa có tài khoản -> tạo, vai NHÂN VIÊN (thấp nhất: chỉ chấm công online cho
                 chính mình). KHÔNG đoán vai cao hơn — cấp nhầm quyền tệ hơn không cấp. */
              /* 🔴 KHÔNG `|| x.ma` — xem ghi chú ở `_pqTenHienThi`. Thiếu tên thì để TRỐNG rồi tra
                 hồ sơ lúc đăng nhập; ghi mã vào ô `Họ tên` là biến ô thiếu thành ô SAI. */
              var _tenM = String(x.ten || '').trim();
              if (_chuanMa(_tenM) === _chuanMa(x.ma)) _tenM = '';
              shPq.appendRow(_pqHangMoi({ pin:kt.pin, name:_tenM, role:ROLE.NHAN_VIEN,
                                          stations:coSo, maNV:x.ma, coSoOnline:coSo }));
              giu[kt.pin] = { hang:shPq.getLastRow(), ten:x.ten, ma:x.ma };
              _clearAuthCache(kt.pin);
              doiTk++;
            }
          }
        }
      }

      // ---------- (2) PIN MÁY -> NhanVien cột 4 ----------
      var oMay = String(x.pinMay || '').trim();
      if (oMay) {
        var km = _pinChung(oMay);
        if (!km.ok) { boQua.push({ hang:x.hang, ma:x.ma, ten:x.ten, cot:'PIN máy', ly:km.ly }); return; }
        var hs = _findRow(shNv, 1, x.ma);
        if (!hs) { boQua.push({ hang:x.hang, ma:x.ma, ten:x.ten, cot:'PIN máy',
                                ly:'chưa có hồ sơ nhân viên cho mã này' }); return; }
        var cu = String(hs.data[3] || '').trim();
        if (cu === km.pin) return;                       // không đổi -> không ghi, không xếp lệnh
        shNv.getRange(hs.row, 4).setValue(km.pin);
        doiMay++;
        /* Người ĐANG CÓ trên máy thì phải báo cho máy biết PIN mới. Người chưa lên máy
           (`khong-day-may` / trạng thái rỗng) thì KHÔNG xếp lệnh — đúng ý "đừng dư lệnh". */
        var tt = String(hs.data[5] || '').trim().toLowerCase();
        if (tt && tt !== 'khong-day-may')
          lenh.push(_enqueue('edit', x.ma, String(hs.data[1] || x.ten), km.pin,
                             String(hs.data[4] || ''),
                             String(hs.data[2] || coSo).replace(/^CS_/, ''), '', ''));
      }
    });

    return { ok:true, coSo:coSo, tenSheet:_nvcsTen(coSo),
             doiTaiKhoan:doiTk, doiPinMay:doiMay, lenhMay:lenh,
             boQua:boQua, pinYeu:pinYeu };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}


/* ===========================================================================
 *  QUÉT MỌI SHEET `NV_*` -> ĐỔ VỀ `NhanVien`   (07/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"xuất PIN ra tab Nhân Viên, để tôi xoá hết các sheet NV_"* ·
 *             *"quét nv và đổ pin về đi em, ai chưa có pin mình cấp pin luôn"*.
 *
 *  🔴 KHÔNG CHỈ CÓ PIN. `NV_<cơ sở>` còn giữ **Nhiệm vụ** (*Thu Tiền - Vệ Sinh* / *Trực Ghế
 *     Posh - JP*), mà `_bangNhiemVuCoSo` đọc thẳng để TÍNH LƯƠNG — hai nhiệm vụ khác đơn giá.
 *     Chỉ chuyển PIN rồi để anh xoá sheet là mọi người rơi về mặc định "Thu Tiền" TRONG IM LẶNG:
 *     bảng vẫn xanh, lương vẫn ra số, chỉ là sai. Nên hàm này chuyển CẢ BA: PIN đăng nhập ·
 *     PIN máy · Nhiệm vụ.
 *  🔴 KHÔNG ĐÈ ô đã có chữ trong `NhanVien`. Ô khác giá trị thì LIỆT KÊ để anh tự quyết —
 *     `NhanVien` là bản anh vừa nhập chuẩn từ file công ty, nó không phải hạng dưới của `NV_`.
 *  🔴 Nguồn thật của PIN đăng nhập là `PhanQuyen`, KHÔNG phải ô trong sheet. Nên thứ tự lấy là
 *     `PhanQuyen` trước, ô trong `NV_` chỉ dùng khi người đó chưa có tài khoản nào.
 *  ⚠️ Ghi theo CỘT (3 lượt `setValues`), không phải mỗi ô một lượt: 244 người × 3 cột là hơn 700
 *     vòng đi–về dịch vụ Sheets, quá 6 phút là hàm bị cắt giữa chừng.
 * =========================================================================== */
function quetNvVeNhanVien(pin, opt){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin mới chuyển PIN / nhiệm vụ về sheet nhân sự.' };
  opt = opt || {};
  var chayThat = !!opt.chayThat, capPin = !!opt.capPinThieu;
  var lock = LockService.getScriptLock(); try { lock.waitLock(30000); } catch(e){}
  try {
    /* ---- (1) Gom mọi sheet NV_* ---- */
    var ss = SpreadsheetApp.getActiveSpreadsheet(), dsSheet = [], tuNv = {}, xungDot = [];
    ss.getSheets().forEach(function(s){
      var ten = s.getName();
      if (ten.indexOf(NVCS_TIEN_TO) !== 0) return;
      var coSo = ten.substring(NVCS_TIEN_TO.length).trim(); if (!coSo) return;
      var d = _nvcsDoc(coSo);
      if (!d.co) return;
      dsSheet.push({ ten:ten, coSo:coSo, so:(d.list || []).length, loi:d.loi || '' });
      if (d.loi) return;                                  // tiêu đề hỏng -> đọc là đọc nhầm cột
      d.list.forEach(function(x){
        var k = _chuanMa(x.ma); if (!k) return;
        var cu = tuNv[k];
        if (!cu){ tuNv[k] = { ma:x.ma, ten:x.ten, pin:x.pin, pinMay:x.pinMay,
                              nhiemVu:x.nhiemVuTho, coSo:coSo, sheet:ten }; return; }
        /* Một người có tên ở HAI sheet NV_ mà khai nhiệm vụ khác nhau -> KHÔNG đoán hộ.
           Đoán sai ở đây là tính sai đơn giá cho cả tháng của họ. */
        ['pin','pinMay','nhiemVu'].forEach(function(f){
          var a = String(cu[f] || '').trim(), b = String(x[f === 'nhiemVu' ? 'nhiemVuTho' : f] || '').trim();
          if (a && b && a !== b)
            xungDot.push({ ma:x.ma, ten:x.ten || cu.ten, cot:f, a:a, sheetA:cu.sheet, b:b, sheetB:ten });
          if (!a && b) cu[f] = b;
        });
      });
    });

    /* ---- (2) PhanQuyen: mã NV -> PIN đăng nhập đang dùng ---- */
    var shPq = _pqNoiCot(), lastPq = shPq.getLastRow();
    var pqTheoMa = {}, pinDaDung = {};
    if (lastPq >= 2){
      shPq.getRange(2, 1, lastPq - 1, PQ_H.length).getValues().forEach(function(r, i){
        var p = String(r[0] == null ? '' : r[0]).trim(); if (p) pinDaDung[p] = true;
        var m = _chuanMa(r[4]); if (m && !pqTheoMa[m]) pqTheoMa[m] = { pin:p, hang:i + 2 };
      });
    }

    /* ---- (3) Duyệt từng hồ sơ trong NhanVien ---- */
    var sh = _nvSheet(), n = NV_HEADERS.length, last = sh.getLastRow();
    var iMay = 4, iNv = 8 + NV_EXTRA.indexOf('nhiemVu'), iDn = 8 + NV_EXTRA.indexOf('pinDangNhap');
    if (last < 2)
      return { ok:true, chayThat:chayThat, sheetNv:dsSheet, soHoSo:0, ghiPinMay:0, ghiNhiemVu:0,
               ghiPinDangNhap:0, capMoi:[], giuNguyen:[], xungDot:xungDot, khongCoHoSo:[],
               khongCapDuoc:[], ghiChu:'Sheet NhanVien chưa có hồ sơ nào.' };

    var v = sh.getRange(2, 1, last - 1, n).getValues();
    var cMay = [], cNv = [], cDn = [], themPq = [];
    var ghiMay = 0, ghiNv = 0, ghiDn = 0, capMoi = [], giuNguyen = [], khongCap = [], daThay = {};

    for (var i = 0; i < v.length; i++){
      var ma = String(v[i][0] == null ? '' : v[i][0]).trim();
      var k  = _chuanMa(ma);
      var nguon = k ? tuNv[k] : null;
      if (k) daThay[k] = 1;

      var oMay = String(v[i][iMay - 1] == null ? '' : v[i][iMay - 1]).trim();
      var oNv  = String(v[i][iNv  - 1] == null ? '' : v[i][iNv  - 1]).trim();
      var oDn  = String(v[i][iDn  - 1] == null ? '' : v[i][iDn  - 1]).trim();

      // --- PIN máy: chỉ điền khi ô đang trống ---
      if (nguon && nguon.pinMay){
        if (!oMay) { oMay = nguon.pinMay; ghiMay++; }
        else if (oMay !== nguon.pinMay)
          giuNguyen.push({ ma:ma, ten:String(v[i][1] || ''), cot:'PIN máy',
                           trongHoSo:oMay, trongNv:nguon.pinMay, sheet:nguon.sheet });
      }
      // --- Nhiệm vụ: chỉ điền khi ô đang trống ---
      if (nguon && nguon.nhiemVu){
        if (!oNv) { oNv = nguon.nhiemVu; ghiNv++; }
        else if (_khongDau(oNv) !== _khongDau(nguon.nhiemVu))
          giuNguyen.push({ ma:ma, ten:String(v[i][1] || ''), cot:'Nhiệm vụ',
                           trongHoSo:oNv, trongNv:nguon.nhiemVu, sheet:nguon.sheet });
      }
      // --- PIN đăng nhập: PhanQuyen là nguồn thật, ô trong NV_ chỉ dùng khi chưa có tài khoản ---
      var tk = k ? pqTheoMa[k] : null;
      var pinThat = tk ? tk.pin : '';
      if (!pinThat && nguon && nguon.pin && _pinChung(nguon.pin).ok && !pinDaDung[_pinChung(nguon.pin).pin])
        pinThat = _pinChung(nguon.pin).pin;               // anh đã gõ sẵn trong NV_, chưa ai dùng

      if (!pinThat && capPin && ma && !_tachMaNhiemVu(ma)){
        var moi = _pinChuaDung(pinDaDung);
        if (!moi) khongCap.push({ ma:ma, ten:String(v[i][1] || ''), ly:'không sinh được PIN mới' });
        else {
          pinThat = moi; pinDaDung[moi] = true;
          var cs = String(v[i][2] || '').replace(/^CS_/, '').trim();
          /* KHÔNG `|| ma` cho ô Họ tên — ghi mã vào ô tên là biến ô thiếu thành ô SAI, rồi màn
             hình chào "Xin chào, MNNV2MTD0026" (anh Thắng bắt được 07/08). */
          var tenM = String(v[i][1] || '').trim();
          if (_chuanMa(tenM) === _chuanMa(ma)) tenM = '';
          themPq.push(_pqHangMoi({ pin:moi, name:tenM, role:ROLE.NHAN_VIEN,
                                   stations:cs, maNV:ma, coSoOnline:cs }));
          capMoi.push({ ma:ma, ten:tenM, pin:moi, coSo:cs, thieuCoSo:!cs });
        }
      }
      if (pinThat){
        if (!oDn) { oDn = pinThat; ghiDn++; }
        else if (oDn !== pinThat)
          giuNguyen.push({ ma:ma, ten:String(v[i][1] || ''), cot:'PIN đăng nhập',
                           trongHoSo:oDn, trongNv:pinThat, sheet:'PhanQuyen' });
      }
      cMay.push([oMay]); cNv.push([oNv]); cDn.push([oDn]);
    }

    /* Người có trong NV_ mà KHÔNG có hồ sơ -> PIN/nhiệm vụ của họ không có chỗ nào để đổ về.
       Xoá sheet NV_ lúc này là mất hẳn. Phải nêu tên. */
    var khongCoHoSo = [];
    Object.keys(tuNv).forEach(function(k2){
      if (daThay[k2]) return;
      var x = tuNv[k2];
      if (x.pin || x.pinMay || x.nhiemVu)
        khongCoHoSo.push({ ma:x.ma, ten:x.ten, sheet:x.sheet,
                           co:[x.pin ? 'PIN' : '', x.pinMay ? 'PIN máy' : '',
                               x.nhiemVu ? 'Nhiệm vụ' : ''].filter(Boolean).join(' · ') });
    });

    if (chayThat){
      var soD = v.length;
      if (ghiMay) sh.getRange(2, iMay, soD, 1).setValues(cMay);
      if (ghiNv)  sh.getRange(2, iNv,  soD, 1).setValues(cNv);
      if (ghiDn)  sh.getRange(2, iDn,  soD, 1).setValues(cDn);
      if (themPq.length){
        shPq.getRange(shPq.getLastRow() + 1, 1, themPq.length, PQ_H.length).setValues(themPq);
        capMoi.forEach(function(x){ _clearAuthCache(x.pin); });
      }
      // Hai cột toàn số hay có số 0 đầu -> ép VĂN BẢN, không thì `049…` rụng số 0.
      [iMay, iDn].forEach(function(c){
        try { sh.getRange(2, c, Math.max(1, sh.getMaxRows() - 1), 1).setNumberFormat('@'); } catch(e){}
      });
    }
    return { ok:true, chayThat:chayThat, sheetNv:dsSheet, soHoSo:v.length,
             ghiPinMay:ghiMay, ghiNhiemVu:ghiNv, ghiPinDangNhap:ghiDn,
             capMoi:capMoi, giuNguyen:giuNguyen, xungDot:xungDot,
             khongCoHoSo:khongCoHoSo, khongCapDuoc:khongCap,
             xoaDuocNv: !khongCoHoSo.length && !xungDot.length && chayThat };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

function _pqNoiCot(){                       // sheet cũ 4 cột -> nối thêm 2 cột mới, không mất dữ liệu
  var sh = _ensureSheet(SH_ROLE);
  var lc = sh.getLastColumn();
  if (lc < PQ_H.length){
    sh.getRange(1, 1, 1, PQ_H.length).setValues([PQ_H])
      .setFontWeight('bold').setBackground('#0f172a').setFontColor('#38bdf8');
  }
  return sh;
}

/** Dòng phân quyền của 1 PIN, kèm 2 cột chấm công online. null nếu không có. */
function _pqDong(pin){
  var sh = _pqNoiCot(), last = sh.getLastRow();
  if (last < 2) return null;
  var v = sh.getRange(2, 1, last - 1, PQ_H.length).getValues();
  for (var i = 0; i < v.length; i++){
    if (String(v[i][0]).trim() === String(pin).trim())
      return { row: i + 2, pin:String(v[i][0]), name:String(v[i][1]||''), role:String(v[i][2]||''),
               stations:String(v[i][3]||''), maNV:String(v[i][4]||'').trim(),
               coSo:String(v[i][5]||'').replace(/^CS_/, '').trim() };
  }
  return null;
}

/** Giờ MÁY CHỦ để client đóng dấu lên ảnh — cùng nguồn với giờ sẽ ghi vào sheet. */
function getGioMayChu(pin){
  var u = loginByPin(pin);
  if (u.ok === false) return { ok:false };
  var d = new Date();
  return { ok:true,
           ngay: Utilities.formatDate(d, TZ, 'yyyy-MM-dd'),
           gio:  Utilities.formatDate(d, TZ, 'HH:mm:ss'),
           hienThi: Utilities.formatDate(d, TZ, 'dd/MM/yyyy HH:mm:ss') };
}

/** Tài khoản này có được chấm công online không, và hôm nay đã chấm gì. */
function getChamCongOnlineInfo(pin){
  var u = _requireAuth(pin);
  var d = _pqDong(u.pin);
  if (!d || !d.maNV) return { ok:true, bat:false,
    ghiChu:'Tài khoản này chưa bật chấm công online. Nhờ Admin điền "Mã NV chấm công online" '
         + 'và "Cơ sở chấm công online" ở tab Phân quyền.' };
  var coSo = d.coSo || String(d.stations || '').split(',')[0].trim().replace(/^CS_/, '');
  if (!coSo) return { ok:true, bat:false, ghiChu:'Chưa khai "Cơ sở chấm công online" cho tài khoản này.' };
  var sh = _sheet('CS_' + coSo);
  if (!sh) return { ok:true, bat:false, ghiChu:'Không thấy sheet CS_' + coSo + '.' };

  var now = new Date(), ngay = Utilities.formatDate(now, TZ, 'yyyy-MM-dd');
  /* 🔴 07/08/2026 — BỎ khối tính giờ vào/ra riêng ở đây.
     Nó làm ĐÚNG việc mà `_ccHomNayNhieuMa` bên dưới đã làm cho từng cơ sở (kể cả cơ sở mặc định),
     nhưng làm bằng cách tệ hơn hẳn: một `getValue()` cho MỖI người để dò mã, rồi hai `getValue()`
     nữa để lấy giờ — vài chục lượt đi–về sang dịch vụ Sheets cho một con số đã có sẵn.
     Nay lấy thẳng từ `dsCoSo`. Kết quả y hệt, và giao diện vốn cũng chỉ đọc `dsCoSo` (hai ô
     `vao`/`ra` cấp trên chỉ còn là đường lùi khi `dsCoSo` rỗng). */
  /* Nhiệm vụ khai RIÊNG TỪNG CƠ SỞ ở sheet `NV_<cơ sở>` (anh Thắng chốt 03/08): ở Posh có thể
     Thu Tiền, ở JP lại Trực Ghế. Nên phải tính cho TỪNG cơ sở, không dùng một danh sách chung.
     ⚠️ Nhiệm vụ chỉ có nghĩa ở Nhóm Máy Tự Động — cơ sở khác trả rỗng để màn hình khỏi hỏi.
     ⚠️ Kiêm ≥2 việc = ≥2 HÀNG, mỗi hàng một giờ vào/ra riêng. Phải trả giờ của TỪNG hàng thì nút
        chọn việc mới nói đúng "bấm để CHECK-IN" hay "bấm để CHECK-OUT" — nói sai còn tệ hơn không nói. */
  var dsCoSo = _dsCoSoCuaNv(d.maNV, coSo).map(function(cs){
    var dsNvCs = _laMayTuDong(cs) ? _nhiemVuTaiCoSo(cs, d.maNV) : [];
    var maTheoNv = {}, dsMa = [d.maNV];
    dsNvCs.forEach(function(nv){
      var m = _maChoNhiemVu(d.maNV, nv, dsNvCs);
      maTheoNv[nv] = m;
      if (dsMa.indexOf(m) < 0) dsMa.push(m);
    });
    var bang = _ccHomNayNhieuMa(cs, dsMa, ngay);
    var g = bang[d.maNV] || { vao:'', ra:'' }, gioNv = {};
    if (dsNvCs.length > 1) dsNvCs.forEach(function(nv){
      gioNv[nv] = bang[maTheoNv[nv]] || { vao:'', ra:'' };
    });
    return { coSo: cs, vao: g.vao, ra: g.ra, dsNhiemVu: dsNvCs, gioNv: gioNv };
  });
  // `dsNhiemVu` / `vao` / `ra` cấp trên = của cơ sở MẶC ĐỊNH, giữ cho chỗ gọi cũ; màn hình đọc
  // theo từng cơ sở trong `dsCoSo`.
  var dsNvMac = [], vao = '', ra = '';
  dsCoSo.forEach(function(x){
    if (x.coSo !== coSo) return;
    dsNvMac = x.dsNhiemVu; vao = x.vao; ra = x.ra;
  });
  /* 🔴 07/08/2026 — anh Thắng: *"thay vì hiện mã NV hãy hiện tên nhân viên"* (trang chấm công
     online lẻ). Lấy qua `_pqTenHienThi`: ô `Họ tên` trong `PhanQuyen` có thể TRỐNG (cấp PIN hàng
     loạt cố ý để trống thay vì ghi mã vào đó), lúc ấy nó tra tên thật trong `NhanVien` theo mã.
     Dùng lại hàm sẵn có, KHÔNG viết luật tra tên thứ hai — hai luật thì sớm muộn một bên hiện
     tên người khác, mà đây là màn hình người ta bấm chấm công cho chính mình. */
  return { ok:true, bat:true, maNV:d.maNV, coSo:coSo,
           hoTen:_pqTenHienThi(d.name, d.maNV), ngay:ngay, vao:vao, ra:ra,
           dsNhiemVu: dsNvMac, nhiemVuMacDinh: NHIEM_VU_MAC_DINH, dsCoSo: dsCoSo };
}

/* ===========================================================================
 *  ẢNH MẪU 3×4 TRÊN TRANG CHẤM CÔNG ONLINE  (07/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"anh muốn gắn ảnh mẫu lên, gắn như nào"*.
 *  Trang chấm công đang vẽ hình bóng người bằng SVG. Anh muốn thay bằng ẢNH THẬT để nhân viên
 *  nhìn là biết chụp thế nào.
 *
 *  Cách làm: anh bỏ ảnh vào Drive, dán link vào app một lần. App đọc file bằng quyền của chính
 *  nó rồi trả về dạng base64 — **KHÔNG** để trang nhúng link Drive trực tiếp:
 *    · link Drive đòi ảnh phải chia sẻ công khai (ai cầm link cũng xem được);
 *    · và trang này cố ý KHÔNG tải gì từ mạng ngoài, để mở được ở chỗ sóng yếu.
 *  🔴 KHÔNG cần đăng nhập mới xem được ảnh mẫu: khối "nhân viên tự gửi thông tin" nằm ở phần
 *     CHƯA đăng nhập. Nhưng hàm này chỉ đọc ĐÚNG file mà Admin đã khai, không nhận id từ client
 *     — nếu không thì nó thành cửa đọc mọi file Drive của công ty.
 * =========================================================================== */
var AMT_KEY = 'ANH_MAU_THE_ID';
var AMT_CACHE = 'anhmau1';
var AMT_TOI_DA = 90 * 1024;        // >90KB thì thôi không cache (CacheService chặn ~100KB)

/* ===========================================================================
 *  CÀI ĐẶT DÙNG CHUNG HAI APP — sheet `CaiDat`   (07/08/2026)
 * ---------------------------------------------------------------------------
 *  🔴 BÀI HỌC ĐẮT: **Script Property là RIÊNG của từng project.** Anh Thắng dán link ảnh mẫu
 *     trong app quản trị (`ChamCongLive`), lưu xong màn hình hiện ảnh đàng hoàng — nhưng trang
 *     `ChamCongOnline` là MỘT PROJECT KHÁC, đọc Script Property của chính nó thấy rỗng, nên vẫn
 *     hiện hình vẽ. Anh gửi ảnh: "trang này chưa có". Đúng, và không có gì báo lỗi cả.
 *     (Cùng loại với `SHEET_ID` / `SSO_SECRET` — hai thứ đó phải khai TAY ở cả hai project.)
 *  Thứ nào HAI APP CÙNG PHẢI THẤY thì để trong **bảng tính**, không để trong Script Property:
 *  cả hai app đều mở đúng một bảng tính đó.
 *  ⚠️ Script Property vẫn được đọc làm ĐƯỜNG LÙI cho giá trị lưu từ trước, nhưng chỗ GHI mới thì
 *     chỉ ghi vào sheet — hai chỗ ghi là sớm muộn lệch nhau.
 * =========================================================================== */
var SH_CAIDAT = 'CaiDat';
var CAIDAT_H  = ['Khoá', 'Giá trị', 'Cập nhật'];
function _caiDatDoc(khoa){
  try {
    var sh = _sheet(SH_CAIDAT); if (!sh) return '';
    var last = sh.getLastRow(); if (last < 2) return '';
    var v = sh.getRange(2, 1, last - 1, 2).getValues();
    for (var i = 0; i < v.length; i++)
      if (String(v[i][0] || '').trim() === khoa) return String(v[i][1] == null ? '' : v[i][1]).trim();
  } catch (e) {}
  return '';
}
function _caiDatGhi(khoa, giaTri){
  var sh = _ensureSheet(SH_CAIDAT, CAIDAT_H);
  var last = sh.getLastRow(), hang = 0;
  if (last >= 2){
    var v = sh.getRange(2, 1, last - 1, 1).getValues();
    for (var i = 0; i < v.length; i++)
      if (String(v[i][0] || '').trim() === khoa) { hang = i + 2; break; }
  }
  if (hang) sh.getRange(hang, 2, 1, 2).setValues([[giaTri, _now()]]);
  else      sh.appendRow([khoa, giaTri, _now()]);
}

/* ===========================================================================
 *  APP LƯƠNG — ĐĂNG KÝ SHEET ↔ CƠ SỞ  (09/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"Lương sẽ lấy từ các sheet CS được tạo ra. Các sheet được quét 1 lần. Thủ công để
 *  dùng. Như vậy tránh mò. Khi quét có tên rồi. Sau cứ đúng sheet đó mà đọc tránh đi mò sheet nào
 *  cơ sở nào. Có 1, 2 cơ sở có 2 sheet do có làm đêm. Còn lại mặc định là 1"*.
 *
 *  Chấm công dò tên sheet `CS_*` MỖI LẦN gọi (`_dsCoSoCuaNv` và nhiều chỗ khác) — ĐÚNG cho việc
 *  của nó (quyền chấm công phải sống theo dữ liệu đang có, xem hồ sơ luật B ở `_dsCoSoCuaNv`).
 *  Lương cần khác: một danh sách CỐ ĐỊNH giữa các lần tính (không dò lại mỗi lần), và cho GỘP
 *  NHIỀU SHEET vào một cơ sở (ca đêm). Nên có bước QUÉT MỘT LẦN — xem trước → xác nhận → lưu,
 *  đúng khuôn `quetNvVeNhanVien` đã có, KHÔNG viết một luật đọc sheet thứ hai chạy ngầm.
 *
 *  Lưu vào sheet `CaiDat` (hai app cùng đọc được) — KHÔNG vào Script Property (property là RIÊNG
 *  từng project, xem bài học ngay phía trên ở `getAnhMauThe`/`AMT_KEY`).
 * =========================================================================== */
var LUONG_DS_CO_SO_KEY = 'LUONG_DS_CO_SO';

/** Đọc cấu hình đã lưu. `null` = CHƯA quét/lưu lần nào. */
function _luongDsCoSoDoc(){
  var raw = _caiDatDoc(LUONG_DS_CO_SO_KEY);
  if (!raw) return null;
  try { var o = JSON.parse(raw); return (o && typeof o === 'object' && !Array.isArray(o)) ? o : null; }
  catch (e) { return null; }
}

/**
 * XEM TRƯỚC — CHỈ ĐỌC, không ghi gì. Quét mọi sheet `CS_*`, gợi ý tên cơ sở = bỏ tiền tố `CS_`.
 * ⚠️ Đã có cấu hình lưu trước thì ĐỔ LẠI đúng tên đã gộp lần trước (khớp theo tên sheet) — quét
 *    lại khi có cơ sở MỚI không được xoá mất công anh đã gộp cho các sheet cũ.
 */
function luongXemTruocSheetCS(pin){
  var u = loginLuong(pin);
  if (!u.ok) return u;
  var cuMap = _luongDsCoSoDoc() || {};
  var cuTheoSheet = {};
  Object.keys(cuMap).forEach(function(cs){
    (cuMap[cs] || []).forEach(function(sh){ cuTheoSheet[sh] = cs; });
  });
  var ds = [];
  SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(sh){
    var ten = sh.getName();
    if (ten.indexOf('CS_') !== 0) return;
    var macDinh = ten.substring(3).trim(); if (!macDinh) return;
    ds.push({ sheet: ten, coSo: cuTheoSheet[ten] || macDinh });
  });
  ds.sort(function(a, b){ return a.sheet < b.sheet ? -1 : (a.sheet > b.sheet ? 1 : 0); });
  return { ok:true, ds: ds, daQuetTruoc: !!Object.keys(cuMap).length };
}

/**
 * LƯU THẬT. `ds` = [{sheet, coSo}, …] từ đúng màn hình xem trước (anh có thể đã sửa tên `coSo`).
 * Nhiều dòng CÙNG một `coSo` thì GỘP thành một cơ sở nhiều sheet — đây chính là đường cho ca
 * "1-2 cơ sở làm đêm, 2 sheet" anh nói tới.
 * ⚠️ CHỈ ADMIN — đây là cấu hình GỐC cho mọi lần tính lương sau này, lưu sai là tính sai cả một
 *    cơ sở mà không ai biết cho tới cuối tháng.
 */
function luongLuuSheetCS(pin, ds){
  var u = loginLuong(pin);
  if (!u.ok) return u;
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin được lưu cấu hình này.' };
  ds = Array.isArray(ds) ? ds : [];

  var ssThat = {};
  SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(s){ ssThat[s.getName()] = true; });

  var loi = [], map = {}, daGap = {};
  ds.forEach(function(d){
    var sheet = String((d && d.sheet) || '').trim();
    var coSo  = String((d && d.coSo)  || '').trim();
    if (!sheet) return;
    if (sheet.indexOf('CS_') !== 0) { loi.push(sheet + ': không phải sheet CS_'); return; }
    if (!ssThat[sheet]) { loi.push(sheet + ': không còn trong bảng tính — quét lại trước khi lưu.'); return; }
    if (daGap[sheet]) { loi.push(sheet + ': xuất hiện hai lần trong danh sách gửi lên.'); return; }
    daGap[sheet] = 1;
    if (!coSo) { loi.push(sheet + ': chưa đặt tên cơ sở.'); return; }
    (map[coSo] = map[coSo] || []).push(sheet);
  });
  if (loi.length) return { ok:false, error: loi.join(' · ') };
  if (!Object.keys(map).length) return { ok:false, error:'Chưa có sheet nào để lưu — quét lại trước.' };

  _caiDatGhi(LUONG_DS_CO_SO_KEY, JSON.stringify(map));
  return { ok:true, soCoSo: Object.keys(map).length,
           coSoNhieuSheet: Object.keys(map).filter(function(k){ return map[k].length > 1; }) };
}

/**
 * Danh sách cơ sở ĐÃ ĐĂNG KÝ — dùng cho ô chọn cơ sở của màn hình chính Lương. KHÔNG dò sheet.
 * `daQuet:false` khi chưa quét/lưu lần nào — màn hình phải NÓI RÕ lý do ô chọn rỗng, không hiện
 * một ô chọn trống trơn mà im lặng.
 */
function luongDsCoSo(pin){
  var u = loginLuong(pin);
  if (!u.ok) return u;
  var map = _luongDsCoSoDoc();
  if (!map) return { ok:true, daQuet:false, ds:[] };
  return { ok:true, daQuet:true, ds: Object.keys(map).sort() };
}

/* ===========================================================================
 *  MÀN HÌNH CHÍNH LƯƠNG — chọn 1 cơ sở → bảng công + lương   (09/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"Hiện cả bảng công và tính ra lương khi chọn 1 cơ sở nhé"*.
 *
 *  🔴 KHÔNG viết công thức tính lương lần hai. Chấm công đã có SẴN hai công thức chạy thật:
 *     · Nhóm Máy Tự Động (Posh/JP, `_laMayTuDong`) -> `_mtdTinhLuong` (đơn giá theo công/giờ)
 *     · Bộ phận Văn phòng (`_vpLaVanPhong`)         -> `_vpBangCongVaLuong` (lương cơ bản ÷ ngày công)
 *     Lương chỉ GỌI LẠI đúng hai hàm thuần đó — sai một số ở đây là hai app hiện hai số khác
 *     nhau cho cùng một người, mà không ai biết cái nào đúng.
 *  🔴 Còn lại (Khu vui chơi / Chưa xếp) — anh Thắng chốt 09/08: *"làm trước 2 bộ phận đã có công
 *     thức"* — CHƯA có công thức lương nào trong hệ thống cho nhóm này. Trả THÔ (giờ vào/ra),
 *     `coLuong:false`, không suy đoán một công thức nào để "cho có số".
 *  ⚠️ KHÔNG lọc theo `_canStation` — khác hẳn Chấm công. Kế toán quản lý lương CẢ CHUỖI, không
 *     phải một cơ sở; `loginLuong` (chỉ Admin/Kế toán) đã là cổng quyền duy nhất cần ở đây.
 */
function luongBangCongVaLuong(pin, station, monthLabel){
  var u = loginLuong(pin);
  if (!u.ok) return u;
  station = String(station || '').trim();
  if (!station) return { ok:false, error:'Thiếu cơ sở.' };
  var prefix = _vpThangPrefix(monthLabel);
  if (!prefix) return { ok:false, error:'Tháng không hợp lệ.' };

  if (_laMayTuDong(station)){
    var r = _mtdTinhLuong(station, monthLabel);
    if (r.error) return { ok:false, error:r.error };
    return { ok:true, coLuong:true, boPhan:'Máy tự động', kieu:'mtd', mtd:r };
  }
  if (_vpLaVanPhong(station)){
    var r2 = _vpBangCongVaLuong(station, monthLabel);
    if (r2.error) return { ok:false, error:r2.error };
    return { ok:true, coLuong:true, boPhan:'Văn phòng', kieu:'vp', vp:r2 };
  }
  var tho = _bangCongTho(station, prefix);
  if (tho.error) return { ok:false, error:tho.error };
  return { ok:true, coLuong:false, boPhan:(_bpMap()[station] || BP_CHUA_XEP), kieu:'tho', tho:tho };
}

/** Sheet(s) THẬT của một cơ sở đã "Quét sheet CS" (Bước 3) — hỗ trợ 1 cơ sở gộp nhiều sheet
 *  (ca đêm). Chưa từng quét/lưu (hoặc cơ sở lạ, không có trong cấu hình) -> đường lùi: coi tên
 *  cơ sở CHÍNH LÀ tên sheet (đúng mọi dữ liệu hiện có, vì 26/26 cơ sở đã quét đều 1-sheet-1-tên). */
function _luongSheetsCuaCoSo(station){
  var map = _luongDsCoSoDoc();
  if (map && map[station] && map[station].length) return map[station].slice();
  return ['CS_' + station];
}

/**
 * BẢNG CÔNG THÔ — giờ vào/ra từng ngày, gộp theo người. KHÔNG tính công, KHÔNG tính tiền: dùng
 * cho cơ sở CHƯA có công thức lương (xem khối chú thích ở `luongBangCongVaLuong`).
 */
function _bangCongTho(station, prefix){
  var sheets = _luongSheetsCuaCoSo(station).filter(function(s){ return !!_sheet(s); });
  if (!sheets.length) return { error:'Không tìm thấy sheet chấm công cho cơ sở "' + station + '".' };
  var byEmp = {}, thuTu = [];
  sheets.forEach(function(sh){
    (_docSheetData(sh, prefix).rows || []).forEach(function(row){
      var day = String(row[1] || ''); if (day.indexOf(prefix) !== 0) return;
      var ma = String(row[2] || '').trim(); if (!ma) return;
      var e = byEmp[ma];
      if (!e){ e = byEmp[ma] = { ma:ma, ten:String(row[3] || ma), ngay:[] }; thuTu.push(ma); }
      e.ngay.push({ date:day, vao:String(row[4] || ''), ra:String(row[6] || '') });
    });
  });
  var rows = thuTu.map(function(k){ return byEmp[k]; })
                  .sort(function(a, b){ return a.ten < b.ten ? -1 : (a.ten > b.ten ? 1 : 0); });
  rows.forEach(function(e){ e.ngay.sort(function(a, b){ return a.date < b.date ? -1 : (a.date > b.date ? 1 : 0); }); });
  return { station:station, sheets:sheets, rows:rows };
}

/** Ảnh mẫu 3×4 dạng data URI. Không khai / đọc không được -> `{ok:false}` và trang tự dùng SVG. */
function getAnhMauThe(){
  try {
    /* Sheet TRƯỚC — đó là chỗ hai app cùng thấy. Script Property chỉ là đường lùi cho giá trị
       lưu từ bản trước (và nó chỉ tồn tại ở project quản trị). */
    var id = _caiDatDoc(AMT_KEY);
    if (!id) id = String(PropertiesService.getScriptProperties().getProperty(AMT_KEY) || '').trim();
    if (!id) return { ok:false };
    var cache = null;
    try { cache = CacheService.getScriptCache(); } catch(e){}
    if (cache){ var hit = cache.get(AMT_CACHE); if (hit) return { ok:true, anh:hit }; }
    var b = DriveApp.getFileById(id).getBlob();
    var mime = String(b.getContentType() || '');
    if (mime.indexOf('image/') !== 0) return { ok:false, error:'File không phải ảnh (' + mime + ').' };
    var uri = 'data:' + mime + ';base64,' + Utilities.base64Encode(b.getBytes());
    if (cache && uri.length < AMT_TOI_DA){ try { cache.put(AMT_CACHE, uri, 21600); } catch(e){} }
    return { ok:true, anh:uri };
  } catch (err) { return { ok:false, error:String(err) }; }
}
/** Admin dán LINK Drive (hoặc id) của ảnh mẫu. Đọc thử ngay để không lưu một link hỏng. */
function datAnhMauThe(pin, link){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin đặt được ảnh mẫu.' };
  var s = String(link || '').trim();
  if (!s){                                   // để trống = gỡ ảnh, quay về hình vẽ SVG
    _caiDatGhi(AMT_KEY, '');
    PropertiesService.getScriptProperties().deleteProperty(AMT_KEY);   // dọn nốt bản cũ
    try { CacheService.getScriptCache().remove(AMT_CACHE); } catch(e){}
    return { ok:true, go:true };
  }
  /* Nhận cả link `/file/d/<id>/view`, link `?id=<id>`, hoặc dán thẳng id. */
  var m = /\/d\/([A-Za-z0-9_-]{10,})/.exec(s) || /[?&]id=([A-Za-z0-9_-]{10,})/.exec(s);
  var id = m ? m[1] : s;
  if (!/^[A-Za-z0-9_-]{10,}$/.test(id))
    return { ok:false, error:'Không nhận ra ID trong link. Dán link Google Drive của ảnh, hoặc dán thẳng ID.' };
  /* 🔴 ĐỌC THỬ NGAY. Lưu một id không đọc được thì trang chấm công vẫn hiện hình vẽ như cũ —
     anh tưởng đã gắn ảnh, mà nhân viên chẳng thấy gì khác. */
  var b;
  try { b = DriveApp.getFileById(id).getBlob(); }
  catch (e) {
    return { ok:false, error:'App không mở được file này. Chia sẻ file cho tài khoản chạy app '
           + '(hoặc đặt "Bất kỳ ai có đường liên kết"), rồi dán lại.' };
  }
  var mime = String(b.getContentType() || '');
  if (mime.indexOf('image/') !== 0) return { ok:false, error:'File không phải ảnh (' + mime + ').' };
  var co = b.getBytes().length;
  /* 🔴 GHI VÀO SHEET, không ghi Script Property: trang chấm công online là project khác, nó
     không bao giờ thấy Script Property của app này. Xoá luôn khoá cũ cho khỏi hai nguồn. */
  _caiDatGhi(AMT_KEY, id);
  try { PropertiesService.getScriptProperties().deleteProperty(AMT_KEY); } catch(e){}
  try { CacheService.getScriptCache().remove(AMT_CACHE); } catch(e){}
  return { ok:true, id:id, mime:mime, kb:Math.round(co / 1024),
           nang: co > AMT_TOI_DA,
           ghiChu: co > AMT_TOI_DA
             ? 'Ảnh ' + Math.round(co / 1024) + 'KB — hơi nặng cho trang chấm công (nên dưới 90KB). '
               + 'Vẫn chạy được, chỉ là mỗi lần mở trang phải tải lại vì không cache được.'
             : '' };
}
/** Ảnh mẫu đang khai (cho màn hình quản trị xem lại). */
function getAnhMauTheInfo(pin){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin.' };
  var id = _caiDatDoc(AMT_KEY)
        || String(PropertiesService.getScriptProperties().getProperty(AMT_KEY) || '').trim();
  var r = getAnhMauThe();
  return { ok:true, id:id, coAnh:!!(r && r.ok), anh:(r && r.ok) ? r.anh : '',
           loi:(r && !r.ok) ? (r.error || '') : '' };
}

/* ---------------------------------------------------------------------------
 *  NHÂN VIÊN LÀM NHIỀU CƠ SỞ  (02/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"một số nhân viên làm việc nhiều cửa hàng, mỗi nhân viên 1 ID cố định, nên khi
 *  app quét 2 sheet cùng 1 nhân viên thì web app sẽ có 2 tuỳ chọn cho NV đó để chấm công"*.
 *
 *  Nguồn sự thật là CHÍNH các sheet cơ sở: có dòng mang mã NV đó = người đó làm ở đó. Không
 *  thêm cột cấu hình nào — cột `Cơ sở chấm công online` trong PhanQuyen giữ nguyên vai trò
 *  "cơ sở MẶC ĐỊNH", không còn là cơ sở DUY NHẤT.
 *
 *  ⚠️ Danh sách này vừa là tuỳ chọn hiển thị, vừa là DANH SÁCH CHO PHÉP GHI. `chamCongOnline`
 *     bắt buộc đối chiếu — nếu không thì bất kỳ tài khoản NHÂN VIÊN nào cũng gửi lên một tên
 *     cơ sở tuỳ ý và ghi giờ vào sheet cửa hàng KHÁC.
 * ------------------------------------------------------------------------ */

/* ===== TĂNG CƯỜNG: LÀM THÊM Ở CƠ SỞ KHÁC (06/08/2026) ====================================
 * Anh Thắng: *"nếu làm ở cơ sở số 2 thì anh sẽ cấu hình. Lúc này sẽ tạo 1 mã -TC trong sheet CS
 * mà nhân viên làm việc. Nếu nghỉ cơ sở đó thì sẽ được khoá lại và sang tháng sau không hiện nữa."*
 * Và: *"cố gắng đơn giản nhất để tránh sheet nặng"*.
 *
 * VÌ SAO PHẢI CÓ SHEET NÀY (chỉ một, cả chuỗi dùng chung, mỗi lượt tăng cường ĐÚNG MỘT DÒNG):
 * Khối tháng mới của `CS_` sinh ra RỖNG (`_csTaoKhoi` chỉ dựng hai nhãn), nên người nghỉ sẽ tự
 * biến mất khỏi tháng sau — trừ một chuyện: `_dsCoSoCuaNv` quét TOÀN BỘ sheet `CS_`, thấy hàng
 * `-TC` của tháng CŨ là vẫn bày cơ sở đó ra ô chọn, họ chấm được và hàng lại mọc lại.
 * Nên "khoá" cần một chỗ ghi. Ghi vào `CS_` thì đụng lưới ngày (cột C trở đi là giờ vào/ra theo
 * công thức `3 + ngày*5`) — hỏng bảng lương. Một sheet phẳng vài chục dòng là cách nhẹ nhất.
 *
 * ⚠️ KHOÁ KHÔNG XOÁ GÌ CẢ. Tháng đang chạy giữ nguyên hàng `-TC` và mọi giờ đã chấm — còn phải
 *    tính lương tháng đó. Khoá chỉ chặn cơ sở đó khỏi ô chọn kể từ sau `Đến ngày`.
 */
var SH_TANG_CUONG = 'TangCuong';
var TC_H = ['Mã NV', 'Họ tên', 'Cơ sở đến', 'Từ ngày', 'Đến ngày', 'Người khai', 'Tạo lúc'];

/** {`<mã>|<cơ sở>`: {tuNgay, denNgay, hang}} — đọc MỘT lần, không cache lâu (khoá phải ăn ngay). */
function _tcMap(){
  var m = {}, sh = _sheet(SH_TANG_CUONG);
  if (!sh) return m;
  var last = sh.getLastRow(); if (last < 2) return m;
  var v = sh.getRange(2, 1, last - 1, TC_H.length).getDisplayValues();
  for (var i = 0; i < v.length; i++){
    var ma = _chuanMa(v[i][0]), cs = String(v[i][2] || '').replace(/^CS_/, '').trim();
    if (!ma || !cs) continue;
    /* Khai TRÙNG (cùng người, cùng cơ sở, khai hai lần) thì lấy dòng CUỐI — dòng mới nhất là ý
       mới nhất của anh. Lấy dòng đầu là bấm "mở lại" xong vẫn thấy bị khoá. */
    m[ma + '|' + _khongDau(cs)] = { tuNgay:String(v[i][3] || '').trim(),
                                    denNgay:String(v[i][4] || '').trim(), hang:i + 2 };
  }
  return m;
}
/** Lượt tăng cường này còn hiệu lực vào `ngay` (yyyy-MM-dd) không? Chưa khai -> KHÔNG. */
function _tcConHieuLuc(map, maNV, coSo, ngay){
  var r = map[_chuanMa(maNV) + '|' + _khongDau(String(coSo || '').replace(/^CS_/, '').trim())];
  if (!r) return false;
  ngay = String(ngay || '').substring(0, 10);
  /* So bằng CHUỖI yyyy-MM-dd, không dựng Date: dựng Date là dính múi giờ, và ở đây lệch một ngày
     nghĩa là người ta mất một ngày công hoặc chấm được ở nơi đã nghỉ. */
  if (r.tuNgay  && ngay && ngay < r.tuNgay.substring(0, 10))  return false;
  if (r.denNgay && ngay && ngay > r.denNgay.substring(0, 10)) return false;
  return true;
}

/* ===== CỬA HÀNG GỐC — DỰNG BẢN ĐỒ MỘT LƯỢT CHO CẢ CHUỖI =================================
 * Anh Thắng: *"cần tại hàng dữ liệu gốc mình bổ sung cột (cửa hàng làm việc) để dò nhanh hơn
 * không"* — không cần, và đây là chỗ tối ưu đúng.
 * Dò từng người thì mỗi người quét ~20 sheet `NV_`; mở bảng 200 người là 4000 lượt đọc.
 * Dựng MỘT bản đồ `mã -> cơ sở gốc` cho cả chuỗi thì chỉ 20 lượt, dùng chung cho mọi người.
 * ⚠️ Người có tên ở NHIỀU sheet `NV_` (dữ liệu tổng khai trùng): lấy sheet ĐẦU TIÊN theo thứ tự
 *    tab, và trả kèm `trung` để màn hình nói ra — im lặng chọn bừa là gốc nhảy lung tung mỗi lần
 *    anh kéo thả tab. */
function _mapCuaHangGoc(){
  var cache = null, m = null;
  try { cache = CacheService.getScriptCache(); } catch(e){}
  if (cache){ var s = cache.get('goc_map'); if (s){ try { m = JSON.parse(s); } catch(e){} } }
  if (m) return m;
  m = { goc:{}, trung:{} };
  SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(sh){
    var nm = sh.getName(); if (nm.indexOf(NVCS_TIEN_TO) !== 0) return;
    var cs = nm.slice(NVCS_TIEN_TO.length);
    var d = null;
    /* Sheet nào tiêu đề hỏng thì BỎ QUA sheet đó, đừng để cả bản đồ chết theo — hôm nay
       `NV_JP_HCM` thiếu cột "Họ tên" và nó không được phép làm hỏng 19 cơ sở còn lại. */
    try { d = _nvcsDoc(cs); } catch(e){ return; }
    if (!d || !d.co || d.loi) return;
    d.list.forEach(function(x){
      var k = _chuanMa(x.ma); if (!k) return;
      if (m.goc[k]) { (m.trung[k] = m.trung[k] || [m.goc[k]]).push(cs); return; }
      m.goc[k] = cs;
    });
  });
  if (cache){ try { cache.put('goc_map', JSON.stringify(m), 300); } catch(e){} }
  return m;
}

/**
 * Dữ liệu cho hai ô mới trên form hồ sơ:
 *   · `goc`       — cửa hàng GỐC, CHỈ ĐỌC (mã nằm ở sheet `NV_` nào). Anh Thắng: *"không sửa
 *                   được, vì nó nằm trên sheet đó rồi"*.
 *   · `dsLamViec` — cửa hàng đang TÍCH CHỌN (gốc + tăng cường còn hiệu lực).
 *   · `dsTatCa`   — mọi cơ sở, để vẽ danh sách ô tích.
 * 🔴 Không tìm thấy gốc (mã chưa có trong sheet `NV_` nào) thì KHÔNG để trống im lặng — trả
 *    `gocTuHoSo` lấy từ ô "Cửa hàng" của hồ sơ và cờ `khongThayGoc` để màn hình nói rõ đang
 *    dùng giá trị cũ. Để trống là hồ sơ mất chỗ ghi `CS_` và mất luôn lớp gác quyền theo cửa hàng.
 */
function getCuaHangLamViec(pin, maNV){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền.' };
  maNV = String(maNV || '').trim();
  if (!maNV) return { ok:false, error:'Thiếu mã NV.' };
  var m = _mapCuaHangGoc(), k = _chuanMa(maNV);
  var goc = m.goc[k] || '';
  var gocTuHoSo = '';
  if (!goc){
    var f = _findRow(_nvSheet(), 1, maNV);
    if (f) gocTuHoSo = String(f.data[2] || '').replace(/^CS_/, '').trim();
  }
  var tatCa = [];
  SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(sh){
    var nm = sh.getName();
    if (nm.indexOf('CS_') === 0) tatCa.push(nm.slice(3));
  });
  tatCa.sort();
  /* CHT chỉ thấy cửa hàng MÌNH QUẢN trong ô tích. Bày cả chuỗi thì họ tích vào cửa hàng người
     khác, `themTangCuong` từ chối (đúng), nhưng người bấm chưng hửng không hiểu vì sao. Cắt
     ngay từ đây — máy chủ vẫn chặn lần nữa lúc ghi, đây chỉ là bớt một cú bấm vô ích. */
  if (!u.isAdmin) tatCa = tatCa.filter(function(cs){ return _canStation(u, cs); });
  return { ok:true, maNV:maNV, goc:goc, gocTuHoSo:gocTuHoSo, khongThayGoc:!goc,
           trungGoc:(m.trung[k] || []),
           dsLamViec:_dsCoSoCuaNv(maNV, goc || gocTuHoSo), dsTatCa:tatCa };
}

/** Danh sách lượt tăng cường — để màn hình bày ra ai đang làm thêm ở đâu, còn chạy hay đã khoá. */
/* ===========================================================================
 *  CƠ SỞ PHỤ — {mã NV -> [cơ sở đang tăng cường CÒN HIỆU LỰC]}   07/08/2026
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"thêm 1 cột cơ sở phụ, bạn nào làm cơ sở thứ 2… phân quyền cửa hàng trưởng là
 *  bạn sẽ quản lý được 2 cửa hàng luôn. Còn CS là nơi chấm công theo phân quyền nhân viên"*.
 *
 *  🔴 KHÔNG thêm cột mới vào `NhanVien`. Cơ chế "làm ở cơ sở thứ 2" ĐÃ CÓ từ 06/08 — sheet
 *     `TangCuong` + khối tích "Cửa hàng làm việc" trong hồ sơ. Thêm cột nữa là có BA nguồn cùng
 *     trả lời "ai làm ở cơ sở nào" (`NhanVien.Cửa hàng` · `NV_<cơ sở>` · cột mới), mà hai nguồn
 *     cãi nhau đã đủ gây ra đúng lỗi này rồi.
 *
 *  🔴 CÁI HỎNG THẬT nằm ở `getEmployees`: nó lọc quyền bằng ĐÚNG MỘT ô "Cửa hàng" trong hồ sơ và
 *     KHÔNG hề đọc `TangCuong`. Nên tích cho người ta làm ở cơ sở 2 xong, cửa hàng trưởng cơ sở 2
 *     vẫn KHÔNG nhận được hồ sơ đó về máy — bảng trống, mà không một dòng lỗi nào.
 *
 *  ⚠️ Dùng lại `_tcConHieuLuc`, KHÔNG viết phép "còn hạn" thứ hai: lệch nhau là người đã nghỉ
 *     cơ sở phụ vẫn hiện ở bảng bên đó (hoặc ngược lại, đang làm mà biến mất).
 *  ⚠️ Nhớ trong MỘT lượt chạy — `getEmployees` gọi cho từng hàng hồ sơ, đọc sheet mỗi hàng là
 *     treo. Khai/khoá tăng cường dọn bộ nhớ này ngay trong cùng lượt.
 * =========================================================================== */
var _CSPHU_LUOT = null;
function _coSoPhuMap(){
  if (_CSPHU_LUOT) return _CSPHU_LUOT;
  var out = {};
  try {
    var sh = _sheet(SH_TANG_CUONG);
    if (sh && sh.getLastRow() >= 2){
      var map = _tcMap(), hn = Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd');
      sh.getRange(2, 1, sh.getLastRow() - 1, TC_H.length).getDisplayValues().forEach(function(r){
        var ma = String(r[0] || '').trim(), cs = String(r[2] || '').replace(/^CS_/, '').trim();
        if (!ma || !cs) return;
        /* 🔴 07/08/2026 — anh Thắng bấm ✕ hai lần vẫn thấy chip: *"không xoá được"*.
           Đây là chỗ thứ hai của cùng một chuyện. `_tcConHieuLuc` cho lượt khoá HÔM NAY còn hiệu
           lực HẾT HÔM NAY (đúng — người ta còn làm nốt ca). Nhưng chip trên bảng đọc chính hàm
           đó, nên bấm ✕ xong tải lại vẫn thấy y nguyên: từ phía anh, nút đó **hỏng**.
           Tách hai chuyện ra:
             · CHIP / danh sách nhân viên — hễ đã có `Đến ngày` là THÔI, biến mất ngay khi bấm ✕;
             · QUYỀN CHẤM CÔNG hôm nay — `_dsCoSoCuaNv` không đi qua hàm này, nó soi thẳng hàng
               trong `CS_`, nên ca hôm nay vẫn chấm được và lương vẫn đủ.
           Nút bấm mà không thấy gì đổi là kiểu hỏng tệ nhất, kể cả khi bên dưới "đúng về lý". */
        if (String(r[4] || '').trim()) return;            // đã khoá (có Đến ngày) -> thôi hiện
        if (!_tcConHieuLuc(map, ma, cs, hn)) return;      // đã hết hạn -> hết là cơ sở phụ
        var k = _chuanMa(ma);
        out[k] = out[k] || [];
        if (out[k].indexOf(cs) < 0) out[k].push(cs);
      });
    }
  } catch(e){}
  return (_CSPHU_LUOT = out);
}
/** Tách ô "Cơ sở phụ" trong hồ sơ: "TUTU_BT, POSH_HCM" -> ['TUTU_BT','POSH_HCM'].
 *  Nhận cả dấu phẩy, chấm phẩy, xuống dòng — anh gõ tay thì kiểu nào cũng có. */
function _coSoPhuTachO(v){
  return String(v == null ? '' : v).split(/[,;\n]+/)
    .map(function(x){ return String(x).replace(/^CS_/, '').trim(); })
    .filter(Boolean);
}
/**
 * Thêm / bớt MỘT cơ sở trong ô "Cơ sở phụ" của hồ sơ trong `NhanVien`.
 *
 * Anh Thắng 07/08/2026 (ảnh chụp hàng 197 · MNNV2KVC0166): bấm ＋ trên bảng thì chip `TUTU_ESTELLA`
 * hiện ngay, nhưng **cột `Cơ sở phụ` trong Sheet vẫn trống** — *"Cơ sở phụ bổ sung cả vào sheet"*.
 * Đúng: trước đây chip đó chỉ đọc từ sheet `TangCuong`, ô trong hồ sơ không ai ghi. Nhìn vào Sheet
 * là thấy sai sự thật, mà Sheet mới là chỗ anh soát.
 *
 * 🔴 GHI THÊM, KHÔNG GHI ĐÈ. Ô này anh gõ tay nhiều cơ sở, đè lên là mất mấy cơ sở kia.
 * 🔴 Bỏ thì chỉ bỏ ĐÚNG một tên. So bằng `_khongDau` để "TuTu BT" và "TUTU_BT" là một.
 * ⚠️ Không ném lỗi ra ngoài: khai tăng cường đã ghi xong sheet `TangCuong` rồi, hỏng ở bước làm
 *    đẹp ô này thì báo, chứ không được huỷ việc chính.
 */
function _csPhuGhiVaoHoSo(maNV, coSo, them){
  try {
    coSo = String(coSo || '').replace(/^CS_/, '').trim();
    if (!maNV || !coSo) return '';
    var sh = _nvSheet(), f = _findRow(sh, 1, maNV);
    if (!f) return '';
    var cot = 8 + NV_EXTRA.indexOf('coSoPhu');
    var ds  = _coSoPhuTachO(f.data[cot - 1]);
    var co  = ds.some(function(x){ return _khongDau(x) === _khongDau(coSo); });
    if (them){
      if (co) return '';                       // đã có -> không ghi lại, không đẻ tên trùng
      ds.push(coSo);
    } else {
      if (!co) return '';
      ds = ds.filter(function(x){ return _khongDau(x) !== _khongDau(coSo); });
    }
    sh.getRange(f.row, cot).setValue(ds.join(', '));
    return them ? (' Đã ghi "' + coSo + '" vào cột Cơ sở phụ của hồ sơ.')
                : (' Đã bỏ "' + coSo + '" khỏi cột Cơ sở phụ của hồ sơ.');
  } catch (e) {
    return ' ⚠️ Chưa ghi được cột Cơ sở phụ trong hồ sơ (' + e + ') — sửa tay trong Sheet.';
  }
}
/**
 * MỌI cơ sở phụ của một người = ô "Cơ sở phụ" trong hồ sơ  ∪  lượt tăng cường còn hiệu lực.
 * 🔴 HỢP hai nguồn chứ không chọn một. Ô trong hồ sơ là anh gõ tay (nhanh, không có hạn ngày);
 *    `TangCuong` là đường bấm ＋ trên bảng (tạo luôn hàng `-TC` trong `CS_` và khoá được theo
 *    ngày). Chọn một nguồn là **mất người** ở nguồn kia — mà mất người ở đây nghĩa là cửa hàng
 *    trưởng bên đó không thấy họ, đúng cái lỗi ban đầu.
 */
function _coSoPhuCuaHoSo(oCot, maNV){
  var out = _coSoPhuTachO(oCot);
  (_coSoPhuMap()[_chuanMa(maNV)] || []).forEach(function(cs){ if (out.indexOf(cs) < 0) out.push(cs); });
  return out;
}

/** Người này có được quyền nhìn thấy vì CƠ SỞ PHỤ không? */
function _quaCoSoPhu(u, dsPhu){
  for (var i = 0; i < (dsPhu || []).length; i++) if (_canStation(u, dsPhu[i])) return true;
  return false;
}

function getTangCuong(pin){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền xem.', list:[] };
  var sh = _sheet(SH_TANG_CUONG);
  if (!sh || sh.getLastRow() < 2) return { ok:true, list:[] };
  var hn = Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd'), map = _tcMap(), out = [];
  sh.getRange(2, 1, sh.getLastRow() - 1, TC_H.length).getDisplayValues().forEach(function(r, i){
    var ma = String(r[0] || '').trim(), cs = String(r[2] || '').replace(/^CS_/, '').trim();
    if (!ma || !cs) return;
    out.push({ hang:i + 2, maNV:ma, ten:String(r[1] || ''), coSo:cs,
               tuNgay:String(r[3] || ''), denNgay:String(r[4] || ''),
               nguoiKhai:String(r[5] || ''), taoLuc:String(r[6] || ''),
               conChay: _tcConHieuLuc(map, ma, cs, hn) });
  });
  /* 🔴 Cùng lý do như `timHoSoDaoCot`: mở cho cửa hàng trưởng thì phải lọc, không thì họ đọc được
     danh sách tăng cường của CẢ CHUỖI. Lọc SAU khi dựng để `hang` vẫn là số hàng thật trong sheet
     (khoá tăng cường ghi theo số hàng này — lọc trước rồi đánh số lại là ghi nhầm người). */
  if (!_canQuanTriNV(u)) out = out.filter(function(x){ return _canStation(u, x.coSo); });
  return { ok:true, list:out };
}

/**
 * Khai một người làm thêm ở cơ sở khác.
 * Ghi MỘT dòng vào `TangCuong` + tạo hàng `<Tên> | <Mã>-TC` trong khối tháng HIỆN TẠI của
 * `CS_<cơ sở đến>` — để họ chấm được ngay, không phải đợi lượt chấm đầu tiên.
 * 🔴 KHÔNG đụng `NV_` của cơ sở nào. Anh Thắng: *"sheet NV anh đồng bộ từ dữ liệu tổng nên anh
 *    không có tự thêm được"* — `NV_` là công thức, ghi vào đó là phá công thức.
 */
function themTangCuong(pin, obj){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền khai tăng cường.' };
  obj = obj || {};
  var maNV = String(obj.maNV || '').trim();
  var coSo = String(obj.coSo || '').replace(/^CS_/, '').trim();
  var tuNgay = String(obj.tuNgay || '').substring(0, 10)
               || Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd');
  if (!maNV || !coSo) return { ok:false, error:'Thiếu mã NV hoặc cơ sở đến.' };
  if (_tachMaNhiemVu(maNV))
    return { ok:false, error:'"' + maNV + '" là mã hàng phụ, không phải mã nhân viên.' };
  if (!u.isAdmin && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cơ sở này.' };

  var lock = LockService.getScriptLock(); try { lock.waitLock(20000); } catch(e){}
  try {
    /* Tên lấy từ HỒ SƠ, không bắt anh gõ — gõ tay là sai chính tả, rồi hàng trong `CS_` mang một
       cái tên khác với mọi chỗ còn lại. */
    var f = _findRow(_nvSheet(), 1, maNV);
    if (!f) return { ok:false, error:'Không có hồ sơ nào mang mã "' + maNV + '".' };
    var ten = String(f.data[1] || '').trim();
    var goc = String(f.data[2] || '').replace(/^CS_/, '').trim();
    if (_khongDau(goc) === _khongDau(coSo))
      return { ok:false, error:'"' + coSo + '" đang là cơ sở GỐC của người này — không cần khai tăng cường.' };

    _CSPHU_LUOT = null;      // bộ nhớ 1 lượt chạy đã cũ — xem `_coSoPhuMap`
    var sh = _ensureSheet(SH_TANG_CUONG, TC_H);
    var map = _tcMap(), k = _chuanMa(maNV) + '|' + _khongDau(coSo);
    if (map[k] && !String(map[k].denNgay || '').trim())
      return { ok:false, error:ten + ' đang được khai tăng cường ở ' + coSo + ' rồi (từ '
             + (map[k].tuNgay || '?') + ').' };
    /* Đã khoá trước rồi khai lại: MỞ LẠI đúng dòng cũ, không đẻ dòng thứ hai. Hai dòng cùng người
       cùng cơ sở là sau này không ai biết dòng nào đang có hiệu lực. */
    if (map[k]) sh.getRange(map[k].hang, 4, 1, 2).setValues([[tuNgay, '']]);
    else sh.appendRow([maNV, ten, coSo, tuNgay, '', (u.name || u.role || ''), _now()]);

    // Tạo sẵn hàng `-TC` trong khối tháng hiện tại để chấm được ngay.
    var ghiChu = '', maTc = maNV + '-' + DUOI_TANG_CUONG, csh = _sheet('CS_' + coSo);
    if (!csh) ghiChu = '⚠️ Chưa có sheet CS_' + coSo + ' — hàng chấm công sẽ tự sinh khi có lượt chấm đầu tiên.';
    else {
      /* Lỗi ở bước này KHÔNG được huỷ việc khai: dòng `TangCuong` đã ghi rồi, và hàng trong `CS_`
         vẫn tự sinh lúc chấm công đầu tiên. Bọc try, báo ra, đừng văng. */
      try {
        var khoi = _csKhoiChoNgay(csh, tuNgay);
        var hang = findOrCreateEmpRow(csh, khoi, maTc, ten);
        ghiChu = 'Đã tạo hàng ' + maTc + ' trong CS_' + coSo + ' (hàng ' + hang + ').';
      } catch (e2) {
        ghiChu = '⚠️ Chưa tạo được hàng trong CS_' + coSo + ' (' + e2 + '). Hàng sẽ tự sinh lúc chấm công đầu tiên.';
      }
    }
    /* Ghi luôn vào ô "Cơ sở phụ" của hồ sơ — nhìn Sheet phải khớp với chip trên bảng. */
    ghiChu += _csPhuGhiVaoHoSo(maNV, coSo, true);
    _xoaCacheCoSo(maNV);
    return { ok:true, maNV:maNV, ten:ten, coSo:coSo, coSoGoc:goc, tuNgay:tuNgay,
             maTangCuong:maTc, ghiChu:ghiChu };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

/**
 * Khoá một lượt tăng cường (người đó nghỉ ở cơ sở đến).
 * 🔴 KHÔNG XOÁ GÌ. Hàng `-TC` và mọi giờ đã chấm giữ nguyên — còn phải tính lương tháng này.
 *    Chỉ đặt `Đến ngày`: từ sau ngày đó cơ sở này biến khỏi ô chọn chấm công, và khối tháng sau
 *    (vốn `_csTaoKhoi` sinh ra RỖNG) sẽ không có họ nữa. Đúng ý anh Thắng: *"nếu nghỉ cơ sở đó
 *    thì sẽ được khoá lại và sang tháng sau sẽ không hiện nữa"*.
 */
function khoaTangCuong(pin, obj){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền khoá tăng cường.' };
  obj = obj || {};
  var maNV = String(obj.maNV || '').trim();
  var coSo = String(obj.coSo || '').replace(/^CS_/, '').trim();
  var denNgay = String(obj.denNgay || '').substring(0, 10)
                || Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd');
  if (!maNV || !coSo) return { ok:false, error:'Thiếu mã NV hoặc cơ sở.' };
  if (!u.isAdmin && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cơ sở này.' };
  var lock = LockService.getScriptLock(); try { lock.waitLock(20000); } catch(e){}
  try {
    var sh = _sheet(SH_TANG_CUONG);
    var map = sh ? _tcMap() : {}, k = _chuanMa(maNV) + '|' + _khongDau(coSo);
    /* 🔴 07/08/2026 — anh Thắng: *"đã thêm cửa hàng giờ muốn xoá khỏi cửa hàng đó không được"*
       (ảnh `MNNV2MTD0027`, chip `VS_POSH+JP` · `VS_POSH` · `VS_JP`).
       Cơ sở phụ có HAI nguồn: lượt `TangCuong` (bấm ＋) và ô "Cơ sở phụ" GÕ TAY trong sheet. Nút ✕
       trước đây chỉ biết nguồn thứ nhất, nên cơ sở nào chỉ có trong Ô thì nó từ chối thẳng — bấm
       ✕ mãi không mất, mà câu báo lại nói về "lượt tăng cường", nghe chẳng liên quan gì.
       Nay: không có lượt tăng cường thì XOÁ KHỎI Ô là xong việc. Chỉ khi cả hai nguồn đều không
       có mới báo lỗi. */
    if (!map[k]){
      var _cpO = _csPhuGhiVaoHoSo(maNV, coSo, false);
      _CSPHU_LUOT = null; _xoaCacheCoSo(maNV);
      if (!_cpO) return { ok:false, error:'"' + coSo + '" không nằm trong cơ sở phụ của ' + maNV
                                 + ' (cả ô "Cơ sở phụ" lẫn lượt tăng cường đều không có).' };
      return { ok:true, maNV:maNV, coSo:coSo, chiTrongO:true,
               ghiChu:'Đã bỏ "' + coSo + '" khỏi cột Cơ sở phụ của hồ sơ. Giờ đã chấm và hàng '
                    + 'trong CS_' + coSo + ' GIỮ NGUYÊN để tính lương.' };
    }
    if (map[k].tuNgay && denNgay < map[k].tuNgay.substring(0, 10))
      return { ok:false, error:'Ngày kết thúc (' + denNgay + ') trước ngày bắt đầu ('
             + map[k].tuNgay + ') — kiểm lại.' };
    sh.getRange(map[k].hang, 5).setValue(denNgay);
    _CSPHU_LUOT = null;      // bộ nhớ 1 lượt chạy đã cũ — xem `_coSoPhuMap`
    /* 🔴 Khoá thì phải BỎ khỏi ô "Cơ sở phụ" luôn. Để nguyên là ô trong hồ sơ vẫn cho họ quyền —
       mà ô đó KHÔNG có hạn ngày, nên khoá xong người ta vẫn chấm được ở cơ sở đã nghỉ. */
    var _cp = _csPhuGhiVaoHoSo(maNV, coSo, false);
    _xoaCacheCoSo(maNV);
    return { ok:true, maNV:maNV, coSo:coSo, denNgay:denNgay,
             ghiChu:'Đã khoá. Giờ đã chấm và hàng trong CS_' + coSo + ' GIỮ NGUYÊN để tính lương; '
                  + 'từ sau ' + denNgay + ' cơ sở này không còn trong ô chọn chấm công.' + _cp };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

/** Xoá cache danh sách cơ sở của một người — khai / khoá xong phải ăn NGAY, không đợi 10 phút. */
function _xoaCacheCoSo(maNV){
  try { CacheService.getScriptCache().remove('nvcs2_' + String(maNV || '').trim()); } catch(e){}
}

/** Mọi cơ sở có dòng mang mã NV này. `macDinh` luôn được xếp đầu (kể cả chưa có dòng). */
function _dsCoSoCuaNv(maNV, macDinh){
  maNV = String(maNV == null ? '' : maNV).trim();
  macDinh = String(macDinh || '').replace(/^CS_/, '').trim();
  if (!maNV) return macDinh ? [macDinh] : [];

  var cache = null, khoa = 'nvcs2_' + maNV, ds = null, dsTc = [];
  try { cache = CacheService.getScriptCache(); } catch(e){}
  /* Cache CẢ HAI danh sách. Cache mỗi `ds` rồi tính `dsTc` lại là hai nguồn lệch nhau.
     ⚠️ Đổi khoá cache sang `nvcs2_`: khoá cũ đang giữ dữ liệu khuôn cũ (mảng phẳng), đọc lên
        `JSON.parse` ra mảng -> `.tran` undefined -> mất sạch cơ sở trong 10 phút sau khi deploy. */
  if (cache){ var s = cache.get(khoa);
              if (s){ try { var o = JSON.parse(s); ds = o.tran; dsTc = o.tc || []; } catch(e){ ds = null; } } }
  if (!ds){
    ds = [];
    SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(sh){
      var nm = sh.getName(); if (nm.indexOf('CS_') !== 0) return;
      var lr = sh.getLastRow(); if (lr < 2) return;
      var v = sh.getRange(1, 2, lr, 1).getDisplayValues();       // cột B = ID, mọi khối tháng
      for (var i = 0; i < v.length; i++){
        /* 🔴 06/08/2026 — PHẢI TÍNH CẢ HÀNG `-TC`. So `=== maNV` là khớp tuyệt đối, nên hàng
           `MNNV0007-TC` (tăng cường ở cơ sở 2) KHÔNG được nhận -> người đó mở chấm công online
           không thấy cơ sở 2 trong ô chọn, tức là không chấm công được ở nơi họ đang làm.
           ⚠️ CHỈ `-TC`, KHÔNG tính `-TG` / `-TT` / `-CD`. Ba cái đó là hàng VIỆC THÊM, chúng nằm
              CẠNH hàng chính ở CÙNG cơ sở — có chúng thì đã có hàng chính khớp rồi. Cơ sở chỉ có
              mỗi hàng `-TG` là dữ liệu lỗi (hàng phụ mồ côi), không phải bằng chứng người đó làm
              ở đó; nhận bừa là mở quyền GHI sang cửa hàng khác (`chamCongOnline` lấy đúng danh
              sách này làm danh sách cho phép ghi). Đã có phép kiểm canh: `test_tachhang.js`. */
        var _id = String(v[i][0]).trim(); if (!_id) continue;
        var _t = _tachMaNhiemVu(_id);
        var _laTc = !!(_t && _t.duoi === DUOI_TANG_CUONG);
        /* 🔴 Phân biệt "có hàng mã TRẦN" với "chỉ có hàng -TC". Cơ sở gốc thì luôn hiện; cơ sở
           tăng cường thì còn phải xét đã khoá chưa (xem `_tcConHieuLuc` phía trên). Gộp chung
           một danh sách là khoá xong vẫn chấm được ở nơi đã nghỉ. */
        if (_id === maNV){ ds.push(nm.replace(/^CS_/, '')); return; }
        if (_laTc && _t.ma === maNV){ dsTc.push(nm.replace(/^CS_/, '')); return; }
      }
    });
    ds.sort(); dsTc.sort();
    if (cache){ try { cache.put(khoa, JSON.stringify({ tran:ds, tc:dsTc }), 600); } catch(e){} }
  }
  /* Lọc KHOÁ ở đây, SAU cache — trạng thái khoá phải ăn ngay, không đợi cache hết 10 phút.
     Cơ sở tăng cường CHƯA KHAI trong `TangCuong` thì vẫn cho qua: đó là hàng `-TC` anh đã gõ tay
     từ trước, chặn nó là cắt chỗ chấm công của người đang đi làm. */
  var out = macDinh ? [macDinh] : [];
  /* 🔴 Ô "Cơ sở phụ" trong hồ sơ cũng là PHÂN QUYỀN CHẤM CÔNG — anh Thắng: *"ghi tất cả tên cơ sở
     làm vào cột đó để phân quyền"*. Không đọc ở đây thì anh gõ vào cột xong người ta mở chấm công
     online vẫn không thấy cơ sở đó, tức là không chấm được ở nơi họ đang làm. */
  try {
    var _f = _findRow(_nvSheet(), 1, maNV);
    if (_f) _coSoPhuTachO(_f.data[7 + NV_EXTRA.indexOf('coSoPhu')])
              .forEach(function(cs){ if (out.indexOf(cs) < 0) out.push(cs); });
  } catch(e){}
  /* 🔴 09/08/2026 — ĐÃ BỎ: "có DÒNG mã trần trong `CS_<cơ sở>`" KHÔNG còn là bằng chứng được
   *    chấm công ở đó. Anh Thắng (ảnh Trần Kiều Di `MNNV2MTD0026`): *"bạn làm cơ sở nào thì hiện
   *    cơ sở đó riêng, chấm công riêng"* — app bày ra 3 cơ sở để chọn trong khi bạn ấy chỉ làm
   *    POSH_HCM (lịch sử toàn POSH_HCM, hai cơ sở kia chưa một lượt chấm nào).
   *    Vì sao luật cũ sai: dòng trong `CS_` do ĐỒNG BỘ DANH SÁCH NHÂN VIÊN tự đẻ ra, không phải
   *    do ai quyết định cho người đó làm ở đấy. Suy quyền từ nó là app tự cấp quyền ghi sang cửa
   *    hàng khác — mà chính danh sách này là danh sách CHO PHÉP GHI của `chamCongOnline`.
   *    Nay quyền chỉ đến từ chỗ anh KHAI RÕ: cửa hàng gốc · ô "Cơ sở phụ" · lượt tăng cường.
   *    (`ds` vẫn được đọc và cache — `_dsCoSoCoDong` dùng nó để soát dữ liệu, xem hàm bên dưới.) */
  if (dsTc.length){
    var _map = _tcMap(), _hn = Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd');
    dsTc.forEach(function(cs){
      /* ⚠️ Trước đây hàng `-TC` CHƯA khai trong `TangCuong` vẫn cho qua. Nay phải có khai và còn
         hiệu lực: lượt ＋ chính thức ghi cả hai chỗ (sheet `TangCuong` + ô "Cơ sở phụ") nên không
         mất gì; chỉ những hàng `-TC` gõ tay mà không khai ở đâu cả mới bị cắt — và đó đúng là thứ
         không ai quyết định, giống hệt dòng mã trần vừa bỏ. */
      var _k = _chuanMa(maNV) + '|' + _khongDau(cs);
      if (!_map[_k]) return;                                        // chưa khai -> không cấp quyền
      if (!_tcConHieuLuc(_map, maNV, cs, _hn)) return;              // đã khoá  -> bỏ
      if (out.indexOf(cs) < 0) out.push(cs);
    });
  }
  return out;
}

/**
 * Cơ sở người này CÓ DÒNG trong sheet `CS_` — **KHÔNG phải** danh sách được phép chấm công.
 * Tách ra để soát dữ liệu: so với `_dsCoSoCuaNv` thì biết ai đang có dòng ở nơi không được khai.
 * ⚠️ ĐỪNG dùng hàm này để gác quyền ghi. Nó chính là luật vừa bị bỏ vì cấp quyền quá tay.
 */
function _dsCoSoCoDong(maNV){
  var khoa = 'nvcs2_' + String(maNV || '').trim();
  try { var s = CacheService.getScriptCache().get(khoa);
        if (s){ var o = JSON.parse(s); return (o.tran || []).slice(); } } catch(e){}
  _dsCoSoCuaNv(maNV, '');                      // nạp lại cache rồi đọc
  try { var s2 = CacheService.getScriptCache().get(khoa);
        if (s2){ var o2 = JSON.parse(s2); return (o2.tran || []).slice(); } } catch(e){}
  return [];
}

/** Giờ vào/ra HÔM NAY của NHIỀU mã tại một cơ sở -> {mã: {vao, ra}}. Đọc sheet MỘT lần.
 *  Người kiêm ≥2 việc có ≥2 hàng (mã trần + mã có đuôi) nên hay phải hỏi nhiều mã cùng lúc;
 *  gọi lẻ từng mã là mở lại sheet bấy nhiêu lần. */
function _ccHomNayNhieuMa(coSo, dsMa, ngay){
  var out = {}, can = {};
  (dsMa || []).forEach(function(m){ var s = String(m || '').trim(); if (s){ can[s] = 1; out[s] = { vao:'', ra:'' }; } });
  try {
    var sh = _sheet('CS_' + String(coSo).replace(/^CS_/, '')); if (!sh) return out;
    var col = _findDateBlockCol(sh, ngay); if (col <= 0) return out;
    var khoi = _csKhoiChoNgay(sh, ngay);
    if (khoi.r2 < khoi.r1) return out;
    var n = khoi.r2 - khoi.r1 + 1;
    var ids = sh.getRange(khoi.r1, 2, n, 1).getValues();
    var gio = sh.getRange(khoi.r1, col, n, 3).getValues();     // [vào][ảnh][ra]
    for (var i = 0; i < n; i++){
      var m = String(ids[i][0]).trim();
      if (!can[m]) continue;
      out[m] = { vao: toTimeStr(gio[i][0]), ra: toTimeStr(gio[i][2]) };
    }
  } catch(e){}
  return out;
}
/** Giờ vào / giờ ra HÔM NAY của một mã NV tại một cơ sở. Không có thì trả chuỗi rỗng. */
function _ccHomNayCua(coSo, maNV, ngay){
  var m = String(maNV == null ? '' : maNV).trim();
  return _ccHomNayNhieuMa(coSo, [m], ngay)[m] || { vao:'', ra:'' };
}

/**
 * Lịch sử chấm công của CHÍNH người đang đăng nhập — bảng để nhân viên tự theo dõi.
 *
 * ⚠️ Dùng lại `_docSheetData` chứ KHÔNG viết bản đọc sheet thứ hai: hai bản đọc lệch nhau
 *    là hai kiểu tính giờ trên cùng một sheet, mà lệch giờ là lệch TIỀN LƯƠNG.
 * ⚠️ Lọc theo ĐÚNG mã NV của họ, nên vai trò NHÂN VIÊN vẫn không thấy chấm công của ai khác
 *    (chỗ này không đi qua `_canStation` vì họ vốn không có quyền xem cơ sở — chỉ xem CỦA MÌNH).
 * Số giờ KHÔNG tính ở đây: phía web đã có `_workMin`/`_fmtHrs`, giữ MỘT công thức duy nhất.
 */
function getLichSuChamCongOnline(pin, soDong){
  var u = _requireAuth(pin);
  var d = _pqDong(u.pin);
  if (!d || !d.maNV) return { ok:true, bat:false, rows:[] };
  var macDinh = d.coSo || String(d.stations || '').split(',')[0].trim().replace(/^CS_/, '');
  var dsCoSo = _dsCoSoCuaNv(d.maNV, macDinh);
  if (!dsCoSo.length) return { ok:true, bat:false, rows:[] };

  // Người làm nhiều cửa hàng -> gộp công của TẤT CẢ cơ sở họ có mặt, mỗi dòng ghi rõ cơ sở nào.
  var ma = String(d.maNV).trim(), rows = [];
  for (var i = 0; i < dsCoSo.length; i++){
    var ds;
    try { ds = _docSheetData('CS_' + dsCoSo[i]); } catch(e){ continue; }
    (ds.rows || []).forEach(function(r){
      if (String(r[2]).trim() !== ma) return;
      rows.push({ ngay: String(r[1] || ''), vao: String(r[4] || ''), ra: String(r[6] || ''), coSo: dsCoSo[i] });
    });
  }
  rows.sort(function(a, b){
    if (a.ngay !== b.ngay) return a.ngay < b.ngay ? 1 : -1;       // mới nhất trên cùng
    return a.coSo < b.coSo ? -1 : (a.coSo > b.coSo ? 1 : 0);      // cùng ngày -> theo tên cơ sở
  });
  var n = Number(soDong) > 0 ? Number(soDong) : 60;
  var tong = rows.length;
  if (tong > n) rows = rows.slice(0, n);
  return { ok:true, bat:true, maNV:ma, coSo:macDinh, dsCoSo:dsCoSo, tong:tong, rows:rows };
}

/**
 * Chấm công online. `gps` = {lat, lng, acc} hoặc null.
 * Giờ lấy Ở ĐÂY (máy chủ), KHÔNG nhận giờ từ client.
 */
function chamCongOnline(pin, anhDataUrl, gps, coSoChon, nhiemVuChon){
  var u = _requireAuth(pin);
  var d = _pqDong(u.pin);
  if (!d || !d.maNV) return { ok:false, error:'Tài khoản này chưa bật chấm công online.' };
  var macDinh = d.coSo || String(d.stations || '').split(',')[0].trim().replace(/^CS_/, '');
  var coSo = macDinh;

  /* ⚠️ Người làm NHIỀU cửa hàng chọn cơ sở ngay trên máy, nên tên cơ sở đi LÊN từ client.
   *    BẮT BUỘC đối chiếu với danh sách cơ sở người đó thật sự có dòng — không kiểm thì bất kỳ
   *    tài khoản NHÂN VIÊN nào cũng gửi lên một tên tuỳ ý và ghi giờ vào sheet cửa hàng KHÁC. */
  var chon = String(coSoChon || '').replace(/^CS_/, '').trim();
  if (chon){
    var duoc = _dsCoSoCuaNv(d.maNV, macDinh);
    if (duoc.indexOf(chon) < 0){
      return { ok:false, error:'Bạn không có ở cơ sở "' + chon + '". Chọn lại cơ sở.' };
    }
    coSo = chon;
  }
  if (!coSo) return { ok:false, error:'Chưa khai "Cơ sở chấm công online" cho tài khoản này.' };

  /* ⚠️ Nhiệm vụ cũng đi LÊN từ client -> cũng phải đối chiếu, y như cơ sở. Chỉ nhận nhiệm vụ
   *    người đó ĐÃ ĐƯỢC KHAI trong hồ sơ; không thì ai cũng tự gán cho mình việc đơn giá cao. */
  var nvChon = '';
  /* Cần cả khi client không chọn: đây là thứ quyết định hàng nào là hàng 1.
     ⚠️ Nhiệm vụ CHỈ có nghĩa ở Nhóm Máy Tự Động. Cơ sở khác -> danh sách rỗng, nên không hỏi,
        không tách hàng, và nhiệm vụ gửi lên (nếu có) bị từ chối ngay bên dưới. */
  var _nvCua = _laMayTuDong(coSo) ? _nhiemVuTaiCoSo(coSo, d.maNV) : [];
  var _nvRaw = String(nhiemVuChon || '').trim();
  if (_nvRaw){
    var _c = _chuanNhiemVu(_nvRaw);
    if (_c === null || _nvCua.indexOf(_c) < 0)
      return { ok:false, error:'Bạn không được khai nhiệm vụ "' + _nvRaw + '". Nhờ Admin bổ sung ở hồ sơ.' };
    nvChon = _c;
  }

  var lock = LockService.getScriptLock();
  try { lock.waitLock(20000); } catch (le) {}
  try {
    var sh = _sheet('CS_' + coSo);
    if (!sh) return { ok:false, error:'Không thấy sheet CS_' + coSo + '.' };

    var now     = new Date();
    var dateStr = Utilities.formatDate(now, TZ, 'yyyy-MM-dd');
    var timeStr = Utilities.formatDate(now, TZ, 'HH:mm:ss');

    /* 🔴 BỘ PHẬN VĂN PHÒNG (04/08) — ca tối / ca đêm ghi sang HÀNG RIÊNG, và lượt lúc 00:00–06:00
       lùi về NGÀY HÔM TRƯỚC (nó thuộc ca đêm bắt đầu hôm qua). Không lùi ngày thì ca đêm bị chẻ
       đôi giữa hai khối ngày, mỗi bên thiếu một đầu -> không tính được công nào.
       `_vpDinhTuyen` trả null với mọi cơ sở KHÔNG phải Văn phòng -> đường cũ chạy y nguyên. */
    var vpR = null;
    try {
      vpR = _vpDinhTuyen(coSo, dateStr, timeStr);
      /* ⚠️ Điều kiện phải là ĐANG TRONG ÂN HẠN, KHÔNG phải `!vpR.dem`. Từ khi gộp tăng ca và ca
         đêm về cùng hàng 2 thì mọi lượt hàng 2 đều mang cờ `dem:true`, nên nhánh này KHÔNG BAO GIỜ
         chạy -> người tan làm 17:30 bị đẩy sang hàng 2 -> hàng 1 thiếu giờ ra -> MẤT TRỌN 1 CÔNG
         NGÀY. `test_cconline` bắt được. Chỉ đọc sheet khi thật trong ân hạn, đừng đọc thừa. */
      if (vpR && _vpTrongAnHan(_vpCfg(), timeStr)){
        var kbT = findOrCreateDateBlock(sh, dateStr);
        var hC  = findOrCreateEmpRow(sh, kbT.khoi, d.maNV, d.name || d.maNV);
        var coVao = String(sh.getRange(hC, kbT.col).getValue()     || '').trim() !== '';
        var coRa  = String(sh.getRange(hC, kbT.col + 2).getValue() || '').trim() !== '';
        if (coVao && !coRa) vpR = _vpDinhTuyen(coSo, dateStr, timeStr, true);
      }
    } catch (ve) { vpR = null; }
    if (vpR) dateStr = vpR.ngay;

    var kb     = findOrCreateDateBlock(sh, dateStr);
    /* Kiêm ≥2 việc -> việc thêm ghi vào HÀNG RIÊNG chèn ngay dưới hàng chính (anh Thắng chốt
       03/08). Kiêm ≤1 việc thì `_hangChoNhiemVu` trả đúng hàng chính, y hệt hành vi cũ. */
    var empRow = vpR
      ? _hangHauTo(sh, kb.khoi, d.maNV, d.name || d.maNV, vpR.duoi, vpR.nhan)
      : _hangChoNhiemVu(sh, kb.khoi, d.maNV, d.name || d.maNV, nvChon, _nvCua);

    // Ảnh: lưu ĐÚNG thư mục ảnh chấm công như đường máy Hikvision.
    var imageFormula = '', imgNote = 'no-img';
    var b64 = String(anhDataUrl || '');
    if (b64.indexOf('base64,') >= 0) b64 = b64.split('base64,')[1];
    if (b64 && b64.length > 100) {
      try {
        var d0 = new Date(dateStr);
        var thang = 'Tháng ' + ('0' + (d0.getMonth() + 1)).slice(-2) + '-' + d0.getFullYear();
        var root = DriveApp.getFolderById(ATT_FOLDER_ID);
        var f1 = root.getFoldersByName(coSo);  var fCoSo  = f1.hasNext() ? f1.next() : root.createFolder(coSo);
        var f2 = fCoSo.getFoldersByName(thang); var fThang = f2.hasNext() ? f2.next() : fCoSo.createFolder(thang);
        var blob = Utilities.newBlob(Utilities.base64Decode(b64), 'image/jpeg',
                     d.maNV + '_' + dateStr + '_' + timeStr.replace(/:/g, '-') + '_online.jpg');
        var file = fThang.createFile(blob);
        imgNote = 'ok:' + file.getId();
        imageFormula = '=IMAGE("https://drive.google.com/thumbnail?id=' + file.getId() + '&sz=w300")';
        try { file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW); } catch (se) {}
      } catch (de) {
        imgNote = 'ERR:' + de;
        try { PropertiesService.getScriptProperties().setProperty('last_img_err',
                _now() + ' | online | ' + coSo + ' | ' + de); } catch(_e){}
      }
    }

    /* Hàng CÔNG ĐÊM phải dùng hàm ghi riêng: `_ghiGioVaoRa` giữ cặp [SỚM NHẤT, MUỘN NHẤT] nên
       06:00 (sớm hơn 22:00) sẽ thành GIỜ VÀO -> ca đêm bị đảo thành 16 tiếng ban ngày.
       Ca tối vẫn dùng hàm cũ: nó nằm trọn trong một ngày nên quy tắc cũ đúng. */
    var kq = (vpR && vpR.dem)
      ? _ghiGioDem(sh, empRow, kb.col, timeStr, imageFormula, _vpCfg())
      : _ghiGioVaoRa(sh, empRow, kb.col, timeStr, imageFormula);

    // GPS: ghi làm GHI CHÚ trên đúng ô giờ vừa ghi. Không thêm cột -> không đụng bố cục sheet,
    // mà rê chuột vào ô là đọc được, và còn nguyên khi xuất/đối chiếu.
    var toaDo = '';
    if (gps && gps.lat != null && gps.lng != null) {
      var la = Number(gps.lat), ln = Number(gps.lng);
      if (isFinite(la) && isFinite(ln)) {
        toaDo = la.toFixed(6) + ',' + ln.toFixed(6);
        var ghi = '📍 Chấm công ONLINE ' + timeStr + '\n' + toaDo
                + (gps.acc ? ('  (±' + Math.round(Number(gps.acc)) + 'm)') : '')
                + '\nhttps://maps.google.com/?q=' + toaDo;
        var oGio = (kq.loai === 'ra') ? sh.getRange(empRow, kb.col + 2) : sh.getRange(empRow, kb.col);
        try { oGio.setNote(ghi); } catch(e){}
      }
    }
    if (!toaDo) {
      var oG2 = (kq.loai === 'ra') ? sh.getRange(empRow, kb.col + 2) : sh.getRange(empRow, kb.col);
      try { oG2.setNote('📍 Chấm công ONLINE ' + timeStr + '\n⚠️ KHÔNG lấy được vị trí GPS'); } catch(e){}
    }

    /* Nhiệm vụ KHÔNG còn ghi sang sheet phụ nữa: chính CÁI HÀNG vừa ghi giờ là bản ghi.
       Hai nguồn sự thật thì kiểu gì cũng có lúc lệch, mà lệch ở đây là lệch tiền lương. */
    var nvGhi = nvChon;

    _touchStation(coSo);
    return { ok:true, loai:kq.loai, ngay:dateStr, gio:timeStr, coSo:coSo, maNV:d.maNV,
             gps:toaDo, anh:imgNote, nhiemVu:nvGhi,
             ghiChu: (kq.loai === 'vao' ? 'Đã ghi GIỜ VÀO ' : (kq.loai === 'ra' ? 'Đã ghi GIỜ RA ' : 'Đã ghi lại (quẹt lộn thứ tự) '))
                   + timeStr + ' ngày ' + dateStr + ' — cơ sở ' + coSo
                   + (nvGhi ? (' · 🧰 ' + nvGhi) : '')
                   + (nvChon && !nvGhi ? ' · ⚠️ KHÔNG ghi được nhiệm vụ' : '')
                   + (toaDo ? (' · 📍 ' + toaDo) : ' · ⚠️ KHÔNG có GPS')
                   + (imgNote.indexOf('ERR') === 0 ? ' · ⚠️ ảnh KHÔNG lưu được' : '') };
  } catch (err) {
    return { ok:false, error:String(err) };
  } finally {
    lock.releaseLock();
  }
}

// Thêm/sửa 1 người. obj = {pin, name, role, stations(chuỗi phẩy), origPin(khi sửa+đổi PIN)}
function saveRole(pin, obj) {
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin mới sửa phân quyền' };
  if (!obj || !obj.pin || !obj.name) return { ok:false, error:'Thiếu PIN hoặc Họ tên' };
  var newPin = String(obj.pin).trim();
  var role = String(obj.role || ROLE.CHT).trim().toUpperCase();
  var stations = String(obj.stations || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean).join(', ');
  var origPin = obj.origPin ? String(obj.origPin).trim() : newPin;
  /* 🔴 Không cấp tài khoản chấm công cho HÀNG NHIỆM VỤ THÊM (`…-TG`). Nó là một hàng trong bảng
     công, không phải một con người — cấp PIN cho nó là người đó có hai tài khoản, chấm công hai
     nơi, và lương đếm hai lần. Anh Thắng: *"gộp nv này lại, tránh cấp nhầm sau này"*. */
  var _tMa = _tachMaNhiemVu(obj && obj.maNV);
  if (_tMa) return { ok:false, error:'"' + String(obj.maNV).trim() + '" là hàng nhiệm vụ thêm của '
                             + _tMa.ma + ', không phải nhân viên riêng. Dùng mã ' + _tMa.ma + '.' };
  var lock = LockService.getScriptLock(); try { lock.waitLock(15000); } catch (e) {}
  try {
    var sh = _pqNoiCot();
    var found = _findRow(sh, 1, origPin);
    var dupe = _findRow(sh, 1, newPin);
    if (dupe && (!found || dupe.row !== found.row)) return { ok:false, error:'PIN này đã có người dùng' };
    var row = _pqHangMoi({ pin:newPin, name:obj.name, role:role, stations:stations,
                           maNV:obj.maNV, coSoOnline:obj.coSoOnline });
    if (found) sh.getRange(found.row, 1, 1, PQ_H.length).setValues([row]);
    else sh.appendRow(row);
    _clearAuthCache(newPin); _clearAuthCache(origPin);   // đổi quyền có hiệu lực ngay (không chờ cache 60s)
    /* Cấp/đổi PIN đăng nhập trên web -> ghi luôn xuống sheet `NV_<cơ sở>` để bản sao lưu không
       lệch. Chỉ làm được khi biết người đó là AI (`maNV`) và ở cơ sở nào. */
    if (obj.maNV && obj.coSoOnline) _nvcsGhiPin(obj.coSoOnline, obj.maNV, newPin, null);
    /* 🔴 07/08/2026 — ghi luôn vào cột `PIN đăng nhập` của `NhanVien`. Dòng trên chỉ ghi được khi
       sheet `NV_<cơ sở>` còn tồn tại; anh sắp xoá hết mấy sheet đó, và lúc ấy bản sao PIN sẽ âm
       thầm ngừng cập nhật — nhìn sheet thấy số cũ mà đăng nhập lại là số mới. */
    if (obj.maNV) {
      try {
        var shNv = _nvSheet(), f = _findRow(shNv, 1, String(obj.maNV).trim());
        if (f) shNv.getRange(f.row, 8 + NV_EXTRA.indexOf('pinDangNhap')).setValue("'" + newPin);
      } catch (e2) {}
    }
    return { ok:true };
  } catch (err) { return { ok:false, error:String(err) }; } finally { lock.releaseLock(); }
}

/**
 * MỘT định nghĩa duy nhất cho một dòng của sheet PhanQuyen.
 * ⚠️ Thêm cột thì sửa ĐÚNG Ở ĐÂY. Trước đây danh sách ô này chép tay trong `saveRole`, thêm
 *    một chỗ ghi thứ hai (cấp PIN hàng loạt) là hai bản dễ lệch số cột — mà lệch số cột khi
 *    `setValues` là văng lỗi, hoặc tệ hơn: ghi giá trị lệch sang cột khác.
 */
function _pqHangMoi(obj){
  obj = obj || {};
  return [ String(obj.pin || '').trim(),
           String(obj.name || '').trim(),
           String(obj.role || ROLE.CHT).trim().toUpperCase(),
           String(obj.stations || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean).join(', '),
           String(obj.maNV || '').trim(),
           String(obj.coSoOnline || '').replace(/^CS_/, '').trim() ];
}

// PIN quá dễ đoán thì không cấp — dò mấy số này là việc đầu tiên ai cũng thử.
var PIN_CAM = ['000000','111111','123456','654321','888888','999999','012345','121212'];
/** PIN yếu: ngắn hơn 6 số, nằm trong danh sách cấm, hoặc chính là PIN mặc định trong mã nguồn. */
function _pinYeu(pin){
  var p = String(pin == null ? '' : pin).trim();
  if (!p) return false;
  if (p === ADMIN_PIN_MAC_DINH) return true;
  if (PIN_CAM.indexOf(p) >= 0) return true;
  if (/^\d+$/.test(p) && p.length < 6) return true;      // "0", "12", "2026"… dò vài giây là ra
  return false;
}

/** Một PIN 6 số chưa ai dùng. `da` = {PIN: true} của những PIN đang tồn tại. '' nếu không sinh được. */
function _pinChuaDung(da){
  for (var i = 0; i < 3000; i++){
    var p = String(Math.floor(Math.random() * 1000000));
    while (p.length < 6) p = '0' + p;
    if (da[p]) continue;
    if (PIN_CAM.indexOf(p) >= 0) continue;
    if (p === ADMIN_PIN_MAC_DINH) continue;
    return p;
  }
  return '';
}

/**
 * CẤP PIN HÀNG LOẠT cho nhiều nhân viên của một cơ sở.
 * Anh Thắng: *"nhiều nhân viên rất khó gõ pin"* — nên máy tự sinh, không gõ tay cái nào.
 *
 * ⚠️ Tên người lấy TỪ SHEET cơ sở, KHÔNG tin tên client gửi lên; và mã nào không có trong sheet
 *    đó thì BỎ QUA. Nếu không, một mã gõ nhầm là tạo tài khoản chấm công cho người khác.
 * ⚠️ Đã có tài khoản thì BỎ QUA, không cấp PIN thứ hai cho cùng một người.
 * ⚠️ Cả lượt nằm trong MỘT khoá: sinh PIN phải nhìn thấy toàn bộ PIN đang có + PIN vừa sinh
 *    trong chính lượt này, nếu không hai người có thể trúng cùng một PIN.
 */
function capPinHangLoat(pin, coSo, dsMa){
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin cấp được PIN.', capMoi:[], boQua:[] };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  if (!coSo) return { ok:false, error:'Thiếu cơ sở.', capMoi:[], boQua:[] };

  var xin = [];
  (dsMa || []).forEach(function(m){
    var k = String(m == null ? '' : m).trim();
    if (k && xin.indexOf(k) < 0) xin.push(k);
  });
  if (!xin.length) return { ok:false, error:'Chưa chọn nhân viên nào.', capMoi:[], boQua:[] };

  var lock = LockService.getScriptLock(); try { lock.waitLock(30000); } catch(e){}
  try {
    var tenTheoMa = {};
    var trong = dsNvTuSheetCoSo(pin, coSo);
    (trong.list || []).forEach(function(x){ tenTheoMa[x.ma] = x.ten; });

    var sh = _pqNoiCot(), last = sh.getLastRow(), daPin = {}, daMa = {};
    if (last >= 2){
      var v = sh.getRange(2, 1, last - 1, PQ_H.length).getValues();
      for (var i = 0; i < v.length; i++){
        var p0 = String(v[i][0] == null ? '' : v[i][0]).trim(); if (p0) daPin[p0] = true;
        var m0 = String(v[i][4] == null ? '' : v[i][4]).trim(); if (m0) daMa[m0] = true;
      }
    }

    var them = [], capMoi = [], boQua = [], thieuTen = [];
    xin.sort().forEach(function(ma){
      // 🔴 Chốt chặn cuối: mã hàng NHIỆM VỤ THÊM không phải một con người. Danh sách đã lọc rồi,
      //    nhưng cấp PIN là tạo tài khoản đăng nhập — không dựa vào màn hình lọc đúng.
      var _t = _tachMaNhiemVu(ma);
      if (_t) { boQua.push({ ma:ma, ly:'là hàng nhiệm vụ thêm của ' + _t.ma + ', không cấp PIN riêng' }); return; }
      if (!(ma in tenTheoMa)) { boQua.push({ ma:ma, ly:'không có trong sheet CS_' + coSo }); return; }
      if (daMa[ma])           { boQua.push({ ma:ma, ten:tenTheoMa[ma], ly:'đã có tài khoản' }); return; }
      var p = _pinChuaDung(daPin);
      if (!p)                 { boQua.push({ ma:ma, ten:tenTheoMa[ma], ly:'không sinh được PIN mới' }); return; }
      daPin[p] = true; daMa[ma] = true;
      /* 🔴 KHÔNG `|| ma`. Hàng nào trong sổ nhân sự bỏ trống ô tên thì trước đây app ghi thẳng MÃ
         vào cột `Họ tên`, rồi màn hình chào "Xin chào, MNNV2MTD0026" — anh Thắng bắt được 07/08.
         Thà để TRỐNG: `_pqTenHienThi` sẽ tra tên thật trong hồ sơ `NhanVien` lúc đăng nhập, còn
         ghi mã vào đó là biến một ô thiếu dữ liệu thành một ô SAI dữ liệu. */
      var _ten = String(tenTheoMa[ma] || '').trim();
      if (_chuanMa(_ten) === _chuanMa(ma)) _ten = '';        // sổ nhân sự cũng ghi mã vào ô tên
      if (!_ten) thieuTen.push(ma);
      them.push(_pqHangMoi({ pin:p, name:_ten, role: ROLE.NHAN_VIEN,
                             stations: coSo, maNV: ma, coSoOnline: coSo }));
      capMoi.push({ ma:ma, ten:_ten, pin:p });
    });

    if (them.length) sh.getRange(sh.getLastRow() + 1, 1, them.length, PQ_H.length).setValues(them);
    /* Báo ra ai chưa có tên: tài khoản vẫn cấp được (họ cần chấm công), nhưng phải nói để anh
       điền, không thì lặng lẽ đẻ thêm một dòng thiếu tên nữa. */
    return { ok:true, coSo:coSo, capMoi:capMoi, boQua:boQua, thieuTen:thieuTen };
  } catch(err){
    return { ok:false, error:String(err), capMoi:[], boQua:[] };
  } finally { lock.releaseLock(); }
}

function deleteRole(pin, targetPin) {
  var u = _requireAuth(pin);
  if (!u.isAdmin) return { ok:false, error:'Chỉ Admin mới xóa phân quyền' };
  targetPin = String(targetPin || '').trim();
  if (targetPin === String(u.pin)) return { ok:false, error:'Không thể tự xóa tài khoản đang đăng nhập' };
  var sh = _ensureSheet(SH_ROLE);
  var last = sh.getLastRow();
  var roles = [];
  if (last >= 2) { sh.getRange(2, 1, last - 1, 4).getValues().forEach(function(r){ if (String(r[0])) roles.push({ pin:String(r[0]), role:String(r[2] || '').toUpperCase() }); }); }
  var admins = roles.filter(function(r){ return r.role === ROLE.ADMIN; });
  var target = roles.filter(function(r){ return r.pin === targetPin; })[0];
  if (target && target.role === ROLE.ADMIN && admins.length <= 1) return { ok:false, error:'Phải còn ít nhất 1 Admin' };
  var found = _findRow(sh, 1, targetPin);
  if (found) sh.deleteRow(found.row);
  _clearAuthCache(targetPin);   // thu hồi quyền có hiệu lực ngay
  return { ok:true };
}


// ============================================================================
//  TIỆN ÍCH CHUNG + SETUP
// ============================================================================
function _json(o){ return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON); }
function _text(s){ return ContentService.createTextOutput(s).setMimeType(ContentService.MimeType.TEXT); }
function _sheet(name){ return SpreadsheetApp.getActiveSpreadsheet().getSheetByName(name); }
function _now(){ return Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd HH:mm:ss'); }

/* ===========================================================================
 *  BẢNG MÁY CHẤM CÔNG — nhận ra cửa hàng theo MÃ THIẾT BỊ, không theo tên máy tự khai
 * ---------------------------------------------------------------------------
 *  Vì sao cần: doPost trước đây tin tuyệt đối `data.stationName` rồi ghi vào
 *  "CS_" + tên đó, TẠO SHEET MỚI nếu chưa có. Nên:
 *    · máy chưa đặt tên khai 'CHUA_DAT_TEN' -> sinh sheet `CS_CHUA_DAT_TEN`
 *    · gõ sai chính tả tên cửa hàng ở portal -> sinh thêm 1 cửa hàng ma
 *  cả hai đều IM LẶNG, chấm công đi vào sheet rác mà không ai thấy.
 *
 *  Khoá nhận máy: SERIAL ĐẦU ĐỌC trước (đầu đọc gắn tường, ít thay -> thay bo
 *  ESP32 thì cắm điện là chạy, không phải khai lại), không có thì tới MAC bo.
 *
 *  ⚠️ NGUYÊN TẮC: server KHÔNG BAO GIỜ tự tạo cửa hàng mới từ lời khai của máy.
 *     Tạo cửa hàng là việc làm có chủ ý trong web app.
 * =========================================================================== */
var SH_MAY    = 'MayChamCong';
/* 🔴 07/08/2026 — thêm cột 'SIM'. Anh Thắng: *"bổ sung cột sim (để lưu lại sim đang gắn trên
   thiết bị) nếu tự dò seri thì quá tốt, không thì nhập tay"*. Tự dò được: firmware ≥ 2026-08-07f
   đọc ICCID qua `AT+CCID` và gửi lên heartbeat. Ô này vẫn cho SỬA TAY (máy WiFi không có module
   4G nên không dò được).
   ⚠️ Thêm vào CUỐI mảng. Chèn vào giữa là mọi chỉ số cột cứng bên dưới lệch hết. */
var MAY_H     = ['Serial đầu đọc','MAC bo ESP32','Cửa hàng','Model đầu đọc','Tên máy tự khai','Lần cuối thấy','Ghi chú','SIM'];
var SH_CHOGAN = 'ChamCongChoGan';
var CHOGAN_H  = ['Nhận lúc','Serial đầu đọc','MAC bo ESP32','Tên máy tự khai','Mã NV','Họ tên','Thời điểm','Có ảnh','Đã chuyển'];

function _chuanMa(v){ return String(v == null ? '' : v).trim().toLowerCase(); }
/**
 * Tạo sheet cửa hàng `CS_<tên>` đúng khuôn (A1:B2 gộp, đóng băng 2 hàng 2 cột).
 * MỘT định nghĩa duy nhất, dùng cho cả doPost và lúc gán máy vào cửa hàng mới —
 * trước đây khuôn này chỉ nằm trong doPost nên không có cách nào mở cửa hàng mới
 * ngoài việc để một cái máy tự khai tên.
 */
function _taoSheetCuaHang(station){
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) throw new Error('Thiếu tên cửa hàng.');
  var ss = SpreadsheetApp.getActiveSpreadsheet(), ten = 'CS_' + station;
  var sh = ss.getSheetByName(ten);
  if (sh) return sh;
  sh = ss.insertSheet(ten);
  sh.getRange('A1:A2').merge().setValue('Họ và Tên');
  sh.getRange('B1:B2').merge().setValue('ID');
  sh.getRange('A1:B2').setBackground('#0f172a').setFontColor('#38bdf8')
    .setFontWeight('bold').setVerticalAlignment('middle');
  sh.setFrozenRows(2);
  sh.setFrozenColumns(2);
  sh.setColumnWidth(1, 180);
  sh.setColumnWidth(2, 70);
  return sh;
}
function _mayRows(){
  var sh = _ensureSheet(SH_MAY), lr = sh.getLastRow();
  return { sh: sh, rows: (lr < 2) ? [] : sh.getRange(2, 1, lr - 1, MAY_H.length).getValues() };
}
/** Tìm dòng máy theo serial (ưu tiên) rồi MAC. Trả {row, rec, theo} hoặc null. */
function _timMay(serial, mac){
  var R = _mayRows(), s = _chuanMa(serial), m = _chuanMa(mac), i;
  if (s) for (i = 0; i < R.rows.length; i++) if (_chuanMa(R.rows[i][0]) === s) return { row: i + 2, rec: R.rows[i], theo: 'serial' };
  if (m) for (i = 0; i < R.rows.length; i++) if (_chuanMa(R.rows[i][1]) === m) return { row: i + 2, rec: R.rows[i], theo: 'mac' };
  return null;
}
/** Máy lạ -> tự ghi 1 dòng CHỜ GÁN (cửa hàng để trống) để hiện lên web app. Có rồi thì cập nhật dấu vết. */
/* ===========================================================================
 *  MÁY VỪA GỌI TỚI MÁY CHỦ LÚC NÀO — ghi theo MAC, không theo tên cửa hàng
 * ---------------------------------------------------------------------------
 *  🔴 07/08/2026, anh Thắng: *"máy đã online, chỉ là không kết nối máy chấm công, nhưng vẫn
 *  báo off"*.
 *
 *  Web quyết định online CHỈ bằng heartbeat trên Firebase (`/hb/<tên cửa hàng>`). Hai chỗ hụt:
 *    · máy MỚI chưa khai xong cấu hình thì **không ghi được Firebase** -> không có nhịp nào,
 *      dù nó vẫn gọi Apps Script đều đặn (chính cột "lần cuối" đang nhảy);
 *    · máy CHƯA GÁN thì nhịp rơi vào tên dùng chung "CHUA_DAT_TEN".
 *  Trong khi đó máy **đang nói chuyện trực tiếp với máy chủ này** — bằng chứng chắc hơn cả
 *  Firebase, mà lại vứt đi không dùng.
 *
 *  Nay mỗi lượt máy gọi tới thì ghi một mốc theo MAC vào CacheService. Không đụng Sheet:
 *  đây là ĐƯỜNG NÓNG (mỗi lượt chấm công đều qua), và ô "Lần cuối" trong Sheet cố tình chỉ ghi
 *  30 phút/lần nên KHÔNG dùng để kết luận online được — trễ tới nửa tiếng.
 * =========================================================================== */
var MAY_LH_TTL = 6 * 3600;
function _mayChamLienLac(mac){
  try {
    var k = _macKey(mac); if (!k) return;
    var c = CacheService.getScriptCache(); if (!c) return;
    c.put('maylh_' + k, String(Date.now()), MAY_LH_TTL);
  } catch (e) {}                       // hỏng chỗ đếm TUYỆT ĐỐI không được làm rơi lượt chấm công
}
function _ghiNhanMay(serial, mac, tuKhai, model){
  _mayChamLienLac(mac);                // ghi TRƯỚC mọi nhánh return bên dưới
  var m = _timMay(serial, mac), sh = _ensureSheet(SH_MAY);
  if (!m){
    sh.appendRow([String(serial||''), String(mac||''), '', String(model||''), String(tuKhai||''), _now(), 'máy mới — CHƯA GÁN cửa hàng']);
    return null;
  }
  // Đã có dòng: chỉ đụng vào ô dấu vết, KHÔNG ghi đè cửa hàng anh đã gán.
  // ⚠️ Hàm này nằm trên ĐƯỜNG NÓNG (mỗi lượt chấm công đều gọi) nên KHÔNG ghi sheet mỗi lượt:
  //    dồn lại 30 phút/máy. Việc thiếu vẫn ghi ngay (bổ sung serial/MAC/model còn trống).
  var thieu = (!_chuanMa(m.rec[0]) && serial) || (!_chuanMa(m.rec[1]) && mac) || (!_chuanMa(m.rec[3]) && model);
  var khoa = 'may_' + m.row, cache = null;
  try { cache = CacheService.getScriptCache(); } catch (e) {}
  if (!thieu && cache && cache.get(khoa)) return m;                 // vừa ghi rồi -> thôi
  try {
    sh.getRange(m.row, 5).setValue(String(tuKhai||''));
    sh.getRange(m.row, 6).setValue(_now());
    if (!_chuanMa(m.rec[0]) && serial) sh.getRange(m.row, 1).setValue(String(serial));   // bổ sung serial cho dòng chỉ có MAC
    if (!_chuanMa(m.rec[1]) && mac)    sh.getRange(m.row, 2).setValue(String(mac));
    if (!_chuanMa(m.rec[3]) && model)  sh.getRange(m.row, 4).setValue(String(model));
    /* ⚠️ 01/08/2026 — ĐỔI PHẦN CỨNG phải BÁO, đừng lặng lẽ điền rồi thôi.
       Trước đây chỉ điền ô TRỐNG; ô đã có mà giá trị khác thì bỏ qua, không cập nhật, không
       báo. Hai ca thật và hậu quả khác nhau hẳn:
         · cùng serial, MAC khác  -> THAY BO ESP32. Vô hại: đầu đọc vẫn là đầu đọc đó, cập
           nhật MAC mới là xong. Đây đúng là lý do khoá chính chọn serial chứ không chọn MAC.
         · cùng MAC, serial khác  -> NGUY. Hoặc đổi đầu đọc, hoặc BO ĐÃ BỊ MANG SANG CỬA HÀNG
           KHÁC. Ca sau là chấm công của cửa hàng mới chảy vào sheet cửa hàng cũ -> sai người,
           sai lương. KHÔNG tự sửa: chỉ ghi dấu để anh Thắng quyết. */
    if (serial && _chuanMa(m.rec[0]) && _chuanMa(m.rec[0]) !== _chuanMa(serial)){
      sh.getRange(m.row, 7).setValue('⚠️ SERIAL ĐẦU ĐỌC ĐỔI: ' + m.rec[0] + ' -> ' + serial
        + ' lúc ' + _now() + ' — kiểm xem có phải bo bị mang sang cửa hàng khác. CHƯA tự sửa.');
      _fbGhiLoi('SERIAL_DOI', 'MAC ' + mac + ': ' + m.rec[0] + ' -> ' + serial);
    }
    /* ⚠️ CÙNG SERIAL, MAC KHÁC — CHỈ GHI DẤU, TUYỆT ĐỐI KHÔNG TỰ CẬP NHẬT MAC.
       Em đã định tự cập nhật cho "thay bo là cắm chạy", nhưng test cũ bắt được và test đúng:
       firmware NHỚ serial đầu đọc trong NVS (prefs "hikSn") và dùng lại khi không đọc được
       đầu đọc. Nên một bo mang sang CỬA HÀNG KHÁC, chưa với tới đầu đọc mới, sẽ khai SERIAL
       CŨ. Tự cập nhật MAC lúc đó = chấm công cửa hàng mới chảy vào sheet cửa hàng cũ, sai
       người sai lương mà không ai thấy. Hai ca "thay bo" và "mang bo đi" nhìn từ server
       GIỐNG HỆT NHAU, nên không được đoán — phải để anh Thắng quyết. */
    if (mac && _chuanMa(m.rec[1]) && _chuanMa(m.rec[1]) !== _chuanMa(mac)){
      sh.getRange(m.row, 7).setValue('⚠️ MAC BO ĐỔI: ' + m.rec[1] + ' -> ' + mac + ' lúc ' + _now()
        + ' — THAY BO (vô hại) hay BO MANG SANG CỬA HÀNG KHÁC (sai cửa hàng)? CHƯA tự sửa, anh kiểm rồi Gán lại.');
      _fbGhiLoi('MAC_DOI', 'serial ' + serial + ': ' + m.rec[1] + ' -> ' + mac);
    }
    if (cache) cache.put(khoa, '1', 1800);
  } catch (e) {}
  return m;
}
/**
 * Quyết định cửa hàng cho một lượt đẩy. Trả:
 *   {station, nguon:'serial'|'mac'|'tu-khai', lech:bool, choGan:bool}
 * choGan = true nghĩa là CHƯA biết cửa hàng nào -> gọi phải đem đi gửi tạm, KHÔNG tạo sheet mới.
 */
function _giaiMaTram(serial, mac, tuKhai, model){
  tuKhai = String(tuKhai || '').replace(/^CS_/, '').trim();
  var m = _ghiNhanMay(serial, mac, tuKhai, model);
  var ganCho = m ? String(m.rec[2] || '').replace(/^CS_/, '').trim() : '';
  if (ganCho) return { station: ganCho, nguon: m.theo, lech: !!(tuKhai && _chuanMa(tuKhai) !== _chuanMa(ganCho)), choGan: false };
  // Chưa gán: chỉ nhận lời khai của máy khi cửa hàng đó ĐÃ TỒN TẠI (không tạo mới bao giờ).
  if (tuKhai && _sheet('CS_' + tuKhai)) return { station: tuKhai, nguon: 'tu-khai', lech: false, choGan: false };
  return { station: '', nguon: 'tu-khai', lech: false, choGan: true };
}
/* ---- Hàm cho web app (gác quyền: chỉ Admin/Quản lý, vì đây là cấu hình hệ thống) ---- */
function _quanTri(pin){
  var u = _requireAuth(pin);
  if (!(u.isAdmin || u.role === ROLE.QUAN_LY)) throw new Error('Chỉ Admin / Quản lý được xem và gán máy chấm công.');
  return u;
}
/**
 * Đếm nhẹ: có máy nào MỚI chưa gán cơ sở không — để web app tự báo ra ngoài
 * (nhãn trên tab + băng thông báo ở trang chính), không phải mở tab mới thấy.
 * KHÔNG ném lỗi cho người không đủ quyền: hàm này bị gọi định kỳ ở nền, ném lỗi
 * là hiện thông báo lỗi vô cớ cho Cửa hàng trưởng.
 */
function demMayChuaGan(pin){
  var kq = { chuaGan:0, soChoGan:0, ds:[] };
  var u = loginByPin(pin);
  if (u.ok === false) return kq;
  if (!(u.isAdmin || u.role === ROLE.QUAN_LY)) return kq;
  _mayRows().rows.forEach(function(r, i){
    if (String(r[2] || '').trim()) return;
    kq.chuaGan++;
    if (kq.ds.length < 5) kq.ds.push({ row:i + 2, serial:String(r[0]||''), mac:String(r[1]||''), tuKhai:String(r[4]||'') });
  });
  var cg = _sheet(SH_CHOGAN);
  if (cg && cg.getLastRow() >= 2)
    cg.getRange(2, 9, cg.getLastRow() - 1, 1).getValues().forEach(function(r){ if (!String(r[0]||'').trim()) kq.soChoGan++; });
  return kq;
}

/** Danh sách máy + số lượt đang gửi tạm, để web app hiện bảng gán cửa hàng. */
/* ===========================================================================
 *  KẾT LUẬN "MÁY CÓ VỚI TỚI ĐẦU ĐỌC KHÔNG" — trả lời TỪ XA
 * ---------------------------------------------------------------------------
 *  🔴 03/08/2026, anh Thắng: *"có cách nào xác định được đầu đọc nối vào ESP đúng IP"*.
 *  Trước đây web chỉ có cột Serial: trống = không đọc được, mà KHÔNG biết vì sao — sai IP? sai
 *  mật khẩu ISAPI? đầu đọc chưa nối AP? Ba nguyên nhân, ba cách sửa khác nhau, nên vẫn phải ra
 *  cửa hàng mò. Nay firmware gửi kèm: IP nó đang gọi, có đọc được serial không, và DANH SÁCH IP
 *  đang nối vào AP của chính nó (ESP32 là router của đầu đọc nên nó biết).
 *  Hàm này chỉ ĐỌC và kết luận bằng lời — không sửa gì, không gọi ra ngoài.
 *
 *  ⚠️ Firmware cũ không gửi -> trả trangThai:'chua-ro'. KHÔNG được đoán thành "chưa nối AP":
 *     nói chắc một điều mà bằng chứng chưa đủ là đúng lỗi đã trả giá nhiều lần ở app này.
 * =========================================================================== */
/* Lượt ĐỌC SỔ chấm công gần nhất nói gì. Chỉ ĐỌC, chỉ diễn giải 3 con số firmware gửi lên.
   🔴 04/08/2026 — anh Thắng: *"chấm lúc máy đang mở thì không lên, rút điện gắn lại thì lên"*.
   Rút điện gắn lại = chạy lượt BÙ lúc khởi động. Nên chỗ im là LƯỢT TRỰC TIẾP.
   `soTong` = đầu đọc báo có bao nhiêu lượt trong khoảng · `soSo` = nó thật sự trả về bao nhiêu
   dòng. Hai số này tách được hai ca hoàn toàn khác nhau:
     tổng > 0 mà trả 0 dòng -> đầu đọc CÓ lượt nhưng KHÔNG chịu trả (đúng kiểu K1T320)
     tổng = 0              -> khoảng thời gian đang hỏi KHÔNG chứa lượt nào (mốc sai / lệch giờ) */
/* ===========================================================================
 *  MÚI GIỜ CỦA ĐẦU ĐỌC — sai là SAI LƯƠNG, mà sai im lặng
 * ---------------------------------------------------------------------------
 *  🔴 07/08/2026 — máy mới của anh Thắng in log: *"Bù dữ liệu bỏ lỡ từ
 *  2026-08-07 17:06:09+08:00"*. Đầu đọc Hikvision mới xuất xưởng mặc định GMT+08 (Trung Quốc).
 *  Lượt chấm công mang dấu thời gian của ĐẦU ĐỌC, nên để nguyên +08 là mọi lượt ghi SỚM HƠN giờ
 *  Việt Nam 1 tiếng. Không ai nhìn ra cho tới lúc tính lương cuối tháng.
 *  Đầu đọc vẫn trả HTTP 200, sheet vẫn có dữ liệu — không có gì đỏ để mà thấy. Phải nói ra.
 * =========================================================================== */
function _chanDoanMuiGio(h){
  if (!h || h.tzDoc === null || h.tzDoc === undefined)
    return { trangThai:'chua-ro', loi:'' };            // firmware cũ chưa gửi -> im, đừng đoán
  var tz = String(h.tzDoc || '');
  if (!tz) return { trangThai:'tot', tz:'', loi:'' };  // đầu đọc không kèm múi giờ -> bình thường
  if (tz === '+07:00') return { trangThai:'tot', tz:tz, loi:'' };
  return { trangThai:'lech', tz:tz, loi:
    '🔴 Đầu đọc đang để múi giờ ' + tz + ' chứ không phải +07:00. Lượt chấm công mang dấu thời '
    + 'gian của ĐẦU ĐỌC, nên mọi lượt đang bị ghi LỆCH GIỜ — sai giờ vào/ra là sai lương, mà '
    + 'không có gì báo đỏ cả. Vào giao diện đầu đọc (192.168.4.50) đặt lại múi giờ GMT+7, rồi '
    + 'bấm Tải lại từ máy cho những ngày đã lỡ.' };
}
function _chanDoanDocSo(h){
  if (!h || h.soTong === null || h.soTong === undefined)
    return { trangThai:'chua-ro', loi:'Firmware đang chạy chưa gửi kết quả đọc sổ (cần bản 2026-08-04a trở lên).' };
  /* 🔴 04/08 — SỐ THÔ phải luôn có mặt. Bản trước chỉ trả câu diễn giải, mà giao diện lại ẩn dòng
     này khi "tốt" -> im lặng ở đây mang HAI nghĩa khác nhau ("đọc sổ ổn" và "firmware chưa gửi"),
     nên một ảnh chụp không kết luận được gì. Đúng cái lỗi vẫn đi bắt: im lặng không phải thông tin. */
  var tu = String(h.soTu || ''), phut = (h.soPhut === null ? -1 : h.soPhut);
  var soTho = 'tổng=' + h.soTong + ' · trả về=' + (h.soSo === null ? '?' : h.soSo)
            + ' · chốt theo=' + (h.soChot || '?')
            + (tu ? (' · mốc=' + tu) : '')
            + ((phut >= 0) ? (' · ' + phut + ' phút trước') : '');
  var moc = tu ? (' · đang hỏi từ mốc ' + tu) : '';
  var lau = (phut >= 0) ? (' · lượt đọc gần nhất ' + phut + ' phút trước') : '';
  if (h.soTong < 0) return { trangThai:'chua-doc', tho:soTho, chot:(h.soChot||''),
    loi:'Máy chưa đọc sổ lần nào từ lúc khởi động.' };
  if (h.soTong > 0 && h.soSo === 0)
    return { trangThai:'co-ma-khong-tra', tong:h.soTong, so:0, tho:soTho, chot:(h.soChot||''), loi:
      'Đầu đọc báo CÓ ' + h.soTong + ' lượt trong khoảng đang hỏi nhưng TRẢ VỀ 0 dòng' + moc + lau
      + '. Đây là lỗi phía đầu đọc, không phải mất mạng — lượt bù (lật hết trang) vẫn lấy được, '
      + 'nên chấm công chỉ lên sau khi khởi động lại.' };
  if (h.soTong === 0)
    return { trangThai:'khoang-rong', tong:0, so:0, tho:soTho, chot:(h.soChot||''), loi:
      'Đầu đọc báo KHÔNG có lượt nào trong khoảng đang hỏi' + moc + lau
      + '. Nếu vừa có người quẹt thì mốc bắt đầu đang SAI (lệch giờ giữa ESP32 và đầu đọc).' };
  /* 🔴 "tot" ở đây CHỈ nghĩa là đọc sổ ra dòng — KHÔNG có nghĩa là chấm công đã lên sheet.
     Máy chủ nhận được bao nhiêu lượt thì xem khối "Tải lại"; hai chuyện khác nhau. */
  return { trangThai:'tot', tong:h.soTong, so:h.soSo, tho:soTho, chot:(h.soChot||''), loi:
    'Đọc sổ ra dòng bình thường: đầu đọc báo ' + h.soTong + ' lượt, trả về ' + h.soSo + ' dòng' + lau
    + '. (Đọc ĐƯỢC không có nghĩa là đã ĐẨY lên sheet — xem số lượt máy chủ nhận ở khối Tải lại.)' };
}

function _chanDoanDauDoc(h){
  if (!h) return { trangThai:'chua-ro', loi:'Máy chưa gửi heartbeat.' };
  if (!h.coCd) return { trangThai:'chua-ro',
    loi:'Firmware đang chạy chưa gửi chẩn đoán đầu đọc (cần bản 2026-08-03g trở lên).' };
  var ipMay = String(h.hikIp || '');
  var dsIp  = String(h.apIp || '').split(',').map(function(x){ return x.trim(); })
                .filter(function(x){ return x; });
  if (h.hikOk) return { trangThai:'tot', hikIp:ipMay, sn:h.hikSn, model:h.hikModel, http:h.hikHttp,
    loi:'Đầu đọc trả lời bình thường ở ' + ipMay + (h.hikModel ? (' · ' + h.hikModel) : '')
        + (h.hikSn ? '' : ' (đời máy này không trả mã serial — không sao, chấm công vẫn chạy)') + '.' };
  /* 🔴 MÃ HTTP nói chắc hơn mọi suy luận khác, nên xét TRƯỚC. Bản đầu chỉ có "đọc được / không",
     nên gặp ca FZ_LTVT (đầu đọc ở đúng .50, trả lời cổng 80, chỉ sai mật khẩu ISAPI) thì web
     bảo "chưa lấy được danh sách máy con để so" — nói KHÔNG BIẾT trong khi đã đủ dữ liệu. */
  if (h.hikHttp === 401 || h.hikHttp === 403)
    return { trangThai:'sai-mat-khau', hikIp:ipMay, apSo:h.apSo, apIp:dsIp, http:h.hikHttp,
      loi:'Tới được đầu đọc ở ' + ipMay + ' nhưng nó trả HTTP ' + h.hikHttp + ' = TỪ CHỐI ĐĂNG NHẬP. '
          + 'Sai tài khoản/mật khẩu Hikvision. Khai lại ô "Mật khẩu Hikvision" ở portal 192.168.4.1.' };
  if (h.apSo === 0)
    return { trangThai:'chua-noi-ap', hikIp:ipMay, apSo:0, apIp:[],
      loi:'KHÔNG có thiết bị nào nối vào WiFi "CHAM_CONG" của máy này — đầu đọc chưa nối AP '
          + '(sai mật khẩu WiFi, chưa khai WiFi trên đầu đọc, hoặc ngoài vùng).' };
  /* Có máy con nối vào nhưng ISAPI im. So IP là ra ngay: IP máy gọi có nằm trong danh sách
     máy con hay không — đó chính là câu hỏi "đúng IP chưa". */
  var dungIp = dsIp.indexOf(ipMay) >= 0;
  if (dsIp.length && !dungIp)
    return { trangThai:'sai-ip', hikIp:ipMay, apSo:h.apSo, apIp:dsIp,
      loi:'SAI IP: máy đang gọi ' + ipMay + ' nhưng thiết bị nối vào AP đang ở ' + dsIp.join(', ')
          + '. Đặt IP tĩnh của đầu đọc thành ' + ipMay + ' (gateway 192.168.4.1), '
          + 'hoặc khai IP đó vào ô "IP đầu đọc" ở portal 192.168.4.1.' };
  if (dungIp)
    return { trangThai:'sai-mat-khau', hikIp:ipMay, apSo:h.apSo, apIp:dsIp, http:h.hikHttp,
      loi:'Có thiết bị trả lời ở ĐÚNG ' + ipMay + ' nhưng ISAPI không ra dữ liệu'
          + ((h.hikHttp !== null && h.hikHttp !== undefined) ? (' (HTTP ' + h.hikHttp + ')') : '')
          + ' — gần như chắc là SAI MẬT KHẨU Hikvision. Khai lại ô "Mật khẩu Hikvision" ở '
          + 'portal 192.168.4.1.' };
  /* Chưa dò ra IP nào, nhưng mã HTTP ≤ 0 là bằng chứng đủ để nói KHÔNG VỚI TỚI — không cần
     danh sách máy con. Thà nói đúng một nửa còn hơn nói "chưa rõ" khi đã biết. */
  if (h.hikHttp !== null && h.hikHttp !== undefined && h.hikHttp <= 0)
    return { trangThai:'khong-toi', hikIp:ipMay, apSo:h.apSo, apIp:dsIp, http:h.hikHttp,
      loi:'KHÔNG với tới đầu đọc ở ' + ipMay + ' (không có ai trả lời). Đầu đọc phải nối WiFi '
          + '"CHAM_CONG" và đặt IP tĩnh ' + ipMay + ' (gateway 192.168.4.1) — hoặc khai IP thật '
          + 'của nó vào ô "IP đầu đọc" ở portal.' };
  return { trangThai:'chua-ro', hikIp:ipMay, apSo:h.apSo, apIp:dsIp,
    loi:'Chưa đọc được đầu đọc ở ' + ipMay + ', mà cũng chưa lấy được danh sách máy con để so.' };
}

function getDanhSachMay(pin){
  _quanTri(pin);
  // ⚠️ PHẢI dùng _hbTatCa(): máy 4G chỉ ghi heartbeat lên Firebase /hb, không gọi Apps Script.
  // Trước đây chỗ này chỉ đọc CacheService -> máy 4G đang chạy tốt vẫn hiện OFFLINE ở bảng này,
  // trong khi tab "Cập nhật FW" hiện online. Trả kèm agoSec để thấy SỐ PHÚT, đừng chỉ 🟢/🔴:
  // một con số ("thấy 2 phút trước") nói được nhiều hơn hẳn một cái nhãn đỏ.
  var R = _mayRows(), hb = _hbTatCa(), now = Date.now();
  /* ===========================================================================
   *  🔴 07/08/2026 — MÁY CHƯA GÁN THÌ KHÔNG BAO GIỜ HIỆN ONLINE
   * ---------------------------------------------------------------------------
   *  Anh Thắng: *"không gán cửa hàng thì nó không hiện online, gán vào nó mới hiện, nên thành ra
   *  bật 2 thiết bị thì chả biết thiết bị nào"*.
   *
   *  Bản cũ tra nhịp bằng `hb[st]` với `st` = cửa hàng ĐÃ GÁN. Máy chưa gán thì `st` rỗng ->
   *  `h = null` -> `online:false`, và hai dòng chẩn đoán trả về câu MẶC ĐỊNH:
   *      "Máy chưa gửi heartbeat" · "Firmware chưa gửi kết quả đọc sổ (cần bản 2026-08-04a)".
   *  Cả hai câu đó đều là NÓI SAI: máy có gửi, chỉ là gửi vào `/hb/CHUA_DAT_TEN` (tên nó tự khai)
   *  còn web thì đi tìm ở tên đã gán. Tệ nhất là câu thứ hai — nó đổ lỗi cho phiên bản firmware,
   *  đúng kiểu kết luận khi chưa có bằng chứng.
   *
   *  Chưa hết: MỌI máy chưa gán đều tự khai "CHUA_DAT_TEN" nên nhịp của chúng ĐÈ LÊN NHAU ở cùng
   *  một khoá. Bật 2 máy là không phân biệt được — đúng câu anh nói.
   *
   *  Chữa: firmware ≥ 2026-08-07b gửi kèm MAC, và ở đây tra theo MAC TRƯỚC. MAC là thứ duy nhất
   *  phân biệt được máy khi nó chưa có tên.
   *  ⚠️ Máy firmware CŨ không gửi MAC -> đành lùi về tra theo tên tự khai, NHƯNG nếu có từ 2 máy
   *     trở lên cùng khai một tên thì KHÔNG được nhận vơ nhịp đó cho máy nào cả: đánh dấu
   *     `hbChung` để giao diện nói thật là "chưa phân biệt được", thay vì đoán bừa một máy.
   * =========================================================================== */
  /* Đánh dấu theo CẢ HAI địa chỉ MAC của con chip: MAC bo (STA) và MAC sóng AP.
     Anh Thắng cầm số máy trạm hiện (MAC của AP) đi dò thì phải ra đúng dòng này. */
  var hbMac = {};                        // MAC (đã chuẩn hoá) -> bản ghi heartbeat
  Object.keys(hb).forEach(function(k){
    var o = hb[k] || {};
    var m = _chuanMa(o.mac);   if (m) hbMac[m] = o;
    var a = _chuanMa(o.macAp); if (a) hbMac[a] = o;
  });
  var demKhai = {};                      // đếm số máy CHƯA GÁN cùng khai một tên
  R.rows.forEach(function(r){
    if (String(r[2] || '').trim()) return;
    var t = _chuanMa(String(r[4] || '').replace(/^CS_/, ''));
    if (t) demKhai[t] = (demKhai[t] || 0) + 1;
  });
  /* Mốc máy gọi tới CHÍNH máy chủ này — đọc một lượt cho cả bảng. Xem ghi chú ở
     `_mayChamLienLac`: đây là bằng chứng chắc hơn heartbeat Firebase, và là thứ DUY NHẤT có
     với máy mới chưa khai được Firebase. */
  var lhMap = {};
  try {
    var _kk = R.rows.map(function(r){ return 'maylh_' + _macKey(r[1]); })
                    .filter(function(x){ return x !== 'maylh_'; });
    if (_kk.length) lhMap = CacheService.getScriptCache().getAll(_kk) || {};
  } catch (e) { lhMap = {}; }
  var may = R.rows.map(function(r, i){
    var st = String(r[2] || '').replace(/^CS_/, '').trim();
    var macCh = _chuanMa(r[1]);
    var khai  = String(r[4] || '').replace(/^CS_/, '').trim();
    var h = null, nguon = '', hbChung = false;
    /* Nhịp CÓ khai MAC thì nó thuộc về đúng máy đó — dòng khác tuyệt đối không được nhận vơ.
       Thiếu chốt này thì ở ca 2 máy chưa gán, máy KHÔNG khớp MAC vẫn rơi vào nhánh "tên tự khai"
       và bị đánh dấu dùng chung, trong khi thật ra đã phân biệt được rõ ràng. */
    var cuaAiKhac = function(x){ var mm = _chuanMa((x || {}).mac); return !!(mm && mm !== macCh); };
    if (macCh && hbMac[macCh])      { h = hbMac[macCh]; nguon = 'mac'; }        // chắc chắn nhất
    else if (st && hb[st] && !cuaAiKhac(hb[st])) { h = hb[st]; nguon = 'cua-hang'; }
    else if (!st && khai && hb[khai] && !cuaAiKhac(hb[khai])) {
      /* Lùi về tên tự khai — chỉ dám nhận khi CHỈ CÓ MỘT máy chưa gán khai tên đó. */
      hbChung = (demKhai[_chuanMa(khai)] || 0) > 1;
      h = hb[khai]; nguon = hbChung ? 'ten-chung' : 'ten-tu-khai';
    }
    /* ===========================================================================
     *  🔴 07/08/2026 — MÁY ĐANG GỌI TỚI MÁY CHỦ MÀ VẪN BÁO OFF
     * ---------------------------------------------------------------------------
     *  Anh Thắng: *"máy đã online, chỉ là không kết nối máy chấm công, nhưng vẫn báo off"*.
     *  Bản cũ chỉ tin heartbeat Firebase. Máy MỚI chưa khai xong cấu hình thì không ghi được
     *  Firebase, nên dù nó gọi Apps Script liên tục (cột "Lần cuối" đang nhảy) web vẫn báo off.
     *  Bằng chứng chắc nhất về "máy còn sống" chính là NÓ VỪA NÓI CHUYỆN VỚI MÁY CHỦ NÀY.
     *
     *  Hai nguồn, lấy cái MỚI HƠN:
     *    · `maylh_<MAC>` — mốc máy gọi Apps Script (theo MAC, xem `_mayChamLienLac`);
     *    · heartbeat Firebase — chỉ tính khi KHÔNG phải khoá dùng chung.
     *  ⚠️ Mốc gọi máy chủ theo MAC nên KHÔNG dính chuyện nhiều máy chung tên "CHUA_DAT_TEN":
     *     dùng được cả khi `hbChung`, và đó đúng là ca đang cần.
     * =========================================================================== */
    var fbSec = (h && !hbChung) ? Math.round((now - h.ms) / 1000) : null;
    var lhMs  = Number(lhMap['maylh_' + _macKey(r[1])] || 0);
    var lhSec = lhMs ? Math.round((now - lhMs) / 1000) : null;
    var agoSec = null, songNguon = '';
    if (lhSec != null) { agoSec = lhSec; songNguon = 'goi-may-chu'; }
    if (fbSec != null && (agoSec == null || fbSec < agoSec)) { agoSec = fbSec; songNguon = 'heartbeat'; }
    return { row:i + 2, serial:String(r[0]||''), mac:String(r[1]||''), station:st, model:String(r[3]||''),
             tuKhai:String(r[4]||''), lanCuoi:String(r[5]||''), ghiChu:String(r[6]||''),
             chuaGan: !st,
             online: (agoSec != null && agoSec <= MAY_ONLINE_SEC),
             agoSec: agoSec, songNguon: songNguon, hbNguon: nguon, hbChung: hbChung,
             /* MAC sóng AP — con số MÁY TRẠM hiện. Phải bày ra cạnh MAC bo, không thì anh cầm số
                bên này dò bên kia mãi không ra (lệch đúng 1 ở nhóm cuối). */
             macAp: (h && h.macAp) ? h.macAp : '',
             fw: (h && h.fw) ? h.fw : '',
             /* SIM: số máy TỰ DÒ đè lên ô nhập tay — máy đọc thẳng từ module 4G thì chắc hơn
                người chép tay 19 chữ số. Không dò được (máy WiFi / firmware cũ / chưa có sóng)
                thì hiện số đã nhập ở cột SIM của sheet (`r[7]`). */
             sim: ((h && h.sim) ? String(h.sim) : String(r[7] || '')),
             simTuDo: !!(h && h.sim),
             dauDoc: (hbChung ? { trangThai:'chua-ro',
               loi:'Máy này CHƯA GÁN cửa hàng nên nó tự khai tên "' + khai + '" — mà có '
                   + demKhai[_chuanMa(khai)] + ' máy chưa gán cùng khai tên đó, nên phần chẩn đoán '
                   + 'ĐẦU ĐỌC của chúng ghi đè lên nhau, không tách được của máy nào. '
                   + '(Trạng thái online thì vẫn đúng — cái đó lấy theo MAC.) '
                   + 'Gán cửa hàng cho từng máy, hoặc nạp firmware 2026-08-07b trở lên — bản đó '
                   + 'gửi kèm MAC nên tách được ngay.' } : _chanDoanDauDoc(h)),
             docSo: (hbChung ? { trangThai:'chua-ro',
               loi:'Chưa tách được nhịp của máy này (xem dòng trên).' } : _chanDoanDocSo(h)),
             muiGio: (hbChung ? { trangThai:'chua-ro', loi:'' } : _chanDoanMuiGio(h)),
             lech: !!(String(r[4]||'').trim() && st && _chuanMa(r[4]) !== _chuanMa(st)) };
  });
  var cg = _sheet(SH_CHOGAN), soChoGan = 0;
  if (cg && cg.getLastRow() >= 2)
    cg.getRange(2, 9, cg.getLastRow() - 1, 1).getValues().forEach(function(r){ if (!String(r[0]||'').trim()) soChoGan++; });
  return { may:may, cuaHang:_allStations().sort(), soChoGan:soChoGan };
}
/**
 * Gán 1 máy vào cửa hàng. `station` phải là cửa hàng ĐANG CÓ (sheet CS_…) — cố ý không cho
 * tạo cửa hàng mới từ đây, để không lặp lại đúng lỗi gõ sai tên ở portal.
 * Gán xong thì tự chuyển các lượt đã gửi tạm của máy đó về sheet cửa hàng.
 */
/* ===========================================================================
 *  BẢN ĐỒ "MÁY NÀO Ở CỬA HÀNG NÀO" TRÊN FIREBASE  (/may/<macKey>/station)
 * ---------------------------------------------------------------------------
 *  Vì sao phải có: máy CẦN biết tên cửa hàng, vì mọi đường Firebase đều mang tên đó
 *  (/queue/<trạm>, /hb/<trạm>, /roster/<trạm>, /photo/<trạm>). Trước đây máy hỏi bằng
 *  doGet ?action=whoami, nhưng qua 4G thì Apps Script trả 302 sang googleusercontent với
 *  URL ~532 ký tự — VƯỢT giới hạn dòng lệnh AT của module A7680C (đúng hạn chế đã ghi ở
 *  QuanLyNhanVien/Code.gs:31). Hậu quả thật ngoài hiện trường: máy giữ tên CHUA_DAT_TEN,
 *  web app xếp lệnh vào /queue/VP_KH-HCM, máy đọc /queue/CHUA_DAT_TEN -> mọi lệnh điều
 *  khiển từ xa (thêm/sửa/xoá nhân viên, tải lại, lấy ảnh) nằm im, KHÔNG báo lỗi.
 *  Đọc Firebase thì không có redirect nên qua được 4G — đúng lý do kiến trúc này đã dùng
 *  Firebase cho hàng đợi ngay từ đầu. Nay whoami đi cùng đường.
 *
 *  Khoá dùng MAC bo (bỏ dấu ':', chữ HOA) chứ không dùng serial đầu đọc: serial chỉ có khi
 *  đọc được đầu đọc, mà đầu đọc mất mạng là rỗng — đúng ca đang xảy ra. MAC luôn có.
 *  ⚠️ Firebase cấm . $ # [ ] / trong khoá; bỏ hết ký tự không phải chữ-số là an toàn tuyệt đối.
 * =========================================================================== */
function _macKey(mac){
  var k = String(mac || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  return k.length ? k : '';
}
/** Ghi /may/<macKey> = {station, at}. Trả true nếu ghi được. Không ném lỗi ra ngoài. */
function _fbCongBoMay(mac, station){
  var k = _macKey(mac);
  if (!k) return false;
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return false;
  try { return _fbPut('/may/' + k, { station: station, at: _now() }); }
  catch (e) { _fbGhiLoi('CONGBO_MAY_LOI', k + ' -> ' + e); return false; }
}
/**
 * Công bố có DỒN 1 giờ/máy — dùng trên đường nóng doPost.
 * Không dùng LockService: ghi trùng cũng chỉ là ghi lại đúng giá trị đó, vô hại.
 */
function _fbCongBoMayThrottle(mac, station){
  var k = _macKey(mac);
  if (!k) return false;
  var cache = null;
  try { cache = CacheService.getScriptCache(); } catch (e) {}
  var khoa = 'fbmay_' + k + '_' + station;      // đổi cửa hàng -> khoá khác -> công bố lại NGAY
  if (cache && cache.get(khoa)) return false;
  var ok = _fbCongBoMay(mac, station);
  if (ok && cache) { try { cache.put(khoa, '1', 3600); } catch (e) {} }
  return ok;
}
/** Xoá bản đồ của 1 máy (dùng khi gỡ máy khỏi cửa hàng). */
function _fbBoMay(mac){
  var k = _macKey(mac);
  if (!k) return false;
  try { return _fbDelete('/may/' + k); } catch (e) { return false; }
}
function ganMayVaoCuaHang(pin, row, station, taoMoi){
  var u = _quanTri(pin);
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Chưa chọn cửa hàng.' };
  if (!_sheet('CS_' + station)) {
    // MỞ CỬA HÀNG MỚI: chỉ Admin, và phải nói rõ ý muốn tạo (taoMoi=true) — cố ý làm nặng tay,
    // vì đây đúng chỗ trước kia sinh ra cửa hàng ma: máy gõ sai tên là có thêm 1 cửa hàng.
    if (!taoMoi) return { ok:false, chuaCo:true,
      error:'Chưa có cửa hàng "' + station + '". Chọn cửa hàng đang có, hoặc xác nhận mở cửa hàng MỚI.' };
    if (!u.isAdmin) return { ok:false, error:'Chỉ Admin được mở cửa hàng mới.' };
    if (!/^[A-Za-z0-9_]{2,30}$/.test(station)) return { ok:false,
      error:'Tên cửa hàng chỉ dùng chữ không dấu, số và _ (2–30 ký tự). VD: FZ_LTVT' };
    _taoSheetCuaHang(station);
  }
  var sh = _ensureSheet(SH_MAY);
  row = Number(row) || 0;
  if (row < 2 || row > sh.getLastRow()) return { ok:false, error:'Không tìm thấy dòng máy.' };
  var rec = sh.getRange(row, 1, 1, MAY_H.length).getValues()[0];
  sh.getRange(row, 3).setValue(station);
  sh.getRange(row, 7).setValue('gán ' + station + ' lúc ' + _now());
  var kq = _chuyenChoGan(rec[0], rec[1], station);
  // Công bố ngay để máy tự nhận tên qua Firebase, khỏi phải vào portal gõ tay.
  var daCongBo = _fbCongBoMay(rec[1], station);
  return { ok:true, station:station, daChuyen:kq.daChuyen, loi:kq.loi,
           daCongBoFirebase:daCongBo,
           ghiChu: daCongBo ? 'Máy sẽ tự nhận tên cửa hàng trong vòng ~1 phút.'
                            : 'CHƯA công bố được lên Firebase (kiểm FB_SECRET) — máy vẫn phải khai tên ở portal.' };
}
/* ===========================================================================
 *  SỐ SIM CỦA MÁY — nhập tay (máy WiFi không có module 4G để tự dò)
 * ---------------------------------------------------------------------------
 *  Anh Thắng 07/08/2026: *"bổ sung cột sim … nếu tự dò seri thì quá tốt, không thì nhập tay"*.
 *  Máy 4G ≥ firmware 2026-08-07f tự gửi ICCID lên heartbeat, khỏi gõ. Hàm này cho hai ca còn lại:
 *  máy chạy WiFi, và máy firmware cũ chưa biết gửi.
 *  ⚠️ Chỉ giữ CHỮ SỐ: ICCID in trên thẻ hay bị chép kèm dấu cách. So hai số khác định dạng nhau
 *     thì lúc đối chiếu với số máy tự dò sẽ lệch oan.
 * =========================================================================== */
function luuSimMay(pin, row, sim){
  var u = _requireAuth(pin);
  if (!_canSuaHoSo(u)) return { ok:false, error:'Không có quyền sửa thông tin máy.' };
  var sh = _ensureSheet(SH_MAY); row = Number(row) || 0;
  if (row < 2 || row > sh.getLastRow()) return { ok:false, error:'Không tìm thấy dòng máy.' };
  var st = String(sh.getRange(row, 3).getValue() || '').replace(/^CS_/, '').trim();
  if (st && !u.isAdmin && !_canStation(u, st)) return { ok:false, error:'Không có quyền cơ sở này.' };
  var so = String(sim || '').replace(/\D/g, '');
  if (so && (so.length < 15 || so.length > 22))
    return { ok:false, error:'Số SIM (ICCID) phải 15–22 chữ số — đang nhận ' + so.length + ' chữ số.' };
  sh.getRange(row, 8).setValue(so);          // cột 8 = 'SIM', xem MAY_H
  return { ok:true, sim:so };
}

/** Bỏ gán (máy tháo ra khỏi cửa hàng) — không xoá dòng để giữ dấu vết serial/MAC. */
function boGanMay(pin, row){
  _quanTri(pin);
  var sh = _ensureSheet(SH_MAY); row = Number(row) || 0;
  if (row < 2 || row > sh.getLastRow()) return { ok:false, error:'Không tìm thấy dòng máy.' };
  var _mac = String(sh.getRange(row, 2).getValue() || '');
  sh.getRange(row, 3).setValue('');
  sh.getRange(row, 7).setValue('bỏ gán lúc ' + _now());
  /* ⚠️ 01/08/2026 — PHẢI xoá luôn bản đồ /may/<MAC> trên Firebase.
     Trước đây chỉ xoá ô trong Sheet, nên "Bỏ gán" KHÔNG có tác dụng thật: máy vẫn đọc
     /may/<MAC> thấy tên cửa hàng cũ và tiếp tục đẩy chấm công vào sheet cũ. Đúng ca anh
     Thắng nêu — MANG MÁY SANG CỬA HÀNG KHÁC. Cách xử lý đúng khi di chuyển máy là: Bỏ gán
     ở cửa hàng cũ -> máy về CHUA_DAT_TEN -> Gán vào cửa hàng mới. Muốn vậy thì Bỏ gán phải
     xoá được cả 2 nơi, không thì thao tác đó chỉ là giả. */
  var _daXoa = _fbBoMay(_mac);
  return { ok:true, daXoaFirebase:_daXoa,
           ghiChu: _daXoa
             ? 'Đã bỏ gán ở cả Sheet và Firebase. Máy sẽ về CHUA_DAT_TEN trong ~30 phút (hoặc khởi động lại cho nhanh), rồi Gán vào cửa hàng mới.'
             : 'Đã bỏ gán trong Sheet, nhưng CHƯA xoá được bản đồ trên Firebase (kiểm FB_SECRET) — máy có thể vẫn tự nhận tên cửa hàng CŨ.' };
}
/**
 * Chuyển các lượt gửi tạm của 1 máy về sheet cửa hàng. Dùng lại ĐÚNG đường ghi của doPost
 * (findOrCreateDateBlock / findOrCreateEmpRow) để không có logic ghi thứ hai lệch với bản chính.
 * Ảnh KHÔNG chuyển được (lúc gửi tạm không giữ base64) — ghi rõ chứ không nói suông là xong.
 */
function _chuyenChoGan(serial, mac, station){
  var cg = _sheet(SH_CHOGAN);
  if (!cg || cg.getLastRow() < 2) return { daChuyen:0, loi:0 };
  var s = _chuanMa(serial), m = _chuanMa(mac);
  var rows = cg.getRange(2, 1, cg.getLastRow() - 1, CHOGAN_H.length).getValues();
  var sheet = _sheet('CS_' + station), daChuyen = 0, loi = 0;
  if (!sheet) return { daChuyen:0, loi:0 };
  rows.forEach(function(r, i){
    if (String(r[8] || '').trim()) return;                                  // đã chuyển rồi
    var khop = (s && _chuanMa(r[1]) === s) || (m && _chuanMa(r[2]) === m);
    if (!khop) return;
    try {
      var parts = String(r[6] || '').split(' ');
      if (parts.length < 2) { loi++; return; }
      var _kb2 = findOrCreateDateBlock(sheet, parts[0]);
      var col = _kb2.col;
      var erow = findOrCreateEmpRow(sheet, _kb2.khoi, r[4], r[5]);
      /* ⚠️ 02/08/2026 — DÙNG CHUNG _ghiGioVaoRa, KHÔNG chép lại quy tắc.
         Trước đây chỗ này có BẢN SAO của quy tắc vào/ra. Bản sao đó không có chốt chống trùng và
         vẫn ghi đè giờ ra bằng lượt sớm hơn, nên chuyển lượt chờ gán 2 lần là hỏng giờ ra — đúng
         cái bẫy "hai nơi cùng một quy tắc rồi lệch nhau" đã gây lỗi tiền x2 bên Vận hành chi phí. */
      _ghiGioVaoRa(sheet, erow, col, parts[1], '');
      cg.getRange(i + 2, 9).setValue(_now() + ' -> CS_' + station + (String(r[7]||'').trim() ? ' (ẢNH KHÔNG chuyển được)' : ''));
      daChuyen++;
    } catch (e) { loi++; }
  });
  return { daChuyen:daChuyen, loi:loi };
}

/* ===========================================================================
 *  CHẨN ĐOÁN 1 MÁY  —  chanDoanMay(pin, row)
 * ---------------------------------------------------------------------------
 *  Vì sao cần: 02/08/2026 anh Thắng gán máy #2 vào TUTU_TP nhưng bảng vẫn báo
 *  máy khai "CHUA_DAT_TEN", và ~40 lượt chấm công "đồng bộ" xong không thấy đâu.
 *  Hai triệu chứng đó có ÍT NHẤT 4 nguyên nhân khác hẳn nhau:
 *     · /may/<MAC> chưa từng công bố  (FB_SECRET hỏng lúc bấm Gán)
 *     · công bố rồi nhưng MAC trong Sheet KHÁC MAC firmware dùng làm khoá
 *     · máy đọc được rồi nhưng chưa khởi động lại nên còn giữ tên cũ
 *     · lượt chấm công nằm ở ChamCongChoGan chưa chuyển, hoặc đã vào CS_<khác>
 *  Đoán mò một trong bốn cái là sửa nhầm chỗ. Hàm này ĐỌC THẲNG cả 4 nguồn
 *  (Sheet máy · Firebase /may · heartbeat · ChamCongChoGan · sheet cơ sở) rồi
 *  nói ra kết luận, không bắt ai suy diễn.
 *  ⚠️ CHỈ ĐỌC — không ghi, không sửa gì. Bấm bao nhiêu lần cũng vô hại.
 * =========================================================================== */
function chanDoanMay(pin, row){
  _quanTri(pin);
  var sh = _ensureSheet(SH_MAY); row = Number(row) || 0;
  if (row < 2 || row > sh.getLastRow()) return { ok:false, error:'Không tìm thấy dòng máy.' };
  var r = sh.getRange(row, 1, 1, MAY_H.length).getValues()[0];
  var serial = String(r[0]||''), mac = String(r[1]||'');
  var ganCho = String(r[2]||'').replace(/^CS_/, '').trim();
  var tuKhai = String(r[4]||'').trim();
  var k = _macKey(mac);
  var kq = { ok:true, row:row, serial:serial, mac:mac, macKey:k, ganCho:ganCho, tuKhai:tuKhai,
             lanCuoi:String(r[5]||''), tenBangChoGan:SH_CHOGAN, viec:[] };

  // 1) Bản đồ trên Firebase — đây là chỗ máy đọc để biết mình thuộc cửa hàng nào.
  if (!k) { kq.banDo = { coKhoa:false }; kq.viec.push('🔴 Dòng máy này KHÔNG có MAC bo. Không có MAC thì không công bố được /may/<MAC>, máy vĩnh viễn không tự biết tên cửa hàng. Cho máy đẩy 1 lượt chấm công để web ghi lại MAC, rồi Gán lại.'); }
  else {
    var v = _fbGet('/may/' + k);
    kq.banDo = { coKhoa:true, duong:'/may/' + k, giaTri:(v === undefined ? null : v), docDuoc:(v !== undefined) };
    if (v === undefined)
      kq.viec.push('🔴 KHÔNG đọc được Firebase (xem loiFirebaseGanNhat ở Chẩn đoán hệ thống). Chưa kết luận được gì về bản đồ máy.');
    else if (v === null)
      kq.viec.push('🔴 Chưa có /may/' + k + ' trên Firebase — máy không có chỗ nào để biết tên cửa hàng, nên giữ nguyên CHUA_DAT_TEN. Bấm "Gán" lại cho dòng này (lần gán trước công bố hỏng).');
    else {
      var stFb = String((v && v.station) || '').trim();
      kq.banDo.station = stFb; kq.banDo.at = String((v && v.at) || '');
      if (!ganCho) kq.viec.push('🟠 Sheet chưa gán cửa hàng nhưng Firebase đang ghi "' + stFb + '". Bỏ gán rồi gán lại cho hai bên khớp.');
      else if (_chuanMa(stFb) !== _chuanMa(ganCho))
        kq.viec.push('🔴 LỆCH: Sheet gán "' + ganCho + '" nhưng Firebase ghi "' + stFb + '". Máy đọc Firebase nên nó đang chạy theo "' + stFb + '". Bấm "Gán" lại.');
      else if (tuKhai && _chuanMa(tuKhai) !== _chuanMa(ganCho))
        kq.viec.push('🟠 Firebase ĐÃ ghi đúng "' + ganCho + '" (lúc ' + kq.banDo.at + ') nhưng máy vẫn tự khai "' + tuKhai + '". Nghĩa là máy chưa đọc lại bản đồ: KHỞI ĐỘNG LẠI máy, hoặc chờ tới ~30 phút. Nếu khởi động lại vẫn khai tên cũ thì MAC firmware dùng làm khoá khác MAC trong Sheet — báo lại để đối chiếu.');
      else kq.viec.push('✅ Bản đồ /may khớp với Sheet.');
    }
  }

  // 2) Heartbeat theo TÊN CỬA HÀNG ĐÃ GÁN — máy khai sai tên thì heartbeat rơi vào tên khác.
  try {
    var hb = _hbTatCa();
    kq.heartbeat = {};
    [ganCho, tuKhai].forEach(function(t){
      if (!t) return;
      var h = hb[t];
      kq.heartbeat[t] = h ? { thayLuc:h.ms, truocDay: Math.round((Date.now() - h.ms)/1000) + 's', fw:(h.fw||'') } : null;
    });
    if (ganCho && tuKhai && _chuanMa(ganCho) !== _chuanMa(tuKhai) && kq.heartbeat[tuKhai] && !kq.heartbeat[ganCho])
      kq.viec.push('🔴 Heartbeat đang về dưới tên "' + tuKhai + '" chứ không phải "' + ganCho + '". Mọi đường Firebase của máy (/queue, /roster, /photo) cũng đang mang tên sai đó — lệnh gửi xuống máy sẽ rơi vào hư không.');
  } catch(e){ kq.heartbeat = { loi:String(e) }; }

  // 3) Lượt chấm công đang nằm ở ChamCongChoGan (gửi tạm, chưa vào sheet cửa hàng nào).
  try {
    var cg = _sheet(SH_CHOGAN), cho = 0, daCh = 0, moiNhat = '';
    if (cg && cg.getLastRow() >= 2){
      var s2 = _chuanMa(serial), m2 = _chuanMa(mac);
      cg.getRange(2, 1, cg.getLastRow() - 1, CHOGAN_H.length).getValues().forEach(function(x){
        var khop = (s2 && _chuanMa(x[1]) === s2) || (m2 && _chuanMa(x[2]) === m2);
        if (!khop) return;
        if (String(x[8]||'').trim()) daCh++;
        else { cho++; if (String(x[6]||'') > moiNhat) moiNhat = String(x[6]||''); }
      });
    }
    kq.choGan = { chuaChuyen:cho, daChuyen:daCh, moiNhat:moiNhat };
    if (cho) kq.viec.push('🔴 Có ' + cho + ' lượt chấm công của máy này đang nằm ở bảng "' + SH_CHOGAN
      + '" chưa vào sheet cửa hàng (mới nhất ' + moiNhat + '). Bấm "Gán" lại cho dòng máy này — hàm gán sẽ tự chuyển hết về CS_' + (ganCho || '<cửa hàng>') + '.');
  } catch(e){ kq.choGan = { loi:String(e) }; }

  // 4) Sheet cửa hàng có thật sự nhận được lượt nào hôm nay không.
  try {
    if (ganCho){
      var shCS = _sheet('CS_' + ganCho);
      kq.sheetCoSo = { ten:'CS_' + ganCho, ton:!!shCS, soNV: shCS ? Math.max(0, shCS.getLastRow() - 1) : 0 };
      if (!shCS) kq.viec.push('🔴 Không có sheet CS_' + ganCho + '. Lượt chấm công không có chỗ để ghi.');
    } else kq.viec.push('🔴 Dòng máy này CHƯA gán cửa hàng. Mọi lượt chấm công của nó chỉ nằm ở "' + SH_CHOGAN + '".');
  } catch(e){ kq.sheetCoSo = { loi:String(e) }; }

  if (!kq.viec.length) kq.viec.push('✅ Không thấy vấn đề nào ở dòng máy này.');
  return kq;
}

/** Gửi tạm 1 lượt chấm công của máy chưa gán — KHÔNG được mất dữ liệu của nhân viên. */
function _luuChoGan(serial, mac, tuKhai, empNo, name, time, coAnh){
  try {
    _ensureSheet(SH_CHOGAN).appendRow([_now(), String(serial||''), String(mac||''), String(tuKhai||''),
      String(empNo||''), String(name||''), String(time||''), coAnh ? 'có' : '', '']);
    return true;
  } catch (e) { _fbGhiLoi('CHOGAN_LOI', String(e)); return false; }
}

// ===== Firebase: đẩy lệnh NV xuống ESP32 qua 4G (hàng đợi doGet redirect KHÔNG chạy qua 4G) =====
// ⚠️ 31/07/2026 — CHUYỂN sang project Firebase RIÊNG của chấm công (tên project bôi đen — xem Script Property FB_HOST).
//    Trước đây dùng chung project gen-lang-client-0925768932 với Ghế massage / QuanLyNhanVien;
//    hai app khác VẪN Ở project cũ, đừng đổi chúng theo.
//    ⚠️ Máy ESP32 phải cùng project: SEC_FB_HOST trong secrets.h, hoặc ô "Link Firebase RTDB"
//    ở portal 192.168.4.1 (giá trị trong NVS THẮNG giá trị biên dịch, nạp lại KHÔNG sửa được).
//    Lệch project thì máy ghi một nơi, web app đọc một nơi — KHÔNG báo lỗi, chỉ "mãi không thấy máy".
//    Đổi project về sau: đặt Script Property `FB_HOST`, khỏi phải sửa code + deploy lại.
var FB_HOST_MACDINH = '<<LINK_FIREBASE_RTDB>>';
function _fbHost(){
  var v = String(PropertiesService.getScriptProperties().getProperty('FB_HOST') || '').trim();
  if (!v) return FB_HOST_MACDINH;
  v = v.replace(/\/+$/, '');                                        // dán từ Console hay kèm "/" cuối
  if (!/^https:\/\/[A-Za-z0-9._-]+\.(firebasedatabase\.app|firebaseio\.com)$/.test(v)){
    _fbGhiLoi('FB_HOST_SAI', 'Script Property FB_HOST sai dạng, phải là https://<ten>-default-rtdb.'
      + '<vung>.firebasedatabase.app -> tạm dùng mặc định. Đang đặt: ' + v);
    return FB_HOST_MACDINH;
  }
  return v;
}
// ⚠️ Secret của Firebase ĐẶT Ở Script Properties (`FB_SECRET`), KHÔNG viết vào code.
// Thiếu secret mà rule RTDB đóng thì MỌI lệnh Firebase thất bại -> ghi lại để chẩn đoán ra,
// trước đây thất bại hoàn toàn im lặng.
function _fbAuth(){
  var s = PropertiesService.getScriptProperties().getProperty('FB_SECRET') || '';
  if (!s) _fbGhiLoi('THIEU_FB_SECRET', 'Chưa đặt Script Property FB_SECRET -> gọi Firebase KHÔNG kèm auth');
  return s ? ('?auth=' + s) : '';
}
function _fbGhiLoi(ma, chiTiet){
  try { PropertiesService.getScriptProperties().setProperty('last_fb_err', _now() + ' | ' + ma + ' | ' + String(chiTiet).substring(0, 300)); } catch (e) {}
  try { console.error('[Firebase] ' + ma + ': ' + chiTiet); } catch (e) {}
}
// ⚠️ QUY ƯỚC TRẢ VỀ CỦA _fbGet — đọc kỹ trước khi dùng:
//    giá trị  = đọc được, có dữ liệu
//    null     = đọc được, node RỖNG (Firebase trả "null")
//    undefined= GỌI THẤT BẠI (mất mạng / sai auth / Firebase trả error)
// Trước đây cả 3 ca đều trả null, nên "gọi thất bại" bị hiểu thành "không có gì" ->
// _reconcileFromFirebase đánh dấu MỌI lệnh đang chờ là 'synced' dù chưa đẩy được gì xuống máy.
function _fbGet(path){
  var t;
  try {
    var r = UrlFetchApp.fetch(_fbHost() + path + '.json' + _fbAuth(), { muteHttpExceptions:true });
    var code = r.getResponseCode();
    t = r.getContentText();
    if (code < 200 || code >= 300) { _fbGhiLoi('GET_HTTP_' + code, path + ' -> ' + t); return undefined; }
  } catch (e) { _fbGhiLoi('GET_NEM_LOI', path + ' -> ' + e); return undefined; }
  if (t === null || t === undefined || t === '') { _fbGhiLoi('GET_RONG_LA', path); return undefined; }
  if (t === 'null') return null;                                  // node rỗng thật
  if (t.indexOf('"error"') >= 0) { _fbGhiLoi('GET_FB_ERROR', path + ' -> ' + t); return undefined; }
  try { return JSON.parse(t); } catch (pe) { _fbGhiLoi('GET_JSON_SAI', path + ' -> ' + t); return undefined; }
}
function _fbPut(path, val){
  try {
    var r = UrlFetchApp.fetch(_fbHost() + path + '.json' + _fbAuth(), { method:'put', contentType:'application/json', payload: JSON.stringify(val), muteHttpExceptions:true });
    var code = r.getResponseCode();
    if (code < 200 || code >= 300) { _fbGhiLoi('PUT_HTTP_' + code, path + ' -> ' + r.getContentText()); return false; }
    return true;
  } catch(e){ _fbGhiLoi('PUT_NEM_LOI', path + ' -> ' + e); return false; }
}
function _fbDelete(path){
  try {
    var r = UrlFetchApp.fetch(_fbHost() + path + '.json' + _fbAuth(), { method:'delete', muteHttpExceptions:true });
    var code = r.getResponseCode();
    if (code < 200 || code >= 300) { _fbGhiLoi('DEL_HTTP_' + code, path + ' -> ' + r.getContentText()); return false; }
    return true;
  } catch(e){ _fbGhiLoi('DEL_NEM_LOI', path + ' -> ' + e); return false; }
}
function _genderMachine(g){ g = String(g||'').toLowerCase(); if (g==='nam'||g==='male') return 'male'; if (g==='nữ'||g==='nu'||g==='female') return 'female'; return ''; }

/**
 * "Người đang chạy hàm này CÓ PHẢI chủ script?" — dùng để chặn google.script.run gọi ẩn danh.
 * Web app deploy kiểu USER_DEPLOYING + "ai cũng truy cập": khách ẩn danh khiến
 * getActiveUser() = '' còn getEffectiveUser() = chủ script. Trong editor và trong
 * trigger theo giờ thì HAI CÁI BẰNG NHAU. Đó chính là chỗ phân biệt.
 * Sai sót phải nghiêng về phía TỪ CHỐI, nên bắt lỗi -> false.
 */
function _laChuScript(){
  try {
    var act = Session.getActiveUser().getEmail();
    return !!act && act === Session.getEffectiveUser().getEmail();
  } catch (e) { return false; }
}
/**
 * ⚠️ SỬA 30/07/2026 — bản trước dùng _laChuScript() làm cổng DUY NHẤT và nó CHẶN NHẦM CHÍNH CHỦ:
 *    appsscript.json không khai `oauthScopes`, nên Session.getActiveUser() trả về RỖNG ngay cả
 *    khi bấm ▶ trong editor -> kiemTraBiMat / kiemTraFolderAnhChamCong / donCmdCu đều báo
 *    "không phải chủ script". Không thể phân biệt "editor" với "google.script.run" nếu không có
 *    email, nên bỏ cách đó: đường chắc chắn là BẮT PIN ADMIN — thứ khách ẩn danh không có.
 *
 * Dùng chẩn đoán ở TAB "Cập nhật FW" trong web app (đã đăng nhập nên tự có PIN), khỏi vào editor.
 */
function _chiQuanTri(ten, pin){
  if (_laChuScript()) return;                       // vẫn nhận, phòng khi scope email có sẵn
  var u = loginByPin(pin || '');
  if (u.ok !== false && u.isAdmin) return;
  _ghiTuChoi(ten);
  throw new Error(ten + ' cần quyền Admin. Mở web app > tab "Cập nhật FW" > mục Chẩn đoán '
                + '(ở đó đã có sẵn PIN), hoặc gọi ' + ten.replace('()', "('<PIN Admin>')") + '.');
}

/**
 * Cổng cho 3 hàm chạy tự động hằng ngày. Chúng vừa là trigger, vừa từng gọi được qua
 * google.script.run bởi khách ẩn danh (autoBackfillAll = bơm lệnh xuống MỌI máy chấm công).
 *
 * KHÔNG chỉ dựa vào _laChuScript(): nếu getActiveUser() trong trigger theo giờ trả về ''
 * thì tác vụ hằng ngày sẽ chết ÂM THẦM — đúng thứ không được phép xảy ra ở đây. Nên nhận
 * thêm đường thứ hai: trigger luôn được gọi kèm event có `triggerUid`, mà uid đó phải KHỚP
 * một trigger đang tồn tại của project — khách không đoán được, cũng không tự tạo được.
 * Hai đường độc lập, còn một đường là tác vụ vẫn chạy.
 */
function _laTrigger(e){
  try {
    var uid = e && e.triggerUid; if (!uid) return false;
    var ts = ScriptApp.getProjectTriggers();
    for (var i = 0; i < ts.length; i++) if (ts[i].getUniqueId() === String(uid)) return true;
    return false;
  } catch (er) { return false; }
}
function _chiTriggerHoacChu(ten, e){
  if (_laTrigger(e) || _laChuScript()) return;
  _ghiTuChoi(ten);
  throw new Error(ten + ' chỉ chạy từ trigger tự động hoặc editor Apps Script.');
}
// Từ chối thì phải THẤY được, không im lặng: nếu tác vụ hằng ngày đột nhiên ngừng chạy thì
// đây là chỗ đầu tiên cần xem (Script Property `last_gac_tuchoi`).
function _ghiTuChoi(ten){
  try {
    PropertiesService.getScriptProperties().setProperty('last_gac_tuchoi', _now() + ' | ' + ten);
    console.error('Từ chối gọi ' + ten + ' — không phải trigger, không phải chủ script.');
  } catch (e) {}
}
// Chạy 1 LẦN trong editor để cấp quyền UrlFetchApp (kết nối Firebase) + test đọc/ghi.
/**
 * CHẠY 1 LẦN TRONG EDITOR sau khi đổi thư mục ảnh chấm công.
 * Kiểm: mở được thư mục mới? GHI được vào đó? thư mục cũ còn đọc được? có lỗi lưu ảnh gần đây?
 * Phải chạy trước khi tin — vì doPost lỗi lưu ảnh thì vẫn ghi giờ, ảnh mất mà không ai thấy.
 */
function kiemTraFolderAnhChamCong(pin){
  _chiQuanTri('kiemTraFolderAnhChamCong()', pin);   // in ra id thư mục + lỗi hệ thống -> không cho khách gọi
  var out = { moi:{ id:ATT_FOLDER_ID }, cu:{ id:ATT_FOLDER_ID_CU }, loiAnhGanNhat:null };
  // 1) thư mục MỚI: mở + thử ghi rồi xoá file thử
  try {
    var f = DriveApp.getFolderById(ATT_FOLDER_ID);
    out.moi.ten = f.getName();
    out.moi.moDuoc = true;
    try {
      var t = f.createFile(Utilities.newBlob('test', 'text/plain', '_kiemtra_ghi.txt'));
      out.moi.ghiDuoc = true;
      t.setTrashed(true);
    } catch (we) { out.moi.ghiDuoc = false; out.moi.loiGhi = String(we); }
  } catch (e) { out.moi.moDuoc = false; out.moi.loi = String(e); }
  // 2) thư mục CŨ: chỉ cần ĐỌC được để ảnh ngày trước còn hiện
  try { out.cu.ten = DriveApp.getFolderById(ATT_FOLDER_ID_CU).getName(); out.cu.docDuoc = true; }
  catch (e2) { out.cu.docDuoc = false; out.cu.loi = String(e2); }
  try { out.loiAnhGanNhat = PropertiesService.getScriptProperties().getProperty('last_img_err') || null; } catch (e3) {}
  out.ketLuan = (out.moi.moDuoc && out.moi.ghiDuoc)
    ? ('OK — ảnh mới sẽ lưu vào "' + out.moi.ten + '"' + (out.cu.docDuoc ? '; ảnh cũ vẫn đọc được.' : '; ⚠ THƯ MỤC CŨ KHÔNG ĐỌC ĐƯỢC -> ảnh ngày trước sẽ không hiện.'))
    : ('⚠ CHƯA DÙNG ĐƯỢC thư mục mới — chia sẻ quyền Editor cho tài khoản đang deploy web app, rồi chạy lại. Lỗi: ' + (out.moi.loiGhi || out.moi.loi));
  Logger.log(JSON.stringify(out, null, 2));
  return out;
}

/**
 * CHẠY TRONG EDITOR để biết bí mật nào còn nằm trong CODE và Firebase có gọi được không.
 * Cố ý KHÔNG in ra giá trị bí mật — chỉ in độ dài + 4 ký tự đầu, đủ để đối chiếu với firmware.
 */
function kiemTraBiMat(pin){
  _chiQuanTri('kiemTraBiMat()', pin);   // in 4 ký tự đầu của bí mật -> tuyệt đối không cho khách gọi
  var P = PropertiesService.getScriptProperties();
  function mo(v){ v = String(v || ''); return v ? (v.substring(0,4) + '…(' + v.length + ' ký tự)') : '(trống)'; }
  var fbProp = P.getProperty('FB_SECRET') || '', tkProp = P.getProperty('EMP_TOKEN') || '';
  var out = {
    // Host KHÔNG phải bí mật — in nguyên văn, vì "lệch project" là lỗi im lặng
    // mà che link đi thì không cách nào tìm ra (đã mất công vì portal ESP32 che link).
    FB_HOST:    { dangDung: _fbHost(), tuScriptProperty: !!(P.getProperty('FB_HOST') || ''),
                  ghiChu: 'Máy ESP32 phải cùng link này (SEC_FB_HOST / ô Firebase ở portal)' },
    FB_SECRET:  { oScriptProperties: !!fbProp, giaTri: mo(fbProp) },
    // 07/08/2026: hằng số dự phòng đã gỡ -> chưa đặt property nghĩa là CHẶN HẾT, không phải "nên chuyển".
    EMP_TOKEN:  { oScriptProperties: !!tkProp, dangDung: tkProp ? 'Script Property' : '🔴 CHƯA ĐẶT — máy chấm công bị chặn hết', giaTri: mo(_empToken()) },
    SSO_SECRET: { oScriptProperties: !!(P.getProperty('SSO_SECRET') || '') },
    loiFirebaseGanNhat: P.getProperty('last_fb_err') || null,
    loiAnhChamCongGanNhat: P.getProperty('last_img_err') || null
  };
  // thử đọc-ghi Firebase thật: phân biệt được "rỗng" với "gọi thất bại"
  var ok = _fbPut('/_kiemtra/ping', { t: _now() });
  var doc = _fbGet('/_kiemtra/ping');
  out.firebase = { ghiDuoc: ok, docDuoc: (doc !== undefined), noiDung: (doc === undefined ? '(gọi THẤT BẠI)' : doc) };
  if (doc !== undefined) _fbDelete('/_kiemtra/ping');
  var cb = [];
  if (!fbProp) cb.push('Chưa đặt FB_SECRET -> gọi Firebase không kèm auth; rule đóng là thất bại HẾT.');
  if (!tkProp) cb.push('CHƯA đặt EMP_TOKEN -> app từ chối MỌI lệnh từ máy chấm công. Bấm "Tạo token mới".');
  if (!out.firebase.ghiDuoc || !out.firebase.docDuoc) cb.push('Firebase KHÔNG gọi được — xem loiFirebaseGanNhat.');
  out.canLam = cb.length ? cb : ['OK — EMP_TOKEN lấy từ Script Property, Firebase gọi được.'];
  Logger.log(JSON.stringify(out, null, 2));
  return out;
}

function authorizeFirebase(pin){
  _chiQuanTri('authorizeFirebase()', pin);   // trả về nội dung Firebase -> chỉ chủ script
  _fbPut('/queue/_authtest/ping', { t: _now() });
  var r = _fbGet('/queue/_authtest');
  Logger.log('Firebase OK: ' + JSON.stringify(r));
  return r;
}

function _findRow(sh, keyCol, keyVal) {
  var last = sh.getLastRow();
  if (last < 2) return null;
  var v = sh.getRange(2, 1, last - 1, sh.getLastColumn()).getValues();
  for (var i = 0; i < v.length; i++) {
    if (String(v[i][keyCol - 1]) === String(keyVal)) return { row: i + 2, data: v[i] };
  }
  return null;
}

/* 🔴 03/08/2026 — HÀM NÀY TỪNG NHẬN THAM SỐ THỨ HAI RỒI VỨT ĐI.
   `_ccnvSheet()` gọi `_ensureSheet(SH_CCNV, CCNV_H)` tưởng là khai tiêu đề, nhưng tên
   'ChamCongNhiemVu' không có trong bảng bên dưới nên sheet được tạo KHÔNG CÓ HÀNG TIÊU ĐỀ.
   Hậu quả dây chuyền: bản ghi đầu rơi vào HÀNG 1, mà mọi chỗ đọc/khử trùng đều bắt đầu từ
   HÀNG 2 -> hàng 1 vô hình -> lượt sau ghi THÊM một dòng trùng thay vì ghi đè.
   Đúng bằng hai dòng trùng anh Thắng thấy trong sheet `ChamCongNhiemVu`.
   Nay: nhận tiêu đề do người gọi truyền vào, và khai luôn SH_CCNV cho chắc cả hai đường. */
function _ensureSheet(name, cotTuKhai) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sh = ss.getSheetByName(name);
  if (sh) return sh;
  sh = ss.insertSheet(name);
  var headers = {};
  if (cotTuKhai && cotTuKhai.length) headers[name] = cotTuKhai.slice();
  headers[SH_CCNV]  = CCNV_H.slice();
  headers[SH_ROLE]  = PQ_H.slice();
  headers[SH_NV]    = NV_HEADERS.slice();
  headers[SH_QUEUE] = ['opId', 'action', 'Mã NV', 'Họ tên', 'PIN máy', 'photoFileId', 'Cửa hàng', 'status', 'Tạo lúc', 'Kết quả'];
  headers[SH_FLAG]  = ['flagId', 'Cửa hàng', 'Ngày', 'Mã NV', 'Họ tên', 'Ghi chú', 'Người gắn', 'status', 'Tạo lúc', 'Xử lý lúc'];
  headers[SH_MAY]    = MAY_H.slice();
  headers[SH_CHOGAN] = CHOGAN_H.slice();
  headers[SH_BOPHAN] = BOPHAN_H.slice();
  headers[SH_MA_SS]  = MA_SS_H.slice();
  if (headers[name]) {
    sh.getRange(1, 1, 1, headers[name].length).setValues([headers[name]])
      .setFontWeight('bold').setBackground('#0f172a').setFontColor('#38bdf8');
    sh.setFrozenRows(1);
  }
  return sh;
}

// Đọc sheet bị lệch cột: chặn theo cột TIÊU ĐỀ NGÀY (miễn nhiễm lệch khối) để lấy giờ vào/ra.
/**
 * Quét sheet lệch cột để dựng lại.
 * ⚠️ Bản cũ chỉ đọc getDisplayValues() nên MỌI CÔNG THỨC =IMAGE(...) BIẾN MẤT sau khi dựng lại —
 *    xoá sạch ảnh chấm công mà không báo một câu. Nay đọc thêm getFormulas() và mang ảnh theo.
 * ⚠️ Một ngày có >2 lượt quẹt thì bản cũ lặng lẽ bỏ các lượt giữa (chỉ giữ sớm nhất + muộn nhất).
 *    Nay vẫn giữ cách đó (đúng bố cục 2 cột vào/ra) nhưng ĐẾM và BÁO số lượt bị bỏ.
 */
/* ⚠️ 01/08/2026 — heal dựng LẠI toàn sheet theo khuôn MỘT KHỐI nằm ngang. Sheet đã có nhiều
   khối tháng xếp dọc mà chạy heal thì nó dàn phẳng hết về một khối — dữ liệu không mất nhưng
   bố cục anh Thắng yêu cầu thì mất sạch, và phải dựng lại bằng tay. Nên CHẶN thẳng, có lý do rõ.
   (Chưa viết lại heal cho nhiều khối: đây là công cụ sửa chữa dùng rất thưa, viết vội dễ hỏng
   hơn là để nó từ chối chạy.) */
function _csChanHealNhieuKhoi(sheetName) {
  var sh = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(sheetName);
  if (!sh) return null;
  var n = _csKhoi(sh).length;
  if (n > 1) return 'Sheet ' + sheetName + ' có ' + n + ' khối tháng xếp dọc. heal sẽ dàn phẳng '
                  + 'hết về một khối nằm ngang -> KHÔNG chạy. Cần sửa dữ liệu thì sửa tay, hoặc '
                  + 'nhờ viết lại heal cho nhiều khối trước.';
  return null;
}

function _healExtract(sheetName) {
  var sh = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(sheetName);
  var lr = sh.getLastRow(), lc = sh.getLastColumn();
  var disp = sh.getRange(1, 1, lr, lc).getDisplayValues();
  var form = sh.getRange(1, 1, lr, lc).getFormulas();   // để giữ =IMAGE(...)
  var hdr = disp[0];
  var dcols = [];
  for (var c = 2; c < lc; c++) {
    var m = String(hdr[c] || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) dcols.push({ col: c, date: m[1] + '-' + m[2] + '-' + m[3] });
  }
  var emps = [], seen = {}, recs = [], soAnh = 0, boQuaLuot = 0;
  for (var r = 2; r < lr; r++) {
    var name = String(disp[r][0] || '').trim(), id = String(disp[r][1] || '').trim();
    if (!id && !name) continue;
    if (id && !seen[id]) { seen[id] = 1; emps.push({ id: id, name: name }); }
    for (var k = 0; k < dcols.length; k++) {
      var start = dcols[k].col, end = ((k + 1 < dcols.length) ? dcols[k + 1].col : lc) - 1;
      var toks = [], anh = [];
      for (var cc = start; cc <= end; cc++) {
        var mm = String(disp[r][cc] || '').match(/\d{1,2}:\d{2}/g);
        if (mm) mm.forEach(function(t){ toks.push(t.length === 4 ? '0' + t : t); });
        var f = String((form[r] && form[r][cc]) || '');    // công thức ảnh trong khối ngày này
        if (f.indexOf('IMAGE(') >= 0 || f.indexOf('image(') >= 0) anh.push(f);
      }
      if (!toks.length && !anh.length) continue;
      var uq = {}; toks.forEach(function(t){ uq[t] = 1; });
      var u = Object.keys(uq).sort();
      if (u.length > 2) boQuaLuot += (u.length - 2);       // bố cục chỉ có 2 cột vào/ra
      soAnh += anh.length;
      recs.push({ id: id, name: name, date: dcols[k].date,
                  in: (u[0] || ''), out: (u.length > 1 ? u[u.length - 1] : ''),
                  anhVao: (anh[0] || ''), anhRa: (anh.length > 1 ? anh[anh.length - 1] : '') });
    }
  }
  var ds = {}, dates = [];
  dcols.forEach(function(d){ if (!ds[d.date]) { ds[d.date] = 1; dates.push(d.date); } });
  dates.sort();
  var cb = [];
  if (boQuaLuot) cb.push(boQuaLuot + ' luot quet giua ngay se BI BO (bo cuc chi co 2 cot vao/ra).');
  if (!dcols.length) cb.push('KHONG tim thay cot ngay nao (hang 1 phai co yyyy-MM-dd) — dung lai se lam TRONG sheet.');
  if (!emps.length)  cb.push('KHONG tim thay nhan vien nao — dung lai se lam TRONG sheet.');
  return { emps: emps, recs: recs, dates: dates, soAnh: soAnh, boQuaLuot: boQuaLuot, canhBao: cb };
}

/**
 * Dựng lại sheet chấm công theo đúng bố cục 5 cột/ngày.
 * ⚠️ Hàm này gọi sh.clear() — XOÁ SẠCH rồi ghi lại. Trước khi xoá PHẢI nhân bản sheet ra
 *    bản sao lưu: nếu quét sai (sheet lệch kiểu lạ) thì còn đường lùi. Trước đây không có,
 *    chạy nhầm một cái là mất trắng chấm công của cả cửa hàng.
 */
function _rebuildAttendanceSheet(sheetName, data) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sh = ss.getSheetByName(sheetName);
  // --- SAO LƯU TRƯỚC KHI XOÁ (copyTo giữ nguyên cả công thức ảnh) ---
  var tenBK = '';
  try {
    tenBK = (sheetName + '_BK_' + Utilities.formatDate(new Date(), TZ, 'MMdd_HHmmss')).substring(0, 99);
    sh.copyTo(ss).setName(tenBK);
  } catch (e) {
    throw new Error('KHONG sao luu duoc sheet truoc khi dung lai (' + e + ') — DUNG LAI, khong xoa gi.');
  }
  var dates = data.dates.slice().sort();
  var emps = data.emps.slice();
  var TC = 2 + dates.length * 5;
  var idxD = {}; dates.forEach(function(d, i){ idxD[d] = i; });
  var subs = ['Giờ Vào / Checkin', 'Ảnh Checkin', 'Giờ Ra / CheckOut', 'Ảnh Checkout', 'Thời gian trong ngày'];
  var out = [];
  var row1 = new Array(TC).fill(''); row1[0] = 'Họ và Tên'; row1[1] = 'ID';
  dates.forEach(function(d, i){ row1[2 + i * 5] = d; }); out.push(row1);
  var row2 = new Array(TC).fill(''); row2[0] = 'Họ và Tên'; row2[1] = 'ID';
  dates.forEach(function(d, i){ for (var s = 0; s < 5; s++) row2[2 + i * 5 + s] = subs[s]; }); out.push(row2);
  var rowByEmp = {}, anhTheoHang = {};
  emps.forEach(function(e, i){ var row = new Array(TC).fill(''); row[0] = e.name; row[1] = e.id;
    rowByEmp[e.id] = row; anhTheoHang[e.id] = i; out.push(row); });
  // anhO[cột 0-based] = mảng công thức theo từng hàng nhân viên
  var anhO = {}, soAnhGhi = 0;
  data.recs.forEach(function(rec){
    var row = rowByEmp[rec.id]; if (!row) return;
    var base = 2 + idxD[rec.date] * 5;
    row[base] = rec.in; row[base + 2] = rec.out; row[base + 4] = (rec.out ? rec.in + ' ' + rec.out : rec.in);
    [[base + 1, rec.anhVao], [base + 3, rec.anhRa]].forEach(function(x){
      if (!x[1]) return;
      if (!anhO[x[0]]) { anhO[x[0]] = []; for (var q = 0; q < emps.length; q++) anhO[x[0]].push(['']); }
      anhO[x[0]][anhTheoHang[rec.id]] = [x[1]]; soAnhGhi++;
    });
  });
  sh.clear();
  sh.getRange(1, 1, sh.getMaxRows(), sh.getMaxColumns()).breakApart();
  var rng = sh.getRange(1, 1, out.length, TC);
  rng.setNumberFormat('@'); rng.setValues(out);
  // Ảnh: ô đang để định dạng '@' (chữ) thì =IMAGE(...) thành chữ chứ không thành công thức
  // -> phải trả về 'General' rồi mới setFormulas.
  Object.keys(anhO).forEach(function(c){
    var col = Number(c) + 1;
    var r = sh.getRange(3, col, emps.length, 1);
    r.setNumberFormat('General');
    r.setFormulas(anhO[c]);
  });
  sh.setFrozenRows(2); sh.setFrozenColumns(2);
  sh.getRange('A1:A2').merge(); sh.getRange('B1:B2').merge();
  dates.forEach(function(d, i){ sh.getRange(1, 3 + i * 5, 1, 5).merge(); });
  sh.getRange(1, 1, 2, TC).setFontWeight('bold');
  return { emps: emps.length, dates: dates.length, records: data.recs.length,
           anhGiuLai: soAnhGhi, banSaoLuu: tenBK };
}

/**
 * Chạy 1 lần trong trình soạn Apps Script để tạo các tab mới + 1 tài khoản Admin.
 * Sau đó anh vào tab PhanQuyen sửa PIN/vai trò/cửa hàng cho từng người.
 */
function setupNangCap(pin){ _chiQuanTri('setupNangCap()', pin); return _nangCap(); }
function _nangCap() {
  _ensureSheet(SH_ROLE);
  _ensureSheet(SH_NV);
  _ensureSheet(SH_QUEUE);
  _ensureSheet(SH_FLAG);
  var role = _sheet(SH_ROLE);
  if (role.getLastRow() < 2) {
    // Admin mặc định = ADMIN_PIN_MAC_DINH — ĐỔI NGAY sau khi đăng nhập lần đầu!
    role.appendRow([ADMIN_PIN_MAC_DINH, 'Admin', ROLE.ADMIN, '']);
  }
  Logger.log('Đã tạo tab PhanQuyen/NhanVien/Queue/GhiChuChamCong. Admin PIN mặc định: ' + ADMIN_PIN_MAC_DINH + ' (hãy đổi).');
}

// ============================================================================
//  PHÂN LỊCH CÔNG VIỆC (Lái Tàu / Lơ Tàu / Thu Ngân...) — theo cơ sở, theo ngày
//  Sheet LichCongViec: Cơ sở | Ngày | Mã NV | Tên | Công việc (phẩy) | Người xếp | Cập nhật
// ============================================================================
var SH_LICHCV   = 'LichCongViec';
var DEFAULT_JOBS = ['Lái Tàu','Lơ Tàu','Thu Ngân'];

var DEFAULT_SHIFTS = [{name:'Ca 1',start:'06:00',end:'14:00'},{name:'Ca 2',start:'14:00',end:'22:00'},{name:'Ca 3',start:'22:00',end:'06:00'}];
function _lichSheet(){
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sh = ss.getSheetByName(SH_LICHCV);
  var HEAD=['Cơ sở','Ngày','Mã NV','Tên','Ca','Công việc','Người xếp','Cập nhật'];
  if(sh && String(sh.getRange(1,5).getValue())!=='Ca'){   // schema cũ (chưa có Ca) -> lưu lại rồi tạo mới
    try{ sh.setName(SH_LICHCV+'_old'); }catch(e){ try{ sh.setName(SH_LICHCV+'_old_'+(new Date()).getTime().toString(36)); }catch(e2){} }
    sh=null;
  }
  if(!sh){
    sh = ss.insertSheet(SH_LICHCV);
    sh.appendRow(HEAD);
    sh.getRange(1,1,1,HEAD.length).setFontWeight('bold').setBackground('#0f172a').setFontColor('#38bdf8');
    sh.setFrozenRows(1);
  }
  return sh;
}
function _jobTypes(){
  var raw = PropertiesService.getScriptProperties().getProperty('JOB_TYPES');
  if(raw){ try{ var a=JSON.parse(raw); if(a && a.length) return a; }catch(e){} }
  return DEFAULT_JOBS.slice();
}
function _shiftMap(){
  var raw = PropertiesService.getScriptProperties().getProperty('SHIFTS_MAP');
  if(raw){ try{ return JSON.parse(raw)||{}; }catch(e){} }
  // di trú từ prop cũ 'SHIFTS' (toàn cục) nếu có
  var old = PropertiesService.getScriptProperties().getProperty('SHIFTS');
  if(old){ try{ var a=JSON.parse(old); if(a&&a.length) return {'*':a}; }catch(e){} }
  return {};
}
function _shiftsOf(station){   // ca THEO CƠ SỞ; không có -> mặc định chung ('*') -> DEFAULT. Mỗi ca: {name,start,end,startW,endW}
  var map=_shiftMap();
  if(station && map[station] && map[station].length) return map[station];
  if(map['*'] && map['*'].length) return map['*'];
  return DEFAULT_SHIFTS.slice();
}
function setShifts(pin, station, list){
  var u=_requireAuth(pin);
  // Admin cấu hình ca mọi cơ sở; Quản lý/Cửa hàng trưởng cấu hình ca cho cơ sở mình quản lý.
  var can = u.isAdmin || ((u.role===ROLE.QUAN_LY || u.role===ROLE.CHT) && _canStation(u, station));
  if(!can) throw new Error('Chỉ Quản lý / Cửa hàng trưởng / Admin được cấu hình ca (và đúng cơ sở của mình).');
  var map=_shiftMap();
  list=(list||[]).map(function(s){ return {name:String(s.name||'').trim(), start:String(s.start||'').trim(), end:String(s.end||'').trim(), startW:String(s.startW||'').trim(), endW:String(s.endW||'').trim()}; }).filter(function(s){return s.name;});
  map[station||'*']=list;
  PropertiesService.getScriptProperties().setProperty('SHIFTS_MAP', JSON.stringify(map));
  return {ok:true};
}
/* Cơ sở BẬT PHÂN VIỆC (xếp ca ở tab Phân Lịch Làm). Mặc định = **chỉ Nhóm Tàu**.
   ⚠️ 03/08 — anh Thắng: *"không cần phân lịch làm của máy tự động bên này, tính lương theo cơ
      chế khác"*. Đúng: Tàu phải xếp ca vì việc đổi theo ca (lái/lơ/thu ngân); Posh–JP thì nhiệm
      vụ đã CỐ ĐỊNH THEO HÀNG trong bảng chấm công rồi, xếp lịch không thêm thông tin gì.
      Nên Posh/JP KHÔNG vào đây, mà đi đường `getLuongMayTuDong`.
   ⚠️ Đừng lẫn với `_luongStations()` bên dưới: tab Giờ & Lương phải liệt kê CẢ HAI nhóm.
   ⚠️ Admin đã tự khai `SCHED_STATIONS` thì tôn trọng khai báo đó, không đè lên. */
function _schedStations(){
  var raw = PropertiesService.getScriptProperties().getProperty('SCHED_STATIONS');
  if(raw){ try{ var d = JSON.parse(raw); if (d && d.length) return d; }catch(e){} }
  return SpreadsheetApp.getActiveSpreadsheet().getSheets()
    .map(function(s){return s.getName();})
    .filter(function(n){ var st=n.replace(/^CS_/,'');
      return n.indexOf('CS_')===0 && !!_nhomCoSo(st) && !_laMayTuDong(st); })
    .map(function(n){return n.replace(/^CS_/,'');});
}
/* Cơ sở hiện trong tab GIỜ & LƯƠNG = cơ sở phân việc + MỌI cơ sở Nhóm Máy Tự Động.
   Hai nhóm tính tiền bằng hai cơ chế khác nhau, nhưng cùng xem ở một chỗ. */
function _luongStations(){
  var ds = _schedStations().slice();
  SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(s){
    var n = s.getName(); if (n.indexOf('CS_') !== 0) return;
    var st = n.replace(/^CS_/, '');
    if (_laMayTuDong(st) && ds.indexOf(st) < 0) ds.push(st);
  });
  return ds;
}
function getScheduleConfig(pin){
  var u = _requireAuth(pin);
  var all = SpreadsheetApp.getActiveSpreadsheet().getSheets()
    .map(function(s){return s.getName();}).filter(function(n){return n.indexOf('CS_')===0;})
    .map(function(n){return n.replace(/^CS_/,'');});
  var enabled = _schedStations();
  var myStations = enabled.filter(function(st){ return u.isAdmin || _canStation(u, st); });
  var mgr = !!u.isAdmin || u.role===ROLE.QUAN_LY || u.role===ROLE.CHT;   // Admin/Quản lý/CHT: đặt lương + cấu hình ca & loại việc
  // Tab Giờ & Lương xem được nhiều cơ sở hơn tab Phân lịch (thêm cả Nhóm Máy Tự Động).
  var luongStations = _luongStations().filter(function(st){ return u.isAdmin || _canStation(u, st); });
  return { jobs:_jobTypes(), shifts:_shiftsOf(''), enabledStations:enabled, allStations:all, myStations:myStations,
           luongStations:luongStations, nhomTheoCoSo:_nhomTheoCoSo(),
           isAdmin:!!u.isAdmin, canWage:mgr, canConfig:mgr };
}
function setJobTypes(pin, list){
  var u=_requireAuth(pin);
  // Loại công việc dùng CHUNG toàn chuỗi -> Admin/Quản lý/Cửa hàng trưởng đều sửa được (không theo cơ sở).
  var can = u.isAdmin || u.role===ROLE.QUAN_LY || u.role===ROLE.CHT;
  if(!can) throw new Error('Chỉ Quản lý / Cửa hàng trưởng / Admin được sửa loại công việc.');
  list=(list||[]).map(function(s){return String(s||'').trim();}).filter(Boolean);
  PropertiesService.getScriptProperties().setProperty('JOB_TYPES', JSON.stringify(list));
  return {ok:true};
}
function setScheduleStations(pin, list){
  var u=_requireAuth(pin); if(!u.isAdmin) throw new Error('Chỉ Admin được bật/tắt cơ sở.');
  list=(list||[]).map(function(s){return String(s||'').trim();}).filter(Boolean);
  PropertiesService.getScriptProperties().setProperty('SCHED_STATIONS', JSON.stringify(list));
  return {ok:true};
}
function getStationEmployees(station, pin){   // GỘP: roster NhanVien (cửa hàng=station) + NV đã có trên sheet CS_
  var u = _requireAuth(pin);
  if(!u.isAdmin && !_canStation(u, station)) throw new Error('Không có quyền cơ sở này.');
  var map={}, order=[];
  function add(id,name){ id=String(id||'').trim(); name=String(name||'').trim(); if(!id&&!name) return;
    var k=id||name; if(!map[k]){ map[k]={id:id,name:name}; order.push(k); } else if(!map[k].name&&name){ map[k].name=name; } }
  var nv=_sheet(SH_NV);   // Mã NV | Họ tên | Cửa hàng | ...
  if(nv && nv.getLastRow()>=2){
    var vn=nv.getRange(2,1,nv.getLastRow()-1,3).getValues();
    vn.forEach(function(r){ if(String(r[2]||'').trim()===station) add(r[0], r[1]); });
  }
  var sh=_sheet('CS_'+station);
  if(sh && sh.getLastRow()>=3){
    var v=sh.getRange(3,1,sh.getLastRow()-2,2).getValues();
    // ⚠️ Bỏ hàng NHIỆM VỤ THÊM (`mã-TG`): phân lịch xếp theo NGƯỜI, không xếp theo hàng.
    v.forEach(function(r){ if(_tachMaNhiemVu(r[1])) return; add(r[1], r[0]); });   // cột A=Tên, B=ID
  }
  return order.map(function(k){ return map[k]; });
}
/* Người CÓ DÒNG trong sheet CS_<cơ sở> nhưng CHƯA có hồ sơ trong NhanVien.
   ⚠️ 02/08/2026 — anh Thắng: *"bên quản lý nhân viên chưa đọc dữ liệu từ sheet"*. Đúng: tab đó chỉ
      đọc sheet `NhanVien`. Người do MÁY tạo ra (doPost -> findOrCreateEmpRow) chỉ có dòng trong
      `CS_<cơ sở>`, không có hồ sơ, nên cơ sở mới như POSH_HCM hiện "chưa có nhân viên nào trên web"
      trong khi Sheet đầy người. Hàm này lôi họ ra để còn tạo hồ sơ.
   ⚠️ Sheet CS_ có NHIỀU khối tháng -> cùng một người xuất hiện nhiều dòng. Phải khử trùng theo mã,
      không thì danh sách lặp mấy chục dòng. */
function getNvChuaCoHoSo(pin, station){
  var u = _requireAuth(pin);
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Thiếu cơ sở.', list:[] };
  if (!u.isAdmin && !u.all && !_canStation(u, station))
    return { ok:false, error:'Không có quyền cơ sở này.', list:[] };
  var daCo = {}, nv = _sheet(SH_NV);
  if (nv && nv.getLastRow() >= 2)
    nv.getRange(2, 1, nv.getLastRow() - 1, 1).getValues().forEach(function(r){
      var m = String(r[0] || '').trim(); if (m) daCo[_chuanMa(m)] = 1; });
  /* ⚠️ PHẢI đi theo TỪNG KHỐI THÁNG (_csKhoi), đừng đọc từ hàng 3 tới cuối sheet.
     Mỗi khối tháng có 2 hàng tiêu đề riêng ("Họ và Tên" / "ID"); quét thẳng cả sheet là vớ luôn
     tiêu đề của khối thứ hai trở đi rồi hiện ra thành một "nhân viên" tên HỌ VÀ TÊN, mã ID.
     Anh Thắng bắt được ngay lần đầu bật tính năng này. */
  var out = [], thay = {};
  /* 🔴 03/08 (bản 4) — `NV_<cơ sở>` là NGUỒN DUY NHẤT của danh sách người.
     Anh Thắng: *"chỉ lấy trên sheet NV_ thôi, không lấy trên CS_. Nếu cần đối chiếu thì mình đối
     chiếu với NV_ luôn, sau đó trả phản hồi về trên đó là được"*.
     Trước đây chưa có `NV_` thì rơi về `CS_` — mà `CS_` đầy mã rác do máy tự sinh ("5", "Nhat",
     "19"…), lôi hết vào danh sách nhân sự. Nay KHÔNG rơi về `CS_` nữa: không có sheet `NV_` thì
     danh sách RỖNG và màn hình nói rõ phải tạo sheet, chứ không bịa ra người.
     `CS_` chỉ còn dùng để ĐỐI CHIẾU -> `thieuTrongNv`.
     🔴 DANH SÁCH LẤY TỪ `NV_<cơ sở>`, KHÔNG lấy từ `CS_`.
     Anh Thắng: *"danh [sách] lấy từ sheet NV_ chứ, còn sheet CS là được đẩy từ máy chấm công và
     chấm công online qua theo nhân viên đó… NV_ được phân quyền chấm công online thì họ chấm
     theo cơ sở đó là nó tự chèn qua sheet đó"*.
     Đúng chiều dữ liệu: `NV_` là DANH SÁCH NHÂN SỰ (anh khai tay), `CS_` là KẾT QUẢ chấm công do
     máy + chấm công online chèn vào. Lấy danh sách người từ `CS_` là lấy ngược từ kết quả.
     (Bản trước em gộp cả hai nguồn — sai chiều, đã bỏ.)

     ⚠️ Nhưng KHÔNG được im lặng bỏ qua người có chấm công mà thiếu tên trong `NV_`: họ vẫn đang
        đi làm thật. Không đưa vào danh sách chính, mà trả riêng ở `thieuTrongNv` để màn hình
        cảnh báo "bổ sung vào sheet NV_". Mất hút hẳn mới là kiểu sai nguy hiểm. */
  var _nvcs = _nvcsDoc(station);
  var coNv  = !!(_nvcs.co && !_nvcs.loi);
  if (coNv){
    _nvcs.list.forEach(function(x){
      var kk = _chuanMa(x.ma);
      thay[kk] = 1;                                  // có tên trong NV_ -> không phải "thiếu"
      if (daCo[kk]) return;                          // đã có hồ sơ rồi
      if (_tachMaNhiemVu(x.ma)) return;
      out.push({ employeeNo: x.ma, name: x.ten || '' });
    });
  }

  /* ⚠️ PHẢI đi theo TỪNG KHỐI THÁNG (_csKhoi), đừng đọc từ hàng 3 tới cuối sheet.
     Mỗi khối tháng có 2 hàng tiêu đề riêng ("Họ và Tên" / "ID"); quét thẳng cả sheet là vớ luôn
     tiêu đề của khối thứ hai trở đi rồi hiện ra thành một "nhân viên" tên HỌ VÀ TÊN, mã ID. */
  var thieu = [], daThieu = {}, daoCS = [];
  var sh = _sheet('CS_' + station);
  if (sh){
    _csKhoi(sh).forEach(function(k){
      if (!(k.r2 >= k.r1)) return;
      sh.getRange(k.r1, 1, k.r2 - k.r1 + 1, 2).getValues().forEach(function(r){
        var ten = String(r[0] || '').trim(), ma = String(r[1] || '').trim();
        if (!ma || !ten) return;                       // hàng trống giữa hai khối
        /* 🔴 `CS_` LÀ DỮ LIỆU MÁY CHẤM CÔNG — KHÔNG GHI, VÀ CŨNG KHÔNG TỰ ĐẢO.
           Anh Thắng: *"trên sheet là dữ liệu của máy chấm công, anh đảo cái đó nó sai"*.
           Máy ghi `Họ và Tên | ID` cố định. Hàng nào trông ngược là do người dán tay vào nhầm —
           BỎ QUA và báo, chứ đừng đoán hộ. Bản trước em hoán lúc đọc: nghe thì tiện, nhưng nó
           biến một hàng SAI thành một "nhân viên" trông rất hợp lý, rồi bấm Tạo hồ sơ là đẻ ra
           hồ sơ rác — đúng cái anh đang phải đi dọn. Thà không nhận còn hơn nhận nhầm. */
        if (_nvcsBiDao(ma, ten)) { daoCS.push({ ma:ten, ten:ma }); return; }
        if (_tachMaNhiemVu(ma)) return;                // hàng NHIỆM VỤ THÊM, không phải người mới
        if (_nvcsLaTieuDe(ma, ten)) return;            // hàng tiêu đề của khối tháng
        var kk = _chuanMa(ma);
        if (thay[kk]) return;                          // đã có trong NV_
        /* Đã có hồ sơ `NhanVien` thì KHÔNG báo ở đây: màn hình có dòng riêng "hồ sơ ghi cửa hàng
           này nhưng không có trong NV_". Báo cả hai chỗ là cùng một người bị nhắc hai lần, rồi
           anh không biết cái nào mới là việc cần làm. Ở đây chỉ còn mã HOÀN TOÀN LẠ. */
        if (daCo[kk]) return;
        if (daThieu[kk]) return;
        daThieu[kk] = 1;
        /* CÓ chấm công mà THIẾU tên trong `NV_` -> chỉ báo về, KHÔNG đưa vào danh sách.
           Chưa có sheet `NV_` thì mọi người trong `CS_` đều rơi vào đây — đúng: lúc đó màn hình
           phải nói "chưa có sheet NV_, tạo đi", chứ không lặng lẽ lấy mã rác của máy làm nhân sự. */
        thieu.push({ employeeNo: ma, name: ten });
      });
    });
  }
  /* Toàn bộ mã CÓ TÊN trong `NV_<cơ sở>` (kể cả người đã có hồ sơ). Bảng nhân viên cần cái này để
     hiểu: anh Thắng — *"khi 1 nhân viên nằm ở 2 sheet nhân viên (cùng ID) thì mình tự hiểu là bạn
     làm ở 2 cửa hàng, cho dù mở cửa hàng khác thì vẫn là thông tin của bạn nhân viên đó"*.
     Hồ sơ `NhanVien` chỉ có MỘT ô "Cửa hàng", nên người làm 2 nơi trước đây chỉ hiện ở một bên. */
  var dsMaNv = [];
  if (coNv) _nvcs.list.forEach(function(x){ if (!_tachMaNhiemVu(x.ma)) dsMaNv.push(x.ma); });

  /* Chưa có sheet `NV_` thì kèm danh sách các sheet `NV_` ĐANG CÓ — anh Thắng đặt tên
     `NV_VP_KH-HCM_HCM` (thừa `_HCM`) nên app tìm `NV_VP_KH-HCM` không thấy. Liệt kê ra là nhìn
     phát hiện ngay chỗ gõ thừa, thay vì ngồi đoán. */
  var dsSheetNv = [];
  if (!coNv) {
    try {
      SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(sh2){
        var t = sh2.getName();
        if (t.indexOf(NVCS_TIEN_TO) === 0) dsSheetNv.push(t);
      });
    } catch (e) {}
  }
  return { ok:true, station:station, list:out,
           coSheetNv: coNv,
           nguon: coNv ? 'NV_' : 'CS_',
           tenSheet: (coNv ? _nvcsTen(station) : ('CS_' + station)),
           /* 🔴 KHÔNG trả `daoCot` lên màn hình nữa (06/08/2026) — anh Thắng: *"loại bỏ cái này"*.
              `_nvcsDoc().daoCot` VẪN dựng, vì đó là bằng chứng phần ĐỌC nhận ra hàng lệch và hoán
              đúng; bộ kiểm bám vào nó. Chỉ là không ai bày ra màn hình nữa. */
           tenSheetCan: _nvcsTen(station),          // tên sheet NV_ app ĐANG tìm
           dsSheetNv: dsSheetNv,
           loiSheetNv: (_nvcs.co ? (_nvcs.loi || '') : ''),
           thieuTrongNv: thieu,
           /* Hàng trong sheet `NV_` bị dán ngược cột Mã ↔ Họ tên. App ĐÃ đọc đúng (xem
              `_nvcsBiDao`), đây chỉ để màn hình nói ra và mời sửa hẳn trong sheet. */
           /* Hàng khai TRÙNG mã trong sheet — app lấy dòng ĐẦU, mấy dòng sau là thừa. */
           trungMa: (_nvcs.trungMa || []),
           /* Hàng trong sheet MÁY (`CS_`) trông như dán ngược cột — app BỎ QUA, không đoán hộ. */
           daoCS: daoCS,
           dsMaNv: dsMaNv };
}

function getWorkSchedule(station, dates, pin){   // dates=['yyyy-MM-dd'..] -> {employees, jobs, assign:{empId:{date:[jobs]}}}
  var u = _requireAuth(pin);
  if(!u.isAdmin && !_canStation(u, station)) throw new Error('Không có quyền cơ sở này.');
  var sh=_lichSheet(), assign={};
  if(sh.getLastRow()>=2){
    var v=sh.getRange(2,1,sh.getLastRow()-1,6).getValues(), dset={};
    (dates||[]).forEach(function(d){ dset[d]=1; });
    v.forEach(function(r){
      if(String(r[0])!==String(station)) return;
      var day=(r[1] instanceof Date)?Utilities.formatDate(r[1],TZ,'yyyy-MM-dd'):String(r[1]);
      if(!dset[day]) return;
      var id=String(r[2]||''), ca=String(r[4]||''), jobs=String(r[5]||'').split(',').map(function(s){return s.trim();}).filter(Boolean);
      assign[id]=assign[id]||{}; assign[id][day]=assign[id][day]||{}; assign[id][day][ca]=jobs;
    });
  }
  return { station:station, jobs:_jobTypes(), shifts:_shiftsOf(station), wages:_wagesOf(station), employees:getStationEmployees(station,pin), assign:assign };
}
// ----- Lương/giờ theo CÔNG VIỆC, RIÊNG mỗi cơ sở -----
function _wageMap(){ var raw=PropertiesService.getScriptProperties().getProperty('WAGE_MAP'); if(raw){ try{ return JSON.parse(raw)||{}; }catch(e){} } return {}; }
function _wagesOf(station){ return _wageMap()[station] || {}; }   // {job: rate/giờ}
function setWages(pin, station, wages){
  var u=_requireAuth(pin);
  // Admin đặt lương mọi cơ sở; Quản lý/Cửa hàng trưởng đặt lương cho cơ sở mình quản lý.
  var can = u.isAdmin || ((u.role===ROLE.QUAN_LY || u.role===ROLE.CHT) && _canStation(u, station));
  if(!can) throw new Error('Chỉ Quản lý / Cửa hàng trưởng / Admin được đặt lương (và đúng cơ sở của mình).');
  var m=_wageMap(), clean={};
  Object.keys(wages||{}).forEach(function(j){ var r=Number(wages[j])||0; if(r>0) clean[j]=r; });
  m[station||'*']=clean; PropertiesService.getScriptProperties().setProperty('WAGE_MAP', JSON.stringify(m));
  return {ok:true};
}
// ----- Tính giờ & lương theo công việc: overlap(check-in/out, khung ca) -> giờ của ca -> công việc ca đó -----
function _minOfDay(t){ var p=String(t||'').split(':'); if(p.length<2) return null; var h=parseInt(p[0],10), m=parseInt(p[1],10); if(isNaN(h)||isNaN(m)) return null; return h*60+m; }
function _overlapMin(inM, outM, s, e){
  if(inM==null||outM==null||s==null||e==null||outM<=inM) return 0;
  function ov(a,b){ var lo=Math.max(inM,a), hi=Math.min(outM,b); return Math.max(0, hi-lo); }
  return (e>s) ? ov(s,e) : (ov(s,1440)+ov(0,e));   // e<=s: ca qua đêm
}
function _isWkendD(day){ var x=new Date(String(day)+'T00:00:00'); var g=x.getDay(); return g===0||g===6; }

/* ===========================================================================
 *  LƯƠNG NHÓM MÁY TỰ ĐỘNG (Posh, JP)  —  KHÔNG qua phân lịch   (03/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"không cần phân lịch làm của máy tự động bên này, mà tính lương
 *  theo cơ chế khác"*, và chốt cách tính:
 *    · hàng MÃ TRẦN  (Thu Tiền)  -> có ĐỦ giờ vào + giờ ra = **1 CÔNG**
 *    · hàng đuôi -TG (Trực Ghế)  -> **giờ ra − giờ vào**, tính theo GIỜ
 *    · ba mức giá: ngày thường · cuối tuần · ngày lễ
 *    · đơn giá khai CHUNG cho cả nhóm (Posh và JP dùng một bảng)
 *
 *  ⚠️ KHÔNG suy nhiệm vụ bằng "có đuôi hay không" cho xong. Người kiêm ĐÚNG MỘT
 *     việc thì không tách hàng, nên ai chỉ khai 'Trực Ghế' sẽ có hàng MÃ TRẦN mà
 *     vẫn phải tính theo GIỜ. Phải hỏi `_nhiemVuCuaHang` (mã + hồ sơ), nếu không
 *     người đó bị trả theo công — sai hẳn tiền lương.
 *  ⚠️ Đọc chấm công qua `getSheetData` (đường đọc DUY NHẤT, đã xử lý nhiều khối
 *     tháng). Tuyệt đối không viết bản đọc sheet thứ hai.
 * =========================================================================== */
var MTD_GIA_KEY = 'MTD_DON_GIA';
var MTD_LE_KEY  = 'MTD_NGAY_LE';
var MTD_GIA_O   = ['congThuong','congCuoiTuan','congLe','gioThuong','gioCuoiTuan','gioLe'];

/** Đơn giá nhóm Máy Tự Động. Chưa khai -> 0 hết (tiền ra 0, KHÔNG đoán bừa một con số). */
function _mtdGia(){
  var g = {}; MTD_GIA_O.forEach(function(k){ g[k] = 0; });
  var raw = PropertiesService.getScriptProperties().getProperty(MTD_GIA_KEY);
  if (raw){ try { var o = JSON.parse(raw) || {};
    MTD_GIA_O.forEach(function(k){ var n = Number(o[k]); if (isFinite(n) && n > 0) g[k] = n; });
  } catch(e){} }
  return g;
}
/** Ngày lễ: nhận 'yyyy-MM-dd' (một lần) hoặc 'MM-dd' (lặp hằng năm, cho lễ dương lịch). */
function _mtdNgayLe(){
  var raw = PropertiesService.getScriptProperties().getProperty(MTD_LE_KEY);
  if (!raw) return [];
  try { var a = JSON.parse(raw); return Array.isArray(a) ? a : []; } catch(e){ return []; }
}
function _mtdLaLe(ngay, dsLe){
  var d = String(ngay || '').trim(); if (d.length < 10) return false;
  var md = d.slice(5);                                  // 'MM-dd'
  for (var i = 0; i < (dsLe || []).length; i++){
    var x = String(dsLe[i] || '').trim();
    if (x === d || (x.length === 5 && x === md)) return true;
  }
  return false;
}
/** 'le' | 'cuoiTuan' | 'thuong' — lễ ĐÈ cuối tuần (lễ rơi vào chủ nhật thì ăn giá lễ). */
function _mtdLoaiNgay(ngay, dsLe){
  if (_mtdLaLe(ngay, dsLe)) return 'le';
  return _isWkendD(ngay) ? 'cuoiTuan' : 'thuong';
}
/* ===========================================================================
 *  CƠ SỞ TÍNH THEO GIỜ  (07/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng: *"thu tiền mặc định tính công theo Posh theo công rồi, vậy vệ sinh ghế tính theo
 *  tiếng — anh tạo luôn 1 sheet, xong cho cơ sở phụ vào, các bạn chấm vào đó luôn cho an toàn,
 *  gọn hơn"*.
 *
 *  Trước đây "theo giờ hay theo công" do **nhiệm vụ của từng người** quyết định, mà nhiệm vụ lại
 *  nằm trong `NV_<cơ sở>` — đúng cái sheet anh sắp xoá. Nay thêm đường thứ hai: đánh dấu CẢ CƠ SỞ
 *  là "tính theo giờ", thì mọi dòng chấm trong sheet đó tính theo tiếng, **không cần khai nhiệm vụ
 *  cho ai cả**. Tạo `CS_VE_SINH_GHE`, tích ô này, cho vào Cơ sở phụ của ai làm — xong.
 *
 *  🔴 GIỮ luôn đường cũ (`nhiệm vụ = Trực Ghế Posh - JP`). Bỏ đi là mọi người đang khai nhiệm vụ
 *     đó lập tức bị tính theo CÔNG — sai tiền ngay tháng này, mà không có gì báo. Hai đường cùng
 *     tồn tại, `theoGio` chỉ cần MỘT trong hai là đúng.
 *  ⚠️ Khoá lưu là tên cơ sở đã chuẩn hoá (`_khongDau`) — "Ve Sinh Ghe" và "VE_SINH_GHE" gõ kiểu
 *     nào cũng phải trúng, không thì tích rồi mà lương vẫn tính theo công.
 * =========================================================================== */
var MTD_CSGIO_KEY = 'MTD_CO_SO_THEO_GIO';
/* Nhớ trong MỘT lượt chạy. `_nhomCoSo` gọi `_coSoTinhTheoGio`, mà `_nhomTheoCoSo` lại gọi
   `_nhomCoSo` cho TỪNG sheet — không nhớ thì mỗi bảng là vài chục lượt đọc Script Properties. */
var _MTD_CSGIO_LUOT = null;
function _mtdCsGio(){
  if (_MTD_CSGIO_LUOT) return _MTD_CSGIO_LUOT;
  var raw = PropertiesService.getScriptProperties().getProperty(MTD_CSGIO_KEY);
  var ra = [];
  if (raw){ try { var a = JSON.parse(raw); if (Array.isArray(a)) ra = a; } catch(e){} }
  _MTD_CSGIO_LUOT = ra;
  return ra;
}
/** Cơ sở này có tính theo GIỜ không (mọi dòng, không xét nhiệm vụ từng người). */
function _coSoTinhTheoGio(station){
  var k = _khongDau(String(station || '').replace(/^CS_/, '').trim()); if (!k) return false;
  var ds = _mtdCsGio();
  for (var i = 0; i < ds.length; i++) if (_khongDau(ds[i]) === k) return true;
  return false;
}
function getGiaMayTuDong(pin){
  var u = _requireAuth(pin);
  var mgr = !!u.isAdmin || u.role === ROLE.QUAN_LY || u.role === ROLE.CHT;
  /* Danh sách cơ sở để anh tích — chỉ cơ sở CÓ sheet `CS_`, khỏi tích vào một cái tên không tồn
     tại rồi ngồi đợi lương đổi. */
  var dsCoSo = [];
  try {
    SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(s){
      var n = s.getName(); if (n.indexOf('CS_') === 0) dsCoSo.push(n.substring(3));
    });
    dsCoSo.sort();
  } catch(e){}
  return { ok:true, gia:_mtdGia(), ngayLe:_mtdNgayLe(), suaDuoc:mgr, nhomTen:'Nhóm Máy Tự Động',
           coSoTheoGio:_mtdCsGio(), dsCoSo:dsCoSo };
}
function setGiaMayTuDong(pin, gia, dsLe, csGio){
  var u = _requireAuth(pin);
  if (!(u.isAdmin || u.role === ROLE.QUAN_LY))
    return { ok:false, error:'Chỉ Admin / Quản lý được đặt đơn giá.' };
  var sach = {};
  MTD_GIA_O.forEach(function(k){ var n = Number((gia || {})[k]); sach[k] = (isFinite(n) && n > 0) ? n : 0; });
  /* ⚠️ Chỉ nhận 'yyyy-MM-dd' hoặc 'MM-dd'. Gõ sai định dạng mà vẫn lưu thì ngày đó âm thầm
     KHÔNG được tính giá lễ — sai tiền mà không có gì báo. */
  var le = [], xau = [];
  (dsLe || []).forEach(function(x){
    var s = String(x || '').trim(); if (!s) return;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s) || /^\d{2}-\d{2}$/.test(s)){ if (le.indexOf(s) < 0) le.push(s); }
    else xau.push(s);
  });
  if (xau.length) return { ok:false, error:'Ngày lễ sai định dạng: ' + xau.join(', ')
                                  + '. Dùng yyyy-MM-dd (một lần) hoặc MM-dd (lặp hằng năm).' };
  le.sort();
  /* Cơ sở tính theo GIỜ. `undefined` = màn hình cũ không gửi lên -> GIỮ NGUYÊN cấu hình đang có.
     Nhận `[]` thì mới xoá hết. Không phân biệt hai ca này là mỗi lần Lưu đơn giá từ một màn hình
     cũ hơn sẽ âm thầm xoá sạch danh sách, và lương cả cơ sở đó nhảy về tính theo công. */
  var csg = null;
  if (csGio !== undefined && csGio !== null){
    if (!Array.isArray(csGio)) return { ok:false, error:'Danh sách cơ sở theo giờ không hợp lệ.' };
    var coSheet = {}, la = [];
    try {
      SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(s){
        var n = s.getName(); if (n.indexOf('CS_') === 0) coSheet[_khongDau(n.substring(3))] = n.substring(3);
      });
    } catch(e){}
    csg = [];
    csGio.forEach(function(x){
      var t = String(x || '').replace(/^CS_/, '').trim(); if (!t) return;
      /* 🔴 Chỉ nhận cơ sở CÓ sheet `CS_`. Gõ nhầm tên thì tích xong lương vẫn tính theo công mà
         màn hình vẫn hiện dấu tích — sai im lặng, đúng trên tiền. */
      var that = coSheet[_khongDau(t)];
      if (!that) { la.push(t); return; }
      if (csg.indexOf(that) < 0) csg.push(that);
    });
    if (la.length) return { ok:false, error:'Không có sheet CS_ cho: ' + la.join(', ') + '.' };
  }
  var p = PropertiesService.getScriptProperties();
  p.setProperty(MTD_GIA_KEY, JSON.stringify(sach));
  p.setProperty(MTD_LE_KEY,  JSON.stringify(le));
  if (csg) { p.setProperty(MTD_CSGIO_KEY, JSON.stringify(csg)); _MTD_CSGIO_LUOT = null; }
  return { ok:true, gia:sach, ngayLe:le, coSoTheoGio:(csg || _mtdCsGio()) };
}

/** Bảng lương tháng của MỘT cơ sở nhóm Máy Tự Động. Gộp theo NGƯỜI (mã gốc). */
function getLuongMayTuDong(pin, station, monthLabel){
  var u = _requireAuth(pin);
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!u.isAdmin && !_canStation(u, station)) return { error:'Không có quyền cơ sở này.' };
  if (!_laMayTuDong(station))
    return { error:'Cơ sở "' + station + '" không thuộc Nhóm Máy Tự Động — dùng bảng Giờ & Lương theo ca.' };
  return _mtdTinhLuong(station, monthLabel);
}

/**
 * PHẦN TÍNH THUẦN của lương Nhóm Máy Tự Động — không pin, không cổng quyền. Tách khỏi
 * `getLuongMayTuDong` để app LƯƠNG (09/08/2026, chỉ Admin/Kế toán qua `loginLuong`, quản lý CẢ
 * CHUỖI — không theo `_canStation` như Chấm công) gọi trực tiếp, không phải nới `_canStation`
 * ngay trong `getLuongMayTuDong` — nới ở đó là cho MỌI người gọi hàm này vượt qua cơ sở của họ.
 */
function _mtdTinhLuong(station, monthLabel){
  var mt = String(monthLabel || '').replace('Tháng ', '').split('-');
  var mm = mt.length === 2 ? parseInt(mt[0], 10) : 0, yyyy = mt.length === 2 ? parseInt(mt[1], 10) : 0;
  if (!mm || !yyyy) return { error:'Tháng không hợp lệ' };
  var prefix = yyyy + '-' + ('0' + mm).slice(-2);

  var gia = _mtdGia(), dsLe = _mtdNgayLe(), hoSo = _bangNhiemVuCoSo(station);
  var csTheoGio = _coSoTinhTheoGio(station);   // đọc MỘT lần, không hỏi lại trong vòng lặp
  /* Truyền `prefix` (yyyy-MM): hàm này vốn ĐÃ bỏ mọi tháng khác ngay dòng dưới, nên đọc cả
     sheet là đọc thừa. Kết quả không đổi một số nào. */
  var att = _docSheetData('CS_' + station, prefix);
  var byEmp = {}, thuTu = [], detail = [];
  (att.rows || []).forEach(function(row){
    var day = String(row[1] || ''); if (day.indexOf(prefix) !== 0) return;
    var ma = String(row[2] || '').trim(); if (!ma) return;
    var inT = row[4], outT = row[6];
    var vaoM = _minOfDay(inT), raM = _minOfDay(outT);
    if (vaoM == null || raM == null) return;             // thiếu vào hoặc ra -> không tính

    var t = _tachMaNhiemVu(ma), maGoc = t ? t.ma : ma;
    var nv = _nhiemVuCuaHang(ma, hoSo[_chuanMa(maGoc)] || []);
    /* HAI đường vào "tính theo giờ", chỉ cần MỘT đúng:
       · cả cơ sở được đánh dấu theo giờ (sheet riêng cho vệ sinh ghế — 07/08/2026);
       · hoặc chính người đó khai nhiệm vụ Trực Ghế (đường cũ, GIỮ để không đổi tiền tháng này). */
    var theoGio = csTheoGio || (nv === 'Trực Ghế Posh - JP');
    var loai = _mtdLoaiNgay(day, dsLe);
    var hoa = loai.charAt(0).toUpperCase() + loai.slice(1);   // thuong -> Thuong

    var e = byEmp[maGoc];
    if (!e){
      e = byEmp[maGoc] = { ma:maGoc, ten:String(row[3] || maGoc),
                           cong:{thuong:0,cuoiTuan:0,le:0}, gio:{thuong:0,cuoiTuan:0,le:0},
                           tienCong:0, tienGio:0, tong:0 };
      thuTu.push(maGoc);
    }
    var soLuong, donGia;
    if (theoGio){
      /* Ca qua đêm (ra < vào) thì cộng trọn một vòng 24h — đúng cách `_overlapMin` đang hiểu.
         Không xử thì ra số ÂM, trừ thẳng vào lương người ta. */
      var phut = (raM > vaoM) ? (raM - vaoM) : (raM + 1440 - vaoM);
      soLuong = Math.round(phut / 60 * 100) / 100;
      donGia  = gia['gio' + hoa] || 0;
      e.gio[loai] += soLuong;
      e.tienGio   += Math.round(soLuong * donGia);
    } else {
      soLuong = 1;                                     // đủ vào + ra = 1 công, không xét dài ngắn
      donGia  = gia['cong' + hoa] || 0;
      e.cong[loai] += 1;
      e.tienCong   += Math.round(donGia);
    }
    e.tong = e.tienCong + e.tienGio;
    detail.push({ date:day, ma:ma, ten:e.ten, nhiemVu:nv, loaiNgay:loai,
                  vao:String(inT || ''), ra:String(outT || ''),
                  theoGio:theoGio, soLuong:soLuong, donGia:donGia,
                  tien: Math.round(soLuong * donGia) });
  });

  var rows = thuTu.map(function(k){ return byEmp[k]; })
                  .sort(function(a, b){ return a.ten < b.ten ? -1 : (a.ten > b.ten ? 1 : 0); });
  detail.sort(function(a, b){ return a.date < b.date ? -1 : (a.date > b.date ? 1 : (a.ten < b.ten ? -1 : 1)); });
  var tong = { tienCong:0, tienGio:0, tong:0, soCong:0, soGio:0 };
  rows.forEach(function(r){
    tong.tienCong += r.tienCong; tong.tienGio += r.tienGio; tong.tong += r.tong;
    tong.soCong += r.cong.thuong + r.cong.cuoiTuan + r.cong.le;
    tong.soGio  += r.gio.thuong  + r.gio.cuoiTuan  + r.gio.le;
  });
  tong.soGio = Math.round(tong.soGio * 100) / 100;
  var chuaKhaiGia = MTD_GIA_O.every(function(k){ return !gia[k]; });
  return { station:station, month:monthLabel, gia:gia, ngayLe:dsLe,
           rows:rows, detail:detail, tong:tong, chuaKhaiGia:chuaKhaiGia };
}
/* ===========================================================================
 *  LƯƠNG BỘ PHẬN VĂN PHÒNG — công ngày + nửa công tối + công đêm (04/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh Thắng chốt 4 quy tắc cho `VP_KH-HCM`:
 *    1. Máy chấm công 08:30–17:00 -> CÔNG NGÀY. Đủ 7–9 tiếng = 1 công.
 *    2. Riêng KẾ TOÁN văn phòng: thứ 7 chỉ làm 08:30–12:00, VẪN tính 1 công.
 *    3. Chấm công online 17:00–22:00 -> 1/2 công, CỘNG VÀO công ngày hôm đó.
 *    4. Chấm công online 22:00–06:00 hôm sau -> 1 CÔNG ĐÊM, nằm ở HÀNG RIÊNG.
 *       Hôm đó nếu còn làm ca ngày thì tổng 2 công, không làm thì 1 công.
 *
 *  ⚠️ VÌ SAO PHẢI TÁCH HÀNG RIÊNG — không phải cho đẹp:
 *     `_ghiGioVaoRa` gộp mọi lượt trong ngày thành CẶP [sớm nhất, muộn nhất] trên MỘT hàng.
 *     Nên nếu ca tối/đêm ghi chung hàng với ca ngày thì hàng đó thành "vào 08:30 – ra 21:00",
 *     và KHÔNG CÁCH NÀO biết đó là "làm liền 12 tiếng" hay "làm ca ngày + ca tối". Tách hàng
 *     thì mỗi hàng giữ đúng một ca, tính được từng ca.
 *
 *  ⚠️ DỮ LIỆU CŨ (trước bản này) không có hàng tách -> suy từ hàng chính bằng phần GIAO NHAU
 *     với từng khung giờ, và ĐÁNH DẤU `suyDoan`. Số suy đoán KHÔNG chắc: người quên chấm ra lúc
 *     17:00 rồi chấm online lúc 21:00 sẽ trông y như người làm thật ca tối. Giao diện phải hiện
 *     dấu này, đừng để anh Thắng trả tiền theo một con số mà app tự đoán.
 * =========================================================================== */
var VP_CFG_KEY = 'VP_CONG_CFG';
/* Mọi ngưỡng đều là CẤU HÌNH, không phải số chết trong code: quy tắc lương hay đổi, mà mỗi lần
   đổi lại phải sửa code + deploy là chậm và dễ sai. Sửa ở app thì có hiệu lực ngay. */
var VP_CFG_MAC_DINH = {
  ngayTu:'08:30', ngayDen:'17:00',   // khung ca ngày (chấm ở HÀNG 1)
  ngayMin:7, ngayMax:9,              // đủ 7–9 tiếng trong khung -> 1 công
  duoiMin:'tyle',                    // dưới ngayMin: 'tyle' | 'nua' | 'tron' | 'khong'
  gioChuan:8,                        // giờ quy 1 công (dùng khi duoiMin='tyle')
  /* Mốc bậc thang — chỉ dùng khi duoiMin='bacthang'. Để thành ô cấu hình chứ không phải số chết:
     mốc lương hay đổi.
     🔴 `bacMot` = 9 CHỨ KHÔNG PHẢI 8, anh Thắng chốt 06/08. Anh đọc luật ra là "<8h 1 công", nhưng
        ca ngày chuẩn 08:30–17:00 dài 8.5 tiếng — với mốc 8 thì NGÀY LÀM BÌNH THƯỜNG rơi vào bậc
        "<12h" = 1.5 công, tức lương CẢ CƠ SỞ tăng 50%, không riêng người thiếu giờ. Anh xem bảng
        đối chiếu rồi chốt: *"đổi 9 = chỉ người thiếu giờ mới nâng"*.
        ⚠️ Mốc này phải LỚN HƠN độ dài ca ngày chuẩn. Đổi khung `ngayTu`/`ngayDen` dài ra thì phải
           nâng mốc này theo — ô cảnh báo đỏ trên màn hình tự tính lại và nhắc. */
  bacNua:4, bacMot:9, bacRuoi:12,
  /* Ca đêm phải đủ bao nhiêu giờ mới tính 1 công đêm. Anh Thắng: *"từ 22h00-6h00 tối thiểu 3
     tiếng (1 công đêm)"*. ĐỂ 0 = như cũ (chỉ cần 1 giờ) để không đổi ngầm lương ai — anh đặt 3
     khi đã xem bảng đối chiếu. */
  demToiThieuGio:0,
  nuaTuGio:4,                        // dùng khi duoiMin='nua': ≥ số giờ này -> 0.5 công
  /* Lượt bấm trong `graceRaPhut` phút sau `ngayDen` mà HÀNG 1 chưa có giờ ra thì hiểu là TAN LÀM
     ca ngày. Không có ô này thì người tan làm bấm 17:05 bị đẩy sang hàng 2 -> hàng 1 thiếu giờ ra
     -> MẤT TRỌN 1 công ngày. */
  graceRaPhut:60,
  /* KẾ TOÁN văn phòng: thứ 7 làm nửa ngày vẫn 1 công · CHỦ NHẬT NGHỈ (anh Thắng 04/08).
     Bộ phận khác: làm ngày nào tính công ngày đó, không có lịch cố định. */
  ktThu7Tu:'08:30', ktThu7Den:'12:00', ktThu7Min:3,
  ktMaNV:[],                         // mã NV thuộc KẾ TOÁN văn phòng
  ktChuNhatNghi:true,
  /* ------ HÀNG 2 (`<mã>-CD`) — anh Thắng 04/08: "chỉ cần 1 giờ cũng đủ xác định" ------
     Giờ ở hàng 2 TỪ `demTu` trở đi (hoặc trước `demDen`) -> CA ĐÊM.
     Giờ ở hàng 2 trong khoảng [ngayDen, demTu) -> TĂNG CA -> `tangCaCong` cho ngày đó.
     ⚠️ `demTu` = 21:00 vì anh giải thích *"nhân viên có thể làm ca đêm nhưng check in sớm"* —
        21:30 là check-in sớm của ca đêm, KHÔNG phải tăng ca. Câu trước đó anh viết "trước 22h00"
        nên nếu ý anh là 22:00 thì đổi Ô NÀY, không phải sửa code. */
  demTu:'21:00', demDen:'06:00',
  /* Ca đêm được TỔNG 2 CÔNG, cả hai ghi cho NGÀY HÔM SAU (anh Thắng chốt):
     `demCong` = công của ca đêm · `demCongBu` = 1 công cho ngày hôm sau (nghỉ bù).
     ⚠️ Ngày hôm sau mà VẪN đi làm ca ngày thì được CỘNG THÊM -> hôm đó 3 công. Đúng nguyên văn
        "+1 công cho ngày sau đó". Muốn "đi làm rồi thì không cộng bù" thì đặt `demBuKhiDaLam` = 0. */
  demCong:1, demCongBu:1, demBuKhiDaLam:1,
  tangCaCong:0.5,
  /* Số ngày công CHUẨN của tháng — mẫu số quy lương tháng ra tiền một công
     (anh Thắng 06/08: *"ngày nghỉ kệ, chỉ cần xác định số ngày công của tháng"*).
     ⚠️ 0 = CHƯA KHAI: bảng hiện "—" và báo thiếu, KHÔNG tự đoán 26. Đoán mẫu số là sai tiền của
        MỌI người cùng lúc, mà bảng vẫn có số nên chẳng ai nghi. */
  ngayCongThang:0
};

/**
 * Trộn một bộ cấu hình THÔ với MẶC ĐỊNH rồi chuẩn hoá.
 * Dùng chung cho cả bản ĐÃ LƯU lẫn bản ĐANG THỬ trên màn hình — để bảng đối chiếu "xem trước"
 * và bảng công "sau khi lưu" không bao giờ ra hai con số khác nhau.
 */
function _vpTron(o){
  var c = {}; Object.keys(VP_CFG_MAC_DINH).forEach(function(k){ c[k] = VP_CFG_MAC_DINH[k]; });
  o = o || {};
  Object.keys(VP_CFG_MAC_DINH).forEach(function(k){
    if (o[k] === undefined || o[k] === null || o[k] === '') return;
    c[k] = o[k];
  });
  c.ktMaNV = (Array.isArray(c.ktMaNV) ? c.ktMaNV : String(c.ktMaNV || '').split(','))
             .map(function(x){ return _chuanMa(x); }).filter(function(x){ return x !== ''; });
  return c;
}
function _vpCfg(){
  var raw = PropertiesService.getScriptProperties().getProperty(VP_CFG_KEY);
  var o = {}; if (raw){ try { o = JSON.parse(raw) || {}; } catch(e){} }
  return _vpTron(o);
}
function _vpLaVanPhong(station){
  station = String(station || '').replace(/^CS_/, '').trim();
  return _bpMap()[station] === 'Văn phòng';
}
/** 0=CN … 6=T7. KHÔNG dùng `_isWkendD` — hàm đó gộp T7 với CN, mà hai ngày này luật khác nhau. */
function _vpThu(ngay){ return new Date(String(ngay) + 'T00:00:00').getDay(); }
function _vpLaThu7(ngay){ return _vpThu(ngay) === 6; }
function _vpLaChuNhat(ngay){ return _vpThu(ngay) === 0; }
function _vpNgayTruoc(ngay){ return _vpDoiNgay(ngay, -1); }
function _vpNgaySau(ngay){ return _vpDoiNgay(ngay, 1); }
function _vpDoiNgay(ngay, buoc){
  var d = new Date(String(ngay) + 'T12:00:00');   // 12:00 để đổi ngày không bị lệch múi giờ
  d.setDate(d.getDate() + buoc);
  return Utilities.formatDate(d, TZ, 'yyyy-MM-dd');
}
/**
 * Số công của ca NGÀY từ số phút làm trong khung.
 * `min` = số giờ tối thiểu để được trọn 1 công (ngày thường 7h, kế toán thứ 7 là 3h).
 * ⚠️ Trên ngayMax vẫn là 1 công — anh Thắng chỉ nói "đủ từ 7-9 tiếng là 1 công", không nói làm quá
 *    9 tiếng thì được thêm. Muốn tính tăng ca thì khai thêm quy tắc, đừng đoán ở đây.
 */
function _vpCongNgayTuPhut(phut, min, cfg){
  if (phut == null || phut <= 0) return 0;
  var gio = phut / 60;

  /* 🔴 06/08/2026 — BẬC THANG, theo anh Thắng: *"làm dưới 4h (1/2 công), dưới 8h (1 công),
   * dưới 12h (1.5 công)"*.
   * ⚠️ KHÁC HẲN 'tyle' đang chạy: 'tyle' chia `giờ ÷ 8` nên 6 tiếng ra 0.75 công, còn bậc thang
   *    cho tròn 1 công. Đổi kiểu tính là LƯƠNG NHIỀU NGƯỜI TĂNG. Vì vậy đây là MỘT LỰA CHỌN
   *    THÊM trong ô "Làm thiếu giờ thì tính sao", KHÔNG phải mặc định — anh phải tự chọn sau khi
   *    xem bảng đối chiếu số cũ / số mới. Không đổi ngầm lương của ai.
   * ⚠️ Bậc thang xét TRƯỚC chốt `gio >= min`, vì mốc 12h nằm TRÊN `min` (7–9 tiếng) — để sau thì
   *    người làm 10 tiếng bị chốt kia trả 1 công và bậc 1.5 không bao giờ chạm tới. */
  if (cfg.duoiMin === 'bacthang'){
    var b1 = Number(cfg.bacNua  || 4);      // dưới mốc này -> 1/2 công
    var b2 = Number(cfg.bacMot  || 8);      // dưới mốc này -> 1 công
    var b3 = Number(cfg.bacRuoi || 12);     // dưới mốc này -> 1.5 công
    if (gio < b1) return 0.5;
    if (gio < b2) return 1;
    if (gio < b3) return 1.5;
    return 1.5;                             // từ b3 trở lên: giữ trần 1.5, không thưởng thêm
  }

  if (gio >= min) return 1;
  if (cfg.duoiMin === 'tron')  return 1;
  if (cfg.duoiMin === 'khong') return 0;
  if (cfg.duoiMin === 'nua')   return (gio >= Number(cfg.nuaTuGio || 4)) ? 0.5 : 0;
  var g = Number(cfg.gioChuan || 8); if (!(g > 0)) g = 8;
  return Math.round((gio / g) * 100) / 100;                 // 'tyle'
}
/**
 * Phút làm của một hàng {vao, ra} nằm trong khung [tu, den].
 * ⚠️ KHÔNG dùng `_overlapMin`: hàm đó chỉ đúng khi giờ vào/ra trong 0..1440, còn ca vắt qua nửa đêm
 *    phải cộng 1440 vào giờ ra -> quá 1440 là nó tính sai (đã thử: ra 120 phút thay vì 480). Ở đây
 *    "trải phẳng" cả ca lẫn khung trên một trục rồi lấy giao nhau — một công thức cho mọi khung.
 */
function _vpPhutTrongKhung(hang, tu, den){
  if (!hang) return 0;
  var v = _minOfDay(hang.vao), r = _minOfDay(hang.ra);
  var w1 = _minOfDay(tu), w2 = _minOfDay(den);
  if (v == null || r == null || w1 == null || w2 == null) return 0;
  if (r <= v)  r  = r  + 1440;      // ca vắt qua nửa đêm
  if (w2 <= w1) w2 = w2 + 1440;     // khung vắt qua nửa đêm (21:00–06:00)
  return Math.max(0, Math.min(r, w2) - Math.max(v, w1));
}
/**
 * HÀNG 2 (`-CD`) là ca gì? Anh Thắng: *"chỉ cần 1 giờ cũng đủ xác định"* — nên KHÔNG đòi đủ cặp
 * vào/ra, có một giờ là kết luận được.
 *   'dem'    : có giờ từ `demTu` trở đi, HOẶC có giờ trước `demDen` (sau nửa đêm)
 *   'tangca' : mọi giờ đều nằm trong [ngayDen, demTu)
 *   ''       : không có giờ nào
 *   'la'     : có giờ nhưng nằm TRONG ca ngày (< ngayDen) -> KHÔNG tính, để anh soi
 * ⚠️ Trả 'la' thay vì tính bừa: giờ ca ngày lọt vào hàng 2 là dấu hiệu sửa tay hoặc chấm sai chỗ.
 *    Tính thành tăng ca là tự cộng tiền cho một cái sai.
 */
function _vpCaHang2(cfg, hang2){
  if (!hang2) return { loai:'', gio:[] };
  var gio = [hang2.vao, hang2.ra].filter(function(g){ return _minOfDay(g) != null; });
  if (!gio.length) return { loai:'', gio:[] };
  var demTu = _minOfDay(cfg.demTu), demDen = _minOfDay(cfg.demDen), ngayDen = _minOfDay(cfg.ngayDen);
  var coDem = gio.some(function(g){ var m = _minOfDay(g); return m >= demTu || m < demDen; });
  if (coDem) return { loai:'dem', gio:gio };
  var trongCaNgay = gio.some(function(g){ return _minOfDay(g) < ngayDen; });
  return { loai: trongCaNgay ? 'la' : 'tangca', gio:gio };
}
/**
 * Công CẢ THÁNG của MỘT người. Hàm THUẦN — không đọc sheet, không đọc Script Property.
 *
 * ⚠️ VÌ SAO PHẢI TÍNH THEO THÁNG, không tính từng ngày rời:
 *    ca đêm ở hàng 2 ngày D cho công vào NGÀY D+1 (anh Thắng chốt), nên một ngày có thể nhận công
 *    từ ngày HÔM TRƯỚC. Tính từng ngày độc lập là không bao giờ ra đúng.
 *
 * `theoNgay` = { 'yyyy-MM-dd': { chinh:{vao,ra}, dem:{vao,ra} } }  (dem = hàng `-CD`)
 * Trả { 'yyyy-MM-dd': {…} } cho MỌI ngày có công, kể cả ngày chỉ nhận công đêm từ hôm trước.
 */
function _vpTinhNguoi(cfg, laKeToan, theoNgay){
  var out = {};
  function o(ngay){
    if (!out[ngay]) out[ngay] = { ngay:ngay, congNgay:0, congTangCa:0, congDem:0, congBu:0, tong:0,
                                  phutNgay:0, khung:'', kt7:false, ktCnNghi:false, caLa:false,
                                  demTuNgay:'', demSangNgay:'', gioDem:[],
                                  demThieuGio:false, demChuaDuCap:false, gioDemThuc:0 };
    return out[ngay];
  }
  Object.keys(theoNgay).sort().forEach(function(ngay){
    var h = theoNgay[ngay] || {}, r = o(ngay);

    /* ----- HÀNG 1: công ngày ----- */
    var kt7 = !!(laKeToan && _vpLaThu7(ngay));
    var ktCn = !!(laKeToan && cfg.ktChuNhatNghi && _vpLaChuNhat(ngay));
    var tu  = kt7 ? cfg.ktThu7Tu  : cfg.ngayTu;
    var den = kt7 ? cfg.ktThu7Den : cfg.ngayDen;
    var min = kt7 ? Number(cfg.ktThu7Min) : Number(cfg.ngayMin);
    r.kt7 = kt7; r.ktCnNghi = ktCn; r.khung = tu + '-' + den;
    r.phutNgay = _vpPhutTrongKhung(h.chinh, tu, den);
    /* Kế toán CHỦ NHẬT: lịch nghỉ -> 0 công ngày. Vẫn giữ số phút để giao diện hiện được
       "đi làm chủ nhật nhưng chủ nhật là ngày nghỉ" cho anh tự quyết, chứ không xoá dấu vết. */
    r.congNgay = ktCn ? 0 : _vpCongNgayTuPhut(r.phutNgay, min, cfg);

    /* ----- HÀNG 2: tăng ca (cùng ngày) hoặc ca đêm (dồn sang NGÀY HÔM SAU) ----- */
    var ca = _vpCaHang2(cfg, h.dem);
    if (ca.loai === 'tangca'){
      r.congTangCa += Number(cfg.tangCaCong);
    } else if (ca.loai === 'la'){
      r.caLa = true;
    } else if (ca.loai === 'dem'){
      /* 🔴 06/08/2026 — NGƯỠNG GIỜ TỐI THIỂU của ca đêm. Anh Thắng: *"từ 22h00-6h00 tối thiểu 3
       * tiếng (1 công đêm)"*. `demToiThieuGio` = 0 nghĩa là KHÔNG xét (y như trước bản này), để
       * bật ngưỡng không âm thầm cắt công của ai — anh đặt số sau khi xem bảng đối chiếu. */
      var toiThieu = Number(cfg.demToiThieuGio || 0);
      var duCap = !!(h.dem && _minOfDay(h.dem.vao) != null && _minOfDay(h.dem.ra) != null);
      r.gioDemThuc = duCap ? Math.round(_vpPhutTrongKhung(h.dem, cfg.demTu, cfg.demDen) / 60 * 100) / 100 : 0;
      /* ⚠️ CHỈ CÓ MỘT GIỜ (quên chấm ra) thì không cách nào biết ca dài bao lâu. KHÔNG được lấy cớ
         đó để cắt công — cắt ngầm là trừ tiền một người vì cái máy chấm công lỗi. Vẫn tính, nhưng
         đánh dấu `demChuaDuCap` để anh Thắng tự soi. */
      r.demChuaDuCap = (toiThieu > 0 && !duCap);
      r.demThieuGio  = (toiThieu > 0 && duCap && r.gioDemThuc < toiThieu);
      r.gioDem = ca.gio.slice();
      if (!r.demThieuGio){
        var sau = _vpNgaySau(ngay);
        var s = o(sau);
        s.congDem += Number(cfg.demCong);
        s.demTuNgay = ngay;
        s.gioDem = ca.gio.slice();
        /* GIỮ LẠI ngày bắt đầu ca đêm dù nó 0 công. Xoá đi thì trên bảng anh Thắng chỉ thấy ngày hôm
           sau tự nhiên có 2 công mà KHÔNG BIẾT TỪ ĐÂU RA — không soi được là không kiểm được lương. */
        r.demSangNgay = sau;
      }
    }
  });

  /* Công BÙ cho ngày hôm sau — làm sau cùng vì cần biết ngày đó có công ngày chưa. */
  Object.keys(out).forEach(function(ngay){
    var r = out[ngay];
    if (!r.congDem) return;
    var daLam = (r.congNgay > 0 || r.congTangCa > 0);
    if (!daLam || Number(cfg.demBuKhiDaLam)) r.congBu = Number(cfg.demCongBu);
  });
  Object.keys(out).forEach(function(ngay){
    var r = out[ngay];
    r.tong = Math.round((r.congNgay + r.congTangCa + r.congDem + r.congBu) * 100) / 100;
    /* `demThieuGio` cũng phải GIỮ dòng lại: đó là ngày người ta CÓ đi làm đêm mà không được công.
       Xoá đi thì ca đêm bị loại biến mất khỏi bảng, không ai biết mà kiểm. */
    if (r.tong <= 0 && !r.caLa && !r.demSangNgay && !r.demThieuGio
        && !(r.ktCnNghi && r.phutNgay > 0)) delete out[ngay];
  });
  return out;
}

/**
 * MỘT NGÀY LÀM BÌNH THƯỜNG (đúng khung ca ngày) dài mấy tiếng, và theo BẬC THANG thì ra mấy công.
 *
 * 🔴 Có hàm này vì mốc anh Thắng đọc ra gây một hệ quả rất dễ sốc: khung chuẩn 08:30–17:00 là
 *    **8.5 tiếng**, mà 8.5 nằm giữa mốc `bacMot`(8) và `bacRuoi`(12) -> rơi vào bậc **1.5 công**.
 *    Tức là NGÀY LÀM BÌNH THƯỜNG cũng thành 1.5 công, lương tăng 50% cho TẤT CẢ mọi người, không
 *    riêng người làm thiếu giờ. Phải nói thẳng ra màn hình TRƯỚC khi anh bấm Lưu.
 */
function _vpCaChuan(cfg){
  var phut = _vpPhutTrongKhung({ vao:cfg.ngayTu, ra:cfg.ngayDen }, cfg.ngayTu, cfg.ngayDen);
  return { gio: Math.round(phut / 60 * 100) / 100,
           cong: _vpCongNgayTuPhut(phut, Number(cfg.ngayMin), { duoiMin:'bacthang',
                   bacNua:cfg.bacNua, bacMot:cfg.bacMot, bacRuoi:cfg.bacRuoi }) };
}
/** Cấu hình cách tính công Văn phòng — cho giao diện đọc. */
function getCauHinhVanPhong(pin){
  var u = _requireAuth(pin);
  var mgr = !!(u.isAdmin || u.role === ROLE.QUAN_LY);
  var c = _vpCfg();
  return { ok:true, cfg:c, macDinh:VP_CFG_MAC_DINH, suaDuoc:mgr,
           duoiToi:VP_DUOI_TOI, duoiDem:VP_DUOI_DEM, caChuan:_vpCaChuan(c) };
}
/**
 * Kiểm + chuẩn hoá một bộ cấu hình THÔ từ màn hình. Trả `{ok, cfg}` hoặc `{ok:false, error}`.
 *
 * ⚠️ TÁCH RA LÀM HÀM RIÊNG để bảng ĐỐI CHIẾU (xem trước, chưa lưu) và nút LƯU dùng CHUNG một bộ
 *    luật. Hai đường kiểm khác nhau thì bảng đối chiếu hứa một số, lưu xong ra số khác — mà đây
 *    là số để trả lương.
 */
function _vpKiemCfg(cfg){
  var c = {}, loi = [];
  var gio = ['ngayTu','ngayDen','ktThu7Tu','ktThu7Den','toiTu','toiDen','demTu','demDen'];
  var so  = ['ngayMin','ngayMax','gioChuan','nuaTuGio','ktThu7Min','toiCong','toiToiThieuPhut','demCong',
             'bacNua','bacMot','bacRuoi','demToiThieuGio','ngayCongThang'];
  gio.forEach(function(k){
    var v = String((cfg || {})[k] == null ? '' : (cfg || {})[k]).trim();
    if (!v) { c[k] = VP_CFG_MAC_DINH[k]; return; }
    /* ⚠️ Giờ sai định dạng mà vẫn lưu thì `_minOfDay` trả null -> khung giờ đó thành 0 phút ->
       công biến mất ÂM THẦM. Chặn ngay lúc lưu. */
    if (!/^\d{1,2}:\d{2}$/.test(v) || _minOfDay(v) == null) { loi.push(k + ' phải dạng HH:mm'); return; }
    c[k] = v;
  });
  so.forEach(function(k){
    var raw = (cfg || {})[k];
    if (raw === '' || raw == null) { c[k] = VP_CFG_MAC_DINH[k]; return; }
    var n = Number(raw);
    if (!isFinite(n) || n < 0) { loi.push(k + ' phải là số ≥ 0'); return; }
    c[k] = n;
  });
  var dm = String((cfg || {}).duoiMin || VP_CFG_MAC_DINH.duoiMin);
  /* ⚠️ 'bacthang' PHẢI có trong danh sách này. Bản trước thêm lựa chọn Bậc thang lên màn hình mà
     quên chỗ kiểm -> chọn xong bấm Lưu là báo "duoiMin không hợp lệ", tính năng không dùng được
     một lần nào. Thêm lựa chọn ở giao diện thì luôn thêm ở ĐÂY. */
  if (['tyle','nua','tron','khong','bacthang'].indexOf(dm) < 0) loi.push('duoiMin không hợp lệ');
  c.duoiMin = dm;
  /* Bậc thang phải TĂNG DẦN. Gõ 8 / 4 / 12 thì chốt `<4` không bao giờ chạm tới, người làm 6 tiếng
     ăn 0.5 công thay vì 1 — sai tiền mà bảng vẫn ra số đẹp. */
  if (dm === 'bacthang' && !(Number(c.bacNua) < Number(c.bacMot) && Number(c.bacMot) < Number(c.bacRuoi)))
    loi.push('Bậc thang phải tăng dần: nửa công < 1 công < 1.5 công (đang là '
             + c.bacNua + ' / ' + c.bacMot + ' / ' + c.bacRuoi + ')');
  var kt = (cfg || {}).ktMaNV;
  kt = (Array.isArray(kt) ? kt : String(kt || '').split(','))
       .map(function(x){ return String(x || '').trim(); }).filter(function(x){ return x !== ''; });
  /* Mã kế toán phải là mã NGƯỜI, không phải hàng tách — gõ 'NV01-CD' vào đây là quy tắc thứ 7
     bám vào hàng công đêm, sai hẳn. */
  var xau = kt.filter(function(x){ return !!_tachMaNhiemVu(x); });
  if (xau.length) loi.push('Mã kế toán không được là mã hàng tách: ' + xau.join(', '));
  c.ktMaNV = kt;
  if (loi.length) return { ok:false, error:loi.join(' · ') };
  return { ok:true, cfg:c };
}
function setCauHinhVanPhong(pin, cfg){
  var u = _requireAuth(pin);
  if (!(u.isAdmin || u.role === ROLE.QUAN_LY))
    return { ok:false, error:'Chỉ Admin / Quản lý được đổi cách tính công.' };
  var k = _vpKiemCfg(cfg);
  if (!k.ok) return k;
  PropertiesService.getScriptProperties().setProperty(VP_CFG_KEY, JSON.stringify(k.cfg));
  return { ok:true, cfg:_vpCfg() };
}

/** 'Tháng 08-2026' | '2026-08' -> { prefix:'2026-08' } · sai khuôn -> null. */
function _vpThangPrefix(monthLabel){
  var mt = String(monthLabel || '').replace('Tháng ', '').split('-');
  var mm = mt.length === 2 ? parseInt(mt[0], 10) : 0, yyyy = mt.length === 2 ? parseInt(mt[1], 10) : 0;
  if (!mm || !yyyy || mm < 1 || mm > 12) return null;
  return yyyy + '-' + ('0' + mm).slice(-2);
}
/**
 * ĐỌC dữ liệu chấm công một tháng của cơ sở Văn phòng, gom theo người.
 * Trả `{ theoMa, ten, thuTu, thangTruoc }`. THUẦN ĐỌC — không tính công, không đụng cấu hình.
 *
 * ⚠️ Tách khỏi phần TÍNH vì bảng đối chiếu phải chạy HAI cách tính trên CÙNG MỘT lần đọc. Đọc hai
 *    lần thì vừa chậm gấp đôi, vừa có nguy cơ hai bảng đọc hai bản dữ liệu khác nhau (ai đó chấm
 *    công xen vào giữa) — lúc đó cột "chênh" là chênh do dữ liệu, không phải do cách tính.
 */
function _vpDocThang(station, prefix){
  var cuoiTruoc = _vpNgayTruoc(prefix + '-01');            // ngày cuối tháng TRƯỚC
  var theoMa = {}, ten = {}, thuTu = [];
  function nap(rows, chiLayNgay, chiHang2){
    (rows || []).forEach(function(row){
      var ngay = String(row[1] || '');
      if (chiLayNgay && ngay !== chiLayNgay) return;
      var maO = String(row[2] || '').trim(); if (!maO) return;
      /* 🔴 07/08/2026 — NHẬN HÀNG TĂNG CƯỜNG `-TC`.
         Anh Thắng: *"còn hàng công đêm CD anh chưa thấy, đối với cơ sở khác có TG nữa nhé"*.
         Dò ra: hàng `-TC` trước đây rơi vào nhánh `else return` nên bị BỎ HẲN — người tăng cường
         sang cơ sở Văn phòng làm cả tháng mà bảng công KHÔNG có tên họ, không một dòng cảnh báo.
         Mất công là mất lương. Mà chính anh đã ghi ý định ngay trong khai báo `VP_DUOI_NHAN`:
         *"bên sheet CS thì sẽ hiện tên nhân viên đó kèm ID có hậu tố… Vì bên CS phải có để chấm
         công và tính lương."* Nên đây là LỖI, không phải lựa chọn thiết kế.

         Tách hai hậu tố chồng nhau: `B02-TC` (tăng cường, ca ngày) và `B02-TC-CD` (tăng cường,
         ca đêm). `_tachMaNhiemVu` chỉ bóc được MỘT lớp nên phải bóc hai lượt. */
      var tangCuong = false, maLoc = maO;
      var t1 = _tachMaNhiemVu(maLoc);
      var tDem = (t1 && t1.duoi === VP_DUOI_DEM) ? t1 : null;
      if (tDem) maLoc = tDem.ma;                             // bỏ '-CD', còn lại có thể là '…-TC'
      var t2 = _tachMaNhiemVu(maLoc);
      if (t2 && t2.duoi === DUOI_TANG_CUONG){ tangCuong = true; maLoc = t2.ma; }

      var t = tDem || t2;
      var maGoc = maLoc;
      var loai;
      if (tDem) loai = 'dem';
      else if (!t2) loai = 'chinh';
      else if (tangCuong) loai = 'chinh';                    // '-TC' là ca NGÀY của người tăng cường
      else return;                                           // hàng nhiệm vụ nhóm khác -> bỏ
      if (chiHang2 && loai !== 'dem') return;
      /* Khoá RIÊNG cho người tăng cường: họ và người cùng mã ở cơ sở gốc là HAI dòng công khác
         nhau. Gộp chung khoá là cộng công của hai nơi vào một dòng — sai lương theo hướng thừa. */
      var k = _chuanMa(maGoc) + (tangCuong ? '|TC' : '');
      if (!ten[k]) { ten[k] = String(row[3] || maGoc).replace(/\s*[·|—].*$/, '').trim() || maGoc; thuTu.push(k); }
      theoMa[k] = theoMa[k] || { ma:maGoc, tangCuong:tangCuong, ngay:{} };
      var d = theoMa[k].ngay[ngay] = theoMa[k].ngay[ngay] || {};
      d[loai] = { vao:String(row[4] || ''), ra:String(row[6] || '') };
    });
  }
  nap(_docSheetData('CS_' + station, prefix).rows, '', false);
  /* ⚠️ Ca đêm ở hàng 2 ngày D cho công vào ngày D+1. Nên bảng tháng này còn thiếu ca đêm của NGÀY
     CUỐI THÁNG TRƯỚC — đêm 31/07 phải vào công ngày 01/08. Không đọc thêm khối tháng trước là mỗi
     tháng mất một đêm mà không ai thấy. */
  try { nap(_docSheetData('CS_' + station, cuoiTruoc.slice(0, 7)).rows, cuoiTruoc, true); } catch(e){}
  return { theoMa:theoMa, ten:ten, thuTu:thuTu,
           thangTruoc:'Tháng ' + cuoiTruoc.slice(5, 7) + '-' + cuoiTruoc.slice(0, 4) };
}
/**
 * TÍNH bảng công tháng từ dữ liệu `_vpDocThang` đã đọc + MỘT bộ cấu hình.
 * Hàm THUẦN — cùng đầu vào luôn ra cùng số, chạy được bao nhiêu lần tuỳ ý. Đây là điều kiện để
 * bảng đối chiếu chạy hai bộ cấu hình mà tin được kết quả.
 */
function _vpBangCong(cfg, doc, prefix){
  var rows = [], detail = [];
  doc.thuTu.forEach(function(k){
    var laKt = cfg.ktMaNV.indexOf(k) >= 0;
    var ngayCong = _vpTinhNguoi(cfg, laKt, doc.theoMa[k].ngay);
    var e = { ma:doc.theoMa[k].ma, ten:doc.ten[k] || doc.theoMa[k].ma, laKeToan:laKt,
              /* Cờ này đi thẳng ra giao diện: hàng `-TC` phải hiện rõ là NGƯỜI CƠ SỞ KHÁC sang
                 làm thêm, không thì nhìn như nhân viên cơ sở này mà sheet NV_ lại không có tên. */
              tangCuong:!!doc.theoMa[k].tangCuong,
              congNgay:0, congTangCa:0, congDem:0, congBu:0, tong:0, soNgay:0, caLa:0, cnNghi:0,
              demThieuGio:0, demChuaDuCap:0 };
    Object.keys(ngayCong).sort().forEach(function(ngay){
      /* Chỉ lấy ngày THUỘC THÁNG đang xem: công đêm của 31/07 đã dồn sang 01/08 nên nằm trong
         tháng 8; ngược lại đêm 31/08 dồn sang 01/09 -> KHÔNG được tính vào tháng 8. */
      if (ngay.indexOf(prefix) !== 0) return;
      var r = ngayCong[ngay];
      e.congNgay += r.congNgay; e.congTangCa += r.congTangCa;
      e.congDem  += r.congDem;  e.congBu     += r.congBu;
      if (r.tong > 0) e.soNgay++;
      if (r.caLa) e.caLa++;
      if (r.ktCnNghi && r.phutNgay > 0) e.cnNghi++;
      if (r.demThieuGio) e.demThieuGio++;
      if (r.demChuaDuCap) e.demChuaDuCap++;
      var h = (doc.theoMa[k].ngay[ngay] || {});
      detail.push({ ngay:ngay, ma:e.ma, ten:e.ten, tangCuong:e.tangCuong,
                    thu:_vpThu(ngay), kt7:r.kt7, ktCnNghi:r.ktCnNghi,
                    khung:r.khung, caLa:r.caLa,
                    vao:(h.chinh ? h.chinh.vao : ''), ra:(h.chinh ? h.chinh.ra : ''),
                    h2vao:(h.dem ? h.dem.vao : ''), h2ra:(h.dem ? h.dem.ra : ''),
                    gioNgay:Math.round(r.phutNgay / 60 * 100) / 100,
                    congNgay:r.congNgay, congTangCa:r.congTangCa, congDem:r.congDem, congBu:r.congBu,
                    tong:r.tong, demTuNgay:r.demTuNgay, demSangNgay:r.demSangNgay,
                    gioDem:r.gioDem,
                    demThieuGio:r.demThieuGio, demChuaDuCap:r.demChuaDuCap, gioDemThuc:r.gioDemThuc });
    });
    ['congNgay','congTangCa','congDem','congBu'].forEach(function(f){ e[f] = Math.round(e[f] * 100) / 100; });
    e.tong = Math.round((e.congNgay + e.congTangCa + e.congDem + e.congBu) * 100) / 100;
    if (e.tong > 0 || e.caLa || e.cnNghi || e.demThieuGio) rows.push(e);
  });
  rows.sort(function(a, b){ return a.ten < b.ten ? -1 : (a.ten > b.ten ? 1 : 0); });
  detail.sort(function(a, b){ return a.ngay < b.ngay ? -1 : (a.ngay > b.ngay ? 1 : (a.ten < b.ten ? -1 : 1)); });
  var tong = { congNgay:0, congTangCa:0, congDem:0, congBu:0, tong:0, caLa:0, cnNghi:0,
               demThieuGio:0, demChuaDuCap:0 };
  rows.forEach(function(e){ Object.keys(tong).forEach(function(f){ tong[f] += (e[f] || 0); }); });
  Object.keys(tong).forEach(function(f){ tong[f] = Math.round(tong[f] * 100) / 100; });
  return { rows:rows, detail:detail, tong:tong };
}

/* ---------------------------------------------------------------------------
 *  LƯƠNG VĂN PHÒNG = (Lương cơ bản tháng ÷ số ngày công của tháng) × số công
 * ------------------------------------------------------------------------ */
/**
 * Đọc một ô tiền cho ra SỐ. Ô "Lương cơ bản" trong `NhanVien` khi thì là số, khi thì là chữ
 * ("8.000.000", "8,000,000", "8.000.000 đ") vì anh Thắng gõ tay / dán từ nơi khác.
 * ⚠️ KHÔNG được `replace(/[^\d]/g,'')` cho gọn: "8.000.000" ra 8000000 thì may, nhưng "8.5" (triệu)
 *    ra 85. Và KHÔNG được `Number('8.000.000')` -> NaN -> 0 -> người đó lương 0 mà bảng vẫn đẹp.
 *    Phân biệt dấu NGÀN với dấu THẬP PHÂN bằng khuôn, không đoán.
 */
function _vpSoTien(v){
  if (typeof v === 'number') return isFinite(v) ? v : 0;
  var s = String(v == null ? '' : v).trim();
  if (!s) return 0;
  s = s.replace(/[^\d.,-]/g, '');                       // bỏ 'đ', 'VND', khoảng trắng
  if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');        // 8.000.000  (kiểu VN)
  else if (/^-?\d{1,3}(,\d{3})+(\.\d+)?$/.test(s)) s = s.replace(/,/g, '');  // 8,000,000.5 (kiểu Anh)
  else s = s.replace(/,/g, '.');                        // '8,5' -> 8.5
  var n = Number(s);
  return isFinite(n) ? n : 0;
}
/** Mã NV (đã `_chuanMa`) -> Lương cơ bản tháng. Đọc `NhanVien` MỘT lượt. */
function _vpLuongCoBan(){
  var out = {}, sh = _nvSheet();
  if (!sh || sh.getLastRow() < 2) return out;
  var iL = NV_HEADERS.indexOf('Lương cơ bản');
  if (iL < 0) return out;
  sh.getRange(2, 1, sh.getLastRow() - 1, NV_HEADERS.length).getValues().forEach(function(r){
    var ma = _chuanMa(r[0]); if (!ma) return;
    out[ma] = _vpSoTien(r[iL]);
  });
  return out;
}
/**
 * Gắn tiền vào từng dòng người của bảng công.
 *   đơn giá 1 công = Lương cơ bản THÁNG ÷ `ngayCongThang`
 *   tiền           = Lương cơ bản × tổng công ÷ `ngayCongThang`
 *
 * ⚠️ NHÂN TRƯỚC RỒI MỚI CHIA. Làm tròn đơn giá trước rồi nhân thì 26 công lệch tới vài trăm đồng
 *    so với đúng một tháng lương — mỗi tháng lệch một ít, không ai đối chiếu ra.
 * ⚠️ Chưa khai `ngayCongThang` (=0) hoặc chưa có lương cơ bản -> để 0 và BÁO, tuyệt đối không
 *    mượn tạm một mẫu số nào cho bảng có số.
 */
/* ===========================================================================
 *  SỐ NGÀY CÔNG — LƯU THEO TỪNG THÁNG × TỪNG CƠ SỞ   (anh Thắng chốt 07/08/2026)
 * ---------------------------------------------------------------------------
 *  Anh: *"theo từng tháng, với theo bộ; chỉ cần bổ sung ô đó nhập trước, sau mình sẽ dùng
 *  công thức sau"*. Nên ở đây CHỈ có chỗ nhập tay + chỗ lưu; phần tự suy ra số ngày công
 *  (trừ chủ nhật, trừ lễ…) để sau, và khi làm thì chỉ thay chỗ GỢI Ý, không thay chỗ lưu.
 *
 *  🔴 VÌ SAO PHẢI BỎ ô `cfg.ngayCongThang` cũ khỏi đường tính tiền:
 *     ô cũ là MỘT giá trị trong Script Property, dùng chung mọi tháng mọi cơ sở. Khai 26 cho
 *     tháng 8, chi tiền xong; sang tháng 9 sửa thành 25; mở lại tháng 8 thì bảng tính theo 25 —
 *     KHÁC số đã trả, mà không để lại dấu vết nào. Số ngày công vốn khác nhau theo tháng (tháng
 *     2 ít ngày, tháng có lễ khác tháng không lễ) nên chuyện sửa lại là chắc chắn xảy ra.
 *
 *  🔴 VÌ SAO DÙNG SHEET chứ không Script Property: một property tối đa 9KB. Mỗi cơ sở mỗi tháng
 *     một khoá (~25 byte) → khoảng 3KB/năm với 10 cơ sở, chạm trần sau vài năm rồi HỎNG IM LẶNG
 *     giữa lúc đang chạy. Sheet thì không có trần đó, lại cho anh Thắng xem/sửa trực tiếp và giữ
 *     được ai khai - khai lúc nào (hệ thống tiền thật thì dấu vết đáng giá hơn sự gọn).
 *
 *  🔴 KHÔNG có giá trị mặc định ngầm: tháng nào chưa khai thì cột tiền để `—` và BÁO, đúng như
 *     luật đang có. Có "mượn tạm" số của tháng khác là quay lại đúng cái bẫy hồi tố ở trên.
 *     Số của tháng gần nhất chỉ được dùng để ĐIỀN SẴN vào ô nhập (gợi ý), phải bấm Lưu mới tính.
 */
var SH_VP_NGAY_CONG = 'VP_NgayCong';
var VP_NC_H = ['Cơ sở', 'Tháng', 'Số ngày công', 'Người khai', 'Cập nhật'];

function _vpNcSheet(){
  var ss = SpreadsheetApp.getActiveSpreadsheet(), sh = ss.getSheetByName(SH_VP_NGAY_CONG);
  if (!sh){
    sh = ss.insertSheet(SH_VP_NGAY_CONG);
    sh.getRange(1, 1, 1, VP_NC_H.length).setValues([VP_NC_H]).setFontWeight('bold');
    sh.setFrozenRows(1);
  }
  return sh;
}
/** Khoá tra cứu. Cơ sở bỏ dấu + bỏ tiền tố CS_ để anh gõ tay trong sheet không bị lệch vì dấu. */
function _vpNcKhoa(coSo, thang){
  return _khongDau(String(coSo || '').replace(/^CS_/, '').trim()) + '|' + String(thang || '').trim();
}
/**
 * Đọc TOÀN BỘ bảng số ngày công MỘT lượt -> map khoá -> {so, nguoi, luc, hang}.
 * ⚠️ Một lượt `getValues`, không đọc từng ô: mỗi lượt gọi Sheets tốn 20–50ms, và hàm này nằm
 *    trên đường tính lương nên đừng để nó thành chỗ chậm mới.
 * ⚠️ Trùng khoá thì DÒNG DƯỚI THẮNG — anh sửa tay trong sheet hay thêm dòng mới ở cuối đều ra
 *    ý "đây là số mới nhất". Lấy dòng đầu thì sửa xong không thấy đổi, tưởng app hỏng.
 */
function _vpNcMap(){
  var sh = _vpNcSheet(), out = {};
  if (sh.getLastRow() < 2) return out;
  var v = sh.getRange(2, 1, sh.getLastRow() - 1, VP_NC_H.length).getDisplayValues();
  for (var i = 0; i < v.length; i++){
    var cs = String(v[i][0] || '').trim(), th = String(v[i][1] || '').trim();
    if (!cs || !th) continue;
    var so = _vpSoTien(v[i][2]);            // dùng lại bộ đọc số đã chịu được "26", " 26 ", "26,0"
    out[_vpNcKhoa(cs, th)] = { so:so, nguoi:String(v[i][3] || ''), luc:String(v[i][4] || ''),
                               hang:i + 2 };
  }
  return out;
}
/** Số ngày công đã khai cho (cơ sở, tháng). Chưa khai -> 0, KHÔNG mượn của tháng khác. */
function _vpNcLay(map, coSo, thang){
  var o = map[_vpNcKhoa(coSo, thang)];
  return o && o.so > 0 ? o.so : 0;
}
/**
 * GỢI Ý cho ô nhập: số của tháng GẦN NHẤT đã khai TẠI CHÍNH CƠ SỞ ĐÓ.
 * Chỉ để điền sẵn vào ô cho đỡ gõ — máy chủ KHÔNG dùng số này để tính tiền.
 */
function _vpNcGoiY(map, coSo, thang){
  var k = _khongDau(String(coSo || '').replace(/^CS_/, '').trim()) + '|';
  var tot = null;
  Object.keys(map).forEach(function(key){
    if (key.indexOf(k) !== 0) return;
    var th = key.slice(k.length);
    if (th >= thang) return;                                   // chỉ lấy tháng TRƯỚC tháng đang xem
    if (!tot || th > tot.thang) tot = { thang:th, so:map[key].so };
  });
  return tot && tot.so > 0 ? tot : null;
}
/**
 * Khai số ngày công cho MỘT cơ sở × MỘT tháng.
 * Gác bằng `_canSalary` vì con số này là MẪU SỐ CỦA LƯƠNG — sai một đơn vị là cả cơ sở lệch tiền.
 */
function setNgayCongThang(pin, coSo, thang, so){
  var u = _requireAuth(pin);
  if (!_canSalary(u)) return { ok:false, error:'Chỉ Admin / Quản lý khai được số ngày công (đây là mẫu số của lương).' };
  coSo = String(coSo || '').replace(/^CS_/, '').trim();
  thang = String(thang || '').trim();
  if (!coSo) return { ok:false, error:'Thiếu cơ sở.' };
  if (!/^\d{4}-\d{2}$/.test(thang)) return { ok:false, error:'Tháng phải dạng YYYY-MM.' };
  if (!u.isAdmin && !_canStation(u, coSo)) return { ok:false, error:'Không có quyền cơ sở này.' };
  if (!_vpLaVanPhong(coSo)) return { ok:false, error:'Cơ sở "' + coSo + '" không thuộc bộ phận Văn phòng.' };
  var n = _vpSoTien(so);
  /* Chặn số vô lý NGAY, đừng để nó thành mẫu số. 31 là trần cứng của một tháng; gõ nhầm 226 thì
     lương chia cho 226 ra gần bằng 0 mà bảng vẫn "có số" trông rất thật. */
  if (!(n > 0)) return { ok:false, error:'Số ngày công phải lớn hơn 0.' };
  if (n > 31)   return { ok:false, error:'Số ngày công không thể quá 31 (đang nhập ' + n + ').' };
  var lock = LockService.getScriptLock();
  try { lock.waitLock(15000); } catch(e){}
  try {
    var sh = _vpNcSheet(), map = _vpNcMap(), cu = map[_vpNcKhoa(coSo, thang)];
    var hang = [coSo, thang, n, (u.name || u.role || ''), _now()];
    if (cu) sh.getRange(cu.hang, 1, 1, VP_NC_H.length).setValues([hang]);
    else    sh.appendRow(hang);
    return { ok:true, coSo:coSo, thang:thang, so:n, truoc:(cu ? cu.so : 0),
             ghiChu:(cu ? 'Đã sửa từ ' + cu.so + ' thành ' + n + ' — tiền tháng này tính lại theo số mới.'
                        : 'Đã khai ' + n + ' ngày công cho ' + coSo + ' tháng ' + thang + '.') };
  } finally { try { lock.releaseLock(); } catch(e){} }
}

function _vpGanTien(rows, cfg, luong, ncNgoai){
  /* `ncNgoai` = số ngày công của ĐÚNG tháng × cơ sở đang xem. Chỉ khi không truyền (bảng đối
     chiếu cách tính, nơi hai bên phải dùng CÙNG một mẫu số) mới rơi về ô cấu hình cũ. */
  var nc = (ncNgoai === undefined || ncNgoai === null)
             ? Number(cfg.ngayCongThang || 0) : Number(ncNgoai || 0);
  var thieuLuong = [];
  rows.forEach(function(e){
    var lcb = Number(luong[_chuanMa(e.ma)] || 0);
    e.luongThang = lcb;
    e.donGiaCong = (nc > 0 && lcb > 0) ? Math.round(lcb / nc) : 0;
    e.tien       = (nc > 0 && lcb > 0) ? Math.round(lcb * e.tong / nc) : 0;
    if (!lcb) thieuLuong.push(e.ten + ' (' + e.ma + ')');
  });
  return { ngayCongThang:nc, chuaKhaiNgayCong:!(nc > 0), thieuLuong:thieuLuong,
           tongTien:rows.reduce(function(s, e){ return s + (e.tien || 0); }, 0) };
}

/**
 * Bảng công tháng của MỘT cơ sở bộ phận Văn phòng.
 * Gom theo NGƯỜI (mã gốc), mỗi ngày một dòng chi tiết.
 * ⚠️ CHỈ ĐỌC — không ghi gì vào sheet, nên bật/tắt không ảnh hưởng dữ liệu.
 */
function getCongVanPhong(pin, station, monthLabel){
  var u = _requireAuth(pin);
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!u.isAdmin && !_canStation(u, station)) return { error:'Không có quyền cơ sở này.' };
  if (!_vpLaVanPhong(station))
    return { error:'Cơ sở "' + station + '" không thuộc bộ phận Văn phòng. Xếp bộ phận ở tab Bộ phận trước.' };
  var r = _vpBangCongVaLuong(station, monthLabel);
  if (r.error) return r;
  /* Tiền chỉ cho người được xem lương. CHT xem được bảng CÔNG của cơ sở mình nhưng không phải
     ai cũng được thấy lương từng người — `_canSalary` là chốt đang dùng cho mọi chỗ khác.
     Khối ô nhập trên giao diện cần đúng ba thứ này. `ncKhaiDuoc` để biết có bày ô nhập hay chỉ
     bày con số — CHT xem được bảng công nhưng không khai được mẫu số lương. */
  var xemLuong = _canSalary(u);
  return { station:r.station, month:r.month, cfg:r.cfg, rows:r.rows, detail:r.detail, tong:r.tong,
           thangTruoc:r.thangTruoc, chuaKhaiKeToan:r.chuaKhaiKeToan,
           xemLuong:xemLuong, tien:(xemLuong ? r.tien : null),
           ncThang:r.ncThang, ncSo:r.ncSo, ncKhaiDuoc:xemLuong, ncGoiY:r.ncGoiY };
}

/**
 * PHẦN TÍNH THUẦN của bảng công + lương Văn phòng — không pin, không cổng quyền, LUÔN kèm tiền.
 * Tách khỏi `getCongVanPhong` để app LƯƠNG (09/08/2026, chỉ Admin/Kế toán qua `loginLuong`,
 * quản lý CẢ CHUỖI — không theo `_canStation` như Chấm công) gọi trực tiếp, không phải nới
 * `_canStation`/`_canSalary` ngay trong `getCongVanPhong` — nới ở đó là cho MỌI người gọi hàm
 * này vượt qua cơ sở của họ, sai với luật "chỉ thấy cơ sở mình" đang có của Chấm công.
 */
function _vpBangCongVaLuong(station, monthLabel){
  var prefix = _vpThangPrefix(monthLabel);
  if (!prefix) return { error:'Tháng không hợp lệ' };
  var cfg = _vpCfg();
  var doc = _vpDocThang(station, prefix);
  var b = _vpBangCong(cfg, doc, prefix);
  /* Số ngày công lấy theo ĐÚNG (cơ sở, tháng) đang xem. Đọc bảng một lượt rồi dùng cho cả phần
     tính tiền lẫn phần gợi ý — đừng đọc hai lần cho cùng một thứ. */
  var ncMap = _vpNcMap(), nc = _vpNcLay(ncMap, station, prefix);
  var tien = _vpGanTien(b.rows, cfg, _vpLuongCoBan(), nc);
  return { station:station, month:monthLabel, cfg:cfg, rows:b.rows, detail:b.detail, tong:b.tong,
           thangTruoc:doc.thangTruoc, chuaKhaiKeToan:(cfg.ktMaNV.length === 0), tien:tien,
           ncThang:prefix, ncSo:nc, ncGoiY:(nc > 0 ? null : _vpNcGoiY(ncMap, station, prefix)) };
}

/**
 * BẢNG ĐỐI CHIẾU: số công / số tiền theo cách tính ĐANG LƯU so với cách tính ĐANG THỬ.
 *
 * Anh Thắng 06/08: *"em ra bảng phân tích để anh chọn ngày công phù hợp trên wed"*.
 * ⚠️ CHỈ ĐỌC TUYỆT ĐỐI — không ghi Script Property, không ghi sheet. Xem bảng này KHÔNG đổi lương
 *    của ai; muốn đổi thật thì bấm Lưu ở khối cấu hình.
 * ⚠️ Hai bảng chạy trên CÙNG MỘT lần đọc `_vpDocThang`, nên cột "chênh" chỉ có thể do CÁCH TÍNH.
 */
function soSanhCachTinhCong(pin, station, monthLabel, cfgThu){
  var u = _requireAuth(pin);
  if (!(u.isAdmin || u.role === ROLE.QUAN_LY))
    return { error:'Chỉ Admin / Quản lý xem được bảng đối chiếu.' };
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!u.isAdmin && !_canStation(u, station)) return { error:'Không có quyền cơ sở này.' };
  if (!_vpLaVanPhong(station))
    return { error:'Cơ sở "' + station + '" không thuộc bộ phận Văn phòng.' };
  var prefix = _vpThangPrefix(monthLabel);
  if (!prefix) return { error:'Tháng không hợp lệ' };

  var cfgCu = _vpCfg();
  /* Dùng CHUNG bộ kiểm với nút Lưu: bảng hứa số nào thì lưu xong phải ra đúng số đó. */
  var k = _vpKiemCfg(cfgThu);
  if (!k.ok) return { error:'Cấu hình thử không hợp lệ: ' + k.error };
  var cfgMoi = _vpTron(k.cfg);

  var doc = _vpDocThang(station, prefix);
  var A = _vpBangCong(cfgCu,  doc, prefix);
  var B = _vpBangCong(cfgMoi, doc, prefix);
  var luong = _vpLuongCoBan();
  /* 🔴 CẢ HAI bên dùng CÙNG một mẫu số — và phải là mẫu số THẬT của tháng đang xem, không phải ô
     cấu hình cũ. Hai lý do, cái sau nặng hơn:
       · cột "chênh tiền" chỉ được sinh ra bởi CÁCH TÍNH; hai mẫu số khác nhau là chênh giả;
       · nếu lấy `cfg.ngayCongThang` cũ thì bảng hứa một số tiền, mà bảng công thật lại chia theo
         số của tháng -> anh Thắng quyết định dựa trên con số không có thật ở đâu cả. */
  var ncSs = _vpNcLay(_vpNcMap(), station, prefix);
  var tA = _vpGanTien(A.rows, cfgCu,  luong, ncSs);
  var tB = _vpGanTien(B.rows, cfgMoi, luong, ncSs);

  /* Ghép theo MÃ, không theo vị trí: đổi ngưỡng có thể làm một người rơi khỏi bảng này mà còn ở
     bảng kia (tổng công về 0). Ghép theo thứ tự là dồn nhầm số của người này sang người khác. */
  var map = {}, thuTu = [];
  function nhan(ds, ben){
    ds.forEach(function(e){
      /* Kèm cờ tăng cường vào khoá ghép: cùng một mã có thể có HAI dòng công (hàng chính và
         hàng `-TC`). Ghép chung là dồn số của hai dòng vào một -> cột "chênh" thành vô nghĩa. */
      var k2 = _chuanMa(e.ma) + (e.tangCuong ? '|TC' : '');
      if (!map[k2]) { map[k2] = { ma:e.ma, ten:e.ten, cu:null, moi:null }; thuTu.push(k2); }
      map[k2][ben] = e;
    });
  }
  nhan(A.rows, 'cu'); nhan(B.rows, 'moi');
  var so = function(e, f){ return e ? Number(e[f] || 0) : 0; };
  var lam = function(n){ return Math.round(n * 100) / 100; };
  var rows = thuTu.map(function(k2){
    var x = map[k2];
    return { ma:x.ma, ten:x.ten,
             congCu:so(x.cu, 'tong'),  congMoi:so(x.moi, 'tong'),
             chenhCong:lam(so(x.moi, 'tong') - so(x.cu, 'tong')),
             tienCu:so(x.cu, 'tien'),  tienMoi:so(x.moi, 'tien'),
             chenhTien:so(x.moi, 'tien') - so(x.cu, 'tien'),
             luongThang:so(x.moi, 'luongThang') || so(x.cu, 'luongThang'),
             ngayCu:so(x.cu, 'soNgay'), ngayMoi:so(x.moi, 'soNgay'),
             demBoCu:so(x.cu, 'demThieuGio'), demBoMoi:so(x.moi, 'demThieuGio') };
  }).sort(function(a, b){ return a.ten < b.ten ? -1 : (a.ten > b.ten ? 1 : 0); });

  var tong = { congCu:lam(A.tong.tong), congMoi:lam(B.tong.tong),
               chenhCong:lam(B.tong.tong - A.tong.tong),
               tienCu:tA.tongTien, tienMoi:tB.tongTien, chenhTien:tB.tongTien - tA.tongTien,
               soNguoiTang:0, soNguoiGiam:0, soNguoiBang:0 };
  rows.forEach(function(r){
    if (r.chenhCong > 0) tong.soNguoiTang++;
    else if (r.chenhCong < 0) tong.soNguoiGiam++;
    else tong.soNguoiBang++;
  });
  return { station:station, month:monthLabel, rows:rows, tong:tong,
           cfgCu:cfgCu, cfgMoi:cfgMoi,
           /* 🔴 Báo ra ĐÚNG mẫu số vừa dùng để tính (`tB.ngayCongThang`), không phải ô cấu hình.
              Sót chỗ này thì màn hình ghi "chia cho 99" trong khi cột tiền chia cho 13 — người
              đọc không có cách nào biết số nào đúng. */
           ngayCongThang:Number(tB.ngayCongThang || 0),
           chuaKhaiNgayCong:tB.chuaKhaiNgayCong,
           thieuLuong:tB.thieuLuong, thangTruoc:doc.thangTruoc,
           /* Để màn hình cảnh báo được "ngày làm bình thường cũng thành 1.5 công" — xem `_vpCaChuan`. */
           bacThang:(cfgMoi.duoiMin === 'bacthang'), caChuan:_vpCaChuan(cfgMoi) };
}

/* ---------------------------------------------------------------------------
 *  ĐỊNH TUYẾN lượt chấm công ONLINE của bộ phận Văn phòng
 * ------------------------------------------------------------------------ */
/**
 * Lượt chấm online lúc `timeStr` của cơ sở Văn phòng thuộc HÀNG NÀO, NGÀY NÀO.
 * Trả null = hàng chính, ngày hôm nay (y như cũ).
 *
 * ⚠️ Lượt lúc 00:00–06:00 thuộc ca đêm BẮT ĐẦU TỪ HÔM QUA, nên phải ghi vào khối ngày HÔM QUA.
 *    Không lùi ngày thì ca đêm bị chẻ đôi: 22:00 nằm ở ngày D, 06:00 nằm ở ngày D+1, cả hai đều
 *    thiếu một đầu -> không tính được công nào.
 */
/** Lượt này có nằm trong khoảng ÂN HẠN tan làm ca ngày không? [ngayDen, ngayDen+graceRaPhut] */
function _vpTrongAnHan(cfg, gio){
  var m = _minOfDay(gio), ngayDen = _minOfDay(cfg.ngayDen);
  if (m == null || ngayDen == null) return false;
  return m >= ngayDen && m <= ngayDen + Number(cfg.graceRaPhut || 0);
}
/**
 * Lượt chấm online của cơ sở Văn phòng thuộc HÀNG NÀO, NGÀY NÀO. null = HÀNG 1, ngày hôm nay.
 *
 * Anh Thắng chốt 04/08: mỗi người chỉ **2 HÀNG** — `ID` (vào–ra ca ngày) và `ID-CD` (hàng 2, chứa
 * cả tăng ca lẫn ca đêm, phân biệt bằng GIỜ khi tính công, không phải bằng hàng).
 *
 * ⚠️ Lượt 00:00–06:00 phải ghi vào khối ngày HÔM TRƯỚC, để giờ ra nằm CÙNG HÀNG 2 với giờ vào
 *    tối hôm trước. Không lùi thì ca đêm bị chẻ đôi giữa hai khối ngày, mỗi bên một đầu.
 *    (Công thì `_vpTinhNguoi` dồn sang ngày hôm sau — chỗ GHI và chỗ TÍNH là hai việc khác nhau.)
 */
function _vpDinhTuyen(coSo, ngay, gio, chinhChuaRa){
  if (!_vpLaVanPhong(coSo)) return null;
  var cfg = _vpCfg();
  var m = _minOfDay(gio), demDen = _minOfDay(cfg.demDen), ngayDen = _minOfDay(cfg.ngayDen);
  if (m == null || demDen == null || ngayDen == null) return null;
  if (m < demDen) return { ngay:_vpNgayTruoc(ngay), duoi:VP_DUOI_DEM, nhan:'Tăng ca / Đêm', dem:true };
  /* Biên `ngayDen`: lượt ĐÚNG 17:00:00 là tan làm ca ngày -> `>` chứ không `>=`. Và trong ân hạn
     sau đó, nếu hàng 1 chưa có giờ ra thì cũng là tan làm, không phải mở hàng 2. */
  if (chinhChuaRa && m >= ngayDen && m <= ngayDen + Number(cfg.graceRaPhut || 0)) return null;
  if (m > ngayDen) return { ngay:ngay, duoi:VP_DUOI_DEM, nhan:'Tăng ca / Đêm', dem:true };
  return null;                                   // trong ca ngày -> hàng 1
}
/**
 * Hàng của (người, hậu tố) trong khối ngày — chèn ngay dưới hàng chính nếu chưa có.
 * Cùng khuôn `_hangChoNhiemVu`, nhưng nhận HẬU TỐ trực tiếp: hậu tố Văn phòng không phải
 * "nhiệm vụ" của Nhóm Máy Tự Động, gộp vào hàm kia là trộn hai khái niệm.
 */
function _hangHauTo(sheet, khoi, maNV, hoTen, hauTo, nhan){
  var maChinh = String(maNV == null ? '' : maNV).trim();
  var maHang  = maChinh + '-' + hauTo;
  var hangChinh = findOrCreateEmpRow(sheet, khoi, maChinh, hoTen || maChinh);
  if (khoi.r2 >= khoi.r1){
    var ids = sheet.getRange(khoi.r1, 2, khoi.r2 - khoi.r1 + 1, 1).getValues();
    for (var i = 0; i < ids.length; i++)
      if (String(ids[i][0]).trim().toUpperCase() === maHang.toUpperCase()) return khoi.r1 + i;
  }
  var tenGoc = String(sheet.getRange(hangChinh, 1).getValue() || '').trim() || String(hoTen || maChinh);
  var moi = hangChinh + 1;
  sheet.insertRowAfter(hangChinh);
  try { sheet.getRange(moi, 1, 1, Math.max(2, sheet.getLastColumn())).clearContent().clearNote(); } catch(e){}
  sheet.getRange(moi, 1).setValue(tenGoc + NV_NGAN_CACH + (nhan || hauTo));
  sheet.getRange(moi, 2).setValue(maHang);
  try { sheet.setRowHeight(moi, 62); } catch(e){}
  khoi.r2 = khoi.r2 + 1;
  return moi;
}
/**
 * Ghi giờ cho HÀNG CÔNG ĐÊM. Không dùng `_ghiGioVaoRa` được: hàm đó giữ cặp
 * [SỚM NHẤT, MUỘN NHẤT] trong ngày, nên ca 22:00 -> 06:00 bị ĐẢO thành "vào 06:00, ra 22:00"
 * (06:00 sớm hơn nên thành giờ vào) -> ca đêm biến thành 16 tiếng ban ngày, công đêm mất sạch.
 *
 * Quy tắc ở đây: giữ đúng cặp [SỚM NHẤT, MUỘN NHẤT] như hàm cũ, nhưng so trên TRỤC ĐÊM ĐÃ TRẢI
 * PHẲNG (giờ sau nửa đêm cộng thêm 1440). Không phụ thuộc thứ tự tới và chạy lại bao nhiêu lần
 * cũng ra một kết quả — đúng tính chất mà `_ghiGioVaoRa` đã phải trả giá mới có (chú thích 02/08).
 *
 * ⚠️ BẢN ĐẦU CỦA EM SAI: chia cứng "≥ 22:00 là giờ vào, < 06:00 là giờ ra". Nghe hợp lý nhưng ai
 *    BẮT ĐẦU ca lúc 01:00 thì lượt đầu tiên bị ghi thành GIỜ RA, hàng đó không bao giờ có giờ vào
 *    -> `_vpPhutTrongKhung` trả 0 -> MẤT TRẮNG công đêm. `test_cconline` bắt được (4 phép đỏ).
 *    Cách trải phẳng thì lượt đầu luôn là giờ vào, bất kể mấy giờ.
 */
function _ghiGioDem(sheet, empRow, blockCol, timeStr, imageFormula, cfg){
  var ngayDen = _minOfDay(cfg.ngayDen), demDen = _minOfDay(cfg.demDen);
  if (ngayDen == null || demDen == null) return { loai:'bo' };
  /* "Trải phẳng" trục hàng 2: 18:00 -> 1080, 22:00 -> 1320, còn 01:00 (sau nửa đêm) -> 1500.
     ⚠️ Mốc trải phẳng phải là `ngayDen` (17:00), KHÔNG phải `demTu` (21:00): hàng 2 chứa CẢ tăng
        ca lẫn ca đêm. Lấy mốc 21:00 thì lượt tăng ca 18:00 trả null -> bị BỎ ÂM THẦM, nhân viên
        bấm mà không có gì được ghi. */
  function traiPhang(g){
    var m = _minOfDay(g); if (m == null) return null;
    if (m >= ngayDen) return m;
    if (m <  demDen)  return m + 1440;
    return null;                        // 06:00–17:00 là ca ngày, không thuộc hàng 2
  }
  var pMoi = traiPhang(timeStr);
  if (pMoi == null) return { loai:'bo' };

  var oVaoT = sheet.getRange(empRow, blockCol),     oVaoA = sheet.getRange(empRow, blockCol + 1);
  var oRaT  = sheet.getRange(empRow, blockCol + 2), oRaA  = sheet.getRange(empRow, blockCol + 3);
  var vCu = oVaoT.getValue(), rCu = oRaT.getValue();
  var vStr = (vCu === '' || vCu === null) ? '' : toTimeStr(vCu);
  var rStr = (rCu === '' || rCu === null) ? '' : toTimeStr(rCu);
  if (timeStr === vStr || timeStr === rStr) return { loai:'trung' };
  var pVao = vStr ? traiPhang(vStr) : null, pRa = rStr ? traiPhang(rStr) : null;

  function chuan(vao, ra){
    sheet.getRange(empRow, blockCol + 4).setNumberFormat('@')
         .setValue((vao ? hhmm(vao) : '') + (ra ? (' ' + hhmm(ra)) : ''));
  }
  if (vStr === ''){                                  // lượt ĐẦU của ca -> giờ vào
    oVaoT.setNumberFormat('@').setValue(timeStr);
    if (imageFormula) oVaoA.setFormula(imageFormula);
    chuan(timeStr, rStr);
    return { loai:'vao' };
  }
  if (pVao != null && pMoi < pVao){                  // sớm hơn giờ vào -> thành giờ vào mới
    var anhCu = oVaoA.getFormula();
    oVaoT.setNumberFormat('@').setValue(timeStr);
    if (imageFormula) oVaoA.setFormula(imageFormula);
    if (rStr === ''){ oRaT.setNumberFormat('@').setValue(vStr); if (anhCu) oRaA.setFormula(anhCu); rStr = vStr; }
    chuan(timeStr, rStr);
    return { loai:'vao' };
  }
  /* Nằm GIỮA vào và ra -> khoảng đã phủ, KHÔNG thu hẹp (đúng bài học 02/08 của `_ghiGioVaoRa`). */
  if (pRa != null && pMoi < pRa) return { loai:'giua' };
  oRaT.setNumberFormat('@').setValue(timeStr);
  if (imageFormula) oRaA.setFormula(imageFormula);
  chuan(vStr, timeStr);
  return { loai:'ra' };
}

function getWorkPayReport(station, monthLabel, pin){
  var u=_requireAuth(pin);
  if(!u.isAdmin && !_canStation(u,station)) throw new Error('Không có quyền cơ sở này.');
  var mt=String(monthLabel||'').replace('Tháng ','').split('-');
  var mm=mt.length===2?parseInt(mt[0],10):0, yyyy=mt.length===2?parseInt(mt[1],10):0;
  if(!mm||!yyyy) return {error:'Tháng không hợp lệ'};
  var prefix=yyyy+'-'+('0'+mm).slice(-2);
  var shifts=_shiftsOf(station), wages=_wagesOf(station), jobs=_jobTypes();
  var shByName={}; shifts.forEach(function(s){ shByName[s.name]=s; });
  // Lịch đã xếp: sched[id][day][ca] = 1 công việc
  var lsh=_lichSheet(), sched={};
  if(lsh.getLastRow()>=2){ var lv=lsh.getRange(2,1,lsh.getLastRow()-1,6).getValues();
    lv.forEach(function(r){ if(String(r[0])!==station) return;
      var day=(r[1] instanceof Date)?Utilities.formatDate(r[1],TZ,'yyyy-MM-dd'):String(r[1]); if(day.indexOf(prefix)!==0) return;
      var id=String(r[2]||''), ca=String(r[4]||''), jb=String(r[5]||'').split(',').map(function(s){return s.trim();}).filter(Boolean)[0]||'';
      if(!jb) return; sched[id]=sched[id]||{}; sched[id][day]=sched[id][day]||{}; sched[id][day][ca]=jb; });
  }
  // Chấm công thực tế
  // Cũng vậy: lọc theo `prefix` ở dòng dưới, nên chỉ cần đọc đúng khối tháng đó.
  var att=getSheetData('CS_'+station, pin, prefix), empName={}, byEmp={}, detail=[];
  (att.rows||[]).forEach(function(row){
    var day=row[1]; if(String(day).indexOf(prefix)!==0) return;
    var id=String(row[2]), name=row[3], inT=row[4], outT=row[6]; empName[id]=name;
    var inM=_minOfDay(inT), outM=_minOfDay(outT); if(inM==null||outM==null||outM<=inM) return;
    var assignDay=sched[id]&&sched[id][day]; if(!assignDay) return;
    var wk=_isWkendD(day);
    Object.keys(assignDay).forEach(function(ca){
      var jb=assignDay[ca], s=shByName[ca]; if(!s||!jb) return;
      var st=(wk&&s.startW)?s.startW:s.start, en=(wk&&s.endW)?s.endW:s.end;
      var ov=_overlapMin(inM,outM,_minOfDay(st),_minOfDay(en)); if(ov<=0) return;
      var hrs=Math.round(ov/60*100)/100, rate=Number(wages[jb])||0, pay=Math.round(hrs*rate);
      byEmp[id]=byEmp[id]||{}; byEmp[id][jb]=byEmp[id][jb]||{hours:0,pay:0}; byEmp[id][jb].hours+=hrs; byEmp[id][jb].pay+=pay;
      detail.push({date:day, id:id, name:name, ca:ca, job:jb, in:inT, out:outT, window:st+'-'+en, hours:hrs, rate:rate, pay:pay});
    });
  });
  var emps=Object.keys(byEmp).map(function(id){ return {id:id, name:empName[id]||id}; }).sort(function(a,b){return a.name<b.name?-1:1;});
  detail.sort(function(a,b){ return a.date<b.date?-1:(a.date>b.date?1:(a.name<b.name?-1:1)); });
  return { station:station, month:monthLabel, jobs:jobs, wages:wages, employees:emps, byEmp:byEmp, detail:detail };
}
function _upsertSched(station, cells, who){   // upsert LichCongViec theo (cơ sở|ngày|mãNV)
  var sh=_lichSheet();
  var lock=LockService.getScriptLock(); try{lock.waitLock(15000);}catch(e){}
  try{
    var last=sh.getLastRow(), rows = last>=2 ? sh.getRange(2,1,last-1,8).getValues() : [], idx={};
    for(var i=0;i<rows.length;i++){
      var day=(rows[i][1] instanceof Date)?Utilities.formatDate(rows[i][1],TZ,'yyyy-MM-dd'):String(rows[i][1]);
      idx[String(rows[i][0])+'|'+day+'|'+String(rows[i][2])+'|'+String(rows[i][4])]=i+2;
    }
    var now=_now();
    (cells||[]).forEach(function(c){
      var day=String(c.date), id=String(c.empNo||''), ca=String(c.ca||''), key=station+'|'+day+'|'+id+'|'+ca;
      var rowData=[station, day, id, c.name||'', ca, (c.jobs||[]).join(', '), who, now];
      if(idx[key]){ sh.getRange(idx[key],1,1,8).setValues([rowData]); }
      else { sh.appendRow(rowData); idx[key]=sh.getLastRow(); }
    });
    return (cells||[]).length;
  } finally { lock.releaseLock(); }
}
function saveWorkSchedule(station, cells, pin){
  var u = _requireAuth(pin);
  if(!u.isAdmin && !_canStation(u, station)) throw new Error('Không có quyền cơ sở này.');
  var n = _upsertSched(station, cells, u.name||u.pin||'');
  try{ _syncSchedToAttendance(station, cells); }catch(e){}   // tạo sẵn cột ngày + note lịch trên sheet chấm công
  return { ok:true, count:n };
}
// Tạo sẵn cột NGÀY trên sheet CS_ cho các ngày đã phân lịch + ghi NOTE (lịch đã xếp) lên ô tiêu đề ngày
function _syncSchedToAttendance(station, cells){
  var sh=_sheet('CS_'+station); if(!sh) return;
  var days={};
  (cells||[]).forEach(function(c){ var d=String(c.date); days[d]=days[d]||[]; var jobs=(c.jobs||[]);
    if(jobs.length) days[d].push((c.name||c.empNo)+': '+c.ca+' = '+jobs.join('/')); });
  Object.keys(days).forEach(function(d){
    var kb=findOrCreateDateBlock(sh, d);   // tạo cột nếu chưa có (đã tự nới cột khi hết)
    var list=days[d];
    // ⚠️ NOTE phải đặt ở hàng tiêu đề CỦA KHỐI đó (kb.khoi.hdr), không phải hàng 1 — từ khi mỗi
    //    tháng là một khối riêng thì hàng 1 chỉ còn là tiêu đề của khối ĐẦU TIÊN.
    try{ sh.getRange(kb.khoi.hdr, kb.col).setNote(list.length ? ('📋 LỊCH LÀM '+d+':\n'+list.join('\n')) : ''); }catch(e){}
  });
}

// ----- XIN ĐỔI LỊCH -> DUYỆT -> ÁP vào LichCongViec (lưu vết ở sheet DoiLichCV) -----
var SH_DOILICH = 'DoiLichCV';   // ID|Cơ sở|Mã NV|Tên|Ngày|Ca|Việc mới|Đổi sang ngày|Lý do|Trạng thái|Người xin|Người duyệt|Lúc xin|Lúc duyệt
function _doiSheet(){
  var ss=SpreadsheetApp.getActiveSpreadsheet(), sh=ss.getSheetByName(SH_DOILICH);
  var HEAD=['ID','Cơ sở','Mã NV','Tên','Ngày','Ca','Việc mới','Đổi sang ngày','Lý do','Trạng thái','Người xin','Người duyệt','Lúc xin','Lúc duyệt'];
  if(sh && String(sh.getRange(1,6).getValue())!=='Ca'){ try{ sh.setName(SH_DOILICH+'_old'); }catch(e){ try{ sh.setName(SH_DOILICH+'_old_'+(new Date()).getTime().toString(36)); }catch(e2){} } sh=null; }
  if(!sh){ sh=ss.insertSheet(SH_DOILICH); sh.appendRow(HEAD);
    sh.getRange(1,1,1,HEAD.length).setFontWeight('bold').setBackground('#0f172a').setFontColor('#38bdf8'); sh.setFrozenRows(1); }
  return sh;
}
function _uidD(){ return 'DL'+(new Date()).getTime().toString(36)+Math.floor(Math.random()*1296).toString(36); }
function xinDoiLich(req, pin){   // req={station,empNo,name,date,ca,newJobs:[],moveDate,reason}
  var u=_requireAuth(pin); req=req||{}; var station=String(req.station||'');
  if(!u.isAdmin && !_canStation(u,station)) throw new Error('Không có quyền cơ sở này.');
  _doiSheet().appendRow([_uidD(), station, String(req.empNo||''), req.name||'', String(req.date||''), String(req.ca||''),
    (req.newJobs||[]).join(', '), String(req.moveDate||''), req.reason||'', 'Chờ duyệt', u.name||u.pin||'', '', _now(), '']);
  return {ok:true};
}
function getDoiLichList(pin, onlyPending){
  var u=_requireAuth(pin); var sh=_doiSheet(); if(sh.getLastRow()<2) return [];
  var v=sh.getRange(2,1,sh.getLastRow()-1,14).getValues(), out=[];
  v.forEach(function(r){
    var station=String(r[1]); if(!u.isAdmin && !_canStation(u,station)) return;
    if(onlyPending && String(r[9])!=='Chờ duyệt') return;
    out.push({ id:r[0], station:station, empNo:String(r[2]), name:r[3],
      date:(r[4] instanceof Date)?Utilities.formatDate(r[4],TZ,'yyyy-MM-dd'):String(r[4]),
      ca:String(r[5]||''), newJobs:String(r[6]||''), moveDate:(r[7] instanceof Date)?Utilities.formatDate(r[7],TZ,'yyyy-MM-dd'):String(r[7]||''),
      reason:r[8], status:r[9], nguoiXin:r[10], nguoiDuyet:r[11], lucXin:String(r[12]||''), lucDuyet:String(r[13]||'') });
  });
  return out.reverse();
}
function duyetDoiLich(id, approve, pin){
  var u=_requireAuth(pin);
  if(!(u.isAdmin || u.role===ROLE.QUAN_LY || u.role===ROLE.CHT)) throw new Error('Bạn không có quyền duyệt.');
  var sh=_doiSheet(), last=sh.getLastRow(); if(last<2) return {ok:false,error:'Không tìm thấy'};
  var v=sh.getRange(2,1,last-1,14).getValues();
  for(var i=0;i<v.length;i++){
    if(String(v[i][0])!==String(id)) continue;
    var row=i+2, station=String(v[i][1]);
    if(!u.isAdmin && !_canStation(u,station)) throw new Error('Không có quyền cơ sở này.');
    if(String(v[i][9])!=='Chờ duyệt') return {ok:false, error:'Yêu cầu đã xử lý rồi'};
    if(approve){
      var empNo=String(v[i][2]), name=v[i][3];
      var date=(v[i][4] instanceof Date)?Utilities.formatDate(v[i][4],TZ,'yyyy-MM-dd'):String(v[i][4]);
      var ca=String(v[i][5]||'');
      var newJobs=String(v[i][6]||'').split(',').map(function(s){return s.trim();}).filter(Boolean);
      var moveDate=(v[i][7] instanceof Date)?Utilities.formatDate(v[i][7],TZ,'yyyy-MM-dd'):String(v[i][7]||'');
      var cells = moveDate
        ? [{date:date, empNo:empNo, name:name, ca:ca, jobs:[]}, {date:moveDate, empNo:empNo, name:name, ca:ca, jobs:newJobs}]
        : [{date:date, empNo:empNo, name:name, ca:ca, jobs:newJobs}];
      _upsertSched(station, cells, u.name||u.pin||'');
      sh.getRange(row,10).setValue('Đã duyệt');
    } else { sh.getRange(row,10).setValue('Từ chối'); }
    sh.getRange(row,12).setValue(u.name||u.pin||''); sh.getRange(row,14).setValue(_now());
    return {ok:true};
  }
  return {ok:false, error:'Không tìm thấy yêu cầu'};
}


/* ============================================================================
 *  VIỆC E — XUẤT PDF BẢNG CÔNG THEO TUẦN / THÁNG   (01/08/2026)
 * ----------------------------------------------------------------------------
 * Anh Thắng: *"Bổ sung tính năng xuất PDF theo tháng hoặc tuần để cửa hàng trưởng gửi nhân
 * viên đối chiếu định kỳ"*.
 *
 * ⚠️ VÌ SAO SỐ LẤY TỪ MÁY KHÁCH, KHÔNG TÍNH LẠI Ở SERVER
 *    Quy ước CLAUDE.md: *"sửa chỗ tính tiền thì so logic cũ với logic mới trên dữ liệu sinh ngẫu
 *    nhiên, đòi trùng khít"*. Cách chắc chắn nhất để KHÔNG lệch là **đừng viết công thức lần thứ
 *    hai**. Giờ làm / số công đang được tính đúng một chỗ duy nhất trong Index.html
 *    (`_workMin` · `_fmtHrs` · `ccSoCong` · `renderTotals`). Nếu ở đây tính lại thì có 2 bản công
 *    thức, sửa 1 bên là PDF nói khác màn hình — mà PDF này chính là tờ giấy nhân viên cầm đi đối
 *    chiếu lương. Nên client gửi lên ĐÚNG các con số nó đang hiện, server chỉ dựng khung + đổi
 *    sang PDF. Tính chất bảo đảm được: **PDF luôn khớp màn hình**.
 *
 *    Đổi lại, server KHÔNG tin nội dung gửi lên:
 *      · phải qua `_requireAuth` + `_canStation` (NHÂN VIÊN bị `_canStation` chặn sẵn);
 *      · mọi ô đều `_pdfEsc` (không cho chèn HTML/CSS), cắt bớt độ dài, giới hạn số dòng;
 *      · file chỉ trả về cho chính người gọi. Có bấm "tạo link" mới ghi vào Drive.
 *    Rủi ro còn lại: người đã đăng nhập tự bịa số cho PDF của chính mình — nhưng họ vốn đã sửa
 *    được sheet, nên đây không phải cửa mới. Sheet vẫn là bản gốc để đối chiếu.
 * ==========================================================================*/
var PDF_MAX_CHI_TIET = 4000;     // 1 cơ sở/tháng thực tế ~600 dòng; 4000 là quá rộng rồi
var PDF_MAX_TONG_HOP = 500;
var PDF_MAX_O        = 120;      // cắt độ dài 1 ô, tránh 1 ô rác kéo dài cả trang
var PDF_FOLDER       = 'BaoCaoChamCong';

function _pdfEsc(s){
  return String(s == null ? '' : s).slice(0, PDF_MAX_O)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
// 'yyyy-MM-dd' -> 'dd/MM/yyyy' (giữ nguyên nếu không đúng khuôn, không tự đoán)
function _pdfNgay(s){
  var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(s || '').trim());
  return m ? (m[3] + '/' + m[2] + '/' + m[1]) : String(s || '');
}
function _pdfTenCongTy(){
  var t = PropertiesService.getScriptProperties().getProperty('TEN_CONG_TY');
  return (t && String(t).trim()) ? String(t).trim() : 'CÔNG TY TNHH GIẢI TRÍ K&H';
}
// Tên file: KHÔNG dấu, không khoảng trắng — Drive/Zalo/máy in đỡ đổi tên lung tung.
function _pdfTenFile(station, tuNgay, denNgay){
  var st = String(station || '').replace(/^CS_/, '').replace(/[^A-Za-z0-9_\-]/g, '_');
  var a  = String(tuNgay || '').replace(/[^0-9]/g, ''), b = String(denNgay || '').replace(/[^0-9]/g, '');
  return 'BangCong_' + (st || 'CoSo') + '_' + a + (b && b !== a ? ('-' + b) : '');
}
function _pdfFolder(){
  var root = DriveApp.getFolderById(ATT_FOLDER_ID);
  var it = root.getFoldersByName(PDF_FOLDER);
  return it.hasNext() ? it.next() : root.createFolder(PDF_FOLDER);
}

/**
 * Dựng HTML của tờ bảng công. Tách riêng để test được mà không cần đụng Drive.
 * req: { station, kyLabel, tuNgay, denNgay, cotCuoi, nguoiXuat,
 *        tongHop: [[maNV, hoTen, ngayCong, thieuOut, cot5], ...],
 *        chiTiet: [[ngay, maNV, hoTen, gioVao, gioRa, gioLam], ...] }
 */
function _pdfHtmlBangCong(req){
  req = req || {};
  var tongHop = (req.tongHop || []).slice(0, PDF_MAX_TONG_HOP);
  var chiTiet = (req.chiTiet || []).slice(0, PDF_MAX_CHI_TIET);
  var cotCuoi = _pdfEsc(req.cotCuoi || 'Tổng giờ làm');
  var station = _pdfEsc(String(req.station || '').replace(/^CS_/, ''));
  var ky      = _pdfEsc(req.kyLabel || '');
  var khoang  = (req.denNgay && req.denNgay !== req.tuNgay)
              ? ('Từ ngày ' + _pdfNgay(req.tuNgay) + ' đến ngày ' + _pdfNgay(req.denNgay))
              : ('Ngày ' + _pdfNgay(req.tuNgay));
  var bicat   = ((req.chiTiet || []).length > PDF_MAX_CHI_TIET);

  var h = [];
  h.push('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Bang cong</title><style>');
  h.push('@page{size:A4;margin:12mm 10mm}');
  h.push('body{font-family:Arial,Helvetica,sans-serif;color:#111;font-size:11px;margin:0}');
  h.push('h1{font-size:16px;margin:0 0 2px;text-align:center;text-transform:uppercase}');
  h.push('.cty{font-size:11px;font-weight:bold;text-align:center;margin:0}');
  h.push('.sub{font-size:11px;text-align:center;color:#333;margin:0 0 2px}');
  h.push('.meta{font-size:10px;color:#555;text-align:center;margin:0 0 10px}');
  h.push('h2{font-size:12px;margin:14px 0 4px;border-bottom:1px solid #888;padding-bottom:2px}');
  h.push('table{border-collapse:collapse;width:100%}');
  h.push('th,td{border:1px solid #999;padding:3px 5px;font-size:10px}');
  h.push('th{background:#e8eef5;font-weight:bold;text-align:center}');
  h.push('thead{display:table-header-group}');   // sang trang mới vẫn lặp lại dòng tiêu đề
  h.push('tr{page-break-inside:avoid}');
  h.push('.c{text-align:center}.r{text-align:right}.b{font-weight:bold}');
  h.push('.thieu{color:#b00;font-weight:bold}');
  h.push('.ghi{font-size:9px;color:#666;margin-top:6px}');
  h.push('.ky{margin-top:22px;width:100%}');
  h.push('.ky td{border:none;text-align:center;font-size:10px;padding-top:4px}');
  h.push('</style></head><body>');

  h.push('<p class="cty">' + _pdfEsc(_pdfTenCongTy()) + '</p>');
  h.push('<h1>Bảng chấm công</h1>');
  h.push('<p class="sub">Cơ sở: <b>' + station + '</b>' + (ky ? (' &nbsp;·&nbsp; ' + ky) : '') + '</p>');
  h.push('<p class="sub">' + _pdfEsc(khoang) + '</p>');
  // Giờ in: viết kiểu Việt (dd/MM/yyyy HH:mm) chứ không để nguyên yyyy-MM-dd của _now().
  var inLuc = Utilities.formatDate(new Date(), TZ, 'dd/MM/yyyy HH:mm');
  h.push('<p class="meta">In lúc ' + _pdfEsc(inLuc) + (req.nguoiXuat ? (' · người xuất: ' + _pdfEsc(req.nguoiXuat)) : '') + '</p>');

  h.push('<h2>1. Tổng hợp theo nhân viên</h2>');
  h.push('<table><thead><tr><th style="width:70px">Mã NV</th><th>Họ và tên</th>'
       + '<th style="width:60px">Ngày công</th><th style="width:70px">Thiếu giờ ra</th>'
       + '<th style="width:110px">' + cotCuoi + '</th></tr></thead><tbody>');
  if (!tongHop.length) h.push('<tr><td colspan="5" class="c">(Không có dữ liệu)</td></tr>');
  tongHop.forEach(function(r){
    r = r || [];
    var thieu = Number(r[3]) || 0;
    h.push('<tr><td class="c">' + _pdfEsc(r[0]) + '</td><td>' + _pdfEsc(r[1]) + '</td>'
      + '<td class="c">' + _pdfEsc(r[2]) + '</td>'
      + '<td class="c' + (thieu ? ' thieu' : '') + '">' + _pdfEsc(r[3]) + '</td>'
      + '<td class="r b">' + _pdfEsc(r[4]) + '</td></tr>');
  });
  h.push('</tbody></table>');

  h.push('<h2>2. Chi tiết từng ngày</h2>');
  h.push('<table><thead><tr><th style="width:70px">Ngày</th><th style="width:70px">Mã NV</th><th>Họ và tên</th>'
       + '<th style="width:65px">Giờ vào</th><th style="width:65px">Giờ ra</th>'
       + '<th style="width:70px">Giờ làm</th></tr></thead><tbody>');
  if (!chiTiet.length) h.push('<tr><td colspan="6" class="c">(Không có dữ liệu)</td></tr>');
  chiTiet.forEach(function(r){
    r = r || [];
    var ra = String(r[4] == null ? '' : r[4]).trim();
    h.push('<tr><td class="c">' + _pdfEsc(_pdfNgay(r[0])) + '</td><td class="c">' + _pdfEsc(r[1]) + '</td>'
      + '<td>' + _pdfEsc(r[2]) + '</td>'
      + '<td class="c">' + _pdfEsc(r[3]) + '</td>'
      + '<td class="c' + (ra ? '' : ' thieu') + '">' + (ra ? _pdfEsc(ra) : 'THIẾU') + '</td>'
      + '<td class="c">' + _pdfEsc(r[5]) + '</td></tr>');
  });
  h.push('</tbody></table>');

  if (bicat) h.push('<p class="ghi thieu">⚠ Kỳ này có nhiều hơn ' + PDF_MAX_CHI_TIET
    + ' dòng nên phần chi tiết đã bị cắt bớt. Hãy xuất theo TUẦN để có đủ.</p>');
  h.push('<p class="ghi">Dòng ghi "THIẾU" ở cột Giờ ra là quên check-out — cần bổ sung trước khi chốt công.</p>');

  h.push('<table class="ky"><tr>'
    + '<td style="width:50%"><b>NHÂN VIÊN XÁC NHẬN</b><br>(ký, ghi rõ họ tên)<br><br><br><br></td>'
    + '<td style="width:50%"><b>CỬA HÀNG TRƯỞNG</b><br>(ký, ghi rõ họ tên)<br><br><br><br></td>'
    + '</tr></table>');
  h.push('</body></html>');
  return h.join('');
}

/**
 * Xuất PDF bảng công. Trả base64 để máy khách tải về; chỉ tạo link Drive khi được yêu cầu.
 * @return {ok, ten, b64, url, soDongTongHop, soDongChiTiet, biCat}
 */
function xuatPdfChamCong(pin, req){
  var u = _requireAuth(pin);
  req = req || {};
  var station = String(req.station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok:false, error:'Chưa chọn cơ sở.' };
  if (!_canStation(u, station)) return { ok:false, error:'Bạn không có quyền xem cơ sở này.' };
  if (!(req.tongHop || []).length && !(req.chiTiet || []).length)
    return { ok:false, error:'Kỳ này không có dữ liệu chấm công để xuất.' };

  req.station   = station;
  req.nguoiXuat = u.name || '';
  var html = _pdfHtmlBangCong(req);
  var ten  = _pdfTenFile(station, req.tuNgay, req.denNgay);

  var blob;
  try {
    blob = Utilities.newBlob(html, 'text/html', ten + '.html').getAs('application/pdf').setName(ten + '.pdf');
  } catch (e) {
    return { ok:false, error:'Không tạo được PDF: ' + (e && e.message ? e.message : e) };
  }

  var url = '';
  if (req.taoLink) {
    try {
      var folder = _pdfFolder();
      var cu = folder.getFilesByName(ten + '.pdf');   // xuất lại cùng kỳ -> thay file cũ, khỏi rác Drive
      while (cu.hasNext()) cu.next().setTrashed(true);
      var file = folder.createFile(blob);
      // ⚠️ Link này AI CÓ LINK CŨNG XEM ĐƯỢC (giống ảnh chấm công). Giao diện đã ghi rõ,
      //    và mặc định KHÔNG tạo link — phải tự tích mới có.
      try { file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW); } catch (se) {}
      url = 'https://drive.google.com/file/d/' + file.getId() + '/view';
    } catch (e2) {
      url = '';   // không tạo được link thì vẫn trả file tải về, chỉ báo lại
      return { ok:true, ten:ten + '.pdf', b64:Utilities.base64Encode(blob.getBytes()), url:'',
               loiLink:'Không tạo được link chia sẻ: ' + (e2 && e2.message ? e2.message : e2),
               soDongTongHop:(req.tongHop||[]).length, soDongChiTiet:(req.chiTiet||[]).length,
               biCat:((req.chiTiet||[]).length > PDF_MAX_CHI_TIET) };
    }
  }
  return { ok:true, ten:ten + '.pdf', b64:Utilities.base64Encode(blob.getBytes()), url:url,
           soDongTongHop:(req.tongHop||[]).length, soDongChiTiet:(req.chiTiet||[]).length,
           biCat:((req.chiTiet||[]).length > PDF_MAX_CHI_TIET) };
}
