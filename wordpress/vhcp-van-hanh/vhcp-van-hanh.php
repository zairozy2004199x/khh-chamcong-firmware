<?php
/**
 * Plugin Name:       K&H — Vận Hành
 * Description:       Vận hành cơ sở: doanh thu & chi phí theo ngày, sự cố, checklist, kho, đánh giá. Dùng chung sổ nhân sự và cửa đăng nhập với plugin Chấm Công.
 * Version:           1.5.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            K&H
 * Text Domain:       vhcp-van-hanh
 *
 * =================================================================================================
 * PLUGIN NÀY LÀ GÌ
 * =================================================================================================
 * Bản dựng lại của app "Vận Hành Nhà Ma" (trước chạy trên Firebase, một tệp `index.html` 6.451
 * dòng) thành plugin WordPress, cài lên khmatrix.com cạnh mấy plugin còn lại.
 *
 * 🔴 VÌ SAO PHẢI DỰNG LẠI, KHÔNG PHẢI VÌ THÍCH WORDPRESS HƠN
 * Bản Firebase khoá cửa bằng JavaScript: danh tính đọc thẳng từ `localStorage` không kiểm lại,
 * mật khẩu băm SHA-256 KHÔNG MUỐI rồi so ngay trong trình duyệt, còn Firebase thì đăng nhập
 * `signInAnonymously()` — ai mở trang cũng là người dùng hợp lệ. Nghĩa là gõ một dòng vào Console
 * là thành Quản lý, xem được doanh thu và tiền phạt của mọi cơ sở, không cần mật khẩu và không
 * để lại dấu vết. Ở bản này mọi câu hỏi "anh là ai, được làm gì" đều hỏi máy chủ.
 *
 * 🔴 KHÔNG DỰNG LẠI SỔ NHÂN SỰ. `vhcp-cham-cong` đã giữ nhân sự, PIN, vai trò, cơ sở, phiên đăng
 * nhập, chấm công và đơn xin đi muộn — và đang chạy thật. Plugin này mượn hết, chỉ thêm phần
 * chấm công chưa có. Hai sổ nhân sự song song là: nghỉ việc một người phải nhớ xoá hai nơi, và
 * quên một nơi thì người đã nghỉ vẫn đăng nhập được.
 *
 * ⚠️ PHỤ THUỘC BẮT BUỘC: plugin Chấm Công. Thiếu nó thì trang này không có cửa đăng nhập nào cả,
 *    và nó nói thẳng ra như vậy thay vì hiện một màn trắng.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

define( 'VHVH_VERSION', '1.5.0' );
define( 'VHVH_DIR', plugin_dir_path( __FILE__ ) );

require_once VHVH_DIR . 'includes/class-vhvh-db.php';
require_once VHVH_DIR . 'includes/class-vhvh-auth.php';
require_once VHVH_DIR . 'includes/class-vhvh-tien.php';
require_once VHVH_DIR . 'includes/class-vhvh-su-co.php';
require_once VHVH_DIR . 'includes/class-vhvh-checklist.php';
require_once VHVH_DIR . 'includes/class-vhvh-kho.php';
require_once VHVH_DIR . 'includes/class-vhvh-danh-gia.php';
require_once VHVH_DIR . 'includes/class-vhvh-kd.php';
require_once VHVH_DIR . 'includes/class-vhvh-viec.php';
require_once VHVH_DIR . 'includes/class-vhvh-bao-cao.php';
require_once VHVH_DIR . 'includes/class-vhvh-tong.php';
require_once VHVH_DIR . 'includes/class-vhvh-api.php';

class VHVH {

	/** Đường trang. Đổi được trong Cài đặt của plugin chấm công sau này; giờ cắm cứng. */
	const DUONG = 'van-hanh';

	public static function url() { return home_url( '/' . self::DUONG ); }

	public static function khoi_dong() {
		add_action( 'init', array( __CLASS__, 'luat' ) );
		add_filter( 'query_vars', array( __CLASS__, 'bien' ) );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ) );
		VHVH_API::khoi_dong();
	}

	public static function luat() {
		add_rewrite_rule( '^' . self::DUONG . '/?$', 'index.php?vhvh=1', 'top' );
		/* Sơ đồ bảng và luật đường dẫn cùng đi theo số bản: cài đè bản mới mà quên xả luật thì
		   địa chỉ /van-hanh trả 404, và người ta tưởng plugin hỏng. */
		if ( get_option( 'vhvh_ban' ) !== VHVH_VERSION ) {
			VHVH_DB::cai_dat();
			flush_rewrite_rules( false );
			update_option( 'vhvh_ban', VHVH_VERSION );
		}
	}

	public static function bien( $v ) { $v[] = 'vhvh'; return $v; }

	public static function phuc_vu() {
		if ( ! get_query_var( 'vhvh' ) ) { return; }
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		/* 🔴 KHÔNG CHO TRANG NÀY LỌT VÀO KHUNG <iframe> CỦA AI KHÁC. Màn quản trị nằm trong khung
		   của một trang lạ là kiểu lừa bấm cổ điển: người ta tưởng đang bấm nút trên trang kia. */
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: same-origin' );
		require VHVH_DIR . 'templates/trang.php';
		exit;
	}
}

register_activation_hook( __FILE__, function () {
	VHVH_DB::cai_dat();
	update_option( 'vhvh_ban', '' ); /* ép khai lại luật đường dẫn ở lượt tải sau */
} );

VHVH::khoi_dong();
