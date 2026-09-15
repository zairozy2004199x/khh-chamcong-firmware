<?php
/**
 * Đọc file Excel .xlsx bằng PHP thuần — không cần thư viện ngoài.
 *
 * File .xlsx thật ra là một file nén chứa các file XML. Hàm dưới mở file nén,
 * đọc bảng tính đầu tiên, tra bảng chuỗi dùng chung, và đổi những ô định dạng
 * ngày từ số thứ tự của Excel về dạng dd/mm/yyyy để phần nhập hiểu được.
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Máy có đọc được .xlsx không (cần ZipArchive và SimpleXML). */
function khh_xlsx_ready() {
	return class_exists( 'ZipArchive' ) && function_exists( 'simplexml_load_string' );
}

/** "BC12" → 54 (số thứ tự cột, tính từ 0). */
function khh_xlsx_col( $ref ) {
	$n   = 0;
	$len = strlen( $ref );
	for ( $i = 0; $i < $len; $i++ ) {
		$c = $ref[ $i ];
		if ( $c < 'A' || $c > 'Z' ) {
			break;
		}
		$n = $n * 26 + ( ord( $c ) - 64 );
	}
	return $n > 0 ? $n - 1 : 0;
}

/** Mã định dạng này có phải định dạng ngày không. */
function khh_xlsx_fmt_is_date( $id, $code ) {
	$id = (int) $id;
	// Các mã ngày/giờ dựng sẵn của Excel.
	if ( ( $id >= 14 && $id <= 22 ) || ( $id >= 45 && $id <= 47 ) || ( $id >= 27 && $id <= 36 ) || ( $id >= 50 && $id <= 58 ) ) {
		return true;
	}
	if ( '' === (string) $code ) {
		return false;
	}
	// Bỏ phần trong ngoặc kép và ngoặc vuông rồi mới dò ký tự ngày giờ.
	$c = preg_replace( '/"[^"]*"/', '', (string) $code );
	$c = preg_replace( '/\[[^\]]*\]/', '', $c );
	return (bool) preg_match( '/[ymdhs]/i', $c );
}

/** Số thứ tự ngày của Excel → "dd/mm/yyyy". */
function khh_xlsx_serial_date( $serial, $base1904 = false ) {
	$serial = (float) $serial;
	if ( $serial <= 0 ) {
		return '';
	}
	$offset = $base1904 ? 24107 : 25569;
	$ts     = ( $serial - $offset ) * 86400;
	if ( $ts < -2208988800 || $ts > 4102444800 ) { // ngoài khoảng 1900–2100 thì coi như không phải ngày
		return '';
	}
	return gmdate( 'd/m/Y', (int) round( $ts ) );
}

/** Lấy nội dung một mục trong file nén, trả về '' nếu không có. */
function khh_xlsx_entry( $zip, $name ) {
	$raw = $zip->getFromName( $name );
	return false === $raw ? '' : $raw;
}

/** Đọc XML mà không cho phép thực thể ngoài (chống XXE). */
function khh_xlsx_xml( $raw ) {
	if ( '' === $raw ) {
		return null;
	}
	$prev = libxml_use_internal_errors( true );
	if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
		$old = libxml_disable_entity_loader( true ); // phpcs:ignore
	}
	$xml = simplexml_load_string( $raw, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT );
	if ( isset( $old ) && function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
		libxml_disable_entity_loader( $old ); // phpcs:ignore
	}
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	return false === $xml ? null : $xml;
}

/** Đường dẫn tới bảng tính đầu tiên trong file. */
function khh_xlsx_first_sheet( $zip ) {
	$wb = khh_xlsx_xml( khh_xlsx_entry( $zip, 'xl/workbook.xml' ) );
	if ( $wb && isset( $wb->sheets->sheet[0] ) ) {
		$rid = '';
		foreach ( $wb->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' ) as $k => $v ) {
			if ( 'id' === (string) $k ) {
				$rid = (string) $v;
			}
		}
		if ( $rid ) {
			$rels = khh_xlsx_xml( khh_xlsx_entry( $zip, 'xl/_rels/workbook.xml.rels' ) );
			if ( $rels ) {
				foreach ( $rels->Relationship as $r ) {
					if ( (string) $r['Id'] === $rid ) {
						$t = ltrim( (string) $r['Target'], '/' );
						if ( 0 !== strpos( $t, 'xl/' ) ) {
							$t = 'xl/' . $t;
						}
						return $t;
					}
				}
			}
		}
	}
	return 'xl/worksheets/sheet1.xml';
}

/** Bảng chuỗi dùng chung. */
function khh_xlsx_shared( $zip ) {
	$xml = khh_xlsx_xml( khh_xlsx_entry( $zip, 'xl/sharedStrings.xml' ) );
	$out = array();
	if ( ! $xml ) {
		return $out;
	}
	foreach ( $xml->si as $si ) {
		$txt = '';
		if ( isset( $si->t ) ) {
			$txt = (string) $si->t;
		}
		if ( isset( $si->r ) ) {             // chuỗi bị chia nhỏ do định dạng chữ
			foreach ( $si->r as $r ) {
				$txt .= (string) $r->t;
			}
		}
		$out[] = $txt;
	}
	return $out;
}

/** Ô nào là ngày: trả về mảng chỉ số kiểu ô → true/false. */
function khh_xlsx_date_styles( $zip ) {
	$xml = khh_xlsx_xml( khh_xlsx_entry( $zip, 'xl/styles.xml' ) );
	$out = array();
	if ( ! $xml ) {
		return $out;
	}
	$codes = array();
	if ( isset( $xml->numFmts ) ) {
		foreach ( $xml->numFmts->numFmt as $f ) {
			$codes[ (string) $f['numFmtId'] ] = (string) $f['formatCode'];
		}
	}
	if ( isset( $xml->cellXfs ) ) {
		$i = 0;
		foreach ( $xml->cellXfs->xf as $xf ) {
			$id         = (string) $xf['numFmtId'];
			$out[ $i++ ] = khh_xlsx_fmt_is_date( $id, isset( $codes[ $id ] ) ? $codes[ $id ] : '' );
		}
	}
	return $out;
}

/**
 * Đọc .xlsx thành mảng dòng × cột, giống hệt kết quả của khh_parse_csv().
 *
 * @param string $path Đường dẫn file trên máy chủ.
 * @return array Mảng các dòng; dòng đầu là tiêu đề cột.
 */
function khh_parse_xlsx( $path ) {
	if ( ! khh_xlsx_ready() || ! is_readable( $path ) ) {
		return array();
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return array();
	}

	$wbraw    = khh_xlsx_entry( $zip, 'xl/workbook.xml' );
	$base1904 = (bool) preg_match( '/date1904\s*=\s*"(1|true)"/i', $wbraw );
	$shared   = khh_xlsx_shared( $zip );
	$dates    = khh_xlsx_date_styles( $zip );
	$sheet    = khh_xlsx_xml( khh_xlsx_entry( $zip, khh_xlsx_first_sheet( $zip ) ) );
	$zip->close();

	if ( ! $sheet || ! isset( $sheet->sheetData ) ) {
		return array();
	}

	$rows = array();
	foreach ( $sheet->sheetData->row as $row ) {
		$line = array();
		$col  = 0;
		foreach ( $row->c as $c ) {
			$ref = (string) $c['r'];
			if ( '' !== $ref ) {
				$col = khh_xlsx_col( $ref );   // giữ đúng cột khi file bỏ trống ô ở giữa
			}
			$type = (string) $c['t'];
			$val  = '';
			if ( 'inlineStr' === $type ) {
				$val = isset( $c->is->t ) ? (string) $c->is->t : '';
				if ( isset( $c->is->r ) ) {
					foreach ( $c->is->r as $r ) {
						$val .= (string) $r->t;
					}
				}
			} elseif ( 's' === $type ) {
				$i   = (int) $c->v;
				$val = isset( $shared[ $i ] ) ? $shared[ $i ] : '';
			} elseif ( 'b' === $type ) {
				$val = ( '1' === (string) $c->v ) ? 'TRUE' : 'FALSE';
			} elseif ( isset( $c->v ) ) {
				$val = (string) $c->v;
				$sid = (string) $c['s'];
				if ( '' !== $sid && ! empty( $dates[ (int) $sid ] ) && is_numeric( $val ) ) {
					$d = khh_xlsx_serial_date( $val, $base1904 );
					if ( '' !== $d ) {
						$val = $d;
					}
				}
			}
			while ( count( $line ) < $col ) {
				$line[] = '';
			}
			$line[] = trim( $val );
			$col++;
		}
		$rows[] = $line;
	}

	// Bỏ những dòng trống hoàn toàn ở cuối bảng.
	while ( $rows ) {
		$last  = end( $rows );
		$empty = true;
		foreach ( $last as $cell ) {
			if ( '' !== $cell ) {
				$empty = false;
				break;
			}
		}
		if ( ! $empty ) {
			break;
		}
		array_pop( $rows );
	}

	// Cho mọi dòng dài bằng dòng tiêu đề để phần ghép cột không bị lệch.
	$width = 0;
	foreach ( $rows as $r ) {
		$width = max( $width, count( $r ) );
	}
	foreach ( $rows as $i => $r ) {
		while ( count( $rows[ $i ] ) < $width ) {
			$rows[ $i ][] = '';
		}
	}
	return $rows;
}
