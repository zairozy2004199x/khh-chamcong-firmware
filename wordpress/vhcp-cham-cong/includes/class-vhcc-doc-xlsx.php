<?php
/**
 * ĐỌC .XLSX — bộ đọc tối giản, không thư viện ngoài. Cặp với `VHCC_Xuat` (bộ ghi).
 *
 * Anh Thắng 18/09/2026, về quy trình sửa bảng công theo tuần: *"nạp bằng file .xlsx cho chuẩn
 * nhé"*. Hệ đã XUẤT được .xlsx từ 26/08; đây là chiều ngược lại.
 *
 * =============================================================================================
 * 🔴 TỆP NẠP LÊN LÀ TỆP NGƯỜI NGOÀI ĐƯA VÀO. ĐỌC NÓ LÀ MỞ MỘT CỬA
 * =============================================================================================
 * `.xlsx` là một tệp ZIP chứa mấy tệp XML. Hai kiểu tấn công kinh điển, và cả hai đều đi qua
 * đúng con đường này:
 *
 *   1. XXE — XML khai một thực thể ngoài trỏ tới `/etc/passwd` hay `wp-config.php`, rồi nhét
 *      nội dung ấy vào một ô. Người nạp mở bảng xem trước là đọc được mật khẩu cơ sở dữ liệu.
 *      Chặn: `LIBXML_NONET | LIBXML_NOENT` KHÔNG đủ — phải chặn `<!DOCTYPE` từ trước khi đưa
 *      vào bộ phân tích, và đó là việc `soat_xml()` làm.
 *   2. BOM ZIP — một tệp 40KB giải nén ra 4GB, máy chủ hết bộ nhớ. Chặn: xem trần ở dưới.
 *
 * ⚠️ VÀ MỘT CHỖ KHÔNG PHẢI TẤN CÔNG NHƯNG HỎNG THẬT: BỘ GHI DÙNG `inlineStr`, EXCEL THÌ KHÔNG.
 *    `VHCC_Xuat` cố ý ghi chuỗi thẳng vào ô (`<is><t>`) — xem lý do ở đầu tệp ấy. Nhưng người ta
 *    tải tệp về, MỞ BẰNG EXCEL rồi LƯU LẠI, và lúc ấy Excel viết lại toàn bộ theo
 *    `sharedStrings.xml`. Bộ đọc chỉ hiểu `inlineStr` thì đọc tệp mình vừa xuất ra thì ngon,
 *    còn tệp người ta gửi về thì mọi ô chữ đều RỖNG — và rỗng lặng lẽ, không lỗi gì.
 *    Nên ở đây hiểu ĐỦ CẢ BỐN kiểu ô: `s` · `inlineStr` · `str` · số trần.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_DocXlsx {

	/** Trần tệp nạp lên. Bảng công một tuần của một cơ sở không tới 1MB. */
	const TEP_TOI_DA = 5242880;          // 5 MB

	/** Trần sau khi giải nén MỖI tệp XML bên trong — chốt chống bom zip. */
	const XML_TOI_DA = 33554432;         // 32 MB

	/** Trần số dòng đọc về. Quá ngần này thì đây không còn là bảng công một tuần. */
	const DONG_TOI_DA = 5000;

	/** Trần số cột. */
	const COT_TOI_DA = 200;

	public static function co() { return class_exists( 'ZipArchive' ); }

	/**
	 * Đọc TỜ ĐẦU TIÊN của một tệp .xlsx thành mảng hai chiều.
	 *
	 * ⚠️ TRẢ VỀ Ô RỖNG CHO CHỖ TRỐNG, không rút ngắn hàng. Excel bỏ hẳn thẻ `<c>` của ô trống,
	 *    nên đọc thô thì hàng "A, (trống), C" ra hai phần tử và cột C tụt sang chỗ của B — cả
	 *    bảng lệch một cột mà không có gì báo. Vị trí ô đọc từ thuộc tính `r` ("C7"), không đếm
	 *    theo thứ tự xuất hiện.
	 *
	 * @param string $duong đường dẫn tệp trên đĩa.
	 * @return array ok · hang (mảng hàng, mỗi hàng là mảng chuỗi) · error
	 */
	public static function doc( $duong ) {
		if ( ! self::co() ) {
			return self::loi( 'Máy chủ chưa bật php-zip nên chưa đọc được .xlsx. Nhờ bên hosting bật giúp.' );
		}
		if ( ! is_string( $duong ) || '' === $duong || ! is_readable( $duong ) ) {
			return self::loi( 'Không mở được tệp vừa nạp.' );
		}
		$co = (int) @filesize( $duong );
		if ( $co <= 0 ) { return self::loi( 'Tệp rỗng.' ); }
		if ( $co > self::TEP_TOI_DA ) {
			return self::loi( 'Tệp nặng quá ' . round( self::TEP_TOI_DA / 1048576 ) . 'MB. '
				. 'Bảng công một tuần không nặng thế — kiểm lại xem có gửi nhầm tệp không.' );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $duong ) ) {
			return self::loi( 'Tệp này không phải .xlsx (mở ra không thấy cấu trúc Excel). '
				. 'Nếu vừa lưu bằng Google Sheets thì chọn Tệp → Tải xuống → Microsoft Excel (.xlsx).' );
		}

		$ra = self::doc_zip( $zip );
		$zip->close();
		return $ra;
	}

	private static function doc_zip( $zip ) {
		/* Tờ đầu tiên theo THỨ TỰ TRONG WORKBOOK, không phải `sheet1.xml`. Xoá một tờ rồi thêm
		   tờ khác thì tờ đầu có thể là `sheet3.xml` — đọc theo tên tệp là đọc nhầm tờ. */
		$duong_to = self::to_dau( $zip );
		if ( '' === $duong_to ) { return self::loi( 'Tệp .xlsx này không có tờ nào đọc được.' ); }

		$chuoi = self::chuoi_chung( $zip );
		$xml   = self::lay( $zip, $duong_to );
		if ( null === $xml ) { return self::loi( 'Không đọc được nội dung tờ đầu của tệp.' ); }

		$to = self::nap_xml( $xml );
		if ( ! $to ) { return self::loi( 'Nội dung tệp hỏng hoặc không phải bảng tính hợp lệ.' ); }

		$hang  = array();
		$cot_max = 0;
		foreach ( $to->getElementsByTagName( 'row' ) as $r ) {
			if ( count( $hang ) >= self::DONG_TOI_DA ) { break; }
			$so_hang = (int) $r->getAttribute( 'r' );
			$dong = array();
			foreach ( $r->getElementsByTagName( 'c' ) as $c ) {
				$i = self::cot_so( (string) $c->getAttribute( 'r' ) );
				if ( $i < 0 || $i >= self::COT_TOI_DA ) { continue; }
				$dong[ $i ] = self::gia_tri( $c, $chuoi );
				if ( $i + 1 > $cot_max ) { $cot_max = $i + 1; }
			}
			/* Giữ CHỖ TRỐNG: điền rỗng cho mọi chỉ số chưa có, rồi sắp theo chỉ số. */
			$hang[ $so_hang > 0 ? $so_hang - 1 : count( $hang ) ] = $dong;
		}
		if ( ! $hang ) { return self::loi( 'Tờ đầu của tệp không có dòng nào.' ); }

		ksort( $hang );
		$ra = array();
		$cuoi = max( array_keys( $hang ) );
		for ( $i = 0; $i <= $cuoi && $i < self::DONG_TOI_DA; $i++ ) {
			$d = isset( $hang[ $i ] ) ? $hang[ $i ] : array();
			$mot = array();
			for ( $j = 0; $j < $cot_max; $j++ ) {
				$mot[] = isset( $d[ $j ] ) ? $d[ $j ] : '';
			}
			$ra[] = $mot;
		}
		return array( 'ok' => true, 'hang' => $ra );
	}

	/* ====================================================================== từng mảnh */

	/** Đường dẫn tờ ĐẦU TIÊN theo thứ tự khai trong workbook. */
	private static function to_dau( $zip ) {
		$wb = self::lay( $zip, 'xl/workbook.xml' );
		if ( null === $wb ) { return ''; }
		$d = self::nap_xml( $wb );
		if ( ! $d ) { return ''; }

		$rid = '';
		foreach ( $d->getElementsByTagName( 'sheet' ) as $s ) {
			$rid = (string) $s->getAttributeNS( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id' );
			if ( '' === $rid ) { $rid = (string) $s->getAttribute( 'r:id' ); }
			break;
		}
		if ( '' === $rid ) { return 'xl/worksheets/sheet1.xml'; }

		$rel = self::lay( $zip, 'xl/_rels/workbook.xml.rels' );
		if ( null === $rel ) { return 'xl/worksheets/sheet1.xml'; }
		$dr = self::nap_xml( $rel );
		if ( ! $dr ) { return 'xl/worksheets/sheet1.xml'; }
		foreach ( $dr->getElementsByTagName( 'Relationship' ) as $x ) {
			if ( (string) $x->getAttribute( 'Id' ) !== $rid ) { continue; }
			$t = ltrim( (string) $x->getAttribute( 'Target' ), '/' );
			if ( '' === $t ) { break; }
			/* Target thường là tương đối với `xl/` ("worksheets/sheet1.xml"). */
			return ( 0 === strpos( $t, 'xl/' ) ) ? $t : ( 'xl/' . $t );
		}
		return 'xl/worksheets/sheet1.xml';
	}

	/**
	 * Bảng chuỗi chung. Rỗng khi tệp không có — tệp do `VHCC_Xuat` ghi thì đúng là không có.
	 *
	 * ⚠️ MỘT Ô CÓ THỂ GỒM NHIỀU MẨU `<r><t>`. Excel chẻ chuỗi ra khi trong ô có đoạn in đậm hay
	 *    đổi màu. Lấy mỗi mẩu đầu là mất phần đuôi — "Nguyễn Văn A" thành "Nguyễn ".
	 */
	private static function chuoi_chung( $zip ) {
		$x = self::lay( $zip, 'xl/sharedStrings.xml' );
		if ( null === $x ) { return array(); }
		$d = self::nap_xml( $x );
		if ( ! $d ) { return array(); }
		$ra = array();
		foreach ( $d->getElementsByTagName( 'si' ) as $si ) {
			$s = '';
			foreach ( $si->getElementsByTagName( 't' ) as $t ) { $s .= $t->textContent; }
			$ra[] = $s;
		}
		return $ra;
	}

	/** Giá trị một ô, hiểu đủ bốn kiểu. */
	private static function gia_tri( $c, $chuoi ) {
		$kieu = (string) $c->getAttribute( 't' );

		if ( 'inlineStr' === $kieu ) {
			$s = '';
			foreach ( $c->getElementsByTagName( 't' ) as $t ) { $s .= $t->textContent; }
			return $s;
		}

		$v = '';
		foreach ( $c->getElementsByTagName( 'v' ) as $x ) { $v = $x->textContent; break; }

		if ( 's' === $kieu ) {
			$i = (int) $v;
			return isset( $chuoi[ $i ] ) ? $chuoi[ $i ] : '';
		}
		if ( 'b' === $kieu ) { return ( '1' === trim( $v ) ) ? '1' : '0'; }
		/* `str` = kết quả công thức dạng chữ; `e` = ô lỗi (#REF!, #N/A) — trả nguyên văn để
		   người soát thấy mà sửa, chứ không nuốt thành rỗng. */
		return (string) $v;
	}

	/** "C7" -> 2. Trả -1 khi không đọc được. */
	public static function cot_so( $o ) {
		if ( ! preg_match( '/^([A-Za-z]+)/', (string) $o, $m ) ) { return -1; }
		$s = strtoupper( $m[1] );
		$n = 0;
		for ( $i = 0; $i < strlen( $s ); $i++ ) {
			$n = $n * 26 + ( ord( $s[ $i ] ) - 64 );
		}
		return $n - 1;
	}

	/* ====================================================================== an toàn */

	/** Lấy một tệp trong zip, có chốt trần giải nén. null = không có hoặc quá trần. */
	private static function lay( $zip, $ten ) {
		$i = $zip->locateName( $ten, ZipArchive::FL_NOCASE );
		if ( false === $i ) { return null; }
		$tt = $zip->statIndex( $i );
		if ( ! $tt || (int) $tt['size'] > self::XML_TOI_DA ) { return null; }
		$s = $zip->getFromIndex( $i, self::XML_TOI_DA );
		return ( false === $s ) ? null : $s;
	}

	/**
	 * Biến chuỗi XML thành cây, sau khi đã soát.
	 *
	 * 🔴 CHẶN `<!DOCTYPE` TRƯỚC KHI PHÂN TÍCH, không tin vào cờ của libxml. Mấy bản libxml/PHP
	 *    khác nhau bật tắt thực thể ngoài khác nhau, và `libxml_disable_entity_loader()` thì bỏ
	 *    từ PHP 8.0. Từ chối thẳng tệp có DOCTYPE là luật không phụ thuộc vào bản nào: bảng tính
	 *    thật không bao giờ cần khai kiểu tài liệu.
	 */
	private static function nap_xml( $s ) {
		if ( ! self::soat_xml( $s ) ) { return null; }
		$cu = libxml_use_internal_errors( true );
		$d  = new DOMDocument();
		$ok = $d->loadXML( $s, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $cu );
		return $ok ? $d : null;
	}

	/** false = có mùi, đừng phân tích. */
	public static function soat_xml( $s ) {
		if ( ! is_string( $s ) || '' === $s ) { return false; }
		$dau = substr( $s, 0, 4096 );
		if ( false !== stripos( $dau, '<!DOCTYPE' ) ) { return false; }
		if ( false !== stripos( $dau, '<!ENTITY' ) ) { return false; }
		return true;
	}

	private static function loi( $cau ) {
		return array( 'ok' => false, 'error' => $cau, 'hang' => array() );
	}
}
