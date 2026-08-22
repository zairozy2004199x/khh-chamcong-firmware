<?php
/**
 * DỰNG CHUỖI VietQR (EMVCo) — bản dịch `buildVietQR` / `_tlv` / `_crc16` của Code.gs.
 *
 * Chuỗi này in ra tem dán trên ghế. In sai một ký tự là khách quét không ra, hoặc tệ hơn: ra
 * SỐ TIỀN KHÁC. Nên phần này là hàm thuần, và có phép thử bằng con số.
 *
 * ⚠️ CRC16/CCITT-FALSE, khởi tạo 0xFFFF, đa thức 0x1021, KHÔNG đảo bit, KHÔNG xor cuối. Đây là
 *    biến thể EMVCo dùng; các biến thể CRC16 khác cho ra chuỗi khác và điện thoại từ chối quét.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_QR {

	/** Một trường EMVCo: mã 2 số + độ dài 2 số + giá trị. */
	public static function tlv( $ma, $gia_tri ) {
		$gia_tri = (string) $gia_tri;
		return $ma . substr( '0' . strlen( $gia_tri ), -2 ) . $gia_tri;
	}

	public static function crc16( $s ) {
		$crc = 0xFFFF;
		$n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) {
			$crc ^= ( ord( $s[ $i ] ) & 0xFF ) << 8;
			for ( $b = 0; $b < 8; $b++ ) {
				$crc = ( $crc & 0x8000 ) ? ( ( $crc << 1 ) ^ 0x1021 ) : ( $crc << 1 );
				$crc &= 0xFFFF;
			}
		}
		return substr( '000' . strtoupper( dechex( $crc ) ), -4 );
	}

	/**
	 * Chuỗi VietQR chuyển khoản nhanh (QRIBFTTA).
	 * `$so_tien` = 0 nghĩa là QR để khách tự nhập số tiền.
	 */
	public static function dung( $bank_bin, $so_tk, $so_tien, $noi_dung ) {
		$s  = self::tlv( '00', '01' );
		$s .= self::tlv( '01', $so_tien ? '12' : '11' );   // 12 = QR dùng MỘT LẦN (có số tiền)
		$ben = self::tlv( '00', (string) $bank_bin ) . self::tlv( '01', (string) $so_tk );
		$s .= self::tlv( '38', self::tlv( '00', 'A000000727' ) . self::tlv( '01', $ben )
			. self::tlv( '02', 'QRIBFTTA' ) );
		$s .= self::tlv( '53', '704' );                    // 704 = VND
		if ( $so_tien ) { $s .= self::tlv( '54', (string) (int) $so_tien ); }
		$s .= self::tlv( '58', 'VN' );
		if ( '' !== (string) $noi_dung ) { $s .= self::tlv( '62', self::tlv( '08', (string) $noi_dung ) ); }
		$s .= '6304';
		return $s . self::crc16( $s );
	}

	/**
	 * Chuỗi QR cho một ghế, kèm mã lượt.
	 *
	 * ⚠️ Nội dung phải đúng khuôn `GHE<mã ghế> <mã lượt>` — đó là thứ `VHG_Doc::ghe_va_ma()` đọc
	 *    lại khi tiền về. Đổi khuôn ở một bên mà quên bên kia là tiền vào mà ghế không chạy.
	 */
	public static function cho_ghe( $ma_may, $ma_lenh = '' ) {
		$m = VHG_May::may( $ma_may );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Chưa khai máy ' . $ma_may . '.' ); }
		if ( '' === trim( (string) $m['so_tk'] ) ) {
			return array( 'ok' => false, 'error' => 'Máy ' . $ma_may . ' chưa khai số tài khoản/VA nhận tiền.' );
		}
		if ( '' === trim( (string) $m['bank_bin'] ) ) {
			return array( 'ok' => false, 'error' => 'Máy ' . $ma_may . ' chưa khai mã ngân hàng (BIN).' );
		}
		$ma_lenh = '' !== $ma_lenh ? $ma_lenh : self::ma_luot();
		$nd = 'GHE' . $m['ma'] . ' ' . $ma_lenh;
		return array( 'ok' => true, 'chuoi' => self::dung( $m['bank_bin'], $m['so_tk'], (int) $m['gia'], $nd ),
			'noi_dung' => $nd, 'so_tien' => (int) $m['gia'], 'ma_lenh' => $ma_lenh );
	}

	/**
	 * Mã lượt ngẫu nhiên, 5 ký tự CHỮ HOA + SỐ.
	 * ⚠️ Bỏ các ký tự nhìn giống nhau (O/0, I/1) — khách phải gõ tay chuỗi này vào nội dung
	 *    chuyển khoản khi app ngân hàng không tự điền. Gõ nhầm là tiền vào mà ghế không chạy.
	 */
	public static function ma_luot() {
		$chu = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$ra = '';
		for ( $i = 0; $i < 5; $i++ ) { $ra .= $chu[ random_int( 0, strlen( $chu ) - 1 ) ]; }
		return $ra;
	}
}
