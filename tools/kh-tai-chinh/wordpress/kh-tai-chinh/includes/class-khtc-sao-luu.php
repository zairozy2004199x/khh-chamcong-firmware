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
		return array( 'ngan_hang', 'diem', 'don_app', 'giao_dich', 'doi_soat', 'ds_dong', 'chi_phi', 'hd_ra', 'hd_vao', 'hop_dong', 'ho_so', 'thanh_toan', 'nhat_ky' );
	}

	// --------------------------------------------- tệp dữ liệu kèm trong bản cài

	/**
	 * Tệp .json đặt sẵn trong thư mục `du-lieu/` của plugin.
	 *
	 * VÌ SAO CÓ: nhập tệp sao lưu qua ô tải lên vướng `upload_max_filesize` của
	 * host — mặc định nhiều nơi chỉ 2 MB, mà một kỳ dữ liệu thật đã 5 MB. Đặt
	 * tệp vào trong chính bản cài thì nó đi cùng file zip của plugin, vốn đã
	 * được nén, và không phải tải lên lần thứ hai.
	 *
	 * Thư mục này KHÔNG có trong mã nguồn. Nó chỉ xuất hiện khi ai đó cố ý gói
	 * kèm dữ liệu vào một bản cài riêng, nên bản phát hành thường không có nút
	 * này. Dữ liệu thật của một công ty không bao giờ nằm trong kho mã.
	 *
	 * @return array [tên tệp => đường dẫn đầy đủ], đã sắp theo tên.
	 */
	public static function tep_kem() {
		$thu_muc = KHTC_DIR . 'du-lieu/';
		if ( ! is_dir( $thu_muc ) ) { return array(); }
		$ra = array();
		foreach ( (array) glob( $thu_muc . '*.json' ) as $d ) {
			// basename() chặn luôn chuyện đường dẫn lạ lọt ra tên hiển thị.
			$ra[ basename( $d ) ] = $d;
		}
		ksort( $ra );
		return $ra;
	}

	/**
	 * Nhập một tệp kèm theo, chọn bằng TÊN chứ không bằng đường dẫn.
	 *
	 * Tên từ trình duyệt gửi lên không bao giờ được ghép thẳng vào đường dẫn:
	 * chỉ nhận nếu nó nằm trong danh sách tep_kem() đã quét sẵn. Có thế thì
	 * `../../wp-config.php` cũng không đọc được gì.
	 */
	public static function nhap_tep_kem( $ten ) {
		$ds = self::tep_kem();
		if ( ! isset( $ds[ $ten ] ) ) {
			return new WP_Error( 'tep', 'Không có tệp dữ liệu kèm theo tên đó trong bản cài.' );
		}
		$json = file_get_contents( $ds[ $ten ] );
		if ( false === $json ) {
			return new WP_Error( 'doc', 'Không đọc được tệp ' . $ten . ' trong thư mục du-lieu của plugin.' );
		}
		return self::nhap( $json );
	}

	/** Bảng có cột cty — mọi bảng của sổ, trừ diem (danh mục) được tách riêng vì hay nạp đúng nhãn sau. */
	public static function bang_co_cty() {
		return array( 'ngan_hang', 'giao_dich', 'doi_soat', 'chi_phi', 'hd_ra', 'hd_vao', 'hop_dong', 'ho_so', 'thanh_toan', 'nhat_ky', 'don_app' );
	}

	/**
	 * Tóm tắt mỗi pháp nhân đang giữ gì — để người ta nhìn trước khi bấm hoán đổi.
	 *
	 * @return array cty → [ 'tai_khoan' => [tên], 'so' => [bảng → số dòng] ]
	 */
	public static function tom_tat_cty() {
		global $wpdb;
		$ra = array();
		foreach ( array_keys( KHTC_Cty::ds() ) as $cty ) {
			$tk = $wpdb->get_col( $wpdb->prepare( 'SELECT ten FROM ' . KHTC_DB::bang( 'ngan_hang' ) . ' WHERE cty = %s ORDER BY ten', $cty ) );
			$so = array();
			foreach ( array( 'giao_dich', 'doi_soat', 'hd_ra', 'hd_vao', 'chi_phi', 'don_app', 'diem' ) as $b ) {
				$so[ $b ] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( $b ) . ' WHERE cty = %s', $cty ) );
			}
			$ra[ $cty ] = array( 'tai_khoan' => $tk, 'so' => $so );
		}
		return $ra;
	}

	/**
	 * Hoán đổi KH Cũ ↔ KH Mới cho phần SỔ. Dùng một lần để sửa bản dữ liệu
	 * 1.2–1.3 (lúc đó gán ngược hai pháp nhân: sổ KH989 nằm dưới nhãn KH Mới).
	 * Danh mục điểm để nguyên trừ khi bảo kèm — vì danh mục thường được nạp
	 * SAU, theo đúng nhãn, và chính sự lệch giữa sổ và danh mục là triệu chứng.
	 *
	 * Hai bước UPDATE qua giá trị tạm để không đụng UNIQUE (cty, ma_cua_hang) ở diem.
	 *
	 * @return array bảng → số dòng đã đổi
	 */
	public static function hoan_doi_cty( $kem_diem = false ) {
		global $wpdb;
		$bang = self::bang_co_cty();
		if ( $kem_diem ) { $bang[] = 'diem'; }
		$doi = array();
		foreach ( $bang as $b ) {
			$t = KHTC_DB::bang( $b );
			$n1 = $wpdb->query( "UPDATE $t SET cty = '__tam__' WHERE cty = 'kh_cu'" );
			$n2 = $wpdb->query( "UPDATE $t SET cty = 'kh_cu' WHERE cty = 'kh_moi'" );
			$wpdb->query( "UPDATE $t SET cty = 'kh_moi' WHERE cty = '__tam__'" );
			$doi[ $b ] = (int) $n1 + (int) $n2;
		}
		// Danh mục chi phí (bộ phận, khoản mục) lưu ở option theo cty — đổi theo sổ.
		foreach ( array( 'bo_phan', 'khoan_muc' ) as $loai ) {
			$a = get_option( 'khtc_dm_' . $loai . '_kh_cu', null );
			$b = get_option( 'khtc_dm_' . $loai . '_kh_moi', null );
			if ( null === $b ) { delete_option( 'khtc_dm_' . $loai . '_kh_cu' ); } else { update_option( 'khtc_dm_' . $loai . '_kh_cu', $b ); }
			if ( null === $a ) { delete_option( 'khtc_dm_' . $loai . '_kh_moi' ); } else { update_option( 'khtc_dm_' . $loai . '_kh_moi', $a ); }
		}
		KHTC_NhatKy::ghi( 'sua', 'sao_luu', 0, 'Hoán đổi pháp nhân KH Cũ ↔ KH Mới cho phần sổ' . ( $kem_diem ? ' và danh mục điểm' : '' ) . ': ' . implode( ', ', array_map( function ( $k, $v ) { return $k . ' ' . $v; }, array_keys( $doi ), $doi ) ) );
		return $doi;
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
		$dung_lai = array();   // dòng khớp với thứ đã có nên dùng lại, không thêm
		// Thứ tự có ý nghĩa: bảng được trỏ tới phải vào trước bảng trỏ đi, để
		// lúc nối lại liên kết đã có id mới mà tra. Thiếu một bảng ở đây thì
		// xuất ra vẫn có nó mà nhập lại mất — nên danh sách này phải phủ hết
		// self::bang().
		$thu_tu = array( 'ngan_hang', 'diem', 'don_app', 'doi_soat', 'giao_dich', 'ds_dong', 'chi_phi', 'hd_ra', 'hd_vao', 'hop_dong', 'ho_so', 'thanh_toan', 'nhat_ky' );
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

				// Tài khoản ngân hàng đã có thì DÙNG LẠI, không tạo thêm bản sao.
				//
				// Bảng ngan_hang không có khoá duy nhất, nên nhập hai tệp cùng
				// chứa một tài khoản sẽ ra hai dòng trùng tên trùng số, và số dư
				// bị xẻ đôi. Chuyện này xảy ra ngay khi tách một kỳ sao kê dài
				// thành nhiều tệp cho khỏi hết giờ — tức là đúng lúc cần nhất.
				if ( 'ngan_hang' === $t && '' !== trim( (string) ( $hang['so_tk'] ?? '' ) ) ) {
					$da_co = $wpdb->get_var(
						$wpdb->prepare(
							'SELECT id FROM ' . KHTC_DB::bang( 'ngan_hang' ) . ' WHERE cty = %s AND so_tk = %s ORDER BY id LIMIT 1',
							(string) ( $hang['cty'] ?? KHTC_Cty::MAC_DINH ),
							(string) $hang['so_tk']
						)
					);
					if ( $da_co ) {
						$moi[ $t ][ $cu ] = (int) $da_co;
						$dung_lai[ $t ]   = ( $dung_lai[ $t ] ?? 0 ) + 1;
						continue;
					}
				}

				// Giao dịch đã có mã đó trong tài khoản đó thì bỏ qua. Nhập lại
				// một tệp sao lưu, hoặc nhập chồng hai tệp có phần giao nhau,
				// là chuyện xảy ra thật khi chạy tháng này qua tháng khác —
				// không chặn thì số dư phình lên mà không có gì báo.
				if ( 'giao_dich' === $t && '' !== trim( (string) ( $hang['ma_gd'] ?? '' ) ) ) {
					$co_roi = $wpdb->get_var(
						$wpdb->prepare(
							'SELECT id FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' WHERE ngan_hang_id = %d AND ma_gd = %s LIMIT 1',
							(int) $hang['ngan_hang_id'],
							(string) $hang['ma_gd']
						)
					);
					if ( $co_roi ) {
						// Ánh xạ sang dòng đã có, để dòng cổng đã ghép với nó
						// vẫn trỏ đúng chỗ thay vì hoá mồ côi.
						$moi[ $t ][ $cu ] = (int) $co_roi;
						$dung_lai[ $t ]   = ( $dung_lai[ $t ] ?? 0 ) + 1;
						continue;
					}
				}

				// Sổ thanh toán trỏ tới chứng từ bằng CẶP (bang, chung_tu_id),
				// không phải một cột khoá ngoại, nên nó không nằm trong mấy phép
				// nối ở dưới. Quên chỗ này thì sau khi phục hồi, mọi khoản đã trả
				// bám nhầm hoá đơn và công nợ sai mà không có gì báo.
				//
				// Phép kiểm cũ không bắt được: nhập vào sổ TRẮNG thì id cấp lại
				// đúng bằng id cũ, sai mà vẫn ra đúng số. Chỉ nhập vào sổ đã có
				// dữ liệu mới lộ.
				if ( 'thanh_toan' === $t ) {
					$bang_ct = (string) ( $hang['bang'] ?? '' );
					$ct_cu   = (int) ( $hang['chung_tu_id'] ?? 0 );
					if ( ! isset( $moi[ $bang_ct ][ $ct_cu ] ) ) {
						// Chứng từ bị từ chối (trùng số hoá đơn) hoặc không có
						// trong tệp. Thà bỏ hẳn dòng trả này còn hơn gán bừa:
						// gán id 0 thì khoản đã trả biến mất khỏi công nợ mà
						// không ai thấy, gán nhầm id thì tệ hơn nữa.
						$bo[ $t ]++;
						continue;
					}
					$hang['chung_tu_id'] = $moi[ $bang_ct ][ $ct_cu ];
				}
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

		foreach ( (array) ( $d['danh_muc'] ?? array() ) as $khoa => $ds ) {
			if ( is_array( $ds ) && $ds ) { update_option( 'khtc_dm_' . $khoa, array_values( $ds ) ); }
		}

		KHTC_NhatKy::dong_lo();
		// Ghi SAU khi đóng lô: dòng tổng kết chỉ lọt ra khi đây là lô ngoài
		// cùng. Lần phục hồi lớn nhất không được là lần duy nhất không có vết.
		KHTC_NhatKy::ghi(
			'nhap',
			'',
			0,
			'Nhập sao lưu từ ' . ( (string) ( $d['website'] ?? '?' ) ) . ' (' . ( (string) ( $d['luc'] ?? '?' ) ) . '): ' . implode( ' · ', array_map( function ( $t, $n ) { return $t . ' ' . $n; }, array_keys( $dem ), $dem ) )
		);
		return array( 'them' => $dem, 'bo' => array_filter( $bo ), 'dung_lai' => array_filter( $dung_lai ) );
	}
}
