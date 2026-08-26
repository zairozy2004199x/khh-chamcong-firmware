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

	/**
	 * 🔴 CHỈ ĐẾM LƯỢT HỎNG, và XOÁ SẠCH khi thành công.
	 *
	 * Anh Thắng 23/08/2026, đang ngồi trên ghế: *"tại sao bấm không chạy"* — màn báo *"Thử quá
	 * nhiều lần, chờ 10 phút"*. Không phải ghế hỏng: anh vừa bị chính cái hãm thử của mình khoá.
	 *
	 * Bản trước đếm MỌI lượt gọi. Nhưng cái hãm này lắp ra để chặn MÁY DÒ PIN, mà một lượt
	 * thành công thì đã chứng minh người gọi biết PIN rồi — đếm nó vào là phạt đúng khách thật.
	 * Và phạt ở chỗ tệ nhất: khách đã trả tiền, đang ngồi trên ghế, mua thêm lượt thứ mười sáu
	 * trong buổi thì bị khoá không cho tiêu tiền của chính mình.
	 *
	 * Nay: hỏng thì đếm, thành công thì XOÁ luôn bộ đếm. Máy dò PIN không bao giờ thành công
	 * nên nó vẫn bị chặn sau 15 lượt như cũ; khách thật thì không bao giờ chạm tới con số đó.
	 */
	private static function dem_neu_hong( $viec, $kq ) {
		if ( empty( $kq['ok'] ) ) { self::dem( $viec ); }
		else { delete_transient( self::khoa_key( $viec ) ); }
		return $kq;
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
				/* Chỉ CỜ BẬT/TẮT, không kèm mốc hay giá trị quà: trang chưa đăng nhập chỉ cần
				   biết có chương trình hay không để mời chào. Số liệu chi tiết đi cùng ví. */
				'tich_bat' => VHG_Vi::tich_cf()['bat'] ? 1 : 0,
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

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * TRẢ MỘT LƯỢT GHẾ BẰNG CHUYỂN KHOẢN — đường DUY NHẤT tích được lượt ưu đãi.
		 *
		 * Anh Thắng 23/08/2026: *"Tích lượt qua quét QR tại máy luôn, chỉ có tiền mặt thì không"*.
		 * Xem chú thích dài ở `VHG_Vi::dat_ghe()` để biết vì sao QR trên màn ghế không tích được.
		 *
		 * ⚠️ Ghế lấy theo ĐÚNG luật của cả trang: thân gói trước, rồi tới địa chỉ. Đọc mỗi địa
		 *    chỉ là lặp lại đúng lỗi ngày 23/08/2026 — trang biết ghế mà máy chủ thì không.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( 'dat_ghe' === $viec ) {
			self::tra_don_ghe( VHG_Vi::dat_ghe(
				self::ghe_tu_dia_chi( $d ),
				isset( $d['menh_gia'] ) ? $d['menh_gia'] : 0,
				isset( $d['sdt'] ) ? $d['sdt'] : '',
				isset( $d['pin'] ) ? $d['pin'] : '' ) );
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
			/* Đơn TRẢ MỘT LƯỢT GHẾ: khách không chờ mã nào cả, họ chờ CÁI GHẾ. Trả về tình
			   trạng tích lượt để trang khoe ngay con dấu vừa được — đó là toàn bộ lý do khách
			   chịu đăng nhập trước khi trả tiền. */
			if ( 'ghe' === $loai ) {
				self::tra( array( 'ok' => true, 'xong' => $xong ? 1 : 0, 'loai' => 'ghe',
					'ma' => array(), 'ma_may' => (string) $don['ma_may'],
					'tich' => ( $xong && '' !== (string) $don['sdt'] )
						? VHG_Vi::tich( $don['sdt'] ) : null ) );
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
			$r = VHG_Ma::tra( isset( $d['sdt'] ) ? $d['sdt'] : '', isset( $d['pin'] ) ? $d['pin'] : '' );
			self::tra( self::dem_neu_hong( 'tra', $r ) );
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
			self::tra( self::dem_neu_hong( 'lay', VHG_Ma::lay_lai_pin(
				isset( $d['sdt'] ) ? $d['sdt'] : '',
				isset( $d['cc'] ) ? $d['cc'] : '',
				isset( $d['pin'] ) ? $d['pin'] : '' ) ) );
			return;
		}

		if ( 'dung' === $viec ) {
			if ( self::bi_khoa( 'dung' ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút rồi thử lại.' ) );
				return;
			}
			self::tra( self::dem_neu_hong( 'dung', VHG_Ma::dung(
				isset( $d['ma'] ) ? $d['ma'] : '',
				isset( $d['ma_may'] ) ? $d['ma_may'] : '' ) ) );
			return;
		}

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * MỘT LẦN NHẬP, RA CẢ VÍ LẪN MÃ.
		 *
		 * Anh Thắng 23/08/2026: *"cùng 1 ví mà, sao lại ra 2 lần đăng nhập"*. Đúng. Tab "Của
		 * tôi" có hai ô nhập số điện thoại + PIN nằm chồng nhau, cùng một số, cùng một PIN —
		 * khách gõ hai lần cho hai thứ mà với họ là MỘT tài khoản.
		 *
		 * ⚠️ GỘP Ở CỔNG, không phải gộp ở trang. Gọi hai lượt API rồi ghép lại ở trình duyệt thì
		 *    ăn HAI lần hãm thử, và một lượt hỏng là màn hiện nửa vời. Một lượt gọi, một lần
		 *    hãm, một kết quả.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( 'cua_toi' === $viec ) {
			if ( self::bi_khoa( 'tra' ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút rồi tra lại, hoặc nhờ nhân viên.' ) );
				return;
			}
			$sdt_c = isset( $d['sdt'] ) ? $d['sdt'] : '';
			$pin_c = isset( $d['pin'] ) ? $d['pin'] : '';
			$v_c   = VHG_Vi::vi( $sdt_c );
			$co_vi = $v_c && VHG_Ma::pin_dung( $pin_c, (string) $v_c['pin_bam'] );
			$ma_c  = VHG_Ma::tra( $sdt_c, $pin_c );
			/* 🔴 Sai PIN ở CẢ HAI thì mới là sai. Có ví mà chưa mua mã lẻ bao giờ (hoặc ngược
			   lại) vẫn là một lượt tra THÀNH CÔNG — trả `ok=false` vì một vế trống là đuổi
			   khách đi đúng lúc họ đang tìm tiền của mình. */
			if ( ! $co_vi && empty( $ma_c['ok'] ) ) {
				self::dem( 'tra' );          // chỉ đếm lượt HỎNG — xem dem_neu_hong()
				self::tra( array( 'ok' => false, 'error' => 'Số điện thoại hoặc PIN chưa đúng.' ) );
				return;
			}
			delete_transient( self::khoa_key( 'tra' ) );   // đúng PIN thì xoá sạch bộ đếm
			self::tra( array(
				'ok'        => true,
				'co_vi'     => $co_vi ? 1 : 0,
				'so_du'     => $co_vi ? VHG_Vi::so_du( $sdt_c ) : null,
				/* Tích lượt chỉ có nghĩa khi có ví — khách chưa nạp thì chưa tích được gì. */
				'tich'      => $co_vi ? VHG_Vi::tich( $sdt_c ) : null,
				'qua'       => $co_vi ? VHG_Vi::ds_qua( $sdt_c, 10 ) : array(),
				'so'        => $co_vi ? VHG_Vi::ds_so( $sdt_c, 20 ) : array(),
				'chua_dung' => isset( $ma_c['chua_dung'] ) ? $ma_c['chua_dung'] : array(),
				'da_dung'   => isset( $ma_c['da_dung'] ) ? $ma_c['da_dung'] : array(),
				'da_huy'    => isset( $ma_c['da_huy'] ) ? $ma_c['da_huy'] : array(),
				'ghe'       => self::ghe_tu_dia_chi( $d ),
			) );
			return;
		}

		/* Khách tra số dư ví. Hãm y như ô tra mã: số điện thoại là thứ đoán được.
		   ⚠️ CÒN DÙNG: tab "Dùng tại ghế" mở ví bằng lượt này, và ở đó khách KHÔNG cần danh
		      sách mã — gọi `cua_toi` ở đó là kéo về một đống dữ liệu không dùng tới. */
		if ( 'vi' === $viec ) {
			if ( self::bi_khoa( 'tra' ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút rồi tra lại, hoặc nhờ nhân viên.' ) );
				return;
			}
			$sdt_ = isset( $d['sdt'] ) ? $d['sdt'] : '';
			$pin_ = isset( $d['pin'] ) ? $d['pin'] : '';
			$v_   = VHG_Vi::vi( $sdt_ );
			/* ⚠️ MỘT CÂU LỖI cho cả "chưa có ví" lẫn "sai PIN" — nói tách ra là biến ô này
			   thành máy dò xem số nào đã nạp tiền. */
			if ( ! $v_ || ! VHG_Ma::pin_dung( $pin_, (string) $v_['pin_bam'] ) ) {
				self::dem( 'tra' );          // chỉ đếm lượt HỎNG — xem dem_neu_hong()
				self::tra( array( 'ok' => false, 'error' => 'Số điện thoại hoặc PIN chưa đúng.' ) );
				return;
			}
			delete_transient( self::khoa_key( 'tra' ) );
			$sd = VHG_Vi::so_du( $sdt_ );
			self::tra( array( 'ok' => true, 'so_du' => $sd, 'tich' => VHG_Vi::tich( $sdt_ ),
				'qua' => VHG_Vi::ds_qua( $sdt_, 10 ),
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
			self::tra( self::dem_neu_hong( 'dung', VHG_Vi::tieu(
				isset( $d['sdt'] ) ? $d['sdt'] : '',
				isset( $d['pin'] ) ? $d['pin'] : '',
				isset( $d['menh_gia'] ) ? $d['menh_gia'] : 0,
				self::ghe_tu_dia_chi( $d ) ) ) );
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
	 * Gói tin trả về cho một đơn TRẢ LƯỢT GHẾ.
	 *
	 * ⚠️ KHÔNG dùng lại `tra_don()`: đơn ghế mang nội dung `GHE<ghế> <mã>` chứ không phải
	 *    `MUA<mã đơn>`, và `VHG_Vi::dat_ghe()` đã dựng sẵn đúng chuỗi VietQR đó rồi. Cho nó đi
	 *    qua `tra_don()` là chuỗi bị dựng lại theo khuôn MUA — khách trả tiền, webhook đọc ra
	 *    một đơn mua mã, và cái ghế họ đang ngồi không bao giờ chạy.
	 */
	private static function tra_don_ghe( $r ) {
		if ( empty( $r['ok'] ) ) { self::tra( $r ); return; }
		/* Mức sửa lỗi L — cùng lý do với đơn mua mã: mã hiện trên màn, không chịu vết xước. */
		$r['qr'] = VHG_QRVe::hang( VHG_QRVe::ma_tran( $r['chuoi_qr'], 'L' ) );
		unset( $r['chuoi_qr'] );
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
/* Tích lượt: hàng ô vuông đầy dần. Nhìn cái là biết còn mấy ô, khỏi phải làm phép trừ. */
.tich{margin-top:12px;padding:12px 13px;border-radius:12px;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12)}
.tich-o{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px}
.tich-o span{width:20px;height:20px;border-radius:6px;
  background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16)}
.tich-o span.on{background:#f0b429;border-color:#f0b429}
.tich-chu{color:#e8dcc4;font-weight:700;font-size:13px}
.tich-p{color:#a9a091;font-size:12.5px;margin-top:3px}
.qua{margin-top:10px;padding:11px 13px;border-radius:12px;color:#231a06;font-weight:700;
  background:#f0b429}
.qua-p{font-weight:400;margin-top:4px;font-size:13px;color:#3a2c07}
/* Lời dặn ngồi lên ghế: viền vàng, đứng ngay TRÊN các thẻ gói. Không để chữ mờ — đây là câu
   duy nhất ngăn khách bấm xong rồi mới đứng dậy đi tìm ghế. */
.dan{border:1px solid rgba(240,180,41,.5);background:rgba(240,180,41,.09);border-radius:12px;
  padding:11px 13px;margin:0 0 12px;color:#f0b429;font-weight:700;line-height:1.5}
.dan-p{color:#cfc3a6;font-weight:400;margin-top:5px;font-size:13px}
/* Đếm ngược: con số to hết cỡ — khách đang xoay người ngồi xuống, mắt không nhìn thẳng vào màn. */
.dem{text-align:center;padding:18px 10px;margin-top:12px;border-radius:14px;
  background:rgba(240,180,41,.10);border:1px solid rgba(240,180,41,.45)}
.dem-so{font-size:64px;line-height:1;font-weight:800;color:#f0b429}
.dem-chu{margin-top:8px;color:#e8dcc4;font-weight:700}
/* Ô chọn ngôn ngữ: nhỏ, nằm trên cùng, không tranh chỗ với nội dung. Nút đang chọn tô vàng như
   mọi nút "đang bật" khác của hệ thống — cùng một quy ước thì khỏi phải học lại. */
.nn{display:flex;gap:6px;justify-content:center;margin:0 0 10px;flex-wrap:wrap}
.nn button{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);color:#cfc3a6;
  border-radius:999px;padding:5px 13px;font-size:13px;font-weight:700;cursor:pointer;line-height:1.2}
.nn button.on{background:#f0b429;border-color:#f0b429;color:#231a06}
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

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * BỐN NGÔN NGỮ — Việt, Anh, Trung, Nga.
 *
 * Anh Thắng 23/08/2026: *"trang mua vé dùng 4 ngôn ngữ: Việt, Anh, Trung Quốc, Nga"*.
 *
 * 🔴 KHOÁ CỦA TỪ ĐIỂN LÀ CHÍNH CÂU TIẾNG VIỆT, không phải một mã như `mua.tieu_de`.
 *    Hai cái lợi, và cái thứ hai mới quan trọng:
 *      1. Thiếu bản dịch thì rơi về tiếng Việt — chữ vẫn đọc được, không ra "mua.tieu_de"
 *         giữa trang đang có khách đứng trả tiền.
 *      2. Câu lỗi MÁY CHỦ trả về cũng là tiếng Việt, nên `L(r.error)` dịch được luôn mà không
 *         phải sửa gì bên PHP. Câu nào chưa có trong từ điển thì hiện nguyên tiếng Việt —
 *         xấu, nhưng đúng, và khách vẫn gọi được nhân viên.
 *
 * ⚠️ Nhớ lựa chọn trong máy khách. Khách Nga ngồi xuống ghế thứ hai không phải chọn lại tiếng
 *    Nga một lần nữa. Bọc try/catch vì trình duyệt ở chế độ riêng tư ném lỗi ở localStorage.
 * ═══════════════════════════════════════════════════════════════════════════════════════ */
/* Từ điển. Khoá = câu tiếng Việt; thiếu bản dịch thì rơi về tiếng Việt, không ra mã lạ. */
var TU = {
  en: {
    'Đã tích {0}/{1} lượt': '{0}/{1} stamps collected',
    'còn {0} lượt nữa được thưởng': '{0} more to earn a reward',
    'Mỗi {0} trả bằng chuyển khoản tại ghế = 1 lượt tích.': 'Every {0} paid by bank transfer at a chair = 1 stamp.',
    'Tiêu bằng số dư ví không tích lượt — tiền nạp đã được khuyến mãi sẵn rồi.': 'Paying from your wallet balance earns no stamps — top-ups already come with a bonus.',
    '<div class="card"><h2>Hoặc trả bằng chuyển khoản</h2>': '<div class="card"><h2>Or pay by bank transfer</h2>',
    '<p class="mut" style="margin:0 0 10px">Bấm một gói — hiện mã QR ngân hàng. Trả xong ': '<p class="mut" style="margin:0 0 10px">Tap a package — a bank QR code appears. Once paid, ',
    '<b>ghế chạy ngay</b>, không phải chờ đếm.</p>': '<b>the chair starts right away</b> — no countdown to wait through.</p>',
    '🎁 Trả bằng chuyển khoản ở đây được TÍCH LƯỢT vào ví của anh/chị.': '🎁 Paying by bank transfer here earns a STAMP on your wallet.',
    '🎁 Trả bằng chuyển khoản được tích lượt ưu đãi — nhưng phải mở ví ở trên trước.': '🎁 Bank transfers earn loyalty stamps — but open your wallet above first.',
    'Quét thẳng mã QR trên màn hình ghế thì hệ thống không biết ai trả, nên không tích được.': 'If you scan the QR code on the chair screen directly, the system cannot tell who paid, so no stamp is given.',
    '<button id="ve-dau" style="width:100%;margin-top:14px">Xong</button></div></div>': '<button id="ve-dau" style="width:100%;margin-top:14px">Done</button></div></div>',
    'Đang dựng mã QR…': 'Creating the QR code…',
    'Không đặt được lượt.': 'Could not start this session.',
    'Chuyển khoản để chạy ghế {0}': 'Transfer to start chair {0}',
    '⚠️ Vui lòng NGỒI LÊN GHẾ trước khi chuyển khoản.': '⚠️ Please SIT ON THE CHAIR before you transfer.',
    'Trả xong ghế {0} chạy ngay trong vài giây — hệ thống gửi lệnh đúng lúc ngân hàng báo có tiền.': 'Once paid, chair {0} starts within seconds — the command goes out the moment the bank confirms the money.',
    '<b style="color:#f0b429">đúng nội dung</b> bên dưới. Ghế chạy ngay khi tiền về.</p>': '<b style="color:#f0b429">the exact reference</b> below. The chair starts as soon as the money arrives.</p>',
    '<div class="card"><h2>Đã nhận tiền — ghế đang chạy</h2>': '<div class="card"><h2>Payment received — the chair is running</h2>',
    'Ghế <b>{0}</b> chạy ngay bây giờ. Mời anh/chị ngồi thoải mái.': 'Chair <b>{0}</b> is starting now. Please make yourself comfortable.',
    '<p class="mut" style="margin:12px 0 0">Lần sau mở ví trước khi trả thì lượt này ': '<p class="mut" style="margin:12px 0 0">Open your wallet before paying next time and this session ',
    'được tính vào chương trình tích lượt.</p>': 'will count towards the loyalty programme.</p>',
    'Anh/chị có {0} phần quà chưa nhận': 'You have {0} gift(s) waiting',
    'Mời anh/chị ra quầy, đọc số điện thoại để nhân viên trao quà.': 'Please come to the counter and give your phone number to collect it.',
    'Của tôi': 'My account',
    'Nhập số điện thoại và PIN — hiện cả số dư ví lẫn mã đã mua.': 'Enter your phone number and PIN — shows both your wallet balance and your codes.',
    'Xem của tôi': 'Show my account',
    'Số này chưa mua mã lẻ nào — chỉ có ví.': 'No individual codes for this number — wallet only.',
    '⚠️ Vui lòng NGỒI LÊN GHẾ trước khi bấm chọn gói.': '⚠️ Please SIT ON THE CHAIR before tapping a package.',
    'Bấm xong màn đếm 5 giây rồi ghế chạy. Lệnh đã gửi ngay từ lúc bấm, nên tới 0 là ghế chạy luôn.': 'After you tap, a 5-second countdown runs and then the chair starts. The command is sent the moment you tap, so at 0 it starts immediately.',
    'Ghế sắp chạy — mời anh/chị ngồi lên ghế': 'Chair starting — please take your seat',
    ' (được thêm ': ' (bonus ',
    ' giờ': ' h',
    ' ngày': ' d',
    ' ngày kể từ bây giờ.</b>': ' days from now.</b>',
    ' ngày kể từ lúc mua.</b>': ' days after purchase.</b>',
    ' ngày.</b>': ' days.</b>',
    ' ngày</b> — ở bất kỳ ghế nào, không hết hạn': ' days</b> — at any chair, never expires',
    ' phút': ' min',
    ' phút</div>': ' min</div>',
    ' · ghế ': ' · chair ',
    ' đang trong hạn chờ': ' on hold',
    ' đang trong hạn chờ</b>': ' on hold</b>',
    ' — dùng được sau ': ' — available in ',
    ' — nhận <b>': ' — receive <b>',
    ' — tiết kiệm ': ' — you save ',
    '">Chép</button></div>': '">Copy</button></div>',
    '</b> vào ví': '</b> to wallet',
    '</b> — nhận ': '</b> — receive ',
    '</div><button id="t-thoat" style="width:100%;margin-top:6px">Đổi số điện thoại</button>': '</div><button id="t-thoat" style="width:100%;margin-top:6px">Use another number</button>',
    '<b style="color:#f0b429">đúng nội dung</b> bên dưới. Mã hiện ra ngay tại đây khi tiền về.</p>': '<b style="color:#f0b429">exact note</b> below. The code appears here as soon as payment lands.</p>',
    '<b style="color:#f0b429">⏳ Mã dùng được sau ': '<b style="color:#f0b429">⏳ Code usable after ',
    '<b style="color:#f0b429">⏳ Số dư nạp dùng được sau ': '<b style="color:#f0b429">⏳ Topped-up balance usable after ',
    '<b>quét từ thư viện ảnh</b>.</p>': '<b>scan from photo library</b>.</p>',
    '<br><b>Ví đang tạm khoá — anh/chị báo nhân viên giúp.</b>': '<br><b>Wallet is locked — please ask staff.</b>',
    '<br><b>⏳ Dùng được sau ': '<br><b>⏳ Available in ',
    '<br><span class="mut">Sai PIN hay quên PIN đều ra câu này. ': '<br><span class="mut">Wrong or forgotten PIN both show this. ',
    '<br>Muốn tiêu: <b>quét mã QR dán trên ghế</b>, nhập số điện thoại và PIN.</div>': '<br>To spend: <b>scan the QR on the chair</b>, then enter your phone number and PIN.</div>',
    '<br>⏳ Đang trong hạn chờ: <b>': '<br>⏳ On hold: <b>',
    '<button id="d-ok" class="chinh" style="margin-top:14px">Dùng mã, chạy ghế</button>': '<button id="d-ok" class="chinh" style="margin-top:14px">Use code, start chair</button>',
    '<button id="huy" style="width:100%">Đổi gói khác</button>': '<button id="huy" style="width:100%">Choose another package</button>',
    '<button id="n-mua" class="chinh">Nạp ngay</button>': '<button id="n-mua" class="chinh">Top up now</button>',
    '<button id="q-ok" style="width:100%;margin-top:14px">Đặt PIN mới</button>': '<button id="q-ok" style="width:100%;margin-top:14px">Set new PIN</button>',
    '<button id="qr-tai">⬇ Tải ảnh mã QR</button>': '<button id="qr-tai">⬇ Save QR image</button>',
    '<button id="t-mo" class="chinh" style="margin-top:14px">Mở ví</button>': '<button id="t-mo" class="chinh" style="margin-top:14px">Open wallet</button>',
    '<button id="ve-dau" style="width:100%;margin-top:14px">Mua thêm</button></div></div>': '<button id="ve-dau" style="width:100%;margin-top:14px">Buy more</button></div></div>',
    '<div class="card"><h2>Dùng mã cho ghế</h2>': '<div class="card"><h2>Use a code at this chair</h2>',
    '<div class="card"><h2>Hoặc dùng mã giảm giá</h2>': '<div class="card"><h2>Or use a discount code</h2>',
    '<div class="card"><h2>Quên PIN?</h2>': '<div class="card"><h2>Forgot PIN?</h2>',
    '<div class="card"><h2>Thông tin nhận mã</h2>': '<div class="card"><h2>Where to send your code</h2>',
    '<div class="card"><h2>Trả bằng số dư ví</h2>': '<div class="card"><h2>Pay with wallet balance</h2>',
    '<div class="card"><h2>Ví của anh/chị</h2>': '<div class="card"><h2>Your wallet</h2>',
    '<div class="card"><h2>Đã nhận tiền — mã của anh/chị đây</h2>': '<div class="card"><h2>Payment received — here is your code</h2>',
    '<div class="card"><h2>Đã nhận tiền — ví đã được cộng</h2>': '<div class="card"><h2>Payment received — wallet credited</h2>',
    '<div class="card"><p class="mut">Hiện chưa mở bán gói nạp.</p></div>': '<div class="card"><p class="mut">Top-up packages are not on sale yet.</p></div>',
    '<div class="card"><p class="mut">Đang tải bảng giá…</p></div>': '<div class="card"><p class="mut">Loading prices…</p></div>',
    '<div class="cho" id="cho">⏳ Đang chờ tiền về…</div>': '<div class="cho" id="cho">⏳ Waiting for payment…</div>',
    '<div class="ck nhan" style="display:block"><div class="nh">Chưa biết ghế nào</div>': '<div class="ck nhan" style="display:block"><div class="nh">Chair not identified</div>',
    '<div class="ck nhan"><div style="flex:1;min-width:0"><div class="nh">Ghế đang ngồi</div>': '<div class="ck nhan"><div style="flex:1;min-width:0"><div class="nh">Your chair</div>',
    '<div class="deal"><b>Giảm tới ': '<div class="deal"><b>Up to ',
    '<div class="deal"><b>Nạp càng nhiều, lợi càng lớn — tới ': '<div class="deal"><b>The more you top up, the more you get — up to ',
    '<div class="g">chụp lại màn hình này giúp em</div></div>': '<div class="g">please screenshot this</div></div>',
    '<div class="g">đã dùng ': '<div class="g">used ',
    '<div class="mo" style="color:#b32d2e">chưa đủ số dư</div>': '<div class="mo" style="color:#b32d2e">not enough balance</div>',
    '<div class="mo">được thêm ': '<div class="mo">bonus ',
    '<div class="mut" style="margin-top:5px">Đây là điều kiện của giá đã giảm: mua trước thì ': '<div class="mut" style="margin-top:5px">This is the condition for the discount: buying ahead is ',
    '<div class="mut" style="margin-top:5px">Đây là điều kiện của phần được tặng thêm: nạp ': '<div class="mut" style="margin-top:5px">This is the condition for the bonus: top up ',
    '<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>, phần còn lại ': '<div class="mut" style="margin:-4px 0 0">We <b>store only the last 4 digits</b>; the rest ',
    '<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>.</div>': '<div class="mut" style="margin:-4px 0 0">We <b>store only the last 4 digits</b>.</div>',
    '<div class="mut" style="margin:12px 0 6px"><b>Gần đây</b></div>': '<div class="mut" style="margin:12px 0 6px"><b>Recent</b></div>',
    '<div class="ok" style="margin-top:12px">Tiêu được ngay: ': '<div class="ok" style="margin-top:12px">Available now: ',
    '<div class="ok" style="margin:0 0 12px">Số dư tiêu được: ': '<div class="ok" style="margin:0 0 12px">Available balance: ',
    '<div class="ok" style="margin:0 0 12px">Trả <b>': '<div class="ok" style="margin:0 0 12px">Pay <b>',
    '<div class="ok">Mã <b>không hết hạn</b>, dùng được ở <b>bất kỳ ghế nào</b>. ': '<div class="ok">Codes <b>never expire</b> and work at <b>any chair</b>. ',
    '<div class="ok">Ví của anh/chị nay có <b style="color:#f0b429">': '<div class="ok">Your wallet now holds <b style="color:#f0b429">',
    '<div class="ten">Nạp ': '<div class="ten">Top up ',
    '<div style="margin-top:6px;line-height:1.5">Hãy <b>quét mã QR dán trên chính cái ghế ': '<div style="margin-top:6px;line-height:1.5">Please <b>scan the QR sticker on the very chair ',
    '<div>Số dư dùng được ở <b>bất kỳ ghế nào</b>, tiêu lẻ từng lượt, không hết hạn</div></div>': '<div>Balance works at <b>any chair</b>, spend it session by session, never expires</div></div>',
    '<input id="cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">': '<input id="cc" type="tel" inputmode="numeric" placeholder="can be left blank" autocomplete="off">',
    '<input id="n-cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">': '<input id="n-cc" type="tel" inputmode="numeric" placeholder="can be left blank" autocomplete="off">',
    '<label>Mã giảm giá</label>': '<label>Discount code</label>',
    '<label>PIN 4 số — ví đã có thì nhập <b>đúng PIN cũ</b></label>': '<label>4-digit PIN — if the wallet exists, enter <b>the existing PIN</b></label>',
    '<label>PIN 4 số</label>': '<label>4-digit PIN</label>',
    '<label>PIN mới (4 số)</label>': '<label>New PIN (4 digits)</label>',
    '<label>Số căn cước (đã khai lúc mua)</label>': '<label>ID number (as given at purchase)</label>',
    '<label>Số căn cước — <b>không bắt buộc</b>, chỉ để lấy lại PIN nếu quên</label>': '<label>ID number — <b>optional</b>, only to recover a forgotten PIN</label>',
    '<label>Số lượng</label>': '<label>Quantity</label>',
    '<label>Số điện thoại</label>': '<label>Phone number</label>',
    '<label>Đặt PIN 4 số — để lần sau tra lại mã của mình</label>': '<label>Set a 4-digit PIN — to look up your codes later</label>',
    '<p class="mut" style="margin:-4px 0 10px">Quên PIN thì <b>gọi nhân viên</b> — nhân viên tra ': '<p class="mut" style="margin:-4px 0 10px">Forgot your PIN? <b>Ask staff</b> — they ',
    '<p class="mut" style="margin:0 0 10px"><b>Bấm một gói</b> — hệ thống trừ thẳng số dư ': '<p class="mut" style="margin:0 0 10px"><b>Tap a package</b> — we deduct from your balance ',
    '<p class="mut" style="margin:0 0 10px">Nhập số điện thoại và PIN để thấy số dư và ': '<p class="mut" style="margin:0 0 10px">Enter your phone number and PIN to see your balance and ',
    '<p class="mut" style="margin:0 0 10px">Nếu lúc mua có khai số căn cước thì tự đặt PIN mới ': '<p class="mut" style="margin:0 0 10px">If you gave an ID number when buying, you can set a new PIN yourself. ',
    '<p class="mut" style="margin:0 0 10px">Số dư gắn với <b>số điện thoại</b>. Lần sau tới, ': '<p class="mut" style="margin:0 0 10px">The balance is tied to your <b>phone number</b>. Next visit, ',
    '<p class="mut" style="margin:0 0 12px">Hoặc chuyển tay: đúng số tiền, ': '<p class="mut" style="margin:0 0 12px">Or transfer manually: exact amount, ',
    '<p class="mut" style="margin:6px 0 14px">Không đúng ghế này thì quét lại mã QR trên ghế ': '<p class="mut" style="margin:6px 0 14px">Not this chair? Scan the QR again on the chair ',
    '<p class="mut" style="margin:6px 0 14px;text-align:center">Quét bằng app ngân hàng. ': '<p class="mut" style="margin:6px 0 14px;text-align:center">Scan with your banking app. ',
    '<p class="mut">Không còn mã nào chưa dùng.</p>': '<p class="mut">No unused codes left.</p>',
    '<p class="mut">Trang này cố ý <b>không</b> cho chọn ghế từ danh sách: chọn lộn là mã ': '<p class="mut">This page deliberately does <b>not</b> let you pick a chair from a list: pick the wrong one and the code ',
    '<p class="mut">Vẫn mua thêm mã được ở mục <b>Mua mã</b> — mua thì không cần biết ghế.</p>': '<p class="mut">You can still buy codes under <b>Buy code</b> — buying needs no chair.</p>',
    '>Của tôi</button>': '>My account</button>',
    '>Dùng tại ghế</button>': '>Use at chair</button>',
    '>Mua mã</button>': '>Buy code</button>',
    '>Nạp ví</button>': '>Top up</button>',
    'Chuyển khoản để nhận mã': 'Transfer to get your code',
    'Chuyển khoản để nạp ví': 'Transfer to top up',
    'Chép dòng này:': 'Copy this:',
    'Chưa biết ghế nào — quét mã QR trên ghế đang ngồi giúp em.': 'Chair unknown — please scan the QR on the chair you are in.',
    'Chọn một gói nạp phía trên.': 'Pick a top-up package above.',
    'Chọn một gói nạp trước nhé.': 'Please pick a top-up package.',
    'Chọn một gói trước nhé.': 'Please pick a package first.',
    'Gọi nhân viên, đọc số điện thoại — nhân viên tra được mã giúp anh/chị.</span>': 'Ask staff and give your phone number — they can look up your code.</span>',
    'Không chạy được.': 'Could not start.',
    'Không mở được ví.': 'Could not open wallet.',
    'Không tra được.': 'Lookup failed.',
    'Không tìm thấy.': 'Not found.',
    'Không tạo được đơn nạp.': 'Could not create top-up.',
    'Không tạo được đơn.': 'Could not create order.',
    'Không đặt lại được.': 'Could not reset.',
    'Mua hôm nay, dùng bất cứ lúc nào, ở bất kỳ ghế nào': 'Buy today, use any time, at any chair',
    'Mua mã giảm giá': 'Buy discount codes',
    'Mua trước, dùng sau <b>': 'Buy now, use after <b>',
    'Máy không cho tải ảnh. Anh/chị chụp màn hình mã QR này, rồi trong app ngân hàng ': 'Your device blocked the download. Please screenshot this QR, then in your banking app ',
    'Mã không dùng được.': 'Code cannot be used.',
    'Mạng đang chập chờn. Thử lại giúp em, hoặc gọi nhân viên.': 'Network is unstable. Please try again, or ask staff.',
    'Ngân hàng / Số tài khoản': 'Bank / Account number',
    'Nội dung chuyển khoản': 'Transfer note',
    'PIN phải gồm đúng 4 chữ số.': 'PIN must be exactly 4 digits.',
    'Phải trả: <b style="color:#f0b429;font-size:18px">': 'Total: <b style="color:#f0b429;font-size:18px">',
    'Quên mã thì vào mục <b>Mã của tôi</b>, nhập số điện thoại và PIN vừa đặt.': 'Lost the code? Go to <b>My codes</b> and enter your phone number and PIN.',
    'Số tiền': 'Amount',
    'Trả: <b style="color:#f0b429;font-size:18px">': 'Pay: <b style="color:#f0b429;font-size:18px">',
    'anh/chị đang ngồi</b> — mã đó cho hệ thống biết đúng ghế, khỏi phải chọn.</div></div>': 'you are sitting in</b> — that code tells us the exact chair, no picking needed.</div></div>',
    'chạy cho người khác, mà mã thì mất rồi. Tem trên ghế mờ hay bong thì gọi nhân viên — ': 'runs for someone else, and the code is gone. If the sticker is faded or peeling, ask staff — ',
    'chọn "quét từ thư viện ảnh".': 'choose "scan from photo library".',
    'các gói bấm được.</p>': 'the packages you can tap.</p>',
    'còn dùng được': 'still valid',
    'không được ghi lại ở đâu cả.</div>': 'the rest is not stored anywhere.</div>',
    'mình đang ngồi.</p>': 'you are sitting in.</p>',
    'nhân viên chạy mã giúp được.</p>': 'staff can redeem the code for you.</p>',
    'nhập lại số này và PIN là tiêu tiếp.</p>': 'enter this number and PIN to keep spending.</p>',
    'rẻ hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế với giá gốc.</div></div>': 'cheaper, in exchange for waiting. Need it today? Pay right at the chair at full price.</div></div>',
    'trước thì lợi hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế.</div></div>': 'ahead gets you more, in exchange for waiting. Need it today? Pay right at the chair.</div></div>',
    'và ghế chạy ngay.</p>': 'and the chair starts.</p>',
    'Đang kiểm mã…': 'Checking code…',
    'Đang kiểm…': 'Checking…',
    'Đang mở ví…': 'Opening wallet…',
    'Đang tra…': 'Looking up…',
    'Đang tạo đơn…': 'Creating order…',
    'Đang xem trên chính máy này thì bấm <b>Tải ảnh mã QR</b>, rồi trong app ngân hàng chọn ': 'Viewing on this same phone? Tap <b>Save QR image</b>, then in your banking app choose ',
    'đ': 'd',
    'được bằng số điện thoại và đọc mã giúp anh/chị.</p>': 'can look it up by phone number and read the code to you.</p>',
    'được. Không khai thì gọi nhân viên — nhân viên tra bằng số điện thoại.</p>': 'If not, ask staff — they can look it up by phone number.</p>',
    '⏳ dùng được từ ': '⏳ valid from ',
    '✓ Đã chép': '✓ Copied'
  },
  zh: {
    'Đã tích {0}/{1} lượt': '已集 {0}/{1} 次',
    'còn {0} lượt nữa được thưởng': '再 {0} 次即可获赠',
    'Mỗi {0} trả bằng chuyển khoản tại ghế = 1 lượt tích.': '在按摩椅每以银行转账支付 {0} = 1 次。',
    'Tiêu bằng số dư ví không tích lượt — tiền nạp đã được khuyến mãi sẵn rồi.': '使用钱包余额支付不累计次数——充值时已享受优惠。',
    '<div class="card"><h2>Hoặc trả bằng chuyển khoản</h2>': '<div class="card"><h2>或使用银行转账支付</h2>',
    '<p class="mut" style="margin:0 0 10px">Bấm một gói — hiện mã QR ngân hàng. Trả xong ': '<p class="mut" style="margin:0 0 10px">点击一个套餐——显示银行二维码。支付后，',
    '<b>ghế chạy ngay</b>, không phải chờ đếm.</p>': '<b>按摩椅立即启动</b>，无需等待倒计时。</p>',
    '🎁 Trả bằng chuyển khoản ở đây được TÍCH LƯỢT vào ví của anh/chị.': '🎁 在此使用银行转账支付，可为您的钱包累计一次。',
    '🎁 Trả bằng chuyển khoản được tích lượt ưu đãi — nhưng phải mở ví ở trên trước.': '🎁 银行转账可累计优惠次数——但请先在上方打开钱包。',
    'Quét thẳng mã QR trên màn hình ghế thì hệ thống không biết ai trả, nên không tích được.': '若直接扫描按摩椅屏幕上的二维码，系统无法识别付款人，因此无法累计。',
    '<button id="ve-dau" style="width:100%;margin-top:14px">Xong</button></div></div>': '<button id="ve-dau" style="width:100%;margin-top:14px">完成</button></div></div>',
    'Đang dựng mã QR…': '正在生成二维码…',
    'Không đặt được lượt.': '无法下单。',
    'Chuyển khoản để chạy ghế {0}': '转账以启动按摩椅 {0}',
    '⚠️ Vui lòng NGỒI LÊN GHẾ trước khi chuyển khoản.': '⚠️ 转账前请先坐上按摩椅。',
    'Trả xong ghế {0} chạy ngay trong vài giây — hệ thống gửi lệnh đúng lúc ngân hàng báo có tiền.': '支付后按摩椅 {0} 数秒内启动——银行确认到账时系统即刻发出指令。',
    '<b style="color:#f0b429">đúng nội dung</b> bên dưới. Ghế chạy ngay khi tiền về.</p>': '<b style="color:#f0b429">准确的转账备注</b>。款项到账后按摩椅立即启动。</p>',
    '<div class="card"><h2>Đã nhận tiền — ghế đang chạy</h2>': '<div class="card"><h2>已收到款项——按摩椅正在运行</h2>',
    'Ghế <b>{0}</b> chạy ngay bây giờ. Mời anh/chị ngồi thoải mái.': '按摩椅 <b>{0}</b> 现在启动。请安心享受。',
    '<p class="mut" style="margin:12px 0 0">Lần sau mở ví trước khi trả thì lượt này ': '<p class="mut" style="margin:12px 0 0">下次支付前先打开钱包，本次消费',
    'được tính vào chương trình tích lượt.</p>': '即可计入优惠累计计划。</p>',
    'Anh/chị có {0} phần quà chưa nhận': '您有 {0} 份礼品待领取',
    'Mời anh/chị ra quầy, đọc số điện thoại để nhân viên trao quà.': '请到前台报手机号领取礼品。',
    'Của tôi': '我的',
    'Nhập số điện thoại và PIN — hiện cả số dư ví lẫn mã đã mua.': '输入手机号和密码 — 同时显示钱包余额与已购优惠码。',
    'Xem của tôi': '查看我的',
    'Số này chưa mua mã lẻ nào — chỉ có ví.': '此号码没有单独的优惠码 — 仅有钱包。',
    '⚠️ Vui lòng NGỒI LÊN GHẾ trước khi bấm chọn gói.': '⚠️ 请先坐到按摩椅上，再点击套餐。',
    'Bấm xong màn đếm 5 giây rồi ghế chạy. Lệnh đã gửi ngay từ lúc bấm, nên tới 0 là ghế chạy luôn.': '点击后倒计时5秒，按摩椅随即启动。指令在点击时已发出，因此归零即刻运行。',
    'Ghế sắp chạy — mời anh/chị ngồi lên ghế': '按摩椅即将启动 — 请就座',
    ' (được thêm ': '（赠送 ',
    ' giờ': ' 小时',
    ' ngày': ' 天',
    ' ngày kể từ bây giờ.</b>': ' 天后可用。</b>',
    ' ngày kể từ lúc mua.</b>': ' 天后可用（自购买起）。</b>',
    ' ngày.</b>': ' 天后。</b>',
    ' ngày</b> — ở bất kỳ ghế nào, không hết hạn': ' 天</b> — 任意按摩椅可用，永不过期',
    ' phút': ' 分钟',
    ' phút</div>': ' 分钟</div>',
    ' · ghế ': ' · 按摩椅 ',
    ' đang trong hạn chờ': ' 处于等待期',
    ' đang trong hạn chờ</b>': ' 处于等待期</b>',
    ' — dùng được sau ': ' — ',
    ' — nhận <b>': ' — 获得 <b>',
    ' — tiết kiệm ': ' — 节省 ',
    '">Chép</button></div>': '">复制</button></div>',
    '</b> vào ví': '</b> 到钱包',
    '</b> — nhận ': '</b> — 获得 ',
    '</div><button id="t-thoat" style="width:100%;margin-top:6px">Đổi số điện thoại</button>': '</div><button id="t-thoat" style="width:100%;margin-top:6px">更换手机号</button>',
    '<b style="color:#f0b429">đúng nội dung</b> bên dưới. Mã hiện ra ngay tại đây khi tiền về.</p>': '<b style="color:#f0b429">备注准确</b>。到账后优惠码会立即显示在此。</p>',
    '<b style="color:#f0b429">⏳ Mã dùng được sau ': '<b style="color:#f0b429">⏳ 优惠码可用时间：',
    '<b style="color:#f0b429">⏳ Số dư nạp dùng được sau ': '<b style="color:#f0b429">⏳ 充值余额可用时间：',
    '<b>quét từ thư viện ảnh</b>.</p>': '<b>从相册扫描</b>。</p>',
    '<br><b>Ví đang tạm khoá — anh/chị báo nhân viên giúp.</b>': '<br><b>钱包已锁定 — 请联系工作人员。</b>',
    '<br><b>⏳ Dùng được sau ': '<br><b>⏳ ',
    '<br><span class="mut">Sai PIN hay quên PIN đều ra câu này. ': '<br><span class="mut">密码错误或忘记密码都会显示此提示。',
    '<br>Muốn tiêu: <b>quét mã QR dán trên ghế</b>, nhập số điện thoại và PIN.</div>': '<br>使用方法：<b>扫描椅子上的二维码</b>，输入手机号和密码。</div>',
    '<br>⏳ Đang trong hạn chờ: <b>': '<br>⏳ 等待期中：<b>',
    '<button id="d-ok" class="chinh" style="margin-top:14px">Dùng mã, chạy ghế</button>': '<button id="d-ok" class="chinh" style="margin-top:14px">使用优惠码并启动</button>',
    '<button id="huy" style="width:100%">Đổi gói khác</button>': '<button id="huy" style="width:100%">更换套餐</button>',
    '<button id="n-mua" class="chinh">Nạp ngay</button>': '<button id="n-mua" class="chinh">立即充值</button>',
    '<button id="q-ok" style="width:100%;margin-top:14px">Đặt PIN mới</button>': '<button id="q-ok" style="width:100%;margin-top:14px">设置新密码</button>',
    '<button id="qr-tai">⬇ Tải ảnh mã QR</button>': '<button id="qr-tai">⬇ 保存二维码</button>',
    '<button id="t-mo" class="chinh" style="margin-top:14px">Mở ví</button>': '<button id="t-mo" class="chinh" style="margin-top:14px">打开钱包</button>',
    '<button id="ve-dau" style="width:100%;margin-top:14px">Mua thêm</button></div></div>': '<button id="ve-dau" style="width:100%;margin-top:14px">继续购买</button></div></div>',
    '<div class="card"><h2>Dùng mã cho ghế</h2>': '<div class="card"><h2>在此按摩椅使用优惠码</h2>',
    '<div class="card"><h2>Hoặc dùng mã giảm giá</h2>': '<div class="card"><h2>或使用优惠码</h2>',
    '<div class="card"><h2>Quên PIN?</h2>': '<div class="card"><h2>忘记密码？</h2>',
    '<div class="card"><h2>Thông tin nhận mã</h2>': '<div class="card"><h2>接收优惠码的信息</h2>',
    '<div class="card"><h2>Trả bằng số dư ví</h2>': '<div class="card"><h2>使用钱包余额支付</h2>',
    '<div class="card"><h2>Ví của anh/chị</h2>': '<div class="card"><h2>您的钱包</h2>',
    '<div class="card"><h2>Đã nhận tiền — mã của anh/chị đây</h2>': '<div class="card"><h2>已收到款项 — 这是您的优惠码</h2>',
    '<div class="card"><h2>Đã nhận tiền — ví đã được cộng</h2>': '<div class="card"><h2>已收到款项 — 钱包已充值</h2>',
    '<div class="card"><p class="mut">Hiện chưa mở bán gói nạp.</p></div>': '<div class="card"><p class="mut">目前未开放充值套餐。</p></div>',
    '<div class="card"><p class="mut">Đang tải bảng giá…</p></div>': '<div class="card"><p class="mut">正在加载价目表…</p></div>',
    '<div class="cho" id="cho">⏳ Đang chờ tiền về…</div>': '<div class="cho" id="cho">⏳ 等待到账…</div>',
    '<div class="ck nhan" style="display:block"><div class="nh">Chưa biết ghế nào</div>': '<div class="ck nhan" style="display:block"><div class="nh">未识别按摩椅</div>',
    '<div class="ck nhan"><div style="flex:1;min-width:0"><div class="nh">Ghế đang ngồi</div>': '<div class="ck nhan"><div style="flex:1;min-width:0"><div class="nh">您所坐的按摩椅</div>',
    '<div class="deal"><b>Giảm tới ': '<div class="deal"><b>最高优惠 ',
    '<div class="deal"><b>Nạp càng nhiều, lợi càng lớn — tới ': '<div class="deal"><b>充值越多，赠送越多 — 最高 ',
    '<div class="g">chụp lại màn hình này giúp em</div></div>': '<div class="g">请截图保存</div></div>',
    '<div class="g">đã dùng ': '<div class="g">已使用 ',
    '<div class="mo" style="color:#b32d2e">chưa đủ số dư</div>': '<div class="mo" style="color:#b32d2e">余额不足</div>',
    '<div class="mo">được thêm ': '<div class="mo">赠送 ',
    '<div class="mut" style="margin-top:5px">Đây là điều kiện của giá đã giảm: mua trước thì ': '<div class="mut" style="margin-top:5px">这是折扣的条件：提前购买',
    '<div class="mut" style="margin-top:5px">Đây là điều kiện của phần được tặng thêm: nạp ': '<div class="mut" style="margin-top:5px">这是赠送金额的条件：',
    '<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>, phần còn lại ': '<div class="mut" style="margin:-4px 0 0">系统<b>仅保存后4位</b>，',
    '<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>.</div>': '<div class="mut" style="margin:-4px 0 0">系统<b>仅保存后4位</b>。</div>',
    '<div class="mut" style="margin:12px 0 6px"><b>Gần đây</b></div>': '<div class="mut" style="margin:12px 0 6px"><b>最近记录</b></div>',
    '<div class="ok" style="margin-top:12px">Tiêu được ngay: ': '<div class="ok" style="margin-top:12px">立即可用：',
    '<div class="ok" style="margin:0 0 12px">Số dư tiêu được: ': '<div class="ok" style="margin:0 0 12px">可用余额：',
    '<div class="ok" style="margin:0 0 12px">Trả <b>': '<div class="ok" style="margin:0 0 12px">支付 <b>',
    '<div class="ok">Mã <b>không hết hạn</b>, dùng được ở <b>bất kỳ ghế nào</b>. ': '<div class="ok">优惠码<b>永不过期</b>，<b>任意按摩椅</b>均可使用。',
    '<div class="ok">Ví của anh/chị nay có <b style="color:#f0b429">': '<div class="ok">您的钱包现有 <b style="color:#f0b429">',
    '<div class="ten">Nạp ': '<div class="ten">充值 ',
    '<div style="margin-top:6px;line-height:1.5">Hãy <b>quét mã QR dán trên chính cái ghế ': '<div style="margin-top:6px;line-height:1.5">请<b>扫描您所坐椅子上的二维码贴纸',
    '<div>Số dư dùng được ở <b>bất kỳ ghế nào</b>, tiêu lẻ từng lượt, không hết hạn</div></div>': '<div>余额可在<b>任意按摩椅</b>使用，按次消费，永不过期</div></div>',
    '<input id="cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">': '<input id="cc" type="tel" inputmode="numeric" placeholder="可留空" autocomplete="off">',
    '<input id="n-cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">': '<input id="n-cc" type="tel" inputmode="numeric" placeholder="可留空" autocomplete="off">',
    '<label>Mã giảm giá</label>': '<label>优惠码</label>',
    '<label>PIN 4 số — ví đã có thì nhập <b>đúng PIN cũ</b></label>': '<label>4位密码 — 若钱包已存在，请输入<b>原密码</b></label>',
    '<label>PIN 4 số</label>': '<label>4位密码</label>',
    '<label>PIN mới (4 số)</label>': '<label>新密码（4位）</label>',
    '<label>Số căn cước (đã khai lúc mua)</label>': '<label>身份证号（购买时填写的）</label>',
    '<label>Số căn cước — <b>không bắt buộc</b>, chỉ để lấy lại PIN nếu quên</label>': '<label>身份证号 — <b>非必填</b>，仅用于找回密码</label>',
    '<label>Số lượng</label>': '<label>数量</label>',
    '<label>Số điện thoại</label>': '<label>手机号码</label>',
    '<label>Đặt PIN 4 số — để lần sau tra lại mã của mình</label>': '<label>设置4位密码 — 以便日后查询优惠码</label>',
    '<p class="mut" style="margin:-4px 0 10px">Quên PIN thì <b>gọi nhân viên</b> — nhân viên tra ': '<p class="mut" style="margin:-4px 0 10px">忘记密码？<b>请联系工作人员</b> — 他们',
    '<p class="mut" style="margin:0 0 10px"><b>Bấm một gói</b> — hệ thống trừ thẳng số dư ': '<p class="mut" style="margin:0 0 10px"><b>点击套餐</b> — 系统直接从余额扣款，',
    '<p class="mut" style="margin:0 0 10px">Nhập số điện thoại và PIN để thấy số dư và ': '<p class="mut" style="margin:0 0 10px">输入手机号和密码以查看余额及',
    '<p class="mut" style="margin:0 0 10px">Nếu lúc mua có khai số căn cước thì tự đặt PIN mới ': '<p class="mut" style="margin:0 0 10px">若购买时填写了身份证号，可自行设置新密码。',
    '<p class="mut" style="margin:0 0 10px">Số dư gắn với <b>số điện thoại</b>. Lần sau tới, ': '<p class="mut" style="margin:0 0 10px">余额与<b>手机号</b>绑定。下次到店，',
    '<p class="mut" style="margin:0 0 12px">Hoặc chuyển tay: đúng số tiền, ': '<p class="mut" style="margin:0 0 12px">或手动转账：金额准确，',
    '<p class="mut" style="margin:6px 0 14px">Không đúng ghế này thì quét lại mã QR trên ghế ': '<p class="mut" style="margin:6px 0 14px">不是这张椅子？请重新扫描',
    '<p class="mut" style="margin:6px 0 14px;text-align:center">Quét bằng app ngân hàng. ': '<p class="mut" style="margin:6px 0 14px;text-align:center">请用银行App扫描。',
    '<p class="mut">Không còn mã nào chưa dùng.</p>': '<p class="mut">没有未使用的优惠码。</p>',
    '<p class="mut">Trang này cố ý <b>không</b> cho chọn ghế từ danh sách: chọn lộn là mã ': '<p class="mut">本页面<b>故意</b>不提供椅子列表：选错了优惠码就会',
    '<p class="mut">Vẫn mua thêm mã được ở mục <b>Mua mã</b> — mua thì không cần biết ghế.</p>': '<p class="mut">您仍可在<b>购买码</b>中购买 — 购买无需选择椅子。</p>',
    '>Của tôi</button>': '>我的</button>',
    '>Dùng tại ghế</button>': '>在椅上使用</button>',
    '>Mua mã</button>': '>购买码</button>',
    '>Nạp ví</button>': '>充值</button>',
    'Chuyển khoản để nhận mã': '转账领取优惠码',
    'Chuyển khoản để nạp ví': '转账充值',
    'Chép dòng này:': '复制此内容：',
    'Chưa biết ghế nào — quét mã QR trên ghế đang ngồi giúp em.': '未识别按摩椅 — 请扫描您所坐椅子上的二维码。',
    'Chọn một gói nạp phía trên.': '请选择上方的充值套餐。',
    'Chọn một gói nạp trước nhé.': '请先选择充值套餐。',
    'Chọn một gói trước nhé.': '请先选择一个套餐。',
    'Gọi nhân viên, đọc số điện thoại — nhân viên tra được mã giúp anh/chị.</span>': '请联系工作人员并提供手机号 — 他们可代为查询。</span>',
    'Không chạy được.': '无法启动。',
    'Không mở được ví.': '无法打开钱包。',
    'Không tra được.': '查询失败。',
    'Không tìm thấy.': '未找到。',
    'Không tạo được đơn nạp.': '无法创建充值订单。',
    'Không tạo được đơn.': '无法创建订单。',
    'Không đặt lại được.': '无法重置。',
    'Mua hôm nay, dùng bất cứ lúc nào, ở bất kỳ ghế nào': '今日购买，随时在任意按摩椅使用',
    'Mua mã giảm giá': '购买优惠码',
    'Mua trước, dùng sau <b>': '先购买，<b>',
    'Máy không cho tải ảnh. Anh/chị chụp màn hình mã QR này, rồi trong app ngân hàng ': '设备阻止了下载。请截图此二维码，然后在银行App中',
    'Mã không dùng được.': '此码无法使用。',
    'Mạng đang chập chờn. Thử lại giúp em, hoặc gọi nhân viên.': '网络不稳定。请重试，或联系工作人员。',
    'Ngân hàng / Số tài khoản': '银行 / 账号',
    'Nội dung chuyển khoản': '转账备注',
    'PIN phải gồm đúng 4 chữ số.': '密码必须是4位数字。',
    'Phải trả: <b style="color:#f0b429;font-size:18px">': '应付：<b style="color:#f0b429;font-size:18px">',
    'Quên mã thì vào mục <b>Mã của tôi</b>, nhập số điện thoại và PIN vừa đặt.': '忘记优惠码？请进入<b>我的优惠码</b>，输入手机号和密码。',
    'Số tiền': '金额',
    'Trả: <b style="color:#f0b429;font-size:18px">': '支付：<b style="color:#f0b429;font-size:18px">',
    'anh/chị đang ngồi</b> — mã đó cho hệ thống biết đúng ghế, khỏi phải chọn.</div></div>': '</b> — 二维码会告知系统正确的椅子，无需手动选择。</div></div>',
    'chạy cho người khác, mà mã thì mất rồi. Tem trên ghế mờ hay bong thì gọi nhân viên — ': '为他人启动，而优惠码已作废。若贴纸模糊或脱落，请联系工作人员 — ',
    'chọn "quét từ thư viện ảnh".': '选择"从相册扫描"。',
    'các gói bấm được.</p>': '可点击的套餐。</p>',
    'còn dùng được': '仍可使用',
    'không được ghi lại ở đâu cả.</div>': '其余部分不会保存。</div>',
    'mình đang ngồi.</p>': '您所坐的按摩椅上。</p>',
    'nhân viên chạy mã giúp được.</p>': '工作人员可代为使用优惠码。</p>',
    'nhập lại số này và PIN là tiêu tiếp.</p>': '再次输入此号码和密码即可继续使用。</p>',
    'rẻ hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế với giá gốc.</div></div>': '更便宜，代价是需要等待。今天就要用？请在椅子上按原价付款。</div></div>',
    'trước thì lợi hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế.</div></div>': '更划算，代价是需要等待。今天就要用？请直接在椅子上付款。</div></div>',
    'và ghế chạy ngay.</p>': '按摩椅立即启动。</p>',
    'Đang kiểm mã…': '正在验证码…',
    'Đang kiểm…': '检查中…',
    'Đang mở ví…': '正在打开钱包…',
    'Đang tra…': '查询中…',
    'Đang tạo đơn…': '正在创建订单…',
    'Đang xem trên chính máy này thì bấm <b>Tải ảnh mã QR</b>, rồi trong app ngân hàng chọn ': '如果正用本机查看，请点击<b>保存二维码</b>，然后在银行App中选择',
    'đ': '越盾',
    'được bằng số điện thoại và đọc mã giúp anh/chị.</p>': '工作人员可用手机号查询并告知您优惠码。</p>',
    'được. Không khai thì gọi nhân viên — nhân viên tra bằng số điện thoại.</p>': '若未填写，请联系工作人员 — 他们可用手机号查询。</p>',
    '⏳ dùng được từ ': '⏳ 可用日期 ',
    '✓ Đã chép': '✓ 已复制'
  },
  ru: {
    'Đã tích {0}/{1} lượt': 'Собрано {0}/{1} отметок',
    'còn {0} lượt nữa được thưởng': 'ещё {0} до подарка',
    'Mỗi {0} trả bằng chuyển khoản tại ghế = 1 lượt tích.': 'Каждые {0}, оплаченные банковским переводом у кресла = 1 отметка.',
    'Tiêu bằng số dư ví không tích lượt — tiền nạp đã được khuyến mãi sẵn rồi.': 'Оплата с баланса кошелька не даёт отметок — при пополнении бонус уже начислен.',
    '<div class="card"><h2>Hoặc trả bằng chuyển khoản</h2>': '<div class="card"><h2>Или оплатить банковским переводом</h2>',
    '<p class="mut" style="margin:0 0 10px">Bấm một gói — hiện mã QR ngân hàng. Trả xong ': '<p class="mut" style="margin:0 0 10px">Нажмите пакет — появится банковский QR-код. После оплаты ',
    '<b>ghế chạy ngay</b>, không phải chờ đếm.</p>': '<b>кресло запускается сразу</b> — ждать отсчёта не нужно.</p>',
    '🎁 Trả bằng chuyển khoản ở đây được TÍCH LƯỢT vào ví của anh/chị.': '🎁 Оплата банковским переводом здесь даёт ОТМЕТКУ в вашем кошельке.',
    '🎁 Trả bằng chuyển khoản được tích lượt ưu đãi — nhưng phải mở ví ở trên trước.': '🎁 Банковские переводы дают отметки лояльности — но сначала откройте кошелёк выше.',
    'Quét thẳng mã QR trên màn hình ghế thì hệ thống không biết ai trả, nên không tích được.': 'Если сканировать QR-код прямо с экрана кресла, система не знает, кто заплатил, и отметка не начисляется.',
    '<button id="ve-dau" style="width:100%;margin-top:14px">Xong</button></div></div>': '<button id="ve-dau" style="width:100%;margin-top:14px">Готово</button></div></div>',
    'Đang dựng mã QR…': 'Создаём QR-код…',
    'Không đặt được lượt.': 'Не удалось оформить сеанс.',
    'Chuyển khoản để chạy ghế {0}': 'Перевод для запуска кресла {0}',
    '⚠️ Vui lòng NGỒI LÊN GHẾ trước khi chuyển khoản.': '⚠️ Пожалуйста, СЯДЬТЕ В КРЕСЛО перед переводом.',
    'Trả xong ghế {0} chạy ngay trong vài giây — hệ thống gửi lệnh đúng lúc ngân hàng báo có tiền.': 'После оплаты кресло {0} запускается за считаные секунды — команда уходит в момент подтверждения банком.',
    '<b style="color:#f0b429">đúng nội dung</b> bên dưới. Ghế chạy ngay khi tiền về.</p>': '<b style="color:#f0b429">точное назначение платежа</b> ниже. Кресло запустится, как только придут деньги.</p>',
    '<div class="card"><h2>Đã nhận tiền — ghế đang chạy</h2>': '<div class="card"><h2>Оплата получена — кресло работает</h2>',
    'Ghế <b>{0}</b> chạy ngay bây giờ. Mời anh/chị ngồi thoải mái.': 'Кресло <b>{0}</b> запускается. Устраивайтесь поудобнее.',
    '<p class="mut" style="margin:12px 0 0">Lần sau mở ví trước khi trả thì lượt này ': '<p class="mut" style="margin:12px 0 0">В следующий раз откройте кошелёк перед оплатой, и этот сеанс ',
    'được tính vào chương trình tích lượt.</p>': 'будет учтён в программе лояльности.</p>',
    'Anh/chị có {0} phần quà chưa nhận': 'Вас ждёт подарков: {0}',
    'Mời anh/chị ra quầy, đọc số điện thoại để nhân viên trao quà.': 'Подойдите к стойке и назовите номер телефона, чтобы получить подарок.',
    'Của tôi': 'Мои',
    'Nhập số điện thoại và PIN — hiện cả số dư ví lẫn mã đã mua.': 'Введите номер телефона и PIN — покажем баланс кошелька и ваши коды.',
    'Xem của tôi': 'Показать мои',
    'Số này chưa mua mã lẻ nào — chỉ có ví.': 'Для этого номера нет отдельных кодов — только кошелёк.',
    '⚠️ Vui lòng NGỒI LÊN GHẾ trước khi bấm chọn gói.': '⚠️ Пожалуйста, СЯДЬТЕ В КРЕСЛО перед выбором пакета.',
    'Bấm xong màn đếm 5 giây rồi ghế chạy. Lệnh đã gửi ngay từ lúc bấm, nên tới 0 là ghế chạy luôn.': 'После нажатия идёт отсчёт 5 секунд, затем кресло запускается. Команда отправляется сразу при нажатии, поэтому на 0 кресло стартует немедленно.',
    'Ghế sắp chạy — mời anh/chị ngồi lên ghế': 'Кресло запускается — пожалуйста, садитесь',
    ' (được thêm ': ' (бонус ',
    ' giờ': ' ч',
    ' ngày': ' дн',
    ' ngày kể từ bây giờ.</b>': ' дн. с этого момента.</b>',
    ' ngày kể từ lúc mua.</b>': ' дн. после покупки.</b>',
    ' ngày.</b>': ' дн.</b>',
    ' ngày</b> — ở bất kỳ ghế nào, không hết hạn': ' дн.</b> — в любом кресле, без срока',
    ' phút': ' мин',
    ' phút</div>': ' мин</div>',
    ' · ghế ': ' · кресло ',
    ' đang trong hạn chờ': ' на удержании',
    ' đang trong hạn chờ</b>': ' на удержании</b>',
    ' — dùng được sau ': ' — доступно через ',
    ' — nhận <b>': ' — получите <b>',
    ' — tiết kiệm ': ' — экономия ',
    '">Chép</button></div>': '">Копировать</button></div>',
    '</b> vào ví': '</b> в кошелёк',
    '</b> — nhận ': '</b> — получите ',
    '</div><button id="t-thoat" style="width:100%;margin-top:6px">Đổi số điện thoại</button>': '</div><button id="t-thoat" style="width:100%;margin-top:6px">Другой номер</button>',
    '<b style="color:#f0b429">đúng nội dung</b> bên dưới. Mã hiện ra ngay tại đây khi tiền về.</p>': '<b style="color:#f0b429">точное назначение</b> ниже. Код появится здесь сразу после зачисления.</p>',
    '<b style="color:#f0b429">⏳ Mã dùng được sau ': '<b style="color:#f0b429">⏳ Код доступен через ',
    '<b style="color:#f0b429">⏳ Số dư nạp dùng được sau ': '<b style="color:#f0b429">⏳ Баланс доступен через ',
    '<b>quét từ thư viện ảnh</b>.</p>': '<b>сканировать из галереи</b>.</p>',
    '<br><b>Ví đang tạm khoá — anh/chị báo nhân viên giúp.</b>': '<br><b>Кошелёк заблокирован — обратитесь к персоналу.</b>',
    '<br><b>⏳ Dùng được sau ': '<br><b>⏳ Доступно через ',
    '<br><span class="mut">Sai PIN hay quên PIN đều ra câu này. ': '<br><span class="mut">Неверный или забытый PIN даёт то же сообщение. ',
    '<br>Muốn tiêu: <b>quét mã QR dán trên ghế</b>, nhập số điện thoại và PIN.</div>': '<br>Чтобы потратить: <b>отсканируйте QR на кресле</b>, введите номер и PIN.</div>',
    '<br>⏳ Đang trong hạn chờ: <b>': '<br>⏳ На удержании: <b>',
    '<button id="d-ok" class="chinh" style="margin-top:14px">Dùng mã, chạy ghế</button>': '<button id="d-ok" class="chinh" style="margin-top:14px">Применить код и запустить</button>',
    '<button id="huy" style="width:100%">Đổi gói khác</button>': '<button id="huy" style="width:100%">Другой пакет</button>',
    '<button id="n-mua" class="chinh">Nạp ngay</button>': '<button id="n-mua" class="chinh">Пополнить</button>',
    '<button id="q-ok" style="width:100%;margin-top:14px">Đặt PIN mới</button>': '<button id="q-ok" style="width:100%;margin-top:14px">Задать новый PIN</button>',
    '<button id="qr-tai">⬇ Tải ảnh mã QR</button>': '<button id="qr-tai">⬇ Скачать QR</button>',
    '<button id="t-mo" class="chinh" style="margin-top:14px">Mở ví</button>': '<button id="t-mo" class="chinh" style="margin-top:14px">Открыть кошелёк</button>',
    '<button id="ve-dau" style="width:100%;margin-top:14px">Mua thêm</button></div></div>': '<button id="ve-dau" style="width:100%;margin-top:14px">Купить ещё</button></div></div>',
    '<div class="card"><h2>Dùng mã cho ghế</h2>': '<div class="card"><h2>Использовать код у кресла</h2>',
    '<div class="card"><h2>Hoặc dùng mã giảm giá</h2>': '<div class="card"><h2>Или используйте промокод</h2>',
    '<div class="card"><h2>Quên PIN?</h2>': '<div class="card"><h2>Забыли PIN?</h2>',
    '<div class="card"><h2>Thông tin nhận mã</h2>': '<div class="card"><h2>Данные для получения кода</h2>',
    '<div class="card"><h2>Trả bằng số dư ví</h2>': '<div class="card"><h2>Оплата с кошелька</h2>',
    '<div class="card"><h2>Ví của anh/chị</h2>': '<div class="card"><h2>Ваш кошелёк</h2>',
    '<div class="card"><h2>Đã nhận tiền — mã của anh/chị đây</h2>': '<div class="card"><h2>Платёж получен — вот ваш код</h2>',
    '<div class="card"><h2>Đã nhận tiền — ví đã được cộng</h2>': '<div class="card"><h2>Платёж получен — кошелёк пополнен</h2>',
    '<div class="card"><p class="mut">Hiện chưa mở bán gói nạp.</p></div>': '<div class="card"><p class="mut">Пакеты пополнения пока не продаются.</p></div>',
    '<div class="card"><p class="mut">Đang tải bảng giá…</p></div>': '<div class="card"><p class="mut">Загрузка цен…</p></div>',
    '<div class="cho" id="cho">⏳ Đang chờ tiền về…</div>': '<div class="cho" id="cho">⏳ Ожидаем поступление…</div>',
    '<div class="ck nhan" style="display:block"><div class="nh">Chưa biết ghế nào</div>': '<div class="ck nhan" style="display:block"><div class="nh">Кресло не определено</div>',
    '<div class="ck nhan"><div style="flex:1;min-width:0"><div class="nh">Ghế đang ngồi</div>': '<div class="ck nhan"><div style="flex:1;min-width:0"><div class="nh">Ваше кресло</div>',
    '<div class="deal"><b>Giảm tới ': '<div class="deal"><b>Скидка до ',
    '<div class="deal"><b>Nạp càng nhiều, lợi càng lớn — tới ': '<div class="deal"><b>Чем больше пополнение, тем больше бонус — до ',
    '<div class="g">chụp lại màn hình này giúp em</div></div>': '<div class="g">сделайте скриншот</div></div>',
    '<div class="g">đã dùng ': '<div class="g">использован ',
    '<div class="mo" style="color:#b32d2e">chưa đủ số dư</div>': '<div class="mo" style="color:#b32d2e">недостаточно средств</div>',
    '<div class="mo">được thêm ': '<div class="mo">бонус ',
    '<div class="mut" style="margin-top:5px">Đây là điều kiện của giá đã giảm: mua trước thì ': '<div class="mut" style="margin-top:5px">Это условие скидки: покупка заранее ',
    '<div class="mut" style="margin-top:5px">Đây là điều kiện của phần được tặng thêm: nạp ': '<div class="mut" style="margin-top:5px">Это условие бонуса: пополните ',
    '<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>, phần còn lại ': '<div class="mut" style="margin:-4px 0 0">Сохраняем <b>только последние 4 цифры</b>, ',
    '<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>.</div>': '<div class="mut" style="margin:-4px 0 0">Сохраняем <b>только последние 4 цифры</b>.</div>',
    '<div class="mut" style="margin:12px 0 6px"><b>Gần đây</b></div>': '<div class="mut" style="margin:12px 0 6px"><b>Последние</b></div>',
    '<div class="ok" style="margin-top:12px">Tiêu được ngay: ': '<div class="ok" style="margin-top:12px">Доступно сейчас: ',
    '<div class="ok" style="margin:0 0 12px">Số dư tiêu được: ': '<div class="ok" style="margin:0 0 12px">Доступный баланс: ',
    '<div class="ok" style="margin:0 0 12px">Trả <b>': '<div class="ok" style="margin:0 0 12px">Оплатите <b>',
    '<div class="ok">Mã <b>không hết hạn</b>, dùng được ở <b>bất kỳ ghế nào</b>. ': '<div class="ok">Коды <b>не истекают</b> и работают в <b>любом кресле</b>. ',
    '<div class="ok">Ví của anh/chị nay có <b style="color:#f0b429">': '<div class="ok">На вашем кошельке теперь <b style="color:#f0b429">',
    '<div class="ten">Nạp ': '<div class="ten">Пополнить ',
    '<div style="margin-top:6px;line-height:1.5">Hãy <b>quét mã QR dán trên chính cái ghế ': '<div style="margin-top:6px;line-height:1.5">Пожалуйста, <b>отсканируйте QR-наклейку на том кресле, ',
    '<div>Số dư dùng được ở <b>bất kỳ ghế nào</b>, tiêu lẻ từng lượt, không hết hạn</div></div>': '<div>Баланс работает в <b>любом кресле</b>, тратится посеансно, без срока</div></div>',
    '<input id="cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">': '<input id="cc" type="tel" inputmode="numeric" placeholder="можно не заполнять" autocomplete="off">',
    '<input id="n-cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">': '<input id="n-cc" type="tel" inputmode="numeric" placeholder="можно не заполнять" autocomplete="off">',
    '<label>Mã giảm giá</label>': '<label>Промокод</label>',
    '<label>PIN 4 số — ví đã có thì nhập <b>đúng PIN cũ</b></label>': '<label>PIN из 4 цифр — для существующего кошелька введите <b>старый PIN</b></label>',
    '<label>PIN 4 số</label>': '<label>PIN из 4 цифр</label>',
    '<label>PIN mới (4 số)</label>': '<label>Новый PIN (4 цифры)</label>',
    '<label>Số căn cước (đã khai lúc mua)</label>': '<label>Номер документа (как при покупке)</label>',
    '<label>Số căn cước — <b>không bắt buộc</b>, chỉ để lấy lại PIN nếu quên</label>': '<label>Номер документа — <b>необязательно</b>, только для восстановления PIN</label>',
    '<label>Số lượng</label>': '<label>Количество</label>',
    '<label>Số điện thoại</label>': '<label>Номер телефона</label>',
    '<label>Đặt PIN 4 số — để lần sau tra lại mã của mình</label>': '<label>Задайте PIN из 4 цифр — чтобы позже найти свои коды</label>',
    '<p class="mut" style="margin:-4px 0 10px">Quên PIN thì <b>gọi nhân viên</b> — nhân viên tra ': '<p class="mut" style="margin:-4px 0 10px">Забыли PIN? <b>Обратитесь к персоналу</b> — ',
    '<p class="mut" style="margin:0 0 10px"><b>Bấm một gói</b> — hệ thống trừ thẳng số dư ': '<p class="mut" style="margin:0 0 10px"><b>Нажмите пакет</b> — сумма спишется с баланса ',
    '<p class="mut" style="margin:0 0 10px">Nhập số điện thoại và PIN để thấy số dư và ': '<p class="mut" style="margin:0 0 10px">Введите номер телефона и PIN, чтобы увидеть баланс и ',
    '<p class="mut" style="margin:0 0 10px">Nếu lúc mua có khai số căn cước thì tự đặt PIN mới ': '<p class="mut" style="margin:0 0 10px">Если при покупке указали номер документа, можно задать новый PIN. ',
    '<p class="mut" style="margin:0 0 10px">Số dư gắn với <b>số điện thoại</b>. Lần sau tới, ': '<p class="mut" style="margin:0 0 10px">Баланс привязан к <b>номеру телефона</b>. В следующий раз ',
    '<p class="mut" style="margin:0 0 12px">Hoặc chuyển tay: đúng số tiền, ': '<p class="mut" style="margin:0 0 12px">Или переведите вручную: точная сумма, ',
    '<p class="mut" style="margin:6px 0 14px">Không đúng ghế này thì quét lại mã QR trên ghế ': '<p class="mut" style="margin:6px 0 14px">Не то кресло? Отсканируйте QR на кресле, ',
    '<p class="mut" style="margin:6px 0 14px;text-align:center">Quét bằng app ngân hàng. ': '<p class="mut" style="margin:6px 0 14px;text-align:center">Отсканируйте банковским приложением. ',
    '<p class="mut">Không còn mã nào chưa dùng.</p>': '<p class="mut">Неиспользованных кодов нет.</p>',
    '<p class="mut">Trang này cố ý <b>không</b> cho chọn ghế từ danh sách: chọn lộn là mã ': '<p class="mut">Эта страница намеренно <b>не</b> даёт выбрать кресло из списка: ошибётесь — и код ',
    '<p class="mut">Vẫn mua thêm mã được ở mục <b>Mua mã</b> — mua thì không cần biết ghế.</p>': '<p class="mut">Купить код можно в разделе <b>Промокод</b> — кресло не нужно.</p>',
    '>Của tôi</button>': '>Мои</button>',
    '>Dùng tại ghế</button>': '>У кресла</button>',
    '>Mua mã</button>': '>Промокод</button>',
    '>Nạp ví</button>': '>Пополнить</button>',
    'Chuyển khoản để nhận mã': 'Перевод для получения кода',
    'Chuyển khoản để nạp ví': 'Перевод для пополнения',
    'Chép dòng này:': 'Скопируйте:',
    'Chưa biết ghế nào — quét mã QR trên ghế đang ngồi giúp em.': 'Кресло не определено — отсканируйте QR на своём кресле.',
    'Chọn một gói nạp phía trên.': 'Выберите пакет пополнения выше.',
    'Chọn một gói nạp trước nhé.': 'Сначала выберите пакет пополнения.',
    'Chọn một gói trước nhé.': 'Сначала выберите пакет.',
    'Gọi nhân viên, đọc số điện thoại — nhân viên tra được mã giúp anh/chị.</span>': 'Обратитесь к персоналу с номером телефона — они найдут ваш код.</span>',
    'Không chạy được.': 'Не удалось запустить.',
    'Không mở được ví.': 'Не удалось открыть кошелёк.',
    'Không tra được.': 'Не удалось найти.',
    'Không tìm thấy.': 'Не найдено.',
    'Không tạo được đơn nạp.': 'Не удалось создать пополнение.',
    'Không tạo được đơn.': 'Не удалось создать заказ.',
    'Không đặt lại được.': 'Не удалось сбросить.',
    'Mua hôm nay, dùng bất cứ lúc nào, ở bất kỳ ghế nào': 'Купите сегодня — используйте когда угодно и в любом кресле',
    'Mua mã giảm giá': 'Купить промокод',
    'Mua trước, dùng sau <b>': 'Купите сейчас, используйте через <b>',
    'Máy không cho tải ảnh. Anh/chị chụp màn hình mã QR này, rồi trong app ngân hàng ': 'Устройство заблокировало скачивание. Сделайте скриншот QR, затем в банковском приложении ',
    'Mã không dùng được.': 'Код недействителен.',
    'Mạng đang chập chờn. Thử lại giúp em, hoặc gọi nhân viên.': 'Сеть нестабильна. Попробуйте снова или обратитесь к персоналу.',
    'Ngân hàng / Số tài khoản': 'Банк / Номер счёта',
    'Nội dung chuyển khoản': 'Назначение платежа',
    'PIN phải gồm đúng 4 chữ số.': 'PIN должен состоять из 4 цифр.',
    'Phải trả: <b style="color:#f0b429;font-size:18px">': 'Итого: <b style="color:#f0b429;font-size:18px">',
    'Quên mã thì vào mục <b>Mã của tôi</b>, nhập số điện thoại và PIN vừa đặt.': 'Потеряли код? Откройте <b>Мои коды</b> и введите номер телефона и PIN.',
    'Số tiền': 'Сумма',
    'Trả: <b style="color:#f0b429;font-size:18px">': 'К оплате: <b style="color:#f0b429;font-size:18px">',
    'anh/chị đang ngồi</b> — mã đó cho hệ thống biết đúng ghế, khỏi phải chọn.</div></div>': 'в котором вы сидите</b> — код сам сообщит нужное кресло.</div></div>',
    'chạy cho người khác, mà mã thì mất rồi. Tem trên ghế mờ hay bong thì gọi nhân viên — ': 'запустит чужое кресло, а код пропадёт. Если наклейка стёрлась — обратитесь к персоналу: ',
    'chọn "quét từ thư viện ảnh".': 'выберите «сканировать из галереи».',
    'các gói bấm được.</p>': 'доступные пакеты.</p>',
    'còn dùng được': 'ещё действует',
    'không được ghi lại ở đâu cả.</div>': 'остальное нигде не хранится.</div>',
    'mình đang ngồi.</p>': 'в котором вы сидите.</p>',
    'nhân viên chạy mã giúp được.</p>': 'персонал активирует код за вас.</p>',
    'nhập lại số này và PIN là tiêu tiếp.</p>': 'введите этот номер и PIN, чтобы продолжить.</p>',
    'rẻ hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế với giá gốc.</div></div>': 'дешевле в обмен на ожидание. Нужно сегодня — платите у кресла по полной цене.</div></div>',
    'trước thì lợi hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế.</div></div>': 'выгоднее в обмен на ожидание. Нужно сегодня — платите прямо у кресла.</div></div>',
    'và ghế chạy ngay.</p>': 'и кресло сразу запустится.</p>',
    'Đang kiểm mã…': 'Проверка кода…',
    'Đang kiểm…': 'Проверка…',
    'Đang mở ví…': 'Открываем кошелёк…',
    'Đang tra…': 'Поиск…',
    'Đang tạo đơn…': 'Создаём заказ…',
    'Đang xem trên chính máy này thì bấm <b>Tải ảnh mã QR</b>, rồi trong app ngân hàng chọn ': 'Смотрите с этого же телефона? Нажмите <b>Скачать QR</b>, затем в банковском приложении выберите ',
    'đ': '₫',
    'được bằng số điện thoại và đọc mã giúp anh/chị.</p>': 'персонал найдёт код по номеру телефона.</p>',
    'được. Không khai thì gọi nhân viên — nhân viên tra bằng số điện thoại.</p>': 'Если нет — обратитесь к персоналу, они найдут по номеру телефона.</p>',
    '⏳ dùng được từ ': '⏳ доступно с ',
    '✓ Đã chép': '✓ Скопировано'
  }
};

var NGON = [
  { ma:'vi', ten:'VI', du:'Tiếng Việt' },
  { ma:'en', ten:'EN', du:'English' },
  { ma:'zh', ten:'中文', du:'中文' },
  { ma:'ru', ten:'RU', du:'Русский' }
];
var NN = 'vi';
try { var _n = localStorage.getItem('vhg_nn'); if (_n && TU[_n]) NN = _n; } catch (e) {}
/* Không nhớ gì thì đoán theo ngôn ngữ trình duyệt — khách Trung/Nga mở trang là thấy tiếng
   mình luôn, khỏi phải tìm nút. Đoán sai thì họ bấm một cái là xong. */
if (NN === 'vi') {
  try {
    var _t = (navigator.language || '').toLowerCase();
    if (_t.indexOf('zh') === 0) NN = 'zh';
    else if (_t.indexOf('ru') === 0) NN = 'ru';
    else if (_t.indexOf('vi') !== 0 && _t) NN = 'en';
  } catch (e) {}
}
function L(s){
  if (NN === 'vi') return s;
  var b = TU[NN];
  return (b && Object.prototype.hasOwnProperty.call(b, s)) ? b[s] : s;
}
/* Câu có chèn số: dịch phần chữ rồi mới thay {0}, {1}… — dịch sau khi đã chèn số thì không
   khoá nào khớp nữa. */
function Lf(s){
  var t = L(s), a = arguments;
  return t.replace(/\{(\d)\}/g, function(_, i){ var v = a[Number(i) + 1]; return v === undefined ? '' : v; });
}
function veNgon(){
  var h = '<div class="nn">';
  NGON.forEach(function(g){
    h += '<button data-nn="' + g.ma + '"' + (NN === g.ma ? ' class="on"' : '')
      + ' title="' + esc(g.du) + '">' + esc(g.ten) + '</button>';
  });
  return h + '</div>';
}
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
  if (ngay > 0) return ngay + L(' ngày') + (gio > 0 ? ' ' + gio + L(' giờ') : '');
  if (gio > 0)  return gio + L(' giờ');
  return Math.max(1, Math.ceil(g / 60)) + L(' phút');
}

function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){
  return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
function tien(n){ return (Number(n)||0).toLocaleString('vi-VN') + L('đ'); }

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
    if (!r) r = { ok:false, error:L('Mạng đang chập chờn. Thử lại giúp em, hoặc gọi nhân viên.') };
    xong(r);
  };
  x.send(JSON.stringify(d));
}

/* Sao chép — có đường lui. `navigator.clipboard` chỉ chạy trên HTTPS và trên một số trình duyệt;
   không có đường lui thì nút bấm không làm gì cả và khách không hiểu vì sao. */
function chep(txt, nut){
  function xong(){ var cu = nut.textContent; nut.textContent = L('✓ Đã chép');
    setTimeout(function(){ nut.textContent = cu; }, 1400); }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(txt).then(xong, function(){ tayChep(txt, xong); });
  } else { tayChep(txt, xong); }
}
function tayChep(txt, xong){
  var o = document.createElement('textarea');
  o.value = txt; o.style.position='fixed'; o.style.opacity='0';
  document.body.appendChild(o); o.select();
  try { document.execCommand('copy'); xong(); } catch(e){ prompt(L('Chép dòng này:'), txt); }
  document.body.removeChild(o);
}

function dau(){
  /* Ô chọn ngôn ngữ ở TRÊN CÙNG, trước cả tiêu đề: khách không đọc được tiếng Việt thì thứ đầu
     tiên họ cần là cái nút đổi tiếng, không phải cái tiêu đề họ không hiểu. */
  return veNgon()
    + '<div class="hero"><div class="o">🎁</div>'
    + '<h1>' + L('Mua mã giảm giá') + '</h1>'
    + '<div class="sub">' + esc(TEN) + '</div></div>';
}

function tab(){
  /* Tab "Nạp ví" chỉ hiện khi anh Thắng CÓ khai gói nạp. Chưa khai mà vẫn hiện là khách bấm
     vào một trang trống — tệ hơn hẳn việc không có tab. */
  var coNap = !!(D && D.goi_nap && D.goi_nap.length);
  var coMa  = !D || D.ban_ma !== 0;          // chưa tải xong thì cứ hiện, tránh tab nhấp nháy
  return '<div class="tabs">'
    + (coNap ? '<button data-tab="nap"' + (TAB==='nap'?' class="on"':'') + L('>Nạp ví</button>') : '')
    + (coMa ? '<button data-tab="mua"' + (TAB==='mua'?' class="on"':'') + L('>Mua mã</button>') : '')
    + '<button data-tab="cua-toi"' + (TAB==='cua-toi'?' class="on"':'') + L('>Của tôi</button>')
    + '<button data-tab="dung"' + (TAB==='dung'?' class="on"':'') + L('>Dùng tại ghế</button>')
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
    return L('<div class="card"><p class="mut">Hiện chưa mở bán gói nạp.</p></div>');
  }
  var cho = (D.cho_ngay || 0), h = '';
  var loiMax = 0;
  D.goi_nap.forEach(function(g){ if (g.loi_pt > loiMax) loiMax = g.loi_pt; });
  h += L('<div class="deal"><b>Nạp càng nhiều, lợi càng lớn — tới ') + loiMax + '%</b>'
    + L('<div>Số dư dùng được ở <b>bất kỳ ghế nào</b>, tiêu lẻ từng lượt, không hết hạn</div></div>');
  if (cho > 0) {
    h += '<div class="card" style="border-color:rgba(240,180,41,.45)">'
      + L('<b style="color:#f0b429">⏳ Số dư nạp dùng được sau ') + cho + L(' ngày.</b>')
      + L('<div class="mut" style="margin-top:5px">Đây là điều kiện của phần được tặng thêm: nạp ')
      + L('trước thì lợi hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế.</div></div>');
  }
  h += '<div class="goi">';
  D.goi_nap.forEach(function(g, i){
    h += '<div class="g' + (NAP === i ? ' chon' : '') + '" data-nap="' + i + '">'
      + '<span class="vip">+' + g.loi_pt + '%</span>'
      + L('<div class="ten">Nạp ') + tien(g.nap) + '</div>'
      + L('<div class="mo">được thêm ') + tien(g.them) + '</div>'
      + '<div class="gia"><span class="moi">' + tien(g.nhan) + '</span>'
      + '<span class="cu">' + tien(g.nap) + '</span></div></div>';
  });
  h += '</div>';
  h += L('<div class="card"><h2>Ví của anh/chị</h2>')
    + L('<p class="mut" style="margin:0 0 10px">Số dư gắn với <b>số điện thoại</b>. Lần sau tới, ')
    + L('nhập lại số này và PIN là tiêu tiếp.</p>')
    + L('<label>Số điện thoại</label>')
    + '<input id="n-sdt" type="tel" inputmode="numeric" placeholder="0909 123 456" autocomplete="tel">'
    /* ⚠️ Ví ĐÃ CÓ thì đây là PIN CŨ, không phải PIN mới — nói rõ, không thì khách gõ một PIN
       khác rồi bị từ chối mà không hiểu vì sao. */
    + L('<label>PIN 4 số — ví đã có thì nhập <b>đúng PIN cũ</b></label>')
    + '<input id="n-pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    + L('<label>Số căn cước — <b>không bắt buộc</b>, chỉ để lấy lại PIN nếu quên</label>')
    + L('<input id="n-cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">')
    + L('<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>.</div>')
    + '<div id="n-tong" class="mut" style="margin:12px 0 4px"></div>'
    + L('<button id="n-mua" class="chinh">Nạp ngay</button>')
    + '<div class="err" id="e"></div></div>';
  return h;
}

// ------------------------------------------------------------------ mua
function veMua(){
  if (DON) return veTraTien();
  if (!D) return L('<div class="card"><p class="mut">Đang tải bảng giá…</p></div>');

  var max = 0;
  D.goi.forEach(function(g){ if (g.giam_pt > max) max = g.giam_pt; });
  var h = '';
  var cho = (D.cho_ngay || 0);
  if (max > 0) {
    h += L('<div class="deal"><b>Giảm tới ') + max + '%</b><div>'
      + (cho > 0
          ? L('Mua trước, dùng sau <b>') + cho + L(' ngày</b> — ở bất kỳ ghế nào, không hết hạn')
          : L('Mua hôm nay, dùng bất cứ lúc nào, ở bất kỳ ghế nào'))
      + '</div></div>';
  }
  /* 🔴 NÓI ĐIỀU KIỆN CHỜ TRƯỚC KHI KHÁCH TRẢ TIỀN, và nói to. Đây là thứ dễ làm khách thấy mình
     bị gạt nhất nếu chỉ hiện ra lúc họ đã trả xong rồi quét không được. Giảm giá là để đổi lấy
     việc trả tiền trước — đổi được cái gì thì phải nói rõ từ đầu. */
  if (cho > 0) {
    h += '<div class="card" style="border-color:rgba(240,180,41,.45)">'
      + L('<b style="color:#f0b429">⏳ Mã dùng được sau ') + cho + L(' ngày kể từ lúc mua.</b>')
      + L('<div class="mut" style="margin-top:5px">Đây là điều kiện của giá đã giảm: mua trước thì ')
      + L('rẻ hơn, đổi lại là chờ. Cần dùng ngay hôm nay thì trả thẳng tại ghế với giá gốc.</div></div>');
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

  h += L('<div class="card"><h2>Thông tin nhận mã</h2>')
    + L('<label>Số điện thoại</label>')
    + '<input id="sdt" type="tel" inputmode="numeric" placeholder="0909 123 456" autocomplete="tel">'
    /* PIN để lần sau tra lại mã. Nói rõ CÔNG DỤNG ngay cạnh ô, không nhét vào chữ nhỏ: khách
       không hiểu để làm gì thì gõ bừa, rồi hôm sau không tra được mã đã mua. */
    + L('<label>Đặt PIN 4 số — để lần sau tra lại mã của mình</label>')
    + '<input id="pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    /* Căn cước KHÔNG bắt buộc, và nói rõ chỉ giữ 4 số cuối. Bắt buộc căn cước để mua một lượt
       massage 85.000đ là mất khách ngay bước đầu; còn giấu chuyện chỉ giữ 4 số cuối thì khách
       ngại khai, mà đó lại đúng là thứ làm họ yên tâm. */
    + L('<label>Số căn cước — <b>không bắt buộc</b>, chỉ để lấy lại PIN nếu quên</label>')
    + L('<input id="cc" type="tel" inputmode="numeric" placeholder="để trống cũng được" autocomplete="off">')
    + L('<div class="mut" style="margin:-4px 0 0">Hệ thống <b>chỉ lưu 4 số cuối</b>, phần còn lại ')
    + L('không được ghi lại ở đâu cả.</div>')
    + L('<label>Số lượng</label>')
    + '<input id="sl" type="number" min="1" max="10" value="' + SL + '">'
    + '<div id="tong" class="mut" style="margin:12px 0 4px"></div>'
    + '<button id="mua" class="chinh">Mua ngay</button>'
    + '<div class="err" id="e"></div></div>';
  return h;
}

function veTraTien(){
  var laNap = (DON.loai === 'nap');
  var laGhe = (DON.loai === 'ghe');
  var h = '<div class="card"><h2>'
    + (laGhe ? Lf('Chuyển khoản để chạy ghế {0}', DON.ma_may)
             : (laNap ? L('Chuyển khoản để nạp ví') : L('Chuyển khoản để nhận mã'))) + '</h2>';
  /* 🔴 LỜI DẶN NGỒI LÊN GHẾ ĐỨNG NGAY ĐÂY, trước cả mã QR. Trả xong là ghế chạy trong vài giây,
     không có đếm ngược nào để kịp đứng dậy đi chỗ khác. */
  if (laGhe) {
    h += '<div class="dan">' + L('⚠️ Vui lòng NGỒI LÊN GHẾ trước khi chuyển khoản.')
      + '<div class="dan-p">'
      + Lf('Trả xong ghế {0} chạy ngay trong vài giây — hệ thống gửi lệnh đúng lúc ngân hàng báo có tiền.', DON.ma_may)
      + '</div></div>';
  }
  /* Nhắc lại NHẬN ĐƯỢC BAO NHIÊU ngay trên mã QR. Khách đang ở bước rút ví ra trả tiền — đó
     đúng là lúc con số "được 120.000đ" cần đứng trước mắt, không phải lúc họ mới chọn gói. */
  if (laNap) {
    h += L('<div class="ok" style="margin:0 0 12px">Trả <b>') + tien(DON.phai_tra) + L('</b> — nhận ')
      + '<b style="color:#f0b429">' + tien(DON.nhan_tien) + L('</b> vào ví')
      + (DON.them > 0 ? L(' (được thêm ') + tien(DON.them) + ')' : '') + '.</div>';
  }
  /* MÃ QR TRƯỚC, chữ chép tay sau. Quét (hoặc chọn ảnh từ thư viện) nhanh và không gõ nhầm được;
     phần chữ là đường dự phòng cho ai muốn gõ tay. */
  if (DON.qr && DON.qr.length) {
    h += '<div class="qr-hop"><canvas id="qr-canvas"></canvas></div>'
      + '<div class="qr-nut">'
      + L('<button id="qr-tai">⬇ Tải ảnh mã QR</button>')
      + '</div>'
      + L('<p class="mut" style="margin:6px 0 14px;text-align:center">Quét bằng app ngân hàng. ')
      + L('Đang xem trên chính máy này thì bấm <b>Tải ảnh mã QR</b>, rồi trong app ngân hàng chọn ')
      + L('<b>quét từ thư viện ảnh</b>.</p>');
  }
  h += L('<p class="mut" style="margin:0 0 12px">Hoặc chuyển tay: đúng số tiền, ')
    + (laGhe ? L('<b style="color:#f0b429">đúng nội dung</b> bên dưới. Ghế chạy ngay khi tiền về.</p>')
             : L('<b style="color:#f0b429">đúng nội dung</b> bên dưới. Mã hiện ra ngay tại đây khi tiền về.</p>'))
    + o_ck(L('Ngân hàng / Số tài khoản'), DON.so_tk, DON.so_tk, '')
    + (DON.ten_tk ? '<div class="mut" style="margin:-4px 0 8px 2px">' + esc(DON.ten_tk) + '</div>' : '')
    + o_ck(L('Số tiền'), tien(DON.phai_tra), String(DON.phai_tra), '')
    /* Ô nội dung tô vàng: đây là thứ sai một ký tự là tiền lạc, còn hai ô trên sai thì ngân hàng
       tự báo lỗi. Hai loại rủi ro khác hẳn nhau nên trông cũng phải khác nhau. */
    + o_ck(L('Nội dung chuyển khoản'), DON.noi_dung, DON.noi_dung, ' nhan')
    + L('<div class="cho" id="cho">⏳ Đang chờ tiền về…</div>')
    + L('<button id="huy" style="width:100%">Đổi gói khác</button>')
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
    + '<button data-chep="' + esc(chep_) + L('">Chép</button></div>');
}

// ------------------------------------------------------------------ mã của tôi
function veCuaToi(){
  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * MỘT Ô NHẬP CHO CẢ VÍ LẪN MÃ.
   *
   * Anh Thắng 23/08/2026: *"cùng 1 ví mà, sao lại ra 2 lần đăng nhập"*. Bản trước có hai thẻ
   * chồng nhau, mỗi thẻ một ô số điện thoại + một ô PIN — cùng số, cùng PIN, cho hai thứ mà với
   * khách là MỘT tài khoản. Gõ hai lần cho một việc là lỗi thiết kế, không phải tính năng.
   *
   * 🔴 QUÊN PIN LÀ CHUYỆN SẼ XẢY RA, không phải nếu. Khách đặt PIN một lần rồi ba tuần sau mới
   *    quay lại. Không nói trước lối ra thì họ gõ mười lần, bị hãm, rồi bỏ đi — mang theo cả số
   *    dư lẫn mã đã trả tiền.
   *
   * ⚠️ KHÔNG làm "quên PIN" tự phục hồi bằng mỗi số điện thoại: số đó người khác đoán được. Tự
   *    đặt lại PIN bằng một thứ đoán được là gỡ đúng cái khoá vừa lắp. Đường căn cước ở khối
   *    dưới, và nhân viên tra hộ — họ nhìn thấy mặt khách.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  return '<div class="card"><h2>' + L('Của tôi') + '</h2>'
    + '<p class="mut" style="margin:0 0 10px">'
    + L('Nhập số điện thoại và PIN — hiện cả số dư ví lẫn mã đã mua.') + '</p>'
    + L('<p class="mut" style="margin:-4px 0 10px">Quên PIN thì <b>gọi nhân viên</b> — nhân viên tra ')
    + L('được bằng số điện thoại và đọc mã giúp anh/chị.</p>')
    + L('<label>Số điện thoại</label>')
    + '<input id="t-sdt" type="tel" inputmode="numeric" placeholder="0909 123 456" value="'
    + esc(nhoSdt()) + '">'
    + L('<label>PIN 4 số</label>')
    + '<input id="t-pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    + '<button id="t-xem" class="chinh" style="margin-top:14px">' + L('Xem của tôi') + '</button>'
    + '<div class="err" id="e"></div><div id="kq"></div></div>'
    /* Ô lấy lại PIN để RIÊNG một khối, dưới khối tra: người nhớ PIN không phải nhìn thấy nó. */
    + L('<div class="card"><h2>Quên PIN?</h2>')
    + L('<p class="mut" style="margin:0 0 10px">Nếu lúc mua có khai số căn cước thì tự đặt PIN mới ')
    + L('được. Không khai thì gọi nhân viên — nhân viên tra bằng số điện thoại.</p>')
    + L('<label>Số điện thoại</label>')
    + '<input id="q-sdt" type="tel" inputmode="numeric" placeholder="0909 123 456">'
    + L('<label>Số căn cước (đã khai lúc mua)</label>')
    + '<input id="q-cc" type="tel" inputmode="numeric" autocomplete="off">'
    + L('<label>PIN mới (4 số)</label>')
    + '<input id="q-pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    + L('<button id="q-ok" style="width:100%;margin-top:14px">Đặt PIN mới</button>')
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
  /* Đang có đơn TRẢ LƯỢT GHẾ chờ tiền về -> màn chuyển khoản chiếm cả tab, y như tab Nạp ví.
     Để nó nằm lẫn giữa các thẻ gói là khách vừa nhìn mã QR vừa nhìn những nút bấm khác cho
     cùng một việc — rồi bấm nhầm sang đường thứ hai và trả tiền hai lần. */
  if (DON && DON.loai === 'ghe') return veTraTien();
  if (!GHE) {
    return L('<div class="card"><h2>Dùng mã cho ghế</h2>')
      + L('<div class="ck nhan" style="display:block"><div class="nh">Chưa biết ghế nào</div>')
      + L('<div style="margin-top:6px;line-height:1.5">Hãy <b>quét mã QR dán trên chính cái ghế ')
      + L('anh/chị đang ngồi</b> — mã đó cho hệ thống biết đúng ghế, khỏi phải chọn.</div></div>')
      + L('<p class="mut">Trang này cố ý <b>không</b> cho chọn ghế từ danh sách: chọn lộn là mã ')
      + L('chạy cho người khác, mà mã thì mất rồi. Tem trên ghế mờ hay bong thì gọi nhân viên — ')
      + L('nhân viên chạy mã giúp được.</p>')
      + L('<p class="mut">Vẫn mua thêm mã được ở mục <b>Mua mã</b> — mua thì không cần biết ghế.</p>')
      + '</div>';
  }
  /* ⚠️ Ghế hiện MỘT LẦN ở đầu trang, không lặp lại trong từng khối: khách chỉ cần biết "mình
     đang ở ghế nào" đúng một lần, lặp lại là nhiễu. */
  /* 🔴 Ô ĐẾM NGƯỢC ĐẶT NGAY ĐẦU TRANG, không nằm dưới các thẻ gói.
     Anh Thắng 23/08/2026: *"đưa màn lên đầu trang nhé"* — ảnh cho thấy bấm xong thì con số đếm
     hiện tuốt dưới đáy, khách phải CUỘN TÌM đúng lúc họ đang xoay người ngồi xuống ghế.
     Để trống thì nó không chiếm chỗ gì; có đếm thì nó là thứ đầu tiên đập vào mắt. */
  var h = '<div id="t-dem"></div>'
    + L('<div class="ck nhan"><div style="flex:1;min-width:0"><div class="nh">Ghế đang ngồi</div>')
    + '<div class="gt">' + esc(GHE) + '</div></div></div>'
    + L('<p class="mut" style="margin:6px 0 14px">Không đúng ghế này thì quét lại mã QR trên ghế ')
    + L('mình đang ngồi.</p>');

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
    h += L('<div class="card"><h2>Trả bằng số dư ví</h2>');
    if (!VI) {
      h += L('<p class="mut" style="margin:0 0 10px">Nhập số điện thoại và PIN để thấy số dư và ')
        + L('các gói bấm được.</p>')
        + L('<label>Số điện thoại</label>')
        + '<input id="t-vsdt" type="tel" inputmode="numeric" placeholder="0909 123 456" value="'
        + esc(nhoSdt()) + '">'
        + L('<label>PIN 4 số</label>')
        + '<input id="t-vpin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
        + L('<button id="t-mo" class="chinh" style="margin-top:14px">Mở ví</button>')
        + '<div class="err" id="t-e"></div>';
    } else {
      var sd = VI.so_du || {};
      h += L('<div class="ok" style="margin:0 0 12px">Số dư tiêu được: ')
        + '<b style="color:#f0b429;font-size:18px">' + tien(sd.dung || 0) + '</b>'
        + (sd.cho > 0
            ? '<br>⏳ ' + tien(sd.cho) + L(' đang trong hạn chờ')
              + (sd.con_cho > 0 ? L(' — dùng được sau ') + docCho(sd.con_cho) : '')
            : '')
        + '</div>'
        /* 🔴 LỜI DẶN ĐỨNG TRƯỚC CÁC THẺ, KHÔNG ĐỨNG SAU.
           Anh Thắng 23/08/2026: *"bổ sung hướng dẫn, trước khi chọn gói. Vui lòng ngồi lên ghế"*.
           Đặt sau các thẻ là khách đã bấm xong mới đọc tới — lúc đó lời dặn thành lời trách. */
        + '<div class="dan">' + L('⚠️ Vui lòng NGỒI LÊN GHẾ trước khi bấm chọn gói.')
        + '<div class="dan-p">'
        + L('Bấm xong màn đếm 5 giây rồi ghế chạy. Lệnh đã gửi ngay từ lúc bấm, nên tới 0 là ghế chạy luôn.')
        + '</div></div>'
        + L('<p class="mut" style="margin:0 0 10px"><b>Bấm một gói</b> — hệ thống trừ thẳng số dư ')
        + L('và ghế chạy ngay.</p>')
        + '<div class="goi">';
      (D.goi || []).forEach(function(g){
        /* Gói quá số dư thì làm mờ và KHÔNG cho bấm — để bấm rồi báo lỗi là bắt khách phát hiện
           điều mà trang đã biết trước. */
        var du = (sd.dung || 0) >= g.menh_gia;
        h += '<div class="g' + (du ? '' : ' het') + '"' + (du ? ' data-tieu="' + g.menh_gia + '"' : '')
          + '>'
          + (g.vip ? '<span class="vip">VVIP</span>' : '')
          + '<div class="ten">' + esc(g.ten || tien(g.menh_gia)) + '</div>'
          + (g.phut > 0 ? '<div class="mo">' + g.phut + L(' phút</div>') : '')
          + '<div class="gia"><span class="moi">' + tien(g.menh_gia) + '</span></div>'
          + (du ? '' : L('<div class="mo" style="color:#b32d2e">chưa đủ số dư</div>'))
          + '</div>';
      });
      h += L('</div><button id="t-thoat" style="width:100%;margin-top:6px">Đổi số điện thoại</button>')
        + '<div class="err" id="t-e"></div><div id="t-kq"></div>';
    }
    h += '</div>';
  }

  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * TRẢ BẰNG CHUYỂN KHOẢN — và đây là đường DUY NHẤT tích được lượt ưu đãi.
   *
   * Anh Thắng 23/08/2026: *"truy cập trang · khách bấm chọn mệnh giá · app tự link nhảy sang ứng
   * dụng ngân hàng và nhập qr thanh toán · khách thanh toán · hệ thống báo thành công · khách
   * được 1 lượt tích · ghế chạy"*.
   *
   * 🔴 KHÔNG CÓ ĐẾM NGƯỢC Ở ĐƯỜNG NÀY. Anh Thắng: *"QR cũng nhận trễ 5s, chứ sau 5s mới gửi lệnh
   *    thì thành 10s rồi"*. Tiền đi qua ngân hàng rồi qua webhook đã mất sẵn vài giây; ghế chạy
   *    NGAY khi webhook chạm tới máy chủ (`VHG_May::xep_cho_chay` gọi thẳng trong lượt webhook).
   *    Vẽ thêm một đồng hồ đếm 5 giây ở đây là cộng thêm 5 giây vào đúng cái độ trễ cả tháng qua
   *    mình đi cắt từng giây — và cộng vào chỗ KHÁCH ĐÃ CHỜ RỒI, khác hẳn đường tiêu ví (ở đó 5
   *    giây chạy SONG SONG với lệnh đã gửi, nên không mất gì).
   *
   * ⚠️ Khách CHƯA đăng nhập vẫn trả được. Chỉ là không có lượt tích — nói thẳng ra, đừng chặn.
   *    Chặn người đang cầm điện thoại định trả tiền là đổi một lượt bán lấy một lượt tích.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  if (D && D.goi && D.goi.length) {
    h += L('<div class="card"><h2>Hoặc trả bằng chuyển khoản</h2>')
      + L('<p class="mut" style="margin:0 0 10px">Bấm một gói — hiện mã QR ngân hàng. Trả xong ')
      + L('<b>ghế chạy ngay</b>, không phải chờ đếm.</p>');
    if (D.tich_bat) {
      h += '<div class="dan">'
        + (VI ? L('🎁 Trả bằng chuyển khoản ở đây được TÍCH LƯỢT vào ví của anh/chị.')
              : L('🎁 Trả bằng chuyển khoản được tích lượt ưu đãi — nhưng phải mở ví ở trên trước.'))
        + '<div class="dan-p">'
        + L('Quét thẳng mã QR trên màn hình ghế thì hệ thống không biết ai trả, nên không tích được.')
        + '</div></div>';
    }
    h += '<div class="goi">';
    (D.goi || []).forEach(function(g){
      h += '<div class="g" data-ghetra="' + g.menh_gia + '">'
        + (g.vip ? '<span class="vip">VVIP</span>' : '')
        + '<div class="ten">' + esc(g.ten || tien(g.menh_gia)) + '</div>'
        + (g.phut > 0 ? '<div class="mo">' + g.phut + L(' phút</div>') : '')
        + '<div class="gia"><span class="moi">' + tien(g.menh_gia) + '</span></div></div>';
    });
    h += '</div><div class="err" id="tg-e"></div></div>';
  }

  /* Khối MÃ đứng sau, và chỉ hiện khi cửa hàng còn bán mã. Đã bán mã trước đây thì mã cũ vẫn
     dùng được — nên khối này CÒN kể cả khi đã tắt bán thêm. */
  h += L('<div class="card"><h2>Hoặc dùng mã giảm giá</h2>')
    + L('<label>Mã giảm giá</label>')
    + '<input id="d-ma" placeholder="ABCD-EFGH" autocapitalize="characters" autocomplete="off">'
    + L('<button id="d-ok" class="chinh" style="margin-top:14px">Dùng mã, chạy ghế</button>')
    + '<div class="err" id="e"></div><div id="kq"></div></div>';

  return h;
}

/* Khối hiện số dư — dùng ở tab "Của tôi". Hiện CẢ HAI cột: tiêu được và đang chờ. Gộp lại
   thành một con số là khách thấy có tiền mà ghế không chạy, rồi tưởng hệ thống nuốt tiền. */
/* Thanh tích lượt — ô vuông đầy dần, không phải một con số.
   🔴 Con số "7/10" bắt người ta làm phép trừ; hàng ô đầy dần thì nhìn cái là biết còn mấy ô.
      Đây là thứ khách liếc qua trong lúc đang ngồi xuống ghế, không phải thứ họ ngồi đọc. */
function veTich(t){
  if (!t || !t.bat) return '';
  var h = '<div class="tich"><div class="tich-o">';
  for (var i = 0; i < t.moc; i++) {
    h += '<span class="' + (i < t.co ? 'on' : '') + '"></span>';
  }
  h += '</div><div class="tich-chu">'
    + Lf('Đã tích {0}/{1} lượt', t.co, t.moc);
  if (t.con > 0) {
    h += ' — ' + Lf('còn {0} lượt nữa được thưởng', t.con);
  }
  /* 🔴 NÓI ĐÚNG ĐƯỜNG NÀO TÍCH ĐƯỢC. Câu cũ ghi "mỗi 10.000đ tiêu tại ghế" — sai kể từ bản
     này: tiêu ví KHÔNG tích (tiền ví đã được khuyến mãi lúc nạp rồi), tiền mặt cũng không.
     Một dòng luật sai ở đây là khách tiêu ba lượt rồi ra hỏi vì sao bộ đếm không nhúc nhích. */
  h += '</div><div class="tich-p">'
    + Lf('Mỗi {0} trả bằng chuyển khoản tại ghế = 1 lượt tích.', tien(t.moi_luot))
    + '<br>' + L('Tiêu bằng số dư ví không tích lượt — tiền nạp đã được khuyến mãi sẵn rồi.')
    + '</div></div>';
  return h;
}

function veSoDu(r){
  var sd = r.so_du || {};
  var h = L('<div class="ok" style="margin-top:12px">Tiêu được ngay: ')
    + '<b style="color:#f0b429;font-size:18px">' + tien(sd.dung || 0) + '</b>';
  if (sd.cho > 0) {
    h += L('<br>⏳ Đang trong hạn chờ: <b>') + tien(sd.cho) + '</b>'
      + (sd.con_cho > 0 ? L(' — dùng được sau ') + docCho(sd.con_cho) : '');
  }
  if (sd.khoa) h += L('<br><b>Ví đang tạm khoá — anh/chị báo nhân viên giúp.</b>');
  h += '</div>';
  h += veTich(r.tich);
  /* Quà VẬT LÝ chưa nhận: nói rõ phải ra quầy lấy, đừng để khách tưởng nó tự vào ví. */
  var qua_cho = (r.qua || []).filter(function(q){ return !q.nhan_luc && q.kieu !== 'luot'; });
  if (qua_cho.length) {
    h += '<div class="qua">🎁 ' + Lf('Anh/chị có {0} phần quà chưa nhận', qua_cho.length)
      + '<div class="qua-p">' + L('Mời anh/chị ra quầy, đọc số điện thoại để nhân viên trao quà.')
      + '</div></div>';
  }
  var so = r.so || [];
  if (so.length) {
    h += L('<div class="mut" style="margin:12px 0 6px"><b>Gần đây</b></div>');
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
  o.innerHTML = L('Phải trả: <b style="color:#f0b429;font-size:18px">') + tien(g.gia_ban * n) + '</b>'
    + (g.giam_pt > 0 ? L(' — tiết kiệm ') + tien((g.menh_gia - g.gia_ban) * n) : '');
}

function noi(){
  [].forEach.call(document.querySelectorAll('[data-nn]'), function(b){
    b.onclick = function(){
      NN = b.getAttribute('data-nn');
      try { localStorage.setItem('vhg_nn', NN); } catch (e) {}
      ve();
    };
  });
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
      ? L('Chọn một gói nạp phía trên.')
      : L('Trả: <b style="color:#f0b429;font-size:18px">') + tien(D.goi_nap[NAP].nap) + '</b>'
        + L(' — nhận <b>') + tien(D.goi_nap[NAP].nhan) + L('</b> vào ví');
  }
  var nMua = document.getElementById('n-mua');
  if (nMua) nMua.onclick = function(){
    var e = document.getElementById('e');
    if (NAP === null) { e.textContent = L('Chọn một gói nạp trước nhé.'); return; }
    var sdt = (document.getElementById('n-sdt').value || '').trim();
    var pin = (document.getElementById('n-pin').value || '').trim();
    if (!/^\d{4}$/.test(pin)) { e.textContent = L('PIN phải gồm đúng 4 chữ số.'); return; }
    if (ban) return;
    ban = true; nMua.disabled = true; e.textContent = L('Đang tạo đơn…');
    goi('dat_nap', { sdt: sdt, pin: pin, cc: (document.getElementById('n-cc')||{}).value || '',
                     nap: D.goi_nap[NAP].nap },
      function(r){
        ban = false; nMua.disabled = false;
        if (!r.ok) { e.textContent = r.error || L('Không tạo được đơn nạp.'); return; }
        DON = r; ve(); soiDon();
      });
  };

  var vXem = document.getElementById('v-xem');
  if (vXem) vXem.onclick = function(){
    var e = document.getElementById('v-e'), kq = document.getElementById('v-kq');
    var sdt = (document.getElementById('v-sdt').value || '').trim();
    var pin = (document.getElementById('v-pin').value || '').trim();
    if (ban) return;
    ban = true; vXem.disabled = true; e.textContent = ''; kq.innerHTML = L('Đang tra…');
    goi('vi', { sdt: sdt, pin: pin }, function(r){
      ban = false; vXem.disabled = false;
      if (!r.ok) { kq.innerHTML = ''; e.textContent = r.error || L('Không tra được.'); return; }
      /* 🔴 GIỮ LẠI PIN VÀ SỐ ĐIỆN THOẠI — xem chú thích dài ở chỗ dựng `VI` trong tab "Của tôi".
         `VI` là thứ tab "Dùng tại ghế" nhìn vào để biết đã đăng nhập hay chưa; thiếu PIN thì tab
         đó hiện đủ số dư và các thẻ bấm được, nhưng bấm vào là máy chủ trả "PIN chưa đúng" và
         ĐẾM MỘT LƯỢT HỎNG — năm lần là khoá 10 phút đúng người vừa nhập đúng PIN. */
      VI = r; VI.pin = pin; VI.sdt = VI.sdt || sdt;
      kq.innerHTML = veSoDu(r);
    });
  };

  /* Mở ví: tra số dư rồi vẽ lại màn thành các thẻ bấm được. PIN giữ trong biến của trang cho
     lượt bấm ngay sau đó — không ghi ra đâu cả, đóng trang là mất. */
  var tMo = document.getElementById('t-mo');
  if (tMo) tMo.onclick = function(){
    var e = document.getElementById('t-e');
    var sdt = (document.getElementById('t-vsdt').value || '').trim();
    var pin = (document.getElementById('t-vpin').value || '').trim();
    if (!/^\d{4}$/.test(pin)) { e.textContent = L('PIN phải gồm đúng 4 chữ số.'); return; }
    if (ban) return;
    ban = true; tMo.disabled = true; e.textContent = L('Đang mở ví…');
    goi('vi', { sdt: sdt, pin: pin }, function(r){
      ban = false; tMo.disabled = false;
      if (!r.ok) { e.textContent = r.error || L('Không mở được ví.'); return; }
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
      ban = true; e.textContent = '';
      /* ══════════════════════════════════════════════════════════════════════════════════════
       * ĐẾM NGƯỢC 5 → 0.
       *
       * Anh Thắng: *"Nếu lỡ bấm đếm 5-4-3-2-1-0 mới chạy ghế, thì lúc này trước 5s hệ thống đã
       * gửi sẵn lệnh, sau 0 là ghế chạy luôn"*.
       *
       * 🔴 LỆNH GỬI NGAY LÚC BẤM, ĐẾM NGƯỢC CHẠY SONG SONG — không phải đếm xong mới gửi.
       *    Ghế mất 2-4 giây để lấy lệnh về. Đếm xong mới gửi là cộng thêm 5 giây nữa vào đúng
       *    cái độ trễ mà cả tháng qua mình đi cắt từng giây. Đếm song song thì 5 giây ấy KHÔNG
       *    mất gì: nó vừa che quãng chờ có thật, vừa cho khách kịp ngồi lên ghế.
       *
       * ⚠️ ĐÂY KHÔNG PHẢI NÚT HUỶ, và không được vẽ ra như nút huỷ. Tới 0 là ghế chạy, dù khách
       *    có bấm gì hay không — tiền đã trừ từ lúc bấm rồi.
       * ═════════════════════════════════════════════════════════════════════════════════════ */
      var con = 5, nhip = null;
      var oDem = document.getElementById('t-dem') || kq;
      var veDem = function(){
        oDem.innerHTML = '<div class="dem"><div class="dem-so">' + con + '</div>'
          + '<div class="dem-chu">' + L('Ghế sắp chạy — mời anh/chị ngồi lên ghế') + '</div></div>';
      };
      veDem();
      /* Cuộn lên đầu NGAY, đừng để khách phải tìm. `try` vì vài trình duyệt cũ không nhận tham
         số dạng đối tượng của `scrollTo`, và một lỗi ở đây làm chết luôn lượt bấm. */
      try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo(0, 0); }
      nhip = setInterval(function(){
        con--;
        if (con >= 0) veDem();
        if (con <= 0) { clearInterval(nhip); nhip = null; }
      }, 1000);
      /* ⚠️ GỬI THẲNG mã ghế trong thân gói, y như lượt `dung` vẫn làm — đừng để máy chủ tự dò
         lại từ địa chỉ. Trang ĐÃ BIẾT ghế rồi; bắt máy chủ suy lại là thêm một chỗ hỏng được,
         và nó đã hỏng thật. Địa chỉ API nay cũng mang ghế, nên đây là lớp thứ hai. */
      goi('tieu', { sdt: VI.sdt || nhoSdt(), pin: VI.pin, menh_gia: mg, ma_may: GHE }, function(r){
        ban = false;
        if (!r.ok) {
          /* Hỏng thì DỪNG ĐẾM NGAY. Để con số tiếp tục chạy về 0 trong khi ghế sẽ không chạy
             là nói dối khách bằng một cái đồng hồ. */
          if (nhip) { clearInterval(nhip); nhip = null; }
          oDem.innerHTML = ''; kq.innerHTML = '';
          e.textContent = r.error || L('Không chạy được.');
          /* Số dư có thể vừa đổi (tiêu ở ghế khác) — tra lại để thẻ mờ đúng thực tế. */
          if (r.so_du) { VI.so_du = r.so_du; ve(); }
          return;
        }
        VI.so_du = r.so_du;
        /* Máy chủ trả lời TRƯỚC khi đếm xong thì chờ nốt — con số đang chạy là lời hứa với
           khách, cắt ngang nó là họ chưa kịp ngồi xuống. */
        var xong = function(){
          /* Câu báo thay chỗ con số đếm — vẫn ở ĐẦU TRANG, nơi khách đang nhìn. Đẩy nó xuống
             cuối là bắt họ cuộn tìm lần thứ hai cho cùng một việc. */
          var bao = '<div class="ok" style="margin:0 0 12px">' + esc(r.thong_bao) + '</div>';
          oDem.innerHTML = bao;
          /* Vẽ lại để số dư và các thẻ mờ khớp ngay, nhưng GIỮ lại câu báo vừa hiện. */
          ve();
          var o2 = document.getElementById('t-dem');
          if (o2) o2.innerHTML = bao;
        };
        if (con > 0) { setTimeout(xong, con * 1000 + 200); } else { xong(); }
      });
    };
  });
  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * BẤM MỘT GÓI -> ĐẶT LƯỢT -> HIỆN MÃ QR NGÂN HÀNG.
   *
   * ⚠️ Số điện thoại + PIN gửi kèm CHỈ KHI ĐÃ MỞ VÍ. Chưa mở thì gửi rỗng, và máy chủ hiểu là
   *    "khách không đăng nhập" — vẫn đặt được lượt, chỉ không tích. Gửi bừa số nhớ trong máy
   *    (`nhoSdt()`) mà không có PIN là máy chủ từ chối cả lượt, tức là chặn đúng người đang
   *    định trả tiền.
   * ══════════════════════════════════════════════════════════════════════════════════════════ */
  [].forEach.call(document.querySelectorAll('[data-ghetra]'), function(b){
    b.onclick = function(){
      if (ban) return;
      var e = document.getElementById('tg-e');
      ban = true; if (e) e.textContent = L('Đang dựng mã QR…');
      goi('dat_ghe', { menh_gia: Number(b.getAttribute('data-ghetra')), ma_may: GHE,
                       sdt: (VI ? (VI.sdt || '') : ''), pin: (VI ? VI.pin : '') }, function(r){
        ban = false;
        if (!r.ok) { if (e) e.textContent = r.error || L('Không đặt được lượt.'); return; }
        DON = r; ve(); soiDon();
        try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (x) { window.scrollTo(0, 0); }
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
    if (CHON === null) { e.textContent = L('Chọn một gói trước nhé.'); return; }
    var sdt = (document.getElementById('sdt').value || '').trim();
    var pin = (document.getElementById('pin').value || '').trim();
    if (!/^\d{4}$/.test(pin)) { e.textContent = L('PIN phải gồm đúng 4 chữ số.'); return; }
    if (ban) return;
    ban = true; mua.disabled = true; e.textContent = L('Đang tạo đơn…');
    goi('dat', { sdt: sdt, pin: pin, cc: (document.getElementById('cc')||{}).value || '',
                 menh_gia: D.goi[CHON].menh_gia,
                 so_luong: Math.max(1, Math.min(10, Number(document.getElementById('sl').value)||1)) },
      function(r){
        ban = false; mua.disabled = false;
        if (!r.ok) { e.textContent = r.error || L('Không tạo được đơn.'); return; }
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
        alert(L('Máy không cho tải ảnh. Anh/chị chụp màn hình mã QR này, rồi trong app ngân hàng ')
          + L('chọn "quét từ thư viện ảnh".'));
      }
    };
  }

  var huy = document.getElementById('huy');
  if (huy) huy.onclick = function(){ DON = null; if (hen) { clearTimeout(hen); hen = null; } ve(); };

  var xem = document.getElementById('t-xem');
  if (xem) xem.onclick = function(){
    var e = document.getElementById('e'), kq = document.getElementById('kq');
    kq.innerHTML = ''; e.textContent = L('Đang tra…');
    var sdt_ct = document.getElementById('t-sdt').value;
    var pin_ct = document.getElementById('t-pin').value;
    /* MỘT lượt gọi cho cả ví lẫn mã — xem việc `cua_toi` bên máy chủ. Gọi hai lượt rồi ghép
       lại ở đây thì ăn HAI lần hãm thử, và một lượt hỏng là màn hiện nửa vời. */
    goi('cua_toi', { sdt: sdt_ct, pin: pin_ct }, function(r){
      if (!r.ok) {
        /* Tra không ra thì đưa luôn LỐI RA, đừng để khách đứng đó gõ lại tới lúc bị hãm. */
        e.innerHTML = esc(r.error || L('Không tìm thấy.'))
          + L('<br><span class="mut">Sai PIN hay quên PIN đều ra câu này. ')
          + L('Gọi nhân viên, đọc số điện thoại — nhân viên tra được mã giúp anh/chị.</span>');
        return;
      }
      e.textContent = '';
      nhoSdt(sdt_ct);
      var h = '';
      /* SỐ DƯ TRƯỚC, mã sau: ai đã nạp thì số dư là thứ họ mở trang này để xem. */
      if (r.co_vi) {
        /* ══════════════════════════════════════════════════════════════════════════════════════
         * 🔴 LỖI 23/08/2026 — MỞ VÍ Ở TAB "CỦA TÔI" RỒI SANG TAB GHẾ THÌ BẤM GÌ CŨNG KHÔNG CHẠY.
         *
         * Anh Thắng: *"tại sao bấm không chạy"*, rồi *"khách không biết bấm nhiều lần, dẫn đến
         * khóa 10p"*.
         *
         * Bản trước dựng `VI` ở đây KHÔNG kèm `pin`. Nhưng `VI` là thứ tab "Dùng tại ghế" nhìn
         * vào để quyết định đã đăng nhập hay chưa — nên tab đó hiện số dư, hiện các thẻ gói bấm
         * được, mọi thứ trông như đã đăng nhập xong. Bấm một gói thì lượt `tieu` gửi lên
         * `pin: undefined`, máy chủ trả "Số điện thoại hoặc PIN chưa đúng", VÀ ĐẾM MỘT LƯỢT HỎNG.
         *
         * Khách không hiểu vì sao (họ vừa nhập PIN đúng ở tab bên cạnh) nên bấm lại. Năm lần là
         * hãm 10 phút — hãm chống máy dò PIN đem đi khoá đúng người đã nhập đúng PIN.
         *
         * ⚠️ Cùng một `VI` thì phải cùng một hình dạng, dù dựng ở đâu. Hai nơi dựng ra hai kiểu
         *    đối tượng cho cùng một cái tên là kiểu lỗi không có phép thử nào bắt được từ xa —
         *    mỗi nơi đọc nó đều đúng với bản mà nơi đó nghĩ tới.
         * ═════════════════════════════════════════════════════════════════════════════════════ */
        VI = { so_du: r.so_du, so: r.so, sdt: sdt_ct, pin: pin_ct, tich: r.tich };
        h += veSoDu(r);
      }
      if (r.co_vi && !r.chua_dung.length && !r.da_dung.length) {
        h += '<p class="mut" style="margin-top:12px">'
          + L('Số này chưa mua mã lẻ nào — chỉ có ví.') + '</p>';
      } else if (!r.chua_dung.length) {
        h += L('<p class="mut">Không còn mã nào chưa dùng.</p>');
      }
      r.chua_dung.forEach(function(m){
        /* Mã chưa tới hạn hiện MỐC DÙNG ĐƯỢC, không hiện "còn dùng được" — khách cần biết quay
           lại lúc nào, chứ nhìn thấy "còn dùng được" rồi ra ghế quét không ăn là tệ nhất. */
        var chua = (m.con_cho || 0) > 0;
        h += '<div class="ma' + (chua ? ' het' : '') + '"><div><div class="m">' + esc(m.ma) + '</div>'
          + '<div class="g">' + tien(m.menh_gia) + ' · '
          + (chua ? L('⏳ dùng được từ ') + esc(String(m.dung_tu).slice(0,16)) : L('còn dùng được'))
          + '</div></div>'
          + '<button data-chep="' + esc(m.ma) + L('">Chép</button></div>');
      });
      r.da_dung.forEach(function(m){
        h += '<div class="ma het"><div><div class="m">' + esc(m.ma) + '</div>'
          + L('<div class="g">đã dùng ') + esc(m.dung_luc)
          + (m.dung_may ? L(' · ghế ') + esc(m.dung_may) : '') + '</div></div></div>';
      });
      kq.innerHTML = h;
      noi();
    });
  };

  var qok = document.getElementById('q-ok');
  if (qok) qok.onclick = function(){
    var e = document.getElementById('q-e');
    if (ban) return;
    ban = true; qok.disabled = true; e.textContent = L('Đang kiểm…');
    goi('lay_lai_pin', { sdt: document.getElementById('q-sdt').value,
                         cc:  document.getElementById('q-cc').value,
                         pin: document.getElementById('q-pin').value }, function(r){
      ban = false; qok.disabled = false;
      if (!r.ok) { e.textContent = r.error || L('Không đặt lại được.'); return; }
      e.innerHTML = '<span style="color:#8ff0b0">' + esc(r.thong_bao) + '</span>';
    });
  };

  var dok = document.getElementById('d-ok');
  if (dok) dok.onclick = function(){
    var e = document.getElementById('e'), kq = document.getElementById('kq');
    /* Ghế CHỈ đến từ cái tem. Không có thì đã không có nút này để bấm (xem veDung), nhưng vẫn
       chặn lần nữa ở đây — nút biến mất là chuyện của giao diện, còn đây là chuyện của tiền. */
    var g = GHE;
    if (!g) { e.textContent = L('Chưa biết ghế nào — quét mã QR trên ghế đang ngồi giúp em.'); return; }
    if (ban) return;
    ban = true; dok.disabled = true; e.textContent = L('Đang kiểm mã…');
    goi('dung', { ma: document.getElementById('d-ma').value, ma_may: g }, function(r){
      ban = false; dok.disabled = false;
      if (!r.ok) { e.textContent = r.error || L('Mã không dùng được.'); return; }
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
  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * ĐƠN TRẢ LƯỢT GHẾ: thứ khách chờ là CÁI GHẾ, không phải mã và cũng không phải số dư.
   *
   * 🔴 KHÔNG ĐẾM NGƯỢC. Lệnh đã xuống ghế từ lúc webhook chạm máy chủ — tức là TRƯỚC cả lúc
   *    trang này biết tin, vì trang chỉ hỏi lại mỗi 3 giây. Vẽ một đồng hồ 5 giây ở đây là bịa
   *    ra một quãng chờ không có thật, sau khi khách đã chờ thật vài giây ở ngân hàng.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  if (r && r.loai === 'ghe') {
    var hg = '<div class="wrap">' + dau()
      + L('<div class="card"><h2>Đã nhận tiền — ghế đang chạy</h2>')
      + '<div class="ok">' + Lf('Ghế <b>{0}</b> chạy ngay bây giờ. Mời anh/chị ngồi thoải mái.', r.ma_may || GHE)
      + '</div>'
      + veTich(r.tich)
      + ((!r.tich && D && D.tich_bat)
          ? L('<p class="mut" style="margin:12px 0 0">Lần sau mở ví trước khi trả thì lượt này ')
            + L('được tính vào chương trình tích lượt.</p>')
          : '')
      + L('<button id="ve-dau" style="width:100%;margin-top:14px">Xong</button></div></div>');
    app.innerHTML = hg;
    DON = null;
    document.getElementById('ve-dau').onclick = function(){ TAB = 'dung'; ve(); };
    return;
  }
  /* Đơn NẠP: thứ khách chờ là SỐ DƯ, không phải bộ mã. Hiện nhầm một danh sách mã rỗng ở đây
     là khách tưởng nạp hỏng. */
  if (r && r.loai === 'nap') {
    var hn = '<div class="wrap">' + dau()
      + L('<div class="card"><h2>Đã nhận tiền — ví đã được cộng</h2>')
      + L('<div class="ok">Ví của anh/chị nay có <b style="color:#f0b429">')
      + tien((r.so_du || 0) + (r.so_du_cho || 0)) + '</b>.'
      + (r.so_du_cho > 0
          ? '<br><b>⏳ ' + tien(r.so_du_cho) + L(' đang trong hạn chờ</b>')
            + (r.con_cho > 0 ? L(' — dùng được sau ') + docCho(r.con_cho) : '') + '.'
          : '')
      + L('<br>Muốn tiêu: <b>quét mã QR dán trên ghế</b>, nhập số điện thoại và PIN.</div>')
      + L('<button id="ve-dau" style="width:100%;margin-top:14px">Xong</button></div></div>');
    app.innerHTML = hn;
    DON = null; NAP = null;
    document.getElementById('ve-dau').onclick = function(){ TAB = 'cua-toi'; ve(); };
    return;
  }
  var h = '<div class="wrap">' + dau()
    + L('<div class="card"><h2>Đã nhận tiền — mã của anh/chị đây</h2>')
    + L('<div class="ok">Mã <b>không hết hạn</b>, dùng được ở <b>bất kỳ ghế nào</b>. ')
    + L('Quên mã thì vào mục <b>Mã của tôi</b>, nhập số điện thoại và PIN vừa đặt.')
    + ((D && D.cho_ngay > 0)
        ? L('<br><b>⏳ Dùng được sau ') + D.cho_ngay + L(' ngày kể từ bây giờ.</b>') : '')
    + '</div>';
  (ds || []).forEach(function(m){
    h += '<div class="ma"><div><div class="m">' + esc(m) + '</div>'
      + L('<div class="g">chụp lại màn hình này giúp em</div></div>')
      + '<button data-chep="' + esc(m) + L('">Chép</button></div>');
  });
  h += L('<button id="ve-dau" style="width:100%;margin-top:14px">Mua thêm</button></div></div>');
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
			. '<style>' . self::css() . VHG_Chan::css() . '</style></head><body' . $lop . $bien . '>'
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
			. VHG_Chan::html()
			. '</body></html>';
	}
}
