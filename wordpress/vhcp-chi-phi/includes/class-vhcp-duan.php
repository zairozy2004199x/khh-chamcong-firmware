<?php
/**
 * CHI PHÍ KỸ THUẬT — dự án Tháo dỡ / Setup lắp đặt + sheet "Chi phí cơ sở" chung xuyên suốt.
 *
 * App cũ mỗi dự án là 1 tab Google Sheet; ở đây là các dòng trong vhcp_da_line khóa theo
 * (ma_da, row_no). row_no vẫn bắt đầu từ 5 để giao diện gọi updateDuAnLine(maDA, row, rec) như cũ.
 *
 * Quy ước tính tiền giữ nguyên:
 *   - Hạng mục lớn (cap_cha rỗng) mang DỰ TOÁN. Thực tế của nó chỉ tính khi KHÔNG có mục con.
 *   - Mục con / (Phát sinh) chỉ mang THỰC TẾ; hình thức chi thừa hưởng của hạng mục cha.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_DuAn {

	const DATA_ROW = 5;

	public static function find( $ma_da ) {
		global $wpdb;
		$t = VHCP_DB::t( 'da_index' );
		return VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s", (string) $ma_da ) );
	}

	private static function lines_of( $ma_da ) {
		global $wpdb;
		$t = VHCP_DB::t( 'da_line' );
		return VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s ORDER BY row_no ASC", (string) $ma_da ) );
	}

	private static function next_row( $ma_da ) {
		global $wpdb;
		$t   = VHCP_DB::t( 'da_line' );
		$max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(row_no) FROM $t WHERE ma_da=%s", (string) $ma_da ) );
		return max( self::DATA_ROW, $max + 1 );
	}

	/** Dòng "có nội dung": bỏ hàng trống hoàn toàn (giống điều kiện lọc của app cũ). */
	private static function is_real( $r ) {
		return ! ( trim( (string) $r['noi_dung'] ) === '' && ! ( VHCP_Util::num( $r['du_toan'] ) || VHCP_Util::num( $r['thuc_te'] ) ) );
	}

	// ---------------------------------------------------------------- tạo / danh sách

	public static function create_du_an( $loai, $ten, $nguoi ) {
		global $wpdb;
		$loai = trim( (string) $loai );
		if ( $loai === 'Chi phí cơ sở' ) { return self::ensure_co_so_chung( $nguoi ); }
		$ten = VHCP_Util::san( $ten );
		if ( ! in_array( $loai, array( 'Tháo dỡ', 'Setup lắp đặt' ), true ) ) { return VHCP_Util::err( 'Loại không hợp lệ' ); }
		if ( $ten === '' ) { return VHCP_Util::err( 'Nhập tên dự án' ); }
		$ma = VHCP_Util::uid( 'DA' );
		$wpdb->insert( VHCP_DB::t( 'da_index' ), array(
			'ma_da'      => $ma,
			'ten'        => $ten,
			'loai'       => $loai,
			'trang_thai' => 'Đang làm',
			'ngay_tao'   => VHCP_Util::now_sql(),
			'nguoi_tao'  => (string) $nguoi,
		) );
		return VHCP_Util::ok( array( 'maDA' => $ma, 'ten' => $ten, 'loai' => $loai, 'sheet' => '', 'trangThai' => 'Đang làm', 'url' => '' ) );
	}

	/** ensureCoSoChung(): chi phí cơ sở kỹ thuật = 1 "sheet" CHUNG duy nhất, xuyên suốt. */
	public static function ensure_co_so_chung( $nguoi ) {
		global $wpdb;
		$t = VHCP_DB::t( 'da_index' );
		$r = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE loai=%s ORDER BY stt ASC LIMIT 1", 'Chi phí cơ sở' ) );
		if ( $r ) {
			return VHCP_Util::ok( array(
				'maDA'      => $r['ma_da'],
				'ten'       => $r['ten'],
				'loai'      => 'Chi phí cơ sở',
				'sheet'     => '',
				'trangThai' => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Đang làm' ),
				'url'       => '',
			) );
		}
		$ma  = VHCP_Util::uid( 'DA' );
		$ten = 'Chi phí cơ sở (Kỹ thuật · chung)';
		$wpdb->insert( VHCP_DB::t( 'da_index' ), array(
			'ma_da'      => $ma,
			'ten'        => $ten,
			'loai'       => 'Chi phí cơ sở',
			'trang_thai' => 'Đang làm',
			'ngay_tao'   => VHCP_Util::now_sql(),
			'nguoi_tao'  => (string) $nguoi,
		) );
		return VHCP_Util::ok( array( 'maDA' => $ma, 'ten' => $ten, 'loai' => 'Chi phí cơ sở', 'sheet' => '', 'trangThai' => 'Đang làm', 'url' => '' ) );
	}

	public static function list_du_an() {
		$out = array();
		$sc_tong = VHCP_SoChi::tong_theo_du_an();   // 1 lệnh DB cho mọi dự án
		foreach ( self::all_with_lines() as $r ) {
			$lines = $r['lines'];
			$dt = 0; $tt = 0; $child = array();
			foreach ( $lines as $x ) {
				$cap = trim( (string) $x['cap_cha'] );
				if ( $cap !== '' && $cap !== '(Phát sinh)' ) { $child[ $cap ] = ( isset( $child[ $cap ] ) ? $child[ $cap ] : 0 ) + VHCP_Util::num( $x['thuc_te'] ); }
			}
			foreach ( $lines as $x ) {
				if ( ! self::is_real( $x ) ) { continue; }
				$nd  = trim( (string) $x['noi_dung'] );
				$cap = trim( (string) $x['cap_cha'] );
				if ( $cap === '' ) {
					$dt += VHCP_Util::num( $x['du_toan'] );
					if ( ! ( isset( $child[ $nd ] ) && $child[ $nd ] > 0 ) ) { $tt += VHCP_Util::num( $x['thuc_te'] ); }
				} else {
					$tt += VHCP_Util::num( $x['thuc_te'] );
				}
			}
			// Cộng thêm phần nằm ở SỔ CHI PHÍ mang mã dự án này (khớp theo mã hoặc theo tên)
			$sc = self::sc_cua( $sc_tong, $r['ma_da'], $r['ten'] );
			$out[] = array(
				'maDA'       => $r['ma_da'],
				'ten'        => $r['ten'],
				'loai'       => $r['loai'],
				'sheet'      => '',
				'trangThai'  => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Đang làm' ),
				'ngayTao'    => VHCP_Util::fmt( $r['ngay_tao'] ),
				'nguoi'      => $r['nguoi_tao'],
				'tongDuToan' => $dt + $sc['duToan'],
				'tongThucTe' => $tt + $sc['tien'],
				'soDongSoChi' => $sc['n'],
				'chenh'      => ( $tt + $sc['tien'] ) - ( $dt + $sc['duToan'] ),
				'url'        => '',
			);
		}
		$coso = array();
		foreach ( VHCP_Cfg::cfg_static()['coso'] as $x ) { $coso[] = $x['ten']; }
		return VHCP_Util::ok( array( 'items' => array_reverse( $out ), 'coso' => $coso ) );
	}

	/** Lấy phần sổ chi phí của 1 dự án trong bảng tổng (khớp theo mã dự án hoặc theo tên). */
	private static function sc_cua( $sc_tong, $ma_da, $ten ) {
		$k0 = array( 'tien' => 0, 'duToan' => 0, 'n' => 0 );
		foreach ( array( $ma_da, $ten ) as $key ) {
			$k = mb_strtolower( trim( (string) $key ) );
			if ( $k !== '' && isset( $sc_tong[ $k ] ) ) { return $sc_tong[ $k ]; }
		}
		return $k0;
	}

	public static function rename_du_an( $ma_da, $ten ) {
		global $wpdb;
		$ten = VHCP_Util::san( $ten );
		if ( $ten === '' ) { return VHCP_Util::err( 'Tên trống' ); }
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['loai'] === 'Chi phí cơ sở' ) { return VHCP_Util::err( 'Sheet Chi phí cơ sở chung không đổi tên' ); }
		$wpdb->update( VHCP_DB::t( 'da_index' ), array( 'ten' => $ten ), array( 'ma_da' => (string) $ma_da ) );
		return VHCP_Util::ok( array( 'ten' => $ten ) );
	}

	// ---------------------------------------------------------------- chi tiết

	public static function get_du_an( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }

		$lines = array();
		foreach ( self::lines_of( $ma_da ) as $r ) {
			if ( ! self::is_real( $r ) ) { continue; }
			$lines[] = array(
				'row'       => (int) $r['row_no'],
				'noiDung'   => (string) $r['noi_dung'],
				'duToan'    => VHCP_Util::num( $r['du_toan'] ),
				'thucTe'    => VHCP_Util::num( $r['thuc_te'] ),
				'soLuong'   => VHCP_Util::num( $r['so_luong'] ),
				'donGia'    => VHCP_Util::num( $r['don_gia'] ),
				'thanhTien' => VHCP_Util::num( $r['thanh_tien'] ),
				'vat'       => (string) $r['vat'],
				'anh'       => (string) $r['anh'],
				'gian'      => (string) $r['gian'],
				'note'      => (string) $r['note'],
				'capCha'    => trim( (string) $r['cap_cha'] ),
				'hinhThuc'  => trim( (string) $r['hinh_thuc'] ),
				'hoSo'      => trim( (string) $r['ho_so'] ),
				'loaiCp'    => (string) $r['loai_cp'],
				'tkNo'      => (string) $r['tk_no'],
				'tkCo'      => (string) $r['tk_co'],
				'maDt'      => (string) $r['ma_dt'],
			);
		}

		$child_tt = array(); $parent_ht = array();
		foreach ( $lines as $l ) {
			if ( $l['capCha'] !== '' && $l['capCha'] !== '(Phát sinh)' ) { $child_tt[ $l['capCha'] ] = ( isset( $child_tt[ $l['capCha'] ] ) ? $child_tt[ $l['capCha'] ] : 0 ) + $l['thucTe']; }
			if ( $l['capCha'] === '' ) { $parent_ht[ $l['noiDung'] ] = $l['hinhThuc']; }
		}
		foreach ( $lines as $i => $l ) {
			if ( $l['capCha'] !== '' && $l['capCha'] !== '(Phát sinh)' && isset( $parent_ht[ $l['capCha'] ] ) && $parent_ht[ $l['capCha'] ] !== '' ) {
				$lines[ $i ]['hinhThuc'] = $parent_ht[ $l['capCha'] ];
			}
		}

		$dt = 0; $tt = 0; $du_tu = 0; $du_tt = 0; $tt_tu = 0; $tt_tt = 0; $tt_vat = 0; $tt_novat = 0;
		foreach ( $lines as $l ) {
			$has_child = ( isset( $child_tt[ $l['noiDung'] ] ) && $child_tt[ $l['noiDung'] ] > 0 );
			$is_pay    = ( $l['capCha'] !== '' ) || ( $l['capCha'] === '' && ! $has_child );
			$is_tt     = ( $l['hinhThuc'] === 'Trực tiếp' );
			if ( $l['capCha'] === '' ) {
				$dt += $l['duToan'];
				if ( ! $has_child ) { $tt += $l['thucTe']; }
				if ( $is_tt ) { $du_tt += $l['duToan']; } else { $du_tu += $l['duToan']; }
			} else {
				$tt += $l['thucTe'];
			}
			if ( $is_pay ) {
				if ( $is_tt ) {
					$tt_tt += $l['thucTe'];
					if ( mb_strpos( (string) $l['vat'], 'Có' ) !== false ) { $tt_vat += $l['thucTe']; } else { $tt_novat += $l['thucTe']; }
				} else {
					$tt_tu += $l['thucTe'];
				}
			}
		}

		// Phần nằm ở sổ chi phí: khớp theo MÃ dự án, không có thì khớp theo TÊN
		$sc_lines = VHCP_SoChi::theo_du_an( (string) $ma_da );
		if ( ! count( $sc_lines ) ) { $sc_lines = VHCP_SoChi::theo_du_an( (string) $f['ten'] ); }
		$sc_tien = 0; $sc_du_toan = 0;
		foreach ( $sc_lines as $x ) {
			$sc_tien    += VHCP_Util::num( $x['soTien'] );
			$sc_du_toan += VHCP_Util::num( $x['duToan'] );
		}

		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		return VHCP_Util::ok( array(
			'maDA'            => (string) $ma_da,
			'ten'             => $f['ten'],
			'loai'            => $f['loai'],
			'trangThai'       => $st,
			'url'             => '',
			'isCoSo'          => ( $f['loai'] === 'Chi phí cơ sở' ),
			// Dự án chi trực tiếp: chỉ còn 2 trạng thái thật là Đang làm / Đã đóng.
			// Chưa đóng thì nhập được — không còn khoá theo bước duyệt tạm ứng.
			'editable'        => ( $st !== 'Đã đóng' ),
			'pending'         => false,
			'approved'        => ( $st !== 'Đã đóng' ),
			'thiCong'         => ( $st !== 'Đã đóng' ),
			'closed'          => ( $st === 'Đã đóng' ),
			'lines'           => $lines,
			// Dòng chi của dự án nay nằm ở SỔ CHI PHÍ (mang mã dự án). Không cộng vào đây
			// thì gian nào cũng hiện 0đ dù dữ liệu đã nạp xong.
			'soChi'           => $sc_lines,
			'tongSoChi'       => $sc_tien,
			// Tách riêng để màn dự án ghi rõ tổng gồm những gì — đối chiếu hệ cũ mới biết
			// lệch ở bảng hạng mục hay ở sổ chi phí.
			'duToanSoChi'     => $sc_du_toan,
			'tongDuToan'      => $dt + $sc_du_toan,
			'tongThucTe'      => $tt + $sc_tien,
			'chenh'           => ( $tt + $sc_tien ) - ( $dt + $sc_du_toan ),
			'canTamUng'       => $du_tu,
			'traTrucTiep'     => $du_tt,
			'ttTamUng'        => $tt_tu,
			'ttTrucTiep'      => $tt_tt,
			'ttTrucTiepVAT'   => $tt_vat,
			'ttTrucTiepNoVAT' => $tt_novat,
			'thieuTamUng'     => $tt_tu - $du_tu,
			'thieuTrucTiep'   => $tt_tt - $du_tt,
			'pay'             => self::get_pay( $ma_da ),
			'noiDungList'     => self::nd_list( $f['loai'] ),
		) );
	}

	// ---------------------------------------------------------------- gợi ý hạng mục con

	private static function nd_list( $loai ) {
		$o = VHCP_Meta::get_json( 'da_ndlist_v1', array() );
		return isset( $o[ $loai ] ) ? $o[ $loai ] : array();
	}

	private static function push_nd( $loai, $nd ) {
		$nd = trim( (string) $nd );
		if ( $nd === '' ) { return; }
		$o = VHCP_Meta::get_json( 'da_ndlist_v1', array() );
		$a = isset( $o[ $loai ] ) ? (array) $o[ $loai ] : array();
		$low = mb_strtolower( $nd );
		foreach ( $a as $x ) { if ( mb_strtolower( (string) $x ) === $low ) { return; } }
		array_unshift( $a, $nd );
		if ( count( $a ) > 300 ) { $a = array_slice( $a, 0, 300 ); }
		$o[ $loai ] = $a;
		VHCP_Meta::set_json( 'da_ndlist_v1', $o );
	}

	// ---------------------------------------------------------------- kế toán chi tiền

	public static function get_pay( $ma_da ) {
		return VHCP_Meta::get_json( 'daPay_' . $ma_da, array() );
	}

	public static function approve_date( $ma_da ) {
		return (string) VHCP_Meta::get( 'daApp_' . $ma_da, '' );
	}

	public static function confirm_pay( $ma_da, $phase, $loai, $amount, $nguoi ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( ! in_array( (string) $phase, array( 'tamUng', 'quyetToan' ), true ) ) { return VHCP_Util::err( 'Giai đoạn không hợp lệ' ); }
		if ( ! in_array( (string) $loai, array( 'tu', 'tt' ), true ) ) { return VHCP_Util::err( 'Loại chi không hợp lệ' ); }
		$st = (string) $f['trang_thai'];
		if ( $st !== 'Đã duyệt' && $st !== 'Đã đóng' ) { return VHCP_Util::err( 'Chỉ chi tiền khi dự án đã kế toán duyệt tạm ứng' ); }
		$p = self::get_pay( $ma_da );
		if ( ! isset( $p[ $phase ] ) || ! is_array( $p[ $phase ] ) ) { $p[ $phase ] = array(); }
		$p[ $phase ][ $loai ] = array(
			'done'   => true,
			'amount' => VHCP_Util::num( $amount ),
			'date'   => VHCP_Util::now()->format( 'd/m/Y H:i' ),
			'by'     => (string) $nguoi,
		);
		VHCP_Meta::set_json( 'daPay_' . $ma_da, $p );
		return VHCP_Util::ok( array( 'pay' => $p ) );
	}

	public static function unconfirm_pay( $ma_da, $phase, $loai ) {
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$p = self::get_pay( $ma_da );
		if ( isset( $p[ $phase ][ $loai ] ) ) { unset( $p[ $phase ][ $loai ] ); }
		VHCP_Meta::set_json( 'daPay_' . $ma_da, $p );
		return VHCP_Util::ok( array( 'pay' => $p ) );
	}

	// ---------------------------------------------------------------- dòng hạng mục

	private static function line_data( $rec ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };
		$sl  = VHCP_Util::num( $g( 'soLuong' ) );
		$dg  = VHCP_Util::num( $g( 'donGia' ) );
		$cap = VHCP_Util::st( $g( 'capCha' ) );
		// Gắn mã tài khoản theo LOẠI CHI PHÍ ngay lúc nhập (giống sổ chi phí).
		$loai_cp = VHCP_Util::st( $g( 'loaiCp' ) );
		// Gian hàng đóng vai "cơ sở" ở mảng kỹ thuật -> mã theo mảng kinh doanh của gian đó.
		$tk      = VHCP_Cfg::resolve_tk( $loai_cp, VHCP_Util::st( $g( 'hinhThuc' ) ), array( 'tkNo' => VHCP_Util::st( $g( 'tkNo' ) ), 'tkCo' => VHCP_Util::st( $g( 'tkCo' ) ), 'maDt' => VHCP_Util::st( $g( 'maDt' ) ) ), VHCP_Util::st( $g( 'gian' ) ) );
		return array(
			'loai_cp'    => $loai_cp,
			'tk_no'      => $loai_cp !== '' ? $tk['tk_no'] : '',
			'tk_co'      => $loai_cp !== '' ? $tk['tk_co'] : '',
			'ma_dt'      => $loai_cp !== '' ? $tk['ma_dt'] : '',
			'noi_dung'   => VHCP_Util::st( $g( 'noiDung' ) ),
			'du_toan'    => ( $cap === '' ) ? VHCP_Util::num( $g( 'duToan' ) ) : 0,   // chỉ hạng mục lớn có dự toán
			'thuc_te'    => VHCP_Util::num( $g( 'thucTe' ) ),
			'so_luong'   => $sl,
			'don_gia'    => $dg,
			'thanh_tien' => $sl * $dg,
			'vat'        => VHCP_Util::st( $g( 'vat' ) ),
			'anh'        => VHCP_Util::st( $g( 'anh' ) ),
			'gian'       => VHCP_Util::st( $g( 'gian' ) ),
			'note'       => VHCP_Util::st( $g( 'note' ) ),
			'cap_cha'    => $cap,
			'hinh_thuc'  => VHCP_Util::st( $g( 'hinhThuc' ) ),
			'ho_so'      => VHCP_Util::st( $g( 'hoSo' ) ),
		);
	}

	public static function add_line( $ma_da, $rec ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		// Dự án chi trực tiếp: chỉ ĐÃ ĐÓNG mới khóa. Trước đây còn chặn cả trạng thái
		// "Chờ kế toán duyệt" của luồng duyệt đã bỏ, làm gian cũ không nhận được dữ liệu.
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( $st === 'Đã đóng' ) {
			return VHCP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi nhập' );
		}
		$data           = self::line_data( $rec );
		$data['ma_da']  = (string) $ma_da;
		$data['row_no'] = self::next_row( $ma_da );
		$wpdb->insert( VHCP_DB::t( 'da_line' ), $data );
		self::push_nd( $f['loai'], $data['noi_dung'] );
		return VHCP_Util::ok();
	}

	public static function update_line( $ma_da, $row, $rec ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( $st === 'Đã đóng' ) { return VHCP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi sửa' ); }
		$row = (int) $row;
		if ( $row < self::DATA_ROW ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$t   = VHCP_DB::t( 'da_line' );
		$cur = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, $row ) );
		if ( ! $cur ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$old_name = trim( (string) $cur['noi_dung'] );
		$data     = self::line_data( $rec );
		$wpdb->update( $t, $data, array( 'ma_da' => (string) $ma_da, 'row_no' => $row ) );
		if ( $data['cap_cha'] === '' && $old_name !== '' && $old_name !== $data['noi_dung'] ) {
			self::relink_children( $ma_da, $old_name, $data['noi_dung'] );   // hạng mục lớn đổi tên -> cập nhật mục con
		}
		self::push_nd( $f['loai'], $data['noi_dung'] );
		return VHCP_Util::ok();
	}

	public static function delete_line( $ma_da, $row ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( $st === 'Đã đóng' ) { return VHCP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi xóa' ); }
		$row = (int) $row;
		if ( $row < self::DATA_ROW ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$t   = VHCP_DB::t( 'da_line' );
		$cur = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, $row ) );
		if ( ! $cur ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$nm  = trim( (string) $cur['noi_dung'] );
		$cap = trim( (string) $cur['cap_cha'] );
		if ( $cap === '' && $nm !== '' ) { self::relink_children( $ma_da, $nm, '(Phát sinh)' ); }   // xóa hạng mục lớn -> mục con thành phát sinh
		$wpdb->delete( $t, array( 'ma_da' => (string) $ma_da, 'row_no' => $row ) );
		return VHCP_Util::ok();
	}

	private static function relink_children( $ma_da, $old, $new ) {
		global $wpdb;
		$t = VHCP_DB::t( 'da_line' );
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET cap_cha=%s WHERE ma_da=%s AND TRIM(cap_cha)=%s", (string) $new, (string) $ma_da, (string) $old ) );
	}

	// ---------------------------------------------------------------- quy trình

	private static function set_status( $ma_da, $status ) {
		global $wpdb;
		$wpdb->update( VHCP_DB::t( 'da_index' ), array( 'trang_thai' => (string) $status ), array( 'ma_da' => (string) $ma_da ) );
	}

	public static function submit( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['loai'] === 'Chi phí cơ sở' ) { return VHCP_Util::err( 'Chi phí cơ sở chung không cần duyệt' ); }
		if ( (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' ) !== 'Đang làm' ) { return VHCP_Util::err( 'Chỉ gửi khi đang làm' ); }
		self::set_status( $ma_da, 'Chờ kế toán duyệt' );
		return VHCP_Util::ok();
	}

	public static function approve( $ma_da, $nguoi ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['trang_thai'] !== 'Chờ kế toán duyệt' ) { return VHCP_Util::err( 'Dự án không ở trạng thái chờ duyệt' ); }
		self::set_status( $ma_da, 'Đã duyệt' );
		VHCP_Meta::set( 'daApp_' . $ma_da, VHCP_Util::now()->format( 'd/m/Y' ) );   // ngày chứng từ khi xuất MISA
		return VHCP_Util::ok();
	}

	public static function ret( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['trang_thai'] !== 'Chờ kế toán duyệt' ) { return VHCP_Util::err( 'Chỉ trả khi đang chờ duyệt' ); }
		self::set_status( $ma_da, 'Đang làm' );
		return VHCP_Util::ok();
	}

	/**
	 * Đóng dự án. Dự án CHI TRỰC TIẾP — không có bước xin/duyệt tạm ứng như đơn tuần,
	 * nên đang làm là đóng được luôn, không đòi phải "Đã duyệt" trước.
	 */
	public static function close( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['loai'] === 'Chi phí cơ sở' ) { return VHCP_Util::err( 'Chi phí cơ sở chung không đóng' ); }
		if ( (string) $f['trang_thai'] === 'Đã đóng' ) { return VHCP_Util::err( 'Dự án đã đóng' ); }
		self::set_status( $ma_da, 'Đã đóng' );
		return VHCP_Util::ok();
	}

	public static function reopen( $ma_da ) {
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		self::set_status( $ma_da, 'Đang làm' );
		return VHCP_Util::ok();
	}

	public static function delete( $ma_da ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['loai'] === 'Chi phí cơ sở' ) { return VHCP_Util::err( 'Không xóa sheet Chi phí cơ sở chung' ); }
		if ( (string) $f['trang_thai'] === 'Đã đóng' ) { return VHCP_Util::err( 'Dự án đã đóng — Admin "Mở lại" trước khi xóa' ); }
		$wpdb->delete( VHCP_DB::t( 'da_line' ), array( 'ma_da' => (string) $ma_da ) );
		$wpdb->delete( VHCP_DB::t( 'da_index' ), array( 'ma_da' => (string) $ma_da ) );
		VHCP_Meta::del( 'daPay_' . $ma_da );
		VHCP_Meta::del( 'daApp_' . $ma_da );
		return VHCP_Util::ok();
	}

	/** Loại dự án -> loại chi phí tương ứng trong danh mục (tên trùng khớp, không phải đoán). */
	public static function loai_cp_mac_dinh( $loai_du_an ) {
		$map = array(
			'Tháo dỡ'       => 'Chi phí tháo dỡ',
			'Setup lắp đặt' => 'Chi phí setup lắp đặt gian hàng mới',
			'Chi phí cơ sở' => 'Chi phí cơ sở',
		);
		$k = trim( (string) $loai_du_an );
		return isset( $map[ $k ] ) ? $map[ $k ] : '';
	}

	/**
	 * Gán loại chi phí + mã tài khoản cho dòng hạng mục CŨ.
	 * Dòng chưa có loại -> lấy theo loại dự án (Tháo dỡ / Setup lắp đặt / Chi phí cơ sở).
	 */
	public static function gan_ma_tai_khoan( $all = false ) {
		global $wpdb;
		$t = VHCP_DB::t( 'da_line' );
		$n = 0; $thieu = array(); $khong_suy = 0;
		foreach ( self::all_with_lines() as $p ) {
			$mac_dinh  = self::loai_cp_mac_dinh( $p['loai'] );
			$parent_ht = array();
			foreach ( $p['lines'] as $x ) {
				if ( trim( (string) $x['cap_cha'] ) === '' ) { $parent_ht[ trim( (string) $x['noi_dung'] ) ] = trim( (string) $x['hinh_thuc'] ); }
			}
			foreach ( $p['lines'] as $x ) {
				$cu   = trim( (string) $x['loai_cp'] );
				$loai = ( $cu !== '' ) ? $cu : $mac_dinh;
				if ( $loai === '' ) { $khong_suy++; continue; }
				if ( ! $all && $cu !== '' && trim( (string) $x['tk_no'] ) !== '' ) { continue; }
				$cap = trim( (string) $x['cap_cha'] );
				$ht  = ( $cap !== '' && $cap !== '(Phát sinh)' && ! empty( $parent_ht[ $cap ] ) ) ? $parent_ht[ $cap ] : trim( (string) $x['hinh_thuc'] );
				$gian_x = trim( (string) $x['gian'] );
				$giu    = VHCP_Cfg::ma_con_hop_le( $loai, $gian_x, $x['tk_no'] );
				$tk  = VHCP_Cfg::resolve_tk( $loai, $ht, array( 'tkNo' => $giu ), $gian_x );
				if ( $tk['tk_no'] === '' ) { $thieu[ $loai ] = 1; }
				if ( $loai === $cu && $tk['tk_no'] === trim( (string) $x['tk_no'] ) && $tk['tk_co'] === trim( (string) $x['tk_co'] ) ) { continue; }
				$wpdb->update( $t, array( 'loai_cp' => $loai, 'tk_no' => $tk['tk_no'], 'tk_co' => $tk['tk_co'], 'ma_dt' => $tk['ma_dt'] ), array( 'id' => (int) $x['id'] ) );
				$n++;
			}
		}
		return VHCP_Util::ok( array( 'updated' => $n, 'thieuMa' => array_keys( $thieu ), 'khongSuyDuoc' => $khong_suy ) );
	}

	/**
	 * Dùng chung cho báo cáo: mọi dự án kèm dòng hạng mục — ĐÚNG 2 LỆNH DB
	 * (1 lệnh danh mục + 1 lệnh toàn bộ dòng, rồi gom trong PHP).
	 * Trước đây mỗi dự án 1 lệnh nên 30 dự án là 31 lệnh.
	 */
	public static function all_with_lines() {
		$ti = VHCP_DB::t( 'da_index' );
		$tl = VHCP_DB::t( 'da_line' );
		$rows = VHCP_DB::rows( "SELECT * FROM $ti ORDER BY stt ASC" );
		$by   = array();
		foreach ( VHCP_DB::rows( "SELECT * FROM $tl ORDER BY ma_da ASC, row_no ASC" ) as $l ) {
			$by[ (string) $l['ma_da'] ][] = $l;
		}
		foreach ( $rows as $i => $r ) {
			$k = (string) $r['ma_da'];
			$rows[ $i ]['lines'] = isset( $by[ $k ] ) ? $by[ $k ] : array();
		}
		return $rows;
	}

	/** Toàn bộ ghi nhận chi tiền của mọi dự án — 1 lệnh DB. */
	public static function pay_map() {
		$out = array();
		foreach ( VHCP_Meta::get_prefix( 'daPay_' ) as $k => $v ) {
			$o = json_decode( (string) $v, true );
			$out[ substr( $k, 7 ) ] = is_array( $o ) ? $o : array();
		}
		return $out;
	}

	/** Ngày kế toán duyệt của mọi dự án — 1 lệnh DB. */
	public static function approve_date_map() {
		$out = array();
		foreach ( VHCP_Meta::get_prefix( 'daApp_' ) as $k => $v ) {
			$out[ substr( $k, 7 ) ] = (string) $v;
		}
		return $out;
	}
}
