<?php
/**
 * KIỂM "CÔNG BÙ THEO BẬC THANG GIỜ RA".
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 26/09/2026: *"chỉnh cho phép set công bù, ví dụ giờ ra trước 2h là + 0,5 công bù,
 * trước 4h là 1 công bù, trước 8h sáng là 1,5 công bù"* — công nghỉ bù sau một ca đêm không còn
 * là một số cố định (`demCongBu`), mà tuỳ giờ RA của ca đêm rơi vào mốc nào trong ba mốc do Admin
 * tự đặt (`VHCC_Luong::VP_O['demBuMoc1'..'demBuMoc3']` / `['demBuSo1'..'demBuSo3']`).
 *
 * Chạy: php tools/test/kiem-cong-bu-bac-thang.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
define( 'VHCC_TEST', 1 );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $ky_vong, $thuc, $them = null ) {
	t( $ten . ' (kỳ vọng ' . wp_json_encode( $ky_vong, JSON_UNESCAPED_UNICODE ) . ', được '
		. wp_json_encode( $thuc, JSON_UNESCAPED_UNICODE ) . ')', $ky_vong === $thuc, $them );
}
$u_ad = array( 'role' => 'Admin' );

/**
 * Dựng một ca đêm 20:00 (ngày `$ngay`) -> `$gio_ra` (hôm sau) và trả về công bù rơi vào ngày kế.
 */
function bu_cua( $cfg, $ngay, $gio_ra ) {
	$ra = VHCC_DB::giay( $gio_ra ) + VHCC_DB::NGAY_GIAY;
	$out = VHCC_Luong::vp_tinh_nguoi( $cfg, false, array(
		$ngay => array( 'dem' => array( VHCC_DB::giay( '20:00:00' ), $ra ) ),
	) );
	$sau = VHCC_Luong::ngay_sau( $ngay );
	return isset( $out[ $sau ] ) ? (float) $out[ $sau ]['congBu'] : 0.0;
}

/* ═══════════════════════════ 1. BA MỐC MẶC ĐỊNH — 02:00 / 04:00 / 08:00, 0.5 / 1 / 1.5 ═══════ */
$cfg = VHCC_Luong::vp_cfg( '' );
teq( 'dựng cảnh: mốc 1 mặc định 02:00', '02:00', $cfg['demBuMoc1'] );
teq( 'dựng cảnh: mốc 2 mặc định 04:00', '04:00', $cfg['demBuMoc2'] );
teq( 'dựng cảnh: mốc 3 mặc định 08:00', '08:00', $cfg['demBuMoc3'] );

teq( '🔴 ra 01:00 (trước mốc 1) -> mức 1 (0.5)', 0.5, bu_cua( $cfg, '2026-09-01', '01:00:00' ) );
teq( '🔴 ra ĐÚNG mốc 1 (02:00) -> vẫn mức 1 (biên bao gồm)', 0.5, bu_cua( $cfg, '2026-09-02', '02:00:00' ) );
teq( '🔴 ra 02:01 (ngay sau mốc 1) -> mức 2 (1)', 1.0, bu_cua( $cfg, '2026-09-03', '02:01:00' ) );
teq( '🔴 ra ĐÚNG mốc 2 (04:00) -> vẫn mức 2 (biên bao gồm — khớp hành vi cũ demCongBu=1)',
	1.0, bu_cua( $cfg, '2026-09-04', '04:00:00' ) );
teq( '🔴 ra 04:01 (ngay sau mốc 2) -> mức 3 (1.5)', 1.5, bu_cua( $cfg, '2026-09-05', '04:01:00' ) );
teq( '🔴 ra ĐÚNG mốc 3 (08:00) -> vẫn mức 3', 1.5, bu_cua( $cfg, '2026-09-06', '08:00:00' ) );
teq( '🔴 ra TRỄ HƠN cả mốc 3 (13:37) -> vẫn mức 3 CAO NHẤT, không rơi về 0',
	1.5, bu_cua( $cfg, '2026-09-07', '13:37:00' ) );

/* ═══════════════════════════ 2. CHỈNH ĐƯỢC MỐC/SỐ — đúng yêu cầu "chỉnh cho phép set" ═══════ */
$r_dat = VHCC_Luong::dat_vp_cfg( $u_ad, array(
	'demBuMoc1' => '01:00', 'demBuSo1' => 0.25,
	'demBuMoc2' => '03:00', 'demBuSo2' => 0.75,
	'demBuMoc3' => '06:00', 'demBuSo3' => 2,
), '', '' );
t( '🔴 lưu bộ mốc/số tuỳ chỉnh -> ok', ! empty( $r_dat['ok'] ), $r_dat );
$cfg2 = VHCC_Luong::vp_cfg( '' );
teq( '   đọc lại đúng mốc 1 đã đổi', '01:00', $cfg2['demBuMoc1'] );
teq( '   đọc lại đúng số mức 3 đã đổi', 2.0, (float) $cfg2['demBuSo3'] );
teq( '🔴 ca ra 02:30 với bộ mốc MỚI (> mốc1 01:00, <= mốc2 03:00) -> mức 2 (0.75)',
	0.75, bu_cua( $cfg2, '2026-09-08', '02:30:00' ) );
teq( '   ra 07:00 (> mốc3 06:00) -> mức 3 MỚI (2), không phải mức 3 cũ (1.5)',
	2.0, bu_cua( $cfg2, '2026-09-09', '07:00:00' ) );
/* Trả cấu hình chung về mặc định để không rò sang phần dưới / các bài thử khác chạy chung tiến
   trình (không áp dụng ở đây vì mỗi bài thử là một tiến trình PHP riêng — nhưng vẫn dọn cho rõ
   ràng, phòng ai sau này gộp file). */
VHCC_Luong::dat_vp_cfg( $u_ad, array(
	'demBuMoc1' => '02:00', 'demBuSo1' => 0.5,
	'demBuMoc2' => '04:00', 'demBuSo2' => 1,
	'demBuMoc3' => '08:00', 'demBuSo3' => 1.5,
), '', '' );

/* ═══════════════════════════ 3. SOÁT LỖI — không cho lưu bộ số vô lý ═══════════════════════ */
$r_loi_tt = VHCC_Luong::dat_vp_cfg( $u_ad, array( 'demBuMoc1' => '05:00', 'demBuMoc2' => '03:00' ), '', '' );
t( '🔴 mốc 2 SỚM HƠN mốc 1 -> bị chối', empty( $r_loi_tt['ok'] ), $r_loi_tt );
t( '   lỗi nói rõ phải xếp tăng dần', strpos( (string) $r_loi_tt['error'], 'TĂNG DẦN' ) !== false, $r_loi_tt );

$r_loi_tt2 = VHCC_Luong::dat_vp_cfg( $u_ad, array( 'demBuMoc2' => '09:00', 'demBuMoc3' => '08:00' ), '', '' );
t( '🔴 mốc 3 SỚM HƠN mốc 2 -> cũng bị chối', empty( $r_loi_tt2['ok'] ), $r_loi_tt2 );

$r_loi_am = VHCC_Luong::dat_vp_cfg( $u_ad, array( 'demBuSo1' => -0.5 ), '', '' );
t( '🔴 công bù ÂM -> bị chối', empty( $r_loi_am['ok'] ), $r_loi_am );

$r_loi_gio = VHCC_Luong::dat_vp_cfg( $u_ad, array( 'demBuMoc1' => 'không phải giờ' ), '', '' );
t( '🔴 mốc không đúng dạng HH:mm -> bị chối', empty( $r_loi_gio['ok'] ), $r_loi_gio );

/* Sau các lượt bị chối, cấu hình chung KHÔNG được đổi (giữ nguyên bản đã lưu ở mục 2/dọn lại). */
$cfg3 = VHCC_Luong::vp_cfg( '' );
teq( '🔴 lượt bị chối KHÔNG làm hỏng cấu hình đang lưu (mốc 1 vẫn 02:00)', '02:00', $cfg3['demBuMoc1'] );

/* ═══════════════ 4. Ô CẤU HÌNH CÓ MẶT TRÊN CẢ HAI MÀN (VP_O + màn wp-admin cũ) ═══════════════ */
foreach ( array( 'demBuMoc1', 'demBuSo1', 'demBuMoc2', 'demBuSo2', 'demBuMoc3', 'demBuSo3' ) as $k ) {
	t( "🔴 VP_O có khai '$k'", array_key_exists( $k, VHCC_Luong::VP_O ) );
}
t( '🔴 demCongBu CŨ đã gỡ khỏi VP_O (thay hẳn bằng bậc thang, không còn hai bộ số song song)',
	! array_key_exists( 'demCongBu', VHCC_Luong::VP_O ) );

/* ═══════════════════════════════════════════════════════════════════════════ KẾT QUẢ ═══ */
if ( $truot ) {
	echo "TRƯỢT " . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — công bù chỉnh được theo bậc thang giờ ra, đúng luật, chối được số vô lý.\n";
