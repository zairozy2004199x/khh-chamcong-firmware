<?php
/**
 * MARKETING — mỗi gian hàng / sự kiện = 1 đơn, trong đơn nhiều hạng mục.
 * Thay 2 sheet MK_Don + MK_Line.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPVP_MK {

	public static function don_row( $ma ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'mk_don' );
		return VHCPVP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma=%s", (string) $ma ) );
	}

	public static function lines_of( $ma ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'mk_line' );
		return VHCPVP_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE ma_don=%s ORDER BY stt ASC", (string) $ma ) );
	}

	/**
	 * ĐƠN VỊ của một đợt marketing — neo theo CƠ SỞ (mỗi đợt chạy cho một gian).
	 *
	 * ⚠️ `$id` Ở LỚP NÀY LÀ MỘT DÒNG TRONG ĐỢT, không phải mã đợt — `update_line()` và
	 *    `delete_line()` nhận nó. Phải đi vòng qua bảng dòng để lấy mã đợt; tra thẳng vào
	 *    `mk_don` thì không thấy, trả `null`, và `null` là CHO QUA: kế toán bên này sửa
	 *    được từng dòng tiền của bên kia.
	 */
	public static function don_vi_cua( $khoa, $kieu = 'ma' ) {
		if ( 'id' === $kieu ) {
			global $wpdb;
			$t = VHCPVP_DB::t( 'mk_line' );
			$l = VHCPVP_DB::row( $wpdb->prepare( "SELECT ma_don FROM $t WHERE id=%s", (string) $khoa ) );
			if ( ! $l ) { return null; }
			$khoa = (string) $l['ma_don'];
		} elseif ( 'ma' !== $kieu && 'ma_don' !== $kieu ) {
			return null;
		}
		$r = self::don_row( $khoa );
		if ( ! $r ) { return null; }
		return VHCPVP_DonVi::cua_coso( isset( $r['coso'] ) ? $r['coso'] : '' );
	}

	public static function all_dons() {
		global $wpdb;
		$t = VHCPVP_DB::t( 'mk_don' );
		return VHCPVP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	public static function all_lines() {
		global $wpdb;
		$t = VHCPVP_DB::t( 'mk_line' );
		return VHCPVP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	public static function create_don( $coso, $ten, $ky, $kenh, $nguoi ) {
		global $wpdb;
		$coso = trim( (string) $coso );
		$ten  = VHCPVP_Util::san( $ten );
		if ( $coso === '' && $ten === '' ) { return VHCPVP_Util::err( 'Nhập Cơ sở/Gian hoặc Tên chiến dịch/sự kiện' ); }
		$ma = VHCPVP_Util::uid( 'MKD' );
		$ky = trim( (string) $ky );
		if ( $ky === '' ) { $ky = VHCPVP_Util::now()->format( 'm/Y' ); }
		$wpdb->insert( VHCPVP_DB::t( 'mk_don' ), array(
			/* Mảng đóng dấu lúc lập — xem chốt ở `VHCPVP_SoChi::add()`. */
			'khoi'       => VHCPVP_DB::khoi(),
			'ma'         => $ma,
			'coso'       => $coso,
			'ten'        => $ten,
			'ky'         => $ky,
			'kenh'       => trim( (string) $kenh ),
			'trang_thai' => 'Đang chạy',
			'ngay_tao'   => VHCPVP_Util::now()->format( 'd/m/Y' ),
			'nguoi_tao'  => (string) $nguoi,
		) );
		return VHCPVP_Util::ok( array( 'ma' => $ma ) );
	}

	/** _mkAggByDon(): tổng dự toán / thực chi / kết quả theo đơn. */
	private static function agg() {
		$agg = array();
		foreach ( self::all_lines() as $r ) {
			$md = (string) $r['ma_don'];
			if ( $md === '' ) { continue; }
			if ( ! isset( $agg[ $md ] ) ) { $agg[ $md ] = array( 'dt' => 0, 'tt' => 0, 'kq' => 0 ); }
			$agg[ $md ]['dt'] += VHCPVP_Util::num( $r['du_toan'] );
			$agg[ $md ]['tt'] += VHCPVP_Util::num( $r['thuc_te'] );
			$agg[ $md ]['kq'] += VHCPVP_Util::num( $r['ket_qua'] );
		}
		return $agg;
	}

	public static function list_don( $coso = 'all' ) {
		$agg = self::agg();
		$out = array();
		$dv_xem = VHCPVP_DonVi::xem_duoc();
		foreach ( self::all_dons() as $r ) {
			/* Lọc theo ĐƠN VỊ đứng trước ô lọc cơ sở của người dùng: ô kia là "tôi muốn xem
			   gian nào", chốt này là "tôi được xem gian nào". */
			if ( null !== $dv_xem && ! VHCPVP_DonVi::xem_duoc_coso( isset( $r['coso'] ) ? $r['coso'] : '' ) ) { continue; }
			if ( $coso && $coso !== 'all' && trim( (string) $r['coso'] ) !== $coso ) { continue; }
			$a  = isset( $agg[ $r['ma'] ] ) ? $agg[ $r['ma'] ] : array( 'dt' => 0, 'tt' => 0, 'kq' => 0 );
			$out[] = array(
				'ma'          => $r['ma'],
				'coso'        => $r['coso'],
				'ten'         => $r['ten'],
				'ky'          => VHCPVP_Util::fmt( $r['ky'] ),
				'kenh'        => $r['kenh'],
				'trangThai'   => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Đang chạy' ),
				'ngayTao'     => VHCPVP_Util::fmt( $r['ngay_tao'] ),
				'nguoi'       => $r['nguoi_tao'],
				'tongDuToan'  => $a['dt'],
				'tongThucChi' => $a['tt'],
				'chenh'       => $a['tt'] - $a['dt'],
				'ketQua'      => $a['kq'],
				'cpKetQua'    => $a['kq'] > 0 ? (int) round( $a['tt'] / $a['kq'] ) : 0,
			);
		}
		/* 🔴 Ô CHỌN CƠ SỞ KHÔNG ĐƯỢC BÀY GIAN CỦA BÊN KIA — anh Thắng 11/09/2026: *"Thêm đơn vị
		   KVC để tách ra được không. Vì để bên K&H vẫn thấy bên Posh"*, kèm ảnh ô "Gian / cơ
		   sở" của đơn Kỹ thuật xổ ra cả "POSH MN CGV VINCOM LANDMARK".

		   Danh sách này trước đây lấy THẲNG toàn bộ danh mục cơ sở, nên mọi lớp tách đơn vị
		   dựng công phu ở `VHCPVP_DonVi` đều vô nghĩa ngay tại ô người ta gõ hằng ngày: chọn
		   nhầm một gian của bên kia là dòng chi rơi sang sổ của họ.

		   ⚠️ `coso_xem_duoc()` trả `null` nghĩa là XEM CẢ (Admin · Quản lý · Kế toán) — lúc ấy
		      phải bày đủ, không phải bày rỗng. */
		$cs = VHCPVP_DonVi::coso_xem_duoc();
		if ( null === $cs ) {
			$cs = array();
			foreach ( VHCPVP_Cfg::cfg_static()['coso'] as $x ) { $cs[] = $x['ten']; }
		}
		return VHCPVP_Util::ok( array( 'items' => array_reverse( $out ), 'coso' => $cs ) );
	}

	public static function get_don( $ma ) {
		$h = self::don_row( $ma );
		if ( ! $h ) { return VHCPVP_Util::err( 'Không tìm thấy đơn' ); }
		$lines = array();
		$dt = 0; $tt = 0; $kq = 0; $dt_tu = 0; $dt_tt = 0; $tt_tu = 0; $tt_tt = 0; $vat = 0; $novat = 0;
		foreach ( self::lines_of( $ma ) as $r ) {
			$d = VHCPVP_Util::num( $r['du_toan'] );
			$t = VHCPVP_Util::num( $r['thuc_te'] );
			$k = VHCPVP_Util::num( $r['ket_qua'] );
			$is_tt = ( trim( (string) $r['hinh_thuc'] ) === 'Trực tiếp' );
			$lines[] = array(
				'id'       => (string) $r['id'],
				'kenh'     => (string) $r['kenh'],
				'noiDung'  => (string) $r['noi_dung'],
				'duToan'   => $d,
				'thucTe'   => $t,
				'hinhThuc' => trim( (string) $r['hinh_thuc'] ),
				'vat'      => (string) $r['vat'],
				'ketQua'   => $k,
				'ngay'     => VHCPVP_Util::fmt( $r['ngay'] ),
				'note'     => (string) $r['note'],
				'hoSo'     => trim( (string) $r['ho_so'] ),
				'loaiCp'   => (string) $r['loai_cp'],
				'tkNo'     => (string) $r['tk_no'],
				'tkCo'     => (string) $r['tk_co'],
				'maDt'     => (string) $r['ma_dt'],
			);
			$dt += $d; $tt += $t; $kq += $k;
			if ( $is_tt ) {
				$dt_tt += $d; $tt_tt += $t;
				if ( mb_strpos( (string) $r['vat'], 'Có' ) !== false ) { $vat += $t; } else { $novat += $t; }
			} else {
				$dt_tu += $d; $tt_tu += $t;
			}
		}
		$st = (string) ( $h['trang_thai'] !== '' ? $h['trang_thai'] : 'Đang chạy' );
		return VHCPVP_Util::ok( array(
			'ma'                 => (string) $ma,
			'coso'               => $h['coso'],
			'ten'                => $h['ten'],
			'ky'                 => VHCPVP_Util::fmt( $h['ky'] ),
			'kenh'               => $h['kenh'],
			'trangThai'          => $st,
			'closed'             => ( $st === 'Đã đóng' ),
			'lines'              => $lines,
			'tongDuToan'         => $dt,
			'tongThucChi'        => $tt,
			'chenh'              => $tt - $dt,
			'ketQua'             => $kq,
			'cpKetQua'           => $kq > 0 ? (int) round( $tt / $kq ) : 0,
			'duToanTamUng'       => $dt_tu,
			'duToanTrucTiep'     => $dt_tt,
			'thucTamUng'         => $tt_tu,
			'thucTrucTiep'       => $tt_tt,
			'thucTrucTiepVAT'    => $vat,
			'thucTrucTiepNoVAT'  => $novat,
		) );
	}

	private static function line_data( $rec, $coso = '' ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };
		$loai_cp = VHCPVP_Util::st( $g( 'loaiCp' ) );
		// Cơ sở của ĐƠN marketing quyết định mảng kinh doanh -> quyết định TK Nợ.
		$tk      = VHCPVP_Cfg::resolve_tk( $loai_cp, VHCPVP_Util::st( $g( 'hinhThuc' ) ), array( 'tkNo' => VHCPVP_Util::st( $g( 'tkNo' ) ), 'tkCo' => VHCPVP_Util::st( $g( 'tkCo' ) ), 'maDt' => VHCPVP_Util::st( $g( 'maDt' ) ) ), $coso );
		return array(
			'loai_cp'   => $loai_cp,
			'tk_no'     => $loai_cp !== '' ? $tk['tk_no'] : '',
			'tk_co'     => $loai_cp !== '' ? $tk['tk_co'] : '',
			'ma_dt'     => $loai_cp !== '' ? $tk['ma_dt'] : '',
			'kenh'      => VHCPVP_Util::st( $g( 'kenh' ) ),
			'noi_dung'  => VHCPVP_Util::st( $g( 'noiDung' ) ),
			'du_toan'   => VHCPVP_Util::num( $g( 'duToan' ) ),
			'thuc_te'   => VHCPVP_Util::num( $g( 'thucTe' ) ),
			'hinh_thuc' => VHCPVP_Util::st( $g( 'hinhThuc' ) ),
			'vat'       => VHCPVP_Util::st( $g( 'vat' ) ),
			'ket_qua'   => VHCPVP_Util::num( $g( 'ketQua' ) ),
			'ngay'      => VHCPVP_Util::st( $g( 'ngay' ) ),
			'note'      => VHCPVP_Util::st( $g( 'note' ) ),
			'ho_so'     => VHCPVP_Util::st( $g( 'hoSo' ) ),
		);
	}

	public static function add_line( $ma_don, $rec ) {
		global $wpdb;
		$don = self::don_row( $ma_don );
		if ( ! $don ) { return VHCPVP_Util::err( 'Không tìm thấy đơn' ); }
		$rec = (array) $rec;
		if ( ! isset( $rec['ngay'] ) || trim( (string) $rec['ngay'] ) === '' ) { $rec['ngay'] = VHCPVP_Util::now()->format( 'd/m/Y' ); }
		$data           = self::line_data( $rec, (string) $don['coso'] );
		$data['id']     = VHCPVP_Util::uid( 'MKL' );
		$data['ma_don'] = (string) $ma_don;
		$wpdb->insert( VHCPVP_DB::t( 'mk_line' ), $data );
		return VHCPVP_Util::ok();
	}

	public static function update_line( $id, $rec ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'mk_line' );
		$cur = VHCPVP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%s", (string) $id ) );
		if ( ! $cur ) { return VHCPVP_Util::err( 'Không tìm thấy hạng mục' ); }
		$don = self::don_row( (string) $cur['ma_don'] );
		$wpdb->update( $t, self::line_data( $rec, $don ? (string) $don['coso'] : '' ), array( 'id' => (string) $id ) );
		return VHCPVP_Util::ok();
	}

	public static function delete_line( $id ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'mk_line' );
		$cur = VHCPVP_DB::row( $wpdb->prepare( "SELECT id FROM $t WHERE id=%s", (string) $id ) );
		if ( ! $cur ) { return VHCPVP_Util::err( 'Không tìm thấy hạng mục' ); }
		$wpdb->delete( $t, array( 'id' => (string) $id ) );
		return VHCPVP_Util::ok();
	}

	public static function edit_don( $ma, $coso, $ten, $ky = null, $kenh = null ) {
		global $wpdb;
		if ( ! self::don_row( $ma ) ) { return VHCPVP_Util::err( 'Không tìm thấy đơn' ); }
		$data = array( 'coso' => trim( (string) $coso ), 'ten' => VHCPVP_Util::san( $ten ) );
		if ( $ky !== null )   { $data['ky']   = trim( (string) $ky ); }
		if ( $kenh !== null ) { $data['kenh'] = trim( (string) $kenh ); }
		$wpdb->update( VHCPVP_DB::t( 'mk_don' ), $data, array( 'ma' => (string) $ma ) );
		return VHCPVP_Util::ok();
	}

	public static function close_don( $ma ) {
		global $wpdb;
		if ( ! self::don_row( $ma ) ) { return VHCPVP_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCPVP_DB::t( 'mk_don' ), array( 'trang_thai' => 'Đã đóng' ), array( 'ma' => (string) $ma ) );
		return VHCPVP_Util::ok();
	}

	public static function reopen_don( $ma ) {
		global $wpdb;
		if ( ! self::don_row( $ma ) ) { return VHCPVP_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCPVP_DB::t( 'mk_don' ), array( 'trang_thai' => 'Đang chạy' ), array( 'ma' => (string) $ma ) );
		return VHCPVP_Util::ok();
	}

	/**
	 * Áp lại mã tài khoản cho hạng mục marketing đã chọn loại chi phí.
	 * Dòng chưa chọn loại thì không suy được (đếm vào chuaChonLoai để báo lại).
	 */
	public static function gan_ma_tai_khoan( $all = false ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'mk_line' );
		$n = 0; $thieu = array(); $chua = 0;
		$coso_of = array();
		foreach ( self::all_dons() as $d ) { $coso_of[ (string) $d['ma'] ] = (string) $d['coso']; }
		foreach ( self::all_lines() as $r ) {
			$loai = trim( (string) $r['loai_cp'] );
			if ( $loai === '' ) { $chua++; continue; }
			if ( ! $all && trim( (string) $r['tk_no'] ) !== '' ) { continue; }
			$md   = (string) $r['ma_don'];
			$cs   = isset( $coso_of[ $md ] ) ? $coso_of[ $md ] : '';
			$giu  = VHCPVP_Cfg::ma_con_hop_le( $loai, $cs, $r['tk_no'] );
			$tk = VHCPVP_Cfg::resolve_tk( $loai, trim( (string) $r['hinh_thuc'] ), array( 'tkNo' => $giu ), $cs );
			if ( $tk['tk_no'] === '' ) { $thieu[ $loai ] = 1; }
			$wpdb->update( $t, array( 'tk_no' => $tk['tk_no'], 'tk_co' => $tk['tk_co'], 'ma_dt' => $tk['ma_dt'] ), array( 'id' => (string) $r['id'] ) );
			$n++;
		}
		return VHCPVP_Util::ok( array( 'updated' => $n, 'thieuMa' => array_keys( $thieu ), 'chuaChonLoai' => $chua ) );
	}

	public static function delete_don( $ma ) {
		global $wpdb;
		if ( ! self::don_row( $ma ) ) { return VHCPVP_Util::err( 'Không tìm thấy đơn' ); }
		$wpdb->delete( VHCPVP_DB::t( 'mk_line' ), array( 'ma_don' => (string) $ma ) );
		$wpdb->delete( VHCPVP_DB::t( 'mk_don' ), array( 'ma' => (string) $ma ) );
		return VHCPVP_Util::ok();
	}
}
