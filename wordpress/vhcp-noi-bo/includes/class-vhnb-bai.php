<?php
/**
 * LÕI BẢNG TIN — đăng bài, bình luận, thả tim, ghim.
 *
 * Hàm ở đây KHÔNG in ra gì và không đọc `$_POST` — nhận tham số, trả mảng. Nhờ vậy thử được
 * bằng con số, không phải dựng cả trang.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Bai {

	/** Bài dài hơn ngần này thì cắt — một bài 50 nghìn ký tự làm hỏng cả trang tin của mọi người. */
	const DAI_TOI_DA = 5000;
	const BL_TOI_DA  = 1000;

	/** Mỗi trang bao nhiêu bài. */
	const MOI_TRANG = 20;

	/* ==================================================================== đăng */

	/**
	 * Đăng một bài.
	 *
	 * 🔴 KHÔNG cho đăng bài rỗng, và KHÔNG cho đăng thay người khác: `$u` là người đang đăng
	 *    nhập, mã và tên lấy TỪ ĐÓ chứ không nhận từ biểu mẫu. Nhận từ biểu mẫu là ai cũng đăng
	 *    được bài mang tên giám đốc.
	 */
	public static function dang( $u, $noi_dung, $nhom = '', $nhom_id = 0 ) {
		global $wpdb;
		$ten = trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Chưa đăng nhập.' ); }

		/* 🔴 ĐĂNG VÀO NHÓM TỰ TẠO THÌ PHẢI LÀ THÀNH VIÊN — gác Ở ĐÂY, không gác ở màn hình.
		   Màn hình chỉ liệt kê nhóm của mình, nhưng biểu mẫu POST thì ai dựng ở đâu cũng gửi
		   lên được: không chặn tại lõi là đăng được bài vào nhóm mình chưa hề được mời. */
		$nhom_id = (int) $nhom_id;
		if ( $nhom_id > 0 ) {
			if ( ! VHNB_Nhom::duoc_vao( $u, $nhom_id ) ) {
				return array( 'ok' => false, 'error' => 'Anh/chị không ở trong nhóm này.' );
			}
			/* Bài của nhóm KHÔNG mang thêm nhãn bộ phận: một bài chỉ thuộc đúng một chỗ, nhận
			   cả hai là nó vừa nằm trong nhóm kín vừa nằm ở bảng tin bộ phận. */
			$nhom = '';
		}

		$nd = self::gon( $noi_dung, self::DAI_TOI_DA );
		if ( '' === $nd ) {
			return array( 'ok' => false, 'error' => 'Bài rỗng thì không ai đọc được gì — gõ vài chữ đã.' );
		}
		$ok = $wpdb->insert( VHNB_DB::t( 'bai' ), array(
			'nhom'     => self::chuan_nhom( $nhom ),
			'nhom_id'  => $nhom_id,
			'ma_nv'    => (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ),
			'ho_ten'   => $ten,
			'vai_tro'  => (string) ( isset( $u['role'] ) ? $u['role'] : '' ),
			'noi_dung' => $nd,
			'tao_luc'  => current_time( 'mysql' ),
		) );
		if ( false === $ok ) { return array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error ); }
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id );
	}

	public static function binh_luan( $u, $bai_id, $noi_dung ) {
		global $wpdb;
		$ten = trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Chưa đăng nhập.' ); }
		$bai_id = (int) $bai_id;
		if ( ! self::co_bai( $bai_id ) ) { return array( 'ok' => false, 'error' => 'Bài này không còn.' ); }
		if ( ! self::doc_duoc( $u, $bai_id ) ) {
			return array( 'ok' => false, 'error' => 'Bài này nằm trong nhóm anh/chị không ở trong.' );
		}
		$nd = self::gon( $noi_dung, self::BL_TOI_DA );
		if ( '' === $nd ) { return array( 'ok' => false, 'error' => 'Bình luận rỗng.' ); }

		$wpdb->insert( VHNB_DB::t( 'binh_luan' ), array(
			'bai_id'   => $bai_id,
			'ma_nv'    => (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ),
			'ho_ten'   => $ten,
			'noi_dung' => $nd,
			'tao_luc'  => current_time( 'mysql' ),
		) );
		self::dem_lai( $bai_id );
		return array( 'ok' => true );
	}

	/**
	 * Thả tim / bỏ tim — bấm lại là bỏ.
	 *
	 * ⚠️ Người CHƯA có mã nhân viên thì không thả tim được: khoá UNIQUE là (bài, mã), nên mọi
	 *    người mã rỗng sẽ chung một ô — người thứ hai thả tim là ghi đè người thứ nhất. Nói
	 *    thẳng ra còn hơn để con số nhảy lung tung.
	 */
	public static function tim( $u, $bai_id ) {
		global $wpdb;
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) {
			return array( 'ok' => false,
				'error' => 'Tài khoản này chưa có Mã NV nên chưa thả tim được — nhờ Admin khai giúp ở hồ sơ.' );
		}
		$bai_id = (int) $bai_id;
		if ( ! self::co_bai( $bai_id ) ) { return array( 'ok' => false, 'error' => 'Bài này không còn.' ); }
		if ( ! self::doc_duoc( $u, $bai_id ) ) {
			return array( 'ok' => false, 'error' => 'Bài này nằm trong nhóm anh/chị không ở trong.' );
		}

		$t  = VHNB_DB::t( 'tim' );
		$cu = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE bai_id=%d AND ma_nv=%s", $bai_id, $ma ) );
		if ( $cu ) { $wpdb->delete( $t, array( 'id' => (int) $cu ) ); $co = false; }
		else {
			$wpdb->insert( $t, array( 'bai_id' => $bai_id, 'ma_nv' => $ma,
				'tao_luc' => current_time( 'mysql' ) ) );
			$co = true;
		}
		self::dem_lai( $bai_id );
		return array( 'ok' => true, 'da_tim' => $co );
	}

	/**
	 * Xoá bài. Tác giả xoá bài của mình; Admin xoá được mọi bài.
	 * Xoá bài thì xoá luôn bình luận và tim của nó — để lại là rác không ai với tới được.
	 */
	public static function xoa( $u, $bai_id ) {
		global $wpdb;
		$bai_id = (int) $bai_id;
		$bai = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=%d', $bai_id ), ARRAY_A );
		if ( ! $bai ) { return array( 'ok' => false, 'error' => 'Bài này không còn.' ); }
		if ( ! self::duoc_xoa( $u, $bai ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ người đăng hoặc Admin mới xoá được bài này.' );
		}
		$wpdb->delete( VHNB_DB::t( 'binh_luan' ), array( 'bai_id' => $bai_id ) );
		$wpdb->delete( VHNB_DB::t( 'tim' ), array( 'bai_id' => $bai_id ) );
		$wpdb->delete( VHNB_DB::t( 'bai' ), array( 'id' => $bai_id ) );
		return array( 'ok' => true );
	}

	/** Ai xoá được bài này. Tách hàm để màn hình hỏi được TRƯỚC khi vẽ nút. */
	public static function duoc_xoa( $u, $bai ) {
		if ( self::la_admin( $u ) ) { return true; }
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		/* So bằng MÃ, không bằng tên: tên trùng nhau đầy. Mã rỗng thì lui về so tên — thà chặt
		   hơn là để một người mã rỗng xoá bài của người mã rỗng khác. */
		if ( '' !== $ma ) { return 0 === strcasecmp( $ma, (string) $bai['ma_nv'] ); }
		return ( '' === trim( (string) $bai['ma_nv'] ) )
			&& 0 === strcasecmp( (string) ( isset( $u['name'] ) ? $u['name'] : '' ), (string) $bai['ho_ten'] );
	}

	/** Ghim bài lên đầu — chỉ Admin. Thông báo của công ty phải nằm trên, không trôi mất. */
	public static function ghim( $u, $bai_id, $bat = true ) {
		global $wpdb;
		if ( ! self::la_admin( $u ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ Admin mới ghim được bài.' );
		}
		$wpdb->update( VHNB_DB::t( 'bai' ), array( 'ghim' => $bat ? 1 : 0 ),
			array( 'id' => (int) $bai_id ) );
		return array( 'ok' => true );
	}

	/* ==================================================================== đọc */

	/**
	 * Bảng tin. Bài GHIM luôn nằm trên, rồi tới bài mới nhất.
	 *
	 * @param string $nhom '' = xem tất cả; tên bộ phận = chỉ bài của bộ phận đó (kèm bài chung).
	 */
	public static function bang_tin( $nhom = '', $trang = 1, $nhom_id = 0 ) {
		global $wpdb;
		$t  = VHNB_DB::t( 'bai' );
		$tr = max( 1, (int) $trang );
		$bo = ( $tr - 1 ) * self::MOI_TRANG;
		$nhom = self::chuan_nhom( $nhom );
		$nhom_id = (int) $nhom_id;

		/* 🔴 BÀI CỦA NHÓM TỰ TẠO KHÔNG BAO GIỜ LỌT RA BẢNG TIN CHUNG.
		   Mọi đường đọc ở dưới đều chặn `nhom_id=0`, trừ đúng đường "đang mở một nhóm". Thiếu
		   một chỗ là bài trong nhóm kín hiện ra ở màn "Tất cả" của cả công ty — và người viết
		   không hề biết, vì họ đăng vào nhóm. */
		if ( $nhom_id > 0 ) {
			return VHNB_DB::rows( $wpdb->prepare(
				"SELECT * FROM $t WHERE nhom_id=%d ORDER BY ghim DESC, tao_luc DESC, id DESC LIMIT %d OFFSET %d",
				$nhom_id, self::MOI_TRANG, $bo ) );
		}

		/* Chọn một bộ phận thì VẪN thấy bài chung (`nhom=''`): thông báo toàn công ty mà biến
		   mất chỉ vì đang lọc bộ phận thì lọc xong là bỏ sót đúng thứ quan trọng nhất. */
		if ( '' !== $nhom ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM $t WHERE nhom_id=0 AND ( nhom=%s OR nhom='' ) ORDER BY ghim DESC, tao_luc DESC, id DESC LIMIT %d OFFSET %d",
				$nhom, self::MOI_TRANG, $bo );
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM $t WHERE nhom_id=0 ORDER BY ghim DESC, tao_luc DESC, id DESC LIMIT %d OFFSET %d",
				self::MOI_TRANG, $bo );
		}
		return VHNB_DB::rows( $sql );
	}

	public static function ds_binh_luan( $bai_id ) {
		global $wpdb;
		return VHNB_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHNB_DB::t( 'binh_luan' ) . ' WHERE bai_id=%d ORDER BY tao_luc ASC, id ASC',
			(int) $bai_id ) );
	}

	/** Những bài mà người này ĐÃ thả tim — để vẽ trái tim đặc thay vì rỗng. */
	public static function da_tim( $u, $ds_bai ) {
		global $wpdb;
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma || ! $ds_bai ) { return array(); }
		$id = array();
		foreach ( $ds_bai as $b ) { $id[] = (int) $b['id']; }
		$cho = implode( ',', array_map( 'intval', $id ) );
		$ra  = array();
		foreach ( VHNB_DB::rows( $wpdb->prepare(
			'SELECT bai_id FROM ' . VHNB_DB::t( 'tim' ) . " WHERE ma_nv=%s AND bai_id IN ($cho)", $ma ) ) as $r ) {
			$ra[ (int) $r['bai_id'] ] = 1;
		}
		return $ra;
	}

	/* ==================================================================== phụ */

	/**
	 * Đếm LẠI tim và bình luận từ chính hai bảng kia, rồi ghi vào bài.
	 *
	 * 🔴 KHÔNG dùng `so_tim = so_tim + 1`. Cộng dồn thì chỉ cần một lượt ghi trượt (mạng đứt,
	 *    bấm hai lần, khoá UNIQUE chặn) là con số lệch VĨNH VIỄN — và không có cách nào biết nó
	 *    đã lệch. Đếm lại thì mỗi lượt tự chữa cho lượt trước.
	 */
	public static function dem_lai( $bai_id ) {
		global $wpdb;
		$bai_id = (int) $bai_id;
		$tim = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'tim' ) . ' WHERE bai_id=%d', $bai_id ) );
		$bl  = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'binh_luan' ) . ' WHERE bai_id=%d', $bai_id ) );
		$wpdb->update( VHNB_DB::t( 'bai' ), array( 'so_tim' => $tim, 'so_bl' => $bl ),
			array( 'id' => $bai_id ) );
	}

	/**
	 * Người này có được đụng vào bài này không (đọc · bình luận · thả tim).
	 *
	 * 🔴 Bài THƯỜNG thì ai cũng được. Bài của NHÓM TỰ TẠO thì chỉ thành viên — và chốt phải nằm
	 *    ở LÕI, không phải ở màn hình: màn chỉ vẽ bài mình thấy được, nhưng `bai_id` thì gõ tay
	 *    vào biểu mẫu POST là gửi lên được. Không chặn ở đây thì đoán mò vài con số là bình luận
	 *    được vào nhóm mình chưa hề được mời — và người trong nhóm thấy bình luận ấy hiện ra.
	 */
	public static function doc_duoc( $u, $bai_id ) {
		global $wpdb;
		$nid = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT nhom_id FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=%d', (int) $bai_id ) );
		if ( $nid <= 0 ) { return true; }
		return VHNB_Nhom::duoc_vao( $u, $nid );
	}

	private static function co_bai( $id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=%d', (int) $id ) );
	}

	public static function la_admin( $u ) {
		if ( class_exists( 'VHCC_Vai' ) && method_exists( 'VHCC_Vai', 'duoc' ) ) {
			return VHCC_Vai::duoc( $u, 'he_thong' );
		}
		/* Chưa cài plugin chấm công -> lui về so chuỗi. Không phải cách hay, nhưng thà thế còn
		   hơn để `la_admin` luôn trả false và không ai ghim / dọn được bài nào. */
		return 0 === strcasecmp( trim( (string) ( isset( $u['role'] ) ? $u['role'] : '' ) ), 'Admin' );
	}

	/** Bỏ khoảng trắng thừa, cắt độ dài. KHÔNG lọc HTML ở đây — nơi in ra lo việc thoát chuỗi. */
	public static function gon( $s, $toi_da ) {
		$s = trim( (string) $s );
		$s = preg_replace( "/\r\n?/", "\n", $s );
		$s = preg_replace( "/\n{4,}/", "\n\n\n", $s );
		if ( function_exists( 'mb_substr' ) ) { $s = mb_substr( $s, 0, (int) $toi_da, 'UTF-8' ); }
		else { $s = substr( $s, 0, (int) $toi_da ); }
		return trim( $s );
	}

	public static function chuan_nhom( $n ) {
		$n = trim( (string) $n );
		if ( function_exists( 'mb_substr' ) ) { $n = mb_substr( $n, 0, 60, 'UTF-8' ); }
		return $n;
	}

	/** "3 phút trước" — người ta đọc bảng tin theo "mới hay cũ", không theo đồng hồ. */
	public static function bao_lau( $luc ) {
		$t = strtotime( (string) $luc );
		if ( ! $t ) { return ''; }
		$g = (int) current_time( 'timestamp' ) - $t;
		if ( $g < 60 )     { return 'vừa xong'; }
		if ( $g < 3600 )   { return intdiv( $g, 60 ) . ' phút trước'; }
		if ( $g < 86400 )  { return intdiv( $g, 3600 ) . ' giờ trước'; }
		if ( $g < 604800 ) { return intdiv( $g, 86400 ) . ' ngày trước'; }
		return gmdate( 'd/m/Y', $t );
	}
}
