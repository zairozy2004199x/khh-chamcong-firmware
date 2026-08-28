<?php
/**
 * ĐẨY NGƯỜI TỪ SỔ NHÂN SỰ SANG HỆ GHẾ MASSAGE.
 *
 * =============================================================================================
 * VÌ SAO KHÔNG PHẢI LÀ MỘT DÒNG TRONG `VHCC_Cong::SO`
 * =============================================================================================
 * Anh Thắng 28/08/2026: *"anh muốn đẩy nhân sự liên kết hệ thống ghế"*, rồi chốt cách làm:
 * *"để tránh mở vai trò thì mình làm từng hệ phân quyền mở / khóa như này"* — tức là một cột
 * thứ tư cạnh Quản trị chấm công · Trạm chấm công · Nội bộ.
 *
 * Trông giống ba cột kia, nhưng BÊN DƯỚI LÀ MỘT CƠ CHẾ KHÁC HẲN, và trộn hai thứ vào một sổ
 * là hỏng ngay:
 *
 *   • Ba trang kia gác cửa bằng CHÍNH phiên chấm công. Chúng đọc được `ma_nv`, nên "ngoại lệ
 *     theo Mã NV" bám thẳng vào cửa vào của chúng — `VHCC_Cong::duoc_vao()` là một câu hỏi.
 *   • `/ghe` có PHIÊN RIÊNG, đăng nhập bằng PIN, và cố ý như vậy: đó là màn có doanh thu, khả
 *     năng phải đá một người ra ngay lập tức là có thật mà không được kéo theo app kia (xem
 *     đầu `VHG_Auth`). Nó KHÔNG đọc `ma_nv`, nên khai ngoại lệ ở `VHCC_Cong` là khai vào chỗ
 *     không ai hỏi tới: màn hình cho tích, tích xong không có gì đổi.
 *
 * 🔴 NÊN "MỞ" Ở ĐÂY KHÔNG PHẢI LÀ GHI MỘT NGOẠI LỆ. Nó là ĐẨY NGƯỜI THẬT sang sổ người dùng
 *    của hệ ghế — họ tên, PIN, vai trò, cơ sở — để bên ấy có người mà nhận diện. "Khoá" là gỡ
 *    người ấy khỏi sổ bên kia. Đó mới đúng chữ *liên kết* anh Thắng dùng.
 *
 * ⚠️ PIN DÙNG LẠI PIN CHẤM CÔNG — anh Thắng chọn, 28/08/2026. Một người một PIN cho cả ba hệ,
 *    khỏi phải nhớ hai số. Cái giá đã nói trước và anh nhận: ai biết PIN chấm công của người
 *    khác thì vào được cả màn có tiền. Vì vậy quyền ĐẨY ở đây đặt cao hơn quyền khai ba cột
 *    kia — xem `QUYEN` bên dưới.
 *
 * ⚠️ ĐẨY VÀO SỔ RIÊNG CỦA GHẾ (`vhg_nguoidung`), KHÔNG vào sổ chung với app chi phí
 *    (`CH_NguoiDung`) — anh Thắng chọn. Đẩy vào sổ chung thì người được đẩy sang vào luôn được
 *    app Vận hành chi phí, mà đó không phải điều ai yêu cầu.
 *
 * 🔴 KHÔNG BAO GIỜ IN PIN RA MÀN HÌNH. Trang quản trị chạy ngoài internet và ảnh chụp màn hình
 *    đi khắp nơi. Lớp này ĐỌC pin để chép sang, và chỉ nói CÓ hay KHÔNG.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_DayGhe {

	/** Khoá cột trên bảng người × trang. Cố ý khác mọi khoá trong `VHCC_Cong::SO`. */
	const COT = 'ghe';

	/** Sổ người dùng riêng của plugin ghế. Tên option do chính `VHG_Auth::users()` đọc. */
	const O_SO   = 'vhg_nguoidung';
	/** Cờ chọn nguồn của plugin ghế: 'rieng' | 'chung'. */
	const O_NGUON = 'vhg_nguon_nguoidung';

	/**
	 * Quyền được đẩy người sang hệ ghế.
	 *
	 * 🔴 CAO HƠN `ho_so` (quyền khai ba cột kia) MỘT BẬC, và có lý do: ba cột kia mở đường vào
	 *    mấy màn xem giờ công; cột này mở đường vào màn có NGĂN TIỀN — chốt ca, thu tiền, xem
	 *    doanh thu cả chuỗi. Và vì PIN dùng chung, đẩy nhầm một người là trao cho họ chìa khoá
	 *    mà chính họ cũng không biết mình đang cầm.
	 */
	const QUYEN = 'he_thong';

	/** Hệ ghế có mặt trên site này không. Không có thì cột kia đừng vẽ ra làm gì. */
	public static function co_he_ghe() {
		return class_exists( 'VHG_Auth' ) && method_exists( 'VHG_Auth', 'users' );
	}

	/**
	 * Sổ riêng của ghế, đã rửa: [ [ten,pin,vaiTro,coso,maNV], … ].
	 *
	 * ⚠️ `maNV` là cột CỦA RIÊNG SỔ NÀY, `VHG_Auth` không đọc tới. Nó là sợi dây duy nhất nối
	 *    một hàng bên ấy về đúng một người bên sổ nhân sự — thiếu nó thì "gỡ người này ra" phải
	 *    dò theo họ tên, mà hai người trùng tên là chuyện có thật trong 400 nhân sự.
	 */
	public static function so() {
		$x = get_option( self::O_SO );
		return is_array( $x ) ? array_values( array_filter( $x, 'is_array' ) ) : array();
	}

	/** Người mang mã NV này đã được đẩy sang chưa. */
	public static function da_day( $ma_nv ) {
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return false; }
		foreach ( self::so() as $u ) {
			if ( isset( $u['maNV'] ) && 0 === strcasecmp( trim( (string) $u['maNV'] ), $ma ) ) {
				return true;
			}
		}
		return false;
	}

	/** Ô trên bảng: 'mo' nếu đã đẩy, '' nếu chưa. Không có trạng thái 'khoa' lưu lại. */
	public static function o( $ma_nv ) {
		return self::da_day( $ma_nv ) ? 'mo' : '';
	}

	/**
	 * NGUỒN NGƯỜI DÙNG CỦA HỆ GHẾ ĐANG TRỎ ĐI ĐÂU.
	 *
	 * 🔴 ĐẨY VÀO SỔ RIÊNG TRONG KHI HỆ GHẾ ĐANG ĐỌC SỔ CHUNG = ĐẨY VÀO HƯ KHÔNG. Màn hình báo
	 *    "đã đẩy 12 người", 12 người ấy gõ PIN và không ai vào được, mà không có một dòng nào
	 *    nói vì sao. Nên trạng thái này phải hiện ra trước khi anh bấm, không phải sau.
	 */
	public static function nguon() {
		return 'rieng' === get_option( self::O_NGUON ) ? 'rieng' : 'chung';
	}

	public static function nguon_dung() {
		return 'rieng' === self::nguon();
	}

	/**
	 * CHUYỂN HỆ GHẾ SANG DÙNG SỔ RIÊNG — có chép người đang đăng nhập được sang trước.
	 *
	 * 🔴 CHÉP TRƯỚC, ĐỔI SAU, KHÔNG ĐƯỢC NGƯỢC LẠI. Hệ ghế đang đọc sổ chung với app chi phí và
	 *    trong đó có người đang dùng thật. Đổi cờ trước khi chép là sổ riêng còn rỗng, và ngay
	 *    khoảnh khắc ấy KHÔNG AI vào được `/ghe` — kể cả người vừa bấm nút. Tự khoá mình ra
	 *    ngoài một màn có doanh thu, giữa giờ làm.
	 *
	 * ⚠️ Chép cả những người KHÔNG có trong sổ nhân sự chấm công (`maNV` rỗng). Họ là người
	 *    thật đang đăng nhập; bỏ lại vì "không tra được mã" là đá họ ra khỏi hệ.
	 */
	public static function chuyen_sang_rieng( $u ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => 'Đổi nguồn người dùng của hệ ghế cần vai Admin.' );
		}
		if ( ! self::co_he_ghe() ) {
			return array( 'ok' => false, 'error' => 'Chưa cài plugin Ghế massage trên site này.' );
		}
		if ( self::nguon_dung() ) {
			return array( 'ok' => true, 'chep' => 0, 'note' => 'Hệ ghế vốn đã dùng sổ riêng.' );
		}

		/* ⚠️ GÁC `method_exists` CÙNG HÀM VỚI LỜI GỌI — luật của `tools/test/kiem-goi-cheo.php`,
		   cho mọi lời gọi sang plugin KHÁC. `co_he_ghe()` ở trên có kiểm, nhưng đó là hàm khác;
		   gác ở đó rồi gọi ở đây thì ngày ai tách hai hàm ra là mất chốt mà không ai thấy. */
		$dang = ( class_exists( 'VHG_Auth' ) && method_exists( 'VHG_Auth', 'users' ) )
			? VHG_Auth::users() : array();
		if ( is_wp_error( $dang ) || ! is_array( $dang ) ) { $dang = array(); }
		$so  = self::so();
		$co  = array();
		foreach ( $so as $x ) { $co[] = strtolower( trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) ) ); }

		$chep = 0;
		foreach ( (array) $dang as $x ) {
			$ten = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			if ( '' === $ten || in_array( strtolower( $ten ), $co, true ) ) { continue; }
			$so[] = array(
				'ten'    => $ten,
				'pin'    => (string) ( isset( $x['pin'] ) ? $x['pin'] : '' ),
				'vaiTro' => (string) ( isset( $x['vaiTro'] ) ? $x['vaiTro'] : '' ),
				'coso'   => (string) ( isset( $x['coso'] ) ? $x['coso'] : '' ),
				'maNV'   => '',
			);
			$co[] = strtolower( $ten );
			$chep++;
		}
		update_option( self::O_SO, $so, false );
		update_option( self::O_NGUON, 'rieng', false );
		return array( 'ok' => true, 'chep' => $chep );
	}

	/**
	 * VAI TRÒ BÊN CHẤM CÔNG -> VAI TRÒ BÊN GHẾ.
	 *
	 * ⚠️ HAI HỆ CÓ HAI THANG VAI KHÁC NHAU, VÀ ĐÓ LÀ CỐ Ý. Bên ghế còn có 'Hotline' (người bật
	 *    ghế hộ khách qua điện thoại) — không có đối ứng bên chấm công, nên không bao giờ đẩy
	 *    ra vai đó; ai cần Hotline thì khai tay bên ấy.
	 *
	 * 🔴 KHÔNG ĐOÁN BỪA KHI KHÔNG KHỚP. Tên vai lạ thì rơi về 'Nhân viên' — vai HẸP NHẤT bên
	 *    ghế (chỉ thấy tab Quỹ, không xem được doanh thu người khác). Đoán rộng ra là trao
	 *    quyền xem tiền cả chuỗi cho một người chỉ vì gõ sai một chữ trong ô vai trò.
	 */
	public static function vai_ghe( $vai_cc ) {
		/* ⚠️ HỎI `VHCC_Vai::ma()`, ĐỪNG SO CHUỖI TÊN VAI. Anh Thắng tự tạo được vai mới ("Kỹ
		   thuật", "Điều phối POSH"…) và mỗi vai ấy gốc ở một bậc; so tên thì vai tự tạo nào
		   cũng trượt xuống nhánh cuối. `ma()` đã tra bảng vai tự tạo TRƯỚC rồi mới đoán theo
		   tên, nên nó là chỗ duy nhất biết đủ. */
		$bac = VHCC_Vai::ma( trim( (string) $vai_cc ) );
		$bang = array(
			VHCC_Vai::ADMIN   => 'Admin',
			VHCC_Vai::QL      => 'Quản lý',
			VHCC_Vai::KE_TOAN => 'Kế toán cá nhân',
			VHCC_Vai::CHT     => 'Cửa hàng trưởng',
			VHCC_Vai::NV      => 'Nhân viên',
		);
		return isset( $bang[ $bac ] ) ? $bang[ $bac ] : 'Nhân viên';
	}

	/**
	 * Hồ sơ cần để đẩy một người: [ho_ten, pin, vai_cc, coso] — hoặc null nếu không đủ.
	 *
	 * ⚠️ PIN NẰM Ở BẢNG `phan_quyen`, KHÔNG Ở `nhan_vien`. Người có hồ sơ nhân sự mà chưa được
	 *    cấp PIN chấm công thì chưa đẩy sang được — và phải NÓI RA đúng lý do ấy, chứ không
	 *    lặng lẽ bỏ qua: người đi khai sẽ tưởng mình bấm hụt.
	 */
	public static function ho_so_day( $ma_nv ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT pin, ho_ten, vai_tro, cua_hang, coso_cc_online FROM '
			. VHCC_DB::t( 'phan_quyen' ) . ' WHERE ma_cc_online=%s LIMIT 1', $ma ), ARRAY_A );
		if ( ! $r || '' === trim( (string) $r['pin'] ) ) { return null; }

		$hs   = VHCC_NhanSu::ho_so( $ma );
		$ten  = trim( (string) $r['ho_ten'] );
		if ( '' === $ten && $hs ) { $ten = trim( (string) $hs['ho_ten'] ); }
		$coso = trim( (string) $r['coso_cc_online'] );
		if ( '' === $coso ) { $coso = trim( (string) $r['cua_hang'] ); }
		if ( '' === $coso && $hs ) { $coso = trim( (string) $hs['cua_hang'] ); }
		$vai  = trim( (string) $r['vai_tro'] );
		if ( '' === $vai && $hs ) { $vai = trim( (string) $hs['vai_tro'] ); }

		if ( '' === $ten ) { return null; }
		return array( 'ho_ten' => $ten, 'pin' => (string) $r['pin'], 'vai_cc' => $vai, 'coso' => $coso );
	}

	/**
	 * Đẩy sang / gỡ khỏi hệ ghế. `$dat`: 'mo' = đẩy, '' hoặc 'khoa' = gỡ.
	 *
	 * ⚠️ ĐẨY LẠI MỘT NGƯỜI ĐÃ CÓ = CẬP NHẬT, KHÔNG ĐẺ HÀNG THỨ HAI. Đổi vai hay chuyển cơ sở
	 *    bên chấm công rồi đẩy lại là chuyện thường; đẻ thêm hàng thì bên ghế có hai người
	 *    trùng tên, và cái nào thắng lúc đăng nhập thì tuỳ thứ tự đọc.
	 */
	public static function dat( $u, $ma_nv, $dat ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => 'Đẩy người sang hệ ghế cần vai Admin.' );
		}
		if ( ! self::co_he_ghe() ) {
			return array( 'ok' => false, 'error' => 'Chưa cài plugin Ghế massage trên site này.' );
		}
		$ma  = trim( (string) $ma_nv );
		$dat = (string) $dat;
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }

		$so  = self::so();
		$moi = array();
		$thay = false;
		foreach ( $so as $x ) {
			$cua = isset( $x['maNV'] ) ? trim( (string) $x['maNV'] ) : '';
			if ( '' !== $cua && 0 === strcasecmp( $cua, $ma ) ) { $thay = true; continue; }
			$moi[] = $x;
		}

		if ( 'mo' !== $dat ) {
			if ( ! $thay ) { return array( 'ok' => true, 'doi' => 0 ); }
			update_option( self::O_SO, $moi, false );
			return array( 'ok' => true, 'doi' => 1, 'viec' => 'go' );
		}

		$hs = self::ho_so_day( $ma );
		if ( ! $hs ) {
			return array( 'ok' => false, 'error' => 'Người mang mã ' . $ma . ' chưa có PIN chấm công — '
				. 'cấp PIN cho họ ở màn Hồ sơ & tài khoản rồi đẩy lại.' );
		}
		$moi[] = array(
			'ten'    => $hs['ho_ten'],
			'pin'    => $hs['pin'],
			'vaiTro' => self::vai_ghe( $hs['vai_cc'] ),
			'coso'   => $hs['coso'],
			'maNV'   => $ma,
		);
		update_option( self::O_SO, $moi, false );
		return array( 'ok' => true, 'doi' => 1, 'viec' => $thay ? 'capnhat' : 'them' );
	}

	/**
	 * Lưu cả cột cho nhiều người một lượt — bắt chước `VHCC_Cong::luu_nhieu()`.
	 *
	 * ⚠️ GOM LỖI LẠI RỒI TRẢ HẾT, đừng dừng ở người đầu tiên thiếu PIN. Anh Thắng tích 20 người
	 *    rồi bấm Lưu; dừng giữa chừng là 19 người kia im lặng không đi đâu cả, mà màn hình chỉ
	 *    kể một cái tên.
	 */
	public static function luu_nhieu( $u, $bang ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => 'Đẩy người sang hệ ghế cần vai Admin.', 'doi' => 0 );
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
}
