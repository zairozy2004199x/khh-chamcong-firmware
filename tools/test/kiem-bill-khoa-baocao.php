<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐÍNH BILL CHUYỂN KHOẢN → NỘP → KHOÁ BÁO CÁO
 *
 * Anh Thắng 05/09/2026: *"chỗ đó sẽ có thêm (bổ sung bill chuyển khoản) · khi nhân viên add bill
 * và xác nhận đã nộp thì báo cáo đó sẽ không sửa được nữa"*.
 *
 * 🔴 CÁI KHOÁ NÀY ĐỨNG GIỮA MỘT TỜ BILL VÀ MỘT LƯỢT TIỀN ĐANG CHỜ KẾ TOÁN. Sửa được số của báo
 *    cáo sau khi đã bấm nộp là để bill nói một đằng, sổ nói một nẻo — mà bill thì đã nằm trong
 *    tay kế toán rồi. Nên bài này canh CHỐT, không canh nút bấm: cổng `bc_edit`/`bc_supplement`
 *    nhận lệnh từ bất cứ ai có PIN trong phạm vi, nút bấm chỉ là dọn mắt.
 *
 * 🔴 VÀ CANH CẢ THỨ TỰ. Khoá trước rồi mở lượt nộp mà lượt nộp hỏng thì báo cáo nằm lại ở trạng
 *    thái tệ nhất: KHOÁ, không sửa được, mà tiền chẳng ai nhận — nhân viên không còn đường tự gỡ.
 *
 * ⚠️ BỐC THẲNG CÁC HÀM TỪ MÃ NGUỒN RA CHẠY với `$wpdb` giả — không chép lại, không dò chuỗi.
 *
 * ⚠️ CHỖ MÙ CỦA BỆ ĐỠ NÀY, ghi ra để người sau khỏi tưởng đã phủ kín: `$wpdb` giả ở đây hiểu
 *    đúng những câu SQL mà mấy hàm này dùng (SELECT một hàng, UPDATE có điều kiện, COUNT), chứ
 *    KHÔNG phải một cơ sở dữ liệu thật. Nó không canh được kiểu cột, khoá UNIQUE, hay hai lượt
 *    chạy song song thật sự — ca "bấm hai lần" dưới đây mô phỏng bằng cách chạy tuần tự.
 *
 * Chạy: php tools/test/kiem-bill-khoa-baocao.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc );
}

$G = __DIR__ . '/../../vhcp-ghe/includes/';
$SRC_BC  = file_get_contents( $G . 'class-vhg-baocao.php' );
$SRC_QUY = file_get_contents( $G . 'class-vhg-quy.php' );
$SRC_DB  = file_get_contents( $G . 'class-vhg-db.php' );

/* ---------- Bốc hàm ---------- */
function boc( $src, $dau, $het ) {
	$i = strpos( $src, $dau );
	if ( false === $i ) { return ''; }
	$j = strpos( $src, $het, $i + strlen( $dau ) );
	return ( false === $j ) ? '' : substr( $src, $i, $j - $i );
}
$f_khoa    = boc( $SRC_BC, 'private static function khoa_bill_( $h ) {', "\n\t/** Mảng URL ảnh bill" );
$f_billanh = boc( $SRC_BC, 'private static function bill_anh_( $h ) {', "\n\tpublic static function ds_24h(" );
$f_nopbill = boc( $SRC_BC, 'public static function nop_bill( $rid, $anh, $ghi_chu, $pin ) {', "\n\t/**\n\t * KẾ TOÁN MỞ KHOÁ" );
$f_mokhoa  = boc( $SRC_BC, 'public static function mo_khoa_bill( $rid, $ai, $ly_do = \'\' ) {', "\n\tpublic static function sua_dong(" );
$f_nopbc   = boc( $SRC_QUY, 'public static function nop_bao_cao( $rid, $nguoi, $ghi_chu = \'\' ) {', "\n\t/**\n\t * GỠ MỘT BÁO CÁO" );
$f_suadong = boc( $SRC_BC, 'public static function sua_dong( $rid, $ma, $patch, $pin ) {', "\n\t\t" . '$d = $wpdb->get_row(' );
$f_bosung  = boc( $SRC_BC, 'public static function nop_bosung( $rid, $ngay, $so_tien, $hinhthuc, $pin ) {', "\n\t\t" . '$s = self::tong_bc_(' );
$f_gobc    = boc( $SRC_QUY, 'public static function go_bao_cao_khoi_nop( $rid ) {', "\n\t/**\n\t * QUẢN LÝ XÁC NHẬN" );

t( 'bốc được hàm đọc khoá',        '' !== $f_khoa );
t( 'bốc được hàm đọc ảnh bill',    '' !== $f_billanh );
t( 'bốc được hàm đính bill + nộp', '' !== $f_nopbill );
t( 'bốc được hàm mở khoá',         '' !== $f_mokhoa );
t( 'bốc được hàm nộp một báo cáo', '' !== $f_nopbc );
t( 'bốc được hàm gỡ khỏi lượt nộp','' !== $f_gobc );
t( 'bốc được đầu hàm sửa dòng',    '' !== $f_suadong );
t( 'bốc được đầu hàm nộp bổ sung', '' !== $f_bosung );
if ( '' === $f_khoa || '' === $f_nopbill || '' === $f_mokhoa || '' === $f_nopbc || '' === $f_gobc ) {
	echo "✗ không bốc đủ hàm — dừng.\n"; exit( 1 );
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BỆ ĐỠ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }

class KhoGia {
	public $bc = array();      // report_id => hàng
	public $nop = array();     // id => hàng
	public $tien_mat = array();// report_id => tổng tien_mat của bc_dong
	public $nop_seq = 100;
	public $chan_insert_nop = false;   // ép insert lượt nộp hỏng, để soi nhánh dọn dẹp
	public $chen_giua = 0;             // >0: ngay sau khi mở lượt nộp, giả bộ một lượt KHÁC vừa
	                                   //     gắn mất báo cáo — dựng lại đúng cuộc đua hai lượt
}
$KHO = new KhoGia();

class WpdbGia {
	public $insert_id = 0;
	private function ten_( $sql ) {
		foreach ( array( 'bc', 'nop', 'chot', 'thu' ) as $b ) {
			if ( false !== strpos( $sql, VHG_DB::t( $b ) ) ) { return $b; }
		}
		return '';
	}
	public function prepare( $sql, ...$a ) {
		foreach ( $a as $x ) {
			$sql = preg_replace( '/%s|%d/', is_int( $x ) ? (string) $x : "'" . $x . "'", $sql, 1 );
		}
		return $sql;
	}
	private function rid_( $sql ) {
		return preg_match( "/report_id='([^']*)'/", $sql, $m ) ? $m[1] : '';
	}
	private function nid_( $sql ) {
		return preg_match( '/nop_id=(\d+)/', $sql, $m ) ? (int) $m[1] : -1;
	}
	public function get_row( $sql, $out = null ) {
		global $KHO;
		$b = $this->ten_( $sql );
		if ( 'bc' === $b ) {
			$r = $this->rid_( $sql );
			return isset( $KHO->bc[ $r ] ) ? $KHO->bc[ $r ] : null;
		}
		if ( 'nop' === $b ) {
			if ( preg_match( '/WHERE id=(\d+)/', $sql, $m ) ) {
				$i = (int) $m[1];
				return isset( $KHO->nop[ $i ] ) ? $KHO->nop[ $i ] : null;
			}
		}
		return null;
	}
	public function get_var( $sql ) {
		global $KHO;
		/* Tổng tiền mặt của báo cáo đã gắn vào lượt nộp này. */
		if ( false !== strpos( $sql, 'SUM(d.tien_mat)' ) ) {
			$nid = $this->nid_( $sql ); $s = 0;
			foreach ( $KHO->bc as $rid => $h ) {
				if ( (int) $h['nop_id'] === $nid ) { $s += (int) ( isset( $KHO->tien_mat[ $rid ] ) ? $KHO->tien_mat[ $rid ] : 0 ); }
			}
			return $s;
		}
		if ( false !== strpos( $sql, 'COUNT(*)' ) ) {
			$b = $this->ten_( $sql ); $nid = $this->nid_( $sql ); $n = 0;
			if ( 'bc' === $b ) { foreach ( $KHO->bc as $h ) { if ( (int) $h['nop_id'] === $nid ) { $n++; } } }
			return $n;   // chot/thu: bệ này không mô phỏng, luôn 0
		}
		return 0;
	}
	public function insert( $bang, $d ) {
		global $KHO;
		if ( false !== strpos( $bang, 'nop' ) ) {
			if ( $KHO->chan_insert_nop ) { $this->insert_id = 0; return false; }
			$id = ++$KHO->nop_seq;
			$d['id'] = $id; $KHO->nop[ $id ] = $d; $this->insert_id = $id;
			if ( $KHO->chen_giua > 0 ) {
				foreach ( $KHO->bc as $r => $h ) { $KHO->bc[ $r ]['nop_id'] = $KHO->chen_giua; }
				$KHO->chen_giua = 0;
			}
			return 1;
		}
		return 1;
	}
	public function update( $bang, $d, $w ) {
		global $KHO;
		if ( false !== strpos( $bang, 'bc' ) && isset( $w['report_id'] ) ) {
			$r = $w['report_id'];
			if ( ! isset( $KHO->bc[ $r ] ) ) { return 0; }
			foreach ( $d as $k => $v ) { $KHO->bc[ $r ][ $k ] = $v; }
			return 1;
		}
		if ( false !== strpos( $bang, 'nop' ) && isset( $w['id'] ) ) {
			$i = (int) $w['id'];
			if ( ! isset( $KHO->nop[ $i ] ) ) { return 0; }
			foreach ( $d as $k => $v ) { $KHO->nop[ $i ][ $k ] = $v; }
			return 1;
		}
		return 0;
	}
	public function delete( $bang, $w ) {
		global $KHO;
		if ( false !== strpos( $bang, 'nop' ) && isset( $w['id'] ) ) { unset( $KHO->nop[ (int) $w['id'] ] ); return 1; }
		return 1;
	}
	/** Chỉ phục vụ đúng hai câu UPDATE mà nop_bao_cao()/go_bao_cao_khoi_nop() dùng. */
	public function query( $sql ) {
		global $KHO;
		if ( ! preg_match( '/^UPDATE/', trim( $sql ) ) ) { return 0; }
		if ( false === strpos( $sql, VHG_DB::t( 'bc' ) ) ) { return 0; }
		$rid = $this->rid_( $sql );
		if ( '' === $rid || ! isset( $KHO->bc[ $rid ] ) ) { return 0; }
		$h = $KHO->bc[ $rid ];
		/* SET nop_id=<moi> WHERE report_id=.. AND nop_id=<cu> [AND nhan_vien=..] */
		if ( ! preg_match( '/SET nop_id=(\d+)/', $sql, $ms ) ) { return 0; }
		$moi = (int) $ms[1];
		if ( preg_match( '/AND nop_id=(\d+)/', $sql, $mc ) && (int) $h['nop_id'] !== (int) $mc[1] ) { return 0; }
		if ( preg_match( "/AND nhan_vien='([^']*)'/", $sql, $mn ) && (string) $h['nhan_vien'] !== $mn[1] ) { return 0; }
		$KHO->bc[ $rid ]['nop_id'] = $moi;
		return 1;
	}
}

/* Hai lớp vỏ mang CHÍNH mã nguồn vừa bốc ra, kèm vài hàm giúp việc mà chúng gọi tới.
   ⚠️ `VHG_QuyCau` là tên `nop_bill()`/`mo_khoa_bill()` sẽ gọi tới — thân hàm bốc ra được thay
      `VHG_Quy::` thành `VHG_QuyCau::` ngay dưới. Chỉ đổi TÊN LỚP ĐƯỢC GỌI, không đụng một chữ
      nào khác trong thân hàm; hai hàm quỹ bên trong cũng là mã thật bốc ra, không phải bản chép. */
eval( 'class VHG_QuyCau { ' . $f_nopbc . "\n" . $f_gobc . ' }' );

$wpdb = new WpdbGia();

eval( 'class VHG_BC {
	const GIO_SUA = 24;
	public static $PIN = null;      public static $ANH_HONG = false;
	public static $DEM_LUU_ANH = 0;
	public static function pin_info( $p ) { return self::$PIN; }
	public static function trong_pham_vi( $q, $cs, $ma = "" ) { return true; }
	public static function header_theo_id_( $rid ) { global $KHO; return isset( $KHO->bc[ $rid ] ) ? $KHO->bc[ $rid ] : null; }
	public static function ngay_( $d ) { return (string) $d; }
	public static function con_han_( $t ) { return "CU" !== (string) $t; }
	public static function luu_nhieu_anh_( $anh, $rid, $tt ) {
		self::$DEM_LUU_ANH++;
		if ( self::$ANH_HONG ) { return array(); }
		$ra = array(); $i = 0;
		foreach ( array( "qr", "cash", "transfer" ) as $nhom ) {
			if ( empty( $anh[ $nhom ] ) || ! is_array( $anh[ $nhom ] ) ) { continue; }
			foreach ( $anh[ $nhom ] as $x ) { $ra[] = "/anh/" . $rid . "-" . $tt . "-" . ( ++$i ) . ".jpg"; }
		}
		return $ra;
	}
	' . str_replace( 'VHG_Quy::', 'VHG_QuyCau::', $f_khoa . "\n" . $f_billanh . "\n" . $f_nopbill . "\n" . $f_mokhoa ) . '
}' );

/* Hai ĐƯỜNG GHI CÒN LẠI mà một PIN chạm tới được. Bốc phần ĐẦU của mỗi hàm — tới ngay trước
   lệnh ghi đầu tiên — rồi chạy: đủ để biết chốt khoá có chặn không, mà không phải dựng cả cỗ
   máy tính tiền phía sau. Chốt lọt thì hàm chạy tiếp và trả về một câu KHÁC HẲN, nên hai ca
   phân biệt được. */
eval( 'class VHG_CHOT {
	const GIO_SUA = 24;
	public static $PIN = null;
	public static function pin_info( $p ) { return self::$PIN; }
	public static function trong_pham_vi( $q, $cs, $ma = "" ) { return true; }
	public static function header_theo_id_( $rid ) { global $KHO; return isset( $KHO->bc[ $rid ] ) ? $KHO->bc[ $rid ] : null; }
	public static function ngay_( $d ) { return (string) $d; }
	public static function con_han_( $t ) { return "CU" !== (string) $t; }
	public static function dang_khoa( $cs, $ng ) { return false; }
	public static function tong_bc_( $rid ) { global $KHO; return array( "tien_mat" => (int) $KHO->tien_mat[ $rid ] ); }
	' . $f_khoa . "\n"
	  . $f_suadong . ' return array( "ok" => true, "DI_QUA_CHOT" => 1 ); }' . "\n"
	  . $f_bosung  . ' return array( "ok" => true, "DI_QUA_CHOT" => 1 ); }
}' );

function current_time( $k ) { return 'mysql' === $k ? '2026-09-05 10:00:00' : time(); }
function wp_json_encode( $x ) { return json_encode( $x, JSON_UNESCAPED_UNICODE ); }
function mb_substr_( $s, $a, $b ) { return mb_substr( $s, $a, $b ); }
function number_format_i18n( $n ) { return number_format( $n ); }

/* ---------- Dựng lại kho về trạng thái sạch ---------- */
function nap( $tien_mat = 900000, $nop_id = 0, $bill_luc = null, $nv = 'Nguyễn Văn Bin' ) {
	global $KHO;
	$KHO = new KhoGia();
	$KHO->bc['RPT-1'] = array(
		'report_id' => 'RPT-1', 'ngay' => '2026-09-05', 'coso' => 'GO TRƯỜNG CHINH',
		'nhan_vien' => $nv, 'nop_id' => $nop_id, 'tao_luc' => '2026-09-05 08:00:00',
		'bill_anh' => '', 'bill_luc' => $bill_luc, 'bill_ai' => '', 'bill_ghichu' => '',
		'bill_mo_luc' => null, 'bill_mo_ai' => '', 'nop_so_tien' => 0, 'nop_ghichu' => '' );
	$KHO->tien_mat['RPT-1'] = $tien_mat;
	VHG_BC::$PIN = array( 'ten' => $nv );
	VHG_BC::$ANH_HONG = false;
	VHG_BC::$DEM_LUU_ANH = 0;
	return $KHO;
}
$ANH = array( 'qr' => array( array( 'name' => 'bill.jpg', 'dataUrl' => 'x' ) ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. ĐƯỜNG THẲNG: ĐÍNH BILL → LƯỢT NỘP → KHOÁ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
nap();
$r = VHG_BC::nop_bill( 'RPT-1', $ANH, 'VCB 123456', 'PIN' );
t( '🔴 đính bill + xác nhận nộp chạy được', ! empty( $r['ok'] ), $r );
teq( 'trả về đúng số tiền mặt của báo cáo', 900000, isset( $r['soTien'] ) ? $r['soTien'] : null );
t( '🔴 báo cáo KHOÁ sau khi bấm', '' !== (string) $KHO->bc['RPT-1']['bill_luc'], $KHO->bc['RPT-1'] );
t( 'ảnh bill được ghi vào báo cáo', false !== strpos( (string) $KHO->bc['RPT-1']['bill_anh'], 'bill' ), $KHO->bc['RPT-1']['bill_anh'] );
t( 'ghi lại AI bấm', 'Nguyễn Văn Bin' === (string) $KHO->bc['RPT-1']['bill_ai'], $KHO->bc['RPT-1']['bill_ai'] );
t( 'giữ ghi chú (mã giao dịch)', false !== strpos( (string) $KHO->bc['RPT-1']['bill_ghichu'], 'VCB 123456' ), $KHO->bc['RPT-1']['bill_ghichu'] );

/* Lượt nộp phải có thật, mang đúng số tiền, và đang CHỜ. */
teq( '🔴 sinh ra ĐÚNG MỘT lượt nộp', 1, count( $KHO->nop ) );
$n = array_values( $KHO->nop )[0];
teq( '🔴 lượt nộp mang đúng số tiền mặt của báo cáo', 900000, (int) $n['so_tien'] );
teq( 'và đang chờ kế toán xác nhận', 'cho', (string) $n['trang_thai'] );
teq( 'đứng tên đúng người gửi báo cáo', 'Nguyễn Văn Bin', (string) $n['nguoi'] );
/* Ghi chú lượt nộp phải nói ra CƠ SỞ và NGÀY — kế toán nhìn bảng chờ phải biết đây là tiền của
   báo cáo nào mà không cần mở thêm màn nào. */
t( '🔴 ghi chú lượt nộp gọi tên cơ sở và ngày',
	false !== strpos( (string) $n['ghi_chu'], 'GO TRƯỜNG CHINH' )
	&& false !== strpos( (string) $n['ghi_chu'], '2026-09-05' ), $n['ghi_chu'] );
teq( 'báo cáo được gắn vào đúng lượt nộp ấy', (int) $n['id'], (int) $KHO->bc['RPT-1']['nop_id'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. ẢNH BILL LÀ BẮT BUỘC
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 Cho bấm mà không có ảnh là khoá một báo cáo bằng KHÔNG GÌ CẢ — đúng cái khoá ấy đứng giữa
   kế toán và quyền sửa số, nên nó phải đổi lấy một tờ bằng chứng. */
nap();
$r = VHG_BC::nop_bill( 'RPT-1', array(), '', 'PIN' );
t( '🔴 không ảnh -> CHỐI', empty( $r['ok'] ), $r );
t( 'và nói rõ là thiếu ảnh', ! empty( $r['thieu_anh'] ), $r );
t( '🔴 và KHÔNG khoá báo cáo', null === $KHO->bc['RPT-1']['bill_luc'], $KHO->bc['RPT-1']['bill_luc'] );
teq( '🔴 và KHÔNG mở lượt nộp nào', 0, count( $KHO->nop ) );

/* Ảnh gửi lên nhưng nén/đọc hỏng hết — cùng kết quả, không được để lại nửa vời. */
nap(); VHG_BC::$ANH_HONG = true;
$r = VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
t( '🔴 ảnh hỏng hết -> CHỐI, không khoá', empty( $r['ok'] ) && null === $KHO->bc['RPT-1']['bill_luc'], $r );
teq( 'và không mở lượt nộp', 0, count( $KHO->nop ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. NỘP TRƯỚC, KHOÁ SAU — THỨ TỰ LÀ CÓ CHỦ Ý
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 CA TỆ NHẤT CÓ THỂ XẢY RA: báo cáo KHOÁ mà tiền chẳng ai nhận. Nhân viên không sửa được,
   cũng không bấm lại được — kẹt hẳn. Khoá phải là việc CUỐI, sau khi lượt nộp đã chắc chắn. */
nap( 0 );   // báo cáo toàn QR, không đồng tiền mặt nào phải nộp
$r = VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
t( '🔴 báo cáo không có tiền mặt -> CHỐI', empty( $r['ok'] ), $r );
t( '🔴 và KHÔNG để lại báo cáo bị khoá mà tiền không ai nhận',
	null === $KHO->bc['RPT-1']['bill_luc'], $KHO->bc['RPT-1']['bill_luc'] );
teq( '🔴 và không để lại lượt nộp 0 đồng trong bảng chờ của kế toán', 0, count( $KHO->nop ) );
teq( '🔴 và nhả nop_id ra, không để báo cáo dính vào lượt đã xoá', 0, (int) $KHO->bc['RPT-1']['nop_id'] );

/* Không mở nổi lượt nộp (CSDL chối) — cũng không được khoá. */
nap(); $KHO->chan_insert_nop = true;
$r = VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
t( '🔴 mở lượt nộp hỏng -> CHỐI và KHÔNG khoá',
	empty( $r['ok'] ) && null === $KHO->bc['RPT-1']['bill_luc'], $r );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. BẤM HAI LẦN
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
nap();
VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
$r2 = VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
t( '🔴 bấm lần hai -> CHỐI', empty( $r2['ok'] ), $r2 );
t( 'và nói rõ vì đã khoá', ! empty( $r2['khoa_bill'] ), $r2 );
teq( '🔴 vẫn chỉ MỘT lượt nộp, không nhân đôi tiền', 1, count( $KHO->nop ) );

/* Chốt tầng dưới: kể cả khi bằng cách nào đó lọt qua chốt khoá, `nop_bao_cao()` vẫn chặn. */
nap( 900000, 55 );   // báo cáo đã gắn vào lượt nộp 55, nhưng chưa có bill_luc
$r = VHG_QuyCau::nop_bao_cao( 'RPT-1', 'Nguyễn Văn Bin' );
t( '🔴 tầng quỹ cũng chối báo cáo đã nộp rồi', empty( $r['ok'] ), $r );
teq( 'và không mở thêm lượt nộp nào', 0, count( $KHO->nop ) );
/* Cùng lý do với ca nộp hộ ở mục 5: bỏ chốt `nop_id > 0` thì mệnh đề `AND nop_id=0` trong câu
   UPDATE vẫn giữ an toàn, nhưng câu trả lời đổi thành "vừa được nộp ở một lượt khác" — trong
   khi thật ra nó đã nộp từ đời nào. Canh câu chối để phép có nghĩa. */
t( '🔴 và nói ĐÚNG chuyện: đã nộp rồi', false !== strpos( (string) $r['error'], 'đã nộp rồi' ), $r['error'] );

/* 🔴 HAI LƯỢT CHEN NHAU THẬT — mệnh đề `AND nop_id=0` trong câu UPDATE là chốt cuối, và nó chỉ
   có nghĩa đúng lúc này: hai người (hoặc một người bấm hai lần vì mạng chậm) cùng đọc thấy
   `nop_id=0`, cùng mở lượt nộp, rồi mới tới lượt gắn. Không có nó thì hai lượt nộp cùng mang
   một xấp tiền — kế toán nhận đủ cả hai, quỹ thừa đúng một lần.
   ⚠️ Bệ đỡ tuần tự không tự đẻ ra cuộc đua, nên dựng nó bằng tay: cho lượt insert LÉN gắn
      `nop_id` cho báo cáo, đúng như một lượt khác vừa chen vào giữa hai bước. */
nap();
$KHO->chen_giua = 77;
$r = VHG_QuyCau::nop_bao_cao( 'RPT-1', 'Nguyễn Văn Bin' );
t( '🔴 lượt khác chen vào giữa -> CHỐI, không nhân đôi tiền', empty( $r['ok'] ), $r );
teq( '🔴 và dọn sạch lượt nộp rỗng vừa mở', 0, count( $KHO->nop ) );
teq( 'báo cáo vẫn thuộc lượt đã chen vào, không bị cướp', 77, (int) $KHO->bc['RPT-1']['nop_id'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. NỘP HỘ NGƯỜI KHÁC = XOÁ NỢ HỘ NGƯỜI KHÁC
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
nap( 900000, 0, null, 'Phan Như Hạnh' );
VHG_BC::$PIN = array( 'ten' => 'Nguyễn Văn Bin' );   // người khác cầm PIN cùng phạm vi cơ sở
$r = VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
t( '🔴 người khác không nộp hộ được', empty( $r['ok'] ), $r );
t( 'câu chối gọi tên người đã gửi báo cáo', false !== strpos( (string) $r['message'], 'Phan Như Hạnh' ), $r['message'] );
t( '🔴 và không khoá báo cáo của người ta', null === $KHO->bc['RPT-1']['bill_luc'], null );
/* 🔴 VÀ PHẢI CHỐI TRƯỚC KHI LƯU ẢNH LÊN HOST. Hai chốt cùng chặn được ca này (tầng báo cáo và
   tầng quỹ), nên chỉ nhìn "có chối không" thì bỏ chốt tầng trên bài vẫn xanh — phá thử bắt
   được đúng chỗ đó. Đếm số lần lưu ảnh phân biệt được hai tầng: chối ở tầng trên thì chưa có
   tấm ảnh nào được lưu; rơi xuống tầng quỹ mới chối thì ảnh đã nằm trên host rồi, và không ai
   đi dọn — mỗi lần bấm nhầm là một tệp rác vĩnh viễn. */
teq( '🔴 chối TRƯỚC khi lưu ảnh lên host, không để lại ảnh rác', 0, VHG_BC::$DEM_LUU_ANH );

/* Chốt tầng dưới cũng phải chặn — hai chốt cho một luật, nhưng ở đây là hai TẦNG khác nhau. */
nap( 900000, 0, null, 'Phan Như Hạnh' );
$r = VHG_QuyCau::nop_bao_cao( 'RPT-1', 'Nguyễn Văn Bin' );
t( '🔴 tầng quỹ cũng chặn nộp hộ', empty( $r['ok'] ), $r );
/* Canh CÂU CHỐI, không chỉ canh "có chối không". Tầng quỹ có hai lớp chặn ca này (chốt tên ở
   đầu hàm, và mệnh đề `AND nhan_vien=%s` trong câu UPDATE); bỏ lớp ngoài thì lớp trong vẫn giữ
   an toàn nhưng câu trả lời hoá ra "vừa được nộp ở một lượt khác" — nói sai hẳn chuyện, và
   người đọc sẽ đi tìm cái lượt nộp không tồn tại ấy. */
t( '🔴 và nói ĐÚNG chuyện: báo cáo của người khác, không phải "vừa nộp ở lượt khác"',
	false !== strpos( (string) $r['error'], 'Phan Như Hạnh' ), $r['error'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. QUÁ HẠN 24H
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
nap(); $KHO->bc['RPT-1']['tao_luc'] = 'CU';
$r = VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
t( 'quá hạn thì không đính bill qua màn này được', empty( $r['ok'] ), $r );
teq( 'và không mở lượt nộp', 0, count( $KHO->nop ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 7. KẾ TOÁN MỞ KHOÁ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
nap();
VHG_BC::nop_bill( 'RPT-1', $ANH, 'VCB 999', 'PIN' );
$anh_cu = (string) $KHO->bc['RPT-1']['bill_anh'];
$r = VHG_BC::mo_khoa_bill( 'RPT-1', 'Huỳnh Quang Thắng', 'nhân viên đính nhầm bill của ngày khác' );
t( '🔴 kế toán mở khoá được', ! empty( $r['ok'] ), $r );
t( '🔴 báo cáo hết khoá', null === $KHO->bc['RPT-1']['bill_luc'], $KHO->bc['RPT-1']['bill_luc'] );
teq( '🔴 lượt nộp đang chờ bị gỡ khỏi bảng của kế toán', 0, count( $KHO->nop ) );
teq( 'và báo cáo nhả nop_id', 0, (int) $KHO->bc['RPT-1']['nop_id'] );
/* ⚠️ ẢNH BILL CŨ PHẢI Ở LẠI. Mở khoá là cho sửa tiếp, không phải xoá dấu vết một lần đã bấm nộp
   — đúng lúc đi tìm xem chuyện gì đã xảy ra thì cần chính tấm ảnh ấy. */
teq( '🔴 ảnh bill CŨ vẫn còn nguyên, không bị xoá', $anh_cu, (string) $KHO->bc['RPT-1']['bill_anh'] );
t( 'ghi lại ai mở', 'Huỳnh Quang Thắng' === (string) $KHO->bc['RPT-1']['bill_mo_ai'], $KHO->bc['RPT-1']['bill_mo_ai'] );
t( '🔴 và ghi lại LÝ DO mở', false !== strpos( (string) $KHO->bc['RPT-1']['bill_ghichu'], 'đính nhầm bill' ),
	$KHO->bc['RPT-1']['bill_ghichu'] );

/* Không lý do thì không mở — ba tháng sau câu hỏi là "vì sao mở", không phải "có ai mở không". */
nap(); VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
$r = VHG_BC::mo_khoa_bill( 'RPT-1', 'Huỳnh Quang Thắng', '   ' );
t( '🔴 không ghi lý do -> không mở', empty( $r['ok'] ), $r );
t( 'và báo cáo vẫn khoá', '' !== (string) $KHO->bc['RPT-1']['bill_luc'], null );

/* Báo cáo không khoá thì không có gì để mở. */
nap();
$r = VHG_BC::mo_khoa_bill( 'RPT-1', 'Huỳnh Quang Thắng', 'thử' );
t( 'báo cáo không khoá -> chối', empty( $r['ok'] ), $r );

/* 🔴 KẾ TOÁN ĐÃ BẤM "ĐÃ NHẬN" THÌ KHÔNG MỞ ĐƯỢC NỮA. Tiền đã đếm, đã vào quầy — mở cho sửa số
   sau đó là để sổ khác hẳn xấp tiền vừa đếm xong. */
nap(); VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
$nid = (int) $KHO->bc['RPT-1']['nop_id'];
$KHO->nop[ $nid ]['trang_thai'] = 'da_nhan';
$r = VHG_BC::mo_khoa_bill( 'RPT-1', 'Huỳnh Quang Thắng', 'muốn sửa' );
t( '🔴 lượt nộp đã "Đã nhận" -> KHÔNG mở khoá', empty( $r['ok'] ), $r );
t( '🔴 và báo cáo VẪN khoá (không mở nửa vời)', '' !== (string) $KHO->bc['RPT-1']['bill_luc'], null );
teq( '🔴 và lượt nộp đã nhận KHÔNG bị gỡ', $nid, (int) $KHO->bc['RPT-1']['nop_id'] );
t( 'câu chối chỉ đường sang điều chỉnh quỹ',
	false !== strpos( (string) $r['error'], 'điều chỉnh' ), $r['error'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 8. ĐỐI CHỨNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* Bệ đỡ có thật sự chạy được tới nơi không — nếu không, mọi phép trên xanh vô nghĩa. */
nap();
$ok = VHG_BC::nop_bill( 'RPT-1', $ANH, '', 'PIN' );
t( 'đối chứng: bệ đỡ chạy được trọn một vòng đính bill -> nộp -> khoá',
	! empty( $ok['ok'] ) && count( $KHO->nop ) === 1 && '' !== (string) $KHO->bc['RPT-1']['bill_luc'], $ok );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 9. HAI ĐƯỜNG GHI CÒN LẠI CŨNG PHẢI ĐÓNG
 *
 * 🔴 CHỐT PHẢI Ở MÁY CHỦ, KHÔNG PHẢI Ở NÚT BẤM. Màn nhân viên giấu nút Sửa khi báo cáo đã khoá,
 *    nhưng cổng `bc_edit` và `bc_supplement` nhận lệnh từ bất cứ ai có PIN trong phạm vi. Nút
 *    bấm là dọn mắt; đây mới là cái khoá.
 *
 * 🔴 VÀ `nop_bosung()` LÀ CỬA DỄ QUÊN NHẤT. Nó không sửa chỉ số hay tiền của ghế nào, nên nhìn
 *    qua tưởng vô hại — nhưng nó sửa `nop_so_tien`, con số NHÂN VIÊN TỰ KHAI đã nộp bao nhiêu.
 *    Khai thêm sau khi đã bấm nộp bằng bill là để con số tự khai lệch hẳn với lượt nộp thật
 *    đang nằm chờ kế toán, mà hai con số ấy chính là thứ đem ra đối chiếu. Một cái khoá bỏ sót
 *    một cửa thì không phải khoá.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
VHG_CHOT::$PIN = array( 'ten' => 'Nguyễn Văn Bin' );

/* Chưa khoá: cả hai đường phải ĐI QUA được — nếu không thì mấy phép dưới xanh vì hàm chối vì
   một lý do khác hẳn, chẳng liên quan gì tới bill. */
nap();
$r = VHG_CHOT::sua_dong( 'RPT-1', 'AMTP01', array(), 'PIN' );
t( 'đối chứng: chưa khoá thì đường sửa đi qua được', ! empty( $r['DI_QUA_CHOT'] ), $r );
$r = VHG_CHOT::nop_bosung( 'RPT-1', '2026-09-05', 100000, 'cash', 'PIN' );
t( 'đối chứng: chưa khoá thì đường nộp bổ sung đi qua được', ! empty( $r['DI_QUA_CHOT'] ), $r );

/* Đã khoá: cả hai phải CHỐI, và chối vì ĐÚNG lý do bill. */
nap( 900000, 0, '2026-09-05 10:00:00' );
$r = VHG_CHOT::sua_dong( 'RPT-1', 'AMTP01', array(), 'PIN' );
t( '🔴 đã khoá -> đường SỬA chối', empty( $r['DI_QUA_CHOT'] ) && empty( $r['ok'] ), $r );
t( '🔴 và chối đúng vì bill, không phải vì lý do khác', ! empty( $r['khoa_bill'] ), $r );
t( 'câu chối nói ra lúc đã đính bill', false !== strpos( (string) $r['message'], '2026-09-05 10:00:00' ), $r['message'] );
t( 'và chỉ đường sang kế toán', false !== strpos( (string) $r['message'], 'kế toán' ), $r['message'] );

$r = VHG_CHOT::nop_bosung( 'RPT-1', '2026-09-05', 100000, 'cash', 'PIN' );
t( '🔴 đã khoá -> đường NỘP BỔ SUNG cũng chối', empty( $r['DI_QUA_CHOT'] ) && empty( $r['ok'] ), $r );
t( '🔴 và cũng chối đúng vì bill', ! empty( $r['khoa_bill'] ), $r );

/* ---------- KẾT ---------- */
if ( $TRUOT ) {
	echo "\n✗ HỎNG " . count( $TRUOT ) . " phép:\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	exit( 1 );
}
echo '✓ SẠCH — ' . $DAT . " phép: đính bill thì nộp và khoá, không bill thì không khoá, kế toán mở lại được, và ba đường ghi từ PIN đều đóng.\n";
