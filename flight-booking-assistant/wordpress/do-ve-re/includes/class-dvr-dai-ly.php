<?php
/**
 * Nguồn giá "đại lý cấp 1" — dùng cho API của phòng vé/consolidator trong nước.
 *
 * Mỗi đại lý một kiểu dữ liệu, nên thay vì viết cứng, file này đọc:
 *   - đường dẫn gọi API (có chỗ thay {from} {to} {dep} {ret} {adt} {chd} {inf} {cabin})
 *   - khoá và tên header mang khoá
 *   - bảng ánh xạ: trường nào của họ ứng với trường nào của mình
 * Tất cả khai trong Cài đặt, không phải sửa code khi đổi nhà cung cấp.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Dai_Ly {

	/** Lấy giá trị theo đường dẫn kiểu "data.flights.0.price"; không có thì trả mặc định. */
	public static function theo_duong( $mang, $duong, $mac_dinh = null ) {
		if ( '' === (string) $duong ) {
			return $mac_dinh;
		}
		$nut = $mang;
		foreach ( explode( '.', (string) $duong ) as $k ) {
			if ( is_array( $nut ) && array_key_exists( $k, $nut ) ) {
				$nut = $nut[ $k ];
			} else {
				return $mac_dinh;
			}
		}
		return $nut;
	}

	/** Thay {from}, {dep}… trong chuỗi mẫu bằng giá trị thật. */
	public static function thay_cho( $mau, $q ) {
		$the = array(
			'{from}'  => isset( $q['from'] ) ? $q['from'] : '',
			'{to}'    => isset( $q['to'] ) ? $q['to'] : '',
			'{dep}'   => isset( $q['dep'] ) ? $q['dep'] : '',
			'{ret}'   => isset( $q['ret'] ) ? $q['ret'] : '',
			'{adt}'   => (int) ( isset( $q['adt'] ) ? $q['adt'] : 1 ),
			'{chd}'   => (int) ( isset( $q['chd'] ) ? $q['chd'] : 0 ),
			'{inf}'   => (int) ( isset( $q['inf'] ) ? $q['inf'] : 0 ),
			'{cabin}' => isset( $q['cabin'] ) ? $q['cabin'] : 'ECONOMY',
		);
		return strtr( (string) $mau, $the );
	}

	private static function bang_anh_xa() {
		$mac = array(
			'duong_dan' => 'data',
			'hang'      => 'airline',
			'ten_hang'  => 'airlineName',
			'so_hieu'   => 'flightNumber',
			'gio_di'    => 'departTime',
			'gio_den'   => 'arriveTime',
			'gia'       => 'price',
			'tien_te'   => 'currency',
			'diem_dung' => 'stops',
			'ky_gui'    => 'baggage',
		);
		$khai = json_decode( (string) dvr_cai_dat( 'dl_map', '' ), true );
		return is_array( $khai ) ? array_merge( $mac, $khai ) : $mac;
	}

	public static function tim_chuyen( $q ) {
		$url = dvr_cai_dat( 'dl_url', '' );
		if ( ! $url ) {
			return new WP_Error( 'dvr_thieu_url', 'Chưa khai đường dẫn API của đại lý trong Cài đặt.' );
		}
		$kq = self::goi( $q );
		if ( is_wp_error( $kq ) ) {
			return $kq;
		}
		return array( 'offers' => self::doi_du_lieu( $kq, $q ), 'source' => 'dai_ly', 'cached' => false );
	}

	/** Gọi API của đại lý, trả về mảng đã giải mã JSON (hoặc WP_Error). */
	public static function goi( $q ) {
		$url    = self::thay_cho( dvr_cai_dat( 'dl_url', '' ), $q );
		$token  = dvr_cai_dat( 'dl_token', '' );
		$ten_hd = dvr_cai_dat( 'dl_header', 'Authorization' );
		$dau    = array( 'Accept' => 'application/json', 'Content-Type' => 'application/json' );
		if ( $token ) {
			$dau[ $ten_hd ] = 'Authorization' === $ten_hd ? 'Bearer ' . $token : $token;
		}
		$than = dvr_cai_dat( 'dl_body', '' );

		$r = $than
			? wp_remote_post( $url, array( 'timeout' => 30, 'headers' => $dau, 'body' => self::thay_cho( $than, $q ) ) )
			: wp_remote_get( $url, array( 'timeout' => 30, 'headers' => $dau ) );

		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ma   = wp_remote_retrieve_response_code( $r );
		$body = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( $ma >= 400 ) {
			return new WP_Error( 'dvr_dai_ly', 'Đại lý trả về ' . $ma . ': ' . substr( wp_remote_retrieve_body( $r ), 0, 300 ) );
		}
		if ( null === $body ) {
			return new WP_Error( 'dvr_dai_ly_json', 'Đại lý trả về thứ không phải JSON: ' . substr( wp_remote_retrieve_body( $r ), 0, 300 ) );
		}
		return $body;
	}

	/** Đổi dữ liệu của đại lý sang hình dạng bảng giá, theo bảng ánh xạ đã khai. */
	public static function doi_du_lieu( $body, $q = array() ) {
		$m    = self::bang_anh_xa();
		$ds   = self::theo_duong( $body, $m['duong_dan'], array() );
		$ra   = array();
		$gio  = function ( $v ) {
			$v = (string) $v;
			if ( preg_match( '/(\d{2}):(\d{2})/', $v, $x ) ) {
				return $x[1] . ':' . $x[2];
			}
			return '';
		};
		foreach ( (array) $ds as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$ma   = (string) self::theo_duong( $c, $m['hang'], '??' );
			$di   = (string) self::theo_duong( $c, $m['gio_di'], '' );
			$den  = (string) self::theo_duong( $c, $m['gio_den'], '' );
			$gia  = (float) self::theo_duong( $c, $m['gia'], 0 );
			$dung = (int) self::theo_duong( $c, $m['diem_dung'], 0 );
			$kien = self::theo_duong( $c, $m['ky_gui'], 0 );
			$bag  = is_numeric( $kien ) ? (int) $kien > 0 : ( '' !== trim( (string) $kien ) && 'no' !== strtolower( (string) $kien ) );

			$phut = ( strtotime( $den ) && strtotime( $di ) ) ? (int) round( ( strtotime( $den ) - strtotime( $di ) ) / 60 ) : 0;
			if ( $phut < 0 ) {
				$phut += 1440;   // hạ cánh hôm sau
			}

			$ra[] = array(
				'al'        => array(
					'code' => $ma,
					'name' => (string) self::theo_duong( $c, $m['ten_hang'], $ma ),
					'site' => isset( DVR_Amadeus::SITE[ $ma ] ) ? DVR_Amadeus::SITE[ $ma ] : '',
				),
				'code'      => $ma . self::theo_duong( $c, $m['so_hieu'], '' ),
				'dep'       => $gio( $di ),
				'arr'       => $gio( $den ),
				'overnight' => ( strtotime( $di ) && strtotime( $den ) && substr( $den, 0, 10 ) !== substr( $di, 0, 10 ) ),
				'mins'      => $phut,
				'stops'     => $dung,
				'bag'       => $bag,
				'bagText'   => $bag ? ( is_numeric( $kien ) ? (int) $kien . ' kiện ký gửi' : (string) $kien ) : 'chỉ xách tay',
				'cabin'     => isset( $q['cabin'] ) ? $q['cabin'] : 'ECONOMY',
				'seller'    => array( 'name' => dvr_cai_dat( 'dl_ten', 'Đại lý' ), 'note' => 'giá đại lý, xuất vé được' ),
				'price'     => (int) round( $gia ),
				'total'     => (int) round( $gia * max( 1, (int) ( isset( $q['adt'] ) ? $q['adt'] : 1 ) + (int) ( isset( $q['chd'] ) ? $q['chd'] : 0 ) ) ),
				'cur'       => (string) self::theo_duong( $c, $m['tien_te'], 'VND' ),
				'depHour'   => (int) substr( $gio( $di ), 0, 2 ),
			);
		}
		usort( $ra, function ( $a, $b ) {
			return $a['price'] <=> $b['price'];
		} );
		return $ra;
	}
}
