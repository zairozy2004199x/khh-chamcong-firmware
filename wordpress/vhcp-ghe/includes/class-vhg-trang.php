<?php
/**
 * TRANG NGOÀI `/ghe` — bản thay cho dashboard "POSH massage" của Apps Script.
 *
 * =============================================================================================
 * KHÁC HẲN TRANG `/cham-cong`: TRANG NÀY TỰ CHỨA
 * =============================================================================================
 * Trang chấm công lấy giao diện thẳng từ Apps Script để khỏi tồn tại "bản chép" lệch với bản
 * gốc. Trang này KHÔNG làm vậy, cố ý: cả hệ thống ghế đã rời hẳn Google, nên đi vòng qua Apps
 * Script là dựng lại đúng cái phụ thuộc vừa gỡ. Giao diện nằm trong plugin, dữ liệu lấy từ
 * MySQL, không gọi ra ngoài lượt nào.
 *
 * =============================================================================================
 * BA LUẬT CỦA MÀN NÀY
 * =============================================================================================
 * 1. KHÔNG BAO GIỜ CHUYỂN HƯỚNG. Giống hai cổng máy: WordPress rất thích thêm/bỏ dấu gạch cuối,
 *    mà trang này người ta lưu vào màn hình chính điện thoại — một lượt 301 là mất phiên.
 *
 * 2. LỖI PHẢI NÓI RA. Ghế mất kết nối, tiền đã vào mà ghế chưa nhận — hai chuyện đó để TRÊN
 *    CÙNG, trên cả con số doanh thu. Người mở trang này lúc 9 giờ tối đang đứng cạnh một ghế
 *    không chạy và một khách đang cáu; họ cần câu trả lời trước, không cần báo cáo tháng.
 *
 * 3. BẬT TAY LÀ CHO KHÔNG MỘT LƯỢT. Nút đó có, vì thực tế cần, nhưng phải ghi lại ai bấm và
 *    lúc nào — cuối tháng còn giải thích được vì sao một ghế chạy nhiều hơn số tiền thu.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Trang {

	/** Tên hệ thống — khai MỘT chỗ. Nó hiện ở thẻ tiêu đề trình duyệt, màn đăng nhập và dải đầu
	    trang; ba chỗ gõ tay là ba chỗ lệch nhau sau lần đổi tên đầu tiên. */
	const TEN_HE_THONG = 'Hệ Thống Thanh Toán Ghế Massage Tự Động POSH';
	const TEN_NGAN     = 'POSH Massage';

	public static function slug() {
		$s = get_option( 'vhg_slug' );
		$s = $s ? sanitize_title( $s ) : 'ghe';
		return $s ? $s : 'ghe';
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhg', 'app', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhg_app=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'chan_chuyen_huong' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ), 0 );
	}

	public static function query_vars( $v ) { $v[] = 'vhg_app'; return $v; }

	private static function la_trang() {
		if ( 1 === (int) get_query_var( 'vhg_app' ) ) { return true; }
		if ( isset( $_GET['vhg'] ) && 'app' === $_GET['vhg'] ) { return true; }
		$d = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$d = trim( (string) parse_url( $d, PHP_URL_PATH ), '/' );
		$s = self::slug();
		return $d === $s || substr( $d, - ( strlen( $s ) + 1 ) ) === '/' . $s;
	}

	/** Luật 1. Xem khối đầu tệp. */
	public static function chan_chuyen_huong() {
		if ( ! self::la_trang() ) { return; }
		add_filter( 'redirect_canonical', '__return_false', 99 );
		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	public static function phuc_vu() {
		if ( ! self::la_trang() ) { return; }
		if ( isset( $_GET['api'] ) || isset( $_POST['api'] ) ) {
			self::api();
			if ( ! defined( 'VHG_TEST' ) ) { exit; }
			return;
		}
		self::ve();
		if ( ! defined( 'VHG_TEST' ) ) { exit; }
	}

	// =========================================================================================
	// API — JSON, tất cả đi qua POST và mang token của phiên
	// =========================================================================================

	private static function tra( $d ) {
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
		}
		echo wp_json_encode( $d );
	}

	private static function than() {
		if ( defined( 'VHG_TEST' ) && isset( $GLOBALS['VHG_THAN'] ) ) { return (string) $GLOBALS['VHG_THAN']; }
		$t = file_get_contents( 'php://input' );
		return is_string( $t ) ? $t : '';
	}

	public static function api() {
		$d = json_decode( self::than(), true );
		if ( ! is_array( $d ) ) { $d = array(); }
		foreach ( $_POST as $k => $v ) { if ( ! isset( $d[ $k ] ) ) { $d[ $k ] = $v; } }
		$viec = isset( $_GET['api'] ) ? (string) $_GET['api']
			: (string) ( isset( $d['api'] ) ? $d['api'] : '' );
		$viec = preg_replace( '/[^a-z_]/', '', strtolower( $viec ) );

		if ( 'login' === $viec ) {
			self::tra( VHG_Auth::login( isset( $d['pin'] ) ? $d['pin'] : '' ) );
			return;
		}

		$tok = (string) ( isset( $d['token'] ) ? $d['token'] : '' );
		$ai  = VHG_Auth::user_by_token( $tok );
		if ( ! $ai ) {
			/* `het_phien` là MÃ, không phải câu chữ: giao diện phải phân biệt được "hết phiên,
			   hiện lại ô PIN" với "lỗi khác, đừng đá người ta ra". Bắt theo câu chữ thì sửa một
			   dấu phẩy trong thông báo là đăng nhập lại vô hạn. */
			self::tra( array( 'ok' => false, 'ma' => 'het_phien',
				'error' => 'Phiên đã hết hoặc quyền đã bị thu — đăng nhập lại.' ) );
			return;
		}

		if ( 'logout' === $viec ) { self::tra( VHG_Auth::logout( $tok ) ); return; }

		if ( 'so_lieu' === $viec ) {
			self::tra( self::so_lieu( isset( $d['ky'] ) ? $d['ky'] : 'today', $ai ) );
			return;
		}

		if ( 'bat' === $viec || 'tat' === $viec || 'khoi_dong_lai' === $viec ) {
			$r = VHG_May::dat_lenh(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '',
				'bat' === $viec ? 'on' : ( 'tat' === $viec ? 'off' : 'reboot' ),
				isset( $d['phut'] ) ? $d['phut'] : 0,
				/* Ghi TÊN NGƯỜI ĐANG CẦM PHIÊN, không lấy tên từ gói gửi lên. Luật 3: bật tay là
				   cho không một lượt, nên chữ ký phải là thứ người bấm không tự khai được. */
				$ai['name'],
				isset( $d['ly_do'] ) ? $d['ly_do'] : '' );
			self::tra( $r );
			return;
		}

		if ( 'gan_ma' === $viec ) {
			/* 🔴 Gán ghế NGAY TRÊN ĐIỆN THOẠI. Người đi lắp ghế ở Aeon Tân Phú cầm cái điện
			 *    thoại, không cầm wp-admin. Bắt họ nhắn về văn phòng nhờ ai đó vào wp-admin gán
			 *    hộ là thêm một vòng chờ, và trong lúc chờ thì ghế đứng đó không thu được đồng nào.
			 *
			 * ⚠️ Ghi TÊN NGƯỜI CẦM PHIÊN vào nhật ký, không lấy tên từ gói gửi lên: gán mã là
			 *    đổi khoá của một dòng doanh thu, phải biết ai làm. */
			$r = VHG_May::gan_ma(
				isset( $d['ma_cu'] ) ? $d['ma_cu'] : '',
				isset( $d['ma_moi'] ) ? $d['ma_moi'] : '',
				isset( $d['coso_id'] ) ? (int) $d['coso_id'] : null );
			if ( ! empty( $r['ok'] ) ) {
				VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
					$ai['name'] . ' gán mã ghế: ' . (string) ( isset( $d['ma_cu'] ) ? $d['ma_cu'] : '' )
					. ' -> ' . (string) ( isset( $d['ma_moi'] ) ? $d['ma_moi'] : '' ) ) );
			}
			self::tra( $r );
			return;
		}

		if ( 'so_may' === $viec ) {
			/* Số liệu MỘT ghế cho màn chốt ca. Gọi riêng chứ không nhét vào lượt `so_lieu`: nó
			   chỉ cần khi người ta bấm Thu tiền mặt, mà `so_lieu` chạy mỗi lần tải trang. */
			$ma = trim( (string) ( isset( $d['ma_may'] ) ? $d['ma_may'] : '' ) );
			/* `ds_may_theo_ma()` chứ không `may()`: chỉ bản này mới kèm tên cơ sở (có JOIN). Màn
			   chốt ca phải nói rõ đang đếm tiền của ghế nào Ở ĐÂU — người đi thu tiền đi nhiều
			   cơ sở trong một buổi. */
			$bd = VHG_May::ds_may_theo_ma();
			$m  = isset( $bd[ $ma ] ) ? $bd[ $ma ] : null;
			if ( ! $m ) { self::tra( array( 'ok' => false, 'error' => 'Không thấy ghế ' . $ma . '.' ) ); return; }
			self::tra( array( 'ok' => true, 'ma_may' => $ma,
				'coso' => (string) ( isset( $m['coso_ten'] ) ? $m['coso_ten'] : '' ),
				'gia'  => (int) VHG_May::ty_le_cua( $m )['gia'],
				'hom_nay' => VHG_Thu::tong_may( $ma, 'today' ),
				'tuan'    => VHG_Thu::tong_may( $ma, 'week' ),
				'thang'   => VHG_Thu::tong_may( $ma, 'month' ),
				'tat_ca'  => VHG_Thu::tong_may( $ma, 'all' ) ) );
			return;
		}

		if ( 'tien_mat' === $viec ) {
			self::tra( VHG_Thu::thu_tien_mat(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '',
				isset( $d['so_tien'] ) ? $d['so_tien'] : 0,
				$ai['name'] ) );
			return;
		}

		self::tra( array( 'ok' => false, 'error' => 'Việc không rõ: ' . $viec ) );
	}

	/**
	 * Toàn bộ số liệu một màn, MỘT LƯỢT GỌI.
	 *
	 * Gọi tách ra bốn lượt thì trên 4G ở trung tâm thương mại là bốn cơ hội hỏng, và màn hình
	 * hiện nửa vời — doanh thu có mà tình trạng ghế trống, người đọc không biết đang xem cái gì.
	 */
	private static function so_lieu( $ky, $ai ) {
		$ky  = in_array( $ky, array( 'today', 'week', 'month', 'year', 'all' ), true ) ? $ky : 'today';
		$t   = VHG_Thu::tong_hop( $ky );
		$may = array();
		foreach ( VHG_May::ds_may() as $m ) {
			$may[] = array(
				'ma'      => $m['ma'],
				'coso'    => $m['coso_ten'] ? $m['coso_ten'] : '',
				'song'    => ! empty( $m['con_song'] ),
				'tt'      => (string) $m['trang_thai'],
				'con_lai' => (int) $m['con_lai'],
				'cho'     => (int) $m['cho'],
				'gia'     => (int) VHG_May::ty_le_cua( $m )['gia'],
				'phut'    => (int) VHG_May::ty_le_cua( $m )['phut'],
				/* Cục nhận tiền: gửi CẢ mã lẫn câu giải thích. Người đứng quầy không tra bảng mã
				   — mà đây lại đúng là người phải chạy ra xem cái máy. */
				'tm'      => (string) $m['tm_loi'],
				'tm_cu'   => (string) $m['tm_cuoi'],
				/* Gửi CẢ hai ngôn ngữ trong một lượt. Gửi theo ngôn ngữ đang chọn thì mỗi lần
				   đổi VI/EN lại phải gọi lại máy chủ — trên 4G là vài giây đứng nhìn cho một
				   việc hoàn toàn nằm trong máy người ta. */
				'tm_chu'    => VHG_May::loi_tien_chu( '' !== (string) $m['tm_loi']
					? $m['tm_loi'] : $m['tm_cuoi'] ),
				'tm_chu_en' => VHG_May::loi_tien_chu( '' !== (string) $m['tm_loi']
					? $m['tm_loi'] : $m['tm_cuoi'], 'en' ),
			);
		}
		/* Ghế đang chờ gán mã + danh sách cơ sở: gửi kèm luôn trong lượt số liệu, không thêm
		   lượt gọi. Xem ghi chú "một lượt gọi ra đủ màn" ở dưới. */
		$cho_gan = array();
		foreach ( VHG_May::chua_gan() as $g ) {
			$cho_gan[] = array( 'ma' => $g['ma'], 'mac' => $g['mac'],
				'song' => ! empty( $g['con_song'] ), 'luc' => (string) $g['nhip_luc'] );
		}
		$ds_coso = array();
		foreach ( VHG_May::ds_coso() as $c ) {
			$ds_coso[] = array( 'id' => (int) $c['id'], 'ten' => (string) $c['ten'] );
		}
		$cho = array();
		foreach ( VHG_May::ds_cho( true, 50 ) as $c ) {
			$cho[] = array( 'luc' => $c['tao_luc'], 'ma_may' => $c['ma_may'],
				'so_tien' => (int) $c['so_tien'], 'ma_lenh' => $c['ma_lenh'] );
		}
		$gd = array();
		foreach ( VHG_Thu::ds( $ky, 60 ) as $r ) {
			$gd[] = array(
				'luc'     => $r['luc'],
				'may'     => '' !== $r['ma_may'] ? $r['ma_may'] : ( '' !== $r['ten_khai'] ? $r['ten_khai'] : '' ),
				'nguon'   => $r['nguon'],
				'so_tien' => (int) $r['so_tien'],
				'noi_dung' => $r['noi_dung'],
			);
		}
		return array( 'ok' => true, 'ky' => $ky, 'ai' => $ai, 'tong' => $t,
			'may' => $may, 'cho' => $cho, 'gd' => $gd,
			'choGan' => $cho_gan, 'coso' => $ds_coso,
			'luc' => current_time( 'H:i:s' ) );
	}

	// =========================================================================================
	// GIAO DIỆN
	// =========================================================================================

	public static function ve() {
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		$api = esc_url( self::url() );
		/* Ảnh nền do người dùng khai trong Cài đặt. `esc_url_raw` rồi mới nhét vào CSS: chuỗi này
		   đi thẳng vào một thuộc tính style, nên một dấu nháy lọt qua là chèn được CSS tuỳ ý. */
		$nen = esc_url_raw( (string) get_option( 'vhg_anh_nen', '' ) );
		$lop = '';
		$bien_nen = '';
		if ( '' !== $nen && ! preg_match( '/["\\\\()]/', $nen ) ) {
			$lop       = ' class="co-anh"';
			$bien_nen  = ' style="--nen:url(&quot;' . esc_attr( $nen ) . '&quot;)"';
		}
		echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>' . esc_html( self::TEN_HE_THONG ) . '</title>'
			/* Người đứng quầy lưu trang này vào màn hình chính điện thoại. */
			. '<meta name="theme-color" content="#12141f">'
			. '<style>' . self::css() . '</style></head><body' . $lop . $bien_nen . '>'
			. '<div id="app"></div>'
			. '<script>window.VHG_API=' . wp_json_encode( $api ) . ';'
				. 'window.VHG_TEN=' . wp_json_encode( self::TEN_HE_THONG ) . ';</script>'
			. '<script>' . self::js() . '</script>'
			. '</body></html>';
	}

	private static function css() {
		return <<<'CSS'
*{box-sizing:border-box}
/* ============================================================================================
 * NỀN ẢNH PHÒNG GHẾ.
 *
 * Lớp ảnh để `position:fixed` ở `body::before` chứ KHÔNG phải `background-attachment:fixed`
 * trên body: Safari trên iOS lờ hẳn thuộc tính đó, mà điện thoại mới là nơi trang này sống.
 * Ảnh còn được phủ một lớp tối; không phủ thì chữ trắng nằm trên vùng sáng của ảnh là không
 * đọc nổi, và ảnh nào cũng có một vùng sáng ở đâu đó.
 *
 * Chưa khai ảnh thì rơi về dải màu tự dựng — KHÔNG để trắng trơn và cũng không tải ảnh từ đâu
 * khác về: trang này mở trên 4G ở trung tâm thương mại, và một ảnh nền không tải được sẽ để lại
 * đúng cái nền trắng chữ trắng đó.
 * ============================================================================================ */
body::before{content:"";position:fixed;inset:0;z-index:-2;
  background:radial-gradient(1200px 620px at 78% 8%,#3a2f1c 0%,transparent 62%),
             radial-gradient(900px 520px at 12% 92%,#1d2647 0%,transparent 60%),
             linear-gradient(160deg,#12141f 0%,#171a2e 46%,#0f1120 100%);
  background-size:cover;background-position:center}
body.co-anh::before{background-image:var(--nen);background-size:cover;background-position:center}
/* Lớp phủ tối RIÊNG, không trộn vào ảnh: trộn thì mỗi lần đổi ảnh lại phải chỉnh lại độ tối. */
body::after{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:linear-gradient(180deg,rgba(9,10,18,.80) 0%,rgba(9,10,18,.68) 38%,rgba(9,10,18,.86) 100%)}
body{margin:0;background:#12141f;color:#e8ebff;min-height:100vh;
  font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:1180px;margin:0 auto;padding:14px}
h1{font-size:19px;margin:0}
h1 small{display:block;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#a79a7d;font-weight:400;margin-top:3px}

/* --- Dải đầu trang: khối kính, dính trên cùng ---
   Dính vì đây là chỗ có nút Thoát và đồng hồ; cuộn xuống bảng giao dịch dài rồi phải cuộn
   ngược lên mới thoát được là một cái phiền không đáng có. */
.top{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 14px;
  position:sticky;top:0;z-index:20;padding:11px 14px;
  background:rgba(16,18,30,.80);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);
  border:1px solid rgba(240,180,41,.20);border-radius:14px;
  box-shadow:0 10px 30px rgba(0,0,0,.42)}
.top .sp{flex:1}
.hieu{display:flex;align-items:center;gap:11px;min-width:0}
/* Ô biểu tượng: viền vàng mảnh, nền vàng rất nhạt — cùng ngôn ngữ với các nhãn vàng khác. */
.hieu-o{width:38px;height:38px;flex:none;display:flex;align-items:center;justify-content:center;
  border-radius:11px;font-size:19px;background:rgba(240,180,41,.13);
  border:1px solid rgba(240,180,41,.34)}
.dh-top{font-variant-numeric:tabular-nums;font-weight:600;color:#f0b429;letter-spacing:.04em}
/* Nút đổi ngôn ngữ: hai ô dính nhau, ô đang chọn tô vàng. Để cạnh đồng hồ vì cả hai là thứ
   người ta liếc chứ không bấm thường xuyên. */
.nn{display:inline-flex;border:1px solid rgba(255,255,255,.15);border-radius:9px;overflow:hidden}
.nn button{border:0;border-radius:0;padding:6px 10px;font-size:12px;font-weight:600;letter-spacing:.06em}
.nn button+button{border-left:1px solid rgba(255,255,255,.15)}
.nn-doi{margin-top:14px;display:flex;justify-content:center}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
button{font:inherit;cursor:pointer;border-radius:9px;border:1px solid rgba(255,255,255,.15);
  background:rgba(255,255,255,.07);color:#e8ebff;padding:7px 13px;transition:background .15s,border-color .15s}
button:hover{background:rgba(255,255,255,.13);border-color:rgba(240,180,41,.4)}
button.on{background:#f0b429;border-color:#f0b429;color:#221a00;font-weight:600}
button.on:hover{background:#f7c246}
button.ghost{background:transparent}
input,select{font:inherit;border-radius:9px;border:1px solid rgba(255,255,255,.15);
  background:rgba(10,12,22,.55);color:#e8ebff;padding:7px 10px;width:100%}
input:focus,select:focus{outline:none;border-color:#f0b429}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:10px;margin-bottom:14px}
.kpi{background:rgba(24,27,44,.72);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
  border:1px solid rgba(255,255,255,.09);border-radius:12px;padding:12px 14px}
.kpi .lb{font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#a79a7d}
.kpi .vl{font-size:21px;font-weight:700;margin-top:3px;word-break:break-all}
.kpi .sb{font-size:12px;color:#8d93c4}
.vl.a{color:#f0b429}.vl.b{color:#5fa8ff}.vl.c{color:#4ade80}.vl.d{color:#e8ebff}
.card{background:rgba(22,25,40,.74);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
  border:1px solid rgba(255,255,255,.09);border-radius:14px;padding:13px 15px;margin-bottom:14px;
  box-shadow:0 8px 26px rgba(0,0,0,.34)}
/* Tiêu đề khối: chữ hoa, giãn chữ, một vạch vàng bên trái — để mắt bắt được ranh giới giữa các
   khối ngay cả khi tất cả cùng là kính mờ trên một tấm ảnh. */
.card h2{font-size:12px;margin:0 0 11px;letter-spacing:.12em;text-transform:uppercase;
  color:#f0b429;font-weight:700;padding-left:10px;border-left:3px solid #f0b429;line-height:1.25}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#a79a7d;font-weight:500;padding:0 8px 7px 0;border-bottom:1px solid rgba(240,180,41,.22)}
td{padding:8px 8px 8px 0;border-bottom:1px solid rgba(255,255,255,.07);vertical-align:middle}
tr:last-child td{border-bottom:0}
.r{text-align:right}
.pill{display:inline-block;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:600}
.p-ok{background:#12351f;color:#4ade80}.p-run{background:#2a2410;color:#f0b429}
.p-wait{background:#111f3d;color:#5fa8ff}.p-off{background:#3a1418;color:#ff8087}
.warn{background:rgba(58,20,24,.82);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
  border:1px solid #7c2732;border-radius:14px;padding:12px 14px;margin-bottom:14px}
.warn b{color:#ff8087}
.note{background:rgba(42,36,16,.82);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
  border:1px solid #6b551a;border-radius:14px;padding:12px 14px;margin-bottom:14px}
.note b{color:#f0b429}
.mut{color:#9aa0c2;font-size:12px}
.login{max-width:360px;margin:12vh auto;padding:28px 24px;background:rgba(20,23,38,.80);
  -webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px);
  border:1px solid rgba(240,180,41,.26);border-radius:18px;text-align:center;
  box-shadow:0 22px 60px rgba(0,0,0,.55)}
.login .hieu-o{margin:0 auto 12px;width:46px;height:46px;font-size:23px}
.login h1{margin-bottom:6px}
.login input{text-align:center;letter-spacing:.5em;font-size:21px;margin:16px 0 10px}
.err{color:#ff8087;font-size:13px;min-height:19px;margin-top:8px}
.act{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
/* --- Tab chính --- */
.nav{display:flex;gap:6px;margin-bottom:14px;border-bottom:1px solid rgba(240,180,41,.2);padding-bottom:10px}
.nav button{border-radius:9px 9px 0 0}
/* --- Thẻ ghế (tab Điều khiển) ---
   Bảng hợp cho đối soát (so số theo cột), nhưng KHÔNG hợp cho điều khiển: người bấm đang đứng
   cạnh một con ghế cụ thể và cần thấy đúng nó, to và rõ, chứ không dò theo hàng. */
.ghe-luoi{display:grid;grid-template-columns:repeat(auto-fill,minmax(258px,1fr));gap:12px}
.ghe{background:rgba(20,23,38,.78);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
  border:1px solid rgba(255,255,255,.10);border-radius:14px;padding:14px;
  box-shadow:0 6px 20px rgba(0,0,0,.3)}
.ghe.dut{border-color:#7c2732;background:rgba(38,20,26,.78)}
/* Ghế đang chạy: viền vàng ĐẬM hơn hẳn, vì trong một lưới 26 thẻ kính mờ giống nhau thì cái
   đang chạy phải bắt được mắt từ đầu bên kia quầy. */
.ghe.chay{border-color:rgba(240,180,41,.65);box-shadow:0 0 0 1px rgba(240,180,41,.18),0 6px 22px rgba(0,0,0,.36)}
.ghe-dau{display:flex;align-items:center;gap:8px;margin-bottom:2px}
.ghe-ma{font-size:17px;font-weight:700}
.ghe-cs{font-size:12px;color:#a79a7d;margin-bottom:10px}
.ghe-dh{font-size:31px;font-weight:700;color:#f0b429;margin:6px 0 2px;
  font-variant-numeric:tabular-nums;letter-spacing:.01em}
.ghe-tien-loi{margin:8px 0;padding:8px 10px;border-radius:8px;font-size:12px;line-height:1.45}
.ghe-tien-loi div{font-weight:400;margin-top:3px;opacity:.92}
.ghe-tien-loi.dang{background:rgba(220,60,50,.16);border:1px solid rgba(220,60,50,.5);
  color:#ffb4ae;font-weight:700}
.ghe-tien-loi.cu{background:rgba(240,180,41,.12);border:1px solid rgba(240,180,41,.4);
  color:#f0c76b;font-weight:600}
.ghe-nut{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:11px}
.ghe-nut button{padding:9px 6px;font-size:13px}
.b-bat{background:#12351f;border-color:#2f6b45;color:#8ff0b0}
.b-tat{background:#3a1418;border-color:#7c2732;color:#ff8087}
.b-kd{background:#111f3d;border-color:#2b4a80;color:#9ecbff}
.ghe-hang{display:flex;gap:6px;align-items:center;margin-top:8px}
.ghe-hang input{width:70px}
.ghe-hang label{font-size:11px;color:#a79a7d}
/* --- Bảng chốt ca thu tiền --- */
.man{position:fixed;inset:0;background:rgba(8,10,22,.82);display:flex;align-items:center;
  justify-content:center;padding:14px;z-index:50;overflow:auto}
.hop{background:#1e2240;border:1px solid #3a4170;border-radius:14px;
  padding:18px;max-width:440px;width:100%}
.hop h3{margin:0 0 2px;font-size:18px}
.hop .cs{font-size:12px;color:#8d93c4;margin-bottom:14px}
.so-hang{display:flex;justify-content:space-between;align-items:baseline;padding:9px 0;
  border-bottom:1px solid #252a4b}
.so-hang:last-of-type{border-bottom:0}
.so-hang .nh{font-size:13px;color:#8d93c4}
.so-hang .gt{font-size:16px;font-weight:700}
.so-hang.to{border-top:1px solid #3a4170;margin-top:6px;padding-top:12px}
.so-hang.to .nh{color:#e8ebff;font-weight:600}
.so-hang.to .gt{font-size:21px;color:#f0b429}
.o-thu{margin:14px 0 8px}
.o-thu input{font-size:23px;text-align:right;padding:11px 13px;font-weight:700}
.phim{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:10px 0}
.phim button{padding:13px 0;font-size:17px}
.hop-nut{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
.hop-nut button{padding:12px 0}
.canh{background:#3a1418;border:1px solid #7c2732;border-radius:9px;padding:9px 11px;
  font-size:12px;color:#ff8087;margin-top:10px}
.act input{width:66px;padding:5px 7px}
.act select{font:inherit;border-radius:8px;border:1px solid #343a63;background:#151831;color:#e8ebff;padding:5px 7px;max-width:130px}
.note code{background:#151831;padding:1px 5px;border-radius:5px}
.act button{padding:5px 10px;font-size:12px}
@media(max-width:560px){.hide-sm{display:none}.wrap{padding:10px}}

/* ============================================================================================
 * MÀN MÁY TÍNH. Bản đầu chỉ ngắm điện thoại nên trên màn rộng nó bó vào một cột giữa, hai bên
 * bỏ trống hơn nửa màn hình — mà người ngồi văn phòng đối soát cuối ngày lại dùng đúng màn đó.
 *
 * Không chỉ nới bề ngang: xếp "Theo cơ sở" và "Theo ghế" NẰM CẠNH NHAU. Hai bảng đó là hai
 * cách nhìn cùng một số tiền, đặt cạnh nhau thì so được bằng mắt; xếp dọc thì phải cuộn qua
 * lại và người ta thôi không so nữa.
 * ============================================================================================ */
@media(min-width:1100px){
  .wrap{max-width:1400px;padding:20px 26px}
  body{font-size:15.5px}
  h1{font-size:22px}
  .kpis{gap:14px}
  .kpi{padding:14px 18px}
  .kpi .vl{font-size:25px}
  .card{padding:16px 18px}
  .card h2{font-size:15px}
  table{font-size:14px}
  td{padding:10px 10px 10px 0}
  /* Hai bảng tổng hợp nằm cạnh nhau */
  .doi{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
  .doi .card{margin-bottom:0}
  /* Ô nhập trong cột "Việc" đang dính sát nhau ở màn rộng — cho chúng thở */
  .act{gap:8px}
  .act input{width:78px}
}
@media(min-width:1500px){
  .wrap{max-width:1560px}
  .kpis{grid-template-columns:repeat(4,1fr)}
}
CSS;
	}

	private static function js() {
		return <<<'JS'
(function(){
var API = window.VHG_API, TOK = null, KY = 'today', D = null, ban = false;
var TEN_HT = window.VHG_TEN || 'POSH Massage';
/* Tab đang mở. Nhớ lại giữa các lần tải: người đang điều khiển ghế bấm ↻ mà bị đá về tab đối
   soát là mỗi lượt bấm mất thêm một cú bấm nữa. */
var TAB = 'doi-soat';
try { TAB = localStorage.getItem('vhg_tab') || 'doi-soat'; } catch(e) {}

/* ============================================================================================
 * HAI NGÔN NGỮ.
 *
 * Cặp Việt/Anh viết NGAY TẠI CHỖ dùng — `L('Đối soát','Reconciliation')` — chứ không gom vào
 * một bảng khoá kiểu `t('tab.doisoat')`. Bảng khoá đọc gọn hơn ở chỗ dùng, nhưng ở đây nó sai:
 * cả tệp này là những câu giải thích dài cho người đứng quầy, mà một câu tiếng Việt nằm cách
 * bản dịch của nó bốn trăm dòng thì sửa một bên quên bên kia là chuyện chắc chắn xảy ra. Để
 * cạnh nhau thì không sửa lệch được.
 *
 * ⚠️ CON SỐ KHÔNG DỊCH. Tiền vẫn định dạng kiểu Việt Nam ("50.000đ") ở cả hai ngôn ngữ: đây là
 *    tiền Việt đếm trong két Việt, và người nước ngoài đọc bảng này vẫn phải đối chiếu với tờ
 *    tiền thật trên tay. Đổi dấu chấm/phẩy theo tiếng Anh là mời người ta đọc nhầm 50.000 thành
 *    năm mươi.
 * ============================================================================================ */
var NN = 'vi';
try { NN = localStorage.getItem('vhg_nn') === 'en' ? 'en' : 'vi'; } catch(e) {}
function L(vi, en){ return NN === 'en' ? en : vi; }
function nutNN(){
  return '<span class="nn">'
    + '<button data-nn="vi"' + (NN==='vi'?' class="on"':'') + '>VI</button>'
    + '<button data-nn="en"' + (NN==='en'?' class="on"':'') + '>EN</button></span>';
}
function noiNN(){
  [].forEach.call(document.querySelectorAll('[data-nn]'), function(b){
    b.onclick = function(){ datNN(b.getAttribute('data-nn')); };
  });
}
function datNN(n){
  NN = (n === 'en') ? 'en' : 'vi';
  try { localStorage.setItem('vhg_nn', NN); } catch(e) {}
  document.documentElement.setAttribute('lang', NN);
  /* Vẽ lại từ dữ liệu ĐANG CÓ. Gọi lại máy chủ chỉ để đổi chữ là bắt người ta chờ 4G cho một
     việc hoàn toàn nằm trong máy họ. */
  if (D) ve(); else veLogin('');
}

/* ============================================================================================
 * TỰ CẬP NHẬT.
 *
 * 🔴 Anh Thắng 22/08/2026: *"bấm điều khiển máy chạy, nhưng trên web thời gian chưa chạy"*. Đúng
 *    — trang chỉ tải khi mở hoặc khi bấm ↻. Người đứng cạnh ghế bấm Bật, ghế chạy thật, nhưng
 *    web vẫn nói "Rảnh"; họ tưởng lệnh không ăn nên bấm lần nữa — mà mỗi lần bấm Bật là CHO
 *    KHÔNG một lượt nữa.
 *
 * Hai nhịp khác nhau, cố ý:
 *   · Tab ĐIỀU KHIỂN 5 giây — người đang đứng đó chờ ghế phản hồi.
 *   · Tab ĐỐI SOÁT 30 giây — số tiền không đổi từng giây, mà trang này mở suốt ngày trên 4G.
 *
 * Và số đếm ngược tự trừ MỖI GIÂY giữa hai lượt hỏi, chứ không đứng im rồi nhảy 5 giây một
 * lần: một con số đứng im là dấu hiệu ghế treo, đừng để giao diện tự tạo ra dấu hiệu đó.
 * ============================================================================================ */
var NHIP_MS = { 'dieu-khien': 5000, 'doi-soat': 30000 };
var hen = null, demGiay = null;
try { TOK = localStorage.getItem('vhg_tok'); } catch(e) {}

var app = document.getElementById('app');
function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, function(c){
  return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
function tien(n){ return (Number(n)||0).toLocaleString('vi-VN') + 'đ'; }
/* mm:ss có số 0 ở đầu — ĐÚNG KIỂU MÀN GHẾ VẼ (`snprintf("%02d:%02d")`). Ghế hiện "04:57" mà
   web hiện "4:57" thì cùng một con số ra hai kiểu, và người đối chiếu bằng mắt sẽ dừng lại một
   nhịp để tự hỏi hai chỗ có nói cùng một thứ không. Chiều rộng cố định còn đỡ nhảy chữ khi
   đếm qua mốc 10 phút. */
function mmss(s){ s=Math.max(0,Number(s)||0);
  return String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0'); }

function goi(viec, d, xong){
  d = d || {}; d.token = TOK;
  var x = new XMLHttpRequest();
  x.open('POST', API + (API.indexOf('?')<0?'?':'&') + 'api=' + viec, true);
  x.setRequestHeader('Content-Type','application/json');
  x.onreadystatechange = function(){
    if (x.readyState !== 4) return;
    var r = null;
    try { r = JSON.parse(x.responseText); } catch(e){}
    /* Máy chủ trả rác (tường lửa hosting chèn trang chặn, mạng đứt giữa chừng) KHÔNG được
       thành "hết phiên" — đá người ta ra rồi họ gõ lại PIN và gặp đúng lỗi đó. */
    if (!r) { xong({ ok:false, error:L('Không đọc được trả lời của máy chủ (mạng hoặc tường lửa).',
      'Could not read the server reply (network or firewall).') }); return; }
    if (r.ma === 'het_phien') { TOK = null; try{localStorage.removeItem('vhg_tok');}catch(e){} veLogin(L('Phiên đã hết — đăng nhập lại.','Session expired — please sign in again.')); return; }
    xong(r);
  };
  x.send(JSON.stringify(d));
}

// ------------------------------------------------------------------ đăng nhập
function veLogin(loi){
  app.innerHTML =
    '<div class="login"><div class="hieu-o">💆</div>'
    + '<h1>' + esc(TEN_HT) + '<small>' + L('Doanh thu &amp; tình trạng ghế','Revenue &amp; chair status')
    + '</small></h1>'
    + '<input id="pin" type="tel" inputmode="numeric" maxlength="8" placeholder="PIN" autocomplete="off">'
    + '<button id="vao" class="on" style="width:100%">' + L('Vào','Sign in') + '</button>'
    + '<div class="err" id="e">' + esc(loi||'') + '</div>'
    + '<div class="nn-doi">' + nutNN() + '</div></div>';
  noiNN();
  var pin = document.getElementById('pin'), e = document.getElementById('e');
  function thu(){
    var v = (pin.value||'').trim();
    if (!v) { e.textContent = L('Chưa nhập PIN.','Please enter your PIN.'); return; }
    e.textContent = L('Đang kiểm…','Checking…');
    goi('login', { pin: v }, function(r){
      if (!r.ok) { e.textContent = r.error || L('PIN không đúng','Incorrect PIN'); pin.value=''; pin.focus(); return; }
      TOK = r.token; try{localStorage.setItem('vhg_tok',TOK);}catch(er){}
      tai();
    });
  }
  document.getElementById('vao').onclick = thu;
  pin.addEventListener('keydown', function(ev){ if (ev.key === 'Enter') thu(); });
  pin.focus();
}

// ------------------------------------------------------------------ màn chính
function tai(im){
  goi('so_lieu', { ky: KY }, function(r){
    if (!r.ok) { if (!im) veLogin(r.error || ''); return; }
    D = r; ve();
  });
}

/* Hẹn lượt hỏi kế tiếp. Luôn huỷ lượt cũ trước: không thì mỗi lần vẽ lại là thêm một đồng hồ,
   và sau mươi phút trang tự hỏi máy chủ vài chục lần một giây. */
function henLai(){
  if (hen) { clearTimeout(hen); hen = null; }
  if (!TOK) return;
  hen = setTimeout(function(){
    /* KHÔNG hỏi khi: người dùng đang chờ một lệnh chạy xong, đang mở bảng chốt ca (vẽ lại là
       xoá mất số họ đang gõ), hoặc trang đang ẩn (điện thoại trong túi — hỏi cũng không ai đọc,
       chỉ tốn 4G). */
    if (ban || CHOT || document.hidden) { henLai(); return; }
    tai(true);
  }, NHIP_MS[TAB] || 30000);
}

/* Đồng hồ đếm ngược chạy TẠI CHỖ giữa hai lượt hỏi. Chỉ đụng vào phần chữ của con số, không
   vẽ lại cả trang — vẽ lại mỗi giây là mất luôn ô đang gõ dở và nút đang bấm. */
function chayDongHo(){
  if (demGiay) { clearInterval(demGiay); demGiay = null; }
  if (TAB !== 'dieu-khien' || !D) return;
  demGiay = setInterval(function(){
    if (!D || document.hidden) return;
    var co = false;
    D.may.forEach(function(m){
      if (m.tt !== 'running' || !m.song) return;
      if (m.con_lai > 0) { m.con_lai--; co = true; }
      var o = document.querySelector('[data-dh="' + m.ma + '"]');
      if (o) o.textContent = mmss(m.con_lai);
    });
    /* Hết giờ tại chỗ thì hỏi lại ngay, đừng đợi hết nhịp 5 giây: lúc đó trạng thái ghế vừa
       đổi và đó chính là thứ người ta đang chờ xem. */
    if (!co) { clearInterval(demGiay); demGiay = null; if (!ban && !CHOT) tai(true); }
  }, 1000);
}

function ve(){
  var t = D.tong, h = '';

  h += '<div class="wrap"><div class="top">'
    + '<div class="hieu"><div class="hieu-o">💆</div>'
    + '<h1>' + esc(TEN_HT) + '<small>' + esc(D.ai.name) + ' · ' + esc(D.ai.role) + '</small></h1></div>'
    + '<span class="sp"></span>'
    /* Đồng hồ chạy từng giây như bảng thiết kế. Lấy giờ MÁY CHỦ làm mốc rồi tự tích, không lấy
       giờ điện thoại: điện thoại nhân viên hay lệch, mà mọi con số khác trên trang này đều theo
       giờ máy chủ — hai loại giờ cạnh nhau là mời người ta đối chiếu nhầm. */
    + '<span class="dh-top" id="dh-top">' + esc(D.luc) + '</span>'
    + nutNN()
    + '<button id="lam-moi" class="ghost" title="' + L('Tải lại','Refresh') + '">↻</button>'
    + '<button id="thoat" class="ghost">' + L('Thoát','Sign out') + '</button></div>';

  h += '<div class="nav">'
    + '<button data-tab="doi-soat"' + (TAB==='doi-soat'?' class="on"':'') + '>📊 '
      + L('Đối soát','Reconciliation') + '</button>'
    + '<button data-tab="dieu-khien"' + (TAB==='dieu-khien'?' class="on"':'') + '>🎛 '
      + L('Điều khiển ghế','Chair control') + '</button>'
    + '</div>';

  if (TAB === 'doi-soat') {
    h += '<div class="tabs">';
    [['today',L('Hôm nay','Today')],['week',L('Tuần này','This week')],['month',L('Tháng này','This month')],
     ['year',L('Năm nay','This year')],['all',L('Tất cả','All time')]]
      .forEach(function(k){ h += '<button data-ky="' + k[0] + '"' + (KY===k[0]?' class="on"':'') + '>' + k[1] + '</button>'; });
    h += '</div>';
  }

  /* GHẾ CHỜ GÁN — trên cùng luôn, trên cả cảnh báo mất kết nối.
     Ghế vừa cắm điện xong là thứ người đang đứng cạnh nó cần thấy đầu tiên; và chừng nào chưa
     gán mã thì nó KHÔNG vẽ được QR, tức là không thu được đồng nào. */
  if (D.choGan && D.choGan.length) {
    h += '<div class="note"><b>' + D.choGan.length + ' '
      + L('ghế vừa nối mạng, chưa có mã','chairs just came online with no code') + '</b> — '
      + L('ghế chưa gán mã thì không hiện được QR. Đặt mã ngắn (VD <code>AMTP01</code>): mã này đi '
          + 'vào nội dung chuyển khoản khách gõ tay.',
          'a chair with no code cannot show a QR. Give it a short code (e.g. <code>AMTP01</code>): '
          + 'this code goes into the transfer memo the customer types by hand.')
      + '<table style="margin-top:8px"><tr><th>MAC</th><th class="hide-sm">'
      + L('Tình trạng','Status') + '</th>'
      + '<th class="r">' + L('Gán mã + cơ sở','Assign code + branch') + '</th></tr>'
      + D.choGan.map(function(g, i){
          return '<tr><td><code>' + esc(g.mac) + '</code><br><span class="mut">' + esc(g.ma) + '</span></td>'
            + '<td class="hide-sm"><span class="pill ' + (g.song?'p-ok':'p-off') + '">'
            + (g.song?L('đang sống','online'):L('mất kết nối','offline')) + '</span></td>'
            + '<td class="r"><div class="act" style="justify-content:flex-end">'
            + '<input type="text" placeholder="AMTP01" data-gma="' + esc(g.ma) + '" style="width:96px">'
            + '<select data-gcs="' + esc(g.ma) + '"><option value="0">— '
            + L('cơ sở','branch') + ' —</option>'
            + (D.coso||[]).map(function(c){
                return '<option value="' + c.id + '">' + esc(c.ten) + '</option>'; }).join('')
            + '</select><button data-gan="' + esc(g.ma) + '">' + L('Gán','Assign')
            + '</button></div></td></tr>'; }).join('')
      + '</table></div>';
  }

  /* LUẬT 2: hỏng để TRÊN CÙNG, trên cả con số doanh thu. */
  var dut = D.may.filter(function(m){ return !m.song; });
  if (dut.length) {
    h += '<div class="warn"><b>' + dut.length + ' ' + L('ghế mất kết nối','chairs offline') + '</b> — '
      + esc(dut.map(function(m){ return m.ma + (m.coso ? ' (' + m.coso + ')' : ''); }).join(', '))
      + '. ' + L('Khách vẫn quét được tem QR trên ghế, tiền vẫn vào, nhưng ghế KHÔNG chạy.',
                 'Customers can still scan the QR sticker on the chair and the money still arrives, '
                 + 'but the chair will NOT run.') + '</div>';
  }
  if (D.cho.length) {
    h += '<div class="note"><b>' + D.cho.length + ' '
      + L('lượt đã trả tiền mà ghế chưa nhận','paid sessions the chair has not picked up') + '</b> — '
      + L('bình thường ghế lấy trong ~10 giây.','a chair normally picks one up within ~10 seconds.')
      + '<table style="margin-top:8px"><tr><th>' + L('Lúc','Time') + '</th><th>'
      + L('Ghế','Chair') + '</th>'
      + '<th class="r">' + L('Số tiền','Amount') + '</th></tr>'
      + D.cho.slice(0,8).map(function(c){
          return '<tr><td>' + esc(c.luc) + '</td><td>' + esc(c.ma_may) + '</td>'
            + '<td class="r">' + tien(c.so_tien) + '</td></tr>'; }).join('')
      + '</table></div>';
  }

  if (TAB === 'dieu-khien') { h += veDieuKhien() + '</div>'; app.innerHTML = h; noi(); return; }

  h += '<div class="kpis">'
    + kpi(L('Tổng doanh thu','Total revenue'), tien(t.tong), t.so_luot + ' ' + L('lượt','sessions'), 'a')
    + kpi(L('Chuyển khoản (QR)','Bank transfer (QR)'), tien(t.qr), t.qr_luot + ' ' + L('lượt','sessions'), 'b')
    + kpi(L('Tiền mặt','Cash'), tien(t.tien_mat), t.tien_mat_luot + ' ' + L('lượt','sessions'), 'c')
    + kpi(L('Đang chờ ghế nhận','Waiting for a chair'), String(D.cho.length),
        L('đã trả, chưa chạy','paid, not started'), 'd')
    + '</div>';

  // --- tình trạng ghế: chỉ LIỆT KÊ ở tab đối soát; bấm nút thì sang tab Điều khiển
  h += '<div class="card"><h2>' + L('Tình trạng ghế','Chair status') + '</h2><table><tr><th>'
    + L('Ghế','Chair') + '</th><th class="hide-sm">' + L('Cơ sở','Branch') + '</th>'
    + '<th>' + L('Trạng thái','State') + '</th><th class="r">' + L('Còn lại','Remaining') + '</th></tr>';
  if (!D.may.length) h += '<tr><td colspan="4" class="mut">'
    + L('Chưa khai ghế nào.','No chairs registered yet.') + '</td></tr>';
  D.may.forEach(function(m){
    var p = trangThai(m);
    h += '<tr><td><b>' + esc(m.ma) + '</b></td>'
      + '<td class="hide-sm">' + esc(m.coso || L('(chưa gán)','(unassigned)')) + '</td>'
      + '<td><span class="pill ' + p[0] + '">' + p[1] + '</span></td>'
      + '<td class="r">' + (m.tt === 'running' && m.song ? mmss(m.con_lai) : '') + '</td></tr>';
  });
  h += '</table><p class="mut" style="margin:9px 0 0">'
    + L('Bật/tắt ghế ở tab <b>🎛 Điều khiển ghế</b>.',
        'Turn chairs on/off in the <b>🎛 Chair control</b> tab.') + '</p></div>';

  /* Hai bảng tổng hợp trong một khối: trên màn rộng chúng nằm cạnh nhau (xem .doi trong CSS),
     trên điện thoại vẫn xếp dọc như cũ. */
  h += '<div class="doi">';
  h += bang(L('Theo cơ sở','By branch'),
    [L('Cơ sở','Branch'),L('Lượt','Sessions'),'QR',L('Tiền mặt','Cash'),L('Tổng','Total')],
    Object.keys(t.theo_coso).map(function(k){ var c = t.theo_coso[k];
      return ['<b>' + esc(c.coso) + '</b>', c.so_luot, tien(c.qr), tien(c.tien_mat), '<b>' + tien(c.tong) + '</b>']; }));
  h += bang(L('Theo ghế','By chair'),
    [L('Ghế','Chair'),L('Cơ sở','Branch'),L('Lượt','Sessions'),'QR',L('Tiền mặt','Cash'),L('Tổng','Total')],
    Object.keys(t.theo_may).map(function(k){ var m = t.theo_may[k];
      return ['<b>' + esc(m.may) + '</b>', esc(m.coso), m.so_luot, tien(m.qr), tien(m.tien_mat), '<b>' + tien(m.tong) + '</b>']; }));
  h += '</div>';

  // --- giao dịch
  h += bang(L('Giao dịch gần đây','Recent transactions'),
    [L('Thời gian','Time'),L('Ghế','Chair'),L('Nguồn','Source'),L('Nội dung','Memo'),L('Số tiền','Amount')],
    D.gd.map(function(g){
      return [esc(g.luc), esc(g.may || '—'),
        '<span class="pill ' + (g.nguon === 'cash' ? 'p-ok' : 'p-wait') + '">'
          + (g.nguon === 'cash' ? L('Tiền mặt','Cash') : String(g.nguon).toUpperCase()) + '</span>',
        '<span class="mut">' + esc(g.noi_dung) + '</span>', tien(g.so_tien)]; }));

  h += '</div>';
  app.innerHTML = h;
  noi();
}

/* Trạng thái một ghế -> [lớp CSS, chữ]. MỘT chỗ duy nhất: hai tab cùng hiện trạng thái này,
   khai hai nơi là sớm muộn một tab nói "Rảnh" còn tab kia nói "Đang chạy". */
function trangThai(m){
  if (!m.song)              return ['p-off',L('Mất kết nối','Offline')];
  if (m.tt === 'running')   return ['p-run',L('Đang chạy','Running')];
  if (m.tt === 'wait_pay')  return ['p-wait',L('Chờ trả tiền','Awaiting payment')];
  return ['p-ok',L('Rảnh','Idle')];
}

/* TAB ĐIỀU KHIỂN — mỗi ghế một thẻ, không phải một hàng bảng.
   Người bấm đang đứng cạnh một con ghế cụ thể và cần thấy đúng nó, to và rõ; dò theo hàng
   trong bảng là nguồn của việc bấm nhầm sang ghế bên cạnh. */
function veDieuKhien(){
  if (!D.may.length) {
    return '<div class="card"><h2>' + L('Điều khiển ghế','Chair control') + '</h2><p class="mut">'
      + L('Chưa khai ghế nào. Cắm ghế lên là nó tự hiện ở khối <b>Ghế vừa nối mạng</b> trong tab Đối soát.',
          'No chairs registered yet. Power one on and it appears by itself under '
          + '<b>Chairs just came online</b> in the Reconciliation tab.') + '</p></div>';
  }
  var h = '<div class="card"><h2>' + L('Quản lý ghế · Điều khiển','Chair management · Control')
    + ' — ' + D.may.length + ' ' + L('ghế','chairs') + '</h2>'
    + '<p class="mut" style="margin:0 0 12px">'
    + L('Bật tay là <b>cho không một lượt</b> — hệ thống ghi lại ai bấm và lúc nào, để cuối tháng '
        + 'còn giải thích được vì sao một ghế chạy nhiều hơn số tiền thu.',
        'Turning a chair on by hand is <b>a free session</b> — the system records who pressed it and '
        + 'when, so at month end you can still explain why a chair ran more than it took in.')
    + '</p><div class="ghe-luoi">';

  D.may.forEach(function(m){
    var p = trangThai(m);
    var lop = !m.song ? ' dut' : (m.tt === 'running' ? ' chay' : '');
    h += '<div class="ghe' + lop + '">'
      + '<div class="ghe-dau"><span class="ghe-ma">' + esc(m.ma) + '</span>'
      + '<span class="pill ' + p[0] + '">' + p[1] + '</span></div>'
      + '<div class="ghe-cs">' + esc(m.coso || L('(chưa gán cơ sở)','(no branch)')) + '</div>';

    /* 🔴 CỤC NHẬN TIỀN HỎNG — nằm ngay trên thẻ ghế, không giấu trong wp-admin. Người phải chạy
       ra xem cái máy là người đứng quầy, mà họ không có tài khoản WordPress. Nói luôn PHẢI LÀM
       GÌ chứ không phải một cái mã lỗi: "lỗi ket" thì ai cũng chịu. */
    if (m.tm) {
      h += '<div class="ghe-tien-loi dang">⚠ '
        + L('Cục nhận tiền đang hỏng','Bill acceptor is faulty right now')
        + '<div>' + esc(L(m.tm_chu, m.tm_chu_en || m.tm_chu)) + '</div></div>';
    } else if (m.tm_cu) {
      h += '<div class="ghe-tien-loi cu">'
        + L('Cục nhận tiền đã hỏng lúc trước','Bill acceptor failed earlier')
        + '<div>' + esc(L(m.tm_chu, m.tm_chu_en || m.tm_chu)) + '</div></div>';
    }

    /* Số đếm ngược to: đó là thứ người đứng cạnh ghế nhìn để biết còn bao lâu. */
    if (m.tt === 'running' && m.song) {
      h += '<div class="ghe-dh" data-dh="' + esc(m.ma) + '">' + mmss(m.con_lai)
        + '</div><div class="mut">' + L('còn lại','remaining') + '</div>';
    } else if (!m.song) {
      h += '<div class="mut" style="margin:8px 0">'
        + L('Ghế không gửi nhịp. Khách vẫn quét được tem QR trên ghế, <b>tiền vẫn vào nhưng ghế '
            + 'không chạy</b>.',
            'The chair is not sending a heartbeat. Customers can still scan the QR sticker on it, '
            + '<b>the money still arrives but the chair will not run</b>.') + '</div>';
    } else if (m.cho > 0) {
      h += '<div class="mut" style="margin:8px 0">' + m.cho + ' '
        + L('lượt đã trả tiền đang chờ ghế nhận.','paid sessions waiting for the chair to pick up.')
        + '</div>';
    } else {
      h += '<div class="mut" style="margin:8px 0">' + L('Sẵn sàng','Ready') + ' · ' + tien(m.gia)
        + ' = ' + m.phut + ' ' + L('phút','min') + '</div>';
    }

    h += '<div class="ghe-hang"><label>' + L('Số phút','Minutes') + '</label>'
      + '<input type="number" min="1" max="60" value="' + m.phut + '" data-phut="' + esc(m.ma) + '">'
      + '<label>' + L('Tiền mặt','Cash') + '</label>'
      + '<input type="number" min="1000" step="1000" value="' + m.gia + '" data-tien="' + esc(m.ma) + '">'
      + '</div>';

    h += '<div class="ghe-nut">'
      + '<button class="b-bat" data-bat="' + esc(m.ma) + '">▶ ' + L('Bật','Start') + '</button>'
      + '<button class="b-tat" data-tat="' + esc(m.ma) + '">■ ' + L('Tắt','Stop') + '</button>'
      + '<button data-mat="' + esc(m.ma) + '">💵 ' + L('Thu tiền mặt','Collect cash') + '</button>'
      + '<button class="b-kd" data-kd="' + esc(m.ma) + '">⟳ ' + L('Khởi động lại','Reboot') + '</button>'
      + '</div></div>';
  });
  return h + '</div></div>';
}

function kpi(lb, vl, sb, m){
  return '<div class="kpi"><div class="lb">' + lb + '</div><div class="vl ' + m + '">' + vl
    + '</div><div class="sb">' + sb + '</div></div>';
}
function bang(ten, cot, hang){
  var h = '<div class="card"><h2>' + ten + '</h2><table><tr>'
    + cot.map(function(c,i){ return '<th' + (i>=cot.length-3?' class="r"':'') + '>' + c + '</th>'; }).join('')
    + '</tr>';
  if (!hang.length) h += '<tr><td colspan="' + cot.length + '" class="mut">'
    + L('Chưa có số liệu kỳ này.','No data for this period.') + '</td></tr>';
  hang.forEach(function(r){
    h += '<tr>' + r.map(function(o,i){ return '<td' + (i>=cot.length-3?' class="r"':'') + '>' + o + '</td>'; }).join('') + '</tr>';
  });
  return h + '</table></div>';
}

/* ============================================================================================
 * BẢNG CHỐT CA THU TIỀN.
 *
 * 🔴 Bản trước bấm "Thu tiền mặt" là hỏi "ghi 10.000đ?" rồi ghi luôn. Sai với việc thật: người
 *    đi thu tiền mở ngăn ghế ra, đếm được một xấp, và cần biết HỆ THỐNG NGHĨ là bao nhiêu để
 *    đối chiếu. Không có con số đó thì họ gõ đại số mình đếm được, và chênh lệch — nếu có —
 *    không bao giờ lộ ra.
 *
 * Nên: hiện số liệu TRƯỚC, nhập tiền SAU. Và hiện cả QR lẫn tổng tháng, vì câu hỏi thật lúc
 * đứng ở cửa hàng là "ghế này tháng này ra bao nhiêu", không phải "hôm nay bao nhiêu".
 * ============================================================================================ */
var CHOT = null;   // { ma_may, so } — bảng đang mở

function moChotCa(ma){
  if (ban) return;
  goi('so_may', { ma_may: ma }, function(r){
    if (!r.ok) { alert(r.error || L('Không lấy được số liệu ghế.','Could not load chair figures.')); return; }
    CHOT = { ma: ma, so: r, go: '' };
    veChotCa();
  });
}

function hangSo(nhan, gt, lop){
  return '<div class="so-hang' + (lop||'') + '"><span class="nh">' + nhan + '</span>'
    + '<span class="gt">' + gt + '</span></div>';
}

function veChotCa(){
  var r = CHOT.so, cu = document.getElementById('man-chot');
  if (cu) cu.remove();
  var d = document.createElement('div');
  d.className = 'man'; d.id = 'man-chot';
  d.innerHTML = '<div class="hop">'
    + '<h3>' + L('Thu tiền mặt','Collect cash') + ' — ' + esc(CHOT.ma) + '</h3>'
    + '<div class="cs">' + esc(r.coso || L('(chưa gán cơ sở)','(no branch)')) + '</div>'
    + hangSo(L('Tiền mặt hôm nay','Cash today'), tien(r.hom_nay.tien_mat))
    + hangSo(L('Chuyển khoản (QR) hôm nay','Bank transfer (QR) today'), tien(r.hom_nay.qr))
    + hangSo(L('Tổng hôm nay','Total today') + ' · ' + r.hom_nay.so_luot + ' '
        + L('lượt','sessions'), tien(r.hom_nay.tong))
    + hangSo(L('Tiền mặt tháng này','Cash this month'), tien(r.thang.tien_mat))
    + hangSo(L('Chuyển khoản (QR) tháng này','Bank transfer (QR) this month'), tien(r.thang.qr))
    + hangSo(L('TỔNG THÁNG NÀY','TOTAL THIS MONTH') + ' · ' + r.thang.so_luot + ' '
        + L('lượt','sessions'), tien(r.thang.tong), ' to')
    + '<div class="o-thu"><label class="mut">'
    + L('Số tiền mặt đã đếm được','Cash counted') + '</label>'
    + '<input id="chot-tien" type="text" inputmode="numeric" value="' + esc(CHOT.go) + '" placeholder="0"></div>'
    + '<div class="phim">'
    + ['1','2','3','4','5','6','7','8','9','000','0','⌫'].map(function(k){
        return '<button data-phim="' + k + '">' + k + '</button>'; }).join('')
    + '</div>'
    /* ⚠️ Nói thẳng: đây là GHI SỔ, không phải mở ngăn tiền. Người bấm tưởng nó mở khoá ghế thì
       họ bấm rồi đứng đợi, và bấm lại — mỗi lần bấm là một dòng doanh thu. */
    + '<div class="canh">'
    + L('Nút này <b>ghi sổ</b> số tiền mặt đã thu, không mở ngăn tiền của ghế. Bấm một lần thôi — '
        + 'mỗi lần bấm là một dòng doanh thu.',
        'This button <b>records</b> the cash you collected; it does not open the chair\'s cash box. '
        + 'Press it once — every press is another revenue entry.') + '</div>'
    + '<div class="hop-nut">'
    + '<button id="chot-huy" class="ghost">' + L('Thoát','Cancel') + '</button>'
    + '<button id="chot-ok" class="on">' + L('Xác nhận thu','Confirm') + '</button>'
    + '</div></div>';
  document.body.appendChild(d);

  var o = document.getElementById('chot-tien');
  [].forEach.call(d.querySelectorAll('[data-phim]'), function(b){
    b.onclick = function(){
      var k = b.getAttribute('data-phim');
      var v = (o.value || '').replace(/\D/g, '');
      if (k === '⌫') v = v.slice(0, -1); else v = (v + k).slice(0, 12);
      CHOT.go = v;
      o.value = v ? Number(v).toLocaleString('vi-VN') : '';
    };
  });
  o.addEventListener('input', function(){
    var v = (o.value || '').replace(/\D/g, '').slice(0, 12);
    CHOT.go = v;
    o.value = v ? Number(v).toLocaleString('vi-VN') : '';
  });
  document.getElementById('chot-huy').onclick = dongChotCa;
  d.onclick = function(ev){ if (ev.target === d) dongChotCa(); };
  document.getElementById('chot-ok').onclick = function(){
    var v = Number((o.value || '').replace(/\D/g, '')) || 0;
    if (v <= 0) { alert(L('Chưa nhập số tiền mặt đã đếm được.','Enter the cash amount you counted.'));
      o.focus(); return; }
    if (!confirm(L('Ghi ' + v.toLocaleString('vi-VN') + 'đ tiền mặt cho ghế ' + CHOT.ma + '?',
        'Record ' + v.toLocaleString('vi-VN') + 'đ cash for chair ' + CHOT.ma + '?'))) return;
    dongChotCa();
    lam('tien_mat', { ma_may: CHOT.ma, so_tien: v });
  };
  o.focus();
}

function dongChotCa(){
  var d = document.getElementById('man-chot');
  if (d) d.remove();
  CHOT = null;
}

/* Đồng hồ dải đầu trang. Mốc là giờ MÁY CHỦ (`D.luc`), sau đó tự tích từng giây — lấy giờ điện
   thoại thì nó lệch với mọi con số khác trên trang, mà hai loại giờ cạnh nhau là mời người ta
   đối chiếu nhầm. Mỗi lượt hỏi máy chủ lại đặt mốc, nên nó không trôi được. */
var dhTop = null;
function chayDongHoTop(){
  if (dhTop) { clearInterval(dhTop); dhTop = null; }
  var o = document.getElementById('dh-top');
  if (!o || !D || !D.luc) return;
  var m = String(D.luc).match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
  if (!m) return;
  var g = Number(m[1]), p = Number(m[2]), gi = Number(m[3] || 0);
  var dau = String(D.luc).slice(0, m.index);
  function ve2(n){ return String(n).padStart(2,'0'); }
  dhTop = setInterval(function(){
    if (document.hidden) return;
    gi++; if (gi > 59) { gi = 0; p++; }
    if (p > 59) { p = 0; g = (g + 1) % 24; }
    o.textContent = dau + ve2(g) + ':' + ve2(p) + ':' + ve2(gi);
  }, 1000);
}

function noi(){
  henLai();
  chayDongHo();
  chayDongHoTop();
  noiNN();
  document.getElementById('lam-moi').onclick = function(){ tai(); };
  document.getElementById('thoat').onclick = function(){
    goi('logout', {}, function(){ TOK = null; try{localStorage.removeItem('vhg_tok');}catch(e){} veLogin(''); });
  };
  [].forEach.call(document.querySelectorAll('[data-ky]'), function(b){
    b.onclick = function(){ KY = b.getAttribute('data-ky'); tai(); };
  });
  [].forEach.call(document.querySelectorAll('[data-tab]'), function(b){
    b.onclick = function(){
      TAB = b.getAttribute('data-tab');
      try { localStorage.setItem('vhg_tab', TAB); } catch(e) {}
      /* Vẽ lại từ dữ liệu ĐANG CÓ, không gọi lại máy chủ: đổi tab không phải đổi dữ liệu, và
         trên 4G mỗi lượt gọi thừa là một lần chờ. */
      ve();
    };
  });
  [].forEach.call(document.querySelectorAll('[data-kd]'), function(b){
    b.onclick = function(){
      var m = b.getAttribute('data-kd');
      if (!confirm(L(
        'Khởi động lại ghế ' + m + '?\n\nGhế sẽ tự khởi động khi đang RẢNH — nếu có khách đang '
          + 'massage thì nó chờ hết lượt rồi mới khởi động, không cắt ngang.\n'
          + 'Sau khi khởi động, ghế mất khoảng 30 giây mới gửi nhịp lại.',
        'Reboot chair ' + m + '?\n\nThe chair reboots itself once it is IDLE — if someone is in it, '
          + 'it waits for the session to finish and does not cut in.\n'
          + 'After rebooting it takes about 30 seconds to send a heartbeat again.'))) return;
      lam('khoi_dong_lai', { ma_may: m });
    };
  });
  function so(attr, ma){
    var el = document.querySelector('[' + attr + '="' + ma + '"]');
    return el ? el.value : 0;
  }
  /* ⚠️ CHẶN BẤM HAI LẦN. Trên 4G một lượt bấm có thể mất 3 giây không thấy gì xảy ra, và phản
     xạ của mọi người là bấm lại. Với "Thu tiền mặt" thì bấm hai lần là GHI HAI LẦN — số tiền
     thật vào sổ gấp đôi. Khoá nút cho tới khi máy chủ trả lời. */
  function lam(viec, d){
    if (ban) return;
    ban = true;
    [].forEach.call(document.querySelectorAll('button'), function(b){ b.disabled = true; });
    goi(viec, d, function(r){
      ban = false;
      if (r && r.ok === false && r.error) alert(r.error);
      else if (r && r.thong_bao) alert(r.thong_bao);
      tai();
    });
  }
  [].forEach.call(document.querySelectorAll('[data-bat]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-bat');
      var ly = prompt(L('Bật ghế ' + m + ' — đây là CHO KHÔNG một lượt.\nLý do:',
        'Start chair ' + m + ' — this is a FREE session.\nReason:')); if (ly === null) return;
      lam('bat', { ma_may: m, phut: so('data-phut', m), ly_do: ly }); };
  });
  [].forEach.call(document.querySelectorAll('[data-tat]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-tat');
      if (!confirm(L('Tắt ghế ' + m + ' ngay?','Stop chair ' + m + ' now?'))) return;
      lam('tat', { ma_may: m }); };
  });
  [].forEach.call(document.querySelectorAll('[data-gan]'), function(b){
    b.onclick = function(){
      var cu = b.getAttribute('data-gan');
      var o  = document.querySelector('[data-gma="' + cu + '"]');
      var cs = document.querySelector('[data-gcs="' + cu + '"]');
      var moi = (o && o.value || '').trim();
      if (!moi) { alert(L('Chưa nhập mã ghế.','Enter a chair code.')); if (o) o.focus(); return; }
      /* Chặn ngay trên máy: mã đi vào nội dung chuyển khoản khách GÕ TAY, có dấu hay khoảng
         trắng là khách gõ sai và ghế không chạy. Máy chủ cũng chặn — chặn hai lớp vì câu báo
         lỗi ở đây tới ngay, còn đi một vòng máy chủ thì trên 4G là vài giây đứng nhìn. */
      if (!/^[A-Za-z0-9]{1,20}$/.test(moi)) {
        alert(L('Mã chỉ được gồm chữ và số, không dấu, không khoảng trắng.',
          'The code may contain letters and digits only — no accents, no spaces.')); return;
      }
      lam('gan_ma', { ma_cu: cu, ma_moi: moi, coso_id: cs ? cs.value : 0 });
    };
  });
  [].forEach.call(document.querySelectorAll('[data-mat]'), function(b){
    b.onclick = function(){ moChotCa(b.getAttribute('data-mat')); };
  });
}

/* Mở lại trang sau khi khoá màn: hỏi NGAY chứ đừng đợi hết nhịp. Người ta mở ra là để xem
   ngay bây giờ, không phải để nhìn số liệu của 30 giây trước. */
document.addEventListener('visibilitychange', function(){
  if (!document.hidden && TOK && !ban && !CHOT) tai(true);
});

if (TOK) tai(); else veLogin('');
})();
JS;
	}
}
