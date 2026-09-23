<?php
/**
 * DẢI NHẮC "CHẤM CÔNG Ở ĐÂU" — hiện vài giây trên trang Nội bộ rồi tự đi.
 *
 * =================================================================================================
 * VIỆC NÓ GIẢI QUYẾT
 * =================================================================================================
 * Anh Thắng 17/09/2026: *"Nhân viên cũ họ đang vào nhầm link nội bộ để chấm công"*. Hai trang
 * dùng CHUNG một mã PIN, nên vào nhầm thì vẫn đăng nhập được — không có câu lỗi nào cả, và
 * người ta đứng đó tìm nút chấm công trên một trang không có nút ấy.
 *
 * Dải này nói đúng một câu: chấm công ở địa chỉ kia, bấm vào đây là sang.
 *
 * =================================================================================================
 * 🔴 SÁU CHỐT — MỖI CHỐT LÀ MỘT CÁCH LÀM PHIỀN NGƯỜI ĐÃ BIẾT RỒI
 * =================================================================================================
 * 1. KHÔNG CHẶN THAO TÁC. Dải nằm ở ĐÁY màn, không phủ kín, không có lớp mờ phía sau. Người vào
 *    trang Nội bộ để đọc bảng tin vẫn bấm được mọi thứ ngay trong lúc dải còn hiện. Một hộp
 *    chặn giữa màn thì tới ngày thứ hai ai cũng bấm tắt theo phản xạ, kể cả người chưa đọc.
 *
 * 2. TỰ ĐI SAU N GIÂY, VÀ THẤY ĐƯỢC NÓ SẮP ĐI. Có một vạch rút ngắn dần chạy suốt N giây ấy.
 *    Thiếu vạch thì người ta không biết nó tự tắt, nên phải dừng việc lại đi tìm nút đóng —
 *    đúng cái phiền mà "tự tắt" sinh ra để tránh.
 *
 * 3. CÓ NÚT "ĐỪNG HIỆN NỮA", NHỚ Ở MÁY. Anh Thắng nói hết 10 giây nó tự bỏ qua là đủ; nhưng
 *    người mở trang Nội bộ mười lần một ngày thì mười lần nhìn lại một dòng chữ họ thuộc lòng.
 *    Nhớ bằng `localStorage`, không bằng cookie: cookie đi kèm MỌI lượt gọi tới máy chủ, và
 *    đây là chuyện của riêng cái máy ấy.
 *
 * 4. 🔴 CÓ HẠN TỰ TẮT. Đây là thông báo CHUYỂN ĐỔI, không phải nội dung thường trực. Không có
 *    hạn thì sang năm nó vẫn nằm đó nhắc một việc không còn ai nhầm nữa — và lúc ấy nó thành
 *    thứ mọi người đã quen bỏ qua, nên lần sau có thông báo thật cũng không ai đọc.
 *
 * 5. 🔴 VIDEO LÀ MỘT ĐỊA CHỈ, KHÔNG NẰM TRONG PLUGIN. Một tệp mp4 hướng dẫn cỡ 4 MB nhét vào
 *    plugin là mỗi lượt tự cập nhật tải thêm 4 MB, mỗi bản phát hành phình thêm 4 MB, và kho
 *    mã mang một tệp nhị phân không bao giờ so khác biệt được. Tải lên Thư viện Media của
 *    WordPress rồi dán địa chỉ vào là xong.
 *
 * 6. 🔴 VIDEO KHÔNG TỰ TẢI. `preload="none"`. Người ở cơ sở mở trang Nội bộ bằng 3G, và một
 *    video 4 MB tự tải ở mỗi lượt mở trang là hết dung lượng của họ để đổi lấy một thứ họ
 *    không bấm. Bấm nút phát thì mới tải.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Nhac {

	const O = 'vhnb_nhac_cc';

	/** Khoá nhớ ở máy người dùng. Đổi nó là mọi người bị nhắc lại từ đầu — cố ý không đổi. */
	const KHOA_MAY = 'vhnb_nhac_cc_tat';

	const GIAY_MD    = 10;
	const GIAY_IT    = 3;
	const GIAY_NHIEU = 60;

	public static function mac_dinh() {
		return array(
			'bat'   => false,
			'giay'  => self::GIAY_MD,
			'dich'  => '',        // để trống -> tự hỏi plugin Chấm Công
			'video' => '',
			'tieu'  => 'Chấm công online đã chuyển sang trang riêng',
			'chu'   => 'Trang Nội bộ này không chấm công được. Bấm nút bên dưới để sang đúng trang '
				. 'chấm công — hoặc xem video hướng dẫn một phút.',
			'han'   => '',        // 'YYYY-MM-DD', rỗng = không hạn
		);
	}

	public static function cai() {
		$d = get_option( self::O );
		return is_array( $d ) ? array_merge( self::mac_dinh(), $d ) : self::mac_dinh();
	}

	public static function dat( $moi ) {
		$m = self::mac_dinh();
		$g = isset( $moi['giay'] ) ? (int) $moi['giay'] : $m['giay'];
		$g = max( self::GIAY_IT, min( self::GIAY_NHIEU, $g ) );
		$han = isset( $moi['han'] ) ? trim( (string) $moi['han'] ) : '';
		if ( '' !== $han && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $han ) ) { $han = ''; }
		update_option( self::O, array(
			'bat'   => ! empty( $moi['bat'] ),
			'giay'  => $g,
			'dich'  => isset( $moi['dich'] ) ? esc_url_raw( trim( (string) $moi['dich'] ) ) : '',
			'video' => isset( $moi['video'] ) ? esc_url_raw( trim( (string) $moi['video'] ) ) : '',
			'tieu'  => isset( $moi['tieu'] ) ? sanitize_text_field( $moi['tieu'] ) : $m['tieu'],
			'chu'   => isset( $moi['chu'] ) ? sanitize_textarea_field( $moi['chu'] ) : $m['chu'],
			'han'   => $han,
		), false );
		return array( 'ok' => true );
	}

	/**
	 * Địa chỉ trang chấm công.
	 *
	 * ⚠️ HỎI CHÍNH PLUGIN CHẤM CÔNG TRƯỚC, đừng ghi cứng `/cham-cong-online/`. Đường dẫn ấy đổi
	 *    được ở màn Cài đặt bên kia; ghi cứng là ngày ai đó đổi nó, dải này lặng lẽ dẫn cả công
	 *    ty tới một trang 404 — mà nội dung dải vẫn nói "bấm vào đây để chấm công".
	 *    Ô khai tay vẫn còn, để dùng được cả khi hai plugin nằm trên hai website khác nhau.
	 */
	public static function dich() {
		$c = self::cai();
		if ( '' !== trim( (string) $c['dich'] ) ) { return (string) $c['dich']; }
		if ( class_exists( 'VHCC_Tram' ) && method_exists( 'VHCC_Tram', 'url' ) ) {
			return (string) VHCC_Tram::url();
		}
		return '';
	}

	/** Còn trong hạn không. Rỗng hạn = còn mãi. */
	public static function con_han( $hom_nay = '' ) {
		$c = self::cai();
		$h = trim( (string) $c['han'] );
		if ( '' === $h ) { return true; }
		if ( '' === $hom_nay ) { $hom_nay = (string) current_time( 'Y-m-d' ); }
		return $hom_nay <= $h;
	}

	/** Có vẽ dải không — bốn điều kiện, thiếu một là thôi. */
	public static function nen_ve() {
		$c = self::cai();
		if ( empty( $c['bat'] ) ) { return false; }
		if ( ! self::con_han() ) { return false; }
		if ( '' === self::dich() ) { return false; }   // không biết dẫn đi đâu thì đừng mời bấm
		return true;
	}

	/** Địa chỉ có phải tệp phim phát thẳng được không (mp4/webm/ogg). */
	public static function la_phim( $u ) {
		$duong = (string) wp_parse_url( (string) $u, PHP_URL_PATH );
		return (bool) preg_match( '/\.(mp4|webm|ogv|ogg|mov)$/i', $duong );
	}

	/* ===================================================================== vẽ */

	public static function ve() {
		if ( ! self::nen_ve() ) { return; }
		$c    = self::cai();
		$dich = self::dich();
		$giay = (int) $c['giay'];
		$vid  = trim( (string) $c['video'] );

		echo '<div id="vhnb-nhac" hidden role="region" aria-label="Nhắc chỗ chấm công">';
		echo '<div class="vhnb-nhac-vach"><i id="vhnb-nhac-chay"></i></div>';
		echo '<div class="vhnb-nhac-than">';
		echo '<div class="vhnb-nhac-chu"><b>' . esc_html( $c['tieu'] ) . '</b>'
			. '<span>' . esc_html( $c['chu'] ) . '</span></div>';

		if ( '' !== $vid ) {
			if ( self::la_phim( $vid ) ) {
				/* 🔴 `preload="none"` — xem chốt 6 ở đầu tệp. `playsinline` để iPhone phát ngay
				   trong khung chứ không nhảy sang chế độ toàn màn hình, nuốt mất cả trang. */
				echo '<video class="vhnb-nhac-phim" src="' . esc_url( $vid ) . '" controls '
					. 'preload="none" playsinline></video>';
			} else {
				/* Không phải tệp phim (YouTube, Drive…) -> một đường dẫn, mở tab mới. Nhúng
				   khung của bên thứ ba vào đây là kéo theo mã của họ lên một trang nội bộ. */
				echo '<a class="vhnb-nhac-xem" href="' . esc_url( $vid ) . '" target="_blank" '
					. 'rel="noopener">▶ Xem hướng dẫn</a>';
			}
		}

		echo '<div class="vhnb-nhac-nut">';
		echo '<a class="vhnb-nhac-di" href="' . esc_url( $dich ) . '">Sang trang chấm công →</a>';
		echo '<button type="button" id="vhnb-nhac-thoi" class="vhnb-nhac-phu">Đừng hiện nữa</button>';
		echo '<button type="button" id="vhnb-nhac-dong" class="vhnb-nhac-phu" '
			. 'aria-label="Đóng">Đóng <span id="vhnb-nhac-dem">' . (int) $giay . '</span></button>';
		echo '</div></div></div>';

		self::css();
		self::js( $giay );
	}

	private static function css() {
		?>
<style>
/* Dải nằm ở ĐÁY và KHÔNG phủ kín — xem chốt 1. `pointer-events` chỉ bật trên chính dải, nên
   phần trang phía sau vẫn bấm được bình thường. */
#vhnb-nhac{position:fixed;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom,0px));
 z-index:9000;max-width:560px;margin:0 auto;background:#0b1f3a;color:#fff;border-radius:14px;
 border:1px solid rgba(201,168,76,.5);box-shadow:0 10px 34px rgba(0,0,0,.34);overflow:hidden;
 font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
#vhnb-nhac[hidden]{display:none}
.vhnb-nhac-vach{height:3px;background:rgba(255,255,255,.14)}
.vhnb-nhac-vach i{display:block;height:100%;width:100%;background:#c9a84c;transform-origin:left center}
.vhnb-nhac-than{padding:12px 14px;display:flex;flex-direction:column;gap:10px}
.vhnb-nhac-chu b{display:block;font-size:15px;margin:0 0 2px}
.vhnb-nhac-chu span{display:block;color:#cbd5e1;font-size:13px}
.vhnb-nhac-phim{width:100%;max-height:180px;border-radius:9px;background:#000}
.vhnb-nhac-xem{align-self:flex-start;color:#c9a84c;text-decoration:none;font-weight:600}
.vhnb-nhac-nut{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.vhnb-nhac-di{flex:1 1 190px;text-align:center;background:#c9a84c;color:#0b1f3a;font-weight:700;
 text-decoration:none;padding:11px 14px;border-radius:9px}
.vhnb-nhac-phu{background:transparent;border:0;color:#94a3b8;font:inherit;padding:10px 6px;cursor:pointer}
.vhnb-nhac-phu:hover{color:#e2e8f0}
/* Máy nào đặt "giảm chuyển động" thì bỏ hẳn phần chạy của vạch — nó vẫn đếm, chỉ không động đậy. */
@media (prefers-reduced-motion:reduce){.vhnb-nhac-vach i{transition:none!important}}
</style>
		<?php
	}

	private static function js( $giay ) {
		?>
<script>
(function(){
	'use strict';
	var o = document.getElementById('vhnb-nhac');
	if(!o) return;
	var KHOA = <?php echo wp_json_encode( self::KHOA_MAY ); ?>;
	var GIAY = <?php echo (int) $giay; ?>;

	/* Đã bấm "Đừng hiện nữa" thì thôi. Bọc try/catch: chế độ riêng tư của Safari chặn
	   localStorage và NÉM lỗi — không bắt thì cả khối này chết, và dải không bao giờ hiện. */
	try { if(localStorage.getItem(KHOA)){ return; } } catch(e){}

	o.hidden = false;

	var chay = document.getElementById('vhnb-nhac-chay');
	var dem  = document.getElementById('vhnb-nhac-dem');
	var het  = null, nhip = null, con = GIAY;

	/* Vạch rút từ đầy về rỗng đúng GIÂY giây — người ta thấy nó sắp tự đi (chốt 2). Dùng
	   transform chứ không đổi width: đổi width bắt trình duyệt tính lại bố cục 60 lần/giây. */
	if(chay){
		chay.style.transition = 'transform ' + GIAY + 's linear';
		/* Đợi một khung hình rồi mới đặt đích, không thì trình duyệt gộp hai giá trị làm một
		   và vạch nhảy phắt sang 0 mà không chạy. */
		requestAnimationFrame(function(){
			requestAnimationFrame(function(){ chay.style.transform = 'scaleX(0)'; });
		});
	}

	function dong(){
		if(het){ clearTimeout(het); het = null; }
		if(nhip){ clearInterval(nhip); nhip = null; }
		o.hidden = true;
	}

	nhip = setInterval(function(){
		con--;
		if(dem){ dem.textContent = con > 0 ? con : ''; }
		if(con <= 0){ clearInterval(nhip); nhip = null; }
	}, 1000);
	het = setTimeout(dong, GIAY * 1000);

	var btDong = document.getElementById('vhnb-nhac-dong');
	if(btDong){ btDong.addEventListener('click', dong); }

	var btThoi = document.getElementById('vhnb-nhac-thoi');
	if(btThoi){
		btThoi.addEventListener('click', function(){
			try { localStorage.setItem(KHOA, '1'); } catch(e){}
			dong();
		});
	}

	/* 🔴 BẤM PHÁT VIDEO THÌ DỪNG ĐẾM. Không dừng thì dải tự đóng giữa lúc người ta đang xem —
	   và họ phải tải lại trang mới xem tiếp được, nếu còn nhớ là có video. */
	var phim = o.querySelector('.vhnb-nhac-phim');
	if(phim){
		phim.addEventListener('play', function(){
			if(het){ clearTimeout(het); het = null; }
			if(nhip){ clearInterval(nhip); nhip = null; }
			if(dem){ dem.textContent = ''; }
			if(chay){ chay.style.transition = 'none'; chay.style.transform = 'scaleX(1)'; }
		});
	}
})();
</script>
		<?php
	}
}
