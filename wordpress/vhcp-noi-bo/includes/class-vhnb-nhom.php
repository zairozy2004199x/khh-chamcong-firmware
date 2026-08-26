<?php
/**
 * NHÓM TỰ TẠO — ai cũng lập được, mời người vào bằng MÃ NHÂN VIÊN.
 *
 * Anh Thắng 26/08/2026: *"nhớ thêm tạo nhóm chat nội bộ cho từng nhóm cho ai tự tạo ra nhé, để
 * mời ai vào thì thêm nv đó vào thôi"*.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 NHÓM LÀ RIÊNG CỦA THÀNH VIÊN — KỂ CẢ ADMIN CŨNG KHÔNG ĐỌC ĐƯỢC NẾU KHÔNG Ở TRONG.
 *
 *    Đây là quyết định có chủ ý, và nó khác với mọi chỗ khác trong hệ (bảng công, chi phí — nơi
 *    Admin thấy hết). Lý do: một nhóm nội bộ là chỗ người ta bàn việc với nhau, và nếu ai cũng
 *    biết "sếp đọc được hết" thì không ai bàn gì ở đó nữa — cái nhóm thành một trang trắng, mà
 *    trang trắng thì không đáng để dựng.
 *
 *    Đổi lại, Admin VẪN XOÁ ĐƯỢC cả nhóm (dọn nhóm rác, nhóm của người đã nghỉ). Xoá mà không
 *    đọc nghe lạ, nhưng đúng: quyền dọn dẹp không phải quyền đọc trộm.
 *
 * ⚠️ MỜI BẰNG MÃ NV, KHÔNG PHẢI BẰNG TÊN. Tên người Việt trùng rất nhiều; mời nhầm một người
 *    trùng tên vào nhóm bàn lương là chuyện không rút lại được.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Nhom {

	/** Một người lập tối đa ngần này nhóm. Không chặn thì một lượt bấm nhầm sinh ra hàng trăm. */
	const TOI_DA_MOI_NGUOI = 30;
	const TEN_TOI_DA = 120;

	/* ==================================================================== lập / xoá */

	/**
	 * Lập nhóm. Người lập tự động là thành viên, vai 'chu'.
	 *
	 * 🔴 NGƯỜI LẬP PHẢI CÓ MÃ NV. Cả cơ chế thành viên chạy trên mã; người mã rỗng mà lập nhóm
	 *    thì chính họ không vào được nhóm mình vừa lập, và không ai giải thích được vì sao.
	 */
	public static function lap( $u, $ten, $mo_ta = '' ) {
		global $wpdb;
		$_q = VHNB_Quyen::vi_sao_khong( $u, 'nhom' );
		if ( '' !== $_q ) { return array( 'ok' => false, 'error' => $_q ); }
		$ma = self::ma( $u );
		if ( '' === $ma ) {
			return array( 'ok' => false,
				'error' => 'Tài khoản này chưa có Mã NV nên chưa lập nhóm được — nhờ Admin khai giúp ở hồ sơ.' );
		}
		$ten = VHNB_Bai::gon( $ten, self::TEN_TOI_DA );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Nhóm phải có tên.' ); }

		$da = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'nhom' ) . ' WHERE ma_nv_tao=%s', $ma ) );
		if ( $da >= self::TOI_DA_MOI_NGUOI ) {
			return array( 'ok' => false, 'error' => 'Mỗi người lập tối đa '
				. self::TOI_DA_MOI_NGUOI . ' nhóm. Xoá bớt nhóm cũ rồi lập tiếp.' );
		}

		$ok = $wpdb->insert( VHNB_DB::t( 'nhom' ), array(
			'ten'        => $ten,
			'mo_ta'      => VHNB_Bai::gon( $mo_ta, 255 ),
			'ma_nv_tao'  => $ma,
			'ho_ten_tao' => self::ten( $u ),
			'tao_luc'    => current_time( 'mysql' ),
		) );
		if ( false === $ok ) { return array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error ); }
		$id = (int) $wpdb->insert_id;

		/* Người lập vào nhóm NGAY, cùng một lượt. Để họ tự thêm mình sau là có một khoảng thời
		   gian nhóm không có ai — và trong khoảng ấy chính người lập cũng không đọc được nó. */
		self::them_tv( $id, $ma, self::ten( $u ), 'chu' );
		self::dem_lai( $id );
		return array( 'ok' => true, 'id' => $id );
	}

	/** Xoá nhóm: chủ nhóm hoặc Admin. Xoá luôn thành viên và bài của nhóm. */
	public static function xoa( $u, $nhom_id ) {
		global $wpdb;
		$n = self::mot( $nhom_id );
		if ( ! $n ) { return array( 'ok' => false, 'error' => 'Nhóm này không còn.' ); }
		if ( ! self::la_chu( $u, $n ) && ! VHNB_Bai::la_admin( $u ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ người lập nhóm hoặc Admin mới xoá được nhóm.' );
		}
		$id = (int) $n['id'];
		/* Bài của nhóm phải đi theo. Để lại là mấy bài mồ côi mang `nhom_id` trỏ vào một nhóm
		   không còn — không ai đọc được, mà vẫn nằm trong kho. */
		foreach ( VHNB_DB::rows( $wpdb->prepare(
			'SELECT id FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE nhom_id=%d', $id ) ) as $b ) {
			$wpdb->delete( VHNB_DB::t( 'binh_luan' ), array( 'bai_id' => (int) $b['id'] ) );
			$wpdb->delete( VHNB_DB::t( 'tim' ), array( 'bai_id' => (int) $b['id'] ) );
		}
		$wpdb->delete( VHNB_DB::t( 'bai' ), array( 'nhom_id' => $id ) );
		$wpdb->delete( VHNB_DB::t( 'thanh_vien' ), array( 'nhom_id' => $id ) );
		$wpdb->delete( VHNB_DB::t( 'nhom' ), array( 'id' => $id ) );
		return array( 'ok' => true );
	}

	/* ==================================================================== thành viên */

	/**
	 * Mời một người vào nhóm bằng MÃ NV.
	 *
	 * ⚠️ Mã phải có hồ sơ thật — nếu hệ chấm công có mặt để mà hỏi. Mời một mã không tồn tại thì
	 *    nhóm có một thành viên ma: đếm thì có, mà không ai đọc được bài trong đó.
	 */
	public static function moi( $u, $nhom_id, $ma_nv ) {
		$n = self::mot( $nhom_id );
		if ( ! $n ) { return array( 'ok' => false, 'error' => 'Nhóm này không còn.' ); }
		if ( ! self::la_chu( $u, $n ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ người lập nhóm mới thêm người vào được.' );
		}
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Chưa nhập mã nhân viên.' ); }
		if ( self::la_tv( $nhom_id, $ma ) ) {
			return array( 'ok' => false, 'error' => 'Mã "' . $ma . '" đã ở trong nhóm rồi.' );
		}

		$ho_ten = '';
		/* ⚠️ Gác `method_exists` cùng hàm với lời gọi — plugin chấm công có thể chưa cài, và gọi
		   hụt một hàm tĩnh là Fatal error, trắng cả trang. */
		if ( class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'ho_so' ) ) {
			$hs = VHCC_NhanSu::ho_so( $ma );
			if ( ! $hs ) {
				return array( 'ok' => false,
					'error' => 'Không thấy hồ sơ của mã "' . $ma . '". Gõ đúng mã nhân viên (không phải tên).' );
			}
			$ho_ten = (string) $hs['ho_ten'];
		}
		self::them_tv( (int) $n['id'], $ma, $ho_ten, 'tv' );
		self::dem_lai( (int) $n['id'] );
		/* Được mời vào một nhóm kín mà không có gì báo thì người ta không biết nhóm ấy tồn tại
		   — nhóm chỉ hiện ở cột trái, mà họ có mở trang đâu để thấy. */
		VHNB_Bao::gui( $ma, 'noi_bo',
			self::ten( $u ) . ' thêm bạn vào nhóm "' . (string) $n['ten'] . '"',
			VHNB_Trang::url() . '?g=' . (int) $n['id'],
			'nhom_moi:' . (int) $n['id'], self::ma( $u ) );
		return array( 'ok' => true, 'maNV' => $ma, 'hoTen' => $ho_ten );
	}

	/**
	 * Bỏ một người khỏi nhóm. Chủ nhóm bỏ được người khác; ai cũng tự rời được nhóm.
	 *
	 * 🔴 CHỦ NHÓM KHÔNG TỰ RỜI ĐƯỢC. Rời thì nhóm còn lại một đám thành viên mà không ai thêm
	 *    bớt được ai nữa — một nhóm chết mà vẫn hiện ra. Muốn thôi thì XOÁ nhóm.
	 */
	public static function bo( $u, $nhom_id, $ma_nv ) {
		global $wpdb;
		$n = self::mot( $nhom_id );
		if ( ! $n ) { return array( 'ok' => false, 'error' => 'Nhóm này không còn.' ); }
		$ma  = trim( (string) $ma_nv );
		$toi = self::ma( $u );
		$tu_roi = ( '' !== $toi && 0 === strcasecmp( $ma, $toi ) );
		if ( ! $tu_roi && ! self::la_chu( $u, $n ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ người lập nhóm mới bỏ người khác ra được.' );
		}
		if ( 0 === strcasecmp( $ma, (string) $n['ma_nv_tao'] ) ) {
			return array( 'ok' => false,
				'error' => 'Người lập nhóm không rời nhóm được — rời thì không còn ai thêm bớt thành viên. '
					. 'Muốn thôi thì xoá hẳn nhóm.' );
		}
		$wpdb->delete( VHNB_DB::t( 'thanh_vien' ),
			array( 'nhom_id' => (int) $n['id'], 'ma_nv' => $ma ) );
		self::dem_lai( (int) $n['id'] );
		return array( 'ok' => true );
	}

	private static function them_tv( $nhom_id, $ma, $ho_ten, $vai ) {
		global $wpdb;
		$wpdb->insert( VHNB_DB::t( 'thanh_vien' ), array(
			'nhom_id' => (int) $nhom_id,
			'ma_nv'   => (string) $ma,
			'ho_ten'  => (string) $ho_ten,
			'vai'     => ( 'chu' === $vai ) ? 'chu' : 'tv',
			'tao_luc' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Đếm LẠI số thành viên từ chính bảng thành viên.
	 *
	 * 🔴 KHÔNG dùng `so_tv = so_tv + 1`. Cộng dồn thì chỉ cần một lượt ghi trượt (khoá UNIQUE
	 *    chặn, bấm hai lần) là con số lệch VĨNH VIỄN, và không có cách nào biết nó đã lệch.
	 */
	public static function dem_lai( $nhom_id ) {
		global $wpdb;
		$n = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'thanh_vien' ) . ' WHERE nhom_id=%d', (int) $nhom_id ) );
		$wpdb->update( VHNB_DB::t( 'nhom' ), array( 'so_tv' => $n ), array( 'id' => (int) $nhom_id ) );
	}

	/* ==================================================================== đọc */

	public static function mot( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHNB_DB::t( 'nhom' ) . ' WHERE id=%d', (int) $id ), ARRAY_A );
	}

	/** Những nhóm mà người này ĐANG Ở TRONG. Không ở trong thì không thấy — kể cả Admin. */
	public static function cua_toi( $u ) {
		global $wpdb;
		$ma = self::ma( $u );
		if ( '' === $ma ) { return array(); }
		return VHNB_DB::rows( $wpdb->prepare(
			'SELECT n.* FROM ' . VHNB_DB::t( 'nhom' ) . ' n'
			. ' INNER JOIN ' . VHNB_DB::t( 'thanh_vien' ) . ' t ON t.nhom_id = n.id'
			. ' WHERE t.ma_nv=%s ORDER BY n.ten ASC', $ma ) );
	}

	public static function ds_thanh_vien( $nhom_id ) {
		global $wpdb;
		return VHNB_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHNB_DB::t( 'thanh_vien' )
			. " WHERE nhom_id=%d ORDER BY (vai='chu') DESC, ho_ten ASC, ma_nv ASC", (int) $nhom_id ) );
	}

	public static function la_tv( $nhom_id, $ma_nv ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return false; }
		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . VHNB_DB::t( 'thanh_vien' ) . ' WHERE nhom_id=%d AND ma_nv=%s',
			(int) $nhom_id, $ma ) );
	}

	/** Người này có đọc / đăng được trong nhóm không. */
	public static function duoc_vao( $u, $nhom_id ) {
		return self::la_tv( $nhom_id, self::ma( $u ) );
	}

	public static function la_chu( $u, $n ) {
		$ma = self::ma( $u );
		return ( '' !== $ma && 0 === strcasecmp( $ma, (string) $n['ma_nv_tao'] ) );
	}

	/* ==================================================================== phụ */

	private static function ma( $u ) {
		return trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
	}
	private static function ten( $u ) {
		return trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) );
	}
}
