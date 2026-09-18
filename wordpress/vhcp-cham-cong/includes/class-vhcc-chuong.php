<?php
/**
 * CHUÔNG THÔNG BÁO TRÊN TRẠM — cửa sổ nhìn vào hộp thư của trang Nội bộ.
 *
 * Anh Thắng 17/09/2026: *"Còn chuông thông báo ở đâu. A chưa thấy"* — anh mở trạm chấm công
 * và tìm cái chuông, vì bên Nội bộ có. Trước lượt này trạm KHÔNG có chuông nào: tin "cửa hàng
 * trưởng vừa sửa giờ công của bạn" do `VHCC_Bu` gửi đi nằm bên Nội bộ, mà người chấm công thì
 * cả ngày ở trạm. Tin có, chỗ đọc có, chỉ là hai chỗ khác nhau.
 *
 * =============================================================================================
 * 🔴 KHÔNG ĐẺ HỘP THƯ THỨ HAI. ĐÂY LÀ QUYẾT ĐỊNH DUY NHẤT ĐÁNG ĐỌC TRONG TỆP NÀY
 * =============================================================================================
 * Cách dễ viết hơn là dựng một bảng `vhcc_bao` riêng cho trạm. Làm thế thì mỗi bên giữ một
 * nửa: đọc chuông ở trạm xong sang Nội bộ con số vẫn đỏ, và ngược lại. Người ta sẽ đọc hai
 * lần cùng một tin rồi thôi không mở cái nào nữa — đúng thứ `VHNB_Bao` đã cảnh báo ở đầu tệp
 * của nó ("chuông thành chỗ không ai mở").
 *
 * Nên ở đây KHÔNG CÓ BẢNG NÀO. Lớp này chỉ là một cửa sổ: đọc thẳng hộp thư `VHNB_Bao`, đánh
 * dấu đã đọc thẳng vào đó. Một hộp thư, hai chỗ mở ra xem, con số luôn khớp.
 *
 * ⚠️ HỆ QUẢ: GỠ PLUGIN NỘI BỘ RA LÀ MẤT CHUÔNG. Đó là lựa chọn có chủ ý, không phải sơ sót.
 *    `co()` trả false thì trạm giấu hẳn cái chuông đi. Treo một cái chuông rỗng đời đời không
 *    bao giờ kêu còn tệ hơn không treo.
 *
 * =============================================================================================
 * 🔴 CHỈ ĐỌC HỘP THƯ CỦA CHÍNH MÌNH
 * =============================================================================================
 * Mọi hàm ở đây lấy mã NV từ `$u` — thẻ phiên do máy chủ cấp — chứ KHÔNG nhận mã NV từ thân
 * yêu cầu. Nhận từ thân là gửi lên mã người khác thì đọc được hộp thư người ta. Chuông không
 * có bậc quyền nào cả: cấp cao tới đâu cũng chỉ đọc hộp thư của mình.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Chuong {

	/** Trần một lượt kéo về. Chuông trên điện thoại không ai cuộn quá ngần này. */
	const TOI_DA = 30;

	/**
	 * Hộp thư có chạy được không.
	 *
	 * ⚠️ Kiểm cả `class_exists` LẪN `method_exists` của TỪNG hàm sẽ gọi, trong cùng một thân
	 *    hàm — cài bản Nội bộ cũ thì lớp có mà hàm chưa có, và lúc ấy lỗi rơi ra giữa lượt
	 *    JSON của trạm. `tools/test/kiem-goi-cheo.php` chốt đúng luật này.
	 */
	public static function co() {
		return class_exists( 'VHNB_Bao' )
			&& method_exists( 'VHNB_Bao', 'nhan_dem' )
			&& method_exists( 'VHNB_Bao', 'ds' )
			&& method_exists( 'VHNB_Bao', 'danh_dau_doc' );
	}

	/** Mã NV của người đang cầm thẻ phiên. Rỗng = không có hộp thư (tài khoản chưa gắn mã). */
	private static function ma( $u ) {
		return ( is_array( $u ) && isset( $u['ma_nv'] ) ) ? trim( (string) $u['ma_nv'] ) : '';
	}

	/**
	 * Chuỗi hiện trên chuông: '' · '3' · '99+'. Rỗng thì trạm không vẽ chấm đỏ.
	 *
	 * ⚠️ Gác lại `class_exists` + `method_exists` NGAY TRONG THÂN HÀM NÀY, dù `co()` ở trên đã
	 *    kiểm y hệt. Gọi `co()` rồi tin là đủ thì hôm nào ai sửa `co()` cho nới ra, chỗ này
	 *    gọi hụt mà không có gì báo. `tools/test/kiem-goi-cheo.php` chốt đúng luật ấy — đừng
	 *    rút gọn ba dòng này thành một lời gọi `co()`.
	 */
	public static function dem( $u ) {
		$ma = self::ma( $u );
		if ( '' === $ma ) { return ''; }
		if ( ! class_exists( 'VHNB_Bao' ) || ! method_exists( 'VHNB_Bao', 'nhan_dem' ) ) { return ''; }
		return (string) VHNB_Bao::nhan_dem( $ma );
	}

	/**
	 * Danh sách tin của chính mình, mới nhất trước, chưa đọc đứng trên.
	 *
	 * ⚠️ CHỈ TRẢ NĂM TRƯỜNG. Hàng trong bảng `bao` còn có `khoa` — khoá gộp nội bộ, mang theo
	 *    id của bài viết hoặc của ngày công. Nó không giúp gì cho người đọc mà lại nói ra sơ
	 *    đồ bên trong, nên cắt ở đây chứ không để trạm tự lờ đi.
	 */
	public static function ds( $u ) {
		$ma = self::ma( $u );
		$duoc = class_exists( 'VHNB_Bao' ) && method_exists( 'VHNB_Bao', 'ds' );
		if ( '' === $ma || ! $duoc ) {
			return array( 'ok' => true, 'co' => false, 'dem' => '', 'ds' => array() );
		}

		$ra = array();
		foreach ( VHNB_Bao::ds( $ma, self::TOI_DA ) as $r ) {
			$ra[] = array(
				'id'      => (int) $r['id'],
				'nguon'   => (string) $r['nguon'],
				'chu'     => (string) $r['chu'],
				'soLan'   => (int) $r['so_lan'],
				'daDoc'   => ! empty( $r['da_doc'] ),
				'luc'     => (string) $r['tao_luc'],
				/* Đường dẫn của Nội bộ là đường TRONG trang Nội bộ. Trạm mở nó ở tab mới chứ
				   không điều hướng cả trạm sang đó — đang chấm công dở mà bị đá đi là mất
				   lượt chấm. Xem `chuongDi()` bên tram.php. */
				'duongDan' => (string) $r['duong_dan'],
			);
		}

		return array( 'ok' => true, 'co' => true, 'dem' => self::dem( $u ), 'ds' => $ra );
	}

	/**
	 * Đánh dấu đã đọc. `$id = 0` là đánh dấu TẤT CẢ.
	 *
	 * ⚠️ KHÔNG tự đánh dấu lúc vẽ danh sách. Mở chuông ra rồi tải lại trang mà con số đã về 0
	 *    thì người ta chưa kịp đọc gì đã mất dấu. Trạm gọi hàm này khi người ta bấm vào tin,
	 *    hoặc bấm "Đọc hết" — một cú chạm có chủ ý.
	 */
	public static function doc( $u, $id = 0 ) {
		$ma = self::ma( $u );
		$duoc = class_exists( 'VHNB_Bao' ) && method_exists( 'VHNB_Bao', 'danh_dau_doc' );
		if ( '' === $ma || ! $duoc ) {
			return array( 'ok' => false, 'error' => 'Chưa cài trang Nội bộ nên chưa có hộp thư.' );
		}
		VHNB_Bao::danh_dau_doc( $ma, (int) $id );
		return array( 'ok' => true, 'dem' => self::dem( $u ) );
	}
}
