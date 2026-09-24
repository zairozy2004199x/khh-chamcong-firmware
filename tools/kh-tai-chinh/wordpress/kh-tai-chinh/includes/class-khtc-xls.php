<?php
/**
 * Đọc .xls đời cũ (BIFF8, Excel 97–2003) — không thư viện ngoài.
 *
 * VÌ SAO PHẢI TỰ VIẾT: file đơn hàng Zalo mini app (Haravan/Sapo) xuất .xls
 * nhị phân, host chỉ có wp-admin nên không cài PhpSpreadsheet. Bảo kế toán
 * mở Excel "Lưu thành .xlsx" mỗi ngày là thêm một bước tay, mà bước tay là
 * chỗ quên.
 *
 * Đọc đủ để lấy BẢNG: chuỗi (SST, LABEL), số (NUMBER, RK, MULRK), công thức
 * có kết quả (FORMULA + STRING), ngày qua định dạng ô (XF → FORMAT). Không
 * đọc định dạng, gộp ô, biểu đồ. Hỏng chỗ nào thì trả WP_Error nói rõ, không
 * đoán.
 *
 * Cấu trúc: tệp là "Compound File" (OLE2) chứa luồng "Workbook"; luồng đó là
 * chuỗi bản ghi [id 2 byte][dài 2 byte][dữ liệu]. Bản ghi dài quá 8224 byte
 * nối tiếp bằng CONTINUE.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Xls {

	/**
	 * Đọc mọi sheet.
	 *
	 * @return array|WP_Error [ ['ten'=>string, 'dong'=>array<array<string>>] ]
	 */
	public static function doc( $duong ) {
		$du = file_get_contents( $duong );
		if ( false === $du || strlen( $du ) < 8 ) {
			return new WP_Error( 'xls', 'Tệp .xls rỗng hoặc đọc không được.' );
		}
		if ( substr( $du, 0, 8 ) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" ) {
			// Nhiều "xls" thực ra là HTML/CSV đổi đuôi — báo thẳng để đi đường khác.
			return new WP_Error( 'xls', 'Tệp không phải Excel 97–2003 thật (có thể là HTML hay CSV đổi đuôi .xls). Mở bằng Excel, Lưu thành .xlsx hoặc CSV rồi nạp.' );
		}
		if ( strlen( $du ) < 512 ) {
			return new WP_Error( 'xls', 'Tệp .xls hỏng (ngắn hơn một sector).' );
		}
		$wb = self::luong_workbook( $du );
		if ( is_wp_error( $wb ) ) { return $wb; }
		return self::doc_workbook( $wb );
	}

	// ------------------------------------------------------------ OLE2

	/** Lấy luồng "Workbook" (hoặc "Book" của BIFF5) ra khỏi Compound File. */
	private static function luong_workbook( $du ) {
		$u16 = function ( $s, $o ) { return unpack( 'v', substr( $s, $o, 2 ) )[1]; };
		$u32 = function ( $s, $o ) { $v = unpack( 'V', substr( $s, $o, 4 ) )[1]; return $v; };

		$sec_shift  = $u16( $du, 0x1E );          // thường 9 → 512
		$mini_shift = $u16( $du, 0x20 );          // thường 6 → 64
		$sec        = 1 << $sec_shift;
		$mini       = 1 << $mini_shift;
		$so_fat     = $u32( $du, 0x2C );
		$dir_start  = $u32( $du, 0x30 );
		$mini_cut   = $u32( $du, 0x38 );          // luồng nhỏ hơn cỡ này nằm trong ministream
		$minifat_st = $u32( $du, 0x3C );
		$minifat_n  = $u32( $du, 0x40 );
		$difat_st   = $u32( $du, 0x44 );
		$difat_n    = $u32( $du, 0x48 );

		// Danh sách sector FAT: 109 cái đầu trong header, còn lại qua DIFAT.
		$fat_secs = array();
		for ( $i = 0; $i < 109 && $i < $so_fat; $i++ ) {
			$v = $u32( $du, 0x4C + $i * 4 );
			if ( $v < 0xFFFFFFFA ) { $fat_secs[] = $v; }
		}
		$ds = $difat_st;
		for ( $k = 0; $k < $difat_n && $ds < 0xFFFFFFFA; $k++ ) {
			$o = ( $ds + 1 ) * $sec;
			for ( $i = 0; $i < $sec / 4 - 1; $i++ ) {
				$v = $u32( $du, $o + $i * 4 );
				if ( $v < 0xFFFFFFFA ) { $fat_secs[] = $v; }
			}
			$ds = $u32( $du, $o + $sec - 4 );
		}
		// FAT: mảng "sector kế tiếp"
		$fat = array();
		foreach ( $fat_secs as $fs ) {
			$o = ( $fs + 1 ) * $sec;
			if ( $o + $sec > strlen( $du ) ) { break; }
			$fat = array_merge( $fat, array_values( unpack( 'V*', substr( $du, $o, $sec ) ) ) );
		}
		$chuoi = function ( $bat_dau ) use ( $fat, $sec, $du ) {
			$ra = ''; $s = $bat_dau; $dem = 0;
			while ( $s < 0xFFFFFFFA && $dem < 1000000 ) {
				$ra .= substr( $du, ( $s + 1 ) * $sec, $sec );
				if ( ! isset( $fat[ $s ] ) ) { break; }
				$s = $fat[ $s ]; $dem++;
			}
			return $ra;
		};

		// Thư mục
		$dir = $chuoi( $dir_start );
		$root = null; $wb = null;
		for ( $o = 0; $o + 128 <= strlen( $dir ); $o += 128 ) {
			$ten_len = $u16( $dir, $o + 0x40 );
			if ( $ten_len < 2 ) { continue; }
			$ten  = mb_convert_encoding( substr( $dir, $o, $ten_len - 2 ), 'UTF-8', 'UTF-16LE' );
			$loai = ord( $dir[ $o + 0x42 ] );
			$st   = $u32( $dir, $o + 0x74 );
			$len  = $u32( $dir, $o + 0x78 );
			if ( 5 === $loai ) { $root = array( 'st' => $st, 'len' => $len ); }
			if ( 2 === $loai && ( 'Workbook' === $ten || 'Book' === $ten ) ) { $wb = array( 'st' => $st, 'len' => $len ); }
		}
		if ( ! $wb ) {
			return new WP_Error( 'xls', 'Tệp .xls không có luồng Workbook — không phải bảng tính Excel.' );
		}
		if ( $wb['len'] < $mini_cut ) {
			// Nằm trong ministream (hiếm với bảng dữ liệu, nhưng có).
			if ( ! $root ) { return new WP_Error( 'xls', 'Tệp .xls hỏng (thiếu gốc ministream).' ); }
			$ministream = $chuoi( $root['st'] );
			$minifat    = array();
			$s = $minifat_st;
			for ( $k = 0; $k < $minifat_n && $s < 0xFFFFFFFA; $k++ ) {
				$minifat = array_merge( $minifat, array_values( unpack( 'V*', substr( $du, ( $s + 1 ) * $sec, $sec ) ) ) );
				$s = $fat[ $s ] ?? 0xFFFFFFFE;
			}
			$ra = ''; $s = $wb['st']; $dem = 0;
			while ( $s < 0xFFFFFFFA && $dem < 1000000 ) {
				$ra .= substr( $ministream, $s * $mini, $mini );
				if ( ! isset( $minifat[ $s ] ) ) { break; }
				$s = $minifat[ $s ]; $dem++;
			}
			return substr( $ra, 0, $wb['len'] );
		}
		return substr( $chuoi( $wb['st'] ), 0, $wb['len'] );
	}

	// ------------------------------------------------------------ BIFF

	/** Đọc luồng Workbook thành các sheet. */
	private static function doc_workbook( $wb ) {
		$len = strlen( $wb );
		$pos = 0;
		$sst = array();           // chuỗi dùng chung
		$xf_fmt = array();        // chỉ số XF → numFmtId
		$fmt_tu_dat = array();    // numFmtId → chuỗi định dạng
		$sheets = array();        // [ten, offset]
		$date1904 = false;
		$codepage = 1252;

		// --- Lượt 1: bản ghi toàn cục (đến EOF đầu tiên)
		$rec = self::ban_ghi( $wb, $pos );
		if ( ! $rec || 0x0809 !== $rec['id'] ) {
			return new WP_Error( 'xls', 'Luồng Workbook không bắt đầu bằng BOF — tệp hỏng hoặc không phải BIFF8.' );
		}
		$phien_ban = self::u16( $rec['d'], 0 );
		if ( 0x0600 !== $phien_ban ) {
			return new WP_Error( 'xls', 'Đây là Excel 5/95 (BIFF' . ( 0x0500 === $phien_ban ? '5' : '?' ) . '), quá cũ. Mở bằng Excel, Lưu thành .xlsx rồi nạp.' );
		}
		$pos = $rec['next'];
		while ( $pos < $len ) {
			$rec = self::ban_ghi( $wb, $pos );
			if ( ! $rec ) { break; }
			$pos = $rec['next'];
			switch ( $rec['id'] ) {
				case 0x0042: // CODEPAGE
					$codepage = self::u16( $rec['d'], 0 );
					break;
				case 0x0022: // DATEMODE
					$date1904 = 1 === self::u16( $rec['d'], 0 );
					break;
				case 0x0085: // BOUNDSHEET
					$off  = self::u32( $rec['d'], 0 );
					$loai = ord( $rec['d'][5] );
					$n    = ord( $rec['d'][6] );
					$flag = ord( $rec['d'][7] );
					$ten  = ( $flag & 1 ) ? mb_convert_encoding( substr( $rec['d'], 8, $n * 2 ), 'UTF-8', 'UTF-16LE' ) : self::cp( substr( $rec['d'], 8, $n ), $codepage );
					if ( 0 === $loai ) { $sheets[] = array( 'ten' => $ten, 'off' => $off ); }
					break;
				case 0x041E: // FORMAT
					$id = self::u16( $rec['d'], 0 );
					$fmt_tu_dat[ $id ] = self::chuoi_unicode( $rec['d'], 2, $codepage )['s'];
					break;
				case 0x00E0: // XF
					$xf_fmt[] = self::u16( $rec['d'], 2 );
					break;
				case 0x00FC: // SST (+ CONTINUE)
					$sst = self::doc_sst( $wb, $rec, $codepage );
					break;
				case 0x000A: // EOF của phần toàn cục
					$pos = $len; // dừng lượt 1
					break;
			}
		}
		if ( ! $sheets ) {
			return new WP_Error( 'xls', 'Tệp .xls không có sheet nào.' );
		}
		$ngay_kieu = array();
		foreach ( $xf_fmt as $i => $id ) { $ngay_kieu[ $i ] = self::la_ngay( $id, $fmt_tu_dat ); }

		// --- Lượt 2: từng sheet
		$ra = array();
		foreach ( $sheets as $sh ) {
			$ra[] = array( 'ten' => $sh['ten'], 'dong' => self::doc_sheet( $wb, $sh['off'], $sst, $ngay_kieu, $date1904, $codepage ) );
		}
		return $ra;
	}

	private static function doc_sheet( $wb, $pos, $sst, $ngay_kieu, $date1904, $codepage ) {
		$len  = strlen( $wb );
		$o    = array();   // [row][col] = string
		$max_row = -1;
		$rec  = self::ban_ghi( $wb, $pos );
		if ( ! $rec || 0x0809 !== $rec['id'] ) { return array(); }
		$pos  = $rec['next'];
		$dat  = function ( $r, $c, $v ) use ( &$o, &$max_row ) { $o[ $r ][ $c ] = $v; if ( $r > $max_row ) { $max_row = $r; } };
		$so   = function ( $v, $xf ) use ( $ngay_kieu, $date1904 ) {
			if ( ! empty( $ngay_kieu[ $xf ] ) ) { return KHTC_Tep::ngay_excel( (float) $v, $date1904 ); }
			return KHTC_Tep::so_chu( self::so_ra_chuoi( $v ) );
		};
		$cho_string = null;   // FORMULA có kết quả chuỗi → bản ghi STRING kế tiếp
		while ( $pos < $len ) {
			$rec = self::ban_ghi( $wb, $pos );
			if ( ! $rec ) { break; }
			$pos = $rec['next'];
			$d   = $rec['d'];
			switch ( $rec['id'] ) {
				case 0x000A: // EOF sheet
					$pos = $len;
					break;
				case 0x00FD: // LABELSST
					$dat( self::u16( $d, 0 ), self::u16( $d, 2 ), $sst[ self::u32( $d, 6 ) ] ?? '' );
					break;
				case 0x0204: // LABEL (BIFF8 vẫn gặp)
					$dat( self::u16( $d, 0 ), self::u16( $d, 2 ), self::chuoi_unicode( $d, 6, $codepage )['s'] );
					break;
				case 0x0203: // NUMBER
					$dat( self::u16( $d, 0 ), self::u16( $d, 2 ), $so( unpack( 'e', substr( $d, 6, 8 ) )[1], self::u16( $d, 4 ) ) );
					break;
				case 0x027E: // RK
					$dat( self::u16( $d, 0 ), self::u16( $d, 2 ), $so( self::rk( self::u32( $d, 6 ) ), self::u16( $d, 4 ) ) );
					break;
				case 0x00BD: // MULRK
					$r = self::u16( $d, 0 ); $c1 = self::u16( $d, 2 ); $n = ( strlen( $d ) - 6 ) / 6;
					for ( $i = 0; $i < $n; $i++ ) {
						$dat( $r, $c1 + $i, $so( self::rk( self::u32( $d, 4 + $i * 6 + 2 ) ), self::u16( $d, 4 + $i * 6 ) ) );
					}
					break;
				case 0x0006: // FORMULA
					$r = self::u16( $d, 0 ); $c = self::u16( $d, 2 ); $xf = self::u16( $d, 4 );
					$kq = substr( $d, 6, 8 );
					if ( "\xFF\xFF" === substr( $kq, 6, 2 ) ) {
						$loai = ord( $kq[0] );
						if ( 0 === $loai ) { $cho_string = array( $r, $c ); }           // chuỗi → STRING kế tiếp
						elseif ( 1 === $loai ) { $dat( $r, $c, ord( $kq[2] ) ? 'TRUE' : 'FALSE' ); }
						elseif ( 2 === $loai ) { $dat( $r, $c, '' ); }                  // lỗi công thức → trống
						else { $dat( $r, $c, '' ); }
					} else {
						$dat( $r, $c, $so( unpack( 'e', $kq )[1], $xf ) );
					}
					break;
				case 0x0207: // STRING (kết quả công thức)
					if ( $cho_string ) { $dat( $cho_string[0], $cho_string[1], self::chuoi_unicode( $d, 0, $codepage )['s'] ); $cho_string = null; }
					break;
				case 0x0205: // BOOLERR
					$r = self::u16( $d, 0 ); $c = self::u16( $d, 2 );
					$dat( $r, $c, ord( $d[7] ) ? '' : ( ord( $d[6] ) ? 'TRUE' : 'FALSE' ) );
					break;
				case 0x0809: // BOF lồng (biểu đồ nhúng) → bỏ qua tới EOF của nó
					$sau = 1;
					while ( $pos < $len && $sau > 0 ) {
						$r2 = self::ban_ghi( $wb, $pos ); if ( ! $r2 ) { break; }
						$pos = $r2['next'];
						if ( 0x0809 === $r2['id'] ) { $sau++; } elseif ( 0x000A === $r2['id'] ) { $sau--; }
					}
					break;
			}
		}
		// Về mảng dòng liên tục, ô trống ''
		$dong = array();
		for ( $r = 0; $r <= $max_row; $r++ ) {
			if ( empty( $o[ $r ] ) ) { $dong[] = array(); continue; }
			$max_c = max( array_keys( $o[ $r ] ) );
			$h = array();
			for ( $c = 0; $c <= $max_c; $c++ ) { $h[] = $o[ $r ][ $c ] ?? ''; }
			while ( $h && '' === end( $h ) ) { array_pop( $h ); }
			$dong[] = $h;
		}
		while ( $dong && ! array_filter( end( $dong ), 'strlen' ) ) { array_pop( $dong ); }
		return $dong;
	}

	// ------------------------------------------------------------ phụ

	private static function ban_ghi( $wb, $pos ) {
		if ( $pos + 4 > strlen( $wb ) ) { return null; }
		$id = self::u16( $wb, $pos ); $n = self::u16( $wb, $pos + 2 );
		return array( 'id' => $id, 'd' => substr( $wb, $pos + 4, $n ), 'next' => $pos + 4 + $n, 'pos' => $pos );
	}
	private static function u16( $s, $o ) { return isset( $s[ $o + 1 ] ) ? unpack( 'v', substr( $s, $o, 2 ) )[1] : 0; }
	private static function u32( $s, $o ) { return isset( $s[ $o + 3 ] ) ? unpack( 'V', substr( $s, $o, 4 ) )[1] : 0; }

	/** Số RK: 30 bit, cờ ×100 và cờ số nguyên. */
	private static function rk( $v ) {
		$chia100 = $v & 1; $nguyen = $v & 2;
		if ( $nguyen ) {
			$n = $v >> 2;
			if ( $n & 0x20000000 ) { $n -= 0x40000000; }   // âm
		} else {
			$n = unpack( 'e', pack( 'V', 0 ) . pack( 'V', $v & 0xFFFFFFFC ) )[1];
		}
		return $chia100 ? $n / 100 : $n;
	}

	private static function so_ra_chuoi( $v ) {
		if ( is_int( $v ) ) { return (string) $v; }
		if ( floor( $v ) === $v && abs( $v ) < 1e15 ) { return sprintf( '%.0f', $v ); }
		return rtrim( rtrim( sprintf( '%.10F', $v ), '0' ), '.' );
	}

	private static function cp( $s, $codepage ) {
		$bang = array( 1252 => 'Windows-1252', 1258 => 'Windows-1258', 1250 => 'Windows-1250', 1251 => 'Windows-1251', 932 => 'SJIS', 936 => 'GB2312', 950 => 'BIG-5', 65001 => 'UTF-8' );
		$tu = $bang[ $codepage ] ?? 'Windows-1252';
		$r  = @mb_convert_encoding( $s, 'UTF-8', $tu );
		return false === $r ? $s : $r;
	}

	/**
	 * Chuỗi Unicode BIFF8 tại $o: [len 2][flags 1][rich 2?][ext 4?][chữ][rich runs][ext].
	 * Trả ['s'=>chuỗi, 'n'=>số byte đã dùng].
	 */
	private static function chuoi_unicode( $d, $o, $codepage, $len_byte = 2 ) {
		$n = ( 2 === $len_byte ) ? self::u16( $d, $o ) : ord( $d[ $o ] );
		$p = $o + $len_byte;
		$flags = ord( $d[ $p ] ); $p++;
		$rich = ( $flags & 8 ) ? self::u16( $d, $p ) : 0; if ( $flags & 8 ) { $p += 2; }
		$ext  = ( $flags & 4 ) ? self::u32( $d, $p ) : 0; if ( $flags & 4 ) { $p += 4; }
		if ( $flags & 1 ) { $s = mb_convert_encoding( substr( $d, $p, $n * 2 ), 'UTF-8', 'UTF-16LE' ); $p += $n * 2; }
		else { $s = self::cp( substr( $d, $p, $n ), $codepage ); $p += $n; }
		$p += $rich * 4 + $ext;
		return array( 's' => $s, 'n' => $p - $o );
	}

	/**
	 * SST trải qua nhiều CONTINUE; một chuỗi có thể bị cắt giữa chừng, và ở
	 * đầu CONTINUE có thêm một byte cờ nén cho phần còn lại. Đây là chỗ mọi
	 * bộ đọc tự viết hay sai, nên đi từng byte thay vì nối hết rồi đọc.
	 */
	private static function doc_sst( $wb, $rec, $codepage ) {
		// Gom các khúc: khúc 0 là SST (bỏ 8 byte đếm), các khúc sau là CONTINUE
		$khuc = array( substr( $rec['d'], 8 ) );
		$tong = self::u32( $rec['d'], 4 );
		$pos  = $rec['next'];
		while ( true ) {
			$r2 = self::ban_ghi( $wb, $pos );
			if ( ! $r2 || 0x003C !== $r2['id'] ) { break; }
			$khuc[] = $r2['d']; $pos = $r2['next'];
		}
		$ra = array();
		$k = 0; $p = 0;
		$byte_con = function () use ( &$khuc, &$k, &$p ) { return strlen( $khuc[ $k ] ) - $p; };
		$sang_khuc = function () use ( &$khuc, &$k, &$p ) { $k++; $p = 0; return $k < count( $khuc ); };
		for ( $i = 0; $i < $tong; $i++ ) {
			// Đầu chuỗi phải có đủ 3 byte (len + flags); hết khúc thì sang khúc kế
			if ( $byte_con() < 3 ) { if ( ! $sang_khuc() ) { break; } }
			$n = self::u16( $khuc[ $k ], $p ); $p += 2;
			$flags = ord( $khuc[ $k ][ $p ] ); $p++;
			$rich = 0; $ext = 0;
			if ( $flags & 8 ) { $rich = self::u16( $khuc[ $k ], $p ); $p += 2; }
			if ( $flags & 4 ) { $ext = self::u32( $khuc[ $k ], $p ); $p += 4; }
			$nen = ! ( $flags & 1 );   // nén = 1 byte/ký tự
			$s = ''; $con = $n;
			while ( $con > 0 ) {
				$moi_ky_tu = $nen ? 1 : 2;
				$lay = min( $con, intdiv( $byte_con(), $moi_ky_tu ) );
				if ( $lay > 0 ) {
					$mieng = substr( $khuc[ $k ], $p, $lay * $moi_ky_tu );
					$s .= $nen ? self::cp( $mieng, $codepage ) : mb_convert_encoding( $mieng, 'UTF-8', 'UTF-16LE' );
					$p += $lay * $moi_ky_tu; $con -= $lay;
				}
				if ( $con > 0 ) {
					if ( ! $sang_khuc() ) { break 2; }
					$nen = ! ( ord( $khuc[ $k ][ $p ] ) & 1 ); $p++;   // byte cờ mới ở đầu CONTINUE
				}
			}
			// Bỏ rich runs và ext — có thể cũng trải qua CONTINUE
			$bo = $rich * 4 + $ext;
			while ( $bo > 0 ) {
				$lay = min( $bo, $byte_con() );
				$p += $lay; $bo -= $lay;
				if ( $bo > 0 && ! $sang_khuc() ) { break 2; }
			}
			$ra[] = $s;
		}
		return $ra;
	}

	/** numFmtId có phải ngày giờ: dựng sẵn 14–22, 45–47; tự đặt thì soi chuỗi. */
	private static function la_ngay( $id, $tu_dat ) {
		if ( ( $id >= 14 && $id <= 22 ) || ( $id >= 45 && $id <= 47 ) ) { return true; }
		if ( isset( $tu_dat[ $id ] ) ) {
			$ma = preg_replace( '/"[^"]*"|\[[^\]]*\]|\\\\./', '', $tu_dat[ $id ] );
			return (bool) preg_match( '/[dmyhs]/i', $ma ) && ! preg_match( '/[#0]/', $ma );
		}
		return false;
	}
}
