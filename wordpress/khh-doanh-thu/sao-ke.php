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
		$khoa = khh_dt_khong_dau( sanitize_text_field( (string) $khoa ) );
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
		$ra[ $d['cua_hang'] ][] = $d['khoa'];
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
	if ( ! khh_dt_co_cong_ghe() ) {
		return;
	}
	/* Chỉ kéo 45 ngày gần đây: sổ bên ấy có thể hàng trăm nghìn dòng, mà đối soát chỉ nhìn kỳ
	   gần. Cần kỳ cũ thì bấm nút kéo tay, nó không giới hạn ngày. */
	khh_dt_keo_ghe( gmdate( 'Y-m-d', time() - 45 * 86400 ) );
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
	);
}

function khh_dt_rest_sk_keo( $req ) {
	$tu = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'tu' ) );
	$kq = khh_dt_keo_ghe( $tu );
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

function khh_dt_rest_sk_xoa() {
	global $wpdb;
	$bang = khh_dt_bang_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "TRUNCATE TABLE $bang" );
	return array( 'ok' => true );
}
