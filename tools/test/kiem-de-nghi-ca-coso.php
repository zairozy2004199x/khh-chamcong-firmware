<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐỀ NGHỊ XOÁ / ĐẶT LẠI CHỈ SỐ CHO CẢ MỘT CƠ SỞ
 *
 * Anh Thắng 08/09/2026: *"một số cơ sở sau thu tiền thì phải xóa chỉ số, thì lệnh này có chọn
 * để kế toán xóa chỉ số được không"*, rồi *"xóa theo từng máy hoặc xóa theo nguyên cơ sở, chọn
 * được"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI CÓ. Cơ sở PHÚ QUỐC có 20 ghế. Nơi nào reset bộ đếm sau mỗi lần thu mà phải gửi
 *    từng ghế thì đó là 20 lượt gửi VÀ 20 lượt kế toán bấm duyệt, mỗi kỳ. Không ai làm nổi, nên
 *    rồi họ thôi gửi — và chỉ số cứ thế sai, tiền tính theo chỉ số sai.
 *
 * 🔴 MỘT DÒNG ĐỀ NGHỊ, TRẢI RA LÚC DUYỆT. Sinh sẵn 20 dòng lúc gửi thì kế toán vẫn phải bấm 20
 *    lần — y như chưa làm gì. Dòng ấy mang `ma_may = '*'`; lúc duyệt mới đọc danh sách ghế của
 *    cơ sở và đặt mốc cho từng con.
 *
 * ⚠️ CHẠY THẬT: dựng cơ sở + ghế, gửi đề nghị, duyệt, rồi đòi đúng mốc trên từng ghế.
 *
 * Chạy: php tools/test/kiem-de-nghi-ca-coso.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$GOC = dirname( dirname( __DIR__ ) );
$BC  = file_get_contents( $GOC . '/vhcp-ghe/includes/class-vhg-baocao.php' );
$KT  = file_get_contents( $GOC . '/vhcp-ghe/includes/class-vhg-ketoan.php' );
$TR  = file_get_contents( $GOC . '/vhcp-ghe/includes/class-vhg-trang.php' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BỆ ĐỠ — CSDL giả đủ cho hai hàm này, không hơn
 *
 * ⚠️ CHỖ MÙ, ghi rõ: đây KHÔNG phải MySQL. Nó kiểm được LUẬT (ai được gửi, gửi rồi trải ra mấy
 *    ghế, mốc đặt bằng bao nhiêu, chặn trùng ra sao). Nó KHÔNG kiểm được cú pháp SQL thật hay
 *    hành vi của `%s` với ký tự lạ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* `squash()` của plugin gọi `remove_accents()` của WordPress. Bản giả đủ cho tiếng Việt — đây
   là chỗ mù đã biết: nó không phủ hết bảng ký tự WordPress bỏ dấu, nhưng bài này chỉ so tên cơ
   sở tiếng Việt với nhau nên vừa đủ. */
define( 'ARRAY_A', 'ARRAY_A' );
function remove_accents( $s ) {
	$b = array(
		'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
		'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
	);
	foreach ( $b as $th => $ds ) {
		foreach ( preg_split( '//u', $ds, -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
			$s = str_replace( array( $c, mb_strtoupper( $c ) ), array( $th, mb_strtoupper( $th ) ), $s );
		}
	}
	return $s;
}
function current_time( $f ) { return 'mysql' === $f ? '2026-09-08 20:00:00' : ( 'timestamp' === $f ? 1789000000 : '20260908200000' ); }
function mb_substr_x( $s, $a, $b ) { return mb_substr( $s, $a, $b ); }

class DB {
	public static $may = array();          // ma => ['moc_chiso'=>?, 'moc_chiso_ngay'=>?]
	public static $denghi = array();       // id => dòng
	public static $dong = array();         // [ma_may, ngay]
	public static function reset() { self::$may = array(); self::$denghi = array(); self::$dong = array(); }
}
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }

class FakeWpdb {
	public function prepare( $sql, ...$a ) { return array( 'sql' => $sql, 'a' => $a ); }
	public function get_var( $q ) {
		$sql = $q['sql']; $a = $q['a'];
		if ( false !== strpos( $sql, 'COUNT(*)' ) && false !== strpos( $sql, 'bc_denghi' ) ) {
			$n = 0;
			foreach ( DB::$denghi as $d ) {
				if ( false !== strpos( $sql, 'coso=%s' ) ) {
					if ( $d['ma_may'] === $a[0] && $d['coso'] === $a[1] && $d['tu_ngay'] === $a[2] && $d['trang_thai'] === $a[3] ) { $n++; }
				} elseif ( $d['ma_may'] === $a[0] && $d['tu_ngay'] === $a[1] && $d['trang_thai'] === $a[2] ) { $n++; }
			}
			return $n;
		}
		if ( false !== strpos( $sql, 'COUNT(*)' ) && false !== strpos( $sql, 'bc_dong' ) ) {
			$n = 0;
			foreach ( DB::$dong as $x ) { if ( $x[0] === $a[0] && $x[1] >= $a[1] ) { $n++; } }
			return $n;
		}
		if ( false !== strpos( $sql, 'SELECT ma FROM' ) ) {
			return isset( DB::$may[ $a[0] ] ) ? $a[0] : null;
		}
		return null;
	}
	public function get_row( $q, $out = null ) {
		$sql = $q['sql']; $a = $q['a'];
		if ( false !== strpos( $sql, 'bc_denghi' ) ) {
			return isset( DB::$denghi[ $a[0] ] ) ? DB::$denghi[ $a[0] ] : null;
		}
		return null;
	}
	public function insert( $bang, $d ) {
		if ( false !== strpos( $bang, 'bc_denghi' ) ) { DB::$denghi[ $d['id'] ] = $d; }
		return 1;
	}
	public function update( $bang, $d, $w ) {
		if ( false !== strpos( $bang, 'bc_denghi' ) ) {
			if ( isset( DB::$denghi[ $w['id'] ] ) ) { DB::$denghi[ $w['id'] ] = array_merge( DB::$denghi[ $w['id'] ], $d ); }
		} elseif ( false !== strpos( $bang, '_may' ) || preg_match( '/vhg_may$/', $bang ) ) {
			if ( isset( DB::$may[ $w['ma'] ] ) ) { DB::$may[ $w['ma'] ] = array_merge( DB::$may[ $w['ma'] ], $d ); }
		}
		return 1;
	}
}
$GLOBALS['wpdb'] = new FakeWpdb();

/* Danh mục ghế giả — `ds_may()` là cửa duy nhất cả hai lớp đọc tới. */
class VHG_May {
	public static $ds = array();
	public static function ds_may() { return self::$ds; }
}

/* Bốc THẬT hai lớp cần dùng, chỉ giữ phần liên quan. */
function boc_lop( $src, $ten_lop, $ham ) {
	$ra = '';
	foreach ( $ham as $h ) {
		$i = strpos( $src, 'function ' . $h . '(' );
		if ( false === $i ) { return ''; }
		$i = strrpos( substr( $src, 0, $i ), "\n\t" ) + 1;
		$j = strpos( $src, "\n\t}", $i );
		$ra .= substr( $src, $i, $j - $i + 3 ) . "\n";
	}
	return $ra;
}
/* Mấy hàm phụ mà `denghi_gui` gọi tới — bốc y nguyên, không chép lại luật. */
$phu = array( 'ngay_', 'squash', 'trong_pham_vi', 'ghe_cua_coso' );
$than_bc = boc_lop( $BC, 'VHG_BaoCao', array_merge( $phu, array( 'denghi_gui' ) ) );
t( 'bốc được các hàm của VHG_BaoCao', '' !== $than_bc && false !== strpos( $than_bc, 'denghi_gui' ) );
$ca_coso = '';
if ( preg_match( "/const CA_COSO = '([^']+)';/", $BC, $mm ) ) { $ca_coso = $mm[1]; }
teq( 'hằng "cả cơ sở" bốc được', '*', $ca_coso );
eval( 'class VHG_BaoCao { const CA_COSO = ' . var_export( $ca_coso, true ) . ';'
	. ' public static $Q = null;'
	. ' public static function pin_info( $p ) { return self::$Q; }'
	. $than_bc . ' }' );

$than_kt = boc_lop( $KT, 'VHG_KeToan', array( 'ngay_', 'denghi_duyet' ) );
t( 'bốc được denghi_duyet của VHG_KeToan', false !== strpos( $than_kt, 'denghi_duyet' ) );
eval( 'class VHG_KeToan { ' . $than_kt . ' }' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. SÂN: cơ sở PHÚ QUỐC 3 ghế, cơ sở khác 1 ghế
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function dung_san() {
	DB::reset();
	VHG_May::$ds = array(
		array( 'ma' => 'VP-PQ-1', 'ten_khai' => 'Ghế 1', 'coso_ten' => 'VP PHÚ QUỐC', 'an' => 0 ),
		array( 'ma' => 'VP-PQ-2', 'ten_khai' => 'Ghế 2', 'coso_ten' => 'VP PHÚ QUỐC', 'an' => 0 ),
		array( 'ma' => 'VP-PQ-3', 'ten_khai' => 'Ghế 3', 'coso_ten' => 'VP PHÚ QUỐC', 'an' => 0 ),
		array( 'ma' => 'VP-PQ-9', 'ten_khai' => 'Đã dọn', 'coso_ten' => 'VP PHÚ QUỐC', 'an' => 1 ),
		array( 'ma' => 'AEON-1',  'ten_khai' => 'Aeon 1', 'coso_ten' => 'AEON TÂN PHÚ', 'an' => 0 ),
	);
	foreach ( VHG_May::$ds as $m ) { DB::$may[ $m['ma'] ] = array( 'moc_chiso' => null, 'moc_chiso_ngay' => null ); }
	VHG_BaoCao::$Q = array( 'ten' => 'Ngọc Lan', 'coso_key' => array(), 'ghe' => array() );   // PIN toàn quyền
}
dung_san();

teq( '🔴 liệt kê ghế của cơ sở — BỎ ghế đã dọn',
	array( 'VP-PQ-1', 'VP-PQ-2', 'VP-PQ-3' ), VHG_BaoCao::ghe_cua_coso( 'VP PHÚ QUỐC' ) );
teq( 'tên cơ sở khác hoa thường / thừa dấu cách vẫn khớp',
	array( 'VP-PQ-1', 'VP-PQ-2', 'VP-PQ-3' ), VHG_BaoCao::ghe_cua_coso( '  vp phú quốc ' ) );
teq( 'cơ sở không có thật → rỗng', array(), VHG_BaoCao::ghe_cua_coso( 'KHÔNG CÓ' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 GỬI MỘT ĐỀ NGHỊ CHO CẢ CƠ SỞ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$r = VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'thu tiền xong reset máy' ), 'PIN' );
t( '🔴 gửi được đề nghị cho cả cơ sở', ! empty( $r['ok'] ), $r );
teq( '🔴 và chỉ sinh ĐÚNG MỘT dòng (không phải 3)', 1, count( DB::$denghi ) );
$dn = array_values( DB::$denghi )[0];
teq( 'dòng ấy mang dấu cả-cơ-sở', '*', $dn['ma_may'] );
teq( 'kèm tên cơ sở để lúc duyệt còn trải ra', 'VP PHÚ QUỐC', $dn['coso'] );
t( 'nhãn nói rõ là cả cơ sở, kèm số ghế', false !== mb_strpos( $dn['ten'], 'CẢ CƠ SỞ' ) && false !== mb_strpos( $dn['ten'], '3' ), $dn['ten'] );
teq( 'đang chờ duyệt', 'cho_duyet', $dn['trang_thai'] );
teq( 'loại là xoá', 'xoa', $dn['loai'] );

/* Vẫn phải đòi lý do — đây là việc đụng tới cả chục ghế. */
$r0 = VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => '  ' ), 'PIN' );
t( '🔴 thiếu lý do → chối', empty( $r0['ok'] ), $r0 );
$r0b = VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => '',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'x' ), 'PIN' );
t( '🔴 chọn cả cơ sở mà không gửi kèm tên cơ sở → chối', empty( $r0b['ok'] ), $r0b );
/* 🔴 VÀ PHẢI NÓI ĐÚNG CHUYỆN. Bỏ chốt "thiếu tên cơ sở" đi thì lượt ấy vẫn bị chối — nhưng chối
   bằng câu "cơ sở … chưa có ghế nào", một câu sai hẳn nguyên nhân. Người đọc đi tìm ghế trong
   khi lỗi nằm ở chỗ khác. Phá thử chỉ ra đúng lỗ này. */
t( '   và nói ĐÚNG là thiếu tên cơ sở, không đổ cho "chưa có ghế"',
	false !== mb_strpos( (string) $r0b['message'], 'Thiếu tên cơ sở' ), $r0b );
$r0c = VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'KHÔNG CÓ',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'x' ), 'PIN' );
t( '🔴 cơ sở chưa có ghế nào → chối, không tạo dòng rỗng', empty( $r0c['ok'] ), $r0c );

/* Chặn trùng theo CƠ SỞ — không thì bấm mấy lần là mấy dòng chờ duyệt. */
$r2 = VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'gửi lại' ), 'PIN' );
t( '🔴 gửi lại cùng cơ sở cùng ngày → chối', empty( $r2['ok'] ), $r2 );
teq( '   và vẫn chỉ một dòng', 1, count( DB::$denghi ) );
/* Nhưng cơ sở KHÁC thì không liên quan. */
$r3 = VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'AEON TÂN PHÚ',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'reset' ), 'PIN' );
t( 'cơ sở khác cùng ngày → vẫn gửi được', ! empty( $r3['ok'] ), $r3 );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 KẾ TOÁN DUYỆT MỘT LẦN → CẢ CƠ SỞ NHẬN MỐC
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
dung_san();
VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'reset sau thu' ), 'PIN' );
$id = array_keys( DB::$denghi )[0];
$rd = VHG_KeToan::denghi_duyet( $id, 'ok', 'Kế toán A' );
t( '🔴 duyệt được', ! empty( $rd['ok'] ), $rd );
foreach ( array( 'VP-PQ-1', 'VP-PQ-2', 'VP-PQ-3' ) as $mx ) {
	teq( '🔴 ghế ' . $mx . ' nhận mốc 0', 0, DB::$may[ $mx ]['moc_chiso'] );
	teq( '   kể từ đúng ngày áp dụng', '2026-09-08', DB::$may[ $mx ]['moc_chiso_ngay'] );
}
teq( '🔴 ghế ĐÃ DỌN không bị đụng', null, DB::$may['VP-PQ-9']['moc_chiso'] );
teq( '🔴 ghế cơ sở KHÁC không bị đụng', null, DB::$may['AEON-1']['moc_chiso'] );
teq( 'đề nghị chuyển sang đã duyệt', 'duyet', DB::$denghi[ $id ]['trang_thai'] );
teq( 'ghi lại ai duyệt', 'Kế toán A', DB::$denghi[ $id ]['duyet_boi'] );
t( 'câu báo nói rõ CẢ CƠ SỞ và số ghế',
	false !== mb_strpos( $rd['message'], 'CẢ CƠ SỞ' ) && false !== mb_strpos( $rd['message'], '3' ), $rd['message'] );
/* Duyệt lần hai phải chối — không thì bấm đúp là đặt mốc hai lượt. */
$rd2 = VHG_KeToan::denghi_duyet( $id, '', 'Kế toán A' );
t( '🔴 duyệt lại lần hai → chối', empty( $rd2['ok'] ), $rd2 );

/* 🔴 GIỮA LÚC GỬI VÀ LÚC DUYỆT, CƠ SỞ CÓ THỂ ĐÃ DỌN HẾT GHẾ. Duyệt bừa lúc ấy là đánh dấu "đã
   duyệt" cho một việc không đặt được mốc nào — kế toán tin là xong, mà chẳng có gì xảy ra. */
dung_san();
VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'reset' ), 'PIN' );
$idX = array_keys( DB::$denghi )[0];
foreach ( VHG_May::$ds as $i => $m ) { if ( 'VP PHÚ QUỐC' === $m['coso_ten'] ) { VHG_May::$ds[ $i ]['an'] = 1; } }
$rx = VHG_KeToan::denghi_duyet( $idX, '', 'KT' );
t( '🔴 cơ sở đã dọn hết ghế → duyệt bị chối', empty( $rx['ok'] ), $rx );
teq( '   và đề nghị VẪN đang chờ, không bị đánh dấu đã duyệt', 'cho_duyet', DB::$denghi[ $idX ]['trang_thai'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. ĐẶT LẠI MỘT SỐ CỤ THỂ CHO CẢ CƠ SỞ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
dung_san();
VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC', 'fromDate' => '2026-09-10',
	'loai' => 'dat_lai', 'meterOpening' => '1240', 'lyDo' => 'thay loạt máy' ), 'PIN' );
$id2 = array_keys( DB::$denghi )[0];
VHG_KeToan::denghi_duyet( $id2, '', 'KT' );
teq( '🔴 cả cơ sở nhận đúng số đề nghị · ghế 1', 1240, DB::$may['VP-PQ-1']['moc_chiso'] );
teq( '   · ghế 3', 1240, DB::$may['VP-PQ-3']['moc_chiso'] );
teq( '   từ đúng ngày', '2026-09-10', DB::$may['VP-PQ-2']['moc_chiso_ngay'] );
/* Loại "đặt lại" mà không nhập số thì chối — 0 và "bỏ trống" là hai chuyện. */
dung_san();
$r4 = VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC',
	'fromDate' => '2026-09-10', 'loai' => 'dat_lai', 'meterOpening' => '', 'lyDo' => 'x' ), 'PIN' );
t( '🔴 đặt lại mà bỏ trống chỉ số → chối', empty( $r4['ok'] ), $r4 );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. CẢNH BÁO KHI ĐÃ CÓ BẢN GHI TỪ NGÀY ẤY TRỞ ĐI
 *
 * 🔴 Mốc mới KHÔNG áp ngược cho báo cáo đã nộp. Im lặng chuyện đó là kế toán duyệt xong tưởng
 *    đã xong, mà mấy bản ghi cũ vẫn mang chỉ số trước kiểu cũ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
dung_san();
DB::$dong[] = array( 'VP-PQ-2', '2026-09-09' );
DB::$dong[] = array( 'VP-PQ-3', '2026-09-11' );
VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'reset' ), 'PIN' );
$id3 = array_keys( DB::$denghi )[0];
$rd3 = VHG_KeToan::denghi_duyet( $id3, '', 'KT' );
t( '🔴 có bản ghi cũ → cảnh báo', '' !== (string) $rd3['canhBao'], $rd3 );
t( '   kể đích danh ghế nào', false !== mb_strpos( $rd3['canhBao'], 'VP-PQ-2' )
	&& false !== mb_strpos( $rd3['canhBao'], 'VP-PQ-3' ), $rd3['canhBao'] );
t( '   không kể oan ghế sạch', false === mb_strpos( $rd3['canhBao'], 'VP-PQ-1' ), $rd3['canhBao'] );
/* Không có bản ghi nào thì đừng doạ. */
dung_san();
VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'reset' ), 'PIN' );
$rd4 = VHG_KeToan::denghi_duyet( array_keys( DB::$denghi )[0], '', 'KT' );
teq( 'kho sạch → không cảnh báo', '', (string) $rd4['canhBao'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. GỬI TỪNG GHẾ VẪN CHẠY Y NHƯ CŨ
 *
 * 🔴 Đối chứng bắt buộc: bản này THÊM một lối, không được đổi lối đang dùng.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
dung_san();
$r5 = VHG_BaoCao::denghi_gui( array( 'chairCode' => 'VP-PQ-2', 'fromDate' => '2026-09-08',
	'loai' => 'dat_lai', 'meterOpening' => '55', 'lyDo' => 'gõ sai' ), 'PIN' );
t( 'gửi một ghế → được', ! empty( $r5['ok'] ), $r5 );
VHG_KeToan::denghi_duyet( array_keys( DB::$denghi )[0], '', 'KT' );
teq( '🔴 chỉ ghế ấy nhận mốc', 55, DB::$may['VP-PQ-2']['moc_chiso'] );
teq( '   ghế cùng cơ sở KHÔNG bị đụng', null, DB::$may['VP-PQ-1']['moc_chiso'] );
$r6 = VHG_BaoCao::denghi_gui( array( 'chairCode' => 'KHÔNG-CÓ', 'fromDate' => '2026-09-08',
	'loai' => 'xoa', 'lyDo' => 'x' ), 'PIN' );
t( 'ghế không có thật → chối', empty( $r6['ok'] ), $r6 );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 7. PHẠM VI PIN — không được gửi cho cơ sở của người khác
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
dung_san();
VHG_BaoCao::$Q = array( 'ten' => 'NV Aeon', 'coso_key' => array( VHG_BaoCao::squash( 'AEON TÂN PHÚ' ) => 1 ), 'ghe' => array() );
$r7 = VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'VP PHÚ QUỐC',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'x' ), 'PIN' );
t( '🔴 PIN chỉ có Aeon → KHÔNG gửi được cho cả cơ sở Phú Quốc', empty( $r7['ok'] ), $r7 );
teq( '   và không tạo dòng nào', 0, count( DB::$denghi ) );
$r8 = VHG_BaoCao::denghi_gui( array( 'chairCode' => '*', 'coso' => 'AEON TÂN PHÚ',
	'fromDate' => '2026-09-08', 'loai' => 'xoa', 'lyDo' => 'x' ), 'PIN' );
t( '   nhưng cơ sở của mình thì được', ! empty( $r8['ok'] ), $r8 );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 8. MÀN HÌNH PHẢI BÀY LỐI ẤY RA
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 ô chọn ghế có mục "CẢ CƠ SỞ"', false !== mb_strpos( $TR, 'CẢ CƠ SỞ ' ), '' );
t( '   và nó gửi đúng dấu cả-cơ-sở', false !== mb_strpos( $TR, "new Option('★ CẢ CƠ SỞ '+LOC+' — '+dsG.length+' ghế', '*')" ), '' );
/* ⚠️ DÒ TRONG ĐÚNG LỜI GỌI. Chuỗi `coso:LOC||''` còn xuất hiện ở lượt gọi `bc_denghi_ds` (nạp
   danh sách) — dò trống là khớp nhầm dòng ấy, và phép thử xanh cả khi lượt GỬI quên tham số.
   Phá thử chỉ ra đúng lỗ này. */
$i_gui = mb_strpos( $TR, "goi('bc_denghi_gui'," );
$loi_gui = ( false !== $i_gui ) ? mb_substr( $TR, $i_gui, 220 ) : '';
t( 'bốc được lời gọi gửi đề nghị', '' !== $loi_gui );
t( '🔴 gửi kèm tên cơ sở (máy chủ không suy ra được từ dấu *)',
	false !== mb_strpos( $loi_gui, "coso:LOC||''" ), $loi_gui );
t( '   và vẫn gửi mã ghế như cũ', false !== mb_strpos( $loi_gui, 'chairCode:code' ), $loi_gui );
t( '🔴 nhắc rõ trước khi bấm là áp cho bao nhiêu ghế', false !== mb_strpos( $TR, 'áp cho TẤT CẢ ' ), '' );
t( '   nhắc ấy chỉ hiện khi đang chọn cả cơ sở', false !== mb_strpos( $TR, "if(sG.value==='*')" ), '' );
/* Một cơ sở chỉ có ĐÚNG MỘT ghế thì mục ấy là chữ thừa. */
t( 'cơ sở một ghế thì không bày mục cả-cơ-sở', false !== mb_strpos( $TR, 'dsG.length>1' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: một đề nghị cho cả cơ sở, kế toán duyệt một lần là mọi ghế nhận mốc.\n";
