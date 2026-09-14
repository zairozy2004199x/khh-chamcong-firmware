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
 *   Lớp này chỉ lọc tag theo TIỀN TỐ của chính plugin mình (`vhcp-chi-phi-tong-v…`), nên chín plugin
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

class VHCPT_TuCapNhat {

	/** Repo giữ mã. Đổi chủ repo thì đổi đúng một dòng này. */
	const REPO = 'zairozy2004199x/khh-chamcong-firmware';

	/** Tiền tố tag của RIÊNG plugin này — xem khối dài ở đầu tệp. */
	const TIEN_TO = 'vhcp-chi-phi-tong-v';

	/** Khoá truy cập GitHub (chỉ cần quyền đọc). Dùng chung cho mọi plugin trên cùng site. */
	const O_KHOA = 'vhcpt_gh_token';

	/** Nhớ kết quả hỏi GitHub trong 6 giờ — đừng gọi mạng ở mỗi lượt tải trang admin. */
	const O_NHO   = 'vhcpt_gh_ban_moi';
	const NHO_LAU = 21600;

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'chen_ban_moi' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'chi_tiet' ), 10, 3 );
		/* 🔴 ĐỔI TÊN THƯ MỤC SAU KHI GIẢI NÉN. Tệp .zip của Releases giải ra thành thư mục
		   `vhcp-chi-phi-tong/` — đúng tên plugin đang cài, nên WordPress đặt lại đúng chỗ. Nhưng nếu
		   một bản zip nào đó mang tên khác (tải tay từ giao diện GitHub chẳng hạn) thì plugin
		   bị cài thành một bản SONG SONG, và trang chạy bản cũ trong khi anh tưởng đã cập nhật. */
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'sua_ten_thu_muc' ), 10, 4 );
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

	/** Đường dẫn plugin dạng `vhcp-chi-phi-tong/vhcp-chi-phi-tong.php` — khoá WordPress dùng để nhận plugin. */
	private static function duong() {
		return plugin_basename( VHCPT_DIR . 'vhcp-chi-phi-tong.php' );
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
			'User-Agent' => 'vhcp-tu-cap-nhat',
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

		$hien  = defined( 'VHCPT_VERSION' ) ? VHCPT_VERSION : '0';
		$tot   = null;
		foreach ( $ds as $rel ) {
			if ( ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) ) { continue; }
			$tag = isset( $rel['tag_name'] ) ? (string) $rel['tag_name'] : '';
			/* 🔴 LỌC THEO TIỀN TỐ CỦA CHÍNH PLUGIN NÀY. Không có bước này thì bản chấm công
			   `vhcp-cham-cong-v3.76.0` hiện ra như bản mới của chi phí. */
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
	 * ĐƯỜNG TẢI GÓI.
	 *
	 * 🔴 REPO RIÊNG TƯ THÌ ĐỊA CHỈ TẢI PHẢI MANG KHOÁ. WordPress tải gói bằng một lượt gọi
	 *    thẳng, không đi qua bộ lọc nào của ta, nên khoá phải nằm sẵn trong địa chỉ.
	 *    GitHub nhận `?access_token=` đã bỏ từ lâu; cách còn dùng được là đặt khoá vào phần
	 *    "người dùng" của địa chỉ — `https://<khoá>@api.github.com/…`.
	 *
	 * ⚠️ VÌ VẬY ĐỊA CHỈ NÀY CHỨA BÍ MẬT: không log nó, không in nó ra màn hình.
	 */
	private static function goi_tai( $zip ) {
		$khoa = trim( (string) get_option( self::O_KHOA, '' ) );
		if ( '' === $khoa ) { return $zip; }
		return preg_replace( '#^https://#', 'https://' . rawurlencode( $khoa ) . '@', $zip );
	}

	/** Nội dung hộp "Xem chi tiết" của plugin. */
	public static function chi_tiet( $ket_qua, $viec, $args ) {
		if ( 'plugin_information' !== $viec ) { return $ket_qua; }
		if ( ! isset( $args->slug ) || $args->slug !== dirname( self::duong() ) ) { return $ket_qua; }
		$moi = self::ban_moi();
		if ( ! $moi ) { return $ket_qua; }
		$o = new stdClass();
		$o->name          = 'Vận Hành Chi Phí';
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
