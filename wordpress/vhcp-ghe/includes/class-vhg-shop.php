<?php
/**
 * TRANG MINI BÁN MÃ — trang của KHÁCH, không phải của nhân viên.
 *
 * =============================================================================================
 * BA ĐIỀU QUYẾT ĐỊNH TOÀN BỘ THIẾT KẾ TRANG NÀY
 * =============================================================================================
 * 1. KHÔNG CÓ CỔNG PIN. Đây là trang khách vãng lai mở bằng cách quét tem dán cạnh thùng tiền.
 *    Bắt đăng nhập là mất khách ngay ở bước đầu. Bù lại: trang này KHÔNG đọc được doanh thu,
 *    KHÔNG bật/tắt được ghế, và mọi thứ nó làm đều phải trả tiền trước.
 *
 * 2. 🔴 KHÔNG DỰNG MÃ QR ĐỂ KHÁCH QUÉT. Khách đang cầm ĐÚNG cái điện thoại hiện trang này —
 *    không ai quét được mã QR trên màn hình của chính máy mình. Nên thay vì mã QR, trang đưa
 *    thẳng số tài khoản, số tiền và nội dung chuyển khoản, mỗi thứ một nút SAO CHÉP.
 *    Bản trước của thiết kế này định vẽ mã QR; nghĩ kỹ thì đó là một mã không ai quét được.
 *
 * 3. NỘI DUNG CHUYỂN KHOẢN LÀ THỨ DUY NHẤT NỐI TIỀN VỚI ĐƠN. Gõ sai một ký tự là tiền vào tài
 *    khoản mà không ai biết của đơn nào. Nên nó phải to, có nút sao chép, và có câu cảnh báo
 *    ngay cạnh — không nhét vào chữ nhỏ.
 *
 * ⚠️ HÃM THỬ Ở Ô TRA MÃ. Số điện thoại là thứ đoán được; không hãm thì một máy dò hết 10.000 PIN
 *    của một số trong vài phút. Xem `bi_khoa()`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Shop {

	public static function slug() {
		$s = get_option( 'vhg_slug_ma' );
		$s = $s ? sanitize_title( $s ) : 'mua-ma';
		return $s ? $s : 'mua-ma';
	}

	public static function url( $ma_may = '' ) {
		$u = get_option( 'permalink_structure' )
			? home_url( '/' . self::slug() . '/' )
			: add_query_arg( 'vhg', 'shop', home_url( '/' ) );
		$m = trim( (string) $ma_may );
		return '' !== $m ? add_query_arg( 'ghe', $m, $u ) : $u;
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhg_shop=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'chan_chuyen_huong' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ), 0 );
	}

	public static function query_vars( $v ) { $v[] = 'vhg_shop'; return $v; }

	private static function la_trang() {
		if ( 1 === (int) get_query_var( 'vhg_shop' ) ) { return true; }
		if ( isset( $_GET['vhg'] ) && 'shop' === $_GET['vhg'] ) { return true; }
		$d = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$d = trim( (string) parse_url( $d, PHP_URL_PATH ), '/' );
		$s = self::slug();
		return $d === $s || substr( $d, - ( strlen( $s ) + 1 ) ) === '/' . $s;
	}

	/* Cùng lý do với trang nhân viên: một lượt bị chuyển hướng là mất trọn thân POST. */
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

	// ===================================================================== hãm thử

	private static function khoa_key( $viec ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhg_shop_' . $viec . '_' . md5( $ip );
	}
	/* 15 lượt / 10 phút cho mỗi IP. Đủ rộng cho người gõ nhầm PIN vài lần, quá hẹp cho máy dò:
	   10.000 PIN với nhịp này là hơn bốn ngày. */
	private static function bi_khoa( $viec ) { return (int) get_transient( self::khoa_key( $viec ) ) >= 15; }
	private static function dem( $viec ) {
		$k = self::khoa_key( $viec );
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );
	}

	// ===================================================================== API

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
		$viec = isset( $_GET['api'] ) ? (string) $_GET['api'] : (string) ( isset( $d['api'] ) ? $d['api'] : '' );
		$viec = preg_replace( '/[^a-z_]/', '', strtolower( $viec ) );

		if ( 'goi' === $viec ) {
			self::tra( array( 'ok' => true, 'goi' => VHG_Ma::ds_menh_gia(),
				'ghe' => self::ghe_tu_dia_chi( $d ), 'ds_ghe' => self::ds_ghe() ) );
			return;
		}

		if ( 'dat' === $viec ) {
			$r = VHG_Ma::dat_don(
				isset( $d['sdt'] ) ? $d['sdt'] : '',
				isset( $d['pin'] ) ? $d['pin'] : '',
				isset( $d['menh_gia'] ) ? $d['menh_gia'] : 0,
				isset( $d['so_luong'] ) ? $d['so_luong'] : 1 );
			if ( empty( $r['ok'] ) ) { self::tra( $r ); return; }
			$tk = VHG_May::nhan_tien_cua( array() );
			if ( '' === $tk['so_tk'] ) {
				self::tra( array( 'ok' => false,
					'error' => 'Cửa hàng chưa khai tài khoản nhận tiền — báo nhân viên giúp em.' ) );
				return;
			}
			$r['so_tk']    = $tk['so_tk'];
			$r['ten_tk']   = $tk['ten_tk'];
			$r['bin']      = $tk['bin'];
			$r['noi_dung'] = VHG_QR::noi_dung_mua( $r['ma_don'] );
			self::tra( $r );
			return;
		}

		/* Trang hỏi lại xem tiền về chưa. KHÔNG trả gì ngoài "xong hay chưa" + bộ mã: đơn mang
		   số điện thoại và PIN băm của khách, đưa ra là rò dữ liệu người khác nếu ai đó đoán
		   trúng mã đơn. */
		if ( 'soi' === $viec ) {
			$don = VHG_Ma::don( isset( $d['ma_don'] ) ? $d['ma_don'] : '' );
			if ( ! $don ) { self::tra( array( 'ok' => false, 'error' => 'Không thấy đơn này.' ) ); return; }
			$xong = ! empty( $don['xong_luc'] );
			$ma   = array();
			if ( $xong ) {
				foreach ( VHG_Ma::ds_ma_cua_don( $don['ma_don'] ) as $m ) { $ma[] = VHG_Ma::ma_dep( $m ); }
			}
			self::tra( array( 'ok' => true, 'xong' => $xong ? 1 : 0, 'ma' => $ma ) );
			return;
		}

		if ( 'tra' === $viec ) {
			if ( self::bi_khoa( 'tra' ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút rồi tra lại, hoặc nhờ nhân viên.' ) );
				return;
			}
			self::dem( 'tra' );
			$r = VHG_Ma::tra( isset( $d['sdt'] ) ? $d['sdt'] : '', isset( $d['pin'] ) ? $d['pin'] : '' );
			self::tra( $r );
			return;
		}

		if ( 'dung' === $viec ) {
			if ( self::bi_khoa( 'dung' ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Thử quá nhiều lần — chờ 10 phút rồi thử lại.' ) );
				return;
			}
			self::dem( 'dung' );
			self::tra( VHG_Ma::dung(
				isset( $d['ma'] ) ? $d['ma'] : '',
				isset( $d['ma_may'] ) ? $d['ma_may'] : '' ) );
			return;
		}

		self::tra( array( 'ok' => false, 'error' => 'Việc không hợp lệ.' ) );
	}

	/**
	 * Ghế lấy từ địa chỉ (`?ghe=AMTP01`) — tem dán ở mỗi ghế mang mã ghế đó.
	 * ⚠️ Chỉ nhận mã CÓ THẬT trong bảng máy. Nhận bừa chuỗi trên địa chỉ là cho phép người ta
	 *    dựng một liên kết trỏ tới "ghế" không tồn tại rồi tiêu mã của mình vào hư không.
	 */
	public static function ghe_tu_dia_chi( $d = array() ) {
		$g = '';
		if ( isset( $_GET['ghe'] ) ) { $g = sanitize_text_field( wp_unslash( $_GET['ghe'] ) ); }
		if ( '' === $g && isset( $d['ghe'] ) ) { $g = sanitize_text_field( (string) $d['ghe'] ); }
		$g = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $g ) );
		if ( '' === $g || ! VHG_May::may( $g ) ) { return ''; }
		return $g;
	}

	/** Danh sách ghế để khách tự chọn khi tem không mang mã ghế. */
	public static function ds_ghe() {
		$ra = array();
		foreach ( VHG_May::ds_may() as $m ) {
			if ( '' === (string) $m['ma'] || '?' === $m['ma'][0] ) { continue; }
			$ra[] = array( 'ma' => (string) $m['ma'], 'coso' => (string) $m['coso_ten'] );
		}
		return $ra;
	}


	private static function css() {
		return <<<'CSS'
*{box-sizing:border-box}
/* Nền dùng CHUNG với trang nhân viên (cùng ô khai ảnh trong Cài đặt): hai trang của cùng một
   cửa hàng mà hai kiểu nền là khách tưởng mình đi lạc sang chỗ khác — mà đây lại là trang họ
   sắp chuyển tiền vào. */
body::before{content:"";position:fixed;inset:0;z-index:-2;
  background:radial-gradient(1100px 600px at 80% 6%,#3a2f1c 0%,transparent 62%),
             radial-gradient(900px 520px at 10% 94%,#1d2647 0%,transparent 60%),
             linear-gradient(160deg,#12141f 0%,#171a2e 46%,#0f1120 100%);
  background-size:cover;background-position:center}
body.co-anh::before{background-image:var(--nen);background-size:cover;background-position:center}
body::after{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:linear-gradient(180deg,rgba(9,10,18,.86) 0%,rgba(9,10,18,.74) 40%,rgba(9,10,18,.90) 100%)}
body{margin:0;background:#12141f;color:#e8ebff;min-height:100vh;
  font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:520px;margin:0 auto;padding:18px 14px 40px}

/* --- Đầu trang --- */
.hero{text-align:center;padding:14px 0 20px}
.hero .o{width:52px;height:52px;margin:0 auto 12px;display:flex;align-items:center;
  justify-content:center;border-radius:15px;font-size:26px;background:rgba(240,180,41,.13);
  border:1px solid rgba(240,180,41,.34)}
.hero h1{margin:0 0 6px;font-size:25px;line-height:1.25;letter-spacing:-.01em}
.hero .sub{color:#a79a7d;font-size:13px;letter-spacing:.1em;text-transform:uppercase}
/* Dải "giảm tới X%" — lý do duy nhất khách dừng lại đọc trang này. Nên nó to, và nó ở trên cùng. */
.deal{margin:16px 0 4px;padding:13px 16px;border-radius:14px;text-align:center;
  background:linear-gradient(135deg,rgba(240,180,41,.22),rgba(240,180,41,.08));
  border:1px solid rgba(240,180,41,.45)}
.deal b{color:#f0b429;font-size:19px}
.deal div{font-size:13px;color:#cfc3a6;margin-top:3px}

/* --- Thẻ gói --- */
.goi{display:grid;gap:12px;margin:18px 0}
.g{position:relative;padding:16px;border-radius:16px;cursor:pointer;text-align:left;
  background:rgba(22,25,40,.80);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
  border:1.5px solid rgba(255,255,255,.10);transition:border-color .15s,transform .1s}
.g:hover{border-color:rgba(240,180,41,.45)}
.g:active{transform:scale(.995)}
.g.chon{border-color:#f0b429;background:rgba(45,38,18,.86)}
.g .ten{font-weight:700;font-size:17px}
.g .mo{font-size:12.5px;color:#9aa0c2;margin-top:2px}
.g .gia{display:flex;align-items:baseline;gap:10px;margin-top:10px;flex-wrap:wrap}
.g .moi{font-size:24px;font-weight:800;color:#f0b429;font-variant-numeric:tabular-nums}
/* Giá gốc GẠCH NGANG: con số bị bỏ đi mới là thứ làm người ta thấy mình đang được lợi. */
.g .cu{text-decoration:line-through;color:#8d93c4;font-size:15px}
.g .pt{margin-left:auto;background:#f0b429;color:#221a00;font-weight:800;font-size:13px;
  padding:3px 9px;border-radius:99px}
.g .vip{position:absolute;top:-9px;right:12px;background:#f0b429;color:#221a00;font-weight:800;
  font-size:10px;letter-spacing:.1em;padding:3px 9px;border-radius:99px}

/* --- Khối chung --- */
.card{background:rgba(22,25,40,.78);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
  border:1px solid rgba(255,255,255,.10);border-radius:16px;padding:16px;margin:14px 0;
  box-shadow:0 8px 26px rgba(0,0,0,.34)}
.card h2{font-size:12px;margin:0 0 12px;letter-spacing:.12em;text-transform:uppercase;
  color:#f0b429;font-weight:700;padding-left:10px;border-left:3px solid #f0b429}
label{display:block;font-size:12.5px;color:#a79a7d;margin:12px 0 5px}
input,select{font:inherit;width:100%;border-radius:11px;padding:12px 13px;
  border:1px solid rgba(255,255,255,.16);background:rgba(10,12,22,.6);color:#e8ebff}
input:focus,select:focus{outline:none;border-color:#f0b429}
button{font:inherit;cursor:pointer;border-radius:11px;padding:13px 16px;font-weight:600;
  border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.07);color:#e8ebff}
button.chinh{background:#f0b429;border-color:#f0b429;color:#221a00;width:100%;font-size:17px;
  font-weight:800;padding:15px}
button.chinh:disabled{opacity:.5;cursor:not-allowed}
.mut{color:#9aa0c2;font-size:12.5px}
.err{color:#ff8087;font-size:13.5px;margin-top:10px;min-height:1px}
.tabs{display:flex;gap:6px;margin-bottom:14px}
.tabs button{flex:1;padding:11px 8px;font-size:14px}
.tabs button.on{background:#f0b429;border-color:#f0b429;color:#221a00;font-weight:700}

/* --- Ô chuyển khoản: to, rõ, có nút sao chép ---
   Nội dung chuyển khoản là thứ DUY NHẤT nối tiền với đơn. Gõ sai một ký tự là tiền vào tài
   khoản mà không ai biết của đơn nào. Nên nó không được nằm trong chữ nhỏ. */
.ck{display:flex;align-items:center;gap:10px;padding:12px 13px;border-radius:12px;margin:9px 0;
  background:rgba(10,12,22,.55);border:1px solid rgba(255,255,255,.12)}
.ck .nh{font-size:11px;color:#a79a7d;letter-spacing:.06em;text-transform:uppercase}
.ck .gt{font-size:17px;font-weight:700;word-break:break-all;font-variant-numeric:tabular-nums}
.ck .gt.nho{font-size:15px}
.ck button{padding:9px 12px;font-size:13px;flex:none}
.ck.nhan{border-color:rgba(240,180,41,.5);background:rgba(45,38,18,.6)}
.ck.nhan .gt{color:#f0b429}
.cho{text-align:center;padding:16px;color:#f0b429;font-weight:600}

/* --- Mã đã phát --- */
.ma{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:9px 0;
  padding:14px;border-radius:13px;background:rgba(45,38,18,.7);border:1px solid rgba(240,180,41,.45)}
.ma .m{font-size:22px;font-weight:800;letter-spacing:.08em;color:#f0b429;font-variant-numeric:tabular-nums}
.ma .g{font-size:12px;color:#cfc3a6;background:none;border:0;padding:0}
.ma.het{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.12)}
.ma.het .m{color:#8d93c4;text-decoration:line-through;font-size:19px}
.ok{background:rgba(18,53,31,.7);border:1px solid #2f6b45;border-radius:13px;padding:14px;
  color:#8ff0b0;margin:12px 0}
CSS;
	}


	private static function js() {
		return <<<'JS'
(function(){
var API = window.VHG_SHOP, GHE = window.VHG_GHE || '', TEN = window.VHG_TEN || 'POSH Massage';
var D = null, CHON = null, SL = 1, TAB = 'mua', DON = null, hen = null, ban = false;
var app = document.getElementById('app');

function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){
  return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
function tien(n){ return (Number(n)||0).toLocaleString('vi-VN') + 'đ'; }

function goi(viec, d, xong){
  d = d || {};
  var x = new XMLHttpRequest();
  x.open('POST', API + (API.indexOf('?')<0?'?':'&') + 'api=' + viec, true);
  x.setRequestHeader('Content-Type','application/json');
  x.onreadystatechange = function(){
    if (x.readyState !== 4) return;
    var r = null;
    try { r = JSON.parse(x.responseText); } catch(e){}
    /* Máy chủ trả rác (tường lửa hosting chèn trang chặn) KHÔNG được thành một câu báo lỗi khó
       hiểu — khách đang định trả tiền, họ cần biết nên làm gì tiếp. */
    if (!r) r = { ok:false, error:'Mạng đang chập chờn. Thử lại giúp em, hoặc gọi nhân viên.' };
    xong(r);
  };
  x.send(JSON.stringify(d));
}

/* Sao chép — có đường lui. `navigator.clipboard` chỉ chạy trên HTTPS và trên một số trình duyệt;
   không có đường lui thì nút bấm không làm gì cả và khách không hiểu vì sao. */
function chep(txt, nut){
  function xong(){ var cu = nut.textContent; nut.textContent = '✓ Đã chép';
    setTimeout(function(){ nut.textContent = cu; }, 1400); }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(txt).then(xong, function(){ tayChep(txt, xong); });
  } else { tayChep(txt, xong); }
}
function tayChep(txt, xong){
  var o = document.createElement('textarea');
  o.value = txt; o.style.position='fixed'; o.style.opacity='0';
  document.body.appendChild(o); o.select();
  try { document.execCommand('copy'); xong(); } catch(e){ prompt('Chép dòng này:', txt); }
  document.body.removeChild(o);
}

function dau(){
  return '<div class="hero"><div class="o">🎁</div>'
    + '<h1>Mua mã giảm giá</h1>'
    + '<div class="sub">' + esc(TEN) + '</div></div>';
}

function tab(){
  return '<div class="tabs">'
    + '<button data-tab="mua"' + (TAB==='mua'?' class="on"':'') + '>Mua mã</button>'
    + '<button data-tab="cua-toi"' + (TAB==='cua-toi'?' class="on"':'') + '>Mã của tôi</button>'
    + '<button data-tab="dung"' + (TAB==='dung'?' class="on"':'') + '>Dùng mã</button>'
    + '</div>';
}

function ve(){
  var h = '<div class="wrap">' + dau() + tab();
  if (TAB === 'mua')      h += veMua();
  else if (TAB === 'cua-toi') h += veCuaToi();
  else                    h += veDung();
  app.innerHTML = h + '</div>';
  noi();
}

// ------------------------------------------------------------------ mua
function veMua(){
  if (DON) return veTraTien();
  if (!D) return '<div class="card"><p class="mut">Đang tải bảng giá…</p></div>';

  var max = 0;
  D.goi.forEach(function(g){ if (g.giam_pt > max) max = g.giam_pt; });
  var h = '';
  if (max > 0) {
    h += '<div class="deal"><b>Giảm tới ' + max + '%</b>'
      + '<div>Mua hôm nay, dùng bất cứ lúc nào, ở bất kỳ ghế nào</div></div>';
  }

  h += '<div class="goi">';
  D.goi.forEach(function(g, i){
    var co = CHON === i;
    h += '<div class="g' + (co?' chon':'') + '" data-goi="' + i + '">'
      + (g.vip ? '<span class="vip">VVIP</span>' : '')
      + '<div class="ten">' + esc(g.ten || tien(g.menh_gia)) + '</div>'
      + (g.mo_ta ? '<div class="mo">' + esc(g.mo_ta) + '</div>' : '')
      + '<div class="gia"><span class="moi">' + tien(g.gia_ban) + '</span>'
      + (g.giam_pt > 0 ? '<span class="cu">' + tien(g.menh_gia) + '</span>'
          + '<span class="pt">-' + g.giam_pt + '%</span>' : '')
      + '</div></div>';
  });
  h += '</div>';

  h += '<div class="card"><h2>Thông tin nhận mã</h2>'
    + '<label>Số điện thoại</label>'
    + '<input id="sdt" type="tel" inputmode="numeric" placeholder="0909 123 456" autocomplete="tel">'
    /* PIN để lần sau tra lại mã. Nói rõ CÔNG DỤNG ngay cạnh ô, không nhét vào chữ nhỏ: khách
       không hiểu để làm gì thì gõ bừa, rồi hôm sau không tra được mã đã mua. */
    + '<label>Đặt PIN 4 số — để lần sau tra lại mã của mình</label>'
    + '<input id="pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    + '<label>Số lượng</label>'
    + '<input id="sl" type="number" min="1" max="10" value="' + SL + '">'
    + '<div id="tong" class="mut" style="margin:12px 0 4px"></div>'
    + '<button id="mua" class="chinh">Mua ngay</button>'
    + '<div class="err" id="e"></div></div>';
  return h;
}

function veTraTien(){
  var h = '<div class="card"><h2>Chuyển khoản để nhận mã</h2>'
    + '<p class="mut" style="margin:0 0 12px">Mở app ngân hàng và chuyển đúng số tiền, '
    + '<b style="color:#f0b429">đúng nội dung</b> bên dưới. Mã hiện ra ngay tại đây khi tiền về.</p>'
    + o_ck('Ngân hàng / Số tài khoản', DON.so_tk, DON.so_tk, '')
    + (DON.ten_tk ? '<div class="mut" style="margin:-4px 0 8px 2px">' + esc(DON.ten_tk) + '</div>' : '')
    + o_ck('Số tiền', tien(DON.phai_tra), String(DON.phai_tra), '')
    /* Ô nội dung tô vàng: đây là thứ sai một ký tự là tiền lạc, còn hai ô trên sai thì ngân hàng
       tự báo lỗi. Hai loại rủi ro khác hẳn nhau nên trông cũng phải khác nhau. */
    + o_ck('Nội dung chuyển khoản', DON.noi_dung, DON.noi_dung, ' nhan')
    + '<div class="cho" id="cho">⏳ Đang chờ tiền về…</div>'
    + '<button id="huy" style="width:100%">Đổi gói khác</button>'
    + '<div class="err" id="e"></div></div>';
  return h;
}

function o_ck(nhan, hien, chep_, lop){
  return '<div class="ck' + (lop||'') + '"><div style="flex:1;min-width:0">'
    + '<div class="nh">' + esc(nhan) + '</div>'
    + '<div class="gt' + (String(hien).length > 18 ? ' nho' : '') + '">' + esc(hien) + '</div></div>'
    + '<button data-chep="' + esc(chep_) + '">Chép</button></div>';
}

// ------------------------------------------------------------------ mã của tôi
function veCuaToi(){
  return '<div class="card"><h2>Mã của tôi</h2>'
    + '<p class="mut" style="margin:0 0 10px">Nhập số điện thoại và PIN đã đặt lúc mua.</p>'
    + '<label>Số điện thoại</label>'
    + '<input id="t-sdt" type="tel" inputmode="numeric" placeholder="0909 123 456">'
    + '<label>PIN 4 số</label>'
    + '<input id="t-pin" type="tel" inputmode="numeric" maxlength="4" placeholder="1234">'
    + '<button id="t-xem" class="chinh" style="margin-top:14px">Xem mã của tôi</button>'
    + '<div class="err" id="e"></div><div id="kq"></div></div>';
}

// ------------------------------------------------------------------ dùng mã
function veDung(){
  var h = '<div class="card"><h2>Dùng mã cho ghế</h2>';
  if (GHE) {
    h += '<p class="mut" style="margin:0 0 10px">Mã sẽ chạy cho ghế <b style="color:#f0b429">'
      + esc(GHE) + '</b> — chính là ghế anh/chị đang ngồi.</p>';
  } else {
    /* Tem không mang mã ghế thì PHẢI hỏi. Đoán bừa một ghế là cho ghế người khác chạy bằng mã
       của khách này — mất mã, mất cả buổi. */
    h += '<p class="mut" style="margin:0 0 10px">Chọn đúng ghế đang ngồi. '
      + 'Mã ghế in trên góc màn hình ghế.</p><label>Ghế</label><select id="d-ghe">'
      + '<option value="">— chọn ghế —</option>'
      + ((D && D.ds_ghe) || []).map(function(g){
          return '<option value="' + esc(g.ma) + '">' + esc(g.ma)
            + (g.coso ? ' · ' + esc(g.coso) : '') + '</option>'; }).join('')
      + '</select>';
  }
  h += '<label>Mã giảm giá</label>'
    + '<input id="d-ma" placeholder="ABCD-EFGH" autocapitalize="characters" autocomplete="off">'
    + '<button id="d-ok" class="chinh" style="margin-top:14px">Dùng mã, chạy ghế</button>'
    + '<div class="err" id="e"></div><div id="kq"></div></div>';
  return h;
}

// ------------------------------------------------------------------ nối nút
function tongTien(){
  var o = document.getElementById('tong');
  if (!o || CHON === null || !D) { if (o) o.textContent = ''; return; }
  var g = D.goi[CHON];
  var n = Math.max(1, Math.min(10, Number((document.getElementById('sl')||{}).value) || 1));
  o.innerHTML = 'Phải trả: <b style="color:#f0b429;font-size:18px">' + tien(g.gia_ban * n) + '</b>'
    + (g.giam_pt > 0 ? ' — tiết kiệm ' + tien((g.menh_gia - g.gia_ban) * n) : '');
}

function noi(){
  [].forEach.call(document.querySelectorAll('[data-tab]'), function(b){
    b.onclick = function(){ TAB = b.getAttribute('data-tab'); ve(); };
  });
  [].forEach.call(document.querySelectorAll('[data-goi]'), function(b){
    b.onclick = function(){ CHON = Number(b.getAttribute('data-goi')); ve(); };
  });
  [].forEach.call(document.querySelectorAll('[data-chep]'), function(b){
    b.onclick = function(){ chep(b.getAttribute('data-chep'), b); };
  });
  var sl = document.getElementById('sl');
  if (sl) sl.oninput = function(){ SL = Number(sl.value) || 1; tongTien(); };
  tongTien();

  var mua = document.getElementById('mua');
  if (mua) mua.onclick = function(){
    var e = document.getElementById('e');
    if (CHON === null) { e.textContent = 'Chọn một gói trước nhé.'; return; }
    var sdt = (document.getElementById('sdt').value || '').trim();
    var pin = (document.getElementById('pin').value || '').trim();
    if (!/^\d{4}$/.test(pin)) { e.textContent = 'PIN phải gồm đúng 4 chữ số.'; return; }
    if (ban) return;
    ban = true; mua.disabled = true; e.textContent = 'Đang tạo đơn…';
    goi('dat', { sdt: sdt, pin: pin, menh_gia: D.goi[CHON].menh_gia,
                 so_luong: Math.max(1, Math.min(10, Number(document.getElementById('sl').value)||1)) },
      function(r){
        ban = false; mua.disabled = false;
        if (!r.ok) { e.textContent = r.error || 'Không tạo được đơn.'; return; }
        DON = r; ve(); soiDon();
      });
  };

  var huy = document.getElementById('huy');
  if (huy) huy.onclick = function(){ DON = null; if (hen) { clearTimeout(hen); hen = null; } ve(); };

  var xem = document.getElementById('t-xem');
  if (xem) xem.onclick = function(){
    var e = document.getElementById('e'), kq = document.getElementById('kq');
    kq.innerHTML = ''; e.textContent = 'Đang tra…';
    goi('tra', { sdt: document.getElementById('t-sdt').value,
                 pin: document.getElementById('t-pin').value }, function(r){
      if (!r.ok) { e.textContent = r.error || 'Không tìm thấy.'; return; }
      e.textContent = '';
      var h = '';
      if (!r.chua_dung.length) h += '<p class="mut">Không còn mã nào chưa dùng.</p>';
      r.chua_dung.forEach(function(m){
        h += '<div class="ma"><div><div class="m">' + esc(m.ma) + '</div>'
          + '<div class="g">' + tien(m.menh_gia) + ' · còn dùng được</div></div>'
          + '<button data-chep="' + esc(m.ma) + '">Chép</button></div>';
      });
      r.da_dung.forEach(function(m){
        h += '<div class="ma het"><div><div class="m">' + esc(m.ma) + '</div>'
          + '<div class="g">đã dùng ' + esc(m.dung_luc)
          + (m.dung_may ? ' · ghế ' + esc(m.dung_may) : '') + '</div></div></div>';
      });
      kq.innerHTML = h;
      noi();
    });
  };

  var dok = document.getElementById('d-ok');
  if (dok) dok.onclick = function(){
    var e = document.getElementById('e'), kq = document.getElementById('kq');
    var g = GHE || ((document.getElementById('d-ghe')||{}).value || '');
    if (!g) { e.textContent = 'Chọn ghế đang ngồi trước nhé.'; return; }
    if (ban) return;
    ban = true; dok.disabled = true; e.textContent = 'Đang kiểm mã…';
    goi('dung', { ma: document.getElementById('d-ma').value, ma_may: g }, function(r){
      ban = false; dok.disabled = false;
      if (!r.ok) { e.textContent = r.error || 'Mã không dùng được.'; return; }
      e.textContent = '';
      kq.innerHTML = '<div class="ok"><b>Xong!</b><br>' + esc(r.thong_bao) + '</div>';
    });
  };
}

/* Hỏi lại xem tiền về chưa. 3 giây một lượt: khách đang đứng nhìn màn hình chờ, mà mỗi lượt là
   một request PHP — dày hơn nữa cũng không nhanh hơn được vì phần chậm nằm ở ngân hàng. */
function soiDon(){
  if (hen) { clearTimeout(hen); hen = null; }
  if (!DON) return;
  hen = setTimeout(function(){
    goi('soi', { ma_don: DON.ma_don }, function(r){
      if (!DON) return;
      if (r.ok && r.xong) { xongDon(r.ma); return; }
      soiDon();
    });
  }, 3000);
}

function xongDon(ds){
  if (hen) { clearTimeout(hen); hen = null; }
  var h = '<div class="wrap">' + dau()
    + '<div class="card"><h2>Đã nhận tiền — mã của anh/chị đây</h2>'
    + '<div class="ok">Mã <b>không hết hạn</b>, dùng được ở <b>bất kỳ ghế nào</b>. '
    + 'Quên mã thì vào mục <b>Mã của tôi</b>, nhập số điện thoại và PIN vừa đặt.</div>';
  (ds || []).forEach(function(m){
    h += '<div class="ma"><div><div class="m">' + esc(m) + '</div>'
      + '<div class="g">chụp lại màn hình này giúp em</div></div>'
      + '<button data-chep="' + esc(m) + '">Chép</button></div>';
  });
  h += '<button id="ve-dau" style="width:100%;margin-top:14px">Mua thêm</button></div></div>';
  app.innerHTML = h;
  DON = null;
  [].forEach.call(document.querySelectorAll('[data-chep]'), function(b){
    b.onclick = function(){ chep(b.getAttribute('data-chep'), b); };
  });
  document.getElementById('ve-dau').onclick = function(){ TAB = 'mua'; CHON = null; ve(); };
}

goi('goi', {}, function(r){
  if (r.ok) { D = r; if (r.ghe) GHE = r.ghe; }
  ve();
});
})();
JS;
	}

	// ===================================================================== trang

	public static function ve() {
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		$nen = esc_url_raw( (string) get_option( 'vhg_anh_nen', '' ) );
		$lop = ''; $bien = '';
		if ( '' !== $nen && ! preg_match( '/["\\\\()]/', $nen ) ) {
			$lop  = ' class="co-anh"';
			$bien = ' style="--nen:url(&quot;' . esc_attr( $nen ) . '&quot;)"';
		}
		echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>Mua mã giảm giá — ' . esc_html( VHG_Trang::TEN_NGAN ) . '</title>'
			. '<meta name="theme-color" content="#12141f">'
			. '<style>' . self::css() . '</style></head><body' . $lop . $bien . '>'
			. '<div id="app"></div>'
			. '<script>window.VHG_SHOP=' . wp_json_encode( self::url() ) . ';'
			. 'window.VHG_GHE=' . wp_json_encode( self::ghe_tu_dia_chi() ) . ';'
			. 'window.VHG_TEN=' . wp_json_encode( VHG_Trang::TEN_NGAN ) . ';</script>'
			. '<script>' . self::js() . '</script>'
			. '</body></html>';
	}
}
