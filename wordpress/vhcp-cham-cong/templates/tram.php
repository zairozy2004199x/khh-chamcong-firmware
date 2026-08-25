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
	<p class="mo">Đưa mặt vào khung, đủ sáng. Ảnh sẽ được đóng dấu giờ máy chủ.</p>
	<div class="the" id="oMau"></div>
	<div class="the" style="padding:10px">
		<video id="vid" playsinline autoplay muted></video>
		<canvas id="xem" class="xem an"></canvas>
		<div id="loiChup"></div>
		<p></p>
		<div class="hang" id="nhomChup">
			<button id="btChup" class="chinh">Chụp</button>
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
function goi(viec, than){
	return fetch(CFG.cong + (CFG.cong.indexOf('?')>=0?'&':'?') + 'viec=' + encodeURIComponent(viec), {
		method:'POST', credentials:'same-origin',
		headers:{'Content-Type':'application/json'},
		body: JSON.stringify(than||{})
	}).then(function(r){ return r.json(); }).then(function(j){
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
	}).catch(function(){ return null; });
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

/* ---------------------------------------------------------------- GPS (không chặn) */
var GPS = null;
function xinGps(){
	if(!navigator.geolocation) return;
	navigator.geolocation.getCurrentPosition(function(p){
		GPS = { lat:p.coords.latitude, lng:p.coords.longitude, acc:p.coords.accuracy };
	}, function(){ GPS = null; }, { enableHighAccuracy:true, timeout:8000, maximumAge:60000 });
}

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
		el('maToi').textContent  = j.maNV || '';
		el('csToi').textContent  = (j.dsCoSo && j.dsCoSo.length) ? j.dsCoSo.join(' · ') : (j.coSoMacDinh||'—');
		el('btCham').disabled = false;
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
	}).catch(function(){ /* het_phien đã tự đá về màn đăng nhập — không báo thêm gì */ });
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
	hien('xem',false); hien('vid',true);
	el('nhomChup').classList.remove('an'); el('nhomXem').classList.add('an');
	bao('loiChup','',null);
	if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
		bao('loiChup','dong','Trình duyệt này không mở được máy ảnh. Dùng Chrome hoặc Safari.');
		return;
	}
	navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:1280} }, audio:false })
	.then(function(s){ LUONG=s; el('vid').srcObject=s; })
	.catch(function(e){
		bao('loiChup','dong','Không mở được máy ảnh: ' + (e && e.name ? e.name : 'lỗi')
			+ '. Vào Cài đặt trình duyệt cho phép Máy ảnh với trang này.');
	});
}

function dongCamera(){
	if(LUONG){ LUONG.getTracks().forEach(function(t){ t.stop(); }); LUONG=null; }
	el('vid').srcObject = null;
}

el('btHuyChup').addEventListener('click', function(){ dongCamera(); hien('mChup',false); });
el('btChupLai').addEventListener('click', function(){ ANH=null; moCamera(); });

el('btChup').addEventListener('click', function(){
	var v = el('vid');
	if(!v.videoWidth){ bao('loiChup','dong','Máy ảnh chưa sẵn sàng — chờ một giây rồi bấm lại.'); return; }
	var d = gioMayChu();
	if(!d){
		/* 🔴 Không có giờ máy chủ thì KHÔNG đóng dấu bừa bằng giờ máy. Thà chối và bảo thử lại. */
		bao('loiChup','dong','Chưa lấy được giờ máy chủ. Kiểm tra mạng rồi bấm Chụp lại.');
		napGio();
		return;
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
	hien('vid',false); hien('xem',true);
	el('nhomChup').classList.add('an'); el('nhomXem').classList.remove('an');
	dongCamera();
});

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

	goi('cham',{ token:token(), anh:ANH, gps:GPS, coSo:cs, nhiemVu:nv }).then(function(j){
		if(!j || !j.ok){ bao('loiChon','dong',(j&&j.error)||'Không lưu được.'); return; }
		ANH = null;
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

/* ---------------------------------------------------------------- khởi động */
if(token()){ moManChinh(); }
else { hien('mVao',true); el('oPin').focus(); }
})();
</script>
</body>
</html>
