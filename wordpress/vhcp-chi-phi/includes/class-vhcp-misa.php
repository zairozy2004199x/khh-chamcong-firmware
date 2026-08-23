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

	/**
	 * Chốt mã tài khoản cho 1 dòng của các mảng Kỹ thuật / Marketing / Công tác-Setup.
	 *
	 * Dòng ĐÃ gắn loại chi phí -> lấy mã theo danh mục (Nợ = tài khoản chi phí, Có = 141/331
	 * theo hình thức chi). Dòng CHƯA gắn -> giữ đúng cách hạch toán cũ (Nợ 141 · Có 331 nếu
	 * trực tiếp NCC / Có 64125 nếu tạm ứng NV) để số liệu cũ xuất ra không đổi.
	 *
	 * @return array [tk_no, tk_co, ma_dt, legacy(bool)]
	 */
	public static function tk_mang( $loai_cp, $is_tt, $line_tk_no = '', $line_tk_co = '', $line_ma_dt = '', $coso = '' ) {
		$loai_cp = trim( (string) $loai_cp );
		$line_tk_no = trim( (string) $line_tk_no );
		if ( $loai_cp === '' && $line_tk_no === '' ) {
			return array( 'tk_no' => '141', 'tk_co' => $is_tt ? '331' : '64125', 'ma_dt' => '', 'legacy' => true );
		}
		$tk = VHCP_Cfg::resolve_tk( $loai_cp, $is_tt ? 'Trực tiếp' : 'Tạm ứng', array(
			'tkCo' => trim( (string) $line_tk_co ),
			'maDt' => trim( (string) $line_ma_dt ),
		), $coso );
		// TK Nợ đi theo LUẬT LÚC XUẤT (xem VHCP_Cfg::tkno_xuat) — chung với đơn vận hành
		// và sổ chi phí, để 5 đường xuất không mỗi đường một kiểu.
		$tk['tk_no'] = VHCP_Cfg::tkno_xuat( $loai_cp, $coso, $line_tk_no );
		$tk['legacy'] = false;
		return $tk;
	}

	/**
	 * GOM CẢNH BÁO NGÀY VÔ LÝ.
	 *
	 * 169 dòng cùng dính một ngày hỏng thì in 169 dòng cảnh báo là ô vàng dài hơn cả bảng
	 * xuất, không đọc được gì. Gom theo GIÁ TRỊ ngày, đếm số dòng, kể tên vài đơn đầu.
	 *
	 * In kèm GIÁ TRỊ THÔ đang nằm trong máy: "22/08/4622" là bản đã định dạng, nhìn nó
	 * không biết trong cột ngày đang chứa cái gì. Ngày hỏng đồng loạt là lỗi lúc GHI chứ
	 * không phải người nhập gõ sai từng dòng, nên phải nhìn được giá trị gốc mới truy ra.
	 */
	public static function gom_ngay_xau( &$xau, $dmy, $raw, $ma = '' ) {
		if ( ! VHCP_Util::ngay_vo_ly( $dmy ) ) { return; }
		$k = $dmy . '|' . (string) $raw;
		if ( ! isset( $xau[ $k ] ) ) { $xau[ $k ] = array( 'dmy' => $dmy, 'raw' => (string) $raw, 'n' => 0, 'don' => array() ); }
		$xau[ $k ]['n']++;
		if ( trim( (string) $ma ) !== '' ) { $xau[ $k ]['don'][ (string) $ma ] = 1; }
	}

	/** Đổi bảng gom ở trên thành các câu cảnh báo. */
	public static function warn_ngay_xau( $xau ) {
		$out = array();
		foreach ( $xau as $x ) {
			$ds  = array_keys( $x['don'] );
			$cau = 'Ngày vô lý "' . $x['dmy'] . '" — ' . $x['n'] . ' dòng';
			if ( count( $ds ) ) {
				$cau .= ' ở ' . count( $ds ) . ' đơn (' . implode( ', ', array_slice( $ds, 0, 5 ) ) . ( count( $ds ) > 5 ? ', …' : '' ) . ')';
			}
			$cau .= '. Giá trị đang lưu trong máy: "' . $x['raw'] . '". Sửa ngày của dòng chi rồi xuất lại.';
			$out[] = $cau;
		}
		return $out;
	}

	/** _cleanNhom(): bỏ đuôi "- NCC" / "- Mua lẻ" khỏi tên nhóm. */
	private static function clean_nhom( $nhom ) {
		return VHCP_Cfg::bo_duoi_nhom( $nhom );
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
		$m_loai = array();   // danh mục LOẠI CHI PHÍ -> TK Nợ
		foreach ( (array) ( isset( $cfg['loaiChiPhi'] ) ? $cfg['loaiChiPhi'] : array() ) as $x ) {
			if ( trim( (string) $x['tkNo'] ) === '' ) { continue; }
			$m_loai[ mb_strtolower( trim( (string) $x['ten'] ) ) ] = (string) $x['tkNo'];
		}

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

		$rows_by_nhom = array(); $nhom_order = array(); $warn = array(); $ndon = 0; $seen_don = array(); $ngay_xau = array();
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

			// TK NỢ = TÀI KHOẢN CỦA LOẠI CHI PHÍ. Nợ trả lời "chi phí gì", nên nguồn thật là
			// danh mục loại chi phí (qua ma trận loại × mảng kinh doanh trước, vì cùng loại mà
			// khác mảng thì khác mã). Mã đã gắn trên dòng CHỈ là bản sao chụp lúc nhập, không
			// phải người nhập gõ tay -> lấy sau danh mục, và chỉ lấy khi không phải mã bên trả
			// tiền. Trước đây mã trên dòng được ưu tiên, nên dòng cũ mang 141 (tạm ứng) đè lên
			// tài khoản chi phí, xuất ra thành "Nợ 141 · Có 141".
			$mx_k     = trim( $nhom ) . '|' . trim( (string) $pll );
			$tk_dong  = trim( (string) ( isset( $r['tk_no'] ) ? $r['tk_no'] : '' ) );
			$tk_no    = VHCP_Cfg::tkno_xuat( $nhom, $coso, $tk_dong );
			$nhom_k   = mb_strtolower( VHCP_Cfg::bo_duoi_nhom( $nhom ) );
			// Hai bảng cấu hình CŨ, chỉ còn dùng cho dữ liệu chưa chuyển sang danh mục loại
			// chi phí. TK Nợ của CH_Nhom hầu hết là 141 nên phải lọc mã bên trả tiền ở đây.
			if ( $tk_no === '' && ! empty( $m_loai[ mb_strtolower( trim( $nhom ) ) ] ) ) { $tk_no = $m_loai[ mb_strtolower( trim( $nhom ) ) ]; }
			if ( $tk_no === '' && ! empty( $m_loai[ $nhom_k ] ) )                        { $tk_no = $m_loai[ $nhom_k ]; }
			if ( $tk_no === '' && ! empty( $m_no_mx[ $mx_k ] ) )                         { $tk_no = $m_no_mx[ $mx_k ]; }
			if ( $tk_no === '' && ! empty( $m_no[ $nhom ] ) && ! VHCP_Cfg::la_tk_ben_tra( $m_no[ $nhom ] ) ) { $tk_no = $m_no[ $nhom ]; }
			// TK Có = ai ứng tiền, không phải "chi phí gì": vẫn ưu tiên TK Có của người duyệt
			// tạm ứng như cũ, rồi tới mã gắn trên dòng, rồi tới TK Có của phân loại.
			$tk_co = '';
			if ( ! empty( $m_co_user[ $duyet_key ] ) ) { $tk_co = $m_co_user[ $duyet_key ]; }
			elseif ( trim( (string) ( isset( $r['tk_co'] ) ? $r['tk_co'] : '' ) ) !== '' ) { $tk_co = trim( (string) $r['tk_co'] ); }
			elseif ( ! empty( $m_co[ $co_key ] ) )     { $tk_co = $m_co[ $co_key ]; }
			$ma_dv = isset( $m_unit[ $coso ] ) ? $m_unit[ $coso ] : '';
			$ma_dt = '';
			if ( ! empty( $m_dt_user[ $duyet_key ] ) )               { $ma_dt = $m_dt_user[ $duyet_key ]; }
			elseif ( ! empty( $m_dt[ mb_strtolower( $dt ) ] ) )      { $ma_dt = $m_dt[ mb_strtolower( $dt ) ]; }

			if ( ! $tk_no ) { $warn[ 'Thiếu TK Nợ cho loại chi phí: ' . VHCP_Cfg::bo_duoi_nhom( $nhom ) . ( $pll !== '' ? ' (mảng ' . $pll . ')' : '' ) . ' — khai ở ⚙️ Cấu hình → Loại chi phí' ] = 1; }
			if ( ! $tk_co ) { $warn[ 'Thiếu TK Có cho người duyệt: ' . ( $d['nguoiDuyet'] !== '' ? $d['nguoiDuyet'] : '(trống)' ) ] = 1; }
			if ( ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở: ' . $coso ] = 1; }

			$ngay = VHCP_Util::fmt( $r['ngay'] );
			if ( $ngay === '' ) { $ngay = $d['ngay']; }
			VHCP_Misa::gom_ngay_xau( $ngay_xau, $ngay, $r['ngay'], $m );
			$nhom_c   = self::clean_nhom( $nhom );
			$ten_misa = ! empty( $m_tm[ $coso ] ) ? $m_tm[ $coso ] : $coso;
			$ten1     = $d['nguoiDuyet'];
			$dg1      = VHCP_Util::j( array( $nhom_c, $pll, $d['ky'] ) ) . ( $ten1 !== '' ? '_' . $ten1 : '' );
			$dg2      = VHCP_Util::j( array( $nhom_c, $pll, $ten_misa ) ) . ( trim( $nd ) !== '' ? '_' . $nd : '' );

			$gk = $nhom_c !== '' ? $nhom_c : '(khác)';
			if ( ! isset( $rows_by_nhom[ $gk ] ) ) { $rows_by_nhom[ $gk ] = array(); $nhom_order[] = $gk; }
			$rows_by_nhom[ $gk ][] = array( $ngay, $ngay, '', $dg1, $dg2, VHCP_Util::ma_so( $tk_no ), VHCP_Util::ma_so( $tk_co ), $sotien, VHCP_Util::ma_so( $ma_dt ), VHCP_Util::ma_so( $ma_dv ) );
		}

		$rows = array();
		foreach ( $nhom_order as $g ) { $rows = array_merge( $rows, $rows_by_nhom[ $g ] ); }

		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => $ndon,
			'warn' => array_merge( array_keys( $warn ), VHCP_Misa::warn_ngay_xau( $ngay_xau ) ), 'maDons' => array_keys( $seen_don ) );
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

		$rows = array(); $warn = array(); $nda = 0; $ngay_xau = array();
		foreach ( VHCP_DuAn::all_with_lines() as $r ) {
			$st   = (string) $r['trang_thai'];
			$loai = (string) $r['loai'];
			if ( $st !== 'Đã duyệt' && $st !== 'Đã đóng' && $loai !== 'Chi phí cơ sở' ) { continue; }
			$ma_da  = (string) $r['ma_da'];
			$ten_da = (string) $r['ten'];
			$pay    = isset( $pay_map[ $ma_da ] ) ? $pay_map[ $ma_da ] : array();
			$ngay   = isset( $app_map[ $ma_da ] ) ? $app_map[ $ma_da ] : '';
			if ( $ngay === '' ) { $ngay = VHCP_Util::fmt( $r['ngay_tao'] ); }
			VHCP_Misa::gom_ngay_xau( $ngay_xau, $ngay, $r['ngay_tao'], $ma_da );
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

				// Mã tài khoản theo LOẠI CHI PHÍ của dòng; dòng chưa gắn loại thì giữ đúng cách cũ.
				// (mục con thừa hưởng hình thức chi của hạng mục cha -> $is_tt đã tính ở trên)
				$tkm   = self::tk_mang( isset( $x['loai_cp'] ) ? $x['loai_cp'] : '', $is_tt, isset( $x['tk_no'] ) ? $x['tk_no'] : '', '', isset( $x['ma_dt'] ) ? $x['ma_dt'] : '', isset( $x['gian'] ) ? (string) $x['gian'] : '' );
				$tk_no = $tkm['tk_no'];
				$tk_co = $tkm['tk_co'];
				$ma_dt = $tkm['ma_dt'];
				if ( $tk_no === '' ) { $warn[ 'Thiếu TK Nợ cho loại chi phí: ' . trim( (string) $x['loai_cp'] ) . ' — khai ở ⚙️ Cấu hình → Loại chi phí' ] = 1; }
				if ( ! empty( $tkm['legacy'] ) && ! $is_tt ) {
					$by_name = ! empty( $pay['tamUng']['tu']['by'] ) ? $pay['tamUng']['tu']['by'] : (string) $r['nguoi_tao'];
					$bk      = mb_strtolower( trim( (string) $by_name ) );
					$ma_dt   = isset( $user_dt[ $bk ] ) ? $user_dt[ $bk ] : '';
				}
				if ( ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở/dự án: ' . $ten_da . ' (thêm ở ⚙️ Cấu hình cơ sở)' ] = 1; }

				$dg1 = VHCP_Util::j( array( $ten_da, $loai, $pll ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
				$dg2 = VHCP_Util::j( array( $hang_muc, $ten_misa ) );
				$tail = VHCP_Util::j( array( $noi_dung, $ghichu ) );
				if ( $tail !== '' ) { $dg2 .= '_' . $tail; }

				$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, VHCP_Util::ma_so( $tk_no ), VHCP_Util::ma_so( $tk_co ), $thuc_te, VHCP_Util::ma_so( $ma_dt ), VHCP_Util::ma_so( $ma_dv ) );
				$used   = true;
			}
			if ( $used ) { $nda++; }
		}
		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => $nda,
			'warn' => array_merge( array_keys( $warn ), VHCP_Misa::warn_ngay_xau( $ngay_xau ) ), 'maDons' => array() );
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
		$rows = array(); $warn = array(); $seen = array(); $ngay_xau = array();
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
			VHCP_Misa::gom_ngay_xau( $ngay_xau, $ngay, $r['ngay'], (string) $r['ma_don'] );
			$ma_dv    = isset( $m_unit[ $coso ] ) ? $m_unit[ $coso ] : '';
			$ten_misa = ! empty( $m_tm[ $coso ] ) ? $m_tm[ $coso ] : $coso;
			if ( $coso !== '' && ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở: ' . $coso . ' (thêm ở ⚙️ Cấu hình cơ sở)' ] = 1; }
			$tkm = self::tk_mang( $r['loai_cp'], $is_tt, $r['tk_no'], $r['tk_co'], $r['ma_dt'], $coso );
			if ( $tkm['tk_no'] === '' ) { $warn[ 'Thiếu TK Nợ cho loại chi phí: ' . trim( (string) $r['loai_cp'] ) . ' — khai ở ⚙️ Cấu hình → Loại chi phí' ] = 1; }
			$lc  = trim( (string) $r['loai_cp'] );
			$dg1 = VHCP_Util::j( array( 'MKT', $lc !== '' ? VHCP_Cfg::ten_misa_loai( $lc ) : '', $d['ten'], $coso, $kenh, $d['ky'] ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
			$dg2 = VHCP_Util::j( array( $nd, $ten_misa ) ) . ( trim( $gc ) !== '' ? '_' . $gc : '' );
			$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, VHCP_Util::ma_so( $tkm['tk_no'] ), VHCP_Util::ma_so( $tkm['tk_co'] ), $tt, VHCP_Util::ma_so( $tkm['ma_dt'] ), VHCP_Util::ma_so( $ma_dv ) );
			$seen[ (string) $r['ma_don'] ] = 1;
		}
		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => count( $seen ),
			'warn' => array_merge( array_keys( $warn ), VHCP_Misa::warn_ngay_xau( $ngay_xau ) ), 'maDons' => array() );
	}

	/** exportMisaBP(): Công tác / Setup. */
	public static function export_bp( $loai = 'all' ) {
		$cfg = VHCP_Cfg::cfg_static();
		$m_unit = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }

		$rows = array(); $warn = array(); $ndot = 0; $ngay_xau = array();
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
				VHCP_Misa::gom_ngay_xau( $ngay_xau, $ngay, $x['ngay'], (string) $r['ma'] );
				$ghichu   = trim( (string) $x['note'] );
				$ma_dv    = isset( $m_unit[ $dia_diem ] ) ? $m_unit[ $dia_diem ] : '';
				$ten_misa = ! empty( $m_tm[ $dia_diem ] ) ? $m_tm[ $dia_diem ] : $dia_diem;
				if ( $dia_diem !== '' && ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho: ' . $dia_diem . ' (thêm ở ⚙️ Cấu hình cơ sở nếu là cơ sở)' ] = 1; }
				$tkm = self::tk_mang( $x['loai_cp'], $is_tt, $x['tk_no'], $x['tk_co'], $x['ma_dt'], $dia_diem );
				if ( $tkm['tk_no'] === '' ) { $warn[ 'Thiếu TK Nợ cho loại chi phí: ' . trim( (string) $x['loai_cp'] ) . ' — khai ở ⚙️ Cấu hình → Loại chi phí' ] = 1; }
				$dg1 = VHCP_Util::j( array( $lo, $ten, $nguoi, $ky ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
				$dg2 = VHCP_Util::j( array( $nd, $ten_misa ) ) . ( $ghichu !== '' ? '_' . $ghichu : '' );
				$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, VHCP_Util::ma_so( $tkm['tk_no'] ), VHCP_Util::ma_so( $tkm['tk_co'] ), $tt, VHCP_Util::ma_so( $tkm['ma_dt'] ), VHCP_Util::ma_so( $ma_dv ) );
				$used   = true;
			}
			if ( $used ) { $ndot++; }
		}
		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => $ndot,
			'warn' => array_merge( array_keys( $warn ), VHCP_Misa::warn_ngay_xau( $ngay_xau ) ), 'maDons' => array() );
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
