<?php
/**
 * TRANG /nap-plugin/ — ô nạp .zip gọn cho điện thoại.
 *
 * Anh Thắng 22/09/2026: *"trang nạp plugin đang khá bất tiện. Nên anh muốn tạo trang nạp rời.
 * Gắn thẳng vào app chấm công cho admin tự xem và nạp"*.
 *
 * Màn wp-admin "Cài plugin → Tải lên" trên điện thoại là ba lượt cuộn ngang, nút bấm nhỏ hơn đầu
 * ngón tay, và sau khi cài xong thì đứng ở một trang trắng không nói bản nào vừa lên. Trang này
 * làm đúng một việc ấy, ở khổ điện thoại, và nói rõ từ bản nào sang bản nào.
 *
 * =================================================================================================
 * 🔴 TRANG NÀY CHỈ VẼ. MỌI PHÉP GÁC NẰM Ở `VHCC_NapPlugin`.
 * =================================================================================================
 * Giấu nút đi không phải là khoá cửa: ai gõ thẳng địa chỉ vẫn tới được đây, và một lượt POST
 * dựng bằng tay thì không đi qua nút nào cả. Nên mọi phép kiểm đều hỏi lại `VHCC_NapPlugin::nap()`,
 * và phần vẽ dưới đây chỉ quyết định xem có BÀY ô nhập ra hay không.
 *
 * @package VHCP_ChamCong
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_TrangNap {

	const SLUG_MD = 'nap-plugin';

	public static function slug() {
		$s = get_option( 'vhcc_slug_nap' );
		$s = $s ? sanitize_title( $s ) : self::SLUG_MD;
		return $s ? $s : self::SLUG_MD;
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhcc_nap', '1', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhcc_nap=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function query_vars( $v ) { $v[] = 'vhcc_nap'; return $v; }

	/* ═════════════════════════════════════════════════════════════ CHỐNG GIẢ MẠO ════════ */

	/**
	 * Vé chống giả mạo, buộc vào ĐÚNG phiên đang mở.
	 *
	 * 🔴 KHÔNG DÙNG `wp_create_nonce()`. Người bấm ở đây thường KHÔNG đăng nhập WordPress, nên
	 *    nonce của WordPress rơi về "người dùng số 0" — tức mọi khách đều có chung một vé, và vé
	 *    ấy dán được vào một trang bất kỳ để lừa người khác bấm hộ. Vé này buộc vào thẻ phiên của
	 *    trạm nên nó chỉ dùng được bởi đúng phiên đã mở nó.
	 */
	private static function ve( $token ) {
		return hash_hmac( 'sha256', 'vhcc-nap|' . (string) $token . '|' . gmdate( 'Y-m-d' ),
			wp_salt( 'nonce' ) );
	}

	private static function ve_dung( $token, $ve ) {
		$that = self::ve( $token );
		return '' !== (string) $ve && hash_equals( $that, (string) $ve );
	}

	/* ═══════════════════════════════════════════════════════════════════ PHIÊN ══════════ */

	/** Người đang mở trang, dạng `array( ma_nv, ho_ten, role, token )`, hoặc `null`. */
	private static function ai() {
		if ( ! class_exists( 'VHCC_Web' ) || ! method_exists( 'VHCC_Web', 'the_phien' ) ) { return null; }
		$tok = (string) VHCC_Web::the_phien();
		if ( '' === $tok ) { return null; }
		if ( ! class_exists( 'VHCC_Auth' ) || ! method_exists( 'VHCC_Auth', 'user_by_token' ) ) { return null; }
		$u = VHCC_Auth::user_by_token( $tok );
		if ( ! $u ) { return null; }
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) { return null; }
		return array( 'ma_nv' => $ma, 'ho_ten' => (string) ( isset( $u['name'] ) ? $u['name'] : '' ),
			'role' => (string) ( isset( $u['role'] ) ? $u['role'] : '' ), 'token' => $tok );
	}

	/* ════════════════════════════════════════════════════════════════════ VẼ ════════════ */

	public static function maybe_render() {
		$is = ( (int) get_query_var( 'vhcc_nap' ) === 1 );
		if ( ! $is && isset( $_GET['vhcc_nap'] ) && '1' === $_GET['vhcc_nap'] ) { $is = true; }
		if ( ! $is ) { return; }
		self::render();
		exit;
	}

	public static function render() {
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		/* Trang này có ô mật khẩu — đừng cho ai nhúng nó vào khung của trang khác. */
		header( 'X-Frame-Options: DENY' );
		header( 'Referrer-Policy: no-referrer' );

		$ai  = self::ai();
		$kq  = null;

		if ( $ai && 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			$kq = self::xu_ly_post( $ai );
		}

		echo self::html( $ai, $kq );
	}

	/**
	 * Một lượt bấm Nạp.
	 *
	 * Cửa xác nhận là PIN của chính người đang đứng trong phiên — xem khối 🔴 ở `VHCC_NapPlugin`.
	 */
	private static function xu_ly_post( $ai ) {
		if ( ! self::ve_dung( $ai['token'], isset( $_POST['ve'] ) ? wp_unslash( $_POST['ve'] ) : '' ) ) {
			return array( 'ok' => false, 'error' => 'Phiếu gửi không hợp lệ hoặc đã cũ — tải lại trang rồi làm lại.' );
		}
		if ( ! is_ssl() ) {
			return array( 'ok' => false, 'error' => 'Trang đang chạy KHÔNG mã hoá (http). '
				. 'Gửi PIN qua đường này là ai nghe trộm cũng đọc được — đã chặn.' );
		}
		/* ⚠️ KHÔNG `sanitize_text_field()` PIN — `pin_sach()` bên trong đã lọc còn chữ số. */
		$pin = isset( $_POST['pin'] ) ? (string) wp_unslash( $_POST['pin'] ) : '';
		$tep = isset( $_FILES['tep'] ) ? $_FILES['tep'] : null;

		return VHCC_NapPlugin::nap( $ai, $pin, $tep );
	}

	/* ══════════════════════════════════════════════════════════════════ KHUNG ═══════════ */

	private static function html( $ai, $kq ) {
		$than = '';

		if ( ! $ai ) {
			$than = self::bao( 'warn', 'Chưa đăng nhập',
				'Mở app chấm công, gõ PIN, rồi quay lại trang này.'
				. ( class_exists( 'VHCC_Tram' ) && method_exists( 'VHCC_Tram', 'url' )
					? ' <a href="' . esc_url( VHCC_Tram::url() ) . '">Mở app →</a>' : '' ) );
		} else {
			$v = VHCC_NapPlugin::kiem_vai( $ai );
			if ( empty( $v['ok'] ) ) {
				/* Nói rõ đang là vai gì và cần vai gì — "không đủ quyền" trống trơn thì người đọc
				   không biết đi xin ai. */
				$than = self::bao( 'warn', 'Không đủ quyền', esc_html( $v['error'] ) );
			} else {
				$than = self::than_chinh( $ai, $kq );
			}
		}

		return '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<meta name="robots" content="noindex,nofollow">'
			. '<title>Nạp plugin</title>' . self::css() . '</head><body>'
			. '<header><b>Nạp plugin</b>'
			. ( $ai ? '<span class="ai">' . esc_html( $ai['ho_ten'] ) . '</span>' : '' )
			. '</header><main>' . $than . '</main></body></html>';
	}

	private static function bao( $loai, $tieu_de, $noi_dung ) {
		return '<div class="bao ' . esc_attr( $loai ) . '"><b>' . esc_html( $tieu_de ) . '</b>'
			. '<div>' . $noi_dung . '</div></div>';
	}

	private static function than_chinh( $ai, $kq ) {
		$h = '';

		if ( is_array( $kq ) ) {
			$h .= ! empty( $kq['ok'] )
				? self::bao( 'ok', 'Xong', esc_html( $kq['msg'] ) )
				: self::bao( 'loi', 'Chưa nạp được', esc_html( $kq['error'] ) );
		}

		if ( ! is_ssl() ) {
			$h .= self::bao( 'loi', 'Trang chưa mã hoá (http)',
				'Trang này đòi gõ lại PIN, mà đường truyền chưa mã hoá thì ai nghe trộm cũng đọc '
				. 'được. Ô nhập bị ẩn cho tới khi site chạy https.' );
		}

		/* ── bảng plugin đang cài ── */
		$ds = VHCC_NapPlugin::ds();
		$h .= '<section><h2>Đang cài (' . count( $ds ) . ')</h2>';
		if ( ! $ds ) {
			$h .= '<p class="mo">Chưa thấy plugin nào họ <code>vhcp-</code>.</p>';
		} else {
			$h .= '<ul class="ds">';
			foreach ( $ds as $x ) {
				$h .= '<li><div class="ten">' . esc_html( $x['ten'] ) . '</div>'
					. '<div class="phu"><code>' . esc_html( $x['slug'] ) . '</code>'
					. ' · bản <b>' . esc_html( $x['ban'] ) . '</b>'
					. ' · ' . ( $x['bat'] ? '<span class="on">đang bật</span>'
						: '<span class="off">đang tắt</span>' ) . '</div></li>';
			}
			$h .= '</ul>';
		}
		$h .= '</section>';

		/* ── ô nạp ── */
		if ( is_ssl() ) {
			$con = VHCC_NapPlugin::con_duoc_thu();
			$h .= '<section><h2>Nạp bản mới</h2>'
				. '<form method="post" enctype="multipart/form-data">'
				. '<input type="hidden" name="ve" value="' . esc_attr( self::ve( $ai['token'] ) ) . '">'
				. '<label>Tệp .zip của plugin'
				. '<input type="file" name="tep" accept=".zip,application/zip" required></label>'
				. '<p class="mo">Chỉ nhận plugin họ <code>vhcp-</code>. Máy chủ mở tệp ra soi '
				. 'trước khi cài — sai ruột là chối, không cần biết tên tệp là gì.</p>'
				. '<hr>'
				. '<p class="mo"><b>Vì sao hỏi lại PIN?</b> Nạp plugin là chạy mã trên máy chủ, nên bước '
				. 'cuối xác nhận đúng người đang cầm máy là người bấm. PIN phải là <b>PIN của chính '
				. 'anh/chị</b> — PIN của người khác không dùng được, kể cả admin khác.</p>'
				. '<label>PIN của anh/chị'
				. '<input type="password" name="pin" inputmode="numeric" pattern="[0-9]*" '
				. 'autocomplete="one-time-code" maxlength="8" required></label>';
			if ( empty( $con['ok'] ) ) {
				$h .= self::bao( 'loi', 'Đang tạm khoá',
					'Gõ sai quá nhiều lần. Chờ ít phút rồi thử lại.' );
			} elseif ( (int) $con['daSai'] > 0 ) {
				$h .= self::bao( 'warn', 'Đã gõ sai ' . (int) $con['daSai'] . ' lần',
					'Còn ' . (int) $con['conLai'] . ' lượt trước khi bị tạm khoá.' );
			}
			$h .= '<button type="submit"' . ( empty( $con['ok'] ) ? ' disabled' : '' ) . '>Nạp</button>'
				. '</form></section>';
		}

		return $h;
	}

	private static function css() {
		return '<style>'
			. ':root{--n:#111;--m:#6b7280;--v:#e5e7eb;--bg:#fff;--ok:#047857;--loi:#b91c1c;--w:#b45309}'
			. '@media(prefers-color-scheme:dark){:root{--n:#e5e7eb;--m:#9ca3af;--v:#374151;--bg:#111827}}'
			. '*{box-sizing:border-box}'
			. 'body{margin:0;background:var(--bg);color:var(--n);'
			. 'font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}'
			. 'header{display:flex;justify-content:space-between;align-items:center;gap:8px;'
			. 'padding:14px 16px;border-bottom:1px solid var(--v);position:sticky;top:0;background:var(--bg)}'
			. 'header .ai{color:var(--m);font-size:14px}'
			. 'main{padding:16px;max-width:640px;margin:0 auto}'
			. 'h2{font-size:15px;text-transform:uppercase;letter-spacing:.04em;color:var(--m);margin:22px 0 10px}'
			. 'section:first-child h2{margin-top:4px}'
			. '.ds{list-style:none;margin:0;padding:0;border:1px solid var(--v);border-radius:12px;overflow:hidden}'
			. '.ds li{padding:12px 14px;border-top:1px solid var(--v)}.ds li:first-child{border-top:0}'
			. '.ten{font-weight:600}.phu{color:var(--m);font-size:13px;margin-top:2px}'
			. '.on{color:var(--ok)}.off{color:var(--m)}'
			. 'code{font-size:13px}'
			. '.mo{color:var(--m);font-size:14px}'
			/* 🔴 Nút và ô nhập cao tối thiểu 48px — ngón tay không bấm trúng ô cao 30px, và đây
			   là trang sinh ra CHỈ vì màn wp-admin quá khó bấm trên điện thoại. */
			. 'label{display:block;margin:14px 0 0;font-size:14px;color:var(--m)}'
			. 'input{display:block;width:100%;margin-top:6px;padding:12px;min-height:48px;'
			. 'font-size:16px;color:var(--n);background:var(--bg);'
			. 'border:1px solid var(--v);border-radius:10px}'
			. 'button{width:100%;margin-top:18px;padding:14px;min-height:52px;font-size:17px;'
			. 'font-weight:600;color:#fff;background:#111827;border:0;border-radius:12px}'
			. '@media(prefers-color-scheme:dark){button{background:#2563eb}}'
			. 'button[disabled]{opacity:.45}'
			. 'hr{border:0;border-top:1px solid var(--v);margin:20px 0}'
			. '.bao{padding:12px 14px;border-radius:12px;margin:0 0 14px;font-size:14px;'
			. 'border:1px solid var(--v)}'
			. '.bao b{display:block;margin-bottom:2px}'
			. '.bao.ok{border-color:var(--ok);color:var(--ok)}'
			. '.bao.loi{border-color:var(--loi);color:var(--loi)}'
			. '.bao.warn{border-color:var(--w);color:var(--w)}'
			. '.bao a{color:inherit}'
			. '</style>';
	}
}
