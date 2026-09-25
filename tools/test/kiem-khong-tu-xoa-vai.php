<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 NẠP PLUGIN KHÔNG ĐƯỢC TỰ Ý ĐỤNG VÀO VAI TRÒ CỦA AI.
 *
 * Anh Thắng 21/09/2026 bảo *"xóa luôn mấy vai trò đó đi, không cho nó hiện"*. Em hiểu thành
 * "máy tự dọn" và cho hẳn một lượt quét vào `seed()` (bản 1.229.0). Anh chối ngay:
 * *"như xóa vai trò là đang sai"*.
 *
 * Hai câu ấy KHÔNG mâu thuẫn, và chỗ em hiểu sai đáng ghi lại: anh muốn mấy vai kia biến khỏi
 * ô chọn — một việc anh tự làm bằng tay trong ba mươi giây — chứ không muốn MÁY tự ý sửa bảng
 * phân quyền của cả công ty lúc nạp plugin.
 *
 * =============================================================================================
 * 🔴 VÌ SAO MỘT LƯỢT DỌN TỰ ĐỘNG LÀ SAI, KỂ CẢ KHI NÓ "ĐÚNG Ý"
 * =============================================================================================
 *   · Nó chạy lúc NẠP PLUGIN, không phải lúc người ta bấm nút. Không ai kịp xem trước, không
 *     ai bấm đồng ý, không có nút hoàn tác.
 *   · Nó đụng hai bảng cùng lúc — bảng vai VÀ vai của từng tài khoản. Sai một nước là quyền
 *     của cả công ty lệch đi, im lặng.
 *   · `CH_Quyen` lưu theo CHỈ SỐ CỘT, mỗi vai một cột. Xoá vai là cột ấy mồ côi — thứ chỉ lộ
 *     ra nhiều ngày sau, ở một màn khác.
 *
 * ⚠️ BÀI NÀY LÀ MỘT PHÉP CẤM, không phải phép kiểm tính năng. Nó gieo đúng bộ vai trên ảnh anh
 *    Thắng gửi rồi chạy `seed()` và đòi MỌI THỨ Y NGUYÊN. Ai thêm lại một lượt dọn tự động là
 *    nó đỏ ngay.
 *
 * Chạy: php tools/test/kiem-khong-tu-xoa-vai.php
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

function chup_vai() {
	$ra = array();
	foreach ( VHCP_Cfg::read( VHCP_Cfg::USER ) as $r ) {
		$r = array_values( (array) $r );
		if ( '' === trim( (string) $r[0] ) ) { continue; }
		$ra[ $r[0] ] = array( 'vai' => isset( $r[2] ) ? $r[2] : '', 'bp' => isset( $r[6] ) ? $r[6] : '' );
	}
	return $ra;
}

/* ═══ GIEO ĐÚNG BỘ VAI TRÊN ẢNH ANH THẮNG GỬI ════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::VAI, array(
	array( 'Nhân Viên Cơ Sơ',     'Nhân viên' ),
	array( 'Nhân Viên Văn Phòng', 'Nhân viên' ),
	array( 'Nhân Viên Kỹ Thuật',  'Nhân viên' ),
	array( 'Nhân Viên Marketing', 'Nhân viên' ),
	array( 'Kế toán máy tự động', 'Kế toán cá nhân' ),
), false );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Nguyễn Hữu Thọ',   '1111', 'Nhân Viên Kỹ Thuật',  '', '', '', 'Kỹ thuật',    '', '' ),
	array( 'Nguyễn Phương Vy', '2222', 'Nhân Viên Marketing', '', '', '', 'Marketing',   '', '' ),
	array( 'Chị Kế Toán MTĐ',  '3333', 'Kế toán máy tự động', '', '', '', 'Máy tự động', '', '' ),
	array( 'Sếp',              '5555', 'Admin',               '', '', '', '',            '', '' ),
), false );
VHCP_Cfg::clear_cache();

$vai_truoc = VHCP_Cfg::vai_tuy_bien();
$ng_truoc  = chup_vai();
teq( 'gieo xong có 5 vai tự tạo', 5, count( $vai_truoc ) );

/* ═══ 🔴 CHẠY `seed()` NHIỀU LƯỢT — KHÔNG ĐƯỢC ĐỔI MỘT CHỮ ═══════════════════════
 * Nhiều lượt, vì một lượt dọn "chỉ chạy một lần" cũng phải bị bắt: lượt đầu tiên là lượt gây
 * hại, và trên máy anh Thắng nó xảy ra ngay lần nạp cấu hình đầu sau khi cài. */
for ( $i = 1; $i <= 3; $i++ ) {
	VHCP_Cfg::seed();
	VHCP_Cfg::clear_cache();
	teq( "🔴 lượt nạp $i: bảng vai còn nguyên 5 dòng", 5, count( VHCP_Cfg::vai_tuy_bien() ) );
	teq( "   lượt nạp $i: từng vai y nguyên tên + vai gốc", $vai_truoc, VHCP_Cfg::vai_tuy_bien() );
	teq( "🔴 lượt nạp $i: vai của từng người KHÔNG bị dời", $ng_truoc, chup_vai() );
}

/* Và mấy vai ấy vẫn đứng trong danh sách cột của ma trận phân quyền — xoá chúng sau lưng là
   cột phân quyền mồ côi. */
$cot = VHCP_Cfg::roles();
foreach ( array( 'Nhân Viên Kỹ Thuật', 'Kế toán máy tự động' ) as $v ) {
	t( 'vai «' . $v . '» vẫn là một cột của ma trận phân quyền', in_array( $v, $cot, true ), $cot );
}

/* ═══ 🔴 SOI MÃ: KHÔNG CÓ CỬA GHI NÀO VÀO BẢNG VAI TRONG LƯỢT GIEO ═══════════════
 * Phép trên đo hành vi với đúng bộ dữ liệu này. Phép dưới gác chính mã nguồn, để một lượt dọn
 * núp sau điều kiện khác (chỉ chạy với site có N vai, chỉ chạy một lần…) cũng không lọt. */
$src  = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );
$i0   = mb_strpos( $src, 'private static function seed_from(' );
$i1   = false === $i0 ? false : mb_strpos( $src, "\n\t/**", $i0 );
$khoi = ( false === $i0 ) ? '' : mb_substr( $src, $i0, ( false === $i1 ? mb_strlen( $src ) : $i1 ) - $i0 );
t( 'cắt được thân `seed_from()` để soi', mb_strlen( $khoi ) > 500, mb_strlen( $khoi ) );
/* Gỡ chú thích trước khi dò — chính khối chú thích giải thích lượt gỡ này có nhắc tên bảng. */
$sach = (string) preg_replace( '#/\*.*?\*/#su', '', $khoi );
$sach = (string) preg_replace( '#//[^\n]*#u', '', $sach );
foreach ( array( 'self::VAI', "'CH_VaiTro'" ) as $x ) {
	t( "🔴 lượt gieo KHÔNG đụng tới bảng vai (`$x`)", false === mb_strpos( $sach, $x ), $x );
}
t( '🔴 và không ghi đè ô Vai trò của tài khoản nào',
	! preg_match( '#set_cell\(\s*self::USER\s*,[^,]+,\s*2\s*,#u', $sach ), 'có lệnh ghi ô vai trò' );

/* ═══ ĐƯỜNG DỌN BẰNG TAY VẪN CÒN, VÀ VẪN HỎI TRƯỚC ═══════════════════════════════
 * Bỏ lượt tự động không có nghĩa là bỏ luôn cách dọn. Anh Thắng xoá dòng ở bảng 🎭 Vai trò rồi
 * bấm Lưu — đường ấy có sẵn chốt đếm-trước-rồi-hỏi. */
$app = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 màn vẫn có nút Lưu vai trò để dọn tay', false !== mb_strpos( $app, 'saveCfgVai()' ) );
t( '   và vẫn liệt kê ai đang mang vai sắp xoá trước khi lưu',
	false !== mb_strpos( $app, 'người đang mang vai sắp bị xoá' ) );

VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
VHCP_Cfg::save_config( array( 'vaiTro' => array(
	array( 'ten' => 'Kế toán máy tự động', 'goc' => 'Kế toán cá nhân' ),
) ) );
VHCP_Cfg::clear_cache();
teq( '🔴 người bấm Lưu thì bảng ĐỔI ĐƯỢC — chỉ máy mới không được tự làm',
	1, count( VHCP_Cfg::vai_tuy_bien() ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: nạp plugin không tự đụng vai trò của ai; muốn dọn thì người bấm.\n";
