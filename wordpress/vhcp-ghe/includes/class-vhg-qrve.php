<?php
/**
 * BỘ VẼ MÃ QR — dựng ma trận QR rồi xuất ra SVG, để IN TEM DÁN LÊN GHẾ.
 *
 * =============================================================================================
 * VÌ SAO PHẢI TỰ VIẾT
 * =============================================================================================
 * Cần một tấm tem cho MỖI ghế, mang đúng địa chỉ của ghế đó. Ba đường khác đều tệ hơn:
 *   · Gọi dịch vụ vẽ QR bên ngoài — tem là thứ dán lên ghế nhiều năm; hôm nào dịch vụ đó đổi
 *     đường dẫn hay ngừng chạy thì mình không in lại được, mà tem cũ vẫn nằm trên ghế.
 *   · Nhúng thư viện JavaScript — trang quản trị phải chạy được cả khi không có mạng ra ngoài.
 *   · Bảo chủ tự vào một trang tạo QR nào đó gõ tay địa chỉ — gõ nhầm một ký tự là cả một cửa
 *     hàng dán tem dẫn đi đâu không rõ, và không ai phát hiện cho tới khi khách kêu.
 *
 * =============================================================================================
 * ⚠️ PHẠM VI CÓ GIỚI HẠN, CỐ Ý
 * =============================================================================================
 * Chỉ làm đúng phần cần cho việc này, và làm cho đúng:
 *   · Chế độ BYTE và ALPHANUMERIC (địa chỉ viết hoa rơi vào alphanumeric — đặc hơn).
 *   · Mức sửa lỗi L..H, version 1..10.
 *   · Chọn mặt nạ theo đúng luật chấm điểm của chuẩn, không gán cứng một mặt nạ.
 * Quá tầm thì TRẢ VỀ RỖNG và nơi gọi phải nói ra — không bao giờ vẽ một mã "gần đúng".
 *
 * 🔴 TỰ VIẾT THÌ PHẢI TỰ CHỨNG MINH. Kèm theo là `VHG_QRVe::doc()` — bộ ĐỌC NGƯỢC ma trận về lại
 *    chuỗi, viết độc lập theo cùng bản chuẩn. Phép thử bắt mọi mã dựng ra phải đọc ngược đúng
 *    chuỗi ban đầu. Không có nó thì "tem in ra chắc là quét được" chỉ là một lời chúc.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_QRVe {

	/** Sức chứa (số ký tự) theo [version][mức sửa lỗi][chế độ]. Chỉ tới version 10. */
	const MUC = array( 'L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3 );

	/** Số codeword sửa lỗi mỗi khối, và số khối — [version][mức] => array(ecc_moi_khoi, so_khoi_g1, so_khoi_g2). */
	private static function bang_khoi() {
		return array(
			1  => array( 'L' => array( 7, 1, 0 ),  'M' => array( 10, 1, 0 ), 'Q' => array( 13, 1, 0 ), 'H' => array( 17, 1, 0 ) ),
			2  => array( 'L' => array( 10, 1, 0 ), 'M' => array( 16, 1, 0 ), 'Q' => array( 22, 1, 0 ), 'H' => array( 28, 1, 0 ) ),
			3  => array( 'L' => array( 15, 1, 0 ), 'M' => array( 26, 1, 0 ), 'Q' => array( 18, 2, 0 ), 'H' => array( 22, 2, 0 ) ),
			4  => array( 'L' => array( 20, 1, 0 ), 'M' => array( 18, 2, 0 ), 'Q' => array( 26, 2, 0 ), 'H' => array( 16, 4, 0 ) ),
			5  => array( 'L' => array( 26, 1, 0 ), 'M' => array( 24, 2, 0 ), 'Q' => array( 18, 2, 2 ), 'H' => array( 22, 2, 2 ) ),
			6  => array( 'L' => array( 18, 2, 0 ), 'M' => array( 16, 4, 0 ), 'Q' => array( 24, 4, 0 ), 'H' => array( 28, 4, 0 ) ),
			7  => array( 'L' => array( 20, 2, 0 ), 'M' => array( 18, 4, 0 ), 'Q' => array( 18, 2, 4 ), 'H' => array( 26, 4, 1 ) ),
			8  => array( 'L' => array( 24, 2, 0 ), 'M' => array( 22, 2, 2 ), 'Q' => array( 22, 4, 2 ), 'H' => array( 26, 4, 2 ) ),
			9  => array( 'L' => array( 30, 2, 0 ), 'M' => array( 22, 3, 2 ), 'Q' => array( 20, 4, 4 ), 'H' => array( 24, 4, 4 ) ),
			10 => array( 'L' => array( 18, 2, 2 ), 'M' => array( 26, 4, 1 ), 'Q' => array( 24, 6, 2 ), 'H' => array( 28, 6, 2 ) ),
		);
	}

	/** Tổng số codeword của một version (dữ liệu + sửa lỗi). */
	private static function tong_codeword( $ver ) {
		$t = array( 1 => 26, 2 => 44, 3 => 70, 4 => 100, 5 => 134, 6 => 172,
			7 => 196, 8 => 242, 9 => 292, 10 => 346 );
		return isset( $t[ $ver ] ) ? $t[ $ver ] : 0;
	}

	/** Toạ độ tâm các ô căn chỉnh, theo version. */
	private static function tam_can( $ver ) {
		$t = array( 1 => array(), 2 => array( 6, 18 ), 3 => array( 6, 22 ), 4 => array( 6, 26 ),
			5 => array( 6, 30 ), 6 => array( 6, 34 ), 7 => array( 6, 22, 38 ), 8 => array( 6, 24, 42 ),
			9 => array( 6, 26, 46 ), 10 => array( 6, 28, 50 ) );
		return isset( $t[ $ver ] ) ? $t[ $ver ] : array();
	}

	// ===================================================================== số học GF(256)

	private static $log = null;
	private static $alog = null;

	/**
	 * Bảng luỹ thừa/logarit của GF(256) với đa thức sinh 0x11D — đúng bản chuẩn QR dùng.
	 * Dựng một lần rồi giữ: mỗi tấm tem gọi hàng nghìn phép nhân.
	 */
	private static function gf() {
		if ( null !== self::$log ) { return; }
		self::$log = array_fill( 0, 256, 0 );
		self::$alog = array_fill( 0, 256, 0 );
		$x = 1;
		for ( $i = 0; $i < 255; $i++ ) {
			self::$alog[ $i ] = $x;
			self::$log[ $x ] = $i;
			$x <<= 1;
			if ( $x & 0x100 ) { $x ^= 0x11D; }
		}
	}

	private static function gf_nhan( $a, $b ) {
		if ( 0 === $a || 0 === $b ) { return 0; }
		self::gf();
		return self::$alog[ ( self::$log[ $a ] + self::$log[ $b ] ) % 255 ];
	}

	/**
	 * Đa thức sinh Reed-Solomon cho `n` codeword sửa lỗi: tích của (x - α^i).
	 * ⚠️ Tính chứ KHÔNG chép bảng: chép bảng là chép cả lỗi gõ, mà một hệ số sai thì mã vẫn dựng
	 *    ra được và vẫn nhìn như thật — chỉ là máy quét từ chối. Phép thử đối chiếu kết quả hàm
	 *    này với bộ hệ số đã công bố cho n=7 và n=10.
	 */
	public static function da_thuc_sinh( $n ) {
		self::gf();
		$g = array( 1 );
		for ( $i = 0; $i < $n; $i++ ) {
			$moi = array_fill( 0, count( $g ) + 1, 0 );
			foreach ( $g as $k => $he ) {
				$moi[ $k ]     ^= self::gf_nhan( $he, self::$alog[ $i ] );
				$moi[ $k + 1 ] ^= $he;
			}
			/* Nhân với (x + α^i): hệ số bậc cao dịch sang, hệ số thấp nhân α^i. */
			$g = $moi;
		}
		/* Đảo về thứ tự bậc GIẢM DẦN (hệ số 1 đứng đầu) — cùng thứ tự với chuỗi codeword, và
		   cùng thứ tự với bộ hệ số đã công bố mà phép thử đối chiếu. */
		return array_reverse( $g );
	}

	/** Codeword sửa lỗi của một khối dữ liệu. */
	public static function ecc( $du_lieu, $n ) {
		$g = self::da_thuc_sinh( $n );
		$du = array_merge( array_values( $du_lieu ), array_fill( 0, $n, 0 ) );
		$len = count( $du_lieu );
		for ( $i = 0; $i < $len; $i++ ) {
			$he = $du[ $i ];
			if ( 0 === $he ) { continue; }
			foreach ( $g as $k => $gk ) {
				$du[ $i + $k ] ^= self::gf_nhan( $gk, $he );
			}
		}
		return array_slice( $du, $len, $n );
	}

	// ===================================================================== mã hoá dữ liệu

	const AN = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

	public static function la_alnum( $s ) {
		$n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( false === strpos( self::AN, $s[ $i ] ) ) { return false; }
		}
		return $n > 0;
	}

	/** Số codeword DỮ LIỆU của một version+mức. */
	public static function so_cw_du_lieu( $ver, $muc ) {
		$b = self::bang_khoi();
		if ( ! isset( $b[ $ver ][ $muc ] ) ) { return 0; }
		list( $ecc, $k1, $k2 ) = $b[ $ver ][ $muc ];
		return self::tong_codeword( $ver ) - $ecc * ( $k1 + $k2 );
	}

	/** Version nhỏ nhất chứa nổi chuỗi này. 0 = không version nào (tới 10) chứa nổi. */
	public static function chon_version( $s, $muc ) {
		$alnum = self::la_alnum( $s );
		for ( $v = 1; $v <= 10; $v++ ) {
			$bit_dai = $alnum ? ( $v <= 9 ? 9 : 11 ) : ( $v <= 9 ? 8 : 16 );
			$bit_du  = $alnum
				? ( intdiv( strlen( $s ), 2 ) * 11 + ( ( strlen( $s ) % 2 ) ? 6 : 0 ) )
				: strlen( $s ) * 8;
			if ( 4 + $bit_dai + $bit_du <= self::so_cw_du_lieu( $v, $muc ) * 8 ) { return $v; }
		}
		return 0;
	}

	/** Chuỗi -> mảng codeword dữ liệu (đã đệm đủ). */
	public static function ma_hoa( $s, $ver, $muc ) {
		$alnum = self::la_alnum( $s );
		$bit = '';
		$bit .= $alnum ? '0010' : '0100';
		$bit_dai = $alnum ? ( $ver <= 9 ? 9 : 11 ) : ( $ver <= 9 ? 8 : 16 );
		$bit .= str_pad( decbin( strlen( $s ) ), $bit_dai, '0', STR_PAD_LEFT );

		if ( $alnum ) {
			for ( $i = 0; $i < strlen( $s ); $i += 2 ) {
				if ( $i + 1 < strlen( $s ) ) {
					$v = strpos( self::AN, $s[ $i ] ) * 45 + strpos( self::AN, $s[ $i + 1 ] );
					$bit .= str_pad( decbin( $v ), 11, '0', STR_PAD_LEFT );
				} else {
					$bit .= str_pad( decbin( strpos( self::AN, $s[ $i ] ) ), 6, '0', STR_PAD_LEFT );
				}
			}
		} else {
			for ( $i = 0; $i < strlen( $s ); $i++ ) {
				$bit .= str_pad( decbin( ord( $s[ $i ] ) ), 8, '0', STR_PAD_LEFT );
			}
		}

		$tong_bit = self::so_cw_du_lieu( $ver, $muc ) * 8;
		/* Dấu kết thúc tối đa 4 bit, rồi đệm cho tròn byte, rồi đệm EC/11 luân phiên. */
		$bit .= str_repeat( '0', min( 4, $tong_bit - strlen( $bit ) ) );
		while ( strlen( $bit ) % 8 ) { $bit .= '0'; }
		$dem = array( 0xEC, 0x11 ); $k = 0;
		while ( strlen( $bit ) < $tong_bit ) {
			$bit .= str_pad( decbin( $dem[ $k % 2 ] ), 8, '0', STR_PAD_LEFT );
			$k++;
		}
		$cw = array();
		for ( $i = 0; $i < strlen( $bit ); $i += 8 ) { $cw[] = bindec( substr( $bit, $i, 8 ) ); }
		return $cw;
	}

	/**
	 * Xen kẽ khối dữ liệu và khối sửa lỗi theo đúng luật của chuẩn.
	 * ⚠️ Xen kẽ SAI thì mã vẫn dựng ra được, vẫn nhìn như thật, và máy quét đọc ra rác. Đây là
	 *    chỗ dễ sai nhất của cả tệp — nên bộ đọc ngược ở dưới cũng phải tự tháo xen kẽ, và phép
	 *    thử bắt hai bên gặp nhau.
	 */
	public static function xen_ke( $cw, $ver, $muc ) {
		$b = self::bang_khoi();
		list( $n_ecc, $k1, $k2 ) = $b[ $ver ][ $muc ];
		$tong_khoi = $k1 + $k2;
		$cw_g1 = intdiv( self::so_cw_du_lieu( $ver, $muc ), $tong_khoi );
		$khoi = array(); $khoi_ecc = array(); $vt = 0;
		for ( $i = 0; $i < $tong_khoi; $i++ ) {
			$n = $cw_g1 + ( $i >= $k1 ? 1 : 0 );
			$kh = array_slice( $cw, $vt, $n );
			$vt += $n;
			$khoi[] = $kh;
			$khoi_ecc[] = self::ecc( $kh, $n_ecc );
		}
		$ra = array();
		$dai_nhat = 0;
		foreach ( $khoi as $kh ) { $dai_nhat = max( $dai_nhat, count( $kh ) ); }
		for ( $i = 0; $i < $dai_nhat; $i++ ) {
			foreach ( $khoi as $kh ) { if ( isset( $kh[ $i ] ) ) { $ra[] = $kh[ $i ]; } }
		}
		for ( $i = 0; $i < $n_ecc; $i++ ) {
			foreach ( $khoi_ecc as $kh ) { if ( isset( $kh[ $i ] ) ) { $ra[] = $kh[ $i ]; } }
		}
		return $ra;
	}

	// ===================================================================== dựng ma trận

	/** Ma trận trống + các hình cố định. Trả về array( $o, $cam ) — `$cam` đánh dấu ô không đặt dữ liệu. */
	private static function khung( $ver ) {
		$n = 17 + 4 * $ver;
		$o   = array_fill( 0, $n, array_fill( 0, $n, 0 ) );
		$cam = array_fill( 0, $n, array_fill( 0, $n, false ) );

		$dat = function ( $x, $y, $v ) use ( &$o, &$cam, $n ) {
			if ( $x < 0 || $y < 0 || $x >= $n || $y >= $n ) { return; }
			$o[ $y ][ $x ] = $v ? 1 : 0;
			$cam[ $y ][ $x ] = true;
		};

		/* Ba ô định vị + dải trắng quanh chúng. */
		foreach ( array( array( 0, 0 ), array( $n - 7, 0 ), array( 0, $n - 7 ) ) as $g ) {
			list( $gx, $gy ) = $g;
			for ( $dy = -1; $dy <= 7; $dy++ ) {
				for ( $dx = -1; $dx <= 7; $dx++ ) {
					$x = $gx + $dx; $y = $gy + $dy;
					if ( $x < 0 || $y < 0 || $x >= $n || $y >= $n ) { continue; }
					$trong = ( $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6 );
					$den = $trong && ( 0 === $dx || 6 === $dx || 0 === $dy || 6 === $dy
						|| ( $dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4 ) );
					$dat( $x, $y, $den );
				}
			}
		}

		/* Hai dải nhịp. */
		for ( $i = 8; $i < $n - 8; $i++ ) {
			$dat( $i, 6, 0 === $i % 2 );
			$dat( 6, $i, 0 === $i % 2 );
		}

		/* Ô căn chỉnh — bỏ những ô đè lên ô định vị. */
		$tam = self::tam_can( $ver );
		foreach ( $tam as $cy ) {
			foreach ( $tam as $cx ) {
				if ( ( 6 === $cx && 6 === $cy ) || ( 6 === $cx && $cy === $n - 7 )
					|| ( $cx === $n - 7 && 6 === $cy ) ) { continue; }
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$den = ( 2 === max( abs( $dx ), abs( $dy ) ) ) || ( 0 === $dx && 0 === $dy );
						$dat( $cx + $dx, $cy + $dy, $den );
					}
				}
			}
		}

		/* ===== Chừa chỗ cho thông tin định dạng, rồi ô tối cố định ==================
		 * 🔴 HAI CHỖ DỄ GIẪM LÊN THỨ KHÁC, và phép thử cấu trúc bắt được cả hai (bộ đọc ngược thì
		 *    KHÔNG — nó chỉ chứng minh vẽ và đọc tự nhất quán, không chứng minh đúng chuẩn):
		 *
		 *    1. Ô (6,8) và (8,6) thuộc DẢI NHỊP, không thuộc vùng định dạng. Quét cả dải 0..8 là
		 *       xoá mất hai ô nhịp — mà dải nhịp chính là thước đo cỡ module của máy quét.
		 *    2. Dải định dạng dọc ở góc dưới-trái chỉ có BẢY ô (n-1 lên n-7). Quét tám ô là chạm
		 *       tới (8, n-8) — đúng chỗ Ô TỐI CỐ ĐỊNH, và xoá nó thành trắng.
		 *
		 *    Cả hai đều cho ra một mã "nhìn như thật" mà máy quét khó hoặc không đọc được. */
		for ( $i = 0; $i <= 8; $i++ ) {
			if ( 6 === $i ) { continue; }          // ô nhịp, không phải vùng định dạng
			$dat( $i, 8, 0 );
			$dat( 8, $i, 0 );
		}
		for ( $i = 0; $i < 8; $i++ ) { $dat( $n - 1 - $i, 8, 0 ); }   // ngang: 8 ô
		for ( $i = 0; $i < 7; $i++ ) { $dat( 8, $n - 1 - $i, 0 ); }   // dọc: 7 ô
		/* Đặt SAU cùng, để không nhánh nào ở trên giẫm lên. */
		$dat( 8, $n - 8, 1 );

		/* Thông tin version (từ version 7). */
		if ( $ver >= 7 ) {
			$bit = self::bit_version( $ver );
			for ( $i = 0; $i < 18; $i++ ) {
				$b = ( $bit >> $i ) & 1;
				$dat( intdiv( $i, 3 ), $n - 11 + ( $i % 3 ), $b );
				$dat( $n - 11 + ( $i % 3 ), intdiv( $i, 3 ), $b );
			}
		}
		return array( $o, $cam );
	}

	/** 18 bit thông tin version: 6 bit version + 12 bit BCH(18,6). */
	public static function bit_version( $ver ) {
		$d = $ver << 12;
		$r = $d;
		for ( $i = 17; $i >= 12; $i-- ) {
			if ( ( $r >> $i ) & 1 ) { $r ^= 0x1F25 << ( $i - 12 ); }
		}
		return $d | $r;
	}

	/** 15 bit thông tin định dạng: 2 bit mức + 3 bit mặt nạ + BCH, rồi XOR mặt nạ cố định. */
	public static function bit_dinh_dang( $muc, $mat_na ) {
		$bit_muc = array( 'L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2 );
		$d = ( $bit_muc[ $muc ] << 3 ) | $mat_na;
		$r = $d << 10;
		for ( $i = 14; $i >= 10; $i-- ) {
			if ( ( $r >> $i ) & 1 ) { $r ^= 0x537 << ( $i - 10 ); }
		}
		return ( ( $d << 10 ) | ( $r & 0x3FF ) ) ^ 0x5412;
	}

	private static function ham_mat_na( $k, $x, $y ) {
		switch ( $k ) {
			case 0: return 0 === ( $x + $y ) % 2;
			case 1: return 0 === $y % 2;
			case 2: return 0 === $x % 3;
			case 3: return 0 === ( $x + $y ) % 3;
			case 4: return 0 === ( intdiv( $y, 2 ) + intdiv( $x, 3 ) ) % 2;
			case 5: return 0 === ( $x * $y ) % 2 + ( $x * $y ) % 3;
			case 6: return 0 === ( ( ( $x * $y ) % 2 + ( $x * $y ) % 3 ) % 2 );
			default: return 0 === ( ( ( $x + $y ) % 2 + ( $x * $y ) % 3 ) % 2 );
		}
	}

	/** Đường đi zigzag của vùng dữ liệu: từ dưới-phải lên, hai cột một, bỏ cột nhịp số 6. */
	private static function duong_di( $n, $cam ) {
		$vt = array();
		$len = false;
		for ( $cot = $n - 1; $cot > 0; $cot -= 2 ) {
			if ( 6 === $cot ) { $cot--; }   // cột nhịp không mang dữ liệu
			$len = ! $len;
			for ( $i = 0; $i < $n; $i++ ) {
				$y = $len ? ( $n - 1 - $i ) : $i;
				foreach ( array( $cot, $cot - 1 ) as $x ) {
					if ( ! $cam[ $y ][ $x ] ) { $vt[] = array( $x, $y ); }
				}
			}
		}
		return $vt;
	}

	/** Chấm điểm một ma trận theo bốn luật phạt của chuẩn — thấp hơn là tốt hơn. */
	private static function cham_diem( $o, $n ) {
		$diem = 0;
		/* Luật 1: dãy 5 ô trở lên cùng màu, theo cả hàng lẫn cột. */
		for ( $lan = 0; $lan < 2; $lan++ ) {
			for ( $a = 0; $a < $n; $a++ ) {
				$dem = 1;
				for ( $b = 1; $b < $n; $b++ ) {
					$nay = $lan ? $o[ $b ][ $a ] : $o[ $a ][ $b ];
					$truoc = $lan ? $o[ $b - 1 ][ $a ] : $o[ $a ][ $b - 1 ];
					if ( $nay === $truoc ) { $dem++; }
					else { if ( $dem >= 5 ) { $diem += 3 + ( $dem - 5 ); } $dem = 1; }
				}
				if ( $dem >= 5 ) { $diem += 3 + ( $dem - 5 ); }
			}
		}
		/* Luật 2: mỗi khối 2×2 cùng màu. */
		for ( $y = 0; $y < $n - 1; $y++ ) {
			for ( $x = 0; $x < $n - 1; $x++ ) {
				$v = $o[ $y ][ $x ];
				if ( $v === $o[ $y ][ $x + 1 ] && $v === $o[ $y + 1 ][ $x ] && $v === $o[ $y + 1 ][ $x + 1 ] ) {
					$diem += 3;
				}
			}
		}
		/* Luật 3: hình 1:1:3:1:1 kèm khoảng trắng — dễ bị nhầm với ô định vị. */
		$mau = array( array( 1,0,1,1,1,0,1,0,0,0,0 ), array( 0,0,0,0,1,0,1,1,1,0,1 ) );
		for ( $lan = 0; $lan < 2; $lan++ ) {
			for ( $a = 0; $a < $n; $a++ ) {
				for ( $b = 0; $b + 10 < $n; $b++ ) {
					foreach ( $mau as $m ) {
						$khop = true;
						for ( $k = 0; $k < 11; $k++ ) {
							$v = $lan ? $o[ $b + $k ][ $a ] : $o[ $a ][ $b + $k ];
							if ( $v !== $m[ $k ] ) { $khop = false; break; }
						}
						if ( $khop ) { $diem += 40; }
					}
				}
			}
		}
		/* Luật 4: lệch tỉ lệ đen/trắng khỏi 50%. */
		$den = 0;
		foreach ( $o as $hang ) { $den += array_sum( $hang ); }
		$ti = intdiv( $den * 100, $n * $n );
		$diem += 10 * intdiv( abs( $ti - 50 ), 5 );
		return $diem;
	}

	/**
	 * Dựng ma trận QR cho một chuỗi. Trả về mảng hai chiều 0/1, hoặc rỗng nếu không dựng được.
	 *
	 * ⚠️ Không dựng được thì trả RỖNG, không trả một ma trận "gần đúng". Nơi gọi phải nói ra —
	 *    một tấm tem in ra mà không quét được thì tệ hơn hẳn việc chưa in tem nào.
	 */
	public static function ma_tran( $chuoi, $muc = 'M' ) {
		$s = (string) $chuoi;
		if ( '' === $s || ! isset( self::MUC[ $muc ] ) ) { return array(); }
		$ver = self::chon_version( $s, $muc );
		if ( 0 === $ver ) { return array(); }

		$cw = self::xen_ke( self::ma_hoa( $s, $ver, $muc ), $ver, $muc );
		list( $khung, $cam ) = self::khung( $ver );
		$n = 17 + 4 * $ver;

		/* Chuỗi bit dữ liệu + bit thừa (remainder) của version. */
		$bit = '';
		foreach ( $cw as $b ) { $bit .= str_pad( decbin( $b ), 8, '0', STR_PAD_LEFT ); }
		$duong = self::duong_di( $n, $cam );
		$bit = str_pad( $bit, count( $duong ), '0' );

		$tot = null; $diem_tot = PHP_INT_MAX; $mn_tot = 0;
		for ( $mn = 0; $mn < 8; $mn++ ) {
			$o = $khung;
			foreach ( $duong as $k => $xy ) {
				list( $x, $y ) = $xy;
				$v = ( '1' === $bit[ $k ] ) ? 1 : 0;
				if ( self::ham_mat_na( $mn, $x, $y ) ) { $v ^= 1; }
				$o[ $y ][ $x ] = $v;
			}
			$dd = self::bit_dinh_dang( $muc, $mn );
			for ( $i = 0; $i < 15; $i++ ) {
				$b = ( $dd >> $i ) & 1;
				/* Bản sao 1: quanh ô định vị trên-trái. */
				if ( $i < 6 )       { $o[8][ $i ] = $b; }
				elseif ( 6 === $i ) { $o[8][7] = $b; }
				elseif ( 7 === $i ) { $o[8][8] = $b; }
				elseif ( 8 === $i ) { $o[7][8] = $b; }
				else                { $o[ 14 - $i ][8] = $b; }
				/* Bản sao 2: BẢY bit đầu chạy dọc ở góc dưới-trái (hàng n-1 lên n-7), TÁM bit sau
				   chạy ngang ở góc trên-phải (cột n-8 tới n-1).
				   ⚠️ Bảy chứ không tám. Lấy tám là bit thứ 8 rơi vào (8, n-8) — đúng chỗ Ô TỐI
				      CỐ ĐỊNH, và ghi đè nó thành bit dữ liệu. Ô đó luôn phải đen; máy quét dùng
				      nó để chốt hướng đọc thông tin định dạng. */
				if ( $i < 7 ) { $o[ $n - 1 - $i ][8] = $b; }
				else          { $o[8][ $n - 15 + $i ] = $b; }
			}
			$d = self::cham_diem( $o, $n );
			if ( $d < $diem_tot ) { $diem_tot = $d; $tot = $o; $mn_tot = $mn; }
		}
		return $tot ? $tot : array();
	}

	// ===================================================================== đọc ngược (tự kiểm)

	/**
	 * ĐỌC NGƯỢC một ma trận QR về lại chuỗi. Rỗng nếu không đọc được.
	 *
	 * 🔴 Đây KHÔNG phải tính năng cho người dùng — đây là cách tệp này tự chứng minh mình đúng.
	 *    Tự viết bộ vẽ QR thì "chắc là quét được" chỉ là một lời chúc; phép thử bắt mọi chuỗi
	 *    dựng ra phải đọc ngược đúng chuỗi ban đầu.
	 *
	 *    Nó đi ngược đúng những bước dễ sai nhất: đọc thông tin định dạng để biết mặt nạ, gỡ mặt
	 *    nạ, đi lại đường zigzag, THÁO XEN KẼ khối, rồi đọc chế độ và độ dài. Một lỗi ở bất kỳ
	 *    bước nào trong bộ vẽ là chuỗi đọc ra khác chuỗi ban đầu.
	 *
	 * ⚠️ Không sửa lỗi Reed-Solomon: nó đọc một ma trận SẠCH. Phần Reed-Solomon được kiểm riêng
	 *    bằng cách đối chiếu với bộ hệ số và ví dụ đã công bố trong bản đặc tả.
	 */
	public static function doc( $o ) {
		if ( ! is_array( $o ) || ! count( $o ) ) { return ''; }
		$n = count( $o );
		if ( $n < 21 || 0 !== ( $n - 17 ) % 4 ) { return ''; }
		$ver = ( $n - 17 ) / 4;
		if ( $ver < 1 || $ver > 10 ) { return ''; }

		/* Thông tin định dạng: đọc bản sao 1, XOR mặt nạ cố định. */
		$dd = 0;
		for ( $i = 0; $i < 15; $i++ ) {
			if ( $i < 6 )       { $b = $o[8][ $i ]; }
			elseif ( 6 === $i ) { $b = $o[8][7]; }
			elseif ( 7 === $i ) { $b = $o[8][8]; }
			elseif ( 8 === $i ) { $b = $o[7][8]; }
			else                { $b = $o[ 14 - $i ][8]; }
			$dd |= ( $b & 1 ) << $i;
		}
		$dd ^= 0x5412;
		$bit_muc = ( $dd >> 13 ) & 3;
		$mat_na  = ( $dd >> 10 ) & 7;
		$ten_muc = array( 1 => 'L', 0 => 'M', 3 => 'Q', 2 => 'H' );
		if ( ! isset( $ten_muc[ $bit_muc ] ) ) { return ''; }
		$muc = $ten_muc[ $bit_muc ];

		list( , $cam ) = self::khung( $ver );
		$duong = self::duong_di( $n, $cam );
		$bit = '';
		foreach ( $duong as $xy ) {
			list( $x, $y ) = $xy;
			$v = $o[ $y ][ $x ] & 1;
			if ( self::ham_mat_na( $mat_na, $x, $y ) ) { $v ^= 1; }
			$bit .= $v ? '1' : '0';
		}

		$cw = array();
		for ( $i = 0; $i + 8 <= strlen( $bit ); $i += 8 ) { $cw[] = bindec( substr( $bit, $i, 8 ) ); }

		/* Tháo xen kẽ: dựng lại các khối dữ liệu theo đúng luật đã xen. */
		$b = self::bang_khoi();
		list( $n_ecc, $k1, $k2 ) = $b[ $ver ][ $muc ];
		$tong_khoi = $k1 + $k2;
		$so_du = self::so_cw_du_lieu( $ver, $muc );
		$cw_g1 = intdiv( $so_du, $tong_khoi );
		$dai = array();
		for ( $i = 0; $i < $tong_khoi; $i++ ) { $dai[ $i ] = $cw_g1 + ( $i >= $k1 ? 1 : 0 ); }
		$khoi = array_fill( 0, $tong_khoi, array() );
		$vt = 0;
		for ( $i = 0; $i < max( $dai ); $i++ ) {
			for ( $k = 0; $k < $tong_khoi; $k++ ) {
				if ( $i < $dai[ $k ] ) {
					if ( ! isset( $cw[ $vt ] ) ) { return ''; }
					$khoi[ $k ][] = $cw[ $vt ];
					$vt++;
				}
			}
		}
		$du = array();
		foreach ( $khoi as $kh ) { $du = array_merge( $du, $kh ); }

		$bs = '';
		foreach ( $du as $x ) { $bs .= str_pad( decbin( $x ), 8, '0', STR_PAD_LEFT ); }

		$che_do = bindec( substr( $bs, 0, 4 ) );
		$p = 4;
		if ( 2 === $che_do ) {
			$bd = $ver <= 9 ? 9 : 11;
			$len = bindec( substr( $bs, $p, $bd ) ); $p += $bd;
			$ra = '';
			for ( $i = 0; $i + 1 < $len; $i += 2 ) {
				$v = bindec( substr( $bs, $p, 11 ) ); $p += 11;
				$ra .= self::AN[ intdiv( $v, 45 ) ] . self::AN[ $v % 45 ];
			}
			if ( $len % 2 ) { $ra .= self::AN[ bindec( substr( $bs, $p, 6 ) ) ]; }
			return $ra;
		}
		if ( 4 === $che_do ) {
			$bd = $ver <= 9 ? 8 : 16;
			$len = bindec( substr( $bs, $p, $bd ) ); $p += $bd;
			$ra = '';
			for ( $i = 0; $i < $len; $i++ ) { $ra .= chr( bindec( substr( $bs, $p, 8 ) ) ); $p += 8; }
			return $ra;
		}
		return '';
	}

	// ===================================================================== xuất SVG

	/**
	 * Ma trận -> SVG. Vẽ bằng các ô vuông gộp theo hàng: một tấm tem version 3 là 29×29 ô, vẽ
	 * từng ô riêng là gần 900 thẻ — nặng và in chậm.
	 *
	 * ⚠️ VÙNG LẶNG 4 Ô mỗi bên, đúng chuẩn. Cắt bớt cho "gọn" là nhiều máy quét không nhận ra mã,
	 *    và đó là kiểu hỏng chỉ lộ ra ở một số máy — tức là sau khi đã dán tem lên 26 cái ghế.
	 */
	public static function svg( $o, $canh_px = 240, $lang = 4 ) {
		if ( ! is_array( $o ) || ! count( $o ) ) { return ''; }
		$n = count( $o );
		$tong = $n + 2 * $lang;
		$duong = '';
		for ( $y = 0; $y < $n; $y++ ) {
			$x = 0;
			while ( $x < $n ) {
				if ( ! $o[ $y ][ $x ] ) { $x++; continue; }
				$d = $x;
				while ( $d < $n && $o[ $y ][ $d ] ) { $d++; }
				$duong .= 'M' . ( $x + $lang ) . ' ' . ( $y + $lang )
					. 'h' . ( $d - $x ) . 'v1h-' . ( $d - $x ) . 'z';
				$x = $d;
			}
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $canh_px . '" height="'
			. (int) $canh_px . '" viewBox="0 0 ' . $tong . ' ' . $tong . '" shape-rendering="crispEdges">'
			. '<rect width="' . $tong . '" height="' . $tong . '" fill="#fff"/>'
			. '<path d="' . $duong . '" fill="#000"/></svg>';
	}
}
