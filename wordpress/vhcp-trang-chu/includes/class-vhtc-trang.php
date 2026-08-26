<?php
/**
 * Trang cổng vào — danh sách app của K&H.
 *
 * KHÔNG có dữ liệu nào trên trang này, cũng không có cổng PIN: nó chỉ là mấy đường dẫn, mà
 * đường dẫn thì ai gõ cũng ra. Đặt thêm một cổng PIN ở đây là thêm một chỗ hỏng, thêm một mật
 * khẩu phải nhớ, mà không chặn thêm được gì — mỗi app đã tự có cổng của nó.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHTC_Trang {

	public static function slug() {
		$s = get_option( 'vhtc_slug' );
		$s = $s ? sanitize_title( $s ) : 'van-hanh';
		return $s ? $s : 'van-hanh';
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhtc', 'app', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhtc_app=1', 'top' );
		add_filter( 'query_vars', function ( $v ) { $v[] = 'vhtc_app'; return $v; } );
	}

	/**
	 * DANH SÁCH APP.
	 *
	 * 🔴 Đường dẫn LẤY TỪ CHÍNH APP ĐÓ, không gõ lại ở đây. Anh Thắng đổi đường dẫn bên app chi
	 *    phí là bên này tự theo. Gõ lại là sớm muộn hai nơi lệch, mà lệch thì bấm vào ra 404 chứ
	 *    không có gì báo.
	 * 🔴 App chưa cài -> `co: false`, hiện xám. KHÔNG dựng đường dẫn đoán, vì một liên kết chết
	 *    trông y hệt một liên kết sống cho tới lúc bấm vào.
	 */
	public static function ds_app() {
		/**
		 * ⚠️ DÒ TỪNG HÀM, KHÔNG DÒ MỖI TÊN LỚP.
		 *
		 * 4 plugin cài ĐỘC LẬP nên bản có thể lệch nhau. class_exists() chỉ nói "có plugin
		 * đó", KHÔNG nói "bản đó có hàm mình định gọi" — lớp CÓ mà hàm KHÔNG là lỗi nghiêm
		 * trọng, trắng cả trang WordPress (đã xảy ra thật 23/08/2026 ở chân trang app chi
		 * phí). Trang tổng này gọi sang cả 4 plugin nên là chỗ dễ dính nhất.
		 */
		$co = function ( $lop, $ham ) { return class_exists( $lop ) && method_exists( $lop, $ham ); };
		return array(
			/* 🔴 TRỎ VỀ HỆ MỚI, KHÔNG VỀ `/cham-cong/`.
			   Anh Thắng 26/08/2026 hỏi: *"trang này còn dùng không"* — `/cham-cong/` là app Apps
			   Script CŨ: plugin chỉ lấy Index.html từ project Apps Script rồi chèn cầu nối, còn
			   số liệu vẫn nằm ở Google Sheets. Hệ MỚI (`VHCC_Web`) chạy thẳng trên host với MySQL.
			   Hai hệ đọc HAI kho khác nhau, nên để trang chủ trỏ về hệ cũ là mời người ta vào
			   nhìn số của một kho đã ngừng được cập nhật. Ưu tiên hệ mới; hệ cũ chỉ còn là đường
			   lui khi plugin chưa nâng cấp. */
			array(
				'ten'   => 'Chấm Công',
				'mo_ta' => 'Bảng công theo ca, chấm công bù, nạp công, hồ sơ & phân quyền',
				'icon'  => '🕐',
				'co'    => $co( 'VHCC_Web', 'url' ) || $co( 'VHCC_Trang', 'url' ),
				'url'   => $co( 'VHCC_Web', 'url' ) ? VHCC_Web::url()
					: ( $co( 'VHCC_Trang', 'url' ) ? VHCC_Trang::url() : '' ),
			),
			/* Trạm chấm công là TRANG RIÊNG, không phải một tab của hệ quản trị: nó cần camera và
			   phải nhẹ để mở bằng 3G ở cơ sở. Nhân viên đứng quầy chỉ cần đúng ô này. */
			array(
				'ten'   => 'Chấm Công Online',
				'mo_ta' => 'Nhân viên bấm giờ vào / giờ ra bằng điện thoại, có ảnh và vị trí',
				'icon'  => '📷',
				'co'    => $co( 'VHCC_Tram', 'url' ),
				'url'   => $co( 'VHCC_Tram', 'url' ) ? VHCC_Tram::url() : '',
			),
			array(
				'ten'   => 'Vận Hành Chi Phí',
				'mo_ta' => 'Tạm ứng, chi phí cơ sở, dự án, quyết toán, xuất MISA',
				'icon'  => '💰',
				'co'    => $co( 'VHCP_App', 'app_url' ),
				'url'   => $co( 'VHCP_App', 'app_url' ) ? VHCP_App::app_url() : '',
			),
			array(
				'ten'   => 'Ghế Massage',
				'mo_ta' => 'Doanh thu QR theo cơ sở & máy, tình trạng ghế',
				'icon'  => '💺',
				'co'    => $co( 'VHG_Trang', 'url' ) || $co( 'VHG_Admin', 'app_url' ),
				/* Trỏ về TRANG NGOÀI `/ghe` (mở bằng PIN), không về wp-admin. Nhân viên đứng quầy
				   không có tài khoản WordPress, và cũng không nên có — cấp tài khoản cho 26 cửa
				   hàng là cấp luôn đường vào phần quản trị website.
				   Bản cũ (vhcp-ghe < 1.1.0) chưa có trang ngoài nên vẫn rơi về wp-admin: liên kết
				   dẫn tới màn đăng nhập còn hơn liên kết chết. */
				'url'   => $co( 'VHG_Trang', 'url' ) ? VHG_Trang::url()
					: ( $co( 'VHG_Admin', 'app_url' ) ? VHG_Admin::app_url() : '' ),
			),
			/* Trang nội bộ dùng CHUNG PIN với hệ chấm công (`VHNB_Trang` đọc cookie của
			   `VHCC_Web`), nên nó chỉ đứng được khi hệ chấm công có mặt. Thiếu hệ ấy thì ô này
			   phải xám: một liên kết dẫn tới trang chỉ nói "chưa cài plugin Chấm Công" thì thà
			   đừng dựng. */
			array(
				'ten'   => 'Nội Bộ',
				'mo_ta' => 'Bảng tin công ty: thông báo, trao đổi theo bộ phận, bình luận',
				'icon'  => '💬',
				'co'    => $co( 'VHNB_Trang', 'url' ) && $co( 'VHCC_Web', 'url' ),
				'url'   => ( $co( 'VHNB_Trang', 'url' ) && $co( 'VHCC_Web', 'url' ) )
					? VHNB_Trang::url() : '',
			),
			array(
				'ten'   => 'Thư Viện Hợp Đồng',
				'mo_ta' => 'Hợp đồng, đối tác, ngày hết hiệu lực',
				'icon'  => '📄',
				'co'    => $co( 'VHD_Trang', 'url' ),
				'url'   => $co( 'VHD_Trang', 'url' ) ? VHD_Trang::url() : '',
			),
		);
	}

	/** Có đang bật "dùng làm trang chủ" không. */
	public static function lam_trang_chu() { return (bool) get_option( 'vhtc_trang_chu' ); }

	/**
	 * Quyết định có vẽ trang cổng cho yêu cầu này không.
	 *
	 * ⚠️ CHỈ chiếm ĐÚNG trang chủ, không chiếm gì khác. `is_front_page()` là chốt duy nhất —
	 *    thiếu nó thì mọi trang của site đều biến thành trang cổng, kể cả trang của app khác,
	 *    và người dùng không còn đường nào đi tiếp.
	 *
	 * ⚠️ `template_redirect` KHÔNG chạy trong wp-admin và không chạy cho REST. Nên bật nhầm cũng
	 *    không bao giờ khoá được anh Thắng ra khỏi wp-admin — luôn còn đường vào để tắt lại.
	 *    Đây là lý do móc ở đây chứ không móc sớm hơn.
	 */
	/**
	 * QUYẾT ĐỊNH: yêu cầu này có phải trang cổng không.
	 *
	 * Tách riêng khỏi phần vẽ vì phần vẽ kết thúc bằng `exit` — mà `exit` thì không phép thử nào
	 * chạy qua được. Phần đáng thử ở đây là QUYẾT ĐỊNH (chiếm cái gì, không chiếm cái gì), nên
	 * nó phải gọi được mà không giết cả bài kiểm.
	 */
	public static function nen_ve() {
		if ( get_query_var( 'vhtc_app' ) || isset( $_GET['vhtc'] ) ) { return true; }
		return self::lam_trang_chu() && ! is_admin() && is_front_page();
	}

	public static function co_phai_trang_nay() {
		if ( ! self::nen_ve() ) { return; }
		status_header( 200 );
		self::ve();
		exit;
	}

	public static function ve() {
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		$ten_cty = get_option( 'vhtc_ten', 'Vận Hành K&H' );
		$ds      = self::ds_app();

		/* Đếm từ chính danh sách, không gõ tay: câu dưới tiêu đề nói có mấy hệ đang dùng được. */
		$so_co = 0;
		foreach ( $ds as $a ) { if ( $a['co'] && $a['url'] ) { $so_co++; } }

		echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
		/* `viewport` bắt buộc: phần lớn người mở trang này là nhân viên trên điện thoại. Thiếu nó
		   thì chữ bé bằng con kiến và phải chụm ngón tay phóng to mới bấm được. */
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html( $ten_cty ) . '</title>';
		echo '<style>' . self::css() . '</style></head><body>';

		echo '<div class="trang">';

		/* ---- đầu trang ---- */
		echo '<header class="dau"><div class="bo">';
		echo '<div class="hieu"><span class="dau-hieu">K&amp;H</span>'
			. '<span class="hieu-chu">' . esc_html( $ten_cty ) . '</span></div>';
		echo '<h1>Chọn hệ thống cần vào</h1>';
		echo '<p class="phu">Mỗi hệ thống là một phần mềm riêng, có <b>mã PIN riêng</b>. '
			. 'Hiện có <b>' . (int) $so_co . '</b> hệ đang dùng được.</p>';
		echo '</div></header>';

		/* ---- lưới ứng dụng ---- */
		echo '<main class="bo"><div class="luoi">';
		foreach ( $ds as $a ) {
			$mo = ( $a['co'] && $a['url'] );
			echo $mo
				? '<a class="the" href="' . esc_url( $a['url'] ) . '">'
				: '<div class="the tat">';
			echo '<span class="hang-tren">';
			echo '<span class="bt">' . esc_html( $a['icon'] ) . '</span>';
			if ( ! $a['co'] ) { echo '<span class="chua">chưa cài</span>'; }
			echo '</span>';
			echo '<span class="ten">' . esc_html( $a['ten'] ) . '</span>';
			echo '<span class="mt">' . esc_html( $a['mo_ta'] ) . '</span>';
			/* Chân thẻ chỉ hiện với hệ ĐANG DÙNG ĐƯỢC. Vẽ mũi tên "Mở" lên một ô xám là hứa một
			   thứ bấm vào không có gì — y hệt cái liên kết chết mà cả trang này sinh ra để tránh. */
			echo $mo ? '<span class="mo-ra">Mở<span class="mui">&rarr;</span></span>'
				: '<span class="mo-ra tat-chu">Chưa cài trên website này</span>';
			echo $mo ? '</a>' : '</div>';
		}
		echo '</div></main>';

		echo '<footer class="chan"><div class="bo">'
			. 'Quên PIN thì hỏi quản lý cơ sở hoặc Admin — mỗi hệ một PIN, không dùng chung.'
			. '</div></footer>';

		self::chan_cty();

		echo '</div></body></html>';
	}

	/**
	 * THÔNG TIN CÔNG TY Ở CUỐI TRANG.
	 *
	 * Anh Thắng 26/08/2026: *"nhớ bổ sung thông tin công ty cuối trang"* — kèm ảnh chân trang
	 * của trang Ghế. Trang này là trang chủ công ty, nên nó là chỗ ĐÁNG có nhất.
	 *
	 * 🔴 LẤY TỪ `VHG_Chan` NẾU CÓ, KHÔNG GÕ LẠI SỐ LIỆU.
	 *    Plugin Ghế đã có sẵn màn quản trị để sửa địa chỉ / người đại diện / chi nhánh. Gõ lại
	 *    ở đây là hai nơi khai cùng một sự thật: anh Thắng sửa địa chỉ bên kia, trang chủ vẫn
	 *    nói địa chỉ cũ — và nói sai đúng chỗ đặt ra để tạo tin cậy, không có gì báo.
	 *
	 * ⚠️ NHƯNG KHÔNG ĐƯỢC PHỤ THUỘC vào plugin Ghế. Đây là trang chủ công ty; gỡ plugin Ghế mà
	 *    mất luôn thông tin pháp lý của công ty là buộc hai thứ chẳng liên quan gì vào nhau.
	 *    Nên có bản dự phòng ngay đây — và `test-trang-chu.php` canh hai bảng phải KHỚP NHAU,
	 *    để chỗ chép này lệch đi thì phép thử đỏ chứ không im lặng.
	 *
	 * ⚠️ KHÔNG đọc cờ `hien` của plugin Ghế. Cờ ấy nói "có hiện chân trang trên trang Ghế
	 *    không" — tắt nó đi để trang bán mã gọn hơn mà kéo theo trang chủ công ty mất luôn phần
	 *    giới thiệu mình là ai thì không ai đoán ra vì sao.
	 */
	const CTY_DU_PHONG = array(
		'ten'        => 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H',
		'ten_qt'     => 'K&H SERVICES AND ENTERTAINMENT COMPANY LIMITED',
		'mst'        => '0106924989',
		'dia_chi'    => 'Thôn Mai Nội, Xã Sóc Sơn, Thành phố Hà Nội, Việt Nam',
		'dai_dien'   => 'Nguyễn Văn Kiên',
		'dien_thoai' => '0435961469',
		'email'      => '',
		'ngay_hd'    => '05/08/2015',
		'co_quan'    => 'Thuế cơ sở 18 thành phố Hà Nội',
		'chi_nhanh'  => "Đà Nẵng\nHải Phòng\nBình Dương\nThành phố Hồ Chí Minh\nSense City Hồ Chí Minh\nNha Trang",
	);

	public static function thong_tin_cty() {
		/* ⚠️ DÒ TỪNG HÀM, KHÔNG DÒ MỖI TÊN LỚP: lớp CÓ mà hàm KHÔNG là trắng cả trang. */
		if ( class_exists( 'VHG_Chan' ) && method_exists( 'VHG_Chan', 'thong_tin' ) ) {
			$t = VHG_Chan::thong_tin();
			if ( is_array( $t ) && '' !== trim( (string) ( isset( $t['ten'] ) ? $t['ten'] : '' ) ) ) {
				$ra = array();
				/* Chỉ lấy đúng những ô trang này dùng. Bản kia thêm ô mới thì trang này không
				   tự nhiên in ra một thứ chưa ai xem qua. */
				foreach ( self::CTY_DU_PHONG as $k => $v ) {
					$ra[ $k ] = isset( $t[ $k ] ) ? (string) $t[ $k ] : (string) $v;
				}
				return $ra;
			}
		}
		return self::CTY_DU_PHONG;
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

	private static function chan_cty() {
		$t = self::thong_tin_cty();
		if ( '' === trim( (string) $t['ten'] ) ) { return; }

		/* Ô nào TRỐNG thì bỏ hẳn dòng đó, không in nhãn treo lơ lửng: "Email:" mà sau nó không
		   có gì trông như trang bị hỏng, chứ không phải như công ty chưa khai email. */
		$dong = function ( $nhan, $gt, $them = '' ) {
			if ( '' === trim( (string) $gt ) ) { return ''; }
			return '<div class="cd"><span>' . esc_html( $nhan ) . '</span> '
				. ( '' !== $them ? $them : esc_html( $gt ) ) . '</div>';
		};

		echo '<section class="cty"><div class="bo"><div class="cty-in">';

		echo '<div class="cot">'
			. '<div class="cty-ten">' . esc_html( $t['ten'] ) . '</div>'
			. ( '' !== trim( (string) $t['ten_qt'] )
				? '<div class="cd qt">' . esc_html( $t['ten_qt'] ) . '</div>' : '' )
			. $dong( 'Mã số thuế / Tax code:', $t['mst'] )
			. $dong( 'Người đại diện / Legal rep.:', $t['dai_dien'] )
			. $dong( 'Hoạt động từ / Since:', $t['ngay_hd'] )
			. '</div>';

		echo '<div class="cot">'
			. $dong( 'Địa chỉ / Address:', $t['dia_chi'] )
			/* Số điện thoại bấm gọi được ngay — phần lớn người mở trang này đang cầm điện thoại,
			   đừng bắt họ bôi đen rồi chép sang ứng dụng gọi. */
			. $dong( 'Điện thoại / Phone:', $t['dien_thoai'],
				'<a href="tel:' . esc_attr( preg_replace( '/\D+/', '', (string) $t['dien_thoai'] ) ) . '">'
					. esc_html( $t['dien_thoai'] ) . '</a>' )
			. $dong( 'Email:', $t['email'],
				'<a href="mailto:' . esc_attr( $t['email'] ) . '">' . esc_html( $t['email'] ) . '</a>' )
			. $dong( 'Cơ quan quản lý thuế:', $t['co_quan'] )
			. '</div>';

		$cn = self::ds_chi_nhanh( $t['chi_nhanh'] );
		if ( $cn ) {
			echo '<div class="cot"><div class="cd"><span>Chi nhánh / Branches:</span></div>'
				. '<div class="cn">' . esc_html( implode( ' · ', $cn ) ) . '</div></div>';
		}

		echo '</div><div class="ban-quyen">© '
			. esc_html( gmdate( 'Y', current_time( 'timestamp' ) ) ) . ' '
			. esc_html( $t['ten'] ) . '. Toàn bộ bản quyền thuộc công ty. / All rights reserved.'
			. '</div></div></section>';
	}

	/**
	 * PHẦN NHÌN.
	 *
	 * 🔴 HAI KHỔ MÀN, HAI CÁCH XẾP — và bản ĐIỆN THOẠI là bản gốc, máy tính là bản nới ra.
	 *    Anh Thắng 26/08/2026 mở trên máy tính: *"chỉnh giao diện cho máy tính, nhìn như trang
	 *    chủ"* — lưới cũ khoá cứng `max-width:430px` nên trên màn 1900px nó là một dải hẹp giữa
	 *    nền đen mênh mông, trông như trang đang tải dở. Nay lưới tự giãn theo bề ngang: một cột
	 *    trên điện thoại, hai cột trên máy tính bảng, ba cột trên máy tính.
	 *
	 * ⚠️ Người mở trang này ĐÔNG NHẤT vẫn là nhân viên đứng quầy cầm điện thoại, nên mọi con số
	 *    tối thiểu (ô bấm cao 62px, chữ 16px) giữ nguyên. Làm đẹp cho màn rộng mà bóp bản điện
	 *    thoại lại là đổi cái nhiều người dùng lấy cái ít người dùng.
	 */
	private static function css() {
		return ''
			. ':root{--nen:#0f172a;--the:#1e293b;--the-hover:#243549;--vien:#334155;'
			. '--chu:#e2e8f0;--mo:#94a3b8;--mo-hon:#64748b;--xanh:#3b82f6;--vang:#f59e0b}'
			. '*{box-sizing:border-box}'
			. 'body{margin:0;background:var(--nen);color:var(--chu);'
			. 'font:16px/1.55 "Segoe UI",system-ui,Arial,sans-serif}'
			/* Vệt sáng mờ sau đầu trang. `fixed` để cuộn xuống nó không trôi theo thành một vệt lạ. */
			. '.trang{min-height:100vh;display:flex;flex-direction:column;'
			. 'background:radial-gradient(900px 420px at 50% -140px,#1e3a5f66,transparent 70%);'
			. 'background-attachment:fixed}'
			. '.bo{width:100%;max-width:1080px;margin:0 auto;padding:0 20px}'

			. '.dau{padding:44px 0 26px;text-align:center}'
			. '.hieu{display:inline-flex;align-items:center;gap:9px;margin:0 0 18px}'
			. '.dau-hieu{font-weight:800;font-size:15px;letter-spacing:.5px;color:#fff;'
			. 'background:var(--xanh);border-radius:8px;padding:4px 9px}'
			. '.hieu-chu{font-weight:600;font-size:15px;color:var(--mo)}'
			. 'h1{font-size:29px;line-height:1.25;margin:0 0 8px;font-weight:700}'
			. '.phu{color:var(--mo);font-size:14.5px;margin:0 auto;max-width:560px}'
			. '.phu b{color:var(--chu)}'

			/* 🔴 Lưới tự giãn: `auto-fit` + `minmax` nghĩa là bề ngang quyết định số cột, không
			   phải một mốc `@media` gõ tay. Thêm ô thứ bảy, thứ tám cũng không phải sửa gì. */
			. 'main{flex:1;padding:8px 0 40px}'
			. '.luoi{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(290px,1fr))}'

			. '.the{display:flex;flex-direction:column;background:var(--the);border:1px solid var(--vien);'
			. 'border-radius:14px;padding:20px;text-decoration:none;color:inherit;min-height:62px}'
			. 'a.the{transition:border-color .12s,background .12s,transform .12s}'
			. 'a.the:hover{background:var(--the-hover);border-color:var(--xanh);transform:translateY(-2px)}'
			/* Viền khi đi bằng phím Tab — bàn phím phải thấy mình đang đứng ở ô nào. */
			. 'a.the:focus-visible{outline:2px solid var(--xanh);outline-offset:2px}'
			. '.the.tat{opacity:.45}'
			. '.hang-tren{display:flex;align-items:flex-start;justify-content:space-between;'
			. 'gap:10px;margin:0 0 13px}'
			. '.bt{font-size:26px;line-height:1;width:48px;height:48px;border-radius:12px;'
			. 'background:#0f172a99;border:1px solid var(--vien);display:flex;align-items:center;'
			. 'justify-content:center;flex:0 0 auto}'
			. '.chua{font-size:11.5px;color:var(--vang);white-space:nowrap;'
			. 'border:1px solid #f59e0b55;border-radius:999px;padding:2px 8px}'
			. '.ten{font-weight:600;font-size:17px;display:block}'
			. '.mt{color:var(--mo);font-size:13.2px;margin-top:5px;display:block;flex:1}'
			. '.mo-ra{display:flex;align-items:center;gap:6px;margin-top:14px;padding-top:12px;'
			. 'border-top:1px solid var(--vien);font-size:13px;font-weight:600;color:var(--xanh)}'
			. '.mo-ra.tat-chu{color:var(--mo-hon);font-weight:400}'
			. '.mui{transition:transform .12s}'
			. 'a.the:hover .mui{transform:translateX(4px)}'

			. '.chan{padding:0 0 26px;color:var(--mo-hon);font-size:12.5px;text-align:center}'

			/* ---- thông tin công ty ---- */
			. '.cty{border-top:1px solid var(--vien);background:#0b1220;padding:26px 0 30px;'
			. 'font-size:12.8px;line-height:1.7;color:var(--mo)}'
			/* Ba cột tự giãn theo bề ngang. `flex:1 1 260px` nghĩa là cột nào cũng phải rộng ít
			   nhất 260px mới đứng cạnh nhau — hẹp hơn thì tự xuống dòng, khỏi cần mốc @media. */
			. '.cty-in{display:flex;flex-wrap:wrap;gap:18px 34px}'
			. '.cty .cot{flex:1 1 260px;min-width:0}'
			. '.cty-ten{color:var(--chu);font-weight:700;margin-bottom:5px;font-size:13.2px}'
			. '.cty .qt{font-style:italic;color:var(--mo-hon);margin-bottom:5px}'
			. '.cty .cd span{color:var(--mo-hon)}'
			. '.cty a{color:var(--xanh);text-decoration:none}'
			. '.cty a:hover{text-decoration:underline}'
			. '.ban-quyen{margin-top:16px;padding-top:12px;border-top:1px solid #1e293b;'
			. 'color:var(--mo-hon);font-size:12px}'

			/* ---- điện thoại: về đúng bản cũ — biểu tượng bên trái, chữ bên phải, gọn một dòng ---- */
			. '@media(max-width:640px){'
			. '.dau{padding:30px 0 20px}h1{font-size:22px}.phu{font-size:13.5px}'
			. '.luoi{grid-template-columns:1fr;gap:13px}'
			. '.the{display:grid;grid-template-columns:auto 1fr;column-gap:14px;padding:15px 16px;'
			. 'align-items:center}'
			. '.hang-tren{display:contents}'
			. '.bt{width:44px;height:44px;font-size:24px;grid-row:span 2}'
			. '.ten{font-size:16.5px;align-self:end}'
			. '.mt{font-size:12.8px;margin-top:2px;align-self:start}'
			/* Nhãn "chưa cài" và dòng "Mở →" bỏ hẳn trên điện thoại: chữ đã kín màn, thêm hai
			   dòng nữa là mỗi ô cao gấp rưỡi mà không nói thêm được gì. Ô xám vẫn nhận ra được
			   nhờ độ mờ, và nó không phải thẻ <a> nên bấm vào cũng không đi đâu. */
			. '.chua,.mo-ra{display:none}'
			. '.cty{font-size:12px;padding:20px 0 24px}.cty-in{gap:14px}'
			. '}';
	}
}
