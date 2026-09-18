<?php
/**
 * PHÍ GIAO DỊCH MoMo — NHẬP TAY THEO KHOẢNG NGÀY × TÀI KHOẢN, HỆ TỰ CHIA VỀ CƠ SỞ.
 *
 * 18/09/2026, anh Thắng chốt từng ý:
 *   · *"file họ gửi là 1 số tổng, trên giao dịch thì không có phí"* -> nhập tay;
 *   · *"nó không theo giao dịch nên cũng không rõ mỗi cơ sở là bao nhiêu, nên mình sẽ chia theo
 *     % doanh thu"* -> chia tỷ lệ;
 *   · *"phí cũng thay đổi nên 0,33% cũng không chắc chắn"* -> KHÔNG tính theo tỷ lệ cố định;
 *   · *"nếu ngày nào chưa nhập thì hiện ngày đó, hoặc hiện 2-3 ngày anh sẽ nhập tổng 2-3 ngày
 *     sau chia tổng doanh thu"* -> một lượt nhập phủ MỘT KHOẢNG ngày, không phải một ngày.
 *
 * Đã kiểm cả ba nguồn, không nguồn nào có phí theo giao dịch:
 *   · `Transaction_report_*.csv` — 16 cột, không cột phí;
 *   · `daily_report_*.xlsx` MoMo gửi — 24 dòng giao dịch, cũng không;
 *   · màn Đối soát business.momo.vn — CHỈ ở đây, và là một số tổng: điều chỉnh −8.118đ,
 *     thanh toán 2.451.882đ. Khớp: 2.460.000 − 8.118 = 2.451.882.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CHỖ DỄ MẤT TIỀN NHẤT: HAI LƯỢT NHẬP CHỒNG NGÀY NHAU
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Nhập phí ngày 17, rồi hôm sau nhập một lượt "17→19" cho gọn — thế là phí ngày 17 bị tính HAI
 * LẦN. Không dòng nào sai, không con số nào âm, tổng vẫn ra một số đẹp. Nên `khh_dt_momo_phi_dat`
 * CHỐI thẳng lượt nào chồng ngày với lượt đã có của cùng tài khoản, và nói rõ đang chồng với ai.
 *
 * 🔴 CHIA XONG PHẢI CỘNG LẠI ĐÚNG BẰNG SỐ ĐÃ NHẬP.
 * Chia theo tỷ lệ rồi làm tròn từng phần thì tổng mấy phần thường lệch số gốc vài đồng — mà đây
 * là bảng đối soát, lệch vài đồng là có người đi tìm. Dùng phép "phần dư lớn nhất": chia sàn
 * trước, còn thừa bao nhiêu đồng thì phát cho mấy cơ sở có phần dư lớn nhất. Tổng khớp tuyệt đối.
 *
 * ⚠️ MoMo trừ phí theo TÀI KHOẢN quyết toán, mà K&H có hai pháp nhân (ví dụ KH785 phủ 4 cơ sở).
 *    Nên phí của một tài khoản chỉ được chia cho cơ sở CỦA tài khoản ấy. Bảng
 *    `mã cửa hàng -> tài khoản` học từ chính lượt nạp sao kê, đúng lối bảng
 *    `mã cửa hàng -> cơ sở` đang chạy.
 */

defined( 'ABSPATH' ) || exit;

function khh_dt_bang_momo_phi() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_momo_phi';
}

function khh_dt_tao_bang_momo_phi() {
	global $wpdb;
	$bang    = khh_dt_bang_momo_phi();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tu date NOT NULL,
			den date NOT NULL,
			tai_khoan varchar(60) NOT NULL DEFAULT '',
			phi double NOT NULL DEFAULT 0,
			ghi_chu varchar(190) NOT NULL DEFAULT '',
			nguoi varchar(120) NOT NULL DEFAULT '',
			luc datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY khoang (tu,den,tai_khoan),
			KEY tu (tu)
		) $charset;"
	);
}

/** Chuẩn hoá mã tài khoản: "kh785 " và "KH785" là một. */
function khh_dt_momo_tk_chuan( $tk ) {
	return strtoupper( preg_replace( '~\s+~', '', (string) $tk ) );
}

/* ================================================================== *
 * Mã cửa hàng MoMo  ->  tài khoản quyết toán
 * ================================================================== */

/** Bảng ghép, học từ lượt nạp sao kê. */
function khh_dt_momo_tk_bang() {
	$b = get_option( 'khh_dt_momo_ma_ch_tk', array() );
	return is_array( $b ) ? $b : array();
}

/** Ghi nhớ: mấy mã cửa hàng này thuộc tài khoản này. */
function khh_dt_momo_tk_hoc( $ds_ma_ch, $tk ) {
	$tk = khh_dt_momo_tk_chuan( $tk );
	if ( '' === $tk ) {
		return 0;
	}
	$b  = khh_dt_momo_tk_bang();
	$so = 0;
	foreach ( (array) $ds_ma_ch as $m ) {
		$m = trim( (string) $m );
		if ( '' === $m ) {
			continue;
		}
		if ( ! isset( $b[ $m ] ) || $b[ $m ] !== $tk ) {
			$b[ $m ] = $tk;
			$so++;
		}
	}
	update_option( 'khh_dt_momo_ma_ch_tk', $b, false );
	return $so;
}

function khh_dt_momo_tk_cua_ma_ch( $ma_ch ) {
	$b = khh_dt_momo_tk_bang();
	$m = trim( (string) $ma_ch );
	return isset( $b[ $m ] ) ? (string) $b[ $m ] : '';
}

/* ================================================================== *
 * Nhập phí
 * ================================================================== */

/** Mấy lượt đã nhập của tài khoản này mà CHỒNG ngày với khoảng đang xét. */
function khh_dt_momo_phi_chong( $tu, $den, $tk, $bo_qua_id = 0 ) {
	global $wpdb;
	$bang = khh_dt_bang_momo_phi();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	/* Hai khoảng chồng nhau khi: tu_cu <= den_moi VÀ den_cu >= tu_moi. */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, tu, den, phi FROM $bang
			 WHERE tai_khoan = %s AND tu <= %s AND den >= %s AND id <> %d ORDER BY tu",
			khh_dt_momo_tk_chuan( $tk ),
			$den,
			$tu,
			(int) $bo_qua_id
		),
		ARRAY_A
	);
}

/**
 * Ghi một lượt phí.
 *
 * @param string $tu  Y-m-d.
 * @param string $den Y-m-d (bằng $tu nếu chỉ một ngày).
 * @param string $tk  Mã tài khoản quyết toán, ví dụ KH785.
 * @param float  $phi Số tiền điều chỉnh MoMo trừ, số dương.
 */
function khh_dt_momo_phi_dat( $tu, $den, $tk, $phi, $ghi_chu = '' ) {
	global $wpdb;
	$tk = khh_dt_momo_tk_chuan( $tk );
	if ( '' === $tk ) {
		return new WP_Error( 'khh_dt_phi', 'Thiếu mã tài khoản (ví dụ KH785).' );
	}
	foreach ( array( $tu, $den ) as $n ) {
		if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', (string) $n ) ) {
			return new WP_Error( 'khh_dt_phi', 'Ngày phải dạng 2026-09-17.' );
		}
	}
	if ( $den < $tu ) {
		return new WP_Error( 'khh_dt_phi', 'Ngày kết thúc sớm hơn ngày bắt đầu.' );
	}
	$phi = (float) $phi;
	if ( $phi < 0 ) {
		return new WP_Error( 'khh_dt_phi', 'Phí không được âm — MoMo trừ phí nên con số phải dương.' );
	}

	/* 🔴 CHỐI CHỒNG NGÀY. Xem chú thích đầu tệp: chồng là tính phí hai lần, im lặng. */
	$chong = khh_dt_momo_phi_chong( $tu, $den, $tk );
	if ( $chong ) {
		$ke = array();
		foreach ( $chong as $c ) {
			$ke[] = $c['tu'] . ' → ' . $c['den'] . ' (' . number_format_i18n( (float) $c['phi'] ) . 'đ)';
		}
		return new WP_Error(
			'khh_dt_phi',
			sprintf(
				'Khoảng %s → %s của %s CHỒNG với lượt đã nhập: %s. Sửa hoặc xoá lượt cũ trước — '
				. 'để cả hai là phí bị tính hai lần mà không có gì báo.',
				$tu,
				$den,
				$tk,
				implode( ' · ', $ke )
			)
		);
	}

	$bang = khh_dt_bang_momo_phi();
	$nd   = function_exists( 'wp_get_current_user' ) ? wp_get_current_user()->display_name : '';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO $bang (tu,den,tai_khoan,phi,ghi_chu,nguoi,luc) VALUES (%s,%s,%s,%f,%s,%s,%s)
			 ON DUPLICATE KEY UPDATE phi=VALUES(phi), ghi_chu=VALUES(ghi_chu), nguoi=VALUES(nguoi), luc=VALUES(luc)",
			$tu, $den, $tk, $phi, (string) $ghi_chu, (string) $nd, current_time( 'mysql' )
		)
	);
	// phpcs:enable
	return array( 'tu' => $tu, 'den' => $den, 'tai_khoan' => $tk, 'phi' => $phi );
}

/** Xoá một lượt phí. */
function khh_dt_momo_phi_xoa( $id ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (bool) $wpdb->delete( khh_dt_bang_momo_phi(), array( 'id' => (int) $id ), array( '%d' ) );
}

/** Mọi lượt phí chạm vào khoảng đang xem. */
function khh_dt_momo_phi_ds( $tu = '', $den = '' ) {
	global $wpdb;
	$bang = khh_dt_bang_momo_phi();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	$sql = "SELECT * FROM $bang WHERE 1=1";
	$a   = array();
	if ( $tu ) { $sql .= ' AND den >= %s'; $a[] = $tu; }
	if ( $den ) { $sql .= ' AND tu <= %s'; $a[] = $den; }
	$sql .= ' ORDER BY tu DESC, tai_khoan';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (array) ( $a ? $wpdb->get_results( $wpdb->prepare( $sql, $a ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ) );
	// phpcs:enable
}

/* ================================================================== *
 * Chia phí về cơ sở theo % doanh thu
 * ================================================================== */

/** Doanh thu MoMo theo (ngày × cơ sở) cho riêng một tài khoản, trong một khoảng. */
function khh_dt_momo_o_theo_tk( $tu, $den, $tk ) {
	global $wpdb;
	$bang = khh_dt_bang_momo_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ngay, ma_ch, SUM(so_tien) tien FROM $bang
			 WHERE ngay >= %s AND ngay <= %s GROUP BY ngay, ma_ch",
			$tu,
			$den
		),
		ARRAY_A
	);
	$tk = khh_dt_momo_tk_chuan( $tk );
	$o  = array();
	foreach ( $ds as $r ) {
		if ( khh_dt_momo_tk_cua_ma_ch( $r['ma_ch'] ) !== $tk ) {
			continue;
		}
		$cs = function_exists( 'khh_dt_ma_ch_toi_co_so' ) ? khh_dt_ma_ch_toi_co_so( $r['ma_ch'] ) : '';
		if ( '' === $cs ) {
			/* Chưa học được cơ sở thì để nguyên mã — thà hiện một dòng tên lạ còn hơn đánh rơi
			   phần phí của nó vào hư không, làm tổng không cộng lại đúng số đã nhập. */
			$cs = $r['ma_ch'];
		}
		$k = $r['ngay'] . '|' . $cs;
		$o[ $k ] = ( isset( $o[ $k ] ) ? $o[ $k ] : 0 ) + (float) $r['tien'];
	}
	return $o;
}

/**
 * Chia MỘT số tiền cho các ô theo tỷ lệ, sao cho tổng các phần BẰNG ĐÚNG số gốc.
 *
 * 🔴 PHÉP PHẦN DƯ LỚN NHẤT. Chia theo tỷ lệ rồi làm tròn từng phần thì tổng thường lệch số gốc
 *    vài đồng — trên bảng đối soát, vài đồng lệch là có người đi tìm. Nên: chia SÀN trước, đếm
 *    xem còn thiếu bao nhiêu đồng, rồi phát từng đồng cho những ô có phần dư lớn nhất. Tổng khớp
 *    tuyệt đối, và ô nào "đáng được" thêm đồng lẻ thì được trước.
 */
function khh_dt_chia_tron( $tong, $trong_so ) {
	$tong = (int) round( $tong );
	$sum  = 0.0;
	foreach ( $trong_so as $w ) {
		$sum += (float) $w;
	}
	$ra = array();
	if ( $sum <= 0 || ! $trong_so ) {
		return $ra;
	}
	$du = array();
	$da = 0;
	foreach ( $trong_so as $k => $w ) {
		$x        = $tong * ( (float) $w ) / $sum;
		$san      = (int) floor( $x );
		$ra[ $k ] = $san;
		$du[ $k ] = $x - $san;
		$da      += $san;
	}
	$con = $tong - $da;
	if ( $con > 0 ) {
		arsort( $du );
		foreach ( array_keys( $du ) as $k ) {
			if ( $con <= 0 ) {
				break;
			}
			$ra[ $k ] += 1;
			$con--;
		}
	}
	return $ra;
}

/**
 * Phí đã chia về từng cơ sở, cho khoảng đang xem.
 *
 * ⚠️ CHIA TRONG PHẠM VI CỦA CHÍNH LƯỢT NHẬP, RỒI MỚI CẮT THEO KHOẢNG ĐANG XEM. Anh Thắng nhập
 *    "tổng 2-3 ngày", mà màn hình lại xem một khoảng khác — chia theo khoảng đang xem thì cùng
 *    một lượt phí sẽ ra số khác nhau tuỳ người đang nhìn kỳ nào. Nên chia theo đúng mấy ngày mà
 *    lượt phí ấy phủ, xuống tới từng (ngày × cơ sở), rồi cộng lại những ô nằm trong khoảng xem.
 *
 * @return array [ 'co_so' => [ tên cơ sở => phí ], 'tong' => tổng phí rơi vào khoảng xem ]
 */
function khh_dt_momo_phi_chia( $tu, $den ) {
	$ra   = array();
	$tong = 0.0;
	foreach ( khh_dt_momo_phi_ds( $tu, $den ) as $p ) {
		$o = khh_dt_momo_o_theo_tk( $p['tu'], $p['den'], $p['tai_khoan'] );
		if ( ! $o ) {
			continue;
		}
		$chia = khh_dt_chia_tron( (float) $p['phi'], $o );
		foreach ( $chia as $k => $tien ) {
			list( $ngay, $cs ) = explode( '|', $k, 2 );
			if ( $ngay < $tu || $ngay > $den ) {
				continue;   // ô ngoài khoảng đang xem
			}
			$ra[ $cs ] = ( isset( $ra[ $cs ] ) ? $ra[ $cs ] : 0 ) + $tien;
			$tong     += $tien;
		}
	}
	return array( 'co_so' => $ra, 'tong' => $tong );
}

/**
 * Ngày nào CÓ doanh thu MoMo mà CHƯA có lượt phí nào phủ — để màn hình nhắc anh Thắng nhập.
 *
 * @return array [ tài khoản => [ ngày, … ] ]
 */
function khh_dt_momo_phi_thieu( $tu, $den ) {
	global $wpdb;
	$bang = khh_dt_bang_momo_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT ngay, ma_ch FROM $bang WHERE ngay >= %s AND ngay <= %s AND so_tien > 0",
			$tu,
			$den
		),
		ARRAY_A
	);
	/* Ngày nào đã được một lượt phí phủ. */
	$phu = array();
	foreach ( khh_dt_momo_phi_ds( $tu, $den ) as $p ) {
		$phu[ $p['tai_khoan'] ][] = array( $p['tu'], $p['den'] );
	}

	$thieu = array();
	foreach ( $ds as $r ) {
		$tk = khh_dt_momo_tk_cua_ma_ch( $r['ma_ch'] );
		if ( '' === $tk ) {
			continue;   // chưa biết mã này thuộc tài khoản nào thì chưa đòi phí được
		}
		$co = false;
		foreach ( ( isset( $phu[ $tk ] ) ? $phu[ $tk ] : array() ) as $k ) {
			if ( $r['ngay'] >= $k[0] && $r['ngay'] <= $k[1] ) {
				$co = true;
				break;
			}
		}
		if ( ! $co ) {
			$thieu[ $tk ][ $r['ngay'] ] = true;
		}
	}
	$ra = array();
	foreach ( $thieu as $tk => $ng ) {
		$d = array_keys( $ng );
		sort( $d );
		$ra[ $tk ] = $d;
	}
	return $ra;
}

/* ================================================================== *
 * Cổng REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_momo_phi_route' );
function khh_dt_momo_phi_route() {
	register_rest_route(
		'khh-dt/v1',
		'/momo-phi',
		array(
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_momo_phi_dat',
				/* Phí đi thẳng vào con số tiền trên bảng đối soát, nên chỉ ai được GHI mới
				   được đụng — không mở theo quyền XEM. */
				'permission_callback' => 'khh_dt_duoc_ghi',
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => 'khh_dt_rest_momo_phi_xoa',
				'permission_callback' => 'khh_dt_duoc_ghi',
			),
		)
	);
}

function khh_dt_rest_momo_phi_dat( $req ) {
	$r = khh_dt_momo_phi_dat(
		(string) $req->get_param( 'tu' ),
		(string) $req->get_param( 'den' ),
		(string) $req->get_param( 'tai_khoan' ),
		(float) $req->get_param( 'phi' ),
		(string) $req->get_param( 'ghi_chu' )
	);
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'khh_dt_phi', $r->get_error_message(), array( 'status' => 400 ) );
	}
	return array( 'xong' => true ) + $r;
}

function khh_dt_rest_momo_phi_xoa( $req ) {
	return array( 'xong' => khh_dt_momo_phi_xoa( (int) $req->get_param( 'id' ) ) );
}

/** Mấy tài khoản quyết toán hệ đã biết (học từ lượt nạp sao kê, hoặc đã từng nhập phí). */
function khh_dt_momo_tk_ds() {
	$ds = array_values( array_unique( array_values( khh_dt_momo_tk_bang() ) ) );
	foreach ( khh_dt_momo_phi_ds( '', '' ) as $p ) {
		if ( ! in_array( $p['tai_khoan'], $ds, true ) ) {
			$ds[] = $p['tai_khoan'];
		}
	}
	sort( $ds );
	return $ds;
}
