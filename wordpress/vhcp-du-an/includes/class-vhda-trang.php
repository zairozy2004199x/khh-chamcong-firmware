<?php
/**
 * TRANG /du-an — QUY TRÌNH CÔNG VIỆC.
 *
 * 🔴 DÙNG CHUNG PHIÊN VỚI CẢ HỆ. Cắm vào `VHCC_Phien` (plugin Chấm công) — xem
 *    `docs/TRANG-MOI-DUNG-CHUNG-DANG-NHAP.md`. Không cấp tài khoản WordPress cho ai: 240 người
 *    mà cấp tài khoản WordPress là cấp 240 đường vào phần quản trị website.
 *
 * ⚠️ KHÔNG CÓ JAVASCRIPT — giống mọi trang khác của hệ. Mọi thao tác là một lượt POST rồi
 *    chuyển hướng. Đổi lại nó chạy được trên mọi máy, và thử được bằng bộ thử PHP.
 *
 * 🔴 `phuc_vu()` KHÔNG `exit`. Có `exit` trong đó thì bài kiểm gọi nó là bài kiểm tự chết giữa
 *    đường — nên toàn bộ phần vẽ trang sẽ không bao giờ có phép thử nào. `hien_trang()` mới là
 *    chỗ gác cửa và exit.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHDA_Trang {

	const SLUG_MD = 'du-an';
	/** Không gian chữ ký biểu mẫu — đặt một lần, ĐỪNG ĐỔI (xem `VHCC_Phien::chu_ky`). */
	const NS = 'vhda';

	public static function slug() {
		$s = get_option( 'vhda_slug' );
		$s = $s ? sanitize_title( $s ) : self::SLUG_MD;
		return $s ? $s : self::SLUG_MD;
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhda', '1', home_url( '/' ) );
	}

	public static function url_da( $ma ) { return add_query_arg( 'da', (string) $ma, self::url() ); }

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhda_trang=1', 'top' );
		add_filter( 'query_vars', function ( $v ) { $v[] = 'vhda_trang'; return $v; } );
		add_action( 'template_redirect', array( __CLASS__, 'hien_trang' ) );
	}

	public static function nen_ve() {
		if ( (int) get_query_var( 'vhda_trang' ) === 1 ) { return true; }
		return isset( $_GET['vhda'] );
	}

	public static function hien_trang() {
		if ( ! self::nen_ve() ) { return; }
		nocache_headers();
		self::phuc_vu();
		exit;
	}

	/* ==================================================================== phiên */

	public static function co_he_phien() {
		return class_exists( 'VHCC_Phien' ) && method_exists( 'VHCC_Phien', 'toi' )
			&& method_exists( 'VHCC_Phien', 'xu_post' );
	}

	/* ═══════════════════════════════════════════════════════════════════════════════════════
	 * BỌC MỌI LỜI GỌI SANG `VHCC_Phien` VÀO NĂM HÀM NGẮN Ở ĐÂY.
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * Luật của `tools/test/kiem-goi-cheo.php`: cái gác `method_exists` phải nằm CÙNG THÂN HÀM
	 * với lời gọi. Mà `o_ky()` được gọi ở cả chục chỗ vẽ khác nhau — rải mười cái gác giống hệt
	 * nhau ra mười chỗ thì sớm muộn một chỗ quên, và chỗ quên ấy là Fatal error, TRẮNG CẢ TRANG.
	 *
	 * Bọc lại: mỗi hàm một cái gác, và mọi nơi khác gọi vào đây.
	 *
	 * ⚠️ Thiếu lớp ấy thì trả về CHUỖI RỖNG / FALSE — nghĩa là biểu mẫu mất chữ ký và bị chối,
	 *    chứ không phải được cho qua. Hỏng thì hỏng về phía ĐÓNG.
	 */
	private static function o_ky() {
		if ( ! class_exists( 'VHCC_Phien' ) || ! method_exists( 'VHCC_Phien', 'o_ky' ) ) { return ''; }
		return VHCC_Phien::o_ky( self::NS );
	}

	private static function ky_dung() {
		if ( ! class_exists( 'VHCC_Phien' ) || ! method_exists( 'VHCC_Phien', 'ky_dung' ) ) { return false; }
		return VHCC_Phien::ky_dung( self::NS );
	}

	private static function nut_thoat() {
		if ( ! class_exists( 'VHCC_Phien' ) || ! method_exists( 'VHCC_Phien', 'nut_thoat' ) ) { return ''; }
		return VHCC_Phien::nut_thoat( self::NS );
	}

	private static function o_pin( $loi ) {
		if ( ! class_exists( 'VHCC_Phien' ) || ! method_exists( 'VHCC_Phien', 'o_pin' ) ) {
			return '<p class="mo">Bản plugin Chấm công đang cài chưa mở ô đăng nhập dùng chung. '
				. 'Nhờ quản trị cập nhật nó.</p>';
		}
		return VHCC_Phien::o_pin( array( 'loi' => (string) $loi ) );
	}

	private static function xu_post_phien() {
		if ( ! class_exists( 'VHCC_Phien' ) || ! method_exists( 'VHCC_Phien', 'xu_post' ) ) {
			return array( 'viec' => '', 'ok' => false, 'loi' => '' );
		}
		return VHCC_Phien::xu_post( self::NS );
	}

	public static function toi() {
		if ( ! class_exists( 'VHCC_Phien' ) || ! method_exists( 'VHCC_Phien', 'toi' ) ) { return null; }
		return VHCC_Phien::toi();
	}

	/* ==================================================================== phục vụ */

	public static function phuc_vu() {
		if ( ! self::co_he_phien() ) {
			echo self::dau();
			echo '<div class="bo"><div class="bao loi"><b>Chưa cài plugin Chấm công.</b> Trang này dùng '
				. 'chung mã PIN với hệ chấm công, nên phải có plugin đó thì mới đăng nhập được.</div></div>';
			echo self::chan();
			return;
		}

		/* Lõi phiên xử cả lượt gõ PIN lẫn lượt bấm Thoát — xem `VHCC_Phien::xu_post`. */
		$phien = self::xu_post_phien();
		if ( ( 'vao' === $phien['viec'] && ! empty( $phien['ok'] ) ) || 'ra' === $phien['viec'] ) {
			wp_safe_redirect( self::url() );
			return;
		}

		$toi = self::toi();
		if ( ! $toi ) {
			echo self::dau();
			echo '<div class="bo" style="max-width:460px;padding-top:52px"><div class="the">';
			echo '<h2 style="margin:0 0 6px">Quy trình công việc</h2>';
			echo '<p class="mo" style="margin:0 0 14px">Nhận hợp đồng · lên phương án · chốt ngày thi công '
				. '· bàn giao bộ phận · theo tiến độ tới ngày mở cửa.</p>';
			echo self::o_pin( (string) $phien['loi'] );
			echo '</div></div>';
			echo self::chan();
			return;
		}

		/* Việc ghi: xử TRƯỚC khi vẽ, rồi chuyển hướng — POST → GET nên F5 không gửi lại. */
		$viec = isset( $_POST['viec'] ) ? sanitize_text_field( wp_unslash( $_POST['viec'] ) ) : '';
		if ( '' !== $viec && self::ky_dung() ) {
			$bao = self::lam_viec( $viec, $toi );
			set_transient( self::khoa_bao(), $bao, 120 );
			$di = ! empty( $bao['ma'] ) ? self::url_da( $bao['ma'] ) : self::url_hien();
			wp_safe_redirect( $di );
			return;
		}
		if ( '' !== $viec ) {
			set_transient( self::khoa_bao(),
				array( 'loi' => 'Phiên đã hết hoặc biểu mẫu không hợp lệ. Tải lại trang rồi làm lại.' ), 120 );
			wp_safe_redirect( self::url_hien() );
			return;
		}

		self::ve( $toi );
	}

	private static function khoa_bao() {
		$tok = method_exists( 'VHCC_Phien', 'the' ) ? VHCC_Phien::the() : '';
		return 'vhda_bao_' . md5( (string) $tok );
	}

	private static function url_hien() {
		$ma = isset( $_POST['da_xem'] ) ? sanitize_text_field( wp_unslash( $_POST['da_xem'] ) ) : '';
		if ( '' === $ma && isset( $_GET['da'] ) ) { $ma = sanitize_text_field( wp_unslash( $_GET['da'] ) ); }
		return ( '' !== $ma ) ? self::url_da( $ma ) : self::url();
	}

	/**
	 * MỘT LƯỢT GHI. Hàm thuần theo nghĩa: không `echo`, không chuyển hướng — trả về câu báo.
	 * Nhờ vậy bài kiểm gọi thẳng được từng việc, không phải giả một lượt HTTP đủ đầu đủ đuôi.
	 */
	private static function lam_viec( $viec, $toi ) {
		$p = function ( $k ) {
			return isset( $_POST[ $k ] ) ? trim( (string) wp_unslash( $_POST[ $k ] ) ) : '';
		};
		$ma = $p( 'ma' );

		if ( 'lap' === $viec ) {
			$r = VHDA_DuAn::lap( $toi, array(
				'ten' => $p( 'ten' ), 'coso' => $p( 'coso' ), 'khach' => $p( 'khach' ),
				'so_hop_dong' => $p( 'so_hop_dong' ), 'gia_tri' => (int) preg_replace( '/\D/', '', $p( 'gia_tri' ) ),
			) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['loi'] )
				: array( 'xong' => 'Đã lập dự án.', 'ma' => $r['ma'] );
		}
		if ( 'sua' === $viec ) {
			$r = VHDA_DuAn::sua( $toi, $ma, array(
				'ten' => $p( 'ten' ), 'coso' => $p( 'coso' ), 'khach' => $p( 'khach' ),
				'so_hop_dong' => $p( 'so_hop_dong' ), 'phuong_an' => $p( 'phuong_an' ),
				'gia_tri' => (int) preg_replace( '/\D/', '', $p( 'gia_tri' ) ),
			) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['loi'], 'ma' => $ma )
				: array( 'xong' => 'Đã lưu.', 'ma' => $ma );
		}
		if ( 'chot_ngay' === $viec ) {
			$r = VHDA_DuAn::chot_ngay( $toi, $ma, $p( 'ngay_thi_cong' ), $p( 'ngay_mo_cua' ) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['loi'], 'ma' => $ma )
				: array( 'xong' => 'Đã chốt ngày.', 'ma' => $ma );
		}
		if ( 'chuyen' === $viec ) {
			$r = VHDA_DuAn::chuyen( $toi, $ma, $p( 'den' ), $p( 'ghi_chu' ) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['loi'], 'ma' => $ma )
				: array( 'xong' => 'Đã chuyển sang "' . VHDA_Luong::ten( $r['chang'] ) . '".', 'ma' => $ma );
		}
		if ( 'giao' === $viec ) {
			$r = VHDA_DuAn::giao( $toi, $ma, $p( 'bo_phan' ), $p( 'noi_dung' ), $p( 'han' ) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['loi'], 'ma' => $ma )
				: array( 'xong' => 'Đã bàn giao cho ' . $p( 'bo_phan' ) . '.', 'ma' => $ma );
		}
		if ( 'tien_do' === $viec ) {
			$r = VHDA_DuAn::tien_do( $toi, (int) $p( 'viec_id' ), (int) $p( 'phan_tram' ), $p( 'ghi_chu' ) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['loi'], 'ma' => $ma )
				: array( 'xong' => 'Đã cập nhật tiến độ.', 'ma' => $ma );
		}
		if ( 'gan_don' === $viec ) {
			$r = VHDA_Tien::gan_don( $toi, $ma, $p( 'ma_don' ) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['loi'], 'ma' => $ma )
				: array( 'xong' => 'Đã gán đơn.', 'ma' => $ma );
		}
		if ( 'bo_don' === $viec ) {
			$r = VHDA_Tien::bo_don( $toi, $ma, $p( 'ma_don' ) );
			return empty( $r['ok'] ) ? array( 'loi' => $r['loi'], 'ma' => $ma )
				: array( 'xong' => 'Đã gỡ đơn khỏi dự án.', 'ma' => $ma );
		}
		return array( 'loi' => 'Không biết việc "' . $viec . '".' );
	}

	/* ==================================================================== vẽ */

	public static function ve( $toi ) {
		$ma = isset( $_GET['da'] ) ? sanitize_text_field( wp_unslash( $_GET['da'] ) ) : '';

		echo self::dau();
		echo '<header><div class="bo">'
			. '<a class="hieu" href="' . esc_url( self::url() ) . '"><b>K&amp;H</b> Quy trình công việc</a>'
			. '<span class="ai">' . esc_html( (string) $toi['name'] ) . '</span>';
		/* ⚠️ Gác `method_exists` cùng thân hàm với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( class_exists( 'VHNB_Trang' ) && method_exists( 'VHNB_Trang', 'url' ) ) {
			echo '<a class="nut" href="' . esc_url( VHNB_Trang::url() ) . '">🏠 Nội bộ</a>';
		}
		if ( class_exists( 'VHCP_App' ) && method_exists( 'VHCP_App', 'app_url' ) ) {
			echo '<a class="nut" href="' . esc_url( VHCP_App::app_url() ) . '">💰 Chi phí</a>';
		}
		echo self::nut_thoat();
		echo '</div></header>';

		/* 🔴 CHỐT "AI ĐƯỢC VÀO" ĐỨNG NGAY SAU CHỐT ĐĂNG NHẬP, trước mọi thứ khác. Đặt sau là đã
		   lỡ vẽ dữ liệu ra rồi mới chối — mà nội dung thì đã nằm trong HTML gửi xuống máy. */
		if ( ! VHDA_Quyen::duoc( $toi, 'xem' ) ) {
			echo '<div class="bo"><div class="the" style="max-width:520px;margin:40px auto">'
				. '<h2>Chưa mở cho vai này</h2><p class="mo">'
				. esc_html( VHDA_Quyen::vi_sao_khong( $toi, 'xem' ) ) . '</p></div></div>';
			echo self::chan();
			return;
		}

		echo '<div class="bo">';
		$bao = get_transient( self::khoa_bao() );
		if ( is_array( $bao ) ) {
			delete_transient( self::khoa_bao() );
			if ( ! empty( $bao['loi'] ) )  { echo '<div class="bao loi">' . esc_html( $bao['loi'] ) . '</div>'; }
			if ( ! empty( $bao['xong'] ) ) { echo '<div class="bao ok">' . esc_html( $bao['xong'] ) . '</div>'; }
		}

		if ( '' !== $ma ) {
			$d = VHDA_DuAn::mot( $ma );
			if ( ! $d ) { echo '<div class="bao loi">Không tìm thấy dự án này.</div>'; self::ds_du_an( $toi ); }
			else { self::mot_du_an( $toi, $d ); }
		} else {
			self::ds_du_an( $toi );
		}
		echo '</div>';
		echo self::chan();
	}

	/** Vạch chặng — nhìn một cái là biết dự án đang ở đâu và còn mấy chặng nữa. */
	private static function vach_chang( $chang ) {
		$h = '<div class="vach">';
		if ( VHDA_Luong::HUY === $chang ) {
			$h .= '<span class="ch huy">Đã huỷ</span>';
		} else {
			$i = VHDA_Luong::vi_tri( $chang );
			foreach ( VHDA_Luong::DAY as $k => $c ) {
				$lop = ( $k < $i ) ? 'qua' : ( ( $k === $i ) ? 'day' : '' );
				$h  .= '<span class="ch ' . $lop . '">' . esc_html( VHDA_Luong::ten( $c ) ) . '</span>';
			}
		}
		return $h . '</div>';
	}

	private static function ds_du_an( $toi ) {
		$loc_chang = isset( $_GET['chang'] ) ? sanitize_text_field( wp_unslash( $_GET['chang'] ) ) : '';
		$ds = VHDA_DuAn::ds( VHDA_Luong::co( $loc_chang ) ? array( 'chang' => $loc_chang ) : array() );

		echo '<div class="the"><h2 style="margin:0 0 10px">Dự án</h2>';
		echo '<p style="margin:0 0 12px;display:flex;gap:6px;flex-wrap:wrap">';
		echo '<a class="nut' . ( '' === $loc_chang ? ' chinh' : '' ) . '" href="'
			. esc_url( self::url() ) . '">Tất cả</a>';
		foreach ( VHDA_Luong::DAY as $c ) {
			echo '<a class="nut' . ( $loc_chang === $c ? ' chinh' : '' ) . '" href="'
				. esc_url( add_query_arg( 'chang', $c, self::url() ) ) . '">'
				. esc_html( VHDA_Luong::ten( $c ) ) . '</a>';
		}
		echo '</p>';

		if ( ! count( $ds ) ) {
			echo '<p class="mo">Chưa có dự án nào ở đây.</p>';
		} else {
			echo '<div class="cuon"><table><thead><tr><th>Dự án</th><th>Cơ sở</th><th>Chặng</th>'
				. '<th>Thi công</th><th>Mở cửa</th><th>Tiến độ</th></tr></thead><tbody>';
			foreach ( $ds as $d ) {
				$viec = VHDA_DuAn::viec_cua( (int) $d['id'] );
				$td   = VHDA_DuAn::tien_do_chung( $viec );
				echo '<tr><td><a href="' . esc_url( self::url_da( $d['ma'] ) ) . '"><b>'
					. esc_html( (string) $d['ten'] ) . '</b></a>'
					. ( '' !== trim( (string) $d['khach'] ) ? '<br><span class="mo">'
						. esc_html( (string) $d['khach'] ) . '</span>' : '' ) . '</td>';
				echo '<td>' . esc_html( (string) $d['coso'] ) . '</td>';
				echo '<td><span class="nhan">' . esc_html( VHDA_Luong::ten( $d['chang'] ) ) . '</span></td>';
				echo '<td>' . esc_html( (string) $d['ngay_thi_cong'] ) . '</td>';
				echo '<td>' . esc_html( (string) $d['ngay_mo_cua'] ) . '</td>';
				/* 🔴 CHƯA BÀN GIAO CHO AI thì ghi thẳng "chưa giao", KHÔNG ghi 0%. Hai thứ ấy
				   khác hẳn nhau, và hiện lẫn lộn thì sếp nhìn bảng tưởng cả phòng ngồi chơi. */
				echo '<td>' . ( null === $td ? '<span class="mo">chưa giao</span>'
					: (int) $td . '%' ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';

		if ( VHDA_Quyen::duoc( $toi, 'lap' ) ) {
			echo '<div class="the"><h3 style="margin:0 0 10px">Lập dự án mới</h3>';
			echo '<form method="post">' . self::o_ky()
				. '<input type="hidden" name="viec" value="lap">'
				. '<div class="hang">'
				. '<label>Tên dự án<input name="ten" required placeholder="VD: Gian hàng GO Dĩ An"></label>'
				. '<label>Cơ sở<input name="coso" placeholder="VD: GO DĨ AN"></label>'
				. '<label>Khách hàng<input name="khach"></label>'
				. '<label>Số hợp đồng<input name="so_hop_dong"></label>'
				. '<label>Giá trị (đ)<input name="gia_tri" inputmode="numeric"></label>'
				. '<button class="chinh" type="submit">Lập dự án</button>'
				. '</div></form></div>';
		}
	}

	private static function mot_du_an( $toi, $d ) {
		$id    = (int) $d['id'];
		$chang = (string) $d['chang'];
		$viec  = VHDA_DuAn::viec_cua( $id );
		$td    = VHDA_DuAn::tien_do_chung( $viec );

		echo '<div class="the">';
		echo '<p style="margin:0 0 6px"><a href="' . esc_url( self::url() ) . '">← Mọi dự án</a></p>';
		echo '<h2 style="margin:0 0 4px">' . esc_html( (string) $d['ten'] ) . '</h2>';
		echo '<p class="mo" style="margin:0 0 10px">'
			. esc_html( (string) $d['ma'] )
			. ( '' !== trim( (string) $d['coso'] ) ? ' · ' . esc_html( (string) $d['coso'] ) : '' )
			. ( '' !== trim( (string) $d['khach'] ) ? ' · ' . esc_html( (string) $d['khach'] ) : '' )
			. ( '' !== trim( (string) $d['so_hop_dong'] ) ? ' · HĐ ' . esc_html( (string) $d['so_hop_dong'] ) : '' )
			. '</p>';
		echo self::vach_chang( $chang );
		/* Nói ra VIỆC TIẾP THEO ngay dưới vạch chặng — người mở màn hình cần biết phải làm gì,
		   chứ không phải tự suy ra từ tên chặng. */
		$cho = VHDA_Luong::cho( $chang );
		if ( '' !== $cho ) { echo '<p class="mo" style="margin:8px 0 0">→ ' . esc_html( $cho ) . '</p>'; }
		echo '<p class="mo" style="margin:6px 0 0">Thi công: <b>'
			. esc_html( '' !== (string) $d['ngay_thi_cong'] ? (string) $d['ngay_thi_cong'] : '— chưa chốt —' )
			. '</b> · Mở cửa: <b>'
			. esc_html( '' !== (string) $d['ngay_mo_cua'] ? (string) $d['ngay_mo_cua'] : '— chưa chốt —' )
			. '</b> · Tiến độ chung: <b>'
			. ( null === $td ? 'chưa giao bộ phận nào' : (int) $td . '%' ) . '</b></p>';
		echo '</div>';

		/* ---------- chuyển chặng ---------- */
		if ( VHDA_Quyen::duoc( $toi, 'chuyen' ) ) {
			echo '<div class="the"><h3 style="margin:0 0 10px">Chuyển chặng</h3>';
			$ke = VHDA_Luong::ke_tiep( $chang );
			echo '<div class="hang">';
			if ( '' !== $ke ) {
				echo '<form method="post" style="margin:0">' . self::o_ky()
					. '<input type="hidden" name="viec" value="chuyen">'
					. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
					. '<input type="hidden" name="den" value="' . esc_attr( $ke ) . '">'
					. '<button class="chinh" type="submit">→ ' . esc_html( VHDA_Luong::ten( $ke ) )
					. '</button></form>';
			}
			/* Lùi: cho chọn chặng nào trước đó cũng được — thực tế hay có (khách đổi ý), và bắt
			   lùi từng bước là bắt bấm bốn lần cho một việc. */
			$i = VHDA_Luong::vi_tri( $chang );
			if ( $i > 0 ) {
				echo '<form method="post" style="margin:0">' . self::o_ky()
					. '<input type="hidden" name="viec" value="chuyen">'
					. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
					. '<select name="den">';
				for ( $k = 0; $k < $i; $k++ ) {
					echo '<option value="' . esc_attr( VHDA_Luong::DAY[ $k ] ) . '">'
						. esc_html( VHDA_Luong::ten( VHDA_Luong::DAY[ $k ] ) ) . '</option>';
				}
				echo '</select> <button type="submit">← Lùi về</button></form>';
			}
			if ( VHDA_Quyen::duoc( $toi, 'huy' ) && VHDA_Luong::HUY !== $chang ) {
				echo '<form method="post" style="margin:0">' . self::o_ky()
					. '<input type="hidden" name="viec" value="chuyen">'
					. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
					. '<input type="hidden" name="den" value="' . esc_attr( VHDA_Luong::HUY ) . '">'
					. '<button class="nguy" type="submit">Huỷ dự án</button></form>';
			}
			if ( VHDA_Luong::HUY === $chang ) {
				echo '<form method="post" style="margin:0">' . self::o_ky()
					. '<input type="hidden" name="viec" value="chuyen">'
					. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
					. '<input type="hidden" name="den" value="">'
					. '<button type="submit">Mở lại dự án</button></form>';
			}
			echo '</div></div>';
		}

		/* ---------- phương án + chốt ngày ---------- */
		if ( VHDA_Quyen::duoc( $toi, 'lap' ) ) {
			echo '<div class="the"><h3 style="margin:0 0 10px">Phương án &amp; hợp đồng</h3>';
			echo '<form method="post">' . self::o_ky()
				. '<input type="hidden" name="viec" value="sua">'
				. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
				. '<div class="hang">'
				. '<label>Tên dự án<input name="ten" value="' . esc_attr( (string) $d['ten'] ) . '"></label>'
				. '<label>Cơ sở<input name="coso" value="' . esc_attr( (string) $d['coso'] ) . '"></label>'
				. '<label>Khách hàng<input name="khach" value="' . esc_attr( (string) $d['khach'] ) . '"></label>'
				. '<label>Số hợp đồng<input name="so_hop_dong" value="'
					. esc_attr( (string) $d['so_hop_dong'] ) . '"></label>'
				. '<label>Giá trị (đ)<input name="gia_tri" inputmode="numeric" value="'
					. esc_attr( (string) (int) $d['gia_tri'] ) . '"></label>'
				. '</div>'
				. '<label style="display:block;margin-top:8px">Phương án'
				. '<textarea name="phuong_an" rows="4" style="width:100%">'
				. esc_textarea( (string) $d['phuong_an'] ) . '</textarea></label>'
				. '<div class="hang" style="margin-top:8px"><button class="chinh" type="submit">Lưu</button></div>'
				. '</form></div>';
		}
		if ( VHDA_Quyen::duoc( $toi, 'chuyen' ) ) {
			echo '<div class="the"><h3 style="margin:0 0 10px">Chốt ngày</h3>';
			echo '<form method="post">' . self::o_ky()
				. '<input type="hidden" name="viec" value="chot_ngay">'
				. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
				. '<div class="hang">'
				. '<label>Ngày thi công<input type="date" name="ngay_thi_cong" value="'
					. esc_attr( (string) $d['ngay_thi_cong'] ) . '" required></label>'
				. '<label>Ngày mở cửa<input type="date" name="ngay_mo_cua" value="'
					. esc_attr( (string) $d['ngay_mo_cua'] ) . '" required></label>'
				. '<button class="chinh" type="submit">Chốt ngày</button>'
				. '</div></form></div>';
		}

		/* ---------- bàn giao bộ phận + tiến độ ---------- */
		echo '<div class="the"><h3 style="margin:0 0 10px">Bàn giao &amp; tiến độ từng bộ phận</h3>';
		if ( ! count( $viec ) ) {
			echo '<p class="mo">Chưa bàn giao cho bộ phận nào.</p>';
		} else {
			echo '<div class="cuon"><table><thead><tr><th>Bộ phận</th><th>Nội dung</th><th>Hạn</th>'
				. '<th>Tiến độ</th><th>Cập nhật</th></tr></thead><tbody>';
			foreach ( $viec as $v ) {
				$sua_duoc = ( '' === VHDA_Quyen::vi_sao_khong_sua_tien_do( $toi, (string) $v['bo_phan'] ) );
				echo '<tr><td><b>' . esc_html( (string) $v['bo_phan'] ) . '</b></td>';
				echo '<td>' . esc_html( (string) $v['noi_dung'] ) . '</td>';
				echo '<td>' . esc_html( (string) $v['han'] ) . '</td>';
				echo '<td><b>' . (int) $v['phan_tram'] . '%</b></td>';
				echo '<td>';
				if ( $sua_duoc ) {
					echo '<form method="post" style="margin:0;display:flex;gap:6px;align-items:center">'
						. self::o_ky()
						. '<input type="hidden" name="viec" value="tien_do">'
						. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
						. '<input type="hidden" name="viec_id" value="' . (int) $v['id'] . '">'
						. '<input name="phan_tram" inputmode="numeric" value="' . (int) $v['phan_tram']
						. '" style="width:70px"> %'
						. '<input name="ghi_chu" placeholder="ghi chú" style="width:150px">'
						. '<button type="submit">Lưu</button></form>';
				} else {
					/* Nói RÕ vì sao không sửa được — ô xám không lời giải thích thì người ta
					   tưởng hệ thống hỏng. */
					echo '<span class="mo">'
						. esc_html( VHDA_Quyen::vi_sao_khong_sua_tien_do( $toi, (string) $v['bo_phan'] ) )
						. '</span>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		if ( VHDA_Quyen::duoc( $toi, 'ban_giao' ) ) {
			echo '<form method="post" style="margin-top:10px">' . self::o_ky()
				. '<input type="hidden" name="viec" value="giao">'
				. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
				. '<div class="hang">'
				. '<label>Bộ phận<input name="bo_phan" list="vhda-bp" required placeholder="VD: Kỹ thuật"></label>'
				. '<label>Nội dung việc<input name="noi_dung" style="min-width:240px"></label>'
				. '<label>Hạn<input type="date" name="han"></label>'
				. '<button class="chinh" type="submit">Bàn giao</button></div></form>';
			echo self::datalist_bo_phan();
		}
		echo '</div>';

		self::the_tien( $toi, $d );
		self::the_nhat_ky( $id );
	}

	/** Gợi ý tên bộ phận — lấy từ hệ nhân sự, không gõ cứng một danh sách ở đây. */
	private static function datalist_bo_phan() {
		$ds = array();
		if ( class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'bo_phan_va_coso' ) ) {
			foreach ( (array) VHCC_NhanSu::bo_phan_va_coso() as $x ) {
				$bp = trim( (string) ( isset( $x['boPhan'] ) ? $x['boPhan'] : '' ) );
				if ( '' !== $bp ) { $ds[ $bp ] = true; }
			}
		}
		if ( ! count( $ds ) ) { return ''; }
		$h = '<datalist id="vhda-bp">';
		foreach ( array_keys( $ds ) as $bp ) { $h .= '<option value="' . esc_attr( $bp ) . '">'; }
		return $h . '</datalist>';
	}

	private static function the_tien( $toi, $d ) {
		$t = VHDA_Tien::tong( (int) $d['id'] );
		echo '<div class="the"><h3 style="margin:0 0 10px">Chi phí của dự án</h3>';
		if ( empty( $t['co'] ) ) {
			/* 🔴 KHÔNG HIỆN SỐ 0 khi không hỏi được — số 0 trông y như "chưa tiêu đồng nào". */
			echo '<p class="mo">Chưa cài plugin <b>Vận hành chi phí</b> trên site này, nên chưa gom được '
				. 'số tiền. Đây KHÔNG phải là "dự án chưa tiêu gì".</p>';
		} else {
			$so = VHDA_Tien::so_voi_hop_dong( (int) $d['gia_tri'], (int) $t['thucChi'] );
			echo '<p style="margin:0 0 10px">Tạm ứng <b>' . esc_html( number_format( (int) $t['tamUng'], 0, ',', '.' ) )
				. 'đ</b> · Đã chi <b>' . esc_html( number_format( (int) $t['thucChi'], 0, ',', '.' ) )
				. 'đ</b> · Còn lại <b>' . esc_html( number_format( (int) $t['conLai'], 0, ',', '.' ) ) . 'đ</b>';
			if ( ! empty( $so['co'] ) ) {
				echo ' · So hợp đồng: <b' . ( ! empty( $so['vuot'] ) ? ' class="chu-do"' : '' ) . '>'
					. (int) $so['phanTram'] . '%</b>'
					. ( ! empty( $so['vuot'] ) ? ' ⚠ vượt giá trị hợp đồng' : '' );
			}
			echo '</p>';
			if ( count( $t['thieu'] ) ) {
				echo '<div class="bao loi">Có ' . count( $t['thieu'] ) . ' đơn đã gán mà bên chi phí không '
					. 'còn thấy: <b>' . esc_html( implode( ', ', $t['thieu'] ) ) . '</b>. '
					. 'Gỡ chúng ra, không thì tổng bên trên thiếu đúng phần của mấy đơn ấy.</div>';
			}
			if ( count( $t['dong'] ) ) {
				echo '<div class="cuon"><table><thead><tr><th>Mã đơn</th><th>Kỳ</th><th>Cơ sở</th>'
					. '<th>Trạng thái</th><th>Tạm ứng</th><th>Đã chi</th><th></th></tr></thead><tbody>';
				foreach ( $t['dong'] as $x ) {
					echo '<tr><td>' . esc_html( $x['maDon'] ) . '</td><td>' . esc_html( $x['ky'] ) . '</td>'
						. '<td>' . esc_html( $x['coso'] ) . '</td><td>' . esc_html( $x['trangThai'] ) . '</td>'
						. '<td>' . esc_html( number_format( (int) $x['tamUng'], 0, ',', '.' ) ) . '</td>'
						. '<td>' . esc_html( number_format( (int) $x['thucChi'], 0, ',', '.' ) ) . '</td><td>';
					if ( VHDA_Quyen::duoc( $toi, 'gan_don' ) ) {
						echo '<form method="post" style="margin:0">' . self::o_ky()
							. '<input type="hidden" name="viec" value="bo_don">'
							. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
							. '<input type="hidden" name="ma_don" value="' . esc_attr( $x['maDon'] ) . '">'
							. '<button class="nguy" type="submit">Gỡ</button></form>';
					}
					echo '</td></tr>';
				}
				echo '</tbody></table></div>';
			} else {
				echo '<p class="mo">Chưa gán đơn chi phí nào vào dự án này.</p>';
			}
		}
		if ( VHDA_Quyen::duoc( $toi, 'gan_don' ) ) {
			echo '<form method="post" style="margin-top:10px">' . self::o_ky()
				. '<input type="hidden" name="viec" value="gan_don">'
				. '<input type="hidden" name="ma" value="' . esc_attr( (string) $d['ma'] ) . '">'
				. '<div class="hang"><label>Mã đơn chi phí<input name="ma_don" required placeholder="VD: D_abc123">'
				. '</label><button class="chinh" type="submit">Gán đơn</button></div></form>';
		}
		echo '</div>';
	}

	private static function the_nhat_ky( $id ) {
		$nk = VHDA_DuAn::nhat_ky_cua( $id, 60 );
		echo '<div class="the"><h3 style="margin:0 0 10px">Nhật ký</h3>';
		if ( ! count( $nk ) ) { echo '<p class="mo">Chưa có gì.</p></div>'; return; }
		echo '<div class="cuon"><table><thead><tr><th>Lúc</th><th>Việc</th><th>Chi tiết</th><th>Người</th>'
			. '</tr></thead><tbody>';
		foreach ( $nk as $x ) {
			$mo = (string) $x['viec'];
			if ( 'chuyen' === $mo ) {
				$mo = VHDA_Luong::ten( $x['tu_chang'] ) . ' → ' . VHDA_Luong::ten( $x['den_chang'] );
			} elseif ( '' !== trim( (string) $x['bo_phan'] ) ) {
				$mo .= ' · ' . (string) $x['bo_phan'];
			}
			echo '<tr><td class="mo">' . esc_html( (string) $x['luc'] ) . '</td>'
				. '<td>' . esc_html( $mo ) . '</td>'
				. '<td>' . esc_html( (string) $x['chi_tiet'] ) . '</td>'
				. '<td>' . esc_html( (string) $x['nguoi'] ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/* ==================================================================== khung */

	private static function dau() {
		return '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			/* Trang điều hành nội bộ — đừng để công cụ tìm kiếm ghé vào. */
			. '<meta name="robots" content="noindex, nofollow">'
			. '<title>Quy trình công việc · K&amp;H</title><style>' . self::css() . '</style></head><body>';
	}

	private static function chan() {
		$h = '<div class="bo"><footer class="cty">';
		/* ⚠️ Gác `method_exists` cùng thân hàm với lời gọi — chân trang công ty nằm ở plugin
		   Ghế; gỡ nó ra thì trang này vẫn phải đóng lại tử tế, chỉ mất mấy dòng thông tin. */
		if ( class_exists( 'VHG_Chan' ) && method_exists( 'VHG_Chan', 'html' ) ) {
			$h .= VHG_Chan::html();
		}
		$h .= '<div class="pb">Bản đang chạy: ' . esc_html( defined( 'VHDA_VERSION' ) ? VHDA_VERSION : '?' )
			. '</div></footer></div></body></html>';
		return $h;
	}

	private static function css() {
		return ':root{--nen:#f1f5f9;--the:#fff;--vien:#e2e8f0;--chu:#0f172a;--mo:#64748b;'
			. '--xanh:#2563eb;--do:#dc2626;--luc:#16a34a}'
			. '*{box-sizing:border-box}'
			. 'body{margin:0;font:15px/1.55 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;'
			. 'background:var(--nen);color:var(--chu)}'
			. '.bo{max-width:1180px;margin:0 auto;padding:16px}'
			. 'header{background:var(--the);border-bottom:1px solid var(--vien)}'
			. 'header .bo{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 16px}'
			. '.hieu{flex:1;font-size:17px;font-weight:700;text-decoration:none;color:var(--chu)}'
			. '.hieu b{color:var(--xanh)}'
			. '.ai{font-weight:600;font-size:14px;color:var(--mo)}'
			. '.the{background:var(--the);border:1px solid var(--vien);border-radius:12px;'
			. 'padding:14px;margin:0 0 14px}'
			. '.the h2{font-size:18px;margin:0 0 8px}.the h3{font-size:15px;margin:0 0 8px}'
			. '.mo{color:var(--mo);font-size:13px}'
			. 'a{color:var(--xanh)}'
			. 'label{display:block;font-size:13px;color:var(--mo)}'
			. 'input,select,textarea{font:inherit;padding:8px 10px;border:1px solid #cbd5e1;'
			. 'border-radius:8px;background:#fff;color:var(--chu);max-width:100%}'
			. 'button{font:inherit;font-weight:600;padding:8px 14px;border-radius:8px;'
			. 'border:1px solid #cbd5e1;background:#fff;color:var(--chu);cursor:pointer}'
			. 'button.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			. 'button.nguy{color:var(--do);border-color:#fecaca}'
			. '.nut{display:inline-block;font-size:14px;font-weight:600;padding:7px 12px;'
			. 'border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:var(--chu);'
			. 'text-decoration:none}'
			. '.nut.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			. '.hang{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}'
			. '.bao{border-radius:9px;padding:11px 13px;margin:0 0 12px;border:1px solid}'
			. '.bao.ok{background:#f0fdf4;border-color:#bbf7d0}'
			. '.bao.loi{background:#fef2f2;border-color:#fecaca}'
			. '.chu-do{color:var(--do)}'
			. '.nhan{background:#e0e7ff;color:#3730a3;border-radius:5px;padding:1px 7px;font-size:12px;'
			. 'font-weight:600;white-space:nowrap}'
			/* Bảng rộng thì CUỘN TRONG KHUNG CỦA NÓ, không đẩy cả trang trượt ngang. */
			. '.cuon{overflow-x:auto}'
			. 'table{width:100%;border-collapse:collapse;font-size:14px}'
			. 'th,td{padding:8px 10px;border-bottom:1px solid var(--vien);text-align:left;vertical-align:top}'
			. 'th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--mo)}'
			/* Vạch chặng: chặng đã qua màu lục, chặng đang đứng màu xanh đậm, chặng chưa tới màu mờ. */
			. '.vach{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0 0}'
			. '.ch{font-size:12px;font-weight:600;padding:4px 10px;border-radius:20px;'
			. 'background:#f1f5f9;color:#94a3b8;border:1px solid var(--vien)}'
			. '.ch.qua{background:#f0fdf4;color:#166534;border-color:#bbf7d0}'
			. '.ch.day{background:var(--xanh);color:#fff;border-color:var(--xanh)}'
			. '.ch.huy{background:#fef2f2;color:#991b1b;border-color:#fecaca}'
			. '.cty{margin:20px 0 0;padding:14px 0 24px;border-top:1px solid var(--vien);'
			. 'color:var(--mo);font-size:12.5px}'
			. '.pb{margin-top:10px;font-size:11px;color:#cbd5e1;'
			. 'font-family:ui-monospace,Menlo,Consolas,monospace}'
			. '@media(max-width:640px){.bo{padding:10px}input,select,textarea{font-size:16px}}';
	}
}
