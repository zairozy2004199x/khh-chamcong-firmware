<?php
/**
 * CHECKLIST ĐẦU / CUỐI NGÀY.
 *
 * =================================================================================================
 * 🔴 SỐ MỤC ĐÃ XONG LÀ MÁY CHỦ ĐẾM, KHÔNG PHẢI TRANG GỬI LÊN
 * =================================================================================================
 * Cùng một luật với doanh thu: trang gửi lên *mục nào tích*, máy chủ đếm lấy `xong`/`tong`. Nhận
 * con số do trang gửi thì gọi thẳng API khai "38/38 xong" trong khi không tích mục nào — mà điểm
 * sức khoẻ của cơ sở lại ăn theo đúng con số ấy.
 *
 * 🔴 DANH MỤC ĐỔI THÌ BẢN GHI CŨ VẪN PHẢI ĐỌC ĐƯỢC
 * Trạng thái lưu theo KHOÁ mục (`z0_3`), không lưu theo vị trí trong mảng. Lưu theo vị trí thì
 * thêm một mục vào giữa danh mục là mọi bản ghi của tháng trước lệch hết một nấc — và không có
 * cách nào biết bản nào đã lệch.
 *
 * ⚠️ `tong` ĐẾM THEO DANH MỤC LÚC GHI, không đếm lại lúc đọc. Danh mục thêm mục mới thì báo cáo
 *    của hôm qua vẫn là "28/28 xong" chứ không tụt thành "28/31 xong" — người làm hôm qua không
 *    bỏ sót gì cả, mục ấy lúc đó chưa tồn tại.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_Checklist {

	const BUOI = array( 'dau_ngay' => 'Đầu ngày (trước khi đón khách)',
		'cuoi_ngay' => 'Cuối ngày (trước khi ra về)' );

	/** Danh mục mặc định — lấy nguyên từ bản đang chạy, không rút gọn. */
	public static function mac_dinh() {
		return array(
			array( 'khoa' => 'thu_ngan', 'ten' => 'Quầy thu ngân', 'muc' => array(
				'Lau mặt bàn, máy tính, máy POS, các bề mặt tiếp xúc',
				'Vệ sinh mặt tiền quầy, thùng rác, sàn xung quanh',
				'Lau toàn bộ các hộc tủ, ngăn kéo – sắp xếp gọn gàng',
				'Vệ sinh tủ kem, tủ lạnh: lau kính, sắp xếp hàng đẹp, kiểm tra nhiệt độ',
				'Vệ sinh khu vực pha chế: rửa thùng đá, bàn pha chế sạch sẽ, khô ráo',
				'Vệ sinh ngách dưới các tủ: thu ngân, tủ kem, tủ lạnh',
				'Lau toàn bộ standee, biển bảng, q-belt',
				'Bật tivi trình chiếu',
				'Chuẩn bị sổ sách, báo cáo số liệu: sổ cam kết, báo cáo hàng hoá đầu ngày',
				'Cuối ngày: báo cáo tắt điện',
				'Báo cáo số liệu vào nhóm',
			) ),
			array( 'khoa' => 'ki_thuat', 'ten' => 'Phòng kĩ thuật', 'muc' => array(
				'Kiểm tra bộ đàm: sạc pin liên tục',
				'Kiểm tra camera: hoạt động ổn định, đúng góc máy',
				'Kiểm tra loa: mở loa + kiểm tra sạc loa',
				'Kiểm tra đồng phục diễn: đúng vị trí, ngăn nắp',
				'Kiểm tra bàn kĩ thuật: ngăn nắp, gọn gàng',
				'Kiểm tra đầu thu: không lỗi',
				'Kiểm tra tư trang cá nhân: để đúng vị trí',
				'Kiểm tra vệ sinh phòng: sạch sẽ, không bẩn',
				'Sẵn sàng đón khách trước giờ mở cửa',
			) ),
			array( 'khoa' => 'kho_hu', 'ten' => 'Phòng kho — Phòng hù', 'muc' => array(
				'Nguyên liệu hàng hoá chất gọn gàng, ngăn nắp',
				'Công cụ vệ sinh đặt, treo đúng vị trí',
				'Đồ doạ đặt, treo đúng vị trí, không quăng vứt lung tung',
				'Kiểm tra các hộc tủ doạ: không bung, không sứt',
				'Quét dọn phòng sạch sẽ, không rác, không bẩn, không mùi',
				'Xe đẩy hàng xếp gọn gàng',
			) ),
			array( 'khoa' => 'phong_dien', 'ten' => 'Các phòng diễn + hành lang', 'muc' => array(
				'Quét dọn, vệ sinh sạch sẽ các phòng',
				'Kiểm tra, gia cố, bổ sung đồ decor: chữ hỷ, bùa chú…',
				'Kiểm tra các tủ nấp, góc nấp',
				'Quét dọn toàn bộ hành lang: trừ lối đi từ bếp sang vườn, vườn sang dâu',
				'Kiểm tra sàn: đảm bảo không tróc',
				'Kiểm tra kĩ thuật các phòng: đèn, tủ, decor…',
			) ),
		);
	}

	/**
	 * Danh mục đang dùng cho một cơ sở.
	 *
	 * Mỗi cơ sở có thể tự khai danh mục riêng (`pham_vi` = tên cơ sở); chưa khai thì dùng bản
	 * chung (`pham_vi` rỗng); chưa khai cả bản chung thì dùng bản mặc định.
	 */
	public static function danh_muc( $coso = '' ) {
		global $wpdb;
		$t = VHVH_DB::t( 'danh_muc' );
		foreach ( array( (string) $coso, '' ) as $pv ) {
			if ( '' === $pv && '' !== (string) $coso ) { /* rơi xuống bản chung */ }
			$r = $wpdb->get_var( $wpdb->prepare(
				"SELECT noi_dung FROM $t WHERE loai=%s AND pham_vi=%s", 'checklist', $pv ) );
			if ( $r ) {
				$j = json_decode( (string) $r, true );
				if ( is_array( $j ) && $j ) { return self::rua( $j ); }
			}
		}
		return self::mac_dinh();
	}

	/** Soát danh mục đọc từ kho — thiếu ô thì bỏ, không để nó làm hỏng phép đếm. */
	public static function rua( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $k ) {
			if ( ! is_array( $k ) ) { continue; }
			$khoa = isset( $k['khoa'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $k['khoa'] ) ) : '';
			$muc  = array();
			foreach ( (array) ( isset( $k['muc'] ) ? $k['muc'] : array() ) as $m ) {
				$m = mb_substr( sanitize_text_field( (string) $m ), 0, 240 );
				if ( '' !== trim( $m ) ) { $muc[] = $m; }
			}
			if ( '' === $khoa || ! $muc ) { continue; }
			$ra[] = array(
				'khoa' => $khoa,
				'ten'  => isset( $k['ten'] ) ? mb_substr( sanitize_text_field( (string) $k['ten'] ), 0, 120 ) : $khoa,
				'muc'  => $muc,
			);
		}
		return $ra;
	}

	/** Khoá của một mục: khu + số thứ tự trong khu. Đổi tên mục thì khoá vẫn thế. */
	public static function khoa_muc( $khu, $i ) { return $khu . '_' . (int) $i; }

	/** Tổng số mục của một danh mục. */
	public static function dem_tong( $dm ) {
		$n = 0;
		foreach ( $dm as $k ) { $n += count( $k['muc'] ); }
		return $n;
	}

	public static function doc( $coso, $ngay, $buoi ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHVH_DB::t( 'checklist' ) . ' WHERE coso=%s AND ngay=%s AND buoi=%s',
			$coso, $ngay, $buoi ), ARRAY_A );
		if ( ! $r ) { return null; }
		$j = json_decode( (string) $r['muc'], true );
		$r['muc']  = is_array( $j ) ? $j : array();
		$r['xong'] = (int) $r['xong'];
		$r['tong'] = (int) $r['tong'];
		return $r;
	}

	/**
	 * Ghi một buổi.
	 *
	 * 🔴 KHÔNG KHOÁ SAU KHI NỘP. Khác doanh thu: checklist là việc làm dở trong ca, người ta tích
	 *    dần rồi bổ sung nốt mục quên — khoá lại là họ phải nhờ quản lý mở, và lần sau họ chờ
	 *    xong hết mới tích một lượt, tức là cái danh sách mất tác dụng nhắc việc.
	 */
	public static function luu( $u, $d ) {
		global $wpdb;
		$coso = isset( $d['coso'] ) ? trim( sanitize_text_field( (string) $d['coso'] ) ) : '';
		$ngay = isset( $d['ngay'] ) ? trim( (string) $d['ngay'] ) : '';
		$buoi = isset( $d['buoi'] ) ? (string) $d['buoi'] : '';
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) {
			return array( 'ok' => false, 'error' => 'Ngày không hợp lệ.' );
		}
		if ( ! isset( self::BUOI[ $buoi ] ) ) { return array( 'ok' => false, 'error' => 'Buổi không hợp lệ.' ); }
		if ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return VHVH_Auth::choi(); }

		$dm   = self::danh_muc( $coso );
		$hop  = array();
		foreach ( $dm as $k ) {
			foreach ( $k['muc'] as $i => $m ) { $hop[ self::khoa_muc( $k['khoa'], $i ) ] = true; }
		}
		/* 🔴 CHỈ NHẬN KHOÁ CÓ THẬT TRONG DANH MỤC. Nhận khoá lạ thì gọi thẳng API nhét thêm 50
		   khoá bịa là `xong` vọt lên 50 trong khi cơ sở chưa làm gì. */
		$tich = array();
		foreach ( (array) ( isset( $d['muc'] ) ? $d['muc'] : array() ) as $k => $v ) {
			$k = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $k ) );
			if ( isset( $hop[ $k ] ) && $v ) { $tich[ $k ] = 1; }
		}

		$hang = array(
			'coso'  => $coso,
			'ngay'  => $ngay,
			'buoi'  => $buoi,
			'muc'   => wp_json_encode( $tich ),
			'xong'  => count( $tich ),
			'tong'  => self::dem_tong( $dm ),
			'nguoi' => (string) $u['ten'],
			'sua'   => current_time( 'mysql' ),
		);
		$t  = VHVH_DB::t( 'checklist' );
		$cu = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $t WHERE coso=%s AND ngay=%s AND buoi=%s", $coso, $ngay, $buoi ) );
		if ( $cu ) {
			$wpdb->update( $t, $hang, array( 'id' => (int) $cu ) );
		} else {
			$hang['tao'] = current_time( 'mysql' );
			if ( ! $wpdb->insert( $t, $hang ) ) {
				/* Khoá duy nhất (coso,ngay,buoi) chặn hai lượt song song — lượt sau hỏng thì đọc
				   lại rồi cập nhật, không sinh dòng thứ hai. */
				$lai = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM $t WHERE coso=%s AND ngay=%s AND buoi=%s", $coso, $ngay, $buoi ) );
				if ( ! $lai ) { return array( 'ok' => false, 'error' => 'Không ghi được checklist.' ); }
				$wpdb->update( $t, $hang, array( 'id' => (int) $lai ) );
			}
		}
		return array( 'ok' => true, 'ban' => self::doc( $coso, $ngay, $buoi ) );
	}
}
