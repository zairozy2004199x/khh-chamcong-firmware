/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2 — WEB NHÂN VIÊN  ·  00_Config
 * ---------------------------------------------------------------------------
 * Hằng số, quy tắc tính tiền, định nghĩa schema.
 * Dùng CHUNG Google Sheet với web kế toán JP ⇒ kế toán thấy dữ liệu realtime.
 *
 * NGUYÊN TẮC (kế thừa bài học POSH v3):
 *  · Mọi con số tiền do server tính từ chỉ số máy — client không gửi lên tổng.
 *  · Ghi chỉ đụng đúng ô cần ghi, luôn kèm Audit.
 *  · Đọc sheet 1 lần rồi cache, không đọc trong vòng lặp.
 *═══════════════════════════════════════════════════════════════════════════*/

/**
 * Sheet dùng chung với web kế toán JP.
 * ID **không** nằm trong code — đặt ở Project Settings → Script Properties,
 * khóa `JP_SHEET_ID`. Lý do: repo này đẩy lên git, ID lọt vào history là vĩnh viễn.
 */
function jpSheetId_() {
  var p = PropertiesService.getScriptProperties();
  var id = p.getProperty('JP_SHEET_ID');
  if (id) return id;

  /*
   * Chưa đặt thì TỰ DÒ theo tên sheet rồi ghi lại — chỉ chạy đúng một lần trong
   * đời project. Không có bước này thì lần deploy đầu là app chết trắng, phải vào
   * editor đặt property tay mới sống lại được.
   *
   * Web app chạy bằng quyền chủ sở hữu (executeAs USER_DEPLOYING) nên dò được.
   */
  var tim = jpDoSheetId_();
  if (!tim.id) throw new Error(tim.msg);

  p.setProperty('JP_SHEET_ID', tim.id);
  return tim.id;
}

/**
 * Dò ID sheet dữ liệu theo tên. Trả {id, msg} — `id` rỗng thì `msg` nói rõ vì sao,
 * kèm cách xử lý. KHÔNG tự chọn khi có nhiều sheet cùng tên.
 */
function jpDoSheetId_() {
  var ten = JP_SHEET_NAME;
  var thay = [];
  try {
    var it = DriveApp.getFilesByName(ten);
    while (it.hasNext()) thay.push(it.next().getId());
  } catch (e) {
    return { id: '', msg: 'Chưa đặt JP_SHEET_ID và không dò được Drive (' + e.message +
                          "). Chạy tay: jpDatCauHinh_('<ID sheet>')" };
  }

  if (thay.length === 1) return { id: thay[0], msg: '' };

  if (!thay.length) {
    return { id: '', msg: 'Chưa đặt JP_SHEET_ID và không thấy sheet tên "' + ten +
                          "\". Chạy tay: jpDatCauHinh_('<ID sheet>')" };
  }
  return { id: '', msg: 'Có ' + thay.length + ' sheet cùng tên "' + ten +
                        '" — không tự chọn. Chạy tay với ID đúng: ' +
                        thay.join(' · ') };
}

/** Tên sheet dữ liệu — dùng để tự dò khi chưa đặt JP_SHEET_ID. */
var JP_SHEET_NAME = 'JP KẾ TOÁN - DATA (v2 làm lại)';

/** Thư mục Drive gốc chứa ảnh báo cáo. Để '' thì tự tạo lần đầu. */
var JP_PHOTO_ROOT_ID = '';
var JP_PHOTO_ROOT_NAME = 'JP Capsule - Anh bao cao';

/**
 * Cỡ tối đa `jpAnhXem` chịu trả về (bytes gốc, chưa base64).
 * Ảnh do app tải lên đã bị `jpUploadPhoto` chặn ở 3MB, nên mọi ảnh bình thường
 * đều lọt. Mốc 5MB là để ảnh cũ / ảnh ai bỏ tay vào thư mục còn được NÓI RA là
 * quá lớn, thay vì làm vỡ lượt gọi mà không ai biết vì sao.
 */
var JP_ANH_XEM_MAX = 5 * 1024 * 1024;

/**
 * Tài khoản kế toán JP — xác nhận bằng Drive: chủ file `Kho Hàng JP HCM Năm 2025`.
 * Chỉ dùng cho `jpChiaSeAnhKeToan_`. Để trong code chứ **đừng gõ tay lúc chạy**: gõ
 * sai một chữ là cấp quyền xem ảnh vận hành cho một địa chỉ lạ.
 */
var JP_MAIL_KE_TOAN = 'kthcm@poshvn.com';

/*───────────────────────────────────────────────────────────────────────────
 * CỠ ẢNH — Andy chốt 11/08/2026: *"phải nén lại dạng thumbnail hết"*
 *
 * Drive tự nén theo bề rộng yêu cầu. Đo trên một ảnh chỉ số thật (gốc 125.862đ
 * byte · 960×1280):
 *
 *   w120 → 10,2 KB · w200 → 19,7 KB · w300 → 36 KB · w600 → 104 KB
 *   w1000 và w1600 → 125.862 byte = ĐÚNG BẢN GỐC (Drive thôi nén từ đây)
 *
 * ⚠️ Ô ảnh nhỏ chỉ **74px** (kế toán) · 62px (nhân viên) · **28px** (trong bảng).
 * Bản `w600` trước đây là **nặng gấp hơn 5 lần mức cần** — báo cáo 138 ảnh của SB
 * Phú Quốc tải 14,4MB chỉ để vẽ mấy ô 74px. `w200` đủ nét cả trên màn 2× DPI.
 *
 * ⚠️ **Đừng hạ xuống `w120`** cho "nhẹ nữa": 120px trên màn 2× DPI là ô 74px nhìn
 * bị nhoè, mà ô ảnh nhỏ là chỗ kế toán nhìn để CHỌN tấm cần soi.
 *───────────────────────────────────────────────────────────────────────────*/

/** Ảnh nhỏ ở dải / trong bảng. */
var JP_ANH_SZ_NHO = 'w200';

/** Bản để ĐỌC CHỈ SỐ ĐỒNG HỒ khi bấm vào xem. */
var JP_ANH_SZ_TO = 'w1600';

/**
 * URL ảnh theo cỡ, dựng từ `fileId`.
 *
 * ⚠️ **Dựng từ `fileId`, KHÔNG dùng lại cột `url` đã lưu trên sheet.** Cột đó ghi
 * cứng `sz=w600` lúc tải ảnh lên, nên mọi ảnh cũ sẽ mãi mãi nặng gấp 5 lần nếu đọc
 * theo nó. Dựng lại thì đổi hằng số là ăn cho cả ảnh cũ.
 */
function jpAnhUrl_(fileId, sz) {
  var id = jpStr_(fileId);
  if (!id) return '';
  return 'https://drive.google.com/thumbnail?id=' + encodeURIComponent(id) +
         '&sz=' + (jpStr_(sz) || JP_ANH_SZ_NHO);
}

/*───────────────────────────────────────────────────────────────────────────
 * QUY TẮC TÍNH TIỀN  (Andy chốt 31/07/2026)
 *───────────────────────────────────────────────────────────────────────────*/

/** Máy TIỀN: mỗi xung đồng hồ = 5.000đ. */
var JP_RATE_MONEY_PULSE = 5000;

/**
 * HAI LOẠI MÁY TIỀN — giá 1 xung đồng hồ (Andy chốt 29/08/2026).
 *
 * Andy: *"máy tiền có 2 loại máy, máy tiền chia 2 đúng kh và có loại máy trong công thức
 * kh chia 2"*, và giải thích: *"chỉ số trên máy là 200 = 2.000.000 thì tiền thực tế chỉ
 * có 1.000.000 thôi"* — tức loại **CHIA 2** là loại app đang tính (200 xung × 5.000đ =
 * 1.000.000đ). Loại còn lại **KHÔNG chia 2**: 200 xung = 2.000.000đ.
 *
 * ⚠️ **DANH SÁCH ĐÓNG**, y như `jpCachThu_`. Cho gõ số tự do là có ngày một khu mang
 * `giaXung = 500` vì gõ thiếu số 0, và **doanh thu khu đó về 1/10 mà sổ vẫn cân**.
 *
 * ⚠️ Cờ nằm ở **TỪNG DÒNG** (`JP_Rows.giaXung`), KHÔNG ở khu. Andy chốt 29/08/2026,
 * sửa lại lời chốt trước đó: hai loại máy **XEN LẪN nhau** trong cùng một khu.
 *
 * ⚠️ **Mặc định là 5.000 (loại chia 2)** — tức **giữ nguyên hành vi cũ**. Chiều an toàn
 * nằm đúng phía đó: khu chưa khai thì tính y như trước bản này, 12 cơ sở còn lại không
 * đổi một đồng. Nếu mặc định là 10.000 thì mọi khu chưa khai **tự nhân đôi doanh thu**.
 *
 * ⚠️ Số không thuộc danh sách thì **rơi về mặc định**, KHÔNG throw: hàm này chạy cả lúc
 * MỞ LẠI báo cáo cũ (dòng đời cũ không có cột này), throw là không mở nổi báo cáo cũ.
 * Bù lại `jpCanhBaoHead_` nói ra khi khu dùng giá khác mặc định, nên không im lặng.
 */
var JP_GIA_XUNG = [5000, 10000];

function jpGiaXung_(v) {
  var n = jpNum_(v);
  for (var i = 0; i < JP_GIA_XUNG.length; i++) {
    if (n === JP_GIA_XUNG[i]) return n;
  }
  return JP_RATE_MONEY_PULSE;
}

/** Máy XU: 1 xu = 50.000đ · 1 đơn vị money = 10.000đ. */
var JP_RATE_COIN = 50000;
var JP_RATE_COIN_MONEY = 10000;

/**
 * GIÁ suy ra từ mã hàng — nay ưu tiên **MÃ MISA** (Andy chốt 05/08/2026).
 *
 * Andy: *"tiền tính từ ô misa chứ không phải mã"*. Đúng: item master có BA mã, và
 * chỉ mã MISA mang tiền tố giá:
 *
 *   TÊN HÀNG      "Trứng khủng long"    — chữ, không có giá
 *   Mã MISA       "100JP023"            — số đầu LÀ GIÁ (100.000đ)  ← lấy giá ở đây
 *   MÃ HÀNG nội bộ "A-077" · "B-002"    — không có giá
 *
 * Nên `jpGiaDong_` thử **mã Misa trước**, rồi mới rơi về `itemCode` cho dữ liệu đời
 * đầu (lúc đó nhân viên gõ mã có tiền tố vào ô Mã hàng).
 *
 * ⚠️ Đừng đảo thứ tự. Đảo lại là cơ sở nào gõ mã nội bộ `A-077` vào ô Mã hàng thì
 * giá ra 0 ⇒ tiền theo hàng = 0 ⇒ bảng tổng báo lệch bằng cả doanh thu.
 */
function jpGiaDong_(row) {
  var g = jpNum_(row && row.price);
  if (g > 0) return g;
  g = jpGiaTuMa_(row && row.itemMisa);
  if (g > 0) return g;
  return jpGiaTuMa_(row && row.itemCode);
}

/**
 * Giá 1 trứng suy từ TIỀN TỐ một mã: 50JPxxx→50k · 100JPxxx→100k · 150JPxxx→150k
 * · 200JPxxx→200k. Trả 0 nếu không nhận dạng được.
 *
 * ⚠️ Gọi hàm này TRỰC TIẾP chỉ khi biết chắc mình đang có mã nào. Ở dòng báo cáo thì
 * dùng `jpGiaDong_(row)` — nó biết thứ tự ưu tiên Misa → itemCode.
 */
function jpGiaTuMa_(ma) {
  var m = String(ma || '').trim().match(/^(\d+)\s*JP/i);
  if (!m) return 0;
  var n = parseInt(m[1], 10);
  return (n > 0 && n <= 1000) ? n * 1000 : 0;
}

/**
 * DÒNG NHÂN VIÊN CHƯA GÕ GÌ VÀO — **NGUỒN DUY NHẤT**, và là dòng KHÔNG được chặn nộp.
 *
 * ⚠️ Gặp thật 26/08/2026: SBPQ nhập xong 15 dòng, thêm một dòng thứ 16 rồi để đó, và
 * **không nộp được báo cáo nào nữa**. Câu chặn nói *"dòng 16 — chưa chọn ô máy, chọn hoặc
 * xoá dòng này"* — mà mẫu TÁCH **không có cột ô máy** (Andy chốt 12/08: nhân viên tự điền
 * hết), nên nó chỉ vào một thứ không có trên màn hình. Đúng lại bệnh 18/08.
 *
 * ⚠️ ĐỊNH NGHĨA CHẶT: dòng trống = **không có DANH TÍNH nào** (không ô máy, không mã hàng,
 * không tên hàng, không ghi chú) **VÀ không một ô số nào lớn hơn 0**.
 *
 * ⚠️⚠️ **Ô SỐ ĐO BẰNG `> 0`, KHÔNG ĐO BẰNG `jpBlank_`** — bản 26/08 đo bằng `jpBlank_` và
 * vì thế **chỉ chữa được mẫu TÁCH**, để nguyên mẫu CHUNG hỏng thêm hai ngày nữa. Máy chủ
 * TỰ GHI số 0 vào `amount`/`cash`/`soldQty`/`collection`… lúc lưu, nên với mẫu CHUNG chỉ
 * cần LƯU một lần (tự lưu sau 25 giây ngừng gõ) là dòng ma có `amount = 0` ⇒ `jpBlank_`
 * trả false ⇒ chặn nộp mãi mãi. Đây chính là bệnh *"gõ thì được, lưu xong là tịt"*.
 *
 * ⚠️ Đo `> 0` KHÔNG phá bất biến *"ô trống là CHƯA NHẬP, không phải 0"*: bất biến đó nói về
 * **ô của một dòng CÓ THẬT**, còn ở đây ta đang hỏi *"dòng này có tồn tại không"*. Một dòng
 * không danh tính thì không ra doanh thu, không ra hàng, không ra lớp tồn — số 0 trên nó
 * không mang tin gì. Ngay khi có **một** số > 0 thì nó thôi là dòng ma và bị chặn như cũ.
 *
 * Dòng không có danh tính thì **không thể** ra doanh thu, ra hàng, hay ra lớp tồn — nó là
 * dòng ma. Bỏ qua khi chặn là an toàn về tiền; nhưng `jpSubmitReport` vẫn **đếm và nói ra**
 * (cảnh báo, không chặn) để không im lặng.
 */
function jpDongTrong_(r) {
  if (!r) return true;
  /* Có DANH TÍNH — hoặc một ghi chú gõ tay — thì không phải dòng trống.
     ⚠️ KHÔNG đo `xuDaysJson`: máy chủ ghi chuỗi `'[]'` vào đó cho MỌI dòng. */
  if (jpStr_(r.machineId) || jpStr_(r.machineCode) ||
      jpStr_(r.itemCode)  || jpStr_(r.itemMisa)   || jpStr_(r.itemName) ||
      jpStr_(r.topupNote) || jpStr_(r.note)       || jpStr_(r.refundRowNote)) return false;
  /* ⚠️ Không danh tính VẪN chưa đủ — phải KHÔNG có số nào ở bất cứ ô nào.
     Gặp thật: dòng COIN của VICOM 3/2 không có ô máy nào được chọn nhưng `mBefore 84855`
     `mAfter 85710` `cBefore 16971` là số THẬT (gieo từ kỳ trước + nhân viên gõ). Bỏ qua
     nó là **mất doanh thu xu của cả kỳ** mà không ai báo — đúng loại lỗi tệ nhất.

     ⚠️⚠️ ĐO BẰNG `> 0`, TUYỆT ĐỐI KHÔNG DÙNG `jpBlank_` — đây là chỗ bản 26/08 sai và
     nó chỉ chữa được ĐÚNG MẪU TÁCH. Lý do: `jpCalcMoneyRow_` ghi thẳng
     `row.amount = mAct * JP_RATE_MONEY_PULSE` (một con SỐ, ra 0 với dòng chưa ai gõ),
     chỉ nhánh gõ tay của mẫu TÁCH mới đi qua `jpNumOrBlank_` nên mới giữ được ô trống.
     ⇒ Với mẫu CHUNG — tức gần hết hệ thống — cứ LƯU một lần là `amount` thành `0`,
     `jpBlank_(0)` là false, dòng ma hoá thành "dòng có dữ liệu" và chặn nộp mãi mãi.
     Đó đúng là thứ đã xảy ra: đo trên sheet thật 28/08/2026, 16/20 báo cáo đang nhập
     dở bị chặn, 55 ô chặn đều là dòng ma, và 61/64 dòng không danh tính KHÔNG có một
     số nào > 0 ở BẤT CỨ cột nào. Ba dòng còn lại (VICOM máy xu) có số thật và phải
     tiếp tục bị chặn — chính là phép đối chứng.

     ⚠️ Vì mọi ô đo bằng `> 0` nên thêm ô vào danh sách là AN TOÀN (chỉ siết thêm),
     còn BỎ ô ra thì phải hỏi "ô này có bao giờ là số thật của nhân viên không". */
  var o = ['mBefore', 'mAfter', 'cBefore', 'cAfter', 'amount', 'cash', 'bank',
           'cashReal', 'hOpen', 'hBefore', 'hAfter', 'soldQty', 'stockOut',
           'addQty1', 'addQty2', 'stockOpen', 'stockActual', 'defectQty',
           'returnQty', 'refundAmt', 'giaXu', 'xuTong'];
  for (var i = 0; i < o.length; i++) { if (jpNum_(r[o[i]]) > 0) return false; }
  return true;
}

/**
 * QR KHÔNG THỂ LỚN HƠN THÀNH TIỀN — **NGUỒN DUY NHẤT**, chặn nộp.
 *
 * Andy xác nhận 29/08/2026 (*"qr app đó em"* + chốt *"đã gồm cả tiền mặt + QR"*): trên
 * app của máy SBPQ, **Thành tiền ĐÃ GỒM cả tiền mặt lẫn QR**. Nên `QR ≤ Thành tiền`
 * là bất biến của **NGUỒN DỮ LIỆU**, không phải một phỏng đoán — vượt là chắc chắn gõ sai.
 *
 * ⚠️ Vì sao phải chặn chứ không chỉ cảnh báo: gõ sai kiểu này làm
 * `cashActual = (Thành tiền − QR) + lệch` ra **SỐ ÂM** ⇒ app nói cơ sở **không phải nộp
 * gì**, thậm chí công ty nợ lại họ. Đo trên sheet thật `RP20260824-0055`: QR 9.500.000đ
 * trên Thành tiền 5.920.000đ ⇒ tiền phải nộp **−1.520.000đ**. Và app **đã cảnh báo 17
 * lần** (W10) mà báo cáo vẫn nhập tiếp — cảnh báo ở đây không đủ.
 *
 * ⚠️ Chặn ở **`>` chứ không phải `>=`**: QR **bằng** Thành tiền là hợp lệ — cả ca khách
 * quét mã hết, tiền mặt bằng 0. Chặn cả trường hợp đó là chặn oan một ngày bán thật.
 *
 * ⚠️ **Chỉ áp cho dòng MAY gõ tay** (`jpMayGoTay_`). Mẫu CHUNG suy tiền từ đồng hồ, ô QR
 * ở đó là khoản khách quét cộng THÊM chứ không nằm trong `amount` — áp vào là chặn oan
 * gần hết hệ thống.
 *
 * Trả về mảng câu chữ (rỗng = không sao), gồm cả phép **CẤP BÁO CÁO**: `Σ QR > Σ Thành
 * tiền` cũng bất khả thi, và nó bắt được cả những dòng lẻ mà phép từng dòng lọt qua
 * (24/08 có 4 dòng như vậy).
 */
function jpQRVuotTien_(rows) {
  var xau = [], sQR = 0, sTT = 0, coGoTay = false;
  (rows || []).forEach(function (r) {
    if (jpStr_(r.rowKind) !== JP_ROW_MAY || !jpMayGoTay_(r)) return;
    coGoTay = true;
    var qr = jpNum_(r.bank), tt = jpNum_(r.amount);
    sQR += qr; sTT += tt;
    if (qr > tt) {
      xau.push((jpStr_(r.itemCode) || jpStr_(r.itemMisa) || ('dòng ' + jpStr_(r.seq))) +
        ': QR ' + jpMoney_(qr) + 'đ lớn hơn Thành tiền ' + jpMoney_(tt) + 'đ');
    }
  });
  if (coGoTay && sQR > sTT) {
    xau.push('CẢ BÁO CÁO: tổng QR ' + jpMoney_(sQR) + 'đ lớn hơn tổng Thành tiền ' +
             jpMoney_(sTT) + 'đ');
  }
  return xau;
}

/**
 * TRA MỘT MÃ HÀNG trong danh mục — **NGUỒN DUY NHẤT** của việc "khớp mã".
 *
 * ⚠️ VÌ SAO CẦN: `jpItemMap_` khoá theo mã THÔ, nên nhân viên gõ `100jp031` chữ thường
 * thì **không khớp** `100JP031` — mã hoàn toàn đúng mà rơi ra ngoài danh mục. Hệ quả
 * không phải chỉ mất tên hàng: `itemCode` lưu xuống giữ nguyên chữ thường, nên khi
 * duyệt xong `jpXuatKhoBaoCao_` **không tìm được lớp tồn** ⇒ cắm cờ `thieuLop`, giá vốn
 * rơi về 0đ, và **sổ 632 thiếu trong khi sổ vẫn CÂN**. Đã đo trên sheet thật 22/08/2026:
 * 4 mã đúng y nguyên chỉ khác chữ hoa (`100jp031` · `100jp038` · `100jp116` · `100jp127`).
 *
 * ⚠️ CHỈ dung thứ HOA/thường và dấu cách hai đầu — **KHÔNG** bỏ dấu, **KHÔNG** dò gần
 * giống. Gõ tắt kiểu `B chuột nước` → `100JP122` là quyết định NGHIỆP VỤ của kế toán,
 * đoán hộ là gán giá vốn của mặt hàng này sang mặt hàng khác, mà sổ vẫn cân nên không
 * phép kiểm nào bắt được. Bảng đối chiếu 69 mã gõ tự do là việc của kế toán, không phải
 * việc của hàm này.
 *
 * ⚠️ Trả về `null` khi không thấy — đừng trả `{}`, vì chỗ gọi cần phân biệt "có trong
 * danh mục" với "không có" để còn cảnh báo được.
 */
function jpTraItem_(itemMap, ma) {
  if (!itemMap) return null;
  var m = jpStr_(ma);
  if (!m) return null;
  if (itemMap[m]) return itemMap[m];
  /* Khoá HOA do `jpItemMap_` gài sẵn — xem chú thích ở đó về chốt chống nhập nhằng */
  var t = itemMap[m.toUpperCase()];
  return t || null;
}

/**
 * ĐƠN VỊ TÍNH của một mã hàng — NGUỒN DUY NHẤT.
 *
 * Form MISA `Tổng hợp Nhập Xuất Tồn` có cột ĐVT bắt buộc, và nó KHÁC NHAU từng mã:
 * đối chiếu file `Tồn kho JP đến 31.7` ra `Quả` 90 mã · `Cái` 6 · `Con` 4 · `Trái` 2
 * · `Bao` 1. Trống thì rơi về `Quả` (đa số) chứ KHÔNG để trống — ô trống là bản xuất
 * không nạp được vào MISA.
 *
 * ⚠️ Hai chỗ dùng: `jpPubItem_` (đưa xuống client) và `jpItemMap_` (dựng báo cáo kho).
 * Viết lại phép rơi-về ở chỗ thứ hai là hai cách hiểu "trống" khác nhau — đúng bài học
 * của bảy hàm phải chép sang `test/core-gia.js`.
 */
var JP_DVT_MAC_DINH = 'Quả';

function jpDvt_(item) {
  return jpStr_(item && item.dvt) || JP_DVT_MAC_DINH;
}

/**
 * NHÓM VẬT TƯ HÀNG HOÁ cho form MISA. File `Tồn kho JP đến 31.7` chia ba nhóm —
 * `JP_Hàng JP` · `PF_Pickfun` · `CCDC_Công cụ dụng cụ` — và **chỉ nhóm đầu là hàng của
 * JP**. Andy chốt 11/08/2026: *"pf và ccdc không phải của jp"*. Hai nhóm kia là
 * 239.246.277đ của khối Pickfun và công cụ dụng cụ; đưa vào `1561` của JP là ghi lấn
 * sang khối khác, đúng loại lỗi `1567` ngày 06/08 (`CCDC` còn sai cả bản chất — 153/242,
 * không phải hàng hoá).
 *
 * ⚠️ Là HẰNG SỐ, không phải cột trên `JP_Items`: mọi mã trong danh mục JP đều thuộc
 * nhóm này, mà thêm một cột không có dữ liệu thật để điền thì chỉ mời người ta đoán.
 * Ngày nào JP có nhóm hàng thứ hai thì mới thêm cột — lúc đó có số thật để điền.
 */
var JP_NHOM_VTHH = 'JP_Hàng JP';

/*
 * ĐỀ NGHỊ — loại và trạng thái. Danh sách ĐÓNG, `jpTrangThaiDeNghi_` throw nếu lạ:
 * một trạng thái không nằm trong đây là dòng không ai xử lý được mà vẫn nằm chờ.
 */
var JP_DN_TON_DAU = 'TON_DAU';
var JP_DN_CHO     = 'CHO';
var JP_DN_DUYET   = 'DUYET';
var JP_DN_TU_CHOI = 'TU_CHOI';

/** Loại máy. */
var JP_TYPE_MONEY = 'TIEN';
var JP_TYPE_COIN  = 'XU';

/**
 * Thứ tự vùng trong bản xuất DAILY SALES cho MISA — giữ đúng thứ tự này để file khớp
 * bản kế toán đang dùng. Số đầu của `unitCode` là mã vùng: 50 = HCM · 51 = Bình Dương ·
 * 52, 53 = Phú Quốc.
 *
 * Trước 04/08/2026 hằng số này nằm trong `09_Router` mà chỉ `08_LienThongKeToan` dùng —
 * chạy được vì Apps Script dùng chung scope, nhưng sai chỗ nên test không nạp được
 * `08` một mình. Hằng số thì để ở `00_Config`.
 */
var JP_KHU_VUC = ['HO CHI MINH', 'BINH DUONG', 'PHU QUOC'];

/*───────────────────────────────────────────────────────────────────────────
 * TRẠNG THÁI BÁO CÁO
 *───────────────────────────────────────────────────────────────────────────*/
var JP_ST_DRAFT    = 'NHAP';        // nhân viên đang làm
var JP_ST_PENDING  = 'CHO_DUYET';   // đã nộp
var JP_ST_FIXING   = 'CAN_SUA';     // 1 kế toán trả về
var JP_ST_DONE     = 'HOAN_TAT';    // đủ 2 chữ ký

/*
 * VAI TRÒ. Andy chốt 04/08/2026: **một tài khoản kế toán duy nhất** (`KETOAN`),
 * bỏ duyệt 2 nhánh — một chữ ký là báo cáo hoàn tất.
 *
 * `KT_DOANHTHU` / `KT_KHO` giữ lại để bản ghi cũ và báo cáo đã duyệt vẫn đọc được;
 * cả hai vẫn tính là kế toán. Tài khoản mới thì dùng `KETOAN`.
 */
var JP_ROLE_NV     = 'NHANVIEN';
var JP_ROLE_KT     = 'KETOAN';
var JP_ROLE_KT_DT  = 'KT_DOANHTHU';   // cũ — chỉ còn để đọc dữ liệu đã có
var JP_ROLE_KT_KHO = 'KT_KHO';        // cũ — chỉ còn để đọc dữ liệu đã có

/** Độ dài mã PIN. Đăng nhập chỉ bằng PIN nên PIN phải DUY NHẤT toàn hệ thống. */
var JP_PIN_LEN = 3;

/*───────────────────────────────────────────────────────────────────────────
 * SCHEMA — tên tab và cột. Tất cả tab đều tiền tố JP_ để không đụng tab cũ.
 *───────────────────────────────────────────────────────────────────────────*/
var JP_TABS = {

  /* ---- Cấu hình ---- */
  USERS: {
    name: 'JP_Users',
    /* `pin` lưu dạng băm y như password. `password` giữ lại cho bản ghi cũ,
       không dùng để đăng nhập nữa. */
    cols: ['id', 'username', 'password', 'pin', 'hoTen', 'role', 'machineType',
           'locationIds', 'active', 'createdAt', 'note']
  },
  LOCATIONS: {
    name: 'JP_Locations',
    /*
     * `unitCode` + `khuVuc` để xuất báo cáo MISA:
     *  · Unit ID trong DAILY SALES REPORT = `unitCode` bỏ số đầu (50JPAMBT → JPAMBT)
     *  · Số đầu chính là vùng: 50 = HCM · 51 = Bình Dương · 52,53 = Phú Quốc
     *  · `maKH` = Mã đối tượng trong sổ MISA (KH00119, KH00129…)
     */
    cols: ['id', 'code', 'name', 'maKH', 'unitCode', 'khuVuc',
           'machineType', 'photoDefault', 'active', 'note',
           /* Mẫu báo cáo nhân viên — xem JP_BC_MAU_TACH. Trống = mẫu chung. */
           'bcMau',
           /* Máy ở cơ sở này có đồng hồ đếm trứng không — `'N'` là KHÔNG, ẩn hai ô
              `hBefore`/`hAfter` bên hàng hoá. Trống = CÓ. Xem `jpCoDhTrung_`. */
           'coDhTrung',
           /* Cơ sở này có HAI LOẠI máy tiền lẫn nhau không — `'Y'` là CÓ, lúc đó bảng
              nhập hiện thêm cột "Loại máy" để chọn giá 1 xung từng dòng. Trống = KHÔNG,
              và giá xung khoá ở mặc định. Xem `jpChonGiaXung_`. */
           'chonGiaXung',
           /*
            * Mã định danh nộp tiền (`KH705MTDMN0041`) — thứ nhân viên gõ vào nội dung
            * chuyển khoản. Khai ở ĐÂY thì kế toán tự điền được trong app; tab
            * `JP_TenDinhDanh` là bảng DÙNG CHUNG của cả công ty, JP có thể không sở hữu.
            * ⚠️ Cột này THẮNG bảng dùng chung khi cả hai cùng có — xem `jpBanDoDinhDanh_`.
            */
           'maDinhDanh']
  },
  CLUSTERS: {                       // cụm máy — ảnh Pay Box gán ở cấp này
    name: 'JP_Clusters',
    cols: ['id', 'locationId', 'name', 'payboxSerial', 'hasQR',
           'photoCount', 'active', 'note']
  },
  MACHINES: {                       // ô/máy
    name: 'JP_Machines',
    cols: ['id', 'locationId', 'clusterId', 'code', 'itemCode', 'itemMisa',
           'photoCount', 'active', 'note']
  },
  ITEMS: {
    name: 'JP_Items',
    /* `dvt` = đơn vị tính, lấy từ file MISA `Tổng hợp tồn kho theo nhóm VTHH`
       (Andy gửi 10/08/2026). Form MISA in ĐVT khác nhau từng mã — 90 Quả, 6 Cái,
       4 Con, 2 Trái, 1 Bao — nên không được gán cứng 'Cái' như `jpGhiPhieuNhap_`
       từng làm. Ô trống thì `jpPubItem_` rơi về 'Quả' (đa số). */
    cols: ['code', 'misa', 'name', 'price', 'dvt', 'active', 'note']
  },

  /* ---- Báo cáo ---- */
  REPORTS: {                        // header 1 báo cáo
    name: 'JP_Reports',
    cols: ['id', 'createdAt', 'locationId', 'locationName', 'maKH',
           'machineType', 'fromDate', 'toDate', 'userId', 'userName',
           'status',
           /* Mẫu báo cáo CHỐT LÚC TẠO, không đọc lại từ cơ sở mỗi lần mở: kế toán
              đổi mẫu của cơ sở thì báo cáo cũ phải giữ đúng bố cục lúc nhân viên
              nhập — cùng lý do `machineType` cũng chốt ở đây. */
           'bcMau',
           'revMeter', 'revBank', 'revCashMeter',
           /* Mẫu TÁCH: tiền suy từ HÀNG (Σ bán × giá) và lệch so với tiền đồng hồ.
              Lưu để kế toán và bản xuất đọc được mà không phải tính lại. */
           'revHang', 'lechTienHang',
           'adjMachine', 'adjMachineNote',
           /* `refundCustomer` = hoàn khách KHÔNG gắn được mã (ô nhân viên gõ ở cuối).
              `refundRows` = Σ `refundAmt` của các DÒNG, máy chủ cộng ra và LƯU LẠI —
              phải lưu vì `jpCanhBaoHead_` chạy cả lúc MỞ LẠI báo cáo, lúc đó không
              có mảng dòng trong tay. Tổng thật = refundCustomer + refundRows. */
           'refundCustomer', 'refundNote', 'refundRows',
           'cashActual', 'totalSubmit',
           'submittedAt',
           /* MỘT chữ ký kế toán (chốt 04/08/2026). Bốn cột apprRevBy, apprRevAt,
              apprStockBy, apprStockAt giữ lại để báo cáo đã duyệt theo luồng
              2 nhánh cũ vẫn đọc được. */
           'apprBy', 'apprAt',
           'apprRevBy', 'apprRevAt', 'apprStockBy', 'apprStockAt',
           'rejectBy', 'rejectAt', 'rejectPart', 'rejectReason',
           /* --- NHÂN VIÊN tự báo đã nộp (POSH v3: cashPaid*) --- */
           'nvPaid', 'nvPaidDate', 'nvPayStatus', 'nvPayNote',
           /* --- KẾ TOÁN xác nhận thực nhận — 3 ô đối soát ghi vào --- */
           'paid', 'paidDate', 'payStatus',
           'warnCount', 'remark',
           /* Cảnh báo thiếu ảnh chốt lúc nộp — để kế toán mở báo cáo là thấy,
              không phải chỉ hiện thoáng trên máy nhân viên lúc bấm nộp */
           'photoWarnJson']
  },
  ZONES: {                          // khu vực nhân viên tự thêm
    name: 'JP_Zones',
    cols: ['id', 'reportId', 'seq', 'name', 'clusterId', 'note']
  },
  ROWS: {                           // 1 dòng = 1 ô/máy trong 1 kỳ
    name: 'JP_Rows',
    cols: ['id', 'reportId', 'zoneId', 'seq', 'rowKind',
           'machineId', 'machineCode',
           'itemCode', 'itemMisa', 'itemName', 'price',
           /* --- đồng hồ TIỀN (cả 2 loại máy) --- */
           'mBefore', 'mAfter', 'mActual', 'collection', 'amount',
           /* --- đồng hồ XU (chỉ máy xu) --- */
           'cBefore', 'cAfter', 'cActual',
           'cash', 'bank',
           /* --- đồng hồ ĐẾM TRỨNG (chỉ máy tiền) --- */
           'hOpen', 'hBefore', 'hAfter',
           'soldQty', 'hLeft', 'stockOut', 'topupNote',
           /* --- bảng hàng tồn máy xu --- */
           'giaXu', 'xuDaysJson', 'xuTong', 'xuLa',
           'addQty1', 'addQty2',
           'stockOpen', 'stockLeftCalc',
           'stockActual', 'defectQty', 'returnQty',
           /* --- HOÀN KHÁCH THEO MÃ HÀNG (Andy chốt 05/08/2026) ---
              `refundAmt` là ô NHÂN VIÊN GÕ (tiền trả lại khách ở đúng mã này);
              `refundQty` do máy chủ suy ra = refundAmt / price. Xem chú thích ở
              `jpHoanTheoMa_` (04_TinhToan) — đây là chỗ dễ đếm hai lần nhất. */
           'refundAmt', 'refundQty', 'refundRowNote',
           /*
            * SBPQ — BẢNG TIỀN NHÂN VIÊN TỰ ĐIỀN HẾT (Andy chốt 12/08/2026)
            * ---------------------------------------------------------------------
            * Andy: *"riêng báo cáo sân bay phú quốc … chỉ cần cột mã hàng mã misa
            * thành tiền rồi 3 cột là tiền mặt app - qr - tiền mặt thực tế"*, và
            * *"cái này sẽ là nhân viên tự điền hết"* + *"tiền thực thu mới chính là
            * tiền thu được, thêm cột lệch"*.
            *
            * SBPQ **không có đồng hồ để suy tiền** — số nằm trên app của máy. Nên với
            * mẫu TÁCH, ba con số tiền là ô NHÂN VIÊN GÕ:
            *   `amount`   = Thành tiền        (app báo bán được bao nhiêu)
            *   `cash`     = Tiền mặt app     (app báo thu tiền mặt bao nhiêu)
            *   `bank`     = QR               (khách quét, về thẳng tài khoản công ty)
            *   `cashReal` = Tiền mặt THỰC TẾ (đếm được trong két) ← CỘT MỚI
            *
            * ⚠️ `cash` ở mẫu khác là ô TỰ TÍNH (`amount − bank`). Ở mẫu TÁCH nó là ô
            * GÕ — cùng một cột sheet, hai nghĩa theo mẫu. Đừng "dọn" thành một nghĩa.
            */
           'cashReal',
           /* Giá 1 xung của CHÍNH Ô MÁY này — xem `jpGiaXung_`. Ở DÒNG chứ không ở
              khu: hai loại máy xen lẫn nhau trong cùng một khu (Andy 29/08/2026). */
           'giaXung',
           'warnJson', 'note']
  },
  /*
   * ĐỀ NGHỊ SỬA TỒN ĐẦU (Andy chốt 05/08/2026)
   * -------------------------------------------------------------------------
   * Andy: *"tồn đầu điền lần đầu và muốn sửa chỉ sửa được đầu tháng mới hoặc gửi đề
   * nghị kế toán"*. Ô tồn đầu là tồn cuối kỳ trước nên sửa tay là **viết lại quá
   * khứ** — phải có người thứ hai đồng ý.
   *
   * Cùng khuôn với `12_DeNghiChiSo` của POSH v3 (đã đọc code 05/08): nhân viên gửi,
   * kế toán duyệt hoặc từ chối, và **giữ cả số CŨ lẫn số MỚI** để sáu tháng sau còn
   * dựng lại được căn cứ. Không lưu số cũ là duyệt xong không ai biết đã sửa từ đâu.
   */
  DENGHI: {
    name: 'JP_DeNghi',
    cols: ['id', 'loai', 'reportId', 'rowId', 'locationId', 'locationName',
           'itemCode', 'itemMisa', 'itemName',
           'soCu', 'soMoi', 'lyDo',
           'userId', 'userName', 'guiLuc',
           'trangThai', 'ktBy', 'ktLuc', 'ktGhiChu']
  },
  PHOTOS: {
    name: 'JP_Photos',
    cols: ['id', 'reportId', 'scope', 'refId', 'kind', 'fileId', 'url',
           'takenAt', 'uploadedAt', 'bytes']
  },
  AUDIT: {
    name: 'JP_Audit',
    cols: ['at', 'who', 'role', 'action', 'reportId', 'target', 'detail']
  },
  PAYMENTS: {                       // nhật ký từng lần nộp tiền của nhân viên
    name: 'JP_Payments',
    cols: ['id', 'reportId', 'locationId', 'amount', 'payDate', 'method',
           'note', 'photoId', 'photoUrl', 'isSupplement',
           'createdBy', 'createdAt']
  },
  /* ---- KHO: nhập có giá mua → lớp tồn FIFO → xuất ra sổ 632 ----
   *
   * Tách 4 tab thay vì nhồi vào một, đúng cách MISA làm: phiếu nhập là chứng từ,
   * lớp tồn là số dư có giá, dòng xuất là bút toán 632. Trộn lại thì không truy
   * được giá vốn của một lần bán ra từ lô nào.
   *
   * Sheet dữ liệu có sẵn vài tab kho từ bản thiết kế cũ (không tiền tố JP_) —
   * KHÔNG dùng, vì code JP2 chưa bao giờ đọc chúng và cấu trúc không khớp.
   */
  /*
   * ── KHO HAI TẦNG (Andy chốt 04/08/2026) ────────────────────────────────
   *   Nhà cung cấp → KHO TỔNG (một kho duy nhất, HCM) → cơ sở → bán
   *
   * `khoId` là chỗ hàng đang nằm: `JP_KHO_TONG` ('TONG') hoặc chính `locationId`.
   * Xuất xuống cơ sở là ĐIỀU CHUYỂN nội bộ — chưa sinh giá vốn; lớp tồn ĐI THEO
   * hàng xuống cơ sở giữ nguyên giá mua, tới lúc bán mới ra 632.
   */
  KHO_NHAP: {                       // phiếu nhập kho từ nhà cung cấp → KHO TỔNG
    name: 'JP_KhoNhap',
    /* `loaiNhap` phân biệt BA đường hàng vào kho, xem `JP_NHAP_LOAI`. Cột này thêm
       sau nên dòng cũ để trống — mọi chỗ đọc đều coi trống là `MUA`. Không có nó
       thì sổ công nợ NCC cộng cả tồn đầu kỳ vào tiền phải trả nhà cung cấp. */
    cols: ['id', 'soChungTu', 'ngay', 'loaiNhap', 'khoId', 'locationId', 'locationName',
           'nccMa', 'nccTen', 'soDong', 'tongTien', 'ghiChu',
           'createdBy', 'createdAt', 'huyBy', 'huyAt', 'huyReason']
  },
  KHO_NHAP_CT: {                    // chi tiết phiếu nhập — 1 dòng = 1 mã hàng
    name: 'JP_KhoNhapCT',
    cols: ['id', 'nhapId', 'seq', 'itemCode', 'itemName', 'dvt',
           'qty', 'unitCost', 'amount', 'lotNo', 'note']
  },
  KHO_DC: {                         // PHIẾU XUẤT kho tổng — đầu phiếu
    name: 'JP_KhoDieuChuyen',
    cols: ['id', 'soChungTu', 'ngay', 'loai', 'khoTu', 'khoDen',
           'locationName', 'soDong', 'tongSL', 'tongTien', 'ghiChu',
           'createdBy', 'createdAt', 'huyBy', 'huyAt', 'huyReason']
  },
  KHO_DC_CT: {                      // chi tiết phiếu xuất — 1 dòng = 1 lớp bị trừ
    name: 'JP_KhoDieuChuyenCT',
    cols: ['id', 'dcId', 'seq', 'itemCode', 'itemName',
           'qty', 'unitCost', 'amount',
           'layerId', 'layerMoi', 'thieuLop', 'note']
  },
  KHO_LOP: {                        // LỚP TỒN FIFO — trái tim của giá vốn
    name: 'JP_KhoLop',
    cols: ['id', 'khoId', 'locationId', 'itemCode', 'ngay',
           'qtyInit', 'qtyRemaining', 'unitCost',
           'nguon', 'lotNo', 'createdAt']
  },
  KHO_XUAT: {                       // 1 dòng = đúng 1 dòng sổ giá vốn (632 hoặc 641)
    name: 'JP_KhoXuat',
    cols: ['id', 'soChungTu', 'ngay', 'loai', 'reportId', 'dcId',
           'khoId', 'locationId', 'locationName',
           'maKH', 'unitCode', 'itemCode', 'itemName',
           'qty', 'unitCost', 'amount', 'tkNo', 'tkCo',
           'layerId', 'thieuLop', 'createdBy', 'createdAt']
  },

  /*
   * ── KIỂM KÊ KHO ────────────────────────────────────────────────────────
   * Một chứng từ = một lần đếm một kho. Chi tiết giữ CẢ tồn sổ sách và tồn thực
   * đếm, không chỉ giữ phần lệch — vì sáu tháng sau không ai dựng lại được tồn
   * sổ sách của đúng thời điểm đó nữa (tồn được suy ngược từ hiện tại).
   */
  KHO_KK: {
    name: 'JP_KhoKiemKe',
    cols: ['id', 'soChungTu', 'ngay', 'khoId', 'locationName', 'cheDoThua',
           'soDong', 'slThua', 'slThieu', 'tienThua', 'tienThieu',
           'slGiamXuat', 'tienGiamXuat', 'nhapId', 'ghiChu', 'createdBy', 'createdAt']
  },
  KHO_KK_CT: {
    name: 'JP_KhoKiemKeCT',
    /* `cheDo` = cách xử lý dòng đó: GIAM_XUAT hay GHI_TANG. `ctGoc` = số chứng từ
       của dòng xuất bị giảm, để lần lại được điều chỉnh đi vào đâu. */
    cols: ['id', 'kkId', 'seq', 'itemCode', 'itemName',
           'tonSo', 'tonThuc', 'lech', 'unitCost', 'amount',
           'cheDo', 'ctGoc', 'tkNo', 'tkCo', 'note']
  },

  /* Phiếu trả tiền nhà cung cấp — bên "đã trả" của sổ công nợ NCC. */
  KHO_TRA_NCC: {
    name: 'JP_KhoTraNcc',
    cols: ['id', 'soChungTu', 'ngay', 'nccMa', 'nccTen', 'soTien', 'hinhThuc',
           'ghiChu', 'createdBy', 'createdAt', 'huyBy', 'huyAt', 'huyReason']
  },

  /*
   * Giao dịch ngân hàng ĐÃ ĐỌC — bảng chống áp trùng của luồng realtime.
   *
   * ⚠️ Vì sao phải có tab riêng thay vì dùng `JP_ReconLog`: `jpBatchId_` băm theo CẢ LÔ,
   * hợp với đường tải file (mỗi lần một file, lô cố định). Đọc realtime thì mỗi lượt
   * đọc ra một lô khác nhau nên băm theo lô KHÔNG chặn được gì — đọc lại sau 15 phút là
   * ghi nhận nộp tiền lần hai, và SỔ VẪN CÂN. Phải chặn theo TỪNG GIAO DỊCH.
   *
   * `refId` = mã tham chiếu của ngân hàng, là khoá duy nhất. `trangThai`:
   * `AP` đã áp vào báo cáo · `CHO` chờ kế toán quyết · `BO` kế toán bỏ qua.
   */
  BANK_GD: {
    name: 'JP_BankGD',
    cols: ['refId', 'ngay', 'soTien', 'noiDung', 'locationId', 'trangThai',
           'reportId', 'paymentId', 'docLuc', 'apLuc', 'apBoi', 'lyDo']
  },

  RECON: {                          // lịch sử áp đối soát — chống áp trùng
    name: 'JP_ReconLog',
    cols: ['at', 'who', 'batchId', 'kind', 'rows', 'matched', 'ambiguous',
           'amount', 'note',
           /* allocJson = đã ghi thêm bao nhiêu cho từng báo cáo. Không lưu thì
              huỷ lô không thể trừ lại đúng số — chỉ có tổng là vô dụng. */
           'allocJson', 'undoneAt', 'undoneBy', 'undoneReason']
  }
};

/** Loại ảnh. */
/** Loại dòng trong bảng ROWS. */
var JP_ROW_MONEY = 'MONEY';   // máy tiền — 1 dòng = 1 ô/máy (tiền + hàng)
var JP_ROW_COIN  = 'COIN';    // máy xu  — 1 dòng = 1 vị trí (2 đồng hồ + thu tiền)
var JP_ROW_STOCK = 'STOCK';   // máy xu  — 1 dòng = 1 mã hàng trong bảng tồn kho
/*
 * KHO NGOÀI (Andy chốt 04/08/2026): *"tạo cho anh thêm phần kho ngoài — nếu chỗ nào
 * anh gửi nhiều thường phải có 1 báo cáo kho kiểm kê riêng cái hàng đó"*.
 *
 * Hàng cơ sở giữ NGOÀI máy: gửi nhiều thì không nhồi hết vào máy được, phần dư nằm
 * trong kho của cơ sở và phải kiểm kê riêng. Có ở CẢ hai loại báo cáo.
 *
 * ⚠️ Dòng `NGOAI` **KHÔNG sinh tiền và KHÔNG sinh giá vốn** — nó chỉ là kiểm kê chỗ
 * hàng đang nằm. Cộng `soldQty` của nó vào là **xuất kho hai lần**, đúng cái bẫy dòng
 * `COIN` đã mắc: hàng bán ra đã đếm ở dòng `MONEY` / `STOCK` rồi.
 */
var JP_ROW_NGOAI = 'NGOAI';   // cả 2 loại — 1 dòng = 1 mã hàng giữ ngoài máy

/*
 * MẪU TÁCH TIỀN / HÀNG (Andy chốt 04/08/2026, riêng JP Sân bay Phú Quốc):
 * *"tách tiền và hàng riêng trong 1 bảng tổng và có phần chênh lệch tiền giữa 2
 * phần này để nhìn so sánh"*.
 *
 * Mẫu mặc định (`MONEY`) nhồi tiền và hàng vào CÙNG MỘT DÒNG, khoá hàng vào đúng
 * một ô máy. SBPQ có 54 mã hàng mà số ô máy ít hơn nhiều, nên hàng không gắn 1-1
 * với ô được. Mẫu này tách làm hai loại dòng:
 *
 *   `MAY`  = 1 dòng · 1 ô máy   · CHỈ tiền (đồng hồ)  → vào doanh thu, KHÔNG ra kho
 *   `HANG` = 1 dòng · 1 mã hàng · CHỈ hàng            → ra kho 632, KHÔNG vào doanh thu
 *
 * ⚠️ **HAI BẤY PHẢI GIỮ, cả hai đều là bẫy đếm hai lần** — đúng loại bẫy dòng
 * `COIN` và `NGOAI` đã mắc:
 *
 *  ① Dòng `MAY` **không được sinh `soldQty`**. `jpCalcMoneyRow_` suy số bán từ tiền
 *     (`amount ÷ giá`) khi chưa có đồng hồ trứng — để nguyên là hàng bán bị đếm ở
 *     CẢ dòng `MAY` và dòng `HANG` ⇒ **xuất kho hai lần**. `jpCalcMayRow_` ép về 0.
 *  ② Dòng `HANG` **không được sinh `amount`**. Tiền suy từ hàng là để ĐEM SO, không
 *     phải để cộng vào doanh thu; cộng cả hai là **doanh thu gấp đôi**, mà sổ vẫn
 *     cân nên không phép kiểm nào bắt được.
 *
 * ⚠️ Mọi chỗ lọc `rowKind` bằng **danh sách LOẠI TRỪ** (`if COIN || NGOAI return`)
 * thì thêm loại mới là **tự động được TÍNH VÀO**. Đúng cho `HANG` (phải ra kho),
 * SAI cho `MAY` — nên `MAY` phải thêm tay vào ba chỗ: `jpXuatKhoBaoCao_` (11_Kho),
 * `jpQuetDayChuyen` phép ① (14_ButToan), và `jpCalcReport_` thì ngược lại.
 *
 * Chọn mẫu theo **CƠ SỞ** (`JP_Locations.bcMau`), không gán cứng mã cơ sở trong
 * code: kế toán mở điểm mới cần mẫu này thì tự bật được. `machineType` GIỮ NGUYÊN
 * `TIEN` — mẫu báo cáo và loại máy là hai chuyện khác nhau, gộp lại là phải sửa cả
 * phân quyền loại máy, cấp PIN, và `jpNapCoSoJP_`.
 */
var JP_ROW_MAY  = 'MAY';      // mẫu tách — 1 dòng = 1 ô máy, CHỈ tiền
var JP_ROW_HANG = 'HANG';     // mẫu tách — 1 dòng = 1 mã hàng, CHỈ hàng

var JP_BC_MAU_CHUNG = '';        // mặc định: tiền + hàng cùng một dòng
var JP_BC_MAU_TACH  = 'TACH';    // tách tiền / hàng + bảng tổng có chênh lệch

var JP_BC_MAU_TEN = {
  '':     'Chung một bảng (tiền + hàng cùng dòng)',
  'TACH': 'Tách tiền / hàng + bảng tổng chênh lệch'
};

/*
 * LÀM TRÒN VỀ ĐỒNG CHẴN cho **tiền ghi vào dòng sổ** (sửa 04/08/2026).
 *
 * ⚠️ Đơn giá TRÊN LỚP TỒN được phép lẻ và **đừng làm tròn nó**: tồn đầu kỳ suy đơn
 * giá bằng `giaTri / soLuong`, làm tròn từng dòng rồi nhân lại là lệch 1.689đ so
 * với file MISA (xem `13_DuLieuDauKy`). Giữ nguyên thì tổng ra đúng
 * `614.132.522đ` không sai một đồng.
 *
 * Nhưng **TIỀN TRÊN DÒNG SỔ thì phải chẵn**. Hai chỗ hỏng nếu không làm tròn:
 *
 *  ① Sổ nhật ký chung in ra `1.057.567,176đ` — kế toán không đối chiếu được với
 *     MISA, mà MISA làm việc bằng đồng chẵn. Bảng cân đối cũng vậy.
 *  ② Rác dấu phẩy động: `931.443,0000000001đ` ở dòng NHẬP, dù số đúng phải là
 *     `931.443` chẵn. Đây là IEEE754, không phải giá lẻ — `qty * (giaTri / qty)`
 *     không quay lại đúng `giaTri`.
 *
 * Làm tròn ở **dòng sổ** thì cân sổ KHÔNG hỏng: cả hai vế của một cặp bút toán
 * dùng CÙNG một con số `amount`, nên `Σ Nợ = Σ Có` vẫn đúng. Đánh đổi đã biết:
 * `Σ 632` lệch với giá trị hàng thực xuất tối đa 0,5đ mỗi dòng — không tránh được,
 * và đây là cách hạch toán thông thường.
 */
function jpDong_(n) {
  var v = jpNum_(n);
  return v < 0 ? -Math.round(-v) : Math.round(v);
}

/**
 * TỔNG hoàn khách của một báo cáo — **NGUỒN DUY NHẤT**, đừng đọc thẳng cột nào.
 *
 * Hoàn khách có hai nguồn kể từ 05/08/2026 (Andy chốt hoàn khách theo mã hàng):
 *   ① `refundRows`     — Σ `refundAmt` của các dòng, gắn được vào mã hàng
 *   ② `refundCustomer` — khoản KHÔNG gắn được mã (máy kẹt, bù thiện chí)
 *
 * ⚠️ Ba chỗ dùng con số này và **cả ba phải dùng cùng một hàm**: công thức
 * `totalSubmit` (`jpCalcReport_`), bút toán `5211 / 131` (`jpButToanBaoCao_`), và phép
 * kiểm ngược `totalSubmit` (`jpKiemTraButToan`). Chỗ nào đọc thẳng `refundCustomer` là
 * chỗ đó **hụt đúng phần theo dòng** — và hụt ở phép kiểm ngược thì **mọi báo cáo có
 * hoàn theo mã đều báo lệch oan**, đúng bệnh báo sai nhiều lần rồi không ai đọc nữa.
 *
 * Báo cáo cũ không có cột `refundRows` ⇒ `jpNum_` ra 0 ⇒ tổng = `refundCustomer`,
 * đúng y như trước. Tương thích ngược không cần làm gì thêm.
 */
/**
 * Số tiền NHÂN VIÊN phải cầm về nộp — là **TIỀN MẶT**, không phải `totalSubmit`.
 *
 * Andy chốt 07/08/2026: *"phần nộp tiền là TM chứ chuyển khoản nó về thẳng công
 * ty (đó là QR khách quét đó)"*. Khách quét QR thì tiền chạy thẳng vào tài khoản
 * công ty — nhân viên **không hề cầm** khoản đó, nên bắt họ nộp là bắt nộp một số
 * tiền chưa bao giờ qua tay họ.
 *
 *     cashActual  = revCashMeter + adj − hoàn khách     ← nhân viên cầm
 *     totalSubmit = cashActual + revBank                ← công ty phải thu
 *
 * ⚠️ `totalSubmit` VẪN đúng cho **sổ công nợ của kế toán** — công ty phải thu cả
 * hai phần, và JP cố ý cho `revBank` đi qua 131 rồi mới nhận (xem `14_ButToan`).
 * Hai con số này khác nhau về nghĩa; chỗ nào nói về NHÂN VIÊN thì dùng hàm này.
 *
 * ⚠️ Dòng đời đầu chưa có cột `cashActual` thì **suy lại** `totalSubmit − revBank`
 * — đúng định nghĩa. Trả 0 cho chúng là báo "hết nợ" cho báo cáo còn thiếu tiền,
 * tức hụt thu mà bảng trông sạch sẽ.
 */
function jpNvPhaiNop_(h) {
  if (!jpBlank_(h.cashActual)) return jpNum_(h.cashActual);
  return jpNum_(h.totalSubmit) - jpNum_(h.revBank);
}

function jpHoanTong_(r) {
  return Math.abs(jpNum_(r && r.refundCustomer)) + Math.abs(jpNum_(r && r.refundRows));
}

/**
 * LỆCH TIỀN MẶT của một dòng `MAY` — Andy chốt 12/08/2026: *"tiền thực thu mới chính
 * là tiền thu được, thêm cột lệch cho tôi nhé"*.
 *
 *     lệch = Tiền mặt THỰC TẾ (đếm trong két) − Tiền mặt APP (máy báo)
 *
 * ⚠️ **So với TIỀN MẶT APP, KHÔNG so với `Thành tiền`.** Hai vế phải cùng là tiền
 * mặt: một bên app báo, một bên đếm được. `Thành tiền` đã gộp cả QR, nên trừ vào nó
 * là ra **lệch giả đúng bằng QR** trên mọi dòng có khách quét mã.
 *
 *   lệch > 0 : đếm được NHIỀU hơn app  ⇒ thừa quỹ
 *   lệch < 0 : đếm được ÍT hơn app     ⇒ THIẾU TIỀN, đúng chỗ cần thấy
 *
 * ⚠️ **Ô TRỐNG là CHƯA ĐẾM, không phải đếm được 0.** Trống mà coi là 0 thì lệch ra
 * `−(tiền mặt app)`, và vì `jpCalcReport_` đặt `adjMachine = Σ lệch` thì **tiền phải
 * nộp của cả báo cáo về 0** — mất trắng một kỳ mà sổ vẫn cân. Báo cáo SBPQ đời cũ
 * (tiền suy từ đồng hồ) chưa có cột này nên chúng đi thẳng vào đúng nhánh đó.
 *
 * ⚠️ Đây là **nguồn duy nhất** của phép trừ này. `jpCalcMayRow_` (từng dòng) và
 * `jpCalcReport_` (tổng) đều gọi nó, và client mirror `jpXemTruoc` sao lại đúng nó —
 * viết lại rời ở ba chỗ là ba con số cùng tên "lệch" rồi lệch nhau.
 */
function jpLechTM_(row) {
  if (!row) return 0;
  if (jpBlank_(row.cashReal) || jpBlank_(row.cash)) return 0;
  return jpNum_(row.cashReal) - jpNum_(row.cash);
}

/**
 * Dòng `MAY` này thuộc loại **GÕ TAY** (không có đồng hồ) hay loại suy từ đồng hồ?
 *
 * SBPQ có hai đời dữ liệu sống cùng nhau, và ba chỗ phải hiểu **giống nhau** về
 * chúng — `jpCalcMayRow_` (tính tiền), `jpThieuChiSo_` (chặn nộp), `jpCalcReport_`
 * (có suy `adjMachine` hay không). Nên hỏi ở MỘT chỗ:
 *
 *   có đồng hồ  → tiền suy ra từ chỉ số, chặn nộp theo "chỉ số sau",
 *                 và ô "Lệch máy" ở header là **ô nhân viên gõ** (máy ăn tiền)
 *   không đồng hồ → tiền là ô GÕ đọc từ APP, chặn nộp theo ba ô tiền,
 *                 và "Lệch máy" **suy ra** = Σ (thực tế − app)
 *
 * ⚠️ Ghi đè `adjMachine` trên báo cáo đời cũ là **mất tiền**: 500.000đ "máy ăn tiền"
 * nhân viên đã gõ bị thay bằng 0 ngay lượt lưu sau, mà sổ vẫn cân nên không phép
 * kiểm nào bắt được. Đó là lý do cờ này tồn tại thay vì `mActual <= 0`.
 */
function jpMayGoTay_(row) {
  /*⚠️⚠️ `mBefore = 0` KHÔNG phải "có đồng hồ" — dùng `> 0`, ĐỪNG dùng `jpBlank_`.
   *
   * Gặp thật 18/08/2026: SBPQ **không nộp được báo cáo nào**, và không có cửa nào thoát.
   * Đường đi của lỗi:
   *   `jpAddRow` ở client tạo ô máy mới với `mBefore: 0` (số 0 thật, không phải trống)
   *   ⇒ `jpBlank_(0)` là false ⇒ hàm này trả **false** ⇒ `jpThieuChiSo_` coi dòng đó là
   *   dòng đồng hồ đời cũ và **đòi `mAfter`** — mà mẫu TÁCH của SBPQ **không còn ô đó
   *   trên bảng** (Andy chốt 12/08/2026: *"cái này sẽ là nhân viên tự điền hết"*).
   *   ⇒ chặn nộp VĨNH VIỄN, nhân viên không có ô nào để điền cho hết chặn.
   * Chính chú thích trong `jpThieuChiSo_` đã cảnh báo đúng chuyện này, nhưng hàng rào
   * lại bị một số 0 vô nghĩa phá.
   *
   * ⚠️ Dòng `MAY` **chỉ có ở mẫu TÁCH** (`loaiDongMay()`: XU→COIN, TÁCH→MAY, còn lại
   * →MONEY). Nên `mBefore > 0` đúng là "báo cáo SBPQ đời cũ còn đồng hồ", và `0` đúng
   * là "không có đồng hồ nào". Bảng CHUNG không đi qua đây.
   *
   * ⚠️ Sửa ở ĐÂY, không chỉ sửa client: client sửa thì chỉ dòng TẠO MỚI TỪ NAY mới
   * đúng, còn dòng ĐÃ LƯU vẫn mang số 0 và vẫn bị chặn — tức báo cáo đang kẹt vẫn kẹt.
   * Cùng đúng một bẫy `hBefore = 0` đã sửa sáng 16/08/2026; đây là chỗ thứ hai. */
  return !!row && jpNum_(row.mBefore) <= 0 && jpBlank_(row.mAfter);
}

/** Mẫu báo cáo của một cơ sở. Giá trị lạ thì coi như mẫu mặc định. */
function jpBcMau_(v) {
  return jpStr_(v).toUpperCase() === JP_BC_MAU_TACH ? JP_BC_MAU_TACH : JP_BC_MAU_CHUNG;
}

/**
 * CƠ SỞ NÀY CÓ ĐỒNG HỒ ĐẾM TRỨNG KHÔNG — nguồn DUY NHẤT (Andy chốt 15/08/2026,
 * *"bên vinwonder pq bỏ cột before after bên hàng hoá"*).
 *
 * Máy ở VinWonders Phú Quốc không có đồng hồ đếm trứng, nên hai ô `hBefore`/`hAfter`
 * của bảng CHUNG máy tiền lúc nào cũng trống — chỉ tổ làm nhân viên phân vân.
 *
 * ⚠️ **Mặc định là CÓ.** Chỉ đúng chuỗi `'N'` mới là không. Mặc định ngược lại là mọi
 * cơ sở chưa khai cột này đều mất đường kiểm thứ hai mà không ai bấm gì cả — cùng luật
 * "danh sách CHO PHÉP" của `JP_ROW_GIU_HANG`: quên khai thì thừa một cửa kiểm (thấy
 * ngay), an toàn hơn thiếu một cửa kiểm (im lặng).
 *
 * ⚠️ Cờ này CHỈ ẨN CỘT trên giao diện. Nó **không** đụng `jpCalcMoneyRow_`: dòng nào
 * đã có `hAfter` thật thì `soldQty` vẫn suy từ đồng hồ như cũ. Đổi cả cách tính theo
 * một cờ hiển thị là đúng kiểu sai tiền mà sổ vẫn cân.
 */
function jpCoDhTrung_(v) {
  return jpStr_(v).toUpperCase() !== 'N';
}

/**
 * Cơ sở này có được CHỌN giá 1 xung theo từng dòng không? (Andy chốt 29/08/2026:
 * *"còn mấy cơ sở khác ẩn đó đi"*).
 *
 * ⚠️ **Mặc định KHÔNG** — ngược chiều với `jpCoDhTrung_`. Chiều an toàn nằm đúng phía
 * đó: chỉ SUNWORLD PQ có hai loại máy lẫn nhau, 12 cơ sở còn lại thấy ô chọn mà không
 * cần dùng là **có ngày ai đó chọn nhầm `10.000đ` và doanh thu cơ sở đó gấp đôi, mà sổ
 * vẫn cân** nên không phép kiểm nào bắt được.
 *
 * ⚠️ **KHÔNG gán cứng mã `SWPQ` vào code** — luật của repo: đừng gán cứng mã cơ sở
 * (bài học `bcMau`). Kế toán bật cờ ở Cấu hình → Cơ sở; cơ sở mới mở sau này mà cũng
 * có hai loại máy thì chỉ việc bật, không phải sửa code.
 *
 * ⚠️ Đây là cờ ĐỌC LẠI MỖI LẦN (như `coDhTrung`), không chốt lúc tạo báo cáo: kế toán
 * bật giữa buổi thì báo cáo đang nhập dở phải hiện cột ngay.
 *
 * ⚠️⚠️ Cờ này KHÔNG chỉ ẩn giao diện — **máy chủ ép giá xung về mặc định khi cờ tắt**.
 * Web ở `ANYONE_ANONYMOUS`, ai cũng gọi được `jpSaveReport` từ console trình duyệt, nên
 * ẩn ô chọn mà không chặn ở máy chủ là **không chặn gì cả**.
 * ⚠️ Hệ quả đã biết: TẮT cờ ở một cơ sở đang có dòng 10.000đ thì lượt lưu kế tiếp đưa
 * chúng về 5.000đ, tức **doanh thu những dòng đó giảm một nửa**. Báo cáo đã `HOAN_TAT`
 * không bị (không ai lưu lại nữa), nhưng báo cáo đang nhập dở thì bị — nên màn Cấu hình
 * phải nói ra điều đó ngay cạnh ô tick.
 */
function jpChonGiaXung_(v) {
  return jpStr_(v).toUpperCase() === 'Y';
}

/*
 * DÒNG NÀY CÓ GIỮ HÀNG KHÔNG — **danh sách CHO PHÉP**, dùng ở mọi chỗ xuất kho.
 *
 * ⚠️ Trước bản này ba chỗ (`jpXuatKhoBaoCao_` · `jpQuetDayChuyen` phép ① · bảng đối
 * soát hàng) mỗi chỗ tự viết một **danh sách LOẠI TRỪ** `if (COIN || NGOAI) return`.
 * Thêm một loại dòng mới là nó **tự động được tính vào** cả ba — im lặng. Đúng như
 * vậy với `HANG`, nhưng **SAI với `MAY`**: dòng máy ở mẫu tách không giữ hàng, tính
 * vào là hàng bán bị đếm ở cả `MAY` và `HANG` ⇒ **xuất kho hai lần**.
 *
 * Lần này `MAY` vô hại nhờ `jpCalcMayRow_` ép `soldQty = 0`, nhưng chú thích ở
 * `jpXuatKhoBaoCao_` đã nói rõ **đừng dựa vào việc số đó tình cờ bằng 0**. Nên đổi
 * hẳn sang danh sách cho phép: loại dòng mới **không giữ hàng cho tới khi được thêm
 * vào đây**, mà quên thêm thì thiếu giá vốn (thấy ngay ở phép ① quét dây chuyền), an
 * toàn hơn nhiều so với quên loại trừ (xuất hai lần, sổ vẫn cân).
 *
 * Dòng đời đầu chưa có `rowKind` thì coi là `MONEY` — y như `jpPubRow_` và
 * `jpCalcRow_` đang làm, không thì báo cáo cũ mất hết giá vốn.
 */
var JP_ROW_GIU_HANG = {};
JP_ROW_GIU_HANG[JP_ROW_MONEY] = true;   // máy tiền mẫu chung — tiền + hàng cùng dòng
JP_ROW_GIU_HANG[JP_ROW_STOCK] = true;   // máy xu — bảng tồn theo mã
JP_ROW_GIU_HANG[JP_ROW_HANG]  = true;   // mẫu tách — bảng hàng theo mã

function jpDongGiuHang_(rowKind) {
  return !!JP_ROW_GIU_HANG[jpStr_(rowKind) || JP_ROW_MONEY];
}

/** Ngày trong tuần dùng cho bảng số xu kiểm. */
var JP_XU_DAYS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

/** Hình thức nộp tiền. */
var JP_PAY_TM = 'TM';   // tiền mặt
var JP_PAY_CK = 'CK';   // chuyển khoản
/* QR thêm 04/08/2026. Trước đó đối soát có ba loại lô TM/CK/QR nhưng `jpReconApply`
   KHÔNG ghi dòng `JP_Payments` nào — chỉ ghi 3 ô trên báo cáo. Nên phương thức thu
   chỉ nằm trong nhật ký lô, không ở đâu tra được theo báo cáo. Hệ quả kép: sổ công
   nợ không tách được TM/CK/QR, và sổ nhật ký chung THIẾU bút toán 1111/1121 cho
   tiền vào qua đối soát. Nay đối soát ghi dòng nộp tiền thật với `method = kind`. */
var JP_PAY_QR = 'QR';   // quét mã QR — tiền về TÀI KHOẢN, hạch toán như CK

/** Tiền vào tài khoản (CK, QR) hay vào quỹ tiền mặt (TM)? Quyết TK 1121 vs 1111. */
function jpVaoTaiKhoan_(method) {
  var m = jpStr_(method).toUpperCase();
  return m === JP_PAY_CK || m === JP_PAY_QR;
}

/** Tên phương thức để in ra bảng. */
var JP_PAY_TEN = { TM: 'Tiền mặt', CK: 'Chuyển khoản', QR: 'QR' };

/** Thời hạn nhân viên được tự sửa báo cáo sau khi nộp (giờ). */
var JP_EDIT_HOURS = 24;

/*──────────── NGUỒN GIAO DỊCH NGÂN HÀNG (API realtime, Andy chốt 15/08/2026) ────────────
 *
 * Andy: *"phần đối chiếu tiền chuyển khoản đã có api chạy realtime giao dịch ngân hàng
 * về"*, kèm câu lệnh mẫu:
 *   =QUERY(IMPORTRANGE("…/1p2KS4n8sr_…"; "GiaoDich!A1:N");
 *          "select * where lower(Col8) like '%mtdmn%' and Col5 > 0 order by Col2 desc"; 1)
 *
 * ⚠️ **KHÔNG dùng IMPORTRANGE trong app.** Công thức đó phải sống trong một Ô của sheet
 * ⇒ thêm một tab trung gian; nó lại volatile — chưa cấp quyền thì ra `#REF!` **im lặng**
 * và Apps Script đọc trúng lúc chưa refresh thì ra số CŨ. Đọc thẳng bằng Apps Script là
 * MỘT nguồn, không qua trung gian, và hỏng quyền thì ném lỗi rõ ràng.
 *
 * ⚠️ **ID sheet KHÔNG nằm trong repo** — Script Property `JP_BANK_SHEET_ID`, cùng luật
 * với `JP_SHEET_ID`. Bí mật lọt vào git history là vĩnh viễn.
 */
/*
 * HAI ĐƯỜNG LẤY GIAO DỊCH, ưu tiên đường ① (Andy đề xuất 15/08/2026:
 * *"hay anh thêm công thức này vào sheet cho nó chạy tự động rồi em nối"*).
 *
 * ① **Tab công thức trong CHÍNH sheet JP** — kế toán dán
 *    `=QUERY(IMPORTRANGE("…"; "GiaoDich!A1:N"); "select * where …"; 1)` vào ô `A1` của
 *    tab `JP_BankFeed`. Ưu tiên vì **chỗ tắc chuyển sang nơi tự sửa được**: cấp quyền
 *    IMPORTRANGE là một nút ngay trong sheet, còn chia sẻ sheet cho tài khoản chủ sở hữu
 *    project thì phải mò đúng email.
 *
 * ② **Đọc thẳng sheet ngoài** bằng `jpDocSheetNgoai_` — giữ làm đường lùi.
 *
 * ⚠️⚠️ Đường ① có MỘT kiểu hỏng nguy hiểm mà đường ② không có: IMPORTRANGE hỏng thì ô
 * chứa **CHUỖI lỗi** (`#REF!` · `#N/A` · `Loading...`), Apps Script đọc ra chuỗi đó chứ
 * KHÔNG ném lỗi. Không chặn thì bộ lọc gạt sạch và app báo *"không có giao dịch mới"* —
 * **trông y hệt một ngày vắng khách**, trong khi thật ra tiền về mà không ai ghi nhận.
 * Nên `jpDocGiaoDich_` phải soi chuỗi lỗi và NÓI RA.
 *
 * ⚠️ Công thức phải giữ `select *` và tham số header `1` — bỏ `*` là cột xô lệch hết so
 * với `JP_BANK_COT`, bỏ `1` là dòng đầu thành dữ liệu và bị bỏ mất.
 */
var JP_BANK_FEED_TAB = 'JP_BankFeed';

/*──────────── BẢNG ĐỊNH DANH NỘP TIỀN — `JP_TenDinhDanh` (15/08/2026) ────────────
 *
 * ⚠️⚠️ **NGUỒN GIAO DỊCH DÙNG CHUNG CHO NHIỀU THƯƠNG HIỆU.** Bộ lọc `%mtdmn%` là marker
 * của CẢ CÔNG TY, không phải của JP — đọc thật ra thấy 52 dòng POSH và 3 dòng JP. Nội
 * dung CK có dạng:
 *
 *     NHAN TU 374670026 TRACE 144484 ND Go Di An KH705MTDMN0021
 *
 * `KH705MTDMN0021` là **mã định danh của cơ sở**, tra ở tab `JP_TenDinhDanh`. Số `0021`
 * kia là **POSH GO DĨ AN**, không phải JP.
 *
 * ⚠️⚠️ Dò cơ sở bằng tên / mã ngắn trên nguồn dùng chung này là **ghi tiền của thương
 * hiệu khác vào sổ JP** — và sổ JP vẫn CÂN, vì nó chỉ thấy một khoản thu hợp lệ. Không
 * phép kiểm nào của JP bắt được: `jpQuetDayChuyen` soi trong phạm vi JP, `jpKiemTraButToan`
 * soi hai vế. Chỉ kế toán POSH mới phát hiện, và phát hiện bằng cách thấy MẤT tiền.
 *
 * ⇒ **Mã định danh là mức khớp MẠNH NHẤT, và là mức DUY NHẤT được phép tự áp.** Gặp định
 * danh của thương hiệu khác thì **CHẶN THẲNG**, không rơi xuống mức mờ hơn.
 */
var JP_DINH_DANH_TAB = 'JP_TenDinhDanh';
/* Cột 1-based: mã đơn vị · tên · thương hiệu · mã định danh */
var JP_DINH_DANH_COT = { maDv: 2, ten: 3, thuongHieu: 4, dinhDanh: 8 };
var JP_THUONG_HIEU = 'JP';
/**
 * Hình dạng mã định danh trong nội dung CK, soi trên chuỗi đã chuẩn hoá (`jpNorm_`).
 *
 * ⚠️ **Đây CHỈ là lưới bắt mã LẠ**, không phải cách nhận ra cơ sở. `jpDoDinhDanh_` dò
 * bằng **chính danh sách mã đã khai** trong `JP_TenDinhDanh`; regex này chỉ chạy khi
 * không khớp mã nào, để nói được *"có mã định danh nhưng CHƯA KHAI"* thay vì *"không có
 * mã định danh"* — hai câu dẫn tới hai việc phải làm khác hẳn nhau.
 *
 * ⚠️ **Đừng gắn cứng `mtdmn` vào đây nữa** (bản 15/08/2026 gắn cứng, và nó sai). Khúc
 * giữa là **mã vùng của CHI NHÁNH GIỮ TÀI KHOẢN**, không phải của JP: `JP MN SC VIVO`
 * mang `KH989KVCMN0010` — vùng `KVCMN`, vì tài khoản đứng tên KVC. Gắn cứng `mtdmn` là
 * mọi khoản CK của SC VIVO **rơi khỏi lưới**, bị chặn với lý do SAI (*"nội dung không có
 * mã định danh"*) và không bao giờ tự áp.
 *
 * Nới rộng thế này chỉ có thể **thêm** lượt bắt, không thể bớt, nên an toàn một chiều:
 * bắt trúng mã đã khai của JP ⇒ tự áp đúng; trúng mã thương hiệu khác ⇒ chặn kèm đúng
 * tên thương hiệu; trúng chuỗi vô nghĩa ⇒ chặn như trước, chỉ khác câu chữ.
 */
var JP_DINH_DANH_RE = /kh\d{2,}[a-z]{2,8}\d{2,}/g;
var JP_BANK_TAB = 'GiaoDich';
var JP_BANK_LOC = 'mtdmn';        // Nội dung CK phải chứa chuỗi này (so chữ thường)

/*
 * Bản đồ cột, đánh số 1-based ĐÚNG như `Col1..Col14` của QUERY.
 *
 * ✅ **Andy đối chiếu hàng tiêu đề thật 15/08/2026** và cả bốn đều khớp:
 *   `Col1 Ma GD` · `Col2 Ngay` · `Col5 So tien vao` · `Col8 Noi dung`
 * Trước đó bốn số này chỉ suy từ câu QUERY (`order by Col2` · `Col5 > 0` ·
 * `Col8 like '%mtdmn%'`) — nay có người nhìn tận mắt.
 *
 * ⚠️ **`Col8` là NỘI DUNG chuyển khoản, không phải cột tài khoản.** Khớp với code POSH:
 * nội dung CK có dạng `KH00119 MTDMN 0001`, tức chữ `MTDMN` nằm TRONG nội dung — đó là
 * lý do câu QUERY lọc ở đúng cột đó.
 *
 * `ma` = mã tham chiếu ngân hàng, khoá chống trùng. Cột đó rỗng hoặc trùng lặp thì
 * `jpDoiSoatNganHang_` **TỪ CHỐI tự áp** và rơi về chờ duyệt tay — thà không tự động
 * còn hơn ghi nhận nộp tiền hai lần.
 */
var JP_BANK_COT = { ma: 1, ngay: 2, tienVao: 5, noiDung: 8 };

/*
 * Dò cơ sở đọc ĐÚNG cột nội dung (`Col8`), KHÔNG ghép mọi ô chữ nữa.
 *
 * ⚠️ Bản đầu ghép hết vì chưa biết nội dung nằm cột nào — chấp nhận được lúc đang đoán,
 * nhưng nay biết rồi thì ghép hết là **rủi ro**: mấy cột khác (tên người gửi, tên tài
 * khoản, ghi chú ngân hàng) có thể chứa chuỗi trùng mã hoặc tên một cơ sở khác ⇒ khớp
 * SAI cơ sở, hoặc bị chấm mơ hồ oan. Tiền vào nhầm sổ cơ sở khác mà sổ vẫn cân.
 *
 * Để `true` chỉ khi nguồn đổi và không còn biết chắc cột nào.
 */
var JP_BANK_GHEP_CHU = false;

/*───────────────────────────────────────────────────────────────────────────
 * KHO — tài khoản kế toán
 *
 * ⚠️⚠️ SỬA 06/08/2026 — BẢNG TÀI KHOẢN THẬT ĐÃ CÓ, BA ĐẦU TRƯỚC ĐÂY SAI ⚠️⚠️
 *
 * Andy gửi `Danh sách hệ thống tài khoản` (382 đầu). Bảng đó chia **theo từng
 * khối kinh doanh**, và JP có đầu RIÊNG ở mọi nhóm:
 *
 *   1561  Giá mua hàng hóa JP      1562 Funzone · 1565 DIY · 1567 ĂN UỐNG · 1568 khác
 *   5111  Doanh thu JP             5112 Funzone · 5113 Posh · …
 *   6321  Giá vốn JP               6322 Funzone · 6323 Posh · …
 *   6411  Chi phí JP               64111 lương · … · 64116 khác
 *
 * Ba đầu em đặt trước đây suy từ `Sổ chi tiết 632/641` chi nhánh **"Khu vui
 * chơi"** — mà chính `CLAUDE.md` đã ghi là file đó **không có một dòng JP nào**.
 * Suy tài khoản từ sổ của khối khác thì ra tài khoản của khối khác:
 *
 *   | Việc              | Em đặt | ĐÚNG   | 'Sai' nghĩa là gì                          |
 *   |-------------------|--------|--------|--------------------------------------------|
 *   | kho hàng hoá      | 1567   | 1561   | 1567 là **ĂN UỐNG** — ghi lấn sang khối khác |
 *   | giá vốn bán hàng  | 632    | 6321   | 632 là đầu TỔNG của mọi khối               |
 *   | xé mẫu / tặng     | 641    | 64116  | 641 là đầu TỔNG của mọi khối               |
 *
 * Nặng nhất là `1567`: hàng VÀO kho ghi `1561` (đúng) mà RA kho ghi `1567`, nên
 * **1561 phình lên mãi và 1567 của bên ăn uống âm dần** đúng bằng giá vốn JP.
 * Chính trong repo đã có dấu hiệu: kiểm kê thiếu dùng `JP_TK_KHO_KK = 1561`,
 * còn bán hàng dùng `JP_TK_KHO = 1567` — **cùng một lô hàng, hai tài khoản**,
 * tuỳ vào lý do nó rời kho. Không đường kiểm nào bắt được vì **sổ VẪN CÂN**.
 *
 * ⚠️ Dòng `JP_KhoXuat` **có LƯU `tkNo`/`tkCo` vào sheet**, nên đổi hằng số ở đây
 * chỉ ăn vào dòng MỚI. Dòng cũ phải chạy `jpDoiTkKhoCu` — xem hàm đó ở `11_Kho`.
 *───────────────────────────────────────────────────────────────────────────*/

/** Giá vốn JP. Xuất kho bán hàng ghi Nợ 6321 / Có 1561. */
var JP_TK_GIA_VON = '6321';

/** Kho hàng hoá JP — `1561 Giá mua hàng hóa JP`. KHÔNG phải 1567 (ăn uống). */
var JP_TK_KHO = '1561';

/** Tiền tố số chứng từ xuất kho. Sổ mẫu dùng XKMB####. */
var JP_XK_PREFIX = 'XKJP';

/** Tiền tố số chứng từ nhập kho. */
var JP_NK_PREFIX = 'NKJP';

/**
 * Chi phí bán hàng của JP — **Andy chốt 06/08/2026**: *"641: tk chi phí"*, rồi
 * chốt tiếp **cả xé mẫu lẫn tặng mall đều vào `64116 Chi phí khác JP`**.
 *
 * `6411 Chi phí JP` có tám con: `64111` lương · `64112` mặt bằng · `64113` chia
 * sẻ doanh thu · `64114` điện · `64115` setup · `64116` khác · `64117` hoa hồng
 * · `64118` thưởng. Hàng xé mẫu / tặng mall không thuộc con nào cụ thể ⇒ "khác".
 *
 * ⚠️ Đừng "gọn hoá" thành `641`: đó là đầu TỔNG của **mọi khối** (Funzone, Posh,
 * DIY…), ghi vào đó là trộn chi phí JP với khối khác. Cũng đừng ghi vào `6411`:
 * đầu đó CÓ CON, MISA thường không cho hạch toán vào.
 */
var JP_TK_CHI_PHI_BH = '64116';

/** Tiền tố số chứng từ điều chuyển / xuất kho tổng. */
var JP_DC_PREFIX = 'DCJP';

/** Tiền tố chứng từ kiểm kê kho và phiếu trả tiền nhà cung cấp. */
var JP_KK_PREFIX = 'KKJP';
var JP_TT_PREFIX = 'TTJP';

/*
 * HẠCH TOÁN KIỂM KÊ — Andy chốt 04/08/2026, KHÁC bản đầu của em.
 *
 * Bản đầu em dùng 1381 (tài sản thiếu chờ xử lý) / 3381 (thừa chờ xử lý). SAI.
 * Andy: *"Mất hàng phải có người đền. Nhận tiền đền thì định khoản Nợ 6321 / Có
 * TK1561. không có vào tài sản thiếu chờ xử lý. Hàng dư thì điều chỉnh giảm số
 * lượng xuất ra trong kỳ để khớp số thực tế. Trường hợp không điều chỉnh được
 * thì định khoản N1561 / Có 1388."*
 *
 *   THIẾU → **Nợ 6321 / Có 1561**  (giá vốn, vì có người đền)
 *   THỪA  → ưu tiên **GIẢM SỐ XUẤT TRONG KỲ** cho khớp thực tế; không giảm được
 *           thì **Nợ 1561 / Có 1388**
 *
 * `JP_TK_THIEU_KHO` / `JP_TK_THUA_KHO` (1381 / 3381) ĐÃ BỎ — đừng đưa lại.
 */
/*
 * ⚠️ Hai hằng dưới đây nay TRỎ VỀ CHÍNH `JP_TK_GIA_VON` / `JP_TK_KHO`, không gõ
 * lại số. Trước đây gõ rời và đó chính là chỗ để lọt lỗi `1567`: kiểm kê ghi
 * `1561` còn bán hàng ghi `1567` — cùng một lô hàng rời kho, hai tài khoản khác
 * nhau, mà không phép kiểm nào bắt được vì sổ vẫn cân. Giữ hai TÊN vì chỗ gọi
 * đọc dễ hơn, nhưng chỉ còn MỘT nguồn giá trị.
 */
var JP_TK_GIA_VON_MAT = JP_TK_GIA_VON;  // giá vốn hàng mất khi kiểm kê thiếu — 6321
var JP_TK_KHO_KK      = JP_TK_KHO;      // bên kho của chứng từ kiểm kê — 1561
var JP_TK_PHAI_THU_KHAC = '1388'; // phải thu khác — thừa mà không giảm xuất được

/** Phải trả nhà cung cấp — dùng cho sổ công nợ NCC. */
var JP_TK_NCC = '331';

/*═════════════ BÊN TIỀN / DOANH THU — Andy chốt 04/08/2026 ═════════════
 *
 * Andy: *"nhân viên gửi báo cáo sau đó kế toán duyệt và sau đó hàng và doanh thu
 * sẽ nhảy qua bán hàng và nhảy ra chuẩn bảng cân đối kế toán"*, và chốt **JP tự
 * hạch toán, đối chiếu MISA** (không phải chỉ đưa bút toán cho MISA).
 *
 * MỘT LUẬT DUY NHẤT, đừng phá: **mọi doanh thu vào 131, mọi lần thu tiền trừ
 * khỏi 131.** Nhờ vậy số dư 131 của sổ nhật ký chung PHẢI bằng `duCuoiKy` của
 * `jpSoCongNo` — hai đường suy ra độc lập nhau, nên đó là phép tự kiểm THẬT
 * (không phải in lại đẳng thức vừa dùng để tính, xem bệnh của `canBang` cũ).
 *
 * Cụ thể mỗi báo cáo `HOAN_TAT` sinh các CẶP sau (xem `jpButToanBaoCao_`):
 *
 *   131 / 5111   revMeter                    toàn bộ doanh thu máy sinh ra
 *   131 / 711    adjMachine       (nếu > 0)  đếm được nhiều hơn máy báo
 *   811 / 131    |adjMachine|     (nếu < 0)  đếm được ít hơn — lỗ
 *   5211 / 131   refundCustomer              hoàn khách, giảm trừ doanh thu
 *
 * Cộng lại: Nợ 131 = revMeter + adj − refund = cashActual + revBank
 *                  = `totalSubmit` ✓  (xem `jpCalcReport_` ở 04_TinhToan)
 *
 * Rồi mỗi dòng `JP_Payments` sinh: `1111 / 131` (TM) hoặc `1121 / 131` (CK).
 *
 * ⚠️ `revBank` CŨNG đi qua 131, không ghi thẳng `1121 / 5111`. Vì `totalSubmit`
 * của JP đã gộp cả `revBank`, và đối soát khớp lô QR/CK vào báo cáo — tức JP coi
 * tiền CK là "phải thu rồi mới nhận". Ghi thẳng vào 1121 là đếm hai lần lúc
 * dòng nộp tiền về.
 */
var JP_TK_DOANH_THU   = '5111';  // doanh thu bán hàng hoá
var JP_TK_PHAI_THU    = '131';   // phải thu khách hàng / cơ sở
var JP_TK_TIEN_MAT    = '1111';  // tiền mặt
var JP_TK_TIEN_GUI    = '1121';  // tiền gửi ngân hàng

/*
 * TÀI KHOẢN BÊN TIỀN — đã đối chiếu `Danh sách hệ thống tài khoản` (06/08/2026)
 *
 * Andy gửi bảng 382 đầu ngày 06/08. Ba đầu dưới đây **có tên khớp từng chữ**
 * trong bảng nên coi như CHỐT, bỏ khỏi `JP_TK_TAM`:
 *
 *   711  Thu nhập khác     — không có biến thể riêng cho khối nào
 *   811  Chi phí khác      — không có biến thể riêng cho khối nào
 *   5111 Doanh thu JP      — đúng đầu của JP, không phải đầu chung 511
 *
 * Ba đầu còn lại vẫn TẠM, vì bảng tài khoản cho biết ĐẦU NÀO CÓ chứ không cho
 * biết NÊN DÙNG ĐẦU NÀO — đó là chính sách hạch toán, đoán là sai tiền thật.
 *
 * `JP_VAT_SUAT = 0` nghĩa là KHÔNG tách thuế — toàn bộ số thu vào 5111. Đặt
 * khác 0 thì `jpButToanBaoCao_` tự tách `Có 33311`, không phải viết lại bút toán.
 */
var JP_TK_THU_NHAP_KHAC = '711';   // lệch máy dương — thừa tiền   ✔ đã đối chiếu
var JP_TK_CHI_PHI_KHAC  = '811';   // lệch máy âm — thiếu tiền, lỗ ✔ đã đối chiếu

/*
 * HOÀN KHÁCH → `5213 Giảm giá hàng bán`. Andy chốt 06/08/2026: *"521: tk chiết
 * khấu, giảm giá"* — tức nhóm 521 là chỗ của **chiết khấu và giảm giá**.
 *
 * Nhóm 521 có ba con, và nghiệp vụ JP loại được hai:
 *   `5211` Chiết khấu thương mại  → chiết khấu theo SẢN LƯỢNG cho khách sỉ.
 *                                   JP bán lẻ tại máy, không có khách sỉ.
 *   `5212` Hàng bán bị trả lại    → khách TRẢ LẠI hàng đã nhận. Ở JP hàng
 *                                   **chưa từng ra khỏi máy**, không có gì để trả.
 *   `5213` Giảm giá hàng bán      → ✔ đúng: khách trả 500.000đ, máy chỉ nhả 3 quả
 *                                   (300.000đ), hoàn 200.000đ — tức giảm trừ trên
 *                                   chính phần đã bán.
 *
 * ⚠️ Bản trước em đặt `5212`. Sai chỗ này: "trả lại" đòi hàng phải đã giao rồi
 * quay về, mà máy gắp thì trứng còn nằm nguyên trong máy. Cả ba con cùng dồn về
 * `521` nên lãi lỗ y nhau — chỉ khác dòng chi tiết, nhưng dòng chi tiết là thứ
 * kế toán đối chiếu với MISA.
 */
var JP_TK_GIAM_TRU_DT   = '5213';  // hoàn khách — giảm giá hàng bán

/*
 * ⚠️ `3331` là đầu CÓ CON: `33311 Thuế GTGT đầu ra` · `33312` hàng nhập khẩu.
 * Doanh thu JP là thuế đầu ra ⇒ `33311`. Hiện `JP_VAT_SUAT = 0` nên chưa sinh
 * dòng nào; cái còn tạm là THUẾ SUẤT, không phải số hiệu tài khoản.
 */
var JP_TK_THUE_GTGT     = '33311'; // thuế GTGT đầu ra
var JP_TK_DU_DAU_KY     = '4211';  // đối ứng tồn đầu kỳ khi đưa JP vào chạy

/*
 * KẾT CHUYỂN CUỐI KỲ — `911` chốt, đầu nhận lãi thì CHƯA.
 *
 * `911 Xác định kết quả kinh doanh` có thật trong bảng tài khoản Andy gửi
 * (dòng 382, "Đang sử dụng"), và là đầu DUY NHẤT — không tách theo khối kinh
 * doanh như 1561/5111/6321. Nên dùng thẳng, không phải đoán.
 *
 * ⚠️ Nhưng `421` thì **KHÔNG có đầu con nào của JP**, và hai con đang có là của
 * KHỐI KHÁC:
 *     4211  lũy kế đến cuối NĂM TRƯỚC
 *     4212  Lợi nhuận KH        (Undistributed P&L KH&FG)
 *     4213  Lợi nhuận TQ        (Undistributed P&L China)
 * Kết chuyển lãi của JP vào `4212` là **đẩy lãi JP sang khối KH** — đúng y cái
 * lỗi `1567` (ghi lấn sang kho ăn uống) mà 06/08 vừa phải đi sửa cả sổ. Nên để
 * TRỐNG và nói ra, đừng chọn hộ. Kế toán khai vào đây khi đã chốt.
 */
var JP_TK_KQKD  = '911';   // xác định kết quả kinh doanh — ĐÃ CHỐT
var JP_TK_LAI_LO = '';     // đầu nhận lãi/lỗ cuối kỳ — CHƯA CHỐT, cố ý để trống


/** Thuế suất GTGT của doanh thu máy gắp. 0 = không tách thuế. */
var JP_VAT_SUAT = 0;

/** Danh sách TK còn TẠM — giao diện in ra để kế toán biết mà sửa. */
var JP_TK_TAM = [
  { tk: JP_TK_THUE_GTGT,     viec: 'số hiệu ĐÃ đúng (33311 thuế GTGT đầu ra); còn tạm là ' +
                                   'THUẾ SUẤT — hiện KHÔNG tách (JP_VAT_SUAT = 0)' },
  { tk: JP_TK_DU_DAU_KY,     viec: 'đối ứng tồn kho đầu kỳ 31/07 — 4211 lợi nhuận chưa ' +
                                   'phân phối; có thể kế toán muốn 411 vốn chủ sở hữu' },
  { tk: '421?',              viec: 'đầu nhận LÃI/LỖ khi kết chuyển 911 cuối kỳ — bảng tài ' +
                                   'khoản KHÔNG có con nào của JP (4212 là "Lợi nhuận KH", ' +
                                   '4213 là "Lợi nhuận TQ"). Chọn bừa là đẩy lãi JP sang ' +
                                   'khối khác, y như lỗi 1567. Kế toán chốt rồi khai vào ' +
                                   'JP_TK_LAI_LO' }
];

/*
 * Tên tài khoản để in ra bảng cân đối — chép ĐÚNG TỪNG CHỮ từ `Danh sách hệ
 * thống tài khoản` Andy gửi 06/08/2026. Thiếu tên thì in số không, không chết.
 *
 * ⚠️ Mấy đầu JP **không dùng nữa** (`632` · `641` · `1567` · `5211` · `1381`)
 * vẫn phải có tên ở đây, vì dòng kho CŨ còn lưu chúng cho tới khi chạy
 * `jpDoiTkKhoCu`. Bỏ tên đi là sổ in ra số trần không ai đọc được.
 *
 * ⚠️ `1381` trong bảng này là **"Tiền cọc đối tác"**, KHÔNG phải "tài sản thiếu
 * chờ xử lý" như sách giáo khoa. Đây là thêm một lý do nữa để đừng bao giờ đưa
 * lại 1381/3381 vào nhánh kiểm kê (Andy đã chốt bỏ từ 04/08).
 */
var JP_TEN_TK = {
  '111': 'Tiền mặt',                    '1111': 'Tiền mặt MN',
  '1112': 'Tiền mặt MB',
  '112': 'Tiền gửi không kỳ hạn',       '1121': 'Tài khoản ngân hàng công ty',
  '131': 'Phải thu của khách hàng',
  '138': 'Phải thu khác',               '1381': 'Tiền cọc đối tác',
  '1388': 'Phải thu khác',
  '156': 'Hàng hóa',                    '1561': 'Giá mua hàng hóa JP',
  '1567': 'Giá mua hàng hóa ăn uống',   '1568': 'Giá mua hàng hóa khác',
  '331': 'Phải trả cho người bán',
  '333': 'Thuế và các khoản phải nộp Nhà nước',
  '3331': 'Thuế giá trị gia tăng phải nộp',
  '33311': 'Thuế GTGT đầu ra',
  '338': 'Phải trả, phải nộp khác',     '3381': 'Tài sản thừa chờ giải quyết',
  '133': 'Thuế GTGT được khấu trừ',
  '1331': 'Thuế GTGT được khấu trừ của hàng hóa, dịch vụ',
  '421': 'Lợi nhuận sau thuế chưa phân phối',
  /* ⚠️ Ba con của 421 chép ĐÚNG bảng thật: 4211 là "cuối NĂM trước" (bản trước
     em ghi "cuối KỲ trước" — sai một chữ nhưng đổi nghĩa), còn 4212/4213 là của
     KHỐI KHÁC. Để tên thật ở đây để ai đọc bảng cân đối cũng thấy ngay vì sao
     JP không được kết chuyển lãi vào chúng. */
  '4211': 'Lợi nhuận sau thuế chưa phân phối lũy kế đến cuối năm trước',
  '4212': 'Lợi nhuận KH',               '4213': 'Lợi nhuận TQ',
  '511': 'Doanh thu bán hàng và cung cấp dịch vụ',
  '5111': 'Doanh thu JP',
  '521': 'Các khoản giảm trừ doanh thu',
  '5211': 'Chiết khấu thương mại',      '5212': 'Hàng bán bị trả lại',
  '5213': 'Giảm giá hàng bán',
  '632': 'Giá vốn hàng bán',            '6321': 'Giá vốn JP',
  '641': 'Chi phí bán hàng',            '6411': 'Chi phí JP',
  '64116': 'Chi phí khác JP',
  '711': 'Thu nhập khác',
  '811': 'Chi phí khác',
  '821': 'Chi phí thuế thu nhập doanh nghiệp',
  '911': 'Xác định kết quả kinh doanh'
};

/*
 * BẢN ĐỒ ĐỔI TÀI KHOẢN CHO DÒNG KHO CŨ — dùng bởi `jpDoiTkKhoCu` (`11_Kho`).
 *
 * Chỉ đổi ĐÚNG những đầu đã xác định là sai khối. Cố ý KHÔNG đổi `5211`: hoàn
 * khách không lưu vào dòng kho, nó suy ra mỗi lần gọi nên tự đổi theo hằng số.
 */
var JP_TK_DOI_CU = {
  '1567': JP_TK_KHO,        // ăn uống → giá mua hàng hóa JP
  '632':  JP_TK_GIA_VON,    // đầu tổng → giá vốn JP
  '641':  JP_TK_CHI_PHI_BH  // đầu tổng → chi phí khác JP
};

/*
 * BA ĐƯỜNG HÀNG VÀO KHO. Cùng một bảng `JP_KhoNhap`, khác `loaiNhap`:
 *   MUA      → mua của nhà cung cấp. ĐÂY LÀ ĐƯỜNG DUY NHẤT sinh công nợ NCC.
 *   DAU_KY   → khai tồn đầu kỳ lúc bắt đầu dùng hệ thống. Có hàng, KHÔNG nợ ai.
 *   KIEM_KE  → phần THỪA phát hiện khi kiểm kê. Có hàng, KHÔNG nợ ai.
 * Dòng cũ chưa có cột này thì coi là `MUA`.
 */
var JP_NHAP_MUA = 'MUA', JP_NHAP_DAU_KY = 'DAU_KY', JP_NHAP_KIEM_KE = 'KIEM_KE';
var JP_NHAP_LOAI = {
  MUA:     { ten: 'Mua hàng nhà cung cấp', congNoNcc: true,  tkCo: JP_TK_NCC },
  DAU_KY:  { ten: 'Số dư đầu kỳ',          congNoNcc: false, tkCo: '' },
  KIEM_KE: { ten: 'Kiểm kê thừa',          congNoNcc: false, tkCo: JP_TK_PHAI_THU_KHAC }
};

/*
 * Hai loại dòng sổ sinh từ kiểm kê — tách khỏi BAN / XE_MAU / TANG_MALL:
 *   KK_THIEU     → Nợ 6321 / Có 1561, số lượng DƯƠNG (hàng ra khỏi kho)
 *   KK_GIAM_XUAT → dòng ĐẢO của một dòng xuất cũ, số lượng ÂM. Đây là cách
 *                  "giảm số lượng xuất ra trong kỳ" mà không sửa vào lịch sử:
 *                  dòng gốc còn nguyên, dòng đảo mang dấu trừ, tổng tự khớp.
 */
var JP_SO_KK_THIEU = 'KK_THIEU';
var JP_SO_KK_GIAM_XUAT = 'KK_GIAM_XUAT';

/** Hai chế độ xử lý hàng THỪA khi kiểm kê. */
var JP_KK_GIAM_XUAT = 'GIAM_XUAT';   // ưu tiên: giảm số xuất trong kỳ
var JP_KK_GHI_TANG  = 'GHI_TANG';    // không giảm được: Nợ 1561 / Có 1388

/*
 * KHO TỔNG là một kho duy nhất (Andy chốt 04/08/2026: PQ chỉ là điểm trung
 * chuyển, không phải kho hạch toán riêng). Dùng chuỗi cố định này làm `khoId`,
 * KHÔNG dùng id cơ sở nào — để không bao giờ lẫn kho tổng với một cơ sở.
 */
var JP_KHO_TONG = 'TONG';

/*
 * NĂM LOẠI XUẤT KHO TỔNG. Loại quyết định hạch toán, nên đừng thêm loại mới mà
 * không nói rõ nó vào tài khoản nào:
 *
 *   XUAT_CS   → xuống cơ sở. ĐIỀU CHUYỂN nội bộ, KHÔNG sinh giá vốn. Lớp tồn đi
 *               theo hàng xuống cơ sở; bán ở cơ sở mới ra 6321.
 *   TRA_KHO   → cơ sở trả hàng lên kho tổng. Ngược chiều XUAT_CS.
 *   XE_MAU    → xé ra trưng bày, không bán được nữa ⇒ Nợ 64116 / Có 1561.
 *   TANG_MALL → tặng mall / khách ⇒ cũng 64116, nhưng tách riêng để giải trình.
 *   ⚠️ Ba số trên sửa 06/08/2026 — trước đó chú thích ghi `641` / `632` / `156x`,
 *      là ĐẦU TỔNG của mọi khối chứ không phải đầu của JP. Code đã trỏ về
 *      `JP_TK_CHI_PHI_BH` / `JP_TK_GIA_VON` nên đúng; chỉ chú thích còn nói số cũ
 *      — mà đọc chú thích thay cho đọc code đúng là cách bỏ lọt.
 *   DIEU_CHUYEN → gửi đi tỉnh khác (Nha Trang, HN, PQ). Ra khỏi phạm vi JP v2
 *               quản lý, không sinh giá vốn — chỉ giảm tồn kho tổng.
 */
var JP_XUAT_CS      = 'XUAT_CS';
var JP_XUAT_TRA     = 'TRA_KHO';
var JP_XUAT_XE_MAU  = 'XE_MAU';
var JP_XUAT_TANG    = 'TANG_MALL';
var JP_XUAT_DC      = 'DIEU_CHUYEN';

/** Loại xuất nào SINH DÒNG SỔ, và vào tài khoản nào. */
var JP_XUAT_LOAI = {
  XUAT_CS:     { ten: 'Xuất xuống cơ sở',   tk: '',                 dauKho: -1, sangCoSo: true },
  TRA_KHO:     { ten: 'Cơ sở trả về kho',   tk: '',                 dauKho: +1, sangCoSo: false },
  XE_MAU:      { ten: 'Xé mẫu / trưng bày', tk: JP_TK_CHI_PHI_BH,   dauKho: -1, sangCoSo: false },
  TANG_MALL:   { ten: 'Tặng mall / khách',  tk: JP_TK_CHI_PHI_BH,   dauKho: -1, sangCoSo: false },
  DIEU_CHUYEN: { ten: 'Gửi đi tỉnh khác',   tk: '',                 dauKho: -1, sangCoSo: false }
};

/** Loại dòng trong sổ giá vốn: bán hàng (632) hay chi phí bán hàng (641). */
var JP_SO_BAN    = 'BAN';
var JP_SO_XE_MAU = 'XE_MAU';
var JP_SO_TANG   = 'TANG_MALL';

var JP_PHOTO_METER  = 'METER';   // ảnh chỉ số máy — gán theo Ô/MÁY
var JP_PHOTO_PAYBOX = 'PAYBOX';  // ảnh Pay Box/QR — gán theo CỤM
var JP_PHOTO_PROOF  = 'PROOF';   // ảnh chứng từ nộp tiền — gán theo BÁO CÁO

/*───────────────────────────────────────────────────────────────────────────
 * MÃ CẢNH BÁO
 *───────────────────────────────────────────────────────────────────────────*/
var JP_WARN = {
  MONEY_MISMATCH : { code: 'W1', part: 'REV',   msg: 'Tiền theo đồng hồ lệch so với đồng hồ đếm trứng' },
  STOCK_MISMATCH : { code: 'W2', part: 'STOCK', msg: 'Hàng thực tế lệch so với tồn tính toán' },
  COIN_VS_MONEY  : { code: 'W3', part: 'REV',   msg: 'Hai đồng hồ COIN và MONEY không khớp' },
  VS_PAYBOX      : { code: 'W4', part: 'REV',   msg: 'Không khớp Pay Box (tiền mặt + chuyển khoản)' },
  METER_BACKWARD : { code: 'W5', part: 'REV',   msg: 'Chỉ số sau nhỏ hơn chỉ số trước' },
  MISSING_REASON : { code: 'W6', part: 'REV',   msg: 'Có điều chỉnh nhưng chưa ghi lý do' },
  MISSING_PHOTO  : { code: 'W7', part: 'STOCK', msg: 'Thiếu ảnh so với cấu hình' },
  METER_MISSING  : { code: 'W8', part: 'REV',   msg: 'Chưa nhập chỉ số sau — chưa tính được tiền' },
  /* ⚠️⚠️ `gop` là danh sách CHO PHÉP gộp, y hệt `JP_ROW_GIU_HANG`. Chỉ cảnh báo mang
     `gop` mới được `jpGopCanhBao_` thu về MỘT dòng; không có `gop` thì in từng cái.
     Chiều an toàn nằm đúng phía đó: quên thêm `gop` là màn hơi dài (thấy ngay), còn
     thêm nhầm là **giấu mất một cảnh báo thật** sau một con số đếm — không ai thấy.
     `gopDv` là đơn vị của `so`, vì tổng của tiền và tổng của xu không cùng loại. */
  PRICE_REMAINDER: { code: 'W9', part: 'REV',   msg: 'Tiền không chia hết cho giá 1 trứng',
                     gop: 'DU_TIEN', gopDv: 'đ',
                     gopGhiChu: 'máy nhả tiền theo xung nên dư lẻ là bình thường' },
  /* SBPQ — bảng tiền nhân viên tự điền (Andy chốt 12/08/2026). Hai phép kiểm riêng:
     tiền mặt đếm được vs app, và app có tự cộng khớp hay không. */
  LECH_TM_APP    : { code: 'W10', part: 'REV',  msg: 'Tiền mặt thực tế lệch so với app' },
  /* Kỳ chồng với một báo cáo ĐÃ NỘP khác của cùng cơ sở. CẢNH BÁO, KHÔNG CHẶN —
     cùng luật với "thiếu ảnh thì cảnh báo, không chặn nộp": có thể có tình huống
     thật cần kỳ chồng mà ta chưa biết, chặn cứng là khoá cơ sở giữa ca. */
  KY_CHONG       : { code: 'W11', part: 'REV',  msg: 'Kỳ chồng với báo cáo khác' },

  /*═════════════════════════════════════════════════════════════════════════
   * TÁCH RA KHỎI `W9` (Andy 16/08/2026: *"gộp W9 thành một dòng đi em"*)
   *
   * ⚠️⚠️ `W9` trước đây mang **NĂM** chuyện khác nhau — dư tiền · dư xu · hoàn khách
   * lẻ · mã không suy ra được giá — mà **câu tiêu đề chỉ nói một chuyện**. Nghĩa là
   * ba trong năm trường hợp đang hiện ra một dòng chữ **nói sai việc đang xảy ra**.
   *
   * Đây không phải chuyện dọn cho đẹp: **gộp mà chưa tách là GIẤU MẤT `W14`**. Gộp
   * theo `code` thì "Bán 7 cái mà mã X không suy ra được giá" — cảnh báo nói rằng
   * **không đối chiếu được tiền với hàng** — biến thành một con số trong câu
   * "Tiền không chia hết cho giá 1 trứng (15 dòng)". Sổ vẫn cân, màn hình vẫn sạch.
   *═════════════════════════════════════════════════════════════════════════*/
  COIN_REMAINDER : { code: 'W12', part: 'REV',  msg: 'Tổng xu không chia hết cho giá xu',
                     gop: 'DU_XU', gopDv: ' xu' },
  /* Hoàn khách lẻ: KHÔNG gộp. Hiếm (1 dòng trên cả sheet 16/08) và mỗi cái là một
     khoản tiền cụ thể phải giải thích — gộp là mất đúng con số cần nhìn. */
  REFUND_REMAINDER:{ code: 'W13', part: 'REV',  msg: 'Hoàn khách không chia tròn cho giá 1 trứng' },
  /* ⚠️ TUYỆT ĐỐI không cho `gop`. Đây là cảnh báo NẶNG NHẤT trong nhóm: mã hàng không
     suy ra được giá thì **cả dòng đó không đối chiếu tiền với hàng được**, tức mất
     hẳn một đường kiểm — chứ không phải "lẻ vài nghìn là bình thường". */
  /* ⚠️ Câu này phải nói HẬU QUẢ, không chỉ nói trạng thái. Bản trước chỉ ghi "không đối
     chiếu được với tiền" — đúng nhưng đó là hậu quả NHẸ hơn. Hậu quả nặng là: mã không
     suy ra được giá thì gần như chắc chắn nó không có trong danh mục, nên khi duyệt xong
     kho KHÔNG tìm được lớp tồn ⇒ giá vốn về 0đ và **sổ 632 thiếu trong khi sổ vẫn CÂN**.
     Đúng bài học của `jpKetKy_` (11/08): nói trạng thái thì người đọc tự cho là bình
     thường; phải nói ra thứ sẽ mất. Đo trên sheet thật 22/08/2026: 1.173 cái đã bán
     mang mã ngoài danh mục. */
  PRICE_NO_RATE  : { code: 'W14', part: 'REV',
                     msg: 'Mã hàng không suy ra được giá — không đối chiếu được với tiền, ' +
                          'và nếu mã không có trong danh mục thì duyệt xong kho không tìm ' +
                          'được lớp tồn nên giá vốn về 0đ (sổ 632 thiếu)' },

  /*═════════════════════════════════════════════════════════════════════════
   * LỆCH MÁY VƯỢT NGƯỠNG (Andy chốt 29/08/2026)
   *
   * Andy hỏi *"báo cáo của nhân viên có cột thực tế không vì đôi khi sai số so với chỉ
   * số máy"*, rồi chốt *"đếm gộp cả ca thôi, làm cảnh báo vượt ngưỡng đi em"*.
   *
   * ⇒ **KHÔNG** thêm cột "tiền mặt thực tế" theo từng dòng cho mẫu CHUNG. Cột đó chỉ
   * đúng khi nhân viên đếm tiền TÁCH THEO TỪNG MÁY (mẫu TÁCH của SBPQ làm được vì họ
   * đếm vậy); nhân viên mẫu CHUNG đếm gộp cả ca, nên bắt họ điền từng dòng là **họ
   * chia bừa cho đủ** — lúc đó cột đó tệ hơn ô tổng đang có, vì nó trông như số đo
   * được mà thật ra là số bịa.
   *
   * ⚠️ Ô "Lệch máy" ở đầu báo cáo VẪN là ô gõ như cũ, KHÔNG đụng vào. Cảnh báo này chỉ
   * đọc con số đó rồi so với doanh thu.
   *═════════════════════════════════════════════════════════════════════════*/
  LECH_MAY_LON   : { code: 'W15', part: 'REV',
                     msg: 'Lệch máy lớn bất thường so với doanh thu' },

  /*
   * W16 — TIỀN THỪA ĐÃ ĐƯỢC QUY RA TRỨNG (Andy 31/08/2026).
   *
   * Andy nhìn dòng lệch `+100.000đ`: *"Này cộng thêm tiền ví dụ + 100k phải trừ thêm
   * 1 trứng mới đúng á e"*. Đúng — đồng hồ không đếm được cú đó nhưng quả trứng thì
   * ĐÃ RA KHỎI MÁY, nên `soldQty` phải tăng và tồn phải giảm theo.
   *
   * ⚠️ Cảnh báo này **part REV nhưng nói về HÀNG** — cố ý gắn REV vì nó là hệ quả của
   * con số tiền. Nó tồn tại để kế toán thấy vì sao `Đã bán` KHÔNG bằng `tiền đồng hồ ÷
   * giá`: không có nó thì con số tự nhảy lên và trông như lỗi tính.
   */
  LECH_QUY_TRUNG : { code: 'W16', part: 'REV',
                     msg: 'Tiền thừa đã được quy ra số trứng bán thêm' },

  /*
   * W17 — CÓ TIỀN QR MÀ KHÔNG CÓ CHỖ NÀO GẮN ẢNH PAY BOX (Andy 08/09/2026).
   *
   * Dải ảnh **Pay Box / QR** gắn theo **CỤM**, không gắn theo khu vực: nó chỉ hiện khi
   * khu vực đã **chọn một cụm** và cụm đó mang `hasQR`. Khu để trống ô "— chọn cụm —"
   * thì `hasQRCluster` trả `false` ⇒ **dải ảnh không xuất hiện**, và trước bản này
   * **không có cảnh báo nào**: báo cáo nộp được với tiền QR mà không kèm một tấm ảnh
   * Pay Box nào.
   *
   * Đo trên sheet thật 08/09: SBPQ có **0 cụm** trong khi 9 báo cáo đều có QR, và đúng
   * ở đó `RP20260824-0055` lệch **1,52 triệu** vì QR. Tức bằng chứng QR thiếu ở đúng
   * nơi đang cần nó nhất.
   *
   * ⚠️ `part: 'STOCK'` giống `MISSING_PHOTO` — cùng một họ *thiếu bằng chứng*, để kế
   * toán nhóm chung khi soi. Nhưng **mã RIÊNG**, không gộp vào `W7`: việc phải làm khác
   * hẳn (đi CHỌN CỤM, không phải đi chụp thêm ảnh), và gộp mã là câu hướng dẫn sai.
   */
  THIEU_CUM_QR   : { code: 'W17', part: 'STOCK',
                     msg: 'Có tiền QR/CK mà chưa chọn cụm nên không có chỗ gắn ảnh Pay Box' }
};

/*
 * NGƯỠNG CẢNH BÁO LỆCH MÁY — phải qua **CẢ HAI** mới kêu.
 *
 * ⚠️⚠️ Hai điều kiện chứ không phải một, và đây là chỗ quyết định cảnh báo này có ai
 * đọc hay không:
 *   · chỉ theo **phần trăm** ⇒ báo cáo doanh thu 400.000đ lệch 50.000đ là 12,5% ⇒ kêu,
 *     mà 50.000đ thì không ai đi truy. Kêu vặt vài lần là **không ai đọc nữa** — đúng
 *     bệnh `canBang` cũ đã ghi ở `CLAUDE.md`.
 *   · chỉ theo **số tuyệt đối** ⇒ cơ sở doanh thu 200 triệu lệch 300.000đ (0,15%) cũng
 *     kêu, mà mức đó là đếm tiền bình thường.
 * Qua cả hai thì nó vừa **đáng tiền để đi truy**, vừa **lớn so với quy mô cơ sở đó**.
 *
 * ⚠️ Doanh thu = 0 mà vẫn có lệch thì `pct × 0 = 0` nên chỉ còn sàn tuyệt đối chặn —
 * ĐÚNG như mong muốn: lệch tiền trên một kỳ không bán được gì là chuyện phải hỏi ngay.
 *
 * ⚠️ Đổi hai số này thì đổi ở ĐÂY, đừng viết số cứng vào câu cảnh báo — `soat-tinh.py`
 * phép ⑫ tồn tại vì đúng loại lỗi "máy chủ đổi mà màn hình dạy số cũ".
 */
var JP_LECH_MAY_PCT = 0.05;      // 5% doanh thu theo đồng hồ
var JP_LECH_MAY_SAN = 200000;    // và ít nhất 200.000đ


