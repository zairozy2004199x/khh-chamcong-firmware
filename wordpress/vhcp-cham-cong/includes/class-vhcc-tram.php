<?php
/**
 * TRẠM CHẤM CÔNG — trang nhân viên tự chấm bằng điện thoại, chạy THẲNG trên WordPress.
 *
 * Đây là bản dựng lại của `ChamCong.html` bên Apps Script. Toàn bộ nghiệp vụ đã nằm sẵn trong
 * VHCC_Online từ trước mà KHÔNG có màn nào gọi tới — file này chỉ là cái cửa: nhận PIN, phát thẻ
 * phiên, rồi chuyển lệnh xuống VHCC_Online.
 *
 * =============================================================================================
 * 🔴 BỐN RÀNG BUỘC CỦA BẢN GỐC, GIỮ NGUYÊN — BỎ CHỖ NÀO CŨNG HỎNG THEO KIỂU IM LẶNG
 * =============================================================================================
 * 1. ẢNH ĐÓNG DẤU BẰNG GIỜ MÁY CHỦ. Trang lấy mốc giờ từ `viec=gio` rồi tự trôi theo đồng hồ
 *    máy, KHÔNG bao giờ in giờ của điện thoại lên ảnh. Điện thoại lệch giờ là chuyện thường; in
 *    giờ điện thoại lên ảnh thì tấm ảnh — thứ duy nhất dùng để đối chiếu khi tranh cãi — lại nói
 *    khác hàng đã ghi.
 * 2. THU NHỎ ẢNH VỀ 720px TRƯỚC KHI GỬI. Ảnh gốc điện thoại nay 3–8 MB; gửi thẳng là vừa quá
 *    `post_max_size` của hosting vừa treo mạng 3G ở cơ sở. Thu nhỏ ở TRÌNH DUYỆT, không phải ở
 *    máy chủ — máy chủ nhận được thì đã tốn băng thông rồi.
 * 3. HỎI CƠ SỞ / NHIỆM VỤ ĐÚNG LÚC LƯU. Hỏi lúc mở trang thì người ta chọn từ sáng, tới chiều
 *    sang cơ sở khác vẫn còn nguyên lựa chọn cũ — và giờ vào ghi nhầm cơ sở.
 * 4. KHOÁ NÚT SAU KHI BẤM. Mạng chậm, người ta bấm ba lần; ba lượt ghi liên tiếp là giờ ra đè
 *    lên giờ vào.
 *
 * =============================================================================================
 * KHÔNG DỰNG ĐƯỜNG GHI RIÊNG
 * =============================================================================================
 * Trang này gọi đúng `VHCC_Online::cham_cong()` — cùng một hàm mà mọi đường khác gọi. Dựng đường
 * ghi thứ hai là hai bộ luật (định tuyến hàng 2, ân hạn tan làm, gác cơ sở/nhiệm vụ), và sớm
 * muộn hai bộ lệch nhau ở đúng chỗ không ai kịp phát hiện.
 *
 * =============================================================================================
 * CỬA ĐĂNG NHẬP RIÊNG, KHÔNG DÙNG VHCC_Auth::login()
 * =============================================================================================
 * `VHCC_Auth::login()` gác theo VAI TRÒ (mặc định chỉ Admin/Quản lý/Kế toán) — đó là cửa của HỆ
 * QUẢN TRỊ và không được nới. Trạm gác theo thứ khác hẳn: **có khai "Mã NV chấm công online"**.
 * Đúng gác số 4 của VHCC_Online, và đúng cách bản gốc phân biệt nhân viên chấm được với nhân
 * viên chỉ theo dõi — bản gốc CỐ Ý không thêm vai trò mới cho việc này.
 *
 * Nới `vai_tro_vao()` để nhân viên vào trạm được thì cùng lúc mở cho họ toàn bộ bảng lương. Đó
 * là lý do có hai cửa.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Tram {

	/** Vai trò gắn lên phiên của trạm. CỐ Ý không nằm trong VHCC_Auth::VAI_TRO_TAT_CA. */
	const VAI_TRAM = 'CC_ONLINE';

	const SLUG_MD = 'cham-cong-online';

	/** Số lượt gõ PIN sai cho mỗi IP trong 10 phút. */
	const SAI_TOI_DA = 12;

	public static function slug() {
		$s = get_option( 'vhcc_slug_tram' );
		$s = $s ? sanitize_title( $s ) : self::SLUG_MD;
		return $s ? $s : self::SLUG_MD;
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhcc_tram', '1', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhcc_tram=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function query_vars( $v ) { $v[] = 'vhcc_tram'; return $v; }

	public static function maybe_render() {
		$is = ( (int) get_query_var( 'vhcc_tram' ) === 1 );
		if ( ! $is && isset( $_GET['vhcc_tram'] ) && '1' === $_GET['vhcc_tram'] ) { $is = true; }
		if ( ! $is ) { return; }

		/* Lệnh đi CHUNG đường với trang, không qua /wp-json/. Hosting của mình (Imunify360) đã
		   từng chặn /wp-json/ theo đường dẫn, và lúc đó cả trạm chết mà không báo gì. */
		if ( isset( $_GET['viec'] ) ) {
			self::cong( sanitize_text_field( wp_unslash( $_GET['viec'] ) ) );
			exit;
		}
		self::render();
		exit;
	}

	// ==================================================================== cổng lệnh

	private static function ra( $data, $ma = 200 ) {
		status_header( (int) $ma );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $data );
		exit;
	}

	/** Thân JSON của lượt POST. Trạm gửi JSON, không gửi biểu mẫu. */
	private static function than() {
		$raw = file_get_contents( 'php://input' );
		$j   = ( '' !== $raw && false !== $raw ) ? json_decode( (string) $raw, true ) : null;
		return is_array( $j ) ? $j : array();
	}

	public static function cong( $viec ) {
		nocache_headers();
		$b = self::than();

		/* --- việc công khai: chưa đăng nhập cũng gọi được --- */
		if ( 'gio' === $viec ) { self::ra( VHCC_Online::gio_may_chu() ); }

		if ( 'anhmau' === $viec ) { self::ra( VHCC_Online::anh_mau_the() ); }

		if ( 'vao' === $viec ) {
			self::ra( self::dang_nhap( isset( $b['pin'] ) ? $b['pin'] : '' ) );
		}

		if ( 'quenpin' === $viec ) {
			/* Bộ đếm chống dò nằm TRONG VHCC_Quyen::tra_pin_theo_cccd — không nhân bản ở đây.
			   Hai bộ đếm cho cùng một cửa là hai con số khác nhau và không con nào đúng. */
			self::ra( VHCC_Quyen::tra_pin_theo_cccd( isset( $b['cccd'] ) ? $b['cccd'] : '' ) );
		}

		/* --- từ đây phải có thẻ phiên của TRẠM --- */
		$u = self::nguoi( isset( $b['token'] ) ? $b['token'] : '' );
		if ( ! $u ) {
			self::ra( array( 'ok' => false, 'ma' => 'het_phien',
				'error' => 'Phiên đã hết — đăng nhập lại bằng PIN.' ), 200 );
		}

		if ( 'toi' === $viec ) { self::ra( VHCC_Online::thong_tin( $u ) ); }

		if ( 'cham' === $viec ) {
			$gps = ( isset( $b['gps'] ) && is_array( $b['gps'] ) ) ? $b['gps'] : null;
			self::ra( VHCC_Online::cham_cong(
				$u,
				isset( $b['anh'] ) ? (string) $b['anh'] : '',
				$gps,
				isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				isset( $b['nhiemVu'] ) ? (string) $b['nhiemVu'] : ''
			) );
		}

		if ( 'lichsu' === $viec ) {
			$ds = VHCC_Online::ds_coso_cua_nv( $u['ma_nv'], VHCC_NhanSu::chuan_coso( $u['coso'] ) );
			self::ra( array( 'ok' => true, 'dong' => VHCC_Online::lich_su( $u['ma_nv'], $ds, 60 ) ) );
		}

		if ( 'ra' === $viec ) {
			VHCC_Auth::logout( isset( $b['token'] ) ? $b['token'] : '' );
			self::ra( array( 'ok' => true ) );
		}

		self::ra( array( 'ok' => false, 'error' => 'Việc không rõ: ' . $viec ), 400 );
	}

	// ==================================================================== đăng nhập

	private static function khoa_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhcc_tram_sai_' . md5( $ip );
	}

	/**
	 * PIN -> thẻ phiên của trạm.
	 *
	 * Đọc THẲNG bảng `phan_quyen`, không đi qua VHCC_Auth::users(): nguồn người dùng của hệ quản
	 * trị đổi được (riêng / chung / app / hồ sơ), nhưng "ai chấm công online được" thì chỉ có
	 * MỘT nguồn duy nhất là cột `ma_cc_online`. Buộc trạm theo nguồn kia là đổi Cài đặt một cái
	 * thì cả cơ sở không chấm công được, mà màn Cài đặt không hề nhắc gì tới trạm.
	 */
	public static function dang_nhap( $pin ) {
		global $wpdb;
		$pin = VHCC_Auth::pin_sach( $pin );
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số.' );
		}
		$k = self::khoa_key();
		if ( (int) get_transient( $k ) >= self::SAI_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Gõ sai quá nhiều lần — thử lại sau 10 phút.' );
		}

		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT pin, ho_ten, ma_cc_online, coso_cc_online FROM ' . VHCC_DB::t( 'phan_quyen' )
			. " WHERE pin=%s AND ma_cc_online <> '' LIMIT 1", $pin ), ARRAY_A );

		if ( ! $r ) {
			/* PIN CÓ trong sổ nhưng CHƯA khai mã NV là một tình huống khác hẳn PIN sai, và phải
			   nói khác đi. Bảo "PIN không đúng" thì người ta gõ lại mười lần rồi tự khoá mình,
			   trong khi thứ thiếu nằm ở hồ sơ chứ không nằm ở ngón tay họ. */
			$co = $wpdb->get_row( $wpdb->prepare(
				'SELECT ho_ten FROM ' . VHCC_DB::t( 'phan_quyen' ) . ' WHERE pin=%s LIMIT 1', $pin ), ARRAY_A );
			if ( $co ) {
				return array( 'ok' => false, 'error' => 'Tài khoản ' . $co['ho_ten']
					. ' chưa được bật chấm công online (chưa khai "Mã NV chấm công online"). '
					. 'Nhờ quản lý cửa hàng khai giúp — không phải gõ lại PIN.' );
			}
			set_transient( $k, (int) get_transient( $k ) + 1, 600 );
			return array( 'ok' => false, 'error' => 'PIN không đúng hoặc chưa được cấp.' );
		}

		delete_transient( $k );
		$ma_nv = trim( (string) $r['ma_cc_online'] );
		$coso  = VHCC_NhanSu::chuan_coso( $r['coso_cc_online'] );
		return array(
			'ok'    => true,
			'hoTen' => (string) $r['ho_ten'],
			'maNV'  => $ma_nv,
			'coSo'  => $coso,
			'token' => VHCC_Auth::phat_token( (string) $r['ho_ten'], self::VAI_TRAM, $coso, $ma_nv ),
		);
	}

	/**
	 * Thẻ phiên -> người, ở dạng VHCC_Online cần: array('ma_nv','ho_ten','coso').
	 *
	 * ⚠️ Chỉ nhận phiên có `vai_tro = CC_ONLINE`. Thẻ của hệ quản trị KHÔNG vào cửa này được, và
	 *    ngược lại — hai chiều đều chặn. Cùng một bảng `session` nhưng hai loại thẻ không đổi
	 *    vai cho nhau được, đó mới là tách quyền thật.
	 */
	public static function nguoi( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT ten, coso, ma_nv, vai_tro FROM ' . VHCC_DB::t( 'session' )
			. ' WHERE token=%s AND het_han > UTC_TIMESTAMP()', $token ), ARRAY_A );
		if ( ! $r || self::VAI_TRAM !== (string) $r['vai_tro'] ) { return null; }
		if ( '' === trim( (string) $r['ma_nv'] ) ) { return null; }
		return array( 'ma_nv' => (string) $r['ma_nv'], 'ho_ten' => (string) $r['ten'],
			'coso' => (string) $r['coso'], 'name' => (string) $r['ten'] );
	}

	// ==================================================================== giao diện

	public static function render() {
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		$cfg = array(
			'cong' => esc_url_raw( self::url() ),
			'ver'  => VHCC_VERSION,
		);
		$VHCC_TRAM_CFG = $cfg;   // phpcs:ignore -- biến dùng trong template
		include VHCC_DIR . 'templates/tram.php';
	}

	public static function shortcode( $atts ) {
		$a  = shortcode_atts( array( 'height' => '900' ), $atts, 'vhcc_tram' );
		$hh = preg_replace( '/[^0-9a-z%]/i', '', (string) $a['height'] );
		if ( '' === $hh ) { $hh = '900'; }
		if ( is_numeric( $hh ) ) { $hh .= 'px'; }
		/* allow="camera" BẮT BUỘC: iframe không có nó thì getUserMedia bị chối thẳng, và trình
		   duyệt không hỏi gì cả — người dùng chỉ thấy nút chụp bấm không lên. */
		return '<iframe src="' . esc_url( self::url() ) . '" allow="camera;geolocation" '
			. 'style="width:100%;height:' . esc_attr( $hh ) . ';border:0;display:block" '
			. 'title="Chấm công"></iframe>';
	}
}
