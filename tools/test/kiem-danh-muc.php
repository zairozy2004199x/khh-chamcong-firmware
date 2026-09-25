<?php
/**
 * DANH MỤC HÀNG HOÁ FABi — NẠP TỪ FILE, DÙNG CHUNG SỔ KHO / COMBO / VÉ / MISA — VÀ XOÁ HÀNG SAI — CHẠY THẬT.
 *
 * Anh Thắng 25/09/2026: *"Tạo hàng mới trên FABi mà chưa bán, thành ra doanh thu nó không có hàng đó để nhập kho. Tạo
 * thêm tải danh sách hàng hoá xuống. Cho phép xoá hàng sai trên kho hàng."* Kèm file thật "update item in store.xlsx"
 * (cột: ID · Mã món · Thành phố · Cửa hàng · Tên · Giá · Trạng thái · … · Đơn vị · Nhóm · Tên nhóm · Loại món · Tên loại).
 *
 * Chốt:
 *   1. 🔴 Đọc đúng bố cục thật: tên ở cột "Tên", nhóm/loại lấy "Tên nhóm"/"Tên loại" (không lấy mã), cùng mã ở hai quán gộp
 *      một dòng kèm danh sách quán; bố cục cũ "Tên hàng + Mã hàng" vẫn đọc; không có dòng tên cột -> lỗi rõ.
 *   2. Loại "Combo" là vé; nạp lại đếm mới / mất; xoá được.
 *   3. 🔴 Sổ kho nhận danh mục của ĐÚNG quán; Bóc tách vé bày vé chưa bán (chua_ban); MISA lấy mã món chưa bán từ danh mục.
 *   4. 🔴 Xoá hàng sai: người phụ trách quán xoá được món FABi chưa bán — hết danh mục, hết dòng sổ, sổ động thêm vết;
 *      món FABi đã bán -> 403 (chỉ văn phòng).
 *   5. REST /danh-muc GET trả ds + nhóm; POST xoa=1 dọn; quyền nạp.
 *
 * Chạy: php tools/test/kiem-danh-muc.php
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
if ( ! function_exists( 'get_temp_dir' ) ) { function get_temp_dir() { return rtrim( sys_get_temp_dir(), '/' ) . '/'; } }
if ( ! function_exists( 'wp_delete_file' ) ) { function wp_delete_file( $f ) { return @unlink( $f ); } } // phpcs:ignore
if ( ! function_exists( 'khh_dt_duoc_ghi' ) ) { function khh_dt_duoc_ghi() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_xem' ) ) { function khh_dt_duoc_xem() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_nap' ) ) { function khh_dt_duoc_nap() { return ! empty( $GLOBALS['VHCP_CO_QUYEN'] ) ? true : new WP_Error( 'x', 'không', array( 'status' => 403 ) ); } }
if ( ! function_exists( 'khh_dt_duoc_quan_tri' ) ) { function khh_dt_duoc_quan_tri() { return ! empty( $GLOBALS['VHCP_CO_QUYEN'] ); } }
if ( ! function_exists( 'khh_dt_phien_nguoi' ) ) { function khh_dt_phien_nguoi() { return false; } }
if ( ! function_exists( 'khh_dt_co_so_ds' ) ) { function khh_dt_co_so_ds() { return array(); } }
/* Người phụ trách quán (seam riêng của bài này). */
if ( ! function_exists( 'khh_dt_duoc_cua_hang' ) ) { function khh_dt_duoc_cua_hang( $c ) { return ! empty( $GLOBALS['DM_PHU_TRACH'] ); } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return ''; } }
require_once $goc . '/ve-khach.php';
require_once $goc . '/kho.php';
require_once $goc . '/misa.php';
require_once $goc . '/danh-muc.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
$GLOBALS['VHCP_CO_QUYEN'] = true;
$GLOBALS['KHH_DT_MISA_KHONG_NHO'] = true;
foreach ( array( khh_dt_bang(), khh_dt_bang_kho(), khh_dt_bang_kho_su() ) as $b ) { $wpdb->exec_raw( "DROP TABLE IF EXISTS $b" ); }
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', doanh_thu REAL DEFAULT 0, pttt TEXT DEFAULT '[]', mon TEXT NOT NULL DEFAULT '[]', UNIQUE(ngay,cua_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_kho() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT NOT NULL, co_so TEXT NOT NULL DEFAULT '', mat_hang TEXT NOT NULL DEFAULT '', nhap REAL DEFAULT 0, ban_khai REAL NULL, combo_tay REAL DEFAULT 0, dem REAL NULL, dat_dau REAL NULL, huy REAL DEFAULT 0, ghi_chu TEXT DEFAULT '', nguoi TEXT DEFAULT '', luc INTEGER DEFAULT 0, UNIQUE(ngay,co_so,mat_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_kho_su() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT NOT NULL, co_so TEXT NOT NULL DEFAULT '', mat_hang TEXT NOT NULL DEFAULT '', nhap REAL NOT NULL DEFAULT 0, ban_khai REAL NULL, combo_tay REAL NOT NULL DEFAULT 0, dem REAL NULL, dat_dau REAL NULL, huy REAL NOT NULL DEFAULT 0, ghi_chu TEXT DEFAULT '', nguoi TEXT DEFAULT '', luc TEXT NULL )" );
foreach ( array( KHH_DT_DM_OPT, 'khh_dt_kho_mat_hang', 'khh_dt_kho_ma', 'khh_dt_ve_khach', 'khh_dt_ve_phu', 'khh_dt_nhom_phu', 'khh_dt_nhom_ve' ) as $k ) { delete_option( $k ); }

$BD = 'Tutu Train - Bình Dương ( Dịch Vụ và Giải Trí K&H )';
$TA = 'Tutu Train -  Aeon Tân An ( Dịch vụ K&H )';
/* File tổng hợp theo đúng bố cục "update item in store" (dữ liệu bịa, hai quán, một mã ở cả hai quán). */
$dong = array(
	array( 'ID', 'Mã món', 'Thành phố', 'Cửa hàng', 'Tên', 'Giá', 'Trạng thái', 'Mã barcode', 'Món ăn kèm', 'Đơn vị', 'Nhóm', 'Tên nhóm', 'Loại món', 'Tên loại', 'SKU' ),
	array( 'id1', 'MNKVCTT021', 'Bình Dương', $BD, 'VÉ TRỂ EM + NGƯỜI LỚN + BIMBIM MÁI NGÓI', '80000', '1', '', '0', 'MON', 'MNKVCVECOMBO', 'VÉ COMBO.', 'ITEM_CLASS-T7HK', 'Vé', '' ),
	array( 'id2', 'MNKVCDS007', 'Bình Dương', $BD, 'BIM BIM NHỎ', '20000', '1', '', '0', 'MON', 'MNKVCDS', 'ĐÓNG SẴN', 'ITEM_CLASS-DA', 'Đồ ăn', '' ),
	array( 'id3', 'MNKVCDS007', 'HCM', 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', 'BIM BIM NHỎ', '20000', '1', '', '0', 'MON', 'MNKVCDS', 'ĐÓNG SẴN', 'ITEM_CLASS-DA', 'Đồ ăn', '' ),
	array( 'id4', 'MNKVCDS099', 'HCM', 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', 'ĐÙI GÀ PHÔ MAI', '25000', '1', '', '0', 'MON', 'MNKVCDS', 'ĐÓNG SẴN', 'ITEM_CLASS-DA', 'Đồ ăn', '' ),
	array( 'id5', 'MNKVCTT050', 'HCM', 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', 'Combo 2 Người (1 NLớn 1 Trẻ Em)', '150000', '1', '', '0', 'VE', 'MNKVCVE', 'VÉ LẺ.', 'ITEM_CLASS-CB', 'Combo', '' ),
	array( 'id6', 'MNKVCTT060', 'HCM', 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', 'VÉ TUTU TRAIN: VÉ MỚI 2026', '50000', '1', '', '0', 'VE', 'MNKVCVE', 'VÉ LẺ.', 'ITEM_CLASS-VE', 'Vé', '' ),
	array( 'id7', '', 'HCM', 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', '', '0', '1', '', '0', 'MON', '', '', '', '', '' ),
);
$tep = tempnam( sys_get_temp_dir(), 'dm' ) . '.csv';
$f = fopen( $tep, 'w' ); fwrite( $f, "\xEF\xBB\xBF" ); foreach ( $dong as $d ) { fputcsv( $f, $d, ',', '"', '\\' ); } fclose( $f );

/* ── 1. đọc ── */
$ds = khh_dt_dm_phan_tich( $tep, 'update-item-in-store.csv' );
phep( 'đọc được, bỏ dòng không tên, gộp cùng mã: 5 dòng', is_array( $ds ) && 5 === count( $ds ) );
$tim = function ( $ds, $ma ) { foreach ( $ds as $m ) { if ( $m['ma'] === $ma ) { return $m; } } return null; };
$b = $tim( $ds, 'MNKVCDS007' );
phep( '🔴 nhóm lấy "Tên nhóm" (ĐÓNG SẴN) chứ không phải mã MNKVCDS; loại lấy "Tên loại" (Đồ ăn); giá số', $b && 'ĐÓNG SẴN' === $b['nhom'] && 'Đồ ăn' === $b['loai'] && 20000.0 === $b['gia'] && 'MON' === $b['dvt'] );
phep( '🔴 cùng mã ở hai quán -> một dòng, cs = [Bình Dương, Tân An] (tên nguyên văn từng quán)', $b && 2 === count( $b['cs'] ) && $BD === $b['cs'][0] );
$v = $tim( $ds, 'MNKVCTT021' );
phep( 'vé: loại "Vé" -> la_ve; nhóm "VÉ COMBO." -> la_combo dù tên không có chữ combo', $v && khh_dt_dm_la_ve( $v ) && khh_dt_dm_la_combo( $v ) && ! khh_dt_dm_la_combo( $b ) );
$c2 = $tim( $ds, 'MNKVCTT050' );
phep( '🔴 loại "Combo" cũng là vé (và là combo)', $c2 && khh_dt_dm_la_ve( $c2 ) && khh_dt_dm_la_combo( $c2 ) );
phep( 'hàng: BIM BIM NHỎ không phải vé', ! khh_dt_dm_la_ve( $b ) );
/* bố cục cũ: Tên hàng + Mã hàng, không có cửa hàng */
$tep2 = tempnam( sys_get_temp_dir(), 'dm' ) . '.csv';
file_put_contents( $tep2, "Danh sách hàng hoá\nMã hàng,Tên hàng,Nhóm hàng,Loại món,Đơn vị tính,Giá bán\nMNKVCDS023,NƯỚC SUỐI DANASI,ĐÓNG SẴN,Đồ uống,Chai,10000\n" );
$ds2 = khh_dt_dm_phan_tich( $tep2, 'x.csv' );
phep( 'bố cục "Tên hàng + Mã hàng" (dòng tiêu đề trước) vẫn đọc; không có cửa hàng -> cs rỗng -> thuộc mọi quán', is_array( $ds2 ) && 1 === count( $ds2 ) && 'Chai' === $ds2[0]['dvt'] && array() === $ds2[0]['cs'] && khh_dt_dm_thuoc_quan( $ds2[0], $BD ) );
$tep3 = tempnam( sys_get_temp_dir(), 'dm' ) . '.csv';
file_put_contents( $tep3, "a,b,c\n1,2,3\n" );
$l = khh_dt_dm_phan_tich( $tep3, 'x.csv' );
phep( 'không có dòng tên cột -> WP_Error khh_dt_cot', is_wp_error( $l ) && 'khh_dt_cot' === $l->get_error_code() );

/* ── 2. nạp / nạp lại / xoá ── */
$kq = khh_dt_dm_nap( $tep, 'update-item-in-store.csv' );
phep( 'nạp lần đầu: 5 món, 5 mới, 0 mất; option có luc/nguon', 5 === $kq['so'] && 5 === $kq['moi'] && 0 === $kq['mat'] && 5 === khh_dt_dm_goi()['so'] && 'update-item-in-store.csv' === khh_dt_dm_goi()['nguon'] );
$kq = khh_dt_dm_nap( $tep2, 'x.csv' );
phep( 'file KHÔNG có cột Cửa hàng, không chọn quán -> thay cả bản: 1 món, 1 mới, 5 mất', 1 === $kq['so'] && 1 === $kq['moi'] && 5 === $kq['mat'] && 1 === khh_dt_dm_goi()['so'] && array() === $kq['quan'] );
khh_dt_dm_xoa();
khh_dt_dm_nap( $tep, 'update-item-in-store.csv' );
/* ── 🔴 NẠP THEO QUÁN (anh Thắng 25/09/2026: "Nạp danh mục hàng hoá có cần chọn cơ sở không") ── */
$tep4 = tempnam( sys_get_temp_dir(), 'dm' ) . '.csv';
$f = fopen( $tep4, 'w' ); fwrite( $f, "\xEF\xBB\xBF" );
fputcsv( $f, $dong[0], ',', '"', '\\' );
fputcsv( $f, array( 'id8', 'MNKVCDS007', 'HCM', 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', 'BIM BIM NHỎ', '20000', '1', '', '0', 'MON', 'MNKVCDS', 'ĐÓNG SẴN', 'ITEM_CLASS-DA', 'Đồ ăn', '' ), ',', '"', '\\' );
fputcsv( $f, array( 'id9', 'MNKVCDS123', 'HCM', 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', 'THẠCH TRÁI CÂY', '10000', '1', '', '0', 'MON', 'MNKVCDS', 'ĐÓNG SẴN', 'ITEM_CLASS-DA', 'Đồ ăn', '' ), ',', '"', '\\' );
fclose( $f );
$kq = khh_dt_dm_nap( $tep4, 'tan-an.csv' );
$ck_bd = khh_dt_dm_cho_kho( $BD );
$ck_ta = khh_dt_dm_cho_kho( $TA );
phep( '🔴 nạp file chỉ có Tân An: Bình Dương GIỮ NGUYÊN (2 món), Tân An thay bằng 2 món của file (mất ĐÙI GÀ, 2 vé cũ; thêm THẠCH)', 2 === count( $ck_bd ) && 2 === count( $ck_ta ) && array( 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )' ) === $kq['quan'] && 1 === $kq['moi'] && 3 === $kq['mat'] && 3 === $kq['tong'] );
$b2 = khh_dt_dm_tra( 'BIM BIM NHỎ' );
phep( 'cùng mã ở hai quán: vẫn một dòng, cs gộp cả Bình Dương lẫn Tân An', $b2 && 2 === count( $b2['cs'] ) );
/* file không có cột quán + CHỌN quán ở ô -> gán cho quán ấy, quán khác giữ */
$kq = khh_dt_dm_nap( $tep2, 'x.csv', 'TuTu Train - Lotte Gò Vấp' );
phep( '🔴 chọn quán cho file không có cột Cửa hàng: NƯỚC SUỐI thuộc Gò Vấp; Bình Dương, Tân An giữ nguyên; cả bảng 4 dòng (BIM BIM dùng chung hai quán)', array( 'TuTu Train - Lotte Gò Vấp' ) === $kq['quan'] && 1 === count( khh_dt_dm_cho_kho( 'TuTu Train - Lotte Gò Vấp' ) ) && 2 === count( khh_dt_dm_cho_kho( $BD ) ) && 4 === $kq['tong'] );
$tq = array(); foreach ( khh_dt_dm_theo_quan() as $x ) { $tq[ $x['cua_hang'] ] = $x; }
phep( 'theo_quan: ba quán, số món và lúc nạp từng quán', 3 === count( $tq ) && 2 === $tq[ $BD ]['so'] && 1 === $tq['TuTu Train - Lotte Gò Vấp']['so'] && '' !== $tq[ $BD ]['luc'] );
khh_dt_dm_xoa( 'tutu train - lotte gò vấp' );
phep( 'xoá riêng một quán (tên gõ thường): Gò Vấp hết, hai quán kia còn, còn 3 dòng', 0 === count( khh_dt_dm_cho_kho( 'TuTu Train - Lotte Gò Vấp' ) ) && 3 === khh_dt_dm_goi()['so'] && ! isset( khh_dt_dm_goi()['cs_luc']['TuTu Train - Lotte Gò Vấp'] ) );
khh_dt_dm_xoa( $TA );
phep( 'xoá Tân An: BIM BIM NHỎ (còn ở Bình Dương) chỉ bớt quán, THẠCH (chỉ Tân An) bỏ hẳn', 2 === khh_dt_dm_goi()['so'] && null === khh_dt_dm_tra( 'THẠCH TRÁI CÂY' ) && array( $BD ) === khh_dt_dm_tra( 'BIM BIM NHỎ' )['cs'] );
@unlink( $tep4 ); // phpcs:ignore
khh_dt_dm_xoa();
khh_dt_dm_nap( $tep, 'update-item-in-store.csv' );
phep( 'ma bảng: tên lỏng -> mã; tra lỏng (thừa dấu cách, hoa thường) ra dòng', 'MNKVCDS099' === khh_dt_dm_ma_bang()['đùi gà phô mai'] && 'MNKVCDS007' === khh_dt_dm_tra( 'bim bim  nhỏ' )['ma'] && null === khh_dt_dm_tra( 'không có' ) );
phep( 'vé của quán Tân An: 2 (VÉ MỚI + Combo 2 Người); của Bình Dương: 1', 2 === count( khh_dt_dm_ve_ds( $TA ) ) && 1 === count( khh_dt_dm_ve_ds( $BD ) ) && 3 === count( khh_dt_dm_ve_ds() ) );
$ck = khh_dt_dm_cho_kho( 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )' );
phep( '🔴 danh mục cho sổ kho Tân An (tên gõ một dấu cách vẫn trúng): 4 món, hàng trước vé, có cờ ve/combo/ma', 4 === count( $ck ) && ! $ck[0]['ve'] && ! $ck[1]['ve'] && $ck[2]['ve'] && 'MNKVCDS007' === $ck[0]['ma'] );
phep( 'quán không có trong file (Gò Vấp) -> chỉ món không gắn quán (không có) -> rỗng', array() === khh_dt_dm_cho_kho( 'TuTu Train - Lotte Gò Vấp' ) );

/* ── 3. sổ kho / bóc tách vé / MISA đọc danh mục ── */
$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,mon) VALUES (%s,%s,%f,%s)', '2026-09-24', $TA, 1000000, wp_json_encode( array(
	array( 'n' => 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN', 'g' => 'VÉ LẺ.', 'l' => 'Vé', 'm' => 'MNKVCTT004', 'q' => 16, 'r' => 800000 ),
	array( 'n' => 'BIM BIM NHỎ', 'g' => 'ĐÓNG SẴN', 'l' => 'Đồ ăn', 'q' => 10, 'r' => 200000 ),
) ) ) );
$r = khh_dt_rest_kho_xem( new WP_REST_Request( array( 'ngay' => '2026-09-24', 'co_so' => $TA ) ) );
phep( '🔴 GET kho mang danh_muc của đúng quán (4 món) + danh_muc_luc', 4 === count( $r['danh_muc'] ) && '' !== $r['danh_muc_luc'] );
$ve = khh_dt_ve_khach_mon_cua( $TA, 90 );
$ten_ve = array(); foreach ( $ve as $x ) { $ten_ve[ $x['ten'] ] = $x; }
phep( '🔴 Bóc tách vé bày vé CHƯA BÁN của quán (VÉ MỚI 2026, Combo 2 Người) với so_luong 0, chua_ban=true; vé đã bán chua_ban=false', isset( $ten_ve['VÉ TUTU TRAIN: VÉ MỚI 2026'] ) && $ten_ve['VÉ TUTU TRAIN: VÉ MỚI 2026']['chua_ban'] && 0.0 === (float) $ten_ve['VÉ TUTU TRAIN: VÉ MỚI 2026']['so_luong'] && 'VÉ LẺ.' === $ten_ve['VÉ TUTU TRAIN: VÉ MỚI 2026']['nhom'] && isset( $ten_ve['Combo 2 Người (1 NLớn 1 Trẻ Em)'] ) && empty( $ten_ve['VÉ TUTU TRAIN: VÉ NGƯỜI LỚN']['chua_ban'] ) );
phep( 'vé của quán khác (Bình Dương) KHÔNG lọt vào Tân An', ! isset( $ten_ve['VÉ TRỂ EM + NGƯỜI LỚN + BIMBIM MÁI NGÓI'] ) );
$ma = khh_dt_misa_ma_fabi();
phep( '🔴 MISA: mã của món chưa bán (ĐÙI GÀ PHÔ MAI) lấy từ danh mục; món đã bán không có m trong dòng cũng có mã nhờ danh mục', 'MNKVCDS099' === $ma['đùi gà phô mai'] && 'MNKVCDS007' === $ma['bim bim nhỏ'] && 'MNKVCTT004' === $ma['vé tutu train: vé người lớn'] );

/* ── 4. xoá hàng sai ── */
khh_dt_kho_them_mh( $TA, 'BIM BIM NHỎ' );
khh_dt_kho_them_mh( $TA, 'BIMBIM NHO SAI', 'X1' );            // gõ sai, FABi chưa bán
khh_dt_kho_ghi( '2026-09-24', $TA, 'BIMBIM NHO SAI', array( 'nhap' => 5, 'dem' => 5 ) );
khh_dt_kho_ghi( '2026-09-23', $TA, 'BIMBIM NHO SAI', array( 'dat_dau' => 3 ) );
$so_su_truoc = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . khh_dt_bang_kho_su() );
$GLOBALS['VHCP_CO_QUYEN'] = false; $GLOBALS['DM_PHU_TRACH'] = true;
$r = khh_dt_rest_kho_mat_hang( new WP_REST_Request( array( 'co_so' => $TA, 'ngay' => '2026-09-24', 'ds' => wp_json_encode( array( 'BIM BIM NHỎ' ) ), 'xoa_ten' => 'BIMBIM NHO SAI' ) ) );
phep( '🔴 người phụ trách quán xoá được món FABi CHƯA BÁN: hết danh mục, hết mã', ! is_wp_error( $r ) && ! in_array( 'BIMBIM NHO SAI', $r['mat_hang'], true ) && ! isset( $r['ma_hang']['BIMBIM NHO SAI'] ) );
phep( '🔴 … và hết DÒNG SỔ của món ấy ở quán ấy; sổ ghi động thêm một vết "xoá mặt hàng"', 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . khh_dt_bang_kho() . ' WHERE co_so=%s AND mat_hang=%s', $TA, 'BIMBIM NHO SAI' ) ) && $so_su_truoc + 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . khh_dt_bang_kho_su() ) && 0 === strpos( (string) $wpdb->get_var( 'SELECT ghi_chu FROM ' . khh_dt_bang_kho_su() . ' ORDER BY id DESC LIMIT 1' ), 'xoá mặt hàng' ) );
$r = khh_dt_rest_kho_mat_hang( new WP_REST_Request( array( 'co_so' => $TA, 'ngay' => '2026-09-24', 'ds' => '[]', 'xoa_ten' => 'BIM BIM NHỎ' ) ) );
phep( '🔴 món FABi ĐÃ BÁN thì người quán không xoá được (403), danh mục còn nguyên', is_wp_error( $r ) && 403 === (int) $r->get_error_data()['status'] && in_array( 'BIM BIM NHỎ', khh_dt_kho_mh_cua( $TA ), true ) );
$GLOBALS['DM_PHU_TRACH'] = false;
khh_dt_kho_them_mh( $TA, 'MÓN LẠ' );
$r = khh_dt_rest_kho_mat_hang( new WP_REST_Request( array( 'co_so' => $TA, 'ngay' => '2026-09-24', 'ds' => '[]', 'xoa_ten' => 'MÓN LẠ' ) ) );
phep( 'không phụ trách quán -> 403 dù món chưa bán', is_wp_error( $r ) && 403 === (int) $r->get_error_data()['status'] );
$GLOBALS['VHCP_CO_QUYEN'] = true;
$r = khh_dt_rest_kho_mat_hang( new WP_REST_Request( array( 'co_so' => $TA, 'ngay' => '2026-09-24', 'ds' => '[]', 'xoa_ten' => 'BIM BIM NHỎ' ) ) );
phep( 'văn phòng xoá được cả món đã bán', ! is_wp_error( $r ) && ! in_array( 'BIM BIM NHỎ', $r['mat_hang'], true ) );

/* ── 5. REST danh mục ── */
$r = khh_dt_rest_dm_xem();
phep( 'GET danh-muc: ds 5, nhóm đếm (ĐÓNG SẴN 2), so_ve 3, so_combo 2, theo_quan 2 quán', 5 === count( $r['ds'] ) && 2 === $r['nhom']['ĐÓNG SẴN'] && 3 === $r['so_ve'] && 2 === $r['so_combo'] && 2 === count( $r['theo_quan'] ) );
$r = khh_dt_rest_dm_nap( new WP_REST_Request( array( 'xoa' => '1', 'cua_hang' => $BD ) ) );
phep( 'POST xoa=1 + cua_hang -> chỉ dọn quán ấy (còn 4 món của Tân An, kể cả BIM BIM giờ chỉ còn Tân An)', 4 === count( $r['ds'] ) && array( 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )' ) === khh_dt_dm_tra( 'BIM BIM NHỎ' )['cs'] );
$r = khh_dt_rest_dm_nap( new WP_REST_Request( array( 'xoa' => '1' ) ) );
phep( 'POST xoa=1 -> dọn sạch, trả bản xem rỗng', 0 === count( $r['ds'] ) && array() === khh_dt_dm_ds() );
$r = khh_dt_rest_dm_nap( new WP_REST_Request( array() ) );
phep( 'POST không file -> 400', is_wp_error( $r ) && 400 === (int) $r->get_error_data()['status'] );
$src = preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $goc . '/danh-muc.php' ) );
phep( 'route /danh-muc: GET quyền xem, POST quyền nạp; ưu tiên trang "Menu", bỏ trang Template', false !== strpos( $src, "'permission_callback' => 'khh_dt_duoc_nap'" ) && false !== strpos( $src, "'permission_callback' => 'khh_dt_duoc_xem'" ) && false !== strpos( $src, "'menu' === khh_dt_khong_dau( \$t['ten'] )" ) );
phep( 'khh-doanh-thu.php nạp danh-muc.php', false !== strpos( file_get_contents( $goc . '/khh-doanh-thu.php' ), "require_once KHH_DT_DIR . 'danh-muc.php';" ) );
@unlink( $tep ); @unlink( $tep2 ); @unlink( $tep3 ); // phpcs:ignore

echo $hong ? '✗ HỎNG ' . count( $hong ) . ' / ' . ( $dat + count( $hong ) ) . " phép:\n  · " . implode( "\n  · ", $hong ) . "\n"
	: '✓ SẠCH — ' . $dat . " phép: danh mục hàng hoá FABi nạp được, sổ kho / vé / MISA đọc chung, xoá hàng sai có vết.\n";
exit( $hong ? 1 : 0 );
