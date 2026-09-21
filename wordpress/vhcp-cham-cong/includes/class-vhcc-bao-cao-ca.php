<?php
/**
 * "CÓ ĐI LÀM MÀ QUÊN CHẤM CÔNG" — đọc BÁO CÁO NGÀY bên hệ ghế POSH.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY
 * =============================================================================================
 * Anh Thắng 09/09/2026: *"đối với cơ sở Posh, nếu ai nhập báo cáo ngày đó thì bên chấm công sẽ
 * gắn cờ có đi làm mà quên chấm công, trong ô ngày đó sẽ hiện vàng lên"*.
 *
 * Người gửi báo cáo doanh thu phải ĐI THU TẬN NƠI mới có số mà nhập — chỉ số từng ghế, tiền mặt
 * đếm trong ngăn, ảnh chứng từ đính kèm. Nên một dòng báo cáo là bằng chứng khá chắc rằng hôm ấy
 * người đó đi làm. Mà bảng công thì để trống, vì họ quên bấm.
 *
 * =============================================================================================
 * 🔴 GẮN CỜ, KHÔNG TỰ TÍNH CÔNG — anh Thắng chọn, 09/09/2026
 * =============================================================================================
 * Ô hiện vàng và nói lý do; **số công VẪN LÀ 0** cho tới khi có người bấm chấm công bù. Đây đúng
 * lối của cờ khuôn mặt (`VHCC_Mat`): gắn cờ thì sai sót thành MỘT DÒNG cần xem lại, còn tự cộng
 * công là một dòng báo cáo tự sinh ra tiền lương mà không ai duyệt. Thêm nữa: báo cáo ngày không
 * nói giờ vào / giờ ra, nên có muốn tính cũng không biết tính mấy công.
 *
 * =============================================================================================
 * 🔴 NỐI HAI HỆ BẰNG **PIN**, KHÔNG BẰNG HỌ TÊN — và KHÔNG phải sửa plugin ghế
 * =============================================================================================
 * Anh Thắng: *"mã, bên POSH cần làm lại mã mình sẽ chạy để cho chuẩn 2 bên"*. Đúng — tên không
 * phải khoá (trang Quản lý nhân sự đang báo **bốn hồ sơ trùng tên**).
 *
 * Bản ghế **2.16.0** đang chạy trên live đã giải sẵn chuyện này: bảng `bc_phien` có **khoá chính
 * `(pin, ngay)`** — hệ báo cáo vốn nhận diện người bằng **PIN**, không bằng tên. Mà PIN là thứ
 * dùng chung cho cả ba hệ (anh Thắng 28/08/2026: *"một người một PIN"* — xem `VHCC_DayGhe`), và
 * PIN trùng nay đã bị chặn ở cả bốn đường ghi hồ sơ (3.47.0).
 *
 * ⇒ **Không phải đụng một dòng nào của plugin ghế.** Bản đầu của tệp này định thêm cột `ma_nv`
 *   vào `vhg_chot` — vừa nhắm nhầm bảng (`chot` là chốt CHỈ SỐ GHẾ, không phải "nhập báo cáo"),
 *   vừa dựng trên bản ghế 1.41.0 trong repo trong khi live đã 2.16.0. Xem
 *   `docs/CANH-BAO-GHE-LECH-BAN.md`.
 *
 * ⚠️ MỘT PIN RA HAI HỒ SƠ THÌ BỎ QUA, KHÔNG ĐOÁN. Chặn PIN trùng chỉ có từ 3.47.0; sổ kéo về từ
 *    Sheets vẫn còn chỗ trùng sẵn. Gắn cờ nhầm người còn tệ hơn không gắn — nó nói "người này có
 *    đi làm" về một người có thể đang nghỉ. Số bỏ qua được đếm và NÓI RA (`bo_qua`).
 *
 * ⚠️ TỰ GIỚI HẠN Ở NGƯỜI CÓ NHẬP BÁO CÁO, KHÔNG VIẾT CỨNG CHỮ "POSH". Chỉ hệ POSH mới sinh ra
 *    dòng `bc_phien`, nên phép lọc là thứ tự nhiên của dữ liệu. Viết cứng tên cơ sở vào mã là
 *    ngày họ đổi tên thì cờ im lặng biến mất.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_BaoCaoCa {

	/**
	 * NGÀY NÀO AI CÓ NHẬP BÁO CÁO — cho MỘT tháng, theo Mã NV.
	 *
	 * @param string $thang 'YYYY-MM'
	 * @return array [ 'theo' => [ MÃ_NV => [ ngày(int) => 1 ] ], 'bo_qua' => int,
	 *                 'thieu_bang' => bool ]
	 */
	public static function theo_thang( $thang ) {
		global $wpdb;
		$rong = array( 'theo' => array(), 'bo_qua' => 0, 'thieu_bang' => false );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', (string) $thang ) ) { return $rong; }

		/* ⚠️ Gác `method_exists` NGAY TRONG HÀM GỌI (luật `tools/test/kiem-goi-cheo.php`): bốn
		   plugin cài độc lập, bản có thể lệch nhau — `class_exists` chỉ nói CÓ PLUGIN, không nói
		   CÓ HÀM. Không có plugin ghế thì im lặng trả rỗng, cờ này vốn không dành cho họ. */
		if ( ! class_exists( 'VHG_DB' ) || ! method_exists( 'VHG_DB', 't' ) ) { return $rong; }

		$t_bc = VHG_DB::t( 'bc_phien' );
		if ( ! VHCC_DB::co_bang( $t_bc ) ) {
			/* 🔴 CÓ PLUGIN GHẾ MÀ THIẾU BẢNG = bản ghế CŨ HƠN bản có trang báo cáo. Nói ra, đừng
			   im: lưới không bao giờ vàng lên mà không một lời giải thích thì người dùng kết luận
			   tính năng hỏng. Một tính năng tắt vì thiếu điều kiện phải nói ra điều kiện ấy. */
			$rong['thieu_bang'] = true;
			return $rong;
		}

		$ds = VHCC_DB::rows( $wpdb->prepare(
			"SELECT pin, ngay FROM $t_bc WHERE ngay >= %s AND ngay <= %s",
			$thang . '-01', date( 'Y-m-t', strtotime( $thang . '-01' ) ) ) );
		if ( ! $ds ) { return $rong; }

		$ma_cua = self::ma_theo_pin();
		$theo   = array();
		$bo     = 0;
		foreach ( $ds as $r ) {
			$pin = VHCC_Auth::pin_sach( (string) $r['pin'] );
			/* Rỗng = PIN ấy không tra ra hồ sơ nào, hoặc tra ra HAI (xem `ma_theo_pin`). */
			if ( '' === $pin || empty( $ma_cua[ $pin ] ) ) { $bo++; continue; }
			$ma = $ma_cua[ $pin ];
			$d  = (int) substr( (string) $r['ngay'], 8, 2 );
			if ( $d < 1 || $d > 31 ) { continue; }
			if ( ! isset( $theo[ $ma ] ) ) { $theo[ $ma ] = array(); }
			$theo[ $ma ][ $d ] = 1;
		}
		return array( 'theo' => $theo, 'bo_qua' => $bo, 'thieu_bang' => false );
	}

	/**
	 * PIN -> MÃ NV. PIN nào ra HAI hồ sơ thì bỏ hẳn khỏi bảng tra.
	 *
	 * 🔴 KHÔNG ĐOÁN KHI TRÙNG. Chốt chặn PIN trùng chỉ có từ 3.47.0, mà sổ kéo về từ Sheets vẫn
	 *    còn chỗ trùng sẵn (xem dải đỏ ở thẻ *Hồ sơ nhân sự*). Chọn đại một trong hai là gắn cờ
	 *    "có đi làm" lên một người có thể đang nghỉ — sai theo cách không ai kiểm ra được.
	 */
	private static function ma_theo_pin() {
		global $wpdb;
		$ds = VHCC_DB::rows( 'SELECT ma_nv, pin_dang_nhap FROM ' . VHCC_DB::t( 'nhan_vien' )
			. " WHERE pin_dang_nhap <> '' AND ma_nv <> ''" );
		$ra = array();
		$hong = array();
		foreach ( (array) $ds as $r ) {
			$p = VHCC_Auth::pin_sach( (string) $r['pin_dang_nhap'] );
			if ( '' === $p ) { continue; }
			if ( isset( $ra[ $p ] ) ) { $hong[ $p ] = 1; continue; }
			$ra[ $p ] = trim( (string) $r['ma_nv'] );
		}
		foreach ( array_keys( $hong ) as $p ) { unset( $ra[ $p ] ); }
		return $ra;
	}
}
