<?php
/**
 * KÉO CHI PHÍ TỪ WEB VENDING HCMC (plugin vending-hcmc, kho `managerExpenses`) VỀ THÀNH ĐƠN THẬT.
 *
 * Anh Thắng 25/09/2026 (gửi VENDING_HCMC_PHP_WordPress_1.80.0.zip): *"nối chi phí từ web khác qua chi
 * phí của web anh"*, rồi chốt ba điều:
 *   1. Nhập thành ĐƠN CHI PHÍ THẬT — có TK Nợ, vào sổ, ra tệp MISA.
 *   2. Khối mới "Vending"; mỗi Bộ phận bên Vending (POSH · JP · Pinball · Vận hành chung · Thị trường)
 *      là một CƠ SỞ, mảng "Chi Phí Vending" — xuất MISA thành bảng riêng (cùng ý "mỗi chi phí 1 bảng").
 *   3. (đổi cùng ngày) *"Đẩy là đẩy từ vending về web chi phí của anh để quyết toán"* · *"web đó nằm bên
 *      server khác"* → CHIỀU ĐI LÀ ĐẨY: web Vending (máy chủ khác) POST sang đây ngay khi lưu kho, khoản
 *      "Đã duyệt / Đã thanh toán / Đã quyết toán" bên Vending thành đơn "CHỜ QUYẾT TOÁN" — quyết toán
 *      làm Ở ĐÂY, kế toán bên này chốt rồi thanh toán, xuất MISA. "Chờ duyệt" / "Từ chối" không sang.
 *
 * Đơn đi luồng TRỰC TIẾP ('tt'): tiền đã chi bên Vending, không có gì để tạm ứng — đúng nghĩa "gửi đơn
 * đầy đủ cho kế toán quyết toán". 10 loại chi phí bên Vending được gieo vào danh mục Loại chi phí với
 * TK Nợ TRỐNG — kế toán gán ở Cấu hình; chưa gán thì tệp MISA cảnh báo thiếu TK Nợ như mọi loại khác.
 *
 * 🔴 KHÔNG NHẬP HAI LẦN. Bản đồ id bên Vending → mã đơn giữ ở `VHCP_Meta` (`vending_map`). Đẩy lại
 *    thì khoản đã có được CẬP NHẬT (số tiền, nội dung, hoá đơn) khi đơn còn "Chờ quyết toán"; đơn kế
 *    toán ĐÃ CHỐT (Đã quyết toán trở đi) không đụng — đếm vào `daChot` để hai bên biết lệch.
 * ⚠️ Đường KÉO (`dong_bo`) giữ làm dự phòng: khi web Vending không đẩy được (mạng, cài trễ), kế toán
 *    bấm kéo tháng — cùng một hàm nhận `nhan_khoan()`, cùng luật.
 * 🔴 KHOÁ LÀ BÍ MẬT — cùng luật `VHCP_DoanhThu`: không trả xuống màn, ô trống = giữ, đi trong header.
 * ⚠️ Đơn vị 'VENDING' — kế toán bị bó đơn vị khác không thấy các đơn này; Admin / nhà mẹ thấy đủ.
 */
class VHCP_Vending {

	const O_URL   = 'vhcp_vd_url';
	const DUONG_NHAN = '/vhcp/v1/vending-nhan';
	const O_KHOA  = 'vhcp_vd_khoa';
	const DUONG   = '/wp-json/vending-hcmc/v1/chi-phi';
	const DON_VI  = 'VENDING';
	const KHOI    = 'vending';
	const PLL     = 'Chi Phí Vending';
	const META_MAP = 'vending_map';
	const META_LAN = 'vending_lan_cuoi';
	const BO_PHAN = array( 'POSH', 'JP', 'Pinball', 'Vận hành chung', 'Thị trường' );
	const LOAI    = array( 'Nhân sự', 'Xăng xe', 'Taxi / di chuyển', 'Vật tư', 'Bảo trì', 'Hàng hóa', 'Tiếp khách', 'Tạm ứng', 'Hoàn ứng', 'Khác' );
	/** Trạng thái bên Vending được nhận (đã qua khâu duyệt bên đó). Đơn bên này LUÔN về "Chờ quyết toán". */
	const TT_NHAN = array( 'Đã duyệt', 'Đã thanh toán', 'Đã quyết toán' );
	const TT_DON  = 'Chờ quyết toán';

	public static function url() { return rtrim( trim( (string) get_option( self::O_URL, '' ) ), '/' ); }
	private static function khoa() { return trim( (string) get_option( self::O_KHOA, '' ) ); }

	public static function cau_hinh() {
		$url = self::url(); $co = '' !== self::khoa();
		$admin = class_exists( 'VHCP_Auth' ) && 'Admin' === VHCP_Auth::vai_tro();
		$map = VHCP_Meta::get_json( self::META_MAP, array() );
		/* `san` = có khoá là nhận được đẩy; địa chỉ chỉ cần cho đường kéo dự phòng. */
		return array( 'san' => $co, 'url' => $admin ? $url : '', 'khoaCo' => $co,
			'diaChiNhan' => function_exists( 'rest_url' ) ? rest_url( ltrim( self::DUONG_NHAN, '/' ) ) : self::DUONG_NHAN,
			'soDaNhap' => count( (array) $map ), 'lanCuoi' => (string) VHCP_Meta::get( self::META_LAN, '' ),
			'boPhan' => self::BO_PHAN, 'khoi' => self::KHOI, 'donVi' => self::DON_VI );
	}

	/** Lưu từ save_config({ vending:{url, khoa} }) — gọi SAU khi đã gác Admin. Khoá rỗng = giữ. */
	public static function luu( $d ) {
		$d = (array) $d;
		$url = isset( $d['url'] ) ? trim( (string) $d['url'] ) : null;
		if ( null !== $url ) {
			if ( '' !== $url && ! preg_match( '#^https?://[^\s/]+#i', $url ) ) { return VHCP_Util::err( 'Địa chỉ web Vending phải bắt đầu bằng http:// hoặc https://' ); }
			update_option( self::O_URL, self::goc_dia_chi( $url ), false );
		}
		$khoa = isset( $d['khoa'] ) ? trim( (string) $d['khoa'] ) : '';
		if ( '' !== $khoa ) { update_option( self::O_KHOA, $khoa, false ); }
		VHCP_Log::log_action( array( 'actor' => VHCP_Auth::nguoi(), 'role' => VHCP_Auth::vai_tro(), 'action' => 'Sửa kết nối web Vending',
			'target' => (string) $url, 'detail' => '' !== $khoa ? 'đổi khoá chia sẻ' : 'giữ khoá cũ' ) );
		return VHCP_Util::ok( self::cau_hinh() );
	}

	/**
	 * Địa chỉ đã RỬA: bỏ ?query và #fragment, bỏ / cuối. Anh Thắng 25/09/2026 dán địa chỉ mở app
	 * `https://…/?vending_hcmc=1` (đúng như hướng dẫn của plugin Vending) → nối `/wp-json/…` vào sau
	 * là WordPress trả trang app (HTTP 200, HTML) — màn báo "trả lời không hiểu được: HTTP 200".
	 */
	public static function goc_dia_chi( $url ) {
		$u = trim( (string) $url );
		if ( '' === $u ) { return ''; }
		$p = wp_parse_url( $u );
		if ( empty( $p['host'] ) ) { return rtrim( $u, '/' ); }
		$path = isset( $p['path'] ) ? rtrim( (string) $p['path'], '/' ) : '';
		/* Dán cả đường /wp-admin… hay /wp-json… thì cũng chỉ lấy phần trước đó. */
		$path = preg_replace( '#/(wp-admin|wp-json|wp-login\.php|index\.php)(/.*)?$#i', '', $path );
		return ( isset( $p['scheme'] ) ? $p['scheme'] : 'https' ) . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ) . $path;
	}

	public static function goc_web( $url ) {
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) ) { return ''; }
		return ( isset( $p['scheme'] ) ? $p['scheme'] : 'https' ) . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
	}

	/** GET sang web Vending. Trả { ok, chiPhi:[...], web } hoặc { ok:false, error }. 404 ở đường trang → thử gốc web. */
	public static function goi( $tu, $den ) {
		$url = self::url(); $khoa = self::khoa();
		if ( '' === $url || '' === $khoa ) { return array( 'ok' => false, 'error' => 'Chưa khai địa chỉ web Vending hoặc khoá chia sẻ (Cấu hình ▸ Kết nối web Vending).' ); }
		$q  = array( 'tu' => $tu, 'den' => $den );
		$kq = self::goi_mot( $url, $q, $khoa );
		/* 404, hoặc 200 mà trả HTML (địa chỉ là trang con / trang app) → thử lại ở GỐC web một lần. */
		if ( ! $kq['ok'] && ( 404 === $kq['ma'] || ! empty( $kq['khongJson'] ) ) ) {
			$goc = self::goc_web( $url );
			if ( '' !== $goc && $goc !== $url ) { $kq2 = self::goi_mot( $goc, $q, $khoa ); if ( $kq2['ok'] || 404 !== $kq2['ma'] ) { $kq = $kq2; } }
		}
		return $kq;
	}

	private static function goi_mot( $goc, $q, $khoa ) {
		$dia = rtrim( $goc, '/' ) . self::DUONG . '?' . http_build_query( $q );
		$r   = wp_remote_get( $dia, array( 'timeout' => 20, 'headers' => array( 'X-KHH-Khoa' => $khoa, 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $r ) ) { return array( 'ok' => false, 'ma' => 0, 'error' => 'Không gọi được web Vending: ' . $r->get_error_message() ); }
		$ma = (int) wp_remote_retrieve_response_code( $r );
		$j  = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( 401 === $ma || 403 === $ma ) { return array( 'ok' => false, 'ma' => $ma, 'error' => 'Web Vending chối khoá chia sẻ (HTTP ' . $ma . '). Kiểm lại khoá ở cả hai web.' ); }
		if ( 404 === $ma ) { return array( 'ok' => false, 'ma' => 404, 'error' => 'Web Vending chưa có điểm chia sẻ (HTTP 404) — cần cài bản plugin Vending 1.81.0 có "Chia sẻ cho Chi phí", hoặc địa chỉ sai.' ); }
		if ( ! is_array( $j ) ) {
			$body = (string) wp_remote_retrieve_body( $r );
			$la_html = ( false !== stripos( $body, '<html' ) || false !== stripos( $body, '<!doctype' ) );
			return array( 'ok' => false, 'ma' => $ma, 'khongJson' => true,
				'error' => 'Web Vending trả về ' . ( $la_html ? 'một TRANG HTML' : 'thứ không phải JSON' ) . ' (HTTP ' . $ma . ') thay vì số liệu — '
					. 'địa chỉ có thể là trang app (?vending_hcmc=1) hay trang con, hoặc /wp-json/ đang bị plugin cache/bảo mật chặn. Điền đúng gốc web (VD https://ten-mien) rồi thử lại.' );
		}
		if ( empty( $j['ok'] ) ) {
			return array( 'ok' => false, 'ma' => $ma, 'error' => 'Web Vending trả lời không hiểu được: ' . ( isset( $j['message'] ) ? (string) $j['message'] : ( isset( $j['error'] ) ? (string) $j['error'] : 'HTTP ' . $ma ) ) );
		}
		$ds = array();
		foreach ( (array) ( isset( $j['chiPhi'] ) ? $j['chiPhi'] : array() ) as $x ) {
			$x = (array) $x;
			$g = function ( $k ) use ( $x ) { return isset( $x[ $k ] ) ? trim( (string) $x[ $k ] ) : ''; };
			if ( '' === $g( 'id' ) ) { continue; }
			$ds[] = array( 'id' => $g( 'id' ), 'code' => $g( 'code' ), 'date' => $g( 'date' ), 'department' => $g( 'department' ), 'type' => $g( 'type' ),
				'content' => $g( 'content' ), 'amount' => (float) ( isset( $x['amount'] ) ? $x['amount'] : 0 ), 'requester' => $g( 'requester' ),
				'approver' => $g( 'approver' ), 'status' => $g( 'status' ), 'receiptCode' => $g( 'receiptCode' ), 'receiptLink' => $g( 'receiptLink' ), 'note' => $g( 'note' ) );
		}
		return array( 'ok' => true, 'ma' => $ma, 'chiPhi' => $ds, 'web' => isset( $j['web'] ) ? (string) $j['web'] : '' );
	}

	/** Nút "Kiểm tra kết nối": 30 ngày gần nhất, đếm theo trạng thái. */
	public static function kiem_tra() {
		$nay = VHCP_Util::now();
		$den = $nay->format( 'Y-m-d' ); $tu = ( clone $nay )->modify( '-30 days' )->format( 'Y-m-d' );
		$kq  = self::goi( $tu, $den );
		if ( ! $kq['ok'] ) { return VHCP_Util::err( $kq['error'] ); }
		$tt = array();
		foreach ( $kq['chiPhi'] as $x ) { $k = '' !== $x['status'] ? $x['status'] : '(trống)'; $tt[ $k ] = ( isset( $tt[ $k ] ) ? $tt[ $k ] : 0 ) + 1; }
		return VHCP_Util::ok( array( 'soKhoan' => count( $kq['chiPhi'] ), 'tu' => $tu, 'den' => $den, 'web' => $kq['web'], 'theoTrangThai' => $tt ) );
	}

	// ------------------------------------------------------------------ gieo danh mục

	/** Gieo 5 cơ sở (= Bộ phận) đơn vị VENDING, mảng "Chi Phí Vending", và 10 loại chi phí (TK Nợ trống). Chỉ thêm cái CHƯA có. */
	public static function gieo_danh_muc() {
		$them_cs = 0; $them_loai = 0;
		$rows = VHCP_Cfg::read( VHCP_Cfg::COSO ); $co = array();
		foreach ( $rows as $r ) { $co[ mb_strtolower( trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) ) ] = 1; }
		foreach ( self::BO_PHAN as $bp ) {
			if ( isset( $co[ mb_strtolower( $bp ) ] ) ) { continue; }
			/* Cột: Cơ sở · Mã đơn vị · Phân loại lớn · Tên MISA · Đóng cửa · Đơn vị · Tỉnh · Bộ phận · Tên bên Doanh thu */
			$rows[] = array( $bp, '', self::PLL, $bp, '', self::DON_VI, '', '', '' ); $them_cs++;
		}
		if ( $them_cs ) { VHCP_Cfg::write( VHCP_Cfg::COSO, $rows, false ); }
		$rows = VHCP_Cfg::read( VHCP_Cfg::LOAI ); $co = array();
		foreach ( $rows as $r ) { $co[ mb_strtolower( trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) ) . '|' . mb_strtolower( trim( (string) ( isset( $r[9] ) ? $r[9] : '' ) ) ) ] = 1; }
		foreach ( self::LOAI as $l ) {
			if ( isset( $co[ mb_strtolower( $l ) . '|' . self::KHOI ] ) ) { continue; }
			/* Cột: Loại chi phí · TK Nợ · TK Có · Mã đối tượng · Bộ phận · Ghi chú · Tên MISA · Loại · Đơn vị · Khối · Vai trò · Đầu mục · Cha */
			$rows[] = array( $l, '', '', '', '', 'Nhập từ Vending HCMC — kế toán gán TK Nợ', '', '', self::DON_VI, self::KHOI, '', self::PLL, '' ); $them_loai++;
		}
		if ( $them_loai ) { VHCP_Cfg::write( VHCP_Cfg::LOAI, $rows, false ); }
		if ( $them_cs || $them_loai ) { VHCP_Cfg::clear_cache(); }
		return array( 'coso' => $them_cs, 'loai' => $them_loai );
	}

	// ------------------------------------------------------------------ đồng bộ

	// ------------------------------------------------------------------ NHẬN đẩy từ web Vending (REST)

	public static function routes() {
		register_rest_route( 'vhcp/v1', '/vending-nhan', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_nhan' ),
			'permission_callback' => array( __CLASS__, 'duoc_nhan' ),
		) );
	}

	/** Cửa duy nhất của điểm nhận: header X-KHH-Khoa khớp khoá đang lưu (hash_equals). Chưa đặt khoá = đóng. */
	public static function duoc_nhan( $req ) {
		$luu = self::khoa();
		$gui = trim( (string) $req->get_header( 'X-KHH-Khoa' ) );
		if ( '' === $gui ) { $gui = trim( (string) $req->get_header( 'x_khh_khoa' ) ); }
		if ( '' === $luu || '' === $gui || ! hash_equals( $luu, $gui ) ) {
			return new WP_Error( 'vhcp_vd_khoa', 'Chưa có khoá chia sẻ hoặc khoá không khớp.', array( 'status' => 401 ) );
		}
		return true;
	}

	/** POST { web, khoan:[…] } → lập/cập nhật đơn. Chạy với vai hệ thống (khoá đã gác cửa), không cần phiên PIN. */
	public static function rest_nhan( $req ) {
		$body = method_exists( $req, 'get_json_params' ) ? $req->get_json_params() : null;
		if ( ! is_array( $body ) ) { $body = json_decode( (string) $req->get_body(), true ); }
		$ds = ( is_array( $body ) && isset( $body['khoan'] ) ) ? (array) $body['khoan'] : array();
		if ( ! $ds ) { return new WP_Error( 'vhcp_vd_rong', 'Không có khoản nào trong gói gửi.', array( 'status' => 400 ) ); }
		$web = ( is_array( $body ) && isset( $body['web'] ) ) ? (string) $body['web'] : 'Vending HCMC';
		VHCP_Auth::dat_vai_tro( 'Admin', 'Vending HCMC (đẩy tự động)' );
		$chuan = array();
		foreach ( $ds as $x ) {
			$x = (array) $x; $g = function ( $k ) use ( $x ) { return isset( $x[ $k ] ) ? trim( (string) $x[ $k ] ) : ''; };
			if ( '' === $g( 'id' ) ) { continue; }
			$chuan[] = array( 'id' => $g( 'id' ), 'code' => $g( 'code' ), 'date' => $g( 'date' ), 'department' => $g( 'department' ), 'type' => $g( 'type' ),
				'content' => $g( 'content' ), 'amount' => (float) ( isset( $x['amount'] ) ? $x['amount'] : 0 ), 'requester' => $g( 'requester' ), 'approver' => $g( 'approver' ),
				'status' => $g( 'status' ), 'receiptCode' => $g( 'receiptCode' ), 'receiptLink' => $g( 'receiptLink' ), 'note' => $g( 'note' ) );
		}
		return self::nhan_khoan( $chuan, 'đẩy từ ' . $web );
	}

	/**
	 * Kéo chi phí một tháng về (ĐƯỜNG DỰ PHÒNG — chiều chính là web Vending đẩy sang). @param array $a { thang:'YYYY-MM' }
	 */
	public static function dong_bo( $a = array() ) {
		$a = (array) $a;
		list( $thang, $tu, $den ) = VHCP_DoanhThu::khoang_thang( isset( $a['thang'] ) ? $a['thang'] : '' );
		$kq = self::goi( $tu, $den );
		if ( ! $kq['ok'] ) { return VHCP_Util::err( $kq['error'] ); }
		$r = self::nhan_khoan( $kq['chiPhi'], 'kéo tháng ' . $thang );
		if ( empty( $r['success'] ) ) { return $r; }
		$r['thang'] = $thang; $r['tu'] = $tu; $r['den'] = $den; $r['web'] = $kq['web'];
		return $r;
	}

	/**
	 * LÕI NHẬN — dùng chung cho REST (đẩy) và kéo tay. Mỗi khoản "Đã duyệt / Đã thanh toán / Đã quyết toán"
	 * → một đơn "Chờ quyết toán" luồng tt; khoản đã có → cập nhật nếu đơn chưa chốt.
	 * Trả { tong, moi, capNhat, boQua (chưa duyệt / từ chối), boQuaBoPhan, daChot, loi[], gieo }.
	 */
	public static function nhan_khoan( $ds, $nguon = '' ) {
		$gieo = self::gieo_danh_muc();
		$map  = (array) VHCP_Meta::get_json( self::META_MAP, array() );
		global $wpdb;
		$t_don = VHCP_DB::t( 'don' ); $t_cp = VHCP_DB::t( 'chiphi' );
		$moi = 0; $cap = 0; $bo_qua = 0; $bo_bp = 0; $da_chot = 0; $loi = array();
		foreach ( (array) $ds as $x ) {
			if ( ! in_array( $x['status'], self::TT_NHAN, true ) ) { $bo_qua++; continue; }
			if ( ! in_array( $x['department'], self::BO_PHAN, true ) ) { $bo_bp++; $loi[] = ( $x['code'] ?: $x['id'] ) . ': bộ phận "' . $x['department'] . '" không có trong 5 bộ phận Vending'; continue; }
			$tt_moi = self::TT_DON;
			$ngay   = VHCP_Util::parse_date( $x['date'] );
			if ( ! $ngay ) { $loi[] = ( $x['code'] ?: $x['id'] ) . ': ngày "' . $x['date'] . '" không đọc được'; continue; }
			$ghi = '[Vending ' . ( $x['code'] ?: $x['id'] ) . ']' . ( '' !== $x['receiptCode'] ? ' HĐ ' . $x['receiptCode'] : '' ) . ( '' !== $x['note'] ? ' · ' . $x['note'] : '' );
			$nd  = '' !== $x['content'] ? $x['content'] : ( $x['type'] ?: 'Chi phí Vending' );

			if ( isset( $map[ $x['id'] ] ) ) {
				$m = (string) $map[ $x['id'] ];
				$d = VHCP_Don::don_row( $m );
				if ( ! $d ) { unset( $map[ $x['id'] ] ); }   // đơn đã bị xoá bên này → lập lại như mới
				else {
					/* 🔴 KẾ TOÁN ĐÃ CHỐT (Đã quyết toán trở đi) → không đụng: số đã vào sổ. Bên Vending sửa sau đó thì hai bên lệch — đếm để biết. */
					if ( VHCP_Don::da_chot( (string) $d['trang_thai'] ) ) { $da_chot++; continue; }
					$wpdb->update( $t_cp, array( 'coso' => $x['department'], 'ngay' => $ngay, 'nhom' => $x['type'], 'noi_dung' => $nd,
						'don_gia' => $x['amount'], 'thanh_tien' => $x['amount'], 'thuc_mua' => $x['amount'], 'anh' => $x['receiptLink'] ), array( 'ma_don' => $m ) );
					$wpdb->update( $t_don, array( 'trang_thai' => $tt_moi, 'so_tien_thuc_mua' => $x['amount'], 'hoa_don_qt' => $x['receiptLink'], 'ghi_chu' => $ghi,
						'nguoi_lap' => ( $x['requester'] ?: 'Vending HCMC' ), 'nguoi_duyet' => $x['approver'] ), array( 'ma_don' => $m ) );
					$cap++; continue;
				}
			}
			$r = VHCP_Don::create_don( VHCP_Don::nhan_ky( $ngay ), ( $x['requester'] ?: 'Vending HCMC' ), 'tt' );
			if ( empty( $r['success'] ) || empty( $r['maDon'] ) ) { $loi[] = ( $x['code'] ?: $x['id'] ) . ': ' . ( isset( $r['error'] ) ? $r['error'] : 'không lập được đơn' ); continue; }
			$m = (string) $r['maDon'];
			/* Đóng dấu đơn vị/khối TRƯỚC khi thêm dòng: `add_line` hỏi cơ sở của đơn theo đơn vị. */
			$wpdb->update( $t_don, array( 'don_vi' => self::DON_VI, 'khoi' => self::KHOI, 'luong' => 'tt' ), array( 'ma_don' => $m ) );
			$l = VHCP_Don::add_line( $m, array( 'coso' => $x['department'], 'ngay' => $ngay, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => $x['type'],
				'noiDung' => $nd, 'soLuong' => 1, 'donGia' => $x['amount'], 'thanhTien' => $x['amount'], 'thucMua' => $x['amount'], 'anh' => $x['receiptLink'], 'ghiChu' => $ghi ) );
			if ( empty( $l['success'] ) ) {
				$loi[] = ( $x['code'] ?: $x['id'] ) . ': ' . ( isset( $l['error'] ) ? $l['error'] : 'không thêm được dòng' );
				$wpdb->delete( $t_don, array( 'ma_don' => $m ) ); continue;
			}
			/* Về "Chờ quyết toán": người duyệt bên Vending ghi vào cột người duyệt; người QUYẾT TOÁN để trống — đó là kế toán bên này, lát nữa. */
			$wpdb->update( $t_don, array( 'trang_thai' => $tt_moi, 'nguoi_duyet' => $x['approver'], 'ngay_duyet' => $ngay, 'ngay_gui_qt' => VHCP_Util::now_sql(),
				'so_tien_thuc_mua' => $x['amount'], 'hinh_thuc_tt' => 'Vending', 'hoa_don_qt' => $x['receiptLink'], 'ghi_chu' => $ghi ), array( 'ma_don' => $m ) );
			$map[ $x['id'] ] = $m; $moi++;
		}
		VHCP_Meta::set_json( self::META_MAP, $map );
		VHCP_Meta::set( self::META_LAN, VHCP_Util::now_sql() . ' · ' . $nguon . ' · mới ' . $moi . ' · cập nhật ' . $cap );
		VHCP_Log::log_action( array( 'actor' => VHCP_Auth::nguoi(), 'role' => VHCP_Auth::vai_tro(), 'action' => 'Nhận chi phí Vending',
			'target' => $nguon, 'detail' => 'mới ' . $moi . ' · cập nhật ' . $cap . ' · chưa duyệt/từ chối bỏ qua ' . $bo_qua . ' · bộ phận lạ ' . $bo_bp . ' · đã chốt giữ ' . $da_chot . ' · lỗi ' . count( $loi )
				. ( $gieo['coso'] || $gieo['loai'] ? ' · gieo ' . $gieo['coso'] . ' cơ sở, ' . $gieo['loai'] . ' loại' : '' ) ) );
		return VHCP_Util::ok( array( 'nguon' => $nguon, 'tong' => count( (array) $ds ),
			'moi' => $moi, 'capNhat' => $cap, 'boQua' => $bo_qua, 'boQuaBoPhan' => $bo_bp, 'daChot' => $da_chot, 'loi' => $loi, 'gieo' => $gieo ) );
	}
}
