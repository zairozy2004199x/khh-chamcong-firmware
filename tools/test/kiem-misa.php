<?php
/**
 * XUẤT MISA — CHỨNG TỪ BÁN HÀNG, COMBO BÓC TÁCH — CHẠY THẬT.
 *
 * Anh Thắng 25/09/2026: *"Giờ bắt đầu bóc tách và xuất dữ liệu ra misa"*, kèm ảnh chứng từ kế toán đã gõ tay cho
 * ngày 24/09/2026 của Tutu Train Aeon Tân An. Bài này dựng LẠI ĐÚNG NGÀY ẤY từ báo cáo FABi (8 dòng, 94 món,
 * 2.510.000) và đòi máy ra đúng 11 dòng kế toán đã gõ:
 *   · combo "… + BIM BIM" 4 × 80.000  → vé 4 × 60.000 = 240.000  + Bim bim nhỏ 4 × 20.000 = 80.000 (TK 6320 / 1567)
 *   · combo "… + THẠCH"   7 × 80.000  → vé 7 × 60.000 = 420.000  + Thạch trái cây 14 × 10.000 = 140.000
 *   · combo "… + NƯỚC SUỐI" 2 × 80.000 → vé 2 × 60.000 = 120.000 + Nước suối 2 × 20.000 = 40.000
 *   · nước suối bán lẻ 1 × 10.000 có giá vốn; vé lẻ không giá vốn; vé miễn phí 49 × 0 vẫn có dòng.
 *
 * Chốt:
 *   1. 🔴 Mã hàng = mã FABi: khai ở bảng MISA → cột Mã hàng dòng FABi → mã từng thấy → mã danh mục kho; thiếu → trống + cảnh báo.
 *   2. 🔴 Combo tách theo sale phụ đã khai (theo tên vé, hay theo nhóm) × công thức combo của sổ kho; tổng khớp từng đồng.
 *   3. Combo chưa khai phụ / chưa có công thức → một dòng vé nguyên giá + cảnh báo, máy không bịa.
 *   4. Tổng dòng ≠ doanh thu POS → cảnh báo. Ngày chưa chốt → cảnh báo đầu danh sách.
 *   5. Đã xuất: đánh dấu theo khoá ngày|quán (lỏng tên), lọc chua/da/tatca; bỏ dấu được.
 *   6. REST: GET/POST cần quyền nạp; POST viec lạ → 400; lưu cf/cs/mh xong trả bản xem tính lại.
 *   7. Cột đúng thứ tự lưới MISA; ngày dd/mm/yyyy; số chứng từ mang mã đơn vị.
 *
 * Chạy: php tools/test/kiem-misa.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
if ( ! function_exists( 'khh_dt_bang' ) ) {
	function khh_dt_bang() { global $wpdb; return $wpdb->prefix . 'khh_dt'; }
}
if ( ! function_exists( 'khh_dt_json' ) ) {
	function khh_dt_json( $raw, $mac_dinh ) { $v = json_decode( (string) $raw, true ); return is_array( $v ) ? $v : $mac_dinh; }
}
if ( ! function_exists( 'khh_dt_duoc_ghi' ) ) { function khh_dt_duoc_ghi() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_xem' ) ) { function khh_dt_duoc_xem() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_nap' ) ) { function khh_dt_duoc_nap() { return ! empty( $GLOBALS['VHCP_CO_QUYEN'] ); } }
if ( ! function_exists( 'khh_dt_duoc_quan_tri' ) ) { function khh_dt_duoc_quan_tri() { return ! empty( $GLOBALS['VHCP_CO_QUYEN'] ); } }
if ( ! function_exists( 'khh_dt_phien_nguoi' ) ) { function khh_dt_phien_nguoi() { return false; } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return isset( $GLOBALS['VHCP_META'][ $k ] ) ? $GLOBALS['VHCP_META'][ $k ] : ''; } }
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';
require_once $goc . '/kho.php';
require_once $goc . '/misa.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
$GLOBALS['VHCP_CO_QUYEN'] = true;
$GLOBALS['KHH_DT_MISA_KHONG_NHO'] = true;   // mã FABi đọc lại mỗi lượt (bài này nạp thêm dòng giữa chừng)
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang() );
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_bc() );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', doanh_thu REAL DEFAULT 0, thanh_tien REAL DEFAULT 0,
	chiet_khau REAL DEFAULT 0, so_hd INTEGER DEFAULT 0, so_mon REAL DEFAULT 0, so_ve REAL DEFAULT 0,
	pttt TEXT DEFAULT '[]', mon TEXT NOT NULL DEFAULT '[]', UNIQUE(ngay,cua_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_bc() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', tien_mat_dem REAL DEFAULT 0, tien_nop REAL DEFAULT 0,
	so_bill_huy INTEGER DEFAULT 0, tien_bill_huy REAL DEFAULT 0, tong_chuyen INTEGER DEFAULT 0, tong_khach INTEGER DEFAULT 0,
	ve_giay INTEGER DEFAULT 0, ghi_chu TEXT DEFAULT '', mon_thuc TEXT NULL, nguoi TEXT DEFAULT '', nguoi_id INTEGER DEFAULT 0,
	chot INTEGER DEFAULT 0, lich_su TEXT DEFAULT '[]', sua_luc TEXT NULL, UNIQUE(ngay,cua_hang) )" );
foreach ( array( KHH_DT_MISA_CF, KHH_DT_MISA_CS, KHH_DT_MISA_MH, KHH_DT_MISA_DA, 'khh_dt_kho_combo_ls', 'khh_dt_kho_combo', 'khh_dt_ve_phu', 'khh_dt_nhom_phu', 'khh_dt_nhom_ve', 'khh_dt_kho_ma', 'khh_dt_kho_mat_hang' ) as $k ) {
	delete_option( $k );
}

/* Tên quán trong FABi có HAI dấu cách — y như thật. */
$TA = 'Tutu Train -  Aeon Tân An';
$C_THACH = 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH';
$C_BIM   = 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM';
$C_NUOC  = 'COMBO TUTU TRAIN:  TRẺ EM + NGƯỜI LỚN + NƯỚC SUỐI';   // dấu cách thừa, khai combo không có
$MON = array(
	array( 'n' => 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN', 'g' => 'VÉ LẺ.',    'l' => 'Vé',      'm' => 'MNKVCTT004', 'q' => 16, 'r' => 800000 ),
	array( 'n' => $C_THACH,                      'g' => 'VÉ COMBO.', 'l' => 'Vé',      'm' => 'MNKVCTT007', 'q' => 7,  'r' => 560000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ TRẺ EM',    'g' => 'VÉ LẺ.',    'l' => 'Vé',      'm' => 'MNKVCTT002', 'q' => 13, 'r' => 520000 ),
	array( 'n' => $C_BIM,                        'g' => 'VÉ COMBO.', 'l' => 'Vé',      'm' => 'MNKVCTT006', 'q' => 4,  'r' => 320000 ),
	array( 'n' => $C_NUOC,                       'g' => 'VÉ COMBO.', 'l' => 'Vé',      'm' => 'MNKVCTT005', 'q' => 2,  'r' => 160000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ TRẺ EM X2', 'g' => 'VÉ LẺ.',    'l' => 'Vé',      'm' => 'MNKVCTT003', 'q' => 2,  'r' => 140000 ),
	array( 'n' => 'NƯỚC SUỐI DANASI',            'g' => 'ĐÓNG SẴN',  'l' => 'Đồ uống', 'm' => 'MNKVCDS023', 'q' => 1,  'r' => 10000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ MIỄN PHÍ',  'g' => 'VÉ MIỄN PHÍ.', 'l' => 'Vé',   'm' => 'MNKVCTT001', 'q' => 49, 'r' => 0 ),
);
function pos_ngay( $ngay, $cs, $mon, $dt = 2510000 ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'INSERT OR REPLACE INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
		$ngay, $cs, $dt, 40, 94, '[]', wp_json_encode( $mon ) ) );
}
pos_ngay( '2026-09-24', $TA, $MON );

/* Công thức combo (sổ kho) + sale phụ 20.000 mỗi vé combo (theo nhóm). */
khh_dt_kho_combo_dat( $C_THACH, array( 'Thạch trái cây' => 2 ), '2026-09-01' );
khh_dt_kho_combo_dat( $C_BIM, array( 'Bim bim nhỏ' => 1 ), '2026-09-01' );
khh_dt_kho_combo_dat( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + NƯỚC SUỐI', array( 'NƯỚC SUỐI DANASI' => 1 ), '2026-09-01' );
khh_dt_nhom_phu_dat( array( 'VÉ COMBO.' => '20000' ) );

/* ── chưa khai gì cho MISA: vẫn ra dòng, nhưng cảnh báo thiếu mã đơn vị và thiếu mã hàng thành phần ── */
$n0 = khh_dt_misa_ngay( '2026-09-24', $TA );
phep( 'ngày không có số POS -> null', null === khh_dt_misa_ngay( '2026-09-25', $TA ) );
phep( '🔴 8 dòng FABi -> 11 dòng MISA (3 combo tách đôi)', 11 === $n0['so_dong'] );
phep( '🔴 tổng 11 dòng = 2.510.000 = doanh thu POS, không cảnh báo lệch', 2510000.0 === (float) $n0['tong'] && ! preg_grep( '/≠ doanh thu/u', $n0['canh'] ) );
phep( 'chưa khai mã đơn vị -> cảnh báo', (bool) preg_grep( '/chưa khai Mã đơn vị/u', $n0['canh'] ) );
phep( '🔴 thành phần combo chưa có mã hàng (Thạch, Bim bim) -> cảnh báo nêu tên, ô mã trống', (bool) preg_grep( '/Chưa có Mã hàng cho "Thạch trái cây"/u', $n0['canh'] ) && (bool) preg_grep( '/"Bim bim nhỏ"/u', $n0['canh'] ) );
phep( 'nước suối thành phần có mã vì FABi từng bán lẻ (MNKVCDS023) -> không cảnh báo', ! preg_grep( '/NƯỚC SUỐI DANASI/u', $n0['canh'] ) );
phep( 'số chứng từ chưa có mã đơn vị -> tiền tố + ngày + số thứ tự quán', 'BH20260924-01' === $n0['so_ct'] );

$tim = function ( $n, $ten, $tu_combo = null ) {
	foreach ( $n['dong'] as $d ) {
		if ( $d['ten_fabi'] === $ten && ( null === $tu_combo || $d['tu_combo'] === $tu_combo ) ) {
			return $d;
		}
	}
	return null;
};
$d = $tim( $n0, $C_BIM );
phep( '🔴 combo BIM BIM: dòng vé 4 × 60.000 = 240.000, mã MNKVCTT006, không giá vốn, ĐVT Vé', $d && 4.0 === (float) $d['sl'] && 60000.0 === (float) $d['don_gia'] && 240000.0 === (float) $d['tien'] && 'MNKVCTT006' === $d['ma'] && '' === $d['tk_gv'] && '' === $d['tk_kho'] && 'Vé' === $d['dvt'] && $d['la_ve'] );
$d = $tim( $n0, 'Bim bim nhỏ', $C_BIM );
phep( '🔴 … + dòng hàng Bim bim nhỏ 4 × 20.000 = 80.000, TK giá vốn 6320, TK kho 1567, ĐVT Cái', $d && 4.0 === (float) $d['sl'] && 20000.0 === (float) $d['don_gia'] && 80000.0 === (float) $d['tien'] && '6320' === $d['tk_gv'] && '1567' === $d['tk_kho'] && 'Cái' === $d['dvt'] && ! $d['la_ve'] );
$d = $tim( $n0, 'Thạch trái cây', $C_THACH );
phep( '🔴 combo THẠCH (2 thạch/combo): 14 × 10.000 = 140.000 — phụ 20.000 chia đều theo số cái', $d && 14.0 === (float) $d['sl'] && 10000.0 === (float) $d['don_gia'] && 140000.0 === (float) $d['tien'] );
$d = $tim( $n0, $C_THACH );
phep( 'combo THẠCH: dòng vé 7 × 60.000 = 420.000', $d && 420000.0 === (float) $d['tien'] && 60000.0 === (float) $d['don_gia'] );
$d = $tim( $n0, 'NƯỚC SUỐI DANASI', $C_NUOC );
phep( '🔴 combo NƯỚC SUỐI (tên FABi thừa dấu cách vẫn khớp công thức): nước 2 × 20.000 = 40.000, mã MNKVCDS023 lấy từ dòng bán lẻ', $d && 2.0 === (float) $d['sl'] && 40000.0 === (float) $d['tien'] && 'MNKVCDS023' === $d['ma'] );
$d = $tim( $n0, 'NƯỚC SUỐI DANASI', '' );
phep( 'nước suối bán lẻ 1 × 10.000 là dòng HÀNG có giá vốn', $d && 10000.0 === (float) $d['tien'] && '6320' === $d['tk_gv'] && ! $d['la_ve'] );
$d = $tim( $n0, 'VÉ TUTU TRAIN: VÉ MIỄN PHÍ' );
phep( 'vé miễn phí 49 × 0 vẫn có dòng, mã MNKVCTT001', $d && 49.0 === (float) $d['sl'] && 0.0 === (float) $d['tien'] && 'MNKVCTT001' === $d['ma'] );
phep( 'mọi dòng TK doanh thu 5110, TK công nợ 131, chi nhánh Khu vui chơi, ngày 24/09/2026', count( array_filter( $n0['dong'], function ( $d ) { return '5110' === $d['tk_dt'] && '131' === $d['tk_no'] && 'Khu vui chơi' === $d['chi_nhanh'] && '24/09/2026' === $d['ngay']; } ) ) === 11 );
$thu_tu = array_map( function ( $d ) { return $d['ten_fabi']; }, $n0['dong'] );
phep( 'dòng hàng của combo đứng NGAY SAU dòng vé của combo ấy', array_search( 'Bim bim nhỏ', $thu_tu, true ) === array_search( $C_BIM, $thu_tu, true ) + 1 && array_search( 'Thạch trái cây', $thu_tu, true ) === array_search( $C_THACH, $thu_tu, true ) + 1 );

/* ── khai cấu hình: mã đơn vị, mã hàng + tên MISA, đơn giá trong combo ── */
khh_dt_misa_cs_dat( array( 'Tutu Train - Aeon Tân An' => array( 'ma_dv' => 'TTAMTA', 'ten' => 'Tutu Train Aeon Tân An', 'ma_kh' => 'KH TT' ) ) );   // tên gõ một dấu cách
$cs = khh_dt_misa_cs();
phep( '🔴 khai quán theo tên một dấu cách -> lưu dưới tên NGUYÊN VĂN FABi (hai dấu cách), mã khách bỏ dấu cách', isset( $cs[ $TA ] ) && 'TTAMTA' === $cs[ $TA ]['ma_dv'] && 'KHTT' === $cs[ $TA ]['ma_kh'] );
khh_dt_misa_mh_dat( array(
	'Thạch trái cây' => array( 'ma' => 'MNKVCDS036', 'ten' => 'Thạch trái cây', 'dvt' => 'Cái' ),
	'BIM BIM NHỎ'    => array( 'ma' => 'mnkvcds007 ', 'ten' => 'Bim bim nhỏ', 'dvt' => 'Gói' ),   // hoa thường, dấu cách thừa
	'Nước suối Danasi' => array( 'ten' => 'Nước suối Danasi', 'dvt' => 'Chai' ),
	$C_BIM => array( 'ten' => 'VÉ TRẺ EM + NGƯỜI LỚN + BIM BIM' ),
) );
$mh = khh_dt_misa_mh();
phep( 'mã hàng giữ nguyên chữ, bỏ dấu cách; tên khai hoa thường tra lỏng ra cùng một món', 'mnkvcds007' === khh_dt_misa_mh_cua( 'Bim bim nhỏ' )['ma'] && 'Gói' === khh_dt_misa_mh_cua( 'Bim bim nhỏ' )['dvt'] );
$n1 = khh_dt_misa_ngay( '2026-09-24', $TA );
phep( '🔴 khai xong: không còn cảnh báo nào', array() === $n1['canh'] );
phep( 'số chứng từ mang mã đơn vị: BH20260924-TTAMTA; diễn giải dùng tên MISA', 'BH20260924-TTAMTA' === $n1['so_ct'] && 'Bán hàng ngày 24/09/2026 - Tutu Train Aeon Tân An' === $n1['dien_giai'] );
$d = $tim( $n1, 'Bim bim nhỏ', $C_BIM );
phep( 'dòng Bim bim: mã MNKVCDS007 (đã khai), tên MISA, ĐVT Gói, đơn vị TTAMTA, mã khách KHTT', $d && 'mnkvcds007' === $d['ma'] && 'Bim bim nhỏ' === $d['ten'] && 'Gói' === $d['dvt'] && 'TTAMTA' === $d['ma_dv'] && 'KHTT' === $d['ma_kh'] );
$d = $tim( $n1, 'NƯỚC SUỐI DANASI', '' );
phep( 'nước suối lẻ: mã FABi MNKVCDS023 (không khai mã thì lấy mã FABi), ĐVT Chai đã khai', $d && 'MNKVCDS023' === $d['ma'] && 'Chai' === $d['dvt'] );

/* đơn giá trong combo khai riêng: thạch 8.000/cái -> 14 × 8.000 = 112.000, vé gánh phần còn lại 448.000 */
khh_dt_misa_mh_dat( array( 'Thạch trái cây' => array( 'ma' => 'MNKVCDS036', 'ten' => 'Thạch trái cây', 'dvt' => 'Cái', 'gia' => '8.000' ) ) );
$n2 = khh_dt_misa_ngay( '2026-09-24', $TA );
$d  = $tim( $n2, 'Thạch trái cây', $C_THACH );
$v  = $tim( $n2, $C_THACH );
phep( '🔴 khai đơn giá trong combo 8.000 -> thạch 14 × 8.000 = 112.000, vé 560.000 − 112.000 = 448.000, tổng vẫn 2.510.000', $d && 112000.0 === (float) $d['tien'] && 8000.0 === (float) $d['don_gia'] && $v && 448000.0 === (float) $v['tien'] && 2510000.0 === (float) $n2['tong'] );
khh_dt_misa_mh_dat( array( 'Thạch trái cây' => array( 'ma' => 'MNKVCDS036', 'ten' => 'Thạch trái cây', 'dvt' => 'Cái', 'gia' => '' ) ) );

/* phụ theo TÊN VÉ đè phụ theo nhóm: combo thạch phụ 30.000 -> thạch 15.000/cái, vé 50.000 */
khh_dt_ve_phu_dat( array( $C_THACH => 30000 ) );
$n3 = khh_dt_misa_ngay( '2026-09-24', $TA );
$d  = $tim( $n3, 'Thạch trái cây', $C_THACH );
$v  = $tim( $n3, $C_THACH );
phep( 'phụ khai theo tên vé (30.000) đè phụ nhóm (20.000): thạch 14 × 15.000, vé 7 × 50.000', $d && 15000.0 === (float) $d['don_gia'] && $v && 50000.0 === (float) $v['don_gia'] && 2510000.0 === (float) $n3['tong'] );
khh_dt_ve_phu_dat( array( $C_THACH => 0 ) );
$n4 = khh_dt_misa_ngay( '2026-09-24', $TA );
phep( '🔴 khai phụ = 0 cho combo ấy -> KHÔNG tách, một dòng vé 7 × 80.000 + cảnh báo "chưa khai sale phụ"; tổng vẫn khớp', null === $tim( $n4, 'Thạch trái cây', $C_THACH ) && 80000.0 === (float) $tim( $n4, $C_THACH )['don_gia'] && (bool) preg_grep( '/chưa khai sale phụ/u', $n4['canh'] ) && 2510000.0 === (float) $n4['tong'] && 10 === $n4['so_dong'] );
delete_option( 'khh_dt_ve_phu' );

/* phụ ≥ đơn giá: không tách, cảnh báo */
khh_dt_nhom_phu_dat( array( 'VÉ COMBO.' => '80000' ) );
$n5 = khh_dt_misa_ngay( '2026-09-24', $TA );
phep( 'phụ ≥ đơn giá -> không tách, cảnh báo nêu tên combo', 8 === $n5['so_dong'] && (bool) preg_grep( '/≥ đơn giá/u', $n5['canh'] ) );
khh_dt_nhom_phu_dat( array( 'VÉ COMBO.' => '20000' ) );

/* combo có công thức nhưng thành phần là hai món khác nhau: chia theo số cái, dòng cuối gánh lẻ */
khh_dt_kho_combo_dat( $C_BIM, array( 'Bim bim nhỏ' => 1, 'Thạch trái cây' => 2 ), '2026-09-01' );
$n6 = khh_dt_misa_ngay( '2026-09-24', $TA );
$b  = $tim( $n6, 'Bim bim nhỏ', $C_BIM );
$t  = $tim( $n6, 'Thạch trái cây', $C_BIM );
phep( '🔴 phụ 20.000 × 4 vé = 80.000 chia 3 cái/combo: bim 4 × 6.667 = 26.667, thạch gánh lẻ 53.333; cộng đúng 80.000', $b && $t && 26667.0 === (float) $b['tien'] && 53333.0 === (float) $t['tien'] && 240000.0 === (float) $tim( $n6, $C_BIM )['tien'] && 2510000.0 === (float) $n6['tong'] );
khh_dt_kho_combo_dat( $C_BIM, array( 'Bim bim nhỏ' => 1 ), '2026-09-01' );
/* ba thành phần mỗi thứ một cái, phụ 20.000 × 2 vé = 40.000 chia 3 KHÔNG tròn: 13.333 + 13.333 + 13.334 */
khh_dt_kho_combo_dat( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + NƯỚC SUỐI', array( 'NƯỚC SUỐI DANASI' => 1, 'Bim bim nhỏ' => 1, 'Thạch trái cây' => 1 ), '2026-09-01' );
$n6b = khh_dt_misa_ngay( '2026-09-24', $TA );
$ba  = array_values( array_filter( $n6b['dong'], function ( $d ) use ( $C_NUOC ) { return $d['tu_combo'] === $C_NUOC; } ) );
phep( '🔴 40.000 chia 3 không tròn: hai dòng đầu 13.333, dòng cuối gánh lẻ 13.334, cộng đúng 40.000, tổng ngày vẫn 2.510.000', 3 === count( $ba ) && 13333.0 === (float) $ba[0]['tien'] && 13333.0 === (float) $ba[1]['tien'] && 13334.0 === (float) $ba[2]['tien'] && 2510000.0 === (float) $n6b['tong'] );
khh_dt_kho_combo_dat( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + NƯỚC SUỐI', array( 'NƯỚC SUỐI DANASI' => 1 ), '2026-09-01' );

/* tổng dòng ≠ doanh thu POS (chiết khấu hoá đơn) -> cảnh báo */
pos_ngay( '2026-09-23', $TA, $MON, 2400000 );
$n7 = khh_dt_misa_ngay( '2026-09-23', $TA );
phep( 'tổng dòng 2.510.000 ≠ doanh thu POS 2.400.000 -> cảnh báo lệch', (bool) preg_grep( '/≠ doanh thu POS 2\.400\.000/u', $n7['canh'] ) );

/* ── cả kỳ, đã xuất, cột ── */
$GV = 'TuTu Train - Lotte Gò Vấp';
pos_ngay( '2026-09-24', $GV, array( array( 'n' => 'VÉ TUTU TRAIN: VÉ TRẺ EM', 'g' => 'VÉ LẺ.', 'l' => 'Vé', 'm' => 'MNKVCTT002', 'q' => 3, 'r' => 120000 ) ), 120000 );
$wpdb->query( $wpdb->prepare( 'INSERT OR REPLACE INTO ' . khh_dt_bang_bc() . ' (ngay,cua_hang,chot) VALUES (%s,%s,%d)', '2026-09-24', $TA, 1 ) );
$x = khh_dt_misa_xuat( '2026-09-01', '2026-09-30' );
phep( 'cả kỳ: 3 chứng từ (TA 23, TA 24, GV 24), theo ngày rồi quán', 3 === count( $x['chung_tu'] ) && '2026-09-23' === $x['chung_tu'][0]['ngay'] && $GV === $x['chung_tu'][1]['cua_hang'] && $TA === $x['chung_tu'][2]['cua_hang'] );
phep( 'số dòng = 11 + 1 + 11 = 23; tổng = 2.510.000 × 2 + 120.000', 23 === count( $x['rows'] ) && 5140000.0 === (float) $x['tong'] );
phep( '🔴 17 cột đúng thứ tự lưới MISA', array( 'Ngày hạch toán', 'Ngày chứng từ', 'Số chứng từ', 'Mã khách hàng', 'Diễn giải', 'Mã hàng', 'Tên hàng', 'TK doanh thu', 'TK công nợ', 'TK giá vốn', 'TK kho', 'ĐVT', 'Số lượng', 'Đơn giá', 'Thành tiền', 'Đơn vị', 'Chi nhánh' ) === $x['cols'] );
$r0 = $x['rows'][0];
phep( 'dòng đầu: ngày dd/mm/yyyy ở cả hai cột ngày, số chứng từ, mã, số là số', '23/09/2026' === $r0[0] && '23/09/2026' === $r0[1] && 'BH20260923-TTAMTA' === $r0[2] && 'MNKVCTT004' === $r0[5] && 16.0 === (float) $r0[12] && 800000.0 === (float) $r0[14] && 'TTAMTA' === $r0[15] );
phep( 'ngày 24 TA đã chốt, 23 TA và GV chưa -> cảnh báo đầu tiên "2 ngày cơ sở chưa Lưu và chốt"', $x['chung_tu'][2]['chot'] && ! $x['chung_tu'][0]['chot'] && 0 === strpos( $x['warn'][0], '2 ngày cơ sở chưa' ) );
phep( 'cảnh báo trùng nhiều ngày gom một dòng kèm số ngày (GV chưa khai mã đơn vị chỉ 1 ngày)', (bool) preg_grep( '/Lotte Gò Vấp" chưa khai Mã đơn vị MISA\.$/u', $x['warn'] ) );
phep( 'tên tệp theo kỳ', 'MISA_BanHang_20260901-20260930' === $x['ten_tep'] );
phep( 'lọc một quán', 1 === count( khh_dt_misa_xuat( '2026-09-01', '2026-09-30', $GV )['chung_tu'] ) );

/* đã xuất */
$n = khh_dt_misa_danh_dau( array( '2026-09-24|' . mb_strtolower( 'Tutu Train - Aeon Tân An' ), 'rác', '2026-09-24|' . khh_dt_kho_long( $GV ) ) );
phep( '🔴 đánh dấu 2 khoá hợp lệ (tên một dấu cách vẫn trúng quán hai dấu cách), bỏ khoá rác', 2 === $n );
$x = khh_dt_misa_xuat( '2026-09-01', '2026-09-30', '', 'chua' );
phep( '"chưa xuất" chỉ còn TA 23/09', 1 === count( $x['chung_tu'] ) && '2026-09-23' === $x['chung_tu'][0]['ngay'] );
$x = khh_dt_misa_xuat( '2026-09-01', '2026-09-30', '', 'da' );
phep( '"đã xuất" = 2, mang lúc đánh dấu', 2 === count( $x['chung_tu'] ) && ! empty( $x['chung_tu'][0]['da_xuat']['luc'] ) );
phep( '"tất cả" = 3', 3 === count( khh_dt_misa_xuat( '2026-09-01', '2026-09-30', '', 'tatca' )['chung_tu'] ) );
phep( 'đánh dấu lại khoá đã có -> 0 đổi; bỏ dấu -> 1', 0 === khh_dt_misa_danh_dau( array( khh_dt_misa_khoa( '2026-09-24', $GV ) ) ) && 1 === khh_dt_misa_danh_dau( array( khh_dt_misa_khoa( '2026-09-24', $GV ) ), false ) );

/* mặt hàng thấy trong kỳ */
$mh = khh_dt_misa_mh_thay( '2026-09-01', '2026-09-30' );
$ten_ds = array_map( function ( $m ) { return $m['ten']; }, $mh );
phep( 'bảng mặt hàng gồm món FABi + thành phần combo (Thạch, Bim bim) — mỗi tên một dòng', in_array( 'Thạch trái cây', $ten_ds, true ) && in_array( 'Bim bim nhỏ', $ten_ds, true ) && count( $ten_ds ) === count( array_unique( array_map( 'khh_dt_kho_long', $ten_ds ) ) ) );
$bim = null; $ve_nl = null;
foreach ( $mh as $m ) { if ( 'Bim bim nhỏ' === $m['ten'] ) { $bim = $m; } if ( 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' === $m['ten'] ) { $ve_nl = $m; } }
phep( 'thành phần combo: là hàng, mang cấu hình đã khai; vé lẻ: là vé, mã FABi thấy, tiền gom cả kỳ', $bim && ! $bim['la_ve'] && 'mnkvcds007' === $bim['cf']['ma'] && $ve_nl && $ve_nl['la_ve'] && 'MNKVCTT004' === $ve_nl['ma_fabi'] && 1600000.0 === (float) $ve_nl['tien'] );
phep( 'vé xếp trước hàng, nhiều tiền trước', $mh[0]['la_ve'] && 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' === $mh[0]['ten'] );
$cb = null;
foreach ( $mh as $m ) { if ( $m['ten'] === $C_BIM ) { $cb = $m; } }
phep( 'combo được gắn cờ combo', $cb && $cb['combo'] );

/* ── cấu hình tài khoản ── */
$cf = khh_dt_misa_cf_dat( array( 'tk_dt' => '5111 ', 'tk_gv' => '', 'chi_nhanh' => 'Khu vui chơi <b>x</b>', 'tien_to' => 'BH-' ) );
phep( 'cf: mã tài khoản bỏ dấu cách; ô trống lùi về mặc định; chi nhánh bỏ thẻ; tiền tố giữ gạch', '5111' === $cf['tk_dt'] && '6320' === $cf['tk_gv'] && 'Khu vui chơi x' === $cf['chi_nhanh'] && 'BH-' === $cf['tien_to'] );
phep( 'đổi cf là dòng đổi theo (TK doanh thu 5111, số chứng từ BH-…)', '5111' === khh_dt_misa_ngay( '2026-09-24', $TA )['dong'][0]['tk_dt'] && 'BH-20260924-TTAMTA' === khh_dt_misa_ngay( '2026-09-24', $TA )['so_ct'] );
khh_dt_misa_cf_dat( array( 'tk_dt' => '5110', 'tien_to' => 'BH', 'chi_nhanh' => 'Khu vui chơi' ) );

/* ── REST ── */
$req = new WP_REST_Request( array( 'tu' => '2026-09-01', 'den' => '2026-09-30', 'cua_hang' => 'Tutu Train - Aeon Tân An', 'tt' => 'tatca' ) );
$r = khh_dt_rest_misa_xem( $req );
phep( 'GET: quán gõ một dấu cách -> tra ra tên nguyên văn; 2 chứng từ TA; kèm cf, cs (mọi quán), mh', $TA === $r['cua_hang'] && 2 === count( $r['chung_tu'] ) && '5110' === $r['cf']['tk_dt'] && 2 === count( $r['cs'] ) && count( $r['mh'] ) >= 9 );
$cs_gv = null;
foreach ( $r['cs'] as $c ) { if ( $c['cua_hang'] === $GV ) { $cs_gv = $c; } }
phep( 'cs của quán chưa khai: ba ô trống', $cs_gv && '' === $cs_gv['ma_dv'] && '' === $cs_gv['ten'] );
$r = khh_dt_rest_misa_xem( new WP_REST_Request( array( 'den' => '2026-09-24' ) ) );
phep( 'thiếu "tu" -> đầu tháng của "den"; tt lạ -> chua', '2026-09-01' === $r['tu'] && 'chua' === $r['tt'] );
$r = khh_dt_rest_misa_xem( new WP_REST_Request( array( 'tu' => '2026-09-30', 'den' => '2026-09-01' ) ) );
phep( 'tu > den -> đảo lại', '2026-09-01' === $r['tu'] && '2026-09-30' === $r['den'] );
$r = khh_dt_rest_misa_luu( new WP_REST_Request( array( 'viec' => 'xxx' ) ) );
phep( 'POST viec lạ -> 400', is_wp_error( $r ) && 400 === $r->get_error_data()['status'] );
$r = khh_dt_rest_misa_luu( new WP_REST_Request( array( 'viec' => 'cs', 'cs' => '{hỏng' ) ) );
phep( 'POST cs JSON hỏng -> 400', is_wp_error( $r ) );
$r = khh_dt_rest_misa_luu( new WP_REST_Request( array( 'viec' => 'cs', 'cs' => wp_json_encode( array( $GV => array( 'ma_dv' => 'TTLGV' ) ) ), 'tu' => '2026-09-01', 'den' => '2026-09-30', 'tt' => 'tatca' ) ) );
phep( '🔴 POST cs: lưu xong trả bản xem CÙNG KỲ đã tính lại — GV có mã, hết cảnh báo mã đơn vị', ! is_wp_error( $r ) && 'TTLGV' === khh_dt_misa_cs_cua( $GV )['ma_dv'] && ! preg_grep( '/chưa khai Mã đơn vị/u', $r['warn'] ) );
$r = khh_dt_rest_misa_luu( new WP_REST_Request( array( 'viec' => 'mh', 'mh' => wp_json_encode( array( 'Thạch trái cây' => array( 'ma' => 'MNKVCDS036', 'ten' => 'Thạch trái cây', 'dvt' => 'Cái', 'gia' => '' ) ) ), 'tu' => '2026-09-01', 'den' => '2026-09-30' ) ) );
phep( 'POST mh: lưu và trả bản xem', ! is_wp_error( $r ) && isset( $r['mh'] ) );
$r = khh_dt_rest_misa_luu( new WP_REST_Request( array( 'viec' => 'cf', 'cf' => wp_json_encode( array( 'tk_kho' => '156' ) ), 'tu' => '2026-09-01', 'den' => '2026-09-30' ) ) );
phep( 'POST cf: lưu và trả bản xem', ! is_wp_error( $r ) && '156' === $r['cf']['tk_kho'] );
khh_dt_misa_cf_dat( array( 'tk_kho' => '1567' ) );
$r = khh_dt_rest_misa_luu( new WP_REST_Request( array( 'viec' => 'da_xuat', 'khoa' => wp_json_encode( array( khh_dt_misa_khoa( '2026-09-23', $TA ) ) ), 'tu' => '2026-09-01', 'den' => '2026-09-30', 'tt' => 'chua' ) ) );
phep( 'POST da_xuat: đánh dấu rồi trả bản "chưa xuất" không còn TA 23 (chỉ còn GV 24); da_doi = 1', ! is_wp_error( $r ) && 1 === $r['da_doi'] && 1 === count( $r['chung_tu'] ) && $GV === $r['chung_tu'][0]['cua_hang'] );
$r = khh_dt_rest_misa_luu( new WP_REST_Request( array( 'viec' => 'bo_xuat', 'khoa' => wp_json_encode( array( khh_dt_misa_khoa( '2026-09-23', $TA ) ) ), 'tu' => '2026-09-01', 'den' => '2026-09-30', 'tt' => 'chua' ) ) );
phep( 'POST bo_xuat: TA 23 quay lại "chưa xuất" (2 chứng từ)', ! is_wp_error( $r ) && 1 === $r['da_doi'] && 2 === count( $r['chung_tu'] ) );
$r = khh_dt_rest_misa_luu( new WP_REST_Request( array( 'viec' => 'da_xuat' ) ) );
phep( 'POST da_xuat thiếu khoá -> 400', is_wp_error( $r ) );

/* quyền: GET/POST cùng cửa quyền nạp file */
$GLOBALS['VHCP_CO_QUYEN'] = false;
phep( '🔴 không có quyền nạp -> đường /misa chối (GET lẫn POST cùng permission_callback khh_dt_duoc_nap)', true !== khh_dt_duoc_nap() );
$src = file_get_contents( $goc . '/misa.php' );
$src_sach = preg_replace( '~/\*.*?\*/~s', '', $src );
$src_sach = preg_replace( '~//[^\n]*~', '', $src_sach );
phep( 'route /misa: hai phương thức đều permission_callback khh_dt_duoc_nap', 2 === substr_count( $src_sach, "'permission_callback' => 'khh_dt_duoc_nap'" ) && false !== strpos( $src_sach, "'/misa'" ) );
phep( 'mã plugin không dựng WP_REST_Request bằng mảng', false === strpos( $src_sach, 'new WP_REST_Request(' ) );

echo $hong ? '✗ HỎNG ' . count( $hong ) . ' / ' . ( $dat + count( $hong ) ) . " phép:\n  · " . implode( "\n  · ", $hong ) . "\n"
	: '✓ SẠCH — ' . $dat . " phép: chứng từ bán hàng MISA, combo bóc tách, mã hàng FABi, đã xuất, REST.\n";
exit( $hong ? 1 : 0 );
