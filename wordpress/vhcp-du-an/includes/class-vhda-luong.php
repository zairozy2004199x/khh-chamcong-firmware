<?php
/**
 * LUỒNG MỘT DỰ ÁN — BẢY CHẶNG, VÀ LUẬT ĐI GIỮA CHÚNG.
 *
 * Anh Thắng 30/08/2026: *"Sếp nhận hợp đồng, lên phương án, sau đó chốt ngày thi công, mở cửa,
 * và bàn giao xuống từng bộ phận. Các bộ phận làm và cập nhật tiến độ vào đó."*
 *
 * =============================================================================================
 * 🔴 LUỒNG LÀ HÀM THUẦN, KHÔNG ĐỤNG CSDL, KHÔNG ĐỤNG PHIÊN
 * =============================================================================================
 * Cả tệp này chỉ nhận vào mấy chuỗi và trả ra mấy chuỗi. Nhờ vậy bộ thử dựng được MỌI cảnh —
 * kể cả cảnh không bao giờ bấm ra được trên màn hình (chặng lạ, nhảy cóc, quay ngược) — mà
 * không cần bảng, không cần đăng nhập, không cần trang.
 *
 * Chỗ nào cần biết ai được phép chuyển thì hỏi `VHDA_Quyen`; chỗ nào cần ghi thì hỏi
 * `VHDA_DuAn`. Trộn ba thứ ấy vào nhau là thứ làm mọi hệ quy trình rối lên sau vài tháng.
 *
 * =============================================================================================
 * ⚠️ ĐI TỚI TỪNG BƯỚC, NHƯNG LÙI THÌ ĐƯỢC LÙI XA
 * =============================================================================================
 * Nhảy cóc về phía trước bị chặn: "Nhận hợp đồng" mà bấm thẳng sang "Mở cửa" là bỏ qua cả
 * phương án lẫn bàn giao, và không ai biết hai chặng ấy đã bị bỏ. Ngược lại LÙI thì cho lùi
 * tự do — thực tế hay có: chốt ngày rồi khách đổi ý, phải quay lại phương án. Bắt lùi từng
 * bước là bắt người ta bấm bốn lần cho một việc, rồi họ sẽ đi sửa thẳng cơ sở dữ liệu.
 *
 * ⚠️ HUỶ đứng ngoài dãy: từ chặng nào cũng huỷ được, và huỷ rồi thì chỉ mở lại về đúng chặng
 *    đang dở trước đó — chứ không phải về đầu.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHDA_Luong {

	/* Mã chặng — LƯU MÃ, KHÔNG LƯU TÊN TIẾNG VIỆT. Đổi tên hiện ra màn thì sửa `TEN`; đổi mã
	   là mọi dự án đã lưu trỏ vào một chặng không còn tồn tại. */
	const HOP_DONG  = 'hop_dong';
	const PHUONG_AN = 'phuong_an';
	const CHOT_NGAY = 'chot_ngay';
	const BAN_GIAO  = 'ban_giao';
	const THI_CONG  = 'thi_cong';
	const MO_CUA    = 'mo_cua';
	const XONG      = 'xong';
	const HUY       = 'huy';

	/** Bảy chặng theo ĐÚNG thứ tự đi. `huy` không nằm trong dãy — xem ghi chú đầu tệp. */
	const DAY = array(
		self::HOP_DONG, self::PHUONG_AN, self::CHOT_NGAY,
		self::BAN_GIAO, self::THI_CONG, self::MO_CUA, self::XONG,
	);

	const TEN = array(
		self::HOP_DONG  => 'Nhận hợp đồng',
		self::PHUONG_AN => 'Lên phương án',
		self::CHOT_NGAY => 'Chốt ngày thi công',
		self::BAN_GIAO  => 'Bàn giao bộ phận',
		self::THI_CONG  => 'Đang thi công',
		self::MO_CUA    => 'Mở cửa',
		self::XONG      => 'Xong · nghiệm thu',
		self::HUY       => 'Đã huỷ',
	);

	/** Một câu nói rõ chặng ấy CHỜ AI LÀM GÌ — để người mở màn hình biết việc tiếp theo. */
	const CHO = array(
		self::HOP_DONG  => 'Chờ lên phương án',
		self::PHUONG_AN => 'Chờ chốt ngày thi công và ngày mở cửa',
		self::CHOT_NGAY => 'Chờ bàn giao xuống các bộ phận',
		self::BAN_GIAO  => 'Chờ các bộ phận bắt tay vào việc',
		self::THI_CONG  => 'Các bộ phận đang làm — theo dõi tiến độ bên dưới',
		self::MO_CUA    => 'Đã mở cửa — chờ nghiệm thu và tất toán chi phí',
		self::XONG      => 'Đã xong',
		self::HUY       => 'Đã huỷ',
	);

	public static function co( $ma ) { return isset( self::TEN[ (string) $ma ] ); }

	public static function ten( $ma ) {
		$ma = (string) $ma;
		return isset( self::TEN[ $ma ] ) ? self::TEN[ $ma ] : $ma;
	}

	public static function cho( $ma ) {
		$ma = (string) $ma;
		return isset( self::CHO[ $ma ] ) ? self::CHO[ $ma ] : '';
	}

	/** Vị trí trong dãy, hoặc -1 nếu không nằm trong dãy (`huy`, hoặc mã lạ). */
	public static function vi_tri( $ma ) {
		$i = array_search( (string) $ma, self::DAY, true );
		return ( false === $i ) ? -1 : (int) $i;
	}

	/**
	 * ĐI TỪ CHẶNG NÀY SANG CHẶNG KIA CÓ HỢP LỆ KHÔNG — trả câu lỗi, '' là được.
	 *
	 * 🔴 TRẢ CÂU LỖI CHỨ KHÔNG TRẢ TRUE/FALSE. Người bị chặn cần biết VÌ SAO; một chữ `false`
	 *    đi lên tới màn hình thì thành câu "không hợp lệ", và người ta sẽ bấm lại đúng cái nút
	 *    ấy thêm mấy lần nữa.
	 */
	public static function vi_sao_khong_di( $tu, $den ) {
		$tu  = (string) $tu;
		$den = (string) $den;
		if ( ! self::co( $tu ) )  { return 'Chặng hiện tại không hợp lệ: ' . $tu; }
		if ( ! self::co( $den ) ) { return 'Chặng muốn chuyển tới không có thật: ' . $den; }
		if ( $tu === $den )       { return 'Dự án đang ở chặng "' . self::ten( $den ) . '" rồi.'; }

		/* HUỶ: từ đâu cũng huỷ được, trừ khi đã huỷ rồi (đã chặn ở trên). */
		if ( self::HUY === $den ) { return ''; }

		/* MỞ LẠI từ trạng thái huỷ: nơi gọi tự chọn chặng cũ, ở đây chỉ cần nó nằm trong dãy. */
		if ( self::HUY === $tu ) { return ''; }

		$i = self::vi_tri( $tu );
		$j = self::vi_tri( $den );
		if ( $j < $i ) { return ''; }              // lùi: cho lùi xa, xem ghi chú đầu tệp
		if ( $j === $i + 1 ) { return ''; }        // tiến đúng một bước

		/* Nhảy cóc về phía trước. Nói rõ chặng đang bị bỏ qua — "không hợp lệ" thì người dùng
		   không biết mình thiếu bước nào. */
		$bo = array();
		for ( $k = $i + 1; $k < $j; $k++ ) { $bo[] = self::ten( self::DAY[ $k ] ); }
		return 'Chưa qua ' . implode( ' → ', $bo ) . ' thì chưa sang được "'
			. self::ten( $den ) . '".';
	}

	/** Chặng kế tiếp trong dãy, '' nếu đã cuối dãy hoặc không nằm trong dãy. */
	public static function ke_tiep( $ma ) {
		$i = self::vi_tri( $ma );
		if ( $i < 0 || $i + 1 >= count( self::DAY ) ) { return ''; }
		return self::DAY[ $i + 1 ];
	}

	/**
	 * Đã đi được bao nhiêu phần trăm dãy — để vẽ thanh tiến độ ở đầu mỗi dự án.
	 *
	 * ⚠️ HUỶ trả 0, KHÔNG trả phần trăm của chặng cũ: một dự án đã huỷ mà thanh tiến độ vẫn
	 *    xanh 70% là mắt đọc nhầm nó vẫn đang chạy.
	 */
	public static function phan_tram( $ma ) {
		$i = self::vi_tri( $ma );
		if ( $i < 0 ) { return 0; }
		return (int) round( $i * 100 / ( count( self::DAY ) - 1 ) );
	}
}
