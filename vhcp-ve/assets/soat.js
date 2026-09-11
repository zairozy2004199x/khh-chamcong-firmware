/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * MÀN HÌNH SOÁT VÉ TẠI QUẦY — đi với shortcode [posh_soat].
 *
 * 🔴 MÀN HÌNH NÀY KHÔNG QUYẾT ĐỊNH CHO AI VÀO CỬA. Nó gửi mã lên và in lại câu trả lời của máy
 * chủ. Mọi luật — đã trả tiền chưa, đã dùng rồi chưa, dùng ở đâu — nằm trong POSH_Ve::r_soat().
 * Để trang tự kết luận thì sửa vài dòng trong trình duyệt là vé nào cũng "hợp lệ".
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
(function(){
	var D = window.PSOAT || {};
	if (!D.rest) { D.rest = location.origin + '/wp-json/posh/v1'; }
	var g = document.querySelector('.psoat'); if (!g) return;
	var KHO = 'posh_soat_ca';
	var CA = null;                 /* { cs: 'Tên cơ sở', pin: '...' } */
	var daThay = {};               /* mã vé đã thấy ở vòng trước — để biết vé nào MỚI về */
	var lanDau = true;
	var hen = null;

	function q(s){ return g.querySelector(s); }
	function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){
		return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
	function tien(n){ try { return (Number(n)||0).toLocaleString('vi-VN') + 'đ'; } catch(e){ return n + 'đ'; } }

	/* ── Vào ca ───────────────────────────────────────────────────────────────────────────
	   Cơ sở + PIN nhớ ngay trên máy ở quầy: khai một lần, đổi ca không phải gõ lại. Gõ PIN lại
	   mỗi lần mở trang thì nhân viên sẽ dán tờ giấy ghi PIN lên cạnh màn hình — tệ hơn nhiều. */
	function caDoc(){
		try { var d = JSON.parse(localStorage.getItem(KHO) || 'null'); return (d && d.cs) ? d : null; }
		catch (e) { return null; }
	}
	function vaoCa(c){
		CA = c;
		try { localStorage.setItem(KHO, JSON.stringify(c)); } catch (e) {}
		q('.psoat-dn').hidden = true;
		q('.psoat-lam').hidden = false;
		q('.psoat-cs-ten').textContent = '📍 ' + c.cs;
		q('.psoat-doi').hidden = false;
		lanDau = true; daThay = {};
		lamTuoi();
		if (hen) clearInterval(hen);
		hen = setInterval(lamTuoi, 10000);
		try { q('.psoat-ma').focus(); } catch (e) {}
	}
	q('.psoat-vao').addEventListener('click', function(){
		var c = { cs: q('.psoat-cs').value, pin: q('.psoat-pin').value.trim() };
		var err = q('.psoat-dn-err');
		if (!c.cs){ err.textContent = 'Chọn cơ sở đang đứng.'; err.hidden = false; return; }
		var btn = this; btn.disabled = true; btn.textContent = 'Đang kiểm…';
		/* Kiểm PIN bằng chính lệnh sẽ dùng cả ca: PIN đúng cho việc lấy danh sách thì cũng đúng
		   cho việc soát. Kiểm bằng một cửa khác là có ngày hai cửa lệch nhau. */
		fetch(D.rest + '/ve/cho-soat?coso=' + encodeURIComponent(c.cs) + '&pin=' + encodeURIComponent(c.pin),
			{ credentials: 'same-origin' })
		.then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
		.then(function(o){
			btn.disabled = false; btn.textContent = 'Vào ca';
			if (!o.ok || o.d.ok === false){
				err.textContent = (o.d && (o.d.message || o.d.code)) || 'Không vào được ca.';
				err.hidden = false; return;
			}
			err.hidden = true; vaoCa(c); ve(o.d.ve || []);
		})
		.catch(function(){ btn.disabled = false; btn.textContent = 'Vào ca';
			err.textContent = 'Lỗi kết nối máy chủ.'; err.hidden = false; });
	});
	q('.psoat-doi').addEventListener('click', function(){
		if (hen) { clearInterval(hen); hen = null; }
		q('.psoat-lam').hidden = true; q('.psoat-dn').hidden = false; q('.psoat-doi').hidden = true;
		q('.psoat-cs-ten').textContent = '';
	});

	/* ── Danh sách vé chờ vào cửa ─────────────────────────────────────────────────────────── */
	function lamTuoi(){
		if (!CA) return;
		/* Thêm tham số đổi theo lượt + no-store: màn này hỏi 10 giây một lượt bằng cùng một địa
		   chỉ, để trình duyệt hay lớp nhớ đệm của site trả lại bản cũ là vé mới không bao giờ
		   hiện ra ở quầy. */
		fetch(D.rest + '/ve/cho-soat?coso=' + encodeURIComponent(CA.cs) + '&pin=' + encodeURIComponent(CA.pin)
				+ '&_=' + Date.now(),
			{ credentials: 'same-origin', cache: 'no-store' })
		.then(function(r){ return r.json(); })
		.then(function(d){ if (d && d.ve) ve(d.ve); })
		.catch(function(){});   /* mất mạng một nhịp thì thôi, vòng sau lấy lại */
	}
	function ve(ds){
		var box = q('.psoat-ds'); box.innerHTML = '';
		var moi = 0;
		ds.forEach(function(x){
			if (!daThay[x.ma_ve]) { moi++; daThay[x.ma_ve] = 1; }
			var el = document.createElement('div'); el.className = 'psoat-the';
			el.innerHTML = '<div class="t"><b>' + esc(x.goi_ten) + '</b>'
				+ '<small>' + esc(x.ten_khach || '') + ' · ' + tien(x.so_tien) + '</small></div>'
				+ (x.tu_xa ? '<span class="xa">mua từ xa</span>' : '')
				+ '<span class="m">' + esc(x.ma_ve) + '</span>';
			/* Bấm vào thẻ là soát luôn vé ấy — khách quên mang mã, đọc tên là tìm thấy trong danh
			   sách rồi bấm, khỏi bắt họ mở điện thoại tìm. */
			el.onclick = function(){ soat(x.ma_ve); };
			box.appendChild(el);
		});
		q('.psoat-n').textContent = ds.length;
		q('.psoat-trong').hidden = !!ds.length;
		/* Chuông chỉ reo cho vé MỚI về, và không reo ở lần tải đầu — mở trang ra mà kêu một tràng
		   cho cả chục vé cũ thì lần sau nhân viên tắt tiếng máy. */
		if (moi && !lanDau) { chuong(); }
		lanDau = false;
	}
	function chuong(){
		try {
			var A = window.AudioContext || window.webkitAudioContext; if (!A) return;
			var c = new A(), o = c.createOscillator(), v = c.createGain();
			o.frequency.value = 880; o.connect(v); v.connect(c.destination);
			v.gain.setValueAtTime(0.001, c.currentTime);
			v.gain.exponentialRampToValueAtTime(0.25, c.currentTime + 0.02);
			v.gain.exponentialRampToValueAtTime(0.001, c.currentTime + 0.35);
			o.start(); o.stop(c.currentTime + 0.36);
		} catch (e) {}
	}

	/* ── Soát một mã ─────────────────────────────────────────────────────────────────────── */
	function soat(ma){
		ma = String(ma || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
		var kq = q('.psoat-kq');
		if (!ma){ bao(false, 'Chưa có mã vé.'); return; }
		fetch(D.rest + '/ve/soat', { method:'POST', credentials:'same-origin',
			headers:{'Content-Type':'application/json'},
			body: JSON.stringify({ ma_ve: ma, coso: CA.cs, pin: CA.pin }) })
		.then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
		.then(function(o){
			if (!o.ok || o.d.ok === false){
				bao(false, (o.d && (o.d.message || o.d.code)) || 'Vé không hợp lệ.');
				return;
			}
			bao(true, '✅ HỢP LỆ — ' + esc(o.d.goi_ten) + '<br>' + esc(o.d.ten_khach || '') + ' · ' + tien(o.d.so_tien));
			q('.psoat-ma').value = '';
			delete daThay[ma];
			lamTuoi();
		})
		.catch(function(){ bao(false, 'Lỗi kết nối máy chủ.'); });
	}
	function bao(ok, html){
		var kq = q('.psoat-kq');
		kq.className = 'psoat-kq ' + (ok ? 'ok' : 'no');
		kq.innerHTML = html;
		kq.hidden = false;
	}
	q('.psoat-go').addEventListener('click', function(){ soat(q('.psoat-ma').value); });
	q('.psoat-ma').addEventListener('keydown', function(e){
		/* Máy quét mã cầm tay gõ xong tự bấm Enter — bắt Enter là dùng được ngay, khỏi cấu hình. */
		if (e.key === 'Enter'){ e.preventDefault(); soat(this.value); }
	});

	/* ── Quét bằng camera ────────────────────────────────────────────────────────────────────
	   Dùng BarcodeDetector có sẵn trong trình duyệt, KHÔNG tải thư viện từ CDN: trang bán vé vừa
	   dính đúng chuyện CDN không tới được (xem POSH_Ve::qr_svg). Máy nào không có thì nói thẳng
	   là gõ tay — vẫn soát được vé, chỉ chậm hơn. */
	var luong = null;
	q('.psoat-cam').addEventListener('click', function(){
		var vd = q('.psoat-video');
		if (luong){ dungCam(); return; }
		if (!('BarcodeDetector' in window)){
			bao(false, 'Trình duyệt này không quét được mã. Dùng máy quét cầm tay hoặc gõ mã bằng tay.');
			return;
		}
		navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
		.then(function(st){
			luong = st; vd.srcObject = st; vd.hidden = false; vd.play();
			q('.psoat-cam').textContent = '✖ Tắt camera';
			var det = new window.BarcodeDetector({ formats: ['qr_code'] });
			var lap = setInterval(function(){
				if (!luong){ clearInterval(lap); return; }
				det.detect(vd).then(function(r){
					if (r && r.length){ clearInterval(lap); dungCam(); soat(r[0].rawValue); }
				}).catch(function(){});
			}, 400);
		})
		.catch(function(){ bao(false, 'Không mở được camera — kiểm tra quyền truy cập camera của trình duyệt.'); });
	});
	function dungCam(){
		var vd = q('.psoat-video');
		if (luong){ luong.getTracks().forEach(function(t){ t.stop(); }); luong = null; }
		vd.hidden = true; vd.srcObject = null;
		q('.psoat-cam').textContent = '📷 Quét bằng camera';
	}

	var cu = caDoc();
	if (cu){ q('.psoat-cs').value = cu.cs; vaoCa(cu); }
})();
