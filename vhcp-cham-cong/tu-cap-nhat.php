<?php
/**
 * TỰ CẬP NHẬT vhcp-cham-cong TỪ GITHUB — hiện nút "Cập nhật" ngay ở màn Plugin của WordPress,
 * và tự hiện thêm một dòng ở trang tổng của IT (khmatrix.com/it).
 *
 * =================================================================================================
 * Đây là bản thứ BA của cùng một lớp. Bản gốc: `vhcp-ghe/includes/class-vhg-tu-cap-nhat.php`
 * (chạy thật chiều 15/09/2026, trang Ghế đi từ 2.93 lên 2.102 mà không tải một tệp zip nào), bản
 * thứ hai: `vhcp-saoke/tu-cap-nhat.php`. Cùng luật, mỗi bản khác đúng bốn thứ: tên plugin, tên tệp
 * zip, chỗ đọc số bản, và tên ô nhớ.
 *
 * Anh Thắng 15/09/2026: *"Việt vào plugin để cập nhật cũng bất tiện"* → trang `/it` dựng bảng từ
 * bộ lọc `vhcp_tu_cap_nhat_ds`; gắn lớp này là plugin chấm công TỰ hiện vào bảng ấy, không phải
 * đi sửa trang `/it` thêm dòng nào.
 *
 * =================================================================================================
 * 🔴 CHỈ NÂNG, KHÔNG BAO GIỜ LÙI.
 *
 *   `ban_moi()` chỉ nhận bản có version LỚN HƠN bản đang chạy (`version_compare(..., '>')`). Nhánh
 *   lỡ mang version thấp hơn (force-push, nhầm nhánh) thì hàm trả null — WordPress KHÔNG hiện cập
 *   nhật, tuyệt đối không tự hạ bản. Với plugin chấm công điều này còn nặng hơn hai plugin kia:
 *   `vhcc_maybe_upgrade()` chạy `VHCC_DB::install()` mỗi lần số bản đổi, nên một lượt hạ bản là
 *   một lượt cho lược đồ bảng cũ chạy lại trên dữ liệu mới.
 *
 * =================================================================================================
 * 🔴 KHÔNG CẦN RELEASE, KHÔNG CẦN ACTIONS — repo CÔNG KHAI + đã commit sẵn `dist/vhcp-cham-cong.zip`.
 *
 *   Quy ước repo (CLAUDE.md §1): mỗi lần tăng số bản là commit kèm tệp zip. Nên chỉ cần đọc thẳng
 *   hai tệp trên nhánh qua raw.githubusercontent:
 *     · số bản mới nhất ← hằng `VHCC_VERSION` trong `vhcp-cham-cong/vhcp-cham-cong.php`
 *     · gói cài đặt     ← `dist/vhcp-cham-cong.zip` (giải nén ra đúng thư mục `vhcp-cham-cong/`)
 *   Repo công khai nên KHÔNG cần token; vẫn đọc Option `vhcp_gh_token` nếu có để nới trần tần suất
 *   gọi (không bắt buộc, không nhét token vào mã nguồn — đúng §4).
 *
 * ⚠️ ĐỌC HẰNG `VHCC_VERSION`, KHÔNG ĐỌC HEADER `Version:`. Số bản khai HAI chỗ và bộ thử
 *    `kiem-chamcong-ban.php` canh chúng bằng nhau; đọc hằng là đọc đúng thứ mã nguồn dùng để tự
 *    xưng (`vhcc_maybe_upgrade()` so `get_option('vhcc_ver')` với chính hằng này).
 *
 * ⚠️ Đổi nhánh phát triển thì đổi hằng NHANH bên dưới (khớp CLAUDE.md §3).
 *
 * =================================================================================================
 * ⚠️ THƯ MỤC `wp-content/uploads/vhcc-mat/` KHÔNG NẰM TRONG GÓI — VÀ ĐÓ LÀ CỐ Ý.
 *
 *   Cài đè plugin bằng zip thì WordPress XOÁ SẠCH thư mục plugin cũ rồi giải nén bản mới. Bảy
 *   megabyte model khuôn mặt mà nằm trong plugin là mỗi lượt cập nhật bay một lần (xem
 *   `assets/mat/DOC-TRUOC.txt`). Tự cập nhật làm việc cài đè ấy xảy ra THƯỜNG XUYÊN HƠN NHIỀU, nên
 *   chỗ đúng của model càng phải là `uploads/`. Đừng "tiện tay" gói model vào zip.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'VHCC_TuCapNhat' ) ) :

class VHCC_TuCapNhat {

	/** Repo giữ mã (công khai). */
	const REPO = 'zairozy2004199x/khh-chamcong-firmware';

	/** Nhánh phát triển đang chạy thật — xem CLAUDE.md §3. */
	const NHANH = 'claude/posh-qr-kh1urz';

	/** Token GitHub (tuỳ chọn, chỉ để nới trần tần suất) — dùng chung ô với các plugin khác. */
	const O_KHOA = 'vhcp_gh_token';

	/** Nhớ kết quả hỏi GitHub — đừng gọi mạng ở mỗi lượt tải trang admin. */
	const O_NHO   = 'vhcc_gh_ban_moi';
	const NHO_LAU = 21600;   // 6 giờ

	/** Mã thư mục plugin — dùng ở cả bốn chỗ dưới, khai một lần để không gõ lệch. */
	const MA = 'vhcp-cham-cong';

	/** Số bản đang chạy — đọc từ chính hằng của plugin, không chép lại thành một con số thứ ba. */
	private static function ban_hien() {
		return defined( 'VHCC_VERSION' ) ? (string) VHCC_VERSION : '0';
	}

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'chen_ban_moi' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'chi_tiet' ), 10, 3 );
		/* Thư mục sau khi giải nén phải mang ĐÚNG tên `vhcp-cham-cong/`, không thì WordPress cài
		   thành plugin song song và trang vẫn chạy bản cũ dù báo "đã cập nhật". */
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'sua_ten_thu_muc' ), 10, 4 );
		/* 🔴 TỰ KHAI TÊN VÀO DANH SÁCH CHUNG. Trang `/it` và tab "Cập nhật" trong /ghe dựng bảng từ
		   chính danh sách này — thêm MỘT dòng như dưới là tự hiện, không phải sửa trang ấy. */
		add_filter( 'vhcp_tu_cap_nhat_ds', array( __CLASS__, 'khai_ds' ) );
	}

	/** Đường plugin dạng `vhcp-cham-cong/vhcp-cham-cong.php` — khoá WordPress dùng để nhận plugin. */
	private static function duong() {
		return plugin_basename( dirname( __FILE__ ) . '/' . self::MA . '.php' );
	}

	private static function dau_() {
		$dau = array(
			'headers' => array( 'User-Agent' => self::MA . '-tu-cap-nhat' ),
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
		$goc = 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::NHANH . '/';
		$r = wp_remote_get( $goc . self::MA . '/' . self::MA . '.php?t=' . time(), self::dau_() );
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			set_transient( self::O_NHO, 'khong', 900 );   // 15 phút rồi thử lại; hỏng thì im
			return null;
		}
		$body = (string) wp_remote_retrieve_body( $r );
		if ( ! preg_match( "/define\\(\\s*'VHCC_VERSION',\\s*'([0-9][0-9.]*)'\\s*\\)/", $body, $m ) ) {
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
			'zip'  => $goc . 'dist/' . self::MA . '.zip',
			'ngay' => date( 'Y-m-d' ),
		);
		set_transient( self::O_NHO, $tot, self::NHO_LAU );
		return $tot;
	}

	/**
	 * 🔴 CHỈ ĐỌC THỨ ĐÃ NHỚ — TUYỆT ĐỐI KHÔNG GỌI MẠNG.
	 *
	 * Dùng cho chỗ muốn khoe "có bản mới" ngay trong trang người dùng đang xem. `ban_moi()` có thể
	 * chờ GitHub tới 15 giây; nhét nó vào một lượt tải trang bình thường là treo cả màn hình khi
	 * mạng chậm — đúng loại lỗi người dùng đổ cho "web lag" chứ không ai ngờ tới bộ cập nhật.
	 */
	public static function ban_moi_nho() {
		$nho = get_transient( self::O_NHO );
		return is_array( $nho ) && $nho ? $nho : null;
	}

	/** Quên kết quả đã nhớ để lần hỏi tới gọi lại GitHub ngay (nút "Kiểm tra ngay" ở trang /it). */
	public static function quen_nho() {
		delete_transient( self::O_NHO );
	}

	/** Khai tên mình vào danh sách plugin có thể tự cập nhật. */
	public static function khai_ds( $ds ) {
		if ( ! is_array( $ds ) ) { $ds = array(); }
		$ds[] = array(
			'ma'    => self::MA,
			'ten'   => 'Chấm Công (K&H)',
			'duong' => self::duong(),
			'hien'  => (string) self::ban_hien(),
			'lop'   => __CLASS__,
		);
		return $ds;
	}

	/** Gắn bản mới vào danh sách cập nhật của WordPress. */
	public static function chen_ban_moi( $tr ) {
		if ( ! is_object( $tr ) ) { return $tr; }
		$moi = self::ban_moi();
		if ( ! $moi ) { return $tr; }
		$duong = self::duong();
		$o = new stdClass();
		$o->slug        = self::MA;
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
		if ( ! isset( $args->slug ) || self::MA !== $args->slug ) { return $ket_qua; }
		$moi = self::ban_moi();
		$o = new stdClass();
		$o->name          = 'Chấm Công (K&H)';
		$o->slug          = self::MA;
		$o->version       = $moi ? $moi['ver'] : self::ban_hien();
		$o->author        = 'K&H';
		$o->homepage      = 'https://github.com/' . self::REPO;
		$o->download_link = $moi ? $moi['zip'] : '';
		$o->sections      = array(
			'description' => 'Chấm công, nhân sự, lịch, lương, yêu cầu, máy chấm công và OTA — chạy '
				. 'thẳng trên MySQL của website. Nguồn: nhánh <code>' . esc_html( self::NHANH )
				. '</code> của repo công khai.',
		);
		return $o;
	}

	/**
	 * 🔴 ĐỔI TÊN THƯ MỤC SAU KHI GIẢI NÉN VỀ ĐÚNG `vhcp-cham-cong`.
	 *
	 * Gói tải từ raw giải ra đã đúng tên (vì zip được dựng từ thư mục `vhcp-cham-cong/`), nhưng vẫn
	 * gác ở đây: nếu vì lý do gì đó tên lệch, WordPress sẽ cài thành MỘT PLUGIN KHÁC nằm song song,
	 * plugin cũ vẫn chạy, và màn hình vẫn là bản cũ dù báo "đã cập nhật" — loại lỗi im lặng tốn cả
	 * ngày mà 15/09/2026 đã dính một lần (xem chú thích opcache ở vhcp-ghe.php).
	 */
	public static function sua_ten_thu_muc( $nguon, $nguon_xa, $nang_cap, $dau = array() ) {
		global $wp_filesystem;
		if ( ! is_array( $dau ) || ! isset( $dau['plugin'] ) || self::duong() !== $dau['plugin'] ) { return $nguon; }
		$muon = trailingslashit( dirname( $nguon ) ) . self::MA;
		if ( untrailingslashit( $nguon ) === $muon ) { return $nguon; }
		if ( $wp_filesystem && $wp_filesystem->move( $nguon, $muon, true ) ) { return trailingslashit( $muon ); }
		return $nguon;
	}
}

endif;
