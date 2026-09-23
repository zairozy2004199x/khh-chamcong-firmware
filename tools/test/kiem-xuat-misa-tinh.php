<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * XUẤT MISA: THỨ TỰ UNIT · TÁCH THEO TỈNH · MÃ ĐỐI TƯỢNG (KHÁCH HÀNG)
 *
 * Anh Thắng 22/09/2026, ba câu:
 *   1. *"Sắp xếp Unit theo thứ tự để xuất misa"*
 *   2. *"Xuất rõ phân theo tỉnh"*
 *   3. *"Xuất kèm mã đối tượng (chính là mã khách hàng)"*  — kèm ảnh tệp chứng từ, cột ấy TRẮNG.
 *   4. *"Chỗ xuất QR lấy theo số thực tức QR số màu đỏ"* — QR trong sổ phải là tiền THỰC về
 *      ngân hàng, không phải số nhân viên đọc trên máy.
 *
 * 🔴 VÌ SAO CỘT MÃ ĐỐI TƯỢNG KHÔNG PHẢI TRANG TRÍ. Bút toán ghi Nợ TK 131 — phải thu KHÁCH HÀNG.
 *    Một khoản phải thu không có đối tượng thì MISA không dựng được sổ công nợ: tổng doanh thu
 *    vẫn đúng, nhưng "ai còn nợ bao nhiêu" thì không có. Tệp vẫn tải về, vẫn trông như xong.
 *
 * 🔴 VÀ NÓ PHẢI LẤY TỪ `coso.ma_kh` — CHỖ ĐANG CÓ. Anh Thắng chỉ thẳng thẻ cơ sở màn Địa điểm:
 *    *"chính là mã khách hàng"* (AEON MALL BÌNH DƯƠNG · 🏷 KH00108). Cột ấy có từ 1.99.8, kế toán
 *    đang dùng để đối chiếu với sổ ngoài. Bản 2.124.0 lỡ đẻ thêm một ô "mã đối tượng" ở bảng Unit
 *    ID — hai chỗ gõ cùng một con số, rồi một ngày chúng lệch nhau và không ô nào tự nhận mình
 *    sai. Bài này canh đúng điều đó: nguồn phải là `coso`, và KHÔNG được có ô khai thứ hai.
 *
 * 🔴 VÌ SAO THỨ TỰ KHÔNG PHẢI CHUYỆN THẨM MỸ. Số đầu Unit ID chính là tỉnh (58·59·60·61·62). Luật
 *    cũ xếp theo `vung` dạng CHỮ nên "CA MAU" (62) nhảy lên trước "CAN THO" (59) — ngược với bảng
 *    anh Thắng dựng tay, và người dán vào Excel tháng trước thấy hàng không còn khớp.
 *    Luật cũ còn một lỗi câm: chốt cuối viết `strcmp($a['unit_id'], $a['unit_id'])` — so $a với
 *    CHÍNH NÓ, luôn trả 0, nên hai cơ sở ngang hàng xếp ngẫu nhiên theo cách MySQL trả hàng.
 *
 * ⚠️ BÀI NÀY BỐC THẲNG HAI HÀM XUẤT TỪ MÃ NGUỒN RA CHẠY với `$wpdb` giả — không chép lại logic,
 *    không dò chuỗi. Dò chuỗi không nói được "dòng CỘNG có đúng bằng tổng các dòng trên nó không".
 *
 * Chạy: php tools/test/kiem-xuat-misa-tinh.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' );
	echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n";
}

/* ---------- Bệ đỡ tí hon ---------- */
define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }
/* `remove_accents` của WordPress — bản tối giản đủ cho tiếng Việt trong bài này. Bốc nguyên
   `squash()` từ mã nguồn ra dùng (dưới), không chép lại luật: luật chuẩn hoá tên cơ sở chỉ được
   có MỘT bản, repo này đã có đúng một vụ hai bản lệch nhau (xem CLAUDE.md mục 5). */
function remove_accents( $s ) {
	$b = array( 'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
		'ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
		'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ' );
	$a = array( 'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e',
		'i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
		'u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d' );
	$s = (string) $s;
	$s = str_replace( $b, $a, mb_strtolower( $s, 'UTF-8' ) );
	return $s;
}
class VHG_BaoCao { public static function ngay_( $v ) {
	$s = trim( (string) $v );
	return preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $s, $m ) ? ( $m[1] . '-' . $m[2] . '-' . $m[3] ) : '';
} }
function current_time( $f ) { return 'Y-m' === $f ? '2026-09' : gmdate( 'Y-m-d H:i:s' ); }
function get_option( $k, $d = false ) { return $d; }

class WpdbGia {
	public $bcDong = array(); public $maMisa = array(); public $ctRows = array();
	public function prepare( $sql, ...$a ) {
		if ( 1 === count( $a ) && is_array( $a[0] ) ) { $a = $a[0]; }
		foreach ( $a as $v ) { $sql = preg_replace( '/%s|%d/', "'" . $v . "'", $sql, 1 ); }
		return $sql;
	}
	public $coso = array();
	public function get_results( $sql, $out = null ) {
		if ( false !== strpos( $sql, 'bc_ma_misa' ) ) { return $this->maMisa; }
		if ( false !== strpos( $sql, 'wp_vhg_coso' ) ) { return $this->coso; }
		if ( false !== strpos( $sql, 'd.ma_may' ) )   { return $this->ctRows; }
		return $this->bcDong;
	}
}

/* ---------- Bốc hai hàm ra ---------- */
$nguon = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
function boc( $nguon, $mo ) {
	$i = strpos( $nguon, $mo );
	if ( false === $i ) { return ''; }
	$d = 0; $n = strlen( $nguon );
	for ( $k = strpos( $nguon, '{', $i ); $k < $n; $k++ ) {
		if ( '{' === $nguon[ $k ] ) { $d++; }
		elseif ( '}' === $nguon[ $k ] && 0 === --$d ) { return substr( $nguon, $i, $k - $i + 1 ); }
	}
	return '';
}
/* Bốc chính `squash()` từ class-vhg-baocao.php — khoá ghép tên cơ sở phải là MỘT luật duy nhất. */
$f_sq = boc( file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-baocao.php' ),
	'public static function squash(' );
$f_bcn = boc( $nguon, 'public static function baocao_ngay(' );
$f_ct  = boc( $nguon, 'public static function misa_chungtu(' );
$f_chia = boc( $nguon, 'public static function chia_ty_le_(' );
$f_vq   = boc( $nguon, 'private static function vietqr_thuc_(' );
t( 'bốc được baocao_ngay + misa_chungtu + chia_ty_le_ + vietqr_thuc_ + squash',
	'' !== $f_bcn && '' !== $f_ct && '' !== $f_sq && '' !== $f_chia && '' !== $f_vq );
if ( '' === $f_bcn || '' === $f_ct || '' === $f_sq || '' === $f_chia || '' === $f_vq ) { echo "✗ không bốc được — dừng.\n"; exit( 1 ); }
eval( 'class VHG_KeToan {
	public static function ngay_( $v ) { return VHG_BaoCao::ngay_( $v ); }
	' . $f_sq . '
	public static function thang_( $v ) {
		$s = trim( (string) $v );
		if ( preg_match( "/^(\\\\d{4})[-_](\\\\d{2})$/", $s, $m ) ) { return $m[1] . "-" . $m[2]; }
		return current_time( "Y-m" );
	}
	private static function dmy_( $d ) {
		$m = self::ngay_( $d );
		return preg_match( "/^(\\\\d{4})-(\\\\d{2})-(\\\\d{2})$/", $m, $x ) ? ( $x[3] . "/" . $x[2] . "/" . $x[1] ) : $m;
	}
	private static function misa_xuat_map() { return array(); }
	' . $f_bcn . "\n" . $f_ct . "\n" . $f_chia . "\n" . $f_vq . '
}' );

/* ══════════════════════════════════ 1. BÁO CÁO NGÀY: thứ tự + tỉnh ══════════════════════════ */
echo "── Báo cáo ngày: thứ tự Unit và tách theo tỉnh ──────────────\n";
global $wpdb;
$wpdb = new WpdbGia();
/* Đúng dàn trong ảnh anh Thắng gửi, cố tình xáo trộn thứ tự trả về để bài kiểm có việc thật. */
$khai = array(
	array( 'SENSE CITY CAN THO', '59SCCT', 'CAN THO' ),
	array( 'GO TRA VINH',        '58GOTV', 'HO CHI MINH' ),
	array( 'SENSE CITY CA MAU',  '62SCCM', 'CA MAU' ),
	array( 'GO CAN THO',         '59GOCT', '' ),          // cùng tỉnh 59, KHÔNG khai lại tên vùng
	array( 'ZONE C KIEN GIANG',  '61ZCKG', 'KIEN GIANG' ),
	array( 'GO DA LAT',          '60GODL', 'BUON ME THUOT' ),
	array( 'CGV VINCOM XUAN KHANH', '59CGVXK', '' ),
	array( 'GO BAC LIEU',        '62GOBL', '' ),
	array( 'CHUA KHAI UNIT',     '',       '' ),          // thiếu Unit ID → dồn cuối
);
$wpdb->maMisa = array(); $wpdb->bcDong = array();
foreach ( $khai as $k ) {
	$wpdb->maMisa[] = array( 'coso_key' => $k[0], 'unit_id' => $k[1], 'unit_name' => $k[0],
		'vung' => $k[2], 'thu_tu' => 0 );
	$wpdb->bcDong[] = array( 'coso' => $k[0], 'coso_key' => $k[0], 'ng' => 6, 'tong' => 100000 );
	$wpdb->bcDong[] = array( 'coso' => $k[0], 'coso_key' => $k[0], 'ng' => 9, 'tong' => 200000 );
}
/* Anh Thắng 23/09/2026: *"xuất MISA nếu tháng đó phát sinh doanh thu, không phát sinh thì không
   hiện"*. Cơ sở này CÓ trong danh mục Unit ID (đã đóng cửa, hay chỉ là chưa có báo cáo) nhưng KHÔNG
   có một dòng tiền nào trong tháng → không được xuất hiện. Báo cáo ngày đi từ dòng tiền, không đi
   từ danh mục — phép này giữ đúng điều đó. */
$wpdb->maMisa[] = array( 'coso_key' => 'DA DONG CUA', 'unit_id' => '99DONG', 'unit_name' => 'DA DONG CUA', 'vung' => 'CA MAU', 'thu_tu' => 0 );
$r = VHG_KeToan::baocao_ngay( '2026-09', 0 );
$aoa = $r['aoa'];
$co_dong = false; foreach ( $aoa as $row ) { if ( '99DONG' === (string) $row[0] || 'DA DONG CUA' === (string) $row[1] ) { $co_dong = true; } }
t( '🔴 cơ sở có trong danh mục nhưng KHÔNG có doanh thu tháng đó → KHÔNG hiện trong Báo cáo ngày MISA', ! $co_dong );
/* Chỉ lấy khối VND (khối đầu), cột 0 — đủ soi thứ tự và chỗ ngắt tỉnh. */
$cotA = array();
foreach ( $aoa as $row ) {
	if ( 'DAILY REPORT' === $row[0] && 'SGD' === $row[1] ) { break; }
	$cotA[] = array( (string) $row[0], (string) $row[1] );
}
$thu_tu = array();
foreach ( $cotA as $x ) {
	if ( in_array( $x[0], array( 'DAILY REPORT', 'Unit ID', 'TỔNG' ), true ) ) { continue; }
	if ( '' === $x[0] && '' === $x[1] ) { continue; }   // dòng trống ngăn khối VND / SGD
	$thu_tu[] = ( 'CỘNG' === $x[0] ) ? ( 'CỘNG ' . $x[1] ) : ( '' !== $x[0] ? $x[0] : '[' . $x[1] . ']' );
}
/* Dòng tỉnh nằm ở CỘT Unit ID với cột tên trống → hiện ra dạng tên tỉnh trần. */
$mong = array(
	'HO CHI MINH', '58GOTV', 'CỘNG HO CHI MINH',
	'CAN THO', '59CGVXK', '59GOCT', '59SCCT', 'CỘNG CAN THO',
	'BUON ME THUOT', '60GODL', 'CỘNG BUON ME THUOT',
	'KIEN GIANG', '61ZCKG', 'CỘNG KIEN GIANG',
	'CA MAU', '62GOBL', '62SCCM', 'CỘNG CA MAU',
	'(chưa khai Unit ID)', '[CHUA KHAI UNIT]', 'CỘNG (chưa khai Unit ID)',
);
t( '🔴 tỉnh xếp theo SỐ đầu Unit ID (CAN THO 59 trước CA MAU 62), Unit trong tỉnh theo A→Z, '
	. 'mỗi tỉnh một dòng mở + một dòng CỘNG, thiếu Unit ID dồn cuối',
	$thu_tu === $mong, $thu_tu );
t( 'một cơ sở khai Vùng là cả tỉnh có tên (59GOCT/59CGVXK bỏ trống vẫn nằm dưới "CAN THO")',
	in_array( 'CAN THO', $thu_tu, true ) && ! in_array( 'Nhóm 59', $thu_tu, true ) );

/* Dòng CỘNG phải ĐÚNG BẰNG tổng các dòng Unit của tỉnh đó — đây là tiền, không phải nhãn. */
$cot_tong = null;
foreach ( $aoa as $row ) { if ( 'Unit ID' === $row[0] ) { $cot_tong = array_search( 'Total', $row, true ); break; } }
t( 'tìm được cột Total', null !== $cot_tong && $cot_tong > 1, $cot_tong );
$dang = 0; $ok_cong = true; $soCong = 0;
foreach ( $aoa as $row ) {
	if ( 'DAILY REPORT' === $row[0] && 'SGD' === $row[1] ) { break; }
	if ( 'CỘNG' === $row[0] ) { $soCong++; if ( (int) $row[ $cot_tong ] !== $dang ) { $ok_cong = false; } $dang = 0; continue; }
	/* Dòng Unit = có TÊN ở cột B. Dòng mở tỉnh để trống cột B, dòng trống ngăn khối cũng vậy.
	   ⚠️ KHÔNG lọc theo "có Unit ID ở cột A": cơ sở chưa khai Unit ID để trống cột ấy mà tiền
	      của nó vẫn phải vào dòng CỘNG — lọc kiểu đó là bài kiểm tự bỏ sót đúng ca dễ sai nhất. */
	if ( '' !== (string) $row[1] && ! in_array( $row[0], array( 'DAILY REPORT', 'Unit ID', 'TỔNG' ), true ) ) {
		$dang += (int) $row[ $cot_tong ];
	}
}
t( '🔴 mỗi dòng CỘNG đúng bằng tổng các Unit của tỉnh ấy', $ok_cong && 6 === $soCong, array( 'ok' => $ok_cong, 'soCong' => $soCong ) );
t( 'TỔNG cuối bảng không đổi (9 cơ sở × 300.000)', 9 * 300000 === (int) $r['tong'], $r['tong'] );
t( 'đếm đúng số tỉnh + kêu tỉnh chưa đặt tên', 6 === (int) $r['soTinh'] && array() === $r['thieuVung'],
	array( $r['soTinh'], $r['thieuVung'] ) );

/* Chưa khai Vùng cho tỉnh nào thì in "Nhóm <số>" và PHẢI kêu lên. */
$wpdb->maMisa = array( array( 'coso_key' => 'A', 'unit_id' => '77XX', 'unit_name' => 'A', 'vung' => '', 'thu_tu' => 0 ) );
$wpdb->bcDong = array( array( 'coso' => 'A', 'coso_key' => 'A', 'ng' => 6, 'tong' => 5000 ) );
$r2 = VHG_KeToan::baocao_ngay( '2026-09', 0 );
$co_nhom = false;
foreach ( $r2['aoa'] as $row ) { if ( 'Nhóm 77' === $row[0] ) { $co_nhom = true; } }
t( 'tỉnh chưa đặt tên in "Nhóm <số>" và báo ra thieuVung', $co_nhom && array( 77 ) === $r2['thieuVung'],
	array( $co_nhom, $r2['thieuVung'] ) );

/* ══════════════════════════════════ 2. CHỨNG TỪ: mã đối tượng ═══════════════════════════════ */
echo "── Chứng từ MISA: mã đối tượng = mã khách hàng ──────────────\n";
/* Nguồn mã khách hàng là bảng `coso`, ghép bằng squash(tên) — KHÔNG phải bảng Unit ID.
   Tên ở đây cố tình viết thường + có dấu để bài kiểm chạm đúng chỗ ghép: `bc.coso_key` là bản đã
   squash, `coso.ten` là tên đang hiển thị. Ghép thẳng hai chuỗi tên là hụt ngay. */
$wpdb->maMisa = array();
$wpdb->coso = array(
	array( 'ten' => 'Gò Cần Thơ',    'ma_kh' => 'KH00059' ),
	array( 'ten' => 'Vạn Hạnh Mall', 'ma_kh' => 'KH00088' ),
	array( 'ten' => 'Chưa khai',     'ma_kh' => '' ),
);
$wpdb->ctRows = array(
	array( 'ngay' => '2026-09-01', 'ma_may' => '80016', 'ten' => 'GO-CT-1', 'tien_mat' => 100000, 'qr' => 50000,
		'dieu_chinh' => 0, 'ghi_chu' => '', 'nop_trang_thai' => '', 'coso' => 'Gò Cần Thơ', 'coso_key' => 'GOCANTHO' ),
	array( 'ngay' => '2026-09-01', 'ma_may' => '80038', 'ten' => 'VHM-9', 'tien_mat' => 200000, 'qr' => 0,
		'dieu_chinh' => 0, 'ghi_chu' => '', 'nop_trang_thai' => '', 'coso' => 'Vạn Hạnh Mall', 'coso_key' => 'VANHANHMALL' ),
	array( 'ngay' => '2026-09-01', 'ma_may' => '80099', 'ten' => 'XX-1', 'tien_mat' => 30000, 'qr' => 0,
		'dieu_chinh' => 0, 'ghi_chu' => '', 'nop_trang_thai' => '', 'coso' => 'Chưa khai', 'coso_key' => 'CHUAKHAI' ),
);
$c = VHG_KeToan::misa_chungtu( '', '', '2026-09', 0, '' );
$head = $c['aoa'][0];
$iMa  = array_search( 'Mã đối tượng Nợ', $head, true );
$iTen = array_search( 'Tên đối tượng nợ', $head, true );
t( 'bảng có cột Mã đối tượng Nợ + Tên đối tượng nợ', false !== $iMa && false !== $iTen, array( $iMa, $iTen ) );
$d1 = $c['aoa'][1]; $d3 = $c['aoa'][3]; $d4 = $c['aoa'][4];
t( '🔴 dòng tiền mặt mang MÃ KH CỦA MÀN ĐỊA ĐIỂM (coso.ma_kh), ghép qua squash(tên)',
	'KH00059' === $d1[ $iMa ], $d1[ $iMa ] );
t( '🔴 dòng QR cùng cơ sở cũng mang mã ấy (không chỉ dòng đầu)', 'KH00059' === $c['aoa'][2][ $iMa ], $c['aoa'][2][ $iMa ] );
t( 'cơ sở khác mang mã khác — không dính mã của dòng trước', 'KH00088' === $d3[ $iMa ], $d3[ $iMa ] );
t( 'tên đối tượng = tên cơ sở (nhãn cho người đọc, không cần ô khai riêng)',
	'Vạn Hạnh Mall' === $d3[ $iTen ], $d3[ $iTen ] );
t( '🔴 KHÔNG đoán mã cho cơ sở chưa khai — để trắng cả mã lẫn tên',
	'' === $d4[ $iMa ] && '' === $d4[ $iTen ], array( $d4[ $iMa ], $d4[ $iTen ] ) );
t( '🔴 và KÊU LÊN đúng cơ sở nào thiếu (trắng âm thầm là sổ công nợ hụt mà không ai biết)',
	array( 'Chưa khai' ) === $c['thieuDoiTuong'], $c['thieuDoiTuong'] );

/* 🔴 MỘT NƠI KHAI DUY NHẤT. Bài kiểm canh cả việc KHÔNG có ô khai thứ hai — đây là loại hỏng
   chỉ lộ ra sau vài tháng, lúc hai con số đã lệch và không ai nhớ ô nào mới đúng. */
$src_kt = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
$src_db = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-db.php' );
$src_tr = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-trang.php' );
t( '🔴 KHÔNG đẻ ô khai mã đối tượng thứ hai ở bảng Unit ID (nguồn duy nhất là coso.ma_kh)',
	false === strpos( $src_kt, 'doi_tuong' ) && false === strpos( $src_db, 'doi_tuong' )
	&& false === strpos( $src_tr, 'doi_tuong' ) );
t( 'và đọc thẳng cột ma_kh của bảng coso', false !== strpos( $src_kt, "SELECT ten, ma_kh FROM" ) );
t( 'Mã đơn vị / Tên đơn vị vẫn là mã & tên GHẾ như cũ (không đụng)',
	'80016' === $d1[ array_search( 'Mã đơn vị', $head, true ) ]
	&& 'GO-CT-1' === $d1[ array_search( 'Tên đơn vị', $head, true ) ] );
t( 'số tiền không đổi', 100000 === $d1[ array_search( 'Số tiền', $head, true ) ] );

/* ══════════════════════════════════ 3. QR = TIỀN THỰC VỀ NGÂN HÀNG ══════════════════════════ */
echo "── Chứng từ MISA: QR lấy số thực về ngân hàng ───────────────\n";
/* Anh Thắng 22/09/2026: *"chỗ xuất QR lấy theo số thực tức QR số màu đỏ"*.
   `bc_dong.qr` là số nhân viên ĐỌC TRÊN MÁY — lệch thật, trong ảnh anh gửi có cơ sở lệch cả chục
   triệu. Đưa số đọc-trên-máy vào sổ kế toán là ghi doanh thu theo con số không ai chuyển tiền theo. */

/* -- 3a. Phép chia: tổng các phần phải ĐÚNG BẰNG số thực, và chạy lại ra y nguyên -- */
$chia = VHG_KeToan::chia_ty_le_( 1000000, array( 'C' => 3, 'A' => 1, 'B' => 1 ) );
t( '🔴 chia tỉ lệ: tổng các phần ĐÚNG BẰNG số thực (không bốc hơi đồng nào)',
	1000000 === array_sum( $chia ), $chia );
t( 'phần dư rơi vào ghế trọng số lớn nhất', $chia['C'] > $chia['A'], $chia );
t( 'ổn định — chạy lại ra y nguyên',
	$chia === VHG_KeToan::chia_ty_le_( 1000000, array( 'A' => 1, 'B' => 1, 'C' => 3 ) ), $chia );
$deu = VHG_KeToan::chia_ty_le_( 100, array( 'A' => 0, 'B' => 0, 'C' => 0 ) );
t( '🔴 trọng số toàn 0 (ngân hàng có tiền mà không có gì để chia theo) → chia ĐỀU, vẫn đủ tổng',
	100 === array_sum( $deu ) && 34 === $deu['A'] && 33 === $deu['B'], $deu );
$le = VHG_KeToan::chia_ty_le_( 7, array( 'A' => 1, 'B' => 1, 'C' => 1 ) );
t( 'số lẻ chia ba vẫn đủ tổng', 7 === array_sum( $le ), $le );

/* -- 3b. Chạy thật: có sao kê → dòng QR mang số THỰC, chia theo tỉ lệ số nhân viên nhập -- */
class SAOKE_App {
	public static $vq = array();
	public static function vietqr_theo_coso_ngay( $tu, $den ) { return array( 'co' => true, 'vq' => self::$vq, 'khongKhop' => 0 ); }
}
SAOKE_App::$vq = array( 'Gò Cần Thơ' => array( '2026-09-01' => 900000 ) );   // thực 900k
$wpdb->ctRows = array(
	/* Nhân viên nhập 100k + 200k = 300k; ngân hàng về 900k. Tỉ lệ 1:2 → 300k / 600k. */
	array( 'ngay' => '2026-09-01', 'ma_may' => 'A1', 'ten' => 'GO-CT-1', 'tien_mat' => 10000, 'qr' => 100000,
		'dieu_chinh' => 0, 'ghi_chu' => '', 'nop_trang_thai' => '', 'coso' => 'Gò Cần Thơ', 'coso_key' => 'GOCANTHO' ),
	array( 'ngay' => '2026-09-01', 'ma_may' => 'A2', 'ten' => 'GO-CT-2', 'tien_mat' => 20000, 'qr' => 200000,
		'dieu_chinh' => 0, 'ghi_chu' => '', 'nop_trang_thai' => '', 'coso' => 'Gò Cần Thơ', 'coso_key' => 'GOCANTHO' ),
	/* Cơ sở-ngày KHÔNG có trong sao kê → phải GIỮ số nhân viên nhập, không được lấy 0. */
	array( 'ngay' => '2026-09-01', 'ma_may' => 'B1', 'ten' => 'VHM-9', 'tien_mat' => 0, 'qr' => 55000,
		'dieu_chinh' => 0, 'ghi_chu' => '', 'nop_trang_thai' => '', 'coso' => 'Vạn Hạnh Mall', 'coso_key' => 'VANHANHMALL' ),
);
$q = VHG_KeToan::misa_chungtu( '', '', '2026-09', 0, '' );
$iTien = array_search( 'Số tiền', $head, true );
$iDon  = array_search( 'Mã đơn vị', $head, true );
$iGc   = array_search( 'Ghi chú', $head, true );
$qrDong = array();
foreach ( $q['aoa'] as $k => $row ) {
	if ( 0 === $k ) { continue; }
	if ( 'QR ngân hàng' === $row[ $iGc ] ) { $qrDong[ (string) $row[ $iDon ] ] = (int) $row[ $iTien ]; }
}
t( '🔴 dòng QR mang SỐ THỰC về ngân hàng, chia theo tỉ lệ số nhân viên nhập (100k:200k → 300k:600k)',
	array( 'A1' => 300000, 'A2' => 600000, 'B1' => 55000 ) === $qrDong, $qrDong );
t( '🔴 tổng QR xuất ra của cơ sở có sao kê ĐÚNG BẰNG số về ngân hàng (900.000)',
	900000 === $qrDong['A1'] + $qrDong['A2'] );
t( '🔴 cơ sở-ngày CHƯA có sao kê thì GIỮ số nhân viên nhập — KHÔNG lấy 0 (lấy 0 là xoá trắng '
	. 'doanh thu QR của ngày ấy khỏi sổ, im lặng)', 55000 === $qrDong['B1'], $qrDong );
t( '🔴 và KÊU LÊN đúng cơ sở-ngày nào chưa có sao kê',
	array( 'Vạn Hạnh Mall · 2026-09-01' ) === $q['qrChuaCoSaoKe'], $q['qrChuaCoSaoKe'] );
t( 'dòng TIỀN MẶT không bị đụng tới', 10000 === (int) $q['aoa'][1][ $iTien ], $q['aoa'][1][ $iTien ] );
t( 'báo lại số liệu đối chiếu (thực / nhân viên nhập)',
	900000 === (int) $q['qrThucTong'] && 355000 === (int) $q['qrNhapTong'], array( $q['qrThucTong'], $q['qrNhapTong'] ) );

/* -- 3c. Tắt cờ → dùng thẳng số nhân viên nhập (đường thoát khi sao kê chưa về) -- */
$q2 = VHG_KeToan::misa_chungtu( '', '', '2026-09', 0, '', false );
$qr2 = array();
foreach ( $q2['aoa'] as $k => $row ) {
	if ( 0 === $k ) { continue; }
	if ( 'QR ngân hàng' === $row[ $iGc ] ) { $qr2[ (string) $row[ $iDon ] ] = (int) $row[ $iTien ]; }
}
t( 'tắt cờ QR-thực → về đúng số nhân viên nhập',
	array( 'A1' => 100000, 'A2' => 200000, 'B1' => 55000 ) === $qr2, $qr2 );

/* -- 3d. Ngân hàng về 0 cho một ghế → BỎ dòng, không viết dòng 0đ vào sổ -- */
SAOKE_App::$vq = array( 'Gò Cần Thơ' => array( '2026-09-01' => 1 ) );   // 1đ, chia ra A1=0 A2=1
$q3 = VHG_KeToan::misa_chungtu( '', '', '2026-09', 0, '' );
$so0 = 0;
foreach ( $q3['aoa'] as $k => $row ) { if ( $k && 'QR ngân hàng' === $row[ $iGc ] && 0 === (int) $row[ $iTien ] ) { $so0++; } }
t( 'chia ra 0đ thì BỎ HẲN DÒNG (không viết dòng 0đ vào sổ)', 0 === $so0, $so0 );

echo "\n";
if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . '/' . ( $DAT + count( $TRUOT ) ) . "\n"; exit( 1 ); }
echo "✓ SẠCH — $DAT phép\n";
