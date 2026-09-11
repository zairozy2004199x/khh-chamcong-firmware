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
	/* 🔴 KHAI PVE Ở NGAY ĐÂY, TRƯỚC MỌI KHỐI KHÁC.
	   PVE giữ "đang đứng ở cửa hàng nào, giảm bao nhiêu %". Trước đây nó khai tận giữa tệp, cạnh
	   khối định vị — mà `var` chỉ được kéo TÊN lên đầu, GIÁ TRỊ thì không. Khối Giỏ vé (đứng
	   trên) đọc PVE.giam lúc vẽ giỏ, gặp undefined và ném lỗi: 11/09/2026 băng đỏ trên site đọc
	   đúng câu "giỏ vé: Cannot read properties of undefined (reading 'giam')" — mở giỏ ra trống
	   trơn. Khai giá trị ngay từ đầu thì khối nào chạy trước cũng đọc được giá trị thật.
	   ⚠️ Đây CHỈ là để hiện. Giá thật do máy chủ chốt ở POSH_Ve::giam_tai_cho() mỗi lượt đặt. */
	var PVE = { cs:null, pos:null, giam:0, kc:-1, vi:'' };
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
					+ 'white-space:pre-wrap;word-break:break-word;max-height:40vh;overflow:auto;'
					/* pointer-events:none — BẮT BUỘC. Băng này đè lên đầu trang; không tắt bắt sự kiện
					   thì chính cái băng báo lỗi lại chặn nút ngay dưới nó — tự gây thêm một lỗi thứ hai
					   đúng lúc đang báo lỗi thứ nhất. */
					+ 'pointer-events:none';
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
/* 🔴 qs() LẤY THẺ ĐẦU TIÊN TRONG CẢ POPUP, KHÔNG PHẢI TRONG BƯỚC ĐANG MỞ.
   Popup có bốn bước (ví / giỏ / đặt lẻ / mã QR) nằm cùng một khung. Thêm bước mới mà đặt trùng
   tên lớp là nó CƯỚP mất lượt tra của bước cũ — im lặng, không lỗi. Đúng chuyện 11/09/2026:
   bước Giỏ có nút `.pve-go .pve-g-go`, mà nó đứng TRƯỚC bước đặt lẻ trong trang, nên
   qs('.pve-go') trả về nút của Giỏ; việc gắn cho nút "Tạo mã thanh toán" của bước đặt lẻ đi lạc
   sang nút khác và bấm vào nó không ra gì — trong khi mua qua Giỏ thì vẫn chạy.
   Nên mọi thứ thuộc bước ĐẶT LẺ tra bằng qf() (có buộc phạm vi), đừng dùng qs() nữa. */
function qf(s){ return mask.querySelector('.pve-step-form ' + s); }
/* Cùng luật cho bước MÃ QR. Lần thứ tư dính bẫy này (11/09/2026): ví vé vẽ mỗi vé một
   `<span class="pve-badge">`, mà bước ví đứng TRƯỚC bước mã QR — nên qs('.pve-badge') trả về
   nhãn của một tấm vé trong ví, và việc đổi "Chờ thanh toán" -> "Đã thanh toán" đi lạc sang đó.
   Khách nhìn màn chuyển khoản thấy đứng im mãi, dù tiền đã vào và quản trị đã cộng ví. */
function qq(s){ return mask.querySelector('.pve-step-qr ' + s); }
/* ═══ HỎI MÁY CHỦ, KHÔNG LẤY BẢN CŨ TRONG ĐỆM ══════════════════════════════════════════════
   🔴 Trang hỏi trạng thái 5 giây một lượt bằng CÙNG MỘT ĐỊA CHỈ. Trình duyệt — và nhất là lớp
   nhớ đệm của site (SpeedyCache) — hoàn toàn có thể trả lại y nguyên câu trả lời cũ mà không
   hỏi máy chủ. Khi ấy tiền đã về, máy chủ đã đổi trạng thái, mà màn hình khách đứng im: phải
   F5 mới thấy. Đúng chuyện 11/09/2026.
   Hai lớp chặn: `cache:'no-store'` và thêm một tham số đổi theo từng lượt để địa chỉ không
   bao giờ lặp lại. */
function layMoi(url){
	var u = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
	return fetch(u, { credentials: 'same-origin', cache: 'no-store' });
}
function qqa(s){ return Array.prototype.slice.call(mask.querySelectorAll('.pve-step-qr ' + s)); }
function qsa(s){ return Array.prototype.slice.call(mask.querySelectorAll(s)); }
/* Ba bước dùng chung một khung popup: giỏ / form đặt lẻ / mã QR. Duyệt theo data-step thay vì
   gọi tên từng khối — thêm bước thứ tư sau này khỏi phải sửa hàm này. */
function show(step){
	qsa('.pve-step').forEach(function(o){ o.hidden = (o.getAttribute('data-step') !== step); });
}
function loadQR(cb){
	if (window.QRCode){ cb(); return; }
	var s=document.createElement('script');
	s.src='https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
	s.onload=cb; s.onerror=function(){ cb('x'); }; document.head.appendChild(s);
}
function moModal(card){
	mFor = card;
	qf('.pve-m-ten').textContent = card.dataset.ten;
	qf('.pve-m-gia').textContent = tien(card.dataset.gia);
	qf('.pve-err').hidden = true; qf('.pve-f-ten').value=''; qf('.pve-f-sdt').value='';
	zaloVaoForm();   /* xoá ô xong mới điền, không thì điền rồi lại bị xoá ngay */
	napNutVi();      /* ví đủ tiền cho ĐÚNG vé này thì mới hiện nút trả bằng ví */
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
	layMoi(REST + '/zalo/toi')
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

/* ═══ NHỚ NGƯỜI MUA NGAY TRÊN MÁY ═══════════════════════════════════════════════════════════
   Zalo web không đưa số điện thoại, mà máy chủ chỉ tra được số từ vé ĐÃ đặt — nên ngay sau lượt
   đặt đầu tiên, trong cùng phiên, ô số vẫn trống (thông tin Zalo đã lấy về từ lúc mở trang, có
   tải lại đâu mà biết). Đúng cái anh Thắng thấy: đặt xong một vé, mở vé thứ hai vẫn phải gõ lại.

   Nên nhớ luôn tại máy: đặt được vé thì ghi tên + số vào localStorage và cập nhật ngay bộ nhớ
   trong phiên. Cách này còn chạy cho cả khách KHÔNG đăng nhập Zalo. */
var KHO = 'posh_ve_kh';
function khachDaLuu(){
	try { return JSON.parse(localStorage.getItem(KHO) || 'null') || null; } catch (e) { return null; }
}
function nhoKhach(ten, sdt){
	if (!ten && !sdt) return;
	try { localStorage.setItem(KHO, JSON.stringify({ ten: ten || '', sdt: sdt || '' })); } catch (e) {}
	if (!ZME) { ZME = { dangnhap: false }; }
	if (ten) ZME.ten = ZME.ten || ten;
	if (sdt) ZME.sdt = sdt;
}
/* ═══ VÍ TIỀN ══════════════════════════════════════════════════════════════════════════════
   Khách nạp trước, chọn mệnh giá có sẵn, nhập mã ưu đãi thì được tặng thêm; mua vé trừ thẳng
   vào ví.

   ⚠️ SỐ DƯ HIỆN Ở ĐÂY CHỈ ĐỂ NHÌN. Mỗi lượt mua, máy chủ trừ bằng một câu UPDATE có điều kiện
   `so_du >= số tiền` (POSH_Ve::vi_tru) — sửa con số trong trình duyệt không mua thêm được đồng
   nào, mà hai tab bấm cùng lúc cũng không trừ được hai lần.

   Ví đi theo tài khoản ZALO, không theo số điện thoại: số điện thoại không phải bí mật, ai gõ
   số người khác cũng tiêu được tiền của họ. */
var VI = { dangnhap:false, so_du:0, goi:[] };
var NAP = { menh_gia:0, code:'', tang:0 };
function viTienVe(){
	var chip = document.querySelector('.pve-vitien');
	if (chip){ chip.hidden = !VI.dangnhap; var b = chip.querySelector('.pve-vitien-sd'); if (b) b.textContent = tien(VI.so_du); }
	/* Nút "Trả bằng ví" chỉ hiện khi ví ĐỦ tiền cho đúng đơn đang xem. Hiện sẵn rồi mới báo
	   thiếu là bắt khách bấm một nút để nhận lời từ chối. */
	napNutVi();
}
/* Từ 1.58.0 việc chọn ví hay chuyển khoản dồn về bước "Chọn cách thanh toán" (moTraTien), nên
   không còn nút "Trả bằng ví" rời rạc ở hai bước mua nữa — một chỗ quyết, một chỗ sửa. */
function napNutVi(){}
function viTienTai(){
	return layMoi(REST + '/vi/toi')
		.then(function(r){ return r.json(); })
		.then(function(d){ if (d){ VI = { dangnhap: !!d.dangnhap, so_du: d.so_du || 0, goi: d.goi || [] }; } viTienVe(); })
		.catch(function(){});
}
boc('ví tiền', function(){ viTienTai(); });

function moNap(){
	qf2('.pve-nap-sd').innerHTML = 'Số dư hiện tại: <b>' + tien(VI.so_du) + '</b>';
	var box = qf2('.pve-nap-goi'); box.innerHTML = '';
	/* 🔴 LỚP RIÊNG CHO DÒNG NÀY — ĐỪNG DÙNG LẠI .pve-nap-tt.
	   Dòng tổng kết cũng mang lớp .pve-nap-tt và qf2() lấy thẻ ĐẦU TIÊN trong bước; khối gói nạp
	   đứng trước nó, nên napTong() tưởng dòng báo này là dòng tổng kết và XOÁ TRẮNG nó. Kết quả:
	   chưa khai gói nạp thì popup trống trơn, không nói gì, bấm Tạo mã chỉ báo "Chọn một mệnh
	   giá" — đúng cái anh Thắng gặp 11/09/2026. Cùng họ với lỗi nút Đặt vé ở 1.48.0. */
	var trong = !VI.goi.length;
	qf2('.pve-nap-lb').hidden = trong;
	qf2('.pve-nap-code-h').hidden = trong;
	qf2('.pve-nap-go').hidden = trong;
	if (trong){
		box.innerHTML = '<div class="pve-nap-trong">Chưa khai mệnh giá nạp nào.<br>'
			+ 'Nhờ quản trị vào <b>WP Admin → Vé khu vui chơi → Ví tiền</b> khai gói nạp rồi lưu lại.</div>';
	}
	VI.goi.forEach(function(g, i){
		var e = document.createElement('div'); e.className = 'pve-nap-i' + (i === 0 ? ' on' : '');
		e.innerHTML = '<b>' + tien(g.nap) + '</b>' + (g.tang ? '<small>+' + tien(g.tang) + '</small>' : '<small>&nbsp;</small>');
		e.onclick = function(){
			[].slice.call(box.children).forEach(function(x){ x.classList.remove('on'); });
			e.classList.add('on'); NAP.menh_gia = g.nap; napTong();
		};
		box.appendChild(e);
	});
	NAP.menh_gia = VI.goi.length ? VI.goi[0].nap : 0; NAP.code = ''; NAP.tang = 0;
	qf2('.pve-nap-code').value = ''; qf2('.pve-nap-err').hidden = true;
	napTong();
	show('nap'); mask.hidden = false;
}
function goiTang(mg){ for (var i=0;i<VI.goi.length;i++){ if (VI.goi[i].nap === mg) return VI.goi[i].tang || 0; } return 0; }
function napTong(){
	var o = qf2('.pve-nap-tt');
	if (!NAP.menh_gia){ o.textContent = ''; return; }
	var tong = NAP.menh_gia + goiTang(NAP.menh_gia) + NAP.tang;
	o.className = 'pve-nap-tt ok';
	o.textContent = 'Chuyển ' + tien(NAP.menh_gia) + ' → vào ví ' + tien(tong)
		+ ((goiTang(NAP.menh_gia) + NAP.tang) ? (' (tặng ' + tien(goiTang(NAP.menh_gia) + NAP.tang) + ')') : '');
}
/* Tra trong bước NẠP. Cùng lý do với qf(): bước nào tra bước ấy, đừng để bước khác cướp lượt. */
function qf2(s){ return mask.querySelector('.pve-step-nap ' + s); }

boc('nạp ví', function(){
	var nutNap = document.querySelector('.pve-vitien-nap');
	if (nutNap) nutNap.onclick = function(){ viTienTai().then(moNap); };

	var thu = qf2('.pve-nap-thu');
	if (thu) thu.onclick = function(){
		var code = qf2('.pve-nap-code').value.trim();
		var o = qf2('.pve-nap-tt');
		if (!code){ NAP.code = ''; NAP.tang = 0; napTong(); return; }
		/* Mã ưu đãi tính theo mệnh giá (có mã chỉ áp dụng từ 200k trở lên), nên chưa chọn mệnh
		   giá thì không kiểm được. Nói ra, đừng gọi máy chủ rồi im lặng. */
		if (!NAP.menh_gia){ o.className = 'pve-nap-tt no'; o.textContent = 'Chọn mệnh giá nạp trước, rồi mới kiểm được mã.'; return; }
		fetch(REST + '/vi/thu-ma', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
			/* Gửi kèm mã cửa hàng + toạ độ: mã ưu đãi có thể khai riêng cho một quầy, và máy chủ
			   tự kiểm khách có đứng ở đó thật không (POSH_Ve::cs_dang_dung). */
			body: JSON.stringify({ code: code, menh_gia: NAP.menh_gia,
				cs: (PVE.cs ? PVE.cs.ma : ''), lat: (PVE.pos ? PVE.pos.lat : ''), lng: (PVE.pos ? PVE.pos.lng : '') }) })
		.then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
		.then(function(x){
			if (!x.ok || x.d.ok === false){
				NAP.code = ''; NAP.tang = 0;
				/* In NGUYÊN câu máy chủ nói: hết hạn, chưa đủ mệnh giá tối thiểu, hết lượt là ba
				   chuyện khác nhau, và hai trong ba khách tự xử lý được nếu biết. */
				o.className = 'pve-nap-tt no'; o.textContent = (x.d && (x.d.message || x.d.code)) || 'Mã không dùng được.';
				return;
			}
			NAP.code = x.d.code; NAP.tang = x.d.tang || 0; napTong();
		})
		.catch(function(){ o.className = 'pve-nap-tt no'; o.textContent = 'Lỗi kết nối máy chủ.'; });
	};

	var go = qf2('.pve-nap-go');
	if (go) go.onclick = function(){
		var err = qf2('.pve-nap-err');
		if (!NAP.menh_gia){ err.textContent = 'Chọn một mệnh giá.'; err.hidden = false; return; }
		err.hidden = true; var btn = this; btn.disabled = true; btn.textContent = 'Đang tạo…';
		fetch(REST + '/vi/nap', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
			body: JSON.stringify({ menh_gia: NAP.menh_gia, code: NAP.code,
				cs: (PVE.cs ? PVE.cs.ma : ''), lat: (PVE.pos ? PVE.pos.lat : ''), lng: (PVE.pos ? PVE.pos.lng : '') }) })
		.then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
		.then(function(x){
			btn.disabled = false; btn.textContent = 'Tạo mã chuyển khoản';
			if (!x.ok || x.d.ok === false){ err.textContent = (x.d && (x.d.message || x.d.code)) || 'Lỗi tạo lệnh nạp.'; err.hidden = false; return; }
			hienQR({ ma_ve: x.d.ma, goi_ten: 'Nạp ví ' + tien(x.d.so_tien) + (x.d.tang ? (' + tặng ' + tien(x.d.tang)) : ''),
				so_tien: x.d.so_tien, noi_dung: x.d.noi_dung, qr: x.d.qr, qr_svg: x.d.qr_svg, bank: x.d.bank, la_nap: 1 });
		})
		.catch(function(){ btn.disabled = false; btn.textContent = 'Tạo mã chuyển khoản';
			err.textContent = 'Lỗi kết nối máy chủ.'; err.hidden = false; });
	};

});

function gioTong(){
	var t = 0;
	for (var id in GIO){ var c = theVe(id); if (c) t += giaSauGiam(c.getAttribute('data-gia')) * GIO[id]; }
	return t;
}

/* ═══ VÍ VÉ ════════════════════════════════════════════════════════════════════════════════
   Mua xong thì mã vé vào ví ngay trên máy, kèm QR để nhân viên quét ở cửa.

   Danh sách mã nằm ở máy khách; máy chủ chỉ làm tươi trạng thái và dựng QR (POSH_Ve::r_vi).
   Lý do không tra theo số điện thoại nằm ở chú thích hàm ấy — số điện thoại không phải bí mật,
   mà mã vé chính là thứ đưa ra cổng để vào cửa. */
var VKHO = 'posh_ve_vi';
function viDoc(){
	try { var d = JSON.parse(localStorage.getItem(VKHO) || '[]'); return Array.isArray(d) ? d : []; }
	catch (e) { return []; }
}
function viThem(ma){
	if (!ma) return;
	var d = viDoc();
	if (d.indexOf(ma) === -1) { d.unshift(ma); }
	try { localStorage.setItem(VKHO, JSON.stringify(d.slice(0, 100))); } catch (e) {}
	viNut(viDoc().length);
}
function viNut(n){
	var nut = document.getElementById('pve-vi-nut'); if (!nut) return;
	nut.hidden = !n;
	var o = nut.querySelector('.pve-vi-n'); if (o) o.textContent = n;
}
var VE_NHAN = { cho: '⏳ Chờ thanh toán', da_tt: '✅ Sẵn sàng vào cửa', da_dung: '🎟️ Đã sử dụng', huy: '✖ Đã huỷ' };
/* Phóng to mã QR ra hết màn hình. Dựng riêng chứ không nhét thêm một bước vào popup: nó phải
   nằm TRÊN popup (khách đang mở ví), và đóng bằng một chạm ở bất kỳ đâu. */
function phongTo(x){
	var o = document.createElement('div');
	o.className = 'pve-qrto';
	o.innerHTML = '<div class="pve-qrto-in">'
		+ '<div class="pve-qrto-goi">' + esc(x.goi_ten) + '</div>'
		+ '<div class="pve-qrto-qr">' + (x.qr_svg || '') + '</div>'
		+ '<div class="pve-qrto-ma">' + esc(x.ma_ve) + '</div>'
		+ '<div class="pve-qrto-tt">' + (VE_NHAN[x.trang_thai] || '') + '</div>'
		+ '<div class="pve-qrto-dong">Chạm để đóng</div></div>';
	o.onclick = function(){ o.remove(); };
	document.body.appendChild(o);
}
function moVi(){
	var ds = qs('.pve-vi-ds'), tr = qs('.pve-vi-trong');
	ds.innerHTML = ''; tr.hidden = false; tr.textContent = 'Đang tải ví…';
	show('vi'); mask.hidden = false;
	fetch(REST + '/ve/vi', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
		body: JSON.stringify({ ma: viDoc() }) })
	.then(function(r){ return r.json(); })
	.then(function(d){
		var v = (d && d.ve) || [];
		if (!v.length){ tr.textContent = 'Chưa có vé nào trên máy này.'; return; }
		tr.hidden = true;
		v.forEach(function(x){
			var el = document.createElement('div'); el.className = 'pve-vi-the';
			var phu = x.trang_thai === 'da_dung'
				? ('Đã dùng' + (x.coso_dung ? ' tại ' + esc(x.coso_dung) : ''))
				: (x.coso ? ('Mua tại ' + esc(x.coso)) : 'Mua từ xa — dùng được ở mọi cơ sở');
			el.innerHTML = '<div class="pve-vi-top"><span class="pve-vi-goi">' + esc(x.goi_ten) + '</span>'
				+ '<span class="pve-badge ' + esc(x.trang_thai) + '">' + (VE_NHAN[x.trang_thai] || esc(x.trang_thai)) + '</span></div>'
				+ '<div class="pve-vi-ma">' + esc(x.ma_ve) + '</div>'
				/* VỪA MÃ CHỮ VỪA MÃ QR, cho mọi trạng thái. Nhân viên quét là ra ngay vé còn dùng
				   được hay đã soát rồi ở đâu — đó mới là thứ gỡ được tranh cãi tại quầy.
				   ⚠️ Vé đã dùng / đã huỷ thì QR phải LÀM MỜ và dán nhãn đè lên: đưa ra một mã
				   trông y như vé thật mà không vào được cửa còn dễ cãi nhau hơn. */
				+ (x.qr_svg
					? '<div class="pve-vi-qr' + (x.trang_thai === 'da_tt' ? '' : ' mo') + '" data-ma="' + esc(x.ma_ve) + '">'
						+ x.qr_svg
						+ (x.trang_thai === 'da_tt' ? '<span class="pve-vi-to">Chạm để phóng to</span>'
							: '<span class="pve-vi-dau">' + (VE_NHAN[x.trang_thai] || '') + '</span>')
					+ '</div>'
					: '')
				+ '<div class="pve-vi-phu">' + tien(x.so_tien) + ' · ' + phu + '</div>';
			/* Chạm vào ô QR là phóng to hết màn — quầy đông, màn hình nghiêng, đèn kém thì tấm
			   QR 190px trong danh sách quét mãi không ăn. */
			var oqr = el.querySelector('.pve-vi-qr');
			if (oqr) { oqr.onclick = function(){ phongTo(x); }; }
			ds.appendChild(el);
		});
	})
	.catch(function(){ tr.textContent = 'Không tải được ví — kiểm tra mạng rồi thử lại.'; });
}

/* ═══ GIỎ VÉ ════════════════════════════════════════════════════════════════════════════════
   Khách mua cho cả nhà thì đặt lẻ từng vé là từng ấy lượt chuyển khoản, từng ấy nội dung phải
   gõ đúng — và nếu gõ sai một cái thì đơn ấy không tự khớp. Giỏ gộp cả nhóm thành MỘT mã.

   Giỏ chỉ giữ { id vé: số lượng }. Tên, giá, khu vực đọc lại từ thẻ vé trong trang mỗi lần vẽ:
   chép giá vào giỏ rồi khách để đó vài hôm là giá trong giỏ nói một đằng, giá thật một nẻo. Vé
   đã bị xoá khỏi trang thì tự rụng khỏi giỏ.

   ⚠️ Giá ở đây CHỈ ĐỂ HIỆN. Máy chủ tính lại toàn bộ ở POSH_Ve::r_dat_gio(). */
var GKHO = 'posh_ve_gio';
var GIO = {};
function gioDoc(){
	try { var d = JSON.parse(localStorage.getItem(GKHO) || '{}'); return (d && typeof d === 'object') ? d : {}; }
	catch (e) { return {}; }
}
function gioGhi(){ try { localStorage.setItem(GKHO, JSON.stringify(GIO)); } catch (e) {} }
function theVe(id){ return document.querySelector('.pve-card[data-id="' + String(id).replace(/"/g,'') + '"]'); }
function gioSo(){ var n = 0; for (var k in GIO) { if (theVe(k)) n += GIO[k]; } return n; }
function gioThem(id, sl){
	id = String(id); if (!theVe(id)) return;
	GIO[id] = Math.max(0, (GIO[id] || 0) + (sl === undefined ? 1 : sl));
	if (!GIO[id]) delete GIO[id];
	gioGhi(); gioNut(); gioVe();
}
function gioNut(){
	var nut = document.getElementById('pve-gio-nut'); if (!nut) return;
	var n = gioSo();
	nut.hidden = !n;
	var o = nut.querySelector('.pve-gio-n'); if (o) o.textContent = n;
	/* Thẻ vé đã có trong giỏ thì nút ＋ đổi màu — khỏi phải mở giỏ ra mới biết đã thêm chưa. */
	qsaAll('.pve-card').forEach(function(c){
		var b = c.querySelector('.pve-them'); if (!b) return;
		var co = !!GIO[c.getAttribute('data-id')];
		b.classList.toggle('da', co);
		b.textContent = co ? ('✓' + GIO[c.getAttribute('data-id')]) : '＋';
	});
}
function qsaAll(sel){ return [].slice.call(document.querySelectorAll(sel)); }
/* Vẽ lại danh sách trong popup giỏ. Gọi cả lúc popup đang đóng cũng không sao. */
function gioVe(){
	var ds = qs('.pve-gio-ds'); if (!ds) return;
	ds.innerHTML = '';
	var tong = 0, co = 0;
	for (var id in GIO) {
		var card = theVe(id);
		if (!card) { delete GIO[id]; continue; }   /* vé đã gỡ khỏi trang -> rụng khỏi giỏ */
		var sl = GIO[id], don = giaSauGiam(card.getAttribute('data-gia'));
		tong += don * sl; co++;
		var h = document.createElement('div'); h.className = 'pve-gio-h';
		h.innerHTML = '<div class="pve-gio-ten">' + esc(card.getAttribute('data-ten'))
			+ '<small>' + tien(don) + (PVE.giam ? (' (-' + PVE.giam + '%)') : '') + '</small></div>'
			+ '<div class="pve-gio-sl"><button type="button" data-b="tru">−</button><b>' + sl
			+ '</b><button type="button" data-b="cong">+</button></div>'
			+ '<button type="button" class="pve-gio-bo" title="Bỏ khỏi giỏ">✕</button>';
		h.querySelector('[data-b="tru"]').onclick = function(i){ return function(){ gioThem(i, -1); }; }(id);
		h.querySelector('[data-b="cong"]').onclick = function(i){ return function(){ gioThem(i, 1); }; }(id);
		h.querySelector('.pve-gio-bo').onclick = function(i){ return function(){ delete GIO[i]; gioGhi(); gioNut(); gioVe(); }; }(id);
		ds.appendChild(h);
	}
	var t = qs('.pve-gio-tien'); if (t) t.textContent = tien(tong);
	var tr = qs('.pve-gio-trong'); if (tr) tr.hidden = !!co;
	var go = qs('.pve-g-go'); if (go) go.disabled = !co;
}
function moGio(){
	gioVe();
	var chip = qs('.pve-zme-g');
	if (ZME && ZME.dangnhap && chip){
		chip.querySelector('.pve-zme-t').textContent = ZME.ten || ('Zalo ' + (ZME.id || ''));
		var a = chip.querySelector('.pve-zme-a'); if (a && ZME.anh) { a.src = ZME.anh; a.hidden = false; }
		chip.hidden = false;
	} else if (chip) { chip.hidden = true; }
	if (ZME){ dienZalo(qs('.pve-g-ten'), ZME.ten); dienZalo(qs('.pve-g-sdt'), ZME.sdt); }
	qs('.pve-g-err').hidden = true;
	napNutVi();
	show('gio'); mask.hidden = false;
}
/* Bắt bằng uỷ quyền trên document — cùng lý do với nút Đặt vé: thẻ vé bị lọc ẩn/hiện, vẽ lại,
   hay khối này chạy trước lúc thẻ vào DOM thì gắn từng nút là chết lặng. */
document.addEventListener('click', function(ev){
	var t = ev.target; if (!t || !t.closest) return;
	var them = t.closest('.pve-them');
	if (them){ var c = them.closest('.pve-card'); if (c){ ev.preventDefault(); gioThem(c.getAttribute('data-id'), 1); } return; }
	if (t.closest('#pve-gio-nut')){ ev.preventDefault(); moGio(); }
	if (t.closest('#pve-vi-nut')){ ev.preventDefault(); moVi(); }
});
boc('ví vé', function(){ viNut(viDoc().length); });
boc('giỏ vé', function(){
	GIO = gioDoc();
	gioNut(); gioVe();
	var go = qs('.pve-g-go'); if (!go) return;
	go.addEventListener('click', function(){
		var ten = qs('.pve-g-ten').value.trim(), sdt = qs('.pve-g-sdt').value.trim();
		var err = qs('.pve-g-err'), items = [];
		for (var id in GIO) { if (theVe(id)) items.push({ id: Number(id), sl: GIO[id] }); }
		if (!items.length){ err.textContent = 'Giỏ đang trống.'; err.hidden = false; return; }
		if (!ten || !sdt){ err.textContent = 'Nhập tên và số điện thoại.'; err.hidden = false; return; }
		err.hidden = true;
		var ten_gio = [];
		for (var j = 0; j < items.length; j++) { var c = theVe(items[j].id); if (c) ten_gio.push(items[j].sl + 'x ' + c.getAttribute('data-ten')); }
		moTraTien({
			duong: '/ve/dat-gio',
			than: { items: items, ten: ten, sdt: sdt },
			mota: ten_gio.join(', '),
			tong: gioTong(),
			/* Đặt xong mới dọn giỏ. Dọn trước rồi máy chủ chối (hết vé, chưa khai tài khoản nhận
			   tiền) là khách mất sạch giỏ vừa chọn mà chẳng được vé nào. */
			sau_khi_xong: function(){ GIO = {}; gioGhi(); gioNut(); gioVe(); }
		});
	});
});

boc('nhớ người mua', function(){
	var k = khachDaLuu(); if (!k) return;
	if (!ZME) { ZME = { dangnhap: false, ten: k.ten, sdt: k.sdt }; }
	else { ZME.ten = ZME.ten || k.ten; ZME.sdt = ZME.sdt || k.sdt; }
	dienZalo(document.querySelector('.pve-qf-ten'), k.ten);
	dienZalo(document.querySelector('.pve-qf-sdt'), k.sdt);
});
/* Gọi mỗi lần mở popup: popup có thể mở trước lúc /zalo/toi kịp trả lời. */
function zaloVaoForm(){
	var chip = qf('.pve-zme');
	if (!ZME) { if (chip) chip.hidden = true; return; }
	dienZalo(qf('.pve-f-ten'), ZME.ten);
	dienZalo(qf('.pve-f-sdt'), ZME.sdt);
	/* Chỉ khoe nhãn Zalo khi đúng là đăng nhập Zalo. Nhớ từ máy thì điền im lặng — dán tên tài
	   khoản Zalo lên một thông tin gõ tay là nói sai nguồn gốc của nó. */
	if (chip && !ZME.dangnhap) { chip.hidden = true; }
	else if (chip){
		qf('.pve-zme-t').textContent = ZME.ten || ('Zalo ' + (ZME.id || ''));
		var a = qf('.pve-zme-a');
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
	/* Khung đặt nhanh có mà thiếu ô chọn vé (trang dựng bằng mẫu khác, hoặc ai đó gỡ bớt) thì
	   bỏ cả khối này — chứ không ném lỗi giữa chừng làm chết phần script còn lại. */
	var nutQf = qf.querySelector('.pve-qf-go');
	/* Thiếu ô chọn vé HOẶC thiếu nút thì bỏ cả khối này. Khối đặt-vé-nhanh KHÔNG nằm trong boc(),
	   nên một lỗi ở đây kéo chết mọi thứ phía sau — trong đó có việc gắn nút "Tạo mã thanh toán"
	   của popup. Đúng kiểu hỏng đã mất hai lượt để tìm. */
	if (!selVe || !nutQf) return;
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
	nutQf.addEventListener('click', function(){
		var ten = qf.querySelector('.pve-qf-ten').value.trim();
		var sdt = qf.querySelector('.pve-qf-sdt').value.trim();
		var id = Number(selVe.value || 0);
		var sl = Math.max(1, Number(qf.querySelector('.pve-qf-sl').value || 1));
		var err = qf.querySelector('.pve-qf-err');
		if(!id){ err.textContent='Vui lòng chọn loại vé.'; err.hidden=false; return; }
		if(!ten || !sdt){ err.textContent='Nhập tên và số điện thoại.'; err.hidden=false; return; }
		err.hidden = true;
		/* "MUA VÉ NGAY" chỉ là CHỌN NHANH. Đơn chưa được tạo ở đây — sang bước chọn cách trả. */
		var the = theVe(id);
		var don_gia = the ? giaSauGiam(the.getAttribute('data-gia')) : 0;
		moTraTien({
			duong: '/ve/dat-gio',
			than: { items: [{ id: id, sl: sl }], ten: ten, sdt: sdt },
			mota: sl + 'x ' + (the ? the.getAttribute('data-ten') : 'vé'),
			tong: don_gia * sl
		});
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

qf('.pve-go').addEventListener('click', function(){
	var ten = qf('.pve-f-ten').value.trim(), sdt = qf('.pve-f-sdt').value.trim();
	var err = qf('.pve-err');
	if(!ten || !sdt){ err.textContent='Nhập tên và số điện thoại.'; err.hidden=false; return; }
	err.hidden = true;
	moTraTien({
		duong: '/ve/dat',
		than: { id: Number(mFor.dataset.id), ten: ten, sdt: sdt },
		mota: mFor.dataset.ten,
		tong: giaSauGiam(mFor.dataset.gia)
	});
});

/* ═══ BÁO THÀNH CÔNG KHI TRẢ BẰNG VÍ ═══════════════════════════════════════════════════════
   Tràn màn hình, rõ ràng, rồi tự về trang mua vé.

   ⚠️ "Về trang chủ" ở đây là VỀ ĐẦU TRANG MUA VÉ, không phải nhảy sang trang chủ của website:
   khách vừa mua xong thường mua tiếp hoặc mở ví xem vé — ném họ ra khỏi trang bán hàng là bắt
   tìm đường quay lại. Muốn rời hẳn thì đã có nút riêng.

   Dựng riêng chứ không thêm một bước vào popup: nó phải nằm TRÊN mọi thứ, kể cả popup đang mở. */
/* Màn HOÀN THÀNH dùng chung cho hai việc đã xong hẳn: trả vé bằng ví, và nạp tiền vào ví.
   Cùng một hình hài vì với khách đó là cùng một cảm giác — xong rồi, về thôi.
   `o` = { tieu, phu, ma, mota, du, nut_chinh, mo_vi } */
/* ═══ MỘT CỬA DUY NHẤT ĐỂ TẠO ĐƠN ══════════════════════════════════════════════════════════
   🔴 ĐƠN CHỈ ĐƯỢC TẠO SAU KHI KHÁCH CHỌN CÁCH TRẢ. Trước đây nút "MUA VÉ NGAY" ở khung đặt
   nhanh vừa chọn vé vừa tạo đơn vừa nhảy thẳng vào màn mã QR chuyển khoản — khách chưa kịp nói
   muốn trả bằng gì thì đơn đã nằm trong sổ, tồn kho đã bị trừ. Anh Thắng 11/09/2026: *"Mua ngay
   là chọn nhanh, chứ thanh toán cũng phải rõ ràng, chọn ví hoặc chuyển khoản"*.

   Nay ba lối mua (đặt nhanh · thẻ vé · giỏ) đều dừng ở đây, hiện tóm tắt + hai cách trả. Bấm
   cách nào mới gọi máy chủ.

   cfg = { duong, than, mota, tong, sau_khi_xong } */
function moTraTien(cfg){
	var tom = qt('.pve-tt-tom');
	tom.innerHTML = esc(cfg.mota) + '<br><b>' + tien(cfg.tong) + '</b>';
	qt('.pve-tt-err').hidden = true;

	var nutVi = qt('.pve-tt-vi'), nho = nutVi.querySelector('small');
	var du = VI.dangnhap && VI.so_du >= cfg.tong;
	nutVi.disabled = !du;
	/* Nói RÕ vì sao không trả ví được — "không bấm được" mà không giải thích là khách tưởng hỏng. */
	nho.textContent = !VI.dangnhap ? 'Đăng nhập Zalo để dùng ví'
		: (du ? ('Ví còn ' + tien(VI.so_du)) : ('Ví còn ' + tien(VI.so_du) + ' — không đủ, cần thêm ' + tien(cfg.tong - VI.so_du)));

	nutVi.onclick = function(){ if (!nutVi.disabled) { goiDat(cfg, true, nutVi); } };
	qt('.pve-tt-ck').onclick = function(){ goiDat(cfg, false, qt('.pve-tt-ck')); };
	qt('.pve-tt-quay').onclick = function(){ mask.hidden = true; };

	show('tt'); mask.hidden = false;
}
function qt(s){ return mask.querySelector('.pve-step-tt ' + s); }

function goiDat(cfg, bang_vi, nut){
	var err = qt('.pve-tt-err');
	var than = {};
	for (var k in cfg.than) { than[k] = cfg.than[k]; }
	if (bang_vi) { than.tt = 'vi'; }
	than.cs = (PVE.cs ? PVE.cs.ma : ''); than.lat = (PVE.pos ? PVE.pos.lat : ''); than.lng = (PVE.pos ? PVE.pos.lng : '');
	err.hidden = true; nut.disabled = true; var chu = nut.innerHTML; nut.innerHTML = '<b>Đang xử lý…</b>';
	fetch(REST + cfg.duong, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
		body: JSON.stringify(than) })
	.then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
	.then(function(o){
		nut.disabled = false; nut.innerHTML = chu;
		if (!o.ok || o.d.ok === false){ err.textContent = (o.d && (o.d.message || o.d.code)) || 'Lỗi tạo vé.'; err.hidden = false; return; }
		nhoKhach(than.ten, than.sdt);
		if (cfg.sau_khi_xong) { cfg.sau_khi_xong(o.d); }
		if (bang_vi){
			VI.so_du = o.d.so_du || 0; viTienVe(); viThem(o.d.ma_ve);
			xongVi(o.d);
		} else {
			hienQR(o.d);
		}
	})
	.catch(function(){ nut.disabled = false; nut.innerHTML = chu;
		err.textContent = 'Lỗi kết nối máy chủ.'; err.hidden = false; });
}

function manXong(o){
	var e = document.createElement('div');
	e.className = 'pve-xong';
	e.innerHTML = '<div class="pve-xong-in">'
		+ '<div class="pve-xong-v">✅</div>'
		+ '<div class="pve-xong-h">' + esc(o.tieu) + '</div>'
		+ '<div class="pve-xong-p">' + esc(o.phu) + '</div>'
		+ '<div class="pve-xong-ma">' + esc(o.ma || '') + '</div>'
		+ '<div class="pve-xong-tien">' + esc(o.mota || '') + '</div>'
		+ (o.du !== undefined ? '<div class="pve-xong-du">Ví còn ' + tien(o.du) + '</div>' : '')
		+ '<div class="pve-xong-nut">'
			+ '<button type="button" class="pve-xong-ve">' + esc(o.nut_chinh) + '</button>'
			+ '<button type="button" class="pve-xong-chu">Về trang mua vé</button>'
		+ '</div>'
		+ '<div class="pve-xong-dem">Tự về sau <b>5</b> giây</div></div>';
	document.body.appendChild(e);
	/* Đóng popup bên dưới NGAY, không đợi lúc tắt màn này: khách chạm nhầm ra sau lại thấy khung
	   cũ với mã QR chuyển khoản — tưởng chưa xong rồi trả lần nữa. */
	try { mask.hidden = true; if (timer) { clearInterval(timer); timer = null; } } catch (x) {}

	function dong(theo_nut){
		if (hen) { clearInterval(hen); hen = null; }
		e.remove();
		if (theo_nut && o.mo_vi) { moVi(); return; }
		try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (x) { window.scrollTo(0, 0); }
	}
	e.querySelector('.pve-xong-ve').onclick  = function(){ dong(true); };
	e.querySelector('.pve-xong-chu').onclick = function(){ dong(false); };

	var con = 5, dem = e.querySelector('.pve-xong-dem b');
	var hen = setInterval(function(){
		con--;
		if (dem) { dem.textContent = con; }
		if (con <= 0) { dong(false); }
	}, 1000);
}

/* Trả vé bằng ví xong. */
function xongVi(v){
	manXong({
		tieu: 'Thanh toán thành công',
		phu: 'Đã trừ ví — vé sẵn sàng vào cửa',
		ma: v.ma_ve,
		mota: (v.goi_ten || '') + ' · ' + tien(v.so_tien),
		du: v.so_du || 0,
		nut_chinh: '🎫 Xem vé của tôi',
		mo_vi: true
	});
}

/* Nạp ví xong — tiền đã vào. */
function xongNap(v, so_du){
	manXong({
		tieu: 'Nạp ví thành công',
		phu: 'Tiền đã vào ví, dùng mua vé được ngay',
		ma: v.ma_ve,
		mota: v.goi_ten || '',
		du: so_du,
		nut_chinh: '🎟️ Mua vé ngay',
		mo_vi: false
	});
}

function hienQR(v){
	/* Lệnh NẠP VÍ cũng dùng lại màn này (cùng một việc: hiện mã QR rồi chờ tiền về), nhưng mã nạp
	   KHÔNG phải vé — bỏ nó vào ví vé là khách thấy một tấm "vé" không vào cửa được. */
	if (!v.la_nap) { viThem(v.ma_ve); }
	mask.hidden = false;   // mở popup (dùng cho cả form đặt nhanh)
	qq('.pve-r-mave').textContent = v.ma_ve;
	qq('.pve-r-goi').textContent  = v.goi_ten;
	qq('.pve-r-tien').textContent = tien(v.so_tien);
	qq('.pve-r-nh').textContent   = (v.bank&&v.bank.ten_nh)||'';
	qq('.pve-r-stk').textContent  = (v.bank&&v.bank.so_tk)||'';
	qq('.pve-r-ctk').textContent  = (v.bank&&v.bank.ten_tk)||'';
	qq('.pve-r-nd').textContent   = v.noi_dung;
	var daTra = ('da_tt' === v.trang_thai);
	var box = qq('.pve-qr'); box.innerHTML='';
	/* Trả bằng ví xong thì tiền đã trừ, vé đã xanh — bày mã QR chuyển khoản ra nữa là mời khách
	   trả lần thứ hai. Giấu cả khối số tài khoản đi. */
	box.hidden = daTra;
	[ '.pve-bank', '.pve-cong' ].forEach(function(sel){ var e = qs(sel); if (e) e.hidden = daTra; });
	/* Máy chủ dựng sẵn mã QR thì dùng luôn — không gọi mạng ngoài, không chờ. Chỉ khi máy chủ
	   không dựng được (thiếu plugin Ghế) mới lùi về thư viện trên CDN như cũ. Xem chú thích ở
	   POSH_Ve::qr_svg(): CDN chết là khách phải gõ tay số tài khoản. */
	if (v.qr_svg) { box.innerHTML = v.qr_svg; }
	else loadQR(function(loi){
		if(loi){ box.textContent='(Không tải được mã QR — dùng nội dung CK bên dưới)'; return; }
		new QRCode(box, { text: v.qr, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
	});
	show('qr');
	var badge = qq('.pve-badge');
	/* 🔴 ĐẶT LẠI NHÃN CHO ĐƠN MỚI. Khung mã QR dùng chung cho mọi lượt, mà nhãn trạng thái là
	   thẻ CÓ SẴN trong trang — không đặt lại thì nó giữ nguyên chữ của đơn trước. Đặt vé tiếp
	   sau một lượt nạp ví là mã mới, số tiền mới, nhưng nhãn vẫn "✅ Đã vào ví": khách tưởng đã
	   trả xong rồi đóng trang, còn vé thì nằm ở "chờ thanh toán" mãi. Đúng chuyện 11/09/2026.
	   Cũng dọn luôn dòng báo của cổng thanh toán lượt trước, cùng lý do. */
	badge.className = 'pve-badge ' + (daTra ? 'da_tt' : 'cho');
	badge.textContent = daTra ? '✅ Đã thanh toán' : '⏳ Chờ thanh toán';
	var msgCu = qq('.pve-cong-msg'); if (msgCu) { msgCu.textContent = ''; msgCu.hidden = true; }
	qq('.pve-copy').onclick = function(){ try{ navigator.clipboard.writeText(v.noi_dung); }catch(e){} };

	// Chọn phương thức: QR (mặc định) / Momo / VNPay. Momo-VNPay mở cổng ở tab mới.
	var msg = qq('.pve-cong-msg'), bank = qq('.pve-bank');
	function datCong(cong, btn){
		qqa('.pve-cong-i').forEach(function(b){ b.classList.toggle('on', b===btn); });
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
	if (daTra){
		badge.className = 'pve-badge da_tt';
		badge.textContent = '✅ Đã trả bằng ví';
		if (timer) { clearInterval(timer); timer = null; }
		return;
	}
	qqa('.pve-cong-i').forEach(function(b){ b.onclick=function(){ datCong(b.getAttribute('data-cong'), b); }; });
	datCong('qr', qq('.pve-cong-i[data-cong="qr"]'));
	if(timer) clearInterval(timer);
	/* Lệnh nạp hỏi đường khác: /vi/nap-tt cộng ví ngay khi thấy tiền về, /ve/trangthai thì đánh
	   dấu vé. Hai việc khác nhau, đừng hỏi nhầm đường — hỏi nhầm là tiền về mà ví vẫn 0đ. */
	timer = setInterval(function(){
		var duong = v.la_nap
			? (REST + '/vi/nap-tt?ma=' + encodeURIComponent(v.ma_ve))
			: (REST + '/ve/trangthai?ma_ve=' + encodeURIComponent(v.ma_ve));
		layMoi(duong).then(function(r){return r.json();}).then(function(d){
			if (!d) return;
			if (v.la_nap){
				if (d.trang_thai === 'xong'){
					badge.className='pve-badge da_tt'; badge.textContent='✅ Đã vào ví';
					VI.so_du = d.so_du || VI.so_du; viTienVe();
					clearInterval(timer); timer=null;
					/* Tiền đã vào ví là XONG HẲN — đừng để khách ngồi nhìn mã QR chuyển khoản mà
					   không biết còn phải làm gì. Báo hoàn thành rồi tự đưa về trang mua vé. */
					xongNap(v, VI.so_du);
				}
				return;
			}
			if(d.trang_thai==='da_tt'){ badge.className='pve-badge da_tt'; badge.textContent='✅ Đã thanh toán'; clearInterval(timer); timer=null; }
			else if(d.trang_thai==='huy'){ badge.className='pve-badge huy'; badge.textContent='✖ Đã huỷ'; clearInterval(timer); timer=null; }
		}).catch(function(){});
	}, 5000);
}

/* ───── Bảng giá theo cơ sở (mẫu anh Thắng gửi 11/09/2026) ─────────────────────────────────
   "Xem giá vé"  → phóng to ảnh bảng giá treo tại quầy; cơ sở chưa khai ảnh thì lọc luôn vé
                   của cơ sở ấy và cuộn xuống — thà đưa khách tới chỗ có giá thật còn hơn mở
                   một hộp trống rồi để họ tự đoán.
   "Đặt vé"      → chọn sẵn cơ sở ở khung đặt vé nhanh rồi cuộn lên. Chỉ lọc khi CÓ vé khai
                   đúng khu ấy: lọc theo một khu không vé nào thuộc về là xoá trắng danh sách,
                   khách tưởng hết vé.                                                        */
function lbMo(ten, anh){
	var lb = document.getElementById('pve-lb'); if (!lb) return false;
	var img = lb.querySelector('.pve-lb-anh'), nh = lb.querySelector('.pve-lb-ten');
	if (!img) return false;
	img.src = anh; if (nh) nh.textContent = ten || 'Bảng giá';
	lb.hidden = false; return true;
}
function lbDong(){ var lb = document.getElementById('pve-lb'); if (lb) lb.hidden = true; }
function coVeKhu(kv){
	if (!kv) return false;
	try { return !!document.querySelector('.pve-card[data-kv="' + kv.replace(/"/g,'\\"') + '"]'); }
	catch(e){ return false; }
}
function bgChonKhu(kv){
	if (coVeKhu(kv)){
		var tab = null, ds = [].slice.call(document.querySelectorAll('.pve-tab[data-loc="kv"]'));
		for (var i = 0; i < ds.length; i++) { if (ds[i].getAttribute('data-v') === kv) { tab = ds[i]; break; } }
		if (tab) tab.click(); else pveLoc(kv, null);
		return true;
	}
	/* Không có vé riêng cho khu này thì vẫn ghi cơ sở vào đơn — cơ sở là chỗ khách tới chơi,
	   không phải bộ lọc. Thiếu bước này là vé bán cho cơ sở ấy vào sổ "mua từ xa".
	   Đồng thời MỞ LẠI bộ lọc về "Tất cả": giữ nguyên khu chọn lần trước thì khách vừa bấm
	   cơ sở này lại đang nhìn đúng danh sách vé của cơ sở khác. */
	var tatca = document.querySelector('.pve-tab[data-loc="kv"][data-v=""]');
	if (tatca) tatca.click(); else if (typeof pveLoc === 'function') pveLoc('', null);
	var sel = document.querySelector('.pve-qf-cs');
	if (sel){ for (var j = 0; j < sel.options.length; j++) { if (sel.options[j].value === kv) { sel.value = kv; return true; } } }
	return false;
}
boc('bảng giá cơ sở', function(){
	document.addEventListener('click', function(ev){
		var t = ev.target; if (!t || !t.closest) return;
		var xem = t.closest('.pve-bg-xem');
		if (xem){
			ev.preventDefault();
			var anh = xem.getAttribute('data-anh') || '', cs = xem.getAttribute('data-cs') || '';
			if (anh && lbMo(cs, anh)) return;
			bgChonKhu(cs);
			var ds = document.getElementById('pve-ds');
			if (ds) ds.scrollIntoView({ behavior:'smooth', block:'start' });
			return;
		}
		var dat = t.closest('.pve-bg-dat');
		if (dat){
			ev.preventDefault();
			bgChonKhu(dat.getAttribute('data-cs') || '');
			/* Khung đặt vé giờ nằm gọn sau một nút, nên cuộn tới nó thôi là khách nhìn thấy…
			   đúng cái nút vừa bấm. Mở thẳng màn đặt vé. */
			if (typeof datMo === 'function' && datMo()){
				var qf0 = document.getElementById('pve-qf');
				var ve0 = qf0 && qf0.querySelector('.pve-qf-ve');
				if (ve0) try { ve0.focus({ preventScroll:true }); } catch(e){ }
				return;
			}
			var qf = document.getElementById('pve-qf');
			if (qf){
				qf.scrollIntoView({ behavior:'smooth', block:'center' });
				var ve = qf.querySelector('.pve-qf-ve'); if (ve) try { ve.focus({ preventScroll:true }); } catch(e){ }
			}
			return;
		}
		if (t.closest('.pve-lb-x') || t.id === 'pve-lb'){ ev.preventDefault(); lbDong(); }
	});
	document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape') lbDong(); });
});


/* ───── Khung đặt vé: gọn ở trang, mở ra thành MÀN ĐẶT VÉ tràn trang ───────────────────────
   Anh Thắng 11/09/2026: *"Nên hiện gọn thành chữ mua vé ngay thôi, khi khách bấm mua vé mới
   hiện ra"* và *"Bấm mua vé thì ra trang và hiện thông tin để đặt vé"*.

   ⚠️ CHUYỂN nguyên khối #pve-qf vào màn đặt vé rồi trả về chỗ cũ, KHÔNG dựng khung thứ hai:
      khối xử lý "Đặt vé nhanh" giữ sẵn tham chiếu tới các ô bên trong #pve-qf từ lúc tải
      trang. Chép ra một khung khác là nút MUA VÉ bấm vào đọc ô của khung cũ — người ta điền
      một đằng, đơn đi một nẻo.                                                               */
function datMo(){
	var qf = document.getElementById('pve-qf'), man = document.getElementById('pve-dat');
	if (!qf || !man) return false;
	var o = document.getElementById('pve-dat-form'); if (!o) return false;
	if (qf.parentNode !== o) o.appendChild(qf);
	var than = qf.querySelector('.pve-qf-than'), nut = qf.querySelector('.pve-qf-mo');
	if (than) than.hidden = false;
	if (nut) nut.hidden = true;
	qf.classList.add('mo');
	man.hidden = false;
	try { document.body.style.overflow = 'hidden'; } catch(e){}
	datTom();
	return true;
}
function datDong(){
	var qf = document.getElementById('pve-qf'), man = document.getElementById('pve-dat');
	var cho = document.getElementById('pve-qf-cho');
	if (man) man.hidden = true;
	try { document.body.style.overflow = ''; } catch(e){}
	if (qf && cho && cho.parentNode) cho.parentNode.insertBefore(qf, cho.nextSibling);
	if (qf){
		var than = qf.querySelector('.pve-qf-than'), nut = qf.querySelector('.pve-qf-mo');
		if (than) than.hidden = true;
		if (nut) nut.hidden = false;
		qf.classList.remove('mo');
	}
}
/* Ô "Thông tin đặt hàng": đọc thẳng từ ô chọn vé đang mở, không giữ bản sao giá riêng —
   giá đã giảm tại quầy do giaSauGiam() tính, hai nơi tự tính là hai con số. */
function datTom(){
	var o = document.querySelector('.pve-dat-tt-b'); if (!o) return;
	var qf = document.getElementById('pve-qf'); if (!qf) return;
	var sel = qf.querySelector('.pve-qf-ve'), slo = qf.querySelector('.pve-qf-sl');
	var cso = qf.querySelector('.pve-qf-cs');
	var id = sel ? Number(sel.value || 0) : 0;
	if (!id){ o.innerHTML = 'Vui lòng chọn vé!'; return; }
	var the = theVe(id);
	var sl  = Math.max(1, Number(slo && slo.value || 1));
	var don = the ? giaSauGiam(the.getAttribute('data-gia')) : 0;
	var h = '';
	if (cso && cso.value) h += '<div class="d"><span>Cơ sở</span><b>' + esc(cso.value) + '</b></div>';
	h += '<div class="d"><span>Vé</span><b>' + esc(the ? the.getAttribute('data-ten') : '') + '</b></div>'
	   + '<div class="d"><span>Đơn giá</span><b>' + tien(don) + '</b></div>'
	   + '<div class="d"><span>Số lượng</span><b>' + sl + '</b></div>'
	   + '<div class="d tong"><span>Tạm tính</span><b>' + tien(don * sl) + '</b></div>';
	o.innerHTML = h;
}
boc('màn đặt vé', function(){
	var man = document.getElementById('pve-dat');
	document.addEventListener('click', function(ev){
		var t = ev.target; if (!t || !t.closest) return;
		if (t.closest('.pve-qf-mo')){ ev.preventDefault(); datMo(); return; }
		if (t.closest('.pve-dat-x')){ ev.preventDefault(); datDong(); return; }
		/* Bấm MUA VÉ mà popup chọn cách trả đã mở = đặt xong phần chọn -> đóng màn đặt vé, để
		   trả tiền xong khách quay về đúng trang chứ không phải cái khung vừa điền. Popup chưa
		   mở nghĩa là còn thiếu thông tin: giữ nguyên màn, dòng báo lỗi nằm ngay trong đó. */
		if (t.closest('.pve-qf-go')){
			setTimeout(function(){
				var m = document.querySelector('.pve-mask');
				if (m && !m.hidden) datDong();
			}, 0);
			return;
		}
		/* Nút "Đặt vé ngay" trên thanh đầu trang trỏ tới #pve-qf — mà #pve-qf giờ chỉ còn một
		   nút, nhảy tới đó rồi vẫn phải bấm thêm một lần. Mở luôn cho xong. */
		var a = t.closest('a[href="#pve-qf"]');
		if (a){ ev.preventDefault(); datMo(); }
	});
	document.addEventListener('keydown', function(ev){
		if (ev.key === 'Escape' && man && !man.hidden) datDong();
	});
	/* Cập nhật ô tóm tắt mỗi lần khách đổi vé / số lượng / cơ sở. */
	['change','input'].forEach(function(e){
		document.addEventListener(e, function(ev){
			if (!man || man.hidden) return;
			var t = ev.target;
			if (t && t.closest && t.closest('#pve-qf')) datTom();
		});
	});
});


/* ───── Chi tiết vé: các tab thông tin nhân viên nhập cho từng vé ──────────────────────────
   Nội dung đã nằm sẵn trong thẻ vé (khối .pve-ct-kho ẩn). Ở đây chỉ bê sang khung xem và
   đổi tab — KHÔNG gọi máy chủ: khách bấm Chi tiết là muốn đọc ngay, mà một lượt gọi mạng
   nữa thì trên 3G nó quay vòng vài giây rồi mới ra chữ.                                     */
function ctMo(card){
	var khung = document.getElementById('pve-ct'); if (!khung || !card) return;
	var kho = card.querySelector('.pve-ct-kho'); if (!kho) return;
	var pha = [].slice.call(kho.querySelectorAll('.pve-ct-pha'));
	if (!pha.length) return;
	var ten = khung.querySelector('.pve-ct-ten');
	if (ten) ten.textContent = card.getAttribute('data-ten') || 'Chi tiết vé';
	var oTab = khung.querySelector('.pve-ct-tabs'), oNoi = khung.querySelector('.pve-ct-noi');
	oTab.innerHTML = ''; oNoi.innerHTML = '';
	pha.forEach(function(x, i){
		var b = document.createElement('button');
		b.type = 'button'; b.textContent = x.getAttribute('data-nhan') || ('Mục ' + (i + 1));
		if (!i) b.className = 'on';
		b.onclick = function(){
			[].slice.call(oTab.children).forEach(function(y){ y.classList.toggle('on', y === b); });
			oNoi.innerHTML = x.innerHTML;
		};
		oTab.appendChild(b);
	});
	oNoi.innerHTML = pha[0].innerHTML;
	khung.hidden = false;
}
function ctDong(){ var k = document.getElementById('pve-ct'); if (k) k.hidden = true; }
boc('chi tiết vé', function(){
	document.addEventListener('click', function(ev){
		var t = ev.target; if (!t || !t.closest) return;
		var mo = t.closest('.pve-ct-mo');
		if (mo){ ev.preventDefault(); ctMo(mo.closest('.pve-card')); return; }
		if (t.closest('.pve-ct-x') || t.id === 'pve-ct'){ ev.preventDefault(); ctDong(); }
	});
	document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape') ctDong(); });
});

})();
