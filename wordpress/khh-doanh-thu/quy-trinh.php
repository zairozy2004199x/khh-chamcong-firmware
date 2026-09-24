<?php
/**
 * QUY TRÌNH BÁO CÁO CƠ SỞ HẰNG NGÀY — CHẠY TỰ ĐỘNG.
 *
 * Anh Thắng 24/09/2026: *"Làm quy trình báo cáo hằng ngày tự động"* — *"Báo cáo cơ sở thôi"*.
 *
 * Mỗi ngày bán hàng D đi qua đúng bốn bước, hệ TỰ theo dõi từng bước cho từng cơ sở:
 *
 *   1. Số máy POS về        — FABi gửi thư 08:00, hộp thư tự lấy 08:02 (`hop-thu.php`).
 *   2. Cơ sở khai           — cửa hàng trưởng mở tab Nhập báo cáo: soát hàng bán, đếm két, khách vào.
 *   3. Sổ kho               — nhập / hàng huỷ / hàng tồn còn của ngày ấy (`kho.php`).
 *   4. Chốt                 — bấm "Lưu và chốt ngày"; kế toán thấy ở Đối soát.
 *
 * Hạn: ngày D phải chốt trước `han` (mặc định 10:00) của ngày D+1. Đến giờ hạn, lịch hằng ngày
 * chạy: tổng hợp cơ sở nào đã chốt, đã lưu, chưa nộp, chưa có số máy; ghi nhật ký và gửi một thư
 * tổng hợp cho văn phòng (ô "Thư tổng hợp gửi tới", nhiều địa chỉ cách nhau dấu phẩy).
 *
 * 🔴 KHÔNG TỰ ĐIỀN SỐ CHO CƠ SỞ. Tiền két đếm được, khách đếm ở cửa là số NGƯỜI đếm; hệ lên sẵn
 *    bản "nháp" bằng số máy là kế toán mở Đối soát thấy lệch 0 tăm tắp trong khi chẳng ai đếm gì —
 *    đúng cái sai mà cả plugin này tránh ("ô trống khác 0"). Tự động ở đây là: THEO DÕI, NHẮC,
 *    TỔNG HỢP — không bịa số.
 *
 * 🔴 WP-CRON CHỈ CHẠY KHI CÓ NGƯỜI MỞ TRANG — cùng nỗi khổ với hộp thư; hosting phải gọi
 *    `wp-cron.php` định kỳ. Màn Quản trị nói ra điều này ở khối hộp thư, và khối này bày "lượt sau".
 */

defined( 'ABSPATH' ) || exit;

const KHH_DT_QT_OPT  = 'khh_dt_quy_trinh';
const KHH_DT_QT_NHAT = 'khh_dt_quy_trinh_nhat_ky';
const KHH_DT_QT_MOC  = 'khh_dt_cron_quy_trinh';

/* ================================================================== *
 * Cấu hình
 * ================================================================== */

function khh_dt_qt_mac_dinh() {
	return array(
		'bat'   => true,       // theo dõi + lịch tổng hợp hằng ngày
		'han'   => '10:00',    // ngày D phải chốt trước giờ này của ngày D+1
		'email' => '',         // thư tổng hợp gửi tới (nhiều địa chỉ, dấu phẩy); trống = chỉ ghi nhật ký
		'lui'   => 7,          // danh sách việc nhìn lùi bao nhiêu ngày
	);
}

function khh_dt_qt_cf() {
	$c = get_option( KHH_DT_QT_OPT, array() );
	$c = is_array( $c ) ? $c : array();
	$c = array_merge( khh_dt_qt_mac_dinh(), $c );
	if ( ! khh_dt_qt_gio_hop_le( $c['han'] ) ) {
		$c['han'] = '10:00';
	}
	$c['lui'] = max( 1, min( 31, (int) $c['lui'] ) );
	return $c;
}

/** 'HH:MM' đúng nghĩa: 00–23 giờ, 00–59 phút. '25:99' khớp mẫu chữ số nhưng không phải giờ. */
function khh_dt_qt_gio_hop_le( $h ) {
	return (bool) preg_match( '~^([01]?\d|2[0-3]):[0-5]\d$~', (string) $h );
}

/** Danh sách địa chỉ hợp lệ trong ô email (bỏ địa chỉ sai, bỏ trùng). */
function khh_dt_qt_email_ds( $chuoi ) {
	$ra = array();
	foreach ( preg_split( '~[,;\s]+~', (string) $chuoi ) as $e ) {
		$e = trim( $e );
		if ( '' === $e ) {
			continue;
		}
		$k = strtolower( $e );
		if ( isset( $ra[ $k ] ) ) {
			continue;   // trùng (không phân biệt hoa thường) -> giữ địa chỉ gõ trước
		}
		if ( function_exists( 'is_email' ) ? is_email( $e ) : filter_var( $e, FILTER_VALIDATE_EMAIL ) ) {
			$ra[ $k ] = $e;
		}
	}
	return array_values( $ra );
}

/* ================================================================== *
 * Thời gian — mọi phép tính ngày/giờ theo MÚI GIỜ CỦA SITE, và nhận `$bay_gio` để bài thử ép được
 * ================================================================== */

/** 'Y-m-d' của hôm nay theo múi giờ site. */
function khh_dt_qt_hom_nay( $bay_gio = null ) {
	$d = new DateTime( '@' . ( null === $bay_gio ? time() : (int) $bay_gio ) );
	$d->setTimezone( wp_timezone() );
	return $d->format( 'Y-m-d' );
}

/** Cộng/trừ ngày trên chuỗi 'Y-m-d'. */
function khh_dt_qt_cong_ngay( $ngay, $so ) {
	$d = new DateTime( $ngay . ' 00:00:00', wp_timezone() );
	$d->modify( ( $so >= 0 ? '+' : '' ) . (int) $so . ' day' );
	return $d->format( 'Y-m-d' );
}

/**
 * Mốc HẠN CHỐT của ngày bán hàng $ngay: giờ `han` của ngày kế tiếp, theo múi giờ site.
 *
 * @return int Unix time.
 */
function khh_dt_qt_han_ts( $ngay, $han = null ) {
	$han = null === $han ? khh_dt_qt_cf()['han'] : $han;
	if ( ! preg_match( '~^(\d{1,2}):(\d{2})$~', (string) $han, $m ) ) {
		$m = array( '', '10', '00' );
	}
	$d = new DateTime( $ngay . ' 00:00:00', wp_timezone() );
	$d->modify( '+1 day' );
	$d->setTime( max( 0, min( 23, (int) $m[1] ) ), max( 0, min( 59, (int) $m[2] ) ), 0 );
	return $d->getTimestamp();
}

/* ================================================================== *
 * Tình trạng một ngày × một cơ sở
 * ================================================================== */

/** Ngày này cơ sở này đã lưu sổ kho chưa (có dòng nào trong bảng kho). */
function khh_dt_qt_kho_da_luu( $ngay, $cua_hang ) {
	global $wpdb;
	if ( ! function_exists( 'khh_dt_bang_kho' ) ) {
		return null;   // không có module kho -> không kết luận
	}
	$bang = khh_dt_bang_kho();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return null;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $bang WHERE ngay = %s AND co_so = %s", $ngay, $cua_hang ) ) > 0;
}

/**
 * Tình trạng của ngày $ngay tại $cua_hang.
 *
 * @return array {
 *   ngay, cua_hang,
 *   trang_thai : 'chua_fabi' | 'chua_nop' | 'da_luu' | 'da_chot',
 *   buoc       : [ fabi => bool, khai => bool, kho => bool|null, chot => bool ],
 *   qua_han    : bool  (chưa chốt mà đã qua giờ hạn),
 *   han        : 'Y-m-d H:i' giờ site,
 *   nguoi, sua_luc, doanh_thu, lech_ket (đếm két − tiền mặt máy; null khi chưa đếm), mon_lech (số món)
 * }
 */
function khh_dt_qt_tinh_trang( $ngay, $cua_hang, $bay_gio = null ) {
	global $wpdb;
	$bay_gio = null === $bay_gio ? time() : (int) $bay_gio;
	$pos     = khh_dt_so_pos( $ngay, $cua_hang );
	$bang    = khh_dt_bang_bc();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$bc = $wpdb->get_row(
		$wpdb->prepare( "SELECT nguoi, chot, sua_luc, tien_mat_dem, mon_thuc FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $cua_hang ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	$co_fabi = null !== $pos;
	$da_khai = $bc && '' !== (string) $bc['nguoi'];
	$da_chot = $da_khai && (int) $bc['chot'] > 0;
	$kho     = khh_dt_qt_kho_da_luu( $ngay, $cua_hang );

	if ( $da_chot ) {
		$tt = 'da_chot';
	} elseif ( $da_khai ) {
		$tt = 'da_luu';
	} elseif ( $co_fabi ) {
		$tt = 'chua_nop';
	} else {
		$tt = 'chua_fabi';
	}

	$lech_ket = null;
	if ( $da_khai && (float) $bc['tien_mat_dem'] > 0 && $co_fabi ) {
		$lech_ket = (float) $bc['tien_mat_dem'] - (float) $pos['tien_mat'];
	}
	/* Số món cơ sở chốt KHÁC máy: `mon_thuc` chỉ giữ dòng có gõ số khác máy (xem `khh_dt_rest_bc_luu`). */
	$mon_lech = 0;
	if ( $da_khai && $co_fabi ) {
		$thuc = khh_dt_bc_mon_thuc_sach( (string) $bc['mon_thuc'] );
		foreach ( (array) $pos['mon'] as $m ) {
			if ( isset( $thuc[ $m['n'] ] ) && (int) round( $thuc[ $m['n'] ] ) !== (int) round( $m['q'] ) ) {
				$mon_lech++;
			}
		}
	}

	$han_ts = khh_dt_qt_han_ts( $ngay );
	$h      = new DateTime( '@' . $han_ts );
	$h->setTimezone( wp_timezone() );

	return array(
		'ngay'       => $ngay,
		'cua_hang'   => $cua_hang,
		'trang_thai' => $tt,
		'buoc'       => array(
			'fabi' => $co_fabi,
			'khai' => $da_khai,
			'kho'  => $kho,
			'chot' => $da_chot,
		),
		'qua_han'    => ! $da_chot && $bay_gio > $han_ts,
		'han'        => $h->format( 'Y-m-d H:i' ),
		'nguoi'      => $da_khai ? (string) $bc['nguoi'] : '',
		'sua_luc'    => $da_khai ? (string) $bc['sua_luc'] : '',
		'doanh_thu'  => $co_fabi ? (float) $pos['doanh_thu'] : null,
		'lech_ket'   => $lech_ket,
		'mon_lech'   => $mon_lech,
	);
}

/* ================================================================== *
 * Danh sách việc & tổng hợp
 * ================================================================== */

/**
 * VIỆC CÒN TREO của những cơ sở $cua_ds (rỗng = mọi cơ sở) trong `lui` ngày tới hôm qua.
 *
 * Chỉ liệt kê ngày CHƯA CHỐT. Ngày cũ mà không có số máy và cũng không ai nhập thì bỏ qua (quán
 * nghỉ, hay chưa có file — Đối soát lo); riêng HÔM QUA chưa có số máy thì vẫn kể, vì đó là việc
 * của văn phòng: FABi chưa về / hộp thư chưa chạy. Xếp ngày cũ trước — nợ cũ trả trước.
 */
function khh_dt_qt_viec( $cua_ds = array(), $bay_gio = null, $lui = null ) {
	$bay_gio = null === $bay_gio ? time() : (int) $bay_gio;
	$lui     = null === $lui ? khh_dt_qt_cf()['lui'] : max( 1, (int) $lui );
	$tat_ca  = khh_dt_ds_cua_hang();
	$cua_ds  = array_values( array_filter( array_map( 'strval', (array) $cua_ds ) ) );
	$ds      = $cua_ds ? array_values( array_intersect( $tat_ca, $cua_ds ) ) : $tat_ca;
	/* Cơ sở người này phụ trách mà kho POS chưa hề có tên (chưa nạp file nào) vẫn phải hiện —
	   họ cần thấy "chưa có số máy" thay vì một màn trống. */
	foreach ( $cua_ds as $c ) {
		if ( ! in_array( $c, $ds, true ) && ( ! defined( 'KHH_DT_CHUA_GHEP' ) || KHH_DT_CHUA_GHEP !== $c ) ) {
			$ds[] = $c;
		}
	}
	$hom_qua = khh_dt_qt_cong_ngay( khh_dt_qt_hom_nay( $bay_gio ), -1 );
	$ra      = array();
	for ( $i = $lui - 1; $i >= 0; $i-- ) {
		$ngay = khh_dt_qt_cong_ngay( $hom_qua, -$i );
		foreach ( $ds as $c ) {
			$t = khh_dt_qt_tinh_trang( $ngay, $c, $bay_gio );
			if ( 'da_chot' === $t['trang_thai'] ) {
				continue;
			}
			if ( 'chua_fabi' === $t['trang_thai'] && $ngay !== $hom_qua ) {
				continue;
			}
			$ra[] = $t;
		}
	}
	return $ra;
}

/** Tổng hợp MỘT ngày cho mọi cơ sở: đếm từng trạng thái + từng dòng. */
function khh_dt_qt_tong_hop( $ngay, $bay_gio = null ) {
	$dem  = array( 'da_chot' => 0, 'da_luu' => 0, 'chua_nop' => 0, 'chua_fabi' => 0 );
	$dong = array();
	foreach ( khh_dt_ds_cua_hang() as $c ) {
		if ( defined( 'KHH_DT_CHUA_GHEP' ) && KHH_DT_CHUA_GHEP === $c ) {
			continue;
		}
		$t = khh_dt_qt_tinh_trang( $ngay, $c, $bay_gio );
		$dem[ $t['trang_thai'] ]++;
		$dong[] = $t;
	}
	return array(
		'ngay' => $ngay,
		'dem'  => $dem,
		'tong' => count( $dong ),
		'dong' => $dong,
	);
}

/** Nhãn tiếng Việt cho trạng thái. */
function khh_dt_qt_nhan( $tt ) {
	$n = array(
		'da_chot'   => 'đã chốt',
		'da_luu'    => 'đã lưu, chưa chốt',
		'chua_nop'  => 'chưa nộp',
		'chua_fabi' => 'chưa có số máy POS',
	);
	return isset( $n[ $tt ] ) ? $n[ $tt ] : $tt;
}

/** Thư tổng hợp: [ 'tieu_de' => …, 'noi_dung' => … ] (văn bản thuần, đọc được trên điện thoại). */
function khh_dt_qt_thu( $th ) {
	$ngay_vn = implode( '/', array_reverse( explode( '-', $th['ngay'] ) ) );
	$d       = $th['dem'];
	$tieu_de = sprintf( 'Báo cáo cơ sở %s: %d/%d đã chốt', $ngay_vn, $d['da_chot'], $th['tong'] );
	if ( $d['chua_nop'] + $d['chua_fabi'] + $d['da_luu'] > 0 ) {
		$tieu_de .= sprintf( ' — %d chưa xong', $d['chua_nop'] + $d['chua_fabi'] + $d['da_luu'] );
	}
	$dong   = array();
	$dong[] = 'BÁO CÁO CƠ SỞ NGÀY ' . $ngay_vn;
	$dong[] = sprintf( 'Đã chốt %d · đã lưu chưa chốt %d · chưa nộp %d · chưa có số máy %d (tổng %d cơ sở)', $d['da_chot'], $d['da_luu'], $d['chua_nop'], $d['chua_fabi'], $th['tong'] );
	$dong[] = '';
	$thu_tu = array( 'chua_nop', 'chua_fabi', 'da_luu', 'da_chot' );
	foreach ( $thu_tu as $tt ) {
		$nhom = array_filter( $th['dong'], function ( $x ) use ( $tt ) { return $x['trang_thai'] === $tt; } );
		if ( ! $nhom ) {
			continue;
		}
		/* mb_: `strtoupper` bỏ qua chữ có dấu -> "CHưA Có Số MáY". */
		$dong[] = mb_strtoupper( khh_dt_qt_nhan( $tt ), 'UTF-8' ) . ' (' . count( $nhom ) . ')';
		foreach ( $nhom as $x ) {
			$chi = array();
			if ( null !== $x['doanh_thu'] ) {
				$chi[] = 'máy ' . number_format( $x['doanh_thu'], 0, ',', '.' ) . ' ₫';
			}
			if ( null !== $x['lech_ket'] ) {
				$chi[] = 'két lệch ' . ( $x['lech_ket'] > 0 ? '+' : '' ) . number_format( $x['lech_ket'], 0, ',', '.' ) . ' ₫';
			}
			if ( $x['mon_lech'] > 0 ) {
				$chi[] = $x['mon_lech'] . ' món lệch máy';
			}
			if ( '' !== $x['nguoi'] ) {
				$chi[] = $x['nguoi'];
			}
			if ( $x['qua_han'] ) {
				$chi[] = 'QUÁ HẠN ' . substr( $x['han'], 11 );
			}
			$dong[] = '  - ' . $x['cua_hang'] . ( $chi ? ' — ' . implode( ' · ', $chi ) : '' );
		}
		$dong[] = '';
	}
	$dong[] = 'Mở trang Doanh thu → Đối soát để xem chi tiết. Thư này do hệ gửi tự động lúc hạn chốt.';
	return array( 'tieu_de' => $tieu_de, 'noi_dung' => implode( "\n", $dong ) );
}

/* ================================================================== *
 * Lịch hằng ngày
 * ================================================================== */

function khh_dt_qt_dat_lich() {
	$cu = wp_next_scheduled( KHH_DT_QT_MOC );
	if ( $cu ) {
		wp_unschedule_event( $cu, KHH_DT_QT_MOC );
	}
	$c = khh_dt_qt_cf();
	if ( empty( $c['bat'] ) ) {
		return;
	}
	/* Dùng chung phép tính mốc với hộp thư: 'HH:MM' gần nhất còn ở tương lai, theo múi giờ site. */
	if ( function_exists( 'khh_dt_thu_lan_sau' ) ) {
		$moc = khh_dt_thu_lan_sau( $c['han'], time(), wp_timezone() );
	} else {
		$moc = khh_dt_qt_han_ts( khh_dt_qt_cong_ngay( khh_dt_qt_hom_nay(), -1 ), $c['han'] );
		if ( $moc <= time() ) {
			$moc += DAY_IN_SECONDS;
		}
	}
	wp_schedule_event( $moc, 'daily', KHH_DT_QT_MOC );
}

function khh_dt_qt_go_lich() {
	$cu = wp_next_scheduled( KHH_DT_QT_MOC );
	if ( $cu ) {
		wp_unschedule_event( $cu, KHH_DT_QT_MOC );
	}
}

function khh_dt_qt_nhat_ky() {
	$nk = get_option( KHH_DT_QT_NHAT, array() );
	return is_array( $nk ) ? $nk : array();
}

/**
 * MỘT LƯỢT: tổng hợp hôm qua, ghi nhật ký, gửi thư nếu có địa chỉ.
 *
 * @param int|null $bay_gio Ép "bây giờ" (bài thử).
 * @return array Dòng nhật ký vừa ghi.
 */
function khh_dt_qt_chay( $bay_gio = null ) {
	$bay_gio = null === $bay_gio ? time() : (int) $bay_gio;
	$c       = khh_dt_qt_cf();
	$ngay    = khh_dt_qt_cong_ngay( khh_dt_qt_hom_nay( $bay_gio ), -1 );
	$th      = khh_dt_qt_tong_hop( $ngay, $bay_gio );
	$thu     = khh_dt_qt_thu( $th );
	$toi     = khh_dt_qt_email_ds( $c['email'] );
	$gui     = false;
	$loi     = '';
	if ( $toi ) {
		$gui = (bool) wp_mail( $toi, $thu['tieu_de'], $thu['noi_dung'] );
		if ( ! $gui ) {
			$loi = 'wp_mail() trả về false — kiểm cấu hình gửi thư của WordPress/hosting.';
		}
	}
	$dong = array(
		'luc'     => current_time( 'mysql' ),
		'ngay'    => $ngay,
		'dem'     => $th['dem'],
		'tong'    => $th['tong'],
		'gui'     => $gui,
		'toi'     => $toi,
		'tieu_de' => $thu['tieu_de'],
		'loi'     => $loi,
	);
	$nk = khh_dt_qt_nhat_ky();
	array_unshift( $nk, $dong );
	update_option( KHH_DT_QT_NHAT, array_slice( $nk, 0, 30 ), false );
	return $dong;
}

add_action( KHH_DT_QT_MOC, 'khh_dt_qt_cron' );
function khh_dt_qt_cron() {
	khh_dt_qt_chay();
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_qt_route' );
function khh_dt_qt_route() {
	register_rest_route(
		'khh-dt/v1',
		'/quy-trinh',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_qt_xem',
				'permission_callback' => 'khh_dt_duoc_xem',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_qt_luu',
				'permission_callback' => 'khh_dt_duoc_quan_tri',
			),
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/quy-trinh-chay',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_qt_chay',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
}

/**
 * Ai cũng nhận `viec` của ĐÚNG cơ sở mình (rỗng = mọi cơ sở, tức người văn phòng). Cấu hình, nhật
 * ký, tổng hợp mọi quán chỉ trả cho quản trị — cửa hàng trưởng không cần biết quán khác nộp chưa.
 */
function khh_dt_rest_qt_xem( $req = null ) {
	$c       = khh_dt_qt_cf();
	$cua_ds  = khh_dt_co_so_ds();
	$ra      = array(
		'han'     => $c['han'],
		'viec'    => khh_dt_qt_viec( $cua_ds ),
		'hom_qua' => khh_dt_qt_cong_ngay( khh_dt_qt_hom_nay(), -1 ),
	);
	if ( khh_dt_duoc_quan_tri() ) {
		$sau            = wp_next_scheduled( KHH_DT_QT_MOC );
		$ra['cf']       = $c;
		$ra['nhat_ky']  = khh_dt_qt_nhat_ky();
		$ra['tong_hop'] = khh_dt_qt_tong_hop( $ra['hom_qua'] );
		$ra['lan_sau']  = $sau ? gmdate( 'c', $sau ) : '';
	}
	return $ra;
}

function khh_dt_rest_qt_luu( $req ) {
	$cu  = khh_dt_qt_cf();
	$moi = array(
		'bat'   => (bool) $req->get_param( 'bat' ),
		'han'   => khh_dt_qt_gio_hop_le( $req->get_param( 'han' ) ) ? (string) $req->get_param( 'han' ) : $cu['han'],
		'email' => implode( ', ', khh_dt_qt_email_ds( (string) $req->get_param( 'email' ) ) ),
		'lui'   => max( 1, min( 31, (int) $req->get_param( 'lui' ) ?: $cu['lui'] ) ),
	);
	/* Địa chỉ gõ sai thì nói, đừng lặng lẽ bỏ. */
	$go = trim( (string) $req->get_param( 'email' ) );
	if ( '' !== $go && ! $moi['email'] ) {
		return new WP_Error( 'khh_dt_qt', 'Địa chỉ thư không hợp lệ: ' . $go, array( 'status' => 400 ) );
	}
	update_option( KHH_DT_QT_OPT, $moi, false );
	khh_dt_qt_dat_lich();
	return khh_dt_rest_qt_xem();
}

function khh_dt_rest_qt_chay() {
	$dong = khh_dt_qt_chay();
	$ra   = khh_dt_rest_qt_xem();
	$ra['vua_chay'] = $dong;
	return $ra;
}
