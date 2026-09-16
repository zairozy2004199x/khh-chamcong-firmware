<?php
/**
 * MÀN TỔNG QUAN — số liệu gom cho cả hệ, và từng cơ sở một dòng.
 *
 * 🔴 CƠ SỞ NÀO CHƯA CÓ DỮ LIỆU THÌ BỎ QUA KHI TÍNH ĐIỂM, KHÔNG TÍNH LÀ 0 ĐIỂM.
 *    Một cơ sở chưa được Quản trị đặt chỉ tiêu doanh thu mà bị chấm 0 cho mảnh ấy thì điểm chung
 *    của nó tụt xuống vùng đỏ vì một việc NGƯỜI KHÁC chưa làm — và cửa hàng trưởng ở đó không có
 *    cách nào sửa. Điểm phải chỉ nói về thứ họ làm được.
 *
 * ⚠️ Danh sách cơ sở và số nhân sự lấy từ plugin CHẤM CÔNG, không giữ bản sao ở đây. Giữ bản sao
 *    là hai danh sách lệch tên nhau, và báo cáo cộng theo cơ sở ra hai kết quả khác nhau.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_Tong {

	/** Năm mảnh của điểm sức khoẻ. Mảnh nào không có dữ liệu thì rơi khỏi phép chia. */
	const MANH = array( 'doanh_thu', 'checklist', 'cham_cong', 'vi_pham', 'viec_tre' );

	/**
	 * Danh sách cơ sở toàn hệ — mượn danh mục của chấm công, GỘP với những cơ sở đang thật sự
	 * mang dữ liệu ở đây.
	 *
	 * 🔴 PHẢI GỘP, KHÔNG ĐƯỢC CHỈ ĐỌC DANH MỤC. Danh mục có thể rỗng (chấm công vừa cài, chưa
	 *    nạp sổ) hoặc thiếu một tên ai đó gõ lệch. Chỉ đọc danh mục thì Quản lý mở màn Tổng quan
	 *    ra thấy TRỐNG TRƠN trong khi sổ đang có doanh thu — và không có câu nào nói vì sao.
	 *    Gộp thêm cơ sở có dữ liệu thì tệ nhất cũng là hiện thừa một cái tên gõ lệch, còn hơn
	 *    giấu mất một cơ sở đang bán hàng thật.
	 */
	public static function ds_coso_he() {
		global $wpdb;
		$ds = array();
		if ( class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'ds_coso' ) ) {
			foreach ( (array) VHCC_NhanSu::ds_coso() as $c ) {
				$c = trim( (string) $c );
				if ( '' !== $c && ! in_array( $c, $ds, true ) ) { $ds[] = $c; }
			}
		}
		foreach ( array( 'doanh_thu', 'su_co', 'checklist' ) as $bang ) {
			$cot = (array) $wpdb->get_col( 'SELECT DISTINCT coso FROM ' . VHVH_DB::t( $bang ) );
			foreach ( $cot as $c ) {
				$c = trim( (string) $c );
				if ( '' !== $c && ! in_array( $c, $ds, true ) ) { $ds[] = $c; }
			}
		}
		sort( $ds );
		return $ds;
	}

	/** Số hồ sơ nhân sự đang hoạt động. Không đọc được thì trả null để màn hình hiện "—". */
	public static function so_nhan_su() {
		global $wpdb;
		if ( ! class_exists( 'VHCC_DB' ) || ! method_exists( 'VHCC_DB', 't' ) ) { return null; }
		$t = VHCC_DB::t( 'nhan_vien' );
		/* Bảng có thể chưa dựng (chấm công vừa cài, chưa chạy nạp sổ) — hỏi trước rồi mới đếm,
		   đếm thẳng vào bảng không có là một dòng cảnh báo PHP in giữa trang. */
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return null; }
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
	}

	/**
	 * Số liệu cho màn Tổng quan.
	 *
	 * @param array  $u   người đang xem — quyết định thấy được mấy cơ sở.
	 * @param string $tu  đầu kỳ (Y-m-d).
	 * @param string $den cuối kỳ (Y-m-d).
	 */
	public static function so( $u, $tu, $den ) {
		$cho = VHVH_Auth::coso_duoc( $u );
		$ds  = ( true === $cho ) ? self::ds_coso_he() : array_values( $cho );

		$hang = array();
		foreach ( $ds as $c ) { $hang[] = self::mot_coso( $u, $c, $tu, $den ); }

		$tom = VHVH_Tien::tom_tat( $u, $tu, $den );
		$cl  = self::checklist_tb( $ds, current_time( 'Y-m-d' ) );

		return array(
			'so_coso'      => count( $ds ),
			'so_nhan_su'   => self::so_nhan_su(),
			'checklist_tb' => $cl,
			'su_co_mo'     => VHVH_SuCo::dem_mo( $u ),
			'thu'          => $tom['thu'],
			'chi'          => $tom['chi'],
			'khach'        => $tom['khach'],
			'cho_duyet'    => $tom['cho_duyet'],
			'hang'         => $hang,
		);
	}

	/** Một dòng trong bảng "Tình hình hoạt động các cơ sở". */
	private static function mot_coso( $u, $coso, $tu, $den ) {
		global $wpdb;
		$hnay = current_time( 'Y-m-d' );

		$thu = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(tong_thu),0) FROM ' . VHVH_DB::t( 'doanh_thu' )
			. ' WHERE coso=%s AND ngay BETWEEN %s AND %s', $coso, $tu, $den ) );

		/* Chỉ tiêu doanh thu do Quản trị đặt; chưa đặt thì mảnh này KHÔNG tham gia chấm điểm. */
		$chi_tieu = self::chi_tieu( $coso );

		$cl_hnay = $wpdb->get_row( $wpdb->prepare(
			'SELECT SUM(xong) x, SUM(tong) t FROM ' . VHVH_DB::t( 'checklist' )
			. ' WHERE coso=%s AND ngay=%s', $coso, $hnay ), ARRAY_A );
		$cl = ( $cl_hnay && (int) $cl_hnay['t'] > 0 )
			? (int) round( 100 * (int) $cl_hnay['x'] / (int) $cl_hnay['t'] ) : null;

		$sc_mo = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHVH_DB::t( 'su_co' ) . " WHERE coso=%s AND tt='mo'", $coso ) );

		$co_bc_hnay = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHVH_DB::t( 'doanh_thu' ) . ' WHERE coso=%s AND ngay=%s',
			$coso, $hnay ) );

		$manh = array();
		if ( null !== $chi_tieu && $chi_tieu > 0 ) {
			$manh['doanh_thu'] = min( 100, (int) round( 100 * $thu / $chi_tieu ) );
		}
		if ( null !== $cl ) { $manh['checklist'] = $cl; }
		$manh['su_co'] = $sc_mo ? max( 0, 100 - 20 * $sc_mo ) : 100;

		$diem = $manh ? (int) round( array_sum( $manh ) / count( $manh ) ) : null;

		return array(
			'coso'      => $coso,
			'thu'       => $thu,
			'chi_tieu'  => $chi_tieu,
			'phan_tram' => ( null !== $chi_tieu && $chi_tieu > 0 )
				? (int) round( 100 * $thu / $chi_tieu ) : null,
			'checklist' => $cl,
			'su_co_mo'  => $sc_mo,
			'bc_hnay'   => $co_bc_hnay ? 1 : 0,
			'diem'      => $diem,
			'trang_thai' => self::xep( $diem ),
		);
	}

	/**
	 * 90–100 Tốt · 70–89 Cần chú ý · dưới 70 Có vấn đề.
	 * Chưa có mảnh nào thì trả 'chua_du' — KHÔNG trả 'co_van_de': cơ sở mới khai chưa nhập gì mà
	 * đã bị dán nhãn đỏ thì cái nhãn ấy mất nghĩa.
	 */
	public static function xep( $diem ) {
		if ( null === $diem ) { return 'chua_du'; }
		if ( $diem >= 90 ) { return 'tot'; }
		if ( $diem >= 70 ) { return 'chu_y'; }
		return 'co_van_de';
	}

	/** Chỉ tiêu doanh thu tháng của một cơ sở. Chưa đặt thì null. */
	public static function chi_tieu( $coso ) {
		global $wpdb;
		$r = $wpdb->get_var( $wpdb->prepare(
			'SELECT noi_dung FROM ' . VHVH_DB::t( 'danh_muc' ) . ' WHERE loai=%s AND pham_vi=%s',
			'chi_tieu', (string) $coso ) );
		if ( null === $r || '' === $r ) { return null; }
		$n = (int) $r;
		return $n > 0 ? $n : null;
	}

	/** Đặt chỉ tiêu — chỉ quản lý. */
	public static function dat_chi_tieu( $u, $coso, $so ) {
		global $wpdb;
		if ( ! VHVH_Auth::du_quyen( $u, 'quan_ly' ) ) { return VHVH_Auth::choi(); }
		$coso = trim( sanitize_text_field( (string) $coso ) );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		$so = max( 0, (int) $so );
		$t  = VHVH_DB::t( 'danh_muc' );
		$cu = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $t WHERE loai=%s AND pham_vi=%s", 'chi_tieu', $coso ) );
		$hang = array( 'loai' => 'chi_tieu', 'pham_vi' => $coso,
			'noi_dung' => (string) $so, 'sua' => current_time( 'mysql' ) );
		if ( $cu ) { $wpdb->update( $t, $hang, array( 'id' => (int) $cu ) ); }
		else { $wpdb->insert( $t, $hang ); }
		return array( 'ok' => true, 'chi_tieu' => $so > 0 ? $so : null );
	}

	/** Checklist trung bình hôm nay trên các cơ sở đang xem. Không cơ sở nào có thì null. */
	public static function checklist_tb( $ds, $ngay ) {
		global $wpdb;
		if ( ! $ds ) { return null; }
		$cho = implode( ',', array_fill( 0, count( $ds ), '%s' ) );
		$gt  = array_merge( $ds, array( $ngay ) );
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT SUM(xong) x, SUM(tong) t FROM ' . VHVH_DB::t( 'checklist' )
			. " WHERE coso IN ($cho) AND ngay=%s", $gt ), ARRAY_A );
		if ( ! $r || (int) $r['t'] <= 0 ) { return null; }
		return (int) round( 100 * (int) $r['x'] / (int) $r['t'] );
	}
}
