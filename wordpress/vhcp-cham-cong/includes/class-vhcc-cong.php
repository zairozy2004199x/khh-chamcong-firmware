<?php
/**
 * CỔNG — MỘT NƠI DUY NHẤT TRẢ LỜI "NGƯỜI NÀY VÀO ĐƯỢC TRANG NÀO".
 *
 * Anh Thắng 26/08/2026: *"Giờ anh muốn tạo 1 trang Quản lý nhân sự riêng, để cấu hình nhân sự
 * có thể xem những trang nào trong tất cả các trang anh làm"* — *"để điều phối nó dễ hơn"*.
 *
 * =============================================================================================
 * 🔴 VAI LÀ LUẬT, BẢNG NÀY CHỈ GHI NGOẠI LỆ
 * =============================================================================================
 * Cách sai — và là cách trông có vẻ "chủ động" hơn — là bỏ thang vai đi, bắt đầu từ trắng, rồi
 * tích tay 240 người × 8 trang. Ngày bản đó lên là cả công ty mất đường vào, và ở lại như thế
 * cho tới khi tích xong gần hai nghìn ô. Không ai làm xong việc ấy trong một buổi.
 *
 * Nên: thang năm bậc của `VHCC_Vai` vẫn quyết định mặc định, y như đang chạy. Bảng này chỉ giữ
 * những dòng KHÁC mặc định — mở thêm một trang cho một người, hoặc khoá một trang với một
 * người. Khai xong đúng mấy dòng ấy, phần còn lại không ai phải đụng tới.
 *
 * ⚠️ NGOẠI LỆ PHẢI PHÂN BIỆT ĐƯỢC "MỞ", "KHOÁ" và "CHƯA KHAI" — ba trạng thái, không phải hai.
 *    Dùng 1/0 rồi coi "không có khoá" là 0 thì không có cách nào nói "người này theo mặc định";
 *    mà không nói được thế thì gỡ một ngoại lệ đã đặt là không gỡ được nữa.
 *
 * =============================================================================================
 * 🔴 DANH SÁCH TRANG TỰ DÒ, KHÔNG GÕ TAY
 * =============================================================================================
 * Mỗi trang là một plugin riêng, cài độc lập, gỡ độc lập. Gõ tay danh sách thì gỡ một plugin là
 * bảng còn một dòng trỏ vào hư không, mà thêm một plugin thì nó không có mặt — và không có gì
 * báo cả hai chuyện. Dò bằng `class_exists` + `method_exists` nên bảng luôn đúng bằng những gì
 * đang thật sự cài trên site.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Cong {

	/** Nơi giữ ngoại lệ: [ ma_nv => [ khoá trang => 'mo' | 'khoa' ] ]. */
	const O = 'vhcc_quyen_trang';

	/**
	 * SỔ TRANG — khai lớp và hàm, KHÔNG khai địa chỉ cứng.
	 *
	 * `quyen` là quyền tối thiểu theo thang `VHCC_Vai` để vào trang ấy khi CHƯA có ngoại lệ nào.
	 * Đúng bằng luật đang chạy hôm nay — bản nâng cấp này không siết thêm của ai.
	 *
	 * =========================================================================================
	 * 🔴 CHỈ KHAI TRANG CÓ CỬA ĐĂNG NHẬP BẰNG PHIÊN CHẤM CÔNG. BỐN TRANG DƯỚI ĐÂY CỐ Ý VẮNG MẶT.
	 * =========================================================================================
	 * Bản nháp đầu của sổ này có đủ bảy trang, và nó SAI ở mức làm hỏng việc kinh doanh:
	 *
	 *   • `/ghe/` — TRANG CỦA KHÁCH. Khách quét QR trên ghế massage rồi trả tiền; không đăng
	 *     nhập, không có mã nhân viên, không có vai. Gác nó bằng thang vai là mọi khách đều bị
	 *     chối, ghế đứng im, và tiền không vào — mà trên màn hình chỉ hiện một câu "không đủ
	 *     quyền" mà khách không hiểu nổi.
	 *   • `/` (Cổng K&H) — CỬA TRƯỚC, công khai, không có phiên. Nó chỉ là bảng liệt kê các hệ.
	 *     Gác cửa trước là khoá cả nhà, kể cả người có chìa.
	 *   • `/hop-dong/` — giao diện lấy thẳng từ Apps Script và TỰ đăng nhập bên trong. Người vào
	 *     đó không mang cookie chấm công, nên gác ở đây là chối sạch, kể cả Admin.
	 *   • `/chi-phi/` — app Vận hành chi phí có SỔ NGƯỜI DÙNG RIÊNG (`VHCP_Cfg`, vai "Kế toán
	 *     cá nhân" / "Kế toán NCC"). Người bên đó không nhất thiết có Mã NV trong hồ sơ chấm
	 *     công, nên ngoại lệ khai theo Mã NV ở đây không bám vào đâu được. Muốn khai quyền cho
	 *     app chi phí thì phải làm ở chính app ấy — hứa ở đây mà không có hiệu lực thì tệ hơn
	 *     là không hứa.
	 *
	 * ⚠️ THÊM TRANG VÀO SỔ NÀY LÀ MỘT VIỆC CÓ HẬU QUẢ, KHÔNG PHẢI MỘT DÒNG MẢNG. Trang được khai
	 *    phải thoả ĐỦ HAI điều: (1) nó gác cửa bằng phiên chấm công, tức đọc được `ma_nv`;
	 *    (2) nó có gọi `duoc_vao()` ở cửa vào — khai mà không gọi thì màn hình cho tích, tích
	 *    xong không có gì đổi, và người khai tưởng mình đã khoá.
	 */
	const SO = array(
		'cham_cong' => array( 'ten' => 'Quản trị chấm công', 'lop' => 'VHCC_Web',   'quyen' => 'cong_minh' ),
		'tram'      => array( 'ten' => 'Trạm chấm công',     'lop' => 'VHCC_Tram',  'quyen' => 'cham_online' ),
		'noi_bo'    => array( 'ten' => 'Nội bộ',             'lop' => 'VHNB_Trang', 'quyen' => 'cham_online' ),
	);

	/* ====================================================================== sổ trang */

	/**
	 * Những trang ĐANG THẬT SỰ CÓ trên site này.
	 *
	 * ⚠️ Dò cả LỚP lẫn HÀM `url()`. Lớp có mà hàm không là gọi hụt một hàm tĩnh — Fatal error,
	 *    trắng cả trang. Luật của `tools/test/kiem-goi-cheo.php`.
	 */
	public static function ds() {
		$ra = array();
		foreach ( self::SO as $k => $t ) {
			if ( ! class_exists( $t['lop'] ) || ! method_exists( $t['lop'], 'url' ) ) { continue; }
			$ra[ $k ] = array(
				'ten'   => $t['ten'],
				'quyen' => $t['quyen'],
				'url'   => (string) call_user_func( array( $t['lop'], 'url' ) ),
			);
		}
		return $ra;
	}

	/** Trang này có tên trong sổ và đang cài không. */
	public static function co( $trang ) {
		$ds = self::ds();
		return isset( $ds[ (string) $trang ] );
	}

	/* ====================================================================== ngoại lệ */

	/** Toàn bộ bảng ngoại lệ. */
	public static function ngoai_le() {
		$x = get_option( self::O );
		return is_array( $x ) ? $x : array();
	}

	/** Ngoại lệ của một người: [ khoá trang => 'mo' | 'khoa' ]. */
	public static function ngoai_le_cua( $ma_nv ) {
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return array(); }
		$b = self::ngoai_le();
		return isset( $b[ $ma ] ) && is_array( $b[ $ma ] ) ? $b[ $ma ] : array();
	}

	/**
	 * Đặt ngoại lệ cho MỘT người trên MỘT trang.
	 *
	 * @param string $dat 'mo' · 'khoa' · '' (bỏ ngoại lệ, quay về theo vai)
	 *
	 * ⚠️ Bỏ ngoại lệ thì XOÁ HẲN khoá, không để lại ô rỗng. Giữ lại thì bảng cứ phình theo mỗi
	 *    lượt bấm, và "người này có ngoại lệ gì không" thành câu không trả lời được bằng mắt.
	 */
	public static function dat( $u, $ma_nv, $trang, $dat ) {
		if ( ! VHCC_Vai::duoc( $u, 'ho_so' ) ) {
			return array( 'ok' => false, 'error' => 'Khai quyền vào trang cần vai Kế toán trở lên.' );
		}
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		if ( ! self::co( $trang ) ) {
			return array( 'ok' => false, 'error' => 'Không có trang "' . $trang . '" trên site này.' );
		}
		$dat = (string) $dat;
		if ( ! in_array( $dat, array( 'mo', 'khoa', '' ), true ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ nhận: mo · khoa · (trống).' );
		}
		$b = self::ngoai_le();
		if ( '' === $dat ) {
			unset( $b[ $ma ][ $trang ] );
			if ( empty( $b[ $ma ] ) ) { unset( $b[ $ma ] ); }
		} else {
			if ( ! isset( $b[ $ma ] ) || ! is_array( $b[ $ma ] ) ) { $b[ $ma ] = array(); }
			$b[ $ma ][ $trang ] = $dat;
		}
		update_option( self::O, $b );
		return array( 'ok' => true );
	}

	/* ====================================================================== hỏi quyền */

	/**
	 * NGƯỜI NÀY VÀO ĐƯỢC TRANG NÀY KHÔNG.
	 *
	 * Thứ tự: ngoại lệ của chính người ấy TRƯỚC, rồi mới tới mặc định theo vai.
	 *
	 * ⚠️ TRANG KHÔNG CÓ TRONG SỔ THÌ CHO QUA. Sổ này là để SIẾT có chủ ý, không phải để trở
	 *    thành một cánh cửa mới mà mọi trang chưa kịp khai đều bị chặn — thêm một plugin mà
	 *    quên khai vào `SO` thì cả trang ấy đóng với tất cả mọi người, và không ai đoán ra.
	 */
	public static function duoc_vao( $u, $trang ) {
		$trang = (string) $trang;
		$ds    = self::ds();
		if ( ! isset( $ds[ $trang ] ) ) { return true; }

		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' !== $ma ) {
			$ng = self::ngoai_le_cua( $ma );
			if ( isset( $ng[ $trang ] ) ) {
				if ( 'mo' === $ng[ $trang ] )   { return true; }
				if ( 'khoa' === $ng[ $trang ] ) { return false; }
			}
		}
		return VHCC_Vai::duoc( $u, $ds[ $trang ]['quyen'] );
	}

	/** Vì sao không vào được — '' là được phép. Nói ra ngoại lệ hay vai, để còn biết xin ai. */
	public static function vi_sao_khong( $u, $trang ) {
		if ( self::duoc_vao( $u, $trang ) ) { return ''; }
		$ds  = self::ds();
		$ten = isset( $ds[ $trang ]['ten'] ) ? $ds[ $trang ]['ten'] : $trang;
		$ma  = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		$ng  = self::ngoai_le_cua( $ma );
		if ( isset( $ng[ $trang ] ) && 'khoa' === $ng[ $trang ] ) {
			return 'Trang "' . $ten . '" đã bị khoá riêng với tài khoản này ở màn Quản lý nhân sự.';
		}
		return 'Trang "' . $ten . '" chưa mở cho vai hiện tại của anh/chị. '
			. 'Cần vào thì nhờ Kế toán mở ở màn Quản lý nhân sự.';
	}

	/**
	 * Những trang người này vào được — dùng để vẽ thanh điều hướng dùng chung.
	 * Trả [ khoá => [ten, url] ].
	 */
	public static function ds_cua( $u ) {
		$ra = array();
		foreach ( self::ds() as $k => $t ) {
			if ( self::duoc_vao( $u, $k ) ) { $ra[ $k ] = $t; }
		}
		return $ra;
	}

	/**
	 * LƯU CẢ MỘT BẢNG NGOẠI LỆ TRONG MỘT LƯỢT.
	 *
	 * @param array $bang [ ma_nv => [ khoá trang => 'mo'|'khoa'|'' ] ] — CHỈ những người đang
	 *                    hiện trên màn. Người không có tên trong `$bang` thì KHÔNG đụng tới.
	 *
	 * 🔴 CHỈ ĐỘNG VÀO NGƯỜI CÓ TRONG `$bang`. Màn hình có phân trang và có bộ lọc, nên cái gửi
	 *    lên chỉ là một lát cắt. Viết kiểu "ghi đè cả sổ bằng cái vừa nhận" thì lưu một trang
	 *    50 người là XOÁ SẠCH ngoại lệ của 190 người còn lại — im lặng, và chỉ lộ ra khi có
	 *    người kêu "sao tôi lại vào được trang đó".
	 *
	 * ⚠️ Một lượt `update_option`, không phải mỗi ô một lượt. Bảng 50 người × 7 trang là 350 ô;
	 *    gọi `dat()` từng ô là 350 lượt đọc-ghi vào cùng một ô option.
	 */
	public static function luu_nhieu( $u, $bang ) {
		if ( ! VHCC_Vai::duoc( $u, 'ho_so' ) ) {
			return array( 'ok' => false, 'error' => 'Khai quyền vào trang cần vai Kế toán trở lên.', 'doi' => 0 );
		}
		$ds  = self::ds();
		$b   = self::ngoai_le();
		$doi = 0;
		foreach ( (array) $bang as $ma => $cac_o ) {
			$ma = trim( (string) $ma );
			if ( '' === $ma || ! is_array( $cac_o ) ) { continue; }
			foreach ( $cac_o as $trang => $dat ) {
				$trang = (string) $trang;
				if ( ! isset( $ds[ $trang ] ) ) { continue; }
				$dat = (string) $dat;
				if ( ! in_array( $dat, array( 'mo', 'khoa', '' ), true ) ) { continue; }
				$cu = isset( $b[ $ma ][ $trang ] ) ? (string) $b[ $ma ][ $trang ] : '';
				if ( $cu === $dat ) { continue; }
				if ( '' === $dat ) {
					unset( $b[ $ma ][ $trang ] );
					if ( empty( $b[ $ma ] ) ) { unset( $b[ $ma ] ); }
				} else {
					if ( ! isset( $b[ $ma ] ) || ! is_array( $b[ $ma ] ) ) { $b[ $ma ] = array(); }
					$b[ $ma ][ $trang ] = $dat;
				}
				$doi++;
			}
		}
		if ( $doi ) { update_option( self::O, $b ); }
		return array( 'ok' => true, 'doi' => $doi );
	}

	/**
	 * Mọi ngoại lệ đang có, dàn phẳng: [ [ma_nv, trang, ten, dat], … ].
	 * Dùng cho khối soát lại — bảng chính có phân trang nên một ngoại lệ đặt nhầm ở trang 4 thì
	 * không ai nhìn thấy nữa.
	 *
	 * ⚠️ GIỮ CẢ dòng trỏ vào trang KHÔNG CÒN CÀI (gỡ plugin, đổi tên lớp) và đánh dấu `co=false`.
	 *    Lọc chúng đi thì sổ trông sạch trong khi vẫn còn rác — mà rác ấy sống lại đúng ngày
	 *    plugin được cài lại.
	 */
	public static function ngoai_le_phang() {
		$ds = self::ds();
		$ra = array();
		foreach ( self::ngoai_le() as $ma => $cac_o ) {
			if ( ! is_array( $cac_o ) ) { continue; }
			foreach ( $cac_o as $trang => $dat ) {
				$ra[] = array(
					'ma_nv' => (string) $ma,
					'trang' => (string) $trang,
					'ten'   => isset( $ds[ $trang ]['ten'] ) ? $ds[ $trang ]['ten'] : (string) $trang,
					'dat'   => (string) $dat,
					'co'    => isset( $ds[ $trang ] ),
				);
			}
		}
		return $ra;
	}

	/** Trạng thái hiện tại của một ô trong bảng: 'mo' · 'khoa' · '' (theo vai). */
	public static function o( $ma_nv, $trang ) {
		$ng = self::ngoai_le_cua( $ma_nv );
		return isset( $ng[ $trang ] ) ? (string) $ng[ $trang ] : '';
	}
}
