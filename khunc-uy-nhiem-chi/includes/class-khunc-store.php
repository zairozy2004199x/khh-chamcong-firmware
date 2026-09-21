<?php
/**
 * KHO DỮ LIỆU — đọc/ghi các thứ dùng chung cho cả bộ phận.
 *
 * Danh sách khoản (recs) đi lên máy chủ THEO TỪNG MẺ. File thật khoảng 3.400 dòng ~2,5 MB JSON,
 * mà `post_max_size` của hosting hay đặt 2–8 MB: đẩy một phát là gặp hosting nào chặt tay hơn
 * thì request bị cắt cụt, PHP nhận `$_POST` RỖNG và không có lỗi nào cả — người dùng chỉ thấy
 * "nhập xong" rồi mất sạch dữ liệu. Nên: mẻ nào lên xong ghi vào một hàng `recs_pN` riêng,
 * mẻ cuối mới gộp lại thành `recs` và mới thay dữ liệu đang chạy. Hỏng giữa chừng thì dữ liệu
 * cũ còn nguyên.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHUNC_Store {

	const MAX_KHOAN = 200000; // chặn trên, đề phòng lời gọi hỏng đẩy vô hạn

	/* ---------------------------------------------------------------- kho khối JSON */

	private static function kho_get( $khoa, $mac_dinh = null ) {
		global $wpdb;
		$t = KHUNC_DB::t( 'kho' );
		$s = $wpdb->get_var( $wpdb->prepare( "SELECT du_lieu FROM $t WHERE khoa=%s", $khoa ) );
		if ( $s === null ) { return $mac_dinh; }
		$j = json_decode( (string) $s, true );
		return $j === null ? $mac_dinh : $j;
	}

	/**
	 * Ghi đè một khối. Dùng $wpdb->replace() chứ không phải cú pháp ON DUPLICATE KEY của MySQL:
	 * replace() là API chuẩn của WordPress, đọc ra ngay, và chạy được cả trên bộ giả lập ở
	 * tools/dev/ nên kiểm thử tự động mới chạm được vào đường ghi này.
	 */
	private static function kho_set( $khoa, $gia_tri ) {
		global $wpdb;
		$t  = KHUNC_DB::t( 'kho' );
		$pb = (int) $wpdb->get_var( $wpdb->prepare( "SELECT phien_ban FROM $t WHERE khoa=%s", $khoa ) );
		$wpdb->replace( $t, array(
			'khoa'           => $khoa,
			'phien_ban'      => $pb + 1,
			'cap_nhat_luc'   => KHUNC_Util::now_sql(),
			'nguoi_cap_nhat' => KHUNC_Auth::ten(),
			'du_lieu'        => wp_json_encode( $gia_tri ),
		) );
	}

	private static function kho_del( $khoa ) {
		global $wpdb;
		return (int) $wpdb->delete( KHUNC_DB::t( 'kho' ), array( 'khoa' => $khoa ) );
	}

	/* ---------------------------------------------------------------- đọc tất cả */

	public static function lay_tat_ca() {
		return KHUNC_Util::ok( array(
			'recs'    => self::kho_get( 'recs', array() ),
			'nhatKy'  => self::kho_get( 'nhatKy', array() ),
			'congTy'  => self::kho_get( 'congTy', null ),
			'tuyChon' => self::kho_get( 'tuyChon', null ),
			'theoDoi' => self::lay_theo_doi(),
			'capNhat' => self::cap_nhat_cuoi(),
		) );
	}

	private static function cap_nhat_cuoi() {
		global $wpdb;
		$t = KHUNC_DB::t( 'kho' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT cap_nhat_luc, nguoi_cap_nhat FROM $t WHERE khoa=%s", 'recs' ), ARRAY_A );
		if ( ! $r ) { return null; }
		return array( 'luc' => KHUNC_Util::iso( $r['cap_nhat_luc'] ), 'nguoi' => (string) $r['nguoi_cap_nhat'] );
	}

	/* ---------------------------------------------------------------- danh sách khoản */

	/**
	 * Nhận một mẻ khoản. Gộp dần vào khối tạm `recs_tam`, mẻ cuối mới thay `recs` đang chạy —
	 * đứt mạng hay hosting cắt giữa chừng thì dữ liệu cũ còn nguyên, không mất gì.
	 *
	 * Gộp bằng PHP chứ không quét bảng bằng `LIKE 'recs\_p%'`: dấu gạch dưới là ký tự đại diện
	 * trong LIKE nên phải escape, mà cách escape lại khác nhau giữa MySQL và SQLite — chỗ đó đã
	 * âm thầm trả về 0 hàng một lần rồi. Không có LIKE thì không có chỗ cho loại lỗi đó.
	 *
	 * @param array $recs    một mẻ khoản
	 * @param mixed $nhat_ky nhật ký đọc file (chỉ mẻ cuối mới gửi)
	 * @param bool  $dau     mẻ đầu — bỏ khối tạm dở dang của lần nhập trước
	 * @param bool  $cuoi    mẻ cuối — thay dữ liệu đang chạy
	 */
	public static function dat_danh_sach( $recs, $nhat_ky, $dau, $cuoi ) {
		if ( ! is_array( $recs ) ) { return KHUNC_Util::err( 'Dữ liệu gửi lên không phải danh sách.' ); }

		$gop = $dau ? array() : (array) self::kho_get( 'recs_tam', array() );
		$gop = array_merge( $gop, $recs );

		if ( count( $gop ) > self::MAX_KHOAN ) {
			self::kho_del( 'recs_tam' );
			return KHUNC_Util::err( 'Quá ' . self::MAX_KHOAN . ' khoản — huỷ lần nhập này.' );
		}

		if ( ! $cuoi ) {
			self::kho_set( 'recs_tam', $gop );
			return KHUNC_Util::ok( array( 'daNhan' => count( $gop ) ) );
		}

		self::kho_set( 'recs', $gop );
		self::kho_set( 'nhatKy', is_array( $nhat_ky ) ? $nhat_ky : array() );
		self::kho_del( 'recs_tam' );

		self::log( 'nhap_excel', count( $gop ) . ' khoản' );
		return KHUNC_Util::ok( array( 'soKhoan' => count( $gop ) ) );
	}

	/* ---------------------------------------------------------------- trạng thái đánh dấu */

	public static function lay_theo_doi() {
		global $wpdb;
		$t    = KHUNC_DB::t( 'theo_doi' );
		$rows = $wpdb->get_results( "SELECT * FROM $t", ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (string) $r['id'] ] = array(
				'trangThai'    => (string) $r['trang_thai'],
				'ngayDi'       => $r['ngay_di'] && $r['ngay_di'] !== '0000-00-00' ? (string) $r['ngay_di'] : '',
				'soUNC'        => (string) $r['so_unc'],
				'nguoiCapNhat' => (string) $r['nguoi_cap_nhat'],
				'capNhatLuc'   => KHUNC_Util::iso( $r['cap_nhat_luc'] ),
				'ghiChu'       => (string) $r['ghi_chu'],
			);
		}
		return (object) $out; // object để JSON ra {} chứ không phải [] khi rỗng
	}

	private static function ngay( $v ) {
		$s = trim( (string) $v );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : null;
	}

	public static function dat_theo_doi( $items ) {
		global $wpdb;
		if ( ! is_array( $items ) ) { return KHUNC_Util::err( 'Thiếu danh sách đánh dấu.' ); }
		$hop_le = array( 'chua_lenh', 'da_lenh', 'da_di', 'huy' );
		$t      = KHUNC_DB::t( 'theo_doi' );
		$n      = 0;
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) { continue; }
			$id = KHUNC_Util::s( isset( $it['id'] ) ? $it['id'] : '', 48 );
			$tt = KHUNC_Util::s( isset( $it['trangThai'] ) ? $it['trangThai'] : '', 20 );
			if ( $id === '' || ! in_array( $tt, $hop_le, true ) ) { continue; }
			$ok = $wpdb->replace( $t, array(
				'id'             => $id,
				'trang_thai'     => $tt,
				'ngay_di'        => self::ngay( isset( $it['ngayDi'] ) ? $it['ngayDi'] : '' ),
				'so_unc'         => KHUNC_Util::s( isset( $it['soUNC'] ) ? $it['soUNC'] : '', 60 ),
				'nguoi_cap_nhat' => KHUNC_Auth::ten(),
				'cap_nhat_luc'   => KHUNC_Util::now_sql(),
				'ghi_chu'        => KHUNC_Util::s( isset( $it['ghiChu'] ) ? $it['ghiChu'] : '', 2000 ),
			) );
			// Đếm theo kết quả thật: báo "đã lưu 400" trong khi câu lệnh hỏng là cách
			// êm ái nhất để mất dữ liệu mà không ai biết.
			if ( $ok !== false ) { $n++; }
		}
		if ( $n ) { self::log( 'danh_dau', $n . ' khoản' ); }
		$gui = count( $items );
		if ( $n < $gui ) {
			return KHUNC_Util::err( 'Chỉ lưu được ' . $n . '/' . $gui . ' khoản: ' . $wpdb->last_error );
		}
		return KHUNC_Util::ok( array( 'daLuu' => $n ) );
	}

	public static function xoa_theo_doi( $ids ) {
		global $wpdb;
		if ( ! is_array( $ids ) || ! $ids ) { return KHUNC_Util::ok( array( 'daXoa' => 0 ) ); }
		$t    = KHUNC_DB::t( 'theo_doi' );
		$sach = array();
		foreach ( $ids as $id ) {
			$s = KHUNC_Util::s( $id, 48 );
			if ( $s !== '' ) { $sach[] = $s; }
		}
		if ( ! $sach ) { return KHUNC_Util::ok( array( 'daXoa' => 0 ) ); }
		$cho = implode( ',', array_fill( 0, count( $sach ), '%s' ) );
		$n   = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE id IN ($cho)", $sach ) );
		self::log( 'bo_danh_dau', $n . ' khoản' );
		return KHUNC_Util::ok( array( 'daXoa' => $n ) );
	}

	/* ---------------------------------------------------------------- cài đặt */

	public static function dat_cai_dat( $cong_ty, $tuy_chon ) {
		if ( is_array( $cong_ty ) ) { self::kho_set( 'congTy', $cong_ty ); }
		if ( is_array( $tuy_chon ) ) { self::kho_set( 'tuyChon', $tuy_chon ); }
		self::log( 'cai_dat', '' );
		return KHUNC_Util::ok();
	}

	public static function xoa_het() {
		$n = KHUNC_DB::xoa_du_lieu( true );
		self::log( 'xoa_het', $n . ' bản ghi' );
		return KHUNC_Util::ok( array( 'daXoa' => $n ) );
	}

	/* ---------------------------------------------------------------- nhật ký */

	public static function log( $hanh_dong, $chi_tiet = '' ) {
		self::log_as( KHUNC_Auth::ten(), KHUNC_Auth::vai(), $hanh_dong, $chi_tiet );
	}

	public static function log_as( $nguoi, $vai, $hanh_dong, $chi_tiet = '' ) {
		global $wpdb;
		$wpdb->insert( KHUNC_DB::t( 'nhat_ky' ), array(
			'luc'       => KHUNC_Util::now_sql(),
			'nguoi'     => KHUNC_Util::s( $nguoi, 120 ),
			'vai'       => KHUNC_Util::s( $vai, 40 ),
			'hanh_dong' => KHUNC_Util::s( $hanh_dong, 60 ),
			'chi_tiet'  => KHUNC_Util::s( $chi_tiet, 2000 ),
		) );
	}

	/** Vài con số cho màn quản trị WordPress. */
	public static function thong_ke() {
		global $wpdb;
		$recs = self::kho_get( 'recs', array() );
		$r    = $wpdb->get_row( $wpdb->prepare(
			'SELECT cap_nhat_luc, nguoi_cap_nhat FROM ' . KHUNC_DB::t( 'kho' ) . ' WHERE khoa=%s', 'recs' ), ARRAY_A );
		return array(
			'soKhoan'   => is_array( $recs ) ? count( $recs ) : 0,
			'soDanhDau' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHUNC_DB::t( 'theo_doi' ) ),
			'daDi'      => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHUNC_DB::t( 'theo_doi' ) . " WHERE trang_thai='da_di'" ),
			'nhapLuc'   => $r ? KHUNC_Util::iso( $r['cap_nhat_luc'] ) : '',
			'nhapBoi'   => $r ? (string) $r['nguoi_cap_nhat'] : '',
		);
	}

	public static function lay_nhat_ky( $a = array() ) {
		global $wpdb;
		$t    = KHUNC_DB::t( 'nhat_ky' );
		$n    = isset( $a['limit'] ) ? max( 1, min( 500, (int) $a['limit'] ) ) : 200;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $n ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'luc'      => KHUNC_Util::iso( $r['luc'] ),
				'nguoi'    => (string) $r['nguoi'],
				'vai'      => (string) $r['vai'],
				'hanhDong' => (string) $r['hanh_dong'],
				'chiTiet'  => (string) $r['chi_tiet'],
			);
		}
		return KHUNC_Util::ok( array( 'log' => $out ) );
	}

	/* ---------------------------------------------------------------- người dùng */

	public static function ds_nguoi_dung() {
		global $wpdb;
		$t    = KHUNC_DB::t( 'nguoi_dung' );
		$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY vai, ten", ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) { $out[] = KHUNC_Auth::out_user( $r ); }
		return KHUNC_Util::ok( array( 'users' => $out ) );
	}

	public static function luu_nguoi_dung( $u ) {
		global $wpdb;
		if ( ! is_array( $u ) ) { return KHUNC_Util::err( 'Thiếu thông tin người dùng.' ); }
		$t   = KHUNC_DB::t( 'nguoi_dung' );
		$id  = (int) ( isset( $u['id'] ) ? $u['id'] : 0 );
		$ten = KHUNC_Util::s( isset( $u['ten'] ) ? $u['ten'] : '', 120 );
		$vai = KHUNC_Util::s( isset( $u['vai'] ) ? $u['vai'] : '', 40 );
		$pin = trim( (string) ( isset( $u['pin'] ) ? $u['pin'] : '' ) );

		if ( $ten === '' ) { return KHUNC_Util::err( 'Chưa nhập tên.' ); }
		if ( ! in_array( $vai, KHUNC_Auth::VAI, true ) ) { return KHUNC_Util::err( 'Vai trò không hợp lệ.' ); }
		if ( $pin !== '' && ! preg_match( '/^\d{4,8}$/', $pin ) ) { return KHUNC_Util::err( 'PIN phải 4–8 chữ số.' ); }
		if ( $id === 0 && $pin === '' ) { return KHUNC_Util::err( 'Người dùng mới phải có PIN.' ); }
		if ( $pin !== '' ) {
			$trung = KHUNC_Auth::pin_trung( $pin, $id );
			if ( $trung ) { return KHUNC_Util::err( 'PIN này đã có người dùng — chọn số khác.' ); }
		}

		$data = array(
			'ten'       => $ten,
			'vai'       => $vai,
			'bo_phan'   => KHUNC_Util::s( isset( $u['boPhan'] ) ? $u['boPhan'] : '', 120 ),
			'email'     => KHUNC_Util::s( isset( $u['email'] ) ? $u['email'] : '', 190 ),
			'hoat_dong' => empty( $u['hoatDong'] ) ? 0 : 1,
			'sua_luc'   => KHUNC_Util::now_sql(),
		);
		if ( $pin !== '' ) { $data['pin_hash'] = password_hash( $pin, PASSWORD_DEFAULT ); }

		if ( $id > 0 ) {
			// Không để Admin cuối cùng tự hạ vai hoặc tự tắt — khoá luôn cửa vào.
			if ( $vai !== 'Admin' || 0 === (int) $data['hoat_dong'] ) {
				$con = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM $t WHERE vai='Admin' AND hoat_dong=1 AND id<>%d", $id ) );
				if ( $con === 0 ) { return KHUNC_Util::err( 'Phải còn ít nhất một Admin đang hoạt động.' ); }
			}
			$wpdb->update( $t, $data, array( 'id' => $id ) );
		} else {
			$data['tao_luc'] = KHUNC_Util::now_sql();
			$wpdb->insert( $t, $data );
			$id = (int) $wpdb->insert_id;
		}
		self::log( 'luu_nguoi_dung', $ten . ' (' . $vai . ')' );
		return KHUNC_Util::ok( array( 'id' => $id ) );
	}

	public static function xoa_nguoi_dung( $id ) {
		global $wpdb;
		$id = (int) $id;
		$t  = KHUNC_DB::t( 'nguoi_dung' );
		$r  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", $id ), ARRAY_A );
		if ( ! $r ) { return KHUNC_Util::err( 'Không có người dùng này.' ); }
		if ( (string) $r['vai'] === 'Admin' ) {
			$con = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $t WHERE vai='Admin' AND hoat_dong=1 AND id<>%d", $id ) );
			if ( $con === 0 ) { return KHUNC_Util::err( 'Phải còn ít nhất một Admin đang hoạt động.' ); }
		}
		$wpdb->delete( $t, array( 'id' => $id ) );
		$wpdb->delete( KHUNC_DB::t( 'phien' ), array( 'user_id' => $id ) );
		self::log( 'xoa_nguoi_dung', (string) $r['ten'] );
		return KHUNC_Util::ok();
	}
}
