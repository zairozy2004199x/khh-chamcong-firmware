<?php
/**
 * TỰ CẬP NHẬT vhcp-ghe TỪ GITHUB — hiện nút "Cập nhật" ngay ở màn Plugin của WordPress.
 *
 * =================================================================================================
 * Anh Thắng 14/09/2026: *"gắn và tiếp tục bản mới để nâng cấp chứ ko phải bản lùi"*.
 *
 * Trước đây mỗi bản mới phải: dựng zip → gửi qua chat → tải về → wp-admin tải lên. Site nào quên
 * tải là kẹt bản cũ mãi (đúng vụ trang /ghe kẹt ở v1.46.0). Lớp này để WordPress TỰ thấy bản mới
 * và bày nút "Cập nhật ngay" như mọi plugin khác.
 *
 * =================================================================================================
 * 🔴 CHỈ NÂNG, KHÔNG BAO GIỜ LÙI.
 *
 *   Yêu cầu số một của anh Thắng. `ban_moi()` chỉ nhận bản có version LỚN HƠN bản đang chạy
 *   (`version_compare(..., '>')`). Nếu nhánh vì lý do gì đó mang version thấp hơn (force-push,
 *   nhầm nhánh) thì hàm trả null — WordPress KHÔNG hiện cập nhật, tuyệt đối không tự hạ bản.
 *
 * =================================================================================================
 * 🔴 KHÔNG CẦN RELEASE, KHÔNG CẦN ACTIONS — repo CÔNG KHAI + đã commit sẵn dist/vhcp-ghe.zip.
 *
 *   Quy ước repo: mỗi lần bump version là commit kèm `dist/vhcp-ghe.zip` (xem CLAUDE.md §2). Nên
 *   chỉ cần đọc thẳng hai tệp trên nhánh qua raw.githubusercontent:
 *     · version mới nhất  ← đọc hằng VHG_VERSION trong vhcp-ghe/vhcp-ghe.php
 *     · gói cài đặt       ← dist/vhcp-ghe.zip (giải nén ra đúng thư mục `vhcp-ghe/`)
 *   Repo công khai nên KHÔNG cần token; vẫn đọc Option `vhcp_gh_token` nếu có để nới trần tần suất
 *   gọi API (không bắt buộc, không nhét token vào mã nguồn — đúng §4).
 *
 * ⚠️ Đổi nhánh phát triển thì đổi đúng hằng NHANH bên dưới (khớp CLAUDE.md §3).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_TuCapNhat {

	/** Repo giữ mã (công khai). */
	const REPO = 'zairozy2004199x/khh-chamcong-firmware';

	/** Nhánh phát triển vhcp-ghe đang chạy thật — xem CLAUDE.md §3. */
	const NHANH = 'claude/posh-qr-kh1urz';

	/** Token GitHub (tuỳ chọn, chỉ để nới trần tần suất) — dùng chung ô với các plugin khác. */
	const O_KHOA = 'vhcp_gh_token';

	/** Nhớ kết quả hỏi GitHub — đừng gọi mạng ở mỗi lượt tải trang admin. */
	const O_NHO   = 'vhg_gh_ban_moi';
	const NHO_LAU = 21600;   // 6 giờ

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'chen_ban_moi' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'chi_tiet' ), 10, 3 );
		/* Thư mục sau khi giải nén phải mang ĐÚNG tên `vhcp-ghe/`, không thì WordPress cài thành
		   plugin song song và trang vẫn chạy bản cũ dù báo "đã cập nhật" — lỗi im khó lần nhất. */
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'sua_ten_thu_muc' ), 10, 4 );
	}

	/** Đường plugin dạng `vhcp-ghe/vhcp-ghe.php` — khoá WordPress dùng để nhận plugin. */
	private static function duong() {
		return plugin_basename( defined( 'VHG_FILE' ) ? VHG_FILE : __FILE__ );
	}

	private static function dau_() {
		$dau = array(
			'headers' => array( 'User-Agent' => 'vhcp-ghe-tu-cap-nhat' ),
			'timeout' => 15,
		);
		$khoa = trim( (string) get_option( self::O_KHOA, '' ) );
		if ( '' !== $khoa ) { $dau['headers']['Authorization'] = 'Bearer ' . $khoa; }
		return $dau;
	}

	/**
	 * Có bản nào MỚI HƠN bản đang chạy không.
	 * @return array|null [ 'ver'=>…, 'zip'=>…, 'ngay'=>… ] hoặc null.
	 */
	public static function ban_moi( $bo_qua_nho = false ) {
		if ( ! $bo_qua_nho ) {
			$nho = get_transient( self::O_NHO );
			if ( is_array( $nho ) ) { return $nho ? $nho : null; }
			if ( 'khong' === $nho ) { return null; }
		}
		$raw = 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::NHANH . '/vhcp-ghe/vhcp-ghe.php';
		$r = wp_remote_get( $raw . '?t=' . time(), self::dau_() );   // ?t= để né bộ nhớ đệm CDN của raw
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			set_transient( self::O_NHO, 'khong', 900 );   // 15 phút rồi thử lại; hỏng thì im, đừng phá màn admin
			return null;
		}
		$body = (string) wp_remote_retrieve_body( $r );
		if ( ! preg_match( "/define\\(\\s*'VHG_VERSION'\\s*,\\s*'([0-9][0-9.]*)'/", $body, $m ) ) {
			set_transient( self::O_NHO, 'khong', 900 );
			return null;
		}
		$ver  = $m[1];
		$hien = defined( 'VHG_VERSION' ) ? VHG_VERSION : '0';
		/* 🔴 CHỈ NÂNG, KHÔNG LÙI. Bằng hoặc thấp hơn -> coi như không có bản mới. */
		if ( version_compare( $ver, $hien, '<=' ) ) {
			set_transient( self::O_NHO, array(), self::NHO_LAU );
			return null;
		}
		$tot = array(
			'ver'  => $ver,
			'zip'  => 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::NHANH . '/dist/vhcp-ghe.zip',
			'ngay' => date( 'Y-m-d' ),
		);
		set_transient( self::O_NHO, $tot, self::NHO_LAU );
		return $tot;
	}

	/** Quên kết quả đã nhớ để lần hỏi tới gọi lại GitHub ngay (dùng cho nút "Kiểm tra ngay"). */
	/**
	 * 🔴 CHỈ ĐỌC THỨ ĐÃ NHỚ — TUYỆT ĐỐI KHÔNG GỌI MẠNG.
	 *
	 * Dùng cho chỗ muốn khoe "có bản mới" ngay trong trang người dùng đang xem. `ban_moi()` có thể
	 * chờ GitHub tới 15 giây; nhét nó vào một lượt tải trang bình thường là treo cả màn hình khi
	 * mạng chậm — đúng loại lỗi mà người dùng đổ cho "web lag" chứ không ai ngờ tới bộ cập nhật.
	 * WordPress tự chạy lượt soát cập nhật định kỳ (qua bộ lọc pre_set_site_transient_update_plugins
	 * bên trên) nên ô nhớ gần như luôn có sẵn; chưa có thì trả null và màn hình chỉ đơn giản là
	 * không khoe gì.
	 */
	public static function ban_moi_nho() {
		$nho = get_transient( self::O_NHO );
		return is_array( $nho ) && $nho ? $nho : null;
	}

	public static function quen_nho() {
		delete_transient( self::O_NHO );
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
		$o->package     = $moi['zip'];
		if ( ! isset( $tr->response ) || ! is_array( $tr->response ) ) { $tr->response = array(); }
		$tr->response[ $duong ] = $o;
		return $tr;
	}

	/** Nội dung hộp "Xem chi tiết". */
	public static function chi_tiet( $ket_qua, $viec, $args ) {
		if ( 'plugin_information' !== $viec ) { return $ket_qua; }
		if ( ! isset( $args->slug ) || $args->slug !== dirname( self::duong() ) ) { return $ket_qua; }
		$moi = self::ban_moi();
		if ( ! $moi ) { return $ket_qua; }
		$o = new stdClass();
		$o->name          = 'Ghế Massage (K&H)';
		$o->slug          = $args->slug;
		$o->version       = $moi['ver'];
		$o->last_updated  = $moi['ngay'];
		$o->download_link = $moi['zip'];
		$o->sections      = array( 'changelog' => 'Bản mới trên nhánh ' . esc_html( self::NHANH )
			. '. Xem lịch sử thay đổi trong repo GitHub.' );
		return $o;
	}

	/** Thư mục vừa giải nén phải mang ĐÚNG tên `vhcp-ghe`. */
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
