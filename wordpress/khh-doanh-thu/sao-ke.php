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
 * BẢNG NHẬN MẶT CƠ SỞ: [ [ 'khoa', 'cua_hang', 'kieu' ], … ].
 *
 * ==================================================================================================
 * 🔴 KHOÁ CHÍNH LÀ *MÃ NỘP TIỀN*, KHÔNG PHẢI TÊN QUÁN VIẾT TRONG NỘI DUNG.
 * ==================================================================================================
 * Anh Thắng 16/09/2026 gửi một dòng sao kê thật:
 *
 *     NHAN TU 050066112230 TRACE 213493 ND IBFT VC Bien Hoa KH705MTDMN0023 — 590.000 vào
 *
 * Nhà mình đã có sẵn lối làm này: mỗi cơ sở một **mã nộp tiền** (`KH705MTDMN0001`,
 * `KH705MTDMN0020`, …) và người nộp gõ mã ấy vào nội dung chuyển khoản. Mã thì cố định, còn tên
 * quán người ta gõ mỗi hôm một kiểu — "Tutu Tan Phu", "TT TÂN PHÚ", "tan phu nop" — nên nhận mặt
 * theo mã là chắc, nhận theo tên là hên xui.
 *
 * ⚠️ MÃ PHẢI KHỚP TRỌN, KHÔNG ĐƯỢC KHỚP LỒNG NHAU. `KH705MTDMN0002` (Aeon Tân Phú) nằm gọn bên
 *    trong `KH705MTDMN0020` (Aeon Bình Dương). Tìm kiểu "có chứa" là tiền của Bình Dương chạy
 *    thẳng vào sổ Tân Phú — sai âm thầm, và sai đúng theo một chiều cố định nên nhìn bảng không
 *    ai thấy gì lạ. Nên `kieu = 'ma'` khớp có RANH GIỚI hai đầu.
 *
 * `kieu = 'chu'` là lối cũ, khớp lỏng — vẫn giữ cho những khoản người ta quên gõ mã, chỉ ghi
 * "TUTU TAN PHU NOP 15/09", và cho việc khai theo số tài khoản riêng của quán.
 */
function khh_dt_ghep_bank_ds() {
	$x  = get_option( 'khh_dt_ghep_bank', array() );
	$ds = is_array( $x ) ? $x : array();
	foreach ( $ds as $i => $d ) {
		if ( ! isset( $d['kieu'] ) ) {
			$ds[ $i ]['kieu'] = khh_dt_kieu_khoa( isset( $d['khoa'] ) ? $d['khoa'] : '' );
		}
		/* Bảng khai từ bản cũ chưa có chữ để hiện — lấy tạm chính khoá, và viết hoa nếu trông
		   như mã, để mấy ô đã khai thôi hiện chữ thường. */
		if ( ! isset( $d['hien'] ) || '' === $d['hien'] ) {
			$k = (string) ( isset( $d['khoa'] ) ? $d['khoa'] : '' );
			$ds[ $i ]['hien'] = ( 'ma' === $ds[ $i ]['kieu'] ) ? strtoupper( $k ) : $k;
		}
	}
	return $ds;
}

/**
 * Khoá này là MÃ nộp tiền hay mẩu CHỮ?
 *
 * Mã thì liền một cục, có cả chữ lẫn số, từ 6 ký tự trở lên — `KH705MTDMN0023`. Mẩu chữ thì có
 * khoảng trắng hoặc toàn chữ cái — "tutu tan phu". Đoán sai chiều nào cũng không mất tiền: mã mà
 * bị coi là chữ thì khớp lỏng hơn (vẫn đúng quán, chỉ kém chặt); chữ mà bị coi là mã thì đòi
 * khớp trọn nên cùng lắm là không nhận ra, và khoản ấy hiện lên ở mục "chưa gán".
 */
function khh_dt_kieu_khoa( $khoa ) {
	$k = trim( (string) $khoa );
	return ( preg_match( '/^[a-z0-9._-]{6,}$/i', $k ) && preg_match( '/\d/', $k ) ) ? 'ma' : 'chu';
}

/**
 * Khai lại cả bảng.
 *
 * Nhận hai hình dạng, vì màn khai bày theo CƠ SỞ còn trong máy lưu theo KHOÁ:
 *   · [ [ 'khoa' => …, 'cua_hang' => … ], … ]        (hình dạng lưu)
 *   · [ 'Tên cơ sở POS' => 'MA1, MA2', … ]            (hình dạng màn khai gửi lên)
 */
function khh_dt_dat_ghep_bank( $ds ) {
	$sach = array();
	$them = function ( $khoa, $ch ) use ( &$sach ) {
		/* 🔴 GIỮ NGUYÊN CHỮ NGƯỜI TA GÕ ĐỂ HIỆN LẠI, chỉ hạ chữ thường ở BẢN ĐEM ĐI SO.
		   Anh Thắng 16/09/2026 gõ `KH705KVCMN0002` rồi mở lại thấy `kh705kvcmn0002` — khớp vẫn
		   đúng (hai đầu cùng hạ chữ), nhưng người khai nhìn là tưởng hệ làm hỏng mã của mình,
		   rồi ngồi sửa lại từng ô. Hiện sai cũng là một loại hỏng. */
		$hien = trim( sanitize_text_field( (string) $khoa ) );
		$khoa = khh_dt_khong_dau( $hien );
		$ch   = sanitize_text_field( (string) $ch );
		if ( '' === $khoa || '' === $ch ) {
			return;
		}
		foreach ( $sach as $x ) {
			if ( $x['khoa'] === $khoa ) {
				return;                          // một khoá chỉ thuộc về một cơ sở
			}
		}
		$sach[] = array(
			'khoa'     => $khoa,
			'hien'     => $hien,
			'cua_hang' => $ch,
			'kieu'     => khh_dt_kieu_khoa( $khoa ),
		);
	};
	foreach ( (array) $ds as $k => $d ) {
		if ( is_array( $d ) ) {
			$them( isset( $d['khoa'] ) ? $d['khoa'] : '', isset( $d['cua_hang'] ) ? $d['cua_hang'] : '' );
			continue;
		}
		/* [ tên cơ sở => "MA1, MA2" ] — một cơ sở khai được nhiều mã: đổi mã giữa chừng thì mã cũ
		   vẫn phải nhận ra, không thì mấy tháng sao kê cũ hoá thành "chưa gán". */
		foreach ( preg_split( '/[,;\n]+/', (string) $d ) as $m ) {
			$them( $m, $k );
		}
	}
	update_option( 'khh_dt_ghep_bank', $sach, false );
	return $sach;
}

/** Bày theo cơ sở để màn khai dựng bảng: [ 'tên cơ sở' => [ mã, … ] ]. */
function khh_dt_ghep_theo_co_so() {
	$ra = array();
	foreach ( khh_dt_ghep_bank_ds() as $d ) {
		$ra[ $d['cua_hang'] ][] = ( isset( $d['hien'] ) && '' !== $d['hien'] ) ? $d['hien'] : $d['khoa'];
	}
	return $ra;
}

/**
 * Nhận mặt cơ sở của một giao dịch; '' nếu chưa nhận ra.
 *
 * ⚠️ MÃ XÉT TRƯỚC CHỮ, VÀ KHOÁ DÀI XÉT TRƯỚC KHOÁ NGẮN. Một nội dung có thể vừa mang mã nộp tiền
 *    vừa mang tên quán — mà mã mới là thứ chắc chắn. Còn giữa hai mẩu chữ, "TUTU TAN PHU" và
 *    "TUTU TAN" cùng khớp thì phải để cái dài thắng, không thì tiền quán này chạy sang sổ quán kia.
 */
function khh_dt_doan_co_so( $noi_dung, $tai_khoan ) {
	$chuoi = khh_dt_khong_dau( (string) $noi_dung . ' ' . (string) $tai_khoan );
	if ( '' === trim( $chuoi ) ) {
		return '';
	}
	$ds = khh_dt_ghep_bank_ds();
	usort(
		$ds,
		function ( $a, $b ) {
			$ka = 'ma' === $a['kieu'] ? 1 : 0;
			$kb = 'ma' === $b['kieu'] ? 1 : 0;
			if ( $ka !== $kb ) {
				return $kb - $ka;                                  // mã trước, chữ sau
			}
			return strlen( $b['khoa'] ) - strlen( $a['khoa'] );    // rồi dài trước, ngắn sau
		}
	);
	foreach ( $ds as $d ) {
		if ( 'ma' === $d['kieu'] ) {
			/* Ranh giới hai đầu: KH705MTDMN0002 KHÔNG được khớp vào KH705MTDMN0020. */
			if ( preg_match( '/(?<![a-z0-9])' . preg_quote( $d['khoa'], '/' ) . '(?![a-z0-9])/', $chuoi ) ) {
				return (string) $d['cua_hang'];
			}
			continue;
		}
		if ( false !== strpos( $chuoi, $d['khoa'] ) ) {
			return (string) $d['cua_hang'];
		}
	}
	return '';
}

/**
 * Mã nộp tiền có trong một nội dung chuyển khoản, kể cả mã chưa khai.
 *
 * Dùng để MÁCH cho người khai: khoản chưa nhận ra cơ sở thì màn bày sẵn mã đọc được trong nội
 * dung, bấm một cái là gán cho cơ sở. Không có nó thì người khai phải tự nhìn một dòng sao kê
 * dài loằng ngoằng mà lọc ra cụm nào là mã — mỗi khoản một lần, và mỗi lần là một cơ hội gõ sai.
 *
 * ⚠️ CHỈ MÁCH, KHÔNG TỰ GÁN. Đọc được một mã không có nghĩa là biết nó của quán nào; tự gán bừa
 *    là tiền vào nhầm sổ mà lại trông như đã đối soát xong.
 */
function khh_dt_ma_trong_nd( $noi_dung ) {
	$s = strtoupper( (string) $noi_dung );
	if ( preg_match_all( '/(?<![A-Z0-9])([A-Z]{2,}[A-Z0-9]*\d[A-Z0-9]*)(?![A-Z0-9])/', $s, $m ) ) {
		foreach ( $m[1] as $x ) {
			/* Bỏ mấy cụm không phải mã cơ sở: số tài khoản thuần số đã bị loại bởi `[A-Z]{2,}`
			   ở đầu, còn lại loại cụm quá ngắn. */
			if ( strlen( $x ) >= 8 ) {
				return $x;
			}
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

/**
 * Tiền ngân hàng thật sự nhận được, theo ngày × cơ sở.
 *
 * Trả [ 'ngay|cơ sở' => [ 'tien', 'so_lan', 'gio_dau', 'ngay_nop', 'noi_dung' ] ].
 *
 * 🔴 KÈM GIỜ VÀ NGÀY NỘP THẬT, KHÔNG CHỈ SỐ TIỀN. Câu anh Thắng hỏi là *"xem nhân viên nộp tiền
 *    chưa"* — mà "chưa nộp" với "nộp muộn ba ngày" là hai chuyện khác hẳn nhau, và một cột tổng
 *    tiền không phân biệt được. Nộp muộn đều đặn là tiền nằm trong tay người ta mấy ngày liền:
 *    chưa mất, nhưng là chỗ để mất.
 */
function khh_dt_nop_bank( $tu = '', $den = '' ) {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	$sql  = "SELECT ngay_tinh, cua_hang, SUM(so_tien) t, COUNT(*) n,
				MIN(gio) g, MIN(ngay) nd, MAX(ngay) nc, MIN(noi_dung) nn
			FROM $bang WHERE cua_hang<>''";
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
		$ra[ $r['ngay_tinh'] . '|' . $r['cua_hang'] ] = array(
			'tien'     => (float) $r['t'],
			'so_lan'   => (int) $r['n'],
			'gio_dau'  => (int) $r['g'],
			'ngay_nop' => (string) $r['nd'],
			'ngay_cuoi' => (string) $r['nc'],
			'noi_dung' => (string) $r['nn'],
		);
	}
	return $ra;
}

/**
 * Khoản tiền của ngày này đã ĐẾN HẠN NỘP chưa.
 *
 * ⚠️ ĐỪNG GỌI TÊN NGƯỜI TA KHI HỌ CHƯA ĐẾN HẠN. Tiền bán hôm nay thì sáng mai mới mang ra ngân
 *    hàng — bôi đỏ ngay tối nay là bảng lúc nào cũng có vài dòng đỏ vô nghĩa, và một bảng lúc nào
 *    cũng đỏ thì không ai nhìn nữa, kể cả hôm đỏ thật.
 */
function khh_dt_qua_han_nop( $ngay ) {
	if ( '' === (string) $ngay ) {
		return false;
	}
	$han = strtotime( $ngay . ' +1 day' ) + khh_dt_gio_cat() * 3600;
	return (int) current_time( 'timestamp' ) > $han;
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
 * KÉO THẲNG TỪ CỔNG SEPAY CỦA PLUGIN GHẾ MASSAGE
 * ================================================================== */

/**
 * Site này đã có cổng SePay rồi — nó nằm trong plugin Ghế Massage (`VHG_Thu`), hứng webhook mỗi
 * lần có tiền vào và ghi theo mã tham chiếu của ngân hàng. Nạp file tay là việc thừa nếu số ấy
 * đã nằm sẵn trong cùng một cơ sở dữ liệu.
 *
 * ==================================================================================================
 * 🔴 BẪY: SỔ ẤY CÓ CẢ TIỀN KHÁCH TRẢ GHẾ, KHÔNG PHẢI CHỈ TIỀN NHÂN VIÊN NỘP.
 * ==================================================================================================
 * Cùng một tài khoản ngân hàng nhận hai dòng tiền khác hẳn nhau về ý nghĩa:
 *
 *   · khách quét QR trả tiền một lượt ghế  -> đó là DOANH THU, tiền vốn đã ở ngân hàng;
 *   · cửa hàng trưởng mang tiền mặt đi nộp -> đó là NỘP TIỀN, cái mình đang đi soi.
 *
 * Cộng nhầm loại thứ nhất vào "đã nộp" là biến cả phép đối soát thành vô nghĩa: quán nào có nhiều
 * ghế thì tự nhiên "nộp đủ" mà không ai mang đồng nào ra ngân hàng. Nên ở đây BỎ mọi giao dịch
 * mà bên ghế đã nhận ra là của một cái ghế (`ma_may` khác rỗng) hoặc của một đơn mua mã.
 *
 * ⚠️ BẢN TRÊN SITE CÓ THỂ MỚI HƠN BẢN TRONG KHO (kho 1.47.0, site 2.81.0 — 16/09/2026). Nên hỏi
 *    bảng và hỏi từng cột trước khi đọc, chứ không tin cấu trúc mình đang thấy.
 */
function khh_dt_co_cong_ghe() {
	global $wpdb;
	if ( ! class_exists( 'VHG_DB' ) || ! method_exists( 'VHG_DB', 't' ) ) {
		return false;
	}
	$bang = VHG_DB::t( 'thu' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) );
}

/**
 * Kéo giao dịch ngân hàng từ sổ của plugin Ghế sang kho sao kê.
 *
 * Trả [ 'ok', 'keo' => số khoản mang sang, 'bo_ghe' => số khoản là tiền khách trả ghế,
 *       'chua_gan' => còn bao nhiêu khoản chưa nhận ra cơ sở ].
 */
function khh_dt_keo_ghe( $tu_ngay = '' ) {
	global $wpdb;
	/* ⚠️ GÁC CÙNG THÂN HÀM với lời gọi — gác ở hàm khác thì người đọc sau không thấy được. */
	if ( ! class_exists( 'VHG_DB' ) || ! method_exists( 'VHG_DB', 't' ) ) {
		return array(
			'ok'    => false,
			'error' => 'Chưa cài plugin Ghế Massage (K&H) trên site này — cổng SePay nằm trong đó.',
		);
	}
	$bang = VHG_DB::t( 'thu' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array( 'ok' => false, 'error' => 'Không thấy bảng thu của plugin Ghế Massage.' );
	}

	/* Hỏi từng cột — bản bên ấy có thể mới hơn hoặc cũ hơn bản mình đang đọc. */
	$co_cot = function ( $ten ) use ( $wpdb, $bang ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $bang LIKE %s", $ten ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	};
	foreach ( array( 'ref', 'luc', 'so_tien', 'noi_dung' ) as $c ) {
		if ( ! $co_cot( $c ) ) {
			return array( 'ok' => false, 'error' => 'Bảng thu bên Ghế Massage thiếu cột "' . $c . '".' );
		}
	}
	$co_may  = $co_cot( 'ma_may' );
	$co_lenh = $co_cot( 'ma_lenh' );
	$co_ch   = $co_cot( 'ma_ch' );
	$co_huy  = $co_cot( 'huy' );
	$co_ng   = $co_cot( 'nguon' );

	$cot = 'ref, luc, so_tien, noi_dung'
		. ( $co_may ? ', ma_may' : '' ) . ( $co_lenh ? ', ma_lenh' : '' )
		. ( $co_ch ? ', ma_ch' : '' ) . ( $co_ng ? ', nguon' : '' );
	$sql  = "SELECT $cot FROM $bang WHERE so_tien > 0";
	$args = array();
	if ( $co_huy ) {
		$sql .= ' AND huy = 0';
	}
	if ( $co_ng ) {
		/* Tiền mặt thu tại quầy (`cash`) KHÔNG phải tiền đã về ngân hàng — chính nó là khoản
		   đang chờ được mang đi nộp. Chỉ lấy những nguồn thật sự đi qua ngân hàng. */
		$sql .= " AND nguon IN ('sepay','vietqr','qr')";
	}
	if ( $tu_ngay ) {
		$sql   .= ' AND luc >= %s';
		$args[] = $tu_ngay . ' 00:00:00';
	}
	$sql .= ' ORDER BY luc ASC LIMIT 20000';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	// phpcs:enable

	$mang   = array();
	$bo_ghe = 0;
	foreach ( (array) $ds as $r ) {
		/* 🔴 Bên ghế đã nhận ra đây là tiền của một cái ghế -> đó là DOANH THU của khách, không
		   phải nhân viên mang tiền đi nộp. Xem khối chú thích đầu mục này. */
		if ( ( $co_may && '' !== trim( (string) $r['ma_may'] ) )
			|| ( $co_lenh && '' !== trim( (string) $r['ma_lenh'] ) ) ) {
			$bo_ghe++;
			continue;
		}
		$luc = (string) $r['luc'];
		$ngay = substr( $luc, 0, 10 );
		$gio  = (int) substr( $luc, 11, 2 );
		$nd   = (string) $r['noi_dung'];
		$tk   = $co_ch ? (string) $r['ma_ch'] : '';
		$mang[] = array(
			/* Tiền tố `ghe-` để phân biệt với khoản nạp từ file, và để kéo lại bao nhiêu lần
			   cũng chỉ ra một hàng. */
			'ma_gd'     => 'ghe-' . (string) $r['ref'],
			'ngay'      => $ngay,
			'gio'       => $gio,
			'ngay_tinh' => khh_dt_ngay_quy( $ngay, $gio ),
			'so_tien'   => (float) $r['so_tien'],
			'noi_dung'  => $nd,
			'tai_khoan' => $tk,
			'cua_hang'  => khh_dt_doan_co_so( $nd, $tk ),
		);
	}
	$n = khh_dt_ghi_sao_ke( $mang );
	return array(
		'ok'       => true,
		'keo'      => $n,
		'bo_ghe'   => $bo_ghe,
		'chua_gan' => khh_dt_sk_chua_gan(),
	);
}

/** Kéo lại mỗi giờ, để màn đối soát không phải chờ ai bấm nút. */
add_action( 'khh_dt_cron_keo', 'khh_dt_cron_keo_chay' );
function khh_dt_cron_keo_chay() {
	/* Chỉ kéo 45 ngày gần đây: sổ bên kia có thể hàng trăm nghìn dòng, mà đối soát chỉ nhìn kỳ
	   gần. Cần kỳ cũ thì bấm nút kéo tay, nó không giới hạn ngày. */
	$tu    = gmdate( 'Y-m-d', time() - 45 * 86400 );
	$nguon = khh_dt_nguon_dang_chon();
	if ( $nguon ) {
		khh_dt_keo_nguon( $nguon, $tu );
		return;
	}
	if ( khh_dt_co_cong_ghe() ) {
		khh_dt_keo_ghe( $tu );
	}
}

function khh_dt_bat_keo() {
	if ( ! wp_next_scheduled( 'khh_dt_cron_keo' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'khh_dt_cron_keo' );
	}
}

function khh_dt_tat_keo() {
	$t = wp_next_scheduled( 'khh_dt_cron_keo' );
	if ( $t ) {
		wp_unschedule_event( $t, 'khh_dt_cron_keo' );
	}
}

/* ================================================================== *
 * DÒ NGUỒN SAO KÊ CÓ SẴN TRONG CHÍNH CƠ SỞ DỮ LIỆU NÀY
 * ================================================================== */

/**
 * Site này có thể đã có sẵn một plugin hứng sao kê rồi — và có thật: trang IT liệt kê
 * *"Sao Kê Ngân Hàng (SePay) 0.34.0"*, màn của nó bày đủ Ngày GD · Ngân hàng · Số TK · Nội dung ·
 * Mã GD · Tiền vào · Nhãn phân loại. Bắt anh Thắng tải file về rồi nạp tay trong khi số ấy đang
 * nằm ngay trong cùng một MySQL là việc thừa, và là chỗ để hai sổ lệch nhau.
 *
 * Mà mã của plugin ấy KHÔNG nằm trong kho này, nên không đoán được tên bảng. Nên ở đây đi dò:
 * quét tên bảng, đọc tên cột, và chỉ nhận bảng nào có đủ ngày + tiền + nội dung. Rồi BÀY RA cho
 * người khai chọn, kèm số dòng và khoảng ngày để họ nhận ra bảng nào là bảng thật.
 *
 * ⚠️ DÒ XONG PHẢI HỎI, KHÔNG TỰ CHỌN. Một site có thể có mấy bảng trông giống nhau (bảng của
 *    plugin, bảng nháp, bảng của bản cũ). Tự chọn nhầm là đối soát bằng một sổ chết mà vẫn xanh.
 */
function khh_dt_cot_nguon() {
	/* ⚠️ DANH SÁCH NÀY LỚN DẦN THEO THỰC TẾ, KHÔNG THEO TRÍ TƯỞNG TƯỢNG. `thoi_diem` thiếu ở bản
	   đầu, và đúng vì thiếu nó mà sổ `wpt9_saoke_cong` (34.860 dòng) không hiện ra trong danh
	   sách dò — anh Thắng phải mở đường chọn tay mới thấy (16/09/2026). Mỗi tên thêm vào đây là
	   một lần bớt phải chọn tay. */
	return array(
		'ngay'       => array( 'thoi_diem', 'ngay_gd', 'ngay_giao_dich', 'thoi_gian_gd', 'ngay_gio',
			'ngay', 'luc', 'thoi_gian', 'created_at', 'transaction_date', 'transactiondate' ),
		/* `tien` — tên cột của sổ thật `wpt9_saoke_gd`. Thiếu nó nên máy dò ra bảng mà vẫn bỏ
		   trống ô "Số tiền vào", người khai phải tự chỉ. */
		'so_tien'    => array( 'tien_vao', 'so_tien', 'tien', 'amount', 'amount_in', 'credit', 'ghi_co' ),
		'tien_ra'    => array( 'tien_ra', 'debit', 'ghi_no' ),
		'noi_dung'   => array( 'noi_dung', 'mo_ta', 'dien_giai', 'description', 'content', 'remark' ),
		'ma_gd'      => array( 'ma_gd', 'ma_giao_dich', 'ref', 'reference_code', 'ma_tham_chieu', 'khoa', 'id' ),
		'tai_khoan'  => array( 'so_tk', 'so_tai_khoan', 'tai_khoan', 'account_number' ),
		/* `ch_chuan` trước `ch_file`: sổ `wpt9_saoke_congfile` giữ cả tên gốc trong file MoMo lẫn
		   tên đã chuẩn hoá — lấy bản đã chuẩn hoá thì đỡ một lần ghép mờ. Thiếu hai tên này nên
		   màn khai bắt "phải chỉ cột Tên cửa hàng" trong khi cột ấy nằm ngay đó (16/09/2026). */
		'nhan'       => array( 'ch_chuan', 'ch_file', 'nhan', 'nhan_phan_loai', 'nhan_luc', 'co_so',
			'cua_hang', 'ten_cua_hang', 'ten_ch', 'ten_khai', 'diem_ban', 'ma_ch' ),
		/* 🔴 CỘT ĐẾM GIAO DỊCH = DẤU HIỆU SỔ ĐÃ GỘP THEO NGÀY. Một dòng ở đó là cả một ngày của
		   một quán, không phải một giao dịch — đem đi đối soát từng giao dịch là so 188 "giao
		   dịch" với vài nghìn, sai từ dòng đầu. */
		'dem'        => array( 'so_dong', 'so_gd', 'so_giao_dich', 'so_luong', 'so_ban_ghi' ),
		'ma_may'     => array( 'ma_may', 'may_tay' ),
		/* 🔴 `loai` LÀ CHIỀU TIỀN, KHÔNG PHẢI NGUỒN — và phải xét TRƯỚC `nguon`, vì một cột chỉ
		   được nhận một vai. Sổ `wpt9_saoke_gd` ghi `loai = in`; xếp nhầm nó vào "nguồn" thì cột
		   chiều bỏ trống, và mọi khoản tiền ĐI cũng được cộng vào phần "đã nộp" — báo cáo đẹp lên
		   mà không ai nộp thêm đồng nào. */
		'huong'      => array( 'huong', 'chieu', 'direction', 'loai' ),
		'trang_thai' => array( 'trang_thai', 'status' ),
		'nguon'      => array( 'nguon', 'nguon_tien' ),
		'huy'        => array( 'huy', 'da_huy', 'cancelled' ),
	);
}

/**
 * Các bảng trong site trông như sổ giao dịch ngân hàng.
 *
 * ⚠️ DÒ THEO CỘT, KHÔNG DÒ THEO TÊN BẢNG. Bản đầu lọc tên bảng bằng một danh sách chữ
 *    (`sepay|sao_ke|bank|…`) — và nó trượt đúng cái bảng cần tìm: màn của anh Thắng 16/09/2026
 *    chỉ hiện `wpt9_vhg_thu` và `wpt9_vhg_sao_ke`, còn sổ thật của plugin "Sao Kê Ngân Hàng
 *    (SePay)" thì không có trong danh sách, vì tên nó không chứa chữ nào mình đoán.
 *
 *    Tên bảng là thứ người khác đặt, mình không đoán được. Nhưng CỘT thì phải có: một sổ tiền
 *    ngân hàng nào cũng phải có ngày, có số tiền, có nội dung. Nên quét mọi bảng mang tiền tố
 *    của site rồi soi cột — bảng nào đủ ba thứ ấy mới là ứng viên.
 */
function khh_dt_nguon_ds() {
	global $wpdb;
	/* ⚠️ QUÉT MỌI BẢNG, KHÔNG CHỈ BẢNG MANG TIỀN TỐ CỦA WORDPRESS. Plugin có thể tạo bảng riêng
	   không theo tiền tố `wpt9_`, và lọc theo tiền tố là bỏ sót đúng cái bảng cần tìm. */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$bang_ds = (array) $wpdb->get_col( 'SHOW TABLES' );
	$ra      = array();
	/* Mấy bảng lõi của WordPress thì chắc chắn không phải sổ tiền — bỏ sớm cho nhanh. */
	$bo = array( 'posts', 'postmeta', 'comments', 'commentmeta', 'options', 'users', 'usermeta',
		'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'links' );
	foreach ( $bang_ds as $b ) {
		if ( $b === khh_dt_bang_sk() ) {
			continue;                                   // kho của chính mình
		}
		if ( in_array( substr( $b, strlen( $wpdb->prefix ) ), $bo, true ) ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$cot_ds = (array) $wpdb->get_col( "SHOW COLUMNS FROM `$b`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$thuong = array_map( 'strtolower', $cot_ds );
		$map    = array();
		foreach ( khh_dt_cot_nguon() as $vai => $ten_ds ) {
			$map[ $vai ] = '';
			foreach ( $ten_ds as $t ) {
				$i = array_search( $t, $thuong, true );
				if ( false !== $i ) {
					$map[ $vai ] = $cot_ds[ $i ];
					break;
				}
			}
		}
		if ( '' === $map['ngay'] || '' === $map['so_tien'] || '' === $map['noi_dung'] ) {
			continue;                                   // thiếu một trong ba thì không phải sổ tiền
		}
		/* Cột ngày phải thật sự là ngày. Bảng nhật ký nào cũng có `noi_dung` và một cột số tên
		   `so_tien`… ít khi, nhưng có bảng cấu hình mang đủ ba tên mà rỗng nghĩa. */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$kieu = (string) $wpdb->get_var( $wpdb->prepare(
			'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s',
			$b,
			$map['ngay']
		) );
		if ( $kieu && ! in_array( strtolower( $kieu ), array( 'date', 'datetime', 'timestamp', 'varchar', 'char', 'int', 'bigint' ), true ) ) {
			continue;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$n   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$b`" );
		$bien = $wpdb->get_row( "SELECT MIN(`{$map['ngay']}`) tu, MAX(`{$map['ngay']}`) den FROM `$b`", ARRAY_A );
		// phpcs:enable
		/* 🔴 KÈM HAI DÒNG LÀM MẪU. Tên bảng thì người khai không nhận ra, nhưng nhìn một dòng nội
		   dung chuyển khoản là biết ngay có phải sổ sao kê ngân hàng hay không. Không có mẫu thì
		   chọn nguồn là đoán mò — mà chọn nhầm sổ là cả bảng đối soát nói sai. */
		$mau = array();
		if ( $n > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			foreach ( (array) $wpdb->get_results(
				"SELECT `{$map['ngay']}` ngay, `{$map['so_tien']}` tien, `{$map['noi_dung']}` nd FROM `$b` ORDER BY `{$map['ngay']}` DESC LIMIT 2", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			) as $m ) {
				$mau[] = array(
					'ngay' => substr( (string) $m['ngay'], 0, 16 ),
					'tien' => khh_dt_so( $m['tien'] ),
					'nd'   => mb_substr( (string) $m['nd'], 0, 90 ),
				);
			}
		}
		$ra[] = array(
			'bang'     => $b,
			'so_dong'  => $n,
			'tu_ngay'  => $bien ? substr( (string) $bien['tu'], 0, 10 ) : '',
			'den_ngay' => $bien ? substr( (string) $bien['den'], 0, 10 ) : '',
			'cot'      => $map,
			'mau'      => $mau,
		);
	}
	usort(
		$ra,
		function ( $a, $b ) {
			/* Bảng có dòng lên trước bảng rỗng; trong cùng nhóm thì tên gợi ý sổ ngân hàng lên
			   trước, rồi mới tới bảng nhiều dòng hơn. */
			$ca = $a['so_dong'] > 0 ? 1 : 0;
			$cb = $b['so_dong'] > 0 ? 1 : 0;
			if ( $ca !== $cb ) {
				return $cb - $ca;
			}
			$ga = preg_match( '/sepay|sao_?ke|saoke|bank|ngan_?hang|giao_?dich/i', $a['bang'] ) ? 1 : 0;
			$gb = preg_match( '/sepay|sao_?ke|saoke|bank|ngan_?hang|giao_?dich/i', $b['bang'] ) ? 1 : 0;
			if ( $ga !== $gb ) {
				return $gb - $ga;
			}
			return $b['so_dong'] - $a['so_dong'];
		}
	);
	return $ra;
}

function khh_dt_nguon_dang_chon() {
	$x = get_option( 'khh_dt_nguon_sk', array() );
	return is_array( $x ) && ! empty( $x['bang'] ) ? $x : array();
}

/**
 * 🔴 BỎ TIỀN CỔNG QR — CHÍNH MÀN SAO KÊ NHÀ MÌNH ĐANG CẢNH BÁO CHUYỆN NÀY.
 *
 * Ảnh anh Thắng gửi 16/09/2026, ngay trên bảng: *"Tổng tiền vào ĐÃ BAO GỒM tiền cổng QR. Cục
 * tiền VNPAY / MoMo / Việt QR chuyển về là một dòng tiền vào của ngân hàng… Đừng cộng hai chỗ
 * lại — cộng là nhân đôi doanh thu."*
 *
 * Với việc đối soát nộp tiền thì còn nặng hơn nhân đôi: tiền cổng QR là KHÁCH trả, nó tự về tài
 * khoản, không ai phải mang đi nộp. Tính nó là "đã nộp" thì quán nào nhiều khách quét QR cũng
 * tự khắc đủ, và phép soi thất thoát tiền mặt thành vô nghĩa.
 */
function khh_dt_la_cong_qr( $noi_dung, $nguon ) {
	/* ⚠️ `khh_dt_khong_dau()` đã hạ chữ thường rồi — bọc thêm `strtoupper()` là mọi phép so với
	   mấy chuỗi chữ thường bên dưới trượt hết, mà trượt kiểu này thì im lặng: tiền cổng QR lẳng
	   lặng được tính là nhân viên đã nộp. Bài kiểm bắt được ngay lần chạy đầu (16/09/2026). */
	$s = khh_dt_khong_dau( (string) $noi_dung . ' ' . (string) $nguon );
	foreach ( array( 'vqr', 'viet qr', 'vietqr', 'momo', 'vnpay', 'zalopay', 'shopeepay' ) as $t ) {
		if ( false !== strpos( $s, $t ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Kéo từ một bảng bất kỳ đã dò ra.
 *
 * Trả [ ok, keo, bo_qr, bo_ghe, bo_ra, chua_gan ].
 */
function khh_dt_keo_nguon( $nguon, $tu_ngay = '' ) {
	global $wpdb;
	$bang = isset( $nguon['bang'] ) ? (string) $nguon['bang'] : '';
	$c    = isset( $nguon['cot'] ) ? (array) $nguon['cot'] : array();
	if ( '' === $bang || empty( $c['ngay'] ) || empty( $c['so_tien'] ) ) {
		return array( 'ok' => false, 'error' => 'Nguồn sao kê chưa khai đủ cột ngày và tiền.' );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array( 'ok' => false, 'error' => 'Không còn thấy bảng «' . $bang . '» trong cơ sở dữ liệu.' );
	}

	$lay = array();
	foreach ( array( 'ngay', 'so_tien', 'tien_ra', 'noi_dung', 'ma_gd', 'tai_khoan', 'nhan', 'ma_may',
		'nguon', 'huong', 'trang_thai', 'huy' ) as $v ) {
		if ( ! empty( $c[ $v ] ) ) {
			$lay[ $v ] = $c[ $v ];
		}
	}
	$sql  = 'SELECT `' . implode( '`, `', array_values( $lay ) ) . "` FROM `$bang` WHERE 1=1";
	$args = array();
	if ( $tu_ngay ) {
		$sql   .= ' AND `' . $lay['ngay'] . '` >= %s';
		$args[] = $tu_ngay . ' 00:00:00';
	}
	$sql .= ' ORDER BY `' . $lay['ngay'] . '` ASC LIMIT 20000';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	// phpcs:enable

	$o = function ( $r, $v ) use ( $lay ) {
		return isset( $lay[ $v ], $r[ $lay[ $v ] ] ) ? $r[ $lay[ $v ] ] : '';
	};
	$mang  = array();
	$bo_qr = 0;
	$bo_ghe = 0;
	$bo_ra = 0;
	foreach ( (array) $ds as $r ) {
		if ( isset( $lay['huy'] ) && (int) $o( $r, 'huy' ) ) {
			continue;
		}
		/* 🔴 CHỈ LẤY TIỀN ĐẾN VÀ GIAO DỊCH THÀNH CÔNG.
		   Sổ thật của nhà mình có cột `huong` (Đến / Đi) và `trang_thai` (Thành công / …). Cộng
		   một khoản tiền ĐI vào phần "đã nộp" là báo cáo đẹp lên mà không ai nộp thêm đồng nào;
		   cộng một giao dịch chưa thành công thì tệ hơn — tiền chưa hề về. */
		if ( isset( $lay['huong'] ) ) {
			$hg = khh_dt_khong_dau( $o( $r, 'huong' ) );
			if ( '' !== $hg && false === strpos( $hg, 'den' ) && false === strpos( $hg, 'vao' )
				&& false === strpos( $hg, 'in' ) && '+' !== $hg ) {
				$bo_ra++;
				continue;
			}
		}
		if ( isset( $lay['trang_thai'] ) ) {
			$tt = khh_dt_khong_dau( $o( $r, 'trang_thai' ) );
			if ( '' !== $tt && false === strpos( $tt, 'thanh cong' ) && false === strpos( $tt, 'success' )
				&& false === strpos( $tt, 'ok' ) && false === strpos( $tt, 'hoan tat' ) ) {
				$bo_ra++;
				continue;
			}
		}
		$tien = khh_dt_so( $o( $r, 'so_tien' ) );
		if ( $tien <= 0 ) {
			$bo_ra++;
			continue;
		}
		if ( isset( $lay['tien_ra'] ) && khh_dt_so( $o( $r, 'tien_ra' ) ) > 0 ) {
			$bo_ra++;
			continue;
		}
		$nd = (string) $o( $r, 'noi_dung' );
		if ( khh_dt_la_cong_qr( $nd, $o( $r, 'nguon' ) ) ) {
			$bo_qr++;
			continue;
		}
		if ( isset( $lay['ma_may'] ) && '' !== trim( (string) $o( $r, 'ma_may' ) ) ) {
			$bo_ghe++;
			continue;
		}
		$luc  = (string) $o( $r, 'ngay' );
		$ngay = khh_dt_ngay( substr( $luc, 0, 10 ) );
		if ( '' === $ngay ) {
			continue;
		}
		$gio = khh_dt_gio( $luc );
		$tk  = (string) $o( $r, 'tai_khoan' );
		$ma  = trim( (string) $o( $r, 'ma_gd' ) );
		if ( '' === $ma ) {
			$ma = 'tu-' . substr( md5( $ngay . '|' . $gio . '|' . $tien . '|' . $nd ), 0, 24 );
		}
		/* Bảng bên ấy đã có cột "Nhãn phân loại" thì TIN NHÃN ẤY TRƯỚC — người ta đã ngồi phân
		   loại tay rồi, mình đoán lại bằng mã là phủ nhận công của họ. Nhãn rỗng mới tự đoán. */
		$nhan = trim( (string) $o( $r, 'nhan' ) );
		$ch   = '' !== $nhan ? khh_dt_ten_co_so_gan( $nhan ) : '';
		if ( '' === $ch ) {
			$ch = khh_dt_doan_co_so( $nd, $tk );
		}
		$mang[] = array(
			'ma_gd'     => 'ng-' . $ma,
			'ngay'      => $ngay,
			'gio'       => $gio,
			'ngay_tinh' => khh_dt_ngay_quy( $ngay, $gio ),
			'so_tien'   => $tien,
			'noi_dung'  => $nd,
			'tai_khoan' => $tk,
			'cua_hang'  => $ch,
		);
	}
	$n = khh_dt_ghi_sao_ke( $mang );
	return array(
		'ok'       => true,
		'keo'      => $n,
		'bo_qr'    => $bo_qr,
		'bo_ghe'   => $bo_ghe,
		'bo_ra'    => $bo_ra,
		'chua_gan' => khh_dt_sk_chua_gan(),
	);
}

/**
 * Nhãn bên kia ("TÀU GÒ VẤP") sang tên cơ sở bên POS.
 *
 * Khớp lỏng: nhãn của họ viết gọn, tên POS viết dài ("TuTu Train - Lotte Gò Vấp ( Dịch vụ K&H )").
 * Không khớp được thì trả rỗng để đường đoán theo mã chạy tiếp — chứ không gán bừa.
 */
function khh_dt_ten_co_so_gan( $nhan ) {
	$n = khh_dt_khong_dau( $nhan );
	if ( '' === $n ) {
		return '';
	}
	foreach ( khh_dt_ds_cua_hang() as $t ) {
		$k = khh_dt_khong_dau( $t );
		if ( $k === $n || false !== strpos( $k, $n ) || false !== strpos( $n, $k ) ) {
			return $t;
		}
	}
	return '';
}

/**
 * MỌI BẢNG TRONG SITE, kèm số dòng — để người khai tự chỉ đích danh khi máy dò không ra.
 *
 * 🔴 PHẢI CÓ ĐƯỜNG CHỌN TAY. Máy dò bằng tên cột, mà tên cột là thứ người khác đặt: sổ của
 *    plugin Sao Kê Ngân Hàng có thể ghi `transactionDate` / `amount_in` / `remark` — không trùng
 *    cái tên nào mình liệt kê, và thế là nó không bao giờ hiện ra. Anh Thắng 16/09/2026 đã kẹt
 *    đúng chỗ ấy: danh sách dò được chỉ có sổ ghế và sổ chi, không có sao kê.
 *
 *    Máy đoán được thì tốt; đoán không được thì phải để người chỉ, chứ không được bắt người ta
 *    chờ mình đoán đúng.
 */
function khh_dt_moi_bang() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT TABLE_NAME ten, TABLE_ROWS so FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s ORDER BY TABLE_ROWS DESC',
			'%'
		),
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$ra[] = array(
			'bang' => (string) $r['ten'],
			/* `TABLE_ROWS` của InnoDB là số ƯỚC LƯỢNG — đủ để người ta nhận ra bảng nào có dữ
			   liệu, và rẻ hơn hẳn `COUNT(*)` trên vài trăm bảng. Nói rõ là "khoảng". */
			'uoc'  => (int) $r['so'],
		);
	}
	return $ra;
}

/**
 * ĐI TÌM GIAO DỊCH MOMO TRONG MỌI SỔ CỦA SITE.
 *
 * 🔴 ANH THẮNG MỞ DANH SÁCH BẢNG RA VÀ KHÔNG THẤY CÁI NÀO LÀ MOMO — đúng, vì chẳng có bảng nào
 *    tên "momo" cả. MoMo nếu có mặt thì nằm LẪN trong sổ cổng thanh toán, phân biệt bằng một giá
 *    trị trong cột `nguon` / `kenh` / `phuong_thuc`. Bắt người ta mở từng bảng trong hơn hai chục
 *    bảng rồi dò từng cột là bắt làm việc của máy.
 *
 *    Nên hàm này quét: mỗi bảng có vẻ là sổ tiền, mỗi cột chữ ngắn, xem có giá trị nào chứa
 *    "momo" không. Có thì chỉ thẳng ra bảng nào cột nào giá trị nào bao nhiêu dòng. Không có thì
 *    NÓI THẲNG LÀ KHÔNG CÓ — để anh khỏi đi tìm tiếp một thứ không tồn tại, và biết đường nạp
 *    file sao kê MoMo.
 */
function khh_dt_tim_momo_trong_so() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$bang_ds = (array) $wpdb->get_col( 'SHOW TABLES' );
	$bo = array( 'posts', 'postmeta', 'comments', 'commentmeta', 'options', 'users', 'usermeta',
		'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'links' );
	$thay   = array();
	$da_soi = 0;
	@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	foreach ( $bang_ds as $b ) {
		if ( $da_soi >= 60 ) {
			break;                                      // site nào có hơn sáu chục sổ thì đã sai chỗ khác
		}
		if ( in_array( substr( $b, strlen( $wpdb->prefix ) ), $bo, true ) ) {
			continue;
		}
		if ( $b === khh_dt_bang_sk() || ( function_exists( 'khh_dt_bang_momo_sk' ) && $b === khh_dt_bang_momo_sk() ) ) {
			continue;                                   // kho của chính mình, không kể là "sổ có sẵn"
		}
		/* Chỉ quét cột CHỮ NGẮN. Cột nội dung dài thì "momo" hay nằm trong câu chuyển khoản của
		   khách, không phải nhãn nguồn tiền — chỉ về nhiễu chứ không chỉ ra sổ. */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$cot_ds = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COLUMN_NAME ten FROM information_schema.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
				   AND DATA_TYPE IN ("varchar","char","enum")
				   AND CHARACTER_MAXIMUM_LENGTH <= 64',
				$b
			),
			ARRAY_A
		);
		if ( ! $cot_ds ) {
			continue;
		}
		$da_soi++;
		foreach ( array_slice( $cot_ds, 0, 12 ) as $c ) {
			$cot = (string) $c['ten'];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$gt = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `$cot` gt, COUNT(*) n FROM `$b` WHERE `$cot` LIKE %s GROUP BY `$cot` ORDER BY n DESC LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'%momo%'
				),
				ARRAY_A
			);
			foreach ( $gt as $r ) {
				$thay[] = array(
					'bang' => $b,
					'cot'  => $cot,
					'gt'   => (string) $r['gt'],
					'n'    => (int) $r['n'],
				);
			}
		}
	}
	usort(
		$thay,
		function ( $a, $b ) {
			return $b['n'] - $a['n'];
		}
	);
	return array(
		'thay'   => array_slice( $thay, 0, 20 ),
		'so_bang' => $da_soi,
	);
}

/**
 * Các giá trị có thật trong một cột, kèm số dòng — để người khai CHỌN, không phải gõ.
 *
 * 🔴 SỔ CỔNG GỘP CẢ BA CỔNG VÀO MỘT BẢNG. Site anh Thắng có `wpt9_saoke_cong` 36.478 dòng, trong
 *    đó VietQR, MoMo và VNPAY nằm chung, phân biệt bằng cột `NGUON`. Muốn đối soát MoMo thì phải
 *    lọc đúng `nguon = momo`; lấy cả bảng là đem tiền VietQR của cả chuỗi cộng vào phần MoMo.
 *
 *    Mà tên giá trị thì mỗi nơi viết một kiểu ("momo", "MOMO", "MoMo Wallet") — nên bày ra cho
 *    người ta chọn, đừng bắt gõ rồi gõ sai một chữ là lọc ra 0 dòng mà không hiểu vì sao.
 */
function khh_dt_gia_tri_cot( $bang, $cot, $gioi_han = 15 ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT `$cot` gt, COUNT(*) n FROM `$bang` GROUP BY `$cot` ORDER BY n DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			(int) $gioi_han
		),
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$ra[] = array(
			'gt' => (string) $r['gt'],
			'n'  => (int) $r['n'],
		);
	}
	return $ra;
}

/**
 * Cột của một bảng, kèm ba dòng đầu để người khai nhìn mà biết cột nào là cột gì.
 *
 * `$cot_them` là cột mà phép dò MoMo vừa chỉ ra. Phải bày giá trị của ĐÚNG cột ấy, vì nó thường
 * không nằm trong ba vai quen thuộc (nguồn / hướng / trạng thái) — thiếu nó thì màn khai vừa chỉ
 * cho người ta "cột `phuong_thuc` = MoMo" xong lại không cho chọn chính cột ấy để lọc.
 */
function khh_dt_soi_bang( $bang, $cot_them = '' ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array( 'cot' => array(), 'dong' => array() );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$cot = (array) $wpdb->get_col( "SHOW COLUMNS FROM `$bang`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$dong = (array) $wpdb->get_results( "SELECT * FROM `$bang` LIMIT 3", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( $dong as $i => $d ) {
		foreach ( $d as $k => $v ) {
			$dong[ $i ][ $k ] = mb_substr( (string) $v, 0, 60 );
		}
	}
	$doan = khh_dt_doan_cot( $cot );
	/* Bày sẵn các giá trị của mấy cột hay dùng để lọc — nguồn, loại, trạng thái. */
	$gia_tri = array();
	foreach ( array( 'nguon', 'huong', 'trang_thai' ) as $vai ) {
		if ( ! empty( $doan[ $vai ] ) ) {
			$gia_tri[ $doan[ $vai ] ] = khh_dt_gia_tri_cot( $bang, $doan[ $vai ] );
		}
	}
	if ( '' !== $cot_them && in_array( $cot_them, $cot, true ) && ! isset( $gia_tri[ $cot_them ] ) ) {
		$gia_tri[ $cot_them ] = khh_dt_gia_tri_cot( $bang, $cot_them, 30 );
	}
	return array(
		'cot'     => $cot,
		'dong'    => $dong,
		/* Đoán sẵn để người khai chỉ phải sửa chỗ sai, không phải khai từ đầu. */
		'doan'    => $doan,
		'gia_tri' => $gia_tri,
	);
}

/** Đoán vai của từng cột theo tên, trả [ vai => tên cột ]. */
function khh_dt_doan_cot( $cot_ds ) {
	$thuong = array_map( 'strtolower', (array) $cot_ds );
	$map    = array();
	foreach ( khh_dt_cot_nguon() as $vai => $ten_ds ) {
		$map[ $vai ] = '';
		foreach ( $ten_ds as $t ) {
			$i = array_search( $t, $thuong, true );
			if ( false !== $i ) {
				$map[ $vai ] = $cot_ds[ $i ];
				break;
			}
		}
	}
	return $map;
}

/* ================================================================== *
 * SỔ MOMO — nguồn thứ ba, để biết phần CK/QR có về đủ không
 * ================================================================== */

/**
 * Anh Thắng 16/09/2026: *"cái chỗ chuyển khoản nó là khoản MoMo, lấy dữ liệu từ MoMo qua mới biết
 * không khớp là bao nhiêu"* — và *"MoMo được lấy từ sao kê MoMo"*.
 *
 * Máy POS ghi khách trả bao nhiêu qua MoMo; sao kê MoMo ghi MoMo nhận bao nhiêu; ngân hàng ghi
 * MoMo chuyển về bao nhiêu. Ba con số ấy đáng lẽ bằng nhau (trừ phí cổng), và mỗi chỗ lệch là
 * một câu hỏi khác nhau:
 *
 *   POS  >  MoMo   -> có giao dịch ghi trên máy mà MoMo không nhận: nhập máy sai, hoặc huỷ.
 *   MoMo >  ngân hàng -> MoMo đã thu mà chưa chuyển về: cục cuối tháng, hoặc trừ phí.
 *
 * ⚠️ MOMO KHÔNG BẮN WEBHOOK (chính app nhà mình ghi thế), nên sổ MoMo là do người ta TẢI FILE lên
 *    hằng ngày. Ngày nào chưa tải thì sổ thiếu ngày ấy — và thiếu file thì phải nói là THIẾU FILE,
 *    tuyệt đối không được coi bằng 0 rồi kết luận "MoMo giữ tiền".
 */
function khh_dt_nguon_momo() {
	$x = get_option( 'khh_dt_nguon_momo', array() );
	return is_array( $x ) && ! empty( $x['bang'] ) ? $x : array();
}

/**
 * Sổ MoMo đang khai có phải SỔ ĐÃ GỘP THEO NGÀY không.
 *
 * 🔴 CÂU HỎI NÀY QUYẾT ĐỊNH ĐƯỢC PHÉP KẾT LUẬN TỚI ĐÂU. Sổ `wpt9_saoke_congfile` của anh Thắng
 *    là bản gộp của chính mấy file `Transaction_report_….csv`: mỗi dòng là một ngày của một quán,
 *    kèm cột đếm `so_dong`. Tổng ngày × cơ sở thì nó đúng và dùng được ngay. Nhưng đối soát TỪNG
 *    GIAO DỊCH thì không: 188 dòng gộp đem so với mấy nghìn giao dịch của máy POS sẽ ra một bảng
 *    lệch toàn phần, và người đọc tưởng mất tiền thật.
 *
 *    Muốn đối soát từng giao dịch thì nạp thẳng file `Transaction_report_….csv` ở thẻ Sao kê MoMo
 *    — cùng một file ấy, chỉ là chưa bị gộp.
 */
function khh_dt_momo_la_so_gop() {
	$n = khh_dt_nguon_momo();
	return ( $n && ! empty( $n['cot']['dem'] ) );
}

/**
 * Doanh thu MoMo theo ngày × cơ sở, đọc thẳng sổ MoMo.
 *
 * Trả [ 'ngay|cơ sở' => tổng ] và [ 'ngay' => true ] cho những ngày sổ CÓ dữ liệu — hai thứ khác
 * nhau: ngày không có dòng nào và ngày chưa tải file trông giống hệt nhau nếu chỉ nhìn tổng.
 */
function khh_dt_momo_theo_ngay( $tu = '', $den = '' ) {
	$gop = khh_dt_momo_ngay_tu_bang( $tu, $den );
	if ( ! function_exists( 'khh_dt_co_momo_sk' ) || ! khh_dt_co_momo_sk() ) {
		return $gop;
	}
	$file = khh_dt_momo_ngay_tu_file( $tu, $den );

	/* 🔴 DÙNG CẢ HAI SỔ, ĐÈ THEO TỪNG Ô (ngày × cơ sở) — KHÔNG CỘNG DỒN.
	 *
	 *    Anh Thắng nạp file `Transaction_report_….csv` lên plugin Sao Kê từ lâu, nên sổ gộp bên
	 *    ấy có cả trăm ngày lịch sử; còn file thô nạp thẳng vào đây thì chỉ có kỳ vừa tải. Bỏ sổ
	 *    gộp đi là mất sạch lịch sử; cộng hai sổ vào nhau là nhân đôi những ngày cả hai cùng có.
	 *
	 *    Nên: ô nào file thô có thì lấy file thô (nó tra được tới từng giao dịch, và theo được
	 *    máy khi dời cơ sở), ô nào không có thì giữ nguyên số của sổ gộp. Mỗi ô một nguồn, không
	 *    bao giờ hai. `nguon_o` nói rõ từng ô lấy ở đâu, để màn hình đừng hứa điều nó không làm
	 *    được — ngày lấy từ sổ gộp thì không đối soát tới giao dịch được. */
	$tong    = $gop['tong'];
	$nguon_o = array();
	foreach ( array_keys( $tong ) as $k ) {
		$nguon_o[ $k ] = 'gop';
	}
	foreach ( $file['tong'] as $k => $v ) {
		$tong[ $k ]    = $v;
		$nguon_o[ $k ] = 'file';
	}
	return array(
		'tong'    => $tong,
		'ngay_co' => $gop['ngay_co'] + $file['ngay_co'],
		'nguon_o' => $nguon_o,
	);
}

/** Cộng tiền MoMo theo (ngày × cơ sở) từ BẢNG NGOÀI đã khai. */
function khh_dt_momo_ngay_tu_bang( $tu = '', $den = '' ) {
	global $wpdb;
	$n = khh_dt_nguon_momo();
	if ( ! $n ) {
		return array( 'tong' => array(), 'ngay_co' => array() );
	}
	$bang = (string) $n['bang'];
	$c    = (array) $n['cot'];
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array( 'tong' => array(), 'ngay_co' => array() );
	}
	$cot_ten = ! empty( $c['nhan'] ) ? $c['nhan'] : ( ! empty( $c['noi_dung'] ) ? $c['noi_dung'] : '' );
	if ( empty( $c['ngay'] ) || empty( $c['so_tien'] ) || '' === $cot_ten ) {
		return array( 'tong' => array(), 'ngay_co' => array() );
	}
	$sql  = "SELECT `{$c['ngay']}` ngay, `{$c['so_tien']}` tien, `$cot_ten` ten FROM `$bang` WHERE 1=1";
	$args = array();
	if ( ! empty( $n['loc']['cot'] ) ) {
		$sql   .= ' AND `' . $n['loc']['cot'] . '` = %s';
		$args[] = (string) $n['loc']['gt'];
	}
	if ( $tu ) {
		$sql   .= " AND `{$c['ngay']}` >= %s";
		$args[] = $tu . ' 00:00:00';
	}
	if ( $den ) {
		$sql   .= " AND `{$c['ngay']}` <= %s";
		$args[] = $den . ' 23:59:59';
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	// phpcs:enable

	$tong    = array();
	$ngay_co = array();
	foreach ( (array) $ds as $r ) {
		$ngay = khh_dt_ngay( substr( (string) $r['ngay'], 0, 10 ) );
		if ( '' === $ngay ) {
			continue;
		}
		$ngay_co[ $ngay ] = true;
		/* Bảng ĐÃ HỌC từ file MoMo của FABi được tin trước — nó dựng từ những cặp giao dịch khớp
		   mã thật, nên đúng cả khi máy FABi vừa dời sang cơ sở khác. Xem `khh_dt_hoc_ghep_momo()`. */
		$ch = function_exists( 'khh_dt_ghep_momo_hoc' ) ? khh_dt_ghep_momo_hoc( (string) $r['ten'] ) : '';
		if ( '' === $ch ) {
			$ch = khh_dt_ten_co_so_gan( (string) $r['ten'] );
		}
		if ( '' === $ch ) {
			$ch = khh_dt_doan_co_so( (string) $r['ten'], '' );
		}
		if ( '' === $ch ) {
			continue;                       // chưa ghép được cửa hàng — đếm riêng ở màn MoMo
		}
		$k          = $ngay . '|' . $ch;
		$tong[ $k ] = ( isset( $tong[ $k ] ) ? $tong[ $k ] : 0 ) + khh_dt_so( $r['tien'] );
	}
	return array( 'tong' => $tong, 'ngay_co' => $ngay_co );
}

/** Cộng tiền MoMo theo (ngày × cơ sở) từ kho sao kê MoMo đã nạp. */
function khh_dt_momo_ngay_tu_file( $tu = '', $den = '' ) {
	global $wpdb;
	$bang = khh_dt_bang_momo_sk();
	$sql  = "SELECT ngay, so_tien tien, ten_ch ten, ma_ch FROM $bang WHERE 1=1";
	$args = array();
	if ( $tu ) {
		$sql   .= ' AND ngay >= %s';
		$args[] = $tu;
	}
	if ( $den ) {
		$sql   .= ' AND ngay <= %s';
		$args[] = $den;
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	// phpcs:enable
	$tong    = array();
	$ngay_co = array();
	foreach ( (array) $ds as $r ) {
		$ngay = khh_dt_ngay( substr( (string) $r['ngay'], 0, 10 ) );
		if ( '' === $ngay ) {
			continue;
		}
		$ngay_co[ $ngay ] = true;
		$ch = function_exists( 'khh_dt_ma_ch_toi_co_so' ) ? khh_dt_ma_ch_toi_co_so( (string) $r['ma_ch'] ) : '';
		if ( '' === $ch && function_exists( 'khh_dt_ghep_momo_hoc' ) ) {
			$ch = khh_dt_ghep_momo_hoc( (string) $r['ten'] );
		}
		if ( '' === $ch ) {
			$ch = khh_dt_ten_co_so_gan( (string) $r['ten'] );
		}
		if ( '' === $ch ) {
			continue;                       // chưa ghép được cửa hàng — đếm riêng ở màn MoMo
		}
		$k          = $ngay . '|' . $ch;
		$tong[ $k ] = ( isset( $tong[ $k ] ) ? $tong[ $k ] : 0 ) + khh_dt_so( $r['tien'] );
	}
	return array( 'tong' => $tong, 'ngay_co' => $ngay_co );
}

/** Một câu tả nguồn đang dùng, để màn đối soát nói ra mình đang đọc sổ nào. */
function khh_dt_nguon_mo_ta() {
	global $wpdb;
	$n = khh_dt_nguon_dang_chon();
	if ( ! $n ) {
		return array( 'bang' => '', 'nhan' => '' );
	}
	$bang = khh_dt_bang_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$b = $wpdb->get_row( "SELECT COUNT(*) n, MIN(ngay) tu, MAX(ngay) den FROM $bang", ARRAY_A );
	return array(
		'bang'    => (string) $n['bang'],
		'so_dong' => $b ? (int) $b['n'] : 0,
		'tu_ngay' => $b && $b['tu'] ? (string) $b['tu'] : '',
		'den_ngay' => $b && $b['den'] ? (string) $b['den'] : '',
	);
}

/* ================================================================== *
 * LẤY SỔ MÃ NỘP TIỀN CÓ SẴN, KHÔNG BẮT GÕ LẠI LẦN HAI
 * ================================================================== */

/**
 * Anh Thắng 16/09/2026 gửi ảnh màn *"Mã nộp tiền — Khu vui chơi"* của plugin Sao Kê: mỗi cơ sở
 * một ô mã, đã khai sẵn. Bắt anh gõ lại đúng bộ mã ấy sang đây là việc thừa — và tệ hơn, là tạo
 * ra HAI SỔ MÃ. Hai sổ thì có ngày lệch nhau, mà lệch ở đây nghĩa là tiền của quán này chạy vào
 * cột của quán kia, âm thầm, trong khi cả hai màn đều trông như đã đối soát xong.
 *
 * ⚠️ NHÀ MÌNH CÓ HAI SỔ MÃ RIÊNG — khu vui chơi và ghế massage — vì *"nhiều cơ sở trùng tên
 *    (cùng một trung tâm thương mại) mà là hai sổ tiền khác nhau"*. Nên ở đây chỉ nhận MỘT bảng
 *    mỗi lần, do người khai chỉ đích danh; gộp cả hai là trộn hai dòng tiền vào một.
 *
 * ⚠️ VÀ PHẢI CHO XEM TRƯỚC KHI GHI. Tên bên ấy viết gọn ("TÀU GÒ VẤP"), tên bên máy POS viết dài
 *    ("TuTu Train - Lotte Gò Vấp ( Dịch vụ K&H )") — máy ghép được phần lớn, nhưng ghép sai một
 *    dòng là sai cả một quán. Nên máy chỉ ĐỀ NGHỊ, người khai gật rồi mới ghi.
 */
function khh_dt_cot_ma_nguon() {
	return array(
		'ten' => array( 'ten_co_so', 'ten_coso', 'co_so', 'coso', 'cua_hang', 'ten', 'name', 'title' ),
		'ma'  => array( 'ma_nop_tien', 'ma_nop', 'ma_ct', 'ma_co_so', 'ma_coso', 'ma', 'code' ),
	);
}

/** Những bảng trông như sổ "cơ sở → mã nộp tiền". */
function khh_dt_ma_nguon_ds() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$bang_ds = (array) $wpdb->get_col( 'SHOW TABLES' );   // mọi bảng — xem `khh_dt_nguon_ds()`
	$bo      = array( 'posts', 'postmeta', 'comments', 'commentmeta', 'options', 'users', 'usermeta',
		'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'links' );
	$ra = array();
	foreach ( $bang_ds as $b ) {
		if ( in_array( substr( $b, strlen( $wpdb->prefix ) ), $bo, true ) ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$cot_ds = (array) $wpdb->get_col( "SHOW COLUMNS FROM `$b`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$thuong = array_map( 'strtolower', $cot_ds );
		$map    = array();
		foreach ( khh_dt_cot_ma_nguon() as $vai => $ten_ds ) {
			$map[ $vai ] = '';
			foreach ( $ten_ds as $t ) {
				$i = array_search( $t, $thuong, true );
				if ( false !== $i ) {
					$map[ $vai ] = $cot_ds[ $i ];
					break;
				}
			}
		}
		if ( '' === $map['ten'] || '' === $map['ma'] ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$b` WHERE `{$map['ma']}` <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $n ) {
			continue;                                   // bảng có cột nhưng chưa khai mã nào
		}
		$ra[] = array(
			'bang'    => $b,
			'so_ma'   => $n,
			'cot'     => $map,
		);
	}
	usort(
		$ra,
		function ( $a, $b ) {
			return $b['so_ma'] - $a['so_ma'];
		}
	);
	return $ra;
}

/** Đọc một sổ mã ra [ [ 'ten', 'ma' ], … ]. */
function khh_dt_ma_nguon_doc( $bang, $cot ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		"SELECT `{$cot['ten']}` ten, `{$cot['ma']}` ma FROM `$bang` WHERE `{$cot['ma']}` <> '' ORDER BY `{$cot['ten']}`", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$ten = trim( (string) $r['ten'] );
		$ma  = trim( (string) $r['ma'] );
		if ( '' !== $ten && '' !== $ma ) {
			$ra[] = array( 'ten' => $ten, 'ma' => $ma );
		}
	}
	return $ra;
}

/**
 * Ghép tên viết gọn bên kia với tên dài bên máy POS.
 *
 * "TÀU GÒ VẤP"  <->  "TuTu Train - Lotte Gò Vấp ( Dịch vụ K&H )"
 *
 * Đếm chữ chung, bỏ những chữ có ở khắp nơi ("dich", "vu", "k&h", "giai", "tri") vì chúng khớp
 * với mọi quán nên chỉ làm nhiễu. Trả về tên POS khớp nhất kèm ĐIỂM, để màn biết chỗ nào chắc
 * chỗ nào cần người nhìn lại.
 */
function khh_dt_ghep_ten_gan( $ten_ngan ) {
	$bo  = array( 'dich', 'vu', 'va', 'giai', 'tri', 'kh', 'k&h', 'cong', 'ty', 'tnhh', 'mall', '-', 'the' );
	$cat = function ( $s ) use ( $bo ) {
		$s  = khh_dt_khong_dau( $s );
		$s  = preg_replace( '/[^a-z0-9]+/', ' ', $s );
		$ra = array();
		foreach ( explode( ' ', $s ) as $t ) {
			$t = trim( $t );
			if ( '' !== $t && ! in_array( $t, $bo, true ) ) {
				$ra[] = $t;
			}
		}
		return $ra;
	};
	$a = $cat( $ten_ngan );
	if ( ! $a ) {
		return array( 'ten' => '', 'diem' => 0 );
	}
	$tot = array( 'ten' => '', 'diem' => 0 );
	foreach ( khh_dt_ds_cua_hang() as $dai ) {
		$b   = $cat( $dai );
		$chung = count( array_intersect( $a, $b ) );
		$diem  = $chung / count( $a );
		if ( $diem > $tot['diem'] ) {
			$tot = array( 'ten' => $dai, 'diem' => $diem );
		}
	}
	return $tot;
}

/** Dựng đề nghị ghép cho cả một sổ mã: [ ten, ma, goi_y, diem ]. */
function khh_dt_ma_nguon_de_nghi( $bang, $cot ) {
	$ra = array();
	foreach ( khh_dt_ma_nguon_doc( $bang, $cot ) as $r ) {
		$g    = khh_dt_ghep_ten_gan( $r['ten'] );
		$ra[] = array(
			'ten'   => $r['ten'],
			'ma'    => $r['ma'],
			/* Dưới 0,6 thì coi như không đoán ra — thà để trống còn hơn gợi ý sai rồi người ta
			   bấm lưu cho nhanh. */
			'goi_y' => $g['diem'] >= 0.6 ? $g['ten'] : '',
			'diem'  => round( $g['diem'], 2 ),
		);
	}
	return $ra;
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
		'/sao-ke-keo',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_sk_keo',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/moi-bang',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_moi_bang',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/soi-bang',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_soi_bang',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/ma-nguon',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_ma_nguon',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/tim-momo',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_tim_momo',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/nguon-momo',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_nguon_momo',
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
	foreach ( (array) $chua as $i => $c ) {
		$chua[ $i ]['ma_doc_duoc'] = khh_dt_ma_trong_nd( $c['noi_dung'] );
	}
	/* Gom theo MÃ đọc được: mười khoản của cùng một quán là MỘT dòng phải khai, không phải mười.
	   Khai xong một mã là cả chục khoản về sổ cùng lúc. */
	$ma_la = array();
	foreach ( (array) $chua as $c ) {
		$m = (string) $c['ma_doc_duoc'];
		if ( '' === $m ) {
			continue;
		}
		if ( ! isset( $ma_la[ $m ] ) ) {
			$ma_la[ $m ] = array(
				'ma'      => $m,
				'so_lan'  => 0,
				'so_tien' => 0,
				'vi_du'   => (string) $c['noi_dung'],
			);
		}
		$ma_la[ $m ]['so_lan']++;
		$ma_la[ $m ]['so_tien'] += (float) $c['so_tien'];
	}
	usort(
		$ma_la,
		function ( $a, $b ) {
			return $b['so_tien'] > $a['so_tien'] ? 1 : -1;
		}
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
		'ma_la'    => array_values( $ma_la ),
		'ghep'     => khh_dt_ghep_bank_ds(),
		'theo_co_so' => khh_dt_ghep_theo_co_so(),
		'gio_cat'  => khh_dt_gio_cat(),
		'cua_hang' => khh_dt_ds_cua_hang(),
		'co_cong_ghe' => khh_dt_co_cong_ghe(),
		'tu_keo'   => (bool) wp_next_scheduled( 'khh_dt_cron_keo' ),
		'nguon_ds' => khh_dt_nguon_ds(),
		'nguon'    => khh_dt_nguon_dang_chon(),
	);
}

function khh_dt_rest_sk_keo( $req ) {
	$tu = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'tu' ) );

	/* Người khai vừa chọn một bảng nguồn thì nhớ lấy, và từ nay kéo từ đó. */
	$bang = sanitize_text_field( (string) $req->get_param( 'bang' ) );
	if ( '' !== $bang ) {
		/* Người khai tự chỉ cột thì nghe theo — máy dò tên cột chỉ là đường tắt, không phải luật. */
		$cot_tay = $req->get_param( 'cot' );
		if ( is_string( $cot_tay ) ) {
			$cot_tay = json_decode( $cot_tay, true );
		}
		if ( is_array( $cot_tay ) && ! empty( $cot_tay['ngay'] ) && ! empty( $cot_tay['so_tien'] ) ) {
			$soi   = khh_dt_soi_bang( $bang );
			$sach  = array();
			foreach ( khh_dt_cot_nguon() as $vai => $bo_qua ) {
				$c = isset( $cot_tay[ $vai ] ) ? sanitize_text_field( (string) $cot_tay[ $vai ] ) : '';
				/* Chỉ nhận tên cột CÓ THẬT trong bảng ấy — chuỗi gửi lên đi thẳng vào câu SQL. */
				$sach[ $vai ] = in_array( $c, $soi['cot'], true ) ? $c : '';
			}
			if ( '' === $sach['ngay'] || '' === $sach['so_tien'] ) {
				return new WP_Error( 'khh_dt_keo', 'Cột ngày và cột tiền phải có thật trong bảng.', array( 'status' => 400 ) );
			}
			update_option( 'khh_dt_nguon_sk', array( 'bang' => $bang, 'cot' => $sach ), false );
		} else {
			$chon = array();
			foreach ( khh_dt_nguon_ds() as $n ) {
				if ( $n['bang'] === $bang ) {
					$chon = $n;
					break;
				}
			}
			if ( ! $chon ) {
				return new WP_Error( 'khh_dt_keo', 'Không thấy bảng «' . $bang . '».', array( 'status' => 400 ) );
			}
			update_option( 'khh_dt_nguon_sk', array( 'bang' => $chon['bang'], 'cot' => $chon['cot'] ), false );
		}
	}

	$nguon = khh_dt_nguon_dang_chon();
	$kq    = $nguon ? khh_dt_keo_nguon( $nguon, $tu ) : khh_dt_keo_ghe( $tu );
	if ( empty( $kq['ok'] ) ) {
		return new WP_Error( 'khh_dt_keo', $kq['error'], array( 'status' => 400 ) );
	}
	if ( $req->get_param( 'tu_dong' ) ) {
		khh_dt_bat_keo();
	}
	$ra = khh_dt_rest_sk_xem();
	$ra['vua_keo'] = $kq;
	return $ra;
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

/**
 * Bày sổ mã có sẵn để người khai chọn, và nếu đã chọn một bảng thì kèm đề nghị ghép.
 *
 * ⚠️ CHỈ ĐỀ NGHỊ, KHÔNG GHI. Việc ghi vẫn đi qua đường khai bình thường (`sao-ke-ghep`), tức là
 *    người khai đã nhìn thấy từng dòng trước khi bấm lưu.
 */
function khh_dt_rest_ma_nguon( $req ) {
	$ds   = khh_dt_ma_nguon_ds();
	$bang = sanitize_text_field( (string) $req->get_param( 'bang' ) );
	$de   = array();
	$chon = array();
	if ( '' !== $bang ) {
		foreach ( $ds as $n ) {
			if ( $n['bang'] === $bang ) {
				$chon = $n;
				break;
			}
		}
		if ( $chon ) {
			$de = khh_dt_ma_nguon_de_nghi( $chon['bang'], $chon['cot'] );
		}
	}
	return array(
		'nguon_ds' => $ds,
		'bang'     => $chon ? $chon['bang'] : '',
		'de_nghi'  => $de,
		'cua_hang' => khh_dt_ds_cua_hang(),
	);
}

function khh_dt_rest_moi_bang() {
	return array( 'bang_ds' => khh_dt_moi_bang() );
}

function khh_dt_rest_tim_momo() {
	$ra = khh_dt_tim_momo_trong_so();
	$ra['co_file'] = function_exists( 'khh_dt_co_momo_sk' ) ? (int) khh_dt_co_momo_sk() : 0;
	return $ra;
}

function khh_dt_rest_soi_bang( $req ) {
	$bang = sanitize_text_field( (string) $req->get_param( 'bang' ) );
	if ( '' === $bang ) {
		return new WP_Error( 'khh_dt_soi', 'Chưa chọn bảng.', array( 'status' => 400 ) );
	}
	$them       = sanitize_text_field( (string) $req->get_param( 'cot_them' ) );
	$ra         = khh_dt_soi_bang( $bang, $them );
	$ra['bang'] = $bang;
	return $ra;
}

function khh_dt_rest_nguon_momo( $req ) {
	$bang = sanitize_text_field( (string) $req->get_param( 'bang' ) );
	if ( '' === $bang ) {
		delete_option( 'khh_dt_nguon_momo' );
		return array( 'ok' => true, 'bo' => true );
	}
	$cot = $req->get_param( 'cot' );
	if ( is_string( $cot ) ) {
		$cot = json_decode( $cot, true );
	}
	$soi  = khh_dt_soi_bang( $bang );
	$sach = array();
	foreach ( khh_dt_cot_nguon() as $vai => $bo_qua ) {
		$c = ( is_array( $cot ) && isset( $cot[ $vai ] ) ) ? sanitize_text_field( (string) $cot[ $vai ] ) : '';
		/* Chỉ nhận tên cột CÓ THẬT — chuỗi gửi lên đi thẳng vào câu SQL. */
		$sach[ $vai ] = in_array( $c, $soi['cot'], true ) ? $c : '';
	}
	if ( '' === $sach['ngay'] || '' === $sach['so_tien'] ) {
		return new WP_Error( 'khh_dt_momo', 'Cột ngày và cột tiền phải có thật trong bảng.', array( 'status' => 400 ) );
	}
	if ( '' === $sach['nhan'] && '' === $sach['noi_dung'] ) {
		return new WP_Error( 'khh_dt_momo', 'Cần một cột mang tên cửa hàng để biết tiền của cơ sở nào.', array( 'status' => 400 ) );
	}
	/* Bộ lọc "chỉ lấy dòng có cột X bằng Y" — để tách MoMo ra khỏi sổ cổng gộp chung. */
	$loc_cot = sanitize_text_field( (string) $req->get_param( 'loc_cot' ) );
	$loc_gt  = sanitize_text_field( (string) $req->get_param( 'loc_gt' ) );
	$loc     = ( '' !== $loc_cot && in_array( $loc_cot, $soi['cot'], true ) && '' !== $loc_gt )
		? array( 'cot' => $loc_cot, 'gt' => $loc_gt )
		: array();
	update_option( 'khh_dt_nguon_momo', array( 'bang' => $bang, 'cot' => $sach, 'loc' => $loc ), false );
	return array( 'ok' => true, 'bang' => $bang, 'cot' => $sach, 'loc' => $loc );
}

function khh_dt_rest_sk_xoa() {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "TRUNCATE TABLE $bang" );
	return array( 'ok' => true );
}
