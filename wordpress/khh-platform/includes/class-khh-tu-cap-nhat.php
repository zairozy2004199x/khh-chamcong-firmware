<?php
/**
 * TỰ CẬP NHẬT TỪ GITHUB RELEASES — hiện nút "Cập nhật" ngay ở màn Plugin.
 *
 * =================================================================================================
 * Anh Thắng 13/09/2026: *"cách kết nối github đẩy thẳng code wed lên"*.
 *
 * Trước đây mỗi bản mới đi bốn nhịp: có người dựng zip → gửi qua chat → anh tải về → vào wp-admin
 * tải lên. Từ nay GitHub tự dựng zip và treo ở Releases (xem `.github/workflows/phat-hanh.yml`),
 * còn lớp này để WordPress tự thấy bản mới và bày nút "Cập nhật ngay" như mọi plugin khác.
 *
 * =================================================================================================
 * 🔴 VÌ SAO TỰ VIẾT, KHÔNG DÙNG "GIT UPDATER" CÓ SẴN.
 *
 *   · MỘT REPO, CHÍN PLUGIN. Git Updater sinh ra cho một repo là một plugin; tài liệu của nó
 *     không nói gì về chuyện nhiều plugin nằm trong các thư mục con. Đọc release mới nhất của
 *     repo mà không lọc theo plugin thì bản chấm công hiện thành "bản mới" của chi phí.
 *   · REPO RIÊNG TƯ. Tài liệu của Git Updater hướng chuyện này sang bản trả phí.
 *
 *   Lớp này chỉ lọc tag theo TIỀN TỐ của chính plugin mình (`khh-platform-v…`), nên chín plugin
 *   sống chung một repo mà không ai nhầm bản của ai; và repo riêng tư thì dùng khoá truy cập.
 *
 * =================================================================================================
 * 🔴 KHOÁ TRUY CẬP LÀ BÍ MẬT — KHÔNG BAO GIỜ IN RA MÀN HÌNH.
 *
 *   Cùng luật đã đặt cho PIN và khoá máy chấm công: trang chạy ngoài internet, một ảnh chụp màn
 *   hình là mất. Ô nhập chỉ NÓI CÓ HAY KHÔNG và nhận khoá mới; không bao giờ đổ khoá đang lưu
 *   ra thuộc tính `value`. Muốn đổi thì dán khoá mới đè lên.
 *
 * ⚠️ KHOÁ CHỈ CẦN QUYỀN ĐỌC. Trên GitHub tạo "fine-grained token", chọn đúng repo này, mục
 *    Contents để "Read-only". Khoá ấy lỡ lộ cũng không ai ghi được gì vào mã.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHH_TuCapNhat {

	/** Repo giữ mã. Đổi chủ repo thì đổi đúng một dòng này. */
	const REPO = 'zairozy2004199x/khh-chamcong-firmware';

	/** Tiền tố tag của RIÊNG plugin này — xem khối dài ở đầu tệp. */
	const TIEN_TO = 'khh-platform-v';

	/** Khoá truy cập GitHub (chỉ cần quyền đọc). Dùng chung cho mọi plugin trên cùng site. */
	const O_KHOA = 'vhcp_gh_token';

	/** Nhớ kết quả hỏi GitHub trong 6 giờ — đừng gọi mạng ở mỗi lượt tải trang admin. */
	const O_NHO   = 'khh_gh_ban_moi';
	const NHO_LAU = 21600;

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'chen_ban_moi' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'chi_tiet' ), 10, 3 );
		/* 🔴 ĐỔI TÊN THƯ MỤC SAU KHI GIẢI NÉN. Tệp .zip của Releases giải ra thành thư mục
		   `vhcp-chi-phi/` — đúng tên plugin đang cài, nên WordPress đặt lại đúng chỗ. Nhưng nếu
		   một bản zip nào đó mang tên khác (tải tay từ giao diện GitHub chẳng hạn) thì plugin
		   bị cài thành một bản SONG SONG, và trang chạy bản cũ trong khi anh tưởng đã cập nhật. */
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'sua_ten_thu_muc' ), 10, 4 );
		/* Gắn khoá vào TIÊU ĐỀ cho lượt tải gói — xem `goi_tai()`
		   để biết vì sao không được nhét vào địa chỉ. */
		add_filter( 'http_request_args', array( __CLASS__, 'them_khoa_tai' ), 10, 2 );
		/* Tự khai vào bảng của trang IT — xem khối dài ở `khai_ds()`. */
		add_filter( 'vhcp_tu_cap_nhat_ds', array( __CLASS__, 'khai_ds' ) );
	}

	/* ─────────────────────────────────────────────────────────────────────────────────────────
	 * KHAI TÊN VÀO BẢNG CHUNG CỦA TRANG IT (16/09/2026).
	 *
	 * Anh Thắng 15/09/2026: *"1 trang tổng do IT quản lý như khmatrix.com/it"*, và *"sau các
	 * trang khác tạo tự cập nhật thì link vào"*. Trang ấy nằm trong plugin Ghế nhưng KHÔNG giữ
	 * danh sách plugin nào cả — nó dựng bảng từ bộ lọc `vhcp_tu_cap_nhat_ds` lúc chạy. Nên plugin
	 * nào khai một dòng như dưới là TỰ hiện thêm vào bảng, không ai phải đi sửa trang ấy.
	 *
	 * ⚠️ Trang IT gọi `ban_moi_nho()` chứ không gọi `ban_moi()`. Khác nhau một trời: `ban_moi()`
	 *    hỏi thẳng GitHub và chờ tới 15 giây — nhét nó vào một lượt tải trang bình thường là
	 *    treo cả màn hình khi mạng chậm, đúng loại lỗi người ta đổ cho "web lag" chứ không ai ngờ
	 *    tới bộ cập nhật. `ban_moi_nho()` CHỈ đọc thứ đã nhớ. WordPress tự chạy lượt soát định kỳ
	 *    (qua `pre_set_site_transient_update_plugins` bên trên) nên ô nhớ gần như luôn có sẵn;
	 *    chưa có thì bảng chỉ đơn giản không khoe gì. Nút "Kiểm tra bản mới" mới gọi `ban_moi()`.
	 * ───────────────────────────────────────────────────────────────────────────────────────── */

	/** Số bản đang chạy — đọc từ chính hằng của plugin, không chép thành một con số thứ hai. */
	private static function ban_hien() {
		return defined( 'KHH_VERSION' ) ? (string) KHH_VERSION : '0';
	}

	/** 🔴 CHỈ ĐỌC THỨ ĐÃ NHỚ — TUYỆT ĐỐI KHÔNG GỌI MẠNG. Xem khối trên. */
	public static function ban_moi_nho() {
		$nho = get_transient( self::O_NHO );
		return is_array( $nho ) && $nho ? $nho : null;
	}

	/** Quên ô nhớ để lượt hỏi tới gọi lại GitHub ngay — nút "Kiểm tra bản mới" của trang IT. */
	public static function quen_nho() {
		delete_transient( self::O_NHO );
	}

	/** Khai tên mình vào danh sách plugin tự cập nhật được. */
	public static function khai_ds( $ds ) {
		if ( ! is_array( $ds ) ) { $ds = array(); }
		$ds[] = array(
			'ma'    => 'khh-platform',
			'ten'   => 'Nền tảng K&H',
			'duong' => self::duong(),
			'hien'  => (string) self::ban_hien(),
			'lop'   => __CLASS__,
		);
		return $ds;
	}

	/** Đã khai khoá chưa — CHỈ trả lời có/không, không bao giờ trả về chính khoá. */
	public static function co_khoa() {
		return '' !== trim( (string) get_option( self::O_KHOA, '' ) );
	}

	public static function dat_khoa( $khoa ) {
		$khoa = trim( (string) $khoa );
		if ( '' === $khoa ) { return; }   // ô để trống = GIỮ NGUYÊN, không phải xoá
		update_option( self::O_KHOA, $khoa );
		delete_option( self::O_NHO );     // khoá mới thì hỏi lại ngay, đừng chờ hết 6 giờ
	}

	public static function xoa_khoa() {
		delete_option( self::O_KHOA );
		delete_option( self::O_NHO );
	}

	/** Đường dẫn plugin dạng `vhcp-chi-phi/vhcp-chi-phi.php` — khoá WordPress dùng để nhận plugin. */
	private static function duong() {
		return plugin_basename( KHH_DIR . 'khh-platform.php' );
	}

	/**
	 * HỎI GITHUB: có bản nào mới hơn bản đang chạy không.
	 *
	 * @return array|null [ 'ver'=>…, 'zip'=>…, 'ghi_chu'=>…, 'ngay'=>… ] hoặc null.
	 */
	public static function ban_moi( $bo_qua_nho = false ) {
		if ( ! $bo_qua_nho ) {
			$nho = get_transient( self::O_NHO );
			if ( is_array( $nho ) ) { return $nho ? $nho : null; }
			if ( 'khong' === $nho ) { return null; }
		}

		$dau = array( 'headers' => array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'khh-platform-tu-cap-nhat',
		), 'timeout' => 15 );
		$khoa = trim( (string) get_option( self::O_KHOA, '' ) );
		if ( '' !== $khoa ) { $dau['headers']['Authorization'] = 'Bearer ' . $khoa; }

		$r = wp_remote_get( 'https://api.github.com/repos/' . self::REPO . '/releases?per_page=60', $dau );
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			/* ⚠️ HỎNG THÌ IM LẶNG VÀ THỬ LẠI SAU, đừng bày lỗi lên màn Plugin. Mạng chập, khoá
			   hết hạn, GitHub quá tải — không cái nào đáng làm hỏng màn quản trị của cả trang.
			   Muốn biết vì sao thì có nút "Kiểm tra ngay" ở trang Cài đặt, nó nói thẳng. */
			set_transient( self::O_NHO, 'khong', 900 );   // 15 phút rồi thử lại
			return null;
		}
		$ds = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( ! is_array( $ds ) ) { set_transient( self::O_NHO, 'khong', 900 ); return null; }

		$hien  = defined( 'KHH_VERSION' ) ? KHH_VERSION : '0';
		$tot   = null;
		foreach ( $ds as $rel ) {
			if ( ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) ) { continue; }
			$tag = isset( $rel['tag_name'] ) ? (string) $rel['tag_name'] : '';
			/* 🔴 LỌC THEO TIỀN TỐ CỦA CHÍNH PLUGIN NÀY. Không có bước này thì bản chấm công
			   `vhcp-cham-cong-v3.89.0` hiện ra như bản mới của nền tảng. */
			if ( 0 !== strpos( $tag, self::TIEN_TO ) ) { continue; }
			$ver = substr( $tag, strlen( self::TIEN_TO ) );
			/* 🔴 LỚP THỨ HAI: SỐ PHIÊN BẢN PHẢI RA HÌNH MỘT SỐ PHIÊN BẢN.
			   Phép lọc tiền tố ngay trên đã gạt bản của plugin khác, nhưng nó là MỘT dòng — gỡ
			   một dòng là chín plugin lẫn vào nhau. Dòng này đứng sau làm lớp đỡ: cắt tiền tố
			   khỏi tag của plugin khác thì ra một mẩu chuỗi vụn ("-v3.76.0", "ong-v9.9.0"),
			   không bao giờ khớp dạng số — nên dù lớp trên bị gỡ, bản của người khác vẫn không
			   lọt vào được. */
			if ( ! preg_match( '/^\d+(\.\d+)*$/', $ver ) ) { continue; }
			if ( version_compare( $ver, $hien, '<=' ) ) { continue; }
			if ( $tot && version_compare( $ver, $tot['ver'], '<=' ) ) { continue; }

			/* Tệp .zip đính kèm là bản CÀI ĐƯỢC (đã bỏ `goc/`, đúng cấu trúc thư mục). Mã nguồn
			   tự đóng của GitHub thì KHÔNG dùng được: nó gói cả repo, chín plugin cùng lúc. */
			$zip = '';
			foreach ( (array) ( isset( $rel['assets'] ) ? $rel['assets'] : array() ) as $a ) {
				if ( isset( $a['name'] ) && substr( (string) $a['name'], -4 ) === '.zip' ) {
					$zip = (string) $a['url'];   // url API, tải được cả với repo riêng tư
					break;
				}
			}
			if ( '' === $zip ) { continue; }
			$tot = array(
				'ver'     => $ver,
				'zip'     => $zip,
				'ghi_chu' => isset( $rel['body'] ) ? (string) $rel['body'] : '',
				'ngay'    => isset( $rel['published_at'] ) ? substr( (string) $rel['published_at'], 0, 10 ) : '',
			);
		}
		set_transient( self::O_NHO, $tot ? $tot : array(), self::NHO_LAU );
		return $tot;
	}

	/** Chèn bản mới vào danh sách cập nhật của WordPress. */
	public static function chen_ban_moi( $tr ) {
		if ( ! is_object( $tr ) ) { return $tr; }
		$moi = self::ban_moi();
		if ( ! $moi ) { return $tr; }
		$duong = self::duong();
		$o = new stdClass();
		$o->slug        = dirname( $duong );
		$o->plugin      = $duong;
		$o->new_version = $moi['ver'];
		$o->url         = 'https://github.com/' . self::REPO;
		$o->package     = self::goi_tai( $moi['zip'] );
		if ( ! isset( $tr->response ) || ! is_array( $tr->response ) ) { $tr->response = array(); }
		$tr->response[ $duong ] = $o;
		return $tr;
	}

	/**
	 * ĐƯỜNG TẢI GÓI — ĐỊA CHỈ TRẦN, KHOÁ ĐI Ở TIÊU ĐỀ.
	 *
	 * =========================================================================================
	 * 🔴 BẢN TRƯỚC NHÉT KHOÁ VÀO ĐỊA CHỈ (`https://<khoá>@api.github.com/…`) — HỎNG CẢ HAI ĐẦU.
	 * =========================================================================================
	 * Anh Thắng 16/09/2026 gửi ảnh hộp thoại trên trang: *"Download failed. Địa chỉ URL không
	 * hợp lệ"* — và trong chính câu báo lỗi ấy là NGUYÊN CÁI KHOÁ, in ra màn hình rồi đi vào ảnh
	 * chụp. Khoá coi như lộ, phải thu hồi.
	 *
	 * Hai lỗi cùng một nguyên nhân:
	 *   1. KHÔNG TẢI ĐƯỢC. `wp_http_validate_url()` CHỐI mọi địa chỉ có phần "người dùng"
	 *      (`user@host`) — chốt chống SSRF của WordPress, có từ lâu và sẽ không bỏ. Nên đường
	 *      tải ấy chưa bao giờ chạy được, chỉ là tới hôm nay mới có người bấm.
	 *   2. LỘ KHOÁ. Chú thích cũ dặn *"không log nó, không in nó ra màn hình"* — nhưng người in
	 *      ra không phải mã của mình: WordPress dán nguyên địa chỉ hỏng vào câu báo lỗi. Dặn dò
	 *      không chặn được điều đó; chỉ KHÔNG ĐỂ BÍ MẬT TRONG ĐỊA CHỈ mới chặn được.
	 *
	 * 🔴 CÁCH ĐÚNG: địa chỉ để trần, khoá gắn vào tiêu đề `Authorization` qua bộ lọc
	 *    `http_request_args` — WordPress chạy bộ lọc ấy cho MỌI lượt gọi HTTP, kể cả lượt tải
	 *    gói của bộ nâng cấp. Tiêu đề không nằm trong địa chỉ nên không lọt vào câu báo lỗi nào.
	 *
	 * ⚠️ Kho này CÔNG KHAI (CLAUDE.md §4) nên tải gói vốn KHÔNG CẦN khoá; khoá chỉ để nới trần
	 *    số lượt gọi API. Chưa khai khoá thì mọi thứ vẫn chạy y nguyên.
	 */
	private static function goi_tai( $zip ) {
		return $zip;
	}

	/**
	 * Gắn khoá + `Accept` cho đúng lượt gọi tới gói của kho này.
	 *
	 * ⚠️ `Accept: application/octet-stream` LÀ BẮT BUỘC với địa chỉ API của tệp đính kèm. Thiếu
	 *    nó thì GitHub trả về JSON MÔ TẢ tệp chứ không phải tệp — WordPress lưu cục JSON ấy
	 *    thành .zip rồi báo "gói không hợp lệ", một câu chẳng chỉ về đâu cả.
	 *
	 * ⚠️ CHỈ gắn cho địa chỉ của CHÍNH kho này. Gắn cho mọi lượt gọi là gửi khoá GitHub tới bất
	 *    cứ máy chủ nào plugin khác trên site gọi tới.
	 */
	public static function them_khoa_tai( $args, $url ) {
		$goc = 'https://api.github.com/repos/' . self::REPO . '/releases/assets/';
		if ( 0 !== strpos( (string) $url, $goc ) ) { return $args; }
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) { $args['headers'] = array(); }
		$args['headers']['Accept'] = 'application/octet-stream';
		$khoa = trim( (string) get_option( self::O_KHOA, '' ) );
		if ( '' !== $khoa ) { $args['headers']['Authorization'] = 'Bearer ' . $khoa; }
		return $args;
	}

	/** Nội dung hộp "Xem chi tiết" của plugin. */
	public static function chi_tiet( $ket_qua, $viec, $args ) {
		if ( 'plugin_information' !== $viec ) { return $ket_qua; }
		if ( ! isset( $args->slug ) || $args->slug !== dirname( self::duong() ) ) { return $ket_qua; }
		$moi = self::ban_moi();
		if ( ! $moi ) { return $ket_qua; }
		$o = new stdClass();
		$o->name          = 'Nền tảng K&H';
		$o->slug          = $args->slug;
		$o->version       = $moi['ver'];
		$o->last_updated  = $moi['ngay'];
		$o->download_link = self::goi_tai( $moi['zip'] );
		$o->sections      = array( 'changelog' => wpautop( esc_html( $moi['ghi_chu'] ) ) );
		return $o;
	}

	/**
	 * Thư mục vừa giải nén phải mang ĐÚNG tên plugin đang cài.
	 *
	 * Gói do `tools/build-plugin-zip.sh` dựng vốn đã đúng tên, nên hàm này gần như không phải
	 * làm gì. Nó ở đây cho ca người ta tải tay một gói tên khác rồi cài đè: không đổi tên thì
	 * WordPress đặt nó thành plugin SONG SONG, trang vẫn chạy bản cũ trong khi màn hình báo
	 * "đã cập nhật" — kiểu hỏng im lặng khó lần nhất.
	 */
	public static function sua_ten_thu_muc( $nguon, $nguon_xa, $nang_cap, $dau = array() ) {
		global $wp_filesystem;
		if ( ! isset( $dau['plugin'] ) || $dau['plugin'] !== self::duong() ) { return $nguon; }
		$can = trailingslashit( $nguon_xa ) . dirname( self::duong() );
		if ( trailingslashit( $nguon ) === trailingslashit( $can ) ) { return $nguon; }
		if ( $wp_filesystem && $wp_filesystem->move( $nguon, $can, true ) ) {
			return trailingslashit( $can );
		}
		return $nguon;
	}
}
