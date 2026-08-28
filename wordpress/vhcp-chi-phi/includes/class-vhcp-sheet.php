<?php
/**
 * ĐỌC TRỰC TIẾP TỪ LINK GOOGLE SHEET — nạp cả file một lượt.
 *
 * Xuất CSV từng tab rồi nạp từng tab dễ thiếu một mảnh: nạp dòng chi trước danh mục thì
 * dòng nào cũng "mồ côi" và bị bỏ. Ở đây plugin tự tải mọi tab, tự đoán tab nào là bảng
 * gì (theo tên cột), tự sắp thứ tự danh mục trước — dòng chi sau, và tự tạo dòng cha còn
 * thiếu rồi báo lại để người dùng bổ sung phần app không đoán được (VD địa điểm của
 * chuyến công tác — thứ quyết định mảng kinh doanh nên không được đoán).
 *
 * Bảng tính phải ở chế độ ai có link cũng xem được (hoặc đã Xuất bản lên web).
 *
 * @package VHCP
 */

defined( 'ABSPATH' ) || exit;

class VHCP_Sheet {

	/**
	 * MỘT TỆP TẠM — KHÔNG DÙNG `wp_tempnam()`.
	 *
	 * 🔴 `wp_tempnam()` nằm ở `wp-admin/includes/file.php`, chỉ nạp khi đang ở trang quản trị
	 *    WordPress. Trang app chi phí là trang THƯỜNG, nên gọi nó là Fatal — và Fatal ở
	 *    front-end thì WordPress chỉ in "Đã có một lỗi nghiêm trọng", không nói tệp nào.
	 *    Vết này đã cắn thật bên chấm công ngày 28/08/2026 (nút Xuất Excel).
	 *
	 * `get_temp_dir()` nằm ở `wp-includes/functions.php` — có ở mọi trang.
	 */
	public static function tep_tam( $dau = 'vhcp' ) {
		$thu = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
		$t   = @tempnam( $thu, $dau );
		if ( ! $t ) { $t = @tempnam( sys_get_temp_dir(), $dau ); }
		return $t ? $t : '';
	}

	/** Lấy ID bảng tính từ link dán vào (hoặc chính ID). */
	public static function doc_id( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) { return ''; }
		if ( preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]{20,})#', $url, $m ) ) { return $m[1]; }
		if ( preg_match( '#^[a-zA-Z0-9_-]{20,}$#', $url ) ) { return $url; }
		return '';
	}

	/** Gọi 1 địa chỉ, trả nội dung hoặc thông báo lỗi đọc được. */
	private static function tai( $url ) {
		$r = wp_remote_get( $url, array( 'timeout' => 25, 'redirection' => 5 ) );
		if ( is_wp_error( $r ) ) { return array( 'loi' => $r->get_error_message() ); }
		$code = (int) wp_remote_retrieve_response_code( $r );
		$body = (string) wp_remote_retrieve_body( $r );
		if ( $code === 401 || $code === 403 ) {
			return array( 'loi' => 'Bảng tính chưa cho xem bằng link (Chia sẻ → Bất kỳ ai có đường liên kết → Người xem)' );
		}
		if ( $code === 404 ) { return array( 'loi' => 'Không tìm thấy (sai link hoặc sai tên tab)' ); }
		if ( $code !== 200 ) { return array( 'loi' => 'Google trả mã ' . $code ); }
		// Chưa chia sẻ thì Google trả về trang đăng nhập chứ không trả mã lỗi
		if ( stripos( $body, '<html' ) !== false && stripos( $body, 'accounts.google.com' ) !== false ) {
			return array( 'loi' => 'Bảng tính chưa cho xem bằng link (Google đòi đăng nhập)' );
		}
		return array( 'body' => $body );
	}

	/**
	 * Liệt kê tab của bảng tính. Google đổi giao diện thường xuyên nên thử lần lượt
	 * 3 đường, đường nào ra thì dùng; không ra thì phía gọi cho nhập tay tên tab.
	 *
	 * @return array ['tabs' => [ ['gid'=>, 'ten'=>], … ], 'cach' => tên cách đọc được,
	 *                'loi' => nếu cả 3 đường đều tắc]
	 */
	public static function liet_ke_tab( $id ) {
		$loi = array();
		foreach ( array( 'htmlview', 'edit', 'xlsx' ) as $cach ) {
			$r = self::liet_ke_bang( $id, $cach );
			if ( ! empty( $r['tabs'] ) ) { return array( 'tabs' => $r['tabs'], 'cach' => $cach ); }
			if ( ! empty( $r['loi'] ) ) { $loi[] = $cach . ': ' . $r['loi']; }
		}
		return array( 'tabs' => array(), 'loi' => 'Không đọc được danh sách tab (' . implode( ' · ', $loi ) . ').'
			. ' Cách chắc chắn nhất: gõ tay tên các tab vào ô "Tên các tab" bên dưới, mỗi dòng một tên.' );
	}

	private static function liet_ke_bang( $id, $cach ) {
		$goc = 'https://docs.google.com/spreadsheets/d/' . $id;

		if ( $cach === 'xlsx' ) {
			if ( ! class_exists( 'ZipArchive' ) ) { return array( 'loi' => 'máy chủ không có ZipArchive' ); }
			$r = self::tai( $goc . '/export?format=xlsx' );
			if ( ! empty( $r['loi'] ) ) { return array( 'loi' => $r['loi'] ); }
			$tmp = self::tep_tam( 'vhcp-sheet' );
			if ( ! $tmp ) { return array( 'loi' => 'không tạo được file tạm' ); }
			file_put_contents( $tmp, $r['body'] );
			$zip = new ZipArchive();
			$ten = array();
			if ( $zip->open( $tmp ) === true ) {
				$xml = $zip->getFromName( 'xl/workbook.xml' );
				$zip->close();
				if ( $xml && preg_match_all( '#<sheet[^>]*\sname="([^"]+)"#', $xml, $m ) ) {
					foreach ( $m[1] as $x ) { $ten[] = html_entity_decode( $x, ENT_QUOTES, 'UTF-8' ); }
				}
			}
			@unlink( $tmp );
			if ( ! count( $ten ) ) { return array( 'loi' => 'file xlsx không có danh sách tab' ); }
			$out = array();
			foreach ( $ten as $x ) { $out[] = array( 'gid' => '', 'ten' => $x ); }   // xlsx không kèm gid
			return array( 'tabs' => $out );
		}

		$r = self::tai( $cach === 'edit' ? $goc . '/edit' : $goc . '/htmlview' );
		if ( ! empty( $r['loi'] ) ) { return array( 'loi' => $r['loi'] ); }
		$body = $r['body'];
		$out  = array();

		// Menu chuyển tab của trang htmlview
		if ( preg_match_all( '#id=(?:"|\')sheet-button-(\d+)(?:"|\')[^>]*>([^<]{1,120})<#', $body, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) { $out[] = array( 'gid' => $x[1], 'ten' => html_entity_decode( trim( $x[2] ), ENT_QUOTES, 'UTF-8' ) ); }
		}
		// Dữ liệu nhúng: {"name":"VH_Index", … "gid":"111"}  và cả thứ tự đảo lại
		if ( ! count( $out ) ) {
			foreach ( array(
				'#"name":"((?:[^"\\\\]|\\\\.)+)"[^{}]{0,400}?"gid":"?(\d+)#',
				'#"gid":"?(\d+)"?[^{}]{0,400}?"name":"((?:[^"\\\\]|\\\\.)+)"#',
			) as $i => $re ) {
				if ( ! preg_match_all( $re, $body, $mm, PREG_SET_ORDER ) ) { continue; }
				foreach ( $mm as $x ) {
					$ten = json_decode( '"' . ( $i === 0 ? $x[1] : $x[2] ) . '"' );
					$gid = ( $i === 0 ? $x[2] : $x[1] );
					if ( $ten !== null && $ten !== '' ) { $out[] = array( 'gid' => $gid, 'ten' => (string) $ten ); }
				}
				if ( count( $out ) ) { break; }
			}
		}

		$seen = array(); $uniq = array();
		foreach ( $out as $x ) {
			$k = $x['gid'] !== '' ? 'g' . $x['gid'] : 't' . VHCP_Nap::kh( $x['ten'] );
			if ( isset( $seen[ $k ] ) ) { continue; }
			$seen[ $k ] = 1;
			$uniq[] = $x;
		}
		if ( ! count( $uniq ) ) { return array( 'loi' => 'không bóc được tên tab trong trang' ); }
		return array( 'tabs' => $uniq );
	}

	/**
	 * ĐOÁN BẢNG ĐÍCH THEO TÊN TAB — chắc hơn dò theo tên cột.
	 *
	 * Tab của bảng tính cũ vốn đặt đúng tên bảng (CH_CoSo, DonHang, ChiPhi…), nên khớp
	 * tên tab là xong; tab của bản sau này thì đổi tên (VH_Index, VH_Line, CT_ChiTiet)
	 * nên có bảng tên gọi khác. Không khớp được thì mới quay sang dò theo tên cột.
	 *
	 * @return string mã loại tab của bộ nạp, '' nếu không nhận ra
	 */
	public static function doan_tu_ten( $ten ) {
		$k = VHCP_Nap::kh( $ten );
		if ( $k === '' ) { return ''; }

		// Tên tab trùng luôn tên loại tab của bộ nạp (CH_CoSo, DonHang, TamUng, ChiPhi…)
		foreach ( array_keys( VHCP_Import::types() ) as $loai ) {
			if ( VHCP_Nap::kh( $loai ) === $k ) { return $loai; }
		}

		// Tên gọi khác
		$alias = array(
			'vhindex'    => 'TD_Don',
			'vhline'     => 'TD_ChiPhi',
			'vhchiphi'   => 'TD_ChiPhi',
			'ctindex'    => 'TD_BPIndex',
			'ctchitiet'  => 'TD_BPLine',
			'bpline'     => 'TD_BPLine',
			'bpchitiet'  => 'TD_BPLine',
			'mkline'     => 'TD_MKLine',
			'mkdon'      => 'TD_MKDon',
			'sochi'      => 'TD_SoChi',
			'chiphicoso' => 'TD_SoChi',
		);
		if ( isset( $alias[ $k ] ) ) { return $alias[ $k ]; }

		// Tab của một dự án kỹ thuật: tên bắt đầu bằng "DA "
		// Tab của một dự án: đổ vào SỔ CHI PHÍ, dòng nào cũng mang mã dự án = tên tab.
		// Một dự án giờ chỉ là "các dòng chi cùng mã", khỏi cần bảng dự án riêng.
		if ( preg_match( '/^da[a-z0-9]/', $k ) || preg_match( '/^\s*DA\s+/iu', (string) $ten ) ) { return 'TD_SoChi'; }

		return '';
	}

	/** Thứ tự nạp: cấu hình trước, danh mục, rồi dòng chi. */
	public static function uu_tien( $loai ) {
		if ( strpos( $loai, 'CH_' ) === 0 ) { return 1; }
		$mux = array(
			'DonHang' => 2, 'TD_Don' => 2, 'DA_Index' => 2, 'BP_Index' => 2, 'TD_BPIndex' => 2,
			'MK_Don' => 2, 'TD_MKDon' => 2,
			'TamUng' => 3,
			'ChiPhi' => 4, 'TD_ChiPhi' => 4, 'BP_Sheet' => 4, 'TD_BPLine' => 4,
			'DA_Sheet' => 4, 'TD_DALine' => 4, 'MK_Line' => 4, 'TD_MKLine' => 4,
			'SoChi' => 5, 'TD_SoChi' => 5, 'NhatKy' => 6,
		);
		return isset( $mux[ $loai ] ) ? $mux[ $loai ] : 9;
	}

	/**
	 * TẢI CẢ BẢNG TÍNH DẠNG .XLSX RỒI ĐỌC THẲNG — đường đáng tin nhất.
	 *
	 * Hai đường kia đều có chỗ hụt: tải theo TÊN tab (gviz) thì Google tự đoán kiểu cột
	 * nên ô tiêu đề của cột số bị trả về rỗng (mất luôn cột tiền); tải theo GID thì phải
	 * dò được gid, mà danh sách tab lấy từ .xlsx lại không kèm gid.
	 *
	 * Đọc .xlsx thì lấy được GIÁ TRỊ GỐC của mọi ô, mọi tab, chỉ với 1 lần tải.
	 *
	 * @return array [ tên tab => mảng dòng (mảng ô) ] · [] nếu không đọc được
	 */
	public static function tai_workbook( $id ) {
		// Chỉ nhớ khi ĐỌC ĐƯỢC: một lần tải lỗi không được làm chết cả lượt sau
		static $nho = array();
		if ( ! empty( $nho[ $id ] ) ) { return $nho[ $id ]; }

		if ( ! class_exists( 'ZipArchive' ) ) { return array(); }
		$r = self::tai( 'https://docs.google.com/spreadsheets/d/' . $id . '/export?format=xlsx' );
		if ( ! empty( $r['loi'] ) ) { return array(); }
		$tmp = self::tep_tam( 'vhcp-xlsx' );
		if ( ! $tmp ) { return array(); }
		file_put_contents( $tmp, $r['body'] );

		$zip = new ZipArchive();
		if ( $zip->open( $tmp ) !== true ) { @unlink( $tmp ); return array(); }

		// 1) tên tab + rId  ·  2) rId -> file worksheet
		$wb = (string) $zip->getFromName( 'xl/workbook.xml' );
		$rels = (string) $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
		$sheet_file = array();
		if ( preg_match_all( '#<Relationship[^>]*Id="([^"]+)"[^>]*Target="([^"]+)"#', $rels, $mr, PREG_SET_ORDER ) ) {
			foreach ( $mr as $x ) { $sheet_file[ $x[1] ] = ltrim( str_replace( '/xl/', '', $x[2] ), '/' ); }
		}
		$tabs = array();
		if ( preg_match_all( '#<sheet[^>]*name="([^"]*)"[^>]*r:id="([^"]+)"#', $wb, $ms, PREG_SET_ORDER ) ) {
			foreach ( $ms as $x ) {
				$ten = html_entity_decode( $x[1], ENT_QUOTES, 'UTF-8' );
				$f   = isset( $sheet_file[ $x[2] ] ) ? $sheet_file[ $x[2] ] : '';
				if ( $ten !== '' && $f !== '' ) { $tabs[ $ten ] = $f; }
			}
		}

		// 3) bảng chuỗi dùng chung
		$ss = array();
		$sx = (string) $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( $sx !== '' && preg_match_all( '#<si>(.*?)</si>#s', $sx, $mss ) ) {
			foreach ( $mss[1] as $si ) {
				$t = '';
				if ( preg_match_all( '#<t[^>]*>(.*?)</t>#s', $si, $mt ) ) { $t = implode( '', $mt[1] ); }
				$ss[] = html_entity_decode( $t, ENT_QUOTES, 'UTF-8' );
			}
		}

		// 4) từng tab -> mảng dòng
		$out = array();
		foreach ( $tabs as $ten => $f ) {
			$xml = (string) $zip->getFromName( 'xl/' . $f );
			if ( $xml === '' ) { $xml = (string) $zip->getFromName( $f ); }
			if ( $xml === '' ) { continue; }
			$out[ $ten ] = self::doc_sheet_xml( $xml, $ss );
		}
		$zip->close();
		@unlink( $tmp );

		$nho[ $id ] = $out;
		return $out;
	}

	/** Đọc 1 worksheet XML thành mảng dòng, giữ đúng vị trí cột theo địa chỉ ô (A1, C4…). */
	private static function doc_sheet_xml( $xml, $ss ) {
		$rows = array();
		if ( ! preg_match_all( '#<row[^>]*>(.*?)</row>#s', $xml, $mr ) ) { return $rows; }
		foreach ( $mr[1] as $rx ) {
			$dong = array();
			// Ô RỖNG của bảng tính là thẻ tự đóng: <c r="A7" s="3"/>. Nếu chỉ bắt dạng
			// <c ...>...</c> thì phần "..." ăn luôn sang ô KẾ TIẾP, nên ô rỗng lại mang giá
			// trị của ô sau — lệch cột và ra số vô lý. Bắt cả hai dạng trong một lần.
			if ( preg_match_all( '#<c\b([^>]*?)(?:/>|>(.*?)</c>)#s', $rx, $mc, PREG_SET_ORDER ) ) {
				foreach ( $mc as $c ) {
					$attr = $c[1];
					$noi  = isset( $c[2] ) ? $c[2] : '';
					$cot  = 0;
					if ( preg_match( '#r="([A-Z]+)#', $attr, $mA ) ) { $cot = self::cot_so( $mA[1] ); }
					$loai = ( preg_match( '#t="([^"]+)"#', $attr, $mT ) ? $mT[1] : '' );
					$val  = '';
					if ( $loai === 's' ) {
						if ( preg_match( '#<v>(.*?)</v>#s', $noi, $mv ) ) {
							$i = (int) $mv[1];
							$val = isset( $ss[ $i ] ) ? $ss[ $i ] : '';
						}
					} elseif ( $loai === 'inlineStr' ) {
						if ( preg_match_all( '#<t[^>]*>(.*?)</t>#s', $noi, $mt2 ) ) { $val = html_entity_decode( implode( '', $mt2[1] ), ENT_QUOTES, 'UTF-8' ); }
					} elseif ( $loai === 'e' ) {
						$val = '';   // ô lỗi (#ERROR!) -> coi như trống
					} else {
						if ( preg_match( '#<v>(.*?)</v>#s', $noi, $mv2 ) ) { $val = html_entity_decode( $mv2[1], ENT_QUOTES, 'UTF-8' ); }
					}
					$dong[ $cot ] = $val;
				}
			}
			if ( count( $dong ) ) {
				$max = max( array_keys( $dong ) );
				for ( $i = 0; $i <= $max; $i++ ) { if ( ! isset( $dong[ $i ] ) ) { $dong[ $i ] = ''; } }
				ksort( $dong );
				$rows[] = array_values( $dong );
			} else {
				$rows[] = array();
			}
		}

		// CẮT ĐUÔI RỖNG. Bảng tính của K&H kẻ khung/định dạng sẵn cả nghìn dòng, nên file
		// .xlsx chứa <row> cho từng dòng đó dù không có chữ nào (ô chỉ có định dạng, hoặc
		// công thức =IFERROR(...,"") trả ra rỗng). Google xuất CSV thì tự cắt, còn .xlsx
		// thì không: giữ lại là báo "sẽ nạp 981 dòng" cho một tab chỉ có 135 dòng thật.
		for ( $i = count( $rows ) - 1; $i >= 0; $i-- ) {
			if ( self::dong_rong( $rows[ $i ] ) ) { unset( $rows[ $i ] ); } else { break; }
		}
		return array_values( $rows );
	}

	/** Dòng không có ô nào mang chữ (kể cả ô chỉ có định dạng hay công thức trả rỗng). */
	private static function dong_rong( $dong ) {
		foreach ( (array) $dong as $o ) {
			if ( trim( (string) $o ) !== '' ) { return false; }
		}
		return true;
	}

	/** Dựng lại CSV từ mảng dòng, để dùng chung bộ nạp sẵn có. */
	public static function rows_to_csv( $rows ) {
		$fh = fopen( 'php://memory', 'r+' );
		foreach ( (array) $rows as $r ) { fputcsv( $fh, array_map( 'strval', (array) $r ) ); }
		rewind( $fh );
		$out = stream_get_contents( $fh );
		fclose( $fh );
		return (string) $out;
	}

	/** "A" -> 0, "B" -> 1, "AA" -> 26 */
	private static function cot_so( $chu ) {
		$n = 0;
		$chu = strtoupper( (string) $chu );
		for ( $i = 0, $L = strlen( $chu ); $i < $L; $i++ ) {
			$n = $n * 26 + ( ord( $chu[ $i ] ) - 64 );
		}
		return max( 0, $n - 1 );
	}

	/**
	 * Dò TÊN TAB gõ tay về đúng tên tab thật trong bảng tính.
	 *
	 * Gõ tay thì không ai gõ trùng từng ký tự: tab thật là "DA NHÀ MA BÀ RỊA" mà người
	 * dùng gõ "NHÀ MA BÀ RỊA", hoặc lệch dấu cách / chữ hoa. Trước đây so khớp nguyên
	 * văn nên trượt, rồi âm thầm rơi xuống đường tải theo tên (đường làm mất tiêu đề cột
	 * số) — báo cáo ra "nạp 0 dòng" mà không nói vì sao.
	 *
	 * @param string $ten tên gõ tay
	 * @param array  $ds  danh sách tên tab thật
	 * @return string tên tab thật, '' nếu không chắc chắn khớp cái nào
	 */
	public static function khop_ten_tab( $ten, $ds ) {
		$k = VHCP_Nap::kh( $ten );
		if ( $k === '' ) { return ''; }
		$bo_da = function ( $x ) { return VHCP_Nap::kh( preg_replace( '/^\s*DA\s+/iu', '', (string) $x ) ); };

		// 1) khớp nguyên tên (đã bỏ dấu, bỏ hoa/thường)
		foreach ( (array) $ds as $t ) { if ( VHCP_Nap::kh( $t ) === $k ) { return (string) $t; } }
		// 2) khớp sau khi bỏ tiền tố "DA " ở cả hai bên — gõ thiếu/thừa chữ DA
		$kd = $bo_da( $ten );
		$hit = array();
		foreach ( (array) $ds as $t ) { if ( $bo_da( $t ) === $kd ) { $hit[] = (string) $t; } }
		if ( count( $hit ) === 1 ) { return $hit[0]; }
		if ( count( $hit ) > 1 ) { return ''; }   // mơ hồ thì không đoán
		// 3) chứa nhau và CHỈ có một tab như vậy
		$hit = array();
		foreach ( (array) $ds as $t ) {
			$kt = VHCP_Nap::kh( $t );
			if ( $kt !== '' && ( strpos( $kt, $k ) !== false || strpos( $k, $kt ) !== false ) ) { $hit[] = (string) $t; }
		}
		return ( count( $hit ) === 1 ) ? $hit[0] : '';
	}

	/** Tải 1 tab về dạng CSV (theo gid nếu có, không thì theo tên). */
	public static function tai_tab( $id, $gid = '', $ten = '' ) {
		if ( $gid !== '' ) {
			$url = 'https://docs.google.com/spreadsheets/d/' . $id . '/export?format=csv&gid=' . rawurlencode( $gid );
		} else {
			$url = 'https://docs.google.com/spreadsheets/d/' . $id . '/gviz/tq?tqx=out:csv&sheet=' . rawurlencode( $ten );
		}
		return self::tai( $url );
	}

	/**
	 * ĐỌC CẢ BẢNG TÍNH MỘT LƯỢT.
	 *
	 * @param string $url  link bảng tính
	 * @param array  $opts ['thu' => true chỉ xem sẽ nạp gì, chưa ghi]
	 *                     ['tabs' => chỉ nạp mấy tab này (theo tên)]
	 *                     ['taoCha' => true tự tạo dự án / đợt còn thiếu (mặc định bật)]
	 */
	public static function nap_ca_file( $url, $opts = array() ) {
		$id = self::doc_id( $url );
		if ( $id === '' ) { return VHCP_Util::err( 'Link không phải Google Sheet — dán link có dạng docs.google.com/spreadsheets/d/…' ); }

		$opts    = (array) $opts;
		$thu     = ! empty( $opts['thu'] );
		$tao_cha = ! isset( $opts['taoCha'] ) || ! empty( $opts['taoCha'] );
		// Gõ tay tên tab thì dùng luôn, khỏi phải đọc danh sách (đường chắc chắn nhất)
		$chi     = array();
		$ten_tay = array();
		foreach ( (array) ( isset( $opts['tabs'] ) ? $opts['tabs'] : array() ) as $t ) {
			$t = trim( (string) $t );
			if ( $t !== '' ) { $ten_tay[] = $t; }
		}
		// Đọc thẳng .xlsx trước: 1 lần tải, mọi tab, GIÁ TRỊ GỐC của mọi ô — không qua
		// chỗ Google đoán kiểu cột (chỗ làm mất tiêu đề cột số).
		$wbk  = self::tai_workbook( $id );
		$cach = 'gõ tay';
		$tabs = array();
		$lk   = count( $wbk ) ? array( 'tabs' => array(), 'cach' => 'file .xlsx' ) : self::liet_ke_tab( $id );
		if ( count( $wbk ) ) {
			foreach ( array_keys( $wbk ) as $tn ) { $lk['tabs'][] = array( 'gid' => '', 'ten' => $tn ); }
		}
		if ( count( $ten_tay ) ) {
			// Gõ tay tên tab nhưng VẪN dò gid: tải theo gid mới đúng nguyên bản sheet.
			// Tải theo TÊN (gviz) thì Google tự đoán kiểu cột, cột số sẽ trả ô tiêu đề
			// thành RỖNG -> app không thấy cột tiền, nạp ra 0đ mà không có gì báo.
			$gid_theo_ten = array();
			foreach ( (array) ( isset( $lk['tabs'] ) ? $lk['tabs'] : array() ) as $x ) {
				$gid_theo_ten[ VHCP_Nap::kh( $x['ten'] ) ] = array( 'gid' => $x['gid'], 'ten' => $x['ten'] );
			}
			$ten_that  = array();
			foreach ( (array) ( isset( $lk['tabs'] ) ? $lk['tabs'] : array() ) as $x ) { $ten_that[] = $x['ten']; }
			$khong_thay = array();
			foreach ( $ten_tay as $t ) {
				// Dò về tên tab THẬT trước: gõ "NHÀ MA BÀ RỊA" phải ra tab "DA NHÀ MA BÀ RỊA"
				$that = self::khop_ten_tab( $t, $ten_that );
				if ( $that === '' && count( $wbk ) ) {
					// Đọc được cả bảng tính mà không có tab nào tên vậy: báo thẳng, đừng
					// rơi xuống đường tải theo tên rồi ra 0 dòng.
					$khong_thay[] = $t;
					continue;
				}
				if ( $that === '' ) { $that = $t; }
				$kk = VHCP_Nap::kh( $that );
				if ( isset( $gid_theo_ten[ $kk ] ) && $gid_theo_ten[ $kk ]['gid'] !== '' ) {
					$tabs[] = array( 'gid' => $gid_theo_ten[ $kk ]['gid'], 'ten' => $gid_theo_ten[ $kk ]['ten'] );
				} else {
					$tabs[] = array( 'gid' => '', 'ten' => $that );
				}
			}
			if ( count( $khong_thay ) && ! count( $tabs ) ) {
				return VHCP_Util::err( 'Bảng tính không có tab nào tên "' . implode( '", "', $khong_thay ) . '".'
					. ' Tên tab trong bảng tính: ' . implode( ' · ', $ten_that )
					. '. Xóa trống ô "Tên các tab" để nạp hết mọi tab.' );
			}
			if ( count( $wbk ) ) {
				$cach = 'file .xlsx';   // đọc thẳng workbook, tên tab gõ tay chỉ để chọn tab
			} else {
				$co_gid = 0;
				foreach ( $tabs as $x ) { if ( $x['gid'] !== '' ) { $co_gid++; } }
				$cach = 'gõ tay' . ( $co_gid ? ' (có gid cho ' . $co_gid . '/' . count( $tabs ) . ' tab)' : ' — KHÔNG dò được gid, phải tải theo tên' );
			}
		} else {
			if ( empty( $lk['tabs'] ) ) { return VHCP_Util::err( isset( $lk['loi'] ) ? $lk['loi'] : 'Không đọc được danh sách tab' ); }
			$tabs = $lk['tabs'];
			$cach = isset( $lk['cach'] ) ? $lk['cach'] : '';
		}

		// 1) Tải từng tab: ưu tiên nhận theo TÊN TAB, không được thì dò theo TÊN CỘT
		$viec = array();
		// Tên gõ tay không có trong bảng tính: đưa vào báo cáo cho thấy, đừng lặng lẽ bỏ
		foreach ( ( isset( $khong_thay ) ? $khong_thay : array() ) as $t ) {
			$viec[] = array( 'tab' => $t, 'bo' => 'bảng tính không có tab nào tên vậy — kiểm lại chính tả, hoặc xóa trống ô "Tên các tab" để nạp hết' );
		}
		foreach ( $tabs as $tab ) {
			$theo_ten = false;
			$csv_tab  = '';
			if ( isset( $wbk[ $tab['ten'] ] ) ) {
				$rows    = $wbk[ $tab['ten'] ];
				$csv_tab = self::rows_to_csv( $rows );
			} else {
				$r = self::tai_tab( $id, $tab['gid'], $tab['ten'] );
				$theo_ten = ( $tab['gid'] === '' );
				if ( ! empty( $r['loi'] ) ) {
					$viec[] = array( 'tab' => $tab['ten'], 'bo' => 'không tải được: ' . $r['loi'] );
					continue;
				}
				$csv_tab = $r['body'];
				$rows    = VHCP_Import::parse( $csv_tab );
			}
			if ( ! count( $rows ) ) { $viec[] = array( 'tab' => $tab['ten'], 'bo' => 'tab trống' ); continue; }

			$loai = self::doan_tu_ten( $tab['ten'] );
			$cach_nhan = 'tên tab';
			$diem = '';
			if ( $loai === '' ) {
				$doan = VHCP_Nap::doan_bang( $rows );
				if ( $doan['bang'] === '' ) {
					$viec[] = array(
						'tab'    => $tab['ten'],
						'bo'     => 'tên tab không nhận ra, tên cột cũng không (khớp được ' . (int) $doan['diem'] . ' cột)',
						'dongDau' => self::xem_dong( $rows ),
					);
					continue;
				}
				$loai = array(
					'don' => 'TD_Don', 'chiphi' => 'TD_ChiPhi', 'bp_index' => 'TD_BPIndex', 'bp_line' => 'TD_BPLine',
					'da_line' => 'TD_DALine', 'mk_don' => 'TD_MKDon', 'mk_line' => 'TD_MKLine', 'sochi' => 'TD_SoChi',
				)[ $doan['bang'] ];
				$cach_nhan = 'tên cột';
				$diem      = $doan['diem'];
			}
			$viec[] = array( 'tab' => $tab['ten'], 'loai' => $loai, 'cachNhan' => $cach_nhan, 'diem' => $diem, 'rows' => $rows, 'csv' => $csv_tab, 'theoTen' => $theo_ten );
		}

		// 2) Sắp thứ tự: cấu hình -> danh mục -> tạm ứng -> dòng chi -> nhật ký
		usort( $viec, function ( $a, $b ) {
			$x = isset( $a['loai'] ) ? VHCP_Sheet::uu_tien( $a['loai'] ) : 99;
			$y = isset( $b['loai'] ) ? VHCP_Sheet::uu_tien( $b['loai'] ) : 99;
			return $x <=> $y;
		} );

		// 3) Nạp
		$bc = array(); $tong = 0; $tong_bo = 0; $tong_thieu_ma = 0; $tao = array();

		// XOÁ DỮ LIỆU CŨ CHỈ MỘT LẦN CHO MỖI BẢNG ĐÍCH.
		//
		// Một bảng đích thường nhận NHIỀU tab: bảng tính có "DA NHÀ MA BÀ RỊA",
		// "DA NHÀ MA BÀ RỊA (2)", rồi hàng chục tab dự án khác, tất cả đều vào sổ chi phí.
		// Trước đây mỗi tab đều được truyền replace=true nên tab sau XOÁ SẠCH những gì các
		// tab trước vừa nạp — chỉ tab cuối còn lại, màn dự án hiện thiếu tiền mà không có
		// gì báo. Nay chỉ tab ĐẦU TIÊN của mỗi bảng mới xoá.
		$da_xoa = array();

		foreach ( $viec as $v ) {
			if ( empty( $v['loai'] ) ) {
				$bc[] = array( 'tab' => $v['tab'], 'ketQua' => 'bỏ qua — ' . $v['bo'], 'dongDau' => isset( $v['dongDau'] ) ? $v['dongDau'] : '' );
				continue;
			}
			$loai = $v['loai'];
			$mo   = array( 'tab' => $v['tab'], 'bang' => self::ten_loai( $loai ), 'cachNhan' => $v['cachNhan'], 'cotKhop' => $v['diem'] );
			// Tải theo TÊN tab (gviz) là Google tự đoán kiểu cột: ô tiêu đề của cột số bị
			// trả về rỗng nên app mất luôn cột tiền. Cảnh báo ngay trên báo cáo.
			if ( ! empty( $v['theoTen'] ) ) { $mo['canhBao'] = 'tải theo TÊN tab (không có gid) — cột số có thể mất tiêu đề, kiểm dòng "đọc:" xem có so_tien chưa'; }

			if ( $thu ) {
				$mo['ketQua'] = 'sẽ nạp vào ' . self::ten_loai( $loai ) . ' · ' . max( 0, count( $v['rows'] ) - 1 ) . ' dòng (trừ dòng tiêu đề)';
				if ( strpos( $loai, 'TD_' ) === 0 ) {
					$bang_td = self::bang_cua_td( $loai );
					$k = VHCP_Nap::khop( $bang_td, $v['rows'] );
					if ( empty( $k['loi'] ) ) {
						$mo['ketQua']   = 'sẽ nạp ' . count( $k['rows'] ) . ' dòng vào ' . self::ten_loai( $loai );
						if ( self::la_tab_du_an( $v['tab'] ) ) {
							$mo['ketQua'] .= ' · mã dự án "' . self::ma_du_an_tu_ten_tab( $v['tab'] ) . '" gắn vào từng dòng';
							$ct = self::coso_cua_tab( $v['tab'] );
							if ( $ct['ghiChu'] !== '' ) { $mo['ketQua'] .= ' · ' . $ct['ghiChu']; }
						}
						// Tính trước TỔNG TIỀN sẽ nạp — biết cột tiền đọc đúng chưa mà chưa ghi gì
						if ( $bang_td === 'sochi' ) {
							$hm = array();
							foreach ( $k['rows'] as $rr ) {
								$h = VHCP_Nap::o( $rr, $k['hd'], 'hang_muc' );
								if ( $h !== '' ) { $hm[ mb_strtolower( $h ) ] = 1; }
							}
							$tt = 0; $k0 = 0; $dt_cha = 0; $dt_con = 0;
							foreach ( $k['rows'] as $rr ) {
								$nd0  = VHCP_Nap::o( $rr, $k['hd'], 'noi_dung' );
								$la_c = isset( $hm[ mb_strtolower( $nd0 ) ] );
								$dt   = VHCP_Util::num( VHCP_Util::doc_so( VHCP_Nap::o_so( $rr, $k, 'du_toan' ) ) );
								if ( $la_c ) { $dt_cha += $dt; continue; }   // dòng tổng hợp: bỏ phần thực chi
								$dt_con += $dt;
								$so = VHCP_Util::num( VHCP_Util::doc_so( VHCP_Nap::o_so( $rr, $k, 'so_tien' ) ) );
								$tt += $so;
								if ( ! $so ) { $k0++; }
							}
							$mo['ketQua'] .= ' · TỔNG TIỀN sẽ nạp ' . number_format( $tt, 0, ',', '.' ) . 'đ';
							if ( $k0 ) { $mo['ketQua'] .= ' · ' . $k0 . ' dòng ra 0đ'; }
							$mo['ketQua'] .= ' · TỔNG DỰ TOÁN ' . number_format( $dt_cha + $dt_con, 0, ',', '.' ) . 'đ';
							// Dự toán mà điền CẢ dòng hạng mục lớn LẪN dòng con là cộng hai lần.
							// Báo ngay ở đây, đừng để tới lúc màn dự án hiện số gấp đôi.
							if ( $dt_cha > 0 && $dt_con > 0 ) {
								$mo['canhBao'] = 'Dự toán điền ở CẢ dòng hạng mục lớn ('
									. number_format( $dt_cha, 0, ',', '.' ) . 'đ) LẪN dòng con ('
									. number_format( $dt_con, 0, ',', '.' ) . 'đ) — tổng dự toán sẽ bị cộng hai lần.'
									. ' Đối chiếu với dòng tổng của tab trước khi nạp thật.';
							}
						}
						$mo['cotThieu'] = $k['thieu'];
						$mo['cotLa']    = $k['la'];
						$mo['khopVoi']  = isset( $k['khopVoi'] ) ? $k['khopVoi'] : array();
					} else {
						$mo['ketQua'] = 'KHÔNG khớp được tên cột — ' . $k['loi'];
						$mo['dongDau'] = self::xem_dong( $v['rows'] );
					}
				}
				$bc[] = $mo;
				continue;
			}

			// Tab của một dự án: mã dự án lấy từ TÊN TAB, gắn vào từng dòng chi
			$ma_chon = '';
			if ( self::la_tab_du_an( $v['tab'] ) ) {
				$ma_chon = self::ma_du_an_tu_ten_tab( $v['tab'] );
			}
			if ( $loai === 'TD_DALine' || $loai === 'DA_Sheet' ) {
				$ten_da  = trim( preg_replace( '/^\s*DA\s+/iu', '', $v['tab'] ) );
				$ma_chon = self::tim_hoac_tao_du_an( $ten_da, $tao_cha, $tao );
				if ( $ma_chon === '' ) {
					$bc[] = array( 'tab' => $v['tab'], 'ketQua' => 'bỏ qua — chưa có dự án "' . $ten_da . '" và đang tắt tự tạo dòng cha' );
					continue;
				}
			}
			if ( ( $loai === 'TD_BPLine' || $loai === 'BP_Sheet' ) && $tao_cha ) {
				self::tao_dot_con_thieu( $v['rows'], $tao );
			}

			$cs_tab = array( 'coso' => '', 'ghiChu' => '' );
			if ( self::la_tab_du_an( $v['tab'] ) ) {
				$cs_tab = self::coso_cua_tab( $v['tab'] );
				if ( $cs_tab['ghiChu'] !== '' ) { $mo['coSo'] = $cs_tab['ghiChu']; }
			}
			$bang_dich = self::bang_cua_td( $loai );
			if ( $bang_dich === '' ) { $bang_dich = $loai; }
			$xoa_lan_nay = ( ! empty( $opts['replace'] ) && empty( $da_xoa[ $bang_dich ] ) );
			if ( $xoa_lan_nay ) { $da_xoa[ $bang_dich ] = 1; }

			$res = VHCP_Import::run( $loai, $v['csv'], array(
				'ma'      => $ma_chon,
				'coso'    => $cs_tab['coso'],
				'replace' => $xoa_lan_nay,
				'header'  => true,
			) );
			if ( empty( $res['success'] ) ) {
				$mo['ketQua'] = 'lỗi — ' . ( isset( $res['error'] ) ? $res['error'] : '' );
				$mo['dongDau'] = self::xem_dong( $v['rows'] );
				$bc[] = $mo;
				continue;
			}
			if ( $xoa_lan_nay ) { $mo['xoaTruoc'] = self::ten_bang( $bang_dich ); }
			if ( self::la_tab_du_an( $v['tab'] ) && self::tab_nhan_ban( $v['tab'] ) ) {
				$mo['gopVao'] = self::ma_du_an_tu_ten_tab( $v['tab'] );
			}
			$mo['napDuoc']  = (int) $res['inserted'];
			$mo['boQua']    = isset( $res['skipped'] ) ? (int) $res['skipped'] : 0;
			$mo['thieuMa']  = isset( $res['thieuMa'] ) ? (int) $res['thieuMa'] : 0;
			$mo['cotThieu'] = isset( $res['cotThieu'] ) ? $res['cotThieu'] : array();
			$mo['cotLa']    = isset( $res['cotLa'] ) ? $res['cotLa'] : array();
			$mo['moCoi']    = isset( $res['chuaCoCha'] ) ? $res['chuaCoCha'] : array();
			$mo['khopVoi']  = isset( $res['khopVoi'] ) ? $res['khopVoi'] : array();
			if ( ! empty( $res['themLoai'] ) ) { $mo['ketQua_them'] = (int) $res['themLoai']; }
			if ( ! empty( $res['dongTong'] ) ) { $mo['dongTong'] = (int) $res['dongTong']; }
			$mo['ketQua']   = 'nạp ' . $mo['napDuoc'] . ' dòng vào ' . self::ten_loai( $loai );
			if ( ! empty( $mo['dongTong'] ) ) { $mo['ketQua'] .= ' · ' . (int) $mo['dongTong'] . ' dòng tổng hợp đưa về 0 để không đếm hai lần'; }
			if ( ! empty( $mo['ketQua_them'] ) ) { $mo['ketQua'] .= ' · thêm ' . (int) $mo['ketQua_them'] . ' loại chi phí vào danh mục'; }
			if ( isset( $res['tongTien'] ) ) {
				$mo['ketQua'] .= ' · TỔNG TIỀN ' . number_format( (float) $res['tongTien'], 0, ',', '.' ) . 'đ';
				if ( ! empty( $res['khongTien'] ) ) { $mo['ketQua'] .= ' · ' . (int) $res['khongTien'] . ' dòng ra 0đ (kiểm lại cột tiền!)'; }
			}
			if ( ! empty( $mo['coSo'] ) ) { $mo['ketQua'] .= ' · ' . $mo['coSo']; }
			$tong          += $mo['napDuoc'];
			$tong_bo       += $mo['boQua'];
			$tong_thieu_ma += $mo['thieuMa'];
			$bc[] = $mo;
		}

		return VHCP_Util::ok( array(
			'thu'      => $thu ? 1 : 0,
			'cach'     => $cach,
			'soTab'    => count( $tabs ),
			'baoCao'   => $bc,
			'tong'     => $tong,
			'boQua'    => $tong_bo,
			'thieuMa'  => $tong_thieu_ma,
			'tuTao'    => array_values( $tao ),
		) );
	}

	/**
	 * CƠ SỞ CỦA MỘT TAB DỰ ÁN, dò từ tên tab.
	 *
	 * Dòng chi trong tab dự án phần lớn để trống cột cơ sở, mà không có cơ sở thì không
	 * biết mảng kinh doanh nào -> không ra TK Nợ. Tên tab lại chính là nơi làm dự án
	 * ("DA FARM NHA TRANG"), nên đối chiếu với danh sách cơ sở: khớp được thì lấy, khớp
	 * nhiều hoặc không khớp thì để trống và báo lại — không đoán bừa.
	 *
	 * @return array [coso, ghiChu]
	 */
	public static function coso_cua_tab( $ten_tab ) {
		// ma_du_an_tu_ten_tab() đã bỏ đuôi nhân bản "(2)" nên tên này dùng được luôn.
		$m = self::ma_du_an_tu_ten_tab( $ten_tab );
		$k = VHCP_Nap::kh( $m );
		if ( $k === '' ) { return array( 'coso' => '', 'ghiChu' => '' ); }
		$hit = array();
		foreach ( VHCP_Cfg::cfg_static()['coso'] as $x ) {
			$c = VHCP_Nap::kh( $x['ten'] );
			if ( $c === '' ) { continue; }
			if ( $c === $k || strpos( $c, $k ) !== false || strpos( $k, $c ) !== false ) { $hit[] = (string) $x['ten']; }
		}
		// Bảng cơ sở có thể khai TRÙNG TÊN (2 dòng cùng tên) — đó vẫn là một cơ sở,
		// không phải chuyện phải đi hỏi lại.
		$hit = array_values( array_unique( $hit ) );
		if ( count( $hit ) === 1 ) { return array( 'coso' => $hit[0], 'ghiChu' => 'cơ sở "' . $hit[0] . '" (dò từ tên tab)' ); }
		if ( count( $hit ) > 1 ) { return array( 'coso' => '', 'ghiChu' => 'tên tab khớp ' . count( $hit ) . ' cơ sở khác nhau (' . implode( ' · ', $hit ) . ') → không đoán, dòng sẽ chưa có mã' ); }
		return array( 'coso' => '', 'ghiChu' => 'không tìm được cơ sở nào tên giống "' . $m . '" → dòng sẽ chưa có mã' );
	}

	/** Tab này có phải tab của một dự án không (tên bắt đầu bằng "DA ")? */
	public static function la_tab_du_an( $ten ) {
		return (bool) preg_match( '/^\s*DA\s+/iu', (string) $ten );
	}

	/**
	 * Mã dự án lấy từ tên tab: bỏ chữ "DA " đứng đầu và bỏ đuôi nhân bản "(2)".
	 *
	 * Một công trình dài thường trải ra nhiều tab: "DA NHÀ MA BÀ RỊA", rồi hết chỗ thì
	 * nhân bản thành "DA NHÀ MA BÀ RỊA (2)". Giữ đuôi đó lại là tách thành HAI dự án khác
	 * nhau, nên màn dự án chỉ hiện tiền của một nửa — đúng bằng lỗi "số tiền dự án ra sai".
	 * Cùng một công trình thì về cùng một mã.
	 */
	public static function ma_du_an_tu_ten_tab( $ten ) {
		$m = trim( preg_replace( '/^\s*DA\s+/iu', '', (string) $ten ) );
		$g = trim( preg_replace( '/\s*\(\d+\)\s*$/', '', $m ) );
		if ( $g !== '' ) { $m = $g; }
		return $m !== '' ? $m : trim( (string) $ten );
	}

	/** Tên tab có đuôi nhân bản "(2)" không — để báo lại là đã gộp vào dự án gốc. */
	public static function tab_nhan_ban( $ten ) {
		$m = trim( preg_replace( '/^\s*DA\s+/iu', '', (string) $ten ) );
		return (bool) preg_match( '/\s*\(\d+\)\s*$/', $m );
	}

	/** 6 ô đầu của dòng đầu tiên — để soi tab lạ chứa gì. */
	public static function xem_dong( $rows ) {
		if ( ! count( $rows ) ) { return ''; }
		$o = array_slice( (array) $rows[0], 0, 6 );
		foreach ( $o as $i => $v ) { $o[ $i ] = mb_substr( trim( (string) $v ), 0, 24 ); }
		return implode( ' | ', $o );
	}

	/** Tên tiếng Việt của loại tab, để in báo cáo. */
	public static function ten_loai( $loai ) {
		if ( strpos( $loai, 'CH_' ) === 0 ) { return 'cấu hình ' . $loai; }
		$m = array(
			'DonHang' => 'đơn vận hành', 'TD_Don' => 'đơn vận hành', 'TamUng' => 'tạm ứng theo cơ sở',
			'ChiPhi' => 'dòng chi của đơn', 'TD_ChiPhi' => 'dòng chi của đơn',
			'DA_Index' => 'danh mục dự án', 'DA_Sheet' => 'dòng hạng mục dự án', 'TD_DALine' => 'dòng hạng mục dự án',
			'MK_Don' => 'đơn marketing', 'TD_MKDon' => 'đơn marketing',
			'MK_Line' => 'hạng mục marketing', 'TD_MKLine' => 'hạng mục marketing',
			'BP_Index' => 'danh mục đợt Công tác/Setup', 'TD_BPIndex' => 'danh mục đợt Công tác/Setup',
			'BP_Sheet' => 'dòng chi Công tác/Setup', 'TD_BPLine' => 'dòng chi Công tác/Setup',
			'SoChi' => 'sổ chi phí', 'TD_SoChi' => 'sổ chi phí', 'NhatKy' => 'nhật ký',
		);
		return isset( $m[ $loai ] ) ? $m[ $loai ] : $loai;
	}

	/** Loại tab tự dò -> tên bảng trong từ điển. */
	public static function bang_cua_td( $loai ) {
		$m = array(
			'TD_Don' => 'don', 'TD_ChiPhi' => 'chiphi', 'TD_BPIndex' => 'bp_index', 'TD_BPLine' => 'bp_line',
			'TD_DALine' => 'da_line', 'TD_MKDon' => 'mk_don', 'TD_MKLine' => 'mk_line', 'TD_SoChi' => 'sochi',
		);
		return isset( $m[ $loai ] ) ? $m[ $loai ] : '';
	}

	public static function ten_bang( $b ) {
		$m = array(
			'don' => 'đơn vận hành', 'chiphi' => 'dòng chi của đơn', 'bp_index' => 'danh mục đợt Công tác/Setup',
			'bp_line' => 'dòng chi Công tác/Setup', 'da_line' => 'dòng hạng mục dự án',
			'mk_don' => 'đơn marketing', 'mk_line' => 'hạng mục marketing', 'sochi' => 'sổ chi phí',
		);
		return isset( $m[ $b ] ) ? $m[ $b ] : $b;
	}

	/** Tìm dự án theo tên, chưa có thì tạo (báo lại để người dùng biết mà kiểm). */
	private static function tim_hoac_tao_du_an( $ten, $cho_tao, &$tao ) {
		$ten = trim( (string) $ten );
		if ( $ten === '' ) { return ''; }
		foreach ( VHCP_DuAn::all_with_lines() as $d ) {
			if ( mb_strtolower( trim( (string) $d['ten'] ) ) === mb_strtolower( $ten ) ) { return (string) $d['ma_da']; }
		}
		if ( ! $cho_tao ) { return ''; }
		// Không suy được dự án thuộc loại nào từ tên tab -> tạm để "Setup lắp đặt",
		// báo lại để người nạp đổi nếu là Tháo dỡ (loại chỉ ảnh hưởng loại chi phí mặc định).
		$r = VHCP_DuAn::create_du_an( 'Setup lắp đặt', $ten, 'Nạp từ Google Sheet' );
		if ( empty( $r['success'] ) ) { return ''; }
		$tao[] = 'dự án "' . $ten . '" (tạm xếp loại Setup lắp đặt — sửa nếu là Tháo dỡ)';
		return isset( $r['maDA'] ) ? (string) $r['maDA'] : '';
	}

	/** Tạo các đợt Công tác/Setup mà dòng chi có tham chiếu nhưng danh mục chưa có. */
	private static function tao_dot_con_thieu( $rows, &$tao ) {
		$k = VHCP_Nap::khop( 'bp_line', $rows );
		if ( ! empty( $k['loi'] ) || ! isset( $k['hd']['ma'] ) ) { return; }
		$co = array();
		foreach ( VHCP_BP::all_with_lines() as $b ) { $co[ (string) $b['ma'] ] = 1; }
		$can = array();
		foreach ( $k['rows'] as $r ) {
			$m = VHCP_Nap::o( $r, $k['hd'], 'ma' );
			if ( $m !== '' && ! isset( $co[ $m ] ) ) { $can[ $m ] = 1; }
		}
		foreach ( array_keys( $can ) as $m ) {
			VHCP_BP::create_voi_ma( $m, 'Công tác', '(nạp từ Sheet) ' . $m, '', '', '', 'Nạp từ Google Sheet' );
			$tao[] = 'đợt công tác "' . $m . '" — CHƯA CÓ ĐỊA ĐIỂM, phải điền rồi bấm Gán mã';
		}
	}
}
