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

	/**
	 * KHUNG HAI CỘT: cột trái điều hướng, cột phải nội dung.
	 *
	 * Anh Thắng 30/08/2026: *"Chuyển sang giao diện HRM trực quan"* — tức là mở lên nhìn một cái
	 * là biết đang có gì, chứ không phải đọc một cái bảng rồi tự cộng trong đầu.
	 *
	 * ⚠️ VẪN KHÔNG CÓ JAVASCRIPT. Cột trái là mấy cái link, bảng chặng là CSS grid, thẻ dự án là
	 *    thẻ HTML. Đổi lại nó chạy trên mọi máy, và thử được bằng bộ thử PHP.
	 */
	public static function ve( $toi, $man = '' ) {
		$ma = isset( $_GET['da'] ) ? sanitize_text_field( wp_unslash( $_GET['da'] ) ) : '';
		if ( '' === $man ) {
			$man = isset( $_GET['man'] ) ? sanitize_text_field( wp_unslash( $_GET['man'] ) ) : '';
		}

		echo self::dau();
		echo '<div class="khung">';
		self::cot_trai( $toi, $ma, $man );
		echo '<main class="phai">';

		/* 🔴 CHỐT "AI ĐƯỢC VÀO" ĐỨNG NGAY SAU CHỐT ĐĂNG NHẬP, trước mọi thứ khác. Đặt sau là đã
		   lỡ vẽ dữ liệu ra rồi mới chối — mà nội dung thì đã nằm trong HTML gửi xuống máy. */
		if ( ! VHDA_Quyen::duoc( $toi, 'xem' ) ) {
			echo '<div class="the" style="max-width:520px"><h2>Chưa mở cho vai này</h2><p class="mo">'
				. esc_html( VHDA_Quyen::vi_sao_khong( $toi, 'xem' ) ) . '</p></div>';
			echo '</main></div>' . self::chan();
			return;
		}

		$bao = get_transient( self::khoa_bao() );
		if ( is_array( $bao ) ) {
			delete_transient( self::khoa_bao() );
			if ( ! empty( $bao['loi'] ) )  { echo '<div class="bao loi">' . esc_html( $bao['loi'] ) . '</div>'; }
			if ( ! empty( $bao['xong'] ) ) { echo '<div class="bao ok">' . esc_html( $bao['xong'] ) . '</div>'; }
		}

		if ( '' !== $ma ) {
			$d = VHDA_DuAn::mot( $ma );
			if ( ! $d ) {
				echo '<div class="bao loi">Không tìm thấy dự án này.</div>';
				self::man_bang( $toi );
			} else {
				self::mot_du_an( $toi, $d );
			}
		} elseif ( 'ds' === $man ) {
			self::man_danh_sach( $toi );
		} elseif ( 'lap' === $man ) {
			self::man_lap( $toi );
		} else {
			self::man_bang( $toi );
		}

		echo '</main></div>' . self::chan();
	}

	/** Cột trái — người dùng, mấy màn chính, và đường sang các trang khác của hệ. */
	private static function cot_trai( $toi, $ma, $man ) {
		$ten  = (string) $toi['name'];
		$vai  = (string) ( isset( $toi['role'] ) ? $toi['role'] : '' );
		$chu  = mb_substr( trim( $ten ), 0, 1, 'UTF-8' );

		echo '<aside class="trai">';
		echo '<div class="hieu-o"><span class="hieu-ic">🗂</span>'
			. '<span><b>Dự án</b><small>Quy trình công việc</small></span></div>';

		echo '<div class="toi"><span class="chu-cai">' . esc_html( mb_strtoupper( $chu, 'UTF-8' ) ) . '</span>'
			. '<span class="toi-chu"><b>' . esc_html( $ten ) . '</b><small>' . esc_html( $vai )
			. '</small></span></div>';

		$dang_bang = ( '' === $ma && 'ds' !== $man && 'lap' !== $man );
		echo '<nav class="dh">';
		echo '<a class="mi' . ( $dang_bang ? ' on' : '' ) . '" href="' . esc_url( self::url() )
			. '"><span class="ic">📊</span>Bảng chặng</a>';
		echo '<a class="mi' . ( 'ds' === $man && '' === $ma ? ' on' : '' ) . '" href="'
			. esc_url( add_query_arg( 'man', 'ds', self::url() ) )
			. '"><span class="ic">📋</span>Danh sách</a>';
		if ( VHDA_Quyen::duoc( $toi, 'lap' ) ) {
			echo '<a class="mi' . ( 'lap' === $man ? ' on' : '' ) . '" href="'
				. esc_url( add_query_arg( 'man', 'lap', self::url() ) )
				. '"><span class="ic">➕</span>Lập dự án</a>';
		}
		echo '</nav>';

		echo '<div class="mi-nhan">Trang khác</div><nav class="dh">';
		/* ⚠️ Gác `method_exists` cùng thân hàm với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( class_exists( 'VHNB_Trang' ) && method_exists( 'VHNB_Trang', 'url' ) ) {
			echo '<a class="mi" href="' . esc_url( VHNB_Trang::url() ) . '"><span class="ic">🏠</span>Nội bộ</a>';
		}
		if ( class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'url' ) ) {
			echo '<a class="mi" href="' . esc_url( VHCC_Web::url() ) . '"><span class="ic">🕐</span>Chấm công</a>';
		}
		if ( class_exists( 'VHCP_App' ) && method_exists( 'VHCP_App', 'app_url' ) ) {
			echo '<a class="mi" href="' . esc_url( VHCP_App::app_url() ) . '"><span class="ic">💰</span>Chi phí</a>';
		}
		echo '</nav>';

		echo '<div class="thoat-o">' . self::nut_thoat() . '</div>';
		echo '</aside>';
	}

	/** Dải thẻ số — thứ nhìn đầu tiên mỗi sáng. */
	private static function dai_the( $ds, $viec ) {
		$t = VHDA_DuAn::tom_tat( $ds, $viec );
		$o = function ( $nhan, $so, $lop = '', $phu = '' ) {
			return '<div class="kpi ' . $lop . '"><span class="kpi-n">' . esc_html( $nhan ) . '</span>'
				. '<b class="kpi-s">' . esc_html( (string) $so ) . '</b>'
				. ( '' !== $phu ? '<span class="kpi-p">' . esc_html( $phu ) . '</span>' : '' ) . '</div>';
		};
		echo '<div class="dai">';
		echo $o( 'Đang chạy', $t['dang_chay'], 'xanh', $t['tong'] . ' dự án' );
		/* 🔴 "Sắp mở cửa" và "Trễ hạn" là hai con số ĐỂ HÀNH ĐỘNG, nên tô màu; mấy con số còn
		   lại chỉ để biết. Tô hết thì không cái nào nổi lên nữa. */
		echo $o( 'Mở cửa ≤7 ngày', $t['sap_mo'], $t['sap_mo'] > 0 ? 'cam' : '' );
		echo $o( 'Bộ phận trễ hạn', $t['tre'], $t['tre'] > 0 ? 'do' : '' );
		echo $o( 'Tiến độ trung bình',
			( null === $t['tien_do'] ? '—' : $t['tien_do'] . '%' ), '',
			null === $t['tien_do'] ? 'chưa giao việc' : '' );
		echo $o( 'Xong', $t['xong'], 'luc' );
		if ( $t['huy'] > 0 ) { echo $o( 'Đã huỷ', $t['huy'] ); }
		echo '</div>';
	}

	/** Đọc một lượt danh sách + phần việc của tất cả — dùng chung cho cả ba màn. */
	private static function doc_het( $loc = array() ) {
		$ds = VHDA_DuAn::ds( $loc );
		$viec = array();
		foreach ( $ds as $d ) { $viec[ (int) $d['id'] ] = VHDA_DuAn::viec_cua( (int) $d['id'] ); }
		return array( $ds, $viec );
	}

	/**
	 * THẺ MỘT DỰ ÁN — dùng ở cả bảng chặng lẫn danh sách.
	 *
	 * ⚠️ Mỗi thẻ nói đủ ba thứ người ta cần để quyết định có mở nó ra không: CÒN MẤY NGÀY tới
	 *    ngày mở cửa, TIẾN ĐỘ tới đâu, và bộ phận nào ĐANG TRỄ. Thiếu cái thứ ba thì phải mở
	 *    từng dự án ra mới biết chỗ nào đang cháy.
	 */
	private static function the_du_an( $d, $dsv ) {
		$td  = VHDA_DuAn::tien_do_chung( $dsv );
		$con = VHDA_DuAn::con_may_ngay( isset( $d['ngay_mo_cua'] ) ? $d['ngay_mo_cua'] : '' );
		$tre = array();
		foreach ( (array) $dsv as $v ) {
			if ( VHDA_DuAn::tre_han( $v ) ) { $tre[] = (string) $v['bo_phan']; }
		}

		$h = '<a class="dth" href="' . esc_url( self::url_da( $d['ma'] ) ) . '">';
		$h .= '<b class="dth-ten">' . esc_html( (string) $d['ten'] ) . '</b>';
		if ( '' !== trim( (string) $d['coso'] ) ) {
			$h .= '<span class="dth-cs">' . esc_html( (string) $d['coso'] ) . '</span>';
		}

		/* Đếm ngược tới ngày mở cửa. Quá ngày mà chưa xong thì nói thẳng "quá N ngày" — chứ
		   "−5" thì người đọc phải tự dịch. */
		if ( null !== $con ) {
			$lop = ( $con < 0 ) ? 'do' : ( ( $con <= 7 ) ? 'cam' : '' );
			$chu = ( $con < 0 ) ? ( 'quá ' . abs( $con ) . ' ngày' )
				: ( 0 === $con ? 'mở cửa hôm nay' : ( 'còn ' . $con . ' ngày' ) );
			$h .= '<span class="dth-ngay ' . $lop . '">🎬 ' . esc_html( $chu ) . '</span>';
		} else {
			$h .= '<span class="dth-ngay mo">chưa chốt ngày</span>';
		}

		/* Thanh tiến độ. CHƯA GIAO CHO AI thì KHÔNG vẽ thanh 0% — 0% trông như "đã giao mà cả
		   phòng ngồi chơi", còn thật ra là chưa giao. */
		if ( null === $td ) {
			$h .= '<span class="dth-td mo">chưa bàn giao bộ phận nào</span>';
		} else {
			$h .= '<span class="thanh"><span class="thanh-in" style="width:' . (int) $td . '%"></span></span>'
				. '<span class="dth-td">' . (int) $td . '% · ' . count( $dsv ) . ' bộ phận</span>';
		}
		if ( count( $tre ) ) {
			$h .= '<span class="dth-tre">⚠ trễ: ' . esc_html( implode( ', ', $tre ) ) . '</span>';
		}
		return $h . '</a>';
	}

	/**
	 * BẢNG CHẶNG — mỗi chặng một cột, dự án là thẻ nằm trong cột của nó.
	 *
	 * 🔴 ĐÂY LÀ MÀN CHÍNH. Câu hỏi đầu tiên mỗi sáng là "cái nào đang kẹt ở đâu", và một cái
	 *    bảng dòng-cột không trả lời được câu ấy — phải đọc hết cột Chặng rồi tự nhóm trong đầu.
	 *
	 * ⚠️ CỘT ĐÃ HUỶ chỉ hiện KHI CÓ dự án đã huỷ. Để nó đứng đó trống trơn quanh năm thì bảy cột
	 *    việc thật bị bóp hẹp lại vì một cột không có gì.
	 */
	private static function man_bang( $toi ) {
		list( $ds, $viec ) = self::doc_het();
		self::dai_the( $ds, $viec );

		$theo = array();
		foreach ( VHDA_Luong::DAY as $c ) { $theo[ $c ] = array(); }
		$theo[ VHDA_Luong::HUY ] = array();
		foreach ( $ds as $d ) {
			$c = (string) $d['chang'];
			if ( ! isset( $theo[ $c ] ) ) { $theo[ $c ] = array(); }
			$theo[ $c ][] = $d;
		}

		echo '<div class="bang">';
		foreach ( VHDA_Luong::DAY as $c ) {
			echo '<section class="cot"><header class="cot-dau">'
				. '<span>' . esc_html( VHDA_Luong::ten( $c ) ) . '</span>'
				. '<span class="dem">' . count( $theo[ $c ] ) . '</span></header>';
			if ( ! count( $theo[ $c ] ) ) {
				echo '<p class="trong">—</p>';
			} else {
				foreach ( $theo[ $c ] as $d ) {
					echo self::the_du_an( $d, isset( $viec[ (int) $d['id'] ] ) ? $viec[ (int) $d['id'] ] : array() );
				}
			}
			echo '</section>';
		}
		if ( count( $theo[ VHDA_Luong::HUY ] ) ) {
			echo '<section class="cot huy"><header class="cot-dau"><span>Đã huỷ</span>'
				. '<span class="dem">' . count( $theo[ VHDA_Luong::HUY ] ) . '</span></header>';
			foreach ( $theo[ VHDA_Luong::HUY ] as $d ) {
				echo self::the_du_an( $d, isset( $viec[ (int) $d['id'] ] ) ? $viec[ (int) $d['id'] ] : array() );
			}
			echo '</section>';
		}
		echo '</div>';

		if ( ! count( $ds ) ) {
			echo '<div class="the"><p class="mo">Chưa có dự án nào. '
				. ( VHDA_Quyen::duoc( $toi, 'lap' ) ? 'Bấm <b>Lập dự án</b> ở cột trái để bắt đầu.'
					: 'Chờ quản lý lập dự án và bàn giao xuống bộ phận.' ) . '</p></div>';
		}
	}

	/** Danh sách dạng bảng — cho ai quen đọc bảng, và để lọc theo chặng. */
	private static function man_danh_sach( $toi ) {
		$loc = isset( $_GET['chang'] ) ? sanitize_text_field( wp_unslash( $_GET['chang'] ) ) : '';
		list( $ds, $viec ) = self::doc_het( VHDA_Luong::co( $loc ) ? array( 'chang' => $loc ) : array() );
		self::dai_the( $ds, $viec );

		echo '<div class="the"><p style="margin:0 0 12px;display:flex;gap:6px;flex-wrap:wrap">';
		$u_ds = add_query_arg( 'man', 'ds', self::url() );
		echo '<a class="nut' . ( '' === $loc ? ' chinh' : '' ) . '" href="' . esc_url( $u_ds ) . '">Tất cả</a>';
		foreach ( VHDA_Luong::DAY as $c ) {
			echo '<a class="nut' . ( $loc === $c ? ' chinh' : '' ) . '" href="'
				. esc_url( add_query_arg( 'chang', $c, $u_ds ) ) . '">'
				. esc_html( VHDA_Luong::ten( $c ) ) . '</a>';
		}
		echo '</p>';

		if ( ! count( $ds ) ) {
			echo '<p class="mo">Không có dự án nào ở đây.</p></div>';
			return;
		}
		echo '<div class="cuon"><table><thead><tr><th>Dự án</th><th>Cơ sở</th><th>Chặng</th>'
			. '<th>Thi công</th><th>Mở cửa</th><th>Tiến độ</th></tr></thead><tbody>';
		foreach ( $ds as $d ) {
			$dsv = isset( $viec[ (int) $d['id'] ] ) ? $viec[ (int) $d['id'] ] : array();
			$td  = VHDA_DuAn::tien_do_chung( $dsv );
			echo '<tr><td><a href="' . esc_url( self::url_da( $d['ma'] ) ) . '"><b>'
				. esc_html( (string) $d['ten'] ) . '</b></a>'
				. ( '' !== trim( (string) $d['khach'] ) ? '<br><span class="mo">'
					. esc_html( (string) $d['khach'] ) . '</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) $d['coso'] ) . '</td>';
			echo '<td><span class="nhan">' . esc_html( VHDA_Luong::ten( $d['chang'] ) ) . '</span></td>';
			echo '<td>' . esc_html( (string) $d['ngay_thi_cong'] ) . '</td>';
			echo '<td>' . esc_html( (string) $d['ngay_mo_cua'] ) . '</td>';
			echo '<td>' . ( null === $td ? '<span class="mo">chưa giao</span>' : (int) $td . '%' ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	private static function man_lap( $toi ) {
		if ( ! VHDA_Quyen::duoc( $toi, 'lap' ) ) {
			echo '<div class="the"><p class="mo">'
				. esc_html( VHDA_Quyen::vi_sao_khong( $toi, 'lap' ) ) . '</p></div>';
			return;
		}
		echo '<div class="the"><h2 style="margin:0 0 10px">Lập dự án mới</h2>';
		echo '<form method="post">' . self::o_ky()
			. '<input type="hidden" name="viec" value="lap">'
			. '<div class="hang">'
			. '<label>Tên dự án<input name="ten" required placeholder="VD: Gian hàng GO Dĩ An"></label>'
			. '<label>Cơ sở<input name="coso" placeholder="VD: GO DĨ AN"></label>'
			. '<label>Khách hàng<input name="khach"></label>'
			. '<label>Số hợp đồng<input name="so_hop_dong"></label>'
			. '<label>Giá trị (đ)<input name="gia_tri" inputmode="numeric"></label>'
			. '<button class="chinh" type="submit">Lập dự án</button>'
			. '</div></form>'
			. '<p class="mo" style="margin:12px 0 0">Lập xong dự án nằm ở chặng <b>Nhận hợp đồng</b>. '
			. 'Các bước sau: lên phương án → chốt hai ngày → bàn giao xuống bộ phận.</p></div>';
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
			. '--xanh:#2563eb;--do:#dc2626;--cam:#ea580c;--luc:#16a34a;--toi:#0f172a}'
			. '*{box-sizing:border-box}'
			. 'body{margin:0;font:15px/1.55 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;'
			. 'background:var(--nen);color:var(--chu)}'
			. 'a{color:var(--xanh)}'

			/* ---------- khung hai cột ---------- */
			. '.khung{display:grid;grid-template-columns:236px minmax(0,1fr);min-height:100vh;align-items:start}'
			. '.trai{background:var(--toi);color:#e2e8f0;min-height:100vh;padding:14px 12px;'
			. 'position:sticky;top:0;display:flex;flex-direction:column;gap:6px}'
			. '.phai{padding:18px;min-width:0}'
			. '.hieu-o{display:flex;align-items:center;gap:10px;padding:4px 8px 12px}'
			. '.hieu-ic{font-size:22px}'
			. '.hieu-o b{display:block;font-size:16px;color:#fff;line-height:1.2}'
			. '.hieu-o small{color:#94a3b8;font-size:11.5px}'
			. '.toi{display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.06);'
			. 'border-radius:10px;padding:8px 10px;margin-bottom:6px}'
			/* Chữ cái đầu thay ảnh đại diện — hệ này không có ảnh nhân sự, mà một ô xám trống
			   thì trông như ảnh hỏng. */
			. '.chu-cai{width:32px;height:32px;border-radius:50%;background:var(--xanh);color:#fff;'
			. 'display:flex;align-items:center;justify-content:center;font-weight:700;flex:none}'
			. '.toi-chu{min-width:0}'
			. '.toi-chu b{display:block;font-size:13.5px;color:#fff;overflow:hidden;'
			. 'text-overflow:ellipsis;white-space:nowrap}'
			. '.toi-chu small{color:#94a3b8;font-size:11.5px}'
			. '.dh{display:flex;flex-direction:column;gap:2px}'
			. '.mi{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:9px;'
			. 'text-decoration:none;color:#cbd5e1;font-weight:600;font-size:13.5px}'
			. '.mi:hover{background:rgba(255,255,255,.07);color:#fff}'
			. '.mi.on{background:var(--xanh);color:#fff}'
			. '.ic{width:20px;text-align:center;flex:none}'
			. '.mi-nhan{font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;'
			. 'color:#64748b;margin:14px 0 4px;padding:0 10px}'
			. '.thoat-o{margin-top:auto;padding-top:12px}'
			. '.thoat-o button{width:100%;background:transparent;color:#cbd5e1;border-color:#334155}'

			/* ---------- dải thẻ số ---------- */
			. '.dai{display:grid;grid-template-columns:repeat(auto-fit,minmax(158px,1fr));gap:12px;'
			. 'margin:0 0 16px}'
			. '.kpi{background:var(--the);border:1px solid var(--vien);border-radius:12px;padding:12px 14px;'
			. 'display:flex;flex-direction:column;gap:2px}'
			. '.kpi-n{font-size:12px;color:var(--mo);font-weight:600}'
			. '.kpi-s{font-size:26px;line-height:1.15;font-weight:700}'
			. '.kpi-p{font-size:11.5px;color:#94a3b8}'
			/* Chỉ hai con số ĐỂ HÀNH ĐỘNG mới được tô màu — tô hết thì không cái nào nổi lên. */
			. '.kpi.xanh{border-left:4px solid var(--xanh)}'
			. '.kpi.cam{border-left:4px solid var(--cam)}.kpi.cam .kpi-s{color:var(--cam)}'
			. '.kpi.do{border-left:4px solid var(--do)}.kpi.do .kpi-s{color:var(--do)}'
			. '.kpi.luc{border-left:4px solid var(--luc)}'

			/* ---------- bảng chặng ---------- */
			/* Cuộn NGANG trong khung của nó, không đẩy cả trang trượt. Bảy cột trên màn hẹp thì
			   phải cuộn — ép chúng co lại thì thẻ nào cũng vỡ chữ. */
			. '.bang{display:flex;gap:12px;overflow-x:auto;padding-bottom:8px;align-items:flex-start}'
			. '.cot{flex:0 0 246px;background:#e9eef5;border-radius:12px;padding:10px;'
			. 'display:flex;flex-direction:column;gap:8px}'
			. '.cot.huy{background:#fdeaea}'
			. '.cot-dau{display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;'
			. 'text-transform:uppercase;letter-spacing:.02em;color:#475569;padding:2px 4px}'
			. '.cot-dau .dem{margin-left:auto;background:#fff;border-radius:9px;padding:1px 8px;'
			. 'font-size:12px;color:var(--mo)}'
			. '.trong{margin:0;padding:8px 4px;color:#94a3b8;font-size:13px}'
			. '.dth{display:flex;flex-direction:column;gap:4px;background:var(--the);'
			. 'border:1px solid var(--vien);border-radius:10px;padding:10px 11px;text-decoration:none;'
			. 'color:var(--chu)}'
			. '.dth:hover{border-color:#94a3b8;box-shadow:0 2px 8px rgba(15,23,42,.07)}'
			. '.dth-ten{font-size:14px;line-height:1.3}'
			. '.dth-cs{font-size:12px;color:var(--mo)}'
			. '.dth-ngay{font-size:12px;font-weight:600}'
			. '.dth-ngay.cam{color:var(--cam)}.dth-ngay.do{color:var(--do)}.dth-ngay.mo{color:#94a3b8;font-weight:400}'
			. '.dth-td{font-size:11.5px;color:var(--mo)}'
			. '.dth-td.mo{color:#94a3b8}'
			. '.dth-tre{font-size:11.5px;color:var(--do);font-weight:600}'
			. '.thanh{display:block;height:6px;border-radius:4px;background:#e2e8f0;overflow:hidden}'
			. '.thanh-in{display:block;height:100%;background:var(--luc)}'

			/* ---------- thẻ, biểu mẫu, bảng ---------- */
			. '.the{background:var(--the);border:1px solid var(--vien);border-radius:12px;'
			. 'padding:14px;margin:0 0 14px}'
			. '.the h2{font-size:18px;margin:0 0 8px}.the h3{font-size:15px;margin:0 0 8px}'
			. '.mo{color:var(--mo);font-size:13px}'
			. 'label{display:block;font-size:13px;color:var(--mo)}'
			. 'input,select,textarea{font:inherit;padding:8px 10px;border:1px solid #cbd5e1;'
			. 'border-radius:8px;background:#fff;color:var(--chu);max-width:100%}'
			. 'button{font:inherit;font-weight:600;padding:8px 14px;border-radius:8px;'
			. 'border:1px solid #cbd5e1;background:#fff;color:var(--chu);cursor:pointer}'
			. 'button.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			. 'button.nguy{color:var(--do);border-color:#fecaca}'
			. '.nut{display:inline-block;font-size:13.5px;font-weight:600;padding:6px 11px;'
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
			. '.cuon{overflow-x:auto}'
			. 'table{width:100%;border-collapse:collapse;font-size:14px}'
			. 'th,td{padding:8px 10px;border-bottom:1px solid var(--vien);text-align:left;vertical-align:top}'
			. 'th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--mo)}'

			/* ---------- vạch chặng ---------- */
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

			/* Màn hẹp: cột trái thành dải ngang cuộn được, không chiếm nửa màn hình điện thoại. */
			. '@media(max-width:820px){'
			. '.khung{grid-template-columns:minmax(0,1fr)}'
			. '.trai{position:static;min-height:0;flex-direction:row;flex-wrap:wrap;align-items:center;gap:8px}'
			. '.hieu-o{padding:0 6px 0 0}.hieu-o small{display:none}'
			. '.dh{flex-direction:row;flex-wrap:wrap}'
			. '.mi-nhan{display:none}.thoat-o{margin:0;padding:0}'
			. '.thoat-o button{width:auto}'
			. '.phai{padding:12px}}'
			. '@media(max-width:640px){input,select,textarea{font-size:16px}}';
	}
}
