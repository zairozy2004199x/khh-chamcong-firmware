<?php
/**
 * DẢI NHẮC CHẤM CÔNG TRÊN APP GHẾ — NHẮC, KHÔNG CHẶN.
 *
 * =================================================================================================
 * Anh Thắng 18/09/2026, hai câu liền nhau:
 *   · *"khi nhân viên muốn làm báo cáo posh thì bắt buộc chấm công, rồi mới được nộp báo cáo"*
 *   · rồi đổi ý ngay: *"hãy loại bỏ tính năng bắt checkin mới nộp báo cáo, mà hãy chỉ đưa cảnh
 *     báo thôi"*.
 * =================================================================================================
 * 🔴 BÀI NÀY CANH ĐÚNG VẾ THỨ HAI, VÀ CANH CẢ CHIỀU NGƯỢC LẠI.
 * Một dải cảnh báo thì dễ; cái dễ hỏng là ai đó về sau đọc thấy dải này rồi "làm cho chặt" bằng
 * một dòng `disabled`. Nên có phép thử đòi KHÔNG có chốt chặn nào trong màn quỹ.
 *
 * 🔴 VÀ CANH CHỖ IM LẶNG. Phiên `/ghe` chỉ mang HỌ TÊN, không mang mã NV — phải tra ngược qua sổ
 * người dùng. Trùng tên, hoặc không tra ra mã, thì PHẢI im: một dải vàng nói sai về người đang
 * đứng đọc nó còn tệ hơn không nói gì.
 *
 * Chạy: php tools/test/kiem-nhac-cham-ghe.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 300 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}

/* ══════════════════════════════════════════════════════ 1. MÃ NGUỒN: NHẮC, KHÔNG CHẶN */

$tr = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' );
t( 'app ghế có hàm dò chấm công', false !== strpos( $tr, 'function nhac_cham_cong(' ) );
t( 'và gửi kèm trong gói tin', 2 === substr_count( $tr, "'nhacCham' => self::nhac_cham_cong( \$ai )" ),
	substr_count( $tr, "'nhacCham' => self::nhac_cham_cong( \$ai )" ) );
t( '🔴 giao diện có dải nhắc', false !== strpos( $tr, 'function daiNhacCham(' ) );
t( '   và dải ấy được vẽ ở tab Quỹ & nộp tiền',
	false !== strpos( $tr, 'daiNhacCham() +' ), $tr );

/* 🔴 KHÔNG CHẶN. Cắt đúng thân `daiNhacCham()` ra soi: trong đó không được có `disabled`, và
   không được có chữ nào khoá nút. */
$i_d = strpos( $tr, 'function daiNhacCham(' );
$than_d = ( false === $i_d ) ? '' : substr( $tr, $i_d, 900 );
t( '🔴 dải nhắc KHÔNG tắt nút nào', false === strpos( $than_d, 'disabled' ), $than_d );
t( '🔴 và nói rõ là vẫn nộp được',
	false !== mb_strpos( $than_d, 'Vẫn nộp báo cáo và chốt ca bình thường' ), $than_d );

/* Chiều ngược: cả tệp KHÔNG được có chốt kiểu "chưa chấm công thì chối". */
t( '🔴 không có cửa nào chối vì chưa chấm công',
	false === mb_strpos( $tr, 'chưa chấm công nên không nộp' )
	&& false === mb_strpos( $tr, 'phải chấm công trước' ), $tr );

/* ⚠️ Gác cross-plugin phải nằm CÙNG HÀM với lời gọi — luật `kiem-goi-cheo.php`. */
$i_n = strpos( $tr, 'private static function nhac_cham_cong(' );
$than_n = ( false === $i_n ) ? '' : substr( $tr, $i_n, 2600 );
t( '🔴 gác class_exists + method_exists cùng hàm với lời gọi VHCC_Online',
	false !== strpos( $than_n, "class_exists( 'VHCC_Online' )" )
	&& false !== strpos( $than_n, "method_exists( 'VHCC_Online', 'hom_nay' )" ), $than_n );
t( '🔴 trùng tên thì IM LẶNG, không đoán mã NV',
	false !== mb_strpos( $than_n, 'trùng tên -> im' ), $than_n );

/* ══════════════════════════════════════════ 2. LÕI: "hôm nay đã chấm chưa" trả đúng */

global $wpdb;
$CS = 'NHAC_CS';
$NG = current_time( 'Y-m-d' );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NH1', 'ho_ten' => 'Người Có Chấm',
	'cua_hang' => $CS, 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NH2', 'ho_ten' => 'Người Chưa Chấm',
	'cua_hang' => $CS, 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => $NG,
	'ma_nv' => 'NH1', 'ho_ten' => 'Người Có Chấm', 'gio_vao_giay' => 8 * 3600,
	'gio_ra_giay' => 0, 'hau_to' => '', 'nguon' => 'online' ) );

t( '🔴 người ĐÃ chấm hôm nay: lõi trả về có dòng',
	count( (array) VHCC_Online::hom_nay( $CS, 'NH1' ) ) > 0 );
t( '🔴 người CHƯA chấm: lõi trả về rỗng',
	0 === count( (array) VHCC_Online::hom_nay( $CS, 'NH2' ) ) );
/* ⚠️ Chấm VÀO mà chưa chấm RA vẫn tính là đã đi làm — dải nhắc hỏi "đã tới chưa", không hỏi
   "đã xong ca chưa". Người đang giữa ca mà bị nhắc là nhắc sai. */
$hn = (array) VHCC_Online::hom_nay( $CS, 'NH1' );
t( '🔴 mới chấm VÀO (chưa ra) vẫn tính là đã chấm', isset( $hn[0] ) && '' !== (string) $hn[0]['vao'], $hn );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — app ghế NHẮC chấm công, và không chặn ai nộp báo cáo.\n";
