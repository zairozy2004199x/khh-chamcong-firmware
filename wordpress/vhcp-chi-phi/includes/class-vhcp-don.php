<?php
/**
 * ĐƠN VẬN HÀNH — đơn tạm ứng theo tuần, hạng mục xin / phát sinh, đối chiếu thừa thiếu.
 *
 * Dịch nguyên logic từ Code.gs (sheet DonHang / TamUng / ChiPhi). Quy ước giữ nguyên:
 *   - Tạm ứng "1 cục" cho cả đơn = 'Tạm ứng duyệt' (nếu có) hoặc tổng hạng mục xin + dự phòng + bù trừ.
 *   - Thực chi mỗi dòng = Thực mua (nếu đã nhập) ngược lại = Thành tiền.
 *   - Dòng NCC hiệu lực = phân loại 'Nhà cung cấp' HOẶC dòng cá nhân bị bỏ tích "CN xử lý".
 *   - Trạng thái: Nháp → Chờ duyệt tạm ứng → Chờ cấp tạm ứng → Đã cấp tạm ứng → Chờ quyết toán → Đã quyết toán → Đã xuất MISA.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Don {

	// ---------------------------------------------------------------- đọc bảng

	/** Mọi dòng chi phí, theo đúng thứ tự nhập (như đọc sheet ChiPhi). */
	public static function cp_rows() {
		global $wpdb;
		$t = VHCP_DB::t( 'chiphi' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	public static function don_rows() {
		global $wpdb;
		$t = VHCP_DB::t( 'don' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	public static function tu_rows() {
		global $wpdb;
		$t = VHCP_DB::t( 'tamung' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY id ASC" );
	}

	public static function don_row( $ma_don ) {
		global $wpdb;
		$t = VHCP_DB::t( 'don' );
		return VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_don=%s", (string) $ma_don ) );
	}

	public static function line_row( $id ) {
		global $wpdb;
		$t = VHCP_DB::t( 'chiphi' );
		return VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%s", (string) $id ) );
	}

	private static function upd_don( $ma_don, $data ) {
		global $wpdb;
		return $wpdb->update( VHCP_DB::t( 'don' ), $data, array( 'ma_don' => (string) $ma_don ) );
	}

	private static function state( $ma_don ) {
		$d = self::don_row( $ma_don );
		return $d ? (string) $d['trang_thai'] : '';
	}

	/** _donInfo(): trạng thái + đơn có đang bị trả lại không. */
	private static function info( $ma_don ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return array( 'st' => '', 'returned' => false ); }
		return array( 'st' => (string) $d['trang_thai'], 'returned' => ( strpos( (string) $d['ghi_chu'], '[Trả lại]' ) !== false ) );
	}

	private static function mark_kt_sua( $ma_don, $actor ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return; }
		$g = (string) $d['ghi_chu'];
		if ( strpos( $g, '[KT sửa]' ) !== false ) { return; }
		self::upd_don( $ma_don, array( 'ghi_chu' => '[KT sửa]' . ( $actor ? ' ' . $actor : '' ) . ( $g !== '' ? ' | ' . $g : '' ) ) );
	}

	private static function clear_tra_marker( $ma_don ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return; }
		$g = (string) $d['ghi_chu'];
		if ( preg_match( '/\[Trả lại\]/u', $g ) ) {
			$g = trim( preg_replace( '/\[Trả lại\][^|]*\|?\s*/u', '', $g ) );
			self::upd_don( $ma_don, array( 'ghi_chu' => $g ) );
		}
	}

	// ---------------------------------------------------------------- khởi động

	/** getBootstrap(): đọc ChiPhi 1 lần rồi dùng chung cho cấu hình + đơn + gợi ý sản phẩm. */
	public static function get_bootstrap() {
		$cp  = self::cp_rows();
		$cfg = VHCP_Cfg::get_config( $cp );

		$coso = array();
		foreach ( $cfg['coso'] as $x ) { $coso[] = $x['ten']; }
		$nhom = array();
		foreach ( $cfg['nhom'] as $x ) { $nhom[] = array( 'ten' => $x['ten'], 'loai' => $x['loai'], 'boPhan' => isset( $x['boPhan'] ) ? $x['boPhan'] : '' ); }
		$pl = array();
		foreach ( $cfg['phanloai'] as $x ) { $pl[] = $x['ten']; }

		$loai = array();
		foreach ( (array) ( isset( $cfg['loaiChiPhi'] ) ? $cfg['loaiChiPhi'] : array() ) as $x ) {
			$loai[] = array( 'ten' => $x['ten'], 'tkNo' => $x['tkNo'], 'tkCo' => $x['tkCo'], 'boPhan' => $x['boPhan'] );
		}

		// Cơ sở -> mảng kinh doanh, và ma trận [loại][mảng] -> TK Nợ: để ô "Loại chi phí"
		// chỉ hiện những loại mà cơ sở đang chọn thật sự dùng (tránh chọn lộn mảng).
		$s_all   = VHCP_Cfg::cfg_static();
		$coso_ml = isset( $s_all['cosoPll'] ) ? $s_all['cosoPll'] : array();
		$mx      = isset( $s_all['tkNoMx'] ) ? $s_all['tkNoMx'] : array();

		// Tên tài khoản của các mã ĐANG dùng: ô nào khai 2 mã thì người nhập phân biệt bằng
		// tên ("64196 Chi phí khác Event" / "64197 Chi phí hoa hồng Event"), nên phải có tên.
		$can = array();
		foreach ( $mx as $per ) {
			foreach ( (array) $per as $ds ) {
				foreach ( (array) $ds as $m ) { $can[ (string) $m ] = 1; }
			}
		}
		foreach ( $loai as $x ) { if ( trim( (string) $x['tkNo'] ) !== '' ) { $can[ trim( (string) $x['tkNo'] ) ] = 1; } }
		$ten_tk = array();
		if ( count( $can ) ) {
			foreach ( VHCP_Cfg::tai_khoan() as $x ) {
				if ( isset( $can[ (string) $x['ma'] ] ) ) { $ten_tk[ (string) $x['ma'] ] = $x['ten']; }
			}
		}

		return array(
			'coso'       => $coso,
			'cosoPll'    => $coso_ml,
			'tkNoMx'     => $mx,
			'tenTk'      => $ten_tk,
			'nhom'       => $nhom,
			'loaiChiPhi' => $loai,
			'phanloai'   => $pl,
			'doiTuong'   => $cfg['doiTuong'],
			'qr'         => $cfg['qr'],
			'quyen'      => VHCP_Cfg::get_quyen(),
			'soDuDauKy'  => self::get_so_du_dau_ky(),
			'dons'       => self::list_dons( $cp ),
			'products'   => self::product_suggestions( $cp ),
		);
	}

	/** _productSuggestions(): mỗi tên hàng → giá/ĐVT/nhóm của lần nhập gần nhất. */
	public static function product_suggestions( $cp = null ) {
		if ( $cp === null ) { $cp = self::cp_rows(); }
		$map = array();
		foreach ( $cp as $r ) {
			$ten = trim( (string) $r['noi_dung'] );
			if ( $ten === '' ) { continue; }
			$k = mb_strtolower( $ten );
			$t = $r['tao_luc'] ? strtotime( $r['tao_luc'] ) : 0;
			if ( ! isset( $map[ $k ] ) || $t >= $map[ $k ]['_t'] ) {
				$map[ $k ] = array( 'ten' => $ten, 'gia' => VHCP_Util::num( $r['don_gia'] ), 'dvt' => (string) $r['dvt'], 'nhom' => (string) $r['nhom'], '_t' => $t );
			}
		}
		$out = array();
		foreach ( $map as $o ) { $out[] = array( 'ten' => $o['ten'], 'gia' => $o['gia'], 'dvt' => $o['dvt'], 'nhom' => $o['nhom'] ); }
		return $out;
	}

	/** listDons(): danh sách đơn + số liệu đối chiếu (mới nhất trước). */
	public static function list_dons( $cp = null ) {
		if ( $cp === null ) { $cp = self::cp_rows(); }
		$dons = self::don_rows();
		$tu   = self::tu_rows();

		$tu_sum = array(); $tu_has = array();
		foreach ( $tu as $r ) {
			$m = (string) $r['ma_don'];
			if ( $m === '' ) { continue; }
			$tu_sum[ $m ] = ( isset( $tu_sum[ $m ] ) ? $tu_sum[ $m ] : 0 ) + VHCP_Util::num( $r['so'] );
			$tu_has[ $m ] = true;
		}

		$xin = array(); $tt_cn = array(); $tt_ncc = array(); $coso_by = array();
		foreach ( $cp as $r ) {
			$m = (string) $r['ma_don'];
			if ( $m === '' ) { continue; }
			$cs = trim( (string) $r['coso'] );
			if ( $cs !== '' ) {
				if ( ! isset( $coso_by[ $m ] ) ) { $coso_by[ $m ] = array(); }
				$coso_by[ $m ][ $cs ] = ( isset( $coso_by[ $m ][ $cs ] ) ? $coso_by[ $m ][ $cs ] : 0 ) + 1;
			}
			$tt  = VHCP_Util::num( $r['thanh_tien'] );
			$tm  = VHCP_Util::blank_or_num( $r['thuc_mua'] );
			$eff = ( $tm === null ) ? $tt : $tm;
			if ( ! VHCP_Util::is_phat_sinh( $r['phat_sinh'] ) ) { $xin[ $m ] = ( isset( $xin[ $m ] ) ? $xin[ $m ] : 0 ) + $tt; }
			if ( VHCP_Util::is_ncc( $r['phan_loai_tt'], $r['cn_xu_ly'] ) ) { $tt_ncc[ $m ] = ( isset( $tt_ncc[ $m ] ) ? $tt_ncc[ $m ] : 0 ) + $eff; }
			else { $tt_cn[ $m ] = ( isset( $tt_cn[ $m ] ) ? $tt_cn[ $m ] : 0 ) + $eff; }
		}
		foreach ( $xin as $m => $v ) { if ( empty( $tu_has[ $m ] ) ) { $tu_sum[ $m ] = $v; } }

		$out = array();
		foreach ( $dons as $r ) {
			$m = (string) $r['ma_don'];
			if ( $m === '' ) { continue; }
			$du_phong = VHCP_Util::num( $r['du_phong'] );
			$bu_tru   = VHCP_Util::num( $r['bu_tru'] );
			$tu_d     = VHCP_Util::num( $r['tam_ung_duyet'] );
			$tu_tay   = ! empty( $tu_has[ $m ] ) ? ( isset( $tu_sum[ $m ] ) ? $tu_sum[ $m ] : 0 ) : ( ( isset( $xin[ $m ] ) ? $xin[ $m ] : 0 ) + $du_phong + $bu_tru );
			$ad_total = ( $tu_d > 0 ) ? $tu_d : $tu_tay;
			$has_tu   = ( $ad_total > 0 );
			$mua_cn   = isset( $tt_cn[ $m ] ) ? $tt_cn[ $m ] : 0;
			$tc_ncc   = isset( $tt_ncc[ $m ] ) ? $tt_ncc[ $m ] : 0;
			$tc       = $mua_cn + $tc_ncc;
			$tam_ung  = $has_tu ? $ad_total : $mua_cn;

			$mp = isset( $coso_by[ $m ] ) ? $coso_by[ $m ] : array();
			arsort( $mp );
			$coso = implode( ', ', array_keys( $mp ) );

			$out[] = array(
				'maDon'       => $m,
				'ky'          => VHCP_Util::fmt( $r['ky'] ),
				'nguoiLap'    => (string) $r['nguoi_lap'],
				'coso'        => $coso,
				'ngayTao'     => VHCP_Util::fmt( $r['ngay_tao'] ),
				'trangThai'   => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Nháp' ),
				'ghiChu'      => (string) $r['ghi_chu'],
				'nguoiDuyet'  => (string) $r['nguoi_duyet'],
				'ngayDuyet'   => VHCP_Util::fmt( $r['ngay_duyet'] ),
				'nguoiQT'     => (string) $r['nguoi_qt'],
				'ngayQT'      => VHCP_Util::fmt( $r['ngay_qt'] ),
				'chenhLechQT' => VHCP_Util::num( $r['chenh_lech_qt'] ),
				'xuLy'        => (string) $r['xu_ly'],
				'soThucMua'   => $mua_cn,
				'httt'        => (string) $r['hinh_thuc_tt'],
				'anhHoaDon'   => (string) $r['hoa_don_qt'],
				'nguoiQTNCC'  => (string) $r['nguoi_qt_ncc'],
				'ngayQTNCC'   => VHCP_Util::fmt( $r['ngay_qt_ncc'] ),
				'qtCN'        => ( (string) $r['nguoi_qt'] !== '' ),
				'qtNCC'       => ( (string) $r['nguoi_qt_ncc'] !== '' ),
				'xuatCN'      => VHCP_Util::fmt( $r['ngay_xuat_cn'] ),
				'xuatNCC'     => VHCP_Util::fmt( $r['ngay_xuat_ncc'] ),
				'nguoiCap'    => (string) $r['nguoi_cap'],
				'ngayCap'     => VHCP_Util::fmt( $r['ngay_cap'] ),
				'htCap'       => (string) $r['ht_cap'],
				'anhCap'      => (string) $r['anh_cap'],
				'tatToan'     => ( trim( (string) $r['tat_toan'] ) !== '' ),
				'nguoiTatToan'=> (string) $r['tat_toan'],
				'ngayTatToan' => VHCP_Util::fmt( $r['ngay_tat_toan'] ),
				'tamUng'      => $tam_ung,
				'thucChi'     => $tc,
				'thucChiCN'   => $mua_cn,
				'thucChiNCC'  => $tc_ncc,
				'chenhLech'   => $tam_ung - $mua_cn,
			);
		}
		return array_reverse( $out );
	}

	// ---------------------------------------------------------------- 1 đơn

	public static function create_don( $ky, $nguoi_lap ) {
		global $wpdb;
		$m = VHCP_Util::uid( 'D' );
		$wpdb->insert( VHCP_DB::t( 'don' ), array(
			'ma_don'     => $m,
			'ky'         => (string) $ky,
			'nguoi_lap'  => (string) $nguoi_lap,
			'ngay_tao'   => VHCP_Util::now_sql(),
			'trang_thai' => 'Nháp',
			'ghi_chu'    => '',
		) );
		return VHCP_Util::ok( array( 'maDon' => $m ) );
	}

	/**
	 * getDon(): đơn + tạm ứng theo cơ sở + dòng chi + đối chiếu CN/NCC.
	 *
	 * $with_products = false: bỏ phần gợi ý sản phẩm (cần đọc cả bảng ChiPhi) —
	 * dùng khi gọi hàng loạt trong nội bộ, vd duyệt quyết toán theo lô.
	 */
	public static function get_don( $ma_don, $with_products = true ) {
		$r = self::don_row( $ma_don );
		if ( ! $r ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }

		$don = array(
			'maDon'       => (string) $r['ma_don'],
			'ky'          => VHCP_Util::fmt( $r['ky'] ),
			'nguoiLap'    => (string) $r['nguoi_lap'],
			'ngayTao'     => VHCP_Util::fmt( $r['ngay_tao'] ),
			'trangThai'   => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Nháp' ),
			'ghiChu'      => (string) $r['ghi_chu'],
			'nguoiDuyet'  => (string) $r['nguoi_duyet'],
			'ngayDuyet'   => VHCP_Util::fmt( $r['ngay_duyet'] ),
			'nguoiQT'     => (string) $r['nguoi_qt'],
			'ngayQT'      => VHCP_Util::fmt( $r['ngay_qt'] ),
			'chenhLechQT' => VHCP_Util::num( $r['chenh_lech_qt'] ),
			'xuLy'        => (string) $r['xu_ly'],
			'soThucMua'   => VHCP_Util::out_num( $r['so_tien_thuc_mua'] ),
			'httt'        => (string) $r['hinh_thuc_tt'],
			'anhHoaDon'   => (string) $r['hoa_don_qt'],
			'nguoiQTNCC'  => (string) $r['nguoi_qt_ncc'],
			'ngayQTNCC'   => VHCP_Util::fmt( $r['ngay_qt_ncc'] ),
			'tamUngDuyet' => VHCP_Util::out_num( $r['tam_ung_duyet'] ),
			'nguoiCap'    => (string) $r['nguoi_cap'],
			'ngayCap'     => VHCP_Util::fmt( $r['ngay_cap'] ),
			'htCap'       => (string) $r['ht_cap'],
			'anhCap'      => (string) $r['anh_cap'],
			'duPhong'     => VHCP_Util::num( $r['du_phong'] ),
			'buTru'       => VHCP_Util::num( $r['bu_tru'] ),
		);

		global $wpdb;
		$tt = VHCP_DB::t( 'tamung' );
		$tam_ung = array(); $has_tu_rows = false;
		foreach ( VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $tt WHERE ma_don=%s ORDER BY id ASC", (string) $ma_don ) ) as $x ) {
			$tam_ung[ (string) $x['coso'] ] = VHCP_Util::num( $x['so'] );
			$has_tu_rows = true;
		}

		$tcp = VHCP_DB::t( 'chiphi' );
		if ( $with_products ) {
			// Gợi ý sản phẩm cần lịch sử toàn bảng -> đọc 1 lần rồi lọc trong PHP,
			// thay vì 1 lệnh lọc theo đơn + 1 lệnh đọc cả bảng như trước.
			$cp_all = self::cp_rows();
			$cp     = array();
			foreach ( $cp_all as $x ) { if ( (string) $x['ma_don'] === (string) $ma_don ) { $cp[] = $x; } }
		} else {
			$cp_all = null;
			$cp     = VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $tcp WHERE ma_don=%s ORDER BY stt ASC", (string) $ma_don ) );
		}
		$lines = array();
		foreach ( $cp as $x ) {
			$lines[] = array(
				'id'         => (string) $x['id'],
				'coso'       => (string) $x['coso'],
				'ngay'       => VHCP_Util::fmt( $x['ngay'] ),
				'phanLoaiTT' => (string) $x['phan_loai_tt'],
				'doiTuong'   => (string) $x['doi_tuong'],
				'nhom'       => (string) $x['nhom'],
				'noiDung'    => (string) $x['noi_dung'],
				'dvt'        => (string) $x['dvt'],
				'soLuong'    => VHCP_Util::num( $x['so_luong'] ),
				'donGia'     => VHCP_Util::num( $x['don_gia'] ),
				'thanhTien'  => VHCP_Util::num( $x['thanh_tien'] ),
				'ghiChu'     => (string) $x['ghi_chu'],
				'anh'        => (string) $x['anh'],
				'thueSuat'   => VHCP_Util::out_num( $x['thue_suat'] ),
				'tienThue'   => VHCP_Util::num( $x['tien_thue'] ),
				'thucMua'    => VHCP_Util::out_num( $x['thuc_mua'] ),
				'cnXuLy'     => VHCP_Util::cn_flag( $x['cn_xu_ly'] ),
				'phatSinh'   => VHCP_Util::is_phat_sinh( $x['phat_sinh'] ),
				'tkNo'       => (string) $x['tk_no'],
				'tkCo'       => (string) $x['tk_co'],
			);
		}

		// Chưa nhập tạm ứng tay -> tạm ứng xin = tổng hạng mục theo cơ sở
		// (ở "Nháp" gộp cả dòng phát sinh vì khi đó mọi dòng đều là hạng mục xin).
		if ( ! $has_tu_rows ) {
			foreach ( $lines as $l ) {
				if ( ! $l['phatSinh'] || $don['trangThai'] === 'Nháp' ) {
					$cs = $l['coso'];
					$tam_ung[ $cs ] = ( isset( $tam_ung[ $cs ] ) ? $tam_ung[ $cs ] : 0 ) + VHCP_Util::num( $l['thanhTien'] );
				}
			}
		}

		$tu_tay_sum = 0;
		foreach ( $tam_ung as $v ) { $tu_tay_sum += VHCP_Util::num( $v ); }
		if ( ! $has_tu_rows ) { $tu_tay_sum += VHCP_Util::num( $don['duPhong'] ) + VHCP_Util::num( $don['buTru'] ); }
		$tu_duyet = ( $don['tamUngDuyet'] !== '' && VHCP_Util::num( $don['tamUngDuyet'] ) > 0 ) ? VHCP_Util::num( $don['tamUngDuyet'] ) : 0;
		$ad_total = $tu_duyet > 0 ? $tu_duyet : $tu_tay_sum;
		$has_tu   = $ad_total > 0;

		$cn_by = array(); $ncc_by = array();
		foreach ( $lines as $l ) {
			$tt_  = VHCP_Util::num( $l['thanhTien'] );
			$tm   = ( $l['thucMua'] === '' || $l['thucMua'] === null ) ? null : VHCP_Util::num( $l['thucMua'] );
			$eff  = ( $tm === null ) ? $tt_ : $tm;
			if ( $l['phanLoaiTT'] === 'Nhà cung cấp' || $l['cnXuLy'] === false ) {
				$ncc_by[ $l['coso'] ] = ( isset( $ncc_by[ $l['coso'] ] ) ? $ncc_by[ $l['coso'] ] : 0 ) + $eff;
			} else {
				$cn_by[ $l['coso'] ] = ( isset( $cn_by[ $l['coso'] ] ) ? $cn_by[ $l['coso'] ] : 0 ) + $eff;
			}
		}
		ksort( $cn_by ); ksort( $ncc_by );
		$recon_cn = array(); $cn_tc = 0;
		foreach ( $cn_by as $cs => $v ) { $recon_cn[] = array( 'coso' => $cs, 'thucChi' => $v ); $cn_tc += $v; }
		$recon_ncc = array(); $ncc_tc = 0;
		foreach ( $ncc_by as $cs => $v ) { $recon_ncc[] = array( 'coso' => $cs, 'thucChi' => $v ); $ncc_tc += $v; }
		$cn_tu = $has_tu ? $ad_total : $cn_tc;

		return VHCP_Util::ok( array(
			'don'       => $don,
			'tamUng'    => $tam_ung,
			'lines'     => $lines,
			'tuMode'    => ( $has_tu ? 'new' : 'old' ),
			'reconCN'   => $recon_cn,
			'tongCN'    => array( 'tamUng' => $cn_tu, 'thucChi' => $cn_tc, 'chenhLech' => $cn_tu - $cn_tc ),
			'reconNCC'  => $recon_ncc,
			'tongNCC'   => array( 'thucChi' => $ncc_tc ),
			'products'  => $with_products ? self::product_suggestions( $cp_all ) : array(),
		) );
	}

	// ---------------------------------------------------------------- tạm ứng

	public static function set_tam_ung( $ma_don, $coso, $so ) {
		global $wpdb;
		if ( ! $coso ) { return VHCP_Util::err( 'Thiếu cơ sở' ); }
		$d = self::don_row( $ma_don );
		if ( $d && (string) $d['trang_thai'] !== 'Nháp' ) { return VHCP_Util::err( 'Tạm ứng đã khóa (chỉ sửa khi đơn ở "Nháp")' ); }
		$t  = VHCP_DB::t( 'tamung' );
		$so = VHCP_Util::num( $so );
		$wpdb->query( $wpdb->prepare( "INSERT INTO $t (ma_don,coso,so) VALUES (%s,%s,%f) ON DUPLICATE KEY UPDATE so=VALUES(so)", (string) $ma_don, (string) $coso, $so ) );
		return VHCP_Util::ok();
	}

	public static function set_du_phong( $ma_don, $so ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Nháp' ) { return VHCP_Util::err( 'Chỉ sửa dự phòng khi đơn ở "Nháp"' ); }
		self::upd_don( $ma_don, array( 'du_phong' => VHCP_Util::num( $so ) ) );
		return VHCP_Util::ok();
	}

	public static function set_tu_extra( $ma_don, $du_phong, $bu_tru ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Nháp' ) { return VHCP_Util::err( 'Chỉ sửa khi đơn ở "Nháp"' ); }
		self::upd_don( $ma_don, array( 'du_phong' => VHCP_Util::num( $du_phong ), 'bu_tru' => VHCP_Util::num( $bu_tru ) ) );
		return VHCP_Util::ok();
	}

	// ---------------------------------------------------------------- dòng chi phí

	/** _lineArr(): dựng bản ghi 1 dòng chi từ dữ liệu giao diện gửi lên. */
	private static function line_data( $id, $ma_don, $rec ) {
		$rec = (array) $rec;
		$get = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };

		$sl = VHCP_Util::num( $get( 'soLuong' ) );
		$dg = VHCP_Util::num( $get( 'donGia' ) );
		$tt = $sl * $dg;
		$tt_in = $get( 'thanhTien' );
		if ( $tt_in !== null && $tt_in !== '' && is_numeric( str_replace( array( ',', ' ' ), '', (string) $tt_in ) ) ) {
			$tt = VHCP_Util::num( $tt_in );
		}
		$ts    = VHCP_Util::blank_or_num( $get( 'thueSuat' ) );
		$tthue = ( $ts === null ) ? null : round( $tt * $ts / 100 );
		$tm    = VHCP_Util::blank_or_num( $get( 'thucMua' ) );
		$cnv   = $get( 'cnXuLy' );
		$cn    = ( $cnv === 0 || $cnv === false || $cnv === '0' ) ? 0 : 1;
		$ps    = VHCP_Util::is_phat_sinh( $get( 'phatSinh' ) ) ? 1 : 0;
		$ngay  = VHCP_Util::parse_date( $get( 'ngay' ) );
		if ( ! $ngay ) { $ngay = VHCP_Util::today_sql(); }   // không nhập ngày -> lấy ngày nhập

		// GẮN MÃ TÀI KHOẢN NGAY LÚC NHẬP: TK Nợ lấy theo LOẠI CHI PHÍ (danh mục), TK Có theo
		// phân loại thanh toán. Nhờ vậy dò lại một dòng chỉ cần đọc cột mã, không phải chạy
		// lại hàm dò ma trận. (Xuất MISA vẫn ưu tiên TK Có của người duyệt tạm ứng như cũ.)
		$tk = self::tk_of_line( $get( 'nhom' ), $get( 'phanLoaiTT' ) );

		return array(
			'id'           => (string) $id,
			'ma_don'       => (string) $ma_don,
			'coso'         => VHCP_Util::st( $get( 'coso' ) ),
			'ngay'         => $ngay,
			'phan_loai_tt' => VHCP_Util::st( $get( 'phanLoaiTT' ) ),
			'doi_tuong'    => VHCP_Util::st( $get( 'doiTuong' ) ),
			'nhom'         => VHCP_Util::st( $get( 'nhom' ) ),
			'noi_dung'     => VHCP_Util::st( $get( 'noiDung' ) ),
			'dvt'          => VHCP_Util::st( $get( 'dvt' ) ),
			'so_luong'     => VHCP_Util::blank_or_num( $get( 'soLuong' ) ),
			'don_gia'      => VHCP_Util::blank_or_num( $get( 'donGia' ) ),
			'thanh_tien'   => $tt,
			'ghi_chu'      => VHCP_Util::st( $get( 'ghiChu' ) ),
			'anh'          => VHCP_Util::st( $get( 'anh' ) ),
			'tao_luc'      => VHCP_Util::now_sql(),
			'thue_suat'    => $ts,
			'tien_thue'    => $tthue,
			'thuc_mua'     => $tm,
			'cn_xu_ly'     => $cn,
			'phat_sinh'    => $ps,
			'tk_no'        => $tk['tk_no'],
			'tk_co'        => $tk['tk_co'],
		);
	}

	/** Mã tài khoản của 1 dòng chi: TK Nợ theo loại chi phí, TK Có theo phân loại thanh toán. */
	public static function tk_of_line( $nhom, $phan_loai_tt ) {
		$cat   = VHCP_Cfg::loai_tk( $nhom );
		$tk_no = $cat['tkNo'];
		$tk_co = $cat['tkCo'];
		if ( $tk_co === '' ) {
			$pl  = ( trim( (string) $phan_loai_tt ) === 'Nhà cung cấp' ) ? 'Nhà cung cấp' : 'Thanh toán cá nhân';
			$cfg = VHCP_Cfg::cfg_static();
			foreach ( (array) $cfg['phanloai'] as $x ) {
				if ( trim( (string) $x['ten'] ) === $pl ) { $tk_co = (string) $x['tkCo']; break; }
			}
		}
		return array( 'tk_no' => $tk_no, 'tk_co' => $tk_co );
	}

	/**
	 * Gán mã tài khoản cho các dòng chi CŨ (nhập trước khi có danh mục loại chi phí).
	 * $all = true thì áp lại cho mọi dòng; mặc định chỉ điền chỗ còn trống.
	 */
	public static function gan_ma_tai_khoan( $all = false ) {
		global $wpdb;
		$t = VHCP_DB::t( 'chiphi' );
		$n = 0; $thieu = array();
		foreach ( self::cp_rows() as $r ) {
			$thieu_ma = ( trim( (string) $r['tk_no'] ) === '' || trim( (string) $r['tk_co'] ) === '' );
			if ( ! $all && ! $thieu_ma ) { continue; }
			$tk = self::tk_of_line( $r['nhom'], $r['phan_loai_tt'] );
			if ( $tk['tk_no'] === '' && trim( (string) $r['nhom'] ) !== '' ) { $thieu[ (string) $r['nhom'] ] = 1; }
			if ( $tk['tk_no'] === (string) $r['tk_no'] && $tk['tk_co'] === (string) $r['tk_co'] ) { continue; }
			$wpdb->update( $t, array( 'tk_no' => $tk['tk_no'], 'tk_co' => $tk['tk_co'] ), array( 'id' => (string) $r['id'] ) );
			$n++;
		}
		return VHCP_Util::ok( array( 'updated' => $n, 'thieuMa' => array_keys( $thieu ) ) );
	}

	public static function add_line( $ma_don, $rec ) {
		global $wpdb;
		$rec = (array) $rec;
		$st  = self::state( $ma_don );
		if ( $st === 'Nháp' ) { $ps = 0; }
		elseif ( $st === 'Đã cấp tạm ứng' ) { $ps = 1; }
		else { return VHCP_Util::err( 'Chỉ thêm hạng mục khi đơn "Nháp" (hạng mục xin) hoặc "Đã cấp tạm ứng" (phát sinh)' ); }
		$rec['phatSinh'] = $ps;
		$id   = VHCP_Util::uid( 'L' );
		$data = self::line_data( $id, $ma_don, $rec );
		$wpdb->insert( VHCP_DB::t( 'chiphi' ), $data );
		return VHCP_Util::ok( array( 'id' => $id, 'phatSinh' => $ps ) );
	}

	public static function update_line( $id, $rec ) {
		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$ma_don = (string) $cur['ma_don'];
		$ps     = VHCP_Util::is_phat_sinh( $cur['phat_sinh'] );
		$info   = self::info( $ma_don );
		$st     = $info['st'];
		$xin_edit = ( $st === 'Nháp' || $st === 'Đã cấp tạm ứng' );
		if ( ! $ps && ! $xin_edit ) { return VHCP_Util::err( 'Hạng mục đã xin chỉ sửa khi "Nháp" hoặc "Đã cấp tạm ứng"' ); }
		if ( $ps && $st !== 'Đã cấp tạm ứng' && $st !== 'Nháp' ) { return VHCP_Util::err( 'Dòng phát sinh chỉ sửa khi đơn "Nháp" hoặc "Đã cấp tạm ứng"' ); }

		$rec = (array) $rec;
		if ( ! array_key_exists( 'thucMua', $rec ) ) { $rec['thucMua'] = VHCP_Util::out_num( $cur['thuc_mua'] ); }
		if ( ! array_key_exists( 'cnXuLy', $rec ) )  { $rec['cnXuLy']  = (int) $cur['cn_xu_ly']; }
		$rec['phatSinh'] = $ps ? 1 : 0;

		$data = self::line_data( $id, $ma_don, $rec );
		$data['tao_luc'] = $cur['tao_luc'] ? $cur['tao_luc'] : VHCP_Util::now_sql();
		// Danh mục chưa khai mã -> giữ mã cũ của dòng, không ghi rỗng lên.
		if ( $data['tk_no'] === '' && trim( (string) $cur['tk_no'] ) !== '' ) { $data['tk_no'] = $cur['tk_no']; }
		if ( $data['tk_co'] === '' && trim( (string) $cur['tk_co'] ) !== '' ) { $data['tk_co'] = $cur['tk_co']; }
		unset( $data['id'] );
		$wpdb->update( VHCP_DB::t( 'chiphi' ), $data, array( 'id' => (string) $id ) );
		return VHCP_Util::ok();
	}

	public static function set_line_thuc_mua( $id, $val, $actor = '' ) {
		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$ma_don = (string) $cur['ma_don'];
		$st     = self::state( $ma_don );
		if ( $st !== 'Đã cấp tạm ứng' && $st !== 'Chờ quyết toán' ) {
			return VHCP_Util::err( 'Chỉ nhập Thực chi khi "Đã cấp tạm ứng" (hoặc kế toán sửa khi "Chờ quyết toán")' );
		}
		$v = VHCP_Util::blank_or_num( $val );
		$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'thuc_mua' => $v ), array( 'id' => (string) $id ) );
		$kt_sua = false;
		if ( $st === 'Chờ quyết toán' ) { self::mark_kt_sua( $ma_don, $actor ); $kt_sua = true; }
		return VHCP_Util::ok( array( 'ktSua' => $kt_sua ) );
	}

	public static function set_line_cn( $id, $on ) {
		global $wpdb;
		if ( ! self::line_row( $id ) ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'cn_xu_ly' => $on ? 1 : 0 ), array( 'id' => (string) $id ) );
		return VHCP_Util::ok();
	}

	public static function set_line_anh( $id, $url ) {
		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$st = self::state( (string) $cur['ma_don'] );
		if ( ! in_array( $st, array( 'Nháp', 'Đã cấp tạm ứng', 'Chờ quyết toán' ), true ) ) {
			return VHCP_Util::err( 'Chỉ đính hóa đơn khi đơn đang nhập / đã cấp tạm ứng / chờ quyết toán' );
		}
		$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'anh' => (string) $url ), array( 'id' => (string) $id ) );
		return VHCP_Util::ok();
	}

	public static function delete_line( $id ) {
		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$ps   = VHCP_Util::is_phat_sinh( $cur['phat_sinh'] );
		$info = self::info( (string) $cur['ma_don'] );
		$st   = $info['st'];
		$xin_edit = ( $st === 'Nháp' ) || ( $st === 'Đã cấp tạm ứng' && $info['returned'] );
		if ( ! $ps && ! $xin_edit ) { return VHCP_Util::err( 'Hạng mục đã xin không được xóa (để Thực chi = 0 nếu không mua; đơn bị trả lại mới mở khóa)' ); }
		if ( $ps && $st !== 'Đã cấp tạm ứng' && $st !== 'Nháp' ) { return VHCP_Util::err( 'Dòng phát sinh chỉ xóa khi đơn "Nháp" hoặc "Đã cấp tạm ứng"' ); }
		$wpdb->delete( VHCP_DB::t( 'chiphi' ), array( 'id' => (string) $id ) );
		return VHCP_Util::ok();
	}

	/** duplicateLine(): tách 1 dòng sang cơ sở khác (đơn 1 phiếu nhiều cơ sở). */
	public static function duplicate_line( $id, $coso, $actor = '' ) {
		global $wpdb;
		$v = self::line_row( $id );
		if ( ! $v ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$ma_don = (string) $v['ma_don'];
		$st     = self::state( $ma_don );
		if ( ! in_array( $st, array( 'Nháp', 'Đã cấp tạm ứng', 'Chờ quyết toán' ), true ) ) {
			return VHCP_Util::err( 'Chỉ tách dòng khi đơn ở "Nháp" / "Đã cấp tạm ứng" / "Chờ quyết toán"' );
		}
		$rec = array(
			'coso'       => ( $coso ? $coso : $v['coso'] ),
			'ngay'       => $v['ngay'],
			'phanLoaiTT' => $v['phan_loai_tt'],
			'doiTuong'   => $v['doi_tuong'],
			'nhom'       => $v['nhom'],
			'noiDung'    => $v['noi_dung'],
			'dvt'        => $v['dvt'],
			'soLuong'    => VHCP_Util::out_num( $v['so_luong'] ),
			'donGia'     => VHCP_Util::out_num( $v['don_gia'] ),
			'thanhTien'  => $v['thanh_tien'],
			'ghiChu'     => $v['ghi_chu'],
			'anh'        => $v['anh'],
			'thueSuat'   => VHCP_Util::out_num( $v['thue_suat'] ),
			'thucMua'    => VHCP_Util::out_num( $v['thuc_mua'] ),
			'cnXuLy'     => (int) $v['cn_xu_ly'],
			'phatSinh'   => (int) $v['phat_sinh'],
		);
		$nid = VHCP_Util::uid( 'L' );
		$wpdb->insert( VHCP_DB::t( 'chiphi' ), self::line_data( $nid, $ma_don, $rec ) );
		if ( $st === 'Chờ quyết toán' ) { self::mark_kt_sua( $ma_don, $actor ); }
		return VHCP_Util::ok( array( 'id' => $nid ) );
	}

	// ---------------------------------------------------------------- NV gửi đơn qua các bước

	public static function gui_duyet_tam_ung( $ma_don ) {
		global $wpdb;
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Nháp' ) { return VHCP_Util::err( 'Chỉ gửi duyệt khi đơn ở "Nháp"' ); }
		$t = VHCP_DB::t( 'chiphi' );
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE ma_don=%s", (string) $ma_don ) );
		// Ở "Nháp" mọi dòng là hạng mục XIN: gộp cả dòng phát sinh (nếu có do đơn bị trả về).
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET phat_sinh=0 WHERE ma_don=%s AND phat_sinh=1", (string) $ma_don ) );
		$dp = VHCP_Util::num( $d['du_phong'] );
		if ( ! $n && ! ( $dp > 0 ) ) { return VHCP_Util::err( 'Chưa nhập hạng mục nào và cũng chưa nhập tạm ứng dự phòng' ); }
		self::clear_tra_marker( $ma_don );
		self::upd_don( $ma_don, array( 'trang_thai' => 'Chờ duyệt tạm ứng' ) );
		return VHCP_Util::ok();
	}

	public static function gui_quyet_toan( $ma_don ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Đã cấp tạm ứng' ) { return VHCP_Util::err( 'Chỉ gửi khi đơn "Đã cấp tạm ứng"' ); }
		self::clear_tra_marker( $ma_don );
		self::upd_don( $ma_don, array( 'trang_thai' => 'Chờ quyết toán' ) );
		return VHCP_Util::ok();
	}

	public static function save_quyet_toan( $ma_don, $obj ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] === 'Đã quyết toán' ) { return VHCP_Util::err( 'Đơn đã quyết toán — không sửa' ); }
		$obj = (array) $obj;
		self::upd_don( $ma_don, array(
			'so_tien_thuc_mua' => VHCP_Util::blank_or_num( isset( $obj['soThucMua'] ) ? $obj['soThucMua'] : '' ),
			'hinh_thuc_tt'     => isset( $obj['httt'] ) ? (string) $obj['httt'] : '',
			'hoa_don_qt'       => isset( $obj['anhHoaDon'] ) ? (string) $obj['anhHoaDon'] : '',
		) );
		return VHCP_Util::ok();
	}

	// ---------------------------------------------------------------- kế toán / quản lý

	public static function duyet_tam_ung( $ma_don, $nguoi, $so_tam_ung = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Chờ duyệt tạm ứng' ) { return VHCP_Util::err( 'Đơn không ở "Chờ duyệt tạm ứng"' ); }
		self::upd_don( $ma_don, array(
			'trang_thai'    => 'Chờ cấp tạm ứng',
			'nguoi_duyet'   => (string) $nguoi,
			'ngay_duyet'    => VHCP_Util::now_sql(),
			'tam_ung_duyet' => VHCP_Util::blank_or_num( $so_tam_ung ),
		) );
		return VHCP_Util::ok();
	}

	public static function cap_tam_ung( $ma_don, $nguoi, $ht_cap = 'Tiền mặt', $anh_cap = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Chờ cấp tạm ứng' ) { return VHCP_Util::err( 'Đơn chưa được duyệt tạm ứng' ); }
		$ht_cap = $ht_cap ? $ht_cap : 'Tiền mặt';
		if ( $ht_cap === 'Chuyển khoản' && ! $anh_cap ) { return VHCP_Util::err( 'Chuyển khoản phải đính ảnh chứng từ' ); }
		self::upd_don( $ma_don, array(
			'trang_thai' => 'Đã cấp tạm ứng',
			'nguoi_cap'  => (string) $nguoi,
			'ngay_cap'   => VHCP_Util::now_sql(),
			'ht_cap'     => (string) $ht_cap,
			'anh_cap'    => (string) $anh_cap,
		) );
		return VHCP_Util::ok();
	}

	/** _donLoai(): đơn có dòng cá nhân / dòng NCC hay không. */
	public static function don_loai( $ma_don ) {
		global $wpdb;
		$t   = VHCP_DB::t( 'chiphi' );
		$cn  = false; $ncc = false;
		foreach ( VHCP_DB::rows( $wpdb->prepare( "SELECT phan_loai_tt, cn_xu_ly FROM $t WHERE ma_don=%s", (string) $ma_don ) ) as $r ) {
			if ( VHCP_Util::is_ncc( $r['phan_loai_tt'], $r['cn_xu_ly'] ) ) { $ncc = true; } else { $cn = true; }
		}
		return array( 'cn' => $cn, 'ncc' => $ncc );
	}

	public static function xac_nhan_quyet_toan_cn( $ma_don, $nguoi, $xu_ly, $chenh_lech ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Chờ quyết toán' ) { return VHCP_Util::err( 'Đơn không ở "Chờ quyết toán"' ); }
		self::upd_don( $ma_don, array(
			'nguoi_qt'      => (string) $nguoi,
			'ngay_qt'       => VHCP_Util::now_sql(),
			'chenh_lech_qt' => VHCP_Util::num( $chenh_lech ),
			'xu_ly'         => (string) $xu_ly,
			'trang_thai'    => 'Đã quyết toán',
		) );
		return VHCP_Util::ok();
	}

	public static function xac_nhan_quyet_toan_ncc( $ma_don, $nguoi ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$st = (string) $d['trang_thai'];
		if ( $st === 'Nháp' || $st === 'Đã xuất MISA' ) { return VHCP_Util::err( 'Đơn chưa gửi hoặc đã xuất' ); }
		$data = array( 'nguoi_qt_ncc' => (string) $nguoi, 'ngay_qt_ncc' => VHCP_Util::now_sql() );
		$loai = self::don_loai( $ma_don );
		if ( ! $loai['cn'] ) { $data['trang_thai'] = 'Đã quyết toán'; }
		self::upd_don( $ma_don, $data );
		return VHCP_Util::ok();
	}

	public static function tra_lai_don( $ma_don, $ly_do = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$st     = (string) $d['trang_thai'];
		$target = ( $st === 'Chờ quyết toán' || $st === 'Chờ quản lý gom' ) ? 'Đã cấp tạm ứng' : 'Nháp';
		$data   = array( 'trang_thai' => $target );
		if ( $ly_do ) {
			$old = (string) $d['ghi_chu'];
			$data['ghi_chu'] = '[Trả lại] ' . $ly_do . ( $old !== '' ? ' | ' . $old : '' );
		}
		self::upd_don( $ma_don, $data );
		return VHCP_Util::ok( array( 'target' => $target ) );
	}

	public static function delete_don( $ma_don ) {
		global $wpdb;
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$st = (string) $d['trang_thai'];
		if ( ! in_array( $st, array( 'Nháp', 'Chờ duyệt tạm ứng', 'Chờ cấp tạm ứng' ), true ) ) {
			return VHCP_Util::err( 'Chỉ xóa được đơn CHƯA cấp tạm ứng. Đơn đã cấp tiền: dùng "🚫 Không dùng" hoặc Trả lại.' );
		}
		return self::purge_don( $ma_don );
	}

	/** deleteDonAdmin(): xóa vĩnh viễn, không xét trạng thái. */
	public static function delete_don_admin( $ma_don ) {
		if ( ! self::don_row( $ma_don ) ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		return self::purge_don( $ma_don );
	}

	private static function purge_don( $ma_don ) {
		global $wpdb;
		$wpdb->delete( VHCP_DB::t( 'chiphi' ), array( 'ma_don' => (string) $ma_don ) );
		$wpdb->delete( VHCP_DB::t( 'tamung' ), array( 'ma_don' => (string) $ma_don ) );
		$wpdb->delete( VHCP_DB::t( 'don' ), array( 'ma_don' => (string) $ma_don ) );
		return VHCP_Util::ok();
	}

	public static function set_tat_toan_tuan( $ma_don, $on, $actor = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		self::upd_don( $ma_don, array(
			'tat_toan'      => $on ? ( $actor ? $actor : '✓' ) : '',
			'ngay_tat_toan' => $on ? VHCP_Util::now_sql() : null,
		) );
		return VHCP_Util::ok( array( 'tatToan' => (bool) $on ) );
	}

	// ---------------------------------------------------------------- xử lý theo lô

	public static function duyet_tam_ung_nhieu( $ma_dons, $nguoi ) {
		$ok = 0; $errs = array();
		foreach ( (array) $ma_dons as $m ) {
			$r = self::duyet_tam_ung( $m, $nguoi, '' );
			if ( ! empty( $r['success'] ) ) { $ok++; } else { $errs[] = $m . ': ' . ( isset( $r['error'] ) ? $r['error'] : '?' ); }
		}
		return array( 'success' => count( $errs ) === 0, 'approved' => $ok, 'errors' => $errs );
	}

	public static function cap_tam_ung_nhieu( $ma_dons, $nguoi, $ht_cap = 'Tiền mặt', $anh_cap = '' ) {
		$ht_cap = $ht_cap ? $ht_cap : 'Tiền mặt';
		if ( $ht_cap === 'Chuyển khoản' && ! $anh_cap ) { return VHCP_Util::err( 'Chuyển khoản phải đính ảnh chứng từ' ); }
		$ok = 0; $errs = array();
		foreach ( (array) $ma_dons as $m ) {
			$r = self::cap_tam_ung( $m, $nguoi, $ht_cap, $anh_cap );
			if ( ! empty( $r['success'] ) ) { $ok++; } else { $errs[] = $m . ': ' . ( isset( $r['error'] ) ? $r['error'] : '?' ); }
		}
		return array( 'success' => count( $errs ) === 0, 'capped' => $ok, 'errors' => $errs );
	}

	public static function tra_lai_don_nhieu( $ma_dons, $ly_do = '' ) {
		$ok = 0; $errs = array();
		foreach ( (array) $ma_dons as $m ) {
			$r = self::tra_lai_don( $m, $ly_do );
			if ( ! empty( $r['success'] ) ) { $ok++; } else { $errs[] = (string) $m; }
		}
		return array( 'success' => count( $errs ) === 0, 'returned' => $ok, 'errors' => $errs );
	}

	public static function khong_dung_tam_ung( $ma_don ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Đã cấp tạm ứng' ) { return VHCP_Util::err( 'Chỉ đánh dấu khi đơn "Đã cấp tạm ứng"' ); }
		$old  = (string) $d['ghi_chu'];
		$data = array( 'trang_thai' => 'Chờ quyết toán' );
		if ( strpos( $old, '[Không dùng]' ) === false ) { $data['ghi_chu'] = '[Không dùng] ' . $old; }
		self::upd_don( $ma_don, $data );
		return VHCP_Util::ok();
	}

	public static function day_cho_ke_toan( $ma_dons, $nguoi = '' ) {
		$ok = 0; $errs = array();
		foreach ( (array) $ma_dons as $m ) {
			$d = self::don_row( $m );
			if ( ! $d ) { $errs[] = $m . ': không tìm thấy'; continue; }
			if ( (string) $d['trang_thai'] !== 'Chờ quản lý gom' ) { $errs[] = $m . ': không ở "Chờ quản lý gom"'; continue; }
			self::upd_don( $m, array( 'trang_thai' => 'Chờ quyết toán' ) );
			$ok++;
		}
		return array( 'success' => count( $errs ) === 0, 'pushed' => $ok, 'errors' => $errs );
	}

	public static function xac_nhan_qt_cn_nhieu( $ma_dons, $nguoi ) {
		$ok = 0; $errs = array();
		// Chênh lệch của mọi đơn tính từ 3 lệnh DB dùng chung (listDons), thay vì
		// gọi getDon cho từng đơn — duyệt 50 đơn trước đây là 50 lượt đọc cả bảng.
		$cl_by = array();
		foreach ( self::list_dons() as $x ) { $cl_by[ $x['maDon'] ] = $x['chenhLech']; }
		foreach ( (array) $ma_dons as $m ) {
			if ( ! array_key_exists( (string) $m, $cl_by ) ) { $errs[] = $m . ': Không tìm thấy đơn'; continue; }
			$cl    = $cl_by[ (string) $m ];
			$xu_ly = $cl > 0 ? 'NV trả lại' : ( $cl < 0 ? 'Kế toán bù' : 'Khớp' );
			$r     = self::xac_nhan_quyet_toan_cn( $m, $nguoi, $xu_ly, $cl );
			if ( ! empty( $r['success'] ) ) { $ok++; } else { $errs[] = $m . ': ' . ( isset( $r['error'] ) ? $r['error'] : '?' ); }
		}
		return array( 'success' => count( $errs ) === 0, 'done' => $ok, 'errors' => $errs );
	}

	// ---------------------------------------------------------------- số dư đầu kỳ

	public static function get_so_du_dau_ky() {
		return VHCP_Util::num( VHCP_Meta::get( 'soDuDauKy', 0 ) );
	}

	public static function set_so_du_dau_ky( $v ) {
		VHCP_Meta::set( 'soDuDauKy', (string) VHCP_Util::num( $v ) );
		return VHCP_Util::ok( array( 'value' => VHCP_Util::num( $v ) ) );
	}

	/** Cơ sở + người lập của đơn (dùng để phân thư mục ảnh). */
	public static function don_folder_meta( $ma_don ) {
		global $wpdb;
		$nguoi_lap = ''; $coso = '';
		if ( $ma_don ) {
			$d = self::don_row( $ma_don );
			if ( $d ) { $nguoi_lap = trim( (string) $d['nguoi_lap'] ); }
			$t    = VHCP_DB::t( 'chiphi' );
			$cnt  = array();
			foreach ( VHCP_DB::rows( $wpdb->prepare( "SELECT coso FROM $t WHERE ma_don=%s ORDER BY stt ASC", (string) $ma_don ) ) as $r ) {
				$c = trim( (string) $r['coso'] );
				if ( $c === '' ) { continue; }
				$cnt[ $c ] = ( isset( $cnt[ $c ] ) ? $cnt[ $c ] : 0 ) + 1;
			}
			foreach ( $cnt as $c => $n ) {
				if ( $coso === '' || $n > $cnt[ $coso ] ) { $coso = $c; }
			}
		}
		return array( 'coso' => $coso, 'nguoiLap' => $nguoi_lap );
	}
}
