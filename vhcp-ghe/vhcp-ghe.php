<?php
/**
 * Plugin Name:       Ghế Massage (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Hệ thống ghế massage QR chạy THẲNG trên host: nhận webhook tiền vào, ghi doanh thu, cho ghế chạy, đối soát theo cơ sở/máy. Không Firebase, không Apps Script.
 * Version:           2.92.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhg
 *
 * ---------------------------------------------------------------------------
 * BẢN GỐC LÀ APPS SCRIPT + FIREBASE. BẢN NÀY BỎ CẢ HAI.
 *
 * Luồng cũ:  ngân hàng -> webhook Apps Script -> Firebase /ghe/pay -> ESP32 thấy -> chạy ghế
 * Luồng nay: ngân hàng -> /ghe-tien trên chính website -> MySQL -> ESP32 hỏi /ghe-may -> chạy
 *
 * 🔴 HAI HẰNG PHẢI KHAI TRONG `wp-config.php` — KHÔNG khai thì cổng ĐÓNG, không phải mở:
 *
 *     define( 'VHG_KHOA_WEBHOOK', '…chuỗi dài ngẫu nhiên…' );   // dán vào ô webhook của bên gửi
 *     define( 'VHG_KHOA_MAY',     '…chuỗi dài ngẫu nhiên KHÁC…' ); // nạp vào ESP32 của ghế
 *
 * Hai khoá KHÁC NHAU, cố ý: khoá webhook đi trên đường dẫn (bên gửi không cho đặt header) nên
 * coi như lộ một phần; khoá máy đi trong header/thân. Dùng chung một chuỗi là lộ cái này kéo
 * theo cái kia.
 *
 * ⚠️ ĐỪNG để PIN hay khoá nào trong mã nguồn. Repo này CÔNG KHAI. Bản Apps Script cũ có
 *    `DASHBOARD_PIN = '246810'` ghi thẳng trong mã — ai đọc được mã là bật/tắt được ghế và xoá
 *    được doanh thu. Ở đây màn hình nằm trong wp-admin nên người xem phải đăng nhập WordPress;
 *    không có PIN thứ hai để lộ.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHG_VERSION', '2.92.0' );
define( 'VHG_FILE', __FILE__ );
define( 'VHG_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHG_URL', plugin_dir_url( __FILE__ ) );

require_once VHG_DIR . 'includes/class-vhg-db.php';
require_once VHG_DIR . 'includes/class-vhg-doc.php';
require_once VHG_DIR . 'includes/class-vhg-may.php';
require_once VHG_DIR . 'includes/class-vhg-thu.php';
require_once VHG_DIR . 'includes/class-vhg-qr.php';
require_once VHG_DIR . 'includes/class-vhg-ma.php';
/* Ví phải nạp SAU class-vhg-ma.php: VHG_Vi gọi VHG_Ma::sdt_sach/bam_pin/... ngay từ
   những hàm đầu tiên. Nạp trước là lỗi "class not found" ở đúng đường tiền vào. */
require_once VHG_DIR . 'includes/class-vhg-vi.php';
/* Quỹ tiền mặt phải nạp SAU class-vhg-thu.php và class-vhg-may.php: VHG_Quy đọc thẳng bảng
   `thu` qua hằng của VHG_Thu, và hỏi VHG_May xem ghế có thật không. */
require_once VHG_DIR . 'includes/class-vhg-quy.php';
/* Báo cáo doanh thu theo cơ sở (port app Apps Script "thu tiền"). Nạp SAU class-vhg-quy.php và
   class-vhg-may.php: VHG_BaoCao dùng VHG_Quy::don_vi() và VHG_May::ds_may(), và đọc chung bảng
   `chot` để lấy chỉ số trước. */
require_once VHG_DIR . 'includes/class-vhg-baocao.php';
/* Trang kế toán (duyệt báo cáo, đối chiếu, công nợ, MISA…). Nạp SAU class-vhg-baocao.php và
   class-vhg-quy.php: VHG_KeToan dùng lại VHG_BaoCao::squash/ngay_/chi_so_truoc và VHG_Quy::don_vi. */
require_once VHG_DIR . 'includes/class-vhg-ketoan.php';
/* Sổ tay Hotline (hỗ trợ khách / kích ghế từ xa). Nạp SAU class-vhg-baocao.php và class-vhg-may.php:
   VHG_Hotline dùng VHG_BaoCao::squash() để so khớp tên cơ sở, và tab của nó đối chiếu với
   VHG_May::dem_luot_kich_coso_ngay(). */
require_once VHG_DIR . 'includes/class-vhg-hotline.php';
require_once VHG_DIR . 'includes/class-vhg-chan.php';
require_once VHG_DIR . 'includes/class-vhg-qrve.php';
require_once VHG_DIR . 'includes/class-vhg-nhap.php';
require_once VHG_DIR . 'includes/class-vhg-cong.php';
require_once VHG_DIR . 'includes/class-vhg-auth.php';
require_once VHG_DIR . 'includes/class-vhg-trang.php';
require_once VHG_DIR . 'includes/class-vhg-shop.php';
require_once VHG_DIR . 'includes/class-vhg-fw.php';
require_once VHG_DIR . 'includes/class-vhg-admin.php';
/* Tự cập nhật từ GitHub — hiện nút "Cập nhật" ở màn Plugin, CHỈ nâng bản, không lùi. */
require_once VHG_DIR . 'includes/class-vhg-tu-cap-nhat.php';
VHG_TuCapNhat::init();

register_activation_hook( __FILE__, array( 'VHG_DB', 'install' ) );

add_action( 'plugins_loaded', 'vhg_maybe_upgrade' );
function vhg_maybe_upgrade() {
	if ( get_option( 'vhg_ver' ) !== VHG_VERSION ) {
		VHG_DB::install();
		update_option( 'vhg_ver', VHG_VERSION );
		update_option( 'vhg_flush_rewrite', 1 );
		/* Vừa lên bản mới thì quên kết quả hỏi GitHub cũ đi, để lần sau hỏi lại từ đầu (khỏi
		   còn báo "có bản mới" cho bản mình vừa cài xong). */
		if ( class_exists( 'VHG_TuCapNhat' ) ) { VHG_TuCapNhat::quen_nho(); }
		/* Vừa lên bản mới → đuổi bytecode CŨ của mọi tệp plugin ra khỏi opcache. Xem vhg_xoa_opcache(). */
		vhg_xoa_opcache();
	}
}

/* 🔴 ĐUỔI MÃ CŨ KHỎI BỘ ĐỆM BYTECODE (opcache) SAU KHI CÀI BẢN MỚI — anh Thắng 15/09/2026.
   Triệu chứng: góc màn in đúng số bản mới (VHG_VERSION đọc từ vhcp-ghe.php), mà một tệp lớp
   (class-vhg-baocao.php) vẫn chạy hành vi của bản CŨ — bốn bản vá liên tiếp "không ăn" dù zip
   đúng. Trên hosting dùng chung, PHP hay giữ bytecode đã biên dịch và không soát lại mtime
   (validate_timestamps tắt / revalidate thưa), nên tệp mới nằm trên đĩa mà PHP vẫn chạy bản cũ
   trong RAM; WordPress khi cài zip có gọi wp_opcache_invalidate() nhưng nhiều host chặn API đó.
   Ở đây tự làm, có gác: thiếu hàm hay bị chặn (restrict_api) thì lệnh vô hại, không đổ lỗi.
   Gọi ở HAI mốc: (1) vhg_maybe_upgrade — ngay lượt tải đầu sau khi số bản đổi; (2) móc
   upgrader_process_complete — ngay khi WordPress cài/nâng xong plugin này, kể cả qua tự cập nhật. */
function vhg_xoa_opcache() {
	if ( ! function_exists( 'opcache_invalidate' ) ) { return; }
	$goc = rtrim( VHG_DIR, '/\\' );
	$ds  = array( $goc . '/vhcp-ghe.php' );
	foreach ( (array) glob( $goc . '/includes/*.php' ) as $f ) { $ds[] = $f; }
	foreach ( $ds as $f ) {
		if ( is_file( $f ) ) { @opcache_invalidate( $f, true ); }
	}
}
add_action( 'upgrader_process_complete', 'vhg_moc_nang_cap_xong', 10, 2 );
function vhg_moc_nang_cap_xong( $nang, $tuy ) {
	if ( ! is_array( $tuy ) || ( isset( $tuy['type'] ) && 'plugin' !== $tuy['type'] ) ) { return; }
	vhg_xoa_opcache();
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 TỰ SOÁT "TỆP LỚP CŨ CÒN SỐNG" — anh Thắng 15/09/2026.
 * Đo được trên host thật (2.91.0): góc màn in đúng số bản mới — hằng VHG_VERSION nằm ở CHÍNH
 * TỆP NÀY nên tệp này chắc chắn mới; class-vhg-trang.php cũng mới (ô đỏ 2.91.0 vẽ ra được);
 * nhưng lớp VHG_BaoCao chạy hành vi bản cũ (không có const BAN → boot() không trả banBc lẫn
 * chanDoan). Bốn bản vá đúng liên tiếp "không ăn" là vì thế: sửa đúng chỗ, mà chỗ ấy không
 * được chạy. Không có gác nào trong mã bắt được chuyện này — chỉ một phép so vân tay mới bắt.
 *
 * Hai lớp có thể "cũ", phân biệt bằng MỘT phép đọc tệp trên đĩa (file_get_contents đi thẳng
 * vào đĩa, không qua opcache):
 *   · đĩa đã có BAN mới ⇒ 'opcache': PHP giữ bytecode cũ (host tắt soát mtime / soát thưa).
 *     Chữa: đuổi tệp khỏi opcache (+ opcache_reset nếu được); lượt tải SAU biên dịch lại từ đĩa.
 *   · đĩa vẫn cũ ⇒ 'dia': lượt cài không ghi đè được tệp (quyền ghi / chủ sở hữu / cài dở).
 *     Đuổi opcache vô ích; phải cài lại hoặc sửa quyền. Nói thẳng ra để khỏi đi vòng thêm.
 *
 * ⚠️ defined('VHG_BaoCao::BAN'), KHÔNG chạm thẳng VHG_BaoCao::BAN: trên chính lớp cũ đang nghi,
 *    hằng ấy chưa tồn tại — gọi thẳng là fatal đúng lượt cần chẩn đoán.
 * ⚠️ Móc plugins_loaded ưu tiên 1: sau mọi require_once (lớp đã định nghĩa), TRƯỚC
 *    vhg_maybe_upgrade (10). Kết luận ghi option `vhg_tep_lech` cho admin_notices đọc; KHỚP THÌ
 *    XOÁ option — không để thông báo treo mãi sau khi đã chữa xong.
 * ⚠️ Số bản nay khai BA chỗ: header Version, VHG_VERSION, VHG_BaoCao::BAN. Bài
 *    tools/test/kiem-ghe-ban-baocao.php canh ba chỗ bằng nhau — quên tăng BAN là bộ soát này
 *    báo đỏ trên host dù mã đúng: lỗi ở bộ canh, đổ tội cho đúng thứ mình canh.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
add_action( 'plugins_loaded', 'vhg_soat_tep_lop', 1 );
function vhg_soat_tep_lop() {
	$chay = defined( 'VHG_BaoCao::BAN' ) ? (string) VHG_BaoCao::BAN : '';
	if ( $chay === VHG_VERSION ) { delete_option( 'vhg_tep_lech' ); return; }
	$tep = VHG_DIR . 'includes/class-vhg-baocao.php';
	$dia = '';
	$src = @file_get_contents( $tep );
	if ( is_string( $src ) && preg_match( "/const BAN = '([0-9][0-9.]*)'/", $src, $m ) ) { $dia = $m[1]; }
	$loai = ( $dia === VHG_VERSION ) ? 'opcache' : 'dia';
	if ( function_exists( 'opcache_invalidate' ) ) { @opcache_invalidate( $tep, true ); }
	if ( 'opcache' === $loai && function_exists( 'opcache_reset' ) ) { @opcache_reset(); }
	update_option( 'vhg_tep_lech', array(
		'tep'  => 'includes/class-vhg-baocao.php',
		'chay' => '' !== $chay ? $chay : '(không có BAN — bản trước 2.91.0)',
		'dia'  => '' !== $dia ? $dia : '(không đọc được / không có BAN)',
		'loai' => $loai,
		'luc'  => current_time( 'mysql' ),
	), false );
}
add_action( 'admin_notices', 'vhg_bao_tep_lech' );
function vhg_bao_tep_lech() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$l = get_option( 'vhg_tep_lech' );
	if ( ! is_array( $l ) || empty( $l['loai'] ) ) { return; }
	$opc = ( 'opcache' === $l['loai'] );
	$cach = $opc
		? 'Đĩa đã đúng bản <code>' . esc_html( VHG_VERSION ) . '</code>, PHP còn giữ bản cũ trong bộ đệm. Plugin đã yêu cầu làm mới — <b>tải lại trang này</b>. Nếu thông báo còn: nhờ hosting <b>khởi động lại PHP / xoá opcache</b> (hoặc trong cPanel đổi phiên bản PHP qua rồi đổi lại).'
		: 'Tệp trên đĩa vẫn là bản cũ — lượt cài vừa rồi <b>không ghi đè được tệp này</b> (quyền ghi / chủ sở hữu / cài dở). Cài lại zip; nếu vẫn vậy, kiểm tra quyền ghi thư mục <code>wp-content/plugins/vhcp-ghe/includes/</code>.';
	echo '<div class="notice notice-error"><p><b>Ghế Massage: tệp báo cáo đang chạy KHÁC bản đã cài.</b><br>'
		. 'Bản cài: <code>' . esc_html( VHG_VERSION ) . '</code>'
		. ' · lớp đang chạy: <code>' . esc_html( (string) $l['chay'] ) . '</code>'
		. ' · tệp trên đĩa: <code>' . esc_html( (string) $l['dia'] ) . '</code>'
		. ' · kết luận: <b>' . ( $opc ? 'bộ đệm PHP giữ bản cũ' : 'tệp trên đĩa chưa được thay' ) . '</b>'
		. ' (soát lúc ' . esc_html( (string) $l['luc'] ) . ').<br>' . $cach . '</p></div>';
}

/* 🔴 CƠ SỞ ĐƠN VỊ POSH BÊN CHI PHÍ -> VÀO THẲNG DANH MỤC CƠ SỞ CỦA GHẾ.
   Anh Thắng 09/09/2026: *"chỉ đẩy sang nếu nó là đơn vị posh thôi"*. Việc lọc đơn vị nằm BÊN
   CHI PHÍ vì chỉ bên ấy biết cơ sở nào thuộc đơn vị nào — bên này chỉ nhận cái tên đã lọc.

   Nghe bằng móc chứ không để bên kia gọi thẳng vào đây: hai plugin cài rời nhau, gỡ cái nào thì
   cái kia vẫn phải chạy. Chưa cài plugin chi phí thì không ai phát, dòng này nằm im.

   Xem `VHG_May::moc_coso_chi_phi()` — nó CHỈ THÊM, và có cờ chặn để cái tên vừa nhận không bị
   báo ngược trở lại thành một vòng qua lại. */
add_action( 'vhcp_coso_posh_da_luu', array( 'VHG_May', 'moc_coso_chi_phi' ) );

/* Cổng gài SỚM (ưu tiên 4) — trước lượt nạp lại luật đường dẫn (99). Đường của tiền là đường
   mà một lượt bị chuyển hướng đồng nghĩa MẤT doanh thu; xem class-vhg-cong.php. */
add_action( 'init', array( 'VHG_Cong', 'init' ), 4 );
/* Trang ngoài gài cùng ưu tiên 4: nó cũng khai luật đường dẫn, nên phải xong TRƯỚC lượt nạp
   lại ở 99. Gài sau 99 thì luật vừa khai chưa nằm trong bản đã nạp — trang trả 404 cho tới
   lần lưu Permalinks kế tiếp, mà không ai nghĩ tới việc đi lưu một trang mình không sửa. */
add_action( 'init', array( 'VHG_Trang', 'init' ), 4 );
/* Trang bán mã cho khách — cũng khai luật đường dẫn, nên cũng phải gài ở ưu tiên 4.
   🔴 Quên dòng này là `/mua-ma` trả 404, mà KHÔNG có gì báo lỗi ở đâu cả: lớp vẫn nạp, hàm vẫn
      gọi được từ phép thử, chỉ là WordPress không bao giờ hỏi tới nó. Đúng chuyện đã xảy ra
      23/08/2026. Phép thử `kiem_gai_trang` bên dưới canh chỗ này. */
add_action( 'init', array( 'VHG_Shop', 'init' ), 4 );
add_action( 'init', 'vhg_flush_rewrite', 99 );
function vhg_flush_rewrite() {
	if ( get_option( 'vhg_flush_rewrite' ) ) {
		flush_rewrite_rules( false );
		delete_option( 'vhg_flush_rewrite' );
	}
}

add_action( 'admin_menu', array( 'VHG_Admin', 'menu' ) );
