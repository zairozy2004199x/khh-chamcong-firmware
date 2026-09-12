<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ — GIAO DỊCH MỚI PHẢI TỰ BIẾT MÁY NÀO, KHÔNG PHẢI ĐỢI NẠP FILE
 *
 * 0.18.1 nhận ra máy được cho 180 dòng cũ, nhưng CHỈ vì anh Thắng tải file kết xuất về nạp. Bản
 * này đóng nốt đường cho tiền MỚI. Ba lỗ hổng, cả ba đều câm:
 *
 * 🔴 1. `cong_doc_obj()` KHÔNG hề đọc mã cửa hàng. Không có lấy một khoá nào. Cổng có gửi mã về
 *       thì cũng bị vứt ngay tại cửa, rồi màn hình báo "chưa rõ máy" và người ta đi tải đúng cái
 *       dữ liệu vừa ném đi.
 *
 * 🔴 2. `cong_nhan_webhook()` — hàm DUY NHẤT đổ webhook vào bảng cổng — được định nghĩa mà
 *       KHÔNG MỘT NƠI NÀO GỌI. Cả `r_webhook` lẫn `r_vqr_callback` chỉ ghi vào sao kê ngân hàng.
 *       Nghĩa là sau lần nạp file gần nhất, giao dịch mới không phải "chưa rõ máy" — mà KHÔNG CÓ
 *       trên màn hình cổng. Mã chết không bao giờ đỏ, nên không bộ thử nào thấy.
 *
 * 🔴 3. Bật (2) lên thì lòi ra nguy cơ ĐẾM ĐÔI: webhook và file kết xuất dựng khoá từ hai giá trị
 *       khác nhau. Đếm thiếu thì ai cũng thấy, đếm gấp đôi thì không ai thấy.
 *
 * Chạy: php tools/test/kiem-saoke-webhook-may.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/lib/be-saoke.php';

require_once $GOC . '/vhcp-saoke/vhcp-saoke.php';
t( 'nạp được lớp SAOKE_App', class_exists( 'SAOKE_App' ) );

/* Bản đồ cửa hàng như anh Thắng nạp từ `store_export_…xlsx`: mã CH -> tên máy, kèm mã điểm bán. */
$GLOBALS['OPT']['saoke_vqr_ch'] = array(
	'AMTP24'         => array( 'ten' => 'AMTP 24', 'maDiem' => 'KH705MTDMN0002', 'tenDiem' => 'AEON MALL TÂN PHÚ' ),
	'GLXQT02'        => array( 'ten' => 'GLX QT 02', 'maDiem' => 'KH705MTDMN0011', 'tenDiem' => 'Galaxy Quang Trung' ),
	'KH705MTDMN0044' => array( 'ten' => 'AMBT 07', 'maDiem' => '', 'tenDiem' => 'Aeon Bình Tân' ),
);

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. ĐỌC MÃ CỬA HÀNG TỪ PAYLOAD — ĐƯỜNG THẲNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
echo "── 1. Đọc mã từ đúng ô cổng gửi ────────────────────────\n";

function doc1( $json ) { $r = goi( 'cong_doc_payload', array( $json ) ); return isset( $r[0] ) ? $r[0] : null; }

/* Payload kiểu "cổng đặt tên trường tử tế". Trước 0.20.0 maCH luôn rỗng ở đây. */
$tx = doc1( json_encode( array(
	'transactionId' => 'VQR2637642208V7L', 'amount' => 150000,
	'transactionDate' => '12/09/2026 15:26:00',
	'content' => 'VQR2637642208V7L PaymentForOrder', 'storeCode' => 'AMTP 24' ) ) );
t( 'đọc được giao dịch', null !== $tx );
teq( '🔴 storeCode vào ô maCH (trước 0.20.0 luôn rỗng)', 'AMTP 24', $tx['maCH'] );
teq( 'và suy ra đúng tên máy', 'AMTP 24', goi( 'cong_may_dong', array( $tx['noiDung'], $tx['maCH'], $tx['diemBan'] ) ) );

/* Mỗi cổng gọi một kiểu tên — danh sách khoá phải rộng. */
foreach ( array( 'store_code', 'merchantCode', 'terminalCode', 'shopCode', 'posCode', 'maCuaHang' ) as $khoa ) {
	$tx = doc1( json_encode( array( 'transactionId' => 'X1', 'amount' => 1000,
		'transactionDate' => '12/09/2026 10:00:00', 'content' => 'PaymentForOrder', $khoa => 'GLX QT 02' ) ) );
	teq( 'khoá "' . $khoa . '" cũng được nhận', 'GLX QT 02', $tx['maCH'] );
}

/* ⚠️ KHÔNG ĐƯỢC CƯỚP `storeId`/`storeName` của `diemBan` — đường lùi cũ đang chạy đúng. */
$tx = doc1( json_encode( array( 'transactionId' => 'X2', 'amount' => 1000,
	'transactionDate' => '12/09/2026 10:00:00', 'storeName' => 'Aeon Bình Tân' ) ) );
teq( '🔴 storeName vẫn thuộc về diemBan', 'Aeon Bình Tân', $tx['diemBan'] );
teq( 'và KHÔNG bị hiểu thành mã cửa hàng', '', $tx['maCH'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. CỬA CUỐI — DÒ MÃ MÀ KHÔNG CẦN BIẾT TÊN TRƯỜNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Đây là chỗ khiến bản này KHÔNG còn phải đợi ai gửi cho một gói payload thật mới vá được: dù
 * cổng gọi trường ấy là gì, chỉ cần GIÁ TRỊ trùng một mã đã có trong bản đồ là nhận ra.
 */
echo "── 2. Dò mã dù cổng đặt tên trường lạ hoắc ─────────────\n";

$tx = doc1( json_encode( array( 'transactionId' => 'X3', 'amount' => 50000,
	'transactionDate' => '12/09/2026 10:00:00', 'content' => 'PaymentForOrder',
	'mot_cai_ten_chua_ai_nghi_ra' => 'AMTP 24' ) ) );
teq( '🔴 tên trường lạ vẫn dò ra mã', 'AMTP24', $tx['maCH'] );

/* Lồng sâu trong object con — payload thật hay có `data`/`object`. */
$tx = doc1( json_encode( array( 'transactionId' => 'X4', 'amount' => 50000,
	'transactionDate' => '12/09/2026 10:00:00',
	'object' => array( 'merchant' => array( 'code' => 'GLX QT 02' ) ) ) ) );
teq( 'dò được cả khi lồng sâu', 'GLXQT02', $tx['maCH'] );

/* Cổng gửi MÃ ĐIỂM BÁN thay vì mã cửa hàng — bản đồ có cả hai cột nên vẫn ra. */
$tx = doc1( json_encode( array( 'transactionId' => 'X5', 'amount' => 50000,
	'transactionDate' => '12/09/2026 10:00:00', 'diemBanCode' => 'KH705MTDMN0002' ) ) );
teq( 'mã ĐIỂM BÁN cũng dò ra', 'KH705MTDMN0002', $tx['maCH'] );
teq( 'và trỏ về đúng máy', 'AMTP 24', goi( 'cong_may_dong', array( '', $tx['maCH'], '' ) ) );

/* 🔴 HAI CHỐT CHỐNG NHẬN BỪA — cùng loại lỗi câm mà `may_hop_le()` từng dính qua cửa đoán cột. */
echo "── 2b. Chốt chống nhận bừa ─────────────────────────────\n";
$GLOBALS['OPT']['saoke_vqr_ch']['150000'] = array( 'ten' => 'MÁY MA', 'maDiem' => '', 'tenDiem' => '' );
$GLOBALS['OPT']['saoke_vqr_ch']['AM']     = array( 'ten' => 'MÁY NGẮN', 'maDiem' => '', 'tenDiem' => '' );
$tx = doc1( json_encode( array( 'transactionId' => 'X6', 'amount' => 150000,
	'transactionDate' => '12/09/2026 10:00:00', 'content' => 'PaymentForOrder' ) ) );
teq( '🔴 SỐ TIỀN toàn số KHÔNG được nhận làm mã (phải có chữ cái)', '', $tx['maCH'] );
$tx = doc1( json_encode( array( 'transactionId' => 'X7', 'amount' => 7000,
	'transactionDate' => '12/09/2026 10:00:00', 'x' => 'AM' ) ) );
teq( '🔴 mã ngắn dưới 4 ký tự KHÔNG được nhận', '', $tx['maCH'] );
unset( $GLOBALS['OPT']['saoke_vqr_ch']['150000'], $GLOBALS['OPT']['saoke_vqr_ch']['AM'] );

/* Giá trị không có trong bản đồ thì nói KHÔNG BIẾT, đừng bịa. */
$tx = doc1( json_encode( array( 'transactionId' => 'X8', 'amount' => 7000,
	'transactionDate' => '12/09/2026 10:00:00', 'storeCode' => 'CHUA-CO-TRONG-BAN-DO' ) ) );
teq( 'mã lạ vẫn được giữ nguyên ở ô maCH', 'CHUA-CO-TRONG-BAN-DO', $tx['maCH'] );
teq( 'nhưng không suy ra máy nào cả', '', goi( 'cong_may_dong', array( '', $tx['maCH'], '' ) ) );

/* Bản đồ rỗng thì cửa cuối phải im, không được quét bừa. */
$luu = $GLOBALS['OPT']['saoke_vqr_ch']; $GLOBALS['OPT']['saoke_vqr_ch'] = array();
$tx = doc1( json_encode( array( 'transactionId' => 'X9', 'amount' => 7000,
	'transactionDate' => '12/09/2026 10:00:00', 'x' => 'AMTP 24' ) ) );
teq( 'bản đồ rỗng -> không đoán gì', '', $tx['maCH'] );
$GLOBALS['OPT']['saoke_vqr_ch'] = $luu;

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2c. DÒNG THẬT CỦA ANH THẮNG — Ô MÃ CỬA HÀNG TOÀN CHỮ BỊ VỨT IM LẶNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 12/09/2026 gửi ba ảnh cạnh nhau: màn hình có dòng "chưa rõ máy", bản kết xuất Việt QR
 * của ĐÚNG giao dịch ấy, và tab Sheet của app cũ chứa payload thô.
 *
 * 🔴 Bản kết xuất có **Mã cửa hàng = `RJFSHCSXE9`** — TOÀN CHỮ CÁI, không số, không khoảng trắng.
 *    `cong_doc_hang()` phân loại ô theo hình dạng: cần có CẢ chữ lẫn số mới cho vào mã giao dịch,
 *    cần có khoảng trắng mới cho vào nội dung/điểm bán. Ô toàn chữ rơi khỏi MỌI nhánh -> bị vứt,
 *    không một dòng mã nào nói "bỏ ô này", nên cũng không ai thấy. Dữ liệu cửa hàng CÓ trong gói
 *    webhook mà không bao giờ tới được bảng: *"Có vấn đề gì đó làm mất dữ liệu cửa hàng"*.
 */
echo "── 2c. Dòng Tingo thật: ô mã toàn chữ ──────────────────\n";

$GLOBALS['OPT']['saoke_vqr_ch']['RJFSHCSXE9'] = array( 'ten' => 'SCVV 09', 'maDiem' => 'VVB851980', 'tenDiem' => 'Sense City Vũng Vằn' );

/* Dựng lại đúng thứ tự ô như tab CongThanhToan cho thấy, kèm hai ô mã của bản kết xuất. */
$hang = array( 'VPBBxq5Qo0CZn', '0832sSyu-8C3vVYGCE', '12/09/2026 15:55:07', '20000', 'Đến',
	'Thành công', '8640107702 - BIDV', 'VQR26377774BYL8B PaymentForOrder', 'VVB851980', 'RJFSHCSXE9' );
$tx = doc1( json_encode( array( 'values' => array( $hang ) ) ) );
t( 'đọc được dòng Tingo', null !== $tx );
teq( 'tiền đúng', 20000.0, (float) $tx['soTien'] );
teq( '🔴 ô mã cửa hàng TOÀN CHỮ không còn bị vứt', 'RJFSHCSXE9', $tx['maCH'] );
teq( '🔴 và dòng "PaymentForOrder" nay ra đúng máy — hết "chưa rõ máy"',
	'SCVV 09', goi( 'cong_may_dong', array( $tx['noiDung'], $tx['maCH'], $tx['diemBan'] ) ) );

/* Cùng gói nhưng chỉ có mã ĐIỂM BÁN (cổng gửi thiếu cột mã cửa hàng) -> vẫn phải ra máy ấy. */
$hang2 = array( 'VPBxxx', '0832sSyu-2', '12/09/2026 15:56:00', '50000', 'Đến', 'Thành công',
	'8640107702 - BIDV', 'VQR26377774ZZZZZ PaymentForOrder', 'VVB851980' );
$tx2 = doc1( json_encode( array( 'values' => array( $hang2 ) ) ) );
teq( 'chỉ có mã điểm bán vẫn dò ra', 'VVB851980', $tx2['maCH'] );
teq( 'và vẫn trỏ đúng máy', 'SCVV 09', goi( 'cong_may_dong', array( $tx2['noiDung'], $tx2['maCH'], $tx2['diemBan'] ) ) );

/* ⚠️ MỘT GÓI, HAI CỬA HÀNG — mỗi dòng phải giữ mã CỦA NÓ. Dò trên cả gói là tiền máy này chui
   sang máy kia, mà bảng nhìn vẫn "đầy đủ" nên không ai nghi. */
$GLOBALS['OPT']['saoke_vqr_ch']['QWERTYUIOP'] = array( 'ten' => 'AMBT 19', 'maDiem' => '', 'tenDiem' => '' );
$hangB = array( 'VPByyy', '0832sSyu-3', '12/09/2026 15:57:00', '70000', 'Đến', 'Thành công',
	'8640107702 - BIDV', 'VQR26377774YYYYY PaymentForOrder', 'QWERTYUIOP' );
$hai = goi( 'cong_doc_payload', array( json_encode( array( 'values' => array( $hang, $hangB ) ) ) ) );
teq( 'đọc được hai dòng', 2, count( $hai ) );
teq( '🔴 dòng 1 giữ mã của dòng 1', 'RJFSHCSXE9', $hai[0]['maCH'] );
teq( '🔴 dòng 2 giữ mã của dòng 2 (không bị lây của dòng 1)', 'QWERTYUIOP', $hai[1]['maCH'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. WEBHOOK SỐNG PHẢI ĐỔ VÀO BẢNG CỔNG (mã chết được đánh thức)
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
echo "── 3. Webhook sống ghi vào bảng cổng ───────────────────\n";

/* ⚠️ Đếm CHỖ GỌI. Mã chết không bao giờ đỏ — chỉ có phép đếm này mới thấy nó sống lại hay chưa. */
t( '🔴 cong_nhan_webhook() đã có nơi gọi (trước 0.20.0 là mã chết)',
	substr_count( $SRC, 'self::cong_nhan_webhook(' ) >= 2,
	substr_count( $SRC, 'self::cong_nhan_webhook(' ) . ' chỗ gọi' );
t( 'VietQR chính thức gọi nó', false !== strpos( $SRC, "self::cong_nhan_webhook( 'vietqr', \$req )" ) );
t( 'webhook chung cũng gọi khi src là một cổng',
	false !== strpos( $SRC, 'if ( in_array( $nguon, self::cong_ds(), true ) ) {' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. CÙNG MỘT GIAO DỊCH, HAI ĐƯỜNG VỀ -> KHÔNG ĐƯỢC ĐẾM ĐÔI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
echo "── 4. Webhook rồi nạp file: tiền không nhân đôi ────────\n";

$wpdb = $GLOBALS['wpdb'];
$wpdb->hang = array(); $wpdb->so_insert = 0; $wpdb->so_update = 0;

/* (a) Webhook về trước: mã giao dịch của ngân hàng, CHƯA có mã cửa hàng. */
$w = array( 'nguon' => 'vietqr', 'khoa' => 'vietqr|FT26255ABCD', 'maGD' => 'FT26255ABCD',
	'ref' => 'VQR2637642208V7L', 'thoiDiem' => '12/09/2026 15:26:00', 'soTien' => 150000, 'huong' => 'Đến',
	'trangThai' => 'Thành công', 'soTK' => '', 'noiDung' => 'VQR2637642208V7L PaymentForOrder',
	'diemBan' => '', 'maCH' => '', 'docDuoc' => true, 'raw' => '{}' );
teq( 'webhook chèn dòng mới', true, goi( 'luu_cong', array( $w ) ) );
teq( 'bảng có 1 dòng', 1, count( $wpdb->hang ) );

/* (b) File kết xuất về sau: CÙNG giao dịch, nhưng "Mã đơn hàng" khác -> khoá khác hẳn.
      Đây chính là chỗ 0.20.0 phải bắt được; không bắt là 150.000đ được đếm hai lần. */
$f = $w;
$f['maGD'] = '2637642208'; $f['khoa'] = 'vietqr|2637642208'; $f['maCH'] = 'AMTP 24';
$kq = null;
teq( '🔴 KHÔNG chèn dòng thứ hai dù khoá khác', false, goi( 'luu_cong', array( $f, &$kq ) ) );
teq( 'bảng vẫn đúng 1 dòng — tiền không nhân đôi', 1, count( $wpdb->hang ) );
teq( 'và nhận ra là lượt VÁ, không phải trùng suông', 'va', $kq );
teq( '🔴 mã cửa hàng của file được vá vào dòng webhook', 'AMTP 24', $wpdb->hang[0]['ma_ch'] );
teq( 'dòng ấy nay suy ra đúng máy',
	'AMTP 24', goi( 'cong_may_dong', array( $wpdb->hang[0]['noi_dung'], $wpdb->hang[0]['ma_ch'], $wpdb->hang[0]['diem_ban'] ) ) );

/* Nạp lại lần nữa: không đổi gì thêm. */
$kq = null;
teq( 'nạp lại lần ba vẫn không chèn', false, goi( 'luu_cong', array( $f, &$kq ) ) );
teq( 'và không vá gì nữa', 'trung', $kq );
teq( 'vẫn đúng 1 dòng', 1, count( $wpdb->hang ) );

/* ⚠️ NHƯNG KHÔNG ĐƯỢC GỘP BỪA. Ba điều kiện phải cùng đúng; thiếu một là hai giao dịch thật
   khác nhau, gộp lại là MẤT hẳn một khoản — còn tệ hơn đếm đôi. */
echo "── 4b. Không được gộp nhầm hai giao dịch thật ──────────\n";
$k = $w; $k['khoa'] = 'vietqr|KHAC1'; $k['maGD'] = 'KHAC1'; $k['ref'] = 'VQR-KHAC'; $k['soTien'] = 150000;
teq( 'mã khác hẳn -> là giao dịch mới', true, goi( 'luu_cong', array( $k ) ) );
teq( 'bảng thành 2 dòng', 2, count( $wpdb->hang ) );

$k2 = $w; $k2['khoa'] = 'vietqr|KHAC2'; $k2['maGD'] = 'KHAC2'; $k2['soTien'] = 90000;
teq( '🔴 cùng ref nhưng KHÁC SỐ TIỀN -> vẫn là giao dịch mới', true, goi( 'luu_cong', array( $k2 ) ) );
teq( 'bảng thành 3 dòng', 3, count( $wpdb->hang ) );

$k3 = $w; $k3['khoa'] = 'momo|FT26255ABCD'; $k3['nguon'] = 'momo';
teq( '🔴 cùng mã nhưng KHÁC NGUỒN -> vẫn là giao dịch mới', true, goi( 'luu_cong', array( $k3 ) ) );
teq( 'bảng thành 4 dòng', 4, count( $wpdb->hang ) );

/* Không có mã nào thì đường dò trùng chéo phải chịu thua, không được quét mò theo số tiền. */
$k4 = $w; $k4['khoa'] = 'vietqr|RAW-xyz'; $k4['maGD'] = ''; $k4['ref'] = '';
teq( 'không có mã -> không dò trùng chéo, chèn bình thường', true, goi( 'luu_cong', array( $k4 ) ) );
teq( 'bảng thành 5 dòng', 5, count( $wpdb->hang ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. CHỈ MỘT CHỖ VÁ MÃ — đừng để mọc bản sao thứ hai (bài học 0.18.0)
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
echo "── 5. Một luật, một chỗ ────────────────────────────────\n";
/* ĐÚNG HAI chỗ gọi, cả hai nằm trong `cong_doc_payload()` — một cho dạng `values[]` (dò theo
   TỪNG DÒNG) và một cho dạng JSON tên trường. Mọc ra chỗ thứ ba ở hàm khác là con số này nhảy. */
teq( 'vqr_ma_tu_payload() gọi đúng 2 chỗ, đều trong cong_doc_payload', 2,
	substr_count( $SRC, 'self::vqr_ma_tu_payload(' ) );
$than = substr( $SRC, strpos( $SRC, 'private static function cong_doc_payload(' ),
	strpos( $SRC, 'private static function vqr_ma_tat_ca(' ) - strpos( $SRC, 'private static function cong_doc_payload(' ) );
teq( '🔴 và cả hai chỗ ấy nằm TRONG cong_doc_payload, không rải ra nơi khác', 2,
	substr_count( $than, 'self::vqr_ma_tu_payload(' ) );
teq( 'cong_dong_trung() chỉ được gọi từ MỘT chỗ (trong luu_cong)', 1,
	substr_count( $SRC, 'self::cong_dong_trung(' ) );
teq( 'cong_may_dong() vẫn đúng 4 chỗ gọi — không thêm bản sao thứ năm', 4,
	substr_count( $SRC, 'self::cong_may_dong(' ) );
t( 'VER_TBL đã lên 5 cho KEY ref', false !== strpos( $SRC, "const VER_TBL = '5';" ) );
t( 'bảng cổng có KEY ref (đường dò trùng chéo đi qua nó)', false !== strpos( $SRC, 'KEY ref (ref)' ) );

/* Phiên bản: header và hằng VER phải bằng nhau. */
preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/m', $SRC, $mh );
preg_match( "/const VER\s*=\s*'([0-9.]+)'/", $SRC, $mc );
teq( 'Version: header == const VER', $mh[1], $mc[1] );

ket_luan( 'giao dịch mới tự biết máy, và webhook + file không đếm tiền hai lần.' );
