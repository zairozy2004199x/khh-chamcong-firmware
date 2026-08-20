<?php
/**
 * TRA THEO MÃ TÀI KHOẢN — gõ 1 mã (vd 64127) là ra mọi khoản chi của MỌI mảng mang mã đó.
 *
 * Đây là thứ thay hẳn việc "gom": không cộng nhiều mảng thành một cục rồi phải lần lại hàm,
 * mà đọc thẳng mã tài khoản đang gắn trên từng dòng của 5 nguồn:
 *   💵 Sổ chi phí · 📝 Đơn vận hành · 🔧 Kỹ thuật · 📣 Marketing · ✈️🛠️ Công tác/Setup
 *
 * Mã hiện ở đây LÀ mã sẽ đi vào MISA (dùng chung bộ chốt với phần xuất), nên tra ra bao nhiêu
 * thì hạch toán đúng bấy nhiêu. Dòng của 3 mảng chưa gắn loại chi phí vẫn hiện, mang mã cũ
 * (Nợ 141 · Có 331/64125) và được đếm riêng ở "dùng mã cũ" để biết còn phải khai chỗ nào.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_TraMa {

	/** Nhãn từng mảng. */
	public static function mangs() {
		return array(
			'sochi'  => array( 'ten' => 'Sổ chi phí',       'icon' => '💵', 'tab' => 'sochi' ),
			'don'    => array( 'ten' => 'Đơn vận hành',     'icon' => '📝', 'tab' => 'don' ),
			'kt'     => array( 'ten' => 'Kỹ thuật',         'icon' => '🔧', 'tab' => 'duan' ),
			'mkt'    => array( 'ten' => 'Marketing',        'icon' => '📣', 'tab' => 'mkt' ),
			'ct'     => array( 'ten' => 'Công tác',         'icon' => '✈️', 'tab' => 'congtac' ),
			'setup'  => array( 'ten' => 'Setup',            'icon' => '🛠️', 'tab' => 'setup' ),
		);
	}

	private static function ky_of( $ky, $ngay_dmy ) {
		$ky = trim( (string) $ky );
		if ( $ky !== '' ) { return $ky; }
		if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', (string) $ngay_dmy, $m ) ) { return $m[2] . '/' . $m[3]; }
		return '';
	}

	private static function sort_key( $ngay_dmy ) {
		if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', (string) $ngay_dmy, $m ) ) {
			return (int) $m[3] * 10000 + (int) $m[2] * 100 + (int) $m[1];
		}
		return 0;
	}

	/** Gom mọi dòng chi của mọi mảng, kèm mã tài khoản thật của dòng đó. */
	public static function all_lines() {
		$out = array();

		// 1) Sổ chi phí
		foreach ( VHCP_SoChi::all_rows() as $r ) {
			$tien = VHCP_Util::num( $r['so_tien'] );
			if ( ! $tien ) { continue; }
			$ngay = VHCP_Util::fmt( $r['ngay'] );
			$out[] = array(
				'mang' => 'sochi',
				'ngay' => $ngay,
				'ky'   => self::ky_of( $r['ky'], $ngay ),
				'coso' => (string) $r['coso'],
				'loai' => (string) $r['loai'],
				'tkNo' => trim( (string) $r['tk_no'] ),
				'tkCo' => trim( (string) $r['tk_co'] ),
				'maDt' => trim( (string) $r['ma_dt'] ),
				'noiDung' => (string) $r['noi_dung'],
				'thuoc'   => trim( (string) $r['hinh_thuc'] ),
				'tien'    => $tien,
				'maCu'    => false,
			);
		}

		// 2) Đơn vận hành — thực chi = Thực mua nếu đã nhập, ngược lại Thành tiền
		$don_ky = array();
		foreach ( VHCP_Don::don_rows() as $d ) { $don_ky[ (string) $d['ma_don'] ] = VHCP_Util::fmt( $d['ky'] ); }
		foreach ( VHCP_Don::cp_rows() as $r ) {
			$tt   = VHCP_Util::num( $r['thanh_tien'] );
			$tm   = VHCP_Util::blank_or_num( $r['thuc_mua'] );
			$tien = ( $tm === null ) ? $tt : $tm;
			if ( ! $tien ) { continue; }
			$ma_don = (string) $r['ma_don'];
			$ngay   = VHCP_Util::fmt( $r['ngay'] );
			$out[]  = array(
				'mang' => 'don',
				'ngay' => $ngay,
				'ky'   => self::ky_of( isset( $don_ky[ $ma_don ] ) ? $don_ky[ $ma_don ] : '', $ngay ),
				'coso' => (string) $r['coso'],
				'loai' => (string) $r['nhom'],
				'tkNo' => trim( (string) $r['tk_no'] ),
				'tkCo' => trim( (string) $r['tk_co'] ),
				'maDt' => '',
				'noiDung' => (string) $r['noi_dung'],
				'thuoc'   => 'Đơn ' . $ma_don,
				'tien'    => $tien,
				'maCu'    => false,
			);
		}

		// 3) Kỹ thuật — chỉ dòng "lá" (bỏ hạng mục lớn đã có mục con để khỏi cộng trùng)
		$app_map = VHCP_DuAn::approve_date_map();
		foreach ( VHCP_DuAn::all_with_lines() as $p ) {
			$ma_da  = (string) $p['ma_da'];
			$ngay   = isset( $app_map[ $ma_da ] ) ? $app_map[ $ma_da ] : VHCP_Util::fmt( $p['ngay_tao'] );
			$ten_da = (string) $p['ten'];
			$child = array(); $parent_ht = array();
			foreach ( $p['lines'] as $x ) {
				$cap = trim( (string) $x['cap_cha'] );
				if ( $cap !== '' && $cap !== '(Phát sinh)' ) { $child[ $cap ] = ( isset( $child[ $cap ] ) ? $child[ $cap ] : 0 ) + VHCP_Util::num( $x['thuc_te'] ); }
				if ( $cap === '' ) { $parent_ht[ trim( (string) $x['noi_dung'] ) ] = trim( (string) $x['hinh_thuc'] ); }
			}
			foreach ( $p['lines'] as $x ) {
				$nd   = trim( (string) $x['noi_dung'] );
				$cap  = trim( (string) $x['cap_cha'] );
				$tien = VHCP_Util::num( $x['thuc_te'] );
				if ( $cap === '' && isset( $child[ $nd ] ) && $child[ $nd ] > 0 ) { continue; }
				if ( ! $tien ) { continue; }
				$ht    = ( $cap !== '' && $cap !== '(Phát sinh)' && ! empty( $parent_ht[ $cap ] ) ) ? $parent_ht[ $cap ] : trim( (string) $x['hinh_thuc'] );
				$is_tt = ( $ht === 'Trực tiếp' );
				$tkm   = VHCP_Misa::tk_mang( $x['loai_cp'], $is_tt, $x['tk_no'], '', $x['ma_dt'] );
				$gian  = trim( (string) $x['gian'] );
				$out[] = array(
					'mang' => 'kt',
					'ngay' => $ngay,
					'ky'   => self::ky_of( '', $ngay ),
					'coso' => ( $gian !== '' ? $gian : $ten_da ),
					'loai' => trim( (string) $x['loai_cp'] ),
					'tkNo' => $tkm['tk_no'],
					'tkCo' => $tkm['tk_co'],
					'maDt' => $tkm['ma_dt'],
					'noiDung' => $nd,
					'thuoc'   => $ten_da . ( $cap !== '' ? ' · ' . $cap : '' ),
					'tien'    => $tien,
					'maCu'    => ! empty( $tkm['legacy'] ),
				);
			}
		}

		// 4) Marketing
		$mk_don = array();
		foreach ( VHCP_MK::all_dons() as $d ) { $mk_don[ (string) $d['ma'] ] = $d; }
		foreach ( VHCP_MK::all_lines() as $r ) {
			$tien = VHCP_Util::num( $r['thuc_te'] );
			if ( ! $tien ) { continue; }
			$d     = isset( $mk_don[ (string) $r['ma_don'] ] ) ? $mk_don[ (string) $r['ma_don'] ] : array( 'coso' => '', 'ten' => '', 'ky' => '', 'ngay_tao' => '' );
			$is_tt = ( trim( (string) $r['hinh_thuc'] ) === 'Trực tiếp' );
			$tkm   = VHCP_Misa::tk_mang( $r['loai_cp'], $is_tt, $r['tk_no'], $r['tk_co'], $r['ma_dt'] );
			$ngay  = VHCP_Util::fmt( $r['ngay'] );
			if ( $ngay === '' ) { $ngay = VHCP_Util::fmt( $d['ngay_tao'] ); }
			$out[] = array(
				'mang' => 'mkt',
				'ngay' => $ngay,
				'ky'   => self::ky_of( isset( $d['ky'] ) ? $d['ky'] : '', $ngay ),
				'coso' => (string) $d['coso'],
				'loai' => trim( (string) $r['loai_cp'] ),
				'tkNo' => $tkm['tk_no'],
				'tkCo' => $tkm['tk_co'],
				'maDt' => $tkm['ma_dt'],
				'noiDung' => (string) $r['noi_dung'],
				'thuoc'   => trim( (string) $d['ten'] . ( $r['kenh'] !== '' ? ' · ' . $r['kenh'] : '' ) ),
				'tien'    => $tien,
				'maCu'    => ! empty( $tkm['legacy'] ),
			);
		}

		// 5) Công tác / Setup
		foreach ( VHCP_BP::all_with_lines() as $b ) {
			$lo    = (string) $b['loai'];
			$mang  = ( $lo === 'Setup' ) ? 'setup' : 'ct';
			$ngay0 = VHCP_Util::fmt( $b['ngay_tao'] );
			foreach ( $b['lines'] as $x ) {
				$tien = VHCP_Util::num( $x['thuc_te'] );
				if ( ! $tien ) { continue; }
				$is_tt = ( trim( (string) $x['hinh_thuc'] ) === 'Trực tiếp' );
				$tkm   = VHCP_Misa::tk_mang( $x['loai_cp'], $is_tt, $x['tk_no'], $x['tk_co'], $x['ma_dt'] );
				$ngay  = VHCP_Util::fmt( $x['ngay'] );
				if ( $ngay === '' ) { $ngay = $ngay0; }
				$out[] = array(
					'mang' => $mang,
					'ngay' => $ngay,
					'ky'   => self::ky_of( (string) $b['ky'], $ngay ),
					'coso' => (string) $b['dia_diem'],
					'loai' => trim( (string) $x['loai_cp'] ),
					'tkNo' => $tkm['tk_no'],
					'tkCo' => $tkm['tk_co'],
					'maDt' => $tkm['ma_dt'],
					'noiDung' => (string) $x['noi_dung'],
					'thuoc'   => (string) $b['ten'],
					'tien'    => $tien,
					'maCu'    => ! empty( $tkm['legacy'] ),
				);
			}
		}

		return $out;
	}

	/**
	 * Tra cứu. $opts: tkNo (mã hoặc 'all') · ky · coso · mang · q · coso_scope (giới hạn cơ sở của NV).
	 */
	public static function search( $opts = array() ) {
		$opts = (array) $opts;
		$o    = function ( $k, $d = 'all' ) use ( $opts ) { return isset( $opts[ $k ] ) && $opts[ $k ] !== '' ? (string) $opts[ $k ] : $d; };

		$f_tk   = trim( $o( 'tkNo' ) );
		$f_ky   = $o( 'ky' );
		$f_cs   = $o( 'coso' );
		$f_mang = $o( 'mang' );
		$q      = mb_strtolower( trim( (string) $o( 'q', '' ) ) );

		$scope = null;
		if ( ! empty( $opts['coso_scope'] ) ) {
			$arr = is_array( $opts['coso_scope'] ) ? $opts['coso_scope'] : explode( ',', (string) $opts['coso_scope'] );
			$arr = array_filter( array_map( function ( $s ) { return mb_strtolower( trim( (string) $s ) ); }, $arr ) );
			if ( count( $arr ) ) { $scope = array_fill_keys( $arr, 1 ); }
		}

		$all = self::all_lines();

		$ma_set = array(); $ky_set = array(); $cs_set = array();
		$items = array(); $tong = 0;
		$by_ma = array(); $by_mang = array(); $by_ky = array(); $by_cs = array();
		$thieu_ma = array(); $ma_cu = array();

		foreach ( $all as $r ) {
			if ( $r['tkNo'] !== '' ) { $ma_set[ $r['tkNo'] ] = 1; }
			if ( $r['ky'] !== '' ) { $ky_set[ $r['ky'] ] = 1; }
			if ( $r['coso'] !== '' ) { $cs_set[ $r['coso'] ] = 1; }

			// đếm việc còn phải khai, tính trên TOÀN BỘ dữ liệu (không theo bộ lọc)
			if ( $r['tkNo'] === '' ) {
				$k = $r['mang'] . '|' . ( $r['loai'] !== '' ? $r['loai'] : '(chưa chọn loại)' );
				if ( ! isset( $thieu_ma[ $k ] ) ) { $thieu_ma[ $k ] = array( 'mang' => $r['mang'], 'loai' => ( $r['loai'] !== '' ? $r['loai'] : '(chưa chọn loại)' ), 'n' => 0, 'tien' => 0 ); }
				$thieu_ma[ $k ]['n']++;
				$thieu_ma[ $k ]['tien'] += $r['tien'];
			} elseif ( ! empty( $r['maCu'] ) ) {
				if ( ! isset( $ma_cu[ $r['mang'] ] ) ) { $ma_cu[ $r['mang'] ] = array( 'mang' => $r['mang'], 'n' => 0, 'tien' => 0 ); }
				$ma_cu[ $r['mang'] ]['n']++;
				$ma_cu[ $r['mang'] ]['tien'] += $r['tien'];
			}

			if ( $scope !== null && ! isset( $scope[ mb_strtolower( trim( $r['coso'] ) ) ] ) ) { continue; }
			if ( $f_tk !== 'all' && $f_tk !== '' && $r['tkNo'] !== $f_tk ) { continue; }
			if ( $f_ky !== 'all' && $r['ky'] !== $f_ky ) { continue; }
			if ( $f_cs !== 'all' && $r['coso'] !== $f_cs ) { continue; }
			if ( $f_mang !== 'all' && $r['mang'] !== $f_mang ) { continue; }
			if ( $q !== '' ) {
				$hay = mb_strtolower( $r['noiDung'] . ' ' . $r['loai'] . ' ' . $r['coso'] . ' ' . $r['thuoc'] . ' ' . $r['tkNo'] . ' ' . $r['tkCo'] );
				if ( mb_strpos( $hay, $q ) === false ) { continue; }
			}

			$items[] = $r;
			$tong   += $r['tien'];

			$km = $r['tkNo'] !== '' ? $r['tkNo'] : '(chưa có mã)';
			if ( ! isset( $by_ma[ $km ] ) ) { $by_ma[ $km ] = array( 'tkNo' => $km, 'n' => 0, 'tien' => 0, 'loai' => array() ); }
			$by_ma[ $km ]['n']++;
			$by_ma[ $km ]['tien'] += $r['tien'];
			if ( $r['loai'] !== '' ) { $by_ma[ $km ]['loai'][ $r['loai'] ] = 1; }

			if ( ! isset( $by_mang[ $r['mang'] ] ) ) { $by_mang[ $r['mang'] ] = array( 'mang' => $r['mang'], 'n' => 0, 'tien' => 0 ); }
			$by_mang[ $r['mang'] ]['n']++;
			$by_mang[ $r['mang'] ]['tien'] += $r['tien'];

			$kk = $r['ky'] !== '' ? $r['ky'] : '(không kỳ)';
			if ( ! isset( $by_ky[ $kk ] ) ) { $by_ky[ $kk ] = array( 'ky' => $kk, 'tien' => 0 ); }
			$by_ky[ $kk ]['tien'] += $r['tien'];

			$kc = $r['coso'] !== '' ? $r['coso'] : '(không cơ sở)';
			if ( ! isset( $by_cs[ $kc ] ) ) { $by_cs[ $kc ] = array( 'coso' => $kc, 'tien' => 0 ); }
			$by_cs[ $kc ]['tien'] += $r['tien'];
		}

		usort( $items, function ( $a, $b ) {
			$ka = VHCP_TraMa::sort_key_pub( $a['ngay'] );
			$kb = VHCP_TraMa::sort_key_pub( $b['ngay'] );
			if ( $ka !== $kb ) { return $kb <=> $ka; }
			return $b['tien'] <=> $a['tien'];
		} );

		foreach ( $by_ma as $k => $v ) { $by_ma[ $k ]['loai'] = implode( ', ', array_keys( $v['loai'] ) ); }
		$desc = function ( $arr ) { $arr = array_values( $arr ); usort( $arr, function ( $a, $b ) { return $b['tien'] <=> $a['tien']; } ); return $arr; };

		// mã để chọn: mã đang có trên dữ liệu + mã đã khai trong danh mục
		$cfg = VHCP_Cfg::cfg_static();
		foreach ( (array) ( isset( $cfg['loaiChiPhi'] ) ? $cfg['loaiChiPhi'] : array() ) as $x ) {
			if ( trim( (string) $x['tkNo'] ) !== '' ) { $ma_set[ trim( (string) $x['tkNo'] ) ] = 1; }
		}
		$ma_list = array_map( 'strval', array_keys( $ma_set ) );   // mã toàn số -> khóa mảng hóa int, ép lại chuỗi
		sort( $ma_list, SORT_NATURAL );
		$ky_list = array_keys( $ky_set );
		usort( $ky_list, function ( $a, $b ) { return VHCP_Util::ky_num( $b ) <=> VHCP_Util::ky_num( $a ); } );
		$cs_list = array_keys( $cs_set );
		sort( $cs_list );

		return VHCP_Util::ok( array(
			'items'    => $items,
			'soDong'   => count( $items ),
			'tong'     => $tong,
			'byMa'     => $desc( $by_ma ),
			'byMang'   => $desc( $by_mang ),
			'byKy'     => $desc( $by_ky ),
			'byCoso'   => $desc( $by_cs ),
			'maList'   => array_values( $ma_list ),
			'kyList'   => array_values( $ky_list ),
			'cosoList' => array_values( $cs_list ),
			'mangs'    => self::mangs(),
			'thieuMa'  => $desc( $thieu_ma ),
			'maCu'     => $desc( $ma_cu ),
		) );
	}

	/**
	 * GÁN MÃ CHO DÒNG CŨ Ở MỌI MẢNG trong 1 lần bấm.
	 * Trả về số dòng đã gán từng mảng + những loại chi phí còn thiếu mã + số dòng
	 * chưa chọn loại (phải vào tab của mảng đó chọn tay, không suy được).
	 */
	public static function gan_ma_tat_ca( $all = false ) {
		$res = array(
			'sochi' => VHCP_SoChi::gan_ma_tai_khoan( $all ),
			'don'   => VHCP_Don::gan_ma_tai_khoan( $all ),
			'kt'    => VHCP_DuAn::gan_ma_tai_khoan( $all ),
			'mkt'   => VHCP_MK::gan_ma_tai_khoan( $all ),
			'bp'    => VHCP_BP::gan_ma_tai_khoan( $all ),
		);
		$tong = 0; $thieu = array(); $chua = 0; $per = array();
		foreach ( $res as $k => $v ) {
			$n = isset( $v['updated'] ) ? (int) $v['updated'] : 0;
			$tong += $n;
			$per[ $k ] = $n;
			foreach ( (array) ( isset( $v['thieuMa'] ) ? $v['thieuMa'] : array() ) as $x ) { $thieu[ $x ] = 1; }
			$chua += (int) ( isset( $v['chuaChonLoai'] ) ? $v['chuaChonLoai'] : 0 ) + (int) ( isset( $v['khongSuyDuoc'] ) ? $v['khongSuyDuoc'] : 0 );
		}
		return VHCP_Util::ok( array( 'updated' => $tong, 'theoMang' => $per, 'thieuMa' => array_keys( $thieu ), 'chuaChonLoai' => $chua ) );
	}

	/** dùng trong usort (PHP 5.x không cho gọi private từ closure tĩnh). */
	public static function sort_key_pub( $ngay_dmy ) {
		return self::sort_key( $ngay_dmy );
	}
}
