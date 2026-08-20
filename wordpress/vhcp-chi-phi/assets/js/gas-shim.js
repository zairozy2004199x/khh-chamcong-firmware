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

	function call( fn, args, onOk, onErr ) {
		var tok = getToken();
		var body;
		try {
			body = JSON.stringify( { fn: fn, args: args, token: tok } );
		} catch ( e ) {
			if ( onErr ) { onErr( { message: 'Dữ liệu gửi lên không hợp lệ' } ); }
			return;
		}

		fetch( CFG.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-VHCP-Token': tok },
			body: body
		} ).then( function ( res ) {
			return res.text().then( function ( txt ) {
				var j = null;
				try { j = JSON.parse( txt ); } catch ( e ) {}
				return { status: res.status, json: j, raw: txt };
			} );
		} ).then( function ( r ) {
			var j = r.json;

			if ( r.status === 401 && j && j.code === 'no_session' ) {
				clearSession();
				if ( onErr ) { onErr( { message: 'Phiên đã hết — trang sẽ tải lại để đăng nhập bằng PIN' } ); }
				if ( ! reloading ) { reloading = true; setTimeout( function () { location.reload(); }, 900 ); }
				return;
			}

			if ( ! j || j.ok !== true ) {
				var msg = ( j && j.error ) ? j.error : ( 'Lỗi máy chủ (' + r.status + ')' );
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
