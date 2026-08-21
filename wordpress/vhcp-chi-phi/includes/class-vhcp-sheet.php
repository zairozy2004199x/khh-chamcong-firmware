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

		// 1) Tải từng tab: ưu tiên nhận theo TÊN TAB, không được thì dò theo TÊN CỘT
		$viec = array();
		foreach ( $tabs as $tab ) {
			$r = self::tai_tab( $id, $tab['gid'], $tab['ten'] );
			if ( ! empty( $r['loi'] ) ) {
				$viec[] = array( 'tab' => $tab['ten'], 'bo' => 'không tải được: ' . $r['loi'] );
				continue;
			}
			$rows = VHCP_Import::parse( $r['body'] );
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
			$viec[] = array( 'tab' => $tab['ten'], 'loai' => $loai, 'cachNhan' => $cach_nhan, 'diem' => $diem, 'rows' => $rows, 'csv' => $r['body'] );
		}

		// 2) Sắp thứ tự: cấu hình -> danh mục -> tạm ứng -> dòng chi -> nhật ký
		usort( $viec, function ( $a, $b ) {
			$x = isset( $a['loai'] ) ? VHCP_Sheet::uu_tien( $a['loai'] ) : 99;
			$y = isset( $b['loai'] ) ? VHCP_Sheet::uu_tien( $b['loai'] ) : 99;
			return $x <=> $y;
		} );

		// 3) Nạp
		$bc = array(); $tong = 0; $tong_bo = 0; $tong_thieu_ma = 0; $tao = array();

		foreach ( $viec as $v ) {
			if ( empty( $v['loai'] ) ) {
				$bc[] = array( 'tab' => $v['tab'], 'ketQua' => 'bỏ qua — ' . $v['bo'], 'dongDau' => isset( $v['dongDau'] ) ? $v['dongDau'] : '' );
				continue;
			}
			$loai = $v['loai'];
			$mo   = array( 'tab' => $v['tab'], 'bang' => self::ten_loai( $loai ), 'cachNhan' => $v['cachNhan'], 'cotKhop' => $v['diem'] );

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
							$tt = 0; $k0 = 0;
							foreach ( $k['rows'] as $rr ) {
								$nd0 = VHCP_Nap::o( $rr, $k['hd'], 'noi_dung' );
								if ( isset( $hm[ mb_strtolower( $nd0 ) ] ) ) { continue; }   // dòng tổng hợp
								$so = VHCP_Util::num( str_replace( array( '.', ' ' ), '', VHCP_Nap::o_so( $rr, $k, 'so_tien' ) ) );
								$tt += $so;
								if ( ! $so ) { $k0++; }
							}
							$mo['ketQua'] .= ' · TỔNG TIỀN sẽ nạp ' . number_format( $tt, 0, ',', '.' ) . 'đ';
							if ( $k0 ) { $mo['ketQua'] .= ' · ' . $k0 . ' dòng ra 0đ'; }
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
			$res = VHCP_Import::run( $loai, $v['csv'], array(
				'ma'      => $ma_chon,
				'coso'    => $cs_tab['coso'],
				'replace' => ! empty( $opts['replace'] ),
				'header'  => true,
			) );
			if ( empty( $res['success'] ) ) {
				$mo['ketQua'] = 'lỗi — ' . ( isset( $res['error'] ) ? $res['error'] : '' );
				$mo['dongDau'] = self::xem_dong( $v['rows'] );
				$bc[] = $mo;
				continue;
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
		$m = self::ma_du_an_tu_ten_tab( $ten_tab );
		// Bỏ đuôi "(2)" do nhân bản tab — nó không thuộc tên cơ sở. Mã dự án vẫn giữ
		// nguyên cả đuôi để còn truy được về đúng tab.
		$k = VHCP_Nap::kh( preg_replace( '/\s*\(\d+\)\s*$/', '', $m ) );
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

	/** Mã dự án lấy từ tên tab: bỏ chữ "DA " đứng đầu, giữ nguyên phần còn lại. */
	public static function ma_du_an_tu_ten_tab( $ten ) {
		$m = trim( preg_replace( '/^\s*DA\s+/iu', '', (string) $ten ) );
		return $m !== '' ? $m : trim( (string) $ten );
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
