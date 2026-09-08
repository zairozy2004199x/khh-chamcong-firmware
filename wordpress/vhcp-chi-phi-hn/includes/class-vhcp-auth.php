<?php
/**
 * ĐĂNG NHẬP — PIN 4 số như app cũ, thêm 2 thứ Apps Script không có:
 *   1) phiên có token: mọi lệnh gọi API (trừ `login`) phải kèm token còn hạn;
 *   2) hãm thử PIN: quá 10 lần sai trong 10 phút từ 1 IP thì chặn tạm.
 *
 * PIN vẫn lưu nguyên văn trong bảng cấu hình vì tab "⚙️ Cấu hình" của giao diện
 * hiện & sửa PIN từng người (giữ đúng cách vận hành cũ). Xem phần "Bảo mật" trong
 * docs/HUONG-DAN-CAI-DAT-WORDPRESS.md nếu muốn siết thêm.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPHN_Auth {

	const TTL = 2592000;   // 30 ngày — giao diện nhớ phiên trong localStorage (token, KHÔNG phải PIN)
	/* Còn dưới 7 ngày thì gia hạn về đủ 30 — xem `ai_dang_dang_nhap()`. */
	const TTL_GIA_HAN = 604800;

	/**
	 * Vai trò của người đang gọi trong lượt request này.
	 *
	 * Bảng hàm gọi thẳng vào VHCPHN_Cfg::save_config() nên bên đó không biết ai đang lưu.
	 * Cần biết để chặn người không phải Admin sửa tài khoản Admin — chặn ở MÁY CHỦ, chứ
	 * khoá ô nhập trên giao diện chỉ là lớp sơn.
	 */
	private static $vai_tro = '';   // VAI GỐC — mọi phép kiểm quyền dùng cái này
	private static $vai_hien = '';  // tên vai NHƯ NGƯỜI TA KHAI (có thể là vai tự tạo)
	private static $nguoi   = '';
	/**
	 * CƠ SỞ NGƯỜI ĐANG GỌI PHỤ TRÁCH — chuỗi thô như đã khai, ví dụ "FARM PHAN THIẾT, ADV GO".
	 *
	 * 🔴 MỘT NGƯỜI PHỤ TRÁCH NHIỀU CƠ SỞ. Ô khai ở màn Cấu hình là hộp tích nhiều lựa chọn, lưu
	 *    xuống thành một chuỗi ngăn bằng dấu phẩy — nên chỗ nào đọc nó cũng phải TÁCH RA, đừng
	 *    so bằng `===`. So nguyên chuỗi là người khai ba cơ sở thì không khớp cơ sở nào cả.
	 */
	private static $coso    = '';
	/**
	 * 🔴 QUY VỀ VAI GỐC NGAY TẠI ĐÂY, một chỗ duy nhất.
	 *
	 * Vai tự tạo ("Nhân viên văn phòng") kế thừa quyền của một vai gốc ("Nhân viên"). Nếu để
	 * mỗi nơi tự quy đổi thì chỉ cần MỘT nơi quên là thủng: `la_nhan_vien()` so với chuỗi
	 * 'Nhân viên' sẽ trả false cho vai tự tạo, và người đó thấy đơn của cả công ty.
	 *
	 * Quy ở cửa vào nên mọi chỗ phía sau không cần biết vai tự tạo là gì.
	 */
	public static function dat_vai_tro( $r, $ten = '', $coso = '' ) {
		self::$vai_hien = (string) $r;
		self::$vai_tro  = class_exists( 'VHCPHN_Cfg' ) ? VHCPHN_Cfg::vai_goc( (string) $r ) : (string) $r;
		self::$nguoi    = (string) $ten;
		self::$coso     = (string) $coso;
	}
	/**
	 * BỘ PHẬN mà người đang gọi bị bó vào — '' = không bó (thấy mọi bộ phận).
	 *
	 * Anh Thắng 08/09/2026: *"thêm vai trò kế toán máy tự động (để chỉ thực hiện công việc bên
	 * bộ phận máy tự động)"*. Bó gắn với VAI, không với ô "Bộ phận" trên tài khoản — xem chốt
	 * dài ở `VHCPHN_Cfg::bo_phan_cua_nguoi()`.
	 */
	public static function bo_phan_bo() {
		if ( ! class_exists( 'VHCPHN_Cfg' ) ) { return ''; }
		return VHCPHN_Cfg::bo_phan_cua_nguoi( self::$vai_hien );
	}

	/* ⚠️ ĐÃ BỎ `xem_duoc_bo_phan( $bp )` — so thẳng tên bộ phận với bộ phận đang bó. Viết ra
	   "cho chắc ăn" rồi KHÔNG chỗ nào gọi: phá thử chỉ nó ra ngay, vì đục cho nó luôn trả
	   `true` mà không phép nào đỏ. Nhánh không ai đi tới thì không ai biết nó còn đúng, và
	   nó cũng không bảo vệ được gì. Mọi chỗ cần hỏi đều đi qua `xem_duoc_loai()` dưới đây —
	   dòng tiền mang TÊN LOẠI chứ không mang tên bộ phận. */

	/**
	 * Người đang gọi có được đọc một dòng chi mang LOẠI CHI PHÍ này không.
	 *
	 * 🔴 LOẠI CHƯA KHAI BỘ PHẬN THÌ CHO QUA. Danh mục loại chi phí của anh Thắng dựng từ sổ cũ,
	 *    rất nhiều dòng còn bỏ trống ô Bộ phận. Chặn chúng lại là ngày bản này lên, kế toán bó
	 *    bộ phận mở màn ra thấy gần như trắng — và họ sẽ kết luận là mất dữ liệu chứ không
	 *    đoán ra là do một ô chưa khai ở màn Cấu hình.
	 */
	public static function xem_duoc_loai( $ten_loai ) {
		$bo = self::bo_phan_bo();
		if ( '' === $bo ) { return true; }
		$bp = class_exists( 'VHCPHN_Cfg' ) ? VHCPHN_Cfg::bo_phan_cua_loai( $ten_loai ) : '';
		if ( '' === $bp ) { return true; }
		return mb_strtolower( $bo ) === mb_strtolower( $bp );
	}

	public static function vai_tro() { return self::$vai_tro; }
	public static function vai_hien() { return self::$vai_hien; }
	public static function nguoi() { return self::$nguoi; }

	/**
	 * CÁC CƠ SỞ NGƯỜI ĐANG GỌI PHỤ TRÁCH, đã tách sẵn thành mảng.
	 *
	 * Anh Thắng 30/08/2026: *"Nhân viên được cấu hình 3 cơ sở, nhưng đơn chỉ hiện 1 cơ sở"*.
	 *
	 * 🔴 HÀM THUẦN, MỘT CHỖ TÁCH DUY NHẤT. Trước đây chuỗi này chỉ được nhét vào thẻ phiên rồi
	 *    thôi — không chỗ nào ở máy chủ đọc tới, nên khai ba cơ sở hay ba mươi cũng như nhau.
	 *    Nay có chỗ đọc thì phải tách ở ĐÚNG MỘT NƠI: mỗi nơi tự `explode` lấy là sớm muộn một
	 *    nơi quên `trim`, và " ADV GO" (thừa một dấu cách) không khớp "ADV GO".
	 *
	 * @return array danh sách tên cơ sở; rỗng nghĩa là KHÔNG khai cơ sở nào.
	 */
	public static function coso_ds() {
		$ra = array();
		foreach ( explode( ',', (string) self::$coso ) as $x ) {
			$x = trim( $x );
			if ( '' !== $x ) { $ra[] = $x; }
		}
		return $ra;
	}

	/**
	 * Tên cơ sở này có nằm trong phạm vi người đang gọi phụ trách không.
	 *
	 * ⚠️ So KHÔNG PHÂN BIỆT HOA THƯỜNG và bỏ khoảng trắng thừa. Tên cơ sở do người gõ tay ở
	 *    nhiều màn khác nhau, "Funzone Vũng Tàu" và "FUNZONE VŨNG TÀU" là một chỗ.
	 */
	public static function trong_coso( $ten ) {
		$ten = mb_strtolower( trim( (string) $ten ) );
		if ( '' === $ten ) { return false; }
		foreach ( self::coso_ds() as $c ) {
			if ( mb_strtolower( $c ) === $ten ) { return true; }
		}
		return false;
	}

	/** Người đang gọi là NHÂN VIÊN (chỉ được thấy / sửa đơn của chính mình)? */
	public static function la_nhan_vien() {
		return ( self::$vai_tro === 'Nhân viên' && trim( self::$nguoi ) !== '' );
	}

	/** login(pin) */
	public static function login( $pin ) {
		$pin = trim( (string) $pin );
		// 4–8 chữ số: PIN dài hơn 4 số vẫn phải đăng nhập được, không thì cấp PIN 6 số
		// là khoá luôn tài khoản đó (chặn ngay ở đây, chưa kịp so PIN).
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) { return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số' ); }
		if ( self::is_locked() ) { return array( 'ok' => false, 'error' => 'Nhập sai quá nhiều lần — thử lại sau 10 phút' ); }

		foreach ( VHCPHN_Cfg::get_users() as $u ) {   // get_users() tự seed nếu cấu hình còn trống
			if ( trim( (string) $u['pin'] ) === $pin ) {
				self::clear_fails();
				$tok = self::issue_token( $u['ten'], ( $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên' ), $u['coso'], $u['boPhan'] );
				return array(
					'ok'     => true,
					'name'   => $u['ten'],
					'role'   => ( $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên' ),
					'roleGoc' => VHCPHN_Cfg::vai_goc( $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên' ),
					'coso'   => $u['coso'],
					'boPhan' => $u['boPhan'],
					'token'  => $tok,
				);
			}
		}
		self::bump_fails();
		return array( 'ok' => false, 'error' => 'PIN không đúng hoặc chưa được cấp' );
	}

	/** changePin(name, oldPin, newPin) */
	public static function change_pin( $name, $old, $new ) {
		$name = trim( (string) $name );
		$old  = trim( (string) $old );
		$new  = trim( (string) $new );
		if ( ! preg_match( '/^\d{4,8}$/', $new ) ) { return VHCPHN_Util::err( 'PIN mới phải gồm 4–8 chữ số' ); }
		VHCPHN_Cfg::seed();
		$rows = VHCPHN_Cfg::read( VHCPHN_Cfg::USER );
		$my   = -1;
		foreach ( $rows as $i => $r ) {
			if ( trim( (string) $r[0] ) === $name && trim( (string) $r[1] ) === $old ) { $my = $i; break; }
		}
		if ( $my < 0 ) { return VHCPHN_Util::err( 'PIN hiện tại không đúng' ); }
		foreach ( $rows as $j => $r ) {
			if ( $j !== $my && trim( (string) $r[1] ) === $new ) { return VHCPHN_Util::err( 'PIN này đã có người dùng khác — chọn PIN khác' ); }
		}
		VHCPHN_Cfg::set_cell( VHCPHN_Cfg::USER, $my, 1, $new );
		return VHCPHN_Util::ok();
	}

	// ---------------------------------------------------------------- phiên

	public static function issue_token( $ten, $role, $coso, $bo_phan ) {
		global $wpdb;
		self::gc( $ten );
		$tok = bin2hex( random_bytes( 32 ) );
		$wpdb->insert( VHCPHN_DB::t( 'session' ), array(
			'token'   => $tok,
			'ten'     => (string) $ten,
			'vai_tro' => (string) $role,
			'coso'    => (string) $coso,
			'bo_phan' => (string) $bo_phan,
			'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ),
		) );
		return $tok;
	}

	/** Người dùng của token (null nếu sai/hết hạn). */
	public static function user_by_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) { return null; }
		$t = VHCPHN_DB::t( 'session' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE token=%s AND het_han > UTC_TIMESTAMP()", $token ), ARRAY_A );
		if ( ! $r ) { return null; }
		/* ══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 VAI · CƠ SỞ · BỘ PHẬN ĐỌC LẠI TỪ BẢNG NGƯỜI DÙNG, KHÔNG TIN THẺ PHIÊN.
		 *
		 * Thẻ phiên ghi vai LÚC ĐĂNG NHẬP và thẻ sống 30 ngày. Nên đổi vai của một người ở màn
		 * Cấu hình KHÔNG có hiệu lực gì cho tới khi họ tự đăng xuất — và không ai bảo họ phải
		 * làm thế.
		 *
		 * Đã cắn thật 08/09/2026: anh Thắng gán vai "Kế toán máy tự động" cho một tài khoản,
		 * bấm Lưu, rồi hỏi *"đã phân qua kế toán máy tự động, tại sao vẫn nhìn được nội dung
		 * của bộ phận khác"*. Màn của người ấy vẫn ghi vai CŨ trên thanh tiêu đề — đúng thứ
		 * đang nằm trong thẻ phiên.
		 *
		 * Chiều nguy hiểm hơn nhiều: THU HỒI quyền cũng không ăn. Hạ một người từ Kế toán
		 * xuống Nhân viên, hay siết tầm nhìn đơn vị của họ, mà phiên đang mở vẫn giữ quyền cũ
		 * suốt 30 ngày.
		 *
		 * Thẻ phiên từ nay chỉ trả lời đúng một câu: NGƯỜI NÀY LÀ AI. Còn họ được làm gì thì
		 * hỏi bảng người dùng, mỗi lượt gọi.
		 *
		 * ⚠️ Tên không còn trong bảng (đã xoá tài khoản) -> dùng nguyên thẻ như cũ. Chối thẳng
		 *    thì một lượt `get_users()` lỗi là đá văng mọi người đang đăng nhập.
		 * ══════════════════════════════════════════════════════════════════════════════════ */
		$ten  = (string) $r['ten'];
		$vai  = (string) $r['vai_tro'];
		$cs   = (string) $r['coso'];
		$bp   = (string) $r['bo_phan'];
		$khoa = mb_strtolower( trim( $ten ) );
		if ( '' !== $khoa && class_exists( 'VHCPHN_Cfg' ) ) {
			foreach ( VHCPHN_Cfg::get_users() as $u ) {
				if ( mb_strtolower( trim( (string) $u['ten'] ) ) !== $khoa ) { continue; }
				$vai = ( trim( (string) $u['vaiTro'] ) !== '' ) ? (string) $u['vaiTro'] : 'Nhân viên';
				$cs  = (string) $u['coso'];
				$bp  = (string) $u['boPhan'];
				break;
			}
		}
		/* 🔴 GỬI KÈM VAI GỐC. Giao diện dựng danh sách tab bằng một bảng tra theo TÊN VAI —
		   vai tự tạo không có trong bảng đó nên rơi vào nhánh mặc định và chỉ còn đúng một tab.
		   Vai vừa tạo ra mà gần như không dùng được thì tính năng tạo vai coi như vô nghĩa. */
		return array( 'name' => $ten, 'role' => $vai,
			'roleGoc' => VHCPHN_Cfg::vai_goc( $vai ),
			'coso' => $cs, 'boPhan' => $bp );
	}

	/**
	 * TÔI LÀ AI — danh tính của phiên đang cầm token, để trang tự vào lại khỏi hỏi PIN.
	 *
	 * =========================================================================================
	 * 🔴 KHÔNG BAO GIỜ LƯU PIN Ở MÁY KHÁCH
	 * =========================================================================================
	 * Anh Thắng 07/09/2026: *"trang chưa tự lưu mật khẩu để lần sau… khỏi đăng nhập lại"*.
	 * Cách làm đúng KHÔNG phải là nhớ PIN trong trình duyệt: PIN nằm trong `localStorage` thì
	 * bất cứ ai mượn máy, hoặc bất cứ đoạn mã lạ nào chạy trên trang, đều đọc được nó — mà PIN
	 * ấy còn mở được cả trang chấm công và trang nội bộ. Trang chạy ngoài internet.
	 *
	 * Thứ được nhớ là TOKEN PHIÊN: một chuỗi ngẫu nhiên, chỉ dùng được cho đúng phiên này, thu
	 * hồi được từ máy chủ (bấm Đăng xuất), và tự chết sau 30 ngày.
	 *
	 * ⚠️ VÌ SAO PHẢI CÓ HÀM NÀY: token vốn đã sống 30 ngày trong `localStorage`, nhưng DANH TÍNH
	 *    (tên · vai · cơ sở) lại nằm ở `sessionStorage` — mà `sessionStorage` chết ngay khi đóng
	 *    trình duyệt. Nên mở lại là trang không biết mình là ai, bày cổng PIN, dù token còn
	 *    nguyên. Hỏi máy chủ một câu là xong, và danh tính lấy từ máy chủ thì luôn đúng: đổi vai
	 *    trò hay đổi cơ sở cho ai đó là lần mở trang sau họ nhận ngay, không phải đăng xuất.
	 *
	 * ⚠️ GIA HẠN LĂN: còn dưới 7 ngày thì đẩy hạn về đủ 30 ngày. Người dùng hằng ngày sẽ không
	 *    bao giờ chạm mốc hết hạn; người bỏ trang 30 ngày thì vẫn phải nhập lại — đúng ý.
	 */
	public static function ai_dang_dang_nhap( $token = '' ) {
		global $wpdb;
		$token = (string) $token;
		$u = self::user_by_token( $token );
		if ( ! $u ) { return array( 'ok' => false, 'error' => 'Phiên đã hết hạn' ); }

		/* ⚠️ ĐO KHOẢNG THỜI GIAN BẰNG PHP, KHÔNG NHỜ SQL. `TIMESTAMPDIFF()` là hàm của MySQL —
		   đúng trên host thật, nhưng bệ đỡ thử chạy SQLite thì nó trả 0, và 0 thì lần nào cũng
		   rơi vào nhánh gia hạn. Nghĩa là phép kiểm sẽ xanh mà chẳng canh được gì. Đọc ra rồi
		   so bằng `strtotime()` thì hai nơi cùng một kết quả. */
		$t   = VHCPHN_DB::t( 'session' );
		$hh  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT het_han FROM $t WHERE token=%s", $token ) );
		$con = $hh !== '' ? ( strtotime( $hh . ' UTC' ) - time() ) : 0;
		if ( $con < self::TTL_GIA_HAN ) {
			$wpdb->update( $t, array( 'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ) ),
				array( 'token' => $token ) );
		}
		return array( 'ok' => true, 'name' => $u['name'], 'role' => $u['role'],
			'roleGoc' => $u['roleGoc'], 'coso' => $u['coso'], 'boPhan' => $u['boPhan'] );
	}

	public static function logout( $token ) {
		global $wpdb;
		$wpdb->delete( VHCPHN_DB::t( 'session' ), array( 'token' => (string) $token ) );
		return VHCPHN_Util::ok();
	}

	private static function gc( $ten = '' ) {
		global $wpdb;
		$t = VHCPHN_DB::t( 'session' );
		$wpdb->query( "DELETE FROM $t WHERE het_han < UTC_TIMESTAMP()" );
		// SSO phát token mỗi lần tải trang -> giữ tối đa 20 phiên còn sống cho mỗi người.
		if ( $ten !== '' ) {
			$keep = $wpdb->get_col( $wpdb->prepare( "SELECT token FROM $t WHERE ten=%s ORDER BY het_han DESC LIMIT 20", (string) $ten ) );
			if ( is_array( $keep ) && count( $keep ) >= 20 ) {
				$in = implode( ',', array_map( array( __CLASS__, 'quote_token' ), $keep ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE ten=%s AND token NOT IN ($in)", (string) $ten ) );
			}
		}
	}

	private static function quote_token( $t ) {
		return "'" . preg_replace( '/[^0-9a-f]/', '', (string) $t ) . "'";
	}

	// ---------------------------------------------------------------- hãm thử PIN

	private static function fail_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhcphn_fail_' . md5( $ip );
	}

	private static function is_locked() {
		return (int) get_transient( self::fail_key() ) >= 10;
	}

	private static function bump_fails() {
		$k = self::fail_key();
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );
	}

	private static function clear_fails() {
		delete_transient( self::fail_key() );
	}

	/**
	 * MỞ KHÓA ĐĂNG NHẬP (gọi từ wp-admin).
	 *
	 * Khóa đếm theo IP và tự hết sau 10 phút, nhưng người bị khóa thì đang cần vào ngay.
	 * Ai vào được wp-admin thì đã là quản trị WordPress, cho mở khóa luôn khỏi phải chờ.
	 * Xóa cả khóa của IP đang gọi lẫn mọi khóa còn hạn trong bảng transient.
	 */
	public static function mo_khoa() {
		global $wpdb;
		delete_transient( self::fail_key() );
		$n = 0;
		$rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_vhcp\\_fail\\_%'" );
		foreach ( (array) $rows as $o ) {
			$key = preg_replace( '/^_transient_/', '', (string) $o );
			if ( $key !== '' ) { delete_transient( $key ); $n++; }
		}
		return $n;
	}

	// ---------------------------------------------------------------- SSO từ trang tổng K&H

	public static function sso_secret() {
		$s = VHCPHN_Meta::get( 'SSO_SECRET' );
		if ( ! $s ) { $s = get_option( 'vhcphn_sso_secret' ); }
		return $s ? $s : '';
	}

	/**
	 * verifySsoToken(): base64url(payload).base64url(HMAC-SHA256) — cùng thuật toán
	 * với app Apps Script cũ nên trang tổng K&H không phải sửa gì.
	 */
	public static function verify_sso_token( $token ) {
		$secret = self::sso_secret();
		if ( ! $secret || ! $token ) { return null; }
		$parts = explode( '.', (string) $token );
		if ( count( $parts ) !== 2 ) { return null; }
		list( $p, $sig ) = $parts;
		$expect = self::b64url( hash_hmac( 'sha256', $p, $secret, true ) );
		if ( ! hash_equals( $expect, $sig ) ) { return null; }
		$json = self::b64url_decode( $p );
		$obj  = json_decode( $json, true );
		if ( ! is_array( $obj ) || empty( $obj['x'] ) ) { return null; }
		if ( ( time() * 1000 ) > (float) $obj['x'] ) { return null; }
		return $obj;
	}

	/** resolveSsoUser(): vai trò trang tổng → vai trò Chi Phí, có bảng override theo email. */
	public static function resolve_sso_user( $ident ) {
		$email    = trim( (string) ( isset( $ident['e'] ) ? $ident['e'] : '' ) );
		$branches = isset( $ident['b'] ) ? $ident['b'] : '';
		$branches = is_array( $branches ) ? implode( ', ', $branches ) : (string) $branches;
		$hub      = (string) ( isset( $ident['r'] ) ? $ident['r'] : '' );

		if ( $hub === 'ADMIN' )                { $role = 'Admin'; }
		elseif ( $hub === 'QUAN_LY' )          { $role = 'Quản lý'; }
		elseif ( $hub === 'CUA_HANG_TRUONG' )  { $role = 'Nhân viên'; }
		elseif ( $hub === 'KE_TOAN' )          { $role = 'Kế toán cá nhân'; }
		else                                   { $role = 'Nhân viên'; }

		$coso = $branches;
		$ov   = self::sso_overrides();
		$k    = strtolower( $email );
		if ( isset( $ov[ $k ] ) ) {
			if ( ! empty( $ov[ $k ]['role'] ) ) { $role = $ov[ $k ]['role']; }
			if ( ! empty( $ov[ $k ]['coso'] ) ) { $coso = $ov[ $k ]['coso']; }
		}
		return array( 'name' => (string) ( isset( $ident['n'] ) ? $ident['n'] : '' ), 'role' => $role,
			'roleGoc' => VHCPHN_Cfg::vai_goc( $role ), 'coso' => $coso );
	}

	private static function sso_overrides() {
		$hit = get_transient( 'vhcphn_ssomap' );
		if ( is_array( $hit ) ) { return $hit; }
		$out = array();
		foreach ( VHCPHN_Cfg::read( VHCPHN_Cfg::SSO ) as $r ) {
			$em = strtolower( trim( (string) $r[0] ) );
			if ( $em === '' ) { continue; }
			$out[ $em ] = array( 'role' => trim( (string) $r[1] ), 'coso' => trim( (string) $r[2] ) );
		}
		set_transient( 'vhcphn_ssomap', $out, 300 );
		return $out;
	}

	private static function b64url( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	private static function b64url_decode( $s ) {
		$s .= str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 );
		return base64_decode( strtr( $s, '-_', '+/' ) );
	}
}
