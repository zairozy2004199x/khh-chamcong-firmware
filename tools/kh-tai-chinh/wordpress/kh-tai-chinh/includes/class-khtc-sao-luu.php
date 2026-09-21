<?php
/**
 * Sao lưu và phục hồi toàn bộ dữ liệu của plugin.
 *
 * VÌ SAO CẦN: dữ liệu giờ nằm trong MySQL của website. Website đổi host, ai đó
 * gỡ nhầm plugin, hoặc một bản nâng cấp hỏng — mất hết. Bản gốc chạy trên file
 * JSON nên "sao lưu" chỉ là copy một tệp; đổi sang MySQL thì phải tự làm lấy
 * đường ra, nếu không là lấy đi mất một thứ người dùng đang có.
 *
 * Xuất ra JSON một tệp, gồm mọi bảng của plugin và danh mục chi phí. Nhập lại thì
 * THÊM VÀO chứ không xoá cái đang có, và id được cấp lại — nếu giữ nguyên id
 * cũ thì nhập vào một website đã có dữ liệu sẽ đè mất dữ liệu ở đó.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_SaoLuu {

	const DINH_DANG = 1;

	public static function bang() {
		return array( 'ngan_hang', 'giao_dich', 'doi_soat', 'ds_dong', 'chi_phi', 'hd_ra', 'hd_vao', 'hop_dong', 'thanh_toan', 'nhat_ky' );
	}

	/** Gom cả kho dữ liệu thành một mảng. Không lọc theo pháp nhân: sao lưu là sao lưu tất. */
	public static function gom() {
		global $wpdb;
		$ra = array(
			'dinh_dang' => self::DINH_DANG,
			'phien_ban' => KHTC_VERSION,
			'luc'       => current_time( 'mysql' ),
			'website'   => home_url(),
			'bang'      => array(),
			'danh_muc'  => array(),
		);
		foreach ( self::bang() as $t ) {
			$ra['bang'][ $t ] = $wpdb->get_results( 'SELECT * FROM ' . KHTC_DB::bang( $t ), ARRAY_A );
		}
		foreach ( array_keys( KHTC_Cty::ds() ) as $cty ) {
			foreach ( array( 'bo_phan', 'khoan_muc' ) as $loai ) {
				$ra['danh_muc'][ $loai . '_' . $cty ] = KHTC_ChiPhi::danh_muc( $loai, $cty );
			}
		}
		return $ra;
	}

	public static function dem() {
		global $wpdb;
		$ra = array();
		foreach ( self::bang() as $t ) {
			$ra[ $t ] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( $t ) );
		}
		return $ra;
	}

	/** Tải tệp sao lưu. Phải chạy trước khi in ra chữ nào, nếu không header bị từ chối. */
	public static function tai() {
		if ( empty( $_GET['khtc_sao_luu'] ) ) { return; }
		if ( ! is_user_logged_in() || ! current_user_can( KHTC_CAP ) ) { return; }
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'khtc_sao_luu' ) ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="kh-tai-chinh-' . gmdate( 'Y-m-d-Hi' ) . '.json"' );
		echo wp_json_encode( self::gom(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Nhập lại từ tệp sao lưu. Thêm vào, không xoá.
	 *
	 * Id được cấp lại và các liên kết (giao dịch → ngân hàng, dòng cổng → đợt,
	 * chi phí → giao dịch) được nối lại theo id mới. Bỏ bước này thì nhập vào
	 * một website đã có 3 tài khoản sẽ khiến giao dịch mới trỏ nhầm tài khoản —
	 * số dư sai mà không có gì báo.
	 */
	public static function nhap( $json ) {
		global $wpdb;
		$d = json_decode( (string) $json, true );
		if ( ! is_array( $d ) || empty( $d['bang'] ) ) {
			return new WP_Error( 'doc', 'Không đọc được tệp sao lưu (phải là tệp .json do chính trang này xuất ra).' );
		}
		if ( (int) ( $d['dinh_dang'] ?? 0 ) !== self::DINH_DANG ) {
			return new WP_Error( 'dinh_dang', 'Tệp sao lưu thuộc định dạng khác (bản ' . (int) ( $d['dinh_dang'] ?? 0 ) . '), bản này đọc định dạng ' . self::DINH_DANG . '.' );
		}

		// Nhập là một lô: từng dòng không ghi nhật ký riêng, chỉ một dòng tổng kết.
		KHTC_NhatKy::mo_lo();
		$moi  = array();   // bảng => [id cũ => id mới]
		$dem  = array();
		$bo   = array();   // dòng bị cơ sở dữ liệu từ chối, hầu hết là trùng khoá
		// Thứ tự có ý nghĩa: bảng được trỏ tới phải vào trước bảng trỏ đi, để
		// lúc nối lại liên kết đã có id mới mà tra. Thiếu một bảng ở đây thì
		// xuất ra vẫn có nó mà nhập lại mất — nên danh sách này phải phủ hết
		// self::bang().
		$thu_tu = array( 'ngan_hang', 'doi_soat', 'giao_dich', 'ds_dong', 'chi_phi', 'hd_ra', 'hd_vao', 'hop_dong', 'thanh_toan', 'nhat_ky' );
		$thieu  = array_diff( self::bang(), $thu_tu );
		if ( $thieu ) {
			return new WP_Error( 'thu_tu', 'Lỗi lập trình: bảng ' . implode( ', ', $thieu ) . ' chưa có trong thứ tự nhập.' );
		}

		foreach ( $thu_tu as $t ) {
			$moi[ $t ] = array();
			$dem[ $t ] = 0;
			$bo[ $t ]  = 0;
			foreach ( (array) ( $d['bang'][ $t ] ?? array() ) as $hang ) {
				$cu = (int) ( $hang['id'] ?? 0 );
				unset( $hang['id'] );
				// Nối lại liên kết theo id mới của bảng đã nhập trước đó.
				if ( isset( $hang['ngan_hang_id'] ) ) {
					$hang['ngan_hang_id'] = $moi['ngan_hang'][ (int) $hang['ngan_hang_id'] ] ?? 0;
				}
				if ( isset( $hang['dot_id'] ) ) {
					$hang['dot_id'] = $moi['doi_soat'][ (int) $hang['dot_id'] ] ?? 0;
				}
				if ( isset( $hang['khop_gd_id'] ) ) {
					$hang['khop_gd_id'] = $moi['giao_dich'][ (int) $hang['khop_gd_id'] ] ?? 0;
				}
				if ( isset( $hang['giao_dich_id'] ) ) {
					$hang['giao_dich_id'] = $moi['giao_dich'][ (int) $hang['giao_dich_id'] ] ?? 0;
				}
				// Hoá đơn đầu ra có UNIQUE (cty, so_hd): nhập đè lên sổ đã có
				// cùng hoá đơn thì dòng đó bị từ chối. Đếm riêng chứ không báo
				// là đã nhập — nói sai chỗ này là kế toán tưởng đã phục hồi đủ.
				if ( false === $wpdb->insert( KHTC_DB::bang( $t ), $hang ) ) {
					$bo[ $t ]++;
					continue;
				}
				$moi[ $t ][ $cu ] = (int) $wpdb->insert_id;
				$dem[ $t ]++;
			}
		}

		// Nhật ký nhập vào cũng ghi một dòng nhật ký — nếu không thì lần phục hồi
		// lớn nhất lại là lần duy nhất không để lại vết.
		KHTC_NhatKy::ghi(
			'nhap',
			'',
			0,
			'Nhập sao lưu từ ' . ( (string) ( $d['website'] ?? '?' ) ) . ' (' . ( (string) ( $d['luc'] ?? '?' ) ) . '): ' . implode( ' · ', array_map( function ( $t, $n ) { return $t . ' ' . $n; }, array_keys( $dem ), $dem ) ),
			null,
			true
		);

		foreach ( (array) ( $d['danh_muc'] ?? array() ) as $khoa => $ds ) {
			if ( is_array( $ds ) && $ds ) { update_option( 'khtc_dm_' . $khoa, array_values( $ds ) ); }
		}

		KHTC_NhatKy::dong_lo();
		return array( 'them' => $dem, 'bo' => array_filter( $bo ) );
	}
}
