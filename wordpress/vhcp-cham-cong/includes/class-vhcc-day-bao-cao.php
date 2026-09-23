<?php
/**
 * ĐẨY NGƯỜI TỪ SỔ NHÂN SỰ SANG MÀN QUẢN TRỊ BÁO CÁO CƠ SỞ.
 *
 * =================================================================================================
 * Anh Thắng 15/09/2026: *"lấy giúp anh dữ liệu nhân viên bên chi phí (vì bên đó là các cửa hàng
 * trưởng được quyền nhập báo cáo), bổ sung thêm cột bên trang nhân sự để cấp quyền đẩy sang"*.
 *
 * Màn báo cáo cơ sở là nơi cửa hàng trưởng nhập SỐ ĐẾM ĐƯỢC hằng ngày — tiền mặt trong két, tiền
 * thực nộp, bill huỷ, khách vào — để đối chiếu với số máy POS. Không có cột đẩy thì mỗi cửa hàng
 * trưởng phải được khai tay ở màn quản trị bên ấy, và hai sổ chép tay là hai sổ lệch nhau.
 *
 * =================================================================================================
 * 🔴 ĐẨY CẢ PIN, VÌ MÀN ẤY ĐĂNG NHẬP BẰNG PIN CHẤM CÔNG — KHÔNG PHẢI TÀI KHOẢN WORDPRESS.
 * =================================================================================================
 * Cửa hàng trưởng đã có PIN chấm công đang gõ hằng ngày. Bắt họ nhớ thêm một mật khẩu WordPress
 * là thêm một thứ để quên, thêm một cửa cho bộ phận kỹ thuật phải mở lại — trong khi cả nhà
 * (chi phí, ghế, nội bộ) đều đã vào bằng PIN ấy. Nên cột này đẩy sang y như cột Vận hành chi phí:
 * tên · PIN · cơ sở · vai.
 *
 * ⚠️ MỘT NGƯỜI CÓ THỂ PHỤ TRÁCH HAI CƠ SỞ, và đẩy sang thì phải đủ cả hai. Ô tích cơ sở ở sổ
 *    nhân sự từ 31/08/2026 mang nghĩa "có mặt làm việc đầy đủ tại chi nhánh đó" — chính với phụ
 *    như nhau, nên chỗ này hỏi `VHCC_NhanSu::ds_coso_hs()` chứ không đọc mỗi `cua_hang`.
 *
 * ⚠️ CƠ SỞ HAI BÊN KHÔNG TRÙNG CHỮ. Bên nhân sự là MÃ (`FARM_PT`, `FZ_SC_VIVO_T4`); bên máy POS
 *    là tên dài (*"TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )"*). Đẩy sang thì gửi
 *    NGUYÊN MÃ, và bên ấy có bảng ghép mã ↔ tên cơ sở khai một lần. Đoán hộ ở đây là gán nhầm
 *    cửa hàng trưởng vào cơ sở khác — họ sẽ nhập báo cáo của quán người ta.
 *
 * ⚠️ HỒ SƠ MẤT PIN THÌ GỠ LUÔN, y như hệ chi phí. Xoá PIN của một người thường là để chặn họ
 *    đăng nhập; giữ bản sao mang PIN cũ là để hở đúng cánh cửa vừa định đóng.
 *
 * @package vhcp-cham-cong
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_DayBaoCao {

	/** Tên cột trên bảng "Ai vào được trang nào". */
	const COT = 'bao_cao';

	/** Sổ ghi mã NV của những người đã đẩy: [ maNV => tên đã ghi sang ]. */
	const O_DA_DAY = 'vhcc_day_bao_cao';

	/**
	 * 🔴 BẬC ADMIN, cùng bậc với đẩy sang chi phí và ghế.
	 *
	 * Màn báo cáo cơ sở bày DOANH THU của cơ sở và là nơi khai tiền mặt đếm được. Đẩy nhầm một
	 * người là cho họ xem doanh thu quán khác, và cho họ khai con số mà cả hệ dùng để soi thất
	 * thoát. Không phải màn tiền, nhưng là màn ĐỐI SOÁT tiền — cùng một bậc cẩn thận.
	 */
	const QUYEN = 'he_thong';

	/* ====================================================================== hỏi trạng thái */

	/**
	 * Có plugin báo cáo cơ sở trên site này không, và nó có đủ hàm để ghi không.
	 *
	 * ⚠️ Dò TỪNG HÀM, không dò mỗi tên lớp — cùng lý do như `VHCC_DayChiPhi::co_he_chi_phi()`:
	 *    các plugin cài độc lập nên bản có thể lệch nhau, mà gọi một hàm không tồn tại là Fatal,
	 *    trắng cả trang chứ không phải một ô hỏng.
	 */
	public static function co_he_bao_cao() {
		return function_exists( 'khh_dt_day_vao' )
			&& function_exists( 'khh_dt_day_ra' )
			&& function_exists( 'khh_dt_da_day' );
	}

	/** Sổ mã NV đã đẩy: [ maNV(chữ hoa) => tên đã ghi sang ]. */
	public static function da_day_ds() {
		$x = get_option( self::O_DA_DAY );
		return is_array( $x ) ? $x : array();
	}

	public static function da_day( $ma_nv ) {
		$ma = strtoupper( trim( (string) $ma_nv ) );
		if ( '' === $ma ) { return false; }
		$ds = self::da_day_ds();
		return isset( $ds[ $ma ] );
	}

	/** Ô trên bảng: 'mo' nếu đã đẩy, '' nếu chưa. */
	public static function o( $ma_nv ) {
		return self::da_day( $ma_nv ) ? 'mo' : '';
	}

	/* ====================================================================== ánh xạ vai */

	/**
	 * VAI BÊN CHẤM CÔNG -> VAI BÊN BÁO CÁO CƠ SỞ.
	 *
	 * Bên ấy chỉ có hai bậc:
	 *   'nhap'  — nhập báo cáo ngày của đúng cơ sở mình (cửa hàng trưởng, nhân viên);
	 *   'duyet' — nhập, và xem đối soát của mọi cơ sở (Admin, Quản lý, Kế toán).
	 *
	 * ⚠️ CỬA HÀNG TRƯỞNG -> 'nhap'. Người khai con số đếm được không phải người đi soi con số ấy;
	 *    để họ tự duyệt phần của mình thì cái đối soát này mất hết ý nghĩa.
	 */
	public static function vai_bao_cao( $vai_cc ) {
		$ma = class_exists( 'VHCC_Vai' ) && method_exists( 'VHCC_Vai', 'ma' )
			? VHCC_Vai::ma( $vai_cc ) : '';
		return in_array( $ma, array( 'ADMIN', 'QUAN_LY', 'KE_TOAN' ), true ) ? 'duyet' : 'nhap';
	}

	/* ====================================================================== dựng hàng */

	/**
	 * Hồ sơ để đẩy: [ ma_nv, ho_ten, pin, vai, coso ]. null nếu không đẩy được.
	 *
	 * ⚠️ PIN Ở `nhan_vien.pin_dang_nhap`, KHÔNG ở `phan_quyen.pin` — cùng cái bẫy đã sập một lần
	 *    ở hệ chi phí (28/08/2026): bảng `phan_quyen` là sổ CŨ nạp từ Sheets.
	 */
	public static function ho_so_day( $ma_nv ) {
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return null; }
		$hs = VHCC_NhanSu::ho_so( $ma );
		if ( ! $hs ) { return null; }

		$pin = trim( (string) $hs['pin_dang_nhap'] );
		/* Sổ cũ ghi PIN từ Google Sheets nên có hàng ra "1234.0" — rửa đuôi, không thì bên kia
		   nhận một chuỗi không ai gõ được. */
		if ( preg_match( '/^(\d+)\.0*$/', $pin, $m ) ) { $pin = $m[1]; }
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) { return null; }

		$ten = trim( (string) $hs['ho_ten'] );
		if ( '' === $ten ) { return null; }

		return array(
			'ma_nv'  => strtoupper( $ma ),
			'ho_ten' => $ten,
			'pin'    => $pin,
			'vai'    => self::vai_bao_cao( (string) $hs['vai_tro'] ),
			/* Gửi NGUYÊN MÃ cơ sở của sổ nhân sự; bên báo cáo có bảng ghép mã ↔ tên cơ sở POS.
			   🔴 GỬI CẢ HAI (HAY BA) CƠ SỞ, không chỉ cơ sở chính. Anh Thắng 15/09/2026: *"cho
			   thêm giúp anh bạn nhập được 2 cơ sở"*. Từ 31/08/2026 ô tích cơ sở ở đây nghĩa là
			   "có mặt làm việc đầy đủ tại chi nhánh đó", chính với phụ như nhau — nên `ds_coso_hs()`
			   là câu trả lời đúng, còn `chuan_coso($hs['cua_hang'])` chỉ lấy được cái đầu tiên và
			   người phụ trách hai quán sẽ nhập được một quán rồi bị chối ở quán kia. */
			'coso'   => self::ma_coso( $hs ),
		);
	}

	/**
	 * Chuỗi mã cơ sở gửi sang: mọi cơ sở của hồ sơ, nối bằng dấu phẩy.
	 *
	 * ⚠️ GÁC `method_exists` CÙNG THÂN HÀM với lời gọi — luật của `tools/test/kiem-goi-cheo.php`.
	 *    Thiếu `ds_coso_hs()` (bản chấm công cũ) thì lui về cơ sở chính, một cơ sở vẫn hơn không.
	 */
	private static function ma_coso( $hs ) {
		if ( method_exists( 'VHCC_NhanSu', 'ds_coso_hs' ) ) {
			$ds = (array) VHCC_NhanSu::ds_coso_hs( $hs );
			if ( $ds ) { return implode( ',', $ds ); }
		}
		return VHCC_NhanSu::chuan_coso( (string) $hs['cua_hang'] );
	}

	/* ====================================================================== đẩy / gỡ */

	public static function dat( $u, $ma_nv, $dat ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false,
				'error' => 'Đẩy người sang màn Quản trị báo cáo cơ sở cần vai Admin — màn ấy bày '
					. 'doanh thu cơ sở và là nơi khai tiền mặt đếm được.' );
		}
		/* ⚠️ GÁC `function_exists` CÙNG THÂN HÀM với lời gọi — luật của `tools/test/kiem-goi-cheo.php`.
		   `co_he_bao_cao()` đã kiểm y hệt, nhưng gác ở HÀM KHÁC thì người đọc sau (và bộ soi)
		   không thấy được. */
		if ( ! function_exists( 'khh_dt_day_vao' ) || ! function_exists( 'khh_dt_day_ra' ) ) {
			return array( 'ok' => false,
				'error' => 'Chưa cài plugin Báo cáo doanh thu FABi trên site này (hoặc bản bên ấy quá cũ).' );
		}
		$ma  = strtoupper( trim( (string) $ma_nv ) );
		$dat = (string) $dat;
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }

		$ds_day = self::da_day_ds();

		/* --- GỠ --- */
		if ( 'mo' !== $dat ) {
			if ( ! isset( $ds_day[ $ma ] ) ) { return array( 'ok' => true, 'doi' => 0 ); }
			khh_dt_day_ra( $ma );
			unset( $ds_day[ $ma ] );
			update_option( self::O_DA_DAY, $ds_day, false );
			return array( 'ok' => true, 'doi' => 1, 'viec' => 'go' );
		}

		/* --- ĐẨY --- */
		$hs = self::ho_so_day( $ma );
		if ( ! $hs ) {
			return array( 'ok' => false, 'error' => 'Người mang mã ' . $ma . ' chưa có PIN chấm công '
				. '(4–8 số) — cấp PIN cho họ ở màn Hồ sơ & tài khoản rồi đẩy lại.' );
		}
		$kq = khh_dt_day_vao( $hs );
		if ( empty( $kq['ok'] ) ) {
			return array( 'ok' => false,
				'error' => isset( $kq['error'] ) ? (string) $kq['error'] : 'Bên báo cáo cơ sở từ chối ghi.' );
		}
		$ds_day[ $ma ] = $hs['ho_ten'];
		update_option( self::O_DA_DAY, $ds_day, false );
		return array(
			'ok'      => true,
			'doi'     => 1,
			'viec'    => isset( $kq['viec'] ) ? $kq['viec'] : 'them',
			/* Bên ấy kể lại ai bị xoá PIN vì trùng, và ai chưa ghép được cơ sở — phải nói ra,
			   im lặng là sáng hôm sau có người không vào được mà không ai biết vì sao. */
			'mat_pin' => isset( $kq['mat_pin'] ) ? (array) $kq['mat_pin'] : array(),
			'chua_ghep' => ! empty( $kq['chua_ghep'] ),
		);
	}

	/**
	 * Đẩy / gỡ một loạt theo bảng vừa gửi lên: [ maNV => 'mo' | '' ].
	 *
	 * ⚠️ BỎ QUA NGƯỜI KHÔNG ĐỔI — bảng gửi lên có cả trăm hàng mà thường chỉ vài hàng đổi.
	 */
	public static function luu_nhieu( $u, $bang ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'doi' => 0,
				'error' => 'Đẩy người sang màn Quản trị báo cáo cơ sở cần vai Admin.' );
		}
		$doi       = 0;
		$loi       = array();
		$mat_pin   = array();
		$chua_ghep = array();
		foreach ( (array) $bang as $ma => $dat ) {
			$ma  = trim( (string) $ma );
			$dat = (string) $dat;
			if ( '' === $ma ) { continue; }
			if ( self::o( $ma ) === ( 'mo' === $dat ? 'mo' : '' ) ) { continue; }
			$kq = self::dat( $u, $ma, $dat );
			if ( empty( $kq['ok'] ) ) { $loi[] = $kq['error']; continue; }
			$doi += (int) ( isset( $kq['doi'] ) ? $kq['doi'] : 0 );
			foreach ( (array) ( isset( $kq['mat_pin'] ) ? $kq['mat_pin'] : array() ) as $t_mp ) {
				$mat_pin[ $t_mp ] = true;
			}
			if ( ! empty( $kq['chua_ghep'] ) ) { $chua_ghep[ $ma ] = true; }
		}
		return array( 'ok' => true, 'doi' => $doi, 'loi' => $loi,
			'mat_pin' => array_keys( $mat_pin ), 'chua_ghep' => array_keys( $chua_ghep ) );
	}

	/**
	 * BẢN SAO BÊN BÁO CÁO PHẢI THEO BẢN GỐC — gọi sau mỗi lần sửa hồ sơ / đổi vai / chuyển cơ sở.
	 *
	 * 🔴 CỐ Ý KHÔNG KIỂM QUYỀN ĐẨY, y như `VHCC_DayGhe::dong_bo()` và `VHCC_DayChiPhi::dong_bo()`.
	 *    Hàm này không MỞ đường cho ai: nó chỉ giữ cho bản sao của một người ĐÃ ĐƯỢC ĐẨY khớp với
	 *    bản gốc. Bắt nó đòi vai Admin thì Cửa hàng trưởng đổi PIN cho nhân viên mình xong, bản
	 *    sao đứng im.
	 */
	public static function dong_bo( $ma_nv ) {
		$ma = strtoupper( trim( (string) $ma_nv ) );
		if ( '' === $ma || ! self::da_day( $ma ) || ! self::co_he_bao_cao() ) {
			return array( 'ok' => true, 'doi' => 0 );
		}
		$gia_admin = array( 'name' => 'dong_bo', 'role' => 'Admin' );
		$hs = self::ho_so_day( $ma );
		return self::dat( $gia_admin, $ma, $hs ? 'mo' : '' );
	}
}
