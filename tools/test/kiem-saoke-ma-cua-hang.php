<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ — MÃ CỬA HÀNG CỦA VIỆT QR LÀ ĐƯỜNG DUY NHẤT BIẾT DÒNG "PaymentForOrder" CỦA MÁY NÀO
 *
 * Anh Thắng 12/09/2026, hai ảnh cạnh nhau — bảng "Từ cổng Việt QR" đầy dòng *"chưa rõ máy"* với
 * nội dung `VQR2637642208V7L PaymentForOrder`, còn bản kết xuất của cổng thì mỗi giao dịch có
 * thêm **Mã cửa hàng**: *"một số giao dịch nội dung không rõ ràng, mà webhook gửi về thì thiếu
 * thông tin, vậy để xác nhận giao dịch đó của ai, thì mình sẽ tải thẳng sao kê của bên VietQR.
 * Nó có đủ các trường để xác định giao dịch đó là của máy này."*
 *
 * 🔴 BÀI NÀY LÀ BỘ THỬ ĐẦU TIÊN CỦA `vhcp-saoke`. Trước nó, plugin sao kê KHÔNG có phép thử nào
 *    (20 bài trong `tools/test/` đều của ghế/vé), nên mọi thay đổi ở đây là sửa mù.
 *
 * ⚠️ CHẠY LỚP THẬT, không chép luật ra đây: nạp thẳng `vhcp-saoke.php` với một bệ đỡ WordPress
 *    giả (option trong mảng, `$wpdb` giả). Chép luật ra bài thử là bài thử canh chính nó.
 *
 * Chạy: php tools/test/kiem-saoke-ma-cua-hang.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

/* Bệ đỡ WordPress giả + t()/teq()/goi()/ket_luan() nằm chung một chỗ cho mọi bài thử sao kê —
   chép lại bệ đỡ ở bài thứ hai là hai bản sẽ lệch nhau lúc nào không hay. */
require_once __DIR__ . '/lib/be-saoke.php';

require_once $GOC . '/vhcp-saoke/vhcp-saoke.php';
t( 'nạp được lớp SAOKE_App', class_exists( 'SAOKE_App' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. CỘT `ma_ch` PHẢI CÓ THẬT, VÀ PHIÊN BẢN BẢNG PHẢI TĂNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 Không tăng `VER_TBL` là `bao_dam_bang()` thoát ngay ở dòng đầu trên MỌI site đang chạy —
 *    cột `ma_ch` không bao giờ được tạo, và tính năng này im lặng không hoạt động ở đúng nơi
 *    cần nó. Bảng mới chỉ dựng đúng cho site cài mới, nên lỗi này rất dễ lọt.
 */
t( '🔴 bảng saoke_cong có cột ma_ch', false !== strpos( $SRC, "ma_ch VARCHAR(40) NOT NULL DEFAULT ''" ) );
t( 'và có KEY để tra cho nhanh', false !== strpos( $SRC, 'KEY ma_ch (ma_ch)' ) );
/* ⚠️ SỐ NÀY PHẢI TĂNG MỖI LẦN ĐỔI CẤU TRÚC BẢNG, và bài thử phải đi theo. 0.20.0 thêm
   `KEY ref (ref)` cho đường dò trùng chéo nguồn -> lên '4'. Ghim số cứng ở đây là cố ý: quên
   tăng thì bài đỏ, chứ không phải site cũ âm thầm thiếu chỉ mục. */
t( "🔴 VER_TBL đã tăng (không tăng thì site cũ KHÔNG có cột)",
	false !== strpos( $SRC, "const VER_TBL = '4';" ), 'VER_TBL' );
/* Hai câu SELECT đọc bảng cổng đều phải lấy cột ấy — thiếu một câu là màn ấy vẫn "chưa rõ máy". */
t( '🔴 câu SELECT của bảng Sao Kê cổng có lấy ma_ch',
	false !== strpos( $SRC, 'so_tk, noi_dung, diem_ban, ma_ch, doc_duoc' ) );
t( '🔴 câu SELECT của phép gom tiền theo mã nộp cũng lấy ma_ch',
	false !== strpos( $SRC, 'SELECT thoi_diem, so_tien, noi_dung, diem_ban, ma_ch FROM' ) );
/* Và luật suy ra máy chỉ được viết MỘT chỗ. Bản trước chép hai lần; hai bản chép của một luật
   thì sớm muộn lệch, mà lệch ở đây là cùng một giao dịch màn này tính máy A, màn kia bỏ vào
   "chưa rõ". */
/* 🔴 PHÉP NÀY TỪNG ĐẾM HỤT, VÀ CÁI GIÁ LÀ CẢ TÍNH NĂNG KHÔNG CHẠY.
 *    0.17.0 gom luật vào `cong_may_dong()` rồi sửa HAI nơi, và phép thử đếm đúng MỘT chuỗi ký
 *    tự để khẳng định "luật chỉ còn một chỗ". Nhưng còn một bản sao thứ BA trong
 *    `rpc_getSaoKeCong()` — đúng cái hàm màn hình gọi — viết bằng chuỗi khác nên không bị đếm.
 *    Kết quả: bộ thử xanh, file nạp đúng, cột `ma_ch` có dữ liệu, mà màn hình vẫn "chưa rõ máy".
 *    Anh Thắng: *"vẫn chưa lọc hết"* — và anh đúng, không dòng nào lọc được cả.
 *
 *    Nay đếm theo LỜI GỌI, không theo một câu văn: `cong_ten_may()` chỉ được gọi từ ĐÚNG một
 *    chỗ (trong thân `cong_may_dong`), `may_hop_le()` đúng hai chỗ (trong `cong_ten_may` và
 *    trong `cong_may_dong`). Ai chép lại luật ở nơi thứ tư là con số này nhảy, và bài đỏ. */
teq( '🔴 cong_ten_may() chỉ được gọi từ MỘT chỗ (trong cong_may_dong)', 1,
	substr_count( $SRC, 'self::cong_ten_may(' ) );
teq( '🔴 may_hop_le() chỉ được gọi từ HAI chỗ (cong_ten_may + cong_may_dong)', 2,
	substr_count( $SRC, 'self::may_hop_le(' ) );
/* Bốn nơi cần biết máy: bảng Sao Kê cổng (REST) · bảng Sao Kê cổng (đường app gọi) · phép gom
   tiền theo mã nộp · lượt nạp file. Bốn nơi, MỘT luật. */
teq( 'và bốn nơi cần biết máy đều gọi đúng hàm chung ấy', 4,
	substr_count( $SRC, 'self::cong_may_dong(' ) );
/* Hàm màn hình THẬT SỰ gọi phải lấy cột `ma_ch` về — thiếu nó thì gọi hàm chung cũng vô ích. */
t( '🔴 rpc_getSaoKeCong lấy cột ma_ch trong câu SELECT',
	false !== strpos( $SRC, 'noi_dung, diem_ban, ma_ch, doc_duoc, raw, nhan_luc' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. CHUẨN HOÁ MÃ CỬA HÀNG — mã là chuỗi máy sinh, so phải khít
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'mã cửa hàng: hoa hết', 'VUOHU3TIJ2', goi( 'vqr_ma_ch', array( 'vuohu3tij2' ) ) );
teq( 'bỏ khoảng trắng hai đầu và ở giữa', 'EZFY9HCIR0', goi( 'vqr_ma_ch', array( ' EZFY9 HCIR0 ' ) ) );
teq( 'rỗng vẫn là rỗng', '', goi( 'vqr_ma_ch', array( '   ' ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. NẠP "DANH SÁCH CỬA HÀNG" — đúng bảng anh Thắng gửi
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$GLOBALS['OPT']['saoke_pin'] = '1234';
$ds_file = array(
	array( 'EZFY9HCIR0', 'LM-NSG 01', 'MC1754018340421', 'Posh Lotte Nam Sài Gòn' ),
	array( 'JGFM5XAXD6', 'LM-NSG 02', 'MC1754018340421', 'Posh Lotte Nam Sài Gòn' ),
	array( 'B9BTQ11VMU', 'sensen pvd 01', 'MC1756054296549', 'sesen city phạm văn đồng' ),
	array( '', 'thiếu mã', '', '' ),              // bỏ
	array( 'XXXX', '', '', '' ),                  // bỏ — không có tên thì bản đồ vô dụng
);
$r = SAOKE_App::rpc_napDsCuaHangVqr( array( '1234', $ds_file, 'danh-sach-cua-hang.xlsx' ) );
t( 'nạp bản đồ chạy được', ! empty( $r['ok'] ), $r );
teq( 'thêm đúng 3 cửa hàng', 3, (int) $r['themMoi'] );
teq( 'và bỏ 2 dòng thiếu mã hoặc thiếu tên', 2, (int) $r['boQua'] );
teq( 'bản đồ đang có 3', 3, (int) $r['tong'] );

/* 🔴 NẠP LẠI LÀ GỘP, KHÔNG PHẢI XOÁ HẾT RỒI GHI. Cổng cho tải từng trang và người ta hay nạp
   làm nhiều lượt — xoá hết mỗi lượt là lượt sau đá mất lượt trước mà không câu nào báo. */
$r2 = SAOKE_App::rpc_napDsCuaHangVqr( array( '1234', array(
	array( 'RDWUP4647G', 'sensen pvd 03', 'MC1756054296549', 'sesen city phạm văn đồng' ),
	array( 'EZFY9HCIR0', 'LM-NSG 01', 'MC1754018340421', 'Posh Lotte Nam Sài Gòn' ),   // y nguyên
	array( 'JGFM5XAXD6', 'LM-NSG 02B', 'MC1754018340421', 'Posh Lotte Nam Sài Gòn' ),  // đổi tên
), 'trang-2.xlsx' ) );
teq( '🔴 nạp trang 2 KHÔNG xoá trang 1', 4, (int) $r2['tong'] );
teq( 'thêm 1 mới', 1, (int) $r2['themMoi'] );
teq( 'sửa 1 dòng đổi tên', 1, (int) $r2['daSua'] );
teq( 'và 1 dòng y nguyên', 1, (int) $r2['yNguyen'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. SUY RA MÁY — thứ tự có chủ ý, và mỗi bước phải đo riêng
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '1) tên nằm trong nội dung thì lấy luôn', 'AMBD 12',
	goi( 'cong_may_dong', array( 'VQR263764167LJRO AMBD 12', '', '' ) ) );
/* 🔴 ĐÂY LÀ CÂU TRẢ LỜI CHO ANH THẮNG: nội dung PaymentForOrder + mã cửa hàng -> ra tên máy. */
teq( '🔴 2) nội dung PaymentForOrder + MÃ CỬA HÀNG -> ra đúng máy', 'LM-NSG 01',
	goi( 'cong_may_dong', array( 'VQR2637642208V7L PaymentForOrder', 'EZFY9HCIR0', '' ) ) );
teq( '   và mã gõ thường vẫn tra được', 'LM-NSG 02B',
	goi( 'cong_may_dong', array( 'VQR2637642208V7L PaymentForOrder', 'jgfm5xaxd6', '' ) ) );
teq( '3) không có mã thì lùi về diem_ban như cũ', 'AEON MALL BÌNH TÂN',
	goi( 'cong_may_dong', array( 'VQR2637 PaymentForOrder', '', 'Aeon Mall Bình Tân' ) ) );
/* 🔴 KHÔNG BIẾT THÌ NÓI KHÔNG BIẾT. Mã lạ (chưa nạp vào bản đồ) tuyệt đối không được biến
   thành một cái "máy" mang tên mã — tiền sẽ nằm dưới một cửa hàng không có thật. */
teq( '🔴 4) mã lạ chưa có trong bản đồ thì vẫn "chưa rõ máy", KHÔNG bịa', '',
	goi( 'cong_may_dong', array( 'VQR2637 PaymentForOrder', 'MA_LA_CHUA_CO', '' ) ) );
/* Nội dung THẮNG bản đồ: khách quét đúng QR của máy ấy là chắc nhất. */
teq( 'nội dung thắng bản đồ khi cả hai cùng có', 'AMBD 12',
	goi( 'cong_may_dong', array( 'VQR26376 AMBD 12', 'EZFY9HCIR0', '' ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. NẠP FILE VÀO DÒNG WEBHOOK ĐÃ CÓ — VÁ, KHÔNG THÊM DÒNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CẢ ĐIỂM CỦA VIỆC NÀY. Dòng `PaymentForOrder` webhook đã ghi từ lâu. Nạp file KHÔNG được
 *    thêm dòng (kẻo đếm tiền hai lần) nhưng PHẢI điền `ma_ch` vào dòng cũ — không vá thì file
 *    có đủ dữ liệu mà màn hình vẫn "chưa rõ máy", và người nạp không hiểu vì sao nạp xong y cũ.
 */
$db = $GLOBALS['wpdb'];
$nen = array( 'nguon' => 'vietqr', 'khoa' => 'vietqr|VPBg37hatYHLg', 'maGD' => 'VPBg37hatYHLg', 'ref' => '',
	'thoiDiem' => '11/09/2026 17:55:55', 'soTien' => 20000, 'huong' => 'Đến', 'trangThai' => 'Thành công',
	'soTK' => '', 'noiDung' => 'VQR2637642208V7L PaymentForOrder', 'diemBan' => '', 'maCH' => '',
	'docDuoc' => true, 'raw' => 'webhook' );
$kq = '';
t( 'dòng webhook đầu tiên -> thêm mới', true === goi( 'luu_cong', array( $nen, &$kq ) ) );
teq( 'và báo đúng là "moi"', 'moi', $kq );
teq( 'dòng ấy chưa có mã cửa hàng — đúng cảnh anh Thắng gặp', '', (string) $db->hang[0]['ma_ch'] );

$tu_file = array_merge( $nen, array( 'maCH' => 'EZFY9HCIR0', 'raw' => 'FILE sao-ke-vietqr.xlsx' ) );
$kq = '';
t( '🔴 nạp lại từ file KHÔNG thêm dòng thứ hai', false === goi( 'luu_cong', array( $tu_file, &$kq ) ) );
teq( 'nhưng báo là đã VÁ, không phải "trùng suông"', 'va', $kq );
teq( '🔴 và mã cửa hàng đã được điền vào dòng cũ', 'EZFY9HCIR0', (string) $db->hang[0]['ma_ch'] );
teq( 'bảng vẫn đúng MỘT dòng (không đếm tiền hai lần)', 1, count( $db->hang ) );
teq( 'tiền của dòng ấy không đổi', 20000, (int) $db->hang[0]['so_tien'] );
/* 🔴 và từ giây phút ấy màn hình đọc ra máy — đo qua đúng hàm mà màn hình dùng. */
teq( '🔴 dòng ấy nay ra đúng tên máy', 'LM-NSG 01',
	goi( 'cong_may_dong', array( $db->hang[0]['noi_dung'], $db->hang[0]['ma_ch'], $db->hang[0]['diem_ban'] ) ) );

/* ⚠️ KHÔNG ĐÈ ô đã có. Nạp lại lần hai, hay nạp một file CŨ hơn, không được đổi máy của một
   dòng đã xác định — đó là sửa lịch sử tiền. */
$de = array_merge( $nen, array( 'maCH' => 'B9BTQ11VMU' ) );
$kq = '';
goi( 'luu_cong', array( $de, &$kq ) );
teq( '🔴 file khác KHÔNG đè được mã cửa hàng đã có', 'EZFY9HCIR0', (string) $db->hang[0]['ma_ch'] );
teq( 'và lượt ấy là trùng suông', 'trung', $kq );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. MÀN HÌNH: hai ô chọn cột mới, và chỗ dễ sai nhất là ĐOÁN NHẦM CỘT MÃ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( 'khối nạp giao dịch có ô chọn cột Mã cửa hàng', false !== strpos( $APP, "id('txMaCH')" ) );
t( 'và ô chọn cột Mã điểm bán', false !== strpos( $APP, "id('txMaDiem')" ) );
t( 'khối bản đồ cửa hàng có mặt (chỉ cho Việt QR)',
	false !== strpos( $APP, "nguon !== 'vietqr' ? '' :" ) && false !== strpos( $APP, "id('bdFile')" ) );
/* 🔴 "Mã đơn hàng" PHẢI đứng trước "Mã giao dịch" trong phép đoán cột: bản kết xuất có CẢ
   "Mã tham chiếu" lẫn "Mã đơn hàng", mà cái webhook ghi vào ô Mã giao dịch cổng là "Mã đơn
   hàng". Đoán nhầm sang Mã tham chiếu thì khoá chống trùng khác hẳn -> mọi dòng thành "mới"
   -> TIỀN ĐẾM HAI LẦN. Đây là lỗi đắt nhất có thể xảy ra ở màn này. */
t( "🔴 đoán cột mã: 'ma don hang' đứng TRƯỚC 'ma giao dich'",
	false !== strpos( $APP, "timTieuDe(['ma don hang','ma giao dich'" ) );
/* Cùng loại bẫy ở bảng Danh sách cửa hàng: "cua hang" là chuỗi con của CẢ "Mã cửa hàng" lẫn
   "Tên cửa hàng". */
t( "🔴 đoán cột bản đồ: 'ma cua hang' dò trước 'ten cua hang'",
	strpos( $APP, "bdMa: tim(['ma cua hang'" ) < strpos( $APP, "bdTen: tim(['ten cua hang'" ) );
t( 'ô "chưa rõ máy" nói luôn là đã có mã CH hay chưa (hai cảnh, hai cách sửa)',
	false !== strpos( $APP, 'chưa có trong bản đồ' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6b. HAI THỨ CHỈ LỘ RA KHI CHẠY TRÊN FILE THẬT (anh Thắng gửi 12/09/2026)
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 Ô RỖNG CỦA FILE KẾT XUẤT LÀ DẤU GẠCH NGANG. Dòng "Vãng lai" trong file thật có
   `Mã cửa hàng` = `-`. Không chặn thì `-` thành một "mã" hợp lệ, nằm trong danh sách "mã chưa
   có trong bản đồ" đời đời, và người đọc đi tìm một cửa hàng tên `-`. */
teq( '🔴 mã cửa hàng "-" coi như KHÔNG CÓ', '', goi( 'vqr_ma_ch', array( '-' ) ) );
teq( 'mấy dấu gạch cũng thế', '', goi( 'vqr_ma_ch', array( ' — ' ) ) );
teq( 'nhưng mã thật thì giữ nguyên', 'M4QMOQLG7Y', goi( 'vqr_ma_ch', array( 'M4QMOQLG7Y' ) ) );

/* 🔴 DÒNG KHÔNG THÀNH CÔNG KHÔNG PHẢI LÀ TIỀN. File thật hôm nay toàn "Thành công", nhưng cửa
   nạp không đọc cột Trạng thái thì một file có dòng hỏng/hoàn sẽ vào bảng như doanh thu, rồi
   nằm im trong tổng "Từ cổng" — chỉ lộ ra ở ô "Chênh lệch (cổng − bank)" dưới dạng một con số
   không ai giải thích nổi.
   ⚠️ Bắt NGHĨA XẤU chứ không bắt nghĩa tốt: cổng đổi "Thành công" thành "Success" là bản dịch,
      còn đòi khớp đúng chữ tốt thì hôm nào họ đổi chữ là CẢ FILE bị bỏ sạch. */
teq( 'trạng thái thật trong file = nhận', false, goi( 'cong_tt_hong', array( 'Thành công' ) ) );
teq( 'bản tiếng Anh cũng nhận', false, goi( 'cong_tt_hong', array( 'Success' ) ) );
teq( '🔴 thất bại thì BỎ', true, goi( 'cong_tt_hong', array( 'Thất bại' ) ) );
teq( 'huỷ thì bỏ', true, goi( 'cong_tt_hong', array( 'Đã huỷ' ) ) );
teq( 'hoàn tiền thì bỏ', true, goi( 'cong_tt_hong', array( 'Hoàn tiền' ) ) );
teq( 'chờ xử lý thì bỏ', true, goi( 'cong_tt_hong', array( 'Đang xử lý' ) ) );
teq( 'FAILED cũng bỏ', true, goi( 'cong_tt_hong', array( 'FAILED' ) ) );

/* Chạy NGUYÊN cửa nạp file trên mấy dòng đúng hình dạng file thật. */
$db->hang = array(); $db->so_insert = 0;
$r_nap = SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', array(
	array( '12-09-2026 15:26:09', '20000', 'VPBfnWDdWQUB4', '0552rdiA-8C3tbvkQN',
		'VQR263777080WFB3 AMBT 03', 'M4QMOQLG7Y', 'VVB701528', 'Thành công' ),
	array( '12-09-2026 15:10:46', '20000', 'VPBTc41kIOX3Y', '0999aaaa-8C3xxx',
		'VQR263776CE4LJBR PaymentForOrder', 'EZFY9HCIR0', 'VVB279793', 'Thành công' ),
	array( '12-09-2026 15:00:00', '50000', 'VPBhongroi000', '0111bbbb-8C3yyy',
		'VQR263776XXXX PaymentForOrder', 'JGFM5XAXD6', 'VVB383929', 'Thất bại' ),
	array( '12-09-2026 14:00:00', '20000', 'VPBvanglai000', '0222cccc-8C3zzz',
		'VQR263776YYYY PaymentForOrder', '-', '-', 'Thành công' ),
), 'transactions_12-09-2026.xlsx' ) );
t( 'cửa nạp file chạy được', ! empty( $r_nap['ok'] ), $r_nap );
teq( '🔴 dòng "Thất bại" bị bỏ, không vào bảng', 1, (int) $r_nap['khongThanhCong'] );
teq( 'ba dòng còn lại vào bảng', 3, (int) $r_nap['themMoi'] );
teq( 'và tổng tiền chỉ cộng ba dòng ấy', 60000, (int) $r_nap['tongTienThem'] );
/* Dòng `-` không được đếm là "mã chưa có trong bản đồ" — nó đâu phải mã. Còn `M4QMOQLG7Y` thì
   ĐÚNG là thiếu thật (bản đồ trong bài này chỉ có mấy mã ở mục 3), nên phải kể tên đúng nó. */
teq( '🔴 danh sách "mã thiếu bản đồ" chỉ có mã THẬT, không có dấu "-"',
	array( 'M4QMOQLG7Y' ), $r_nap['thieuBanDo'] );
teq( 'và chỉ đếm 2 mã cửa hàng thật', 2, (int) $r_nap['soMaCH'] );
/* Dòng PaymentForOrder vừa nạp phải tra ra máy ngay. */
$dong_pfo = null;
foreach ( $db->hang as $h ) { if ( 'VPBTc41kIOX3Y' === (string) $h['ma_gd'] ) { $dong_pfo = $h; } }
teq( '🔴 dòng PaymentForOrder tra ra đúng máy', 'LM-NSG 01',
	goi( 'cong_may_dong', array( $dong_pfo['noi_dung'], $dong_pfo['ma_ch'], $dong_pfo['diem_ban'] ) ) );
/* Và trạng thái được giữ lại để còn soi — trước đây cửa nạp ghi rỗng. */
teq( 'trạng thái từ file được giữ lại', 'Thành công', (string) $dong_pfo['trang_thai'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6c. "VẪN CHƯA LỌC HẾT" — DÒNG MỚI HƠN LẦN NẠP FILE
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng chỉ vào hai dòng 15:28:22 và 15:29:19, trong khi file anh xuất lúc 15:26 dừng ở
 * 15:26:09 — file KHÔNG THỂ chứa chúng. Nhưng màn hình không nói điều đó ra, nên nhìn vào chỉ
 * thấy "vẫn còn", và tưởng bản vá hỏng. Nay mỗi lượt nạp ghi lại mốc "phủ tới đâu".
 */
$GLOBALS['OPT']['saoke_cong_nap_vietqr'] = null;
$db->hang = array();
SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', array(
	array( '12-09-2026 15:26:09', '20000', 'VPBaaa', '', 'VQR1 AMBT 03', 'M4QMOQLG7Y', '', 'Thành công' ),
	array( '12-09-2026 09:00:00', '20000', 'VPBbbb', '', 'VQR2 AMBT 04', 'EZFY9HCIR0', '', 'Thành công' ),
), 'transactions.xlsx' ) );
$moc = get_option( 'saoke_cong_nap_vietqr' );
t( '🔴 lượt nạp ghi lại mốc "phủ tới thời điểm nào"', is_array( $moc ) && ! empty( $moc['den'] ), $moc );
teq( 'và mốc ấy là giao dịch MỚI NHẤT trong file', '2026-09-12 15:26:09', (string) $moc['den'] );
teq( 'kèm tên file để còn biết đã nạp cái nào', 'transactions.xlsx', (string) $moc['tenFile'] );
/* 🔴 MỐC CHỈ TIẾN, KHÔNG LÙI. Nạp bù một file CŨ (tháng trước) mà kéo mốc lùi thì mọi dòng mới
   lại mang nhãn "mới hơn lần nạp" — sai, và sai theo hướng bắt người ta đi xuất lại file vô ích. */
SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', array(
	array( '01-08-2026 10:00:00', '20000', 'VPBcu001', '', 'VQR3 AMBT 05', 'EZFY9HCIR0', '', 'Thành công' ),
), 'thang-8-cu.xlsx' ) );
$moc2 = get_option( 'saoke_cong_nap_vietqr' );
teq( '🔴 nạp file CŨ hơn KHÔNG kéo mốc lùi', '2026-09-12 15:26:09', (string) $moc2['den'] );
teq( 'và tên file cũng giữ của lần phủ xa nhất', 'transactions.xlsx', (string) $moc2['tenFile'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 7. TÌM ĐƯỢC THÌ MỚI DÙNG ĐƯỢC
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng, ngay sau khi có bản đầu: *"Anh chưa thấy chỗ thêm file"* — đúng, khối nạp file nằm
 * trong thẻ ⚙️ đang GẬP KÍN, trong khi màn hình báo 593 dòng chưa rõ máy. Một việc cần làm mà
 * không tự chỉ đường tới chỗ làm nó thì coi như chưa làm xong.
 */
t( '🔴 khối "Cần biết" chỉ đường tới chỗ nạp file khi còn dòng chưa rõ máy',
	false !== strpos( $APP, "cgMoCongCu(\\''+nguon+'\\')" )
	&& false !== strpos( $APP, 'giao dịch chưa rõ máy' ) );
t( 'có hàm mở thẻ gập và cuộn tới đúng ô chọn file', false !== strpos( $APP, 'function cgMoCongCu(' ) );
t( 'thẻ ⚙️ có id để mở được bằng mã', false !== strpos( $APP, "id('congCu')" ) );
/* Tên khối phải KỂ RA thứ vừa thêm — người đọc lướt qua dòng ấy là biết có nên mở hay không. */
t( 'và tên thẻ ⚙️ nhắc tới "bản đồ cửa hàng"',
	false !== strpos( $APP, "· <b>bản đồ cửa hàng</b>" ) );

/* 🔴 SỐ BẢN PHẢI IN RA MÀN. Câu đầu tiên khi một tính năng "không thấy đâu" là *bản đang chạy
 *    có nó chưa* — mà trang không in số bản thì không ai đáp được ngoài cách mở wp-admin. */
t( 'máy chủ gửi số bản cho trang', false !== strpos( $SRC, "'ok' => true, 'ver' => self::VER," ) );
t( 'và trang in nó ra cạnh tên công ty', false !== strpos( $APP, "'· v' + cfg.ver" ) );
/* Hai chỗ khai số bản (header plugin + hằng VER) phải BẰNG NHAU — lệch thì trang khoe một đằng,
   WordPress hiện một nẻo, và người đi kiểm bản nào đang chạy sẽ tin nhầm. */
preg_match( '/^ \* Version:\s+([0-9.]+)/m', $SRC, $mv );
preg_match( "/const VER = '([0-9.]+)';/", $SRC, $mc );
teq( '🔴 số bản ở header và hằng VER bằng nhau',
	isset( $mv[1] ) ? $mv[1] : 'thiếu-header', isset( $mc[1] ) ? $mc[1] : 'thiếu-hằng' );

echo "\n";
if ( $TRUOT ) {
	echo 'TRƯỢT ' . count( $TRUOT ) . ":\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $DAT\n";
	exit( 1 );
}
echo "✓ SẠCH — $DAT phép: mã cửa hàng Việt QR vá được máy cho dòng 'PaymentForOrder', và không đếm tiền hai lần.\n";
