<?php
/**
 * GIAO DIỆN TRẠM CHẤM CÔNG. Nhận $VHCC_TRAM_CFG từ VHCC_Tram::render().
 *
 * Trang này CỐ Ý không nạp theme: nhân viên mở bằng 3G ở cơ sở, một theme WordPress kéo theo
 * jQuery + font + slider là mười giây trắng màn trước khi thấy nút bấm.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$cfg = isset( $VHCC_TRAM_CFG ) ? $VHCC_TRAM_CFG : array( 'cong' => '', 'ver' => '' );
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Chấm công — K&amp;H</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{font:15px/1.55 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
	background:#0f172a;color:#e2e8f0;-webkit-text-size-adjust:100%}
.bao{max-width:520px;margin:0 auto;padding:14px 14px calc(28px + env(safe-area-inset-bottom))}
h1{font-size:18px;margin:0 0 2px}
.mo{color:#94a3b8;font-size:12.5px;margin:0 0 14px}
.the{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:16px;margin:0 0 12px}
label{display:block;font-size:12.5px;color:#94a3b8;margin:0 0 5px}
input,select{width:100%;padding:12px 13px;font-size:16px;border-radius:10px;border:1px solid #475569;
	background:#0f172a;color:#e2e8f0;font-family:inherit}
input:focus,select:focus{outline:2px solid #38bdf8;outline-offset:-1px}
button{font-family:inherit;font-size:15px;border:0;border-radius:10px;padding:13px 16px;
	background:#334155;color:#e2e8f0;cursor:pointer}
button:disabled{opacity:.5;cursor:not-allowed}
.chinh{background:#0ea5e9;color:#04283a;font-weight:700;width:100%}
.to{font-size:19px;padding:20px 16px;font-weight:800;letter-spacing:.4px}
.phu{background:transparent;border:1px solid #475569;color:#cbd5e1}
.hang{display:flex;gap:9px}
.hang>*{flex:1}
.dong{background:#7f1d1d;border:1px solid #b91c1c;color:#fecaca;border-radius:10px;
	padding:11px 13px;margin:10px 0;font-size:13.5px}
.xanh{background:#064e3b;border:1px solid #059669;color:#bbf7d0;border-radius:10px;
	padding:11px 13px;margin:10px 0;font-size:13.5px}
.vang{background:#422006;border:1px solid #a16207;color:#fde68a;border-radius:10px;
	padding:11px 13px;margin:10px 0;font-size:13px}
.dhho{font-variant-numeric:tabular-nums;font-size:38px;font-weight:800;letter-spacing:1px;
	text-align:center;margin:2px 0 0;color:#f8fafc}
.dngay{text-align:center;color:#94a3b8;font-size:12.5px;margin:0 0 2px}
.nhan{display:inline-block;font-size:11px;padding:2px 8px;border-radius:99px;
	background:#334155;color:#cbd5e1;margin-left:6px;vertical-align:2px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:7px 6px;border-bottom:1px solid #334155;text-align:left}
th{color:#94a3b8;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px}
td.g{font-variant-numeric:tabular-nums}
.trong{color:#64748b}
video,canvas.xem{width:100%;border-radius:12px;background:#000;display:block}
.bando{position:relative;width:100%;height:200px;border:1px solid #334155;border-radius:12px;
	background:#1e293b;overflow:hidden;margin:10px 0 0}
.bando .luoi{position:absolute;left:50%;top:50%;width:768px;height:768px;
	display:grid;grid-template-columns:repeat(3,256px);grid-template-rows:repeat(3,256px)}
.bando .o{display:block;width:256px;height:256px;background:#1e293b}
.bando .cham{position:absolute;left:50%;top:50%;width:16px;height:16px;margin:-8px 0 0 -8px;
	border-radius:50%;background:#ef4444;border:3px solid #fff;box-shadow:0 0 0 2px rgba(0,0,0,.35)}
.bando .ghi{position:absolute;right:4px;bottom:2px;font-size:10px;color:#0f172a;
	background:rgba(255,255,255,.72);padding:0 5px;border-radius:4px}
.khung{position:relative}
.dem{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
	pointer-events:none;border-radius:12px}
.dem span{font-size:96px;font-weight:800;color:#fff;line-height:1;
	text-shadow:0 0 22px rgba(0,0,0,.85),0 3px 10px rgba(0,0,0,.9);
	font-variant-numeric:tabular-nums}
.an{display:none!important}
.mn{position:fixed;inset:0;background:rgba(2,6,23,.94);z-index:9;overflow:auto;
	padding:14px 14px calc(20px + env(safe-area-inset-bottom))}
.mn .bao{padding-top:8px}
.mmau{width:96px;border-radius:8px;border:1px solid #475569;float:right;margin:0 0 8px 10px}
a{color:#7dd3fc}
.ct{text-align:center;color:#64748b;font-size:11.5px;margin:16px 0 0}
</style>
</head>
<body>

<!-- ============ MÀN ĐĂNG NHẬP ============ -->
<div id="mVao" class="bao">
	<h1>Chấm công</h1>
	<p class="mo">Gõ mã PIN của anh/chị để vào.</p>
	<div class="the">
		<label for="oPin">Mã PIN</label>
		<input id="oPin" type="tel" inputmode="numeric" autocomplete="off" maxlength="8"
			placeholder="••••••" enterkeyhint="go">
		<div id="loiVao"></div>
		<p></p>
		<button id="btVao" class="chinh to">VÀO</button>
		<p style="margin:12px 0 0"><button id="btQuen" class="phu" style="width:100%">Quên PIN?</button></p>
	</div>
	<p class="ct">K&amp;H · b<?php echo esc_html( $cfg['ver'] ); ?></p>
</div>

<!-- ============ MÀN QUÊN PIN ============ -->
<div id="mQuen" class="mn an"><div class="bao">
	<h1>Quên PIN</h1>
	<p class="mo">Gõ số căn cước đã khai trong hồ sơ. Không có hồ sơ thì nhờ quản lý cửa hàng.</p>
	<div class="the">
		<label for="oCccd">Số căn cước</label>
		<input id="oCccd" type="tel" inputmode="numeric" maxlength="12" placeholder="0790xxxxxxxx">
		<div id="kqQuen"></div>
		<p></p>
		<div class="hang">
			<button id="btTra" class="chinh">Tra PIN</button>
			<button id="btDongQuen" class="phu">Đóng</button>
		</div>
	</div>
</div></div>

<!-- ============ MÀN CHÍNH ============ -->
<div id="mChinh" class="bao an">
	<h1 id="tenToi">—</h1>
	<p class="mo"><span id="maToi"></span> · <span id="csToi"></span></p>

	<div class="the">
		<p class="dngay" id="ngayMC">—</p>
		<p class="dhho" id="gioMC">--:--:--</p>
		<p class="dngay" style="margin-top:4px">giờ máy chủ</p>
	</div>

	<div class="the">
		<div id="trangThai"></div>
		<div id="baoCham"></div>
		<p></p>
		<button id="btCham" class="chinh to">📷 CHẤM CÔNG</button>
	</div>

	<!-- ============ VỊ TRÍ ĐANG ĐỨNG ============ -->
	<div class="the">
		<label style="margin:0 0 8px">Vị trí đang đứng</label>
		<div id="oViTri"><p class="trong">Đang lấy vị trí…</p></div>
		<p></p>
		<button id="btViTri" class="phu" style="width:100%">Lấy lại vị trí</button>
	</div>

	<!-- ============ CƠ SỞ ĐƯỢC CHẤM ============ -->
	<div class="the">
		<label style="margin:0 0 8px">Cơ sở được chấm công</label>
		<div id="oCoSo"><p class="trong">Đang tải…</p></div>
	</div>

	<div class="the">
		<label style="margin:0 0 8px">Hôm nay</label>
		<div id="bangHN"><p class="trong">Đang tải…</p></div>
	</div>

	<div class="the">
		<label style="margin:0 0 8px">Công của tôi</label>
		<div class="hang" style="align-items:center;margin:0 0 10px">
			<button id="btThangTruoc" class="phu" style="flex:0 0 46px">‹</button>
			<b id="nhanThang" style="flex:1;text-align:center;font-variant-numeric:tabular-nums;font-size:16px">—</b>
			<button id="btThangSau" class="phu" style="flex:0 0 46px">›</button>
		</div>
		<div id="tomTat"></div>
		<div id="bangThang"><p class="trong">—</p></div>
		<p class="ct" style="margin:10px 0 0;text-align:left">Số ở đây là <b>giờ có mặt</b> đọc thẳng từ
			bảng chấm công, chưa trừ nghỉ và chưa quy ra công tính lương. Bảng lương do kế toán chốt
			có thể khác — thấy lệch thì báo, đừng tự cộng.</p>
	</div>

	<p id="oQuanTri" class="an" style="margin:14px 0 0">
		<a id="lkQuanTri" class="phu" href="#"
			style="display:block;text-align:center;text-decoration:none;padding:13px 16px;border-radius:10px">
			Trang quản trị →</a></p>

	<p style="margin:14px 0 0"><button id="btRa" class="phu" style="width:100%">Thoát</button></p>
	<p class="ct">K&amp;H · b<?php echo esc_html( $cfg['ver'] ); ?></p>
</div>

<!-- ============ MÀN CHỤP ẢNH ============ -->
<div id="mChup" class="mn an"><div class="bao">
	<h1>Chụp ảnh</h1>
	<p class="mo">Đưa mặt vào khung, đủ sáng. Máy tự chụp sau <b>5 giây</b> —
		chạm vào khung hình để đếm lại từ đầu. Ảnh được đóng dấu giờ máy chủ.</p>
	<div class="the" id="oMau"></div>
	<div class="the" style="padding:10px">
		<div class="khung">
			<video id="vid" playsinline autoplay muted></video>
			<canvas id="xem" class="xem an"></canvas>
			<div id="oDem" class="dem an"><span id="soDem">5</span></div>
		</div>
		<div id="loiChup"></div>
		<p></p>
		<div class="hang" id="nhomChup">
			<button id="btChup" class="chinh">Chụp ngay</button>
			<button id="btHuyChup" class="phu">Huỷ</button>
		</div>
		<div class="hang an" id="nhomXem">
			<button id="btDung" class="chinh">Dùng ảnh này</button>
			<button id="btChupLai" class="phu">Chụp lại</button>
		</div>
	</div>
</div></div>

<!-- ============ MÀN CHỌN CƠ SỞ / NHIỆM VỤ (ĐÚNG LÚC LƯU) ============ -->
<div id="mChon" class="mn an"><div class="bao">
	<h1>Lưu chấm công</h1>
	<p class="mo">Chọn đúng nơi anh/chị đang có mặt <em>lúc này</em>.</p>
	<div class="the">
		<div id="oChonCS"></div>
		<div id="oChonNV"></div>
		<div id="loiChon"></div>
		<p></p>
		<button id="btLuu" class="chinh to">LƯU CHẤM CÔNG</button>
		<p style="margin:10px 0 0"><button id="btHuyChon" class="phu" style="width:100%">Quay lại</button></p>
	</div>
</div></div>

<script>
(function(){
'use strict';
var CFG = <?php echo wp_json_encode( $cfg ); ?>;
/* Khoá phiên RIÊNG, cố ý KHÔNG dùng chung với trang quản lý (`vhcc_token`). Thẻ của trạm mang
   vai 'CC_ONLINE' — hệ quản trị luôn chối nó, và ngược lại. Để chung một khoá thì nhân viên
   đăng nhập trạm trên máy quầy là xoá luôn phiên của quản lý đang mở tab bên cạnh, mà không ai
   hiểu vì sao mình bị đá ra. */
var KHOA_PHIEN = 'cc_session';
var RONG_ANH   = 720;            /* 🔴 ràng buộc 2: thu nhỏ về 720px TRƯỚC khi gửi */

function el(id){ return document.getElementById(id); }
function hien(x,co){ el(x).classList[co?'remove':'add']('an'); }
function esc(s){ var d=document.createElement('div'); d.textContent=(s===null||s===undefined)?'':String(s); return d.innerHTML; }
function bao(o,kieu,chu){ el(o).innerHTML = chu ? '<div class="'+kieu+'">'+esc(chu)+'</div>' : ''; }

/* ---------------------------------------------------------------- gọi máy chủ */
/**
 * Gọi máy chủ.
 *
 * 🔴 `r.json()` KHÔNG ĐƯỢC GỌI TRẦN. Khi máy chủ trả lỗi 500, hoặc hosting chèn một trang
 *    chặn, thì thân trả về là HTML — `r.json()` ném một lỗi kiểu "Unexpected token <", và lỗi
 *    ấy trôi vào `.catch` của chỗ gọi rồi bị nuốt. Kết quả đúng như màn hình anh Thắng chụp
 *    lúc 16:44: tên "—", giờ "--:--:--", hai khối "Đang tải…" nằm im mãi mãi. Không có gì đỏ,
 *    không có gì để bấm, và không ai đoán được chuyện gì đang xảy ra.
 *
 *    Nên: đọc thân ra CHỮ trước, tự phân tích, và khi không phải JSON thì ném một lỗi NÓI ĐƯỢC
 *    — mã HTTP là bao nhiêu, máy chủ trả về cái gì. Đó là thứ anh Thắng chụp lại được và em
 *    đọc ra ngay.
 */
function goi(viec, than){
	var url = CFG.cong + (CFG.cong.indexOf('?')>=0?'&':'?') + 'viec=' + encodeURIComponent(viec);
	var ma  = 0;
	return fetch(url, {
		method:'POST', credentials:'same-origin',
		headers:{'Content-Type':'application/json'},
		body: JSON.stringify(than||{})
	}).then(function(r){
		ma = r.status;
		return r.text();
	}).then(function(chu){
		var j = null;
		try { j = JSON.parse(chu); }
		catch(e){
			var goi_y = '';
			if(ma === 0)   { goi_y = ' Mất mạng giữa chừng.'; }
			if(ma >= 500)  { goi_y = ' Máy chủ đang lỗi — báo quản trị xem nhật ký lỗi của hosting.'; }
			if(ma === 403) { goi_y = ' Hosting đang chặn đường này (tường lửa).'; }
			if(ma === 404) { goi_y = ' Sai đường dẫn trang — vào Cài đặt bấm Lưu để nạp lại luật đường.'; }
			/* Kèm mấy chữ đầu của thứ nhận được: một trang lỗi PHP hay trang chặn của hosting
			   thường lộ nguyên nhân ngay dòng đầu. */
			var dau = String(chu || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 120);
			throw new Error('Máy chủ trả về nội dung không đọc được (mã ' + ma + ').' + goi_y
				+ (dau ? ' Máy chủ nói: ' + dau : ''));
		}
		if(j && j.ma==='het_phien'){ dangXuat(true); throw new Error(j.error||'Phiên đã hết'); }
		return j;
	});
}

function token(){ try{ return localStorage.getItem(KHOA_PHIEN)||''; }catch(e){ return ''; } }
function datToken(t){ try{ t?localStorage.setItem(KHOA_PHIEN,t):localStorage.removeItem(KHOA_PHIEN); }catch(e){} }

/* ---------------------------------------------------------------- 🔴 ràng buộc 1: GIỜ MÁY CHỦ
   Lấy mốc từ máy chủ MỘT lần rồi để nó tự trôi theo đồng hồ máy. Không bao giờ đọc
   `new Date()` làm giờ hiển thị hay giờ đóng dấu — điện thoại lệch giờ là chuyện thường, và
   một tấm ảnh in sai giờ là bằng chứng nói ngược lại hàng đã ghi. */
var MOC = null;   /* {sec: giây epoch của máy chủ, tuLuc: performance.now() lúc nhận} */

function napGio(){
	return goi('gio',{}).then(function(j){
		if(j && j.ok){ MOC = { sec: Number(j.moc)||0, tuLuc: performance.now() }; }
		return j;
	}).catch(function(e){
		/* Đồng hồ đứng ở "--:--:--" là dấu hiệu đầu tiên người ta nhìn thấy khi máy chủ hỏng —
		   nói ngay tại đó, đừng để họ ngồi đợi một cái đồng hồ không bao giờ chạy. */
		el('ngayMC').textContent = 'không lấy được giờ máy chủ';
		bao('trangThai','dong', (e && e.message) || 'Không gọi được máy chủ.');
		return null;
	});
}

/** Giờ máy chủ NGAY BÂY GIỜ. null = chưa lấy được mốc (và lúc đó KHÔNG được đoán). */
function gioMayChu(){
	if(!MOC) return null;
	return new Date((MOC.sec*1000) + (performance.now() - MOC.tuLuc));
}
function hai(n){ return (n<10?'0':'')+n; }
function chuGio(d){ return hai(d.getUTCHours())+':'+hai(d.getUTCMinutes())+':'+hai(d.getUTCSeconds()); }
function chuNgay(d){ return hai(d.getUTCDate())+'/'+hai(d.getUTCMonth()+1)+'/'+d.getUTCFullYear(); }
/* Mốc từ máy chủ là `current_time('timestamp')` — đã CỘNG lệch múi giờ WordPress. Nên đọc bằng
   getUTC* mới ra đúng giờ Việt Nam; đọc bằng getHours() là cộng lệch máy điện thoại lần thứ hai. */

function nhipDongHo(){
	var d = gioMayChu();
	if(!d){ return; }
	el('gioMC').textContent = chuGio(d);
	el('ngayMC').textContent = chuNgay(d);
}
setInterval(nhipDongHo, 1000);

/* ---------------------------------------------------------------- GPS (không chặn)

   🔴 KHÔNG CHẶN CHẤM CÔNG. Vị trí là thứ ghi kèm để đối chiếu, không phải điều kiện để chấm:
      trong nhà kho, dưới hầm gửi xe, máy cũ tắt định vị — thiếu sóng GPS là chuyện thường, mà
      giờ vào thì không đợi được. Không lấy được thì vẫn chấm, phiếu ghi "KHÔNG có GPS".

   ⚠️ NÓI RA TRẠNG THÁI, ĐỪNG IM. Bản trước nuốt lỗi (`GPS = null` rồi thôi) nên người dùng
      không biết phiếu của mình có toạ độ hay không, và cũng không biết vì sao không có. Ba
      nguyên nhân dẫn tới ba cách sửa KHÁC HẲN nhau, nên phải phân biệt:
        · bị TỪ CHỐI QUYỀN  -> vào Cài đặt trình duyệt bật lại (người dùng tự sửa được)
        · KHÔNG BẮT ĐƯỢC SÓNG -> ra chỗ thoáng, bấm lấy lại
        · máy KHÔNG HỖ TRỢ  -> không sửa được, đừng bắt họ thử mãi                            */
var GPS = null;
var GPS_TRANG = 'chua';   /* chua | dangxin | co | choi | hong | khong_ho_tro */

/**
 * Độ chính xác nói lên điều gì.
 *
 * 🔴 ±200000m LÀ VỊ TRÍ THEO ĐỊA CHỈ MẠNG, KHÔNG PHẢI GPS. Trình duyệt vẫn trả về một cặp toạ
 *    độ trông rất thật (10.775500,106.702100 — trung tâm TP.HCM), kèm sai số 200 KILÔMÉT. Ai
 *    đọc phiếu mà chỉ nhìn cặp số ấy sẽ tưởng đã xác nhận được người này đứng ở đâu, trong khi
 *    nó chỉ nói "đâu đó ở miền Nam". Đây là kiểu sai nguy hiểm nhất: có số, trông đúng, và sai.
 *
 * Nên: chia mức, nói thẳng mức nào dùng được vào việc gì.
 */
function mucGps(acc){
	if(acc <= 50)   return 'tot';     /* GPS đã khoá — đủ để nói đứng ở toà nhà nào */
	if(acc <= 200)  return 'tam';     /* GPS yếu hoặc Wi-Fi tốt — đủ để nói đúng khu phố */
	if(acc <= 2000) return 'tho';     /* Wi-Fi / trạm phát sóng — chỉ đúng phường, quận */
	return 'mang';                     /* theo địa chỉ mạng — KHÔNG dùng để xác nhận có mặt */
}

/** 1234 -> "1,2km"; 85 -> "85m". Đọc "±200000m" thì không ai thấy nó to cỡ nào. */
function dai(m){
	m = Math.round(Number(m) || 0);
	if(m < 1000) return m + 'm';
	return (m / 1000).toFixed(m < 10000 ? 1 : 0).replace('.', ',') + 'km';
}

function veViTri(){
	var e = el('oViTri');
	if(!e) return;

	if(GPS_TRANG === 'co' && GPS){
		var q  = GPS.lat.toFixed(6) + ',' + GPS.lng.toFixed(6);
		var m  = mucGps(GPS.acc);
		var lk = '<p style="margin:8px 0 0"><a target="_blank" rel="noopener"'
		       + ' href="https://maps.google.com/?q=' + encodeURIComponent(q) + '">Mở Google Maps ↗</a></p>';

		if(m === 'mang'){
			/* KHÔNG vẽ bản đồ ở mức này. Vẽ một chấm đỏ giữa Quận 1 khi sai số là 200km chính
			   là nói dối bằng hình ảnh — người xem tin vào cái chấm chứ không đọc dòng ±. */
			e.innerHTML = '<div class="vang" style="margin:0">📍 <b>Chưa bắt được GPS thật.</b> '
				+ 'Toạ độ đang lấy theo <b>địa chỉ mạng</b>, sai số ±' + dai(GPS.acc) + ' — '
				+ 'chỉ nói được "đâu đó trong vùng này", không xác nhận được anh/chị đứng ở đâu.'
				+ '<br>Bật <b>Dịch vụ định vị</b> trong Cài đặt máy, ra chỗ thoáng, rồi bấm '
				+ '"Lấy lại vị trí". Vẫn chấm công được — phiếu sẽ ghi rõ là vị trí ước lượng.</div>'
				+ '<p class="ct" style="margin:8px 0 0;text-align:left">' + esc(q) + ' (±'
				+ dai(GPS.acc) + ')</p>' + lk;
			return;
		}

		var them = '';
		if(m === 'tam'){ them = '<br><span style="opacity:.85">GPS chưa khoá hẳn — đúng khu phố, '
			+ 'chưa chắc đúng toà nhà. Đợi vài giây hoặc ra chỗ thoáng thì số này nhỏ lại.</span>'; }
		if(m === 'tho'){ them = '<br><span style="opacity:.85">Đang lấy theo Wi-Fi / trạm phát sóng, '
			+ 'chưa phải GPS. Ra chỗ thoáng rồi bấm "Lấy lại vị trí".</span>'; }

		/* Tên lớp viết nội tuyến từ hằng, không đi qua biến: bộ kiểm giao diện canh "mọi thứ
		   ghép vào innerHTML phải là hằng hoặc đã esc()", và nó canh đúng — hôm nay là tên lớp
		   do mình đặt, mai có người sửa thành giá trị lấy từ máy chủ thì chốt ấy phải còn. */
		e.innerHTML = '<div class="' + ( 'tot' === m ? 'xanh' : 'vang' ) + '" style="margin:0">📍 <b>'
			+ esc(q) + '</b>'
			+ ' <span style="opacity:.75">(±' + dai(GPS.acc) + ')</span>' + them + '</div>'
			+ veBanDo(GPS.lat, GPS.lng, GPS.acc)
			/* Vẫn giữ link ra Google Maps: bản đồ ở đây đủ để thấy "mình đang ở đâu", còn khi
			   cần chỉ đường hay xem ảnh phố thì mở ứng dụng bản đồ thật vẫn hơn. */
			+ lk;
		nghenBanDo();   /* phải gọi SAU khi đã chèn HTML — trước đó chưa có thẻ nào để nghe */
		return;
	}

	if(GPS_TRANG === 'dangxin'){
		/* Đang chờ GPS khoá: nếu đã có một vị trí thô rồi thì nói ra, đừng để màn hình câm —
		   người ta cần biết máy vẫn đang cố, chứ không phải đã treo. */
		e.innerHTML = '<p class="trong">Đang lấy vị trí…'
			+ (GPS ? ' (hiện ±' + dai(GPS.acc) + ', đang chờ chính xác hơn)' : '') + '</p>';
		return;
	}
	if(GPS_TRANG === 'khong_ho_tro'){
		e.innerHTML = '<div class="vang" style="margin:0">📍 Máy này không hỗ trợ định vị. '
			+ 'Vẫn chấm công được — phiếu sẽ ghi <b>KHÔNG có GPS</b>.</div>';
		return;
	}
	if(GPS_TRANG === 'choi'){
		e.innerHTML = '<div class="vang" style="margin:0">📍 <b>Trình duyệt đang chặn định vị.</b> '
			+ 'Bấm biểu tượng ổ khoá 🔒 cạnh địa chỉ web → cho phép <b>Vị trí</b> → bấm "Lấy lại vị trí". '
			+ 'Vẫn chấm công được — phiếu sẽ ghi <b>KHÔNG có GPS</b>.</div>';
		return;
	}
	if(GPS_TRANG === 'hong'){
		e.innerHTML = '<div class="vang" style="margin:0">📍 Chưa bắt được vị trí (trong nhà hay '
			+ 'dưới hầm hay bị vậy). Ra chỗ thoáng rồi bấm "Lấy lại vị trí". '
			+ 'Vẫn chấm công được — phiếu sẽ ghi <b>KHÔNG có GPS</b>.</div>';
		return;
	}
	e.innerHTML = '<p class="trong">Chưa lấy vị trí.</p>';
}

/**
 * Bản đồ quanh chỗ đang đứng — GHÉP TỪ Ô ẢNH, KHÔNG DÙNG IFRAME.
 *
 * =========================================================================================
 * 🔴 VÌ SAO BỎ IFRAME: "www.openstreetmap.org đã từ chối kết nối"
 * =========================================================================================
 * Bản trước nhúng `openstreetmap.org/export/embed.html` bằng <iframe>. Trên máy anh Thắng nó
 * ra đúng một khung xám với dòng "đã từ chối kết nối" — máy chủ OSM trả tiêu đề chặn nhúng
 * (X-Frame-Options / frame-ancestors), và trình duyệt bỏ luôn khung, không có cách nào bắt
 * lỗi bằng JavaScript để hiện thứ khác thay thế. Nhúng khung của người khác là đặt một mảnh
 * giao diện của mình dưới quyền quyết định của họ.
 *
 * Ô ảnh thì khác: nó chỉ là <img>. Không ai chặn được bằng tiêu đề khung, và nếu tải hỏng thì
 * `onerror` bắt được — bản đồ tự ẩn đi, còn lại dòng toạ độ và link, chứ không để một khung
 * xám báo lỗi giữa trang chấm công.
 *
 * Ghép 3×3 ô 256px quanh điểm cần xem, dịch bằng lề âm cho điểm ấy nằm đúng giữa khung. Toán
 * là phép chiếu Web Mercator chuẩn — cùng công thức mọi thư viện bản đồ dùng, chỉ là mình tự
 * viết mười dòng thay vì kéo về một thư viện 150KB cho một cái bản đồ tĩnh.
 *
 * ⚠️ TẢI Ô ẢNH LÀ GỬI TOẠ ĐỘ RA NGOÀI. Đường dẫn ô ảnh chứa vị trí, nên máy chủ OSM biết
 *    vùng đang xem. Chấp nhận được (phi lợi nhuận, không quảng cáo) nhưng là một lựa chọn.
 *    `referrerpolicy="origin"` gửi mỗi tên miền, không gửi đường dẫn trang.
 *
 * ⚠️ Chỉ vẽ khi độ chính xác đủ tốt — xem `veViTri()`. Chấm đỏ giữa Quận 1 với sai số 200km
 *    là nói dối bằng hình ảnh.
 */
function veBanDo(lat, lng, acc){
	/* Sai số càng lớn thì kéo càng xa: phóng to hết cỡ trong khi máy chỉ biết mình ở đâu đó
	   trong bán kính 500m là vẽ một chấm rất chính xác vào một chỗ rất có thể sai. */
	var z = ( acc <= 60 ) ? 17 : ( acc <= 200 ? 16 : 15 );
	var n  = Math.pow(2, z);
	var xf = (lng + 180) / 360 * n;
	var la = lat * Math.PI / 180;
	var yf = (1 - Math.log(Math.tan(la) + 1 / Math.cos(la)) / Math.PI) / 2 * n;
	var x  = Math.floor(xf), y = Math.floor(yf);
	var px = 256 + Math.round((xf - x) * 256);   /* vị trí điểm trong lưới 3×3 */
	var py = 256 + Math.round((yf - y) * 256);

	var h = '<div class="bando"><div class="luoi" style="margin-left:' + (-px) + 'px;margin-top:'
	      + (-py) + 'px">';
	for(var dy = -1; dy <= 1; dy++){
		for(var dx = -1; dx <= 1; dx++){
			var tx = ((x + dx) % n + n) % n;      /* vòng quanh quả đất theo chiều ngang */
			var ty = y + dy;
			if(ty < 0 || ty >= n){ h += '<i class="o"></i>'; continue; }   /* quá cực, ô trống */
			h += '<img class="o" alt="" loading="lazy" src="' + esc(urlO(z, tx, ty)) + '">';
		}
	}
	h += '</div><b class="cham"></b>'
	   + '<span class="ghi">© OpenStreetMap</span></div>';
	return h;
}

/* Ô ảnh lấy từ MÁY CHỦ MÌNH, không lấy thẳng từ openstreetmap.org.
   Lấy thẳng đã thử và hỏng: ô trắng, một ô hiện dấu hỏi ảnh vỡ. Chính sách dùng ô ảnh của
   OpenStreetMap không cho một trang bất kỳ móc thẳng vào máy chủ ô ảnh của họ — họ chặn, và
   họ đúng. Nay máy chủ mình tải hộ một lần rồi nhớ lại; xem VHCC_BanDo. */
function urlO(z, x, y){
	return CFG.cong + (CFG.cong.indexOf('?') >= 0 ? '&' : '?')
	     + 'viec=o&z=' + z + '&x=' + x + '&y=' + y;
}

/**
 * Ô ảnh tải hỏng -> ẩn cả bản đồ.
 *
 * 🔴 GẮN BẰNG addEventListener, KHÔNG DÙNG onerror="..." TRONG HTML.
 *    Lỗi thật vừa gặp: cả tệp JavaScript này nằm trong một hàm bọc kín `(function(){…})()`,
 *    nên `banDoHong` KHÔNG có mặt ở phạm vi toàn cục. Mà thuộc tính `onerror` trong HTML thì
 *    chạy ở đúng phạm vi toàn cục ấy — nó gọi một cái tên không tồn tại, ném lỗi, và cái việc
 *    cần làm (ẩn bản đồ) không bao giờ chạy. Kết quả trên máy anh Thắng: khung bản đồ nằm đó
 *    với chín ô trắng và một dấu hỏi, trông như trang hỏng.
 *
 *    Đây là loại lỗi im lặng đúng nghĩa: không có gì đỏ, chỉ có một thứ đáng lẽ phải biến mất
 *    thì lại nằm nguyên.
 *
 * ⚠️ Ẩn khi có BẤT KỲ ô nào hỏng, không đợi hỏng hết. Một bản đồ thủng lỗ chỗ còn khó hiểu
 *    hơn là không có bản đồ.
 */
function nghenBanDo(){
	var ds = document.querySelectorAll('.bando img.o');
	for(var i = 0; i < ds.length; i++){
		ds[i].addEventListener('error', function(){
			var b = this.parentNode && this.parentNode.parentNode;
			if(b && b.classList && b.classList.contains('bando')){ b.style.display = 'none'; }
		});
	}
}

function xinGps(){
	if(!navigator.geolocation){ GPS = null; GPS_TRANG = 'khong_ho_tro'; veViTri(); return; }
	thoiTheoGps();
	GPS = null;                      /* đo lại từ đầu, không giữ số cũ của lần đứng chỗ khác */
	GPS_TRANG = 'dangxin';
	veViTri();

	GPS_THEO = navigator.geolocation.watchPosition(function(p){
		var moi = { lat:p.coords.latitude, lng:p.coords.longitude, acc:p.coords.accuracy };
		/* Chỉ nhận khi TỐT HƠN cái đang có. Máy có lúc bắn ra một lần đo tệ hơn ở giữa chừng;
		   nhận bừa là số sai lệch nhảy qua nhảy lại trên màn hình. */
		if(!GPS || moi.acc < GPS.acc){ GPS = moi; }
		if(GPS.acc <= GPS_DU){ thoiTheoGps(); GPS_TRANG = 'co'; }
		veViTri();
	}, function(err){
		/* err.code 1 = PERMISSION_DENIED. Hai mã còn lại (2 hết chỗ dò, 3 quá hạn) đều là
		   "không bắt được sóng" với người dùng, nên gộp — họ làm cùng một việc: ra chỗ thoáng. */
		thoiTheoGps();
		if(GPS){ GPS_TRANG = 'co'; }      /* đã đo được lần nào đó rồi thì giữ, đừng vứt */
		else   { GPS = null; GPS_TRANG = ( err && 1 === err.code ) ? 'choi' : 'hong'; }
		veViTri();
	}, { enableHighAccuracy:true, timeout:GPS_CHO, maximumAge:0 });

	GPS_HEN = setTimeout(function(){
		thoiTheoGps();
		/* Hết giờ chờ: có gì dùng nấy, nhưng `veViTri` sẽ dán nhãn đúng mức. Không có gì thì
		   coi như không bắt được sóng — vẫn chấm công được. */
		GPS_TRANG = GPS ? 'co' : 'hong';
		veViTri();
	}, GPS_CHO);
}

el('btViTri').addEventListener('click', xinGps);

/* ---------------------------------------------------------------- đăng nhập */
var TOI = null;   /* thông tin từ viec=toi */

el('btVao').addEventListener('click', vaoHe);
el('oPin').addEventListener('keydown', function(e){ if(e.key==='Enter'){ vaoHe(); } });

function vaoHe(){
	var pin = el('oPin').value.trim();
	bao('loiVao','',null);
	var b = el('btVao');
	b.disabled = true; b.textContent = 'Đang vào…';
	goi('vao',{pin:pin}).then(function(j){
		if(!j || !j.ok){ bao('loiVao','dong', (j&&j.error)||'Không vào được.'); return; }
		datToken(j.token);
		el('oPin').value='';
		moManChinh();
	}).catch(function(e){ bao('loiVao','dong', e.message||'Lỗi mạng.'); })
	.then(function(){ b.disabled=false; b.textContent='VÀO'; });
}

el('btQuen').addEventListener('click', function(){ hien('mQuen',true); });
el('btDongQuen').addEventListener('click', function(){ hien('mQuen',false); });
el('btTra').addEventListener('click', function(){
	var b=el('btTra'); b.disabled=true; b.textContent='Đang tra…';
	bao('kqQuen','',null);
	goi('quenpin',{cccd:el('oCccd').value}).then(function(j){
		if(!j || !j.ok){ bao('kqQuen','dong',(j&&j.error)||'Không tra được.'); return; }
		el('kqQuen').innerHTML = '<div class="xanh"><b>'+esc(j.ten)+'</b><br>PIN: <b style="font-size:19px">'
			+ esc(j.pin) + '</b><br>Cơ sở: ' + esc(j.coSo||'—') + '</div>';
	}).catch(function(e){ bao('kqQuen','dong', e.message||'Lỗi mạng.'); })
	.then(function(){ b.disabled=false; b.textContent='Tra PIN'; });
});

function dangXuat(imLang){
	datToken('');
	TOI = null;
	hien('mChinh',false); hien('mChup',false); hien('mChon',false);
	hien('mVao',true);
	if(!imLang){ bao('loiVao','',null); }
	else { bao('loiVao','vang','Phiên đã hết. Đăng nhập lại bằng PIN.'); }
}
el('btRa').addEventListener('click', function(){
	goi('ra',{token:token()}).catch(function(){});
	dangXuat(false);
});

/* ---------------------------------------------------------------- màn chính */
function moManChinh(){
	hien('mVao',false); hien('mQuen',false); hien('mChinh',true);
	xinGps();
	napGio().then(nhipDongHo);
	napToi();
}

function napToi(){
	return goi('toi',{token:token()}).then(function(j){
		if(!j || !j.ok){ bao('trangThai','dong',(j&&j.error)||'Không đọc được hồ sơ.'); return; }
		if(!j.bat){
			TOI = null;
			el('tenToi').textContent = 'Chưa bật chấm công';
			bao('trangThai','vang', j.ghiChu || 'Tài khoản này chưa bật chấm công online.');
			el('btCham').disabled = true;
			return;
		}
		TOI = j;
		if(j.gio){ MOC = { sec: Number(j.gio.moc)||0, tuLuc: performance.now() }; nhipDongHo(); }
		el('tenToi').textContent = j.hoTen || '—';
		el('maToi').textContent  = 'Mã ' + (j.maNV || '—');
		el('csToi').textContent  = j.coSoMacDinh || '—';
		el('btCham').disabled = false;
		veCoSo(j);
		/* Đường sang trang quản trị chỉ hiện khi MÁY CHỦ gửi nó về — tức người này thật sự mở
		   được. Trang không tự đoán theo vai trò: đoán ở đây là bộ luật quyền thứ hai, và bộ
		   thứ hai bao giờ cũng lệch trước. */
		if(j.qtUrl){
			el('lkQuanTri').href = j.qtUrl;
			el('lkQuanTri').textContent = 'Trang quản trị (' + (j.vaiTen || '') + ') →';
			el('oQuanTri').classList.remove('an');
		} else {
			el('oQuanTri').classList.add('an');
		}
		veHomNay(j);
		if(!THANG){ var tn = thangNay(); if(tn) veThang(tn); }
	}).catch(function(e){
		/* het_phien tự đá về màn đăng nhập rồi, không báo thêm. Còn lại thì PHẢI nói ra: màn
		   hình đứng im với mấy chữ "Đang tải…" là thứ tệ nhất — người ta không biết nên chờ,
		   nên bấm lại, hay nên gọi ai. */
		if(/Phiên đã hết/.test(e && e.message)) return;
		bao('trangThai','dong', (e && e.message) || 'Không đọc được hồ sơ.');
		el('oCoSo').innerHTML = '<p class="trong">Không tải được.</p>';
		el('bangHN').innerHTML = '<p class="trong">Không tải được.</p>';
	});
}

/* Cơ sở được chấm — khối riêng, không nhét vào dòng chú thích nhỏ ở đầu trang.
   🔴 Người ở nhiều cơ sở phải NHÌN THẤY mình có những cơ sở nào TRƯỚC khi bấm chấm. Ô chọn cơ
      sở chỉ hiện ra lúc lưu (đúng ràng buộc: hỏi đúng lúc lưu, không hỏi từ sáng), nên nếu ở
      đây cũng không hiện thì tới màn chọn họ mới biết mình thiếu một cơ sở — mà lúc ấy tay đã
      cầm ảnh vừa chụp, giờ vào thì đang trôi. */
function veCoSo(j){
	var ds = (j.dsCoSo && j.dsCoSo.length) ? j.dsCoSo : (j.coSoMacDinh ? [j.coSoMacDinh] : []);
	if(!ds.length){
		el('oCoSo').innerHTML = '<div class="vang" style="margin:0">Hồ sơ chưa khai cơ sở nào. '
			+ 'Nhờ quản lý khai ô <b>Cửa hàng</b> trong hồ sơ — chưa có cơ sở thì lượt chấm '
			+ 'không biết ghi vào đâu.</div>';
		return;
	}
	var h = '<table><tbody>';
	for(var i=0;i<ds.length;i++){
		var chinh = (ds[i] === j.coSoMacDinh);
		h += '<tr><td>' + esc(ds[i])
		   + (chinh ? '<span class="nhan">cơ sở chính</span>' : '<span class="nhan">cơ sở phụ</span>')
		   + '</td></tr>';
	}
	h += '</tbody></table>';
	if(ds.length > 1){
		h += '<p class="ct" style="margin:8px 0 0;text-align:left">Lúc lưu, trang sẽ hỏi anh/chị '
		   + '<b>đang có mặt ở cơ sở nào</b> — chọn đúng cơ sở đang đứng, đừng chọn theo thói quen.</p>';
	}
	el('oCoSo').innerHTML = h;
}

function veHomNay(j){
	var cs = j.dsCoSo||[], co=false, h='<table><thead><tr><th>Cơ sở</th><th>Hàng</th><th>Vào</th><th>Ra</th></tr></thead><tbody>';
	for(var i=0;i<cs.length;i++){
		var ds = (j.homNay && j.homNay[cs[i]]) || [];
		for(var k=0;k<ds.length;k++){
			co=true;
			h += '<tr><td>'+esc(cs[i])+'</td><td>'+esc(ds[k].hauTo||'chính')+'</td>'
			   + '<td class="g">'+esc(ds[k].vao||'—')+'</td><td class="g">'+esc(ds[k].ra||'—')+'</td></tr>';
		}
	}
	h += '</tbody></table>';
	el('bangHN').innerHTML = co ? h : '<p class="trong">Hôm nay chưa chấm lượt nào.</p>';
}

/* ------------------------------------------------------- công của tôi (theo tháng)

   🔴 THÁNG TÍNH TỪ GIỜ MÁY CHỦ, KHÔNG TỪ ĐIỆN THOẠI. Ngày 1 và ngày cuối tháng, một cái điện
      thoại lệch múi giờ mở ra là thấy tháng khác — rồi báo "mất công" trong khi công vẫn còn
      nguyên ở tháng bên cạnh. `TOI.gio.ngay` là chuỗi ngày do máy chủ gửi kèm mọi lượt nạp.

   Cộng trừ tháng bằng CHUỖI chứ không bằng đối tượng Date: Date đọc '2026-08' theo UTC rồi in
   ra theo giờ máy, và ở múi giờ âm thì tháng lùi mất một. */
var THANG = '';

function thangDich(ym, buoc){
	var p = String(ym).split('-'), n = Number(p[0])||2026, t = (Number(p[1])||1) + buoc;
	while(t > 12){ t -= 12; n++; }
	while(t < 1){ t += 12; n--; }
	return n + '-' + hai(t);
}

function thangNay(){
	var ng = (TOI && TOI.gio && TOI.gio.ngay) ? String(TOI.gio.ngay) : '';
	return /^\d{4}-\d{2}/.test(ng) ? ng.slice(0,7) : '';
}

function gioPhut(p){
	if(p === null || p === undefined) return '—';
	var g = Math.floor(p/60), m = p%60;
	return g + 'h' + (m ? hai(m) : '');
}

function veThang(ym){
	THANG = ym;
	el('nhanThang').textContent = 'Tháng ' + ym.slice(5) + '/' + ym.slice(0,4);
	/* Không cho đi tới tương lai — tháng sau chắc chắn trống, và một bảng trống làm người ta
	   tưởng mất dữ liệu. */
	el('btThangSau').disabled = ( thangNay() !== '' && ym >= thangNay() );
	el('bangThang').innerHTML = '<p class="trong">Đang tải…</p>';
	el('tomTat').innerHTML = '';
	goi('thang',{token:token(), thang:ym}).then(function(j){
		if(!j || !j.ok){ el('bangThang').innerHTML = '<p class="trong">Không tải được.</p>'; return; }
		var t = j.tong || {}, d = j.dong || [];
		var s = '<div class="xanh" style="margin:0 0 10px">' + (t.ngay||0) + ' ngày · ' + (t.luot||0)
		      + ' lượt · ' + gioPhut(t.phut||0) + ' có mặt</div>';
		if(t.thieuRa){
			s += '<div class="vang" style="margin:0 0 10px">' + t.thieuRa
			   + ' lượt thiếu giờ ra (ô <b>Ra</b> để trống bên dưới). Báo quản lý bổ sung '
			   + '<b>trước khi chốt lương tháng</b> — chốt rồi thì sửa rất phiền.</div>';
		}
		el('tomTat').innerHTML = s;
		if(!d.length){ el('bangThang').innerHTML = '<p class="trong">Tháng này chưa có lượt nào.</p>'; return; }
		var h = '<table><thead><tr><th>Ngày</th><th>Cơ sở</th><th>Vào</th><th>Ra</th><th>Giờ</th></tr></thead><tbody>';
		for(var i=0;i<d.length;i++){
			var thieu = !d[i].ra;
			h += '<tr' + (thieu ? ' style="background:#3f1d1d"' : '') + '>'
			   + '<td class="g">' + esc(String(d[i].ngay).slice(8)) + '</td>'
			   + '<td>' + esc(d[i].coSo) + (d[i].hauTo ? '<span class="nhan">'+esc(d[i].hauTo)+'</span>' : '') + '</td>'
			   + '<td class="g">' + esc(d[i].vao||'—') + '</td>'
			   + '<td class="g">' + (thieu ? '<b style="color:#fca5a5">thiếu</b>' : esc(d[i].ra)) + '</td>'
			   + '<td class="g">' + gioPhut(d[i].phut) + '</td></tr>';
		}
		el('bangThang').innerHTML = h + '</tbody></table>';
	}).catch(function(){ el('bangThang').innerHTML = '<p class="trong">Lỗi mạng.</p>'; });
}

el('btThangTruoc').addEventListener('click', function(){ if(THANG) veThang(thangDich(THANG,-1)); });
el('btThangSau').addEventListener('click', function(){ if(THANG) veThang(thangDich(THANG,1)); });

/* ---------------------------------------------------------------- chụp ảnh */
var LUONG = null, ANH = null;

el('btCham').addEventListener('click', function(){
	bao('baoCham','',null);
	ANH = null;
	xinGps();
	/* Mốc giờ lấy LẠI ngay trước khi chụp: trang có thể đã mở từ sáng, và mốc cũ trôi theo đồng
	   hồ máy suốt tám tiếng thì đủ lệch để đóng dấu sai phút. */
	napGio().then(nhipDongHo);
	hien('mChup',true);
	veAnhMau();
	moCamera();
});

function veAnhMau(){
	if(el('oMau').getAttribute('data-xong')==='1') return;
	goi('anhmau',{}).then(function(j){
		el('oMau').setAttribute('data-xong','1');
		if(j && j.ok && j.dataUri){
			el('oMau').innerHTML = '<img class="mmau" src="'+esc(j.dataUri)+'" alt="ảnh mẫu">'
				+ '<p style="margin:0;font-size:12.5px;color:#94a3b8">Chụp giống hình mẫu bên cạnh: '
				+ 'thẳng mặt, đủ sáng, không đội mũ.</p>';
		} else {
			el('oMau').innerHTML = '<p style="margin:0;font-size:12.5px;color:#94a3b8">'
				+ 'Chụp thẳng mặt, đủ sáng, không đội mũ.</p>';
		}
	}).catch(function(){ el('oMau').setAttribute('data-xong','1'); });
}

function moCamera(){
	DEM_HUT = 0;
	hien('xem',false); hien('vid',true);
	el('nhomChup').classList.remove('an'); el('nhomXem').classList.add('an');
	bao('loiChup','',null);
	if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
		bao('loiChup','dong','Trình duyệt này không mở được máy ảnh. Dùng Chrome hoặc Safari.');
		return;
	}
	navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:1280} }, audio:false })
	.then(function(s){
		LUONG = s;
		var v = el('vid');
		v.srcObject = s;
		/* Đếm từ lúc CÓ HÌNH, không phải từ lúc bấm nút: máy ảnh trên điện thoại cũ mất một
		   hai giây mới lên hình, đếm sớm là hết 5 giây khi màn hình vẫn còn đen. */
		v.onloadedmetadata = function(){ if(!ANH) batDem(); };
		if(v.videoWidth && !ANH){ batDem(); }
	})
	.catch(function(e){
		bao('loiChup','dong','Không mở được máy ảnh: ' + (e && e.name ? e.name : 'lỗi')
			+ '. Vào Cài đặt trình duyệt cho phép Máy ảnh với trang này.');
	});
}

function dongCamera(){
	if(LUONG){ LUONG.getTracks().forEach(function(t){ t.stop(); }); LUONG=null; }
	el('vid').srcObject = null;
}

el('btHuyChup').addEventListener('click', function(){ dungDem(); dongCamera(); hien('mChup',false); });
el('btChupLai').addEventListener('click', function(){ dungDem(); ANH=null; moCamera(); });

/* ---------------------------------------------------------------- đếm ngược rồi TỰ CHỤP

   Anh Thắng 25/08/2026: *"Trước khi chụp nó sẽ báo 5-4-3-2-1"*. Lý do thật sự đáng làm: chụp
   bằng một tay trong khi tay kia giơ điện thoại thì ngón cái che ống kính hoặc làm rung máy —
   ảnh mờ, mà ảnh mờ thì mất luôn công dụng duy nhất của nó là đối chiếu khi tranh cãi.

   ⚠️ ĐẾM NGƯỢC KHÔNG ĐỤNG TỚI GIỜ ĐÓNG DẤU. Giờ in lên ảnh là giờ máy chủ ở ĐÚNG GIÂY BẤM
      máy, `gioMayChu()` tự trôi theo đồng hồ máy từ mốc đã lấy — nên năm giây đếm ngược không
      làm ảnh ghi sai giờ. Nếu đóng dấu bằng giờ lúc MỞ màn chụp thì mỗi tấm ảnh lệch 5 giây,
      và lệch âm thầm.

   ⚠️ Chưa có mốc giờ máy chủ thì KHÔNG chụp. Đếm lại chứ không chụp bừa — xem `chupNgay()`. */
var DEM = null;          /* id của bộ đếm đang chạy */
var DEM_GIAY = 5;
var DEM_HUT = 0;         /* số lần đếm xong mà chụp không được */
var HUT_TOI_DA = 3;

function dungDem(){
	if(DEM){ clearInterval(DEM); DEM = null; }
	el('oDem').classList.add('an');
}

function batDem(){
	dungDem();
	var con = DEM_GIAY;
	el('soDem').textContent = con;
	el('oDem').classList.remove('an');
	DEM = setInterval(function(){
		con--;
		if(con > 0){ el('soDem').textContent = con; return; }
		dungDem();
		/* Chụp hụt (máy ảnh chưa sẵn sàng, chưa có giờ máy chủ) thì ĐẾM LẠI, đừng đứng im:
		   người ta đang giơ điện thoại chờ, không nhìn vào dòng chữ lỗi nhỏ phía dưới. */
		if(chupNgay()){ DEM_HUT = 0; return; }
		/* Hụt mãi (mất mạng nên không có giờ máy chủ) thì DỪNG, đừng quay vòng vô tận: vòng lặp
		   im lặng làm người ta đứng chờ mà không hiểu, còn nút "Chụp ngay" thì vẫn bấm được. */
		DEM_HUT++;
		if(DEM_HUT >= HUT_TOI_DA){
			DEM_HUT = 0;
			bao('loiChup','dong','Thử tự chụp ' + HUT_TOI_DA + ' lần chưa được — thường là mạng '
				+ 'đang chập chờn nên chưa lấy được giờ máy chủ. Bấm "Chụp ngay" để thử bằng tay.');
			return;
		}
		setTimeout(function(){ if(!ANH) batDem(); }, 1200);
	}, 1000);
}

/* Chạm vào khung hình = "khoan, đếm lại từ đầu". Không thêm nút: màn chụp đã có hai nút, thêm
   nút thứ ba vào chỗ người ta đang giơ điện thoại một tay là mời bấm nhầm. */
el('oDem').parentNode.addEventListener('click', function(){
	if(ANH) return;                       /* đã chụp xong, đang xem lại */
	if(el('vid').classList.contains('an')) return;
	batDem();
});

/**
 * Chụp một tấm. Trả về true nếu chụp được.
 * Dùng chung cho nút "Chụp ngay" và cho bộ đếm — hai đường chụp riêng là hai chỗ đóng dấu giờ,
 * và sớm muộn một chỗ quên mất ràng buộc nào đó.
 */
function chupNgay(){
	if(ANH) return true;                  /* 🔴 ràng buộc 4: đã có ảnh thì không chụp đè */
	var v = el('vid');
	if(!v.videoWidth){ bao('loiChup','dong','Máy ảnh chưa sẵn sàng — chờ một giây rồi bấm lại.'); return false; }
	var d = gioMayChu();
	if(!d){
		/* 🔴 Không có giờ máy chủ thì KHÔNG đóng dấu bừa bằng giờ máy. Thà chối và bảo thử lại. */
		bao('loiChup','dong','Chưa lấy được giờ máy chủ. Kiểm tra mạng rồi bấm Chụp lại.');
		napGio();
		return false;
	}

	/* 🔴 ràng buộc 2: thu nhỏ về 720px NGAY TẠI ĐÂY, trước mọi thứ khác. */
	var ti = v.videoWidth / v.videoHeight;
	var W = Math.min(RONG_ANH, v.videoWidth), H = Math.round(W / ti);
	var c = el('xem');
	c.width = W; c.height = H;
	var g = c.getContext('2d');
	g.drawImage(v, 0, 0, W, H);

	/* 🔴 ràng buộc 1: đóng dấu bằng GIỜ MÁY CHỦ */
	var chu = chuNgay(d) + '  ' + chuGio(d);
	var co  = Math.max(13, Math.round(W/28));
	g.font = '700 ' + co + 'px monospace';
	var rong = g.measureText(chu).width;
	g.fillStyle = 'rgba(0,0,0,.62)';
	g.fillRect(0, H - co - 16, rong + 20, co + 16);
	g.fillStyle = '#fff';
	g.fillText(chu, 10, H - 10);

	ANH = c.toDataURL('image/jpeg', 0.8);
	dungDem();
	hien('vid',false); hien('xem',true);
	el('nhomChup').classList.add('an'); el('nhomXem').classList.remove('an');
	dongCamera();

	/* Ảnh tối thui thì CẢNH BÁO, không chặn. Máy tự bấm nên người chụp không kịp nhìn khung
	   hình — phải nói ra để họ bấm "Chụp lại" thay vì gửi đi một tấm không nhận ra ai. Cố ý
	   không tự chối: thà có ảnh tối còn hơn không có lượt chấm công nào. */
	if(doSang(g, W, H) < 55){
		/* `bao()` thoát HTML (đúng — chữ ở đây có thể tới từ máy chủ), nên viết chữ thuần,
		   đừng nhét thẻ vào rồi ngồi thắc mắc sao màn hình hiện ra "&lt;b&gt;". */
		bao('loiChup','vang','Ảnh hơi tối, khó nhận ra mặt. Ra chỗ sáng hơn rồi bấm "Chụp lại" '
			+ '— hoặc cứ dùng ảnh này nếu anh/chị thấy rõ mặt mình.');
	}
	return true;
}

/** Độ sáng trung bình 0–255. Lấy mẫu thưa: quét đủ 720×540 điểm trên máy cũ là khựng một nhịp. */
function doSang(g, W, H){
	try{
		var d = g.getImageData(0, 0, W, H).data, tong = 0, n = 0;
		for(var i = 0; i < d.length; i += 4 * 40){
			tong += (d[i] * 0.299 + d[i+1] * 0.587 + d[i+2] * 0.114);
			n++;
		}
		return n ? (tong / n) : 255;
	}catch(e){ return 255; }   /* đọc không được thì coi như đủ sáng, đừng doạ nhầm */
}

el('btChup').addEventListener('click', function(){ dungDem(); chupNgay(); });

/* ---------------------------------------------------------------- 🔴 ràng buộc 3:
   hỏi cơ sở / nhiệm vụ ĐÚNG LÚC LƯU, không hỏi lúc mở trang. */
el('btDung').addEventListener('click', function(){
	hien('mChup',false);
	veManChon();
	hien('mChon',true);
});
el('btHuyChon').addEventListener('click', function(){ hien('mChon',false); hien('mChup',true); moCamera(); });

function veManChon(){
	bao('loiChon','',null);
	var cs = (TOI && TOI.dsCoSo) || [];
	if(cs.length > 1){
		var h = '<label for="oCS">Cơ sở đang có mặt</label><select id="oCS">';
		for(var i=0;i<cs.length;i++){
			h += '<option value="'+esc(cs[i])+'"'
			   + (cs[i]===TOI.coSoMacDinh?' selected':'') + '>'+esc(cs[i])+'</option>';
		}
		el('oChonCS').innerHTML = h + '</select><p></p>';
	} else {
		el('oChonCS').innerHTML = '<label>Cơ sở</label><div class="vang" style="margin:0">'
			+ esc(cs[0] || (TOI && TOI.coSoMacDinh) || '—') + '</div><p></p>';
	}

	var nv = (TOI && TOI.dsNhiemVu) || [];
	if(nv.length){
		var h2 = '<label for="oNV">Nhiệm vụ</label><select id="oNV"><option value="">— việc chính —</option>';
		for(var k=0;k<nv.length;k++){ h2 += '<option value="'+esc(nv[k])+'">'+esc(nv[k])+'</option>'; }
		el('oChonNV').innerHTML = h2 + '</select>';
	} else {
		el('oChonNV').innerHTML = '';
	}
}

/* 🔴 ràng buộc 4: khoá nút ngay khi bấm, mở lại chỉ khi đã có câu trả lời. */
var DANG_LUU = false;
el('btLuu').addEventListener('click', function(){
	if(DANG_LUU) return;
	if(!ANH){ bao('loiChon','dong','Chưa có ảnh. Quay lại chụp.'); return; }
	DANG_LUU = true;
	var b = el('btLuu');
	b.disabled = true; b.textContent = 'ĐANG LƯU…';
	bao('loiChon','',null);

	var oCS = el('oCS'), oNV = el('oNV');
	var cs = oCS ? oCS.value : (((TOI&&TOI.dsCoSo)||[])[0] || (TOI&&TOI.coSoMacDinh) || '');
	var nv = oNV ? oNV.value : '';

	var anhVuaGui = ANH;   /* giữ lại để đối chiếu mặt SAU KHI giờ đã ghi xong */
	goi('cham',{ token:token(), anh:ANH, gps:GPS, coSo:cs, nhiemVu:nv }).then(function(j){
		if(!j || !j.ok){ bao('loiChon','dong',(j&&j.error)||'Không lưu được.'); return; }
		ANH = null;
		soiMat(anhVuaGui, j.ngay, j.coSo);
		hien('mChon',false);
		var nhan = (j.loai==='ra') ? 'GIỜ RA' : 'GIỜ VÀO';
		bao('baoCham','xanh', '✔ Đã ghi ' + nhan + ' ' + j.gio + ' — ' + j.coSo
			+ ' (' + j.ngay + ')' + (j.ma!==(TOI&&TOI.maNV) ? ' · hàng ' + j.ma : ''));
		napToi();
	}).catch(function(e){
		bao('loiChon','dong', e.message || 'Lỗi mạng — chưa lưu được. Bấm lại.');
	}).then(function(){
		DANG_LUU = false;
		b.disabled = false; b.textContent = 'LƯU CHẤM CÔNG';
	});
});

/* ================================================================ ĐỐI CHIẾU KHUÔN MẶT

   🔴 CHẠY SAU KHI GIỜ ĐÃ GHI XONG, VÀ KHÔNG AI PHẢI CHỜ NÓ.
      Tính dãy đặc trưng cần tải một model vài megabyte. Nếu việc ấy nằm trên đường đi của
      lượt chấm công thì mỗi lần chấm phải đợi model tải xong mới ghi được giờ — đổi một tiện
      ích lấy chính cái việc mà cả hệ thống sinh ra để làm. Nên: `cham` trả về ok, màn hình
      báo "đã ghi giờ vào", XONG; rồi cái này mới lặng lẽ chạy.

   ⚠️ HỎNG Ở BẤT KỲ ĐÂU CŨNG IM. Thiếu file, model tải dở, ảnh không thấy mặt, mạng chết —
      tất cả đều `return` không nói gì. Người dùng KHÔNG được thấy lỗi của một thứ họ không
      yêu cầu và không sửa được. Cái duy nhất họ cần thấy là dòng "đã ghi giờ vào" ở trên.

   ⚠️ Máy chủ đã tự gác: thiếu thư viện thì `CFG.mat.co` là false ngay từ lúc dựng trang. */
var MAT_TAI = null;      /* Promise nạp thư viện — chỉ nạp MỘT lần cho cả phiên */

function napThuVienMat(){
	if(MAT_TAI) return MAT_TAI;
	MAT_TAI = new Promise(function(xong, hong){
		var s = document.createElement('script');
		s.src = CFG.mat.js;
		s.onload = function(){ xong(); };
		s.onerror = function(){ hong(new Error('không tải được thư viện')); };
		document.head.appendChild(s);
	}).then(function(){
		if(!window.faceapi) throw new Error('thư viện nạp rồi mà không thấy faceapi');
		var m = CFG.mat.mau;
		/* Ba model, tải song song. Bản "tiny" cho bộ dò và bộ điểm mốc — nhẹ hơn nhiều bản
		   đầy đủ và đủ dùng cho ảnh selfie chính diện. Bộ nhận dạng thì không có bản tiny. */
		return Promise.all([
			faceapi.nets.tinyFaceDetector.loadFromUri(m),
			faceapi.nets.faceLandmark68TinyNet.loadFromUri(m),
			faceapi.nets.faceRecognitionNet.loadFromUri(m)
		]);
	});
	MAT_TAI.catch(function(){ /* nuốt, để lần sau còn thử lại được */ MAT_TAI = null; });
	return MAT_TAI;
}

function soiMat(anh, ngay, coSo){
	if(!CFG.mat || !CFG.mat.co || !anh) return;
	napThuVienMat().then(function(){
		return new Promise(function(xong, hong){
			var img = new Image();
			img.onload  = function(){ xong(img); };
			img.onerror = function(){ hong(new Error('ảnh hỏng')); };
			img.src = anh;
		});
	}).then(function(img){
		return faceapi
			.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 320 }))
			.withFaceLandmarks(true)
			.withFaceDescriptor();
	}).then(function(kq){
		/* Không thấy mặt trong ảnh: KHÔNG gửi gì cả. Gửi một dãy rỗng lên là máy chủ hoặc lấy
		   nó làm mẫu, hoặc gắn cờ — cả hai đều sai, vì thứ thiếu là tấm ảnh chứ không phải
		   con người. Ảnh không thấy mặt thì quản lý mở ra xem là biết ngay. */
		if(!kq || !kq.descriptor) return;
		var v = [];
		for(var i = 0; i < kq.descriptor.length; i++){ v.push(kq.descriptor[i]); }
		return goi('mat', { token:token(), vector:v, ngay:ngay, coSo:coSo });
	}).catch(function(){ /* im — xem chú thích ở đầu khối */ });
}

/* ---------------------------------------------------------------- khởi động */
if(token()){ moManChinh(); }
else { hien('mVao',true); el('oPin').focus(); }
})();
</script>
</body>
</html>
