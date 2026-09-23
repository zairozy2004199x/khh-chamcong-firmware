<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CỜ "ĐÂY LÀ ĐƠN CHI PHÍ CƠ SỞ" PHẢI ĐI TỪ MÁY CHỦ XUỐNG MỌI MÀN
 *
 * Anh Thắng 11/09/2026, nhìn khối duyệt lệnh của một đơn chi phí cơ sở theo tuần:
 *   *"Lệnh tạm ứng theo chi phí kỹ thuật chứ"*  ·  *"Dự án tuần thì nó đâu có thười gian setup"*
 *
 * =============================================================================================
 * 🔴 MÀN KHÔNG ĐƯỢC TỰ SO CHUỖI "Chi phí cơ sở". So ở màn là nơi thứ hai phải sửa mỗi lần đổi
 *    tên loại, và nơi thứ hai thì sớm muộn lệch — lúc ấy một nửa số màn gọi đúng tên, nửa kia
 *    gọi sai, mà không có gì đỏ lên.
 *
 * 🔴 BA NƠI GỬI CỜ, KHÔNG PHẢI MỘT: trang dự án (`get_du_an`), bảng hạng mục (`list_don_hm`) và
 *    khối duyệt lệnh (`list_lenh_da`). Anh Thắng bắt đúng chỗ thứ ba — chỗ hai màn kia không đi
 *    qua. Thiếu một nơi là bảng ấy lặng lẽ gọi sai tên trở lại.
 *
 * ⚠️ CHẠY THẬT trên CSDL giả: tạo một đơn cơ sở và một dự án Setup, rồi đọc cờ ở cả ba cửa.
 *
 * Chạy: php tools/test/kiem-co-don-coso.php
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

VHCP_Cfg::seed();
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. CHỐT GỐC — một phép so, một chỗ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'hằng tên loại đúng chữ đang dùng', 'Chi phí cơ sở', VHCP_DuAn::LOAI_COSO );
teq( '🔴 "Chi phí cơ sở" là ĐƠN',        true,  VHCP_DuAn::la_don_coso( 'Chi phí cơ sở' ) );
teq( '🔴 "Setup lắp đặt" KHÔNG phải đơn', false, VHCP_DuAn::la_don_coso( 'Setup lắp đặt' ) );
teq( '🔴 "Tháo dỡ" KHÔNG phải đơn',      false, VHCP_DuAn::la_don_coso( 'Tháo dỡ' ) );
teq( 'loại rỗng cũng không phải đơn',    false, VHCP_DuAn::la_don_coso( '' ) );
teq( 'thừa khoảng trắng vẫn nhận ra',    true,  VHCP_DuAn::la_don_coso( '  Chi phí cơ sở  ' ) );
/* ⚠️ CÓ PHÂN BIỆT HOA THƯỜNG — cột `loai` do chính mã này ghi, không phải người gõ, nên so
   chặt là đúng: một chữ khác hoa thường nghĩa là ai đó vừa ghi loại bằng đường khác. */
teq( 'khác hoa thường thì KHÔNG nhận (loại do mã ghi, không do người gõ)',
	false, VHCP_DuAn::la_don_coso( 'chi phí cơ sở' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. CỜ ĐI XUỐNG ĐỦ BA CỬA
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$don = VHCP_DuAn::tao_don_coso( 'Chi phí cơ sở tuần 07.09-13.09.2026', 'Sếp', '07/09/2026', '13/09/2026' );
t( 'tạo được đơn cơ sở', ! empty( $don['success'] ), $don );
$ma_don = $don['maDA'];

$da = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Setup gian ADV GO AN LẠC', 'Sếp' );
t( 'tạo được dự án Setup', ! empty( $da['success'] ), $da );
$ma_da = $da['maDA'];

/* ---- cửa 1: trang dự án ---- */
$g1 = VHCP_DuAn::get_du_an( $ma_don );
teq( '🔴 get_du_an: đơn cơ sở -> isCoSo = true', true, $g1['isCoSo'] );
$g2 = VHCP_DuAn::get_du_an( $ma_da );
teq( '🔴 get_du_an: dự án Setup -> isCoSo = false', false, $g2['isCoSo'] );

/* ---- cửa 2 & 3: bảng hạng mục và khối duyệt lệnh ----
   Cả hai chỉ liệt kê dự án CÓ hạng mục, nên phải thêm dòng trước đã. */
VHCP_DuAn::add_line( $ma_don, array( 'noiDung' => 'Cáp màn hình', 'gian' => 'VR SC Vivo Q7',
	'soLuong' => 1, 'donGia' => 500000, 'duToan' => 500000 ) );
VHCP_DuAn::add_line( $ma_da, array( 'noiDung' => 'Thi công sàn',
	'soLuong' => 1, 'donGia' => 900000, 'duToan' => 900000 ) );

/** Số hàng của hạng mục lớn đầu tiên — KHÔNG đoán là 1: bảng dự án có mấy hàng khuôn sẵn. */
function hang_dau( $ma ) {
	$g = VHCP_DuAn::get_du_an( $ma );
	foreach ( (array) $g['lines'] as $l ) {
		if ( trim( (string) $l['capCha'] ) === '' ) { return (int) $l['row']; }
	}
	return 0;
}

/** Cờ isCoSo của một mã trong danh sách trả về. */
function co_cua( $items, $ma ) {
	foreach ( (array) $items as $x ) {
		if ( isset( $x['maDA'] ) && $x['maDA'] === $ma ) {
			return array_key_exists( 'isCoSo', $x ) ? $x['isCoSo'] : 'THIẾU CỜ';
		}
	}
	return 'KHÔNG THẤY DÒNG';
}

$hm = VHCP_DuAn::list_don_hm();
teq( '🔴 list_don_hm: đơn cơ sở -> true',   true,  co_cua( $hm['items'], $ma_don ) );
teq( '🔴 list_don_hm: dự án Setup -> false', false, co_cua( $hm['items'], $ma_da ) );

/* Khối duyệt lệnh chỉ có dòng khi đã XIN tạm ứng — đó đúng là lúc anh Thắng nhìn thấy chữ sai. */
$x1 = VHCP_DuAn::xin_tam_ung_dot( $ma_don, array( hang_dau( $ma_don ) ) );
t( 'xin được tạm ứng cho đơn cơ sở', ! empty( $x1['success'] ), $x1 );
$x2 = VHCP_DuAn::xin_tam_ung_dot( $ma_da,  array( hang_dau( $ma_da ) ) );
t( 'xin được tạm ứng cho dự án Setup', ! empty( $x2['success'] ), $x2 );
$ln = VHCP_DuAn::list_lenh_da();
teq( '🔴 list_lenh_da: đơn cơ sở -> true',   true,  co_cua( $ln['items'], $ma_don ) );
teq( '🔴 list_lenh_da: dự án Setup -> false', false, co_cua( $ln['items'], $ma_da ) );

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
echo "\n";
if ( $TRUOT ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — cờ isCoSo đi đủ ba cửa, màn khỏi phải so chuỗi\n";
