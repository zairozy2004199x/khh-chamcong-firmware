<?php
/** Các đường REST: /wp-json/dovere/v1/… */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Rest {

	const NS = 'dovere/v1';

	public static function khoi_dong() {
		add_action( 'rest_api_init', array( __CLASS__, 'dang_ky' ) );
	}

	public static function ai_cung_duoc() {
		return true;
	}

	public static function chi_quan_tri() {
		return current_user_can( 'manage_options' );
	}

	public static function dang_ky() {
		register_rest_route( self::NS, '/offers', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'ai_cung_duoc' ),
			'callback'            => array( __CLASS__, 'offers' ),
		) );
		register_rest_route( self::NS, '/tax', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'ai_cung_duoc' ),
			'callback'            => array( __CLASS__, 'tax' ),
		) );
		register_rest_route( self::NS, '/orders', array(
			'methods'             => 'POST',
			'permission_callback' => array( __CLASS__, 'ai_cung_duoc' ),
			'callback'            => array( __CLASS__, 'tao_don' ),
		) );
		register_rest_route( self::NS, '/orders/(?P<code>[A-Za-z0-9]+)', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'ai_cung_duoc' ),
			'callback'            => array( __CLASS__, 'xem_don' ),
		) );
		register_rest_route( self::NS, '/webhook/bank', array(
			'methods'             => 'POST',
			'permission_callback' => array( __CLASS__, 'ai_cung_duoc' ),
			'callback'            => array( __CLASS__, 'webhook' ),
		) );
		register_rest_route( self::NS, '/admin/thu-nguon', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'chi_quan_tri' ),
			'callback'            => array( __CLASS__, 'thu_nguon' ),
		) );
		register_rest_route( self::NS, '/admin/orders', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'chi_quan_tri' ),
			'callback'            => array( __CLASS__, 'ds_don' ),
		) );
		register_rest_route( self::NS, '/admin/orders/(?P<code>[A-Za-z0-9]+)/(?P<act>[a-z]+)', array(
			'methods'             => 'POST',
			'permission_callback' => array( __CLASS__, 'chi_quan_tri' ),
			'callback'            => array( __CLASS__, 'xu_ly_don' ),
		) );
	}

	/* ---------- giá ---------- */
	public static function offers( $req ) {
		$q = array(
			'from'   => strtoupper( sanitize_text_field( $req->get_param( 'from' ) ) ),
			'to'     => strtoupper( sanitize_text_field( $req->get_param( 'to' ) ) ),
			'dep'    => sanitize_text_field( $req->get_param( 'dep' ) ),
			'ret'    => sanitize_text_field( $req->get_param( 'ret' ) ),
			'adt'    => (int) $req->get_param( 'adt' ),
			'chd'    => (int) $req->get_param( 'chd' ),
			'inf'    => (int) $req->get_param( 'inf' ),
			'cabin'  => sanitize_text_field( $req->get_param( 'cabin' ) ),
			'direct' => '1' === (string) $req->get_param( 'direct' ),
		);
		if ( ! $q['from'] || ! $q['to'] || ! $q['dep'] ) {
			return new WP_REST_Response( array( 'error' => 'Thiếu tham số from / to / dep.' ), 400 );
		}
		$nguon = dvr_cai_dat( 'nguon', 'mo_phong' );
		if ( 'dai_ly' === $nguon && dvr_cai_dat( 'dl_url', '' ) ) {
			$kq = DVR_Dai_Ly::tim_chuyen( $q );
		} elseif ( 'duffel' === $nguon && dvr_cai_dat( 'duffel_token', '' ) ) {
			$kq = DVR_Duffel::tim_chuyen( $q );
		} elseif ( 'amadeus' === $nguon && dvr_cai_dat( 'amadeus_id', '' ) ) {
			$kq = DVR_Amadeus::tim_chuyen( $q );
		} else {
			// chưa khai nguồn nào: trang tự dựng giá mô phỏng, nói rõ cho người xem biết
			return new WP_REST_Response( array( 'offers' => array(), 'source' => 'none' ), 200 );
		}
		if ( is_wp_error( $kq ) ) {
			return new WP_REST_Response( array( 'error' => $kq->get_error_message() ), 502 );
		}
		return new WP_REST_Response( $kq, 200 );
	}

	public static function tax( $req ) {
		$kq = DVR_Amadeus::tra_mst( $req->get_param( 'mst' ) );
		if ( is_wp_error( $kq ) ) {
			return new WP_REST_Response( array( 'error' => $kq->get_error_message() ), 404 );
		}
		return new WP_REST_Response( $kq, 200 );
	}

	/** Gọi thử đúng nguồn đang chọn, trả về vài chuyến để soi xem có dùng được không. */
	public static function thu_nguon( $req ) {
		$from = strtoupper( sanitize_text_field( (string) $req->get_param( 'from' ) ) );
		$to   = strtoupper( sanitize_text_field( (string) $req->get_param( 'to' ) ) );
		$q    = array(
			'from'  => $from ? $from : 'SGN',
			'to'    => $to ? $to : 'HAN',
			'dep'   => gmdate( 'Y-m-d', time() + 14 * DAY_IN_SECONDS ),
			'ret'   => '', 'adt' => 1, 'chd' => 0, 'inf' => 0, 'cabin' => 'ECONOMY', 'direct' => false,
		);
		$nguon = dvr_cai_dat( 'nguon', 'mo_phong' );
		$tho   = null;

		$canh_bao = '';
		if ( 'duffel' === $nguon ) {
			$khoa = dvr_cai_dat( 'duffel_token', '' );
			if ( ! $khoa ) {
				return new WP_REST_Response( array( 'error' => 'Đang chọn nguồn Duffel nhưng chưa dán khoá.' ), 200 );
			}
			if ( 0 === strpos( $khoa, 'duffel_test' ) ) {
				$canh_bao = 'Khoá đang dùng là khoá THỬ (duffel_test_). Chuyến và giờ bay có thể đúng, nhưng GIÁ trong chế độ thử '
					. 'không phải giá bán thật, và Duffel còn trộn thêm chuyến của hãng giả "Duffel Airways" (mã ZZ) — mình đã lọc bỏ. '
					. 'Vì vậy trang khách đang GIẤU giá của nguồn này: khách vẫn thấy giờ bay và số hiệu chuyến, còn giá thì bấm sang nơi bán để xem. '
					. 'Muốn giá hiện lên thì hoàn tất xác minh doanh nghiệp ở Duffel rồi đổi sang khoá duffel_live_.';
			}
			$kq = DVR_Duffel::tim_chuyen( $q );
		} elseif ( 'dai_ly' === $nguon ) {
			if ( ! dvr_cai_dat( 'dl_url', '' ) ) {
				return new WP_REST_Response( array( 'error' => 'Đang chọn nguồn đại lý nhưng chưa khai đường dẫn API ở mục "API của đại lý cấp 1".' ), 200 );
			}
			$tho = DVR_Dai_Ly::goi( $q );
			if ( is_wp_error( $tho ) ) {
				return new WP_REST_Response( array( 'error' => $tho->get_error_message() ), 200 );
			}
			$kq = array( 'offers' => DVR_Dai_Ly::doi_du_lieu( $tho, $q ), 'source' => 'dai_ly' );
		} elseif ( 'amadeus' === $nguon ) {
			if ( ! dvr_cai_dat( 'amadeus_id', '' ) ) {
				return new WP_REST_Response( array( 'error' => 'Đang chọn nguồn Amadeus nhưng chưa khai khoá.' ), 200 );
			}
			$kq = DVR_Amadeus::tim_chuyen( $q );
		} else {
			return new WP_REST_Response( array( 'error' => 'Đang để "Giá mô phỏng" — chọn một nguồn thật rồi Lưu, sau đó thử lại.' ), 200 );
		}

		if ( is_wp_error( $kq ) ) {
			return new WP_REST_Response( array( 'error' => $kq->get_error_message() ), 200 );
		}

		$ds  = isset( $kq['offers'] ) ? $kq['offers'] : array();
		$vai = array();
		foreach ( array_slice( $ds, 0, 5 ) as $o ) {
			$vai[] = sprintf(
				'%s %s · %s → %s · %s%s · %s',
				$o['al']['code'],
				$o['code'],
				$o['dep'],
				$o['arr'],
				number_format( $o['price'], 0, ',', '.' ),
				'VND' === $o['cur'] ? 'đ' : ' ' . $o['cur'],
				$o['stops'] ? $o['stops'] . ' điểm dừng' : 'bay thẳng'
			);
		}
		list( $tg_usd, $ng_usd ) = dvr_ty_gia( 'USD' );
		$ra = array(
			'canh_bao'  => $canh_bao,
			'ty_gia'    => $tg_usd > 0
				? '1 USD = ' . number_format( round( $tg_usd ), 0, ',', '.' ) . 'đ · '
					. ( 'tay' === $ng_usd ? 'tỉ giá mình tự khai trong Cài đặt'
						: ( 'mang' === $ng_usd ? 'tự lấy trên mạng, 12 giờ làm mới một lần'
							: 'bảng dự phòng trong plugin (không hỏi được mạng) — nên tự khai tỉ giá' ) )
				: '',
			'nguon'     => $nguon,
			'chang'     => $q['from'] . ' → ' . $q['to'] . ' ngày ' . $q['dep'],
			'so_chuyen' => count( $ds ),
			'vai_chuyen'=> $vai,
			'ket_luan'  => count( $ds )
				? 'Nguồn này có ' . count( $ds ) . ' chuyến cho chặng vừa thử — dùng được.'
				: 'Nguồn này KHÔNG có chuyến nào cho chặng vừa thử. Thử chặng quốc tế (vd SGN → SIN) để xem nguồn có chạy không, hay chỉ thiếu nội địa Việt Nam.',
		);
		if ( null !== $tho ) {
			$ra['tho'] = $tho;   // để khai bảng ánh xạ cho khớp
		}
		return new WP_REST_Response( $ra, 200 );
	}

	/* ---------- đơn hàng ---------- */
	public static function tao_don( $req ) {
		$b = $req->get_json_params();
		if ( ! is_array( $b ) ) {
			return new WP_REST_Response( array( 'error' => 'Dữ liệu gửi lên không đọc được.' ), 400 );
		}
		list( $loi, $pax ) = DVR_Store::kiem_tra( $b );
		if ( $loi ) {
			return new WP_REST_Response( array( 'error' => implode( '; ', $loi ) ), 400 );
		}
		$don = DVR_Store::tao( $b, $pax );
		DVR_Mail::gui( $don['code'], 'cho_bao_gia' === $don['status'] ? 'cho_bao_gia' : 'moi' );
		$don = DVR_Store::lay( $don['code'] );
		return new WP_REST_Response( self::cho_khach( $don, true ), 201 );
	}

	public static function xem_don( $req ) {
		$don = DVR_Store::lay( $req->get_param( 'code' ) );
		if ( ! $don ) {
			return new WP_REST_Response( array( 'error' => 'Không có đơn này.' ), 404 );
		}
		return new WP_REST_Response( self::cho_khach( $don ), 200 );
	}

	/** Bản đơn cho khách xem: bỏ giá mình mua vào và nhật ký nội bộ. */
	private static function cho_khach( $o, $moi_tao = false ) {
		$cd   = dvr_cai_dat();
		$chua = 'cho_thanh_toan' === $o['status'];   // chưa chốt giá thì chưa có số tài khoản, chưa có QR
		return array(
			'code'       => $o['code'],
			'status'     => $o['status'],
			'statusText' => $o['statusText'],
			'createdAt'  => $o['createdAt'],
			'expiresAt'  => $o['expiresAt'],
			'flight'     => $o['flight'],
			'money'      => array(
				'fare'  => $o['money']['fare'],
				'fee'   => $o['money']['fee'],
				'total' => $o['money']['total'],
				'paid'  => $o['money']['paid'],
			),
			'pnr'        => $o['pnr'],
			'paxCount'   => count( (array) $o['pax'] ),
			'baoGiaPhut' => (int) dvr_cai_dat( 'bao_gia_phut', 15 ),
			'bank'       => $chua ? array(
				'bankId'       => $cd['bank_id'],
				'bankLabel'    => $cd['bank_label'],
				'account'      => $cd['bank_account'],
				'accountName'  => $cd['bank_name'],
				'transferNote' => $o['code'],
			) : null,
			'qr'         => $chua ? dvr_qr( $o['money']['total'], $o['code'] ) : '',
		);
	}

	/* ---------- ngân hàng báo tiền về ---------- */
	public static function webhook( $req ) {
		$khoa = dvr_cai_dat( 'webhook_secret', '' );
		$gui  = $req->get_header( 'authorization' );
		$gui  = $gui ? preg_replace( '/^Apikey\s+/i', '', $gui ) : $req->get_header( 'x_webhook_secret' );
		if ( ! $khoa || ! hash_equals( $khoa, (string) $gui ) ) {
			return new WP_REST_Response( array( 'error' => 'Sai khoá webhook.' ), 401 );
		}
		$b = (array) $req->get_json_params();
		if ( ! empty( $b['transferType'] ) && 'in' !== $b['transferType'] ) {
			return new WP_REST_Response( array( 'skipped' => 'không phải tiền vào' ), 200 );
		}
		$noi_dung = isset( $b['content'] ) ? $b['content'] : ( isset( $b['description'] ) ? $b['description'] : '' );
		$code     = DVR_Store::khop_ma( $noi_dung );
		$tien     = (int) round( (float) ( isset( $b['transferAmount'] ) ? $b['transferAmount'] : ( isset( $b['amount'] ) ? $b['amount'] : 0 ) ) );
		if ( ! $code ) {
			return new WP_REST_Response( array( 'skipped' => 'nội dung chuyển khoản không có mã đơn' ), 200 );
		}
		$don = DVR_Store::lay( $code );
		if ( ! $don ) {
			return new WP_REST_Response( array( 'error' => 'không thấy đơn ' . $code ), 404 );
		}
		if ( ! in_array( $don['status'], array( 'cho_thanh_toan', 'het_han' ), true ) ) {
			return new WP_REST_Response( array( 'skipped' => 'đơn ' . $code . ' đang ở trạng thái ' . $don['status'] ), 200 );
		}
		$thieu  = (int) $don['money']['total'] - $tien;
		$qua_han = 'het_han' === $don['status'];
		DVR_Store::sua(
			$code,
			array( 'paid' => $tien, 'status' => 'da_nhan_tien', 'data' => array( 'thieu' => $thieu, 'quaHan' => $qua_han ) ),
			'Nhận ' . dvr_tien( $tien )
				. ( $thieu > 0 ? ' — THIẾU ' . dvr_tien( $thieu ) : ( $thieu < 0 ? ' — THỪA ' . dvr_tien( -$thieu ) : '' ) )
				. ( $qua_han ? ' — đã quá hạn giữ giá' : '' )
		);
		DVR_Mail::gui( $code, 'da_nhan_tien' );
		return new WP_REST_Response( array( 'ok' => true, 'code' => $code, 'paid' => $tien, 'thieu' => $thieu, 'quaHan' => $qua_han ), 200 );
	}

	/* ---------- quản trị ---------- */
	public static function ds_don( $req ) {
		$ds   = DVR_Store::tat_ca( sanitize_text_field( (string) $req->get_param( 'status' ) ) );
		$thu  = 0;
		$mua  = 0;
		$lai  = 0;
		foreach ( $ds as $o ) {
			if ( in_array( $o['status'], array( 'da_nhan_tien', 'dang_dat_ve', 'da_xuat_ve' ), true ) ) {
				$thu += (int) $o['money']['paid'];
			}
			if ( 'da_xuat_ve' === $o['status'] ) {
				$mua += (int) $o['money']['cost'];
				$lai += (int) $o['money']['paid'] - (int) $o['money']['cost'];
			}
		}
		return new WP_REST_Response( array(
			'orders' => $ds,
			'tong'   => array( 'don' => count( $ds ), 'daThu' => $thu, 'daMua' => $mua, 'lai' => $lai ),
		), 200 );
	}

	public static function xu_ly_don( $req ) {
		$code = strtoupper( $req->get_param( 'code' ) );
		$act  = $req->get_param( 'act' );
		$b    = (array) $req->get_json_params();
		$don  = DVR_Store::lay( $code );
		if ( ! $don ) {
			return new WP_REST_Response( array( 'error' => 'Không có đơn ' . $code ), 400 );
		}

		if ( 'quote' === $act ) {
			// chốt giá thật sau khi kiểm chỗ với hãng; đồng hồ giữ giá bắt đầu từ đây
			$ve = isset( $b['fare'] ) ? (int) $b['fare'] : 0;
			if ( $ve <= 0 ) {
				return new WP_REST_Response( array( 'error' => 'Nhập giá vé thật (đồng).' ), 400 );
			}
			$phi  = DVR_Store::tinh_phi( $ve );
			$het  = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + (int) dvr_cai_dat( 'hold_minutes', 30 ) * 60 );
			$cu   = (int) $don['money']['fare'];
			DVR_Store::sua(
				$code,
				array(
					'total'      => $ve + $phi,
					'status'     => 'cho_thanh_toan',
					'expires_at' => $het,
					'data'       => array( 'money' => array_merge( $don['money'], array( 'fare' => $ve, 'fee' => $phi, 'total' => $ve + $phi ) ) ),
				),
				'Chốt giá ' . dvr_tien( $ve ) . ' (tham khảo lúc đặt ' . dvr_tien( $cu ) . ')'
					. ( $ve > $cu ? ' — cao hơn ' . dvr_tien( $ve - $cu ) : ( $ve < $cu ? ' — thấp hơn ' . dvr_tien( $cu - $ve ) : '' ) )
			);
			DVR_Mail::gui( $code, 'bao_gia' );
		} elseif ( 'hetcho' === $act ) {
			DVR_Store::sua( $code, array( 'status' => 'huy' ),
				'Hết chỗ ở mức giá tham khảo' . ( ! empty( $b['reason'] ) ? ' — ' . sanitize_text_field( $b['reason'] ) : '' ) );
			DVR_Mail::gui( $code, 'het_cho' );
		} elseif ( 'paid' === $act ) {
			$tien = isset( $b['amount'] ) ? (int) $b['amount'] : (int) $don['money']['total'];
			DVR_Store::sua( $code, array( 'paid' => $tien, 'status' => 'da_nhan_tien',
				'data' => array( 'thieu' => (int) $don['money']['total'] - $tien ) ), 'Đánh dấu đã nhận tiền (tay)' );
			DVR_Mail::gui( $code, 'da_nhan_tien' );
		} elseif ( 'booking' === $act ) {
			if ( empty( $b['pnr'] ) ) {
				return new WP_REST_Response( array( 'error' => 'Nhập mã đặt chỗ.' ), 400 );
			}
			$pnr  = strtoupper( sanitize_text_field( $b['pnr'] ) );
			$gia  = isset( $b['cost'] ) ? (int) $b['cost'] : 0;
			DVR_Store::sua( $code, array( 'pnr' => $pnr, 'cost' => $gia, 'status' => 'da_xuat_ve' ),
				'Đã mua vé · ' . $pnr . ( $gia ? ' · giá mua ' . dvr_tien( $gia ) . ' · chênh ' . dvr_tien( (int) $don['money']['paid'] - $gia ) : '' ) );
			DVR_Mail::gui( $code, 'da_xuat_ve' );
		} elseif ( 'refund' === $act ) {
			DVR_Store::sua( $code, array( 'status' => 'hoan_tien' ),
				'Hoàn tiền' . ( ! empty( $b['reason'] ) ? ' — ' . sanitize_text_field( $b['reason'] ) : '' ) );
			DVR_Mail::gui( $code, 'hoan_tien' );
		} elseif ( 'cancel' === $act ) {
			DVR_Store::sua( $code, array( 'status' => 'huy' ),
				'Huỷ đơn' . ( ! empty( $b['reason'] ) ? ' — ' . sanitize_text_field( $b['reason'] ) : '' ) );
		} elseif ( 'mail' === $act ) {
			$map  = array( 'cho_bao_gia' => 'cho_bao_gia', 'cho_thanh_toan' => 'bao_gia', 'het_han' => 'bao_gia', 'da_nhan_tien' => 'da_nhan_tien',
				'dang_dat_ve' => 'da_nhan_tien', 'da_xuat_ve' => 'da_xuat_ve', 'hoan_tien' => 'hoan_tien' );
			$kind = ! empty( $b['kind'] ) ? $b['kind'] : ( isset( $map[ $don['status'] ] ) ? $map[ $don['status'] ] : '' );
			if ( ! $kind ) {
				return new WP_REST_Response( array( 'error' => 'Đơn ở trạng thái ' . $don['status'] . ' không có mẫu thư nào.' ), 400 );
			}
			if ( ! DVR_Mail::gui( $code, $kind ) ) {
				return new WP_REST_Response( array( 'error' => 'WordPress không gửi được thư — kiểm tra cấu hình SMTP.' ), 502 );
			}
			return new WP_REST_Response( array( 'ok' => true, 'kind' => $kind, 'order' => DVR_Store::lay( $code ) ), 200 );
		} else {
			return new WP_REST_Response( array( 'error' => 'Không có việc "' . $act . '".' ), 400 );
		}

		return new WP_REST_Response( array( 'ok' => true, 'order' => DVR_Store::lay( $code ) ), 200 );
	}
}
