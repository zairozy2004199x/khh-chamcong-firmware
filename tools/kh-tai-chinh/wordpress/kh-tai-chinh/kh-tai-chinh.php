<?php
/**
 * Plugin Name:       Tài Chính K&H
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Theo dõi ngân hàng, giao dịch và đối soát cho CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H — chạy thẳng trên host WordPress, dữ liệu nằm trong MySQL của chính website.
 * Version:           1.8.2
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            K&H
 * License:           GPL-2.0-or-later
 *
 * ---------------------------------------------------------------------------
 * VÌ SAO CÓ PLUGIN NÀY
 *
 * Bản gốc (KH Bank Tracker) là app Node.js: phải có server chạy Node, mà hosting
 * đang dùng chỉ có WordPress/PHP. Dựng lại bằng PHP để cài thẳng qua wp-admin,
 * không cần Render/Railway, không cần SSH.
 *
 * KHÁC BẢN GỐC Ở HAI ĐIỂM NỀN TẢNG:
 *
 * 1. Dữ liệu nằm trong BẢNG MySQL, không phải file JSON. Bản gốc giữ toàn bộ
 *    giao dịch trong transactions.json — dữ liệu thật đã 95 MB / 219.000 dòng,
 *    mỗi lần đọc là nạp cả file vào RAM. Trên shared hosting cách đó chết ngay.
 *    Bảng MySQL có chỉ mục, lọc theo ngày/ngân hàng không phải quét cả tệp.
 *
 * 2. Đăng nhập dùng LUÔN tài khoản WordPress, không tự dựng bảng người dùng và
 *    mật khẩu riêng. Ít một chỗ lưu mật khẩu là ít một chỗ rò.
 * ---------------------------------------------------------------------------
 */

defined( 'ABSPATH' ) || exit;

define( 'KHTC_VERSION', '1.8.2' );
define( 'KHTC_DIR', plugin_dir_path( __FILE__ ) );
define( 'KHTC_URL', plugin_dir_url( __FILE__ ) );

/** Quyền tối thiểu để mở plugin. Kế toán thường là Editor nên không dùng manage_options. */
// Quyền riêng của plugin, không mượn edit_pages nữa: cho kế toán xem sổ mà
// không phải cho họ quyền sửa mọi trang của website. Ai đang có edit_pages vẫn
// vào được, nhờ bộ lọc trong KHTC_NguoiDung — nâng cấp không khoá ai ra ngoài.
define( 'KHTC_CAP', 'khtc_xem' );

require_once KHTC_DIR . 'includes/class-khtc-db.php';
require_once KHTC_DIR . 'includes/class-khtc-cty.php';
require_once KHTC_DIR . 'includes/class-khtc-nhat-ky.php';
require_once KHTC_DIR . 'includes/class-khtc-khoa.php';
require_once KHTC_DIR . 'includes/class-khtc-ngan-hang.php';
require_once KHTC_DIR . 'includes/class-khtc-giao-dich.php';
require_once KHTC_DIR . 'includes/class-khtc-doi-soat.php';
require_once KHTC_DIR . 'includes/class-khtc-chi-phi.php';
require_once KHTC_DIR . 'includes/class-khtc-hoa-don-ra.php';
require_once KHTC_DIR . 'includes/class-khtc-hoa-don-vao.php';
require_once KHTC_DIR . 'includes/class-khtc-cong-no.php';
require_once KHTC_DIR . 'includes/class-khtc-phap-danh.php';
require_once KHTC_DIR . 'includes/class-khtc-ho-so.php';
require_once KHTC_DIR . 'includes/class-khtc-bao-cao.php';
require_once KHTC_DIR . 'includes/class-khtc-mau.php';
require_once KHTC_DIR . 'includes/class-khtc-sao-luu.php';
require_once KHTC_DIR . 'includes/class-khtc-ui.php';
require_once KHTC_DIR . 'includes/class-khtc-trang.php';
require_once KHTC_DIR . 'includes/class-khtc-web.php';
require_once KHTC_DIR . 'includes/class-khtc-diem.php';
require_once KHTC_DIR . 'includes/class-khtc-dan-tho.php';
require_once KHTC_DIR . 'includes/class-khtc-sinh-hd.php';
require_once KHTC_DIR . 'includes/class-khtc-nguoi-dung.php';
require_once KHTC_DIR . 'includes/class-khtc-tep.php';
require_once KHTC_DIR . 'includes/class-khtc-admin.php';

register_activation_hook(
	__FILE__,
	function () {
		KHTC_DB::tao_bang();
		KHTC_NguoiDung::dung_vai_tro();
		KHTC_Web::nap_lai_rule();
	}
);
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', array( 'KHTC_DB', 'nang_cap_neu_can' ) );
add_action( 'admin_menu', array( 'KHTC_Admin', 'menu' ) );
add_action( 'admin_init', array( 'KHTC_Admin', 'nhay_ra_web' ) );
add_action( 'admin_enqueue_scripts', array( 'KHTC_Admin', 'nap_style' ) );

KHTC_NguoiDung::khoi_dong();
KHTC_Web::khoi_dong();

// Tải CSV phải chạy TRƯỚC khi có chữ nào được in ra, nếu không header bị từ chối
// và trình duyệt nhận một trang HTML mang tên .csv.
add_action( 'init', array( 'KHTC_DoiSoat', 'tai_csv' ), 20 );
add_action( 'init', array( 'KHTC_SaoLuu', 'tai' ), 20 );
add_action( 'init', array( 'KHTC_HoaDonRa', 'tai_csv' ), 20 );
add_action( 'init', array( 'KHTC_HoaDonVao', 'tai_csv' ), 20 );
add_action( 'init', array( 'KHTC_BaoCao', 'tai_csv' ), 20 );
add_action( 'init', array( 'KHTC_PhapDanh', 'tai_csv' ), 20 );

/**
 * Nâng cấp từ 0.1.x lên: bảng đối soát là bảng mới và luật đường dẫn /tai-chinh/
 * chưa từng được ghi, nên phải nạp lại một lần. Cờ khtc_rule giữ cho việc này
 * chỉ xảy ra đúng một lần chứ không phải mỗi lần tải trang — flush_rewrite_rules
 * là thao tác nặng.
 */
add_action(
	'init',
	function () {
		if ( get_option( 'khtc_rule' ) !== KHTC_VERSION ) {
			flush_rewrite_rules();
			// Nâng cấp KHÔNG chạy register_activation_hook, nên vai trò và
			// quyền phải được dựng lại ở đây, nếu không bản nâng cấp lên sẽ
			// không có vai trò "Kế toán K&H" mà màn hình Người dùng cần.
			KHTC_NguoiDung::dung_vai_tro();
			update_option( 'khtc_rule', KHTC_VERSION );
		}
	},
	99
);
