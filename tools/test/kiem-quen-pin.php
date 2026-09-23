<?php
/**
 * KIỂM CỬA "QUÊN PIN" — TRA THEO SỐ CĂN CƯỚC.
 *
 * =============================================================================================
 * 🔴 BÀI HỌC 14/09/2026: HAI CỬA HỎI CÙNG MỘT CÂU, ĐỌC HAI SỔ KHÁC NHAU
 * =============================================================================================
 * Anh Thắng gửi hai ảnh chụp CÙNG MỘT NGƯỜI, nói ngược nhau:
 *   · màn Quản lý nhân sự hiện PIN rõ ràng ("đã vào sổ");
 *   · màn Quên PIN gõ đúng căn cước thì nhận "Hồ sơ có nhưng chưa được cấp mật khẩu đăng nhập".
 *
 * Vì `tra_pin_theo_cccd()` chỉ đọc bảng `phan_quyen` (sổ CŨ), còn cửa đăng nhập
 * `VHCC_Tram::tim_pin()` đọc CẢ HAI kho — `phan_quyen` rồi `nhan_vien.pin_dang_nhap`. Site đã
 * chuyển nguồn người dùng sang `ho_so`, nên PIN nằm ở hồ sơ: người ta ĐĂNG NHẬP ĐƯỢC mà TRA LẠI
 * thì bị chối.
 *
 * ⚠️ Hậu quả nặng hơn một câu báo sai: nó đá người ta sang quản lý cho một việc không có thật,
 *    quản lý đọc "chưa được cấp" thì CẤP MỚI — và PIN của người ta bị đổi trong khi PIN cũ vẫn
 *    đang chạy.
 *
 * Bài này canh đúng một luật: **cửa Quên PIN phải trả lời giống hệt cửa đăng nhập.**
 *
 * Chạy: php tools/test/kiem-quen-pin.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . $mong . ')', $mong === $thuc, is_scalar( $thuc ) ? $thuc : wp_json_encode( $thuc ) );
}

global $wpdb;
$nv = function ( $ma, $ten, $cccd, $pin, $tt = 'Đang làm' ) use ( $wpdb ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
		'ma_nv' => $ma, 'ho_ten' => $ten, 'cccd' => $cccd, 'pin_dang_nhap' => $pin,
		'cua_hang' => 'FZ_LTVT', 'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => $tt ) );
};

/* Số căn cước dựng riêng cho bài thử — KHÔNG dùng số thật của ai. */
$CC_HS  = '111122223333';   // PIN nằm ở HỒ SƠ (nguồn đang dùng)
$CC_PQ  = '444455556666';   // PIN nằm ở sổ PhanQuyen cũ
$CC_KO  = '777788889999';   // có hồ sơ, không có PIN ở đâu cả
$CC_NGHI= '101010101010';   // có PIN nhưng đã nghỉ
$CC_2HS = '121212121212';   // TRÙNG căn cước: một hồ sơ rỗng, một hồ sơ có PIN

$nv( 'Q_HS',  'Người Hồ Sơ',  $CC_HS,  '942565' );
$nv( 'Q_PQ',  'Người Sổ Cũ',  $CC_PQ,  '' );
$nv( 'Q_KO',  'Người Chưa Cấp', $CC_KO, '' );
$nv( 'Q_NGHI','Người Đã Nghỉ', $CC_NGHI, '551122', 'Đã nghỉ việc' );
$nv( 'Q_A',   'Hồ Sơ Tạo Lỡ', $CC_2HS, '' );
$nv( 'Q_B',   'Hồ Sơ Thật',   $CC_2HS, '778899' );

$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array(
	'ma_cc_online' => 'Q_PQ', 'pin' => '330011', 'ho_ten' => 'Người Sổ Cũ',
	'coso_cc_online' => 'POSH_Q1', 'vai_tro' => 'Nhân viên' ) );

echo "── Quên PIN: tra theo số căn cước ──────────────────────\n";

/* ---- 1. PIN nằm ở HỒ SƠ — chính là cảnh anh Thắng gặp ---- */
$r = VHCC_Quyen::tra_pin_theo_cccd( $CC_HS );
t( '🔴 PIN nằm ở HỒ SƠ thì tra RA — không còn báo "chưa được cấp"', ! empty( $r['ok'] ), $r );
teq( 'và trả đúng PIN ấy', '942565', isset( $r['pin'] ) ? $r['pin'] : '(không có)' );
teq( 'kèm đúng tên', 'Người Hồ Sơ', isset( $r['ten'] ) ? $r['ten'] : '' );
teq( 'kèm cơ sở lấy từ hồ sơ', 'FZ_LTVT', isset( $r['coSo'] ) ? $r['coSo'] : '' );

/* 🔴 ĐỐI CHỨNG: cửa ĐĂNG NHẬP vẫn nhận đúng PIN ấy. Không có dòng này thì phép trên xanh mà vẫn
   có thể hai cửa lệch nhau — đúng cái lỗi đang vá. */
$tp = VHCC_Tram::tim_pin( '942565' );
t( '🔴 ĐỐI CHỨNG: cửa đăng nhập cũng nhận đúng PIN ấy', ! empty( $tp['thay'] ), $tp );
teq( 'và ra cùng một người', 'Q_HS', isset( $tp['ma_nv'] ) ? $tp['ma_nv'] : '' );

/* ---- 2. PIN nằm ở sổ PhanQuyen cũ — nết cũ phải giữ nguyên ---- */
$r = VHCC_Quyen::tra_pin_theo_cccd( $CC_PQ );
t( 'PIN ở sổ PhanQuyen cũ vẫn tra ra như trước', ! empty( $r['ok'] ), $r );
teq( 'đúng PIN của sổ cũ', '330011', isset( $r['pin'] ) ? $r['pin'] : '' );
teq( 'và cơ sở lấy theo sổ cũ', 'POSH_Q1', isset( $r['coSo'] ) ? $r['coSo'] : '' );

/* ---- 3. Không có PIN ở đâu cả — câu chối CŨ vẫn đúng, và chỉ lúc này mới đúng ---- */
$r = VHCC_Quyen::tra_pin_theo_cccd( $CC_KO );
t( 'không có PIN ở kho nào thì mới báo "chưa được cấp"', empty( $r['ok'] ), $r );
t( 'và câu chối chỉ đúng người cần nhờ',
	isset( $r['error'] ) && false !== strpos( $r['error'], 'quản lý cửa hàng' ), $r );

/* ---- 4. Đã nghỉ thì KHÔNG đưa PIN ra ---- */
$r = VHCC_Quyen::tra_pin_theo_cccd( $CC_NGHI );
t( '🔴 người đã nghỉ KHÔNG tra được PIN — đưa PIN là đưa chìa khoá', empty( $r['ok'] ), $r );
t( 'nhưng nói thẳng là "đã nghỉ", đừng để họ gõ lại mãi',
	isset( $r['error'] ) && false !== strpos( $r['error'], 'nghỉ' ), $r );
t( '⚠️ và câu chối KHÔNG chở theo PIN',
	! isset( $r['pin'] ) && ( ! isset( $r['error'] ) || false === strpos( $r['error'], '551122' ) ), $r );

/* ---- 5. TRÙNG căn cước: phải vớ đúng hồ sơ CÓ PIN ---- */
$r = VHCC_Quyen::tra_pin_theo_cccd( $CC_2HS );
t( '🔴 trùng căn cước thì lấy hồ sơ CÓ PIN, không vớ hồ sơ tạo lỡ', ! empty( $r['ok'] ), $r );
teq( 'đúng PIN của hồ sơ thật', '778899', isset( $r['pin'] ) ? $r['pin'] : '' );

/* ---- 6. Số không có trong sổ ---- */
$r = VHCC_Quyen::tra_pin_theo_cccd( '999900001111' );
t( 'số lạ thì báo không tìm thấy', empty( $r['ok'] ), $r );
t( 'và chỉ đường sang ô gửi thông tin',
	isset( $r['error'] ) && false !== strpos( $r['error'], 'Gửi thông tin' ), $r );

/* ---- 7. Số sai định dạng ---- */
foreach ( array( '123', '', 'abcdefgh' ) as $xau ) {
	$r = VHCC_Quyen::tra_pin_theo_cccd( $xau );
	t( 'số "' . $xau . '" bị chối vì sai định dạng', empty( $r['ok'] ), $r );
}

/* ---- 8. NHẬT KÝ KHÔNG ĐƯỢC GHI PIN ---- */
$nk = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhat_ky_pin' ) );
if ( $nk ) {
	$co_pin = false;
	foreach ( $nk as $d ) {
		foreach ( (array) $d as $o ) {
			if ( false !== strpos( (string) $o, '942565' ) || false !== strpos( (string) $o, '330011' ) ) {
				$co_pin = true;
			}
		}
	}
	t( '🔴 nhật ký tra PIN KHÔNG ghi PIN ra sổ', ! $co_pin, $nk );
	$che = false;
	foreach ( $nk as $d ) { foreach ( (array) $d as $o ) {
		if ( false !== strpos( (string) $o, '111122223333' ) ) { $che = true; }
	} }
	t( '🔴 và KHÔNG ghi nguyên số căn cước', ! $che, $nk );
}

/* ---- 9. Mã nguồn: không còn đường đọc MỘT kho ---- */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-quyen.php' );
$than = substr( $src, strpos( $src, 'function tra_pin_theo_cccd' ) );
$than = substr( $than, 0, strpos( $than, "\n\t}" ) );
t( '🔴 hàm tra có đọc kho hồ sơ', false !== strpos( $than, 'pin_dang_nhap' ), 'thiếu' );
t( 'và vẫn đọc kho PhanQuyen', false !== strpos( $than, 'ma_cc_online' ), 'thiếu' );
t( '⚠️ không còn `break` sau hồ sơ đầu tiên khớp căn cước',
	false === strpos( $than, '{ $hs = $r; break; }' ), 'còn break' );

echo "\n";
if ( $truot ) {
	echo '✗ TRƯỢT ' . count( $truot ) . ' / ' . ( $dat + count( $truot ) ) . ":\n";
	foreach ( $truot as $x ) { echo '    · ' . $x . "\n"; }
	exit( 1 );
}
echo "✓ ĐẠT — $dat phép: cửa Quên PIN trả lời giống hệt cửa đăng nhập.\n";
