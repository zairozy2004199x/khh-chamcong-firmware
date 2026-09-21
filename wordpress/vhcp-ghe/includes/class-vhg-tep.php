<?php
/**
 * ĐỌC TỆP SAO KÊ TẢI LÊN — .xlsx và .csv, không cần thư viện ngoài.
 *
 * =============================================================================================
 * VÌ SAO PHẢI NHẬN TỆP CHỨ KHÔNG CHỈ DÁN
 * =============================================================================================
 * Anh Thắng 11/09/2026, sau khi nhìn bảng cổng có hàng loạt dòng "chưa rõ máy" mà trên VietQR
 * thì tra ra cửa hàng: *"sẽ tải sao kê trên vietQR hệ thống tự đối chiếu mã giao dịch cổng để
 * xác định giao dịch đó của cơ sở nào, upload lên bằng file excel"*.
 *
 * Đường dán sẵn có chạy tốt cho vài chục dòng. Sao kê thật là 2.000 dòng: dán vào ô textarea
 * thì trình duyệt treo, mà một ô nội dung có xuống dòng là cả bảng lệch từ đó trở đi — lệch
 * lặng lẽ, và lệch ở đây nghĩa là tiền của cơ sở này rơi sang cơ sở khác.
 *
 * =============================================================================================
 * 🔴 VÌ SAO TỰ ĐỌC .xlsx CHỨ KHÔNG KÉO PhpSpreadsheet VỀ
 * =============================================================================================
 * Thứ cần đọc ở đây là một bảng phẳng: hàng, cột, chữ. PhpSpreadsheet nặng ~40MB gói phụ thuộc
 * và ngốn bộ nhớ theo cấp số với số ô — chính là thứ hosting chia sẻ hay chết vì hết bộ nhớ,
 * đúng lúc nhập 2.000 dòng. Còn .xlsx chỉ là một tệp .zip chứa XML: `ZipArchive` + `SimpleXML`
 * là đủ, cả hai đều có sẵn trong PHP.
 *
 * ⚠️ BA CHỖ .xlsx HAY LÀM NGƯỜI VIẾT VẤP, cả ba đều dẫn tới LỆCH CỘT:
 *   1. Ô trống KHÔNG được ghi ra XML. Đọc tuần tự rồi `$hang[] = $v` là mọi ô sau ô trống dồn
 *      lên một cột. Phải đọc địa chỉ ô ("C7") để biết nó là cột thứ mấy.
 *   2. Chuỗi nằm ở tệp KHÁC (`sharedStrings.xml`), ô chỉ giữ chỉ số — `t="s"`. Không tra bảng
 *      ấy thì cả cột chữ ra một dãy số 0,1,2…
 *   3. Ngày tháng là SỐ (số ngày kể từ 1899-12-30). Cột "Thời điểm" sẽ ra "45911" nếu không
 *      đổi lại. Ở đây giữ nguyên chuỗi thô và để `VHG_Thu::ghi()` tự hiểu — nhưng phải nhận ra
 *      để còn đổi, nên có `ngay_excel()`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Tep {

	/** Kích thước tối đa nhận vào (byte). Sao kê 2.000 dòng nặng ~300KB, nên 8MB là rộng rãi. */
	const TOI_DA = 8388608;

	/**
	 * Đọc một tệp đã tải lên thành bảng hai chiều (dòng đầu là tiêu đề).
	 *
	 * @param array $tep Một phần tử của `$_FILES`.
	 * @return array array( 'ok' => bool, 'bang' => array, 'error' => string, 'kieu' => 'xlsx|csv' )
	 */
	public static function doc( $tep ) {
		if ( ! is_array( $tep ) || ! isset( $tep['tmp_name'] ) || '' === $tep['tmp_name'] ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn tệp nào.' );
		}
		/* 🔴 `is_uploaded_file` LÀ CHỐT BẮT BUỘC, KHÔNG PHẢI THỦ TỤC. Thiếu nó thì một lượt gửi
		   khéo tay có thể trỏ `tmp_name` vào bất cứ tệp nào trên máy chủ — kể cả wp-config.php —
		   và màn xem trước sẽ in nội dung tệp ấy ra màn hình. */
		if ( ! is_uploaded_file( $tep['tmp_name'] ) ) {
			return array( 'ok' => false, 'error' => 'Tệp không hợp lệ (không phải tệp vừa tải lên).' );
		}
		if ( ! empty( $tep['error'] ) ) {
			return array( 'ok' => false, 'error' => self::loi_tai( (int) $tep['error'] ) );
		}
		if ( (int) $tep['size'] > self::TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Tệp quá lớn (' . size_format( (int) $tep['size'] )
				. '). Tối đa ' . size_format( self::TOI_DA ) . ' — tách sao kê theo tháng rồi tải từng tệp.' );
		}
		$ten  = strtolower( (string) ( isset( $tep['name'] ) ? $tep['name'] : '' ) );
		$duoi = ( false !== strrpos( $ten, '.' ) ) ? substr( $ten, strrpos( $ten, '.' ) + 1 ) : '';

		/* ⚠️ NHẬN DIỆN BẰNG NỘI DUNG, KHÔNG CHỈ BẰNG ĐUÔI. Người ta xuất .csv rồi đổi tên thành
		   .xlsx là chuyện thường; tin cái đuôi thì `ZipArchive` mở hỏng và câu lỗi nói về "tệp
		   nén" trong khi vấn đề chẳng liên quan gì. Bốn byte đầu của .xlsx luôn là "PK\x03\x04". */
		$dau = (string) @file_get_contents( $tep['tmp_name'], false, null, 0, 4 );
		$la_zip = ( 0 === strpos( $dau, "PK\x03\x04" ) );

		if ( $la_zip ) { return self::doc_xlsx( $tep['tmp_name'] ); }
		if ( in_array( $duoi, array( 'csv', 'txt', 'tsv' ), true ) || '' === $duoi ) {
			return self::doc_csv( $tep['tmp_name'] );
		}
		if ( 'xls' === $duoi ) {
			return array( 'ok' => false, 'error' => 'Tệp .xls (bản Excel cũ) chưa đọc được. '
				. 'Mở bằng Excel rồi "Lưu thành" .xlsx hoặc .csv, sau đó tải lại.' );
		}
		return self::doc_csv( $tep['tmp_name'] );
	}

	private static function loi_tai( $ma ) {
		$m = array(
			UPLOAD_ERR_INI_SIZE   => 'Tệp lớn hơn mức hosting cho phép (upload_max_filesize).',
			UPLOAD_ERR_FORM_SIZE  => 'Tệp lớn hơn mức biểu mẫu cho phép.',
			UPLOAD_ERR_PARTIAL    => 'Tệp tải lên dở dang — thử lại.',
			UPLOAD_ERR_NO_FILE    => 'Chưa chọn tệp nào.',
			UPLOAD_ERR_NO_TMP_DIR => 'Máy chủ thiếu thư mục tạm.',
			UPLOAD_ERR_CANT_WRITE => 'Máy chủ không ghi được tệp tạm.',
			UPLOAD_ERR_EXTENSION  => 'Một phần mở rộng PHP chặn lượt tải lên này.',
		);
		return isset( $m[ $ma ] ) ? $m[ $ma ] : 'Tải tệp lên không thành công (mã ' . $ma . ').';
	}

	// ======================================================================= CSV

	/**
	 * Đọc .csv / .tsv.
	 *
	 * ⚠️ BA CÁI BẪY, cả ba đều làm lệch cột hoặc mất dòng tiêu đề:
	 *   · BOM UTF-8 ở đầu tệp dính vào tên cột đầu tiên -> `cot()` không nhận ra "Mã tham chiếu";
	 *   · Excel bản tiếng Việt xuất .csv ngăn bằng DẤU CHẤM PHẨY, không phải dấu phẩy;
	 *   · một ô nội dung có xuống dòng -> phải để `fgetcsv` lo, đừng tự `explode("\n")`.
	 */
	private static function doc_csv( $duong ) {
		$fh = @fopen( $duong, 'r' );
		if ( ! $fh ) { return array( 'ok' => false, 'error' => 'Không mở được tệp.' ); }

		$dau = fgets( $fh, 4096 );
		if ( false === $dau ) { fclose( $fh ); return array( 'ok' => false, 'error' => 'Tệp rỗng.' ); }
		/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: bỏ dòng này KHÔNG đổi kết
		   quả hôm nay — BOM không chứa dấu phẩy, chấm phẩy hay TAB nên phép đếm dưới ra y hệt,
		   mà ô đầu thì đã được rửa BOM lần nữa ở vòng đọc. Giữ vì `$dau` còn được đọc lại nếu
		   sau này thêm phép dò khác (dò dòng tiêu đề nằm ở hàng mấy chẳng hạn), và lúc ấy BOM
		   sẽ dính vào tên cột đầu — hỏng lặng lẽ, không câu lỗi nào. */
		$dau = preg_replace( '/^\xEF\xBB\xBF/', '', $dau );
		/* Dấu ngăn = ký tự xuất hiện nhiều nhất ở DÒNG ĐẦU. Dòng đầu là tiêu đề nên sạch nhất:
		   đếm trên dòng dữ liệu thì một ô "Nguyễn Văn A, Q1" đủ để chọn nhầm. */
		$ung = array( "\t" => substr_count( $dau, "\t" ), ';' => substr_count( $dau, ';' ),
			',' => substr_count( $dau, ',' ) );
		arsort( $ung );
		$ngan = key( $ung );
		if ( 0 === $ung[ $ngan ] ) { $ngan = ','; }

		rewind( $fh );
		$bang = array(); $dong_dau = true;
		/* ⚠️ TRUYỀN ĐỦ CẢ BỐN THAM SỐ. PHP 8.4 cảnh báo khi thiếu `$escape`, và bản sau sẽ đổi
		   giá trị mặc định của nó — để trống là một ngày nào đó ô có dấu \ đọc ra khác đi. */
		while ( false !== ( $o = fgetcsv( $fh, 0, $ngan, '"', '\\' ) ) ) {
			if ( null === $o || ( 1 === count( $o ) && null === $o[0] ) ) { continue; }   // dòng trống
			if ( $dong_dau ) {
				$dong_dau = false;
				if ( isset( $o[0] ) ) { $o[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $o[0] ); }
			}
			$bang[] = array_map( function ( $x ) { return trim( (string) $x ); }, $o );
		}
		fclose( $fh );
		return array( 'ok' => true, 'bang' => $bang, 'kieu' => 'csv' );
	}

	// ======================================================================= XLSX

	/** Đổi địa chỉ ô kiểu "AB7" thành chỉ số cột 0-based (AB -> 27). */
	public static function cot_tu_o( $dia_chi ) {
		$c = 0;
		$s = strtoupper( (string) $dia_chi );
		for ( $i = 0, $n = strlen( $s ); $i < $n; $i++ ) {
			$k = $s[ $i ];
			if ( $k < 'A' || $k > 'Z' ) { break; }
			$c = $c * 26 + ( ord( $k ) - 64 );
		}
		return $c > 0 ? $c - 1 : 0;
	}

	/**
	 * Số ngày của Excel -> 'Y-m-d H:i:s'. Trả '' nếu không phải số ngày hợp lý.
	 *
	 * ⚠️ MỐC LÀ 1899-12-30, KHÔNG PHẢI 1900-01-01. Excel cố tình giữ lỗi "năm 1900 nhuận" của
	 *    Lotus 1-2-3, nên đếm từ 1900-01-01 là LỆCH ĐÚNG HAI NGÀY cho mọi ngày sau 28/02/1900.
	 *    Hai ngày lệch trong sổ tiền là đủ để một buổi đối soát không bao giờ khớp.
	 */
	public static function ngay_excel( $v ) {
		if ( ! is_numeric( $v ) ) { return ''; }
		$n = (float) $v;
		/* Khoảng hợp lý: 2010-01-01 (40179) .. 2100-01-01 (73051). Ngoài khoảng thì đây là một
		   con số bình thường (số tiền, mã số), đừng biến nó thành ngày. */
		if ( $n < 40179 || $n > 73051 ) { return ''; }
		$giay = (int) round( ( $n - 25569 ) * 86400 );
		return gmdate( 'Y-m-d H:i:s', $giay );
	}

	/**
	 * Đọc .xlsx: lấy trang tính ĐẦU TIÊN.
	 *
	 * ⚠️ "Trang đầu" theo thứ tự khai trong workbook.xml, không phải theo tên tệp sheetN.xml —
	 *    hai thứ ấy không nhất thiết trùng nhau, và lấy nhầm trang là nhập một bảng khác hẳn.
	 */
	private static function doc_xlsx( $duong ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'ok' => false, 'error' => 'Máy chủ thiếu phần mở rộng ZipArchive nên chưa '
				. 'đọc được .xlsx. Mở tệp bằng Excel rồi "Lưu thành" .csv, sau đó tải lại.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $duong ) ) {
			return array( 'ok' => false, 'error' => 'Không mở được tệp .xlsx (tệp hỏng hoặc có mật khẩu).' );
		}

		/* --- bảng chuỗi dùng chung --- */
		$chuoi = array();
		$ss = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false !== $ss && '' !== $ss ) {
			$x = @simplexml_load_string( $ss );
			if ( $x ) {
				foreach ( $x->si as $si ) {
					/* Một ô có thể vỡ thành nhiều đoạn `<r><t>` khi trong ô có chữ in đậm giữa
					   chừng — nối lại, lấy mỗi `<t>` đầu là mất phần còn lại của tên cửa hàng. */
					$t = '';
					if ( isset( $si->t ) ) { $t = (string) $si->t; }
					if ( isset( $si->r ) ) { foreach ( $si->r as $r ) { $t .= (string) $r->t; } }
					$chuoi[] = $t;
				}
			}
		}

		/* --- trang tính đầu tiên --- */
		$duong_sheet = 'xl/worksheets/sheet1.xml';
		$wb = $zip->getFromName( 'xl/workbook.xml' );
		$rel = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
		if ( false !== $wb && false !== $rel ) {
			$xw = @simplexml_load_string( $wb );
			$xr = @simplexml_load_string( $rel );
			if ( $xw && $xr && isset( $xw->sheets->sheet[0] ) ) {
				$rid = '';
				foreach ( $xw->sheets->sheet[0]->attributes( 'r', true ) as $k => $v ) {
					if ( 'id' === (string) $k ) { $rid = (string) $v; }
				}
				foreach ( $xr->Relationship as $r ) {
					if ( (string) $r['Id'] === $rid && '' !== $rid ) {
						$t = ltrim( (string) $r['Target'], '/' );
						$duong_sheet = ( 0 === strpos( $t, 'xl/' ) ) ? $t : ( 'xl/' . $t );
					}
				}
			}
		}
		$sheet = $zip->getFromName( $duong_sheet );
		if ( false === $sheet ) { $sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' ); }
		$zip->close();
		if ( false === $sheet || '' === $sheet ) {
			return array( 'ok' => false, 'error' => 'Không thấy trang tính nào trong tệp .xlsx.' );
		}

		$xs = @simplexml_load_string( $sheet );
		if ( ! $xs || ! isset( $xs->sheetData ) ) {
			return array( 'ok' => false, 'error' => 'Trang tính trong tệp .xlsx đọc không ra.' );
		}

		$bang = array();
		foreach ( $xs->sheetData->row as $row ) {
			$hang = array();
			foreach ( $row->c as $c ) {
				/* 🔴 ĐẶT THEO ĐỊA CHỈ Ô. Ô trống không có trong XML; dồn tuần tự là lệch cột.
				   Xem khối ⚠️ ở đầu tệp, mục 1. */
				$i = isset( $c['r'] ) ? self::cot_tu_o( (string) $c['r'] ) : count( $hang );
				$kieu = isset( $c['t'] ) ? (string) $c['t'] : '';
				$v = '';
				if ( 's' === $kieu ) {
					$k = (int) $c->v;
					$v = isset( $chuoi[ $k ] ) ? $chuoi[ $k ] : '';
				} elseif ( 'inlineStr' === $kieu ) {
					$v = isset( $c->is->t ) ? (string) $c->is->t : '';
					if ( isset( $c->is->r ) ) { $v = ''; foreach ( $c->is->r as $r ) { $v .= (string) $r->t; } }
				} else {
					$v = isset( $c->v ) ? (string) $c->v : '';
				}
				$hang[ $i ] = trim( $v );
			}
			if ( ! $hang ) { continue; }
			/* Lấp ô trống ở giữa để mọi hàng cùng khuôn — chỗ gọi đọc theo chỉ số cột. */
			$max = max( array_keys( $hang ) );
			$day = array();
			for ( $i = 0; $i <= $max; $i++ ) { $day[] = isset( $hang[ $i ] ) ? $hang[ $i ] : ''; }
			$bang[] = $day;
		}
		/* Bỏ những hàng trống trơn ở cuối — Excel hay để lại vài trăm hàng như thế, và chúng
		   làm con số "đã đọc N dòng" nói sai gấp mấy lần. */
		while ( $bang ) {
			$cuoi = end( $bang );
			if ( '' !== trim( implode( '', $cuoi ) ) ) { break; }
			array_pop( $bang );
		}
		return array( 'ok' => true, 'bang' => $bang, 'kieu' => 'xlsx' );
	}
}
