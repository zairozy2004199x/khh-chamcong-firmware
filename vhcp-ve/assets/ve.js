/* ═══════════════════════════════════════════════════════════════════════════════════════
 * TRANG BÁN VÉ POSH — toàn bộ việc chạy máy của trang khách.
 *
 * ⚠️ VÌ SAO LÀ TỆP RIÊNG, KHÔNG NHÚNG THẲNG VÀO TRANG NỮA
 * 11/09/2026: "bấm không mua được" suốt mấy lượt. Màn Kiểm tra hệ thống xanh hết (bảng đủ cột,
 * tài khoản nhận tiền đủ, thử ghi vé thành công) -> máy chủ sạch. Nút 🩺 do máy chủ dựng thì
 * HIỆN -> PHP bản mới đã sống trên site. Nhưng bấm nút không ra bảng, mà bảng ấy do chính khối
 * script trong trang gắn -> khối script ĐÓ KHÔNG HỀ CHẠY.
 *
 * Một khối <script> gần 450 dòng nằm trong đầu ra của shortcode là miếng mồi của đủ thứ: plugin
 * gộp/nén JS, tường lửa lọc thẻ script, trình dựng trang chạy wp_kses qua nội dung. Tệp ngoài
 * nạp bằng wp_enqueue_script() thì mấy lớp đó đều tôn trọng — lại còn xem được bằng View Source
 * và báo 404 rõ ràng nếu thiếu, thay vì hỏng im lặng.
 *
 * Dữ liệu máy chủ đi qua wp_localize_script() -> window.PVE_DATA. Thiếu nó vẫn chạy được (đường
 * dự phòng ngay bên dưới): một lỗi cấu hình không được phép giết đường bán hàng.
 * ═══════════════════════════════════════════════════════════════════════════════════════ */
(function(){
	/* PVE_DATA bị nuốt thì vẫn đoán được địa chỉ REST từ chính trang đang mở. Mất danh sách cơ sở
	   thì chỉ mất phần giảm giá tại quầy, KHÔNG mất nút mua vé. */
	var D = window.PVE_DATA || {};
	if (!D.rest) { D.rest = location.origin + '/wp-json/posh/v1'; }
	if (!D.ban)  { D.ban  = '?'; }
	if (!D.cs || !D.cs.length) { D.cs = []; }
/* 🔴 LỖI JS PHẢI HIỆN RA. Ba lượt "bấm không ra gì" vừa rồi mất cả buổi vì lỗi nằm im
   trong console — mà không ai mở console khi đang đứng bán hàng. Nay lỗi nào chặn
   trang thì in thành một băng đỏ ngay trên đầu, chép được, gửi được. */
(function(){
	function bang(txt){
		try {
			var d = document.getElementById('pve-loi-js');
			if (!d) {
				d = document.createElement('div'); d.id = 'pve-loi-js';
				d.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:99999;background:#7f1d1d;'
					+ 'color:#fff;font:12px/1.5 ui-monospace,Menlo,monospace;padding:8px 12px;'
					+ 'white-space:pre-wrap;word-break:break-word;max-height:40vh;overflow:auto';
				(document.body || document.documentElement).appendChild(d);
			}
			d.textContent = (d.textContent ? d.textContent + '\n' : '⚠️ Lỗi trang bán vé (chụp màn hình gửi kỹ thuật):\n') + txt;
		} catch (e) {}
	}
	window.addEventListener('error', function(ev){
		bang((ev.message || 'lỗi') + '  @ ' + (ev.filename || '') + ':' + (ev.lineno || '?'));
	});
	window.addEventListener('unhandledrejection', function(ev){
		var r = ev && ev.reason; bang('Promise: ' + ((r && r.message) || r));
	});
	/* Lỗi bị boc() nuốt cũng phải hiện — nó không chặn trang nhưng vẫn là hỏng. */
	window.addEventListener('load', function(){
		if (window.__pveLoi && window.__pveLoi.length) { bang(window.__pveLoi.join('\n')); }
	});
})();
/* Số bản do CHÍNH khối script này khai. So với số bản PHP in ở chân trang: hai số
   lệch nhau (hoặc chỗ này trống) nghĩa là trình duyệt đang chạy HTML cũ trong cache —
   đúng cái bẫy đã ghi trong tài liệu bàn giao của site này (SpeedyCache). Không có nó
   thì "bấm không ra gì" và "trang cũ" nhìn giống hệt nhau. */
var PVE_BAN_JS = D.ban;
try {
	document.addEventListener('DOMContentLoaded', function(){
		var o = document.querySelector('.pve-ban-js');
		if (o) { o.textContent = 'JS ' + PVE_BAN_JS + ' \u2713'; }
	});
} catch (e) {}
/* ═══ SOI TẠI CHỖ ═════════════════════════════════════════════════════════════════
 * Nút 🩺 do MÁY CHỦ dựng (#pve-soi-nut) — xem chú thích ở chỗ dựng nút. Khối này chỉ
 * gắn việc cho nó. Nhờ tách vậy mà hai câu hỏi tách bạch được: thấy nút = PHP mới đã
 * sống; bấm nút không ra bảng = JS không chạy.
 */
/* Dấu hiệu KHÔNG cần bấm gì: hai chỗ này do máy chủ ghi "chưa chạy"; tệp này chạy được thì
   nó đổi thành số bản. Nhìn phát biết ngay JS sống hay chết, khỏi mở console. */
try {
	var tt = document.getElementById('pve-soi-tt');
	if (tt) { tt.textContent = '\u00b7 JS ' + PVE_BAN_JS + ' \u2713'; }
} catch (e) {}
try {
	var nutSoi = document.getElementById('pve-soi-nut');
	if (nutSoi) nutSoi.onclick = function(){
		var d = [];
		d.push('JS bản: ' + (typeof PVE_BAN_JS !== 'undefined' ? PVE_BAN_JS : '(không có — TRANG CŨ TRONG CACHE)'));
				d.push('Tệp ve.js: ĐÃ CHẠY');
				d.push('Script trong trang: ' + (window.PVE_INLINE ? 'đã chạy' : 'BỊ CHẶN — plugin tối ưu/bảo mật nuốt thẻ script'));
				d.push('Dữ liệu máy chủ: ' + (window.PVE_DATA ? 'có' : 'KHÔNG — đang dùng đường dự phòng ' + REST));
		var bs = document.querySelectorAll('.pve-buy');
		d.push('Số nút "Đặt vé": ' + bs.length);
		if (bs.length) {
			bs[0].scrollIntoView({ block: 'center' });
			var r = bs[0].getBoundingClientRect();
			var tren = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
			var mo = tren ? (tren.tagName + (tren.className ? '.' + String(tren.className).split(' ').join('.') : '')) : '(không thấy)';
			d.push('Thứ NẰM TRÊN nút: ' + mo);
			d.push('  -> ' + (tren && tren.closest && tren.closest('.pve-buy') ? 'ĐÚNG là nút, bấm phải ăn' : 'CÓ THỨ KHÁC CHE NÚT'));
		}
		['.pve-wel', '.pve-mask'].forEach(function(sel){
			var e = document.querySelector(sel);
			if (!e) { d.push(sel + ': không có trên trang'); return; }
			var cs = getComputedStyle(e);
			d.push(sel + ': display=' + cs.display + ' z=' + cs.zIndex + ' hidden=' + e.hasAttribute('hidden'));
		});
		var body = getComputedStyle(document.body);
		d.push('body: overflow=' + body.overflow + ' pointer-events=' + body.pointerEvents);
		d.push('Lỗi JS đã nuốt: ' + ((window.__pveLoi && window.__pveLoi.join(' | ')) || '(không có)'));
		var h = document.createElement('pre');
		h.style.cssText = 'position:fixed;inset:8px;z-index:2147483647;background:#0b0c10;color:#e8e8ea;'
			+ 'border:2px solid #d4af37;border-radius:12px;padding:14px;overflow:auto;white-space:pre-wrap;'
			+ 'font:12px/1.6 ui-monospace,Menlo,monospace';
		h.textContent = d.join('\n') + '\n\n(bấm vào bảng này để đóng)';
		h.onclick = function(){ h.remove(); };
		document.body.appendChild(h);
	};
} catch (e) {}
var REST = D.rest;
/* Cơ sở kèm toạ độ / % giảm / bán kính — để trang tự sắp cơ sở gần nhất lên trước và
   hiện nhãn giảm giá. ⚠️ Chỉ để HIỆN. Giá thật do máy chủ chốt lại ở POSH_Ve::giam_tai_cho()
   mỗi lượt đặt; sửa mấy con số này trong trình duyệt không mua rẻ được đồng nào. */
var PVE_CS = D.cs;
var mask = document.querySelector('.pve-mask');
try { document.body.appendChild(mask); } catch(e){}  // đưa popup ra body để nền mờ phủ kín (khỏi lỗi theme bọc transform)
var mFor = null, timer = null;
function tien(n){ try{ return (Number(n)||0).toLocaleString('vi-VN')+'đ'; }catch(e){ return n+'đ'; } }
function qs(s){ return mask.querySelector(s); }
function qsa(s){ return Array.prototype.slice.call(mask.querySelectorAll(s)); }
function show(step){ qs('.pve-step-form').hidden = (step!=='form'); qs('.pve-step-qr').hidden = (step!=='qr'); }
function loadQR(cb){
	if (window.QRCode){ cb(); return; }
	var s=document.createElement('script');
	s.src='https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
	s.onload=cb; s.onerror=function(){ cb('x'); }; document.head.appendChild(s);
}
function moModal(card){
	mFor = card;
	qs('.pve-m-ten').textContent = card.dataset.ten;
	qs('.pve-m-gia').textContent = tien(card.dataset.gia);
	qs('.pve-err').hidden = true; qs('.pve-f-ten').value=''; qs('.pve-f-sdt').value='';
	zaloVaoForm();   /* xoá ô xong mới điền, không thì điền rồi lại bị xoá ngay */
	show('form'); mask.hidden = false;
}
function dong(){ mask.hidden = true; if(timer){ clearInterval(timer); timer=null; } }

/* ═══ THÔNG TIN ZALO ═══════════════════════════════════════════════════════════════════════
   Khách đã bấm "Đăng nhập Zalo" thì đừng bắt gõ lại tên với số điện thoại.

   ⚠️ Đăng nhập Zalo TRÊN WEB không cho số điện thoại — OAuth v4 chỉ trả id + tên + ảnh. Số chỉ
   lấy được trong Zalo Mini App. Nên máy chủ lấy SĐT từ vé gần nhất của chính Zalo ID này: lần
   đầu khách vẫn phải gõ, từ lần hai là có sẵn.

   Chỉ điền vào ô ĐANG TRỐNG. Khách mua hộ người khác mà mình nhảy vào ghi đè tên họ vừa gõ thì
   vé xuất sai tên, và họ không hiểu vì sao. */
var ZME = null;
boc('thông tin Zalo', function(){
	fetch(REST + '/zalo/toi', { credentials: 'same-origin' })
		.then(function(r){ return r.json(); })
		.then(function(d){
			if (!d || !d.dangnhap) return;
			ZME = d;
			dienZalo(document.querySelector('.pve-qf-ten'), d.ten);
			dienZalo(document.querySelector('.pve-qf-sdt'), d.sdt);
		})
		.catch(function(){});   /* mất mạng thì thôi, khách gõ tay — không được chặn mua vé */
});
function dienZalo(o, v){ if (o && !o.value && v) { o.value = v; } }
/* Gọi mỗi lần mở popup: popup có thể mở trước lúc /zalo/toi kịp trả lời. */
function zaloVaoForm(){
	var chip = qs('.pve-zme');
	if (!ZME) { if (chip) chip.hidden = true; return; }
	dienZalo(qs('.pve-f-ten'), ZME.ten);
	dienZalo(qs('.pve-f-sdt'), ZME.sdt);
	if (chip){
		qs('.pve-zme-t').textContent = ZME.ten || ('Zalo ' + (ZME.id || ''));
		var a = qs('.pve-zme-a');
		if (a && ZME.anh) { a.src = ZME.anh; a.hidden = false; }
		chip.hidden = false;
	}
}

/* ═══ MỘT ĐƯỜNG LỌC DUY NHẤT ═══════════════════════════════════════════════════
   Trang có ba chỗ cùng nói về "đang xem khu nào": thanh tab, màn chào mừng chọn khu,
   và ô chọn cơ sở ở khung đặt vé nhanh. Ba chỗ mà ba hàm lọc riêng thì sớm muộn bấm
   một chỗ, hai chỗ kia nói khác — nên tất cả gọi chung pveLoc(). */
var PVE_KV = '', PVE_NHOM = '';
/* 🔴 GẮN NÚT ĐẶT VÉ TRƯỚC, TRƯỚC MỌI TÍNH NĂNG KHÁC.
 *
 * Anh Thắng 11/09/2026: "bấm không đặt vé được" — trong khi màn Kiểm tra hệ thống
 * xanh hết, tức máy chủ sạch. Chỗ gắn sự kiện cho nút nằm gần CUỐI khối script, sau
 * một loạt tính năng thêm sau (tem cửa hàng, định vị, thanh tab, dải danh mục…).
 * Một lỗi lúc chạy ở BẤT KỲ đoạn nào phía trên là cả phần còn lại không chạy, nút Đặt
 * vé không bao giờ được gắn — bấm không ra gì, không báo gì.
 *
 * Nay việc bán hàng gắn TRƯỚC, và mỗi tính năng thêm sau đều bọc try/catch riêng
 * (xem boc()). Trang trưng bày có thể hỏng; đường mua vé thì không.
 */
/* Bắt bằng UỶ QUYỀN trên document thay vì gắn vào từng nút: thẻ vé bị vẽ lại, bị lọc
   ẩn/hiện, hay khối script chạy trước lúc thẻ vào DOM — cách này vẫn ăn. Gắn từng nút
   thì chỉ cần một trong mấy tình huống đó là nút chết lặng, đúng thứ vừa mất ba lượt
   để tìm. */
document.addEventListener('click', function(ev){
	var b = ev.target && ev.target.closest ? ev.target.closest('.pve-buy') : null;
	if (!b || b.disabled) return;
	var card = b.closest('.pve-card'); if (!card) return;
	ev.preventDefault();
	moModal(card);
});

/* Bọc một tính năng phụ: hỏng thì ghi ra console + báo cho khối bắt lỗi, KHÔNG kéo
   theo phần còn lại của trang. */
function boc(ten, fn){
	try { fn(); }
	catch (e) {
		try { console.error('[posh-ve] ' + ten + ':', e); } catch (x) {}
		try { window.__pveLoi = (window.__pveLoi || []).concat(ten + ': ' + (e && e.message || e)); } catch (x) {}
	}
}

function pveLoc(kv, nhom){
	if (kv !== undefined && kv !== null) PVE_KV = kv;
	if (nhom !== undefined && nhom !== null) PVE_NHOM = nhom;
	var hien = 0;
	[].slice.call(document.querySelectorAll('.pve-sec')).forEach(function(sec){
		/* Khối Ưu đãi cũng là .pve-sec nhưng KHÔNG phải vé — lọc theo tab danh mục thì
		   nó biến mất, mà nó có liên quan gì tới "Combo" hay "Vé lẻ" đâu. */
		if (sec.getAttribute('data-loc-bo')) return;
		var n = 0;
		[].slice.call(sec.querySelectorAll('.pve-card')).forEach(function(c){
			var k = c.getAttribute('data-kv') || '', g = c.getAttribute('data-nhom') || '';
			/* Vé không khai khu = bán ở MỌI khu -> luôn hiện. Đây là luật cũ của trang,
			   giữ nguyên: đổi nó là hàng loạt vé chung bỗng biến mất khỏi mọi khu. */
			var ok = (!PVE_KV || !k || k === PVE_KV) && (!PVE_NHOM || g === PVE_NHOM);
			c.style.display = ok ? '' : 'none'; if (ok) n++;
		});
		sec.style.display = n ? '' : 'none';
		hien += n;
	});
	var trong = document.querySelector('.pve-tab-trong');
	if (trong) trong.hidden = !!hien;
	/* Ô chọn cơ sở ở khung đặt vé nhanh đi theo tab khu, không thì người ta lọc khu này
	   mà bấm Mua vé ngay lại ra vé của khu khác. */
	var selCs = document.querySelector('.pve-qf-cs');
	if (selCs && selCs.value !== PVE_KV){ selCs.value = PVE_KV; try{ selCs.dispatchEvent(new Event('change')); }catch(e){} }
	/* Lưu cả cờ "đã chọn rồi": bấm "Tất cả" là một lựa chọn HỢP LỆ, không phải chưa
	   chọn. Thiếu cờ này thì màn chào mừng (nếu còn dựng) coi như chưa ai chọn gì và
	   bật lớp phủ lên che cả trang. */
	try { sessionStorage.setItem('posh_kvuc', PVE_KV); sessionStorage.setItem('posh_kvuc_da', '1'); } catch(e){}
}
boc('thanh tab lọc', function(){
	var tabs = [].slice.call(document.querySelectorAll('.pve-tab, .pve-dmi'));
	if (!tabs.length) return;
	/* Đếm ngay trên nhãn: "Combo (1)" cho biết bấm vào có gì, khỏi bấm thử từng tab. */
	tabs.forEach(function(t){
		var loc = t.getAttribute('data-loc'), v = t.getAttribute('data-v');
		if (!v) return;
		var n = document.querySelectorAll('.pve-card[data-' + (loc === 'kv' ? 'kv' : 'nhom') + '="' + v.replace(/"/g,'\\"') + '"]').length;
		if (loc === 'kv') { n += document.querySelectorAll('.pve-card:not([data-kv]), .pve-card[data-kv=""]').length; }
		/* Số chỉ gắn cho nút chữ. Nút danh mục có ảnh thì tên đã sát đáy ô, nhét thêm
		   con số vào là chữ xuống hai dòng, dải cao vống lên mà chẳng rõ hơn. */
		if (n && t.classList.contains('pve-tab')) { t.innerHTML = t.textContent + ' <span class="pve-tab-n">' + n + '</span>'; }
	});
	tabs.forEach(function(t){
		t.addEventListener('click', function(){
			var loc = t.getAttribute('data-loc'), v = t.getAttribute('data-v');
			tabs.forEach(function(x){ if (x.getAttribute('data-loc') === loc) x.classList.toggle('on', x === t); });
			if (loc === 'kv') pveLoc(v, null); else pveLoc(null, v);
		});
	});
	/* Khu đã chọn từ lần trước (hoặc từ tem QR cửa hàng) -> bật sẵn đúng tab. */
	var kv0 = ''; try { kv0 = sessionStorage.getItem('posh_kvuc') || ''; } catch(e){}
	if (kv0){
		var t0 = tabs.filter(function(x){ return x.getAttribute('data-loc')==='kv' && x.getAttribute('data-v')===kv0; })[0];
		if (t0) t0.click();
	}
});

// ----- Màn chào mừng: chọn khu vực rồi lọc vé -----
var wel = document.querySelector('.pve-wel');
if (wel) {
	try { document.body.appendChild(wel); } catch(e){}
	var KVKEY = 'posh_kvuc';
	var okBtn = wel.querySelector('.pve-wel-ok');
	var kvBtns = [].slice.call(wel.querySelectorAll('.pve-wel-kv'));
	var bar = document.querySelector('.pve-kvbar');
	var kvChon = kvBtns.length ? kvBtns[0].getAttribute('data-kv') : '';
	function chon(kv){
		kvChon = kv;
		kvBtns.forEach(function(x){ x.classList.toggle('on', x.getAttribute('data-kv') === kv); });
	}
	function locKV(kv){
		/* Gọi chung pveLoc() thay vì tự lọc lấy — xem khối "MỘT ĐƯỜNG LỌC DUY NHẤT". */
		pveLoc(kv, null);
		var tb = [].slice.call(document.querySelectorAll('.pve-tab[data-loc="kv"]'));
		tb.forEach(function(x){ x.classList.toggle('on', (x.getAttribute('data-v')||'') === kv); });
		if (bar){ var t = bar.querySelector('.pve-kvbar-ten'); if (t) t.textContent = kv; bar.hidden = false; }
	}
	kvBtns.forEach(function(b){ b.onclick = function(){ chon(b.getAttribute('data-kv')); }; });
	okBtn.onclick = function(){ wel.hidden = true; try{ sessionStorage.setItem(KVKEY, kvChon); }catch(e){} locKV(kvChon); };
	if (bar){ var d = bar.querySelector('.pve-kvbar-doi'); if (d) d.onclick = function(e){ e.preventDefault(); wel.hidden = false; }; }
	var daChon = '', daHoi = '';
	try{ daChon = sessionStorage.getItem(KVKEY) || ''; daHoi = sessionStorage.getItem('posh_kvuc_da') || ''; }catch(e){}
	var hopLe = kvBtns.some(function(b){ return b.getAttribute('data-kv') === daChon; });
	if (daHoi && !daChon) { wel.hidden = true; }              // đã chọn "Tất cả" -> đừng hỏi lại
	else if (daChon && hopLe) { chon(daChon); locKV(daChon); wel.hidden = true; }
	else { chon(kvChon); wel.hidden = false; }
}

/* ═══ ĐANG Ở CỬA HÀNG NÀO — nền của luật giá ═══════════════════════════════════
   Khách quét tem QR dán tại quầy -> vào trang với ?cs=<mã cơ sở>. Trang hỏi vị trí,
   trong bán kính thì hiện giá giảm; ngoài bán kính hoặc khách không cho vị trí thì
   giá gốc và NÓI RÕ VÌ SAO. Im lặng tính giá gốc là kiểu khiến khách đứng ngay quầy
   cãi nhau với nhân viên về một con số không ai giải thích được.
   Hỏi vị trí CHỈ khi có ?cs= — tự dưng hỏi GPS lúc khách mới vào xem giá là cách nhanh
   nhất để họ bấm Chặn, và trình duyệt nhớ lựa chọn ấy cho cả những lần sau. */
var PVE = { cs:null, pos:null, giam:0, kc:-1, vi:'' };
boc('tem cửa hàng & định vị', function(){
	var ma = '';
	try { ma = (new URLSearchParams(location.search)).get('cs') || ''; } catch(e){}
	ma = String(ma).toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,12);
	if (!ma) return;
	for (var i=0;i<PVE_CS.length;i++){ if (PVE_CS[i].ma === ma) { PVE.cs = PVE_CS[i]; break; } }
	if (!PVE.cs) { bangCS('Không nhận ra mã cửa hàng trên tem — mua vé vẫn bình thường, giá gốc.', 'cho'); return; }
	if (!PVE.cs.giam) { bangCS('Bạn đang xem vé của <b>'+esc(PVE.cs.ten)+'</b>.', 'ok'); dongBo(); return; }
	bangCS('Đang kiểm vị trí để áp giảm <b>'+PVE.cs.giam+'%</b> tại <b>'+esc(PVE.cs.ten)+'</b>…', 'cho');
	if (!navigator.geolocation) { PVE.vi='may_khong_ho_tro'; xongVT(); return; }
	navigator.geolocation.getCurrentPosition(function(p){
		PVE.pos = { lat:p.coords.latitude, lng:p.coords.longitude };
		PVE.kc = kcMet(PVE.pos.lat, PVE.pos.lng, PVE.cs.lat, PVE.cs.lng);
		if (PVE.kc <= PVE.cs.bk) { PVE.giam = PVE.cs.giam; } else { PVE.vi='o_xa'; }
		xongVT();
	}, function(){ PVE.vi='tu_choi'; xongVT(); }, { enableHighAccuracy:true, timeout:8000, maximumAge:60000 });
});
function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
/* Haversine — cùng công thức với POSH_Ve::kc_met() bên máy chủ. Hai bên lệch nhau thì
   trang hứa giảm mà máy chủ không cho, khách đã bấm mua rồi mới thấy giá khác. */
function kcMet(la1,ln1,la2,ln2){
	var R=6371000, p=Math.PI/180;
	var a=0.5-Math.cos((la2-la1)*p)/2+Math.cos(la1*p)*Math.cos(la2*p)*(1-Math.cos((ln2-ln1)*p))/2;
	return Math.round(2*R*Math.asin(Math.sqrt(Math.max(0,a))));
}
function giaSauGiam(gia){
	gia = Number(gia)||0;
	if (!PVE.giam) return gia;
	return Math.max(0, Math.floor(gia * (100 - PVE.giam) / 100 / 1000) * 1000);
}
function bangCS(html, loai){
	var b = document.querySelector('.pve-csbar');
	if (!b){ b = document.createElement('div'); b.className='pve-csbar';
		var qf = document.querySelector('.pve-qf'); if (!qf) return; qf.parentNode.insertBefore(b, qf); }
	b.className = 'pve-csbar pve-csbar-'+(loai||'cho');
	b.innerHTML = html;
}
function xongVT(){
	var c = PVE.cs;
	if (PVE.giam > 0){
		bangCS('✅ Đang ở <b>'+esc(c.ten)+'</b> — giá đã giảm <b>'+PVE.giam+'%</b>'
			+ (PVE.kc>=0 ? ' <span class="pve-csbar-kc">(cách '+PVE.kc+'m)</span>' : ''), 'ok');
	} else {
		var vi = PVE.vi==='tu_choi' ? 'bạn chưa cho phép xem vị trí'
			: (PVE.vi==='o_xa' ? ('bạn đang cách cửa hàng '+PVE.kc+'m, ngoài bán kính '+c.bk+'m')
			: 'máy không xác định được vị trí');
		bangCS('ℹ️ Vé <b>'+esc(c.ten)+'</b> đang tính <b>giá gốc</b> — '+vi
			+ '. Tới quầy và bật vị trí thì được giảm <b>'+c.giam+'%</b>.', 'cho');
	}
	dongBo();
}
/* Chọn sẵn cơ sở của tem, và sắp cơ sở gần nhất lên đầu ô chọn. */
function dongBo(){
	var sel = document.querySelector('.pve-qf-cs');
	if (sel && PVE.cs){
		for (var i=0;i<sel.options.length;i++){
			if (sel.options[i].value === PVE.cs.ten){ sel.selectedIndex = i; break; }
		}
		try { sel.dispatchEvent(new Event('change')); } catch(e){}
	}
	if (sel && PVE.pos){
		var giu = sel.value, ds = [].slice.call(sel.options).slice(1);
		ds.forEach(function(o){
			var cs=null; for (var i=0;i<PVE_CS.length;i++){ if (PVE_CS[i].ten===o.value) cs=PVE_CS[i]; }
			o.__kc = (cs && cs.lat && cs.lng) ? kcMet(PVE.pos.lat, PVE.pos.lng, cs.lat, cs.lng) : 1e12;
		});
		ds.sort(function(a,b){ return a.__kc - b.__kc; });
		ds.forEach(function(o,i){
			if (o.__kc < 1e11 && !/·/.test(o.textContent)){
				o.textContent = o.textContent + ' · ' + (o.__kc<1000 ? (o.__kc+'m') : ((o.__kc/1000).toFixed(1)+'km'))
					+ (i===0 ? ' (gần bạn nhất)' : '');
			}
			sel.appendChild(o);
		});
		sel.value = giu;
	}
	/* Biết vị trí rồi mới biết có giảm hay không -> phải vẽ lại ô chọn vé, không thì
	   nó còn treo giá gốc trong khi băng trên đã nói "đã giảm 10%". */
	try { if (window.pveFillVe) window.pveFillVe(); } catch(e){}
}

// ----- Đặt vé nhanh (form trên hero) -----
(function(){
	var qf = document.querySelector('.pve-qf'); if(!qf) return;
	var selVe = qf.querySelector('.pve-qf-ve');
	var selCs = qf.querySelector('.pve-qf-cs');
	var cards = [].slice.call(document.querySelectorAll('.pve-card')).map(function(c){
		return { id:c.getAttribute('data-id'), ten:c.getAttribute('data-ten'), gia:c.getAttribute('data-gia'), kv:c.getAttribute('data-kv')||'', het:c.classList.contains('pve-het') };
	});
	function fillVe(){
		var cs = selCs ? selCs.value : '';
		selVe.innerHTML = '<option value="">-- Chọn loại vé --</option>';
		cards.forEach(function(c){
			if (c.het) return;
			if (cs && c.kv && c.kv !== cs) return;
			var o = document.createElement('option'); o.value = c.id;
			/* Giá hiện đúng thứ khách sẽ trả: đang trong bán kính thì hiện giá đã giảm.
			   Cùng luật làm tròn với POSH_Ve::gia_sau_giam() — lệch một nghìn là khách
			   thấy một giá ở ô chọn, một giá khác trên mã QR chuyển khoản. */
			o.textContent = c.ten + ' — ' + tien(giaSauGiam(c.gia)) + (PVE.giam ? (' (-' + PVE.giam + '%)') : '');
			selVe.appendChild(o);
		});
	}
	if (selCs){ var kv0=''; try{ kv0 = sessionStorage.getItem('posh_kvuc')||''; }catch(e){} if(kv0){ selCs.value = kv0; } selCs.addEventListener('change', fillVe); }
	window.pveFillVe = fillVe;   // để khối định vị gọi vẽ lại khi đã biết có giảm hay không
	fillVe();
	qf.querySelector('.pve-qf-go').addEventListener('click', function(){
		var ten = qf.querySelector('.pve-qf-ten').value.trim();
		var sdt = qf.querySelector('.pve-qf-sdt').value.trim();
		var id = Number(selVe.value || 0);
		var sl = Math.max(1, Number(qf.querySelector('.pve-qf-sl').value || 1));
		var err = qf.querySelector('.pve-qf-err');
		if(!id){ err.textContent='Vui lòng chọn loại vé.'; err.hidden=false; return; }
		if(!ten || !sdt){ err.textContent='Nhập tên và số điện thoại.'; err.hidden=false; return; }
		err.hidden = true; var btn = this; btn.disabled = true; btn.textContent = 'Đang tạo…';
		fetch(REST+'/ve/dat-gio', { method:'POST', headers:{'Content-Type':'application/json'},
			/* Gửi kèm mã cửa hàng + toạ độ để máy chủ TỰ kiểm lại rồi mới chốt giá. */
			body: JSON.stringify({ items:[{ id:id, sl:sl }], ten:ten, sdt:sdt,
				cs: (PVE.cs ? PVE.cs.ma : ''), lat: (PVE.pos ? PVE.pos.lat : ''), lng: (PVE.pos ? PVE.pos.lng : '') }) })
		.then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
		.then(function(o){ btn.disabled=false; btn.textContent='Mua vé ngay';
			if(!o.ok || o.d.ok===false){ err.textContent = (o.d && (o.d.message||o.d.code)) || 'Lỗi tạo vé.'; err.hidden=false; return; }
			hienQR(o.d);
		})
		.catch(function(){ btn.disabled=false; btn.textContent='Mua vé ngay'; err.textContent='Lỗi kết nối máy chủ.'; err.hidden=false; });
	});
})();

// ----- Banner carousel tự chạy -----
(function(){
	var bn = document.querySelector('.pve-bn'); if(!bn) return;
	var track = bn.querySelector('.pve-bn-track');
	var dots = [].slice.call(bn.querySelectorAll('.pve-bn-dots span'));
	var n = track.children.length; if (n < 2) return;
	var i = 0;
	function go(k){ i = (k + n) % n; track.style.transform = 'translateX(-' + (i * 100) + '%)'; dots.forEach(function(d,j){ d.classList.toggle('on', j === i); }); }
	dots.forEach(function(d,j){ d.onclick = function(){ go(j); }; });
	setInterval(function(){ go(i + 1); }, 4000);
})();

/* (Nút Đặt vé đã được gắn ở ngay đầu khối — xem "GẮN NÚT ĐẶT VÉ TRƯỚC".) */
mask.querySelector('.pve-x').addEventListener('click', dong);
mask.addEventListener('click', function(e){ if(e.target===mask) dong(); });

qs('.pve-go').addEventListener('click', function(){
	var ten = qs('.pve-f-ten').value.trim(), sdt = qs('.pve-f-sdt').value.trim();
	var err = qs('.pve-err');
	if(!ten || !sdt){ err.textContent='Nhập tên và số điện thoại.'; err.hidden=false; return; }
	err.hidden = true; this.disabled = true; this.textContent='Đang tạo…';
	var btn = this;
	fetch(REST+'/ve/dat', { method:'POST', headers:{'Content-Type':'application/json'},
		body: JSON.stringify({ id: Number(mFor.dataset.id), ten: ten, sdt: sdt }) })
	.then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
	.then(function(o){
		btn.disabled=false; btn.textContent='Tạo mã thanh toán';
		if(!o.ok || o.d.ok===false){ err.textContent = (o.d && (o.d.message||o.d.code)) || 'Lỗi tạo vé.'; err.hidden=false; return; }
		hienQR(o.d);
	})
	.catch(function(){ btn.disabled=false; btn.textContent='Tạo mã thanh toán'; err.textContent='Lỗi kết nối máy chủ.'; err.hidden=false; });
});

function hienQR(v){
	mask.hidden = false;   // mở popup (dùng cho cả form đặt nhanh)
	qs('.pve-r-mave').textContent = v.ma_ve;
	qs('.pve-r-goi').textContent  = v.goi_ten;
	qs('.pve-r-tien').textContent = tien(v.so_tien);
	qs('.pve-r-nh').textContent   = (v.bank&&v.bank.ten_nh)||'';
	qs('.pve-r-stk').textContent  = (v.bank&&v.bank.so_tk)||'';
	qs('.pve-r-ctk').textContent  = (v.bank&&v.bank.ten_tk)||'';
	qs('.pve-r-nd').textContent   = v.noi_dung;
	var box = qs('.pve-qr'); box.innerHTML='';
	loadQR(function(loi){
		if(loi){ box.textContent='(Không tải được mã QR — dùng nội dung CK bên dưới)'; return; }
		new QRCode(box, { text: v.qr, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
	});
	show('qr');
	var badge = qs('.pve-badge');
	qs('.pve-copy').onclick = function(){ try{ navigator.clipboard.writeText(v.noi_dung); }catch(e){} };

	// Chọn phương thức: QR (mặc định) / Momo / VNPay. Momo-VNPay mở cổng ở tab mới.
	var msg = qs('.pve-cong-msg'), bank = qs('.pve-bank');
	function datCong(cong, btn){
		qsa('.pve-cong-i').forEach(function(b){ b.classList.toggle('on', b===btn); });
		if(cong==='qr'){ box.hidden=false; bank.hidden=false; msg.hidden=true; return; }
		box.hidden=true; bank.hidden=true; msg.hidden=false; msg.textContent='Đang mở cổng '+(cong==='momo'?'Momo':'VNPay')+'…';
		fetch(REST+'/ve/thanhtoan', { method:'POST', headers:{'Content-Type':'application/json'},
			body: JSON.stringify({ ma_ve:v.ma_ve, cong:cong }) })
			.then(function(r){ return r.json().then(function(d){ if(!r.ok||d.ok===false) throw new Error(d&&(d.message||d.code)||'Lỗi'); return d; }); })
			.then(function(d){
				var url = d.deeplink || d.pay_url;
				if(url){ msg.innerHTML='Đã mở cổng thanh toán ở tab mới. Nếu bị chặn, <a href="'+url+'" target="_blank" rel="noopener">bấm vào đây</a>. Thanh toán xong quay lại, trang sẽ tự cập nhật.'; window.open(url,'_blank'); }
				else throw new Error('Không tạo được liên kết.');
			})
			.catch(function(e){ msg.textContent = (e.message||e)+' — vui lòng chọn QR ngân hàng.'; });
	}
	qsa('.pve-cong-i').forEach(function(b){ b.onclick=function(){ datCong(b.getAttribute('data-cong'), b); }; });
	datCong('qr', qs('.pve-cong-i[data-cong="qr"]'));
	if(timer) clearInterval(timer);
	timer = setInterval(function(){
		fetch(REST+'/ve/trangthai?ma_ve='+encodeURIComponent(v.ma_ve)).then(function(r){return r.json();}).then(function(d){
			if(d && d.trang_thai==='da_tt'){ badge.className='pve-badge da_tt'; badge.textContent='✅ Đã thanh toán'; clearInterval(timer); timer=null; }
			else if(d && d.trang_thai==='huy'){ badge.className='pve-badge huy'; badge.textContent='✖ Đã huỷ'; clearInterval(timer); timer=null; }
		}).catch(function(){});
	}, 5000);
}
})();
