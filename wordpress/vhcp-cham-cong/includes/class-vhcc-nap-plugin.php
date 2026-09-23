<?php
/**
 * NẠP PLUGIN TỪ ĐIỆN THOẠI — ô nạp .zip gọn, thay cho màn wp-admin khó bấm.
 *
 * =================================================================================================
 * 🔴 ĐÂY LÀ CỬA CHẠY MÃ PHP TRÊN MÁY CHỦ. KHÔNG PHẢI MỘT MÀN NHẬP LIỆU.
 * =================================================================================================
 * Nạp một plugin = thả tệp PHP vào máy chủ rồi cho nó chạy. Ai qua được cửa này thì đọc được cơ
 * sở dữ liệu, đọc `wp-config.php`, và với tay sang mọi site nằm chung hosting. Nên nó KHÔNG được
 * gác cùng một mức với những màn còn lại của app.
 *
 * BA CỬA, PHẢI QUA CẢ BA:
 *   ① Phiên trạm còn sống          — biết anh là ai.
 *   ② Vai ADMIN trong app          — chỉ người được khai mới thấy màn này.
 *   ③ GÕ LẠI PIN CỦA CHÍNH MÌNH    — xác nhận đúng người đang cầm máy là người bấm.
 *
 * =================================================================================================
 * 🔴 CỬA ③ LÀ LỰA CHỌN CÓ CHỦ Ý CỦA ANH THẮNG, VÀ NÓ YẾU HƠN MẬT KHẨU WORDPRESS
 * =================================================================================================
 * Bản đầu (4.77) đòi TÀI KHOẢN + MẬT KHẨU WORDPRESS có quyền cài plugin. Anh Thắng 23/09/2026:
 * *"thay vì nhập mật khẩu và đăng nhập, thì nhập pin không được không"* — em đã nói rõ cái giá
 * (PIN là 4–8 chữ số, gõ hằng ngày trước mặt người khác, và lộ nó là lộ cả máy chủ), đưa phương
 * án "mã nạp riêng ≥ 8 số", anh vẫn chọn PIN đăng nhập. Đây là quyết định của chủ hệ thống; mã
 * này làm đúng theo đó, và làm CHẶT NHẤT trong khuôn khổ ấy:
 *
 *   · PIN phải là PIN CỦA CHÍNH NGƯỜI ĐANG ĐỨNG TRONG PHIÊN (so Mã NV tra ra từ PIN với Mã NV của
 *     phiên). Biết PIN của một admin KHÁC không đủ: phải đang đăng nhập bằng đúng phiên của người
 *     ấy. Tức kẻ xấu cần cả điện thoại đang mở app LẪN PIN của cùng một người.
 *   · PIN của người khác và PIN sai hẳn trả CÙNG một câu — không xác nhận hộ "PIN này có thật".
 *   · Sai 5 lần khoá 15 phút, theo IP. Lượt đúng và lượt bỏ trống không bị đếm.
 *   · PIN không bao giờ vào nhật ký, không bao giờ in ra.
 *
 * ⚠️ Muốn quay lại cửa mật khẩu WordPress thì xem lịch sử git của tệp này ở bản 4.77.0 (hàm
 *    kiểm mật khẩu và phép đòi quyền cài plugin). Không giữ mã chết ở đây.
 *
 * =================================================================================================
 * 🔴 SOI RUỘT TỆP .ZIP, KHÔNG TIN CÁI TÊN
 * =================================================================================================
 * Tên tệp do người gửi đặt, đổi một giây là xong. Nên `kiem_tep()` mở zip ra đọc danh sách mục,
 * và đòi: đúng MỘT thư mục gốc · tên thư mục là `vhcp-…` · không mục nào vượt ra ngoài thư mục ấy
 * · có tệp plugin chính mang dòng `Plugin Name:`. Thiếu một điều là chối, không cài.
 *
 * ⚠️ Cùng một luật với `VHJP_Anh` đã đặt cho ảnh tải lên: *soi ruột chứ không tin nhãn*.
 *
 * @package VHCP_ChamCong
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NapPlugin {

	/** Quyền trong bảng vai — khai ở `VHCC_Vai::QUYEN`, bậc ADMIN. */
	const QUYEN = 'nap_plugin';

	/** Chỉ nhận plugin họ nhà mình. Một tiền tố không chặn được kẻ xấu, nhưng chặn được nhầm. */
	const TIEN_TO = 'vhcp-';

	/** Gõ sai PIN bao nhiêu lần thì khoá, và khoá bao lâu. */
	const SAI_TOI_DA = 5;
	const KHOA_LAU   = 900;      // 15 phút

	/** Trần cỡ tệp. Plugin to nhất của mình ~500 KB; 25 MB đã rộng gấp nhiều lần. */
	const CO_TOI_DA = 26214400;  // 25 MB

	/* ═══════════════════════════════════════════════════════════════ CỬA ① VÀ ② ═════════ */

	/**
	 * Người này có được vào màn nạp không.
	 *
	 * ⚠️ Nhận `$u` dạng mảng của trạm (`ma_nv` · `role`), đúng thứ `VHCC_Tram::nguoi()` trả ra.
	 */
	public static function kiem_vai( $u ) {
		if ( ! is_array( $u ) || '' === trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) ) ) {
			return array( 'ok' => false, 'error' => 'Chưa đăng nhập — mở app rồi gõ PIN trước.' );
		}
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Nạp plugin' ) );
		}
		return array( 'ok' => true );
	}

	/* ═══════════════════════════════════════════════════════════════════ CỬA ③ ══════════ */

	private static function khoa_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhcc_nap_sai_' . md5( $ip );
	}

	/** Còn được thử không, và còn mấy lượt. */
	public static function con_duoc_thu() {
		$n = (int) get_transient( self::khoa_key() );
		return array( 'ok' => $n < self::SAI_TOI_DA, 'daSai' => $n,
			'conLai' => max( 0, self::SAI_TOI_DA - $n ) );
	}

	private static function ghi_nhan_sai() {
		$k = self::khoa_key();
		set_transient( $k, (int) get_transient( $k ) + 1, self::KHOA_LAU );
	}

	/**
	 * Gõ lại PIN — và PIN ấy phải là của CHÍNH người đang đứng trong phiên.
	 *
	 * 🔴 SO MÃ NV, KHÔNG SO VAI. Tra PIN ra một người (cùng nguồn với cửa trạm, `VHCC_Tram::tim_pin`)
	 *    rồi đòi Mã NV của người ấy TRÙNG Mã NV của phiên. Chỉ đòi "PIN này của một admin nào đó"
	 *    là ai cầm được điện thoại đang mở app của anh cũng nạp được bằng PIN của một admin khác.
	 *
	 * 🔴 PIN CỦA NGƯỜI KHÁC và PIN SAI HẲN trả CÙNG MỘT CÂU. Câu khác nhau là biến ô nhập thành
	 *    máy dò PIN: thử một nghìn số, câu nào khác là số ấy có thật.
	 *
	 * ⚠️ PIN KHÔNG BAO GIỜ đi vào nhật ký hay câu báo lỗi.
	 */
	public static function kiem_pin( $u, $pin ) {
		$chung = 'PIN không đúng.';

		$con = self::con_duoc_thu();
		if ( empty( $con['ok'] ) ) {
			return array( 'ok' => false,
				'error' => 'Gõ sai quá nhiều lần — thử lại sau ' . (int) ( self::KHOA_LAU / 60 ) . ' phút.' );
		}

		$ma_phien = trim( (string) ( is_array( $u ) && isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma_phien ) {
			return array( 'ok' => false, 'error' => 'Phiên không có Mã NV — đăng nhập lại.' );
		}

		$p = VHCC_Auth::pin_sach( $pin );
		if ( '' === $p ) {
			/* Bỏ trống KHÔNG tính là một lượt dò — đó là lỗi thao tác. */
			return array( 'ok' => false, 'error' => 'Gõ lại PIN của anh/chị để xác nhận.' );
		}
		if ( ! preg_match( '/^\d{4,8}$/', $p ) ) {
			self::ghi_nhan_sai();
			return array( 'ok' => false, 'error' => $chung );
		}

		$tim = VHCC_Tram::tim_pin( $p );
		if ( empty( $tim['thay'] ) || trim( (string) $tim['ma_nv'] ) !== $ma_phien ) {
			self::ghi_nhan_sai();
			return array( 'ok' => false, 'error' => $chung );
		}
		return array( 'ok' => true, 'maNV' => $ma_phien );
	}

	/* ══════════════════════════════════════════════════════════════ SOI RUỘT ZIP ════════ */

	/**
	 * Mở tệp .zip ra đọc danh sách mục, KHÔNG giải nén.
	 *
	 * Trả `array( ok, slug, ten_plugin, error )`.
	 *
	 * 🔴 ĐỌC DANH SÁCH MỤC TRƯỚC KHI GIẢI NÉN. Giải ra rồi mới soi là đã muộn: tệp đã nằm trên
	 *    đĩa, và một mục tên `../../wp-config.php` thì nó nằm ở chỗ không ai muốn.
	 */
	public static function kiem_tep( $duong, $ten_goc = '' ) {
		$loi = function ( $m ) { return array( 'ok' => false, 'slug' => '', 'tenPlugin' => '', 'error' => $m ); };

		if ( ! is_readable( $duong ) ) { return $loi( 'Không đọc được tệp vừa tải lên.' ); }
		$co = (int) filesize( $duong );
		if ( $co <= 0 ) { return $loi( 'Tệp rỗng.' ); }
		if ( $co > self::CO_TOI_DA ) {
			return $loi( 'Tệp nặng ' . size_format( $co ) . ' — quá mức cho phép ('
				. size_format( self::CO_TOI_DA ) . ').' );
		}
		if ( '' !== $ten_goc && ! preg_match( '/\.zip$/i', (string) $ten_goc ) ) {
			return $loi( 'Chỉ nhận tệp .zip.' );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			return $loi( 'Máy chủ thiếu ZipArchive — không soi được ruột tệp nên KHÔNG cài. '
				. 'Nhờ hosting bật php-zip, hoặc nạp bằng màn wp-admin.' );
		}

		$z = new ZipArchive();
		if ( true !== $z->open( $duong ) ) { return $loi( 'Tệp không phải .zip đọc được.' ); }

		$goc = ''; $co_chinh = false; $ten_plugin = ''; $so_muc = 0;
		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			$ten = (string) $z->getNameIndex( $i );
			if ( '' === $ten ) { continue; }
			$so_muc++;

			/* Đường dẫn tuyệt đối, vượt cấp, hay gạch ngược của Windows — chối thẳng, không đoán. */
			if ( '/' === substr( $ten, 0, 1 ) || false !== strpos( $ten, '\\' )
				|| preg_match( '#(^|/)\.\.(/|$)#', $ten ) ) {
				$z->close();
				return $loi( 'Tệp chứa đường dẫn không hợp lệ: ' . esc_html( $ten ) );
			}

			$phan = explode( '/', $ten );
			$dau  = $phan[0];
			if ( '' === $goc ) { $goc = $dau; }
			if ( $dau !== $goc ) {
				$z->close();
				return $loi( 'Tệp có nhiều hơn một thư mục gốc (' . esc_html( $goc ) . ', '
					. esc_html( $dau ) . '). Một tệp .zip chỉ được chứa MỘT plugin.' );
			}
			if ( $ten === $goc . '/' . $goc . '.php' ) { $co_chinh = true; }
		}

		if ( ! $so_muc )        { $z->close(); return $loi( 'Tệp .zip rỗng.' ); }
		if ( '' === $goc )      { $z->close(); return $loi( 'Không đọc ra thư mục gốc trong tệp.' ); }
		if ( ! preg_match( '/^' . preg_quote( self::TIEN_TO, '/' ) . '[a-z0-9-]+$/', $goc ) ) {
			$z->close();
			return $loi( 'Thư mục gốc là "' . esc_html( $goc ) . '" — chỉ nhận plugin họ "'
				. self::TIEN_TO . '". Đây không phải plugin của hệ mình.' );
		}
		if ( ! $co_chinh ) {
			$z->close();
			return $loi( 'Thiếu tệp chính ' . esc_html( $goc . '/' . $goc . '.php' ) . '.' );
		}

		/* Đọc đúng tệp chính để lấy dòng `Plugin Name:` — một thư mục đặt tên cho khéo mà bên
		   trong không phải plugin thì WordPress cài vào rồi cũng không kích hoạt được. */
		$src = $z->getFromName( $goc . '/' . $goc . '.php' );
		$z->close();
		if ( false === $src || '' === $src ) { return $loi( 'Không đọc được nội dung tệp chính.' ); }
		if ( ! preg_match( '/^[ \t\/*#@]*Plugin Name\s*:\s*(.+)$/mi', (string) $src, $m ) ) {
			return $loi( 'Tệp chính không có dòng "Plugin Name:" — đây không phải một plugin.' );
		}
		$ten_plugin = trim( preg_replace( '/\s+/', ' ', $m[1] ) );

		return array( 'ok' => true, 'slug' => $goc, 'tenPlugin' => $ten_plugin, 'error' => '' );
	}

	/* ═══════════════════════════════════════════════════════ DANH SÁCH ĐANG CÀI ═════════ */

	/**
	 * Plugin họ `vhcp-` đang có trên site, kèm bản và trạng thái.
	 *
	 * =============================================================================================
	 * 🔴 KHÔNG DÙNG `get_plugins()` — ĐỌC THẲNG THƯ MỤC.
	 * =============================================================================================
	 * `get_plugins()` nằm trong `wp-admin/includes/plugin.php`, chỉ được nạp khi đang ở trang quản
	 * trị. Trang này là trang THƯỜNG (cố ý — để vào bằng PIN, không cần tài khoản WordPress), nên
	 * gọi vào là *"Call to undefined function"* và TRẮNG CẢ TRANG. Đúng lỗi anh Thắng gặp
	 * 28/08/2026 với `wp_tempnam()`, và `tools/test/kiem-goi-cheo.php` cấm hẳn cả họ hàm ấy.
	 *
	 * ⚠️ Gọi `require_once` rồi kiểm `function_exists()` NGHE có vẻ an toàn, nhưng luật của repo
	 *    là CẤM HẲN chứ không phải "gác cho khéo" — vì một cái gác viết hụt trông y hệt một cái
	 *    gác viết đủ, cho tới lúc trang trắng. Đọc thư mục thì không cần gác gì cả.
	 *
	 * ⚠️ Cũng KHÔNG dùng `is_plugin_active()` (cùng tệp wp-admin ấy) — đọc thẳng ô `active_plugins`.
	 */
	public static function ds() {
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) { return array(); }
		$thu_muc = WP_PLUGIN_DIR;
		if ( ! is_dir( $thu_muc ) ) { return array(); }

		$dang_bat = get_option( 'active_plugins' );
		$dang_bat = is_array( $dang_bat ) ? $dang_bat : array();

		$ra = array();
		foreach ( (array) glob( $thu_muc . '/' . self::TIEN_TO . '*', GLOB_ONLYDIR ) as $d ) {
			$slug  = basename( $d );
			$chinh = $d . '/' . $slug . '.php';
			if ( ! is_readable( $chinh ) ) { continue; }
			$tin = self::doc_dau( $chinh );
			$duong = $slug . '/' . $slug . '.php';
			$ra[] = array(
				'slug'  => $slug,
				'duong' => $duong,
				'ten'   => '' !== $tin['ten'] ? $tin['ten'] : $slug,
				'ban'   => $tin['ban'],
				'bat'   => in_array( $duong, $dang_bat, true ),
			);
		}
		usort( $ra, function ( $a, $b ) { return strcmp( $a['ten'], $b['ten'] ); } );
		return $ra;
	}

	/**
	 * Đọc `Plugin Name:` và `Version:` ở đầu một tệp plugin.
	 *
	 * ⚠️ Chỉ đọc 8 KB đầu — y cách WordPress làm. Đọc cả tệp là nạp vài trăm KB vào bộ nhớ cho
	 *    mỗi plugin, chỉ để lấy hai dòng.
	 */
	private static function doc_dau( $duong ) {
		$ra = array( 'ten' => '', 'ban' => '' );
		$fp = @fopen( $duong, 'r' );
		if ( ! $fp ) { return $ra; }
		$src = (string) fread( $fp, 8192 );
		fclose( $fp );
		if ( preg_match( '/^[ \t\/*#@]*Plugin Name\s*:\s*(.+)$/mi', $src, $m ) ) {
			$ra['ten'] = trim( preg_replace( '/\s+/', ' ', $m[1] ) );
		}
		if ( preg_match( '/^[ \t\/*#@]*Version\s*:\s*(.+)$/mi', $src, $m ) ) {
			$ra['ban'] = trim( preg_replace( '/\s+/', ' ', $m[1] ) );
		}
		return $ra;
	}

	/** Slug này đã cài chưa — để màn hình nói rõ "cập nhật" hay "CÀI MỚI". */
	public static function da_cai( $slug ) {
		foreach ( self::ds() as $x ) { if ( $x['slug'] === $slug ) { return $x; } }
		return null;
	}

	/* ═══════════════════════════════════════════════════════════════════ CÀI ════════════ */

	/**
	 * Ghép cả ba cửa rồi mới cài: vai → soi tệp → gõ lại PIN của chính mình.
	 *
	 * 🔴 KIỂM THEO ĐÚNG THỨ TỰ NÀY, VÀ KHÔNG BỎ CỬA NÀO. Mỗi `return` ở đây là một cửa đóng lại;
	 *    đảo thứ tự hay bỏ bớt một phép là mở đường chạy mã PHP cho người không đáng được mở.
	 *
	 * ⚠️ Dùng bộ cài CỦA WORDPRESS (`Plugin_Upgrader`) chứ không tự giải nén: nó lo chuyện quyền
	 *    ghi, thư mục tạm, gỡ bản cũ, và lùi lại khi hỏng giữa chừng. Tự viết lại mấy việc ấy là
	 *    tự chuốc đúng những lỗi mà người ta đã sửa suốt mười lăm năm.
	 */
	public static function nap( $u, $pin, $tep ) {
		$v = self::kiem_vai( $u );
		if ( empty( $v['ok'] ) ) { return $v; }

		if ( ! is_array( $tep ) || empty( $tep['tmp_name'] ) ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn tệp .zip.' );
		}
		if ( isset( $tep['error'] ) && UPLOAD_ERR_OK !== (int) $tep['error'] ) {
			return array( 'ok' => false,
				'error' => 'Tải tệp lên không xong (mã ' . (int) $tep['error'] . '). '
					. 'Thường là tệp quá nặng so với mức hosting cho phép.' );
		}

		$soi = self::kiem_tep( $tep['tmp_name'], isset( $tep['name'] ) ? $tep['name'] : '' );
		if ( empty( $soi['ok'] ) ) { return $soi; }

		/* Cửa ③ đặt SAU phép soi tệp có chủ ý: người gõ nhầm PIN vì chọn nhầm tệp thì không mất
		   một lượt thử. Đổi lại, phép soi chạy trước khi xác nhận lại người bấm — nó chỉ đọc,
		   không ghi, không cài, nên đó là cái giá chấp nhận được. */
		$xn = self::kiem_pin( $u, $pin );
		if ( empty( $xn['ok'] ) ) { return $xn; }

		$cu = self::da_cai( $soi['slug'] );

		foreach ( array( 'file.php', 'plugin.php', 'misc.php', 'class-wp-upgrader.php' ) as $f ) {
			$d = ABSPATH . 'wp-admin/includes/' . $f;
			if ( is_readable( $d ) ) { require_once $d; }
		}
		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			return array( 'ok' => false,
				'error' => 'Không nạp được bộ cài của WordPress — dùng màn wp-admin cho lượt này.' );
		}

		$skin = class_exists( 'Automatic_Upgrader_Skin' ) ? new Automatic_Upgrader_Skin() : null;
		$up   = $skin ? new Plugin_Upgrader( $skin ) : new Plugin_Upgrader();
		$kq   = $up->install( $tep['tmp_name'], array( 'overwrite_package' => true ) );

		if ( is_wp_error( $kq ) ) {
			return array( 'ok' => false, 'error' => 'WordPress từ chối: ' . $kq->get_error_message() );
		}
		if ( ! $kq ) {
			$m = $skin && method_exists( $skin, 'get_upgrade_messages' ) ? $skin->get_upgrade_messages() : array();
			return array( 'ok' => false,
				'error' => 'Cài không xong. ' . ( $m ? wp_strip_all_tags( implode( ' · ', $m ) ) : '' ) );
		}

		$moi = self::da_cai( $soi['slug'] );

		/* 🔴 GHI SỔ — và KHÔNG ghi PIN. Ghi ai, lúc nào, plugin nào, từ bản nào sang bản nào. */
		if ( class_exists( 'VHCC_NhatKy' ) && method_exists( 'VHCC_NhatKy', 'tin' ) ) {
			VHCC_NhatKy::tin( 'nap_plugin', $soi['slug'] . ' ' . ( $cu ? $cu['ban'] : '—' )
				. ' → ' . ( $moi ? $moi['ban'] : '?' )
				. ' · ' . ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '?' )
				. ' · xác nhận: PIN' );
		}

		return array( 'ok' => true, 'slug' => $soi['slug'], 'tenPlugin' => $soi['tenPlugin'],
			'banCu' => $cu ? $cu['ban'] : '', 'banMoi' => $moi ? $moi['ban'] : '',
			'caiMoi' => ! $cu,
			'msg' => ( $cu ? 'Đã cập nhật ' : 'Đã CÀI MỚI ' ) . $soi['tenPlugin']
				. ( $cu && $moi ? ' · ' . $cu['ban'] . ' → ' . $moi['ban']
					: ( $moi ? ' · bản ' . $moi['ban'] : '' ) ) . '.' );
	}
}
