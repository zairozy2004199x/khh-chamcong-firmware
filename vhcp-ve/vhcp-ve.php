<?php
/**
 * Plugin Name:       POSH · Bán vé (Zalo Mini App)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Bán vé/dịch vụ khu vui chơi trả trước qua Zalo Mini App. Quản lý dịch vụ (ảnh/giá/mô tả), nhận đơn từ Zalo, dựng VietQR. ĐỘC LẬP với plugin ghế massage.
 * Version:           1.49.1
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 *
 * ⚠️ Repo CÔNG KHAI: KHÔNG hardcode số tài khoản/khoá vào mã. Số TK nhận tiền khai trong trang
 *    admin của plugin này (hoặc tự lấy lại từ plugin ghế nếu đã khai ở đó). Số TK vốn công khai
 *    trên QR nên không phải bí mật; nhưng vẫn để ở cấu hình, không nhét chuỗi cụ thể vào source.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Chống "Cannot redeclare class" nếu lỡ tồn tại 2 bản plugin trùng tên. */
if ( ! class_exists( 'POSH_Ve' ) ) :

class POSH_Ve {

	const NS      = 'posh/v1';
	const VER_TBL = '9';

	/* Hạng thành viên mặc định (điểm mốc). 1 điểm = 1.000đ chi tiêu. Sửa trong admin. */
	const HANG_MAC_DINH = array(
		array( 'ten' => 'Thành viên', 'moc' => 0 ),
		array( 'ten' => 'Bạc',        'moc' => 1500 ),
		array( 'ten' => 'Vàng',       'moc' => 3000 ),
		array( 'ten' => 'Kim cương',  'moc' => 10000 ),
	);

	const VE_MAC_DINH = array(
		array( 'id' => 1, 'ten' => 'Vé vào cửa', 'gia' => 50000,  'gia_goc' => 0,      'nhom' => 'Vé lẻ',  'mo_ta' => 'Vé vào cửa 1 người', 'anh' => '', 'thoi_luong' => '', 'hien' => 1 ),
		array( 'id' => 2, 'ten' => 'Vé 1 giờ',   'gia' => 80000,  'gia_goc' => 100000, 'nhom' => 'Vé lẻ',  'mo_ta' => 'Chơi trong 1 giờ',    'anh' => '', 'thoi_luong' => '60 phút', 'hien' => 1 ),
		array( 'id' => 3, 'ten' => 'Vé cả ngày', 'gia' => 150000, 'gia_goc' => 0,      'nhom' => 'Vé lẻ',  'mo_ta' => 'Chơi không giới hạn trong ngày', 'anh' => '', 'thoi_luong' => 'Cả ngày', 'hien' => 1 ),
		array( 'id' => 4, 'ten' => 'Combo 2 bé', 'gia' => 140000, 'gia_goc' => 200000, 'nhom' => 'Combo', 'mo_ta' => '2 bé chơi cả ngày', 'anh' => '', 'thoi_luong' => 'Cả ngày', 'hien' => 1 ),
	);

	/* Bảng BIN ngân hàng (đủ cho hiển thị tên). Không có thì trả rỗng — thà "không rõ" còn hơn đoán. */
	const NGAN_HANG = array(
		'970418' => 'BIDV', '970436' => 'Vietcombank', '970415' => 'VietinBank', '970422' => 'MB',
		'970407' => 'Techcombank', '970416' => 'ACB', '970448' => 'OCB', '970432' => 'VPBank',
		'970405' => 'Agribank', '970423' => 'TPBank', '970443' => 'SHB', '970441' => 'VIB',
		'970426' => 'MSB', '970403' => 'Sacombank', '970431' => 'Eximbank', '970437' => 'HDBank',
	);

	public static function init() {
		self::bao_dam_bang();
		self::bao_dam_trang_ql();   // tự tạo sẵn trang quản trị vé cho marketing (chạy 1 lần)
		add_action( 'rest_api_init', array( __CLASS__, 'dang_ky' ) );
		/* Dò sổ phụ định kỳ. Trang khách hỏi trạng thái 5 giây một lượt là đủ nhanh KHI còn mở —
		   nhưng khách chuyển khoản xong thường đóng tab luôn, và không ai ngồi canh hộ. Không có
		   lịch này thì vé (và lệnh nạp ví) nằm chờ tới lúc tình cờ có người mở đúng nó. */
		add_action( 'pve_do_saoke', array( __CLASS__, 'cron_do_saoke' ) );
		if ( ! wp_next_scheduled( 'pve_do_saoke' ) ) {
			wp_schedule_event( time() + 120, 'pve_5p', 'pve_do_saoke' );
		}
		add_filter( 'cron_schedules', function ( $ds ) {
			$ds['pve_5p'] = array( 'interval' => 300, 'display' => 'Mỗi 5 phút (vé)' );
			return $ds;
		} );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_shortcode( 'posh_ve', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'posh_ql', array( __CLASS__, 'shortcode_ql' ) );
		add_shortcode( 'posh_soat', array( __CLASS__, 'shortcode_soat' ) );
		add_action( 'wp', array( __CLASS__, 'an_admin_bar' ) );
		add_action( 'template_redirect', array( __CLASS__, 'zalo_web_login' ) );
		add_action( 'wp_head', array( __CLASS__, 'zalo_verify_meta' ) );
		add_action( 'template_redirect', array( __CLASS__, 'zalo_verify_file' ), 0 );
	}

	// ───────────────────────────── VietQR (tự chứa) ─────────────────────────────
	private static function tlv( $ma, $v ) { $v = (string) $v; return $ma . substr( '0' . strlen( $v ), -2 ) . $v; }
	private static function crc16( $s ) {
		$crc = 0xFFFF; $n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) { $crc ^= ( ord( $s[ $i ] ) & 0xFF ) << 8;
			for ( $b = 0; $b < 8; $b++ ) { $crc = ( $crc & 0x8000 ) ? ( ( $crc << 1 ) ^ 0x1021 ) : ( $crc << 1 ); $crc &= 0xFFFF; } }
		return substr( '000' . strtoupper( dechex( $crc ) ), -4 );
	}
	/**
	 * Mã QR chuyển khoản, dựng SẴN THÀNH SVG Ở MÁY CHỦ.
	 *
	 * ⚠️ ĐỪNG QUAY LẠI KIỂU TẢI THƯ VIỆN QR TỪ CDN. Trang khách trước đây gọi qrcodejs từ
	 * cdnjs.cloudflare.com rồi mới vẽ. Ngày 11/09/2026 trên site thật nó ra đúng câu dự phòng
	 * "(Không tải được mã QR — dùng nội dung CK bên dưới)": CDN không tới được. Khách đang cầm
	 * điện thoại định quét thì phải tự gõ tay số tài khoản và nội dung — gõ sai một ký tự trong
	 * nội dung là tiền vào mà đơn không tự khớp.
	 *
	 * Dựng ở máy chủ thì không phụ thuộc mạng ngoài, không phụ thuộc cả plugin chặn/gộp JS. Bộ
	 * dựng mượn của plugin Ghế (VHG_QRVe) — đã đối chiếu khớp từng ô với thư viện chuẩn cho đúng
	 * loại chuỗi VietQR này. Gác class_exists đúng luật gọi chéo: thiếu plugin Ghế thì trả rỗng
	 * và trang tự lùi về cách cũ, chứ không vỡ.
	 */
	public static function qr_svg( $chuoi, $px = 220 ) {
		$chuoi = (string) $chuoi;
		if ( '' === $chuoi ) { return ''; }
		if ( ! class_exists( 'VHG_QRVe' ) || ! method_exists( 'VHG_QRVe', 'ma_tran' ) ) { return ''; }
		$mt = VHG_QRVe::ma_tran( $chuoi, 'M' );
		/* Ma trận rỗng = không dựng được. Trả rỗng để nơi gọi biết mà nói ra, đừng bịa một tấm
		   tem gần đúng: mã quét không ra còn tệ hơn không có mã. */
		if ( ! is_array( $mt ) || ! count( $mt ) ) { return ''; }
		return VHG_QRVe::svg( $mt, (int) $px );
	}

	public static function vietqr( $bin, $so_tk, $so_tien, $noi_dung ) {
		$s  = self::tlv( '00', '01' ) . self::tlv( '01', $so_tien ? '12' : '11' );
		$ben = self::tlv( '00', (string) $bin ) . self::tlv( '01', (string) $so_tk );
		$s .= self::tlv( '38', self::tlv( '00', 'A000000727' ) . self::tlv( '01', $ben ) . self::tlv( '02', 'QRIBFTTA' ) );
		$s .= self::tlv( '53', '704' );
		if ( $so_tien ) { $s .= self::tlv( '54', (string) (int) $so_tien ); }
		$s .= self::tlv( '58', 'VN' );
		if ( '' !== (string) $noi_dung ) { $s .= self::tlv( '62', self::tlv( '08', (string) $noi_dung ) ); }
		$s .= '6304'; return $s . self::crc16( $s );
	}
	private static function ten_nh( $bin ) {
		$bin = preg_replace( '/\D+/', '', (string) $bin );
		return isset( self::NGAN_HANG[ $bin ] ) ? self::NGAN_HANG[ $bin ] : '';
	}

	/** TK nhận tiền: ưu tiên cấu hình RIÊNG của plugin vé; trống thì lấy lại từ plugin ghế (nếu có). */
	private static function bank() {
		$bin = trim( (string) get_option( 'pve_bin', '' ) );
		$tk  = trim( (string) get_option( 'pve_so_tk', '' ) );
		$ten = trim( (string) get_option( 'pve_ten_tk', '' ) );
		if ( '' === $bin ) { $bin = trim( (string) get_option( 'vhg_bin', '' ) ); }
		if ( '' === $tk )  { $tk  = trim( (string) get_option( 'vhg_so_tk', '' ) ); }
		if ( '' === $ten ) { $ten = trim( (string) get_option( 'vhg_ten_tk', '' ) ); }
		return array( 'bin' => $bin, 'so_tk' => $tk, 'ten_tk' => $ten, 'ten_nh' => self::ten_nh( $bin ) );
	}

	// ───────────────────────────── Danh mục dịch vụ ─────────────────────────────
	private static function chuan_hoa( $v, $auto_id ) {
		return array(
			'id'         => (int) ( ! empty( $v['id'] ) ? $v['id'] : $auto_id ),
			'ten'        => trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) ),
			'gia'        => (int) ( isset( $v['gia'] ) ? $v['gia'] : 0 ),
			'gia_goc'    => (int) ( isset( $v['gia_goc'] ) ? $v['gia_goc'] : 0 ),   // giá gốc (gạch ngang) — 0 = không sale
			'nhom'       => trim( (string) ( isset( $v['nhom'] ) ? $v['nhom'] : '' ) ),
			'khu_vuc'    => trim( (string) ( isset( $v['khu_vuc'] ) ? $v['khu_vuc'] : '' ) ),   // cơ sở/khu vực (HN/HCM…), trống = mọi khu vực
			'mo_ta'      => trim( (string) ( isset( $v['mo_ta'] ) ? $v['mo_ta'] : '' ) ),
			'anh'        => esc_url_raw( (string) ( isset( $v['anh'] ) ? $v['anh'] : '' ) ),
			'thoi_luong' => trim( (string) ( isset( $v['thoi_luong'] ) ? $v['thoi_luong'] : '' ) ),
			'so_luong'   => isset( $v['so_luong'] ) && '' !== $v['so_luong'] ? (int) $v['so_luong'] : -1,   // -1 = không giới hạn; >=0 = số vé còn
			'hien'       => empty( $v['hien'] ) ? 0 : 1,
		);
	}
	/* Trừ tồn kho (n vé) cho dịch vụ có giới hạn. Bỏ qua nếu không giới hạn (-1). */
	private static function giam_ton( $id, $n ) {
		$ds = self::ds_tatca(); $thay = false;
		foreach ( $ds as $k => $v ) {
			if ( (int) $v['id'] === (int) $id && $v['so_luong'] >= 0 ) {
				$ds[ $k ]['so_luong'] = max( 0, (int) $v['so_luong'] - (int) $n );
				$thay = true; break;
			}
		}
		if ( $thay ) { self::luu_ds( $ds ); }
	}
	public static function ds_tatca() {
		$ds = get_option( 'pve_dichvu' );
		if ( ! is_array( $ds ) || ! $ds ) {
			$mac = array(); foreach ( self::VE_MAC_DINH as $v ) { $mac[] = self::chuan_hoa( $v, $v['id'] ); }
			return $mac;   // chuẩn hoá để có đủ trường (so_luong = -1 = không giới hạn)
		}
		$ra = array(); $auto = 1;
		foreach ( $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa( $v, $auto );
			if ( '' === $m['ten'] || $m['gia'] < 1000 ) { continue; }
			$ra[] = $m;
		}
		return $ra ? $ra : self::VE_MAC_DINH;
	}
	private static function ds() { $r = array(); foreach ( self::ds_tatca() as $v ) { if ( $v['hien'] ) { $r[] = $v; } } return $r; }
	private static function theo_id( $id ) { foreach ( self::ds() as $v ) { if ( (int) $v['id'] === (int) $id ) { return $v; } } return null; }
	private static function luu_ds( $ds ) {
		$ra = array(); $auto = 1;
		foreach ( (array) $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa( $v, $auto );
			if ( '' === $m['ten'] || $m['gia'] < 1000 ) { continue; }
			$ra[] = $m;
		}
		update_option( 'pve_dichvu', array_values( $ra ) );
	}

	// ───────────────────────────── Bảng vé ─────────────────────────────
	public static function tbl() { global $wpdb; return $wpdb->prefix . 'pve_ve'; }
	public static function tbl_tv() { global $wpdb; return $wpdb->prefix . 'pve_tv'; }
	public static function tbl_vi() { global $wpdb; return $wpdb->prefix . 'pve_vi'; }
	public static function tbl_vi_gd() { global $wpdb; return $wpdb->prefix . 'pve_vi_gd'; }
	public static function tbl_nap() { global $wpdb; return $wpdb->prefix . 'pve_nap'; }
	public static function bao_dam_bang() {
		if ( get_option( 'pve_tbl' ) === self::VER_TBL ) { return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tbl = self::tbl(); $col = $wpdb->get_charset_collate();
		/**
		 * 🔴 TUYỆT ĐỐI KHÔNG VIẾT CHÚ THÍCH BÊN TRONG CÂU CREATE TABLE.
		 *
		 * dbDelta() không phải trình phân tích SQL: nó cắt phần thân theo dấu phẩy cuối dòng rồi
		 * lấy TỪ ĐẦU TIÊN của mỗi mẩu làm tên cột. Một khối `/* … *\/` nằm giữa sẽ dính liền với
		 * dòng cột ngay sau nó, thành một "cột" tên `/*` — lệnh ALTER hỏng, CỘT THẬT KHÔNG ĐƯỢC
		 * TẠO, mà hàm vẫn chạy tiếp và vẫn đánh dấu đã nâng cấp nên không bao giờ thử lại.
		 *
		 * Đúng lỗi đã gây ra ở 1.38.0: ba cột coso/giam/gia_goc không có thật, mọi lượt đặt vé
		 * insert hỏng — khách bấm Mua vé mà không đơn nào vào sổ. Chú thích để HẾT ở đây.
		 *
		 * Ba cột thêm ở 1.38.0: `coso` = mua tại cửa hàng nào (rỗng = mua từ xa, giá gốc),
		 * `giam` = % đã giảm, `gia_goc` = giá trước giảm. Ghi cả ba để sau này tách được phần
		 * giảm ra khỏi phần khách mua vé rẻ khi đối chiếu doanh thu từng cơ sở.
		 */
		dbDelta( "CREATE TABLE $tbl (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ma_ve VARCHAR(24) NOT NULL,
			dv_ten VARCHAR(120) NOT NULL DEFAULT '',
			so_tien INT NOT NULL DEFAULT 0,
			ten_khach VARCHAR(80) NOT NULL DEFAULT '',
			sdt VARCHAR(20) NOT NULL DEFAULT '',
			noi_dung VARCHAR(40) NOT NULL DEFAULT '',
			chi_tiet TEXT NULL,
			nguon VARCHAR(10) NOT NULL DEFAULT 'web',
			trang_thai VARCHAR(12) NOT NULL DEFAULT 'cho',
			da_cong_diem TINYINT NOT NULL DEFAULT 0,
			coso VARCHAR(60) NOT NULL DEFAULT '',
			giam TINYINT NOT NULL DEFAULT 0,
			gia_goc INT NOT NULL DEFAULT 0,
			zalo_id VARCHAR(32) NOT NULL DEFAULT '',
			coso_dung VARCHAR(60) NOT NULL DEFAULT '',
			dung_luc DATETIME NULL,
			tao_luc DATETIME NOT NULL,
			tt_luc DATETIME NULL,
			PRIMARY KEY (id), UNIQUE KEY ma_ve (ma_ve), KEY trang_thai (trang_thai)
		) $col;" );
		$tv = self::tbl_tv();
		dbDelta( "CREATE TABLE $tv (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sdt VARCHAR(20) NOT NULL,
			ten VARCHAR(80) NOT NULL DEFAULT '',
			diem INT NOT NULL DEFAULT 0,
			tong_chi BIGINT NOT NULL DEFAULT 0,
			so_don INT NOT NULL DEFAULT 0,
			tao_luc DATETIME NOT NULL,
			sua_luc DATETIME NULL,
			PRIMARY KEY (id), UNIQUE KEY sdt (sdt), KEY diem (diem)
		) $col;" );
		/* 🔴 CHỈ ĐÁNH DẤU ĐÃ NÂNG CẤP KHI CỘT CÓ THẬT.
		   Bản trước đánh dấu vô điều kiện: dbDelta nuốt lỗi, cột không được tạo, mà lần sau hàm
		   này thấy "đã nâng cấp" nên thoát ngay — hỏng một lần là hỏng vĩnh viễn, chỉ chữa được
		   bằng cách sửa tay trong CSDL. Nay thiếu cột thì KHÔNG đánh dấu, lượt tải trang sau tự
		   thử lại, và ai đó sửa được nguyên nhân là nó tự lành. */
		/* ── VÍ TIỀN ──────────────────────────────────────────────────────────────────────
		   Ba bảng, mỗi bảng một việc, đừng gộp:
		    · pve_vi     = số dư hiện tại, mỗi chủ ví một dòng.
		    · pve_vi_gd  = SỔ CÁI từng lượt cộng/trừ, có số dư sau mỗi lượt. Không có sổ này thì
		                   khách kêu "mất tiền" là không có gì để đối chiếu, chỉ còn một con số.
		    · pve_nap    = lệnh nạp đang chờ tiền về; mã nạp là nội dung chuyển khoản.
		   ⚠️ Nhắc lại luật ở trên: KHÔNG chú thích bên trong câu CREATE TABLE. */
		$tvi = self::tbl_vi();
		dbDelta( "CREATE TABLE $tvi (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chu VARCHAR(48) NOT NULL,
			ten VARCHAR(80) NOT NULL DEFAULT '',
			sdt VARCHAR(20) NOT NULL DEFAULT '',
			so_du BIGINT NOT NULL DEFAULT 0,
			tong_nap BIGINT NOT NULL DEFAULT 0,
			tong_tang BIGINT NOT NULL DEFAULT 0,
			tong_tieu BIGINT NOT NULL DEFAULT 0,
			tao_luc DATETIME NOT NULL,
			sua_luc DATETIME NULL,
			PRIMARY KEY (id), UNIQUE KEY chu (chu)
		) $col;" );
		$tgd = self::tbl_vi_gd();
		dbDelta( "CREATE TABLE $tgd (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chu VARCHAR(48) NOT NULL,
			loai VARCHAR(8) NOT NULL DEFAULT 'nap',
			so_tien BIGINT NOT NULL DEFAULT 0,
			sau BIGINT NOT NULL DEFAULT 0,
			ma VARCHAR(24) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(140) NOT NULL DEFAULT '',
			tao_luc DATETIME NOT NULL,
			PRIMARY KEY (id), KEY chu (chu), KEY ma (ma)
		) $col;" );
		$tnp = self::tbl_nap();
		dbDelta( "CREATE TABLE $tnp (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ma VARCHAR(24) NOT NULL,
			chu VARCHAR(48) NOT NULL,
			so_tien INT NOT NULL DEFAULT 0,
			tang INT NOT NULL DEFAULT 0,
			code VARCHAR(24) NOT NULL DEFAULT '',
			noi_dung VARCHAR(40) NOT NULL DEFAULT '',
			trang_thai VARCHAR(12) NOT NULL DEFAULT 'cho',
			tao_luc DATETIME NOT NULL,
			tt_luc DATETIME NULL,
			PRIMARY KEY (id), UNIQUE KEY ma (ma), KEY chu (chu), KEY trang_thai (trang_thai)
		) $col;" );

		$cot = $wpdb->get_col( "SHOW COLUMNS FROM $tbl" );
		$can = array( 'coso', 'giam', 'gia_goc', 'zalo_id', 'coso_dung' );
		foreach ( $can as $c ) {
			if ( ! in_array( $c, (array) $cot, true ) ) { return; }
		}
		/* Ba bảng ví cũng phải dựng được thì mới coi là xong — cùng lý do với các cột ở trên:
		   đánh dấu sớm là hỏng vĩnh viễn, khách nạp tiền vào một cái bảng không tồn tại. */
		foreach ( array( $tvi, $tgd, $tnp ) as $t ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return; }
		}
		update_option( 'pve_tbl', self::VER_TBL );
	}

	/* Bảo đảm có sẵn 1 trang bán vé chứa [posh_ve]. Trả về ID trang (0 nếu lỗi). */
	public static function bao_dam_trang() {
		$pid = (int) get_option( 'pve_page_id' );
		if ( $pid && ( $p = get_post( $pid ) ) && 'trash' !== $p->post_status ) { return $pid; }
		global $wpdb;
		$co = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status IN ('publish','draft','pending') AND post_content LIKE '%[posh_ve]%' ORDER BY ID ASC LIMIT 1" );
		if ( $co ) { update_option( 'pve_page_id', $co ); return $co; }
		$moi = wp_insert_post( array(
			'post_title'   => 'Mua vé khu vui chơi',
			'post_name'    => 'mua-ve',
			'post_content' => '[posh_ve]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );
		if ( $moi && ! is_wp_error( $moi ) ) { update_option( 'pve_page_id', (int) $moi ); return (int) $moi; }
		return 0;
	}

	/* Bảo đảm có sẵn 1 trang QUẢN TRỊ VÉ (marketing) chứa [posh_ql]. Trả về ID (0 nếu lỗi). */
	public static function bao_dam_trang_ql() {
		$pid = (int) get_option( 'pve_ql_page_id' );
		if ( $pid && ( $p = get_post( $pid ) ) && 'trash' !== $p->post_status ) { return $pid; }
		global $wpdb;
		$co = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status IN ('publish','draft','pending') AND post_content LIKE '%[posh_ql]%' ORDER BY ID ASC LIMIT 1" );
		if ( $co ) { update_option( 'pve_ql_page_id', $co ); return $co; }
		$moi = wp_insert_post( array(
			'post_title'   => 'Quản trị vé (Marketing)',
			'post_name'    => 'quan-tri-ve',
			'post_content' => '[posh_ql]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );
		if ( $moi && ! is_wp_error( $moi ) ) { update_option( 'pve_ql_page_id', (int) $moi ); return (int) $moi; }
		return 0;
	}
	public static function url_trang_ql() {
		$pid = self::bao_dam_trang_ql();
		return $pid ? get_permalink( $pid ) : '';
	}

	// ───────────────────────────── Điểm & hạng thành viên (1 điểm = 1.000đ) ─────────────────────────────
	/* Cơ sở + toạ độ (để app gợi ý khu vực theo định vị). ten khớp với "khu_vuc" của vé. */
	public static function ds_coso() {
		$c = get_option( 'pve_coso' ); if ( ! is_array( $c ) ) { return array(); }
		$ra = array();
		foreach ( $c as $x ) {
			$ten = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			$ma = self::ma_coso( isset( $x['ma'] ) ? $x['ma'] : '' );
			if ( '' === $ma ) { $ma = self::ma_coso( $ten ); }
			$ra[] = array( 'ten' => $ten, 'ma' => $ma,
				'lat' => (float) ( isset( $x['lat'] ) ? $x['lat'] : 0 ),
				'lng' => (float) ( isset( $x['lng'] ) ? $x['lng'] : 0 ),
				/* % giảm khi khách quét tem QR ĐANG ĐỨNG tại cơ sở này. 0 = không giảm. */
				'giam' => max( 0, min( 90, (int) ( isset( $x['giam'] ) ? $x['giam'] : 0 ) ) ),
				/* Bán kính nhận là "đang ở đây" (mét). Đừng để quá nhỏ: GPS điện thoại trong nhà,
				   trong trung tâm thương mại, sai vài trăm mét là chuyện thường — siết 50m thì
				   khách đứng ngay quầy vẫn bị từ chối giảm, mà lỗi ấy nhân viên không giải thích được. */
				'bk' => max( 50, min( 5000, (int) ( isset( $x['bk'] ) && $x['bk'] ? $x['bk'] : 400 ) ) ) );
		}
		return $ra;
	}

	/**
	 * Chân trang: khối thông tin công ty + dòng số bản các plugin đang bật.
	 * Anh Thắng 11/09/2026: *"bổ sung thông tin này vào cuối trang"* + *"cả phiên bản vào để
	 * biết phiên bản bao nhiêu"*.
	 *
	 * ⚠️ THÔNG TIN CÔNG TY LẤY TỪ PLUGIN GHẾ (VHG_Chan), không chép lại sang đây. Mã số thuế,
	 *    địa chỉ, người đại diện mà nằm hai nơi thì đổi một nơi là hai trang nói hai kiểu — và
	 *    thứ sai lệch ở đây đi thẳng lên hoá đơn. Gác class_exists đúng luật gọi chéo: thiếu
	 *    plugin Ghế thì bỏ khối công ty, vẫn còn dòng số bản.
	 */
	public static function chan_trang_html() {
		$h = '';
		if ( class_exists( 'VHG_Chan' ) && method_exists( 'VHG_Chan', 'html' ) ) {
			$khoi = VHG_Chan::html();
			if ( '' !== $khoi ) { $h .= '<style>' . VHG_Chan::css() . '</style>' . $khoi; }
		}
		$ban = self::dong_phien_ban();
		if ( '' === $ban ) { $ban = 'Vé ' . self::phien_ban(); }   // ít nhất phải biết bản của trang này
		$h .= '<div class="pve-ban">© ' . esc_html( gmdate( 'Y' ) ) . ' '
			. esc_html( self::ten_cty_ngan() ) . '. Toàn bộ bản quyền thuộc công ty. '
			. '<span class="pve-ban-s">' . esc_html( $ban ) . '</span> <span class="pve-ban-s pve-ban-js">JS chưa chạy</span></div>';
		return $h;
	}

	/**
	 * "Vé 1.43.2 · Ghế 2.34.2 · Chấm công 3.63.0 …" — đọc THẲNG từ header của các plugin đang
	 * bật, không viết cứng. Viết cứng thì mỗi lần nâng cấp một plugin là dòng này nói dối, mà
	 * nói dối ở đúng chỗ người ta dùng để kiểm "đã lên bản mới chưa" thì tệ hơn không có.
	 *
	 * Nhớ 1 giờ: mỗi lượt đọc là mở header vài tệp trên đĩa, trang bán vé thì tải liên tục.
	 */
	/* Tên công ty cho dòng bản quyền — mượn của plugin Ghế, không gõ cứng. */
	public static function ten_cty_ngan() {
		if ( class_exists( 'VHG_Chan' ) && method_exists( 'VHG_Chan', 'thong_tin' ) ) {
			$t = VHG_Chan::thong_tin();
			if ( ! empty( $t['ten'] ) ) { return (string) $t['ten']; }
		}
		$x = trim( (string) get_option( 'pve_ft_ten', '' ) );
		return '' !== $x ? $x : get_bloginfo( 'name' );
	}

	public static function dong_phien_ban() {
		/* ⚠️ KHÔNG nhớ đệm dòng này nữa. Dòng số bản chính là thứ người ta nhìn để biết
		   "trang đã lên bản mới chưa" — đem nhớ đệm 1 giờ thì ngay sau khi cài đè nó vẫn khoe số
		   cũ, và ta lại đi tìm lỗi ở chỗ không có lỗi. Đọc header vài tệp là rẻ hơn nhiều so với
		   một buổi dò nhầm hướng. */
		$ra = array();
		if ( function_exists( 'wp_get_active_and_valid_plugins' ) ) {
			foreach ( wp_get_active_and_valid_plugins() as $tep ) {
				$duong = str_replace( '\\', '/', (string) $tep );
				if ( false === strpos( $duong, '/vhcp-' ) ) { continue; }   // chỉ plugin của nhà
				$d = get_file_data( $tep, array( 'n' => 'Plugin Name', 'v' => 'Version' ) );
				if ( empty( $d['v'] ) ) { continue; }
				$ten = trim( preg_replace( '/\s*\(K&H\)\s*$/u', '', (string) $d['n'] ) );
				if ( '' === $ten ) { $ten = basename( dirname( $duong ) ); }
				$ra[] = $ten . ' ' . $d['v'];
			}
		}
		sort( $ra, SORT_NATURAL | SORT_FLAG_CASE );
		return $ra ? implode( ' · ', $ra ) : '';
	}

	/* ═══ DANH MỤC VÉ ═══════════════════════════════════════════════════════════════════════
	 * Anh Thắng 11/09/2026: *"muốn tạo phân loại"* ngay trong màn quản trị marketing, thay vì gõ
	 * tay ô "Nhóm" cho từng vé.
	 *
	 * ⚠️ DANH TÍNH CỦA DANH MỤC LÀ CÁI TÊN. Vé vẫn lưu tên nhóm dưới dạng chữ (`nhom`) như từ
	 *    trước — cố ý, để không phải chuyển đổi dữ liệu cũ và để vé gõ tay một tên mới vẫn chạy.
	 *    Hệ quả: ĐỔI TÊN DANH MỤC PHẢI SỬA LUÔN MỌI VÉ ĐANG MANG TÊN CŨ, không thì vé rơi ra
	 *    một danh mục vô hình. Xem doi_ten_dm().
	 * ⚠️ Danh sách trả về GỘP hai nguồn: danh mục đã khai + tên nhóm bắt gặp trên vé. Chỉ đọc
	 *    bảng đã khai thì danh mục cũ (gõ tay trước khi có màn này) biến mất khỏi dải chọn.
	 */
	public static function ds_dm() {
		$luu = get_option( 'pve_dm' ); $luu = is_array( $luu ) ? $luu : array();
		$ra = array(); $co = array();
		foreach ( $luu as $d ) {
			$t = trim( (string) ( isset( $d['ten'] ) ? $d['ten'] : '' ) );
			if ( '' === $t || isset( $co[ $t ] ) ) { continue; }
			$co[ $t ] = 1;
			$ra[] = array( 'ten' => $t, 'anh' => (string) ( isset( $d['anh'] ) ? $d['anh'] : '' ), 'khai' => 1, 'so_ve' => 0 );
		}
		foreach ( self::ds_tatca() as $g ) {
			$t = trim( (string) $g['nhom'] ); if ( '' === $t ) { $t = 'Vé'; }
			if ( ! isset( $co[ $t ] ) ) { $co[ $t ] = 1; $ra[] = array( 'ten' => $t, 'anh' => '', 'khai' => 0, 'so_ve' => 0 ); }
		}
		/* Đếm vé từng danh mục — quản trị cần biết xoá cái này thì bao nhiêu vé phải dọn. */
		foreach ( $ra as $i => $d ) {
			$n = 0;
			foreach ( self::ds_tatca() as $g ) {
				$t = trim( (string) $g['nhom'] ); if ( '' === $t ) { $t = 'Vé'; }
				if ( $t === $d['ten'] ) { $n++; }
			}
			$ra[ $i ]['so_ve'] = $n;
			if ( '' === $ra[ $i ]['anh'] ) { $ra[ $i ]['anh'] = self::anh_nhom( $d['ten'], array() ); }
		}
		return $ra;
	}

	public static function luu_dm( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $d ) {
			$t = trim( (string) ( isset( $d['ten'] ) ? $d['ten'] : '' ) );
			if ( '' === $t ) { continue; }
			$ra[] = array( 'ten' => mb_substr( $t, 0, 60 ), 'anh' => esc_url_raw( (string) ( isset( $d['anh'] ) ? $d['anh'] : '' ) ) );
		}
		update_option( 'pve_dm', array_values( $ra ) );
	}

	/* Đổi tên danh mục: sửa bảng danh mục VÀ mọi vé đang mang tên cũ, trong một lượt. */
	public static function doi_ten_dm( $cu, $moi ) {
		$cu = trim( (string) $cu ); $moi = trim( (string) $moi );
		if ( '' === $cu || '' === $moi || $cu === $moi ) { return; }
		$ds = self::ds_tatca(); $doi = false;
		foreach ( $ds as $i => $g ) {
			$t = trim( (string) $g['nhom'] ); if ( '' === $t ) { $t = 'Vé'; }
			if ( $t === $cu ) { $ds[ $i ]['nhom'] = $moi; $doi = true; }
		}
		if ( $doi ) { self::luu_ds( $ds ); }
		$m = self::ds_nhom_anh();
		if ( isset( $m[ $cu ] ) ) { $m[ $moi ] = $m[ $cu ]; unset( $m[ $cu ] ); update_option( 'pve_nhom_anh', $m ); }
	}

	/* Chuyển hết vé của một danh mục sang danh mục khác (dùng khi xoá). */
	public static function chuyen_ve_dm( $tu, $sang ) {
		$tu = trim( (string) $tu ); $sang = trim( (string) $sang );
		if ( '' === $sang ) { $sang = 'Vé'; }
		$ds = self::ds_tatca(); $doi = false;
		foreach ( $ds as $i => $g ) {
			$t = trim( (string) $g['nhom'] ); if ( '' === $t ) { $t = 'Vé'; }
			if ( $t === $tu ) { $ds[ $i ]['nhom'] = $sang; $doi = true; }
		}
		if ( $doi ) { self::luu_ds( $ds ); }
	}

	/* Ảnh cho từng DANH MỤC vé (tên nhóm -> URL). Quản trị khai ở màn admin; bỏ trống thì lấy
	   ảnh của vé đầu tiên trong nhóm — dải danh mục có hình ngay từ đầu, không bắt ai đi khai
	   thêm một vòng nữa mới thấy được thành quả. */
	public static function ds_nhom_anh() {
		$v = get_option( 'pve_nhom_anh' );
		return is_array( $v ) ? $v : array();
	}

	/* Ảnh đại diện của một danh mục: ảnh khai riêng -> ảnh vé đầu tiên có ảnh -> rỗng. */
	public static function anh_nhom( $ten_nhom, $ds_ve ) {
		/* ⚠️ Đọc THẲNG option pve_dm, không gọi ds_dm() — ds_dm() gọi ngược lại hàm này để điền
		   ảnh, hai bên gọi nhau là đệ quy vô tận, trang trắng. */
		$dm = get_option( 'pve_dm' );
		if ( is_array( $dm ) ) {
			foreach ( $dm as $d ) {
				if ( isset( $d['ten'] ) && trim( (string) $d['ten'] ) === $ten_nhom && ! empty( $d['anh'] ) ) {
					return (string) $d['anh'];
				}
			}
		}
		$m = self::ds_nhom_anh();   // bảng cũ khai ở wp-admin, vẫn dùng được
		if ( ! empty( $m[ $ten_nhom ] ) ) { return (string) $m[ $ten_nhom ]; }
		foreach ( (array) $ds_ve as $g ) { if ( ! empty( $g['anh'] ) ) { return (string) $g['anh']; } }
		return '';
	}

	/* Mã cơ sở dùng trong đường dẫn tem QR: bỏ dấu, chỉ chữ và số, tối đa 12 ký tự.
	   Sinh từ tên nếu chưa khai, nên tem cũ vẫn chạy khi quản trị chưa đụng tới ô mã. */
	public static function ma_coso( $s ) {
		$s = strtoupper( remove_accents( (string) $s ) );
		return substr( preg_replace( '/[^A-Z0-9]/', '', $s ), 0, 12 );
	}

	public static function coso_theo_ma( $ma ) {
		$ma = self::ma_coso( $ma ); if ( '' === $ma ) { return null; }
		foreach ( self::ds_coso() as $c ) { if ( $c['ma'] === $ma ) { return $c; } }
		return null;
	}

	/* Khoảng cách hai điểm trên mặt đất, mét (haversine). */
	public static function kc_met( $la1, $ln1, $la2, $ln2 ) {
		$R = 6371000.0; $p = M_PI / 180;
		$a = 0.5 - cos( ( $la2 - $la1 ) * $p ) / 2
			+ cos( $la1 * $p ) * cos( $la2 * $p ) * ( 1 - cos( ( $ln2 - $ln1 ) * $p ) ) / 2;
		return (int) round( 2 * $R * asin( sqrt( max( 0, $a ) ) ) );
	}

	/**
	 * 🔴 CỬA DUY NHẤT QUYẾT ĐỊNH CÓ GIẢM GIÁ HAY KHÔNG — và nó nằm ở MÁY CHỦ.
	 *
	 * Trang khách cũng tính khoảng cách để hiện nhãn, nhưng đó chỉ là phần nhìn. Giá thật phải
	 * chốt ở đây: mọi thứ trình duyệt gửi lên (kể cả toạ độ) đều là thứ người ta sửa được bằng
	 * công cụ có sẵn trong trình duyệt. Tin trang khách nghĩa là ai cũng mua được giá giảm từ nhà.
	 *
	 * Vẫn còn một lỗ không bịt được bằng mã: điện thoại giả toạ độ GPS. Chấp nhận — mức giảm là
	 * khuyến mãi, không phải tiền mặt, và chặn kỹ hơn thì phải có màn hình đổi mã ở quầy (đắt hơn
	 * nhiều so với thứ nó bảo vệ). Ghi ra đây để người sau khỏi tưởng đã kín.
	 *
	 * @return array ma, ten, giam (%), ok (bool), vi (lý do khi không giảm), kc (mét, -1 = không rõ)
	 */
	public static function giam_tai_cho( $ma, $lat, $lng ) {
		$ra = array( 'ma' => '', 'ten' => '', 'giam' => 0, 'ok' => false, 'vi' => '', 'kc' => -1 );
		$c  = self::coso_theo_ma( $ma );
		if ( ! $c ) { $ra['vi'] = 'khong_co_coso'; return $ra; }
		$ra['ma'] = $c['ma']; $ra['ten'] = $c['ten'];
		if ( $c['giam'] <= 0 )                { $ra['vi'] = 'coso_khong_giam'; return $ra; }
		if ( ! $c['lat'] || ! $c['lng'] )     { $ra['vi'] = 'coso_chua_khai_toado'; return $ra; }
		$lat = (float) $lat; $lng = (float) $lng;
		if ( ! $lat || ! $lng || abs( $lat ) > 90 || abs( $lng ) > 180 ) { $ra['vi'] = 'khong_co_vi_tri'; return $ra; }
		$kc = self::kc_met( $lat, $lng, $c['lat'], $c['lng'] );
		$ra['kc'] = $kc;
		if ( $kc > $c['bk'] ) { $ra['vi'] = 'o_xa'; return $ra; }
		$ra['giam'] = $c['giam']; $ra['ok'] = true;
		return $ra;
	}

	/* Đường dẫn tem QR dán tại quầy của một cơ sở. */
	public static function link_tem( $ma ) {
		$pid = (int) get_option( 'pve_page_id' );
		$goc = $pid ? get_permalink( $pid ) : home_url( '/mua-ve/' );
		return add_query_arg( 'cs', self::ma_coso( $ma ), $goc );
	}

	/**
	 * Tem QR để in dán quầy. Vẽ bằng bộ dựng QR của plugin Ghế nếu có.
	 *
	 * ⚠️ GÁC `class_exists` NGAY TẠI ĐÂY, đúng luật gọi chéo của cả hệ: hai plugin cài độc lập
	 *    nhau, không được giả định plugin Ghế có mặt. Thiếu nó thì vẫn in ra ĐƯỜNG DẪN để quản
	 *    trị tự tạo QR bằng công cụ ngoài — mất tấm hình còn hơn mất cả tính năng.
	 * ⚠️ Ma trận rỗng = KHÔNG in tem (xem chú thích ở VHG_QRVe::ma_tran): một tấm tem dán lên
	 *    tường mà không quét được thì tệ hơn hẳn chưa dán tem nào.
	 */
	public static function tem_qr_html( $ma, $ten = '' ) {
		$ma = self::ma_coso( $ma ); if ( '' === $ma ) { return '<span class="description">Lưu để sinh mã</span>'; }
		$url = self::link_tem( $ma );
		$svg = '';
		if ( class_exists( 'VHG_QRVe' ) && method_exists( 'VHG_QRVe', 'ma_tran' ) ) {
			$mt = VHG_QRVe::ma_tran( $url, 'M' );
			if ( is_array( $mt ) && count( $mt ) ) { $svg = VHG_QRVe::svg( $mt, 150 ); }
		}
		$ra = $svg ? ( '<div>' . $svg . '</div>' )
			: '<div class="description" style="color:#b45309">Chưa vẽ được mã (cần plugin Ghế Massage) — dùng đường dẫn dưới đây.</div>';
		$ra .= '<div style="word-break:break-all"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><code>'
			. esc_html( $url ) . '</code></a></div>';
		if ( $svg ) { $ra .= '<div class="description">In ra, dán tại quầy ' . esc_html( $ten ) . '.</div>'; }
		return $ra;
	}

	/* Giá sau giảm, làm tròn xuống bội 1.000đ — giá lẻ tới đồng trên tem vé trông như lỗi tính. */
	public static function gia_sau_giam( $gia, $pc ) {
		$gia = (int) $gia; $pc = (int) $pc;
		if ( $pc <= 0 ) { return $gia; }
		return max( 0, (int) ( floor( $gia * ( 100 - $pc ) / 100 / 1000 ) * 1000 ) );
	}
	public static function ds_hang() {
		$h = get_option( 'pve_hang' );
		if ( ! is_array( $h ) || ! $h ) { return self::HANG_MAC_DINH; }
		$ra = array();
		foreach ( $h as $x ) {
			$ten = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			$ra[] = array( 'ten' => $ten, 'moc' => max( 0, (int) ( isset( $x['moc'] ) ? $x['moc'] : 0 ) ) );
		}
		if ( ! $ra ) { return self::HANG_MAC_DINH; }
		usort( $ra, function ( $a, $b ) { return $a['moc'] - $b['moc']; } );
		return $ra;
	}
	/* Hạng hiện tại + hạng kế + điểm còn thiếu để lên hạng. */
	public static function hang_cua( $diem ) {
		$ds = self::ds_hang(); $cur = $ds[0]; $ke = null;
		foreach ( $ds as $h ) {
			if ( $diem >= $h['moc'] ) { $cur = $h; } elseif ( null === $ke ) { $ke = $h; }
		}
		return array(
			'hang'      => $cur['ten'],
			'hang_ke'   => $ke ? $ke['ten'] : '',
			'con_thieu' => $ke ? max( 0, $ke['moc'] - (int) $diem ) : 0,
		);
	}
	/* Cộng điểm cho 1 số điện thoại (1 điểm = 1.000đ). */
	public static function cong_diem( $sdt, $ten, $so_tien ) {
		$sdt = preg_replace( '/[^0-9+]/', '', (string) $sdt );
		if ( '' === $sdt ) { return; }
		global $wpdb; $tv = self::tbl_tv();
		$diem = (int) floor( (int) $so_tien / 1000 );
		$cu = $wpdb->get_row( $wpdb->prepare( "SELECT id, diem, tong_chi, so_don FROM $tv WHERE sdt=%s", $sdt ), ARRAY_A );
		if ( $cu ) {
			$wpdb->update( $tv, array(
				'ten' => mb_substr( (string) $ten, 0, 80 ),
				'diem' => (int) $cu['diem'] + $diem,
				'tong_chi' => (int) $cu['tong_chi'] + (int) $so_tien,
				'so_don' => (int) $cu['so_don'] + 1,
				'sua_luc' => current_time( 'mysql' ),
			), array( 'id' => (int) $cu['id'] ) );
		} else {
			$wpdb->insert( $tv, array(
				'sdt' => mb_substr( $sdt, 0, 20 ), 'ten' => mb_substr( (string) $ten, 0, 80 ),
				'diem' => $diem, 'tong_chi' => (int) $so_tien, 'so_don' => 1,
				'tao_luc' => current_time( 'mysql' ), 'sua_luc' => current_time( 'mysql' ),
			) );
		}
	}

	// ───────────────────────────── REST ─────────────────────────────
	public static function cron_do_saoke() {
		self::do_lai_saoke();
		self::do_lai_nap();
	}

	public static function dang_ky() {
		register_rest_route( self::NS, '/ve/goi', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_goi' ) ) );
		register_rest_route( self::NS, '/ve/dat', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_dat' ) ) );
		register_rest_route( self::NS, '/ve/dat-gio', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_dat_gio' ) ) );
		register_rest_route( self::NS, '/ve/thanhtoan', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_thanhtoan' ) ) );
		register_rest_route( self::NS, '/ve/momo-ipn', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_momo_ipn' ) ) );
		register_rest_route( self::NS, '/ve/vnpay-ipn', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_vnpay_ipn' ) ) );
		register_rest_route( self::NS, '/ve/trangthai', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_trangthai' ) ) );
		register_rest_route( self::NS, '/ve/vi', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_vi' ) ) );
		register_rest_route( self::NS, '/ve/cho-soat', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_cho_soat' ) ) );
		register_rest_route( self::NS, '/ve/soat', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_soat' ) ) );
		register_rest_route( self::NS, '/vi/toi', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_vi_toi' ) ) );
		register_rest_route( self::NS, '/vi/thu-ma', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_vi_thu_ma' ) ) );
		register_rest_route( self::NS, '/vi/nap', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_vi_nap' ) ) );
		register_rest_route( self::NS, '/vi/nap-tt', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_vi_nap_tt' ) ) );
		register_rest_route( self::NS, '/tin', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_tin' ) ) );
		register_rest_route( self::NS, '/tv', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_tv' ) ) );
		register_rest_route( self::NS, '/uudai', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_uudai' ) ) );
		register_rest_route( self::NS, '/zalo/sdt', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_zalo_sdt' ) ) );
		register_rest_route( self::NS, '/zalo/toi', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_zalo_toi' ) ) );
		register_rest_route( self::NS, '/zalo/cb', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_zalo_cb' ) ) );
		// Khu quản lý (nhân viên) — bảo vệ bằng PIN khai ở admin (không hardcode).
		register_rest_route( self::NS, '/ql/dangnhap', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_dangnhap' ) ) );
		register_rest_route( self::NS, '/ql/do-saoke', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_do_saoke' ) ) );
		register_rest_route( self::NS, '/ql/baocao', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_baocao' ) ) );
		register_rest_route( self::NS, '/ql/donhang', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_donhang' ) ) );
		register_rest_route( self::NS, '/ql/capnhat', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_capnhat' ) ) );
		// Marketing tạo/sửa/xoá vé từ web (bảo vệ bằng PIN) — vé tự lên web + Zalo.
		register_rest_route( self::NS, '/ql/ve-ds', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_ve_ds' ) ) );
		register_rest_route( self::NS, '/ql/ve-luu', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_ve_luu' ) ) );
		register_rest_route( self::NS, '/ql/ve-xoa', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_ve_xoa' ) ) );
		register_rest_route( self::NS, '/ql/ve-anh', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_ve_anh' ) ) );
		// Marketing quản lý ưu đãi từ web (PIN).
		register_rest_route( self::NS, '/ql/uu-ds', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_uu_ds' ) ) );
		register_rest_route( self::NS, '/ql/uu-luu', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_uu_luu' ) ) );
		register_rest_route( self::NS, '/ql/uu-xoa', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_uu_xoa' ) ) );
		register_rest_route( self::NS, '/ql/dm-ds', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_dm_ds' ) ) );
		register_rest_route( self::NS, '/ql/dm-luu', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_dm_luu' ) ) );
		register_rest_route( self::NS, '/ql/dm-xoa', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_dm_xoa' ) ) );
		register_rest_route( self::NS, '/ql/tk-ds', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_tk_ds' ) ) );
		register_rest_route( self::NS, '/ql/tk-luu', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_tk_luu' ) ) );
		register_rest_route( self::NS, '/ql/soi', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_soi' ) ) );
		register_rest_route( self::NS, '/ql/hang-ds', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_hang_ds' ) ) );
		register_rest_route( self::NS, '/ql/hang-luu', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_hang_luu' ) ) );
		register_rest_route( self::NS, '/ql/khach', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_khach' ) ) );
	}
	/* Trang có [posh_ve] -> tắt admin bar cho gọn (chạy sớm ở hook wp). */
	public static function an_admin_bar() {
		if ( ! is_singular() ) { return; }
		$p = get_post();
		if ( $p && ( has_shortcode( (string) $p->post_content, 'posh_ve' ) || has_shortcode( (string) $p->post_content, 'posh_ql' ) ) ) {
			add_filter( 'show_admin_bar', '__return_false' );
		}
	}
	public static function cors( $served, $result, $request, $server ) {
		if ( $request && 0 === strpos( (string) $request->get_route(), '/' . self::NS ) ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
		}
		return $served;
	}
	public static function r_goi() {
		$goi = array();
		foreach ( self::ds() as $g ) {
			$goi[] = array( 'ma' => (int) $g['id'], 'ten' => $g['ten'], 'tien' => (int) $g['gia'],
				'gia_goc' => (int) $g['gia_goc'], 'nhom' => (string) $g['nhom'], 'khu_vuc' => (string) $g['khu_vuc'],
				'mo_ta' => (string) $g['mo_ta'], 'anh' => (string) $g['anh'], 'thoi_luong' => (string) $g['thoi_luong'],
				'so_luong' => (int) $g['so_luong'] );
		}
		$b = self::bank();
		return array( 'ok' => true, 'goi' => $goi, 'co_so' => self::ds_coso(),
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}
	public static function r_dat( $req ) {
		$id  = (int) $req->get_param( 'id' );
		$ten = sanitize_text_field( (string) $req->get_param( 'ten' ) );
		$sdt = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'sdt' ) );
		$goi = self::theo_id( $id );
		if ( ! $goi ) {                                  // tương thích app cũ gửi `tien`
			$t = (int) $req->get_param( 'tien' );
			if ( $t > 0 ) { foreach ( self::ds() as $v ) { if ( (int) $v['gia'] === $t ) { $goi = $v; break; } } }
		}
		if ( ! $goi ) { return new WP_Error( 'goi', 'Vé không hợp lệ.', array( 'status' => 400 ) ); }
		if ( $goi['so_luong'] >= 0 && $goi['so_luong'] < 1 ) { return new WP_Error( 'het', 'Vé "' . $goi['ten'] . '" đã hết.', array( 'status' => 409 ) ); }
		if ( '' === $ten || '' === $sdt ) { return new WP_Error( 'thieu', 'Cần tên và số điện thoại.', array( 'status' => 400 ) ); }
		$b = self::bank();
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) { return new WP_Error( 'tk', 'Chưa cấu hình tài khoản nhận tiền.', array( 'status' => 409 ) ); }
		if ( ! self::nhip_ok() ) { return new WP_Error( 'nhip', 'Thao tác quá nhanh, thử lại sau giây lát.', array( 'status' => 429 ) ); }

		global $wpdb;
		/* Giảm giá TẠI CHỖ: máy chủ tự kiểm lại, không lấy giá trang khách gửi lên. */
		$g0  = self::giam_tai_cho( $req->get_param( 'cs' ), $req->get_param( 'lat' ), $req->get_param( 'lng' ) );
		$goc = (int) $goi['gia'];
		$tien = self::gia_sau_giam( $goc, $g0['ok'] ? $g0['giam'] : 0 );
		$ma_ve = self::ma_ve_moi(); $noidung = 'VE' . $ma_ve;
		$ok_ghi = $wpdb->insert( self::tbl(), array(
			'ma_ve' => $ma_ve, 'dv_ten' => $goi['ten'], 'so_tien' => $tien,
			'ten_khach' => mb_substr( $ten, 0, 80 ), 'sdt' => mb_substr( $sdt, 0, 20 ),
			'noi_dung' => $noidung, 'nguon' => ( 'zalo' === $req->get_param( 'nguon' ) ? 'zalo' : 'web' ),
			'zalo_id' => self::zalo_id_hien(),
			'coso' => $g0['ok'] ? mb_substr( $g0['ten'], 0, 60 ) : '',
			'giam' => $g0['ok'] ? (int) $g0['giam'] : 0,
			'gia_goc' => $goc,
			'trang_thai' => 'cho', 'tao_luc' => current_time( 'mysql' ),
		) );
		/* 🔴 KIỂM KẾT QUẢ GHI SỔ. Bản trước bỏ qua giá trị trả về: bảng thiếu một cột là insert
		   hỏng, mà hàm vẫn trả về ok kèm mã QR — khách quét, chuyển tiền, còn đơn thì không có
		   trong sổ. Thà báo lỗi ngay lúc bấm còn hơn nhận tiền của một đơn không tồn tại. */
		if ( ! $ok_ghi ) {
			return new WP_Error( 'ghi', 'Không ghi được đơn vào sổ vé. Báo quản trị kiểm bảng dữ liệu.', array( 'status' => 500 ) );
		}
		self::giam_ton( $goi['id'], 1 );
		$vi = self::tra_bang_vi( $req, $ma_ve, $tien, $goi['ten'] );
		if ( is_wp_error( $vi ) ) { return $vi; }
		$qr = self::vietqr( $b['bin'], $b['so_tk'], $tien, $noidung );
		return array( 'ok' => true, 'ma_ve' => $ma_ve, 'so_tien' => $tien, 'goi_ten' => $goi['ten'],
			'noi_dung' => $noidung, 'qr' => $qr, 'qr_svg' => self::qr_svg( $qr ),
			'trang_thai' => $vi ? 'da_tt' : 'cho', 'tra_vi' => $vi ? 1 : 0, 'so_du' => self::vi_so_du( self::vi_chu() ),
			'gia_goc' => $goc, 'giam' => $g0['ok'] ? (int) $g0['giam'] : 0, 'coso' => $g0['ok'] ? $g0['ten'] : '',
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}
	/* Đặt nhiều vé trong 1 giỏ -> 1 đơn, 1 mã QR tổng. items = [{id, sl}, ...] */
	public static function r_dat_gio( $req ) {
		$items = $req->get_param( 'items' );
		$ten   = sanitize_text_field( (string) $req->get_param( 'ten' ) );
		$sdt   = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'sdt' ) );
		if ( ! is_array( $items ) || ! $items ) { return new WP_Error( 'gio', 'Giỏ hàng trống.', array( 'status' => 400 ) ); }
		if ( '' === $ten || '' === $sdt ) { return new WP_Error( 'thieu', 'Cần tên và số điện thoại.', array( 'status' => 400 ) ); }
		$b = self::bank();
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) { return new WP_Error( 'tk', 'Chưa cấu hình tài khoản nhận tiền.', array( 'status' => 409 ) ); }
		if ( ! self::nhip_ok() ) { return new WP_Error( 'nhip', 'Thao tác quá nhanh, thử lại sau giây lát.', array( 'status' => 429 ) ); }

		/* Cùng một cửa kiểm với r_dat — giảm giá chốt ở máy chủ, không tin giá trang khách gửi. */
		$g0 = self::giam_tai_cho( $req->get_param( 'cs' ), $req->get_param( 'lat' ), $req->get_param( 'lng' ) );
		$pc = $g0['ok'] ? (int) $g0['giam'] : 0;
		$tong = 0; $tong_goc = 0; $mota = array(); $ct = array(); $can = array();
		foreach ( $items as $it ) {
			$id = (int) ( isset( $it['id'] ) ? $it['id'] : 0 );
			$sl = max( 1, (int) ( isset( $it['sl'] ) ? $it['sl'] : 1 ) );
			$g  = self::theo_id( $id );
			if ( ! $g ) { continue; }
			$can[ $id ] = ( isset( $can[ $id ] ) ? $can[ $id ] : 0 ) + $sl;
			if ( $g['so_luong'] >= 0 && $g['so_luong'] < $can[ $id ] ) {
				return new WP_Error( 'het', 'Vé "' . $g['ten'] . '" không đủ số lượng.', array( 'status' => 409 ) );
			}
			/* Giảm trên GIÁ TỪNG VÉ rồi mới nhân số lượng — giảm trên tổng rồi chia ngược lại
			   thì đơn giá trên tem lệch vài đồng với tổng, và kế toán sẽ đi tìm chỗ lệch ấy. */
			$don_goc = (int) $g['gia'];
			$don     = self::gia_sau_giam( $don_goc, $pc );
			$tong     += $don * $sl;
			$tong_goc += $don_goc * $sl;
			$mota[] = $sl . 'x ' . $g['ten'];
			$ct[]   = array( 'id' => $id, 'ten' => $g['ten'], 'gia' => $don, 'gia_goc' => $don_goc, 'sl' => $sl );
		}
		if ( $tong < 1000 ) { return new WP_Error( 'gio', 'Giỏ hàng không hợp lệ.', array( 'status' => 400 ) ); }

		global $wpdb;
		$ma_ve = self::ma_ve_moi(); $noidung = 'VE' . $ma_ve; $tomtat = implode( ', ', $mota );
		$ok_ghi = $wpdb->insert( self::tbl(), array(
			'ma_ve' => $ma_ve, 'dv_ten' => mb_substr( $tomtat, 0, 120 ), 'so_tien' => $tong,
			'ten_khach' => mb_substr( $ten, 0, 80 ), 'sdt' => mb_substr( $sdt, 0, 20 ),
			'noi_dung' => $noidung, 'chi_tiet' => wp_json_encode( $ct ),
			'nguon' => ( 'zalo' === $req->get_param( 'nguon' ) ? 'zalo' : 'web' ),
			'zalo_id' => self::zalo_id_hien(),
			'coso' => $g0['ok'] ? mb_substr( $g0['ten'], 0, 60 ) : '',
			'giam' => $pc, 'gia_goc' => $tong_goc,
			'trang_thai' => 'cho', 'tao_luc' => current_time( 'mysql' ),
		) );
		/* Cùng lý do với r_dat: insert hỏng mà vẫn trả QR là nhận tiền cho đơn không tồn tại. */
		if ( ! $ok_ghi ) {
			return new WP_Error( 'ghi', 'Không ghi được đơn vào sổ vé. Báo quản trị kiểm bảng dữ liệu.', array( 'status' => 500 ) );
		}
		foreach ( $can as $id => $sl ) { self::giam_ton( $id, $sl ); }
		$vi = self::tra_bang_vi( $req, $ma_ve, $tong, $tomtat );
		if ( is_wp_error( $vi ) ) { return $vi; }
		$qr = self::vietqr( $b['bin'], $b['so_tk'], $tong, $noidung );
		return array( 'ok' => true, 'ma_ve' => $ma_ve, 'so_tien' => $tong, 'goi_ten' => $tomtat,
			'noi_dung' => $noidung, 'qr' => $qr, 'qr_svg' => self::qr_svg( $qr ),
			'trang_thai' => $vi ? 'da_tt' : 'cho', 'tra_vi' => $vi ? 1 : 0, 'so_du' => self::vi_so_du( self::vi_chu() ),
			'chi_tiet' => $ct,
			'gia_goc' => $tong_goc, 'giam' => $pc, 'coso' => $g0['ok'] ? $g0['ten'] : '',
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}
	public static function r_trangthai( $req ) {
		global $wpdb;
		$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $req->get_param( 'ma_ve' ) ) );
		if ( '' === $ma ) { return new WP_Error( 'ma', 'Thiếu mã vé.', array( 'status' => 400 ) ); }
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT ma_ve, dv_ten, so_tien, noi_dung, trang_thai, tao_luc, tt_luc FROM ' . self::tbl() . ' WHERE ma_ve=%s', $ma ), ARRAY_A );
		if ( ! $r ) { return new WP_Error( 'khong_co', 'Không tìm thấy vé.', array( 'status' => 404 ) ); }
		/* Còn chờ thì soi sổ phụ xem tiền vào chưa — xem chú thích ở tu_khop(). */
		if ( 'cho' === $r['trang_thai'] && self::tu_khop( $r['ma_ve'], (int) $r['so_tien'], $r['noi_dung'] ) ) {
			$r['trang_thai'] = 'da_tt';
			$r['tt_luc']     = current_time( 'mysql' );
		}
		return array( 'ok' => true, 'ma_ve' => $r['ma_ve'], 'goi_ten' => $r['dv_ten'], 'so_tien' => (int) $r['so_tien'],
			'trang_thai' => $r['trang_thai'], 'tao_luc' => $r['tao_luc'], 'tt_luc' => $r['tt_luc'] );
	}

	/**
	 * TỰ KHỚP CHUYỂN KHOẢN VIETQR VỚI VÉ.
	 *
	 * ⚠️ TRƯỚC BẢN 1.48.0 KHÔNG CÓ ĐƯỜNG NÀY. Vé đánh dấu "đã thanh toán" chỉ qua hai lối: IPN
	 * của Momo/VNPay, hoặc quản trị bấm tay. Khách quét mã VietQR chuyển tiền xong thì vé nằm mãi
	 * ở "Chờ thanh toán" — đúng cái anh Thắng thấy: *"mua vé thành công mà chưa xác nhận"*. Tiền
	 * đã vào tài khoản mà hệ thống không biết, nhân viên soát vé cũng không dám cho vào.
	 *
	 * Bắc cầu sang plugin Sao Kê: tìm một giao dịch TIỀN VÀO có nội dung chứa đúng chuỗi đã in
	 * trên mã QR (VE + mã vé) và số tiền không thiếu. Nội dung ấy là duy nhất cho từng vé nên
	 * không có chuyện một lượt chuyển khoản khớp cho hai vé.
	 *
	 * Gọi ngay trong lúc trang khách hỏi trạng thái (mỗi 5 giây) -> tiền vào là vé xanh gần như
	 * tức thì, không phải chờ cron. Một câu SELECT có LIMIT 1 cho đúng một mã vé, rẻ.
	 *
	 * Gác class_exists đúng luật gọi chéo: chưa cài Sao Kê thì bỏ qua, quản trị vẫn xác nhận tay
	 * được như cũ.
	 */
	public static function tu_khop( $ma_ve, $so_tien, $noi_dung ) {
		if ( ! self::co_tien_ve( $so_tien, $noi_dung ) ) { return false; }
		return self::danh_dau_tt( $ma_ve );
	}

	/**
	 * Sổ phụ đã có lượt tiền VÀO nào mang đúng nội dung này, đủ số tiền chưa?
	 *
	 * Dùng chung cho vé (tu_khop) và nạp ví (tu_khop_nap) — hai chỗ cùng một câu hỏi, viết hai
	 * lần là sớm muộn sửa một bên quên bên kia.
	 */
	public static function co_tien_ve( $so_tien, $noi_dung ) {
		if ( ! class_exists( 'SAOKE_App' ) || ! method_exists( 'SAOKE_App', 'tbl' ) ) { return false; }
		$nd = trim( (string) $noi_dung );
		if ( '' === $nd ) { return false; }
		global $wpdb;
		$tbl = SAOKE_App::tbl();
		/* Bảng Sao Kê có thể chưa dựng (mới cài, chưa nhận giao dịch nào). Hỏi thẳng là lỗi SQL
		   ngay trong lượt trang khách hỏi trạng thái — hỏng chỗ không đáng hỏng. */
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) { return false; }
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $tbl WHERE loai='in' AND tien >= %d AND noi_dung LIKE %s ORDER BY id DESC LIMIT 1",
			(int) $so_tien, '%' . $wpdb->esc_like( $nd ) . '%' ) );
	}

	/**
	 * VÍ VÉ CÁ NHÂN — mã + QR của những vé khách đã mua.
	 *
	 * 🔴 VÍ NẰM Ở MÁY KHÁCH, MÁY CHỦ CHỈ LÀM TƯƠI. Trang gửi lên danh sách mã vé mà CHÍNH máy ấy
	 * đã mua (lưu trong localStorage), máy chủ trả về trạng thái mới nhất. Đừng đổi thành "tra vé
	 * theo số điện thoại": số điện thoại không phải bí mật, ai gõ số người khác cũng xem được vé
	 * của họ — mà mã vé chính là thứ đưa ra cổng để vào cửa.
	 *
	 * Đăng nhập Zalo thì máy chủ GỘP THÊM vé mua bằng tài khoản ấy trên máy khác — chỗ này an
	 * toàn vì danh tính do cookie đã ký của chính plugin xác nhận, không phải do khách khai.
	 *
	 * Mỗi vé kèm QR dựng sẵn ở máy chủ (xem qr_svg): nhân viên quét thẳng trên màn hình khách.
	 */
	public static function r_vi( $req ) {
		global $wpdb; $tbl = self::tbl();
		$ma = $req->get_param( 'ma' );
		$ds = array();
		if ( is_array( $ma ) ) {
			foreach ( $ma as $m ) {
				$m = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $m ) );
				if ( '' !== $m ) { $ds[ $m ] = 1; }
			}
		}
		$ds = array_slice( array_keys( $ds ), 0, 100 );   // ví cá nhân, không phải công cụ quét sổ
		$dk = array(); $args = array();
		if ( $ds ) {
			$dk[] = 'ma_ve IN (' . implode( ',', array_fill( 0, count( $ds ), '%s' ) ) . ')';
			$args = array_merge( $args, $ds );
		}
		$zid = self::zalo_id_hien();
		if ( '' !== $zid ) { $dk[] = 'zalo_id=%s'; $args[] = $zid; }
		if ( ! $dk ) { return array( 'ok' => true, 've' => array() ); }
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ma_ve, dv_ten, so_tien, trang_thai, coso, coso_dung, tao_luc, tt_luc, dung_luc
			 FROM $tbl WHERE (" . implode( ' OR ', $dk ) . ') ORDER BY id DESC LIMIT 100', $args ), ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $r ) {
			$ra[] = array(
				'ma_ve' => $r['ma_ve'], 'goi_ten' => $r['dv_ten'], 'so_tien' => (int) $r['so_tien'],
				'trang_thai' => $r['trang_thai'], 'coso' => $r['coso'], 'coso_dung' => $r['coso_dung'],
				'tao_luc' => $r['tao_luc'], 'tt_luc' => $r['tt_luc'], 'dung_luc' => $r['dung_luc'],
				/* QR chứa ĐÚNG mã vé, không thêm gì: máy quét ở cổng đọc ra là tra được ngay. */
				'qr_svg' => self::qr_svg( $r['ma_ve'], 190 ),
			);
		}
		return array( 'ok' => true, 've' => $ra );
	}

	/**
	 * Vé ĐÃ TRẢ TIỀN, CHƯA SOÁT của một cơ sở — màn hình nhân viên tại quầy tự làm tươi.
	 *
	 * ⚠️ Chỉ trả vé của ĐÚNG cơ sở đang mở màn hình, và chỉ vé đã trả tiền. Trả cả vé còn chờ là
	 * nhân viên soát nhầm cho người chưa chuyển tiền.
	 *
	 * Vé mua từ xa (không quét tem cửa hàng) thì cột `coso` rỗng — những vé ấy hiện ở MỌI cơ sở,
	 * vì khách mua trước ở nhà rồi tới cơ sở nào cũng vào được. Đó là luật bán hàng đã chọn từ
	 * đầu; đổi nó là vé mua trước bỗng không dùng được ở đâu cả.
	 */
	public static function r_cho_soat( $req ) {
		if ( ! self::pin_hople( $req ) ) { return new WP_Error( 'pin', 'Chưa đăng nhập quầy.', array( 'status' => 401 ) ); }
		global $wpdb; $tbl = self::tbl();
		$cs = sanitize_text_field( (string) $req->get_param( 'coso' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ma_ve, dv_ten, so_tien, ten_khach, sdt, coso, tt_luc FROM $tbl
			  WHERE trang_thai='da_tt' AND (coso=%s OR coso='') ORDER BY tt_luc DESC, id DESC LIMIT 50", $cs ), ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $r ) {
			$ra[] = array( 'ma_ve' => $r['ma_ve'], 'goi_ten' => $r['dv_ten'], 'so_tien' => (int) $r['so_tien'],
				'ten_khach' => $r['ten_khach'], 'sdt' => $r['sdt'],
				'tu_xa' => ( '' === (string) $r['coso'] ), 'tt_luc' => $r['tt_luc'] );
		}
		return array( 'ok' => true, 've' => $ra, 'coso' => $cs );
	}

	/**
	 * Nhân viên quét mã của khách rồi xác nhận ĐÃ SỬ DỤNG.
	 *
	 * 🔴 MỖI VÉ CHỈ VÀO ĐƯỢC MỘT LẦN. Vé đã soát mà soát lại thì trả về lỗi kèm nơi và giờ đã
	 * dùng — nói rõ hơn hẳn một câu "không hợp lệ", vì hai tình huống rất khác nhau: vé giả, và
	 * vé thật mà cả nhà cùng đưa một mã.
	 */
	public static function r_soat( $req ) {
		if ( ! self::pin_hople( $req ) ) { return new WP_Error( 'pin', 'Chưa đăng nhập quầy.', array( 'status' => 401 ) ); }
		global $wpdb; $tbl = self::tbl();
		$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $req->get_param( 'ma_ve' ) ) );
		$cs = mb_substr( sanitize_text_field( (string) $req->get_param( 'coso' ) ), 0, 60 );
		if ( '' === $ma ) { return new WP_Error( 'ma', 'Thiếu mã vé.', array( 'status' => 400 ) ); }
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tbl WHERE ma_ve=%s", $ma ), ARRAY_A );
		if ( ! $r ) { return new WP_Error( 'khong_co', 'Không có vé mang mã này.', array( 'status' => 404 ) ); }
		if ( 'huy' === $r['trang_thai'] ) { return new WP_Error( 'huy', 'Vé đã bị huỷ.', array( 'status' => 409 ) ); }
		if ( 'da_dung' === $r['trang_thai'] ) {
			return new WP_Error( 'da_dung', 'Vé ĐÃ SỬ DỤNG'
				. ( $r['coso_dung'] ? ' tại ' . $r['coso_dung'] : '' )
				. ( $r['dung_luc'] ? ' lúc ' . mysql2date( 'H:i d/m/Y', $r['dung_luc'] ) : '' ) . '.',
				array( 'status' => 409 ) );
		}
		if ( 'da_tt' !== $r['trang_thai'] ) {
			/* Còn chờ thì soi sổ phụ một lần nữa: khách vừa chuyển khoản ngay tại quầy là chuyện
			   thường, bắt họ đứng đợi vòng hỏi trạng thái sau mới vào được thì vô lý. */
			if ( ! self::tu_khop( $r['ma_ve'], (int) $r['so_tien'], $r['noi_dung'] ) ) {
				return new WP_Error( 'chua_tt', 'Vé CHƯA THANH TOÁN — chưa thấy tiền về.', array( 'status' => 409 ) );
			}
		}
		$wpdb->update( $tbl, array( 'trang_thai' => 'da_dung', 'coso_dung' => $cs, 'dung_luc' => current_time( 'mysql' ) ), array( 'ma_ve' => $ma ) );
		return array( 'ok' => true, 'ma_ve' => $ma, 'goi_ten' => $r['dv_ten'], 'so_tien' => (int) $r['so_tien'],
			'ten_khach' => $r['ten_khach'], 'coso_dung' => $cs );
	}

	/**
	 * Dò lại TẤT CẢ vé đang chờ. Dùng cho hai chỗ:
	 *  · WP-Cron 5 phút một lần — khách chuyển khoản xong ĐÓNG TAB là không còn ai hỏi trạng
	 *    thái nữa, mà vé vẫn phải tự xanh; không có cron thì nó chờ tới lúc ai đó mở đúng vé ấy.
	 *  · Nút "Dò lại sao kê" ở khu quản trị — để không phải ngồi đợi cron khi đang đứng bán hàng.
	 */
	public static function do_lai_saoke( $gioi_han = 200 ) {
		global $wpdb; $tbl = self::tbl();
		$cho = $wpdb->get_results( $wpdb->prepare(
			"SELECT ma_ve, so_tien, noi_dung FROM $tbl WHERE trang_thai='cho' ORDER BY id DESC LIMIT %d", (int) $gioi_han ), ARRAY_A );
		$n = 0;
		foreach ( (array) $cho as $c ) {
			if ( self::tu_khop( $c['ma_ve'], (int) $c['so_tien'], $c['noi_dung'] ) ) { $n++; }
		}
		return $n;
	}
	public static function r_ql_do_saoke( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$n = self::do_lai_saoke();
		return array( 'ok' => true, 'so' => $n,
			'noi' => $n ? ( 'Đã xác nhận ' . $n . ' vé từ sao kê.' ) : 'Không có vé chờ nào thấy tiền về.' );
	}

	/**
	 * Khách chọn trả bằng ví: trừ tiền và đánh dấu vé đã thanh toán ngay.
	 *
	 * 🔴 GỌI SAU KHI ĐƠN ĐÃ VÀO SỔ, KHÔNG PHẢI TRƯỚC. Trừ tiền trước rồi mới ghi đơn, mà ghi đơn
	 * hỏng, là tiền mất còn vé thì không có. Thứ tự này thì lỗi tệ nhất là đơn nằm ở "chờ thanh
	 * toán" — khách vẫn chuyển khoản được, quản trị vẫn xác nhận tay được.
	 *
	 * Thiếu tiền thì BÁO LỖI, đừng lặng lẽ lùi về chuyển khoản: khách bấm "trả bằng ví" là đang
	 * tin rằng ví đủ; để họ tự phát hiện ở màn hình QR là kiểu bất ngờ không ai thích.
	 *
	 * @return WP_Error|bool true = đã trừ ví · false = khách không chọn trả bằng ví
	 */
	private static function tra_bang_vi( $req, $ma_ve, $so_tien, $mo_ta ) {
		if ( 'vi' !== (string) $req->get_param( 'tt' ) ) { return false; }
		$chu = self::vi_chu();
		if ( '' === $chu ) { return new WP_Error( 'dn', 'Đăng nhập Zalo để trả bằng ví.', array( 'status' => 401 ) ); }
		$du = self::vi_so_du( $chu );
		if ( ! self::vi_tru( $chu, (int) $so_tien, $ma_ve, 'Mua vé: ' . $mo_ta ) ) {
			return new WP_Error( 'vi_thieu', 'Ví không đủ tiền — còn ' . number_format_i18n( $du )
				. 'đ, cần ' . number_format_i18n( (int) $so_tien ) . 'đ. Nạp thêm rồi thử lại.', array( 'status' => 409 ) );
		}
		self::danh_dau_tt( $ma_ve );
		return true;
	}

	// ═════════════════════════════ VÍ TIỀN ═════════════════════════════

	/**
	 * CHỦ VÍ LÀ TÀI KHOẢN ZALO ĐÃ ĐĂNG NHẬP — không phải số điện thoại.
	 *
	 * 🔴 ĐỪNG ĐỔI SANG TRA THEO SỐ ĐIỆN THOẠI. Ví vé (mã + QR) đã chốt luật này rồi, nhưng ở ví
	 * TIỀN thì hậu quả nặng hơn hẳn: số điện thoại không phải bí mật, ai gõ số người khác cũng
	 * tiêu được tiền của họ. Danh tính Zalo do cookie đã ký bằng muối của site xác nhận, khách
	 * không tự khai được.
	 */
	public static function vi_chu() {
		return self::zalo_id_hien();
	}

	/* Đọc ví (tự tạo dòng nếu chưa có). Trả mảng, hoặc null khi chưa đăng nhập. */
	public static function vi_lay( $chu ) {
		$chu = trim( (string) $chu );
		if ( '' === $chu ) { return null; }
		global $wpdb; $t = self::tbl_vi();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE chu=%s", $chu ), ARRAY_A );
		if ( $r ) { return $r; }
		$zu = self::zalo_user();
		$wpdb->insert( $t, array(
			'chu' => $chu, 'ten' => $zu && ! empty( $zu['name'] ) ? mb_substr( $zu['name'], 0, 80 ) : '',
			'sdt' => '', 'so_du' => 0, 'tao_luc' => current_time( 'mysql' ) ) );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE chu=%s", $chu ), ARRAY_A );
	}

	public static function vi_so_du( $chu ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT so_du FROM ' . self::tbl_vi() . ' WHERE chu=%s', $chu ) );
	}

	/* Ghi một dòng sổ cái. Số dư SAU mỗi lượt ghi luôn — để đối chiếu được khi có tranh cãi. */
	private static function vi_ghi_so( $chu, $loai, $so_tien, $sau, $ma = '', $ghi_chu = '' ) {
		global $wpdb;
		$wpdb->insert( self::tbl_vi_gd(), array(
			'chu' => $chu, 'loai' => $loai, 'so_tien' => (int) $so_tien, 'sau' => (int) $sau,
			'ma' => mb_substr( (string) $ma, 0, 24 ), 'ghi_chu' => mb_substr( (string) $ghi_chu, 0, 140 ),
			'tao_luc' => current_time( 'mysql' ) ) );
	}

	/**
	 * Cộng tiền vào ví. `$loai` = nap (tiền khách chuyển) | tang (khuyến mãi) | hoan (trả lại).
	 * Tách `nap` với `tang` vì kế toán cần biết bao nhiêu là tiền thật, bao nhiêu là tiền tặng —
	 * gộp một cục thì doanh thu và chi phí khuyến mãi lẫn vào nhau, không gỡ ra được nữa.
	 */
	public static function vi_cong( $chu, $so_tien, $loai = 'nap', $ma = '', $ghi_chu = '' ) {
		$so_tien = (int) $so_tien;
		if ( '' === (string) $chu || $so_tien <= 0 ) { return false; }
		global $wpdb; $t = self::tbl_vi();
		self::vi_lay( $chu );
		$cot = ( 'tang' === $loai ) ? 'tong_tang' : ( 'nap' === $loai ? 'tong_nap' : '' );
		$sql = "UPDATE $t SET so_du = so_du + %d" . ( $cot ? ", $cot = $cot + %d" : '' ) . ', sua_luc = %s WHERE chu = %s';
		$args = $cot ? array( $so_tien, $so_tien, current_time( 'mysql' ), $chu )
					: array( $so_tien, current_time( 'mysql' ), $chu );
		$wpdb->query( $wpdb->prepare( $sql, $args ) );
		self::vi_ghi_so( $chu, $loai, $so_tien, self::vi_so_du( $chu ), $ma, $ghi_chu );
		return true;
	}

	/**
	 * Trừ tiền mua vé.
	 *
	 * 🔴 TRỪ BẰNG MỘT CÂU UPDATE CÓ ĐIỀU KIỆN `so_du >= %d`, KHÔNG "đọc rồi mới ghi".
	 * Đọc số dư, kiểm đủ, rồi ghi lại là hai lượt bấm gần nhau (hai tab, hoặc bấm hai lần vì
	 * mạng chậm) cùng đọc thấy đủ tiền rồi cùng trừ — tiêu 100k hai lần từ một ví 100k. Để
	 * MySQL tự quyết trong một câu thì lượt thứ hai không đổi được dòng nào, và ta biết ngay.
	 */
	public static function vi_tru( $chu, $so_tien, $ma = '', $ghi_chu = '' ) {
		$so_tien = (int) $so_tien;
		if ( '' === (string) $chu || $so_tien <= 0 ) { return false; }
		global $wpdb; $t = self::tbl_vi();
		$n = $wpdb->query( $wpdb->prepare(
			"UPDATE $t SET so_du = so_du - %d, tong_tieu = tong_tieu + %d, sua_luc = %s WHERE chu = %s AND so_du >= %d",
			$so_tien, $so_tien, current_time( 'mysql' ), $chu, $so_tien ) );
		if ( ! $n ) { return false; }
		self::vi_ghi_so( $chu, 'tieu', -$so_tien, self::vi_so_du( $chu ), $ma, $ghi_chu );
		return true;
	}

	/* ── Gói nạp & mã ưu đãi (khai ở khu quản trị) ─────────────────────────────────────── */

	public static function ds_goi_nap() {
		$d = get_option( 'pve_vi_goi' );
		$ra = array();
		foreach ( (array) ( is_array( $d ) ? $d : array() ) as $g ) {
			$nap = (int) ( isset( $g['nap'] ) ? $g['nap'] : 0 );
			if ( $nap < 1000 ) { continue; }
			$ra[] = array( 'nap' => $nap, 'tang' => max( 0, (int) ( isset( $g['tang'] ) ? $g['tang'] : 0 ) ) );
		}
		return $ra;
	}

	public static function ds_ma_uu_dai() {
		$d = get_option( 'pve_vi_code' );
		$ra = array();
		foreach ( (array) ( is_array( $d ) ? $d : array() ) as $c ) {
			$ma = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( isset( $c['code'] ) ? $c['code'] : '' ) ) );
			if ( '' === $ma ) { continue; }
			$ra[] = array(
				'code'     => $ma,
				'kieu'     => ( isset( $c['kieu'] ) && 'tien' === $c['kieu'] ) ? 'tien' : 'pt',
				'gia_tri'  => max( 0, (int) ( isset( $c['gia_tri'] ) ? $c['gia_tri'] : 0 ) ),
				'toi_thieu' => max( 0, (int) ( isset( $c['toi_thieu'] ) ? $c['toi_thieu'] : 0 ) ),
				'han'      => trim( (string) ( isset( $c['han'] ) ? $c['han'] : '' ) ),
				'gioi_han' => max( 0, (int) ( isset( $c['gioi_han'] ) ? $c['gioi_han'] : 0 ) ),
				'da_dung'  => max( 0, (int) ( isset( $c['da_dung'] ) ? $c['da_dung'] : 0 ) ),
				/* '' = dùng ở mọi nơi. Khai tên cơ sở thì mã CHỈ ăn khi khách đang đứng tại đúng
				   cơ sở ấy — mỗi cửa hàng một mã riêng, vừa là chương trình riêng của quầy, vừa
				   biết mã nào kéo được khách nạp tiền. */
				'coso'     => trim( (string) ( isset( $c['coso'] ) ? $c['coso'] : '' ) ),
			);
		}
		return $ra;
	}

	/**
	 * Tính tiền tặng của một mã ưu đãi cho mệnh giá đang nạp.
	 *
	 * ⚠️ MỖI LÝ DO TỪ CHỐI MỘT CÂU RIÊNG. "Mã không dùng được" là câu khiến khách gõ lại năm lần
	 * rồi gọi điện cho cửa hàng. Hết hạn, chưa đủ mệnh giá tối thiểu, hết lượt — ba chuyện khác
	 * nhau, và hai trong ba khách tự xử lý được nếu biết.
	 *
	 * @return array{ok:bool, tang:int, loi:string, code:string}
	 */
	/**
	 * Khách ĐANG ĐỨNG ở cơ sở nào — do máy chủ tự chốt, không tin lời trang khách khai.
	 *
	 * Dùng lại đúng cửa kiểm của giá tại quầy (giam_tai_cho): phải có mã tem cửa hàng VÀ toạ độ
	 * nằm trong bán kính. Tin theo tham số `cs` trang gửi lên là ai cũng gõ được mã của quầy
	 * đông khách nhất mà chẳng cần tới đó.
	 */
	private static function cs_dang_dung( $req ) {
		$c = self::coso_theo_ma( $req->get_param( 'cs' ) );
		if ( ! $c || ! $c['lat'] || ! $c['lng'] ) { return ''; }
		$lat = (float) $req->get_param( 'lat' ); $lng = (float) $req->get_param( 'lng' );
		if ( ! $lat || ! $lng || abs( $lat ) > 90 || abs( $lng ) > 180 ) { return ''; }
		/* ⚠️ KHÔNG gọi giam_tai_cho() ở đây. Hàm ấy trả false cho cơ sở chưa khai % giảm — mà
		   "đang đứng ở đâu" với "cơ sở ấy có giảm giá vé không" là hai câu hỏi khác nhau. Dùng
		   chung thì mã riêng của một quầy không khai % giảm sẽ không bao giờ ăn. */
		return ( self::kc_met( $lat, $lng, $c['lat'], $c['lng'] ) <= $c['bk'] ) ? (string) $c['ten'] : '';
	}

	public static function tinh_uu_dai( $code, $menh_gia, $cs_dang = '' ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
		if ( '' === $code ) { return array( 'ok' => true, 'tang' => 0, 'loi' => '', 'code' => '' ); }
		foreach ( self::ds_ma_uu_dai() as $c ) {
			if ( $c['code'] !== $code ) { continue; }
			if ( '' !== $c['han'] && current_time( 'Y-m-d' ) > $c['han'] ) {
				return array( 'ok' => false, 'tang' => 0, 'code' => $code,
					'loi' => 'Mã "' . $code . '" đã hết hạn ngày ' . mysql2date( 'd/m/Y', $c['han'] ) . '.' );
			}
			if ( $c['toi_thieu'] > 0 && (int) $menh_gia < $c['toi_thieu'] ) {
				return array( 'ok' => false, 'tang' => 0, 'code' => $code,
					'loi' => 'Mã "' . $code . '" chỉ áp dụng khi nạp từ ' . number_format_i18n( $c['toi_thieu'] ) . 'đ trở lên.' );
			}
			/* Mã của một cửa hàng: chỉ ăn khi khách đang Ở ĐÓ. `$cs_dang` do máy chủ tự chốt từ
			   toạ độ (POSH_Ve::giam_tai_cho), không lấy theo lời khách khai — nếu không thì ai
			   cũng gõ được mã của quầy đông khách nhất. */
			if ( '' !== $c['coso'] && self::squash_cs( $c['coso'] ) !== self::squash_cs( $cs_dang ) ) {
				return array( 'ok' => false, 'tang' => 0, 'code' => $code,
					'loi' => 'Mã "' . $code . '" chỉ dùng được tại ' . $c['coso']
						. '. Tới quầy, quét mã QR dán tại cửa hàng rồi bật vị trí để áp mã.' );
			}
			if ( $c['gioi_han'] > 0 && $c['da_dung'] >= $c['gioi_han'] ) {
				return array( 'ok' => false, 'tang' => 0, 'code' => $code,
					'loi' => 'Mã "' . $code . '" đã hết lượt sử dụng.' );
			}
			$tang = ( 'tien' === $c['kieu'] )
				? $c['gia_tri']
				: (int) floor( (int) $menh_gia * $c['gia_tri'] / 100 / 1000 ) * 1000;
			return array( 'ok' => true, 'tang' => max( 0, $tang ), 'loi' => '', 'code' => $code );
		}
		return array( 'ok' => false, 'tang' => 0, 'code' => $code, 'loi' => 'Không có mã ưu đãi "' . $code . '".' );
	}

	/* So tên cơ sở: bỏ dấu, bỏ khoảng trắng, về chữ hoa. Khai tay trong ô cấu hình thì "Funzone
	   Hà Nội" và "FUNZONE HÀ NỘI" phải là một — so thẳng chuỗi là mã không bao giờ ăn mà chẳng ai
	   hiểu vì sao. */
	private static function squash_cs( $s ) {
		$s = function_exists( 'remove_accents' ) ? remove_accents( (string) $s ) : (string) $s;
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $s ) );
	}

	/* Đếm lượt đã dùng của một mã — gọi lúc tiền về, không phải lúc tạo lệnh nạp: tạo lệnh rồi
	   không chuyển tiền mà đã trừ lượt thì mã hết sạch vì những người không mua. */
	private static function uu_dai_ghi_luot( $code ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
		if ( '' === $code ) { return; }
		$d = get_option( 'pve_vi_code' );
		if ( ! is_array( $d ) ) { return; }
		foreach ( $d as $i => $c ) {
			$m = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( isset( $c['code'] ) ? $c['code'] : '' ) ) );
			if ( $m === $code ) { $d[ $i ]['da_dung'] = 1 + (int) ( isset( $c['da_dung'] ) ? $c['da_dung'] : 0 ); break; }
		}
		update_option( 'pve_vi_code', $d );
	}

	private static function ma_nap_moi() {
		global $wpdb; $bang = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		for ( $lan = 0; $lan < 12; $lan++ ) {
			$s = ''; for ( $i = 0; $i < 8; $i++ ) { $s .= $bang[ random_int( 0, strlen( $bang ) - 1 ) ]; }
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::tbl_nap() . ' WHERE ma=%s', $s ) ) ) { return $s; }
		}
		return 'N' . substr( (string) time(), -7 );
	}

	/* Ví của khách đang mở trang: số dư, các gói nạp đang mở, và sổ cái gần đây. */
	public static function r_vi_toi( $req ) {
		$chu = self::vi_chu();
		if ( '' === $chu ) {
			/* Chưa đăng nhập thì vẫn trả gói nạp — để trang mời đăng nhập kèm bảng giá, chứ
			   không phải một ô trống chẳng nói gì. */
			return array( 'ok' => true, 'dangnhap' => false, 'goi' => self::ds_goi_nap() );
		}
		$v = self::vi_lay( $chu );
		global $wpdb;
		$gd = $wpdb->get_results( $wpdb->prepare(
			'SELECT loai, so_tien, sau, ma, ghi_chu, tao_luc FROM ' . self::tbl_vi_gd() . ' WHERE chu=%s ORDER BY id DESC LIMIT 30', $chu ), ARRAY_A );
		return array( 'ok' => true, 'dangnhap' => true,
			'so_du' => (int) $v['so_du'], 'ten' => $v['ten'],
			'tong_nap' => (int) $v['tong_nap'], 'tong_tang' => (int) $v['tong_tang'], 'tong_tieu' => (int) $v['tong_tieu'],
			'goi' => self::ds_goi_nap(), 'gd' => array_map( function ( $r ) {
				return array( 'loai' => $r['loai'], 'so_tien' => (int) $r['so_tien'], 'sau' => (int) $r['sau'],
					'ma' => $r['ma'], 'ghi_chu' => $r['ghi_chu'], 'tao_luc' => $r['tao_luc'] );
			}, (array) $gd ) );
	}

	/* Thử một mã ưu đãi trước khi tạo lệnh nạp — để khách biết được tặng bao nhiêu rồi mới quyết. */
	public static function r_vi_thu_ma( $req ) {
		$mg = (int) $req->get_param( 'menh_gia' );
		$kq = self::tinh_uu_dai( (string) $req->get_param( 'code' ), $mg, self::cs_dang_dung( $req ) );
		if ( ! $kq['ok'] ) { return new WP_Error( 'code', $kq['loi'], array( 'status' => 400 ) ); }
		return array( 'ok' => true, 'tang' => (int) $kq['tang'], 'code' => $kq['code'] );
	}

	/**
	 * Tạo lệnh nạp: trả mã QR chuyển khoản. Tiền về mới cộng ví (xem r_vi_nap_tt / tu_khop_nap).
	 *
	 * ⚠️ MỆNH GIÁ PHẢI LÀ MỘT GÓI CÓ THẬT, không nhận số khách tự gõ. Nhận số tuỳ ý thì phần
	 * "tặng thêm" của gói không còn cơ sở nào để tra, và ai cũng nạp được 1.000đ để lấy quà.
	 */
	public static function r_vi_nap( $req ) {
		$chu = self::vi_chu();
		if ( '' === $chu ) { return new WP_Error( 'dn', 'Đăng nhập Zalo để dùng ví.', array( 'status' => 401 ) ); }
		$b = self::bank();
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) { return new WP_Error( 'tk', 'Chưa cấu hình tài khoản nhận tiền.', array( 'status' => 409 ) ); }
		if ( ! self::nhip_ok() ) { return new WP_Error( 'nhip', 'Thao tác quá nhanh, thử lại sau giây lát.', array( 'status' => 429 ) ); }

		$mg   = (int) $req->get_param( 'menh_gia' );
		$goi  = null;
		foreach ( self::ds_goi_nap() as $g ) { if ( (int) $g['nap'] === $mg ) { $goi = $g; break; } }
		if ( ! $goi ) { return new WP_Error( 'goi', 'Mệnh giá không hợp lệ.', array( 'status' => 400 ) ); }

		$uu = self::tinh_uu_dai( (string) $req->get_param( 'code' ), $mg, self::cs_dang_dung( $req ) );
		if ( ! $uu['ok'] ) { return new WP_Error( 'code', $uu['loi'], array( 'status' => 400 ) ); }
		$tang = (int) $goi['tang'] + (int) $uu['tang'];

		global $wpdb;
		$ma = self::ma_nap_moi(); $nd = 'NAP' . $ma;
		$ghi = $wpdb->insert( self::tbl_nap(), array(
			'ma' => $ma, 'chu' => $chu, 'so_tien' => $mg, 'tang' => $tang, 'code' => $uu['code'],
			'noi_dung' => $nd, 'trang_thai' => 'cho', 'tao_luc' => current_time( 'mysql' ) ) );
		/* Cùng lý do với đặt vé: không ghi được lệnh nạp mà vẫn trả mã QR là nhận tiền cho một
		   lệnh không tồn tại — tiền vào tài khoản, ví khách vẫn 0đ, không ai truy ra được. */
		if ( ! $ghi ) { return new WP_Error( 'ghi', 'Không ghi được lệnh nạp. Báo quản trị kiểm bảng dữ liệu.', array( 'status' => 500 ) ); }

		$qr = self::vietqr( $b['bin'], $b['so_tk'], $mg, $nd );
		return array( 'ok' => true, 'ma' => $ma, 'so_tien' => $mg, 'tang' => $tang, 'nhan' => $mg + $tang,
			'code' => $uu['code'], 'noi_dung' => $nd, 'qr' => $qr, 'qr_svg' => self::qr_svg( $qr ),
			'trang_thai' => 'cho',
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}

	/**
	 * Tiền của một lệnh nạp về chưa. Cùng cơ chế với vé: soi sổ phụ của plugin Sao Kê.
	 *
	 * 🔴 CỘNG VÍ ĐÚNG MỘT LẦN. Trang hỏi 5 giây một lượt, lại còn mở hai tab được — nên việc
	 * chuyển trạng thái phải do MySQL quyết trong MỘT câu có điều kiện `trang_thai='cho'`. Lượt
	 * thứ hai không đổi được dòng nào và không cộng gì thêm. Đọc-rồi-ghi ở đây là nhân đôi tiền.
	 */
	public static function tu_khop_nap( $ma ) {
		global $wpdb; $t = self::tbl_nap();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE ma=%s", $ma ), ARRAY_A );
		if ( ! $r || 'cho' !== $r['trang_thai'] ) { return false; }
		if ( ! self::co_tien_ve( (int) $r['so_tien'], $r['noi_dung'] ) ) { return false; }
		$n = $wpdb->query( $wpdb->prepare(
			"UPDATE $t SET trang_thai='xong', tt_luc=%s WHERE ma=%s AND trang_thai='cho'",
			current_time( 'mysql' ), $ma ) );
		if ( ! $n ) { return false; }                       // lượt khác vừa cộng rồi
		self::vi_cong( $r['chu'], (int) $r['so_tien'], 'nap', $ma, 'Nạp ví' );
		if ( (int) $r['tang'] > 0 ) {
			self::vi_cong( $r['chu'], (int) $r['tang'], 'tang', $ma,
				'Tặng thêm' . ( $r['code'] ? ' (mã ' . $r['code'] . ')' : '' ) );
		}
		if ( $r['code'] ) { self::uu_dai_ghi_luot( $r['code'] ); }
		return true;
	}

	public static function r_vi_nap_tt( $req ) {
		$chu = self::vi_chu();
		if ( '' === $chu ) { return new WP_Error( 'dn', 'Đăng nhập Zalo để dùng ví.', array( 'status' => 401 ) ); }
		$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $req->get_param( 'ma' ) ) );
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tbl_nap() . ' WHERE ma=%s', $ma ), ARRAY_A );
		if ( ! $r ) { return new WP_Error( 'khong_co', 'Không tìm thấy lệnh nạp.', array( 'status' => 404 ) ); }
		/* Chỉ chủ ví được hỏi lệnh nạp của mình — mã nạp ngắn, dò được. */
		if ( (string) $r['chu'] !== $chu ) { return new WP_Error( 'quyen', 'Lệnh nạp không thuộc ví này.', array( 'status' => 403 ) ); }
		if ( 'cho' === $r['trang_thai'] && self::tu_khop_nap( $ma ) ) { $r['trang_thai'] = 'xong'; }
		return array( 'ok' => true, 'ma' => $ma, 'trang_thai' => $r['trang_thai'], 'so_du' => self::vi_so_du( $chu ) );
	}

	/* Dò lại mọi lệnh nạp đang chờ — cho cron, cùng lý do với vé: khách đóng tab là hết ai hỏi. */
	public static function do_lai_nap( $gioi_han = 200 ) {
		global $wpdb;
		$ds = $wpdb->get_col( $wpdb->prepare(
			'SELECT ma FROM ' . self::tbl_nap() . " WHERE trang_thai='cho' ORDER BY id DESC LIMIT %d", (int) $gioi_han ) );
		$n = 0;
		foreach ( (array) $ds as $m ) { if ( self::tu_khop_nap( $m ) ) { $n++; } }
		return $n;
	}

	// ───────────────────────────── Cổng thanh toán ─────────────────────────────
	/* Đánh dấu vé ĐÃ THANH TOÁN + cộng điểm (dùng chung cho IPN Momo/VNPay). Idempotent. */
	private static function danh_dau_tt( $ma ) {
		global $wpdb; $tbl = self::tbl();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT trang_thai, sdt, ten_khach, so_tien, da_cong_diem FROM $tbl WHERE ma_ve=%s", $ma ), ARRAY_A );
		if ( ! $r ) { return false; }
		if ( 'da_tt' !== $r['trang_thai'] && 'da_dung' !== $r['trang_thai'] ) {
			$wpdb->update( $tbl, array( 'trang_thai' => 'da_tt', 'tt_luc' => current_time( 'mysql' ) ), array( 'ma_ve' => $ma ) );
		}
		if ( ! (int) $r['da_cong_diem'] ) {
			self::cong_diem( $r['sdt'], $r['ten_khach'], $r['so_tien'] );
			$wpdb->update( $tbl, array( 'da_cong_diem' => 1 ), array( 'ma_ve' => $ma ) );
		}
		return true;
	}
	/* Cấu hình cổng (khoá bí mật lưu trong options, KHÔNG hardcode). */
	private static function cong_cf() {
		return array(
			'momo' => array(
				'partner' => trim( (string) get_option( 'pve_momo_partner', '' ) ),
				'access'  => trim( (string) get_option( 'pve_momo_access', '' ) ),
				'secret'  => trim( (string) get_option( 'pve_momo_secret', '' ) ),
				'test'    => (int) get_option( 'pve_momo_test', 0 ),
			),
			'vnpay' => array(
				'tmn'    => trim( (string) get_option( 'pve_vnp_tmn', '' ) ),
				'secret' => trim( (string) get_option( 'pve_vnp_secret', '' ) ),
				'test'   => (int) get_option( 'pve_vnp_test', 0 ),
			),
		);
	}
	private static function ipn_momo() { return esc_url_raw( rest_url( self::NS . '/ve/momo-ipn' ) ); }
	private static function ipn_vnpay() { return esc_url_raw( rest_url( self::NS . '/ve/vnpay-ipn' ) ); }

	/* Tạo yêu cầu thanh toán theo cổng: qr | momo | vnpay. Trả về payUrl/deeplink để app mở. */
	public static function r_thanhtoan( $req ) {
		global $wpdb;
		$ma   = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $req->get_param( 'ma_ve' ) ) );
		$cong = sanitize_key( (string) $req->get_param( 'cong' ) );
		if ( '' === $ma )   { return new WP_Error( 'ma', 'Thiếu mã vé.', array( 'status' => 400 ) ); }
		$v = $wpdb->get_row( $wpdb->prepare( 'SELECT ma_ve, dv_ten, so_tien, noi_dung, trang_thai FROM ' . self::tbl() . ' WHERE ma_ve=%s', $ma ), ARRAY_A );
		if ( ! $v ) { return new WP_Error( 'khong_co', 'Không tìm thấy vé.', array( 'status' => 404 ) ); }
		$tien = (int) $v['so_tien']; $nd = (string) $v['noi_dung'];
		if ( $tien < 1000 ) { return new WP_Error( 'tien', 'Số tiền không hợp lệ.', array( 'status' => 400 ) ); }
		$ttinfo = 'Thanh toan ve ' . $ma;

		if ( 'momo' === $cong ) {
			$cf = self::cong_cf()['momo'];
			if ( '' === $cf['partner'] || '' === $cf['access'] || '' === $cf['secret'] ) {
				return new WP_Error( 'chua_cf', 'Chưa cấu hình Momo. Vui lòng chọn cách khác hoặc quét QR ngân hàng.', array( 'status' => 409 ) );
			}
			$host    = $cf['test'] ? 'https://test-payment.momo.vn' : 'https://payment.momo.vn';
			$reqId   = $ma . '-' . time();
			$orderId = $reqId;                       // Momo yêu cầu orderId duy nhất mỗi lần tạo
			$redirect = esc_url_raw( rest_url( self::NS . '/ve/trangthai' ) . '?ma_ve=' . rawurlencode( $ma ) );
			$ipn      = self::ipn_momo();
			$rawType  = 'captureWallet';
			$extra    = '';
			$raw = 'accessKey=' . $cf['access'] . '&amount=' . $tien . '&extraData=' . $extra
				. '&ipnUrl=' . $ipn . '&orderId=' . $orderId . '&orderInfo=' . $ttinfo
				. '&partnerCode=' . $cf['partner'] . '&redirectUrl=' . $redirect
				. '&requestId=' . $reqId . '&requestType=' . $rawType;
			$sig = hash_hmac( 'sha256', $raw, $cf['secret'] );
			$body = array(
				'partnerCode' => $cf['partner'], 'accessKey' => $cf['access'], 'requestId' => $reqId,
				'amount' => (string) $tien, 'orderId' => $orderId, 'orderInfo' => $ttinfo,
				'redirectUrl' => $redirect, 'ipnUrl' => $ipn, 'extraData' => $extra,
				'requestType' => $rawType, 'signature' => $sig, 'lang' => 'vi',
			);
			$resp = wp_remote_post( $host . '/v2/gateway/api/create', array(
				'timeout' => 20, 'headers' => array( 'Content-Type' => 'application/json' ),
				'body' => wp_json_encode( $body ),
			) );
			if ( is_wp_error( $resp ) ) { return new WP_Error( 'momo', 'Không kết nối được Momo.', array( 'status' => 502 ) ); }
			$d = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( ! is_array( $d ) || empty( $d['payUrl'] ) ) {
				$msg = is_array( $d ) && ! empty( $d['message'] ) ? $d['message'] : 'Tạo đơn Momo thất bại.';
				return new WP_Error( 'momo', $msg, array( 'status' => 502 ) );
			}
			return array( 'ok' => true, 'cong' => 'momo', 'ma_ve' => $ma, 'so_tien' => $tien,
				'pay_url' => $d['payUrl'], 'deeplink' => isset( $d['deeplink'] ) ? $d['deeplink'] : $d['payUrl'] );
		}

		if ( 'vnpay' === $cong ) {
			$cf = self::cong_cf()['vnpay'];
			if ( '' === $cf['tmn'] || '' === $cf['secret'] ) {
				return new WP_Error( 'chua_cf', 'Chưa cấu hình VNPay. Vui lòng chọn cách khác hoặc quét QR ngân hàng.', array( 'status' => 409 ) );
			}
			$host = $cf['test'] ? 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html' : 'https://vnpayment.vn/paymentv2/vpcpay.html';
			$ret  = esc_url_raw( rest_url( self::NS . '/ve/vnpay-ipn' ) );
			$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) $_SERVER['REMOTE_ADDR'] ) : '127.0.0.1';
			$now  = current_time( 'YmdHis' );
			$exp  = current_time( 'timestamp' ) + 15 * 60;
			$p = array(
				'vnp_Version' => '2.1.0', 'vnp_Command' => 'pay', 'vnp_TmnCode' => $cf['tmn'],
				'vnp_Amount' => (string) ( $tien * 100 ), 'vnp_CreateDate' => $now, 'vnp_CurrCode' => 'VND',
				'vnp_IpAddr' => $ip, 'vnp_Locale' => 'vn', 'vnp_OrderInfo' => $ttinfo, 'vnp_OrderType' => 'other',
				'vnp_ReturnUrl' => $ret, 'vnp_TxnRef' => $ma, 'vnp_ExpireDate' => gmdate( 'YmdHis', $exp ),
			);
			ksort( $p );
			$hashdata = ''; $query = ''; $i = 0;
			foreach ( $p as $k => $val ) {
				$hashdata .= ( $i ? '&' : '' ) . urlencode( $k ) . '=' . urlencode( $val );
				$query    .= ( $i ? '&' : '' ) . urlencode( $k ) . '=' . urlencode( $val );
				$i++;
			}
			$secure = hash_hmac( 'sha512', $hashdata, $cf['secret'] );
			$pay_url = $host . '?' . $query . '&vnp_SecureHash=' . $secure;
			return array( 'ok' => true, 'cong' => 'vnpay', 'ma_ve' => $ma, 'so_tien' => $tien,
				'pay_url' => $pay_url, 'deeplink' => $pay_url );
		}

		// Mặc định: QR ngân hàng (VietQR). Miễn phí, không cần cổng.
		$b = self::bank();
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) { return new WP_Error( 'tk', 'Chưa cấu hình tài khoản nhận tiền.', array( 'status' => 409 ) ); }
		$qr = self::vietqr( $b['bin'], $b['so_tk'], $tien, $nd );
		return array( 'ok' => true, 'cong' => 'qr', 'ma_ve' => $ma, 'so_tien' => $tien,
			'noi_dung' => $nd, 'qr' => $qr,
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}

	/* Momo báo về (server→server). Xác thực chữ ký rồi đánh dấu đã TT. */
	public static function r_momo_ipn( $req ) {
		$cf = self::cong_cf()['momo'];
		if ( '' === $cf['secret'] ) { return new WP_REST_Response( array( 'resultCode' => 99 ), 200 ); }
		$p = $req->get_json_params();
		if ( ! is_array( $p ) ) { $p = $req->get_params(); }
		$get = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) ? (string) $p[ $k ] : ''; };
		$raw = 'accessKey=' . $cf['access']
			. '&amount=' . $get( 'amount' ) . '&extraData=' . $get( 'extraData' )
			. '&message=' . $get( 'message' ) . '&orderId=' . $get( 'orderId' )
			. '&orderInfo=' . $get( 'orderInfo' ) . '&orderType=' . $get( 'orderType' )
			. '&partnerCode=' . $get( 'partnerCode' ) . '&payType=' . $get( 'payType' )
			. '&requestId=' . $get( 'requestId' ) . '&responseTime=' . $get( 'responseTime' )
			. '&resultCode=' . $get( 'resultCode' ) . '&transId=' . $get( 'transId' );
		$sig = hash_hmac( 'sha256', $raw, $cf['secret'] );
		if ( ! hash_equals( $sig, $get( 'signature' ) ) ) { return new WP_REST_Response( array( 'resultCode' => 97 ), 200 ); }
		if ( '0' === $get( 'resultCode' ) || 0 === (int) $get( 'resultCode' ) ) {
			$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( substr( $get( 'orderId' ), 0, 8 ) ) );
			self::danh_dau_tt( $ma );
		}
		return new WP_REST_Response( array( 'resultCode' => 0, 'message' => 'received' ), 200 );
	}

	/* VNPay báo về (redirect/IPN qua GET). Xác thực chữ ký rồi đánh dấu đã TT. */
	public static function r_vnpay_ipn( $req ) {
		$cf = self::cong_cf()['vnpay'];
		if ( '' === $cf['secret'] ) { return new WP_REST_Response( array( 'RspCode' => '99', 'Message' => 'no config' ), 200 ); }
		$p = $req->get_params();
		$secure = isset( $p['vnp_SecureHash'] ) ? (string) $p['vnp_SecureHash'] : '';
		unset( $p['vnp_SecureHash'], $p['vnp_SecureHashType'] );
		// bỏ tham số nội bộ của REST (rest_route…) không thuộc VNPay
		foreach ( array_keys( $p ) as $k ) { if ( 0 !== strpos( $k, 'vnp_' ) ) { unset( $p[ $k ] ); } }
		ksort( $p );
		$hashdata = ''; $i = 0;
		foreach ( $p as $k => $val ) { $hashdata .= ( $i ? '&' : '' ) . urlencode( $k ) . '=' . urlencode( $val ); $i++; }
		$tinh = hash_hmac( 'sha512', $hashdata, $cf['secret'] );
		if ( ! hash_equals( $tinh, $secure ) ) { return new WP_REST_Response( array( 'RspCode' => '97', 'Message' => 'Invalid signature' ), 200 ); }
		if ( '00' === (string) ( isset( $p['vnp_ResponseCode'] ) ? $p['vnp_ResponseCode'] : '' ) ) {
			$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) ( isset( $p['vnp_TxnRef'] ) ? $p['vnp_TxnRef'] : '' ) ) );
			self::danh_dau_tt( $ma );
		}
		return new WP_REST_Response( array( 'RspCode' => '00', 'Message' => 'Confirm Success' ), 200 );
	}

	public static function r_tin() {
		$q = new WP_Query( array(
			'post_type' => 'post', 'post_status' => 'publish',
			'posts_per_page' => 12, 'ignore_sticky_posts' => true,
			'no_found_rows' => true,
		) );
		$ra = array();
		foreach ( $q->posts as $p ) {
			$anh = get_the_post_thumbnail_url( $p->ID, 'medium' );
			$xem = (int) get_post_meta( $p->ID, 'post_views_count', true );
			$ra[] = array(
				'id'       => (int) $p->ID,
				'tieu_de'  => get_the_title( $p ),
				'anh'      => $anh ? $anh : '',
				'ngay'     => get_the_date( 'H:i, d/m/Y', $p ),
				'luot_xem' => $xem,
				'link'     => get_permalink( $p ),
			);
		}
		wp_reset_postdata();
		return array( 'ok' => true, 'tin' => $ra );
	}

	/* Tra điểm/hạng thành viên theo SĐT (cho tab Cá nhân). */
	public static function r_tv( $req ) {
		$sdt = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'sdt' ) );
		if ( '' === $sdt ) { return new WP_Error( 'sdt', 'Thiếu số điện thoại.', array( 'status' => 400 ) ); }
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT ten, diem, tong_chi, so_don FROM ' . self::tbl_tv() . ' WHERE sdt=%s', $sdt ), ARRAY_A );
		$diem = $r ? (int) $r['diem'] : 0;
		$h = self::hang_cua( $diem );
		return array(
			'ok' => true, 'sdt' => $sdt, 'ten' => $r ? $r['ten'] : '',
			'diem' => $diem, 'tong_chi' => $r ? (int) $r['tong_chi'] : 0, 'so_don' => $r ? (int) $r['so_don'] : 0,
			'hang' => $h['hang'], 'hang_ke' => $h['hang_ke'], 'con_thieu' => $h['con_thieu'],
			'moc' => self::ds_hang(),
		);
	}

	// ───────────────────────────── Ưu đãi (voucher/khuyến mãi) ─────────────────────────────
	private static function chuan_hoa_uu( $v, $auto ) {
		return array(
			'id'    => (int) ( ! empty( $v['id'] ) ? $v['id'] : $auto ),
			'ten'   => trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) ),
			'mo_ta' => trim( (string) ( isset( $v['mo_ta'] ) ? $v['mo_ta'] : '' ) ),
			'anh'   => esc_url_raw( (string) ( isset( $v['anh'] ) ? $v['anh'] : '' ) ),
			'hang'  => trim( (string) ( isset( $v['hang'] ) ? $v['hang'] : '' ) ),   // hạng tối thiểu (trống = mọi khách)
			'han'   => trim( (string) ( isset( $v['han'] ) ? $v['han'] : '' ) ),     // hạn dùng (text tự do)
			'hien'  => empty( $v['hien'] ) ? 0 : 1,
		);
	}
	public static function ds_uudai_tatca() {
		$ds = get_option( 'pve_uudai' ); if ( ! is_array( $ds ) ) { return array(); }
		$ra = array(); $auto = 1;
		foreach ( $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa_uu( $v, $auto ); if ( '' === $m['ten'] ) { continue; }
			$ra[] = $m;
		}
		return $ra;
	}
	private static function ds_uudai() { $r = array(); foreach ( self::ds_uudai_tatca() as $v ) { if ( $v['hien'] ) { $r[] = $v; } } return $r; }
	private static function luu_uudai( $ds ) {
		$ra = array(); $auto = 1;
		foreach ( (array) $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa_uu( $v, $auto ); if ( '' === $m['ten'] ) { continue; }
			$ra[] = $m;
		}
		update_option( 'pve_uudai', array_values( $ra ) );
	}
	public static function r_uudai() {
		$ra = array();
		foreach ( self::ds_uudai() as $u ) {
			$ra[] = array( 'id' => (int) $u['id'], 'ten' => $u['ten'], 'mo_ta' => $u['mo_ta'],
				'anh' => $u['anh'], 'hang' => $u['hang'], 'han' => $u['han'] );
		}
		return array( 'ok' => true, 'uudai' => $ra );
	}

	/* Giải mã SĐT từ token đăng nhập Zalo (getPhoneNumber). Secret Key khai ở admin, KHÔNG hardcode. */
	public static function r_zalo_sdt( $req ) {
		$secret = (string) get_option( 'pve_zalo_secret', '' );
		if ( '' === $secret ) { return new WP_Error( 'cfg', 'Chưa cấu hình Zalo Secret Key.', array( 'status' => 409 ) ); }
		$token = (string) $req->get_param( 'token' );
		$at    = (string) $req->get_param( 'access_token' );
		if ( '' === $token || '' === $at ) { return new WP_Error( 'thieu', 'Thiếu token đăng nhập.', array( 'status' => 400 ) ); }
		$res = wp_remote_get( 'https://graph.zalo.me/v2.0/me/info', array(
			'timeout' => 10,
			'headers' => array( 'access_token' => $at, 'code' => $token, 'secret_key' => $secret ),
		) );
		if ( is_wp_error( $res ) ) { return new WP_Error( 'kn', 'Không gọi được Zalo.', array( 'status' => 502 ) ); }
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$sdt  = isset( $body['data']['number'] ) ? preg_replace( '/[^0-9]/', '', (string) $body['data']['number'] ) : '';
		if ( '' === $sdt ) { return new WP_Error( 'sdt', 'Không lấy được số điện thoại.', array( 'status' => 400 ) ); }
		if ( 0 === strpos( $sdt, '84' ) ) { $sdt = '0' . substr( $sdt, 2 ); }   // 84xxx -> 0xxx
		return array( 'ok' => true, 'sdt' => $sdt );
	}

	// ───────────────────────────── Đăng nhập Zalo trên web (OAuth v4) ─────────────────────────────

	/**
	 * 🔴 HỎNG PHẢI NÓI RA. Bản trước mọi lối thất bại đều `wp_safe_redirect($back); exit;` —
	 * không một chữ nào. Bấm "Đăng nhập bằng Zalo" rồi quay về đúng màn hình cũ, y như chưa
	 * bấm: anh Thắng 11/09/2026 *"bấm zalo để vào quản trị nhưng không phản hồi"*. Thiếu khoá
	 * bí mật, Zalo chối redirect_uri, phiên hết hạn, graph.zalo.me lỗi — bốn nguyên nhân khác
	 * hẳn nhau mà giao diện giống hệt nhau, nên không ai biết phải đi sửa cái gì.
	 *
	 * Nay mỗi lối về mang theo `pve_zl_loi` (mã lý do) và `pve_zl_chi` (NGUYÊN VĂN câu Zalo trả
	 * lời, nếu có); trang in thẳng ra. Đúng bài học đã ghi ở plugin nhà ma bản 1.5.1.
	 */
	private static function ve_kem_loi( $back, $ma, $chi = '' ) {
		$u = add_query_arg( 'pve_zl_loi', rawurlencode( $ma ), $back ? $back : home_url( '/' ) );
		if ( '' !== $chi ) { $u = add_query_arg( 'pve_zl_chi', rawurlencode( mb_substr( $chi, 0, 300 ) ), $u ); }
		wp_safe_redirect( $u ); exit;
	}

	/* Địa chỉ trang đang mở (bỏ mấy tham số thông báo để bấm lại không cộng dồn). */
	public static function url_hien_tai() {
		$u = home_url( add_query_arg( array() ) );
		return remove_query_arg( array( 'pve_zl_loi', 'pve_zl_chi', 'pve_zl_ok' ), $u );
	}

	/* Mã lý do -> câu tiếng Việt. Kèm nguyên văn câu Zalo trả lời ở dòng dưới (nếu có). */
	public static function loi_zalo_html() {
		if ( empty( $_GET['pve_zl_loi'] ) ) { return ''; }
		$ma = sanitize_key( wp_unslash( $_GET['pve_zl_loi'] ) );
		$chi = isset( $_GET['pve_zl_chi'] ) ? sanitize_text_field( wp_unslash( $_GET['pve_zl_chi'] ) ) : '';
		$noi = array(
			'chua_cau_hinh'     => 'Chưa khai <b>Zalo App ID</b> hoặc <b>Khoá bí mật</b> — vào WP Admin → Vé khu vui chơi → phần Zalo.',
			'zalo_choi'         => 'Zalo từ chối lượt đăng nhập.',
			'het_han'           => 'Phiên đăng nhập đã hết hạn (quá 10 phút) hoặc mở lại link cũ — bấm lại từ đầu.',
			'khong_co_code'     => 'Zalo quay về nhưng không kèm mã uỷ quyền.',
			'khong_goi_duoc'    => 'Máy chủ web không gọi ra được Zalo (mạng hoặc tường lửa hosting chặn).',
			'doi_token_hong'    => 'Đổi mã uỷ quyền lấy token không thành.',
			'khong_lay_duoc_id' => 'Lấy được token nhưng không đọc được hồ sơ Zalo.',
		);
		$cau = isset( $noi[ $ma ] ) ? $noi[ $ma ] : 'Đăng nhập Zalo không thành.';
		$h = '<div class="pql-err">⚠️ ' . $cau;
		if ( '' !== $chi ) { $h .= '<div class="pql-err-chi">Zalo trả lời: <code>' . esc_html( $chi ) . '</code></div>'; }
		return $h . '</div>';
	}

	/* Đăng nhập Zalo xong nhưng ID chưa nằm trong danh sách quản trị -> nói rõ và đưa ID ra cho
	   người ta chép. Không có chỗ nào khác cho người dùng biết Zalo ID của chính mình. */
	public static function zalo_chua_trong_ds_html() {
		$zu = self::zalo_user(); if ( ! $zu || empty( $zu['id'] ) ) { return ''; }
		if ( '' !== self::quan_tri_khong_pin() ) { return ''; }
		$ds = trim( (string) get_option( 'pve_zalo_admin_ids', '' ) );
		$h  = '<div class="pql-err">⚠️ Đã đăng nhập Zalo'
			. ( ! empty( $zu['name'] ) ? ( ' (<b>' . esc_html( $zu['name'] ) . '</b>)' ) : '' )
			. ' nhưng tài khoản này <b>chưa được cấp quyền quản trị</b>.'
			. '<div class="pql-err-chi">Zalo ID của bạn: <code>' . esc_html( $zu['id'] ) . '</code> — chép số này vào '
			. '<b>WP Admin → Vé khu vui chơi → Zalo ID quản trị</b>'
			. ( '' === $ds ? ' (ô đang để trống nên chưa ai được bỏ qua PIN).' : '.' ) . '</div></div>';
		return $h;
	}

	public static function zalo_web_login() {
		if ( ! isset( $_GET['pve_zalo'] ) ) { return; }
		$act    = sanitize_key( $_GET['pve_zalo'] );
		$appid  = (string) get_option( 'pve_zalo_appid', '' );
		$secret = (string) get_option( 'pve_zalo_secret', '' );
		$cb     = esc_url_raw( rest_url( self::NS . '/zalo/cb' ) );   // callback sạch, không có dấu ?

		if ( 'login' === $act ) {
			/* Chỗ quay về: ưu tiên tham số `back` do chính trang gắn vào, rồi mới tới referer.
			   Referer không phải lúc nào cũng có (trình duyệt chặn, mở từ tab mới) — mất nó thì
			   quản trị bấm từ /quan-tri-ve lại rơi về trang chủ, trông như "vào không được". */
			$back = isset( $_GET['back'] ) ? esc_url_raw( wp_unslash( $_GET['back'] ) ) : '';
			$back = wp_validate_redirect( $back, '' );
			if ( '' === $back ) { $back = wp_get_referer(); }
			if ( ! $back ) { $back = home_url( '/' ); }
			/* Kiểm CẢ HAI khoá ngay đây. Thiếu secret thì bản cũ vẫn đẩy sang Zalo bình thường
			   rồi chết lặng ở bước đổi token — sai một chỗ, báo ở chỗ khác. */
			if ( '' === $appid || '' === $secret ) { self::ve_kem_loi( $back, 'chua_cau_hinh' ); }
			$verifier  = wp_generate_password( 64, false );
			$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
			$state     = wp_generate_password( 24, false );
			set_transient( 'pve_zl_' . $state, array( 'v' => $verifier, 'r' => $back ), 600 );
			wp_redirect( 'https://oauth.zaloapp.com/v4/permission?app_id=' . rawurlencode( $appid )
				. '&redirect_uri=' . rawurlencode( $cb ) . '&code_challenge=' . $challenge . '&state=' . $state );
			exit;
		}
		if ( 'cb' === $act ) {   // tương thích callback cũ ?pve_zalo=cb
			self::xong_dang_nhap(
				isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '',
				isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '',
				self::loi_tu_zalo()
			);
		}
		if ( 'logout' === $act ) {
			setcookie( 'pve_zuser', '', time() - 3600, '/' );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) ); exit;
		}
	}
	/* Callback sạch (REST): oauth.zaloapp.com quay về đây với code+state. */
	public static function r_zalo_cb( $req ) {
		self::xong_dang_nhap( (string) $req->get_param( 'code' ), (string) $req->get_param( 'state' ), self::loi_tu_zalo() );
	}

	/* Zalo chối thì nó quay về KHÔNG có `code`, chỉ có error/error_description. Bản cũ chỉ nhìn
	   `code` nên lời chối ấy rơi xuống đất — đây chính là ca "-14003 Invalid redirect uri" mà
	   bên nhà ma mất hai ngày đi dò. Gom nguyên văn để in ra. */
	private static function loi_tu_zalo() {
		$ra = array();
		foreach ( array( 'error', 'error_code', 'error_reason', 'error_description', 'message' ) as $k ) {
			if ( isset( $_GET[ $k ] ) && '' !== trim( (string) $_GET[ $k ] ) ) {
				$ra[] = $k . '=' . sanitize_text_field( wp_unslash( $_GET[ $k ] ) );
			}
		}
		return implode( ' · ', $ra );
	}
	/* Đổi code -> access_token -> lấy tên -> đặt cookie -> quay lại trang bán. */
	private static function xong_dang_nhap( $code, $state, $loi_zalo = '' ) {
		$appid  = (string) get_option( 'pve_zalo_appid', '' );
		$secret = (string) get_option( 'pve_zalo_secret', '' );
		$code   = preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $code );
		$state  = preg_replace( '/[^A-Za-z0-9]/', '', (string) $state );
		$data   = get_transient( 'pve_zl_' . $state );
		$back   = ( $data && ! empty( $data['r'] ) ) ? $data['r'] : home_url( '/' );
		if ( '' !== $loi_zalo )  { self::ve_kem_loi( $back, 'zalo_choi', $loi_zalo ); }
		if ( ! $data )           { self::ve_kem_loi( $back, 'het_han' ); }
		if ( '' === $code )      { self::ve_kem_loi( $back, 'khong_co_code' ); }
		if ( '' === $secret )    { self::ve_kem_loi( $back, 'chua_cau_hinh' ); }
		delete_transient( 'pve_zl_' . $state );
		$res = wp_remote_post( 'https://oauth.zaloapp.com/v4/access_token', array( 'timeout' => 12,
			'headers' => array( 'secret_key' => $secret, 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array( 'app_id' => $appid, 'code' => $code, 'grant_type' => 'authorization_code', 'code_verifier' => $data['v'] ) ) );
		if ( is_wp_error( $res ) ) { self::ve_kem_loi( $back, 'khong_goi_duoc', $res->get_error_message() ); }
		$than = (string) wp_remote_retrieve_body( $res );
		$tok  = json_decode( $than, true );
		$at   = isset( $tok['access_token'] ) ? $tok['access_token'] : '';
		/* Đổi code lấy token hỏng -> chép NGUYÊN VĂN thân trả lời của Zalo. Câu ấy nói thẳng
		   thiếu bước nào (redirect_uri, app chưa được OA cấp quyền…), đoán mò thì mất cả ngày. */
		if ( '' === $at ) { self::ve_kem_loi( $back, 'doi_token_hong', $than ); }
		$me = wp_remote_get( 'https://graph.zalo.me/v2.0/me?fields=id,name,picture', array( 'timeout' => 12, 'headers' => array( 'access_token' => $at ) ) );
		if ( is_wp_error( $me ) ) { self::ve_kem_loi( $back, 'khong_goi_duoc', $me->get_error_message() ); }
		$thanMe = (string) wp_remote_retrieve_body( $me );
		$u  = json_decode( $thanMe, true );
		$id = isset( $u['id'] ) ? preg_replace( '/\D+/', '', (string) $u['id'] ) : '';
		$nm = isset( $u['name'] ) ? sanitize_text_field( $u['name'] ) : '';
		/* Ảnh đại diện Zalo trả về lồng trong picture.data.url. Đã xin trường `picture` rồi thì
		   giữ lại luôn — có ảnh thì khách nhìn phát biết mình đang mua bằng tài khoản nào. */
		$anh = '';
		if ( isset( $u['picture']['data']['url'] ) ) { $anh = esc_url_raw( (string) $u['picture']['data']['url'] ); }
		if ( '' === $id ) { self::ve_kem_loi( $back, 'khong_lay_duoc_id', $thanMe ); }
		$val = base64_encode( wp_json_encode( array( 'id' => $id, 'name' => $nm, 'anh' => $anh ) ) );
		$sig = hash_hmac( 'sha256', $val, wp_salt( 'auth' ) );
		setcookie( 'pve_zuser', $val . '.' . $sig, time() + 30 * DAY_IN_SECONDS, '/' );
		/* Vào được rồi thì nói ai đang vào — và kèm ID để quản trị chép vào ô "Zalo ID quản trị".
		   Không có chỗ nào khác trên đời cho người ta biết Zalo ID của chính mình. */
		$u2 = add_query_arg( 'pve_zl_ok', rawurlencode( $id ), $back );
		wp_safe_redirect( $u2 ); exit;
	}
	/* Mã xác thực domain Zalo (bỏ tiền tố nếu có). */
	private static function zalo_verify_token() {
		$v = trim( (string) get_option( 'pve_zalo_verify', '' ) );
		if ( false !== strpos( $v, '=' ) ) { $v = substr( $v, strpos( $v, '=' ) + 1 ); }
		return trim( $v );
	}
	/* Chèn thẻ meta xác thực domain Zalo vào <head> (cả name lẫn property cho chắc). */
	public static function zalo_verify_meta() {
		$v = self::zalo_verify_token();
		if ( '' === $v ) { return; }
		echo '<meta name="zalo-platform-site-verification" content="' . esc_attr( $v ) . '" />' . "\n";
		echo '<meta property="zalo-platform-site-verification" content="' . esc_attr( $v ) . '" />' . "\n";
	}
	/* Tự phục vụ file zalo_verifier<token>.html ở web root (cách xác thực bằng file). */
	public static function zalo_verify_file() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) { return; }
		$uri = (string) $_SERVER['REQUEST_URI'];
		if ( false === stripos( $uri, 'zalo_verifier' ) ) { return; }
		$tok = self::zalo_verify_token();
		if ( '' === $tok ) { return; }
		$norm = function ( $s ) { return strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $s ) ); };
		if ( false === strpos( $norm( $uri ), $norm( $tok ) ) ) { return; }
		header( 'Content-Type: text/html; charset=utf-8' );
		echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta property=\"zalo-platform-site-verification\" content=\"" . esc_attr( $tok ) . "\" />\n</head>\n<body>\nThere Is No Limit To What You Can Accomplish Using Zalo!\n</body>\n</html>";
		exit;
	}
	/* Số phiên bản plugin (đọc từ header). */
	public static function phien_ban() {
		$d = get_file_data( __FILE__, array( 'v' => 'Version' ) );
		return isset( $d['v'] ) ? $d['v'] : '';
	}
	/* Đọc khách đã đăng nhập Zalo (từ cookie đã ký). */
	public static function zalo_user() {
		if ( empty( $_COOKIE['pve_zuser'] ) ) { return null; }
		$raw = (string) wp_unslash( $_COOKIE['pve_zuser'] );
		$p   = strrpos( $raw, '.' ); if ( false === $p ) { return null; }
		$val = substr( $raw, 0, $p ); $sig = substr( $raw, $p + 1 );
		if ( ! hash_equals( hash_hmac( 'sha256', $val, wp_salt( 'auth' ) ), $sig ) ) { return null; }
		$d = json_decode( base64_decode( $val ), true );
		return is_array( $d ) ? $d : null;
	}
	/* Zalo ID của khách đang mở trang ('' nếu chưa đăng nhập) — ghi kèm mỗi vé để sau này tra
	   "vé của tôi" và để nhớ SĐT khách đã dùng lần trước. */
	public static function zalo_id_hien() {
		$zu = self::zalo_user();
		return ( $zu && ! empty( $zu['id'] ) ) ? preg_replace( '/\D+/', '', (string) $zu['id'] ) : '';
	}

	/**
	 * Thông tin khách đang đăng nhập Zalo, cho trang khách điền sẵn ô Họ tên / Số điện thoại.
	 *
	 * ⚠️ ĐĂNG NHẬP ZALO TRÊN WEB KHÔNG CHO SỐ ĐIỆN THOẠI. OAuth v4 chỉ trả id + name + picture;
	 * số điện thoại chỉ lấy được trong Zalo Mini App (getPhoneNumber -> POSH_Ve::r_zalo_sdt()).
	 * Nên ở đây SĐT lấy từ chính lịch sử mua: vé gần nhất của Zalo ID này. Lần đầu khách vẫn phải
	 * gõ, từ lần hai trở đi thì có sẵn — và không phải bịa ra một kho dữ liệu riêng để nhớ.
	 */
	public static function r_zalo_toi() {
		$zu = self::zalo_user();
		if ( ! $zu || empty( $zu['id'] ) ) { return array( 'ok' => true, 'dangnhap' => false ); }
		global $wpdb;
		$zid = self::zalo_id_hien();
		$sdt = '';
		$cot = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::tbl() );
		/* Bảng chưa kịp nâng cấp cột zalo_id thì bỏ phần nhớ SĐT, ĐỪNG chạy câu SQL hỏng: mất
		   điền sẵn là phiền, mà lỗi 500 ở đây thì trang khách trắng luôn phần thông tin Zalo. */
		if ( in_array( 'zalo_id', (array) $cot, true ) ) {
			$sdt = (string) $wpdb->get_var( $wpdb->prepare(
				'SELECT sdt FROM ' . self::tbl() . " WHERE zalo_id=%s AND sdt<>'' ORDER BY id DESC LIMIT 1", $zid ) );
		}
		return array( 'ok' => true, 'dangnhap' => true, 'id' => $zid,
			'ten' => isset( $zu['name'] ) ? (string) $zu['name'] : '',
			'anh' => isset( $zu['anh'] ) ? (string) $zu['anh'] : '',
			'sdt' => $sdt );
	}

	/* Zalo user đã đăng nhập có phải admin? Danh sách ID admin khai ở admin (cách nhau dấu phẩy).
	   Để TRỐNG = mọi người đã đăng nhập đều thấy nút Quản trị (trang vẫn khoá PIN). */
	public static function la_admin_zalo( $zu ) {
		if ( ! is_array( $zu ) || empty( $zu['id'] ) ) { return false; }
		$ds = trim( (string) get_option( 'pve_zalo_admin_ids', '' ) );
		if ( '' === $ds ) { return true; }
		$ids = array_filter( array_map( 'trim', preg_split( '/[,\s]+/', $ds ) ) );
		return in_array( (string) $zu['id'], $ids, true );
	}

	// ───────────────────────────── Bảo vệ khu quản lý bằng PIN ─────────────────────────────

	/**
	 * Ai được vào khu quản trị KHÔNG CẦN PIN — anh Thắng 11/09/2026: *"đăng nhập bằng zalo quản
	 * trị thì không cần mã pin"*.
	 *
	 * Hai cửa, cả hai đều đã được xác thực trước khi tới đây:
	 *  · `wp`   — đang đăng nhập WordPress với quyền quản trị site. Người này sửa được cả plugin
	 *             lẫn cơ sở dữ liệu từ wp-admin, bắt gõ thêm PIN không chặn thêm được gì.
	 *  · `zalo` — cookie `pve_zuser` do chính plugin ký (HMAC bằng muối của site), và Zalo ID nằm
	 *             trong danh sách quản trị.
	 *
	 * 🔴 BẮT BUỘC CÓ DANH SÁCH ID. `la_admin_zalo()` trả TRUE cho mọi người khi ô "Zalo ID quản
	 *    trị" bỏ trống — chủ ý của nó là "ai đăng nhập cũng THẤY nút Quản trị, trang vẫn khoá
	 *    PIN". Đem đúng hàm ấy ra làm CỬA VÀO mà quên điều kiện này thì mọi khách từng đăng nhập
	 *    Zalo trên trang bán vé đều bước thẳng vào khu quản trị. Nên: trống = không ai được bỏ
	 *    qua PIN, dù đã đăng nhập.
	 *
	 * @return string '' = phải gõ PIN · 'wp' · 'zalo'
	 */
	public static function quan_tri_khong_pin() {
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) { return 'wp'; }
		$ds = trim( (string) get_option( 'pve_zalo_admin_ids', '' ) );
		if ( '' === $ds ) { return ''; }                            // xem khối 🔴 ở trên
		$zu = self::zalo_user();
		return ( $zu && self::la_admin_zalo( $zu ) ) ? 'zalo' : '';
	}

	private static function pin_hople( $req ) {
		/* Kiểm ở MỌI route /ql/* vì khu này không phát thẻ phiên: PIN đi kèm từng lượt gọi. Đặt
		   cửa mới ngay đây là mọi route được bảo vệ như nhau, khỏi sót một route nào đó. */
		if ( '' !== self::quan_tri_khong_pin() ) { return true; }
		$pin_luu = (string) get_option( 'pve_pin', '' );
		if ( '' === $pin_luu ) { return false; }                    // chưa khai PIN => khoá hẳn khu quản lý
		$pin = (string) $req->get_param( 'pin' );
		return hash_equals( $pin_luu, $pin );
	}
	private static function pin_chan() {
		// Chặn dò PIN: tối đa ~1 lần/giây/IP cho khu /ql.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) $_SERVER['REMOTE_ADDR'] ) : 'x';
		$k = 'pve_pinchan_' . md5( $ip );
		if ( get_transient( $k ) ) { return true; }
		set_transient( $k, 1, 1 ); return false;
	}
	private static function loi_pin() { return new WP_Error( 'pin', 'Sai mã PIN hoặc chưa cấu hình.', array( 'status' => 401 ) ); }
	public static function r_ql_dm_ds( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		return array( 'ok' => true, 'ds' => self::ds_dm() );
	}

	public static function r_ql_dm_luu( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$ten   = trim( sanitize_text_field( (string) $req->get_param( 'ten' ) ) );
		$ten_cu = trim( sanitize_text_field( (string) $req->get_param( 'ten_cu' ) ) );
		$anh   = esc_url_raw( (string) $req->get_param( 'anh' ) );
		if ( '' === $ten ) { return new WP_Error( 'ten', 'Cần tên danh mục.', array( 'status' => 400 ) ); }
		$ten = mb_substr( $ten, 0, 60 );
		$ds = self::ds_dm();
		/* Trùng tên với một danh mục KHÁC là hai dòng cùng tên trong dải chọn, và vé của cái này
		   đột nhiên đếm sang cái kia — chặn ngay chứ không gộp âm thầm. */
		foreach ( $ds as $d ) {
			if ( $d['ten'] === $ten && $ten !== $ten_cu ) {
				return new WP_Error( 'trung', 'Đã có danh mục tên "' . $ten . '".', array( 'status' => 409 ) );
			}
		}
		if ( '' !== $ten_cu && $ten_cu !== $ten ) { self::doi_ten_dm( $ten_cu, $ten ); }
		$moi = array(); $co = false;
		foreach ( self::ds_dm() as $d ) {
			if ( $d['ten'] === $ten ) { $co = true; $moi[] = array( 'ten' => $ten, 'anh' => $anh ); }
			elseif ( $d['khai'] ) { $moi[] = array( 'ten' => $d['ten'], 'anh' => $d['anh'] ); }
		}
		if ( ! $co ) { $moi[] = array( 'ten' => $ten, 'anh' => $anh ); }
		self::luu_dm( $moi );
		return array( 'ok' => true, 'ds' => self::ds_dm() );
	}

	public static function r_ql_dm_xoa( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$ten  = trim( sanitize_text_field( (string) $req->get_param( 'ten' ) ) );
		$sang = trim( sanitize_text_field( (string) $req->get_param( 'sang' ) ) );
		if ( '' === $ten ) { return new WP_Error( 'ten', 'Thiếu tên danh mục.', array( 'status' => 400 ) ); }
		/* Vé của danh mục bị xoá phải có chỗ đi — không thì chúng rơi vào một danh mục vô hình,
		   biến mất khỏi dải chọn mà vẫn nằm trong kho, tức là bán không ai thấy. */
		self::chuyen_ve_dm( $ten, '' !== $sang ? $sang : 'Vé' );
		$moi = array();
		foreach ( self::ds_dm() as $d ) { if ( $d['khai'] && $d['ten'] !== $ten ) { $moi[] = array( 'ten' => $d['ten'], 'anh' => $d['anh'] ); } }
		self::luu_dm( $moi );
		$m = self::ds_nhom_anh(); if ( isset( $m[ $ten ] ) ) { unset( $m[ $ten ] ); update_option( 'pve_nhom_anh', $m ); }
		return array( 'ok' => true, 'ds' => self::ds_dm() );
	}

	/* Tài khoản nhận tiền — anh Thắng 11/09/2026: "bổ sung cấu hình tài khoản nhận tiền" ngay
	   trong màn marketing, khỏi phải nhờ người có quyền wp-admin. */
	public static function r_ql_tk_ds( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$b = self::bank();
		/* Nói rõ số đang dùng là của CHÍNH plugin vé hay đang mượn của plugin Ghế: người sửa cần
		   biết mình đang sửa cái gì, không thì gõ vào đây mà tưởng đang đổi cho cả hai bên. */
		$rieng = '' !== trim( (string) get_option( 'pve_so_tk', '' ) );
		return array( 'ok' => true, 'bin' => $b['bin'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'],
			'ten_nh' => $b['ten_nh'], 'rieng' => $rieng ? 1 : 0, 'nh' => self::NGAN_HANG );
	}

	public static function r_ql_tk_luu( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$bin = preg_replace( '/\D+/', '', (string) $req->get_param( 'bin' ) );
		$tk  = preg_replace( '/[^0-9A-Za-z]/', '', (string) $req->get_param( 'so_tk' ) );
		$ten = trim( sanitize_text_field( (string) $req->get_param( 'ten_tk' ) ) );
		/* 🔴 Sai số tài khoản thì mã QR vẫn in ra bình thường, khách vẫn quét được, tiền mới đi
		   sai chỗ — hỏng ở đây không có dấu hiệu nào cho tới khi có người mất tiền. Nên chặn ngay
		   mấy lỗi thấy được: BIN phải nằm trong danh sách ngân hàng, số TK đủ dài, tên chủ TK
		   viết HOA không dấu (app ngân hàng đối chiếu tên, có dấu là báo sai). */
		if ( '' !== $bin && ! isset( self::NGAN_HANG[ $bin ] ) ) {
			return new WP_Error( 'bin', 'Mã ngân hàng (BIN) không có trong danh sách.', array( 'status' => 400 ) );
		}
		if ( '' !== $tk && strlen( $tk ) < 6 ) {
			return new WP_Error( 'tk', 'Số tài khoản trông quá ngắn — soi lại kẻo tiền về nhầm chỗ.', array( 'status' => 400 ) );
		}
		/* mb_strtoupper chứ không strtoupper: nếu remove_accents() bỏ sót một chữ tiếng Việt thì
		   strtoupper (tính theo byte) để nguyên chữ thường, tên gửi sang ngân hàng lệch một ký
		   tự — đúng loại lỗi chỉ lộ ra lúc khách chuyển tiền. */
		$ten = mb_strtoupper( remove_accents( $ten ), 'UTF-8' );
		update_option( 'pve_bin', $bin );
		update_option( 'pve_so_tk', $tk );
		update_option( 'pve_ten_tk', $ten );
		$b = self::bank();
		return array( 'ok' => true, 'bin' => $b['bin'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'],
			'ten_nh' => $b['ten_nh'], 'rieng' => 1 );
	}

	/**
	 * 🩺 SOI HỆ THỐNG — vì sao khách không mua được vé.
	 *
	 * Ba lượt liền anh Thắng báo "đặt vé chưa được" mà em phải ĐOÁN nguyên nhân: thiếu tài khoản
	 * nhận tiền? bảng thiếu cột? hết vé? Mỗi lượt đoán là một vòng cài lại. Hàm này đi hết các
	 * điều kiện của một lượt mua và nói thẳng cái nào hỏng — kể cả THỬ GHI MỘT ĐƠN NHÁP rồi xoá,
	 * chép nguyên văn lỗi của MySQL. Không còn gì để đoán nữa.
	 */
	public static function r_ql_soi( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		global $wpdb;
		$muc = array();
		$them = function ( $ten, $ok, $noi, $chua = '' ) use ( &$muc ) {
			$muc[] = array( 'ten' => $ten, 'ok' => $ok ? 1 : 0, 'noi' => $noi, 'chua' => $chua );
		};

		/* 1. Bảng vé và ba cột của bản 1.38.0 */
		$tbl = self::tbl();
		$co_bang = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl );
		$them( 'Bảng vé', $co_bang, $co_bang ? $tbl : 'Không thấy bảng ' . $tbl,
			$co_bang ? '' : 'Tắt rồi bật lại plugin để nó tạo bảng.' );
		if ( $co_bang ) {
			$cot = (array) $wpdb->get_col( "SHOW COLUMNS FROM $tbl" );
			$thieu = array_values( array_diff( array( 'coso', 'giam', 'gia_goc' ), $cot ) );
			$them( 'Cột của bản 1.38.0', ! $thieu,
				$thieu ? ( 'THIẾU: ' . implode( ', ', $thieu ) ) : 'đủ coso, giam, gia_goc',
				$thieu ? 'Cài lại plugin bản mới nhất; nó tự thêm cột lúc tải trang.' : '' );
		}

		/* 2. Tài khoản nhận tiền — thiếu là r_dat trả lỗi ngay, không tạo được mã QR */
		$b = self::bank();
		$co_tk = ( '' !== $b['so_tk'] && '' !== $b['bin'] );
		$them( 'Tài khoản nhận tiền', $co_tk,
			$co_tk ? ( $b['ten_nh'] . ' · ' . $b['so_tk'] . ' · ' . $b['ten_tk'] ) : 'Chưa khai',
			$co_tk ? '' : 'Vào mục 🏦 Tài khoản nhận tiền, điền rồi Lưu.' );

		/* 3. Có vé nào đang bán không */
		$dsv = self::ds();
		$them( 'Vé đang mở bán', count( $dsv ) > 0, count( $dsv ) . ' vé',
			count( $dsv ) ? '' : 'Tạo vé ở mục Vé, nhớ tích "Cho hiện bán".' );

		/* 4. THỬ GHI MỘT ĐƠN NHÁP rồi xoá — chỗ này mới bắt được lỗi thật của MySQL */
		if ( $co_bang ) {
			$ma = 'ZZTEST' . wp_generate_password( 6, false, false );
			$wpdb->hide_errors();
			$ghi = $wpdb->insert( $tbl, array(
				'ma_ve' => $ma, 'dv_ten' => 'THU HE THONG', 'so_tien' => 1000,
				'ten_khach' => 'THU', 'sdt' => '0', 'noi_dung' => 'VE' . $ma,
				'nguon' => 'web', 'coso' => '', 'giam' => 0, 'gia_goc' => 1000,
				'trang_thai' => 'huy', 'tao_luc' => current_time( 'mysql' ),
			) );
			$loi = (string) $wpdb->last_error;
			if ( $ghi ) { $wpdb->delete( $tbl, array( 'ma_ve' => $ma ) ); }
			$them( 'Thử ghi một đơn', (bool) $ghi,
				$ghi ? 'Ghi được và đã xoá đơn nháp' : 'KHÔNG ghi được',
				$ghi ? '' : ( '' !== $loi ? ( 'MySQL nói: ' . $loi ) : 'MySQL không nói lý do.' ) );
		}

		/* 5. ĐƯỜNG TỰ XÁC NHẬN CHUYỂN KHOẢN — chỗ hay đứt nhất, mà đứt thì im lặng.
		   Bốn câu hỏi tách bạch: có plugin Sao Kê chưa · bảng dựng chưa · đã nhận đồng nào chưa ·
		   và với những vé đang chờ, có giao dịch nào khớp mà chưa được đánh dấu không. Gộp cả bốn
		   vào một dòng "chưa xác nhận" là lại ngồi đoán như mấy hôm trước. */
		$co_sk = class_exists( 'SAOKE_App' ) && method_exists( 'SAOKE_App', 'tbl' );
		$them( 'Plugin Sao Kê', $co_sk, $co_sk ? 'Đã cài và bật' : 'CHƯA có',
			$co_sk ? '' : 'Chuyển khoản VietQR sẽ KHÔNG bao giờ tự xác nhận. Cài plugin Sao Kê trên cùng site này.' );
		if ( $co_sk ) {
			$tsk = SAOKE_App::tbl();
			$co_tsk = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tsk ) ) === $tsk );
			$them( 'Bảng sao kê', $co_tsk, $co_tsk ? $tsk : 'CHƯA dựng',
				$co_tsk ? '' : 'Mở trang Sao Kê một lần để plugin tự dựng bảng.' );
			if ( $co_tsk ) {
				$sl_in = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tsk WHERE loai='in'" );
				$them( 'Giao dịch tiền VÀO đã nhận', $sl_in > 0, $sl_in . ' giao dịch',
					$sl_in ? '' : 'Chưa nhận được đồng nào từ SePay. Kiểm webhook trong plugin Sao Kê (mục Kết nối).' );

				/* Với từng vé đang chờ: có dòng sao kê nào chứa đúng nội dung không. Đây là câu
				   trả lời thẳng cho "đã chuyển tiền rồi mà vé vẫn chờ". */
				$cho = $wpdb->get_results( "SELECT ma_ve, so_tien, noi_dung FROM $tbl WHERE trang_thai='cho' ORDER BY id DESC LIMIT 20", ARRAY_A );
				$khop = 0; $lech = array();
				foreach ( (array) $cho as $c ) {
					$co_nd = (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM $tsk WHERE loai='in' AND noi_dung LIKE %s", '%' . $wpdb->esc_like( $c['noi_dung'] ) . '%' ) );
					if ( ! $co_nd ) { continue; }
					$du = (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM $tsk WHERE loai='in' AND tien >= %d AND noi_dung LIKE %s",
						(int) $c['so_tien'], '%' . $wpdb->esc_like( $c['noi_dung'] ) . '%' ) );
					if ( $du ) { $khop++; } else { $lech[] = $c['ma_ve'] . ' (tiền về THIẾU so với ' . number_format_i18n( $c['so_tien'] ) . 'đ)'; }
				}
				if ( $cho ) {
					$them( 'Vé đang chờ đã có tiền về', 0 === count( $lech ),
						$khop . '/' . count( $cho ) . ' vé chờ đã thấy tiền về'
							. ( $khop ? ' — bấm "Dò lại sao kê" để đánh dấu' : '' ),
						$lech ? ( 'Tiền về không đủ: ' . implode( '; ', $lech ) ) : '' );
				}
			}
		}

		$hong = 0;
		foreach ( $muc as $m ) { if ( ! $m['ok'] ) { $hong++; } }
		return array( 'ok' => true, 'muc' => $muc, 'hong' => $hong, 'ban' => self::phien_ban() );
	}

	public static function r_ql_dangnhap( $req ) {
		if ( self::pin_chan() ) { return new WP_Error( 'nhip', 'Thử lại sau giây lát.', array( 'status' => 429 ) ); }
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		return array( 'ok' => true );
	}
	public static function r_ql_baocao( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tbl = self::tbl(); $paid = "trang_thai IN ('da_tt','da_dung')";
		$hnay = current_time( 'Y-m-d' ); $now = (int) current_time( 'timestamp' );

		// Bộ lọc: kỳ (tuan/thang/tatca) + loại vé (theo tên).
		$ky = sanitize_key( (string) $req->get_param( 'ky' ) );
		$ve = trim( (string) $req->get_param( 've' ) );
		$rng = ''; $ba = array(); $ndays = 14;
		if ( 'tuan' === $ky )       { $rng = ' AND tao_luc>=%s'; $ba[] = gmdate( 'Y-m-d 00:00:00', strtotime( '-6 day', $now ) ); $ndays = 7; }
		elseif ( 'thang' === $ky )  { $rng = ' AND tao_luc>=%s'; $ba[] = gmdate( 'Y-m-d 00:00:00', strtotime( '-29 day', $now ) ); $ndays = 30; }
		$vc = ''; if ( '' !== $ve ) { $vc = ' AND dv_ten LIKE %s'; $ba[] = '%' . $wpdb->esc_like( $ve ) . '%'; }
		$W = $paid . $rng . $vc;   // mệnh đề WHERE chung (đã lọc)

		$sum = function ( $extra = '' ) use ( $wpdb, $tbl, $W, $ba ) {
			$sql = "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $W$extra";
			return (int) ( $ba ? $wpdb->get_var( $wpdb->prepare( $sql, $ba ) ) : $wpdb->get_var( $sql ) );
		};
		$cnt = function ( $extra = '' ) use ( $wpdb, $tbl, $W, $ba ) {
			$sql = "SELECT COUNT(*) FROM $tbl WHERE $W$extra";
			return (int) ( $ba ? $wpdb->get_var( $wpdb->prepare( $sql, $ba ) ) : $wpdb->get_var( $sql ) );
		};

		// Vé bán chạy (trong phạm vi lọc) + ảnh.
		$anh_map = array(); $ds_ve = array();
		foreach ( self::ds_tatca() as $g ) { $ds_ve[] = $g['ten']; if ( '' !== (string) $g['anh'] ) { $anh_map[ $g['ten'] ] = $g['anh']; } }
		$sql_top = "SELECT dv_ten, COUNT(*) sl, COALESCE(SUM(so_tien),0) dt FROM $tbl WHERE $W GROUP BY dv_ten ORDER BY dt DESC LIMIT 100";
		$rows_top = $ba ? $wpdb->get_results( $wpdb->prepare( $sql_top, $ba ), ARRAY_A ) : $wpdb->get_results( $sql_top, ARRAY_A );
		$top = array();
		foreach ( (array) $rows_top as $r ) {
			$top[] = array( 'ten' => $r['dv_ten'], 'sl' => (int) $r['sl'], 'dt' => (int) $r['dt'],
				'anh' => isset( $anh_map[ $r['dv_ten'] ] ) ? $anh_map[ $r['dv_ten'] ] : '' );
		}
		// Số vé thực bán (cộng số lượng chi_tiet; đơn lẻ = 1).
		$sql_ct = "SELECT chi_tiet FROM $tbl WHERE $W";
		$cts = $ba ? $wpdb->get_col( $wpdb->prepare( $sql_ct, $ba ) ) : $wpdb->get_col( $sql_ct );
		$so_ve = 0;
		foreach ( (array) $cts as $ct ) {
			$arr = $ct ? json_decode( (string) $ct, true ) : null;
			if ( is_array( $arr ) && $arr ) { foreach ( $arr as $it ) { $so_ve += max( 1, (int) ( isset( $it['sl'] ) ? $it['sl'] : 1 ) ); } }
			else { $so_ve += 1; }
		}
		// Biểu đồ doanh thu theo ngày (số ngày tuỳ kỳ), vẫn theo loại vé đã lọc.
		$chart_lb = array(); $chart_dl = array();
		for ( $i = $ndays - 1; $i >= 0; $i-- ) {
			$d = gmdate( 'Y-m-d', strtotime( "-$i day", $now ) );
			$chart_lb[] = gmdate( 'd/m', strtotime( $d ) );
			$a = $ba; $a[] = $d;
			$chart_dl[] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $W AND DATE(tao_luc)=%s", $a ) );
		}
		return array( 'ok' => true,
			'ky'       => ( 'tuan' === $ky || 'thang' === $ky ) ? $ky : 'tatca',
			've_loc'   => $ve,
			'ds_ve'    => $ds_ve,
			'dt_tong'  => $sum(),
			'so_ve'    => $so_ve,
			've_ban'   => $cnt(),
			'chart_lb' => $chart_lb,
			'chart_dl' => $chart_dl,
			'dt_hnay'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND DATE(tao_luc)=%s", $hnay ) ),
			've_hnay'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tbl WHERE $paid AND DATE(tao_luc)=%s", $hnay ) ),
			'dt_thang' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND DATE_FORMAT(tao_luc,'%%Y-%%m')=%s", current_time( 'Y-m' ) ) ),
			've_cho'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE trang_thai='cho'" ),
			've_zalo'  => $cnt( " AND nguon='zalo'" ),
			've_web'   => $cnt( " AND nguon<>'zalo'" ),
			'dt_zalo'  => $sum( " AND nguon='zalo'" ),
			'dt_web'   => $sum( " AND nguon<>'zalo'" ),
			'top'      => $top );
	}
	public static function r_ql_donhang( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tbl = self::tbl();
		$loc = sanitize_key( (string) $req->get_param( 'loc' ) );
		$tim = trim( (string) $req->get_param( 'tim' ) );
		$where = array(); $args = array();
		if ( in_array( $loc, array( 'cho', 'da_tt', 'da_dung', 'huy' ), true ) ) { $where[] = 'trang_thai=%s'; $args[] = $loc; }
		if ( '' !== $tim ) { $like = '%' . $wpdb->esc_like( $tim ) . '%'; $where[] = '(ma_ve LIKE %s OR sdt LIKE %s OR ten_khach LIKE %s)'; array_push( $args, $like, $like, $like ); }
		if ( in_array( $loc, array( 'zalo', 'web' ), true ) ) {
			// cho phép lọc theo kênh: loc=zalo / loc=web
			$where[] = ( 'zalo' === $loc ? "nguon='zalo'" : "nguon<>'zalo'" );
		}
		$sql = "SELECT ma_ve, dv_ten, so_tien, ten_khach, sdt, trang_thai, nguon, tao_luc FROM $tbl";
		if ( $where ) { $sql .= ' WHERE ' . implode( ' AND ', $where ); }
		$sql .= ' ORDER BY id DESC LIMIT 60';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		$ds = array();
		foreach ( (array) $rows as $r ) {
			$ds[] = array( 'ma_ve' => $r['ma_ve'], 'dv_ten' => $r['dv_ten'], 'so_tien' => (int) $r['so_tien'],
				'ten_khach' => $r['ten_khach'], 'sdt' => $r['sdt'], 'trang_thai' => $r['trang_thai'],
				'nguon' => $r['nguon'], 'tao_luc' => $r['tao_luc'] );
		}
		return array( 'ok' => true, 'don' => $ds );
	}
	public static function r_ql_capnhat( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tbl = self::tbl();
		$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $req->get_param( 'ma_ve' ) ) );
		$xin = sanitize_key( (string) $req->get_param( 'trang_thai' ) );
		if ( '' === $ma ) { return new WP_Error( 'ma', 'Thiếu mã vé.', array( 'status' => 400 ) ); }
		if ( ! in_array( $xin, array( 'da_tt', 'da_dung', 'huy' ), true ) ) { return new WP_Error( 'tt', 'Trạng thái không hợp lệ.', array( 'status' => 400 ) ); }
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT sdt, ten_khach, so_tien, da_cong_diem FROM $tbl WHERE ma_ve=%s", $ma ), ARRAY_A );
		if ( ! $r ) { return new WP_Error( 'khong_co', 'Không tìm thấy vé.', array( 'status' => 404 ) ); }
		$wpdb->update( $tbl, array( 'trang_thai' => $xin, 'tt_luc' => current_time( 'mysql' ) ), array( 'ma_ve' => $ma ) );
		$diem = 0;
		if ( 'da_tt' === $xin && ! (int) $r['da_cong_diem'] ) {
			self::cong_diem( $r['sdt'], $r['ten_khach'], $r['so_tien'] );
			$wpdb->update( $tbl, array( 'da_cong_diem' => 1 ), array( 'ma_ve' => $ma ) );
			$diem = (int) floor( (int) $r['so_tien'] / 1000 );
		}
		return array( 'ok' => true, 'ma_ve' => $ma, 'trang_thai' => $xin, 'diem_cong' => $diem );
	}

	/* ── Marketing quản lý vé từ web (PIN) ── */
	public static function r_ql_ve_ds( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		return array( 'ok' => true, 'ds' => array_values( self::ds_tatca() ) );   // gồm cả vé đang ẩn
	}
	public static function r_ql_ve_luu( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$id  = (int) $req->get_param( 'id' );
		$moi = array(
			'id'         => $id,
			'ten'        => sanitize_text_field( (string) $req->get_param( 'ten' ) ),
			'gia'        => (int) preg_replace( '/\D+/', '', (string) $req->get_param( 'gia' ) ),
			'gia_goc'    => (int) preg_replace( '/\D+/', '', (string) $req->get_param( 'gia_goc' ) ),
			'nhom'       => sanitize_text_field( (string) $req->get_param( 'nhom' ) ),
			'khu_vuc'    => sanitize_text_field( (string) $req->get_param( 'khu_vuc' ) ),
			'mo_ta'      => sanitize_textarea_field( (string) $req->get_param( 'mo_ta' ) ),
			'anh'        => esc_url_raw( (string) $req->get_param( 'anh' ) ),
			'thoi_luong' => sanitize_text_field( (string) $req->get_param( 'thoi_luong' ) ),
			'so_luong'   => ( '' === trim( (string) $req->get_param( 'so_luong' ) ) ) ? -1 : max( 0, (int) preg_replace( '/\D+/', '', (string) $req->get_param( 'so_luong' ) ) ),
			'hien'       => $req->get_param( 'hien' ) ? 1 : 0,
		);
		if ( '' === $moi['ten'] || $moi['gia'] < 1000 ) { return new WP_Error( 'thieu', 'Cần tên vé và giá ≥ 1.000đ.', array( 'status' => 400 ) ); }
		$ds = self::ds_tatca(); $thay = false;
		if ( $id > 0 ) { foreach ( $ds as $k => $v ) { if ( (int) $v['id'] === $id ) { $ds[ $k ] = $moi; $thay = true; break; } } }
		if ( ! $thay ) { $moi['id'] = 0; $ds[] = $moi; }   // thêm mới -> luu_ds tự cấp id
		self::luu_ds( $ds );
		return array( 'ok' => true );
	}
	public static function r_ql_ve_xoa( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$id = (int) $req->get_param( 'id' );
		$ds = self::ds_tatca(); $ra = array();
		foreach ( $ds as $v ) { if ( (int) $v['id'] !== $id ) { $ra[] = $v; } }
		self::luu_ds( $ra );
		return array( 'ok' => true );
	}
	public static function r_ql_ve_anh( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		if ( empty( $_FILES['file'] ) ) { return new WP_Error( 'file', 'Chưa chọn ảnh.', array( 'status' => 400 ) ); }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$ov = array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' ) );
		$up = wp_handle_upload( $_FILES['file'], $ov );
		if ( ! is_array( $up ) || isset( $up['error'] ) ) { return new WP_Error( 'up', isset( $up['error'] ) ? $up['error'] : 'Tải ảnh thất bại.', array( 'status' => 400 ) ); }
		$aid = wp_insert_attachment( array(
			'post_mime_type' => $up['type'], 'post_title' => sanitize_file_name( basename( $up['file'] ) ),
			'post_content' => '', 'post_status' => 'inherit',
		), $up['file'] );
		if ( ! is_wp_error( $aid ) ) { wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $up['file'] ) ); }
		return array( 'ok' => true, 'url' => $up['url'] );
	}

	/* ── Marketing quản lý ƯU ĐÃI từ web (PIN) ── */
	public static function r_ql_uu_ds( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$hang = array(); foreach ( self::ds_hang() as $h ) { $hang[] = $h['ten']; }
		return array( 'ok' => true, 'ds' => array_values( self::ds_uudai_tatca() ), 'ds_hang' => $hang );
	}
	public static function r_ql_uu_luu( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$id  = (int) $req->get_param( 'id' );
		$moi = array(
			'id'    => $id,
			'ten'   => sanitize_text_field( (string) $req->get_param( 'ten' ) ),
			'mo_ta' => sanitize_textarea_field( (string) $req->get_param( 'mo_ta' ) ),
			'anh'   => esc_url_raw( (string) $req->get_param( 'anh' ) ),
			'hang'  => sanitize_text_field( (string) $req->get_param( 'hang' ) ),
			'han'   => sanitize_text_field( (string) $req->get_param( 'han' ) ),
			'hien'  => $req->get_param( 'hien' ) ? 1 : 0,
		);
		if ( '' === $moi['ten'] ) { return new WP_Error( 'thieu', 'Cần tên ưu đãi.', array( 'status' => 400 ) ); }
		$ds = self::ds_uudai_tatca(); $thay = false;
		if ( $id > 0 ) { foreach ( $ds as $k => $v ) { if ( (int) $v['id'] === $id ) { $ds[ $k ] = $moi; $thay = true; break; } } }
		if ( ! $thay ) { $moi['id'] = 0; $ds[] = $moi; }
		self::luu_uudai( $ds );
		return array( 'ok' => true );
	}
	public static function r_ql_uu_xoa( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$id = (int) $req->get_param( 'id' ); $ra = array();
		foreach ( self::ds_uudai_tatca() as $v ) { if ( (int) $v['id'] !== $id ) { $ra[] = $v; } }
		self::luu_uudai( $ra );
		return array( 'ok' => true );
	}

	/* ── Marketing quản lý HẠNG THÀNH VIÊN từ web (PIN) ── */
	public static function r_ql_hang_ds( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		return array( 'ok' => true, 'ds' => self::ds_hang() );
	}
	public static function r_ql_hang_luu( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		$ds = $req->get_param( 'ds' );
		if ( ! is_array( $ds ) ) { return new WP_Error( 'ds', 'Dữ liệu không hợp lệ.', array( 'status' => 400 ) ); }
		$ra = array();
		foreach ( $ds as $x ) {
			$ten = sanitize_text_field( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			$ra[] = array( 'ten' => mb_substr( $ten, 0, 40 ), 'moc' => max( 0, (int) preg_replace( '/\D+/', '', (string) ( isset( $x['moc'] ) ? $x['moc'] : 0 ) ) ) );
		}
		if ( ! $ra ) { return new WP_Error( 'trong', 'Cần ít nhất 1 hạng.', array( 'status' => 400 ) ); }
		usort( $ra, function ( $a, $b ) { return $a['moc'] - $b['moc']; } );
		update_option( 'pve_hang', array_values( $ra ) );
		return array( 'ok' => true, 'ds' => self::ds_hang() );
	}

	/* ── Danh sách khách hàng đã mua vé (từ bảng thành viên pve_tv) ── */
	public static function r_ql_khach( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tv = self::tbl_tv();
		$tim = trim( (string) $req->get_param( 'tim' ) );
		$sx  = sanitize_key( (string) $req->get_param( 'sx' ) );   // moi | chi | diem
		$order = 'chi' === $sx ? 'tong_chi DESC' : ( 'diem' === $sx ? 'diem DESC' : 'sua_luc DESC' );
		$sql = "SELECT sdt, ten, diem, tong_chi, so_don FROM $tv";
		$args = array();
		if ( '' !== $tim ) { $like = '%' . $wpdb->esc_like( $tim ) . '%'; $sql .= ' WHERE ten LIKE %s OR sdt LIKE %s'; $args = array( $like, $like ); }
		$sql .= ' ORDER BY ' . $order . ' LIMIT 300';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		$hangs = self::ds_hang();
		$ten_hang = function ( $diem ) use ( $hangs ) { $h = $hangs[0]['ten']; foreach ( $hangs as $x ) { if ( $diem >= $x['moc'] ) { $h = $x['ten']; } } return $h; };
		$ds = array(); $tong_kh = 0; $tong_chi = 0;
		foreach ( (array) $rows as $r ) {
			$diem = (int) $r['diem'];
			$ds[] = array( 'ten' => $r['ten'], 'sdt' => $r['sdt'], 'diem' => $diem,
				'so_don' => (int) $r['so_don'], 'tong_chi' => (int) $r['tong_chi'], 'hang' => $ten_hang( $diem ) );
			$tong_kh++; $tong_chi += (int) $r['tong_chi'];
		}
		return array( 'ok' => true, 'ds' => $ds, 'tong_kh' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tv" ), 'tong_chi' => $tong_chi );
	}

	private static function ma_ve_moi() {
		global $wpdb; $bang = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		for ( $lan = 0; $lan < 12; $lan++ ) {
			$s = ''; for ( $i = 0; $i < 8; $i++ ) { $s .= $bang[ random_int( 0, strlen( $bang ) - 1 ) ]; }
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::tbl() . ' WHERE ma_ve=%s', $s ) ) ) { return $s; }
		}
		return 'V' . substr( (string) time(), -7 );
	}
	private static function nhip_ok() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) $_SERVER['REMOTE_ADDR'] ) : 'x';
		$k  = 'pve_nhip_' . md5( $ip );
		if ( get_transient( $k ) ) { return false; }
		set_transient( $k, 1, 3 ); return true;
	}

	// ───────────────────────────── Trang bán vé công khai ([posh_ve]) ─────────────────────────────
	/* Dán [posh_ve] vào 1 trang trên khmatrix.com. Đặt vé ở đây đẩy thẳng vào cùng bảng
	 * pve_ve mà Zalo App + trang admin đọc — nên vé bán ở web/Zalo đều thấy chung một chỗ. */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'tieu_de'  => '',                              // tiêu đề nhỏ trong lưới (để trống = không lặp)
			'hero'     => 'Khu vui chơi POSH',             // tiêu đề banner hero
			'hero_phu' => 'Mua vé trước – nhận mã QR – vào cửa nhanh, không xếp hàng',
			'anh_nen'  => '',                              // ảnh nền hero (URL). Trống = nền gradient vàng
			'bang_ron' => '',                              // banner carousel: các URL ảnh, cách nhau dấu phẩy
			'an_theme' => '1',                             // 1 = ẩn header/footer mặc định của theme cho gọn
		), $atts, 'posh_ve' );
		$bangron = array_values( array_filter( array_map( 'trim', explode( ',', (string) $atts['bang_ron'] ) ) ) );
		$rest = esc_url_raw( rest_url( self::NS ) );
		$zu   = self::zalo_user();
		$ten_dn = $zu && ! empty( $zu['name'] ) ? $zu['name'] : '';

		// Gom dịch vụ theo nhóm + gom danh sách khu vực (để hiện màn chọn khu vực).
		$nhom = array(); $kvucs = array();
		foreach ( self::ds() as $g ) {
			$k = trim( (string) $g['nhom'] ) !== '' ? $g['nhom'] : 'Vé';
			$nhom[ $k ][] = $g;
			$kv = trim( (string) $g['khu_vuc'] );
			if ( '' !== $kv && ! in_array( $kv, $kvucs, true ) ) { $kvucs[] = $kv; }
		}
		/* Dải danh mục xếp theo THỨ TỰ TRONG MÀN PHÂN LOẠI, không theo thứ tự vé lọt vào. Marketing
		   xếp lại danh mục là để đổi cái khách nhìn thấy trước — xếp theo vé thì thêm một vé là
		   thứ tự nhảy lung tung. Danh mục chưa khai vẫn giữ, xếp xuống cuối. */
		$dm_luu = get_option( 'pve_dm' );
		if ( is_array( $dm_luu ) && $dm_luu ) {
			$sap = array();
			foreach ( $dm_luu as $d ) {
				$t = trim( (string) ( isset( $d['ten'] ) ? $d['ten'] : '' ) );
				if ( '' !== $t && isset( $nhom[ $t ] ) ) { $sap[ $t ] = $nhom[ $t ]; }
			}
			foreach ( $nhom as $t => $v ) { if ( ! isset( $sap[ $t ] ) ) { $sap[ $t ] = $v; } }
			$nhom = $sap;
		}

		ob_start();
		?>
		<?php if ( $atts['an_theme'] ) : ?>
		<style id="pve-an-theme">
		/* Ẩn header/footer/tiêu đề của theme */
		.wp-site-blocks > header.wp-block-template-part, .wp-site-blocks > footer.wp-block-template-part,
		header.wp-block-template-part, footer.wp-block-template-part,
		#masthead, #colophon, .site-header, .site-footer, .wp-block-site-title,
		.wp-block-post-title, .entry-header, header.entry-header { display:none !important; }
		/* Ẩn thanh admin bar WordPress (kèm reset khoảng đệm nó chiếm) */
		#wpadminbar { display:none !important; }
		html { margin-top:0 !important; }
		* html body { margin-top:0 !important; }
		body { margin:0 !important; padding:0 !important; }
		/* Ép nội dung TRÀN FULL MÀN — bỏ giới hạn chiều rộng, lề & padding của theme */
		.wp-site-blocks, .wp-site-blocks > *, .entry-content, .wp-block-group, main, .site-main, .content-area,
		.wp-block-post-content, article, .page, .type-page, .hentry,
		.is-layout-constrained, .is-layout-flow, .is-layout-constrained > *,
		.wp-block-post-content > *, .entry-content > *, .alignwide, .alignfull {
			max-width:none !important; width:auto !important;
			margin-left:0 !important; margin-right:0 !important;
			padding-left:0 !important; padding-right:0 !important;
			margin-top:0 !important; padding-top:0 !important;
		}
		/* Không cho container cha cắt phần tràn (nhiều theme đặt overflow-x:hidden) */
		html, body, .wp-site-blocks, .entry-content, .wp-block-post-content, main, article { overflow-x:clip !important; }
		/* Trang full-bleed: phá khung ra sát mép màn hình dù cha có căn giữa */
		.pve-page { position:relative !important; left:50% !important; right:50% !important;
			margin-left:-50vw !important; margin-right:-50vw !important; width:100vw !important; max-width:100vw !important; }
		</style>
		<?php endif; ?>
		<div class="pve-page">
		<div class="pve-hero"<?php echo $atts['anh_nen'] ? ' style="background-image:linear-gradient(rgba(255,253,246,.80),rgba(250,247,238,.92)),url(' . esc_url( $atts['anh_nen'] ) . ')"' : ''; ?>>
			<div class="pve-hero-in">
				<h1 class="pve-hero-t"><?php echo esc_html( $atts['hero'] ); ?></h1>
				<?php if ( $atts['hero_phu'] ) : ?><p class="pve-hero-p"><?php echo esc_html( $atts['hero_phu'] ); ?></p><?php endif; ?>
				<a href="#pve-ds" class="pve-hero-btn">Mua vé ngay ↓</a>
			</div>
		</div>
		<div class="pve-wrap" id="pve-ds">
			<?php if ( $bangron ) : ?>
			<div class="pve-bn">
				<div class="pve-bn-track"><?php foreach ( $bangron as $b ) : ?><img src="<?php echo esc_url( $b ); ?>" alt="banner" loading="lazy"><?php endforeach; ?></div>
				<?php if ( count( $bangron ) > 1 ) : ?><div class="pve-bn-dots"><?php foreach ( $bangron as $i => $b ) : ?><span<?php echo 0 === $i ? ' class="on"' : ''; ?>></span><?php endforeach; ?></div><?php endif; ?>
			</div>
			<?php endif; ?>
			<?php if ( $kvucs ) : ?><div class="pve-kvbar" hidden>📍 Khu vực: <b class="pve-kvbar-ten"></b> <a href="#" class="pve-kvbar-doi">Đổi khu vực</a></div><?php endif; ?>

			<div class="pve-auth">
				<?php if ( $zu ) : ?>
					<span>👋 Xin chào, <b><?php echo esc_html( $ten_dn ? $ten_dn : 'bạn' ); ?></b></span>
					<span class="pve-vitien" hidden>💰 Ví <b class="pve-vitien-sd">—</b>
						<button type="button" class="pve-vitien-nap">Nạp tiền</button></span>
					<span class="pve-auth-r">
						<?php if ( self::la_admin_zalo( $zu ) && ( $ql = self::url_trang_ql() ) ) : ?>
							<a class="pve-auth-ql" href="<?php echo esc_url( $ql ); ?>">🔧 Quản trị vé</a>
						<?php endif; ?>
						<a href="<?php echo esc_url( home_url( '/?pve_zalo=logout' ) ); ?>">Đăng xuất</a>
					</span>
				<?php else : ?>
					<span>Đăng nhập để đồng bộ vé với Zalo</span>
					<a class="pve-auth-btn" href="<?php echo esc_url( add_query_arg( array( 'pve_zalo' => 'login', 'back' => rawurlencode( self::url_hien_tai() ) ), home_url( '/' ) ) ); ?>">Đăng nhập bằng Zalo</a>
				<?php endif; ?>
			</div>

			<div class="pve-qf">
				<div class="pve-qf-h">🎟️ Đặt vé nhanh</div>
				<div class="pve-qf-grid">
					<input class="pve-qf-ten" placeholder="Họ và tên" autocomplete="name" value="<?php echo esc_attr( $ten_dn ); ?>">
					<input class="pve-qf-sdt" inputmode="tel" placeholder="Số điện thoại" autocomplete="tel">
					<?php if ( $kvucs ) : ?>
					<select class="pve-qf-cs">
						<option value="">Tất cả cơ sở</option>
						<?php foreach ( $kvucs as $kv ) : ?><option value="<?php echo esc_attr( $kv ); ?>"><?php echo esc_html( $kv ); ?></option><?php endforeach; ?>
					</select>
					<?php endif; ?>
					<select class="pve-qf-ve"><option value="">-- Chọn loại vé --</option></select>
					<input class="pve-qf-sl" type="number" min="1" value="1" title="Số lượng">
				</div>
				<button type="button" class="pve-qf-go">Mua vé ngay</button>
				<div class="pve-qf-err" hidden></div>
				<div class="pve-qf-note">Hoặc chọn loại vé ở danh mục bên dưới ↓</div>
			</div>
			<?php if ( '' !== trim( (string) $atts['tieu_de'] ) ) : ?><h2 class="pve-title"><?php echo esc_html( $atts['tieu_de'] ); ?></h2><?php endif; ?>
			<?php
			/* Thanh lọc — anh Thắng 11/09/2026: "tạo tab phân loại các loại vé, các loại khu đang có".
			   Hai hàng vì đây là hai câu hỏi khác nhau: "chơi ở đâu" và "mua loại gì". Gộp một hàng
			   thì mỗi lần thêm một khu là hàng dài thêm, và người ta không biết mình đang lọc theo
			   chiều nào. Hàng KHU chỉ hiện khi thật sự có nhiều hơn một khu — một khu mà bày tab
			   chọn khu là bắt người ta bấm một cái không đổi gì. */
			$co_tab_kv   = count( $kvucs ) > 1;
			$co_tab_nhom = count( $nhom ) > 1;
			?>
			<?php if ( $co_tab_kv || $co_tab_nhom ) : ?>
			<div class="pve-tabs">
				<?php if ( $co_tab_kv ) : ?>
				<div class="pve-tabr">
					<span class="pve-tabl">📍 Khu</span>
					<button type="button" class="pve-tab on" data-loc="kv" data-v="">Tất cả</button>
					<?php foreach ( $kvucs as $kv ) : ?>
						<button type="button" class="pve-tab" data-loc="kv" data-v="<?php echo esc_attr( $kv ); ?>"><?php echo esc_html( $kv ); ?></button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php if ( $co_tab_nhom ) : ?>
				<?php /* Dải danh mục có ẢNH — theo mẫu anh Thắng gửi 11/09/2026 (dải danh mục trong
				   Zalo Mini App): logo vuông + tên dưới, cuộn ngang, cái đang chọn gạch chân vàng.
				   Chữ không thôi thì năm danh mục nhìn như nhau; cái logo mới là thứ khách nhận ra
				   từ xa ("cái hình gấu tuyết" chứ không phải "chữ Snow Fun"). */ ?>
				<div class="pve-dm">
					<button type="button" class="pve-dmi on" data-loc="nhom" data-v="">
						<span class="pve-dmi-anh pve-dmi-all">🎟️</span>
						<span class="pve-dmi-ten">Tất cả</span>
					</button>
					<?php foreach ( $nhom as $tn => $ds_tn ) : $anh_dm = self::anh_nhom( $tn, $ds_tn ); ?>
						<button type="button" class="pve-dmi" data-loc="nhom" data-v="<?php echo esc_attr( $tn ); ?>">
							<span class="pve-dmi-anh">
								<?php if ( $anh_dm ) : ?>
									<img src="<?php echo esc_url( $anh_dm ); ?>" alt="<?php echo esc_attr( $tn ); ?>" loading="lazy">
								<?php else : ?>🎫<?php endif; ?>
							</span>
							<span class="pve-dmi-ten"><?php echo esc_html( $tn ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<div class="pve-tab-trong" hidden>Không có vé nào khớp bộ lọc này.</div>
			</div>
			<?php endif; ?>
			<?php if ( empty( $nhom ) ) : ?>
				<p class="pve-empty">Chưa có vé nào đang mở bán.</p>
			<?php endif; ?>
			<?php foreach ( $nhom as $ten_nhom => $ds ) : ?>
				<div class="pve-sec">
					<div class="pve-sec-h"><?php echo esc_html( $ten_nhom ); ?></div>
					<div class="pve-grid">
						<?php foreach ( $ds as $g ) :
							$sale = ( (int) $g['gia_goc'] > (int) $g['gia'] )
								? round( ( 1 - $g['gia'] / $g['gia_goc'] ) * 100 ) : 0;
								$het = ( $g['so_luong'] >= 0 && $g['so_luong'] < 1 ); ?>
							<div class="pve-card<?php echo $het ? ' pve-het' : ''; ?>"
								data-id="<?php echo (int) $g['id']; ?>"
								data-kv="<?php echo esc_attr( trim( (string) $g['khu_vuc'] ) ); ?>"
								data-nhom="<?php echo esc_attr( $ten_nhom ); ?>"
								data-ten="<?php echo esc_attr( $g['ten'] ); ?>"
								data-gia="<?php echo (int) $g['gia']; ?>">
								<div class="pve-img">
									<?php if ( $g['anh'] ) : ?>
										<img src="<?php echo esc_url( $g['anh'] ); ?>" alt="<?php echo esc_attr( $g['ten'] ); ?>" loading="lazy">
									<?php else : ?><span class="pve-noimg">🎟️</span><?php endif; ?>
									<?php if ( $sale > 0 ) : ?><span class="pve-sale">-<?php echo (int) $sale; ?>%</span><?php endif; ?>
								</div>
								<div class="pve-body">
									<div class="pve-ten"><?php echo esc_html( $g['ten'] ); ?></div>
									<?php if ( $g['thoi_luong'] ) : ?><div class="pve-tl">⏱ <?php echo esc_html( $g['thoi_luong'] ); ?></div><?php endif; ?>
									<?php if ( $g['mo_ta'] ) : ?><div class="pve-mota"><?php echo esc_html( $g['mo_ta'] ); ?></div><?php endif; ?>
									<?php if ( $g['so_luong'] >= 0 ) : ?><div class="pve-con"><?php echo $het ? 'Hết vé' : 'Còn ' . (int) $g['so_luong'] . ' vé'; ?></div><?php endif; ?>
									<div class="pve-foot">
										<div class="pve-gia-wrap">
											<span class="pve-gia"><?php echo esc_html( number_format_i18n( $g['gia'] ) ); ?>đ</span>
											<?php if ( $sale > 0 ) : ?><span class="pve-goc"><?php echo esc_html( number_format_i18n( $g['gia_goc'] ) ); ?>đ</span><?php endif; ?>
										</div>
										<?php if ( $het ) : ?><button type="button" class="pve-buy" disabled>Hết vé</button>
										<?php else : ?><button type="button" class="pve-them" title="Thêm vào giỏ" aria-label="Thêm vào giỏ">＋</button><button type="button" class="pve-buy">Đặt vé</button><?php endif; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php
			$ft_ten = get_option( 'pve_ft_ten', 'K&H COM., LTD' );
			$ft_dc  = get_option( 'pve_ft_dc', '' );
			$ft_lh  = get_option( 'pve_ft_lh', '' );
			$ft_nbu = get_option( 'pve_ft_nb_url', '' );
			$ft_nbt = get_option( 'pve_ft_nb_ten', 'Trang nội bộ' );
		?>
		<div class="pve-wrap">
		<?php
		/* ⚠️ Bọc trong .pve-wrap: khối này trước nằm NGOÀI khung bọc nội dung nên nó tràn sát
		   mép trái màn hình trong khi mọi khối khác căn giữa (ảnh anh Thắng 11/09/2026).
		   ── Ưu đãi & tin tức trên TRANG WEB ──────────────────────────────────────────────
		   Anh Thắng 11/09/2026: "thiếu trang tin tức ưu đãi". Ưu đãi vốn chỉ hiện trên Zalo
		   Mini App — khai ở màn quản trị rồi mà mở web không thấy đâu, nên trông như khai hụt.
		   Cùng một kho `pve_uudai`, chỉ thêm chỗ hiện: khai một lần, hai nơi cùng thấy. */
		$uu_ds = self::ds_uudai();
		?>
		<?php if ( $uu_ds ) : ?>
		<div class="pve-sec pve-uu" data-loc-bo="1">
			<div class="pve-sec-h">🎁 Ưu đãi đang có</div>
			<div class="pve-grid">
				<?php foreach ( $uu_ds as $u ) : ?>
				<div class="pve-card pve-uucard">
					<div class="pve-img">
						<?php if ( $u['anh'] ) : ?>
							<img src="<?php echo esc_url( $u['anh'] ); ?>" alt="<?php echo esc_attr( $u['ten'] ); ?>" loading="lazy">
						<?php else : ?><span class="pve-noimg">🎁</span><?php endif; ?>
					</div>
					<div class="pve-body">
						<div class="pve-ten"><?php echo esc_html( $u['ten'] ); ?></div>
						<?php if ( '' !== trim( (string) $u['mo_ta'] ) ) : ?><div class="pve-mota"><?php echo esc_html( $u['mo_ta'] ); ?></div><?php endif; ?>
						<div class="pve-uu-meta">
							<?php if ( '' !== trim( (string) $u['han'] ) ) : ?><span>⏳ HSD <?php echo esc_html( $u['han'] ); ?></span><?php endif; ?>
							<?php /* Ưu đãi có điều kiện hạng thì NÓI RA ngay trên thẻ: để khách tới quầy mới
							   biết mình không đủ hạng là một lần mất vui, và nhân viên hứng trọn. */ ?>
							<?php if ( '' !== trim( (string) $u['hang'] ) ) : ?><span>🏅 Từ hạng <?php echo esc_html( $u['hang'] ); ?></span><?php endif; ?>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php
		/* Khối công ty đầy đủ (MST, người đại diện, chi nhánh…) lấy từ plugin Ghế. Có nó thì
		   KHÔNG in lại ba dòng tên/địa chỉ/điện thoại khai riêng bên dưới — cùng một công ty mà
		   nói hai lần, hai nguồn, là sớm muộn hai chỗ lệch nhau. */
		$chan_cty = self::chan_trang_html();
		?>
		<div class="pve-ft">
			<?php if ( '' !== $chan_cty ) : ?>
				<?php echo $chan_cty; // phpcs:ignore WordPress.Security.EscapeOutput — HTML đã dựng sẵn, đã escape từng trường ?>
			<?php else : ?>
				<?php if ( $ft_ten ) : ?><div class="pve-ft-ten"><?php echo esc_html( $ft_ten ); ?></div><?php endif; ?>
				<?php if ( $ft_dc ) : ?><div class="pve-ft-l">📍 <?php echo esc_html( $ft_dc ); ?></div><?php endif; ?>
				<?php if ( $ft_lh ) : ?><div class="pve-ft-l">☎️ <?php echo esc_html( $ft_lh ); ?></div><?php endif; ?>
			<?php endif; ?>
			<?php if ( $ft_nbu ) : ?><div class="pve-ft-nb"><a href="<?php echo esc_url( $ft_nbu ); ?>"><?php echo esc_html( $ft_nbt ? $ft_nbt : 'Trang nội bộ' ); ?> →</a></div><?php endif; ?>
		</div>
		</div><!-- /pve-wrap (ưu đãi + chân trang) -->
		</div>

		<?php
		/* 🔴 MÀN CHÀO MỪNG CHỈ CÒN KHI KHÔNG CÓ THANH TAB.
		 *
		 * Anh Thắng 11/09/2026: "bấm không đặt vé được". Dựng lại được bằng trình duyệt: lớp phủ
		 * này position:fixed z-index:100000, khi hiện thì NÓ CHE NÚT ĐẶT VÉ — bấm vào nút là
		 * trúng lớp phủ, không ra gì và không báo gì. Nó hiện mỗi khi chưa có khu nào lưu trong
		 * sessionStorage; mà thanh tab thêm ở 1.40.0 lưu khu RỖNG mỗi lần bấm "Tất cả", nên lần
		 * tải sau lớp phủ lại bật lên. Hai tính năng đúng riêng lẻ, ghép vào thì chặn mất lối mua.
		 *
		 * Thanh tab làm đúng việc của màn này (chọn khu) mà không che gì cả, nên có tab thì bỏ
		 * hẳn lớp phủ. Giữ lại cho trường hợp chỉ có một khu — khi đó thanh tab khu không dựng.
		 */
		?>
		<?php if ( $kvucs && ! $co_tab_kv ) : ?>
		<div class="pve-wel" hidden>
			<div class="pve-wel-box">
				<div class="pve-wel-h">Chào mừng bạn! Vui lòng chọn khu vực</div>
				<div class="pve-wel-list">
					<?php foreach ( $kvucs as $kv ) : ?>
						<button type="button" class="pve-wel-kv" data-kv="<?php echo esc_attr( $kv ); ?>"><?php echo esc_html( $kv ); ?></button>
					<?php endforeach; ?>
				</div>
				<button type="button" class="pve-wel-ok">Xác nhận</button>
				<div class="pve-wel-note">Chọn khu vực để xem đúng vé đang mở bán tại đó.</div>
			</div>
		</div>
		<?php endif; ?>

		<?php
		/* 🩺 Nút soi trang: dựng TỪ MÁY CHỦ cho người đang đăng nhập quản trị WordPress, hoặc khi
		   mở kèm ?soi=1. Trước đây nút do JS tạo — mà chính JS mới là thứ đang nghi không chạy,
		   nên "không thấy nút" vừa có thể là chưa cài bản mới, vừa có thể là JS chết, không phân
		   biệt được. Dựng từ máy chủ thì: THẤY nút = PHP mới đã sống trên site; bấm nút mà không
		   ra bảng = JS không chạy. Hai câu trả lời khác nhau, tách bạch. */
		$soi_hien = ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) )
			|| ( isset( $_GET['soi'] ) && '1' === (string) $_GET['soi'] );   // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<?php if ( $soi_hien ) : ?>
		<button type="button" id="pve-soi-nut" style="position:fixed;right:12px;bottom:12px;z-index:2147483647;padding:10px 14px;border-radius:10px;border:none;background:#d4af37;color:#1a1204;font-weight:800;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.5)">🩺 Soi trang <span id="pve-soi-tt" style="font-weight:600">· JS chưa chạy</span></button>
		<?php endif; ?>

		<?php /* Nút giỏ do MÁY CHỦ dựng, cùng lý do với nút 🩺: thấy nút = bản mới đã sống. JS chỉ
				 gắn việc và cập nhật con số. Ẩn sẵn, có vé trong giỏ mới hiện. */ ?>
		<button type="button" id="pve-gio-nut" class="pve-gio-nut" hidden>🛒 <span class="pve-gio-n">0</span></button>
		<button type="button" id="pve-vi-nut" class="pve-vi-nut" hidden>🎫 <span class="pve-vi-n">0</span></button>

		<div class="pve-mask" hidden>
			<div class="pve-modal">
				<button type="button" class="pve-x" aria-label="Đóng">×</button>

				<?php /* Giỏ hàng dùng CHUNG khung popup với đặt vé lẻ: dựng popup thứ hai là hai bộ
						 mã đóng/mở, hai chỗ phải nhớ đưa ra <body>, và sớm muộn một bên lệch. Ba bước
						 (giỏ / form / QR) chỉ là ba khối .pve-step, show() bật đúng một khối. */ ?>
				<?php /* Ví vé: mã + QR của vé đã mua. Dữ liệu do máy chủ làm tươi (POSH_Ve::r_vi),
						 danh sách mã thì nằm ở máy khách — xem chú thích ở r_vi(). */ ?>
				<?php /* Nạp ví. Mệnh giá do máy chủ khai (POSH_Ve::ds_goi_nap) — trang không cho gõ số
						 tuỳ ý, xem chú thích ở r_vi_nap(). */ ?>
				<div class="pve-step pve-step-nap" data-step="nap" hidden>
					<div class="pve-m-ten2">💰 Nạp ví</div>
					<div class="pve-nap-sd">Số dư hiện tại: <b>—</b></div>
					<div class="pve-nap-goi"></div>
					<label class="pve-lb pve-nap-lb">Mã ưu đãi (nếu có)</label>
					<div class="pve-nap-code-h">
						<input class="pve-in pve-nap-code" placeholder="VD: HE2026" autocapitalize="characters" autocomplete="off">
						<button type="button" class="pve-nap-thu">Kiểm tra</button>
					</div>
					<div class="pve-nap-tt"></div>
					<button type="button" class="pve-go pve-nap-go">Tạo mã chuyển khoản</button>
					<div class="pve-err pve-nap-err" hidden></div>
					<p class="pve-note">Tiền vào ví ngay khi hệ thống nhận được chuyển khoản. Giữ đúng <b>nội dung</b> để tự khớp.</p>
				</div>
				<div class="pve-step pve-step-vi" data-step="vi" hidden>
					<div class="pve-m-ten">🎫 Vé của tôi</div>
					<div class="pve-vi-ds"></div>
					<div class="pve-vi-trong">Chưa có vé nào trên máy này.</div>
					<p class="pve-note">Đưa mã QR cho nhân viên quét khi vào cửa. Mỗi vé chỉ dùng được một lần.</p>
				</div>
				<div class="pve-step pve-step-gio" data-step="gio" hidden>
					<div class="pve-m-ten">🛒 Giỏ vé</div>
					<div class="pve-gio-ds"></div>
					<div class="pve-gio-trong">Giỏ đang trống. Bấm dấu <b>＋</b> ở thẻ vé để thêm.</div>
					<div class="pve-gio-tong"><span>Tổng cộng</span><b class="pve-gio-tien">0đ</b></div>
					<div class="pve-zme pve-zme-g" hidden><img class="pve-zme-a" alt="" hidden><span>Mua bằng tài khoản Zalo <b class="pve-zme-t"></b></span></div>
					<label class="pve-lb">Họ tên</label>
					<input class="pve-in pve-g-ten" placeholder="Tên người mua" autocomplete="name">
					<label class="pve-lb">Số điện thoại</label>
					<input class="pve-in pve-g-sdt" inputmode="tel" placeholder="Số Zalo/điện thoại" autocomplete="tel">
					<button type="button" class="pve-go2 pve-g-vi" hidden>Trả bằng ví</button>
					<button type="button" class="pve-go pve-g-go">Tạo mã thanh toán</button>
					<div class="pve-err pve-g-err" hidden></div>
					<p class="pve-note">Cả giỏ gộp thành MỘT mã chuyển khoản. Vé được xác nhận sau khi nhận đủ tiền.</p>
				</div>
				<div class="pve-step pve-step-form" data-step="form">
					<div class="pve-m-ten"></div>
					<div class="pve-m-gia"></div>
					<?php /* Điền bằng JS sau khi hỏi /zalo/toi — xem khối "THÔNG TIN ZALO" trong assets/ve.js. */ ?>
					<div class="pve-zme" hidden><img class="pve-zme-a" alt="" hidden><span>Mua bằng tài khoản Zalo <b class="pve-zme-t"></b></span></div>
					<label class="pve-lb">Họ tên</label>
					<input class="pve-in pve-f-ten" placeholder="Tên người mua" autocomplete="name">
					<label class="pve-lb">Số điện thoại</label>
					<input class="pve-in pve-f-sdt" placeholder="Số Zalo/điện thoại" inputmode="tel" autocomplete="tel">
					<button type="button" class="pve-go2 pve-f-vi" hidden>Trả bằng ví</button>
					<button type="button" class="pve-go">Tạo mã thanh toán</button>
					<div class="pve-err" hidden></div>
					<p class="pve-note">Bấm để tạo vé và hiện mã QR chuyển khoản. Vé được xác nhận sau khi nhận đủ tiền.</p>
				</div>

				<div class="pve-step pve-step-qr" data-step="qr" hidden>
					<div class="pve-badge cho">⏳ Chờ thanh toán</div>
					<div class="pve-cong">
						<button type="button" class="pve-cong-i on" data-cong="qr"><span>🏦</span>QR ngân hàng</button>
						<button type="button" class="pve-cong-i" data-cong="momo"><span>🟣</span>Momo</button>
						<button type="button" class="pve-cong-i" data-cong="vnpay"><span>🔵</span>VNPay</button>
					</div>
					<div class="pve-qr"></div>
					<div class="pve-cong-msg" hidden></div>
					<div class="pve-kv"><span>Mã vé</span><b class="pve-r-mave"></b></div>
					<div class="pve-kv"><span>Gói</span><b class="pve-r-goi"></b></div>
					<div class="pve-kv"><span>Số tiền</span><b class="pve-r-tien"></b></div>
					<div class="pve-bank">
						<div class="pve-kv"><span>Ngân hàng</span><b class="pve-r-nh"></b></div>
						<div class="pve-kv"><span>Số TK</span><b class="pve-r-stk"></b></div>
						<div class="pve-kv"><span>Chủ TK</span><b class="pve-r-ctk"></b></div>
						<div class="pve-kv pve-copy"><span>Nội dung</span><b class="pve-r-nd"></b> <em>(chạm để copy)</em></div>
					</div>
					<p class="pve-note">Quét mã bằng app ngân hàng. Giữ đúng <b>nội dung</b> để hệ thống tự khớp. Trang sẽ tự cập nhật khi đã nhận tiền.</p>
				</div>
			</div>
		</div>

		<?php
		/* ═══ NẠP VIỆC CHẠY MÁY BẰNG TỆP NGOÀI ═══════════════════════════════════════════════
		 * Trước đây gần 450 dòng JS nằm thẳng trong đầu ra shortcode. Ngày 11/09/2026 nó ngừng
		 * chạy trên site thật — nút 🩺 (do máy chủ dựng) thì hiện, bấm vào không ra bảng, tức
		 * khối script trong trang bị nuốt trước khi tới trình duyệt: plugin gộp/nén JS, tường
		 * lửa lọc thẻ script, hoặc wp_kses của trình dựng trang. Mà hỏng kiểu ấy IM LẶNG: không
		 * lỗi, không báo, chỉ là bấm Đặt vé không ra gì.
		 *
		 * Tệp ngoài qua wp_enqueue_script() thì các lớp kia đều tôn trọng, lại còn báo 404 rõ
		 * ràng nếu thiếu. Số bản đi kèm ?ver= nên cài đè xong là trình duyệt tự lấy bản mới,
		 * khỏi phải dặn "xoá cache".
		 */
		wp_enqueue_script( 'posh-ve', plugins_url( 'assets/ve.js', __FILE__ ), array(), self::phien_ban(), true );
		wp_localize_script( 'posh-ve', 'PVE_DATA', array(
			'rest' => $rest,
			'ban'  => self::phien_ban(),
			/* Cơ sở kèm toạ độ / % giảm / bán kính — để trang tự sắp cơ sở gần nhất lên trước và
			   hiện nhãn giảm giá. ⚠️ Chỉ để HIỆN. Giá thật do máy chủ chốt lại ở
			   POSH_Ve::giam_tai_cho() mỗi lượt đặt; sửa mấy con số này trong trình duyệt không
			   mua rẻ được đồng nào. */
			'cs'   => self::ds_coso(),
		) );
		?>
		<?php /* Một dòng duy nhất, để biết thẻ script TRONG TRANG có sống không. Bảng 🩺 đọc cờ
		         này: tệp ngoài chạy mà cờ tắt = đúng là có lớp nào đó nuốt script nhúng. */ ?>
		<script>window.PVE_INLINE=1;</script>

		<style>
		/* ═══ BỘ MÀU — NỀN SÁNG ═══════════════════════════════════════════════════════════════
		   Cả trang lấy màu từ mấy biến ngay dưới đây; đổi tông chỉ sửa chỗ này.

		   ⚠️ HAI THỨ DỄ SAI KHI ĐỔI TÔNG:
		   1. Bộ biến phải khai Ở CẢ `.pve-mask, .pve-wel` bên dưới — hai khối ấy bị JS đưa ra
		      thẳng <body>, ra khỏi .pve-page là mất biến (xem chú thích tại chỗ).
		   2. `--g2` là màu CHỮ vàng, phải đủ đậm để đọc trên nền trắng. Nút vàng KHÔNG dùng
		      --g2 nữa mà dùng `--gr` (dải vàng sáng + chữ #1a1204) — trước đây nút và chữ dùng
		      chung một màu, nên hễ làm chữ đọc được thì nút xỉn, làm nút đẹp thì chữ mờ tịt. */
		.pve-page{ --g:#b8912a; --g2:#8a6d1b; --gr:linear-gradient(135deg,#e7cd7a,#d4af37);
			--bg:#f7f5ef; --sf:#ffffff; --sf2:#f4f1e8; --bd:rgba(160,125,20,.26);
			--tx:#23201a; --mut:#6f6a5d; width:100vw; margin-left:calc(50% - 50vw); background:var(--bg); color:var(--tx);
			overflow:hidden; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
		.pve-page ::selection{ background:var(--g); color:#1b1810; }
		/* Hero */
		.pve-hero{ position:relative; background:radial-gradient(1200px 500px at 50% -10%,rgba(212,175,55,.30),transparent 60%),#fbf9f3;
			background-size:cover; background-position:center; padding:84px 20px 78px; text-align:center; border-bottom:1px solid var(--bd); }
		.pve-hero::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:1px; background:linear-gradient(90deg,transparent,var(--g),transparent); opacity:.6; }
		.pve-hero-in{ max-width:820px; margin:0 auto; position:relative; }
		.pve-hero-t{ color:#1b1810; font-family:Georgia,"Times New Roman",serif; font-size:clamp(30px,5.4vw,52px); font-weight:700;
			margin:0 0 16px; line-height:1.12; letter-spacing:.5px; }
		.pve-hero-t::after{ content:""; display:block; width:64px; height:2px; margin:18px auto 0; background:linear-gradient(90deg,transparent,var(--g),transparent); }
		.pve-hero-p{ color:#5d574a; font-size:clamp(14px,2.3vw,18px); margin:0 0 26px; letter-spacing:.3px; }
		.pve-hero-btn{ display:inline-block; background:var(--gr); color:#1a1204; font-weight:800;
			font-size:15px; letter-spacing:.5px; padding:14px 34px; border-radius:999px; text-decoration:none; text-transform:uppercase;
			box-shadow:0 10px 30px rgba(212,175,55,.28); }
		.pve-hero-btn:hover{ filter:brightness(1.06); color:#1a1204; }
		.pve-wrap{ max-width:1080px; margin:0 auto; padding:34px 16px 44px; }
		.pve-title{ font-size:22px; font-weight:800; margin:6px 0 14px; color:#1b1810; }
		/* Thanh lọc: hai hàng, cuộn ngang được trên điện thoại thay vì xuống dòng lung tung. */
		.pve-tabs{ margin:0 0 22px; display:flex; flex-direction:column; gap:8px; }
		.pve-tabr{ display:flex; gap:8px; align-items:center; overflow-x:auto; padding-bottom:2px;
			scrollbar-width:none; }
		.pve-tabr::-webkit-scrollbar{ display:none; }
		.pve-tabl{ color:var(--mut); font-size:13px; white-space:nowrap; flex:0 0 auto; min-width:64px; }
		.pve-tab{ flex:0 0 auto; border:1px solid var(--bd); background:rgba(255,255,255,.04); color:#e8e8ea;
			border-radius:999px; padding:8px 14px; font-size:14px; cursor:pointer; white-space:nowrap;
			transition:background .15s,border-color .15s,color .15s; }
		.pve-tab:hover{ background:rgba(255,255,255,.09); }
		.pve-tab.on{ background:var(--gr); border-color:transparent;
			color:#1a1204; font-weight:800; }
		.pve-tab-n{ opacity:.65; font-size:12px; margin-left:2px; }
		.pve-tab.on .pve-tab-n{ opacity:.75; }
		.pve-tab-trong{ color:var(--mut); font-size:14px; padding:6px 2px; }
		.pve-ban{ margin-top:14px; color:var(--mut); font-size:12px; line-height:1.7; }
		.pve-uucard{ cursor:default; }
		.pve-uucard:hover{ transform:none; }
		.pve-uu-meta{ display:flex; flex-wrap:wrap; gap:6px 14px; margin-top:8px; color:var(--mut); font-size:12px; }
		.pve-ban-s{ opacity:.62; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:11px; }
		/* Dải danh mục có ảnh (mẫu Zalo Mini App): cuộn ngang, mỗi ô ảnh vuông + tên dưới. */
		.pve-dm{ display:flex; gap:14px; overflow-x:auto; padding:4px 2px 6px; scrollbar-width:none; }
		.pve-dm::-webkit-scrollbar{ display:none; }
		.pve-dmi{ flex:0 0 auto; width:84px; background:none; border:none; padding:6px 0 8px; cursor:pointer;
			display:flex; flex-direction:column; align-items:center; gap:7px; border-bottom:3px solid transparent; }
		.pve-dmi-anh{ width:60px; height:60px; border-radius:14px; overflow:hidden; background:var(--sf2);
			border:1px solid var(--bd); display:flex; align-items:center; justify-content:center; font-size:26px;
			transition:border-color .15s, transform .15s; }
		.pve-dmi-anh img{ width:100%; height:100%; object-fit:cover; display:block; }
		.pve-dmi-ten{ font-size:12px; line-height:1.3; color:var(--mut); text-align:center;
			display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
		.pve-dmi:hover .pve-dmi-anh{ transform:translateY(-2px); }
		.pve-dmi.on{ border-bottom-color:var(--g); }
		.pve-dmi.on .pve-dmi-anh{ border-color:var(--g); }
		.pve-dmi.on .pve-dmi-ten{ color:var(--tx); font-weight:700; }
		.pve-empty{ color:var(--mut); }
		/* Section + lưới vé */
		.pve-sec{ margin-bottom:40px; }
		.pve-sec-h{ font-family:Georgia,"Times New Roman",serif; font-size:24px; font-weight:700; color:#1b1810; margin:0 0 18px;
			padding-bottom:10px; border-bottom:1px solid var(--bd); position:relative; }
		.pve-sec-h::after{ content:""; position:absolute; left:0; bottom:-1px; width:54px; height:2px; background:var(--g); }
		.pve-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:18px; }
		.pve-card{ background:var(--sf); border:1px solid var(--bd); border-radius:14px; overflow:hidden; display:flex; flex-direction:column;
			transition:transform .18s ease,box-shadow .18s ease,border-color .18s; }
		.pve-card:hover{ transform:translateY(-4px); border-color:rgba(212,175,55,.5); box-shadow:0 14px 32px rgba(60,50,20,.18); }
		.pve-img{ position:relative; aspect-ratio:4/3; background:#0f1116; }
		.pve-img img{ width:100%; height:100%; object-fit:cover; }
		.pve-img::after{ content:""; position:absolute; inset:0; background:linear-gradient(180deg,transparent 55%,rgba(11,12,16,.65)); }
		.pve-noimg{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:44px; opacity:.5; }
		.pve-sale{ position:absolute; top:10px; left:10px; z-index:2; background:var(--gr); color:#1a1204;
			font-weight:800; font-size:12px; padding:4px 11px; border-radius:999px; }
		.pve-body{ padding:14px 14px 15px; display:flex; flex-direction:column; gap:6px; flex:1; }
		.pve-ten{ font-weight:700; font-size:16px; color:#1b1810; line-height:1.3; }
		.pve-tl{ color:var(--mut); font-size:12px; }
		.pve-mota{ color:var(--mut); font-size:12px; line-height:1.45; }
		.pve-foot{ display:flex; justify-content:space-between; align-items:flex-end; margin-top:auto; padding-top:10px; }
		.pve-gia{ font-size:19px; font-weight:800; color:var(--g2); }
		.pve-goc{ font-size:12px; color:#8b8576; text-decoration:line-through; margin-left:6px; }
		.pve-buy{ border:1px solid var(--g); background:transparent; color:var(--g2); font-weight:700; font-size:13px; letter-spacing:.3px;
			padding:9px 16px; border-radius:999px; cursor:pointer; transition:.15s; }
		.pve-buy:hover{ background:var(--gr); color:#1a1204; }
		.pve-buy[disabled]{ border-color:#3a3a3a; color:#8b8576; background:transparent; cursor:not-allowed; }
		.pve-con{ font-size:12px; color:var(--mut); font-weight:600; }
		.pve-het{ opacity:.6; } .pve-het .pve-con{ color:#e07a7a; }
		/* Popup mua vé */
		/* 🔴 KHAI LẠI BỘ MÀU CHO POPUP — ĐỪNG BỎ.
		   .pve-mask và .pve-wel bị JS chuyển ra thẳng <body> (để nền mờ phủ kín, khỏi vướng theme
		   bọc transform). Ra khỏi .pve-page là mất luôn mấy biến màu khai ở đó, mà `color:var(--tx)`
		   khi --tx không tồn tại thì KHÔNG phải là bỏ qua: cả dòng thành "không hợp lệ lúc tính
		   giá trị" và color tụt về kế thừa — tức màu chữ mặc định của theme, ĐEN trên nền đen.
		   Đúng lỗi 11/09/2026 "nếu chữ đen": gõ tên vào ô mà không đọc được mình vừa gõ gì.
		   Đổi màu ở .pve-page thì đổi cả ở đây. */
		.pve-mask, .pve-wel{ --g:#b8912a; --g2:#8a6d1b; --gr:linear-gradient(135deg,#e7cd7a,#d4af37);
			--bg:#f7f5ef; --sf:#ffffff; --sf2:#f4f1e8; --bd:rgba(160,125,20,.26); --tx:#23201a; --mut:#6f6a5d; }
		/* Và chốt thẳng màu chữ ô nhập: nhiều theme đặt color/-webkit-text-fill-color cho input với
		   độ ưu tiên cao hơn, biến có đúng vẫn bị đè. Ô nhập thì phải đọc được, không thương lượng. */
		.pve-in, .pve-qf-grid input, .pve-qf-grid select{ color:#23201a !important; -webkit-text-fill-color:#23201a !important; }
		.pve-in:-webkit-autofill{ -webkit-text-fill-color:#23201a !important; -webkit-box-shadow:0 0 0 1000px #f4f1e8 inset; }
		.pve-mask[hidden], .pve-wel[hidden]{ display:none !important; }   /* [hidden] phải thắng display:flex */
		/* Hộp đặt vé phải nằm TRÊN mọi lớp phủ khác (màn chào mừng z-index 100000): thấp hơn là
		   bấm mở được mà hộp bị chôn bên dưới, nhìn y như không có chuyện gì xảy ra. */
		.pve-mask{ position:fixed; inset:0; background:rgba(32,28,18,.45); backdrop-filter:blur(3px); display:flex; align-items:center; justify-content:center; padding:16px; z-index:100001; }
		.pve-modal{ background:var(--sf); border:1px solid var(--bd); border-radius:18px; padding:22px; width:100%; max-width:390px; max-height:90vh; overflow:auto; position:relative; box-shadow:0 24px 60px rgba(60,50,20,.28); }
		.pve-x{ position:absolute; top:10px; right:12px; border:none; background:none; font-size:26px; line-height:1; color:var(--mut); cursor:pointer; }
		.pve-m-ten{ font-weight:800; font-size:18px; color:#1b1810; }
		.pve-m-gia{ font-weight:800; font-size:21px; color:var(--g2); margin:2px 0 14px; }
		.pve-lb{ display:block; font-size:13px; color:var(--mut); margin:10px 0 4px; font-weight:600; }
		.pve-in{ width:100%; box-sizing:border-box; border:1px solid #ddd6c4; background:var(--sf2); color:var(--tx); border-radius:10px; padding:11px 13px; font-size:15px; }
		.pve-zme{ display:flex; align-items:center; gap:8px; margin:2px 0 10px; padding:8px 10px; border-radius:10px;
			background:rgba(212,175,55,.1); border:1px solid var(--bd); color:var(--g2); font-size:12.5px; }
		.pve-zme[hidden]{ display:none; }
		.pve-zme img{ width:26px; height:26px; border-radius:50%; object-fit:cover; flex:none; }
		.pve-zme b{ color:#1b1810; }
		.pve-in::placeholder{ color:#8b8576; }
		.pve-in:focus{ outline:none; border-color:var(--g); }
		/* ── Giỏ vé ─────────────────────────────────────────────────────────────────────── */
		.pve-them{ border:1px solid var(--bd); background:var(--sf2); color:var(--g2); font-weight:800; font-size:15px;
			line-height:1; padding:8px 11px; border-radius:9px; cursor:pointer; flex:none; }
		.pve-them:hover{ border-color:var(--g); background:rgba(212,175,55,.14); }
		.pve-them.da{ background:var(--gr); color:#1a1204; border-color:var(--g); }
		/* Nút giỏ nổi. Đứng trên nút 🩺 (nút soi ở bottom:12px) để hai nút không chồng lên nhau. */
		.pve-gio-nut{ position:fixed; right:12px; bottom:70px; z-index:100002; display:flex; align-items:center; gap:7px;
			padding:11px 16px; border:none; border-radius:999px; cursor:pointer; font-size:15px; font-weight:800;
			background:var(--gr); color:#1a1204; box-shadow:0 8px 22px rgba(60,50,20,.22); }
		.pve-gio-nut[hidden]{ display:none; }
		.pve-gio-n{ background:#3a2c07; color:#f3dc9a; border-radius:999px; min-width:21px; padding:1px 6px; font-size:13px; text-align:center; }
		.pve-vi-nut{ position:fixed; right:12px; bottom:126px; z-index:100002; display:flex; align-items:center; gap:7px;
			padding:11px 16px; border:1px solid var(--g); border-radius:999px; cursor:pointer; font-size:15px; font-weight:800;
			background:#fff; color:#8a6d1b; box-shadow:0 8px 22px rgba(60,50,20,.22); }
		.pve-vi-nut[hidden]{ display:none; }
		.pve-vi-n{ background:#8a6d1b; color:#fff; border-radius:999px; min-width:21px; padding:1px 6px; font-size:13px; text-align:center; }
		.pve-vi-the{ border:1px solid var(--bd); border-radius:14px; padding:12px; margin:12px 0; background:var(--sf2); }
		.pve-vi-top{ display:flex; justify-content:space-between; align-items:center; gap:10px; }
		.pve-vi-goi{ font-weight:700; color:var(--tx); font-size:14px; }
		.pve-vi-ma{ font:800 19px/1.2 ui-monospace,Menlo,monospace; color:var(--g2); letter-spacing:1.5px; margin:6px 0 2px; }
		.pve-vi-qr{ background:#fff; border-radius:12px; padding:10px; width:max-content; max-width:100%; margin:10px auto 4px; }
		.pve-vi-qr svg{ display:block; }
		.pve-vi-phu{ color:var(--mut); font-size:12px; text-align:center; }
		.pve-vi-trong{ color:var(--mut); font-size:13px; padding:16px 0; text-align:center; }
		.pve-gio-ds{ margin:12px 0 0; }
		.pve-gio-h{ display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px dashed #e2dcca; }
		.pve-gio-ten{ flex:1; min-width:0; font-size:14px; color:var(--tx); }
		.pve-gio-ten small{ display:block; color:var(--mut); font-size:12px; }
		.pve-gio-sl{ display:flex; align-items:center; gap:6px; flex:none; }
		.pve-gio-sl button{ width:27px; height:27px; border-radius:8px; border:1px solid var(--bd); background:var(--sf2);
			color:var(--g2); font-size:16px; font-weight:800; line-height:1; cursor:pointer; }
		.pve-gio-sl b{ min-width:22px; text-align:center; color:var(--tx); font-size:14px; }
		.pve-gio-bo{ border:none; background:none; color:var(--mut); font-size:18px; cursor:pointer; flex:none; padding:0 2px; }
		.pve-gio-trong{ color:var(--mut); font-size:13px; padding:16px 0; text-align:center; }
		.pve-gio-tong{ display:flex; justify-content:space-between; align-items:center; margin-top:12px;
			padding-top:12px; border-top:1px solid var(--bd); font-size:14px; color:var(--mut); }
		.pve-gio-tien{ font-size:20px; font-weight:800; color:var(--g2); }
		.pve-go{ width:100%; margin-top:16px; border:none; background:var(--gr); color:#1a1204; font-weight:800; font-size:16px; padding:13px; border-radius:12px; cursor:pointer; }
		/* Nút trả bằng ví: KHÔNG đặt lớp .pve-go. Popup có bốn bước dùng chung khung, mà JS tra
		   '.pve-step-form .pve-go' — thêm một .pve-go thứ hai vào cùng bước là lại đúng cái bẫy đã
		   làm chết nút Đặt vé ở 1.48.0. */
		.pve-go2{ width:100%; margin-top:14px; border:1.5px solid var(--g); background:transparent; color:var(--g2);
			font-weight:800; font-size:15px; padding:12px; border-radius:12px; cursor:pointer; }
		.pve-go2[hidden]{ display:none; }
		.pve-go2:hover{ background:rgba(212,175,55,.12); }
		.pve-m-ten2{ font-weight:800; font-size:18px; color:#1b1810; }
		.pve-nap-sd{ color:var(--mut); font-size:13px; margin:2px 0 12px; } .pve-nap-sd b{ color:var(--g2); font-size:16px; }
		.pve-nap-goi{ display:grid; grid-template-columns:1fr 1fr; gap:8px; }
		.pve-nap-i{ border:1.5px solid #ddd6c4; background:var(--sf2); border-radius:12px; padding:11px 8px; cursor:pointer; text-align:center; }
		.pve-nap-i.on{ border-color:var(--g); background:rgba(212,175,55,.14); }
		.pve-nap-i b{ display:block; font-size:15px; color:#1b1810; }
		.pve-nap-i small{ color:#166534; font-weight:700; font-size:12px; }
		/* [hidden] phải thắng display:block/flex — không thì `el.hidden = true` chẳng giấu được gì.
		   Cùng bẫy với .pve-mask[hidden] ở trên. */
		.pve-lb[hidden], .pve-nap-code-h[hidden], .pve-go[hidden], .pve-nap-trong[hidden]{ display:none !important; }
		.pve-nap-code-h{ display:flex; gap:8px; }
		.pve-nap-code{ flex:1; text-transform:uppercase; }
		.pve-nap-thu{ flex:none; border:1px solid var(--bd); background:var(--sf2); color:var(--tx); border-radius:10px; padding:0 14px; font-weight:700; cursor:pointer; }
		.pve-nap-trong{ grid-column:1/-1; border:1px dashed var(--bd); border-radius:12px; padding:14px;
			color:var(--mut); font-size:13px; line-height:1.6; text-align:center; background:var(--sf2); }
		.pve-nap-trong b{ color:var(--g2); }
		.pve-nap-tt{ margin-top:10px; font-size:13px; }
		.pve-nap-tt.ok{ color:#166534; font-weight:700; } .pve-nap-tt.no{ color:#b91c1c; }
		.pve-vitien{ display:inline-flex; align-items:center; gap:8px; }
		.pve-vitien[hidden]{ display:none; }
		.pve-vitien b{ color:var(--g2); }
		.pve-vitien-nap{ border:1px solid var(--g); background:transparent; color:var(--g2); font-weight:700; font-size:12px;
			padding:4px 10px; border-radius:999px; cursor:pointer; }
		.pve-err{ color:#b91c1c; font-size:13px; margin-top:10px; }
		.pve-note{ color:var(--mut); font-size:12px; line-height:1.5; margin-top:12px; }
		.pve-cong{ display:flex; gap:8px; margin:4px 0 14px; }
		.pve-cong-i{ flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; padding:10px 4px; border:1.5px solid #ddd6c4; background:var(--sf2); border-radius:12px; font-size:12px; font-weight:700; color:var(--tx); cursor:pointer; }
		.pve-cong-i span{ font-size:22px; }
		.pve-cong-i.on{ border-color:var(--g); background:rgba(212,175,55,.12); color:var(--g2); }
		.pve-cong-msg{ background:rgba(212,175,55,.1); border:1px solid var(--bd); color:var(--g2); border-radius:10px; padding:12px; font-size:13px; margin-bottom:12px; text-align:center; }
		.pve-cong-msg a{ color:var(--g2); font-weight:800; }
		.pve-qr{ display:flex; justify-content:center; margin:6px auto 14px; background:#fff; padding:14px; border-radius:14px; width:max-content; max-width:100%; }
		.pve-qr img,.pve-qr canvas{ display:block; }
		.pve-badge{ display:inline-block; font-weight:800; padding:6px 14px; border-radius:999px; font-size:14px; margin-bottom:12px; }
		/* Màu trạng thái phải đọc được TRÊN NỀN SÁNG: bản nền tối dùng chữ xanh/đỏ nhạt, bê nguyên
		   sang nền trắng là gần như vô hình. */
		.pve-badge.cho{ background:rgba(212,175,55,.18); color:#7a5f12; }
		.pve-badge.da_tt{ background:rgba(34,197,94,.16); color:#166534; }
		.pve-badge.da_dung{ background:rgba(59,130,246,.14); color:#1d4ed8; }
		.pve-badge.huy{ background:rgba(239,68,68,.14); color:#991b1b; }
		.pve-kv{ display:flex; justify-content:space-between; gap:10px; font-size:14px; padding:7px 0; border-bottom:1px dashed #e2dcca; color:var(--mut); }
		.pve-kv b{ color:var(--tx); text-align:right; word-break:break-all; }
		.pve-copy{ cursor:pointer; } .pve-copy em{ color:#8b8576; font-size:11px; font-style:normal; }
		/* Màn chào mừng chọn khu vực */
		.pve-wel{ position:fixed; inset:0; background:rgba(32,28,18,.5); backdrop-filter:blur(3px); display:flex; align-items:center; justify-content:center; padding:16px; z-index:100000; }
		.pve-wel-box{ background:var(--sf); border:1px solid var(--bd); border-radius:18px; padding:30px 24px; width:100%; max-width:460px; text-align:center; box-shadow:0 24px 60px rgba(60,50,20,.28); }
		.pve-wel-h{ font-family:Georgia,"Times New Roman",serif; font-size:22px; font-weight:700; color:#1b1810; margin-bottom:20px; }
		.pve-wel-list{ display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:22px; }
		.pve-wel-kv{ border:1.5px solid #ddd6c4; background:var(--sf2); color:var(--tx); font-weight:700; font-size:15px; padding:15px 10px; border-radius:12px; cursor:pointer; transition:.15s; }
		.pve-wel-kv:hover{ border-color:var(--g); }
		.pve-wel-kv.on{ border-color:var(--g); background:rgba(212,175,55,.14); color:var(--g2); }
		.pve-wel-ok{ width:100%; border:none; background:var(--gr); color:#1a1204; font-weight:800; font-size:16px; padding:14px; border-radius:12px; cursor:pointer; }
		.pve-wel-ok:disabled{ background:#e7e2d3; color:#8b8576; cursor:not-allowed; }
		.pve-wel-note{ color:var(--mut); font-size:12px; margin-top:12px; }
		.pve-kvbar{ background:var(--sf); border:1px solid var(--bd); border-radius:12px; padding:10px 14px; margin-bottom:16px; font-size:14px; color:var(--mut); }
		.pve-kvbar b{ color:var(--tx); }
		.pve-kvbar-doi{ float:right; color:var(--g2); font-weight:700; text-decoration:none; }
		/* Đăng nhập Zalo */
		.pve-auth{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; background:var(--sf); border:1px solid var(--bd); border-radius:12px; padding:11px 15px; margin-bottom:18px; font-size:14px; color:var(--mut); }
		.pve-auth b{ color:var(--tx); }
		.pve-auth a{ color:var(--g2); font-weight:700; text-decoration:none; }
		.pve-auth-btn{ background:#0068ff; color:#fff !important; padding:8px 16px; border-radius:999px; }
		.pve-auth-r{ display:inline-flex; align-items:center; gap:14px; }
		.pve-auth-ql{ background:var(--gr); color:#1a1204 !important; font-weight:800; padding:7px 15px; border-radius:999px; }
		/* Banner carousel */
		.pve-bn{ position:relative; margin-bottom:22px; border-radius:16px; overflow:hidden; border:1px solid var(--bd); }
		.pve-bn-track{ display:flex; transition:transform .4s ease; }
		.pve-bn-track img{ width:100%; flex:0 0 100%; aspect-ratio:16/6; object-fit:cover; display:block; }
		.pve-bn-dots{ position:absolute; left:0; right:0; bottom:10px; display:flex; justify-content:center; gap:7px; }
		.pve-bn-dots span{ width:8px; height:8px; border-radius:50%; background:rgba(255,255,255,.5); cursor:pointer; }
		.pve-bn-dots span.on{ background:var(--g2); width:20px; border-radius:999px; }
		/* Chân trang */
		.pve-ft{ margin-top:24px; padding:30px 16px calc(28px + env(safe-area-inset-bottom)); background:#08090c; border-top:1px solid var(--bd); color:var(--mut); text-align:center; font-size:13px; line-height:1.8; }
		.pve-ft-ten{ font-family:Georgia,"Times New Roman",serif; font-weight:700; color:#1b1810; font-size:16px; letter-spacing:.3px; }
		.pve-ft-l{ color:var(--mut); }
		.pve-ft-nb a{ color:var(--g2); font-weight:700; text-decoration:none; }
		.pve-ft-ver{ color:#5f5c54; font-size:12px; margin-top:8px; }
		/* Form đặt vé nhanh */
		.pve-qf{ background:linear-gradient(180deg,#ffffff,#f6f2e6); border:1px solid var(--bd); border-radius:16px; padding:18px; margin:0 0 24px; box-shadow:0 12px 30px rgba(60,50,20,.10); }
		.pve-qf-h{ font-family:Georgia,"Times New Roman",serif; font-weight:700; font-size:19px; color:#1b1810; margin-bottom:14px; }
		.pve-qf-grid{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
		.pve-qf-grid input, .pve-qf-grid select{ width:100%; box-sizing:border-box; border:1px solid #ddd6c4; border-radius:10px; padding:11px 12px; font-size:14px; background:var(--sf2); color:var(--tx); }
		.pve-qf-grid input::placeholder{ color:#8b8576; }
		.pve-qf-grid input:focus, .pve-qf-grid select:focus{ outline:none; border-color:var(--g); }
		.pve-qf-ve{ grid-column:1 / -1; }
		.pve-qf-sl{ max-width:100%; }
		.pve-qf-go{ width:100%; margin-top:12px; border:none; background:var(--gr); color:#1a1204; font-weight:800; font-size:16px; letter-spacing:.3px; padding:13px; border-radius:12px; cursor:pointer; text-transform:uppercase; }
		.pve-qf-err{ color:#b91c1c; font-size:13px; margin-top:10px; }
		.pve-qf-note{ color:var(--mut); font-size:12px; text-align:center; margin-top:10px; }
		/* Băng trạng thái cửa hàng: nằm ngay trên khung đặt vé, vì nó nói giá đang là giá nào. */
		.pve-csbar{margin:0 auto 10px;max-width:1080px;padding:10px 14px;border-radius:12px;font-size:14px;line-height:1.5}
		.pve-csbar-ok{background:rgba(34,197,94,.14);border:1px solid rgba(34,197,94,.45);color:#bbf7d0}
		.pve-csbar-cho{background:rgba(234,179,8,.12);border:1px solid rgba(234,179,8,.40);color:#fde68a}
		.pve-csbar-kc{opacity:.75;font-size:12px}
		@media(max-width:520px){ .pve-qf-grid{ grid-template-columns:1fr; } }
		</style>
		<?php
		return ob_get_clean();
	}

	// ───────────── Trang quản trị vé trên web (cho marketing, đăng nhập PIN) ─────────────
	/* [posh_ql] — marketing tạo/sửa/xoá vé ngay trên web, không cần vào wp-admin.
	   Vé lưu chung kho -> tự lên trang bán vé + Zalo Mini App. Bảo vệ bằng PIN (khai ở admin). */
	/**
	 * TRANG SOÁT VÉ TẠI QUẦY — `[posh_soat]`.
	 *
	 * Nhân viên mở trên điện thoại/máy tính ở cửa hàng: vé nào vừa mua và đã trả tiền thì tự đẩy
	 * về đây kèm chuông báo; khách đưa mã, nhân viên quét hoặc gõ mã rồi xác nhận đã sử dụng.
	 *
	 * 🔴 QUYẾT ĐỊNH CHO VÀO CỬA NẰM Ở MÁY CHỦ, KHÔNG Ở MÀN HÌNH NÀY. Trang chỉ gửi mã lên và in
	 * lại câu trả lời. Mọi luật — đã trả tiền chưa, đã dùng rồi chưa, dùng ở đâu — kiểm trong
	 * POSH_Ve::r_soat(). Để màn hình tự kết luận thì sửa vài dòng trong trình duyệt là vé nào
	 * cũng "hợp lệ".
	 *
	 * Cơ sở và PIN nhớ trong localStorage của chính máy ấy: máy ở quầy nào khai một lần, đổi ca
	 * không phải gõ lại. PIN không đi vào mã nguồn (repo công khai) — xem CLAUDE.md mục 4.
	 */
	public static function shortcode_soat( $atts ) {
		$rest = esc_url_raw( rest_url( self::NS ) );
		$cs   = self::ds_coso();
		wp_enqueue_script( 'posh-soat', plugins_url( 'assets/soat.js', __FILE__ ), array(), self::phien_ban(), true );
		wp_localize_script( 'posh-soat', 'PSOAT', array( 'rest' => $rest, 'cs' => $cs, 'ban' => self::phien_ban() ) );
		ob_start();
		?>
		<div class="psoat">
			<div class="psoat-top">
				<b>🎫 Soát vé tại quầy</b>
				<span class="psoat-cs-ten"></span>
				<button type="button" class="psoat-doi" hidden>Đổi quầy</button>
			</div>

			<div class="psoat-dn">
				<label>Cơ sở đang đứng</label>
				<select class="psoat-cs">
					<?php foreach ( $cs as $c ) : ?>
					<option value="<?php echo esc_attr( $c['ten'] ); ?>"><?php echo esc_html( $c['ten'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<label>Mã PIN quầy</label>
				<input type="password" class="psoat-pin" inputmode="numeric" placeholder="PIN">
				<button type="button" class="psoat-vao">Vào ca</button>
				<div class="psoat-dn-err" hidden></div>
			</div>

			<div class="psoat-lam" hidden>
				<div class="psoat-quet">
					<input class="psoat-ma" placeholder="Quét hoặc gõ mã vé" autocomplete="off" autocapitalize="characters">
					<button type="button" class="psoat-go">Soát vé</button>
				</div>
				<button type="button" class="psoat-cam">📷 Quét bằng camera</button>
				<video class="psoat-video" playsinline hidden></video>
				<div class="psoat-kq" hidden></div>

				<div class="psoat-h">Vé đã trả tiền, chờ vào cửa <span class="psoat-n">0</span></div>
				<div class="psoat-ds"></div>
				<div class="psoat-trong">Chưa có vé nào chờ.</div>
			</div>
		</div>
		<style>
		.psoat{ max-width:560px; margin:0 auto; padding:16px; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
			color:#1f2430; }
		.psoat-top{ display:flex; align-items:center; gap:10px; font-size:17px; margin-bottom:14px; }
		.psoat-top b{ flex:1; }
		.psoat-cs-ten{ font-weight:700; color:#8a6d1b; }
		.psoat-doi{ border:1px solid #d9d4c6; background:#fff; border-radius:8px; padding:5px 10px; cursor:pointer; font-size:13px; }
		.psoat label{ display:block; font-size:13px; font-weight:600; color:#6b6a63; margin:10px 0 4px; }
		.psoat select,.psoat input{ width:100%; box-sizing:border-box; border:1px solid #d9d4c6; background:#fff; color:#1f2430;
			border-radius:10px; padding:11px 13px; font-size:16px; }
		.psoat-vao,.psoat-go,.psoat-cam{ width:100%; margin-top:12px; border:none; border-radius:12px; padding:13px;
			font-size:16px; font-weight:800; cursor:pointer; background:linear-gradient(135deg,#e7cd7a,#d4af37); color:#1a1204; }
		.psoat-cam{ background:#fff; border:1px solid #d9d4c6; color:#1f2430; font-weight:700; }
		.psoat-quet{ display:flex; gap:8px; align-items:flex-end; }
		.psoat-quet input{ flex:1; font:800 18px/1.2 ui-monospace,Menlo,monospace; letter-spacing:2px; text-transform:uppercase; }
		.psoat-quet .psoat-go{ width:auto; margin-top:0; padding:13px 18px; flex:none; }
		.psoat-video{ width:100%; border-radius:12px; margin-top:12px; background:#000; }
		.psoat-kq{ margin-top:14px; padding:14px; border-radius:12px; font-size:15px; font-weight:700; line-height:1.5; }
		.psoat-kq.ok{ background:#dcfce7; color:#14532d; border:1px solid #86efac; }
		.psoat-kq.no{ background:#fee2e2; color:#7f1d1d; border:1px solid #fca5a5; }
		.psoat-dn-err{ margin-top:10px; color:#b91c1c; font-size:14px; }
		.psoat-h{ margin:22px 0 8px; font-weight:700; font-size:15px; display:flex; gap:8px; align-items:center; }
		.psoat-n{ background:#d4af37; color:#1a1204; border-radius:999px; padding:1px 9px; font-size:13px; font-weight:800; }
		.psoat-the{ border:1px solid #e6e2d6; border-radius:12px; padding:11px 13px; margin-bottom:9px; background:#fff;
			display:flex; gap:10px; align-items:center; }
		.psoat-the .t{ flex:1; min-width:0; }
		.psoat-the .t b{ display:block; font-size:14px; }
		.psoat-the .t small{ color:#6b6a63; font-size:12px; }
		.psoat-the .m{ font:800 15px/1 ui-monospace,Menlo,monospace; color:#8a6d1b; letter-spacing:1px; }
		.psoat-the .xa{ background:#eef2ff; color:#3730a3; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:700; }
		.psoat-trong{ color:#6b6a63; font-size:13px; text-align:center; padding:14px 0; }
		</style>
		<?php
		return ob_get_clean();
	}

	public static function shortcode_ql( $atts ) {
		$atts   = shortcode_atts( array( 'an_theme' => '1' ), $atts, 'posh_ql' );
		$rest   = esc_url_raw( rest_url( self::NS ) );
		$co_pin = '' !== (string) get_option( 'pve_pin', '' );
		/* Vào thẳng nếu là quản trị WordPress hoặc Zalo trong danh sách quản trị. Tính ở máy chủ
		   rồi mới in trang — hỏi bằng một lượt gọi REST thì trang nháy qua ô PIN rồi mới nhảy vào,
		   trông như vừa bị đăng xuất. */
		$khong_pin = self::quan_tri_khong_pin();
		$zu_ql     = self::zalo_user();
		$kvucs  = array();
		foreach ( self::ds_tatca() as $g ) { $kv = trim( (string) $g['khu_vuc'] ); if ( '' !== $kv && ! in_array( $kv, $kvucs, true ) ) { $kvucs[] = $kv; } }
		ob_start();
		?>
		<?php if ( $atts['an_theme'] ) : ?>
		<style id="pql-an-theme">
		header.wp-block-template-part, footer.wp-block-template-part, #masthead, #colophon,
		.site-header, .site-footer, .wp-block-site-title, .wp-block-post-title, .entry-header, #wpadminbar { display:none !important; }
		/* Cả trang nền đen (hết trắng 2 bên) */
		html, body { margin:0 !important; padding:0 !important; background:#f7f5ef !important; }
		/* Bỏ giới hạn chiều rộng của theme để .pql tự căn giữa */
		.wp-site-blocks, .entry-content, .wp-block-group, main, .site-main, .content-area,
		.wp-block-post-content, article, .page, .hentry, .is-layout-constrained, .is-layout-flow,
		.wp-block-post-content > *, .entry-content > * {
			max-width:none !important; width:auto !important; margin-left:0 !important; margin-right:0 !important;
			padding-left:0 !important; padding-right:0 !important; background:transparent !important;
		}
		</style>
		<?php endif; ?>
		<div class="pql" data-rest="<?php echo esc_attr( $rest ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
			<div class="pql-top"><div class="pql-brand">🎟️ Quản trị vé — Marketing</div><button class="pql-out" hidden>Đăng xuất</button></div>

			<div class="pql-login">
				<div class="pql-card">
					<div class="pql-h">Đăng nhập</div>
					<?php if ( $khong_pin ) : ?>
						<p class="pql-ok">✅ Đang vào bằng <?php echo 'zalo' === $khong_pin ? 'tài khoản <b>Zalo quản trị</b>' : 'tài khoản <b>quản trị WordPress</b>'; ?><?php
							if ( 'zalo' === $khong_pin && $zu_ql && ! empty( $zu_ql['name'] ) ) { echo ' — ' . esc_html( $zu_ql['name'] ); }
						?> · không cần mã PIN.</p>
						<div class="pql-msg">Đang mở khu quản trị…</div>
					<?php else : ?>
						<?php
						/* Nói ra vì sao chưa vào được: lời chối của Zalo, hoặc đã đăng nhập rồi mà
						   ID chưa nằm trong danh sách quản trị. Im lặng ở đây chính là lỗi "bấm
						   zalo nhưng không phản hồi". */
						echo self::loi_zalo_html();          // phpcs:ignore WordPress.Security.EscapeOutput
						echo self::zalo_chua_trong_ds_html(); // phpcs:ignore WordPress.Security.EscapeOutput
						?>
						<?php if ( ! $co_pin ) : ?>
							<p class="pql-err">Chưa đặt mã PIN. Nhờ quản trị vào <b>WP Admin → Vé khu vui chơi → Khu quản lý (PIN)</b> đặt trước.</p>
						<?php else : ?>
							<input class="pql-pin" type="password" inputmode="numeric" placeholder="Nhập mã PIN" autocomplete="off">
							<button class="pql-dn">Vào quản trị</button>
							<div class="pql-msg"></div>
						<?php endif; ?>
						<?php
						/* Lối vào thứ hai cho quản trị đã khai Zalo ID — khỏi phải nhớ PIN.
						   Gắn `back` = chính trang này: referer không phải lúc nào cũng có, thiếu nó
						   thì đăng nhập xong rơi về trang chủ, trông như vào không được. */
						$url_zl = add_query_arg( array( 'pve_zalo' => 'login', 'back' => rawurlencode( self::url_hien_tai() ) ), home_url( '/' ) );
						?>
						<a class="pql-zalo" href="<?php echo esc_url( $url_zl ); ?>">Đăng nhập bằng Zalo quản trị</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="pql-app" hidden>
			<div class="pql-layout">
				<aside class="pql-side">
					<div class="pql-side-g">Bán vé</div>
					<button class="pql-tab on" data-tab="bc">📊 Tổng quan</button>
					<button class="pql-tab" data-tab="ve">🎟️ Vé</button>
					<button class="pql-tab" data-tab="dm">🗂️ Phân loại vé</button>
					<button class="pql-tab" data-tab="don">🧾 Đơn hàng &amp; soát vé</button>
					<div class="pql-side-g">Khách hàng</div>
					<button class="pql-tab" data-tab="kh">👥 Danh sách khách</button>
					<div class="pql-side-g">Loyalty</div>
					<button class="pql-tab" data-tab="uu">🎁 Ưu đãi</button>
					<button class="pql-tab" data-tab="hang">🏅 Hạng thành viên</button>
					<div class="pql-side-g">Cấu hình</div>
					<button class="pql-tab" data-tab="tk">🏦 Tài khoản nhận tiền</button>
					<button class="pql-tab" data-tab="soi">🩺 Kiểm tra hệ thống</button>
				</aside>
				<div class="pql-main">

				<div class="pql-pane" data-pane="ve" hidden>
				<div class="pql-bar">
					<b>Danh sách vé</b>
					<button class="pql-them">+ Tạo vé mới</button>
				</div>
				<div class="pql-list"></div>

				<div class="pql-form" hidden>
					<div class="pql-h pql-form-h">Tạo vé mới</div>
					<input type="hidden" class="f-id" value="0">
					<?php /* Phân loại LÊN ĐẦU (anh Thắng 11/09/2026): chọn vé thuộc nhóm nào là việc
					   nghĩ trước, tên và giá nghĩ sau. Để nó nằm giữa form thì người ta gõ xong
					   hết mới nhớ ra chưa chọn nhóm, mà bỏ trống là vé rơi vào nhóm "Vé" mặc định
					   — lọt xuống một danh mục không ai để ý. */ ?>
					<div class="pql-2">
						<div><label>Phân loại</label><input class="f-nhom" list="pql-dm" placeholder="Vé lẻ / Combo"><datalist id="pql-dm"></datalist></div>
						<div><label>Khu vực / Cơ sở</label><input class="f-kv" list="pql-kv" placeholder="để trống = mọi nơi"></div>
					</div>
					<datalist id="pql-kv"><?php foreach ( $kvucs as $kv ) : ?><option value="<?php echo esc_attr( $kv ); ?>"></option><?php endforeach; ?></datalist>
					<label>Tên vé *</label><input class="f-ten" placeholder="VD Vé vào cửa">
					<div class="pql-2">
						<div><label>Giá bán (đ) *</label><input class="f-gia" type="number" inputmode="numeric" placeholder="50000"></div>
						<div><label>Giá gốc (đ)</label><input class="f-goc" type="number" inputmode="numeric" placeholder="để trống nếu không giảm"></div>
					</div>
					<div class="pql-2">
						<div><label>Số lượng vé</label><input class="f-sl" type="number" inputmode="numeric" placeholder="trống = không giới hạn"></div>
						<div><label>Thời lượng</label><input class="f-tl" placeholder="60 phút / Cả ngày"></div>
					</div>
					<label>Mô tả</label><textarea class="f-mota" rows="2"></textarea>
					<label>Ảnh vé</label>
					<div class="pql-anh">
						<img class="f-xem" alt="" hidden>
						<input class="f-anh" placeholder="Dán link ảnh, hoặc tải ảnh lên →">
						<label class="pql-upl">Tải ảnh<input class="f-file" type="file" accept="image/*" hidden></label>
					</div>
					<label class="pql-ck"><input type="checkbox" class="f-hien" checked> Cho hiện bán (web + Zalo)</label>
					<div class="pql-acts">
						<button class="pql-luu">Lưu vé</button>
						<button class="pql-huy">Huỷ</button>
					</div>
					<div class="pql-msg2"></div>
				</div>
				</div><!-- /pane ve -->

				<div class="pql-pane" data-pane="dm" hidden>
					<div class="pql-bar"><b>Phân loại vé (danh mục)</b><button class="pql-dm-them">+ Tạo phân loại</button></div>
					<p style="color:var(--mut);font-size:12px;margin:0 0 10px">
						Danh mục là dải <b>ảnh + tên</b> khách bấm để chọn loại vé ở trang bán.
						Đổi tên ở đây thì <b>mọi vé đang thuộc danh mục đó đổi theo</b>, không phải sửa từng vé.
					</p>
					<div class="pql-dmlist"></div>
					<div class="pql-dmform" hidden>
						<div class="pql-h pql-dmform-h">Tạo phân loại</div>
						<input type="hidden" class="d-cu" value="">
						<label>Tên phân loại *</label><input class="d-ten" placeholder="VD Vé lẻ / Combo / Khu tuyết">
						<label>Ảnh đại diện</label>
						<div class="pql-anh">
							<img class="d-xem" alt="" hidden>
							<input class="d-anh" placeholder="Dán link ảnh, hoặc tải ảnh lên →">
							<label class="pql-upl">Tải ảnh<input class="d-file" type="file" accept="image/*" hidden></label>
						</div>
						<p style="color:var(--mut);font-size:12px;margin:6px 0 0">Bỏ trống ảnh thì dải chọn tự lấy ảnh của vé đầu tiên trong danh mục.</p>
						<div class="pql-acts"><button class="pql-dm-luu">Lưu phân loại</button><button class="pql-dm-huy">Huỷ</button></div>
						<div class="pql-dmmsg"></div>
					</div>
				</div><!-- /pane dm -->

				<div class="pql-pane" data-pane="tk" hidden>
					<div class="pql-bar"><b>Tài khoản nhận tiền</b></div>
					<p style="color:var(--mut);font-size:12px;margin:0 0 12px">
						Số tài khoản này đi thẳng vào <b>mã QR trên thiệp vé</b> của khách.
						Gõ sai thì mã QR vẫn in ra bình thường, khách vẫn quét được — <b>tiền mới đi sai chỗ</b>.
						Sửa xong hãy <b>chuyển thử 1.000đ</b> bằng app ngân hàng thật trước khi mở bán.
					</p>
					<div class="pql-tkmuon" style="display:none"></div>
					<div class="pql-2">
						<div><label>Ngân hàng *</label><select class="t-bin"><option value="">— Chọn ngân hàng —</option></select></div>
						<div><label>Số tài khoản *</label><input class="t-tk" inputmode="numeric" placeholder="VD 8888815678"></div>
					</div>
					<label>Chủ tài khoản *</label>
					<input class="t-ten" placeholder="VD NGUYEN VAN A">
					<p style="color:var(--mut);font-size:12px;margin:6px 0 0">Viết <b>HOA, không dấu</b> — app ngân hàng đối chiếu tên, có dấu là báo sai. Lưu xong hệ thống tự viết hoa bỏ dấu giúp.</p>
					<div class="pql-acts"><button class="pql-tk-luu">Lưu tài khoản</button></div>
					<div class="pql-tkmsg"></div>
				</div><!-- /pane tk -->

				<div class="pql-pane" data-pane="soi" hidden>
					<div class="pql-bar"><b>Kiểm tra hệ thống</b><button class="pql-soi-lai">Kiểm lại</button></div>
					<p style="color:var(--mut);font-size:12px;margin:0 0 12px">
						Đi hết các điều kiện của một lượt khách mua vé và chỉ ra cái nào hỏng.
						Bước cuối <b>thử ghi một đơn nháp rồi xoá</b> — nếu ghi không được thì chép nguyên văn câu MySQL trả lời.
					</p>
					<div class="pql-soilist"><p style="color:#6f6a5d">Đang kiểm…</p></div>
				</div><!-- /pane soi -->

				<div class="pql-pane" data-pane="bc">
					<div class="pql-bcf">
						<div class="pql-seg" data-seg="ky">
							<button data-v="tatca" class="on">Tất cả</button>
							<button data-v="tuan">Tuần</button>
							<button data-v="thang">Tháng</button>
						</div>
						<select class="pql-bcve"><option value="">Tất cả loại vé</option></select>
					</div>
					<div class="pql-kpi">
						<div class="pql-kpi-i"><span>Lợi nhuận</span><b class="pql-green" data-k="dt_tong">—</b></div>
						<div class="pql-kpi-i"><span>Tổng đơn hàng</span><b data-k="ve_ban">—</b></div>
						<div class="pql-kpi-i"><span>Giá vốn</span><b>0đ</b></div>
						<div class="pql-kpi-i"><span>Vé đã bán</span><b data-k="so_ve">—</b></div>
					</div>
					<div class="pql-cards">
						<div class="pql-stat"><span>Doanh thu hôm nay</span><b data-k="dt_hnay">—</b></div>
						<div class="pql-stat"><span>Doanh thu tháng</span><b data-k="dt_thang">—</b></div>
						<div class="pql-stat"><span>Vé bán hôm nay</span><b data-k="ve_hnay">—</b></div>
						<div class="pql-stat"><span>Vé đang chờ TT</span><b data-k="ve_cho">—</b></div>
					</div>
					<div class="pql-h2">Tổng doanh thu 7 ngày</div>
					<div class="pql-chart"><canvas id="pql-canvas"></canvas></div>
					<div class="pql-h2">Bán theo kênh</div>
					<table class="pql-tbl"><thead><tr><th>Kênh</th><th>Vé</th><th>Doanh thu</th></tr></thead>
						<tbody><tr><td>📱 Zalo Mini App</td><td data-k="ve_zalo">—</td><td data-k="dt_zalo">—</td></tr>
						<tr><td>🌐 Website</td><td data-k="ve_web">—</td><td data-k="dt_web">—</td></tr></tbody></table>
					<div class="pql-h2">Vé bán chạy</div>
					<div class="pql-topban"></div>
				</div><!-- /pane bc -->

				<div class="pql-pane" data-pane="don" hidden>
					<div class="pql-filter">
						<select class="pql-loc">
							<option value="">Tất cả</option><option value="cho">Chờ TT</option><option value="da_tt">Đã TT</option>
							<option value="da_dung">Đã dùng</option><option value="huy">Đã huỷ</option>
							<option value="zalo">Kênh Zalo</option><option value="web">Kênh Web</option>
						</select>
						<input class="pql-tim" placeholder="Tìm mã vé / SĐT / tên">
						<button class="pql-loc-btn">Lọc</button>
					</div>
					<div class="pql-donlist"></div>
				</div><!-- /pane don -->

				<div class="pql-pane" data-pane="uu" hidden>
					<div class="pql-bar"><b>Ưu đãi (hiện trên Zalo)</b><button class="pql-uu-them">+ Tạo ưu đãi</button></div>
					<div class="pql-uulist"></div>
					<div class="pql-uuform" hidden>
						<div class="pql-h pql-uuform-h">Tạo ưu đãi</div>
						<input type="hidden" class="u-id" value="0">
						<label>Tên ưu đãi *</label><input class="u-ten" placeholder="VD Giảm 20% vé Funzone">
						<label>Mô tả</label><textarea class="u-mota" rows="2"></textarea>
						<div class="pql-2">
							<div><label>Hạng cần (tối thiểu)</label><select class="u-hang"><option value="">Mọi khách</option></select></div>
							<div><label>Hạn dùng</label><input class="u-han" placeholder="VD 31/12/2026"></div>
						</div>
						<label>Ảnh ưu đãi</label>
						<div class="pql-anh">
							<img class="u-xem" alt="" hidden>
							<input class="u-anh" placeholder="Dán link ảnh, hoặc tải ảnh lên →">
							<label class="pql-upl">Tải ảnh<input class="u-file" type="file" accept="image/*" hidden></label>
						</div>
						<label class="pql-ck"><input type="checkbox" class="u-hien" checked> Cho hiện trên Zalo</label>
						<div class="pql-acts"><button class="pql-uu-luu">Lưu ưu đãi</button><button class="pql-uu-huy">Huỷ</button></div>
						<div class="pql-uumsg"></div>
					</div>
				</div><!-- /pane uu -->

				<div class="pql-pane" data-pane="hang" hidden>
					<div class="pql-bar"><b>Hạng thành viên (theo điểm)</b><button class="pql-hang-them">+ Thêm hạng</button></div>
					<p class="tp-empty-s" style="color:var(--mut);font-size:12px;margin:0 0 10px">1 điểm = 1.000đ chi tiêu. Khách đạt đủ điểm mốc sẽ lên hạng. Sửa xong bấm <b>Lưu hạng</b>.</p>
					<div class="pql-hanglist"></div>
					<div class="pql-acts"><button class="pql-hang-luu">Lưu hạng</button></div>
					<div class="pql-hangmsg"></div>
				</div><!-- /pane hang -->

				<div class="pql-pane" data-pane="kh" hidden>
					<div class="pql-khtop">
						<input class="pql-khtim" placeholder="Tìm tên / SĐT khách">
						<select class="pql-khsx"><option value="moi">Mới nhất</option><option value="chi">Chi nhiều nhất</option><option value="diem">Điểm cao nhất</option></select>
						<button class="pql-khloc">Lọc</button>
					</div>
					<div class="pql-khtong"></div>
					<div class="pql-khwrap"><table class="pql-tbl pql-khtbl"><thead><tr><th>Khách hàng</th><th>SĐT</th><th>Điểm</th><th>Hạng</th><th>Đơn</th><th>Tổng chi</th></tr></thead><tbody class="pql-khlist"></tbody></table></div>
				</div><!-- /pane kh -->

				</div><!-- /main -->
			</div><!-- /layout -->
			</div>
		</div>

		<style>
		/* 🔴 max-width PHẢI CÓ !important. Khối CSS "ẩn theme" ngay trên đầu shortcode này đặt
		   `max-width:none !important` cho mọi con trực tiếp của .entry-content — mà .pql chính là
		   một trong số đó. Không có !important ở đây thì luật kia thắng, trang quản trị giãn hết
		   bề ngang màn hình: trên máy tính mỗi thẻ đơn kéo dài cả mét, hai nút Xác nhận/Huỷ to
		   bằng nửa màn hình. Đúng cái anh Thắng chụp ngày 11/09/2026. */
		/* ═══ BỘ MÀU KHU QUẢN TRỊ — NỀN SÁNG ═══════════════════════════════════════════════
		   Đi theo trang khách (đổi ở 1.48.0). Để trang khách sáng mà khu quản trị tối thì nền
		   trắng của theme lòi ra quanh một khối đen, chữ tiêu đề màu sáng nằm trên nền sáng ấy
		   và gần như không đọc được — đúng cái anh Thắng chụp.
		   Cùng luật với trang khách: `--g2` là màu CHỮ vàng (phải đậm), nút vàng dùng `--gr`. */
		.pql{ --g:#b8912a; --g2:#8a6d1b; --gr:linear-gradient(135deg,#e7cd7a,#d4af37);
			--sf:#ffffff; --sf2:#f4f1e8; --bd:rgba(160,125,20,.26); --tx:#23201a; --mut:#6f6a5d;
			box-sizing:border-box; width:100%; max-width:900px !important; margin:0 auto !important; min-height:100vh;
			padding:24px 16px 56px; color:var(--tx);
			font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
		.pql *{ box-sizing:border-box; }
		.pql-top{ display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
		.pql-brand{ font-size:18px; font-weight:800; color:#1b1810; }
		.pql-out{ border:1px solid var(--bd); background:transparent; color:var(--g2); border-radius:8px; padding:6px 12px; cursor:pointer; font-weight:700; }
		.pql-card{ background:var(--sf); border:1px solid var(--bd); border-radius:14px; padding:20px; max-width:360px; margin:10px auto; }
		.pql-h{ font-size:16px; font-weight:800; color:#1b1810; margin-bottom:12px; }
		.pql label{ display:block; font-size:13px; color:var(--mut); margin:10px 0 4px; font-weight:600; }
		.pql input, .pql textarea, .pql-card input{ width:100%; border:1px solid #ddd6c4; background:var(--sf2); color:var(--tx); border-radius:10px; padding:11px 12px; font-size:15px; }
		.pql input::placeholder,.pql textarea::placeholder{ color:#6f6b61; }
		.pql input:focus,.pql textarea:focus{ outline:none; border-color:var(--g); }
		.pql-dn,.pql-luu,.pql-them{ border:none; background:var(--gr); color:#1a1204; font-weight:800; border-radius:10px; padding:12px; cursor:pointer; font-size:15px; }
		.pql-dn{ width:100%; margin-top:12px; }
		/* Tiêu đề phân loại trong danh sách vé */
		.pql-gh{ display:flex; align-items:center; gap:10px; margin:18px 0 8px; padding-bottom:6px;
			border-bottom:1px solid var(--bd); }
		.pql-gh:first-child{ margin-top:4px; }
		/* Màn soi hệ thống */
		.pql-soi-tong{ padding:12px 14px; border-radius:10px; margin-bottom:12px; font-size:14px; }
		.pql-soi-tong.tot{ background:rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.4); color:#bbf7d0; }
		.pql-soi-tong.xau{ background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.4); color:#991b1b; }
		.pql-soi-m{ display:flex; gap:10px; padding:11px 12px; border:1px solid var(--bd); border-radius:10px;
			background:var(--sf); margin-bottom:8px; }
		.pql-soi-i{ font-size:16px; line-height:1.4; }
		.pql-soi-t{ color:var(--tx); font-weight:700; font-size:14px; }
		.pql-soi-n{ color:var(--mut); font-size:12.5px; margin-top:2px; word-break:break-word; }
		.pql-soi-c{ color:#7a5f12; font-size:12.5px; margin-top:4px; }
		.pql-gh img{ width:28px; height:28px; border-radius:7px; object-fit:cover; }
		.pql-gh .noimg{ width:28px; height:28px; border-radius:7px; background:var(--sf2); display:flex;
			align-items:center; justify-content:center; font-size:15px; }
		.pql-gh b{ color:var(--tx); font-size:15px; }
		.pql-gh span{ color:var(--mut); font-size:12px; }
		.pql-ok{ color:#86efac; background:rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.35);
			border-radius:10px; padding:10px 12px; margin:0 0 10px; font-size:14px; line-height:1.5; }
		.pql-err-chi{ margin-top:6px; font-size:12px; opacity:.9; word-break:break-all; }
		.pql-err-chi code{ background:rgba(60,50,20,.10); padding:1px 5px; border-radius:5px; }
		.pql-zalo{ display:block; text-align:center; margin-top:10px; padding:10px; border-radius:10px;
			background:#0068ff; color:#fff; text-decoration:none; font-weight:700; font-size:14px; }
		.pql-msg,.pql-err{ color:#b91c1c; font-size:13px; margin-top:10px; text-align:center; }
		.pql-msg2{ font-size:13px; margin-top:10px; text-align:center; }
		.pql-bar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
		.pql-bar b{ color:#1b1810; font-size:16px; }
		.pql-them{ padding:9px 16px; }
		.pql-row{ display:flex; gap:12px; align-items:center; background:var(--sf); border:1px solid var(--bd); border-radius:12px; padding:10px 12px; margin-bottom:8px; }
		.pql-row img,.pql-row .noimg{ width:52px; height:52px; border-radius:8px; object-fit:cover; background:var(--sf2); display:flex; align-items:center; justify-content:center; font-size:22px; flex:0 0 auto; }
		.pql-row-mid{ flex:1; min-width:0; }
		.pql-row-ten{ font-weight:700; color:#1b1810; }
		.pql-row-sub{ font-size:12px; color:var(--mut); margin-top:2px; }
		.pql-row-gia{ color:var(--g2); font-weight:800; }
		.pql-tag{ font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; background:var(--sf2); color:var(--mut); margin-left:6px; }
		.pql-tag.on{ background:rgba(34,197,94,.18); color:#166534; } .pql-tag.off{ background:rgba(239,68,68,.16); color:#b91c1c; }
		.pql-row-btn{ display:flex; flex-direction:column; gap:6px; flex:0 0 auto; }
		.pql-row-btn button{ border:1px solid var(--bd); background:transparent; color:var(--tx); border-radius:8px; padding:6px 12px; font-size:12px; font-weight:700; cursor:pointer; }
		.pql-row-btn .del{ color:#b91c1c; border-color:rgba(239,68,68,.3); }
		.pql-form,.pql-uuform{ background:var(--sf); border:1px solid var(--bd); border-radius:14px; padding:18px; margin-top:8px; }
		.pql-uumsg{ font-size:13px; margin-top:10px; text-align:center; }
		.pql-hrow{ display:flex; gap:8px; margin-bottom:8px; align-items:center; }
		.pql-hrow .h-ten{ flex:1; } .pql-hrow .h-moc{ flex:0 0 140px; }
		.pql-hrow input{ border:1px solid #ddd6c4; background:var(--sf2); color:var(--tx); border-radius:10px; padding:10px 12px; font-size:14px; }
		.pql-hrow .h-del{ flex:0 0 auto; border:1px solid rgba(239,68,68,.3); background:transparent; color:#b91c1c; border-radius:8px; padding:9px 12px; cursor:pointer; font-weight:800; }
		.pql-hangmsg{ font-size:13px; margin-top:10px; text-align:center; }
		.pql-khtop{ display:flex; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
		.pql-khtim{ flex:1; min-width:150px; border:1px solid #ddd6c4; background:var(--sf2); color:var(--tx); border-radius:10px; padding:10px 12px; font-size:14px; }
		.pql-khsx{ border:1px solid #ddd6c4; background:var(--sf2); color:var(--tx); border-radius:10px; padding:10px 12px; font-size:14px; }
		.pql-khloc{ border:none; background:var(--gr); color:#1a1204; font-weight:800; border-radius:10px; padding:10px 16px; cursor:pointer; }
		.pql-khtong{ font-size:13px; color:var(--mut); margin-bottom:10px; } .pql-khtong b{ color:var(--g2); }
		.pql-khwrap{ overflow-x:auto; } .pql-khtbl{ min-width:520px; }
		.pql-2{ display:flex; gap:12px; } .pql-2 > div{ flex:1; }
		.pql-anh{ display:flex; gap:10px; align-items:center; }
		.pql-anh .f-anh{ flex:1; }
		.pql-anh img{ width:52px; height:52px; border-radius:8px; object-fit:cover; }
		.pql-upl{ background:var(--sf2); border:1px solid #ddd6c4; border-radius:10px; padding:11px 14px; cursor:pointer; white-space:nowrap; font-size:14px; margin:0 !important; color:var(--tx) !important; }
		.pql-ck{ display:flex; align-items:center; gap:8px; margin-top:12px; color:var(--tx) !important; }
		.pql-ck input{ width:auto; }
		.pql-acts{ display:flex; gap:10px; margin-top:16px; }
		.pql-acts button{ flex:1; }
		.pql-huy{ border:1px solid var(--bd); background:transparent; color:var(--tx); border-radius:10px; padding:12px; cursor:pointer; font-weight:700; }
		/* Layout HRM: sidebar trái + nội dung phải */
		.pql-layout{ display:flex; gap:18px; align-items:flex-start; }
		.pql-side{ flex:0 0 210px; position:sticky; top:16px; display:flex; flex-direction:column; gap:4px;
			background:var(--sf); border:1px solid var(--bd); border-radius:14px; padding:12px; }
		.pql-side-g{ font-size:11px; font-weight:800; color:var(--mut); text-transform:uppercase; letter-spacing:.5px; margin:10px 8px 4px; }
		.pql-side-g:first-child{ margin-top:2px; }
		.pql-main{ flex:1; min-width:0; }
		.pql-tab{ width:100%; text-align:left; border:none; background:transparent; color:var(--tx); border-radius:10px; padding:11px 12px; font-weight:700; font-size:14px; cursor:pointer; }
		.pql-tab:hover{ background:var(--sf2); }
		.pql-tab.on{ background:var(--gr); color:#1a1204; }
		@media(max-width:640px){
			.pql-layout{ flex-direction:column; }
			.pql-side{ flex:1 1 auto; width:100%; position:static; flex-direction:row; flex-wrap:wrap; }
			.pql-side-g{ display:none; }
			.pql-tab{ width:auto; flex:1 0 auto; text-align:center; padding:9px 12px; font-size:13px; }
		}
		.pql-bcf{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:14px; }
		.pql-seg{ display:inline-flex; border:1px solid var(--bd); border-radius:10px; overflow:hidden; }
		.pql-seg button{ border:none; background:var(--sf); color:var(--tx); padding:9px 16px; font-weight:700; font-size:13px; cursor:pointer; }
		.pql-seg button.on{ background:var(--gr); color:#1a1204; }
		.pql-bcve{ flex:1; min-width:160px; border:1px solid #ddd6c4; background:var(--sf2); color:var(--tx); border-radius:10px; padding:10px 12px; font-size:14px; }
		.pql-kpi{ display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:12px; }
		.pql-kpi-i{ background:linear-gradient(180deg,#ffffff,#f6f2e6); border:1px solid var(--bd); border-radius:12px; padding:14px; text-align:center; }
		.pql-kpi-i span{ display:block; font-size:12px; color:var(--mut); margin-bottom:6px; }
		.pql-kpi-i b{ font-size:19px; color:#1b1810; } .pql-kpi-i .pql-green{ color:#166534; }
		.pql-chart{ background:var(--sf); border:1px solid var(--bd); border-radius:12px; padding:14px; height:220px; }
		.pql-topban{ display:flex; flex-direction:column; gap:8px; }
		.pql-toprow{ display:flex; align-items:center; gap:12px; background:var(--sf); border:1px solid var(--bd); border-radius:12px; padding:10px 12px; }
		.pql-toprow img,.pql-toprow .noimg{ width:48px; height:48px; border-radius:8px; object-fit:cover; background:var(--sf2); display:flex; align-items:center; justify-content:center; font-size:20px; flex:0 0 auto; }
		.pql-topmid{ flex:1; min-width:0; } .pql-topten{ font-weight:700; color:#1b1810; font-size:14px; } .pql-topsub{ font-size:12px; color:var(--mut); }
		.pql-topdt{ color:var(--g2); font-weight:800; white-space:nowrap; }
		@media(max-width:560px){ .pql-kpi{ grid-template-columns:1fr 1fr; } }
		.pql-cards{ display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:6px; }
		.pql-stat{ background:var(--sf); border:1px solid var(--bd); border-radius:12px; padding:14px; }
		.pql-stat span{ display:block; font-size:12px; color:var(--mut); margin-bottom:6px; }
		.pql-stat b{ font-size:18px; color:var(--g2); }
		.pql-h2{ font-size:14px; font-weight:800; color:#1b1810; margin:18px 0 8px; }
		.pql-tbl{ width:100%; border-collapse:collapse; background:var(--sf); border:1px solid var(--bd); border-radius:12px; overflow:hidden; font-size:13px; }
		.pql-tbl th,.pql-tbl td{ padding:9px 10px; text-align:left; border-bottom:1px solid rgba(212,175,55,.1); color:var(--tx); }
		.pql-tbl th{ background:var(--sf2); font-size:12px; color:var(--mut); }
		.pql-filter{ display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
		.pql-filter .pql-loc{ width:auto; flex:0 0 130px; } .pql-filter .pql-tim{ flex:1; min-width:140px; } .pql-filter .pql-loc-btn{ width:auto; flex:0 0 70px; padding:11px; }
		.pql-don{ background:var(--sf); border:1px solid var(--bd); border-radius:12px; padding:12px; margin-bottom:8px; }
		.pql-don-top{ display:flex; justify-content:space-between; align-items:center; }
		.pql-don-ma{ font-weight:800; font-family:monospace; color:#1b1810; }
		.pql-don-sub{ font-size:12px; color:var(--mut); margin-top:3px; }
		.pql-bdg{ font-size:11px; font-weight:800; padding:3px 9px; border-radius:999px; }
		.pql-bdg.cho{ background:rgba(212,175,55,.15); color:var(--g2); } .pql-bdg.da_tt{ background:rgba(34,197,94,.18); color:#166534; }
		.pql-bdg.da_dung{ background:rgba(59,130,246,.14); color:#93c5fd; } .pql-bdg.huy{ background:rgba(239,68,68,.16); color:#b91c1c; }
		.pql-don-act{ display:flex; gap:6px; margin-top:10px; }
		.pql-don-act button{ flex:0 1 200px; border:1px solid var(--bd); background:transparent; color:var(--tx); border-radius:8px; padding:8px 4px; font-size:12px; font-weight:700; cursor:pointer; }
		.pql-don-act .go{ background:#16a34a; color:#fff; border:none; } .pql-don-act .use{ background:#2563eb; color:#fff; border:none; } .pql-don-act .no{ background:#dc2626; color:#fff; border:none; }
		@media(max-width:560px){ .pql-2{ flex-direction:column; gap:0; } }
		</style>

		<script>
		(function(){
		  var root = document.querySelector('.pql'); if(!root) return;
		  var REST = root.getAttribute('data-rest'), PIN='';
		  /* ⚠️ Gọi REST từ trình duyệt thì WordPress CHỈ nhận ra người đang đăng nhập khi có nonce.
		     Thiếu nó, current_user_can('manage_options') trong quan_tri_khong_pin() luôn trả false
		     -> quản trị WordPress mở được màn hình nhưng mọi lượt gọi trả 401, trông như hỏng
		     ngẫu nhiên. Lối vào bằng Zalo không cần nonce (plugin tự đọc cookie đã ký của nó). */
		  var NONCE = root.getAttribute('data-nonce') || '';
		  function hdr(extra){ var h = extra || {}; if (NONCE) h['X-WP-Nonce'] = NONCE; return h; }
		  var $=function(s){return root.querySelector(s);};
		  var VND=function(n){try{return (n||0).toLocaleString('vi-VN')+'đ';}catch(e){return (n||0)+'đ';}};
		  var esc=function(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});};
		  function post(path,body){ body=body||{}; body.pin=PIN;
		    return fetch(REST+path,{method:'POST',credentials:'same-origin',headers:hdr({'Content-Type':'application/json'}),body:JSON.stringify(body)})
		      .then(function(r){return r.json().then(function(d){if(!r.ok||d.ok===false)throw new Error(d&&(d.message||d.code)||'Lỗi');return d;});}); }
		  function get(path){ var u=new URL(REST+path); u.searchParams.set('pin',PIN);
		    return fetch(u.toString(),{credentials:'same-origin',headers:hdr()}).then(function(r){return r.json().then(function(d){if(!r.ok||d.ok===false)throw new Error(d&&(d.message||d.code)||'Lỗi');return d;});}); }

		  // Đăng nhập
		  /* Máy chủ đã nhận ra quản trị (Zalo/WordPress) -> mở thẳng, PIN để rỗng. Mọi route /ql/*
		     vẫn tự kiểm lại quyền ở máy chủ mỗi lượt gọi, nên bỏ qua màn PIN ở đây không nới lỏng
		     gì: đặt PQL_AUTO=true bằng tay trong trình duyệt chỉ khiến các lượt gọi trả 401. */
		  var PQL_AUTO = <?php echo $khong_pin ? 'true' : 'false'; ?>;
		  if (PQL_AUTO) {
		    PIN='';
		    $('.pql-login').hidden=true; $('.pql-app').hidden=false; $('.pql-out').hidden=false;
		    napBaoCao();
		  }
		  var dn=$('.pql-dn');
		  if(dn){
		    var go=function(){ var v=($('.pql-pin').value||'').trim(); if(!v)return; $('.pql-msg').textContent='Đang kiểm tra…'; PIN=v;
		      post('/ql/dangnhap',{}).then(function(){ $('.pql-login').hidden=true; $('.pql-app').hidden=false; $('.pql-out').hidden=false; napBaoCao(); })
		        .catch(function(e){ PIN=''; $('.pql-msg').textContent=String(e.message||e); }); };
		    dn.addEventListener('click',go);
		    $('.pql-pin').addEventListener('keydown',function(e){if(e.key==='Enter')go();});
		  }
		  $('.pql-out').addEventListener('click',function(){ PIN=''; $('.pql-app').hidden=true; $('.pql-login').hidden=false; $('.pql-out').hidden=true; if($('.pql-pin')){$('.pql-pin').value='';$('.pql-msg').textContent='';}
		    /* Vào bằng Zalo/WordPress thì "Thoát" chỉ đóng khu quản trị, tải lại trang là vào tiếp —
		       quyền nằm ở phiên đăng nhập chứ không ở màn này. Nói thẳng ra, không để người ta
		       tưởng đã thoát hẳn rồi đưa máy cho người khác. */
		    if (PQL_AUTO && $('.pql-msg')) { $('.pql-msg').textContent='Đã đóng khu quản trị. Bạn vẫn đang đăng nhập quản trị — tải lại trang là vào tiếp; muốn thoát hẳn thì đăng xuất Zalo/WordPress.'; }
		  });

		  // Tabs: Vé / Báo cáo / Đơn & soát
		  Array.prototype.forEach.call(root.querySelectorAll('.pql-tab'),function(t){
		    t.addEventListener('click',function(){
		      Array.prototype.forEach.call(root.querySelectorAll('.pql-tab'),function(x){x.classList.remove('on');}); t.classList.add('on');
		      var name=t.getAttribute('data-tab');
		      Array.prototype.forEach.call(root.querySelectorAll('.pql-pane'),function(p){ p.hidden = p.getAttribute('data-pane')!==name; });
		      if(name==='ve') napDs(); if(name==='dm') napDm(); if(name==='tk') napTk(); if(name==='soi') napSoi(); if(name==='bc') napBaoCao(); if(name==='don') napDon(); if(name==='uu') napUu(); if(name==='hang') napHang(); if(name==='kh') napKhach();
		    });
		  });

		  // Báo cáo
		  var NHAN={cho:'Chờ TT',da_tt:'Đã TT',da_dung:'Đã dùng',huy:'Đã huỷ'};
		  var chart=null;
		  function veChart(lb,dl){
		    var c=root.querySelector('#pql-canvas'); if(!c) return;
		    function draw(){
		      if(!window.Chart){ setTimeout(draw,150); return; }
		      if(chart){ chart.destroy(); }
		      chart=new Chart(c,{type:'line',data:{labels:lb,datasets:[{label:'Doanh thu',data:dl,borderColor:'#d4af37',backgroundColor:'rgba(212,175,55,.12)',fill:true,tension:.35,pointRadius:3,borderWidth:2}]},
		        options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{ticks:{color:'#6f6a5d'},grid:{color:'rgba(60,50,20,.10)'}},y:{beginAtZero:true,ticks:{color:'#6f6a5d',callback:function(v){return Number(v).toLocaleString('vi-VN');}},grid:{color:'rgba(60,50,20,.10)'}}}}});
		    }
		    if(!window.Chart && !document.getElementById('pql-chartjs')){
		      var s=document.createElement('script'); s.id='pql-chartjs'; s.src='https://cdn.jsdelivr.net/npm/chart.js@4'; document.head.appendChild(s);
		    }
		    draw();
		  }
		  var bcKy='tatca', bcVe='', bcVeLoaded=false;
		  Array.prototype.forEach.call(root.querySelectorAll('.pql-seg[data-seg="ky"] button'),function(b){
		    b.addEventListener('click',function(){
		      Array.prototype.forEach.call(root.querySelectorAll('.pql-seg[data-seg="ky"] button'),function(x){x.classList.remove('on');}); b.classList.add('on');
		      bcKy=b.getAttribute('data-v'); napBaoCao();
		    });
		  });
		  $('.pql-bcve').addEventListener('change',function(){ bcVe=this.value; napBaoCao(); });
		  function napBaoCao(){
		    var u='/ql/baocao?ky='+encodeURIComponent(bcKy)+(bcVe?'&ve='+encodeURIComponent(bcVe):'');
		    get(u).then(function(d){
		      if(!bcVeLoaded){ // nạp dropdown loại vé 1 lần
		        var sel=$('.pql-bcve'); (d.ds_ve||[]).forEach(function(t){ var o=document.createElement('option'); o.value=t; o.textContent=t; sel.appendChild(o); }); bcVeLoaded=true;
		      }
		      var money={dt_tong:1,dt_hnay:1,dt_thang:1,dt_zalo:1,dt_web:1};
		      ['dt_tong','ve_ban','so_ve','dt_hnay','dt_thang','ve_hnay','ve_cho','ve_zalo','ve_web','dt_zalo','dt_web'].forEach(function(k){
		        var el=root.querySelector('[data-k="'+k+'"]'); if(el) el.textContent = money[k]?VND(d[k]):(d[k]||0);
		      });
		      veChart(d.chart_lb||[], d.chart_dl||[]);
		      root.querySelector('.pql-topban').innerHTML=(d.top||[]).slice(0,15).map(function(r){
		        var img=r.anh?'<img src="'+esc(r.anh)+'">':'<span class="noimg">🎟️</span>';
		        return '<div class="pql-toprow">'+img+'<div class="pql-topmid"><div class="pql-topten">'+esc(r.ten)+'</div>'
		          +'<div class="pql-topsub">Bán '+r.sl+' đơn</div></div><div class="pql-topdt">'+VND(r.dt)+'</div></div>';
		      }).join('')||'<p style="color:#6f6a5d">Chưa có dữ liệu bán.</p>';
		    }).catch(function(e){ alert(e.message||e); });
		  }

		  // Đơn & soát
		  function napDon(){
		    var loc=$('.pql-loc').value, tim=$('.pql-tim').value.trim();
		    $('.pql-donlist').innerHTML='<p style="color:#6f6a5d">Đang tải…</p>';
		    var u=new URL(REST+'/ql/donhang'); u.searchParams.set('pin',PIN); if(loc)u.searchParams.set('loc',loc); if(tim)u.searchParams.set('tim',tim);
		    fetch(u.toString()).then(function(r){return r.json();}).then(function(d){
		      if(!d||d.ok===false) throw new Error(d&&(d.message||d.code)||'Lỗi');
		      $('.pql-donlist').innerHTML=(d.don||[]).map(rowDon).join('')||'<p style="color:#6f6a5d">Không có đơn.</p>';
		    }).catch(function(e){ $('.pql-donlist').innerHTML='<p class="pql-err">'+esc(e.message||e)+'</p>'; });
		  }
		  function rowDon(r){
		    var src=r.nguon==='zalo'?'📱 Zalo':'🌐 Web';
		    var act='<div class="pql-don-act">';
		    if(r.trang_thai==='cho') act+='<button class="go" data-ma="'+r.ma_ve+'" data-tt="da_tt">Xác nhận đã TT</button>';
		    if(r.trang_thai==='da_tt') act+='<button class="use" data-ma="'+r.ma_ve+'" data-tt="da_dung">Đã dùng (soát)</button>';
		    if(r.trang_thai!=='huy'&&r.trang_thai!=='da_dung') act+='<button class="no" data-ma="'+r.ma_ve+'" data-tt="huy">Huỷ</button>';
		    act+='</div>';
		    return '<div class="pql-don"><div class="pql-don-top"><span class="pql-don-ma">'+esc(r.ma_ve)+'</span>'
		      +'<span class="pql-bdg '+r.trang_thai+'">'+(NHAN[r.trang_thai]||r.trang_thai)+'</span></div>'
		      +'<div class="pql-don-sub">'+esc(r.dv_ten)+' · '+VND(r.so_tien)+'</div>'
		      +'<div class="pql-don-sub">'+esc(r.ten_khach||'')+' · '+esc(r.sdt||'')+' · '+src+'</div>'+act+'</div>';
		  }
		  $('.pql-loc-btn').addEventListener('click',napDon);
		  $('.pql-loc').addEventListener('change',napDon);
		  $('.pql-tim').addEventListener('keydown',function(e){if(e.key==='Enter')napDon();});
		  $('.pql-donlist').addEventListener('click',function(e){
		    var b=e.target.closest('.pql-don-act button'); if(!b)return;
		    var ma=b.getAttribute('data-ma'), tt=b.getAttribute('data-tt');
		    if(tt==='huy'&&!confirm('Huỷ vé '+ma+'?'))return;
		    b.disabled=true; b.textContent='...';
		    post('/ql/capnhat',{ma_ve:ma,trang_thai:tt}).then(napDon).catch(function(err){alert(err.message||err);b.disabled=false;});
		  });

		  // ── Ưu đãi ──
		  var uuHangLoaded=false;
		  /* ═══ SOI HỆ THỐNG ═══════════════════════════════════════════════════════════════ */
		  function napSoi(){
		    $('.pql-soilist').innerHTML='<p style="color:#6f6a5d">Đang kiểm…</p>';
		    get('/ql/soi').then(function(d){
		      var h = '<div class="pql-soi-tong '+(d.hong?'xau':'tot')+'">'
		        + (d.hong ? ('⚠️ '+d.hong+' chỗ đang hỏng — khách chưa mua được vé.')
		                  : '✅ Mọi thứ sẵn sàng — khách mua vé được.')
		        + ' <span style="opacity:.6">Bản vé '+esc(d.ban||'')+'</span></div>';
		      h += (d.muc||[]).map(function(m){
		        return '<div class="pql-soi-m">'
		          + '<span class="pql-soi-i">'+(m.ok?'✅':'❌')+'</span>'
		          + '<div><div class="pql-soi-t">'+esc(m.ten)+'</div>'
		          + '<div class="pql-soi-n">'+esc(m.noi)+'</div>'
		          + (m.chua ? '<div class="pql-soi-c">→ '+esc(m.chua)+'</div>' : '')
		          + '</div></div>';
		      }).join('');
		      $('.pql-soilist').innerHTML = h;
		    }).catch(function(e){ $('.pql-soilist').innerHTML='<p class="pql-err">'+esc(e.message||e)+'</p>'; });
		  }
		  $('.pql-soi-lai').addEventListener('click', napSoi);

		  /* ═══ TÀI KHOẢN NHẬN TIỀN ═══════════════════════════════════════════════════════ */
		  var TK_NH_DA = false;
		  function napTk(){
		    $('.pql-tkmsg').textContent='';
		    get('/ql/tk-ds').then(function(d){
		      if(!TK_NH_DA){
		        var sel=$('.t-bin'), nh=d.nh||{};
		        Object.keys(nh).forEach(function(bin){ var o=document.createElement('option'); o.value=bin; o.textContent=nh[bin]+' — '+bin; sel.appendChild(o); });
		        TK_NH_DA=true;
		      }
		      $('.t-bin').value=d.bin||''; $('.t-tk').value=d.so_tk||''; $('.t-ten').value=d.ten_tk||'';
		      /* Chưa khai riêng = đang MƯỢN tài khoản của plugin Ghế. Nói ra, không thì người ta
		         thấy ô có sẵn số rồi tưởng đã cấu hình xong cho vé. */
		      var m=$('.pql-tkmuon');
		      if(!d.rieng && d.so_tk){
		        m.style.display=''; m.className='pql-err';
		        m.innerHTML='⚠️ Vé đang <b>mượn tài khoản của plugin Ghế Massage</b>. Bấm <b>Lưu tài khoản</b> để chốt riêng cho vé — về sau bên Ghế đổi số thì vé không đổi theo.';
		      } else if(!d.so_tk){
		        m.style.display=''; m.className='pql-err';
		        m.innerHTML='⚠️ <b>Chưa có tài khoản nhận tiền</b> — khách đặt vé sẽ báo lỗi, không tạo được mã QR.';
		      } else { m.style.display='none'; }
		    }).catch(function(e){ $('.pql-tkmsg').style.color='#f0a0a0'; $('.pql-tkmsg').textContent=String(e.message||e); });
		  }
		  $('.pql-tk-luu').addEventListener('click',function(){
		    var bin=$('.t-bin').value, tk=$('.t-tk').value.trim(), ten=$('.t-ten').value.trim();
		    if(!bin||!tk||!ten){ $('.pql-tkmsg').style.color='#f0a0a0'; $('.pql-tkmsg').textContent='Điền đủ ngân hàng, số tài khoản và chủ tài khoản.'; return; }
		    $('.pql-tkmsg').style.color='#6f6a5d'; $('.pql-tkmsg').textContent='Đang lưu…';
		    post('/ql/tk-luu',{bin:bin,so_tk:tk,ten_tk:ten}).then(function(d){
		      $('.t-ten').value=d.ten_tk||ten; $('.pql-tkmuon').style.display='none';
		      $('.pql-tkmsg').style.color='#86efac';
		      $('.pql-tkmsg').textContent='Đã lưu: '+(d.ten_nh||'')+' · '+d.so_tk+' · '+d.ten_tk+'. Nhớ chuyển thử 1.000đ trước khi mở bán.';
		    }).catch(function(e){ $('.pql-tkmsg').style.color='#f0a0a0'; $('.pql-tkmsg').textContent=String(e.message||e); });
		  });

		  /* ═══ PHÂN LOẠI VÉ ═══════════════════════════════════════════════════════════════
		     Danh tính của danh mục là CÁI TÊN (vé lưu tên nhóm dưới dạng chữ), nên màn sửa phải
		     gửi kèm tên cũ để máy chủ đổi luôn mọi vé đang mang tên đó. Gửi thiếu tên cũ thì
		     thành "tạo thêm một danh mục mới", còn đám vé cũ ở lại danh mục cũ — chia đôi im lặng. */
		  var DM = [];
		  function napDm(){
		    $('.pql-dmlist').innerHTML='<p style="color:#6f6a5d">Đang tải…</p>';
		    get('/ql/dm-ds').then(function(d){ dmVe(d.ds||[]); })
		      .catch(function(e){ $('.pql-dmlist').innerHTML='<p class="pql-err">'+esc(e.message||e)+'</p>'; });
		  }
		  function dmVe(ds){
		    DM = ds;
		    /* Ô "Phân loại" ở form vé gợi ý từ chính danh sách này — gõ tay tên mới vẫn được,
		       nhưng đỡ đẻ ra "Combo" và "combo " là hai danh mục khác nhau. */
		    var dl=document.getElementById('pql-dm');
		    if(dl) dl.innerHTML = ds.map(function(d){ return '<option value="'+esc(d.ten)+'">'; }).join('');
		    $('.pql-dmlist').innerHTML = ds.length ? ds.map(dmRow).join('')
		      : '<p style="color:#6f6a5d">Chưa có phân loại nào. Bấm “+ Tạo phân loại”.</p>';
		  }
		  function dmRow(d){
		    var img = d.anh ? '<img src="'+esc(d.anh)+'">' : '<span class="noimg">🗂️</span>';
		    var sub = d.so_ve + ' vé' + (d.khai ? '' : ' · <i>chưa khai, đang lấy từ vé</i>')
		            + (d.anh ? '' : ' · chưa có ảnh riêng');
		    return '<div class="pql-row">'+img+'<div class="pql-row-mid"><div class="pql-row-ten">'+esc(d.ten)+'</div>'
		      +'<div class="pql-row-sub">'+sub+'</div></div>'
		      +'<div class="pql-row-btn"><button data-dsua=\''+esc(JSON.stringify(d))+'\'>Sửa</button>'
		      +'<button class="del" data-dxoa="'+esc(d.ten)+'" data-sove="'+d.so_ve+'">Xoá</button></div></div>';
		  }
		  function dmForm(d){
		    d=d||{}; $('.pql-dmform-h').textContent = d.ten ? 'Sửa phân loại' : 'Tạo phân loại';
		    $('.d-cu').value = d.ten||''; $('.d-ten').value = d.ten||''; $('.d-anh').value = d.anh||'';
		    var xem=$('.d-xem'); if(d.anh){xem.src=d.anh;xem.hidden=false;}else{xem.hidden=true;}
		    $('.pql-dmmsg').textContent='';
		    $('.pql-dmform').hidden=false; $('.d-ten').focus(); $('.pql-dmform').scrollIntoView({behavior:'smooth',block:'center'});
		  }
		  $('.pql-dm-them').addEventListener('click',function(){ dmForm(null); });
		  $('.pql-dm-huy').addEventListener('click',function(){ $('.pql-dmform').hidden=true; });
		  $('.d-file').addEventListener('change',function(){
		    var f=this.files&&this.files[0]; if(!f)return; var fd=new FormData(); fd.append('file',f); fd.append('pin',PIN);
		    $('.pql-dmmsg').style.color='#6f6a5d'; $('.pql-dmmsg').textContent='Đang tải ảnh…';
		    fetch(REST+'/ql/ve-anh',{method:'POST',credentials:'same-origin',headers:hdr(),body:fd}).then(function(r){return r.json();}).then(function(d){
		      if(!d||d.ok===false||!d.url) throw new Error(d&&(d.message||d.code)||'Lỗi tải ảnh');
		      $('.d-anh').value=d.url; var xem=$('.d-xem'); xem.src=d.url; xem.hidden=false; $('.pql-dmmsg').textContent='Đã tải ảnh ✓';
		    }).catch(function(e){ $('.pql-dmmsg').style.color='#f0a0a0'; $('.pql-dmmsg').textContent=String(e.message||e); });
		  });
		  $('.d-anh').addEventListener('change',function(){ var xem=$('.d-xem'); if(this.value){xem.src=this.value;xem.hidden=false;}else{xem.hidden=true;} });
		  $('.pql-dm-luu').addEventListener('click',function(){
		    var ten=$('.d-ten').value.trim();
		    if(!ten){ $('.pql-dmmsg').style.color='#f0a0a0'; $('.pql-dmmsg').textContent='Cần tên phân loại.'; return; }
		    var cu=$('.d-cu').value;
		    if(cu && cu!==ten && !confirm('Đổi tên "'+cu+'" thành "'+ten+'"?\n\nMọi vé đang thuộc phân loại này sẽ đổi theo.')) return;
		    $('.pql-dmmsg').style.color='#6f6a5d'; $('.pql-dmmsg').textContent='Đang lưu…';
		    post('/ql/dm-luu',{ ten:ten, ten_cu:cu, anh:$('.d-anh').value.trim() }).then(function(d){
		      $('.pql-dmform').hidden=true; dmVe(d.ds||[]); napDs();
		    }).catch(function(e){ $('.pql-dmmsg').style.color='#f0a0a0'; $('.pql-dmmsg').textContent=String(e.message||e); });
		  });
		  root.addEventListener('click',function(e){
		    var s=e.target.closest('[data-dsua]'); if(s){ try{ dmForm(JSON.parse(s.getAttribute('data-dsua'))); }catch(err){} return; }
		    var x=e.target.closest('[data-dxoa]'); if(!x) return;
		    var ten=x.getAttribute('data-dxoa'), n=+x.getAttribute('data-sove')||0;
		    /* Có vé thì BẮT chọn chỗ chuyển sang, không cho xoá trắng: vé mất danh mục là vé nằm
		       trong kho mà không dải nào hiện ra — bán không ai thấy. */
		    var sang='';
		    if(n>0){
		      var ds=DM.filter(function(d){ return d.ten!==ten; }).map(function(d){ return d.ten; });
		      sang = prompt('Phân loại "'+ten+'" đang có '+n+' vé.\nChuyển số vé đó sang phân loại nào?\n\nĐang có: '+(ds.join(', ')||'(chưa có cái nào)'), ds[0]||'Vé');
		      if(sang===null) return;
		      sang = String(sang).trim(); if(!sang) sang='Vé';
		    } else if(!confirm('Xoá phân loại "'+ten+'"?')) { return; }
		    post('/ql/dm-xoa',{ ten:ten, sang:sang }).then(function(d){ dmVe(d.ds||[]); napDs(); })
		      .catch(function(err){ alert(String(err.message||err)); });
		  });

		  function napUu(){
		    $('.pql-uulist').innerHTML='<p style="color:#6f6a5d">Đang tải…</p>';
		    get('/ql/uu-ds').then(function(d){
		      if(!uuHangLoaded){ var sel=$('.u-hang'); (d.ds_hang||[]).forEach(function(h){ var o=document.createElement('option'); o.value=h; o.textContent='Từ hạng '+h; sel.appendChild(o); }); uuHangLoaded=true; }
		      var ds=d.ds||[];
		      $('.pql-uulist').innerHTML = ds.length ? ds.map(uuRow).join('') : '<p style="color:#6f6a5d">Chưa có ưu đãi. Bấm “+ Tạo ưu đãi”.</p>';
		    }).catch(function(e){ $('.pql-uulist').innerHTML='<p class="pql-err">'+esc(e.message||e)+'</p>'; });
		  }
		  function uuRow(v){
		    var img=v.anh?'<img src="'+esc(v.anh)+'">':'<span class="noimg">🎁</span>';
		    var tag=v.hien?'<span class="pql-tag on">Đang hiện</span>':'<span class="pql-tag off">Ẩn</span>';
		    return '<div class="pql-row">'+img+'<div class="pql-row-mid"><div class="pql-row-ten">'+esc(v.ten)+tag+'</div>'
		      +'<div class="pql-row-sub">'+(v.hang?'Từ hạng '+esc(v.hang)+' · ':'')+(v.han?'HSD '+esc(v.han):'')+'</div></div>'
		      +'<div class="pql-row-btn"><button data-usua=\''+esc(JSON.stringify(v))+'\'>Sửa</button><button class="del" data-uxoa="'+v.id+'" data-ten="'+esc(v.ten)+'">Xoá</button></div></div>';
		  }
		  function uuForm(v){
		    v=v||{}; $('.pql-uuform-h').textContent=v.id?'Sửa ưu đãi':'Tạo ưu đãi';
		    $('.u-id').value=v.id||0; $('.u-ten').value=v.ten||''; $('.u-mota').value=v.mo_ta||''; $('.u-hang').value=v.hang||''; $('.u-han').value=v.han||''; $('.u-anh').value=v.anh||''; $('.u-hien').checked=v.id?!!v.hien:true;
		    var xem=$('.u-xem'); if(v.anh){xem.src=v.anh;xem.hidden=false;}else{xem.hidden=true;} $('.pql-uumsg').textContent='';
		    $('.pql-uuform').hidden=false; $('.u-ten').focus(); $('.pql-uuform').scrollIntoView({behavior:'smooth',block:'center'});
		  }
		  $('.pql-uu-them').addEventListener('click',function(){ uuForm(null); });
		  $('.pql-uu-huy').addEventListener('click',function(){ $('.pql-uuform').hidden=true; });
		  root.addEventListener('click',function(e){
		    var s=e.target.closest('[data-usua]'); if(s){ try{ uuForm(JSON.parse(s.getAttribute('data-usua'))); }catch(err){} return; }
		    var x=e.target.closest('[data-uxoa]'); if(x){ if(!confirm('Xoá ưu đãi "'+x.getAttribute('data-ten')+'"?'))return;
		      x.disabled=true; post('/ql/uu-xoa',{id:+x.getAttribute('data-uxoa')}).then(napUu).catch(function(err){alert(err.message||err);x.disabled=false;}); }
		  });
		  $('.u-file').addEventListener('change',function(){
		    var f=this.files&&this.files[0]; if(!f)return; var fd=new FormData(); fd.append('file',f); fd.append('pin',PIN);
		    $('.pql-uumsg').style.color='#6f6a5d'; $('.pql-uumsg').textContent='Đang tải ảnh…';
		    fetch(REST+'/ql/ve-anh',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
		      if(!d||d.ok===false||!d.url) throw new Error(d&&(d.message||d.code)||'Lỗi tải ảnh');
		      $('.u-anh').value=d.url; var xem=$('.u-xem'); xem.src=d.url; xem.hidden=false; $('.pql-uumsg').textContent='Đã tải ảnh ✓';
		    }).catch(function(e){ $('.pql-uumsg').style.color='#f0a0a0'; $('.pql-uumsg').textContent=String(e.message||e); });
		  });
		  $('.u-anh').addEventListener('change',function(){ var xem=$('.u-xem'); if(this.value){xem.src=this.value;xem.hidden=false;}else{xem.hidden=true;} });
		  $('.pql-uu-luu').addEventListener('click',function(){
		    var body={ id:+$('.u-id').value, ten:$('.u-ten').value.trim(), mo_ta:$('.u-mota').value.trim(), hang:$('.u-hang').value, han:$('.u-han').value.trim(), anh:$('.u-anh').value.trim(), hien:$('.u-hien').checked?1:'' };
		    if(!body.ten){ $('.pql-uumsg').style.color='#f0a0a0'; $('.pql-uumsg').textContent='Cần tên ưu đãi.'; return; }
		    var b=$('.pql-uu-luu'); b.disabled=true; b.textContent='Đang lưu…';
		    post('/ql/uu-luu',body).then(function(){ $('.pql-uuform').hidden=true; napUu(); })
		      .catch(function(e){ $('.pql-uumsg').style.color='#f0a0a0'; $('.pql-uumsg').textContent=String(e.message||e); })
		      .then(function(){ b.disabled=false; b.textContent='Lưu ưu đãi'; });
		  });

		  // ── Khách hàng ──
		  function napKhach(){
		    var tim=$('.pql-khtim').value.trim(), sx=$('.pql-khsx').value;
		    $('.pql-khlist').innerHTML='<tr><td colspan="6" style="color:#6f6a5d">Đang tải…</td></tr>';
		    var u='/ql/khach?sx='+encodeURIComponent(sx)+(tim?'&tim='+encodeURIComponent(tim):'');
		    get(u).then(function(d){
		      $('.pql-khtong').innerHTML='Tổng <b>'+(d.tong_kh||0)+'</b> khách · Tổng chi <b>'+VND(d.tong_chi)+'</b>';
		      $('.pql-khlist').innerHTML=(d.ds||[]).map(function(k){
		        return '<tr><td><b>'+esc(k.ten||'—')+'</b></td><td>'+esc(k.sdt)+'</td><td>'+(k.diem||0)+'</td>'
		          +'<td><span class="pql-tag on">'+esc(k.hang)+'</span></td><td>'+(k.so_don||0)+'</td><td>'+VND(k.tong_chi)+'</td></tr>';
		      }).join('')||'<tr><td colspan="6" style="color:#6f6a5d">Chưa có khách nào.</td></tr>';
		    }).catch(function(e){ $('.pql-khlist').innerHTML='<tr><td colspan="6" class="pql-err">'+esc(e.message||e)+'</td></tr>'; });
		  }
		  $('.pql-khloc').addEventListener('click',napKhach);
		  $('.pql-khsx').addEventListener('change',napKhach);
		  $('.pql-khtim').addEventListener('keydown',function(e){if(e.key==='Enter')napKhach();});

		  // ── Hạng thành viên ──
		  function napHang(){
		    $('.pql-hanglist').innerHTML='<p style="color:#6f6a5d">Đang tải…</p>'; $('.pql-hangmsg').textContent='';
		    get('/ql/hang-ds').then(function(d){ hangVe(d.ds||[]); }).catch(function(e){ $('.pql-hanglist').innerHTML='<p class="pql-err">'+esc(e.message||e)+'</p>'; });
		  }
		  function hangRow(ten,moc){
		    return '<div class="pql-hrow"><input class="h-ten" placeholder="Tên hạng" value="'+esc(ten||'')+'">'
		      +'<input class="h-moc" type="number" inputmode="numeric" placeholder="Điểm mốc" value="'+(moc||0)+'">'
		      +'<button class="h-del" title="Xoá hạng">✕</button></div>';
		  }
		  function hangVe(ds){
		    $('.pql-hanglist').innerHTML = (ds.length?ds:[{ten:'',moc:0}]).map(function(h){return hangRow(h.ten,h.moc);}).join('');
		  }
		  $('.pql-hang-them').addEventListener('click',function(){ $('.pql-hanglist').insertAdjacentHTML('beforeend', hangRow('',0)); });
		  $('.pql-hanglist').addEventListener('click',function(e){ var b=e.target.closest('.h-del'); if(b){ b.parentNode.remove(); } });
		  $('.pql-hang-luu').addEventListener('click',function(){
		    var ds=[]; Array.prototype.forEach.call(root.querySelectorAll('.pql-hrow'),function(r){
		      var ten=r.querySelector('.h-ten').value.trim(); if(ten) ds.push({ten:ten, moc:+String(r.querySelector('.h-moc').value).replace(/\D+/g,'')||0});
		    });
		    if(!ds.length){ $('.pql-hangmsg').style.color='#f0a0a0'; $('.pql-hangmsg').textContent='Cần ít nhất 1 hạng.'; return; }
		    var b=$('.pql-hang-luu'); b.disabled=true; b.textContent='Đang lưu…';
		    post('/ql/hang-luu',{ds:ds}).then(function(d){ hangVe(d.ds||[]); $('.pql-hangmsg').style.color='#7ee2a8'; $('.pql-hangmsg').textContent='Đã lưu ✓'; })
		      .catch(function(e){ $('.pql-hangmsg').style.color='#f0a0a0'; $('.pql-hangmsg').textContent=String(e.message||e); })
		      .then(function(){ b.disabled=false; b.textContent='Lưu hạng'; });
		  });

		  // Danh sách vé
		  /* Danh sách vé XẾP THEO PHÂN LOẠI — anh Thắng 11/09/2026: "vé cũng tách ra theo phân loại".
		     Một danh sách phẳng thì mở ra là phải đọc hết mới biết Combo có mấy vé; xếp theo phân
		     loại thì thấy ngay đúng cái khách cũng thấy trên dải chọn.
		     ⚠️ Thứ tự nhóm lấy từ /ql/dm-ds — CÙNG một thứ tự với dải bên trang khách. Xếp kiểu
		        khác ở đây là marketing sửa theo màn này rồi ra trang khách thấy một trật tự lạ. */
		  function napDs(){
		    $('.pql-list').innerHTML='<p style="color:#6f6a5d">Đang tải…</p>';
		    Promise.all([ get('/ql/ve-ds'), get('/ql/dm-ds').catch(function(){ return { ds: [] }; }) ])
		      .then(function(r){
		        var ds = r[0].ds || [], dm = (r[1] && r[1].ds) || [];
		        if (!ds.length){ $('.pql-list').innerHTML='<p style="color:#6f6a5d">Chưa có vé nào. Bấm “+ Tạo vé mới”.</p>'; return; }
		        var gom = {}, thutu = [];
		        dm.forEach(function(d){ gom[d.ten] = { anh:d.anh, ve:[] }; thutu.push(d.ten); });
		        ds.forEach(function(v){
		          var k = (v.nhom||'').trim() || 'Vé';
		          if (!gom[k]) { gom[k] = { anh:'', ve:[] }; thutu.push(k); }   // vé mang tên nhóm chưa khai
		          gom[k].ve.push(v);
		        });
		        var h = '';
		        thutu.forEach(function(k){
		          var g = gom[k]; if (!g.ve.length) return;   // phân loại rỗng: quản ở màn Phân loại, không bày ở đây
		          var anh = g.anh ? '<img src="'+esc(g.anh)+'" alt="">' : '<span class="noimg">🗂️</span>';
		          h += '<div class="pql-gh">'+anh+'<b>'+esc(k)+'</b><span>'+g.ve.length+' vé</span></div>'
		             + g.ve.map(row).join('');
		        });
		        $('.pql-list').innerHTML = h;
		      }).catch(function(e){ $('.pql-list').innerHTML='<p class="pql-err">'+esc(e.message||e)+'</p>'; });
		  }
		  function row(v){
		    var img = v.anh ? '<img src="'+esc(v.anh)+'" alt="">' : '<span class="noimg">🎟️</span>';
		    var goc = (v.gia_goc>v.gia) ? ' <s style="color:#8b8576">'+VND(v.gia_goc)+'</s>' : '';
		    var sl  = (v.so_luong<0) ? '∞' : (v.so_luong>0 ? v.so_luong+' vé' : 'Hết');
		    var tag = v.hien ? '<span class="pql-tag on">Đang bán</span>' : '<span class="pql-tag off">Ẩn</span>';
		    return '<div class="pql-row">'+img+'<div class="pql-row-mid"><div class="pql-row-ten">'+esc(v.ten)+tag+'</div>'
		      /* Không in lại tên phân loại ở đây: tiêu đề nhóm ngay phía trên đã nói rồi, nhắc
		         lại từng dòng chỉ làm loãng chỗ đáng đọc (giá, khu, số vé còn). */
		      +'<div class="pql-row-sub"><span class="pql-row-gia">'+VND(v.gia)+'</span>'+goc+(v.khu_vuc?' · 📍'+esc(v.khu_vuc):'')+' · Còn '+sl+'</div></div>'
		      +'<div class="pql-row-btn"><button data-sua=\''+esc(JSON.stringify(v))+'\'>Sửa</button><button class="del" data-xoa="'+v.id+'" data-ten="'+esc(v.ten)+'">Xoá</button></div></div>';
		  }

		  // Mở form
		  function moForm(v){
		    v=v||{};
		    $('.pql-form-h').textContent = v.id ? 'Sửa vé' : 'Tạo vé mới';
		    $('.f-id').value=v.id||0; $('.f-ten').value=v.ten||''; $('.f-gia').value=v.gia||''; $('.f-goc').value=v.gia_goc||'';
		    $('.f-nhom').value=v.nhom||''; $('.f-kv').value=v.khu_vuc||''; $('.f-sl').value=(v.so_luong>=0?v.so_luong:''); $('.f-tl').value=v.thoi_luong||'';
		    $('.f-mota').value=v.mo_ta||''; $('.f-anh').value=v.anh||''; $('.f-hien').checked = v.id? !!v.hien : true;
		    var xem=$('.f-xem'); if(v.anh){xem.src=v.anh; xem.hidden=false;} else {xem.hidden=true;}
		    $('.pql-msg2').textContent='';
		    $('.pql-form').hidden=false; $('.f-ten').focus();
		    $('.pql-form').scrollIntoView({behavior:'smooth',block:'center'});
		  }
		  $('.pql-them').addEventListener('click',function(){ moForm(null); });
		  $('.pql-huy').addEventListener('click',function(){ $('.pql-form').hidden=true; });

		  // Sửa / Xoá (uỷ quyền sự kiện)
		  root.addEventListener('click',function(e){
		    var s=e.target.closest('[data-sua]'); if(s){ try{ moForm(JSON.parse(s.getAttribute('data-sua'))); }catch(err){} return; }
		    var x=e.target.closest('[data-xoa]'); if(x){ if(!confirm('Xoá vé "'+x.getAttribute('data-ten')+'"?'))return;
		      x.disabled=true; post('/ql/ve-xoa',{id:+x.getAttribute('data-xoa')}).then(napDs).catch(function(err){alert(err.message||err);x.disabled=false;}); }
		  });

		  // Upload ảnh
		  $('.f-file').addEventListener('change',function(){
		    var f=this.files&&this.files[0]; if(!f)return;
		    var fd=new FormData(); fd.append('file',f); fd.append('pin',PIN);
		    $('.pql-msg2').style.color='#6f6a5d'; $('.pql-msg2').textContent='Đang tải ảnh…';
		    fetch(REST+'/ql/ve-anh',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
		      if(!d||d.ok===false||!d.url) throw new Error(d&&(d.message||d.code)||'Lỗi tải ảnh');
		      $('.f-anh').value=d.url; var xem=$('.f-xem'); xem.src=d.url; xem.hidden=false; $('.pql-msg2').textContent='Đã tải ảnh ✓';
		    }).catch(function(e){ $('.pql-msg2').style.color='#f0a0a0'; $('.pql-msg2').textContent=String(e.message||e); });
		  });
		  $('.f-anh').addEventListener('change',function(){ var xem=$('.f-xem'); if(this.value){xem.src=this.value;xem.hidden=false;}else{xem.hidden=true;} });

		  // Lưu vé
		  $('.pql-luu').addEventListener('click',function(){
		    var body={ id:+$('.f-id').value, ten:$('.f-ten').value.trim(), gia:$('.f-gia').value, gia_goc:$('.f-goc').value,
		      nhom:$('.f-nhom').value.trim(), khu_vuc:$('.f-kv').value.trim(), so_luong:$('.f-sl').value.trim(),
		      thoi_luong:$('.f-tl').value.trim(), mo_ta:$('.f-mota').value.trim(), anh:$('.f-anh').value.trim(), hien:$('.f-hien').checked?1:'' };
		    if(!body.ten || !(+String(body.gia).replace(/\D+/g,'')>=1000)){ $('.pql-msg2').style.color='#f0a0a0'; $('.pql-msg2').textContent='Cần tên vé và giá ≥ 1.000đ.'; return; }
		    var b=$('.pql-luu'); b.disabled=true; b.textContent='Đang lưu…';
		    post('/ql/ve-luu',body).then(function(){ $('.pql-form').hidden=true; napDs(); })
		      .catch(function(e){ $('.pql-msg2').style.color='#f0a0a0'; $('.pql-msg2').textContent=String(e.message||e); })
		      .then(function(){ b.disabled=false; b.textContent='Lưu vé'; });
		  });
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	// ───────────────────────────── Admin (menu top-level riêng) ─────────────────────────────
	public static function admin_menu() {
		add_menu_page( 'Vé khu vui chơi', 'Vé khu vui chơi', 'manage_options', 'posh-ve',
			array( __CLASS__, 'trang_admin' ), 'dashicons-tickets-alt', 27 );
	}
	public static function trang_admin() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb; wp_enqueue_media();

		if ( isset( $_POST['pve_bank'] ) && check_admin_referer( 'pve_bank' ) ) {
			update_option( 'pve_bin', preg_replace( '/\D+/', '', (string) $_POST['bin'] ) );
			update_option( 'pve_so_tk', sanitize_text_field( wp_unslash( $_POST['so_tk'] ) ) );
			update_option( 'pve_ten_tk', sanitize_text_field( wp_unslash( $_POST['ten_tk'] ) ) );
			echo '<div class="notice notice-success"><p>Đã lưu tài khoản.</p></div>';
		}
		if ( isset( $_POST['pve_vi'] ) && check_admin_referer( 'pve_vi' ) ) {
			/* Gói nạp: mỗi dòng "mệnh giá | tặng thêm". Bỏ dòng trống, bỏ mệnh giá dưới 1.000đ. */
			$goi = array();
			$g_nap = isset( $_POST['goi_nap'] ) ? (array) $_POST['goi_nap'] : array();
			$g_tang = isset( $_POST['goi_tang'] ) ? (array) $_POST['goi_tang'] : array();
			foreach ( $g_nap as $i => $v ) {
				$nap = (int) preg_replace( '/\D+/', '', (string) $v );
				if ( $nap < 1000 ) { continue; }
				$goi[] = array( 'nap' => $nap, 'tang' => (int) preg_replace( '/\D+/', '', (string) ( isset( $g_tang[ $i ] ) ? $g_tang[ $i ] : 0 ) ) );
			}
			update_option( 'pve_vi_goi', $goi );

			/* Mã ưu đãi. GIỮ LẠI SỐ LƯỢT ĐÃ DÙNG của mã cũ cùng tên: lưu lại cấu hình mà đếm về
			   0 là mã giới hạn 100 lượt bỗng dùng được thêm 100 lượt nữa. */
			$cu = array();
			foreach ( self::ds_ma_uu_dai() as $c ) { $cu[ $c['code'] ] = (int) $c['da_dung']; }
			$code = array();
			$c_ma = isset( $_POST['c_ma'] ) ? (array) $_POST['c_ma'] : array();
			foreach ( $c_ma as $i => $v ) {
				$m = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $v ) );
				if ( '' === $m ) { continue; }
				$lay = function ( $ten, $i ) { $a = isset( $_POST[ $ten ] ) ? (array) $_POST[ $ten ] : array(); return isset( $a[ $i ] ) ? $a[ $i ] : ''; };
				$code[] = array(
					'code'      => $m,
					'kieu'      => ( 'tien' === (string) $lay( 'c_kieu', $i ) ) ? 'tien' : 'pt',
					'gia_tri'   => (int) preg_replace( '/\D+/', '', (string) $lay( 'c_gia_tri', $i ) ),
					'toi_thieu' => (int) preg_replace( '/\D+/', '', (string) $lay( 'c_toi_thieu', $i ) ),
					'han'       => preg_replace( '/[^0-9-]/', '', (string) $lay( 'c_han', $i ) ),
					'gioi_han'  => (int) preg_replace( '/\D+/', '', (string) $lay( 'c_gioi_han', $i ) ),
					'coso'      => sanitize_text_field( wp_unslash( (string) $lay( 'c_coso', $i ) ) ),
					'da_dung'   => isset( $cu[ $m ] ) ? $cu[ $m ] : 0,
				);
			}
			update_option( 'pve_vi_code', $code );
			echo '<div class="notice notice-success"><p>Đã lưu ví tiền: ' . count( $goi ) . ' gói nạp, ' . count( $code ) . ' mã ưu đãi.</p></div>';
		}
		if ( isset( $_POST['pve_cong'] ) && check_admin_referer( 'pve_cong' ) ) {
			update_option( 'pve_momo_partner', sanitize_text_field( wp_unslash( $_POST['momo_partner'] ) ) );
			update_option( 'pve_momo_access', sanitize_text_field( wp_unslash( $_POST['momo_access'] ) ) );
			update_option( 'pve_momo_secret', sanitize_text_field( wp_unslash( $_POST['momo_secret'] ) ) );
			update_option( 'pve_momo_test', empty( $_POST['momo_test'] ) ? 0 : 1 );
			update_option( 'pve_vnp_tmn', sanitize_text_field( wp_unslash( $_POST['vnp_tmn'] ) ) );
			update_option( 'pve_vnp_secret', sanitize_text_field( wp_unslash( $_POST['vnp_secret'] ) ) );
			update_option( 'pve_vnp_test', empty( $_POST['vnp_test'] ) ? 0 : 1 );
			echo '<div class="notice notice-success"><p>Đã lưu cổng thanh toán.</p></div>';
		}
		if ( isset( $_POST['pve_luu'] ) && check_admin_referer( 'pve_luu' ) ) {
			$id = (int) $_POST['id'];
			$moi = array( 'id' => $id, 'ten' => sanitize_text_field( wp_unslash( $_POST['ten'] ) ),
				'gia' => (int) preg_replace( '/\D+/', '', (string) $_POST['gia'] ),
				'gia_goc' => (int) preg_replace( '/\D+/', '', (string) ( isset( $_POST['gia_goc'] ) ? $_POST['gia_goc'] : '' ) ),
				'nhom' => sanitize_text_field( wp_unslash( isset( $_POST['nhom'] ) ? $_POST['nhom'] : '' ) ),
				'khu_vuc' => sanitize_text_field( wp_unslash( isset( $_POST['khu_vuc'] ) ? $_POST['khu_vuc'] : '' ) ),
				'mo_ta' => sanitize_textarea_field( wp_unslash( $_POST['mo_ta'] ) ),
				'anh' => esc_url_raw( wp_unslash( $_POST['anh'] ) ),
				'thoi_luong' => sanitize_text_field( wp_unslash( $_POST['thoi_luong'] ) ),
				'so_luong' => ( ! isset( $_POST['so_luong'] ) || '' === trim( (string) $_POST['so_luong'] ) ) ? -1 : max( 0, (int) preg_replace( '/\D+/', '', (string) $_POST['so_luong'] ) ),
				'hien' => empty( $_POST['hien'] ) ? 0 : 1 );
			$ds = self::ds_tatca(); $thay = false;
			if ( $id > 0 ) { foreach ( $ds as $k => $v ) { if ( (int) $v['id'] === $id ) { $ds[ $k ] = $moi; $thay = true; break; } } }
			if ( ! $thay ) { $moi['id'] = 0; $ds[] = $moi; }
			self::luu_ds( $ds );
			echo '<div class="notice notice-success"><p>Đã lưu dịch vụ.</p></div>';
		}
		if ( isset( $_POST['pve_xoa'] ) && check_admin_referer( 'pve_xoa' ) ) {
			$id = (int) $_POST['id'];
			self::luu_ds( array_filter( self::ds_tatca(), function ( $v ) use ( $id ) { return (int) $v['id'] !== $id; } ) );
			echo '<div class="notice notice-success"><p>Đã xoá dịch vụ.</p></div>';
		}
		if ( isset( $_POST['pve_tt'] ) && check_admin_referer( 'pve_tt' ) ) {
			$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $_POST['ma_ve'] ) );
			$xin = sanitize_key( (string) $_POST['pve_tt'] );
			$hople = array( 'cho', 'da_tt', 'da_dung', 'huy' );
			$tt = in_array( $xin, $hople, true ) ? $xin : 'da_tt';
			$wpdb->update( self::tbl(), array( 'trang_thai' => $tt, 'tt_luc' => current_time( 'mysql' ) ), array( 'ma_ve' => $ma ) );
			$bs = '';
			if ( 'da_tt' === $tt ) {
				$r = $wpdb->get_row( $wpdb->prepare( 'SELECT sdt, ten_khach, so_tien, da_cong_diem FROM ' . self::tbl() . ' WHERE ma_ve=%s', $ma ), ARRAY_A );
				if ( $r && ! (int) $r['da_cong_diem'] ) {
					self::cong_diem( $r['sdt'], $r['ten_khach'], $r['so_tien'] );
					$wpdb->update( self::tbl(), array( 'da_cong_diem' => 1 ), array( 'ma_ve' => $ma ) );
					$bs = ' (+' . number_format( (int) floor( (int) $r['so_tien'] / 1000 ), 0, ',', '.' ) . ' điểm cho ' . esc_html( $r['sdt'] ) . ')';
				}
			}
			$nh = array( 'cho' => 'Chờ', 'da_tt' => 'Đã thanh toán', 'da_dung' => 'Đã dùng (đã soát)', 'huy' => 'Đã huỷ' );
			echo '<div class="notice notice-success"><p>Vé <b>' . esc_html( $ma ) . '</b> → ' . esc_html( $nh[ $tt ] ) . $bs . '.</p></div>';
		}

		if ( isset( $_POST['pve_hang'] ) && check_admin_referer( 'pve_hang' ) ) {
			$tens = isset( $_POST['hang_ten'] ) ? (array) $_POST['hang_ten'] : array();
			$mocs = isset( $_POST['hang_moc'] ) ? (array) $_POST['hang_moc'] : array();
			$moi = array();
			foreach ( $tens as $i => $t ) {
				$t = sanitize_text_field( wp_unslash( $t ) );
				if ( '' === trim( $t ) ) { continue; }
				$moi[] = array( 'ten' => $t, 'moc' => max( 0, (int) preg_replace( '/\D+/', '', (string) ( isset( $mocs[ $i ] ) ? $mocs[ $i ] : 0 ) ) ) );
			}
			update_option( 'pve_hang', $moi );
			echo '<div class="notice notice-success"><p>Đã lưu hạng thành viên.</p></div>';
		}
		if ( isset( $_POST['pve_nhom_anh_luu'] ) && check_admin_referer( 'pve_nhom_anh' ) ) {
			$tens = isset( $_POST['dm_ten'] ) ? (array) $_POST['dm_ten'] : array();
			$anhs = isset( $_POST['dm_anh'] ) ? (array) $_POST['dm_anh'] : array();
			$moi = array();
			foreach ( $tens as $i => $t ) {
				$t = sanitize_text_field( wp_unslash( $t ) );
				$a = esc_url_raw( wp_unslash( isset( $anhs[ $i ] ) ? $anhs[ $i ] : '' ) );
				if ( '' !== trim( $t ) && '' !== $a ) { $moi[ $t ] = $a; }
			}
			update_option( 'pve_nhom_anh', $moi );
			echo '<div class="notice notice-success"><p>Đã lưu ảnh danh mục.</p></div>';
		}
		if ( isset( $_POST['pve_coso_luu'] ) && check_admin_referer( 'pve_coso' ) ) {
			$tens = isset( $_POST['cs_ten'] ) ? (array) $_POST['cs_ten'] : array();
			$lats = isset( $_POST['cs_lat'] ) ? (array) $_POST['cs_lat'] : array();
			$lngs = isset( $_POST['cs_lng'] ) ? (array) $_POST['cs_lng'] : array();
			$mas  = isset( $_POST['cs_ma'] ) ? (array) $_POST['cs_ma'] : array();
			$gis  = isset( $_POST['cs_giam'] ) ? (array) $_POST['cs_giam'] : array();
			$bks  = isset( $_POST['cs_bk'] ) ? (array) $_POST['cs_bk'] : array();
			$moi = array(); $da_dung_ma = array();
			foreach ( $tens as $i => $t ) {
				$t = sanitize_text_field( wp_unslash( $t ) );
				if ( '' === trim( $t ) ) { continue; }
				$ma = self::ma_coso( isset( $mas[ $i ] ) ? wp_unslash( $mas[ $i ] ) : '' );
				if ( '' === $ma ) { $ma = self::ma_coso( $t ); }
				/* Hai cơ sở trùng mã là hai tem QR chỉ về một chỗ — cơ sở kia bán cả ngày mà sổ
				   ghi doanh thu cho cơ sở khác. Thà thêm số vào đuôi còn hơn để trùng im lặng. */
				$goc_ma = $ma; $k = 2;
				while ( '' === $ma || isset( $da_dung_ma[ $ma ] ) ) { $ma = substr( $goc_ma, 0, 10 ) . $k; $k++; }
				$da_dung_ma[ $ma ] = 1;
				$moi[] = array( 'ten' => $t, 'ma' => $ma,
					'lat' => (float) ( isset( $lats[ $i ] ) ? str_replace( ',', '.', (string) $lats[ $i ] ) : 0 ),
					'lng' => (float) ( isset( $lngs[ $i ] ) ? str_replace( ',', '.', (string) $lngs[ $i ] ) : 0 ),
					'giam' => (int) ( isset( $gis[ $i ] ) ? $gis[ $i ] : 0 ),
					'bk' => (int) ( isset( $bks[ $i ] ) ? $bks[ $i ] : 0 ) );
			}
			update_option( 'pve_coso', $moi );
			echo '<div class="notice notice-success"><p>Đã lưu cơ sở &amp; toạ độ.</p></div>';
		}
		if ( isset( $_POST['pve_pin_luu'] ) && check_admin_referer( 'pve_pin' ) ) {
			update_option( 'pve_pin', preg_replace( '/\s+/', '', (string) wp_unslash( $_POST['pin'] ) ) );
			echo '<div class="notice notice-success"><p>Đã lưu PIN khu quản lý.</p></div>';
		}
		if ( isset( $_POST['pve_ft_luu'] ) && check_admin_referer( 'pve_ft' ) ) {
			update_option( 'pve_ft_ten', sanitize_text_field( wp_unslash( $_POST['ft_ten'] ) ) );
			update_option( 'pve_ft_dc', sanitize_text_field( wp_unslash( $_POST['ft_dc'] ) ) );
			update_option( 'pve_ft_lh', sanitize_text_field( wp_unslash( $_POST['ft_lh'] ) ) );
			update_option( 'pve_ft_nb_url', esc_url_raw( wp_unslash( $_POST['ft_nb_url'] ) ) );
			update_option( 'pve_ft_nb_ten', sanitize_text_field( wp_unslash( $_POST['ft_nb_ten'] ) ) );
			echo '<div class="notice notice-success"><p>Đã lưu chân trang.</p></div>';
		}
		if ( isset( $_POST['pve_zalo_luu'] ) && check_admin_referer( 'pve_zalo' ) ) {
			update_option( 'pve_zalo_secret', trim( (string) wp_unslash( $_POST['zalo_secret'] ) ) );
			update_option( 'pve_zalo_appid', preg_replace( '/\D+/', '', (string) wp_unslash( isset( $_POST['zalo_appid'] ) ? $_POST['zalo_appid'] : '' ) ) );
			update_option( 'pve_zalo_verify', sanitize_text_field( wp_unslash( isset( $_POST['zalo_verify'] ) ? $_POST['zalo_verify'] : '' ) ) );
			update_option( 'pve_zalo_admin_ids', sanitize_text_field( wp_unslash( isset( $_POST['zalo_admin_ids'] ) ? $_POST['zalo_admin_ids'] : '' ) ) );
			echo '<div class="notice notice-success"><p>Đã lưu cấu hình Zalo.</p></div>';
		}
		if ( isset( $_POST['pve_uu_luu'] ) && check_admin_referer( 'pve_uu' ) ) {
			$id = (int) $_POST['id'];
			$moi = array( 'id' => $id, 'ten' => sanitize_text_field( wp_unslash( $_POST['ten'] ) ),
				'mo_ta' => sanitize_textarea_field( wp_unslash( $_POST['mo_ta'] ) ),
				'anh' => esc_url_raw( wp_unslash( $_POST['anh'] ) ),
				'hang' => sanitize_text_field( wp_unslash( isset( $_POST['hang'] ) ? $_POST['hang'] : '' ) ),
				'han' => sanitize_text_field( wp_unslash( isset( $_POST['han'] ) ? $_POST['han'] : '' ) ),
				'hien' => empty( $_POST['hien'] ) ? 0 : 1 );
			$ds = self::ds_uudai_tatca(); $thay = false;
			if ( $id > 0 ) { foreach ( $ds as $k => $v ) { if ( (int) $v['id'] === $id ) { $ds[ $k ] = $moi; $thay = true; break; } } }
			if ( ! $thay ) { $moi['id'] = 0; $ds[] = $moi; }
			self::luu_uudai( $ds );
			echo '<div class="notice notice-success"><p>Đã lưu ưu đãi.</p></div>';
		}
		if ( isset( $_POST['pve_uu_xoa'] ) && check_admin_referer( 'pve_uu_xoa' ) ) {
			$id = (int) $_POST['id'];
			self::luu_uudai( array_filter( self::ds_uudai_tatca(), function ( $v ) use ( $id ) { return (int) $v['id'] !== $id; } ) );
			echo '<div class="notice notice-success"><p>Đã xoá ưu đãi.</p></div>';
		}

		$b = self::bank(); $ds = self::ds_tatca();
		$url = esc_url( home_url( '/wp-json/' . self::NS . '/ve/goi' ) );
		$sua = null; $sid = isset( $_GET['sua'] ) ? (int) $_GET['sua'] : 0;
		if ( $sid ) { foreach ( $ds as $v ) { if ( (int) $v['id'] === $sid ) { $sua = $v; break; } } }

		$page_id   = self::bao_dam_trang();
		$page_link = $page_id ? get_permalink( $page_id ) : '';
		$page_edit = $page_id ? get_edit_post_link( $page_id ) : '';

		echo '<div class="wrap"><h1>Vé khu vui chơi</h1>';
		if ( $page_link ) {
			echo '<div class="notice notice-info inline" style="margin:10px 0"><p><b>Trang bán vé trên web đã sẵn:</b> '
				. '<a href="' . esc_url( $page_link ) . '" target="_blank">' . esc_html( $page_link ) . '</a> '
				. '&nbsp; <a class="button button-small" href="' . esc_url( $page_link ) . '" target="_blank">Mở trang</a> '
				. '<a class="button button-small" href="' . esc_url( $page_edit ) . '">Sửa (đổi tên/đường dẫn)</a></p></div>';
		}
		$ql_link = self::url_trang_ql();
		if ( $ql_link ) {
			echo '<div class="notice notice-success inline" style="margin:10px 0"><p><b>🎟️ Trang QUẢN TRỊ VÉ (cho marketing):</b> '
				. '<a href="' . esc_url( $ql_link ) . '" target="_blank">' . esc_html( $ql_link ) . '</a> '
				. '&nbsp; <a class="button button-small" href="' . esc_url( $ql_link ) . '" target="_blank">Mở trang</a></p>'
				. '<p class="description">Gửi link này + <b>mã PIN</b> (mục “Khu quản lý” bên dưới) cho nhân viên marketing. Họ đăng nhập PIN để tạo/sửa vé — không cần tài khoản WordPress. Vé tạo ra tự lên web + Zalo.</p></div>';
		}
		echo '<p>API cho Zalo Mini App: <code>' . $url . '</code> · Muốn thêm trang bán khác: dán shortcode <code>[posh_ve]</code> vào trang bất kỳ.</p>';

		/* ── Tổng quan ── */
		$tbl   = self::tbl();
		$paid  = "trang_thai IN ('da_tt','da_dung')";
		$hnay  = current_time( 'Y-m-d' );
		$thang = current_time( 'Y-m' );
		$dt_hnay  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND DATE(tao_luc)=%s", $hnay ) );
		$dt_thang = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND DATE_FORMAT(tao_luc,'%%Y-%%m')=%s", $thang ) );
		$sl_ban   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE $paid" );
		$sl_cho   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE trang_thai='cho'" );
		$cards = array(
			array( 'Doanh thu hôm nay', number_format( $dt_hnay, 0, ',', '.' ) . 'đ', '#166534' ),
			array( 'Doanh thu tháng này', number_format( $dt_thang, 0, ',', '.' ) . 'đ', '#1d4ed8' ),
			array( 'Vé đã bán', number_format( $sl_ban, 0, ',', '.' ), '#0f172a' ),
			array( 'Vé đang chờ TT', number_format( $sl_cho, 0, ',', '.' ), '#92600a' ),
		);
		echo '<div style="display:flex;gap:14px;flex-wrap:wrap;margin:14px 0 6px">';
		foreach ( $cards as $c ) {
			echo '<div style="flex:1;min-width:170px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;box-shadow:0 2px 8px rgba(0,0,0,.04)">'
				. '<div style="color:#64748b;font-size:13px">' . esc_html( $c[0] ) . '</div>'
				. '<div style="font-size:24px;font-weight:800;color:' . esc_attr( $c[2] ) . ';margin-top:4px">' . esc_html( $c[1] ) . '</div></div>';
		}
		echo '</div>';

		// Biểu đồ doanh thu 7 ngày gần nhất
		$lb = array(); $dl = array();
		for ( $i = 6; $i >= 0; $i-- ) {
			$d = gmdate( 'Y-m-d', strtotime( "-$i day", (int) current_time( 'timestamp' ) ) );
			$lb[] = gmdate( 'd/m', strtotime( $d ) );
			$dl[] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND DATE(tao_luc)=%s", $d ) );
		}
		echo '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;max-width:760px;margin:8px 0 4px"><b>Doanh thu 7 ngày</b><div style="height:180px"><canvas id="pve-chart"></canvas></div></div>';
		echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>';
		echo '<script>(function(){function go(){var c=document.getElementById("pve-chart");if(!c)return;if(!window.Chart){setTimeout(go,120);return;}'
			. 'new Chart(c,{type:"line",data:{labels:' . wp_json_encode( $lb ) . ',datasets:[{label:"Doanh thu",data:' . wp_json_encode( $dl )
			. ',borderColor:"#cf9f22",backgroundColor:"rgba(207,159,34,.12)",fill:true,tension:.35,pointRadius:3,borderWidth:2}]},'
			. 'options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:function(v){return Number(v).toLocaleString("vi-VN");}}}}}});}go();})();</script>';

		$top = $wpdb->get_results( "SELECT dv_ten, COUNT(*) sl, COALESCE(SUM(so_tien),0) dt FROM $tbl WHERE $paid GROUP BY dv_ten ORDER BY sl DESC LIMIT 5", ARRAY_A );
		if ( $top ) {
			echo '<p style="margin:12px 0 4px"><b>Vé bán chạy</b></p><table class="widefat striped" style="max-width:640px"><thead><tr><th>Dịch vụ</th><th style="width:110px">Đã bán</th><th style="width:150px">Doanh thu</th></tr></thead><tbody>';
			foreach ( $top as $t ) {
				echo '<tr><td>' . esc_html( $t['dv_ten'] ) . '</td><td>' . (int) $t['sl'] . '</td><td>' . esc_html( number_format( $t['dt'], 0, ',', '.' ) ) . 'đ</td></tr>';
			}
			echo '</tbody></table>';
		}

		// Tách theo kênh bán (Zalo / Web)
		$ve_zalo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE $paid AND nguon='zalo'" );
		$ve_web  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE $paid AND nguon<>'zalo'" );
		$dt_zalo = (int) $wpdb->get_var( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND nguon='zalo'" );
		$dt_web  = (int) $wpdb->get_var( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND nguon<>'zalo'" );
		echo '<p style="margin:14px 0 4px"><b>Bán theo kênh</b></p>';
		echo '<table class="widefat striped" style="max-width:520px"><thead><tr><th>Kênh</th><th style="width:120px">Vé đã bán</th><th style="width:160px">Doanh thu</th></tr></thead><tbody>';
		echo '<tr><td>📱 Zalo Mini App</td><td>' . $ve_zalo . '</td><td>' . esc_html( number_format( $dt_zalo, 0, ',', '.' ) ) . 'đ</td></tr>';
		echo '<tr><td>🌐 Web (khmatrix.com)</td><td>' . $ve_web . '</td><td>' . esc_html( number_format( $dt_web, 0, ',', '.' ) ) . 'đ</td></tr>';
		echo '</tbody></table>';
		echo '<hr>';

		/* Tài khoản nhận tiền */
		echo '<h2>Tài khoản nhận tiền</h2><form method="post"><table class="form-table">';
		wp_nonce_field( 'pve_bank' );
		echo '<tr><th>Số tài khoản</th><td><input name="so_tk" class="regular-text code" value="' . esc_attr( get_option( 'pve_so_tk', '' ) ) . '"></td></tr>';
		echo '<tr><th>BIN ngân hàng</th><td><input name="bin" class="regular-text code" value="' . esc_attr( get_option( 'pve_bin', '' ) ) . '" placeholder="VD 970415 = VietinBank"></td></tr>';
		echo '<tr><th>Chủ tài khoản</th><td><input name="ten_tk" class="regular-text" value="' . esc_attr( get_option( 'pve_ten_tk', '' ) ) . '"></td></tr>';
		echo '</table><p class="description">Để trống cả 3 ô = tự dùng lại tài khoản đã khai ở plugin ghế. Đang dùng: <b>'
			. ( $b['so_tk'] ? esc_html( $b['so_tk'] . ' · ' . $b['ten_nh'] . ' · ' . $b['ten_tk'] ) : 'CHƯA CÓ — khách sẽ không tạo được QR' ) . '</b></p>';
		echo '<p><button class="button button-primary" name="pve_bank" value="1">Lưu tài khoản</button></p></form><hr>';

		/* Cổng thanh toán Momo / VNPay (khoá bí mật lưu ở đây, KHÔNG nằm trong mã nguồn) */
		/* ── Ví tiền: gói nạp + mã ưu đãi ─────────────────────────────────────────────── */
		echo '<h2>Ví tiền — gói nạp &amp; mã ưu đãi</h2>';
		echo '<p class="description">Khách nạp trước vào ví rồi dùng dần. <b>Mệnh giá phải có trong bảng này</b> — trang không nhận số khách tự gõ, vì "tặng thêm" đi theo gói. Ví dùng danh tính <b>Zalo</b>: khách phải đăng nhập Zalo mới có ví.</p>';
		echo '<form method="post">';
		wp_nonce_field( 'pve_vi' );
		$goi = self::ds_goi_nap();
		if ( ! $goi ) { $goi = array( array( 'nap' => 100000, 'tang' => 0 ) ); }
		$goi[] = array( 'nap' => 0, 'tang' => 0 );   // một dòng trống để thêm mới
		echo '<h3 style="margin-bottom:4px">Gói nạp</h3>';
		echo '<table class="widefat striped" style="max-width:560px"><thead><tr><th>Mệnh giá nạp (đ)</th><th>Tặng thêm (đ)</th><th>Khách nhận</th></tr></thead><tbody>';
		foreach ( $goi as $g ) {
			echo '<tr><td><input type="number" name="goi_nap[]" value="' . ( $g['nap'] ? (int) $g['nap'] : '' ) . '" placeholder="vd 200000" style="width:100%"></td>'
				. '<td><input type="number" name="goi_tang[]" value="' . ( $g['tang'] ? (int) $g['tang'] : '' ) . '" placeholder="0" style="width:100%"></td>'
				. '<td><b>' . esc_html( number_format_i18n( (int) $g['nap'] + (int) $g['tang'] ) ) . 'đ</b></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">Xoá mệnh giá là gói đó biến mất. Lưu xong trang tự thêm một dòng trống mới.</p>';

		$code = self::ds_ma_uu_dai();
		$code[] = array( 'code' => '', 'kieu' => 'pt', 'gia_tri' => 0, 'toi_thieu' => 0, 'han' => '', 'gioi_han' => 0, 'da_dung' => 0 );
		echo '<h3 style="margin-bottom:4px">Mã ưu đãi khi nạp</h3>';
		echo '<table class="widefat striped"><thead><tr><th>Mã</th><th>Cơ sở áp dụng</th><th>Kiểu</th><th>Giá trị</th><th>Nạp tối thiểu (đ)</th><th>Hạn (YYYY-MM-DD)</th><th>Giới hạn lượt</th><th>Đã dùng</th></tr></thead><tbody>';
		foreach ( $code as $c ) {
			echo '<tr>'
				. '<td><input name="c_ma[]" value="' . esc_attr( $c['code'] ) . '" placeholder="HE2026" style="width:110px;text-transform:uppercase"></td>'
				. '<td><select name="c_coso[]"><option value="">Mọi cơ sở</option>';
			foreach ( self::ds_coso() as $cs0 ) {
				echo '<option value="' . esc_attr( $cs0['ten'] ) . '"' . selected( $c['coso'], $cs0['ten'], false ) . '>' . esc_html( $cs0['ten'] ) . '</option>';
			}
			echo '</select></td>'
				. '<td><select name="c_kieu[]"><option value="pt"' . selected( $c['kieu'], 'pt', false ) . '>% tặng thêm</option>'
				. '<option value="tien"' . selected( $c['kieu'], 'tien', false ) . '>Số tiền tặng</option></select></td>'
				. '<td><input type="number" name="c_gia_tri[]" value="' . ( $c['gia_tri'] ? (int) $c['gia_tri'] : '' ) . '" placeholder="10" style="width:90px"></td>'
				. '<td><input type="number" name="c_toi_thieu[]" value="' . ( $c['toi_thieu'] ? (int) $c['toi_thieu'] : '' ) . '" placeholder="0" style="width:120px"></td>'
				. '<td><input name="c_han[]" value="' . esc_attr( $c['han'] ) . '" placeholder="để trống = không hạn" style="width:150px"></td>'
				. '<td><input type="number" name="c_gioi_han[]" value="' . ( $c['gioi_han'] ? (int) $c['gioi_han'] : '' ) . '" placeholder="0 = không giới hạn" style="width:110px"></td>'
				. '<td>' . (int) $c['da_dung'] . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description"><b>% tặng thêm</b>: giá trị 10 = nạp 200.000đ được tặng 20.000đ (làm tròn xuống hàng nghìn). '
			. '<b>Số tiền tặng</b>: giá trị 20000 = tặng thẳng 20.000đ. '
			. 'Số lượt chỉ tăng khi <b>tiền thật sự về</b>, không tăng lúc khách mới bấm tạo mã — nếu không thì mã hết sạch vì những người không chuyển tiền.</p>';
		echo '<p class="description"><b>Cơ sở áp dụng</b>: chọn một cơ sở thì mã chỉ ăn khi khách <b>đang đứng tại đó</b> — '
			. 'máy chủ tự kiểm bằng toạ độ, không tin theo lời trang khách khai, nên không ai gõ được mã của quầy khác. '
			. 'Cơ sở phải đã khai <b>toạ độ và bán kính</b> ở mục Tài khoản/Cơ sở, nếu không thì mã không bao giờ ăn.</p>';
		echo '<p><button class="button button-primary" name="pve_vi" value="1">Lưu ví tiền</button></p></form><hr>';

		echo '<h2>Cổng thanh toán (Momo · VNPay)</h2>';
		echo '<p class="description">Để trống = ẩn cổng đó, khách chỉ quét QR ngân hàng. Khai khoá lấy trong trang quản trị đối tác của Momo/VNPay. '
			. '<b>Không</b> ghi khoá vào mã nguồn.</p>';
		echo '<form method="post"><table class="form-table">';
		wp_nonce_field( 'pve_cong' );
		echo '<tr><th colspan="2"><h3 style="margin:.2em 0">Momo</h3></th></tr>';
		echo '<tr><th>Partner Code</th><td><input name="momo_partner" class="regular-text code" value="' . esc_attr( get_option( 'pve_momo_partner', '' ) ) . '"></td></tr>';
		echo '<tr><th>Access Key</th><td><input name="momo_access" class="regular-text code" value="' . esc_attr( get_option( 'pve_momo_access', '' ) ) . '"></td></tr>';
		echo '<tr><th>Secret Key</th><td><input name="momo_secret" type="password" class="regular-text code" value="' . esc_attr( get_option( 'pve_momo_secret', '' ) ) . '" autocomplete="off"></td></tr>';
		echo '<tr><th>Chế độ thử (test)</th><td><label><input type="checkbox" name="momo_test" value="1"' . checked( 1, (int) get_option( 'pve_momo_test', 0 ), false ) . '> Dùng test-payment.momo.vn</label></td></tr>';
		echo '<tr><th>IPN URL (khai bên Momo)</th><td><code>' . esc_html( self::ipn_momo() ) . '</code></td></tr>';
		echo '<tr><th colspan="2"><h3 style="margin:.2em 0">VNPay</h3></th></tr>';
		echo '<tr><th>TmnCode</th><td><input name="vnp_tmn" class="regular-text code" value="' . esc_attr( get_option( 'pve_vnp_tmn', '' ) ) . '"></td></tr>';
		echo '<tr><th>Hash Secret</th><td><input name="vnp_secret" type="password" class="regular-text code" value="' . esc_attr( get_option( 'pve_vnp_secret', '' ) ) . '" autocomplete="off"></td></tr>';
		echo '<tr><th>Chế độ thử (sandbox)</th><td><label><input type="checkbox" name="vnp_test" value="1"' . checked( 1, (int) get_option( 'pve_vnp_test', 0 ), false ) . '> Dùng sandbox.vnpayment.vn</label></td></tr>';
		echo '<tr><th>Return/IPN URL (khai bên VNPay)</th><td><code>' . esc_html( self::ipn_vnpay() ) . '</code></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_cong" value="1">Lưu cổng thanh toán</button></p></form><hr>';

		/* Bảng dịch vụ */
		echo '<h2>Danh sách dịch vụ</h2><table class="widefat striped"><thead><tr><th style="width:70px">Ảnh</th>'
			. '<th>Tên</th><th>Nhóm</th><th>Giá</th><th>Còn</th><th>Thời lượng</th><th>Hiện</th><th>Thao tác</th></tr></thead><tbody>';
		foreach ( $ds as $v ) {
			$gia_html = esc_html( number_format( $v['gia'], 0, ',', '.' ) ) . 'đ';
			if ( $v['gia_goc'] > $v['gia'] ) { $gia_html .= ' <s style="color:#999">' . esc_html( number_format( $v['gia_goc'], 0, ',', '.' ) ) . 'đ</s>'; }
			echo '<tr><td>' . ( $v['anh'] ? '<img src="' . esc_url( $v['anh'] ) . '" style="width:56px;height:56px;object-fit:cover;border-radius:8px">' : '—' ) . '</td>'
				. '<td><b>' . esc_html( $v['ten'] ) . '</b>' . ( $v['mo_ta'] !== '' ? '<br><small>' . esc_html( $v['mo_ta'] ) . '</small>' : '' ) . '</td>'
				. '<td>' . esc_html( $v['nhom'] ) . ( $v['khu_vuc'] !== '' ? '<br><small>📍 ' . esc_html( $v['khu_vuc'] ) . '</small>' : '' ) . '</td>'
				. '<td>' . $gia_html . '</td>'
				. '<td>' . ( $v['so_luong'] < 0 ? '∞' : ( $v['so_luong'] > 0 ? (int) $v['so_luong'] : '<b style="color:#991b1b">Hết</b>' ) ) . '</td>'
				. '<td>' . esc_html( $v['thoi_luong'] ) . '</td><td>' . ( $v['hien'] ? '✅' : '⛔' ) . '</td><td>'
				. '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve&sua=' . $v['id'] ) ) . '">Sửa</a> '
				. '<form method="post" style="display:inline" onsubmit="return confirm(\'Xoá?\')">';
			wp_nonce_field( 'pve_xoa' );
			echo '<input type="hidden" name="id" value="' . (int) $v['id'] . '"><button class="button" name="pve_xoa" value="1">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		/* Form thêm/sửa */
		echo '<h2>' . ( $sua ? 'Sửa dịch vụ' : 'Thêm dịch vụ' ) . '</h2><form method="post">';
		wp_nonce_field( 'pve_luu' );
		echo '<input type="hidden" name="id" value="' . (int) ( $sua ? $sua['id'] : 0 ) . '"><table class="form-table">';
		echo '<tr><th>Tên</th><td><input name="ten" class="regular-text" required value="' . esc_attr( $sua ? $sua['ten'] : '' ) . '"></td></tr>';
		echo '<tr><th>Giá bán (đ)</th><td><input name="gia" type="number" min="1000" step="1000" required value="' . esc_attr( $sua ? $sua['gia'] : '' ) . '"></td></tr>';
		echo '<tr><th>Giá gốc (đ)</th><td><input name="gia_goc" type="number" min="0" step="1000" value="' . esc_attr( $sua ? $sua['gia_goc'] : '' ) . '"> <span class="description">để 0 hoặc trống nếu không giảm giá. Lớn hơn giá bán → hiện badge % + giá gạch.</span></td></tr>';
		echo '<tr><th>Nhóm</th><td><input name="nhom" class="regular-text" placeholder="VD Vé lẻ / Combo / Funzone Aeon…" value="' . esc_attr( $sua ? $sua['nhom'] : '' ) . '"> <span class="description">gom các vé cùng nhóm thành một hàng trên app.</span></td></tr>';
		echo '<tr><th>Khu vực / Cơ sở</th><td><input name="khu_vuc" class="regular-text" placeholder="VD Hà Nội / Hồ Chí Minh / Aeon Long Biên…" value="' . esc_attr( $sua ? $sua['khu_vuc'] : '' ) . '"> <span class="description">Khách sẽ chọn khu vực khi vào trang bán; để <b>trống</b> = hiện ở mọi khu vực.</span></td></tr>';
		echo '<tr><th>Số lượng vé</th><td><input name="so_luong" type="number" min="0" step="1" value="' . esc_attr( $sua && $sua['so_luong'] >= 0 ? $sua['so_luong'] : '' ) . '"> <span class="description">số vé còn bán — để <b>trống</b> = không giới hạn. Mỗi vé bán (web/Zalo) tự trừ 1.</span></td></tr>';
		echo '<tr><th>Thời lượng</th><td><input name="thoi_luong" class="regular-text" placeholder="VD 60 phút / Cả ngày" value="' . esc_attr( $sua ? $sua['thoi_luong'] : '' ) . '"></td></tr>';
		echo '<tr><th>Mô tả</th><td><textarea name="mo_ta" rows="3" class="large-text">' . esc_textarea( $sua ? $sua['mo_ta'] : '' ) . '</textarea></td></tr>';
		echo '<tr><th>Ảnh</th><td><input type="text" name="anh" id="pve-anh" class="large-text code" value="' . esc_attr( $sua ? $sua['anh'] : '' ) . '"><br>'
			. '<button type="button" class="button" id="pve-chon" style="margin-top:6px">Chọn ảnh từ thư viện</button> '
			. '<img id="pve-xem" src="' . esc_url( $sua ? $sua['anh'] : '' ) . '" style="' . ( $sua && $sua['anh'] ? '' : 'display:none;' ) . 'height:80px;border-radius:8px;margin-left:10px;vertical-align:middle"></td></tr>';
		echo '<tr><th>Hiện</th><td><label><input type="checkbox" name="hien" value="1" ' . checked( $sua ? $sua['hien'] : 1, 1, false ) . '> Cho hiện trên app</label></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_luu" value="1">' . ( $sua ? 'Lưu thay đổi' : 'Thêm dịch vụ' ) . '</button>'
			. ( $sua ? ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve' ) ) . '">Huỷ</a>' : '' ) . '</p></form>';
		echo '<script>jQuery(function($){var f;$("#pve-chon").on("click",function(e){e.preventDefault();'
			. 'if(f){f.open();return;}f=wp.media({title:"Chọn ảnh",multiple:false,library:{type:"image"}});'
			. 'f.on("select",function(){var a=f.state().get("selection").first().toJSON();$("#pve-anh").val(a.url);$("#pve-xem").attr("src",a.url).show();});f.open();});});</script>';

		/* ── Đơn vé (bộ lọc trạng thái + tìm + soát vé) ── */
		echo '<hr><h2>Đơn vé</h2>';

		// Soát vé nhanh: nhập/quét mã -> lọc đúng vé đó.
		echo '<form method="get" style="margin:6px 0 12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">'
			. '<input type="hidden" name="page" value="posh-ve">'
			. '<input type="text" name="tim" value="' . esc_attr( isset( $_GET['tim'] ) ? wp_unslash( $_GET['tim'] ) : '' ) . '" placeholder="Soát vé: nhập mã vé / SĐT" class="regular-text code" style="max-width:280px">'
			. '<button class="button button-primary">Tìm / Soát vé</button>';
		if ( isset( $_GET['tim'] ) && '' !== trim( (string) $_GET['tim'] ) ) {
			echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve' ) ) . '">Xoá lọc</a>';
		}
		echo '</form>';

		$nhan = array( 'cho' => 'Chờ', 'da_tt' => 'Đã thanh toán', 'da_dung' => 'Đã dùng', 'huy' => 'Đã huỷ' );
		$loc  = isset( $_GET['loc'] ) ? sanitize_key( $_GET['loc'] ) : '';
		$tim  = isset( $_GET['tim'] ) ? trim( (string) wp_unslash( $_GET['tim'] ) ) : '';

		// Tabs lọc trạng thái (đếm nhanh).
		$tabs = array( '' => 'Tất cả', 'cho' => 'Chờ', 'da_tt' => 'Đã thanh toán', 'da_dung' => 'Đã dùng', 'huy' => 'Đã huỷ' );
		echo '<h2 class="nav-tab-wrapper" style="margin-bottom:12px">';
		foreach ( $tabs as $k => $ten ) {
			$dem = '' === $k ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl" )
				: (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tbl WHERE trang_thai=%s", $k ) );
			$au  = add_query_arg( array( 'page' => 'posh-ve', 'loc' => $k ), admin_url( 'admin.php' ) );
			echo '<a class="nav-tab' . ( $loc === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $au ) . '">' . esc_html( $ten ) . ' (' . $dem . ')</a>';
		}
		echo '</h2>';

		// Truy vấn có lọc + tìm.
		$where = array(); $args = array();
		if ( '' !== $loc ) { $where[] = 'trang_thai=%s'; $args[] = $loc; }
		if ( '' !== $tim ) {
			$like = '%' . $wpdb->esc_like( $tim ) . '%';
			$where[] = '(ma_ve LIKE %s OR sdt LIKE %s OR ten_khach LIKE %s)';
			array_push( $args, $like, $like, $like );
		}
		$sql = "SELECT ma_ve, dv_ten, so_tien, ten_khach, sdt, trang_thai, nguon, tao_luc, tt_luc FROM $tbl";
		if ( $where ) { $sql .= ' WHERE ' . implode( ' AND ', $where ); }
		$sql .= ' ORDER BY id DESC LIMIT 100';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		if ( ! $rows ) { echo '<p>Không có vé nào khớp.</p>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>Lúc</th><th>Mã vé</th><th>Dịch vụ</th><th>Kênh</th><th>Số tiền</th><th>Khách</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$mau = array( 'cho' => '#92600a', 'da_tt' => '#166534', 'da_dung' => '#1d4ed8', 'huy' => '#991b1b' );
				$c   = isset( $mau[ $r['trang_thai'] ] ) ? $mau[ $r['trang_thai'] ] : '#334155';
				$kenh = ( 'zalo' === $r['nguon'] ) ? '📱 Zalo' : '🌐 Web';
				echo '<tr><td>' . esc_html( $r['tao_luc'] ) . '</td><td><code>' . esc_html( $r['ma_ve'] ) . '</code></td><td>' . esc_html( $r['dv_ten'] ) . '</td>'
					. '<td>' . $kenh . '</td>'
					. '<td>' . esc_html( number_format( $r['so_tien'], 0, ',', '.' ) ) . 'đ</td>'
					. '<td>' . esc_html( $r['ten_khach'] ) . '<br><small>' . esc_html( $r['sdt'] ) . '</small></td>'
					. '<td><b style="color:' . esc_attr( $c ) . '">' . esc_html( isset( $nhan[ $r['trang_thai'] ] ) ? $nhan[ $r['trang_thai'] ] : $r['trang_thai'] ) . '</b></td><td>';
				echo '<form method="post" style="display:inline">'; wp_nonce_field( 'pve_tt' );
				echo '<input type="hidden" name="ma_ve" value="' . esc_attr( $r['ma_ve'] ) . '">';
				if ( 'cho' === $r['trang_thai'] ) {
					echo '<button class="button button-primary" name="pve_tt" value="da_tt">Đã thanh toán</button> '
						. '<button class="button" name="pve_tt" value="huy">Huỷ</button>';
				} elseif ( 'da_tt' === $r['trang_thai'] ) {
					echo '<button class="button button-primary" name="pve_tt" value="da_dung">Đã dùng (soát)</button> '
						. '<button class="button" name="pve_tt" value="huy">Huỷ</button>';
				} else { echo '—'; }
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">“Đã dùng (soát)” dùng khi khách vào cổng. Bộ lọc + ô soát vé giúp tìm nhanh 1 vé bằng mã hoặc SĐT.</p>';
		}

		/* ── Thành viên & tích điểm ── */
		echo '<hr><h2>Thành viên &amp; tích điểm</h2>';
		echo '<p class="description">Quy đổi: <b>1 điểm = 1.000đ</b> chi tiêu. Điểm cộng tự động khi bấm “Đã thanh toán” cho vé.</p>';

		// Cấu hình hạng.
		$hang = self::ds_hang();
		echo '<h3>Hạng thành viên (theo điểm)</h3><form method="post">'; wp_nonce_field( 'pve_hang' );
		echo '<table class="widefat striped" style="max-width:560px"><thead><tr><th>Tên hạng</th><th style="width:200px">Điểm tối thiểu</th></tr></thead><tbody>';
		$rows_hang = $hang; for ( $i = count( $rows_hang ); $i < 6; $i++ ) { $rows_hang[] = array( 'ten' => '', 'moc' => '' ); }
		foreach ( $rows_hang as $h ) {
			echo '<tr><td><input name="hang_ten[]" class="regular-text" value="' . esc_attr( $h['ten'] ) . '" placeholder="VD Bạc / Vàng / Kim cương"></td>'
				. '<td><input name="hang_moc[]" type="number" min="0" step="100" value="' . esc_attr( '' === $h['moc'] ? '' : (int) $h['moc'] ) . '"></td></tr>';
		}
		echo '</tbody></table><p><button class="button button-primary" name="pve_hang" value="1">Lưu hạng</button> <span class="description">Bỏ trống tên = xoá hạng đó.</span></p></form>';

		// Danh sách thành viên.
		$tvs = $wpdb->get_results( 'SELECT sdt, ten, diem, tong_chi, so_don, sua_luc FROM ' . self::tbl_tv() . ' ORDER BY diem DESC LIMIT 100', ARRAY_A );
		echo '<h3>Khách tích điểm</h3>';
		if ( ! $tvs ) { echo '<p>Chưa có khách nào tích điểm. (Điểm cộng khi vé chuyển sang “Đã thanh toán”.)</p>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>SĐT</th><th>Tên</th><th>Điểm</th><th>Hạng</th><th>Tổng chi</th><th>Số đơn</th><th>Cập nhật</th></tr></thead><tbody>';
			foreach ( $tvs as $t ) {
				$hc = self::hang_cua( (int) $t['diem'] );
				echo '<tr><td><code>' . esc_html( $t['sdt'] ) . '</code></td><td>' . esc_html( $t['ten'] ) . '</td>'
					. '<td><b>' . esc_html( number_format( $t['diem'], 0, ',', '.' ) ) . '</b></td>'
					. '<td>' . esc_html( $hc['hang'] ) . '</td>'
					. '<td>' . esc_html( number_format( $t['tong_chi'], 0, ',', '.' ) ) . 'đ</td>'
					. '<td>' . (int) $t['so_don'] . '</td><td>' . esc_html( $t['sua_luc'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ── Cơ sở & toạ độ (gợi ý theo định vị) ── */
		/* ── Ảnh danh mục (dải chọn vé ở trang khách) ── */
		echo '<hr><h2>Ảnh danh mục vé</h2>';
		echo '<p class="description">Dải danh mục ở trang bán vé hiện <b>ảnh + tên</b> cho khách bấm. '
			. 'Bỏ trống thì tự lấy ảnh của vé đầu tiên trong danh mục — khai ở đây khi muốn dùng <b>logo riêng</b> cho danh mục đó. '
			. 'Danh mục chính là ô “Nhóm” của từng vé.</p>';
		$dm_anh = self::ds_nhom_anh();
		$dm_ten = array();
		foreach ( self::ds_tatca() as $g ) {
			$n = trim( (string) $g['nhom'] ) !== '' ? $g['nhom'] : 'Vé';
			if ( ! in_array( $n, $dm_ten, true ) ) { $dm_ten[] = $n; }
		}
		foreach ( array_keys( $dm_anh ) as $n ) { if ( ! in_array( $n, $dm_ten, true ) ) { $dm_ten[] = $n; } }
		if ( ! $dm_ten ) {
			echo '<p class="description"><i>Chưa có vé nào nên chưa có danh mục. Thêm vé trước, ô “Nhóm” của vé chính là danh mục.</i></p>';
		} else {
			echo '<form method="post">'; wp_nonce_field( 'pve_nhom_anh' );
			echo '<table class="widefat striped" style="max-width:860px"><thead><tr><th style="width:220px">Danh mục</th>'
				. '<th>Ảnh (URL)</th><th style="width:210px">Xem trước</th></tr></thead><tbody>';
			foreach ( $dm_ten as $i => $n ) {
				$a = isset( $dm_anh[ $n ] ) ? (string) $dm_anh[ $n ] : '';
				echo '<tr><td><b>' . esc_html( $n ) . '</b><input type="hidden" name="dm_ten[]" value="' . esc_attr( $n ) . '"></td>'
					. '<td><input type="text" name="dm_anh[]" id="dm-anh-' . (int) $i . '" class="large-text code" value="' . esc_attr( $a ) . '" placeholder="để trống = lấy ảnh vé đầu tiên"> '
					. '<button type="button" class="button pve-dm-chon" data-o="dm-anh-' . (int) $i . '" data-x="dm-xem-' . (int) $i . '">Chọn ảnh</button></td>'
					. '<td><img id="dm-xem-' . (int) $i . '" src="' . esc_url( $a ) . '" style="' . ( $a ? '' : 'display:none;' ) . 'height:64px;width:64px;object-fit:cover;border-radius:10px"></td></tr>';
			}
			echo '</tbody></table><p><button class="button button-primary" name="pve_nhom_anh_luu" value="1">Lưu ảnh danh mục</button></p></form>';
			/* Bộ chọn ảnh dùng chung cho mọi dòng — một trình chọn, nhận id ô đích từ data-o. */
			echo '<script>jQuery(function($){var f;$(".pve-dm-chon").on("click",function(e){e.preventDefault();var b=$(this);'
				. 'f=wp.media({title:"Chọn ảnh danh mục",library:{type:"image"},multiple:false});'
				. 'f.on("select",function(){var a=f.state().get("selection").first().toJSON();'
				. '$("#"+b.data("o")).val(a.url);$("#"+b.data("x")).attr("src",a.url).show();});f.open();});});</script>';
		}

		echo '<hr><h2>Cơ sở · toạ độ · giảm giá tại chỗ</h2>';
		echo '<p class="description">Tên cơ sở phải khớp <b>đúng</b> với ô “Khu vực / Cơ sở” của vé. Toạ độ lấy từ Google Maps: chuột phải điểm cần → bấm cặp số để copy (dạng <code>10.776,106.700</code>).<br>'
			. '<b>% giảm</b> chỉ áp khi khách quét tem QR của cơ sở <i>và</i> điện thoại báo đang trong <b>bán kính</b> đó. Mua từ xa = giá gốc. '
			. 'Bán kính mặc định 400m — GPS trong trung tâm thương mại sai vài trăm mét là thường, siết quá nhỏ thì khách đứng ngay quầy vẫn bị từ chối giảm.</p>';
		$coso = self::ds_coso();
		echo '<form method="post">'; wp_nonce_field( 'pve_coso' );
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Tên cơ sở / khu vực</th>'
			. '<th style="width:120px">Mã (tem QR)</th><th style="width:130px">Vĩ độ (lat)</th><th style="width:130px">Kinh độ (lng)</th>'
			. '<th style="width:90px">% giảm</th><th style="width:110px">Bán kính (m)</th><th style="width:220px">Tem QR dán tại quầy</th></tr></thead><tbody>';
		$rows_cs = $coso; for ( $i = count( $rows_cs ); $i < 8; $i++ ) { $rows_cs[] = array( 'ten' => '', 'ma' => '', 'lat' => '', 'lng' => '', 'giam' => 0, 'bk' => 0 ); }
		foreach ( $rows_cs as $c ) {
			$lat = ( is_numeric( $c['lat'] ) && $c['lat'] ) ? $c['lat'] : '';
			$lng = ( is_numeric( $c['lng'] ) && $c['lng'] ) ? $c['lng'] : '';
			echo '<tr><td><input name="cs_ten[]" class="regular-text" value="' . esc_attr( $c['ten'] ) . '" placeholder="VD Aeon Long Biên"></td>'
				. '<td><input name="cs_ma[]" class="regular-text code" style="width:110px" value="' . esc_attr( isset( $c['ma'] ) ? $c['ma'] : '' ) . '" placeholder="tự sinh"></td>'
				. '<td><input name="cs_lat[]" class="regular-text code" style="width:120px" value="' . esc_attr( $lat ) . '" placeholder="10.7769"></td>'
				. '<td><input name="cs_lng[]" class="regular-text code" style="width:120px" value="' . esc_attr( $lng ) . '" placeholder="106.7009"></td>'
				. '<td><input name="cs_giam[]" type="number" min="0" max="90" style="width:70px" value="' . esc_attr( (int) ( isset( $c['giam'] ) ? $c['giam'] : 0 ) ) . '"></td>'
				. '<td><input name="cs_bk[]" type="number" min="50" max="5000" step="50" style="width:90px" value="' . esc_attr( (int) ( isset( $c['bk'] ) && $c['bk'] ? $c['bk'] : 400 ) ) . '"></td>'
				. '<td>' . self::tem_qr_html( isset( $c['ma'] ) ? $c['ma'] : '', $c['ten'] ) . '</td></tr>';
		}
		echo '</tbody></table><p><button class="button button-primary" name="pve_coso_luu" value="1">Lưu cơ sở</button> <span class="description">Bỏ trống tên = xoá dòng đó. Lưu xong tem QR mới hiện.</span></p></form>';

		/* ── PIN khu quản lý (Zalo) ── */
		echo '<hr><h2>Khu quản lý trên Zalo (Báo cáo / Đơn / Soát vé)</h2>';
		echo '<form method="post"><table class="form-table">'; wp_nonce_field( 'pve_pin' );
		echo '<tr><th>Mã PIN nhân viên</th><td><input name="pin" class="regular-text code" value="' . esc_attr( get_option( 'pve_pin', '' ) ) . '" placeholder="VD 4–8 chữ số"> '
			. '<span class="description">Nhập PIN này trong app Zalo (mục Quản lý) để xem báo cáo, xác nhận/huỷ, soát vé. Để trống = KHOÁ khu quản lý.</span></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_pin_luu" value="1">Lưu PIN</button></p></form>';

		/* ── Đăng nhập Zalo (lấy SĐT khách) ── */
		echo '<hr><h2>Đăng nhập Zalo – lấy số điện thoại khách</h2>';
		echo '<p class="description">Để app tự lấy SĐT khi khách đăng nhập Zalo. Lấy <b>Secret Key</b> ở Zalo Mini App console: <i>Thông tin ứng dụng → App Secret Key</i>. Cần bật quyền <i>“Số điện thoại”</i> cho Mini App. Secret lưu tại đây (DB), không nằm trong mã nguồn.</p>';
		$zs = (string) get_option( 'pve_zalo_secret', '' );
		$zappid = (string) get_option( 'pve_zalo_appid', '' );
		echo '<form method="post"><table class="form-table">'; wp_nonce_field( 'pve_zalo' );
		echo '<tr><th>Zalo App ID</th><td><input name="zalo_appid" class="regular-text code" value="' . esc_attr( $zappid ) . '" placeholder="VD 1234567890123"> <span class="description">Dùng cho nút “Đăng nhập bằng Zalo” trên web.</span></td></tr>';
		echo '<tr><th>Zalo App Secret Key</th><td><input name="zalo_secret" class="regular-text code" value="' . esc_attr( $zs ) . '" placeholder="Dán Secret Key">'
			. ' <span class="description">' . ( $zs ? 'Đang có (' . esc_html( strlen( $zs ) ) . ' ký tự)' : 'Chưa cấu hình' ) . '</span></td></tr>';
		echo '<tr><th>Callback URL (khai trên Zalo)</th><td><code>' . esc_html( rest_url( self::NS . '/zalo/cb' ) ) . '</code><br><span class="description">Vào Zalo App console → Đăng nhập → thêm URL này vào <i>Redirect URI</i>. <b>Bắt buộc</b>: vào <i>Xác thực domain → Tiền tố URL</i> thêm <code>' . esc_html( home_url( '/' ) ) . '</code> và Xác thực (redirect URI phải nằm dưới tiền tố đã xác thực). SĐT vẫn nhập ở form vì Zalo hạn chế lấy SĐT qua web.</span></td></tr>';
		$zv = self::zalo_verify_token();
		echo '<tr><th>Mã xác thực domain</th><td><input name="zalo_verify" class="large-text code" value="' . esc_attr( (string) get_option( 'pve_zalo_verify', '' ) ) . '" placeholder="VD IS-HTRVS1az... (dán mã Zalo cho)">'
			. '<br><span class="description">Zalo console → <i>Xác thực domain</i> cho mã dạng <code>IS-xxxx</code>. Dán vào đây → plugin tự chèn thẻ meta + phục vụ file xác thực. '
			. ( $zv ? 'Đang có: kiểm tra <a href="' . esc_url( home_url( '/zalo_verifier' . $zv . '.html' ) ) . '" target="_blank">file xác thực</a>.' : '' )
			. ' Xong thì bấm <b>Xác thực</b> trên Zalo (chọn cách <i>meta</i> hoặc <i>file</i> đều được).</span></td></tr>';
		echo '<tr><th>Zalo ID quản trị</th><td><input name="zalo_admin_ids" class="large-text code" value="' . esc_attr( (string) get_option( 'pve_zalo_admin_ids', '' ) ) . '" placeholder="VD 123456789, 987654321">'
			. '<p class="description"><b>Từ bản 1.39.0 đây là CỬA VÀO khu quản trị:</b> Zalo ID khai ở đây đăng nhập Zalo xong là vào thẳng, <b>không cần mã PIN</b>. '
			. 'Để <b>trống</b> = không ai được bỏ qua PIN (và mọi người đã đăng nhập đều thấy nút Quản trị như trước). Khai nhầm một ID lạ là trao khu quản trị cho người đó.</p>'
			. '<br><span class="description">Các Zalo ID được hiện nút <b>“🔧 Quản trị vé”</b> trên web sau khi đăng nhập Zalo (cách nhau dấu phẩy). Để <b>trống</b> = mọi người đăng nhập Zalo đều thấy nút (trang vẫn khoá bằng PIN). Mẹo lấy ID: đăng nhập Zalo trên web, nút quản trị sẽ hiện — hoặc xem trong <i>Đơn vé</i>.</span></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_zalo_luu" value="1">Lưu cấu hình Zalo</button></p></form>';

		/* ── Chân trang (thông tin công ty) ── */
		echo '<hr><h2>Chân trang trang bán vé</h2>';
		echo '<p class="description">Hiện ở cuối trang <code>[posh_ve]</code>: thông tin công ty + số phiên bản + link trang nội bộ. Phiên bản hiện tại: <b>' . esc_html( self::phien_ban() ) . '</b>.</p>';
		echo '<form method="post"><table class="form-table">'; wp_nonce_field( 'pve_ft' );
		echo '<tr><th>Tên công ty</th><td><input name="ft_ten" class="regular-text" value="' . esc_attr( get_option( 'pve_ft_ten', 'K&H COM., LTD' ) ) . '"></td></tr>';
		echo '<tr><th>Địa chỉ</th><td><input name="ft_dc" class="large-text" value="' . esc_attr( get_option( 'pve_ft_dc', '' ) ) . '"></td></tr>';
		echo '<tr><th>Liên hệ (ĐT / Email)</th><td><input name="ft_lh" class="regular-text" value="' . esc_attr( get_option( 'pve_ft_lh', '' ) ) . '"></td></tr>';
		echo '<tr><th>Link trang nội bộ</th><td><input name="ft_nb_url" class="large-text code" value="' . esc_attr( get_option( 'pve_ft_nb_url', admin_url( 'admin.php?page=posh-ve' ) ) ) . '"> <span class="description">VD trang quản lý nội bộ.</span></td></tr>';
		echo '<tr><th>Chữ hiển thị link</th><td><input name="ft_nb_ten" class="regular-text" value="' . esc_attr( get_option( 'pve_ft_nb_ten', 'Trang nội bộ' ) ) . '"></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_ft_luu" value="1">Lưu chân trang</button></p></form>';

		/* ── Ưu đãi (hiện trên Zalo) ── */
		echo '<hr><h2>Ưu đãi (hiện trên Zalo)</h2>';
		$uus = self::ds_uudai_tatca();
		$uu_sua = null; $uid = isset( $_GET['sua_uu'] ) ? (int) $_GET['sua_uu'] : 0;
		if ( $uid ) { foreach ( $uus as $v ) { if ( (int) $v['id'] === $uid ) { $uu_sua = $v; break; } } }
		echo '<table class="widefat striped"><thead><tr><th style="width:70px">Ảnh</th><th>Tên</th><th>Hạng cần</th><th>Hạn</th><th>Hiện</th><th>Thao tác</th></tr></thead><tbody>';
		if ( ! $uus ) { echo '<tr><td colspan="6">Chưa có ưu đãi nào.</td></tr>'; }
		foreach ( $uus as $v ) {
			echo '<tr><td>' . ( $v['anh'] ? '<img src="' . esc_url( $v['anh'] ) . '" style="width:56px;height:56px;object-fit:cover;border-radius:8px">' : '—' ) . '</td>'
				. '<td><b>' . esc_html( $v['ten'] ) . '</b>' . ( $v['mo_ta'] ? '<br><small>' . esc_html( $v['mo_ta'] ) . '</small>' : '' ) . '</td>'
				. '<td>' . esc_html( $v['hang'] !== '' ? $v['hang'] : 'Mọi khách' ) . '</td>'
				. '<td>' . esc_html( $v['han'] ) . '</td><td>' . ( $v['hien'] ? '✅' : '⛔' ) . '</td><td>'
				. '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve&sua_uu=' . $v['id'] . '#uu' ) ) . '">Sửa</a> '
				. '<form method="post" style="display:inline" onsubmit="return confirm(\'Xoá ưu đãi?\')">';
			wp_nonce_field( 'pve_uu_xoa' );
			echo '<input type="hidden" name="id" value="' . (int) $v['id'] . '"><button class="button" name="pve_uu_xoa" value="1">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		echo '<h3 id="uu">' . ( $uu_sua ? 'Sửa ưu đãi' : 'Thêm ưu đãi' ) . '</h3><form method="post">';
		wp_nonce_field( 'pve_uu' );
		echo '<input type="hidden" name="id" value="' . (int) ( $uu_sua ? $uu_sua['id'] : 0 ) . '"><table class="form-table">';
		echo '<tr><th>Tên ưu đãi</th><td><input name="ten" class="regular-text" required value="' . esc_attr( $uu_sua ? $uu_sua['ten'] : '' ) . '"></td></tr>';
		echo '<tr><th>Mô tả</th><td><textarea name="mo_ta" rows="3" class="large-text">' . esc_textarea( $uu_sua ? $uu_sua['mo_ta'] : '' ) . '</textarea></td></tr>';
		echo '<tr><th>Hạng cần (tối thiểu)</th><td><input name="hang" class="regular-text" placeholder="VD Vàng / Kim cương — trống = mọi khách" value="' . esc_attr( $uu_sua ? $uu_sua['hang'] : '' ) . '"></td></tr>';
		echo '<tr><th>Hạn dùng</th><td><input name="han" class="regular-text" placeholder="VD đến 31/12 / còn 10 ngày" value="' . esc_attr( $uu_sua ? $uu_sua['han'] : '' ) . '"></td></tr>';
		echo '<tr><th>Ảnh</th><td><input type="text" name="anh" id="pve-uu-anh" class="large-text code" value="' . esc_attr( $uu_sua ? $uu_sua['anh'] : '' ) . '"><br>'
			. '<button type="button" class="button" id="pve-uu-chon" style="margin-top:6px">Chọn ảnh từ thư viện</button> '
			. '<img id="pve-uu-xem" src="' . esc_url( $uu_sua ? $uu_sua['anh'] : '' ) . '" style="' . ( $uu_sua && $uu_sua['anh'] ? '' : 'display:none;' ) . 'height:70px;border-radius:8px;margin-left:10px;vertical-align:middle"></td></tr>';
		echo '<tr><th>Hiện</th><td><label><input type="checkbox" name="hien" value="1" ' . checked( $uu_sua ? $uu_sua['hien'] : 1, 1, false ) . '> Cho hiện trên Zalo</label></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_uu_luu" value="1">' . ( $uu_sua ? 'Lưu thay đổi' : 'Thêm ưu đãi' ) . '</button>'
			. ( $uu_sua ? ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve' ) ) . '">Huỷ</a>' : '' ) . '</p></form>';
		echo '<script>jQuery(function($){var f;$("#pve-uu-chon").on("click",function(e){e.preventDefault();'
			. 'if(f){f.open();return;}f=wp.media({title:"Chọn ảnh",multiple:false,library:{type:"image"}});'
			. 'f.on("select",function(){var a=f.state().get("selection").first().toJSON();$("#pve-uu-anh").val(a.url);$("#pve-uu-xem").attr("src",a.url).show();});f.open();});});</script>';

		echo '</div>';
	}
}

register_activation_hook( __FILE__, function () { POSH_Ve::bao_dam_bang(); POSH_Ve::bao_dam_trang(); POSH_Ve::bao_dam_trang_ql(); flush_rewrite_rules(); } );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'pve_do_saoke' ); } );
add_action( 'init', array( 'POSH_Ve', 'init' ), 6 );

endif; // class_exists( 'POSH_Ve' )
