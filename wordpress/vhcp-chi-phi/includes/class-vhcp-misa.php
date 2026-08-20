<?php
/**
 * XUẤT MISA — 4 luồng (Đơn vận hành · Kỹ thuật · Marketing · Công tác/Setup),
 * cùng 10 cột theo mẫu import "Chứng từ nghiệp vụ khác" của MISA.
 * Giao diện tự đổ ra CSV/XLSX nên PHP chỉ trả cols + rows như app cũ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Misa {

	public static function cols() {
		return array( 'Ngày chứng từ (*)', 'Ngày hạch toán (*)', 'Số chứng từ (*)', 'Diễn giải', 'Diễn giải (Hạch toán)', 'TK Nợ (*)', 'TK Có (*)', 'Số tiền', 'Mã đối tượng Có', 'Mã đơn vị' );
	}

	/** _cleanNhom(): bỏ đuôi "- NCC" / "- Mua lẻ" khỏi tên nhóm. */
	private static function clean_nhom( $nhom ) {
		$s = preg_replace( '/\s*-\s*NCC/iu', '', (string) $nhom );
		$s = preg_replace( '/\s*-\s*Mua lẻ/iu', '', $s );
		$s = preg_replace( '/\s{2,}/u', ' ', $s );
		return trim( $s );
	}

	/** exportMisa(): đơn vận hành. mode = chuaxuat|daxuat ; plF = all|cn|ncc. */
	public static function export_misa( $ky = 'all', $mode = 'chuaxuat', $pl_f = 'all' ) {
		$mode = $mode ? $mode : 'chuaxuat';
		$pl_f = $pl_f ? $pl_f : 'all';
		$cp   = VHCP_Don::cp_rows();              // đọc 1 lần, dùng cho cả cấu hình đối tượng lẫn vòng lặp dưới
		$cfg  = VHCP_Cfg::get_config( $cp );

		$m_unit = array(); $m_pll = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_pll[ $x['ten'] ] = $x['phanLoaiLon']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }
		$m_no = array();
		foreach ( $cfg['nhom'] as $x ) { $m_no[ $x['ten'] ] = $x['tkNo']; }
		$m_co = array();
		foreach ( $cfg['phanloai'] as $x ) { $m_co[ $x['ten'] ] = $x['tkCo']; }
		$m_dt = array();
		foreach ( $cfg['doiTuong'] as $x ) { $m_dt[ mb_strtolower( (string) $x['ten'] ) ] = $x['ma']; }
		$m_no_mx = array();
		foreach ( (array) $cfg['tkNoMatrix'] as $x ) { $m_no_mx[ trim( (string) $x['nhom'] ) . '|' . trim( (string) $x['pll'] ) ] = $x['tkNo']; }

		$m_co_user = array(); $m_dt_user = array(); $role_by = array();
		foreach ( VHCP_Cfg::get_users() as $u ) {
			if ( $u['ten'] === '' ) { continue; }
			$k = mb_strtolower( trim( $u['ten'] ) );
			$m_co_user[ $k ] = $u['tkCo'];
			$m_dt_user[ $k ] = $u['maDt'];
			$role_by[ $k ]   = $u['vaiTro'];
		}

		// Gom đơn theo bộ phận + trạng thái xuất
		$by_don = array();
		foreach ( VHCP_Don::don_rows() as $r ) {
			$ky2 = VHCP_Util::fmt( $r['ky'] );
			if ( $ky && $ky !== 'all' && $ky2 !== $ky ) { continue; }
			$qt_cn  = ( (string) $r['nguoi_qt'] !== '' );
			$qt_ncc = ( (string) $r['nguoi_qt_ncc'] !== '' );
			$x_cn   = ( VHCP_Util::fmt( $r['ngay_xuat_cn'] ) !== '' );
			$x_ncc  = ( VHCP_Util::fmt( $r['ngay_xuat_ncc'] ) !== '' );
			if ( $pl_f === 'cn' )        { $take = $qt_cn  && ( $mode === 'daxuat' ? $x_cn  : ! $x_cn ); }
			elseif ( $pl_f === 'ncc' )   { $take = $qt_ncc && ( $mode === 'daxuat' ? $x_ncc : ! $x_ncc ); }
			else                         { $take = ( $mode === 'daxuat' ? ( $r['trang_thai'] === 'Đã xuất MISA' ) : ( $r['trang_thai'] === 'Đã quyết toán' ) ); }
			if ( ! $take ) { continue; }
			$ngay = VHCP_Util::fmt( $r['ngay_qt'] );
			if ( $ngay === '' ) { $ngay = VHCP_Util::fmt( $r['ngay_tao'] ); }
			$by_don[ (string) $r['ma_don'] ] = array(
				'ky'         => $ky2,
				'nguoiLap'   => (string) $r['nguoi_lap'],
				'nguoiDuyet' => (string) $r['nguoi_duyet'],
				'nguoiQT'    => (string) $r['nguoi_qt'],
				'nguoiQTNCC' => (string) $r['nguoi_qt_ncc'],
				'ngay'       => $ngay,
			);
		}

		$rows_by_nhom = array(); $nhom_order = array(); $warn = array(); $ndon = 0; $seen_don = array();
		foreach ( $cp as $r ) {
			$m = (string) $r['ma_don'];
			if ( ! isset( $by_don[ $m ] ) ) { continue; }
			$d       = $by_don[ $m ];
			$coso    = (string) $r['coso'];
			$pltt    = (string) $r['phan_loai_tt'];
			$dt      = (string) $r['doi_tuong'];
			$nhom    = (string) $r['nhom'];
			$nd      = (string) $r['noi_dung'];
			$eff_ncc = VHCP_Util::is_ncc( $pltt, $r['cn_xu_ly'] );
			if ( $pl_f === 'cn' && $eff_ncc ) { continue; }
			if ( $pl_f === 'ncc' && ! $eff_ncc ) { continue; }
			if ( ! isset( $seen_don[ $m ] ) ) { $seen_don[ $m ] = 1; $ndon++; }

			$tt     = VHCP_Util::num( $r['thanh_tien'] );
			$tm     = VHCP_Util::blank_or_num( $r['thuc_mua'] );
			$sotien = ( $tm === null ) ? $tt : $tm;
			if ( ! $sotien ) { continue; }

			$co_key    = $eff_ncc ? 'Nhà cung cấp' : $pltt;
			$pll       = isset( $m_pll[ $coso ] ) ? $m_pll[ $coso ] : '';
			$duyet_key = mb_strtolower( trim( (string) $d['nguoiDuyet'] ) );

			$tk_no = '';
			$mx_k  = trim( $nhom ) . '|' . trim( (string) $pll );
			if ( ! empty( $m_no_mx[ $mx_k ] ) )      { $tk_no = $m_no_mx[ $mx_k ]; }
			elseif ( ! empty( $m_no[ $nhom ] ) )     { $tk_no = $m_no[ $nhom ]; }
			$tk_co = '';
			if ( ! empty( $m_co_user[ $duyet_key ] ) ) { $tk_co = $m_co_user[ $duyet_key ]; }
			elseif ( ! empty( $m_co[ $co_key ] ) )     { $tk_co = $m_co[ $co_key ]; }
			$ma_dv = isset( $m_unit[ $coso ] ) ? $m_unit[ $coso ] : '';
			$ma_dt = '';
			if ( ! empty( $m_dt_user[ $duyet_key ] ) )               { $ma_dt = $m_dt_user[ $duyet_key ]; }
			elseif ( ! empty( $m_dt[ mb_strtolower( $dt ) ] ) )      { $ma_dt = $m_dt[ mb_strtolower( $dt ) ]; }

			if ( ! $tk_no ) { $warn[ 'Thiếu TK Nợ (nhóm × phân loại): ' . $nhom . ' × ' . ( $pll !== '' ? $pll : '?' ) ] = 1; }
			if ( ! $tk_co ) { $warn[ 'Thiếu TK Có cho người duyệt: ' . ( $d['nguoiDuyet'] !== '' ? $d['nguoiDuyet'] : '(trống)' ) ] = 1; }
			if ( ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở: ' . $coso ] = 1; }

			$ngay = VHCP_Util::fmt( $r['ngay'] );
			if ( $ngay === '' ) { $ngay = $d['ngay']; }
			$nhom_c   = self::clean_nhom( $nhom );
			$ten_misa = ! empty( $m_tm[ $coso ] ) ? $m_tm[ $coso ] : $coso;
			$ten1     = $d['nguoiDuyet'];
			$dg1      = VHCP_Util::j( array( $nhom_c, $pll, $d['ky'] ) ) . ( $ten1 !== '' ? '_' . $ten1 : '' );
			$dg2      = VHCP_Util::j( array( $nhom_c, $pll, $ten_misa ) ) . ( trim( $nd ) !== '' ? '_' . $nd : '' );

			$gk = $nhom_c !== '' ? $nhom_c : '(khác)';
			if ( ! isset( $rows_by_nhom[ $gk ] ) ) { $rows_by_nhom[ $gk ] = array(); $nhom_order[] = $gk; }
			$rows_by_nhom[ $gk ][] = array( $ngay, $ngay, '', $dg1, $dg2, $tk_no, $tk_co, $sotien, $ma_dt, $ma_dv );
		}

		$rows = array();
		foreach ( $nhom_order as $g ) { $rows = array_merge( $rows, $rows_by_nhom[ $g ] ); }

		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => $ndon, 'warn' => array_keys( $warn ), 'maDons' => array_keys( $seen_don ) );
	}

	/** exportMisaKyThuat(): dự án Kỹ thuật đã duyệt / đã đóng + Chi phí cơ sở chung. */
	public static function export_ky_thuat() {
		$cfg = VHCP_Cfg::cfg_static();   // chỉ cần danh mục cơ sở -> khỏi đọc bảng ChiPhi để gộp đối tượng
		$m_unit = array(); $m_pll = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_pll[ $x['ten'] ] = $x['phanLoaiLon']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }
		$user_dt = array();
		foreach ( VHCP_Cfg::get_users() as $u ) {
			if ( $u['ten'] === '' ) { continue; }
			$user_dt[ mb_strtolower( trim( $u['ten'] ) ) ] = $u['maDt'];
		}

		$pay_map = VHCP_DuAn::pay_map();
		$app_map = VHCP_DuAn::approve_date_map();

		$rows = array(); $warn = array(); $nda = 0;
		foreach ( VHCP_DuAn::all_with_lines() as $r ) {
			$st   = (string) $r['trang_thai'];
			$loai = (string) $r['loai'];
			if ( $st !== 'Đã duyệt' && $st !== 'Đã đóng' && $loai !== 'Chi phí cơ sở' ) { continue; }
			$ma_da  = (string) $r['ma_da'];
			$ten_da = (string) $r['ten'];
			$pay    = isset( $pay_map[ $ma_da ] ) ? $pay_map[ $ma_da ] : array();
			$ngay   = isset( $app_map[ $ma_da ] ) ? $app_map[ $ma_da ] : '';
			if ( $ngay === '' ) { $ngay = VHCP_Util::fmt( $r['ngay_tao'] ); }
			$ma_dv    = isset( $m_unit[ $ten_da ] ) ? $m_unit[ $ten_da ] : '';
			$pll      = isset( $m_pll[ $ten_da ] ) ? $m_pll[ $ten_da ] : '';
			$ten_misa = ! empty( $m_tm[ $ten_da ] ) ? $m_tm[ $ten_da ] : $ten_da;

			$child = array(); $parent_ht = array();
			foreach ( $r['lines'] as $x ) {
				$cap = trim( (string) $x['cap_cha'] );
				if ( $cap !== '' && $cap !== '(Phát sinh)' ) { $child[ $cap ] = ( isset( $child[ $cap ] ) ? $child[ $cap ] : 0 ) + VHCP_Util::num( $x['thuc_te'] ); }
				if ( $cap === '' ) { $parent_ht[ trim( (string) $x['noi_dung'] ) ] = trim( (string) $x['hinh_thuc'] ); }
			}

			$used = false;
			foreach ( $r['lines'] as $x ) {
				$nd0     = trim( (string) $x['noi_dung'] );
				$cap     = trim( (string) $x['cap_cha'] );
				$thuc_te = VHCP_Util::num( $x['thuc_te'] );
				if ( $nd0 === '' && ! $thuc_te ) { continue; }
				if ( $cap === '' && isset( $child[ $nd0 ] ) && $child[ $nd0 ] > 0 ) { continue; }
				if ( ! $thuc_te ) { continue; }

				$ht = ( $cap !== '' && $cap !== '(Phát sinh)' )
					? ( ! empty( $parent_ht[ $cap ] ) ? $parent_ht[ $cap ] : trim( (string) $x['hinh_thuc'] ) )
					: trim( (string) $x['hinh_thuc'] );
				$is_tt   = ( $ht === 'Trực tiếp' );
				$hang_muc = ( $cap === '' || $cap === '(Phát sinh)' ) ? $nd0 : $cap;
				$noi_dung = ( $cap === '' || $cap === '(Phát sinh)' ) ? '' : $nd0;
				$ghichu   = trim( (string) $x['note'] );

				$tk_no = '141';
				$tk_co = $is_tt ? '331' : '64125';
				$ma_dt = '';
				if ( ! $is_tt ) {
					$by_name = ! empty( $pay['tamUng']['tu']['by'] ) ? $pay['tamUng']['tu']['by'] : (string) $r['nguoi_tao'];
					$bk      = mb_strtolower( trim( (string) $by_name ) );
					$ma_dt   = isset( $user_dt[ $bk ] ) ? $user_dt[ $bk ] : '';
				}
				if ( ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở/dự án: ' . $ten_da . ' (thêm ở ⚙️ Cấu hình cơ sở)' ] = 1; }

				$dg1 = VHCP_Util::j( array( $ten_da, $loai, $pll ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
				$dg2 = VHCP_Util::j( array( $hang_muc, $ten_misa ) );
				$tail = VHCP_Util::j( array( $noi_dung, $ghichu ) );
				if ( $tail !== '' ) { $dg2 .= '_' . $tail; }

				$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, $tk_no, $tk_co, $thuc_te, $ma_dt, $ma_dv );
				$used   = true;
			}
			if ( $used ) { $nda++; }
		}
		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => $nda, 'warn' => array_keys( $warn ), 'maDons' => array() );
	}

	/** exportMisaMarketing(): mỗi khoản có thực chi = 1 dòng hạch toán. */
	public static function export_marketing() {
		$cfg = VHCP_Cfg::cfg_static();
		$m_unit = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }

		$don = array();
		foreach ( VHCP_MK::all_dons() as $r ) {
			$don[ (string) $r['ma'] ] = array(
				'coso'   => (string) $r['coso'],
				'ten'    => (string) $r['ten'],
				'ky'     => VHCP_Util::fmt( $r['ky'] ),
				'kenhCD' => (string) $r['kenh'],
				'ngay'   => VHCP_Util::fmt( $r['ngay_tao'] ),
			);
		}
		$rows = array(); $warn = array(); $seen = array();
		foreach ( VHCP_MK::all_lines() as $r ) {
			$tt = VHCP_Util::num( $r['thuc_te'] );
			if ( ! $tt ) { continue; }
			$d     = isset( $don[ (string) $r['ma_don'] ] ) ? $don[ (string) $r['ma_don'] ] : array( 'coso' => '', 'ten' => '', 'ky' => '', 'kenhCD' => '', 'ngay' => '' );
			$coso  = $d['coso'];
			$is_tt = ( trim( (string) $r['hinh_thuc'] ) === 'Trực tiếp' );
			$kenh  = (string) $r['kenh'] !== '' ? (string) $r['kenh'] : $d['kenhCD'];
			$nd    = (string) $r['noi_dung'];
			$gc    = (string) $r['note'];
			$ngay  = VHCP_Util::fmt( $r['ngay'] );
			if ( $ngay === '' ) { $ngay = $d['ngay']; }
			$ma_dv    = isset( $m_unit[ $coso ] ) ? $m_unit[ $coso ] : '';
			$ten_misa = ! empty( $m_tm[ $coso ] ) ? $m_tm[ $coso ] : $coso;
			if ( $coso !== '' && ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở: ' . $coso . ' (thêm ở ⚙️ Cấu hình cơ sở)' ] = 1; }
			$dg1 = VHCP_Util::j( array( 'MKT', $d['ten'], $coso, $kenh, $d['ky'] ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
			$dg2 = VHCP_Util::j( array( $nd, $ten_misa ) ) . ( trim( $gc ) !== '' ? '_' . $gc : '' );
			$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, '141', $is_tt ? '331' : '64125', $tt, '', $ma_dv );
			$seen[ (string) $r['ma_don'] ] = 1;
		}
		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => count( $seen ), 'warn' => array_keys( $warn ), 'maDons' => array() );
	}

	/** exportMisaBP(): Công tác / Setup. */
	public static function export_bp( $loai = 'all' ) {
		$cfg = VHCP_Cfg::cfg_static();
		$m_unit = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }

		$rows = array(); $warn = array(); $ndot = 0;
		foreach ( VHCP_BP::all_with_lines() as $r ) {
			if ( $loai && $loai !== 'all' && (string) $r['loai'] !== $loai ) { continue; }
			$lo       = (string) $r['loai'];
			$ten      = (string) $r['ten'];
			$nguoi    = (string) $r['nguoi'];
			$dia_diem = (string) $r['dia_diem'];
			$ky       = VHCP_Util::fmt( $r['ky'] );
			$ngay_dot = VHCP_Util::fmt( $r['ngay_tao'] );
			$used     = false;
			foreach ( $r['lines'] as $x ) {
				$nd = trim( (string) $x['noi_dung'] );
				$tt = VHCP_Util::num( $x['thuc_te'] );
				if ( $nd === '' && ! $tt ) { continue; }
				if ( ! $tt ) { continue; }
				$is_tt = ( trim( (string) $x['hinh_thuc'] ) === 'Trực tiếp' );
				$ngay  = VHCP_Util::fmt( $x['ngay'] );
				if ( $ngay === '' ) { $ngay = $ngay_dot; }
				$ghichu   = trim( (string) $x['note'] );
				$ma_dv    = isset( $m_unit[ $dia_diem ] ) ? $m_unit[ $dia_diem ] : '';
				$ten_misa = ! empty( $m_tm[ $dia_diem ] ) ? $m_tm[ $dia_diem ] : $dia_diem;
				if ( $dia_diem !== '' && ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho: ' . $dia_diem . ' (thêm ở ⚙️ Cấu hình cơ sở nếu là cơ sở)' ] = 1; }
				$dg1 = VHCP_Util::j( array( $lo, $ten, $nguoi, $ky ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
				$dg2 = VHCP_Util::j( array( $nd, $ten_misa ) ) . ( $ghichu !== '' ? '_' . $ghichu : '' );
				$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, '141', $is_tt ? '331' : '64125', $tt, '', $ma_dv );
				$used   = true;
			}
			if ( $used ) { $ndot++; }
		}
		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => $ndot, 'warn' => array_keys( $warn ), 'maDons' => array() );
	}

	/** markExported(): chốt "đã xuất" theo bộ phận; đủ cả 2 -> "Đã xuất MISA". */
	public static function mark_exported( $ma_dons, $pl_f = 'all' ) {
		global $wpdb;
		$pl_f = $pl_f ? $pl_f : 'all';
		$ma_dons = (array) $ma_dons;
		if ( ! count( $ma_dons ) ) { return VHCP_Util::err( 'Không có đơn để chốt' ); }
		$now = VHCP_Util::now_sql();
		$n   = 0;
		foreach ( $ma_dons as $m ) {
			$d = VHCP_Don::don_row( $m );
			if ( ! $d ) { continue; }
			$data  = array();
			$x_cn  = ( VHCP_Util::fmt( $d['ngay_xuat_cn'] ) !== '' );
			$x_ncc = ( VHCP_Util::fmt( $d['ngay_xuat_ncc'] ) !== '' );
			if ( ( $pl_f === 'cn' || $pl_f === 'all' ) && ! $x_cn )  { $data['ngay_xuat_cn'] = $now; $x_cn = true; }
			if ( ( $pl_f === 'ncc' || $pl_f === 'all' ) && ! $x_ncc ) { $data['ngay_xuat_ncc'] = $now; $x_ncc = true; }
			$loai = VHCP_Don::don_loai( $m );
			if ( ( ! $loai['cn'] || $x_cn ) && ( ! $loai['ncc'] || $x_ncc ) ) { $data['trang_thai'] = 'Đã xuất MISA'; }
			if ( count( $data ) ) { $wpdb->update( VHCP_DB::t( 'don' ), $data, array( 'ma_don' => (string) $m ) ); }
			$n++;
		}
		return VHCP_Util::ok( array( 'count' => $n ) );
	}
}
