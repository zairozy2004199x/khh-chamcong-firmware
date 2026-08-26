<?php
/**
 * TRANG /noi-bo/ — bảng tin nội bộ.
 *
 * 🔴 DÙNG CHUNG THẺ PHIÊN VỚI HỆ CHẤM CÔNG. Ai đăng nhập trạm chấm công thì vào thẳng đây, không
 *    phải nhập PIN lần hai. Cụ thể: đọc cookie của `VHCC_Web` rồi hỏi `VHCC_Auth::user_by_token`.
 *
 * ⚠️ KHÔNG có script nào — giống mọi trang khác của hệ. Bảng tin không cần JavaScript: đăng bài,
 *    bình luận, thả tim đều là một lượt POST rồi chuyển hướng. Đổi lại nó chạy được trên mọi máy,
 *    và thử được bằng bộ thử PHP.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Trang {

	const SLUG_MD = 'noi-bo';

	public static function slug() {
		$s = get_option( 'vhnb_slug' );
		$s = $s ? sanitize_title( $s ) : self::SLUG_MD;
		return $s ? $s : self::SLUG_MD;
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhnb', '1', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhnb_trang=1', 'top' );
		add_filter( 'query_vars', function ( $v ) { $v[] = 'vhnb_trang'; return $v; } );
		add_action( 'template_redirect', array( __CLASS__, 'hien_trang' ) );
	}

	/**
	 * Cửa vào của trang. Tách khỏi `phuc_vu()` vì `exit` nằm ở ĐÂY, không nằm trong đó.
	 *
	 * 🔴 `phuc_vu()` KHÔNG được `exit`. Có `exit` trong đó thì bộ thử gọi nó là bộ thử tự chết
	 *    giữa đường — nên toàn bộ phần vẽ trang sẽ không bao giờ có phép thử nào. Đúng cách
	 *    `VHCC_Web` làm: `maybe_render` gác cửa và exit, `phuc_vu` chỉ in ra.
	 */
	public static function hien_trang() {
		$la = ( (int) get_query_var( 'vhnb_trang' ) === 1 );
		if ( ! $la && isset( $_GET['vhnb'] ) ) { $la = true; }
		if ( ! $la ) { return; }
		nocache_headers();
		self::phuc_vu();
		exit;
	}

	/* ==================================================================== người dùng */

	/**
	 * Người đang đăng nhập — lấy từ thẻ phiên của hệ chấm công.
	 * Thiếu plugin chấm công thì trả null và trang nói thẳng ra, không trắng trang.
	 */
	public static function toi() {
		$tok = self::the_phien();
		if ( '' === $tok ) { return null; }
		/* ⚠️ Gác `method_exists` NGAY TẠI ĐÂY, dù `the_phien()` cũng đã hỏi. Gác ở hàm khác thì
		   hôm nào có người đổi `the_phien()` là lời gọi này hụt — mà gọi hụt một hàm tĩnh là
		   Fatal error, TRẮNG CẢ TRANG. Luật của `tools/test/kiem-goi-cheo.php`: gác phải nằm
		   CÙNG HÀM với lời gọi. */
		if ( ! method_exists( 'VHCC_Auth', 'user_by_token' ) ) { return null; }
		return VHCC_Auth::user_by_token( $tok );
	}

	/**
	 * Thẻ phiên đang mang trong cookie, hoặc chuỗi rỗng.
	 *
	 * ⚠️ `defined()` phải đứng MỘT MÌNH trước `constant()`. Viết
	 *    `if ( ! defined( X ) && ! constant( X ) )` là đúng lúc hằng KHÔNG tồn tại thì vẫn gọi
	 *    `constant()` — PHP 8 ném Error, cả trang trắng. Đây là nhánh chỉ chạy khi thiếu plugin
	 *    chấm công, tức là đúng lúc không ai ngồi thử.
	 */
	public static function the_phien() {
		if ( ! self::co_he_cham_cong() ) { return ''; }
		if ( ! defined( 'VHCC_Web::COOKIE' ) ) { return ''; }
		$c = constant( 'VHCC_Web::COOKIE' );
		if ( ! $c || empty( $_COOKIE[ $c ] ) ) { return ''; }
		return (string) $_COOKIE[ $c ];
	}

	public static function co_he_cham_cong() {
		return class_exists( 'VHCC_Web' ) && class_exists( 'VHCC_Auth' )
			&& method_exists( 'VHCC_Auth', 'user_by_token' );
	}

	/* ==================================================================== chữ ký biểu mẫu */

	/**
	 * Chữ ký chống giả mạo biểu mẫu, buộc vào chính thẻ phiên.
	 *
	 * 🔴 KHÔNG dùng `wp_nonce_field` được: nonce của WordPress buộc vào tài khoản WordPress, mà
	 *    240 người ở đây không có tài khoản WordPress nào cả — nonce sẽ tính theo id 0, ai cũng
	 *    ra một chuỗi giống nhau, tức là chẳng chặn được gì.
	 *
	 * ⚠️ Tự tính lấy chứ KHÔNG mượn `VHCC_Web::chu_ky` — hai plugin cài độc lập; mượn hàm bên kia
	 *    là đúng lúc bên kia đổi tên hàm thì bên này gãy.
	 */
	public static function chu_ky( $tok ) {
		return hash_hmac( 'sha256', 'vhnb|' . (string) $tok, wp_salt( 'nonce' ) );
	}

	/** Ô ẩn mang chữ ký — mọi biểu mẫu POST của trang này đều phải có. */
	public static function o_ky() {
		return '<input type="hidden" name="ky" value="' . esc_attr( self::chu_ky( self::the_phien() ) ) . '">';
	}

	private static function ky_dung() {
		$tok = self::the_phien();
		$gui = isset( $_POST['ky'] ) ? (string) wp_unslash( $_POST['ky'] ) : '';
		return ( '' !== $tok && '' !== $gui && hash_equals( self::chu_ky( $tok ), $gui ) );
	}

	/* ==================================================================== phục vụ */

	public static function phuc_vu() {
		$toi = self::toi();

		if ( ! empty( $_POST['viec'] ) && $toi ) {
			$bao = self::ky_dung()
				? self::lam_viec( sanitize_text_field( wp_unslash( $_POST['viec'] ) ), $toi )
				: array( 'loi' => 'Phiên đã hết hoặc biểu mẫu không hợp lệ. Tải lại trang rồi làm lại.' );
			set_transient( self::khoa_bao(), $bao, 120 );
			/* POST -> chuyển hướng -> GET: F5 không gửi lại bài, và giữ nguyên chỗ đang đứng.
			   Riêng lượt LẬP NHÓM thì nhảy thẳng vào nhóm vừa lập — lập xong mà bị ném về bảng
			   tin chung thì người ta phải tự đi tìm cái mình vừa tạo. */
			$di = ( ! empty( $bao['g'] ) ) ? add_query_arg( 'g', (int) $bao['g'], self::url() ) : self::url_hien();
			wp_safe_redirect( $di );
			return;
		}

		self::ve( $toi );
	}

	private static function khoa_bao() {
		return 'vhnb_bao_' . md5( self::the_phien() );
	}

	/**
	 * Địa chỉ hiện tại, KÈM chỗ đang đứng.
	 *
	 * ⚠️ Phải chở theo cả `g` (nhóm tự tạo) lẫn `nhom` (bộ phận). Thiếu `g` là bình luận xong bị
	 *    ném về bảng tin chung — mà bài vừa bình luận thì nằm trong nhóm, tức là biến mất.
	 */
	private static function url_hien() {
		$g = isset( $_POST['g_xem'] ) ? (int) $_POST['g_xem'] : ( isset( $_GET['g'] ) ? (int) $_GET['g'] : 0 );
		if ( $g > 0 ) { return add_query_arg( 'g', $g, self::url() ); }
		$nhom = isset( $_POST['nhom_xem'] ) ? sanitize_text_field( wp_unslash( $_POST['nhom_xem'] ) ) : '';
		if ( '' === $nhom && isset( $_GET['nhom'] ) ) {
			$nhom = sanitize_text_field( wp_unslash( $_GET['nhom'] ) );
		}
		return ( '' !== $nhom ) ? add_query_arg( 'nhom', $nhom, self::url() ) : self::url();
	}

	private static function lam_viec( $viec, $toi ) {
		$id = isset( $_POST['bai'] ) ? (int) $_POST['bai'] : 0;

		$g  = isset( $_POST['g_xem'] ) ? (int) $_POST['g_xem'] : 0;

		if ( 'dang' === $viec ) {
			$r = VHNB_Bai::dang( $toi,
				isset( $_POST['noi_dung'] ) ? wp_unslash( $_POST['noi_dung'] ) : '',
				isset( $_POST['nhom'] ) ? wp_unslash( $_POST['nhom'] ) : '', $g );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => 'Đã đăng.' );
		}
		if ( 'lap_nhom' === $viec ) {
			$r = VHNB_Nhom::lap( $toi,
				isset( $_POST['ten'] ) ? wp_unslash( $_POST['ten'] ) : '',
				isset( $_POST['mo_ta'] ) ? wp_unslash( $_POST['mo_ta'] ) : '' );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] )
				: array( 'xong' => 'Đã lập nhóm. Thêm người vào bằng Mã NV ở cột bên phải.',
					'g' => (int) $r['id'] );
		}
		if ( 'moi_tv' === $viec ) {
			$r = VHNB_Nhom::moi( $toi, $g, isset( $_POST['ma_nv'] ) ? wp_unslash( $_POST['ma_nv'] ) : '' );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] )
				: array( 'xong' => 'Đã thêm ' . ( '' !== $r['hoTen'] ? $r['hoTen'] . ' (' . $r['maNV'] . ')' : $r['maNV'] ) . ' vào nhóm.' );
		}
		if ( 'bo_tv' === $viec ) {
			$r = VHNB_Nhom::bo( $toi, $g, isset( $_POST['ma_nv'] ) ? wp_unslash( $_POST['ma_nv'] ) : '' );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => 'Đã bỏ khỏi nhóm.' );
		}
		if ( 'xoa_nhom' === $viec ) {
			$r = VHNB_Nhom::xoa( $toi, $g );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => 'Đã xoá nhóm.' );
		}
		if ( 'binh_luan' === $viec ) {
			$r = VHNB_Bai::binh_luan( $toi, $id,
				isset( $_POST['noi_dung'] ) ? wp_unslash( $_POST['noi_dung'] ) : '' );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array();
		}
		if ( 'tim' === $viec ) {
			$r = VHNB_Bai::tim( $toi, $id );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array();
		}
		if ( 'xoa' === $viec ) {
			$r = VHNB_Bai::xoa( $toi, $id );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => 'Đã xoá bài.' );
		}
		if ( 'ghim' === $viec ) {
			$r = VHNB_Bai::ghim( $toi, $id, ! empty( $_POST['bat'] ) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array();
		}
		return array( 'loi' => 'Không biết việc "' . $viec . '".' );
	}

	/* ==================================================================== vẽ */

	public static function ve( $toi ) {
		$nhom = isset( $_GET['nhom'] ) ? sanitize_text_field( wp_unslash( $_GET['nhom'] ) ) : '';
		$g    = isset( $_GET['g'] ) ? (int) $_GET['g'] : 0;

		echo self::dau();
		echo '<header><div class="bo">'
			. '<a class="hieu" href="' . esc_url( self::url() ) . '"><b>K&amp;H</b> Nội bộ</a>';
		if ( $toi ) {
			echo '<span class="ai">' . self::anh_dai_dien( $toi['name'], 30 )
				. '<span class="ai-ten">' . esc_html( $toi['name'] ) . '</span></span>';
			/* ⚠️ DÒ TỪNG HÀM, KHÔNG DÒ MỖI TÊN LỚP: lớp CÓ mà hàm KHÔNG là trắng cả trang. */
			if ( class_exists( 'VHTC_Trang' ) && method_exists( 'VHTC_Trang', 'url' ) ) {
				echo '<a class="nut" href="' . esc_url( VHTC_Trang::url() ) . '">🏠 Cổng K&amp;H</a>';
			}
			if ( class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'url' ) ) {
				echo '<a class="nut" href="' . esc_url( VHCC_Web::url() ) . '">🕐 Chấm công</a>';
			}
		}
		echo '</div></header>';

		if ( ! self::co_he_cham_cong() ) {
			echo '<div class="bo"><div class="bao loi"><b>Chưa cài plugin Chấm Công.</b> Trang nội bộ '
				. 'dùng chung mã PIN với hệ chấm công, nên phải có plugin đó thì mới đăng nhập được.</div>';
			self::dong_trang();
			return;
		}
		if ( ! $toi ) {
			echo '<div class="bo"><div class="the" style="max-width:460px;margin:40px auto">'
				. '<h2>Nội bộ K&amp;H</h2>'
				. '<p class="mo">Đăng nhập bằng <b>mã PIN chấm công</b> ở trang chấm công, rồi quay '
				. 'lại đây — hai trang dùng chung một phiên, không phải nhập PIN hai lần.</p>'
				. '<p><a class="nut chinh" href="' . esc_url( VHCC_Web::url() ) . '">Tới trang đăng nhập</a></p>'
				. '</div>';
			self::dong_trang();
			return;
		}

		/* Nhóm tự tạo: kiểm quyền vào TRƯỚC khi vẽ bất cứ thứ gì của nó. */
		$n_hien = null;
		if ( $g > 0 ) {
			$n_hien = VHNB_Nhom::mot( $g );
			if ( ! $n_hien || ! VHNB_Nhom::duoc_vao( $toi, $g ) ) {
				/* 🔴 Nói ĐÚNG MỘT CÂU cho cả hai trường hợp "nhóm không có" và "không được vào".
				   Nói khác nhau là ai cũng dò được nhóm nào đang tồn tại bằng cách đổi số trên
				   thanh địa chỉ — mà tên nhóm cũng là thông tin. */
				$g = 0; $n_hien = null;
				echo '<div class="bo"><div class="bao loi">Nhóm này không tồn tại, hoặc anh/chị '
					. 'không ở trong nhóm ấy.</div></div>';
			}
		}

		echo '<div class="bo khung">';

		/* ---------------- cột TRÁI: điều hướng ---------------- */
		self::cot_trai( $toi, $nhom, $g );

		/* ---------------- cột GIỮA: soạn bài + bảng tin ---------------- */
		echo '<main class="giua">';
		$bao = get_transient( self::khoa_bao() );
		if ( is_array( $bao ) ) {
			delete_transient( self::khoa_bao() );
			if ( ! empty( $bao['loi'] ) )  { echo '<div class="bao loi">' . esc_html( $bao['loi'] ) . '</div>'; }
			if ( ! empty( $bao['xong'] ) ) { echo '<div class="bao ok">' . esc_html( $bao['xong'] ) . '</div>'; }
		}
		if ( $n_hien ) {
			echo '<div class="the dau-nhom"><h2>' . esc_html( $n_hien['ten'] ) . '</h2>'
				. ( '' !== trim( (string) $n_hien['mo_ta'] )
					? '<p class="mo">' . esc_html( $n_hien['mo_ta'] ) . '</p>' : '' )
				. '<p class="mo">🔒 Nhóm riêng · <b>' . (int) $n_hien['so_tv'] . '</b> thành viên · '
				. 'người lập: <b>' . esc_html( $n_hien['ho_ten_tao'] ) . '</b></p></div>';
		}
		self::o_dang( $toi, $nhom, $g );
		self::bang_tin( $toi, $nhom, $g );
		echo '</main>';

		/* ---------------- cột PHẢI: thành viên / đường tắt ---------------- */
		self::cot_phai( $toi, $n_hien );

		self::dong_trang();
	}

	/**
	 * ĐÓNG TRANG — đóng nốt `$so_div` khung đang mở, in chân trang công ty, rồi đóng thẻ.
	 *
	 * 🔴 CHÂN TRANG PHẢI NẰM TRONG `.bo`. Anh Thắng 26/08: *"bị lệch"* — chân trang in ra SAU khi
	 *    đã đóng `.bo` thì nó dính sát mép trái màn trong khi cả trang còn lại thụt vào.
	 *
	 * ⚠️ Gác `method_exists` cùng chỗ với lời gọi: `VHCC_Cty` nằm ở plugin KHÁC (chấm công), gỡ
	 *    plugin ấy ra là mất lớp. Thiếu chân trang là thiếu một đoạn chữ, không phải trắng trang.
	 */
	private static function dong_trang( $so_div = 1 ) {
		echo str_repeat( '</div>', max( 0, (int) $so_div ) );
		if ( class_exists( 'VHCC_Cty' ) && method_exists( 'VHCC_Cty', 'html' ) ) {
			$h = VHCC_Cty::html();
			if ( '' !== $h ) { echo '<div class="bo">' . $h . '</div>'; }
		}
		echo '</body></html>';
	}

	/**
	 * ẢNH ĐẠI DIỆN — vòng tròn chữ cái đầu, màu suy TỪ CHÍNH CÁI TÊN.
	 *
	 * 🔴 KHÔNG lưu ảnh thật. 240 người tải ảnh lên là 240 tệp phải trông coi, phải giới hạn dung
	 *    lượng, phải lo ai đổi ảnh của ai — cho một thứ chỉ để nhận mặt nhau trong bảng tin.
	 *    Vòng tròn chữ cái làm đúng việc ấy, không tốn gì.
	 *
	 * ⚠️ Màu suy từ tên bằng `crc32`, KHÔNG random: cùng một người thì mọi lần tải trang phải ra
	 *    cùng một màu, không thì màu chẳng giúp nhận ra ai cả.
	 */
	public static function anh_dai_dien( $ten, $co = 40 ) {
		$ten = trim( (string) $ten );
		$chu = ( '' !== $ten ) ? mb_strtoupper( mb_substr( $ten, 0, 1, 'UTF-8' ), 'UTF-8' ) : '?';
		$mau = array( '#2563eb', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0891b2', '#ca8a04', '#dc2626' );
		$m   = $mau[ crc32( $ten ) % count( $mau ) ];
		$co  = (int) $co;
		return '<span class="add" style="width:' . $co . 'px;height:' . $co . 'px;background:' . $m
			. ';font-size:' . max( 11, (int) round( $co * 0.42 ) ) . 'px">' . esc_html( $chu ) . '</span>';
	}

	/** Bộ phận = bộ phận của hệ chấm công. Không bịa danh sách riêng — hai nơi sẽ lệch nhau. */
	public static function ds_nhom() {
		if ( class_exists( 'VHCC_Luong' ) && defined( 'VHCC_Luong::BP_DS' ) ) {
			return (array) constant( 'VHCC_Luong::BP_DS' );
		}
		return array();
	}

	/* ==================================================================== cột trái */

	private static function cot_trai( $toi, $nhom, $g ) {
		echo '<aside class="trai"><nav class="dh">';
		echo '<a class="mi' . ( '' === $nhom && 0 === $g ? ' on' : '' ) . '" href="'
			. esc_url( self::url() ) . '"><span class="ic">🏠</span>Bảng tin</a>';

		$ds = self::ds_nhom();
		if ( $ds ) {
			echo '<div class="mi-nhan">Bộ phận</div>';
			foreach ( $ds as $x ) {
				echo '<a class="mi' . ( $x === $nhom ? ' on' : '' ) . '" href="'
					. esc_url( add_query_arg( 'nhom', $x, self::url() ) ) . '">'
					. '<span class="ic">🏢</span>' . esc_html( $x ) . '</a>';
			}
		}

		/* 🔴 CHỈ LIỆT KÊ NHÓM MÌNH ĐANG Ở TRONG. Liệt kê hết rồi chặn lúc bấm vào là để lộ tên
		   mọi nhóm trong công ty — mà tên nhóm cũng là thông tin ("Nhóm xử lý nghỉ việc A"). */
		$ds_g = VHNB_Nhom::cua_toi( $toi );
		echo '<div class="mi-nhan">Nhóm của tôi' . ( $ds_g ? ' (' . count( $ds_g ) . ')' : '' ) . '</div>';
		foreach ( $ds_g as $n ) {
			echo '<a class="mi' . ( (int) $n['id'] === $g ? ' on' : '' ) . '" href="'
				. esc_url( add_query_arg( 'g', (int) $n['id'], self::url() ) ) . '">'
				. '<span class="ic">🔒</span>' . esc_html( $n['ten'] )
				. '<span class="dem">' . (int) $n['so_tv'] . '</span></a>';
		}
		if ( ! $ds_g ) {
			echo '<p class="mo" style="margin:4px 8px 8px;font-size:12px">Chưa có nhóm nào. '
				. 'Lập một nhóm rồi thêm người bằng Mã NV.</p>';
		}

		/* Ô lập nhóm: thu gọn sẵn để cột trái không bị một biểu mẫu chiếm chỗ mỗi ngày. */
		echo '<details class="lap"><summary>➕ Lập nhóm mới</summary>';
		echo '<form method="post">' . self::o_ky()
			. '<input type="hidden" name="viec" value="lap_nhom">'
			. '<input name="ten" required maxlength="' . VHNB_Nhom::TEN_TOI_DA . '" placeholder="Tên nhóm">'
			. '<input name="mo_ta" maxlength="255" placeholder="Nhóm này để làm gì (không bắt buộc)">'
			. '<button class="chinh">Lập nhóm</button></form>';
		echo '<p class="mo" style="font-size:11.5px;margin:6px 0 0">Lập xong, anh/chị là <b>chủ '
			. 'nhóm</b>: thêm người vào bằng <b>Mã NV</b>. Chỉ thành viên đọc được bài trong nhóm.</p>';
		echo '</details>';
		echo '</nav></aside>';
	}

	/* ==================================================================== cột phải */

	private static function cot_phai( $toi, $n ) {
		echo '<aside class="phai">';

		if ( $n ) {
			$ds_tv  = VHNB_Nhom::ds_thanh_vien( (int) $n['id'] );
			$la_chu = VHNB_Nhom::la_chu( $toi, $n );
			echo '<div class="the"><h3>Thành viên (' . count( $ds_tv ) . ')</h3>';
			foreach ( $ds_tv as $tv ) {
				$ten = ( '' !== trim( (string) $tv['ho_ten'] ) ) ? $tv['ho_ten'] : $tv['ma_nv'];
				echo '<div class="tv">' . self::anh_dai_dien( $ten, 30 )
					. '<span class="tv-ten">' . esc_html( $ten )
					. ( 'chu' === $tv['vai'] ? ' <span class="duoi">chủ nhóm</span>' : '' )
					. '<br><span class="mo">' . esc_html( $tv['ma_nv'] ) . '</span></span>';
				/* Chủ nhóm bỏ được người khác; ai cũng tự rời được. Chủ nhóm KHÔNG tự rời —
				   xem chú thích ở `VHNB_Nhom::bo()`. */
				$tu_minh = ( 0 === strcasecmp( (string) $tv['ma_nv'],
					trim( (string) ( isset( $toi['ma_nv'] ) ? $toi['ma_nv'] : '' ) ) ) );
				if ( ( $la_chu || $tu_minh ) && 'chu' !== $tv['vai'] ) {
					echo '<form method="post" style="margin:0">' . self::o_ky()
						. '<input type="hidden" name="viec" value="bo_tv">'
						. '<input type="hidden" name="g_xem" value="' . (int) $n['id'] . '">'
						. '<input type="hidden" name="ma_nv" value="' . esc_attr( $tv['ma_nv'] ) . '">'
						. '<button class="nho nguy">' . ( $tu_minh ? 'Rời' : 'Bỏ' ) . '</button></form>';
				}
				echo '</div>';
			}
			if ( $la_chu ) {
				echo '<form method="post" class="moi">' . self::o_ky()
					. '<input type="hidden" name="viec" value="moi_tv">'
					. '<input type="hidden" name="g_xem" value="' . (int) $n['id'] . '">'
					. '<input name="ma_nv" required placeholder="Mã NV cần thêm">'
					. '<button class="chinh">Thêm</button></form>';
				/* ⚠️ Nói rõ là MÃ, không phải tên: tên người Việt trùng rất nhiều, mời nhầm một
				   người trùng tên vào nhóm bàn lương là chuyện không rút lại được. */
				echo '<p class="mo" style="font-size:11.5px">Thêm bằng <b>Mã NV</b> (không phải tên) '
					. '— tên trùng nhau nhiều, mời nhầm người thì không rút lại được.</p>';
				echo '<form method="post" style="margin-top:8px">' . self::o_ky()
					. '<input type="hidden" name="viec" value="xoa_nhom">'
					. '<input type="hidden" name="g_xem" value="' . (int) $n['id'] . '">'
					. '<button class="nho nguy">🗑 Xoá nhóm này</button></form>';
			}
			echo '</div>';
		}

		echo '<div class="the"><h3>Đang dùng</h3>';
		echo '<div class="tv">' . self::anh_dai_dien( $toi['name'], 34 )
			. '<span class="tv-ten">' . esc_html( $toi['name'] )
			. '<br><span class="mo">' . esc_html( VHNB_Bai::la_admin( $toi ) ? 'Admin' : (string) $toi['role'] )
			. ( '' !== trim( (string) $toi['ma_nv'] ) ? ' · ' . esc_html( $toi['ma_nv'] ) : '' )
			. '</span></span></div>';
		if ( '' === trim( (string) $toi['ma_nv'] ) ) {
			/* Mã rỗng chặn thả tim VÀ chặn lập nhóm — nói ra một lần ở đây thay vì để họ đâm vào
			   hai câu từ chối khác nhau ở hai chỗ khác nhau. */
			echo '<p class="mo" style="font-size:11.5px">⚠️ Tài khoản chưa có <b>Mã NV</b> nên chưa '
				. 'thả tim và chưa lập nhóm được — nhờ Admin khai giúp ở hồ sơ.</p>';
		}
		echo '</div>';

		echo '<div class="the"><h3>Nhớ giúp</h3><ul class="nq">'
			. '<li>Bài ở <b>Bảng tin</b> và <b>Bộ phận</b> thì cả công ty đọc được.</li>'
			. '<li>Bài trong <b>nhóm riêng</b> chỉ thành viên đọc — kể cả Admin cũng không.</li>'
			. '<li>Xoá bài thì mất luôn bình luận của người khác trong bài ấy.</li>'
			. '</ul></div>';
		echo '</aside>';
	}

	/* ==================================================================== soạn bài */

	private static function o_dang( $toi, $nhom, $g ) {
		echo '<div class="the soan">';
		echo '<form method="post">' . self::o_ky();
		echo '<input type="hidden" name="viec" value="dang">';
		echo '<input type="hidden" name="nhom_xem" value="' . esc_attr( $nhom ) . '">';
		echo '<input type="hidden" name="g_xem" value="' . (int) $g . '">';
		echo '<div class="soan-tren">' . self::anh_dai_dien( $toi['name'], 40 )
			. '<textarea name="noi_dung" rows="2" maxlength="' . VHNB_Bai::DAI_TOI_DA . '" '
			. 'placeholder="' . esc_attr( $toi['name'] . ' ơi, có gì mới?' ) . '"></textarea></div>';
		echo '<div class="soan-duoi">';
		if ( $g > 0 ) {
			/* Đang trong một nhóm thì KHÔNG cho chọn nơi đăng: đăng vào nhóm nào là do đang đứng
			   ở nhóm nào. Bày thêm ô chọn ở đây là mở đường đăng nhầm ra ngoài. */
			echo '<span class="mo">Đăng vào <b>nhóm này</b> — chỉ thành viên đọc được.</span>';
		} else {
			echo '<label for="nb_nhom" class="mo">Đăng vào</label><select id="nb_nhom" name="nhom">';
			echo '<option value="">Toàn công ty</option>';
			foreach ( self::ds_nhom() as $x ) {
				echo '<option value="' . esc_attr( $x ) . '"' . ( $x === $nhom ? ' selected' : '' ) . '>'
					. esc_html( $x ) . '</option>';
			}
			echo '</select>';
		}
		echo '<button class="chinh">Đăng</button>';
		echo '</div></form></div>';
	}

	/* ==================================================================== bảng tin */

	private static function bang_tin( $toi, $nhom, $g ) {
		$trang = isset( $_GET['tr'] ) ? max( 1, (int) $_GET['tr'] ) : 1;
		$ds    = VHNB_Bai::bang_tin( $nhom, $trang, $g );
		if ( ! $ds ) {
			echo '<div class="the"><p class="mo">Chưa có bài nào'
				. ( $g > 0 ? ' trong nhóm này' : ( '' !== $nhom ? ' ở ' . esc_html( $nhom ) : '' ) )
				. '. Đăng bài đầu tiên đi.</p></div>';
			return;
		}
		$tim = VHNB_Bai::da_tim( $toi, $ds );

		foreach ( $ds as $b ) {
			$id = (int) $b['id'];
			echo '<div class="the bai">';
			echo '<div class="dau-bai">' . self::anh_dai_dien( $b['ho_ten'], 40 ) . '<span class="db-chu">';
			echo '<b>' . esc_html( $b['ho_ten'] ) . '</b>';
			if ( '' !== trim( (string) $b['vai_tro'] ) ) {
				echo ' <span class="duoi">' . esc_html( $b['vai_tro'] ) . '</span>';
			}
			if ( '' !== trim( (string) $b['nhom'] ) ) {
				echo ' <span class="duoi nhom">' . esc_html( $b['nhom'] ) . '</span>';
			}
			if ( $b['ghim'] ) { echo ' <span class="duoi ghim">📌 ghim</span>'; }
			echo '<br><span class="mo">' . esc_html( VHNB_Bai::bao_lau( $b['tao_luc'] ) ) . '</span>';
			echo '</span></div>';

			/* `esc_html` rồi `nl2br`: thoát TRƯỚC, xuống dòng SAU. Làm ngược lại là mấy thẻ <br>
			   vừa chèn cũng bị thoát thành chữ. */
			echo '<div class="nd">' . nl2br( esc_html( (string) $b['noi_dung'] ) ) . '</div>';

			$o_lo = '<input type="hidden" name="nhom_xem" value="' . esc_attr( $nhom ) . '">'
				. '<input type="hidden" name="g_xem" value="' . (int) $g . '">';

			echo '<div class="chan">';
			echo '<form method="post">' . self::o_ky()
				. '<input type="hidden" name="viec" value="tim">'
				. '<input type="hidden" name="bai" value="' . $id . '">' . $o_lo
				. '<button class="nho' . ( isset( $tim[ $id ] ) ? ' da' : '' ) . '">'
				. ( isset( $tim[ $id ] ) ? '❤️' : '🤍' ) . ' ' . (int) $b['so_tim'] . '</button></form>';
			echo '<span class="mo">💬 ' . (int) $b['so_bl'] . '</span>';
			if ( VHNB_Bai::la_admin( $toi ) ) {
				echo '<form method="post">' . self::o_ky()
					. '<input type="hidden" name="viec" value="ghim">'
					. '<input type="hidden" name="bai" value="' . $id . '">' . $o_lo
					. ( $b['ghim'] ? '' : '<input type="hidden" name="bat" value="1">' )
					. '<button class="nho">' . ( $b['ghim'] ? 'Bỏ ghim' : '📌 Ghim' ) . '</button></form>';
			}
			if ( VHNB_Bai::duoc_xoa( $toi, $b ) ) {
				echo '<form method="post">' . self::o_ky()
					. '<input type="hidden" name="viec" value="xoa">'
					. '<input type="hidden" name="bai" value="' . $id . '">' . $o_lo
					. '<button class="nho nguy">Xoá</button></form>';
			}
			echo '</div>';

			foreach ( VHNB_Bai::ds_binh_luan( $id ) as $c ) {
				echo '<div class="bl">' . self::anh_dai_dien( $c['ho_ten'], 28 ) . '<span class="bl-chu">'
					. '<b>' . esc_html( $c['ho_ten'] ) . '</b> '
					. '<span class="mo">' . esc_html( VHNB_Bai::bao_lau( $c['tao_luc'] ) ) . '</span><br>'
					. nl2br( esc_html( (string) $c['noi_dung'] ) ) . '</span></div>';
			}
			echo '<form method="post" class="o-bl">' . self::o_ky()
				. '<input type="hidden" name="viec" value="binh_luan">'
				. '<input type="hidden" name="bai" value="' . $id . '">' . $o_lo
				. self::anh_dai_dien( $toi['name'], 28 )
				. '<input name="noi_dung" placeholder="Viết bình luận…" maxlength="' . VHNB_Bai::BL_TOI_DA . '">'
				. '<button>Gửi</button></form>';
			echo '</div>';
		}

		/* Phân trang: chỉ hiện nút khi có đủ bài cho trang sau — nút dẫn tới trang trống là nói dối. */
		echo '<div class="the thanh">';
		$giu = array( 'nhom' => $nhom, 'g' => $g ? $g : null );
		if ( $trang > 1 ) {
			echo '<a class="nut" href="' . esc_url( add_query_arg(
				array_merge( $giu, array( 'tr' => $trang - 1 ) ), self::url() ) ) . '">← Mới hơn</a>';
		}
		if ( count( $ds ) >= VHNB_Bai::MOI_TRANG ) {
			echo '<a class="nut" href="' . esc_url( add_query_arg(
				array_merge( $giu, array( 'tr' => $trang + 1 ) ), self::url() ) ) . '">Cũ hơn →</a>';
		}
		echo '</div>';
	}

	/**
	 * ĐẦU TRANG + toàn bộ kiểu chữ.
	 *
	 * 🔴 BỐ CỤC BA CỘT, không phải một dải hẹp giữa màn.
	 *    Màn rộng: trái 260px (điều hướng) · giữa co giãn (bảng tin) · phải 300px (thành viên).
	 *    Điện thoại: về MỘT cột, cột trái thành thanh cuốn ngang ở trên, cột phải xuống dưới cùng
	 *    — chứ không giấu đi: danh sách thành viên và ô mời người là thứ người ta cần nhất khi
	 *    vừa lập nhóm xong, mà lập nhóm thì hay làm trên điện thoại.
	 *
	 * ⚠️ `minmax(0,1fr)` chứ KHÔNG phải `1fr` cho cột giữa. `1fr` là `minmax(auto,1fr)`, mà `auto`
	 *    lấy theo bề rộng nội dung — một người dán cái link dài không dấu cách vào bài là cột giữa
	 *    phình ra, đẩy cột phải rơi khỏi màn. `minmax(0,…)` cho phép cột co nhỏ hơn nội dung, rồi
	 *    `word-break` trong `.nd` lo phần xuống dòng.
	 */
	private static function dau() {
		return '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<meta name="robots" content="noindex, nofollow"><title>Nội bộ K&amp;H</title><style>'
			. ':root{--nen:#f0f2f5;--the:#fff;--vien:#dfe3e8;--chu:#0f172a;--mo:#64748b;'
			. '--xanh:#2563eb;--do:#dc2626;--luc:#16a34a;--ro:#e4e6eb}'
			. '*{box-sizing:border-box}body{margin:0;font:15px/1.5 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;'
			. 'background:var(--nen);color:var(--chu)}'
			. '.bo{max-width:1180px;margin:0 auto;padding:16px}'
			. 'header{background:var(--the);border-bottom:1px solid var(--vien);position:sticky;top:0;z-index:5}'
			. 'header .bo{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 16px;max-width:1180px}'
			. '.hieu{flex:1;font-size:17px;font-weight:700;text-decoration:none;color:var(--chu)}'
			. '.hieu b{color:var(--xanh)}'
			. '.ai{display:flex;align-items:center;gap:7px}'
			. '.ai-ten{font-weight:600;font-size:14px}'

			/* ---------- khung ba cột ---------- */
			. '.khung{display:grid;grid-template-columns:260px minmax(0,1fr) 300px;gap:16px;align-items:start}'
			/* Hai cột bên dính theo khi cuốn — bảng tin dài, mà điều hướng thì luôn cần trong tầm mắt. */
			. '.trai,.phai{position:sticky;top:70px}'
			. '.giua{min-width:0}'

			/* ---------- cột trái: điều hướng ---------- */
			. '.dh{display:flex;flex-direction:column;gap:2px}'
			. '.mi{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;'
			. 'text-decoration:none;color:var(--chu);font-weight:600;font-size:14px}'
			. '.mi:hover{background:var(--ro)}'
			. '.mi.on{background:#e0e7ff;color:#1e3a8a}'
			. '.ic{width:24px;text-align:center;font-size:17px;flex:none}'
			. '.mi-nhan{font-size:12px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;'
			. 'color:var(--mo);margin:14px 0 4px;padding:0 10px}'
			. '.dem{margin-left:auto;background:var(--ro);color:var(--mo);border-radius:10px;'
			. 'padding:1px 8px;font-size:12px;font-weight:700}'
			. '.lap{margin-top:8px;background:var(--the);border:1px solid var(--vien);border-radius:10px;'
			. 'padding:8px 10px}'
			. '.lap summary{cursor:pointer;font-weight:600;font-size:14px}'
			. '.lap input,.lap textarea{width:100%;margin:6px 0}'

			/* ---------- ảnh đại diện ---------- */
			. '.add{display:inline-flex;align-items:center;justify-content:center;border-radius:50%;'
			. 'color:#fff;font-weight:700;flex:none;line-height:1;user-select:none}'

			/* ---------- thẻ chung ---------- */
			. '.the{background:var(--the);border:1px solid var(--vien);border-radius:10px;padding:14px;margin:0 0 14px}'
			. '.the h2{font-size:17px;margin:0 0 6px}.the h3{font-size:14px;margin:0 0 8px}'
			. '.mo{color:var(--mo);font-size:13px}'
			. 'label{display:block;font-size:13px;color:var(--mo);margin:0 0 3px}'
			. 'input,select,textarea{font:inherit;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;'
			. 'background:#fff;color:var(--chu);max-width:100%}'
			. 'textarea{resize:vertical}'
			. '.hang{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}'
			. 'button{font:inherit;font-weight:600;padding:8px 14px;border-radius:8px;border:1px solid #cbd5e1;'
			. 'background:#fff;color:var(--chu);cursor:pointer}'
			. 'button.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			. 'button.nho{padding:5px 10px;font-size:13px}'
			. 'button.nguy{color:var(--do);border-color:#fecaca}'
			. 'button.nho.da{background:#dbeafe;border-color:#93c5fd;color:#1d4ed8}'
			. '.nut{display:inline-block;font-size:14px;font-weight:600;padding:7px 12px;border-radius:8px;'
			. 'border:1px solid #cbd5e1;background:#fff;color:var(--chu);text-decoration:none}'
			. '.nut.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			. '.thanh{display:flex;gap:8px;flex-wrap:wrap;padding:10px}'
			. '.bao{border-radius:9px;padding:11px 13px;margin:0 0 12px;border:1px solid}'
			. '.bao.ok{background:#f0fdf4;border-color:#bbf7d0}.bao.loi{background:#fef2f2;border-color:#fecaca}'
			. '.dau-nhom{border-left:4px solid var(--luc)}'

			/* ---------- ô soạn bài ---------- */
			. '.soan-tren{display:flex;align-items:center;gap:10px}'
			/* Ô soạn hình viên thuốc, bấm vào mới bung ra — giống chỗ "Bạn đang nghĩ gì?". */
			. '.soan-tren textarea{flex:1;min-width:0;border-radius:20px;background:var(--ro);'
			. 'border-color:transparent;padding:10px 14px}'
			. '.soan-tren textarea:focus{background:#fff;border-color:#cbd5e1;outline:none}'
			. '.soan-duoi{display:flex;gap:8px;flex-wrap:wrap;align-items:center;'
			. 'border-top:1px solid var(--vien);margin-top:10px;padding-top:10px}'

			/* ---------- thẻ bài ---------- */
			. '.bai{padding:12px 14px}'
			. '.dau-bai{display:flex;align-items:center;gap:8px;flex-wrap:wrap}'
			. '.db-chu{display:flex;flex-direction:column;line-height:1.3;min-width:0}'
			. '.dau-bai .luc{margin-left:auto;font-size:12px;color:var(--mo)}'
			. '.duoi{background:#e0e7ff;color:#3730a3;border-radius:4px;padding:0 6px;font-size:11px;font-weight:600}'
			. '.duoi.nhom{background:#dcfce7;color:#166534}.duoi.ghim{background:#fef3c7;color:#92400e}'
			. '.nd{margin:10px 0;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere}'
			. '.chan{display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--vien);'
			. 'padding-top:8px;align-items:center}'
			. '.bl{display:flex;gap:8px;align-items:flex-start;background:#f0f2f5;border-radius:14px;'
			. 'padding:8px 12px;margin-top:6px;font-size:14px;word-break:break-word}'
			. '.bl-chu{min-width:0;overflow-wrap:anywhere}'
			. '.o-bl{display:flex;gap:8px;margin-top:8px}'
			. '.o-bl input{flex:1;min-width:0;border-radius:18px;background:var(--ro);border-color:transparent}'

			/* ---------- cột phải: thành viên ---------- */
			. '.tv{display:flex;align-items:center;gap:8px;padding:5px 0}'
			. '.tv-ten{min-width:0;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
			. '.tv form{margin-left:auto}'
			. '.moi{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;'
			. 'border-top:1px solid var(--vien);padding-top:10px}'
			. '.moi input{flex:1;min-width:120px}'
			. '.nq{margin:0;padding-left:18px;font-size:13px;color:var(--mo);line-height:1.7}'

			/* ---------- màn hẹp ---------- */
			/* 1024px: bỏ cột phải xuống DƯỚI cột giữa (vẫn còn, chỉ đổi chỗ), giữ cột trái. */
			. '@media(max-width:1024px){.khung{grid-template-columns:230px minmax(0,1fr)}'
			. '.phai{grid-column:2;position:static}}'
			/* 760px: một cột. Điều hướng thành thanh cuốn ngang dính trên đầu. */
			. '@media(max-width:760px){.khung{grid-template-columns:minmax(0,1fr);gap:12px}'
			. '.phai{grid-column:1}.trai{position:static}'
			. '.dh{flex-direction:row;overflow-x:auto;gap:6px;padding-bottom:4px}'
			. '.mi{flex:none;white-space:nowrap;background:var(--the);border:1px solid var(--vien)}'
			. '.mi-nhan{display:none}.dem{background:transparent}}'
			/* Điện thoại: ô nhập đủ 16px, kẻo iPhone tự phóng to cả trang mỗi lần bấm vào ô. */
			. '@media(max-width:640px){.bo{padding:10px}input,select,textarea{font-size:16px}}'
			/* Chân trang công ty mang bộ kiểu chữ riêng, tiền tố `cty-`. Ghép vào đây thay vì in
			   thẻ <style> thứ hai giữa trang. */
			. ( ( class_exists( 'VHCC_Cty' ) && method_exists( 'VHCC_Cty', 'css' ) )
				? VHCC_Cty::css() : '' )
			. '</style></head><body>';
	}
}
