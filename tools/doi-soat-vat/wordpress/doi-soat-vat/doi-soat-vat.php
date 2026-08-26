<?php
/**
 * Plugin Name:       Đối soát VAT
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Gộp sao kê QR VietQR, Payoo, VNPay và Zalo Mini App, quy về điểm xuất hoá đơn, tách VAT rồi xuất file Excel để nhập Misa. Sao kê được xử lý ngay trong trình duyệt, không tải lên máy chủ.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * License:           MIT
 * Text Domain:       doi-soat-vat
 *
 * Công cụ chạy hoàn toàn bằng JavaScript trong trình duyệt. Plugin này chỉ làm
 * hai việc: thêm một mục vào menu quản trị, và nhúng trang công cụ vào đó.
 * Không có endpoint nào nhận dữ liệu, không ghi gì vào cơ sở dữ liệu.
 */

// Chặn truy cập thẳng vào file.
if (!defined('ABSPATH')) {
    exit;
}

const DSVAT_SLUG = 'doi-soat-vat';

/** Quyền cần có để mở công cụ. Sao kê là dữ liệu kế toán nên để mức quản trị. */
const DSVAT_CAPABILITY = 'manage_options';

/** Đường dẫn tới trang công cụ (file tĩnh nằm trong chính thư mục plugin). */
function dsvat_app_url(): string
{
    return plugins_url('web/index.html', __FILE__);
}

add_action('admin_menu', 'dsvat_dang_ky_menu');

function dsvat_dang_ky_menu(): void
{
    add_menu_page(
        __('Đối soát VAT', 'doi-soat-vat'),
        __('Đối soát VAT', 'doi-soat-vat'),
        DSVAT_CAPABILITY,
        DSVAT_SLUG,
        'dsvat_hien_trang',
        'dashicons-media-spreadsheet',
        58
    );
}

/**
 * Trang quản trị.
 *
 * Công cụ được nhúng bằng iframe thay vì in thẳng ra trang: giao diện của nó có
 * bộ CSS riêng, in thẳng vào trang quản trị thì hai bên đè nhau. iframe cùng
 * nguồn nên Web Worker và việc tải file vẫn chạy bình thường.
 */
function dsvat_hien_trang(): void
{
    if (!current_user_can(DSVAT_CAPABILITY)) {
        wp_die(esc_html__('Bạn không có quyền mở trang này.', 'doi-soat-vat'));
    }

    $url = dsvat_app_url();
    ?>
    <div class="wrap dsvat-wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Đối soát VAT', 'doi-soat-vat'); ?></h1>
        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="page-title-action">
            <?php esc_html_e('Mở ở tab mới', 'doi-soat-vat'); ?>
        </a>

        <p class="description dsvat-note">
            <?php esc_html_e('Sao kê được xử lý ngay trong trình duyệt của bạn — không có file nào được tải lên máy chủ.', 'doi-soat-vat'); ?>
        </p>

        <iframe
            id="dsvat-frame"
            src="<?php echo esc_url($url); ?>"
            title="<?php esc_attr_e('Công cụ đối soát VAT', 'doi-soat-vat'); ?>"
            loading="lazy"></iframe>
    </div>

    <style>
        .dsvat-wrap .dsvat-note { margin: 8px 0 12px; }
        #dsvat-frame {
            display: block;
            width: 100%;
            /* Trừ đi thanh quản trị, tiêu đề và lề dưới để iframe vừa đúng màn hình. */
            height: calc(100vh - 190px);
            min-height: 620px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            background: #fff;
        }
        @media screen and (max-width: 782px) {
            #dsvat-frame { height: calc(100vh - 240px); }
        }
    </style>
    <?php
}

/**
 * Nhắc một lần sau khi kích hoạt, kèm đường dẫn tới công cụ.
 */
add_action('activated_plugin', 'dsvat_danh_dau_vua_kich_hoat');

function dsvat_danh_dau_vua_kich_hoat(string $plugin): void
{
    if ($plugin === plugin_basename(__FILE__)) {
        set_transient('dsvat_vua_kich_hoat', 1, 60);
    }
}

add_action('admin_notices', 'dsvat_thong_bao_kich_hoat');

function dsvat_thong_bao_kich_hoat(): void
{
    if (!get_transient('dsvat_vua_kich_hoat') || !current_user_can(DSVAT_CAPABILITY)) {
        return;
    }
    delete_transient('dsvat_vua_kich_hoat');
    $link = admin_url('admin.php?page=' . DSVAT_SLUG);
    ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <?php esc_html_e('Đã bật Đối soát VAT.', 'doi-soat-vat'); ?>
            <a href="<?php echo esc_url($link); ?>"><?php esc_html_e('Mở công cụ', 'doi-soat-vat'); ?></a>
        </p>
    </div>
    <?php
}

/** Thêm lối tắt ngay ở danh sách plugin. */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'dsvat_lien_ket_nhanh');

function dsvat_lien_ket_nhanh(array $links): array
{
    $mo = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('admin.php?page=' . DSVAT_SLUG)),
        esc_html__('Mở công cụ', 'doi-soat-vat')
    );
    array_unshift($links, $mo);
    return $links;
}
