<?php
/**
 * CHIA SẺ DOANH THU THEO CỬA HÀNG CHO PLUGIN VẬN HÀNH CHI PHÍ.
 *
 * Anh Thắng 25/09/2026: *"Em có thể lấy doanh thu cơ sở trên wed doanh-thu-hcm không."* ·
 * *"Để anh đánh giá doanh thu dựa trên chi phí"*.
 *
 * Trang Chi phí (khmatrix.com) gọi sang đây, mang theo một KHOÁ CHIA SẺ trong header
 * `X-KHH-Khoa`. Khoá khớp mới trả số; không thì 401 và không nói gì thêm.
 *
 * 🔴 CHỈ TRẢ TỔNG THEO CỬA HÀNG trong khoảng ngày hỏi — không trả từng ngày, từng hoá đơn, từng
 *    món, từng cách thanh toán. Bên kia chỉ cần một con số để đặt cạnh chi phí; gửi thừa là mở
 *    thêm một cửa rò cho dữ liệu doanh thu.
 * 🔴 KHOÁ KHÔNG BAO GIỜ HIỆN LẠI. Ô nhập ở trang cài đặt chỉ nói ĐÃ CÓ / CHƯA CÓ; để trống là giữ
 *    nguyên; muốn xoá phải tích ô xoá. Nút "Tạo khoá ngẫu nhiên" bày khoá đúng MỘT lần ngay sau
 *    khi tạo (qua transient 2 phút gắn với người bấm), rồi thôi — ai không chép kịp thì tạo lại.
 * ⚠️ Không đi qua `khh_dt_duoc_xem()`: bên gọi là một máy chủ, không có phiên PIN lẫn tài khoản
 *    WordPress. Khoá là cửa duy nhất.
 *
 * Tệp này đứng một mình: chỉ cần `khh_dt_bang()` của plugin. Bên Chi phí đọc bằng
 * `VHCP_DoanhThu::goi()` (wordpress/vhcp-chi-phi/includes/class-vhcp-doanh-thu.php).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KHH_DT_O_KHOA_CP = 'khh_dt_khoa_chi_phi';

/** Khoá đang lưu (chuỗi rỗng = chưa đặt). Không autoload: mỗi trang không cần tải nó. */
function khh_dt_cp_khoa() {
	return trim( (string) get_option( KHH_DT_O_KHOA_CP, '' ) );
}

/**
 * Cửa duy nhất: khoá trong header phải khớp khoá đang lưu. So bằng `hash_equals` để không lộ độ dài
 * khoá qua thời gian so sánh. Chưa đặt khoá = đóng cửa, kể cả bên gọi gửi chuỗi rỗng.
 */
function khh_dt_cp_duoc_goi( $req ) {
	$luu = khh_dt_cp_khoa();
	$gui = trim( (string) $req->get_header( 'X-KHH-Khoa' ) );
	if ( '' === $gui ) { $gui = trim( (string) $req->get_header( 'x_khh_khoa' ) ); }
	if ( '' === $luu || '' === $gui ) {
		return new WP_Error( 'khh_dt_khoa', 'Chưa có khoá chia sẻ hoặc khoá không khớp.', array( 'status' => 401 ) );
	}
	if ( ! hash_equals( $luu, $gui ) ) {
		return new WP_Error( 'khh_dt_khoa', 'Chưa có khoá chia sẻ hoặc khoá không khớp.', array( 'status' => 401 ) );
	}
	return true;
}

add_action( 'rest_api_init', 'khh_dt_cp_rest' );
function khh_dt_cp_rest() {
	register_rest_route(
		'khh-dt/v1',
		'/doanh-thu-co-so',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_cp_rest_doanh_thu',
			'permission_callback' => 'khh_dt_cp_duoc_goi',
		)
	);
}

/** Ngày dạng Y-m-d; sai dạng → ''. */
function khh_dt_cp_ngay( $v ) {
	$s = preg_replace( '/[^0-9\-]/', '', (string) $v );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
}

/**
 * GET /khh-dt/v1/doanh-thu-co-so?tu=Y-m-d&den=Y-m-d
 * → { ok, tu, den, web, cuaHang: [ { ten, doanhThu, thanhTien, soHd, soNgay } ] }
 *
 * `ping=1` → chỉ trả { ok, web } để bên kia thử kết nối mà không kéo số.
 */
function khh_dt_cp_rest_doanh_thu( $req ) {
	global $wpdb;
	$web = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
	if ( (string) $req->get_param( 'ping' ) !== '' ) {
		return array( 'ok' => true, 'web' => $web );
	}
	$tu  = khh_dt_cp_ngay( $req->get_param( 'tu' ) );
	$den = khh_dt_cp_ngay( $req->get_param( 'den' ) );
	if ( '' === $tu || '' === $den ) {
		return new WP_Error( 'khh_dt_ngay', 'Thiếu tu/den dạng YYYY-MM-DD.', array( 'status' => 400 ) );
	}
	if ( $tu > $den ) { $t = $tu; $tu = $den; $den = $t; }

	$bang = khh_dt_bang();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT cua_hang, SUM(doanh_thu) dt, SUM(thanh_tien) tt, SUM(so_hd) hd, COUNT(DISTINCT ngay) n
			   FROM $bang WHERE ngay >= %s AND ngay <= %s GROUP BY cua_hang ORDER BY dt DESC",
			$tu, $den
		),
		ARRAY_A
	);
	// phpcs:enable
	$out = array();
	foreach ( (array) $rows as $r ) {
		$ten = trim( (string) $r['cua_hang'] );
		if ( '' === $ten ) { continue; }
		$out[] = array(
			'ten'       => $ten,
			'doanhThu'  => round( (float) $r['dt'] ),
			'thanhTien' => round( (float) $r['tt'] ),
			'soHd'      => (int) $r['hd'],
			'soNgay'    => (int) $r['n'],
		);
	}
	return array( 'ok' => true, 'tu' => $tu, 'den' => $den, 'web' => $web, 'cuaHang' => $out );
}

/* ------------------------------------------------------------------ *
 * Trang cài đặt: Doanh thu FABi ▸ Chia sẻ cho Chi phí
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'khh_dt_cp_menu', 20 );
function khh_dt_cp_menu() {
	add_submenu_page(
		'khh-doanh-thu',
		'Chia sẻ cho Chi phí',
		'Chia sẻ cho Chi phí',
		'manage_options',
		'khh-dt-chia-se-chi-phi',
		'khh_dt_cp_trang'
	);
}

/** Khoá vừa tạo — bày đúng một lần cho người đã bấm, rồi xoá. */
function khh_dt_cp_khoa_moi_lay() {
	$k = 'khh_dt_khoa_moi_' . get_current_user_id();
	$v = get_transient( $k );
	if ( false !== $v && '' !== $v ) { delete_transient( $k ); }
	return is_string( $v ) ? $v : '';
}

function khh_dt_cp_trang() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không đủ quyền.' ); }
	$co   = '' !== khh_dt_cp_khoa();
	$moi  = khh_dt_cp_khoa_moi_lay();
	$luu  = isset( $_GET['luu'] ) ? (string) $_GET['luu'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$dia  = trailingslashit( home_url() ) . 'wp-json/khh-dt/v1/doanh-thu-co-so';
	?>
	<div class="wrap">
		<h1>📈 Chia sẻ doanh thu cho trang Chi phí</h1>
		<?php if ( '1' === $luu ) : ?><div class="notice notice-success"><p>Đã lưu.</p></div><?php endif; ?>
		<?php if ( '' !== $moi ) : ?>
			<div class="notice notice-warning" style="padding:12px 16px">
				<p><b>Khoá mới — chép ngay, trang này KHÔNG bày lại:</b></p>
				<p><code style="font-size:15px;user-select:all"><?php echo esc_html( $moi ); ?></code></p>
				<p>Dán vào trang Chi phí ▸ Cấu hình ▸ <b>Kết nối web Doanh thu</b> ▸ ô Khoá chia sẻ, rồi bấm Lưu.</p>
			</div>
		<?php endif; ?>
		<p>Trang Chi phí gọi sang địa chỉ <code><?php echo esc_html( $dia ); ?></code> mang theo khoá này để lấy
			<b>tổng doanh thu theo cửa hàng</b> trong một khoảng ngày — chỉ tổng, không có từng ngày hay từng hoá đơn.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
			<input type="hidden" name="action" value="khh_dt_luu_khoa_chi_phi">
			<?php wp_nonce_field( 'khh_dt_khoa_chi_phi' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="khh_dt_khoa_cp">Khoá chia sẻ</label></th>
					<td>
						<input type="password" id="khh_dt_khoa_cp" name="khoa" class="regular-text" autocomplete="new-password"
							placeholder="<?php echo $co ? 'ĐÃ CÓ khoá — để trống là giữ nguyên' : 'CHƯA có khoá — dán hoặc bấm Tạo khoá'; ?>">
						<p class="description">
							Đang: <b><?php echo $co ? 'ĐÃ CÓ khoá' : 'CHƯA có khoá (cửa đóng)'; ?></b>.
							Khoá đã lưu không bao giờ hiện lại ở đây. Muốn đổi thì dán khoá mới; muốn tự sinh thì bấm nút bên dưới.
						</p>
						<?php if ( $co ) : ?>
							<label><input type="checkbox" name="xoa" value="1"> Xoá khoá (đóng cửa chia sẻ)</label>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary">Lưu</button>
				<button type="submit" class="button" name="tao" value="1"
					onclick="return confirm('Tạo khoá ngẫu nhiên mới? Khoá cũ (nếu có) hết hiệu lực ngay, trang Chi phí phải dán khoá mới.');">🎲 Tạo khoá ngẫu nhiên</button>
			</p>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_khh_dt_luu_khoa_chi_phi', 'khh_dt_cp_luu' );
function khh_dt_cp_luu() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không đủ quyền.' ); }
	check_admin_referer( 'khh_dt_khoa_chi_phi' );
	$kq = khh_dt_cp_ap_dung( array(
		'khoa' => isset( $_POST['khoa'] ) ? (string) wp_unslash( $_POST['khoa'] ) : '',
		'xoa'  => ! empty( $_POST['xoa'] ),
		'tao'  => ! empty( $_POST['tao'] ),
	) );
	if ( '' !== $kq['khoaMoi'] ) {
		set_transient( 'khh_dt_khoa_moi_' . get_current_user_id(), $kq['khoaMoi'], 120 );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=khh-dt-chia-se-chi-phi&luu=1' ) );
	exit;
}

/**
 * Luật lưu, tách khỏi HTTP để kiểm được:
 *   tao   → sinh khoá 40 ký tự, lưu, trả `khoaMoi` (bày một lần).
 *   xoa   → xoá khoá.
 *   khoa  → chuỗi có nội dung thì lưu (cắt khoảng trắng); RỖNG = GIỮ NGUYÊN, không phải xoá.
 * Trả { thayDoi, khoaMoi }.
 */
function khh_dt_cp_ap_dung( $d ) {
	$d = (array) $d;
	if ( ! empty( $d['tao'] ) ) {
		$moi = wp_generate_password( 40, false, false );
		update_option( KHH_DT_O_KHOA_CP, $moi, false );
		return array( 'thayDoi' => true, 'khoaMoi' => $moi );
	}
	if ( ! empty( $d['xoa'] ) ) {
		delete_option( KHH_DT_O_KHOA_CP );
		return array( 'thayDoi' => true, 'khoaMoi' => '' );
	}
	$k = isset( $d['khoa'] ) ? trim( (string) $d['khoa'] ) : '';
	if ( '' === $k ) { return array( 'thayDoi' => false, 'khoaMoi' => '' ); }
	if ( mb_strlen( $k ) < 12 ) {
		/* Khoá ngắn là khoá đoán được. Không lưu, không đổi gì. */
		return array( 'thayDoi' => false, 'khoaMoi' => '', 'loi' => 'Khoá phải từ 12 ký tự.' );
	}
	update_option( KHH_DT_O_KHOA_CP, $k, false );
	return array( 'thayDoi' => true, 'khoaMoi' => '' );
}
