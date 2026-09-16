<?php
/**
 * SAO KÊ NGÂN HÀNG — NGUỒN ĐỘC LẬP DUY NHẤT TRONG CẢ HỆ NÀY.
 *
 * ==================================================================================================
 * 🔴 VÌ SAO CỘT "THỰC NỘP" PHẢI LẤY TỪ ĐÂY, KHÔNG PHẢI CƠ SỞ GÕ VÀO.
 * ==================================================================================================
 * Đối soát file "báo cáo cơ sở" của Tàu Tân Phú với máy POS (01–14/09/2026) cho kết quả khớp
 * ĐÚNG 0 ĐỒNG suốt 14/14 ngày — vì báo cáo ấy chép ra từ chính máy POS. Hai con số cùng một
 * nguồn thì so với nhau mãi mãi bằng không, kể cả khi có thất thoát thật.
 *
 * Tiền thực nộp mà cũng để cơ sở tự gõ thì y hệt: ai giữ tiền, người ấy khai mình đã nộp bao
 * nhiêu. Còn sao kê là do NGÂN HÀNG ghi — không ai trong nhà sửa được. Nên từ bản này:
 *
 *   · cơ sở vẫn gõ "tiền thực nộp" (họ khai họ nộp bao nhiêu);
 *   · nhưng cột đối soát lấy số NGÂN HÀNG NHẬN ĐƯỢC;
 *   · và chỗ nào hai số ấy lệch nhau thì bày ra — đó mới là tín hiệu thật.
 *
 * ⚠️ DÒNG KHÔNG GÁN ĐƯỢC CƠ SỞ PHẢI HIỆN RA, KHÔNG ĐƯỢC BỎ IM. Mỗi dòng tiền bỏ sót là một
 *    khoản "chưa nộp" GIẢ — nó tố oan một cửa hàng trưởng đã nộp tiền thật. Bộ đếm ở tab Đối
 *    soát luôn nói rõ còn bao nhiêu dòng chưa gán.
 *
 * ⚠️ CHỈ LẤY TIỀN VÀO. Sao kê có cả tiền ra; cộng nhầm một khoản chi thành tiền nộp là báo cáo
 *    đẹp lên mà không ai nộp thêm đồng nào.
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function khh_dt_bang_sk() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_sao_ke';
}

function khh_dt_tao_bang_sk() {
	global $wpdb;
	$bang    = khh_dt_bang_sk();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ma_gd varchar(80) NOT NULL DEFAULT '',
			ngay date NOT NULL,
			gio tinyint(4) NOT NULL DEFAULT 0,
			ngay_tinh date NOT NULL,
			so_tien double NOT NULL DEFAULT 0,
			noi_dung text NOT NULL,
			tai_khoan varchar(60) NOT NULL DEFAULT '',
			cua_hang varchar(190) NOT NULL DEFAULT '',
			nap_luc datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_gd (ma_gd),
			KEY ngay_tinh (ngay_tinh),
			KEY cua_hang (cua_hang(120))
		) $charset;"
	);
}

/* ================================================================== *
 * Hai thứ phải khai một lần: giờ cắt và bảng nhận mặt cơ sở
 * ================================================================== */

/**
 * GIỜ CẮT — tiền nộp trước giờ này tính cho doanh thu NGÀY HÔM TRƯỚC.
 *
 * 🔴 Quán đóng cửa 22h, tiền mặt nằm trong két qua đêm, sáng hôm sau mới mang ra ngân hàng. Tính
 *    khoản ấy vào ngày nộp thì ngày bán hàng nào cũng "chưa nộp" đủ và ngày hôm sau nào cũng
 *    "nộp thừa" — cả bảng đối soát đỏ lòm mà không ai làm sai gì.
 */
function khh_dt_gio_cat() {
	$g = (int) get_option( 'khh_dt_gio_cat', 12 );
	return ( $g >= 0 && $g <= 23 ) ? $g : 12;
}

/** Ngày doanh thu mà một giao dịch thuộc về. */
function khh_dt_ngay_quy( $ngay, $gio ) {
	if ( '' === (string) $ngay ) {
		return '';
	}
	if ( (int) $gio < khh_dt_gio_cat() ) {
		return gmdate( 'Y-m-d', strtotime( $ngay . ' -1 day' ) );
	}
	return $ngay;
}

/**
 * Bảng nhận mặt cơ sở: [ [ 'khoa' => chuỗi, 'cua_hang' => tên cơ sở POS ], … ].
 *
 * Khoá là một mẩu chữ tìm trong NỘI DUNG chuyển khoản hoặc SỐ TÀI KHOẢN nhận. Cửa hàng trưởng
 * nộp tiền thường ghi "TUTU TAN PHU NOP 15/09" hoặc nộp vào đúng một số tài khoản riêng của
 * quán — hai đường đều nhận mặt được, và bảng này khai cả hai kiểu như nhau.
 */
function khh_dt_ghep_bank_ds() {
	$x = get_option( 'khh_dt_ghep_bank', array() );
	return is_array( $x ) ? $x : array();
}

function khh_dt_dat_ghep_bank( $ds ) {
	$sach = array();
	foreach ( (array) $ds as $d ) {
		$khoa = khh_dt_khong_dau( sanitize_text_field( (string) ( isset( $d['khoa'] ) ? $d['khoa'] : '' ) ) );
		$ch   = sanitize_text_field( (string) ( isset( $d['cua_hang'] ) ? $d['cua_hang'] : '' ) );
		if ( '' === $khoa || '' === $ch ) {
			continue;
		}
		$sach[] = array(
			'khoa'     => $khoa,
			'cua_hang' => $ch,
		);
	}
	update_option( 'khh_dt_ghep_bank', $sach, false );
	return $sach;
}

/**
 * Nhận mặt cơ sở của một giao dịch; '' nếu chưa nhận ra.
 *
 * ⚠️ KHOÁ DÀI THẮNG KHOÁ NGẮN. "TUTU TAN PHU" và "TUTU TAN AN" cùng bắt đầu bằng "TUTU TAN";
 *    xét theo thứ tự khai thì ai khai trước thắng, và một hôm nào đó tiền của quán này chạy sang
 *    sổ quán kia mà không ai biết vì sao.
 */
function khh_dt_doan_co_so( $noi_dung, $tai_khoan ) {
	$chuoi = khh_dt_khong_dau( (string) $noi_dung . ' ' . (string) $tai_khoan );
	$ds    = khh_dt_ghep_bank_ds();
	usort(
		$ds,
		function ( $a, $b ) {
			return strlen( $b['khoa'] ) - strlen( $a['khoa'] );
		}
	);
	foreach ( $ds as $d ) {
		if ( false !== strpos( $chuoi, $d['khoa'] ) ) {
			return (string) $d['cua_hang'];
		}
	}
	return '';
}

/* ================================================================== *
 * Đọc file sao kê
 * ================================================================== */

/** Tên cột các ngân hàng hay dùng, so khớp không dấu. */
function khh_dt_ten_cot_sk() {
	return array(
		'ngay'      => array( 'ngay giao dich', 'ngay hach toan', 'ngay gd', 'transaction date', 'ngay', 'thoi gian' ),
		'gio'       => array( 'gio giao dich', 'gio' ),
		'tien_vao'  => array( 'ghi co', 'phat sinh co', 'tien vao', 'so tien vao', 'credit' ),
		'tien_ra'   => array( 'ghi no', 'phat sinh no', 'tien ra', 'so tien ra', 'debit' ),
		'so_tien'   => array( 'so tien', 'amount', 'gia tri giao dich' ),
		'loai'      => array( 'loai giao dich', 'loai gd', 'loai', 'type' ),
		'noi_dung'  => array( 'noi dung chuyen khoan', 'noi dung', 'dien giai', 'mo ta', 'description', 'ghi chu' ),
		'tai_khoan' => array( 'so tai khoan', 'tai khoan nhan', 'tai khoan', 'account' ),
		'ma_gd'     => array( 'ma giao dich', 'ma tham chieu', 'so tham chieu', 'ma gd', 'reference', 'ref' ),
	);
}

/** Dò vị trí cột — cùng lối với `khh_dt_do_cot()` nhưng cho bảng tên của sao kê. */
function khh_dt_do_cot_sk( $hdr ) {
	$n    = array_map( 'khh_dt_khong_dau', $hdr );
	$dung = array();
	$map  = array();
	foreach ( khh_dt_ten_cot_sk() as $khoa => $mau ) {
		$map[ $khoa ] = -1;
		foreach ( array( true, false ) as $chat ) {          // khớp đúng cả chuỗi trước, khớp đầu chuỗi sau
			foreach ( $mau as $p ) {
				if ( $map[ $khoa ] > -1 ) {
					break;
				}
				foreach ( $n as $i => $h ) {
					if ( isset( $dung[ $i ] ) ) {
						continue;
					}
					if ( $chat ? ( $h === $p ) : ( 0 === strpos( $h, $p ) ) ) {
						$map[ $khoa ] = $i;
						$dung[ $i ]   = 1;
						break;
					}
				}
			}
		}
	}
	return $map;
}

/** Ô thứ $i của dòng, '' nếu không có. */
function khh_dt_o_dong( $dong, $map, $khoa ) {
	$i = isset( $map[ $khoa ] ) ? (int) $map[ $khoa ] : -1;
	return ( $i > -1 && isset( $dong[ $i ] ) ) ? $dong[ $i ] : '';
}

/**
 * Đọc một dòng sao kê thành bản ghi, hoặc null nếu bỏ.
 *
 * Ba đường nhận ra tiền VÀO, xét theo thứ tự tin cậy:
 *   1. có cột Ghi có / Tiền vào  -> số dương ở cột ấy;
 *   2. có cột Loại               -> chữ "co"/"vao"/"in"/"+";
 *   3. chỉ có một cột Số tiền    -> số ÂM là tiền ra, số dương là tiền vào.
 */
function khh_dt_dong_sao_ke( $dong, $map, &$gop ) {
	$ngay = khh_dt_ngay( khh_dt_o_dong( $dong, $map, 'ngay' ) );
	if ( '' === $ngay ) {
		$gop['bo_qua']++;
		return null;
	}

	$vao = null;
	if ( $map['tien_vao'] > -1 ) {
		$vao = khh_dt_so( khh_dt_o_dong( $dong, $map, 'tien_vao' ) );
		if ( $vao <= 0 ) {
			$gop['tien_ra']++;
			return null;
		}
	} elseif ( $map['loai'] > -1 ) {
		$l   = khh_dt_khong_dau( khh_dt_o_dong( $dong, $map, 'loai' ) );
		$tien = abs( khh_dt_so( khh_dt_o_dong( $dong, $map, 'so_tien' ) ) );
		$la_vao = ( false !== strpos( $l, 'co' ) || false !== strpos( $l, 'vao' ) || false !== strpos( $l, 'in' )
			|| false !== strpos( $l, '+' ) );
		if ( ! $la_vao ) {
			$gop['tien_ra']++;
			return null;
		}
		$vao = $tien;
	} else {
		$vao = khh_dt_so( khh_dt_o_dong( $dong, $map, 'so_tien' ) );
		$gop['doan_dau']++;                                   // file không nói vào/ra — phải báo lại
		if ( $vao <= 0 ) {
			$gop['tien_ra']++;
			return null;
		}
	}
	if ( $vao <= 0 ) {
		$gop['bo_qua']++;
		return null;
	}

	$noi_dung = trim( (string) khh_dt_o_dong( $dong, $map, 'noi_dung' ) );
	$tk       = trim( (string) khh_dt_o_dong( $dong, $map, 'tai_khoan' ) );
	$gio      = $map['gio'] > -1 ? khh_dt_gio( khh_dt_o_dong( $dong, $map, 'gio' ) )
		: khh_dt_gio( khh_dt_o_dong( $dong, $map, 'ngay' ) );
	$ma       = trim( (string) khh_dt_o_dong( $dong, $map, 'ma_gd' ) );
	if ( '' === $ma ) {
		/* Sao kê không có mã giao dịch thì tự đúc một mã từ chính nội dung dòng — để nạp lại
		   cùng một file không cộng dồn thành gấp đôi tiền nộp. */
		$ma = 'tu-' . substr( md5( $ngay . '|' . $gio . '|' . $vao . '|' . $noi_dung . '|' . $tk ), 0, 24 );
	}

	return array(
		'ma_gd'     => $ma,
		'ngay'      => $ngay,
		'gio'       => $gio,
		'ngay_tinh' => khh_dt_ngay_quy( $ngay, $gio ),
		'so_tien'   => $vao,
		'noi_dung'  => $noi_dung,
		'tai_khoan' => $tk,
		'cua_hang'  => khh_dt_doan_co_so( $noi_dung, $tk ),
	);
}

/** Đọc cả file sao kê (.xlsx / .csv) thành danh sách bản ghi. */
function khh_dt_doc_sao_ke( $duong_dan, $ten_file = '' ) {
	$duoi = strtolower( pathinfo( $ten_file ? $ten_file : $duong_dan, PATHINFO_EXTENSION ) );
	$gop  = array(
		'dong'     => array(),
		'so_dong'  => 0,
		'bo_qua'   => 0,
		'tien_ra'  => 0,
		'doan_dau' => 0,
		'trang'    => '',
	);
	$map  = null;
	$hang = 0;

	$xu_ly = function ( $dong ) use ( &$gop, &$map, &$hang ) {
		$hang++;
		if ( null === $map ) {
			$kd = array_map( 'khh_dt_khong_dau', $dong );
			$co_ngay = false;
			$co_tien = false;
			foreach ( $kd as $h ) {
				foreach ( array( 'ngay', 'thoi gian', 'transaction date' ) as $p ) {
					if ( 0 === strpos( $h, $p ) ) {
						$co_ngay = true;
					}
				}
				foreach ( array( 'so tien', 'ghi co', 'phat sinh co', 'tien vao', 'credit', 'amount' ) as $p ) {
					if ( 0 === strpos( $h, $p ) ) {
						$co_tien = true;
					}
				}
			}
			if ( $co_ngay && $co_tien ) {
				$map = khh_dt_do_cot_sk( $dong );
			} elseif ( $hang > 25 ) {
				/* Sao kê ngân hàng hay có cả chục dòng đầu trang (tên chủ tài khoản, kỳ sao kê,
				   số dư đầu kỳ) nên dò rộng hơn file POS. */
				$map = false;
			}
			return;
		}
		if ( false === $map ) {
			return;
		}
		$gop['so_dong']++;
		$r = khh_dt_dong_sao_ke( $dong, $map, $gop );
		if ( $r ) {
			$gop['dong'][] = $r;
		}
	};

	if ( 'csv' === $duoi || 'tsv' === $duoi || 'txt' === $duoi ) {
		$f = fopen( $duong_dan, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $f ) {
			return new WP_Error( 'khh_dt_sk', 'Không mở được file sao kê.' );
		}
		$dau = ( 'tsv' === $duoi ) ? "\t" : ',';
		$bom = fread( $f, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $f );
		}
		/* ⚠️ TRUYỀN ĐỦ CẢ `$escape`. PHP 8.4 kêu Deprecated nếu thiếu, và hosting nào bật
		   `display_errors` thì dòng cảnh báo ấy in thẳng vào giữa JSON — giao diện nhận được
		   một chuỗi không phải JSON rồi báo "not valid JSON", đúng lỗi đã cắn ngày 01/09/2026.
		   Giữ nguyên `'\\'` (mặc định cũ) để cách đọc file không đổi. */
		while ( false !== ( $d = fgetcsv( $f, 0, $dau, '"', '\\' ) ) ) {
			if ( null === $d || ( 1 === count( $d ) && null === $d[0] ) ) {
				continue;
			}
			$xu_ly( $d );
		}
		fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	} else {
		if ( ! khh_dt_doc_duoc_xlsx() ) {
			return new WP_Error( 'khh_dt_zip', 'Máy chủ thiếu ZipArchive hoặc XMLReader nên chưa đọc được .xlsx. Anh tải sao kê bản CSV rồi nạp lại.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $duong_dan ) ) {
			return new WP_Error( 'khh_dt_zip', 'File .xlsx hỏng hoặc không mở được.' );
		}
		$ds = khh_dt_ds_trang( $zip );
		if ( ! $ds ) {
			$zip->close();
			return new WP_Error( 'khh_dt_sheet', 'Không đọc được danh sách trang tính trong file.' );
		}
		$chon         = $ds[0];
		$gop['trang'] = $chon['ten'];
		$chuoi        = khh_dt_chuoi_chung( $zip );
		$tam          = khh_dt_rut_file( $zip, $chon['file'] );
		$zip->close();
		if ( ! $tam ) {
			return new WP_Error( 'khh_dt_sheet', 'Không rút được trang tính ra khỏi file nén.' );
		}
		khh_dt_doc_trang( $tam, $chuoi, $xu_ly );
		wp_delete_file( $tam );
	}

	if ( ! $map ) {
		return new WP_Error(
			'khh_dt_sk_cot',
			'Không tìm thấy dòng tên cột trong sao kê. Em cần một cột ngày (Ngày giao dịch) và một cột tiền '
				. '(Ghi có / Số tiền). Anh tải bản sao kê dạng bảng của ngân hàng giúp em.'
		);
	}
	if ( ! $gop['dong'] ) {
		return new WP_Error( 'khh_dt_sk_rong', 'Đọc xong mà không có dòng tiền vào nào. Kiểm tra lại kỳ sao kê.' );
	}
	return $gop;
}

/** Ghi vào kho, không cộng dồn khi nạp lại cùng một file (khoá theo mã giao dịch). */
function khh_dt_ghi_sao_ke( $ds ) {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	$luc  = current_time( 'mysql' );
	$n    = 0;
	foreach ( (array) $ds as $r ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $bang (ma_gd,ngay,gio,ngay_tinh,so_tien,noi_dung,tai_khoan,cua_hang,nap_luc)
				 VALUES (%s,%s,%d,%s,%f,%s,%s,%s,%s)
				 ON DUPLICATE KEY UPDATE ngay=VALUES(ngay), gio=VALUES(gio), ngay_tinh=VALUES(ngay_tinh),
				 so_tien=VALUES(so_tien), noi_dung=VALUES(noi_dung), tai_khoan=VALUES(tai_khoan),
				 cua_hang=VALUES(cua_hang), nap_luc=VALUES(nap_luc)",
				$r['ma_gd'],
				$r['ngay'],
				$r['gio'],
				$r['ngay_tinh'],
				$r['so_tien'],
				$r['noi_dung'],
				$r['tai_khoan'],
				$r['cua_hang'],
				$luc
			)
		);
		$n++;
	}
	return $n;
}

/**
 * Gán lại cơ sở cho mọi dòng — chạy sau mỗi lần sửa bảng nhận mặt hoặc đổi giờ cắt.
 *
 * ⚠️ Không có hàm này thì khai thêm một khoá chỉ ăn cho những lần nạp SAU, còn mấy tháng sao kê
 *    đã nạp vẫn nằm im không cơ sở — và người khai tưởng mình vừa sửa xong.
 */
function khh_dt_gan_lai_sao_ke() {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results( "SELECT id, ngay, gio, noi_dung, tai_khoan, cua_hang, ngay_tinh FROM $bang", ARRAY_A );
	$doi = 0;
	foreach ( $ds as $r ) {
		$ch = khh_dt_doan_co_so( $r['noi_dung'], $r['tai_khoan'] );
		$nt = khh_dt_ngay_quy( $r['ngay'], $r['gio'] );
		if ( $ch === (string) $r['cua_hang'] && $nt === (string) $r['ngay_tinh'] ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$bang,
			array(
				'cua_hang'  => $ch,
				'ngay_tinh' => $nt,
			),
			array( 'id' => (int) $r['id'] )
		);
		$doi++;
	}
	return $doi;
}

/** Tiền ngân hàng thật sự nhận được: [ 'ngay|cơ sở' => tổng ]. */
function khh_dt_nop_bank( $tu = '', $den = '' ) {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	$sql  = "SELECT ngay_tinh, cua_hang, SUM(so_tien) t FROM $bang WHERE cua_hang<>''";
	$args = array();
	if ( $tu ) {
		$sql   .= ' AND ngay_tinh >= %s';
		$args[] = $tu;
	}
	if ( $den ) {
		$sql   .= ' AND ngay_tinh <= %s';
		$args[] = $den;
	}
	$sql .= ' GROUP BY ngay_tinh, cua_hang';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	// phpcs:enable
	$ra = array();
	foreach ( (array) $ds as $r ) {
		$ra[ $r['ngay_tinh'] . '|' . $r['cua_hang'] ] = (float) $r['t'];
	}
	return $ra;
}

/** Còn bao nhiêu dòng tiền chưa gán được cơ sở, và tổng bao nhiêu tiền. */
function khh_dt_sk_chua_gan() {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$r = $wpdb->get_row( "SELECT COUNT(*) n, COALESCE(SUM(so_tien),0) t FROM $bang WHERE cua_hang=''", ARRAY_A );
	return array(
		'so_dong' => $r ? (int) $r['n'] : 0,
		'so_tien' => $r ? (float) $r['t'] : 0,
	);
}

function khh_dt_co_sao_ke() {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" );
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_rest_sk' );
function khh_dt_rest_sk() {
	register_rest_route(
		'khh-dt/v1',
		'/sao-ke',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_sk_xem',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/sao-ke-ghep',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_sk_ghep',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/sao-ke-xoa',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_sk_xoa',
			'permission_callback' => 'khh_dt_duoc_nap',
		)
	);
}

function khh_dt_rest_sk_xem() {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$bien = $wpdb->get_row( "SELECT MIN(ngay) tu, MAX(ngay) den, COUNT(*) n, COALESCE(SUM(so_tien),0) t FROM $bang", ARRAY_A );
	$chua = $wpdb->get_results(
		"SELECT id, ngay, so_tien, noi_dung, tai_khoan FROM $bang WHERE cua_hang='' ORDER BY ngay DESC, id DESC LIMIT 80",
		ARRAY_A
	);
	$theo_ch = $wpdb->get_results(
		"SELECT cua_hang, COUNT(*) n, SUM(so_tien) t FROM $bang WHERE cua_hang<>'' GROUP BY cua_hang ORDER BY t DESC",
		ARRAY_A
	);
	// phpcs:enable
	return array(
		'tu_ngay'  => $bien && $bien['tu'] ? $bien['tu'] : '',
		'den_ngay' => $bien && $bien['den'] ? $bien['den'] : '',
		'so_dong'  => $bien ? (int) $bien['n'] : 0,
		'tong'     => $bien ? (float) $bien['t'] : 0,
		'chua_gan' => $chua ? $chua : array(),
		'theo_ch'  => $theo_ch ? $theo_ch : array(),
		'ghep'     => khh_dt_ghep_bank_ds(),
		'gio_cat'  => khh_dt_gio_cat(),
		'cua_hang' => khh_dt_ds_cua_hang(),
	);
}

function khh_dt_rest_sk_ghep( $req ) {
	$ds = $req->get_param( 'ghep' );
	if ( is_string( $ds ) ) {
		$ds = json_decode( $ds, true );
	}
	if ( ! is_array( $ds ) ) {
		return new WP_Error( 'khh_dt_sk', 'Không đọc được bảng nhận mặt gửi lên.', array( 'status' => 400 ) );
	}
	khh_dt_dat_ghep_bank( $ds );
	$gio = $req->get_param( 'gio_cat' );
	if ( null !== $gio && '' !== $gio ) {
		$g = (int) $gio;
		update_option( 'khh_dt_gio_cat', ( $g >= 0 && $g <= 23 ) ? $g : 12, false );
	}
	$doi = khh_dt_gan_lai_sao_ke();
	$ra  = khh_dt_rest_sk_xem();
	$ra['da_gan_lai'] = $doi;
	return $ra;
}

function khh_dt_rest_sk_xoa() {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "TRUNCATE TABLE $bang" );
	return array( 'ok' => true );
}
