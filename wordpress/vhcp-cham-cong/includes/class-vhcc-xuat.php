<?php
/**
 * XUẤT .XLSX — bộ ghi tối giản, không thư viện ngoài.
 *
 * Anh Thắng 26/08/2026: *"bổ sung tính năng xuất excel, khi xuất thì nó sẽ chi tiết ca đó từ
 * mấy h đến mấy h"*.
 *
 * 🔴 VÌ SAO KHÔNG DÙNG .CSV CHO NHANH.
 *    Excel bản tiếng Việt đọc .csv theo dấu phân cách của HỆ ĐIỀU HÀNH — máy đặt dấu phẩy thì
 *    ra một kiểu, đặt chấm phẩy ra một kiểu, và mã nhân viên kiểu `0029` bị Excel nuốt số 0 ở
 *    đầu thành `29`. Cả hai đều hỏng IM LẶNG: tệp vẫn mở ra, vẫn có bảng, chỉ là sai. Gửi cho
 *    các bộ phận thì không ai kiểm lại được. `.xlsx` thì kiểu ô nằm trong chính tệp.
 *
 * ⚠️ Ghi chuỗi kiểu `inlineStr`, KHÔNG dùng bảng `sharedStrings`. Bảng chia sẻ tiết kiệm dung
 *    lượng khi có nhiều chuỗi lặp, nhưng nó là một tệp thứ hai phải khớp chỉ số với từng ô —
 *    lệch một chỉ số là cả bảng đọc nhầm chữ của ô khác, mà tệp vẫn mở được. Với vài nghìn dòng
 *    thì chỗ tiết kiệm ấy không đáng đổi lấy một kiểu hỏng như vậy.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Xuat {

	/** Máy chủ có dựng được .xlsx không (cần ZipArchive). */
	public static function co_xlsx() {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * Dựng tệp .xlsx trong bộ nhớ.
	 *
	 * @param array $to  [ [ 'ten' => 'Chi tiết ca', 'hang' => [ [ô, ô…], … ] ], … ]
	 * @return string|null nội dung tệp, hoặc null nếu máy chủ không dựng được.
	 */
	public static function xlsx( $to ) {
		if ( ! self::co_xlsx() ) { return null; }
		$to = array_values( (array) $to );
		if ( ! $to ) { return null; }

		$tam = VHCC_DB::tep_tam( 'vhcc-xuat' );
		if ( ! $tam ) { return null; }
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tam, ZipArchive::OVERWRITE ) ) { @unlink( $tam ); return null; }

		$zip->addFromString( '[Content_Types].xml', self::content_types( count( $to ) ) );
		$zip->addFromString( '_rels/.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
			. ' Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', self::workbook( $to ) );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::wb_rels( count( $to ) ) );
		$zip->addFromString( 'xl/styles.xml', self::styles() );
		foreach ( $to as $i => $t ) {
			$zip->addFromString( 'xl/worksheets/sheet' . ( $i + 1 ) . '.xml',
				self::sheet( isset( $t['hang'] ) ? $t['hang'] : array() ) );
		}
		$zip->close();

		$noi = file_get_contents( $tam );
		@unlink( $tam );
		return ( false === $noi ) ? null : $noi;
	}

	/* ==================================================================== các phần */

	private static function content_types( $so ) {
		$x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
		for ( $i = 1; $i <= $so; $i++ ) {
			$x .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml"'
				. ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		return $x . '</Types>';
	}

	private static function workbook( $to ) {
		$x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
		foreach ( $to as $i => $t ) {
			$x .= '<sheet name="' . self::ten_to( isset( $t['ten'] ) ? $t['ten'] : '', $i )
				. '" sheetId="' . ( $i + 1 ) . '" r:id="rId' . ( $i + 1 ) . '"/>';
		}
		return $x . '</sheets></workbook>';
	}

	private static function wb_rels( $so ) {
		$x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		for ( $i = 1; $i <= $so; $i++ ) {
			$x .= '<Relationship Id="rId' . $i . '"'
				. ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
				. ' Target="worksheets/sheet' . $i . '.xml"/>';
		}
		$x .= '<Relationship Id="rId' . ( $so + 1 ) . '"'
			. ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
			. ' Target="styles.xml"/>';
		return $x . '</Relationships>';
	}

	/** Hai kiểu ô: 0 = thường, 1 = đậm (dòng tiêu đề). Đủ dùng, không bày thêm. */
	private static function styles() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="2"><fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="gray125"/></fill></fills>'
			. '<borders count="1"><border/></borders>'
			. '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
			. '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
			. '</styleSheet>';
	}

	private static function sheet( $hang ) {
		$x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
		foreach ( array_values( (array) $hang ) as $r => $dong ) {
			$x .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( (array) $dong ) as $c => $o ) {
				$x .= self::o( self::cot( $c ) . ( $r + 1 ), $o, 0 === $r );
			}
			$x .= '</row>';
		}
		return $x . '</sheetData></worksheet>';
	}

	/**
	 * Một ô.
	 *
	 * 🔴 SỐ VIẾT LÀ SỐ, CHỮ VIẾT LÀ CHỮ — và mã nhân viên LUÔN là chữ. Mã `MNNV2MTD0029` thì
	 *    hiển nhiên là chữ, nhưng mã `0029` hay `17` mà để Excel tự đoán là nó thành số 29 và 17,
	 *    mất số 0 ở đầu. Nên nơi gọi bọc mọi mã bằng `chu()`, còn ở đây chỉ số THẬT mới thành số.
	 */
	private static function o( $vt, $gia, $dam ) {
		$s = $dam ? ' s="1"' : '';
		if ( is_array( $gia ) && isset( $gia['chu'] ) ) { $gia = (string) $gia['chu']; }
		elseif ( is_int( $gia ) || is_float( $gia ) ) {
			return '<c r="' . $vt . '"' . $s . '><v>' . ( 0 + $gia ) . '</v></c>';
		}
		$gia = (string) $gia;
		if ( '' === $gia ) { return '<c r="' . $vt . '"' . $s . '/>'; }
		return '<c r="' . $vt . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">'
			. self::x( $gia ) . '</t></is></c>';
	}

	/** Bọc một giá trị để nó CHẮC CHẮN là chữ, kể cả khi trông như số. */
	public static function chu( $v ) { return array( 'chu' => (string) $v ); }

	/** 0 -> A, 25 -> Z, 26 -> AA. */
	public static function cot( $i ) {
		$i = (int) $i;
		$s = '';
		do {
			$s = chr( 65 + ( $i % 26 ) ) . $s;
			$i = intdiv( $i, 26 ) - 1;
		} while ( $i >= 0 );
		return $s;
	}

	/**
	 * Tên trang tính hợp lệ. Excel cấm : \ / ? * [ ] và giới hạn 31 ký tự — quá tay là tệp
	 * KHÔNG MỞ ĐƯỢC, báo "found unreadable content", chứ không phải cắt bớt cho.
	 */
	public static function ten_to( $ten, $i = 0 ) {
		$t = trim( str_replace( array( ':', '\\', '/', '?', '*', '[', ']' ), ' ', (string) $ten ) );
		$t = trim( preg_replace( '/\s+/', ' ', $t ) );
		if ( '' === $t ) { $t = 'Trang ' . ( (int) $i + 1 ); }
		if ( function_exists( 'mb_substr' ) ) { $t = mb_substr( $t, 0, 31, 'UTF-8' ); }
		else { $t = substr( $t, 0, 31 ); }
		return self::x( $t );
	}

	/** Thoát XML. `&` phải đi trước, kẻo nó thoát lại chính dấu & của mấy cái kia. */
	private static function x( $s ) {
		return str_replace( array( '&', '<', '>', '"', "'" ),
			array( '&amp;', '&lt;', '&gt;', '&quot;', '&apos;' ), (string) $s );
	}

	/* ==================================================================== gửi về trình duyệt */

	public static function gui( $ten_tep, $noi_dung ) {
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $ten_tep . '"' );
		header( 'Content-Length: ' . strlen( $noi_dung ) );
		echo $noi_dung; // phpcs:ignore WordPress.Security.EscapeOutput -- nhị phân .xlsx
		/* 🔴 `exit` GIỮA BÀI KIỂM LÀ MỘT CÁI BẪY IM LẶNG. Bài kiểm chạy trong cùng một tiến
		   trình: `exit` ở đây kết thúc luôn cả bài, mọi phép thử phía sau biến mất, mà mã trả
		   về vẫn là 0 — nên bài kiểm báo "đạt" trong khi nó chỉ chạy được nửa chừng.
		   Phá thử phát hiện: bỏ đường chẩn đoán xuất đi mà không phép thử nào đỏ, vì luồng rơi
		   xuống đây rồi tắt máy. Cùng cái mối hẹp đã dùng ở `VHCC_Web::ve()`. */
		if ( defined( 'VHCC_TEST' ) ) { return; }
		exit;
	}
}
