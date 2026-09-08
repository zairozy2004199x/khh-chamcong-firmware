<?php
/**
 * Plugin Name:       Nhà Ma · Bán vé theo khung giờ (Ghost Bride VIP)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Bán vé nhà ma theo KHUNG GIỜ, chạy thẳng trên host. Trang khách ở /ban-ve-nha-ma, trang quản trị ở /ban-ve-nha-ma/#quanly (kế toán duyệt tiền, soát vé tại cửa, đối soát). Sổ vé nằm trong MySQL của chính website — không Google Sheet, không Firebase. ĐỘC LẬP với plugin bán vé khu vui chơi và plugin ghế.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 *
 * ==============================================================================================
 * VÌ SAO LÀ PLUGIN CHỨ KHÔNG PHẢI MỘT FILE HTML THẢ LÊN HOST
 * ==============================================================================================
 * Bản HTML rời chạy được ngay, nhưng sổ vé nằm trong trình duyệt: khách đặt trên điện thoại của
 * họ thì máy quản trị KHÔNG thấy đơn ấy. Tức là nó chỉ bán được tại quầy bằng đúng một máy.
 *
 * Thành plugin thì sổ nằm trong MySQL của website — mọi máy nhìn chung một sổ, và đó mới là thứ
 * cho phép khách tự đặt từ điện thoại của họ.
 *
 * ==============================================================================================
 * 🔴 ĐẾM CHỖ LÀ VIỆC CỦA MÁY CHỦ, KHÔNG PHẢI CỦA TRANG
 * ==============================================================================================
 * Trang có vẽ "còn 7 chỗ" thì đó chỉ là ảnh chụp của mười phút trước. Người ta mở trang, đi pha
 * ấm trà, rồi mới bấm. Nên `dat()` đếm lại chỗ NGAY TRƯỚC KHI GHI, và còn kiểm lại một lần nữa
 * SAU KHI ghi (xem chú thích ở đó) — hai người bấm trong cùng một giây là chuyện có thật, và cái
 * giá của nó là hai đoàn cùng đứng ở cửa cho một khung giờ.
 *
 * ==============================================================================================
 * 🔴 TRANG KHÁCH KHÔNG BAO GIỜ ĐƯỢC NHẬN DANH SÁCH ĐƠN
 * ==============================================================================================
 * Nó chỉ nhận SỐ ĐÃ ĐẶT của từng khung. Bản HTML rời đẩy cả sổ xuống trình duyệt (vì không có
 * máy chủ để đếm hộ) — nghĩa là ai mở trang cũng xem được tên và số điện thoại của mọi khách.
 * Ở đây thì không: muốn xem đơn phải có số điện thoại của chính mình, muốn xem cả sổ phải qua PIN.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'NHAMA' ) ) :

class NHAMA {

	const NS   = 'nhama/v1';
	const BANG = 'nhama_don';
	const VER  = '1.0.0';

	/** Trạng thái đơn — thứ tự này cũng là vòng đời. */
	const TT = array(
		'giu_cho'   => 'Đang giữ chỗ',
		'cho_duyet' => 'Chờ duyệt thanh toán',
		'cho_vao'   => 'Chờ Check-in',
		'da_vao'    => 'Đã vào',
		'huy'       => 'Bị huỷ',
	);

	/**
	 * Đơn CÒN CHIẾM CHỖ.
	 * ⚠️ Có cả `giu_cho` — đơn mới giữ, chưa trả đồng nào. Giữ chỗ mà không trừ chỗ thì hai đoàn
	 *    cùng một khung, và đoàn thứ hai chỉ biết mình hụt lúc đã tới cửa.
	 */
	const CHIEM = array( 'giu_cho', 'cho_duyet', 'cho_vao', 'da_vao' );

	/**
	 * Mã đơn: GB + 8 ký tự, BỎ HẲN 0/O và 1/I/L.
	 * Khách đọc mã từ ảnh chụp màn hình rồi gõ tay ở cửa, có khi nhân viên đọc hộ qua điện thoại.
	 * Một mã có chữ O cạnh số 0 là một cuộc cãi nhau ở cửa, và người thua luôn là khách.
	 */
	const CHU_MA = 'ABCDEFGHJKMNPQRTUVWXY23456789';

	public static function cf_md() {
		return array(
			'gia'    => 100000,   // đồng / 1 người
			'suc'    => 10,       // người tối đa mỗi khung giờ
			'mo'     => '10:10',  // khung đầu tiên
			'dong'   => '11:45',  // khung cuối cùng
			'buoc'   => 5,        // mỗi khung cách nhau mấy phút
			'ngayMo' => 14,       // mở bán trước bao nhiêu ngày
			'denSom' => 10,       // khách phải có mặt trước bao nhiêu phút
			'quet'   => 6000,     // màn quản trị tự nạp lại, mili giây
			'am'     => 1,        // kêu chuông khi có đơn mới chờ duyệt
			'ten'    => 'GHOST BRIDE VIP',
			'phu'    => 'Nghi thức phân luồng dành riêng cho Khách Mời Danh Dự',
		);
	}
	public static function cf() {
		$c = get_option( 'nhama_cf' );
		return array_merge( self::cf_md(), is_array( $c ) ? $c : array() );
	}
	public static function slug() {
		$s = (string) get_option( 'nhama_slug', 'ban-ve-nha-ma' );
		return $s !== '' ? $s : 'ban-ve-nha-ma';
	}
	public static function t() { global $wpdb; return $wpdb->prefix . self::BANG; }

	// ========================================================================== dựng bảng
	public static function cai_dat() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		dbDelta( 'CREATE TABLE ' . self::t() . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma VARCHAR(24) NOT NULL,
			ten VARCHAR(120) NOT NULL DEFAULT '',
			sdt VARCHAR(20) NOT NULL DEFAULT '',
			ngay DATE NOT NULL,
			gio VARCHAR(5) NOT NULL DEFAULT '',
			sl INT NOT NULL DEFAULT 1,
			tien BIGINT NOT NULL DEFAULT 0,
			tt VARCHAR(12) NOT NULL DEFAULT 'giu_cho',
			ghi VARCHAR(255) NOT NULL DEFAULT '',
			vao_luc DATETIME NULL,
			tao DATETIME NOT NULL,
			sua DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma (ma),
			KEY khung (ngay,gio),
			KEY nguoi (sdt),
			KEY trang_thai (tt)
		) $c" );
		if ( ! get_option( 'nhama_pin_bam' ) ) { self::dat_pin( '246810' ); }
		update_option( 'nhama_ver', self::VER );
	}

	/** PIN cất bằng dấu băm + muối. Sổ này không đáng để ai đọc được PIN từ bảng cấu hình. */
	public static function dat_pin( $pin ) {
		$muoi = wp_generate_password( 16, false );
		update_option( 'nhama_pin_muoi', $muoi );
		update_option( 'nhama_pin_bam', hash( 'sha256', $muoi . $pin ) );
	}
	public static function pin_dung( $pin ) {
		$muoi = (string) get_option( 'nhama_pin_muoi', '' );
		$bam  = (string) get_option( 'nhama_pin_bam', '' );
		if ( '' === $bam ) { return false; }
		return hash_equals( $bam, hash( 'sha256', $muoi . (string) $pin ) );
	}

	// ========================================================================== gài vào WordPress
	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?nhama=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ) );
		if ( get_option( 'nhama_ver' ) !== self::VER ) { self::cai_dat(); flush_rewrite_rules( false ); }
	}
	public static function query_vars( $v ) { $v[] = 'nhama'; return $v; }

	public static function phuc_vu() {
		$la = ( (int) get_query_var( 'nhama' ) === 1 );
		if ( ! $la && isset( $_GET['nhama'] ) ) { $la = true; }
		if ( ! $la ) {
			/* Luật đường dẫn chưa được nạp lại thì so bằng chính địa chỉ — tem in / link đã gửi
			   cho khách không đợi ai đi bấm Lưu Permalinks. */
			$d = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
			$d = trim( (string) wp_parse_url( $d, PHP_URL_PATH ), '/' );
			$s = self::slug();
			$la = ( $d === $s || substr( $d, - ( strlen( $s ) + 1 ) ) === '/' . $s );
		}
		if ( ! $la ) { return; }
		nocache_headers();
		self::trang();
		if ( ! defined( 'NHAMA_TEST' ) ) { exit; }
	}

	public static function rest() {
		register_rest_route( self::NS, '/x', array(
			'methods'  => 'POST',
			'permission_callback' => '__return_true',
			'callback' => array( __CLASS__, 'cong' ),
		) );
	}

	// ========================================================================== khung giờ
	private static function phut( $hhmm ) {
		$p = explode( ':', (string) $hhmm );
		return ( (int) $p[0] ) * 60 + ( isset( $p[1] ) ? (int) $p[1] : 0 );
	}
	private static function gio_chu( $p ) {
		return str_pad( (string) intdiv( $p, 60 ), 2, '0', STR_PAD_LEFT ) . ':'
			. str_pad( (string) ( $p % 60 ), 2, '0', STR_PAD_LEFT );
	}
	public static function ds_khung( $cf = null ) {
		$cf = $cf ? $cf : self::cf();
		$a = self::phut( $cf['mo'] ); $b = self::phut( $cf['dong'] );
		$buoc = max( 1, (int) $cf['buoc'] );
		$ra = array();
		if ( $b < $a ) { $b = $a; }
		for ( $p = $a; $p <= $b; $p += $buoc ) { $ra[] = self::gio_chu( $p ); }
		return $ra;
	}
	/** Số khách ĐANG CHIẾM CHỖ của một khung. */
	public static function da_dat( $ngay, $gio ) {
		global $wpdb;
		$t = self::t();
		$in = "'" . implode( "','", self::CHIEM ) . "'";
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(sl),0) FROM $t WHERE ngay=%s AND gio=%s AND tt IN ($in)", $ngay, $gio ) );
	}
	/** Khung đã trôi qua thì thôi bán — tính bằng GIỜ MÁY CHỦ, không tin giờ điện thoại. */
	public static function da_qua( $ngay, $gio ) {
		if ( $ngay !== current_time( 'Y-m-d' ) ) { return $ngay < current_time( 'Y-m-d' ); }
		return self::phut( $gio ) <= self::phut( current_time( 'H:i' ) );
	}

	// ========================================================================== cổng lệnh
	private static function than( $req ) {
		$d = $req->get_json_params();
		if ( ! is_array( $d ) ) { $d = $req->get_params(); }
		return is_array( $d ) ? $d : array();
	}
	private static function chu( $d, $k, $dai = 190 ) {
		return mb_substr( sanitize_text_field( isset( $d[ $k ] ) ? (string) $d[ $k ] : '' ), 0, $dai );
	}
	private static function sdt_sach( $s ) { return preg_replace( '/[^0-9+]/', '', (string) $s ); }
	private static function ma_moi() {
		$s = '';
		for ( $i = 0; $i < 8; $i++ ) { $s .= substr( self::CHU_MA, wp_rand( 0, strlen( self::CHU_MA ) - 1 ), 1 ); }
		return 'GB-' . $s;
	}
	private static function loi( $chu ) { return array( 'ok' => false, 'error' => $chu ); }

	public static function cong( $req ) {
		$d    = self::than( $req );
		$viec = preg_replace( '/[^a-z_]/', '', strtolower( (string) ( isset( $d['viec'] ) ? $d['viec'] : '' ) ) );
		$cf   = self::cf();

		/* ------------------------------------------------------------------ việc công khai */
		if ( 'goi' === $viec )     { return self::v_goi( $d, $cf ); }
		if ( 'dat' === $viec )     { return self::v_dat( $d, $cf ); }
		if ( 'cua_toi' === $viec ) { return self::v_cua_toi( $d ); }
		if ( 'bao_ck' === $viec )  { return self::v_bao_ck( $d ); }
		if ( 'vao' === $viec )     { return self::v_vao( $d ); }

		/* ------------------------------------------------------------------ từ đây phải có thẻ */
		if ( ! self::the_dung( isset( $d['the'] ) ? $d['the'] : '' ) ) {
			return array( 'ok' => false, 'ma' => 'het_phien', 'error' => 'Phiên đã hết — nhập PIN lại.' );
		}
		if ( 'ds' === $viec )    { return self::v_ds( $cf ); }
		if ( 'doi' === $viec )   { return self::v_doi( $d ); }
		if ( 'soat' === $viec )  { return self::v_soat( $d ); }
		if ( 'cai' === $viec )   { return self::v_cai( $d, $cf ); }
		if ( 'mau' === $viec )   { return self::v_mau( $cf ); }
		if ( 'xoa' === $viec )   { return self::v_xoa(); }
		return self::loi( 'Việc không rõ: ' . $viec );
	}

	/** Trang khách hỏi: khung giờ nào còn chỗ. CHỈ trả về CON SỐ, không trả về đơn của ai cả. */
	private static function v_goi( $d, $cf ) {
		$ngay = self::chu( $d, 'ngay', 10 );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { $ngay = current_time( 'Y-m-d' ); }
		$khung = array();
		foreach ( self::ds_khung( $cf ) as $gio ) {
			$khung[] = array( 'gio' => $gio, 'dat' => self::da_dat( $ngay, $gio ),
				'qua' => self::da_qua( $ngay, $gio ) ? 1 : 0 );
		}
		return array( 'ok' => true, 'ngay' => $ngay, 'khung' => $khung,
			'cf' => array( 'gia' => (int) $cf['gia'], 'suc' => (int) $cf['suc'],
				'ngayMo' => (int) $cf['ngayMo'], 'denSom' => (int) $cf['denSom'],
				'ten' => $cf['ten'], 'phu' => $cf['phu'] ),
			'hnay' => current_time( 'Y-m-d' ) );
	}

	private static function v_dat( $d, $cf ) {
		global $wpdb;
		$ngay = self::chu( $d, 'ngay', 10 );
		$gio  = self::chu( $d, 'gio', 5 );
		$ten  = self::chu( $d, 'ten', 120 );
		$sdt  = self::sdt_sach( isset( $d['sdt'] ) ? $d['sdt'] : '' );
		$sl   = max( 1, (int) ( isset( $d['sl'] ) ? $d['sl'] : 1 ) );

		if ( mb_strlen( $ten ) < 2 )   { return self::loi( 'Nhập tên người đại diện.' ); }
		if ( strlen( $sdt ) < 9 )      { return self::loi( 'Số điện thoại chưa đúng.' ); }
		if ( ! in_array( $gio, self::ds_khung( $cf ), true ) ) { return self::loi( 'Khung giờ không có trong lịch.' ); }
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { return self::loi( 'Ngày không hợp lệ.' ); }
		if ( self::da_qua( $ngay, $gio ) ) { return self::loi( 'Khung ' . $gio . ' đã qua giờ.' ); }
		if ( $sl > (int) $cf['suc'] )      { return self::loi( 'Mỗi khung nhận tối đa ' . (int) $cf['suc'] . ' người.' ); }

		/* Hãm tay: một số điện thoại không thể đẻ ra hai chục đơn trong mười phút. Không có nó thì
		   một người bấm nghịch là kín cả buổi mà không ai trả đồng nào. */
		$k = 'nhama_dat_' . md5( $sdt );
		if ( (int) get_transient( $k ) >= 6 ) {
			return self::loi( 'Số này vừa giữ nhiều chỗ liên tiếp — chờ 10 phút rồi đặt tiếp, hoặc gọi ban tổ chức.' );
		}
		if ( self::da_dat( $ngay, $gio ) + $sl > (int) $cf['suc'] ) {
			return self::loi( 'Khung ' . $gio . ' vừa hết chỗ cho ' . $sl . ' người. Chọn khung khác giúp em.' );
		}

		$ma  = '';
		for ( $i = 0; $i < 6 && '' === $ma; $i++ ) {
			$thu = self::ma_moi();
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t() . ' WHERE ma=%s', $thu ) ) ) { $ma = $thu; }
		}
		if ( '' === $ma ) { return self::loi( 'Không cấp được mã, thử lại giúp em.' ); }

		$luc = current_time( 'mysql' );
		$ok  = $wpdb->insert( self::t(), array(
			'ma' => $ma, 'ten' => $ten, 'sdt' => $sdt, 'ngay' => $ngay, 'gio' => $gio,
			'sl' => $sl, 'tien' => $sl * (int) $cf['gia'], 'tt' => 'giu_cho',
			'tao' => $luc, 'sua' => $luc ) );
		if ( false === $ok ) { return self::loi( 'Không ghi được vào sổ.' ); }
		$id = (int) $wpdb->insert_id;
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );

		/* 🔴 KIỂM LẠI SAU KHI GHI — chỗ này là chỗ duy nhất chặn được hai người bấm trong cùng một
		   giây. Cả hai cùng thấy còn chỗ, cả hai cùng ghi. Nên sau khi ghi, đếm lại phần đã chiếm
		   của những đơn CÓ id NHỎ HƠN HOẶC BẰNG đơn mình: ai vào sổ trước thì giữ chỗ, ai tràn ra
		   ngoài sức chứa thì tự rút. Luật "id nhỏ thắng" là luật xác định, nên hai lượt chạy song
		   song không bao giờ cùng rút lui và cũng không bao giờ cùng ở lại. */
		$in  = "'" . implode( "','", self::CHIEM ) . "'";
		$den = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(sl),0) FROM ' . self::t()
			. " WHERE ngay=%s AND gio=%s AND tt IN ($in) AND id<=%d", $ngay, $gio, $id ) );
		if ( $den > (int) $cf['suc'] ) {
			$wpdb->delete( self::t(), array( 'id' => $id ) );
			return self::loi( 'Khung ' . $gio . ' vừa có người giữ trước mất rồi. Chọn khung khác giúp em.' );
		}

		return array( 'ok' => true, 'don' => self::don_theo_ma( $ma ) );
	}

	private static function v_cua_toi( $d ) {
		global $wpdb;
		$sdt = self::sdt_sach( isset( $d['sdt'] ) ? $d['sdt'] : '' );
		if ( strlen( $sdt ) < 9 ) { return self::loi( 'Số điện thoại chưa đúng.' ); }
		/* Hãm dò: số điện thoại là thứ đoán được. Không hãm thì một máy dò quét hết đầu số. */
		$k = 'nhama_tra_' . md5( self::ip() );
		if ( (int) get_transient( $k ) >= 30 ) { return self::loi( 'Tra quá nhiều lần — chờ 10 phút.' ); }
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT ma,ten,sdt,ngay,gio,sl,tien,tt,ghi FROM ' . self::t()
			. ' WHERE sdt=%s ORDER BY ngay DESC, gio DESC LIMIT 50', $sdt ), ARRAY_A );
		return array( 'ok' => true, 'don' => $r ? $r : array() );
	}

	private static function v_bao_ck( $d ) {
		global $wpdb;
		$ma = strtoupper( self::chu( $d, 'ma', 24 ) );
		$don = self::don_theo_ma( $ma );
		if ( ! $don ) { return self::loi( 'Không thấy thiệp này.' ); }
		if ( 'giu_cho' !== $don['tt'] ) { return array( 'ok' => true, 'don' => $don ); }
		$wpdb->update( self::t(), array( 'tt' => 'cho_duyet', 'sua' => current_time( 'mysql' ) ),
			array( 'ma' => $ma ) );
		return array( 'ok' => true, 'don' => self::don_theo_ma( $ma ) );
	}

	// -------------------------------------------------------------------- cửa của nhân viên
	private static function ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	}
	private static function v_vao( $d ) {
		$k = 'nhama_pin_' . md5( self::ip() );
		if ( (int) get_transient( $k ) >= 10 ) {
			return self::loi( 'Gõ sai quá nhiều lần — chờ 10 phút.' );
		}
		if ( ! self::pin_dung( isset( $d['pin'] ) ? $d['pin'] : '' ) ) {
			set_transient( $k, (int) get_transient( $k ) + 1, 600 );
			return self::loi( 'PIN chưa đúng.' );
		}
		delete_transient( $k );
		$the = wp_generate_password( 40, false );
		set_transient( 'nhama_the_' . $the, 1, 8 * 3600 );
		return array( 'ok' => true, 'the' => $the );
	}
	/**
	 * 🔴 THẺ KIỂM Ở MÁY CHỦ. Bản HTML rời gác bằng PIN nằm trong trang — ai xem mã nguồn là vào
	 *    được. Ở đây PIN đi lên máy chủ, máy chủ phát thẻ, và MỌI việc của nhân viên đều phải
	 *    mang thẻ. Xem mã nguồn trang bây giờ không cho ai thêm quyền gì.
	 */
	private static function the_dung( $the ) {
		$the = preg_replace( '/[^A-Za-z0-9]/', '', (string) $the );
		if ( '' === $the ) { return false; }
		return (bool) get_transient( 'nhama_the_' . $the );
	}

	private static function v_ds( $cf ) {
		global $wpdb;
		$r = $wpdb->get_results( 'SELECT ma,ten,sdt,ngay,gio,sl,tien,tt,ghi,vao_luc,tao FROM '
			. self::t() . ' ORDER BY id DESC LIMIT 2000', ARRAY_A );
		return array( 'ok' => true, 'don' => $r ? $r : array(), 'cf' => $cf,
			'khung' => self::ds_khung( $cf ), 'hnay' => current_time( 'Y-m-d' ) );
	}

	/** Đổi trạng thái một đơn: duyệt / từ chối / huỷ / mở lại. */
	private static function v_doi( $d ) {
		global $wpdb;
		$ma  = strtoupper( self::chu( $d, 'ma', 24 ) );
		$tt  = self::chu( $d, 'tt', 12 );
		$ghi = self::chu( $d, 'ghi', 255 );
		if ( ! isset( self::TT[ $tt ] ) ) { return self::loi( 'Trạng thái không hợp lệ.' ); }
		$don = self::don_theo_ma( $ma );
		if ( ! $don ) { return self::loi( 'Không thấy đơn ' . $ma . '.' ); }
		$wpdb->update( self::t(),
			array( 'tt' => $tt, 'ghi' => $ghi, 'sua' => current_time( 'mysql' ) ),
			array( 'ma' => $ma ) );
		return array( 'ok' => true, 'don' => self::don_theo_ma( $ma ) );
	}

	/**
	 * Soát vé tại cửa.
	 * ⚠️ SAI NGÀY THÌ CẢNH BÁO CHỨ KHÔNG CHẶN. Khách tới sớm hoặc trễ một khung là chuyện thường;
	 *    chặn cứng thì nhân viên phải gọi quản lý giữa lúc đông nhất. Nói ra để người ở cửa quyết
	 *    định — họ đang nhìn thấy khách, máy chủ thì không.
	 * 🔴 VÉ ĐÃ VÀO THÌ CHẶN HẲN lần hai. Đó là toàn bộ lý do có màn soát vé.
	 */
	private static function v_soat( $d ) {
		global $wpdb;
		$ma = strtoupper( self::chu( $d, 'ma', 40 ) );
		if ( preg_match( '/GB-[A-Z0-9]{4,}/', $ma, $m ) ) { $ma = $m[0]; }
		$don = self::don_theo_ma( $ma );
		if ( ! $don )                       { return self::loi( 'Không có mã ' . $ma . ' trong sổ.' ); }
		if ( 'huy' === $don['tt'] )         { return self::loi( $don['ten'] . ' — vé ĐÃ HUỶ (' . ( $don['ghi'] ? $don['ghi'] : 'không rõ lý do' ) . ').' ); }
		if ( 'giu_cho' === $don['tt'] )     { return self::loi( $don['ten'] . ' — CHƯA THANH TOÁN. Thu tiền rồi duyệt ở màn Xét duyệt.' ); }
		if ( 'cho_duyet' === $don['tt'] )   { return self::loi( $don['ten'] . ' — đang CHỜ KẾ TOÁN DUYỆT tiền.' ); }
		if ( 'da_vao' === $don['tt'] )      { return self::loi( $don['ten'] . ' — vé này ĐÃ VÀO lúc ' . $don['vao_luc'] . '. Không cho vào lần hai.' ); }
		$wpdb->update( self::t(), array( 'tt' => 'da_vao', 'vao_luc' => current_time( 'mysql' ),
			'sua' => current_time( 'mysql' ) ), array( 'ma' => $ma ) );
		$canh = ( $don['ngay'] !== current_time( 'Y-m-d' ) )
			? ' ⚠️ vé của ngày ' . $don['ngay'] . ', không phải hôm nay.' : '';
		return array( 'ok' => true, 'chu' => '✔ CHO VÀO — ' . $don['ten'] . ', ' . $don['sl']
			. ' khách, khung ' . $don['gio'] . '.' . $canh, 'don' => self::don_theo_ma( $ma ) );
	}

	private static function v_cai( $d, $cf ) {
		$moi = isset( $d['cf'] ) && is_array( $d['cf'] ) ? $d['cf'] : array();
		$so  = array( 'gia', 'suc', 'buoc', 'ngayMo', 'denSom', 'quet', 'am' );
		foreach ( $so as $k ) { if ( isset( $moi[ $k ] ) ) { $cf[ $k ] = max( 0, (int) $moi[ $k ] ); } }
		foreach ( array( 'mo', 'dong' ) as $k ) {
			if ( isset( $moi[ $k ] ) && preg_match( '/^\d{1,2}:\d{2}$/', (string) $moi[ $k ] ) ) {
				$cf[ $k ] = (string) $moi[ $k ];
			}
		}
		foreach ( array( 'ten', 'phu' ) as $k ) {
			if ( isset( $moi[ $k ] ) ) { $cf[ $k ] = mb_substr( sanitize_text_field( (string) $moi[ $k ] ), 0, 120 ); }
		}
		$cf['suc']  = max( 1, (int) $cf['suc'] );
		$cf['buoc'] = max( 1, (int) $cf['buoc'] );
		update_option( 'nhama_cf', $cf );
		$pin = isset( $d['pin'] ) ? preg_replace( '/\D/', '', (string) $d['pin'] ) : '';
		if ( strlen( $pin ) >= 4 ) { self::dat_pin( $pin ); }
		return array( 'ok' => true, 'cf' => $cf, 'khung' => self::ds_khung( $cf ) );
	}

	/** Đơn mẫu — để bấm thử cho hết các màn khi sổ còn trống. Xoá bằng nút "Xoá sạch sổ". */
	private static function v_mau( $cf ) {
		global $wpdb;
		$ten = array( 'Gia Huy', 'Hân', 'Đức Bùi', 'Nguyễn Phương Linh', 'Trần Mỹ Duyên', 'Lê Anh Khoa' );
		$tts = array( 'giu_cho', 'cho_duyet', 'cho_vao', 'cho_vao', 'da_vao', 'huy' );
		$kh  = self::ds_khung( $cf );
		for ( $i = 0; $i < 12; $i++ ) {
			$sl  = wp_rand( 1, 4 );
			$tt  = $tts[ $i % count( $tts ) ];
			$luc = current_time( 'mysql' );
			$wpdb->insert( self::t(), array(
				'ma' => self::ma_moi(), 'ten' => $ten[ $i % count( $ten ) ],
				'sdt' => '09' . wp_rand( 10000000, 99999999 ),
				'ngay' => gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) ) + ( $i % 3 ) * 86400 ),
				'gio' => $kh[ $i % count( $kh ) ], 'sl' => $sl, 'tien' => $sl * (int) $cf['gia'],
				'tt' => $tt, 'ghi' => ( 'huy' === $tt ? 'không chuyển khoản' : '' ),
				'tao' => $luc, 'sua' => $luc ) );
		}
		return array( 'ok' => true );
	}
	private static function v_xoa() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . self::t() );
		return array( 'ok' => true );
	}

	public static function don_theo_ma( $ma ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT ma,ten,sdt,ngay,gio,sl,tien,tt,ghi,vao_luc,tao FROM ' . self::t() . ' WHERE ma=%s',
			$ma ), ARRAY_A );
		return $r ? $r : null;
	}

	// ========================================================================== trang
	public static function trang() {
		$cf = self::cf();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>' . esc_html( $cf['ten'] ) . '</title>'
			. '<link rel="preconnect" href="https://fonts.googleapis.com">'
			. '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
			. '<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&'
			. 'family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">'
			. '<style>' . self::css() . '</style></head><body>'
			. self::than_trang()
			. '<script>var NM = ' . wp_json_encode( array(
				'api' => esc_url_raw( rest_url( self::NS . '/x' ) ),
				'cf'  => array( 'ten' => $cf['ten'], 'phu' => $cf['phu'] ),
			) ) . ';</script><script>' . self::js() . '</script></body></html>';
	}

	private static function than_trang() {
		$cf = self::cf();
		return '<div id="apKhach">'
		. '<div class="kh-bao">'
		. '<div class="kh-dau">'
		. '<button class="kh-nut-dau" id="btTraCuu">🔎 TRA CỨU LỜI MỜI</button>'
		. '<button class="kh-nut-dau" id="btThiep">🎟 THIỆP CỦA TÔI</button></div>'
		. '<div class="kh-tieu"><h1>' . esc_html( $cf['ten'] ) . '</h1><div class="kh-gach"></div>'
		. '<p class="kh-phu">' . esc_html( $cf['phu'] ) . '</p></div>'
		. '<div class="kh-luoi"><div>'
		. '<div class="the"><h2 class="the-tieu">⧗ 1. Lựa chọn thời khắc</h2>'
		. '<select class="o" id="oNgay"></select><div class="gio-luoi" id="luoiGio"></div></div>'
		. '<div class="the" style="margin-top:18px"><h2 class="the-tieu">🖋 2. Ghi danh khách mời</h2>'
		. '<label class="nhan">Thời khắc được chọn:</label>'
		. '<input class="o" id="oChon" readonly placeholder="Chưa chọn khung giờ">'
		. '<label class="nhan">Danh Tính Người Đại Diện:</label>'
		. '<input class="o" id="oTen" placeholder="Nhập tên người đại diện tham dự...">'
		. '<label class="nhan">Tín Hiệu Liên Lạc (SĐT Zalo để nhận thư):</label>'
		. '<input class="o" id="oSdt" inputmode="tel" placeholder="Ví dụ: 0912345678">'
		. '<label class="nhan" id="nhanSl">Số lượng thành viên:</label>'
		. '<select class="o" id="oSl"></select>'
		. '<label class="cam-ket"><input type="checkbox" id="oCamKet"><span id="chuCamKet"></span></label>'
		. '<button class="nut-chinh" id="btGiuCho">🔑 Khởi tạo mã giữ chỗ - 0₫</button>'
		. '<div id="baoDat"></div></div></div>'
		. '<div class="the"><h2 class="the-tieu">👁 Tình trạng tiền sảnh</h2>'
		. '<p class="kh-phu" style="text-align:left;margin:-8px 0 12px;font-size:12px">'
		. 'Hệ thống trung tâm tự động cập nhật số lượng chỗ trống tại sảnh thực tế.</p>'
		. '<div class="ts-cuon" id="tsCuon"></div></div></div></div></div>'
		. '<div id="apQL" class="an"><aside class="q-ben">'
		. '<div class="q-hieu"><b>ADMIN PRO</b><i>ONLINE CLOUD</i></div>'
		. '<nav class="q-nav" id="qNav"></nav></aside><div class="q-than">'
		. '<div class="q-tren"><div class="noi"><span class="q-cham-tt"></span>'
		. '<span id="qNoiTT">Sổ chung trên máy chủ</span></div>'
		. '<button class="q-nut xam" id="btAm">🔔 Cảnh báo âm thanh: Đã Bật</button></div>'
		. '<div class="q-noi-dung" id="qNoiDung"></div></div></div><div id="hopThoai"></div>';
	}

	private static function css() {
		return <<<'CSS'
:root{--den:#08070a;--vien:#241d24;--do:#8b1a1a;--do-sang:#c1272d;--vang:#c8a84b;
--vang-nhat:#e6d6a2;--chu:#e8e2d9;--chu-mo:#9b9189;
--q-nen:#0b0e11;--q-nen2:#12161c;--q-the:#171c23;--q-vien:#242c36;--q-xanh:#2f81f7;
--q-luc:#2ea043;--q-do:#f85149;--q-cam:#d29922;--q-chu:#e6edf3;--q-chu-mo:#8b949e}
*{box-sizing:border-box}html,body{margin:0;padding:0}html{color-scheme:dark}
body{background:var(--den);color:var(--chu);font-family:"Be Vietnam Pro",system-ui,-apple-system,
"Segoe UI",Roboto,Arial,sans-serif;font-size:15px;line-height:1.6;-webkit-text-size-adjust:100%}
.an{display:none !important}button,input,select,textarea{font:inherit;color:inherit}
button{cursor:pointer}a{color:inherit}
#apKhach{min-height:100vh;background:radial-gradient(1200px 600px at 15% -10%,#241016 0,transparent 60%),
radial-gradient(900px 500px at 110% 20%,#1a1016 0,transparent 55%),
repeating-linear-gradient(115deg,rgba(255,255,255,.012) 0 2px,transparent 2px 7px),var(--den)}
.kh-bao{max-width:1080px;margin:0 auto;padding:20px 16px 64px}
.kh-dau{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:26px}
.kh-nut-dau{background:#15111a;border:1px solid var(--vien);color:var(--vang-nhat);border-radius:8px;
padding:9px 14px;font-size:13px;font-weight:600;letter-spacing:.02em}
.kh-nut-dau:hover{border-color:var(--vang);background:#1c1620}
.kh-tieu{text-align:center;margin:6px 0 34px}
.kh-tieu h1{font-family:Cinzel,Georgia,serif;font-size:clamp(30px,6vw,52px);letter-spacing:.12em;
margin:0;color:var(--do-sang);text-shadow:0 0 26px rgba(193,39,45,.35);font-weight:700}
.kh-gach{width:min(420px,70%);height:1px;margin:14px auto 12px;
background:linear-gradient(90deg,transparent,var(--do),transparent)}
.kh-phu{font-style:italic;color:var(--chu-mo);font-size:14px;margin:0}
.kh-luoi{display:grid;grid-template-columns:1.35fr 1fr;gap:20px;align-items:start}
@media(max-width:900px){.kh-luoi{grid-template-columns:1fr}}
.the{background:rgba(18,15,20,.86);border:1px solid var(--vien);border-radius:12px;padding:20px}
.the-tieu{display:flex;align-items:center;gap:10px;font-family:Cinzel,Georgia,serif;font-size:15px;
letter-spacing:.08em;text-transform:uppercase;color:var(--vang-nhat);margin:0 0 16px;padding-left:12px;
border-left:3px solid var(--do)}
.nhan{display:block;font-size:12px;color:var(--chu-mo);font-style:italic;margin:14px 0 6px}
.o{width:100%;background:#0c0a0e;border:1px solid var(--vien);border-radius:8px;padding:11px 12px;
color:var(--chu);outline:none}
.o:focus{border-color:var(--do);box-shadow:0 0 0 2px rgba(139,26,26,.25)}
.o::placeholder{color:#5d555c}
.gio-luoi{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-top:14px}
@media(max-width:640px){.gio-luoi{grid-template-columns:repeat(3,1fr)}}
.gio{background:#0e0c11;border:1px solid var(--vien);border-radius:8px;padding:10px 4px;
text-align:center;line-height:1.25}
.gio b{display:block;font-size:15px;font-weight:600}
.gio span{display:block;font-size:11px;color:var(--chu-mo);margin-top:2px}
.gio.duoc:hover{border-color:var(--vang);background:#171018}
.gio.chon{border-color:var(--do-sang);background:#1d1013;box-shadow:0 0 0 1px var(--do-sang) inset}
.gio.chon b,.gio.chon span{color:var(--vang-nhat)}
.gio.het{opacity:.34;cursor:not-allowed}.gio.het b,.gio.het span{color:#6b6068}
.ts-cuon{max-height:420px;overflow:auto;padding-right:6px}
.ts-cuon::-webkit-scrollbar{width:6px}.ts-cuon::-webkit-scrollbar-thumb{background:var(--do);border-radius:3px}
.ts-hang{display:grid;grid-template-columns:60px 1fr auto;align-items:center;gap:10px;padding:9px 2px;
border-bottom:1px solid #1b161b;font-size:13px}
.ts-hang i{font-style:normal;color:var(--chu-mo);font-size:12px}
.the-trang{background:#1c1a1d;border-radius:5px;padding:3px 9px;font-size:11px;font-weight:600;white-space:nowrap}
.the-trang.con{background:#12261a;color:#7ddba0}.the-trang.sap{background:#2a2110;color:#e0be6a}
.the-trang.het{background:#231a1a;color:#c9a0a0}
.cam-ket{display:flex;gap:10px;align-items:flex-start;background:#0e0b10;border-left:3px solid var(--do);
border-radius:6px;padding:12px;margin:16px 0;font-size:12.5px;color:var(--chu-mo)}
.cam-ket input{margin-top:3px;flex:none;width:16px;height:16px;accent-color:var(--do-sang)}
.nut-chinh{width:100%;background:linear-gradient(180deg,#6d1418,#4a0e11);border:1px solid #7c1c20;
color:var(--vang-nhat);border-radius:9px;padding:15px;font-family:Cinzel,Georgia,serif;font-size:14px;
letter-spacing:.1em;font-weight:700;text-transform:uppercase}
.nut-chinh:hover:not(:disabled){background:linear-gradient(180deg,#87181d,#5c1114)}
.nut-chinh:disabled{opacity:.45;cursor:not-allowed}
.bao{border-radius:8px;padding:11px 13px;font-size:13px;margin-top:12px}
.bao.hong{background:#2a1113;border:1px solid #5c1f22;color:#f0b8ba}
.bao.duoc{background:#10241a;border:1px solid #1f5c37;color:#a7e6c1}
.thiep{border:1px solid var(--vang);border-radius:12px;padding:20px;margin-top:14px;
background:linear-gradient(180deg,#151016,#0d0a0e)}
.thiep .ma{font-family:Cinzel,Georgia,serif;font-size:26px;letter-spacing:.16em;color:var(--vang);
text-align:center;margin:6px 0 4px;word-break:break-all}
.thiep .dong{display:flex;justify-content:space-between;gap:12px;padding:7px 0;
border-bottom:1px dashed #2a222a;font-size:13.5px}
.thiep .dong:last-of-type{border-bottom:0}.thiep .dong span{color:var(--chu-mo)}
.huong-dan{background:#0e0b10;border:1px solid var(--vien);border-radius:8px;padding:13px;
margin-top:14px;font-size:13px}.huong-dan b{color:var(--vang-nhat)}
.nut-phu{background:#191319;border:1px solid var(--vien);color:var(--chu);border-radius:8px;
padding:10px 14px;font-size:13px;font-weight:600}.nut-phu:hover{border-color:var(--vang)}
.hang-nut{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.man-che{position:fixed;inset:0;background:rgba(4,3,5,.86);display:flex;align-items:center;
justify-content:center;padding:18px;z-index:50}
.hop{background:#100d12;border:1px solid var(--vien);border-radius:12px;padding:22px;
width:min(420px,100%);position:relative;max-height:90vh;overflow:auto}
.hop-dong{position:absolute;top:10px;right:12px;background:none;border:0;color:var(--chu-mo);
font-size:20px;line-height:1}
#apQL{min-height:100vh;background:var(--q-nen);color:var(--q-chu);display:grid;grid-template-columns:260px 1fr}
@media(max-width:820px){#apQL{grid-template-columns:1fr}.q-ben{position:static !important}}
.q-ben{background:#000;border-right:1px solid var(--q-vien);min-height:100vh;position:sticky;top:0}
.q-hieu{padding:22px 18px;border-bottom:1px solid var(--q-vien);text-align:center}
.q-hieu b{display:block;font-size:20px;font-weight:700;letter-spacing:.14em}
.q-hieu i{display:inline-block;margin-top:7px;background:var(--q-luc);color:#04120a;border-radius:4px;
padding:2px 9px;font-size:10px;font-style:normal;font-weight:700;letter-spacing:.08em}
.q-nav{padding:8px 0}
.q-nav button{display:flex;align-items:center;gap:11px;width:100%;background:none;border:0;
border-left:3px solid transparent;color:var(--q-chu-mo);padding:13px 18px;font-size:14px;text-align:left}
.q-nav button:hover{background:#0e1319;color:var(--q-chu)}
.q-nav button.on{background:#0d1b2e;border-left-color:var(--q-xanh);color:#fff;font-weight:600}
.q-nav button.thoat{color:var(--q-do)}
.q-nav .cham{margin-left:auto;background:var(--q-do);color:#fff;border-radius:999px;min-width:20px;
height:20px;display:grid;place-items:center;font-size:11px;font-weight:700;padding:0 6px}
.q-tren{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
background:var(--q-nen2);border-bottom:1px solid var(--q-vien);padding:14px 22px;font-size:14px}
.q-tren .noi{display:flex;align-items:center;gap:9px;font-weight:600}
.q-cham-tt{width:10px;height:10px;border-radius:50%;background:var(--q-luc);flex:none}
.q-than{min-width:0}.q-noi-dung{padding:22px}
.q-tieu{display:flex;align-items:center;gap:10px;font-size:22px;font-weight:700;margin:0 0 18px}
.q-the{background:var(--q-the);border:1px solid var(--q-vien);border-radius:10px;padding:18px}
.q-so{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
@media(max-width:1180px){.q-so{grid-template-columns:1fr}}
.q-so .q-the{display:flex;align-items:center;gap:16px}
.q-bieu{width:52px;height:52px;border-radius:9px;display:grid;place-items:center;font-size:22px;flex:none}
.q-so small{display:block;color:var(--q-chu-mo);font-size:11.5px;letter-spacing:.06em;
text-transform:uppercase;font-weight:600}
.q-so b{font-size:26px;font-weight:700;line-height:1.2}
.q-thanh{height:9px;background:#0d1117;border-radius:5px;overflow:hidden;margin:14px 0 18px}
.q-thanh i{display:block;height:100%;background:linear-gradient(90deg,var(--q-xanh),var(--q-luc))}
.q-luoi-gio{display:grid;grid-template-columns:repeat(auto-fill,minmax(128px,1fr));gap:12px}
.q-gio{background:#10151b;border:1px solid var(--q-vien);border-radius:9px;padding:13px 8px;text-align:center}
.q-gio b{display:block;font-size:17px;font-weight:700}
.q-gio span{display:block;font-size:12px;color:var(--q-chu-mo);margin-top:3px}
.q-gio em{display:block;font-style:normal;font-size:12px;font-weight:600;margin-top:2px}
.q-gio.trong em{color:var(--q-chu-mo)}
.q-gio.sap{border-color:#5c4813}.q-gio.sap em{color:var(--q-cam)}
.q-gio.day{border-color:#5c2222}.q-gio.day em{color:var(--q-do)}
.q-nut{border:0;border-radius:8px;padding:11px 16px;font-size:13.5px;font-weight:700;color:#fff}
.q-nut.xanh{background:var(--q-xanh)}.q-nut.luc{background:var(--q-luc)}
.q-nut.do{background:var(--q-do)}.q-nut.xam{background:#21262d;color:var(--q-chu)}
.q-nut:hover{filter:brightness(1.12)}
.q-duyet{display:grid;grid-template-columns:150px 1fr auto;gap:18px;align-items:center}
@media(max-width:760px){.q-duyet{grid-template-columns:1fr}}
.q-bill{background:#0d1117;border:1px dashed var(--q-vien);border-radius:8px;height:150px;
display:grid;place-items:center;text-align:center;color:var(--q-chu-mo);font-size:12px;font-weight:600}
.q-bang{width:100%;border-collapse:collapse;font-size:13.5px}
.q-bang th{text-align:left;color:var(--q-chu-mo);font-size:11.5px;letter-spacing:.06em;
text-transform:uppercase;padding:12px 10px;border-bottom:1px solid var(--q-vien);white-space:nowrap}
.q-bang td{padding:12px 10px;border-bottom:1px solid #1b2129;vertical-align:top}
.q-bang tr:hover td{background:#10151b}.q-cuon{overflow-x:auto}
.nhan-tt{display:inline-block;border-radius:5px;padding:3px 9px;font-size:11.5px;font-weight:700;
border:1px solid;white-space:nowrap}
.tt-giu{background:#161b22;border-color:#30363d;color:var(--q-chu-mo)}
.tt-duyet{background:#2a1f08;border-color:#6b4c0e;color:#e3b341}
.tt-vao{background:#0d2416;border-color:#1a6b34;color:#56d364}
.tt-xong{background:#0d1b2e;border-color:#1f4e85;color:#79c0ff}
.tt-huy{background:#2a1214;border-color:#6b1f24;color:#ff7b72}
.q-zalo{display:inline-block;background:#0068ff;color:#fff;border-radius:5px;padding:3px 9px;
font-size:11.5px;font-weight:600;text-decoration:none;margin-top:4px}
.q-loc{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.q-o{background:#0d1117;border:1px solid var(--q-vien);border-radius:7px;padding:9px 12px;
color:var(--q-chu);outline:none}.q-o:focus{border-color:var(--q-xanh)}
.q-cam-hinh{background:#0d1117;border:1px solid var(--q-vien);border-radius:9px;overflow:hidden;
aspect-ratio:4/3;display:grid;place-items:center}
.q-cam-hinh video{width:100%;height:100%;object-fit:cover}
.q-doi{display:grid;grid-template-columns:1fr 1fr;gap:18px}
@media(max-width:820px){.q-doi{grid-template-columns:1fr}}
.q-nho{color:var(--q-chu-mo);font-size:12.5px}
CSS;
	}

	private static function js() {
		return <<<'JS'
/* ============================================================================================
 * Trang khách + trang quản trị. Mọi thứ đi qua ĐÚNG MỘT cổng: NM.api.
 * 🔴 Trang này KHÔNG tự đếm chỗ và KHÔNG tự quyết trạng thái đơn — máy chủ quyết. Trang chỉ vẽ.
 *    Đếm ở trình duyệt là đếm trên một ảnh chụp cũ, mà hai người bấm cùng lúc thì cả hai đều
 *    thấy "còn chỗ".
 * ========================================================================================== */
function g(id){ return document.getElementById(id); }
function esc(s){ var d=document.createElement("div"); d.textContent=(s==null?"":String(s)); return d.innerHTML; }
function tien(n){ return (Number(n)||0).toLocaleString("vi-VN")+"₫"; }
function hai(n){ return (n<10?"0":"")+n; }
function ngayISO(d){ return d.getFullYear()+"-"+hai(d.getMonth()+1)+"-"+hai(d.getDate()); }
function ngayVN(iso){ var p=String(iso).split("-"); return p.length===3?p[2]+"/"+p[1]+"/"+p[0]:iso; }
var THU=["Chủ Nhật","Thứ Hai","Thứ Ba","Thứ Tư","Thứ Năm","Thứ Sáu","Thứ Bảy"];
function sdtSach(s){ return String(s||"").replace(/[^0-9+]/g,""); }
var TT={ giu_cho:{chu:"Đang giữ chỗ",lop:"tt-giu"}, cho_duyet:{chu:"Chờ duyệt thanh toán",lop:"tt-duyet"},
  cho_vao:{chu:"Chờ Check-in",lop:"tt-vao"}, da_vao:{chu:"Đã vào",lop:"tt-xong"},
  huy:{chu:"Bị huỷ",lop:"tt-huy"} };

var THE = null;   /* thẻ phiên của nhân viên — máy chủ phát sau khi đúng PIN */
try { THE = sessionStorage.getItem("nhama_the")||null; } catch(e){}

/* 🔴 KHÔNG GỌI r.json() TRẦN. Hosting chèn trang chặn, hoặc PHP nổ, thì thân trả về là HTML và
   r.json() ném "Unexpected token <" — lỗi ấy trôi vào catch rồi bị nuốt, màn hình đứng im không
   nói gì. Đọc ra chữ trước, không phải JSON thì báo cả mã HTTP lẫn mấy chữ máy chủ nói. */
function api(viec, than){
  var t = Object.assign({viec:viec}, than||{});
  if (THE) { t.the = THE; }
  var ma = 0;
  return fetch(NM.api, { method:"POST", headers:{"Content-Type":"application/json"},
    body: JSON.stringify(t) })
  .then(function(r){ ma=r.status; return r.text(); })
  .then(function(chu){
    var j=null;
    try { j = JSON.parse(chu); } catch(e){
      var goi = ma>=500 ? " Máy chủ đang lỗi — báo quản trị xem nhật ký hosting."
              : (ma===403 ? " Hosting đang chặn đường này (tường lửa)."
              : (ma===404 ? " Sai đường dẫn — vào Cài đặt → Đường dẫn tĩnh bấm Lưu một lần." : ""));
      var dau = String(chu||"").replace(/<[^>]*>/g," ").replace(/\s+/g," ").trim().slice(0,120);
      throw new Error("Máy chủ trả về nội dung không đọc được (mã "+ma+")."+goi+(dau?" Máy chủ nói: "+dau:""));
    }
    if (j && j.ma==="het_phien"){ THE=null; try{ sessionStorage.removeItem("nhama_the"); }catch(e){}
      veTrang(); throw new Error(j.error||"Phiên đã hết."); }
    return j;
  });
}

/* ============================================================================== TRANG KHÁCH */
var CFK={gia:0,suc:10,ngayMo:14,denSom:10}, KHUNG=[], chonNgay=null, chonGio="", HNAY="";

function napKhach(){
  return api("goi", {ngay: chonNgay}).then(function(j){
    if (!j || !j.ok) { return; }
    CFK=j.cf; KHUNG=j.khung; HNAY=j.hnay;
    if (!chonNgay) { chonNgay=j.ngay; }
    veKhach();
  }).catch(function(e){
    g("luoiGio").innerHTML = "<div class='bao hong' style='grid-column:1/-1'>"+esc(e.message)+"</div>";
  });
}
function timKhung(gio){ for(var i=0;i<KHUNG.length;i++){ if(KHUNG[i].gio===gio) return KHUNG[i]; } return null; }
function conCho(gio){ var k=timKhung(gio); return k ? Math.max(0, CFK.suc-k.dat) : 0; }

function veNgay(){
  var s=g("oNgay"), h="", hnay=new Date(HNAY+"T00:00:00");
  if (isNaN(hnay)) { hnay=new Date(); }
  for (var i=0;i<CFK.ngayMo;i++){
    var d=new Date(hnay.getFullYear(),hnay.getMonth(),hnay.getDate()+i), iso=ngayISO(d);
    h+="<option value='"+iso+"'>"+(i===0?"[Hôm nay] ":"")+THU[d.getDay()]+" - "+ngayVN(iso)+"</option>";
  }
  s.innerHTML=h; s.value=chonNgay;
}
function veLuoiGio(){
  var h="";
  for (var i=0;i<KHUNG.length;i++){
    var k=KHUNG[i], con=CFK.suc-k.dat, het=(con<=0||k.qua);
    var chu = k.qua ? "Đã qua" : (con<=0 ? "Đóng" : con+" chỗ");
    h+="<button class='gio "+(het?"het":"duoc")+(chonGio===k.gio?" chon":"")+"'"+(het?" disabled":"")
      +" data-gio='"+k.gio+"'><b>"+k.gio+"</b><span>"+chu+"</span></button>";
  }
  g("luoiGio").innerHTML=h;
}
function veTienSanh(){
  var h="";
  for (var i=0;i<KHUNG.length;i++){
    var k=KHUNG[i], con=CFK.suc-k.dat, lop, chu;
    if (k.qua)       { lop="het"; chu="Đã qua"; }
    else if (con<=0) { lop="het"; chu="Khép Kín"; }
    else if (con<=3) { lop="sap"; chu="Còn "+con+" chỗ"; }
    else             { lop="con"; chu="Còn "+con+" chỗ"; }
    h+="<div class='ts-hang'><b>"+k.gio+"</b><i>"+((k.qua||con<=0)?"-":"trống "+con+"/"+CFK.suc)
      +"</i><span class='the-trang "+lop+"'>"+chu+"</span></div>";
  }
  g("tsCuon").innerHTML=h;
}
function veSoLuong(){
  var toi = chonGio ? Math.min(CFK.suc, conCho(chonGio)) : CFK.suc, h="";
  if (toi<1) { toi=1; }
  for (var i=1;i<=toi;i++){ h+="<option value='"+i+"'>"+i+" Thành Viên</option>"; }
  g("oSl").innerHTML=h;
  g("nhanSl").textContent="Số lượng thành viên (Tối đa "+toi+" người):";
}
function veKhach(){
  veNgay(); veLuoiGio(); veTienSanh(); veSoLuong();
  g("oChon").value = chonGio ? (THU[new Date(chonNgay+"T00:00:00").getDay()]+" "+ngayVN(chonNgay)+" — "+chonGio) : "";
  g("chuCamKet").innerHTML="Tôi xác nhận tham gia nghi thức và cam kết hiện diện tại tiền sảnh trước <b>"
    +esc(CFK.denSom)+" phút</b> để được dẫn lối.";
}
g("oNgay").addEventListener("change", function(){ chonNgay=this.value; chonGio=""; napKhach(); });
g("luoiGio").addEventListener("click", function(e){
  var n=e.target.closest("[data-gio]"); if(!n) return;
  chonGio=n.getAttribute("data-gio"); veLuoiGio(); veSoLuong();
  g("oChon").value=THU[new Date(chonNgay+"T00:00:00").getDay()]+" "+ngayVN(chonNgay)+" — "+chonGio;
});
g("btGiuCho").addEventListener("click", function(){
  var b=this, ten=g("oTen").value.trim(), sdt=sdtSach(g("oSdt").value), sl=+g("oSl").value||1;
  function hong(chu){ g("baoDat").innerHTML="<div class='bao hong'>"+esc(chu)+"</div>"; }
  if (!chonGio)             { return hong("Chưa chọn khung giờ ở mục 1."); }
  if (ten.length<2)         { return hong("Nhập tên người đại diện."); }
  if (sdt.length<9)         { return hong("Số điện thoại chưa đúng — cần đủ số để gửi thư mời qua Zalo."); }
  if (!g("oCamKet").checked){ return hong("Cần tích vào ô cam kết có mặt trước "+CFK.denSom+" phút."); }
  /* Khoá nút: mạng chậm, người ta bấm ba lần, và ba lượt ấy là ba đơn. */
  b.disabled=true; b.textContent="ĐANG GIỮ CHỖ…";
  api("dat", {ngay:chonNgay, gio:chonGio, ten:ten, sdt:sdt, sl:sl}).then(function(j){
    if (!j || !j.ok) { hong((j&&j.error)||"Không giữ được chỗ."); return napKhach(); }
    try { localStorage.setItem("nhama_sdt", sdt); } catch(e){}
    g("oCamKet").checked=false; g("baoDat").innerHTML="";
    napKhach(); moThiep([j.don], "Đã giữ chỗ. Đây là thiệp của anh/chị.");
  }).catch(function(e){ hong(e.message); })
    .then(function(){ b.disabled=false; b.textContent="🔑 Khởi tạo mã giữ chỗ - 0₫"; });
});

function htmlThiep(v){
  var t=TT[v.tt]||TT.giu_cho;
  return "<div class='thiep'><div class='ma'>"+esc(v.ma)+"</div>"
    +"<div style='text-align:center;margin-bottom:12px'><span class='the-trang "
    +(v.tt==="huy"?"het":(v.tt==="giu_cho"?"sap":"con"))+"'>"+esc(t.chu)+"</span></div>"
    +"<div class='dong'><span>Khách mời</span><b>"+esc(v.ten)+"</b></div>"
    +"<div class='dong'><span>Thời khắc</span><b>"+esc(ngayVN(v.ngay))+" — "+esc(v.gio)+"</b></div>"
    +"<div class='dong'><span>Thành viên</span><b>"+esc(v.sl)+" người</b></div>"
    +"<div class='dong'><span>Cần thu</span><b style='color:var(--vang)'>"+tien(v.tien)+"</b></div></div>";
}
function moThiep(ds, loi){
  if (!ds || !ds.length){
    return moHop("<h3 class='the-tieu'>Thiệp của tôi</h3><p class='kh-phu' style='text-align:left'>"
      +"Chưa tìm thấy lời mời nào cho số này.</p>");
  }
  var h=(loi?"<div class='bao duoc'>"+esc(loi)+"</div>":"");
  for (var i=0;i<ds.length;i++){ h+=htmlThiep(ds[i]); }
  h+="<div class='huong-dan'><b>Bước tiếp theo:</b> chuyển khoản đủ số tiền trên, nội dung ghi "
    +"<b>mã thiệp</b>. Chuyển xong bấm nút dưới đây để báo; ban tổ chức duyệt xong thiệp chuyển "
    +"sang <i>Chờ Check-in</i>. Tới nơi đọc mã ở cửa là được dẫn vào.</div>";
  var chua=ds.filter(function(v){ return v.tt==="giu_cho"; });
  h+="<div class='hang-nut'>"+(chua.length?"<button class='nut-phu' id='btDaCK'>Tôi đã chuyển khoản</button>":"")
    +"<button class='nut-phu' id='btLuuAnh'>Lưu thiệp (.svg)</button></div>";
  moHop(h);
  if (chua.length){
    g("btDaCK").addEventListener("click", function(){
      var b=this; b.disabled=true; b.textContent="Đang báo…";
      Promise.all(chua.map(function(v){ return api("bao_ck",{ma:v.ma}); })).then(function(){
        return api("cua_toi",{sdt:ds[0].sdt});
      }).then(function(j){
        moThiep((j&&j.don)||ds, "Đã báo. Ban tổ chức sẽ duyệt trong ít phút.");
      }).catch(function(e){ alert(e.message); b.disabled=false; b.textContent="Tôi đã chuyển khoản"; });
    });
  }
  g("btLuuAnh").addEventListener("click", function(){ taiThiep(ds[0]); });
}
function taiThiep(v){
  var s="<svg xmlns='http://www.w3.org/2000/svg' width='640' height='300' viewBox='0 0 640 300'>"
    +"<rect width='640' height='300' fill='#0d0a0e'/>"
    +"<rect x='10' y='10' width='620' height='280' fill='none' stroke='#c8a84b'/>"
    +"<text x='320' y='62' fill='#c1272d' font-size='24' font-family='Georgia,serif' letter-spacing='5' "
    +"text-anchor='middle'>"+esc(NM.cf.ten)+"</text>"
    +"<text x='320' y='120' fill='#c8a84b' font-size='34' font-family='Georgia,serif' letter-spacing='8' "
    +"text-anchor='middle'>"+esc(v.ma)+"</text>"
    +"<text x='320' y='166' fill='#e8e2d9' font-size='17' font-family='Arial' text-anchor='middle'>"
    +esc(v.ten)+" · "+esc(v.sl)+" người</text>"
    +"<text x='320' y='200' fill='#e8e2d9' font-size='17' font-family='Arial' text-anchor='middle'>"
    +esc(ngayVN(v.ngay))+" — "+esc(v.gio)+"</text>"
    +"<text x='320' y='250' fill='#9b9189' font-size='13' font-family='Arial' text-anchor='middle'>"
    +"Đọc mã này ở cửa.</text></svg>";
  var a=document.createElement("a");
  a.href="data:image/svg+xml;charset=utf-8,"+encodeURIComponent(s);
  a.download="thiep-"+v.ma+".svg"; a.click();
}
g("btThiep").addEventListener("click", function(){
  var sdt=""; try{ sdt=localStorage.getItem("nhama_sdt")||""; }catch(e){}
  if (!sdt) { return moTraCuu(); }
  api("cua_toi",{sdt:sdt}).then(function(j){ moThiep((j&&j.don)||[]); }).catch(function(e){ alert(e.message); });
});
g("btTraCuu").addEventListener("click", moTraCuu);
function moTraCuu(){
  moHop("<h3 class='the-tieu'>🔎 Tra cứu lời mời</h3><p class='kh-phu' style='text-align:left;margin-top:-8px'>"
    +"Cung cấp tín hiệu liên lạc (SĐT) để hệ thống trích xuất danh tính.</p>"
    +"<input class='o' id='oTraSdt' inputmode='tel' placeholder='Nhập Số Điện Thoại...' "
    +"style='text-align:center;margin:14px 0'><button class='nut-chinh' id='btTra'>Trích xuất dữ liệu</button>"
    +"<div id='baoTra'></div>");
  g("btTra").addEventListener("click", function(){
    var sdt=sdtSach(g("oTraSdt").value);
    if (sdt.length<9){ g("baoTra").innerHTML="<div class='bao hong'>Số điện thoại chưa đúng.</div>"; return; }
    try{ localStorage.setItem("nhama_sdt", sdt); }catch(e){}
    api("cua_toi",{sdt:sdt}).then(function(j){ moThiep((j&&j.don)||[]); })
      .catch(function(e){ g("baoTra").innerHTML="<div class='bao hong'>"+esc(e.message)+"</div>"; });
  });
}
function moHop(html){
  g("hopThoai").innerHTML="<div class='man-che'><div class='hop'>"
    +"<button class='hop-dong' aria-label='Đóng'>&times;</button>"+html+"</div></div>";
  g("hopThoai").querySelector(".hop-dong").addEventListener("click", dongHop);
  g("hopThoai").querySelector(".man-che").addEventListener("click", function(e){ if(e.target===this){ dongHop(); } });
}
function dongHop(){ g("hopThoai").innerHTML=""; }

/* ============================================================================ TRANG QUẢN TRỊ */
var MAN=[{id:"tong",ten:"Tổng Quan",bieu:"◔"},{id:"duyet",ten:"Xét Duyệt Tiền",bieu:"🧾"},
  {id:"soat",ten:"Soát Vé Tại Cửa",bieu:"⛶"},{id:"doi",ten:"Dữ Liệu Đối Soát",bieu:"🗄"},
  {id:"cai",ten:"Cài Đặt Hệ Thống",bieu:"⚙"}];
var manDang="tong", DON=[], CFQ={}, KHUNGQ=[], qNgay="", soCho=null, hen=null, caiCam=null;

function napQL(){
  return api("ds").then(function(j){
    if (!j || !j.ok) { return; }
    DON=j.don; CFQ=j.cf; KHUNGQ=j.khung;
    if (!qNgay) { qNgay=j.hnay; }
  });
}
function veNav(){
  var h="", cho=DON.filter(function(d){ return d.tt==="cho_duyet"; }).length;
  for (var i=0;i<MAN.length;i++){
    var m=MAN[i];
    h+="<button data-man='"+m.id+"' class='"+(manDang===m.id?"on":"")+"'><span>"+m.bieu+"</span>"
      +"<span>"+m.ten+"</span>"+(m.id==="duyet"&&cho?"<span class='cham'>"+cho+"</span>":"")+"</button>";
  }
  h+="<button class='thoat' data-man='thoat'><span>⏻</span><span>Đăng Xuất</span></button>";
  g("qNav").innerHTML=h;
  g("btAm").textContent="🔔 Cảnh báo âm thanh: "+(CFQ.am?"Đã Bật":"Đã Tắt");
}
g("qNav").addEventListener("click", function(e){
  var n=e.target.closest("[data-man]"); if(!n) return;
  var m=n.getAttribute("data-man");
  if (m==="thoat"){ THE=null; try{ sessionStorage.removeItem("nhama_the"); }catch(e2){}
    location.hash=""; return veTrang(); }
  manDang=m; veQL();
});
g("btAm").addEventListener("click", function(){
  CFQ.am=CFQ.am?0:1; api("cai",{cf:CFQ}).then(function(){ veNav(); });
});
function veQL(){
  veNav();
  if (manDang==="tong")  { return manTong(); }
  if (manDang==="duyet") { return manDuyet(); }
  if (manDang==="soat")  { return manSoat(); }
  if (manDang==="doi")   { return manDoi(); }
  if (manDang==="cai")   { return manCai(); }
}
function daDatQ(ngay,gio){
  var n=0, ok=["giu_cho","cho_duyet","cho_vao","da_vao"];
  for (var i=0;i<DON.length;i++){
    var d=DON[i];
    if (d.ngay===ngay && d.gio===gio && ok.indexOf(d.tt)>=0) { n+=(+d.sl||0); }
  }
  return n;
}
function manTong(){
  var tong=DON.length,
      cho=DON.filter(function(d){ return d.tt==="cho_duyet"; }).length,
      huy=DON.filter(function(d){ return d.tt==="huy"; }).length,
      /* 🔴 THỰC THU CHỈ ĐẾM ĐƠN ĐÃ DUYỆT TIỀN. Đơn đang giữ chỗ chưa có đồng nào về tài khoản;
         cộng vào là báo cáo kế toán nói dối đúng con số mang đi đối chiếu ngân hàng. */
      thu=DON.filter(function(d){ return d.tt==="cho_vao"||d.tt==="da_vao"; })
             .reduce(function(s,d){ return s+(+d.tien||0); },0);
  var khach=0, h="";
  for (var i=0;i<KHUNGQ.length;i++){
    var gio=KHUNGQ[i], dat=daDatQ(qNgay,gio), con=CFQ.suc-dat;
    khach+=dat;
    var lop = dat<=0?"trong":(con<=0?"day":"sap"), chu = dat<=0?"Trống":(con<=0?"Đầy":"Còn "+con);
    h+="<div class='q-gio "+lop+"'><b>"+gio+"</b><span>"+dat+" / "+CFQ.suc+" Khách</span><em>"+chu+"</em></div>";
  }
  var suc=KHUNGQ.length*CFQ.suc, pt=suc?Math.round(khach*100/suc):0;
  g("qNoiDung").innerHTML="<h2 class='q-tieu'>📈 Báo Cáo Kế Toán &amp; Vận Hành</h2><div class='q-so'>"
    +oSo("🗓","#132a4a","Tổng lượt đặt",tong,"")+oSo("⏳","#3a2c08","Chờ duyệt tiền",cho,"")
    +oSo("🚫","#3a1416","Huỷ / hoàn tiền",huy,"")+oSo("💳","#0f2a1a","Kế toán (thực thu)",tien(thu),"color:var(--q-luc)")
    +"</div><div class='q-the' style='margin-top:18px'>"
    +"<div style='display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap'>"
    +"<h3 style='margin:0;font-size:18px;color:var(--q-xanh)'>🗓 Tình Trạng Sảnh Theo Ngày</h3>"
    +"<input type='date' class='q-o' id='qNgayO' value='"+qNgay+"'></div>"
    +"<p style='margin:14px 0 0'>Tổng khách trong ngày: <b>"+khach+"</b> người.</p>"
    +"<div class='q-thanh'><i style='width:"+pt+"%'></i></div><div class='q-luoi-gio'>"+h+"</div></div>";
  g("qNgayO").addEventListener("change", function(){ qNgay=this.value||qNgay; manTong(); });
}
function oSo(bieu,nen,ten,so,kieu){
  return "<div class='q-the'><div class='q-bieu' style='background:"+nen+"'>"+bieu+"</div>"
    +"<div><small>"+ten+"</small><b style='"+kieu+"'>"+so+"</b></div></div>";
}
function manDuyet(){
  var ds=DON.filter(function(d){ return d.tt==="cho_duyet"; }).sort(function(a,b){ return a.tao<b.tao?-1:1; });
  var h="<h2 class='q-tieu'>✅ Kế Toán Xét Duyệt</h2>";
  if (!ds.length){ h+="<div class='q-the q-nho'>Không có đơn nào chờ duyệt. Đơn hiện ở đây khi khách "
    +"bấm <b>“Tôi đã chuyển khoản”</b> trên thiệp của họ.</div>"; }
  for (var i=0;i<ds.length;i++){
    var d=ds[i];
    h+="<div class='q-the' style='margin-bottom:14px'><div class='q-duyet'>"
      +"<div class='q-bill'>🧾<br>ĐỐI CHIẾU SAO KÊ</div><div>"
      +"<div style='color:var(--q-cam);font-size:18px;font-weight:700'>Mã: "+esc(d.ma)+"</div>"
      +"<div style='margin:8px 0'>Khách: <b>"+esc(d.ten)+"</b> | SĐT: "+esc(d.sdt)
      +" <a class='q-zalo' target='_blank' rel='noopener' href='https://zalo.me/"+esc(sdtSach(d.sdt))
      +"'>💬 Chat Zalo</a></div>"
      +"<div>Lịch: "+esc(d.ngay)+" | Giờ: <b style='color:var(--q-xanh)'>"+esc(d.gio)+"</b></div>"
      +"<div>Cần thu: <b style='color:var(--q-luc)'>"+tien(d.tien)+"</b> (Đoàn "+esc(d.sl)+" khách)</div></div>"
      +"<div style='display:grid;gap:9px'>"
      +"<button class='q-nut luc' data-duyet='"+esc(d.ma)+"'>☑ DUYỆT</button>"
      +"<button class='q-nut do' data-choi='"+esc(d.ma)+"'>✕ TỪ CHỐI</button></div></div></div>";
  }
  g("qNoiDung").innerHTML=h;
}
g("qNoiDung").addEventListener("click", function(e){
  var n;
  if ((n=e.target.closest("[data-duyet]"))) { return doi(n.getAttribute("data-duyet"),"cho_vao",""); }
  if ((n=e.target.closest("[data-choi]"))){
    var ly=prompt("Từ chối vì sao? (ghi lại để còn giải thích với khách)","không chuyển khoản");
    if (ly===null) { return; }
    return doi(n.getAttribute("data-choi"),"huy",ly);
  }
  if ((n=e.target.closest("[data-huy]")))  { return doi(n.getAttribute("data-huy"),"huy","huỷ ở màn đối soát"); }
  if ((n=e.target.closest("[data-hoan]")))  { return doi(n.getAttribute("data-hoan"),"cho_vao","mở lại"); }
});
function doi(ma,tt,ghi){
  api("doi",{ma:ma,tt:tt,ghi:ghi}).then(function(j){
    if (j && !j.ok) { alert(j.error); }
    return napQL();
  }).then(veQL).catch(function(e){ alert(e.message); });
}
function manSoat(){
  g("qNoiDung").innerHTML=
    "<div class='q-the' style='max-width:620px;margin:0 auto;border-color:var(--q-xanh)'>"
    +"<h2 class='q-tieu' style='justify-content:center;font-size:20px'>📷 Soát Vé Bằng Điện Thoại</h2>"
    +"<p style='text-align:center;color:var(--q-chu-mo);margin-top:-10px'>Đưa vé QR của khách vào vùng camera bên dưới.</p>"
    +"<div class='q-cam-hinh' id='qCam'><div style='text-align:center;padding:18px'>"
    +"<div style='font-size:34px'>📱</div><button class='q-nut xam' id='btCam' style='margin-top:10px'>Bật camera</button></div></div>"
    +"<p style='text-align:center;color:var(--q-chu-mo);margin:16px 0 6px'>Hoặc nhập thủ công mã ID vào ô dưới đây:</p>"
    +"<input class='q-o' id='oMa' placeholder='VD: GB-XYZ123' style='width:100%;text-align:center;"
    +"font-size:20px;letter-spacing:.12em;padding:14px'>"
    +"<button class='q-nut luc' id='btVao' style='width:100%;margin-top:12px;padding:15px;font-size:15px'>"
    +"XÁC NHẬN CHO KHÁCH VÀO</button><div id='baoSoat'></div></div>";
  g("btVao").addEventListener("click", function(){ soat(g("oMa").value); });
  g("oMa").addEventListener("keydown", function(e){ if(e.key==="Enter"){ soat(this.value); } });
  g("btCam").addEventListener("click", batCam);
}
/* Quét bằng bộ đọc CÓ SẴN của trình duyệt. KHÔNG nạp thư viện từ mạng ngoài: cửa hay sóng yếu,
   mà một trang soát vé chết vì tải không nổi thư viện là cả hàng khách đứng đợi. Máy không có bộ
   đọc thì vẫn còn ô gõ tay ngay bên dưới — đường luôn chạy được. */
function batCam(){
  if (!("BarcodeDetector" in window)){
    return baoSoat("hong","Trình duyệt này không quét được QR. Gõ mã ở ô bên dưới — nhanh không kém.");
  }
  navigator.mediaDevices.getUserMedia({video:{facingMode:"environment"}}).then(function(l){
    var v=document.createElement("video"); v.setAttribute("playsinline",""); v.srcObject=l; v.play();
    g("qCam").innerHTML=""; g("qCam").appendChild(v);
    var bo=new window.BarcodeDetector({formats:["qr_code"]});
    caiCam=setInterval(function(){
      bo.detect(v).then(function(kq){ if (kq&&kq.length){ soat(kq[0].rawValue); } }).catch(function(){});
    },400);
  }).catch(function(){
    baoSoat("hong","Không mở được camera (chưa cho quyền, hoặc trang không chạy qua HTTPS).");
  });
}
function baoSoat(kieu,chu){
  var m = kieu==="hong" ? "background:#2a1214;border:1px solid #6b1f24;color:#ff7b72"
                        : "background:#0d2416;border:1px solid #1a6b34;color:#56d364";
  g("baoSoat").innerHTML="<div style='"+m+";border-radius:8px;padding:13px;margin-top:14px;"
    +"font-size:14px;font-weight:600'>"+esc(chu)+"</div>";
}
var soatBan=false;
function soat(ma){
  if (soatBan) { return; }
  soatBan=true;
  api("soat",{ma:ma}).then(function(j){
    if (!j || !j.ok) { baoSoat("hong",(j&&j.error)||"Không soát được."); }
    else { baoSoat("duoc", j.chu); g("oMa").value=""; }
    return napQL();
  }).then(veNav).catch(function(e){ baoSoat("hong", e.message); })
    .then(function(){ setTimeout(function(){ soatBan=false; }, 800); });
}
var locChu="", locNgay="", locTT="";
function manDoi(){
  var ds=DON.filter(function(d){
    if (locTT && d.tt!==locTT) { return false; }
    if (locNgay && d.ngay!==locNgay) { return false; }
    if (locChu){
      var k=(d.ten+" "+d.sdt+" "+d.ma).toLowerCase();
      if (k.indexOf(locChu.toLowerCase())<0) { return false; }
    }
    return true;
  });
  var hTT="<option value=''>Tất cả trạng thái</option>";
  for (var k in TT){ hTT+="<option value='"+k+"'"+(locTT===k?" selected":"")+">"+TT[k].chu+"</option>"; }
  var h="<h2 class='q-tieu'>🗄 Dữ Liệu Đối Soát Khách Hàng</h2><div class='q-loc'>"
    +"<input class='q-o' id='lChu' placeholder='🔎 Tìm Tên, SĐT, Mã vé...' value='"+esc(locChu)+"' style='min-width:230px'>"
    +"<input class='q-o' type='date' id='lNgay' value='"+esc(locNgay)+"'>"
    +"<select class='q-o' id='lTT'>"+hTT+"</select>"
    +"<button class='q-nut xam' id='btBoLoc'>↺ Bỏ lọc</button>"
    +"<button class='q-nut luc' id='btXuat' style='margin-left:auto'>⬇ Xuất Excel</button></div>"
    +"<div class='q-the q-cuon'><table class='q-bang'><thead><tr><th>Mã vé</th><th>Khách hàng</th>"
    +"<th>Liên hệ / Chat</th><th>Số lượng</th><th>Tổng tiền</th><th>Lịch trình</th><th>Trạng thái</th>"
    +"<th>Ghi chú</th><th>Thao tác</th></tr></thead><tbody>";
  if (!ds.length){ h+="<tr><td colspan='9' class='q-nho' style='padding:22px'>Không có đơn nào khớp.</td></tr>"; }
  for (var i=0;i<ds.length;i++){
    var d=ds[i], t=TT[d.tt]||TT.giu_cho;
    h+="<tr><td><b style='color:var(--q-xanh)'>"+esc(d.ma)+"</b></td><td><b>"+esc(d.ten)+"</b></td>"
      +"<td>"+esc(d.sdt)+"<br><a class='q-zalo' target='_blank' rel='noopener' href='https://zalo.me/"
      +esc(sdtSach(d.sdt))+"'>💬 Chat Zalo</a></td><td>"+esc(d.sl)+"</td>"
      +"<td style='color:var(--q-luc);font-weight:700'>"+tien(d.tien)+"</td>"
      +"<td>"+esc(d.ngay)+"<br><b style='color:var(--q-cam)'>"+esc(d.gio)+"</b></td>"
      +"<td><span class='nhan-tt "+t.lop+"'>"+esc(t.chu)+"</span></td>"
      +"<td class='q-nho'>"+esc(d.ghi||"")+"</td><td style='white-space:nowrap'>"
      +(d.tt!=="huy"?"<button class='q-nut do' data-huy='"+esc(d.ma)+"' style='padding:6px 10px'>✕</button> ":"")
      +(d.tt==="huy"?"<button class='q-nut xam' data-hoan='"+esc(d.ma)+"' style='padding:6px 10px'>↺</button>":"")
      +"</td></tr>";
  }
  g("qNoiDung").innerHTML=h+"</tbody></table></div>";
  g("lChu").addEventListener("input", function(){ locChu=this.value; manDoi(); g("lChu").focus(); });
  g("lNgay").addEventListener("change", function(){ locNgay=this.value; manDoi(); });
  g("lTT").addEventListener("change", function(){ locTT=this.value; manDoi(); });
  g("btBoLoc").addEventListener("click", function(){ locChu=locNgay=locTT=""; manDoi(); });
  g("btXuat").addEventListener("click", function(){ xuatCSV(ds); });
}
/* 🔴 PHẢI CÓ BOM Ở ĐẦU TỆP, không thì Excel trên Windows đọc UTF-8 thành "Ph?m Tu?ng Vi" và người
   nhận sẽ bảo "file lỗi" chứ không ai đi dò bảng mã. */
function xuatCSV(ds){
  var d1=[["Mã vé","Khách hàng","SĐT","Số lượng","Tổng tiền","Ngày","Giờ","Trạng thái","Ghi chú"].join(",")];
  for (var i=0;i<ds.length;i++){
    var d=ds[i];
    d1.push([d.ma,d.ten,d.sdt,d.sl,d.tien,d.ngay,d.gio,(TT[d.tt]||{}).chu||d.tt,d.ghi||""]
      .map(function(x){ return '"'+String(x==null?"":x).replace(/"/g,'""')+'"'; }).join(","));
  }
  var b=new Blob(["﻿"+d1.join("\r\n")],{type:"text/csv;charset=utf-8"});
  var u=URL.createObjectURL(b), a=document.createElement("a");
  a.href=u; a.download="doi-soat-nha-ma-"+ngayISO(new Date())+".csv"; a.click();
  setTimeout(function(){ URL.revokeObjectURL(u); },4000);
}
function manCai(){
  g("qNoiDung").innerHTML="<h2 class='q-tieu'>⚙ Cấu Hình Hệ Thống</h2><div class='q-doi'>"
    +"<div class='q-the'><h3 style='margin-top:0;color:var(--q-xanh)'>☁ Cấu hình Kinh Doanh</h3>"
    +oCai("Giá Vé / 1 Người (VND)","cGia",CFQ.gia,"number")
    +oCai("Sức chứa tối đa (Số người / 1 Khung giờ)","cSuc",CFQ.suc,"number")
    +oCai("Khung đầu tiên","cMo",CFQ.mo,"time")+oCai("Khung cuối cùng","cDong",CFQ.dong,"time")
    +oCai("Mỗi khung cách nhau (phút)","cBuoc",CFQ.buoc,"number")
    +oCai("Mở bán trước (ngày)","cNgayMo",CFQ.ngayMo,"number")
    +oCai("Khách phải có mặt trước (phút)","cDenSom",CFQ.denSom,"number")
    +"<button class='q-nut xanh' id='btLuuKD' style='width:100%;margin-top:16px'>LƯU LÊN SERVER</button></div>"
    +"<div class='q-the'><h3 style='margin-top:0;color:var(--q-luc)'>🖥 Cấu hình Màn Hình</h3>"
    +"<label class='q-nho'>Tốc độ tự động quét đơn mới:</label>"
    +"<select class='q-o' id='cQuet' style='width:100%;margin:8px 0 16px'>"
    +"<option value='3000'>Nhanh (3 giây)</option><option value='6000'>Vừa (6 giây)</option>"
    +"<option value='15000'>Chậm (15 giây)</option><option value='0'>Tắt</option></select>"
    +oCai("Tên hiển thị trên trang khách","cTen",CFQ.ten,"text")
    +oCai("Câu phụ dưới tiêu đề","cPhu",CFQ.phu,"text")
    +oCai("Đổi mã PIN (để trống là giữ nguyên)","cPin","","text")
    +"<button class='q-nut luc' id='btLuuMH' style='width:100%;margin-top:14px'>LƯU MÀN HÌNH</button>"
    +"<hr style='border:0;border-top:1px solid var(--q-vien);margin:20px 0'>"
    +"<button class='q-nut xam' id='btMau' style='width:100%'>Nạp 12 đơn mẫu (để xem thử)</button>"
    +"<button class='q-nut do' id='btXoa' style='width:100%;margin-top:9px'>Xoá sạch sổ</button></div></div>";
  g("cQuet").value=String(CFQ.quet);
  g("btLuuKD").addEventListener("click", function(){
    luuCai({ gia:+g("cGia").value||0, suc:+g("cSuc").value||1, mo:g("cMo").value, dong:g("cDong").value,
      buoc:+g("cBuoc").value||5, ngayMo:+g("cNgayMo").value||14, denSom:+g("cDenSom").value||0 }, "");
  });
  g("btLuuMH").addEventListener("click", function(){
    luuCai({ quet:+g("cQuet").value, ten:g("cTen").value, phu:g("cPhu").value }, g("cPin").value);
  });
  g("btMau").addEventListener("click", function(){
    api("mau").then(napQL).then(veQL).catch(function(e){ alert(e.message); });
  });
  g("btXoa").addEventListener("click", function(){
    if (!confirm("Xoá sạch mọi đơn trong sổ? Không lấy lại được.")) { return; }
    api("xoa").then(napQL).then(veQL).catch(function(e){ alert(e.message); });
  });
}
function luuCai(phan, pin){
  var moi=Object.assign({}, CFQ, phan);
  api("cai",{cf:moi, pin:pin}).then(function(j){
    if (!j || !j.ok) { return alert((j&&j.error)||"Không lưu được."); }
    CFQ=j.cf; KHUNGQ=j.khung; datHen(); manCai(); alert("Đã lưu lên server.");
  }).catch(function(e){ alert(e.message); });
}
function oCai(nhan,id,gt,kieu){
  return "<label class='q-nho' style='display:block;margin-top:12px'>"+esc(nhan)+"</label>"
    +"<input class='q-o' id='"+id+"' type='"+kieu+"' value='"+esc(gt)+"' style='width:100%;margin-top:6px'>";
}
function datHen(){
  if (hen) { clearInterval(hen); hen=null; }
  if (!CFQ.quet) { return; }
  hen=setInterval(function(){
    if (!THE) { return; }
    napQL().then(function(){
      var cho=DON.filter(function(d){ return d.tt==="cho_duyet"; }).length;
      if (soCho!==null && cho>soCho && CFQ.am) { keu(); }
      soCho=cho;
      /* Không vẽ lại màn đang gõ dở: người ta đang nhập mã ở cửa mà ô bị dựng lại là mất chữ. */
      if (manDang!=="soat" && manDang!=="cai") { veQL(); } else { veNav(); }
    }).catch(function(){});
  }, Math.max(1000, CFQ.quet));
}
function keu(){
  try{
    var A=new (window.AudioContext||window.webkitAudioContext)();
    var o=A.createOscillator(), gg=A.createGain();
    o.type="sine"; o.frequency.value=880; gg.gain.value=.05;
    o.connect(gg); gg.connect(A.destination); o.start();
    setTimeout(function(){ o.frequency.value=1320; },130);
    setTimeout(function(){ o.stop(); A.close(); },300);
  }catch(e){}
}
/* ============================================================================== cửa vào */
function veTrang(){
  var la = location.hash.replace("#","").toLowerCase()==="quanly";
  if (caiCam) { clearInterval(caiCam); caiCam=null; }
  if (hen && !la) { clearInterval(hen); hen=null; }
  if (!la){
    g("apKhach").classList.remove("an"); g("apQL").classList.add("an");
    dongHop(); napKhach(); return;
  }
  g("apKhach").classList.add("an"); g("apQL").classList.remove("an");
  if (!THE) { return veCuaPin(); }
  napQL().then(function(){ veQL(); datHen(); }).catch(function(e){
    g("qNoiDung").innerHTML="<div class='q-the'>"+esc(e.message)+"</div>";
  });
}
function veCuaPin(){
  g("qNav").innerHTML="";
  g("qNoiDung").innerHTML="<div class='q-the' style='max-width:360px;margin:60px auto;text-align:center'>"
    +"<h2 style='margin-top:0'>Trang quản trị</h2>"
    +"<input class='q-o' id='oPin' type='password' inputmode='numeric' placeholder='Mã PIN' "
    +"style='width:100%;text-align:center;font-size:20px;letter-spacing:.3em;padding:13px'>"
    +"<button class='q-nut xanh' id='btPin' style='width:100%;margin-top:12px;padding:13px'>VÀO</button>"
    +"<div id='baoPin' class='q-nho' style='margin-top:10px'></div></div>";
  function thu(){
    api("vao",{pin:g("oPin").value}).then(function(j){
      if (!j || !j.ok) { g("baoPin").innerHTML="<span style='color:var(--q-do)'>"+esc((j&&j.error)||"PIN chưa đúng.")+"</span>"; return; }
      THE=j.the; try{ sessionStorage.setItem("nhama_the", THE); }catch(e){}
      veTrang();
    }).catch(function(e){ g("baoPin").innerHTML="<span style='color:var(--q-do)'>"+esc(e.message)+"</span>"; });
  }
  g("btPin").addEventListener("click", thu);
  g("oPin").addEventListener("keydown", function(e){ if(e.key==="Enter"){ thu(); } });
  g("oPin").focus();
}
window.addEventListener("hashchange", veTrang);
veTrang();
JS;
	}
}

register_activation_hook( __FILE__, array( 'NHAMA', 'cai_dat' ) );
add_action( 'init', array( 'NHAMA', 'init' ), 5 );
add_action( 'rest_api_init', array( 'NHAMA', 'rest' ) );

endif;
