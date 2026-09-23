<?php
/**
 * CÔNG TÁC / SETUP (chi phí bộ phận) — mỗi đợt là 1 "sheet" riêng ở app cũ,
 * nay là các dòng trong vhcphn_bp_line khóa theo (ma, row_no), row_no bắt đầu từ 5.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPHN_BP {

	const DATA_ROW = 5;

	public static function find( $ma ) {
		global $wpdb;
		$t = VHCPHN_DB::t( 'bp_index' );
		return VHCPHN_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma=%s", (string) $ma ) );
	}

	public static function lines_of( $ma ) {
		global $wpdb;
		$t = VHCPHN_DB::t( 'bp_line' );
		return VHCPHN_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE ma=%s ORDER BY row_no ASC", (string) $ma ) );
	}

	/**
	 * ĐƠN VỊ của một đợt công tác / setup — neo theo NGƯỜI TẠO.
	 *
	 * Bảng này có `dia_diem` chứ không có cơ sở: địa điểm là chỗ người ta ĐI TỚI (một tỉnh,
	 * một hội chợ), gõ tự do, không nằm trong danh mục cơ sở nên không tra được đơn vị. Người
	 * lập thì luôn có đúng một nhà — xem chốt dài ở `VHCPHN_DuAn::don_vi_cua()`.
	 */
	public static function don_vi_cua( $khoa, $kieu = 'ma' ) {
		if ( 'ma' !== $kieu ) { return null; }
		$r = self::find( $khoa );
		if ( ! $r ) { return null; }
		return VHCPHN_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' );
	}

	public static function all_index() {
		$t = VHCPHN_DB::t( 'bp_index' );
		return VHCPHN_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	/**
	 * Mọi đợt kèm dòng chi — ĐÚNG 2 LỆNH DB (danh mục + toàn bộ dòng, gom trong PHP).
	 * Dùng cho danh sách, báo cáo tuần, báo cáo gian và xuất MISA.
	 */
	public static function all_with_lines() {
		$ti = VHCPHN_DB::t( 'bp_index' );
		$tl = VHCPHN_DB::t( 'bp_line' );
		$rows = VHCPHN_DB::rows( "SELECT * FROM $ti ORDER BY stt ASC" );
		$by   = array();
		foreach ( VHCPHN_DB::rows( "SELECT * FROM $tl ORDER BY ma ASC, row_no ASC" ) as $l ) {
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
		$t   = VHCPHN_DB::t( 'bp_line' );
		$max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(row_no) FROM $t WHERE ma=%s", (string) $ma ) );
		return max( self::DATA_ROW, $max + 1 );
	}

	private static function is_real( $r ) {
		return ! ( trim( (string) $r['noi_dung'] ) === '' && ! ( VHCPHN_Util::num( $r['du_toan'] ) || VHCPHN_Util::num( $r['thuc_te'] ) ) );
	}

	/**
	 * Tạo đợt với MÃ CHO TRƯỚC — dùng khi nạp dữ liệu cũ: dòng chi đã mang mã chuyến
	 * (BP_ms5y6…), nếu tạo đợt mã mới thì dòng chi mất liên kết.
	 * Địa điểm để trống thì dòng chi của đợt này CHƯA ra được TK Nợ (mảng lấy theo địa
	 * điểm) — phải điền rồi bấm "Gán mã cho dòng cũ".
	 */
	public static function create_voi_ma( $ma, $loai, $ten, $nguoi, $dia_diem, $ky, $creator ) {
		global $wpdb;
		$ma = trim( (string) $ma );
		if ( $ma === '' ) { return VHCPHN_Util::err( 'Thiếu mã đợt' ); }
		if ( self::find( $ma ) ) { return VHCPHN_Util::ok( array( 'ma' => $ma, 'daCo' => 1 ) ); }
		$loai = in_array( trim( (string) $loai ), array( 'Công tác', 'Setup' ), true ) ? trim( (string) $loai ) : 'Công tác';
		$ky   = trim( (string) $ky );
		if ( $ky === '' ) { $ky = VHCPHN_Util::now()->format( 'm/Y' ); }
		$wpdb->insert( VHCPHN_DB::t( 'bp_index' ), array(
			'ma'         => $ma,
			'loai'       => $loai,
			'ten'        => VHCPHN_Util::san( $ten ) !== '' ? VHCPHN_Util::san( $ten ) : $ma,
			'nguoi'      => trim( (string) $nguoi ),
			'dia_diem'   => trim( (string) $dia_diem ),
			'ky'         => $ky,
			'trang_thai' => 'Đang xử lý',
			'ngay_tao'   => VHCPHN_Util::now()->format( 'd/m/Y' ),
			'nguoi_tao'  => (string) $creator,
		) );
		return VHCPHN_Util::ok( array( 'ma' => $ma ) );
	}

	public static function create( $loai, $ten, $nguoi, $dia_diem, $ky, $creator ) {
		global $wpdb;
		$loai = trim( (string) $loai );
		if ( ! in_array( $loai, array( 'Công tác', 'Setup' ), true ) ) { return VHCPHN_Util::err( 'Loại không hợp lệ' ); }
		$ten = VHCPHN_Util::san( $ten );
		if ( $ten === '' ) { return VHCPHN_Util::err( 'Tên đợt trống' ); }
		$ky = trim( (string) $ky );
		if ( $ky === '' ) { $ky = VHCPHN_Util::now()->format( 'm/Y' ); }
		$ma = VHCPHN_Util::uid( 'BP' );
		$wpdb->insert( VHCPHN_DB::t( 'bp_index' ), array(
			'ma'         => $ma,
			'loai'       => $loai,
			'ten'        => $ten,
			'nguoi'      => trim( (string) $nguoi ),
			'dia_diem'   => trim( (string) $dia_diem ),
			'ky'         => $ky,
			'trang_thai' => 'Đang xử lý',
			'ngay_tao'   => VHCPHN_Util::now()->format( 'd/m/Y' ),
			'nguoi_tao'  => (string) $creator,
		) );
		return VHCPHN_Util::ok( array( 'ma' => $ma ) );
	}

	public static function list_bp( $loai = 'all' ) {
		$coso = array();
		foreach ( VHCPHN_Cfg::cfg_static()['coso'] as $x ) { $coso[] = $x['ten']; }
		$out = array();
		$dv_xem = VHCPHN_DonVi::xem_duoc();
		foreach ( self::all_with_lines() as $r ) {
			/* Công tác / Setup neo theo NGƯỜI TẠO — địa điểm gõ tự do, không tra được đơn vị. */
			if ( null !== $dv_xem
				&& ! VHCPHN_DonVi::duoc_xem( VHCPHN_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' ) ) ) { continue; }
			if ( $loai && $loai !== 'all' && (string) $r['loai'] !== $loai ) { continue; }
			$dt = 0; $tt = 0;
			foreach ( $r['lines'] as $x ) {
				$dt += VHCPHN_Util::num( $x['du_toan'] );
				$tt += VHCPHN_Util::num( $x['thuc_te'] );
			}
			$out[] = array(
				'ma'          => $r['ma'],
				'loai'        => $r['loai'],
				'ten'         => $r['ten'],
				'nguoi'       => $r['nguoi'],
				'diaDiem'     => $r['dia_diem'],
				'ky'          => VHCPHN_Util::fmt( $r['ky'] ),
				'trangThai'   => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Đang xử lý' ),
				'ngayTao'     => VHCPHN_Util::fmt( $r['ngay_tao'] ),
				'nguoiTao'    => $r['nguoi_tao'],
				'tongDuToan'  => $dt,
				'tongThucChi' => $tt,
				'chenh'       => $tt - $dt,
				'url'         => '',
			);
		}
		return VHCPHN_Util::ok( array( 'items' => array_reverse( $out ), 'coso' => $coso ) );
	}

	public static function get( $ma ) {
		$f = self::find( $ma );
		if ( ! $f ) { return VHCPHN_Util::err( 'Không tìm thấy đợt chi phí' ); }
		$lines = array();
		$dt = 0; $tt = 0; $vat = 0; $novat = 0; $tu = 0; $ttt = 0;
		foreach ( self::lines_of( $ma ) as $r ) {
			if ( ! self::is_real( $r ) ) { continue; }
			$l = array(
				'row'       => (int) $r['row_no'],
				'noiDung'   => (string) $r['noi_dung'],
				'soLuong'   => VHCPHN_Util::num( $r['so_luong'] ),
				'donGia'    => VHCPHN_Util::num( $r['don_gia'] ),
				'thanhTien' => VHCPHN_Util::num( $r['thanh_tien'] ),
				'duToan'    => VHCPHN_Util::num( $r['du_toan'] ),
				'thucTe'    => VHCPHN_Util::num( $r['thuc_te'] ),
				'hinhThuc'  => trim( (string) $r['hinh_thuc'] ),
				'vat'       => (string) $r['vat'],
				'ngay'      => VHCPHN_Util::fmt( $r['ngay'] ),
				'note'      => (string) $r['note'],
				'hoSo'      => trim( (string) $r['ho_so'] ),
				'loaiCp'    => (string) $r['loai_cp'],
				'tkNo'      => (string) $r['tk_no'],
				'tkCo'      => (string) $r['tk_co'],
				'maDt'      => (string) $r['ma_dt'],
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
		return VHCPHN_Util::ok( array(
			'ma'                => (string) $ma,
			'loai'              => $f['loai'],
			'ten'               => $f['ten'],
			'nguoi'             => $f['nguoi'],
			'diaDiem'           => $f['dia_diem'],
			'ky'                => VHCPHN_Util::fmt( $f['ky'] ),
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

	private static function line_data( $rec, $coso = '' ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };
		$sl  = VHCPHN_Util::num( $g( 'soLuong' ) );
		$dg  = VHCPHN_Util::num( $g( 'donGia' ) );
		$loai_cp = VHCPHN_Util::st( $g( 'loaiCp' ) );
		// Địa điểm của đợt công tác / setup đóng vai "cơ sở" -> quyết định mảng kinh doanh.
		$tk      = VHCPHN_Cfg::resolve_tk( $loai_cp, VHCPHN_Util::st( $g( 'hinhThuc' ) ), array( 'tkNo' => VHCPHN_Util::st( $g( 'tkNo' ) ), 'tkCo' => VHCPHN_Util::st( $g( 'tkCo' ) ), 'maDt' => VHCPHN_Util::st( $g( 'maDt' ) ) ), $coso );
		return array(
			'loai_cp'    => $loai_cp,
			'tk_no'      => $loai_cp !== '' ? $tk['tk_no'] : '',
			'tk_co'      => $loai_cp !== '' ? $tk['tk_co'] : '',
			'ma_dt'      => $loai_cp !== '' ? $tk['ma_dt'] : '',
			'noi_dung'   => VHCPHN_Util::st( $g( 'noiDung' ) ),
			'so_luong'   => $sl,
			'don_gia'    => $dg,
			'thanh_tien' => $sl * $dg,
			'du_toan'    => VHCPHN_Util::num( $g( 'duToan' ) ),
			'thuc_te'    => VHCPHN_Util::num( $g( 'thucTe' ) ),
			'hinh_thuc'  => VHCPHN_Util::st( $g( 'hinhThuc' ) ),
			'vat'        => VHCPHN_Util::st( $g( 'vat' ) ),
			'ngay'       => VHCPHN_Util::st( $g( 'ngay' ) ),
			'note'       => VHCPHN_Util::st( $g( 'note' ) ),
			'ho_so'      => VHCPHN_Util::st( $g( 'hoSo' ) ),
		);
	}

	public static function add_line( $ma, $rec ) {
		global $wpdb;
		$dot = self::find( $ma );
		if ( ! $dot ) { return VHCPHN_Util::err( 'Không tìm thấy' ); }
		$rec = (array) $rec;
		if ( ! isset( $rec['ngay'] ) || trim( (string) $rec['ngay'] ) === '' ) { $rec['ngay'] = VHCPHN_Util::now()->format( 'd/m/Y' ); }
		$data           = self::line_data( $rec, (string) $dot['dia_diem'] );
		$data['ma']     = (string) $ma;
		$data['row_no'] = self::next_row( $ma );
		$wpdb->insert( VHCPHN_DB::t( 'bp_line' ), $data );
		return VHCPHN_Util::ok();
	}

	public static function update_line( $ma, $row, $rec ) {
		global $wpdb;
		$dot = self::find( $ma );
		if ( ! $dot ) { return VHCPHN_Util::err( 'Không tìm thấy' ); }
		$row = (int) $row;
		if ( $row < self::DATA_ROW ) { return VHCPHN_Util::err( 'Dòng không hợp lệ' ); }
		$t = VHCPHN_DB::t( 'bp_line' );
		if ( ! VHCPHN_DB::row( $wpdb->prepare( "SELECT id FROM $t WHERE ma=%s AND row_no=%d", (string) $ma, $row ) ) ) { return VHCPHN_Util::err( 'Dòng không hợp lệ' ); }
		$wpdb->update( $t, self::line_data( $rec, (string) $dot['dia_diem'] ), array( 'ma' => (string) $ma, 'row_no' => $row ) );
		return VHCPHN_Util::ok();
	}

	public static function delete_line( $ma, $row ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCPHN_Util::err( 'Không tìm thấy' ); }
		$row = (int) $row;
		if ( $row < self::DATA_ROW ) { return VHCPHN_Util::err( 'Dòng không hợp lệ' ); }
		$wpdb->delete( VHCPHN_DB::t( 'bp_line' ), array( 'ma' => (string) $ma, 'row_no' => $row ) );
		return VHCPHN_Util::ok();
	}

	public static function rename( $ma, $ten ) {
		global $wpdb;
		$ten = VHCPHN_Util::san( $ten );
		if ( $ten === '' ) { return VHCPHN_Util::err( 'Tên trống' ); }
		if ( ! self::find( $ma ) ) { return VHCPHN_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCPHN_DB::t( 'bp_index' ), array( 'ten' => $ten ), array( 'ma' => (string) $ma ) );
		return VHCPHN_Util::ok( array( 'ten' => $ten ) );
	}

	public static function close( $ma ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCPHN_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCPHN_DB::t( 'bp_index' ), array( 'trang_thai' => 'Đã đóng' ), array( 'ma' => (string) $ma ) );
		return VHCPHN_Util::ok();
	}

	public static function reopen( $ma ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCPHN_Util::err( 'Không tìm thấy' ); }
		$wpdb->update( VHCPHN_DB::t( 'bp_index' ), array( 'trang_thai' => 'Đang xử lý' ), array( 'ma' => (string) $ma ) );
		return VHCPHN_Util::ok();
	}

	/** Áp lại mã tài khoản cho dòng Công tác/Setup đã chọn loại chi phí. */
	public static function gan_ma_tai_khoan( $all = false ) {
		global $wpdb;
		$t = VHCPHN_DB::t( 'bp_line' );
		$n = 0; $thieu = array(); $chua = 0;
		foreach ( self::all_with_lines() as $b ) {
			foreach ( $b['lines'] as $r ) {
				$loai = trim( (string) $r['loai_cp'] );
				if ( $loai === '' ) { $chua++; continue; }
				if ( ! $all && trim( (string) $r['tk_no'] ) !== '' ) { continue; }
				$giu = VHCPHN_Cfg::ma_con_hop_le( $loai, (string) $b['dia_diem'], $r['tk_no'] );
				$tk = VHCPHN_Cfg::resolve_tk( $loai, trim( (string) $r['hinh_thuc'] ), array( 'tkNo' => $giu ), (string) $b['dia_diem'] );
				if ( $tk['tk_no'] === '' ) { $thieu[ $loai ] = 1; }
				$wpdb->update( $t, array( 'tk_no' => $tk['tk_no'], 'tk_co' => $tk['tk_co'], 'ma_dt' => $tk['ma_dt'] ), array( 'id' => (int) $r['id'] ) );
				$n++;
			}
		}
		return VHCPHN_Util::ok( array( 'updated' => $n, 'thieuMa' => array_keys( $thieu ), 'chuaChonLoai' => $chua ) );
	}

	public static function delete( $ma ) {
		global $wpdb;
		if ( ! self::find( $ma ) ) { return VHCPHN_Util::err( 'Không tìm thấy' ); }
		$wpdb->delete( VHCPHN_DB::t( 'bp_line' ), array( 'ma' => (string) $ma ) );
		$wpdb->delete( VHCPHN_DB::t( 'bp_index' ), array( 'ma' => (string) $ma ) );
		return VHCPHN_Util::ok();
	}
}
