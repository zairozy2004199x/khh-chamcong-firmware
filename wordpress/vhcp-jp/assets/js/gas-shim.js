/**
 * gas-shim.js — dựng lại `google.script.run` của Apps Script trên WordPress.
 *
 * Nhờ lớp này, 11 tệp giao diện của JP chạy nguyên vẹn: mỗi lệnh
 *     google.script.run.withSuccessHandler(ok).withFailureHandler(err).jpBootstrap(token)
 * thành một POST tới /wp-json/vhjp/v1/call {fn:'jpBootstrap', args:[token]}.
 *
 * ⚠️ THẺ PHIÊN LÀ THAM SỐ ĐẦU TIÊN, không phải một trường riêng — giao diện JP gọi
 *    `srv('jpBootstrap', APP.token)`, và chính nó tự giữ thẻ trong localStorage (`JP2_token`).
 *    Shim KHÔNG đụng vào chỗ ấy: chèn thêm thẻ ở đây là hai nơi cùng quản một thứ, và tới ngày
 *    chúng lệch nhau thì không ai biết bên nào đúng.
 *
 * 🔴 KHÔNG BAO GIỜ CẤT PIN. Chỉ thẻ phiên — thứ hết hạn được và thu hồi được. Trang này chạy
 *    ngoài internet; PIN cất ở máy khách là ai mượn máy một phút cũng đọc ra, mà PIN thì dùng
 *    lại được mãi.
 *
 * ⚠️ BA ĐƯỜNG, TỰ LÙI. Nhiều hosting và Cloudflare chặn thẳng /wp-json/ rồi trả 403 kèm một
 *    trang HTML — không phải lỗi của app. Gặp thế thì lùi sang admin-ajax, rồi sang chính
 *    đường dẫn của trang, và NHỚ đường nào đi được để lần sau khỏi dò lại.
 */
(function () {
	'use strict';

	var CFG = window.VHJP_CFG || {};
	var KEY_DUONG = 'vhjp_duong';
	var DUONG = ['rest', 'ajax', 'trang'];

	function nhoDuong() {
		try { return localStorage.getItem(KEY_DUONG) || 'rest'; } catch (e) { return 'rest'; }
	}
	function catDuong(d) {
		try { localStorage.setItem(KEY_DUONG, d); } catch (e) {}
	}

	function diaChi(duong) {
		if (duong === 'ajax') { return CFG.ajax + '?action=vhjp_call'; }
		if (duong === 'trang') { return CFG.trang; }
		return CFG.rest;
	}

	/**
	 * Gửi một lệnh theo MỘT đường. Trả Promise {ma, than}.
	 *
	 * ⚠️ Hosting chặn thì trả HTML chứ không trả JSON — đọc chữ trước rồi mới thử giải mã, để
	 *    phân biệt "máy chủ báo lỗi" với "có ai đó chặn giữa đường". Gọi thẳng `res.json()` là
	 *    nhận một lỗi cú pháp JSON vô nghĩa, và không biết đường nào mà lùi.
	 */
	function gui(duong, fn, args) {
		return fetch(diaChi(duong), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ fn: fn, args: args })
		}).then(function (res) {
			return res.text().then(function (chu) {
				var than = null;
				try { than = JSON.parse(chu); } catch (e) {}
				return { ma: res.status, than: than, tho: chu };
			});
		});
	}

	/** Có phải "bị chặn giữa đường" không — để biết có nên lùi sang đường khác. */
	function biChan(r) {
		if (r.than !== null) { return false; }        // máy chủ mình trả JSON -> không phải bị chặn
		return r.ma === 403 || r.ma === 405 || r.ma === 0 || r.ma >= 500;
	}

	function goi(fn, args) {
		var batDau = nhoDuong();
		var thu = DUONG.slice(DUONG.indexOf(batDau) < 0 ? 0 : DUONG.indexOf(batDau));

		function lan(i) {
			if (i >= thu.length) {
				return Promise.reject(new Error('Không gọi được máy chủ (cả ba đường đều không đi được)'));
			}
			return gui(thu[i], fn, args).then(function (r) {
				if (biChan(r) && i + 1 < thu.length) { return lan(i + 1); }
				if (thu[i] !== batDau) { catDuong(thu[i]); }
				if (r.than === null) {
					throw new Error('Máy chủ trả về thứ không đọc được (' + r.ma + ')');
				}
				/* Giao diện JP bắt lỗi THEO CHUỖI: `SESSION_EXPIRED` bày lại ô PIN,
				   `PHAI_DOI_PIN` mở màn đổi PIN. Ném đúng mấy chữ ấy ra để `srv()` nhận được. */
				if (r.ma === 401 && r.than && r.than.error) { throw new Error(r.than.error); }
				if (r.ma >= 400) {
					throw new Error((r.than && r.than.msg) || ('Lỗi ' + r.ma));
				}
				return r.than;
			}, function (e) {
				if (i + 1 < thu.length) { return lan(i + 1); }
				throw e;
			});
		}
		return lan(0);
	}

	/**
	 * Dựng lại đúng hình dạng `google.script.run`: mỗi lượt gắn handler trả về MỘT bản mới, và
	 * tên hàm nào cũng gọi được (bên Apps Script không có danh sách tên ở phía client).
	 * Danh sách CHO PHÉP nằm ở máy chủ — đúng chỗ của nó.
	 */
	function tao(ok, loi) {
		var than = {
			withSuccessHandler: function (f) { return tao(f, loi); },
			withFailureHandler: function (f) { return tao(ok, f); },
			withUserObject: function () { return than; }
		};
		return new Proxy(than, {
			get: function (t, k) {
				if (k in t) { return t[k]; }
				if (typeof k !== 'string') { return undefined; }
				return function () {
					var args = [].slice.call(arguments);
					goi(k, args).then(
						function (d) { if (ok) { ok(d); } },
						function (e) { if (loi) { loi(e); } else { throw e; } }
					);
				};
			}
		});
	}

	window.google = window.google || {};
	window.google.script = window.google.script || {};
	Object.defineProperty(window.google.script, 'run', { get: function () { return tao(null, null); } });

	/* Bản Apps Script còn có `google.script.host` (đóng hộp thoại). Trên web thường không có
	   hộp thoại nào, nhưng giao diện vẫn gọi — thiếu là nổ giữa chừng. */
	window.google.script.host = window.google.script.host || {
		close: function () {}, setHeight: function () {}, setWidth: function () {}
	};
})();
