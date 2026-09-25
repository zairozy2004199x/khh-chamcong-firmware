<?php
/**
 * XUẤT MISA — CHỨNG TỪ BÁN HÀNG, TỪNG MẶT HÀNG, COMBO BÓC TÁCH.
 *
 * Anh Thắng 25/09/2026: *"Giờ bắt đầu bóc tách và xuất dữ liệu ra misa"* — kèm ba ảnh:
 *   · màn MISA của kế toán: một CHỨNG TỪ BÁN HÀNG cho ngày 24/09 của Tutu Train Aeon Tân An, 11 dòng,
 *     mỗi dòng một Mã hàng (MNKVCTT004…, đúng mã FABi), TK doanh thu 5110, TK công nợ 131; dòng HÀNG
 *     (bim bim, thạch, nước) có thêm TK giá vốn 6320 + TK kho 1567 và ĐVT Gói/Cái/Chai; dòng VÉ thì
 *     không; cột Đơn vị = mã đơn vị của quán (TTAMTA); chi nhánh lập chứng từ "Khu vui chơi";
 *   · báo cáo FABi cùng ngày: 8 dòng, 94 món, 2.510.000;
 *   · ba dòng COMBO khoanh đỏ.
 *
 * 🔴 CÁI KHÓ LÀ COMBO. FABi ghi "COMBO … + BIM BIM" 4 × 80.000 là MỘT dòng; kế toán ghi HAI dòng:
 *    vé 4 × 60.000 (không giá vốn) và "Bim bim nhỏ" 4 × 20.000 (có giá vốn, xuất kho). 20.000 ấy chính
 *    là SALE PHỤ mỗi vé đã khai ở Quản trị; số bim bim / thạch mỗi combo là CÔNG THỨC COMBO của sổ kho.
 *    Hai thứ ấy đã có, đã kiểm — nên bóc tách ở đây chỉ là ghép lại, không khai lần ba:
 *      · phần vé   = (đơn giá − phụ) × số vé;
 *      · phần hàng = phụ × số vé, chia cho các thành phần theo số cái mỗi combo (thạch 2 cái → 10.000
 *        một cái), dòng cuối gánh phần lẻ để tổng khớp đến từng đồng;
 *      · món có thể khai riêng "đơn giá trong combo" để đè cách chia đều.
 *    Combo chưa khai sale phụ thì KHÔNG tách (ghi một dòng vé nguyên giá) và cảnh báo — máy không
 *    được tự bịa ra 20.000.
 *
 * 🔴 MÃ HÀNG LÀ MÃ FABi. Kế toán nhập vào MISA theo mã, tên chỉ để đọc. Mã lấy theo thứ tự: khai ở
 *    bảng Mặt hàng MISA → cột Mã hàng của chính dòng FABi hôm ấy → mã từng thấy ở mọi ngày khác → mã
 *    trong danh mục kho. Không tìm được thì ô trống + cảnh báo, không đoán.
 *
 * Tệp ra: mỗi ngày × cơ sở một chứng từ (một Số chứng từ, nhiều dòng), cột theo đúng lưới kế toán
 * đang gõ tay. MISA khi nhập khẩu có bước ghép cột, nên tên cột ở đây là tên trên lưới.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KHH_DT_MISA_CF = 'khh_dt_misa_cf';
const KHH_DT_MISA_CS = 'khh_dt_misa_cs';
const KHH_DT_MISA_MH = 'khh_dt_misa_mh';
const KHH_DT_MISA_DA = 'khh_dt_misa_da_xuat';

/* ================================================================== *
 * Cấu hình
 * ================================================================== */

function khh_dt_misa_cf_mac_dinh() {
	return array(
		'tk_dt'     => '5110',
		'tk_no'     => '131',
		'tk_gv'     => '6320',
		'tk_kho'    => '1567',
		'chi_nhanh' => 'Khu vui chơi',
		'dvt_ve'    => 'Vé',
		'dvt_hang'  => 'Cái',
		'tien_to'   => 'BH',
		'ma_kh'     => '',
	);
}

/** Mã tài khoản / mã hàng: chỉ chữ số, chữ cái, dấu chấm và gạch — không dấu cách, không ký tự lạ. */
function khh_dt_misa_ma_sach( $s ) {
	return preg_replace( '/[^A-Za-z0-9.\-_\/]/u', '', (string) $s );
}

function khh_dt_misa_cf() {
	$c  = get_option( KHH_DT_MISA_CF, array() );
	$c  = is_array( $c ) ? $c : array();
	$ra = khh_dt_misa_cf_mac_dinh();
	foreach ( $ra as $k => $v ) {
		if ( isset( $c[ $k ] ) && '' !== trim( (string) $c[ $k ] ) ) {
			$ra[ $k ] = trim( (string) $c[ $k ] );
		}
	}
	foreach ( array( 'tk_dt', 'tk_no', 'tk_gv', 'tk_kho' ) as $k ) {
		$ra[ $k ] = khh_dt_misa_ma_sach( $ra[ $k ] );
	}
	$ra['tien_to'] = khh_dt_misa_ma_sach( $ra['tien_to'] );
	return $ra;
}

function khh_dt_misa_cf_dat( $moi ) {
	$cu = khh_dt_misa_cf();
	foreach ( khh_dt_misa_cf_mac_dinh() as $k => $v ) {
		if ( is_array( $moi ) && array_key_exists( $k, $moi ) ) {
			$cu[ $k ] = sanitize_text_field( (string) $moi[ $k ] );
		}
	}
	update_option( KHH_DT_MISA_CF, $cu, false );
	return khh_dt_misa_cf();
}

/** [ tên quán (nguyên văn FABi) => { ma_dv, ten, ma_kh } ] */
function khh_dt_misa_cs() {
	$b  = get_option( KHH_DT_MISA_CS, array() );
	$b  = is_array( $b ) ? $b : array();
	$ra = array();
	foreach ( $b as $cs => $x ) {
		if ( ! is_array( $x ) ) {
			continue;
		}
		$ra[ (string) $cs ] = array(
			'ma_dv' => khh_dt_misa_ma_sach( isset( $x['ma_dv'] ) ? $x['ma_dv'] : '' ),
			'ten'   => trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) ),
			'ma_kh' => khh_dt_misa_ma_sach( isset( $x['ma_kh'] ) ? $x['ma_kh'] : '' ),
		);
	}
	return $ra;
}

/** Cấu hình của một quán — tra lỏng tên (FABi hay có hai dấu cách). */
function khh_dt_misa_cs_cua( $cua_hang, $bang = null ) {
	$bang = null === $bang ? khh_dt_misa_cs() : $bang;
	$k    = function_exists( 'khh_dt_kho_khoa_long' ) ? khh_dt_kho_khoa_long( $bang, $cua_hang ) : ( array_key_exists( $cua_hang, $bang ) ? $cua_hang : null );
	return null !== $k ? $bang[ $k ] : array( 'ma_dv' => '', 'ten' => '', 'ma_kh' => '' );
}

function khh_dt_misa_cs_dat( $ds ) {
	$bang = khh_dt_misa_cs();
	foreach ( (array) $ds as $cs => $x ) {
		$cs = function_exists( 'khh_dt_bc_ten_cua' ) ? khh_dt_bc_ten_cua( (string) $cs ) : trim( (string) $cs );
		if ( '' === $cs || ! is_array( $x ) ) {
			continue;
		}
		$k = function_exists( 'khh_dt_kho_khoa_long' ) ? khh_dt_kho_khoa_long( $bang, $cs ) : null;
		if ( null !== $k && $k !== $cs ) {
			unset( $bang[ $k ] );
		}
		$dong = array(
			'ma_dv' => khh_dt_misa_ma_sach( isset( $x['ma_dv'] ) ? $x['ma_dv'] : '' ),
			'ten'   => sanitize_text_field( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) ),
			'ma_kh' => khh_dt_misa_ma_sach( isset( $x['ma_kh'] ) ? $x['ma_kh'] : '' ),
		);
		if ( '' === $dong['ma_dv'] && '' === $dong['ten'] && '' === $dong['ma_kh'] ) {
			unset( $bang[ $cs ] );
		} else {
			$bang[ $cs ] = $dong;
		}
	}
	update_option( KHH_DT_MISA_CS, $bang, false );
	return khh_dt_misa_cs();
}

/** [ tên món => { ma, ten, dvt, gia } ] — chung mọi quán (mã hàng FABi giống nhau cả chuỗi). */
function khh_dt_misa_mh() {
	$b  = get_option( KHH_DT_MISA_MH, array() );
	$b  = is_array( $b ) ? $b : array();
	$ra = array();
	foreach ( $b as $ten => $x ) {
		if ( ! is_array( $x ) ) {
			continue;
		}
		$ra[ (string) $ten ] = array(
			'ma'  => khh_dt_misa_ma_sach( isset( $x['ma'] ) ? $x['ma'] : '' ),
			'ten' => trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) ),
			'dvt' => trim( (string) ( isset( $x['dvt'] ) ? $x['dvt'] : '' ) ),
			'gia' => max( 0.0, (float) ( isset( $x['gia'] ) ? $x['gia'] : 0 ) ),
		);
	}
	return $ra;
}

function khh_dt_misa_mh_cua( $ten, $bang = null ) {
	$bang = null === $bang ? khh_dt_misa_mh() : $bang;
	$k    = function_exists( 'khh_dt_kho_khoa_long' ) ? khh_dt_kho_khoa_long( $bang, $ten ) : ( array_key_exists( $ten, $bang ) ? $ten : null );
	return null !== $k ? $bang[ $k ] : array( 'ma' => '', 'ten' => '', 'dvt' => '', 'gia' => 0.0 );
}

function khh_dt_misa_mh_dat( $ds ) {
	$bang = khh_dt_misa_mh();
	foreach ( (array) $ds as $ten => $x ) {
		$ten = trim( (string) $ten );
		if ( '' === $ten || ! is_array( $x ) ) {
			continue;
		}
		$k = function_exists( 'khh_dt_kho_khoa_long' ) ? khh_dt_kho_khoa_long( $bang, $ten ) : null;
		if ( null !== $k && $k !== $ten ) {
			unset( $bang[ $k ] );
		}
		$dong = array(
			'ma'  => khh_dt_misa_ma_sach( isset( $x['ma'] ) ? $x['ma'] : '' ),
			'ten' => sanitize_text_field( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) ),
			'dvt' => sanitize_text_field( (string) ( isset( $x['dvt'] ) ? $x['dvt'] : '' ) ),
			/* Giá gõ kiểu Việt "8.000" là tám nghìn — bỏ mọi dấu, chỉ giữ chữ số. */
			'gia' => max( 0.0, (float) preg_replace( '/[^\d]/', '', (string) ( isset( $x['gia'] ) ? $x['gia'] : 0 ) ) ),
		);
		if ( '' === $dong['ma'] && '' === $dong['ten'] && '' === $dong['dvt'] && $dong['gia'] <= 0 ) {
			unset( $bang[ $ten ] );
		} else {
			$bang[ $ten ] = $dong;
		}
	}
	update_option( KHH_DT_MISA_MH, $bang, false );
	return khh_dt_misa_mh();
}

/* ================================================================== *
 * Đã xuất
 * ================================================================== */

function khh_dt_misa_da_xuat() {
	$b = get_option( KHH_DT_MISA_DA, array() );
	return is_array( $b ) ? $b : array();
}

function khh_dt_misa_khoa( $ngay, $cua_hang ) {
	return $ngay . '|' . ( function_exists( 'khh_dt_kho_long' ) ? khh_dt_kho_long( $cua_hang ) : trim( (string) $cua_hang ) );
}

/** Đánh dấu (hay bỏ dấu) "đã xuất" cho các khoá 'ngày|quán'. Trả về số khoá đổi. */
function khh_dt_misa_danh_dau( $khoa_ds, $da = true ) {
	$b = khh_dt_misa_da_xuat();
	$n = 0;
	foreach ( (array) $khoa_ds as $k ) {
		$k = trim( (string) $k );
		if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}\|.+$~u', $k ) ) {
			continue;
		}
		if ( $da && ! isset( $b[ $k ] ) ) {
			$b[ $k ] = array( 'luc' => current_time( 'mysql' ), 'nguoi' => function_exists( 'khh_dt_ten_ghi_so' ) ? khh_dt_ten_ghi_so() : '' );
			$n++;
		} elseif ( ! $da && isset( $b[ $k ] ) ) {
			unset( $b[ $k ] );
			$n++;
		}
	}
	update_option( KHH_DT_MISA_DA, $b, false );
	return $n;
}

/* ================================================================== *
 * Mã hàng FABi từng thấy
 * ================================================================== */

/** [ khoá lỏng tên món => mã FABi ] gom từ mọi dòng POS đã nạp + danh mục kho. Dòng mới nhất thắng. */
function khh_dt_misa_ma_fabi() {
	global $wpdb;
	static $nho = null;
	if ( null !== $nho && empty( $GLOBALS['KHH_DT_MISA_KHONG_NHO'] ) ) {
		return $nho;
	}
	$long = function ( $t ) {
		return function_exists( 'khh_dt_kho_long' ) ? khh_dt_kho_long( $t ) : mb_strtolower( trim( (string) $t ) );
	};
	/* Danh mục hàng hoá FABi nạp ở Quản trị (danh-muc.php) — mã của cả món CHƯA BÁN; dòng bán thật (dưới) đè lên nếu khác. */
	$ra   = function_exists( 'khh_dt_dm_ma_bang' ) ? khh_dt_dm_ma_bang() : array();
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_col( "SELECT mon FROM $bang ORDER BY ngay ASC" );
	foreach ( $ds as $mon ) {
		foreach ( khh_dt_json( $mon, array() ) as $m ) {
			$ma = isset( $m['m'] ) ? trim( (string) $m['m'] ) : '';
			$n  = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
			if ( '' !== $ma && '' !== $n ) {
				$ra[ $long( $n ) ] = $ma;
			}
		}
	}
	if ( function_exists( 'khh_dt_kho_ma_cua' ) ) {
		$b = get_option( 'khh_dt_kho_ma', array() );
		foreach ( ( is_array( $b ) ? $b : array() ) as $cs => $d ) {
			foreach ( ( is_array( $d ) ? $d : array() ) as $t => $ma ) {
				if ( '' !== trim( (string) $ma ) && ! isset( $ra[ $long( $t ) ] ) ) {
					$ra[ $long( $t ) ] = trim( (string) $ma );
				}
			}
		}
	}
	$nho = $ra;
	return $ra;
}

/* ================================================================== *
 * Bóc tách một ngày × cơ sở
 * ================================================================== */

/** Món này là VÉ (không giá vốn) hay HÀNG (có giá vốn, xuất kho) — theo cột Loại món của FABi. */
function khh_dt_misa_la_ve( $m ) {
	$l = isset( $m['l'] ) ? trim( (string) $m['l'] ) : '';
	if ( '' !== $l ) {
		return 0 === strpos( khh_dt_khong_dau( $l ), 've' );
	}
	return function_exists( 'khh_dt_ve_la_ve' )
		&& khh_dt_ve_la_ve( isset( $m['n'] ) ? (string) $m['n'] : '', isset( $m['g'] ) ? (string) $m['g'] : '' );
}

function khh_dt_misa_ngay_vn( $ymd ) {
	return preg_match( '~^(\d{4})-(\d{2})-(\d{2})$~', (string) $ymd, $x ) ? $x[3] . '/' . $x[2] . '/' . $x[1] : (string) $ymd;
}

/** Số chứng từ: tiền tố + ngày + mã đơn vị (hay số thứ tự quán khi chưa khai mã). */
function khh_dt_misa_so_ct( $ngay, $cua_hang, $cf, $cs ) {
	$duoi = '' !== $cs['ma_dv'] ? $cs['ma_dv'] : '';
	if ( '' === $duoi ) {
		$ds = function_exists( 'khh_dt_ds_cua_hang' ) ? khh_dt_ds_cua_hang() : array();
		$i  = array_search( $cua_hang, $ds, true );
		$duoi = sprintf( '%02d', false === $i ? 0 : $i + 1 );
	}
	return $cf['tien_to'] . str_replace( '-', '', (string) $ngay ) . '-' . $duoi;
}

/**
 * Các dòng MISA của một ngày × cơ sở.
 *
 * @return array|null null khi ngày ấy chưa có số POS. Không thì { ngay, cua_hang, so_ct, dien_giai, dong:[…],
 *                    tong, doanh_thu, so_dong, canh:[…], chot, da_xuat }.
 */
function khh_dt_misa_ngay( $ngay, $cua_hang, $ctx = null ) {
	global $wpdb;
	$ctx = is_array( $ctx ) ? $ctx : khh_dt_misa_ctx();
	$cf  = $ctx['cf'];
	$cs  = khh_dt_misa_cs_cua( $cua_hang, $ctx['cs'] );
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT doanh_thu, mon FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $cua_hang ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $r ) {
		return null;
	}
	$canh  = array();
	$mon   = khh_dt_json( $r['mon'], array() );
	$combo = function_exists( 'khh_dt_kho_combo_bang' ) ? khh_dt_kho_combo_bang( $ngay ) : array();
	$phu_b = function_exists( 'khh_dt_ve_phu_bang' ) ? khh_dt_ve_phu_bang( $cua_hang ) : array();
	$nhom_phu = function_exists( 'khh_dt_nhom_phu_ds' ) ? khh_dt_nhom_phu_ds( $cua_hang ) : null;
	$ten_cs = '' !== $cs['ten'] ? $cs['ten'] : (string) $cua_hang;
	$so_ct  = khh_dt_misa_so_ct( $ngay, $cua_hang, $cf, $cs );
	$dg     = 'Bán hàng ngày ' . khh_dt_misa_ngay_vn( $ngay ) . ' - ' . $ten_cs;
	if ( '' === $cs['ma_dv'] ) {
		$canh[] = 'Cơ sở "' . $cua_hang . '" chưa khai Mã đơn vị MISA.';
	}
	$ma_kh = '' !== $cs['ma_kh'] ? $cs['ma_kh'] : $cf['ma_kh'];

	$dong = array();
	$tong = 0.0;
	$them = function ( $ten_fabi, $q, $don_gia, $thanh_tien, $la_ve, $tu_combo = '' ) use ( &$dong, &$tong, &$canh, $ctx, $cf, $ma_kh, $so_ct, $dg, $ngay, $cs ) {
		$mh  = khh_dt_misa_mh_cua( $ten_fabi, $ctx['mh'] );
		$ma  = '' !== $mh['ma'] ? $mh['ma'] : '';
		if ( '' === $ma ) {
			$k = function_exists( 'khh_dt_kho_long' ) ? khh_dt_kho_long( $ten_fabi ) : mb_strtolower( trim( $ten_fabi ) );
			$ma = isset( $ctx['ma_fabi'][ $k ] ) ? $ctx['ma_fabi'][ $k ] : '';
		}
		if ( '' === $ma ) {
			$canh[] = 'Chưa có Mã hàng cho "' . $ten_fabi . '"' . ( '' !== $tu_combo ? ' (thành phần của ' . $tu_combo . ')' : '' ) . ' — khai ở bảng Mặt hàng MISA.';
		}
		$dvt = '' !== $mh['dvt'] ? $mh['dvt'] : ( $la_ve ? $cf['dvt_ve'] : $cf['dvt_hang'] );
		$dong[] = array(
			'ngay'      => khh_dt_misa_ngay_vn( $ngay ),
			'so_ct'     => $so_ct,
			'ma_kh'     => $ma_kh,
			'dien_giai' => $dg,
			'ma'        => $ma,
			'ten'       => '' !== $mh['ten'] ? $mh['ten'] : $ten_fabi,
			'ten_fabi'  => $ten_fabi,
			'tk_dt'     => $cf['tk_dt'],
			'tk_no'     => $cf['tk_no'],
			'tk_gv'     => $la_ve ? '' : $cf['tk_gv'],
			'tk_kho'    => $la_ve ? '' : $cf['tk_kho'],
			'dvt'       => $dvt,
			'sl'        => round( (float) $q, 2 ),
			'don_gia'   => round( (float) $don_gia, 2 ),
			'tien'      => round( (float) $thanh_tien ),
			'ma_dv'     => $cs['ma_dv'],
			'chi_nhanh' => $cf['chi_nhanh'],
			'la_ve'     => $la_ve,
			'tu_combo'  => $tu_combo,
		);
		$tong += round( (float) $thanh_tien );
	};

	foreach ( $mon as $m ) {
		$ten = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
		$q   = isset( $m['q'] ) ? (float) $m['q'] : 0;
		$r_  = isset( $m['r'] ) ? (float) $m['r'] : 0;
		if ( '' === $ten ) {
			continue;
		}
		$la_ve   = khh_dt_misa_la_ve( $m );
		$don_gia = $q > 0 ? $r_ / $q : 0;
		$k_cb    = ( $combo && function_exists( 'khh_dt_kho_khoa_long' ) ) ? khh_dt_kho_khoa_long( $combo, $ten ) : null;
		if ( null === $k_cb ) {
			$them( $ten, $q, $don_gia, $r_, $la_ve );
			continue;
		}
		/* Combo: phụ mỗi vé — khai theo tên vé (riêng quán) trước, không thì theo nhóm món. */
		$tra = function_exists( 'khh_dt_ve_tra' ) ? khh_dt_ve_tra( $phu_b, $ten ) : null;
		$phu = null !== $tra ? (float) $tra['gia'] : ( function_exists( 'khh_dt_bc_phu_moi_ve' ) ? khh_dt_bc_phu_moi_ve( $m, $nhom_phu ) : 0.0 );
		$tp  = (array) $combo[ $k_cb ];
		if ( $phu <= 0 || ! $tp || $q <= 0 ) {
			$canh[] = 'Combo "' . $ten . '" ' . ( $phu <= 0 ? 'chưa khai sale phụ mỗi vé' : 'chưa có công thức thành phần' ) . ' — xuất một dòng vé nguyên giá, chưa bóc tách.';
			$them( $ten, $q, $don_gia, $r_, true );
			continue;
		}
		if ( $phu >= $don_gia ) {
			$canh[] = 'Combo "' . $ten . '": sale phụ ' . number_format( $phu, 0, ',', '.' ) . ' ≥ đơn giá ' . number_format( $don_gia, 0, ',', '.' ) . ' — xuất một dòng vé nguyên giá.';
			$them( $ten, $q, $don_gia, $r_, true );
			continue;
		}
		/* Phần hàng = phụ × số vé, chia cho các thành phần theo số cái. Món có khai "đơn giá trong combo"
		   thì lấy đúng giá ấy; phần còn lại chia đều theo số cái cho các món chưa khai; dòng cuối gánh lẻ. */
		$tong_hang = round( $phu * $q );
		$con       = $tong_hang;
		$chua      = array();
		$hang      = array();
		$tong_cai  = 0.0;
		foreach ( $tp as $mh_ten => $moi_cai ) {
			$moi_cai = (float) $moi_cai;
			if ( $moi_cai <= 0 ) {
				continue;
			}
			$cfm = khh_dt_misa_mh_cua( (string) $mh_ten, $ctx['mh'] );
			if ( $cfm['gia'] > 0 ) {
				$t = round( $cfm['gia'] * $moi_cai * $q );
				$hang[] = array( 'ten' => (string) $mh_ten, 'sl' => $moi_cai * $q, 'tien' => $t );
				$con   -= $t;
			} else {
				$chua[]    = array( 'ten' => (string) $mh_ten, 'sl' => $moi_cai * $q, 'cai' => $moi_cai );
				$tong_cai += $moi_cai;
			}
		}
		if ( $chua ) {
			$da = 0;
			foreach ( $chua as $i => $x ) {
				$t = ( $i === count( $chua ) - 1 ) ? $con - $da : round( $con * $x['cai'] / $tong_cai );
				$hang[] = array( 'ten' => $x['ten'], 'sl' => $x['sl'], 'tien' => $t );
				$da    += $t;
			}
			$con = 0;
		}
		/* Phần vé = tiền combo − phần hàng thật sự đã chia (khi mọi món khai giá riêng thì phần vé nhận cả phần lẻ). */
		$tien_hang = 0;
		foreach ( $hang as $x ) {
			$tien_hang += $x['tien'];
		}
		$tien_ve = $r_ - $tien_hang;
		if ( $tien_ve < 0 ) {
			$canh[] = 'Combo "' . $ten . '": phần hàng ' . number_format( $tien_hang, 0, ',', '.' ) . ' vượt tiền combo — xuất một dòng vé nguyên giá.';
			$them( $ten, $q, $don_gia, $r_, true );
			continue;
		}
		$them( $ten, $q, $tien_ve / $q, $tien_ve, true );
		foreach ( $hang as $x ) {
			$them( $x['ten'], $x['sl'], $x['sl'] > 0 ? $x['tien'] / $x['sl'] : 0, $x['tien'], false, $ten );
		}
	}

	$dt = (float) $r['doanh_thu'];
	if ( abs( $tong - $dt ) > 1 ) {
		$canh[] = 'Tổng dòng ' . number_format( $tong, 0, ',', '.' ) . ' ≠ doanh thu POS ' . number_format( $dt, 0, ',', '.' ) . ' (chiết khấu hoá đơn, hay món ngoài top ' . ( defined( 'KHH_DT_MON_MOI_NGAY' ) ? KHH_DT_MON_MOI_NGAY : 400 ) . ').';
	}
	$khoa = khh_dt_misa_khoa( $ngay, $cua_hang );
	return array(
		'ngay'      => $ngay,
		'cua_hang'  => $cua_hang,
		'khoa'      => $khoa,
		'so_ct'     => $so_ct,
		'dien_giai' => $dg,
		'dong'      => $dong,
		'so_dong'   => count( $dong ),
		'tong'      => $tong,
		'doanh_thu' => $dt,
		'canh'      => $canh,
		'chot'      => isset( $ctx['chot'][ $khoa ] ) ? (bool) $ctx['chot'][ $khoa ] : false,
		'da_xuat'   => isset( $ctx['da'][ $khoa ] ) ? $ctx['da'][ $khoa ] : null,
	);
}

/** Bối cảnh đọc một lần cho cả kỳ: cấu hình, mã FABi, đã xuất, ngày đã chốt. */
function khh_dt_misa_ctx( $tu = '', $den = '' ) {
	global $wpdb;
	$chot = array();
	if ( function_exists( 'khh_dt_bang_bc' ) ) {
		$bc = khh_dt_bang_bc();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$ds = (array) $wpdb->get_results( "SELECT ngay, cua_hang, chot FROM $bc", ARRAY_A );
		foreach ( $ds as $x ) {
			$chot[ khh_dt_misa_khoa( $x['ngay'], $x['cua_hang'] ) ] = (bool) $x['chot'];
		}
	}
	return array(
		'cf'      => khh_dt_misa_cf(),
		'cs'      => khh_dt_misa_cs(),
		'mh'      => khh_dt_misa_mh(),
		'ma_fabi' => khh_dt_misa_ma_fabi(),
		'da'      => khh_dt_misa_da_xuat(),
		'chot'    => $chot,
	);
}

/* ================================================================== *
 * Cả kỳ: cột + dòng
 * ================================================================== */

function khh_dt_misa_cot() {
	return array( 'Ngày hạch toán', 'Ngày chứng từ', 'Số chứng từ', 'Mã khách hàng', 'Diễn giải', 'Mã hàng', 'Tên hàng',
		'TK doanh thu', 'TK công nợ', 'TK giá vốn', 'TK kho', 'ĐVT', 'Số lượng', 'Đơn giá', 'Thành tiền', 'Đơn vị', 'Chi nhánh' );
}

function khh_dt_misa_dong_ra( $d ) {
	return array( $d['ngay'], $d['ngay'], $d['so_ct'], $d['ma_kh'], $d['dien_giai'], $d['ma'], $d['ten'],
		$d['tk_dt'], $d['tk_no'], $d['tk_gv'], $d['tk_kho'], $d['dvt'], $d['sl'], $d['don_gia'], $d['tien'], $d['ma_dv'], $d['chi_nhanh'] );
}

/**
 * @param string $tt 'chua' (mặc định) chỉ ngày chưa đánh dấu đã xuất · 'da' chỉ đã xuất · 'tatca'.
 */
function khh_dt_misa_xuat( $tu, $den, $cua_hang = '', $tt = 'chua' ) {
	global $wpdb;
	$bang = khh_dt_bang();
	$sql  = "SELECT ngay, cua_hang FROM $bang WHERE ngay >= %s AND ngay <= %s";
	$args = array( $tu, $den );
	$cua_ds = function_exists( 'khh_dt_co_so_ds' ) ? khh_dt_co_so_ds() : array();
	if ( '' !== $cua_hang && '*' !== $cua_hang ) {
		$sql   .= ' AND cua_hang = %s';
		$args[] = $cua_hang;
	} elseif ( $cua_ds ) {
		$sql   .= ' AND cua_hang IN (' . implode( ',', array_fill( 0, count( $cua_ds ), '%s' ) ) . ')';
		$args   = array_merge( $args, $cua_ds );
	}
	/* "Toàn hệ thống" là dòng FABi gộp cả chuỗi khi file không có cột cửa hàng — không phải quán, không có chứng từ. */
	$sql   .= ' AND cua_hang <> %s';
	$args[] = 'Toàn hệ thống';
	$sql .= ' ORDER BY ngay ASC, cua_hang ASC LIMIT 3000';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds  = (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	$ctx = khh_dt_misa_ctx( $tu, $den );
	$ct  = array();
	$rows = array();
	$canh = array();
	$chua_chot = 0;
	foreach ( $ds as $x ) {
		$khoa = khh_dt_misa_khoa( $x['ngay'], $x['cua_hang'] );
		$da   = isset( $ctx['da'][ $khoa ] );
		if ( 'chua' === $tt && $da ) {
			continue;
		}
		if ( 'da' === $tt && ! $da ) {
			continue;
		}
		$n = khh_dt_misa_ngay( $x['ngay'], $x['cua_hang'], $ctx );
		if ( ! $n ) {
			continue;
		}
		if ( ! $n['chot'] ) {
			$chua_chot++;
		}
		foreach ( $n['dong'] as $d ) {
			$rows[] = khh_dt_misa_dong_ra( $d );
		}
		foreach ( $n['canh'] as $c ) {
			$canh[ $c ] = isset( $canh[ $c ] ) ? $canh[ $c ] + 1 : 1;
		}
		$ct[] = array(
			'ngay'      => $n['ngay'],
			'cua_hang'  => $n['cua_hang'],
			'khoa'      => $n['khoa'],
			'so_ct'     => $n['so_ct'],
			'so_dong'   => $n['so_dong'],
			'tong'      => $n['tong'],
			'doanh_thu' => $n['doanh_thu'],
			'canh'      => $n['canh'],
			'chot'      => $n['chot'],
			'da_xuat'   => $n['da_xuat'],
		);
	}
	$nhom = khh_dt_misa_gom_canh( $canh, $chua_chot );
	return array(
		'cols'     => khh_dt_misa_cot(),
		'rows'     => $rows,
		'chung_tu' => $ct,
		'warn'     => $nhom['gon'],
		'warn_nhom' => $nhom['nhom'],
		'tong'     => array_sum( array_map( function ( $c ) { return $c['tong']; }, $ct ) ),
		'ten_tep'  => 'MISA_BanHang_' . str_replace( '-', '', $tu ) . '-' . str_replace( '-', '', $den ),
	);
}

/**
 * GOM CẢNH BÁO THEO LOẠI. Anh Thắng 25/09/2026 mở kỳ 1–25/09 cả chuỗi: hơn 40 dòng "Chưa có Mã hàng cho …" choán cả
 * màn, không đọc nổi. Mỗi loại một câu ngắn kể vài tên đầu + "và N nữa"; danh sách đủ nằm ở `nhom` cho màn mở ra khi cần.
 *
 * @param array $canh [ câu => số ngày ]  @return [ 'gon' => [câu…], 'nhom' => [ {loai, tieu_de, ds:[{ten, so}]} ] ]
 */
function khh_dt_misa_gom_canh( $canh, $chua_chot = 0 ) {
	$loai = array(
		'ma_mh'   => array( 'mau' => '/^Chưa có Mã hàng cho "(.+?)"/u', 'tieu_de' => 'món chưa có Mã hàng — khai ở bảng Mặt hàng MISA, hoặc nạp lại file FABi có cột Mã hàng' ),
		'ma_dv'   => array( 'mau' => '/^Cơ sở "(.+?)" chưa khai Mã đơn vị/u', 'tieu_de' => 'cơ sở chưa khai Mã đơn vị MISA — khai ở bảng Cơ sở' ),
		'combo'   => array( 'mau' => '/^Combo "(.+?)"/u', 'tieu_de' => 'combo chưa bóc tách được (chưa khai sale phụ / công thức, hay phụ vượt giá) — xuất một dòng vé nguyên giá' ),
		'lech'    => array( 'mau' => '/^Tổng dòng .* ≠ doanh thu POS/u', 'tieu_de' => 'ngày có tổng dòng lệch doanh thu POS (chiết khấu hoá đơn hay món ngoài bảng)' ),
	);
	$nhom = array();
	foreach ( $loai as $k => $l ) {
		$nhom[ $k ] = array( 'loai' => $k, 'tieu_de' => $l['tieu_de'], 'ds' => array() );
	}
	$khac = array();
	foreach ( $canh as $cau => $so ) {
		$trung = false;
		foreach ( $loai as $k => $l ) {
			if ( preg_match( $l['mau'], (string) $cau, $m ) ) {
				$ten = isset( $m[1] ) ? $m[1] : (string) $cau;
				if ( 'lech' === $k ) {
					$ten = (string) $cau;
				}
				if ( isset( $nhom[ $k ]['ds'][ $ten ] ) ) {
					$nhom[ $k ]['ds'][ $ten ] += (int) $so;
				} else {
					$nhom[ $k ]['ds'][ $ten ] = (int) $so;
				}
				$trung = true;
				break;
			}
		}
		if ( ! $trung ) {
			$khac[] = $so > 1 ? $cau . ' (' . $so . ' ngày)' : (string) $cau;
		}
	}
	$gon = array();
	if ( $chua_chot ) {
		$gon[] = $chua_chot . ' ngày cơ sở chưa "Lưu và chốt" báo cáo — số máy POS vẫn xuất được, nhưng hàng bán thực có thể còn đổi.';
	}
	$ra_nhom = array();
	foreach ( $nhom as $k => $n ) {
		if ( ! $n['ds'] ) {
			continue;
		}
		arsort( $n['ds'] );
		$ten_ds = array_keys( $n['ds'] );
		$dau    = array_slice( $ten_ds, 0, 'lech' === $k ? 2 : 6 );
		$con    = count( $ten_ds ) - count( $dau );
		$gon[]  = count( $ten_ds ) . ' ' . $n['tieu_de'] . ': ' . implode( '; ', $dau ) . ( $con > 0 ? ' … và ' . $con . ' nữa' : '' ) . '.';
		$ds     = array();
		foreach ( $n['ds'] as $ten => $so ) {
			$ds[] = array( 'ten' => (string) $ten, 'so' => (int) $so );
		}
		$ra_nhom[] = array( 'loai' => $k, 'tieu_de' => $n['tieu_de'], 'ds' => $ds );
	}
	foreach ( $khac as $c ) {
		$gon[] = $c;
	}
	return array( 'gon' => $gon, 'nhom' => $ra_nhom );
}

/**
 * Mặt hàng từng thấy trong kỳ (mọi quán được xem) + thành phần combo — để bảng Mặt hàng MISA bày ra khai.
 * Mỗi dòng: { ten, la_ve, combo, ma_fabi, so_luong, tien, cf:{ma,ten,dvt,gia} }.
 */
function khh_dt_misa_mh_thay( $tu, $den, $cua_hang = '' ) {
	global $wpdb;
	$bang = khh_dt_bang();
	$sql  = "SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s";
	$args = array( $tu, $den );
	if ( '' !== $cua_hang && '*' !== $cua_hang ) {
		$sql   .= ' AND cua_hang = %s';
		$args[] = $cua_hang;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds    = (array) $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
	$combo = function_exists( 'khh_dt_kho_combo_bang' ) ? khh_dt_kho_combo_bang() : array();
	$cfmh  = khh_dt_misa_mh();
	$ma_fb = khh_dt_misa_ma_fabi();
	$long  = function ( $t ) {
		return function_exists( 'khh_dt_kho_long' ) ? khh_dt_kho_long( $t ) : mb_strtolower( trim( (string) $t ) );
	};
	$ra = array();
	$them = function ( $ten, $la_ve, $la_combo, $q, $r ) use ( &$ra, $long, $cfmh, $ma_fb ) {
		$k = $long( $ten );
		if ( '' === $k ) {
			return;
		}
		if ( ! isset( $ra[ $k ] ) ) {
			$ra[ $k ] = array(
				'ten'      => $ten,
				'la_ve'    => $la_ve,
				'combo'    => $la_combo,
				'ma_fabi'  => isset( $ma_fb[ $k ] ) ? $ma_fb[ $k ] : '',
				'so_luong' => 0,
				'tien'     => 0,
				'cf'       => khh_dt_misa_mh_cua( $ten, $cfmh ),
			);
		}
		$ra[ $k ]['so_luong'] += $q;
		$ra[ $k ]['tien']     += $r;
		$ra[ $k ]['combo']     = $ra[ $k ]['combo'] || $la_combo;
	};
	foreach ( $ds as $mon ) {
		foreach ( khh_dt_json( $mon, array() ) as $m ) {
			$ten = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
			if ( '' === $ten ) {
				continue;
			}
			$k_cb = ( $combo && function_exists( 'khh_dt_kho_khoa_long' ) ) ? khh_dt_kho_khoa_long( $combo, $ten ) : null;
			$them( $ten, khh_dt_misa_la_ve( $m ), null !== $k_cb, isset( $m['q'] ) ? (float) $m['q'] : 0, isset( $m['r'] ) ? (float) $m['r'] : 0 );
			if ( null !== $k_cb ) {
				foreach ( (array) $combo[ $k_cb ] as $tp => $cai ) {
					$them( (string) $tp, false, false, 0, 0 );
				}
			}
		}
	}
	usort( $ra, function ( $a, $b ) {
		if ( $a['la_ve'] !== $b['la_ve'] ) {
			return $a['la_ve'] ? -1 : 1;
		}
		return $b['tien'] <=> $a['tien'];
	} );
	return array_values( $ra );
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_misa_route' );
function khh_dt_misa_route() {
	register_rest_route(
		'khh-dt/v1',
		'/misa',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_misa_xem',
				'permission_callback' => 'khh_dt_duoc_nap',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_misa_luu',
				'permission_callback' => 'khh_dt_duoc_nap',
			),
		)
	);
}

function khh_dt_misa_ky_sach( $tu, $den ) {
	$tu  = preg_match( '~^\d{4}-\d{2}-\d{2}$~', (string) $tu ) ? (string) $tu : '';
	$den = preg_match( '~^\d{4}-\d{2}-\d{2}$~', (string) $den ) ? (string) $den : '';
	if ( '' === $den ) {
		$den = current_time( 'Y-m-d' );
	}
	if ( '' === $tu ) {
		$tu = substr( $den, 0, 8 ) . '01';
	}
	if ( $tu > $den ) {
		list( $tu, $den ) = array( $den, $tu );
	}
	return array( $tu, $den );
}

function khh_dt_rest_misa_xem( $req ) {
	list( $tu, $den ) = khh_dt_misa_ky_sach( $req->get_param( 'tu' ), $req->get_param( 'den' ) );
	$ch = (string) $req->get_param( 'cua_hang' );
	$ch = ( '' !== $ch && '*' !== $ch && function_exists( 'khh_dt_bc_ten_cua' ) ) ? khh_dt_bc_ten_cua( $ch ) : $ch;
	$tt = in_array( (string) $req->get_param( 'tt' ), array( 'chua', 'da', 'tatca' ), true ) ? (string) $req->get_param( 'tt' ) : 'chua';
	$ra = khh_dt_misa_xuat( $tu, $den, $ch, $tt );
	$ra['tu']       = $tu;
	$ra['den']      = $den;
	$ra['cua_hang'] = $ch;
	$ra['tt']       = $tt;
	$ra['cf']       = khh_dt_misa_cf();
	$cs_cf = khh_dt_misa_cs();
	$cs_ds = array();
	foreach ( ( function_exists( 'khh_dt_ds_cua_hang' ) ? khh_dt_ds_cua_hang() : array() ) as $c ) {
		$cs_ds[] = array_merge( array( 'cua_hang' => (string) $c ), khh_dt_misa_cs_cua( (string) $c, $cs_cf ) );
	}
	$ra['cs'] = $cs_ds;
	$ra['mh'] = khh_dt_misa_mh_thay( $tu, $den, $ch );
	return $ra;
}

/**
 * POST: `viec` = cf | cs | mh | da_xuat | bo_xuat. Dữ liệu JSON ở tham số cùng tên với viec (hay `khoa` cho
 * đánh dấu). Trả lại bản xem như GET với cùng kỳ để màn vẽ lại một lần.
 */
function khh_dt_rest_misa_luu( $req ) {
	$viec = (string) $req->get_param( 'viec' );
	$json = function ( $k ) use ( $req ) {
		$v = $req->get_param( $k );
		if ( is_array( $v ) ) {
			return $v;
		}
		$d = json_decode( (string) $v, true );
		return is_array( $d ) ? $d : null;
	};
	if ( 'cf' === $viec ) {
		$d = $json( 'cf' );
		if ( null === $d ) {
			return new WP_Error( 'khh_dt_misa', 'Cấu hình gửi lên không đọc được.', array( 'status' => 400 ) );
		}
		khh_dt_misa_cf_dat( $d );
	} elseif ( 'cs' === $viec ) {
		$d = $json( 'cs' );
		if ( null === $d ) {
			return new WP_Error( 'khh_dt_misa', 'Bảng cơ sở gửi lên không đọc được.', array( 'status' => 400 ) );
		}
		khh_dt_misa_cs_dat( $d );
	} elseif ( 'mh' === $viec ) {
		$d = $json( 'mh' );
		if ( null === $d ) {
			return new WP_Error( 'khh_dt_misa', 'Bảng mặt hàng gửi lên không đọc được.', array( 'status' => 400 ) );
		}
		khh_dt_misa_mh_dat( $d );
	} elseif ( 'da_xuat' === $viec || 'bo_xuat' === $viec ) {
		$d = $json( 'khoa' );
		if ( null === $d ) {
			return new WP_Error( 'khh_dt_misa', 'Thiếu danh sách ngày cần đánh dấu.', array( 'status' => 400 ) );
		}
		$n = khh_dt_misa_danh_dau( $d, 'da_xuat' === $viec );
		$ra = khh_dt_rest_misa_xem( $req );
		$ra['da_doi'] = $n;
		return $ra;
	} else {
		return new WP_Error( 'khh_dt_misa', 'Không hiểu việc "' . $viec . '".', array( 'status' => 400 ) );
	}
	return khh_dt_rest_misa_xem( $req );
}
