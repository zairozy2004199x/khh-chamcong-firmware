<?php
/**
 * BÓC TÁCH VÉ → KHÁCH VÀO: mỗi loại vé trên máy POS tính bao nhiêu người.
 *
 * Anh Thắng 23/09/2026: *"mình sẽ bóc tách sẵn cho nhân viên, giờ áp dụng cho gian Tàu trước"* —
 * *"Tổng khách vào: nếu vé là combo VÉ TRẺ EM + NGƯỜI LỚN tính là 2 người, còn nếu nó là trẻ hoặc
 * người lớn riêng thì là 1 người"*.
 *
 * Trước đó ô "Tổng khách vào" (nhân viên đếm ở cửa) chỉ so được với "Số vé bán" — mà một vé combo
 * là HAI người qua cửa, nên số máy luôn thấp hơn số đếm và cột lệch đỏ oan mỗi ngày. Chỗ này cho
 * quản trị khai MỘT LẦN mỗi tên vé tính mấy khách; máy tự ra "Khách vào (POS)" = Σ số lượng × số
 * khách mỗi vé, và nhân viên chỉ còn việc đếm.
 *
 * 🔴 KHAI THEO TỪNG CỬA HÀNG. Anh Thắng 23/09/2026: *"Mỗi cửa hàng 1 cấu hình đi. Để cho dễ"* —
 *    *"trong tài khoản admin… cứ chọn cửa hàng để cấu hình tránh lẫn lộn"*. Tên vé mỗi quán mỗi khác
 *    (Gò Vấp "COMBO … + THẠCH", Tân Phú "VÉ TRẺ EM + NGƯỜI LỚN"), gom một bảng là lẫn. Bảng lưu dạng
 *    [ cửa hàng => [ tên vé => khách ] ]; khoá '*' là bảng CHUNG (bản 1.59.0 lưu phẳng, tự hiểu là
 *    '*') và chỉ làm mặc định cho quán chưa khai riêng tên vé ấy.
 *
 * ⚠️ VÉ CHƯA KHAI THÌ PHẢI NÓI RA. Cộng thiếu một loại vé là số máy thấp hơn thật, lệch trông như
 *    nhân viên đếm dư — sai im lặng đúng kiểu người ta không đi tìm. Nên mỗi ngày trả kèm danh sách
 *    vé (theo tên hoặc nhóm món bắt đầu bằng "Vé") có bán mà chưa được khai.
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ================================================================== *
 * Sổ khai theo cửa hàng — dùng chung cho HAI bảng cùng hình dạng:
 *   · 'khh_dt_ve_khach' : [ cửa hàng => [ tên vé => KHÁCH mỗi vé ] ]
 *   · 'khh_dt_ve_phu'   : [ cửa hàng => [ tên vé => TIỀN SALE PHỤ mỗi vé (đ) ] ]
 * Anh Thắng 23/09/2026: *"Sau mình áp dụng trong cấu hình cứ Sale … = loại vé (sl) × tiền tại mỗi cửa
 * hàng, khác cách tính sale phụ khác"* — tiền phụ khai được tới từng LOẠI VÉ, riêng từng quán; tên vé
 * có số riêng thì đè số của nhóm món (khai ở khối Sale vé / Bán lẻ / Sale phụ).
 * ================================================================== */

function khh_dt_ve_so_cua( $khoa ) {
	$b = get_option( $khoa, array() );
	if ( ! is_array( $b ) ) {
		return array();
	}
	/* Bản 1.59.0 lưu phẳng [ tên => số ] -> coi là bảng chung '*'. Nhận ra bằng: có giá trị là số. */
	$phang = false;
	foreach ( $b as $v ) {
		if ( ! is_array( $v ) ) {
			$phang = true;
			break;
		}
	}
	if ( $phang ) {
		$b = array( '*' => $b );
	}
	$ra = array();
	foreach ( $b as $cs => $bang ) {
		$cs = trim( (string) $cs );
		if ( '' === $cs || ! is_array( $bang ) ) {
			continue;
		}
		foreach ( $bang as $ten => $so ) {
			$ten = trim( (string) $ten );
			if ( '' !== $ten && is_numeric( $so ) ) {
				$ra[ $cs ][ $ten ] = (int) round( (float) $so );
			}
		}
	}
	return $ra;
}

function khh_dt_ve_bang_cua( $khoa, $cua_hang = '' ) {
	$so = khh_dt_ve_so_cua( $khoa );
	$ra = isset( $so['*'] ) ? $so['*'] : array();
	$cs = trim( (string) $cua_hang );
	if ( '' !== $cs && '*' !== $cs && isset( $so[ $cs ] ) ) {
		foreach ( $so[ $cs ] as $ten => $n ) {
			$ra[ $ten ] = $n;
		}
	}
	return $ra;
}

function khh_dt_ve_rieng_cua( $khoa, $cua_hang ) {
	$so = khh_dt_ve_so_cua( $khoa );
	$cs = trim( (string) $cua_hang );
	return '' !== $cs && isset( $so[ $cs ] ) ? $so[ $cs ] : array();
}

function khh_dt_ve_dat_cua( $khoa, $bang, $cua_hang = '' ) {
	$cs = trim( (string) $cua_hang );
	$cs = '' === $cs ? '*' : $cs;
	$so = khh_dt_ve_so_cua( $khoa );
	$cu = isset( $so[ $cs ] ) ? $so[ $cs ] : array();
	foreach ( (array) $bang as $ten => $gia ) {
		$ten = trim( (string) $ten );
		if ( '' === $ten ) {
			continue;
		}
		if ( null === $gia || '' === trim( (string) $gia ) ) {
			unset( $cu[ $ten ] );
			continue;
		}
		if ( ! is_numeric( $gia ) || (float) $gia < 0 ) {
			continue;
		}
		$cu[ $ten ] = (int) round( (float) $gia );
	}
	if ( $cu ) {
		$so[ $cs ] = $cu;
	} else {
		unset( $so[ $cs ] );
	}
	update_option( $khoa, $so, false );
	return khh_dt_ve_bang_cua( $khoa, '*' === $cs ? '' : $cs );
}

/** Toàn bộ sổ khai khách/vé, đã chuẩn hoá: [ cửa hàng ('*' = chung) => [ tên vé => khách ] ]. */
function khh_dt_ve_khach_so() {
	return khh_dt_ve_so_cua( 'khh_dt_ve_khach' );
}

/**
 * Bảng khai áp cho MỘT cửa hàng: [ tên vé => khách ] — bảng chung '*' làm nền, bảng riêng của quán
 * đè lên. Không truyền cửa hàng thì chỉ là bảng chung.
 */
function khh_dt_ve_khach_bang( $cua_hang = '' ) {
	return khh_dt_ve_bang_cua( 'khh_dt_ve_khach', $cua_hang );
}

/** Bảng RIÊNG của một cửa hàng (không lẫn bảng chung) — để màn biết ô nào là quán tự khai. */
function khh_dt_ve_khach_bang_rieng( $cua_hang ) {
	return khh_dt_ve_rieng_cua( 'khh_dt_ve_khach', $cua_hang );
}

/**
 * Ghi thêm/sửa/xoá vào bảng khai của MỘT cửa hàng ('' hay '*' = bảng chung). Giá trị '' hay null là
 * XOÁ tên ấy khỏi bảng quán đó; số âm bị chối (giữ nguyên). Trả về bảng áp cho quán sau khi ghi.
 */
function khh_dt_ve_khach_dat( $bang, $cua_hang = '' ) {
	return khh_dt_ve_dat_cua( 'khh_dt_ve_khach', $bang, $cua_hang );
}

/* ---- Sale phụ theo TÊN VÉ (đ/vé), riêng từng quán. 0 = "vé này không có phụ" dù nhóm có. ---- */
function khh_dt_ve_phu_bang( $cua_hang = '' ) {
	return khh_dt_ve_bang_cua( 'khh_dt_ve_phu', $cua_hang );
}
function khh_dt_ve_phu_bang_rieng( $cua_hang ) {
	return khh_dt_ve_rieng_cua( 'khh_dt_ve_phu', $cua_hang );
}
function khh_dt_ve_phu_dat( $bang, $cua_hang = '' ) {
	return khh_dt_ve_dat_cua( 'khh_dt_ve_phu', $bang, $cua_hang );
}

/** Món này trông như một loại VÉ (tên hay nhóm món có chữ "Vé" đứng đầu một từ). */
function khh_dt_ve_la_ve( $ten, $nhom = '' ) {
	foreach ( array( $ten, $nhom ) as $s ) {
		$k = khh_dt_khong_dau( (string) $s );
		if ( '' !== $k && preg_match( '/(^|[\s:(\/\-])ve(\s|$|:)/', $k ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Gợi ý số khách cho một tên vé — không phải vé -> null.
 *   · Đếm số chữ chỉ NGƯỜI trong tên ("trẻ em", "người lớn", "bé", "em bé", "phụ huynh"): "TRẺ EM +
 *     NGƯỜI LỚN + THẠCH" -> 2 (thạch không phải người); "VÉ TRẺ EM" -> 1.
 *   · Không có chữ chỉ người: có dấu "+" -> 2, còn lại -> 1.
 */
function khh_dt_ve_khach_goi_y( $ten, $nhom = '' ) {
	if ( ! khh_dt_ve_la_ve( $ten, $nhom ) ) {
		return null;
	}
	$k = ' ' . preg_replace( '/\s+/', ' ', khh_dt_khong_dau( (string) $ten ) ) . ' ';
	$n = 0;
	foreach ( array( 'tre em', 'nguoi lon', 'em be', 'phu huynh', 'be ' ) as $tu ) {
		$n += preg_match_all( '/(?<![a-z])' . preg_quote( $tu, '/' ) . '(?![a-z])/', $k );
	}
	if ( $n > 0 ) {
		return $n;
	}
	return false !== strpos( (string) $ten, '+' ) ? 2 : 1;
}

/**
 * Tính khách từ danh sách món của một ngày ([ {n, g, q, r} ... ]).
 *
 * 🔴 VÉ CHƯA KHAI THÌ TẠM TÍNH 1 KHÁCH MỘT VÉ, KHÔNG BỎ QUA. Anh Thắng 23/09/2026 nhìn Lotte Gò Vấp:
 *    hai combo tên khác Aeon Tân Phú chưa được khai, máy chỉ cộng 12 + 2 = 14 rồi bày như số thật —
 *    *"bên khách lại lấy khách vào sai… phải 28 chứ"* (10 + 12 + 4 + 2). Bỏ qua một loại vé là số
 *    máy tụt xuống dưới cả số vé bán, vô lý ngay. Nay vé chưa khai góp 1 khách/vé và được KỂ TÊN
 *    kèm chữ "tạm tính"; khai 2 cho combo ở Quản trị là số nhảy lên đúng.
 *
 * @return array khach (int|null — null khi không có vé nào, khai hay chưa), chac (phần từ vé đã
 *               khai), tam (phần tạm tính 1 khách/vé từ vé chưa khai), da_tach (số loại vé đã khai
 *               khớp), chua_tach ([ tên => số lượng ] vé có bán mà chưa khai), du (không còn vé chưa
 *               khai).
 */
function khh_dt_khach_may_tu_mon( $mon, $bang = null ) {
	/* ⚠️ Không truyền bảng thì chỉ có bảng CHUNG — người gọi biết cửa hàng phải truyền
	   `khh_dt_ve_khach_bang( $cua_hang )`. */
	$bang = null === $bang ? khh_dt_ve_khach_bang() : (array) $bang;
	$chac = 0;
	$tam  = 0;
	$n    = 0;
	$chua = array();
	foreach ( (array) $mon as $m ) {
		$ten = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
		$sl  = isset( $m['q'] ) ? (float) $m['q'] : 0;
		if ( '' === $ten ) {
			continue;
		}
		if ( array_key_exists( $ten, $bang ) ) {
			$chac += $sl * (int) $bang[ $ten ];
			$n++;
			continue;
		}
		if ( $sl > 0 && khh_dt_ve_la_ve( $ten, isset( $m['g'] ) ? (string) $m['g'] : '' ) ) {
			$chua[ $ten ] = ( isset( $chua[ $ten ] ) ? $chua[ $ten ] : 0 ) + $sl;
			$tam         += $sl;
		}
	}
	$co = $n > 0 || $chua;
	return array(
		'khach'     => $co ? (int) round( $chac + $tam ) : null,
		'chac'      => (int) round( $chac ),
		'tam'       => (int) round( $tam ),
		'da_tach'   => $n,
		'chua_tach' => $chua,
		'du'        => ! $chua,
	);
}

/**
 * Mỗi cửa hàng còn bao nhiêu loại vé chưa khai (N ngày gần nhất) — để màn nhắc "còn quán X, Y" và
 * người quản trị đổi ô chọn cửa hàng sang khai tiếp. [ cửa hàng => số loại vé chưa khai ], chỉ quán
 * còn thiếu, thiếu nhiều xếp trước.
 */
function khh_dt_ve_khach_chua_khai( $lui = 90 ) {
	global $wpdb;
	$bang = khh_dt_bang();
	$den  = current_time( 'Y-m-d' );
	$tu   = gmdate( 'Y-m-d', strtotime( $den . ' -' . max( 1, (int) $lui ) . ' days' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT cua_hang, mon FROM $bang WHERE ngay >= %s AND ngay <= %s", $tu, $den ), ARRAY_A );
	$gom = array();   // cửa hàng => [ tên vé chưa khai => true ]
	$nho = array();   // bảng khai theo cửa hàng, nhớ lại
	foreach ( $ds as $r ) {
		$cs = (string) $r['cua_hang'];
		if ( ! isset( $nho[ $cs ] ) ) {
			$nho[ $cs ] = khh_dt_ve_khach_bang( $cs );
		}
		$mon = json_decode( (string) $r['mon'], true );
		foreach ( is_array( $mon ) ? $mon : array() as $m ) {
			$ten = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
			$sl  = isset( $m['q'] ) ? (float) $m['q'] : 0;
			$g   = isset( $m['g'] ) ? (string) $m['g'] : '';
			if ( '' === $ten || $sl <= 0 || array_key_exists( $ten, $nho[ $cs ] ) || ! khh_dt_ve_la_ve( $ten, $g ) ) {
				continue;
			}
			$gom[ $cs ][ $ten ] = true;
		}
	}
	$ra = array();
	foreach ( $gom as $cs => $ds_ten ) {
		$ra[ $cs ] = count( $ds_ten );
	}
	arsort( $ra );
	return $ra;
}

/** Khách vào theo máy cho một ngày, một cơ sở — đọc thẳng cột `mon` của kho số FABi. */
function khh_dt_khach_may( $ngay, $cua_hang ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$mon = $wpdb->get_var( $wpdb->prepare( "SELECT mon FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $cua_hang ) );
	$ds  = json_decode( (string) $mon, true );
	return khh_dt_khach_may_tu_mon( is_array( $ds ) ? $ds : array(), khh_dt_ve_khach_bang( $cua_hang ) );
}

/**
 * Những món một cơ sở đã bán trong N ngày gần nhất — để màn Quản trị bày ra cho khai.
 *
 * @return array [ { ten, nhom, so_luong, khach (đã khai | null), goi_y, la_ve } ], vé xếp trước,
 *               rồi theo số lượng giảm dần.
 */
function khh_dt_ve_khach_mon_cua( $cua_hang, $lui = 90 ) {
	global $wpdb;
	$bang = khh_dt_bang();
	$den  = current_time( 'Y-m-d' );
	$tu   = gmdate( 'Y-m-d', strtotime( $den . ' -' . max( 1, (int) $lui ) . ' days' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s", $tu, $den, $cua_hang ),
		ARRAY_A
	);
	$gom = array();
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		foreach ( is_array( $mon ) ? $mon : array() as $m ) {
			$ten = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
			if ( '' === $ten ) {
				continue;
			}
			if ( ! isset( $gom[ $ten ] ) ) {
				$gom[ $ten ] = array( 'q' => 0, 'g' => isset( $m['g'] ) ? (string) $m['g'] : '' );
			}
			$gom[ $ten ]['q'] += isset( $m['q'] ) ? (float) $m['q'] : 0;
		}
	}
	$khai  = khh_dt_ve_khach_bang( $cua_hang );
	$rieng = khh_dt_ve_khach_bang_rieng( $cua_hang );
	$phu   = khh_dt_ve_phu_bang( $cua_hang );
	$phu_r = khh_dt_ve_phu_bang_rieng( $cua_hang );
	$nhom_phu = function_exists( 'khh_dt_nhom_phu_ds' ) ? khh_dt_nhom_phu_ds( $cua_hang ) : false;
	$ra    = array();
	foreach ( $gom as $ten => $x ) {
		$ra[] = array(
			'ten'      => $ten,
			'nhom'     => $x['g'],
			'so_luong' => $x['q'],
			'khach'    => array_key_exists( $ten, $khai ) ? $khai[ $ten ] : null,
			/* Số đang áp là của quán tự khai, hay thừa từ bảng chung. */
			'rieng'    => array_key_exists( $ten, $rieng ),
			/* Sale phụ đ/vé khai theo TÊN vé (null = chưa), và số của NHÓM đang áp nếu không khai tên. */
			'phu'      => array_key_exists( $ten, $phu ) ? $phu[ $ten ] : null,
			'phu_rieng' => array_key_exists( $ten, $phu_r ),
			'phu_nhom' => is_array( $nhom_phu ) && isset( $nhom_phu[ trim( $x['g'] ) ] ) ? (float) $nhom_phu[ trim( $x['g'] ) ] : null,
			'goi_y'    => khh_dt_ve_khach_goi_y( $ten, $x['g'] ),
			'la_ve'    => khh_dt_ve_la_ve( $ten, $x['g'] ),
		);
	}
	usort(
		$ra,
		function ( $a, $b ) {
			if ( $a['la_ve'] !== $b['la_ve'] ) {
				return $a['la_ve'] ? -1 : 1;
			}
			if ( $a['so_luong'] === $b['so_luong'] ) {
				return strcmp( $a['ten'], $b['ten'] );
			}
			return $a['so_luong'] < $b['so_luong'] ? 1 : -1;
		}
	);
	return $ra;
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_ve_khach_route' );
function khh_dt_ve_khach_route() {
	register_rest_route(
		'khh-dt/v1',
		'/ve-khach',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_ve_khach_xem',
				/* Bóc tách là việc của văn phòng (vai duyệt / tài khoản biên tập) — cùng cửa với nạp file.
				   Cửa hàng chỉ đếm, không tự định vé của mình tính mấy người. */
				'permission_callback' => 'khh_dt_duoc_nap',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_ve_khach_dat',
				'permission_callback' => 'khh_dt_duoc_nap',
			),
		)
	);
}

function khh_dt_rest_ve_khach_xem( $req ) {
	$ch = (string) $req->get_param( 'cua_hang' );
	return array(
		'bang'      => khh_dt_ve_khach_bang( $ch ),
		'bang_rieng' => khh_dt_ve_khach_bang_rieng( $ch ),
		'phu'       => khh_dt_ve_phu_bang( $ch ),
		'phu_rieng' => khh_dt_ve_phu_bang_rieng( $ch ),
		'cua_hang'  => $ch,
		'mon'       => '' !== $ch ? khh_dt_ve_khach_mon_cua( $ch ) : array(),
		/* Quán nào còn vé chưa khai — để nhắc đổi ô chọn cửa hàng sang khai tiếp. */
		'con_thieu' => khh_dt_ve_khach_chua_khai(),
	);
}

function khh_dt_rest_ve_khach_dat( $req ) {
	$tho  = $req->get_param( 'bang' );
	$bang = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	if ( ! is_array( $bang ) ) {
		return new WP_Error( 'khh_dt_ve_khach', 'Không đọc được bảng khai gửi lên.', array( 'status' => 400 ) );
	}
	$ch = sanitize_text_field( (string) $req->get_param( 'cua_hang' ) );
	if ( '' === $ch ) {
		return new WP_Error( 'khh_dt_ve_khach', 'Chưa chọn cửa hàng — bảng bóc tách khai riêng từng cửa hàng.', array( 'status' => 400 ) );
	}
	$sach = array();
	foreach ( $bang as $ten => $so ) {
		$sach[ sanitize_text_field( (string) $ten ) ] = null === $so ? '' : sanitize_text_field( (string) $so );
	}
	khh_dt_ve_khach_dat( $sach, $ch );
	/* Sale phụ theo tên vé, gửi cùng lượt; không gửi thì giữ nguyên. */
	$tho_p = $req->get_param( 'phu' );
	if ( null !== $tho_p ) {
		$phu = is_array( $tho_p ) ? $tho_p : json_decode( (string) $tho_p, true );
		if ( is_array( $phu ) ) {
			$sach_p = array();
			foreach ( $phu as $ten => $so ) {
				$sach_p[ sanitize_text_field( (string) $ten ) ] = null === $so ? '' : sanitize_text_field( (string) $so );
			}
			khh_dt_ve_phu_dat( $sach_p, $ch );
		}
	}
	return khh_dt_rest_ve_khach_xem( $req );
}
