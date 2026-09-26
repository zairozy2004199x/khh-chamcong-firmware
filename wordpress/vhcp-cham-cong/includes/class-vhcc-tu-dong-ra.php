<?php
/**
 * TỰ ĐỘNG GÁN GIỜ RA MẶC ĐỊNH — cho lượt quên bấm giờ ra, chạy bằng WP-Cron.
 *
 * =================================================================================================
 * Anh Thắng 26/09/2026: nhân viên chấm vào rồi hay QUÊN bấm ra. Muốn hệ thống tự lấp một giờ ra
 * MẶC ĐỊNH khi tới một mốc trễ nhất định vẫn chưa thấy — khác nhau cho hai loại cơ sở:
 *   · Cơ sở CHÍNH khối Văn phòng (ca ngày, VD KH_HCM): tới 22:00 mà hàng hôm nay còn thiếu giờ ra
 *     thì tự gán "Ca ngày đến" (`ngayDen`) làm giờ ra. Nếu đã có giờ ra thật (18:00, 20:00 — tăng
 *     ca) thì đó là số THẬT, không đụng vào.
 *   · Cơ sở PHỤ đã ghép vào một cơ sở chính Văn phòng (ca đêm, VD SETUP_VP): tới 08:00 sáng hôm
 *     sau mà hàng `-CD` của hôm qua còn thiếu giờ ra thì tự gán "Ca đêm đến" (`demDen`, đã trải
 *     phẳng +24h) làm giờ ra.
 *
 * =================================================================================================
 * 🔴 VÌ SAO ĐI QUA `VHCC_Nhan::ghi_gio()`, KHÔNG TỰ VIẾT SQL UPDATE.
 * =================================================================================================
 * Đây là cửa ghi DUY NHẤT của cả hệ (xem chú thích ở đầu hàm đó: "Mọi đường ghi vào bảng chấm công
 * đều qua đây"). Đi qua nó, lượt quét này TỰ ĐỘNG thừa hưởng mọi chốt đã có mà không phải chép lại
 * luật nào:
 *   · `quyet_dinh_gio()` chỉ NỚI giờ ra khi ô đang TRỐNG — hàng đã có giờ ra thật (kể cả tăng ca
 *     muộn 18h/20h) thì `$ra_cu` khác `null`, nhánh nới không đụng tới, số thật giữ nguyên.
 *   · "LƯỢT MÁY KHÔNG ĐÈ LÊN Ô NGƯỜI TA ĐÃ SỬA HOẶC BÙ" (`VHCC_Bu::o_da_dong_tay()`) — Admin cố
 *     ý xoá trắng giờ ra sau khi phát hiện sai thì lượt quét sau không được viết đè lại.
 *   · `nguon` tự chuyển thành `'hon-hop'` nếu hàng đã có nguồn khác trước đó — không cần lo trộn
 *     nguồn ở đây.
 *
 * ⚠️ KHÔNG PHẢI MỘT NHÁNH MỚI TRONG ĐƯỜNG GHI. Đây là một LƯỢT QUÉT ĐỘC LẬP chạy SAU, không đụng
 *    gì tới `cham_cong()`/`tuyen_cho_ghi()` — điện thoại bấm thật vẫn chạy y nguyên, không hay
 *    biết gì về lớp này.
 *
 * =================================================================================================
 * ⚠️ WP-CRON CHỈ CHẠY KHI CÓ NGƯỜI MỞ TRANG. Cùng giới hạn với `VHCC_DiaChi`/`VHCC_Push` — mốc
 *    22:00/08:00 có thể trễ vài phút nếu site đang vắng. Đã được anh Thắng chấp nhận.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_TuDongRa {

	/** Từ mốc này (giờ server) trở đi mới quét ca NGÀY của hôm nay. */
	const GIO_QUET_NGAY = '22:00:00';

	/** Từ mốc này (giờ server) trở đi mới quét ca ĐÊM của HÔM QUA. */
	const GIO_QUET_DEM = '08:00:00';

	const GHI_CHU_MAC_DINH = '⚠ Hệ thống tự động gán giờ ra mặc định — CHƯA XÁC NHẬN, kiểm lại nếu sai.';

	/**
	 * 🔴 KHAI NHỊP TRƯỚC, XẾP LỊCH SAU — cùng luật với `VHCC_DiaChi::init()`. Đảo thứ tự hai dòng
	 *    là lịch không bao giờ được xếp, hỏng im lặng, xem chú thích dài ở đó.
	 */
	public static function init() {
		add_action( 'vhcc_tu_dong_ra_quet', array( __CLASS__, 'quet' ) );
		if ( ! wp_next_scheduled( 'vhcc_tu_dong_ra_quet' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'vhcc_tu_dong_ra_quet' );
		}
	}

	/**
	 * Một lượt quét — hàm THUẦN theo nghĩa không phụ thuộc cron, gọi thẳng được từ phép thử.
	 *
	 * @return array( 'ngay' => int, 'dem' => int ) số hàng đã tự gán mỗi loại, để phép thử soi
	 *         được và để nhật ký cron (nếu cần) không phải tự đếm lại.
	 */
	public static function quet() {
		$so_ngay = 0;
		$so_dem  = 0;
		foreach ( VHCC_NhanSu::ds_coso() as $coso ) {
			if ( ! VHCC_Luong::la_van_phong( $coso ) ) { continue; }
			$cfg = VHCC_Luong::vp_cfg( $coso );
			if ( empty( $cfg['tuDongRa'] ) ) { continue; }

			$so_ngay += self::quet_ca_ngay( $coso, $cfg );
			foreach ( VHCC_Luong::phu_cua( $coso ) as $coso_phu ) {
				$so_dem += self::quet_ca_dem( $coso_phu, $cfg );
			}
		}
		return array( 'ngay' => $so_ngay, 'dem' => $so_dem );
	}

	/**
	 * CA NGÀY, cơ sở CHÍNH: hàng hôm nay, hậu tố rỗng, có vào chưa có ra, và đã qua GIO_QUET_NGAY.
	 */
	private static function quet_ca_ngay( $coso, $cfg ) {
		global $wpdb;
		if ( current_time( 'H:i:s' ) < self::GIO_QUET_NGAY ) { return 0; }
		$ngay_den_giay = VHCC_DB::giay( $cfg['ngayDen'] );
		if ( null === $ngay_den_giay ) { return 0; }
		$ngay = current_time( 'Y-m-d' );
		$hang = VHCC_DB::rows( $wpdb->prepare(
			'SELECT ma_nv, ho_ten FROM ' . VHCC_DB::t( 'cham_cong' )
			. " WHERE coso=%s AND ngay=%s AND hau_to=''"
			. ' AND gio_vao_giay IS NOT NULL AND gio_ra_giay IS NULL',
			$coso, $ngay ) );
		$so = 0;
		foreach ( $hang as $h ) {
			$kq = VHCC_Nhan::ghi_gio( $coso, $ngay, (string) $h['ma_nv'], (string) $h['ho_ten'],
				$ngay_den_giay, '', 'tu_dong', self::GHI_CHU_MAC_DINH );
			if ( ! isset( $kq['loi'] ) ) { $so++; }
		}
		return $so;
	}

	/**
	 * CA ĐÊM, cơ sở PHỤ: hàng NGÀY HÔM QUA, hậu tố `-CD`, có vào chưa có ra, và đã qua GIO_QUET_DEM
	 * (của hôm nay — tức đã qua một đêm trọn kể từ ngày ghi trên hàng đó).
	 */
	private static function quet_ca_dem( $coso_phu, $cfg ) {
		global $wpdb;
		if ( current_time( 'H:i:s' ) < self::GIO_QUET_DEM ) { return 0; }
		$dem_den_giay = VHCC_DB::giay( $cfg['demDen'] );
		if ( null === $dem_den_giay ) { return 0; }
		$hom_qua = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -1 day' ) );
		$hang = VHCC_DB::rows( $wpdb->prepare(
			'SELECT ma_nv, ho_ten FROM ' . VHCC_DB::t( 'cham_cong' )
			. " WHERE coso=%s AND ngay=%s AND hau_to='CD'"
			. ' AND gio_vao_giay IS NOT NULL AND gio_ra_giay IS NULL',
			$coso_phu, $hom_qua ) );
		$gio_ra_flat = $dem_den_giay + VHCC_DB::NGAY_GIAY;
		$so = 0;
		foreach ( $hang as $h ) {
			$kq = VHCC_Nhan::ghi_gio( $coso_phu, $hom_qua, ( (string) $h['ma_nv'] ) . '-CD',
				(string) $h['ho_ten'], $gio_ra_flat, '', 'tu_dong', self::GHI_CHU_MAC_DINH );
			if ( ! isset( $kq['loi'] ) ) { $so++; }
		}
		return $so;
	}
}
