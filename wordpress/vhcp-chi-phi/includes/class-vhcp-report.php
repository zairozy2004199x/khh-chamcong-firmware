<?php
/**
 * BÁO CÁO — Tổng quan dòng tiền, đơn chờ kế toán ở mọi mảng, tổng chi phí 1 gian/cơ sở,
 * và bảng "Vận hành theo tuần" gom cả 5 mảng chi phí.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Report {

	/** getFinanceReport(): chi phí xin vs thực tế theo hạng mục · cơ sở · tuần. */
	public static function finance( $opts = array() ) {
		$opts = (array) $opts;
		$f_ky = isset( $opts['ky'] ) && $opts['ky'] !== '' ? $opts['ky'] : 'all';
		$f_tt = isset( $opts['tt'] ) && $opts['tt'] !== '' ? $opts['tt'] : 'all';

		$coso_f = null;
		if ( ! empty( $opts['coso'] ) ) {
			$arr = is_array( $opts['coso'] ) ? $opts['coso'] : explode( ',', (string) $opts['coso'] );
			$arr = array_filter( array_map( function ( $s ) { return mb_strtolower( trim( (string) $s ) ); }, $arr ) );
			if ( count( $arr ) ) { $coso_f = array_fill_keys( $arr, 1 ); }
		}

		$don_info = array(); $ky_set = array();
		foreach ( VHCP_Don::don_rows() as $r ) {
			$ky = VHCP_Util::fmt( $r['ky'] );
			$don_info[ (string) $r['ma_don'] ] = array(
				'ky' => $ky,
				'tt' => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Nháp' ),
				'dp' => VHCP_Util::num( $r['du_phong'] ),
				'bt' => VHCP_Util::num( $r['bu_tru'] ),
			);
			$ky_set[ $ky ] = 1;
		}

		$by_hm = array(); $by_cs = array(); $by_ky = array();
		$tot_xin = 0; $tot_tt = 0; $don_match = array();
		foreach ( VHCP_Don::cp_rows() as $r ) {
			$m = (string) $r['ma_don'];
			if ( $m === '' || ! isset( $don_info[ $m ] ) ) { continue; }
			$info = $don_info[ $m ];
			if ( $f_ky !== 'all' && $info['ky'] !== $f_ky ) { continue; }
			if ( $f_tt !== 'all' && $info['tt'] !== $f_tt ) { continue; }
			$coso = trim( (string) $r['coso'] );
			if ( $coso === '' ) { $coso = '(không cơ sở)'; }
			if ( $coso_f !== null && ! isset( $coso_f[ mb_strtolower( $coso ) ] ) ) { continue; }

			$tt_  = VHCP_Util::num( $r['thanh_tien'] );
			$tm   = VHCP_Util::blank_or_num( $r['thuc_mua'] );
			$eff  = ( $tm === null ) ? $tt_ : $tm;
			$xin  = VHCP_Util::is_phat_sinh( $r['phat_sinh'] ) ? 0 : $tt_;
			$nhom = trim( (string) $r['nhom'] );
			if ( $nhom === '' ) { $nhom = '(không nhóm)'; }

			if ( ! isset( $by_hm[ $nhom ] ) ) { $by_hm[ $nhom ] = array( 'xin' => 0, 'tt' => 0, 'n' => 0 ); }
			$by_hm[ $nhom ]['xin'] += $xin; $by_hm[ $nhom ]['tt'] += $eff; $by_hm[ $nhom ]['n']++;
			if ( ! isset( $by_cs[ $coso ] ) ) { $by_cs[ $coso ] = array( 'xin' => 0, 'tt' => 0 ); }
			$by_cs[ $coso ]['xin'] += $xin; $by_cs[ $coso ]['tt'] += $eff;
			if ( ! isset( $by_ky[ $info['ky'] ] ) ) { $by_ky[ $info['ky'] ] = array( 'xin' => 0, 'tt' => 0 ); }
			$by_ky[ $info['ky'] ]['xin'] += $xin; $by_ky[ $info['ky'] ]['tt'] += $eff;

			$tot_xin += $xin; $tot_tt += $eff; $don_match[ $m ] = 1;
		}

		$du_phong_tot = 0; $bu_tru_tot = 0;
		foreach ( array_keys( $don_match ) as $m ) { $du_phong_tot += $don_info[ $m ]['dp']; $bu_tru_tot += $don_info[ $m ]['bt']; }

		$mk = function ( $o, $key ) {
			$out = array();
			foreach ( $o as $k => $v ) {
				$row = array( 'xin' => $v['xin'], 'thucTe' => $v['tt'], 'cl' => $v['xin'] - $v['tt'] );
				$row[ $key ] = $k;
				if ( isset( $v['n'] ) ) { $row['n'] = $v['n']; }
				$out[] = $row;
			}
			return $out;
		};
		$hm = $mk( $by_hm, 'nhom' );
		$cs = $mk( $by_cs, 'coso' );
		$ky = $mk( $by_ky, 'ky' );
		usort( $hm, function ( $a, $b ) { return $b['thucTe'] <=> $a['thucTe']; } );
		usort( $cs, function ( $a, $b ) { return $b['thucTe'] <=> $a['thucTe']; } );
		usort( $ky, function ( $a, $b ) { return VHCP_Util::ky_num( $a['ky'] ) <=> VHCP_Util::ky_num( $b['ky'] ); } );
		$ky_list = array_keys( $ky_set );
		usort( $ky_list, function ( $a, $b ) { return VHCP_Util::ky_num( $b ) <=> VHCP_Util::ky_num( $a ); } );

		return VHCP_Util::ok( array(
			'soDuDauKy'  => VHCP_Don::get_so_du_dau_ky(),
			'totals'     => array(
				'xin'     => $tot_xin,
				'thucTe'  => $tot_tt,
				'cl'      => $tot_xin - $tot_tt,
				'duPhong' => $du_phong_tot,
				'buTru'   => $bu_tru_tot,
				'soDon'   => count( $don_match ),
				'soTuan'  => count( $ky ),
			),
			'byHangMuc'  => $hm,
			'byCoso'     => $cs,
			'byKy'       => $ky,
			'kyList'     => array_values( $ky_list ),
		) );
	}

	/** getPendingModules(): đơn đang chờ kế toán ở Kỹ thuật · Marketing · Công tác · Setup. */
	public static function pending_modules() {
		$out = array();

		foreach ( VHCP_DuAn::all_with_lines() as $r ) {
			$st = (string) $r['trang_thai'];
			if ( $st !== 'Chờ kế toán duyệt' ) { continue; }
			$sum = 0;
			foreach ( $r['lines'] as $x ) { $sum += VHCP_Util::num( $x['thuc_te'] ); }
			$out[] = array(
				'module'    => 'Kỹ thuật',
				'icon'      => '🔧',
				'tab'       => 'duan',
				'ma'        => $r['ma_da'],
				'ten'       => $r['ten'],
				'phu'       => (string) $r['loai'],
				'trangThai' => $st,
				'soTien'    => $sum,
				'nguoi'     => (string) $r['nguoi_tao'],
				'ngay'      => VHCP_Util::fmt( $r['ngay_tao'] ),
			);
		}

		$agg = array();
		foreach ( VHCP_MK::all_lines() as $r ) {
			$md = (string) $r['ma_don'];
			$agg[ $md ] = ( isset( $agg[ $md ] ) ? $agg[ $md ] : 0 ) + VHCP_Util::num( $r['thuc_te'] );
		}
		foreach ( VHCP_MK::all_dons() as $r ) {
			$st = (string) $r['trang_thai'];
			if ( $st === 'Đã đóng' ) { continue; }
			$out[] = array(
				'module'    => 'Marketing',
				'icon'      => '📣',
				'tab'       => 'mkt',
				'ma'        => $r['ma'],
				'ten'       => ( $r['ten'] !== '' ? $r['ten'] : $r['coso'] ),
				'phu'       => (string) $r['coso'],
				'trangThai' => ( $st !== '' ? $st : 'Đang chạy' ),
				'soTien'    => isset( $agg[ $r['ma'] ] ) ? $agg[ $r['ma'] ] : 0,
				'nguoi'     => (string) $r['nguoi_tao'],
				'ngay'      => VHCP_Util::fmt( $r['ngay_tao'] ),
			);
		}

		foreach ( VHCP_BP::all_with_lines() as $r ) {
			$st = (string) $r['trang_thai'];
			if ( $st === 'Đã đóng' ) { continue; }
			$lo  = (string) $r['loai'];
			$sum = 0;
			foreach ( $r['lines'] as $x ) { $sum += VHCP_Util::num( $x['thuc_te'] ); }
			$out[] = array(
				'module'    => $lo,
				'icon'      => ( $lo === 'Công tác' ) ? '✈️' : '🛠️',
				'tab'       => ( $lo === 'Công tác' ) ? 'congtac' : 'setup',
				'ma'        => $r['ma'],
				'ten'       => $r['ten'],
				'phu'       => (string) $r['nguoi'],
				'trangThai' => ( $st !== '' ? $st : 'Đang xử lý' ),
				'soTien'    => $sum,
				'nguoi'     => (string) $r['nguoi_tao'],
				'ngay'      => VHCP_Util::fmt( $r['ngay_tao'] ),
			);
		}

		return VHCP_Util::ok( array( 'items' => $out ) );
	}

	/** getGianReport(): tổng chi phí 1 gian/cơ sở, gom mọi mảng. */
	public static function gian_report( $key ) {
		$key = trim( (string) $key );
		if ( $key === '' ) { return VHCP_Util::err( 'Thiếu tên gian/cơ sở' ); }
		$kl = mb_strtolower( $key );
		$sections = array(); $grand = 0;

		// 1) Kỹ thuật
		$rows = array(); $tot = 0;
		foreach ( VHCP_DuAn::all_with_lines() as $r ) {
			$match_proj = ( mb_strtolower( trim( (string) $r['ten'] ) ) === $kl );
			$child = array();
			foreach ( $r['lines'] as $x ) {
				$cap = trim( (string) $x['cap_cha'] );
				if ( $cap !== '' && $cap !== '(Phát sinh)' ) { $child[ $cap ] = ( isset( $child[ $cap ] ) ? $child[ $cap ] : 0 ) + VHCP_Util::num( $x['thuc_te'] ); }
			}
			foreach ( $r['lines'] as $x ) {
				$nd   = trim( (string) $x['noi_dung'] );
				$cap  = trim( (string) $x['cap_cha'] );
				$tt   = VHCP_Util::num( $x['thuc_te'] );
				$gian = trim( (string) $x['gian'] );
				if ( $cap === '' && isset( $child[ $nd ] ) && $child[ $nd ] > 0 ) { continue; }
				if ( ! $tt ) { continue; }
				if ( $match_proj || mb_strtolower( $gian ) === $kl ) {
					$rows[] = array( 'nd' => $nd, 'ct' => (string) $r['ten'] . ( $gian !== '' ? ' · ' . $gian : '' ), 'tien' => $tt );
					$tot   += $tt;
				}
			}
		}
		if ( count( $rows ) ) { $sections[] = array( 'module' => '🔧 Kỹ thuật (Setup/Tháo dỡ)', 'rows' => $rows, 'tot' => $tot ); $grand += $tot; }

		// 2) Marketing
		$rows = array(); $tot = 0;
		$don = array();
		foreach ( VHCP_MK::all_dons() as $r ) { $don[ (string) $r['ma'] ] = array( 'coso' => (string) $r['coso'], 'ten' => (string) $r['ten'] ); }
		foreach ( VHCP_MK::all_lines() as $r ) {
			$d = isset( $don[ (string) $r['ma_don'] ] ) ? $don[ (string) $r['ma_don'] ] : null;
			if ( ! $d ) { continue; }
			if ( mb_strtolower( trim( $d['coso'] ) ) !== $kl ) { continue; }
			$tt = VHCP_Util::num( $r['thuc_te'] );
			if ( ! $tt ) { continue; }
			$rows[] = array( 'nd' => (string) $r['noi_dung'], 'ct' => $d['ten'] . ( $r['kenh'] !== '' ? ' · ' . $r['kenh'] : '' ), 'tien' => $tt );
			$tot   += $tt;
		}
		if ( count( $rows ) ) { $sections[] = array( 'module' => '📣 Marketing', 'rows' => $rows, 'tot' => $tot ); $grand += $tot; }

		// 3) Công tác / Setup
		$rows = array(); $tot = 0;
		foreach ( VHCP_BP::all_with_lines() as $r ) {
			if ( mb_strtolower( trim( (string) $r['dia_diem'] ) ) !== $kl ) { continue; }
			foreach ( $r['lines'] as $x ) {
				$tt = VHCP_Util::num( $x['thuc_te'] );
				if ( ! $tt ) { continue; }
				$rows[] = array( 'nd' => (string) $x['noi_dung'], 'ct' => (string) $r['loai'] . ' · ' . (string) $r['ten'], 'tien' => $tt );
				$tot   += $tt;
			}
		}
		if ( count( $rows ) ) { $sections[] = array( 'module' => '✈️🛠️ Công tác / Setup', 'rows' => $rows, 'tot' => $tot ); $grand += $tot; }

		// 4) Sổ chi phí (nhập phẳng) — kèm mã tài khoản để dò thẳng, không phải lần lại hàm
		$rows = array(); $tot = 0;
		foreach ( VHCP_SoChi::all_rows() as $r ) {
			if ( mb_strtolower( trim( (string) $r['coso'] ) ) !== $kl ) { continue; }
			$so = VHCP_Util::num( $r['so_tien'] );
			if ( ! $so ) { continue; }
			$ct = trim( (string) $r['loai'] );
			if ( trim( (string) $r['tk_no'] ) !== '' ) { $ct .= ' · TK ' . trim( (string) $r['tk_no'] ); }
			$rows[] = array( 'nd' => (string) $r['noi_dung'], 'ct' => $ct, 'tien' => $so );
			$tot   += $so;
		}
		if ( count( $rows ) ) { $sections[] = array( 'module' => '💵 Sổ chi phí', 'rows' => $rows, 'tot' => $tot ); $grand += $tot; }

		// 5) Đơn vận hành
		$rows = array(); $tot = 0;
		foreach ( VHCP_Don::cp_rows() as $r ) {
			if ( mb_strtolower( trim( (string) $r['coso'] ) ) !== $kl ) { continue; }
			$tt = VHCP_Util::num( $r['thanh_tien'] );
			$tm = VHCP_Util::blank_or_num( $r['thuc_mua'] );
			$so = ( $tm === null ) ? $tt : $tm;
			if ( ! $so ) { continue; }
			$rows[] = array( 'nd' => ( (string) $r['noi_dung'] !== '' ? (string) $r['noi_dung'] : (string) $r['nhom'] ), 'ct' => (string) $r['nhom'], 'tien' => $so );
			$tot   += $so;
		}
		if ( count( $rows ) ) { $sections[] = array( 'module' => '📝 Đơn vận hành', 'rows' => $rows, 'tot' => $tot ); $grand += $tot; }

		return VHCP_Util::ok( array( 'key' => $key, 'sections' => $sections, 'grand' => $grand ) );
	}

	/**
	 * getVanHanhTuan(): chi phí VẬN HÀNH theo tuần (Thứ 2 → Chủ nhật), mỗi cơ sở 1 dòng.
	 *
	 * Từ bản 1.1.0 chỉ gom 3 nguồn của mảng vận hành: 📝 đơn vận hành · 💵 sổ chi phí ·
	 * 📣 marketing. Chi phí Kỹ thuật / Công tác / Setup KHÔNG còn bị kéo vào đây.
	 */
	public static function van_hanh_tuan( $monday_str = '' ) {
		$mon = $monday_str ? VHCP_Util::vh_parse_dmy( $monday_str ) : null;
		if ( ! $mon ) { $mon = VHCP_Util::now(); }
		$mon = VHCP_Util::vh_monday( $mon );
		$sun = clone $mon;
		$sun->modify( '+6 day' );
		$lo = VHCP_Util::vh_ymd( $mon );
		$hi = VHCP_Util::vh_ymd( $sun );

		$in_wk = function ( $dt ) use ( $lo, $hi ) {
			if ( ! $dt ) { return false; }
			$k = VHCP_Util::vh_ymd( $dt );
			return $k >= $lo && $k <= $hi;
		};
		$dstr = function ( $dt ) { return $dt ? $dt->format( 'd/m/Y' ) : ''; };

		$map = array();
		$co  = function ( $c ) use ( &$map ) {
			$c = trim( (string) $c );
			if ( $c === '' ) { $c = '(Không rõ cơ sở)'; }
			if ( ! isset( $map[ $c ] ) ) { $map[ $c ] = array( 'coso' => $c, 'vh' => 0, 'chi' => 0, 'mkt' => 0, 'lines' => array() ); }
			return $c;
		};

		// 1) Đơn vận hành
		foreach ( VHCP_Don::cp_rows() as $r ) {
			$dt = VHCP_Util::vh_parse_dmy( $r['ngay'] );
			if ( ! $in_wk( $dt ) ) { continue; }
			$tt = VHCP_Util::num( $r['thanh_tien'] );
			$tm = VHCP_Util::blank_or_num( $r['thuc_mua'] );
			$st = ( $tm === null ) ? $tt : $tm;
			if ( ! $st ) { continue; }
			$k = $co( $r['coso'] );
			$map[ $k ]['vh'] += $st;
			$map[ $k ]['lines'][] = array( 'mod' => 'vh', 'nd' => ( (string) $r['noi_dung'] !== '' ? (string) $r['noi_dung'] : (string) $r['nhom'] ), 'ct' => (string) $r['nhom'], 'ngay' => $dstr( $dt ), 'tien' => $st );
		}

		// 2) Sổ chi phí (nhập phẳng) — mã tài khoản đi kèm từng dòng
		foreach ( VHCP_SoChi::all_rows() as $r ) {
			$dt = VHCP_Util::vh_parse_dmy( $r['ngay'] );
			if ( ! $in_wk( $dt ) ) { continue; }
			$st = VHCP_Util::num( $r['so_tien'] );
			if ( ! $st ) { continue; }
			$k = $co( $r['coso'] );
			$map[ $k ]['chi'] += $st;
			$ct = trim( (string) $r['loai'] );
			if ( trim( (string) $r['tk_no'] ) !== '' ) { $ct .= ' · TK ' . trim( (string) $r['tk_no'] ); }
			$map[ $k ]['lines'][] = array( 'mod' => 'chi', 'nd' => (string) $r['noi_dung'], 'ct' => $ct, 'ngay' => $dstr( $dt ), 'tien' => $st );
		}

		// 3) Marketing
		$don = array();
		foreach ( VHCP_MK::all_dons() as $r ) { $don[ (string) $r['ma'] ] = array( 'coso' => (string) $r['coso'], 'ten' => (string) $r['ten'] ); }
		foreach ( VHCP_MK::all_lines() as $r ) {
			$dt = VHCP_Util::vh_parse_dmy( $r['ngay'] );
			if ( ! $in_wk( $dt ) ) { continue; }
			$tt = VHCP_Util::num( $r['thuc_te'] );
			if ( ! $tt ) { continue; }
			$dd = isset( $don[ (string) $r['ma_don'] ] ) ? $don[ (string) $r['ma_don'] ] : array( 'coso' => '(Marketing)', 'ten' => '' );
			$k  = $co( $dd['coso'] );
			$map[ $k ]['mkt'] += $tt;
			$map[ $k ]['lines'][] = array( 'mod' => 'mkt', 'nd' => (string) $r['noi_dung'], 'ct' => $dd['ten'] . ( $r['kenh'] !== '' ? ' · ' . $r['kenh'] : '' ), 'ngay' => $dstr( $dt ), 'tien' => $tt );
		}

		// ĐÃ BỎ (bản 1.1.0): gom Công tác · Setup · Kỹ thuật vào chi phí vận hành.
		// Mỗi mảng đó đứng riêng theo mã tài khoản của nó; kéo chung vào đây khiến muốn dò
		// một con số là phải lần lại hàm gom nhiều mảng.

		$list = array();
		foreach ( $map as $m ) {
			$m['tong'] = $m['vh'] + $m['chi'] + $m['mkt'];
			usort( $m['lines'], function ( $a, $b ) { return $b['tien'] <=> $a['tien']; } );
			if ( $m['tong'] > 0 ) { $list[] = $m; }
		}
		usort( $list, function ( $a, $b ) { return $b['tong'] <=> $a['tong']; } );

		$grand = array( 'vh' => 0, 'chi' => 0, 'mkt' => 0, 'tong' => 0 );
		foreach ( $list as $m ) {
			foreach ( array( 'vh', 'chi', 'mkt', 'tong' ) as $k ) { $grand[ $k ] += $m[ $k ]; }
		}

		$prev = clone $mon; $prev->modify( '-7 day' );
		$next = clone $mon; $next->modify( '+7 day' );

		return VHCP_Util::ok( array(
			'monday'     => $mon->format( 'd/m/Y' ),
			'sunday'     => $sun->format( 'd/m/Y' ),
			'weekNo'     => VHCP_Util::vh_week_no( $mon ),
			'nam'        => (int) $mon->format( 'Y' ),
			'prevMonday' => $prev->format( 'd/m/Y' ),
			'nextMonday' => $next->format( 'd/m/Y' ),
			'list'       => $list,
			'grand'      => $grand,
		) );
	}
}
