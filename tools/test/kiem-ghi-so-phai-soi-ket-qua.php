<?php
/**
 * GHI XUỐNG SỔ THÌ PHẢI SOI KẾT QUẢ — KHÔNG BÁO "XONG" KHI CHƯA CHẮC ĐÃ GHI ĐƯỢC
 * =============================================================================================
 *
 * 🔴 LỚP LỖI NÀY ĐÃ CẮN HAI LẦN, CÁCH NHAU MƯỜI BA NGÀY, Ở HAI HÀM KHÁC NHAU:
 *
 *   · 09/09/2026 — `create_don()`: màn báo "Đã tạo đơn" rồi ngay sau đó "Không tìm thấy đơn".
 *   · 22/09/2026 — `add_line()`: anh Thắng *"có thấy báo thêm hạng mục, nhưng không thấy gì"*.
 *     Bản 1.266.0 thêm cột `chiphi.giai_doan` mà quên nâng `SCHEMA_VERSION`, nên cột không được
 *     tạo trong CSDL thật và MySQL chối mọi câu INSERT vì "Unknown column". `add_line()` không
 *     soi kết quả nên vẫn trả về thành công — người nhập gõ cả buổi, sổ vẫn trống.
 *
 * Lần đầu đã vá ĐÚNG CHỖ ẤY và ghi chú cẩn thận, nhưng KHÔNG ĐI SOI mấy chỗ còn lại. Nên nó
 * quay lại bằng một cửa khác. Bài này đóng cả dãy cửa cùng lúc, và canh mãi về sau.
 *
 * ⚠️ VÌ SAO KHÔNG ĐỂ MÁY CHỦ IM LẶNG: một lượt ghi hỏng mà màn báo xanh là kiểu hỏng đắt nhất —
 *    người dùng tin là xong, đi làm việc khác, và chỉ phát hiện ra khi đối chiếu sổ. Lúc ấy
 *    không còn biết đã mất những gì.
 *
 * Chạy: php tools/test/kiem-ghi-so-phai-soi-ket-qua.php
 */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) );
}
$goc = dirname( dirname( __DIR__ ) );

/* Soi MỌI plugin có bảng riêng, không kê tay từng tệp — cửa mới mở ở plugin nào cũng bị canh. */
$tep = glob( $goc . '/wordpress/*/includes/class-*.php' );
t( 'tìm được mã máy chủ để soi', count( $tep ) >= 10, count( $tep ) );

/* Bảng nào giữ CHỨNG TỪ — ghi hỏng ở đây là mất tiền trong sổ, không phải mất một dòng nhật ký.
   Mấy bảng phụ (nhật ký, thùng rác, đệm) cố ý KHÔNG nằm trong danh sách: ghi hỏng ở đó thì
   tiếc, nhưng không ai mất tiền, và bắt chúng cũng phải soi là đẻ ra nhiễu. */
$BANG_TIEN = array( 'don', 'chiphi', 'tamung', 'so_chi', 'da_line', 'lenh_tu' );

$tong = 0; $ho = array();
foreach ( $tep as $f ) {
	$src = file_get_contents( $f );
	$ten_tep = basename( dirname( dirname( $f ) ) ) . '/' . basename( $f );
	$dong = explode( "\n", $src );
	foreach ( $dong as $i => $d ) {
		if ( ! preg_match( "/\\\$wpdb->insert\(\s*(?:VHCP\w*_DB|VHCC_DB|self)::t\(\s*'([a-z_]+)'\s*\)/", $d, $m ) ) { continue; }
		if ( ! in_array( $m[1], $BANG_TIEN, true ) ) { continue; }
		$tong++;
		/* HAI LỐI SOI, cả hai đều thật sự soi — nhận cả hai, không ép một lối viết:
		     · `$ok = $wpdb->insert(…)`  rồi `if ( ! $ok )` ở dòng dưới;
		     · `if ( ! $wpdb->insert(…) )` gộp luôn một dòng.
		   Ép đúng một lối là bài đỏ vì CÁCH VIẾT chứ không vì hành vi hỏng — thứ đã làm mất
		   thì giờ nhiều lần trong phiên 22/09. Cái phải bắt là lối THỨ BA: gọi rồi bỏ mặc. */
		$co_soi = 1 === preg_match( "/\\\$\w+\s*=\s*\\\$wpdb->insert\(/", $d )
			|| 1 === preg_match( "/if\s*\(\s*!\s*\\\$wpdb->insert\(/", $d );
		if ( ! $co_soi ) { $ho[] = $ten_tep . ':' . ( $i + 1 ) . ' — bảng `' . $m[1] . '`'; }
	}
}
t( 'tìm được các chỗ ghi vào bảng chứng từ', $tong >= 5, $tong );
t( "🔴 MỌI chỗ ghi vào bảng chứng từ đều SOI KẾT QUẢ ($tong chỗ).\n"
	. "      Chỗ nào không soi thì một lượt ghi hỏng (cột chưa có, ô quá dài, kết nối rớt) vẫn\n"
	. "      ra màn XANH — người dùng tin là xong, đi làm việc khác, và chỉ biết mất gì khi đối\n"
	. "      chiếu sổ. Sửa: đổi `\$wpdb->insert(…)` thành `\$ok = \$wpdb->insert(…)` rồi chối\n"
	. "      kèm `\$wpdb->last_error` như `create_don()` và `add_line()` đang làm.",
	array() === $ho, implode( "\n           ", $ho ) );

/* Và câu chối phải MANG THEO lời của MySQL — không thì lần sau lại đoán một vòng nữa. */
$don_src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
t( '🔴 câu chối mang theo lời của MySQL (`last_error`), không chỉ "có lỗi"',
	substr_count( $don_src, 'wpdb->last_error' ) >= 3, substr_count( $don_src, 'wpdb->last_error' ) );
t( '🔴 `add_line()` chối khi ghi hỏng — đúng chỗ anh Thắng cắn 22/09',
	false !== strpos( $don_src, "return VHCP_Util::err( 'Không ghi được dòng chi vào sổ'" ), '' );

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "✓ SẠCH — $DAT phép: $tong chỗ ghi vào bảng chứng từ đều soi kết quả, không chỗ nào báo xong khi chưa chắc.\n";
