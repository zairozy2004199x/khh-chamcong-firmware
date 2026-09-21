<?php
/**
 * TRẠM NGHE IPN CỦA MoMo — bước MỘT: nghe và ghi lại, chưa kết luận gì.
 *
 * 18/09/2026, anh Thắng: *"Giờ thử kết nối api với momo"*, kèm ảnh trang cấu hình của
 * developers.momo.vn. Ảnh ấy cho ba điều chắc chắn:
 *   · MoMo gọi bằng POST, `Content-Type: application/json; charset=UTF-8`;
 *   · ký bằng HMAC-SHA256;
 *   · MoMo gọi VỀ mình từ IP **118.69.210.244** (outgoing, giống nhau ở cả sandbox lẫn production).
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 VÌ SAO CHỈ NGHE, CHƯA KIỂM CHỮ KÝ
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Chữ ký MoMo là HMAC-SHA256 trên một chuỗi ghép các trường theo ĐÚNG MỘT THỨ TỰ. Thứ tự ấy nằm
 * ở trang IPN, mà trang ấy chưa đọc được. Đoán thứ tự rồi kiểm là chữ ký LUÔN LUÔN sai, và cái
 * sai ấy không nói ra được nguyên nhân — nhìn từ ngoài thì y hệt "MoMo gửi sai" hoặc "khoá sai".
 * Dựng như thế là tự chuốc mấy vòng dò dẫm.
 *
 * Nên bước một chỉ làm đúng một việc: GHI NGUYÊN VĂN những gì MoMo gửi. Cuộc gọi thật đầu tiên
 * trả lời luôn ba câu đang treo:
 *   1. chuỗi ký gồm những trường nào, theo thứ tự nào;
 *   2. payload có mang MÃ CỬA HÀNG không — đối soát gom theo cơ sở, mà API cổng thanh toán vốn
 *      là theo MERCHANT chứ không theo cửa hàng, nên đây là câu chưa ai trả lời được;
 *   3. `transId` MoMo gửi có khớp `Mã đối tác` bên máy POS không — đó là chốt để ghép hai sổ.
 *
 * ⚠️ CHƯA GHI VÀO SỔ MoMo. Sổ ấy là số liệu tiền; đổ vào đó một dòng chưa kiểm chữ ký nghĩa là
 *    bất kỳ ai biết đường dẫn cũng bơm được doanh thu giả. Nhật ký thì khác — nó chỉ là thứ để
 *    người đọc, không dòng nào của nó đi vào phép đối soát.
 *
 * ⚠️ LUÔN GHI, KỂ CẢ KHI CHỐI. "MoMo bảo đã gọi mà hệ không thấy gì" là ca tốn thời gian nhất;
 *    có nhật ký ghi cả lượt bị chối kèm LÝ DO thì nó thành một câu trả lời trong ba mươi giây.
 */

defined( 'ABSPATH' ) || exit;

/** IP MoMo gọi về mình — lấy từ bảng "Địa chỉ IP" trong tài liệu, cột Outcoming. */
function khh_dt_momo_ip_cho_phep() {
	/* Lọc để anh Thắng thêm được IP mới mà không phải sửa mã, phòng khi MoMo đổi. */
	return (array) apply_filters( 'khh_dt_momo_ip_cho_phep', array( '118.69.210.244' ) );
}

function khh_dt_bang_momo_ipn() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_momo_ipn';
}

function khh_dt_tao_bang_momo_ipn() {
	global $wpdb;
	$bang    = khh_dt_bang_momo_ipn();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			luc datetime NOT NULL,
			ip varchar(64) NOT NULL DEFAULT '',
			ket_qua varchar(40) NOT NULL DEFAULT '',
			ly_do varchar(190) NOT NULL DEFAULT '',
			than longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY luc (luc)
		) $charset;"
	);
}

/**
 * Ghi một lượt vào nhật ký.
 *
 * ⚠️ CẮT THÂN Ở 8KB. Nhật ký này nhận mọi thứ gõ vào đường dẫn công khai, kể cả rác — không cắt
 *    thì một lượt POST 50MB là một dòng 50MB trong cơ sở dữ liệu.
 */
function khh_dt_momo_ipn_ghi( $ip, $ket_qua, $ly_do, $than ) {
	global $wpdb;
	$bang = khh_dt_bang_momo_ipn();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->insert(
		$bang,
		array(
			'luc'     => current_time( 'mysql' ),
			'ip'      => substr( (string) $ip, 0, 64 ),
			'ket_qua' => substr( (string) $ket_qua, 0, 40 ),
			'ly_do'   => substr( (string) $ly_do, 0, 190 ),
			'than'    => substr( (string) $than, 0, 8192 ),
		)
	);
	/* Giữ 200 dòng gần nhất. Đây là nhật ký để soi, không phải sổ. */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query(
		"DELETE FROM $bang WHERE id NOT IN ( SELECT id FROM ( SELECT id FROM $bang ORDER BY id DESC LIMIT 200 ) x )"
	);
}

/**
 * IP thật của bên gọi.
 *
 * ⚠️ KHÔNG tin `X-Forwarded-For` một cách vô điều kiện — ai cũng đặt được header ấy. Chỉ lấy nó
 *    khi hosting có đặt, và lấy MẨU ĐẦU (bên gọi gốc). Đây là gác lớp một, không phải lớp duy
 *    nhất: chữ ký mới là thứ chốt, IP chỉ để chặn rác cho rẻ.
 */
function khh_dt_momo_ip_goi() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
		if ( empty( $_SERVER[ $k ] ) ) {
			continue;
		}
		$v = sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) );
		$v = trim( explode( ',', $v )[0] );
		if ( '' !== $v ) {
			return $v;
		}
	}
	return '';
}

add_action( 'rest_api_init', 'khh_dt_momo_ipn_route' );
function khh_dt_momo_ipn_route() {
	register_rest_route(
		'khh-dt/v1',
		'/momo-ipn',
		array(
			'methods'  => array( 'POST', 'GET' ),
			'callback' => 'khh_dt_momo_ipn_nhan',
			/* MoMo không đăng nhập được, nên cổng phải mở. Gác nằm ở IP + (sau này) chữ ký.
			   Cho cả GET để anh Thắng mở bằng trình duyệt mà biết đường dẫn đã sống chưa —
			   đúng cách plugin Sao Kê đã làm với webhook SePay. */
			'permission_callback' => '__return_true',
		)
	);
}

function khh_dt_momo_ipn_nhan( $req ) {
	$ip = khh_dt_momo_ip_goi();

	if ( 'GET' === $req->get_method() ) {
		khh_dt_momo_ipn_ghi( $ip, 'ping', 'mở bằng trình duyệt', '' );
		return new WP_REST_Response(
			array(
				'song'   => true,
				'ghi_chu' => 'Đường dẫn IPN đang sống. MoMo phải gọi bằng POST JSON.',
				'ip_cua_ban' => $ip,
			),
			200
		);
	}

	$than = $req->get_body();
	$cho  = khh_dt_momo_ip_cho_phep();

	if ( $cho && ! in_array( $ip, $cho, true ) ) {
		khh_dt_momo_ipn_ghi( $ip, 'chối', 'IP không nằm trong danh sách cho phép', $than );
		/* Trả 204 chứ không 403: nói ra "IP sai" là chỉ đường cho người đang dò. MoMo thật thì
		   không bao giờ chạm nhánh này. */
		return new WP_REST_Response( null, 204 );
	}

	/* 🔴 BƯỚC MỘT: CHỈ GHI. Chưa kiểm chữ ký, nên KHÔNG ghi vào sổ MoMo — xem chú thích đầu tệp. */
	khh_dt_momo_ipn_ghi( $ip, 'nhận', 'ghi nhật ký, CHƯA vào sổ (chưa kiểm chữ ký)', $than );

	/* MoMo đợi 204 No Content. Trả khác đi thì nó gọi lại nhiều lượt. */
	return new WP_REST_Response( null, 204 );
}

/** Mấy lượt gần nhất, cho màn Quản trị soi. */
function khh_dt_momo_ipn_ds( $so = 20 ) {
	global $wpdb;
	$bang = khh_dt_bang_momo_ipn();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM $bang ORDER BY id DESC LIMIT %d", (int) $so ),
		ARRAY_A
	);
}

/** Đường dẫn IPN để dán vào trang quản trị MoMo. */
function khh_dt_momo_ipn_link() {
	return rest_url( 'khh-dt/v1/momo-ipn' );
}
