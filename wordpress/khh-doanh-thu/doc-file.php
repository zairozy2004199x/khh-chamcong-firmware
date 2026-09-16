<?php
/**
 * Đọc file "Báo cáo bán hàng" xuất từ FABi (iPOS) và gộp thành số theo ngày × cửa hàng.
 *
 * File .xlsx thật ra là file nén chứa XML. Ở đây đọc bằng XMLReader theo dòng
 * (không nạp cả file vào bộ nhớ) vì bản xuất một tuần của 15 cửa hàng đã hơn 60MB.
 *
 * HAI CÁI BẪY CỦA FILE FABi — xử lý sẵn ở đây:
 *   1. File có một trang cho mỗi cửa hàng VÀ một trang "Tất cả cửa hàng" gộp.
 *      Đọc trang đầu là thiếu 14 cửa hàng, cộng hết các trang là gấp đôi.
 *      → luôn ưu tiên trang "Tất cả cửa hàng".
 *   2. Trong trang có chèn dòng "Tổng" cộng dồn, cột Thời gian để "-".
 *      Cộng thẳng cột Thành tiền là doanh thu GẤP ĐÔI.
 *      → bỏ mọi dòng không đọc được ngày, và đếm lại để báo cho người dùng.
 *
 * Doanh thu lấy cột "Tổng tiền" (thực thu); không có thì Thành tiền − (Chiết khấu + Giảm giá).
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Máy chủ có đọc được .xlsx không. */
function khh_dt_doc_duoc_xlsx() {
	return class_exists( 'ZipArchive' ) && class_exists( 'XMLReader' );
}

/** Bỏ dấu tiếng Việt để so tên cột. */
/**
 * Bỏ dấu, hạ chữ thường — dùng để so tên cột và nhận mặt cơ sở trong nội dung chuyển khoản.
 *
 * ⚠️ `strtolower()` KHÔNG hạ được chữ có dấu: nó đi từng byte, mà "Ầ" là hai byte. Nên phải bỏ
 *    dấu TRƯỚC rồi mới hạ, và có `mb_strtolower()` thì dùng.
 *
 * ⚠️ CÓ ĐƯỜNG LUI KHI KHÔNG CÓ `remove_accents()`. Hàm ấy của WordPress, và trên site thật thì
 *    luôn có — nhưng hàm này còn chạy ở bộ thử và ở những đường nạp sớm. Thiếu nó mà cứ thế trả
 *    chuỗi còn nguyên dấu thì "Tutu Tân Phú" không khớp khoá "TUTU TAN PHU", và cả một cơ sở
 *    lặng lẽ không nhận được đồng tiền nộp nào — kiểu hỏng không có dòng đỏ nào báo.
 */
function khh_dt_khong_dau( $s ) {
	$s = (string) $s;
	/* MoMo xuất tên cột kiểu tách dấu (o + dấu sắc rời), bỏ dấu rời trước. */
	$tach = preg_replace( '/[\x{0300}-\x{036F}]/u', '', $s );
	if ( null !== $tach ) {
		$s = $tach;
	}
	if ( function_exists( 'remove_accents' ) ) {
		$s = remove_accents( $s );
	} else {
		$s = strtr(
			$s,
			array(
				'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a', 'â' => 'a', 'ầ' => 'a',
				'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a',
				'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e',
				'ẽ' => 'e', 'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
				'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ò' => 'o', 'ó' => 'o',
				'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o',
				'ổ' => 'o', 'ỗ' => 'o', 'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o',
				'ỡ' => 'o', 'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ư' => 'u',
				'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ỳ' => 'y', 'ý' => 'y',
				'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'đ' => 'd',
				'À' => 'A', 'Á' => 'A', 'Ạ' => 'A', 'Ả' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ầ' => 'A',
				'Ấ' => 'A', 'Ậ' => 'A', 'Ẩ' => 'A', 'Ẫ' => 'A', 'Ă' => 'A', 'Ằ' => 'A', 'Ắ' => 'A',
				'Ặ' => 'A', 'Ẳ' => 'A', 'Ẵ' => 'A', 'È' => 'E', 'É' => 'E', 'Ẹ' => 'E', 'Ẻ' => 'E',
				'Ẽ' => 'E', 'Ê' => 'E', 'Ề' => 'E', 'Ế' => 'E', 'Ệ' => 'E', 'Ể' => 'E', 'Ễ' => 'E',
				'Ì' => 'I', 'Í' => 'I', 'Ị' => 'I', 'Ỉ' => 'I', 'Ĩ' => 'I', 'Ò' => 'O', 'Ó' => 'O',
				'Ọ' => 'O', 'Ỏ' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ồ' => 'O', 'Ố' => 'O', 'Ộ' => 'O',
				'Ổ' => 'O', 'Ỗ' => 'O', 'Ơ' => 'O', 'Ờ' => 'O', 'Ớ' => 'O', 'Ợ' => 'O', 'Ở' => 'O',
				'Ỡ' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Ụ' => 'U', 'Ủ' => 'U', 'Ũ' => 'U', 'Ư' => 'U',
				'Ừ' => 'U', 'Ứ' => 'U', 'Ự' => 'U', 'Ử' => 'U', 'Ữ' => 'U', 'Ỳ' => 'Y', 'Ý' => 'Y',
				'Ỵ' => 'Y', 'Ỷ' => 'Y', 'Ỹ' => 'Y', 'Đ' => 'D',
			)
		);
	}
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $s ), 'UTF-8' ) : strtolower( trim( $s ) );
}

/** "BC12" → 54 (số thứ tự cột, tính từ 0). */
function khh_dt_cot_so( $ref ) {
	$n   = 0;
	$len = strlen( $ref );
	for ( $i = 0; $i < $len; $i++ ) {
		$c = strtoupper( $ref[ $i ] );
		if ( $c < 'A' || $c > 'Z' ) {
			break;
		}
		$n = $n * 26 + ( ord( $c ) - 64 );
	}
	return $n > 0 ? $n - 1 : 0;
}

/** Số kiểu Việt Nam: "1.234.567", "1,234,567.00", "-", "" → float. */
function khh_dt_so( $v ) {
	if ( is_int( $v ) || is_float( $v ) ) {
		return (float) $v;
	}
	$s = trim( (string) $v );
	if ( '' === $s || '-' === $s ) {
		return 0.0;
	}
	$am = ( '-' === substr( $s, 0, 1 ) ) || ( '(' === substr( $s, 0, 1 ) && ')' === substr( $s, -1 ) );
	$s  = preg_replace( '/[^\d.,]/', '', $s );
	if ( '' === $s ) {
		return 0.0;
	}
	$cuoi = max( strrpos( $s, '.' ), strrpos( $s, ',' ) );
	if ( false !== $cuoi ) {
		$duoi = substr( $s, $cuoi + 1 );
		if ( strlen( $duoi ) > 0 && strlen( $duoi ) <= 2 ) {
			$s = preg_replace( '/[.,]/', '', substr( $s, 0, $cuoi ) ) . '.' . $duoi;
		} else {
			$s = preg_replace( '/[.,]/', '', $s );
		}
	}
	$n = (float) $s;
	return $am ? -$n : $n;
}

/** Trả 'Y-m-d', hoặc '' nếu ô không phải ngày (chính là dòng "Tổng" của FABi). */
function khh_dt_ngay( $v ) {
	if ( is_numeric( $v ) && $v > 20000 && $v < 80000 ) {          // số thứ tự ngày của Excel
		return gmdate( 'Y-m-d', (int) round( ( $v - 25569 ) * 86400 ) );
	}
	$s = trim( (string) $v );
	if ( '' === $s || '-' === $s ) {
		return '';
	}
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $s, $m ) ) {
		return $m[1] . '-' . $m[2] . '-' . $m[3];
	}
	if ( preg_match( '#(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})#', $s, $m ) ) {
		$d  = (int) $m[1];
		$th = (int) $m[2];
		if ( $th >= 1 && $th <= 12 && $d >= 1 && $d <= 31 ) {
			return sprintf( '%s-%02d-%02d', $m[3], $th, $d );
		}
	}
	return '';
}

/** Giờ trong ngày, 0–23. */
function khh_dt_gio( $v ) {
	if ( is_numeric( $v ) && $v >= 0 && $v < 1 ) {                 // 0.6923 = 16:37
		return (int) floor( $v * 24 );
	}
	if ( is_numeric( $v ) && $v > 20000 ) {
		return (int) gmdate( 'G', (int) round( ( $v - 25569 ) * 86400 ) );
	}
	if ( preg_match( '/(\d{1,2}):(\d{2})/', (string) $v, $m ) && (int) $m[1] < 24 ) {
		return (int) $m[1];
	}
	return 0;
}

/** Các tên cột FABi dùng, so khớp không dấu. */
function khh_dt_ten_cot() {
	return array(
		'cua_hang'   => array( 'cua hang', 'chi nhanh', 'co so' ),
		'pos_id'     => array( 'pos id' ),
		'ten_hang'   => array( 'ten hang', 'ten mon', 'ten san pham' ),
		'nhom_mon'   => array( 'nhom mon', 'nhom hang', 'danh muc' ),
		'loai_mon'   => array( 'loai mon' ),
		'pttt'       => array( 'pttt', 'hinh thuc thanh toan', 'phuong thuc thanh toan' ),
		'nguon'      => array( 'nguon' ),
		'ma_hd'      => array( 'ma hoa don', 'hoa don', 'ma hd', 'so hoa don' ),
		'ngay'       => array( 'thoi gian', 'ngay ban', 'ngay' ),
		'gio'        => array( 'gio' ),
		'so_luong'   => array( 'so luong' ),
		'thanh_tien' => array( 'thanh tien' ),
		'chiet_khau' => array( 'chiet khau' ),
		'giam_gia'   => array( 'giam gia' ),
		'tong_tien'  => array( 'tong tien' ),
	);
}

/** Dò xem cột nào nằm ở vị trí nào. */
function khh_dt_do_cot( $hdr ) {
	$n    = array_map( 'khh_dt_khong_dau', $hdr );
	$dung = array();
	$map  = array();
	foreach ( khh_dt_ten_cot() as $khoa => $mau ) {
		$map[ $khoa ] = -1;
		foreach ( $mau as $p ) {                                    // khớp đúng trước
			if ( $map[ $khoa ] > -1 ) {
				break;
			}
			foreach ( $n as $i => $h ) {
				if ( $h === $p && ! isset( $dung[ $i ] ) ) {
					$map[ $khoa ] = $i;
					$dung[ $i ]   = 1;
					break;
				}
			}
		}
		if ( -1 === $map[ $khoa ] ) {                               // rồi mới khớp đầu chuỗi
			foreach ( $mau as $p ) {
				if ( $map[ $khoa ] > -1 ) {
					break;
				}
				foreach ( $n as $i => $h ) {
					if ( 0 === strpos( $h, $p ) && ! isset( $dung[ $i ] ) ) {
						$map[ $khoa ] = $i;
						$dung[ $i ]   = 1;
						break;
					}
				}
			}
		}
	}
	return $map;
}

/* ------------------------------------------------------------------ *
 * Đọc file
 * ------------------------------------------------------------------ */

/**
 * Tạo file tạm.
 *
 * KHÔNG dùng wp_tempnam(): hàm đó nằm trong wp-admin/includes/file.php, không
 * được nạp khi chạy REST, gọi vào là lỗi "Call to undefined function".
 */
function khh_dt_file_tam( $tien_to = 'khh-dt-' ) {
	$thu_muc = trailingslashit( get_temp_dir() );
	$duong   = tempnam( $thu_muc, $tien_to );
	return $duong ? $duong : $thu_muc . $tien_to . wp_generate_password( 12, false ) . '.tmp';
}

/** Lấy một file trong .xlsx ra file tạm, trả đường dẫn (hoặc '' nếu không có). */
function khh_dt_rut_file( $zip, $ten ) {
	$st = $zip->getStream( $ten );
	if ( ! $st ) {
		return '';
	}
	$tam = khh_dt_file_tam();
	$out = fopen( $tam, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $out ) {
		fclose( $st ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return '';
	}
	stream_copy_to_stream( $st, $out );
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fclose( $st );  // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $tam;
}

/** Danh sách trang tính: [ ['ten'=>..., 'file'=>'xl/worksheets/sheet1.xml'], ... ] */
function khh_dt_ds_trang( $zip ) {
	$wb = $zip->getFromName( 'xl/workbook.xml' );
	if ( ! $wb ) {
		return array();
	}
	$rels = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
	$map  = array();
	if ( $rels && preg_match_all( '/Id="([^"]+)"[^>]*Target="([^"]+)"/', $rels, $mm, PREG_SET_ORDER ) ) {
		foreach ( $mm as $r ) {
			$t            = ltrim( $r[2], '/' );
			$map[ $r[1] ] = ( 0 === strpos( $t, 'xl/' ) ) ? $t : 'xl/' . $t;
		}
	}
	$ds = array();
	if ( preg_match_all( '/<sheet[^>]*name="([^"]*)"[^>]*r:id="([^"]+)"/', $wb, $mm, PREG_SET_ORDER ) ) {
		foreach ( $mm as $i => $s ) {
			$ds[] = array(
				'ten'  => html_entity_decode( $s[1], ENT_QUOTES, 'UTF-8' ),
				'file' => isset( $map[ $s[2] ] ) ? $map[ $s[2] ] : 'xl/worksheets/sheet' . ( $i + 1 ) . '.xml',
			);
		}
	}
	return $ds;
}

/** Bảng chuỗi dùng chung của .xlsx. */
function khh_dt_chuoi_chung( $zip ) {
	$tam = khh_dt_rut_file( $zip, 'xl/sharedStrings.xml' );
	if ( ! $tam ) {
		return array();
	}
	$ds = array();
	$r  = new XMLReader();
	if ( $r->open( $tam ) ) {
		while ( $r->read() ) {
			if ( XMLReader::ELEMENT === $r->nodeType && 'si' === $r->name ) {
				$xml  = $r->readInnerXml();
				$ds[] = html_entity_decode( wp_strip_all_tags( $xml ), ENT_QUOTES, 'UTF-8' );
			}
		}
		$r->close();
	}
	wp_delete_file( $tam );
	return $ds;
}

/**
 * Tách một thẻ <row> thành mảng ô theo thứ tự cột.
 *
 * Bản xuất của FABi có hai kiểu ô khác nhau tuỳ lúc:
 *   <c r="Q2" s="5"><v>25000</v></c>      — có ô nào bỏ trống thì nhảy cột
 *   <c><v>25000</v></c>                   — không thuộc tính, cột tính tuần tự
 *   <c t="inlineStr"><is><t>…</t></is></c> — chuỗi viết thẳng, không dùng bảng chuỗi
 * Đọc bằng SimpleXML nên kiểu nào cũng đúng; cắt bằng biểu thức chính quy thì
 * kiểu thứ hai bị bỏ sót và mọi cột lệch đi, tiền ra 0.
 */
function khh_dt_tach_o( $xml, $chuoi ) {
	$cu = libxml_use_internal_errors( true );
	$sx = simplexml_load_string( $xml );
	libxml_clear_errors();
	libxml_use_internal_errors( $cu );
	if ( false === $sx ) {
		return array();
	}
	$o = $sx->c;
	if ( ! count( $o ) ) {                 // phòng khi thẻ còn giữ không gian tên
		$o = $sx->children( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' )->c;
	}
	$dong = array();
	$i    = 0;
	foreach ( $o as $c ) {
		$thuoc = $c->attributes();
		$ref   = isset( $thuoc['r'] ) ? (string) $thuoc['r'] : '';
		$vt    = '' !== $ref ? khh_dt_cot_so( $ref ) : $i;
		$loai  = isset( $thuoc['t'] ) ? (string) $thuoc['t'] : '';
		$gt    = '';
		if ( 'inlineStr' === $loai ) {
			$gt = isset( $c->is->t ) ? (string) $c->is->t : '';
		} elseif ( isset( $c->v ) ) {
			$gt = (string) $c->v;
			if ( 's' === $loai ) {
				$k  = (int) $gt;
				$gt = isset( $chuoi[ $k ] ) ? $chuoi[ $k ] : '';
			} elseif ( is_numeric( $gt ) ) {
				$gt = 0 + $gt;
			}
		}
		$dong[ $vt ] = $gt;
		$i           = $vt + 1;
	}
	if ( ! $dong ) {
		return array();
	}
	$max = max( array_keys( $dong ) );
	$day = array();
	for ( $k = 0; $k <= $max; $k++ ) {
		$day[ $k ] = isset( $dong[ $k ] ) ? $dong[ $k ] : '';
	}
	return $day;
}

/**
 * Đọc một trang tính, gọi $cb cho từng dòng (mảng ô theo thứ tự cột).
 * Đọc theo dòng nên file mấy chục MB vẫn chạy được.
 */
function khh_dt_doc_trang( $duong_dan_xml, $chuoi, $cb ) {
	$r = new XMLReader();
	if ( ! $r->open( $duong_dan_xml ) ) {
		return 0;
	}
	$n = 0;
	// Nhảy tới thẻ <row> đầu tiên rồi đi ngang từ <row> này sang <row> kế.
	// (Dùng read() lồng trong vòng lặp là nhảy cóc mất một nửa số dòng.)
	while ( $r->read() && 'row' !== $r->name ) {
		continue;
	}
	while ( XMLReader::ELEMENT === $r->nodeType && 'row' === $r->name ) {
		$dong = khh_dt_tach_o( $r->readOuterXml(), $chuoi );
		if ( $dong ) {
			$n++;
			call_user_func( $cb, $dong );
		}
		$r->next( 'row' );
	}
	$r->close();
	return $n;
}

/* ------------------------------------------------------------------ *
 * Gộp số
 * ------------------------------------------------------------------ */

/**
 * Phân tích file FABi.
 *
 * @param string $duong_dan Đường dẫn file .xlsx hoặc .csv.
 * @param string $ten_file  Tên gốc để ghi lại là nạp từ đâu.
 * @return array|WP_Error   ['ngay'=>[...], 'tom_tat'=>[...]]
 */
function khh_dt_phan_tich( $duong_dan, $ten_file = '' ) {
	$duoi = strtolower( pathinfo( $ten_file ? $ten_file : $duong_dan, PATHINFO_EXTENSION ) );

	$gop = array(
		'ngay'    => array(),
		'hd'      => array(),
		'so_dong' => 0,
		'bo_qua'  => 0,
		'ky'      => '',
		'trang'   => '',
	);
	$map     = null;
	$hang    = 0;
	$xu_ly   = function ( $dong ) use ( &$gop, &$map, &$hang ) {
		$hang++;
		if ( null === $map ) {
			$kd = array_map( 'khh_dt_khong_dau', $dong );
			if ( in_array( 'thanh tien', $kd, true ) || in_array( 'ma hoa don', $kd, true ) || in_array( 'thoi gian', $kd, true ) ) {
				$map = khh_dt_do_cot( $dong );
			} elseif ( 1 === $hang ) {
				$gop['ky'] = trim( (string) ( isset( $dong[0] ) ? $dong[0] : '' ) );
			} elseif ( $hang > 8 ) {
				$map = false;                                        // không tìm thấy dòng tên cột
			}
			return;
		}
		if ( false === $map ) {
			return;
		}
		khh_dt_gop_dong( $dong, $map, $gop );
	};

	if ( 'csv' === $duoi || 'tsv' === $duoi || 'txt' === $duoi ) {
		$f = fopen( $duong_dan, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $f ) {
			return new WP_Error( 'khh_dt_file', 'Không mở được file.' );
		}
		$dau = ( 'tsv' === $duoi ) ? "\t" : ',';
		$bom = fread( $f, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $f );
		}
		/* ⚠️ TRUYỀN ĐỦ CẢ `$escape`. PHP 8.4 kêu Deprecated nếu thiếu, và hosting nào bật
		   `display_errors` thì dòng cảnh báo ấy in thẳng vào giữa JSON — giao diện nhận được
		   một chuỗi không phải JSON rồi báo "not valid JSON", đúng lỗi đã cắn ngày 01/09/2026.
		   Giữ nguyên `'\\'` (mặc định cũ) để cách đọc file không đổi. */
		while ( false !== ( $d = fgetcsv( $f, 0, $dau, '"', '\\' ) ) ) {
			if ( null === $d || ( 1 === count( $d ) && null === $d[0] ) ) {
				continue;
			}
			$xu_ly( $d );
		}
		fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	} else {
		if ( ! khh_dt_doc_duoc_xlsx() ) {
			return new WP_Error( 'khh_dt_zip', 'Máy chủ thiếu ZipArchive hoặc XMLReader nên chưa đọc được .xlsx. Anh xuất bản CSV từ FABi rồi nạp lại.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $duong_dan ) ) {
			return new WP_Error( 'khh_dt_zip', 'File .xlsx hỏng hoặc không mở được.' );
		}
		$ds = khh_dt_ds_trang( $zip );
		if ( ! $ds ) {
			$zip->close();
			return new WP_Error( 'khh_dt_sheet', 'Không đọc được danh sách trang tính trong file.' );
		}
		$chon = null;
		foreach ( $ds as $t ) {                                      // ưu tiên trang gộp
			if ( false !== strpos( khh_dt_khong_dau( $t['ten'] ), 'tat ca cua hang' ) ) {
				$chon = $t;
				break;
			}
		}
		if ( ! $chon ) {
			$chon = $ds[0];
		}
		$gop['trang'] = $chon['ten'];

		$chuoi = khh_dt_chuoi_chung( $zip );
		$tam   = khh_dt_rut_file( $zip, $chon['file'] );
		$zip->close();
		if ( ! $tam ) {
			return new WP_Error( 'khh_dt_sheet', 'Không rút được trang tính ra khỏi file nén.' );
		}
		khh_dt_doc_trang( $tam, $chuoi, $xu_ly );
		wp_delete_file( $tam );
	}

	if ( ! $map ) {
		return new WP_Error( 'khh_dt_cot', 'Không tìm thấy dòng tên cột. Anh xuất đúng bản "Báo cáo bán hàng" của FABi giúp em.' );
	}
	if ( ! $gop['ngay'] ) {
		return new WP_Error( 'khh_dt_ngay', 'Không đọc được ngày nào trong file. Kiểm tra lại cột Thời gian.' );
	}

	$ra    = array();
	$tong  = 0;
	$so_hd = 0;
	ksort( $gop['ngay'] );
	foreach ( $gop['ngay'] as $ngay => $ds_ch ) {
		foreach ( $ds_ch as $ten => $o ) {
			arsort( $o['mon_r'] );
			$mon = array();
			$i   = 0;
			foreach ( $o['mon_r'] as $ten_mon => $r ) {
				if ( $i++ >= 40 ) {
					break;
				}
				$mon[] = array(
					'n' => $ten_mon,
					'g' => isset( $o['mon_g'][ $ten_mon ] ) ? $o['mon_g'][ $ten_mon ] : '',
					'q' => $o['mon_q'][ $ten_mon ],
					'r' => $r,
				);
			}
			$ra[] = array(
				'ngay'       => $ngay,
				'cua_hang'   => $ten,
				'pos_id'     => $o['pos'],
				'doanh_thu'  => $o['dt'],
				'chiet_khau' => $o['ck'],
				'thanh_tien' => $o['tt'],
				'so_hd'      => $o['hd'],
				'so_mon'     => $o['sl'],
				'so_ve'      => $o['ve'],
				'gio'        => $o['gio'],
				'pttt'       => $o['pttt'],
				'nguon'      => $o['nguon'],
				'mon'        => $mon,
			);
			$tong  += $o['dt'];
			$so_hd += $o['hd'];
		}
	}

	$ky = $gop['ky'];
	if ( '' === $ky && $ra ) {
		$ky = 'Từ ' . gmdate( 'd/m/Y', strtotime( $ra[0]['ngay'] ) ) .
			' đến ' . gmdate( 'd/m/Y', strtotime( $ra[ count( $ra ) - 1 ]['ngay'] ) );
	}

	return array(
		'ngay'    => $ra,
		'tom_tat' => array(
			'nguon'         => $ten_file,
			'trang'         => $gop['trang'],
			'ky'            => $ky,
			'so_dong'       => $gop['so_dong'],
			'bo_qua'        => $gop['bo_qua'],
			'so_ban_ghi'    => count( $ra ),
			'tong_doanh_thu' => $tong,
			'so_hoa_don'    => $so_hd,
			'tu_ngay'       => $ra ? $ra[0]['ngay'] : '',
			'den_ngay'      => $ra ? $ra[ count( $ra ) - 1 ]['ngay'] : '',
		),
	);
}

/** Gộp một dòng vào kết quả. */
function khh_dt_gop_dong( $dong, $map, &$gop ) {
	$lay = function ( $khoa ) use ( $dong, $map ) {
		$i = isset( $map[ $khoa ] ) ? $map[ $khoa ] : -1;
		return ( $i > -1 && isset( $dong[ $i ] ) ) ? $dong[ $i ] : '';
	};

	$ngay = khh_dt_ngay( $lay( 'ngay' ) );
	if ( '' === $ngay ) {
		$gop['bo_qua']++;                                            // dòng "Tổng" cộng dồn của FABi
		return;
	}
	$gop['so_dong']++;

	$ten = trim( (string) $lay( 'cua_hang' ) );
	if ( '' === $ten ) {
		$ten = 'Toàn hệ thống';
	}
	$tt = khh_dt_so( $lay( 'thanh_tien' ) );
	$ck = khh_dt_so( $lay( 'chiet_khau' ) ) + khh_dt_so( $lay( 'giam_gia' ) );
	$dt = ( isset( $map['tong_tien'] ) && $map['tong_tien'] > -1 ) ? khh_dt_so( $lay( 'tong_tien' ) ) : ( $tt - $ck );
	$sl = khh_dt_so( $lay( 'so_luong' ) );
	$h  = khh_dt_gio( ( isset( $map['gio'] ) && $map['gio'] > -1 ) ? $lay( 'gio' ) : $lay( 'ngay' ) );

	if ( ! isset( $gop['ngay'][ $ngay ] ) ) {
		$gop['ngay'][ $ngay ] = array();
	}
	if ( ! isset( $gop['ngay'][ $ngay ][ $ten ] ) ) {
		$gop['ngay'][ $ngay ][ $ten ] = array(
			'pos'   => (string) $lay( 'pos_id' ),
			'dt'    => 0,
			'ck'    => 0,
			'tt'    => 0,
			'hd'    => 0,
			'sl'    => 0,
			'gio'   => array_fill( 0, 24, 0 ),
			've'    => 0,
			'pttt'  => array(),
			'nguon' => array(),
			'mon_q' => array(),
			'mon_r' => array(),
			'mon_g' => array(),
		);
	}
	$o = &$gop['ngay'][ $ngay ][ $ten ];

	$o['dt']         += $dt;
	$o['ck']         += $ck;
	$o['tt']         += $tt;
	$o['sl']         += $sl;
	$o['gio'][ $h ]  += $dt;

	// "Loại món" = Vé thì đếm riêng, để đối chiếu với số khách cơ sở báo.
	if ( 0 === strpos( khh_dt_khong_dau( (string) $lay( 'loai_mon' ) ), 've' ) ) {
		$o['ve'] += $sl;
	}

	$ma = trim( (string) $lay( 'ma_hd' ) );
	if ( '' !== $ma && '-' !== $ma ) {
		$k = $ngay . '|' . $ten . '|' . $ma;
		if ( ! isset( $gop['hd'][ $k ] ) ) {
			$gop['hd'][ $k ] = 1;
			$o['hd']++;
		}
	}
	$p = trim( (string) $lay( 'pttt' ) );
	if ( '' !== $p && '-' !== $p ) {
		$o['pttt'][ $p ] = ( isset( $o['pttt'][ $p ] ) ? $o['pttt'][ $p ] : 0 ) + $dt;
	}
	$g = trim( (string) $lay( 'nguon' ) );
	if ( '' !== $g && '-' !== $g ) {
		$o['nguon'][ $g ] = ( isset( $o['nguon'][ $g ] ) ? $o['nguon'][ $g ] : 0 ) + $dt;
	}
	$tm = trim( (string) $lay( 'ten_hang' ) );
	if ( '' !== $tm && '-' !== $tm ) {
		$o['mon_q'][ $tm ] = ( isset( $o['mon_q'][ $tm ] ) ? $o['mon_q'][ $tm ] : 0 ) + $sl;
		$o['mon_r'][ $tm ] = ( isset( $o['mon_r'][ $tm ] ) ? $o['mon_r'][ $tm ] : 0 ) + $dt;
		$nm                = trim( (string) $lay( 'nhom_mon' ) );
		if ( '' !== $nm && '-' !== $nm ) {
			$o['mon_g'][ $tm ] = $nm;
		}
	}
	unset( $o );
}
