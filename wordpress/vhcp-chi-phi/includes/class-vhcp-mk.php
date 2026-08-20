<?php
/**
 * MARKETING — mỗi gian hàng / sự kiện = 1 đơn, trong đơn nhiều hạng mục.
 * Thay 2 sheet MK_Don + MK_Line.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_MK {

	public static function don_row( $ma ) {
		global $wpdb;
		$t = VHCP_DB::t( 'mk_don' );
		return VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma=%s", (string) $ma ) );
	}

	public static function lines_of( $ma ) {
		global $wpdb;
		$t = VHCP_DB::t( 'mk_line' );
		return VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE ma_don=%s ORDER BY stt ASC", (string) $ma ) );
	}

	public static function all_dons() {
		global $wpdb;
		$t = VHCP_DB::t( 'mk_don' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	public static function all_lines() {
		global $wpdb;
		$t = VHCP_DB::t( 'mk_line' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	public static function create_don( $coso, $ten, $ky, $kenh, $nguoi ) {
		global $wpdb;
		$coso = trim( (string) $coso );
		$ten  = VHCP_Util::san( $ten );
		if ( $coso === '' && $ten === '' ) { return VHCP_Util::err( 'Nhập Cơ sở/Gian hoặc Tên chiến dịch/sự kiện' ); }
		$ma = VHCP_Util::uid( 'MKD' );
		$ky = trim( (string) $ky );
		if ( $ky === '' ) { $ky = VHCP_Util::now()->format( 'm/Y' ); }
		$wpdb->insert( VHCP_DB::t( 'mk_don' ), array(
			'ma'         => $ma,
			'coso'       => $coso,
			'ten'        => $ten,
			'ky'         => $ky,
			'kenh'       => trim( (string) $kenh ),
			'trang_thai' => 'Đang chạy',
			'ngay_tao'   => VHCP_Util::now()->format( 'd/m/Y' ),
			'nguoi_tao'  => (string) $nguoi,
		) );
		return VHCP_Util::ok( array( 'ma' => $ma ) );
	}

	/** _mkAggByDon(): tổng dự toán / thực chi / kết quả theo đơn. */
	private static function agg() {
		$agg = array();
		foreach ( self::all_lines() as $r ) {
			$md = (string) $r['ma_don'];
			if ( $md === '' ) { continue; }
			if ( ! isset( $agg[ $md ] ) ) { $agg[ $md ] = array( 'dt' => 0, 'tt' => 0, 'kq' => 0 ); }
			$agg[ $md ]['dt'] += VHCP_Util::num( $r['du_toan'] );
			$agg[ $md ]['tt'] += VHCP_Util::num( $r['thuc_te'] );
			$agg[ $md ]['kq'] += VHCP_Util::num( $r['ket_qua'] );
		}
		return $agg;
	}

	public static function list_don( $coso = 'all' ) {
		$agg = self::agg();
		$out = array();
		foreach ( self::all_dons() as $r ) {
			if ( $coso && $coso !== 'all' && trim( (string) $r['coso'] ) !== $coso ) { continue; }
			$a  = isset( $agg[ $r['ma'] ] ) ? $agg[ $r['ma'] ] : array( 'dt' => 0, 'tt' => 0, 'kq' => 0 );
			$out[] = array(
				'ma'          => $r['ma'],
				'coso'        => $r['coso'],
				'ten'         => $r['ten'],
				'ky'          => VHCP_Util::fmt( $r['ky'] ),
				'kenh'        => $r['kenh'],
				'trangThai'   => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Đang chạy' ),
				'ngayTao'     => VHCP_Util::fmt( $r['ngay_tao'] ),
				'nguoi'       => $r['nguoi_tao'],
				'tongDuToan'  => $a['dt'],
				'tongThucChi' => $a['tt'],
				'chenh'       => $a['tt'] - $a['dt'],
				'ketQua'      => $a['kq'],
				'cpKetQua'    => $a['kq'] > 0 ? (int) round( $a['tt'] / $a['kq'] ) : 0,
			);
		}
		$cs = array();
		foreach ( VHCP_Cfg::cfg_static()['coso'] as $x ) { $cs[] = $x['ten']; }
		return VHCP_Util::ok( array( 'items' => array_reverse( $out ), 'coso' => $cs ) );
	}

	public static function get_don( $ma ) {
		$h = self::don_row( $ma );
		if ( ! $h ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$lines = array();
		$dt = 0; $tt = 0; $kq = 0; $dt_tu = 0; $dt_tt = 0; $tt_tu = 0; $tt_tt = 0; $vat = 0; $novat = 0;
		foreach ( self::lines_of( $ma ) as $r ) {
			$d = VHCP_Util::num( $r['du_toan'] );
			$t = VHCP_Util::num( $r['thuc_te'] );
			$k = VHCP_Util::num( $r['ket_qua'] );
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
				'ngay'     => VHCP_Util::fmt( $r['ngay'] ),
				'note'     => (string) $r['note'],
				'hoSo'     => trim( (string) $r['ho_so'] ),
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
		return VHCP_Util::ok( array(
			'ma'                 => (string) $ma,
			'coso'               => $h['coso'],
			'ten'                => $h['ten'],
			'ky'                 => VHCP_Util::fmt( $h['ky'] ),
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

	private static function line_data( $rec ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };
		return array(
			'kenh'      => VHCP_Util::st( $g( 'kenh' ) ),
			'noi_dung'  => VHCP_Util::st( $g( 'noiDung' ) ),
			'du_toan'   => VHCP_Util::num( $g( 'duToan' ) ),
			'thuc_te'   => VHCP_Util::num( $g( 'thucTe' ) ),
			'hinh_thuc' => VHCP_Util::st( $g( 'hinhThuc' ) ),
			'vat'       => VHCP_Util::st( $g( 'vat' ) ),
			'ket_qua'   => VHCP_Util::num( $g( 'ketQua' ) ),
			'ngay'      => VHCP_Util::st( $g( 'ngay' ) ),
			'note'      => VHCP_Util::st( $g( 'note' ) ),
			'ho_so'     => VHCP_Util::st( $g( 'hoSo' ) ),
		);
	}

	public static function add_line( $ma_don, $rec ) {
		global $wpdb;
		if ( ! self::don_row( $ma_don ) ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$rec = (array) $rec;
		if ( ! isset( $rec['ngay'] ) || trim( (string) $rec['ngay'] ) === '' ) { $rec['ngay'] = VHCP_Util::now()->format( 'd/m/Y' ); }
		$data           = self::line_data( $rec );
		$data['id']     = VHCP_Util::uid( 'MKL' );
		$data['ma_don'] = (string) $ma_don;
		$wpdb->insert( VHCP_DB::t( 'mk_line' ), $data );
		return VHCP_Util::ok();
	}

	public static function update_line( $id, $rec ) {
		global $wpdb;
		$t = VHCP_DB::t( 'mk_line' );
		$cur = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%s", (string) $id ) );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy hạng mục' ); }
		$wpdb->update( $t, self::line_data( $rec ), array( 'id' => (string) $id ) );
		return VHCP_Util::ok();
	}

	public static function delete_line( $id ) {
		global $wpdb;
		$t = VHCP_DB::t( 'mk_line' );
		$cur = VHCP_DB::row( $wpdb->prepare( "SELECT id FROM $t WHERE id=%s", (string) $id ) );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy hạng mục' ); }
		$wpdb->delete( $t, array( 'id' => (string) $id ) );
		return VHCP_Util::ok();
	}

	public static function edit_don( $ma, $coso, $ten, $ky = null, $kenh = null ) {
		global $wpdb;
		if ( ! self::don_row( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$data = array( 'coso' => trim( (string) $coso ), 'ten' => VHCP_Util::san( $ten ) );
		if ( $ky !== null )   { $data['ky']   = trim( (string) $ky ); }
		if ( $kenh !== null ) { $data['kenh'] = trim( (string) $kenh ); }
		$wpdb->update( VHCP_DB::t( 'mk_don' ), $data, array( 'ma' => (string) $ma ) );
		return VHCP_Util::ok();
	}

	public static function close_don( $ma ) {
		global $wpdb;
		if ( ! self::don_row( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCP_DB::t( 'mk_don' ), array( 'trang_thai' => 'Đã đóng' ), array( 'ma' => (string) $ma ) );
		return VHCP_Util::ok();
	}

	public static function reopen_don( $ma ) {
		global $wpdb;
		if ( ! self::don_row( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCP_DB::t( 'mk_don' ), array( 'trang_thai' => 'Đang chạy' ), array( 'ma' => (string) $ma ) );
		return VHCP_Util::ok();
	}

	public static function delete_don( $ma ) {
		global $wpdb;
		if ( ! self::don_row( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$wpdb->delete( VHCP_DB::t( 'mk_line' ), array( 'ma_don' => (string) $ma ) );
		$wpdb->delete( VHCP_DB::t( 'mk_don' ), array( 'ma' => (string) $ma ) );
		return VHCP_Util::ok();
	}
}
