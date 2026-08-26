<?php
/**
 * TRANG /noi-bo/ — bảng tin nội bộ.
 *
 * 🔴 DÙNG CHUNG THẺ PHIÊN VỚI HỆ CHẤM CÔNG. Ai đăng nhập trạm chấm công thì vào thẳng đây, không
 *    phải nhập PIN lần hai. Cụ thể: đọc cookie của `VHCC_Web` rồi hỏi `VHCC_Auth::user_by_token`.
 *
 * ⚠️ KHÔNG có script nào — giống mọi trang khác của hệ. Bảng tin không cần JavaScript: đăng bài,
 *    bình luận, thả tim đều là một lượt POST rồi chuyển hướng. Đổi lại nó chạy được trên mọi máy,
 *    và thử được bằng bộ thử PHP.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Trang {

	const SLUG_MD = 'noi-bo';

	public static function slug() {
		$s = get_option( 'vhnb_slug' );
		$s = $s ? sanitize_title( $s ) : self::SLUG_MD;
		return $s ? $s : self::SLUG_MD;
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhnb', '1', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhnb_trang=1', 'top' );
		add_filter( 'query_vars', function ( $v ) { $v[] = 'vhnb_trang'; return $v; } );
		add_action( 'template_redirect', array( __CLASS__, 'hien_trang' ) );
	}

	/**
	 * Cửa vào của trang. Tách khỏi `phuc_vu()` vì `exit` nằm ở ĐÂY, không nằm trong đó.
	 *
	 * 🔴 `phuc_vu()` KHÔNG được `exit`. Có `exit` trong đó thì bộ thử gọi nó là bộ thử tự chết
	 *    giữa đường — nên toàn bộ phần vẽ trang sẽ không bao giờ có phép thử nào. Đúng cách
	 *    `VHCC_Web` làm: `maybe_render` gác cửa và exit, `phuc_vu` chỉ in ra.
	 */
	public static function hien_trang() {
		$la = ( (int) get_query_var( 'vhnb_trang' ) === 1 );
		if ( ! $la && isset( $_GET['vhnb'] ) ) { $la = true; }
		if ( ! $la ) { return; }
		nocache_headers();
		self::phuc_vu();
		exit;
	}

	/* ==================================================================== người dùng */

	/**
	 * Người đang đăng nhập — lấy từ thẻ phiên của hệ chấm công.
	 * Thiếu plugin chấm công thì trả null và trang nói thẳng ra, không trắng trang.
	 */
	public static function toi() {
		$tok = self::the_phien();
		if ( '' === $tok ) { return null; }
		/* ⚠️ Gác `method_exists` NGAY TẠI ĐÂY, dù `the_phien()` cũng đã hỏi. Gác ở hàm khác thì
		   hôm nào có người đổi `the_phien()` là lời gọi này hụt — mà gọi hụt một hàm tĩnh là
		   Fatal error, TRẮNG CẢ TRANG. Luật của `tools/test/kiem-goi-cheo.php`: gác phải nằm
		   CÙNG HÀM với lời gọi. */
		if ( ! method_exists( 'VHCC_Auth', 'user_by_token' ) ) { return null; }
		return VHCC_Auth::user_by_token( $tok );
	}

	/**
	 * Thẻ phiên đang mang trong cookie, hoặc chuỗi rỗng.
	 *
	 * ⚠️ `defined()` phải đứng MỘT MÌNH trước `constant()`. Viết
	 *    `if ( ! defined( X ) && ! constant( X ) )` là đúng lúc hằng KHÔNG tồn tại thì vẫn gọi
	 *    `constant()` — PHP 8 ném Error, cả trang trắng. Đây là nhánh chỉ chạy khi thiếu plugin
	 *    chấm công, tức là đúng lúc không ai ngồi thử.
	 */
	public static function the_phien() {
		if ( ! self::co_he_cham_cong() ) { return ''; }
		if ( ! defined( 'VHCC_Web::COOKIE' ) ) { return ''; }
		$c = constant( 'VHCC_Web::COOKIE' );
		if ( ! $c || empty( $_COOKIE[ $c ] ) ) { return ''; }
		return (string) $_COOKIE[ $c ];
	}

	public static function co_he_cham_cong() {
		return class_exists( 'VHCC_Web' ) && class_exists( 'VHCC_Auth' )
			&& method_exists( 'VHCC_Auth', 'user_by_token' );
	}

	/* ==================================================================== chữ ký biểu mẫu */

	/**
	 * Chữ ký chống giả mạo biểu mẫu, buộc vào chính thẻ phiên.
	 *
	 * 🔴 KHÔNG dùng `wp_nonce_field` được: nonce của WordPress buộc vào tài khoản WordPress, mà
	 *    240 người ở đây không có tài khoản WordPress nào cả — nonce sẽ tính theo id 0, ai cũng
	 *    ra một chuỗi giống nhau, tức là chẳng chặn được gì.
	 *
	 * ⚠️ Tự tính lấy chứ KHÔNG mượn `VHCC_Web::chu_ky` — hai plugin cài độc lập; mượn hàm bên kia
	 *    là đúng lúc bên kia đổi tên hàm thì bên này gãy.
	 */
	public static function chu_ky( $tok ) {
		return hash_hmac( 'sha256', 'vhnb|' . (string) $tok, wp_salt( 'nonce' ) );
	}

	/** Ô ẩn mang chữ ký — mọi biểu mẫu POST của trang này đều phải có. */
	public static function o_ky() {
		return '<input type="hidden" name="ky" value="' . esc_attr( self::chu_ky( self::the_phien() ) ) . '">';
	}

	private static function ky_dung() {
		$tok = self::the_phien();
		$gui = isset( $_POST['ky'] ) ? (string) wp_unslash( $_POST['ky'] ) : '';
		return ( '' !== $tok && '' !== $gui && hash_equals( self::chu_ky( $tok ), $gui ) );
	}

	/* ==================================================================== phục vụ */

	public static function phuc_vu() {
		$toi = self::toi();

		if ( ! empty( $_POST['viec'] ) && $toi ) {
			$bao = self::ky_dung()
				? self::lam_viec( sanitize_text_field( wp_unslash( $_POST['viec'] ) ), $toi )
				: array( 'loi' => 'Phiên đã hết hoặc biểu mẫu không hợp lệ. Tải lại trang rồi làm lại.' );
			set_transient( self::khoa_bao(), $bao, 120 );
			/* POST -> chuyển hướng -> GET: F5 không gửi lại bài, và giữ nguyên bộ lọc nhóm. */
			wp_safe_redirect( self::url_hien() );
			return;
		}

		self::ve( $toi );
	}

	private static function khoa_bao() {
		return 'vhnb_bao_' . md5( self::the_phien() );
	}

	private static function url_hien() {
		$nhom = isset( $_POST['nhom_xem'] ) ? sanitize_text_field( wp_unslash( $_POST['nhom_xem'] ) ) : '';
		if ( '' === $nhom && isset( $_GET['nhom'] ) ) {
			$nhom = sanitize_text_field( wp_unslash( $_GET['nhom'] ) );
		}
		return ( '' !== $nhom ) ? add_query_arg( 'nhom', $nhom, self::url() ) : self::url();
	}

	private static function lam_viec( $viec, $toi ) {
		$id = isset( $_POST['bai'] ) ? (int) $_POST['bai'] : 0;

		if ( 'dang' === $viec ) {
			$r = VHNB_Bai::dang( $toi,
				isset( $_POST['noi_dung'] ) ? wp_unslash( $_POST['noi_dung'] ) : '',
				isset( $_POST['nhom'] ) ? wp_unslash( $_POST['nhom'] ) : '' );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => 'Đã đăng.' );
		}
		if ( 'binh_luan' === $viec ) {
			$r = VHNB_Bai::binh_luan( $toi, $id,
				isset( $_POST['noi_dung'] ) ? wp_unslash( $_POST['noi_dung'] ) : '' );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array();
		}
		if ( 'tim' === $viec ) {
			$r = VHNB_Bai::tim( $toi, $id );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array();
		}
		if ( 'xoa' === $viec ) {
			$r = VHNB_Bai::xoa( $toi, $id );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => 'Đã xoá bài.' );
		}
		if ( 'ghim' === $viec ) {
			$r = VHNB_Bai::ghim( $toi, $id, ! empty( $_POST['bat'] ) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array();
		}
		return array( 'loi' => 'Không biết việc "' . $viec . '".' );
	}

	/* ==================================================================== vẽ */

	public static function ve( $toi ) {
		$nhom = isset( $_GET['nhom'] ) ? sanitize_text_field( wp_unslash( $_GET['nhom'] ) ) : '';
		echo self::dau();
		echo '<header><div class="bo">'
			. '<a class="hieu" href="' . esc_url( self::url() ) . '"><b>K&amp;H</b> Nội bộ</a>';
		if ( $toi ) {
			echo '<span class="mo ai">' . esc_html( $toi['name'] ) . '</span>';
			/* ⚠️ DÒ TỪNG HÀM, KHÔNG DÒ MỖI TÊN LỚP: lớp CÓ mà hàm KHÔNG là trắng cả trang. */
			if ( class_exists( 'VHTC_Trang' ) && method_exists( 'VHTC_Trang', 'url' ) ) {
				echo '<a class="nut" href="' . esc_url( VHTC_Trang::url() ) . '">🏠 Cổng K&amp;H</a>';
			}
			if ( class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'url' ) ) {
				echo '<a class="nut" href="' . esc_url( VHCC_Web::url() ) . '">🕐 Chấm công</a>';
			}
		}
		echo '</div></header><div class="bo">';

		if ( ! self::co_he_cham_cong() ) {
			echo '<div class="bao loi"><b>Chưa cài plugin Chấm Công.</b> Trang nội bộ dùng chung '
				. 'mã PIN với hệ chấm công, nên phải có plugin đó thì mới đăng nhập được.</div>';
			echo '</div></body></html>';
			return;
		}
		if ( ! $toi ) {
			echo '<div class="the" style="max-width:460px;margin:40px auto">'
				. '<h2>Nội bộ K&amp;H</h2>'
				. '<p class="mo">Đăng nhập bằng <b>mã PIN chấm công</b> ở trang chấm công, rồi quay '
				. 'lại đây — hai trang dùng chung một phiên, không phải nhập PIN hai lần.</p>'
				. '<p><a class="nut chinh" href="' . esc_url( VHCC_Web::url() ) . '">Tới trang đăng nhập</a></p>'
				. '</div></div></body></html>';
			return;
		}

		$bao = get_transient( self::khoa_bao() );
		if ( is_array( $bao ) ) {
			delete_transient( self::khoa_bao() );
			if ( ! empty( $bao['loi'] ) )  { echo '<div class="bao loi">' . esc_html( $bao['loi'] ) . '</div>'; }
			if ( ! empty( $bao['xong'] ) ) { echo '<div class="bao ok">' . esc_html( $bao['xong'] ) . '</div>'; }
		}

		self::o_dang( $toi, $nhom );
		self::thanh_nhom( $nhom );
		self::bang_tin( $toi, $nhom );
		echo '</div></body></html>';
	}

	private static function o_dang( $toi, $nhom ) {
		echo '<div class="the">';
		echo '<form method="post">';
		echo self::o_ky();
		echo '<input type="hidden" name="viec" value="dang">';
		echo '<input type="hidden" name="nhom_xem" value="' . esc_attr( $nhom ) . '">';
		echo '<textarea name="noi_dung" rows="3" style="width:100%" maxlength="' . VHNB_Bai::DAI_TOI_DA . '" '
			. 'placeholder="' . esc_attr( $toi['name'] . ' ơi, có gì mới?' ) . '"></textarea>';
		echo '<div class="hang" style="margin-top:8px">';
		echo '<div><label for="nb_nhom">Đăng vào</label><select id="nb_nhom" name="nhom">';
		echo '<option value="">Toàn công ty</option>';
		foreach ( self::ds_nhom() as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . ( $x === $nhom ? ' selected' : '' ) . '>'
				. esc_html( $x ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><button class="chinh">Đăng</button></div>';
		echo '</div></form></div>';
	}

	private static function thanh_nhom( $nhom ) {
		$ds = self::ds_nhom();
		if ( ! $ds ) { return; }
		echo '<div class="the thanh"><a class="nut' . ( '' === $nhom ? ' chinh' : '' ) . '" href="'
			. esc_url( self::url() ) . '">Tất cả</a>';
		foreach ( $ds as $x ) {
			echo '<a class="nut' . ( $x === $nhom ? ' chinh' : '' ) . '" href="'
				. esc_url( add_query_arg( 'nhom', $x, self::url() ) ) . '">' . esc_html( $x ) . '</a>';
		}
		echo '</div>';
	}

	/** Nhóm = bộ phận của hệ chấm công. Không bịa danh sách riêng — hai nơi sẽ lệch nhau. */
	public static function ds_nhom() {
		if ( class_exists( 'VHCC_Luong' ) && defined( 'VHCC_Luong::BP_DS' ) ) {
			return (array) constant( 'VHCC_Luong::BP_DS' );
		}
		return array();
	}

	private static function bang_tin( $toi, $nhom ) {
		$trang = isset( $_GET['tr'] ) ? max( 1, (int) $_GET['tr'] ) : 1;
		$ds    = VHNB_Bai::bang_tin( $nhom, $trang );
		if ( ! $ds ) {
			echo '<div class="the"><p class="mo">Chưa có bài nào'
				. ( '' !== $nhom ? ' ở nhóm ' . esc_html( $nhom ) : '' ) . '. Đăng bài đầu tiên đi.</p></div>';
			return;
		}
		$tim = VHNB_Bai::da_tim( $toi, $ds );

		foreach ( $ds as $b ) {
			$id = (int) $b['id'];
			echo '<div class="the bai">';
			echo '<div class="dau-bai"><b>' . esc_html( $b['ho_ten'] ) . '</b>';
			if ( '' !== trim( (string) $b['vai_tro'] ) ) {
				echo ' <span class="duoi">' . esc_html( $b['vai_tro'] ) . '</span>';
			}
			if ( '' !== trim( (string) $b['nhom'] ) ) {
				echo ' <span class="duoi nhom">' . esc_html( $b['nhom'] ) . '</span>';
			}
			if ( $b['ghim'] ) { echo ' <span class="duoi ghim">📌 ghim</span>'; }
			echo '<span class="mo luc">' . esc_html( VHNB_Bai::bao_lau( $b['tao_luc'] ) ) . '</span>';
			echo '</div>';

			/* `esc_html` rồi `nl2br`: thoát TRƯỚC, xuống dòng SAU. Làm ngược lại là mấy thẻ <br>
			   vừa chèn cũng bị thoát thành chữ. */
			echo '<div class="nd">' . nl2br( esc_html( (string) $b['noi_dung'] ) ) . '</div>';

			echo '<div class="hang chan">';
			echo '<form method="post" style="margin:0">' . self::o_ky()
				. '<input type="hidden" name="viec" value="tim">'
				. '<input type="hidden" name="bai" value="' . $id . '">'
				. '<input type="hidden" name="nhom_xem" value="' . esc_attr( $nhom ) . '">'
				. '<button class="nho">' . ( isset( $tim[ $id ] ) ? '❤️' : '🤍' ) . ' '
				. (int) $b['so_tim'] . '</button></form>';
			echo '<span class="mo">💬 ' . (int) $b['so_bl'] . '</span>';
			if ( VHNB_Bai::la_admin( $toi ) ) {
				echo '<form method="post" style="margin:0">' . self::o_ky()
					. '<input type="hidden" name="viec" value="ghim">'
					. '<input type="hidden" name="bai" value="' . $id . '">'
					. '<input type="hidden" name="nhom_xem" value="' . esc_attr( $nhom ) . '">'
					. ( $b['ghim'] ? '' : '<input type="hidden" name="bat" value="1">' )
					. '<button class="nho">' . ( $b['ghim'] ? 'Bỏ ghim' : '📌 Ghim' ) . '</button></form>';
			}
			if ( VHNB_Bai::duoc_xoa( $toi, $b ) ) {
				echo '<form method="post" style="margin:0">' . self::o_ky()
					. '<input type="hidden" name="viec" value="xoa">'
					. '<input type="hidden" name="bai" value="' . $id . '">'
					. '<input type="hidden" name="nhom_xem" value="' . esc_attr( $nhom ) . '">'
					. '<button class="nho nguy">Xoá</button></form>';
			}
			echo '</div>';

			foreach ( VHNB_Bai::ds_binh_luan( $id ) as $c ) {
				echo '<div class="bl"><b>' . esc_html( $c['ho_ten'] ) . '</b> '
					. '<span class="mo">' . esc_html( VHNB_Bai::bao_lau( $c['tao_luc'] ) ) . '</span><br>'
					. nl2br( esc_html( (string) $c['noi_dung'] ) ) . '</div>';
			}
			echo '<form method="post" class="hang" style="margin-top:8px">'
				. self::o_ky()
				. '<input type="hidden" name="viec" value="binh_luan">'
				. '<input type="hidden" name="bai" value="' . $id . '">'
				. '<input type="hidden" name="nhom_xem" value="' . esc_attr( $nhom ) . '">'
				. '<div style="flex:1"><input name="noi_dung" placeholder="Viết bình luận…" '
				. 'maxlength="' . VHNB_Bai::BL_TOI_DA . '" style="width:100%"></div>'
				. '<div><button>Gửi</button></div></form>';
			echo '</div>';
		}

		/* Phân trang: chỉ hiện nút khi có đủ bài cho trang sau — nút dẫn tới trang trống là nói dối. */
		echo '<div class="the thanh">';
		if ( $trang > 1 ) {
			echo '<a class="nut" href="' . esc_url( add_query_arg(
				array( 'nhom' => $nhom, 'tr' => $trang - 1 ), self::url() ) ) . '">← Mới hơn</a>';
		}
		if ( count( $ds ) >= VHNB_Bai::MOI_TRANG ) {
			echo '<a class="nut" href="' . esc_url( add_query_arg(
				array( 'nhom' => $nhom, 'tr' => $trang + 1 ), self::url() ) ) . '">Cũ hơn →</a>';
		}
		echo '</div>';
	}

	private static function dau() {
		return '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<meta name="robots" content="noindex, nofollow"><title>Nội bộ K&amp;H</title><style>'
			. ':root{--nen:#f1f5f9;--the:#fff;--vien:#e2e8f0;--chu:#0f172a;--mo:#64748b;'
			. '--xanh:#2563eb;--do:#dc2626;--luc:#16a34a}'
			. '*{box-sizing:border-box}body{margin:0;font:15px/1.6 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;'
			. 'background:var(--nen);color:var(--chu)}'
			. '.bo{max-width:760px;margin:0 auto;padding:16px}'
			. 'header{background:var(--the);border-bottom:1px solid var(--vien);position:sticky;top:0;z-index:5}'
			. 'header .bo{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:12px 16px}'
			. '.hieu{flex:1;font-size:16px;text-decoration:none;color:var(--chu)}.hieu b{color:var(--xanh)}'
			. '.the{background:var(--the);border:1px solid var(--vien);border-radius:10px;padding:14px;margin:0 0 14px}'
			. '.the h2{font-size:16px;margin:0 0 6px}'
			. '.mo{color:var(--mo);font-size:13px}'
			. 'label{display:block;font-size:13px;color:var(--mo);margin:0 0 3px}'
			. 'input,select,textarea{font:inherit;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;'
			. 'background:#fff;color:var(--chu);max-width:100%}'
			. 'textarea{resize:vertical}'
			. '.hang{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}'
			. 'button{font:inherit;font-weight:600;padding:8px 14px;border-radius:8px;border:1px solid #cbd5e1;'
			. 'background:#fff;color:var(--chu);cursor:pointer}'
			. 'button.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			. 'button.nho{padding:5px 10px;font-size:13px}'
			. 'button.nguy{color:var(--do);border-color:#fecaca}'
			. '.nut{display:inline-block;font-size:14px;font-weight:600;padding:7px 12px;border-radius:8px;'
			. 'border:1px solid #cbd5e1;background:#fff;color:var(--chu);text-decoration:none}'
			. '.nut.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			. '.thanh{display:flex;gap:8px;flex-wrap:wrap;padding:10px}'
			. '.bao{border-radius:9px;padding:11px 13px;margin:0 0 12px;border:1px solid}'
			. '.bao.ok{background:#f0fdf4;border-color:#bbf7d0}.bao.loi{background:#fef2f2;border-color:#fecaca}'
			. '.dau-bai{display:flex;align-items:center;gap:6px;flex-wrap:wrap}'
			. '.dau-bai .luc{margin-left:auto}'
			. '.duoi{background:#e0e7ff;color:#3730a3;border-radius:4px;padding:0 6px;font-size:11px;font-weight:600}'
			. '.duoi.nhom{background:#dcfce7;color:#166534}.duoi.ghim{background:#fef3c7;color:#92400e}'
			. '.nd{margin:8px 0;white-space:pre-wrap;word-break:break-word}'
			. '.chan{border-top:1px solid var(--vien);padding-top:8px;align-items:center}'
			. '.bl{background:#f8fafc;border-radius:8px;padding:8px 10px;margin-top:6px;font-size:14px;'
			. 'word-break:break-word}'
			/* Điện thoại: ô nhập đủ 16px, kẻo iPhone tự phóng to cả trang mỗi lần bấm vào ô. */
			. '@media(max-width:640px){.bo{padding:10px}input,select,textarea{font-size:16px}}'
			. '</style></head><body>';
	}
}
