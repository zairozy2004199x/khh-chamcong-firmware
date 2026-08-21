<?php
/**
 * YÊU CẦU NHÂN VIÊN — xin thêm người, xin sửa hồ sơ, và ô "gửi thông tin vào máy chấm công".
 *
 * ⚠️ DUYỆT một yêu cầu thêm người là CẤP MÃ NV dùng chung cả chuỗi -> chỉ Admin/Quản lý. Cửa hàng
 *    trưởng GỬI được yêu cầu nhưng không duyệt được yêu cầu của chính mình. Nếu cho duyệt thì hai
 *    bậc quyền bên VHCC_NhanSu thành vô nghĩa: ai cũng tự cấp mã cho mình qua đường yêu cầu.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_YeuCau {

	const CHO    = 'Chờ duyệt';
	const DUYET  = 'Đã duyệt';
	const TU_CHOI = 'Từ chối';

	private static function ma_moi() {
		return 'YC' . gmdate( 'YmdHis', (int) current_time( 'timestamp' ) ) . wp_rand( 100, 999 );
	}

	public static function ds( $u, $chi_cho = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . VHCC_DB::t( 'yeu_cau_nv' );
		if ( $chi_cho ) { $sql .= $wpdb->prepare( ' WHERE trang_thai=%s', self::CHO ); }
		$sql .= ' ORDER BY luc_xin DESC';
		$out = array();
		foreach ( VHCC_DB::rows( $sql ) as $r ) {
			/* Yêu cầu KHÔNG có cơ sở (người ngoài tự gửi qua trang chấm công phụ) thì chỉ
			   Admin/Quản lý thấy — không gán bừa cho một cửa hàng nào. */
			if ( '' === trim( (string) $r['coso'] ) ) {
				if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) { continue; }
			} elseif ( ! VHCC_NhanSu::co_quyen_coso( $u, $r['coso'] ) ) {
				continue;
			}
			$out[] = $r;
		}
		return $out;
	}

	public static function dem_cho( $u ) {
		return count( self::ds( $u, true ) );
	}

	/** Gửi một yêu cầu. Cửa hàng trưởng gửi được; nhân viên thì không (họ dùng gui_thong_tin_nv). */
	public static function gui( $u, $dat ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_sua_ho_so( $u ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền gửi yêu cầu nhân sự.' );
		}
		$coso = VHCC_NhanSu::chuan_coso( isset( $dat['coso'] ) ? $dat['coso'] : '' );
		if ( '' !== $coso && ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		$noi_dung = trim( isset( $dat['noi_dung'] ) ? (string) $dat['noi_dung'] : '' );
		if ( '' === $noi_dung ) {
			return array( 'ok' => false, 'error' => 'Yêu cầu rỗng thì người duyệt không biết duyệt gì.' );
		}
		$ma_yc = self::ma_moi();
		$ok = $wpdb->insert( VHCC_DB::t( 'yeu_cau_nv' ), array(
			'ma_yc' => $ma_yc,
			'loai' => trim( isset( $dat['loai'] ) ? (string) $dat['loai'] : 'Thêm người' ),
			'ma_nv' => trim( isset( $dat['ma_nv'] ) ? (string) $dat['ma_nv'] : '' ),
			'ho_ten' => trim( isset( $dat['ho_ten'] ) ? (string) $dat['ho_ten'] : '' ),
			'coso' => $coso, 'noi_dung' => $noi_dung, 'trang_thai' => self::CHO,
			'nguoi_xin' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'luc_xin' => current_time( 'mysql' ) ) );
		return ( false === $ok )
			? array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error )
			: array( 'ok' => true, 'maYc' => $ma_yc );
	}

	/** Gửi nhiều yêu cầu một lượt. Trả số gửi được và danh sách bị bỏ, KÈM lý do từng cái. */
	public static function gui_loat( $u, $ds ) {
		$duoc = array();
		$bo = array();
		foreach ( (array) $ds as $i => $x ) {
			$r = self::gui( $u, (array) $x );
			if ( ! empty( $r['ok'] ) ) { $duoc[] = $r['maYc']; }
			else { $bo[] = 'dòng ' . ( (int) $i + 1 ) . ': ' . $r['error']; }
		}
		return array( 'ok' => true, 'duoc' => $duoc, 'bo' => $bo );
	}

	/**
	 * DUYỆT. Chỉ Admin/Quản lý — duyệt là cấp Mã NV dùng chung cả chuỗi.
	 * `$tao_ho_so` = true thì tạo luôn hồ sơ; dùng lại VHCC_NhanSu::luu_ho_so để đi qua ĐÚNG bộ
	 * chốt quyền đó, không viết đường tạo hồ sơ thứ hai.
	 */
	public static function duyet( $u, $ma_yc, $tao_ho_so = false, $ho_so = array() ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false,
				'error' => 'Duyệt yêu cầu là cấp Mã NV cho cả chuỗi — ' . VHCC_NhanSu::LOI_QT );
		}
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'yeu_cau_nv' ) . ' WHERE ma_yc=%s', trim( (string) $ma_yc ) ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy yêu cầu.' ); }
		if ( self::CHO !== $r['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Yêu cầu đã xử lý rồi (' . $r['trang_thai'] . ').' );
		}
		$kq_hs = null;
		if ( $tao_ho_so ) {
			$dat = array_merge( array( 'ma_nv' => $r['ma_nv'], 'ho_ten' => $r['ho_ten'],
				'cua_hang' => $r['coso'] ), (array) $ho_so );
			$kq_hs = VHCC_NhanSu::luu_ho_so( $u, $dat );
			/* Hồ sơ tạo trượt thì KHÔNG đánh dấu đã duyệt — không thì yêu cầu hiện "Đã duyệt" mà
			   không có hồ sơ nào, và không ai biết phải làm lại. */
			if ( empty( $kq_hs['ok'] ) ) { return $kq_hs; }
		}
		$wpdb->update( VHCC_DB::t( 'yeu_cau_nv' ), array(
			'trang_thai' => self::DUYET,
			'nguoi_duyet' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'luc_duyet' => current_time( 'mysql' ) ), array( 'id' => (int) $r['id'] ) );
		return array( 'ok' => true, 'hoSo' => $kq_hs );
	}

	public static function tu_choi( $u, $ma_yc, $ly_do = '' ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Xử lý yêu cầu — ' . VHCC_NhanSu::LOI_QT );
		}
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'yeu_cau_nv' ) . ' WHERE ma_yc=%s', trim( (string) $ma_yc ) ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy yêu cầu.' ); }
		if ( self::CHO !== $r['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Yêu cầu đã xử lý rồi (' . $r['trang_thai'] . ').' );
		}
		/* Từ chối PHẢI có lý do: người gửi không biết vì sao bị từ chối thì sẽ gửi lại y như cũ. */
		if ( '' === trim( (string) $ly_do ) ) {
			return array( 'ok' => false, 'error' => 'Ghi lý do từ chối — không thì người gửi sẽ gửi lại y như cũ.' );
		}
		$wpdb->update( VHCC_DB::t( 'yeu_cau_nv' ), array(
			'trang_thai' => self::TU_CHOI, 'ghi_chu' => trim( (string) $ly_do ),
			'nguoi_duyet' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'luc_duyet' => current_time( 'mysql' ) ), array( 'id' => (int) $r['id'] ) );
		return array( 'ok' => true );
	}

	/**
	 * Ô "GỬI THÔNG TIN VÀO MÁY CHẤM CÔNG" trên trang chấm công phụ — người CHƯA có hồ sơ tự gửi.
	 *
	 * ⚠️ KHÔNG cần đăng nhập: người chưa có hồ sơ thì chưa có tài khoản. Nên đây là cửa mở, và:
	 *    · nó CHỈ ghi vào bảng yêu cầu, KHÔNG tạo hồ sơ, KHÔNG cấp mã, KHÔNG chạm chấm công;
	 *    · có bộ đếm chặn nhịp độ, không thì một vòng lặp là đầy bảng yêu cầu.
	 */
	public static function gui_thong_tin_nv( $dat ) {
		global $wpdb;
		$ten = trim( isset( $dat['ho_ten'] ) ? (string) $dat['ho_ten'] : '' );
		$sdt = trim( isset( $dat['sdt'] ) ? (string) $dat['sdt'] : '' );
		$coso = VHCC_NhanSu::chuan_coso( isset( $dat['coso'] ) ? $dat['coso'] : '' );
		if ( '' === $ten || '' === $sdt ) {
			return array( 'ok' => false, 'error' => 'Cần ít nhất Họ tên và Số điện thoại.' );
		}
		if ( '' === $coso ) {
			return array( 'ok' => false, 'error' => 'Chọn cơ sở anh/chị sẽ làm.' );
		}
		/* Cửa mở thì phải có nhịp độ. Đếm theo SỐ ĐIỆN THOẠI: chặn gửi lặp mà không chặn cả cơ sở. */
		if ( VHCC_Quyen::dem( 'guitt_' . preg_replace( '/\D+/', '', $sdt ), 3 )['qua'] ) {
			return array( 'ok' => false, 'error' => 'Số này vừa gửi rồi. Chờ ít phút, '
				. 'hoặc hỏi trực tiếp quản lý cửa hàng.' );
		}
		$ma_yc = self::ma_moi();
		$wpdb->insert( VHCC_DB::t( 'yeu_cau_nv' ), array(
			'ma_yc' => $ma_yc, 'loai' => 'NV tự gửi thông tin', 'ma_nv' => '',
			'ho_ten' => $ten, 'coso' => $coso,
			'noi_dung' => wp_json_encode( array(
				'sdt' => $sdt,
				'cccd' => trim( isset( $dat['cccd'] ) ? (string) $dat['cccd'] : '' ),
				'ngaySinh' => trim( isset( $dat['ngay_sinh'] ) ? (string) $dat['ngay_sinh'] : '' ),
				'diaChi' => trim( isset( $dat['dia_chi'] ) ? (string) $dat['dia_chi'] : '' ),
			), JSON_UNESCAPED_UNICODE ),
			'trang_thai' => self::CHO, 'nguoi_xin' => $ten . ' (tự gửi)',
			'luc_xin' => current_time( 'mysql' ) ) );
		return array( 'ok' => true, 'maYc' => $ma_yc,
			'ghiChu' => 'Đã gửi. Quản lý cửa hàng sẽ lập hồ sơ và cấp mã cho anh/chị.' );
	}
}
