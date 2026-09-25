<?php
/**
 * KÉO CHI PHÍ TỪ WEB VENDING HCMC (plugin vending-hcmc, kho `managerExpenses`) VỀ THÀNH ĐƠN THẬT.
 *
 * Anh Thắng 25/09/2026 (gửi VENDING_HCMC_PHP_WordPress_1.80.0.zip): *"nối chi phí từ web khác qua chi
 * phí của web anh"*, rồi chốt ba điều:
 *   1. Nhập thành ĐƠN CHI PHÍ THẬT — có TK Nợ, vào sổ, ra tệp MISA.
 *   2. Khối mới "Vending"; mỗi Bộ phận bên Vending (POSH · JP · Pinball · Vận hành chung · Thị trường)
 *      là một CƠ SỞ, mảng "Chi Phí Vending" — xuất MISA thành bảng riêng (cùng ý "mỗi chi phí 1 bảng").
 *   3. Trạng thái THEO BÊN VENDING: "Đã thanh toán" / "Đã quyết toán" → đơn về đúng bước ấy (tiền đã
 *      chi, tính thực chi ngay); "Chờ duyệt" / "Đã duyệt" / "Từ chối" → KHÔNG kéo về.
 *
 * Đơn kéo về đi luồng TRỰC TIẾP ('tt'): tiền đã chi bên Vending, không có gì để tạm ứng — đúng nghĩa
 * "gửi đơn đầy đủ cho kế toán". 10 loại chi phí bên Vending được gieo vào danh mục Loại chi phí với
 * TK Nợ TRỐNG — kế toán gán ở Cấu hình; chưa gán thì tệp MISA cảnh báo thiếu TK Nợ như mọi loại khác.
 *
 * 🔴 KHÔNG NHẬP HAI LẦN. Bản đồ id bên Vending → mã đơn giữ ở `VHCPVP_Meta` (`vending_map`). Kéo lại
 *    thì khoản đã có được CẬP NHẬT (số tiền, trạng thái, hoá đơn) — trừ đơn đã "Đã xuất MISA": sổ đã
 *    nộp, không đụng (đếm vào `daXuat` để kế toán biết).
 * 🔴 KHOÁ LÀ BÍ MẬT — cùng luật `VHCPVP_DoanhThu`: không trả xuống màn, ô trống = giữ, đi trong header.
 * ⚠️ Đơn vị 'VENDING' — kế toán bị bó đơn vị khác không thấy các đơn này; Admin / nhà mẹ thấy đủ.
 */
class VHCPVP_Vending {

	const O_URL   = 'vhcpvp_vd_url';
	const O_KHOA  = 'vhcpvp_vd_khoa';
	const DUONG   = '/wp-json/vending-hcmc/v1/chi-phi';
	const DON_VI  = 'VENDING';
	const KHOI    = 'vending';
	const PLL     = 'Chi Phí Vending';
	const META_MAP = 'vending_map';
	const META_LAN = 'vending_lan_cuoi';
	const BO_PHAN = array( 'POSH', 'JP', 'Pinball', 'Vận hành chung', 'Thị trường' );
	const LOAI    = array( 'Nhân sự', 'Xăng xe', 'Taxi / di chuyển', 'Vật tư', 'Bảo trì', 'Hàng hóa', 'Tiếp khách', 'Tạm ứng', 'Hoàn ứng', 'Khác' );
	/** Trạng thái bên Vending → trạng thái đơn bên này. Không có trong bảng = không kéo. */
	const TT_LAY  = array( 'Đã thanh toán' => 'Đã thanh toán', 'Đã quyết toán' => 'Đã quyết toán' );

	public static function url() { return rtrim( trim( (string) get_option( self::O_URL, '' ) ), '/' ); }
	private static function khoa() { return trim( (string) get_option( self::O_KHOA, '' ) ); }

	public static function cau_hinh() {
		$url = self::url(); $co = '' !== self::khoa();
		$admin = class_exists( 'VHCPVP_Auth' ) && 'Admin' === VHCPVP_Auth::vai_tro();
		$map = VHCPVP_Meta::get_json( self::META_MAP, array() );
		return array( 'san' => ( '' !== $url && $co ), 'url' => $admin ? $url : '', 'khoaCo' => $co,
			'soDaNhap' => count( (array) $map ), 'lanCuoi' => (string) VHCPVP_Meta::get( self::META_LAN, '' ),
			'boPhan' => self::BO_PHAN, 'khoi' => self::KHOI, 'donVi' => self::DON_VI );
	}

	/** Lưu từ save_config({ vending:{url, khoa} }) — gọi SAU khi đã gác Admin. Khoá rỗng = giữ. */
	public static function luu( $d ) {
		$d = (array) $d;
		$url = isset( $d['url'] ) ? trim( (string) $d['url'] ) : null;
		if ( null !== $url ) {
			if ( '' !== $url && ! preg_match( '#^https?://[^\s/]+#i', $url ) ) { return VHCPVP_Util::err( 'Địa chỉ web Vending phải bắt đầu bằng http:// hoặc https://' ); }
			update_option( self::O_URL, rtrim( $url, '/' ), false );
		}
		$khoa = isset( $d['khoa'] ) ? trim( (string) $d['khoa'] ) : '';
		if ( '' !== $khoa ) { update_option( self::O_KHOA, $khoa, false ); }
		VHCPVP_Log::log_action( array( 'actor' => VHCPVP_Auth::nguoi(), 'role' => VHCPVP_Auth::vai_tro(), 'action' => 'Sửa kết nối web Vending',
			'target' => (string) $url, 'detail' => '' !== $khoa ? 'đổi khoá chia sẻ' : 'giữ khoá cũ' ) );
		return VHCPVP_Util::ok( self::cau_hinh() );
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
		if ( ! $kq['ok'] && 404 === $kq['ma'] ) {
			$goc = self::goc_web( $url );
			if ( '' !== $goc && $goc !== $url ) { $kq = self::goi_mot( $goc, $q, $khoa ); }
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
		if ( ! is_array( $j ) || empty( $j['ok'] ) ) {
			return array( 'ok' => false, 'ma' => $ma, 'error' => 'Web Vending trả lời không hiểu được: ' . ( is_array( $j ) && isset( $j['message'] ) ? (string) $j['message'] : 'HTTP ' . $ma ) );
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
		$nay = VHCPVP_Util::now();
		$den = $nay->format( 'Y-m-d' ); $tu = ( clone $nay )->modify( '-30 days' )->format( 'Y-m-d' );
		$kq  = self::goi( $tu, $den );
		if ( ! $kq['ok'] ) { return VHCPVP_Util::err( $kq['error'] ); }
		$tt = array();
		foreach ( $kq['chiPhi'] as $x ) { $k = '' !== $x['status'] ? $x['status'] : '(trống)'; $tt[ $k ] = ( isset( $tt[ $k ] ) ? $tt[ $k ] : 0 ) + 1; }
		return VHCPVP_Util::ok( array( 'soKhoan' => count( $kq['chiPhi'] ), 'tu' => $tu, 'den' => $den, 'web' => $kq['web'], 'theoTrangThai' => $tt ) );
	}

	// ------------------------------------------------------------------ gieo danh mục

	/** Gieo 5 cơ sở (= Bộ phận) đơn vị VENDING, mảng "Chi Phí Vending", và 10 loại chi phí (TK Nợ trống). Chỉ thêm cái CHƯA có. */
	public static function gieo_danh_muc() {
		$them_cs = 0; $them_loai = 0;
		$rows = VHCPVP_Cfg::read( VHCPVP_Cfg::COSO ); $co = array();
		foreach ( $rows as $r ) { $co[ mb_strtolower( trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) ) ] = 1; }
		foreach ( self::BO_PHAN as $bp ) {
			if ( isset( $co[ mb_strtolower( $bp ) ] ) ) { continue; }
			/* Cột: Cơ sở · Mã đơn vị · Phân loại lớn · Tên MISA · Đóng cửa · Đơn vị · Tỉnh · Bộ phận · Tên bên Doanh thu */
			$rows[] = array( $bp, '', self::PLL, $bp, '', self::DON_VI, '', '', '' ); $them_cs++;
		}
		if ( $them_cs ) { VHCPVP_Cfg::write( VHCPVP_Cfg::COSO, $rows, false ); }
		$rows = VHCPVP_Cfg::read( VHCPVP_Cfg::LOAI ); $co = array();
		foreach ( $rows as $r ) { $co[ mb_strtolower( trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) ) . '|' . mb_strtolower( trim( (string) ( isset( $r[9] ) ? $r[9] : '' ) ) ) ] = 1; }
		foreach ( self::LOAI as $l ) {
			if ( isset( $co[ mb_strtolower( $l ) . '|' . self::KHOI ] ) ) { continue; }
			/* Cột: Loại chi phí · TK Nợ · TK Có · Mã đối tượng · Bộ phận · Ghi chú · Tên MISA · Loại · Đơn vị · Khối · Vai trò · Đầu mục · Cha */
			$rows[] = array( $l, '', '', '', '', 'Nhập từ Vending HCMC — kế toán gán TK Nợ', '', '', self::DON_VI, self::KHOI, '', self::PLL, '' ); $them_loai++;
		}
		if ( $them_loai ) { VHCPVP_Cfg::write( VHCPVP_Cfg::LOAI, $rows, false ); }
		if ( $them_cs || $them_loai ) { VHCPVP_Cfg::clear_cache(); }
		return array( 'coso' => $them_cs, 'loai' => $them_loai );
	}

	// ------------------------------------------------------------------ đồng bộ

	/**
	 * Kéo chi phí một tháng về thành đơn. @param array $a { thang:'YYYY-MM' }
	 * Trả { moi, capNhat, boQua (trạng thái chưa trả tiền), boQuaBoPhan, daXuat, loi[], gieo }.
	 */
	public static function dong_bo( $a = array() ) {
		$a = (array) $a;
		list( $thang, $tu, $den ) = VHCPVP_DoanhThu::khoang_thang( isset( $a['thang'] ) ? $a['thang'] : '' );
		$kq = self::goi( $tu, $den );
		if ( ! $kq['ok'] ) { return VHCPVP_Util::err( $kq['error'] ); }
		$gieo = self::gieo_danh_muc();
		$map  = (array) VHCPVP_Meta::get_json( self::META_MAP, array() );
		global $wpdb;
		$t_don = VHCPVP_DB::t( 'don' ); $t_cp = VHCPVP_DB::t( 'chiphi' );
		$moi = 0; $cap = 0; $bo_qua = 0; $bo_bp = 0; $da_xuat = 0; $loi = array();
		foreach ( $kq['chiPhi'] as $x ) {
			if ( ! isset( self::TT_LAY[ $x['status'] ] ) ) { $bo_qua++; continue; }
			if ( ! in_array( $x['department'], self::BO_PHAN, true ) ) { $bo_bp++; $loi[] = ( $x['code'] ?: $x['id'] ) . ': bộ phận "' . $x['department'] . '" không có trong 5 bộ phận Vending'; continue; }
			$tt_moi = self::TT_LAY[ $x['status'] ];
			$ngay   = VHCPVP_Util::parse_date( $x['date'] );
			if ( ! $ngay ) { $loi[] = ( $x['code'] ?: $x['id'] ) . ': ngày "' . $x['date'] . '" không đọc được'; continue; }
			$ghi = '[Vending ' . ( $x['code'] ?: $x['id'] ) . ']' . ( '' !== $x['receiptCode'] ? ' HĐ ' . $x['receiptCode'] : '' ) . ( '' !== $x['note'] ? ' · ' . $x['note'] : '' );
			$nd  = '' !== $x['content'] ? $x['content'] : ( $x['type'] ?: 'Chi phí Vending' );

			if ( isset( $map[ $x['id'] ] ) ) {
				$m = (string) $map[ $x['id'] ];
				$d = VHCPVP_Don::don_row( $m );
				if ( ! $d ) { unset( $map[ $x['id'] ] ); }   // đơn đã bị xoá bên này → lập lại như mới
				else {
					if ( 'Đã xuất MISA' === (string) $d['trang_thai'] ) { $da_xuat++; continue; }
					$wpdb->update( $t_cp, array( 'coso' => $x['department'], 'ngay' => $ngay, 'nhom' => $x['type'], 'noi_dung' => $nd,
						'don_gia' => $x['amount'], 'thanh_tien' => $x['amount'], 'thuc_mua' => $x['amount'], 'anh' => $x['receiptLink'] ), array( 'ma_don' => $m ) );
					$wpdb->update( $t_don, array( 'trang_thai' => $tt_moi, 'ngay_qt' => $ngay, 'nguoi_qt' => $x['approver'], 'so_tien_thuc_mua' => $x['amount'],
						'hoa_don_qt' => $x['receiptLink'], 'ghi_chu' => $ghi, 'nguoi_lap' => ( $x['requester'] ?: 'Vending HCMC' ) ), array( 'ma_don' => $m ) );
					$cap++; continue;
				}
			}
			$r = VHCPVP_Don::create_don( VHCPVP_Don::nhan_ky( $ngay ), ( $x['requester'] ?: 'Vending HCMC' ), 'tt' );
			if ( empty( $r['success'] ) || empty( $r['maDon'] ) ) { $loi[] = ( $x['code'] ?: $x['id'] ) . ': ' . ( isset( $r['error'] ) ? $r['error'] : 'không lập được đơn' ); continue; }
			$m = (string) $r['maDon'];
			/* Đóng dấu đơn vị/khối TRƯỚC khi thêm dòng: `add_line` hỏi cơ sở của đơn theo đơn vị. */
			$wpdb->update( $t_don, array( 'don_vi' => self::DON_VI, 'khoi' => self::KHOI, 'luong' => 'tt' ), array( 'ma_don' => $m ) );
			$l = VHCPVP_Don::add_line( $m, array( 'coso' => $x['department'], 'ngay' => $ngay, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => $x['type'],
				'noiDung' => $nd, 'soLuong' => 1, 'donGia' => $x['amount'], 'thanhTien' => $x['amount'], 'thucMua' => $x['amount'], 'anh' => $x['receiptLink'], 'ghiChu' => $ghi ) );
			if ( empty( $l['success'] ) ) {
				$loi[] = ( $x['code'] ?: $x['id'] ) . ': ' . ( isset( $l['error'] ) ? $l['error'] : 'không thêm được dòng' );
				$wpdb->delete( $t_don, array( 'ma_don' => $m ) ); continue;
			}
			$wpdb->update( $t_don, array( 'trang_thai' => $tt_moi, 'ngay_qt' => $ngay, 'nguoi_qt' => $x['approver'], 'ngay_gui_qt' => VHCPVP_Util::now_sql(),
				'so_tien_thuc_mua' => $x['amount'], 'hinh_thuc_tt' => 'Vending', 'hoa_don_qt' => $x['receiptLink'], 'ghi_chu' => $ghi ), array( 'ma_don' => $m ) );
			$map[ $x['id'] ] = $m; $moi++;
		}
		VHCPVP_Meta::set_json( self::META_MAP, $map );
		VHCPVP_Meta::set( self::META_LAN, VHCPVP_Util::now_sql() . ' · ' . $thang . ' · mới ' . $moi . ' · cập nhật ' . $cap );
		VHCPVP_Log::log_action( array( 'actor' => VHCPVP_Auth::nguoi(), 'role' => VHCPVP_Auth::vai_tro(), 'action' => 'Kéo chi phí Vending về',
			'target' => $thang, 'detail' => 'mới ' . $moi . ' · cập nhật ' . $cap . ' · chưa trả tiền bỏ qua ' . $bo_qua . ' · bộ phận lạ ' . $bo_bp . ' · đã xuất MISA giữ ' . $da_xuat . ' · lỗi ' . count( $loi )
				. ( $gieo['coso'] || $gieo['loai'] ? ' · gieo ' . $gieo['coso'] . ' cơ sở, ' . $gieo['loai'] . ' loại' : '' ) ) );
		return VHCPVP_Util::ok( array( 'thang' => $thang, 'tu' => $tu, 'den' => $den, 'web' => $kq['web'], 'tong' => count( $kq['chiPhi'] ),
			'moi' => $moi, 'capNhat' => $cap, 'boQua' => $bo_qua, 'boQuaBoPhan' => $bo_bp, 'daXuat' => $da_xuat, 'loi' => $loi, 'gieo' => $gieo ) );
	}
}
