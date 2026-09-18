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

	/* ═══════════════════════════════════════════════════════════════════════════════════════
	 * VÉ GIỜ — cách DUY NHẤT một lượt chấm gửi lại sau khi mất mạng được mang theo giờ cũ.
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 GÁC 1 KHÔNG ĐƯỢC NỚI. "Giờ lấy ở máy chủ, tuyệt đối không nhận giờ từ điện thoại" —
	 *    nhận giờ của client là ai cũng tự khai mình đến từ 8 giờ sáng. Hàng đợi offline thì
	 *    lại BUỘC phải ghi một giờ đã trôi qua. Hai điều ấy chỉ sống chung được nếu giờ ấy
	 *    KHÔNG do điện thoại nghĩ ra, mà do CHÍNH MÁY CHỦ phát ra lúc còn mạng.
	 *
	 *    Nên `viec=gio` phát kèm một cái vé đã ký. Vé nói: *"máy chủ ở thời điểm M, vé này của
	 *    thẻ phiên T, dùng được tới M+30 phút"*. Lượt chấm gửi lại nộp vé cùng số mili giây đã
	 *    trôi (đo bằng `performance.now()`, không đọc đồng hồ máy). Máy chủ tự cộng lại và tự
	 *    kiểm — điện thoại không chọn được một con số nào nằm ngoài khoảng vé cho phép.
	 *
	 * 🔴 BA CHỐT, THIẾU CHỐT NÀO CŨNG THÀNH CỬA KHAI GIỜ TỰ DO:
	 *    1. CHỮ KÝ. Không ký thì vé là một con số gõ tay được, và cả bộ này chỉ là thủ tục.
	 *    2. BUỘC VÀO THẺ PHIÊN. Vé không gắn thẻ thì một người xin vé lúc 6 giờ rồi đưa vé cho
	 *       cả cửa hàng — mười người cùng "đến từ 6 giờ".
	 *    3. HẠN NGẮN. Giờ khai được chỉ nằm trong khoảng [lúc phát vé, lúc phát vé + 30 phút].
	 *       Hạn dài là mở lại đúng cái lỗ vừa bịt: xin vé lúc 6h, 9h mới bấm, khai 6h.
	 *
	 * ⚠️ VÉ KHÔNG PHẢI THẺ ĐĂNG NHẬP. Nó chỉ chứng nhận MỘT MỐC GIỜ, không mở cửa gì. Lộ vé thì
	 *    kẻ cầm nó cũng phải có thẻ phiên khớp mới dùng được, và dùng được cũng chỉ để ghi một
	 *    lượt chấm vào đúng nửa giờ đã qua — bằng đúng việc họ làm được khi chấm bình thường.
	 */
	const VE_HAN = 1800;

	/**
	 * Lượt gửi lại cũ hơn bấy nhiêu giây thì THÔI, không nhận nữa.
	 *
	 * 🔴 VÌ SAO PHẢI CÓ TRẦN, dù vé đã có hạn riêng. Hạn vé chặn việc khai một giờ QUÁ SỚM;
	 *    trần này chặn việc NỘP QUÁ MUỘN. Hai chuyện khác nhau: một cái điện thoại để trong
	 *    ngăn kéo ba ngày rồi mới có mạng sẽ nhả ra một lượt chấm cho ngày thứ Hai vào sáng
	 *    thứ Năm — công của một ngày đã chốt bỗng đổi, sau khi bảng công ngày ấy đã được đọc,
	 *    đã được duyệt, và có khi đã tính lương. Việc sửa công của ngày đã qua có cửa riêng
	 *    (chấm bù), có người duyệt và có dấu vết; hàng đợi không được lén làm thay việc ấy.
	 */
	const GUI_LAI_TOI_DA = 43200;   // 12 giờ

	private static function ve_ky( $moc, $han, $token ) {
		return substr( hash_hmac( 'sha256',
			(int) $moc . '|' . (int) $han . '|' . (string) $token, wp_salt( 'auth' ) ), 0, 32 );
	}

	/** Vé cho một thẻ phiên. Thẻ rỗng -> không phát vé (khách chưa đăng nhập không cần vé). */
	public static function ve_gio( $token ) {
		$token = trim( (string) $token );
		if ( '' === $token ) { return ''; }
		$moc = (int) current_time( 'timestamp' );
		$han = $moc + self::VE_HAN;
		return $moc . '.' . $han . '.' . self::ve_ky( $moc, $han, $token );
	}

	/**
	 * Đọc vé + số mili giây đã trôi -> mốc giờ ĐƯỢC PHÉP ghi.
	 *
	 * @return array array('ok'=>bool,'moc'=>int,'cham'=>int giây trễ,'error'=>string)
	 */
	public static function doc_ve( $ve, $token, $troi_ms ) {
		$chia = explode( '.', trim( (string) $ve ) );
		if ( 3 !== count( $chia ) ) {
			return array( 'ok' => false, 'error' => 'Vé giờ không đọc được — chấm lại khi có mạng.' );
		}
		list( $moc, $han, $ky ) = $chia;
		if ( ! ctype_digit( (string) $moc ) || ! ctype_digit( (string) $han ) ) {
			return array( 'ok' => false, 'error' => 'Vé giờ hỏng — chấm lại khi có mạng.' );
		}
		$moc = (int) $moc;
		$han = (int) $han;
		/* `hash_equals` chứ không phải `===`: so chuỗi bằng `===` thoát ra ở byte đầu khác nhau,
		   và thời gian thoát ấy đo được — đủ để dò dần ra chữ ký đúng. */
		if ( ! hash_equals( self::ve_ky( $moc, $han, $token ), (string) $ky ) ) {
			return array( 'ok' => false, 'error' => 'Vé giờ không phải của phiên này — đăng nhập '
				. 'lại rồi chấm lại.' );
		}

		$troi = ( is_numeric( $troi_ms ) && $troi_ms > 0 ) ? (int) round( (float) $troi_ms / 1000 ) : 0;
		$khai = $moc + $troi;
		$nay  = (int) current_time( 'timestamp' );

		/* Khai vượt hạn vé -> kéo về mốc vé. KHÔNG chối: người bấm không làm gì sai, họ chỉ
		   mất mạng lâu hơn nửa giờ. Kéo về là ghi SỚM HƠN thực tế — bất lợi cho người bấm ở
		   lượt giờ vào, nhưng nó là con số máy chủ chắc chắn, và lệch bao nhiêu thì có ghi. */
		$keo = false;
		if ( $khai > $han ) { $khai = $han; $keo = true; }

		/* 🔴 KHÔNG BAO GIỜ GHI MỘT GIỜ Ở TƯƠNG LAI. Đồng hồ máy chủ có thể bị chỉnh lùi (đồng bộ
		   NTP, đổi múi giờ của hosting) và lúc đó mốc cũ nằm sau "bây giờ". Ghi giờ ra ở tương
		   lai là ca ấy dài thêm vài tiếng mà không ai hiểu vì sao. */
		if ( $khai > $nay ) { $khai = $nay; }

		$cham = $nay - $khai;
		if ( $cham > self::GUI_LAI_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Lượt chấm này giữ trong máy đã '
				. round( $cham / 3600 ) . ' giờ — quá lâu để tự ghi vào bảng công. Nhờ quản lý '
				. 'chấm bù (có ghi lại ai bù, vì sao).' );
		}
		return array( 'ok' => true, 'moc' => $khai, 'cham' => $cham, 'keo' => $keo );
	}

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
		/* Ô ảnh bản đồ — xử TRƯỚC `nocache_headers()`, và đó là điểm mấu chốt.
		   `nocache_headers()` đặt `Pragma: no-cache` cùng `Expires` quá khứ; để nó chạy rồi mới
		   trả ảnh thì trình duyệt KHÔNG nhớ ô ảnh nào cả, và mỗi lần mở trang là chín lượt hỏi
		   lại máy chủ — đúng thứ mà việc nhớ đệm sinh ra để tránh. Mấy tiêu đề ấy đúng cho JSON
		   của lượt chấm công, sai cho một mảnh bản đồ đường phố.

		   Không đòi đăng nhập: thẻ <img> không mang phiên theo được, mà nội dung cũng chỉ là
		   bản đồ công khai. Phần gác nằm ở VHCC_BanDo — chỉ mức phóng đang dùng, chỉ vùng Việt
		   Nam, và địa chỉ đích dựng từ ba số nguyên đã kiểm. */
		if ( 'o' === $viec ) {
			VHCC_BanDo::phuc_vu(
				isset( $_GET['z'] ) ? (int) $_GET['z'] : 0,
				isset( $_GET['x'] ) ? (int) $_GET['x'] : -1,
				isset( $_GET['y'] ) ? (int) $_GET['y'] : -1
			);
		}

		/**
		 * ĐƯỜNG CHẨN ĐOÁN — mở bằng trình duyệt, đọc được bằng mắt, chụp màn hình gửi đi được.
		 *
		 * 🔴 VÌ SAO CẦN. Khi trang trạm đứng im, người dùng chỉ chụp được cái màn hình đứng im
		 *    ấy — và nhìn vào đó thì không ai biết máy chủ đang trả về cái gì, bảng đã dựng
		 *    chưa, plugin bản mấy. Ba lần liền phải đoán qua ảnh. Một đường in thẳng mấy con số
		 *    đó ra chữ thì hết đoán.
		 *
		 * ⚠️ KHÔNG IN GÌ BÍ MẬT. Không PIN, không thẻ phiên, không khoá, không tên người. Đường
		 *    này ai gõ trúng cũng mở được — nên nó chỉ được nói về TÌNH TRẠNG MÁY, không nói về
		 *    người. Cái duy nhất lộ ra là plugin có chạy hay không, mà nhìn trang chấm công thì
		 *    cũng biết rồi.
		 */
		if ( 'chan_doan' === $viec ) {
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			$tv = VHCC_Mat::thu_vien();
			$d  = array(
				'plugin'      => VHCC_VERSION,
				'so_do_bang'  => VHCC_DB::SCHEMA_VERSION,
				'da_cai'      => (string) get_option( 'vhcc_ver' ),
				'php'         => PHP_VERSION,
				'gio_may_chu' => current_time( 'Y-m-d H:i:s' ),
				'mui_gio_wp'  => (string) get_option( 'timezone_string' ) . ' / '
					. (string) get_option( 'gmt_offset' ),
			);
			echo "== CHAM CONG: CHAN DOAN ==\n";
			foreach ( $d as $k => $v ) { echo str_pad( $k, 14 ) . ': ' . $v . "\n"; }

			echo "\n-- bang du lieu --\n";
			foreach ( array( 'cham_cong', 'nhan_vien', 'session', 'ghi_chu', 'mat_mau', 'mat_nhat_ky' ) as $bg ) {
				echo str_pad( $bg, 14 ) . ': '
					. ( VHCC_DB::co_bang( VHCC_DB::t( $bg ) ) ? 'co' : 'CHUA CO' ) . "\n";
			}

			echo "\n-- doi chieu khuon mat --\n";
			echo 'bat           : ' . ( VHCC_Mat::bat() ? 'co' : 'khong' ) . "\n";
			echo 'che do        : ' . VHCC_Mat::che_do() . "\n";
			echo 'thu vien      : ' . ( $tv['co'] ? 'san sang' : 'thieu ' . count( $tv['thieu'] ) . ' tep' ) . "\n";
			echo 'noi dat       : ' . str_replace( ABSPATH, '', $tv['noi'] ) . "\n";
			$dm = VHCC_Mat::dem();
			echo 'mau khuon mat : ' . (int) $dm['tong'] . ' (cho duyet ' . (int) $dm['cho'] . ")\n";

			echo "\n-- duong dan --\n";
			echo 'tram          : ' . self::url() . "\n";
			echo 'quan tri      : ' . VHCC_Web::url() . "\n";
			echo "\nNeu doc duoc dong nay thi may chu CHAY BINH THUONG va tra ve duoc noi dung.\n";
			exit;
		}

		nocache_headers();
		$b = self::than();

		/* --- việc công khai: chưa đăng nhập cũng gọi được --- */
		if ( 'gio' === $viec ) {
			/* Vé giờ đi kèm mốc, CHỈ khi lượt hỏi có mang thẻ phiên. Màn đăng nhập cũng gọi
			   `gio` để chạy đồng hồ, và lúc ấy chưa có thẻ — không phát vé cho nó là đúng:
			   vé không gắn thẻ thì một người xin vé rồi đưa cho cả cửa hàng. */
			$g = VHCC_Online::gio_may_chu();
			$tk_g = isset( $b['token'] ) ? (string) $b['token'] : '';
			if ( '' !== $tk_g && self::nguoi( $tk_g ) ) { $g['ve'] = self::ve_gio( $tk_g ); }
			self::ra( $g );
		}

		if ( 'anhmau' === $viec ) { self::ra( VHCC_Online::anh_mau_the() ); }

		if ( 'vao' === $viec ) {
			$kq = self::dang_nhap( isset( $b['pin'] ) ? $b['pin'] : '' );
			/* Mở luôn phiên của trang quản trị bằng CHÍNH thẻ này. Người đủ bậc bấm sang là vào
			   thẳng; người không đủ bậc thì phiên ấy cũng chỉ mở đúng màn "Công của tôi" — cửa
			   nằm ở từng màn, không nằm ở việc có cookie hay không. */
			if ( ! empty( $kq['ok'] ) && ! headers_sent() ) { VHCC_Web::mo_phien( $kq['token'] ); }
			self::ra( $kq );
		}

		/* ĐANG CÓ PHIÊN Ở TRANG QUẢN TRỊ THÌ VÀO THẲNG — xem `phien_tu_cookie()`. */
		if ( 'phien' === $viec ) { self::ra( self::phien_tu_cookie() ); }

		/**
		 * HỘP TIN CHO WORKER — cố ý KHÔNG đòi thẻ phiên.
		 *
		 * ⚠️ Worker chạy khi trang đã đóng: nó không đọc được `localStorage`, nên không có thẻ
		 *    nào để gửi kèm. Gác ở đây là `khoa_hop` — chìa 64 ký tự hex do máy chủ phát lúc
		 *    đăng ký, worker cất ở IndexedDB. Chìa ấy mở đúng một việc: đọc tin chờ của chính
		 *    thuê bao đó. Không chấm công được, không xem được lương.
		 */
		if ( 'push_hop' === $viec ) {
			$b = self::than();
			self::ra( VHCC_Push::hop( isset( $b['khoa'] ) ? (string) $b['khoa'] : '' ) );
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
			/* Mốc giờ trong `toi` phải đi KÈM VÉ, y như `gio`. Trạm đặt lại `MOC` từ lượt này,
			   và một mốc không vé là mốc không dùng được cho hàng đợi — lúc mất mạng mới lộ
			   ra, tức đúng lúc không sửa được gì. */
			if ( isset( $tt['gio'] ) && is_array( $tt['gio'] ) ) {
				$tt['gio']['ve'] = self::ve_gio( isset( $b['token'] ) ? (string) $b['token'] : '' );
			}
			/* Đường sang trang quản trị — CHỈ gửi cho người thật sự mở được nó. Gửi cho ai
			   cũng thì nhân viên bấm vào rồi nhận một trang chối, và họ tưởng mình hỏng máy.
			   Cùng một thẻ dùng được cả hai bên nên bấm sang là vào thẳng, không gõ PIN lại. */
			if ( VHCC_Vai::duoc( $u, 'cong_coso' ) ) {
				$tt['qtUrl'] = VHCC_Web::url();
				$tt['vaiTen'] = VHCC_Vai::ten( $u );
			}
			/* Số chuông đi KÈM lượt `toi`, không phải một lượt gọi riêng. Gọi riêng thì lúc
			   vừa đăng nhập xong chuông trắng mấy trăm mili-giây rồi mới nhảy số — nhìn như
			   trang bị giật, và ai bấm nhanh thì bấm vào cái chuông đang nói dối là rỗng. */
			$tt['chuongCo']  = VHCC_Chuong::co();
			$tt['chuongDem'] = VHCC_Chuong::dem( $u );
			self::ra( $tt );
		}

		if ( 'push_dk' === $viec ) {
			$b = self::than();
			self::ra( VHCC_Push::dang_ky(
				$u['ma_nv'],
				isset( $b['endpoint'] ) ? (string) $b['endpoint'] : '',
				isset( $b['p256dh'] ) ? (string) $b['p256dh'] : '',
				isset( $b['auth'] ) ? (string) $b['auth'] : '',
				isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''
			) );
		}

		if ( 'push_huy' === $viec ) {
			$b = self::than();
			self::ra( VHCC_Push::huy( isset( $b['endpoint'] ) ? (string) $b['endpoint'] : '' ) );
		}

		/* ============ CHUÔNG THÔNG BÁO ============
		   Hai đầu nối này KHÔNG nhận mã NV từ thân yêu cầu — `VHCC_Chuong` lấy mã từ `$u`,
		   tức từ thẻ phiên do máy chủ cấp. Nhận từ thân là gửi lên mã người khác thì đọc được
		   hộp thư người ta. Xem khối cảnh báo ở đầu `class-vhcc-chuong.php`. */
		/* ============ XIN BÙ GIỜ ============
		   Mã NV và cơ sở lấy từ thẻ phiên + hồ sơ, không nhận từ thân — nhận từ thân là xin bù
		   hộ người khác. Xem khối đầu `class-vhcc-xin-bu.php`. */
		if ( 'xinbu' === $viec ) {
			$b = self::than();
			self::ra( VHCC_XinBu::gui( $u,
				isset( $b['ngay'] ) ? (string) $b['ngay'] : '',
				isset( $b['vao'] ) ? (string) $b['vao'] : '',
				isset( $b['ra'] ) ? (string) $b['ra'] : '',
				isset( $b['lyDo'] ) ? (string) $b['lyDo'] : '' ) );
		}

		if ( 'xinbuds' === $viec ) {
			self::ra( array( 'ok' => true, 'ds' => VHCC_XinBu::cua_toi( $u ),
				'ten' => VHCC_XinBu::TEN_TT, 'homNay' => (string) current_time( 'Y-m-d' ) ) );
		}

		/* Cửa hàng trưởng duyệt CẤP MỘT ngay trên điện thoại — đó là chỗ họ đứng cả ngày. */
		if ( 'buchocht' === $viec ) {
			$b  = self::than();
			$cs = VHCC_NhanSu::chuan_coso( isset( $b['coSo'] ) ? (string) $b['coSo'] : '' );
			if ( '' === $cs || ! VHCC_NhanSu::co_quyen_coso( $u, $cs ) ) {
				self::ra( array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' ) );
			}
			self::ra( array( 'ok' => true, 'coSo' => $cs,
				'ds' => VHCC_XinBu::cho_duyet( VHCC_XinBu::CHO_CHT, $cs ) ) );
		}

		if ( 'buduyet' === $viec ) {
			$b = self::than();
			self::ra( VHCC_XinBu::duyet_cht( $u,
				isset( $b['id'] ) ? (int) $b['id'] : 0,
				! empty( $b['dongY'] ),
				isset( $b['lyDo'] ) ? (string) $b['lyDo'] : '' ) );
		}

		/* ============ GIỜ TỰ KHAI ============
		   Không nhận `maNV` lẫn `coSo` từ thân: cả hai lấy từ thẻ phiên và từ hồ sơ của chính
		   người ấy — xem `VHCC_GioKhai::coso_cua()`. Đây là cửa mở cho bậc thấp nhất trong hệ
		   nên chốt phải chặt nhất. */
		if ( 'khaigio' === $viec ) {
			$b = self::than();
			self::ra( VHCC_GioKhai::khai( $u,
				isset( $b['ngay'] ) ? (string) $b['ngay'] : '',
				isset( $b['soGio'] ) ? (string) $b['soGio'] : '',
				isset( $b['viec'] ) ? (string) $b['viec'] : '',
				isset( $b['ghiChu'] ) ? (string) $b['ghiChu'] : '' ) );
		}

		if ( 'khaids' === $viec ) {
			self::ra( array( 'ok' => true, 'ds' => VHCC_GioKhai::cua_toi( $u ),
				'homNay' => (string) current_time( 'Y-m-d' ) ) );
		}

		if ( 'chuong' === $viec ) {
			self::ra( VHCC_Chuong::ds( $u ) );
		}

		if ( 'chuongdoc' === $viec ) {
			$b = self::than();
			self::ra( VHCC_Chuong::doc( $u, isset( $b['id'] ) ? (int) $b['id'] : 0 ) );
		}

		if ( 'hoso' === $viec ) {
			self::ra( VHCC_HoSoToi::doc( $u['ma_nv'] ) );
		}

		if ( 'luu_hoso' === $viec ) {
			$b = self::than();
			self::ra( VHCC_HoSoToi::luu( $u, isset( $b['hs'] ) ? $b['hs'] : array() ) );
		}

		/* Đổi PIN — uỷ THẲNG cho `VHCC_Quyen::doi_pin()`, không viết lại.
		   ⚠️ Hàm ấy đã mang đủ chốt: cấm PIN dễ đoán, chặn nhịp độ khi dò trúng PIN người
		      khác, và nói thật khi trùng. Viết một bản rút gọn ở đây là bỏ mất cả ba mà không
		      ai nhận ra, vì đường này vẫn "chạy được". */
		if ( 'doi_pin' === $viec ) {
			$b = self::than();
			self::ra( VHCC_Quyen::doi_pin(
				isset( $b['cu'] ) ? $b['cu'] : '',
				isset( $b['moi'] ) ? $b['moi'] : '',
				isset( $b['lai'] ) ? $b['lai'] : ''
			) );
		}

		if ( 'ung' === $viec ) {
			/* ⚠️ THỨ TỰ NHÓM DO MÁY CHỦ ĐẶT, không để trình duyệt tự gom. Trình duyệt gom thì
			   thứ tự ra theo thứ tự ô gặp được, tức thêm một ô ở giữa là cả trang đổi bố cục
			   — người dùng nhớ chỗ bằng mắt, không đọc lại tiêu đề mỗi lần. */
			$ds_ung = VHCC_Ung::ds( $u );
			self::ra( array( 'ok' => true, 'ds' => $ds_ung, 'nhom' => VHCC_Ung::ds_nhom( $ds_ung ) ) );
		}

		if ( 'cham' === $viec ) {
			$gps = ( isset( $b['gps'] ) && is_array( $b['gps'] ) ) ? $b['gps'] : null;

			/* LƯỢT GỬI LẠI TỪ HÀNG ĐỢI — mang vé giờ, nên ghi vào giờ ĐÃ BẤM chứ không phải
			   giờ máy chủ nhận được. Lượt chấm bình thường không có vé và đi đúng đường cũ:
			   `$moc = null` -> `cham_cong()` tự lấy giờ máy chủ, gác 1 nguyên vẹn. */
			$moc  = null;
			$tre_gui = 0;
			if ( ! empty( $b['veGio'] ) ) {
				$v = self::doc_ve( (string) $b['veGio'],
					isset( $b['token'] ) ? (string) $b['token'] : '',
					isset( $b['troi'] ) ? $b['troi'] : 0 );
				if ( empty( $v['ok'] ) ) { self::ra( array( 'ok' => false, 'error' => $v['error'] ) ); }
				$moc = (int) $v['moc'];
				$tre_gui = (int) $v['cham'];
			}

			self::ra( VHCC_Online::cham_cong(
				$u,
				isset( $b['anh'] ) ? (string) $b['anh'] : '',
				$gps,
				isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				isset( $b['nhiemVu'] ) ? (string) $b['nhiemVu'] : '',
				$moc,
				$tre_gui
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

		/* ═════════════════════════════════════════════════════════════════════════════════
		 * XIN PHÉP NGAY TRÊN TRẠM — đi trễ, và đổi lịch (gồm cả xin nghỉ một ngày).
		 *
		 * Anh Thắng 27/08/2026 đã nói rõ chỗ nộp đơn đi trễ nằm ở đâu: *"tại trang chấm công
		 * online nhân viên sẽ chọn Xin Phép đi trễ TRƯỚC KHI TỚI cửa hàng"*. Nghiệp vụ ấy viết
		 * xong từ bản 3.x và chạy đủ (VHCC_XinTre), nhưng MÀN ĐỂ NỘP thì chưa bao giờ có — nó
		 * chỉ hiện ở trang quản trị, nơi nhân viên không vào được. Nên trên thực tế người duy
		 * nhất nộp được đơn đi trễ là cửa hàng trưởng, nộp hộ.
		 *
		 * 🔴 KHÔNG VIẾT LẠI MỘT DÒNG NGHIỆP VỤ NÀO Ở ĐÂY. Cả hai lệnh chuyển thẳng xuống
		 *    `VHCC_XinTre::nop()` và `VHCC_Lich::xin_doi_lich()` — đúng hai hàm mà trang quản
		 *    trị gọi. Dựng đường nộp thứ hai là hai bộ luật (hạn nộp trước/sau, một người một
		 *    ngày một đơn, đơn nộp lại về chờ duyệt), và sớm muộn hai bộ lệch nhau ở đúng chỗ
		 *    không ai kịp phát hiện.
		 *
		 * ⚠️ MÃ NV LUÔN LẤY TỪ THẺ PHIÊN. `nop()` đã tự lấy từ `$u` và cố ý không đọc biểu mẫu;
		 *    `xin_doi_lich()` thì NHẬN mã từ tham số, nên ở đây phải tự chèn mã của phiên vào và
		 *    KHÔNG được chuyển tiếp `ma_nv` do trình duyệt gửi lên — không thì ai cũng nộp được
		 *    đơn đứng tên người khác.
		 * ═════════════════════════════════════════════════════════════════════════════════ */
		/**
		 * LỊCH LÀM CỦA TÔI — một tháng, chỉ của chính người đang đăng nhập.
		 *
		 * 🔴 MÃ NV TỪ PHIÊN, và phép lọc nằm ở CÂU SQL (xem `VHCC_Lich::lich_cua_nguoi`). Lấy cả
		 *    lịch cơ sở rồi lọc ở trình duyệt là đã gửi lịch của toàn bộ đồng nghiệp xuống máy
		 *    một nhân viên — họ không thấy trên màn, nhưng nó nằm trong lượt trả về.
		 *
		 * ⚠️ TRẢ VỀ CẢ KHI CƠ SỞ CHƯA BẬT PHÂN LỊCH. Lúc ấy danh sách rỗng, và màn nói "chưa xếp
		 *    lịch" — khác hẳn với chối lượt gọi. Chối thì người dùng thấy một câu lỗi đỏ cho một
		 *    việc chẳng ai làm sai.
		 */
		if ( 'lichtoi' === $viec ) {
			$th_l = isset( $b['thang'] ) ? (string) $b['thang'] : '';
			if ( ! preg_match( '/^\d{4}-\d{2}$/', $th_l ) ) { $th_l = current_time( 'Y-m' ); }
			$tu_l  = $th_l . '-01';
			$den_l = gmdate( 'Y-m-t', strtotime( $tu_l . ' 00:00:00 UTC' ) );
			self::ra( array(
				'ok'      => true,
				'thang'   => $th_l,
				'homNay'  => current_time( 'Y-m-d' ),
				'dong'    => VHCC_Lich::lich_cua_nguoi( $u['ma_nv'], $tu_l, $den_l ),
			) );
		}

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * CỬA HÀNG TRƯỞNG — nghiệp vụ ở `VHCC_CuaHang`, mấy cửa này chỉ chuyển tiếp.
		 *
		 * 🔴 KHÔNG CÓ CỬA NÀO TỰ QUYẾT QUYỀN. Chúng gửi thẳng `$u` xuống, và `VHCC_CuaHang` gọi
		 *    lại đúng hàm nghiệp vụ đã có — hàm ấy chốt cơ sở từ CHÍNH BẢN GHI. Kiểm quyền ở
		 *    đây nữa là bộ luật quyền thứ hai, và bộ thứ hai bao giờ cũng lệch trước.
		 * ══════════════════════════════════════════════════════════════════════════════════ */
		if ( 'cuahang' === $viec ) {
			if ( ! VHCC_CuaHang::duoc( $u ) ) { self::ra( array( 'ok' => true, 'duoc' => false ) ); }
			self::ra( array(
				'ok'     => true,
				'duoc'   => true,
				'dsCoSo' => VHCC_CuaHang::ds_coso( $u ),
				'thang'  => current_time( 'Y-m' ),
				/* Câu nhắc "hết 24h là khoá" do CHÍNH nơi thi hành luật đọc ra, không phải màn
				   tự chế — bày một câu mà luật không làm đúng vậy là nói dối người dùng. */
				'nhacHan' => VHCC_Bu::nhac_han_ngay( $u ),
				'homNay'  => current_time( 'Y-m-d' ),
			) );
		}

		if ( 'chdon' === $viec ) {
			self::ra( VHCC_CuaHang::don_cho( $u, isset( $b['coSo'] ) ? (string) $b['coSo'] : '' ) );
		}

		if ( 'chduyet' === $viec ) {
			self::ra( VHCC_CuaHang::duyet( $u,
				isset( $b['loai'] ) ? (string) $b['loai'] : '',
				isset( $b['id'] ) ? (string) $b['id'] : '',
				! empty( $b['dongY'] ),
				isset( $b['lyDo'] ) ? (string) $b['lyDo'] : '' ) );
		}

		if ( 'chcong' === $viec ) {
			self::ra( VHCC_CuaHang::cong_coso( $u,
				isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				isset( $b['thang'] ) ? (string) $b['thang'] : '' ) );
		}

		if ( 'chngay' === $viec ) {
			self::ra( VHCC_CuaHang::ngay_cua( $u,
				isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				isset( $b['thang'] ) ? (string) $b['thang'] : '',
				isset( $b['maNV'] ) ? (string) $b['maNV'] : '' ) );
		}

		if ( 'chsua' === $viec ) {
			self::ra( VHCC_CuaHang::sua_gio( $u, array(
				'coSo'    => isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				'maNV'    => isset( $b['maNV'] ) ? (string) $b['maNV'] : '',
				'ngay'    => isset( $b['ngay'] ) ? (string) $b['ngay'] : '',
				'vao'     => isset( $b['vao'] ) ? (string) $b['vao'] : '',
				'ra'      => isset( $b['ra'] ) ? (string) $b['ra'] : '',
				'xoaVao'  => ! empty( $b['xoaVao'] ),
				'xoaRa'   => ! empty( $b['xoaRa'] ),
				'gay'     => ! empty( $b['gay'] ),
				'nghiTu'  => isset( $b['nghiTu'] ) ? (string) $b['nghiTu'] : '',
				'nghiDen' => isset( $b['nghiDen'] ) ? (string) $b['nghiDen'] : '',
				'lyDo'    => isset( $b['lyDo'] ) ? (string) $b['lyDo'] : '',
			) ) );
		}

		if ( 'chxoadong' === $viec ) {
			self::ra( VHCC_CuaHang::xoa_cong( $u, array(
				'coSo' => isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				'maNV' => isset( $b['maNV'] ) ? (string) $b['maNV'] : '',
				'ngay' => isset( $b['ngay'] ) ? (string) $b['ngay'] : '',
				'lyDo' => isset( $b['lyDo'] ) ? (string) $b['lyDo'] : '',
			) ) );
		}

		if ( 'chchot' === $viec ) {
			self::ra( VHCC_CuaHang::chot_cua( $u,
				isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				isset( $b['thang'] ) ? (string) $b['thang'] : '',
				isset( $b['maNV'] ) ? (string) $b['maNV'] : '' ) );
		}

		if ( 'chchotluu' === $viec ) {
			self::ra( VHCC_CuaHang::chot_luu( $u, array(
				'coSo'        => isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				'thang'       => isset( $b['thang'] ) ? (string) $b['thang'] : '',
				'maNV'        => isset( $b['maNV'] ) ? (string) $b['maNV'] : '',
				'viecChinh'   => isset( $b['viecChinh'] ) ? (string) $b['viecChinh'] : '',
				'dong'        => isset( $b['dong'] ) && is_array( $b['dong'] ) ? $b['dong'] : array(),
				'anLuongThang' => ! empty( $b['anLuongThang'] ),
				'luongCb'     => isset( $b['luongCb'] ) ? $b['luongCb'] : '',
				'congYc'      => isset( $b['congYc'] ) ? $b['congYc'] : '',
				'cong'        => isset( $b['cong'] ) && is_array( $b['cong'] ) ? $b['cong'] : array(),
				'tru'         => isset( $b['tru'] ) && is_array( $b['tru'] ) ? $b['tru'] : array(),
			) ) );
		}

		if ( 'chnhansu' === $viec ) {
			self::ra( VHCC_CuaHang::nhan_su( $u,
				isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				isset( $b['tim'] ) ? (string) $b['tim'] : '' ) );
		}

		if ( 'chthem' === $viec ) {
			self::ra( VHCC_CuaHang::them_nguoi( $u, array(
				'coSo'     => isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				'hoTen'    => isset( $b['hoTen'] ) ? (string) $b['hoTen'] : '',
				'cccd'     => isset( $b['cccd'] ) ? (string) $b['cccd'] : '',
				'sdt'      => isset( $b['sdt'] ) ? (string) $b['sdt'] : '',
				'gioiTinh' => isset( $b['gioiTinh'] ) ? (string) $b['gioiTinh'] : '',
			) ) );
		}

		/**
		 * PHIẾU LƯƠNG CỦA TÔI. Nghiệp vụ ở `VHCC_PhieuLuong`; cửa này chỉ chuyển tiếp.
		 *
		 * 🔴 KHÔNG NHẬN `ma_nv` TỪ BIỂU MẪU. `phieu()` tự lấy từ `$u` và tự kiểm cơ sở gửi lên
		 *    có thuộc về người ấy không — cửa này gửi thẳng `$u` xuống chứ không tự quyết gì,
		 *    để chỉ có MỘT nơi gác chứ không phải hai nơi gác khác nhau.
		 */
		if ( 'phieuluong' === $viec ) {
			/* Cửa hàng trưởng còn xem được CẢ CƠ SỞ — anh Thắng 17/09/2026: *"nhân viên thì 1
			   phiếu của chính mình. Cửa hàng trưởng thì có chính mình và cả cửa hàng"*. Máy chủ
			   quyết có phần ấy hay không; màn chỉ vẽ theo. */
			$cs_ql = VHCC_Vai::duoc( $u, VHCC_PhieuLuong::QUYEN_CS )
				? VHCC_CuaHang::ds_coso( $u ) : array();
			self::ra( array(
				'ok'     => true,
				'dsThang' => VHCC_PhieuLuong::ds_thang( $u ),
				'khoan'  => VHCC_PhieuLuong::ten_khoan(),
				'dsCoSoQl' => $cs_ql,
				'thangNay' => current_time( 'Y-m' ),
			) );
		}

		if ( 'phieucs' === $viec ) {
			self::ra( VHCC_PhieuLuong::ca_coso( $u,
				isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				isset( $b['thang'] ) ? (string) $b['thang'] : '' ) );
		}

		if ( 'phieu' === $viec ) {
			self::ra( VHCC_PhieuLuong::phieu( $u,
				isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				isset( $b['thang'] ) ? (string) $b['thang'] : '' ) );
		}

		/**
		 * BÁO MỘT LƯỢT CHẤM SAI, và danh sách lượt đã báo. Nghiệp vụ ở `VHCC_Cham::nv_bao_sai`.
		 *
		 * ⚠️ Cửa này KHÔNG sửa giờ. Nó gắn một cái cờ nằm cạnh ngày ấy để cửa hàng trưởng thấy ở
		 *    chính màn cờ họ vẫn mở. Cho nhân viên sửa giờ của chính mình là bỏ luôn ý nghĩa của
		 *    việc chấm công.
		 */
		if ( 'baosai' === $viec ) {
			self::ra( VHCC_Cham::nv_bao_sai( $u, array(
				'ngay' => isset( $b['ngay'] ) ? (string) $b['ngay'] : '',
				'coso' => isset( $b['coSo'] ) ? (string) $b['coSo'] : '',
				'lyDo' => isset( $b['lyDo'] ) ? (string) $b['lyDo'] : '',
			) ) );
		}

		if ( 'dabao' === $viec ) {
			self::ra( array( 'ok' => true,
				'dong'   => VHCC_Cham::nv_bao_cua( $u['ma_nv'], 20 ),
				'homNay' => current_time( 'Y-m-d' ),
				'dsCoSo' => VHCC_Online::ds_coso_cham_cua_nv( $u['ma_nv'],
					isset( $u['coso'] ) ? $u['coso'] : '' ),
			) );
		}

		if ( 'xintre' === $viec ) {
			self::ra( VHCC_XinTre::nop( $u, array(
				'ngay'    => isset( $b['ngay'] ) ? (string) $b['ngay'] : '',
				'so_phut' => isset( $b['soPhut'] ) ? $b['soPhut'] : '',
				'ly_do'   => isset( $b['lyDo'] ) ? (string) $b['lyDo'] : '',
			) ) );
		}

		/* ĐƠN XIN NGHỈ. Nghiệp vụ ở `VHCC_XinNghi`; cửa này chỉ chuyển tiếp, và mã NV thì lấy
		   từ thẻ phiên (nop() tự lấy từ `$u`, cố ý không đọc biểu mẫu). */
		if ( 'xinnghi' === $viec ) {
			self::ra( VHCC_XinNghi::nop( $u, array(
				'tu'   => isset( $b['tu'] ) ? (string) $b['tu'] : '',
				'den'  => isset( $b['den'] ) ? (string) $b['den'] : '',
				'loai' => isset( $b['loai'] ) ? (string) $b['loai'] : '',
				'lyDo' => isset( $b['lyDo'] ) ? (string) $b['lyDo'] : '',
			) ) );
		}

		if ( 'xinlich' === $viec ) {
			/* Cơ sở đi lên từ client -> đối chiếu với danh sách người đó thật sự có, đúng gác 2
			   của đường chấm công. Không kiểm thì một người nộp được đơn nghỉ vào lịch cơ sở
			   khác, và cửa hàng trưởng bên ấy thấy một cái tên lạ xin nghỉ. */
			$cs_x = trim( (string) ( isset( $b['coSo'] ) ? $b['coSo'] : '' ) );
			$duoc = VHCC_Online::ds_coso_cham_cua_nv( $u['ma_nv'], isset( $u['coso'] ) ? $u['coso'] : '' );
			$hop  = '';
			foreach ( $duoc as $x ) { if ( 0 === strcasecmp( $x, $cs_x ) ) { $hop = $x; } }
			if ( '' === $hop ) { $hop = VHCC_NhanSu::chuan_coso( isset( $u['coso'] ) ? $u['coso'] : '' ); }
			if ( '' === $hop ) {
				self::ra( array( 'ok' => false, 'error' => 'Hồ sơ của anh/chị chưa tích cơ sở nào '
					. 'nên đơn không biết gửi cho ai duyệt.' ) );
			}
			if ( ! VHCC_Lich::co_bat_lich( $hop ) ) {
				self::ra( array( 'ok' => false, 'error' => 'Cơ sở "' . $hop . '" chưa bật phân lịch '
					. 'nên không có lịch nào để đổi. Xin nghỉ thì báo trực tiếp quản lý.' ) );
			}
			$hs = VHCC_NhanSu::ho_so( $u['ma_nv'] );
			self::ra( VHCC_Lich::xin_doi_lich( $u, array(
				'coso'          => $hop,
				'ma_nv'         => $u['ma_nv'],          // ⚠️ từ PHIÊN, không từ biểu mẫu
				'ho_ten'        => $hs && isset( $hs['ho_ten'] ) ? (string) $hs['ho_ten'] : (string) $u['ho_ten'],
				'ngay'          => isset( $b['ngay'] ) ? (string) $b['ngay'] : '',
				'ca'            => isset( $b['ca'] ) ? (string) $b['ca'] : '',
				'viec_moi'      => isset( $b['viecMoi'] ) ? (string) $b['viecMoi'] : '',
				'doi_sang_ngay' => isset( $b['doiSangNgay'] ) ? (string) $b['doiSangNgay'] : '',
				'ly_do'         => isset( $b['lyDo'] ) ? (string) $b['lyDo'] : '',
			) ) );
		}

		/**
		 * MÀN XIN PHÉP CẦN GÌ ĐỂ DỰNG, VÀ ĐƠN CŨ CỦA CHÍNH NGƯỜI NÀY.
		 *
		 * 🔴 TRẢ VỀ CẢ ĐƠN ĐÃ NỘP, KHÔNG CHỈ Ô ĐỂ NỘP. Nộp xong mà màn không hiện đơn nào thì
		 *    người ta không phân biệt được "đã gửi" với "bấm hụt" — và cách duy nhất họ có để
		 *    chắc chắn là nộp lại. Đơn đi trễ nộp lại thì ĐÈ lên đơn cũ và quay về chờ duyệt
		 *    (đúng luật của VHCC_XinTre), nên đơn vừa được duyệt có thể bị chính người xin huỷ
		 *    mất — chỉ vì màn không nói cho họ biết đơn đang nằm đó.
		 */
		if ( 'donxin' === $viec ) {
			$cs_mac = VHCC_NhanSu::chuan_coso( isset( $u['coso'] ) ? $u['coso'] : '' );
			$ds_cs  = VHCC_Online::ds_coso_cham_cua_nv( $u['ma_nv'], isset( $u['coso'] ) ? $u['coso'] : '' );
			$bat    = array();
			foreach ( $ds_cs as $x ) { if ( VHCC_Lich::co_bat_lich( $x ) ) { $bat[] = $x; } }
			$cf = array(
				'ca'       => (array) VHCC_Luong::cai_dat( 'LICH_CA', array( 'Sáng', 'Chiều', 'Tối' ) ),
				'loaiViec' => (array) VHCC_Luong::cai_dat( 'LICH_LOAI_VIEC', array() ),
			);
			self::ra( array(
				'ok'          => true,
				'phutToiDa'   => VHCC_Tre::TOI_DA,
				'truocToiDa'  => VHCC_XinTre::TRUOC_TOI_DA,
				'muonToiDa'   => VHCC_XinTre::MUON_TOI_DA,
				'homNay'      => current_time( 'Y-m-d' ),
				'coSoMacDinh' => $cs_mac,
				'dsCoSo'      => $ds_cs,
				'coSoBatLich' => $bat,
				'ca'          => $cf['ca'],
				'loaiViec'    => $cf['loaiViec'],
				'donTre'      => VHCC_XinTre::cua_nguoi( $u['ma_nv'], 12 ),
				'donLich'     => VHCC_Lich::cua_nguoi( $u['ma_nv'], 12 ),
				'donNghi'     => VHCC_XinNghi::cua_nguoi( $u['ma_nv'], 12 ),
				'loaiNghi'    => VHCC_XinNghi::TEN_LOAI,
				'quyPhep'     => VHCC_XinNghi::quy_phep( $u['ma_nv'] ),
			) );
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

		/* 🔴 CHỐT "AI VÀO ĐƯỢC TRANG NÀO" — khai ở trang Quản lý nhân sự (`VHCC_TrangNS`).
		   Gác Ở ĐÂY chứ không ở `maybe_render()`: trạm là một trang JavaScript, người mở nó ra
		   CHƯA có phiên nào — gác ở cửa trang thì chối cả người chưa kịp gõ PIN. Đúng chỗ chối
		   là lúc PHÁT THẺ: PIN đúng, biết là ai rồi, mới nói được "người này bị khoá".
		   ⚠️ Gác `method_exists` cùng hàm với lời gọi (luật `tools/test/kiem-goi-cheo.php`). */
		if ( class_exists( 'VHCC_Cong' ) && method_exists( 'VHCC_Cong', 'duoc_vao' ) ) {
			$ai = array( 'ma_nv' => $r['ma_nv'], 'role' => $vai );
			if ( ! VHCC_Cong::duoc_vao( $ai, 'tram' ) ) {
				/* KHÔNG đếm lượt sai: PIN gõ đúng thì không phải là dò mật khẩu. */
				return array( 'ok' => false, 'error' => VHCC_Cong::vi_sao_khong( $ai, 'tram' ) );
			}
		}

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
	 * ĐANG CÓ PHIÊN Ở TRANG QUẢN TRỊ THÌ VÀO THẲNG, KHÔNG GÕ PIN LẦN HAI.
	 *
	 * =========================================================================================
	 * 🔴 CÁI RỜI NHAU LÀ CHỖ CẤT THẺ, KHÔNG PHẢI CÁI THẺ.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026: *"nhân viên đăng nhập bên quản trị chấm công, nhưng qua chấm công
	 * đăng nhập online lại bắt đăng nhập lại, tự vào chung luôn"*.
	 *
	 * Thẻ đã dùng chung từ 25/08 — cùng `VHCC_Auth::phat_token()`, cùng bảng phiên, mang vai
	 * thật. Nhưng trang quản trị cất thẻ ở COOKIE HttpOnly, còn trạm cất ở localStorage, mà
	 * cookie HttpOnly thì JavaScript của trạm không đọc được. Nên trạm mở ra là thấy trống, và
	 * hỏi PIN — trong khi cookie ngay bên cạnh đang giữ một phiên còn hạn.
	 *
	 * Việc này là đường đọc ngược: máy chủ đọc cookie giúp, rồi trao lại CHÍNH thẻ ấy.
	 *
	 * ⚠️ TRAO LẠI CHÍNH THẺ, KHÔNG PHÁT THẺ MỚI. Phát thẻ mới thì cùng một người có hai thẻ
	 *    sống song song: bấm "Thoát" ở trạm chỉ giết một cái, cookie vẫn mở — người ta tưởng đã
	 *    thoát mà máy vẫn đang đăng nhập. Một lần đăng nhập thì phải là một cái thẻ, để một lần
	 *    thoát là thoát hẳn. Đổi lại, thẻ HttpOnly chảy sang localStorage của trạm — đúng mức
	 *    phơi ra mà chiều ngược lại (`vao` gọi `VHCC_Web::mo_phien`) vốn đã chấp nhận.
	 *
	 * ⚠️ CHỐT CỬA TRẠM VẪN PHẢI CHẠY. Có cookie không có nghĩa là được vào trạm: người bị khoá
	 *    ở màn "Ai vào được trang nào" phải bị chối ở đây y như lúc gõ PIN. Bỏ khúc này là mở
	 *    một cửa sau đi vòng qua đúng cái màn sinh ra để đóng nó.
	 *
	 * ⚠️ THẺ KHÔNG CÓ MÃ NHÂN VIÊN THÌ CHỐI. Lượt chấm công ghi theo mã NV; thẻ cũ phát từ
	 *    trước khi có cột ấy mà cho vào thì người ta chấm được mà công không vào hồ sơ ai cả.
	 */
	public static function phien_tu_cookie() {
		$chua = array( 'ok' => false, 'ma' => 'chua_co', 'error' => 'Chưa có phiên sẵn.' );
		/* ⚠️ Gác `method_exists` cùng hàm với lời gọi (luật `tools/test/kiem-goi-cheo.php`). */
		if ( ! class_exists( 'VHCC_Web' ) || ! method_exists( 'VHCC_Web', 'the_phien' ) ) {
			return $chua;
		}
		$tok = (string) VHCC_Web::the_phien();
		if ( '' === $tok ) { return $chua; }
		$u = VHCC_Auth::user_by_token( $tok );
		if ( ! $u ) { return $chua; }

		$ma_nv = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		if ( '' === $ma_nv ) { return $chua; }

		$vai = isset( $u['role'] ) ? (string) $u['role'] : '';
		if ( class_exists( 'VHCC_Cong' ) && method_exists( 'VHCC_Cong', 'duoc_vao' ) ) {
			$ai = array( 'ma_nv' => $ma_nv, 'role' => $vai );
			if ( ! VHCC_Cong::duoc_vao( $ai, 'tram' ) ) {
				return array( 'ok' => false, 'error' => VHCC_Cong::vi_sao_khong( $ai, 'tram' ) );
			}
		}

		return array(
			'ok'     => true,
			'hoTen'  => isset( $u['name'] ) ? (string) $u['name'] : '',
			'maNV'   => $ma_nv,
			'coSo'   => isset( $u['coso'] ) ? (string) $u['coso'] : '',
			'vaiTro' => VHCC_Vai::ten( $u ),
			'token'  => $tok,
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
