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

	/**
	 * ⚠️ BỎ TỪ 25/08/2026, giữ hằng số để mã cũ không gãy.
	 *
	 * Trước đây trạm phát thẻ mang vai giả này, cố ý không nằm trong VAI_TRO_TAT_CA, để thẻ
	 * trạm và thẻ quản trị không đổi cho nhau. Chốt ấy làm đúng việc của nó, nhưng cái giá là
	 * cùng một người phải gõ PIN hai lần ở hai trang, và hai nửa hệ thống có hai bộ luật quyền
	 * — đúng cái anh Thắng gọi là *"xung đột phân quyền"*. Nay một thẻ, một bộ luật; phép gác
	 * chuyển sang quyền `cham_online` + bắt buộc có Mã NV (xem `nguoi()`).
	 */
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
			$kq = self::dang_nhap( isset( $b['pin'] ) ? $b['pin'] : '' );
			/* Mở luôn phiên của trang quản trị bằng CHÍNH thẻ này. Người đủ bậc bấm sang là vào
			   thẳng; người không đủ bậc thì phiên ấy cũng chỉ mở đúng màn "Công của tôi" — cửa
			   nằm ở từng màn, không nằm ở việc có cookie hay không. */
			if ( ! empty( $kq['ok'] ) && ! headers_sent() ) { VHCC_Web::mo_phien( $kq['token'] ); }
			self::ra( $kq );
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

		if ( 'toi' === $viec ) {
			$tt = VHCC_Online::thong_tin( $u );
			/* Đường sang trang quản trị — CHỈ gửi cho người thật sự mở được nó. Gửi cho ai
			   cũng thì nhân viên bấm vào rồi nhận một trang chối, và họ tưởng mình hỏng máy.
			   Cùng một thẻ dùng được cả hai bên nên bấm sang là vào thẳng, không gõ PIN lại. */
			if ( VHCC_Vai::duoc( $u, 'cong_coso' ) ) {
				$tt['qtUrl'] = VHCC_Web::url();
				$tt['vaiTen'] = VHCC_Vai::ten( $u );
			}
			self::ra( $tt );
		}

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

		/**
		 * Dãy đặc trưng khuôn mặt của tấm ảnh vừa chấm.
		 *
		 * 🔴 GỬI RIÊNG, SAU KHI GIỜ ĐÃ GHI XONG. Cố ý không nhét vào lượt `cham`: tính dãy đặc
		 *    trưng cần tải một model vài megabyte về máy, và ở cơ sở dùng 3G thì việc ấy mất
		 *    hàng chục giây. Nhét chung là mỗi lượt chấm công phải đợi model tải xong mới ghi
		 *    được giờ — đổi một tiện ích lấy chính cái việc mà cả hệ thống sinh ra để làm.
		 *
		 *    Tách ra thì: giờ vào ghi ngay, còn đối chiếu mặt chạy sau ở nền. Mạng chết giữa
		 *    chừng thì chỉ mất phần đối chiếu, lượt chấm vẫn nguyên.
		 */
		if ( 'mat' === $viec ) {
			self::ra( VHCC_Mat::soi( $u,
				isset( $b['vector'] ) ? $b['vector'] : null,
				isset( $b['ngay'] ) ? (string) $b['ngay'] : '',
				isset( $b['coSo'] ) ? (string) $b['coSo'] : '' ) );
		}

		if ( 'thang' === $viec ) {
			$ds = VHCC_Online::ds_coso_cua_nv( $u['ma_nv'], VHCC_NhanSu::chuan_coso( $u['coso'] ) );
			self::ra( VHCC_Online::bang_thang( $u['ma_nv'], $ds,
				isset( $b['thang'] ) ? (string) $b['thang'] : '' ) );
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
	 * KHÔNG đi qua `VHCC_Auth::users()`: nguồn người dùng của hệ quản trị đổi được (riêng /
	 * chung / app / hồ sơ) ngay trong màn Cài đặt, và màn đó không hề nhắc gì tới trạm — lật
	 * một ô chọn ở đấy mà cả công ty không chấm công được thì không ai lần ra vì sao.
	 *
	 * Trạm có luật riêng, cố định, không đổi theo Cài đặt: **ai có MÃ NV thì chấm được**. Xem
	 * `tim_pin()` cho hai cuốn sổ mà luật ấy tra.
	 */
	public static function dang_nhap( $pin ) {
		$pin = VHCC_Auth::pin_sach( $pin );
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số.' );
		}
		$k = self::khoa_key();
		if ( (int) get_transient( $k ) >= self::SAI_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Gõ sai quá nhiều lần — thử lại sau 10 phút.' );
		}

		$r = self::tim_pin( $pin );
		if ( ! $r['thay'] ) {
			if ( '' !== $r['vi_sao'] ) {
				/* PIN CÓ trong sổ nhưng thiếu thứ khác là tình huống khác hẳn PIN sai, và phải
				   nói khác đi. Bảo "PIN không đúng" thì người ta gõ lại mười lần rồi tự khoá
				   mình, trong khi thứ thiếu nằm ở hồ sơ chứ không nằm ở ngón tay họ.
				   Cũng KHÔNG đếm lượt sai cho nhóm này — gõ đúng PIN thì không phải là dò. */
				return array( 'ok' => false, 'error' => $r['vi_sao'] );
			}
			set_transient( $k, (int) get_transient( $k ) + 1, 600 );
			return array( 'ok' => false, 'error' => 'PIN không đúng hoặc chưa được cấp.' );
		}

		delete_transient( $k );
		/* Thẻ mang VAI THẬT của người ta, không phải vai giả. Nhờ vậy cùng một lần đăng nhập
		   dùng được cả ở trang quản trị: cửa hàng trưởng chấm công xong bấm sang xem bảng công
		   cơ sở mình, không phải gõ lại PIN ở một trang khác. */
		$vai = '' !== $r['vai_tro'] ? $r['vai_tro'] : VHCC_Vai::TEN[ VHCC_Vai::NV ];
		return array(
			'ok'    => true,
			'hoTen' => $r['ho_ten'],
			'maNV'  => $r['ma_nv'],
			'coSo'  => $r['coso'],
			'vaiTro' => VHCC_Vai::ten( $vai ),
			'kho'   => $r['kho'],
			'token' => VHCC_Auth::phat_token( $r['ho_ten'], $vai, $r['coso'], $r['ma_nv'] ),
		);
	}

	/**
	 * PIN -> người chấm công được, tìm trong CẢ HAI kho.
	 *
	 * =========================================================================================
	 * 🔴 VÌ SAO PHẢI HAI KHO — bản đầu chỉ đọc `phan_quyen`, và KHÔNG MỘT AI vào được trạm
	 * =========================================================================================
	 * `phan_quyen` là BẢN SAO sổ PhanQuyen của app Apps Script cũ, chỉ có dữ liệu nếu đã bấm
	 * nút kéo về. Thực tế trên khmatrix.com: hồ sơ Nhân sự có **240 người khai PIN**, còn
	 * `phan_quyen` thì trống — nên mọi PIN đều rơi vào nhánh "PIN không đúng hoặc chưa được
	 * cấp". Người dùng thấy PIN của mình dùng được ở cửa khác, mà cửa này chối; không có gì
	 * trên màn hình chỉ ra rằng vấn đề là hai cửa đọc hai sổ khác nhau.
	 *
	 * Nay tìm ở cả hai, THEO THỨ TỰ:
	 *   1. `phan_quyen.ma_cc_online` — nơi bản gốc khai riêng "ai chấm công online được".
	 *   2. hồ sơ `nhan_vien` — `pin_dang_nhap` + `ma_nv`, tức nơi anh Thắng thật sự nhập liệu.
	 * Kho 1 đi trước vì nó là lời khai CÓ CHỦ Ý cho đúng việc này (và mang theo cơ sở riêng);
	 * hồ sơ chỉ là suy ra. Khai ở kho 1 thì kho 1 thắng.
	 *
	 * ⚠️ VẪN GÁC NHƯ CŨ: phải CÓ MÃ NV. Không có mã thì lượt chấm ghi vào đâu cũng không tra
	 *    ra người. Đây không phải nới quyền — chỉ là đọc thêm một cuốn sổ nữa của CÙNG một luật.
	 *
	 * ⚠️ RỬA PIN CẢ HAI BÊN. Sổ xuất từ Google Sheets ghi PIN thành SỐ, nên `246810` nằm trong
	 *    cột dưới dạng `"246810.0"`. So thẳng `WHERE pin=%s` với PIN người ta gõ thì không bao
	 *    giờ khớp, mà màn Cài đặt vẫn in "có PIN" — sai âm thầm. `VHCC_Auth::pin_sach()` đã có
	 *    sẵn phép rửa đó; ở đây đọc ra rồi so bằng PHP để phép rửa áp cho CẢ cột lẫn ô nhập.
	 *
	 * @return array{thay:bool, vi_sao:string, ma_nv:string, ho_ten:string, coso:string, kho:string}
	 */
	public static function tim_pin( $pin ) {
		global $wpdb;
		$pin    = VHCC_Auth::pin_sach( $pin );
		$khong  = array( 'thay' => false, 'vi_sao' => '', 'ma_nv' => '', 'ho_ten' => '',
			'coso' => '', 'vai_tro' => '', 'kho' => '' );
		if ( '' === $pin ) { return $khong; }

		$vi_sao = '';

		/* ---- kho 1: bản sao sổ PhanQuyen ---- */
		$t_pq = VHCC_DB::t( 'phan_quyen' );
		if ( VHCC_DB::co_bang( $t_pq ) ) {
			$ds = $wpdb->get_results(
				"SELECT pin, ho_ten, vai_tro, ma_cc_online, coso_cc_online FROM $t_pq WHERE pin <> ''", ARRAY_A );
			foreach ( (array) $ds as $r ) {
				if ( VHCC_Auth::pin_sach( $r['pin'] ) !== $pin ) { continue; }
				$ma = trim( (string) $r['ma_cc_online'] );
				if ( '' !== $ma ) {
					return array( 'thay' => true, 'vi_sao' => '', 'ma_nv' => $ma,
						'ho_ten'  => trim( (string) $r['ho_ten'] ),
						'coso'    => VHCC_NhanSu::chuan_coso( $r['coso_cc_online'] ),
						'vai_tro' => trim( (string) $r['vai_tro'] ), 'kho' => 'phan_quyen' );
				}
				/* Có tên trong sổ nhưng chưa khai mã — nhớ lại để báo, nhưng ĐỪNG trả về ngay:
				   cùng người đó có thể đã khai mã bên hồ sơ, và hồ sơ mới là nơi đang được dùng. */
				$vi_sao = 'Tài khoản ' . trim( (string) $r['ho_ten'] )
					. ' chưa được bật chấm công online (chưa khai "Mã NV chấm công online" '
					. 'và hồ sơ nhân sự cũng chưa có Mã NV). Nhờ quản lý khai giúp — không phải gõ lại PIN.';
			}
		}

		/* ---- kho 2: hồ sơ nhân sự ---- */
		$t_hs = VHCC_DB::t( 'nhan_vien' );
		if ( VHCC_DB::co_bang( $t_hs ) ) {
			$ds = $wpdb->get_results(
				"SELECT ma_nv, ho_ten, cua_hang, vai_tro, pin_dang_nhap, trang_thai_lam_viec"
				. " FROM $t_hs WHERE pin_dang_nhap <> ''", ARRAY_A );
			$nghi = '';
			foreach ( (array) $ds as $r ) {
				if ( VHCC_Auth::pin_sach( $r['pin_dang_nhap'] ) !== $pin ) { continue; }
				$ma = trim( (string) $r['ma_nv'] );
				if ( '' === $ma ) { continue; }
				/* Đã nghỉ thì KHÔNG cho chấm — nhưng nói thẳng ra là "đã nghỉ", đừng để họ
				   đứng gõ lại PIN. Đây là cùng một luật với bảng đối chiếu máy chấm công. */
				if ( VHCC_NhanSu::da_nghi( $r['trang_thai_lam_viec'] ) ) {
					$nghi = 'Hồ sơ ' . trim( (string) $r['ho_ten'] ) . ' đang ghi "'
						. trim( (string) $r['trang_thai_lam_viec'] ) . '" nên không chấm công được. '
						. 'Nếu đi làm lại, nhờ quản lý sửa Trạng thái làm việc trong hồ sơ.';
					continue;
				}
				return array( 'thay' => true, 'vi_sao' => '', 'ma_nv' => $ma,
					'ho_ten'  => trim( (string) $r['ho_ten'] ),
					'coso'    => VHCC_NhanSu::chuan_coso( $r['cua_hang'] ),
					'vai_tro' => trim( (string) $r['vai_tro'] ), 'kho' => 'ho_so' );
			}
			if ( '' !== $nghi ) { $vi_sao = $nghi; }
		}

		$khong['vi_sao'] = $vi_sao;
		return $khong;
	}

	/**
	 * Thẻ phiên -> người, ở dạng VHCC_Online cần: array('ma_nv','ho_ten','coso').
	 *
	 * ⚠️ Nhận thẻ CHUNG của hệ, không còn vai riêng cho trạm. Hai phép gác thay cho vai giả cũ:
	 *      1. Có quyền `cham_online` — mọi vai đều có, nhưng phải là một vai HỢP LỆ.
	 *      2. Thẻ phải mang MÃ NV. Không có mã thì lượt chấm ghi vào đâu cũng không tra ra
	 *         người, nên chối ở cửa còn hơn ghi vào một dòng vô chủ.
	 */
	public static function nguoi( $token ) {
		$u = VHCC_Auth::user_by_token( $token );
		if ( ! $u ) { return null; }
		if ( ! VHCC_Vai::duoc( $u, 'cham_online' ) ) { return null; }
		$ma = trim( isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' );
		if ( '' === $ma ) { return null; }
		return array( 'ma_nv' => $ma, 'ho_ten' => (string) $u['name'], 'coso' => (string) $u['coso'],
			'name' => (string) $u['name'], 'role' => (string) $u['role'] );
	}

	// ==================================================================== giao diện

	public static function render() {
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		/* Trạng thái thư viện nhận diện: hỏi MÁY CHỦ một lần, không để trình duyệt tự dò.
		   Trình duyệt dò thiếu file thì nhận về trang lỗi 404 của WordPress, cố đọc như
		   JavaScript, rồi ném lỗi giữa lúc người ta đang chấm công. */
		$tv  = VHCC_Mat::thu_vien();
		$cfg = array(
			'cong' => esc_url_raw( self::url() ),
			'ver'  => VHCC_VERSION,
			'mat'  => array(
				'co'  => ( VHCC_Mat::bat() && $tv['co'] ),
				'js'  => $tv['co'] ? esc_url_raw( $tv['js'] ) : '',
				'mau' => $tv['co'] ? esc_url_raw( $tv['mau_url'] ) : '',
			),
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
