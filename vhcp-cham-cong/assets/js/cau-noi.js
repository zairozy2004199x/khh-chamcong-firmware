/**
 * google.script.run  →  WordPress  →  app chấm công trên Apps Script
 * ============================================================================
 *
 * File này để GIAO DIỆN GỐC CHẠY NGUYÊN VẸN, không sửa một dòng nào của Index.html.
 * App gốc gọi `google.script.run.withSuccessHandler(f).withFailureHandler(g).tenHam(args…)`;
 * ở đây dựng lại đúng cái đối tượng đó, nhưng mỗi lệnh gọi thành một request tới WordPress.
 *
 * BA VIỆC KHÔNG HIỂN NHIÊN:
 *
 * 1. XẾP HÀNG CHỜ ĐĂNG NHẬP. Dòng cuối của app gốc là `load(false)` — nó gọi getData NGAY khi
 *    trang tải, trước khi ai kịp nhập PIN. Nếu cứ gửi đi thì nhận 401 và app hiện "Không tải
 *    được dữ liệu" ngay trên màn hình đăng nhập. Nên mọi lệnh khi chưa có phiên được GIỮ LẠI,
 *    đăng nhập xong mới thả ra. App gốc không biết gì, chỉ thấy hơi lâu.
 *
 * 2. PHIÊN HẾT GIỮA LÚC ĐANG DÙNG. Lệnh nào trả 401 thì hiện lại cổng PIN và GIỮ chính lệnh đó,
 *    đăng nhập xong chạy tiếp. Bỏ luôn lệnh đó là người dùng mất việc đang làm mà không hiểu vì sao.
 *
 * 3. LỆNH CHẠY LÂU. Bóc tách 1 file PDF bằng AI mất 1–3 phút, một lô 6 file có thể lâu hơn.
 *    Không đặt timeout ở phía trình duyệt; để máy chủ quyết.
 */
(function () {
	'use strict';

	var CFG       = window.VHCC_CFG || {};
	var TOKEN_KEY = 'vhcc_token';          // khoá riêng, KHÔNG dùng chung với app Vận hành chi phí:
	                                      // hai hệ thống riêng thì token cũng phải riêng
	var cho       = [];                   // lệnh đang chờ đăng nhập
	var dangMoGate = false;

	function layToken() { try { return localStorage.getItem( TOKEN_KEY ) || ''; } catch ( e ) { return ''; } }
	function datToken( t ) { try { localStorage.setItem( TOKEN_KEY, t ); } catch ( e ) {} }
	function xoaToken() { try { localStorage.removeItem( TOKEN_KEY ); } catch ( e ) {} }

	// ---------------------------------------------------------------- gọi máy chủ

	function guiJSON( url, payload ) {
		return fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( payload )
		} ).then( function ( r ) {
			return r.text().then( function ( t ) {
				var j = null;
				try { j = JSON.parse( t ); } catch ( e ) {}
				return { status: r.status, json: j, text: t };
			} );
		} );
	}

	/**
	 * Gửi một lệnh. Hosting nào chặn /wp-json/ thì tự chuyển sang đường dự phòng —
	 * Cloudflare chặn theo ĐƯỜNG DẪN, mà đường dẫn của chính trang này thì vừa mở được.
	 */
	function guiCoDuPhong( payload ) {
		return guiJSON( CFG.endpoint, payload ).then( function ( r ) {
			var chan = ( r.status === 403 || r.status === 404 || r.status === 405 || ! r.json );
			if ( ! chan ) { return r; }
			return guiJSON( CFG.trang, payload );
		} ).catch( function () {
			return guiJSON( CFG.trang, payload );
		} );
	}

	function chay( viec ) {
		var payload = { fn: viec.fn, args: viec.args, token: layToken() };
		guiCoDuPhong( payload ).then( function ( r ) {
			if ( r.json && r.json.code === 'no_session' ) {
				xoaToken();
				cho.push( viec );          // giữ lại việc đang làm, đăng nhập xong chạy tiếp
				moGate();
				return;
			}
			if ( ! r.json ) {
				viec.that( new Error( 'Máy chủ trả về không phải JSON (mã ' + r.status + ')' ) );
				return;
			}
			if ( ! r.json.ok ) {
				viec.that( new Error( r.json.error || 'Lỗi không rõ' ) );
				return;
			}
			viec.duoc( r.json.data );
		} ).catch( function ( e ) {
			viec.that( new Error( ( e && e.message ) ? e.message : String( e ) ) );
		} );
	}

	function xepHangHoacChay( viec ) {
		if ( ! layToken() ) { cho.push( viec ); moGate(); return; }
		chay( viec );
	}

	function thaHangCho() {
		var ds = cho.slice();
		cho = [];
		ds.forEach( chay );
	}

	// ---------------------------------------------------------------- cổng PIN
	//
	// Dựng bằng JS chứ không chèn HTML vào Index.html: chèn thêm thẻ vào file giao diện gốc là
	// mỗi lần app gốc đổi cấu trúc lại phải sửa chỗ chèn. Ở đây không đụng một chữ nào của nó.

	function moGate() {
		if ( dangMoGate ) { return; }
		dangMoGate = true;

		/* Chú thích dưới ô PIN — phải khớp NGUỒN đang dùng, không ghi cứng.
		   Bản trước ghi cứng "Dùng chung mã PIN với app Vận hành chi phí"; chuyển sang danh sách
		   riêng rồi thì câu đó chỉ người ta đi tìm PIN ở chỗ không liên quan. */
		function chuThichNguon() {
			var c = window.VHCC_CFG || {};
			var vt = c.vaiTro || 'Kế toán · Quản lý · Admin';
			if ( c.soVao === 0 ) {
				return '<b style="color:#dc2626">Chưa có tài khoản nào đăng nhập được.</b><br>'
					+ 'Quản trị vào wp-admin → Chấm Công → Cài đặt để khai.';
			}
			var nguon = ( c.nguon === 'rieng' )
				? 'PIN do quản trị khai trong plugin Chấm Công.'
				: 'Dùng chung mã PIN với app Vận hành chi phí.';
			return nguon + '<br>Chỉ ' + vt + ' vào được.';
		}

		var wrap = document.createElement( 'div' );
		wrap.id = 'vhdGate';
		wrap.style.cssText = 'position:fixed;inset:0;z-index:99999;background:#0f172a;'
			+ 'display:flex;align-items:center;justify-content:center;'
			+ 'font-family:"Segoe UI",Arial,sans-serif';
		wrap.innerHTML =
			'<div style="background:#fff;border-radius:14px;padding:26px 28px;width:min(370px,92vw);'
			+ 'text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.45)">'
			+ '<div style="font-size:32px">📄</div>'
			+ '<h2 style="font-size:17px;color:#1d4ed8;margin:6px 0 3px;font-weight:700">Chấm Công</h2>'
			+ '<div style="font-size:12px;color:#64748b;margin-bottom:15px">Nhập mã PIN (4–8 số) để vào</div>'
			+ '<input id="vhdPin" type="password" inputmode="numeric" maxlength="8" autocomplete="off" '
			+ 'placeholder="••••" style="width:170px;text-align:center;font-size:25px;letter-spacing:9px;'
			+ 'padding:9px;border:1px solid #cbd5e1;border-radius:8px">'
			+ '<div id="vhdErr" style="color:#dc2626;font-size:12px;min-height:32px;margin:9px 0;line-height:1.4"></div>'
			+ '<button id="vhdVao" style="width:170px;padding:9px;border:none;border-radius:8px;'
			+ 'background:#1d4ed8;color:#fff;font-size:13px;font-weight:600;cursor:pointer">Đăng nhập</button>'
			+ '<div style="font-size:11px;color:#94a3b8;margin-top:13px">' + chuThichNguon() + '</div></div>';
		document.body.appendChild( wrap );

		var oPin = document.getElementById( 'vhdPin' );
		var oErr = document.getElementById( 'vhdErr' );
		var oVao = document.getElementById( 'vhdVao' );

		function vao() {
			var pin = ( oPin.value || '' ).trim();
			if ( ! /^\d{4,8}$/.test( pin ) ) { oErr.textContent = 'PIN phải 4–8 chữ số'; return; }
			oErr.textContent = 'Đang kiểm…';
			oVao.disabled = true;
			guiCoDuPhong( { fn: 'login', args: [ pin ], token: '' } ).then( function ( r ) {
				oVao.disabled = false;
				var d = ( r.json && r.json.ok ) ? r.json.data : null;
				if ( ! d ) {
					oErr.textContent = ( r.json && r.json.error ) ? r.json.error : 'Không gọi được máy chủ';
					return;
				}
				if ( ! d.ok ) { oErr.textContent = d.error || 'PIN không đúng'; oPin.value = ''; oPin.focus(); return; }
				datToken( d.token );
				try { sessionStorage.setItem( 'vhcc_user', JSON.stringify( { name: d.name, role: d.role, coso: d.coso } ) ); } catch ( e ) {}
				document.body.removeChild( wrap );
				dangMoGate = false;
				themThanhNguoiDung( d );
				thaHangCho();
			} ).catch( function ( e ) {
				oVao.disabled = false;
				oErr.textContent = ( e && e.message ) ? e.message : String( e );
			} );
		}

		oVao.onclick = vao;
		oPin.onkeydown = function ( e ) { if ( e.key === 'Enter' ) { vao(); } };
		setTimeout( function () { oPin.focus(); }, 80 );
	}

	/**
	 * Thanh nhỏ góc phải: ai đang đăng nhập + nút Thoát.
	 * App gốc không có khái niệm người dùng nên không có chỗ nào hiện việc này; thêm bằng JS.
	 */
	function themThanhNguoiDung( d ) {
		if ( document.getElementById( 'vhdWho' ) ) { return; }
		var b = document.createElement( 'div' );
		b.id = 'vhdWho';
		b.style.cssText = 'position:fixed;right:10px;bottom:10px;z-index:9998;background:#0f172a;'
			+ 'color:#fff;border-radius:999px;padding:6px 12px;font:600 11.5px "Segoe UI",Arial,sans-serif;'
			+ 'box-shadow:0 6px 18px rgba(0,0,0,.25);display:flex;gap:9px;align-items:center';
		b.innerHTML = '<span>' + ( d.name || '' ) + ' · ' + ( d.role || '' ) + '</span>'
			+ '<span id="vhdThoat" style="cursor:pointer;color:#93c5fd">Thoát</span>';
		document.body.appendChild( b );
		document.getElementById( 'vhdThoat' ).onclick = function () {
			var tok = layToken();
			if ( tok ) { guiCoDuPhong( { fn: 'vhccLogout', args: [], token: tok } ); }
			xoaToken();
			try { sessionStorage.removeItem( 'vhcc_user' ); } catch ( e ) {}
			location.reload();
		};
	}

	// ---------------------------------------------------------------- google.script.run

	function taoRunner() {
		var ok = null, fail = null;
		var runner = {
			withSuccessHandler: function ( f ) { ok = f; return runner; },
			withFailureHandler: function ( f ) { fail = f; return runner; },
			withUserObject: function () { return runner; }
		};
		( CFG.fns || [] ).forEach( function ( ten ) {
			runner[ ten ] = function () {
				xepHangHoacChay( {
					fn: ten,
					args: Array.prototype.slice.call( arguments ),
					duoc: function ( d ) { if ( ok ) { ok( d ); } },
					that: function ( e ) {
						if ( fail ) { fail( e ); }
						else { console.error( '[hợp đồng] ' + ten + ': ' + e.message ); }
					}
				} );
			};
		} );
		return runner;
	}

	window.google = window.google || {};
	window.google.script = window.google.script || {};
	// Mỗi lần đọc `google.script.run` phải ra MỘT runner mới: app gốc gắn handler rồi mới gọi hàm,
	// dùng chung một đối tượng là hai lệnh chạy song song sẽ tráo handler của nhau.
	Object.defineProperty( window.google.script, 'run', { get: taoRunner, configurable: true } );

	window.google.script.host = {
		close: function () {},
		setHeight: function () {},
		setWidth: function () {},
		origin: location.origin,
		editor: { focus: function () {} }
	};
	window.google.script.url = {
		getLocation: function ( cb ) {
			try {
				var p = {};
				( location.search.replace( /^\?/, '' ).split( '&' ) ).forEach( function ( kv ) {
					if ( ! kv ) { return; }
					var i = kv.indexOf( '=' );
					var k = decodeURIComponent( i < 0 ? kv : kv.slice( 0, i ) );
					p[ k ] = decodeURIComponent( i < 0 ? '' : kv.slice( i + 1 ) );
				} );
				cb( { parameter: p, parameters: p, hash: location.hash.replace( /^#/, '' ) } );
			} catch ( e ) {}
		}
	};

	// Có phiên sẵn thì hiện luôn thanh người dùng; chưa có thì cổng PIN sẽ tự mở ở lệnh đầu tiên.
	window.addEventListener( 'DOMContentLoaded', function () {
		if ( ! layToken() ) { moGate(); return; }
		var u = null;
		try { u = JSON.parse( sessionStorage.getItem( 'vhcc_user' ) || 'null' ); } catch ( e ) {}
		if ( u && u.name ) { themThanhNguoiDung( u ); }
	} );
})();
