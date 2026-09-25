<?php
/**
 * PHIẾU NHẬP HÀNG → SỔ KHO — CHẠY THẬT.
 *
 * Anh Thắng 25/09/2026: *"Tạo phiếu nhập hàng, khi có phiếu nhập hàng nhập vào hoặc đẩy lên nó sẽ đẩy vào
 * dữ liệu kho hàng"*.
 *
 * Chốt:
 *   1. Số phiếu tự đánh NH<yyyymmdd>-NN, tăng dần trong ngày; số gõ tay trùng thì chối.
 *   2. 🔴 Lưu phiếu là ô Nhập của sổ kho (ngày, cơ sở, mặt hàng) = tổng các phiếu ngày ấy; hai phiếu cộng dồn.
 *   3. 🔴 Đồng bộ KHÔNG đụng số đếm / hàng huỷ / tồn đầu đặt lại / ghi chú của dòng kho.
 *   4. Xoá phiếu -> tính lại (về 0 nếu hết phiếu). Mặt hàng mới trên phiếu vào danh mục kho.
 *   5. Dòng sạch: bỏ tên trống / số lượng ≤ 0, cùng tên (khác dấu cách, hoa thường) cộng dồn.
 *   6. REST: người không phụ trách quán -> 403; xoá chỉ văn phòng; đẩy lên bằng JSON đi cùng cổng.
 *   7. Sổ kho ghi qua khh_dt_kho_ghi -> sổ ghi động có vết; tab Kho biết mặt hàng nào "theo phiếu".
 *
 * Chạy: php tools/test/kiem-phieu-nhap.php
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
if ( ! function_exists( 'khh_dt_phien_nguoi' ) ) { function khh_dt_phien_nguoi() { return false; } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return isset( $GLOBALS['VHCP_META'][ $k ] ) ? $GLOBALS['VHCP_META'][ $k ] : ''; } }
if ( ! function_exists( 'wp_list_pluck' ) ) { function wp_list_pluck( $ds, $k ) { return array_map( function ( $d ) use ( $k ) { return is_array( $d ) ? $d[ $k ] : $d->$k; }, (array) $ds ); } }
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';
require_once $goc . '/kho.php';
require_once $goc . '/phieu-nhap.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
foreach ( array( khh_dt_bang(), khh_dt_bang_bc(), khh_dt_bang_kho(), khh_dt_bang_kho_su(), khh_dt_bang_pn() ) as $b ) {
	$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . $b );
}
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '',
	doanh_thu REAL DEFAULT 0, thanh_tien REAL DEFAULT 0, chiet_khau REAL DEFAULT 0, so_hd INTEGER DEFAULT 0, so_mon REAL DEFAULT 0, so_ve REAL DEFAULT 0,
	pttt TEXT DEFAULT '[]', mon TEXT NOT NULL DEFAULT '[]', UNIQUE(ngay,cua_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_bc() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '',
	tien_mat_dem REAL DEFAULT 0, tien_nop REAL DEFAULT 0, so_bill_huy INTEGER DEFAULT 0, tien_bill_huy REAL DEFAULT 0, tong_chuyen INTEGER DEFAULT 0,
	tong_khach INTEGER DEFAULT 0, ve_giay INTEGER DEFAULT 0, ghi_chu TEXT DEFAULT '', mon_thuc TEXT NULL, nguoi TEXT DEFAULT '', nguoi_id INTEGER DEFAULT 0,
	chot INTEGER DEFAULT 0, lich_su TEXT DEFAULT '[]', sua_luc TEXT NULL, UNIQUE(ngay,cua_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_kho() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT NOT NULL, co_so TEXT NOT NULL DEFAULT '',
	mat_hang TEXT NOT NULL DEFAULT '', nhap REAL DEFAULT 0, ban_khai REAL NULL, combo_tay REAL DEFAULT 0, dem REAL NULL, dat_dau REAL NULL, huy REAL DEFAULT 0,
	ghi_chu TEXT DEFAULT '', nguoi TEXT DEFAULT '', luc TEXT NULL, UNIQUE(ngay,co_so,mat_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_kho_su() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT NOT NULL, co_so TEXT NOT NULL DEFAULT '',
	mat_hang TEXT NOT NULL DEFAULT '', nhap REAL NOT NULL DEFAULT 0, ban_khai REAL NULL, combo_tay REAL NOT NULL DEFAULT 0, dem REAL NULL, dat_dau REAL NULL,
	huy REAL NOT NULL DEFAULT 0, ghi_chu TEXT DEFAULT '', nguoi TEXT DEFAULT '', luc TEXT NULL )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_pn() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, so_phieu TEXT NOT NULL DEFAULT '' UNIQUE, ngay TEXT NOT NULL,
	co_so TEXT NOT NULL DEFAULT '', ncc TEXT DEFAULT '', ghi_chu TEXT NULL, dong TEXT NULL, tong_sl REAL DEFAULT 0, tong_tien REAL DEFAULT 0,
	nguoi TEXT DEFAULT '', luc TEXT NULL )" );
delete_option( 'khh_dt_kho_mat_hang' );
$GLOBALS['VHCP_CO_QUYEN'] = true; $GLOBALS['VHCP_DANG_NHAP_WP'] = true;

$CS = 'TuTu Train - Aeon Tân Phú ( Dịch Vụ  và Giải Trí K&H )';   // hai dấu cách như tên POS thật
$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,mon) VALUES (%s,%s,%f,%s)', '2026-09-24', $CS, 100000, '[]' ) );
khh_dt_kho_mh_dat( $CS, array( 'Nước suối Danasi', 'Kẹo dẻo' ) );
/* Dòng kho 25/09 đã có số đếm và hàng huỷ — phiếu KHÔNG được đụng. */
khh_dt_kho_ghi( '2026-09-25', $CS, 'Nước suối Danasi', array( 'nhap' => 3, 'dem' => 40, 'huy' => 2, 'ghi_chu' => 'đếm tối' ) );

/* ── 1. số phiếu ── */
phep( 'số phiếu đầu ngày: NH20260925-01', 'NH20260925-01' === khh_dt_pn_so_moi( '2026-09-25' ) );

/* ── 5. dòng sạch ── */
$d = khh_dt_pn_dong_sach( array(
	array( 'mh' => 'Nước suối Danasi', 'sl' => '24', 'gia' => '5.000' ),
	array( 'mh' => '', 'sl' => 5 ),
	array( 'mh' => 'Kẹo dẻo', 'sl' => 0 ),
	array( 'mh' => 'nước suối  danasi', 'sl' => 6 ),
	array( 'mat_hang' => 'Bim bim nhỏ', 'so_luong' => 10 ),
) );
phep( '🔴 bỏ dòng trống / SL ≤ 0; cùng tên khác dấu cách + hoa thường thì cộng dồn (24 + 6 = 30); nhận cả mat_hang/so_luong', 2 === count( $d ) && 'Nước suối Danasi' === $d[0]['mh'] && 30.0 === $d[0]['sl'] && 5000.0 === $d[0]['gia'] && 'Bim bim nhỏ' === $d[1]['mh'] && 10.0 === $d[1]['sl'] );

/* ── 2/3. lưu phiếu -> sổ kho ── */
$p1 = khh_dt_pn_luu( array( 'ngay' => '2026-09-25', 'co_so' => 'TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )', 'ncc' => 'Cty Nước Danasi', 'dong' => array( array( 'mh' => 'Nước suối Danasi', 'sl' => 24, 'gia' => 5000 ), array( 'mh' => 'Bim bim nhỏ', 'sl' => 10 ) ) ) );
phep( '🔴 lưu được, số phiếu tự đánh, tên quán về nguyên văn (hai dấu cách), tổng SL 34, tổng tiền 120.000', ! is_wp_error( $p1 ) && 'NH20260925-01' === $p1['so_phieu'] && $CS === $p1['co_so'] && 34.0 === $p1['tong_sl'] && 120000.0 === $p1['tong_tien'] );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . khh_dt_bang_kho() . ' WHERE ngay = %s AND co_so = %s AND mat_hang = %s', '2026-09-25', $CS, 'Nước suối Danasi' ), ARRAY_A );
phep( '🔴 ô Nhập của sổ kho = 24 theo phiếu (đè số 3 gõ tay)', 24.0 === (float) $row['nhap'] );
phep( '🔴 đếm 40, huỷ 2, ghi chú "đếm tối" GIỮ NGUYÊN', 40.0 === (float) $row['dem'] && 2.0 === (float) $row['huy'] && 'đếm tối' === $row['ghi_chu'] );
$row2 = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . khh_dt_bang_kho() . ' WHERE ngay = %s AND co_so = %s AND mat_hang = %s', '2026-09-25', $CS, 'Bim bim nhỏ' ), ARRAY_A );
phep( 'mặt hàng mới: có dòng kho nhập 10, đếm vẫn trống (chưa ai đếm), và vào danh mục', 10.0 === (float) $row2['nhap'] && null === $row2['dem'] && in_array( 'Bim bim nhỏ', khh_dt_kho_mh_cua( $CS ), true ) );
phep( 'sổ ghi động có vết lượt phiếu', 2 <= (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . khh_dt_bang_kho_su() . ' WHERE ngay = %s AND co_so = %s AND mat_hang = %s', '2026-09-25', $CS, 'Nước suối Danasi' ) ) );
$p2 = khh_dt_pn_luu( array( 'ngay' => '2026-09-25', 'co_so' => $CS, 'dong' => array( array( 'mh' => 'nước suối danasi', 'sl' => 6 ) ) ) );
phep( 'phiếu thứ hai: số -02', 'NH20260925-02' === $p2['so_phieu'] );
phep( '🔴 hai phiếu cộng dồn: Nhập = 30', 30.0 === (float) $wpdb->get_var( $wpdb->prepare( 'SELECT nhap FROM ' . khh_dt_bang_kho() . ' WHERE ngay = %s AND co_so = %s AND mat_hang = %s', '2026-09-25', $CS, 'Nước suối Danasi' ) ) );
phep( 'bảng ngày của tab Kho: dòng Nước suối nhập 30, đếm 40', 30.0 === (float) dong_pn( khh_dt_kho_bang_ngay( '2026-09-25', $CS ), 'Nước suối Danasi' )['nhap'] && 40.0 === (float) dong_pn( khh_dt_kho_bang_ngay( '2026-09-25', $CS ), 'Nước suối Danasi' )['dem'] );
function dong_pn( $bang, $mh ) { foreach ( $bang as $d ) { if ( $d['mat_hang'] === $mh ) { return $d; } } return null; }
$cn = khh_dt_pn_cua_ngay( '2026-09-25', $CS );
phep( 'tab Kho biết Nước suối theo 2 phiếu, Bim bim theo 1', array( 'NH20260925-01', 'NH20260925-02' ) === $cn['Nước suối Danasi'] && array( 'NH20260925-01' ) === $cn['Bim bim nhỏ'] );
$e = khh_dt_pn_luu( array( 'ngay' => '2026-09-25', 'co_so' => $CS, 'so_phieu' => 'NH20260925-01', 'dong' => array( array( 'mh' => 'Kẹo dẻo', 'sl' => 1 ) ) ) );
phep( 'số phiếu gõ tay trùng -> chối', is_wp_error( $e ) );
phep( 'thiếu dòng -> chối; thiếu ngày -> chối', is_wp_error( khh_dt_pn_luu( array( 'ngay' => '2026-09-25', 'co_so' => $CS, 'dong' => array() ) ) ) && is_wp_error( khh_dt_pn_luu( array( 'ngay' => '', 'co_so' => $CS, 'dong' => array( array( 'mh' => 'X', 'sl' => 1 ) ) ) ) ) );

/* ── 4. xoá ── */
$x = khh_dt_pn_xoa( $p2['id'] );
phep( '🔴 xoá phiếu -02: Nhập về 24 (còn phiếu -01)', 'NH20260925-02' === $x['so_phieu'] && 24.0 === (float) $wpdb->get_var( $wpdb->prepare( 'SELECT nhap FROM ' . khh_dt_bang_kho() . ' WHERE ngay = %s AND co_so = %s AND mat_hang = %s', '2026-09-25', $CS, 'Nước suối Danasi' ) ) );
khh_dt_pn_xoa( $p1['id'] );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . khh_dt_bang_kho() . ' WHERE ngay = %s AND co_so = %s AND mat_hang = %s', '2026-09-25', $CS, 'Nước suối Danasi' ), ARRAY_A );
phep( '🔴 hết phiếu: Nhập về 0, đếm 40 vẫn nguyên', 0.0 === (float) $row['nhap'] && 40.0 === (float) $row['dem'] );
phep( 'xoá id không có -> false', false === khh_dt_pn_xoa( 99999 ) );
phep( 'số phiếu mới sau khi xoá vẫn tăng theo số lớn nhất còn lại (hết phiếu -> -01)', 'NH20260925-01' === khh_dt_pn_so_moi( '2026-09-25' ) );

/* ── 6. REST ── */
$GLOBALS['VHCP_CO_QUYEN'] = false;
$GLOBALS['VHCP_META'] = array( 'khh_dt_co_so' => 'TuTu Train - Lotte Gò Vấp' );
$r = khh_dt_rest_pn_luu( new WP_REST_Request( array( 'ngay' => '2026-09-25', 'co_so' => $CS, 'dong' => wp_json_encode( array( array( 'mh' => 'Kẹo dẻo', 'sl' => 5 ) ) ) ) ) );
phep( '🔴 cửa hàng trưởng quán khác lập phiếu cho quán này -> 403', is_wp_error( $r ) && 403 === (int) $r->get_error_data()['status'] );
$r = khh_dt_rest_pn_xem( new WP_REST_Request( array( 'co_so' => $CS ) ) );
phep( 'và xem cũng bị chối 403', is_wp_error( $r ) && 403 === (int) $r->get_error_data()['status'] );
$GLOBALS['VHCP_META'] = array( 'khh_dt_co_so' => 'TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )' );   // hồ sơ ghi một dấu cách
/* "Đẩy lên": thân JSON như hệ khác gọi vào. */
$r = khh_dt_rest_pn_luu( new WP_REST_Request( array( 'ngay' => '2026-09-25', 'co_so' => 'TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )', 'ncc' => 'Kho tổng', 'dong' => '[{"mh":"Kẹo dẻo","sl":"12"},{"mh":"Thạch trái cây","sl":"48","gia":"3000"}]' ) ) );
phep( '🔴 đúng quán mình (hồ sơ lệch dấu cách vẫn nhận): lập được phiếu từ JSON, trả về phiếu + danh sách + số mới', ! is_wp_error( $r ) && 'NH20260925-01' === $r['phieu']['so_phieu'] && 2 === count( $r['phieu']['dong'] ) && 1 === count( $r['ds'] ) && 'NH20260925-02' === $r['so_moi'] && false === $r['xoa_duoc'] );
phep( 'kho: Kẹo dẻo nhập 12, Thạch trái cây (mới) nhập 48 và vào danh mục', 12.0 === (float) $wpdb->get_var( $wpdb->prepare( 'SELECT nhap FROM ' . khh_dt_bang_kho() . ' WHERE ngay = %s AND co_so = %s AND mat_hang = %s', '2026-09-25', $CS, 'Kẹo dẻo' ) ) && in_array( 'Thạch trái cây', khh_dt_kho_mh_cua( $CS ), true ) );
$r = khh_dt_rest_pn_luu( new WP_REST_Request( array( 'xoa' => (string) $r['phieu']['id'] ) ) );
phep( '🔴 cửa hàng trưởng KHÔNG xoá được phiếu (403)', is_wp_error( $r ) && 403 === (int) $r->get_error_data()['status'] );
$GLOBALS['VHCP_CO_QUYEN'] = true; $GLOBALS['VHCP_META'] = array();
$ds = khh_dt_pn_ds( $CS );
$r  = khh_dt_rest_pn_luu( new WP_REST_Request( array( 'xoa' => (string) $ds[0]['id'] ) ) );
phep( 'văn phòng xoá được: trả da_xoa, danh sách rỗng, Kẹo dẻo về 0', ! is_wp_error( $r ) && 'NH20260925-01' === $r['da_xoa'] && array() === $r['ds'] && 0.0 === (float) $wpdb->get_var( $wpdb->prepare( 'SELECT nhap FROM ' . khh_dt_bang_kho() . ' WHERE ngay = %s AND co_so = %s AND mat_hang = %s', '2026-09-25', $CS, 'Kẹo dẻo' ) ) );
$r = khh_dt_rest_pn_xem( new WP_REST_Request( array( 'co_so' => $CS, 'ngay' => '2026-09-25' ) ) );
phep( 'GET: số mới, danh sách, cờ xoá được cho văn phòng', 'NH20260925-01' === $r['so_moi'] && array() === $r['ds'] && true === $r['xoa_duoc'] );

/* ── 7. cổng kho GET mang phieu_nhap; plugin nạp module + tạo bảng ── */
khh_dt_pn_luu( array( 'ngay' => '2026-09-25', 'co_so' => $CS, 'dong' => array( array( 'mh' => 'Kẹo dẻo', 'sl' => 7 ) ) ) );
$k = khh_dt_rest_kho_xem( new WP_REST_Request( array( 'ngay' => '2026-09-25', 'co_so' => $CS ) ) );
phep( 'GET kho mang phieu_nhap [mặt hàng => số phiếu]', ! is_wp_error( $k ) && isset( $k['phieu_nhap']['Kẹo dẻo'] ) && 'NH20260925-01' === $k['phieu_nhap']['Kẹo dẻo'][0] );
$src = preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $goc . '/khh-doanh-thu.php' ) );
phep( 'khh-doanh-thu.php nạp phieu-nhap.php và tạo bảng lúc kích hoạt', false !== strpos( $src, "require_once KHH_DT_DIR . 'phieu-nhap.php';" ) && false !== strpos( $src, 'khh_dt_tao_bang_pn();' ) );
$src_pn = preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $goc . '/phieu-nhap.php' ) );
phep( "route /phieu-nhap: GET gác khh_dt_duoc_xem, POST gác khh_dt_duoc_ghi", false !== strpos( $src_pn, "'permission_callback' => 'khh_dt_duoc_xem'" ) && false !== strpos( $src_pn, "'permission_callback' => 'khh_dt_duoc_ghi'" ) );
phep( 'mã plugin không dựng WP_REST_Request bằng mảng', 0 === preg_match( '~new WP_REST_Request\s*\(\s*array~', $src_pn ) );
delete_option( 'khh_dt_kho_mat_hang' );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: phiếu nhập đẩy vào ô Nhập của sổ kho, cộng dồn, xoá tính lại, không đụng số đếm.\n";
