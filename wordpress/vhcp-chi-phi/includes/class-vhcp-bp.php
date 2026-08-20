<?php
/**
 * CÔNG TÁC / SETUP (chi phí bộ phận) — mỗi đợt là 1 "sheet" riêng ở app cũ,
 * nay là các dòng trong vhcp_bp_line khóa theo (ma, row_no), row_no bắt đầu từ 5.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_BP {

	const DATA_ROW = 5;

	public static function find( $ma ) {
		global $wpdb;
		$t = VHCP_DB::t( 'bp_index' );
		return VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma=%s", (string) $ma ) );
	}

	public static function lines_of( $ma ) {
		global $wpdb;
		$t = VHCP_DB::t( 'bp_line' );
		return VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE ma=%s ORDER BY row_no ASC", (string) $ma ) );
	}

	public static function all_index() {
		$t = VHCP_DB::t( 'bp_index' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	/**
	 * Mọi đợt kèm dòng chi — ĐÚNG 2 LỆNH DB (danh mục + toàn bộ dòng, gom trong PHP).
	 * Dùng cho danh sách, báo cáo tuần, báo cáo gian và xuất MISA.
	 */
	public static function all_with_lines() {
		$ti = VHCP_DB::t( 'bp_index' );
		$tl = VHCP_DB::t( 'bp_line' );
		$rows = VHCP_DB::rows( "SELECT * FROM $ti ORDER BY stt ASC" );
		$by   = array();
		foreach ( VHCP_DB::rows( "SELECT * FROM $tl ORDER BY ma ASC, row_no ASC" ) as $l ) {
			$by[ (string) $l['ma'] ][] = $l;
		}
		foreach ( $rows as $i => $r ) {
			$k = (string) $r['ma'];
			$rows[ $i ]['lines'] = isset( $by[ $k ] ) ? $by[ $k ] : array();
		}
		return $rows;
	}

	private static function next_row( $ma ) {
		global $wpdb;
		$t   = VHCP_DB::t( 'bp_line' );
		$max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(row_no) FROM $t WHERE ma=%s", (string) $ma ) );
		return max( self::DATA_ROW, $max + 1 );
	}

	private static function is_real( $r ) {
		return ! ( trim( (string) $r['noi_dung'] ) === '' && ! ( VHCP_Util::num( $r['du_toan'] ) || VHCP_Util::num( $r['thuc_te'] ) ) );
	}

	public static function create( $loai, $ten, $nguoi, $dia_diem, $ky, $creator ) {
		global $wpdb;
		$loai = trim( (string) $loai );
		if ( ! in_array( $loai, array( 'Công tác', 'Setup' ), true ) ) { return VHCP_Util::err( 'Loại không hợp lệ' ); }
		$ten = VHCP_Util::san( $ten );
		if ( $ten === '' ) { return VHCP_Util::err( 'Tên đợt trống' ); }
		$ky = trim( (string) $ky );
		if ( $ky === '' ) { $ky = VHCP_Util::now()->format( 'm/Y' ); }
		$ma = VHCP_Util::uid( 'BP' );
		$wpdb->insert( VHCP_DB::t( 'bp_index' ), array(
			'ma'         => $ma,
			'loai'       => $loai,
			'ten'        => $ten,
			'nguoi'      => trim( (string) $nguoi ),
			'dia_diem'   => trim( (string) $dia_diem ),
			'ky'         => $ky,
			'trang_thai' => 'Đang xử lý',
			'ngay_tao'   => VHCP_Util::now()->format( 'd/m/Y' ),
			'nguoi_tao'  => (string) $creator,
		) );
		return VHCP_Util::ok( array( 'ma' => $ma ) );
	}

	public static function list_bp( $loai = 'all' ) {
		$coso = array();
		foreach ( VHCP_Cfg::cfg_static()['coso'] as $x ) { $coso[] = $x['ten']; }
		$out = array();
		foreach ( self::all_with_lines() as $r ) {
			if ( $loai && $loai !== 'all' && (string) $r['loai'] !== $loai ) { continue; }
			$dt = 0; $tt = 0;
			foreach ( $r['lines'] as $x ) {
				$dt += VHCP_Util::num( $x['du_toan'] );
				$tt += VHCP_Util::num( $x['thuc_te'] );
			}
			$out[] = array(
				'ma'          => $r['ma'],
				'loai'        => $r['loai'],
				'ten'         => $r['ten'],
				'nguoi'       => $r['nguoi'],
				'diaDiem'     => $r['dia_diem'],
				'ky'          => VHCP_Util::fmt( $r['ky'] ),
				'trangThai'   => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Đang xử lý' ),
				'ngayTao'     => VHCP_Util::fmt( $r['ngay_tao'] ),
				'nguoiTao'    => $r['nguoi_tao'],
				'tongDuToan'  => $dt,
				'tongThucChi' => $tt,
				'chenh'       => $tt - $dt,
				'url'         => '',
			);
		}
		return VHCP_Util::ok( array( 'items' => array_reverse( $out ), 'coso' => $coso ) );
	}

	public static function get( $ma ) {
		$f = self::find( $ma );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy đợt chi phí' ); }
		$lines = array();
		$dt = 0; $tt = 0; $vat = 0; $novat = 0; $tu = 0; $ttt = 0;
		foreach ( self::lines_of( $ma ) as $r ) {
			if ( ! self::is_real( $r ) ) { continue; }
			$l = array(
				'row'       => (int) $r['row_no'],
				'noiDung'   => (string) $r['noi_dung'],
				'soLuong'   => VHCP_Util::num( $r['so_luong'] ),
				'donGia'    => VHCP_Util::num( $r['don_gia'] ),
				'thanhTien' => VHCP_Util::num( $r['thanh_tien'] ),
				'duToan'    => VHCP_Util::num( $r['du_toan'] ),
				'thucTe'    => VHCP_Util::num( $r['thuc_te'] ),
				'hinhThuc'  => trim( (string) $r['hinh_thuc'] ),
				'vat'       => (string) $r['vat'],
				'ngay'      => VHCP_Util::fmt( $r['ngay'] ),
				'note'      => (string) $r['note'],
				'hoSo'      => trim( (string) $r['ho_so'] ),
			);
			$lines[] = $l;
			$dt += $l['duToan'];
			$tt += $l['thucTe'];
			if ( $l['hinhThuc'] === 'Trực tiếp' ) {
				$ttt += $l['thucTe'];
				if ( mb_strpos( $l['vat'], 'Có' ) !== false ) { $vat += $l['thucTe']; } else { $novat += $l['thucTe']; }
			} else {
				$tu += $l['thucTe'];
			}
		}
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang xử lý' );
		return VHCP_Util::ok( array(
			'ma'                => (string) $ma,
			'loai'              => $f['loai'],
			'ten'               => $f['ten'],
			'nguoi'             => $f['nguoi'],
			'diaDiem'           => $f['dia_diem'],
			'ky'                => VHCP_Util::fmt( $f['ky'] ),
			'trangThai'         => $st,
			'url'               => '',
			'closed'            => ( $st === 'Đã đóng' ),
			'lines'             => $lines,
			'tongDuToan'        => $dt,
			'tongThucChi'       => $tt,
			'chenh'             => $tt - $dt,
			'thucTrucTiepVAT'   => $vat,
			'thucTrucTiepNoVAT' => $novat,
			'thucTamUng'        => $tu,
			'thucTrucTiep'      => $ttt,
		) );
	}

	private static function line_data( $rec ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };
		$sl  = VHCP_Util::num( $g( 'soLuong' ) );
		$dg  = VHCP_Util::num( $g( 'donGia' ) );
		return array(
			'noi_dung'   => VHCP_Util::st( $g( 'noiDung' ) ),
			'so_luong'   => $sl,
			'don_gia'    => $dg,
			'thanh_tien' => $sl * $dg,
			'du_toan'    => VHCP_Util::num( $g( 'duToan' ) ),
			'thuc_te'    => VHCP_Util::num( $g( 'thucTe' ) ),
			'hinh_thuc'  => VHCP_Util::st( $g( 'hinhThuc' ) ),
			'vat'        => VHCP_Util::st( $g( 'vat' ) ),
			'ngay'       => VHCP_Util::st( $g( 'ngay' ) ),
			'note'       => VHCP_Util::st( $g( 'note' ) ),
			'ho_so'      => VHCP_Util::st( $g( 'hoSo' ) ),
		);
	}

	public static function add_line( $ma, $rec ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy' ); }
		$rec = (array) $rec;
		if ( ! isset( $rec['ngay'] ) || trim( (string) $rec['ngay'] ) === '' ) { $rec['ngay'] = VHCP_Util::now()->format( 'd/m/Y' ); }
		$data           = self::line_data( $rec );
		$data['ma']     = (string) $ma;
		$data['row_no'] = self::next_row( $ma );
		$wpdb->insert( VHCP_DB::t( 'bp_line' ), $data );
		return VHCP_Util::ok();
	}

	public static function update_line( $ma, $row, $rec ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy' ); }
		$row = (int) $row;
		if ( $row < self::DATA_ROW ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$t = VHCP_DB::t( 'bp_line' );
		if ( ! VHCP_DB::row( $wpdb->prepare( "SELECT id FROM $t WHERE ma=%s AND row_no=%d", (string) $ma, $row ) ) ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$wpdb->update( $t, self::line_data( $rec ), array( 'ma' => (string) $ma, 'row_no' => $row ) );
		return VHCP_Util::ok();
	}

	public static function delete_line( $ma, $row ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy' ); }
		$row = (int) $row;
		if ( $row < self::DATA_ROW ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$wpdb->delete( VHCP_DB::t( 'bp_line' ), array( 'ma' => (string) $ma, 'row_no' => $row ) );
		return VHCP_Util::ok();
	}

	public static function rename( $ma, $ten ) {
		global $wpdb;
		$ten = VHCP_Util::san( $ten );
		if ( $ten === '' ) { return VHCP_Util::err( 'Tên trống' ); }
		if ( ! self::find( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCP_DB::t( 'bp_index' ), array( 'ten' => $ten ), array( 'ma' => (string) $ma ) );
		return VHCP_Util::ok( array( 'ten' => $ten ) );
	}

	public static function close( $ma ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCP_DB::t( 'bp_index' ), array( 'trang_thai' => 'Đã đóng' ), array( 'ma' => (string) $ma ) );
		return VHCP_Util::ok();
	}

	public static function reopen( $ma ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCP_DB::t( 'bp_index' ), array( 'trang_thai' => 'Đang xử lý' ), array( 'ma' => (string) $ma ) );
		return VHCP_Util::ok();
	}

	public static function delete( $ma ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy' ); }
		$wpdb->delete( VHCP_DB::t( 'bp_line' ), array( 'ma' => (string) $ma ) );
		$wpdb->delete( VHCP_DB::t( 'bp_index' ), array( 'ma' => (string) $ma ) );
		return VHCP_Util::ok();
	}
}
