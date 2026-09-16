<?php
/**
 * ĐỐI SOÁT MOMO THEO TỪNG GIAO DỊCH — không phải theo tổng ngày.
 *
 * ==================================================================================================
 * 🔴 VÌ SAO PHẢI XUỐNG TỚI TỪNG GIAO DỊCH.
 * ==================================================================================================
 * Anh Thắng 16/09/2026: *"2 bên cũng hay lỗi do nhận tiền lỗi, nên cũng cần có bảng lệch giao dịch
 * CK/QR/MoMo giữa 2 bên"*.
 *
 * So tổng một ngày thì hai lỗi ngược chiều nhau triệt tiêu lẫn nhau: một giao dịch MoMo nhận mà máy
 * không ghi, cộng một giao dịch máy ghi mà MoMo không nhận — tổng vẫn khớp, mà thực tế là HAI cái
 * sai. Xuống tới từng mã giao dịch thì cả hai hiện ra.
 *
 * File anh Thắng gửi ("MoMo payments" xuất từ FABi) có sẵn thứ để ghép: cột **Mã đối tác** chính
 * là mã giao dịch của MoMo. Ghép theo mã ấy là chắc; không có mã thì mới lui về ghép mờ theo
 * ngày + số tiền + cơ sở.
 *
 * ==================================================================================================
 * 🔴 VÀ NÓ GIẢI LUÔN CHUYỆN "MÁY FABI ĐỔI CƠ SỞ".
 * ==================================================================================================
 * Anh Thắng: *"1 mã cửa MoMo sẽ gắn 1 cửa hàng trên FABi, nhưng sau khi đóng cửa, máy FABi đó đổi
 * sang cơ sở khác, thì MoMo vẫn nguyên, nhưng tên cửa hàng sẽ đổi sang cửa hàng mới… nếu trong
 * tháng có thay đổi cửa hàng, anh sẽ gửi file này lên để hệ thống tự rà"*.
 *
 * Khai một bảng "mã MoMo thuộc cửa hàng nào" rồi sửa tay mỗi lần dời máy là kiểu bảo trì không ai
 * theo nổi — dời máy giữa tháng một cái là cả tháng ấy gán sai, âm thầm.
 *
 * Nhưng CHÍNH FILE NÀY đã trả lời: mỗi trang tính mang tên cửa hàng FABi tại thời điểm xuất, và
 * mỗi giao dịch trong trang ấy thuộc về cửa hàng ấy. Nên mình không cần khai gì: giao dịch nào
 * thuộc cửa hàng nào là do file nói, theo đúng từng giao dịch, đúng từng ngày. Dời máy giữa tháng
 * thì nửa đầu tháng nằm ở trang cũ, nửa sau nằm ở trang mới — tự nó đúng.
 *
 * Và mỗi lần nạp, hệ CÒN HỌC ĐƯỢC bảng ghép "tên cửa hàng bên MoMo ↔ cơ sở FABi" từ những cặp
 * giao dịch đã khớp mã — để những dòng sao kê MoMo KHÔNG khớp được với máy vẫn biết đường về đúng
 * cơ sở. Xem `khh_dt_hoc_ghep_momo()`.
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function khh_dt_bang_momo() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_momo_pos';
}

function khh_dt_tao_bang_momo() {
	global $wpdb;
	$bang    = khh_dt_bang_momo();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ma_doi_tac varchar(80) NOT NULL DEFAULT '',
			ma_hd varchar(80) NOT NULL DEFAULT '',
			so_hd varchar(40) NOT NULL DEFAULT '',
			ngay date NOT NULL,
			gio tinyint(4) NOT NULL DEFAULT 0,
			cua_hang varchar(190) NOT NULL DEFAULT '',
			so_tien double NOT NULL DEFAULT 0,
			trang_thai varchar(60) NOT NULL DEFAULT '',
			loai varchar(10) NOT NULL DEFAULT 'dong',
			nap_luc datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_doi_tac (ma_doi_tac),
			KEY ngay (ngay),
			KEY cua_hang (cua_hang(120))
		) $charset;"
	);
}

/* ================================================================== *
 * Đọc file "MoMo payments" xuất từ FABi
 * ================================================================== */

/**
 * Mỗi trang tính một cửa hàng; trong trang có HAI khối nằm cạnh nhau:
 *   cột A–F  "Giao dịch QR Tĩnh"  — Mã hoá đơn · Số HĐ · Ngày bán · Mã đối tác · Tổng tiền · Trạng thái
 *   cột H–L  "Giao dịch QR Động"  — Mã hoá đơn · Số HĐ · Ngày bán · Mã đối tác · Tổng tiền
 *
 * ⚠️ ĐỌC CẢ HAI KHỐI. Quán nào cũng có thể dùng cả mã QR dán sẵn lẫn mã sinh theo đơn; bỏ một khối
 *    là mất nguyên một dòng tiền mà tổng vẫn trông hợp lý.
 */
function khh_dt_doc_momo_pos( $duong_dan ) {
	if ( ! khh_dt_doc_duoc_xlsx() ) {
		return new WP_Error( 'khh_dt_zip', 'Máy chủ thiếu ZipArchive hoặc XMLReader nên chưa đọc được .xlsx.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $duong_dan ) ) {
		return new WP_Error( 'khh_dt_zip', 'File .xlsx hỏng hoặc không mở được.' );
	}
	$ds_trang = khh_dt_ds_trang( $zip );
	if ( ! $ds_trang ) {
		$zip->close();
		return new WP_Error( 'khh_dt_sheet', 'Không đọc được danh sách trang tính.' );
	}
	$chuoi = khh_dt_chuoi_chung( $zip );

	$gop = array(
		'dong'    => array(),
		'so_trang' => 0,
		'bo_qua'  => 0,
		'quan'    => array(),
	);

	foreach ( $ds_trang as $trang ) {
		/* 🔴 EXCEL CẮT TÊN TRANG TÍNH Ở 31 KÝ TỰ. File thật của anh Thắng có trang
		   "FUNZONE CITY VŨNG TÀU ( Dịch Vụ" — thiếu đuôi "và Giải Trí K&H )" so với tên cơ sở
		   trong số liệu POS. Giữ nguyên tên cụt thì mọi giao dịch của quán ấy nằm riêng một cơ sở
		   không tồn tại, và mọi phép đối soát sau đó đều hụt. Ghép về tên đầy đủ ngay tại đây. */
		$ten_quan = trim( (string) $trang['ten'] );
		$ghep     = khh_dt_ghep_ten_gan( $ten_quan );
		if ( $ghep['diem'] >= 0.6 && '' !== $ghep['ten'] ) {
			$ten_quan = $ghep['ten'];
		}
		$tam      = khh_dt_rut_file( $zip, $trang['file'] );
		if ( ! $tam ) {
			continue;
		}
		$gop['so_trang']++;
		$hang = 0;
		khh_dt_doc_trang(
			$tam,
			$chuoi,
			function ( $d ) use ( &$gop, $ten_quan, &$hang ) {
				$hang++;
				if ( $hang <= 2 ) {
					return;                       // dòng tiêu đề khối và dòng tên cột
				}
				/* Hai khối: tĩnh bắt đầu ở cột 0, động ở cột 7. */
				foreach ( array( array( 0, 'tinh' ), array( 7, 'dong' ) ) as $khoi ) {
					list( $c, $loai ) = $khoi;
					$ma_dt = trim( (string) ( isset( $d[ $c + 3 ] ) ? $d[ $c + 3 ] : '' ) );
					$tien  = khh_dt_so( isset( $d[ $c + 4 ] ) ? $d[ $c + 4 ] : '' );
					$luc   = (string) ( isset( $d[ $c + 2 ] ) ? $d[ $c + 2 ] : '' );
					$ngay  = khh_dt_ngay( $luc );
					if ( '' === $ma_dt && $tien <= 0 ) {
						continue;                  // ô trống của khối bên kia
					}
					if ( '' === $ngay || $tien <= 0 ) {
						$gop['bo_qua']++;
						continue;
					}
					$gop['dong'][] = array(
						'ma_doi_tac' => $ma_dt,
						'ma_hd'      => trim( (string) ( isset( $d[ $c ] ) ? $d[ $c ] : '' ) ),
						'so_hd'      => trim( (string) ( isset( $d[ $c + 1 ] ) ? $d[ $c + 1 ] : '' ) ),
						'ngay'       => $ngay,
						'gio'        => khh_dt_gio( $luc ),
						'cua_hang'   => $ten_quan,
						'so_tien'    => $tien,
						'trang_thai' => trim( (string) ( isset( $d[ $c + 5 ] ) ? $d[ $c + 5 ] : '' ) ),
						'loai'       => $loai,
					);
					$gop['quan'][ $ten_quan ] = true;
				}
			}
		);
		wp_delete_file( $tam );
	}
	$zip->close();

	if ( ! $gop['dong'] ) {
		return new WP_Error(
			'khh_dt_momo_rong',
			'Đọc xong mà không có giao dịch nào. Anh xuất đúng bản "MoMo payments" của FABi giúp em.'
		);
	}
	$gop['quan'] = array_keys( $gop['quan'] );
	return $gop;
}

/**
 * Ghi vào kho, khoá theo Mã đối tác.
 *
 * ⚠️ NẠP LẠI THÌ GHI ĐÈ, KỂ CẢ CỘT CỬA HÀNG. Đó chính là cách hệ "tự rà" khi máy FABi dời cơ sở:
 *    anh Thắng nạp lại file, giao dịch cũ nằm ở trang tính mới và cột cửa hàng đổi theo. Nếu chỉ
 *    thêm dòng mới mà không sửa dòng cũ thì cái dời máy ấy không bao giờ ăn.
 */
function khh_dt_ghi_momo_pos( $ds ) {
	global $wpdb;
	$bang = khh_dt_bang_momo();
	$luc  = current_time( 'mysql' );
	$n    = 0;
	foreach ( (array) $ds as $r ) {
		$ma = (string) $r['ma_doi_tac'];
		if ( '' === $ma ) {
			/* Không có mã đối tác thì tự đúc một mã ổn định — để nạp lại không đẻ dòng mới. */
			$ma = 'tu-' . substr( md5( $r['ngay'] . '|' . $r['gio'] . '|' . $r['so_tien'] . '|' . $r['cua_hang'] . '|' . $r['ma_hd'] ), 0, 24 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $bang (ma_doi_tac,ma_hd,so_hd,ngay,gio,cua_hang,so_tien,trang_thai,loai,nap_luc)
				 VALUES (%s,%s,%s,%s,%d,%s,%f,%s,%s,%s)
				 ON DUPLICATE KEY UPDATE ma_hd=VALUES(ma_hd), so_hd=VALUES(so_hd), ngay=VALUES(ngay),
				 gio=VALUES(gio), cua_hang=VALUES(cua_hang), so_tien=VALUES(so_tien),
				 trang_thai=VALUES(trang_thai), loai=VALUES(loai), nap_luc=VALUES(nap_luc)",
				$ma,
				$r['ma_hd'],
				$r['so_hd'],
				$r['ngay'],
				$r['gio'],
				$r['cua_hang'],
				$r['so_tien'],
				$r['trang_thai'],
				$r['loai'],
				$luc
			)
		);
		$n++;
	}
	return $n;
}

function khh_dt_co_momo_pos() {
	global $wpdb;
	$bang = khh_dt_bang_momo();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" );
}

/* ================================================================== *
 * Đối soát từng giao dịch: máy POS ↔ sao kê MoMo
 * ================================================================== */

/** Giao dịch MoMo trên máy POS trong kỳ. */
function khh_dt_momo_pos_ds( $tu, $den ) {
	global $wpdb;
	$bang = khh_dt_bang_momo();
	$sql  = "SELECT ma_doi_tac, ma_hd, ngay, gio, cua_hang, so_tien, trang_thai FROM $bang WHERE 1=1";
	$args = array();
	if ( $tu ) {
		$sql   .= ' AND ngay >= %s';
		$args[] = $tu;
	}
	if ( $den ) {
		$sql   .= ' AND ngay <= %s';
		$args[] = $den;
	}
	$sql .= ' ORDER BY ngay, gio LIMIT 20000';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (array) ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ) );
	// phpcs:enable
}

/** Giao dịch trong sổ sao kê MoMo, đọc thẳng nguồn đã khai. */
function khh_dt_momo_sk_ds( $tu, $den ) {
	global $wpdb;
	$n = khh_dt_nguon_momo();
	if ( ! $n ) {
		return array();
	}
	$bang = (string) $n['bang'];
	$c    = (array) $n['cot'];
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	if ( empty( $c['ngay'] ) || empty( $c['so_tien'] ) ) {
		return array();
	}
	$ten  = ! empty( $c['nhan'] ) ? $c['nhan'] : ( ! empty( $c['noi_dung'] ) ? $c['noi_dung'] : '' );
	$ma   = ! empty( $c['ma_gd'] ) ? $c['ma_gd'] : '';
	$lay  = "`{$c['ngay']}` ngay, `{$c['so_tien']}` tien";
	$lay .= $ten ? ", `$ten` ten" : ", '' ten";
	$lay .= $ma ? ", `$ma` ma" : ", '' ma";
	$sql  = "SELECT $lay FROM `$bang` WHERE 1=1";
	$args = array();
	if ( $tu ) {
		$sql   .= " AND `{$c['ngay']}` >= %s";
		$args[] = $tu . ' 00:00:00';
	}
	if ( $den ) {
		$sql   .= " AND `{$c['ngay']}` <= %s";
		$args[] = $den . ' 23:59:59';
	}
	$sql .= ' LIMIT 20000';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (array) ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ) );
	// phpcs:enable
}

/**
 * Ghép hai bên và kể ra ba nhóm lệch.
 *
 * Ghép hai lượt:
 *   1. THEO MÃ — Mã đối tác của máy POS chính là mã giao dịch MoMo. Chắc chắn, không bàn.
 *   2. THEO NGÀY + SỐ TIỀN + CƠ SỞ — cho những dòng sổ MoMo không có mã, hoặc mã ghi khác kiểu.
 *      Mỗi dòng chỉ được ghép MỘT lần; hai giao dịch cùng ngày cùng số tiền thì ghép lần lượt,
 *      không nhân đôi.
 *
 * ⚠️ GIAO DỊCH LỖI / HUỶ Ở MÁY POS KHÔNG PHẢI "MOMO THIẾU". Cột Trạng thái của file FABi nói rõ;
 *    đếm riêng chúng ra, đừng dồn vào nhóm lệch rồi bắt người ta đi tìm một khoản tiền chưa từng
 *    tồn tại.
 */
function khh_dt_doi_soat_momo_gd( $tu, $den ) {
	$pos = khh_dt_momo_pos_ds( $tu, $den );
	$sk  = khh_dt_momo_sk_ds( $tu, $den );

	$theo_ma = array();
	foreach ( $sk as $i => $r ) {
		$m = trim( (string) $r['ma'] );
		if ( '' !== $m ) {
			$theo_ma[ $m ][] = $i;
		}
	}
	$da_dung = array();
	$khop    = array();
	$chi_pos = array();
	$lech    = array();
	$loi_pos = array();

	foreach ( $pos as $p ) {
		$tt = khh_dt_khong_dau( $p['trang_thai'] );
		if ( '' !== $tt && false === strpos( $tt, 'thanh cong' ) && false === strpos( $tt, 'success' ) ) {
			$loi_pos[] = $p;                      // máy ghi nhưng không thành công
			continue;
		}
		$m = trim( (string) $p['ma_doi_tac'] );
		$j = null;
		if ( '' !== $m && isset( $theo_ma[ $m ] ) ) {
			foreach ( $theo_ma[ $m ] as $k ) {
				if ( ! isset( $da_dung[ $k ] ) ) {
					$j = $k;
					break;
				}
			}
		}
		if ( null === $j ) {
			/* Lui về ghép mờ: cùng ngày, cùng số tiền, cùng cơ sở. */
			foreach ( $sk as $k => $r ) {
				if ( isset( $da_dung[ $k ] ) ) {
					continue;
				}
				if ( substr( (string) $r['ngay'], 0, 10 ) !== $p['ngay'] ) {
					continue;
				}
				if ( abs( khh_dt_so( $r['tien'] ) - (float) $p['so_tien'] ) >= 1 ) {
					continue;
				}
				$ch = khh_dt_ten_co_so_gan( (string) $r['ten'] );
				if ( '' !== $ch && $ch !== $p['cua_hang'] ) {
					continue;
				}
				$j = $k;
				break;
			}
		}
		if ( null === $j ) {
			$chi_pos[] = $p;
			continue;
		}
		$da_dung[ $j ] = true;
		$tien_sk       = khh_dt_so( $sk[ $j ]['tien'] );
		if ( abs( $tien_sk - (float) $p['so_tien'] ) >= 1 ) {
			$lech[] = array( 'pos' => $p, 'sk_tien' => $tien_sk );
		} else {
			$khop[] = $p;
		}
	}

	$chi_sk = array();
	foreach ( $sk as $k => $r ) {
		if ( isset( $da_dung[ $k ] ) ) {
			continue;
		}
		$chi_sk[] = array(
			'ngay' => substr( (string) $r['ngay'], 0, 16 ),
			'tien' => khh_dt_so( $r['tien'] ),
			'ten'  => (string) $r['ten'],
			'ma'   => (string) $r['ma'],
		);
	}

	return array(
		'so_pos'   => count( $pos ),
		'so_sk'    => count( $sk ),
		'so_khop'  => count( $khop ),
		'chi_pos'  => array_slice( $chi_pos, 0, 200 ),
		'so_chi_pos' => count( $chi_pos ),
		'tien_chi_pos' => array_sum( wp_list_pluck( $chi_pos, 'so_tien' ) ),
		'chi_sk'   => array_slice( $chi_sk, 0, 200 ),
		'so_chi_sk' => count( $chi_sk ),
		'tien_chi_sk' => array_sum( wp_list_pluck( $chi_sk, 'tien' ) ),
		'lech'     => array_slice( $lech, 0, 200 ),
		'so_lech'  => count( $lech ),
		'loi_pos'  => array_slice( $loi_pos, 0, 50 ),
		'so_loi_pos' => count( $loi_pos ),
	);
}

/**
 * HỌC BẢNG GHÉP "tên cửa hàng bên MoMo ↔ cơ sở FABi" từ những cặp đã khớp mã.
 *
 * 🔴 ĐÂY LÀ CÁCH HỆ "TỰ RÀ" KHI MÁY DỜI CƠ SỞ. Không khai tay bảng nào cả: cứ nạp lại file MoMo
 *    của FABi là những cặp khớp mã dạy lại cho hệ biết tên bên MoMo giờ thuộc quán nào.
 *
 * ⚠️ CHỈ HỌC KHI MỘT TÊN CHỈ TRỎ VỀ MỘT QUÁN trong kỳ vừa nạp. Tên nào trỏ về hai quán khác nhau
 *    (đúng cảnh dời máy giữa kỳ) thì KHÔNG học — vì học cái nào cũng sai một nửa. Lúc ấy giao dịch
 *    đã khớp mã vẫn đúng cơ sở theo file, chỉ những dòng sổ MoMo lẻ là chưa biết đường về, và
 *    chúng nằm sẵn ở nhóm "chỉ có bên MoMo" cho người nhìn.
 */
function khh_dt_hoc_ghep_momo( $tu, $den ) {
	$pos = khh_dt_momo_pos_ds( $tu, $den );
	$sk  = khh_dt_momo_sk_ds( $tu, $den );
	if ( ! $pos || ! $sk ) {
		return array( 'hoc' => 0, 'lan_can' => array() );
	}
	$quan_theo_ma = array();
	foreach ( $pos as $p ) {
		$m = trim( (string) $p['ma_doi_tac'] );
		if ( '' !== $m ) {
			$quan_theo_ma[ $m ] = (string) $p['cua_hang'];
		}
	}
	$ten_toi_quan = array();
	foreach ( $sk as $r ) {
		$m = trim( (string) $r['ma'] );
		$t = trim( (string) $r['ten'] );
		if ( '' === $m || '' === $t || ! isset( $quan_theo_ma[ $m ] ) ) {
			continue;
		}
		$ten_toi_quan[ $t ][ $quan_theo_ma[ $m ] ] = true;
	}
	$hoc     = get_option( 'khh_dt_ghep_momo_ten', array() );
	$hoc     = is_array( $hoc ) ? $hoc : array();
	$so      = 0;
	$lan_can = array();
	foreach ( $ten_toi_quan as $ten => $quan ) {
		if ( count( $quan ) > 1 ) {
			$lan_can[] = $ten;                    // một tên, hai quán — không học, xem khối chú thích
			continue;
		}
		$q = key( $quan );
		if ( ! isset( $hoc[ $ten ] ) || $hoc[ $ten ] !== $q ) {
			$hoc[ $ten ] = $q;
			$so++;
		}
	}
	update_option( 'khh_dt_ghep_momo_ten', $hoc, false );
	return array( 'hoc' => $so, 'lan_can' => $lan_can );
}

/** Tên bên MoMo -> cơ sở FABi, theo bảng đã học. */
function khh_dt_ghep_momo_hoc( $ten ) {
	$hoc = get_option( 'khh_dt_ghep_momo_ten', array() );
	$ten = trim( (string) $ten );
	return ( is_array( $hoc ) && isset( $hoc[ $ten ] ) ) ? (string) $hoc[ $ten ] : '';
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_rest_momo' );
function khh_dt_rest_momo() {
	register_rest_route(
		'khh-dt/v1',
		'/momo-gd',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_momo_gd',
			'permission_callback' => 'khh_dt_duoc_xem',
		)
	);
}

function khh_dt_rest_momo_gd( $req ) {
	$tu  = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'tu' ) );
	$den = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'den' ) );
	$ra  = khh_dt_doi_soat_momo_gd( $tu, $den );
	$ra['co_pos']  = (bool) khh_dt_co_momo_pos();
	$ra['co_sk']   = (bool) khh_dt_nguon_momo();
	return $ra;
}
