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

/**
 * Khoá đang có của một quán trong bảng theo cửa hàng — tra LỎNG: khác dấu cách / hoa thường vẫn là một quán.
 * 🔴 Trước 1.64.3 đường ghi đi qua `sanitize_text_field` (gộp hai dấu cách thành một) còn đường đọc lấy tên
 *    nguyên văn: quán tên có hai dấu cách như Estella lưu xong đọc lại không thấy — anh Thắng 24/09/2026:
 *    *"lúc thì tự lưu, lúc thì không lưu"*. Quán tên không có dấu cách đôi thì… lưu được. Hai đường nay cùng
 *    tra về tên nguyên văn (`khh_dt_bc_ten_cua`), và bảng cũ lưu dưới khoá lệch vẫn đọc được nhờ hàm này.
 */
function khh_dt_ve_khoa_cua( $so, $cua_hang ) {
	$cs = trim( (string) $cua_hang );
	if ( '' === $cs || '*' === $cs || ! is_array( $so ) ) {
		return '';
	}
	if ( isset( $so[ $cs ] ) ) {
		return $cs;
	}
	$long = function ( $t ) {
		$t = preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $t );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $t ), 'UTF-8' ) : strtolower( trim( $t ) );
	};
	$k = $long( $cs );
	foreach ( array_keys( $so ) as $khoa ) {
		if ( '*' !== $khoa && $long( $khoa ) === $k ) {
			return (string) $khoa;
		}
	}
	return '';
}

/**
 * Tra một TÊN VÉ trong bảng khai — khớp đúng, không thì khớp LỎNG (gộp dấu cách, bỏ hoa thường).
 * 🔴 Bảng khai lưu tên qua `sanitize_text_field` (gộp hai dấu cách thành một) còn tên trong file FABi giữ
 *    nguyên văn. Một combo trong FABi có hai dấu cách hay dấu cách thừa là "khai 2 mà vẫn báo chưa khai,
 *    tạm tính 1" — anh Thắng 24/09/2026: *"Hiện đủ vé. Nhập 2 mà vẫn cứ báo sai"*.
 * @return array|null [ 'khoa' => khoá đang có, 'gia' => giá trị ] hay null khi không có.
 */
function khh_dt_ve_tra( $bang, $ten ) {
	$ten = trim( (string) $ten );
	if ( '' === $ten || ! is_array( $bang ) ) {
		return null;
	}
	if ( array_key_exists( $ten, $bang ) ) {
		return array( 'khoa' => $ten, 'gia' => $bang[ $ten ] );
	}
	$long = function ( $t ) {
		$t = preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $t );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $t ), 'UTF-8' ) : strtolower( trim( $t ) );
	};
	$k = $long( $ten );
	foreach ( $bang as $khoa => $gia ) {
		if ( $long( $khoa ) === $k ) {
			return array( 'khoa' => (string) $khoa, 'gia' => $gia );
		}
	}
	return null;
}

/**
 * GỘP MỘT LẦN các khai theo quán của bản 1.60–1.64.3 về bảng chung.
 *
 * Bản ấy nút Lưu ghi vào đúng quán đang chọn, nên anh Thắng khai combo = 2 ở Gò Vấp mà Bình Tân vẫn tạm tính 1
 * (24/09/2026: *"vé đã có sẵn lấy theo và anh đã set vé đó là tính 2 người mà"*, *"khai linh tinh rồi quán có
 * quán không"*). Ý anh là khai cho VÉ, dùng mọi quán; quán nào vé ấy khác thì *"cơ sở đó chủ động tự set"*.
 * Nên: vé nào chưa có ở bảng chung thì lấy từ quán (quán khai trước lấy trước), rồi xoá phần theo quán — từ
 * đây phần riêng chỉ còn những gì cửa hàng cố ý bấm "Lưu riêng". Chạy một lần lúc nâng cấp, cho cả hai sổ.
 *
 * @return int Số vé đã dồn về bảng chung.
 */
function khh_dt_ve_gop_mot_lan() {
	if ( get_option( 'khh_dt_ve_gop_1644', false ) ) {
		return 0;
	}
	$n = 0;
	foreach ( array( 'khh_dt_ve_khach', 'khh_dt_ve_phu' ) as $khoa ) {
		$so    = khh_dt_ve_so_cua( $khoa );
		$chung = isset( $so['*'] ) ? $so['*'] : array();
		foreach ( $so as $cs => $bang ) {
			if ( '*' === $cs ) {
				continue;
			}
			foreach ( (array) $bang as $ten => $gia ) {
				if ( null === khh_dt_ve_tra( $chung, $ten ) ) {
					$chung[ $ten ] = $gia;
					$n++;
				}
			}
		}
		update_option( $khoa, $chung ? array( '*' => $chung ) : array(), false );
	}
	update_option( 'khh_dt_ve_gop_1644', gmdate( 'Y-m-d H:i:s' ), false );
	return $n;
}

/**
 * Bảng áp cho MỘT quán: bảng chung '*' làm nền, phần quán tự set riêng đè lên (tra lỏng theo tên vé).
 */
function khh_dt_ve_bang_cua( $khoa, $cua_hang = '' ) {
	$so = khh_dt_ve_so_cua( $khoa );
	$ra = isset( $so['*'] ) ? $so['*'] : array();
	$kh = khh_dt_ve_khoa_cua( $so, $cua_hang );
	if ( '' !== $kh ) {
		foreach ( $so[ $kh ] as $ten => $n ) {
			$co = khh_dt_ve_tra( $ra, $ten );
			$ra[ null !== $co ? $co['khoa'] : $ten ] = $n;
		}
	}
	return $ra;
}

function khh_dt_ve_rieng_cua( $khoa, $cua_hang ) {
	$so = khh_dt_ve_so_cua( $khoa );
	$kh = khh_dt_ve_khoa_cua( $so, $cua_hang );
	return '' !== $kh ? $so[ $kh ] : array();
}

function khh_dt_ve_dat_cua( $khoa, $bang, $cua_hang = '' ) {
	$cs = trim( (string) $cua_hang );
	$cs = '' === $cs ? '*' : $cs;
	$so = khh_dt_ve_so_cua( $khoa );
	/* Khoá cũ khác dấu cách của cùng quán -> dồn về khoá nguyên văn, khỏi hai bản song song. */
	$kh = khh_dt_ve_khoa_cua( $so, $cs );
	$cu = '' !== $kh ? $so[ $kh ] : ( isset( $so[ $cs ] ) ? $so[ $cs ] : array() );
	if ( '' !== $kh && $kh !== $cs ) {
		unset( $so[ $kh ] );
	}
	foreach ( (array) $bang as $ten => $gia ) {
		$ten = trim( (string) $ten );
		if ( '' === $ten ) {
			continue;
		}
		/* Tên gửi lên khác dấu cách với tên đang có -> ghi vào đúng khoá đang có, khỏi hai dòng một vé. */
		$co = khh_dt_ve_tra( $cu, $ten );
		if ( null !== $co ) {
			$ten = $co['khoa'];
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
	/* "X2", "x 3" ở cuối tên = bán theo lố: combo TRẺ EM + NGƯỜI LỚN X2 là 4 người, VÉ TRẺ EM X2 là 2.
	   Anh Thắng 24/09/2026: *"sai, combo này là 4"*. */
	$boi = 1;
	if ( preg_match( '/(?<![a-z0-9])x\s?(\d{1,2})(?![a-z0-9])/', $k, $m ) && (int) $m[1] > 1 ) {
		$boi = (int) $m[1];
	}
	$n = 0;
	foreach ( array( 'tre em', 'nguoi lon', 'em be', 'phu huynh', 'be ' ) as $tu ) {
		$n += preg_match_all( '/(?<![a-z])' . preg_quote( $tu, '/' ) . '(?![a-z])/', $k );
	}
	if ( $n > 0 ) {
		return $n * $boi;
	}
	return ( false !== strpos( (string) $ten, '+' ) ? 2 : 1 ) * $boi;
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
	$chi  = array();   // từng vé: tên, số vé, khách/vé đang áp, có phải tạm tính — để màn bày "cách tính"
	foreach ( (array) $mon as $m ) {
		$ten = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
		$sl  = isset( $m['q'] ) ? (float) $m['q'] : 0;
		if ( '' === $ten ) {
			continue;
		}
		$co = khh_dt_ve_tra( $bang, $ten );
		if ( null !== $co ) {
			$chac += $sl * (int) $co['gia'];
			$n++;
			if ( $sl > 0 ) {
				$chi[] = array( 'n' => $ten, 'q' => (int) round( $sl ), 'k' => (int) $co['gia'], 'tam' => false );
			}
			continue;
		}
		if ( $sl > 0 && khh_dt_ve_la_ve( $ten, isset( $m['g'] ) ? (string) $m['g'] : '' ) ) {
			$chua[ $ten ] = ( isset( $chua[ $ten ] ) ? $chua[ $ten ] : 0 ) + $sl;
			$tam         += $sl;
			$chi[]        = array( 'n' => $ten, 'q' => (int) round( $sl ), 'k' => 1, 'tam' => true );
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
		'chi_tiet'  => $chi,
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
	$chung = khh_dt_ve_khach_bang();
	$phu   = khh_dt_ve_phu_bang( $cua_hang );
	$phu_r = khh_dt_ve_phu_bang_rieng( $cua_hang );
	$phu_c = khh_dt_ve_phu_bang();
	$nhom_phu = function_exists( 'khh_dt_nhom_phu_ds' ) ? khh_dt_nhom_phu_ds( $cua_hang ) : false;
	$ra    = array();
	foreach ( $gom as $ten => $x ) {
		$k_khai = khh_dt_ve_tra( $khai, $ten );
		$k_chung = khh_dt_ve_tra( $chung, $ten );
		$k_phu  = khh_dt_ve_tra( $phu, $ten );
		$k_phu_c = khh_dt_ve_tra( $phu_c, $ten );
		$ra[] = array(
			'ten'      => $ten,
			'nhom'     => $x['g'],
			'so_luong' => $x['q'],
			'khach'    => null !== $k_khai ? $k_khai['gia'] : null,
			/* Số đang áp là do quán tự set riêng (đè số chung) — kèm số chung để màn bày "chung: N". */
			'rieng'    => null !== khh_dt_ve_tra( $rieng, $ten ),
			'khach_chung' => null !== $k_chung ? $k_chung['gia'] : null,
			/* Sale phụ đ/vé khai theo TÊN vé (null = chưa), và số của NHÓM đang áp nếu không khai tên. */
			'phu'      => null !== $k_phu ? $k_phu['gia'] : null,
			'phu_rieng' => null !== khh_dt_ve_tra( $phu_r, $ten ),
			'phu_chung' => null !== $k_phu_c ? $k_phu_c['gia'] : null,
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
	$ch = function_exists( 'khh_dt_bc_ten_cua' ) ? khh_dt_bc_ten_cua( $req->get_param( 'cua_hang' ) ) : (string) $req->get_param( 'cua_hang' );
	return khh_dt_ve_khach_goi_ve( $ch );
}

/**
 * Gói trả về cho một cửa hàng (đã tra tên). 🔴 Tách riêng để đường POST gọi thẳng bằng TÊN, không dựng
 * `new WP_REST_Request( array(...) )` — kiểu ấy là của bản giả trong bộ thử; WordPress thật nhận
 * ($method, $route) nên `strtoupper( mảng )` nổ 500 ngay khi bấm Lưu (anh Thắng 24/09/2026).
 */
function khh_dt_ve_khach_goi_ve( $ch ) {
	$ch = (string) $ch;
	return array(
		/* bang = chung + riêng của quán đè lên; bang_chung = chỉ bảng chung; bang_rieng = phần quán tự set. */
		'bang'      => khh_dt_ve_khach_bang( $ch ),
		'bang_chung' => khh_dt_ve_khach_bang(),
		'bang_rieng' => khh_dt_ve_khach_bang_rieng( $ch ),
		'phu'       => khh_dt_ve_phu_bang( $ch ),
		'phu_chung' => khh_dt_ve_phu_bang(),
		'phu_rieng' => khh_dt_ve_phu_bang_rieng( $ch ),
		'cua_hang'  => $ch,
		'mon'       => '' !== $ch ? khh_dt_ve_khach_mon_cua( $ch ) : array(),
		/* Quán nào còn vé chưa khai — để nhắc đổi ô chọn cửa hàng sang khai tiếp. */
		'con_thieu' => khh_dt_ve_khach_chua_khai(),
	);
}

function khh_dt_rest_ve_khach_dat( $req ) {
	$tho  = $req->get_param( 'bang' );
	/* Lệnh "bỏ set riêng" không kèm bảng — đừng bắt nó có. */
	if ( $req->get_param( 'xoa_rieng' ) && null === $tho ) {
		$tho = '{}';
	}
	$bang = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	if ( ! is_array( $bang ) ) {
		return new WP_Error( 'khh_dt_ve_khach', 'Không đọc được bảng khai gửi lên.', array( 'status' => 400 ) );
	}
	$ch = function_exists( 'khh_dt_bc_ten_cua' ) ? khh_dt_bc_ten_cua( $req->get_param( 'cua_hang' ) ) : sanitize_text_field( (string) $req->get_param( 'cua_hang' ) );
	if ( '' === $ch ) {
		return new WP_Error( 'khh_dt_ve_khach', 'Chưa chọn cửa hàng.', array( 'status' => 400 ) );
	}
	/* "Bỏ khai riêng": xoá phần quán tự set (cả khách lẫn phụ), quán thừa lại bảng chung. Trả về theo quán đang xem. */
	if ( $req->get_param( 'xoa_rieng' ) ) {
		$xem = function_exists( 'khh_dt_bc_ten_cua' ) ? khh_dt_bc_ten_cua( $req->get_param( 'xem_cua_hang' ) ) : '';
		if ( '*' === $ch ) {
			return new WP_Error( 'khh_dt_ve_khach', 'Chưa chọn cửa hàng để bỏ khai riêng.', array( 'status' => 400 ) );
		}
		foreach ( array( 'khh_dt_ve_khach', 'khh_dt_ve_phu' ) as $khoa ) {
			$so = khh_dt_ve_so_cua( $khoa );
			$kh = khh_dt_ve_khoa_cua( $so, $ch );
			if ( '' !== $kh ) {
				unset( $so[ $kh ] );
				update_option( $khoa, $so, false );
			}
		}
		return khh_dt_ve_khach_goi_ve( $ch );
	}
	/* '*' = ghi bảng CHUNG cho mọi quán (nút chính); tên quán = quán tự set riêng. Trả về theo quán đang xem. */
	$xem = function_exists( 'khh_dt_bc_ten_cua' ) ? khh_dt_bc_ten_cua( $req->get_param( 'xem_cua_hang' ) ) : '';
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
	return khh_dt_ve_khach_goi_ve( '' !== $xem && '*' !== $xem ? $xem : ( '*' === $ch ? '' : $ch ) );
}
