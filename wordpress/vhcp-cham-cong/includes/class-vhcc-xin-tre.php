<?php
/**
 * ĐƠN XIN PHÉP ĐI TRỄ — nhân viên nộp trước, cửa hàng trưởng duyệt, cảnh báo vàng bỏ đi.
 *
 * =================================================================================================
 * Anh Thắng 27/08/2026, nguyên văn:
 *   *"nếu bạn nào chấm thiếu giờ thì hiện cảnh báo ô vàng cho cửa hàng trưởng biết (để khỏi bị
 *   cảnh báo, thì tại trang chấm công online nhân viên sẽ chọn Xin Phép đi trễ TRƯỚC KHI TỚI cửa
 *   hàng. lúc này bên tài khoản cửa hàng trưởng sẽ hiện trong phần Lệnh đi trễ, cửa hàng trưởng
 *   duyệt đơn thì cảnh báo đó sẽ bỏ"*.
 * =================================================================================================
 *
 * 🔴 ĐƠN CHỈ BỎ CẢNH BÁO, KHÔNG BỎ SỐ. Đơn được duyệt thì ô thôi vàng — nhưng số giờ trong ô
 *    KHÔNG đổi, và giờ vào–giờ ra vẫn nguyên như máy ghi. Nếu đơn cộng bù giờ thì nó thành một
 *    cửa cấp công không qua chấm công, và cửa ấy không có ai gác.
 *
 * 🔴 NỘP TRƯỚC LÀ CẢ Ý NGHĨA CỦA NÓ. "Xin phép trước khi tới" khác hẳn "giải trình sau khi bị
 *    phát hiện". Nên đơn cho ngày ĐÃ QUA thì vẫn nhận, nhưng đánh dấu là nộp muộn để cửa hàng
 *    trưởng nhìn ra — chứ không lặng lẽ xoá mất sự khác nhau ấy.
 *
 * ⚠️ MỘT NGƯỜI MỘT NGÀY MỘT ĐƠN. Nộp lại là ĐÈ lên đơn cũ (và quay về trạng thái chờ), chứ không
 *    xếp thêm đơn mới. Xếp thêm thì cửa hàng trưởng phải duyệt hai lần cho một buổi sáng, và
 *    duyệt cái nào mới là đúng thì không ai biết.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_XinTre {

	const CHO      = 'cho';
	const DUYET    = 'duyet';
	const TU_CHOI  = 'tu_choi';

	/** Xin trước được xa nhất bấy nhiêu ngày — xin phép cho tháng sau thì không còn là xin phép. */
	const TRUOC_TOI_DA = 31;
	/** Nộp muộn cho ngày đã qua: quá bấy nhiêu ngày thì thôi, đó là chuyện của bảng lương rồi. */
	const MUON_TOI_DA  = 14;

	const TEN_TT = array(
		self::CHO     => 'Chờ duyệt',
		self::DUYET   => 'Đã duyệt',
		self::TU_CHOI => 'Không duyệt',
	);

	/* ====================================================================== nộp đơn */

	/**
	 * Nhân viên nộp đơn cho CHÍNH MÌNH.
	 *
	 * ⚠️ MÃ NV LẤY TỪ PHIÊN, KHÔNG LẤY TỪ BIỂU MẪU. Để người gửi tự khai mã là ai cũng nộp được
	 *    đơn đứng tên người khác — và đơn ấy được duyệt thì cảnh báo của người khác biến mất.
	 */
	public static function nop( $u, $dat ) {
		global $wpdb;
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Tài khoản này chưa gắn Mã NV nên chưa nộp đơn '
				. 'được. Nhờ quản lý khai Mã NV trong hồ sơ.' );
		}
		$ngay = self::ngay( isset( $dat['ngay'] ) ? $dat['ngay'] : '' );
		if ( '' === $ngay ) { return array( 'ok' => false, 'error' => 'Ngày không hợp lệ.' ); }

		$lech = self::cach_hom_nay( $ngay );
		if ( $lech > self::TRUOC_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Chỉ xin phép trước tối đa '
				. self::TRUOC_TOI_DA . ' ngày.' );
		}
		if ( $lech < -self::MUON_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Ngày ' . $ngay . ' đã qua quá '
				. self::MUON_TOI_DA . ' ngày — việc này thuộc về bảng lương, không phải đơn xin phép.' );
		}

		$phut = self::phut( isset( $dat['so_phut'] ) ? $dat['so_phut'] : '' );
		if ( null === $phut ) {
			return array( 'ok' => false, 'error' => 'Xin trễ bao nhiêu phút? Điền số từ 1 đến '
				. VHCC_Tre::TOI_DA . '.' );
		}
		$ly_do = trim( (string) ( isset( $dat['ly_do'] ) ? $dat['ly_do'] : '' ) );
		if ( '' === $ly_do ) {
			return array( 'ok' => false, 'error' => 'Điền lý do — cửa hàng trưởng duyệt theo lý do, '
				. 'không duyệt theo số phút.' );
		}

		$hs   = VHCC_NhanSu::ho_so( $ma );
		$coso = VHCC_NhanSu::chuan_coso( $hs && isset( $hs['cua_hang'] ) ? $hs['cua_hang'] : '' );
		if ( '' === $coso ) {
			$coso = VHCC_NhanSu::chuan_coso( isset( $u['coso'] ) ? $u['coso'] : '' );
		}
		if ( '' === $coso ) {
			return array( 'ok' => false, 'error' => 'Hồ sơ của bạn chưa khai cửa hàng nên đơn không '
				. 'biết gửi cho ai duyệt.' );
		}
		$ho_ten = $hs && isset( $hs['ho_ten'] ) ? trim( (string) $hs['ho_ten'] ) : '';
		if ( '' === $ho_ten ) { $ho_ten = trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) ); }

		$cu  = self::cua_ngay( $ma, $ngay );
		$bang = VHCC_DB::t( 'xin_tre' );
		$hang = array(
			'coso'           => $coso,
			'ngay'           => $ngay,
			'ma_nv'          => $ma,
			'ho_ten'         => $ho_ten,
			'so_phut'        => $phut,
			'ly_do'          => mb_substr( $ly_do, 0, 250 ),
			/* 🔴 NỘP LẠI LÀ VỀ CHỜ DUYỆT. Đơn đã duyệt mà sửa số phút rồi vẫn còn dấu "đã duyệt"
			   thì người ta xin 10 phút, được duyệt, rồi sửa thành 90 — và không ai duyệt cái 90
			   ấy cả. */
			'trang_thai'     => self::CHO,
			'nguoi_duyet'    => '',
			'ma_nguoi_duyet' => '',
			'ly_do_choi'     => '',
			'tao_luc'        => current_time( 'mysql' ),
			'duyet_luc'      => null,
		);
		if ( $cu ) {
			$wpdb->update( $bang, $hang, array( 'id' => (int) $cu['id'] ) );
			$id = (int) $cu['id'];
		} else {
			$wpdb->insert( $bang, $hang );
			$id = (int) $wpdb->insert_id;
		}
		return array( 'ok' => true, 'id' => $id, 'ngay' => $ngay, 'coSo' => $coso,
			'phut' => $phut, 'muon' => ( $lech < 0 ), 'lai' => (bool) $cu );
	}

	/* ====================================================================== duyệt đơn */

	/**
	 * Cửa hàng trưởng duyệt / không duyệt một đơn của CƠ SỞ MÌNH.
	 *
	 * ⚠️ Chốt cơ sở đọc từ CHÍNH ĐƠN, không từ biểu mẫu: id đơn gõ tay được, và một cửa hàng
	 *    trưởng gõ đúng id là duyệt được đơn của cửa hàng khác.
	 */
	public static function duyet( $u, $id, $dat = self::DUYET, $ly_do_choi = '' ) {
		global $wpdb;
		if ( ! VHCC_Vai::duoc( $u, 'lich_lam' ) ) {
			return array( 'ok' => false,
				'error' => 'Duyệt đơn đi trễ cần vai Cửa hàng trưởng trở lên.' );
		}
		$don = self::mot( $id );
		if ( ! $don ) { return array( 'ok' => false, 'error' => 'Không tìm thấy đơn.' ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, (string) $don['coso'] ) ) {
			return array( 'ok' => false, 'error' => 'Đơn này thuộc cửa hàng khác.' );
		}
		if ( ! in_array( $dat, array( self::DUYET, self::TU_CHOI, self::CHO ), true ) ) {
			return array( 'ok' => false, 'error' => 'Không biết việc "' . $dat . '".' );
		}
		$wpdb->update( VHCC_DB::t( 'xin_tre' ), array(
			'trang_thai'     => $dat,
			'nguoi_duyet'    => trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) ),
			'ma_nguoi_duyet' => trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) ),
			'ly_do_choi'     => ( self::TU_CHOI === $dat )
				? mb_substr( trim( (string) $ly_do_choi ), 0, 250 ) : '',
			'duyet_luc'      => current_time( 'mysql' ),
		), array( 'id' => (int) $don['id'] ) );
		return array( 'ok' => true, 'id' => (int) $don['id'], 'trang_thai' => $dat,
			'ma_nv' => (string) $don['ma_nv'], 'ngay' => (string) $don['ngay'] );
	}

	/* ====================================================================== đọc */

	public static function mot( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_tre' ) . ' WHERE id=%d', $id ), ARRAY_A );
		return $r ? $r : null;
	}

	public static function cua_ngay( $ma_nv, $ngay ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		$ng = self::ngay( $ngay );
		if ( '' === $ma || '' === $ng ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_tre' ) . ' WHERE ma_nv=%s AND ngay=%s', $ma, $ng ),
			ARRAY_A );
		return $r ? $r : null;
	}

	/** Đơn của một cửa hàng trong một tháng: [ MÃ ][ ngày ] => hàng. Một lượt hỏi cho cả lưới. */
	public static function ban_do_thang( $coso, $thang ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$th = trim( (string) $thang );
		$ra = array();
		if ( '' === $cs || ! preg_match( '/^\d{4}-\d{2}$/', $th ) ) { return $ra; }
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_tre' )
			. ' WHERE coso=%s AND ngay LIKE %s', $cs, $th . '-%' ), ARRAY_A );
		foreach ( (array) $rows as $r ) {
			$ra[ (string) $r['ma_nv'] ][ (string) $r['ngay'] ] = $r;
		}
		return $ra;
	}

	/** Đơn CHỜ DUYỆT của một cửa hàng — mới nhất trước, vì đơn hôm nay mới là đơn cần xử. */
	public static function cho_duyet( $coso, $so = 100 ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $cs ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_tre' )
			. ' WHERE coso=%s AND trang_thai=%s ORDER BY ngay DESC, id DESC LIMIT %d',
			$cs, self::CHO, max( 1, (int) $so ) ), ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/** Đơn gần đây của MỘT người — để họ tự nhìn thấy đơn mình đã nộp và kết quả. */
	public static function cua_nguoi( $ma_nv, $so = 12 ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_tre' )
			. ' WHERE ma_nv=%s ORDER BY ngay DESC, id DESC LIMIT %d', $ma, max( 1, (int) $so ) ),
			ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * NGÀY ẤY, NGƯỜI ẤY ĐÃ ĐƯỢC DUYỆT ĐI TRỄ CHƯA — câu hỏi mà ô lưới cần.
	 *
	 * Hàm THUẦN: nhận sẵn bản đồ đơn (một lượt hỏi CSDL cho cả lưới), không tự đi hỏi. Lưới có
	 * hơn 600 ô; để mỗi ô tự hỏi một câu là 600 lượt truy vấn cho một lần mở trang.
	 */
	public static function da_duyet( $ban_do, $ma_nv, $ngay ) {
		$ma = (string) $ma_nv;
		$ng = (string) $ngay;
		if ( ! isset( $ban_do[ $ma ][ $ng ] ) ) { return false; }
		return self::DUYET === (string) $ban_do[ $ma ][ $ng ]['trang_thai'];
	}

	/* ====================================================================== nhỏ nhặt */

	public static function ngay( $s ) {
		$s = trim( (string) $s );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
	}

	/** Số phút xin trễ. null = không dùng được. 0 KHÔNG hợp lệ: xin trễ 0 phút là không xin gì. */
	public static function phut( $v ) {
		if ( is_array( $v ) || is_object( $v ) ) { return null; }
		$s = trim( (string) $v );
		if ( ! preg_match( '/^\d{1,3}$/', $s ) ) { return null; }
		$n = (int) $s;
		return ( $n >= 1 && $n <= VHCC_Tre::TOI_DA ) ? $n : null;
	}

	/**
	 * Ngày ấy cách hôm nay mấy ngày: dương = còn ở tương lai, âm = đã qua.
	 * Tách riêng để thử được bằng con số trần, không phải chờ tới ngày mai mới biết đúng sai.
	 */
	public static function cach_hom_nay( $ngay, $hom_nay = '' ) {
		$ng = self::ngay( $ngay );
		if ( '' === $ng ) { return 0; }
		if ( '' === $hom_nay ) { $hom_nay = substr( (string) current_time( 'Y-m-d' ), 0, 10 ); }
		$a = strtotime( $ng . ' 00:00:00 UTC' );
		$b = strtotime( $hom_nay . ' 00:00:00 UTC' );
		if ( false === $a || false === $b ) { return 0; }
		return (int) round( ( $a - $b ) / 86400 );
	}
}
