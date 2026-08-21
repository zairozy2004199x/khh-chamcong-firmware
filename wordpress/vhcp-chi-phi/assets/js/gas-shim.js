/**
 * gas-shim.js — dựng lại google.script.run của Apps Script trên WordPress.
 *
 * Nhờ lớp này, toàn bộ giao diện Index.html của app cũ chạy nguyên vẹn: mỗi lệnh
 *   google.script.run.withSuccessHandler(ok).withFailureHandler(err).getDon(ma)
 * được dịch thành 1 request POST tới /wp-json/vhcp/v1/call {fn:'getDon', args:[ma]}.
 *
 * Token phiên (do login trả về) lưu ở localStorage và tự gắn vào mọi request.
 * Máy chủ trả 401 -> xóa phiên và tải lại trang để hiện cổng PIN.
 */
(function () {
	'use strict';

	var CFG        = window.VHCP_CFG || {};
	var TOKEN_KEY  = 'vhcp_token';
	var USER_KEY   = 'vhcp_user';
	var reloading  = false;

	function getToken() { try { return localStorage.getItem( TOKEN_KEY ) || ''; } catch ( e ) { return ''; } }
	function setToken( t ) { try { localStorage.setItem( TOKEN_KEY, t ); } catch ( e ) {} }

	function clearSession() {
		try { localStorage.removeItem( TOKEN_KEY ); } catch ( e ) {}
		try { sessionStorage.removeItem( USER_KEY ); } catch ( e ) {}
	}

	/**
	 * Nhiều hosting (LiteSpeed/ModSecurity) hoặc plugin bảo mật chặn thẳng /wp-json/ và
	 * trả 403 kèm trang HTML — không phải lỗi của app. Khi đó chuyển sang admin-ajax.php:
	 * cùng một bộ xử lý phía máy chủ, nhưng đường này hầu như không bị chặn.
	 */
	var DUONG_KEY = 'vhcp_duong';
	var duong = 'rest';
	try {
		var d = localStorage.getItem( DUONG_KEY );
		if ( d === 'ajax' || d === 'trang' ) { duong = d; }
		else if ( localStorage.getItem( 'vhcp_ajax' ) === '1' ) { duong = 'ajax'; }
	} catch ( e ) {}
	function nhoDuong( d ) {
		duong = d;
		try { localStorage.setItem( DUONG_KEY, d ); } catch ( e ) {}
	}

	function guiRest( fn, args, tok ) {
		return fetch( CFG.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-VHCP-Token': tok },
			body: JSON.stringify( { fn: fn, args: args, token: tok } )
		} );
	}

	function guiAjax( fn, args, tok ) {
		var fd = new FormData();
		fd.append( 'action', 'vhcp_call' );
		fd.append( 'fn', fn );
		fd.append( 'args', JSON.stringify( args || [] ) );
		fd.append( 'token', tok );
		return fetch( CFG.ajax, { method: 'POST', credentials: 'same-origin', body: fd } );
	}

	/**
	 * Đường CUỐI: gửi vào chính URL của app (…/chi-phi/?vhcp_api=1).
	 *
	 * Cloudflare / tường lửa hosting chặn theo ĐƯỜNG DẪN. Khi cả /wp-json/ lẫn
	 * /wp-admin/admin-ajax.php đều trả 403 "Checking your browser" thì đường dẫn của
	 * trang app vẫn đi được — người dùng vừa mở nó xong. Gửi bằng form-data để không
	 * kích hoạt preflight, và không đặt header lạ vì tường lửa hay soi header.
	 */
	function guiTrang( fn, args, tok ) {
		// Gửi dạng x-www-form-urlencoded (không phải multipart): tường lửa hosting soi
		// multipart chặt hơn nhiều, mà đây là đường dự phòng cuối nên phải càng thường
		// càng tốt — giống y một form HTML bình thường.
		var b = 'fn=' + encodeURIComponent( fn )
			+ '&args=' + encodeURIComponent( JSON.stringify( args || [] ) )
			+ '&token=' + encodeURIComponent( tok );
		return fetch( CFG.trang, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
			body: b
		} );
	}

	var TEN_DUONG = { rest: '/wp-json/', ajax: 'admin-ajax.php', trang: 'URL app' };

	function boGui( d ) {
		if ( d === 'ajax' && CFG.ajax ) { return guiAjax; }
		if ( d === 'trang' && CFG.trang ) { return guiTrang; }
		return guiRest;
	}

	/** Đường kế tiếp để thử khi đường đang dùng bị chặn ('' = hết đường). */
	function duongKe( d ) {
		if ( d === 'rest' && CFG.ajax ) { return 'ajax'; }
		if ( ( d === 'rest' || d === 'ajax' ) && CFG.trang ) { return 'trang'; }
		return '';
	}

	function docKetQua( res ) {
		return res.text().then( function ( txt ) {
			var j = null;
			try { j = JSON.parse( txt ); } catch ( e ) {}
			return { status: res.status, json: j, raw: txt };
		} );
	}

	/** 403/404/405 hoặc trả về không phải JSON = đường /wp-json/ bị chặn, không phải lỗi app. */
	function biChan( r ) {
		if ( r.json && typeof r.json.ok !== 'undefined' ) { return false; }
		return ( r.status === 403 || r.status === 404 || r.status === 405 || r.status === 0 || ! r.json );
	}

	function call( fn, args, onOk, onErr ) {
		var tok = getToken();
		try {
			JSON.stringify( { fn: fn, args: args, token: tok } );
		} catch ( e ) {
			if ( onErr ) { onErr( { message: 'Dữ liệu gửi lên không hợp lệ' } ); }
			return;
		}

		// Bị chặn thì tự lần lượt đổi đường: /wp-json/ -> admin-ajax.php -> URL của app.
		// Ghi lại TỪNG đường thất bại thế nào: chặn ở đâu, mã bao nhiêu, trả về gì. Gộp
		// thành một câu "chặn cả 3 đường" thì không biết đường nào hỏng vì lý do gì —
		// 403 của tường lửa và 200-trả-HTML (cổng không chạy) là hai bệnh khác nhau.
		var vet = [];
		function ghiVet( d, r ) {
			var loai = ( r.status === 0 ) ? 'không tới được máy chủ'
				: ( r.status === 403 || r.status === 401 ) ? 'bị chặn'
				: ( r.status === 404 ) ? 'không có đường này'
				: ( r.status === 405 ) ? 'không cho POST'
				: ( ! r.json ) ? 'trả về không phải JSON' : 'lỗi';
			vet.push( TEN_DUONG[ d ] + ': ' + r.status + ' ' + loai );
		}
		function thu( d ) {
			return boGui( d )( fn, args, tok ).then( docKetQua ).then( function ( r ) {
				if ( ! biChan( r ) ) {
					if ( d !== duong ) { nhoDuong( d ); }
					return r;
				}
				ghiVet( d, r );
				var ke = duongKe( d );
				if ( ke ) { return thu( ke ); }
				return r;
			} );
		}

		thu( duong ).then( function ( r ) {
			if ( ! r ) { return; }
			var j = r.json;

			if ( r.status === 401 && j && j.code === 'no_session' ) {
				clearSession();
				if ( onErr ) { onErr( { message: 'Phiên đã hết — trang sẽ tải lại để đăng nhập bằng PIN' } ); }
				if ( ! reloading ) { reloading = true; setTimeout( function () { location.reload(); }, 900 ); }
				return;
			}

			if ( ! j || j.ok !== true ) {
				var msg = ( j && j.error ) ? j.error : ( 'Lỗi máy chủ (' + r.status + ')' );
				if ( ! j ) {
					// Không phải JSON: gần như chắc chắn bị hosting hoặc plugin bảo mật chặn.
					// In VẾT của từng đường + vài chữ đầu máy chủ trả về, để biết chữa ở đâu.
					var dau = String( r.raw || '' ).replace( /<[^>]*>/g, ' ' ).replace( /\s+/g, ' ' ).trim().slice( 0, 110 );
					msg = 'Không gọi được máy chủ. Đã thử: ' + vet.join( ' · ' )
						+ ( dau ? ' — máy chủ trả về: "' + dau + '"' : '' )
						+ ' · Mở thử ' + ( CFG.trang || '' ) + ' trên trình duyệt: ra {"ok":true} là cổng còn sống,'
						+ ' ra trang app là cổng chưa chạy (cần cập nhật plugin).';
				}
				if ( onErr ) { onErr( { message: msg } ); }
				return;
			}

			if ( fn === 'login' && j.data && j.data.ok && j.data.token ) { setToken( j.data.token ); }
			if ( onOk ) { onOk( j.data ); }
		} ).catch( function ( e ) {
			if ( onErr ) { onErr( { message: ( e && e.message ) ? e.message : 'Không kết nối được máy chủ' } ); }
		} );
	}

	function makeRunner( ok, err ) {
		var r = {};
		r.withSuccessHandler = function ( f ) { return makeRunner( f, err ); };
		r.withFailureHandler = function ( f ) { return makeRunner( ok, f ); };
		r.withUserObject     = function () { return r; };
		( CFG.fns || [] ).forEach( function ( name ) {
			r[ name ] = function () { call( name, Array.prototype.slice.call( arguments ), ok, err ); };
		} );
		return r;
	}

	window.google        = window.google || {};
	window.google.script = window.google.script || {};
	window.google.script.run  = makeRunner( null, null );
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
				var p = {}, q = location.search.replace( /^\?/, '' ).split( '&' );
				q.forEach( function ( kv ) {
					if ( ! kv ) { return; }
					var i = kv.indexOf( '=' );
					var k = decodeURIComponent( i < 0 ? kv : kv.slice( 0, i ) );
					p[ k ] = decodeURIComponent( i < 0 ? '' : kv.slice( i + 1 ) );
				} );
				cb( { parameter: p, parameters: p, hash: location.hash.replace( /^#/, '' ) } );
			} catch ( e ) {}
		}
	};

	// "Đăng xuất" của giao diện chỉ xóa sessionStorage — xóa luôn token & thu hồi phiên ở máy chủ.
	window.addEventListener( 'load', function () {
		if ( typeof window.logout === 'function' && ! window.logout.__vhcp ) {
			var orig = window.logout;
			window.logout = function () {
				var tok = getToken();
				if ( tok ) { call( 'vhcpLogout', [ tok ], null, null ); }
				clearSession();
				return orig.apply( this, arguments );
			};
			window.logout.__vhcp = true;
		}
	} );
})();
