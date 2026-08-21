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
			$tmp = wp_tempnam( 'vhcp-sheet' );
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
			foreach ( $ten as $x ) { $out[] = array( 'gid' => '', 'ten' => $x ); }   // không có gid -> tải theo tên
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
		$cach = 'gõ tay';
		$tabs = array();
		if ( count( $ten_tay ) ) {
			foreach ( $ten_tay as $t ) { $tabs[] = array( 'gid' => '', 'ten' => $t ); }
		} else {
			$lk = self::liet_ke_tab( $id );
			if ( empty( $lk['tabs'] ) ) { return VHCP_Util::err( isset( $lk['loi'] ) ? $lk['loi'] : 'Không đọc được danh sách tab' ); }
			$tabs = $lk['tabs'];
			$cach = isset( $lk['cach'] ) ? $lk['cach'] : '';
		}

		// 1) Tải từng tab, đoán bảng đích
		$viec = array();
		foreach ( $tabs as $tab ) {
			if ( count( $chi ) && ! isset( $chi[ VHCP_Nap::kh( $tab['ten'] ) ] ) ) { continue; }
			$r = self::tai_tab( $id, $tab['gid'], $tab['ten'] );
			if ( ! empty( $r['loi'] ) ) {
				$viec[] = array( 'tab' => $tab['ten'], 'bo' => 'không tải được: ' . $r['loi'] );
				continue;
			}
			$rows = VHCP_Import::parse( $r['body'] );
			if ( ! count( $rows ) ) { $viec[] = array( 'tab' => $tab['ten'], 'bo' => 'tab trống' ); continue; }
			$doan = VHCP_Nap::doan_bang( $rows );
			if ( $doan['bang'] === '' ) {
				$viec[] = array( 'tab' => $tab['ten'], 'bo' => 'không nhận ra là bảng gì (khớp được ' . (int) $doan['diem'] . ' cột)' );
				continue;
			}
			$viec[] = array( 'tab' => $tab['ten'], 'bang' => $doan['bang'], 'diem' => $doan['diem'], 'rows' => $rows, 'csv' => $r['body'] );
		}

		// 2) Sắp thứ tự: danh mục trước, dòng chi sau
		$uu_tien = array_flip( VHCP_Nap::cac_bang() );
		usort( $viec, function ( $a, $b ) use ( $uu_tien ) {
			$x = isset( $a['bang'], $uu_tien[ $a['bang'] ] ) ? $uu_tien[ $a['bang'] ] : 99;
			$y = isset( $b['bang'], $uu_tien[ $b['bang'] ] ) ? $uu_tien[ $b['bang'] ] : 99;
			return $x <=> $y;
		} );

		// 3) Nạp
		$loai_theo_bang = array(
			'don'      => 'TD_Don',
			'chiphi'   => 'TD_ChiPhi',
			'bp_index' => 'TD_BPIndex',
			'bp_line'  => 'TD_BPLine',
			'da_line'  => 'TD_DALine',
			'mk_don'   => 'TD_MKDon',
			'mk_line'  => 'TD_MKLine',
			'sochi'    => 'TD_SoChi',
		);
		$bc = array(); $tong = 0; $tong_bo = 0; $tong_thieu_ma = 0; $tao = array();

		foreach ( $viec as $v ) {
			if ( empty( $v['bang'] ) ) { $bc[] = array( 'tab' => $v['tab'], 'ketQua' => 'bỏ qua — ' . $v['bo'] ); continue; }
			$bang = $v['bang'];
			$mo   = array( 'tab' => $v['tab'], 'bang' => $bang, 'cotKhop' => $v['diem'] );

			if ( $thu ) {
				$k = VHCP_Nap::khop( $bang, $v['rows'] );
				$mo['soDong']   = count( $k['rows'] );
				$mo['cotThieu'] = $k['thieu'];
				$mo['cotLa']    = $k['la'];
				$mo['ketQua']   = 'sẽ nạp ' . count( $k['rows'] ) . ' dòng vào ' . self::ten_bang( $bang );
				$bc[] = $mo;
				continue;
			}

			// Tab dự án kỹ thuật: tên tab chính là tên dự án -> tự tạo dự án nếu chưa có
			$ma_chon = '';
			if ( $bang === 'da_line' ) {
				$k = VHCP_Nap::khop( $bang, $v['rows'] );
				if ( ! isset( $k['hd']['ma_da'] ) ) {
					$ten_da = trim( preg_replace( '/^\s*DA\s+/iu', '', $v['tab'] ) );
					$ma_chon = self::tim_hoac_tao_du_an( $ten_da, $tao_cha, $tao );
					if ( $ma_chon === '' ) {
						$bc[] = array( 'tab' => $v['tab'], 'ketQua' => 'bỏ qua — chưa có dự án "' . $ten_da . '" và đang tắt tự tạo dòng cha' );
						continue;
					}
				}
			}
			// Dòng chi công tác: mã chuyến phải có trong danh mục đợt -> tạo trước cho đủ
			if ( $bang === 'bp_line' && $tao_cha ) {
				self::tao_dot_con_thieu( $v['rows'], $tao );
			}

			$res = VHCP_Import::run( $loai_theo_bang[ $bang ], $v['csv'], array( 'ma' => $ma_chon, 'replace' => ! empty( $opts['replace'] ) ) );
			if ( empty( $res['success'] ) ) {
				$mo['ketQua'] = 'lỗi — ' . ( isset( $res['error'] ) ? $res['error'] : '' );
				$bc[] = $mo;
				continue;
			}
			$mo['napDuoc'] = (int) $res['inserted'];
			$mo['boQua']   = (int) $res['skipped'];
			$mo['thieuMa'] = isset( $res['thieuMa'] ) ? (int) $res['thieuMa'] : 0;
			$mo['cotThieu'] = isset( $res['cotThieu'] ) ? $res['cotThieu'] : array();
			$mo['cotLa']    = isset( $res['cotLa'] ) ? $res['cotLa'] : array();
			$mo['moCoi']    = isset( $res['chuaCoCha'] ) ? $res['chuaCoCha'] : array();
			$mo['ketQua']   = 'nạp ' . $mo['napDuoc'] . ' dòng vào ' . self::ten_bang( $bang );
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
