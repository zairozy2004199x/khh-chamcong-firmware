<?php
/**
 * CHÂN TRANG — THÔNG TIN PHÁP LÝ CỦA CÔNG TY
 *
 * Anh Thắng 23/08/2026: *"cuối trang bổ sung nội dung này cho uy tín, các wed đã làm luôn nhé"*
 * — kèm ảnh chân trang VnExpress và ảnh tra cứu doanh nghiệp của K&H.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 VÌ SAO KHAI ĐƯỢC, KHÔNG NHÉT CỨNG VÀO MÃ.
 *
 *    Địa chỉ công ty đổi, người đại diện đổi, số điện thoại đổi. Nhét cứng là mỗi lần đổi phải
 *    sửa mã, đóng gói lại, cài lại — cho một dòng chữ. Và trong lúc chờ thì trang đang nói sai
 *    thông tin pháp lý của chính mình, đúng chỗ đặt ra để tạo tin cậy.
 *
 *    Điền sẵn đúng dữ liệu anh Thắng gửi, nên cài xong là chạy luôn; muốn sửa thì vào màn quản
 *    trị, không cần ai.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 MỘT NƠI DỰNG, HAI TRANG DÙNG.
 *
 *    Trang khách (`/mua-ma`) và trang nhân viên (`/ghe`) cùng gọi `html()`. Chép ra hai bản là
 *    kiểu lỗi đã cắn dự án này năm lần trong một ngày (nội dung chuyển khoản thiếu tiền tố,
 *    địa chỉ tem viết hoa, cỡ vùng vẽ 58/70, hai chỗ dựng mã QR...): sửa một nơi, quên nơi
 *    kia, và nơi quên thì im lặng nói sai.
 *
 * ⚠️ Nhãn để song ngữ Việt/Anh ngay trong một dòng ("Mã số thuế / Tax code"). Trang khách có
 *    bốn thứ tiếng, nhưng mã số thuế và địa chỉ đăng ký là SỰ KIỆN PHÁP LÝ tiếng Việt — dịch
 *    chúng ra tiếng Nga là tạo ra một địa chỉ không tra cứu được ở đâu cả. Khách nước ngoài
 *    cần biết mình đang trả tiền cho ai, và tên quốc tế cùng nhãn tiếng Anh là đủ cho việc đó.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Chan {

	/**
	 * Điền sẵn theo bản tra cứu doanh nghiệp anh Thắng gửi (23/08/2026).
	 *
	 * ⚠️ Đây là MẶC ĐỊNH, không phải sự thật cố định. Ai cũng sửa được ở màn quản trị, và khi
	 *    đã sửa thì bảng này không còn được đọc tới nữa.
	 */
	public static function mac_dinh() {
		return array(
			'ten'      => 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H',
			'ten_qt'   => 'K&H SERVICES AND ENTERTAINMENT COMPANY LIMITED',
			'mst'      => '0106924989',
			'dia_chi'  => 'Thôn Mai Nội, Xã Sóc Sơn, Thành phố Hà Nội, Việt Nam',
			'dai_dien' => 'Nguyễn Văn Kiên',
			'dien_thoai' => '0435961469',
			'email'    => '',
			'ngay_hd'  => '05/08/2015',
			'co_quan'  => 'Thuế cơ sở 18 thành phố Hà Nội',
			'chi_nhanh' => "Đà Nẵng\nHải Phòng\nBình Dương\nThành phố Hồ Chí Minh\nSense City Hồ Chí Minh\nNha Trang",
			'hien'     => 1,
		);
	}

	/** Thông tin đang dùng: ô nào khai rồi thì lấy, chưa khai thì lấy mặc định. */
	public static function thong_tin() {
		$o  = get_option( 'vhg_chan' );
		$o  = is_array( $o ) ? $o : array();
		$md = self::mac_dinh();
		$ra = array();
		foreach ( $md as $k => $v ) {
			/* ⚠️ `isset` chứ không phải `! empty`: ô cố ý để TRỐNG (VD chưa có email) phải giữ
			   được trạng thái trống, không bị mặc định nhảy vào lấp. Riêng `hien` là cờ bật/tắt
			   nên xử riêng ở dưới. */
			$ra[ $k ] = array_key_exists( $k, $o ) ? (string) $o[ $k ] : (string) $v;
		}
		/* Cờ hiện: chưa khai bao giờ = BẬT. `(int) false === 0` nên so sánh kiểu số ở đây là mặc
		   định thành tắt — đúng cái bẫy đã cắn ô quảng cáo ngày 23/08/2026. */
		$ra['hien'] = array_key_exists( 'hien', $o ) ? (bool) (int) $o['hien'] : true;
		return $ra;
	}

	/** Lưu. Cắt độ dài để một lần dán nhầm cả trang web vào ô không làm vỡ chân trang. */
	public static function luu( $d ) {
		$d  = (array) $d;
		$md = self::mac_dinh();
		$ra = array();
		foreach ( $md as $k => $v ) {
			if ( 'hien' === $k ) { continue; }
			$dai = 'chi_nhanh' === $k ? 600 : 250;
			$ra[ $k ] = mb_substr( trim( (string) ( isset( $d[ $k ] ) ? $d[ $k ] : '' ) ), 0, $dai );
		}
		$ra['hien'] = empty( $d['hien'] ) ? 0 : 1;
		update_option( 'vhg_chan', $ra );
		return array( 'ok' => true, 'thong_bao' => $ra['hien']
			? 'Đã lưu chân trang. Hiện ở cuối trang khách và trang nhân viên.'
			: 'Đã lưu, và ĐANG TẮT — chân trang không hiện ở đâu cả.' );
	}

	/** Danh sách chi nhánh, mỗi dòng một cái. */
	public static function ds_chi_nhanh() {
		$t  = (string) self::thong_tin()['chi_nhanh'];
		$ra = array();
		foreach ( preg_split( '/[\r\n]+/', $t ) as $d ) {
			$d = trim( $d );
			if ( '' !== $d ) { $ra[] = $d; }
		}
		return $ra;
	}

	/**
	 * Chân trang, dựng sẵn ở MÁY CHỦ.
	 *
	 * 🔴 DỰNG Ở MÁY CHỦ, KHÔNG DỰNG BẰNG JS. Hai trang này đều là ứng dụng một trang: `#app`
	 *    rỗng cho tới khi JS chạy xong. Đặt chân trang vào JS là JS hỏng (mạng chập, trình duyệt
	 *    cũ, tường lửa hosting chèn trang chặn) thì thông tin pháp lý biến mất — đúng lúc khách
	 *    đang phân vân không biết mình chuyển tiền cho ai. Nằm thẳng trong HTML thì nó luôn ở đó.
	 */
	public static function html() {
		$t = self::thong_tin();
		if ( empty( $t['hien'] ) || '' === trim( (string) $t['ten'] ) ) { return ''; }

		$dong = function ( $nhan, $gt, $them = '' ) {
			if ( '' === trim( (string) $gt ) ) { return ''; }
			return '<div class="vhg-cd"><span>' . esc_html( $nhan ) . '</span> '
				. ( '' !== $them ? $them : esc_html( $gt ) ) . '</div>';
		};

		$h = '<footer class="vhg-chan"><div class="vhg-chan-in">';

		$h .= '<div class="vhg-cot">'
			. '<div class="vhg-ten">' . esc_html( $t['ten'] ) . '</div>'
			. ( '' !== trim( (string) $t['ten_qt'] )
				? '<div class="vhg-cd vhg-qt">' . esc_html( $t['ten_qt'] ) . '</div>' : '' )
			. $dong( 'Mã số thuế / Tax code:', $t['mst'] )
			. $dong( 'Người đại diện / Legal rep.:', $t['dai_dien'] )
			. $dong( 'Hoạt động từ / Since:', $t['ngay_hd'] )
			. '</div>';

		$h .= '<div class="vhg-cot">'
			. $dong( 'Địa chỉ / Address:', $t['dia_chi'] )
			/* Điện thoại và email là ĐƯỜNG DÂY THẬT — bấm được ngay trên điện thoại, đừng bắt
			   khách bôi đen rồi chép. Đây là chỗ họ tìm khi có chuyện với tiền của mình. */
			. $dong( 'Điện thoại / Phone:', $t['dien_thoai'],
				'<a href="tel:' . esc_attr( preg_replace( '/\D+/', '', (string) $t['dien_thoai'] ) ) . '">'
					. esc_html( $t['dien_thoai'] ) . '</a>' )
			. $dong( 'Email:', $t['email'],
				'<a href="mailto:' . esc_attr( $t['email'] ) . '">' . esc_html( $t['email'] ) . '</a>' )
			. $dong( 'Cơ quan quản lý thuế:', $t['co_quan'] )
			. '</div>';

		$cn = self::ds_chi_nhanh();
		if ( $cn ) {
			$h .= '<div class="vhg-cot"><div class="vhg-cd"><span>Chi nhánh / Branches:</span></div>'
				. '<div class="vhg-cn">' . esc_html( implode( ' · ', $cn ) ) . '</div></div>';
		}

		$h .= '</div><div class="vhg-ban-quyen">© ' . esc_html( gmdate( 'Y', current_time( 'timestamp' ) ) )
			. ' ' . esc_html( $t['ten'] ) . '. '
			. 'Toàn bộ bản quyền thuộc công ty. / All rights reserved.</div></footer>';
		return $h;
	}

	/**
	 * Kiểu chữ cho chân trang.
	 *
	 * ⚠️ Mọi lớp đều mang tiền tố `vhg-chan`. Chân trang này chèn vào HAI trang có bộ kiểu chữ
	 *    riêng, và cả hai đều dùng những tên lớp ngắn (`.cot`, `.ten`, `.mut`). Đặt trùng tên là
	 *    chân trang bẻ giao diện của trang chứa nó, hoặc ngược lại.
	 */
	public static function css() {
		return '.vhg-chan{margin:34px auto 0;padding:20px 16px 26px;max-width:1180px;'
			. 'border-top:1px solid rgba(255,255,255,.12);color:#a9a091;font-size:12.5px;line-height:1.65}'
			. '.vhg-chan-in{display:flex;flex-wrap:wrap;gap:18px 34px}'
			. '.vhg-chan .vhg-cot{flex:1 1 260px;min-width:0}'
			. '.vhg-chan .vhg-ten{color:#e8dcc4;font-weight:700;margin-bottom:5px}'
			. '.vhg-chan .vhg-qt{color:#8d8577;font-style:italic;margin-bottom:5px}'
			. '.vhg-chan .vhg-cd span{color:#7f7768}'
			. '.vhg-chan .vhg-cn{color:#a9a091}'
			. '.vhg-chan a{color:#d9b86a;text-decoration:none}'
			. '.vhg-chan a:hover{text-decoration:underline}'
			. '.vhg-ban-quyen{margin-top:16px;padding-top:12px;'
			. 'border-top:1px solid rgba(255,255,255,.07);color:#7f7768}'
			/* Điện thoại: một cột, chữ nhỏ hơn chút. Ba cột trên màn 360px là mỗi cột một chữ. */
			. '@media(max-width:640px){.vhg-chan{font-size:12px;padding:16px 14px 22px}'
			. '.vhg-chan-in{gap:14px}}';
	}

	/**
	 * BẢN NỀN SÁNG — cho app Vận Hành Chi Phí (nền trắng/xám), khác hai trang ghế nền tối.
	 *
	 * ⚠️ Chỉ đổi MÀU, giữ nguyên bố cục của css(). Để chung một tệp với html() vì đây vẫn là
	 *    chuyện "chân trang trông thế nào" — tách màu sang plugin khác là lại hai nơi giữ một
	 *    sự thật, đúng kiểu lỗi mà chú thích đầu tệp này đang cảnh báo.
	 */
	public static function css_sang() {
		return self::css()
			. '.vhg-chan{border-top-color:#e2e8f0;color:#64748b}'
			. '.vhg-chan .vhg-ten{color:#0f766e}'
			. '.vhg-chan .vhg-qt{color:#94a3b8}'
			. '.vhg-chan .vhg-cd span{color:#94a3b8}'
			. '.vhg-chan .vhg-cn{color:#475569}'
			. '.vhg-chan a{color:#0f766e}'
			. '.vhg-ban-quyen{border-top-color:#eef2f7;color:#94a3b8}';
	}
}
