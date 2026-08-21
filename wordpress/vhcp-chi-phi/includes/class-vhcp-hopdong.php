<?php
/**
 * THƯ VIỆN HỢP ĐỒNG — lưu hợp đồng + bản scan ngay trên web, và nhắc cái sắp hết hạn.
 *
 * Chỉ Kế toán / Quản lý / Admin xem và sửa (chặn ở cửa API): hợp đồng mang giá và điều
 * khoản, không phải thứ để cả cơ sở đọc.
 *
 * File đính kèm dùng lại VHCP_Upload::upload_doc() (đã nhận PDF/Word/Excel/ảnh) và lưu
 * dạng JSON trong một cột — một hợp đồng thường chỉ vài file, tách bảng riêng chỉ thêm
 * một lần đọc mà không được gì.
 *
 * @package VHCP
 */

defined( 'ABSPATH' ) || exit;

class VHCP_HopDong {

	/** Mốc nhắc hạn (ngày). Đổi ở đây là đổi cả màn hợp đồng lẫn thẻ ở Tổng quan. */
	const MOC_NHAC = array( 30, 60, 90 );

	private static function t() {
		return VHCP_DB::t( 'hopdong' );
	}

	/** Hôm nay dạng yyyy-mm-dd (so sánh chuỗi được vì ngày lưu cùng dạng). */
	private static function hom_nay() {
		return VHCP_Util::today_sql();
	}

	/**
	 * Còn bao nhiêu ngày tới hạn. Âm = đã hết hạn. null = không có ngày hết hạn.
	 * Tính theo NGÀY tròn, không theo giờ: hợp đồng hết hạn "hôm nay" thì còn 0 ngày.
	 */
	public static function con_lai( $ngay_het ) {
		$h = trim( (string) $ngay_het );
		if ( $h === '' ) { return null; }
		$a = strtotime( self::hom_nay() . ' 00:00:00' );
		$b = strtotime( $h . ' 00:00:00' );
		if ( ! $a || ! $b ) { return null; }
		return (int) floor( ( $b - $a ) / 86400 );
	}

	/** Nhãn tình trạng hạn, để giao diện khỏi tự suy mỗi nơi một kiểu. */
	public static function tinh_trang_han( $ngay_het ) {
		$c = self::con_lai( $ngay_het );
		if ( $c === null ) { return array( 'ma' => 'khonghan', 'nhan' => 'Không có hạn', 'conLai' => null ); }
		if ( $c < 0 )      { return array( 'ma' => 'hethan',   'nhan' => 'Đã hết hạn ' . abs( $c ) . ' ngày', 'conLai' => $c ); }
		if ( $c === 0 )    { return array( 'ma' => 'homnay',   'nhan' => 'Hết hạn HÔM NAY', 'conLai' => 0 ); }
		foreach ( self::MOC_NHAC as $m ) {
			if ( $c <= $m ) { return array( 'ma' => 'con' . $m, 'nhan' => 'Còn ' . $c . ' ngày', 'conLai' => $c ); }
		}
		return array( 'ma' => 'conxa', 'nhan' => 'Còn ' . $c . ' ngày', 'conLai' => $c );
	}

	private static function files_ra( $v ) {
		$a = json_decode( (string) $v, true );
		if ( ! is_array( $a ) ) { return array(); }
		$out = array();
		foreach ( $a as $x ) {
			$x = (array) $x;
			$u = isset( $x['url'] ) ? trim( (string) $x['url'] ) : '';
			if ( $u === '' ) { continue; }
			$out[] = array(
				'url' => $u,
				'ten' => isset( $x['ten'] ) ? (string) $x['ten'] : basename( $u ),
			);
		}
		return $out;
	}

	private static function ra( $r ) {
		$tt = self::tinh_trang_han( $r['ngay_het'] );
		return array(
			'id'        => (string) $r['id'],
			'soHD'      => (string) $r['so_hd'],
			'ten'       => (string) $r['ten'],
			'doiTac'    => (string) $r['doi_tac'],
			'coso'      => (string) $r['coso'],
			'loaiHD'    => (string) $r['loai_hd'],
			'ngayKy'    => VHCP_Util::fmt( $r['ngay_ky'] ),
			'ngayHet'   => VHCP_Util::fmt( $r['ngay_het'] ),
			'ngayKySql' => (string) $r['ngay_ky'],
			'ngayHetSql'=> (string) $r['ngay_het'],
			'giaTri'    => VHCP_Util::out_num( $r['gia_tri'] ),
			'trangThai' => (string) $r['trang_thai'],
			'nguoiPT'   => (string) $r['nguoi_pt'],
			'ghiChu'    => (string) $r['ghi_chu'],
			'files'     => self::files_ra( $r['files'] ),
			'nguoiTao'  => (string) $r['nguoi_tao'],
			'taoLuc'    => VHCP_Util::fmt_dt( $r['tao_luc'] ),
			'han'       => $tt,
		);
	}

	/**
	 * listHopDong({coso, doiTac, trangThai, han, q})
	 *
	 * 'han' lọc theo tình trạng hạn: 'hethan' · 'con30' · 'con60' · 'con90' · '' (tất cả).
	 * Mấy mốc này là "còn <= N ngày VÀ chưa hết hạn" — hết hạn có ô riêng, không thì đếm
	 * lẫn vào nhau rồi tưởng còn thời gian xử.
	 */
	public static function list_hd( $opts = array() ) {
		$opts = (array) $opts;
		$g    = function ( $k ) use ( $opts ) { return isset( $opts[ $k ] ) ? trim( (string) $opts[ $k ] ) : ''; };
		$t    = self::t();

		$items = array();
		$dem   = array( 'tong' => 0, 'hetHan' => 0, 'homNay' => 0, 'con30' => 0, 'con60' => 0, 'con90' => 0, 'khongHan' => 0 );
		$tong_gt = 0;

		$f_cs = $g( 'coso' ); $f_dt = $g( 'doiTac' ); $f_tt = $g( 'trangThai' );
		$f_han = $g( 'han' ); $q = mb_strtolower( $g( 'q' ) );

		foreach ( VHCP_DB::rows( "SELECT * FROM $t ORDER BY (ngay_het = '') ASC, ngay_het ASC, stt DESC" ) as $r ) {
			$x = self::ra( $r );

			// Đếm luôn trên TOÀN BỘ, không theo bộ lọc: mấy ô đếm là để biết còn việc gì
			// phải xử, lọc rồi mới đếm thì lọc hẹp lại tưởng hết việc.
			$dem['tong']++;
			$c = $x['han']['conLai'];
			if ( $c === null )     { $dem['khongHan']++; }
			elseif ( $c < 0 )      { $dem['hetHan']++; }
			elseif ( $c === 0 )    { $dem['homNay']++; $dem['con30']++; $dem['con60']++; $dem['con90']++; }
			else {
				if ( $c <= 30 ) { $dem['con30']++; }
				if ( $c <= 60 ) { $dem['con60']++; }
				if ( $c <= 90 ) { $dem['con90']++; }
			}

			if ( $f_cs !== '' && $x['coso'] !== $f_cs ) { continue; }
			if ( $f_dt !== '' && $x['doiTac'] !== $f_dt ) { continue; }
			if ( $f_tt !== '' && $x['trangThai'] !== $f_tt ) { continue; }
			if ( $f_han !== '' ) {
				if ( $f_han === 'hethan' ) { if ( $c === null || $c >= 0 ) { continue; } }
				elseif ( $f_han === 'khonghan' ) { if ( $c !== null ) { continue; } }
				else {
					$m = (int) preg_replace( '/\D+/', '', $f_han );
					if ( $m <= 0 || $c === null || $c < 0 || $c > $m ) { continue; }
				}
			}
			if ( $q !== '' ) {
				$hay = mb_strtolower( $x['soHD'] . ' ' . $x['ten'] . ' ' . $x['doiTac'] . ' ' . $x['coso'] . ' ' . $x['loaiHD'] . ' ' . $x['nguoiPT'] . ' ' . $x['ghiChu'] );
				if ( mb_strpos( $hay, $q ) === false ) { continue; }
			}

			$items[]  = $x;
			$tong_gt += VHCP_Util::num( $x['giaTri'] );
		}

		// Danh sách cho ô lọc: lấy từ dữ liệu thật, khỏi phải khai ở đâu
		$cs = array(); $dt = array(); $lo = array();
		foreach ( VHCP_DB::rows( "SELECT DISTINCT coso, doi_tac, loai_hd FROM $t" ) as $r ) {
			if ( trim( (string) $r['coso'] ) !== '' )    { $cs[ (string) $r['coso'] ] = 1; }
			if ( trim( (string) $r['doi_tac'] ) !== '' ) { $dt[ (string) $r['doi_tac'] ] = 1; }
			if ( trim( (string) $r['loai_hd'] ) !== '' ) { $lo[ (string) $r['loai_hd'] ] = 1; }
		}

		return VHCP_Util::ok( array(
			'items'    => $items,
			'soDong'   => count( $items ),
			'tongGiaTri' => $tong_gt,
			'dem'      => $dem,
			'cosoList' => array_keys( $cs ),
			'doiTacList' => array_keys( $dt ),
			'loaiList' => array_keys( $lo ),
		) );
	}

	/** Hợp đồng sắp hết hạn / đã hết hạn — cho thẻ ở Tổng quan. */
	public static function sap_het_han( $trong_bao_nhieu_ngay = 90 ) {
		$n = (int) $trong_bao_nhieu_ngay;
		if ( $n <= 0 ) { $n = 90; }
		$t = self::t();
		$out = array();
		foreach ( VHCP_DB::rows( "SELECT * FROM $t WHERE ngay_het <> '' ORDER BY ngay_het ASC" ) as $r ) {
			$c = self::con_lai( $r['ngay_het'] );
			if ( $c === null || $c > $n ) { continue; }
			$out[] = self::ra( $r );
		}
		return $out;
	}

	public static function get_hd( $id ) {
		global $wpdb;
		$t = self::t();
		$r = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%s", (string) $id ) );
		if ( ! $r ) { return VHCP_Util::err( 'Không tìm thấy hợp đồng' ); }
		return VHCP_Util::ok( array( 'hd' => self::ra( $r ) ) );
	}

	/**
	 * saveHopDong(rec) — id trống thì thêm mới, có thì cập nhật.
	 *
	 * Bắt buộc TÊN hợp đồng. Số HĐ không bắt vì hợp đồng nhỏ nhiều khi không có số, nhưng
	 * có số thì kiểm TRÙNG — trùng số là lúc tra cứu không biết bản nào mới.
	 */
	public static function save_hd( $rec, $nguoi = '' ) {
		global $wpdb;
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? trim( (string) $rec[ $k ] ) : ''; };

		$ten = $g( 'ten' );
		if ( $ten === '' ) { return VHCP_Util::err( 'Nhập tên hợp đồng' ); }

		$id  = $g( 'id' );
		$t   = self::t();
		$so  = $g( 'soHD' );
		if ( $so !== '' ) {
			$trung = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $t WHERE so_hd=%s AND id<>%s LIMIT 1", $so, $id
			) );
			if ( $trung ) { return VHCP_Util::err( 'Số hợp đồng "' . $so . '" đã có ở một hợp đồng khác' ); }
		}

		$ky  = VHCP_Util::parse_date( $g( 'ngayKy' ) );
		$het = VHCP_Util::parse_date( $g( 'ngayHet' ) );
		// Sửa mà chỉ gửi 1 trong 2 ngày: đối chiếu với ngày đang lưu, không thì đổi ngày ký
		// lùi sau ngày hết hạn cũ mà app không thấy gì sai.
		if ( $id !== '' ) {
			$cu = VHCP_DB::row( $wpdb->prepare( "SELECT ngay_ky, ngay_het FROM $t WHERE id=%s", $id ) );
			if ( $cu ) {
				if ( ! array_key_exists( 'ngayKy', $rec ) )  { $ky  = trim( (string) $cu['ngay_ky'] ) !== '' ? (string) $cu['ngay_ky'] : null; }
				if ( ! array_key_exists( 'ngayHet', $rec ) ) { $het = trim( (string) $cu['ngay_het'] ) !== '' ? (string) $cu['ngay_het'] : null; }
			}
		}
		if ( $ky && $het && $het < $ky ) {
			return VHCP_Util::err( 'Ngày hết hạn (' . VHCP_Util::fmt( $het ) . ') trước ngày ký (' . VHCP_Util::fmt( $ky ) . ')' );
		}

		// File đính kèm: nhận cả mảng {url,ten} lẫn chuỗi url
		$files = array();
		foreach ( (array) ( isset( $rec['files'] ) ? $rec['files'] : array() ) as $f ) {
			if ( is_string( $f ) ) { $u = trim( $f ); $tn = basename( $u ); }
			else { $f = (array) $f; $u = isset( $f['url'] ) ? trim( (string) $f['url'] ) : ''; $tn = isset( $f['ten'] ) ? (string) $f['ten'] : basename( $u ); }
			if ( $u === '' ) { continue; }
			$files[] = array( 'url' => $u, 'ten' => $tn );
		}

		// CHỈ GHI NHỮNG Ô ĐƯỢC GỬI LÊN.
		//
		// Lời gọi có thể chỉ gửi vài ô (sửa số tiền chẳng hạn). Ghi cả mảng thì ô không gửi
		// bị xoá trắng — sửa giá hợp đồng xong là bay luôn ngày hết hạn, mà không có gì báo.
		// Thêm mới thì ngược lại: điền đủ để cột nào cũng có giá trị mặc định đàng hoàng.
		$co = function ( $k ) use ( $rec ) { return array_key_exists( $k, $rec ); };
		$moi = ( $id === '' );
		$data = array( 'ten' => $ten );
		if ( $moi || $co( 'soHD' ) )      { $data['so_hd']    = $so; }
		if ( $moi || $co( 'doiTac' ) )    { $data['doi_tac']  = $g( 'doiTac' ); }
		if ( $moi || $co( 'coso' ) )      { $data['coso']     = $g( 'coso' ); }
		if ( $moi || $co( 'loaiHD' ) )    { $data['loai_hd']  = $g( 'loaiHD' ); }
		if ( $moi || $co( 'ngayKy' ) )    { $data['ngay_ky']  = $ky ? $ky : ''; }
		if ( $moi || $co( 'ngayHet' ) )   { $data['ngay_het'] = $het ? $het : ''; }
		if ( $moi || $co( 'giaTri' ) )    { $data['gia_tri']  = VHCP_Util::blank_or_num( isset( $rec['giaTri'] ) ? $rec['giaTri'] : '' ); }
		if ( $moi || $co( 'nguoiPT' ) )   { $data['nguoi_pt'] = $g( 'nguoiPT' ); }
		if ( $moi || $co( 'ghiChu' ) )    { $data['ghi_chu']  = $g( 'ghiChu' ); }
		if ( $moi || $co( 'files' ) )     { $data['files']    = wp_json_encode( $files ); }
		if ( $moi || $co( 'trangThai' ) ) {
			$data['trang_thai'] = ( $g( 'trangThai' ) !== '' ? $g( 'trangThai' ) : 'Còn hiệu lực' );
		}

		if ( $id === '' ) {
			$id = VHCP_Util::uid( 'HD' );
			$data['id']        = $id;
			$data['nguoi_tao'] = (string) $nguoi;
			$data['tao_luc']   = VHCP_Util::now_sql();
			$wpdb->insert( $t, $data );
			$hanh = 'Thêm hợp đồng';
		} else {
			if ( ! VHCP_DB::row( $wpdb->prepare( "SELECT id FROM $t WHERE id=%s", $id ) ) ) {
				return VHCP_Util::err( 'Không tìm thấy hợp đồng để sửa' );
			}
			$wpdb->update( $t, $data, array( 'id' => $id ) );
			$hanh = 'Sửa hợp đồng';
		}

		VHCP_Log::log_action( array(
			'actor'  => (string) $nguoi,
			'action' => $hanh,
			'target' => ( $so !== '' ? $so . ' · ' : '' ) . $ten,
			'detail' => 'cơ sở: ' . $g( 'coso' ) . ' · đối tác: ' . $g( 'doiTac' )
				. ' · hết hạn: ' . ( $het ? VHCP_Util::fmt( $het ) : 'không có' )
				. ' · ' . count( $files ) . ' file',
		) );
		return VHCP_Util::ok( array( 'id' => $id ) );
	}

	/**
	 * Tải file hợp đồng lên — để riêng thư mục uploads/.../HopDong cho dễ sao lưu và dễ
	 * soát: bản scan hợp đồng không nên nằm lẫn với hồ sơ dự án.
	 */
	public static function upload_file( $data, $ma_hd = '' ) {
		return VHCP_Upload::upload_doc( $data, ( $ma_hd !== '' ? $ma_hd : 'HD' ), 'HopDong' );
	}

	public static function delete_hd( $id, $nguoi = '' ) {
		global $wpdb;
		$t = self::t();
		$r = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%s", (string) $id ) );
		if ( ! $r ) { return VHCP_Util::err( 'Không tìm thấy hợp đồng' ); }
		$wpdb->delete( $t, array( 'id' => (string) $id ) );
		VHCP_Log::log_action( array(
			'actor'  => (string) $nguoi,
			'action' => 'Xóa hợp đồng',
			'target' => trim( (string) $r['so_hd'] . ' · ' . (string) $r['ten'], ' ·' ),
			// Ghi lại đường dẫn file: xóa hợp đồng KHÔNG xóa file trên ổ, còn tìm lại được
			'detail' => count( self::files_ra( $r['files'] ) ) . ' file vẫn còn trên ổ đĩa',
		) );
		return VHCP_Util::ok();
	}
}
