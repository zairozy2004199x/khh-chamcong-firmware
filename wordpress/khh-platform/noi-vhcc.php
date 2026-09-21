<?php
/**
 * Nối thẳng với plugin Chấm Công (K&H) — vhcp-cham-cong — đang chạy cùng site.
 *
 * Trang chấm công của công ty chính là plugin đó, bảng `{prefix}vhcc_cham_cong`
 * nằm ngay trong cơ sở dữ liệu WordPress này. Mã nhân viên `ma_nv` bên ấy trùng
 * y Mã nhân sự bên nền tảng. Nên KHÔNG bắt ai khai gì: thấy bảng là đọc.
 *
 * Chỉ đọc. Không có câu lệnh ghi nào chạm vào bảng của plugin kia.
 *
 * Giá trị công lấy đúng luật của bên ấy (VHCC_Cham::phut_lam): số phút = giờ ra
 * trừ giờ vào; ra sớm hơn vào là dấu hiệu ghi sai → không tính, để người soát
 * nhìn thấy. Không tự trừ nghỉ trưa, không áp ca chuẩn của nền tảng — "chỉ lấy
 * giá trị bảng công thôi".
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KHH_VHCC_CO = 'khh_vhcc_co';

function khh_vhcc_bang( $ten ) {
	global $wpdb;
	return $wpdb->prefix . 'vhcc_' . $ten;
}

/** Plugin Chấm Công (K&H) có mặt không — nhìn bảng, không nhìn tên plugin. */
function khh_vhcc_co() {
	global $wpdb;
	$c = get_transient( KHH_VHCC_CO );
	if ( false !== $c ) {
		return (bool) $c;
	}
	$can = array( khh_vhcc_bang( 'cham_cong' ), khh_vhcc_bang( 'nhan_vien' ) );
	$co  = true;
	foreach ( $can as $t ) {
		$th = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ); // phpcs:ignore
		if ( $th !== $t ) {
			$co = false;
		}
	}
	set_transient( KHH_VHCC_CO, $co ? 1 : 0, 10 * MINUTE_IN_SECONDS );
	return $co;
}

/**
 * Bật hay tắt plugin nào cũng quên kết quả dò bảng, để vừa bật Chấm Công (K&H) là
 * nối ngay chứ không chờ hết 10 phút đệm; và vừa tắt là ngừng ngay, không đọc bảng ma.
 */
add_action( 'activated_plugin', 'khh_vhcc_quen_do' );
add_action( 'deactivated_plugin', 'khh_vhcc_quen_do' );
function khh_vhcc_quen_do() {
	delete_transient( KHH_VHCC_CO );
	delete_transient( 'khh_vhcc_coso' );
	if ( defined( 'KHH_CC_CACHE' ) ) {
		delete_transient( KHH_CC_CACHE );
	}
}

/** Giây trong ngày → "HH:MM". Bản dịch của VHCC_DB::hhmm. */
function khh_vhcc_hhmm( $giay ) {
	if ( null === $giay || '' === $giay ) {
		return '';
	}
	$g = ( (int) $giay ) % 86400;
	if ( $g < 0 ) {
		$g += 86400;
	}
	return sprintf( '%02d:%02d', intdiv( $g, 3600 ), intdiv( $g % 3600, 60 ) );
}

/** Số phút của một lượt — y hệt VHCC_Cham::phut_lam, kể cả chỗ ra < vào trả null. */
function khh_vhcc_phut( $vao, $ra ) {
	if ( null === $vao || '' === $vao || null === $ra || '' === $ra ) {
		return null;
	}
	$d = (int) $ra - (int) $vao;
	return $d < 0 ? null : (int) round( $d / 60 );
}

/** Nguồn ghi bên ấy → chữ hiện ở cột Phương thức. */
function khh_vhcc_phuong_thuc( $nguon ) {
	$m = array(
		'may'    => 'Máy chấm công',
		'bu'     => 'Chấm bù',
		'online' => 'Chấm công online',
		'csv'    => 'Nạp từ sổ',
	);
	$k = strtolower( trim( (string) $nguon ) );
	return isset( $m[ $k ] ) ? $m[ $k ] : ( '' === $k ? '' : ucfirst( $k ) );
}

/**
 * Cơ sở của từng người theo sổ nhân viên bên Chấm Công: mã → cửa hàng.
 * Dùng để chia Bảng công cơ sở mà không bắt ai gán tay 251 người.
 */
function khh_vhcc_coso_theo_ma() {
	global $wpdb;
	$rows = $wpdb->get_results( // phpcs:ignore
		'SELECT ma_nv, cua_hang FROM ' . khh_vhcc_bang( 'nhan_vien' ) . " WHERE cua_hang <> ''",
		ARRAY_A
	);
	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[ strtolower( trim( $r['ma_nv'] ) ) ] = trim( $r['cua_hang'] );
	}
	return $out;
}

/** Mã chạy song song: mã này ↔ mã kia của cùng một người (đã khai, không đoán). */
function khh_vhcc_ma_song_song() {
	global $wpdb;
	$t = khh_vhcc_bang( 'ma_song_song' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { // phpcs:ignore
		return array();
	}
	$rows = $wpdb->get_results( 'SELECT ma_a, ma_b FROM ' . $t, ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$a = strtolower( trim( $r['ma_a'] ) );
		$b = strtolower( trim( $r['ma_b'] ) );
		if ( '' !== $a && '' !== $b ) {
			$out[ $a ] = $b;
			$out[ $b ] = $a;
		}
	}
	return $out;
}

/**
 * Đọc bảng chấm công bên Chấm Công (K&H) thành bản ghi của nền tảng.
 *
 * @param int $thang Số tháng gần nhất.
 * @return array array( docs, stat )
 */
function khh_vhcc_doc_song( $thang = 3 ) {
	global $wpdb;
	list( $tu, $den ) = khh_cc_khoang( array( 'thang' => $thang ) );

	/* Hồ sơ bên nền tảng: mã → id. Có mã song song thì mã kia cũng trỏ về cùng người. */
	$theo_ma = array();
	foreach ( khh_get_coll( 'staff' ) as $id => $s ) {
		$code = isset( $s['code'] ) ? strtolower( trim( (string) $s['code'] ) ) : '';
		if ( '' !== $code ) {
			$theo_ma[ $code ] = $id;
		}
	}
	foreach ( khh_vhcc_ma_song_song() as $a => $b ) {
		if ( ! isset( $theo_ma[ $a ] ) && isset( $theo_ma[ $b ] ) ) {
			$theo_ma[ $a ] = $theo_ma[ $b ];
		}
	}

	$rows = $wpdb->get_results( // phpcs:ignore
		$wpdb->prepare(
			'SELECT ngay, ma_nv, hau_to, coso, gio_vao_giay, gio_ra_giay, nguon, ghi_chu FROM '
			. khh_vhcc_bang( 'cham_cong' )
			. ' WHERE ngay BETWEEN %s AND %s ORDER BY ngay, ma_nv, hau_to',
			$tu,
			$den
		),
		ARRAY_A
	);

	$stat = array(
		'rows'    => 0,
		'used'    => 0,
		'noStaff' => 0,
		'noDate'  => 0,
		'dupName' => 0,
		'people'  => array(),
		'lost'    => array(),
		'tu'      => $tu,
		'den'     => $den,
		'nguon'   => 'vhcc',
	);
	$days = array();

	foreach ( (array) $rows as $r ) {
		++$stat['rows'];
		$ma  = strtolower( trim( (string) $r['ma_nv'] ) );
		$sid = isset( $theo_ma[ $ma ] ) ? $theo_ma[ $ma ] : '';
		if ( '' === $sid ) {
			++$stat['noStaff'];
			$goc = trim( (string) $r['ma_nv'] );
			if ( '' !== $goc && count( $stat['lost'] ) < 20 && ! in_array( $goc, $stat['lost'], true ) ) {
				$stat['lost'][] = $goc;
			}
			continue;
		}
		$ngay = (string) $r['ngay'];
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) {
			++$stat['noDate'];
			continue;
		}
		$cyc = substr( $ngay, 0, 7 );
		$dd  = substr( $ngay, 8, 2 );
		if ( ! isset( $days[ $sid ][ $cyc ][ $dd ] ) ) {
			$days[ $sid ][ $cyc ][ $dd ] = array();
		}
		$o = &$days[ $sid ][ $cyc ][ $dd ];

		$vao = khh_vhcc_hhmm( $r['gio_vao_giay'] );
		$ra  = khh_vhcc_hhmm( $r['gio_ra_giay'] );
		if ( '' !== $vao && ( empty( $o['in'] ) || $vao < $o['in'] ) ) {
			$o['in'] = $vao;
		}
		if ( '' !== $ra && ( empty( $o['out'] ) || $ra > $o['out'] ) ) {
			$o['out'] = $ra;
		}
		/* Hàng chính và hàng -CD cùng ngày cộng phút lại: vẫn MỘT ngày công. */
		$phut = khh_vhcc_phut( $r['gio_vao_giay'], $r['gio_ra_giay'] );
		if ( null !== $phut && $phut > 0 ) {
			$o['m'] = ( isset( $o['m'] ) ? $o['m'] : 0 ) + $phut;
		}
		$ht = strtoupper( trim( (string) $r['hau_to'] ) );
		if ( '' !== $ht ) {
			$o['ca'] = empty( $o['ca'] ) ? $ht : $o['ca'] . '-' . $ht;
		}
		if ( ! empty( $r['coso'] ) && empty( $o['device'] ) ) {
			$o['device'] = trim( (string) $r['coso'] );
		}
		$pt = khh_vhcc_phuong_thuc( $r['nguon'] );
		if ( '' !== $pt && empty( $o['method'] ) ) {
			$o['method'] = $pt;
		}
		if ( ! empty( $r['ghi_chu'] ) && empty( $o['note'] ) ) {
			$o['note'] = sanitize_text_field( $r['ghi_chu'] );
		}
		unset( $o );
		++$stat['used'];
		$stat['people'][ $sid ] = true;
	}

	$docs = array();
	foreach ( $days as $sid => $cycs ) {
		foreach ( $cycs as $cyc => $dd ) {
			$docs[] = array(
				'id'      => $sid . '_' . str_replace( '-', '', $cyc ),
				'staffId' => $sid,
				'cycle'   => $cyc,
				'days'    => $dd,
			);
		}
	}
	$stat['people'] = count( $stat['people'] );
	$stat['docs']   = count( $docs );
	return array( $docs, $stat );
}

/**
 * Điền cơ sở còn trống trong hồ sơ nhân sự bằng cửa hàng bên Chấm Công — lúc TRẢ
 * về giao diện, không ghi vào hồ sơ. Người nào đã gán tay thì giữ nguyên.
 */
function khh_vhcc_dien_coso( $staff_docs ) {
	if ( ! khh_vhcc_co() ) {
		return $staff_docs;
	}
	$map = get_transient( 'khh_vhcc_coso' );
	if ( ! is_array( $map ) ) {
		$map = khh_vhcc_coso_theo_ma();
		set_transient( 'khh_vhcc_coso', $map, KHH_CC_NHIP );
	}
	foreach ( $staff_docs as &$s ) {
		if ( ! empty( $s['office'] ) ) {
			continue;
		}
		$code = isset( $s['code'] ) ? strtolower( trim( (string) $s['code'] ) ) : '';
		if ( '' !== $code && isset( $map[ $code ] ) ) {
			$s['office'] = $map[ $code ];
		}
	}
	unset( $s );
	return $staff_docs;
}

/**
 * Yêu cầu đổi lịch đang chờ bên Chấm Công (K&H) — chỉ đọc, để màn Lịch ca của nền
 * tảng nhắc quản lý sang bên ấy duyệt. Không duyệt thay: bảng của plugin kia là
 * của plugin kia.
 *
 * @return array|null array( n, ds, url ) hoặc null khi không có plugin / bảng.
 */
function khh_vhcc_doi_lich_cho() {
	global $wpdb;
	if ( ! khh_vhcc_co() ) {
		return null;
	}
	$t = khh_vhcc_bang( 'doi_lich_cv' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { // phpcs:ignore
		return null;
	}
	$c = get_transient( 'khh_vhcc_doilich' );
	if ( is_array( $c ) ) {
		return $c;
	}
	$n  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE trang_thai IN ('cho','', 'CHO', 'cho_duyet')" ); // phpcs:ignore
	$ds = $wpdb->get_results( // phpcs:ignore
		"SELECT ma_yc, ho_ten, coso, ngay, ca, doi_sang_ngay, luc_xin FROM $t WHERE trang_thai IN ('cho','', 'CHO', 'cho_duyet') ORDER BY luc_xin DESC LIMIT 20",
		ARRAY_A
	);
	$out = array(
		'n'   => $n,
		'ds'  => array_map(
			function ( $r ) {
				return array(
					'ma_yc'  => (string) $r['ma_yc'],
					'ho_ten' => (string) $r['ho_ten'],
					'coso'   => (string) $r['coso'],
					'ngay'   => (string) $r['ngay'],
					'ca'     => (string) $r['ca'],
					'sang'   => (string) $r['doi_sang_ngay'],
				);
			},
			(array) $ds
		),
		'url' => admin_url( 'admin.php?page=vhcc' ),
	);
	set_transient( 'khh_vhcc_doilich', $out, 2 * MINUTE_IN_SECONDS );
	return $out;
}
