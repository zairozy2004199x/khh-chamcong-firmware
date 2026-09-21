<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GÓI KHỞI ĐỘNG PHẢI MANG THEO DANH SÁCH BỘ PHẬN.
 *
 * Anh Thắng 21/09/2026, ảnh chụp dải 👁 Xem như: *"chỉnh phần khai bộ phận cho admin để tes"* —
 * ô chọn bộ phận chỉ có hai dòng *"như tôi"* và *"để TRỐNG"*, không một tên bộ phận nào.
 *
 * =============================================================================================
 * 🔴 HAI NỬA ĐỀU ĐÚNG, MỐI NỐI THÌ KHÔNG AI CANH
 * =============================================================================================
 * `glDung()` đọc `BOOT.boPhanDs` — có phép canh, xanh. `boot()` gọi lại `glDung()` sau khi nạp
 * — có phép canh, xanh. Mà tính năng vẫn hỏng, vì `get_bootstrap()` CHƯA TỪNG gửi khoá ấy: nó
 * chỉ có trong gói Cấu hình (`CFG.boPhanDs`).
 *
 * Một khoá thiếu trông y hệt một danh sách rỗng. Và vì bảng Loại chi phí lấy tên từ `CFG` nên
 * nó vẫn bày đủ bảy ô tích bình thường — không có gì trên màn gợi ý rằng chỗ kia đang đói dữ
 * liệu. Bài này canh đúng cái mối nối ấy.
 *
 * ⚠️ Dải Xem như là CÔNG CỤ THỬ của Admin. Không chọn được bộ phận thì mọi luật "ai thấy loại
 *    chi phí nào" không thử được bằng tay — đúng phần anh Thắng đang sửa tới lui mấy hôm nay.
 *
 * Chạy: php tools/test/kiem-goi-khoi-dong-bo-phan.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/** Gói khởi động, đã bóc vỏ `ok()`. */
function goi() {
	VHCP_Cfg::clear_cache();
	$r = VHCP_Don::get_bootstrap();
	return isset( $r['data'] ) ? $r['data'] : $r;
}

/* ═══ 1. DANH MỤC ĐÃ KHAI → GÓI MANG ĐÚNG TÊN ĐÃ KHAI ════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::BP, array(
	array( 'Nhân viên cơ sở' ), array( 'Nhân viên văn phòng' ), array( 'Kỹ thuật' ), array( 'Máy tự động' ),
), false );
$b = goi();
t( '🔴 gói khởi động CÓ khoá `boPhanDs`', array_key_exists( 'boPhanDs', $b ), array_keys( $b ) );
teq( '🔴 và mang đúng tên đã khai ở danh mục',
	array( 'Nhân viên cơ sở', 'Nhân viên văn phòng', 'Kỹ thuật', 'Máy tự động' ),
	array_values( (array) $b['boPhanDs'] ) );
/* Cùng một nguồn với mọi chỗ khác — lệch là thử một bộ phận không tồn tại rồi kết luận nhầm. */
teq( '   khớp đúng `VHCP_Cfg::bo_phan_ds()`', VHCP_Cfg::bo_phan_ds(), array_values( (array) $b['boPhanDs'] ) );

/* ═══ 2. 🔴 DANH MỤC RỖNG → NGÃ VỀ MẶC ĐỊNH, KHÔNG GỬI MẢNG RỖNG ═════════════════
 * Site chưa khai mà gửi rỗng là ô chọn trắng trơn, trong khi máy chủ vẫn nhận bảy tên ấy ở
 * mọi phép gác — hai bên lệch nhau lặng lẽ. Đây chính là nhánh mà `bo_phan_ds()` sinh ra để
 * đỡ, nên gói này phải đi qua hàm ấy chứ không đọc thẳng bảng. */
VHCP_Cfg::write( VHCP_Cfg::BP, array(), false );
$b = goi();
t( '🔴 danh mục rỗng: gói vẫn có danh sách, KHÔNG rỗng', count( (array) $b['boPhanDs'] ) > 0, $b['boPhanDs'] );
teq( '   và đúng bảy tên mặc định', VHCP_Cfg::BO_PHAN_DS, array_values( (array) $b['boPhanDs'] ) );

/* ═══ 3. BỎ DÒNG TRỐNG, BỎ TRÙNG ═════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::BP, array(
	array( 'Kỹ thuật' ), array( '' ), array( 'Kỹ thuật' ), array( 'Setup' ),
), false );
$b = goi();
teq( 'dòng trống và dòng trùng không lọt xuống ô chọn',
	array( 'Kỹ thuật', 'Setup' ), array_values( (array) $b['boPhanDs'] ) );

/* ═══ 4. GÓI CẤU HÌNH VẪN CÓ — KHÔNG DỜI, CHỈ THÊM ═══════════════════════════════
 * Bảng Loại chi phí lấy tên từ `CFG.boPhanDs`. Gỡ nó đi để "gọn" là bảy ô tích ở đó rơi về
 * danh sách gõ cứng trong app.html — đúng cái hai-nơi-lệch-nhau mà bản 10/09 sinh ra để bỏ. */
VHCP_Cfg::clear_cache();
$c = VHCP_Cfg::get_config();
$c = isset( $c['data'] ) ? $c['data'] : $c;
t( '🔴 gói Cấu hình VẪN giữ `boPhanDs` (không dời sang gói khởi động)',
	isset( $c['boPhanDs'] ) && count( (array) $c['boPhanDs'] ), isset( $c['boPhanDs'] ) ? $c['boPhanDs'] : '(không có)' );

/* ═══ 5. HAI ĐẦU CỦA MỐI NỐI PHẢI GỌI ĐÚNG TÊN NHAU ══════════════════════════════ */
$app = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
$don = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
t( '🔴 máy chủ đặt khoá bằng `bo_phan_ds()`, không đọc thẳng bảng',
	false !== mb_strpos( $don, "'boPhanDs'   => VHCP_Cfg::bo_phan_ds()" ), 'không thấy' );
t( '   và giao diện đọc đúng khoá ấy', false !== mb_strpos( $app, 'BOOT.boPhanDs' ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: gói khởi động mang danh sách bộ phận, và ô Xem như của Admin có cái để chọn.\n";
