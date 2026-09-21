<?php
/**
 * ĐĂNG NHẬP BẰNG PIN, PHIÊN LÀM VIỆC, PHÂN QUYỀN. Thay `JP2_02_Auth.gs`.
 *
 * =============================================================================================
 * 🔴🔴 ĐỌC KHỐI NÀY TRƯỚC KHI MỞ TRANG RA INTERNET.
 * =============================================================================================
 * Hệ JP đăng nhập CHỈ BẰNG PIN BA SỐ — không tên đăng nhập, không mật khẩu. Ba số là 1.000 khả
 * năng. Chính bản gốc đã ghi thẳng điều đó vào mã, không giấu:
 *
 *    *"PIN 3 số = 1000 khả năng, mà web app ở ANYONE_ANONYMOUS. Băm chỉ chống người đọc được
 *      sheet, KHÔNG chống dò: 1000 giá trị thì thử hết là ra. […] Muốn bảo vệ thật thì phải
 *      đổi `access` của web app, không phải đổi code."*
 *
 * Bản chuyển này GIỮ NGUYÊN độ dài PIN, vì mọi tài khoản đang chạy ngoài đời đều là ba số —
 * đổi độ dài là khoá cửa cả hệ trong một lượt cài đặt. Nhưng phải nói rõ: **đây là cánh cửa
 * yếu nhất của cả hệ thống, và nó không tự khoẻ lên khi chuyển sang WordPress.**
 *
 * Chỗ duy nhất bản chuyển làm chặt hơn được mà không phá tương thích là LÀM CHẬM NGƯỜI DÒ:
 *   · bản gốc đếm SỐ LẦN SAI CHUNG CHO CẢ HỆ trong bộ nhớ tạm — người dò và nhân viên gõ nhầm
 *     dùng chung một bộ đếm, nên một người dò làm chậm cả cửa hàng, còn kẻ dò kiên nhẫn thì chỉ
 *     cần chờ 10 phút cho bộ đếm nguội;
 *   · bản này đếm RIÊNG THEO ĐỊA CHỈ MÁY, ghi xuống cơ sở dữ liệu chứ không bộ nhớ tạm (bộ nhớ
 *     tạm bay mất sau mỗi lượt nạp trang), nên kẻ dò tự làm chậm chính mình mà không kéo theo
 *     người khác.
 *
 * ⚠️ VẪN CỐ Ý KHÔNG KHOÁ CỨNG. Khoá cứng là tự tay đưa cho người ngoài cách làm cả cửa hàng
 *    không đăng nhập được: cứ gõ sai vài lần vào PIN của người ta là xong.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 PIN KHÔNG BAO GIỜ ĐI RA MÀN HÌNH, KHÔNG BAO GIỜ LƯU Ở MÁY KHÁCH.
 * ---------------------------------------------------------------------------------------------
 * Trang này chạy ngoài internet. Máy khách chỉ giữ THẺ PHIÊN (token) — thứ hết hạn được và thu
 * hồi được. Lưu PIN ở `localStorage` là ai mượn máy một phút cũng đọc ra, và PIN thì dùng lại
 * được mãi.
 *
 * ---------------------------------------------------------------------------------------------
 * ⚠️ CÒN DÙNG PIN MẶC ĐỊNH THÌ ĐĂNG NHẬP ĐƯỢC, NHƯNG MỌI CỬA ĐỀU ĐÓNG.
 * ---------------------------------------------------------------------------------------------
 * `222` và `101` nằm trong tài liệu và trong mã — coi như chưa có mật khẩu. Bản gốc chặn ngay
 * tại `jpAuth_` chứ không ở từng cửa, vì có chín hàm chỉ đi qua `jpAuth_`; chặn ở từng cửa là
 * còn chín lối hở. Giữ nguyên nước đi ấy.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Auth {

	const PIN_LEN       = 3;
	const PHIEN_GIO     = 12;
	const O_PHIEN       = 'vhjp_phien';       // nơi giữ thẻ phiên
	const O_SAI         = 'vhjp_pin_sai';     // sổ đếm gõ sai, theo địa chỉ máy
	const CUA_SO_SAI    = 600;                // đếm số lần sai trong 10 phút
	const CHO_TOI_DA_MS = 5000;

	/** PIN mặc định hệ tự cấp — ai cũng đoán ra, nên còn dùng là coi như chưa có mật khẩu. */
	public static function pin_mac_dinh() { return array( '222', '101' ); }

	/* ═══════════════════════ BĂM PIN ═══════════════════════ */

	/**
	 * Băm một PIN.
	 *
	 * 🔴 KHÔNG dùng SHA-256 trần như bản gốc. Với 1.000 khả năng, SHA-256 trần là bảng tra cứu
	 *    dựng xong trong một phần nghìn giây — ai đọc được cơ sở dữ liệu là có luôn mọi PIN.
	 *    `password_hash()` của PHP (bcrypt) cố ý chạy chậm, nên cùng việc ấy mất hàng giờ.
	 *    Đây là chỗ bản chuyển mạnh hơn bản gốc mà KHÔNG phá gì: người dùng vẫn gõ đúng ba số
	 *    như cũ, chỉ cách cất giữ đổi.
	 *
	 * ⚠️ Nó không cứu được cửa trước (dò 1.000 số qua mạng) — xem khối 🔴🔴 ở đầu tệp. Nó cứu
	 *    trường hợp cơ sở dữ liệu bị đọc trộm.
	 */
	public static function bam( $pin ) {
		return password_hash( (string) $pin, PASSWORD_DEFAULT );
	}

	/**
	 * PIN có khớp giá trị đã lưu không. Trả `array( 'ok', 'cu' )` — `cu` nghĩa là bản ghi còn
	 * lưu PIN thô, cần băm lại ngay tại chỗ (đúng nước đi của bản gốc, cho dữ liệu chuyển sang).
	 */
	public static function khop( $da_luu, $pin ) {
		$s = (string) $da_luu;
		$p = (string) $pin;
		if ( '' === $s ) { return array( 'ok' => false, 'cu' => false ); }
		/* Chuỗi băm của PHP bắt đầu bằng `$2y$`, `$argon2` … — nhận ra thì so bằng hàm băm. */
		if ( '$' === substr( $s, 0, 1 ) ) {
			return array( 'ok' => password_verify( $p, $s ), 'cu' => false );
		}
		/* PIN thô còn sót từ Google Sheets. So bằng `hash_equals` để không rò rỉ độ dài khớp
		   qua thời gian so sánh. */
		return array( 'ok' => '' !== $p && hash_equals( $s, $p ), 'cu' => true );
	}

	/** Chuẩn hoá PIN: chỉ chữ số, và phải đúng độ dài. Sai thì trả chuỗi rỗng. */
	public static function chuan_pin( $pin ) {
		$s = preg_replace( '/\D/', '', VHJP_Doc::str( $pin ) );
		return strlen( $s ) === self::PIN_LEN ? $s : '';
	}

	/** PIN này đã có tài khoản nào dùng chưa. Đăng nhập chỉ bằng PIN nên PIN phải DUY NHẤT. */
	public static function pin_da_dung( $pin, $bo_qua_id = '' ) {
		$p = self::chuan_pin( $pin );
		if ( '' === $p ) { return null; }
		foreach ( VHJP_Nguon::doc( 'JP_Users' ) as $u ) {
			if ( '' !== (string) $bo_qua_id && (string) $u['id'] === (string) $bo_qua_id ) { continue; }
			if ( '' === VHJP_Doc::str( $u['pin'] ) ) { continue; }
			$k = self::khop( $u['pin'], $p );
			if ( $k['ok'] ) { return $u; }
		}
		return null;
	}

	/* ═══════════════════════ LÀM CHẬM NGƯỜI DÒ ═══════════════════════ */

	/** Địa chỉ máy đang gọi. Chỉ để đếm số lần gõ sai — không dùng để phân quyền. */
	public static function dia_chi() {
		$v = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return '' === $v ? 'khong-ro' : $v;
	}

	/**
	 * Ghi nhận một lượt gõ sai, rồi chờ lâu dần. Trả số lần sai trong cửa sổ.
	 *
	 * ⚠️ ĐẾM THEO ĐỊA CHỈ MÁY, không đếm chung cả hệ. Đếm chung thì một người dò làm chậm mọi
	 *    nhân viên đang đăng nhập — tức là biến chốt chặn thành công cụ phá hoại.
	 */
	public static function ghi_nhan_sai( $ngu = true ) {
		$so = get_option( self::O_SAI, array() );
		if ( ! is_array( $so ) ) { $so = array(); }
		$nay = time();
		$key = self::dia_chi();

		/* Dọn mọi mục đã nguội — sổ này không được phép phình mãi. */
		foreach ( $so as $k => $v ) {
			if ( ! isset( $v['luc'] ) || $v['luc'] < $nay - self::CUA_SO_SAI ) { unset( $so[ $k ] ); }
		}
		$n = isset( $so[ $key ]['n'] ) ? (int) $so[ $key ]['n'] + 1 : 1;
		$so[ $key ] = array( 'n' => $n, 'luc' => $nay );
		update_option( self::O_SAI, $so );

		if ( $n >= 5 && $ngu ) {
			usleep( min( self::CHO_TOI_DA_MS, 500 * $n ) * 1000 );
		}
		return $n;
	}

	/** Xoá sổ đếm cho địa chỉ này — gọi sau một lượt đăng nhập ĐÚNG. */
	public static function xoa_dem_sai() {
		$so = get_option( self::O_SAI, array() );
		if ( ! is_array( $so ) ) { return; }
		unset( $so[ self::dia_chi() ] );
		update_option( self::O_SAI, $so );
	}

	/* ═══════════════════════ ĐĂNG NHẬP ═══════════════════════ */

	/**
	 * Đăng nhập bằng PIN. Trả `array( 'ok', 'token', 'user' )`.
	 *
	 * 🔴 KHÔNG BAO GIỜ trả PIN ra ngoài, và câu báo lỗi CỐ Ý không phân biệt "không có PIN này"
	 *    với "tài khoản đã tắt" — phân biệt là chỉ cho người dò biết số nào có thật.
	 */
	public static function dang_nhap( $pin ) {
		$p = self::chuan_pin( $pin );
		if ( '' === $p ) {
			return array( 'ok' => false, 'msg' => 'Nhập đúng ' . self::PIN_LEN . ' số' );
		}

		$trung = null; $cu = false;
		foreach ( VHJP_Nguon::doc( 'JP_Users' ) as $u ) {
			if ( 'N' === VHJP_Doc::str( $u['active'] ) || '' === VHJP_Doc::str( $u['pin'] ) ) { continue; }
			$k = self::khop( $u['pin'], $p );
			if ( $k['ok'] ) { $trung = $u; $cu = $k['cu']; break; }
		}

		if ( ! $trung ) {
			self::ghi_nhan_sai();
			return array( 'ok' => false, 'msg' => 'Mã PIN không đúng' );
		}

		/* Ai còn PIN thô (chuyển từ Sheets sang) thì băm lại ngay tại chỗ. */
		if ( $cu ) {
			VHJP_Nguon::sua( 'JP_Users', $trung['id'], array( 'pin' => self::bam( $p ) ) );
		}

		self::xoa_dem_sai();
		return self::mo_phien( $trung, $p );
	}

	/** PIN này có phải PIN mặc định hệ tự cấp không. */
	public static function la_pin_mac_dinh( $pin ) {
		return in_array( self::chuan_pin( $pin ), self::pin_mac_dinh(), true );
	}

	/** Cấp thẻ phiên và ghi phiên. */
	public static function mo_phien( $u, $pin_vua_go ) {
		$token = self::the_moi();
		$phien = array(
			'id'          => (string) $u['id'],
			'username'    => VHJP_Doc::str( $u['username'] ),
			'hoTen'       => VHJP_Doc::str( $u['hoTen'] ),
			'role'        => VHJP_Doc::str( $u['role'] ),
			'machineType' => VHJP_Doc::str( $u['machineType'] ),
			'locationIds' => self::tach_ids( $u['locationIds'] ),
			'phaiDoiPin'  => self::la_pin_mac_dinh( $pin_vua_go ),
			'het_han'     => time() + self::PHIEN_GIO * 3600,
		);
		$ds = self::doc_phien();
		$ds[ $token ] = $phien;
		self::ghi_phien( $ds );
		return array( 'ok' => true, 'token' => $token, 'user' => self::user_cong_khai( $phien ) );
	}

	/**
	 * Thẻ phiên mới.
	 *
	 * 🔴 PHẢI LÀ NGẪU NHIÊN AN TOÀN. `uniqid()` hay `rand()` đoán được từ thời điểm gọi — đoán
	 *    được thẻ là vào được phiên người khác mà không cần biết PIN nào cả.
	 */
	public static function the_moi() {
		return bin2hex( random_bytes( 32 ) );
	}

	public static function thoat( $token ) {
		$ds = self::doc_phien();
		unset( $ds[ (string) $token ] );
		self::ghi_phien( $ds );
		return array( 'ok' => true );
	}

	private static function doc_phien() {
		$ds = get_option( self::O_PHIEN, array() );
		return is_array( $ds ) ? $ds : array();
	}

	/** Ghi sổ phiên, dọn luôn mọi phiên đã hết hạn — sổ này không được phép phình mãi. */
	private static function ghi_phien( $ds ) {
		$nay = time();
		foreach ( $ds as $k => $v ) {
			if ( ! isset( $v['het_han'] ) || $v['het_han'] < $nay ) { unset( $ds[ $k ] ); }
		}
		update_option( self::O_PHIEN, $ds );
	}

	/**
	 * Lấy người dùng từ thẻ phiên. Trả `null` khi phiên hết hạn hoặc thẻ lạ.
	 *
	 * ⚠️ CÒN DÙNG PIN MẶC ĐỊNH THÌ CHẶN Ở ĐÂY, không chặn ở từng cửa — xem khối ⚠️ đầu tệp.
	 *    `$cho_pin_mac_dinh` chỉ dành cho đúng ba đường phải sống để còn đổi được PIN: dựng màn
	 *    hình, đổi PIN, và thoát.
	 */
	public static function kiem( $token, $cho_pin_mac_dinh = false ) {
		$ds = self::doc_phien();
		$k  = (string) $token;
		if ( '' === $k || ! isset( $ds[ $k ] ) ) {
			return array( 'ok' => false, 'ma' => 'PHIEN_HET_HAN' );
		}
		$u = $ds[ $k ];
		if ( ! isset( $u['het_han'] ) || $u['het_han'] < time() ) {
			unset( $ds[ $k ] );
			self::ghi_phien( $ds );
			return array( 'ok' => false, 'ma' => 'PHIEN_HET_HAN' );
		}
		if ( ! empty( $u['phaiDoiPin'] ) && ! $cho_pin_mac_dinh ) {
			return array( 'ok' => false, 'ma' => 'PHAI_DOI_PIN',
				'msg' => 'Tài khoản đang dùng PIN mặc định. Đặt PIN mới thì mới dùng được.' );
		}
		/* Phiên TRƯỢT: còn làm việc thì còn phiên. */
		$ds[ $k ]['het_han'] = time() + self::PHIEN_GIO * 3600;
		self::ghi_phien( $ds );
		return array( 'ok' => true, 'user' => $u );
	}

	/**
	 * Người dùng ở dạng ĐƯA RA NGOÀI ĐƯỢC.
	 *
	 * 🔴 Cố ý dựng danh sách trường theo lối CHO PHÉP, không theo lối loại trừ. Lối loại trừ
	 *    (`unset($u['pin'])`) là thêm một cột nhạy cảm vào bảng nào đó là nó tự chảy ra ngoài.
	 */
	public static function user_cong_khai( $u ) {
		return array(
			'id'          => isset( $u['id'] ) ? $u['id'] : '',
			'hoTen'       => isset( $u['hoTen'] ) ? $u['hoTen'] : '',
			'role'        => isset( $u['role'] ) ? $u['role'] : '',
			'machineType' => isset( $u['machineType'] ) ? $u['machineType'] : '',
			'locationIds' => isset( $u['locationIds'] ) ? $u['locationIds'] : array(),
			'phaiDoiPin'  => ! empty( $u['phaiDoiPin'] ),
		);
	}

	/** `'L-1, L-2 | L-3'` -> `array('L-1','L-2','L-3')`. */
	public static function tach_ids( $v ) {
		$ra = array();
		foreach ( preg_split( '/[,;|]/', VHJP_Doc::str( $v ) ) as $s ) {
			$s = trim( $s );
			if ( '' !== $s ) { $ra[] = $s; }
		}
		return $ra;
	}

	/* ═══════════════════════ VAI TRÒ ═══════════════════════ */

	const VAI_NV     = 'NHANVIEN';
	const VAI_KT     = 'KETOAN';
	const VAI_KT_DT  = 'KT_DOANHTHU';   // cũ — chỉ còn để đọc dữ liệu đã có
	const VAI_KT_KHO = 'KT_KHO';        // cũ — chỉ còn để đọc dữ liệu đã có

	public static function la_nv( $u ) {
		return self::VAI_NV === VHJP_Doc::str( isset( $u['role'] ) ? $u['role'] : '' );
	}

	/**
	 * Tên vai trò để ĐƯA VÀO CÂU CHẶN.
	 *
	 * 🔴 Hai câu chặn vai trò phải NÓI RA ĐANG LÀ AI và LÀM GÌ TIẾP. Cả hai màn dùng CHUNG một
	 *    cửa đăng nhập, nên PIN kế toán mở được cả màn nhân viên và ngược lại — vào được nhưng
	 *    bấm gì cũng bị chặn. Câu cũ của bản gốc chỉ ghi *"Chức năng này dành cho nhân viên"*:
	 *    đúng nhưng vô dụng, người đọc không biết mình đang đăng nhập bằng PIN nào. Anh Andy
	 *    mắc đúng chỗ này 06/08/2026 — gõ PIN kế toán vào màn nhân viên rồi bấm "Mở báo cáo".
	 */
	public static function ten_vai_tro( $u ) {
		$r = VHJP_Doc::str( isset( $u['role'] ) ? $u['role'] : '' );
		if ( self::la_kt( array( 'role' => $r ) ) ) { return 'KẾ TOÁN'; }
		if ( self::VAI_NV === $r ) { return 'NHÂN VIÊN'; }
		return '' !== $r ? $r : 'chưa đặt';
	}

	/** Câu chặn khi màn này của nhân viên mà người đăng nhập không phải nhân viên. */
	public static function cau_chan_nv( $u ) {
		return 'Màn này của NHÂN VIÊN CƠ SỞ, mà mã PIN vừa nhập là tài khoản '
			. self::ten_vai_tro( $u ) . ' ('
			. ( VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ) ?: '—' ) . '). '
			. 'Thoát ra rồi đăng nhập lại bằng mã PIN của cơ sở. '
			. 'Quên PIN cơ sở thì vào web kế toán → Cấu hình → Tài khoản → Cấp lại.';
	}

	/**
	 * CẤP TÀI KHOẢN ĐẦU TIÊN — chỉ khi sổ người dùng HOÀN TOÀN rỗng.
	 *
	 * =========================================================================================
	 * 🔴 BƯỚC NÀY BẢN GỐC KHÔNG CẦN, BẢN NÀY THÌ CÓ.
	 * =========================================================================================
	 * Bên Apps Script, sổ người dùng đã sẵn trong Google Sheet, nên `jpCapPinMacDinh_()` chỉ
	 * phải GÁN PIN cho một tài khoản đã có. Trên WordPress cài mới thì bảng rỗng trơn: không
	 * tài khoản nào, không PIN nào, và màn đăng nhập chỉ hỏi PIN — tức KHÔNG AI VÀO ĐƯỢC, kể
	 * cả người vừa cài. Đó đúng là chỗ anh Thắng mắc: bật plugin xong *"chưa truy cập được"*.
	 *
	 * ⚠️ CHỈ CHẠY KHI BẢNG RỖNG. Có dù một tài khoản thì thôi — không bao giờ được đẻ thêm
	 *    tài khoản vào một sổ đang dùng, và càng không được đụng PIN của ai.
	 *
	 * ⚠️ PIN `222` là số MẶC ĐỊNH của chính bản gốc, đã nằm trong mã và trong tài liệu — nó
	 *    không phải bí mật. Và vì nó nằm trong danh sách PIN mặc định nên `kiem()` đóng MỌI cửa
	 *    cho tới khi đổi: đăng nhập được, nhưng không làm được gì ngoài đổi PIN.
	 */
	public static function cap_tai_khoan_dau() {
		if ( VHJP_Nguon::doc( 'JP_Users' ) ) { return null; }
		$o = array(
			'username'    => 'ketoan',
			'hoTen'       => 'Kế toán',
			'role'        => self::VAI_KT,
			'pin'         => self::bam( '222' ),
			'active'      => 'Y',
			'createdAt'   => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),
			'note'        => 'Tài khoản đầu tiên do hệ tự cấp — PIN mặc định, ĐỔI NGAY',
			'locationIds' => '',
			'machineType' => '',
		);
		$kq = VHJP_Ma::them( 'JP_Users', 'U', $o );
		if ( false === $kq ) { return null; }
		VHJP_NhatKy::ghi( null, 'PIN_KHOI_TAO', '', 'ketoan', array( 'role' => self::VAI_KT ) );
		return $kq;
	}

	/**
	 * Tài khoản kế toán đầu tiên CÒN đang dùng PIN mặc định không.
	 *
	 * Dùng để màn quản trị biết có nên nhắc số PIN ấy hay không — đổi rồi thì thôi nhắc, vì
	 * lúc đó số ấy không còn đúng và nhắc chỉ làm người ta gõ nhầm.
	 */
	public static function con_pin_mac_dinh() {
		foreach ( VHJP_Nguon::doc( 'JP_Users' ) as $u ) {
			if ( '' === VHJP_Doc::str( $u['pin'] ) ) { continue; }
			foreach ( self::pin_mac_dinh() as $p ) {
				if ( self::khop( $u['pin'], $p )['ok'] ) { return $p; }
			}
		}
		return '';
	}

	/**
	 * Người này có được nhìn cơ sở ấy không.
	 *
	 * ⚠️ Kế toán thấy hết; nhân viên chỉ thấy cơ sở đã gán. So theo CHUỖI — danh sách gán lưu
	 *    dạng `'L-1, L-2'` nên phần tử là chuỗi, còn nơi gọi hay truyền số vào.
	 */
	public static function xem_duoc_coso( $u, $ma_coso ) {
		if ( self::la_kt( $u ) ) { return true; }
		$ds = isset( $u['locationIds'] ) && is_array( $u['locationIds'] ) ? $u['locationIds'] : array();
		return in_array( (string) $ma_coso, array_map( 'strval', $ds ), true );
	}

	/**
	 * Đổi PIN.
	 *
	 * 🔴 GỠ CỜ "PHẢI ĐỔI PIN" TRÊN CHÍNH PHIÊN ĐANG DÙNG. Không gỡ thì đổi xong vẫn bị chặn
	 *    cho tới lúc hết phiên — mà PIN mới đã không còn là mặc định nữa. Người dùng đổi xong,
	 *    thấy vẫn chặn, sẽ đổi lại lần nữa rồi đi hỏi.
	 *
	 * 🔴 PIN PHẢI DUY NHẤT TOÀN HỆ. Đăng nhập chỉ bằng PIN nên hai người trùng PIN là một
	 *    người vào được tài khoản của người kia mà không làm gì sai cả.
	 */
	public static function doi_pin( $token, $pin_cu, $pin_moi ) {
		$k = self::kiem( $token, true );          // phải qua được kể cả khi còn PIN mặc định
		if ( empty( $k['ok'] ) ) {
			return array( 'ok' => false, 'msg' => 'Phiên hết hạn, đăng nhập lại' );
		}
		$u   = $k['user'];
		$moi = self::chuan_pin( $pin_moi );
		if ( '' === $moi ) {
			return array( 'ok' => false, 'msg' => 'PIN mới phải đúng ' . self::PIN_LEN . ' số' );
		}
		if ( $moi === self::chuan_pin( $pin_cu ) ) {
			return array( 'ok' => false, 'msg' => 'PIN mới phải khác PIN cũ' );
		}

		$hs = VHJP_Nguon::tim_mot( 'JP_Users', 'id', $u['id'] );
		if ( ! $hs ) { return array( 'ok' => false, 'msg' => 'Không tìm thấy tài khoản' ); }
		if ( '' === VHJP_Doc::str( $hs['pin'] ) || ! self::khop( $hs['pin'], $pin_cu )['ok'] ) {
			/* Gõ sai PIN hiện tại cũng tính là một lượt sai — không thì đây thành cửa dò PIN
			   không bị làm chậm, chỉ cần một thẻ phiên bất kỳ. */
			self::ghi_nhan_sai();
			return array( 'ok' => false, 'msg' => 'PIN hiện tại không đúng' );
		}
		if ( self::pin_da_dung( $moi, $u['id'] ) ) {
			return array( 'ok' => false, 'msg' => 'PIN này đã có người dùng, chọn số khác' );
		}

		if ( ! VHJP_Nguon::sua( 'JP_Users', $u['id'], array( 'pin' => self::bam( $moi ) ) ) ) {
			return array( 'ok' => false, 'msg' => 'Không lưu được PIN mới' );
		}

		$ds = self::doc_phien();
		if ( isset( $ds[ (string) $token ] ) ) {
			$ds[ (string) $token ]['phaiDoiPin'] = self::la_pin_mac_dinh( $moi );
			self::ghi_phien( $ds );
		}
		VHJP_NhatKy::ghi( $u, 'DOI_PIN', '', $u['id'], '' );
		return array( 'ok' => true, 'msg' => 'Đã đổi PIN' );
	}

	/** Nhận cả hai vai kế toán cũ, để tài khoản đã có vẫn dùng được. */
	public static function la_kt( $u ) {
		$v = VHJP_Doc::str( isset( $u['role'] ) ? $u['role'] : '' );
		return in_array( $v, array( self::VAI_KT, self::VAI_KT_DT, self::VAI_KT_KHO ), true );
	}
}
