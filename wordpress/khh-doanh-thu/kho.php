<?php
/**
 * SỔ KHO HÀNG HOÁ — nhân viên khai cuối ngày, hệ đối chiếu với FABi.
 *
 * 20/09/2026 anh Thắng chốt, từng câu một:
 *   · *"B"* — làm quản kho thật, không chỉ xem hàng bán được bao nhiêu;
 *   · *"chỗ nhập cho nhân viên nhập cuối hàng còn bao nhiêu bán bao nhiêu"*;
 *   · *"Sau đó đối chiếu với dữ liệu fabi"*;
 *   · *"Nếu sau fabi phát sinh món hệ thống tự tách thêm ô nhập"*;
 *   · *"Có những món nằm trong combo nên cũng tự hiểu tách ra số lượng tồn kho nhé"*.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * HAI CON SỐ ĐỘC LẬP, VÀ ĐÓ LÀ TOÀN BỘ GIÁ TRỊ CỦA SỔ NÀY
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Nhân viên khai `bán` và `còn`; máy POS ghi `bán`. Hai nguồn không nhìn thấy nhau, nên đối
 * chiếu mới có nghĩa:
 *
 *   lệch khai  = nhân viên khai bán  −  máy POS ghi bán
 *   tồn tính   = tồn đầu + nhập − máy POS ghi bán − chạy combo
 *   lệch kho   = nhân viên đếm còn  −  tồn tính
 *
 * 🔴 TỒN TÍNH LẤY SỐ CỦA MÁY, KHÔNG LẤY SỐ NHÂN VIÊN KHAI. Lấy số khai thì `lệch kho` luôn
 *    bằng 0 dù thực tế mất hàng — người khai thiếu bao nhiêu thì tồn tính cũng thừa bấy nhiêu,
 *    hai vế tự triệt tiêu. Cả sổ sẽ xanh mướt và vô dụng.
 *
 * 🔴 TỒN ĐẦU BẮT NGUỒN TỪ LẦN ĐẾM GẦN NHẤT, KHÔNG PHẢI TỪ CHUỖI TÍNH TỪ ĐẦU. Đếm tay là sự
 *    thật; số tính là suy ra. Một ngày lệch mà cứ tính tiếp từ số suy ra thì cái lệch ấy theo
 *    mãi về sau, và mọi ngày sau đều đỏ vì một lỗi đã xử lý xong từ lâu.
 *
 * 🔴 MÓN TRONG COMBO PHẢI TRỪ KHO. Bán một "Combo 2 người" là một chai nước rời kho, nhưng
 *    FABi ghi doanh thu vào tên combo chứ không vào tên chai nước. Không tách thành phần ra
 *    thì chai nước ấy mãi mãi "chưa bán", tồn tính cứ thừa dần, tới lúc nhìn lại thì không ai
 *    biết thừa từ đâu. Bảng thành phần combo do người đặt một lần — hệ không tự đoán được.
 */

defined( 'ABSPATH' ) || exit;

/** Lùi tối đa ngần này ngày để dựng lại tồn đầu. */
const KHH_DT_KHO_LUI = 90;

function khh_dt_bang_kho() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_kho';
}

function khh_dt_tao_bang_kho() {
	global $wpdb;
	$bang    = khh_dt_bang_kho();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ngay date NOT NULL,
			co_so varchar(190) NOT NULL DEFAULT '',
			mat_hang varchar(190) NOT NULL DEFAULT '',
			nhap double NOT NULL DEFAULT 0,
			ban_khai double NULL DEFAULT NULL,
			combo_tay double NOT NULL DEFAULT 0,
			dem double NULL DEFAULT NULL,
			ghi_chu varchar(190) NOT NULL DEFAULT '',
			nguoi varchar(120) NOT NULL DEFAULT '',
			luc datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY mot_dong (ngay,co_so(60),mat_hang(60)),
			KEY ngay (ngay)
		) $charset;"
	);
}

/* ================================================================== *
 * Mặt hàng KHO của từng cơ sở
 * ================================================================== */

/**
 * Cơ sở này coi những món nào là HÀNG HOÁ CÓ KHO.
 *
 * 20/09/2026 anh Thắng, sau khi mở thử trên điện thoại: *"Phân loại theo cơ sở đang có hàng
 * của mình nhé"*. Đúng — FABi bán cả BẠC XỈU, CACAO LATTE, COMBO TRÀ CHANH GIÃ TAY… là đồ pha
 * tại chỗ, không có kho để đếm. Đổ hết vào sổ kho thì nhân viên phải cuộn qua vài chục dòng vô
 * nghĩa mới tới chai nước cần đếm, và mấy dòng ấy mãi mãi đỏ vì chẳng ai đếm chúng bao giờ.
 * Sổ đỏ vì lý do vớ vẩn thì người ta thôi nhìn cột lệch — mất luôn tác dụng của cả sổ.
 *
 * @return array Danh sách mặt hàng. RỖNG nghĩa là chưa chọn, KHÔNG phải "không có gì".
 */
function khh_dt_kho_mh_cua( $co_so ) {
	$b = get_option( 'khh_dt_kho_mat_hang', array() );
	$b = is_array( $b ) ? $b : array();
	$d = isset( $b[ $co_so ] ) ? $b[ $co_so ] : array();
	return is_array( $d ) ? array_values( array_unique( array_map( 'strval', $d ) ) ) : array();
}

function khh_dt_kho_mh_dat( $co_so, $ds ) {
	$b = get_option( 'khh_dt_kho_mat_hang', array() );
	$b = is_array( $b ) ? $b : array();
	$sach = array();
	foreach ( (array) $ds as $x ) {
		$x = trim( (string) $x );
		if ( '' !== $x && ! in_array( $x, $sach, true ) ) {
			$sach[] = $x;
		}
	}
	$b[ (string) $co_so ] = $sach;
	update_option( 'khh_dt_kho_mat_hang', $b, false );
	return count( $sach );
}

/** Mọi món FABi từng ghi ở cơ sở này — để bày ra cho người chọn. */
function khh_dt_kho_mon_da_thay( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		foreach ( is_array( $mon ) ? $mon : array() as $m ) {
			$ten = isset( $m['n'] ) ? (string) $m['n'] : '';
			if ( '' === $ten ) {
				continue;
			}
			$ra[ $ten ] = ( isset( $ra[ $ten ] ) ? $ra[ $ten ] : 0 ) + ( isset( $m['q'] ) ? (float) $m['q'] : 0 );
		}
	}
	ksort( $ra );
	return $ra;
}

/* ================================================================== *
 * Thành phần combo
 * ================================================================== */

/**
 * Bảng combo: tên món combo -> [ tên mặt hàng => số lượng trong một combo ].
 *
 * Người đặt một lần. Hệ KHÔNG tự đoán: FABi chỉ có tên combo và doanh thu, không có công thức.
 */
function khh_dt_kho_combo_bang() {
	$b = get_option( 'khh_dt_kho_combo', array() );
	return is_array( $b ) ? $b : array();
}

function khh_dt_kho_combo_dat( $ten_combo, $thanh_phan ) {
	$ten = trim( (string) $ten_combo );
	if ( '' === $ten ) {
		return false;
	}
	$b  = khh_dt_kho_combo_bang();
	$tp = array();
	foreach ( (array) $thanh_phan as $mh => $sl ) {
		$mh = trim( (string) $mh );
		$sl = (float) $sl;
		if ( '' !== $mh && $sl > 0 ) {
			$tp[ $mh ] = $sl;
		}
	}
	if ( $tp ) {
		$b[ $ten ] = $tp;
	} else {
		unset( $b[ $ten ] );   // bảng rỗng là lệnh xoá combo ấy
	}
	update_option( 'khh_dt_kho_combo', $b, false );
	return true;
}

/* ================================================================== *
 * Số máy POS ghi — lấy từ chính kho số FABi đã nạp
 * ================================================================== */

/**
 * Số lượng bán theo máy, cho một cơ sở, trong một khoảng.
 *
 * @return array [ ngày => [ mặt hàng => số lượng ] ]  — đã CỘNG cả phần đi theo combo.
 */
function khh_dt_kho_ban_may( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ngay, mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$combo = khh_dt_kho_combo_bang();
	$ra    = array();
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		if ( ! is_array( $mon ) ) {
			continue;
		}
		$ngay = (string) $r['ngay'];
		foreach ( $mon as $m ) {
			$ten = isset( $m['n'] ) ? (string) $m['n'] : '';
			$sl  = isset( $m['q'] ) ? (float) $m['q'] : 0;
			if ( '' === $ten ) {
				continue;
			}
			if ( isset( $combo[ $ten ] ) ) {
				/* Món này là một combo: KHÔNG trừ kho theo tên combo (kho không có "combo"),
				   mà trừ từng thành phần. Một combo bán ra là ngần ấy chai nước rời kho. */
				foreach ( $combo[ $ten ] as $mh => $moi_cai ) {
					$ra[ $ngay ][ $mh ] = ( isset( $ra[ $ngay ][ $mh ] ) ? $ra[ $ngay ][ $mh ] : 0 )
						+ $sl * (float) $moi_cai;
				}
				continue;
			}
			$ra[ $ngay ][ $ten ] = ( isset( $ra[ $ngay ][ $ten ] ) ? $ra[ $ngay ][ $ten ] : 0 ) + $sl;
		}
	}
	return $ra;
}

/** Số lượng bán theo máy, TÁCH RIÊNG phần đi thẳng và phần đi theo combo — để bày ra hai cột. */
function khh_dt_kho_ban_may_tach( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ngay, mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$combo = khh_dt_kho_combo_bang();
	$ra    = array();
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		if ( ! is_array( $mon ) ) {
			continue;
		}
		$ngay = (string) $r['ngay'];
		foreach ( $mon as $m ) {
			$ten = isset( $m['n'] ) ? (string) $m['n'] : '';
			$sl  = isset( $m['q'] ) ? (float) $m['q'] : 0;
			if ( '' === $ten ) {
				continue;
			}
			if ( isset( $combo[ $ten ] ) ) {
				foreach ( $combo[ $ten ] as $mh => $moi_cai ) {
					if ( ! isset( $ra[ $ngay ][ $mh ] ) ) {
						$ra[ $ngay ][ $mh ] = array( 'le' => 0, 'combo' => 0 );
					}
					$ra[ $ngay ][ $mh ]['combo'] += $sl * (float) $moi_cai;
				}
				continue;
			}
			if ( ! isset( $ra[ $ngay ][ $ten ] ) ) {
				$ra[ $ngay ][ $ten ] = array( 'le' => 0, 'combo' => 0 );
			}
			$ra[ $ngay ][ $ten ]['le'] += $sl;
		}
	}
	return $ra;
}

/* ================================================================== *
 * Dòng nhân viên đã khai
 * ================================================================== */

function khh_dt_kho_dong( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang_kho();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $bang WHERE ngay >= %s AND ngay <= %s AND co_so = %s ORDER BY ngay, mat_hang",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$ra[ $r['ngay'] ][ $r['mat_hang'] ] = $r;
	}
	return $ra;
}

/** Ghi một dòng khai. `null` nghĩa là "chưa khai", khác hẳn số 0 — 0 là "đếm được 0 cái". */
function khh_dt_kho_ghi( $ngay, $co_so, $mat_hang, $o ) {
	global $wpdb;
	$bang = khh_dt_bang_kho();
	$sl   = function ( $x ) {
		if ( null === $x || '' === $x ) {
			return null;
		}
		return function_exists( 'khh_dt_so' ) ? khh_dt_so( $x ) : (float) $x;
	};
	$nhap  = $sl( isset( $o['nhap'] ) ? $o['nhap'] : null );
	$combo = $sl( isset( $o['combo_tay'] ) ? $o['combo_tay'] : null );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return false !== $wpdb->query(
		$wpdb->prepare(
			"INSERT INTO $bang (ngay,co_so,mat_hang,nhap,ban_khai,combo_tay,dem,ghi_chu,nguoi,luc)
			 VALUES (%s,%s,%s,%f,%s,%f,%s,%s,%s,%s)
			 ON DUPLICATE KEY UPDATE nhap=VALUES(nhap), ban_khai=VALUES(ban_khai),
			   combo_tay=VALUES(combo_tay), dem=VALUES(dem), ghi_chu=VALUES(ghi_chu),
			   nguoi=VALUES(nguoi), luc=VALUES(luc)",
			$ngay,
			$co_so,
			$mat_hang,
			null === $nhap ? 0 : $nhap,
			$sl( isset( $o['ban_khai'] ) ? $o['ban_khai'] : null ),
			null === $combo ? 0 : $combo,
			$sl( isset( $o['dem'] ) ? $o['dem'] : null ),
			isset( $o['ghi_chu'] ) ? (string) $o['ghi_chu'] : '',
			function_exists( 'khh_dt_ten_ghi_so' ) ? khh_dt_ten_ghi_so() : '',
			current_time( 'mysql' )
		)
	);
}

/* ================================================================== *
 * Dựng lại tồn đầu, và ghép bảng đối chiếu
 * ================================================================== */

/**
 * Trạng thái kho ở CUỐI ngày `$den_ngay`, dựng lại bằng cách chạy lại từng ngày.
 *
 * 🔴 MỖI LẦN ĐẾM TAY LÀ MỘT MỐC MỚI. Gặp `dem` là lấy luôn số ấy làm tồn cuối, bỏ số tính ra.
 *    Không làm thế thì một ngày lệch sẽ theo mãi về sau: mọi ngày sau đều đỏ vì một lỗi đã xử
 *    lý xong từ lâu, và người ta sẽ thôi nhìn cột lệch.
 *
 * @return array [ mặt hàng => [ 'ton' => số, 'co_moc' => đã từng được đếm tay chưa ] ]
 */
function khh_dt_kho_trang_thai( $co_so, $den_ngay ) {
	$bd   = gmdate( 'Y-m-d', strtotime( $den_ngay ) - KHH_DT_KHO_LUI * DAY_IN_SECONDS );
	$khai = khh_dt_kho_dong( $bd, $den_ngay, $co_so );
	$may  = khh_dt_kho_ban_may( $bd, $den_ngay, $co_so );

	$ngay_ds = array_unique( array_merge( array_keys( $khai ), array_keys( $may ) ) );
	sort( $ngay_ds );

	$tt = array();
	foreach ( $ngay_ds as $ng ) {
		if ( $ng > $den_ngay ) {
			break;
		}
		$mh_ds = array_unique(
			array_merge(
				array_keys( isset( $khai[ $ng ] ) ? $khai[ $ng ] : array() ),
				array_keys( isset( $may[ $ng ] ) ? $may[ $ng ] : array() )
			)
		);
		foreach ( $mh_ds as $mh ) {
			if ( ! isset( $tt[ $mh ] ) ) {
				/* 🔴 `null` = CHƯA BIẾT TỒN, khác hẳn 0. Bắt đầu từ 0 là sai: hệ chưa hề biết
				   trên kệ có bao nhiêu, mà cứ trừ số bán ra thì ngày đầu đã ra tồn ÂM. Anh
				   Thắng mở thử trên điện thoại 20/09/2026 thấy đúng thế — "BIMBIM LỚN −61",
				   "−139", cả màn toàn số âm. Số âm ấy không sai một cách thú vị, nó chỉ vô
				   nghĩa: chưa ai đặt mốc thì không có gì để tính. */
				$tt[ $mh ] = array( 'ton' => null, 'co_moc' => false );
			}
			$d     = isset( $khai[ $ng ][ $mh ] ) ? $khai[ $ng ][ $mh ] : array();
			$nhap  = isset( $d['nhap'] ) ? (float) $d['nhap'] : 0.0;
			$ctay  = isset( $d['combo_tay'] ) ? (float) $d['combo_tay'] : 0.0;
			$b_may = isset( $may[ $ng ][ $mh ] ) ? (float) $may[ $ng ][ $mh ] : 0.0;

			/* Lượt NHẬP đầu tiên cũng là một mốc: nhập vào kho rỗng thì tồn chính bằng số nhập. */
			if ( null === $tt[ $mh ]['ton'] && $nhap > 0 ) {
				$tt[ $mh ]['ton'] = 0.0;
			}
			if ( null !== $tt[ $mh ]['ton'] ) {
				$tt[ $mh ]['ton'] = $tt[ $mh ]['ton'] + $nhap - $b_may - $ctay;
			}

			/* Đếm tay thắng số tính — đây là chỗ cắt đứt cái lệch cũ, và cũng là cách đặt mốc
			   đầu tiên cho một mặt hàng chưa ai đếm bao giờ. */
			if ( isset( $d['dem'] ) && null !== $d['dem'] && '' !== $d['dem'] ) {
				$tt[ $mh ]['ton']    = (float) $d['dem'];
				$tt[ $mh ]['co_moc'] = true;
			}
		}
	}
	return $tt;
}

/**
 * Bảng đối chiếu kho của MỘT ngày — cũng chính là biểu mẫu nhân viên khai cuối ngày.
 *
 * 🔴 DANH SÁCH MẶT HÀNG TỰ SINH, KHÔNG GÕ TAY. Anh Thắng: *"Nếu sau fabi phát sinh món hệ
 *    thống tự tách thêm ô nhập"*. Nên danh sách là hợp của ba nguồn:
 *      · món FABi ghi bán hôm nay        -> món mới xuất hiện là có ô nhập ngay, khỏi khai báo;
 *      · mặt hàng còn tồn từ hôm trước   -> hôm nay không bán cái nào vẫn phải đếm được;
 *      · dòng nhân viên đã khai hôm nay  -> khai rồi thì không được biến mất.
 *    Thiếu nguồn thứ hai là món hết bán sẽ rơi khỏi sổ mang theo cả phần tồn của nó.
 */
function khh_dt_kho_bang_ngay( $ngay, $co_so ) {
	$hom_qua = gmdate( 'Y-m-d', strtotime( $ngay ) - DAY_IN_SECONDS );
	$dau     = khh_dt_kho_trang_thai( $co_so, $hom_qua );
	$khai    = khh_dt_kho_dong( $ngay, $ngay, $co_so );
	$khai    = isset( $khai[ $ngay ] ) ? $khai[ $ngay ] : array();
	$tach    = khh_dt_kho_ban_may_tach( $ngay, $ngay, $co_so );
	$tach    = isset( $tach[ $ngay ] ) ? $tach[ $ngay ] : array();

	$mh_ds = array_keys( $tach );
	foreach ( array_keys( $dau ) as $mh ) {
		if ( ! in_array( $mh, $mh_ds, true ) ) {
			$mh_ds[] = $mh;
		}
	}
	foreach ( array_keys( $khai ) as $mh ) {
		if ( ! in_array( $mh, $mh_ds, true ) ) {
			$mh_ds[] = $mh;
		}
	}

	/* 🔴 LỌC THEO DANH MỤC HÀNG HOÁ CỦA CƠ SỞ. FABi bán cả đồ pha tại chỗ (BẠC XỈU, CACAO
	   LATTE, COMBO TRÀ CHANH…) — không có kho để đếm. Đổ hết vào sổ thì nhân viên cuộn qua vài
	   chục dòng vô nghĩa mới tới chai nước, và mấy dòng ấy mãi mãi đỏ vì chẳng ai đếm chúng.
	   ⚠️ NHƯNG DÒNG ĐÃ KHAI THÌ KHÔNG ĐƯỢC GIẤU, kể cả khi mặt hàng bị bỏ khỏi danh mục: giấu
	      đi là số người ta đã gõ biến mất khỏi màn mà vẫn nằm trong sổ. Bỏ nhầm một mặt hàng
	      rồi không hiểu vì sao tồn không khớp là chuyện không ai lần ra. */
	$chon = khh_dt_kho_mh_cua( $co_so );
	if ( $chon ) {
		$mh_ds = array_values(
			array_filter(
				$mh_ds,
				function ( $mh ) use ( $chon, $khai ) {
					return in_array( $mh, $chon, true ) || isset( $khai[ $mh ] );
				}
			)
		);
	}
	sort( $mh_ds );

	$ra = array();
	foreach ( $mh_ds as $mh ) {
		$d      = isset( $khai[ $mh ] ) ? $khai[ $mh ] : array();
		/* `null` = chưa ai đặt mốc, nên tồn đầu CHƯA BIẾT. Xem chú thích trong
		   `khh_dt_kho_trang_thai()` — ép về 0 là ngày đầu cả màn ra số âm. */
		$t_dau  = ( isset( $dau[ $mh ] ) && null !== $dau[ $mh ]['ton'] ) ? (float) $dau[ $mh ]['ton'] : null;
		$co_moc = ! empty( $dau[ $mh ]['co_moc'] );
		$nhap   = isset( $d['nhap'] ) ? (float) $d['nhap'] : 0.0;
		$c_tay  = isset( $d['combo_tay'] ) ? (float) $d['combo_tay'] : 0.0;
		$b_le   = isset( $tach[ $mh ]['le'] ) ? (float) $tach[ $mh ]['le'] : 0.0;
		$b_cb   = isset( $tach[ $mh ]['combo'] ) ? (float) $tach[ $mh ]['combo'] : 0.0;
		$b_may  = $b_le + $b_cb;
		/* Chưa biết tồn đầu thì KHÔNG tính ra tồn cuối. Lượt nhập đầu tiên đặt mốc = 0. */
		$goc    = ( null === $t_dau && $nhap > 0 ) ? 0.0 : $t_dau;
		$tinh   = ( null === $goc ) ? null : $goc + $nhap - $b_may - $c_tay;

		$khai_ban = ( isset( $d['ban_khai'] ) && null !== $d['ban_khai'] && '' !== $d['ban_khai'] )
			? (float) $d['ban_khai'] : null;
		$dem      = ( isset( $d['dem'] ) && null !== $d['dem'] && '' !== $d['dem'] )
			? (float) $d['dem'] : null;

		$ra[] = array(
			'mat_hang'  => $mh,
			'ton_dau'   => $t_dau,
			'co_moc'    => $co_moc,
			'nhap'      => $nhap,
			'ban_le'    => $b_le,
			'ban_combo' => $b_cb,
			'ban_may'   => $b_may,
			'combo_tay' => $c_tay,
			'ban_khai'  => $khai_ban,
			'ton_tinh'  => $tinh,
			'dem'       => $dem,
			/* `null` khi chưa khai — KHÁC HẲN số 0. Trộn hai thứ là một ô bỏ trống trông y như
			   một ô đã đếm và đếm đúng, rồi cả sổ xanh mướt trong khi chưa ai đếm gì. */
			'lech_kho'  => ( null === $dem || null === $tinh ) ? null : $dem - $tinh,
			'lech_khai' => ( null === $khai_ban ) ? null : $khai_ban - $b_may,
			'ghi_chu'   => isset( $d['ghi_chu'] ) ? (string) $d['ghi_chu'] : '',
			'nguoi'     => isset( $d['nguoi'] ) ? (string) $d['nguoi'] : '',
		);
	}
	return $ra;
}

/** Mấy món FABi có mà bảng combo chưa khai thành phần — để màn hình nhắc. */
function khh_dt_kho_combo_chua_khai( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$co    = khh_dt_kho_combo_bang();
	$nghi  = array();
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		foreach ( is_array( $mon ) ? $mon : array() as $m ) {
			$ten = isset( $m['n'] ) ? (string) $m['n'] : '';
			if ( '' === $ten || isset( $co[ $ten ] ) || isset( $nghi[ $ten ] ) ) {
				continue;
			}
			/* Đoán theo tên: chỉ để NHẮC, không để tự trừ kho. Đoán sai công thức combo là trừ
			   nhầm kho hàng loạt, mà không dòng nào sai — nên việc đặt thành phần phải do người. */
			if ( preg_match( '~\b(combo|set|gói|suất)\b~iu', $ten ) ) {
				$nghi[ $ten ] = true;
			}
		}
	}
	return array_keys( $nghi );
}

/**
 * MÓN MÁY GHI SỐ LƯỢNG MÀ DOANH THU 0đ.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ĐÂY KHÔNG PHẢI PHÉP DÒ "FABi ĐÃ TÁCH SẴN COMBO" — BẢN TRƯỚC NÓI THẾ LÀ SAI.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 20/09/2026 anh Thắng hỏi *"Theo máy thì nó có tự tách combo có hàng trong đó không"*, và em
 * dựng hàm này để tự trả lời, với lý lẽ: "thành phần combo thì máy ghi số lượng mà doanh thu
 * 0đ". Lý lẽ ấy hỏng, vì `doc-file.php` CỘNG GỘP các món THEO TÊN trong mỗi (ngày × cơ sở):
 *
 *   · Một mặt hàng vừa bán lẻ vừa nằm trong combo -> doanh thu phần bán lẻ kéo tổng lên > 0,
 *     nên nó KHÔNG BAO GIỜ lọt vào danh sách này. Tức đúng trường hợp cần dò thì dò không ra.
 *   · Thứ lọt vào lại là món LÚC NÀO CŨNG 0đ — hàng cho, khuyến mãi, vé online. Anh Thắng gửi
 *     ảnh màn hình và nó liệt kê đúng thế: "BIMBIM LỚN MIỄN PHÍ", "NƯỚC SUỐI DANASI MIỄN PHÍ",
 *     "TRÀ CHANH GIÃ TAY MIỄN PHÍ", "VÉ ONLINE". Không cái nào là thành phần combo.
 *
 * Nên hàm này nay chỉ nói ĐÚNG điều nó biết: mấy món máy ghi 0đ. Chúng CÓ trừ kho (số lượng
 * vẫn vào `ban_may`), và chúng CÓ THỂ là hàng cho, cũng CÓ THỂ là thành phần combo máy đã tách
 * — hệ không phân biệt được, nên phải để người xem quyết định, không được kết luận thay.
 *
 * @return array [ tên món => số lượng ] — món máy ghi số lượng mà doanh thu 0đ.
 */
function khh_dt_kho_mon_khong_tien( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		foreach ( is_array( $mon ) ? $mon : array() as $m ) {
			$ten = isset( $m['n'] ) ? (string) $m['n'] : '';
			$q   = isset( $m['q'] ) ? (float) $m['q'] : 0;
			$rv  = isset( $m['r'] ) ? (float) $m['r'] : 0;
			if ( '' === $ten || $q <= 0 || $rv > 0 ) {
				continue;
			}
			$ra[ $ten ] = ( isset( $ra[ $ten ] ) ? $ra[ $ten ] : 0 ) + $q;
		}
	}
	arsort( $ra );
	return $ra;
}

/**
 * Mặt hàng NGHI bị trừ kho hai lần: vừa có dòng 0đ của máy, vừa bị khai trong bảng combo.
 *
 * ⚠️ NGHI, KHÔNG PHẢI CHẮC — xem chú thích `khh_dt_kho_mon_khong_tien()`. Dòng 0đ có thể là
 *    hàng cho (thì khai combo vẫn đúng), có thể là thành phần máy đã tách (thì khai combo là
 *    trừ hai lần). Hệ nêu ra để người xem kiểm, chứ không kết luận thay.
 */
function khh_dt_kho_tru_hai_lan( $tu, $den, $co_so ) {
	$da_tach = khh_dt_kho_mon_khong_tien( $tu, $den, $co_so );
	if ( ! $da_tach ) {
		return array();
	}
	$ra = array();
	foreach ( khh_dt_kho_combo_bang() as $combo => $tp ) {
		foreach ( array_keys( (array) $tp ) as $mh ) {
			if ( isset( $da_tach[ $mh ] ) && ! in_array( $mh, $ra, true ) ) {
				$ra[] = $mh;
			}
		}
	}
	return $ra;
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_kho_route' );
function khh_dt_kho_route() {
	register_rest_route(
		'khh-dt/v1',
		'/kho',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_kho_xem',
				'permission_callback' => 'khh_dt_duoc_xem',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_kho_luu',
				/* Khai kho là việc của nhân viên trực, cùng mức quyền với nhập báo cáo ngày. */
				'permission_callback' => 'khh_dt_duoc_ghi',
			),
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/kho-mat-hang',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_kho_mat_hang',
			/* Đổi danh mục là đổi những gì cả cơ sở phải đếm mỗi ngày — chỉ người được ghi. */
			'permission_callback' => 'khh_dt_duoc_ghi',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/kho-combo',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_kho_combo',
			/* Đặt công thức combo là đổi cách trừ kho của MỌI ngày — chỉ người được ghi. */
			'permission_callback' => 'khh_dt_duoc_ghi',
		)
	);
}

function khh_dt_rest_kho_xem( $req ) {
	$ngay  = (string) $req->get_param( 'ngay' );
	$co_so = (string) $req->get_param( 'co_so' );
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ngay ) ) {
		$ngay = current_time( 'Y-m-d' );
	}
	if ( '' === $co_so ) {
		return new WP_Error( 'khh_dt_kho', 'Chưa chọn cơ sở.', array( 'status' => 400 ) );
	}
	/* 🔴 GÁC CẢ ĐƯỜNG ĐỌC, KHÔNG CHỈ ĐƯỜNG GHI.
	   Bản trước bỏ trống chỗ này với lý do "`khh_dt_duoc_cua_hang()` đòi quyền GHI nên không
	   dùng được ở màn xem" — đúng về mặt hàm, sai về mặt kết luận: bỏ luôn phép gác thì cửa
	   hàng trưởng quán này đổi một chữ trên thanh địa chỉ là đọc được sổ kho, tồn hàng và cả
	   phần khai của quán kia. `bao-cao-ngay.php` đã vấp đúng chỗ này và xử bằng `khh_dt_co_so_ds()`
	   — phạm vi cơ sở của người dùng, KHÔNG dính tới quyền ghi. Ở đây làm y như thế. */
	$cho_phep = function_exists( 'khh_dt_co_so_ds' ) ? khh_dt_co_so_ds() : array();
	if ( $cho_phep && ! in_array( $co_so, $cho_phep, true ) ) {
		return new WP_Error( 'khh_dt_kho', 'Anh/chị không phụ trách cơ sở này.', array( 'status' => 403 ) );
	}
	return array(
		'ngay'       => $ngay,
		'co_so'      => $co_so,
		'dong'       => khh_dt_kho_bang_ngay( $ngay, $co_so ),
		'combo'      => khh_dt_kho_combo_bang(),
		/* Danh mục hàng hoá của cơ sở, và mọi món FABi từng ghi ở đây — để màn bày ra cho chọn. */
		'mat_hang'   => khh_dt_kho_mh_cua( $co_so ),
		'mon_da_thay' => khh_dt_kho_mon_da_thay(
			gmdate( 'Y-m-d', strtotime( $ngay ) - 90 * DAY_IN_SECONDS ),
			$ngay,
			$co_so
		),
		/* Món máy ghi 0đ — CHỈ vậy thôi, không kết luận là thành phần combo. Xem chú thích
		   dài ở `khh_dt_kho_mon_khong_tien()`: phép dò cũ nói quá điều nó biết. */
		'mon_khong_tien' => khh_dt_kho_mon_khong_tien(
			gmdate( 'Y-m-d', strtotime( $ngay ) - 30 * DAY_IN_SECONDS ),
			$ngay,
			$co_so
		),
		'tru_hai_lan'   => khh_dt_kho_tru_hai_lan(
			gmdate( 'Y-m-d', strtotime( $ngay ) - 30 * DAY_IN_SECONDS ),
			$ngay,
			$co_so
		),
		'combo_nghi' => khh_dt_kho_combo_chua_khai(
			gmdate( 'Y-m-d', strtotime( $ngay ) - 30 * DAY_IN_SECONDS ),
			$ngay,
			$co_so
		),
		'duoc_ghi'   => function_exists( 'khh_dt_duoc_ghi' ) ? khh_dt_duoc_ghi() : false,
	);
}

function khh_dt_rest_kho_luu( $req ) {
	$ngay  = (string) $req->get_param( 'ngay' );
	$co_so = (string) $req->get_param( 'co_so' );
	$tho   = $req->get_param( 'dong' );
	$dong  = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ngay ) || '' === $co_so ) {
		return new WP_Error( 'khh_dt_kho', 'Thiếu ngày hoặc cơ sở.', array( 'status' => 400 ) );
	}
	if ( ! is_array( $dong ) || ! $dong ) {
		return new WP_Error( 'khh_dt_kho', 'Không có dòng nào để lưu.', array( 'status' => 400 ) );
	}
	if ( function_exists( 'khh_dt_duoc_cua_hang' ) && ! khh_dt_duoc_cua_hang( $co_so ) ) {
		return new WP_Error( 'khh_dt_kho', 'Anh/chị không được khai kho của cơ sở này.', array( 'status' => 403 ) );
	}
	$n = 0;
	foreach ( $dong as $d ) {
		$mh = isset( $d['mat_hang'] ) ? sanitize_text_field( (string) $d['mat_hang'] ) : '';
		if ( '' === $mh ) {
			continue;
		}
		$n += khh_dt_kho_ghi( $ngay, $co_so, $mh, $d ) ? 1 : 0;
	}
	$r          = khh_dt_rest_kho_xem( $req );
	$r['da_ghi'] = $n;
	return $r;
}

function khh_dt_rest_kho_mat_hang( $req ) {
	$co_so = (string) $req->get_param( 'co_so' );
	if ( '' === $co_so ) {
		return new WP_Error( 'khh_dt_kho', 'Chưa chọn cơ sở.', array( 'status' => 400 ) );
	}
	$tho = $req->get_param( 'ds' );
	$ds  = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	/* Gửi danh sách RỖNG là hợp lệ — nghĩa là "thôi lọc, bày hết". Không được coi là lỗi. */
	khh_dt_kho_mh_dat( $co_so, is_array( $ds ) ? $ds : array() );
	return khh_dt_rest_kho_xem( $req );
}

function khh_dt_rest_kho_combo( $req ) {
	$ten = (string) $req->get_param( 'ten' );
	$tho = $req->get_param( 'thanh_phan' );
	$tp  = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	if ( '' === trim( $ten ) ) {
		return new WP_Error( 'khh_dt_kho', 'Thiếu tên combo.', array( 'status' => 400 ) );
	}
	khh_dt_kho_combo_dat( $ten, is_array( $tp ) ? $tp : array() );
	return array( 'combo' => khh_dt_kho_combo_bang() );
}
