<?php
/**
 * Plugin Name: VHCP Chấm công (dựng mới)
 * Description: Chấm công dựng lại từ đầu — nạp CSV xuất thẳng từ Google Sheets, mọi thao tác nằm NGOÀI trang quản trị.
 * Version: 1.0.0
 * Text Domain: vhcp-cong
 *
 * VÌ SAO CÓ PLUGIN NÀY THAY VÌ SỬA BẢN CŨ
 * Anh Thắng chốt 25/08/2026: viết lại toàn bộ, cột lấy đúng như Sheet, nạp bằng CSV, và mọi
 * thao tác nằm ngoài web chứ không dùng trang quản trị WordPress.
 *
 * 🔴 CHẠY SONG SONG với `vhcp-cham-cong` cũ, KHÔNG thay thế nó. Bảng riêng (`cong_*`), đường
 *    riêng (`/cong`), tiền tố hàm riêng (`VCG_`). Bản cũ vẫn chạy trong lúc bản này chưa xong —
 *    tắt bản cũ trước khi bản mới đứng vững là cả cửa hàng mất chấm công trong một buổi.
 *
 * @package vhcp-cong
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VCG_PHIEN_BAN', '1.0.0' );
define( 'VCG_DUONG_DAN', plugin_dir_path( __FILE__ ) );

require_once VCG_DUONG_DAN . 'includes/class-vcg-db.php';
require_once VCG_DUONG_DAN . 'includes/class-vcg-quyen.php';
require_once VCG_DUONG_DAN . 'includes/class-vcg-nap.php';
require_once VCG_DUONG_DAN . 'includes/class-vcg-nhap.php';
require_once VCG_DUONG_DAN . 'includes/class-vcg-nguoi.php';
require_once VCG_DUONG_DAN . 'includes/class-vcg-trang.php';

register_activation_hook( __FILE__, array( 'VCG_Boot', 'bat' ) );

class VCG_Boot {

	/** Dựng bảng rồi nạp lại luật đường dẫn. */
	public static function bat() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		foreach ( VCG_DB::lenh_tao( $charset ) as $sql ) { dbDelta( $sql ); }
		update_option( 'vcg_phien_ban_db', VCG_DB::PHIEN_BAN, false );

		/* ⚠️ PHẢI nạp lại luật đường dẫn khi bật plugin. Thiếu bước này thì `/cong` trả 404 và
		   người ta tưởng plugin hỏng, trong khi mã hoàn toàn đúng — chỉ là WordPress chưa biết
		   đường mới. Đây là kiểu lỗi ngốn cả buổi để tìm ra vì nó trông như lỗi nghiêm trọng. */
		VCG_Trang::luat();
		flush_rewrite_rules();
	}
}

VCG_Trang::init();
