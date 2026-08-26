<?php
/**
 * THÔNG TIN CÔNG TY Ở CUỐI TRANG.
 *
 * Anh Thắng 26/08/2026: *"nhớ bổ sung thông tin công ty ở cuối trang nhé"* — kèm ảnh trang
 * quản trị chấm công. Trước đó đã làm cho trang chủ K&H và trang Ghế.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BỐN TRANG, MỘT KHO. KHÔNG GÕ LẠI SỐ LIỆU Ở ĐÂY.
 *
 *    Địa chỉ công ty, người đại diện, số điện thoại đều đổi được. Mỗi plugin giữ một bản chép
 *    là bốn nơi phải sửa, và nơi nào quên thì im lặng nói sai — đúng chỗ đặt ra để tạo tin cậy.
 *
 *    Kho THẬT là một ô cài đặt của WordPress: `vhg_chan`. Ô ấy nằm trong bảng `wp_options`,
 *    tức là MỌI plugin trên cùng website đều đọc được, không cần plugin nào phải có mặt. Anh
 *    Thắng sửa một lần ở màn quản trị của plugin Ghế thì cả bốn trang đổi theo.
 *
 * ⚠️ CHỈ BẢNG MẶC ĐỊNH là còn phải chép — dùng khi ô cài đặt chưa từng được lưu. Plugin này
 *    KHÔNG giữ bản chép nào: nó hỏi `VHG_Chan` rồi hỏi `VHTC_Trang`, plugin nào có mặt thì lấy.
 *    Không có plugin nào và cũng chưa ai lưu ô cài đặt -> KHÔNG vẽ chân trang. Đúng: chưa ai
 *    nói cho website này biết công ty là ai thì bịa ra một cái tên còn tệ hơn để trống.
 *
 * ⚠️ KHÔNG đọc cờ `hien`. Cờ ấy nói *"có hiện chân trang trên trang Ghế không"* — tắt nó để
 *    trang bán mã gọn hơn mà kéo theo cả trang chấm công mất phần giới thiệu công ty thì không
 *    ai đoán ra vì sao.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Cty {

	/** Ô cài đặt dùng chung. Tên giữ nguyên của plugin Ghế — đổi tên là mất dữ liệu đã lưu. */
	const O = 'vhg_chan';

	/** Những ô trang này in ra. Bản kia thêm ô mới thì trang này không tự in một thứ chưa ai xem. */
	const CAC_O = array( 'ten', 'ten_qt', 'mst', 'dia_chi', 'dai_dien', 'dien_thoai', 'email',
		'ngay_hd', 'co_quan', 'chi_nhanh' );

	/**
	 * Bảng mặc định — hỏi plugin khác, KHÔNG tự khai.
	 *
	 * ⚠️ Gác `method_exists` / `defined` ngay tại đây, cùng hàm với lời gọi. Gọi hụt một hàm
	 *    tĩnh hay đọc một hằng của lớp không có là Fatal error, TRẮNG CẢ TRANG — xem
	 *    `tools/test/kiem-goi-cheo.php`.
	 */
	public static function mac_dinh() {
		if ( class_exists( 'VHG_Chan' ) && method_exists( 'VHG_Chan', 'mac_dinh' ) ) {
			$m = VHG_Chan::mac_dinh();
			if ( is_array( $m ) && ! empty( $m['ten'] ) ) { return $m; }
		}
		if ( class_exists( 'VHTC_Trang' ) && defined( 'VHTC_Trang::CTY_DU_PHONG' ) ) {
			$m = (array) constant( 'VHTC_Trang::CTY_DU_PHONG' );
			if ( ! empty( $m['ten'] ) ) { return $m; }
		}
		return array();
	}

	/** Thông tin đang dùng: ô cài đặt đè lên bảng mặc định. Rỗng hết thì trả mảng rỗng. */
	public static function thong_tin() {
		$o  = get_option( self::O );
		$o  = is_array( $o ) ? $o : array();
		$md = self::mac_dinh();

		$ra = array();
		foreach ( self::CAC_O as $k ) {
			/* `array_key_exists` chứ không phải `! empty`: ô cố ý để TRỐNG (ví dụ chưa có email)
			   phải giữ được trạng thái trống, không bị mặc định nhảy vào lấp. */
			if ( array_key_exists( $k, $o ) )       { $ra[ $k ] = (string) $o[ $k ]; }
			elseif ( array_key_exists( $k, $md ) )  { $ra[ $k ] = (string) $md[ $k ]; }
			else                                    { $ra[ $k ] = ''; }
		}
		return ( '' === trim( $ra['ten'] ) ) ? array() : $ra;
	}

	/** Chi nhánh: mỗi dòng một cái. */
	public static function ds_chi_nhanh( $t ) {
		$ra = array();
		foreach ( preg_split( '/[\r\n]+/', (string) $t ) as $d ) {
			$d = trim( $d );
			if ( '' !== $d ) { $ra[] = $d; }
		}
		return $ra;
	}

	/**
	 * Chân trang, dạng chuỗi.
	 *
	 * ⚠️ Mọi lớp mang tiền tố `cty-`. Chân trang này chèn vào mấy trang có bộ kiểu chữ riêng,
	 *    và các trang ấy dùng tên lớp rất ngắn (`.the`, `.mo`, `.cd`). Đặt trùng tên là chân
	 *    trang bẻ giao diện của trang chứa nó, hoặc ngược lại.
	 */
	public static function html() {
		$t = self::thong_tin();
		if ( ! $t ) { return ''; }

		/* Ô nào TRỐNG thì bỏ hẳn dòng, không in nhãn treo lơ lửng: "Email:" mà sau nó không có
		   gì trông như trang hỏng, chứ không phải như công ty chưa khai email. */
		$dong = function ( $nhan, $gt, $them = '' ) {
			if ( '' === trim( (string) $gt ) ) { return ''; }
			return '<div class="cty-d"><span>' . esc_html( $nhan ) . '</span> '
				. ( '' !== $them ? $them : esc_html( $gt ) ) . '</div>';
		};

		$h = '<footer class="cty"><div class="cty-in">';

		$h .= '<div class="cty-cot">'
			. '<div class="cty-ten">' . esc_html( $t['ten'] ) . '</div>'
			. ( '' !== trim( $t['ten_qt'] )
				? '<div class="cty-d cty-qt">' . esc_html( $t['ten_qt'] ) . '</div>' : '' )
			. $dong( 'Mã số thuế / Tax code:', $t['mst'] )
			. $dong( 'Người đại diện / Legal rep.:', $t['dai_dien'] )
			. $dong( 'Hoạt động từ / Since:', $t['ngay_hd'] )
			. '</div>';

		$h .= '<div class="cty-cot">'
			. $dong( 'Địa chỉ / Address:', $t['dia_chi'] )
			/* Số điện thoại bấm gọi được ngay — nửa số người mở mấy trang này đang cầm điện
			   thoại, đừng bắt họ bôi đen rồi chép sang ứng dụng gọi. */
			. $dong( 'Điện thoại / Phone:', $t['dien_thoai'],
				'<a href="tel:' . esc_attr( preg_replace( '/\D+/', '', $t['dien_thoai'] ) ) . '">'
					. esc_html( $t['dien_thoai'] ) . '</a>' )
			. $dong( 'Email:', $t['email'],
				'<a href="mailto:' . esc_attr( $t['email'] ) . '">' . esc_html( $t['email'] ) . '</a>' )
			. $dong( 'Cơ quan quản lý thuế:', $t['co_quan'] )
			. '</div>';

		$cn = self::ds_chi_nhanh( $t['chi_nhanh'] );
		if ( $cn ) {
			$h .= '<div class="cty-cot"><div class="cty-d"><span>Chi nhánh / Branches:</span></div>'
				. '<div>' . esc_html( implode( ' · ', $cn ) ) . '</div></div>';
		}

		$h .= '</div><div class="cty-bq">© '
			. esc_html( gmdate( 'Y', current_time( 'timestamp' ) ) ) . ' '
			. esc_html( $t['ten'] ) . '. Toàn bộ bản quyền thuộc công ty. / All rights reserved.'
			. '</div></footer>';
		return $h;
	}

	/** Kiểu chữ — nền sáng, hợp với trang chấm công và trang nội bộ. */
	public static function css() {
		/* ⚠️ KHÔNG tự đặt `max-width` ở đây. Chân trang này chèn vào mấy trang có khung riêng
		   (`.bo` rộng 1760px ở trang chấm công, 760px ở trang nội bộ). Tự đặt khung là hai
		   khung chồng nhau, và chân trang thụt vào khác phần trên — cũng lệch, chỉ lệch kiểu
		   khác. Nơi gọi có trách nhiệm bọc nó vào đúng khung của trang mình. */
		return '.cty{margin:26px 0 0;padding:20px 0 30px;border-top:1px solid #e2e8f0;'
			. 'color:#64748b;font-size:12.5px;line-height:1.7}'
			/* Ba cột tự giãn: `flex:1 1 250px` nghĩa là cột nào cũng phải rộng ít nhất 250px mới
			   đứng cạnh nhau — hẹp hơn thì tự xuống dòng, khỏi cần mốc @media nào. */
			. '.cty-in{display:flex;flex-wrap:wrap;gap:16px 34px}'
			. '.cty-cot{flex:1 1 250px;min-width:0}'
			. '.cty-ten{color:#0f172a;font-weight:700;margin-bottom:4px;font-size:13px}'
			. '.cty-qt{font-style:italic;color:#94a3b8;margin-bottom:4px}'
			. '.cty-d span{color:#94a3b8}'
			. '.cty a{color:#2563eb;text-decoration:none}'
			. '.cty a:hover{text-decoration:underline}'
			. '.cty-bq{margin-top:14px;padding-top:11px;border-top:1px solid #f1f5f9;color:#94a3b8;'
			. 'font-size:12px}'
			. '@media(max-width:640px){.cty{font-size:12px;padding:16px 0 22px}.cty-in{gap:13px}}'
			/* Bản in: chân trang pháp lý là thứ ĐÁNG in — bảng công ký đưa kế toán thì tờ giấy
			   phải nói rõ của công ty nào. Mấy thứ khác (thanh nút, biểu mẫu) đã bị ẩn sẵn. */
			. '@media print{.cty{border-top:1px solid #999;color:#000}.cty a{color:#000}}';
	}
}
