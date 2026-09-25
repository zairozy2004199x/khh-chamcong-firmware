<?php
/**
 * Plugin Name:       Vận Hành Chi Phí (MTD)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       App Chi Phí Cơ Sở / Vận Hành Chi Phí dựng lại trên WordPress — đơn tạm ứng theo tuần, chi phí kỹ thuật, marketing, công tác/setup, quyết toán thừa/thiếu và xuất MISA. Dữ liệu nằm trong bảng MySQL riêng (không phụ thuộc Google Sheet).
 * Version:           1.333.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhcpmtd
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 🔴 SỐ NÀY PHẢI BẰNG ĐÚNG "Version:" Ở ĐẦU TỆP.
 *
 * Nó không phải chỗ ghi chú: `vhcpmtd_ver` so với nó để biết có phải chạy bước nâng cấp không, và
 * nó đi vào ?ver= của CSS/JS để trình duyệt bỏ bộ nhớ đệm. Header đã lên 1.35.0 trong khi số
 * này còn đứng ở 1.31.0 — nghĩa là suốt từ đó tới giờ, cài đè KHÔNG chạy bước nâng cấp nào và
 * trình duyệt vẫn dùng CSS/JS cũ. Có phép thử chốt hai số bằng nhau: tools/test/kiem-phien-ban.py
 */
define( 'VHCPMTD_VERSION', '1.333.0' );
define( 'VHCPMTD_FILE', __FILE__ );
define( 'VHCPMTD_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHCPMTD_URL', plugin_dir_url( __FILE__ ) );

require_once VHCPMTD_DIR . 'includes/class-vhcp-util.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-db.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-meta.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-cfg.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-auth.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-log.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-donvi.php';
/* Khung TRỤC PHÂN TÍCH — nạp TRƯỚC `class-vhcp-don.php` không được: bảng khai trục đọc hai
   hằng `GIAI_DOAN_*` của lớp ấy. Nạp SAU, và chỉ đọc lúc chạy hàm nên thứ tự này là đủ. */
require_once VHCPMTD_DIR . 'includes/class-vhcp-don.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-truc.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-sochi.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-duan.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-mk.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-bp.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-report.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-misa.php';
/* Doanh thu theo cơ sở kéo từ web Doanh thu (plugin khh-doanh-thu) — nạp SAU cfg/don/report vì nó đọc cả ba. */
require_once VHCPMTD_DIR . 'includes/class-vhcp-doanh-thu.php';
/* Chi phí Vending HCMC kéo về thành đơn thật — dùng khoang_thang của lớp Doanh thu nên nạp sau nó. */
require_once VHCPMTD_DIR . 'includes/class-vhcp-vending.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-trama.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-upload.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-nap.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-sheet.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-import.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-gop.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-api.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-app.php';
/* Lớp vỏ app điện thoại (PWA) — gắn vào chính đường của trang, không đẻ đường thứ hai.
   Nạp SAU `class-vhcp-app.php`: nó dựng địa chỉ từ `VHCPMTD_App::cac_slug()`. */
require_once VHCPMTD_DIR . 'includes/class-vhcp-pwa.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-admin.php';
require_once VHCPMTD_DIR . 'includes/class-vhcp-tu-cap-nhat.php';

register_activation_hook( __FILE__, array( 'VHCPMTD_DB', 'install' ) );

/* Tự cập nhật từ GitHub Releases — hiện nút "Cập nhật" ngay ở màn Plugin.
   Anh Thắng 13/09/2026: *"cách kết nối github đẩy thẳng code wed lên"*.
   Xem khối dài ở `VHCPMTD_TuCapNhat`. Chưa khai khoá GitHub thì nó im lặng không làm gì. */
VHCPMTD_TuCapNhat::init();

add_action( 'plugins_loaded', 'vhcpmtd_maybe_upgrade', 20 );   // 20: sau khi plugin Ghế nạp xong lớp VHG_May
function vhcpmtd_maybe_upgrade() {
	if ( get_option( 'vhcpmtd_db_version' ) !== VHCPMTD_DB::SCHEMA_VERSION ) {
		VHCPMTD_DB::install();
	}
	// Lên bản mới có thể thêm trang mới (VD /hop-dong/ của bản 1.16.0). Đường dẫn tĩnh chỉ
	// chạy sau khi WordPress nạp lại bảng rewrite, nên cứ đổi phiên bản là đặt cờ nạp lại —
	// không thì trang mới trả 404 cho tới khi có ai bấm Lưu ở Cài đặt.
	if ( get_option( 'vhcpmtd_ver' ) !== VHCPMTD_VERSION ) {
		update_option( 'vhcpmtd_ver', VHCPMTD_VERSION );
		update_option( 'vhcpmtd_flush_rewrite', 1 );
	}
	/* 🔴 BẢN VÁ PHÂN QUYỀN PHẢI ĐỨNG NGOÀI CHỐT PHIÊN BẢN CSDL.
	   Anh Thắng 28/08/2026 cài b1.50.1 xong vẫn báo *"anh chưa thấy nút duyệt"*. Lý do: bản vá
	   được đặt trong `VHCPMTD_DB::install()`, mà hàm ấy chỉ chạy khi `vhcpmtd_db_version` KHÁC
	   `SCHEMA_VERSION` — bản trước không đổi sơ đồ bảng nên số ấy y nguyên, và bản vá không
	   bao giờ được gọi. Cài đè xong trông như đã sửa mà thực ra chưa chạy một dòng nào.
	   Đặt ở đây thì nó chạy đúng một lần cho MỌI site, dù sơ đồ bảng có đổi hay không —
	   `va_quyen_quyet_toan()` tự giữ cờ, nên lần nạp thứ hai trở đi chỉ tốn một `get_option`. */
	if ( method_exists( 'VHCPMTD_Cfg', 'va_quyen_quyet_toan' ) ) {
		VHCPMTD_Cfg::va_quyen_quyet_toan();
	}
	/* 🔴 DỜI CỘT BẢNG PHÂN QUYỀN VỀ ĐÚNG VAI — cùng lý do đặt ở đây với bản vá ngay trên.
	   Anh Thắng 13/09/2026 gửi ảnh một tài khoản vai Quản lý mất hẳn nút "✔ Duyệt tạm ứng"
	   trong khi nút "↩ Trả lại" vẫn còn. Bản 1.154.0 chèn 'Giám đốc' vào đầu danh sách vai,
	   mà `CH_Quyen` lưu giá trị theo THỨ TỰ CỘT, nên mọi ô của bảng đã lưu trượt sang phải
	   đúng một vai. Xem khối dài ở `VHCPMTD_Cfg::va_cot_quyen_them_vai()`.

	   ⚠️ ĐẶT NGOÀI CHỐT `vhcpmtd_db_version`: bản vá này không đổi sơ đồ bảng nào, nên nằm trong
	      `install()` là không bao giờ chạy — đúng ca 28/08/2026, cài đè xong trông như đã sửa
	      mà chưa chạy một dòng nào.
	   ⚠️ Hàm tự giữ mốc, chạy lại không đổi gì, nên lượt nạp thứ hai trở đi chỉ tốn một lượt
	      đọc meta. */
	if ( method_exists( 'VHCPMTD_Cfg', 'va_cot_quyen_them_vai' ) ) {
		VHCPMTD_Cfg::va_cot_quyen_them_vai();
	}
	/* 🔴 HÚT CƠ SỞ BÊN GHẾ SANG — LƯỢT ĐẦU.
	   Anh Thắng 08/09/2026: *"tự đẩy lấy dữ liệu qua luôn"*. Móc `vhg_coso_da_luu` dưới đây
	   chỉ bắt được cơ sở tạo TỪ BÂY GIỜ; bên ghế thì đã có sẵn hàng chục địa điểm. Không hút
	   một lượt thì mọi đồng chi cho mấy gian ấy rơi về nhà mặc định — số của POSH nằm trong sổ
	   K&H, không ai thấy để sửa.

	   🔴 CHẠY ĐÚNG MỘT LẦN CHO CẢ ĐờI SITE, KHÔNG PHẢI MỖI PHIÊN BẢN — đổi 21/09/2026.

	      Bản cũ so cờ với `VHCPMTD_VERSION`, tức mỗi lần cài bản mới là hút LẠI cả danh mục bên Ghế.
	      Đó chính là câu *"tại sao xóa không được"* của anh Thắng 14/09/2026: xóa 67 gian xong, cài
	      bản sau là chúng về nguyên, không một câu báo nào. Hôm ấy chữa bằng cách TẮT hẳn đường
	      hút; nay anh xin bật lại (*"đẩy cơ sở bên ghế sang nhé"*) nên phải chữa đúng chỗ gốc:
	      hút một lần để mồi, sau đó XÓA LÀ Ở YÊN.

	      ⚠️ Muốn hút lại thì bấm nút 🚛 Hút cơ sở từ Ghế ở màn Cấu hình — một cú bấm có chủ,
	         thay cho một lượt chạy ngầm không ai biết. Đó cũng là lý do cái nút ấy có mặt.
	   ⚠️ VẪN không chạy ở mọi lượt tải trang: hàm hút quét cả danh mục cơ sở cho từng dòng bên
	      ghế, làm ở mọi lượt tải là một khoản phí vô ích trên trang nào cũng phải trả.
	   ⚠️ ĐẶT SAU `plugins_loaded` của bên ghế bằng cách gọi ở ưu tiên muộn — lúc này lớp
	      `VHG_May` mới chắc chắn đã nạp. Chưa cài plugin ghế thì hàm tự trả 0. */
	if ( ! get_option( 'vhcpmtd_hut_coso_ghe' ) ) {
		update_option( 'vhcpmtd_hut_coso_ghe', '1' );
		VHCPMTD_Cfg::hut_coso_ghe();
	}
}

/* 🔴 CƠ SỞ MỚI BÊN GHẾ -> VÀO THẲNG DANH MỤC CƠ SỞ CỦA CHI PHÍ, gắn sẵn đơn vị POSH.
   Nghe bằng móc chứ không để bên ghế gọi thẳng vào đây: hai plugin cài độc lập, gỡ cái nào
   thì cái kia vẫn phải chạy. Xem `VHCPMTD_Cfg::nhan_coso_ngoai()` — nó CHỈ THÊM, không sửa
   không xoá, nên nghe nhiều lượt cùng một tên cũng không sinh dòng thứ hai. */
add_action( 'vhg_coso_da_luu', array( 'VHCPMTD_Cfg', 'moc_coso_ghe' ) );

/**
 * Nạp lại bảng đường dẫn — chạy ở ưu tiên muộn để CẢ HAI trang đã khai đường dẫn xong.
 * Nếu nạp lại sớm hơn thì đường dẫn khai sau bị bỏ khỏi bảng và trả 404.
 */
function vhcpmtd_flush_rewrite() {
	if ( ! get_option( 'vhcpmtd_flush_rewrite' ) ) { return; }
	delete_option( 'vhcpmtd_flush_rewrite' );
	flush_rewrite_rules( false );
}

add_action( 'rest_api_init', array( 'VHCPMTD_API', 'register_routes' ) );
// Điểm nhận chi phí web Vending HCMC đẩy sang (máy chủ khác, gác bằng khoá chung) — xem VHCPMTD_Vending::routes()
add_action( 'rest_api_init', array( 'VHCPMTD_Vending', 'routes' ) );
// Cổng dự phòng: hosting nào chặn /wp-json/ thì giao diện tự chuyển sang admin-ajax.php
add_action( 'wp_ajax_vhcpmtd_call', array( 'VHCPMTD_API', 'ajax' ) );
add_action( 'wp_ajax_nopriv_vhcpmtd_call', array( 'VHCPMTD_API', 'ajax' ) );
add_action( 'init', array( 'VHCPMTD_App', 'init' ), 5 );
/* ⚠️ CÙNG ƯU TIÊN 5, VÀ ĐẶT NGAY SAU. Hai bên cùng khai luật đường dẫn, mà lượt nạp lại bảng
   luật (`vhcpmtd_flush_rewrite`, ưu tiên 99) phải thấy ĐỦ cả hai — khai muộn hơn 99 là luật của
   manifest/sw không vào bảng, và app báo "manifest không đọc được" mà không nói vì sao. */
add_action( 'init', array( 'VHCPMTD_Pwa', 'init' ), 5 );
add_action( 'init', 'vhcpmtd_flush_rewrite', 99 );
add_action( 'admin_menu', array( 'VHCPMTD_Admin', 'menu' ) );
add_action( 'admin_init', array( 'VHCPMTD_Admin', 'handle_post' ) );
add_shortcode( 'vhcpmtd_app', array( 'VHCPMTD_App', 'shortcode' ) );
