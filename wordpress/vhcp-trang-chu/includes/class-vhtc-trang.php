<?php
/**
 * Trang cổng vào — danh sách app của K&H.
 *
 * KHÔNG có dữ liệu nào trên trang này, cũng không có cổng PIN: nó chỉ là mấy đường dẫn, mà
 * đường dẫn thì ai gõ cũng ra. Đặt thêm một cổng PIN ở đây là thêm một chỗ hỏng, thêm một mật
 * khẩu phải nhớ, mà không chặn thêm được gì — mỗi app đã tự có cổng của nó.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHTC_Trang {

	public static function slug() {
		$s = get_option( 'vhtc_slug' );
		$s = $s ? sanitize_title( $s ) : 'van-hanh';
		return $s ? $s : 'van-hanh';
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhtc', 'app', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhtc_app=1', 'top' );
		add_filter( 'query_vars', function ( $v ) { $v[] = 'vhtc_app'; return $v; } );
	}

	/**
	 * DANH SÁCH APP.
	 *
	 * 🔴 Đường dẫn LẤY TỪ CHÍNH APP ĐÓ, không gõ lại ở đây. Anh Thắng đổi đường dẫn bên app chi
	 *    phí là bên này tự theo. Gõ lại là sớm muộn hai nơi lệch, mà lệch thì bấm vào ra 404 chứ
	 *    không có gì báo.
	 * 🔴 App chưa cài -> `co: false`, hiện xám. KHÔNG dựng đường dẫn đoán, vì một liên kết chết
	 *    trông y hệt một liên kết sống cho tới lúc bấm vào.
	 */
	public static function ds_app() {
		return array(
			array(
				'ten'   => 'Chấm Công',
				'mo_ta' => 'Bảng công, lương, nhân sự, lịch làm việc',
				'icon'  => '🕐',
				'co'    => class_exists( 'VHCC_Trang' ),
				'url'   => class_exists( 'VHCC_Trang' ) ? VHCC_Trang::url() : '',
			),
			array(
				'ten'   => 'Vận Hành Chi Phí',
				'mo_ta' => 'Tạm ứng, chi phí cơ sở, dự án, quyết toán, xuất MISA',
				'icon'  => '💰',
				'co'    => class_exists( 'VHCP_App' ),
				'url'   => class_exists( 'VHCP_App' ) ? VHCP_App::app_url() : '',
			),
			array(
				'ten'   => 'Ghế Massage',
				'mo_ta' => 'Doanh thu QR theo cơ sở & máy, tình trạng ghế',
				'icon'  => '💺',
				'co'    => class_exists( 'VHG_Trang' ) || class_exists( 'VHG_Admin' ),
				/* Trỏ về TRANG NGOÀI `/ghe` (mở bằng PIN), không về wp-admin. Nhân viên đứng quầy
				   không có tài khoản WordPress, và cũng không nên có — cấp tài khoản cho 26 cửa
				   hàng là cấp luôn đường vào phần quản trị website.
				   Bản cũ (vhcp-ghe < 1.1.0) chưa có trang ngoài nên vẫn rơi về wp-admin: liên kết
				   dẫn tới màn đăng nhập còn hơn liên kết chết. */
				'url'   => class_exists( 'VHG_Trang' ) ? VHG_Trang::url()
					: ( class_exists( 'VHG_Admin' ) ? VHG_Admin::app_url() : '' ),
			),
			array(
				'ten'   => 'Thư Viện Hợp Đồng',
				'mo_ta' => 'Hợp đồng, đối tác, ngày hết hiệu lực',
				'icon'  => '📄',
				'co'    => class_exists( 'VHD_Trang' ),
				'url'   => class_exists( 'VHD_Trang' ) ? VHD_Trang::url() : '',
			),
		);
	}

	/** Có đang bật "dùng làm trang chủ" không. */
	public static function lam_trang_chu() { return (bool) get_option( 'vhtc_trang_chu' ); }

	/**
	 * Quyết định có vẽ trang cổng cho yêu cầu này không.
	 *
	 * ⚠️ CHỈ chiếm ĐÚNG trang chủ, không chiếm gì khác. `is_front_page()` là chốt duy nhất —
	 *    thiếu nó thì mọi trang của site đều biến thành trang cổng, kể cả trang của app khác,
	 *    và người dùng không còn đường nào đi tiếp.
	 *
	 * ⚠️ `template_redirect` KHÔNG chạy trong wp-admin và không chạy cho REST. Nên bật nhầm cũng
	 *    không bao giờ khoá được anh Thắng ra khỏi wp-admin — luôn còn đường vào để tắt lại.
	 *    Đây là lý do móc ở đây chứ không móc sớm hơn.
	 */
	/**
	 * QUYẾT ĐỊNH: yêu cầu này có phải trang cổng không.
	 *
	 * Tách riêng khỏi phần vẽ vì phần vẽ kết thúc bằng `exit` — mà `exit` thì không phép thử nào
	 * chạy qua được. Phần đáng thử ở đây là QUYẾT ĐỊNH (chiếm cái gì, không chiếm cái gì), nên
	 * nó phải gọi được mà không giết cả bài kiểm.
	 */
	public static function nen_ve() {
		if ( get_query_var( 'vhtc_app' ) || isset( $_GET['vhtc'] ) ) { return true; }
		return self::lam_trang_chu() && ! is_admin() && is_front_page();
	}

	public static function co_phai_trang_nay() {
		if ( ! self::nen_ve() ) { return; }
		status_header( 200 );
		self::ve();
		exit;
	}

	public static function ve() {
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		$ten_cty = get_option( 'vhtc_ten', 'Vận Hành K&H' );
		$ds      = self::ds_app();

		echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
		/* `viewport` bắt buộc: phần lớn người mở trang này là nhân viên trên điện thoại. Thiếu nó
		   thì chữ bé bằng con kiến và phải chụm ngón tay phóng to mới bấm được. */
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html( $ten_cty ) . '</title>';
		echo '<style>'
			. 'body{margin:0;min-height:100vh;background:#0f172a;color:#e2e8f0;'
			. 'font:16px/1.55 "Segoe UI",system-ui,Arial,sans-serif;'
			. 'display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 18px}'
			. 'h1{font-size:23px;margin:0 0 4px;font-weight:700;text-align:center}'
			. '.phu{color:#94a3b8;font-size:13.5px;margin:0 0 26px;text-align:center}'
			. '.luoi{display:grid;gap:14px;width:100%;max-width:430px}'
			. 'a.the,div.the{display:flex;gap:14px;align-items:center;background:#1e293b;'
			. 'border:1px solid #334155;border-radius:13px;padding:16px 17px;text-decoration:none;color:inherit}'
			/* Ô bấm cao ít nhất 62px: ngón tay người lớn bấm trên điện thoại, nút bé là bấm trượt. */
			. 'a.the{min-height:62px}'
			. 'a.the:hover{background:#243549;border-color:#3b82f6}'
			. 'div.the{opacity:.42}'
			. '.bt{font-size:27px;line-height:1}'
			. '.ten{font-weight:600;font-size:16.5px}'
			. '.mt{color:#94a3b8;font-size:12.8px;margin-top:2px}'
			. '.chua{margin-left:auto;font-size:11.5px;color:#f59e0b;white-space:nowrap}'
			. '.chan{color:#64748b;font-size:11.5px;margin-top:26px;text-align:center}'
			. '</style></head><body>';

		echo '<h1>' . esc_html( $ten_cty ) . '</h1>';
		echo '<p class="phu">Chọn hệ thống cần vào</p>';
		echo '<div class="luoi">';
		foreach ( $ds as $a ) {
			if ( $a['co'] && $a['url'] ) {
				echo '<a class="the" href="' . esc_url( $a['url'] ) . '">';
			} else {
				echo '<div class="the">';
			}
			echo '<span class="bt">' . esc_html( $a['icon'] ) . '</span>';
			echo '<span><span class="ten">' . esc_html( $a['ten'] ) . '</span>'
				. '<br><span class="mt">' . esc_html( $a['mo_ta'] ) . '</span></span>';
			if ( ! $a['co'] ) { echo '<span class="chua">chưa cài</span>'; }
			echo ( $a['co'] && $a['url'] ) ? '</a>' : '</div>';
		}
		echo '</div>';
		echo '<p class="chan">Mỗi hệ thống có mã PIN riêng.</p>';
		echo '</body></html>';
	}
}
