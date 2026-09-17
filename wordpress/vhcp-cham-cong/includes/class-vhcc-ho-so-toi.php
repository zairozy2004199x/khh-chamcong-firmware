<?php
/**
 * HỒ SƠ CỦA CHÍNH MÌNH — nhân viên tự xem và tự bổ sung mấy ô còn thiếu.
 *
 * Anh Thắng 17/09/2026: *"hiện thông tin nhân sự, nếu thiếu gì nhân sự tự bổ sung cho mình,
 * như căn cước, số điện thoại, địa chỉ, mã pin"*.
 *
 * =============================================================================================
 * 🔴 DANH SÁCH TRẮNG, KHÔNG PHẢI DANH SÁCH ĐEN
 * =============================================================================================
 * Bảng `nhan_vien` có hơn ba chục cột, trong đó có `luong_co_ban`, `chuc_vu`, `cua_hang`,
 * `vai_tro`, `trang_thai_lam_viec`. Cách viết tự nhiên là nhận cả `$_POST` rồi loại vài cột
 * nhạy cảm ra — và đó là cách sai: thêm một cột mới vào bảng là nó tự động lọt vào diện sửa
 * được, mà không ai nhớ quay lại đây bổ sung vào danh sách loại trừ.
 *
 * Nên chỉ có SUA_DUOC bên dưới mới ghi được. Cột nào không nằm trong đó thì dù giao diện có
 * gửi lên cũng bị bỏ qua — im lặng, vì đó là chuyện của máy chủ chứ không phải lỗi người dùng.
 *
 * =============================================================================================
 * 🔴 SỐ TÀI KHOẢN NGÂN HÀNG CỐ Ý KHÔNG CHO TỰ SỬA
 * =============================================================================================
 * `so_tai_khoan` và `ngan_hang` là nơi lương chạy vào. Cho tự đổi nghĩa là ai cầm được điện
 * thoại đang mở app — hoặc đoán trúng một mã PIN 4–8 số — đều đổi được đích đến của lương
 * tháng ấy, và không ai biết cho tới kỳ trả lương.
 *
 * Mấy ô còn lại thì sai cũng chỉ là sai dữ liệu, sửa lại được. Ô này sai là mất tiền.
 *
 * Muốn mở thì phải kèm một đường DUYỆT (nhân viên đề nghị → quản lý xác nhận), không phải bỏ
 * cột này vào mảng dưới đây.
 *
 * =============================================================================================
 * ⚠️ HỌ TÊN, MÃ NV, CƠ SỞ, CHỨC VỤ CŨNG KHÔNG
 * =============================================================================================
 * Không phải vì nhạy cảm, mà vì chúng là KHOÁ NỐI sang bốn hệ khác: mã NV nối bảng chấm công,
 * cơ sở quyết định chấm được ở đâu, họ tên đã chép sang sổ người dùng của hệ ghế / chi phí /
 * báo cáo. Sửa một chỗ ở đây là bốn chỗ kia lệch mà không có gì báo.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_HoSoToi {

	/**
	 * Cột nhân viên tự sửa được: [ cột => nhãn hiện trên màn hình ].
	 *
	 * Thứ tự ở đây CHÍNH LÀ thứ tự hiện trên màn hình — xếp theo mức hay thiếu, không theo
	 * thứ tự cột trong bảng.
	 */
	const SUA_DUOC = array(
		'sdt'                 => 'Số điện thoại',
		'cccd'                => 'Số căn cước',
		'dia_chi'             => 'Địa chỉ',
		'ngay_sinh'           => 'Ngày sinh',
		'nguoi_lien_he_khan'  => 'Người báo khi khẩn cấp',
		'sdt_khan'            => 'Điện thoại người ấy',
	);

	/** Cột chỉ ĐỌC — hiện cho người ta biết, nhưng muốn sửa thì báo quản lý. */
	const CHI_DOC = array(
		'ho_ten'       => 'Họ tên',
		'ma_nv'        => 'Mã nhân viên',
		'chuc_vu'      => 'Chức vụ',
		'cua_hang'     => 'Cơ sở',
		'ngay_vao_lam' => 'Ngày vào làm',
	);

	// ==================================================================== đọc

	/**
	 * Hồ sơ của người đang đăng nhập.
	 *
	 * ⚠️ CHỌN ĐÍCH DANH TỪNG CỘT, không `SELECT *`. `SELECT *` thì thêm cột `luong_co_ban` vào
	 *    bảng là lương chạy thẳng xuống điện thoại của mọi nhân viên, chỉ vì không ai nhớ rằng
	 *    có một câu truy vấn ở đây cũng đang đọc cả bảng.
	 */
	public static function doc( $ma_nv ) {
		global $wpdb;

		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu mã nhân viên.' ); }

		$cot = array_merge( array_keys( self::CHI_DOC ), array_keys( self::SUA_DUOC ) );
		$sql = 'SELECT `' . implode( '`, `', $cot ) . '` FROM ' . VHCC_DB::t( 'nhan_vien' )
			. ' WHERE ma_nv = %s LIMIT 1';

		$r = $wpdb->get_row( $wpdb->prepare( $sql, $ma ), ARRAY_A );
		if ( ! $r ) {
			return array( 'ok' => false, 'error' => 'Chưa có hồ sơ nhân sự cho mã ' . $ma
				. '. Nhờ quản lý lập hồ sơ giúp.' );
		}

		$thieu = array();
		foreach ( self::SUA_DUOC as $c => $nhan ) {
			if ( '' === trim( (string) ( isset( $r[ $c ] ) ? $r[ $c ] : '' ) )
				|| '0000-00-00' === $r[ $c ] ) {
				$thieu[] = $c;
			}
		}

		return array(
			'ok'       => true,
			'hs'       => $r,
			'sua_duoc' => self::SUA_DUOC,
			'chi_doc'  => self::CHI_DOC,
			/* Đếm sẵn ở máy chủ thay vì để giao diện tự dò: hai chỗ đếm là hai cách hiểu
			   "thiếu" (chuỗi rỗng? '0000-00-00'? khoảng trắng?), và chúng sẽ lệch nhau. */
			'thieu'    => $thieu,
		);
	}

	// ==================================================================== ghi

	/**
	 * Lưu mấy ô người ta tự sửa. `$vao` là mảng [ cột => giá trị ] do giao diện gửi lên.
	 */
	public static function luu( $u, $vao ) {
		global $wpdb;

		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu mã nhân viên.' ); }
		if ( ! is_array( $vao ) ) { return array( 'ok' => false, 'error' => 'Dữ liệu không hợp lệ.' ); }

		$dat = array();
		$loi = array();

		foreach ( self::SUA_DUOC as $c => $nhan ) {
			if ( ! array_key_exists( $c, $vao ) ) { continue; }   // không gửi = không đụng tới
			$v = is_scalar( $vao[ $c ] ) ? trim( (string) $vao[ $c ] ) : '';

			$e = self::kiem( $c, $v );
			if ( '' !== $e ) { $loi[] = $nhan . ': ' . $e; continue; }

			$dat[ $c ] = $v;
		}

		if ( $loi ) { return array( 'ok' => false, 'error' => implode( ' · ', $loi ) ); }
		if ( ! $dat ) { return array( 'ok' => true, 'so' => 0, 'message' => 'Không có gì đổi.' ); }

		$dat['cap_nhat'] = current_time( 'mysql' );

		$n = $wpdb->update( VHCC_DB::t( 'nhan_vien' ), $dat, array( 'ma_nv' => $ma ) );
		if ( false === $n ) {
			return array( 'ok' => false, 'error' => 'Không ghi được vào cơ sở dữ liệu.' );
		}

		/**
		 * Để lại vết, y như khi quản lý sửa hồ sơ.
		 *
		 * ⚠️ GHI THẲNG VÀO BẢNG, KHÔNG GỌI `VHCC_NhanSu`. Bản đầu của tệp này viết
		 *    `method_exists( 'VHCC_NhanSu', 'ghi_nhat_ky' )` rồi gọi — mà hàm ấy KHÔNG TỒN
		 *    TẠI (tên thật là `ghi_nhat_ky_bp`, và nó `private`). Phép gác `method_exists`
		 *    làm đúng việc của nó: không cho nổ. Nhưng nó cũng nuốt luôn cả nhật ký, im
		 *    lặng, mãi mãi — và ai đó ba tháng sau đi tìm "ai đổi số căn cước này" sẽ
		 *    không thấy gì, rồi kết luận là hệ thống không ghi.
		 *
		 *    Bài học: `method_exists` chống Fatal, KHÔNG thay được việc kiểm hàm ấy có thật.
		 *
		 * ⚠️ `cu`/`moi` chỉ ghi TÊN CỘT, không ghi giá trị: số căn cước và địa chỉ nhà nằm
		 *    trong một bảng nhật ký mà nhiều người đọc được là chuyện khác hẳn.
		 */
		$t_nk = VHCC_DB::t( 'nhat_ky_ho_so' );
		if ( VHCC_DB::co_bang( $t_nk ) ) {
			$wpdb->insert( $t_nk, array(
				'luc'     => current_time( 'mysql' ),
				'ma_nv'   => $ma,
				'ai'      => isset( $u['name'] ) ? (string) $u['name'] : $ma,
				'tu_coso' => isset( $u['coso'] ) ? (string) $u['coso'] : '',
				'o'       => 'tu_bo_sung',
				'cu'      => '',
				'moi'     => implode( ', ', array_diff( array_keys( $dat ), array( 'cap_nhat' ) ) ),
			) );
		}

		return array( 'ok' => true, 'so' => count( $dat ) - 1,
			'message' => 'Đã lưu ' . ( count( $dat ) - 1 ) . ' mục.' );
	}

	/**
	 * Kiểm một ô. Trả chuỗi rỗng nếu được, hoặc câu nói rõ sai ở đâu.
	 *
	 * ⚠️ ĐỂ TRỐNG LUÔN ĐƯỢC. Đây là màn TỰ BỔ SUNG, không phải form bắt buộc: chặn ô trống là
	 *    người quên số căn cước ở nhà không lưu nổi số điện thoại vừa đổi.
	 */
	private static function kiem( $cot, $v ) {
		if ( '' === $v ) { return ''; }

		if ( 'sdt' === $cot || 'sdt_khan' === $cot ) {
			$so = preg_replace( '/[^0-9+]/', '', $v );
			if ( strlen( $so ) < 9 || strlen( $so ) > 15 ) {
				return 'số điện thoại phải 9–15 chữ số.';
			}
			return '';
		}

		if ( 'cccd' === $cot ) {
			/* Căn cước công dân Việt Nam 12 số; CMND cũ 9 số vẫn còn người dùng. Nhận cả hai,
			   không nhận gì khác — gõ nhầm sang số điện thoại là chuyện hay xảy ra. */
			$so = preg_replace( '/[^0-9]/', '', $v );
			if ( 9 !== strlen( $so ) && 12 !== strlen( $so ) ) {
				return 'căn cước 12 số (hoặc CMND cũ 9 số).';
			}
			return '';
		}

		if ( 'ngay_sinh' === $cot ) {
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) { return 'ngày sinh chưa đúng dạng.'; }
			$t = strtotime( $v );
			if ( ! $t ) { return 'ngày sinh không có thật.'; }
			/* Chặn ngày vô lý ở CẢ HAI đầu: gõ nhầm năm hiện tại thành năm sinh là chuyện
			   thường, và một hồ sơ ghi người 0 tuổi thì không ai phát hiện ra bằng mắt. */
			$tuoi = (int) ( ( time() - $t ) / ( 365.25 * 86400 ) );
			if ( $tuoi < 14 || $tuoi > 80 ) { return 'ngày sinh cho ra tuổi ' . $tuoi . ' — kiểm lại.'; }
			return '';
		}

		if ( 'dia_chi' === $cot && mb_strlen( $v ) > 255 ) { return 'địa chỉ quá dài.'; }
		if ( 'nguoi_lien_he_khan' === $cot && mb_strlen( $v ) > 190 ) { return 'tên quá dài.'; }

		return '';
	}
}
