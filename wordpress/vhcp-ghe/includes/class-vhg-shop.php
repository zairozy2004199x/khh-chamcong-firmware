<?php
/**
 * TRANG MINI BÁN MÃ — trang của KHÁCH, không phải của nhân viên.
 *
 * =============================================================================================
 * BA ĐIỀU QUYẾT ĐỊNH TOÀN BỘ THIẾT KẾ TRANG NÀY
 * =============================================================================================
 * 1. KHÔNG CÓ CỔNG PIN. Đây là trang khách vãng lai mở bằng cách quét tem dán cạnh thùng tiền.
 *    Bắt đăng nhập là mất khách ngay ở bước đầu. Bù lại: trang này KHÔNG đọc được doanh thu,
 *    KHÔNG bật/tắt được ghế, và mọi thứ nó làm đều phải trả tiền trước.
 *
 * 2. CÓ CẢ MÃ QR LẪN CHỮ CHÉP TAY — hai đường, vì khách dùng hai kiểu khác nhau.
 *    ⚠️ Em từng bỏ mã QR với lý do "khách đang cầm chính cái máy hiện trang này, không ai quét
 *       được màn hình của mình". Suy luận đó THIẾU: app ngân hàng Việt Nam đều cho **chọn ảnh QR
 *       từ thư viện**. Khách bấm tải ảnh, mở app, chọn ảnh — quét được bình thường. Chưa kể
 *       người thứ hai (nhân viên, người đi cùng) chĩa máy vào màn là quét luôn.
 *    Nên có nút TẢI ẢNH QR, và ảnh phải là PNG: thư viện ảnh của điện thoại không hiện tệp SVG,
 *    tải về một tệp không nhìn thấy trong thư viện thì coi như chưa tải.
 *
 * 3. NỘI DUNG CHUYỂN KHOẢN LÀ THỨ DUY NHẤT NỐI TIỀN VỚI ĐƠN. Gõ sai một ký tự là tiền vào tài
 *    khoản mà không ai biết của đơn nào. Nên nó phải to, có nút sao chép, và có câu cảnh báo
 *    ngay cạnh — không nhét vào chữ nhỏ.
 *
 * ⚠️ HÃM THỬ Ở Ô TRA MÃ. Số điện thoại là thứ đoán được; không hãm thì một máy dò hết 10.000 PIN
 *    của một số trong vài phút. Xem `bi_khoa()`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Shop {

	public static function slug() {
		$s = get_option( 'vhg_slug_ma' );
		$s = $s ? sanitize_title( $s ) : 'mua-ma';
		return $s ? $s : 'mua-ma';
	}

	public static function url( $ma_may = '' ) {
		$u = get_option( 'permalink_structure' )
			? home_url( '/' . self::slug() . '/' )
			: add_query_arg( 'vhg', 'shop', home_url( '/' ) );
		$m = trim( (string) $ma_may );
		return '' !== $m ? add_query_arg( 'ghe', $m, $u ) : $u;
	}

	/**
	 * Địa chỉ ĐẦY ĐỦ của một ghế — dùng cho TEM IN. Đây là dạng đã chạy thật trên khmatrix.com.
	 *
	 * 🔴 Dạng tham số `?ghe=` chứ KHÔNG phải dạng thư mục `/mua-ma/AMTP01`. Anh Thắng thử
	 *    23/08/2026: `/MUA-MA/AMTP01` nhảy ra trang "Không tìm thấy trang", còn
	 *    `/mua-ma/?ghe=AMTP01` vào đúng trang.
	 *
	 *    Hai lý do, và cả hai đều là lỗi của bản trước:
	 *      · Luật đường dẫn của WordPress PHÂN BIỆT HOA THƯỜNG. Em viết hoa cả địa chỉ cho mã QR
	 *        nhỏ lại, nhưng `/MUA-MA/` thì không khớp luật `mua-ma` nào cả.
	 *      · Luật dạng thư mục là luật MỚI, chỉ có hiệu lực sau khi WordPress nạp lại bảng đường
	 *        dẫn. Dạng `?ghe=` thì không cần luật nào — nó chạy ngay, ở mọi cấu hình.
	 *
	 *    Tem dán lên ghế nhiều năm. Nó phải mang dạng CHẮC CHẮN CHẠY, không phải dạng ngắn nhất.
	 *
	 * ⚠️ Và viết hoa hoá ra CHẲNG LỢI GÌ ở độ dài này: `KHMATRIX.COM/MUA-MA/AMTP01` với
	 *    `khmatrix.com/mua-ma/AMTP01` đều ra mã 25×25. Em đã đánh đổi tính đúng đắn lấy một cái
	 *    lợi bằng không.
	 */
	public static function url_ghe( $ma_may ) {
		$m = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $ma_may ) );
		if ( '' === $m ) { return ''; }
		return add_query_arg( 'ghe', $m, self::url() );
	}

	/**
	 * Cùng địa chỉ đó nhưng BỎ "https://" — chỉ dùng cho mã QR ghế tự vẽ trên màn.
	 *
	 * 🔴 Ô gói trên màn ghế chỉ chừa `VHG_Ma::QR_VUNG_PX` pixel. Có scheme thì chuỗi dài 39 ký tự
	 *    -> mã 29×29; bỏ scheme còn 31 ký tự -> 25×25. Đó là toàn bộ lý do tồn tại của hàm này.
	 *    (Đừng chép số pixel vào đây thành chữ — nó đã đổi một lần, 58 -> 70.)
	 *
	 * ⚠️ GIỮ NGUYÊN HOA THƯỜNG của đường dẫn. Viết hoa không làm mã nhỏ hơn ở độ dài này, mà
	 *    lại làm đường dẫn không khớp — đúng lỗi vừa sửa.
	 * ⚠️ Đường dẫn phải là dạng đã chạy thật (`?ghe=`), y như tem in. Hai nơi hai dạng khác nhau
	 *    là một nơi sẽ chết mà nơi kia vẫn chạy, nên không ai phát hiện.
	 */
	public static function url_ngan( $ma_may ) {
		$u = self::url_ghe( $ma_may );
		if ( '' === $u ) { return ''; }
		return preg_replace( '#^https?://#i', '', $u );
	}

	public static function init() {
		/* Dạng thư mục `/<slug>/<mã ghế>` — cho mã QR trên màn ghế ngắn hết mức. Khai TRƯỚC luật
		   không có mã ghế: WordPress lấy luật khớp đầu tiên. */
		add_rewrite_rule( '^' . self::slug() . '/([A-Za-z0-9]{1,20})/?$',
			'index.php?vhg_shop=1&vhg_ghe=$matches[1]', 'top' );
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhg_shop=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'chan_chuyen_huong' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ), 0 );
	}

	public static function query_vars( $v ) { $v[] = 'vhg_shop'; $v[] = 'vhg_ghe'; return $v; }

	private static function la_trang() {
		if ( 1 === (int) get_query_var( 'vhg_shop' ) ) { return true; }
		if ( isset( $_GET['vhg'] ) && 'shop' === $_GET['vhg'] ) { return true; }
		$d = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$d = trim( (string) parse_url( $d, PHP_URL_PATH ), '/' );
		$s = self::slug();
		if ( $d === $s || substr( $d, - ( strlen( $s ) + 1 ) ) === '/' . $s ) { return true; }
		/* Dạng `/<slug>/<mã ghế>`. Xét cả ở đây chứ không chỉ dựa vào luật đường dẫn: luật chưa
		   được nạp lại là trang trả 404, mà mã QR thì đã in lên tem dán ở ghế rồi. */
		return (bool) preg_match( '#(^|/)' . preg_quote( $s, '#' ) . '/[A-Za-z0-9]{1,20}/?$#', $d );
	}

	/* Cùng lý do với trang nhân viên: một lượt bị chuyển hướng là mất trọn thân POST. */
	public static function chan_chuyen_huong() {
		if ( ! self::la_trang() ) { return; }
		add_filter( 'redirect_canonical', '__return_false', 99 );
		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	public static function phuc_vu() {
		if ( ! self::la_trang() ) { return; }
		if ( isset( $_GET['api'] ) || isset( $_POST['api'] ) ) {
			self::api();
			if ( ! defined( 'VHG_TEST' ) ) { exit; }
			return;
		}
		self::ve();
		if ( ! defined( 'VHG_TEST' ) ) { exit; }
	}

	// ===================================================================== hãm thử

	private static function khoa_key( $viec ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhg_shop_' . $viec . '_' . md5( $ip );
	}
	/* 15 lượt / 10 phút cho mỗi IP. Đủ rộng cho người gõ nhầm PIN vài lần, quá hẹp cho máy dò:
	   10.000 PIN với nhịp này là hơn bốn ngày. */
	private static function bi_khoa( $viec ) { return (int) get_transient( self::khoa_key( $viec ) ) >= 15; }
	private static function dem( $viec ) {
		$k = self::khoa_key( $viec );
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );
	}

	// ===================================================================== API

	private static function tra( $d ) {
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
		}
		echo wp_json_encode( $d );
	}

	private static function than() {
		if ( defined( 'VHG_TEST' ) && isset( $GLOBALS['VHG_THAN'] ) ) { return (string) $GLOBALS['VHG_THAN']; }
		$t = file_get_contents( 'php://input' );
		return is_string( $t ) ? $t : '';
	}

	public static function api() {
		$d = json_decode( self::than(), true );
		if ( ! is_array( $d ) ) { $d = array(); }
		foreach ( $_POST as $k => $v ) { if ( ! isset( $d[ $k ] ) ) { $d[ $k ] = $v; } }
		$viec = isset( $_GET['api'] ) ? (string) $_GET['api'] : (string) ( isset( $d['api'] ) ? $d['api'] : '' );
		$viec = preg_replace( '/[^a-z_]/', '', strtolower( $viec ) );

		if ( 'goi' === $viec ) {
			/* ⚠️ KHÔNG gửi danh sách ghế xuống trang khách. Ghế do CÁI TEM quyết định; đưa ra một
			   danh sách là mời dựng lại đúng cái ô chọn vừa bỏ — và nó cũng phơi toàn bộ mã ghế
			   của hệ thống ra một trang không cần đăng nhập. */
			/* 🔴 TRUYỀN MÃ GHẾ VÀO. Số phút của mỗi gói theo TỈ LỆ RIÊNG của ghế đó — không
			   truyền là mọi ghế đều hiện số phút của tỉ lệ chung, tức là sai ở mọi ghế có
			   tỉ lệ riêng. Chưa biết ghế (màn mua) thì tỉ lệ chung là đúng. */
			$ghe_ = self::ghe_tu_dia_chi( $d );
			self::tra( array( 'ok' => true, 'goi' => VHG_Ma::ds_menh_gia( $ghe_ ),
				'goi_nap' => VHG_Vi::goi_nap(),
				'ban_ma'  => VHG_Ma::con_ban_ma() ? 1 : 0,
				'cho_ngay' => VHG_Ma::cho_ngay_mac_dinh(),
				'ghe' => $ghe_ ) );
			return;
		}

		if ( 'dat' === $viec ) {
			/* ⚠️ Tắt bán mã lẻ thì phải chặn CẢ Ở CỔNG, không chỉ giấu tab. Giấu tab mà cổng
			   vẫn nhận là ai còn giữ link cũ vẫn đặt được đơn — rồi trả tiền, rồi nhận mã cho
			   một thứ cửa hàng đã ngừng bán. */
			if ( ! VHG_Ma::con_ban_ma() ) {
				self::tra( array( 'ok' => false,
					'error' => 'Cửa hàng đã chuyển sang nạp ví — mời anh/chị dùng mục Nạp ví.' ) );
				return;
			}
			$r = VHG_Ma::dat_don(
				isset( $d['sdt'] ) ? $d['sdt'] : '',
				isset( $d['pin'] ) ? $d['pin'] : '',
				isset( $d['menh_gia'] ) ? $d['menh_gia'] : 0,
				isset( $d['so_luong'] ) ? $d['so_luong'] : 1,
				isset( $d['cc'] ) ? $d['cc'] : '' );
			self::tra_don( $r );
			return;
		}

		/* Gói NẠP VÍ. Cùng một đường trả tiền với mua mã lẻ — chỉ khác thứ nhận về. */
		if ( 'dat_nap' === $viec ) {
			$r = VHG_Vi::dat_don(
				isset( $d['sdt'] ) ? $d['sdt'] : '',
				isset( $d['pin'] ) ? $d['pin'] : '',
				isset( $d['nap'] ) ? $d['nap'] : 0,
				isset( $d['cc'] ) ? $d['cc'] : '' );
			self::tra_don( $r );
			return;
		}

		/* Trang hỏi lại xem tiền về chưa. KHÔNG trả gì ngoài "xong hay chưa" + bộ mã: đơn mang
		   số điện thoại và PIN băm của khách, đưa ra là rò dữ liệu người khác nếu ai đó đoán
		   trúng mã đơn. */
		if ( 'soi' === $viec ) {
			$don = VHG_Ma::don( isset( $d['ma_don'] ) ? $d['ma_don'] : '' );
			if ( ! $don ) { self::tra( array( 'ok' => false, 'error' => 'Không thấy đơn này.' ) ); return; }
			$xong = ! empty( $don['xong_luc'] );
			$loai = (string) ( isset( $don['loai'] ) ? $don['loai'] : '' );
			/* Đơn NẠP thì thứ khách chờ là SỐ DƯ, không phải bộ mã. Trả cả hai kiểu qua cùng một
			   việc `soi` để trang chỉ phải hỏi một chỗ. */
			if ( 'nap' === $loai ) {
				$sd = $xong ? VHG_Vi::so_du( $don['sdt'] ) : null;
				self::tra( array( 'ok' => true, 'xong' => $xong ? 1 : 0, 'loai' => 'nap',
					'ma' => array(), 'nhan_tien' => (int) $don['nhan_tien'],
					'so_du' => $sd ? (int) $sd['dung'] : 0,
					'so_du_cho' => $sd ? (int) $sd['cho'] : 0,
					'con_cho' => $sd ? (int) $sd['con_cho'] : 0 ) );
				return;
			}
			$ma = array();
			if ( $xong ) {
				foreach ( VHG_Ma::ds_ma_cua_don( $don['ma_don'] ) as $m ) { $ma[] = VHG_Ma::ma_dep( $m ); }
			}
			self::tra( array( 'ok' => true, 'xong' => $xong ? 1 : 0, 'loai' => 'ma', 'ma' => $ma ) );
			return;
		}

		if ( 'tra' === $viec ) {
			if ( self::bi_khoa( 'tra' ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút rồi tra lại, hoặc nhờ nhân viên.' ) );
				return;
			}
			self::dem( 'tra' );
			$r = VHG_Ma::tra( isset( $d['sdt'] ) ? $d['sdt'] : '', isset( $d['pin'] ) ? $d['pin'] : '' );
			self::tra( $r );
			return;
		}

		if ( 'lay_lai_pin' === $viec ) {
			/* 🔴 HÃM CHẶT HƠN HẲN ô tra mã: đây là đường ĐỔI PIN, canh bằng bốn số cuối căn cước —
			   chỉ 10.000 tổ hợp. Không hãm thì dò hết trong vài phút và toàn bộ mã của người ta
			   đổi chủ. 5 lượt mỗi 10 phút: đủ cho người gõ nhầm, quá hẹp cho máy dò (10.000 tổ
			   hợp với nhịp này là hơn hai tuần). */
			if ( (int) get_transient( self::khoa_key( 'lay' ) ) >= 5 ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút, hoặc nhờ nhân viên tra giúp.' ) );
				return;
			}
			self::dem( 'lay' );
			self::tra( VHG_Ma::lay_lai_pin(
				isset( $d['sdt'] ) ? $d['sdt'] : '',
				isset( $d['cc'] ) ? $d['cc'] : '',
				isset( $d['pin'] ) ? $d['pin'] : '' ) );
			return;
		}

		if ( 'dung' === $viec ) {
			if ( self::bi_khoa( 'dung' ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút rồi thử lại.' ) );
				return;
			}
			self::dem( 'dung' );
			self::tra( VHG_Ma::dung(
				isset( $d['ma'] ) ? $d['ma'] : '',
				isset( $d['ma_may'] ) ? $d['ma_may'] : '' ) );
			return;
		}

		/* Khách tra số dư ví. Hãm y như ô tra mã: số điện thoại là thứ đoán được. */
		if ( 'vi' === $viec ) {
			if ( self::bi_khoa( 'tra' ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút rồi tra lại, hoặc nhờ nhân viên.' ) );
				return;
			}
			self::dem( 'tra' );
			$sdt_ = isset( $d['sdt'] ) ? $d['sdt'] : '';
			$pin_ = isset( $d['pin'] ) ? $d['pin'] : '';
			$v_   = VHG_Vi::vi( $sdt_ );
			/* ⚠️ MỘT CÂU LỖI cho cả "chưa có ví" lẫn "sai PIN" — nói tách ra là biến ô này
			   thành máy dò xem số nào đã nạp tiền. */
			if ( ! $v_ || ! VHG_Ma::pin_dung( $pin_, (string) $v_['pin_bam'] ) ) {
				self::tra( array( 'ok' => false, 'error' => 'Số điện thoại hoặc PIN chưa đúng.' ) );
				return;
			}
			$sd = VHG_Vi::so_du( $sdt_ );
			self::tra( array( 'ok' => true, 'so_du' => $sd,
				'so' => VHG_Vi::ds_so( $sdt_, 20 ), 'ghe' => self::ghe_tu_dia_chi( $d ) ) );
			return;
		}

		/* Tiêu số dư tại ghế. Hãm chung rổ với `dung` — cùng là đường biến tiền thành lượt chạy. */
		if ( 'tieu' === $viec ) {
			if ( self::bi_khoa( 'dung' ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút rồi thử lại.' ) );
				return;
			}
			self::dem( 'dung' );
			self::tra( VHG_Vi::tieu(
				isset( $d['sdt'] ) ? $d['sdt'] : '',
				isset( $d['pin'] ) ? $d['pin'] : '',
				isset( $d['menh_gia'] ) ? $d['menh_gia'] : 0,
				self::ghe_tu_dia_chi( $d ) ) );
			return;
		}

		self::tra( array( 'ok' => false, 'error' => 'Việc không hợp lệ.' ) );
	}

	/**
	 * Gắn tài khoản nhận tiền + nội dung + mã QR vào một đơn vừa đặt, rồi trả về.
	 *
	 * 🔴 DÙNG CHUNG cho đơn mua mã và đơn nạp ví. Hai chỗ cùng dựng một mã QR chuyển khoản là
	 *    kiểu lỗi đã cắn dự án này bốn lần (nội dung chuyển khoản thiếu tiền tố SEVQR, địa chỉ
	 *    tem viết hoa, cỡ vùng vẽ 58/70...): sửa một nơi, quên nơi kia, và nơi quên thì im lặng
	 *    nhận tiền vào hư không.
	 */
	private static function tra_don( $r ) {
		if ( empty( $r['ok'] ) ) { self::tra( $r ); return; }
		$tk = VHG_May::nhan_tien_cua( array() );
		if ( '' === $tk['so_tk'] ) {
			self::tra( array( 'ok' => false,
				'error' => 'Cửa hàng chưa khai tài khoản nhận tiền — báo nhân viên giúp em.' ) );
			return;
		}
		$r['so_tk']    = $tk['so_tk'];
		$r['ten_tk']   = $tk['ten_tk'];
		$r['bin']      = $tk['bin'];
		$r['noi_dung'] = VHG_QR::noi_dung_mua( $r['ma_don'] );
		/* Mã QR VietQR cho chính đơn này. Mức sửa lỗi L: chuỗi VietQR dài ~125 ký tự, mức L
		   cho ra 37x37 module thay vì 41x41 — vẽ trên màn điện thoại thì mỗi module to hơn,
		   mà mã hiện trên màn không phải chịu vết xước như tem in. */
		$qr_don = VHG_QR::cho_don_mua( $r['ma_don'], (int) $r['phai_tra'] );
		$r['qr'] = ! empty( $qr_don['ok'] )
			? VHG_QRVe::hang( VHG_QRVe::ma_tran( $qr_don['chuoi'], 'L' ) ) : array();
		self::tra( $r );
	}

	/**
	 * Ghế lấy từ địa chỉ (`?ghe=AMTP01`) — tem dán ở mỗi ghế mang mã ghế đó.
	 * ⚠️ Chỉ nhận mã CÓ THẬT trong bảng máy. Nhận bừa chuỗi trên địa chỉ là cho phép người ta
	 *    dựng một liên kết trỏ tới "ghế" không tồn tại rồi tiêu mã của mình vào hư không.
	 */
	public static function ghe_tu_dia_chi( $d = array() ) {
		$g = '';
		/* Ba dạng, cùng một ý: `/mua-ma/AMTP01` (ngắn nhất, cho mã QR trên màn ghế), `?ghe=AMTP01`
		   (tem in đời đầu), và trong thân gói (lượt gọi API). */
		$qv = (string) get_query_var( 'vhg_ghe' );
		if ( '' !== $qv ) { $g = $qv; }
		if ( '' === $g ) {
			$duong = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
			$duong = trim( (string) parse_url( $duong, PHP_URL_PATH ), '/' );
			if ( preg_match( '#(?:^|/)' . preg_quote( self::slug(), '#' )
					. '/([A-Za-z0-9]{1,20})/?$#', $duong, $m_d ) ) {
				$g = $m_d[1];
			}
		}
		if ( '' === $g && isset( $_GET['ghe'] ) ) { $g = sanitize_text_field( wp_unslash( $_GET['ghe'] ) ); }
		/* Thân gói dùng `ma_may` (như lượt `dung`) hoặc `ghe` — nhận cả hai, vì hai tên cho cùng
		   một thứ đã tồn tại sẵn trong cổng này và đổi tên bây giờ là gãy lượt `dung`. */
		if ( '' === $g && isset( $d['ma_may'] ) ) { $g = sanitize_text_field( (string) $d['ma_may'] ); }
		if ( '' === $g && isset( $d['ghe'] ) ) { $g = sanitize_text_field( (string) $d['ghe'] ); }
		$g = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $g ) );
		if ( '' === $g || ! VHG_May::may( $g ) ) { return ''; }
		return $g;
	}



	private static function css() {
		return <<<'CSS'
*{box-sizing:border-box}
/* Nền dùng CHUNG với trang nhân viên (cùng ô khai ảnh trong Cài đặt): hai trang của cùng một
   cửa hàng mà hai kiểu nền là khách tưởng mình đi lạc sang chỗ khác — mà đây lại là trang họ
   sắp chuyển tiền vào. */
body::before{content:"";position:fixed;inset:0;z-index:-2;
  background:radial-gradient(1100px 600px at 80% 6%,#3a2f1c 0%,transparent 62%),
             radial-gradient(900px 520px at 10% 94%,#1d2647 0%,transparent 60%),
             linear-gradient(160deg,#12141f 0%,#171a2e 46%,#0f1120 100%);
  background-size:cover;background-position:center}
body.co-anh::before{background-image:var(--nen);background-size:cover;background-position:center}
body::after{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:linear-gradient(180deg,rgba(9,10,18,.86) 0%,rgba(9,10,18,.74) 40%,rgba(9,10,18,.90) 100%)}
body{margin:0;background:#12141f;color:#e8ebff;min-height:100vh;
  font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:520px;margin:0 auto;padding:18px 14px 40px}

/* --- Đầu trang --- */
.hero{text-align:center;padding:14px 0 20px}
.hero .o{width:52px;height:52px;margin:0 auto 12px;display:flex;align-items:center;
  justify-content:center;border-radius:15px;font-size:26px;background:rgba(240,180,41,.13);
  border:1px solid rgba(240,180,41,.34)}
.hero h1{margin:0 0 6px;font-size:25px;line-height:1.25;letter-spacing:-.01em}
.hero .sub{color:#a79a7d;font-size:13px;letter-spacing:.1em;text-transform:uppercase}
/* Dải "giảm tới X%" — lý do duy nhất khách dừng lại đọc trang này. Nên nó to, và nó ở trên cùng. */
.deal{margin:16px 0 4px;padding:13px 16px;border-radius:14px;text-align:center;
  background:linear-gradient(135deg,rgba(240,180,41,.22),rgba(240,180,41,.08));
  border:1px solid rgba(240,180,41,.45)}
.deal b{color:#f0b429;font-size:19px}
.deal div{font-size:13px;color:#cfc3a6;margin-top:3px}

/* --- Thẻ gói --- */
.goi{display:grid;gap:12px;margin:18px 0}
.g{position:relative;padding:16px;border-radius:16px;cursor:pointer;text-align:left;
  background:rgba(22,25,40,.80);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
  border:1.5px solid rgba(255,255,255,.10);transition:border-color .15s,transform .1s}
.g:hover{border-color:rgba(240,180,41,.45)}
.g:active{transform:scale(.995)}
.g.chon{border-color:#f0b429;background:rgba(45,38,18,.86)}
/* Gói vượt quá số dư: làm mờ và BỎ HẲN con trỏ bấm. Chỉ làm mờ mà vẫn bấm được là khách bấm,
   chờ, rồi nhận một câu lỗi về điều trang đã biết từ trước. */
.g.het{opacity:.42;cursor:not-allowed}
.g .ten{font-weight:700;font-size:17px}
.g .mo{font-size:12.5px;color:#9aa0c2;margin-top:2px}
.g .gia{display:flex;align-items:baseline;gap:10px;margin-top:10px;flex-wrap:wrap}
.g .moi{font-size:24px;font-weight:800;color:#f0b429;font-variant-numeric:tabular-nums}
/* Giá gốc GẠCH NGANG: con số bị bỏ đi mới là thứ làm người ta thấy mình đang được lợi. */
.g .cu{text-decoration:line-through;color:#8d93c4;font-size:15px}
.g .pt{margin-left:auto;background:#f0b429;color:#221a00;font-weight:800;font-size:13px;
  padding:3px 9px;border-radius:99px}
.g .vip{position:absolute;top:-9px;right:12px;background:#f0b429;color:#221a00;font-weight:800;
  font-size:10px;letter-spacing:.1em;padding:3px 9px;border-radius:99px}

/* --- Khối chung --- */
.card{background:rgba(22,25,40,.78);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
  border:1px solid rgba(255,255,255,.10);border-radius:16px;padding:16px;margin:14px 0;
  box-shadow:0 8px 26px rgba(0,0,0,.34)}
.card h2{font-size:12px;margin:0 0 12px;letter-spacing:.12em;text-transform:uppercase;
  color:#f0b429;font-weight:700;padding-left:10px;border-left:3px solid #f0b429}
label{display:block;font-size:12.5px;color:#a79a7d;margin:12px 0 5px}
input,select{font:inherit;width:100%;border-radius:11px;padding:12px 13px;
  border:1px solid rgba(255,255,255,.16);background:rgba(10,12,22,.6);color:#e8ebff}
input:focus,select:focus{outline:none;border-color:#f0b429}
button{font:inherit;cursor:pointer;border-radius:11px;padding:13px 16px;font-weight:600;
  border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.07);color:#e8ebff}
button.chinh{background:#f0b429;border-color:#f0b429;color:#221a00;width:100%;font-size:17px;
  font-weight:800;padding:15px}
button.chinh:disabled{opacity:.5;cursor:not-allowed}
.mut{color:#9aa0c2;font-size:12.5px}
.err{color:#ff8087;font-size:13.5px;margin-top:10px;min-height:1px}
.tabs{display:flex;gap:6px;margin-bottom:14px}
.tabs button{flex:1;padding:11px 8px;font-size:14px}
.tabs button.on{background:#f0b429;border-color:#f0b429;color:#221a00;font-weight:700}

/* --- Ô chuyển khoản: to, rõ, có nút sao chép ---
   Nội dung chuyển khoản là thứ DUY NHẤT nối tiền với đơn. Gõ sai một ký tự là tiền vào tài
   khoản mà không ai biết của đơn nào. Nên nó không được nằm trong chữ nhỏ. */
.ck{display:flex;align-items:center;gap:10px;padding:12px 13px;border-radius:12px;margin:9px 0;
  background:rgba(10,12,22,.55);border:1px solid rgba(255,255,255,.12)}
.ck .nh{font-size:11px;color:#a79a7d;letter-spacing:.06em;text-transform:uppercase}
.ck .gt{font-size:17px;font-weight:700;word-break:break-all;font-variant-numeric:tabular-nums}
.ck .gt.nho{font-size:15px}
.ck button{padding:9px 12px;font-size:13px;flex:none}
.ck.nhan{border-color:rgba(240,180,41,.5);background:rgba(45,38,18,.6)}
.ck.nhan .gt{color:#f0b429}
.cho{text-align:center;padding:16px;color:#f0b429;font-weight:600}
/* Ô mã QR: nền TRẮNG kín, bo góc nhẹ. Không tô nền tối phía sau mã — bộ dò cần tương phản
   trắng-đen, đặt mã lên nền tối là mất vùng lặng dù có chừa chỗ. */
.qr-hop{background:#fff;border-radius:14px;padding:10px;margin:2px 0 10px;text-align:center}
.qr-hop canvas{max-width:100%;height:auto;display:block;margin:0 auto;image-rendering:pixelated}
.qr-nut{display:flex;gap:8px;justify-content:center;margin-bottom:2px}
.qr-nut button{padding:11px 18px}

/* --- Mã đã phát --- */
.ma{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:9px 0;
  padding:14px;border-radius:13px;background:rgba(45,38,18,.7);border:1px solid rgba(240,180,41,.45)}
.ma .m{font-size:22px;font-weight:800;letter-spacing:.08em;color:#f0b429;font-variant-numeric:tabular-nums}
.ma .g{font-size:12px;color:#cfc3a6;background:none;border:0;padding:0}
.ma.het{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.12)}
.ma.het .m{color:#8d93c4;text-decoration:line-through;font-size:19px}
.ok{background:rgba(18,53,31,.7);border:1px solid #2f6b45;border-radius:13px;padding:14px;
  color:#8ff0b0;margin:12px 0}
CSS;
	}


	private static function js() {
		return <<<'JS'
(function(){
var API = window.VHG_SHOP, GHE = window.VHG_GHE || '', TEN = window.VHG_TEN || 'POSH Massage';
var D = null, CHON = null, SL = 1, TAB = 'mua', DON = null, hen = null, ban = false;
/* NAP = chỉ số gói nạp đang chọn; VI = số dư vừa tra được. Để riêng CHON của gói mã: hai luồng
   mua khác nhau, dùng chung một biến là chọn bên này lại sáng nút bên kia. */
var NAP = null, VI = null;
var app = document.getElementById('app');

/* "còn 4 ngày 3 giờ" — câu người đọc là hiểu. Cùng cách nói với VHG_Ma::doc_con_cho() bên máy
   chủ; hai nơi nói khác nhau về cùng một khoảng thời gian là khách tưởng hệ thống mâu thuẫn. */
/* Nhớ SỐ ĐIỆN THOẠI cho lần sau — và CHỈ số điện thoại.
   🔴 KHÔNG nhớ PIN. Điện thoại để quên trên ghế, hoặc đưa cho bạn mượn quét, là người cầm máy
      tiêu sạch ví. Nhớ số thì lần sau chỉ phải gõ 4 chữ số — nhanh gần bằng, mà mất máy thì
      vẫn không mất tiền.
   ⚠️ Bọc try/catch: trình duyệt ở chế độ riêng tư ném lỗi khi chạm localStorage, và một lỗi ở
      đây làm chết cả trang chỉ vì một tiện ích nhỏ. */
function nhoSdt(v){
  try {
    if (v === undefined) return localStorage.getItem('vhg_sdt') || '';
    localStorage.setItem('vhg_sdt', String(v || ''));
  } catch (e) {}
  return '';
}

function docCho(giay){
  var g = Math.max(0, Number(giay) || 0);
  var ngay = Math.floor(g / 86400), gio = Math.floor((g % 86400) / 3600);
  if (ngay > 0) return ngay + ' ngày' + (gio > 0 ? ' ' + gio + ' giờ' : '');
  if (gio > 0)  return gio + ' giờ';
  return Math.max(1, Math.ceil(g / 60)) + ' phút';
}

function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){
  return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
function tien(n){ return (Number(n)||0).toLocaleString('vi-VN') + 'đ'; }

function goi(viec, d, xong){
  d = d || {};
  var x = new XMLHttpRequest();
  x.open('POST', API + (API.indexOf('?')<0?'?':'&') + 'api=' + viec, true);
  x.setRequestHeader('Content-Type','application/json');
  x.onreadystatechange = function(){
    if (x.readyState !== 4) return;
    var r = null;
    try { r = JSON.parse(x.responseText); } catch(e){}
    /* Máy chủ trả rác (tường lửa hosting chèn trang chặn) KHÔNG được thành một câu báo lỗi khó
       hiểu — khách đang định trả tiền, họ cần biết nên làm gì tiếp. */
    if (!r) r = { ok:false, error:'Mạng đang chập chờn. Thử lại giúp em, hoặc gọi nhân viên.' };
    xong(r);
  };
  x.send(JSON.stringify(d));
}

/* Sao chép — có đường lui. `navigator.clipboard` chỉ chạy trên HTTPS và trên một số trình duyệt;
   không có đường lui thì nút bấm không làm gì cả và khách không hiểu vì sao. */
function chep(txt, nut){
  function xong(){ var cu = nut.textContent; nut.textContent = '✓ Đã chép';
    setTimeout(function(){ nut.textContent = cu; }, 1400); }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(txt).then(xong, function(){ tayChep(txt, xong); });
  } else { tayChep(txt, xong); }
}
function tayChep(txt, xong){
  var o = document.createElement('textarea');
  o.value = txt; o.style.position='fixed'; o.style.opacity='0';
  document.body.appendChild(o); o.select();
  try { document.execCommand('copy'); xong(); } catch(e){ prompt('Chép dòng này:', txt); }
  document.body.removeChild(o);
}

function dau(){
  return '<div class="hero"><div class="o">🎁</div>'
    + '<h1>Mua mã giảm giá</h1>'
    + '<div class="sub">' + esc(TEN) + '</div></div>';
}

function tab(){
  /* Tab "Nạp ví" chỉ hiện khi anh Thắng CÓ khai gói nạp. Chưa khai mà vẫn hiện là khách bấm
     vào một trang trống — tệ hơn hẳn việc không có tab. */
  var coNap = !!(D && D.goi_nap && D.goi_nap.length);
  var coMa  = !D || D.ban_ma !== 0;          // chưa tải xong thì cứ hiện, tránh tab nhấp nháy
  return '<div class="tabs">'
    + (coNap ? '<button data-tab="nap"' + (TAB==='nap'?' class="on"':'') + '>Nạp ví</button>' : '')
    + (coMa ? '<button data-tab="mua"' + (TAB==='mua'?' class="on"':'') + '>Mua mã</button>' : '')
    + '<button data-tab="cua-toi"' + (TAB==='cua-toi'?' class="on"':'') + '>Của tôi</button>'
    + '<button data-tab="dung"' + (TAB==='dung'?' class="on"':'') + '>Dùng tại ghế</button>'
    + '</div>';
}

function ve(){
  var h = '<div class="wrap">' + dau() + tab();
  if (TAB === 'nap')      h += veNap();
  else if (TAB === 'mua') h += veMua();
  else if (TAB === 'cua-toi') h += veCuaToi();
  else                    h += veDung();
  app.innerHTML = h + '</div>';
  noi();
}

// ------------------------------------------------------------------ nạp ví
/* ============================================================================================
 * GÓI NẠP: NÓI "ĐƯỢC THÊM BAO NHIÊU", KHÔNG NÓI "GIẢM BAO NHIÊU %".
 *
 * Nạp 100k được 120k. Về số học đó là giảm 16,7% — nhưng không ai nghĩ theo hướng ấy. Khách
 * nghĩ "bỏ ra 100 được 120", tức là LỢI 20. Hiện con số khách dùng để quyết định, không hiện
 * con số đúng về mặt kế toán.
 *
 * 🔴 VÀ NÓI HẠN CHỜ TRƯỚC KHI HỌ TRẢ TIỀN. Tiền nạp có hạn chờ y như mã mua trước, cùng một lý
 *    do. Chỉ hiện ra sau khi đã trả là khách thấy mình bị gạt — đúng lúc họ đang ngồi trên ghế
 *    và ghế không chạy.
 * ============================================================================================ */
function veNap(){
  if (DON) return veTraTien();
  if (!D || !D.goi_nap || !D.goi_nap.length) {
    return '<div class="card"><p class="mut">Hiện chưa mở bán gói nạp.</p></div>';
  }
  var cho = (D.cho_ngay || 0), h = '';
  var loiMax = 0;
  D.goi_nap.forEach(function(g){ if (g.loi_pt > loiMax) loiMax = g.loi_pt; });
  h += '<div class="deal"><b>Nạp càng nhiều, lợi càng lớn — tới ' + loiMax + '%</b>'
    + '<div>Số dư dùng được ở <b>bất kỳ ghế nào</b>, tiêu lẻ từng lượt, không hết hạn</div></div>';
  if (cho > 0) {
    h += '<div class="card" style="border-color:rgba(240,180,41,.45)">'
      + '<b style="color:#f0b429">⏳ Số dư nạp dùng được sau ' + cho + ' ngày.</b>'
      + '<div class="mut" style="margin-top:5px">Đây là điều kiện của phần được tặng thêm: nạp '
      + 'trước thì lợi hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế.</div></div>';
  }
  h += '<div class="goi">';
  D.goi_nap.forEach(function(g, i){
    h += '<div class="g' + (NAP === i ? ' chon' : '') + '" data-nap="' + i + '">'
      + '<span class="vip">+' + g.loi_pt + '%</span>'
      + '<div class="ten">Nạp ' + tien(g.nap) + '</div>'
      + '<div class="mo">được thêm ' + tien(g.them) + '</div>'
      + '<div class="gia"><span class="moi">' + tien(g.nhan) + '</span>'
      + '<span class="cu">' + tien(g.nap) + '</span></div></div>';
  });
  h += '</div>';
  h += '<div class="card"><h2>Ví của anh/chị</h2>'
    + '<p class="mut" style="margin:0 0 10px">Số dư gắn với <b>số điện thoại</b>. Lần sau tới, '
    + 'nhập lại số này và PIN là tiêu tiếp.</p>'
    + '<label>Số điện thoại</label>'
    + '<input id="n-sdt" type="tel" inputmode="numeric" placeholder="0909 123 456" autocomplete="tel">'
    /* ⚠️ Ví ĐÃ CÓ thì đây là PIN CŨ, không phải PIN mới — nói rõ, không thì khách gõ một PIN
       khác rồi bị từ chối mà không hiểu vì sao. */
    + '<label>PIN 4 số — ví đã có thì nhập <b>đúng PIN cũ</b></label>'
    + '<input id="n-pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    + '<label>Số căn cước — <b>không bắt buộc</b>, chỉ để lấy lại PIN nếu quên</label>'
    + '<input id="n-cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">'
    + '<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>.</div>'
    + '<div id="n-tong" class="mut" style="margin:12px 0 4px"></div>'
    + '<button id="n-mua" class="chinh">Nạp ngay</button>'
    + '<div class="err" id="e"></div></div>';
  return h;
}

// ------------------------------------------------------------------ mua
function veMua(){
  if (DON) return veTraTien();
  if (!D) return '<div class="card"><p class="mut">Đang tải bảng giá…</p></div>';

  var max = 0;
  D.goi.forEach(function(g){ if (g.giam_pt > max) max = g.giam_pt; });
  var h = '';
  var cho = (D.cho_ngay || 0);
  if (max > 0) {
    h += '<div class="deal"><b>Giảm tới ' + max + '%</b><div>'
      + (cho > 0
          ? 'Mua trước, dùng sau <b>' + cho + ' ngày</b> — ở bất kỳ ghế nào, không hết hạn'
          : 'Mua hôm nay, dùng bất cứ lúc nào, ở bất kỳ ghế nào')
      + '</div></div>';
  }
  /* 🔴 NÓI ĐIỀU KIỆN CHỜ TRƯỚC KHI KHÁCH TRẢ TIỀN, và nói to. Đây là thứ dễ làm khách thấy mình
     bị gạt nhất nếu chỉ hiện ra lúc họ đã trả xong rồi quét không được. Giảm giá là để đổi lấy
     việc trả tiền trước — đổi được cái gì thì phải nói rõ từ đầu. */
  if (cho > 0) {
    h += '<div class="card" style="border-color:rgba(240,180,41,.45)">'
      + '<b style="color:#f0b429">⏳ Mã dùng được sau ' + cho + ' ngày kể từ lúc mua.</b>'
      + '<div class="mut" style="margin-top:5px">Đây là điều kiện của giá đã giảm: mua trước thì '
      + 'rẻ hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế với giá gốc.</div></div>';
  }

  h += '<div class="goi">';
  D.goi.forEach(function(g, i){
    var co = CHON === i;
    h += '<div class="g' + (co?' chon':'') + '" data-goi="' + i + '">'
      + (g.vip ? '<span class="vip">VVIP</span>' : '')
      + '<div class="ten">' + esc(g.ten || tien(g.menh_gia)) + '</div>'
      + (g.mo_ta ? '<div class="mo">' + esc(g.mo_ta) + '</div>' : '')
      + '<div class="gia"><span class="moi">' + tien(g.gia_ban) + '</span>'
      + (g.giam_pt > 0 ? '<span class="cu">' + tien(g.menh_gia) + '</span>'
          + '<span class="pt">-' + g.giam_pt + '%</span>' : '')
      + '</div></div>';
  });
  h += '</div>';

  h += '<div class="card"><h2>Thông tin nhận mã</h2>'
    + '<label>Số điện thoại</label>'
    + '<input id="sdt" type="tel" inputmode="numeric" placeholder="0909 123 456" autocomplete="tel">'
    /* PIN để lần sau tra lại mã. Nói rõ CÔNG DỤNG ngay cạnh ô, không nhét vào chữ nhỏ: khách
       không hiểu để làm gì thì gõ bừa, rồi hôm sau không tra được mã đã mua. */
    + '<label>Đặt PIN 4 số — để lần sau tra lại mã của mình</label>'
    + '<input id="pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    /* Căn cước KHÔNG bắt buộc, và nói rõ chỉ giữ 4 số cuối. Bắt buộc căn cước để mua một lượt
       massage 85.000đ là mất khách ngay bước đầu; còn giấu chuyện chỉ giữ 4 số cuối thì khách
       ngại khai, mà đó lại đúng là thứ làm họ yên tâm. */
    + '<label>Số căn cước — <b>không bắt buộc</b>, chỉ để lấy lại PIN nếu quên</label>'
    + '<input id="cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">'
    + '<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>, phần còn lại '
    + 'không được ghi lại ở đâu cả.</div>'
    + '<label>Số lượng</label>'
    + '<input id="sl" type="number" min="1" max="10" value="' + SL + '">'
    + '<div id="tong" class="mut" style="margin:12px 0 4px"></div>'
    + '<button id="mua" class="chinh">Mua ngay</button>'
    + '<div class="err" id="e"></div></div>';
  return h;
}

function veTraTien(){
  var laNap = (DON.loai === 'nap');
  var h = '<div class="card"><h2>'
    + (laNap ? 'Chuyển khoản để nạp ví' : 'Chuyển khoản để nhận mã') + '</h2>';
  /* Nhắc lại NHẬN ĐƯỢC BAO NHIÊU ngay trên mã QR. Khách đang ở bước rút ví ra trả tiền — đó
     đúng là lúc con số "được 120.000đ" cần đứng trước mắt, không phải lúc họ mới chọn gói. */
  if (laNap) {
    h += '<div class="ok" style="margin:0 0 12px">Trả <b>' + tien(DON.phai_tra) + '</b> — nhận '
      + '<b style="color:#f0b429">' + tien(DON.nhan_tien) + '</b> vào ví'
      + (DON.them > 0 ? ' (được thêm ' + tien(DON.them) + ')' : '') + '.</div>';
  }
  /* MÃ QR TRƯỚC, chữ chép tay sau. Quét (hoặc chọn ảnh từ thư viện) nhanh và không gõ nhầm được;
     phần chữ là đường dự phòng cho ai muốn gõ tay. */
  if (DON.qr && DON.qr.length) {
    h += '<div class="qr-hop"><canvas id="qr-canvas"></canvas></div>'
      + '<div class="qr-nut">'
      + '<button id="qr-tai">⬇ Tải ảnh mã QR</button>'
      + '</div>'
      + '<p class="mut" style="margin:6px 0 14px;text-align:center">Quét bằng app ngân hàng. '
      + 'Đang xem trên chính máy này thì bấm <b>Tải ảnh mã QR</b>, rồi trong app ngân hàng chọn '
      + '<b>quét từ thư viện ảnh</b>.</p>';
  }
  h += '<p class="mut" style="margin:0 0 12px">Hoặc chuyển tay: đúng số tiền, '
    + '<b style="color:#f0b429">đúng nội dung</b> bên dưới. Mã hiện ra ngay tại đây khi tiền về.</p>'
    + o_ck('Ngân hàng / Số tài khoản', DON.so_tk, DON.so_tk, '')
    + (DON.ten_tk ? '<div class="mut" style="margin:-4px 0 8px 2px">' + esc(DON.ten_tk) + '</div>' : '')
    + o_ck('Số tiền', tien(DON.phai_tra), String(DON.phai_tra), '')
    /* Ô nội dung tô vàng: đây là thứ sai một ký tự là tiền lạc, còn hai ô trên sai thì ngân hàng
       tự báo lỗi. Hai loại rủi ro khác hẳn nhau nên trông cũng phải khác nhau. */
    + o_ck('Nội dung chuyển khoản', DON.noi_dung, DON.noi_dung, ' nhan')
    + '<div class="cho" id="cho">⏳ Đang chờ tiền về…</div>'
    + '<button id="huy" style="width:100%">Đổi gói khác</button>'
    + '<div class="err" id="e"></div></div>';
  return h;
}

/* Vẽ ma trận lên canvas. Canvas chứ không phải SVG vì canvas XUẤT RA PNG ĐƯỢC — và thư viện ảnh
   của điện thoại chỉ hiện ảnh raster, tải về một tệp SVG thì app ngân hàng không thấy đâu mà chọn.
   ⚠️ VÙNG LẶNG 4 Ô mỗi bên, và nền TRẮNG kín. Thiếu một trong hai là nhiều máy quét không nhận ra
      mã — kiểu hỏng chỉ lộ ở một số máy, tức là lộ ở khách chứ không lộ lúc mình thử. */
function veQR(hang, o, px){
  var n = hang.length, lang = 4, tong = (n + lang * 2) * px;
  o.width = tong; o.height = tong;
  var c = o.getContext('2d');
  c.fillStyle = '#fff'; c.fillRect(0, 0, tong, tong);
  c.fillStyle = '#000';
  for (var y = 0; y < n; y++) {
    for (var x = 0; x < n; x++) {
      if (hang[y].charAt(x) === '1') c.fillRect((x + lang) * px, (y + lang) * px, px, px);
    }
  }
}

function o_ck(nhan, hien, chep_, lop){
  return '<div class="ck' + (lop||'') + '"><div style="flex:1;min-width:0">'
    + '<div class="nh">' + esc(nhan) + '</div>'
    + '<div class="gt' + (String(hien).length > 18 ? ' nho' : '') + '">' + esc(hien) + '</div></div>'
    + '<button data-chep="' + esc(chep_) + '">Chép</button></div>';
}

// ------------------------------------------------------------------ mã của tôi
function veCuaToi(){
  /* Ví ĐỨNG TRƯỚC mã: ai đã nạp thì số dư là thứ họ mở trang này để xem. Chỉ hiện khi cửa hàng
     có bán gói nạp — chưa bán mà vẫn hiện là bày ra một ô không ai dùng được. */
  var hv = '';
  if (D && D.goi_nap && D.goi_nap.length) {
    hv = '<div class="card"><h2>Số dư ví</h2>'
      + '<p class="mut" style="margin:0 0 10px">Nhập số điện thoại và PIN của ví.</p>'
      + '<label>Số điện thoại</label>'
      + '<input id="v-sdt" type="tel" inputmode="numeric" placeholder="0909 123 456">'
      + '<label>PIN 4 số</label>'
      + '<input id="v-pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
      + '<button id="v-xem" class="chinh" style="margin-top:14px">Xem số dư</button>'
      + '<div class="err" id="v-e"></div><div id="v-kq"></div></div>';
  }
  return hv + '<div class="card"><h2>Mã của tôi</h2>'
    + '<p class="mut" style="margin:0 0 10px">Nhập số điện thoại và PIN đã đặt lúc mua.</p>'
    /* 🔴 QUÊN PIN LÀ CHUYỆN SẼ XẢY RA, không phải nếu. Khách đặt PIN một lần rồi ba tuần sau mới
       quay lại. Không nói trước lối ra thì họ gõ mười lần, bị hãm, rồi bỏ đi — mang theo cái mã
       đã trả tiền.
       ⚠️ KHÔNG làm chức năng "quên PIN" tự phục hồi trên trang này: thứ duy nhất trang biết về
          khách là số điện thoại, mà số điện thoại thì người khác đoán được. Tự đặt lại PIN bằng
          một thứ đoán được là gỡ đúng cái khoá vừa lắp. Nhân viên tra hộ mới là lối đúng — họ
          nhìn thấy mặt khách. */
    + '<p class="mut" style="margin:-4px 0 10px">Quên PIN thì <b>gọi nhân viên</b> — nhân viên tra '
    + 'được bằng số điện thoại và đọc mã giúp anh/chị.</p>'
    + '<label>Số điện thoại</label>'
    + '<input id="t-sdt" type="tel" inputmode="numeric" placeholder="0909 123 456">'
    + '<label>PIN 4 số</label>'
    + '<input id="t-pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    + '<button id="t-xem" class="chinh" style="margin-top:14px">Xem mã của tôi</button>'
    + '<div class="err" id="e"></div><div id="kq"></div></div>'
    /* Ô lấy lại PIN để RIÊNG một khối, dưới khối tra: người nhớ PIN không phải nhìn thấy nó. */
    + '<div class="card"><h2>Quên PIN?</h2>'
    + '<p class="mut" style="margin:0 0 10px">Nếu lúc mua có khai số căn cước thì tự đặt PIN mới '
    + 'được. Không khai thì gọi nhân viên — nhân viên tra bằng số điện thoại.</p>'
    + '<label>Số điện thoại</label>'
    + '<input id="q-sdt" type="tel" inputmode="numeric" placeholder="0909 123 456">'
    + '<label>Số căn cước (đã khai lúc mua)</label>'
    + '<input id="q-cc" type="tel" inputmode="numeric" autocomplete="off">'
    + '<label>PIN mới (4 số)</label>'
    + '<input id="q-pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    + '<button id="q-ok" style="width:100%;margin-top:14px">Đặt PIN mới</button>'
    + '<div class="err" id="q-e"></div></div>';
}

// ------------------------------------------------------------------ dùng mã
/* ============================================================================================
 * DÙNG MÃ — GHẾ DO CÁI TEM QUYẾT ĐỊNH, KHÔNG DO KHÁCH CHỌN.
 *
 * 🔴 Anh Thắng 23/08/2026: *"khách hàng rất dễ chọn lộn ghế, vì số lượng ghế rất nhiều"*. Đúng.
 *    Bản trước đưa ra ô chọn liệt kê mọi ghế trong hệ thống — với 26 ghế thì đó là một cái bẫy:
 *    khách chọn lộn là mã của họ chạy cho GHẾ NGƯỜI KHÁC. Mất mã, mất cả buổi, và không ai
 *    chứng minh được chuyện gì vừa xảy ra.
 *
 *    Cái tem dán trên ghế đã mang mã ghế đó rồi. Quét tem = đã nói "tôi đang ngồi ghế này",
 *    chính xác hơn mọi ô chọn. Nên: KHÔNG còn ô chọn ghế. Không biết ghế thì KHÔNG cho dùng mã.
 *
 * ⚠️ TỪ CHỐI chứ đừng đoán. Đoán một ghế "gần đúng" là cho không một lượt ở ghế sai, trong khi
 *    khách thật vẫn ngồi đó chờ. Chặn ở đây rồi chỉ họ đi quét tem là đường ngắn nhất về chỗ đúng.
 * ============================================================================================ */
function veDung(){
  if (!GHE) {
    return '<div class="card"><h2>Dùng mã cho ghế</h2>'
      + '<div class="ck nhan" style="display:block"><div class="nh">Chưa biết ghế nào</div>'
      + '<div style="margin-top:6px;line-height:1.5">Hãy <b>quét mã QR dán trên chính cái ghế '
      + 'anh/chị đang ngồi</b> — mã đó cho hệ thống biết đúng ghế, khỏi phải chọn.</div></div>'
      + '<p class="mut">Trang này cố ý <b>không</b> cho chọn ghế từ danh sách: chọn lộn là mã '
      + 'chạy cho người khác, mà mã thì mất rồi. Tem trên ghế mờ hay bong thì gọi nhân viên — '
      + 'nhân viên chạy mã giúp được.</p>'
      + '<p class="mut">Vẫn mua thêm mã được ở mục <b>Mua mã</b> — mua thì không cần biết ghế.</p>'
      + '</div>';
  }
  /* ⚠️ Ghế hiện MỘT LẦN ở đầu trang, không lặp lại trong từng khối: khách chỉ cần biết "mình
     đang ở ghế nào" đúng một lần, lặp lại là nhiễu. */
  var h = '<div class="ck nhan"><div style="flex:1;min-width:0"><div class="nh">Ghế đang ngồi</div>'
    + '<div class="gt">' + esc(GHE) + '</div></div></div>'
    + '<p class="mut" style="margin:6px 0 14px">Không đúng ghế này thì quét lại mã QR trên ghế '
    + 'mình đang ngồi.</p>';

  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * TRẢ BẰNG SỐ DƯ — ĐỨNG TRƯỚC, VÀ LÀ THẺ BẤM.
   *
   * Anh Thắng 23/08/2026: *"Quét qr. cho gói sử dụng. Bấm. Hệ thống sẽ trừ thẳng tiền trong gói
   * đang còn"*. Ba bước, không có bước nào là "mở danh sách rồi cuộn tìm".
   *
   * Bản trước dùng một ô chọn `<select>` + hai ô nhập + một nút. Trên điện thoại, ô chọn là một
   * cửa sổ bật lên che hết màn — khách phải rời khỏi thứ đang nhìn để chọn, rồi quay lại. Thẻ
   * bấm thì thấy hết, chạm một cái là xong, và giống hệt màn hình trên chính cái ghế họ đang
   * ngồi — đỡ phải học hai giao diện cho cùng một việc.
   *
   * 🔴 SỐ PHÚT LẤY THEO GHẾ NÀY, không phải tỉ lệ chung — xem VHG_Ma::ds_menh_gia($ma_may).
   * ══════════════════════════════════════════════════════════════════════════════════════════ */
  if (D && D.goi_nap && D.goi_nap.length) {
    h += '<div class="card"><h2>Trả bằng số dư ví</h2>';
    if (!VI) {
      h += '<p class="mut" style="margin:0 0 10px">Nhập số điện thoại và PIN để thấy số dư và '
        + 'các gói bấm được.</p>'
        + '<label>Số điện thoại</label>'
        + '<input id="t-vsdt" type="tel" inputmode="numeric" placeholder="0909 123 456" value="'
        + esc(nhoSdt()) + '">'
        + '<label>PIN 4 số</label>'
        + '<input id="t-vpin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
        + '<button id="t-mo" class="chinh" style="margin-top:14px">Mở ví</button>'
        + '<div class="err" id="t-e"></div>';
    } else {
      var sd = VI.so_du || {};
      h += '<div class="ok" style="margin:0 0 12px">Số dư tiêu được: '
        + '<b style="color:#f0b429;font-size:18px">' + tien(sd.dung || 0) + '</b>'
        + (sd.cho > 0
            ? '<br>⏳ ' + tien(sd.cho) + ' đang trong hạn chờ'
              + (sd.con_cho > 0 ? ' — dùng được sau ' + docCho(sd.con_cho) : '')
            : '')
        + '</div>'
        + '<p class="mut" style="margin:0 0 10px"><b>Bấm một gói</b> — hệ thống trừ thẳng số dư '
        + 'và ghế chạy ngay.</p>'
        + '<div class="goi">';
      (D.goi || []).forEach(function(g){
        /* Gói quá số dư thì làm mờ và KHÔNG cho bấm — để bấm rồi báo lỗi là bắt khách phát hiện
           điều mà trang đã biết trước. */
        var du = (sd.dung || 0) >= g.menh_gia;
        h += '<div class="g' + (du ? '' : ' het') + '"' + (du ? ' data-tieu="' + g.menh_gia + '"' : '')
          + '>'
          + (g.vip ? '<span class="vip">VVIP</span>' : '')
          + '<div class="ten">' + esc(g.ten || tien(g.menh_gia)) + '</div>'
          + (g.phut > 0 ? '<div class="mo">' + g.phut + ' phút</div>' : '')
          + '<div class="gia"><span class="moi">' + tien(g.menh_gia) + '</span></div>'
          + (du ? '' : '<div class="mo" style="color:#b32d2e">chưa đủ số dư</div>')
          + '</div>';
      });
      h += '</div><button id="t-thoat" style="width:100%;margin-top:6px">Đổi số điện thoại</button>'
        + '<div class="err" id="t-e"></div><div id="t-kq"></div>';
    }
    h += '</div>';
  }

  /* Khối MÃ đứng sau, và chỉ hiện khi cửa hàng còn bán mã. Đã bán mã trước đây thì mã cũ vẫn
     dùng được — nên khối này CÒN kể cả khi đã tắt bán thêm. */
  h += '<div class="card"><h2>Hoặc dùng mã giảm giá</h2>'
    + '<label>Mã giảm giá</label>'
    + '<input id="d-ma" placeholder="ABCD-EFGH" autocapitalize="characters" autocomplete="off">'
    + '<button id="d-ok" class="chinh" style="margin-top:14px">Dùng mã, chạy ghế</button>'
    + '<div class="err" id="e"></div><div id="kq"></div></div>';

  return h;
}

/* Khối hiện số dư — dùng ở tab "Của tôi". Hiện CẢ HAI cột: tiêu được và đang chờ. Gộp lại
   thành một con số là khách thấy có tiền mà ghế không chạy, rồi tưởng hệ thống nuốt tiền. */
function veSoDu(r){
  var sd = r.so_du || {};
  var h = '<div class="ok" style="margin-top:12px">Tiêu được ngay: '
    + '<b style="color:#f0b429;font-size:18px">' + tien(sd.dung || 0) + '</b>';
  if (sd.cho > 0) {
    h += '<br>⏳ Đang trong hạn chờ: <b>' + tien(sd.cho) + '</b>'
      + (sd.con_cho > 0 ? ' — dùng được sau ' + docCho(sd.con_cho) : '');
  }
  if (sd.khoa) h += '<br><b>Ví đang tạm khoá — anh/chị báo nhân viên giúp.</b>';
  h += '</div>';
  var so = r.so || [];
  if (so.length) {
    h += '<div class="mut" style="margin:12px 0 6px"><b>Gần đây</b></div>';
    so.forEach(function(d){
      var t = Number(d.thay_doi) || 0;
      if (!t) return;
      h += '<div class="ma"><div><div class="m" style="font-size:15px">'
        + (t > 0 ? '+' : '') + tien(t) + '</div>'
        + '<div class="g">' + esc(d.ghi_chu || d.loai) + ' · ' + esc(String(d.luc||'').substr(0,16))
        + '</div></div></div>';
    });
  }
  return h;
}

// ------------------------------------------------------------------ nối nút
function tongTien(){
  var o = document.getElementById('tong');
  if (!o || CHON === null || !D) { if (o) o.textContent = ''; return; }
  var g = D.goi[CHON];
  var n = Math.max(1, Math.min(10, Number((document.getElementById('sl')||{}).value) || 1));
  o.innerHTML = 'Phải trả: <b style="color:#f0b429;font-size:18px">' + tien(g.gia_ban * n) + '</b>'
    + (g.giam_pt > 0 ? ' — tiết kiệm ' + tien((g.menh_gia - g.gia_ban) * n) : '');
}

function noi(){
  [].forEach.call(document.querySelectorAll('[data-tab]'), function(b){
    b.onclick = function(){ TAB = b.getAttribute('data-tab'); ve(); };
  });
  [].forEach.call(document.querySelectorAll('[data-goi]'), function(b){
    b.onclick = function(){ CHON = Number(b.getAttribute('data-goi')); ve(); };
  });
  [].forEach.call(document.querySelectorAll('[data-nap]'), function(b){
    b.onclick = function(){ NAP = Number(b.getAttribute('data-nap')); ve(); };
  });

  var nTong = document.getElementById('n-tong');
  if (nTong) {
    nTong.innerHTML = (NAP === null || !D.goi_nap[NAP])
      ? 'Chọn một gói nạp phía trên.'
      : 'Trả: <b style="color:#f0b429;font-size:18px">' + tien(D.goi_nap[NAP].nap) + '</b>'
        + ' — nhận <b>' + tien(D.goi_nap[NAP].nhan) + '</b> vào ví';
  }
  var nMua = document.getElementById('n-mua');
  if (nMua) nMua.onclick = function(){
    var e = document.getElementById('e');
    if (NAP === null) { e.textContent = 'Chọn một gói nạp trước nhé.'; return; }
    var sdt = (document.getElementById('n-sdt').value || '').trim();
    var pin = (document.getElementById('n-pin').value || '').trim();
    if (!/^\d{4}$/.test(pin)) { e.textContent = 'PIN phải gồm đúng 4 chữ số.'; return; }
    if (ban) return;
    ban = true; nMua.disabled = true; e.textContent = 'Đang tạo đơn…';
    goi('dat_nap', { sdt: sdt, pin: pin, cc: (document.getElementById('n-cc')||{}).value || '',
                     nap: D.goi_nap[NAP].nap },
      function(r){
        ban = false; nMua.disabled = false;
        if (!r.ok) { e.textContent = r.error || 'Không tạo được đơn nạp.'; return; }
        DON = r; ve(); soiDon();
      });
  };

  var vXem = document.getElementById('v-xem');
  if (vXem) vXem.onclick = function(){
    var e = document.getElementById('v-e'), kq = document.getElementById('v-kq');
    var sdt = (document.getElementById('v-sdt').value || '').trim();
    var pin = (document.getElementById('v-pin').value || '').trim();
    if (ban) return;
    ban = true; vXem.disabled = true; e.textContent = ''; kq.innerHTML = 'Đang tra…';
    goi('vi', { sdt: sdt, pin: pin }, function(r){
      ban = false; vXem.disabled = false;
      if (!r.ok) { kq.innerHTML = ''; e.textContent = r.error || 'Không tra được.'; return; }
      VI = r; kq.innerHTML = veSoDu(r);
    });
  };

  /* Mở ví: tra số dư rồi vẽ lại màn thành các thẻ bấm được. PIN giữ trong biến của trang cho
     lượt bấm ngay sau đó — không ghi ra đâu cả, đóng trang là mất. */
  var tMo = document.getElementById('t-mo');
  if (tMo) tMo.onclick = function(){
    var e = document.getElementById('t-e');
    var sdt = (document.getElementById('t-vsdt').value || '').trim();
    var pin = (document.getElementById('t-vpin').value || '').trim();
    if (!/^\d{4}$/.test(pin)) { e.textContent = 'PIN phải gồm đúng 4 chữ số.'; return; }
    if (ban) return;
    ban = true; tMo.disabled = true; e.textContent = 'Đang mở ví…';
    goi('vi', { sdt: sdt, pin: pin }, function(r){
      ban = false; tMo.disabled = false;
      if (!r.ok) { e.textContent = r.error || 'Không mở được ví.'; return; }
      nhoSdt(sdt); VI = r; VI.pin = pin; ve();
    });
  };
  var tThoat = document.getElementById('t-thoat');
  if (tThoat) tThoat.onclick = function(){ VI = null; ve(); };

  /* 🔴 BẤM MỘT THẺ LÀ CHẠY. Không có bước xác nhận: khách đã ngồi lên ghế, đã mở ví, đã nhìn
     thấy số dư và giá — thêm một hộp "anh/chị chắc chưa?" chỉ làm chậm đúng thứ anh Thắng bảo
     phải nhanh. Bấm nhầm thì mất một lượt, và một lượt vẫn chạy cho chính người đang ngồi đó. */
  [].forEach.call(document.querySelectorAll('[data-tieu]'), function(b){
    b.onclick = function(){
      if (ban || !VI) return;
      var mg = Number(b.getAttribute('data-tieu'));
      var e = document.getElementById('t-e'), kq = document.getElementById('t-kq');
      ban = true; e.textContent = ''; kq.innerHTML = '<div class="cho">⏳ Đang trừ tiền…</div>';
      /* ⚠️ GỬI THẲNG mã ghế trong thân gói, y như lượt `dung` vẫn làm — đừng để máy chủ tự dò
         lại từ địa chỉ. Trang ĐÃ BIẾT ghế rồi; bắt máy chủ suy lại là thêm một chỗ hỏng được,
         và nó đã hỏng thật. Địa chỉ API nay cũng mang ghế, nên đây là lớp thứ hai. */
      goi('tieu', { sdt: VI.sdt || nhoSdt(), pin: VI.pin, menh_gia: mg, ma_may: GHE }, function(r){
        ban = false;
        if (!r.ok) {
          kq.innerHTML = '';
          e.textContent = r.error || 'Không chạy được.';
          /* Số dư có thể vừa đổi (tiêu ở ghế khác) — tra lại để thẻ mờ đúng thực tế. */
          if (r.so_du) { VI.so_du = r.so_du; ve(); }
          return;
        }
        VI.so_du = r.so_du;
        kq.innerHTML = '<div class="ok" style="margin-top:12px">' + esc(r.thong_bao) + '</div>';
        /* Vẽ lại để số dư và các thẻ mờ khớp ngay, nhưng GIỮ lại câu báo vừa hiện. */
        var giu = kq.innerHTML;
        ve();
        var kq2 = document.getElementById('t-kq');
        if (kq2) kq2.innerHTML = giu;
      });
    };
  });
  [].forEach.call(document.querySelectorAll('[data-chep]'), function(b){
    b.onclick = function(){ chep(b.getAttribute('data-chep'), b); };
  });
  var sl = document.getElementById('sl');
  if (sl) sl.oninput = function(){ SL = Number(sl.value) || 1; tongTien(); };
  tongTien();

  var mua = document.getElementById('mua');
  if (mua) mua.onclick = function(){
    var e = document.getElementById('e');
    if (CHON === null) { e.textContent = 'Chọn một gói trước nhé.'; return; }
    var sdt = (document.getElementById('sdt').value || '').trim();
    var pin = (document.getElementById('pin').value || '').trim();
    if (!/^\d{4}$/.test(pin)) { e.textContent = 'PIN phải gồm đúng 4 chữ số.'; return; }
    if (ban) return;
    ban = true; mua.disabled = true; e.textContent = 'Đang tạo đơn…';
    goi('dat', { sdt: sdt, pin: pin, cc: (document.getElementById('cc')||{}).value || '',
                 menh_gia: D.goi[CHON].menh_gia,
                 so_luong: Math.max(1, Math.min(10, Number(document.getElementById('sl').value)||1)) },
      function(r){
        ban = false; mua.disabled = false;
        if (!r.ok) { e.textContent = r.error || 'Không tạo được đơn.'; return; }
        DON = r; ve(); soiDon();
      });
  };

  var qc = document.getElementById('qr-canvas');
  if (qc && DON && DON.qr && DON.qr.length) {
    /* 8 px mỗi module: mã 37x37 ra 360px — vừa màn điện thoại, và đủ to để app ngân hàng đọc
       được cả khi khách chụp lại màn hình thay vì tải ảnh. */
    veQR(DON.qr, qc, 8);
    var tai = document.getElementById('qr-tai');
    if (tai) tai.onclick = function(){
      try {
        var a = document.createElement('a');
        a.href = qc.toDataURL('image/png');
        a.download = 'QR-mua-ma-' + (DON.ma_don || '') + '.png';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
      } catch (er) {
        /* Trình duyệt chặn tải thì nói ra và chỉ đường khác, đừng để nút bấm không làm gì. */
        alert('Máy không cho tải ảnh. Anh/chị chụp màn hình mã QR này, rồi trong app ngân hàng '
          + 'chọn "quét từ thư viện ảnh".');
      }
    };
  }

  var huy = document.getElementById('huy');
  if (huy) huy.onclick = function(){ DON = null; if (hen) { clearTimeout(hen); hen = null; } ve(); };

  var xem = document.getElementById('t-xem');
  if (xem) xem.onclick = function(){
    var e = document.getElementById('e'), kq = document.getElementById('kq');
    kq.innerHTML = ''; e.textContent = 'Đang tra…';
    goi('tra', { sdt: document.getElementById('t-sdt').value,
                 pin: document.getElementById('t-pin').value }, function(r){
      if (!r.ok) {
        /* Tra không ra thì đưa luôn LỐI RA, đừng để khách đứng đó gõ lại tới lúc bị hãm. */
        e.innerHTML = esc(r.error || 'Không tìm thấy.')
          + '<br><span class="mut">Sai PIN hay quên PIN đều ra câu này. '
          + 'Gọi nhân viên, đọc số điện thoại — nhân viên tra được mã giúp anh/chị.</span>';
        return;
      }
      e.textContent = '';
      var h = '';
      if (!r.chua_dung.length) h += '<p class="mut">Không còn mã nào chưa dùng.</p>';
      r.chua_dung.forEach(function(m){
        /* Mã chưa tới hạn hiện MỐC DÙNG ĐƯỢC, không hiện "còn dùng được" — khách cần biết quay
           lại lúc nào, chứ nhìn thấy "còn dùng được" rồi ra ghế quét không ăn là tệ nhất. */
        var chua = (m.con_cho || 0) > 0;
        h += '<div class="ma' + (chua ? ' het' : '') + '"><div><div class="m">' + esc(m.ma) + '</div>'
          + '<div class="g">' + tien(m.menh_gia) + ' · '
          + (chua ? '⏳ dùng được từ ' + esc(String(m.dung_tu).slice(0,16)) : 'còn dùng được')
          + '</div></div>'
          + '<button data-chep="' + esc(m.ma) + '">Chép</button></div>';
      });
      r.da_dung.forEach(function(m){
        h += '<div class="ma het"><div><div class="m">' + esc(m.ma) + '</div>'
          + '<div class="g">đã dùng ' + esc(m.dung_luc)
          + (m.dung_may ? ' · ghế ' + esc(m.dung_may) : '') + '</div></div></div>';
      });
      kq.innerHTML = h;
      noi();
    });
  };

  var qok = document.getElementById('q-ok');
  if (qok) qok.onclick = function(){
    var e = document.getElementById('q-e');
    if (ban) return;
    ban = true; qok.disabled = true; e.textContent = 'Đang kiểm…';
    goi('lay_lai_pin', { sdt: document.getElementById('q-sdt').value,
                         cc:  document.getElementById('q-cc').value,
                         pin: document.getElementById('q-pin').value }, function(r){
      ban = false; qok.disabled = false;
      if (!r.ok) { e.textContent = r.error || 'Không đặt lại được.'; return; }
      e.innerHTML = '<span style="color:#8ff0b0">' + esc(r.thong_bao) + '</span>';
    });
  };

  var dok = document.getElementById('d-ok');
  if (dok) dok.onclick = function(){
    var e = document.getElementById('e'), kq = document.getElementById('kq');
    /* Ghế CHỈ đến từ cái tem. Không có thì đã không có nút này để bấm (xem veDung), nhưng vẫn
       chặn lần nữa ở đây — nút biến mất là chuyện của giao diện, còn đây là chuyện của tiền. */
    var g = GHE;
    if (!g) { e.textContent = 'Chưa biết ghế nào — quét mã QR trên ghế đang ngồi giúp em.'; return; }
    if (ban) return;
    ban = true; dok.disabled = true; e.textContent = 'Đang kiểm mã…';
    goi('dung', { ma: document.getElementById('d-ma').value, ma_may: g }, function(r){
      ban = false; dok.disabled = false;
      if (!r.ok) { e.textContent = r.error || 'Mã không dùng được.'; return; }
      e.textContent = '';
      kq.innerHTML = '<div class="ok"><b>Xong!</b><br>' + esc(r.thong_bao) + '</div>';
    });
  };
}

/* Hỏi lại xem tiền về chưa. 3 giây một lượt: khách đang đứng nhìn màn hình chờ, mà mỗi lượt là
   một request PHP — dày hơn nữa cũng không nhanh hơn được vì phần chậm nằm ở ngân hàng. */
function soiDon(){
  if (hen) { clearTimeout(hen); hen = null; }
  if (!DON) return;
  hen = setTimeout(function(){
    goi('soi', { ma_don: DON.ma_don }, function(r){
      if (!DON) return;
      if (r.ok && r.xong) { xongDon(r.ma, r); return; }
      soiDon();
    });
  }, 3000);
}

function xongDon(ds, r){
  if (hen) { clearTimeout(hen); hen = null; }
  /* Đơn NẠP: thứ khách chờ là SỐ DƯ, không phải bộ mã. Hiện nhầm một danh sách mã rỗng ở đây
     là khách tưởng nạp hỏng. */
  if (r && r.loai === 'nap') {
    var hn = '<div class="wrap">' + dau()
      + '<div class="card"><h2>Đã nhận tiền — ví đã được cộng</h2>'
      + '<div class="ok">Ví của anh/chị nay có <b style="color:#f0b429">'
      + tien((r.so_du || 0) + (r.so_du_cho || 0)) + '</b>.'
      + (r.so_du_cho > 0
          ? '<br><b>⏳ ' + tien(r.so_du_cho) + ' đang trong hạn chờ</b>'
            + (r.con_cho > 0 ? ' — dùng được sau ' + docCho(r.con_cho) : '') + '.'
          : '')
      + '<br>Muốn tiêu: <b>quét mã QR dán trên ghế</b>, nhập số điện thoại và PIN.</div>'
      + '<button id="ve-dau" style="width:100%;margin-top:14px">Xong</button></div></div>';
    app.innerHTML = hn;
    DON = null; NAP = null;
    document.getElementById('ve-dau').onclick = function(){ TAB = 'cua-toi'; ve(); };
    return;
  }
  var h = '<div class="wrap">' + dau()
    + '<div class="card"><h2>Đã nhận tiền — mã của anh/chị đây</h2>'
    + '<div class="ok">Mã <b>không hết hạn</b>, dùng được ở <b>bất kỳ ghế nào</b>. '
    + 'Quên mã thì vào mục <b>Mã của tôi</b>, nhập số điện thoại và PIN vừa đặt.'
    + ((D && D.cho_ngay > 0)
        ? '<br><b>⏳ Dùng được sau ' + D.cho_ngay + ' ngày kể từ bây giờ.</b>' : '')
    + '</div>';
  (ds || []).forEach(function(m){
    h += '<div class="ma"><div><div class="m">' + esc(m) + '</div>'
      + '<div class="g">chụp lại màn hình này giúp em</div></div>'
      + '<button data-chep="' + esc(m) + '">Chép</button></div>';
  });
  h += '<button id="ve-dau" style="width:100%;margin-top:14px">Mua thêm</button></div></div>';
  app.innerHTML = h;
  DON = null;
  [].forEach.call(document.querySelectorAll('[data-chep]'), function(b){
    b.onclick = function(){ chep(b.getAttribute('data-chep'), b); };
  });
  document.getElementById('ve-dau').onclick = function(){ TAB = 'mua'; CHON = null; ve(); };
}

goi('goi', {}, function(r){
  if (r.ok) { D = r; if (r.ghe) GHE = r.ghe; }
  ve();
});
})();
JS;
	}

	// ===================================================================== trang

	public static function ve() {
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		$nen = esc_url_raw( (string) get_option( 'vhg_anh_nen', '' ) );
		$lop = ''; $bien = '';
		if ( '' !== $nen && ! preg_match( '/["\\\\()]/', $nen ) ) {
			$lop  = ' class="co-anh"';
			$bien = ' style="--nen:url(&quot;' . esc_attr( $nen ) . '&quot;)"';
		}
		echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>Mua mã giảm giá — ' . esc_html( VHG_Trang::TEN_NGAN ) . '</title>'
			. '<meta name="theme-color" content="#12141f">'
			. '<style>' . self::css() . '</style></head><body' . $lop . $bien . '>'
			. '<div id="app"></div>'
			/* ══════════════════════════════════════════════════════════════════════════════
			 * 🔴 ĐỊA CHỈ API PHẢI MANG THEO MÃ GHẾ.
			 *
			 * Anh Thắng 23/08/2026: *"quét QR tại ghế nó chọn luôn ghế đó, nhưng giờ chọn gói
			 * nó báo chưa chọn ghế"*.
			 *
			 * Bản trước đưa `self::url()` — địa chỉ TRẦN, không tham số. Trang thì biết ghế
			 * (`window.VHG_GHE` đúng, màn hiện "AMTP01"), nhưng mọi lượt gọi API lại đi tới một
			 * địa chỉ KHÔNG mang ghế, nên máy chủ dò lại và không thấy gì.
			 *
			 * Hỏng HAI chỗ, không phải một:
			 *   · `tieu` -> "Chưa biết dùng cho ghế nào" (chỗ anh Thắng thấy)
			 *   · `goi`  -> số phút rơi về TỈ LỆ CHUNG ở mọi ghế — tức là bản vá "mệnh giá theo
			 *     máy" hôm nay âm thầm không chạy. Chỗ này KHÔNG kêu lên: nó chỉ hiện một con
			 *     số phút sai, và khách tin nó.
			 * ══════════════════════════════════════════════════════════════════════════════ */
			. '<script>window.VHG_SHOP=' . wp_json_encode( self::url( self::ghe_tu_dia_chi() ) ) . ';'
			. 'window.VHG_GHE=' . wp_json_encode( self::ghe_tu_dia_chi() ) . ';'
			. 'window.VHG_TEN=' . wp_json_encode( VHG_Trang::TEN_NGAN ) . ';</script>'
			. '<script>' . self::js() . '</script>'
			. '</body></html>';
	}
}
