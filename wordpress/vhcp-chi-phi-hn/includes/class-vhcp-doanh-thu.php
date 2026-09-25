<?php
/**
 * DOANH THU THEO CƠ SỞ — KÉO TỪ WEB DOANH THU (plugin khh-doanh-thu) ĐỂ ĐẶT CẠNH CHI PHÍ.
 *
 * Anh Thắng 25/09/2026: *"Em có thể lấy doanh thu cơ sở trên wed doanh-thu-hcm không."* ·
 * *"Để anh đánh giá doanh thu dựa trên chi phí"*.
 *
 * Đường đi: Cấu hình ▸ Kết nối web Doanh thu (địa chỉ + khoá chia sẻ) → `goi()` GET
 * `{url}/wp-json/khh-dt/v1/doanh-thu-co-so?tu&den` với header `X-KHH-Khoa` → `so_sanh()` gộp
 * doanh thu từng CỬA HÀNG (tên bên POS/FABi) với chi phí từng CƠ SỞ (tên ở bảng Cơ sở bên này).
 *
 * 🔴 KHOÁ LÀ BÍ MẬT — cùng luật với khoá GitHub ở `class-vhcp-tu-cap-nhat.php`:
 *    · không bao giờ trả khoá xuống trình duyệt (`cau_hinh()` chỉ nói CÓ/KHÔNG);
 *    · ô nhập để trống = GIỮ NGUYÊN, không phải xoá;
 *    · khoá đi trong HEADER, không nằm trong địa chỉ (địa chỉ lọt vào nhật ký máy chủ, header thì
 *      không — và transient nhớ theo tu/den, không nhớ khoá).
 * 🔴 CHỈ ADMIN đổi địa chỉ / khoá (`save_config` gác). Ai duyệt được đơn thì xem được bảng so sánh.
 * ⚠️ Khớp tên hai bên: bảng Cơ sở có cột "Tên bên Doanh thu" (khai tay là chắc nhất); chưa khai thì
 *    khớp LỎNG bằng tên thường gọi / tên MISA bỏ dấu, bỏ đuôi "( Dịch Vụ … K&H )", "TuTu Train"…
 *    Khớp lỏng CÓ THỂ SAI → bảng ghi rõ "tự khớp" để kế toán khai tay cho chắc. Không khớp được thì
 *    cửa hàng vẫn hiện với chi phí 0 — giấu đi là mất một quán đang có doanh thu.
 * ⚠️ Chi phí = THỰC CHI theo `VHCPHN_Don::thuc_chi()` (chưa cấp tiền → 0), tính theo NGÀY của dòng
 *    hạng mục rơi trong tháng — cùng tháng với doanh thu, không theo kỳ của đơn.
 */
class VHCPHN_DoanhThu {

	const O_URL  = 'vhcphn_dt_url';
	const O_KHOA = 'vhcphn_dt_khoa';
	const DUONG  = '/wp-json/khh-dt/v1/doanh-thu-co-so';
	const NHO    = 600;   // nhớ kết quả 10 phút — doanh thu nạp theo ngày, không cần hỏi lại mỗi cú bấm

	/** Địa chỉ web Doanh thu đã lưu (không có dấu / cuối). */
	public static function url() {
		return rtrim( trim( (string) get_option( self::O_URL, '' ) ), '/' );
	}

	private static function khoa() {
		return trim( (string) get_option( self::O_KHOA, '' ) );
	}

	/**
	 * Gói cho màn. `san` = đã có cả địa chỉ lẫn khoá (màn Tổng quan dựa vào đây để bày thẻ).
	 * Địa chỉ chỉ trả cho Admin (người sửa được nó); khoá KHÔNG BAO GIỜ trả.
	 */
	public static function cau_hinh() {
		$tai_cho = self::tai_cho();
		$url = self::url(); $co = '' !== self::khoa();
		$admin = class_exists( 'VHCPHN_Auth' ) && 'Admin' === VHCPHN_Auth::vai_tro();
		/* Cùng WordPress thì `san` LUÔN true — không cần url/khoá gì cả. */
		return array( 'san' => ( $tai_cho || ( '' !== $url && $co ) ), 'url' => $admin ? $url : '', 'khoaCo' => $co, 'taiCho' => $tai_cho );
	}

	/**
	 * Lưu từ `save_config( { doanhThu: { url, khoa } } )`. Gọi SAU khi `save_config` đã gác Admin.
	 *   url  : luôn ghi (rỗng = xoá kết nối). Phải là http(s).
	 *   khoa : rỗng = giữ nguyên; có chữ = ghi đè.
	 */
	public static function luu( $d ) {
		$d   = (array) $d;
		$url = isset( $d['url'] ) ? trim( (string) $d['url'] ) : null;
		if ( null !== $url ) {
			if ( '' !== $url && ! preg_match( '#^https?://[^\s/]+#i', $url ) ) {
				return VHCPHN_Util::err( 'Địa chỉ web Doanh thu phải bắt đầu bằng http:// hoặc https://' );
			}
			update_option( self::O_URL, rtrim( $url, '/' ), false );
		}
		$khoa = isset( $d['khoa'] ) ? trim( (string) $d['khoa'] ) : '';
		$doi_khoa = false;
		if ( '' !== $khoa ) {
			update_option( self::O_KHOA, $khoa, false );
			$doi_khoa = true;
		}
		self::quen();
		VHCPHN_Log::log_action( array( 'actor' => VHCPHN_Auth::nguoi(), 'role' => VHCPHN_Auth::vai_tro(), 'action' => 'Sửa kết nối web Doanh thu',
			'target' => (string) $url, 'detail' => $doi_khoa ? 'đổi khoá chia sẻ' : 'giữ khoá cũ' ) );
		return VHCPHN_Util::ok( self::cau_hinh() );
	}

	/** Quên mọi kết quả đã nhớ (đổi cấu hình, hoặc người dùng bấm Tải lại). */
	public static function quen() {
		update_option( 'vhcphn_dt_doi', (string) microtime( true ), false );
	}

	private static function khoa_nho( $tu, $den ) {
		return 'vhcphn_dt_' . md5( self::url() . '|' . (string) get_option( 'vhcphn_dt_doi', '' ) . '|' . $tu . '|' . $den );
	}

	/**
	 * ĐANG CHẠY CHUNG MỘT WORDPRESS VỚI PLUGIN DOANH THU?
	 *
	 * Anh Thắng 25/09/2026: *"Em có thể lấy doanh thu cơ sở trên web doanh-thu-hcm không."*, rồi
	 * sau khi 1.326.0 cài xong: *"anh vẫn chưa thấy doanh thu theo cơ sở qua"*. Lý do: đường HTTP
	 * cần BA BƯỚC TAY (cài bản Doanh thu mới có "Chia sẻ cho Chi phí" → Tạo khoá → dán khoá), mà
	 * phần bên Doanh thu CHƯA TỪNG được đóng gói (không có `dist/khh-doanh-thu.zip`) — trên host
	 * cửa `doanh-thu-co-so` không tồn tại, nên có dán khoá đúng cũng chỉ ra 404.
	 *
	 * Trong khi đó Chấm công đã gọi thẳng `khh_dt_day_vao()` trong PHP
	 * (`class-vhcc-day-bao-cao.php:65-67, :182`) — nghĩa là Chấm công, Doanh thu và bộ này CÙNG
	 * MỘT WORDPRESS, CÙNG MỘT CSDL. Cùng CSDL thì đọc thẳng bảng `{prefix}khh_dt_ngay`
	 * (`khh_dt_bang()`, `khh-doanh-thu.php:84-86`) — không cần địa chỉ web, không cần khoá, không
	 * độ trễ mạng, và Admin thấy ngay khi mở Tổng quan.
	 *
	 * Đường HTTP (`goi_mot()` ở trên) vẫn giữ nguyên cho site chạy RIÊNG (một bản vùng dựng bằng `tach-ban-vung.sh`), nơi
	 * Doanh thu nằm ở một WordPress khác thật.
	 *
	 * ⚠️ GÁC ĐÚNG HÀM, NGAY TRONG HÀM GỌI NÓ — luật `kiem-goi-cheo.php`. `tai_cho()` là điểm gác
	 *    DUY NHẤT; `goi_tai_cho()` chỉ được gọi SAU khi hàm này đã trả `true`.
	 */
	public static function tai_cho() {
		return function_exists( 'khh_dt_bang' );
	}

	/**
	 * Đọc thẳng bảng của Doanh thu — CÙNG SQL với `chia-se-chi-phi.php:92-93` bên đó (đường HTTP
	 * 1.326.0), chỉ khác là chạy ngay trong tiến trình này. Trả cùng khuôn với `goi_mot()`.
	 */
	private static function goi_tai_cho( $tu, $den ) {
		if ( ! self::tai_cho() ) { return array( 'ok' => false, 'ma' => 0, 'error' => 'Không đọc thẳng được ở đây.' ); }
		global $wpdb;
		$bang = khh_dt_bang();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT cua_hang, SUM(doanh_thu) dt, SUM(thanh_tien) tt, SUM(so_hd) hd, COUNT(DISTINCT ngay) n
			   FROM $bang WHERE ngay >= %s AND ngay <= %s GROUP BY cua_hang ORDER BY dt DESC",
			$tu, $den ), ARRAY_A );
		$ds = array();
		foreach ( (array) $rows as $r ) {
			$ten = trim( (string) $r['cua_hang'] );
			if ( '' === $ten ) { continue; }
			$ds[] = array( 'ten' => $ten, 'doanhThu' => (float) $r['dt'], 'thanhTien' => (float) $r['tt'],
				'soHd' => (int) $r['hd'], 'soNgay' => (int) $r['n'] );
		}
		/* `khh_dt_slug()` là hàm CÙNG plugin với `khh_dt_bang()` — đã gác `tai_cho()` ở trên rồi. */
		$web = function_exists( 'khh_dt_slug' ) ? home_url( '/' . ltrim( (string) khh_dt_slug(), '/' ) ) : home_url( '/' );
		return array( 'ok' => true, 'ma' => 200, 'cuaHang' => $ds, 'web' => $web, 'goc' => home_url( '/' ), 'nguon' => 'tai_cho' );
	}

	/**
	 * GET sang web Doanh thu. Trả { ok, cuaHang:[{ten,doanhThu,thanhTien,soHd,soNgay}], web } hoặc { ok:false, error }.
	 * Người đưa địa chỉ trang báo cáo (…/doanh-thu-hcm) thay vì gốc web thì lượt đầu 404 → thử lại
	 * ở gốc (scheme://host) một lần.
	 */
	public static function goi( $tu, $den, $tuoi = false ) {
		/* 🔴 CÙNG WORDPRESS THÌ ĐỌC THẲNG, KHÔNG HỎI ĐỊA CHỈ / KHOÁ. Xem `tai_cho()`. */
		if ( self::tai_cho() ) {
			$k = 'vhcphn_dt_tc_' . md5( $tu . '|' . $den );
			if ( ! $tuoi ) {
				$nho = get_transient( $k );
				if ( is_array( $nho ) && ! empty( $nho['ok'] ) ) { $nho['nho'] = true; return $nho; }
			}
			$kq = self::goi_tai_cho( $tu, $den );
			if ( $kq['ok'] ) { set_transient( $k, $kq, self::NHO ); }
			return $kq;
		}
		$url = self::url(); $khoa = self::khoa();
		if ( '' === $url || '' === $khoa ) {
			return array( 'ok' => false, 'error' => 'Chưa khai địa chỉ web Doanh thu hoặc khoá chia sẻ (Cấu hình ▸ Kết nối web Doanh thu).' );
		}
		$k = self::khoa_nho( $tu, $den );
		if ( ! $tuoi ) {
			$nho = get_transient( $k );
			if ( is_array( $nho ) && ! empty( $nho['ok'] ) ) { $nho['nho'] = true; return $nho; }
		}
		$q  = array( 'tu' => $tu, 'den' => $den );
		$kq = self::goi_mot( $url, $q, $khoa );
		if ( ! $kq['ok'] && 404 === $kq['ma'] ) {
			$goc = self::goc_web( $url );
			if ( '' !== $goc && $goc !== $url ) { $kq = self::goi_mot( $goc, $q, $khoa ); }
		}
		if ( $kq['ok'] ) { set_transient( $k, $kq, self::NHO ); }
		return $kq;
	}

	/** scheme://host của một địa chỉ (bỏ đường dẫn). */
	public static function goc_web( $url ) {
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) ) { return ''; }
		return ( isset( $p['scheme'] ) ? $p['scheme'] : 'https' ) . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
	}

	private static function goi_mot( $goc, $q, $khoa ) {
		$dia = rtrim( $goc, '/' ) . self::DUONG . '?' . http_build_query( $q );
		$r   = wp_remote_get( $dia, array(
			'timeout' => 15,
			'headers' => array( 'X-KHH-Khoa' => $khoa, 'Accept' => 'application/json' ),
		) );
		if ( is_wp_error( $r ) ) {
			return array( 'ok' => false, 'ma' => 0, 'error' => 'Không gọi được web Doanh thu: ' . $r->get_error_message() );
		}
		$ma   = (int) wp_remote_retrieve_response_code( $r );
		$body = (string) wp_remote_retrieve_body( $r );
		$j    = json_decode( $body, true );
		if ( 401 === $ma || 403 === $ma ) {
			return array( 'ok' => false, 'ma' => $ma, 'error' => 'Web Doanh thu chối khoá chia sẻ (HTTP ' . $ma . '). Kiểm lại khoá ở cả hai web.' );
		}
		if ( 404 === $ma ) {
			return array( 'ok' => false, 'ma' => 404, 'error' => 'Web Doanh thu chưa có điểm chia sẻ (HTTP 404) — cần cài bản plugin Doanh thu có "Chia sẻ cho Chi phí", hoặc địa chỉ sai.' );
		}
		if ( ! is_array( $j ) || empty( $j['ok'] ) ) {
			$loi = is_array( $j ) && isset( $j['message'] ) ? (string) $j['message'] : ( 'HTTP ' . $ma );
			return array( 'ok' => false, 'ma' => $ma, 'error' => 'Web Doanh thu trả lời không hiểu được: ' . $loi );
		}
		$ds = array();
		foreach ( (array) ( isset( $j['cuaHang'] ) ? $j['cuaHang'] : array() ) as $x ) {
			$x = (array) $x; $ten = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			$ds[] = array( 'ten' => $ten, 'doanhThu' => (float) ( isset( $x['doanhThu'] ) ? $x['doanhThu'] : 0 ),
				'thanhTien' => (float) ( isset( $x['thanhTien'] ) ? $x['thanhTien'] : 0 ),
				'soHd' => (int) ( isset( $x['soHd'] ) ? $x['soHd'] : 0 ), 'soNgay' => (int) ( isset( $x['soNgay'] ) ? $x['soNgay'] : 0 ) );
		}
		return array( 'ok' => true, 'ma' => $ma, 'cuaHang' => $ds, 'web' => isset( $j['web'] ) ? (string) $j['web'] : '', 'goc' => rtrim( $goc, '/' ) );
	}

	/** Nút "Kiểm tra kết nối": gọi thử 7 ngày gần nhất, trả số cửa hàng thấy được. */
	public static function kiem_tra() {
		$nay = VHCPHN_Util::now();
		$den = $nay->format( 'Y-m-d' );
		$tu  = ( clone $nay )->modify( '-6 days' )->format( 'Y-m-d' );
		$kq  = self::goi( $tu, $den, true );
		if ( ! $kq['ok'] ) { return VHCPHN_Util::err( $kq['error'] ); }
		return VHCPHN_Util::ok( array( 'soCuaHang' => count( $kq['cuaHang'] ), 'tu' => $tu, 'den' => $den,
			'web' => $kq['web'], 'cuaHang' => array_map( function ( $x ) { return $x['ten']; }, $kq['cuaHang'] ) ) );
	}

	// ------------------------------------------------------------------ khớp tên

	/**
	 * Từ đồng nghĩa hai bên (bên POS đặt tên thương hiệu, bên chi phí gọi tên gian). Áp SAU bỏ dấu.
	 * Anh Thắng 25/09/2026 (ảnh bảng so sánh thật): "TuTu Train - Aeon Tân Phú" bị gán vào NHÀ MA AEON
	 * TÂN PHÚ, "Tutu Train - Bình Dương" vào SNOW NHÀ TUYẾT BÌNH DƯƠNG — vì bản đầu XOÁ "tutu train" rồi
	 * so "chứa nhau", còn lại mỗi tên chỗ. Nay đổi "tutu train" → "tau" và so theo TỪ.
	 */
	const DONG_NGHIA = array(
		'tutu train'    => 'tau',
		'tu tu train'   => 'tau',
		'nha tuyet'     => 'snow',
		'ghost bride'   => 'nha ma',
		'haunted house' => 'nha ma',
		'ngoi nha ma'   => 'nha ma',
		'adventure'     => 'adv',
		'coffee'        => 'coffe',
	);
	/** Chữ đệm không mang nghĩa nhận diện — bỏ ở CẢ hai bên trước khi so từ. */
	const BO_TU = array( 'dich', 'vu', 'va', 'giai', 'tri', 'k', 'h', 'kh', 'posh', 'cong', 'ty', 'chi', 'nhanh', 'cn', 'the', 'and' );

	/** Tên rút gọn để so: bỏ dấu, bỏ phần trong ngoặc là đuôi công ty, đổi từ đồng nghĩa, chỉ giữ chữ-số. */
	public static function rut_gon( $s ) {
		$s = (string) $s;
		/* Chỉ bỏ ngoặc là ĐUÔI CÔNG TY "( Dịch Vụ và Giải Trí K&H )"; ngoặc mang tên gian
		   "(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ" thì giữ — đó là phần dễ khớp nhất của cái tên. */
		$s = preg_replace( '/\((?=[^)]*(?:d[iị]ch\s*v[uụ]|k\s*&\s*h|k&h|posh))[^)]*\)/iu', ' ', $s );
		$s = VHCPHN_Cfg::bo_dau( $s );
		$s = preg_replace( '/[^a-z0-9]+/u', ' ', $s );
		$s = ' ' . trim( preg_replace( '/\s+/', ' ', $s ) ) . ' ';
		foreach ( self::DONG_NGHIA as $tu => $thanh ) { $s = str_replace( ' ' . $tu . ' ', ' ' . $thanh . ' ', $s ); }
		$s = preg_replace( '/\s+(?:' . implode( '|', self::BO_TU ) . ')(?=\s)/u', ' ', $s );
		return trim( preg_replace( '/\s+/', ' ', $s ) );
	}

	/** Tập từ của một tên rút gọn (khử trùng). */
	private static function tu_cua( $rut_gon ) {
		$ds = array_values( array_unique( array_filter( explode( ' ', (string) $rut_gon ), 'strlen' ) ) );
		return $ds;
	}

	/**
	 * Ánh xạ cửa hàng (doanh thu) → cơ sở (chi phí).
	 * Trả [ ten_cua_hang => [ 'coso' => tên cơ sở, 'khop' => 'khai'|'tu', 'khac' => [tên cơ sở khác cũng khớp] ] ].
	 *
	 * Thứ tự:
	 *   1. Cột "Tên bên Doanh thu" khai tay (so nguyên chữ, không phân biệt hoa/thường) → 'khai'.
	 *   2. Tên rút gọn trùng hẳn (tên thường gọi hoặc tên MISA) → 'tu'.
	 *   3. MỌI TỪ của cơ sở nằm trong tên cửa hàng (cơ sở ⊂ cửa hàng) → 'tu'. Cơ sở phải có ≥ 2 từ,
	 *      hoặc 1 từ dài ≥ 6 ký tự ("estella"). Nhiều cơ sở cùng đạt → lấy cơ sở NHIỀU TỪ NHẤT (khớp
	 *      chặt nhất: "Tutu Train - Estella" → TÀU ESTELLA hơn ESTELLA), các cơ sở còn lại ghi vào `khac`
	 *      để màn bày "hay là …?" cho kế toán quyết.
	 * ⚠️ KHÔNG so chiều ngược (cửa hàng ⊂ cơ sở): "Tutu Train - Bình Dương" mà chui vào mọi gian Bình
	 *    Dương là đúng cái sai đã cắn.
	 */
	public static function anh_xa( $cua_hang_ds, $coso_ds ) {
		$khai = array(); $rut = array(); $tu = array();
		foreach ( (array) $coso_ds as $c ) {
			$ten = trim( (string) ( isset( $c['ten'] ) ? $c['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			$tdt = trim( (string) ( isset( $c['tenDoanhThu'] ) ? $c['tenDoanhThu'] : '' ) );
			if ( '' !== $tdt ) { $khai[ mb_strtolower( preg_replace( '/\s+/u', ' ', $tdt ) ) ] = $ten; }
			foreach ( array( $ten, isset( $c['tenMisa'] ) ? $c['tenMisa'] : '' ) as $t ) {
				$g = self::rut_gon( $t );
				if ( '' === $g ) { continue; }
				if ( ! isset( $rut[ $g ] ) ) { $rut[ $g ] = $ten; }
				$ds = self::tu_cua( $g );
				if ( count( $ds ) >= 2 || ( 1 === count( $ds ) && mb_strlen( $ds[0] ) >= 6 ) ) {
					$tu[] = array( 'ten' => $ten, 'tu' => $ds );
				}
			}
		}
		$ra = array();
		foreach ( (array) $cua_hang_ds as $ch ) {
			$ch = trim( (string) $ch ); if ( '' === $ch ) { continue; }
			$k = mb_strtolower( preg_replace( '/\s+/u', ' ', $ch ) );
			if ( isset( $khai[ $k ] ) ) { $ra[ $ch ] = array( 'coso' => $khai[ $k ], 'khop' => 'khai', 'khac' => array() ); continue; }
			$g = self::rut_gon( $ch );
			if ( '' === $g ) { continue; }
			if ( isset( $rut[ $g ] ) ) { $ra[ $ch ] = array( 'coso' => $rut[ $g ], 'khop' => 'tu', 'khac' => array() ); continue; }
			$co = self::tu_cua( $g );
			$dat = array();
			foreach ( $tu as $x ) {
				if ( ! array_diff( $x['tu'], $co ) ) { $dat[ $x['ten'] ] = max( isset( $dat[ $x['ten'] ] ) ? $dat[ $x['ten'] ] : 0, count( $x['tu'] ) ); }
			}
			if ( ! $dat ) { continue; }
			arsort( $dat );
			$ten_ds = array_keys( $dat );
			$ra[ $ch ] = array( 'coso' => $ten_ds[0], 'khop' => 'tu', 'khac' => array_slice( $ten_ds, 1 ) );
		}
		return $ra;
	}

	// ------------------------------------------------------------------ so sánh

	/** Khoảng ngày của một tháng 'YYYY-MM' (sai dạng → tháng hiện tại). */
	public static function khoang_thang( $thang ) {
		$t = trim( (string) $thang );
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $t, $m ) ) { $t = VHCPHN_Util::now()->format( 'Y-m' ); preg_match( '/^(\d{4})-(\d{2})$/', $t, $m ); }
		$tu  = sprintf( '%04d-%02d-01', $m[1], $m[2] );
		$den = gmdate( 'Y-m-t', gmmktime( 0, 0, 0, (int) $m[2], 1, (int) $m[1] ) );
		return array( $t, $tu, $den );
	}

	/**
	 * Chi phí THỰC CHI theo cơ sở trong [tu, den] (theo ngày của dòng; đơn phải đã cấp tiền).
	 * `$khoi` (mb/mn/…) rỗng = cả hệ; đơn chưa đóng dấu khối vẫn tính (cùng luật `VHCPHN_Report::finance`).
	 */
	public static function chi_phi_coso( $tu, $den, $khoi = '' ) {
		$khoi = mb_strtolower( trim( (string) $khoi ) );
		$tt_don = array();
		foreach ( VHCPHN_Don::don_rows() as $r ) {
			if ( '' !== $khoi ) {
				$kd = mb_strtolower( trim( (string) ( isset( $r['khoi'] ) ? $r['khoi'] : '' ) ) );
				if ( '' !== $kd && $kd !== $khoi ) { continue; }
			}
			$tt_don[ (string) $r['ma_don'] ] = ( '' !== (string) $r['trang_thai'] ) ? (string) $r['trang_thai'] : 'Nháp';
		}
		$ra = array(); $n = array();
		foreach ( VHCPHN_Don::cp_rows() as $r ) {
			$m = (string) $r['ma_don'];
			if ( '' === $m || ! isset( $tt_don[ $m ] ) ) { continue; }
			$ngay = VHCPHN_Util::parse_date( isset( $r['ngay'] ) ? $r['ngay'] : '' );
			if ( null === $ngay || $ngay < $tu || $ngay > $den ) { continue; }
			$eff = VHCPHN_Don::thuc_chi( $r['thanh_tien'], $r['thuc_mua'], $tt_don[ $m ] );
			if ( ! $eff ) { continue; }
			$cs = trim( (string) $r['coso'] );
			if ( '' === $cs ) { $cs = '(không cơ sở)'; }
			if ( ! isset( $ra[ $cs ] ) ) { $ra[ $cs ] = 0; $n[ $cs ] = 0; }
			$ra[ $cs ] += $eff; $n[ $cs ]++;
		}
		return array( 'tien' => $ra, 'dong' => $n );
	}

	/**
	 * Bảng "Doanh thu vs Chi phí theo cơ sở" cho một tháng.
	 * @param array $a { thang:'YYYY-MM', khoi:'', tuoi:bool }
	 */
	public static function so_sanh( $a = array() ) {
		$a = (array) $a;
		list( $thang, $tu, $den ) = self::khoang_thang( isset( $a['thang'] ) ? $a['thang'] : '' );
		$kq = self::goi( $tu, $den, ! empty( $a['tuoi'] ) );
		if ( ! $kq['ok'] ) { return VHCPHN_Util::err( $kq['error'] ); }

		$cfg  = VHCPHN_Cfg::get_config();
		$coso = isset( $cfg['coso'] ) ? VHCPHN_Cfg::coso_theo_don_vi( $cfg['coso'] ) : array();
		$cp   = self::chi_phi_coso( $tu, $den, isset( $a['khoi'] ) ? $a['khoi'] : '' );
		$map  = self::anh_xa( array_map( function ( $x ) { return $x['ten']; }, $kq['cuaHang'] ), $coso );

		$rows = array(); $da_cs = array(); $chua = array();
		$t_dt = 0; $t_cp = 0;
		foreach ( $kq['cuaHang'] as $ch ) {
			$ten = $ch['ten']; $dt = $ch['doanhThu'];
			/* Dòng gộp "Toàn hệ thống" hay cửa hàng không có lấy một hoá đơn trong tháng: không phải một
			   gian để đặt cạnh chi phí — bày nó là thêm một dòng "chưa khớp" vô nghĩa (ảnh 25/09/2026). */
			if ( preg_match( '/^to[aà]n\s+h[eệ]\s+th[oố]ng$/iu', trim( $ten ) ) ) { continue; }
			if ( $dt <= 0 && (int) $ch['soHd'] <= 0 && (int) $ch['soNgay'] <= 0 ) { continue; }
			$cs = isset( $map[ $ten ] ) ? $map[ $ten ]['coso'] : '';
			$kh = isset( $map[ $ten ] ) ? $map[ $ten ]['khop'] : 'chua';
			$cpt = ( '' !== $cs && isset( $cp['tien'][ $cs ] ) ) ? $cp['tien'][ $cs ] : 0;
			if ( '' !== $cs ) { $da_cs[ $cs ] = 1; } else { $chua[] = $ten; }
			$rows[] = array( 'coso' => $cs, 'cuaHang' => $ten, 'doanhThu' => $dt, 'chiPhi' => $cpt,
				'tyLe' => $dt > 0 ? round( $cpt * 100 / $dt, 1 ) : null, 'khop' => $kh,
				'khac' => isset( $map[ $ten ] ) ? $map[ $ten ]['khac'] : array(),
				'soHd' => $ch['soHd'], 'soNgay' => $ch['soNgay'] );
			$t_dt += $dt; $t_cp += $cpt;
		}
		/* Cơ sở có chi phí mà không cửa hàng nào trỏ tới: vẫn bày (doanh thu 0) — một gian đang
		   tiêu tiền mà không có doanh thu là điều anh cần thấy nhất. */
		foreach ( $cp['tien'] as $cs => $tien ) {
			if ( isset( $da_cs[ $cs ] ) ) { continue; }
			$rows[] = array( 'coso' => $cs, 'cuaHang' => '', 'doanhThu' => 0, 'chiPhi' => $tien, 'tyLe' => null, 'khop' => 'chua', 'khac' => array(), 'soHd' => 0, 'soNgay' => 0 );
			$t_cp += $tien;
		}
		usort( $rows, function ( $x, $y ) { return $y['doanhThu'] <=> $x['doanhThu'] ?: $y['chiPhi'] <=> $x['chiPhi']; } );
		return VHCPHN_Util::ok( array(
			'thang' => $thang, 'tu' => $tu, 'den' => $den, 'web' => $kq['web'], 'nho' => ! empty( $kq['nho'] ),
			'rows' => $rows, 'tongDoanhThu' => $t_dt, 'tongChiPhi' => $t_cp,
			'tyLe' => $t_dt > 0 ? round( $t_cp * 100 / $t_dt, 1 ) : null,
			'cuaHangChuaKhop' => $chua,
			'cuaHangDs' => array_map( function ( $x ) { return $x['ten']; }, $kq['cuaHang'] ),
		) );
	}

	// ------------------------------------------------------------------ doanh thu ĐỜI GIAN (Kỹ thuật)

	/**
	 * DOANH THU TỪ NGÀY SETUP ĐẾN NGÀY THÁO DỠ — cho khối "Chi phí Kỹ thuật" ở Tổng quan.
	 *
	 * Anh Thắng 25/09/2026, sau khi xem ảnh khối Kỹ thuật, chọn so *"Từ ngày Setup đến Tháo dỡ
	 * (Khuyến nghị)"*: *"Cả đời gian: cộng doanh thu từ ngày mở tới ngày tháo (chưa tháo thì tới
	 * hôm nay). Khớp đúng với cột Thực tế — cũng là cả đời gian — nên so được thẳng."* Và với câu
	 * hỏi tên hai bên không giống nhau ("TuTu Train – Aeon Tân Phú" ≠ "ADV SỰ KIỆN TÂN PHÚ"), anh
	 * chọn *"Anh/kế toán chọn một lần"* — dùng lại đúng cột **"Tên bên Doanh thu"** ở bảng Cơ sở
	 * (đã có sẵn từ 1.326.0), KHÔNG dựng thêm một ô chọn nào mới.
	 *
	 * Vào: `$items` = mảng của `VHCPHN_DuAn::list_du_an()` (mỗi phần tử có `ten`, `loai`). Ra: CÙNG
	 * mảng ấy; mỗi phần tử `loai` Setup/Tháo dỡ được thêm `doanhThu` · `dtTu` · `dtDen` (Y-m-d) ·
	 * `dtCuaHang` (tên bên POS) · `dtNoi` ('khai'|'chua_noi'|'khong_coso'). `Chi phí cơ sở` đi
	 * qua nguyên vẹn — nó không phải một gian để so doanh thu.
	 *
	 * ⚠️ CHỈ CHẠY KHI `tai_cho()`. Đường HTTP (site chạy riêng, xem `tools/tach-ban-vung.sh`) không lộ
	 *    `MIN(ngay)` — `chia-se-chi-phi.php` chỉ trả tổng trong [tu,den] CHO SẴN — nên không tự
	 *    dò được "ngày đầu có doanh thu" mà không hỏi nhiều lượt cho MỖI cửa hàng MỖI lần mở
	 *    Tổng quan. Bỏ qua đúng trường hợp ấy còn hơn gọi HTTP hàng chục lượt một lần tải trang.
	 * ⚠️ LỖI THÌ TRẢ NGUYÊN `$items`, KHÔNG NÉM RA — hàm này nuôi `list_du_an()`, thứ nhiều màn
	 *    khác đang dùng; một lỗi ở doanh thu không được phép làm sập cả bảng dự án.
	 * ⚠️ GẦN NHƯ MỘT LỆNH SQL CHO MỌI CỬA HÀNG: nhóm theo TÊN CỬA HÀNG (nhiều gian có thể dùng
	 *    chung một cửa hàng), một lệnh gộp lấy `MIN(ngay)` + tổng CẢ ĐỜI cho tất cả; cửa hàng ĐÃ
	 *    ĐÓNG (`dongCua` có ngày) mới hỏi thêm MỘT lệnh riêng để trừ phần doanh thu sau ngày đóng.
	 *    Số lệnh phụ thuộc số CỬA HÀNG ĐÃ ĐÓNG (thường vài cái), không phụ thuộc số DỰ ÁN —
	 *    `bench-queries.php` đo đúng chỗ này khi seed dự án tăng dần.
	 */
	public static function doi_gian( array $items ) {
		try {
			if ( ! self::tai_cho() ) { return $items; }
			$coso_ds = class_exists( 'VHCPHN_Cfg' ) ? (array) VHCPHN_Cfg::cfg_static()['coso'] : array();
			if ( ! $coso_ds ) { return $items; }

			/* gian (dự án) → cơ sở: khớp ĐÚNG tên trước (Tháo dỡ vốn CHỌN tên từ chính danh sách
			   cơ sở, nên khớp chắc), rồi mới rút gọn cho tên Setup gõ tay. Cùng một nguồn vựng
			   nội bộ nên không cần khớp theo TỪ như `anh_xa()` (cái đó dành riêng cho tên bên
			   POS, vốn mang đuôi công ty và tên thương hiệu khác hẳn quy ước đặt tên ở đây). */
			$theo_ten = array(); $theo_rg = array();
			foreach ( $coso_ds as $c ) {
				$c   = (array) $c;
				$ten = trim( (string) ( isset( $c['ten'] ) ? $c['ten'] : '' ) );
				if ( '' === $ten ) { continue; }
				$theo_ten[ mb_strtolower( $ten ) ] = $c;
				$g = self::rut_gon( $ten );
				if ( '' !== $g && ! isset( $theo_rg[ $g ] ) ) { $theo_rg[ $g ] = $c; }
			}

			$can = array(); $hom_nay = VHCPHN_Util::now()->format( 'Y-m-d' );
			foreach ( $items as $i => $x ) {
				if ( ! in_array( isset( $x['loai'] ) ? $x['loai'] : '', array( 'Setup lắp đặt', 'Tháo dỡ' ), true ) ) { continue; }
				$ten_gian = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
				$k = mb_strtolower( $ten_gian );
				$c = isset( $theo_ten[ $k ] ) ? $theo_ten[ $k ] : null;
				if ( null === $c ) {
					$g = self::rut_gon( $ten_gian );
					$c = ( '' !== $g && isset( $theo_rg[ $g ] ) ) ? $theo_rg[ $g ] : null;
				}
				if ( null === $c ) { $items[ $i ]['dtNoi'] = 'khong_coso'; continue; }
				$tdt = trim( (string) ( isset( $c['tenDoanhThu'] ) ? $c['tenDoanhThu'] : '' ) );
				if ( '' === $tdt ) { $items[ $i ]['dtNoi'] = 'chua_noi'; continue; }
				$dong_ngay = VHCPHN_Util::parse_date( isset( $c['dongCua'] ) ? $c['dongCua'] : '' );
				$can[ $i ] = array( 'cuaHang' => $tdt, 'den' => $dong_ngay ? $dong_ngay : $hom_nay, 'moc' => $dong_ngay );
			}
			if ( ! $can ) { return $items; }

			global $wpdb;
			$bang   = khh_dt_bang();
			$ten_ds = array_values( array_unique( array_column( $can, 'cuaHang' ) ) );
			$hold   = implode( ',', array_fill( 0, count( $ten_ds ), '%s' ) );
			$rows   = $wpdb->get_results( $wpdb->prepare(
				"SELECT cua_hang, MIN(ngay) tu, SUM(doanh_thu) dt FROM $bang WHERE cua_hang IN ($hold) GROUP BY cua_hang",
				$ten_ds ), ARRAY_A );
			$so = array();
			foreach ( (array) $rows as $r ) { $so[ (string) $r['cua_hang'] ] = array( 'tu' => (string) $r['tu'], 'dt' => (float) $r['dt'] ); }

			/* Cửa hàng ĐÃ ĐÓNG: trừ phần doanh thu phát sinh SAU ngày đóng — một lệnh cho MỖI
			   TÊN CỬA HÀNG đã đóng (không phải mỗi dự án); nhiều gian trùng tên chỉ tốn một lệnh. */
			$moc_cua = array();
			foreach ( $can as $x ) { if ( $x['moc'] ) { $moc_cua[ $x['cuaHang'] ] = isset( $moc_cua[ $x['cuaHang'] ] ) ? min( $moc_cua[ $x['cuaHang'] ], $x['moc'] ) : $x['moc']; } }
			foreach ( $moc_cua as $ten => $moc ) {
				if ( ! isset( $so[ $ten ] ) ) { continue; }
				$sau = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(doanh_thu) FROM $bang WHERE cua_hang = %s AND ngay > %s", $ten, $moc ) );
				$so[ $ten ]['dt'] -= $sau;
			}

			foreach ( $can as $i => $x ) {
				$ten = $x['cuaHang'];
				if ( ! isset( $so[ $ten ] ) ) { $items[ $i ]['dtNoi'] = 'chua_noi'; continue; }
				$items[ $i ]['doanhThu']  = (float) $so[ $ten ]['dt'];
				$items[ $i ]['dtTu']      = $so[ $ten ]['tu'];
				$items[ $i ]['dtDen']     = isset( $moc_cua[ $ten ] ) ? $moc_cua[ $ten ] : $hom_nay;
				$items[ $i ]['dtCuaHang'] = $ten;
				$items[ $i ]['dtNoi']     = 'khai';
			}
			return $items;
		} catch ( \Throwable $e ) {
			return $items;   // doanh thu lỗi KHÔNG được làm đổ list_du_an() — nhiều màn khác dùng nó.
		}
	}

}
