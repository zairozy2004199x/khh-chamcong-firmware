<?php
/**
 * Nguồn giá Duffel — https://duffel.com
 *
 * Vì sao có file này: cổng tự phục vụ của Amadeus đã đóng ngày 17/7/2025, giờ
 * chỉ còn Amadeus Enterprise (phải ký hợp đồng). Duffel là nguồn còn tự đăng ký
 * được: bật chế độ thử là có khoá ngay, dữ liệu thử miễn phí.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Duffel {

	const GOC = 'https://api.duffel.com';
	const PHIEN_BAN = 'v2';

	private static function dau( $token ) {
		return array(
			'Authorization'  => 'Bearer ' . $token,
			'Duffel-Version' => self::PHIEN_BAN,
			'Accept'         => 'application/json',
			'Content-Type'   => 'application/json',
		);
	}

	/** Gói yêu cầu gửi lên Duffel. Tách riêng để test được mà không chạm mạng. */
	public static function goi_yeu_cau( $q ) {
		$lat = array(
			array(
				'origin'         => $q['from'],
				'destination'    => $q['to'],
				'departure_date' => $q['dep'],
			),
		);
		if ( ! empty( $q['ret'] ) ) {
			$lat[] = array(
				'origin'         => $q['to'],
				'destination'    => $q['from'],
				'departure_date' => $q['ret'],
			);
		}
		$khach = array();
		for ( $i = 0; $i < max( 1, (int) $q['adt'] ); $i++ ) {
			$khach[] = array( 'type' => 'adult' );
		}
		for ( $i = 0; $i < (int) ( isset( $q['chd'] ) ? $q['chd'] : 0 ); $i++ ) {
			$khach[] = array( 'age' => 10 );
		}
		for ( $i = 0; $i < (int) ( isset( $q['inf'] ) ? $q['inf'] : 0 ); $i++ ) {
			$khach[] = array( 'type' => 'infant_without_seat' );
		}
		$hang = array( 'ECONOMY' => 'economy', 'PREMIUM_ECONOMY' => 'premium_economy', 'BUSINESS' => 'business' );
		$data = array( 'slices' => $lat, 'passengers' => $khach );
		if ( ! empty( $q['cabin'] ) && isset( $hang[ $q['cabin'] ] ) ) {
			$data['cabin_class'] = $hang[ $q['cabin'] ];
		}
		return array( 'data' => $data );
	}

	public static function tim_chuyen( $q ) {
		$token = dvr_cai_dat( 'duffel_token', '' );
		if ( ! $token ) {
			return new WP_Error( 'dvr_thieu_khoa', 'Chưa khai khoá Duffel trong Cài đặt.' );
		}
		$khoa_cache = 'dovere_duffel_' . md5( wp_json_encode( $q ) );
		$cu         = get_transient( $khoa_cache );
		if ( false !== $cu ) {
			return array( 'offers' => $cu, 'source' => 'duffel', 'cached' => true );
		}

		$r = wp_remote_post(
			self::GOC . '/air/offer_requests?return_offers=true&supplier_timeout=15000',
			array(
				'timeout' => 30,
				'headers' => self::dau( $token ),
				'body'    => wp_json_encode( self::goi_yeu_cau( $q ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$body = json_decode( wp_remote_retrieve_body( $r ), true );
		$ma   = wp_remote_retrieve_response_code( $r );
		if ( $ma >= 400 ) {
			$e = isset( $body['errors'][0] ) ? $body['errors'][0] : array();
			return new WP_Error( 'dvr_duffel', 'Duffel ' . $ma . ': '
				. ( isset( $e['message'] ) ? $e['message'] : ( isset( $e['title'] ) ? $e['title'] : 'không rõ lỗi' ) ) );
		}
		$chuyen = self::doi_du_lieu( $body, $q );
		set_transient( $khoa_cache, $chuyen, 5 * MINUTE_IN_SECONDS );
		return array( 'offers' => $chuyen, 'source' => 'duffel', 'cached' => false );
	}

	/** Đổi chào giá của Duffel sang đúng hình dạng bảng giá của trang. */
	/** Hãng giả Duffel dựng cho chế độ thử — không có thật, không được lọt ra trang khách. */
	const HANG_GIA = array( 'ZZ' );

	public static function co_hang_gia( $body ) {
		foreach ( (array) ( isset( $body['data']['offers'] ) ? $body['data']['offers'] : array() ) as $o ) {
			$ma = isset( $o['owner']['iata_code'] ) ? $o['owner']['iata_code'] : '';
			if ( in_array( $ma, self::HANG_GIA, true ) ) {
				return true;
			}
		}
		return false;
	}

	public static function doi_du_lieu( $body, $q = array() ) {
		$offers = isset( $body['data']['offers'] ) ? $body['data']['offers'] : ( isset( $body['data'] ) && isset( $body['data'][0] ) ? $body['data'] : array() );
		$ra     = array();
		$so     = max( 1, (int) ( isset( $q['adt'] ) ? $q['adt'] : 1 ) ) + (int) ( isset( $q['chd'] ) ? $q['chd'] : 0 );

		foreach ( (array) $offers as $o ) {
			$seg = isset( $o['slices'][0]['segments'] ) ? $o['slices'][0]['segments'] : array();
			if ( ! $seg ) {
				continue;
			}
			$dau  = $seg[0];
			$cuoi = $seg[ count( $seg ) - 1 ];
			$hang = isset( $dau['marketing_carrier'] ) ? $dau['marketing_carrier'] : ( isset( $o['owner'] ) ? $o['owner'] : array() );
			$ma   = isset( $hang['iata_code'] ) ? $hang['iata_code'] : '??';
			if ( in_array( $ma, self::HANG_GIA, true ) ) {
				continue;   // chuyến của hãng giả, bỏ qua
			}
			$di   = isset( $dau['departing_at'] ) ? $dau['departing_at'] : '';
			$den  = isset( $cuoi['arriving_at'] ) ? $cuoi['arriving_at'] : '';
			$tong = (float) ( isset( $o['total_amount'] ) ? $o['total_amount'] : 0 );

			// hành lý ký gửi: Duffel để trong passengers[].baggages của từng chặng
			$kien = 0;
			foreach ( $seg as $s ) {
				foreach ( (array) ( isset( $s['passengers'] ) ? $s['passengers'] : array() ) as $k ) {
					foreach ( (array) ( isset( $k['baggages'] ) ? $k['baggages'] : array() ) as $b ) {
						if ( isset( $b['type'] ) && 'checked' === $b['type'] ) {
							$kien = max( $kien, (int) $b['quantity'] );
						}
					}
				}
			}

			$cur_goc = isset( $o['total_currency'] ) ? $o['total_currency'] : 'VND';
			list( $gia_moi, $cur, $ghi_chu ) = dvr_quy_doi( $so > 0 ? $tong / $so : $tong, $cur_goc );
			list( $tong_moi, , )             = dvr_quy_doi( $tong, $cur_goc );

			$ra[] = array(
				'al'        => array(
					'code' => $ma,
					'name' => isset( $hang['name'] ) ? $hang['name'] : $ma,
					'site' => isset( DVR_Amadeus::SITE[ $ma ] ) ? DVR_Amadeus::SITE[ $ma ] : '',
				),
				'code'      => $ma . ( isset( $dau['marketing_carrier_flight_number'] ) ? $dau['marketing_carrier_flight_number'] : '' ),
				'dep'       => substr( $di, 11, 5 ),
				'arr'       => substr( $den, 11, 5 ),
				'overnight' => substr( $den, 0, 10 ) !== substr( $di, 0, 10 ),
				'mins'      => DVR_Amadeus::phut( isset( $o['slices'][0]['duration'] ) ? $o['slices'][0]['duration'] : '' )
					?: (int) round( ( strtotime( $den ) - strtotime( $di ) ) / 60 ),
				'stops'     => count( $seg ) - 1,
				'bag'       => $kien > 0,
				'bagText'   => $kien > 0 ? $kien . ' kiện ký gửi' : 'chỉ xách tay',
				'cabin'     => isset( $q['cabin'] ) ? $q['cabin'] : 'ECONOMY',
				'seller'    => array( 'name' => 'Duffel', 'note' => 'giá lấy thẳng từ hệ thống đặt chỗ' ),
				'price'     => $gia_moi,
				'total'     => $tong_moi,
				'cur'       => $cur,
				'goc'       => $ghi_chu,
				'depHour'   => (int) substr( $di, 11, 2 ),
			);
		}
		usort( $ra, function ( $a, $b ) {
			return $a['price'] <=> $b['price'];
		} );
		return $ra;
	}
}
