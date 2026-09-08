<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CƠ SỞ RESET BỘ ĐẾM SAU MỖI LẦN THU
 *
 * Anh Thắng 08/09/2026: *"có 1 cơ sở cần reset định kì sau mỗi lần thu"*, sau khi hỏi *"lệnh
 * định kì thì nó có xóa liền không"*.
 *
 * =============================================================================================
 * 🔴 KHÔNG XOÁ GÌ CẢ. Máy VẬT LÝ được nhân viên bấm về 0 sau khi thu; việc của web chỉ là BIẾT
 *    điều đó, để lần nhập kế tiếp lấy 0 làm chỉ số trước thay vì đi tìm chỉ số sau của kỳ
 *    trước. Mọi báo cáo đã nộp giữ nguyên từng con số — bài này canh đúng chuyện ấy.
 *
 * 🔴 CHỖ NGUY NHẤT LÀ `ap_moc_()`. Hàm ấy tính lại `chi_so_truoc` cho những hàng ĐÃ LƯU. Với cơ
 *    sở reset, `chi_so_truoc()` luôn trả 0 — nên nếu để nó chạy, nó kéo mọi hàng CŨ (có từ
 *    trước khi bật cờ) về 0, tức tính lại tiền của những kỳ đã chốt xong. Phải chặn.
 *
 * ⚠️ CHẠY THẬT lõi `chi_so_truoc()` với CSDL giả.
 *
 * Chạy: php tools/test/kiem-coso-reset-moi-lan.php
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
$DB  = file_get_contents( $GOC . '/vhcp-ghe/includes/class-vhg-db.php' );
$MAY = file_get_contents( $GOC . '/vhcp-ghe/includes/class-vhg-may.php' );
$TR  = file_get_contents( $GOC . '/vhcp-ghe/includes/class-vhg-trang.php' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. SƠ ĐỒ BẢNG PHẢI CÓ CỘT ẤY — không thì mọi phép dưới xanh trên một cột không tồn tại
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 bảng cơ sở có cột reset_moi_lan', false !== strpos( $DB, 'reset_moi_lan TINYINT(1) NOT NULL DEFAULT 0' ), '' );
/* Mặc định 0: bật bản mới lên KHÔNG được đổi cách tính của bất kỳ cơ sở nào đang chạy. */
t( '   và mặc định TẮT (cơ sở đang chạy không bị đụng)', false !== strpos( $DB, "reset_moi_lan TINYINT(1) NOT NULL DEFAULT 0" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. BỆ ĐỠ
 * ⚠️ Chỗ mù: đây không phải MySQL. Kiểm được LUẬT (khi nào trả 0, khi nào đi tìm kỳ trước,
 *    `ap_moc_` có chạy không). KHÔNG kiểm được cú pháp SQL.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
define( 'ARRAY_A', 'ARRAY_A' );
function current_time( $f ) { return 'mysql' === $f ? '2026-09-08 20:00:00' : '2026-09-08'; }
function remove_accents( $s ) { return $s; }

class KHO {
	public static $dong = array();     // [ma, ngay, chi_so_sau, lan]
	public static $chot = array();     // [ma, ngay, chi_so]
	public static $moc  = array();     // ma => [cs, ngay]
	public static $reset = array();    // ma => bool
	public static $ghi = array();      // lượt ap_moc_ ghi đè
	public static function reset_het() { self::$dong = array(); self::$chot = array(); self::$moc = array(); self::$reset = array(); self::$ghi = array(); }
}
class VHG_DB {
	public static function t( $b ) { return 'wp_vhg_' . $b; }
	public static function rows( $sql ) {
		if ( false !== strpos( $sql, 'reset_moi_lan' ) ) {
			$ra = array();
			foreach ( KHO::$reset as $ma => $v ) { $ra[] = array( 'ma' => $ma, 'reset_moi_lan' => $v ? 1 : 0 ); }
			return $ra;
		}
		return array();
	}
}
class FakeWpdb {
	public function prepare( $sql, ...$a ) { return array( 'sql' => $sql, 'a' => $a ); }
	public function get_row( $q, $o = null ) {
		$sql = $q['sql']; $a = $q['a'];
		if ( false !== strpos( $sql, 'bc_dong' ) ) {
			$ss = ( false !== strpos( $sql, 'ngay <= %s' ) ) ? '<=' : '<';
			$best = null;
			foreach ( KHO::$dong as $d ) {
				if ( $d[0] !== $a[0] ) { continue; }
				$ok = ( '<=' === $ss ) ? ( $d[1] <= $a[1] ) : ( $d[1] < $a[1] );
				if ( ! $ok ) { continue; }
				if ( null === $best || $d[1] > $best[1] || ( $d[1] === $best[1] && $d[2] > $best[2] ) ) { $best = $d; }
			}
			return $best ? array( 'cs' => $best[2], 'd' => $best[1] ) : null;
		}
		if ( false !== strpos( $sql, 'chot' ) ) {
			$best = null;
			foreach ( KHO::$chot as $c ) {
				if ( $c[0] !== $a[0] || ! ( $c[1] < $a[1] ) ) { continue; }
				if ( null === $best || $c[1] > $best[1] ) { $best = $c; }
			}
			return $best ? array( 'cs' => $best[2], 'd' => $best[1] ) : null;
		}
		if ( false !== strpos( $sql, 'moc_chiso' ) ) {
			$m = isset( KHO::$moc[ $a[0] ] ) ? KHO::$moc[ $a[0] ] : null;
			return $m ? array( 'cs' => $m[0], 'd' => $m[1] ) : array( 'cs' => null, 'd' => null );
		}
		return null;
	}
	public function get_var( $q ) { return null; }
	public function update( $bang, $d, $w ) { KHO::$ghi[] = array( $w, $d ); return 1; }
}
$GLOBALS['wpdb'] = new FakeWpdb();

/* Bốc THẬT lõi tính chỉ số. */
function boc( $src, $ham ) {
	$ra = '';
	foreach ( $ham as $h ) {
		$i = strpos( $src, 'function ' . $h . '(' );
		if ( false === $i ) { return ''; }
		$i = strrpos( substr( $src, 0, $i ), "\n\t" ) + 1;
		/* ⚠️ HÀM MỘT DÒNG (`quen_reset_memo()`) KẾT THÚC NGAY TRÊN DÒNG ẤY. Cắt tới `\n\t}` tiếp
		   theo là ôm luôn cả hàm đứng sau — và `eval` chối vì khai lại một hàm hai lần. Mất một
		   lượt đúng như thế. */
		$het_dong = strpos( $src, "\n", $i );
		$dong1 = substr( $src, $i, $het_dong - $i );
		if ( '}' === substr( rtrim( $dong1 ), -1 ) && false !== strpos( $dong1, '{' ) ) {
			$ra .= $dong1 . "\n";
			continue;
		}
		$j = strpos( $src, "\n\t}", $i );
		$ra .= substr( $src, $i, $j - $i + 3 ) . "\n";
	}
	return $ra;
}
$than = boc( $BC, array( 'ngay_', 'may_reset_moi_lan', 'quen_reset_memo', 'chi_so_truoc_ct_', 'chi_so_truoc', 'ap_moc_' ) );
t( 'bốc được lõi tính chỉ số', false !== strpos( $than, 'chi_so_truoc_ct_' ) && false !== strpos( $than, 'may_reset_moi_lan' ), '' );
eval( 'class VHG_BaoCao { private static $reset_memo = null;'
	. str_replace( 'private static function ap_moc_', 'public static function ap_moc_', $than )
	. ' public static function songuyen_( $x ) { return (int) $x; }'
	/* `ap_moc_` gọi `tinh_()` để tính lại tiền của hàng — không phải việc của bài này, và dựng
	   lại nó ở đây là chép luật tính tiền ra một bản thứ hai. Vỏ rỗng: bài này chỉ hỏi
	   `ap_moc_` CÓ GHI ĐÈ hay không, chứ không hỏi nó tính ra bao nhiêu. */
	/* Vỏ này còn điền mấy khoá `ap_moc_` đọc sau khi tính — không thì PHP kêu "Undefined array
	   key" ở mọi lượt, và cảnh báo lẫn vào kết quả làm người đọc tưởng bài kiểm hỏng. */
	. ' public static function tinh_( &$r ) { $r["_da_tinh"] = 1;'
	. '   foreach ( array( "actual", "tien_mat", "tong" ) as $k ) { if ( ! isset( $r[$k] ) ) { $r[$k] = 0; } } }'
	. ' public static function chi_so_truoc_ct( $a, $b, $c = false ) { return self::chi_so_truoc_ct_( $a, $b, $c ); } }' );

function san() {
	KHO::reset_het();
	KHO::$reset = array( 'PQ-1' => true, 'PQ-2' => true, 'TP-1' => false );
	VHG_BaoCao::quen_reset_memo();
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 CƠ SỞ RESET → CHỈ SỐ TRƯỚC LUÔN LÀ 0
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
san();
KHO::$dong[] = array( 'PQ-1', '2026-09-01', 4400, 1 );   // kỳ trước có số to
KHO::$dong[] = array( 'TP-1', '2026-09-01', 4400, 1 );
teq( '🔴 ghế cơ sở reset → chỉ số trước = 0 dù kỳ trước có số', 0, VHG_BaoCao::chi_so_truoc( 'PQ-1', '2026-09-08' ) );
teq( '🔴 ghế cơ sở THƯỜNG → vẫn lấy chỉ số kỳ trước', 4400, VHG_BaoCao::chi_so_truoc( 'TP-1', '2026-09-08' ) );
/* Thu lần nữa trong ngày: reset sau MỖI lần thu nghĩa là lần nào cũng từ 0. */
KHO::$dong[] = array( 'PQ-1', '2026-09-08', 120, 1 );
teq( '🔴 thu lần nữa trong ngày → vẫn từ 0', 0, VHG_BaoCao::chi_so_truoc( 'PQ-1', '2026-09-08', true ) );
KHO::$dong[] = array( 'TP-1', '2026-09-08', 120, 1 );
teq( '   đối chứng · cơ sở thường thì nối tiếp lần trước', 120, VHG_BaoCao::chi_so_truoc( 'TP-1', '2026-09-08', true ) );

/* Mốc do kế toán duyệt cũng không lấn được — cơ sở reset thì luôn 0. */
san();
KHO::$moc['PQ-1'] = array( 999, '2026-09-05' );
KHO::$moc['TP-1'] = array( 999, '2026-09-05' );
teq( 'cơ sở reset: mốc kế toán không đổi được luật', 0, VHG_BaoCao::chi_so_truoc( 'PQ-1', '2026-09-08' ) );
teq( 'đối chứng · cơ sở thường vẫn ăn mốc kế toán', 999, VHG_BaoCao::chi_so_truoc( 'TP-1', '2026-09-08' ) );

/* Ngày rỗng / mã rỗng vẫn phải trả null, không rơi về 0 — "chưa biết" khác "bằng 0". */
teq( 'mã ghế rỗng → null', null, VHG_BaoCao::chi_so_truoc( '', '2026-09-08' ) );
teq( 'ngày rỗng → null',   null, VHG_BaoCao::chi_so_truoc( 'PQ-1', '' ) );

/* Ghế của cơ sở reset mà CHƯA có dữ liệu gì cũng là 0, không phải null (null = "nhập lần đầu",
   sẽ bắt người ta gõ tay chỉ số trước — trong khi ở đây nó chắc chắn là 0). */
san();
teq( '🔴 ghế reset chưa có dữ liệu → 0, KHÔNG phải null (khỏi bắt gõ tay)', 0, VHG_BaoCao::chi_so_truoc( 'PQ-2', '2026-09-08' ) );
teq( '   đối chứng · ghế thường chưa có gì → null (nhập lần đầu)', null, VHG_BaoCao::chi_so_truoc( 'TP-1', '2026-09-08' ) );

/* Ghế không có trong bảng tra (mới thêm, chưa gán cơ sở) → coi như thường, không tự bật. */
teq( '🔴 ghế lạ → KHÔNG tự coi là reset', null, VHG_BaoCao::chi_so_truoc( 'LA-9', '2026-09-08' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 `ap_moc_` KHÔNG ĐƯỢC ĐỤNG GHẾ CỦA CƠ SỞ RESET
 *
 * Đây là chỗ nguy nhất: nó ghi đè `chi_so_truoc` của hàng ĐÃ LƯU. Chạy trên cơ sở reset là kéo
 * mọi hàng cũ về 0 — tính lại tiền của những kỳ đã chốt xong.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
san();
$hang = array( 'ma_may' => 'PQ-1', 'ngay' => '2026-09-08', 'chi_so_truoc' => 4400,
	'chi_so_sau' => 4520, 'ghi_chu' => '', 'id' => 7, 'qr' => 0, 'dieu_chinh' => 0 );
VHG_BaoCao::ap_moc_( $hang );
teq( '🔴 ghế cơ sở reset → ap_moc_ KHÔNG ghi gì', 0, count( KHO::$ghi ) );

/* Đối chứng: cơ sở thường thì nó VẪN phải chạy như trước — bản này chỉ thêm một chốt. */
san();
KHO::$dong[] = array( 'TP-1', '2026-09-01', 4000, 1 );
$hang2 = array( 'ma_may' => 'TP-1', 'ngay' => '2026-09-08', 'chi_so_truoc' => 3000,
	'chi_so_sau' => 4520, 'ghi_chu' => '', 'id' => 8, 'qr' => 0, 'dieu_chinh' => 0 );
VHG_BaoCao::ap_moc_( $hang2 );
t( '🔴 đối chứng · cơ sở THƯỜNG thì ap_moc_ vẫn nối lại như cũ', count( KHO::$ghi ) > 0, KHO::$ghi );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. ĐỆM PHẢI DỌN ĐƯỢC — không thì đổi cấu hình xong lượt sau còn đọc số cũ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
san();
teq( 'ban đầu PQ-1 là cơ sở reset', 0, VHG_BaoCao::chi_so_truoc( 'PQ-1', '2026-09-08' ) );
KHO::$reset['PQ-1'] = false;
teq( '⚠️ chưa dọn đệm → vẫn đọc số cũ (đúng, đó là ý của đệm)', 0, VHG_BaoCao::chi_so_truoc( 'PQ-1', '2026-09-08' ) );
VHG_BaoCao::quen_reset_memo();
teq( '🔴 dọn đệm xong → theo cấu hình mới', null, VHG_BaoCao::chi_so_truoc( 'PQ-1', '2026-09-08' ) );
/* Và nơi lưu cấu hình phải THẬT SỰ gọi dọn — không thì hàm dọn kia là mã chết. */
t( '🔴 lưu cơ sở xong có dọn đệm', 3 === substr_count( $MAY, 'self::quen_dem_reset_();' ), substr_count( $MAY, 'self::quen_dem_reset_();' ) );
t( '   và hàm dọn gọi đúng chỗ', false !== strpos( $MAY, "VHG_BaoCao::quen_reset_memo()" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. LƯU CỜ: KHÔNG TRUYỀN = KHÔNG ĐỤNG
 *
 * 🔴 Mọi lượt lưu tên / tỉnh / mã KH đều gọi cùng hàm ấy. Hiểu "không truyền" thành "tắt" là
 *    đổi tên cơ sở một cái là tắt mất chế độ reset, và không ai biết vì sao tiền lệch.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 luu_coso nhận tham số reset, mặc định null', false !== strpos( $MAY, 'function luu_coso( $id, $ten, $tinh = null, $ma_kh = null, $reset = null )' ), '' );
t( '🔴 null = không đụng cờ', false !== strpos( $MAY, '$co_reset = ( null !== $reset );' ), '' );
t( '   chỉ ghi khi có truyền · lúc sửa', false !== strpos( $MAY, 'if ( $co_reset ) { $data[\'reset_moi_lan\'] = $reset; }' ), '' );
t( '   chỉ ghi khi có truyền · lúc tên đã tồn tại', false !== strpos( $MAY, 'if ( $co_reset ) { $data_cu[\'reset_moi_lan\'] = $reset; }' ), '' );
t( 'cơ sở mới mặc định TẮT', false !== strpos( $MAY, '\'reset_moi_lan\' => $co_reset ? $reset : 0' ), '' );
/* Cổng cũng phải phân biệt "không gửi" với "gửi 0". */
t( '🔴 cổng phân biệt "không gửi khoá" với "gửi 0"',
	false !== strpos( $TR, 'array_key_exists( \'reset_moi_lan\', $d ) ? ! empty( $d[\'reset_moi_lan\'] ) : null' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. MÀN HÌNH PHẢI NÓI RA
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 màn quản trị bày huy hiệu cơ sở đang bật', false !== mb_strpos( $TR, 'Reset về 0 sau mỗi lần thu' ), '' );
t( '🔴 có nút bật/tắt riêng', false !== mb_strpos( $TR, 'data-csreset=' ), '' );
/* Dò trong ĐÚNG tay nghe của nút ấy: chuỗi `confirm(` có mặt khắp trang, dò trống là khớp
   nhầm một nút khác — phá thử chỉ ra đúng lỗ ấy. */
$i_nut = mb_strpos( $TR, "querySelectorAll('[data-csreset]')" );
$than_nut = ( false !== $i_nut ) ? mb_substr( $TR, $i_nut, 1400 ) : '';
t( 'bốc được tay nghe nút bật/tắt', '' !== $than_nut, '' );
t( '   hỏi lại trước khi đổi', false !== mb_strpos( $than_nut, 'if (!confirm(hoi)) return;' ), $than_nut );
t( '   và chỉ lưu SAU khi người ta đồng ý',
	mb_strpos( $than_nut, 'confirm(hoi)' ) < mb_strpos( $than_nut, "lam('coso_luu'" ), '' );
t( '   nói rõ KHÔNG xoá gì', false !== mb_strpos( $TR, 'KHÔNG xoá gì cả' ), '' );
t( '🔴 boot gửi danh sách cơ sở reset xuống màn nhập', false !== strpos( $BC, '\'resetCoso\' => $reset_cs' ), '' );
t( '   và chỉ gửi cơ sở TRONG phạm vi người đó', false !== strpos( $BC, 'isset( $cs[ $t ] )' ), '' );
t( '🔴 màn nhập có dải nhắc vì sao chỉ số trước bằng 0', false !== mb_strpos( $TR, 'nên "Chỉ số trước" luôn là 0' ), '' );
t( '   nói rõ số liệu kỳ trước vẫn còn', false !== mb_strpos( $TR, 'vẫn còn nguyên' ), '' );
t( '   đổi cơ sở thì dọn dải cũ đi', false !== mb_strpos( $TR, "querySelector('.bc-nhac-reset')" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: cơ sở reset thì chỉ số trước luôn 0, và không đụng một con số đã nộp nào.\n";
