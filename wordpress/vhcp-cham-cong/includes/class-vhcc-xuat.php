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
	/**
	 * ⚠️ HAI Ô NÀY LÀ ĐỂ SOI, KHÔNG PHẢI ĐỂ ĐIỀU KHIỂN.
	 *
	 * `$ep_khong_zip` cho bộ thử dựng lại đúng cái hoàn cảnh không dựng được trên máy nào chạy
	 * bộ thử: **hosting thiếu `php-zip`**. Đó là hoàn cảnh THẬT — anh Thắng đang ở hosting chia
	 * sẻ — và là hoàn cảnh mà đường xuất ảnh sinh ra để cứu. Không có nó thì nhánh "ảnh không
	 * cần ZipArchive" là nhánh không phép thử nào phân biệt được: bỏ chốt đi vẫn xanh.
	 *
	 * `$mime_da_gui` giữ lại kiểu MIME của lượt gửi gần nhất, vì `header()` gọi trong CLI
	 * không ghi lại đâu cả — gửi ảnh dưới tên MIME của Excel là cái sai mà không cách nào
	 * chạm tới nếu không có ô này.
	 */
	public static $ep_khong_zip = false;
	public static $mime_da_gui  = '';
	/** Nguyên văn các dòng đầu tệp của lượt gửi gần nhất — xem chú thích ở `dau()`. */
	public static $dau_da_gui   = array();

	public static function co_xlsx() {
		if ( self::$ep_khong_zip ) { return false; }
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
			$zip->addFromString( 'xl/worksheets/sheet' . ( $i + 1 ) . '.xml', self::sheet( $t ) );
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
		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 BẢO EXCEL TÍNH LẠI LÚC MỞ — KHÔNG CÓ DÒNG NÀY THÌ MỌI Ô CÔNG THỨC RA Ô TRỐNG.
		 *
		 * Anh Thắng 16/09/2026 gửi ảnh file lương: cột **Lương chính** và **TOTAL SALARY** trống
		 * trơn, mà thanh công thức của Excel VẪN hiện `=G9*H9`. Tức công thức có được ghi, chỉ là
		 * Excel không chịu tính.
		 *
		 * Vì sao: `o()` ghi `<f>` mà CỐ Ý không kèm `<v>` (xem chú thích ở đó — một giá trị đệm
		 * đoán sẵn là con số trông như thật mà sai). Đúng. Nhưng thiếu `<calcPr>` thì Excel coi
		 * workbook này "đã tính rồi", đi đọc giá trị đệm, không thấy gì, và in ra ô trống. Hai
		 * quyết định đúng riêng lẻ, ghép lại thành một tờ lương không có tiền.
		 *
		 * `fullCalcOnLoad="1"` bắt tính lại toàn bộ lúc mở. Giữ được cả hai: mở ra là có số NGAY,
		 * mà kế toán gõ vào mấy cột phụ cấp thì tổng vẫn tự nhảy.
		 *
		 * ⚠️ VỊ TRÍ LÀ BẮT BUỘC: lược đồ `CT_Workbook` xếp `calcPr` SAU `</sheets>`. Đặt trước là
		 *    Excel từ chối mở cả tệp và không nói vì sao — cùng loại bẫy với `<cols>`/`<mergeCells>`
		 *    ở `to()`, và bài kiểm canh thứ tự ấy cũng canh cái này.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		return $x . '</sheets><calcPr calcId="0" fullCalcOnLoad="1"/></workbook>';
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

	/**
	 * BẢNG KIỂU Ô.
	 *
	 * 🔴 CHỈ SỐ 0 VÀ 1 LÀ HỢP ĐỒNG CŨ, KHÔNG ĐƯỢC ĐỔI CHỖ.
	 *    Bản đầu chỉ có hai kiểu (thường / đậm) và `sheet()` tự đóng `s="1"` cho dòng đầu. Mọi
	 *    nơi đang xuất .xlsx vẫn trông vào hai chỉ số ấy. Chèn kiểu mới vào GIỮA là mọi tiêu đề
	 *    cũ đổi kiểu mà không ai sửa gì — nên kiểu mới CHỈ thêm vào CUỐI.
	 *
	 * 2 tiêu đề to · 3 băng tiêu đề cột · 4 chữ có viền · 5 tiền có viền · 6 giờ có viền
	 * 7 dòng tên khối · 8 dòng tổng · 9 số thứ tự (canh giữa, có viền)
	 */
	private static function styles() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			/* 164 tiền: không lẻ, có dấu phân nhóm. 165 giờ: hai số lẻ — 19,2 và 49,15 là giờ thật
			   trong file kế toán, làm tròn lên số nguyên là lệch tiền ngay dòng đầu tiên. */
			. '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0"/>'
			. '<numFmt numFmtId="165" formatCode="0.00"/></numFmts>'
			. '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="14"/><name val="Calibri"/></font></fonts>'
			. '<fills count="4"><fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/>'
			. '<bgColor indexed="64"/></patternFill></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F2F2"/>'
			. '<bgColor indexed="64"/></patternFill></fill></fills>'
			. '<borders count="2"><border/>'
			. '<border><left style="thin"/><right style="thin"/><top style="thin"/>'
			. '<bottom style="thin"/></border></borders>'
			. '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
			. '<cellXfs count="12">'
			. '<xf xfId="0"/>'
			. '<xf xfId="0" fontId="1" applyFont="1"/>'
			. '<xf xfId="0" fontId="2" applyFont="1" applyAlignment="1">'
			. '<alignment horizontal="center"/></xf>'
			. '<xf xfId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1"'
			. ' applyBorder="1" applyAlignment="1">'
			. '<alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
			. '<xf xfId="0" borderId="1" applyBorder="1"/>'
			. '<xf xfId="0" numFmtId="164" borderId="1" applyNumberFormat="1" applyBorder="1"/>'
			. '<xf xfId="0" numFmtId="165" borderId="1" applyNumberFormat="1" applyBorder="1"/>'
			. '<xf xfId="0" fontId="1" fillId="3" borderId="1" applyFont="1" applyFill="1"'
			. ' applyBorder="1"/>'
			. '<xf xfId="0" fontId="1" numFmtId="164" fillId="3" borderId="1" applyFont="1"'
			. ' applyNumberFormat="1" applyFill="1" applyBorder="1"/>'
			. '<xf xfId="0" borderId="1" applyBorder="1" applyAlignment="1">'
			. '<alignment horizontal="center"/></xf>'
			/* 10 — HAI HÀNG TRONG MỘT Ô. `wrapText` là thứ bắt Excel xuống hàng ở ký tự `\n`;
			   thiếu nó thì ô vẫn CHỨA hai hàng nhưng hiện ra một hàng dính liền, và người ta
			   tưởng tệp hỏng. Dùng cho ô giờ vào/ra của tệp bảng công tháng. */
			. '<xf xfId="0" borderId="1" applyBorder="1" applyAlignment="1">'
			. '<alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
			/* 11 — Ô MỘT HÀNG ĐỨNG CẠNH Ô HAI HÀNG. Hàng nào có ô hai dòng thì cao gấp đôi, và
			   mấy ô một dòng bên cạnh tụt hẳn xuống đáy — nhìn như lệch hàng. Canh giữa theo
			   chiều dọc là hết. */
			. '<xf xfId="0" borderId="1" applyBorder="1" applyAlignment="1">'
			. '<alignment vertical="center"/></xf>'
			. '</cellXfs>'
			/* ⚠️ `<cellStyles>` nhìn thì thừa, nhưng thiếu nó thì một số trình đọc (openpyxl, vài bản
			   LibreOffice) kêu "không có kiểu mặc định" rồi tự đắp kiểu của chúng vào — định dạng
			   tiền và giờ mình đặt có thể bị bỏ. Một dòng, để khỏi phải đoán máy người ta dùng gì. */
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '</styleSheet>';
	}

	/** Kiểu ô HAI HÀNG (xuống dòng trong ô). Xem chú thích ở `styles()` mục 10. */
	const HAI_HANG = 10;

	/** Ô một hàng đứng cạnh ô hai hàng — canh giữa theo chiều dọc. Xem `styles()` mục 11. */
	const GIUA_DOC = 11;

	/** Một ô mang kiểu riêng: `o_kieu( 441600, VHCC_Xuat::TIEN )`. */
	public static function o_kieu( $v, $s ) { return array( 'v' => $v, 's' => (int) $s ); }
	/** Một ô là CÔNG THỨC: `ct( 'M10+U10-Y10', VHCC_Xuat::TIEN )`. */
	/**
	 * Ô CÔNG THỨC. `$v` là GIÁ TRỊ ĐÃ TÍNH SẴN — có thì ghi kèm, không thì thôi.
	 *
	 * 🔴 PHẢI GHI KÈM GIÁ TRỊ. Chú thích cũ ở `o()` bảo "đừng kèm `<v>` đoán sẵn" và em giữ nó
	 *    qua mấy bản. SAI — nó đúng với một giá trị ĐOÁN, còn đây là con số chính lõi lương vừa
	 *    tính ra, cùng một phép với công thức. Không kèm thì tệp CHỈ có số khi mở bằng một ứng
	 *    dụng chịu tính lại; xem nhanh trên điện thoại, Google Sheets, WPS hay ô xem trước của
	 *    hòm thư thì cả cột Lương chính và TOTAL SALARY trống trơn.
	 *    Anh Thắng 16/09/2026: *"Sao bảo làm y chang lại xuất bảng không có gì"* — kèm chính
	 *    tệp hệ xuất ra. Soi trong file mẫu của kế toán: 560 ô công thức đều có `<v>` đi kèm.
	 * ⚠️ Vẫn giữ `fullCalcOnLoad` ở `workbook()`: kế toán gõ vào ô phụ cấp là Excel tính lại,
	 *    nên con số ghi sẵn không bao giờ kịp cũ.
	 */
	public static function ct( $f, $s = 0, $v = null ) {
		return array( 'ct' => (string) $f, 's' => (int) $s, 'v' => $v );
	}

	const DAM   = 1;
	const TUA   = 2;
	const BANG  = 3;
	const CHU_V = 4;
	const TIEN  = 5;
	const GIO   = 6;
	const KHOI  = 7;
	const TONG  = 8;
	const STT   = 9;

	/**
	 * Một trang tính.
	 *
	 * @param array $t  ten · hang[][] · cot[] (độ rộng) · gop[] ('A4:AA4')
	 *
	 * ⚠️ `<cols>` phải đứng TRƯỚC `<sheetData>` và `<mergeCells>` phải đứng SAU — lược đồ của
	 *    Excel bắt đúng thứ tự ấy. Sai thứ tự thì Excel báo "found unreadable content" và KHÔNG mở
	 *    tệp, chứ không bỏ qua phần nó không hiểu.
	 */
	private static function sheet( $t ) {
		$hang = isset( $t['hang'] ) ? (array) $t['hang'] : array();
		$x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		if ( ! empty( $t['cot'] ) ) {
			$x .= '<cols>';
			foreach ( array_values( (array) $t['cot'] ) as $i => $w ) {
				$w = (float) $w;
				if ( $w <= 0 ) { continue; }
				$x .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="'
					. round( $w, 2 ) . '" customWidth="1"/>';
			}
			$x .= '</cols>';
		}
		$x .= '<sheetData>';
		/* `dam_dong_dau` mặc định BẬT để giữ nguyên hành vi cũ của mọi nơi đang xuất; bảng
		   nào tự đặt kiểu cho từng ô thì tắt đi. */
		$dam_dau = ! isset( $t['damDongDau'] ) || $t['damDongDau'];
		foreach ( array_values( $hang ) as $r => $dong ) {
			$x .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( (array) $dong ) as $c => $o ) {
				$x .= self::o( self::cot( $c ) . ( $r + 1 ), $o, ( $dam_dau && 0 === $r ) );
			}
			$x .= '</row>';
		}
		$x .= '</sheetData>';
		if ( ! empty( $t['gop'] ) ) {
			$g = array_values( (array) $t['gop'] );
			$x .= '<mergeCells count="' . count( $g ) . '">';
			foreach ( $g as $v ) { $x .= '<mergeCell ref="' . self::x( $v ) . '"/>'; }
			$x .= '</mergeCells>';
		}
		return $x . '</worksheet>';
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
		if ( is_array( $gia ) && isset( $gia['s'] ) ) { $s = ' s="' . (int) $gia['s'] . '"'; }
		/* Công thức: ghi `<f>`, và KÈM `<v>` khi nơi gọi đưa xuống một giá trị đã tính. Xem khối
		   chú thích ở `ct()` về việc vì sao phải kèm — và vì sao "đừng kèm" của bản cũ là sai. */
		if ( is_array( $gia ) && isset( $gia['ct'] ) ) {
			$gt = ( array_key_exists( 'v', $gia ) && null !== $gia['v'] && '' !== $gia['v'] )
				? '<v>' . ( 0 + $gia['v'] ) . '</v>' : '';
			return '<c r="' . $vt . '"' . $s . '><f>' . self::x( (string) $gia['ct'] ) . '</f>'
				. $gt . '</c>';
		}
		if ( is_array( $gia ) && array_key_exists( 'v', $gia ) ) { $gia = $gia['v']; }
		if ( is_array( $gia ) && isset( $gia['chu'] ) ) { $gia = (string) $gia['chu']; }
		if ( null === $gia ) { return '<c r="' . $vt . '"' . $s . '/>'; }
		if ( is_int( $gia ) || is_float( $gia ) ) {
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

	/**
	 * Kiểu MIME sẽ gửi đi. Hàm THUẦN, tách riêng vì `header()` không soi được từ bộ thử: gọi
	 * nó trong CLI thì chẳng ghi lại đâu cả, nên gửi ảnh dưới tên MIME của Excel là một cái sai
	 * không phép thử nào chạm tới được nếu để nguyên trong thân `gui()`.
	 *
	 * @param string $kieu  kiểu MIME. Mặc định là .xlsx — nhưng cùng lối gửi này nay còn chở
	 *                      tấm ảnh .svg của bảng công, và gửi ảnh dưới tên MIME của Excel thì
	 *                      trình duyệt tải về xong không mở được bằng gì.
	 */
	public static function mime( $kieu = '' ) {
		return ( '' !== trim( (string) $kieu ) ) ? trim( (string) $kieu )
			: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
	}

	/**
	 * MỌI DÒNG ĐẦU TỆP ĐI QUA ĐÂY — đây là RANH GIỚI QUAN SÁT của lượt gửi.
	 *
	 * `header()` là hàm của PHP: gọi trong CLI thì không ghi lại đâu cả, nên mọi cái sai nằm ở
	 * ĐỐI SỐ truyền cho nó đều là cái sai không phép thử nào chạm tới. Ghi lại đúng chuỗi sắp
	 * truyền đi, ngay tại một chỗ duy nhất, là cách kéo ranh giới ấy sát vào `header()` nhất
	 * có thể — dưới dòng này thì chỉ còn PHP, không còn mã của mình để mà sai.
	 */
	private static function dau( $dong ) {
		self::$dau_da_gui[] = (string) $dong;
		header( (string) $dong );
	}

	public static function gui( $ten_tep, $noi_dung, $kieu = '' ) {
		nocache_headers();
		self::$dau_da_gui  = array();
		self::$mime_da_gui = self::mime( $kieu );
		self::dau( 'Content-Type: ' . self::$mime_da_gui );
		self::dau( 'Content-Disposition: attachment; filename="' . $ten_tep . '"' );
		self::dau( 'Content-Length: ' . strlen( $noi_dung ) );
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
