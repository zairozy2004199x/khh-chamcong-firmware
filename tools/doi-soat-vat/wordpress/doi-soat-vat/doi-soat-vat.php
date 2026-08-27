<?php
/**
 * Plugin Name:       Đối soát VAT
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Gộp sao kê QR VietQR, Payoo, VNPay, Zalo Mini App và MoMo, quy về điểm xuất hoá đơn, tách VAT rồi xuất file Excel để nhập Misa. Sao kê được xử lý ngay trong trình duyệt, không tải lên máy chủ.
 * Version:           1.3.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * License:           MIT
 * Text Domain:       doi-soat-vat
 *
 * Công cụ chạy hoàn toàn bằng JavaScript trong trình duyệt. Plugin này chỉ làm
 * ba việc: thêm một mục vào menu quản trị, mở một địa chỉ web riêng cho công cụ,
 * và nhớ xem địa chỉ đó có cho người ngoài vào hay không.
 * Không có endpoint nào nhận dữ liệu, không tạo bảng nào trong cơ sở dữ liệu.
 */

// Chặn truy cập thẳng vào file.
if (!defined('ABSPATH')) {
    exit;
}

const DSVAT_SLUG = 'doi-soat-vat';

/**
 * Phiên bản plugin, phải trùng số ghi ở đầu file và trùng chuỗi ?v= trong
 * web/index.html. Dùng để buộc trình duyệt tải lại giao diện sau khi cập nhật:
 * không có nó thì người dùng cập nhật xong vẫn thấy bản cũ trong bộ đệm.
 * dong-goi.sh kiểm tra ba chỗ này khớp nhau lúc đóng gói.
 */
const DSVAT_VERSION = '1.3.0';

/** Quyền cần có để mở công cụ trong trang quản trị. */
const DSVAT_CAPABILITY = 'manage_options';

/** Tên tuỳ chọn lưu việc có cho người chưa đăng nhập vào địa chỉ công khai. */
const DSVAT_OPTION_CONG_KHAI = 'dsvat_cong_khai';

/** Biến truy vấn nhận diện địa chỉ công khai. */
const DSVAT_QUERY_VAR = 'dsvat_trang';

/** Đường dẫn thư mục chứa giao diện công cụ (kết thúc bằng dấu /). */
function dsvat_base_url(): string
{
    return plugins_url('web/', __FILE__);
}

/**
 * Địa chỉ file giao diện, dùng cho iframe trong trang quản trị.
 *
 * Kèm ?v= để trình duyệt không dùng lại bản cũ sau khi cập nhật plugin. Trỏ thẳng
 * vào file tĩnh chứ không qua địa chỉ web gọn, để iframe vẫn chạy khi bảng đường
 * dẫn của WordPress chưa được dựng lại.
 */
function dsvat_app_url(): string
{
    return dsvat_base_url() . 'index.html?v=' . DSVAT_VERSION;
}

/** Địa chỉ web gọn cho công cụ, ví dụ https://tenmien.com/doi-soat-vat/ */
function dsvat_public_url(): string
{
    return home_url('/' . DSVAT_SLUG . '/');
}

function dsvat_cho_cong_khai(): bool
{
    return (bool) get_option(DSVAT_OPTION_CONG_KHAI, false);
}

/* -------------------------------------------------------------------------
 * Địa chỉ web công khai
 * ---------------------------------------------------------------------- */

add_action('init', 'dsvat_them_duong_dan');

function dsvat_them_duong_dan(): void
{
    add_rewrite_rule('^' . DSVAT_SLUG . '/?$', 'index.php?' . DSVAT_QUERY_VAR . '=1', 'top');
}

add_filter('query_vars', 'dsvat_khai_query_var');

function dsvat_khai_query_var(array $vars): array
{
    $vars[] = DSVAT_QUERY_VAR;
    return $vars;
}

add_action('template_redirect', 'dsvat_phuc_vu_trang_cong_khai');

/**
 * Phục vụ công cụ ở địa chỉ web gọn.
 *
 * Trang được in ra từ chính file web/index.html, chỉ thêm một thẻ <base> để mọi
 * đường dẫn tương đối (CSS, JS, Web Worker) trỏ về thư mục plugin. Nhờ vậy giao
 * diện chỉ có một bản duy nhất, không phải chép lại vào PHP.
 */
function dsvat_phuc_vu_trang_cong_khai(): void
{
    if (!get_query_var(DSVAT_QUERY_VAR)) {
        return;
    }

    if (!dsvat_cho_cong_khai() && !is_user_logged_in()) {
        auth_redirect();
        exit;
    }

    $duong_dan = plugin_dir_path(__FILE__) . 'web/index.html';
    $html = is_readable($duong_dan) ? file_get_contents($duong_dan) : false;

    if ($html === false) {
        status_header(500);
        nocache_headers();
        wp_die(
            esc_html__('Không đọc được giao diện công cụ. Hãy cài lại plugin.', 'doi-soat-vat'),
            esc_html__('Đối soát VAT', 'doi-soat-vat'),
            ['response' => 500]
        );
    }

    $the_base = '<base href="' . esc_url(dsvat_base_url()) . '">';
    // Chèn ngay sau <head> để <base> đứng trước mọi thẻ có đường dẫn tương đối.
    $html = preg_replace('/<head\b[^>]*>/i', '$0' . "\n" . $the_base, $html, 1);

    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- file tĩnh của chính plugin
    exit;
}

/* -------------------------------------------------------------------------
 * Kích hoạt / vô hiệu hoá
 * ---------------------------------------------------------------------- */

register_activation_hook(__FILE__, 'dsvat_khi_kich_hoat');

function dsvat_khi_kich_hoat(): void
{
    dsvat_them_duong_dan();
    // Đường dẫn mới chỉ có tác dụng sau khi WordPress dựng lại bảng đường dẫn.
    flush_rewrite_rules();
    set_transient('dsvat_vua_kich_hoat', 1, 60);
}

register_deactivation_hook(__FILE__, 'dsvat_khi_vo_hieu_hoa');

function dsvat_khi_vo_hieu_hoa(): void
{
    flush_rewrite_rules();
}

/* -------------------------------------------------------------------------
 * Trang quản trị
 * ---------------------------------------------------------------------- */

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

    dsvat_xu_ly_luu_cai_dat();

    $url_cong_khai = dsvat_public_url();
    $cong_khai = dsvat_cho_cong_khai();
    ?>
    <div class="wrap dsvat-wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Đối soát VAT', 'doi-soat-vat'); ?></h1>
        <a href="<?php echo esc_url($url_cong_khai); ?>" target="_blank" rel="noopener" class="page-title-action">
            <?php esc_html_e('Mở ở tab mới', 'doi-soat-vat'); ?>
        </a>

        <div class="dsvat-link">
            <p>
                <strong><?php esc_html_e('Địa chỉ web của công cụ', 'doi-soat-vat'); ?></strong>
            </p>
            <p class="dsvat-url-row">
                <input type="text" id="dsvat-url" readonly
                       value="<?php echo esc_attr($url_cong_khai); ?>"
                       onclick="this.select()">
                <button type="button" class="button" id="dsvat-copy">
                    <?php esc_html_e('Sao chép', 'doi-soat-vat'); ?>
                </button>
            </p>

            <form method="post">
                <?php wp_nonce_field('dsvat_luu_cai_dat', 'dsvat_nonce'); ?>
                <label>
                    <input type="checkbox" name="dsvat_cong_khai" value="1" <?php checked($cong_khai); ?>>
                    <?php esc_html_e('Cho người chưa đăng nhập dùng địa chỉ này', 'doi-soat-vat'); ?>
                </label>
                <p class="description">
                    <?php if ($cong_khai) : ?>
                        <?php esc_html_e('Đang mở: ai có đường dẫn cũng dùng được công cụ. Sao kê vẫn không rời khỏi máy người dùng, nhưng công cụ thì không giới hạn ai.', 'doi-soat-vat'); ?>
                    <?php else : ?>
                        <?php esc_html_e('Đang khoá: phải đăng nhập WordPress mới mở được. Bỏ tick này nếu muốn gửi đường dẫn cho người ngoài.', 'doi-soat-vat'); ?>
                    <?php endif; ?>
                </p>
                <?php submit_button(__('Lưu', 'doi-soat-vat'), 'secondary', 'dsvat_luu', false); ?>
            </form>

            <p class="description">
                <?php esc_html_e('Nếu địa chỉ trên báo 404, vào Cài đặt → Đường dẫn tĩnh và bấm Lưu một lần để WordPress dựng lại bảng đường dẫn. Đường dẫn dự phòng:', 'doi-soat-vat'); ?>
                <a href="<?php echo esc_url(dsvat_app_url()); ?>" target="_blank" rel="noopener"><code><?php echo esc_html(dsvat_app_url()); ?></code></a>
            </p>
        </div>

        <p class="description dsvat-note">
            <?php esc_html_e('Sao kê được xử lý ngay trong trình duyệt của bạn — không có file nào được tải lên máy chủ.', 'doi-soat-vat'); ?>
            <?php printf(
                /* translators: %s là số phiên bản plugin */
                esc_html__('Phiên bản %s — nhận sao kê QR VietQR, Payoo, VNPay, Zalo Mini App, MoMo.', 'doi-soat-vat'),
                '<strong>' . esc_html(DSVAT_VERSION) . '</strong>'
            ); ?>
        </p>

        <iframe
            id="dsvat-frame"
            src="<?php echo esc_url(dsvat_app_url()); ?>"
            title="<?php esc_attr_e('Công cụ đối soát VAT', 'doi-soat-vat'); ?>"
            loading="lazy"></iframe>
    </div>

    <style>
        .dsvat-wrap .dsvat-note { margin: 8px 0 12px; }
        .dsvat-link {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 12px 16px;
            margin: 16px 0 4px;
            max-width: 720px;
        }
        .dsvat-link p { margin: 6px 0; }
        .dsvat-url-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .dsvat-url-row input {
            flex: 1 1 320px;
            font-family: Consolas, Monaco, monospace;
            padding: 5px 8px;
        }
        #dsvat-frame {
            display: block;
            width: 100%;
            /* Trừ đi thanh quản trị, tiêu đề, khối đường dẫn và lề dưới. */
            height: calc(100vh - 430px);
            min-height: 560px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            background: #fff;
        }
        @media screen and (max-width: 782px) {
            #dsvat-frame { height: calc(100vh - 300px); }
        }
    </style>
    <script>
        document.getElementById('dsvat-copy').addEventListener('click', function () {
            var o = document.getElementById('dsvat-url');
            o.select();
            var xong = function () {
                var cu = this.textContent;
                this.textContent = <?php echo wp_json_encode(__('Đã sao chép', 'doi-soat-vat')); ?>;
                setTimeout(function (n, t) { n.textContent = t; }.bind(null, this, cu), 1500);
            }.bind(this);
            if (navigator.clipboard) {
                navigator.clipboard.writeText(o.value).then(xong, function () { document.execCommand('copy'); xong(); });
            } else {
                document.execCommand('copy');
                xong();
            }
        });
    </script>
    <?php
}

/** Lưu tuỳ chọn cho phép truy cập công khai. */
function dsvat_xu_ly_luu_cai_dat(): void
{
    if (!isset($_POST['dsvat_luu'])) {
        return;
    }
    if (!current_user_can(DSVAT_CAPABILITY)) {
        return;
    }
    $nonce = isset($_POST['dsvat_nonce']) ? sanitize_text_field(wp_unslash($_POST['dsvat_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'dsvat_luu_cai_dat')) {
        return;
    }

    update_option(DSVAT_OPTION_CONG_KHAI, isset($_POST['dsvat_cong_khai']) ? 1 : 0);
    add_settings_error('dsvat', 'dsvat_luu', __('Đã lưu.', 'doi-soat-vat'), 'updated');
    settings_errors('dsvat');
}

/* -------------------------------------------------------------------------
 * Thông báo và lối tắt
 * ---------------------------------------------------------------------- */

add_action('admin_notices', 'dsvat_thong_bao_kich_hoat');

function dsvat_thong_bao_kich_hoat(): void
{
    if (!get_transient('dsvat_vua_kich_hoat') || !current_user_can(DSVAT_CAPABILITY)) {
        return;
    }
    delete_transient('dsvat_vua_kich_hoat');
    ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <?php esc_html_e('Đã bật Đối soát VAT.', 'doi-soat-vat'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . DSVAT_SLUG)); ?>">
                <?php esc_html_e('Mở công cụ và lấy đường dẫn', 'doi-soat-vat'); ?>
            </a>
        </p>
    </div>
    <?php
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'dsvat_lien_ket_nhanh');

function dsvat_lien_ket_nhanh(array $links): array
{
    array_unshift(
        $links,
        sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . DSVAT_SLUG)),
            esc_html__('Mở công cụ', 'doi-soat-vat')
        )
    );
    return $links;
}
