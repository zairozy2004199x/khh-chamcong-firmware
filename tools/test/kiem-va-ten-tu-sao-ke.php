<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * VÁ TÊN MÁY TỪ TỆP SAO KÊ — CHỈ ĐIỀN TÊN, KHÔNG THÊM DÒNG NÀO
 *
 * Anh Thắng 11/09/2026: *"anh lọc trực tiếp từ trang sao kê chứ, chỗ sao kê QR — tải sao kê
 * lọc, tải lên sẽ lọc cái bảng kê không tên"*.
 *
 * =============================================================================================
 * 🔴 HAI ĐƯỜNG ĐỌC CÙNG MỘT TỆP, NHƯNG LÀM HAI VIỆC KHÁC HẲN:
 *      `nhap_giao_dich()` THÊM giao dịch vào sổ — đường vá cho ngày webhook chết;
 *      `va_ten_tu_bang()` KHÔNG thêm gì, chỉ điền cái TÊN còn thiếu vào dòng đã có.
 *
 *    Gộp hai việc cho gọn chính là thứ anh Thắng đã chặn: *"không nên đẩy vào trang ghế bằng
 *    file sao kê, sau nó sẽ rối"*. Tệp sao kê trùm khoảng ngày rộng hơn chỗ đang thiếu — đổ cả
 *    tệp vào sổ chỉ để lấy vài cái tên là kéo theo hàng trăm dòng không ai kiểm, mà `ref` là
 *    UNIQUE nên xoá đi rồi thì đúng giao dịch ấy webhook bắn lại cũng không vào được nữa.
 *
 *    Bài kiểm canh bằng HAI tầng: chạy thật (đếm lại số dòng trong sổ trước/sau) và quét mã
 *    (hàm ấy không được có lệnh `insert`). Tầng hai bắt cả lần sửa sau này.
 *
 * 🔴 VÀ KHÔNG ĐƯỢC ĐỤNG DÒNG ĐÃ RÕ. Tên trong sổ có thể do người khai gán tay ở bảng "chưa rõ
 *    ghế" — người khai luôn đúng hơn phép đoán từ một tệp tải về.
 *
 * Chạy: php tools/test/kiem-va-ten-tu-sao-ke.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
define( 'VHG_TEST', 1 );
define( 'VHG_VERSION', 'test' );
define( 'VHG_DIR', $goc . '/wordpress/vhcp-ghe/' );
foreach ( array( 'db', 'doc', 'may', 'thu', 'qr', 'ma', 'vi', 'quy', 'chan', 'qrve', 'tep', 'nhap' ) as $f ) {
	require_once VHG_DIR . 'includes/class-vhg-' . $f . '.php';
}

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

global $wpdb;
foreach ( VHG_DB::bang() as $ten => $than ) {
	$bang = $wpdb->prefix . 'vhg_' . $ten;
	$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . $bang );
	$cot = array();
	foreach ( array_filter( array_map( 'trim', explode( "\n", $than ) ) ) as $d ) {
		$d = rtrim( $d, ',' );
		if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\b/', $d ) ) { continue; }
		$cot[] = preg_replace( '/BIGINT\(20\) NOT NULL AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $d );
	}
	$wpdb->exec_raw( 'CREATE TABLE ' . $bang . " (\n" . implode( ",\n", $cot ) . "\n)" );
}
function dem_thu() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHG_DB::t( 'thu' ) );
}
/**
 * Đếm dòng CHƯA CÓ TÊN MÁY.
 *
 * ⚠️ KHÁC HẲN `VHG_Thu::ds_chua_ro()`, và hai cái tên nghe giống nhau đến mức dễ lẫn:
 *   · `ds_chua_ro()` = chưa gán MÃ GHẾ (`ma_may`) — tức chưa cho ghế nào chạy được. Bảng ấy ở
 *     màn Đối soát, để người ta chọn ghế rồi bấm "Gán & cho chạy".
 *   · hàm dưới đây = chưa có TÊN MÁY trên sao kê (`ten_khai`) — đúng cái bảng "không tên" anh
 *     Thắng chỉ vào, và là thứ phép vá này chữa.
 * Một dòng có thể đã có tên mà vẫn chưa gán ghế; lẫn hai cái là bài kiểm canh nhầm chỗ.
 */
function dem_chua_ten() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHG_DB::t( 'thu' ) . " WHERE ten_khai=''" );
}
function dong( $ref ) {
	global $wpdb;
	$r = VHG_DB::rows( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'thu' ) . ' WHERE ref=%s', $ref ) );
	return $r ? $r[0] : null;
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * DỰNG SỔ: bốn dòng đã vào bằng webhook, ba trong đó CHƯA RÕ MÁY.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHG_Thu::ghi( array( 'ref' => 'VPB001', 'so_tien' => 20000, 'noi_dung' => 'PaymentForOrder',
	'nguon' => VHG_Thu::VIETQR, 'luc' => '2026-09-11 17:55:00' ) );
VHG_Thu::ghi( array( 'ref' => 'VPB002', 'so_tien' => 50000, 'noi_dung' => 'PaymentForOrder',
	'nguon' => VHG_Thu::VIETQR, 'luc' => '2026-09-11 17:50:00' ) );
VHG_Thu::ghi( array( 'ref' => 'VPB003', 'so_tien' => 20000, 'noi_dung' => 'PaymentForOrder',
	'nguon' => VHG_Thu::VIETQR, 'luc' => '2026-09-11 17:40:00' ) );
/* Dòng này NGƯỜI KHAI đã gán tay — phải được để yên. */
VHG_Thu::ghi( array( 'ref' => 'VPB004', 'so_tien' => 20000, 'noi_dung' => 'PaymentForOrder',
	'nguon' => VHG_Thu::VIETQR, 'luc' => '2026-09-11 17:30:00', 'ten_khai' => 'TÊN NGƯỜI KHAI GÁN' ) );

teq( 'sổ đang có 4 dòng', 4, dem_thu() );
teq( 'ba dòng chưa có TÊN MÁY', 3, dem_chua_ten() );
teq( '   còn "chưa gán ghế" thì cả bốn (khái niệm khác)', 4, count( VHG_Thu::ds_chua_ro( 50 ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * TỆP SAO KÊ: có dòng nói được tên máy, có dòng chỉ có mã cửa hàng, và có dòng KHÔNG có
 * trong sổ (sao kê trùm khoảng ngày rộng hơn — chuyện bình thường).
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$TEP = array(
	array( 'Mã tham chiếu', 'Mã điểm bán', 'Mã cửa hàng', 'Số tiền đến (VND)', 'Nội dung TT' ),
	/* Dòng DẠY: nội dung mang tên máy, kèm mã cửa hàng -> bản đồ học được cặp ấy. */
	array( 'VPB900', 'VVB20', 'KH0020', '20000', 'AMBD 12' ),
	/* Ba dòng dưới là những dòng ĐANG chưa rõ trong sổ. */
	array( 'VPB001', 'VVB20', 'KH0020', '20000', 'PaymentForOrder' ),   // tra được qua bản đồ
	array( 'VPB002', '',      '',       '50000', 'ESTELA 06' ),          // tên nằm ngay nội dung
	array( 'VPB003', 'VVB99', 'KH9999', '20000', 'PaymentForOrder' ),   // bản đồ cũng chịu
	/* Dòng người khai đã gán -> phải để yên. */
	array( 'VPB004', 'VVB20', 'KH0020', '20000', 'PaymentForOrder' ),
	/* Dòng KHÔNG có trong sổ. */
	array( 'VPB777', 'VVB20', 'KH0020', '20000', 'PaymentForOrder' ),
);
$truoc = dem_thu();
$kq = VHG_Nhap::va_ten_tu_bang( $TEP );
t( 'vá được', ! empty( $kq['ok'] ), $kq );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 PHÉP QUAN TRỌNG NHẤT: SỐ DÒNG TRONG SỔ KHÔNG ĐỔI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '🔴 KHÔNG thêm dòng nào vào sổ tiền', $truoc, dem_thu() );
teq( '   và sổ vẫn đúng 4 dòng',           4,      dem_thu() );
t( '🔴 dòng VPB777 (chỉ có trong tệp) KHÔNG lọt vào sổ', null === dong( 'VPB777' ), dong( 'VPB777' ) );
teq( '   đếm đúng số dòng không có trong sổ', 2, $kq['khongThay'] );   // VPB900 + VPB777

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * VÁ ĐÚNG NHỮNG DÒNG CẦN VÁ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'vá được 2 dòng', 2, $kq['va'] );
teq( '🔴 VPB001: tra qua bản đồ ra tên máy', 'AMBD 12', (string) dong( 'VPB001' )['ten_khai'] );
teq( '   và ghi kèm mã cửa hàng để lần sau tra lại được', 'KH0020', (string) dong( 'VPB001' )['ma_ch'] );
teq( '🔴 VPB002: tên nằm ngay trong nội dung',  'ESTELA 06', (string) dong( 'VPB002' )['ten_khai'] );
teq( '🔴 VPB003: tệp cũng không nói được máy nào → vẫn trống', '', (string) dong( 'VPB003' )['ten_khai'] );
teq( '   và được đếm vào mục "chịu"', 1, $kq['chiu'] );

/* 🔴 NGƯỜI KHAI THẮNG. */
teq( '🔴 VPB004 do người khai gán → KHÔNG bị ghi đè',
	'TÊN NGƯỜI KHAI GÁN', (string) dong( 'VPB004' )['ten_khai'] );
teq( '   và được đếm vào mục "vốn đã rõ"', 1, $kq['daRo'] );

teq( '🔴 bảng "không tên" rút từ 3 xuống còn 1 dòng', 1, dem_chua_ten() );
t( '   học thêm được máy vào bản đồ', $kq['hoc'] >= 1, $kq['hoc'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * SỐ TIỀN VÀ NGÀY CỦA DÒNG CŨ KHÔNG ĐƯỢC ĐỤNG TỚI
 *
 * ⚠️ Vá tên mà lỡ ghi đè số tiền theo tệp là sổ lệch với ngân hàng ở đúng những dòng vừa
 *    "chữa" — hỏng lặng lẽ, và lần đối soát sau không ai nghĩ tới đường này.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '🔴 số tiền giữ nguyên',  20000, (int) dong( 'VPB001' )['so_tien'] );
teq( '🔴 thời điểm giữ nguyên', '2026-09-11 17:55:00', (string) dong( 'VPB001' )['luc'] );
teq( '   nội dung giữ nguyên', 'PaymentForOrder', (string) dong( 'VPB001' )['noi_dung'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * CHẠY LẠI ĐÚNG TỆP ẤY — không hỏng gì thêm
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$kq2 = VHG_Nhap::va_ten_tu_bang( $TEP );
teq( '🔴 lần hai: không vá thêm dòng nào', 0, $kq2['va'] );
teq( '   sổ vẫn 4 dòng',                   4, dem_thu() );
teq( '   ba dòng nay đã rõ',               3, $kq2['daRo'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * CA CHỐI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$r = VHG_Nhap::va_ten_tu_bang( array( array( 'Mã cửa hàng', 'Nội dung' ), array( 'KH1', 'AMBD 12' ) ) );
t( '🔴 thiếu cột Mã tham chiếu → chối và nói vì sao',
	empty( $r['ok'] ) && false !== mb_strpos( $r['error'], 'Mã tham chiếu' ), $r );
$r = VHG_Nhap::va_ten_tu_bang( array( array( 'Mã tham chiếu', 'Số tiền' ), array( 'VPB001', '2' ) ) );
t( 'tệp không có cột nào nói được máy → chối', empty( $r['ok'] ), $r );
$r = VHG_Nhap::va_ten_tu_bang( array( array( 'Mã tham chiếu', 'Nội dung' ) ) );
t( 'chỉ có tiêu đề → chối', empty( $r['ok'] ), $r );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 QUÉT MÃ — hàm vá KHÔNG được có lệnh thêm dòng
 *
 * Tầng chạy thật ở trên chỉ canh những đường bài kiểm đi qua. Phép quét này bắt cả một nhánh
 * mới viết sau này lỡ gọi `insert` — đúng kiểu "rối" anh Thắng chặn từ đầu.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$nguon = file_get_contents( VHG_DIR . 'includes/class-vhg-nhap.php' );
$i = strpos( $nguon, 'public static function va_ten_tu_bang' );
$j = strpos( $nguon, 'public static function ap_lai_ban_do' );
t( 'tìm được thân hàm va_ten_tu_bang()', $i > 0 && $j > $i, array( $i, $j ) );
$than = substr( $nguon, $i, $j - $i );
$than = preg_replace( '#/\*.*?\*/#s', '', $than );
$than = preg_replace( '#//[^\n]*#', '', $than );
t( '🔴 hàm vá KHÔNG gọi $wpdb->insert', ! preg_match( '#\$wpdb\s*->\s*insert\s*\(#i', $than ), 'có insert!' );
t( '🔴 hàm vá KHÔNG gọi VHG_Thu::ghi',  false === strpos( $than, 'VHG_Thu::ghi' ), 'có gọi!' );
t( '   và KHÔNG có câu SQL INSERT',     ! preg_match( '#INSERT\s+INTO#i', $than ), 'có INSERT!' );
t( '   cũng KHÔNG xoá dòng nào',        ! preg_match( '#\$wpdb\s*->\s*delete\s*\(|DELETE\s+FROM#i', $than ), 'có xoá!' );
t( '   nhưng CÓ cập nhật (đó là việc của nó)', preg_match( '#\$wpdb\s*->\s*update\s*\(#i', $than ) > 0 );

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
echo "\n";
if ( $TRUOT ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — vá tên cho dòng chưa rõ, không thêm dòng nào vào sổ\n";
