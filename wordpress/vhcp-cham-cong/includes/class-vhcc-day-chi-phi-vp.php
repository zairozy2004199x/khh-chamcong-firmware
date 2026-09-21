<?php
/**
 * ĐẨY NGƯỜI TỪ SỔ NHÂN SỰ SANG BẢN CHI PHÍ VĂN PHÒNG (VP).
 *
 * =================================================================================================
 * Anh Thắng 15/09/2026: *"Tạo tab lệnh để đẩy dữ liệu nv sang 1 trang chi phí văn phòng trước"*.
 *
 * Chi phí nay có bốn bản chạy song song trên cùng một WordPress — khu vui chơi (`VHCP_*`), Văn
 * phòng (`VHCPVP_*`), Máy tự động (`VHCPMTD_*`), Hà Nội. Mỗi bản một bộ bảng, một sổ người dùng,
 * một đường đăng nhập riêng. Bảng "Ai vào được trang nào" mới chỉ đẩy được sang bản khu vui chơi.
 *
 * =================================================================================================
 * 🔴 LỚP NÀY CỐ Ý CHỈ CÓ SÁU HÀM. Mọi luật khó nằm ở lớp cha `VHCC_DayChiPhi`:
 *      · PIN lấy ở `nhan_vien.pin_dang_nhap`, KHÔNG ở `phan_quyen.pin`;
 *      · bộ phận lấy ở cột `bo_phan`, KHÔNG phải `chuc_vu` (chức vụ là việc người ta LÀM);
 *      · PIN trùng người khác -> xoá PIN của hàng bên kia, KHÔNG xoá hàng (hàng ấy mang TK Có,
 *        Mã đối tượng, Đơn vị do kế toán khai);
 *      · sửa đúng bốn ô của người được đẩy, giữ nguyên phần còn lại của sổ.
 *    Chép bốn bản sao của chừng ấy luật là bốn chỗ phải nhớ sửa mỗi lần — và bản lệch sẽ là bản
 *    ít người dùng nhất, nên không ai phát hiện ra.
 *
 * ⚠️ NĂM HÀM BỘ NỐI PHẢI GÁC `method_exists` NGAY TRONG THÂN NÓ — luật của
 *    `tools/test/kiem-goi-cheo.php`, sinh ra sau một lần trắng cả trang (23/08/2026): lớp CÓ mà
 *    hàm KHÔNG, vì hai plugin cài độc lập nên bản có thể lệch nhau bất cứ lúc nào.
 *
 * ⚠️ SỔ "ĐÃ ĐẨY" PHẢI RIÊNG (`O_DA_DAY`). Đẩy một người sang bản Văn phòng không có nghĩa họ có
 *    mặt bên khu vui chơi. Dùng chung một sổ là gỡ bên này thì bên kia cũng mất dấu, và bảng
 *    trên màn bày sai trạng thái của cả hai cột.
 *
 * ⚠️ NHƯNG HAI BẢN ĐỒ PHÒNG BAN (`O_BAN_DO`, `O_BAN_DO_BP`) THÌ DÙNG CHUNG, cố ý. Bản VP sinh ra
 *    từ bản khu vui chơi nên danh mục bộ phận y hệt (Cơ sở · Văn phòng · Kỹ thuật · Marketing ·
 *    Công tác · Setup · Máy tự động). Bắt anh Thắng khai lại cùng một bảng cho từng bản là ba
 *    lượt gõ cho một thông tin — và ba bảng khai tay là ba bảng lệch nhau.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_DayChiPhiVP extends VHCC_DayChiPhi {

	/** Tên cột trên bảng "Ai vào được trang nào" — KHÁC cột của bản khu vui chơi. */
	const COT = 'chi_phi_vp';

	/** Sổ mã NV đã đẩy sang bản VP: [ maNV => tên đã ghi sang ]. Riêng, xem chú thích đầu tệp. */
	const O_DA_DAY = 'vhcc_day_chi_phi_vp';

	public static function ten_he() { return 'Chi phí Văn phòng'; }

	public static function co_he() {
		return class_exists( 'VHCPVP_Cfg' )
			&& method_exists( 'VHCPVP_Cfg', 'read' )
			&& method_exists( 'VHCPVP_Cfg', 'write' )
			&& defined( 'VHCPVP_Cfg::USER' );
	}

	public static function doc_user() {
		if ( ! class_exists( 'VHCPVP_Cfg' ) || ! method_exists( 'VHCPVP_Cfg', 'read' )
			|| ! defined( 'VHCPVP_Cfg::USER' ) ) {
			return array();
		}
		return (array) VHCPVP_Cfg::read( VHCPVP_Cfg::USER );
	}

	public static function ghi_user( $rows ) {
		if ( ! class_exists( 'VHCPVP_Cfg' ) || ! method_exists( 'VHCPVP_Cfg', 'write' )
			|| ! defined( 'VHCPVP_Cfg::USER' ) ) {
			return false;
		}
		VHCPVP_Cfg::write( VHCPVP_Cfg::USER, $rows );
		return true;
	}

	public static function bp_chuan( $x ) {
		if ( ! class_exists( 'VHCPVP_Cfg' ) || ! method_exists( 'VHCPVP_Cfg', 'bo_phan_chuan' ) ) {
			return '';
		}
		return (string) VHCPVP_Cfg::bo_phan_chuan( $x );
	}

	public static function bp_ds() {
		if ( ! class_exists( 'VHCPVP_Cfg' ) || ! defined( 'VHCPVP_Cfg::BO_PHAN_DS' ) ) { return array(); }
		return array_values( (array) VHCPVP_Cfg::BO_PHAN_DS );
	}
}
