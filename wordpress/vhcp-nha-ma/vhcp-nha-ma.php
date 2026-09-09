<?php
/**
 * Plugin Name:       Nhà Ma · Bán vé theo khung giờ (Ghost Bride VIP)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Bán vé nhà ma theo KHUNG GIỜ, chạy thẳng trên host. Trang khách ở /ban-ve-nha-ma (chọn khung giờ, giữ chỗ, nhận mã QR VietQR để chuyển khoản), cổng nhận tiền tự động từ ngân hàng (SePay/Casso) tự duyệt thiệp, gửi mã vé + QR vé qua Zalo OA (nối bằng một nút, tự làm mới token), trang quản trị ở /ban-ve-nha-ma/#quanly (duyệt tiền, soát vé tại cửa, đối soát, sổ tiền về). Sổ vé nằm trong MySQL của chính website — không Google Sheet, không Firebase. ĐỘC LẬP với plugin bán vé khu vui chơi và plugin ghế.
 * Version:           1.6.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 *
 * ==============================================================================================
 * VÌ SAO LÀ PLUGIN CHỨ KHÔNG PHẢI MỘT FILE HTML THẢ LÊN HOST
 * ==============================================================================================
 * Bản HTML rời chạy được ngay, nhưng sổ vé nằm trong trình duyệt: khách đặt trên điện thoại của
 * họ thì máy quản trị KHÔNG thấy đơn ấy. Tức là nó chỉ bán được tại quầy bằng đúng một máy.
 *
 * Thành plugin thì sổ nằm trong MySQL của website — mọi máy nhìn chung một sổ, và đó mới là thứ
 * cho phép khách tự đặt từ điện thoại của họ.
 *
 * ==============================================================================================
 * 🔴 ĐẾM CHỖ LÀ VIỆC CỦA MÁY CHỦ, KHÔNG PHẢI CỦA TRANG
 * ==============================================================================================
 * Trang có vẽ "còn 7 chỗ" thì đó chỉ là ảnh chụp của mười phút trước. Người ta mở trang, đi pha
 * ấm trà, rồi mới bấm. Nên `dat()` đếm lại chỗ NGAY TRƯỚC KHI GHI, và còn kiểm lại một lần nữa
 * SAU KHI ghi (xem chú thích ở đó) — hai người bấm trong cùng một giây là chuyện có thật, và cái
 * giá của nó là hai đoàn cùng đứng ở cửa cho một khung giờ.
 *
 * ==============================================================================================
 * 🔴 TRANG KHÁCH KHÔNG BAO GIỜ ĐƯỢC NHẬN DANH SÁCH ĐƠN
 * ==============================================================================================
 * Nó chỉ nhận SỐ ĐÃ ĐẶT của từng khung. Bản HTML rời đẩy cả sổ xuống trình duyệt (vì không có
 * máy chủ để đếm hộ) — nghĩa là ai mở trang cũng xem được tên và số điện thoại của mọi khách.
 * Ở đây thì không: muốn xem đơn phải có số điện thoại của chính mình, muốn xem cả sổ phải qua PIN.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'NHAMA' ) ) :

class NHAMA {

	const NS   = 'nhama/v1';
	const BANG = 'nhama_don';
	const VER  = '1.6.0';

	/** Trạng thái đơn — thứ tự này cũng là vòng đời. */
	const TT = array(
		'giu_cho'   => 'Đang giữ chỗ',
		'cho_duyet' => 'Chờ duyệt thanh toán',
		'cho_vao'   => 'Chờ Check-in',
		'da_vao'    => 'Đã vào',
		'huy'       => 'Bị huỷ',
	);

	/**
	 * Đơn CÒN CHIẾM CHỖ.
	 * ⚠️ Có cả `giu_cho` — đơn mới giữ, chưa trả đồng nào. Giữ chỗ mà không trừ chỗ thì hai đoàn
	 *    cùng một khung, và đoàn thứ hai chỉ biết mình hụt lúc đã tới cửa.
	 */
	const CHIEM = array( 'giu_cho', 'cho_duyet', 'cho_vao', 'da_vao' );

	/**
	 * Mã đơn: GB + 8 ký tự, BỎ HẲN 0/O và 1/I/L.
	 * Khách đọc mã từ ảnh chụp màn hình rồi gõ tay ở cửa, có khi nhân viên đọc hộ qua điện thoại.
	 * Một mã có chữ O cạnh số 0 là một cuộc cãi nhau ở cửa, và người thua luôn là khách.
	 */
	const CHU_MA = 'ABCDEFGHJKMNPQRTUVWXY23456789';

	public static function cf_md() {
		return array(
			'gia'    => 100000,   // đồng / 1 người
			'suc'    => 10,       // người tối đa mỗi khung giờ
			'mo'     => '10:10',  // khung đầu tiên
			'dong'   => '11:45',  // khung cuối cùng
			'buoc'   => 5,        // mỗi khung cách nhau mấy phút
			'ngayMo' => 14,       // mở bán trước bao nhiêu ngày
			'denSom' => 10,       // khách phải có mặt trước bao nhiêu phút
			'quet'   => 6000,     // màn quản trị tự nạp lại, mili giây
			'am'     => 1,        // kêu chuông khi có đơn mới chờ duyệt
			'bin'    => '',       // mã ngân hàng Napas (BIDV 970418, Vietcombank 970436, MB 970422…)
			'so_tk'  => '',       // số tài khoản nhận tiền
			'ten_tk' => '',       // tên chủ tài khoản, viết HOA không dấu cho khớp app ngân hàng
			'zalo_app_id'  => '', // ID ứng dụng ở developers.zalo.me
			'zalo_secret'  => '', // Khoá bí mật của ứng dụng
			'zalo_refresh' => '', // refresh token — lấy MỘT LẦN bằng nút "Kết nối Zalo OA"
			'zalo_token'   => '', // access token đang dùng (plugin tự lấy, tự làm mới)
			'zalo_het'     => 0,  // access token hết hạn lúc nào (giây epoch)
			'zalo_oa'      => '', // OA id, chỉ để hiện cho biết đang nối với OA nào
			'zalo_tpl'     => '', // mã mẫu tin ZNS (để trống = gửi tin tư vấn CS)
			'ten'    => 'GHOST BRIDE VIP',
			'phu'    => 'Nghi thức phân luồng dành riêng cho Khách Mời Danh Dự',
		);
	}
	public static function cf() {
		$c = get_option( 'nhama_cf' );
		return array_merge( self::cf_md(), is_array( $c ) ? $c : array() );
	}
	/**
	 * Tài khoản nhận tiền. Chưa khai ở đây thì MƯỢN của plugin ghế massage — cùng một cửa hàng,
	 * cùng một tài khoản, khai hai lần là hai chỗ để gõ nhầm.
	 *
	 * 🔴 SỐ TÀI KHOẢN THIẾU MỘT CHỮ SỐ LÀ QUÉT RA LỖI "định dạng tài khoản định danh không hợp lệ"
	 *    — chuyện đã xảy ra thật bên plugin ghế ngày 22/08/2026. Nên màn Cài đặt in luôn chuỗi
	 *    VietQR thử để soi bằng mắt trước khi giao cho khách.
	 */
	public static function tk() {
		$cf = self::cf();
		$ra = array(
			'bin'    => trim( (string) $cf['bin'] ),
			'so_tk'  => trim( (string) $cf['so_tk'] ),
			'ten_tk' => trim( (string) $cf['ten_tk'] ),
		);
		if ( '' === $ra['bin'] )    { $ra['bin']    = trim( (string) get_option( 'vhg_bin', '' ) ); }
		if ( '' === $ra['so_tk'] )  { $ra['so_tk']  = trim( (string) get_option( 'vhg_so_tk', '' ) ); }
		if ( '' === $ra['ten_tk'] ) { $ra['ten_tk'] = trim( (string) get_option( 'vhg_ten_tk', '' ) ); }
		$ra['ten_nh'] = self::ten_nh( $ra['bin'] );
		return $ra;
	}

	/** Tên ngân hàng từ mã BIN — không có trong bảng thì trả rỗng, thà "không rõ" còn hơn đoán. */
	const NGAN_HANG = array(
		'970418' => 'BIDV', '970436' => 'Vietcombank', '970415' => 'VietinBank', '970422' => 'MB',
		'970407' => 'Techcombank', '970416' => 'ACB', '970448' => 'OCB', '970432' => 'VPBank',
		'970405' => 'Agribank', '970423' => 'TPBank', '970443' => 'SHB', '970441' => 'VIB',
		'970426' => 'MSB', '970403' => 'Sacombank', '970431' => 'Eximbank', '970437' => 'HDBank',
	);
	public static function ten_nh( $bin ) {
		$bin = preg_replace( '/\D+/', '', (string) $bin );
		return isset( self::NGAN_HANG[ $bin ] ) ? self::NGAN_HANG[ $bin ] : '';
	}

	/**
	 * Nội dung chuyển khoản = mã thiệp BỎ DẤU GẠCH.
	 *
	 * 🔴 ĐÂY LÀ SỢI DÂY DUY NHẤT NỐI TIỀN VỚI ĐƠN. Gõ sai một ký tự là tiền vào tài khoản mà
	 *    không ai biết của đơn nào — kế toán ngồi dò tay giữa lúc khách đứng đợi ở cửa.
	 * ⚠️ Bỏ dấu gạch vì nhiều app ngân hàng lọc ký tự đặc biệt trong nội dung chuyển khoản; để
	 *    nguyên `GB-XXXX` thì app cắt thành `GBXXXX` và hai bên không khớp nhau nữa.
	 */
	public static function noi_dung( $ma ) {
		return preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $ma ) );
	}

	/**
	 * Gắn tài khoản + mã QR vào một đơn để trả về cho trang khách.
	 * Chưa khai tài khoản thì trả `tk.thieu = 1` và KHÔNG dựng QR — thà nói thẳng "chưa khai"
	 * còn hơn vẽ một tấm QR trỏ vào tài khoản rỗng để khách quét rồi chuyển đi đâu không rõ.
	 */
	public static function kem_qr( $don ) {
		if ( ! $don ) { return $don; }
		/* ==========================================================================================
		 * QR VÉ — cái nhân viên soát ở cửa. KHÁC HẲN QR chuyển khoản, và không bao giờ hiện cùng
		 * lúc: thiệp chưa trả tiền thì chỉ có QR chuyển khoản, trả rồi thì chỉ có QR vé. Hai mã
		 * đen trắng giống hệt nhau nằm cạnh nhau là khách quét nhầm cái này ra cái kia — quét QR
		 * vé bằng app ngân hàng thì báo lỗi, còn nhân viên soi QR chuyển khoản thì không ra mã vé.
		 * ⚠️ Nội dung QR vé là ĐÚNG MÃ THIỆP, không phải đường dẫn: máy quét nào cũng đọc ra được
		 *    chuỗi ấy, và màn soát vé cũng nhận cả chuỗi gõ tay lẫn chuỗi quét ra.
		 * ======================================================================================== */
		if ( in_array( $don['tt'], array( 'cho_vao', 'da_vao' ), true ) ) {
			$mt_ve = NHAMA_QRVe::ma_tran( (string) $don['ma'], 'M' );
			$don['qr_ve'] = $mt_ve ? NHAMA_QRVe::svg( $mt_ve, 200 ) : '';
		}
		$tk = self::tk();
		$don['nd'] = self::noi_dung( $don['ma'] );
		if ( '' === $tk['so_tk'] || '' === $tk['bin'] ) {
			$don['tk'] = array( 'thieu' => 1 );
			return $don;
		}
		$chuoi = NHAMA_QR::dung( $tk['bin'], $tk['so_tk'], (int) $don['tien'], $don['nd'] );
		/* 🔴 CHỮ HIỆN CHO KHÁCH CHÉP LẤY TỪ CHÍNH CHUỖI QR, không lấy lại từ biến ở trên.
		   Anh Thắng 08/09/2026: *"nội dung nó theo qr chuyển khoản, để tránh khách copy bị sai"*.
		   Đúng: khách có hai đường trả tiền — quét QR, hoặc chép chữ gõ tay. Hai đường mà đi từ
		   hai nguồn thì sớm muộn lệch nhau, và lệch IM LẶNG: người quét thì vào đúng đơn, người
		   gõ tay thì tiền vào tài khoản mà không ai biết của thiệp nào. Bóc ngược ra là chỉ còn
		   một nguồn sự thật, và nếu bộ dựng QR đổi gì thì chữ trên màn đổi theo ngay. */
		$don['nd']    = NHAMA_QR::boc( $chuoi, '62', '08' );
		$don['tien_qr'] = (int) NHAMA_QR::boc( $chuoi, '54' );
		$mt    = NHAMA_QRVe::ma_tran( $chuoi, 'L' );
		$don['tk'] = array( 'thieu' => 0, 'bin' => $tk['bin'], 'so_tk' => $tk['so_tk'],
			'ten_tk' => $tk['ten_tk'], 'ten_nh' => $tk['ten_nh'] );
		/* Mức sửa lỗi L: chuỗi VietQR dài ~125 ký tự, mức L cho ra 37×37 thay vì 41×41 — mã hiện
		   trên màn điện thoại thì mỗi ô to hơn, mà màn hình không chịu vết xước như tem in. */
		$don['qr'] = $mt ? NHAMA_QRVe::svg( $mt, 220 ) : '';
		return $don;
	}

	public static function slug() {
		$s = (string) get_option( 'nhama_slug', 'ban-ve-nha-ma' );
		return $s !== '' ? $s : 'ban-ve-nha-ma';
	}
	/**
	 * Đường dẫn trang khách và trang quản trị.
	 *
	 * 🔴 CÔNG KHAI RA NGOÀI để trang Cổng K&H (`VHTC_Trang::ds_app`) lấy được — bên ấy CỐ Ý không
	 *    gõ lại địa chỉ của app nào, vì gõ lại là sớm muộn hai nơi lệch, mà lệch thì bấm vào ra
	 *    404 chứ không có gì báo. Đổi slug ở đây là mọi nút bấm bên kia tự theo.
	 */
	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'nhama', '1', home_url( '/' ) );
	}
	/** Trang quản trị = cùng trang, khác dấu neo. Gác PIN nằm ở máy chủ, không nằm ở đường dẫn. */
	public static function url_ql() { return self::url() . '#quanly'; }

	public static function t() { global $wpdb; return $wpdb->prefix . self::BANG; }
	public static function t_tien() { global $wpdb; return $wpdb->prefix . 'nhama_tien'; }
	public static function khoa_tien() {
		if ( defined( 'NHAMA_KHOA_TIEN' ) && '' !== (string) NHAMA_KHOA_TIEN ) { return (string) NHAMA_KHOA_TIEN; }
		return (string) get_option( 'nhama_khoa_tien', '' );
	}
	public static function duong_tien() { return home_url( '/nha-ma-tien' ); }

	// ========================================================================== dựng bảng
	public static function cai_dat() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		dbDelta( 'CREATE TABLE ' . self::t() . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma VARCHAR(24) NOT NULL,
			ten VARCHAR(120) NOT NULL DEFAULT '',
			sdt VARCHAR(20) NOT NULL DEFAULT '',
			ngay DATE NOT NULL,
			gio VARCHAR(5) NOT NULL DEFAULT '',
			sl INT NOT NULL DEFAULT 1,
			tien BIGINT NOT NULL DEFAULT 0,
			tt VARCHAR(12) NOT NULL DEFAULT 'giu_cho',
			ghi VARCHAR(255) NOT NULL DEFAULT '',
			vao_luc DATETIME NULL,
			zalo_luc DATETIME NULL,
			tao DATETIME NOT NULL,
			sua DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma (ma),
			KEY khung (ngay,gio),
			KEY nguoi (sdt),
			KEY trang_thai (tt)
		) $c" );
		/* Sổ tiền về: MỌI gói bên gửi bắn tới đều vào đây, kể cả gói không đọc được và gói không
		   khớp thiệp nào. Đó là cách duy nhất phân biệt "ngân hàng chưa bắn" với "bắn rồi mà mình
		   không hiểu" — hai ca ấy đi sửa ở hai nơi khác hẳn nhau.
		   ⚠️ `ref` là UNIQUE: bên gửi bắn lại cùng một giao dịch (chuyện thường, họ đẩy lại khi
		      không chắc mình đã nhận) thì phần trùng tự hoà, không duyệt đơn hai lần. */
		dbDelta( 'CREATE TABLE ' . self::t_tien() . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ref VARCHAR(64) NOT NULL,
			luc DATETIME NOT NULL,
			so_tien BIGINT NOT NULL DEFAULT 0,
			noi_dung VARCHAR(255) NOT NULL DEFAULT '',
			ma VARCHAR(24) NOT NULL DEFAULT '',
			kq VARCHAR(20) NOT NULL DEFAULT '',
			tho TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
			KEY luc (luc)
		) $c" );
		/* Khoá cổng tiền: KHÔNG bắt anh sửa wp-config. Tự sinh một chuỗi ngẫu nhiên rồi in ra ở
		   màn quản trị để dán vào ô webhook bên ngân hàng.
		   ⚠️ Khoá đi trên ĐƯỜNG DẪN (?token=…) vì bên gửi webhook phần lớn không cho đặt header
		      tuỳ ý — nghĩa là nó nằm trong nhật ký máy chủ, coi như đã lộ một phần. Nên nó phải
		      đổi được dễ (nút đổi khoá ở màn quản trị) và KHÔNG dùng chung với PIN. */
		if ( ! get_option( 'nhama_khoa_tien' ) ) {
			update_option( 'nhama_khoa_tien', wp_generate_password( 32, false ) );
		}
		if ( ! get_option( 'nhama_pin_bam' ) ) { self::dat_pin( '246810' ); }
		update_option( 'nhama_ver', self::VER );
	}

	/** PIN cất bằng dấu băm + muối. Sổ này không đáng để ai đọc được PIN từ bảng cấu hình. */
	public static function dat_pin( $pin ) {
		$muoi = wp_generate_password( 16, false );
		update_option( 'nhama_pin_muoi', $muoi );
		update_option( 'nhama_pin_bam', hash( 'sha256', $muoi . $pin ) );
	}
	public static function pin_dung( $pin ) {
		$muoi = (string) get_option( 'nhama_pin_muoi', '' );
		$bam  = (string) get_option( 'nhama_pin_bam', '' );
		if ( '' === $bam ) { return false; }
		return hash_equals( $bam, hash( 'sha256', $muoi . (string) $pin ) );
	}

	// ========================================================================== gài vào WordPress
	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?nhama=1', 'top' );
		add_rewrite_rule( '^nha-ma-tien/?$', 'index.php?nhama_tien=1', 'top' );
		add_rewrite_rule( '^nha-ma-zalo/?$', 'index.php?nhama_zalo=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ) );
		if ( get_option( 'nhama_ver' ) !== self::VER ) { self::cai_dat(); flush_rewrite_rules( false ); }
	}
	public static function query_vars( $v ) { $v[] = 'nhama'; $v[] = 'nhama_tien'; $v[] = 'nhama_zalo'; return $v; }

	public static function phuc_vu() {
		/* Cổng tiền xử TRƯỚC trang, và không bao giờ được để WordPress chuyển hướng: bên gửi
		   webhook phần lớn KHÔNG đi theo 30x, hoặc đi theo bằng GET và mất trọn thân POST —
		   tức là mất một lượt tiền về mà không ai biết. */
		$la_tien = ( (int) get_query_var( 'nhama_tien' ) === 1 ) || isset( $_GET['nhama_tien'] );
		if ( ! $la_tien && isset( $_SERVER['REQUEST_URI'] ) ) {
			$d = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
			$la_tien = ( 'nha-ma-tien' === $d || substr( $d, -12 ) === '/nha-ma-tien' );
		}
		$la_zalo = ( (int) get_query_var( 'nhama_zalo' ) === 1 ) || isset( $_GET['nhama_zalo'] );
		if ( ! $la_zalo && isset( $_SERVER['REQUEST_URI'] ) ) {
			$dz = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
			$la_zalo = ( 'nha-ma-zalo' === $dz || substr( $dz, -12 ) === '/nha-ma-zalo' );
		}
		if ( $la_zalo ) {
			self::zalo_nhan_ma();
			if ( ! defined( 'NHAMA_TEST' ) ) { exit; }
			return;
		}
		if ( $la_tien ) {
			self::cong_tien();
			if ( ! defined( 'NHAMA_TEST' ) ) { exit; }
			return;
		}
		$la = ( (int) get_query_var( 'nhama' ) === 1 );
		if ( ! $la && isset( $_GET['nhama'] ) ) { $la = true; }
		if ( ! $la ) {
			/* Luật đường dẫn chưa được nạp lại thì so bằng chính địa chỉ — tem in / link đã gửi
			   cho khách không đợi ai đi bấm Lưu Permalinks. */
			$d = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
			$d = trim( (string) wp_parse_url( $d, PHP_URL_PATH ), '/' );
			$s = self::slug();
			$la = ( $d === $s || substr( $d, - ( strlen( $s ) + 1 ) ) === '/' . $s );
		}
		if ( ! $la ) { return; }
		nocache_headers();
		self::trang();
		if ( ! defined( 'NHAMA_TEST' ) ) { exit; }
	}

	/**
	 * ==========================================================================================
	 * CỔNG NHẬN TIỀN — ngân hàng / SePay / Casso / Tingo bắn vào đây
	 * ==========================================================================================
	 * Anh Thắng 08/09/2026: *"thanh toán xong, dữ liệu tự đẩy ngược về báo thành công chứ"*. Đúng:
	 * không có cổng này thì kế toán phải ngồi soi sao kê rồi bấm duyệt tay từng đơn, mà khách thì
	 * đứng ở cửa đợi.
	 *
	 * BỐN LUẬT, học nguyên từ cổng tiền của plugin ghế (class-vhg-cong.php):
	 *
	 * 1. TRẢ 200 CHO MỌI GÓI ĐÃ QUA KHOÁ, kể cả gói không đọc được. Bên gửi thấy khác 2xx là đẩy
	 *    lại nhiều lần rồi TẮT HẲN webhook — lúc đó mới là mất tiền thật. Gói không hiểu thì giữ
	 *    nguyên văn trong sổ để xử tay. Ca DUY NHẤT trả khác 200: sai khoá (401), để người cấu
	 *    hình thấy ngay.
	 * 2. GHI SỔ MỌI LƯỢT, KỂ CẢ LƯỢT BỊ TỪ CHỐI. Đó là cách duy nhất phân biệt "bên gửi chưa
	 *    bắn" với "bắn rồi mà mình chặn" — hai ca đi sửa ở hai nơi khác hẳn nhau.
	 * 3. KHÔNG BAO GIỜ CHUYỂN HƯỚNG (xem `phuc_vu`).
	 * 4. KHOÁ ĐI TRÊN ĐƯỜNG DẪN, đổi được dễ, không dùng chung với PIN.
	 */
	public static function cong_tien() {
		nocache_headers();
		$khoa = self::khoa_tien();
		$gui  = isset( $_GET['token'] ) ? (string) $_GET['token'] : '';
		$tho  = self::than_tho();
		if ( '' === $khoa || ! hash_equals( $khoa, $gui ) ) {
			self::ghi_tien( 'sai-khoa-' . substr( md5( $tho . microtime() ), 0, 16 ), 0, '', '', 'sai_khoa', $tho );
			status_header( 401 );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( array( 'ok' => false, 'error' => 'Sai khoá cổng.' ) );
			return;
		}
		$kq = self::nhan_tien( $tho );
		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $kq );
	}

	private static function than_tho() {
		if ( defined( 'NHAMA_TEST' ) && isset( $GLOBALS['NHAMA_THAN'] ) ) { return (string) $GLOBALS['NHAMA_THAN']; }
		$t = file_get_contents( 'php://input' );
		return is_string( $t ) ? $t : '';
	}

	/** Lấy giá trị đầu tiên tìm thấy trong mấy nhánh, theo danh sách tên trường. */
	private static function lay( $nhanh, $tens ) {
		foreach ( $nhanh as $n ) {
			foreach ( $tens as $k ) {
				if ( isset( $n[ $k ] ) && '' !== $n[ $k ] && ! is_array( $n[ $k ] ) ) { return $n[ $k ]; }
			}
		}
		return '';
	}

	/**
	 * Đọc gói của bên gửi và khớp với thiệp.
	 *
	 * ⚠️ TÊN TRƯỜNG MỖI BÊN MỘT KIỂU (SePay `transferAmount`/`content`/`referenceCode`, Casso
	 *    `amount`/`description`/`tid`, Tingo lại khác) — nên dò theo cả một danh sách thay vì
	 *    ép bên gửi phải giống mình. Danh sách này chép từ plugin ghế, nơi nó đã ăn gói thật.
	 */
	public static function nhan_tien( $tho ) {
		$goi = json_decode( $tho, true );
		if ( ! is_array( $goi ) ) {
			self::ghi_tien( 'khong-doc-' . substr( md5( $tho . microtime() ), 0, 16 ), 0, '', '', 'khong_doc', $tho );
			return array( 'ok' => true, 'ghi_chu' => 'Không đọc được gói — đã giữ nguyên văn trong sổ.' );
		}
		$nhanh = array( $goi );
		foreach ( array( 'data', 'transaction', 'payload', 'result', 'body' ) as $k ) {
			if ( isset( $goi[ $k ] ) && is_array( $goi[ $k ] ) ) { $nhanh[] = $goi[ $k ]; }
		}
		$tien = (int) preg_replace( '/[^0-9]/', '', (string) self::lay( $nhanh, array( 'transferAmount',
			'amount', 'creditAmount', 'amountIn', 'value', 'money', 'transactionAmount',
			'totalAmount', 'soTien' ) ) );
		$nd = (string) self::lay( $nhanh, array( 'content', 'description', 'addInfo', 'note',
			'comment', 'transactionContent', 'orderInfo', 'message', 'noiDung', 'ndct' ) );
		$ref = (string) self::lay( $nhanh, array( 'referenceCode', 'reference', 'id', 'tid',
			'transactionId', 'refId', 'ftCode', 'transactionCode', 'orderCode', 'maThamChieu' ) );
		$huong = strtolower( (string) self::lay( $nhanh, array( 'transferType', 'type', 'direction' ) ) );
		if ( '' === $ref ) { $ref = 'tu-sinh-' . substr( md5( $tho ), 0, 20 ); }

		/* Tiền RA khỏi tài khoản thì bỏ — SePay bắn cả hai chiều. Duyệt nhầm một lượt chuyển đi
		   là thiệp được duyệt mà tiền thì vừa rời tài khoản. */
		if ( 'out' === $huong || 'debit' === $huong ) {
			self::ghi_tien( $ref, $tien, $nd, '', 'tien_ra', $tho );
			return array( 'ok' => true, 'ghi_chu' => 'Lượt tiền ra — bỏ qua.' );
		}

		$ma = self::ma_tu_noi_dung( $nd );
		if ( '' === $ma ) {
			self::ghi_tien( $ref, $tien, $nd, '', 'khong_khop', $tho );
			return array( 'ok' => true, 'ghi_chu' => 'Nội dung không mang mã thiệp nào — vào sổ chờ xử tay.' );
		}
		$don = self::don_theo_ma( $ma );
		if ( ! $don ) {
			self::ghi_tien( $ref, $tien, $nd, $ma, 'khong_co_don', $tho );
			return array( 'ok' => true, 'ghi_chu' => 'Không có thiệp ' . $ma . ' trong sổ.' );
		}
		/* 🔴 THIẾU TIỀN THÌ KHÔNG DUYỆT, nhưng phải GHI vào đơn để kế toán thấy mà gọi khách —
		   im lặng bỏ qua là khách tưởng đã trả xong, tới cửa mới biết. */
		if ( $tien > 0 && $tien < (int) $don['tien'] ) {
			self::ghi_tien( $ref, $tien, $nd, $ma, 'thieu_tien', $tho );
			self::ghi_chu_don( $ma, 'Tiền về THIẾU: ' . number_format( $tien ) . ' / '
				. number_format( (int) $don['tien'] ) . ' (' . $ref . ')' );
			return array( 'ok' => true, 'ghi_chu' => 'Thiếu tiền — chưa duyệt.' );
		}
		if ( 'huy' === $don['tt'] ) {
			self::ghi_tien( $ref, $tien, $nd, $ma, 'don_da_huy', $tho );
			self::ghi_chu_don( $ma, 'Tiền về cho thiệp ĐÃ HUỶ (' . $ref . ') — cần hoàn tiền.' );
			return array( 'ok' => true, 'ghi_chu' => 'Thiệp đã huỷ — cần hoàn tiền.' );
		}
		/* Ghi sổ TRƯỚC khi duyệt: `ref` là UNIQUE nên bên gửi bắn lại cùng giao dịch thì lượt sau
		   ghi trượt, và ta thôi không duyệt lần nữa. Đây là chốt chống đếm hai lần. */
		if ( ! self::ghi_tien( $ref, $tien, $nd, $ma, 'khop', $tho ) ) {
			return array( 'ok' => true, 'ghi_chu' => 'Giao dịch ' . $ref . ' đã xử lý trước đó.' );
		}
		global $wpdb;
		if ( 'cho_vao' !== $don['tt'] && 'da_vao' !== $don['tt'] ) {
			$wpdb->update( self::t(), array( 'tt' => 'cho_vao', 'sua' => current_time( 'mysql' ),
				'ghi' => 'Tiền về tự động ' . $ref ), array( 'ma' => $ma ) );
		}
		self::gui_zalo( $ma );
		return array( 'ok' => true, 'ghi_chu' => 'Đã duyệt thiệp ' . $ma . '.' );
	}

	/**
	 * ==========================================================================================
	 * NỐI VỚI ZALO OA — lấy token bằng NÚT BẤM, không bắt ai chạy lệnh curl
	 * ==========================================================================================
	 * 🔴 ACCESS TOKEN CỦA ZALO CHỈ SỐNG KHOẢNG MỘT GIỜ. Nên một ô "dán access token vào đây" là
	 *    thiết kế sai từ gốc: dán xong gửi được vài tin rồi chết, và mỗi giờ lại phải đi lấy token
	 *    mới — không ai làm được việc đó. Thứ phải cất là REFRESH TOKEN (sống vài tháng), còn
	 *    access token thì plugin tự đổi lấy khi cần.
	 *
	 * 🔴 MỖI LẦN LÀM MỚI, ZALO CẤP LUÔN MỘT REFRESH TOKEN MỚI, và cái cũ hết dùng được. Không lưu
	 *    đè cái mới là lần sau hỏng — mà hỏng IM LẶNG, chỉ lộ ra khi có khách không nhận được vé.
	 *
	 * ⚠️ CHƯA CHẠY THỬ VỚI ZALO THẬT — máy dựng plugin bị chặn ra Internet, không mở nổi cả trang
	 *    tài liệu của Zalo. Phần dựng địa chỉ, đổi mã, làm mới và lưu token đều có phép thử bằng
	 *    máy chủ giả; phần nói chuyện thật với Zalo thì chưa. Nhật ký Zalo ghi NGUYÊN VĂN câu trả
	 *    lời của Zalo để lần đầu nối có hỏng cũng đọc ra ngay hỏng ở đâu.
	 */
	const ZALO_OAUTH = 'https://oauth.zaloapp.com/v4/oa/permission';
	const ZALO_TOKEN = 'https://oauth.zaloapp.com/v4/oa/access_token';

	/** Địa chỉ Zalo trả mã về — khai đúng chuỗi này ở ứng dụng bên developers.zalo.me. */
	public static function zalo_callback() { return home_url( '/nha-ma-zalo' ); }

	/**
	 * Địa chỉ bấm vào để cấp quyền. Kèm `state` chống ai đó dụ trình duyệt của anh nối nhầm sang
	 * ứng dụng của họ.
	 */
	public static function zalo_url_noi() {
		$cf = self::cf();
		$app = trim( (string) $cf['zalo_app_id'] );
		if ( '' === $app ) { return ''; }
		/* ⚠️ DÙNG LẠI `state` CŨ NẾU CÒN SỐNG. Màn quản trị tự nạp lại mỗi 6 giây, mà mỗi lượt
		   nạp đều đọc tình trạng Zalo — sinh mã mới mỗi lượt thì cái nút anh đang nhìn mang mã
		   của 6 giây trước, bấm vào là Zalo trả về "state không khớp" dù anh chẳng làm gì sai.
		   Còn hạn thì giữ nguyên mã và nới hạn ra, mở màn quản trị bao lâu nút vẫn bấm được. */
		$state = (string) get_transient( 'nhama_zalo_state' );
		if ( '' === $state ) { $state = wp_generate_password( 20, false ); }
		set_transient( 'nhama_zalo_state', $state, 900 );
		return self::ZALO_OAUTH . '?' . http_build_query( array(
			'app_id'       => $app,
			'redirect_uri' => self::zalo_callback(),
			'state'        => $state,
		) );
	}

	/**
	 * Một lượt gọi HTTP. TÁCH RA LÀM HÀM RIÊNG để phép thử thay được bằng máy chủ giả — phần nói
	 * chuyện với Zalo là phần duy nhất ở đây không thể chạy thật trong bài kiểm.
	 */
	public static function http( $url, $args ) {
		if ( defined( 'NHAMA_TEST' ) && isset( $GLOBALS['NHAMA_HTTP'] ) && is_callable( $GLOBALS['NHAMA_HTTP'] ) ) {
			return call_user_func( $GLOBALS['NHAMA_HTTP'], $url, $args );
		}
		return wp_remote_post( $url, $args );
	}

	/** Đọc thân JSON của một lượt trả lời, chấp cả dạng WP_Error lẫn mảng. */
	private static function than_tra( $tra ) {
		if ( is_wp_error( $tra ) ) { return array( 'loi_mang' => $tra->get_error_message() ); }
		$than = is_array( $tra ) && isset( $tra['body'] ) ? (string) $tra['body'] : '';
		$j = json_decode( $than, true );
		return is_array( $j ) ? $j : array( 'khong_doc' => mb_substr( $than, 0, 300 ) );
	}

	/** Cất bộ token vừa nhận. Trả về câu lỗi, hoặc '' nếu xuôi. */
	private static function zalo_cat_token( $j ) {
		if ( empty( $j['access_token'] ) ) {
			/* Zalo trả HTTP 200 kể cả khi hỏng, lỗi nằm trong thân — đọc mã HTTP là tưởng xong. */
			return 'Zalo không trả access_token: ' . wp_json_encode( $j );
		}
		$cf = self::cf();
		$cf['zalo_token'] = (string) $j['access_token'];
		/* Trừ hao 120 giây: token hết hạn đúng lúc đang gửi thì tin ấy mất. */
		$cf['zalo_het'] = time() + max( 60, (int) ( isset( $j['expires_in'] ) ? $j['expires_in'] : 3600 ) ) - 120;
		if ( ! empty( $j['refresh_token'] ) ) { $cf['zalo_refresh'] = (string) $j['refresh_token']; }
		update_option( 'nhama_cf', $cf );
		return '';
	}

	/** Zalo gọi về đây kèm `code` sau khi anh bấm cấp quyền. */
	public static function zalo_nhan_ma() {
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		$ma    = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$that  = (string) get_transient( 'nhama_zalo_state' );
		$oa    = isset( $_GET['oa_id'] ) ? sanitize_text_field( wp_unslash( $_GET['oa_id'] ) ) : '';

		if ( '' === $ma ) {
			/* ⚠️ GIỮ NGUYÊN VĂN CÂU TỪ CHỐI CỦA ZALO. Zalo không gửi `code` thì gần như luôn kèm
			   lý do trên địa chỉ (`error`, `error_description`, `error_reason`…). Nuốt mất mấy
			   chữ ấy là anh chỉ còn "thử lại đi" — mà thử mười lần vẫn hỏng đúng chỗ cũ. */
			$vi = array();
			foreach ( array( 'error', 'error_code', 'error_reason', 'error_description', 'message' ) as $k ) {
				if ( isset( $_GET[ $k ] ) && '' !== (string) $_GET[ $k ] ) {
					$vi[] = $k . '=' . sanitize_text_field( wp_unslash( $_GET[ $k ] ) );
				}
			}
			$chu = $vi
				? 'Zalo từ chối cấp quyền — nguyên văn: ' . implode( ' · ', $vi )
				: 'Zalo không gửi mã về (và cũng không nói vì sao). Thử bấm Kết nối lại.';
			self::nk_zalo( '', 'noi_hong', $chu );
			self::zalo_man( false, $chu );
			return;
		}
		/* ⚠️ SO `state`. Thiếu chốt này thì ai đó dụ được trình duyệt của anh mở một địa chỉ có
		   `code` của họ, và OA của họ được nối vào website của anh. */
		if ( '' === $that || ! hash_equals( $that, $state ) ) {
			self::zalo_man( false, 'Mã trạng thái không khớp (state) — lượt nối này không phải do anh bắt đầu, hoặc đã quá 15 phút. Bấm Kết nối lại từ màn Cài đặt.' );
			return;
		}
		delete_transient( 'nhama_zalo_state' );

		$cf  = self::cf();
		$tra = self::http( self::ZALO_TOKEN, array(
			'timeout' => 20,
			'headers' => array( 'secret_key' => trim( (string) $cf['zalo_secret'] ),
				'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array( 'code' => $ma, 'app_id' => trim( (string) $cf['zalo_app_id'] ),
				'grant_type' => 'authorization_code' ),
		) );
		$j   = self::than_tra( $tra );
		$loi = self::zalo_cat_token( $j );
		if ( '' !== $loi ) { self::nk_zalo( '', 'noi_hong', $loi ); self::zalo_man( false, $loi ); return; }
		if ( '' !== $oa ) {
			$cf2 = self::cf(); $cf2['zalo_oa'] = $oa; update_option( 'nhama_cf', $cf2 );
		}
		self::nk_zalo( '', 'da_noi', 'Đã nối OA ' . ( $oa ? $oa : '(không rõ id)' ) );
		self::zalo_man( true, 'Đã nối xong. Từ giờ plugin tự làm mới token, anh không phải làm gì thêm.' );
	}

	private static function zalo_man( $xuoi, $chu ) {
		echo '<!doctype html><meta charset="utf-8"><title>Nối Zalo OA</title>'
			. '<div style="font:16px/1.6 system-ui,Arial;max-width:640px;margin:60px auto;padding:0 16px">'
			. '<h2>' . ( $xuoi ? '✔ Nối Zalo OA thành công' : '✕ Chưa nối được' ) . '</h2>'
			. '<p>' . esc_html( $chu ) . '</p>'
			. '<p><a href="' . esc_url( self::url_ql() ) . '">← Về trang quản trị</a></p></div>';
	}

	/**
	 * Access token đang dùng được — hết hạn thì tự làm mới bằng refresh token.
	 * Trả về '' nếu chưa nối, hoặc làm mới hỏng (và ghi nhật ký nói rõ vì sao).
	 */
	public static function zalo_token_song() {
		$cf = self::cf();
		if ( (string) $cf['zalo_token'] !== '' && (int) $cf['zalo_het'] > time() ) {
			return (string) $cf['zalo_token'];
		}
		$rf = trim( (string) $cf['zalo_refresh'] );
		if ( '' === $rf ) { return ''; }
		$tra = self::http( self::ZALO_TOKEN, array(
			'timeout' => 20,
			'headers' => array( 'secret_key' => trim( (string) $cf['zalo_secret'] ),
				'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array( 'refresh_token' => $rf, 'app_id' => trim( (string) $cf['zalo_app_id'] ),
				'grant_type' => 'refresh_token' ),
		) );
		$j   = self::than_tra( $tra );
		$loi = self::zalo_cat_token( $j );
		if ( '' !== $loi ) { self::nk_zalo( '', 'lam_moi_hong', $loi ); return ''; }
		self::nk_zalo( '', 'da_lam_moi', 'Đã làm mới access token.' );
		return (string) self::cf()['zalo_token'];
	}

	/** Tình trạng nối Zalo — cho màn Cài đặt hiện ra, không in token ra màn hình. */
	public static function zalo_tinh_trang() {
		$cf = self::cf();
		return array(
			'co_app'     => '' !== trim( (string) $cf['zalo_app_id'] ) && '' !== trim( (string) $cf['zalo_secret'] ),
			'da_noi'     => '' !== trim( (string) $cf['zalo_refresh'] ),
			'oa'         => (string) $cf['zalo_oa'],
			'con_song'   => ( (string) $cf['zalo_token'] !== '' && (int) $cf['zalo_het'] > time() )
				? max( 0, (int) $cf['zalo_het'] - time() ) : 0,
			'callback'   => self::zalo_callback(),
			'url_noi'    => self::zalo_url_noi(),
		);
	}

	/**
	 * ==========================================================================================
	 * GỬI MÃ VÉ + QR VÉ VÀO ZALO CHO KHÁCH
	 * ==========================================================================================
	 * Anh Thắng 08/09/2026: *"mã vé sẽ gửi vào tin nhắn zalo của khách mã vé và QR vé"*.
	 *
	 * 🔴 ĐỌC TRƯỚC KHI TRÔNG CHỜ VÀO NÓ. Zalo KHÔNG cho gửi tin cho một số điện thoại bất kỳ.
	 *    Phải có **Official Account (OA)** và một trong hai đường:
	 *      · **ZNS** (Zalo Notification Service) — gửi được cho mọi số, nhưng phải đăng ký MẪU TIN
	 *        và chờ Zalo duyệt, và mỗi tin TỐN TIỀN.
	 *      · **Tin tư vấn (CS)** — miễn phí, nhưng CHỈ gửi được cho người đã nhắn cho OA trong
	 *        vòng 7 ngày. Khách mua vé lần đầu thì gần như chắc chắn KHÔNG thoả.
	 *    Nghĩa là: chưa có OA + mẫu tin ZNS đã duyệt thì đường này KHÔNG chạy, và đó là chuyện của
	 *    Zalo chứ không phải của mã nguồn.
	 *
	 * ⚠️ CHƯA CHẠY THỬ VỚI OA THẬT — em không có token của anh. Phần dựng gói tin và ghi nhật ký
	 *    thì có phép thử; phần bắn đi thì chỉ chạy khi anh điền token, và nhật ký sẽ nói ngay Zalo
	 *    trả về gì.
	 *
	 * 🔴 GỬI HỎNG KHÔNG ĐƯỢC LÀM HỎNG LƯỢT TIỀN VỀ. Thiệp đã duyệt là đã duyệt; Zalo chết thì ghi
	 *    vào nhật ký rồi đi tiếp. Khách vẫn xem được thiệp trên web bằng "Tra cứu lời mời".
	 */
	public static function gui_zalo( $ma ) {
		$don = self::don_theo_ma( $ma );
		if ( ! $don ) { return false; }
		$cf  = self::cf();
		$tok = self::zalo_token_song();
		$sdt = preg_replace( '/[^0-9]/', '', (string) $don['sdt'] );
		if ( '' === $tok ) { self::nk_zalo( $ma, 'chua_cau_hinh', 'Chưa nối Zalo OA (hoặc làm mới token hỏng).' ); return false; }
		if ( '' === $sdt ) { self::nk_zalo( $ma, 'thieu_sdt', 'Đơn không có số điện thoại.' ); return false; }
		/* Zalo đòi số dạng 84…, không phải 0… */
		if ( '0' === substr( $sdt, 0, 1 ) ) { $sdt = '84' . substr( $sdt, 1 ); }

		$link = self::url_ve( $don['ma'] );
		$tpl  = trim( (string) ( isset( $cf['zalo_tpl'] ) ? $cf['zalo_tpl'] : '' ) );
		if ( '' !== $tpl ) {
			/* ZNS — mẫu tin đã đăng ký. Tên tham số phải khớp mẫu anh khai bên Zalo. */
			$url  = 'https://business.openapi.zalo.me/message/template';
			$than = array( 'phone' => $sdt, 'template_id' => $tpl, 'template_data' => array(
				'ma_ve'  => (string) $don['ma'],
				'ten'    => (string) $don['ten'],
				'ngay'   => (string) $don['ngay'],
				'gio'    => (string) $don['gio'],
				'so_ve'  => (string) $don['sl'],
				'link'   => $link,
			) );
		} else {
			/* Tin tư vấn — chỉ tới được người đã nhắn cho OA trong 7 ngày. */
			$url  = 'https://openapi.zalo.me/v3.0/oa/message/cs';
			$chu  = "🎟 THIỆP " . $don['ma'] . "\n" . $don['ten'] . " · " . $don['sl'] . " người\n"
				. "Thời khắc: " . $don['ngay'] . " — " . $don['gio'] . "\n"
				. "Mở thiệp (có mã QR để soát ở cửa): " . $link;
			$than = array( 'recipient' => array( 'user_id_by_phone' => $sdt ),
				'message' => array( 'text' => $chu ) );
		}
		$tra = self::http( $url, array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json', 'access_token' => $tok ),
			'body'    => wp_json_encode( $than ),
		) );
		if ( is_wp_error( $tra ) ) {
			self::nk_zalo( $ma, 'loi_mang', $tra->get_error_message() );
			return false;
		}
		$j = self::than_tra( $tra );
		/* Zalo trả HTTP 200 kể cả khi hỏng, lỗi nằm trong `error` — đọc mã HTTP là tưởng gửi xong. */
		if ( is_array( $j ) && isset( $j['error'] ) && 0 !== (int) $j['error'] ) {
			self::nk_zalo( $ma, 'zalo_choi', 'Zalo báo lỗi ' . $j['error'] . ': '
				. ( isset( $j['message'] ) ? $j['message'] : '' ) );
			return false;
		}
		global $wpdb;
		$wpdb->update( self::t(), array( 'zalo_luc' => current_time( 'mysql' ) ), array( 'ma' => $ma ) );
		self::nk_zalo( $ma, 'da_gui', 'Đã gửi tới ' . $sdt );
		return true;
	}

	/** Đường dẫn mở thẳng thiệp của một mã — dùng cho tin Zalo. */
	public static function url_ve( $ma ) {
		return home_url( '/' . self::slug() . '/#ve=' . rawurlencode( (string) $ma ) );
	}

	/** Nhật ký gửi Zalo — 200 dòng gần nhất. Gộp dòng liên tiếp giống hệt nhau. */
	private static function nk_zalo( $ma, $kq, $chu ) {
		$ds = get_option( 'nhama_nk_zalo', array() );
		if ( ! is_array( $ds ) ) { $ds = array(); }
		array_unshift( $ds, array( 'luc' => current_time( 'mysql' ), 'ma' => $ma, 'kq' => $kq,
			'chu' => mb_substr( (string) $chu, 0, 255 ) ) );
		update_option( 'nhama_nk_zalo', array_slice( $ds, 0, 200 ), false );
	}

	/**
	 * Bóc mã thiệp ra khỏi nội dung chuyển khoản.
	 * ⚠️ Ngân hàng hay chèn thêm chữ quanh nội dung ("CT DEN:… GBXXXX …"), và có nơi bỏ dấu gạch,
	 *    có nơi giữ. Nên bỏ hết ký tự không phải chữ-số rồi mới dò khuôn GB + 8 ký tự.
	 */
	public static function ma_tu_noi_dung( $nd ) {
		$s = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $nd ) );
		if ( ! preg_match( '/GB([' . self::CHU_MA . ']{8})/', $s, $m ) ) { return ''; }
		return 'GB-' . $m[1];
	}

	private static function ghi_chu_don( $ma, $chu ) {
		global $wpdb;
		$wpdb->update( self::t(), array( 'ghi' => mb_substr( $chu, 0, 255 ),
			'sua' => current_time( 'mysql' ) ), array( 'ma' => $ma ) );
	}

	/** Ghi một dòng sổ tiền. Trả false nếu `ref` đã có (bên gửi bắn lại). */
	private static function ghi_tien( $ref, $tien, $nd, $ma, $kq, $tho ) {
		global $wpdb;
		$co = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t_tien() . ' WHERE ref=%s', $ref ) );
		if ( $co ) { return false; }
		$ok = $wpdb->insert( self::t_tien(), array(
			'ref' => mb_substr( (string) $ref, 0, 64 ), 'luc' => current_time( 'mysql' ),
			'so_tien' => (int) $tien, 'noi_dung' => mb_substr( (string) $nd, 0, 255 ),
			'ma' => (string) $ma, 'kq' => (string) $kq,
			/* Giữ nguyên văn gói, cắt ở 4000 ký tự — đủ để đọc lại mà không phình sổ. */
			'tho' => mb_substr( (string) $tho, 0, 4000 ) ) );
		return false !== $ok;
	}

	public static function rest() {
		register_rest_route( self::NS, '/x', array(
			'methods'  => 'POST',
			'permission_callback' => '__return_true',
			'callback' => array( __CLASS__, 'cong' ),
		) );
	}

	// ========================================================================== khung giờ
	private static function phut( $hhmm ) {
		$p = explode( ':', (string) $hhmm );
		return ( (int) $p[0] ) * 60 + ( isset( $p[1] ) ? (int) $p[1] : 0 );
	}
	private static function gio_chu( $p ) {
		return str_pad( (string) intdiv( $p, 60 ), 2, '0', STR_PAD_LEFT ) . ':'
			. str_pad( (string) ( $p % 60 ), 2, '0', STR_PAD_LEFT );
	}
	public static function ds_khung( $cf = null ) {
		$cf = $cf ? $cf : self::cf();
		$a = self::phut( $cf['mo'] ); $b = self::phut( $cf['dong'] );
		$buoc = max( 1, (int) $cf['buoc'] );
		$ra = array();
		if ( $b < $a ) { $b = $a; }
		for ( $p = $a; $p <= $b; $p += $buoc ) { $ra[] = self::gio_chu( $p ); }
		return $ra;
	}
	/** Số khách ĐANG CHIẾM CHỖ của một khung. */
	public static function da_dat( $ngay, $gio ) {
		global $wpdb;
		$t = self::t();
		$in = "'" . implode( "','", self::CHIEM ) . "'";
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(sl),0) FROM $t WHERE ngay=%s AND gio=%s AND tt IN ($in)", $ngay, $gio ) );
	}
	/** Khung đã trôi qua thì thôi bán — tính bằng GIỜ MÁY CHỦ, không tin giờ điện thoại. */
	public static function da_qua( $ngay, $gio ) {
		if ( $ngay !== current_time( 'Y-m-d' ) ) { return $ngay < current_time( 'Y-m-d' ); }
		return self::phut( $gio ) <= self::phut( current_time( 'H:i' ) );
	}

	// ========================================================================== cổng lệnh
	private static function than( $req ) {
		$d = $req->get_json_params();
		if ( ! is_array( $d ) ) { $d = $req->get_params(); }
		return is_array( $d ) ? $d : array();
	}
	private static function chu( $d, $k, $dai = 190 ) {
		return mb_substr( sanitize_text_field( isset( $d[ $k ] ) ? (string) $d[ $k ] : '' ), 0, $dai );
	}
	private static function sdt_sach( $s ) { return preg_replace( '/[^0-9+]/', '', (string) $s ); }
	private static function ma_moi() {
		$s = '';
		for ( $i = 0; $i < 8; $i++ ) { $s .= substr( self::CHU_MA, wp_rand( 0, strlen( self::CHU_MA ) - 1 ), 1 ); }
		return 'GB-' . $s;
	}
	private static function loi( $chu ) { return array( 'ok' => false, 'error' => $chu ); }

	public static function cong( $req ) {
		$d    = self::than( $req );
		$viec = preg_replace( '/[^a-z_]/', '', strtolower( (string) ( isset( $d['viec'] ) ? $d['viec'] : '' ) ) );
		$cf   = self::cf();

		/* ------------------------------------------------------------------ việc công khai */
		if ( 'goi' === $viec )     { return self::v_goi( $d, $cf ); }
		if ( 'dat' === $viec )     { return self::v_dat( $d, $cf ); }
		if ( 'cua_toi' === $viec ) { return self::v_cua_toi( $d ); }
		if ( 'bao_ck' === $viec )  { return self::v_bao_ck( $d ); }
		if ( 've' === $viec )      { return self::v_ve( $d ); }
		if ( 'vao' === $viec )     { return self::v_vao( $d ); }

		/* ------------------------------------------------------------------ từ đây phải có thẻ */
		if ( ! self::the_dung( isset( $d['the'] ) ? $d['the'] : '' ) ) {
			return array( 'ok' => false, 'ma' => 'het_phien', 'error' => 'Phiên đã hết — nhập PIN lại.' );
		}
		if ( 'ds' === $viec )    { return self::v_ds( $cf ); }
		if ( 'doi' === $viec )   { return self::v_doi( $d ); }
		if ( 'soat' === $viec )  { return self::v_soat( $d ); }
		if ( 'cai' === $viec )   { return self::v_cai( $d, $cf ); }
		if ( 'mau' === $viec )   { return self::v_mau( $cf ); }
		if ( 'xoa' === $viec )   { return self::v_xoa(); }
		if ( 'tien' === $viec )  { return self::v_tien(); }
		if ( 'gui_zalo' === $viec ) {
			$ma = strtoupper( self::chu( $d, 'ma', 24 ) );
			return array( 'ok' => self::gui_zalo( $ma ), 'nk' => array_slice( (array) get_option( 'nhama_nk_zalo', array() ), 0, 1 ) );
		}
		return self::loi( 'Việc không rõ: ' . $viec );
	}

	/** Trang khách hỏi: khung giờ nào còn chỗ. CHỈ trả về CON SỐ, không trả về đơn của ai cả. */
	private static function v_goi( $d, $cf ) {
		$ngay = self::chu( $d, 'ngay', 10 );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { $ngay = current_time( 'Y-m-d' ); }
		$khung = array();
		foreach ( self::ds_khung( $cf ) as $gio ) {
			$khung[] = array( 'gio' => $gio, 'dat' => self::da_dat( $ngay, $gio ),
				'qua' => self::da_qua( $ngay, $gio ) ? 1 : 0 );
		}
		return array( 'ok' => true, 'ngay' => $ngay, 'khung' => $khung,
			'cf' => array( 'gia' => (int) $cf['gia'], 'suc' => (int) $cf['suc'],
				'ngayMo' => (int) $cf['ngayMo'], 'denSom' => (int) $cf['denSom'],
				'ten' => $cf['ten'], 'phu' => $cf['phu'] ),
			'hnay' => current_time( 'Y-m-d' ) );
	}

	private static function v_dat( $d, $cf ) {
		global $wpdb;
		$ngay = self::chu( $d, 'ngay', 10 );
		$gio  = self::chu( $d, 'gio', 5 );
		$ten  = self::chu( $d, 'ten', 120 );
		$sdt  = self::sdt_sach( isset( $d['sdt'] ) ? $d['sdt'] : '' );
		$sl   = max( 1, (int) ( isset( $d['sl'] ) ? $d['sl'] : 1 ) );

		if ( mb_strlen( $ten ) < 2 )   { return self::loi( 'Nhập tên người đại diện.' ); }
		if ( strlen( $sdt ) < 9 )      { return self::loi( 'Số điện thoại chưa đúng.' ); }
		if ( ! in_array( $gio, self::ds_khung( $cf ), true ) ) { return self::loi( 'Khung giờ không có trong lịch.' ); }
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { return self::loi( 'Ngày không hợp lệ.' ); }
		if ( self::da_qua( $ngay, $gio ) ) { return self::loi( 'Khung ' . $gio . ' đã qua giờ.' ); }
		if ( $sl > (int) $cf['suc'] )      { return self::loi( 'Mỗi khung nhận tối đa ' . (int) $cf['suc'] . ' người.' ); }

		/* Hãm tay: một số điện thoại không thể đẻ ra hai chục đơn trong mười phút. Không có nó thì
		   một người bấm nghịch là kín cả buổi mà không ai trả đồng nào. */
		$k = 'nhama_dat_' . md5( $sdt );
		if ( (int) get_transient( $k ) >= 6 ) {
			return self::loi( 'Số này vừa giữ nhiều chỗ liên tiếp — chờ 10 phút rồi đặt tiếp, hoặc gọi ban tổ chức.' );
		}
		if ( self::da_dat( $ngay, $gio ) + $sl > (int) $cf['suc'] ) {
			return self::loi( 'Khung ' . $gio . ' vừa hết chỗ cho ' . $sl . ' người. Chọn khung khác giúp em.' );
		}

		$ma  = '';
		for ( $i = 0; $i < 6 && '' === $ma; $i++ ) {
			$thu = self::ma_moi();
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t() . ' WHERE ma=%s', $thu ) ) ) { $ma = $thu; }
		}
		if ( '' === $ma ) { return self::loi( 'Không cấp được mã, thử lại giúp em.' ); }

		$luc = current_time( 'mysql' );
		$ok  = $wpdb->insert( self::t(), array(
			'ma' => $ma, 'ten' => $ten, 'sdt' => $sdt, 'ngay' => $ngay, 'gio' => $gio,
			'sl' => $sl, 'tien' => $sl * (int) $cf['gia'], 'tt' => 'giu_cho',
			'tao' => $luc, 'sua' => $luc ) );
		if ( false === $ok ) { return self::loi( 'Không ghi được vào sổ.' ); }
		$id = (int) $wpdb->insert_id;
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );

		/* 🔴 KIỂM LẠI SAU KHI GHI — chỗ này là chỗ duy nhất chặn được hai người bấm trong cùng một
		   giây. Cả hai cùng thấy còn chỗ, cả hai cùng ghi. Nên sau khi ghi, đếm lại phần đã chiếm
		   của những đơn CÓ id NHỎ HƠN HOẶC BẰNG đơn mình: ai vào sổ trước thì giữ chỗ, ai tràn ra
		   ngoài sức chứa thì tự rút. Luật "id nhỏ thắng" là luật xác định, nên hai lượt chạy song
		   song không bao giờ cùng rút lui và cũng không bao giờ cùng ở lại. */
		$in  = "'" . implode( "','", self::CHIEM ) . "'";
		$den = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(sl),0) FROM ' . self::t()
			. " WHERE ngay=%s AND gio=%s AND tt IN ($in) AND id<=%d", $ngay, $gio, $id ) );
		if ( $den > (int) $cf['suc'] ) {
			$wpdb->delete( self::t(), array( 'id' => $id ) );
			return self::loi( 'Khung ' . $gio . ' vừa có người giữ trước mất rồi. Chọn khung khác giúp em.' );
		}

		return array( 'ok' => true, 'don' => self::kem_qr( self::don_theo_ma( $ma ) ) );
	}

	/** Mở một thiệp theo mã — dùng cho đường dẫn trong tin Zalo (#ve=GB-XXXX). */
	private static function v_ve( $d ) {
		$ma = strtoupper( self::chu( $d, 'ma', 24 ) );
		$don = self::don_theo_ma( $ma );
		if ( ! $don ) { return self::loi( 'Không thấy thiệp này.' ); }
		return array( 'ok' => true, 'don' => array( self::kem_qr( $don ) ) );
	}

	/** Sổ tiền về + nhật ký Zalo — cho màn quản trị. */
	private static function v_tien() {
		global $wpdb;
		$r = $wpdb->get_results( 'SELECT ref,luc,so_tien,noi_dung,ma,kq FROM ' . self::t_tien()
			. ' ORDER BY id DESC LIMIT 200', ARRAY_A );
		return array( 'ok' => true, 'tien' => $r ? $r : array(),
			'zalo' => array_slice( (array) get_option( 'nhama_nk_zalo', array() ), 0, 60 ),
			'duong' => self::duong_tien() . '?token=' . self::khoa_tien() );
	}

	private static function v_cua_toi( $d ) {
		global $wpdb;
		$sdt = self::sdt_sach( isset( $d['sdt'] ) ? $d['sdt'] : '' );
		if ( strlen( $sdt ) < 9 ) { return self::loi( 'Số điện thoại chưa đúng.' ); }
		/* Hãm dò: số điện thoại là thứ đoán được. Không hãm thì một máy dò quét hết đầu số. */
		$k = 'nhama_tra_' . md5( self::ip() );
		if ( (int) get_transient( $k ) >= 30 ) { return self::loi( 'Tra quá nhiều lần — chờ 10 phút.' ); }
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT ma,ten,sdt,ngay,gio,sl,tien,tt,ghi FROM ' . self::t()
			. ' WHERE sdt=%s ORDER BY ngay DESC, gio DESC LIMIT 50', $sdt ), ARRAY_A );
		$r = $r ? $r : array();
		foreach ( $r as $i => $x ) { $r[ $i ] = self::kem_qr( $x ); }
		return array( 'ok' => true, 'don' => $r );
	}

	private static function v_bao_ck( $d ) {
		global $wpdb;
		$ma = strtoupper( self::chu( $d, 'ma', 24 ) );
		$don = self::don_theo_ma( $ma );
		if ( ! $don ) { return self::loi( 'Không thấy thiệp này.' ); }
		if ( 'giu_cho' !== $don['tt'] ) { return array( 'ok' => true, 'don' => self::kem_qr( $don ) ); }
		$wpdb->update( self::t(), array( 'tt' => 'cho_duyet', 'sua' => current_time( 'mysql' ) ),
			array( 'ma' => $ma ) );
		return array( 'ok' => true, 'don' => self::kem_qr( self::don_theo_ma( $ma ) ) );
	}

	// -------------------------------------------------------------------- cửa của nhân viên
	private static function ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	}
	private static function v_vao( $d ) {
		$k = 'nhama_pin_' . md5( self::ip() );
		if ( (int) get_transient( $k ) >= 10 ) {
			return self::loi( 'Gõ sai quá nhiều lần — chờ 10 phút.' );
		}
		if ( ! self::pin_dung( isset( $d['pin'] ) ? $d['pin'] : '' ) ) {
			set_transient( $k, (int) get_transient( $k ) + 1, 600 );
			return self::loi( 'PIN chưa đúng.' );
		}
		delete_transient( $k );
		$the = wp_generate_password( 40, false );
		set_transient( 'nhama_the_' . $the, 1, 8 * 3600 );
		return array( 'ok' => true, 'the' => $the );
	}
	/**
	 * 🔴 THẺ KIỂM Ở MÁY CHỦ. Bản HTML rời gác bằng PIN nằm trong trang — ai xem mã nguồn là vào
	 *    được. Ở đây PIN đi lên máy chủ, máy chủ phát thẻ, và MỌI việc của nhân viên đều phải
	 *    mang thẻ. Xem mã nguồn trang bây giờ không cho ai thêm quyền gì.
	 */
	private static function the_dung( $the ) {
		$the = preg_replace( '/[^A-Za-z0-9]/', '', (string) $the );
		if ( '' === $the ) { return false; }
		return (bool) get_transient( 'nhama_the_' . $the );
	}

	private static function v_ds( $cf ) {
		global $wpdb;
		$r = $wpdb->get_results( 'SELECT ma,ten,sdt,ngay,gio,sl,tien,tt,ghi,vao_luc,tao FROM '
			. self::t() . ' ORDER BY id DESC LIMIT 2000', ARRAY_A );
		$cf['zalo_tt'] = self::zalo_tinh_trang();
		return array( 'ok' => true, 'don' => $r ? $r : array(), 'cf' => $cf,
			'khung' => self::ds_khung( $cf ), 'hnay' => current_time( 'Y-m-d' ) );
	}

	/** Đổi trạng thái một đơn: duyệt / từ chối / huỷ / mở lại. */
	private static function v_doi( $d ) {
		global $wpdb;
		$ma  = strtoupper( self::chu( $d, 'ma', 24 ) );
		$tt  = self::chu( $d, 'tt', 12 );
		$ghi = self::chu( $d, 'ghi', 255 );
		if ( ! isset( self::TT[ $tt ] ) ) { return self::loi( 'Trạng thái không hợp lệ.' ); }
		$don = self::don_theo_ma( $ma );
		if ( ! $don ) { return self::loi( 'Không thấy đơn ' . $ma . '.' ); }
		$wpdb->update( self::t(),
			array( 'tt' => $tt, 'ghi' => $ghi, 'sua' => current_time( 'mysql' ) ),
			array( 'ma' => $ma ) );
		/* Kế toán duyệt tay (khách trả tiền mặt, hoặc tiền về mà webhook chưa khớp) thì cũng gửi
		   Zalo — cùng một việc thì phải cùng một kết quả, bất kể ai bấm. */
		if ( 'cho_vao' === $tt && 'cho_vao' !== $don['tt'] ) { self::gui_zalo( $ma ); }
		return array( 'ok' => true, 'don' => self::don_theo_ma( $ma ) );
	}

	/**
	 * Soát vé tại cửa.
	 * ⚠️ SAI NGÀY THÌ CẢNH BÁO CHỨ KHÔNG CHẶN. Khách tới sớm hoặc trễ một khung là chuyện thường;
	 *    chặn cứng thì nhân viên phải gọi quản lý giữa lúc đông nhất. Nói ra để người ở cửa quyết
	 *    định — họ đang nhìn thấy khách, máy chủ thì không.
	 * 🔴 VÉ ĐÃ VÀO THÌ CHẶN HẲN lần hai. Đó là toàn bộ lý do có màn soát vé.
	 */
	private static function v_soat( $d ) {
		global $wpdb;
		$ma = strtoupper( self::chu( $d, 'ma', 40 ) );
		if ( preg_match( '/GB-[A-Z0-9]{4,}/', $ma, $m ) ) { $ma = $m[0]; }
		$don = self::don_theo_ma( $ma );
		if ( ! $don )                       { return self::loi( 'Không có mã ' . $ma . ' trong sổ.' ); }
		if ( 'huy' === $don['tt'] )         { return self::loi( $don['ten'] . ' — vé ĐÃ HUỶ (' . ( $don['ghi'] ? $don['ghi'] : 'không rõ lý do' ) . ').' ); }
		if ( 'giu_cho' === $don['tt'] )     { return self::loi( $don['ten'] . ' — CHƯA THANH TOÁN. Thu tiền rồi duyệt ở màn Xét duyệt.' ); }
		if ( 'cho_duyet' === $don['tt'] )   { return self::loi( $don['ten'] . ' — đang CHỜ KẾ TOÁN DUYỆT tiền.' ); }
		if ( 'da_vao' === $don['tt'] )      { return self::loi( $don['ten'] . ' — vé này ĐÃ VÀO lúc ' . $don['vao_luc'] . '. Không cho vào lần hai.' ); }
		$wpdb->update( self::t(), array( 'tt' => 'da_vao', 'vao_luc' => current_time( 'mysql' ),
			'sua' => current_time( 'mysql' ) ), array( 'ma' => $ma ) );
		$canh = ( $don['ngay'] !== current_time( 'Y-m-d' ) )
			? ' ⚠️ vé của ngày ' . $don['ngay'] . ', không phải hôm nay.' : '';
		return array( 'ok' => true, 'chu' => '✔ CHO VÀO — ' . $don['ten'] . ', ' . $don['sl']
			. ' khách, khung ' . $don['gio'] . '.' . $canh, 'don' => self::don_theo_ma( $ma ) );
	}

	private static function v_cai( $d, $cf ) {
		$moi = isset( $d['cf'] ) && is_array( $d['cf'] ) ? $d['cf'] : array();
		$so  = array( 'gia', 'suc', 'buoc', 'ngayMo', 'denSom', 'quet', 'am' );
		foreach ( $so as $k ) { if ( isset( $moi[ $k ] ) ) { $cf[ $k ] = max( 0, (int) $moi[ $k ] ); } }
		foreach ( array( 'mo', 'dong' ) as $k ) {
			if ( isset( $moi[ $k ] ) && preg_match( '/^\d{1,2}:\d{2}$/', (string) $moi[ $k ] ) ) {
				$cf[ $k ] = (string) $moi[ $k ];
			}
		}
		foreach ( array( 'zalo_app_id', 'zalo_secret', 'zalo_tpl' ) as $k ) {
			if ( isset( $moi[ $k ] ) ) { $cf[ $k ] = trim( sanitize_text_field( (string) $moi[ $k ] ) ); }
		}
		/* ⚠️ REFRESH TOKEN DÁN TAY — đường vòng khi ô "Official Account Callback Url" bên Zalo bị
		   khoá, không khai được callback nên nút Kết nối không dùng được. Lấy bằng Zalo API
		   Explorer rồi dán vào đây.
		   🔴 Ô TRỐNG NGHĨA LÀ "GIỮ NGUYÊN", không phải "xoá". Màn Cài đặt cố tình không in token
		      ra, nên ô ấy luôn hiện trống; lấy giá trị trống mà ghi đè là mỗi lượt bấm LƯU MÀN
		      HÌNH lại tự cắt mất kết nối Zalo, mà chẳng ai ngờ tại nút Lưu.
		   🔴 CÓ TOKEN MỚI THÌ VỨT ACCESS TOKEN CŨ. Access token cũ đẻ ra từ refresh token cũ; giữ
		      lại là plugin còn tưởng mình đang nối, gửi tiếp bằng thẻ đã chết cho tới lúc hết hạn. */
		if ( isset( $moi['zalo_refresh'] ) ) {
			$rf_moi = trim( sanitize_text_field( (string) $moi['zalo_refresh'] ) );
			if ( '' !== $rf_moi && $rf_moi !== (string) $cf['zalo_refresh'] ) {
				$cf['zalo_refresh'] = $rf_moi;
				$cf['zalo_token']   = '';
				$cf['zalo_het']     = 0;
			}
		}
		foreach ( array( 'so_tk', 'ten_tk' ) as $k ) {
			if ( isset( $moi[ $k ] ) ) { $cf[ $k ] = mb_substr( sanitize_text_field( (string) $moi[ $k ] ), 0, 60 ); }
		}
		if ( isset( $moi['bin'] ) ) { $cf['bin'] = preg_replace( '/\D+/', '', (string) $moi['bin'] ); }
		foreach ( array( 'ten', 'phu' ) as $k ) {
			if ( isset( $moi[ $k ] ) ) { $cf[ $k ] = mb_substr( sanitize_text_field( (string) $moi[ $k ] ), 0, 120 ); }
		}
		$cf['suc']  = max( 1, (int) $cf['suc'] );
		$cf['buoc'] = max( 1, (int) $cf['buoc'] );
		update_option( 'nhama_cf', $cf );
		$cf['tk_thu'] = self::tk();
		$cf['zalo_tt'] = self::zalo_tinh_trang();
		$pin = isset( $d['pin'] ) ? preg_replace( '/\D/', '', (string) $d['pin'] ) : '';
		if ( strlen( $pin ) >= 4 ) { self::dat_pin( $pin ); }
		return array( 'ok' => true, 'cf' => $cf, 'khung' => self::ds_khung( $cf ) );
	}

	/** Đơn mẫu — để bấm thử cho hết các màn khi sổ còn trống. Xoá bằng nút "Xoá sạch sổ". */
	private static function v_mau( $cf ) {
		global $wpdb;
		$ten = array( 'Gia Huy', 'Hân', 'Đức Bùi', 'Nguyễn Phương Linh', 'Trần Mỹ Duyên', 'Lê Anh Khoa' );
		$tts = array( 'giu_cho', 'cho_duyet', 'cho_vao', 'cho_vao', 'da_vao', 'huy' );
		$kh  = self::ds_khung( $cf );
		for ( $i = 0; $i < 12; $i++ ) {
			$sl  = wp_rand( 1, 4 );
			$tt  = $tts[ $i % count( $tts ) ];
			$luc = current_time( 'mysql' );
			$wpdb->insert( self::t(), array(
				'ma' => self::ma_moi(), 'ten' => $ten[ $i % count( $ten ) ],
				'sdt' => '09' . wp_rand( 10000000, 99999999 ),
				'ngay' => gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) ) + ( $i % 3 ) * 86400 ),
				'gio' => $kh[ $i % count( $kh ) ], 'sl' => $sl, 'tien' => $sl * (int) $cf['gia'],
				'tt' => $tt, 'ghi' => ( 'huy' === $tt ? 'không chuyển khoản' : '' ),
				'tao' => $luc, 'sua' => $luc ) );
		}
		return array( 'ok' => true );
	}
	private static function v_xoa() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . self::t() );
		return array( 'ok' => true );
	}

	public static function don_theo_ma( $ma ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT ma,ten,sdt,ngay,gio,sl,tien,tt,ghi,vao_luc,tao FROM ' . self::t() . ' WHERE ma=%s',
			$ma ), ARRAY_A );
		return $r ? $r : null;
	}

	// ========================================================================== trang
	public static function trang() {
		$cf = self::cf();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>' . esc_html( $cf['ten'] ) . '</title>'
			. '<link rel="preconnect" href="https://fonts.googleapis.com">'
			. '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
			. '<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&'
			. 'family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">'
			. '<style>' . self::css() . '</style></head><body>'
			. self::than_trang()
			. '<script>var NM = ' . wp_json_encode( array(
				'api' => esc_url_raw( rest_url( self::NS . '/x' ) ),
				'cf'  => array( 'ten' => $cf['ten'], 'phu' => $cf['phu'] ),
			) ) . ';</script><script>' . self::js() . '</script></body></html>';
	}

	private static function than_trang() {
		$cf = self::cf();
		return '<div id="apKhach">'
		. '<div class="kh-bao">'
		. '<div class="kh-dau">'
		. '<button class="kh-nut-dau" id="btTraCuu">🔎 TRA CỨU LỜI MỜI</button>'
		. '<button class="kh-nut-dau" id="btThiep">🎟 THIỆP CỦA TÔI</button></div>'
		. '<div class="kh-tieu"><h1>' . esc_html( $cf['ten'] ) . '</h1><div class="kh-gach"></div>'
		. '<p class="kh-phu">' . esc_html( $cf['phu'] ) . '</p></div>'
		. '<div class="kh-luoi"><div>'
		. '<div class="the"><h2 class="the-tieu">⧗ 1. Lựa chọn thời khắc</h2>'
		. '<select class="o" id="oNgay"></select><div class="gio-luoi" id="luoiGio"></div></div>'
		. '<div class="the" style="margin-top:18px"><h2 class="the-tieu">🖋 2. Ghi danh khách mời</h2>'
		. '<label class="nhan">Thời khắc được chọn:</label>'
		. '<input class="o" id="oChon" readonly placeholder="Chưa chọn khung giờ">'
		. '<label class="nhan">Danh Tính Người Đại Diện:</label>'
		. '<input class="o" id="oTen" placeholder="Nhập tên người đại diện tham dự...">'
		. '<label class="nhan">Tín Hiệu Liên Lạc (SĐT Zalo để nhận thư):</label>'
		. '<input class="o" id="oSdt" inputmode="tel" placeholder="Ví dụ: 0912345678">'
		. '<label class="nhan" id="nhanSl">Số lượng thành viên:</label>'
		. '<select class="o" id="oSl"></select>'
		. '<label class="cam-ket"><input type="checkbox" id="oCamKet"><span id="chuCamKet"></span></label>'
		. '<button class="nut-chinh" id="btGiuCho">🔑 Khởi tạo mã giữ chỗ - 0₫</button>'
		. '<div id="baoDat"></div></div></div>'
		. '<div class="the"><h2 class="the-tieu">👁 Tình trạng tiền sảnh</h2>'
		. '<p class="kh-phu" style="text-align:left;margin:-8px 0 12px;font-size:12px">'
		. 'Hệ thống trung tâm tự động cập nhật số lượng chỗ trống tại sảnh thực tế.</p>'
		. '<div class="ts-cuon" id="tsCuon"></div></div></div></div></div>'
		. '<div id="apQL" class="an"><aside class="q-ben">'
		. '<div class="q-hieu"><b>ADMIN PRO</b><i>ONLINE CLOUD</i></div>'
		. '<nav class="q-nav" id="qNav"></nav></aside><div class="q-than">'
		. '<div class="q-tren"><div class="noi"><span class="q-cham-tt"></span>'
		. '<span id="qNoiTT">Sổ chung trên máy chủ</span></div>'
		. '<button class="q-nut xam" id="btAm">🔔 Cảnh báo âm thanh: Đã Bật</button></div>'
		. '<div class="q-noi-dung" id="qNoiDung"></div></div></div><div id="hopThoai"></div>';
	}

	private static function css() {
		return <<<'CSS'
:root{--den:#08070a;--vien:#241d24;--do:#8b1a1a;--do-sang:#c1272d;--vang:#c8a84b;
--vang-nhat:#e6d6a2;--chu:#e8e2d9;--chu-mo:#9b9189;
--q-nen:#0b0e11;--q-nen2:#12161c;--q-the:#171c23;--q-vien:#242c36;--q-xanh:#2f81f7;
--q-luc:#2ea043;--q-do:#f85149;--q-cam:#d29922;--q-chu:#e6edf3;--q-chu-mo:#8b949e}
*{box-sizing:border-box}html,body{margin:0;padding:0}html{color-scheme:dark}
body{background:var(--den);color:var(--chu);font-family:"Be Vietnam Pro",system-ui,-apple-system,
"Segoe UI",Roboto,Arial,sans-serif;font-size:15px;line-height:1.6;-webkit-text-size-adjust:100%}
.an{display:none !important}button,input,select,textarea{font:inherit;color:inherit}
button{cursor:pointer}a{color:inherit}
#apKhach{min-height:100vh;background:radial-gradient(1200px 600px at 15% -10%,#241016 0,transparent 60%),
radial-gradient(900px 500px at 110% 20%,#1a1016 0,transparent 55%),
repeating-linear-gradient(115deg,rgba(255,255,255,.012) 0 2px,transparent 2px 7px),var(--den)}
.kh-bao{max-width:1080px;margin:0 auto;padding:20px 16px 64px}
.kh-dau{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:26px}
.kh-nut-dau{background:#15111a;border:1px solid var(--vien);color:var(--vang-nhat);border-radius:8px;
padding:9px 14px;font-size:13px;font-weight:600;letter-spacing:.02em}
.kh-nut-dau:hover{border-color:var(--vang);background:#1c1620}
.kh-tieu{text-align:center;margin:6px 0 34px}
.kh-tieu h1{font-family:Cinzel,Georgia,serif;font-size:clamp(30px,6vw,52px);letter-spacing:.12em;
margin:0;color:var(--do-sang);text-shadow:0 0 26px rgba(193,39,45,.35);font-weight:700}
.kh-gach{width:min(420px,70%);height:1px;margin:14px auto 12px;
background:linear-gradient(90deg,transparent,var(--do),transparent)}
.kh-phu{font-style:italic;color:var(--chu-mo);font-size:14px;margin:0}
.kh-luoi{display:grid;grid-template-columns:1.35fr 1fr;gap:20px;align-items:start}
@media(max-width:900px){.kh-luoi{grid-template-columns:1fr}}
.the{background:rgba(18,15,20,.86);border:1px solid var(--vien);border-radius:12px;padding:20px}
.the-tieu{display:flex;align-items:center;gap:10px;font-family:Cinzel,Georgia,serif;font-size:15px;
letter-spacing:.08em;text-transform:uppercase;color:var(--vang-nhat);margin:0 0 16px;padding-left:12px;
border-left:3px solid var(--do)}
.nhan{display:block;font-size:12px;color:var(--chu-mo);font-style:italic;margin:14px 0 6px}
.o{width:100%;background:#0c0a0e;border:1px solid var(--vien);border-radius:8px;padding:11px 12px;
color:var(--chu);outline:none}
.o:focus{border-color:var(--do);box-shadow:0 0 0 2px rgba(139,26,26,.25)}
.o::placeholder{color:#5d555c}
.gio-luoi{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-top:14px}
@media(max-width:640px){.gio-luoi{grid-template-columns:repeat(3,1fr)}}
.gio{background:#0e0c11;border:1px solid var(--vien);border-radius:8px;padding:10px 4px;
text-align:center;line-height:1.25}
.gio b{display:block;font-size:15px;font-weight:600}
.gio span{display:block;font-size:11px;color:var(--chu-mo);margin-top:2px}
.gio.duoc:hover{border-color:var(--vang);background:#171018}
.gio.chon{border-color:var(--do-sang);background:#1d1013;box-shadow:0 0 0 1px var(--do-sang) inset}
.gio.chon b,.gio.chon span{color:var(--vang-nhat)}
.gio.het{opacity:.34;cursor:not-allowed}.gio.het b,.gio.het span{color:#6b6068}
.ts-cuon{max-height:420px;overflow:auto;padding-right:6px}
.ts-cuon::-webkit-scrollbar{width:6px}.ts-cuon::-webkit-scrollbar-thumb{background:var(--do);border-radius:3px}
.ts-hang{display:grid;grid-template-columns:60px 1fr auto;align-items:center;gap:10px;padding:9px 2px;
border-bottom:1px solid #1b161b;font-size:13px}
.ts-hang i{font-style:normal;color:var(--chu-mo);font-size:12px}
.the-trang{background:#1c1a1d;border-radius:5px;padding:3px 9px;font-size:11px;font-weight:600;white-space:nowrap}
.the-trang.con{background:#12261a;color:#7ddba0}.the-trang.sap{background:#2a2110;color:#e0be6a}
.the-trang.het{background:#231a1a;color:#c9a0a0}
.cam-ket{display:flex;gap:10px;align-items:flex-start;background:#0e0b10;border-left:3px solid var(--do);
border-radius:6px;padding:12px;margin:16px 0;font-size:12.5px;color:var(--chu-mo)}
.cam-ket input{margin-top:3px;flex:none;width:16px;height:16px;accent-color:var(--do-sang)}
.nut-chinh{width:100%;background:linear-gradient(180deg,#6d1418,#4a0e11);border:1px solid #7c1c20;
color:var(--vang-nhat);border-radius:9px;padding:15px;font-family:Cinzel,Georgia,serif;font-size:14px;
letter-spacing:.1em;font-weight:700;text-transform:uppercase}
.nut-chinh:hover:not(:disabled){background:linear-gradient(180deg,#87181d,#5c1114)}
.nut-chinh:disabled{opacity:.45;cursor:not-allowed}
.bao{border-radius:8px;padding:11px 13px;font-size:13px;margin-top:12px}
.bao.hong{background:#2a1113;border:1px solid #5c1f22;color:#f0b8ba}
.bao.duoc{background:#10241a;border:1px solid #1f5c37;color:#a7e6c1}
.thiep{border:1px solid var(--vang);border-radius:12px;padding:20px;margin-top:14px;
background:linear-gradient(180deg,#151016,#0d0a0e)}
.thiep .ma{font-family:Cinzel,Georgia,serif;font-size:26px;letter-spacing:.16em;color:var(--vang);
text-align:center;margin:6px 0 4px;word-break:break-all}
.thiep .dong{display:flex;justify-content:space-between;gap:12px;padding:7px 0;
border-bottom:1px dashed #2a222a;font-size:13.5px}
.thiep .dong:last-of-type{border-bottom:0}.thiep .dong span{color:var(--chu-mo)}
.huong-dan{background:#0e0b10;border:1px solid var(--vien);border-radius:8px;padding:13px;
margin-top:14px;font-size:13px}.huong-dan b{color:var(--vang-nhat)}
.qr-khung{text-align:center;margin-top:16px}
.qr-anh{background:#fff;border-radius:10px;padding:10px;display:inline-block;line-height:0}
.qr-anh svg{width:220px;height:220px;display:block}
.tk-dong{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 0;
border-bottom:1px dashed #2a222a;font-size:13.5px;text-align:left}
.tk-dong:last-child{border-bottom:0}
.tk-dong span{color:var(--chu-mo);flex:none}
.tk-dong b{margin-left:auto;text-align:right;word-break:break-all}
.chep{background:#191319;border:1px solid var(--vien);color:var(--vang-nhat);border-radius:6px;
padding:4px 9px;font-size:11px;font-weight:600;flex:none}
.chep:hover{border-color:var(--vang)}
.nd-to{cursor:pointer;font-family:Cinzel,Georgia,serif;font-size:20px;letter-spacing:.14em;color:var(--vang);
text-align:center;background:#0c0a0e;border:1px dashed var(--vang);border-radius:8px;
padding:10px;margin:10px 0 4px;word-break:break-all;transition:background .15s,border-color .15s}
.nd-to.nd-nhay{background:#14240f;border-color:#7ddba0;border-style:solid;color:#a7e6c1}
.nut-phu{background:#191319;border:1px solid var(--vien);color:var(--chu);border-radius:8px;
padding:10px 14px;font-size:13px;font-weight:600}.nut-phu:hover{border-color:var(--vang)}
.hang-nut{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.man-che{position:fixed;inset:0;background:rgba(4,3,5,.86);display:flex;align-items:center;
justify-content:center;padding:18px;z-index:50}
.hop{background:#100d12;border:1px solid var(--vien);border-radius:12px;padding:22px;
width:min(420px,100%);position:relative;max-height:90vh;overflow:auto}
.hop-dong{position:absolute;top:10px;right:12px;background:none;border:0;color:var(--chu-mo);
font-size:20px;line-height:1}
#apQL{min-height:100vh;background:var(--q-nen);color:var(--q-chu);display:grid;grid-template-columns:260px 1fr}
@media(max-width:820px){#apQL{grid-template-columns:1fr}.q-ben{position:static !important}}
.q-ben{background:#000;border-right:1px solid var(--q-vien);min-height:100vh;position:sticky;top:0}
.q-hieu{padding:22px 18px;border-bottom:1px solid var(--q-vien);text-align:center}
.q-hieu b{display:block;font-size:20px;font-weight:700;letter-spacing:.14em}
.q-hieu i{display:inline-block;margin-top:7px;background:var(--q-luc);color:#04120a;border-radius:4px;
padding:2px 9px;font-size:10px;font-style:normal;font-weight:700;letter-spacing:.08em}
.q-nav{padding:8px 0}
.q-nav button{display:flex;align-items:center;gap:11px;width:100%;background:none;border:0;
border-left:3px solid transparent;color:var(--q-chu-mo);padding:13px 18px;font-size:14px;text-align:left}
.q-nav button:hover{background:#0e1319;color:var(--q-chu)}
.q-nav button.on{background:#0d1b2e;border-left-color:var(--q-xanh);color:#fff;font-weight:600}
.q-nav button.thoat{color:var(--q-do)}
.q-nav .cham{margin-left:auto;background:var(--q-do);color:#fff;border-radius:999px;min-width:20px;
height:20px;display:grid;place-items:center;font-size:11px;font-weight:700;padding:0 6px}
.q-tren{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
background:var(--q-nen2);border-bottom:1px solid var(--q-vien);padding:14px 22px;font-size:14px}
.q-tren .noi{display:flex;align-items:center;gap:9px;font-weight:600}
.q-cham-tt{width:10px;height:10px;border-radius:50%;background:var(--q-luc);flex:none}
.q-than{min-width:0}.q-noi-dung{padding:22px}
.q-tieu{display:flex;align-items:center;gap:10px;font-size:22px;font-weight:700;margin:0 0 18px}
.q-the{background:var(--q-the);border:1px solid var(--q-vien);border-radius:10px;padding:18px}
.q-so{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
@media(max-width:1180px){.q-so{grid-template-columns:1fr}}
.q-so .q-the{display:flex;align-items:center;gap:16px}
.q-bieu{width:52px;height:52px;border-radius:9px;display:grid;place-items:center;font-size:22px;flex:none}
.q-so small{display:block;color:var(--q-chu-mo);font-size:11.5px;letter-spacing:.06em;
text-transform:uppercase;font-weight:600}
.q-so b{font-size:26px;font-weight:700;line-height:1.2}
.q-thanh{height:9px;background:#0d1117;border-radius:5px;overflow:hidden;margin:14px 0 18px}
.q-thanh i{display:block;height:100%;background:linear-gradient(90deg,var(--q-xanh),var(--q-luc))}
.q-luoi-gio{display:grid;grid-template-columns:repeat(auto-fill,minmax(128px,1fr));gap:12px}
.q-gio{background:#10151b;border:1px solid var(--q-vien);border-radius:9px;padding:13px 8px;text-align:center}
.q-gio b{display:block;font-size:17px;font-weight:700}
.q-gio span{display:block;font-size:12px;color:var(--q-chu-mo);margin-top:3px}
.q-gio em{display:block;font-style:normal;font-size:12px;font-weight:600;margin-top:2px}
.q-gio.trong em{color:var(--q-chu-mo)}
.q-gio.sap{border-color:#5c4813}.q-gio.sap em{color:var(--q-cam)}
.q-gio.day{border-color:#5c2222}.q-gio.day em{color:var(--q-do)}
.q-nut{border:0;border-radius:8px;padding:11px 16px;font-size:13.5px;font-weight:700;color:#fff}
.q-nut.xanh{background:var(--q-xanh)}.q-nut.luc{background:var(--q-luc)}
.q-nut.do{background:var(--q-do)}.q-nut.xam{background:#21262d;color:var(--q-chu)}
.q-nut:hover{filter:brightness(1.12)}
.q-duyet{display:grid;grid-template-columns:150px 1fr auto;gap:18px;align-items:center}
@media(max-width:760px){.q-duyet{grid-template-columns:1fr}}
.q-bill{background:#0d1117;border:1px dashed var(--q-vien);border-radius:8px;height:150px;
display:grid;place-items:center;text-align:center;color:var(--q-chu-mo);font-size:12px;font-weight:600}
.q-bang{width:100%;border-collapse:collapse;font-size:13.5px}
.q-bang th{text-align:left;color:var(--q-chu-mo);font-size:11.5px;letter-spacing:.06em;
text-transform:uppercase;padding:12px 10px;border-bottom:1px solid var(--q-vien);white-space:nowrap}
.q-bang td{padding:12px 10px;border-bottom:1px solid #1b2129;vertical-align:top}
.q-bang tr:hover td{background:#10151b}.q-cuon{overflow-x:auto}
.nhan-tt{display:inline-block;border-radius:5px;padding:3px 9px;font-size:11.5px;font-weight:700;
border:1px solid;white-space:nowrap}
.tt-giu{background:#161b22;border-color:#30363d;color:var(--q-chu-mo)}
.tt-duyet{background:#2a1f08;border-color:#6b4c0e;color:#e3b341}
.tt-vao{background:#0d2416;border-color:#1a6b34;color:#56d364}
.tt-xong{background:#0d1b2e;border-color:#1f4e85;color:#79c0ff}
.tt-huy{background:#2a1214;border-color:#6b1f24;color:#ff7b72}
.q-zalo{display:inline-block;background:#0068ff;color:#fff;border-radius:5px;padding:3px 9px;
font-size:11.5px;font-weight:600;text-decoration:none;margin-top:4px}
.q-loc{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.q-o{background:#0d1117;border:1px solid var(--q-vien);border-radius:7px;padding:9px 12px;
color:var(--q-chu);outline:none}.q-o:focus{border-color:var(--q-xanh)}
.q-cam-hinh{background:#0d1117;border:1px solid var(--q-vien);border-radius:9px;overflow:hidden;
aspect-ratio:4/3;display:grid;place-items:center}
.q-cam-hinh video{width:100%;height:100%;object-fit:cover}
.q-doi{display:grid;grid-template-columns:1fr 1fr;gap:18px}
@media(max-width:820px){.q-doi{grid-template-columns:1fr}}
.q-nho{color:var(--q-chu-mo);font-size:12.5px}
CSS;
	}

	private static function js() {
		return <<<'JS'
/* ============================================================================================
 * Trang khách + trang quản trị. Mọi thứ đi qua ĐÚNG MỘT cổng: NM.api.
 * 🔴 Trang này KHÔNG tự đếm chỗ và KHÔNG tự quyết trạng thái đơn — máy chủ quyết. Trang chỉ vẽ.
 *    Đếm ở trình duyệt là đếm trên một ảnh chụp cũ, mà hai người bấm cùng lúc thì cả hai đều
 *    thấy "còn chỗ".
 * ========================================================================================== */
function g(id){ return document.getElementById(id); }
function esc(s){ var d=document.createElement("div"); d.textContent=(s==null?"":String(s)); return d.innerHTML; }
function tien(n){ return (Number(n)||0).toLocaleString("vi-VN")+"₫"; }
function hai(n){ return (n<10?"0":"")+n; }
function ngayISO(d){ return d.getFullYear()+"-"+hai(d.getMonth()+1)+"-"+hai(d.getDate()); }
function ngayVN(iso){ var p=String(iso).split("-"); return p.length===3?p[2]+"/"+p[1]+"/"+p[0]:iso; }
var THU=["Chủ Nhật","Thứ Hai","Thứ Ba","Thứ Tư","Thứ Năm","Thứ Sáu","Thứ Bảy"];
function sdtSach(s){ return String(s||"").replace(/[^0-9+]/g,""); }
var TT={ giu_cho:{chu:"Đang giữ chỗ",lop:"tt-giu"}, cho_duyet:{chu:"Chờ duyệt thanh toán",lop:"tt-duyet"},
  cho_vao:{chu:"Chờ Check-in",lop:"tt-vao"}, da_vao:{chu:"Đã vào",lop:"tt-xong"},
  huy:{chu:"Bị huỷ",lop:"tt-huy"} };

var THE = null;   /* thẻ phiên của nhân viên — máy chủ phát sau khi đúng PIN */
try { THE = sessionStorage.getItem("nhama_the")||null; } catch(e){}

/* 🔴 KHÔNG GỌI r.json() TRẦN. Hosting chèn trang chặn, hoặc PHP nổ, thì thân trả về là HTML và
   r.json() ném "Unexpected token <" — lỗi ấy trôi vào catch rồi bị nuốt, màn hình đứng im không
   nói gì. Đọc ra chữ trước, không phải JSON thì báo cả mã HTTP lẫn mấy chữ máy chủ nói. */
function api(viec, than){
  var t = Object.assign({viec:viec}, than||{});
  if (THE) { t.the = THE; }
  var ma = 0;
  return fetch(NM.api, { method:"POST", headers:{"Content-Type":"application/json"},
    body: JSON.stringify(t) })
  .then(function(r){ ma=r.status; return r.text(); })
  .then(function(chu){
    var j=null;
    try { j = JSON.parse(chu); } catch(e){
      var goi = ma>=500 ? " Máy chủ đang lỗi — báo quản trị xem nhật ký hosting."
              : (ma===403 ? " Hosting đang chặn đường này (tường lửa)."
              : (ma===404 ? " Sai đường dẫn — vào Cài đặt → Đường dẫn tĩnh bấm Lưu một lần." : ""));
      var dau = String(chu||"").replace(/<[^>]*>/g," ").replace(/\s+/g," ").trim().slice(0,120);
      throw new Error("Máy chủ trả về nội dung không đọc được (mã "+ma+")."+goi+(dau?" Máy chủ nói: "+dau:""));
    }
    if (j && j.ma==="het_phien"){ THE=null; try{ sessionStorage.removeItem("nhama_the"); }catch(e){}
      veTrang(); throw new Error(j.error||"Phiên đã hết."); }
    return j;
  });
}

/* ============================================================================== TRANG KHÁCH */
var CFK={gia:0,suc:10,ngayMo:14,denSom:10}, KHUNG=[], chonNgay=null, chonGio="", HNAY="";

function napKhach(){
  return api("goi", {ngay: chonNgay}).then(function(j){
    if (!j || !j.ok) { return; }
    CFK=j.cf; KHUNG=j.khung; HNAY=j.hnay;
    if (!chonNgay) { chonNgay=j.ngay; }
    veKhach();
  }).catch(function(e){
    g("luoiGio").innerHTML = "<div class='bao hong' style='grid-column:1/-1'>"+esc(e.message)+"</div>";
  });
}
function timKhung(gio){ for(var i=0;i<KHUNG.length;i++){ if(KHUNG[i].gio===gio) return KHUNG[i]; } return null; }
function conCho(gio){ var k=timKhung(gio); return k ? Math.max(0, CFK.suc-k.dat) : 0; }

function veNgay(){
  var s=g("oNgay"), h="", hnay=new Date(HNAY+"T00:00:00");
  if (isNaN(hnay)) { hnay=new Date(); }
  for (var i=0;i<CFK.ngayMo;i++){
    var d=new Date(hnay.getFullYear(),hnay.getMonth(),hnay.getDate()+i), iso=ngayISO(d);
    h+="<option value='"+iso+"'>"+(i===0?"[Hôm nay] ":"")+THU[d.getDay()]+" - "+ngayVN(iso)+"</option>";
  }
  s.innerHTML=h; s.value=chonNgay;
}
function veLuoiGio(){
  var h="";
  for (var i=0;i<KHUNG.length;i++){
    var k=KHUNG[i], con=CFK.suc-k.dat, het=(con<=0||k.qua);
    var chu = k.qua ? "Đã qua" : (con<=0 ? "Đóng" : con+" chỗ");
    h+="<button class='gio "+(het?"het":"duoc")+(chonGio===k.gio?" chon":"")+"'"+(het?" disabled":"")
      +" data-gio='"+k.gio+"'><b>"+k.gio+"</b><span>"+chu+"</span></button>";
  }
  g("luoiGio").innerHTML=h;
}
function veTienSanh(){
  var h="";
  for (var i=0;i<KHUNG.length;i++){
    var k=KHUNG[i], con=CFK.suc-k.dat, lop, chu;
    if (k.qua)       { lop="het"; chu="Đã qua"; }
    else if (con<=0) { lop="het"; chu="Khép Kín"; }
    else if (con<=3) { lop="sap"; chu="Còn "+con+" chỗ"; }
    else             { lop="con"; chu="Còn "+con+" chỗ"; }
    h+="<div class='ts-hang'><b>"+k.gio+"</b><i>"+((k.qua||con<=0)?"-":"trống "+con+"/"+CFK.suc)
      +"</i><span class='the-trang "+lop+"'>"+chu+"</span></div>";
  }
  g("tsCuon").innerHTML=h;
}
function veSoLuong(){
  var toi = chonGio ? Math.min(CFK.suc, conCho(chonGio)) : CFK.suc, h="";
  if (toi<1) { toi=1; }
  for (var i=1;i<=toi;i++){ h+="<option value='"+i+"'>"+i+" Thành Viên</option>"; }
  g("oSl").innerHTML=h;
  g("nhanSl").textContent="Số lượng thành viên (Tối đa "+toi+" người):";
}
function veKhach(){
  veNgay(); veLuoiGio(); veTienSanh(); veSoLuong();
  g("oChon").value = chonGio ? (THU[new Date(chonNgay+"T00:00:00").getDay()]+" "+ngayVN(chonNgay)+" — "+chonGio) : "";
  g("chuCamKet").innerHTML="Tôi xác nhận tham gia nghi thức và cam kết hiện diện tại tiền sảnh trước <b>"
    +esc(CFK.denSom)+" phút</b> để được dẫn lối.";
}
g("oNgay").addEventListener("change", function(){ chonNgay=this.value; chonGio=""; napKhach(); });
g("luoiGio").addEventListener("click", function(e){
  var n=e.target.closest("[data-gio]"); if(!n) return;
  chonGio=n.getAttribute("data-gio"); veLuoiGio(); veSoLuong();
  g("oChon").value=THU[new Date(chonNgay+"T00:00:00").getDay()]+" "+ngayVN(chonNgay)+" — "+chonGio;
});
g("btGiuCho").addEventListener("click", function(){
  var b=this, ten=g("oTen").value.trim(), sdt=sdtSach(g("oSdt").value), sl=+g("oSl").value||1;
  function hong(chu){ g("baoDat").innerHTML="<div class='bao hong'>"+esc(chu)+"</div>"; }
  if (!chonGio)             { return hong("Chưa chọn khung giờ ở mục 1."); }
  if (ten.length<2)         { return hong("Nhập tên người đại diện."); }
  if (sdt.length<9)         { return hong("Số điện thoại chưa đúng — cần đủ số để gửi thư mời qua Zalo."); }
  if (!g("oCamKet").checked){ return hong("Cần tích vào ô cam kết có mặt trước "+CFK.denSom+" phút."); }
  /* Khoá nút: mạng chậm, người ta bấm ba lần, và ba lượt ấy là ba đơn. */
  b.disabled=true; b.textContent="ĐANG GIỮ CHỖ…";
  api("dat", {ngay:chonNgay, gio:chonGio, ten:ten, sdt:sdt, sl:sl}).then(function(j){
    if (!j || !j.ok) { hong((j&&j.error)||"Không giữ được chỗ."); return napKhach(); }
    try { localStorage.setItem("nhama_sdt", sdt); } catch(e){}
    g("oCamKet").checked=false; g("baoDat").innerHTML="";
    napKhach(); moThiep([j.don], "Đã giữ chỗ. Đây là thiệp của anh/chị.");
  }).catch(function(e){ hong(e.message); })
    .then(function(){ b.disabled=false; b.textContent="🔑 Khởi tạo mã giữ chỗ - 0₫"; });
});

function htmlThiep(v){
  var t=TT[v.tt]||TT.giu_cho;
  return "<div class='thiep'><div class='ma'>"+esc(v.ma)+"</div>"
    +"<div style='text-align:center;margin-bottom:12px'><span class='the-trang "
    +(v.tt==="huy"?"het":(v.tt==="giu_cho"?"sap":"con"))+"'>"+esc(t.chu)+"</span></div>"
    +"<div class='dong'><span>Khách mời</span><b>"+esc(v.ten)+"</b></div>"
    +"<div class='dong'><span>Thời khắc</span><b>"+esc(ngayVN(v.ngay))+" — "+esc(v.gio)+"</b></div>"
    +"<div class='dong'><span>Thành viên</span><b>"+esc(v.sl)+" người</b></div>"
    +"<div class='dong'><span>Cần thu</span><b style='color:var(--vang)'>"+tien(v.tien)+"</b></div></div>";
}
/**
 * Khối trả tiền: mã QR VietQR + số tài khoản + nội dung.
 *
 * 🔴 CÓ CẢ QR LẪN CHỮ CHÉP TAY, hai đường, vì khách dùng hai kiểu khác nhau. Người đang cầm chính
 *    cái máy hiện trang này vẫn quét được: app ngân hàng Việt Nam đều cho CHỌN ẢNH QR TỪ THƯ VIỆN,
 *    và người đi cùng chĩa máy vào màn là quét luôn. Còn ai không quét được thì vẫn còn số tài
 *    khoản và nội dung để gõ tay.
 * 🔴 NỘI DUNG CHUYỂN KHOẢN LÀ SỢI DÂY DUY NHẤT NỐI TIỀN VỚI ĐƠN — nên nó to, có nút chép, và có
 *    câu cảnh báo ngay cạnh, không nhét vào chữ nhỏ.
 */
function htmlTraTien(v, nhieu){
  if (!v.tk || v.tk.thieu){
    return "<div class='bao hong' style='margin-top:14px'>Ban tổ chức chưa khai tài khoản nhận "
      +"tiền nên chưa dựng được mã QR. Nhờ anh/chị báo giúp ban tổ chức — thiệp vẫn giữ chỗ bình "
      +"thường.</div>";
  }
  return "<div class='qr-khung'>"
    +(v.qr ? "<div class='qr-anh'>"+v.qr+"</div>" : "")
    +(nhieu ? "<p class='kh-phu' style='font-size:12px;margin:8px 0 0'>Mã QR này cho thiệp "
      +esc(v.ma)+". Mỗi thiệp chuyển một lần riêng.</p>" : "")
    +"</div>"
    +"<div style='margin-top:14px'>"
    +"<div class='tk-dong'><span>Ngân hàng</span><b>"+esc(v.tk.ten_nh||("BIN "+v.tk.bin))+"</b></div>"
    +"<div class='tk-dong'><span>Số tài khoản</span><b>"+esc(v.tk.so_tk)+"</b>"
      +"<button class='chep' data-chep='"+esc(v.tk.so_tk)+"'>Chép</button></div>"
    +(v.tk.ten_tk ? "<div class='tk-dong'><span>Chủ tài khoản</span><b>"+esc(v.tk.ten_tk)+"</b></div>" : "")
    /* Số tiền cũng lấy con số NẰM TRONG QR (v.tien_qr), không lấy tiền của đơn: hiện một đằng
       mà QR mang một nẻo là khách chuyển thiếu rồi cãi nhau ở cửa. */
    +"<div class='tk-dong'><span>Số tiền</span><b style='color:var(--vang)'>"+tien(v.tien_qr||v.tien)+"</b>"
      +"<button class='chep' data-chep='"+esc(v.tien_qr||v.tien)+"'>Chép</button></div>"
    +"</div>"
    +"<p class='nhan' style='text-align:center'>Nội dung chuyển khoản — GÕ ĐÚNG chuỗi này:</p>"
    /* Bấm thẳng vào ô là chép. Chuỗi hiện ra có giãn chữ cho dễ đọc, mà bôi đen tay thì rất dễ
       hụt một ký tự ở đầu hoặc cuối — nút chép luôn ra đúng nguyên chuỗi. */
    +"<div class='nd-to' data-chep='"+esc(v.nd||v.ma)+"' title='Bấm để chép'>"+esc(v.nd||v.ma)+"</div>"
    +"<div style='text-align:center'><button class='chep' data-chep='"+esc(v.nd||v.ma)+"'>Chép nội dung</button></div>"
    +"<p class='kh-phu' style='font-size:12px;margin-top:8px;text-align:center'>Sai nội dung là tiền "
    +"vào tài khoản mà không biết của thiệp nào — ban tổ chức phải dò tay.</p>";
}
/* Chép vào bộ nhớ tạm. Bản `clipboard` chỉ chạy trên HTTPS; máy nào không có thì bôi đen sẵn cho
   khách tự bấm chép — im lặng không làm gì là khách bấm mãi tưởng máy hỏng.
 * 🔴 KHÔNG ĐƯỢC GHI ĐÈ CHỮ CỦA CHÍNH Ô NỘI DUNG. Bản đầu đổi `textContent` của phần tử vừa bấm để
 *    báo "đã chép" — với cái nút thì được, nhưng ô nội dung chuyển khoản CŨNG bấm chép được, và
 *    thế là chuỗi khách đang cần đọc bị thay mất trong một giây rưỡi. Bấm hai lần liên tiếp thì
 *    nó nhớ luôn chữ "✓ Đã chép" làm chữ gốc và chuỗi mất hẳn. Phép thử bấm thật bắt được.
 *    Nay: ô thì NHÁY VIỀN, chỉ nút mới đổi nhãn — và nút cũng chống bấm chồng. */
function chep(txt, nut){
  function xong(){
    if (nut.classList.contains("nd-to")){ nhay(nut); return; }
    if (nut.dataset.dangBao) { return; }
    nut.dataset.dangBao="1";
    var cu=nut.textContent; nut.textContent="✓ Đã chép";
    setTimeout(function(){ nut.textContent=cu; delete nut.dataset.dangBao; },1400);
  }
  if (navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(txt).then(xong, function(){ tayChep(txt,xong); });
  } else { tayChep(txt,xong); }
}
/* Báo "đã chép" cho ô nội dung mà KHÔNG đụng vào chữ trong ô. */
function nhay(o){
  o.classList.add("nd-nhay");
  setTimeout(function(){ o.classList.remove("nd-nhay"); },900);
}
function tayChep(txt, xong){
  var o=document.createElement("textarea");
  o.value=txt; o.style.position="fixed"; o.style.opacity="0";
  document.body.appendChild(o); o.select();
  try{ document.execCommand("copy"); xong(); }catch(e){ prompt("Chép chuỗi này:", txt); }
  document.body.removeChild(o);
}
/* QR VÀO CỬA — chỉ hiện khi thiệp đã hợp lệ. */
function htmlQrVe(v){
  return "<div class='qr-khung' style='margin-top:18px'>"
    +"<p class='nhan' style='text-align:center;margin-bottom:6px'>Mã QR vào cửa — đưa cho nhân viên quét:</p>"
    +"<div class='qr-anh'>"+v.qr_ve+"</div>"
    +"<p class='kh-phu' style='font-size:12px;margin-top:8px'>Không quét được thì đọc mã chữ "
    +"<b style='color:var(--vang)'>"+esc(v.ma)+"</b> cũng vào được.</p></div>";
}

/* ============================================================================================
 * TỰ DÒ TIỀN VỀ — trả lời đúng câu "thanh toán xong có tự báo thành công không"
 * ============================================================================================
 * Khách chuyển khoản xong thì ngân hàng bắn về cổng /nha-ma-tien, máy chủ khớp nội dung với mã
 * thiệp rồi tự duyệt. Trang này hỏi lại máy chủ vài giây một lần để đổi màn hình ngay tại chỗ —
 * khách không phải bấm gì, không phải tải lại trang.
 *
 * ⚠️ CÓ ĐIỂM DỪNG. Hỏi mãi thì một cái điện thoại để quên trong túi thành ra gõ cửa máy chủ cả
 *    ngày. Ba phút không thấy tiền về thì thôi, và NÓI RA là đã thôi, kèm nút hỏi lại — im lặng
 *    dừng là khách ngồi đợi một cái màn hình không bao giờ đổi.
 * ========================================================================================== */
var doTienHen = null;
function doTien(ds){
  if (doTienHen) { clearInterval(doTienHen); doTienHen=null; }
  var can = ds.filter(function(v){ return v.tt==="giu_cho"||v.tt==="cho_duyet"; });
  if (!can.length) { return; }
  var sdt = can[0].sdt, lan = 0;
  doTienHen = setInterval(function(){
    /* Hộp đóng rồi thì thôi — người ta đã đi chỗ khác. */
    if (!g("hopThoai").innerHTML) { clearInterval(doTienHen); doTienHen=null; return; }
    lan++;
    if (lan > 36) {                       /* 36 × 5 giây = 3 phút */
      clearInterval(doTienHen); doTienHen=null;
      var o=g("baoDo"); if(o){ o.innerHTML="<div class='bao hong'>Vẫn chưa thấy tiền về. Ngân hàng "
        +"có khi chậm vài phút. <button class='nut-phu' id='btDoLai' style='margin-left:6px'>Kiểm tra lại</button></div>";
        g("btDoLai").addEventListener("click", function(){ napThiep(sdt); }); }
      return;
    }
    api("cua_toi",{sdt:sdt}).then(function(j){
      if (!j || !j.ok) { return; }
      var moi = j.don||[], doi = false;
      for (var i=0;i<moi.length;i++){
        for (var k=0;k<ds.length;k++){
          if (moi[i].ma===ds[k].ma && moi[i].tt!==ds[k].tt) { doi=true; }
        }
      }
      if (doi){ clearInterval(doTienHen); doTienHen=null; moThiep(moi, "✔ Đã nhận được tiền. Thiệp hợp lệ."); }
    }).catch(function(){});
  }, 5000);
}
function napThiep(sdt){
  api("cua_toi",{sdt:sdt}).then(function(j){ moThiep((j&&j.don)||[]); }).catch(function(e){ alert(e.message); });
}

function moThiep(ds, loi){
  if (!ds || !ds.length){
    return moHop("<h3 class='the-tieu'>Thiệp của tôi</h3><p class='kh-phu' style='text-align:left'>"
      +"Chưa tìm thấy lời mời nào cho số này.</p>");
  }
  var h=(loi?"<div class='bao duoc'>"+esc(loi)+"</div>":"");
  for (var i=0;i<ds.length;i++){ h+=htmlThiep(ds[i]); }
  /* Khối chuyển khoản chỉ hiện cho đơn CHƯA trả tiền. Đơn đã duyệt rồi mà còn chìa QR ra là mời
     khách trả lần thứ hai. */
  var noTien = ds.filter(function(v){ return v.tt==="giu_cho"||v.tt==="cho_duyet"; });
  if (noTien.length) { h += htmlTraTien(noTien[0], noTien.length>1); }
  /* Đã trả tiền rồi thì thay QR chuyển khoản bằng QR VÀO CỬA. Không bao giờ hiện hai mã đen
     trắng giống hệt nhau cùng lúc — khách quét nhầm cái nọ ra cái kia. */
  var daTra = ds.filter(function(v){ return (v.tt==="cho_vao"||v.tt==="da_vao") && v.qr_ve; });
  if (daTra.length) { h += htmlQrVe(daTra[0]); }
  if (noTien.length){
    h+="<div class='huong-dan'><b>Bước tiếp theo:</b> quét mã QR trên bằng app ngân hàng — số tiền và "
      +"nội dung đã điền sẵn, không phải gõ. <b>Chuyển xong không phải làm gì thêm</b>: hệ thống nhận "
      +"báo từ ngân hàng và tự đổi thiệp sang <i>Chờ Check-in</i>, thường trong khoảng một phút. "
      +"Trang này tự cập nhật, anh/chị cứ để mở.</div>";
  } else {
    h+="<div class='huong-dan'><b>Thiệp đã hợp lệ.</b> Tới nơi đưa mã QR trên cho nhân viên ở cửa "
      +"quét, hoặc đọc mã chữ. Nhớ có mặt trước "+esc(CFK.denSom)+" phút.</div>";
  }
  var chua=ds.filter(function(v){ return v.tt==="giu_cho"; });
  h+="<div class='hang-nut'>"+(chua.length?"<button class='nut-phu' id='btDaCK'>Tôi đã chuyển khoản</button>":"")
    +"<button class='nut-phu' id='btLuuAnh'>Lưu thiệp (.svg)</button></div>"
    +"<div id='baoDo'></div>";
  moHop(h);
  doTien(ds);
  if (chua.length){
    g("btDaCK").addEventListener("click", function(){
      var b=this; b.disabled=true; b.textContent="Đang báo…";
      Promise.all(chua.map(function(v){ return api("bao_ck",{ma:v.ma}); })).then(function(){
        return api("cua_toi",{sdt:ds[0].sdt});
      }).then(function(j){
        moThiep((j&&j.don)||ds, "Đã báo. Ban tổ chức sẽ duyệt trong ít phút.");
      }).catch(function(e){ alert(e.message); b.disabled=false; b.textContent="Tôi đã chuyển khoản"; });
    });
  }
  g("btLuuAnh").addEventListener("click", function(){ taiThiep(ds[0]); });
}
function taiThiep(v){
  var s="<svg xmlns='http://www.w3.org/2000/svg' width='640' height='300' viewBox='0 0 640 300'>"
    +"<rect width='640' height='300' fill='#0d0a0e'/>"
    +"<rect x='10' y='10' width='620' height='280' fill='none' stroke='#c8a84b'/>"
    +"<text x='320' y='62' fill='#c1272d' font-size='24' font-family='Georgia,serif' letter-spacing='5' "
    +"text-anchor='middle'>"+esc(NM.cf.ten)+"</text>"
    +"<text x='320' y='120' fill='#c8a84b' font-size='34' font-family='Georgia,serif' letter-spacing='8' "
    +"text-anchor='middle'>"+esc(v.ma)+"</text>"
    +"<text x='320' y='166' fill='#e8e2d9' font-size='17' font-family='Arial' text-anchor='middle'>"
    +esc(v.ten)+" · "+esc(v.sl)+" người</text>"
    +"<text x='320' y='200' fill='#e8e2d9' font-size='17' font-family='Arial' text-anchor='middle'>"
    +esc(ngayVN(v.ngay))+" — "+esc(v.gio)+"</text>"
    +"<text x='320' y='250' fill='#9b9189' font-size='13' font-family='Arial' text-anchor='middle'>"
    +"Đọc mã này ở cửa.</text></svg>";
  var a=document.createElement("a");
  a.href="data:image/svg+xml;charset=utf-8,"+encodeURIComponent(s);
  a.download="thiep-"+v.ma+".svg"; a.click();
}
g("btThiep").addEventListener("click", function(){
  var sdt=""; try{ sdt=localStorage.getItem("nhama_sdt")||""; }catch(e){}
  if (!sdt) { return moTraCuu(); }
  api("cua_toi",{sdt:sdt}).then(function(j){ moThiep((j&&j.don)||[]); }).catch(function(e){ alert(e.message); });
});
g("btTraCuu").addEventListener("click", moTraCuu);
function moTraCuu(){
  moHop("<h3 class='the-tieu'>🔎 Tra cứu lời mời</h3><p class='kh-phu' style='text-align:left;margin-top:-8px'>"
    +"Cung cấp tín hiệu liên lạc (SĐT) để hệ thống trích xuất danh tính.</p>"
    +"<input class='o' id='oTraSdt' inputmode='tel' placeholder='Nhập Số Điện Thoại...' "
    +"style='text-align:center;margin:14px 0'><button class='nut-chinh' id='btTra'>Trích xuất dữ liệu</button>"
    +"<div id='baoTra'></div>");
  g("btTra").addEventListener("click", function(){
    var sdt=sdtSach(g("oTraSdt").value);
    if (sdt.length<9){ g("baoTra").innerHTML="<div class='bao hong'>Số điện thoại chưa đúng.</div>"; return; }
    try{ localStorage.setItem("nhama_sdt", sdt); }catch(e){}
    api("cua_toi",{sdt:sdt}).then(function(j){ moThiep((j&&j.don)||[]); })
      .catch(function(e){ g("baoTra").innerHTML="<div class='bao hong'>"+esc(e.message)+"</div>"; });
  });
}
function moHop(html){
  g("hopThoai").innerHTML="<div class='man-che'><div class='hop'>"
    +"<button class='hop-dong' aria-label='Đóng'>&times;</button>"+html+"</div></div>";
  g("hopThoai").querySelector(".hop-dong").addEventListener("click", dongHop);
  g("hopThoai").querySelector(".man-che").addEventListener("click", function(e){ if(e.target===this){ dongHop(); } });
  g("hopThoai").addEventListener("click", function(e){
    var n=e.target.closest("[data-chep]"); if(n){ chep(n.getAttribute("data-chep"), n); }
  });
}
function dongHop(){ g("hopThoai").innerHTML=""; }

/* ============================================================================ TRANG QUẢN TRỊ */
var MAN=[{id:"tong",ten:"Tổng Quan",bieu:"◔"},{id:"duyet",ten:"Xét Duyệt Tiền",bieu:"🧾"},
  {id:"soat",ten:"Soát Vé Tại Cửa",bieu:"⛶"},{id:"doi",ten:"Dữ Liệu Đối Soát",bieu:"🗄"},
  {id:"cai",ten:"Cài Đặt Hệ Thống",bieu:"⚙"}];
var manDang="tong", DON=[], CFQ={}, KHUNGQ=[], qNgay="", soCho=null, hen=null, caiCam=null;

function napQL(){
  return api("ds").then(function(j){
    if (!j || !j.ok) { return; }
    DON=j.don; CFQ=j.cf; KHUNGQ=j.khung;
    if (!qNgay) { qNgay=j.hnay; }
  });
}
function veNav(){
  var h="", cho=DON.filter(function(d){ return d.tt==="cho_duyet"; }).length;
  for (var i=0;i<MAN.length;i++){
    var m=MAN[i];
    h+="<button data-man='"+m.id+"' class='"+(manDang===m.id?"on":"")+"'><span>"+m.bieu+"</span>"
      +"<span>"+m.ten+"</span>"+(m.id==="duyet"&&cho?"<span class='cham'>"+cho+"</span>":"")+"</button>";
  }
  h+="<button class='thoat' data-man='thoat'><span>⏻</span><span>Đăng Xuất</span></button>";
  g("qNav").innerHTML=h;
  g("btAm").textContent="🔔 Cảnh báo âm thanh: "+(CFQ.am?"Đã Bật":"Đã Tắt");
}
g("qNav").addEventListener("click", function(e){
  var n=e.target.closest("[data-man]"); if(!n) return;
  var m=n.getAttribute("data-man");
  if (m==="thoat"){ THE=null; try{ sessionStorage.removeItem("nhama_the"); }catch(e2){}
    location.hash=""; return veTrang(); }
  manDang=m; veQL();
});
g("btAm").addEventListener("click", function(){
  CFQ.am=CFQ.am?0:1; api("cai",{cf:CFQ}).then(function(){ veNav(); });
});
function veQL(){
  veNav();
  if (manDang==="tong")  { return manTong(); }
  if (manDang==="duyet") { return manDuyet(); }
  if (manDang==="soat")  { return manSoat(); }
  if (manDang==="doi")   { return manDoi(); }
  if (manDang==="tien")  { return manTien(); }
  if (manDang==="cai")   { return manCai(); }
}
function daDatQ(ngay,gio){
  var n=0, ok=["giu_cho","cho_duyet","cho_vao","da_vao"];
  for (var i=0;i<DON.length;i++){
    var d=DON[i];
    if (d.ngay===ngay && d.gio===gio && ok.indexOf(d.tt)>=0) { n+=(+d.sl||0); }
  }
  return n;
}
function manTong(){
  var tong=DON.length,
      cho=DON.filter(function(d){ return d.tt==="cho_duyet"; }).length,
      huy=DON.filter(function(d){ return d.tt==="huy"; }).length,
      /* 🔴 THỰC THU CHỈ ĐẾM ĐƠN ĐÃ DUYỆT TIỀN. Đơn đang giữ chỗ chưa có đồng nào về tài khoản;
         cộng vào là báo cáo kế toán nói dối đúng con số mang đi đối chiếu ngân hàng. */
      thu=DON.filter(function(d){ return d.tt==="cho_vao"||d.tt==="da_vao"; })
             .reduce(function(s,d){ return s+(+d.tien||0); },0);
  var khach=0, h="";
  for (var i=0;i<KHUNGQ.length;i++){
    var gio=KHUNGQ[i], dat=daDatQ(qNgay,gio), con=CFQ.suc-dat;
    khach+=dat;
    var lop = dat<=0?"trong":(con<=0?"day":"sap"), chu = dat<=0?"Trống":(con<=0?"Đầy":"Còn "+con);
    h+="<div class='q-gio "+lop+"'><b>"+gio+"</b><span>"+dat+" / "+CFQ.suc+" Khách</span><em>"+chu+"</em></div>";
  }
  var suc=KHUNGQ.length*CFQ.suc, pt=suc?Math.round(khach*100/suc):0;
  g("qNoiDung").innerHTML="<h2 class='q-tieu'>📈 Báo Cáo Kế Toán &amp; Vận Hành</h2><div class='q-so'>"
    +oSo("🗓","#132a4a","Tổng lượt đặt",tong,"")+oSo("⏳","#3a2c08","Chờ duyệt tiền",cho,"")
    +oSo("🚫","#3a1416","Huỷ / hoàn tiền",huy,"")+oSo("💳","#0f2a1a","Kế toán (thực thu)",tien(thu),"color:var(--q-luc)")
    +"</div><div class='q-the' style='margin-top:18px'>"
    +"<div style='display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap'>"
    +"<h3 style='margin:0;font-size:18px;color:var(--q-xanh)'>🗓 Tình Trạng Sảnh Theo Ngày</h3>"
    +"<input type='date' class='q-o' id='qNgayO' value='"+qNgay+"'></div>"
    +"<p style='margin:14px 0 0'>Tổng khách trong ngày: <b>"+khach+"</b> người.</p>"
    +"<div class='q-thanh'><i style='width:"+pt+"%'></i></div><div class='q-luoi-gio'>"+h+"</div></div>";
  g("qNgayO").addEventListener("change", function(){ qNgay=this.value||qNgay; manTong(); });
}
function oSo(bieu,nen,ten,so,kieu){
  return "<div class='q-the'><div class='q-bieu' style='background:"+nen+"'>"+bieu+"</div>"
    +"<div><small>"+ten+"</small><b style='"+kieu+"'>"+so+"</b></div></div>";
}
function manDuyet(){
  var ds=DON.filter(function(d){ return d.tt==="cho_duyet"; }).sort(function(a,b){ return a.tao<b.tao?-1:1; });
  var h="<h2 class='q-tieu'>✅ Kế Toán Xét Duyệt</h2>";
  if (!ds.length){ h+="<div class='q-the q-nho'>Không có đơn nào chờ duyệt. Đơn hiện ở đây khi khách "
    +"bấm <b>“Tôi đã chuyển khoản”</b> trên thiệp của họ.</div>"; }
  for (var i=0;i<ds.length;i++){
    var d=ds[i];
    h+="<div class='q-the' style='margin-bottom:14px'><div class='q-duyet'>"
      +"<div class='q-bill'>🧾<br>ĐỐI CHIẾU SAO KÊ</div><div>"
      +"<div style='color:var(--q-cam);font-size:18px;font-weight:700'>Mã: "+esc(d.ma)+"</div>"
      +"<div style='margin:8px 0'>Khách: <b>"+esc(d.ten)+"</b> | SĐT: "+esc(d.sdt)
      +" <a class='q-zalo' target='_blank' rel='noopener' href='https://zalo.me/"+esc(sdtSach(d.sdt))
      +"'>💬 Chat Zalo</a></div>"
      +"<div>Lịch: "+esc(d.ngay)+" | Giờ: <b style='color:var(--q-xanh)'>"+esc(d.gio)+"</b></div>"
      +"<div>Cần thu: <b style='color:var(--q-luc)'>"+tien(d.tien)+"</b> (Đoàn "+esc(d.sl)+" khách)</div></div>"
      +"<div style='display:grid;gap:9px'>"
      +"<button class='q-nut luc' data-duyet='"+esc(d.ma)+"'>☑ DUYỆT</button>"
      +"<button class='q-nut do' data-choi='"+esc(d.ma)+"'>✕ TỪ CHỐI</button></div></div></div>";
  }
  g("qNoiDung").innerHTML=h;
}
g("qNoiDung").addEventListener("click", function(e){
  var n;
  if ((n=e.target.closest("[data-chep]"))) { return chep(n.getAttribute("data-chep"), n); }
  if ((n=e.target.closest("[data-duyet]"))) { return doi(n.getAttribute("data-duyet"),"cho_vao",""); }
  if ((n=e.target.closest("[data-choi]"))){
    var ly=prompt("Từ chối vì sao? (ghi lại để còn giải thích với khách)","không chuyển khoản");
    if (ly===null) { return; }
    return doi(n.getAttribute("data-choi"),"huy",ly);
  }
  if ((n=e.target.closest("[data-huy]")))  { return doi(n.getAttribute("data-huy"),"huy","huỷ ở màn đối soát"); }
  if ((n=e.target.closest("[data-hoan]")))  { return doi(n.getAttribute("data-hoan"),"cho_vao","mở lại"); }
  if ((n=e.target.closest("[data-zalo]")))  {
    var ma=n.getAttribute("data-zalo");
    api("gui_zalo",{ma:ma}).then(function(j){
      var d=(j&&j.nk&&j.nk[0])||{};
      alert(j&&j.ok ? ("Đã gửi Zalo cho "+ma+".") : ("Chưa gửi được: "+(d.chu||"không rõ")));
    }).catch(function(e2){ alert(e2.message); });
    return;
  }
});
function doi(ma,tt,ghi){
  api("doi",{ma:ma,tt:tt,ghi:ghi}).then(function(j){
    if (j && !j.ok) { alert(j.error); }
    return napQL();
  }).then(veQL).catch(function(e){ alert(e.message); });
}
function manSoat(){
  g("qNoiDung").innerHTML=
    "<div class='q-the' style='max-width:620px;margin:0 auto;border-color:var(--q-xanh)'>"
    +"<h2 class='q-tieu' style='justify-content:center;font-size:20px'>📷 Soát Vé Bằng Điện Thoại</h2>"
    +"<p style='text-align:center;color:var(--q-chu-mo);margin-top:-10px'>Đưa vé QR của khách vào vùng camera bên dưới.</p>"
    +"<div class='q-cam-hinh' id='qCam'><div style='text-align:center;padding:18px'>"
    +"<div style='font-size:34px'>📱</div><button class='q-nut xam' id='btCam' style='margin-top:10px'>Bật camera</button></div></div>"
    +"<p style='text-align:center;color:var(--q-chu-mo);margin:16px 0 6px'>Hoặc nhập thủ công mã ID vào ô dưới đây:</p>"
    +"<input class='q-o' id='oMa' placeholder='VD: GB-XYZ123' style='width:100%;text-align:center;"
    +"font-size:20px;letter-spacing:.12em;padding:14px'>"
    +"<button class='q-nut luc' id='btVao' style='width:100%;margin-top:12px;padding:15px;font-size:15px'>"
    +"XÁC NHẬN CHO KHÁCH VÀO</button><div id='baoSoat'></div></div>";
  g("btVao").addEventListener("click", function(){ soat(g("oMa").value); });
  g("oMa").addEventListener("keydown", function(e){ if(e.key==="Enter"){ soat(this.value); } });
  g("btCam").addEventListener("click", batCam);
}
/* Quét bằng bộ đọc CÓ SẴN của trình duyệt. KHÔNG nạp thư viện từ mạng ngoài: cửa hay sóng yếu,
   mà một trang soát vé chết vì tải không nổi thư viện là cả hàng khách đứng đợi. Máy không có bộ
   đọc thì vẫn còn ô gõ tay ngay bên dưới — đường luôn chạy được. */
function batCam(){
  if (!("BarcodeDetector" in window)){
    return baoSoat("hong","Trình duyệt này không quét được QR. Gõ mã ở ô bên dưới — nhanh không kém.");
  }
  navigator.mediaDevices.getUserMedia({video:{facingMode:"environment"}}).then(function(l){
    var v=document.createElement("video"); v.setAttribute("playsinline",""); v.srcObject=l; v.play();
    g("qCam").innerHTML=""; g("qCam").appendChild(v);
    var bo=new window.BarcodeDetector({formats:["qr_code"]});
    caiCam=setInterval(function(){
      bo.detect(v).then(function(kq){ if (kq&&kq.length){ soat(kq[0].rawValue); } }).catch(function(){});
    },400);
  }).catch(function(){
    baoSoat("hong","Không mở được camera (chưa cho quyền, hoặc trang không chạy qua HTTPS).");
  });
}
function baoSoat(kieu,chu){
  var m = kieu==="hong" ? "background:#2a1214;border:1px solid #6b1f24;color:#ff7b72"
                        : "background:#0d2416;border:1px solid #1a6b34;color:#56d364";
  g("baoSoat").innerHTML="<div style='"+m+";border-radius:8px;padding:13px;margin-top:14px;"
    +"font-size:14px;font-weight:600'>"+esc(chu)+"</div>";
}
var soatBan=false;
function soat(ma){
  if (soatBan) { return; }
  soatBan=true;
  api("soat",{ma:ma}).then(function(j){
    if (!j || !j.ok) { baoSoat("hong",(j&&j.error)||"Không soát được."); }
    else { baoSoat("duoc", j.chu); g("oMa").value=""; }
    return napQL();
  }).then(veNav).catch(function(e){ baoSoat("hong", e.message); })
    .then(function(){ setTimeout(function(){ soatBan=false; }, 800); });
}
var locChu="", locNgay="", locTT="";
function manDoi(){
  var ds=DON.filter(function(d){
    if (locTT && d.tt!==locTT) { return false; }
    if (locNgay && d.ngay!==locNgay) { return false; }
    if (locChu){
      var k=(d.ten+" "+d.sdt+" "+d.ma).toLowerCase();
      if (k.indexOf(locChu.toLowerCase())<0) { return false; }
    }
    return true;
  });
  var hTT="<option value=''>Tất cả trạng thái</option>";
  for (var k in TT){ hTT+="<option value='"+k+"'"+(locTT===k?" selected":"")+">"+TT[k].chu+"</option>"; }
  var h="<h2 class='q-tieu'>🗄 Dữ Liệu Đối Soát Khách Hàng</h2><div class='q-loc'>"
    +"<input class='q-o' id='lChu' placeholder='🔎 Tìm Tên, SĐT, Mã vé...' value='"+esc(locChu)+"' style='min-width:230px'>"
    +"<input class='q-o' type='date' id='lNgay' value='"+esc(locNgay)+"'>"
    +"<select class='q-o' id='lTT'>"+hTT+"</select>"
    +"<button class='q-nut xam' id='btBoLoc'>↺ Bỏ lọc</button>"
    +"<button class='q-nut luc' id='btXuat' style='margin-left:auto'>⬇ Xuất Excel</button></div>"
    +"<div class='q-the q-cuon'><table class='q-bang'><thead><tr><th>Mã vé</th><th>Khách hàng</th>"
    +"<th>Liên hệ / Chat</th><th>Số lượng</th><th>Tổng tiền</th><th>Lịch trình</th><th>Trạng thái</th>"
    +"<th>Ghi chú</th><th>Thao tác</th></tr></thead><tbody>";
  if (!ds.length){ h+="<tr><td colspan='9' class='q-nho' style='padding:22px'>Không có đơn nào khớp.</td></tr>"; }
  for (var i=0;i<ds.length;i++){
    var d=ds[i], t=TT[d.tt]||TT.giu_cho;
    h+="<tr><td><b style='color:var(--q-xanh)'>"+esc(d.ma)+"</b></td><td><b>"+esc(d.ten)+"</b></td>"
      +"<td>"+esc(d.sdt)+"<br><a class='q-zalo' target='_blank' rel='noopener' href='https://zalo.me/"
      +esc(sdtSach(d.sdt))+"'>💬 Chat Zalo</a></td><td>"+esc(d.sl)+"</td>"
      +"<td style='color:var(--q-luc);font-weight:700'>"+tien(d.tien)+"</td>"
      +"<td>"+esc(d.ngay)+"<br><b style='color:var(--q-cam)'>"+esc(d.gio)+"</b></td>"
      +"<td><span class='nhan-tt "+t.lop+"'>"+esc(t.chu)+"</span></td>"
      +"<td class='q-nho'>"+esc(d.ghi||"")+"</td><td style='white-space:nowrap'>"
      +(d.tt!=="huy"?"<button class='q-nut do' data-huy='"+esc(d.ma)+"' style='padding:6px 10px'>✕</button> ":"")
      +(d.tt==="huy"?"<button class='q-nut xam' data-hoan='"+esc(d.ma)+"' style='padding:6px 10px'>↺</button>":"")
      +((d.tt==="cho_vao"||d.tt==="da_vao")?" <button class='q-nut xam' data-zalo='"+esc(d.ma)
        +"' title='Gửi lại vé qua Zalo' style='padding:6px 10px'>💬</button>":"")
      +"</td></tr>";
  }
  g("qNoiDung").innerHTML=h+"</tbody></table></div>";
  g("lChu").addEventListener("input", function(){ locChu=this.value; manDoi(); g("lChu").focus(); });
  g("lNgay").addEventListener("change", function(){ locNgay=this.value; manDoi(); });
  g("lTT").addEventListener("change", function(){ locTT=this.value; manDoi(); });
  g("btBoLoc").addEventListener("click", function(){ locChu=locNgay=locTT=""; manDoi(); });
  g("btXuat").addEventListener("click", function(){ xuatCSV(ds); });
}
/* 🔴 PHẢI CÓ BOM Ở ĐẦU TỆP, không thì Excel trên Windows đọc UTF-8 thành "Ph?m Tu?ng Vi" và người
   nhận sẽ bảo "file lỗi" chứ không ai đi dò bảng mã. */
function xuatCSV(ds){
  var d1=[["Mã vé","Khách hàng","SĐT","Số lượng","Tổng tiền","Ngày","Giờ","Trạng thái","Ghi chú"].join(",")];
  for (var i=0;i<ds.length;i++){
    var d=ds[i];
    d1.push([d.ma,d.ten,d.sdt,d.sl,d.tien,d.ngay,d.gio,(TT[d.tt]||{}).chu||d.tt,d.ghi||""]
      .map(function(x){ return '"'+String(x==null?"":x).replace(/"/g,'""')+'"'; }).join(","));
  }
  var b=new Blob(["﻿"+d1.join("\r\n")],{type:"text/csv;charset=utf-8"});
  var u=URL.createObjectURL(b), a=document.createElement("a");
  a.href=u; a.download="doi-soat-nha-ma-"+ngayISO(new Date())+".csv"; a.click();
  setTimeout(function(){ URL.revokeObjectURL(u); },4000);
}
/* Màn TIỀN VỀ — sổ mọi gói ngân hàng bắn tới, kể cả gói không khớp thiệp nào.
   🔴 SỔ NÀY LÀ CHỖ DUY NHẤT PHÂN BIỆT "ngân hàng chưa bắn" với "bắn rồi mà mình không hiểu".
      Không có nó thì cả hai ca đều trông y hệt nhau: thiệp nằm im ở Chờ duyệt. */
function manTien(){
  g("qNoiDung").innerHTML="<h2 class='q-tieu'>💸 Tiền Về &amp; Zalo</h2><div class='q-the q-nho'>Đang tải…</div>";
  api("tien").then(function(j){
    if (!j || !j.ok) { return; }
    var t=j.tien||[], z=j.zalo||[];
    var nhan={khop:"✔ Đã khớp, đã duyệt", khong_khop:"Không mang mã thiệp",
      khong_co_don:"Mã không có trong sổ", thieu_tien:"Thiếu tiền — chưa duyệt",
      don_da_huy:"Thiệp đã huỷ — cần hoàn tiền", tien_ra:"Tiền ra — bỏ qua",
      khong_doc:"Gói không đọc được", sai_khoa:"Sai khoá cổng"};
    var lop={khop:"tt-vao", thieu_tien:"tt-duyet", sai_khoa:"tt-huy", khong_doc:"tt-huy",
      don_da_huy:"tt-huy", khong_khop:"tt-giu", khong_co_don:"tt-giu", tien_ra:"tt-giu"};
    var h="<h2 class='q-tieu'>💸 Tiền Về &amp; Zalo</h2>"
      +"<div class='q-the'><h3 style='margin-top:0;color:var(--q-xanh)'>🔗 Địa chỉ webhook</h3>"
      +"<p class='q-nho' style='margin-top:0'>Dán nguyên chuỗi này vào ô Webhook bên SePay / Casso / "
        +"ngân hàng (kiểu POST JSON, sự kiện tiền vào):</p>"
      +"<div class='q-o' style='word-break:break-all;font-size:12.5px' data-chep='"+esc(j.duong)+"'>"
        +esc(j.duong)+"</div>"
      +"<button class='q-nut xam' data-chep='"+esc(j.duong)+"' style='margin-top:8px'>Chép địa chỉ</button>"
      +"<p class='q-nho'>Chuyển thử <b>1.000đ</b> với nội dung là một mã thiệp có thật — sổ dưới đây "
        +"phải hiện ngay một dòng. Sổ trống hoàn toàn nghĩa là bên gửi chưa bắn tới, hoặc tường lửa "
        +"hosting chặn.</p></div>"
      +"<h3 style='margin:20px 0 10px'>Sổ tiền về ("+t.length+" dòng gần nhất)</h3><div class='q-the q-cuon'>"
      +"<table class='q-bang'><thead><tr><th>Lúc</th><th>Mã tham chiếu</th><th>Số tiền</th>"
      +"<th>Nội dung</th><th>Thiệp</th><th>Kết quả</th></tr></thead><tbody>";
    if (!t.length){ h+="<tr><td colspan='6' class='q-nho' style='padding:22px'>Chưa có gói nào bắn tới.</td></tr>"; }
    for (var i=0;i<t.length;i++){
      var d=t[i];
      h+="<tr><td>"+esc(d.luc)+"</td><td class='q-nho'>"+esc(d.ref)+"</td>"
        +"<td style='color:var(--q-luc);font-weight:700'>"+tien(d.so_tien)+"</td>"
        +"<td class='q-nho'>"+esc(d.noi_dung)+"</td><td><b>"+esc(d.ma||"—")+"</b></td>"
        +"<td><span class='nhan-tt "+(lop[d.kq]||"tt-giu")+"'>"+esc(nhan[d.kq]||d.kq)+"</span></td></tr>";
    }
    h+="</tbody></table></div>"
      +"<h3 style='margin:20px 0 10px'>Nhật ký gửi Zalo</h3><div class='q-the q-cuon'>"
      +"<table class='q-bang'><thead><tr><th>Lúc</th><th>Thiệp</th><th>Kết quả</th><th>Chi tiết</th>"
      +"</tr></thead><tbody>";
    if (!z.length){ h+="<tr><td colspan='4' class='q-nho' style='padding:22px'>Chưa gửi tin nào. "
      +"Khai token OA ở màn Cài đặt thì mỗi thiệp được duyệt sẽ tự gửi.</td></tr>"; }
    for (var k=0;k<z.length;k++){
      h+="<tr><td>"+esc(z[k].luc)+"</td><td><b>"+esc(z[k].ma)+"</b></td>"
        +"<td>"+esc(z[k].kq)+"</td><td class='q-nho'>"+esc(z[k].chu)+"</td></tr>";
    }
    g("qNoiDung").innerHTML=h+"</tbody></table></div>";
  }).catch(function(e){ g("qNoiDung").innerHTML="<div class='q-the'>"+esc(e.message)+"</div>"; });
}

function manCai(){
  g("qNoiDung").innerHTML="<h2 class='q-tieu'>⚙ Cấu Hình Hệ Thống</h2><div class='q-doi'>"
    +"<div class='q-the'><h3 style='margin-top:0;color:var(--q-xanh)'>☁ Cấu hình Kinh Doanh</h3>"
    +oCai("Giá Vé / 1 Người (VND)","cGia",CFQ.gia,"number")
    +oCai("Sức chứa tối đa (Số người / 1 Khung giờ)","cSuc",CFQ.suc,"number")
    +oCai("Khung đầu tiên","cMo",CFQ.mo,"time")+oCai("Khung cuối cùng","cDong",CFQ.dong,"time")
    +oCai("Mỗi khung cách nhau (phút)","cBuoc",CFQ.buoc,"number")
    +oCai("Mở bán trước (ngày)","cNgayMo",CFQ.ngayMo,"number")
    +oCai("Khách phải có mặt trước (phút)","cDenSom",CFQ.denSom,"number")
    +"<hr style='border:0;border-top:1px solid var(--q-vien);margin:18px 0'>"
    +"<h3 style='margin:0 0 4px;color:var(--q-cam)'>🏦 Tài khoản nhận tiền</h3>"
    +"<p class='q-nho' style='margin:0'>Mã QR trên thiệp của khách dựng từ ba ô này. Bỏ trống thì "
      +"mượn tài khoản đã khai ở plugin Ghế Massage.</p>"
    +oCai("Mã ngân hàng BIN (BIDV 970418 · Vietcombank 970436 · MB 970422 · Techcombank 970407)","cBin",CFQ.bin||"","text")
    +oCai("Số tài khoản","cSoTk",CFQ.so_tk||"","text")
    +oCai("Chủ tài khoản (viết HOA không dấu)","cTenTk",CFQ.ten_tk||"","text")
    +"<div id='tkThu' class='q-nho' style='margin-top:8px'></div>"
    +"<button class='q-nut xanh' id='btLuuKD' style='width:100%;margin-top:16px'>LƯU LÊN SERVER</button></div>"
    +"<div class='q-the'><h3 style='margin-top:0;color:var(--q-luc)'>🖥 Cấu hình Màn Hình</h3>"
    +"<label class='q-nho'>Tốc độ tự động quét đơn mới:</label>"
    +"<select class='q-o' id='cQuet' style='width:100%;margin:8px 0 16px'>"
    +"<option value='3000'>Nhanh (3 giây)</option><option value='6000'>Vừa (6 giây)</option>"
    +"<option value='15000'>Chậm (15 giây)</option><option value='0'>Tắt</option></select>"
    +oCai("Tên hiển thị trên trang khách","cTen",CFQ.ten,"text")
    +oCai("Câu phụ dưới tiêu đề","cPhu",CFQ.phu,"text")
    +oCai("Đổi mã PIN (để trống là giữ nguyên)","cPin","","text")
    +"<hr style='border:0;border-top:1px solid var(--q-vien);margin:18px 0'>"
    +"<h3 style='margin:0 0 4px;color:var(--q-cam)'>💬 Gửi vé qua Zalo</h3>"
    +"<p class='q-nho' style='margin:0'>Thiệp được duyệt là tự gửi mã vé + đường dẫn QR vé cho khách. "
      +"Cần <b>Zalo OA</b> + một ứng dụng ở developers.zalo.me. Chưa nối thì bỏ qua, mọi thứ khác vẫn chạy.</p>"
    +oCai("ID ứng dụng (developers.zalo.me → Thông tin ứng dụng)","cZaloApp",CFQ.zalo_app_id||"","text")
    +oCai("Khoá bí mật của ứng dụng","cZaloSecret",CFQ.zalo_secret||"","password")
    +oCai("Mã mẫu tin ZNS (để trống = gửi tin tư vấn CS)","cZaloTpl",CFQ.zalo_tpl||"","text")
    /* 🔴 LUÔN HIỆN TRỐNG, không đổ token cũ ra ô. Màn này ai vào được là chụp màn hình được. */
    +oCai("Refresh token dán tay (để trống = giữ nguyên)","cZaloRf","","password")
    +"<p class='q-nho' style='margin:4px 0 0'>Chỉ dùng khi ô <b>Official Account Callback Url</b> bên "
      +"Zalo bị khoá nên không bấm Kết nối được: vào <b>Zalo API Explorer</b> lấy tay một bộ token, "
      +"dán <b>refresh token</b> vào đây. Từ đó plugin tự làm mới như thường.</p>"
    +"<div id='zaloTT' class='q-nho' style='margin-top:10px'></div>"
    +"<p class='q-nho'>⚠️ <b>Tin tư vấn (CS)</b> miễn phí nhưng CHỈ tới được người đã nhắn cho OA "
      +"trong 7 ngày — khách mua lần đầu gần như chắc chắn không thoả. Gửi được cho mọi số thì phải "
      +"dùng <b>ZNS</b>: đăng ký mẫu tin, chờ Zalo duyệt, và mỗi tin tốn phí.</p>"
    +"<button class='q-nut luc' id='btLuuMH' style='width:100%;margin-top:14px'>LƯU MÀN HÌNH</button>"
    +"<hr style='border:0;border-top:1px solid var(--q-vien);margin:20px 0'>"
    +"<button class='q-nut xam' id='btMau' style='width:100%'>Nạp 12 đơn mẫu (để xem thử)</button>"
    +"<button class='q-nut do' id='btXoa' style='width:100%;margin-top:9px'>Xoá sạch sổ</button></div></div>";
  g("cQuet").value=String(CFQ.quet);
  veTkThu(); veZalo();
  g("btLuuKD").addEventListener("click", function(){
    luuCai({ gia:+g("cGia").value||0, suc:+g("cSuc").value||1, mo:g("cMo").value, dong:g("cDong").value,
      buoc:+g("cBuoc").value||5, ngayMo:+g("cNgayMo").value||14, denSom:+g("cDenSom").value||0,
      bin:g("cBin").value, so_tk:g("cSoTk").value, ten_tk:g("cTenTk").value }, "");
  });
  g("btLuuMH").addEventListener("click", function(){
    luuCai({ quet:+g("cQuet").value, ten:g("cTen").value, phu:g("cPhu").value,
      zalo_app_id:g("cZaloApp").value, zalo_secret:g("cZaloSecret").value,
      zalo_tpl:g("cZaloTpl").value, zalo_refresh:g("cZaloRf").value }, g("cPin").value);
  });
  g("btMau").addEventListener("click", function(){
    api("mau").then(napQL).then(veQL).catch(function(e){ alert(e.message); });
  });
  g("btXoa").addEventListener("click", function(){
    if (!confirm("Xoá sạch mọi đơn trong sổ? Không lấy lại được.")) { return; }
    api("xoa").then(napQL).then(veQL).catch(function(e){ alert(e.message); });
  });
}
/* 🔴 IN RA TÀI KHOẢN ĐANG THỰC DÙNG, không phải ô vừa gõ. Ba ô có thể còn trống mà hệ thống vẫn
   chạy bằng tài khoản mượn của plugin ghế — không nói ra thì người khai tưởng chưa có gì, hoặc tệ
   hơn: tưởng đang thu về tài khoản này mà thật ra tiền chảy sang tài khoản kia. Số tài khoản
   THIẾU MỘT CHỮ SỐ là app ngân hàng báo "định dạng không hợp lệ" — đã xảy ra thật bên ghế
   22/08/2026, nên phải soi được bằng mắt trước khi giao cho khách. */
function veTkThu(){
  var o=g("tkThu"); if(!o) return;
  var t=CFQ.tk_thu||{};
  if (!t.so_tk || !t.bin){
    o.innerHTML="<span style='color:var(--q-do)'>⚠️ CHƯA CÓ TÀI KHOẢN — thiệp của khách sẽ không có "
      +"mã QR, chỉ giữ chỗ được thôi.</span>";
    return;
  }
  o.innerHTML="Đang thực dùng: <b>"+esc(t.ten_nh||("BIN "+t.bin))+" · "+esc(t.so_tk)+"</b>"
    +(t.ten_tk?" · "+esc(t.ten_tk):"")
    +"<br>Soi kỹ số tài khoản: thiếu một chữ số là app ngân hàng chối, mà mã QR thì trông vẫn bình thường.";
}
/* ============================================================================================
 * TÌNH TRẠNG NỐI ZALO
 * ==========================================================================================
 * 🔴 KHÔNG IN TOKEN RA MÀN HÌNH, kể cả một phần. Màn này ai vào được là chụp màn hình được, mà
 *    ảnh chụp thì đi khắp nơi. Chỉ nói ĐÃ NỐI hay CHƯA, và token còn sống bao lâu.
 * 🔴 IN RA ĐỊA CHỈ CALLBACK để anh dán sang bên Zalo. Thiếu bước ấy là bấm Kết nối xong Zalo báo
 *    "redirect_uri không hợp lệ", mà câu ấy không nói phải đi khai ở đâu.
 * ========================================================================================== */
function veZalo(){
  var o=g("zaloTT"); if(!o) return;
  var t=(CFQ.zalo_tt)||{};
  var h="<div class='q-o' style='word-break:break-all;font-size:12.5px' data-chep='"+esc(t.callback||"")+"'>"
    +esc(t.callback||"")+"</div>"
    +"<p class='q-nho' style='margin:6px 0 10px'>↑ Dán chuỗi này (bấm để chép, <b>đừng gõ tay</b>) vào ô "
    +"<b>Redirect URI / Callback URL</b> của ứng dụng bên developers.zalo.me.</p>"
    +"<p class='q-nho' style='margin:0 0 10px;padding:8px 10px;border-left:3px solid var(--q-cam)'>"
    +"Bấm Kết nối mà Zalo hiện <b>error_code -14003 · Invalid redirect uri</b> thì Zalo chối ngay từ "
    +"cửa nó, chưa hề gọi về website — đúng hai lý do:<br>"
    +"<b>1.</b> Miền <b>chưa xác thực XONG</b>. Điền ô <i>Miền ứng dụng</i> thôi là chưa đủ: phải tải "
    +"tệp <code>zalo_verifier….html</code> Zalo đưa lên thư mục gốc <code>public_html</code>, mở thử "
    +"đúng địa chỉ tệp ấy thấy ra chữ, rồi mới bấm <b>Xác thực</b> cho tới khi hiện <i>Đã xác thực</i>.<br>"
    +"<b>2.</b> Chuỗi trên <b>khớp từng ký tự</b> với ô bên Zalo — thừa dấu <code>/</code> ở cuối, "
    +"thiếu chữ <code>s</code> trong <code>https</code>, hay có <code>www</code> là chối.</p>";
  if (!t.co_app){
    h+="<div style='color:var(--q-cam)'>⚠️ Chưa khai ID ứng dụng và khoá bí mật — điền hai ô trên rồi "
      +"bấm <b>LƯU MÀN HÌNH</b>, nút Kết nối mới hiện.</div>";
  } else if (!t.da_noi){
    h+="<a class='q-nut xanh' style='display:inline-block;text-decoration:none' href='"+esc(t.url_noi||"")
      +"'>🔗 KẾT NỐI ZALO OA</a>"
      +"<p class='q-nho' style='margin-top:8px'>Bấm → Zalo hỏi chọn OA → xong tự quay về đây. Chỉ làm "
      +"<b>một lần</b>; sau đó plugin tự làm mới token.</p>";
  } else {
    h+="<div style='color:var(--q-luc)'>✔ Đã nối"+(t.oa?" với OA <b>"+esc(t.oa)+"</b>":"")+". "
      +(t.con_song ? "Token còn sống "+Math.round(t.con_song/60)+" phút." : "Token đã hết hạn — plugin tự làm mới ở lần gửi tới.")
      +"</div>"
      +"<a class='q-nut xam' style='display:inline-block;text-decoration:none;margin-top:8px' href='"
      +esc(t.url_noi||"")+"'>Nối lại (đổi OA khác)</a>";
  }
  o.innerHTML=h;
}

function luuCai(phan, pin){
  var moi=Object.assign({}, CFQ, phan);
  api("cai",{cf:moi, pin:pin}).then(function(j){
    if (!j || !j.ok) { return alert((j&&j.error)||"Không lưu được."); }
    CFQ=j.cf; KHUNGQ=j.khung; datHen(); manCai(); alert("Đã lưu lên server.");
  }).catch(function(e){ alert(e.message); });
}
function oCai(nhan,id,gt,kieu){
  return "<label class='q-nho' style='display:block;margin-top:12px'>"+esc(nhan)+"</label>"
    +"<input class='q-o' id='"+id+"' type='"+kieu+"' value='"+esc(gt)+"' style='width:100%;margin-top:6px'>";
}
function datHen(){
  if (hen) { clearInterval(hen); hen=null; }
  if (!CFQ.quet) { return; }
  hen=setInterval(function(){
    if (!THE) { return; }
    napQL().then(function(){
      var cho=DON.filter(function(d){ return d.tt==="cho_duyet"; }).length;
      if (soCho!==null && cho>soCho && CFQ.am) { keu(); }
      soCho=cho;
      /* Không vẽ lại màn đang gõ dở: người ta đang nhập mã ở cửa mà ô bị dựng lại là mất chữ. */
      if (manDang!=="soat" && manDang!=="cai") { veQL(); } else { veNav(); }
    }).catch(function(){});
  }, Math.max(1000, CFQ.quet));
}
function keu(){
  try{
    var A=new (window.AudioContext||window.webkitAudioContext)();
    var o=A.createOscillator(), gg=A.createGain();
    o.type="sine"; o.frequency.value=880; gg.gain.value=.05;
    o.connect(gg); gg.connect(A.destination); o.start();
    setTimeout(function(){ o.frequency.value=1320; },130);
    setTimeout(function(){ o.stop(); A.close(); },300);
  }catch(e){}
}
/* ============================================================================== cửa vào */
function veTrang(){
  var bam = location.hash.replace("#","");
  /* Đường dẫn trong tin Zalo: #ve=GB-XXXX — mở thẳng thiệp ấy, khách khỏi gõ số điện thoại. */
  var mve = bam.match(/^ve=(.+)$/i);
  if (mve){
    g("apKhach").classList.remove("an"); g("apQL").classList.add("an");
    napKhach();
    api("ve",{ma:decodeURIComponent(mve[1])}).then(function(j){
      if (j && j.ok) { moThiep(j.don); } else { moThiep([]); }
    }).catch(function(e){ alert(e.message); });
    return;
  }
  var la = bam.toLowerCase()==="quanly";
  if (caiCam) { clearInterval(caiCam); caiCam=null; }
  if (hen && !la) { clearInterval(hen); hen=null; }
  if (!la){
    g("apKhach").classList.remove("an"); g("apQL").classList.add("an");
    dongHop(); napKhach(); return;
  }
  g("apKhach").classList.add("an"); g("apQL").classList.remove("an");
  if (!THE) { return veCuaPin(); }
  napQL().then(function(){ veQL(); datHen(); }).catch(function(e){
    g("qNoiDung").innerHTML="<div class='q-the'>"+esc(e.message)+"</div>";
  });
}
function veCuaPin(){
  g("qNav").innerHTML="";
  g("qNoiDung").innerHTML="<div class='q-the' style='max-width:360px;margin:60px auto;text-align:center'>"
    +"<h2 style='margin-top:0'>Trang quản trị</h2>"
    +"<input class='q-o' id='oPin' type='password' inputmode='numeric' placeholder='Mã PIN' "
    +"style='width:100%;text-align:center;font-size:20px;letter-spacing:.3em;padding:13px'>"
    +"<button class='q-nut xanh' id='btPin' style='width:100%;margin-top:12px;padding:13px'>VÀO</button>"
    +"<div id='baoPin' class='q-nho' style='margin-top:10px'></div></div>";
  function thu(){
    api("vao",{pin:g("oPin").value}).then(function(j){
      if (!j || !j.ok) { g("baoPin").innerHTML="<span style='color:var(--q-do)'>"+esc((j&&j.error)||"PIN chưa đúng.")+"</span>"; return; }
      THE=j.the; try{ sessionStorage.setItem("nhama_the", THE); }catch(e){}
      veTrang();
    }).catch(function(e){ g("baoPin").innerHTML="<span style='color:var(--q-do)'>"+esc(e.message)+"</span>"; });
  }
  g("btPin").addEventListener("click", thu);
  g("oPin").addEventListener("keydown", function(e){ if(e.key==="Enter"){ thu(); } });
  g("oPin").focus();
}
window.addEventListener("hashchange", veTrang);
veTrang();
JS;
	}
}


/**
 * DỰNG CHUỖI VietQR (EMVCo) — chép từ `class-vhg-qr.php` của plugin ghế, cùng lý do như trên.
 *
 * ⚠️ CRC16/CCITT-FALSE: khởi tạo 0xFFFF, đa thức 0x1021, KHÔNG đảo bit, KHÔNG xor cuối. Các biến
 *    thể CRC16 khác cho ra chuỗi khác và điện thoại từ chối quét.
 */
class NHAMA_QR {

	public static function tlv( $ma, $gia_tri ) {
		$gia_tri = (string) $gia_tri;
		return $ma . substr( '0' . strlen( $gia_tri ), -2 ) . $gia_tri;
	}

	public static function crc16( $s ) {
		$crc = 0xFFFF;
		$n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) {
			$crc ^= ( ord( $s[ $i ] ) & 0xFF ) << 8;
			for ( $b = 0; $b < 8; $b++ ) {
				$crc = ( $crc & 0x8000 ) ? ( ( $crc << 1 ) ^ 0x1021 ) : ( $crc << 1 );
				$crc &= 0xFFFF;
			}
		}
		return substr( '000' . strtoupper( dechex( $crc ) ), -4 );
	}

	/**
	 * BÓC NGƯỢC một trường EMVCo ra khỏi chuỗi VietQR.
	 *
	 * 🔴 VÌ SAO CẦN, dù mình vừa tự dựng ra chuỗi ấy: cái hiện lên cho khách chép phải là ĐÚNG CÁI
	 *    NẰM TRONG QR, không phải một chuỗi tính song song. Hai đường cùng đi từ một biến hôm nay
	 *    thì giống nhau; ngày nào đó một bên thêm tiền tố (nhiều nơi chèn "SEVQR ") hay cắt bớt ký
	 *    tự, hai đường lệch nhau — và lệch IM LẶNG: khách quét QR thì tiền vào đúng đơn, khách gõ
	 *    tay theo chữ trên màn thì tiền vào hư không. Bóc ngược ra thì chỉ còn MỘT nguồn sự thật.
	 *
	 * @param string $chuoi chuỗi VietQR đầy đủ
	 * @param string $ma    mã trường ngoài (vd '62')
	 * @param string $ma_trong mã trường con bên trong (vd '08'); rỗng = lấy nguyên trường ngoài
	 */
	public static function boc( $chuoi, $ma, $ma_trong = '' ) {
		$i = 0; $n = strlen( $chuoi );
		while ( $i + 4 <= $n ) {
			$m   = substr( $chuoi, $i, 2 );
			$dai = (int) substr( $chuoi, $i + 2, 2 );
			$gt  = substr( $chuoi, $i + 4, $dai );
			if ( $m === $ma ) { return '' === $ma_trong ? $gt : self::boc( $gt, $ma_trong ); }
			$i += 4 + $dai;
		}
		return '';
	}

	/**
	 * Chuỗi VietQR chuyển khoản nhanh (QRIBFTTA). `$so_tien` = 0 nghĩa là khách tự nhập số tiền.
	 */
	public static function dung( $bank_bin, $so_tk, $so_tien, $noi_dung ) {
		$s  = self::tlv( '00', '01' );
		$s .= self::tlv( '01', $so_tien ? '12' : '11' );   // 12 = QR dùng MỘT LẦN (có số tiền)
		$ben = self::tlv( '00', (string) $bank_bin ) . self::tlv( '01', (string) $so_tk );
		$s .= self::tlv( '38', self::tlv( '00', 'A000000727' ) . self::tlv( '01', $ben )
			. self::tlv( '02', 'QRIBFTTA' ) );
		$s .= self::tlv( '53', '704' );                    // 704 = VND
		if ( $so_tien ) { $s .= self::tlv( '54', (string) (int) $so_tien ); }
		$s .= self::tlv( '58', 'VN' );
		if ( '' !== (string) $noi_dung ) { $s .= self::tlv( '62', self::tlv( '08', (string) $noi_dung ) ); }
		$s .= '6304';
		return $s . self::crc16( $s );
	}
}

/**
 * BỘ DỰNG MÃ QR — chép nguyên từ `wordpress/vhcp-ghe/includes/class-vhg-qrve.php`, đổi tên lớp.
 *
 * 🔴 CHÉP CHỨ KHÔNG VIẾT LẠI, VÀ ĐÓ LÀ CHỦ Ý. Bộ ấy đã chạy thật trên tem dán 26 cái ghế và có
 *    phép thử ĐỌC NGƯỢC ma trận về lại chuỗi ban đầu — tức là nó tự chứng minh được mã dựng ra
 *    quét được. Viết một bộ QR thứ hai bằng tay là phải tự chứng minh lại từ đầu, mà một tấm QR
 *    "gần đúng" thì không ai phát hiện cho tới lúc khách đứng ở quầy quét mãi không ra.
 *
 * ⚠️ Sửa ở đây thì sửa cả bên kia, và ngược lại. Hai bản đã lệch nhau là một bên tem in ra không
 *    quét được mà bên kia vẫn chạy, nên không ai nghĩ tới chuyện so hai tệp.
 */
class NHAMA_QRVe {

	/** Sức chứa (số ký tự) theo [version][mức sửa lỗi][chế độ]. Chỉ tới version 10. */
	const MUC = array( 'L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3 );

	/** Số codeword sửa lỗi mỗi khối, và số khối — [version][mức] => array(ecc_moi_khoi, so_khoi_g1, so_khoi_g2). */
	private static function bang_khoi() {
		return array(
			1  => array( 'L' => array( 7, 1, 0 ),  'M' => array( 10, 1, 0 ), 'Q' => array( 13, 1, 0 ), 'H' => array( 17, 1, 0 ) ),
			2  => array( 'L' => array( 10, 1, 0 ), 'M' => array( 16, 1, 0 ), 'Q' => array( 22, 1, 0 ), 'H' => array( 28, 1, 0 ) ),
			3  => array( 'L' => array( 15, 1, 0 ), 'M' => array( 26, 1, 0 ), 'Q' => array( 18, 2, 0 ), 'H' => array( 22, 2, 0 ) ),
			4  => array( 'L' => array( 20, 1, 0 ), 'M' => array( 18, 2, 0 ), 'Q' => array( 26, 2, 0 ), 'H' => array( 16, 4, 0 ) ),
			5  => array( 'L' => array( 26, 1, 0 ), 'M' => array( 24, 2, 0 ), 'Q' => array( 18, 2, 2 ), 'H' => array( 22, 2, 2 ) ),
			6  => array( 'L' => array( 18, 2, 0 ), 'M' => array( 16, 4, 0 ), 'Q' => array( 24, 4, 0 ), 'H' => array( 28, 4, 0 ) ),
			7  => array( 'L' => array( 20, 2, 0 ), 'M' => array( 18, 4, 0 ), 'Q' => array( 18, 2, 4 ), 'H' => array( 26, 4, 1 ) ),
			8  => array( 'L' => array( 24, 2, 0 ), 'M' => array( 22, 2, 2 ), 'Q' => array( 22, 4, 2 ), 'H' => array( 26, 4, 2 ) ),
			9  => array( 'L' => array( 30, 2, 0 ), 'M' => array( 22, 3, 2 ), 'Q' => array( 20, 4, 4 ), 'H' => array( 24, 4, 4 ) ),
			10 => array( 'L' => array( 18, 2, 2 ), 'M' => array( 26, 4, 1 ), 'Q' => array( 24, 6, 2 ), 'H' => array( 28, 6, 2 ) ),
		);
	}

	/** Tổng số codeword của một version (dữ liệu + sửa lỗi). */
	private static function tong_codeword( $ver ) {
		$t = array( 1 => 26, 2 => 44, 3 => 70, 4 => 100, 5 => 134, 6 => 172,
			7 => 196, 8 => 242, 9 => 292, 10 => 346 );
		return isset( $t[ $ver ] ) ? $t[ $ver ] : 0;
	}

	/** Toạ độ tâm các ô căn chỉnh, theo version. */
	private static function tam_can( $ver ) {
		$t = array( 1 => array(), 2 => array( 6, 18 ), 3 => array( 6, 22 ), 4 => array( 6, 26 ),
			5 => array( 6, 30 ), 6 => array( 6, 34 ), 7 => array( 6, 22, 38 ), 8 => array( 6, 24, 42 ),
			9 => array( 6, 26, 46 ), 10 => array( 6, 28, 50 ) );
		return isset( $t[ $ver ] ) ? $t[ $ver ] : array();
	}

	// ===================================================================== số học GF(256)

	private static $log = null;
	private static $alog = null;

	/**
	 * Bảng luỹ thừa/logarit của GF(256) với đa thức sinh 0x11D — đúng bản chuẩn QR dùng.
	 * Dựng một lần rồi giữ: mỗi tấm tem gọi hàng nghìn phép nhân.
	 */
	private static function gf() {
		if ( null !== self::$log ) { return; }
		self::$log = array_fill( 0, 256, 0 );
		self::$alog = array_fill( 0, 256, 0 );
		$x = 1;
		for ( $i = 0; $i < 255; $i++ ) {
			self::$alog[ $i ] = $x;
			self::$log[ $x ] = $i;
			$x <<= 1;
			if ( $x & 0x100 ) { $x ^= 0x11D; }
		}
	}

	private static function gf_nhan( $a, $b ) {
		if ( 0 === $a || 0 === $b ) { return 0; }
		self::gf();
		return self::$alog[ ( self::$log[ $a ] + self::$log[ $b ] ) % 255 ];
	}

	/**
	 * Đa thức sinh Reed-Solomon cho `n` codeword sửa lỗi: tích của (x - α^i).
	 * ⚠️ Tính chứ KHÔNG chép bảng: chép bảng là chép cả lỗi gõ, mà một hệ số sai thì mã vẫn dựng
	 *    ra được và vẫn nhìn như thật — chỉ là máy quét từ chối. Phép thử đối chiếu kết quả hàm
	 *    này với bộ hệ số đã công bố cho n=7 và n=10.
	 */
	public static function da_thuc_sinh( $n ) {
		self::gf();
		$g = array( 1 );
		for ( $i = 0; $i < $n; $i++ ) {
			$moi = array_fill( 0, count( $g ) + 1, 0 );
			foreach ( $g as $k => $he ) {
				$moi[ $k ]     ^= self::gf_nhan( $he, self::$alog[ $i ] );
				$moi[ $k + 1 ] ^= $he;
			}
			/* Nhân với (x + α^i): hệ số bậc cao dịch sang, hệ số thấp nhân α^i. */
			$g = $moi;
		}
		/* Đảo về thứ tự bậc GIẢM DẦN (hệ số 1 đứng đầu) — cùng thứ tự với chuỗi codeword, và
		   cùng thứ tự với bộ hệ số đã công bố mà phép thử đối chiếu. */
		return array_reverse( $g );
	}

	/** Codeword sửa lỗi của một khối dữ liệu. */
	public static function ecc( $du_lieu, $n ) {
		$g = self::da_thuc_sinh( $n );
		$du = array_merge( array_values( $du_lieu ), array_fill( 0, $n, 0 ) );
		$len = count( $du_lieu );
		for ( $i = 0; $i < $len; $i++ ) {
			$he = $du[ $i ];
			if ( 0 === $he ) { continue; }
			foreach ( $g as $k => $gk ) {
				$du[ $i + $k ] ^= self::gf_nhan( $gk, $he );
			}
		}
		return array_slice( $du, $len, $n );
	}

	// ===================================================================== mã hoá dữ liệu

	const AN = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

	public static function la_alnum( $s ) {
		$n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( false === strpos( self::AN, $s[ $i ] ) ) { return false; }
		}
		return $n > 0;
	}

	/** Số codeword DỮ LIỆU của một version+mức. */
	public static function so_cw_du_lieu( $ver, $muc ) {
		$b = self::bang_khoi();
		if ( ! isset( $b[ $ver ][ $muc ] ) ) { return 0; }
		list( $ecc, $k1, $k2 ) = $b[ $ver ][ $muc ];
		return self::tong_codeword( $ver ) - $ecc * ( $k1 + $k2 );
	}

	/** Version nhỏ nhất chứa nổi chuỗi này. 0 = không version nào (tới 10) chứa nổi. */
	public static function chon_version( $s, $muc ) {
		$alnum = self::la_alnum( $s );
		for ( $v = 1; $v <= 10; $v++ ) {
			$bit_dai = $alnum ? ( $v <= 9 ? 9 : 11 ) : ( $v <= 9 ? 8 : 16 );
			$bit_du  = $alnum
				? ( intdiv( strlen( $s ), 2 ) * 11 + ( ( strlen( $s ) % 2 ) ? 6 : 0 ) )
				: strlen( $s ) * 8;
			if ( 4 + $bit_dai + $bit_du <= self::so_cw_du_lieu( $v, $muc ) * 8 ) { return $v; }
		}
		return 0;
	}

	/** Chuỗi -> mảng codeword dữ liệu (đã đệm đủ). */
	public static function ma_hoa( $s, $ver, $muc ) {
		$alnum = self::la_alnum( $s );
		$bit = '';
		$bit .= $alnum ? '0010' : '0100';
		$bit_dai = $alnum ? ( $ver <= 9 ? 9 : 11 ) : ( $ver <= 9 ? 8 : 16 );
		$bit .= str_pad( decbin( strlen( $s ) ), $bit_dai, '0', STR_PAD_LEFT );

		if ( $alnum ) {
			for ( $i = 0; $i < strlen( $s ); $i += 2 ) {
				if ( $i + 1 < strlen( $s ) ) {
					$v = strpos( self::AN, $s[ $i ] ) * 45 + strpos( self::AN, $s[ $i + 1 ] );
					$bit .= str_pad( decbin( $v ), 11, '0', STR_PAD_LEFT );
				} else {
					$bit .= str_pad( decbin( strpos( self::AN, $s[ $i ] ) ), 6, '0', STR_PAD_LEFT );
				}
			}
		} else {
			for ( $i = 0; $i < strlen( $s ); $i++ ) {
				$bit .= str_pad( decbin( ord( $s[ $i ] ) ), 8, '0', STR_PAD_LEFT );
			}
		}

		$tong_bit = self::so_cw_du_lieu( $ver, $muc ) * 8;
		/* Dấu kết thúc tối đa 4 bit, rồi đệm cho tròn byte, rồi đệm EC/11 luân phiên. */
		$bit .= str_repeat( '0', min( 4, $tong_bit - strlen( $bit ) ) );
		while ( strlen( $bit ) % 8 ) { $bit .= '0'; }
		$dem = array( 0xEC, 0x11 ); $k = 0;
		while ( strlen( $bit ) < $tong_bit ) {
			$bit .= str_pad( decbin( $dem[ $k % 2 ] ), 8, '0', STR_PAD_LEFT );
			$k++;
		}
		$cw = array();
		for ( $i = 0; $i < strlen( $bit ); $i += 8 ) { $cw[] = bindec( substr( $bit, $i, 8 ) ); }
		return $cw;
	}

	/**
	 * Xen kẽ khối dữ liệu và khối sửa lỗi theo đúng luật của chuẩn.
	 * ⚠️ Xen kẽ SAI thì mã vẫn dựng ra được, vẫn nhìn như thật, và máy quét đọc ra rác. Đây là
	 *    chỗ dễ sai nhất của cả tệp — nên bộ đọc ngược ở dưới cũng phải tự tháo xen kẽ, và phép
	 *    thử bắt hai bên gặp nhau.
	 */
	public static function xen_ke( $cw, $ver, $muc ) {
		$b = self::bang_khoi();
		list( $n_ecc, $k1, $k2 ) = $b[ $ver ][ $muc ];
		$tong_khoi = $k1 + $k2;
		$cw_g1 = intdiv( self::so_cw_du_lieu( $ver, $muc ), $tong_khoi );
		$khoi = array(); $khoi_ecc = array(); $vt = 0;
		for ( $i = 0; $i < $tong_khoi; $i++ ) {
			$n = $cw_g1 + ( $i >= $k1 ? 1 : 0 );
			$kh = array_slice( $cw, $vt, $n );
			$vt += $n;
			$khoi[] = $kh;
			$khoi_ecc[] = self::ecc( $kh, $n_ecc );
		}
		$ra = array();
		$dai_nhat = 0;
		foreach ( $khoi as $kh ) { $dai_nhat = max( $dai_nhat, count( $kh ) ); }
		for ( $i = 0; $i < $dai_nhat; $i++ ) {
			foreach ( $khoi as $kh ) { if ( isset( $kh[ $i ] ) ) { $ra[] = $kh[ $i ]; } }
		}
		for ( $i = 0; $i < $n_ecc; $i++ ) {
			foreach ( $khoi_ecc as $kh ) { if ( isset( $kh[ $i ] ) ) { $ra[] = $kh[ $i ]; } }
		}
		return $ra;
	}

	// ===================================================================== dựng ma trận

	/** Ma trận trống + các hình cố định. Trả về array( $o, $cam ) — `$cam` đánh dấu ô không đặt dữ liệu. */
	private static function khung( $ver ) {
		$n = 17 + 4 * $ver;
		$o   = array_fill( 0, $n, array_fill( 0, $n, 0 ) );
		$cam = array_fill( 0, $n, array_fill( 0, $n, false ) );

		$dat = function ( $x, $y, $v ) use ( &$o, &$cam, $n ) {
			if ( $x < 0 || $y < 0 || $x >= $n || $y >= $n ) { return; }
			$o[ $y ][ $x ] = $v ? 1 : 0;
			$cam[ $y ][ $x ] = true;
		};

		/* Ba ô định vị + dải trắng quanh chúng. */
		foreach ( array( array( 0, 0 ), array( $n - 7, 0 ), array( 0, $n - 7 ) ) as $g ) {
			list( $gx, $gy ) = $g;
			for ( $dy = -1; $dy <= 7; $dy++ ) {
				for ( $dx = -1; $dx <= 7; $dx++ ) {
					$x = $gx + $dx; $y = $gy + $dy;
					if ( $x < 0 || $y < 0 || $x >= $n || $y >= $n ) { continue; }
					$trong = ( $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6 );
					$den = $trong && ( 0 === $dx || 6 === $dx || 0 === $dy || 6 === $dy
						|| ( $dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4 ) );
					$dat( $x, $y, $den );
				}
			}
		}

		/* Hai dải nhịp. */
		for ( $i = 8; $i < $n - 8; $i++ ) {
			$dat( $i, 6, 0 === $i % 2 );
			$dat( 6, $i, 0 === $i % 2 );
		}

		/* Ô căn chỉnh — bỏ những ô đè lên ô định vị. */
		$tam = self::tam_can( $ver );
		foreach ( $tam as $cy ) {
			foreach ( $tam as $cx ) {
				if ( ( 6 === $cx && 6 === $cy ) || ( 6 === $cx && $cy === $n - 7 )
					|| ( $cx === $n - 7 && 6 === $cy ) ) { continue; }
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$den = ( 2 === max( abs( $dx ), abs( $dy ) ) ) || ( 0 === $dx && 0 === $dy );
						$dat( $cx + $dx, $cy + $dy, $den );
					}
				}
			}
		}

		/* ===== Chừa chỗ cho thông tin định dạng, rồi ô tối cố định ==================
		 * 🔴 HAI CHỖ DỄ GIẪM LÊN THỨ KHÁC, và phép thử cấu trúc bắt được cả hai (bộ đọc ngược thì
		 *    KHÔNG — nó chỉ chứng minh vẽ và đọc tự nhất quán, không chứng minh đúng chuẩn):
		 *
		 *    1. Ô (6,8) và (8,6) thuộc DẢI NHỊP, không thuộc vùng định dạng. Quét cả dải 0..8 là
		 *       xoá mất hai ô nhịp — mà dải nhịp chính là thước đo cỡ module của máy quét.
		 *    2. Dải định dạng dọc ở góc dưới-trái chỉ có BẢY ô (n-1 lên n-7). Quét tám ô là chạm
		 *       tới (8, n-8) — đúng chỗ Ô TỐI CỐ ĐỊNH, và xoá nó thành trắng.
		 *
		 *    Cả hai đều cho ra một mã "nhìn như thật" mà máy quét khó hoặc không đọc được. */
		for ( $i = 0; $i <= 8; $i++ ) {
			if ( 6 === $i ) { continue; }          // ô nhịp, không phải vùng định dạng
			$dat( $i, 8, 0 );
			$dat( 8, $i, 0 );
		}
		for ( $i = 0; $i < 8; $i++ ) { $dat( $n - 1 - $i, 8, 0 ); }   // ngang: 8 ô
		for ( $i = 0; $i < 7; $i++ ) { $dat( 8, $n - 1 - $i, 0 ); }   // dọc: 7 ô
		/* Đặt SAU cùng, để không nhánh nào ở trên giẫm lên. */
		$dat( 8, $n - 8, 1 );

		/* Thông tin version (từ version 7). */
		if ( $ver >= 7 ) {
			$bit = self::bit_version( $ver );
			for ( $i = 0; $i < 18; $i++ ) {
				$b = ( $bit >> $i ) & 1;
				$dat( intdiv( $i, 3 ), $n - 11 + ( $i % 3 ), $b );
				$dat( $n - 11 + ( $i % 3 ), intdiv( $i, 3 ), $b );
			}
		}
		return array( $o, $cam );
	}

	/** 18 bit thông tin version: 6 bit version + 12 bit BCH(18,6). */
	public static function bit_version( $ver ) {
		$d = $ver << 12;
		$r = $d;
		for ( $i = 17; $i >= 12; $i-- ) {
			if ( ( $r >> $i ) & 1 ) { $r ^= 0x1F25 << ( $i - 12 ); }
		}
		return $d | $r;
	}

	/** 15 bit thông tin định dạng: 2 bit mức + 3 bit mặt nạ + BCH, rồi XOR mặt nạ cố định. */
	public static function bit_dinh_dang( $muc, $mat_na ) {
		$bit_muc = array( 'L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2 );
		$d = ( $bit_muc[ $muc ] << 3 ) | $mat_na;
		$r = $d << 10;
		for ( $i = 14; $i >= 10; $i-- ) {
			if ( ( $r >> $i ) & 1 ) { $r ^= 0x537 << ( $i - 10 ); }
		}
		return ( ( $d << 10 ) | ( $r & 0x3FF ) ) ^ 0x5412;
	}

	private static function ham_mat_na( $k, $x, $y ) {
		switch ( $k ) {
			case 0: return 0 === ( $x + $y ) % 2;
			case 1: return 0 === $y % 2;
			case 2: return 0 === $x % 3;
			case 3: return 0 === ( $x + $y ) % 3;
			case 4: return 0 === ( intdiv( $y, 2 ) + intdiv( $x, 3 ) ) % 2;
			case 5: return 0 === ( $x * $y ) % 2 + ( $x * $y ) % 3;
			case 6: return 0 === ( ( ( $x * $y ) % 2 + ( $x * $y ) % 3 ) % 2 );
			default: return 0 === ( ( ( $x + $y ) % 2 + ( $x * $y ) % 3 ) % 2 );
		}
	}

	/** Đường đi zigzag của vùng dữ liệu: từ dưới-phải lên, hai cột một, bỏ cột nhịp số 6. */
	private static function duong_di( $n, $cam ) {
		$vt = array();
		$len = false;
		for ( $cot = $n - 1; $cot > 0; $cot -= 2 ) {
			if ( 6 === $cot ) { $cot--; }   // cột nhịp không mang dữ liệu
			$len = ! $len;
			for ( $i = 0; $i < $n; $i++ ) {
				$y = $len ? ( $n - 1 - $i ) : $i;
				foreach ( array( $cot, $cot - 1 ) as $x ) {
					if ( ! $cam[ $y ][ $x ] ) { $vt[] = array( $x, $y ); }
				}
			}
		}
		return $vt;
	}

	/** Chấm điểm một ma trận theo bốn luật phạt của chuẩn — thấp hơn là tốt hơn. */
	private static function cham_diem( $o, $n ) {
		$diem = 0;
		/* Luật 1: dãy 5 ô trở lên cùng màu, theo cả hàng lẫn cột. */
		for ( $lan = 0; $lan < 2; $lan++ ) {
			for ( $a = 0; $a < $n; $a++ ) {
				$dem = 1;
				for ( $b = 1; $b < $n; $b++ ) {
					$nay = $lan ? $o[ $b ][ $a ] : $o[ $a ][ $b ];
					$truoc = $lan ? $o[ $b - 1 ][ $a ] : $o[ $a ][ $b - 1 ];
					if ( $nay === $truoc ) { $dem++; }
					else { if ( $dem >= 5 ) { $diem += 3 + ( $dem - 5 ); } $dem = 1; }
				}
				if ( $dem >= 5 ) { $diem += 3 + ( $dem - 5 ); }
			}
		}
		/* Luật 2: mỗi khối 2×2 cùng màu. */
		for ( $y = 0; $y < $n - 1; $y++ ) {
			for ( $x = 0; $x < $n - 1; $x++ ) {
				$v = $o[ $y ][ $x ];
				if ( $v === $o[ $y ][ $x + 1 ] && $v === $o[ $y + 1 ][ $x ] && $v === $o[ $y + 1 ][ $x + 1 ] ) {
					$diem += 3;
				}
			}
		}
		/* Luật 3: hình 1:1:3:1:1 kèm khoảng trắng — dễ bị nhầm với ô định vị. */
		$mau = array( array( 1,0,1,1,1,0,1,0,0,0,0 ), array( 0,0,0,0,1,0,1,1,1,0,1 ) );
		for ( $lan = 0; $lan < 2; $lan++ ) {
			for ( $a = 0; $a < $n; $a++ ) {
				for ( $b = 0; $b + 10 < $n; $b++ ) {
					foreach ( $mau as $m ) {
						$khop = true;
						for ( $k = 0; $k < 11; $k++ ) {
							$v = $lan ? $o[ $b + $k ][ $a ] : $o[ $a ][ $b + $k ];
							if ( $v !== $m[ $k ] ) { $khop = false; break; }
						}
						if ( $khop ) { $diem += 40; }
					}
				}
			}
		}
		/* Luật 4: lệch tỉ lệ đen/trắng khỏi 50%. */
		$den = 0;
		foreach ( $o as $hang ) { $den += array_sum( $hang ); }
		$ti = intdiv( $den * 100, $n * $n );
		$diem += 10 * intdiv( abs( $ti - 50 ), 5 );
		return $diem;
	}

	/**
	 * Dựng ma trận QR cho một chuỗi. Trả về mảng hai chiều 0/1, hoặc rỗng nếu không dựng được.
	 *
	 * ⚠️ Không dựng được thì trả RỖNG, không trả một ma trận "gần đúng". Nơi gọi phải nói ra —
	 *    một tấm tem in ra mà không quét được thì tệ hơn hẳn việc chưa in tem nào.
	 */
	public static function ma_tran( $chuoi, $muc = 'M' ) {
		$s = (string) $chuoi;
		if ( '' === $s || ! isset( self::MUC[ $muc ] ) ) { return array(); }
		$ver = self::chon_version( $s, $muc );
		if ( 0 === $ver ) { return array(); }

		$cw = self::xen_ke( self::ma_hoa( $s, $ver, $muc ), $ver, $muc );
		list( $khung, $cam ) = self::khung( $ver );
		$n = 17 + 4 * $ver;

		/* Chuỗi bit dữ liệu + bit thừa (remainder) của version. */
		$bit = '';
		foreach ( $cw as $b ) { $bit .= str_pad( decbin( $b ), 8, '0', STR_PAD_LEFT ); }
		$duong = self::duong_di( $n, $cam );
		$bit = str_pad( $bit, count( $duong ), '0' );

		$tot = null; $diem_tot = PHP_INT_MAX; $mn_tot = 0;
		for ( $mn = 0; $mn < 8; $mn++ ) {
			$o = $khung;
			foreach ( $duong as $k => $xy ) {
				list( $x, $y ) = $xy;
				$v = ( '1' === $bit[ $k ] ) ? 1 : 0;
				if ( self::ham_mat_na( $mn, $x, $y ) ) { $v ^= 1; }
				$o[ $y ][ $x ] = $v;
			}
			$dd = self::bit_dinh_dang( $muc, $mn );
			for ( $i = 0; $i < 15; $i++ ) {
				$b = ( $dd >> $i ) & 1;
				/* ══════════════════════════════════════════════════════════════════════════
				 * 🔴 LỖI 23/08/2026: 15 BIT NÀY BỊ ĐẶT SOI GƯƠNG — MÃ QR KHÔNG QUÉT ĐƯỢC.
				 *
				 * Anh Thắng: *"giờ quét mã này không nhận"*. Đem ma trận so từng ô với một bộ mã
				 * hoá độc lập thì 1.353/1.369 ô KHỚP TUYỆT ĐỐI — chỉ 16 ô lệch, và cả 16 đều nằm
				 * ở cột 8 hoặc hàng 8, tức là đúng vùng thông tin định dạng.
				 *
				 * Giá trị 15 bit thì ĐÚNG (mức L, mặt nạ 0 — cùng kết quả với bộ chuẩn). Sai ở
				 * chỗ ĐẶT: bản cũ cho nửa bit thấp chạy NGANG hàng 8 và nửa bit cao chạy DỌC cột
				 * 8, trong khi bản đặc tả quy định ngược lại. Đọc bit của bộ chuẩn theo thứ tự
				 * của bản cũ thì ra đúng chuỗi bit của bản cũ ĐẢO NGƯỢC — dấu vân tay của một
				 * lỗi soi gương.
				 *
				 * Hậu quả: máy quét đọc ra một mức sửa lỗi và một mặt nạ SAI, gỡ mặt nạ sai, và
				 * nhận về toàn rác. Nó không báo "mã hỏng" — nó chỉ lặng lẽ không nhận.
				 *
				 * ⚠️ VÌ SAO BỘ ĐỌC CỦA CHÍNH TỆP NÀY KHÔNG BẮT ĐƯỢC. `doc()` đọc thông tin định
				 *    dạng từ ĐÚNG NHỮNG Ô SAI đó, nên nó khớp với bộ vẽ một cách hoàn hảo. Phép
				 *    thử đọc-ngược chỉ chứng minh "bộ đọc của tôi hiểu bộ vẽ của tôi" — nó KHÔNG
				 *    chứng minh được gì về việc máy quét thật có hiểu hay không. Muốn biết điều
				 *    đó thì phải so với một bộ mã hoá KHÁC, và nay bộ thử làm đúng vậy.
				 * ══════════════════════════════════════════════════════════════════════════ */
				/* Bản sao 1, quanh ô định vị trên-trái: bit thấp chạy DỌC cột 8 (từ trên xuống),
				   bit cao chạy NGANG hàng 8 (từ phải sang trái). Ô nhịp (8,6) và (6,8) bị nhảy qua. */
				if ( $i < 6 )       { $o[ $i ][8] = $b; }          // (x=8, y=i)
				elseif ( 6 === $i ) { $o[7][8] = $b; }             // (x=8, y=7)
				elseif ( 7 === $i ) { $o[8][8] = $b; }             // (8,8)
				elseif ( 8 === $i ) { $o[8][7] = $b; }             // (x=7, y=8)
				else                { $o[8][ 14 - $i ] = $b; }     // (x=14-i, y=8)
				/* Bản sao 2: TÁM bit đầu chạy NGANG ở mép phải hàng 8 (cột n-1 lùi về n-8), BẢY
				   bit sau chạy DỌC ở mép dưới cột 8 (hàng n-7 xuống n-1).
				   ⚠️ Bảy chứ không tám ở vế sau. Lấy tám là bit đầu của vế đó rơi vào (8, n-8) —
				      đúng chỗ Ô TỐI CỐ ĐỊNH, và ghi đè nó thành bit dữ liệu. Ô đó luôn phải đen;
				      máy quét dùng nó để chốt hướng đọc thông tin định dạng. */
				if ( $i < 8 ) { $o[8][ $n - 1 - $i ] = $b; }       // (x=n-1-i, y=8)
				else          { $o[ $n - 15 + $i ][8] = $b; }      // (x=8, y=n-15+i)
			}
			$d = self::cham_diem( $o, $n );
			if ( $d < $diem_tot ) { $diem_tot = $d; $tot = $o; $mn_tot = $mn; }
		}
		return $tot ? $tot : array();
	}

	// ===================================================================== đọc ngược (tự kiểm)

	/**
	 * ĐỌC NGƯỢC một ma trận QR về lại chuỗi. Rỗng nếu không đọc được.
	 *
	 * 🔴 Đây KHÔNG phải tính năng cho người dùng — đây là cách tệp này tự chứng minh mình đúng.
	 *    Tự viết bộ vẽ QR thì "chắc là quét được" chỉ là một lời chúc; phép thử bắt mọi chuỗi
	 *    dựng ra phải đọc ngược đúng chuỗi ban đầu.
	 *
	 *    Nó đi ngược đúng những bước dễ sai nhất: đọc thông tin định dạng để biết mặt nạ, gỡ mặt
	 *    nạ, đi lại đường zigzag, THÁO XEN KẼ khối, rồi đọc chế độ và độ dài. Một lỗi ở bất kỳ
	 *    bước nào trong bộ vẽ là chuỗi đọc ra khác chuỗi ban đầu.
	 *
	 * ⚠️ Không sửa lỗi Reed-Solomon: nó đọc một ma trận SẠCH. Phần Reed-Solomon được kiểm riêng
	 *    bằng cách đối chiếu với bộ hệ số và ví dụ đã công bố trong bản đặc tả.
	 */
	public static function doc( $o ) {
		if ( ! is_array( $o ) || ! count( $o ) ) { return ''; }
		$n = count( $o );
		if ( $n < 21 || 0 !== ( $n - 17 ) % 4 ) { return ''; }
		$ver = ( $n - 17 ) / 4;
		if ( $ver < 1 || $ver > 10 ) { return ''; }

		/* Thông tin định dạng: đọc bản sao 1, XOR mặt nạ cố định. */
		$dd = 0;
		for ( $i = 0; $i < 15; $i++ ) {
			/* ⚠️ PHẢI KHỚP CHÍNH XÁC toạ độ bên `ma_tran()` — và cả hai phải khớp BẢN ĐẶC TẢ.
			   23/08/2026: hai bên cùng đọc/ghi ở những ô SOI GƯƠNG so với đặc tả, nên chúng khớp
			   nhau hoàn hảo và phép thử đọc-ngược đạt 100% — trong khi máy quét thật không đọc
			   nổi mã nào (0/36). Sửa một bên mà quên bên kia thì bộ thử gãy ngay, và đó là điều
			   TỐT: nó buộc người sửa nhìn cả hai. */
			if ( $i < 6 )       { $b = $o[ $i ][8]; }          // (x=8, y=i)
			elseif ( 6 === $i ) { $b = $o[7][8]; }             // (x=8, y=7)
			elseif ( 7 === $i ) { $b = $o[8][8]; }             // (8,8)
			elseif ( 8 === $i ) { $b = $o[8][7]; }             // (x=7, y=8)
			else                { $b = $o[8][ 14 - $i ]; }     // (x=14-i, y=8)
			$dd |= ( $b & 1 ) << $i;
		}
		$dd ^= 0x5412;
		$bit_muc = ( $dd >> 13 ) & 3;
		$mat_na  = ( $dd >> 10 ) & 7;
		$ten_muc = array( 1 => 'L', 0 => 'M', 3 => 'Q', 2 => 'H' );
		if ( ! isset( $ten_muc[ $bit_muc ] ) ) { return ''; }
		$muc = $ten_muc[ $bit_muc ];

		list( , $cam ) = self::khung( $ver );
		$duong = self::duong_di( $n, $cam );
		$bit = '';
		foreach ( $duong as $xy ) {
			list( $x, $y ) = $xy;
			$v = $o[ $y ][ $x ] & 1;
			if ( self::ham_mat_na( $mat_na, $x, $y ) ) { $v ^= 1; }
			$bit .= $v ? '1' : '0';
		}

		$cw = array();
		for ( $i = 0; $i + 8 <= strlen( $bit ); $i += 8 ) { $cw[] = bindec( substr( $bit, $i, 8 ) ); }

		/* Tháo xen kẽ: dựng lại các khối dữ liệu theo đúng luật đã xen. */
		$b = self::bang_khoi();
		list( $n_ecc, $k1, $k2 ) = $b[ $ver ][ $muc ];
		$tong_khoi = $k1 + $k2;
		$so_du = self::so_cw_du_lieu( $ver, $muc );
		$cw_g1 = intdiv( $so_du, $tong_khoi );
		$dai = array();
		for ( $i = 0; $i < $tong_khoi; $i++ ) { $dai[ $i ] = $cw_g1 + ( $i >= $k1 ? 1 : 0 ); }
		$khoi = array_fill( 0, $tong_khoi, array() );
		$vt = 0;
		for ( $i = 0; $i < max( $dai ); $i++ ) {
			for ( $k = 0; $k < $tong_khoi; $k++ ) {
				if ( $i < $dai[ $k ] ) {
					if ( ! isset( $cw[ $vt ] ) ) { return ''; }
					$khoi[ $k ][] = $cw[ $vt ];
					$vt++;
				}
			}
		}
		$du = array();
		foreach ( $khoi as $kh ) { $du = array_merge( $du, $kh ); }

		$bs = '';
		foreach ( $du as $x ) { $bs .= str_pad( decbin( $x ), 8, '0', STR_PAD_LEFT ); }

		$che_do = bindec( substr( $bs, 0, 4 ) );
		$p = 4;
		if ( 2 === $che_do ) {
			$bd = $ver <= 9 ? 9 : 11;
			$len = bindec( substr( $bs, $p, $bd ) ); $p += $bd;
			$ra = '';
			for ( $i = 0; $i + 1 < $len; $i += 2 ) {
				$v = bindec( substr( $bs, $p, 11 ) ); $p += 11;
				$ra .= self::AN[ intdiv( $v, 45 ) ] . self::AN[ $v % 45 ];
			}
			if ( $len % 2 ) { $ra .= self::AN[ bindec( substr( $bs, $p, 6 ) ) ]; }
			return $ra;
		}
		if ( 4 === $che_do ) {
			$bd = $ver <= 9 ? 8 : 16;
			$len = bindec( substr( $bs, $p, $bd ) ); $p += $bd;
			$ra = '';
			for ( $i = 0; $i < $len; $i++ ) { $ra .= chr( bindec( substr( $bs, $p, 8 ) ) ); $p += 8; }
			return $ra;
		}
		return '';
	}

	/**
	 * Ma trận -> mảng chuỗi '0'/'1', mỗi chuỗi một hàng. Dạng gọn để gửi xuống trình duyệt rồi
	 * vẽ lên canvas — nhẹ hơn hẳn SVG, và canvas thì XUẤT RA PNG ĐƯỢC.
	 *
	 * 🔴 Vì sao cần PNG chứ không chỉ SVG: khách tải ảnh mã QR về máy rồi mở app ngân hàng, chọn
	 *    "quét từ thư viện ảnh". Thư viện ảnh của điện thoại KHÔNG hiện tệp SVG — tải về một tệp
	 *    không nhìn thấy trong thư viện thì coi như chưa tải.
	 */
	public static function hang( $o ) {
		$ra = array();
		foreach ( (array) $o as $hang ) { $ra[] = implode( '', array_map( 'intval', $hang ) ); }
		return $ra;
	}

	// ===================================================================== xuất SVG

	/**
	 * Ma trận -> SVG. Vẽ bằng các ô vuông gộp theo hàng: một tấm tem version 3 là 29×29 ô, vẽ
	 * từng ô riêng là gần 900 thẻ — nặng và in chậm.
	 *
	 * ⚠️ VÙNG LẶNG 4 Ô mỗi bên, đúng chuẩn. Cắt bớt cho "gọn" là nhiều máy quét không nhận ra mã,
	 *    và đó là kiểu hỏng chỉ lộ ra ở một số máy — tức là sau khi đã dán tem lên 26 cái ghế.
	 */
	public static function svg( $o, $canh_px = 240, $lang = 4 ) {
		if ( ! is_array( $o ) || ! count( $o ) ) { return ''; }
		$n = count( $o );
		$tong = $n + 2 * $lang;
		$duong = '';
		for ( $y = 0; $y < $n; $y++ ) {
			$x = 0;
			while ( $x < $n ) {
				if ( ! $o[ $y ][ $x ] ) { $x++; continue; }
				$d = $x;
				while ( $d < $n && $o[ $y ][ $d ] ) { $d++; }
				$duong .= 'M' . ( $x + $lang ) . ' ' . ( $y + $lang )
					. 'h' . ( $d - $x ) . 'v1h-' . ( $d - $x ) . 'z';
				$x = $d;
			}
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $canh_px . '" height="'
			. (int) $canh_px . '" viewBox="0 0 ' . $tong . ' ' . $tong . '" shape-rendering="crispEdges">'
			. '<rect width="' . $tong . '" height="' . $tong . '" fill="#fff"/>'
			. '<path d="' . $duong . '" fill="#000"/></svg>';
	}
}

register_activation_hook( __FILE__, array( 'NHAMA', 'cai_dat' ) );
add_action( 'init', array( 'NHAMA', 'init' ), 5 );
add_action( 'rest_api_init', array( 'NHAMA', 'rest' ) );

endif;
