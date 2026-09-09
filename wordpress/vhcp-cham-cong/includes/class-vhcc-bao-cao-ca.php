<?php
/**
 * "CÓ ĐI LÀM MÀ QUÊN CHẤM CÔNG" — đọc BÁO CÁO CA bên hệ ghế POSH.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY
 * =============================================================================================
 * Anh Thắng 09/09/2026: *"đối với cơ sở Posh, nếu ai nhập báo cáo ngày đó thì bên chấm công sẽ
 * gắn cờ có đi làm mà quên chấm công, trong ô ngày đó sẽ hiện vàng lên"*.
 *
 * Người chốt ghế phải ĐỨNG TẠI CƠ SỞ mới chốt được (mã ghế đến từ QR dán trên ghế, tiền mặt
 * đếm trong ngăn). Nên một dòng chốt là bằng chứng khá chắc rằng hôm ấy người đó có mặt — chắc
 * hơn hẳn mọi thứ suy đoán từ bảng công. Mà bảng công thì để trống, vì họ quên bấm.
 *
 * =============================================================================================
 * 🔴 GẮN CỜ, KHÔNG TỰ TÍNH CÔNG — anh Thắng chọn, 09/09/2026
 * =============================================================================================
 * Ô hiện vàng và nói lý do; **số công VẪN LÀ 0** cho tới khi có người bấm chấm công bù. Đây đúng
 * lối của cờ khuôn mặt (`VHCC_Mat`): gắn cờ thì sai sót thành MỘT DÒNG cần xem lại, còn tự cộng
 * công là một dòng báo cáo tự sinh ra tiền lương mà không ai duyệt. Thêm nữa: báo cáo ca không
 * nói giờ vào / giờ ra, nên có muốn tính cũng không biết tính mấy công.
 *
 * =============================================================================================
 * 🔴 NỐI HAI HỆ BẰNG MÃ NV, KHÔNG BẰNG HỌ TÊN
 * =============================================================================================
 * Anh Thắng: *"mã, bên POSH cần làm lại mã mình sẽ chạy để cho chuẩn 2 bên"*. Đúng — chính trang
 * Quản lý nhân sự đang báo bốn hồ sơ **trùng tên**, nên tên không phải khoá. Bảng `vhg_chot` từ
 * bản ghế 1.42.0 ghi kèm `ma_nv`, lấy từ phiên đăng nhập (sổ người dùng bên ghế vốn đã mang
 * `maNV` do `VHCC_DayGhe` đẩy sang).
 *
 * ⚠️ DÒNG CHỐT CŨ KHÔNG CÓ MÃ THÌ BỎ QUA, KHÔNG DÒ THEO TÊN. Dò theo tên là đúng cái khoá vừa
 *    bác bỏ; gắn cờ nhầm người còn tệ hơn không gắn, vì nó nói "người này có đi làm" về một
 *    người có thể đang nghỉ. Số dòng bỏ qua được đếm và NÓI RA (`bo_qua`), để ai đọc biết là
 *    con số còn thiếu chứ không tưởng đã đủ.
 *
 * ⚠️ TỰ GIỚI HẠN Ở CƠ SỞ CÓ GHẾ, KHÔNG VIẾT CỨNG CHỮ "POSH". Chỉ cơ sở nào có ghế massage mới
 *    sinh ra dòng chốt, nên phép lọc là thứ tự nhiên của dữ liệu. Viết cứng tên cơ sở vào mã là
 *    ngày họ đổi tên cơ sở thì cờ im lặng biến mất.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_BaoCaoCa {

	/**
	 * CA ĐÊM VẮT QUA NỬA ĐÊM — chốt trước giờ này tính cho NGÀY HÔM TRƯỚC.
	 *
	 * 🔴 Ca tối POSH đóng cửa lúc 1 giờ sáng (xem chú thích "Báo cáo ca" ở `VHG_Trang`). Cắt
	 *    theo mốc nửa đêm thì người làm ca tối bị gắn cờ vào NGÀY HÔM SAU — một ngày họ không
	 *    hề đi làm — còn ngày họ thật sự làm thì vẫn trống. Sai cả hai đầu, và sai theo cách
	 *    nhìn rất giống đúng.
	 * ⚠️ 5 giờ chứ không phải 2: chốt xong còn đếm tiền, còn nộp quỹ. Nới rộng thì cùng lắm là
	 *    một lượt chốt lúc 4 giờ sáng bị tính về hôm trước — mà 4 giờ sáng thì đúng là hôm trước.
	 */
	const GIO_CAT = 5;

	/** Có hệ ghế trên site này không. Không có thì mọi thứ ở đây im lặng trả rỗng. */
	public static function co_he_ghe() {
		return class_exists( 'VHG_DB' ) && method_exists( 'VHG_DB', 't' );
	}

	/**
	 * NGÀY NÀO AI CÓ BÁO CÁO CA — cho MỘT cơ sở, MỘT tháng.
	 *
	 * @param string $coso  tên cơ sở bên chấm công
	 * @param string $thang 'YYYY-MM'
	 * @return array [ 'theo' => [ MÃ_NV => [ ngày(int) => số lượt chốt ] ], 'bo_qua' => int ]
	 */
	public static function theo_thang( $coso, $thang ) {
		global $wpdb;
		$rong = array( 'theo' => array(), 'bo_qua' => 0 );
		$cs = VHCC_NhanSu::chuan_coso( (string) $coso );
		if ( '' === $cs || ! preg_match( '/^\d{4}-\d{2}$/', (string) $thang ) ) { return $rong; }
		/* ⚠️ Gác `method_exists` NGAY TRONG HÀM GỌI, không gác hộ ở một hàm khác (luật
		   `tools/test/kiem-goi-cheo.php`). Bốn plugin cài độc lập, bản có thể lệch nhau:
		   `class_exists` chỉ nói CÓ PLUGIN, không nói CÓ HÀM — gọi hụt là trắng cả trang. */
		if ( ! class_exists( 'VHG_DB' ) || ! method_exists( 'VHG_DB', 't' ) ) { return $rong; }

		$t_chot = VHG_DB::t( 'chot' );
		$t_may  = VHG_DB::t( 'may' );
		$t_coso = VHG_DB::t( 'coso' );
		if ( ! VHCC_DB::co_bang( $t_chot ) || ! VHCC_DB::co_bang( $t_may )
			|| ! VHCC_DB::co_bang( $t_coso ) ) { return $rong; }

		/* 🔴 LẤY RỘNG RA MỘT NGÀY MỖI ĐẦU. Mốc cắt 5 giờ sáng kéo một lượt chốt sang ngày hôm
		   trước, nên lượt chốt lúc 02:00 ngày 01 của tháng sau thuộc về ngày cuối THÁNG NÀY —
		   không lấy rộng thì mất đúng ngày ấy, mà mất một cách im lặng. */
		/* ⚠️ `date()` chứ KHÔNG phải `gmdate()`. `chot.tao_luc` ghi bằng `current_time('mysql')`
		   — giờ ĐỊA PHƯƠNG, không phải UTC. Đọc bằng `strtotime` rồi in lại bằng `gmdate` là
		   lệch đúng bằng múi giờ: ở +07 thì mọi lượt chốt trước 7 giờ sáng rơi sang ngày hôm
		   trước một cách im lặng. Vào ra cùng một họ hàm thì phép khứ hồi luôn khớp. */
		$dau  = $thang . '-01 00:00:00';
		$cuoi = date( 'Y-m-t', strtotime( $thang . '-01' ) ) . ' 23:59:59';
		$tu   = date( 'Y-m-d H:i:s', strtotime( $dau ) - 86400 );
		$den  = date( 'Y-m-d H:i:s', strtotime( $cuoi ) + 86400 );

		$ds = VHCC_DB::rows( $wpdb->prepare(
			"SELECT c.ma_nv, c.nguoi, c.tao_luc FROM $t_chot c"
			. " JOIN $t_may m ON m.ma = c.ma_may"
			. " JOIN $t_coso s ON s.id = m.coso_id"
			. ' WHERE c.tao_luc >= %s AND c.tao_luc <= %s AND s.ten = %s',
			$tu, $den, $cs ) );

		$theo = array();
		$bo   = 0;
		foreach ( (array) $ds as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			if ( '' === $ma ) { $bo++; continue; }   // dòng cũ chưa có mã — KHÔNG dò theo tên
			$ngay = self::ngay_cua( (string) $r['tao_luc'] );
			if ( substr( $ngay, 0, 7 ) !== $thang ) { continue; }   // rơi ra ngoài tháng sau khi cắt
			$d = (int) substr( $ngay, 8, 2 );
			if ( ! isset( $theo[ $ma ] ) ) { $theo[ $ma ] = array(); }
			$theo[ $ma ][ $d ] = ( isset( $theo[ $ma ][ $d ] ) ? $theo[ $ma ][ $d ] : 0 ) + 1;
		}
		return array( 'theo' => $theo, 'bo_qua' => $bo );
	}

	/** Một mốc thời gian thuộc về NGÀY LÀM VIỆC nào — xem `GIO_CAT`. */
	public static function ngay_cua( $luc ) {
		$t = strtotime( (string) $luc );
		if ( ! $t ) { return ''; }
		if ( (int) date( 'H', $t ) < self::GIO_CAT ) { $t -= 86400; }
		return date( 'Y-m-d', $t );
	}
}
