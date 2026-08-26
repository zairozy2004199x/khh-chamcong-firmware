<?php
/**
 * AI ĐƯỢC LÀM GÌ TRÊN TRANG NỘI BỘ.
 *
 * Anh Thắng 26/08/2026: *"phần quyền người vào chỗ nào"* — và câu trả lời lúc đó là KHÔNG CÓ
 * CHỖ NÀO. Ai có mã PIN chấm công là vào được, đăng được, bình luận được; còn ghim và xoá bài
 * thì khoá cứng ở bậc Admin ngay trong mã. Không màn nào khai, không câu nào trên trang nói ra
 * luật ấy, nên người quản trị đi tìm là đi tìm một thứ chưa từng tồn tại.
 *
 * =============================================================================================
 * 🔴 BỐN VIỆC, MỖI VIỆC MỘT BẬC — KHÔNG PHẢI BẢNG Ô TÍCH
 * =============================================================================================
 * Đi theo đúng mô hình `VHCC_Vai` đã chốt: một THANG năm bậc, bậc trên làm được mọi việc của
 * bậc dưới. Ở đây chỉ khai "việc này cần từ bậc mấy trở lên".
 *
 * Bảng ô tích rời (5 vai × 4 việc = 20 ô) thì dựng được những tổ hợp vô nghĩa — Quản lý dọn
 * được bài mà Admin thì không — và ngày nào đó có người tích nhầm.
 *
 * =============================================================================================
 * ⚠️ MẶC ĐỊNH PHẢI BẰNG ĐÚNG HÀNH VI ĐANG CHẠY
 * =============================================================================================
 * Trang này đang chạy thật, có người đang đăng bài trên đó. Bản nâng cấp mà đặt mặc định chặt
 * hơn hiện tại là sáng hôm sau cả công ty mất quyền đăng bài, và không ai hiểu vì sao — trong
 * khi họ không đổi gì cả. Nên: vào / đăng / lập nhóm = bậc Nhân viên (ai cũng được, y như
 * trước), dọn bài = bậc Admin (y như trước). Muốn siết thì Admin tự nâng lên, cố ý, thấy được.
 *
 * ⚠️ CHƯA CÀI PLUGIN CHẤM CÔNG THÌ CHO QUA. `VHCC_Vai` nằm ở plugin khác; gỡ plugin ấy ra mà
 *    ở đây trả false là cả trang nội bộ đóng cửa với mọi người, kể cả Admin — không còn ai vào
 *    để bật lại. Thiếu bộ đo bậc thì thôi không đo, chứ không chối.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Quyen {

	const O = 'vhnb_quyen';

	/** Bốn việc + bậc mặc định. Mặc định = ĐÚNG hành vi trước khi có màn này. */
	const VIEC = array(
		'vao'  => array( 'nhan' => 'Vào trang Nội bộ',                'md' => 'NHAN_VIEN' ),
		'dang' => array( 'nhan' => 'Đăng bài · bình luận · thả tim',  'md' => 'NHAN_VIEN' ),
		'nhom' => array( 'nhan' => 'Lập nhóm riêng',                  'md' => 'NHAN_VIEN' ),
		'don'  => array( 'nhan' => 'Ghim bài · xoá bài của người khác', 'md' => 'ADMIN' ),
	);

	/** Năm bậc, đúng thang của `VHCC_Vai`. Khai lại TÊN HIỆN ra màn, không khai lại luật. */
	const BAC_DS = array(
		'NHAN_VIEN'       => 'Nhân viên',
		'CUA_HANG_TRUONG' => 'Cửa hàng trưởng',
		'QUAN_LY'         => 'Quản lý',
		'KE_TOAN'         => 'Kế toán',
		'ADMIN'           => 'Admin',
	);

	/* ====================================================================== đọc / ghi */

	/** Bảng đã khai: [ việc => mã bậc ]. Việc lạ / bậc lạ bị bỏ, không nhận bừa. */
	public static function cai_dat() {
		$luu = get_option( self::O );
		$luu = is_array( $luu ) ? $luu : array();
		$ra  = array();
		foreach ( self::VIEC as $k => $v ) {
			$x = isset( $luu[ $k ] ) ? (string) $luu[ $k ] : '';
			$ra[ $k ] = isset( self::BAC_DS[ $x ] ) ? $x : $v['md'];
		}
		return $ra;
	}

	/** Lưu. Chỉ nhận việc có tên và bậc có thật — ô lạ rơi về mặc định qua `cai_dat()`. */
	public static function dat( $map ) {
		$cu  = self::cai_dat();
		$moi = $cu;
		foreach ( (array) $map as $k => $v ) {
			if ( ! isset( self::VIEC[ $k ] ) ) { continue; }
			$v = (string) $v;
			if ( isset( self::BAC_DS[ $v ] ) ) { $moi[ $k ] = $v; }
		}
		update_option( self::O, $moi );
		return $moi;
	}

	/** Bậc tối thiểu của một việc, dạng SỐ (1..5). */
	public static function bac_can( $viec ) {
		$c  = self::cai_dat();
		$ma = isset( $c[ $viec ] ) ? $c[ $viec ] : 'ADMIN';
		if ( ! class_exists( 'VHCC_Vai' ) || ! defined( 'VHCC_Vai::BAC' ) ) { return 1; }
		$bac = constant( 'VHCC_Vai::BAC' );
		return isset( $bac[ $ma ] ) ? (int) $bac[ $ma ] : 5;
	}

	/* ====================================================================== hỏi quyền */

	/**
	 * Người này có được làm việc ấy không.
	 *
	 * ⚠️ Gác `method_exists` CÙNG HÀM với lời gọi — luật của `tools/test/kiem-goi-cheo.php`.
	 *    Và thiếu `VHCC_Vai` thì CHO QUA, không chối: xem khối cảnh báo ở đầu tệp.
	 */
	public static function duoc( $u, $viec ) {
		if ( ! isset( self::VIEC[ $viec ] ) ) { return false; }
		if ( ! class_exists( 'VHCC_Vai' ) || ! method_exists( 'VHCC_Vai', 'bac' ) ) { return true; }
		return (int) VHCC_Vai::bac( $u ) >= self::bac_can( $viec );
	}

	/** Câu chối nói RÕ cần bậc nào — '' là được phép. */
	public static function vi_sao_khong( $u, $viec ) {
		if ( self::duoc( $u, $viec ) ) { return ''; }
		$c   = self::cai_dat();
		$ma  = isset( $c[ $viec ] ) ? $c[ $viec ] : 'ADMIN';
		$ten = isset( self::BAC_DS[ $ma ] ) ? self::BAC_DS[ $ma ] : $ma;
		$nh  = isset( self::VIEC[ $viec ] ) ? self::VIEC[ $viec ]['nhan'] : $viec;
		/* Nói ra BẬC CẦN, không nói trống "không đủ quyền". Người đọc cần biết phải xin ai. */
		return 'Việc "' . $nh . '" cần vai từ ' . $ten . ' trở lên.';
	}
}
