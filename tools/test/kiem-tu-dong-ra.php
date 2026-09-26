<?php
/**
 * KIỂM "TỰ ĐỘNG GÁN GIỜ RA MẶC ĐỊNH" — `VHCC_TuDongRa::quet()`.
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 26/09/2026: nhân viên hay quên bấm giờ ra. Muốn hệ thống tự lấp một giờ ra MẶC ĐỊNH
 * khi tới mốc trễ nhất định vẫn chưa thấy — 22:00 cho ca ngày (cơ sở chính Văn phòng, gán
 * `ngayDen`), 08:00 cho ca đêm (cơ sở phụ đã ghép, gán `demDen` đã trải phẳng vào hàng hôm qua).
 * Không được đè giờ ra THẬT (kể cả tăng ca muộn), không được viết đè quyết định Admin đã cố ý
 * xoá trắng, và bật/tắt được riêng theo khối.
 *
 * Chạy: php tools/test/kiem-tu-dong-ra.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
global $wpdb;

$CHINH = 'VP_TDR'; $PHU = 'SETUP_TDR';
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $CHINH, 'bo_phan' => 'Văn phòng' ) );
/* Cơ sở phụ CỐ Ý không khai bộ phận — đúng hình dạng sản xuất (xem `kiem-don-dem.php`). */
foreach ( array( array( 'TDR1', 'Người Ca Ngày' ), array( 'TDR2', 'Người Ca Đêm' ) ) as $x ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $x[0], 'ho_ten' => $x[1],
		'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
}
VHCC_Luong::dat_ghep( array( 'role' => 'Admin' ), array( $PHU => $CHINH ) );
$g = function ( $chu ) { return VHCC_DB::giay( $chu ); };
$hang = function ( $coso, $ngay, $ma, $hau_to = '' ) use ( $wpdb ) {
	return $wpdb->get_row( $wpdb->prepare(
		'SELECT * FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s AND ngay=%s AND ma_nv=%s AND hau_to=%s',
		$coso, $ngay, $ma, $hau_to ), ARRAY_A );
};

/* ═══════════════════════════════ 1. CA NGÀY — cơ sở CHÍNH ═══════════════════════════════ */
vhcp_test_dat_gio( '2026-09-10 09:00:00' );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CHINH, 'ngay' => '2026-09-10',
	'ma_nv' => 'TDR1', 'hau_to' => '', 'ho_ten' => 'Người Ca Ngày',
	'gio_vao_giay' => $g( '08:30:00' ), 'gio_ra_giay' => null, 'nguon' => 'online', 'chuan' => '08:30' ) );

/* TRƯỚC 22:00 -> chưa quét gì cả. */
vhcp_test_dat_gio( '2026-09-10 21:59:00' );
VHCC_TuDongRa::quet();
t( '🔴 trước 22:00: chưa đụng gì, giờ ra vẫn trống',
	null === $hang( $CHINH, '2026-09-10', 'TDR1' )['gio_ra_giay'], $hang( $CHINH, '2026-09-10', 'TDR1' ) );

/* SAU 22:00 -> tự gán ngayDen (17:00) làm giờ ra. */
vhcp_test_dat_gio( '2026-09-10 22:05:00' );
$kq1 = VHCC_TuDongRa::quet();
t( '🔴 sau 22:00: tự gán "Ca ngày đến" (17:00) làm giờ ra', 1 === (int) $kq1['ngay'], $kq1 );
$h1 = $hang( $CHINH, '2026-09-10', 'TDR1' );
t( '   hàng tồn tại', null !== $h1, $h1 );
t( '   giờ ra đúng 17:00', $h1 && '17:00:00' === VHCC_DB::hhmmss( $h1['gio_ra_giay'] ), $h1 );
t( '   nguồn đổi thành hỗn hợp (trước là online, nay thêm tu_dong)', 'hon-hop' === (string) $h1['nguon'], $h1 );
t( '   ghi chú có cảnh báo "hệ thống tự động"', false !== strpos( (string) $h1['ghi_chu'], 'Hệ thống tự động' ), $h1 );

/* Chạy lại lần hai trong cùng ngày -> không đụng gì nữa (đã có giờ ra). */
$kq1b = VHCC_TuDongRa::quet();
t( '🔴 chạy lại lần hai: KHÔNG đụng gì nữa (giờ ra đã có)', 0 === (int) $kq1b['ngay'] );

/* Hàng ĐÃ có giờ ra thật (tăng ca muộn) -> tuyệt đối không đụng. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CHINH, 'ngay' => '2026-09-11',
	'ma_nv' => 'TDR1', 'hau_to' => '', 'ho_ten' => 'Người Ca Ngày',
	'gio_vao_giay' => $g( '08:30:00' ), 'gio_ra_giay' => $g( '20:00:00' ), 'nguon' => 'online', 'chuan' => '08:30 20:00' ) );
vhcp_test_dat_gio( '2026-09-11 23:00:00' );
VHCC_TuDongRa::quet();
$h2 = $hang( $CHINH, '2026-09-11', 'TDR1' );
t( '🔴 giờ ra THẬT (tăng ca 20:00) không bị đụng vào', $h2 && '20:00:00' === VHCC_DB::hhmmss( $h2['gio_ra_giay'] )
	&& 'online' === (string) $h2['nguon'], $h2 );

/* ═══════════════════════════════ 2. CA ĐÊM — cơ sở PHỤ ═══════════════════════════════ */
vhcp_test_dat_gio( '2026-09-12 22:00:00' );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-12',
	'ma_nv' => 'TDR2', 'hau_to' => 'CD', 'ho_ten' => 'Người Ca Đêm',
	'gio_vao_giay' => $g( '22:00:00' ), 'gio_ra_giay' => null, 'nguon' => 'online', 'chuan' => '22:00' ) );

/* Hôm sau, TRƯỚC 08:00 -> chưa quét. */
vhcp_test_dat_gio( '2026-09-13 07:59:00' );
VHCC_TuDongRa::quet();
t( '🔴 trước 08:00 hôm sau: chưa đụng gì',
	null === $hang( $PHU, '2026-09-12', 'TDR2', 'CD' )['gio_ra_giay'] );

/* SAU 08:00 -> tự gán demDen (06:00, trải phẳng) làm giờ ra của NGÀY HÔM QUA. */
vhcp_test_dat_gio( '2026-09-13 08:05:00' );
$kq2 = VHCC_TuDongRa::quet();
t( '🔴 sau 08:00: tự gán "Ca đêm đến" (06:00) làm giờ ra của hàng -CD hôm qua',
	1 === (int) $kq2['dem'], $kq2 );
$h3 = $hang( $PHU, '2026-09-12', 'TDR2', 'CD' );
t( '   giờ ra đúng 06:00 (trên trục phẳng, > 24h)', $h3 && '06:00:00' === VHCC_DB::hhmmss( $h3['gio_ra_giay'] )
	&& (int) $h3['gio_ra_giay'] > 86400, $h3 );
t( '   KHÔNG đẻ hàng nào ở ngày hôm nay (13/09)', null === $hang( $PHU, '2026-09-13', 'TDR2', 'CD' ) );

/* ═══════════════════════════════ 3. TẮT tuDongRa Ở KHỐI VĂN PHÒNG ═══════════════════════════════ */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CHINH, 'ngay' => '2026-09-14',
	'ma_nv' => 'TDR1', 'hau_to' => '', 'ho_ten' => 'Người Ca Ngày',
	'gio_vao_giay' => $g( '08:30:00' ), 'gio_ra_giay' => null, 'nguon' => 'online', 'chuan' => '08:30' ) );
VHCC_Luong::dat_cfg_khoi( array( 'role' => 'Admin' ), 'Văn phòng', array( 'tuDongRa' => 0 ) );
vhcp_test_dat_gio( '2026-09-14 22:30:00' );
VHCC_TuDongRa::quet();
t( '🔴 tắt "tuDongRa" ở khối Văn phòng: KHÔNG tự gán gì nữa',
	null === $hang( $CHINH, '2026-09-14', 'TDR1' )['gio_ra_giay'] );
VHCC_Luong::dat_cfg_khoi( array( 'role' => 'Admin' ), 'Văn phòng', array( 'tuDongRa' => '' ) );   // bỏ khai riêng, về lại bật

/* ⚠️ 26/09/2026 — KHÔNG CÓ PHÉP THỬ CHO "Admin cố ý xoá trắng giờ ra, đừng viết đè lại".
   Đã DỰNG THỬ cảnh này (Admin `cham_bu` đánh dấu `o_gio='ra'` cho một ô đang trống) và PHÁ THỬ
   trực tiếp vào `VHCC_Nhan::ghi_gio()`: chốt `$de_len` ở đó (`class-vhcc-nhan.php:721-722`) chỉ
   mở sổ "đã đóng tay" khi Ô CŨ ĐANG CÓ SỐ (`null !== $vao`/`null !== $ra` của giá trị CŨ) — một
   ô đã bị xoá về NULL thì `$de_len` sai ngay từ đầu, cả khối "hỏi sổ" bị bỏ qua, và giá trị mới
   VẪN được ghi. Tức chú thích ngay trong hàm đó ("Ô có dấu tay mà đang trống là ô người ta cố ý
   XOÁ TRẮNG") mô tả một luật mà chốt bên ngoài nó chưa từng cho chạy tới — không phải lỗi của
   lượt quét này, mà là một khe hở đã có sẵn ở cửa ghi dùng chung, lộ ra vì đây là người gọi ĐẦU
   TIÊN thật sự đi vào đúng tình huống ấy (`bu`/`sua` không bao giờ tới nhánh này, xem chú thích
   dài tại đó). Sửa `$de_len` là sửa cửa ghi CHUNG cho mọi đường ghi — việc lớn hơn, ngoài phạm
   vi bản vá này (xem `/root/.claude/plans/unified-toasting-boot.md`) — nên KHÔNG viết một phép
   thử khẳng định điều hiện chưa đúng, chỉ ghi lại đây để người sau khỏi phải phá thử lại từ đầu. */

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — tự động gán giờ ra không đụng giờ thật, không đụng quyết định Admin.\n";
