<?php
/**
 * GIỜ NHÂN VIÊN TỰ KHAI — để cửa hàng theo dõi, KHÔNG cộng vào lương.
 *
 * Anh Thắng 18/09/2026: *"Nhân viên có quyền nhập giờ khác vào đây để cửa hàng cũng biết để
 * theo dõi cũng nhân viên (nó chỉ không cộng vào bảng tổng lương thôi) nhưng sẽ hiện cột tổng
 * ở cuối trang"*.
 *
 * =============================================================================================
 * 🔴 ĐÂY LÀ CON SỐ NGƯỜI TA TỰ NÓI, KHÔNG PHẢI CON SỐ MÁY GHI
 * =============================================================================================
 * Cả hệ chấm công dựng trên đúng một nguyên tắc: giờ công là thứ MÁY ghi lại được, và mọi
 * đường sửa nó đều có cửa, có lý do, có nhật ký. Lớp này mở một dòng chảy NGƯỢC LẠI — nhân
 * viên tự gõ một con số, không ai duyệt.
 *
 * Nên nó phải tách bạch tuyệt đối:
 *   · BẢNG RIÊNG, không nhét vào `cham_cong`;
 *   · KHÔNG một hàm tính lương nào đọc tới nó;
 *   · trên lưới bày ở MỘT CỘT RIÊNG ở cuối, không trộn vào cột TỔNG.
 *
 * ⚠️ VÌ SAO KHÔNG BẮT DUYỆT. Duyệt thì nó thành một cái đơn nữa cho cửa hàng trưởng, và cái
 *    giá trị duy nhất của nó — "cửa hàng biết ngay hôm nay ai làm thêm gì" — mất sạch. Nó
 *    không đẻ ra đồng nào nên không cần cửa; thứ cần là NÓI RÕ nó là số tự khai, ở mọi chỗ
 *    bày nó ra.
 *
 * ⚠️ KHAI LẠI LÀ ĐÈ, KHÔNG CỘNG DỒN. Bấm Lưu hai lần là số gấp đôi mà không ai biết.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_GioKhai {

	/** Ai khai được: chính mình, bậc thấp nhất. Đây là cửa của nhân viên. */
	const QUYEN = 'cong_minh';

	/** Trần một ngày. Quá đây thì không còn là giờ làm nữa mà là gõ nhầm. */
	const GIO_TOI_DA = 24.0;

	/** Lùi xa nhất được khai. Khai bù ba tháng trước thì không ai đối chiếu được nữa. */
	const NGAY_LUI_TOI_DA = 31;

	/* ====================================================================== ghi */

	/**
	 * Nhân viên tự khai giờ của MỘT ngày.
	 *
	 * ⚠️ CƠ SỞ LẤY TỪ HỒ SƠ, KHÔNG NHẬN TỪ THÂN YÊU CẦU. Nhận từ thân là khai được vào cơ sở
	 *    mình không thuộc về, và lúc ấy bảng theo dõi của cửa hàng người ta mọc thêm một dòng
	 *    lạ. Đây là cửa mở cho bậc thấp nhất nên chốt phải chặt nhất.
	 */
	public static function khai( $u, $ngay, $so_gio, $viec = '', $ghi_chu = '' ) {
		global $wpdb;

		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Tài khoản này chưa gắn Mã NV nên chưa khai được.' );
		}
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Khai giờ' ) );
		}

		$ng = trim( (string) $ngay );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ng ) ) {
			return array( 'ok' => false, 'error' => 'Ngày không hợp lệ.' );
		}
		$hom_nay = (string) current_time( 'Y-m-d' );
		if ( $ng > $hom_nay ) {
			return array( 'ok' => false, 'error' => 'Chưa khai được cho ngày mai.' );
		}
		$lui = (int) round( ( strtotime( $hom_nay . ' 00:00:00 UTC' ) - strtotime( $ng . ' 00:00:00 UTC' ) ) / 86400 );
		if ( $lui > self::NGAY_LUI_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Chỉ khai được trong vòng '
				. self::NGAY_LUI_TOI_DA . ' ngày gần đây. Xa hơn thì báo cửa hàng trưởng.' );
		}

		$gio = (float) str_replace( ',', '.', trim( (string) $so_gio ) );
		if ( $gio < 0 || $gio > self::GIO_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Số giờ phải trong khoảng 0 đến '
				. (int) self::GIO_TOI_DA . '.' );
		}
		$gio = round( $gio, 2 );

		/* Cơ sở: lấy cơ sở CHẤM CÔNG của chính người ấy. Ai có nhiều cơ sở thì lấy cơ sở đang
		   đăng nhập, miễn là nó nằm trong danh sách của họ. */
		$cs = self::coso_cua( $u );
		if ( '' === $cs ) {
			return array( 'ok' => false, 'error' => 'Hồ sơ của anh/chị chưa tích cơ sở nào — '
				. 'nhờ quản lý cửa hàng mở giúp rồi khai lại.' );
		}

		$cu = self::mot( $cs, $ng, $ma );

		/* Khai 0 giờ = XOÁ dòng. Không để lại một dòng 0 giờ nằm trong bảng theo dõi: nó chiếm
		   chỗ, trông như có khai, mà không nói gì. */
		if ( $gio <= 0 ) {
			if ( $cu ) { $wpdb->delete( VHCC_DB::t( 'gio_khai' ), array( 'id' => (int) $cu['id'] ) ); }
			return array( 'ok' => true, 'xoa' => true, 'ngay' => $ng, 'soGio' => 0.0 );
		}

		$dat = array(
			'coso'    => $cs,
			'ngay'    => $ng,
			'ma_nv'   => $ma,
			'ho_ten'  => trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) ),
			'so_gio'  => $gio,
			'viec'    => mb_substr( trim( (string) $viec ), 0, 120 ),
			'ghi_chu' => mb_substr( trim( (string) $ghi_chu ), 0, 250 ),
			'sua_luc' => current_time( 'mysql' ),
		);
		if ( $cu ) {
			/* ⚠️ ĐÈ, không cộng dồn — xem khối cảnh báo ở đầu tệp. */
			$wpdb->update( VHCC_DB::t( 'gio_khai' ), $dat, array( 'id' => (int) $cu['id'] ) );
		} else {
			$dat['tao_luc'] = current_time( 'mysql' );
			$wpdb->insert( VHCC_DB::t( 'gio_khai' ), $dat );
		}
		return array( 'ok' => true, 'ngay' => $ng, 'soGio' => $gio, 'coSo' => $cs );
	}

	/**
	 * Cơ sở CHẤM CÔNG của một người.
	 *
	 * ⚠️ Dùng `ds_coso_cham_cua_nv()`, KHÔNG dùng `ds_coso_cua()`. Anh Thắng 17/09/2026:
	 *    *"Cơ sở được chọn để chấm công là cơ sở người đang đăng nhập chấm công và tính lương.
	 *    Cơ sở phụ trách là cơ sở theo dõi nhân sự… chứ không có chấm công trong đó"*. Giờ tự
	 *    khai đi cùng giờ chấm công, nên nó theo danh sách CHẤM, không theo danh sách phụ trách.
	 */
	private static function coso_cua( $u ) {
		$hs = VHCC_NhanSu::ho_so( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' );
		if ( ! $hs ) { return ''; }
		$ds = (array) VHCC_NhanSu::ds_coso_cham( $hs );
		if ( ! $ds ) { return ''; }
		/* Ai làm nhiều cơ sở thì lấy cơ sở họ ĐANG đăng nhập, miễn là nó nằm trong danh sách
		   chấm của chính họ. Không nằm trong thì lùi về cơ sở đầu — chứ không nhận bừa. */
		$dang = VHCC_NhanSu::chuan_coso( isset( $u['coso'] ) ? $u['coso'] : '' );
		foreach ( $ds as $x ) {
			if ( 0 === strcasecmp( (string) $x, $dang ) ) { return (string) $x; }
		}
		return (string) $ds[0];
	}

	public static function mot( $coso, $ngay, $ma_nv ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'gio_khai' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND ngay=%s AND ma_nv=%s LIMIT 1',
			VHCC_NhanSu::chuan_coso( $coso ), (string) $ngay, trim( (string) $ma_nv ) ), ARRAY_A );
		return $r ? $r : null;
	}

	/* ====================================================================== đọc */

	/** Mấy ngày gần đây của CHÍNH MÌNH — để trạm bày lại thứ họ đã khai. */
	public static function cua_toi( $u, $so = 31 ) {
		global $wpdb;
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT ngay, so_gio, viec, ghi_chu, coso FROM ' . VHCC_DB::t( 'gio_khai' )
			. ' WHERE ma_nv=%s ORDER BY ngay DESC LIMIT %d', $ma, max( 1, min( 100, (int) $so ) ) ),
			ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * Tổng giờ tự khai của từng người trong một tháng — cho cột riêng trên lưới bảng công.
	 *
	 * @return array [ MÃ NV (hoa) => array( 'gio' => float, 'ngay' => int ) ]
	 */
	public static function thang_cua( $coso, $thang ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$th = trim( (string) $thang );
		if ( '' === $cs || ! preg_match( '/^\d{4}-\d{2}$/', $th ) ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT ma_nv, SUM(so_gio) AS g, COUNT(*) AS n FROM ' . VHCC_DB::t( 'gio_khai' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND ngay LIKE %s GROUP BY ma_nv',
			$cs, $th . '-%' ), ARRAY_A );
		$ra = array();
		foreach ( (array) $r as $x ) {
			$ra[ strtoupper( trim( (string) $x['ma_nv'] ) ) ] = array(
				'gio'  => round( (float) $x['g'], 2 ),
				'ngay' => (int) $x['n'],
			);
		}
		return $ra;
	}

	/** Từng dòng của một tháng — cho khối chi tiết dưới lưới. */
	public static function ds_thang( $coso, $thang ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$th = trim( (string) $thang );
		if ( '' === $cs || ! preg_match( '/^\d{4}-\d{2}$/', $th ) ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'gio_khai' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND ngay LIKE %s ORDER BY ngay DESC, ho_ten',
			$cs, $th . '-%' ), ARRAY_A );
		return is_array( $r ) ? $r : array();
	}
}
