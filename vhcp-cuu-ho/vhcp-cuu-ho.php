<?php
/**
 * Plugin Name:       Cứu Hộ Plugin (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Chụp lại bản cũ trước mỗi lượt cập nhật, và cho hạ cấp về bản trước bằng một nút — kể cả khi plugin kia đã chết.
 * Version:           1.1.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * VÌ SAO CÓ TỆP NÀY — SÁNG 16/09/2026, khmatrix.com/ghe SẬP.
 *
 * Anh Thắng bấm Cập nhật ở trang /it. `Plugin_Upgrader` xoá sạch thư mục plugin rồi mới giải
 * nén; một tệp không xoá được (xem CLAUDE.md §1 — `class-vhg-baocao.php` kẹt quyền trên host)
 * nên lượt cài đứt giữa chừng. Thư mục còn dở dang, plugin chết, và chết theo là mọi trang ảo:
 * /ghe, /it, /mua-ma. Nhân viên đang thu tiền không nộp được, phải chờ người cài tay lại zip.
 *
 * 🔴 VÀ ĐÂY LÀ BÀI HỌC ĐẮT NHẤT CỦA HÔM ẤY: NÚT CỨU HỘ NẰM TRONG THỨ ĐANG HỎNG THÌ KHÔNG PHẢI
 *    NÚT CỨU HỘ. Trang /it nằm trong chính plugin Ghế. Ghế chết thì /it chết theo, đúng lúc cần
 *    nó nhất. Nên tệp này là MỘT PLUGIN RIÊNG, không gọi một hàm nào của Ghế, không đọc bảng
 *    nào của Ghế, và không tắt khi Ghế tắt.
 *
 * 🔴 VÌ SAO ĐÚNG MỘT TỆP, KHÔNG CÓ `includes/`.
 *    Cùng lý lẽ. Thư mục nhiều tệp là thứ có thể cài dở dang — mà cài dở dang chính là cái mình
 *    đang đi chữa. Một tệp thì hoặc có hoặc không, không có trạng thái ở giữa. Đừng "dọn cho
 *    gọn" bằng cách tách ra thành lớp; gọn không phải là thứ tệp này tồn tại vì nó.
 *
 * 🔴 VÌ SAO KHÔNG DÙNG LUẬT ĐƯỜNG DẪN (rewrite).
 *    Trang ảo sống nhờ luật đường dẫn nằm trong CSDL. Lượt cài hỏng có thể làm mất luật ấy —
 *    sáng nay /ghe và /it cùng 404 đúng kiểu đó. Nên trang cứu hộ mở bằng THAM SỐ TRUY VẤN trên
 *    trang chủ (`/?cuuho=1`), đường đi không cần luật nào cả. Xấu hơn, nhưng nó lên được vào
 *    cái hôm mà mọi thứ khác không lên.
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHCH_VERSION', '1.1.0' );

if ( ! class_exists( 'VHCH_CuuHo' ) ) :

class VHCH_CuuHo {

	/** Tham số mở trang cứu hộ: khmatrix.com/?cuuho=1 */
	const THAM_SO = 'cuuho';

	/** Băm của mã vào trang cứu hộ. 🔴 KHÔNG BAO GIỜ lưu mã trần (CLAUDE.md §4). */
	const O_MA = 'vhch_ma_bam';

	/** Đếm lượt gõ sai để khoá tạm — chống dò mã. */
	const O_SAI = 'vhch_sai_';

	public static function init() {
		/* 🔴 CHỤP BẢN CŨ TRƯỚC KHI CẬP NHẬT — móc này chạy TRƯỚC lúc thư mục bị xoá.
		   `upgrader_process_complete` thì đã muộn: lúc ấy bản cũ không còn trên đĩa để mà chụp. */
		add_filter( 'upgrader_pre_install', array( __CLASS__, 'chup_truoc' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'phuc_vu' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		/* 🔴 MỞ KHO RA CHO TRANG KHÁC ĐỌC — MỘT CHIỀU, CHỈ ĐỌC.
		   Trang /it (trong plugin Ghế) dựng ô "chọn bản để cài" từ danh sách này, nên anh Thắng
		   chọn được đúng bản đã thử ổn thay vì chỉ có mỗi bản mới nhất.
		   ⚠️ Chiều phụ thuộc phải là /it → cứu hộ, TUYỆT ĐỐI không ngược lại. Cứu hộ mà cần /it
		      còn sống mới chạy được thì nó thành đúng thứ nó đi chữa. Bộ lọc là một chiều: bên
		      này khai ra rồi thôi, không hỏi han gì bên kia, không có bên kia cũng không sao. */
		add_filter( 'vhcp_cuu_ho_anh', array( __CLASS__, 'khai_anh' ) );
		add_filter( 'vhcp_cuu_ho_cai', array( __CLASS__, 'cai_ho' ), 10, 2 );
	}

	/** Khai danh sách ảnh chụp cho trang khác đọc. Xem khối 🔴 ở init(). */
	public static function khai_anh( $ds ) {
		if ( ! is_array( $ds ) ) { $ds = array(); }
		foreach ( self::ds_anh() as $a ) { $ds[] = $a; }
		return $ds;
	}

	/**
	 * CÀI HỘ MỘT ẢNH CHỤP cho trang khác gọi sang.
	 *
	 * 🔴 SOÁT QUYỀN TẠI ĐÂY, ĐỪNG TIN BÊN GỌI. Bộ lọc thì ai gắn vào cũng gọi được; một plugin
	 *    thứ ba (hay một lỗi ở /it) mà gọi nhầm là cài đè mã nguồn lên máy chủ. Quyền phải soát
	 *    ở chỗ THỰC HIỆN, không phải ở chỗ vẽ nút.
	 */
	public static function cai_ho( $tra, $tep ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'update_plugins' ) ) {
			return array( 'ok' => false, 'loi' => 'Không đủ quyền cài plugin.' );
		}
		return self::ha_cap( (string) $tep );
	}

	// ══════════════════════════════════════════════════════════════════════ kho ảnh chụp

	/**
	 * Thư mục giữ ảnh chụp: wp-content/uploads/vhcp-cuu-ho/
	 *
	 * ⚠️ PHẢI nằm trong uploads/, KHÔNG nằm trong thư mục plugin. Cài đè plugin là WordPress xoá
	 *    sạch thư mục plugin — ảnh chụp để trong đó thì mất đúng vào lượt cần nó. uploads/ thì
	 *    WordPress không đụng tới (cùng lý do thư viện khuôn mặt của Chấm Công nằm ở đấy).
	 */
	private static function kho() {
		$u = wp_upload_dir();
		$d = trailingslashit( $u['basedir'] ) . 'vhcp-cuu-ho';
		if ( ! is_dir( $d ) ) {
			wp_mkdir_p( $d );
			/* Kho chứa mã nguồn plugin — chặn đọc từ web. Hai lớp: .htaccess cho Apache, và một
			   index.php trống cho ca máy chủ bỏ qua .htaccess. */
			@file_put_contents( $d . '/.htaccess', "Require all denied\nDeny from all\n" );
			@file_put_contents( $d . '/index.php', "<?php // im lặng là vàng\n" );
		}
		return $d;
	}

	/** Tên tệp ảnh chụp — mã plugin + số bản + mốc, để xếp theo thời gian và đọc được bằng mắt. */
	private static function ten_anh( $ma, $ban ) {
		return sanitize_file_name( $ma . '--' . $ban . '--' . gmdate( 'Ymd-His' ) . '.zip' );
	}

	/**
	 * CHỤP THƯ MỤC PLUGIN THÀNH .ZIP TRƯỚC KHI CẬP NHẬT.
	 *
	 * @param bool|WP_Error $tra  Giá trị đang đi qua bộ lọc — PHẢI trả nguyên, đừng nuốt.
	 * @param array         $dau  ['plugin' => 'vhcp-ghe/vhcp-ghe.php'] khi là lượt cập nhật plugin.
	 */
	public static function chup_truoc( $tra, $dau ) {
		/* 🔴 TRẢ `$tra` NGUYÊN VẸN Ở MỌI ĐƯỜNG RA. Đây là bộ LỌC nằm giữa luồng cập nhật của
		   WordPress: trả về một WP_Error (hay cả `false`) là CHẶN lượt cài. Một tệp cứu hộ mà
		   chụp hỏng rồi chặn luôn lượt cập nhật thì nó là thứ gây sự cố, không phải thứ chữa. */
		if ( ! is_array( $dau ) || empty( $dau['plugin'] ) ) { return $tra; }
		$duong = (string) $dau['plugin'];
		$ma    = dirname( $duong );
		if ( '' === $ma || '.' === $ma ) { return $tra; }

		$goc = trailingslashit( WP_PLUGIN_DIR ) . $ma;
		if ( ! is_dir( $goc ) || ! class_exists( 'ZipArchive' ) ) { return $tra; }

		$ban = '0';
		if ( function_exists( 'get_plugin_data' ) ) {
			$tt  = @get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $duong, false, false );
			$ban = ( is_array( $tt ) && ! empty( $tt['Version'] ) ) ? (string) $tt['Version'] : '0';
		}

		$dich = trailingslashit( self::kho() ) . self::ten_anh( $ma, $ban );
		$z    = new ZipArchive();
		if ( true !== $z->open( $dich, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return $tra; }
		$xep = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $goc, FilesystemIterator::SKIP_DOTS ) );
		$cat = strlen( trailingslashit( WP_PLUGIN_DIR ) );
		foreach ( $xep as $t ) {
			if ( $t->isDir() ) { continue; }
			$z->addFile( $t->getPathname(), substr( $t->getPathname(), $cat ) );
		}
		$z->close();

		self::don_kho( $ma );
		return $tra;
	}

	/**
	 * GIỮ 5 ẢNH GẦN NHẤT MỖI PLUGIN.
	 *
	 * ⚠️ Không giới hạn thì kho phình mãi — gói Chấm Công đã 850 KB, Ghế 2,2 MB; vài chục lượt
	 *    cập nhật là hết dung lượng hosting, và hosting đầy là hỏng cả website chứ không riêng
	 *    plugin nào. Năm bản là đủ lùi qua một buổi vá dồn dập.
	 */
	private static function don_kho( $ma ) {
		$ds = glob( trailingslashit( self::kho() ) . sanitize_file_name( $ma ) . '--*.zip' );
		if ( ! is_array( $ds ) || count( $ds ) <= 5 ) { return; }
		sort( $ds );   // tên mang mốc thời gian nên xếp chữ cái = xếp thời gian
		foreach ( array_slice( $ds, 0, count( $ds ) - 5 ) as $cu ) { @unlink( $cu ); }
	}

	/** Danh sách ảnh chụp đang có, mới nhất trước. */
	private static function ds_anh() {
		$ra = array();
		foreach ( (array) glob( trailingslashit( self::kho() ) . '*.zip' ) as $t ) {
			$ten = basename( $t );
			$p   = explode( '--', substr( $ten, 0, -4 ) );
			if ( count( $p ) < 3 ) { continue; }
			$ra[] = array( 'tep' => $ten, 'ma' => $p[0], 'ban' => $p[1], 'moc' => $p[2],
				'co' => size_format( (int) filesize( $t ) ) );
		}
		usort( $ra, function ( $a, $b ) { return strcmp( $b['moc'], $a['moc'] ); } );
		return $ra;
	}

	// ══════════════════════════════════════════════════════════════════════ hạ cấp

	/**
	 * CÀI ĐÈ MỘT ẢNH CHỤP TRỞ LẠI.
	 *
	 * 🔴 SOÁT XOÁ ĐƯỢC TRƯỚC — cùng cửa mà `VHG_Trang::cn_tep_ket_()` đang gác bên plugin Ghế.
	 *    Ở đây PHẢI có bản riêng, không gọi sang Ghế: cả lý do tồn tại của tệp này là chạy được
	 *    vào lúc Ghế đã chết. Hai bản sao của một luật là thứ CLAUDE.md §6 cấm — ngoại lệ này cố
	 *    ý và có ghi lại, và `kiem-cuu-ho.php` canh hai bên nói cùng một điều.
	 */
	private static function ha_cap( $tep ) {
		$tep = basename( $tep );
		$goc = trailingslashit( self::kho() ) . $tep;
		if ( ! is_file( $goc ) ) { return array( 'ok' => false, 'loi' => 'Không thấy ảnh chụp này.' ); }
		$p = explode( '--', substr( $tep, 0, -4 ) );
		if ( count( $p ) < 3 ) { return array( 'ok' => false, 'loi' => 'Tên ảnh chụp không đọc được.' ); }
		$ma = $p[0];

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( 'direct' === get_filesystem_method() ) {
			$ket = self::tep_ket( trailingslashit( WP_PLUGIN_DIR ) . $ma );
			if ( $ket ) {
				return array( 'ok' => false, 'loi' => 'CHƯA ĐỘNG VÀO GÌ CẢ — ' . count( $ket )
					. ' chỗ không xoá được: ' . implode( ' · ', array_slice( $ket, 0, 4 ) )
					. '. Cài tiếp là hỏng nửa chừng. Nhờ hosting sửa quyền thư mục trước.' );
			}
		}

		$up = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$kq = $up->install( $goc, array( 'overwrite_package' => true ) );
		if ( is_wp_error( $kq ) ) { return array( 'ok' => false, 'loi' => $kq->get_error_message() ); }
		if ( false === $kq )      { return array( 'ok' => false, 'loi' => 'Máy chủ không cho ghi tệp plugin (host đòi FTP?).' ); }

		/* Cài xong mà plugin đang tắt thì bật lại — hạ cấp để trang sống lại, không phải để nó
		   nằm im chờ ai đó nhớ ra là phải bật. */
		$duong = $ma . '/' . $ma . '.php';
		if ( function_exists( 'is_plugin_active' ) && ! is_plugin_active( $duong )
			&& is_file( trailingslashit( WP_PLUGIN_DIR ) . $duong ) ) {
			activate_plugin( $duong );
		}
		/* Luật đường dẫn của plugin vừa cài lại chưa có trong CSDL -> trang ảo còn 404. Nạp lại
		   ngay, đừng bắt người đang cuống đi tìm Settings → Permalinks. */
		flush_rewrite_rules( false );
		return array( 'ok' => true, 'ma' => $ma, 'ban' => $p[1] );
	}

	/**
	 * DÒ CHỖ KHÔNG XOÁ ĐƯỢC.
	 *
	 * ⚠️ Xoá một tệp cần quyền GHI TRÊN THƯ MỤC CHA, không phải trên chính tệp. Soát nhầm sang
	 *    `is_writable($tep)` là cửa luôn xanh mà không chặn được gì: tệp chmod 444 trong thư mục
	 *    ghi được thì vẫn xoá bình thường, còn tệp chmod 777 trong thư mục khoá thì không.
	 */
	private static function tep_ket( $thu_muc ) {
		$ket = array();
		if ( ! is_dir( $thu_muc ) ) { return $ket; }
		$goc = trailingslashit( dirname( $thu_muc ) );
		if ( ! is_writable( dirname( $thu_muc ) ) ) { $ket[] = basename( $thu_muc ) . '/ (thư mục cha khoá)'; }
		$xep = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $thu_muc, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $xep as $t ) {
			$d = $t->getPathname();
			clearstatcache( true, $d );
			if ( ! is_writable( dirname( $d ) ) ) { $ket[] = str_replace( $goc, '', $d ); }
		}
		return array_values( array_unique( $ket ) );
	}

	// ══════════════════════════════════════════════════════════════════════ cửa vào

	/** Đã đặt mã vào trang cứu hộ chưa. */
	private static function co_ma() { return '' !== (string) get_option( self::O_MA, '' ); }

	/**
	 * AI ĐƯỢC VÀO.
	 *
	 * Hai đường, vì đúng lúc sự cố thì đường nào cũng có thể đang hỏng:
	 *   · đang đăng nhập WordPress với quyền cập nhật plugin — đường thường ngày;
	 *   · hoặc gõ đúng mã cứu hộ — đường cho điện thoại, nơi wp-admin không mở nổi.
	 *
	 * 🔴 Chưa đặt mã thì CHỈ đường đăng nhập chạy. Không có mã mặc định, không có đường tắt —
	 *    trang này cài lại được mã nguồn lên máy chủ, mở toang nó là mở toang cả website.
	 */
	private static function duoc_vao( $ma_go ) {
		if ( is_user_logged_in() && current_user_can( 'update_plugins' ) ) { return true; }
		if ( ! self::co_ma() || '' === $ma_go ) { return false; }
		$khoa = self::O_SAI . md5( (string) self::ip_() );
		if ( (int) get_transient( $khoa ) >= 8 ) { return false; }   // gõ sai 8 lượt -> nghỉ 15 phút
		if ( wp_check_password( $ma_go, (string) get_option( self::O_MA, '' ) ) ) {
			delete_transient( $khoa );
			return true;
		}
		set_transient( $khoa, (int) get_transient( $khoa ) + 1, 900 );
		return false;
	}

	private static function ip_() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	}

	// ══════════════════════════════════════════════════════════════════════ trang

	public static function phuc_vu() {
		if ( ! isset( $_GET[ self::THAM_SO ] ) ) { return; }

		$ma_go  = isset( $_POST['ma'] ) ? (string) wp_unslash( $_POST['ma'] ) : '';
		$duoc   = self::duoc_vao( $ma_go );
		$bao    = '';
		$hong   = '';

		if ( $duoc && isset( $_POST['ha'] ) ) {
			/* Nonce chỉ dựng được khi đã qua cửa, nên nó không thay cho việc soát quyền — nó chặn
			   trang khác mượn phiên của người đang đăng nhập để bấm hộ (CSRF). */
			if ( ! is_user_logged_in() || check_admin_referer( 'vhch-ha' ) ) {
				$kq = self::ha_cap( (string) wp_unslash( $_POST['ha'] ) );
				if ( ! empty( $kq['ok'] ) ) {
					$bao = 'Đã hạ cấp ' . $kq['ma'] . ' về bản ' . $kq['ban'] . '. Mở lại trang của plugin đó để kiểm.';
				} else {
					$hong = (string) $kq['loi'];
				}
			}
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>Cứu hộ plugin</title>';
		echo '<style>body{font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;background:#f5f5f5;'
			. 'color:#0a0a0a;margin:0;padding:18px}.h{max-width:760px;margin:0 auto}'
			. '.c{background:#fff;border:1px solid #e5e5e5;border-radius:18px;padding:18px;margin:12px 0}'
			. 'h1{font-size:20px;margin:0 0 4px}.m{color:#737373;font-size:13px}'
			. 'table{width:100%;border-collapse:collapse;margin-top:8px}'
			. 'th,td{text-align:left;padding:8px 6px;border-bottom:1px solid #e5e5e5;font-size:13px}'
			. 'th{color:#737373;font-weight:600;font-size:11px;text-transform:uppercase}'
			. '.n{font-family:ui-monospace,monospace;font-variant-numeric:tabular-nums}'
			. 'button,input{font:inherit}input{padding:10px 12px;border:1px solid #e5e5e5;border-radius:12px;width:100%}'
			. 'button{padding:9px 14px;border:1px solid #0a0a0a;background:#0a0a0a;color:#fff;border-radius:12px;cursor:pointer}'
			. '.do{color:#e7000b}.ok{color:#047857}</style>';
		echo '<div class="h"><h1>Cứu hộ plugin</h1>';
		echo '<div class="m">Hạ cấp một plugin về bản đã chụp trước lượt cập nhật. Trang này chạy độc lập — '
			. 'không cần plugin nào khác còn sống.</div>';

		if ( '' !== $bao )  { echo '<div class="c ok">✔ ' . esc_html( $bao ) . '</div>'; }
		if ( '' !== $hong ) { echo '<div class="c do">✗ ' . esc_html( $hong ) . '</div>'; }

		if ( ! $duoc ) {
			echo '<div class="c"><form method="post">';
			if ( self::co_ma() ) {
				echo '<div class="m" style="margin-bottom:8px">Nhập mã cứu hộ, hoặc đăng nhập WordPress rồi mở lại trang này.</div>';
				echo '<input type="password" name="ma" autocomplete="off" placeholder="Mã cứu hộ">';
				echo '<div style="margin-top:10px"><button>Vào</button></div>';
				if ( '' !== $ma_go ) { echo '<div class="do" style="margin-top:8px">Mã không đúng.</div>'; }
			} else {
				echo '<div class="do">Chưa đặt mã cứu hộ.</div><div class="m">Vào wp-admin → Cứu hộ plugin để đặt, '
					. 'hoặc đăng nhập WordPress rồi mở lại trang này.</div>';
			}
			echo '</form></div></div>';
			exit;
		}

		$ds = self::ds_anh();
		echo '<div class="c"><b>Ảnh chụp đang giữ</b>';
		if ( ! $ds ) {
			echo '<div class="m" style="margin-top:6px">Chưa có ảnh nào. Ảnh được chụp TỰ ĐỘNG ngay trước mỗi '
				. 'lượt cập nhật plugin — nên bản đầu tiên sẽ xuất hiện sau lượt cập nhật kế tiếp.</div>';
		} else {
			echo '<table><tr><th>Plugin</th><th>Bản</th><th>Chụp lúc</th><th>Cỡ</th><th></th></tr>';
			foreach ( $ds as $a ) {
				echo '<tr><td><b>' . esc_html( $a['ma'] ) . '</b></td>'
					. '<td class="n">' . esc_html( $a['ban'] ) . '</td>'
					. '<td class="n">' . esc_html( $a['moc'] ) . '</td>'
					. '<td class="n">' . esc_html( $a['co'] ) . '</td>'
					. '<td style="text-align:right"><form method="post" onsubmit="return confirm('
					. '\'Hạ cấp ' . esc_attr( $a['ma'] ) . ' về bản ' . esc_attr( $a['ban'] ) . '?\')">';
				if ( is_user_logged_in() ) { wp_nonce_field( 'vhch-ha' ); }
				if ( ! is_user_logged_in() ) { echo '<input type="hidden" name="ma" value="' . esc_attr( $ma_go ) . '">'; }
				echo '<input type="hidden" name="ha" value="' . esc_attr( $a['tep'] ) . '">'
					. '<button>Hạ cấp</button></form></td></tr>';
			}
			echo '</table>';
		}
		echo '</div></div>';
		exit;
	}

	// ══════════════════════════════════════════════════════════════════════ đặt mã

	public static function menu() {
		add_menu_page( 'Cứu hộ plugin', 'Cứu hộ plugin', 'update_plugins', 'vhcp-cuu-ho',
			array( __CLASS__, 'man_admin' ), 'dashicons-sos', 81 );
	}

	public static function man_admin() {
		if ( ! current_user_can( 'update_plugins' ) ) { return; }
		if ( isset( $_POST['ma_moi'] ) && check_admin_referer( 'vhch-ma' ) ) {
			$m = trim( (string) wp_unslash( $_POST['ma_moi'] ) );
			if ( strlen( $m ) >= 6 ) {
				update_option( self::O_MA, wp_hash_password( $m ) );
				echo '<div class="notice notice-success"><p>Đã đặt mã cứu hộ.</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>Mã phải từ 6 ký tự trở lên.</p></div>';
			}
		}
		$url = home_url( '/?' . self::THAM_SO . '=1' );
		echo '<div class="wrap"><h1>Cứu hộ plugin</h1>';
		echo '<p>Trang cứu hộ: <a href="' . esc_url( $url ) . '" target="_blank"><code>' . esc_html( $url ) . '</code></a></p>';
		echo '<p>Ảnh chụp được tạo <b>tự động ngay trước mỗi lượt cập nhật plugin</b>, giữ 5 bản gần nhất mỗi plugin.</p>';
		echo '<h2>Mã vào từ điện thoại</h2>';
		echo '<p>Đặt mã để mở trang cứu hộ mà không cần đăng nhập wp-admin. '
			. ( self::co_ma() ? '<b>Đang có mã.</b> Gõ mã mới để đổi.' : '<b>Chưa đặt mã.</b>' ) . '</p>';
		echo '<form method="post"><input type="password" name="ma_moi" autocomplete="new-password" '
			. 'placeholder="Mã mới (≥ 6 ký tự)" style="width:280px">';
		wp_nonce_field( 'vhch-ma' );
		echo ' <button class="button button-primary">Lưu mã</button></form></div>';
	}
}

VHCH_CuuHo::init();

endif;
