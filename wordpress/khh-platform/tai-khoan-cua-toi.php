<?php
/**
 * Tài khoản của tôi — nhân viên tự xem hồ sơ của mình và tự đổi mật khẩu.
 *
 * Trước bản này, hộp "Tài khoản của bạn" chỉ hiện tên, vai trò, tên đăng nhập và nút Thoát.
 * Người ở cơ sở muốn xem hồ sơ của chính mình phải nhờ văn phòng mở ứng dụng Hồ sơ nhân sự,
 * còn đổi mật khẩu thì phải nhờ Quản trị viên đặt lại rồi đọc mật khẩu mới qua điện thoại —
 * mật khẩu đi qua tay người thứ ba là mất ý nghĩa của mật khẩu.
 *
 * Hai việc đó nay làm ngay trong nền tảng, và Quản trị viên bật/tắt được từng việc ở
 * **Nền tảng K&H → Cấu hình → Tài khoản của nhân viên**.
 *
 * Chốt chặn khi đổi mật khẩu:
 *  - Bắt nhập ĐÚNG mật khẩu hiện tại. Ai mượn được máy đang mở sẵn cũng không đổi được.
 *  - Sai 5 lần thì khoá 15 phút, đếm theo từng tài khoản (không theo IP: cả cơ sở dùng
 *    chung một đường mạng, đếm theo IP là một người gõ sai khoá cả cửa hàng).
 *  - Mật khẩu mới tối thiểu 8 ký tự và phải khác mật khẩu cũ.
 *  - Đổi xong WordPress vô hiệu mọi phiên đang mở (chuỗi xác thực có lấy một mẩu của
 *    mật khẩu), nên ở đây cấp lại cookie cho đúng máy vừa đổi rồi bảo giao diện tải lại
 *    trang — máy khác đang mở phải đăng nhập lại, đúng như mong đợi.
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Giá trị mặc định cho phần cấu hình này — gộp vào khh_settings(). */
function khh_tk_mac_dinh() {
	return array(
		/* Cho nhân viên xem hồ sơ nhân sự của CHÍNH MÌNH trong hộp "Tài khoản của bạn". */
		'self_profile'     => 1,
		/* Có hiện lương cơ bản / phụ cấp trong hồ sơ đó không. */
		'self_profile_pay' => 0,
		/* Cho nhân viên tự đổi mật khẩu. */
		'self_password'    => 1,
		/* Độ dài tối thiểu của mật khẩu mới. */
		'self_password_min' => 8,
	);
}

/** Một khoá cấu hình của phần này. */
function khh_tk_cfg( $k ) {
	$s = khh_settings();
	$d = khh_tk_mac_dinh();
	return isset( $s[ $k ] ) ? $s[ $k ] : ( isset( $d[ $k ] ) ? $d[ $k ] : '' );
}

/** Độ dài tối thiểu, kẹp trong khoảng hợp lý để cấu hình gõ nhầm không khoá hết mọi người. */
function khh_tk_do_dai_min() {
	$n = (int) khh_tk_cfg( 'self_password_min' );
	if ( $n < 6 ) {
		$n = 6;
	}
	if ( $n > 64 ) {
		$n = 64;
	}
	return $n;
}

/** Khoá đếm số lần gõ sai mật khẩu hiện tại, theo từng tài khoản. */
function khh_tk_khoa_sai( $user_id ) {
	return 'khh_mk_sai_' . (int) $user_id;
}

/* ------------------------------------------------------------------ *
 * REST: đổi mật khẩu
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', 'khh_tk_rest_routes' );
function khh_tk_rest_routes() {
	register_rest_route(
		'khh/v1',
		'/doi-mat-khau',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_tk_doi_mat_khau',
			'permission_callback' => 'is_user_logged_in',
		)
	);
}

/**
 * Đổi mật khẩu của chính người đang đăng nhập.
 *
 * Nhận { cu, moi }. Trả { ok:1, taiLai:1 } — giao diện tải lại trang để lấy mã
 * chống giả mạo (nonce) mới, vì mã cũ gắn với phiên đã bị mật khẩu mới vô hiệu.
 */
function khh_tk_doi_mat_khau( $request ) {
	if ( ! khh_tk_cfg( 'self_password' ) ) {
		return new WP_Error(
			'khh_tat',
			'Nơi này đang tắt chức năng tự đổi mật khẩu. Nhờ quản trị đặt lại giúp.',
			array( 'status' => 403 )
		);
	}

	$user = wp_get_current_user();
	if ( ! $user || ! $user->ID ) {
		return new WP_Error( 'khh_chua_dang_nhap', 'Chưa đăng nhập.', array( 'status' => 401 ) );
	}

	$khoa  = khh_tk_khoa_sai( $user->ID );
	$sai   = (int) get_transient( $khoa );
	if ( $sai >= 5 ) {
		return new WP_Error(
			'khh_khoa',
			'Gõ sai mật khẩu hiện tại quá 5 lần. Chờ 15 phút rồi thử lại.',
			array( 'status' => 429 )
		);
	}

	$cu  = (string) $request->get_param( 'cu' );
	$moi = (string) $request->get_param( 'moi' );

	/* KHÔNG sanitize mật khẩu: dấu cách đầu/cuối và ký tự lạ đều là một phần của
	   mật khẩu người ta đặt, cắt đi là đổi mất mật khẩu mà không ai biết. */
	if ( '' === $cu || ! wp_check_password( $cu, $user->user_pass, $user->ID ) ) {
		set_transient( $khoa, $sai + 1, 15 * MINUTE_IN_SECONDS );
		$con = 4 - $sai;
		return new WP_Error(
			'khh_sai_mk',
			'Mật khẩu hiện tại không đúng.' . ( $con > 0 ? ' Còn ' . $con . ' lần thử.' : '' ),
			array( 'status' => 400 )
		);
	}

	$min = khh_tk_do_dai_min();
	if ( strlen( $moi ) < $min ) {
		return new WP_Error(
			'khh_ngan',
			'Mật khẩu mới phải từ ' . $min . ' ký tự trở lên.',
			array( 'status' => 400 )
		);
	}
	if ( hash_equals( $cu, $moi ) ) {
		return new WP_Error( 'khh_trung', 'Mật khẩu mới trùng mật khẩu cũ.', array( 'status' => 400 ) );
	}

	delete_transient( $khoa );
	wp_set_password( $moi, $user->ID );

	/* wp_set_password làm mọi phiên đang mở hết hiệu lực, kể cả phiên đang gọi lệnh này.
	   Cấp lại cookie cho đúng máy vừa đổi để người ta không bị văng ra giữa chừng. */
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, false );

	return rest_ensure_response(
		array(
			'ok'     => 1,
			/* Mã nonce hiện tại gắn với phiên vừa bị vô hiệu → tải lại trang mới xin được mã mới. */
			'taiLai' => 1,
		)
	);
}

/* ------------------------------------------------------------------ *
 * Cấu hình trong trang quản trị
 * ------------------------------------------------------------------ */

/** Phần cấu hình "Tài khoản của nhân viên", gọi từ khh_settings_page(). */
function khh_tk_o_cau_hinh( $s ) {
	$min = isset( $s['self_password_min'] ) ? (int) $s['self_password_min'] : 8;
	?>
	<tr>
		<th>Nhân viên xem hồ sơ của mình</th>
		<td>
			<label><input type="checkbox" name="self_profile" value="1"
				<?php checked( ! empty( $s['self_profile'] ), true ); ?>>
				Hiện hồ sơ nhân sự trong hộp <strong>Tài khoản của bạn</strong></label>
			<p class="description">Mỗi người chỉ thấy hồ sơ của chính mình: mã nhân sự, chức danh, bộ phận,
				mảng, cơ sở, quản lý trực tiếp, ngày vào làm, hợp đồng, phép còn lại, liên hệ.
				Muốn sửa thì vẫn phải qua ứng dụng Hồ sơ nhân sự — ô này chỉ để xem.</p>
			<label><input type="checkbox" name="self_profile_pay" value="1"
				<?php checked( ! empty( $s['self_profile_pay'] ), true ); ?>>
				Hiện cả lương cơ bản và phụ cấp</label>
			<p class="description">Mặc định tắt. Lưu ý cho đúng: đây là <em>ẩn trên màn hình</em>, không phải
				khoá dữ liệu — nền tảng vẫn gửi toàn bộ hồ sơ nhân sự về máy của mọi tài khoản đã đăng nhập,
				đúng như trước nay. Muốn lương thật sự kín thì phải chặn từ tầng dữ liệu, chưa làm trong bản này.</p>
		</td>
	</tr>
	<tr>
		<th>Nhân viên tự đổi mật khẩu</th>
		<td>
			<label><input type="checkbox" name="self_password" value="1"
				<?php checked( ! empty( $s['self_password'] ), true ); ?>>
				Cho phép đổi mật khẩu ngay trong nền tảng</label>
			<p class="description">Phải nhập đúng mật khẩu hiện tại. Sai 5 lần thì khoá 15 phút theo tài khoản.
				Đổi xong, các máy khác đang mở tài khoản này phải đăng nhập lại.
				Người vào bằng <strong>mã PIN</strong> mà không nhớ mật khẩu thì không đổi được — nhờ quản trị
				đặt lại ở màn <em>Cấp tài khoản</em>.</p>
			<p>
				<label for="self_password_min">Độ dài tối thiểu</label>
				<input type="number" id="self_password_min" name="self_password_min" min="6" max="64"
					value="<?php echo esc_attr( $min ? $min : 8 ); ?>" class="small-text">
				<span class="description">ký tự (6–64, mặc định 8)</span>
			</p>
		</td>
	</tr>
	<?php
}

/** Đọc phần cấu hình này từ $_POST khi bấm Lưu. Gọi từ khh_save_settings(). */
function khh_tk_nhan_cau_hinh() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- khh_save_settings() đã kiểm nonce.
	$min = isset( $_POST['self_password_min'] ) ? (int) $_POST['self_password_min'] : 8;
	if ( $min < 6 ) {
		$min = 6;
	}
	if ( $min > 64 ) {
		$min = 64;
	}
	return array(
		'self_profile'      => isset( $_POST['self_profile'] ) ? 1 : 0,
		'self_profile_pay'  => isset( $_POST['self_profile_pay'] ) ? 1 : 0,
		'self_password'     => isset( $_POST['self_password'] ) ? 1 : 0,
		'self_password_min' => $min,
	);
	// phpcs:enable
}
