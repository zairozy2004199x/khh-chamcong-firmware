<?php
/**
 * ĐẨY NGƯỜI TỪ SỔ NHÂN SỰ SANG HỆ VẬN HÀNH CHI PHÍ.
 *
 * =================================================================================================
 * Anh Thắng 28/08/2026: *"bên quản lý nhân sự chưa cho đẩy nhân sự sang vận hành chi phí"*, rồi
 * *"Đồng bộ nhân sự với hệ thống vận hành chi phí luôn nhé em"*.
 *
 * Bảng "Ai vào được trang nào" đã có bốn cột — Quản trị chấm công · Trạm · Nội bộ · Ghế massage —
 * mà thiếu đúng cột chi phí. Nên bên ấy vẫn phải khai tay từng người ở màn 🔐 Người dùng & Phân
 * quyền: gõ lại tên, gõ lại PIN, chọn lại cơ sở. Hai sổ chép tay là hai sổ lệch nhau.
 *
 * =================================================================================================
 * 🔴 KHÁC HỆ GHẾ Ở MỘT ĐIỂM CỐT TỬ: SỔ BÊN CHI PHÍ CÓ NGƯỜI THẬT ĐANG DÙNG.
 * =================================================================================================
 * Hệ ghế có một sổ riêng do chính mình dựng ra. Còn `CH_NguoiDung` bên chi phí là sổ ĐANG CHẠY —
 * ảnh anh gửi có Admin, hai Kế toán, Quản lý, và hai chục nhân viên cơ sở, mỗi người một PIN mà
 * họ đang gõ hằng ngày. Nên:
 *
 * ⚠️ CHỈ THÊM VÀ SỬA HÀNG CỦA NGƯỜI ĐƯỢC ĐẨY. Không đụng một hàng nào khác, không sắp xếp lại,
 *    không dọn "cho gọn". Ghi đè cả sổ là xoá sạch PIN của những người bên ấy tự khai — và họ
 *    chỉ phát hiện ra lúc đứng gõ PIN vào sáng hôm sau.
 *
 * ⚠️ GIỮ NGUYÊN NHỮNG CỘT BÊN ẤY TỰ KHAI: TK Có · Mã đối tượng · Đơn vị · Xem đơn vị. Đó là
 *    thông tin KẾ TOÁN, sổ nhân sự không biết và không được đoán. Đẩy mà xoá chúng là kế toán
 *    mất bảng khai của mình mà không ai báo.
 *
 * ⚠️ KHOÁ THEO MÃ NV, KHÔNG THEO TÊN. Sổ bên chi phí khoá theo tên, nhưng 400 nhân sự thì trùng
 *    tên là chuyện có thật (bảng nhân sự đang có 14 hồ sơ trùng tên). Nên giữ mã NV ở một sổ
 *    riêng bên này — đó là sợi dây duy nhất nối một hàng bên ấy về đúng một người.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_DayChiPhi {

	/** Tên cột trên bảng "Ai vào được trang nào". */
	const COT = 'chi_phi';

	/** Sổ ghi mã NV của những người đã đẩy: [ maNV => tên đã ghi sang ]. */
	const O_DA_DAY = 'vhcc_day_chi_phi';

	/**
	 * 🔴 BẬC ADMIN, cùng bậc với đẩy sang hệ ghế và vì cùng một lý do: màn chi phí có ngăn TIỀN
	 *    (duyệt chi, quyết toán), và PIN đẩy sang là PIN chấm công dùng chung. Đẩy nhầm một
	 *    người là mở cửa buồng tiền cho họ.
	 */
	const QUYEN = 'he_thong';

	/** Vị trí các cột trong `CH_NguoiDung` — xem `VHCP_Cfg::headers()`. */
	const C_TEN = 0;
	const C_PIN = 1;
	const C_VAI = 2;
	const C_COSO = 3;
	const C_BO_PHAN = 6;

	/* ====================================================================== hỏi trạng thái */

	/**
	 * Có plugin chi phí trên site này không, và nó có đủ hàm để ghi không.
	 *
	 * ⚠️ Dò TỪNG HÀM, không dò mỗi tên lớp: bốn plugin cài độc lập nên bản có thể lệch nhau, và
	 *    gọi một hàm không tồn tại là Fatal — trắng cả trang, không phải một ô hỏng.
	 */
	public static function co_he_chi_phi() {
		return class_exists( 'VHCP_Cfg' )
			&& method_exists( 'VHCP_Cfg', 'read' )
			&& method_exists( 'VHCP_Cfg', 'write' )
			&& defined( 'VHCP_Cfg::USER' );
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
	 * VAI BÊN CHẤM CÔNG -> VAI BÊN CHI PHÍ.
	 *
	 * Đi theo đúng bảng mà chính app chi phí đang dùng cho đăng nhập một lần
	 * (`VHCP_Auth::resolve_sso_user`) — hai đường vào cùng một hệ mà ánh xạ khác nhau thì cùng
	 * một người vào bằng hai cửa lại ra hai quyền.
	 *
	 * ⚠️ CỬA HÀNG TRƯỞNG -> 'Nhân viên', KHÔNG phải 'Quản lý'. Bên chi phí, 'Quản lý' duyệt được
	 *    chi của mọi cơ sở. Cửa hàng trưởng là người ĐỀ NGHỊ chi, không phải người duyệt.
	 */
	public static function vai_chi_phi( $vai_cc ) {
		$ma = class_exists( 'VHCC_Vai' ) && method_exists( 'VHCC_Vai', 'ma' )
			? VHCC_Vai::ma( $vai_cc ) : '';
		$b  = array(
			'ADMIN'   => 'Admin',
			'QUAN_LY' => 'Quản lý',
			'KE_TOAN' => 'Kế toán cá nhân',
		);
		return isset( $b[ $ma ] ) ? $b[ $ma ] : 'Nhân viên';
	}

	/* ====================================================================== dựng hàng */

	/**
	 * Hồ sơ để đẩy: [ ho_ten, pin, vai_cc, coso, bo_phan ]. null nếu không đẩy được.
	 *
	 * ⚠️ PIN Ở `nhan_vien.pin_dang_nhap`, KHÔNG ở `phan_quyen.pin`. Đã tra nhầm một lần
	 *    (28/08/2026, anh Thắng: *"Anh thấy lưu mà bên Posh chưa qua"*) — bảng `phan_quyen` là
	 *    sổ CŨ nạp từ Sheets, còn hồ sơ nhân sự mới là nơi cấp PIN bây giờ.
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
			'ho_ten'  => $ten,
			'pin'     => $pin,
			'vai_cc'  => (string) $hs['vai_tro'],
			'coso'    => VHCC_NhanSu::chuan_coso( (string) $hs['cua_hang'] ),
			'bo_phan' => trim( (string) $hs['chuc_vu'] ),
		);
	}

	/* ====================================================================== đẩy / gỡ */

	public static function dat( $u, $ma_nv, $dat ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false,
				'error' => 'Đẩy người sang hệ Vận hành chi phí cần vai Admin — màn ấy có ngăn tiền.' );
		}
		/* ⚠️ GÁC `method_exists` CÙNG THÂN HÀM với lời gọi — luật của `tools/test/kiem-goi-cheo.php`.
		   `co_he_chi_phi()` đã kiểm y hệt, nhưng gác ở HÀM KHÁC thì người đọc sau (và bộ soi)
		   không thấy được, mà bốn plugin cài độc lập nên bản có thể lệch nhau bất cứ lúc nào. */
		if ( ! class_exists( 'VHCP_Cfg' ) || ! method_exists( 'VHCP_Cfg', 'read' )
			|| ! method_exists( 'VHCP_Cfg', 'write' ) || ! defined( 'VHCP_Cfg::USER' ) ) {
			return array( 'ok' => false,
				'error' => 'Chưa cài plugin Vận hành chi phí trên site này (hoặc bản bên ấy quá cũ).' );
		}
		$ma  = strtoupper( trim( (string) $ma_nv ) );
		$dat = (string) $dat;
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }

		$ds_day = self::da_day_ds();
		$rows   = (array) VHCP_Cfg::read( VHCP_Cfg::USER );

		/* --- GỠ --- */
		if ( 'mo' !== $dat ) {
			if ( ! isset( $ds_day[ $ma ] ) ) { return array( 'ok' => true, 'doi' => 0 ); }
			$ten_cu = (string) $ds_day[ $ma ];
			$moi    = array();
			foreach ( $rows as $r ) {
				$r = (array) $r;
				if ( 0 === strcasecmp( trim( (string) ( isset( $r[ self::C_TEN ] ) ? $r[ self::C_TEN ] : '' ) ), $ten_cu ) ) {
					continue;
				}
				$moi[] = $r;
			}
			VHCP_Cfg::write( VHCP_Cfg::USER, $moi );
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
		/* PIN trùng người khác bên ấy là hai người chung một cửa: cổng đăng nhập tra theo PIN,
		   nên ai gõ vào cũng rơi vào hàng đứng trước. Chối, và chỉ ra ai đang giữ. */
		$ten_cu = isset( $ds_day[ $ma ] ) ? (string) $ds_day[ $ma ] : $hs['ho_ten'];
		foreach ( $rows as $r ) {
			$r = (array) $r;
			$t = trim( (string) ( isset( $r[ self::C_TEN ] ) ? $r[ self::C_TEN ] : '' ) );
			$p = trim( (string) ( isset( $r[ self::C_PIN ] ) ? $r[ self::C_PIN ] : '' ) );
			if ( $p === $hs['pin'] && 0 !== strcasecmp( $t, $ten_cu ) && 0 !== strcasecmp( $t, $hs['ho_ten'] ) ) {
				return array( 'ok' => false, 'error' => 'PIN của người này trùng với "' . $t
					. '" đang có bên Vận hành chi phí — đổi PIN một trong hai rồi đẩy lại.' );
			}
		}

		$thay = false;
		foreach ( $rows as $i => $r ) {
			$r = (array) $r;
			$t = trim( (string) ( isset( $r[ self::C_TEN ] ) ? $r[ self::C_TEN ] : '' ) );
			if ( 0 !== strcasecmp( $t, $ten_cu ) && 0 !== strcasecmp( $t, $hs['ho_ten'] ) ) { continue; }
			/* 🔴 SỬA ĐÚNG BỐN Ô, GIỮ NGUYÊN PHẦN CÒN LẠI. TK Có · Mã đối tượng · Đơn vị · Xem
			   đơn vị là bảng khai của KẾ TOÁN — sổ nhân sự không biết và không được đoán. */
			$r[ self::C_TEN ]     = $hs['ho_ten'];
			$r[ self::C_PIN ]     = $hs['pin'];
			$r[ self::C_VAI ]     = self::vai_chi_phi( $hs['vai_cc'] );
			$r[ self::C_COSO ]    = $hs['coso'];
			if ( '' !== $hs['bo_phan'] ) { $r[ self::C_BO_PHAN ] = $hs['bo_phan']; }
			$rows[ $i ] = $r;
			$thay = true;
			break;
		}
		if ( ! $thay ) {
			$hang = array_fill( 0, 9, '' );
			$hang[ self::C_TEN ]     = $hs['ho_ten'];
			$hang[ self::C_PIN ]     = $hs['pin'];
			$hang[ self::C_VAI ]     = self::vai_chi_phi( $hs['vai_cc'] );
			$hang[ self::C_COSO ]    = $hs['coso'];
			$hang[ self::C_BO_PHAN ] = $hs['bo_phan'];
			$rows[] = $hang;
		}
		VHCP_Cfg::write( VHCP_Cfg::USER, array_values( $rows ) );
		$ds_day[ $ma ] = $hs['ho_ten'];
		update_option( self::O_DA_DAY, $ds_day, false );
		return array( 'ok' => true, 'doi' => 1, 'viec' => $thay ? 'capnhat' : 'them' );
	}

	/**
	 * BẢN SAO BÊN CHI PHÍ PHẢI THEO BẢN GỐC — gọi sau mỗi lần sửa hồ sơ / đổi vai / chuyển cơ sở.
	 *
	 * 🔴 CỐ Ý KHÔNG KIỂM QUYỀN ĐẨY, y như `VHCC_DayGhe::dong_bo()`. Hàm này không MỞ đường cho
	 *    ai: nó chỉ giữ cho bản sao của một người ĐÃ ĐƯỢC ĐẨY khớp với bản gốc. Bắt nó đòi vai
	 *    Admin thì Cửa hàng trưởng đổi PIN cho nhân viên mình xong, bản sao đứng im — cái chốt
	 *    ấy không bảo vệ được gì mà chỉ đẻ ra lệch.
	 *
	 * ⚠️ HỒ SƠ MẤT PIN THÌ GỠ LUÔN. Xoá PIN của một người thường là để chặn họ đăng nhập; giữ
	 *    bản sao mang PIN cũ là để hở đúng cánh cửa vừa định đóng.
	 */
	/**
	 * Đẩy / gỡ một loạt theo bảng vừa gửi lên: [ maNV => 'mo' | '' ].
	 *
	 * ⚠️ BỎ QUA NGƯỜI KHÔNG ĐỔI. Bảng gửi lên có cả trăm hàng mà thường chỉ vài hàng đổi; ghi
	 *    lại cả sổ chi phí cho mỗi hàng là hàng trăm lượt đọc-ghi cho một cú bấm.
	 */
	public static function luu_nhieu( $u, $bang ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'doi' => 0,
				'error' => 'Đẩy người sang hệ Vận hành chi phí cần vai Admin.' );
		}
		$doi = 0;
		$loi = array();
		foreach ( (array) $bang as $ma => $dat ) {
			$ma  = trim( (string) $ma );
			$dat = (string) $dat;
			if ( '' === $ma ) { continue; }
			if ( self::o( $ma ) === ( 'mo' === $dat ? 'mo' : '' ) ) { continue; }
			$kq = self::dat( $u, $ma, $dat );
			if ( empty( $kq['ok'] ) ) { $loi[] = $kq['error']; continue; }
			$doi += (int) ( isset( $kq['doi'] ) ? $kq['doi'] : 0 );
		}
		return array( 'ok' => true, 'doi' => $doi, 'loi' => $loi );
	}

	public static function dong_bo( $ma_nv ) {
		$ma = strtoupper( trim( (string) $ma_nv ) );
		if ( '' === $ma || ! self::da_day( $ma ) || ! self::co_he_chi_phi() ) {
			return array( 'ok' => true, 'doi' => 0 );
		}
		$gia_admin = array( 'name' => 'dong_bo', 'role' => 'Admin' );
		$hs = self::ho_so_day( $ma );
		return self::dat( $gia_admin, $ma, $hs ? 'mo' : '' );
	}
}
