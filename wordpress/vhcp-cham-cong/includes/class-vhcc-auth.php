<?php
/**
 * CỔNG PIN — dùng chung tài khoản với plugin Vận hành chi phí.
 *
 * NGUỒN NGƯỜI DÙNG (quyết định một lần, hiện rõ trong Cài đặt, không tự đổi ngầm):
 *   'chung' — đọc bảng cấu hình của plugin Vận hành chi phí (`{prefix}vhcp_cfg`, hàng
 *             `CH_NguoiDung`). Thêm/sửa/xoá nhân sự vẫn làm ở tab ⚙️ Cấu hình bên đó, khai
 *             một lần dùng cho cả hai hệ thống.
 *   'rieng' — plugin này tự giữ danh sách trong option `vhcc_nguoidung` (dùng khi cài một
 *             mình trên site không có plugin chi phí).
 *
 * Chọn 'chung' mà bảng kia không có thì BÁO LỖI RÕ, không âm thầm rơi về 'rieng': đổi nguồn
 * danh tính trong im lặng là kiểu lỗi tệ nhất của một cổng đăng nhập.
 *
 * PHIÊN: token riêng của plugin này (bảng vhcc_session), KHÔNG dùng lại token của app chi phí —
 * hai hệ thống riêng thì thu hồi phiên bên này không được kéo bên kia xuống theo.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Auth {

	const TTL = 2592000;   // 30 ngày

	/**
	 * Vai trò vào được CỔNG.
	 *
	 * =========================================================================================
	 * 🔴 TỪ 25/08/2026: MỌI VAI ĐỀU VÀO ĐƯỢC. Cửa không còn nằm ở đây.
	 * =========================================================================================
	 * Bản cũ chặn ngay tại cổng: chỉ Admin / Quản lý / Kế toán. Hệ quả là nhân viên có PIN gõ
	 * vào thì nhận đúng câu *"Tài khoản … (Nhân viên) không được xem hệ thống chấm công"* — dù
	 * việc họ cần chỉ là chấm công của chính mình và xem công của chính mình, hai việc mà mô
	 * hình anh Thắng chốt giao cho MỌI người.
	 *
	 * Chặn ở cổng còn đẻ ra cái cửa thứ hai: trạm chấm công phải tự dựng đường đăng nhập riêng
	 * với một vai giả, và từ đó hai nửa hệ thống không nhận thẻ của nhau. Một người phải đăng
	 * nhập hai lần, ở hai trang, bằng cùng một PIN.
	 *
	 * Nay: **vào thì ai cũng vào, thấy gì thì do QUYỀN quyết định** (`VHCC_Vai::duoc()`). Một
	 * cửa, một bộ luật. Nhân viên vào và chỉ thấy hai màn của mình; kế toán vào thấy thêm lương.
	 *
	 * ⚠️ Đây KHÔNG phải nới quyền: không màn nào bỏ phép gác, chỉ đổi chỗ gác từ cổng vào sang
	 *    từng việc. Việc nào chưa khai quyền thì `duoc()` CHỐI, nên quên khai là bị chặn chứ
	 *    không phải lọt.
	 */
	const VAI_TRO_MAC_DINH = array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC',
		'Cửa hàng trưởng', 'Nhân viên' );

	/** Mọi vai trò có trong hệ thống — để Cài đặt vẽ ô tích. */
	const VAI_TRO_TAT_CA = array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC',
		'Cửa hàng trưởng', 'Nhân viên' );

	/**
	 * Vai trò của app gốc -> vai trò ở đây.
	 *
	 * App gốc dùng mã hoa (`ADMIN`, `CUA_HANG_TRUONG`…), plugin dùng tên tiếng Việt. Bản đồ để
	 * MỘT CHỖ, vì khai hai nơi thì thêm vai trò là lệch, và lệch phân quyền là lệch quyền xem
	 * bảng lương.
	 *
	 * ⚠️ `CUA_HANG_TRUONG` có vai trò riêng, KHÔNG gộp vào "Nhân viên". Gộp là mất khả năng cho
	 *    cửa hàng trưởng vào mà không mở cho toàn bộ nhân viên — đúng thứ anh Thắng cần cân nhắc
	 *    riêng. Mặc định cả hai đều KHÔNG vào được (xem VAI_TRO_MAC_DINH).
	 */
	const BAN_DO_VAI_TRO = array(
		'ADMIN'            => 'Admin',
		'QUAN_LY'          => 'Quản lý',
		'KE_TOAN'          => 'Kế toán cá nhân',
		'CUA_HANG_TRUONG'  => 'Cửa hàng trưởng',
		'NHAN_VIEN'        => 'Nhân viên',
	);

	/**
	 * Còn trả về danh sách để mã cũ không gãy, nhưng nay LUÔN là mọi vai — xem chú thích ở
	 * VAI_TRO_MAC_DINH. Tuỳ chọn `vhcc_vai_tro_vao` cũ CỐ Ý bị bỏ qua: nó chặn ở đúng chỗ
	 * không nên chặn, và để lại thì một ô tích quên bỏ sẽ khoá cửa cả công ty mà không ai
	 * ngờ tới nó.
	 */
	public static function vai_tro_vao() {
		/* 🔴 GỘP CẢ VAI TỰ TẠO. Thiếu dòng này là người mang vai "Kế toán POSH" gõ đúng PIN mà
		   bị chối ở cửa — `VHCC_Web::trang_dang_nhap` so vai với đúng danh sách này. Và câu chối
		   sẽ nói "vai trò không được vào hệ thống", tức là đổ cho vai trò trong khi lỗi nằm ở
		   chỗ danh sách quên mất mấy vai vừa khai. Đúng loại lỗi không ai lần ra.
		   ⚠️ Gác `method_exists` cùng hàm với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( class_exists( 'VHCC_Vai' ) && method_exists( 'VHCC_Vai', 'ds_ten' ) ) {
			return VHCC_Vai::ds_ten();
		}
		return self::VAI_TRO_TAT_CA;
	}

	/**
	 * Nguồn người dùng đang dùng: 'chung' | 'rieng' | 'app'.
	 *
	 * 'app' thêm 22/08/2026. Anh Thắng: *"mỗi nhân viên đều có pin hết, sao không đăng nhập
	 * được"* — đúng, ai cũng có PIN, nhưng PIN đó nằm ở sổ `PhanQuyen` của app gốc, còn cổng
	 * của plugin lại đọc một danh sách khác. Kéo sổ đó về rồi đọc thẳng nó thì ai đang đăng
	 * nhập được app gốc là đăng nhập được trang web bằng CHÍNH PIN đó, khỏi cấp lần hai.
	 */
	public static function nguon() {
		$n = get_option( 'vhcc_nguon_nguoidung' );
		return in_array( $n, array( 'rieng', 'app', 'ho_so' ), true ) ? $n : 'chung';
	}

	/** Bảng cấu hình của plugin Vận hành chi phí có tồn tại không? */
	public static function co_bang_chung() {
		global $wpdb;
		$t = $wpdb->prefix . 'vhcp_cfg';
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	}

	/**
	 * PIN sạch — CẮT ĐUÔI ".0" TRƯỚC KHI bỏ ký tự lạ.
	 *
	 * 🔴 Đây là lỗi đã làm KHÔNG AI đăng nhập được trang chấm công, dù nhìn màn Cài đặt vẫn
	 *    thấy "có PIN". Google Sheets coi PIN là SỐ, nên xuất ra `246810` thành `"246810.0"`.
	 *    Chuỗi đó dài 8 KÝ TỰ nhưng không khớp luật 4–8 CHỮ SỐ -> `login()` chối ngay từ dòng
	 *    đầu, và bảng ở màn Cài đặt in "8 ký tự — không dùng được".
	 *
	 *    App chi phí đã rửa chỗ này từ lâu (VHCP_Util::pin_sach). Cổng bên đây đọc THẲNG cột
	 *    JSON của bảng `vhcp_cfg` nên đi vòng qua phép rửa đó — hai nơi đọc cùng một dữ liệu,
	 *    một nơi rửa, một nơi không. Rửa ngay lúc ĐỌC ở cả ba nguồn, đừng bắt anh Thắng sửa
	 *    tay 21 dòng người dùng.
	 *
	 * ⚠️ THỨ TỰ HAI PHÉP LÀ QUAN TRỌNG. Bỏ ký tự lạ trước thì `"246810.0"` thành `"2468100"` —
	 *    bảy chữ số, vẫn khớp luật 4–8, nên KHÔNG báo lỗi ở đâu cả, chỉ là không ai gõ trúng.
	 *    Sai âm thầm còn tệ hơn sai ồn ào.
	 *
	 * Không đụng số 0 đứng đầu: `"0123"` giữ nguyên `"0123"`, vì đó là PIN thật của người ta.
	 */
	public static function pin_sach( $v ) {
		$s = trim( (string) $v );
		if ( '' === $s ) { return ''; }
		if ( preg_match( '/^(\d+)\.0*$/', $s, $m ) ) { $s = $m[1]; }
		return preg_replace( '/\D+/', '', $s );
	}

	/**
	 * Danh sách người dùng: [ ['ten','pin','vaiTro','coso'], … ]
	 *
	 * @return array|WP_Error
	 */
	public static function users() { return self::users_cua( self::nguon() ); }

	/**
	 * Đọc MỘT kho người dùng ĐÍCH DANH, không phụ thuộc nguồn đang chọn.
	 *
	 * Tách ra để màn Cài đặt soi được CẢ BA kho cùng lúc: "PIN của mọi người đang nằm ở đâu"
	 * là câu phải trả lời được TRƯỚC khi chọn nguồn, chứ không phải chọn xong mới biết kho đó
	 * trống. Và để nạp sổ PIN cũ sang danh sách riêng mà không phải lật nguồn qua lại.
	 */
	public static function users_cua( $nguon ) {
		global $wpdb;

		/* Nguồn 'ho_so': đọc THẲNG hồ sơ Nhân sự — cột `pin_dang_nhap` + `vai_tro`.
		   🔴 Vì sao có nguồn này: trước đó khai PIN trong hồ sơ xong VẪN không đăng nhập được,
		      phải nhớ bấm thêm "Nạp tài khoản" để chép sang một danh sách thứ hai. Anh Thắng
		      vấp đúng chỗ đó nhiều lần — *"vẫn chưa đăng nhập bằng pin"*. Hai bản danh sách cho
		      cùng một việc thì sớm muộn lệch nhau, và cái lệch đó im lặng. Đọc thẳng hồ sơ thì
		      sửa ở đâu là có hiệu lực ở đó, không còn bước chép. */
		if ( 'ho_so' === $nguon ) {
			$bang_hs = VHCC_DB::t( 'nhan_vien' );
			$ra_hs   = array();
			foreach ( VHCC_DB::rows( "SELECT ho_ten, pin_dang_nhap, vai_tro, chuc_vu, cua_hang"
				. " FROM $bang_hs WHERE pin_dang_nhap <> ''" ) as $r ) {
				$ten_hs = trim( (string) $r['ho_ten'] );
				if ( '' === $ten_hs ) { continue; }
				/* Vai trò lạ / chưa khai -> 'Nhân viên', bậc THẤP NHẤT. KHÔNG đoán lên cao: đoán
				   nhầm lên Admin là mở toàn bộ bảng lương cho một dòng gõ sai chính tả. */
				$vt_hs = VHCC_NguoiDung::vai_tro_biet( $r['vai_tro'] );
				$ra_hs[] = array(
					'ten'    => $ten_hs,
					'pin'    => self::pin_sach( $r['pin_dang_nhap'] ),
					'vaiTro' => '' !== $vt_hs ? $vt_hs : 'Nhân viên',
					'coso'   => trim( (string) $r['cua_hang'] ),
				);
			}
			return $ra_hs;
		}

		/* Nguồn 'app': đọc thẳng bảng `phan_quyen` — bản sao sổ PhanQuyen của app gốc, kéo về
		   bằng nút ở màn Phân quyền & PIN. */
		if ( 'app' === $nguon ) {
			$bang_pq = VHCC_DB::t( 'phan_quyen' );
			$ra_pq   = array();
			foreach ( VHCC_DB::rows( "SELECT pin, ho_ten, vai_tro, cua_hang FROM $bang_pq" ) as $r ) {
				$pin_pq = trim( (string) $r['pin'] );
				if ( '' === $pin_pq ) { continue; }
				$vt_pq = strtoupper( trim( (string) $r['vai_tro'] ) );
				$ra_pq[] = array(
					'ten'    => trim( (string) $r['ho_ten'] ),
					'pin'    => self::pin_sach( $pin_pq ),
					/* Vai trò lạ -> 'Nhân viên' (bậc thấp nhất). KHÔNG đoán lên cao: đoán nhầm
					   lên Admin là mở toàn bộ bảng lương cho một dòng gõ sai chính tả. */
					'vaiTro' => isset( self::BAN_DO_VAI_TRO[ $vt_pq ] ) ? self::BAN_DO_VAI_TRO[ $vt_pq ] : 'Nhân viên',
					'coso'   => trim( (string) $r['cua_hang'] ),
				);
			}
			return $ra_pq;
		}

		if ( 'rieng' === $nguon ) {
			$ds  = get_option( 'vhcc_nguoidung' );
			$out = array();
			foreach ( (array) $ds as $u ) {
				$u = (array) $u;
				if ( trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) ) === '' ) { continue; }
				$out[] = array(
					'ten'    => (string) $u['ten'],
					'pin'    => self::pin_sach( isset( $u['pin'] ) ? $u['pin'] : '' ),
					'vaiTro' => (string) ( isset( $u['vaiTro'] ) ? $u['vaiTro'] : 'Kế toán cá nhân' ),
					'coso'   => (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ),
				);
			}
			return $out;
		}

		if ( ! self::co_bang_chung() ) {
			return new WP_Error( 'thieu_bang', 'Đang đặt nguồn người dùng là "dùng chung với Vận hành chi phí" '
				. 'nhưng không thấy bảng ' . $wpdb->prefix . 'vhcp_cfg. Vào Cài đặt → chuyển sang '
				. '"danh sách riêng của plugin này", hoặc cài lại plugin Vận hành chi phí.' );
		}

		// Bảng cfg lưu mỗi hàng sheet thành 1 dòng JSON: [Tên, PIN, Vai trò, Cơ sở, …]
		$t    = $wpdb->prefix . 'vhcp_cfg';
		$rows = VHCC_DB::rows( $wpdb->prepare(
			"SELECT cols FROM $t WHERE bang=%s ORDER BY stt ASC, id ASC", 'CH_NguoiDung'
		) );
		$out = array();
		foreach ( $rows as $r ) {
			$a = json_decode( (string) $r['cols'], true );
			if ( ! is_array( $a ) ) { continue; }
			$ten = isset( $a[0] ) ? trim( (string) $a[0] ) : '';
			if ( $ten === '' ) { continue; }
			$out[] = array(
				'ten'    => $ten,
				'pin'    => isset( $a[1] ) ? self::pin_sach( $a[1] ) : '',
				'vaiTro' => isset( $a[2] ) ? trim( (string) $a[2] ) : '',
				'coso'   => isset( $a[3] ) ? trim( (string) $a[3] ) : '',
			);
		}
		return $out;
	}

	/**
	 * ĐỐI CHIẾU SỔ ĐANG DÙNG VỚI HỒ SƠ NHÂN SỰ — ai sẽ lệch nếu chuyển nguồn sang hồ sơ.
	 *
	 * =============================================================================================
	 * Anh Thắng 27/08/2026: *"đồng bộ phần chấm công nhân sự trước, người nào sai đưa ra cảnh báo
	 * anh chỉnh lại quyền"*.
	 * =============================================================================================
	 * 🔴 SOI TRƯỚC, ĐỔI SAU. Chuyển nguồn người dùng là đổi CẢ CUỐN SỔ mà cổng PIN đang tra. Bấm
	 * một cái là 240 người đổi đường vào cùng lúc: ai có PIN ở sổ cũ mà hồ sơ chưa khai PIN sẽ
	 * đứng ngoài ngay lượt đăng nhập sau, và họ không biết vì sao — màn hình chỉ nói "PIN không
	 * đúng". Người bị mất đường vào KHÔNG tự báo được, vì cái họ mất chính là đường để báo.
	 *
	 * Nên hàm này chạy TRƯỚC, chỉ đọc, không ghi một dòng nào, và trả về đúng những người lệch.
	 *
	 * ⚠️ SO THEO PIN, KHÔNG SO THEO TÊN. PIN mới là thứ quyết định ai đăng nhập được; tên thì hai
	 *    sổ gõ hai kiểu ("Nguyễn Văn A" / "NGUYEN VAN A") nên so tên là ra một rừng lệch giả.
	 *    Tên vẫn được soi, nhưng chỉ để CẢNH BÁO khi cùng PIN mà khác tên — đó mới là chuyện lạ.
	 *
	 * @return array{nguon:string, so_cu:int, so_moi:int, muc:array, nang:int}
	 */
	public static function doi_chieu_ho_so() {
		global $wpdb;
		$nguon = self::nguon();
		$muc   = array();

		$cu  = self::users_cua( $nguon );
		$moi = self::users_cua( 'ho_so' );
		if ( is_wp_error( $cu ) )  { $cu  = array(); }
		if ( is_wp_error( $moi ) ) { $moi = array(); }

		/* Gom hồ sơ theo PIN. Một PIN có thể ứng với NHIỀU hồ sơ — đó chính là chỗ hỏng nặng
		   nhất, nên đừng gom kiểu ghi đè mà phải giữ cả cụm. */
		$theo_pin = array();
		foreach ( $moi as $u ) {
			$p = self::pin_sach( isset( $u['pin'] ) ? $u['pin'] : '' );
			if ( '' === $p ) { continue; }
			$theo_pin[ $p ][] = $u;
		}

		/* ---- NẶNG 1: hai hồ sơ dùng CHUNG một PIN ----
		   Ai gõ PIN ấy sẽ đăng nhập thành người mà vòng lặp tìm thấy TRƯỚC — tức là tuỳ thứ tự
		   dòng trong bảng. Chấm công, sửa hồ sơ, duyệt chi phí đều ghi tên người kia. */
		foreach ( $theo_pin as $p => $ds ) {
			if ( count( $ds ) < 2 ) { continue; }
			$ten = array();
			foreach ( $ds as $u ) { $ten[] = trim( (string) $u['ten'] ); }
			$muc[] = array(
				'loai' => 'pin_trung', 'nang' => true,
				'ten'  => implode( ' + ', $ten ),
				'noi'  => count( $ds ) . ' hồ sơ đang dùng CHUNG một mã PIN. Ai gõ PIN đó sẽ đăng '
					. 'nhập thành một trong số họ — tuỳ thứ tự dòng trong bảng, không đoán được. '
					. 'Đổi PIN cho tất cả trừ một người.',
			);
		}

		/* ---- NẶNG 2: hồ sơ có PIN nhưng THIẾU Mã NV ----
		   Họ vào được trang, nhưng nút chấm công báo "hồ sơ chưa có Mã NV" và mọi ngoại lệ quyền
		   khai theo mã đều không bám vào đâu. Vào được mà không làm được gì. */
		$t_hs = VHCC_DB::t( 'nhan_vien' );
		if ( VHCC_DB::co_bang( $t_hs ) ) {
			$thieu = VHCC_DB::rows( "SELECT ho_ten, pin_dang_nhap FROM $t_hs"
				. " WHERE pin_dang_nhap <> '' AND ( ma_nv IS NULL OR TRIM(ma_nv) = '' )" );
			foreach ( (array) $thieu as $r ) {
				$muc[] = array(
					'loai' => 'thieu_ma', 'nang' => true,
					'ten'  => trim( (string) $r['ho_ten'] ),
					'noi'  => 'Hồ sơ có PIN nhưng CHƯA có Mã NV. Vào được trang, nhưng không chấm '
						. 'công được và không khai quyền riêng cho họ được.',
				);
			}
		}

		/* ---- So từng người ĐANG vào được với hồ sơ ---- */
		$so_cu = 0;
		foreach ( $cu as $u ) {
			$p = self::pin_sach( isset( $u['pin'] ) ? $u['pin'] : '' );
			if ( '' === $p ) { continue; }
			$so_cu++;
			$ten_cu = trim( (string) $u['ten'] );
			$vai_cu = trim( (string) $u['vaiTro'] );

			/* NẶNG 3: đang vào được, đổi nguồn là MẤT ĐƯỜNG VÀO. */
			if ( ! isset( $theo_pin[ $p ] ) ) {
				$muc[] = array(
					'loai' => 'mat_duong', 'nang' => true,
					'ten'  => '' !== $ten_cu ? $ten_cu : '(không tên)',
					'noi'  => 'Đang đăng nhập được, nhưng hồ sơ nhân sự CHƯA khai PIN này. Chuyển '
						. 'nguồn là họ đứng ngoài ngay — mà màn hình chỉ nói "PIN không đúng", nên '
						. 'họ không đoán ra vì sao. Khai PIN vào hồ sơ của họ trước.',
				);
				continue;
			}
			if ( count( $theo_pin[ $p ] ) > 1 ) { continue; }   // đã báo ở mục pin_trung
			$b = $theo_pin[ $p ][0];

			$vai_moi = trim( (string) $b['vaiTro'] );
			if ( VHCC_Vai::ma( $vai_cu ) !== VHCC_Vai::ma( $vai_moi ) ) {
				$muc[] = array(
					'loai' => 'vai_doi', 'nang' => false,
					'ten'  => '' !== $ten_cu ? $ten_cu : trim( (string) $b['ten'] ),
					'noi'  => 'Vai trò lệch giữa hai sổ: đang là ' . ( '' !== $vai_cu ? $vai_cu : '(trống)' )
						. ', hồ sơ ghi ' . ( '' !== $vai_moi ? $vai_moi : '(trống)' )
						. '. Chuyển nguồn là quyền của họ đổi theo hồ sơ.',
				);
			}
			$ten_moi = trim( (string) $b['ten'] );
			if ( '' !== $ten_cu && '' !== $ten_moi
				&& VHCC_NhanSu::khoa_so( $ten_cu ) !== VHCC_NhanSu::khoa_so( $ten_moi ) ) {
				$muc[] = array(
					'loai' => 'ten_doi', 'nang' => false,
					'ten'  => $ten_cu . ' ↔ ' . $ten_moi,
					'noi'  => 'Cùng một mã PIN mà hai sổ ghi hai TÊN khác nhau — nhiều khả năng đây '
						. 'là hai người, và một trong hai đang đăng nhập bằng PIN của người kia.',
				);
			}
		}

		/* ---- Người MỚI có đường vào sau khi đổi. Không phải lỗi, nhưng phải biết trước. ---- */
		$pin_cu = array();
		foreach ( $cu as $u ) {
			$p = self::pin_sach( isset( $u['pin'] ) ? $u['pin'] : '' );
			if ( '' !== $p ) { $pin_cu[ $p ] = true; }
		}
		$so_moi = 0;
		$moi_vao = 0;
		foreach ( $theo_pin as $p => $ds ) {
			$so_moi += count( $ds );
			if ( ! isset( $pin_cu[ $p ] ) ) { $moi_vao += count( $ds ); }
		}
		if ( $moi_vao > 0 ) {
			$muc[] = array(
				'loai' => 'moi_vao', 'nang' => false, 'ten' => $moi_vao . ' người',
				'noi'  => 'Có PIN trong hồ sơ nhưng hiện CHƯA đăng nhập được. Chuyển nguồn là họ vào '
					. 'được — theo đúng vai ghi trong hồ sơ. Soát lại vai của họ trước cho chắc.',
			);
		}

		$nang = 0;
		foreach ( $muc as $m ) { if ( $m['nang'] ) { $nang++; } }
		return array( 'nguon' => $nguon, 'so_cu' => $so_cu, 'so_moi' => $so_moi,
			'muc' => $muc, 'nang' => $nang );
	}

	public static function login( $pin ) {
		$pin = trim( (string) $pin );
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số' );
		}
		if ( self::bi_khoa() ) {
			return array( 'ok' => false, 'error' => 'Nhập sai quá nhiều lần — thử lại sau 10 phút' );
		}

		$users = self::users();
		if ( is_wp_error( $users ) ) {
			return array( 'ok' => false, 'error' => $users->get_error_message() );
		}

		foreach ( $users as $u ) {
			if ( $u['pin'] === '' || $u['pin'] !== $pin ) { continue; }
			self::xoa_dem_sai();
			$role = $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên';

			/* MÃ NV gắn ngay vào thẻ, để CÙNG một lần đăng nhập vừa quản trị vừa chấm công
			   được. Không có nó thì người ta phải gõ lại PIN ở trang thứ hai — và đó chính là
			   cái làm hệ thống tách đôi. Không tra ra mã thì để rỗng: họ vẫn vào xem được, chỉ
			   là nút chấm công báo "hồ sơ chưa có Mã NV". */
			$ma_nv = '';
			if ( class_exists( 'VHCC_Tram' ) ) {
				$tim = VHCC_Tram::tim_pin( $pin );
				if ( $tim['thay'] ) { $ma_nv = $tim['ma_nv']; }
			}
			return array(
				'ok'    => true,
				'name'  => $u['ten'],
				'role'  => $role,
				'coso'  => $u['coso'],
				'maNV'  => $ma_nv,
				'token' => self::phat_token( $u['ten'], $role, $u['coso'], $ma_nv ),
			);
		}

		self::dem_sai();
		return array( 'ok' => false, 'error' => 'PIN không đúng hoặc chưa được cấp' );
	}

	/**
	 * Phát một thẻ phiên.
	 *
	 * `$ma_nv` là mã nhân viên trong hồ sơ — thứ để biết lượt chấm công ghi cho ai. Gắn cho MỌI
	 * phiên, không riêng phiên của trạm: một thẻ dùng được ở cả trang quản trị lẫn trang chấm
	 * công là điều kiện để hệ thống chỉ có MỘT lần đăng nhập.
	 *
	 * ⚠️ Trước 25/08/2026 trạm phát thẻ mang vai giả 'CC_ONLINE' để hai bên không nhận thẻ của
	 *    nhau. Chốt ấy đã bỏ: nó bắt cùng một người gõ PIN hai lần ở hai trang, và là gốc của
	 *    xung đột phân quyền. Nay thẻ mang VAI THẬT, còn ai được làm gì thì `VHCC_Vai` quyết.
	 */
	public static function phat_token( $ten, $role, $coso, $ma_nv = '' ) {
		global $wpdb;
		$t = VHCC_DB::t( 'session' );
		$wpdb->query( "DELETE FROM $t WHERE het_han < UTC_TIMESTAMP()" );
		$tok = bin2hex( random_bytes( 32 ) );
		$wpdb->insert( $t, array(
			'token'   => $tok,
			'ten'     => (string) $ten,
			'vai_tro' => (string) $role,
			'coso'    => (string) $coso,
			'ma_nv'   => (string) $ma_nv,
			'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ),
		) );
		return $tok;
	}

	public static function user_by_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) { return null; }
		$t = VHCC_DB::t( 'session' );
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $t WHERE token=%s AND het_han > UTC_TIMESTAMP()", $token
		), ARRAY_A );
		if ( ! $r ) { return null; }
		return array( 'name' => $r['ten'], 'role' => $r['vai_tro'], 'coso' => $r['coso'],
			'ma_nv' => isset( $r['ma_nv'] ) ? (string) $r['ma_nv'] : '' );
	}

	public static function logout( $token ) {
		global $wpdb;
		$wpdb->delete( VHCC_DB::t( 'session' ), array( 'token' => (string) $token ) );
		return array( 'success' => true );
	}

	// ------------------------------------------------------------ hãm thử PIN
	private static function khoa_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhcc_fail_' . md5( $ip );
	}
	private static function bi_khoa()      { return (int) get_transient( self::khoa_key() ) >= 10; }
	private static function dem_sai()      { $k = self::khoa_key(); set_transient( $k, (int) get_transient( $k ) + 1, 600 ); }
	private static function xoa_dem_sai()  { delete_transient( self::khoa_key() ); }
	public static function mo_khoa()       { delete_transient( self::khoa_key() ); }
}
