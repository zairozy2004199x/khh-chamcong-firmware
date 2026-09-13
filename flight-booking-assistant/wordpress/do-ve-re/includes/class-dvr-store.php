<?php
/** Kho đơn hàng: một bảng riêng trong cơ sở dữ liệu WordPress. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Store {

	const TRANG_THAI = array(
		'cho_bao_gia'    => 'Chờ báo giá chính thức',
		'cho_thanh_toan' => 'Chờ khách chuyển khoản',
		'het_han'        => 'Quá hạn giữ giá',
		'da_nhan_tien'   => 'Đã nhận tiền, chờ mua vé',
		'dang_dat_ve'    => 'Đang mua vé',
		'da_xuat_ve'     => 'Đã xuất vé',
		'hoan_tien'      => 'Đã hoàn tiền',
		'huy'            => 'Đã huỷ',
	);

	public static function bang() {
		global $wpdb;
		return $wpdb->prefix . 'dovere_orders';
	}

	public static function tao_bang() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$bang    = self::bang();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$bang} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				code varchar(20) NOT NULL,
				created_at datetime NOT NULL,
				expires_at datetime NOT NULL,
				status varchar(20) NOT NULL,
				total bigint(20) NOT NULL DEFAULT 0,
				paid bigint(20) NOT NULL DEFAULT 0,
				cost bigint(20) NOT NULL DEFAULT 0,
				pnr varchar(20) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				phone varchar(30) NOT NULL DEFAULT '',
				data longtext NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY code (code),
				KEY status (status)
			) {$collate};"
		);
	}

	/** Phí dịch vụ cộng vào giá vé. */
	public static function tinh_phi( $tien_ve, $cd = null ) {
		$cd  = $cd ? $cd : dvr_cai_dat();
		$phi = (int) round( $tien_ve * (float) $cd['fee_pct'] / 100 ) + (int) $cd['fee_flat'];
		return max( $phi, (int) $cd['fee_min'] );
	}

	/** Mã đơn: DVR + năm/tháng + số thứ tự. */
	public static function ma_don() {
		$dem = (int) get_option( 'dovere_seq', 0 ) + 1;
		update_option( 'dovere_seq', $dem, false );
		return 'DVR' . gmdate( 'ym', current_time( 'timestamp' ) ) . str_pad( (string) $dem, 4, '0', STR_PAD_LEFT );
	}

	/** Tìm mã đơn trong nội dung chuyển khoản, bỏ qua chữ ngân hàng chèn thêm. */
	public static function khop_ma( $noi_dung ) {
		$phang = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $noi_dung ) );
		return preg_match( '/DVR\d{8}/', $phang, $m ) ? $m[0] : null;
	}

	/** Kiểm tra dữ liệu khách gửi lên; trả về mảng lỗi rỗng nếu sạch. */
	public static function kiem_tra( $b ) {
		$loi   = array();
		$f     = isset( $b['flight'] ) ? (array) $b['flight'] : array();
		$fare  = isset( $b['fare'] ) ? (array) $b['fare'] : array();
		$ct    = isset( $b['contact'] ) ? (array) $b['contact'] : array();
		$pax   = array();
		foreach ( (array) ( isset( $b['pax'] ) ? $b['pax'] : array() ) as $p ) {
			$p = (array) $p;
			if ( ! empty( $p['full'] ) && trim( $p['full'] ) !== '' ) {
				$pax[] = $p;
			}
		}
		if ( empty( $f['route'] ) || empty( $f['date'] ) ) {
			$loi[] = 'thiếu chặng bay hoặc ngày bay';
		}
		if ( empty( $fare['total'] ) || (float) $fare['total'] <= 0 ) {
			$loi[] = 'thiếu số tiền vé';
		}
		if ( ! $pax ) {
			$loi[] = 'chưa có hành khách nào';
		}
		foreach ( $pax as $p ) {
			if ( count( preg_split( '/\s+/', trim( $p['full'] ) ) ) < 2 ) {
				$loi[] = 'họ tên hành khách phải đủ họ và tên';
				break;
			}
		}
		if ( ! preg_match( '/^0\d{9}$/', preg_replace( '/\D/', '', isset( $ct['phone'] ) ? $ct['phone'] : '' ) ) ) {
			$loi[] = 'số điện thoại phải là 10 số bắt đầu bằng 0';
		}
		if ( ! is_email( isset( $ct['email'] ) ? $ct['email'] : '' ) ) {
			$loi[] = 'email chưa đúng';
		}
		if ( ! empty( $fare['cur'] ) && 'VND' !== $fare['cur'] ) {
			$loi[] = 'đơn chỉ nhận tiền VND, chặng này đang báo giá ' . sanitize_text_field( $fare['cur'] );
		}
		return array( $loi, $pax );
	}

	public static function tao( $b, $pax ) {
		global $wpdb;
		$cd      = dvr_cai_dat();
		$tien_ve = (int) round( (float) $b['fare']['total'] );
		$phi     = self::tinh_phi( $tien_ve, $cd );
		$now     = current_time( 'mysql' );
		$code    = self::ma_don();
		$f       = (array) $b['flight'];

		$don = array(
			'code'       => $code,
			'created_at' => $now,
			'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( $now ) + (int) $cd['hold_minutes'] * 60 ),
			'status'     => dvr_cai_dat( 'bao_gia_truoc', 1 ) ? 'cho_bao_gia' : 'cho_thanh_toan',
			'total'      => $tien_ve + $phi,
			'paid'       => 0,
			'cost'       => 0,
			'pnr'        => '',
			'email'      => sanitize_email( $b['contact']['email'] ),
			'phone'      => sanitize_text_field( $b['contact']['phone'] ),
			'data'       => array(
				'flight'  => array(
					'route'   => sanitize_text_field( $f['route'] ),
					'date'    => sanitize_text_field( $f['date'] ),
					'airline' => sanitize_text_field( isset( $f['airline'] ) ? $f['airline'] : '' ),
					'number'  => sanitize_text_field( isset( $f['number'] ) ? $f['number'] : '' ),
					'dep'     => sanitize_text_field( isset( $f['dep'] ) ? $f['dep'] : '' ),
					'arr'     => sanitize_text_field( isset( $f['arr'] ) ? $f['arr'] : '' ),
					'stops'   => (int) ( isset( $f['stops'] ) ? $f['stops'] : 0 ),
					'bag'     => ! empty( $f['bag'] ),
					'cabin'   => sanitize_text_field( isset( $f['cabin'] ) ? $f['cabin'] : 'ECONOMY' ),
				),
				'money'   => array( 'fare' => $tien_ve, 'fee' => $phi, 'total' => $tien_ve + $phi ),
				'pax'     => array_map( array( __CLASS__, 'sach_khach' ), $pax ),
				'contact' => array(
					'name'  => sanitize_text_field( isset( $b['contact']['name'] ) ? $b['contact']['name'] : '' ),
					'phone' => sanitize_text_field( $b['contact']['phone'] ),
					'email' => sanitize_email( $b['contact']['email'] ),
				),
				'invoice' => array_map( 'sanitize_text_field', (array) ( isset( $b['invoice'] ) ? $b['invoice'] : array() ) ),
				'log'     => array( array(
				'at'   => $now,
				'what' => dvr_cai_dat( 'bao_gia_truoc', 1 )
					? 'Khách đặt theo giá tham khảo ' . dvr_tien( $tien_ve ) . ' — chờ mình kiểm chỗ và chốt giá'
					: 'Khách tạo đơn',
			) ),
				'mail'    => array(),
			),
		);

		$wpdb->insert( self::bang(), array_merge( $don, array( 'data' => wp_json_encode( $don['data'] ) ) ) );
		return self::lay( $code );
	}

	private static function sach_khach( $p ) {
		$p = (array) $p;
		return array(
			'full'  => sanitize_text_field( $p['full'] ),
			'dob'   => sanitize_text_field( isset( $p['dob'] ) ? $p['dob'] : '' ),
			'idNo'  => sanitize_text_field( isset( $p['idNo'] ) ? $p['idNo'] : '' ),
		);
	}

	public static function lay( $code ) {
		global $wpdb;
		$bang = self::bang();
		$hang = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bang} WHERE code = %s", strtoupper( (string) $code ) ), ARRAY_A );
		return $hang ? self::mo( $hang ) : null;
	}

	public static function tat_ca( $trang_thai = '' ) {
		global $wpdb;
		$bang = self::bang();
		$hang = $trang_thai
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$bang} WHERE status = %s ORDER BY id DESC LIMIT 300", $trang_thai ), ARRAY_A )
			: $wpdb->get_results( "SELECT * FROM {$bang} ORDER BY id DESC LIMIT 300", ARRAY_A );
		return array_map( array( __CLASS__, 'mo' ), (array) $hang );
	}

	/** Bung JSON và tính trạng thái thấy được (quá hạn giữ giá thì coi như hết hạn). */
	private static function mo( $hang ) {
		$d   = json_decode( $hang['data'], true );
		$don = is_array( $d ) ? $d : array();
		$don['code']       = $hang['code'];
		$don['createdAt']  = $hang['created_at'];
		$don['expiresAt']  = $hang['expires_at'];
		$don['pnr']        = $hang['pnr'];
		$don['money']      = array_merge(
			isset( $don['money'] ) ? $don['money'] : array(),
			array( 'total' => (int) $hang['total'], 'paid' => (int) $hang['paid'], 'cost' => (int) $hang['cost'] )
		);
		$het               = 'cho_thanh_toan' === $hang['status'] && strtotime( $hang['expires_at'] ) < time();
		// đơn chờ báo giá chưa chạy đồng hồ giữ giá: giá chưa chốt thì chưa có gì để giữ
		$don['status']     = $het ? 'het_han' : $hang['status'];
		$don['statusText'] = self::TRANG_THAI[ $don['status'] ];
		return $don;
	}

	/** Ghi đè vài cột và nối thêm dòng nhật ký. */
	public static function sua( $code, $cot, $ghi_chu = '' ) {
		global $wpdb;
		$don = self::lay( $code );
		if ( ! $don ) {
			return null;
		}
		$data = $don;
		unset( $data['code'], $data['createdAt'], $data['expiresAt'], $data['statusText'], $data['status'], $data['pnr'] );
		if ( $ghi_chu ) {
			$data['log'][] = array( 'at' => current_time( 'mysql' ), 'what' => $ghi_chu );
		}
		if ( isset( $cot['data'] ) ) {
			$data = array_merge( $data, $cot['data'] );
			unset( $cot['data'] );
		}
		$cot['data'] = wp_json_encode( $data );
		$wpdb->update( self::bang(), $cot, array( 'code' => strtoupper( $code ) ) );
		return self::lay( $code );
	}
}
