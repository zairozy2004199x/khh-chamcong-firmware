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

		if ( 'bat' === $viec || 'tat' === $viec ) {
			$r = VHG_May::dat_lenh(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '',
				'bat' === $viec ? 'on' : 'off',
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
				'gia'     => (int) $m['gia'],
				'phut'    => (int) $m['phut'],
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
		echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>Ghế massage — K&amp;H</title>'
			/* Người đứng quầy lưu trang này vào màn hình chính điện thoại. */
			. '<meta name="theme-color" content="#1b1f38">'
			. '<style>' . self::css() . '</style></head><body>'
			. '<div id="app"></div>'
			. '<script>window.VHG_API=' . wp_json_encode( $api ) . ';</script>'
			. '<script>' . self::js() . '</script>'
			. '</body></html>';
	}

	private static function css() {
		return <<<'CSS'
*{box-sizing:border-box}
body{margin:0;background:#171a2e;color:#e8ebff;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:1000px;margin:0 auto;padding:14px}
h1{font-size:19px;margin:0}
h1 small{display:block;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#8d93c4;font-weight:400;margin-top:3px}
.top{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
.top .sp{flex:1}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
button{font:inherit;cursor:pointer;border-radius:9px;border:1px solid #343a63;background:#232848;color:#e8ebff;padding:7px 13px}
button:hover{background:#2b3155}
button.on{background:#f0b429;border-color:#f0b429;color:#221a00;font-weight:600}
button.ghost{background:transparent}
input{font:inherit;border-radius:9px;border:1px solid #343a63;background:#151831;color:#e8ebff;padding:7px 10px;width:100%}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:10px;margin-bottom:14px}
.kpi{background:#1e2240;border:1px solid #2c3157;border-radius:12px;padding:12px 14px}
.kpi .lb{font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#8d93c4}
.kpi .vl{font-size:21px;font-weight:700;margin-top:3px;word-break:break-all}
.kpi .sb{font-size:12px;color:#8d93c4}
.vl.a{color:#f0b429}.vl.b{color:#5fa8ff}.vl.c{color:#4ade80}.vl.d{color:#e8ebff}
.card{background:#1e2240;border:1px solid #2c3157;border-radius:12px;padding:12px 14px;margin-bottom:14px}
.card h2{font-size:14px;margin:0 0 10px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#8d93c4;font-weight:500;padding:0 8px 7px 0;border-bottom:1px solid #2c3157}
td{padding:8px 8px 8px 0;border-bottom:1px solid #252a4b;vertical-align:middle}
tr:last-child td{border-bottom:0}
.r{text-align:right}
.pill{display:inline-block;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:600}
.p-ok{background:#12351f;color:#4ade80}.p-run{background:#2a2410;color:#f0b429}
.p-wait{background:#111f3d;color:#5fa8ff}.p-off{background:#3a1418;color:#ff8087}
.warn{background:#3a1418;border:1px solid #7c2732;border-radius:12px;padding:12px 14px;margin-bottom:14px}
.warn b{color:#ff8087}
.note{background:#2a2410;border:1px solid #6b551a;border-radius:12px;padding:12px 14px;margin-bottom:14px}
.note b{color:#f0b429}
.mut{color:#8d93c4;font-size:12px}
.login{max-width:330px;margin:14vh auto;padding:26px 22px;background:#1e2240;border:1px solid #2c3157;border-radius:14px;text-align:center}
.login h1{margin-bottom:6px}
.login input{text-align:center;letter-spacing:.5em;font-size:21px;margin:16px 0 10px}
.err{color:#ff8087;font-size:13px;min-height:19px;margin-top:8px}
.act{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.act input{width:66px;padding:5px 7px}
.act select{font:inherit;border-radius:8px;border:1px solid #343a63;background:#151831;color:#e8ebff;padding:5px 7px;max-width:130px}
.note code{background:#151831;padding:1px 5px;border-radius:5px}
.act button{padding:5px 10px;font-size:12px}
@media(max-width:560px){.hide-sm{display:none}.wrap{padding:10px}}
CSS;
	}

	private static function js() {
		return <<<'JS'
(function(){
var API = window.VHG_API, TOK = null, KY = 'today', D = null, ban = false;
try { TOK = localStorage.getItem('vhg_tok'); } catch(e) {}

var app = document.getElementById('app');
function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, function(c){
  return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
function tien(n){ return (Number(n)||0).toLocaleString('vi-VN') + 'đ'; }
function mmss(s){ s=Math.max(0,Number(s)||0); var m=Math.floor(s/60);
  return m + ':' + String(s%60).padStart(2,'0'); }

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
    if (!r) { xong({ ok:false, error:'Không đọc được trả lời của máy chủ (mạng hoặc tường lửa).' }); return; }
    if (r.ma === 'het_phien') { TOK = null; try{localStorage.removeItem('vhg_tok');}catch(e){} veLogin('Phiên đã hết — đăng nhập lại.'); return; }
    xong(r);
  };
  x.send(JSON.stringify(d));
}

// ------------------------------------------------------------------ đăng nhập
function veLogin(loi){
  app.innerHTML =
    '<div class="login"><h1>Ghế massage<small>K&amp;H · doanh thu &amp; tình trạng</small></h1>'
    + '<input id="pin" type="tel" inputmode="numeric" maxlength="8" placeholder="PIN" autocomplete="off">'
    + '<button id="vao" class="on" style="width:100%">Vào</button>'
    + '<div class="err" id="e">' + esc(loi||'') + '</div></div>';
  var pin = document.getElementById('pin'), e = document.getElementById('e');
  function thu(){
    var v = (pin.value||'').trim();
    if (!v) { e.textContent = 'Chưa nhập PIN.'; return; }
    e.textContent = 'Đang kiểm…';
    goi('login', { pin: v }, function(r){
      if (!r.ok) { e.textContent = r.error || 'PIN không đúng'; pin.value=''; pin.focus(); return; }
      TOK = r.token; try{localStorage.setItem('vhg_tok',TOK);}catch(er){}
      tai();
    });
  }
  document.getElementById('vao').onclick = thu;
  pin.addEventListener('keydown', function(ev){ if (ev.key === 'Enter') thu(); });
  pin.focus();
}

// ------------------------------------------------------------------ màn chính
function tai(){
  goi('so_lieu', { ky: KY }, function(r){
    if (!r.ok) { veLogin(r.error || ''); return; }
    D = r; ve();
  });
}

function ve(){
  var t = D.tong, h = '';

  h += '<div class="wrap"><div class="top">'
    + '<h1>Ghế massage<small>' + esc(D.ai.name) + ' · ' + esc(D.ai.role) + '</small></h1>'
    + '<span class="sp"></span>'
    + '<span class="mut">' + esc(D.luc) + '</span>'
    + '<button id="lam-moi" class="ghost">↻</button>'
    + '<button id="thoat" class="ghost">Thoát</button></div>';

  h += '<div class="tabs">';
  [['today','Hôm nay'],['week','Tuần này'],['month','Tháng này'],['year','Năm nay'],['all','Tất cả']]
    .forEach(function(k){ h += '<button data-ky="' + k[0] + '"' + (KY===k[0]?' class="on"':'') + '>' + k[1] + '</button>'; });
  h += '</div>';

  /* GHẾ CHỜ GÁN — trên cùng luôn, trên cả cảnh báo mất kết nối.
     Ghế vừa cắm điện xong là thứ người đang đứng cạnh nó cần thấy đầu tiên; và chừng nào chưa
     gán mã thì nó KHÔNG vẽ được QR, tức là không thu được đồng nào. */
  if (D.choGan && D.choGan.length) {
    h += '<div class="note"><b>' + D.choGan.length + ' ghế vừa nối mạng, chưa có mã</b> — '
      + 'ghế chưa gán mã thì không hiện được QR. Đặt mã ngắn (VD <code>AMTP01</code>): mã này đi '
      + 'vào nội dung chuyển khoản khách gõ tay.'
      + '<table style="margin-top:8px"><tr><th>MAC</th><th class="hide-sm">Tình trạng</th>'
      + '<th class="r">Gán mã + cơ sở</th></tr>'
      + D.choGan.map(function(g, i){
          return '<tr><td><code>' + esc(g.mac) + '</code><br><span class="mut">' + esc(g.ma) + '</span></td>'
            + '<td class="hide-sm"><span class="pill ' + (g.song?'p-ok':'p-off') + '">'
            + (g.song?'đang sống':'mất kết nối') + '</span></td>'
            + '<td class="r"><div class="act" style="justify-content:flex-end">'
            + '<input type="text" placeholder="AMTP01" data-gma="' + esc(g.ma) + '" style="width:96px">'
            + '<select data-gcs="' + esc(g.ma) + '"><option value="0">— cơ sở —</option>'
            + (D.coso||[]).map(function(c){
                return '<option value="' + c.id + '">' + esc(c.ten) + '</option>'; }).join('')
            + '</select><button data-gan="' + esc(g.ma) + '">Gán</button></div></td></tr>'; }).join('')
      + '</table></div>';
  }

  /* LUẬT 2: hỏng để TRÊN CÙNG, trên cả con số doanh thu. */
  var dut = D.may.filter(function(m){ return !m.song; });
  if (dut.length) {
    h += '<div class="warn"><b>' + dut.length + ' ghế mất kết nối</b> — '
      + esc(dut.map(function(m){ return m.ma + (m.coso ? ' (' + m.coso + ')' : ''); }).join(', '))
      + '. Khách vẫn quét được tem QR trên ghế, tiền vẫn vào, nhưng ghế KHÔNG chạy.</div>';
  }
  if (D.cho.length) {
    h += '<div class="note"><b>' + D.cho.length + ' lượt đã trả tiền mà ghế chưa nhận</b> — '
      + 'bình thường ghế lấy trong ~10 giây.<table style="margin-top:8px"><tr><th>Lúc</th><th>Ghế</th>'
      + '<th class="r">Số tiền</th></tr>'
      + D.cho.slice(0,8).map(function(c){
          return '<tr><td>' + esc(c.luc) + '</td><td>' + esc(c.ma_may) + '</td>'
            + '<td class="r">' + tien(c.so_tien) + '</td></tr>'; }).join('')
      + '</table></div>';
  }

  h += '<div class="kpis">'
    + kpi('Tổng doanh thu', tien(t.tong), t.so_luot + ' lượt', 'a')
    + kpi('Chuyển khoản (QR)', tien(t.qr), t.qr_luot + ' lượt', 'b')
    + kpi('Tiền mặt', tien(t.tien_mat), t.tien_mat_luot + ' lượt', 'c')
    + kpi('Đang chờ ghế nhận', String(D.cho.length), 'đã trả, chưa chạy', 'd')
    + '</div>';

  // --- tình trạng ghế
  h += '<div class="card"><h2>Tình trạng ghế</h2><table><tr><th>Ghế</th><th class="hide-sm">Cơ sở</th>'
    + '<th>Trạng thái</th><th class="hide-sm">Còn lại</th><th class="r">Việc</th></tr>';
  if (!D.may.length) h += '<tr><td colspan="5" class="mut">Chưa khai ghế nào.</td></tr>';
  D.may.forEach(function(m){
    var p = !m.song ? ['p-off','Mất kết nối']
      : m.tt === 'running' ? ['p-run','Đang chạy']
      : m.tt === 'wait_pay' ? ['p-wait','Chờ trả tiền'] : ['p-ok','Rảnh'];
    h += '<tr><td><b>' + esc(m.ma) + '</b></td>'
      + '<td class="hide-sm">' + esc(m.coso || '(chưa gán)') + '</td>'
      + '<td><span class="pill ' + p[0] + '">' + p[1] + '</span></td>'
      + '<td class="hide-sm">' + (m.tt === 'running' && m.song ? mmss(m.con_lai) : '') + '</td>'
      + '<td class="r"><div class="act" style="justify-content:flex-end">'
      + '<input type="number" min="1" max="60" value="' + m.phut + '" data-phut="' + esc(m.ma) + '">'
      + '<button data-bat="' + esc(m.ma) + '">Bật</button>'
      + '<button data-tat="' + esc(m.ma) + '">Tắt</button>'
      + '<input type="number" min="1000" step="1000" value="' + m.gia + '" data-tien="' + esc(m.ma) + '" style="width:84px">'
      + '<button data-mat="' + esc(m.ma) + '">Thu mặt</button>'
      + '</div></td></tr>';
  });
  h += '</table><p class="mut" style="margin:9px 0 0">Bật tay là <b>cho không một lượt</b> — hệ thống '
    + 'ghi lại ai bấm và lúc nào, để cuối tháng còn giải thích được vì sao một ghế chạy nhiều hơn '
    + 'số tiền thu.</p></div>';

  // --- theo cơ sở
  h += bang('Theo cơ sở', ['Cơ sở','Lượt','QR','Tiền mặt','Tổng'],
    Object.keys(t.theo_coso).map(function(k){ var c = t.theo_coso[k];
      return ['<b>' + esc(c.coso) + '</b>', c.so_luot, tien(c.qr), tien(c.tien_mat), '<b>' + tien(c.tong) + '</b>']; }));

  // --- theo máy
  h += bang('Theo ghế', ['Ghế','Cơ sở','Lượt','QR','Tiền mặt','Tổng'],
    Object.keys(t.theo_may).map(function(k){ var m = t.theo_may[k];
      return ['<b>' + esc(m.may) + '</b>', esc(m.coso), m.so_luot, tien(m.qr), tien(m.tien_mat), '<b>' + tien(m.tong) + '</b>']; }));

  // --- giao dịch
  h += bang('Giao dịch gần đây', ['Thời gian','Ghế','Nguồn','Nội dung','Số tiền'],
    D.gd.map(function(g){
      return [esc(g.luc), esc(g.may || '—'),
        '<span class="pill ' + (g.nguon === 'cash' ? 'p-ok' : 'p-wait') + '">'
          + (g.nguon === 'cash' ? 'Tiền mặt' : String(g.nguon).toUpperCase()) + '</span>',
        '<span class="mut">' + esc(g.noi_dung) + '</span>', tien(g.so_tien)]; }));

  h += '</div>';
  app.innerHTML = h;
  noi();
}

function kpi(lb, vl, sb, m){
  return '<div class="kpi"><div class="lb">' + lb + '</div><div class="vl ' + m + '">' + vl
    + '</div><div class="sb">' + sb + '</div></div>';
}
function bang(ten, cot, hang){
  var h = '<div class="card"><h2>' + ten + '</h2><table><tr>'
    + cot.map(function(c,i){ return '<th' + (i>=cot.length-3?' class="r"':'') + '>' + c + '</th>'; }).join('')
    + '</tr>';
  if (!hang.length) h += '<tr><td colspan="' + cot.length + '" class="mut">Chưa có số liệu kỳ này.</td></tr>';
  hang.forEach(function(r){
    h += '<tr>' + r.map(function(o,i){ return '<td' + (i>=cot.length-3?' class="r"':'') + '>' + o + '</td>'; }).join('') + '</tr>';
  });
  return h + '</table></div>';
}

function noi(){
  document.getElementById('lam-moi').onclick = tai;
  document.getElementById('thoat').onclick = function(){
    goi('logout', {}, function(){ TOK = null; try{localStorage.removeItem('vhg_tok');}catch(e){} veLogin(''); });
  };
  [].forEach.call(document.querySelectorAll('[data-ky]'), function(b){
    b.onclick = function(){ KY = b.getAttribute('data-ky'); tai(); };
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
      var ly = prompt('Bật ghế ' + m + ' — đây là CHO KHÔNG một lượt.\nLý do:'); if (ly === null) return;
      lam('bat', { ma_may: m, phut: so('data-phut', m), ly_do: ly }); };
  });
  [].forEach.call(document.querySelectorAll('[data-tat]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-tat');
      if (!confirm('Tắt ghế ' + m + ' ngay?')) return;
      lam('tat', { ma_may: m }); };
  });
  [].forEach.call(document.querySelectorAll('[data-gan]'), function(b){
    b.onclick = function(){
      var cu = b.getAttribute('data-gan');
      var o  = document.querySelector('[data-gma="' + cu + '"]');
      var cs = document.querySelector('[data-gcs="' + cu + '"]');
      var moi = (o && o.value || '').trim();
      if (!moi) { alert('Chưa nhập mã ghế.'); if (o) o.focus(); return; }
      /* Chặn ngay trên máy: mã đi vào nội dung chuyển khoản khách GÕ TAY, có dấu hay khoảng
         trắng là khách gõ sai và ghế không chạy. Máy chủ cũng chặn — chặn hai lớp vì câu báo
         lỗi ở đây tới ngay, còn đi một vòng máy chủ thì trên 4G là vài giây đứng nhìn. */
      if (!/^[A-Za-z0-9]{1,20}$/.test(moi)) {
        alert('Mã chỉ được gồm chữ và số, không dấu, không khoảng trắng.'); return;
      }
      lam('gan_ma', { ma_cu: cu, ma_moi: moi, coso_id: cs ? cs.value : 0 });
    };
  });
  [].forEach.call(document.querySelectorAll('[data-mat]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-mat'), v = so('data-tien', m);
      if (!confirm('Ghi ' + Number(v).toLocaleString('vi-VN') + 'đ tiền mặt cho ghế ' + m + '?')) return;
      lam('tien_mat', { ma_may: m, so_tien: v }); };
  });
}

if (TOK) tai(); else veLogin('');
})();
JS;
	}
}
