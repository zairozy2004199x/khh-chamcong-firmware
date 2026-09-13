<?php
/** Hỏi giá thật từ Amadeus và tra mã số thuế. Khoá nằm trong cài đặt, không lộ ra trình duyệt. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Amadeus {

	const SITE = array(
		'VN' => 'https://www.vietnamairlines.com/vn/vi/home',
		'VJ' => 'https://www.vietjetair.com/vi',
		'QH' => 'https://www.bambooairways.com/vn/vi/',
		'VU' => 'https://www.vietravelairlines.com/vi',
	);

	private static function goc() {
		return 'production' === dvr_cai_dat( 'amadeus_env', 'test' )
			? 'https://api.amadeus.com'
			: 'https://test.api.amadeus.com';
	}

	/** Token dùng lại tới khi hết hạn, cất trong transient. */
	public static function token() {
		$cu = get_transient( 'dovere_amadeus_token' );
		if ( $cu ) {
			return $cu;
		}
		$id  = dvr_cai_dat( 'amadeus_id', '' );
		$key = dvr_cai_dat( 'amadeus_secret', '' );
		if ( ! $id || ! $key ) {
			return new WP_Error( 'dvr_thieu_khoa', 'Chưa khai khoá Amadeus trong Cài đặt.' );
		}
		$r = wp_remote_post(
			self::goc() . '/v1/security/oauth2/token',
			array(
				'timeout' => 20,
				'headers' => array( 'content-type' => 'application/x-www-form-urlencoded' ),
				'body'    => array( 'grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $key ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$j = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $j['access_token'] ) ) {
			return new WP_Error( 'dvr_khoa_sai', 'Amadeus từ chối khoá: ' . ( isset( $j['error_description'] ) ? $j['error_description'] : 'không rõ' ) );
		}
		set_transient( 'dovere_amadeus_token', $j['access_token'], max( 60, (int) $j['expires_in'] - 60 ) );
		return $j['access_token'];
	}

	public static function tim_chuyen( $q ) {
		$khoa_cache = 'dovere_off_' . md5( wp_json_encode( $q ) );
		$cu         = get_transient( $khoa_cache );
		if ( false !== $cu ) {
			return array( 'offers' => $cu, 'source' => 'amadeus', 'cached' => true );
		}
		$token = self::token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$tham = array(
			'originLocationCode'      => $q['from'],
			'destinationLocationCode' => $q['to'],
			'departureDate'           => $q['dep'],
			'adults'                  => max( 1, (int) $q['adt'] ),
			'currencyCode'            => 'VND',
			'max'                     => 25,
		);
		if ( ! empty( $q['ret'] ) ) {
			$tham['returnDate'] = $q['ret'];
		}
		if ( ! empty( $q['chd'] ) ) {
			$tham['children'] = (int) $q['chd'];
		}
		if ( ! empty( $q['inf'] ) ) {
			$tham['infants'] = (int) $q['inf'];
		}
		if ( ! empty( $q['cabin'] ) ) {
			$tham['travelClass'] = $q['cabin'];
		}
		if ( ! empty( $q['direct'] ) ) {
			$tham['nonStop'] = 'true';
		}

		$r = wp_remote_get(
			add_query_arg( $tham, self::goc() . '/v2/shopping/flight-offers' ),
			array( 'timeout' => 25, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) )
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$body = json_decode( wp_remote_retrieve_body( $r ), true );
		$ma   = wp_remote_retrieve_response_code( $r );
		if ( $ma >= 400 ) {
			$e = isset( $body['errors'][0] ) ? $body['errors'][0] : array();
			return new WP_Error( 'dvr_amadeus', 'Amadeus ' . $ma . ': ' . ( isset( $e['detail'] ) ? $e['detail'] : ( isset( $e['title'] ) ? $e['title'] : 'không rõ lỗi' ) ) );
		}
		$chuyen = self::doi_du_lieu( $body, $q );
		set_transient( $khoa_cache, $chuyen, 5 * MINUTE_IN_SECONDS );
		return array( 'offers' => $chuyen, 'source' => 'amadeus', 'cached' => false );
	}

	/** Đổi dữ liệu Amadeus v2 sang đúng hình dạng bảng giá của trang. */
	public static function doi_du_lieu( $body, $q = array() ) {
		$hang_bay = isset( $body['dictionaries']['carriers'] ) ? $body['dictionaries']['carriers'] : array();
		$ra       = array();
		foreach ( (array) ( isset( $body['data'] ) ? $body['data'] : array() ) as $o ) {
			$it = isset( $o['itineraries'][0]['segments'] ) ? $o['itineraries'][0]['segments'] : array();
			if ( ! $it ) {
				continue;
			}
			$dau  = $it[0];
			$cuoi = $it[ count( $it ) - 1 ];
			$ma   = ! empty( $dau['carrierCode'] ) ? $dau['carrierCode'] : ( isset( $o['validatingAirlineCodes'][0] ) ? $o['validatingAirlineCodes'][0] : '??' );
			$tp   = isset( $o['travelerPricings'][0] ) ? $o['travelerPricings'][0] : array();
			$fd   = isset( $tp['fareDetailsBySegment'][0] ) ? $tp['fareDetailsBySegment'][0] : array();
			$kien = isset( $fd['includedCheckedBags']['quantity'] ) ? (int) $fd['includedCheckedBags']['quantity'] : 0;
			$moi  = isset( $tp['price']['total'] ) ? (float) $tp['price']['total'] : 0;
			$tong = isset( $o['price']['grandTotal'] ) ? (float) $o['price']['grandTotal'] : ( isset( $o['price']['total'] ) ? (float) $o['price']['total'] : 0 );
			$di   = isset( $dau['departure']['at'] ) ? $dau['departure']['at'] : '';
			$den  = isset( $cuoi['arrival']['at'] ) ? $cuoi['arrival']['at'] : '';

			$phut = self::phut( isset( $o['itineraries'][0]['duration'] ) ? $o['itineraries'][0]['duration'] : '' );
			if ( ! $phut ) {
				foreach ( $it as $s ) {
					$phut += self::phut( isset( $s['duration'] ) ? $s['duration'] : '' );
				}
			}

			$cur_goc = isset( $o['price']['currency'] ) ? $o['price']['currency'] : 'VND';
			list( $gia_moi, $cur, $ghi_chu ) = dvr_quy_doi( $moi, $cur_goc );
			list( $tong_moi, , )             = dvr_quy_doi( $tong, $cur_goc );

			$ra[] = array(
				'al'        => array(
					'code' => $ma,
					'name' => isset( $hang_bay[ $ma ] ) ? self::hoa_dau( $hang_bay[ $ma ] ) : $ma,
					'site' => isset( self::SITE[ $ma ] ) ? self::SITE[ $ma ] : '',
				),
				'code'      => $ma . ( isset( $dau['number'] ) ? $dau['number'] : '' ),
				'dep'       => substr( $di, 11, 5 ),
				'arr'       => substr( $den, 11, 5 ),
				'overnight' => substr( $den, 0, 10 ) !== substr( $di, 0, 10 ),
				'mins'      => $phut,
				'stops'     => count( $it ) - 1,
				'bag'       => $kien > 0,
				'bagText'   => $kien > 0 ? $kien . ' kiện ký gửi' : 'chỉ xách tay',
				'cabin'     => isset( $fd['cabin'] ) ? $fd['cabin'] : ( isset( $q['cabin'] ) ? $q['cabin'] : 'ECONOMY' ),
				'seller'    => array( 'name' => 'Amadeus (GDS)', 'note' => 'giá GDS' ),
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

	/** PT2H10M -> 130 phút. */
	public static function phut( $iso ) {
		if ( ! preg_match( '/P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?/', (string) $iso, $m ) ) {
			return 0;
		}
		return ( isset( $m[1] ) ? (int) $m[1] : 0 ) * 1440 + ( isset( $m[2] ) ? (int) $m[2] : 0 ) * 60 + ( isset( $m[3] ) ? (int) $m[3] : 0 );
	}

	private static function hoa_dau( $s ) {
		return ucwords( strtolower( (string) $s ) );
	}

	/** Tra tên và địa chỉ doanh nghiệp theo mã số thuế. */
	public static function tra_mst( $mst ) {
		$mst = preg_replace( '/[^\d-]/', '', (string) $mst );
		if ( ! preg_match( '/^\d{10}(-\d{3})?$/', $mst ) ) {
			return new WP_Error( 'dvr_mst', 'Mã số thuế phải là 10 số (hoặc 10-3 số cho chi nhánh).' );
		}
		$cu = get_transient( 'dovere_mst_' . $mst );
		if ( $cu ) {
			return $cu;
		}
		$r = wp_remote_get( dvr_cai_dat( 'tax_api' ) . rawurlencode( $mst ), array( 'timeout' => 15 ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$j = json_decode( wp_remote_retrieve_body( $r ), true );
		$d = isset( $j['data'] ) ? $j['data'] : $j;
		$ten = isset( $d['name'] ) ? $d['name'] : '';
		if ( ! $ten ) {
			return new WP_Error( 'dvr_mst_khong_thay', 'Không tra được mã số thuế ' . $mst );
		}
		$ra = array(
			'tax'     => $mst,
			'company' => $ten,
			'address' => isset( $d['address'] ) ? $d['address'] : '',
		);
		set_transient( 'dovere_mst_' . $mst, $ra, DAY_IN_SECONDS );
		return $ra;
	}
}
