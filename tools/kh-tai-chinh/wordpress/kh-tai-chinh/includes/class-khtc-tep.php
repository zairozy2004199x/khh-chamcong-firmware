<?php
/**
 * Đọc tệp bảng người dùng nạp lên (.xlsx / .csv / .tsv / .txt) thành văn bản
 * tab-phân-cách — đúng cái dạng mà mọi ô "dán bảng" trong plugin vẫn nhận.
 *
 * Vì sao quy về văn bản chứ không đọc thẳng vào sổ: mọi hàm dán hàng loạt
 * (dan_hang_loat, nap_dong, KHTC_DanTho::doc) đã được kiểm kỹ trên văn bản
 * dán. Nạp file chỉ là một cách khác để có đúng văn bản đó — không mở thêm
 * một đường vào sổ thứ hai để rồi phải kiểm lại từ đầu.
 *
 * .xlsx được đọc bằng ZipArchive + SimpleXML có sẵn trong PHP, không cần thư
 * viện ngoài — host chỉ có wp-admin thì không cài được gì thêm. Ô ngày trong
 * Excel là số (ngày thứ n kể từ 1900) — ở đây đổi về dd/mm/yyyy như người ta
 * nhìn thấy trên màn hình, để cột ngày đọc ra y như khi copy-dán.
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Tep {

	/** Giới hạn cỡ tệp nhận (byte). Sao kê cả tháng của một cổng chưa tới 5MB. */
	const TOI_DA = 20 * 1024 * 1024;

	/** Đuôi tệp nhận. */
	const DUOI = array( 'xlsx', 'xlsm', 'xls', 'csv', 'tsv', 'txt' );

	/**
	 * Đưa chữ Việt về dạng dựng sẵn (NFC). File MoMo Business ghi "gốc" là
	 * "ô" + dấu sắc rời (NFD): nhìn y hệt, so chuỗi thì khác, và máy "không
	 * nhận ra" một tiêu đề đang hiện rõ trước mắt. Có intl thì dùng Normalizer;
	 * không có (host rẻ) thì tra bảng ghép dấu tiếng Việt.
	 */
	public static function chuan_unicode( $s ) {
		$s = (string) $s;
		if ( ! preg_match( '/[\x{0300}-\x{036F}]/u', $s ) ) { return $s; }   // không có dấu rời → khỏi làm gì
		if ( class_exists( 'Normalizer' ) ) {
			$r = Normalizer::normalize( $s, Normalizer::FORM_C );
			if ( false !== $r ) { return $r; }
		}
		static $bang = null;
		if ( null === $bang ) {
			$bang = array(
			"\u{0061}\u{0300}" => "à",
			"\u{0061}\u{0301}" => "á",
			"\u{0061}\u{0303}" => "ã",
			"\u{0061}\u{0309}" => "ả",
			"\u{0061}\u{0323}" => "ạ",
			"\u{0103}\u{0300}" => "ằ",
			"\u{0103}\u{0301}" => "ắ",
			"\u{0103}\u{0303}" => "ẵ",
			"\u{0103}\u{0309}" => "ẳ",
			"\u{0103}\u{0323}" => "ặ",
			"\u{00E2}\u{0300}" => "ầ",
			"\u{00E2}\u{0301}" => "ấ",
			"\u{00E2}\u{0303}" => "ẫ",
			"\u{00E2}\u{0309}" => "ẩ",
			"\u{00E2}\u{0323}" => "ậ",
			"\u{0065}\u{0300}" => "è",
			"\u{0065}\u{0301}" => "é",
			"\u{0065}\u{0303}" => "ẽ",
			"\u{0065}\u{0309}" => "ẻ",
			"\u{0065}\u{0323}" => "ẹ",
			"\u{00EA}\u{0300}" => "ề",
			"\u{00EA}\u{0301}" => "ế",
			"\u{00EA}\u{0303}" => "ễ",
			"\u{00EA}\u{0309}" => "ể",
			"\u{00EA}\u{0323}" => "ệ",
			"\u{0069}\u{0300}" => "ì",
			"\u{0069}\u{0301}" => "í",
			"\u{0069}\u{0303}" => "ĩ",
			"\u{0069}\u{0309}" => "ỉ",
			"\u{0069}\u{0323}" => "ị",
			"\u{006F}\u{0300}" => "ò",
			"\u{006F}\u{0301}" => "ó",
			"\u{006F}\u{0303}" => "õ",
			"\u{006F}\u{0309}" => "ỏ",
			"\u{006F}\u{0323}" => "ọ",
			"\u{00F4}\u{0300}" => "ồ",
			"\u{00F4}\u{0301}" => "ố",
			"\u{00F4}\u{0303}" => "ỗ",
			"\u{00F4}\u{0309}" => "ổ",
			"\u{00F4}\u{0323}" => "ộ",
			"\u{01A1}\u{0300}" => "ờ",
			"\u{01A1}\u{0301}" => "ớ",
			"\u{01A1}\u{0303}" => "ỡ",
			"\u{01A1}\u{0309}" => "ở",
			"\u{01A1}\u{0323}" => "ợ",
			"\u{0075}\u{0300}" => "ù",
			"\u{0075}\u{0301}" => "ú",
			"\u{0075}\u{0303}" => "ũ",
			"\u{0075}\u{0309}" => "ủ",
			"\u{0075}\u{0323}" => "ụ",
			"\u{01B0}\u{0300}" => "ừ",
			"\u{01B0}\u{0301}" => "ứ",
			"\u{01B0}\u{0303}" => "ữ",
			"\u{01B0}\u{0309}" => "ử",
			"\u{01B0}\u{0323}" => "ự",
			"\u{0079}\u{0300}" => "ỳ",
			"\u{0079}\u{0301}" => "ý",
			"\u{0079}\u{0303}" => "ỹ",
			"\u{0079}\u{0309}" => "ỷ",
			"\u{0079}\u{0323}" => "ỵ",
			"\u{0041}\u{0300}" => "À",
			"\u{0041}\u{0301}" => "Á",
			"\u{0041}\u{0303}" => "Ã",
			"\u{0041}\u{0309}" => "Ả",
			"\u{0041}\u{0323}" => "Ạ",
			"\u{0102}\u{0300}" => "Ằ",
			"\u{0102}\u{0301}" => "Ắ",
			"\u{0102}\u{0303}" => "Ẵ",
			"\u{0102}\u{0309}" => "Ẳ",
			"\u{0102}\u{0323}" => "Ặ",
			"\u{00C2}\u{0300}" => "Ầ",
			"\u{00C2}\u{0301}" => "Ấ",
			"\u{00C2}\u{0303}" => "Ẫ",
			"\u{00C2}\u{0309}" => "Ẩ",
			"\u{00C2}\u{0323}" => "Ậ",
			"\u{0045}\u{0300}" => "È",
			"\u{0045}\u{0301}" => "É",
			"\u{0045}\u{0303}" => "Ẽ",
			"\u{0045}\u{0309}" => "Ẻ",
			"\u{0045}\u{0323}" => "Ẹ",
			"\u{00CA}\u{0300}" => "Ề",
			"\u{00CA}\u{0301}" => "Ế",
			"\u{00CA}\u{0303}" => "Ễ",
			"\u{00CA}\u{0309}" => "Ể",
			"\u{00CA}\u{0323}" => "Ệ",
			"\u{0049}\u{0300}" => "Ì",
			"\u{0049}\u{0301}" => "Í",
			"\u{0049}\u{0303}" => "Ĩ",
			"\u{0049}\u{0309}" => "Ỉ",
			"\u{0049}\u{0323}" => "Ị",
			"\u{004F}\u{0300}" => "Ò",
			"\u{004F}\u{0301}" => "Ó",
			"\u{004F}\u{0303}" => "Õ",
			"\u{004F}\u{0309}" => "Ỏ",
			"\u{004F}\u{0323}" => "Ọ",
			"\u{00D4}\u{0300}" => "Ồ",
			"\u{00D4}\u{0301}" => "Ố",
			"\u{00D4}\u{0303}" => "Ỗ",
			"\u{00D4}\u{0309}" => "Ổ",
			"\u{00D4}\u{0323}" => "Ộ",
			"\u{01A0}\u{0300}" => "Ờ",
			"\u{01A0}\u{0301}" => "Ớ",
			"\u{01A0}\u{0303}" => "Ỡ",
			"\u{01A0}\u{0309}" => "Ở",
			"\u{01A0}\u{0323}" => "Ợ",
			"\u{0055}\u{0300}" => "Ù",
			"\u{0055}\u{0301}" => "Ú",
			"\u{0055}\u{0303}" => "Ũ",
			"\u{0055}\u{0309}" => "Ủ",
			"\u{0055}\u{0323}" => "Ụ",
			"\u{01AF}\u{0300}" => "Ừ",
			"\u{01AF}\u{0301}" => "Ứ",
			"\u{01AF}\u{0303}" => "Ữ",
			"\u{01AF}\u{0309}" => "Ử",
			"\u{01AF}\u{0323}" => "Ự",
			"\u{0059}\u{0300}" => "Ỳ",
			"\u{0059}\u{0301}" => "Ý",
			"\u{0059}\u{0303}" => "Ỹ",
			"\u{0059}\u{0309}" => "Ỷ",
			"\u{0059}\u{0323}" => "Ỵ",
			"\u{0061}\u{0306}" => "ă",
			"\u{0061}\u{0302}" => "â",
			"\u{0065}\u{0302}" => "ê",
			"\u{006F}\u{0302}" => "ô",
			"\u{006F}\u{031B}" => "ơ",
			"\u{0075}\u{031B}" => "ư",
			"\u{0041}\u{0306}" => "Ă",
			"\u{0041}\u{0302}" => "Â",
			"\u{0045}\u{0302}" => "Ê",
			"\u{004F}\u{0302}" => "Ô",
			"\u{004F}\u{031B}" => "Ơ",
			"\u{0055}\u{031B}" => "Ư"
			);
			// Nguyên âm có mũ/móc cũng có thể bị tách hai lần (a + ̂ + ́): ghép mũ/móc trước, dấu thanh sau.
			uksort( $bang, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		}
		$truoc = '';
		while ( $truoc !== $s ) { $truoc = $s; $s = strtr( $s, $bang ); }
		return $s;
	}

	/** Ghi chú của lần đọc tệp vừa rồi, theo tên ô — o_nap() in lại ngay trên ô. */
	public static $ghi_chu = array();

	/** Form có gửi tệp ở ô này không (đã chọn tệp, kể cả khi lên lỗi). */
	public static function co_tep( $o_tep ) {
		return isset( $_FILES[ $o_tep ] ) && is_array( $_FILES[ $o_tep ] ) && '' !== (string) ( $_FILES[ $o_tep ]['name'] ?? '' );
	}

	/**
	 * Đọc tệp nạp lên thành MỌI sheet (mỗi sheet một văn bản), để chỗ gọi tự
	 * chọn — dùng cho Dán thô, nơi phải soi từng sheet xem sheet nào là bảng cổng.
	 *
	 * @return array{ten_tep:string, trang:array}|WP_Error  trang = [ ['ten','van_ban','so_dong'] ].
	 */
	public static function lay_moi_trang( $o_tep ) {
		if ( ! self::co_tep( $o_tep ) ) {
			return new WP_Error( 'tep', 'Chưa chọn tệp.' );
		}
		$f = $_FILES[ $o_tep ];
		if ( ! empty( $f['error'] ) ) {
			return new WP_Error( 'tep', self::loi_upload( (int) $f['error'] ) );
		}
		if ( empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'tep', 'Tệp không lên được máy chủ. Thử lại, hoặc dán bảng vào ô bên dưới.' );
		}
		return self::doc_moi_trang( $f['tmp_name'], (string) $f['name'] );
	}

	/** Như lay_moi_trang nhưng đọc từ đường dẫn — để kiểm và để dùng lại. */
	public static function doc_moi_trang( $duong, $ten ) {
		$ten_tep = sanitize_file_name( basename( $ten ) ) ?: 'tệp';
		$duoi    = strtolower( pathinfo( $ten, PATHINFO_EXTENSION ) );
		if ( 'xlsx' === $duoi || 'xlsm' === $duoi || 'xls' === $duoi ) {
			$kiem = self::kiem_tep( $duong, $ten );
			if ( is_wp_error( $kiem ) ) { return $kiem; }
			$ds = 'xls' === $duoi ? KHTC_Xls::doc( $duong ) : self::doc_xlsx( $duong );
			if ( is_wp_error( $ds ) ) { return $ds; }
			$trang = array();
			foreach ( $ds as $s ) {
				$trang[] = array( 'ten' => $s['ten'], 'van_ban' => self::ghep_dong( $s['dong'] ), 'so_dong' => count( $s['dong'] ) );
			}
			return array( 'ten_tep' => $ten_tep, 'trang' => $trang );
		}
		$mot = self::doc_bang( $duong, $ten );
		if ( is_wp_error( $mot ) ) { return $mot; }
		return array(
			'ten_tep' => $ten_tep,
			'trang'   => array( array( 'ten' => $ten_tep, 'van_ban' => $mot['van_ban'], 'so_dong' => $mot['trang'][0]['so_dong'] ) ),
		);
	}

	/** Kiểm đọc được / cỡ / đuôi. */
	private static function kiem_tep( $duong, $ten ) {
		if ( ! is_readable( $duong ) ) {
			return new WP_Error( 'tep', 'Không đọc được tệp.' );
		}
		$co = filesize( $duong );
		if ( $co > self::TOI_DA ) {
			return new WP_Error( 'tep', sprintf( 'Tệp %s lớn hơn mức nhận %s. Cắt bớt kỳ hoặc lưu riêng sheet cần nạp.', size_format( $co ), size_format( self::TOI_DA ) ) );
		}
		$duoi = strtolower( pathinfo( $ten, PATHINFO_EXTENSION ) );
		if ( ! in_array( $duoi, self::DUOI, true ) ) {
			return new WP_Error( 'tep', sprintf( 'Không nhận đuôi .%s. Nhận: .xlsx, .xls, .csv, .tsv, .txt.', $duoi ?: '?' ) );
		}
		return true;
	}

	/**
	 * Lấy văn bản bảng từ form: ưu tiên tệp nạp lên, không có thì lấy ô dán.
	 *
	 * @param string $o_dan  Tên ô textarea.
	 * @param string $o_tep  Tên ô file (mặc định 'tep_' . $o_dan).
	 * @param string $trang  Tên/số sheet muốn đọc ('' = sheet có dữ liệu đầu tiên).
	 * @return array{van_ban:string, nguon:string, ghi_chu:string, loi:string, trang:array}
	 *   nguon = 'tep' | 'dan' | ''.
	 */
	public static function lay( $o_dan, $o_tep = '', $trang = '' ) {
		$o_tep = $o_tep ?: 'tep_' . $o_dan;
		$kq    = array( 'van_ban' => '', 'nguon' => '', 'ghi_chu' => '', 'loi' => '', 'trang' => array() );

		if ( isset( $_FILES[ $o_tep ] ) && is_array( $_FILES[ $o_tep ] ) && '' !== (string) ( $_FILES[ $o_tep ]['name'] ?? '' ) ) {
			$f = $_FILES[ $o_tep ];
			if ( ! empty( $f['error'] ) ) {
				$kq['loi'] = self::loi_upload( (int) $f['error'] );
				return $kq;
			}
			if ( empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
				$kq['loi'] = 'Tệp không lên được máy chủ. Thử lại, hoặc dán bảng vào ô bên dưới.';
				return $kq;
			}
			$doc = self::doc_bang( $f['tmp_name'], (string) $f['name'], $trang );
			if ( is_wp_error( $doc ) ) {
				$kq['loi'] = $doc->get_error_message();
				return $kq;
			}
			$kq['van_ban'] = $doc['van_ban'];
			$kq['nguon']   = 'tep';
			$kq['trang']   = $doc['trang'];
			$kq['ghi_chu'] = trim( sprintf( 'Đã đọc tệp %s. %s', sanitize_file_name( basename( (string) $f['name'] ) ), $doc['ghi_chu'] ) );
			self::$ghi_chu[ $o_dan ] = $kq['ghi_chu'];
			return $kq;
		}

		if ( isset( $_POST[ $o_dan ] ) ) {
			$kq['van_ban'] = self::chuan_unicode( wp_unslash( $_POST[ $o_dan ] ) );
			$kq['nguon']   = '' !== trim( (string) $kq['van_ban'] ) ? 'dan' : '';
		}
		return $kq;
	}

	/** Câu báo lỗi PHP upload, nói bằng tiếng người. */
	public static function loi_upload( $ma ) {
		switch ( $ma ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return sprintf( 'Tệp quá lớn so với mức máy chủ cho phép (%s). Lưu bớt sheet, hoặc xuất .csv rồi nạp lại.', size_format( wp_max_upload_size() ) );
			case UPLOAD_ERR_PARTIAL:
				return 'Tệp lên chưa hết (mạng ngắt giữa chừng). Nạp lại.';
			case UPLOAD_ERR_NO_FILE:
				return 'Chưa chọn tệp.';
			default:
				return 'Máy chủ không nhận được tệp (mã ' . (int) $ma . '). Dán bảng vào ô bên dưới thay cho nạp tệp.';
		}
	}

	/**
	 * Đọc một tệp bảng thành văn bản tab-phân-cách.
	 *
	 * @param string $duong  Đường dẫn tệp trên máy chủ.
	 * @param string $ten    Tên gốc (để biết đuôi).
	 * @param string $trang  Sheet muốn lấy: tên, hoặc số thứ tự từ 1; '' = sheet có dữ liệu đầu tiên.
	 * @return array{van_ban:string, trang:array, ghi_chu:string}|WP_Error
	 *   trang = danh sách mọi sheet trong tệp: [ ['ten'=>..., 'so_dong'=>..., 'dung'=>bool] ].
	 */
	public static function doc_bang( $duong, $ten, $trang = '' ) {
		$kiem = self::kiem_tep( $duong, $ten );
		if ( is_wp_error( $kiem ) ) { return $kiem; }
		$duoi = strtolower( pathinfo( $ten, PATHINFO_EXTENSION ) );

		if ( 'xlsx' === $duoi || 'xlsm' === $duoi || 'xls' === $duoi ) {
			$ds = 'xls' === $duoi ? KHTC_Xls::doc( $duong ) : self::doc_xlsx( $duong );
			if ( is_wp_error( $ds ) ) { return $ds; }
			foreach ( $ds as &$s ) { foreach ( $s['dong'] as &$d ) { foreach ( $d as &$o ) { $o = self::chuan_unicode( $o ); } } }
			unset( $s, $d, $o );
			return self::chon_trang( $ds, $trang );
		}

		$vb = file_get_contents( $duong );
		$vb = self::chuan_unicode( self::ve_utf8( $vb ) );
		$vb = self::ve_tab( $vb );
		if ( '' === trim( $vb ) ) {
			return new WP_Error( 'tep', 'Tệp rỗng.' );
		}
		$so = count( preg_split( '/\r\n|\r|\n/', trim( $vb ) ) );
		return array(
			'van_ban' => $vb,
			'trang'   => array( array( 'ten' => basename( $ten ), 'so_dong' => $so, 'dung' => true ) ),
			'ghi_chu' => '',
		);
	}

	/**
	 * Chọn sheet trong danh sách đọc được.
	 *
	 * @param array  $ds    Kết quả doc_xlsx: [ ['ten'=>..., 'dong'=>array of array] ].
	 * @param string $trang Tên / số thứ tự / ''.
	 */
	public static function chon_trang( $ds, $trang = '' ) {
		$trang = trim( (string) $trang );
		$chon  = -1;
		if ( '' !== $trang ) {
			foreach ( $ds as $i => $s ) {
				if ( 0 === strcasecmp( trim( $s['ten'] ), $trang ) ) { $chon = $i; break; }
			}
			if ( -1 === $chon && ctype_digit( $trang ) && isset( $ds[ (int) $trang - 1 ] ) ) {
				$chon = (int) $trang - 1;
			}
			if ( -1 === $chon ) {
				$ten = array_map( function ( $s ) { return $s['ten']; }, $ds );
				return new WP_Error( 'trang', sprintf( 'Không có sheet "%s". Tệp có: %s.', $trang, implode( ' · ', $ten ) ) );
			}
		} else {
			foreach ( $ds as $i => $s ) {
				if ( count( $s['dong'] ) > 0 ) { $chon = $i; break; }
			}
			if ( -1 === $chon ) {
				return new WP_Error( 'tep', 'Tệp không có sheet nào có dữ liệu.' );
			}
		}

		$bang = array();
		foreach ( $ds as $i => $s ) {
			$bang[] = array( 'ten' => $s['ten'], 'so_dong' => count( $s['dong'] ), 'dung' => $i === $chon );
		}
		$ghi = '';
		if ( count( $ds ) > 1 ) {
			$khac = array();
			foreach ( $ds as $i => $s ) {
				if ( $i !== $chon && count( $s['dong'] ) > 0 ) { $khac[] = $s['ten']; }
			}
			$ghi = sprintf( 'Đọc sheet "%s"', $ds[ $chon ]['ten'] );
			if ( $khac ) { $ghi .= ' — tệp còn sheet khác có dữ liệu: ' . implode( ' · ', $khac ) . '. Muốn lấy sheet khác thì ghi tên nó vào ô Sheet.'; }
			$ghi .= '.';
		}
		return array(
			'van_ban' => self::ghep_dong( $ds[ $chon ]['dong'] ),
			'trang'   => $bang,
			'ghi_chu' => $ghi,
		);
	}

	/** Mảng dòng → văn bản tab. Bỏ dòng trống hoàn toàn ở cuối, giữ dòng trống giữa bảng (số thứ tự dòng không đổi). */
	public static function ghep_dong( $dong ) {
		$ra = array();
		foreach ( $dong as $d ) {
			$ra[] = implode( "\t", array_map( function ( $o ) {
				return str_replace( array( "\t", "\r\n", "\r", "\n" ), array( ' ', ' ', ' ', ' ' ), (string) $o );
			}, $d ) );
		}
		while ( $ra && '' === trim( end( $ra ), "\t " ) ) { array_pop( $ra ); }
		return self::chuan_unicode( implode( "\n", $ra ) );
	}

	/**
	 * Đọc mọi sheet của tệp .xlsx.
	 *
	 * @return array|WP_Error [ ['ten'=>string, 'dong'=>array<array<string>>] ] theo thứ tự sheet.
	 */
	public static function doc_xlsx( $duong ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip', 'Máy chủ thiếu phần mở rộng Zip nên không mở được .xlsx. Mở file bằng Excel, Lưu thành CSV (UTF-8) rồi nạp lại — hoặc copy cả sheet và dán vào ô bên dưới.' );
		}
		if ( ! class_exists( 'SimpleXMLElement' ) ) {
			return new WP_Error( 'xml', 'Máy chủ thiếu SimpleXML nên không đọc được .xlsx. Xuất CSV rồi nạp, hoặc dán bảng.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $duong ) ) {
			return new WP_Error( 'tep', 'Tệp không phải .xlsx hợp lệ (mở không ra). File .xls đời cũ thì Lưu thành .xlsx rồi nạp.' );
		}

		$lay = function ( $ten ) use ( $zip ) {
			$i = $zip->locateName( $ten, ZipArchive::FL_NOCASE );
			return false === $i ? false : $zip->getFromIndex( $i );
		};
		$xml = function ( $s ) {
			if ( false === $s || '' === $s ) { return false; }
			$truoc = libxml_use_internal_errors( true );
			$x     = simplexml_load_string( $s, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
			libxml_clear_errors();
			libxml_use_internal_errors( $truoc );
			return $x;
		};

		$wb = $xml( $lay( 'xl/workbook.xml' ) );
		if ( ! $wb ) {
			$zip->close();
			return new WP_Error( 'tep', 'Tệp .xlsx thiếu workbook — có thể hỏng hoặc chưa phải file Excel.' );
		}

		// rId → đường dẫn sheet
		$rels = $xml( $lay( 'xl/_rels/workbook.xml.rels' ) );
		$map  = array();
		if ( $rels ) {
			foreach ( $rels->Relationship as $r ) {
				$id  = (string) $r['Id'];
				$muc = (string) $r['Target'];
				if ( '' !== $muc && '/' === $muc[0] ) { $muc = ltrim( $muc, '/' ); } else { $muc = 'xl/' . $muc; }
				$map[ $id ] = $muc;
			}
		}

		// Chuỗi dùng chung
		$ss = array();
		$sx = $xml( $lay( 'xl/sharedStrings.xml' ) );
		if ( $sx ) {
			foreach ( $sx->si as $si ) { $ss[] = self::chu_si( $si ); }
		}

		// Kiểu ô: cellXfs[i] → numFmtId → có phải ngày không
		$ngay_kieu = self::kieu_ngay( $xml( $lay( 'xl/styles.xml' ) ) );

		// 1904 date system?
		$moc1904 = false;
		if ( isset( $wb->workbookPr ) ) {
			$v = (string) $wb->workbookPr['date1904'];
			$moc1904 = ( '1' === $v || 'true' === $v );
		}

		$ds = array();
		$wb->registerXPathNamespace( 'r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
		foreach ( $wb->sheets->sheet as $sh ) {
			$rid   = '';
			$attrs = $sh->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
			if ( $attrs && isset( $attrs['id'] ) ) { $rid = (string) $attrs['id']; }
			$duong_sheet = $map[ $rid ] ?? '';
			$dong        = array();
			if ( $duong_sheet ) {
				$sx2 = $xml( $lay( $duong_sheet ) );
				if ( $sx2 && isset( $sx2->sheetData ) ) {
					$dong = self::doc_sheet( $sx2, $ss, $ngay_kieu, $moc1904 );
				}
			}
			$ds[] = array( 'ten' => (string) $sh['name'], 'dong' => $dong );
		}
		$zip->close();
		if ( ! $ds ) {
			return new WP_Error( 'tep', 'Tệp .xlsx không có sheet nào.' );
		}
		return $ds;
	}

	/** Nối mọi mẩu chữ trong một <si> (chuỗi có định dạng nhiều đoạn <r><t>). */
	private static function chu_si( $si ) {
		if ( isset( $si->t ) && ! isset( $si->r ) ) { return (string) $si->t; }
		$s = '';
		foreach ( $si->r as $r ) { $s .= (string) $r->t; }
		if ( '' === $s && isset( $si->t ) ) { $s = (string) $si->t; }
		return $s;
	}

	/**
	 * Từ styles.xml, trả mảng chỉ số cellXfs → true nếu kiểu là ngày/giờ.
	 * numFmtId dựng sẵn 14–22, 45–47 là ngày giờ; numFmt tự đặt thì soi chuỗi
	 * định dạng có d/m/y/h mà không phải chữ trong ngoặc kép hay [Red].
	 */
	private static function kieu_ngay( $st ) {
		$ra = array();
		if ( ! $st ) { return $ra; }
		$tu_dat = array();
		if ( isset( $st->numFmts ) ) {
			foreach ( $st->numFmts->numFmt as $f ) {
				$tu_dat[ (int) $f['numFmtId'] ] = (string) $f['formatCode'];
			}
		}
		if ( ! isset( $st->cellXfs ) ) { return $ra; }
		$i = 0;
		foreach ( $st->cellXfs->xf as $xf ) {
			$id = (int) $xf['numFmtId'];
			$la = false;
			if ( ( $id >= 14 && $id <= 22 ) || ( $id >= 45 && $id <= 47 ) ) {
				$la = true;
			} elseif ( isset( $tu_dat[ $id ] ) ) {
				$ma = $tu_dat[ $id ];
				$ma = preg_replace( '/"[^"]*"|\[[^\]]*\]|\\\\./', '', $ma );   // bỏ chữ trong "", [màu], ký tự escape
				$la = (bool) preg_match( '/[dmyhs]/i', $ma ) && ! preg_match( '/[#0]/', $ma );
			}
			$ra[ $i ] = $la;
			$i++;
		}
		return $ra;
	}

	/** Đọc <sheetData> thành mảng dòng (chỉ số dòng liên tục từ 0; ô trống là ''). */
	private static function doc_sheet( $sx, $ss, $ngay_kieu, $moc1904 ) {
		$dong    = array();
		$so_dong = 0;
		foreach ( $sx->sheetData->row as $row ) {
			$r = (int) $row['r'];
			if ( $r <= 0 ) { $r = $so_dong + 1; }
			// Lấp dòng trống ở giữa để số thứ tự không nhảy
			while ( $so_dong < $r - 1 ) { $dong[] = array(); $so_dong++; }
			$o      = array();
			$cot_so = 0;
			foreach ( $row->c as $c ) {
				$ref = (string) $c['r'];
				$cot = $ref ? self::cot_so( $ref ) : $cot_so + 1;
				while ( $cot_so < $cot - 1 ) { $o[] = ''; $cot_so++; }
				$o[]    = self::gia_tri_o( $c, $ss, $ngay_kieu, $moc1904 );
				$cot_so = $cot;
			}
			// bỏ ô trống cuối dòng
			while ( $o && '' === end( $o ) ) { array_pop( $o ); }
			$dong[] = $o;
			$so_dong++;
		}
		while ( $dong && ! array_filter( end( $dong ), 'strlen' ) ) { array_pop( $dong ); }
		return $dong;
	}

	/** "AB12" → 28 */
	public static function cot_so( $ref ) {
		$chu = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
		$n   = 0;
		for ( $i = 0, $l = strlen( $chu ); $i < $l; $i++ ) { $n = $n * 26 + ( ord( $chu[ $i ] ) - 64 ); }
		return $n;
	}

	/** Giá trị một ô thành chuỗi như người ta thấy. */
	private static function gia_tri_o( $c, $ss, $ngay_kieu, $moc1904 ) {
		$t = (string) $c['t'];
		if ( 'inlineStr' === $t ) {
			return isset( $c->is ) ? self::chu_si( $c->is ) : '';
		}
		if ( ! isset( $c->v ) ) { return ''; }
		$v = (string) $c->v;
		switch ( $t ) {
			case 's':
				return $ss[ (int) $v ] ?? '';
			case 'b':
				return '1' === $v ? 'TRUE' : 'FALSE';
			case 'str':
				return $v;
			case 'e':
				return '';   // #N/A, #REF! — lỗi công thức, không phải số liệu
		}
		// số
		$s = isset( $c['s'] ) ? (int) $c['s'] : -1;
		if ( $s >= 0 && ! empty( $ngay_kieu[ $s ] ) && is_numeric( $v ) ) {
			return self::ngay_excel( (float) $v, $moc1904 );
		}
		return self::so_chu( $v );
	}

	/** Số Excel → "dd/mm/yyyy" hoặc "dd/mm/yyyy HH:MM:SS" nếu có phần giờ. */
	public static function ngay_excel( $n, $moc1904 = false ) {
		if ( $n < 0 ) { return (string) $n; }
		$goc  = $moc1904 ? 24107 : 25569;          // số ngày từ 1899-12-30 (hoặc 1904-01-01) tới 1970-01-01
		if ( ! $moc1904 && $n < 60 ) { $goc = 25568; } // trước 1/3/1900 Excel không tính "ngày 29/2/1900"
		$giay = (int) round( ( $n - $goc ) * 86400 );
		$ngay = gmdate( 'd/m/Y', $giay );
		$gio  = gmdate( 'H:i:s', $giay );
		return '00:00:00' === $gio ? $ngay : $ngay . ' ' . $gio;
	}

	/** Chuỗi số trong XML → chuỗi đẹp: bỏ .0, không ký hiệu mũ, giữ nguyên số nguyên dài. */
	public static function so_chu( $v ) {
		if ( ! is_numeric( $v ) ) { return $v; }
		if ( false !== stripos( $v, 'e' ) ) {
			$f = (float) $v;
			return ( floor( $f ) === $f ) ? sprintf( '%.0f', $f ) : rtrim( rtrim( sprintf( '%.6f', $f ), '0' ), '.' );
		}
		if ( false !== strpos( $v, '.' ) ) {
			$v = rtrim( rtrim( $v, '0' ), '.' );
			if ( '' === $v || '-' === $v ) { $v = '0'; }
		}
		return $v;
	}

	/** Bỏ BOM, đổi UTF-16 (Excel "Unicode Text") về UTF-8. */
	public static function ve_utf8( $vb ) {
		if ( substr( $vb, 0, 3 ) === "\xEF\xBB\xBF" ) { return substr( $vb, 3 ); }
		if ( substr( $vb, 0, 2 ) === "\xFF\xFE" ) { return mb_convert_encoding( substr( $vb, 2 ), 'UTF-8', 'UTF-16LE' ); }
		if ( substr( $vb, 0, 2 ) === "\xFE\xFF" ) { return mb_convert_encoding( substr( $vb, 2 ), 'UTF-8', 'UTF-16BE' ); }
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $vb, 'UTF-8' ) ) {
			// Excel Việt lưu CSV không chọn UTF-8 hay ra Windows-1258
			$thu = @mb_convert_encoding( $vb, 'UTF-8', 'Windows-1258' );
			if ( $thu ) { return $thu; }
		}
		return $vb;
	}

	/**
	 * CSV (phẩy / chấm phẩy) → tab. Đã có tab thì để nguyên. Chọn dấu tách theo
	 * dấu xuất hiện nhiều nhất ở 5 dòng đầu; str_getcsv xử lý ô có ngoặc kép.
	 */
	public static function ve_tab( $vb ) {
		$dong = preg_split( '/\r\n|\r|\n/', $vb );
		$mau  = array_slice( $dong, 0, 5 );
		$dem  = array( "\t" => 0, ';' => 0, ',' => 0 );
		foreach ( $mau as $d ) {
			foreach ( $dem as $k => $_ ) { $dem[ $k ] += substr_count( $d, $k ); }
		}
		if ( $dem["\t"] > 0 ) { return $vb; }
		$tach = $dem[';'] > $dem[','] ? ';' : ',';
		if ( 0 === $dem[ $tach ] ) { return $vb; }
		$ra = array();
		foreach ( $dong as $d ) {
			if ( '' === trim( $d ) ) { $ra[] = ''; continue; }
			$o    = str_getcsv( $d, $tach, '"', '' );
			$ra[] = implode( "\t", array_map( function ( $x ) { return str_replace( "\t", ' ', (string) $x ); }, $o ) );
		}
		return implode( "\n", $ra );
	}

	/**
	 * In ô nạp tệp + ô dán, dùng chung cho mọi màn hình.
	 *
	 * @param string $ten        Tên textarea (ô file là 'tep_' . $ten).
	 * @param string $placeholder
	 * @param int    $rows
	 * @param string $gia_tri    Nội dung có sẵn trong textarea.
	 * @param bool   $chon_sheet Có in ô chọn sheet không.
	 */
	public static function o_nap( $ten, $placeholder, $rows = 7, $gia_tri = '', $chon_sheet = true ) {
		$id = 'khtc-tep-' . sanitize_html_class( $ten );
		echo '<div class="khtc-nap-tep">';
		printf(
			'<label class="khtc-nap-tep-o"><span class="khtc-nap-tep-nhan">Nạp tệp</span> <input type="file" id="%s" name="tep_%s" accept=".xlsx,.xlsm,.xls,.csv,.tsv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain"></label>',
			esc_attr( $id ),
			esc_attr( $ten )
		);
		if ( $chon_sheet ) {
			printf(
				'<label class="khtc-nap-tep-o"><span class="khtc-nap-tep-nhan">Sheet</span> <input type="text" name="trang_%s" size="14" placeholder="để trống = sheet đầu"></label>',
				esc_attr( $ten )
			);
		}
		echo '<span class="khtc-sub">Nhận .xlsx, .xls, .csv, .tsv, .txt — hoặc dán bảng vào ô dưới. Có tệp thì máy đọc tệp, bỏ ô dán.</span>';
		echo '</div>';
		if ( ! empty( self::$ghi_chu[ $ten ] ) ) {
			printf( '<p class="khtc-sub khtc-tu-tep">%s</p>', esc_html( self::$ghi_chu[ $ten ] ) );
		}
		printf(
			'<textarea name="%s" rows="%d" placeholder="%s">%s</textarea>',
			esc_attr( $ten ),
			(int) $rows,
			$placeholder,
			esc_textarea( $gia_tri )
		);
	}
}
