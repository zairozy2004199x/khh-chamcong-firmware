<?php
/**
 * Tài khoản đăng nhập cho bản web ngoài.
 *
 * VẪN KHÔNG DỰNG BẢNG MẬT KHẨU RIÊNG. Người dùng ở đây là tài khoản WordPress
 * thật: WordPress lo băm mật khẩu, khoá sau nhiều lần sai, gửi thư đặt lại,
 * và mọi bản vá bảo mật về sau. Bớt một chỗ giữ mật khẩu là bớt một chỗ rò.
 * Màn hình này chỉ là lối tắt để khỏi phải đi vòng qua Người dùng của wp-admin
 * và khỏi phải hiểu hệ thống vai trò của WordPress.
 *
 * QUYỀN RIÊNG, KHÔNG MƯỢN QUYỀN SẴN CÓ. Bản trước lấy `edit_pages` làm điều
 * kiện vào, nghĩa là muốn cho kế toán xem sổ thì phải cho họ làm Editor — kèm
 * theo quyền sửa và xoá mọi trang của website. Giờ có quyền riêng `khtc_xem`
 * và vai trò "Kế toán K&H" chỉ mang đúng quyền đó: vào được sổ, không đụng
 * được gì khác trong WordPress.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_NguoiDung {

	const QUYEN    = 'khtc_xem';
	const VAI_TRO  = 'khtc_ke_toan';
	const TEN_VT   = 'Kế toán K&H';

	/** Vai trò sẵn có được coi là đủ quyền, để bản cũ nâng cấp lên không ai mất lối vào. */
	const QUYEN_CU = 'edit_pages';

	public static function khoi_dong() {
		// Lọc chứ không chỉ gán quyền lúc kích hoạt: NÂNG CẤP plugin không chạy
		// lại register_activation_hook, nên chỉ trông vào lúc kích hoạt là
		// người đang dùng bản cũ bị khoá ngoài ngay sau khi bấm Cập nhật.
		add_filter( 'user_has_cap', array( __CLASS__, 'bu_quyen' ), 10, 4 );
	}

	public static function bu_quyen( $quyen, $can, $args, $user ) {
		if ( empty( $quyen[ self::QUYEN ] ) && ! empty( $quyen[ self::QUYEN_CU ] ) ) {
			$quyen[ self::QUYEN ] = true;
		}
		return $quyen;
	}

	/** Dựng vai trò và gắn quyền cho quản trị viên. Gọi lại nhiều lần không sao. */
	public static function dung_vai_tro() {
		if ( ! get_role( self::VAI_TRO ) ) {
			add_role( self::VAI_TRO, self::TEN_VT, array( 'read' => true, self::QUYEN => true ) );
		} else {
			get_role( self::VAI_TRO )->add_cap( self::QUYEN );
		}
		foreach ( array( 'administrator', 'editor' ) as $vt ) {
			$r = get_role( $vt );
			if ( $r ) { $r->add_cap( self::QUYEN ); }
		}
	}

	/** Ai được phép quản lý tài khoản — KHÔNG phải ai vào được sổ cũng được. */
	public static function duoc_quan_ly() {
		return current_user_can( 'create_users' ) && current_user_can( 'list_users' );
	}

	/** Người dùng đang vào được sổ. */
	public static function ds() {
		$ra = array();
		foreach ( get_users( array( 'number' => 200, 'orderby' => 'user_login' ) ) as $u ) {
			if ( ! user_can( $u, self::QUYEN ) ) { continue; }
			$ra[] = array(
				'id'       => (int) $u->ID,
				'ten'      => $u->user_login,
				'hien_thi' => $u->display_name,
				'email'    => $u->user_email,
				'vai_tro'  => implode( ', ', array_map( array( __CLASS__, 'ten_vai_tro' ), (array) $u->roles ) ),
				'rieng'    => in_array( self::VAI_TRO, (array) $u->roles, true ),
				'quan_tri' => user_can( $u, 'manage_options' ),
				'toi'      => get_current_user_id() === (int) $u->ID,
			);
		}
		return $ra;
	}

	public static function ten_vai_tro( $vt ) {
		$ds = wp_roles()->get_names();
		return $ds[ $vt ] ?? $vt;
	}

	/**
	 * Thêm một người vào sổ.
	 *
	 * Tên đăng nhập đã tồn tại thì KHÔNG tạo mới mà cấp quyền cho người đó —
	 * tạo trùng là điều WordPress từ chối, và câu báo lỗi của nó không nói cho
	 * kế toán biết phải làm gì.
	 *
	 * @return array|WP_Error [id, mat_khau, đã có sẵn hay chưa]
	 */
	public static function them( $d ) {
		if ( ! self::duoc_quan_ly() ) {
			return new WP_Error( 'quyen', 'Chỉ quản trị viên mới thêm được tài khoản.' );
		}
		$ten = sanitize_user( (string) ( $d['ten'] ?? '' ), true );
		if ( '' === $ten ) {
			return new WP_Error( 'ten', 'Chưa có tên đăng nhập. Chỉ dùng chữ thường, số và dấu chấm.' );
		}
		$email = trim( (string) ( $d['email'] ?? '' ) );
		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'email', 'Địa chỉ thư "' . $email . '" không đúng dạng.' );
		}

		$co = get_user_by( 'login', $ten );
		if ( $co ) {
			$co->add_cap( self::QUYEN );
			KHTC_NhatKy::ghi( 'quyen', '', (int) $co->ID, 'Cấp quyền vào sổ cho tài khoản sẵn có ' . $ten );
			return array( 'id' => (int) $co->ID, 'mat_khau' => '', 'da_co' => true );
		}

		if ( '' !== $email && get_user_by( 'email', $email ) ) {
			return new WP_Error( 'email', 'Địa chỉ thư này đã gắn với một tài khoản khác.' );
		}

		$mk = (string) ( $d['mat_khau'] ?? '' );
		// Mật khẩu để trống thì WordPress sinh một chuỗi mạnh. Không bao giờ
		// đặt sẵn một mật khẩu đoán được cho người khác.
		$tu_sinh = '' === $mk;
		if ( $tu_sinh ) { $mk = wp_generate_password( 16, true, false ); }
		if ( strlen( $mk ) < 8 ) {
			return new WP_Error( 'mat_khau', 'Mật khẩu phải từ 8 ký tự. Để trống thì máy tự sinh một mật khẩu mạnh.' );
		}

		$id = wp_insert_user(
			array(
				'user_login'   => $ten,
				'user_pass'    => $mk,
				'user_email'   => $email,
				'display_name' => trim( (string) ( $d['hien_thi'] ?? '' ) ) ?: $ten,
				'role'         => self::VAI_TRO,
			)
		);
		if ( is_wp_error( $id ) ) { return $id; }

		KHTC_NhatKy::ghi( 'quyen', '', (int) $id, 'Tạo tài khoản ' . $ten . ' (' . self::TEN_VT . ')' );
		return array( 'id' => (int) $id, 'mat_khau' => $mk, 'da_co' => false, 'tu_sinh' => $tu_sinh );
	}

	/**
	 * Gỡ quyền vào sổ. KHÔNG xoá tài khoản WordPress.
	 *
	 * Xoá tài khoản kéo theo bài viết và mọi thứ khác người đó từng làm trên
	 * website; ở đây chỉ cần chặn lối vào sổ. Việc xoá hẳn để wp-admin lo.
	 */
	public static function go( $id ) {
		if ( ! self::duoc_quan_ly() ) {
			return new WP_Error( 'quyen', 'Chỉ quản trị viên mới gỡ được quyền.' );
		}
		$id = (int) $id;
		if ( $id === get_current_user_id() ) {
			return new WP_Error( 'tu_go', 'Không tự gỡ quyền của chính mình — gỡ xong là không vào lại được để sửa.' );
		}
		$u = get_user_by( 'id', $id );
		if ( ! $u ) { return new WP_Error( 'khong_co', 'Không tìm thấy tài khoản.' ); }
		if ( user_can( $u, 'manage_options' ) ) {
			return new WP_Error( 'quan_tri', 'Đây là quản trị viên của website. Đổi vai trò cho họ trong wp-admin → Người dùng, không gỡ từ đây.' );
		}

		$u->remove_cap( self::QUYEN );
		if ( in_array( self::VAI_TRO, (array) $u->roles, true ) ) {
			$u->remove_role( self::VAI_TRO );
			// Còn lại không vai trò nào thì không đăng nhập vào đâu được nữa;
			// cho về Subscriber để tài khoản vẫn còn mà không xem được sổ.
			if ( ! $u->roles ) { $u->add_role( 'subscriber' ); }
		}
		// Vai trò cũ (Editor) mang sẵn edit_pages, và bộ lọc bù quyền sẽ cho
		// họ vào lại. Chặn hẳn bằng một quyền phủ định, nếu không nút Gỡ
		// bấm xong mà người đó vẫn vào được — tệ hơn là không có nút.
		if ( user_can( get_user_by( 'id', $id ), self::QUYEN_CU ) ) {
			$u->add_cap( self::QUYEN, false );
		}
		KHTC_NhatKy::ghi( 'quyen', '', $id, 'Gỡ quyền vào sổ của ' . $u->user_login );
		return true;
	}

	public static function doi_mat_khau( $id, $mk ) {
		if ( ! self::duoc_quan_ly() ) {
			return new WP_Error( 'quyen', 'Chỉ quản trị viên mới đổi được mật khẩu người khác.' );
		}
		$id = (int) $id;
		$u  = get_user_by( 'id', $id );
		if ( ! $u ) { return new WP_Error( 'khong_co', 'Không tìm thấy tài khoản.' ); }
		$tu_sinh = '' === (string) $mk;
		if ( $tu_sinh ) { $mk = wp_generate_password( 16, true, false ); }
		if ( strlen( (string) $mk ) < 8 ) {
			return new WP_Error( 'mat_khau', 'Mật khẩu phải từ 8 ký tự.' );
		}
		wp_set_password( $mk, $id );
		// Nhật ký ghi VIỆC, không bao giờ ghi mật khẩu.
		KHTC_NhatKy::ghi( 'quyen', '', $id, 'Đổi mật khẩu tài khoản ' . $u->user_login );
		return array( 'mat_khau' => $mk, 'tu_sinh' => $tu_sinh );
	}
}
