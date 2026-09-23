<?php
/**
 * CÀI ĐƯỢC LÊN MÀN HÌNH CHÍNH — manifest, worker và mấy thẻ meta cho trang trạm.
 *
 * =============================================================================================
 * 🔴 CÁI NÀY GIẢI QUYẾT ĐÚNG MỘT VIỆC, VÀ KHÔNG ĐỤNG NGHIỆP VỤ
 * =============================================================================================
 * Trạm đã chạy tốt trên trình duyệt. Thiếu đúng chỗ này: nhân viên bấm "Thêm vào màn hình
 * chính" trên iPhone thì cái nhận được là một lối tắt Safari — còn thanh địa chỉ, còn nút
 * back, và mở ra vẫn là một tab trong đống tab. Mỗi sáng phải lục tab hoặc gõ lại địa chỉ.
 *
 * Có manifest thì nó mở toàn màn hình, có biểu tượng riêng, nằm cạnh Zalo với Facebook như một
 * ứng dụng thật. Nghiệp vụ không đổi một dòng nào — vẫn `VHCC_Online::cham_cong()`.
 *
 * =============================================================================================
 * 🔴 WORKER CỐ Ý KHÔNG NHỚ ĐỆM TRANG CHẤM CÔNG. BỎ CHỐT NÀY LÀ SAI GIỜ HÀNG LOẠT
 * =============================================================================================
 * Mẫu PWA nào trên mạng cũng bảo "cache-first cho app shell, chạy được offline". Áp vào đây là
 * hỏng, vì ba lý do đã nằm sẵn trong ràng buộc của trạm:
 *
 *   1. ẢNH ĐÓNG DẤU GIỜ MÁY CHỦ (ràng buộc số 1 của `tram.php`). Trang lấy mốc giờ từ
 *      `viec=gio` rồi tự trôi. Trả một trang cũ từ đệm là trả kèm mốc giờ cũ — ảnh in ra giờ
 *      của lần mở trước, mà tấm ảnh ấy chính là thứ dùng để đối chiếu khi tranh cãi.
 *   2. THẺ PHIÊN NẰM TRONG TRANG. `phien_tu_cookie()` dựng trạng thái lúc render. Đệm trang
 *      HTML là đệm luôn trạng thái đăng nhập của người mở trước — trên máy dùng chung ở cơ sở
 *      thì đó là người A chấm công vào thẻ người B.
 *   3. CHẤM CÔNG OFFLINE KHÔNG PHẢI THỨ TA MUỐN. Ghi được lúc mất mạng nghĩa là có một hàng
 *      đợi ở điện thoại, và giờ ghi nhận là giờ máy nào thì không ai trả lời được. Máy chấm
 *      công có hàng đợi vì nó đứng một chỗ và đồng bộ NTP; điện thoại thì không.
 *
 * Nên worker ở đây chỉ làm MỘT việc: nhớ đệm mấy tấm biểu tượng. Mọi thứ khác đi thẳng ra
 * mạng. Mất mạng thì hiện một trang báo "cần mạng để chấm công" thay cho khủng long của Chrome
 * — và đó là toàn bộ phần offline.
 *
 * ⚠️ AI ĐÓ MAI MỐT MUỐN "CHO NHANH HƠN" BẰNG CÁCH ĐỆM THÊM: đọc lại ba điều trên. Trang này
 *    nặng 60 KB và không nạp theme; nó không chậm vì thiếu đệm.
 *
 * =============================================================================================
 * 🔴 PHẠM VI WORKER PHẢI NẰM CÙNG THƯ MỤC VỚI TRANG
 * =============================================================================================
 * Trình duyệt chỉ cho worker quản những địa chỉ NẰM DƯỚI đường dẫn phát ra nó. Trang trạm ở
 * `/cham-cong-online/`, nên worker phải phát từ `/cham-cong-online/sw.js` — phát từ gốc site
 * thì nó quản cả website (đụng plugin khác), phát sai chỗ thì nó không quản được trang trạm.
 *
 * Vì thế ba đường dưới đây khai riêng, mỗi đường một luật, chứ không nhét vào `?viec=`: worker
 * bắt buộc phải là một tệp `.js` có đường dẫn thật thì trình duyệt mới nhận.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_PWA {

	/**
	 * Đổi số này mỗi khi sửa danh sách tệp đệm. Worker cũ thấy tên kho khác là dọn kho cũ đi.
	 * Không gắn vào VHCC_VERSION: bản plugin lên thì đây cũng lên theo, mà 99% lần lên bản
	 * không đụng gì tới mấy tấm biểu tượng — dọn kho vô ích, người dùng tải lại icon mỗi lần.
	 */
	const KHO = 'vhcc-tram-v4';

	/**
	 * Nền `--nen` của `tram.php`. Lệch màu này thì iPhone nháy một khung khác màu lúc mở app,
	 * và thanh trạng thái sai tông suốt phiên dùng.
	 *
	 * ⚠️ ĐỔI CÙNG LÚC VỚI `--nen` TRONG `templates/tram.php`. Hai chỗ này không có gì buộc
	 *    nhau tự động — lệch thì không lỗi, chỉ là một khung sáng nháy lên rồi tối lại (hoặc
	 *    ngược lại) ở mỗi lần mở, đủ khó chịu mà không đủ rõ để ai đó đi tìm nguyên nhân.
	 */
	const MAU_NEN = '#f9f8f6';

	public static function init() {
		$slug = VHCC_Tram::slug();

		add_rewrite_rule( '^' . $slug . '/manifest\.webmanifest$', 'index.php?vhcc_pwa=manifest', 'top' );
		add_rewrite_rule( '^' . $slug . '/sw\.js$',                'index.php?vhcc_pwa=sw',       'top' );
		add_rewrite_rule( '^' . $slug . '/icon/([a-z0-9\-]+)\.png$', 'index.php?vhcc_pwa=icon&vhcc_icon=$matches[1]', 'top' );

		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		/* Ưu tiên 9 — TRƯỚC `VHCC_Tram::maybe_render` (mặc định 10). Trạm bắt theo
		   `vhcc_tram=1`, ba đường này không mang cờ ấy nên thực ra không giẫm chân nhau; để 9
		   là phòng khi sau này ai đó nới luật đường của trạm cho khớp cả thư mục con. */
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ), 9 );
	}

	public static function query_vars( $v ) {
		$v[] = 'vhcc_pwa';
		$v[] = 'vhcc_icon';
		return $v;
	}

	// ==================================================================== đường dẫn

	public static function url( $duoi ) {
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/' . VHCC_Tram::slug() . '/' . ltrim( $duoi, '/' ) );
		}
		/* Đường dẫn thô: không có luật rewrite nào chạy, nên đi bằng tham số. Worker thì
		   KHÔNG có đường thay thế — xem `co_the_cai()`. */
		$map = array( 'manifest.webmanifest' => 'manifest', 'sw.js' => 'sw' );
		if ( isset( $map[ $duoi ] ) ) {
			return add_query_arg( 'vhcc_pwa', $map[ $duoi ], home_url( '/' ) );
		}
		if ( 0 === strpos( $duoi, 'icon/' ) ) {
			return add_query_arg(
				array( 'vhcc_pwa' => 'icon', 'vhcc_icon' => basename( $duoi, '.png' ) ),
				home_url( '/' )
			);
		}
		return home_url( '/' );
	}

	/**
	 * Cài được hay không. Hai điều kiện cứng của trình duyệt, không nới được:
	 *   · HTTPS — worker không đăng ký trên http (trừ localhost).
	 *   · Đường dẫn đẹp — worker phải là tệp `.js` ở đúng thư mục; `?vhcc_pwa=sw` thì phạm vi
	 *     của nó là gốc site, trình duyệt từ chối quản `/cham-cong-online/`.
	 * Thiếu một trong hai thì vẫn phát manifest (biểu tượng + tên vẫn đẹp khi thêm vào màn
	 * hình chính trên iPhone), chỉ là không có worker.
	 */
	public static function co_the_cai() {
		return is_ssl() && (bool) get_option( 'permalink_structure' );
	}

	// ==================================================================== phát nội dung

	public static function phuc_vu() {
		$viec = get_query_var( 'vhcc_pwa' );
		if ( ! $viec && isset( $_GET['vhcc_pwa'] ) ) {
			$viec = sanitize_key( wp_unslash( $_GET['vhcc_pwa'] ) );
		}
		if ( ! $viec ) { return; }

		if ( 'manifest' === $viec ) { self::ra_manifest(); }
		if ( 'sw'       === $viec ) { self::ra_worker(); }
		if ( 'icon'     === $viec ) { self::ra_icon(); }
	}

	private static function ra_manifest() {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );

		$m = array(
			'name'              => 'Chấm công K&H',
			'short_name'        => 'Chấm công',
			'description'       => 'Chấm công bằng điện thoại — K&H',
			'lang'              => 'vi',
			'dir'               => 'ltr',
			/**
			 * ══════════════════════════════════════════════════════════════════════════════
			 * 🔴 SCOPE LÀ CẢ SITE, KHÔNG PHẢI RIÊNG TRANG TRẠM. ĐỔI 17/09/2026
			 * ══════════════════════════════════════════════════════════════════════════════
			 * iPhone so địa chỉ đang mở với `scope`: nằm DƯỚI nó thì ở lại trong app, nằm
			 * ngoài thì bật Safari ra. Khi trạm chỉ có một trang thì `/cham-cong-online/` là
			 * đúng. Nhưng từ lúc có tab Ứng dụng, bấm ô Chi phí (`/chi-phi`) hay ô Báo cáo
			 * POSH (`/ghe`) là rơi ra ngoài scope — Safari bật lên kèm thanh địa chỉ, và cái
			 * cảm giác "một ứng dụng" mất sạch ngay ở lần bấm đầu tiên.
			 *
			 * Nên scope mở ra cả site. `start_url` vẫn là trang trạm, nên mở app từ biểu
			 * tượng vẫn vào thẳng màn chấm công như cũ.
			 *
			 * ⚠️ CÁI GIÁ, VÀ NÓ CÓ THẬT: ở chế độ standalone, iOS KHÔNG có nút back. Người
			 *    dùng sang trang Chi phí rồi muốn về trạm thì phải VUỐT TỪ MÉP TRÁI — làm
			 *    được, nhưng không ai tự đoán ra. Đây là lý do mấy trang đích nên có đường
			 *    về của riêng nó; trang nào chưa có thì vuốt mép là đường duy nhất.
			 *
			 * ⚠️ Scope rộng cũng nghĩa là `/wp-admin` mở trong app luôn nếu ai đó bấm trúng
			 *    một link tới đó. Không hỏng gì, nhưng nhìn lạ. Chấp nhận, vì siết lại thì
			 *    phải liệt kê từng trang — mà thêm một hệ nữa là lại quên sửa chỗ này.
			 */
			/**
			 * ⚠️ `id` PHẢI BẰNG ĐÚNG ĐƯỜNG DẪN CỦA `start_url` HIỆN TẠI.
			 *
			 * Không khai `id` thì Chrome tự suy nó từ `start_url` — nghĩa là ngày nào đổi slug
			 * trạm, Chrome coi đó là một ỨNG DỤNG KHÁC: máy nhân viên mọc thêm một biểu tượng
			 * thứ hai, biểu tượng cũ vẫn đó và vẫn mở trang cũ. Khai `id` thì đổi slug chỉ là
			 * đổi `start_url`, app vẫn là app ấy.
			 *
			 * 🔴 ĐẶT ĐÚNG GIÁ TRỊ CHROME ĐANG TỰ SUY, KHÔNG ĐẶT GIÁ TRỊ "ĐẸP HƠN". Máy nào đã
			 *    cài rồi thì `id` hiện tại của nó CHÍNH LÀ đường dẫn start_url; khai một chuỗi
			 *    khác đi là Chrome coi bản đang cài và bản mới là hai app, và người ta lại có
			 *    hai biểu tượng — đúng cái mình vừa tránh.
			 */
			'id'                => wp_parse_url( trailingslashit( VHCC_Tram::url() ), PHP_URL_PATH ),
			'scope'             => home_url( '/' ),
			'start_url'         => trailingslashit( VHCC_Tram::url() ),
			'display'           => 'standalone',
			/* ⚠️ KHÔNG KHOÁ DỌC NỮA. Khoá dọc đúng khi app chỉ có màn chấm công — một đồng hồ
			   và một cái nút, xoay ngang chẳng để làm gì. Nhưng từ lúc scope mở ra cả site,
			   app chứa luôn trang Chi phí với bảng dòng chi 13 cột: ở đó xoay ngang là cách
			   nhanh nhất để đọc hết bảng mà không phải trượt. Khoá dọc là lấy mất nó. */
			'background_color'  => self::MAU_NEN,
			'theme_color'       => self::MAU_NEN,
			'categories'        => array( 'business', 'productivity' ),
			'icons'             => array(
				array(
					'src'     => self::url( 'icon/icon-192.png' ),
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
				array(
					'src'     => self::url( 'icon/icon-512.png' ),
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
				/* `maskable` riêng, viền rộng hơn. Android cắt biểu tượng theo hình của hãng
				   máy (tròn, vuông bo, giọt nước); đưa bản `any` cho nó cắt là cụt mất hai
				   bên lông vũ. */
				array(
					'src'     => self::url( 'icon/icon-192-maskable.png' ),
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'purpose' => 'maskable',
				),
				array(
					'src'     => self::url( 'icon/icon-512-maskable.png' ),
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'maskable',
				),
			),
		);

		echo wp_json_encode( $m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	private static function ra_icon() {
		$ten = get_query_var( 'vhcc_icon' );
		if ( ! $ten && isset( $_GET['vhcc_icon'] ) ) {
			$ten = sanitize_file_name( wp_unslash( $_GET['vhcc_icon'] ) );
		}

		/* Danh sách trắng, không ghép đường dẫn từ tham số. Thư mục này nằm trong plugin;
		   một dấu `../` lọt qua là đọc được tệp bất kỳ trên host. */
		$cho_phep = array(
			'icon-192', 'icon-512', 'icon-192-maskable', 'icon-512-maskable',
			'apple-touch-icon', 'favicon-32',
		);
		if ( ! in_array( (string) $ten, $cho_phep, true ) ) {
			status_header( 404 );
			exit;
		}

		$tep = VHCC_DIR . 'assets/pwa/' . $ten . '.png';
		if ( ! file_exists( $tep ) ) { status_header( 404 ); exit; }

		header( 'Content-Type: image/png' );
		header( 'Content-Length: ' . filesize( $tep ) );
		/* Một năm: tên tệp cố định nhưng nội dung gần như không đổi, mà biểu tượng thì tải
		   lại tốn băng thông 3G của đúng những người đang đứng chờ chấm công. Đổi logo thì
		   đổi tên tệp. */
		header( 'Cache-Control: public, max-age=31536000, immutable' );
		readfile( $tep );
		exit;
	}

	private static function ra_worker() {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		/* KHÔNG đệm chính tệp worker. Trình duyệt hỏi lại tệp này để biết có bản mới; để nó
		   đệm thì sửa worker xong người dùng vẫn chạy bản cũ, có khi hàng tuần. */
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		$kho  = self::KHO;
		$icon = self::url( 'icon/icon-192.png' );

		/* Viết thẳng JS ở đây thay vì để một tệp .js tĩnh trong assets/: worker cần biết địa
		   chỉ tuyệt đối của biểu tượng và tên kho, mà hai thứ đó phụ thuộc slug người dùng
		   đặt trong cài đặt. Một tệp tĩnh thì phải chèn biến qua query string, và query
		   string trên đường của worker lại làm rối phạm vi. */
		?>
const KHO = '<?php echo esc_js( $kho ); ?>';
const ICON = '<?php echo esc_js( $icon ); ?>';
const TRANG = '<?php echo esc_js( trailingslashit( VHCC_Tram::url() ) ); ?>';
const DEM_TRUOC = [ICON];

self.addEventListener('install', function (e) {
	// Nhận việc ngay, không chờ tab cũ đóng. Worker ở đây không đệm trang nên không có rủi ro
	// "nửa cũ nửa mới" mà skipWaiting thường gây ra.
	self.skipWaiting();
	e.waitUntil(
		caches.open(KHO).then(function (c) { return c.addAll(DEM_TRUOC); }).catch(function () {})
	);
});

self.addEventListener('activate', function (e) {
	e.waitUntil(
		caches.keys().then(function (ds) {
			return Promise.all(ds.map(function (d) {
				if (d !== KHO) { return caches.delete(d); }
			}));
		}).then(function () { return self.clients.claim(); })
	);
});

self.addEventListener('fetch', function (e) {
	var req = e.request;
	if (req.method !== 'GET') { return; }

	var url;
	try { url = new URL(req.url); } catch (err) { return; }
	if (url.origin !== self.location.origin) { return; }

	// Biểu tượng: lấy từ kho trước cho nhanh, thiếu thì ra mạng rồi cất lại.
	if (url.pathname.indexOf('/icon/') !== -1 && url.pathname.slice(-4) === '.png') {
		e.respondWith(
			caches.match(req).then(function (r) {
				return r || fetch(req).then(function (res) {
					var ban = res.clone();
					caches.open(KHO).then(function (c) { c.put(req, ban); });
					return res;
				});
			})
		);
		return;
	}

	// MỌI THỨ CÒN LẠI ĐI THẲNG RA MẠNG — xem khối chú thích đầu class-vhcc-pwa.php.
	// Trang chấm công mang mốc giờ máy chủ và trạng thái phiên; trả bản đệm là ghi sai giờ
	// hoặc chấm nhầm người. Mất mạng thì báo thẳng, không đoán.
	if (req.mode === 'navigate') {
		e.respondWith(
			fetch(req).catch(function () {
				return new Response(
					'<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8">' +
					'<meta name="viewport" content="width=device-width,initial-scale=1">' +
					'<title>Mất mạng</title><style>' +
					'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;' +
					'background:<?php echo esc_js( self::MAU_NEN ); ?>;color:#171417;' +
					"font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;" +
					'text-align:center;padding:24px}' +
					'h1{font-size:19px;margin:0 0 10px;color:#0c1754}' +
					'p{margin:0 0 18px;color:#8c8781;max-width:22em}' +
					'button{padding:12px 22px;border:0;border-radius:12px;background:#2545ff;' +
					'color:#fff;font:inherit;font-weight:700}' +
					'</style></head><body><div>' +
					'<h1>Chưa có mạng</h1>' +
					'<p>Chấm công cần mạng để lấy giờ máy chủ — giờ trên điện thoại không dùng để ghi công được.</p>' +
					'<button onclick="location.reload()">Thử lại</button>' +
					'</div></body></html>',
					{ status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
				);
			})
		);
	}
});

// ═════════════════════════════════════════════════════════════════════════════════════════
// TIẾNG GÕ CỬA. Máy chủ đẩy một lượt RỖNG; nội dung lấy ở đây.
//
// ⚠️ MỌI NHÁNH ĐỀU PHẢI GỌI showNotification(). Nhận push mà không hiện gì thì iOS và Chrome
//    tự hiện "trang này đang chạy nền" vài lần, rồi THU HỒI QUYỀN THÔNG BÁO của cả trang.
//    Nên không có `return` nào ở giữa, và lấy tin hỏng cũng vẫn hiện một câu chung.
// ═════════════════════════════════════════════════════════════════════════════════════════
self.addEventListener('push', function (e) {
	e.waitUntil(
		layTin().then(function (t) {
			var tieu = (t && t.tieu_de) ? t.tieu_de : 'Chấm công K&H';
			var than = (t && t.than) ? t.than : 'Mở app để xem.';
			return self.registration.showNotification(tieu, {
				body: than,
				icon: ICON,
				badge: ICON,
				// Cùng tag = tin mới ĐÈ tin cũ thay vì xếp chồng. Ba lượt nhắc chấm ra nằm
				// chồng nhau trong khay thông báo chỉ làm người ta bực rồi tắt hết.
				tag: 'vhcc',
				renotify: true,
				data: { duong_dan: (t && t.duong_dan) ? t.duong_dan : TRANG }
			});
		})
	);
});

function layTin() {
	return chiaHop().then(function (khoa) {
		if (!khoa) { return null; }
		return fetch(TRANG + '?viec=push_hop', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ khoa: khoa })
		}).then(function (r) { return r.json(); })
		  .then(function (j) { return (j && j.ok && j.tin) ? j.tin : null; });
	}).catch(function () { return null; });
}

// Chìa hộp tin nằm ở IndexedDB — worker không đọc được localStorage nơi trang cất thẻ phiên.
function chiaHop() {
	return new Promise(function (ok) {
		var r = indexedDB.open('vhcc', 1);
		r.onupgradeneeded = function () { r.result.createObjectStore('kv'); };
		r.onerror = function () { ok(null); };
		r.onsuccess = function () {
			try {
				var g = r.result.transaction('kv', 'readonly').objectStore('kv').get('khoa_hop');
				g.onsuccess = function () { ok(g.result || null); };
				g.onerror = function () { ok(null); };
			} catch (err) { ok(null); }
		};
	});
}

self.addEventListener('notificationclick', function (e) {
	e.notification.close();
	var dich = (e.notification.data && e.notification.data.duong_dan) || TRANG;
	e.waitUntil(
		clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (ds) {
			// Đang mở sẵn thì đưa cửa sổ ấy lên, đừng mở thêm cái thứ hai.
			for (var i = 0; i < ds.length; i++) {
				if (ds[i].url.indexOf(TRANG) === 0 && 'focus' in ds[i]) { return ds[i].focus(); }
			}
			return clients.openWindow(dich);
		})
	);
});
		<?php
		exit;
	}

	// ==================================================================== thẻ trong <head>

	/**
	 * In vào `<head>` của `tram.php`. Gọi từ đúng một chỗ trong tệp ấy.
	 *
	 * ⚠️ `apple-mobile-web-app-*` KHÔNG THỪA dù đã có manifest. Safari trên iOS tới nay vẫn
	 *    đọc mấy thẻ này chứ không đọc `display`/`icons` của manifest cho màn hình chính. Bỏ
	 *    chúng đi thì trên iPhone vẫn hiện thanh địa chỉ và biểu tượng là ảnh chụp màn hình
	 *    trang — mà quá nửa nhân viên dùng iPhone.
	 */
	public static function the_head() {
		$mau = esc_attr( self::MAU_NEN );
		?>
<link rel="manifest" href="<?php echo esc_url( self::url( 'manifest.webmanifest' ) ); ?>">
<meta name="theme-color" content="<?php echo $mau; ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Chấm công">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="<?php echo esc_url( self::url( 'icon/apple-touch-icon.png' ) ); ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( self::url( 'icon/favicon-32.png' ) ); ?>">
<?php
		if ( ! self::co_the_cai() ) { return; }
		?>
<script>
/* Đăng ký worker sau `load` — trước đó thì nó tranh băng thông với chính lượt tải trang, và
   trang trạm mở trên 3G ở cơ sở thì từng trăm mili-giây đều thấy được. Hỏng thì im lặng:
   không có worker trang vẫn chạy đủ, không việc gì phải dựng người dùng dậy vì chuyện đó. */
(function () {
	if (!('serviceWorker' in navigator)) { return; }
	window.addEventListener('load', function () {
		navigator.serviceWorker.register(
			<?php echo wp_json_encode( self::url( 'sw.js' ) ); ?>,
			{ scope: <?php echo wp_json_encode( trailingslashit( VHCC_Tram::url() ) ); ?> }
		).catch(function () {});
	});
})();
</script>
<?php
	}

	// ==================================================================== nút cài app

	/**
	 * Ô "Cài ứng dụng" trong tab Tôi.
	 *
	 * =========================================================================================
	 * 🔴 VÌ SAO CẦN — TRÊN ANDROID KHÔNG AI TÌM THẤY NÚT CÀI
	 * =========================================================================================
	 * Trang này đã đủ mọi điều kiện Chrome đòi (HTTPS, manifest, biểu tượng 192+512, worker),
	 * nên Android CÀI ĐƯỢC từ lâu. Nhưng Chrome đời mới bỏ hẳn thanh gợi ý tự trồi lên; đường
	 * duy nhất còn lại là menu ⋮ → "Cài đặt ứng dụng", nằm sau ba lớp và không ai đoán ra.
	 *
	 * Chrome vẫn bắn sự kiện `beforeinstallprompt` cho trang — bắt lấy nó thì mình bày được
	 * một cái nút ngay chỗ người ta đang nhìn, và bấm vào là hộp thoại cài của hệ điều hành
	 * hiện lên thật.
	 *
	 * =========================================================================================
	 * ⚠️ BA ĐIỀU DỄ LÀM SAI, CẢ BA ĐỀU HỎNG ÂM THẦM
	 * =========================================================================================
	 * 1. PHẢI `preventDefault()` NGAY. Không chặn thì Chrome tự xử sự kiện theo cách của nó và
	 *    `prompt()` sau đó không làm gì cả — nút bấm không lên, không lỗi.
	 * 2. MỖI SỰ KIỆN CHỈ `prompt()` ĐƯỢC MỘT LẦN. Giữ lại gọi lần hai là một Promise bị từ
	 *    chối, mà nếu không bắt thì nó im. Nên dùng xong là bỏ, chờ Chrome bắn sự kiện mới.
	 * 3. iOS KHÔNG BAO GIỜ BẮN sự kiện này. Nên ô này tự ẩn trên iPhone — và đó là đúng: iPhone
	 *    đã có phần chỉ đường riêng ở ô Bật thông báo ("Chia sẻ → Thêm vào màn hình chính").
	 *    Bày một nút cài không bao giờ bấm được trên iPhone là cách nhanh nhất để mất lòng tin.
	 */
	public static function nut_cai() {
		?>
<div id="oCai" class="the an">
	<label style="margin:0 0 8px">Cài ứng dụng</label>
	<p class="ct" style="text-align:left;margin:0 0 10px">Cài vào màn hình chính để mở nhanh,
		chạy toàn màn hình và nhận được thông báo nhắc chấm công.</p>
	<button id="btCai" class="chinh" style="width:100%">Cài vào màn hình chính</button>
	<div id="baoCai"></div>
</div>
<script>
(function () {
	var o = document.getElementById('oCai'), bt = document.getElementById('btCai');
	if (!o || !bt) { return; }

	/* Đã chạy ở chế độ ứng dụng rồi thì không bày gì — cài lần nữa là vô nghĩa. */
	var daCai = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
	if (daCai || window.navigator.standalone === true) { return; }

	var sk = null;

	window.addEventListener('beforeinstallprompt', function (e) {
		/* ⚠️ Chặn NGAY. Không chặn thì `prompt()` phía dưới thành vô tác dụng, im lặng. */
		e.preventDefault();
		sk = e;
		o.classList.remove('an');
	});

	bt.addEventListener('click', function () {
		if (!sk) { return; }
		bt.disabled = true;
		var e = sk;
		/* Dùng xong là bỏ: mỗi sự kiện chỉ prompt() được một lần, gọi lần hai là Promise bị
		   từ chối mà không ai thấy. */
		sk = null;
		e.prompt();
		e.userChoice.then(function (kq) {
			if (kq && kq.outcome === 'accepted') {
				o.classList.add('an');
			} else {
				/* Từ chối thì ẩn nút đi chứ không bày mãi — Chrome sẽ bắn lại sự kiện ở lần mở
				   sau, lúc ấy nút tự hiện lại. Bày một nút đã bấm mà không cài được nữa là nút
				   chết. */
				o.classList.add('an');
			}
		}).catch(function () { o.classList.add('an'); });
	});

	window.addEventListener('appinstalled', function () { o.classList.add('an'); });
})();
</script>
<?php
	}
}
