<?php
/**
 * SỔ CHI PHÍ — nhập phẳng như sheet "Chi phí cơ sở": chọn LOẠI CHI PHÍ rồi nhập, hết.
 * Không lập đơn, không tạm ứng, không quyết toán.
 *
 * Điểm cốt lõi: mỗi dòng LƯU SẴN MÃ TÀI KHOẢN (TK Nợ / TK Có / Mã đối tượng) lấy từ
 * danh mục loại chi phí ngay lúc nhập. Nhờ vậy sau này muốn biết "đây là chi phí gì"
 * chỉ cần đọc cột mã tài khoản trên dòng — không phải chạy lại hàm dò ma trận
 * nhóm × phân loại lớn như luồng đơn vận hành.
 *
 * Đổi mã trong danh mục KHÔNG làm đổi mã của dòng đã nhập (số cũ giữ đúng lịch sử);
 * muốn áp mã mới cho dòng cũ thì dùng ganMaTaiKhoanSoChi().
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_SoChi {

	/** Hình thức chi -> phân loại thanh toán (để lấy TK Có mặc định 141 / 331). */
	const HT_TAM_UNG   = 'Tạm ứng NV';
	const HT_TRUC_TIEP = 'Trực tiếp NCC';

	public static function row( $id ) {
		global $wpdb;
		$t = VHCP_DB::t( 'so_chi' );
		return VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%s", (string) $id ) );
	}

	public static function all_rows() {
		$t = VHCP_DB::t( 'so_chi' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	// ---------------------------------------------------------------- mã tài khoản

	/** Chốt mã tài khoản cho 1 dòng — dùng chung bộ chốt của Cấu hình (mọi mảng giống nhau). */
	public static function resolve_tk( $rec ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? trim( (string) $rec[ $k ] ) : ''; };
		return VHCP_Cfg::resolve_tk( $g( 'loai' ), $g( 'hinhThuc' ), array( 'tkNo' => $g( 'tkNo' ), 'tkCo' => $g( 'tkCo' ), 'maDt' => $g( 'maDt' ) ), $g( 'coso' ) );
	}

	// ---------------------------------------------------------------- thêm / sửa / xóa

	private static function data( $rec, $nguoi = '' ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };

		$sl = VHCP_Util::blank_or_num( $g( 'soLuong' ) );
		$dg = VHCP_Util::blank_or_num( $g( 'donGia' ) );
		$st = VHCP_Util::blank_or_num( $g( 'soTien' ) );
		if ( $st === null ) { $st = VHCP_Util::num( $sl ) * VHCP_Util::num( $dg ); }

		$ts    = VHCP_Util::blank_or_num( $g( 'thueSuat' ) );
		$tthue = ( $ts === null ) ? null : round( $st * $ts / 100 );

		$ngay = VHCP_Util::parse_date( $g( 'ngay' ) );
		if ( ! $ngay ) { $ngay = VHCP_Util::today_sql(); }
		$ky = VHCP_Util::st( $g( 'ky' ) );
		if ( $ky === '' ) { $ky = substr( $ngay, 5, 2 ) . '/' . substr( $ngay, 0, 4 ); }   // trống -> MM/yyyy theo ngày chi

		$tk = self::resolve_tk( $rec );

		return array(
			'ngay'       => $ngay,
			'ky'         => $ky,
			'coso'       => VHCP_Util::st( $g( 'coso' ) ),
			'loai'       => VHCP_Util::st( $g( 'loai' ) ),
			'tk_no'      => $tk['tk_no'],
			'tk_co'      => $tk['tk_co'],
			'ma_dt'      => $tk['ma_dt'],
			'doi_tuong'  => VHCP_Util::st( $g( 'doiTuong' ) ),
			'noi_dung'   => VHCP_Util::st( $g( 'noiDung' ) ),
			'dvt'        => VHCP_Util::st( $g( 'dvt' ) ),
			'so_luong'   => $sl,
			'don_gia'    => $dg,
			'so_tien'    => $st,
			'hinh_thuc'  => VHCP_Util::st( $g( 'hinhThuc' ) ),
			'vat'        => VHCP_Util::st( $g( 'vat' ) ),
			'thue_suat'  => $ts,
			'tien_thue'  => $tthue,
			'ghi_chu'    => VHCP_Util::st( $g( 'ghiChu' ) ),
			'anh'        => VHCP_Util::st( $g( 'anh' ) ),
			// Dòng thuộc một dự án / công trình thì mang MÃ DỰ ÁN — gom theo mã là ra cả
			// dự án, khỏi cần bảng riêng cho dự án nữa.
			'ma_du_an'   => VHCP_Util::st( $g( 'maDuAn' ) ),
			'hang_muc'   => VHCP_Util::st( $g( 'hangMuc' ) ),
			'du_toan'    => VHCP_Util::blank_or_num( $g( 'duToan' ) ),
			'ho_so'      => VHCP_Util::st( $g( 'hoSo' ) ),
			'nguoi_nhap' => (string) $nguoi,
		);
	}

	public static function add( $rec, $nguoi = '' ) {
		global $wpdb;
		$rec = (array) $rec;
		if ( VHCP_Util::st( isset( $rec['loai'] ) ? $rec['loai'] : '' ) === '' ) { return VHCP_Util::err( 'Chọn loại chi phí' ); }
		$data               = self::data( $rec, $nguoi );
		$data['id']         = VHCP_Util::uid( 'C' );
		$data['tao_luc']    = VHCP_Util::now_sql();
		$wpdb->insert( VHCP_DB::t( 'so_chi' ), $data );
		return VHCP_Util::ok( array( 'id' => $data['id'], 'tkNo' => $data['tk_no'], 'tkCo' => $data['tk_co'] ) );
	}

	public static function update( $id, $rec, $nguoi = '' ) {
		global $wpdb;
		$cur = self::row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng chi' ); }
		if ( $cur['ngay_xuat'] ) { return VHCP_Util::err( 'Dòng đã xuất MISA — không sửa (bỏ chốt xuất trước nếu cần)' ); }
		$rec  = (array) $rec;
		$data = self::data( $rec, $nguoi !== '' ? $nguoi : (string) $cur['nguoi_nhap'] );
		$wpdb->update( VHCP_DB::t( 'so_chi' ), $data, array( 'id' => (string) $id ) );
		return VHCP_Util::ok( array( 'tkNo' => $data['tk_no'], 'tkCo' => $data['tk_co'] ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$cur = self::row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng chi' ); }
		if ( $cur['ngay_xuat'] ) { return VHCP_Util::err( 'Dòng đã xuất MISA — không xóa' ); }
		$wpdb->delete( VHCP_DB::t( 'so_chi' ), array( 'id' => (string) $id ) );
		return VHCP_Util::ok();
	}

	// ---------------------------------------------------------------- đọc & tổng hợp

	private static function out( $r ) {
		return array(
			'id'        => (string) $r['id'],
			'ngay'      => VHCP_Util::fmt( $r['ngay'] ),
			'ky'        => (string) $r['ky'],
			'coso'      => (string) $r['coso'],
			'loai'      => (string) $r['loai'],
			'tkNo'      => (string) $r['tk_no'],
			'tkCo'      => (string) $r['tk_co'],
			'maDt'      => (string) $r['ma_dt'],
			'doiTuong'  => (string) $r['doi_tuong'],
			'noiDung'   => (string) $r['noi_dung'],
			'dvt'       => (string) $r['dvt'],
			'soLuong'   => VHCP_Util::out_num( $r['so_luong'] ),
			'donGia'    => VHCP_Util::out_num( $r['don_gia'] ),
			'soTien'    => VHCP_Util::num( $r['so_tien'] ),
			'hinhThuc'  => (string) $r['hinh_thuc'],
			'vat'       => (string) $r['vat'],
			'thueSuat'  => VHCP_Util::out_num( $r['thue_suat'] ),
			'tienThue'  => VHCP_Util::num( $r['tien_thue'] ),
			'maDuAn'    => isset( $r['ma_du_an'] ) ? (string) $r['ma_du_an'] : '',
			'hangMuc'   => isset( $r['hang_muc'] ) ? (string) $r['hang_muc'] : '',
			'duToan'    => isset( $r['du_toan'] ) ? VHCP_Util::out_num( $r['du_toan'] ) : '',
			'hoSo'      => isset( $r['ho_so'] ) ? (string) $r['ho_so'] : '',
			'ghiChu'    => (string) $r['ghi_chu'],
			'anh'       => (string) $r['anh'],
			'nguoiNhap' => (string) $r['nguoi_nhap'],
			'daXuat'    => ( VHCP_Util::fmt( $r['ngay_xuat'] ) !== '' ),
			'ngayXuat'  => VHCP_Util::fmt( $r['ngay_xuat'] ),
		);
	}

	/**
	 * Danh sách + tổng hợp. $opts: ky|coso|loai|tkNo|q|xuat(all|chuaxuat|daxuat)|coso_scope(mảng, giới hạn cơ sở của Nhân viên).
	 * Tổng hợp trả về gom theo LOẠI CHI PHÍ (kèm mã TK), theo CƠ SỞ và theo KỲ.
	 */
	public static function list_chi( $opts = array() ) {
		$opts = (array) $opts;
		$o    = function ( $k, $d = '' ) use ( $opts ) { return isset( $opts[ $k ] ) && $opts[ $k ] !== '' ? $opts[ $k ] : $d; };

		$f_ky   = (string) $o( 'ky', 'all' );
		$f_cs   = (string) $o( 'coso', 'all' );
		$f_loai = (string) $o( 'loai', 'all' );
		$f_tk   = (string) $o( 'tkNo', 'all' );
		$f_da   = (string) $o( 'maDuAn', 'all' );
		$f_x    = (string) $o( 'xuat', 'all' );
		$q      = mb_strtolower( trim( (string) $o( 'q', '' ) ) );

		$scope = null;
		if ( ! empty( $opts['coso_scope'] ) ) {
			$arr = is_array( $opts['coso_scope'] ) ? $opts['coso_scope'] : explode( ',', (string) $opts['coso_scope'] );
			$arr = array_filter( array_map( function ( $s ) { return mb_strtolower( trim( (string) $s ) ); }, $arr ) );
			if ( count( $arr ) ) { $scope = array_fill_keys( $arr, 1 ); }
		}

		$items = array();
		$by_loai = array(); $by_coso = array(); $by_ky = array(); $by_tk = array(); $by_da = array();
		$tong = 0; $tong_thue = 0; $ky_set = array(); $loai_set = array(); $tk_set = array(); $da_set = array();

		foreach ( self::all_rows() as $r ) {
			$ky_set[ (string) $r['ky'] ] = 1;
			if ( trim( (string) $r['loai'] ) !== '' ) { $loai_set[ (string) $r['loai'] ] = 1; }
			if ( trim( (string) $r['tk_no'] ) !== '' ) { $tk_set[ (string) $r['tk_no'] ] = 1; }
			$mda = isset( $r['ma_du_an'] ) ? trim( (string) $r['ma_du_an'] ) : '';
			if ( $mda !== '' ) { $da_set[ $mda ] = 1; }

			if ( $scope !== null && ! isset( $scope[ mb_strtolower( trim( (string) $r['coso'] ) ) ] ) ) { continue; }
			if ( $f_ky !== 'all' && (string) $r['ky'] !== $f_ky ) { continue; }
			if ( $f_cs !== 'all' && (string) $r['coso'] !== $f_cs ) { continue; }
			if ( $f_loai !== 'all' && (string) $r['loai'] !== $f_loai ) { continue; }
			if ( $f_tk !== 'all' && (string) $r['tk_no'] !== $f_tk ) { continue; }
			if ( $f_da !== 'all' ) {
				if ( $f_da === '(khong)' ) { if ( $mda !== '' ) { continue; } }
				elseif ( $mda !== $f_da ) { continue; }
			}
			$da_xuat = ( VHCP_Util::fmt( $r['ngay_xuat'] ) !== '' );
			if ( $f_x === 'chuaxuat' && $da_xuat ) { continue; }
			if ( $f_x === 'daxuat' && ! $da_xuat ) { continue; }
			if ( $q !== '' ) {
				$hay = mb_strtolower( $r['noi_dung'] . ' ' . $r['loai'] . ' ' . $r['coso'] . ' ' . $r['doi_tuong'] . ' ' . $r['ghi_chu'] . ' ' . $r['tk_no'] . ' ' . $mda . ' ' . ( isset( $r['hang_muc'] ) ? $r['hang_muc'] : '' ) );
				if ( mb_strpos( $hay, $q ) === false ) { continue; }
			}

			$x       = self::out( $r );
			$items[] = $x;
			$tong      += $x['soTien'];
			$tong_thue += $x['tienThue'];

			$kl = $x['loai'] !== '' ? $x['loai'] : '(chưa chọn loại)';
			if ( ! isset( $by_loai[ $kl ] ) ) { $by_loai[ $kl ] = array( 'loai' => $kl, 'tkNo' => $x['tkNo'], 'tien' => 0, 'n' => 0 ); }
			$by_loai[ $kl ]['tien'] += $x['soTien'];
			$by_loai[ $kl ]['n']++;
			if ( $by_loai[ $kl ]['tkNo'] === '' ) { $by_loai[ $kl ]['tkNo'] = $x['tkNo']; }

			$kc = $x['coso'] !== '' ? $x['coso'] : '(không cơ sở)';
			if ( ! isset( $by_coso[ $kc ] ) ) { $by_coso[ $kc ] = array( 'coso' => $kc, 'tien' => 0 ); }
			$by_coso[ $kc ]['tien'] += $x['soTien'];

			$kk = $x['ky'];
			if ( ! isset( $by_ky[ $kk ] ) ) { $by_ky[ $kk ] = array( 'ky' => $kk, 'tien' => 0 ); }
			$by_ky[ $kk ]['tien'] += $x['soTien'];

			$kt = $x['tkNo'] !== '' ? $x['tkNo'] : '(chưa có mã TK)';
			if ( ! isset( $by_tk[ $kt ] ) ) { $by_tk[ $kt ] = array( 'tkNo' => $kt, 'tien' => 0, 'n' => 0 ); }
			$by_tk[ $kt ]['tien'] += $x['soTien'];
			$by_tk[ $kt ]['n']++;

			// Gom theo MÃ DỰ ÁN: một dự án giờ chỉ là các dòng chi mang cùng mã, nên
			// dự toán / thực chi / chênh lệch của nó tính ngay ở đây.
			if ( $mda !== '' ) {
				if ( ! isset( $by_da[ $mda ] ) ) { $by_da[ $mda ] = array( 'maDuAn' => $mda, 'tien' => 0, 'duToan' => 0, 'n' => 0 ); }
				$by_da[ $mda ]['tien']   += $x['soTien'];
				$by_da[ $mda ]['duToan'] += VHCP_Util::num( $x['duToan'] );
				$by_da[ $mda ]['n']++;
			}
		}

		$items = array_reverse( $items );   // mới nhất trước
		$sort_desc = function ( &$arr ) {
			$arr = array_values( $arr );
			usort( $arr, function ( $a, $b ) { return $b['tien'] <=> $a['tien']; } );
		};
		$sort_desc( $by_loai );
		$sort_desc( $by_coso );
		$sort_desc( $by_tk );
		$sort_desc( $by_da );
		$by_ky = array_values( $by_ky );
		usort( $by_ky, function ( $a, $b ) { return VHCP_Util::ky_num( $b['ky'] ) <=> VHCP_Util::ky_num( $a['ky'] ); } );

		$ky_list = array_keys( $ky_set );
		usort( $ky_list, function ( $a, $b ) { return VHCP_Util::ky_num( $b ) <=> VHCP_Util::ky_num( $a ); } );
		$loai_list = array_keys( $loai_set );
		sort( $loai_list );
		$tk_list = array_map( 'strval', array_keys( $tk_set ) );   // mã toàn số -> ép lại chuỗi
		sort( $tk_list, SORT_NATURAL );
		$da_list = array_map( 'strval', array_keys( $da_set ) );
		sort( $da_list, SORT_NATURAL );

		$cfg   = VHCP_Cfg::cfg_static();
		$dm    = array();
		foreach ( (array) ( isset( $cfg['loaiChiPhi'] ) ? $cfg['loaiChiPhi'] : array() ) as $x ) { $dm[] = $x; }
		$coso = array();
		foreach ( $cfg['coso'] as $x ) { $coso[] = $x['ten']; }

		return VHCP_Util::ok( array(
			'items'      => $items,
			'tong'       => $tong,
			'tongThue'   => $tong_thue,
			'soDong'     => count( $items ),
			'byLoai'     => $by_loai,
			'byCoso'     => $by_coso,
			'byKy'       => $by_ky,
			'byTkNo'     => $by_tk,
			'byDuAn'     => $by_da,
			'kyList'     => array_values( $ky_list ),
			'loaiList'   => array_values( $loai_list ),
			'tkNoList'   => array_values( $tk_list ),
			'duAnList'   => array_values( $da_list ),
			'danhMuc'    => $dm,
			'coso'       => $coso,
		) );
	}

	/**
	 * TỔNG SỔ CHI PHÍ THEO MÃ DỰ ÁN — 1 lệnh DB cho mọi dự án.
	 *
	 * Dòng chi của dự án nay nằm trong sổ chi phí (mang mã dự án), nên màn hình dự án và
	 * danh sách dự án phải cộng cả phần này, không thì gian nào cũng hiện 0đ.
	 *
	 * @return array khóa = mã dự án đã hạ chữ thường
	 */
	public static function tong_theo_du_an() {
		global $wpdb;
		$t = VHCP_DB::t( 'so_chi' );
		$rows = VHCP_DB::rows(
			"SELECT ma_du_an, COUNT(*) AS n, SUM(so_tien) AS tien, SUM(du_toan) AS du_toan
			 FROM $t WHERE ma_du_an <> '' GROUP BY ma_du_an"
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$k = mb_strtolower( trim( (string) $r['ma_du_an'] ) );
			if ( $k === '' ) { continue; }
			$out[ $k ] = array(
				'maDuAn' => (string) $r['ma_du_an'],
				'n'      => (int) $r['n'],
				'tien'   => VHCP_Util::num( $r['tien'] ),
				'duToan' => VHCP_Util::num( $r['du_toan'] ),
			);
		}
		return $out;
	}

	/** Các dòng sổ chi phí của 1 mã dự án (dùng cho màn hình dự án). */
	public static function theo_du_an( $ma_du_an ) {
		global $wpdb;
		$ma = trim( (string) $ma_du_an );
		if ( $ma === '' ) { return array(); }
		$t = VHCP_DB::t( 'so_chi' );
		$rows = VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE LOWER(ma_du_an)=%s ORDER BY stt ASC", mb_strtolower( $ma ) ) );
		$out = array();
		foreach ( (array) $rows as $r ) { $out[] = self::out( $r ); }
		return $out;
	}

	// ---------------------------------------------------------------- gán mã cho dòng cũ

	/**
	 * Áp lại mã tài khoản từ danh mục cho các dòng CHƯA XUẤT còn thiếu mã
	 * (hoặc tất cả khi $all = true). Dùng sau khi sửa danh mục loại chi phí.
	 */
	public static function gan_ma_tai_khoan( $all = false ) {
		global $wpdb;
		$t = VHCP_DB::t( 'so_chi' );
		$n = 0; $chua = array();
		foreach ( self::all_rows() as $r ) {
			if ( VHCP_Util::fmt( $r['ngay_xuat'] ) !== '' ) { continue; }
			$thieu = ( trim( (string) $r['tk_no'] ) === '' || trim( (string) $r['tk_co'] ) === '' );
			if ( ! $all && ! $thieu ) { continue; }
			// Ô khai nhiều mã thì máy không chọn hộ được — giữ mã người nhập đã chọn tay.
			$giu = VHCP_Cfg::ma_con_hop_le( $r['loai'], $r['coso'], $r['tk_no'] );
			$tk  = self::resolve_tk( array( 'loai' => $r['loai'], 'hinhThuc' => $r['hinh_thuc'], 'coso' => $r['coso'], 'tkNo' => $giu ) );
			if ( $tk['tk_no'] === '' ) { $chua[ (string) $r['loai'] ] = 1; }
			if ( $tk['tk_no'] === (string) $r['tk_no'] && $tk['tk_co'] === (string) $r['tk_co'] && $tk['ma_dt'] === (string) $r['ma_dt'] ) { continue; }
			$wpdb->update( $t, array( 'tk_no' => $tk['tk_no'], 'tk_co' => $tk['tk_co'], 'ma_dt' => $tk['ma_dt'] ), array( 'id' => (string) $r['id'] ) );
			$n++;
		}
		return VHCP_Util::ok( array( 'updated' => $n, 'thieuMa' => array_keys( $chua ) ) );
	}

	// ---------------------------------------------------------------- xuất MISA

	/**
	 * Mỗi dòng chi = 1 dòng hạch toán, LẤY THẲNG mã tài khoản trên dòng.
	 * $mode: chuaxuat | daxuat ; $ky: 'all' hoặc 1 kỳ.
	 */
	public static function export_misa( $ky = 'all', $mode = 'chuaxuat' ) {
		$cfg    = VHCP_Cfg::cfg_static();
		$m_unit = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }

		$rows = array(); $warn = array(); $ids = array();
		foreach ( self::all_rows() as $r ) {
			$da_xuat = ( VHCP_Util::fmt( $r['ngay_xuat'] ) !== '' );
			if ( $mode === 'daxuat' ? ! $da_xuat : $da_xuat ) { continue; }
			if ( $ky && $ky !== 'all' && (string) $r['ky'] !== $ky ) { continue; }
			$so_tien = VHCP_Util::num( $r['so_tien'] );
			if ( ! $so_tien ) { continue; }

			$coso  = (string) $r['coso'];
			$loai  = (string) $r['loai'];
			$tk_no = trim( (string) $r['tk_no'] );
			$tk_co = trim( (string) $r['tk_co'] );
			$ma_dv = isset( $m_unit[ $coso ] ) ? $m_unit[ $coso ] : '';
			$tenm  = ! empty( $m_tm[ $coso ] ) ? $m_tm[ $coso ] : $coso;
			$ngay  = VHCP_Util::fmt( $r['ngay'] );

			if ( $tk_no === '' ) { $warn[ 'Thiếu TK Nợ cho loại chi phí: ' . ( $loai !== '' ? $loai : '(chưa chọn)' ) . ' — khai ở ⚙️ Cấu hình → Loại chi phí' ] = 1; }
			if ( $tk_co === '' ) { $warn[ 'Thiếu TK Có cho dòng: ' . ( $loai !== '' ? $loai : '(chưa chọn)' ) ] = 1; }
			if ( $coso !== '' && ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở: ' . $coso ] = 1; }

			$is_tt = ( trim( (string) $r['hinh_thuc'] ) === self::HT_TRUC_TIEP );
			// Diễn giải lấy TÊN XUẤT MISA của loại chi phí (khai ở danh mục, sửa lúc nào cũng
			// được); trống thì dùng chính tên gọi. Mã đã lưu trên dòng không đổi theo.
			$ten_xuat = VHCP_Cfg::ten_misa_loai( $loai );
			$mda   = isset( $r['ma_du_an'] ) ? trim( (string) $r['ma_du_an'] ) : '';
			$dg1   = VHCP_Util::j( array( $ten_xuat, $coso, $r['ky'] ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
			// Dòng thuộc dự án thì ghi mã dự án vào diễn giải để bên kế toán dò lại được
			$dg2   = VHCP_Util::j( array( $mda, (string) $r['noi_dung'], $tenm ) );
			$gc    = trim( (string) $r['ghi_chu'] );
			if ( $gc !== '' ) { $dg2 .= '_' . $gc; }

			$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, $tk_no, $tk_co, $so_tien, (string) $r['ma_dt'], $ma_dv );
			$ids[]  = (string) $r['id'];
		}

		return array(
			'cols'   => VHCP_Misa::cols(),
			'rows'   => $rows,
			'count'  => count( $rows ),
			'sodon'  => count( $ids ),
			'warn'   => array_keys( $warn ),
			'maDons' => $ids,
		);
	}

	/** Chốt "đã xuất MISA" cho các dòng vừa tải về. */
	public static function mark_exported( $ids ) {
		global $wpdb;
		$ids = (array) $ids;
		if ( ! count( $ids ) ) { return VHCP_Util::err( 'Không có dòng nào để chốt' ); }
		$now = VHCP_Util::now_sql();
		$n   = 0;
		foreach ( $ids as $id ) {
			$r = self::row( $id );
			if ( ! $r || VHCP_Util::fmt( $r['ngay_xuat'] ) !== '' ) { continue; }
			$wpdb->update( VHCP_DB::t( 'so_chi' ), array( 'ngay_xuat' => $now ), array( 'id' => (string) $id ) );
			$n++;
		}
		return VHCP_Util::ok( array( 'count' => $n ) );
	}

	/** Bỏ chốt (nhập/xuất lại) — chỉ Admin gọi qua API. */
	public static function unmark_exported( $ids ) {
		global $wpdb;
		$n = 0;
		foreach ( (array) $ids as $id ) {
			$wpdb->update( VHCP_DB::t( 'so_chi' ), array( 'ngay_xuat' => null ), array( 'id' => (string) $id ) );
			$n++;
		}
		return VHCP_Util::ok( array( 'count' => $n ) );
	}
}
