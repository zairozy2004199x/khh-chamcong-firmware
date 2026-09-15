<?php
/**
 * TỰ CẬP NHẬT vhcp-saoke TỪ GITHUB — hiện nút "Cập nhật" ngay ở màn Plugin của WordPress.
 *
 * =================================================================================================
 * Anh Thắng 15/09/2026: *"để tránh tạo nhiều trang và up lên nhiều plugin"*. Bản gốc của lớp này
 * nằm ở `vhcp-ghe/includes/class-vhg-tu-cap-nhat.php` và đã chạy thật cả buổi chiều 15/09 (trang
 * Ghế đi từ 2.93 lên 2.98 mà không phải tải một tệp zip nào). Đây là bản chép sang Sao Kê — cùng
 * luật, khác ba thứ: tên plugin, tên tệp zip, và chỗ đọc số bản.
 *
 * Sau bản này, cả Ghế lẫn Sao Kê hiện chung ở **WordPress → Cập nhật**: tích cả hai, bấm một lần.
 *
 * =================================================================================================
 * 🔴 CHỈ NÂNG, KHÔNG BAO GIỜ LÙI.
 *
 *   `ban_moi()` chỉ nhận bản có version LỚN HƠN bản đang chạy (`version_compare(..., '>')`). Nhánh
 *   lỡ mang version thấp hơn (force-push, nhầm nhánh) thì hàm trả null — WordPress KHÔNG hiện cập
 *   nhật, tuyệt đối không tự hạ bản.
 *
 * =================================================================================================
 * 🔴 KHÔNG CẦN RELEASE, KHÔNG CẦN ACTIONS — repo CÔNG KHAI + đã commit sẵn `dist/vhcp-saoke.zip`.
 *
 *   Quy ước repo (CLAUDE.md §1): mỗi lần tăng số bản là commit kèm tệp zip. Nên chỉ cần đọc thẳng
 *   hai tệp trên nhánh qua raw.githubusercontent:
 *     · số bản mới nhất ← hằng `VER` trong `vhcp-saoke/vhcp-saoke.php`
 *     · gói cài đặt     ← `dist/vhcp-saoke.zip` (giải nén ra đúng thư mục `vhcp-saoke/`)
 *   Repo công khai nên KHÔNG cần token; vẫn đọc Option `vhcp_gh_token` nếu có để nới trần tần suất
 *   gọi (không bắt buộc, không nhét token vào mã nguồn — đúng §4).
 *
 * ⚠️ ĐỌC HẰNG `VER`, KHÔNG ĐỌC HEADER `Version:`. Sao Kê khai số bản ở HAI chỗ (§6 CLAUDE.md) và
 *    bộ thử canh chúng bằng nhau; đọc hằng là đọc đúng thứ mã nguồn dùng để tự xưng.
 *
 * ⚠️ Đổi nhánh phát triển thì đổi hằng NHANH bên dưới (khớp CLAUDE.md §3).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'SAOKE_TuCapNhat' ) ) :

class SAOKE_TuCapNhat {

	/** Repo giữ mã (công khai). */
	const REPO = 'zairozy2004199x/khh-chamcong-firmware';

	/** Nhánh phát triển đang chạy thật — xem CLAUDE.md §3. */
	const NHANH = 'claude/posh-qr-kh1urz';

	/** Token GitHub (tuỳ chọn, chỉ để nới trần tần suất) — dùng chung ô với các plugin khác. */
	const O_KHOA = 'vhcp_gh_token';

	/** Nhớ kết quả hỏi GitHub — đừng gọi mạng ở mỗi lượt tải trang admin. */
	const O_NHO   = 'saoke_gh_ban_moi';
	const NHO_LAU = 21600;   // 6 giờ

	/** Số bản đang chạy — đọc từ chính lớp app, không chép lại thành một con số thứ ba. */
	private static function ban_hien() {
		return ( class_exists( 'SAOKE_App' ) && defined( 'SAOKE_App::VER' ) ) ? (string) SAOKE_App::VER : '0';
	}

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'chen_ban_moi' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'chi_tiet' ), 10, 3 );
		/* Thư mục sau khi giải nén phải mang ĐÚNG tên `vhcp-saoke/`, không thì WordPress cài thành
		   plugin song song và trang vẫn chạy bản cũ dù báo "đã cập nhật" — lỗi im khó lần nhất. */
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'sua_ten_thu_muc' ), 10, 4 );
	}

	/** Đường plugin dạng `vhcp-saoke/vhcp-saoke.php` — khoá WordPress dùng để nhận plugin. */
	private static function duong() {
		return plugin_basename( dirname( __FILE__ ) . '/vhcp-saoke.php' );
	}

	private static function dau_() {
		$dau = array(
			'headers' => array( 'User-Agent' => 'vhcp-saoke-tu-cap-nhat' ),
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
		$raw = 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::NHANH . '/vhcp-saoke/vhcp-saoke.php';
		$r = wp_remote_get( $raw . '?t=' . time(), self::dau_() );   // ?t= để né bộ nhớ đệm CDN của raw
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			set_transient( self::O_NHO, 'khong', 900 );   // 15 phút rồi thử lại; hỏng thì im, đừng phá màn admin
			return null;
		}
		$body = (string) wp_remote_retrieve_body( $r );
		if ( ! preg_match( "/const VER\\s*=\\s*'([0-9][0-9.]*)'/", $body, $m ) ) {
			set_transient( self::O_NHO, 'khong', 900 );
			return null;
		}
		$ver  = $m[1];
		$hien = self::ban_hien();
		/* 🔴 CHỈ NÂNG, KHÔNG LÙI. Bằng hoặc thấp hơn -> coi như không có bản mới. */
		if ( version_compare( $ver, $hien, '<=' ) ) {
			set_transient( self::O_NHO, array(), self::NHO_LAU );
			return null;
		}
		$tot = array(
			'ver'  => $ver,
			'zip'  => 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::NHANH . '/dist/vhcp-saoke.zip',
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

	/** Gắn bản mới vào danh sách cập nhật của WordPress. */
	public static function chen_ban_moi( $tr ) {
		if ( ! is_object( $tr ) ) { return $tr; }
		$moi = self::ban_moi();
		if ( ! $moi ) { return $tr; }
		$duong = self::duong();
		$o = new stdClass();
		$o->slug        = 'vhcp-saoke';
		$o->plugin      = $duong;
		$o->new_version = $moi['ver'];
		$o->url         = 'https://github.com/' . self::REPO;
		$o->package     = $moi['zip'];
		if ( ! isset( $tr->response ) || ! is_array( $tr->response ) ) { $tr->response = array(); }
		$tr->response[ $duong ] = $o;
		return $tr;
	}

	/** Bảng thông tin khi bấm "Xem chi tiết" ở màn Plugin. */
	public static function chi_tiet( $ket_qua, $viec, $args ) {
		if ( 'plugin_information' !== $viec ) { return $ket_qua; }
		if ( ! isset( $args->slug ) || 'vhcp-saoke' !== $args->slug ) { return $ket_qua; }
		$moi = self::ban_moi();
		$o = new stdClass();
		$o->name          = 'Sao Kê Ngân Hàng K&H (SePay)';
		$o->slug          = 'vhcp-saoke';
		$o->version       = $moi ? $moi['ver'] : self::ban_hien();
		$o->author        = 'K&H';
		$o->homepage      = 'https://github.com/' . self::REPO;
		$o->download_link = $moi ? $moi['zip'] : '';
		$o->sections      = array(
			'description' => 'Sao kê & đối soát dòng tiền qua SePay + đối chiếu nộp tiền theo điểm '
				. '+ sao kê cổng Việt QR/MoMo/VNPAY. Nguồn: nhánh <code>' . esc_html( self::NHANH )
				. '</code> của repo công khai.',
		);
		return $o;
	}

	/**
	 * 🔴 ĐỔI TÊN THƯ MỤC SAU KHI GIẢI NÉN VỀ ĐÚNG `vhcp-saoke`.
	 *
	 * Gói tải từ raw giải ra đã đúng tên (vì zip được dựng từ thư mục `vhcp-saoke/`), nhưng vẫn
	 * gác ở đây: nếu vì lý do gì đó tên lệch, WordPress sẽ cài thành MỘT PLUGIN KHÁC nằm song
	 * song, plugin cũ vẫn chạy, và màn hình vẫn là bản cũ dù báo "đã cập nhật" — đúng loại lỗi im
	 * lặng tốn cả ngày mà 15/09/2026 đã dính một lần (xem chú thích opcache bên vhcp-ghe.php).
	 */
	public static function sua_ten_thu_muc( $nguon, $nguon_xa, $nang_cap, $dau = array() ) {
		global $wp_filesystem;
		if ( ! is_array( $dau ) || ! isset( $dau['plugin'] ) || self::duong() !== $dau['plugin'] ) { return $nguon; }
		$muon = trailingslashit( dirname( $nguon ) ) . 'vhcp-saoke';
		if ( untrailingslashit( $nguon ) === $muon ) { return $nguon; }
		if ( $wp_filesystem && $wp_filesystem->move( $nguon, $muon, true ) ) { return trailingslashit( $muon ); }
		return $nguon;
	}
}

endif;
