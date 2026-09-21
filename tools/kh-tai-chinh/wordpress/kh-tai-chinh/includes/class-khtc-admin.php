<?php
/**
 * Các màn hình trong wp-admin.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Admin {

	public static function menu() {
		add_menu_page( 'Tài Chính K&H', 'Tài Chính K&H', KHTC_CAP, 'khtc', array( __CLASS__, 'trang_tong_quan' ), 'dashicons-bank', 57 );
		add_submenu_page( 'khtc', 'Tổng quan', 'Tổng quan', KHTC_CAP, 'khtc', array( __CLASS__, 'trang_tong_quan' ) );
		add_submenu_page( 'khtc', 'Ngân hàng', 'Ngân hàng', KHTC_CAP, 'khtc-ngan-hang', array( __CLASS__, 'trang_ngan_hang' ) );
		add_submenu_page( 'khtc', 'Giao dịch / Sao kê', 'Giao dịch / Sao kê', KHTC_CAP, 'khtc-giao-dich', array( __CLASS__, 'trang_giao_dich' ) );
	}

	public static function nap_style( $hook ) {
		if ( strpos( (string) $hook, 'khtc' ) === false ) { return; }
		wp_enqueue_style( 'khtc', KHTC_URL . 'assets/khtc.css', array(), KHTC_VERSION );
	}

	// ------------------------------------------------------------ tổng quan

	public static function trang_tong_quan() {
		KHTC_UI::nhan_doi_cty();
		$nh  = KHTC_NganHang::ds_kem_so_du();
		$now = current_time( 'Y-m' );
		$thang = KHTC_GiaoDich::loc( array( 'tu' => $now . '-01', 'den' => $now . '-31' ) );

		$tong_du = 0;
		foreach ( $nh as $b ) { $tong_du += $b->so_du; }

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Tổng quan' );
		KHTC_UI::the_so(
			array(
				array( 'Tổng số dư ngân hàng', KHTC_UI::tien( $tong_du ) ),
				array( 'Thu tháng này', KHTC_UI::tien( $thang['thu'] ), 'thu' ),
				array( 'Chi tháng này', KHTC_UI::tien( $thang['chi'] ), 'chi' ),
				array( 'Giao dịch tháng này', number_format( $thang['so_dong'], 0, ',', '.' ) ),
			)
		);

		echo '<div class="khtc-panel"><h2>Số dư từng tài khoản</h2>';
		if ( ! $nh ) {
			echo '<div class="khtc-trong">Chưa có tài khoản nào. Vào mục <strong>Ngân hàng</strong> để thêm.</div>';
		} else {
			echo '<table><thead><tr><th>Tài khoản</th><th>Số tài khoản</th><th class="so">Số dư đầu</th><th class="so">Số dư hiện tại</th></tr></thead><tbody>';
			foreach ( $nh as $b ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td class="so">%s</td><td class="so"><strong>%s</strong></td></tr>',
					esc_html( $b->ten ),
					esc_html( $b->so_tk ?: '—' ),
					esc_html( KHTC_UI::tien( $b->so_du_dau ) ),
					esc_html( KHTC_UI::tien( $b->so_du ) )
				);
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';
	}

	// ------------------------------------------------------------- ngân hàng

	public static function trang_ngan_hang() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = $bao_loi = '';

		if ( isset( $_POST['khtc_them_nh'] ) && check_admin_referer( 'khtc_nh' ) ) {
			$kq = KHTC_NganHang::them(
				array(
					'ten'       => sanitize_text_field( wp_unslash( $_POST['ten'] ?? '' ) ),
					'so_tk'     => sanitize_text_field( wp_unslash( $_POST['so_tk'] ?? '' ) ),
					'so_du_dau' => KHTC_GiaoDich::doc_so( wp_unslash( $_POST['so_du_dau'] ?? '0' ) ),
					'ngay_dau'  => KHTC_GiaoDich::doc_ngay( wp_unslash( $_POST['ngay_dau'] ?? '' ) ),
					'ghi_chu'   => sanitize_textarea_field( wp_unslash( $_POST['ghi_chu'] ?? '' ) ),
				)
			);
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); }
			else { $bao_ok = 'Đã thêm tài khoản.'; }
		}

		if ( isset( $_POST['khtc_xoa_nh'] ) && check_admin_referer( 'khtc_nh' ) ) {
			KHTC_NganHang::xoa( (int) $_POST['khtc_xoa_nh'] );
			$bao_ok = 'Đã xoá tài khoản và toàn bộ giao dịch của nó.';
		}

		$ds = KHTC_NganHang::ds_kem_so_du();

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Ngân hàng' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		echo '<div class="khtc-panel"><h2>Thêm tài khoản</h2><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_nh' );
		echo '<label>Tên tài khoản / ngân hàng<input type="text" name="ten" required style="min-width:240px"></label>';
		echo '<label>Số tài khoản<input type="text" name="so_tk"></label>';
		echo '<label>Số dư đầu<input type="text" name="so_du_dau" placeholder="0"></label>';
		echo '<label>Tính từ ngày<input type="date" name="ngay_dau"></label>';
		echo '<button type="submit" name="khtc_them_nh" value="1" class="button button-primary">Thêm</button>';
		echo '</div><p class="khtc-sub">Giao dịch phát sinh <em>trước</em> ngày này coi như đã nằm sẵn trong số dư đầu, không cộng lại lần nữa.</p></form></div>';

		echo '<div class="khtc-panel"><h2>Danh sách tài khoản</h2>';
		if ( ! $ds ) {
			echo '<div class="khtc-trong">Chưa có tài khoản nào.</div>';
		} else {
			echo '<table><thead><tr><th>Tên</th><th>Số tài khoản</th><th>Tính từ</th><th class="so">Số dư đầu</th><th class="so">Số dư hiện tại</th><th></th></tr></thead><tbody>';
			foreach ( $ds as $b ) {
				echo '<tr>';
				printf( '<td>%s</td><td>%s</td><td>%s</td>', esc_html( $b->ten ), esc_html( $b->so_tk ?: '—' ), esc_html( KHTC_UI::ngay( $b->ngay_dau ) ) );
				printf( '<td class="so">%s</td><td class="so"><strong>%s</strong></td>', esc_html( KHTC_UI::tien( $b->so_du_dau ) ), esc_html( KHTC_UI::tien( $b->so_du ) ) );
				echo '<td><form method="post" onsubmit="return confirm(\'Xoá tài khoản này và TẤT CẢ giao dịch của nó?\')">';
				wp_nonce_field( 'khtc_nh' );
				printf( '<button type="submit" name="khtc_xoa_nh" value="%d" class="button button-small">Xoá</button>', (int) $b->id );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';
	}

	// ------------------------------------------------------------- giao dịch

	public static function trang_giao_dich() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = $bao_loi = '';

		if ( isset( $_POST['khtc_them_gd'] ) && check_admin_referer( 'khtc_gd' ) ) {
			$kq = KHTC_GiaoDich::them(
				array(
					'ngan_hang_id' => (int) ( $_POST['ngan_hang_id'] ?? 0 ),
					'ngay'         => sanitize_text_field( wp_unslash( $_POST['ngay'] ?? '' ) ),
					'dien_giai'    => sanitize_text_field( wp_unslash( $_POST['dien_giai'] ?? '' ) ),
					'so_tien'      => sanitize_text_field( wp_unslash( $_POST['so_tien'] ?? '' ) ),
					'loai'         => sanitize_text_field( wp_unslash( $_POST['loai'] ?? 'thu' ) ),
				)
			);
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); }
			else { $bao_ok = 'Đã thêm giao dịch.'; }
		}

		if ( isset( $_POST['khtc_dan'] ) && check_admin_referer( 'khtc_gd' ) ) {
			$nh = (int) ( $_POST['dan_ngan_hang_id'] ?? 0 );
			if ( ! $nh ) {
				$bao_loi = 'Chưa chọn tài khoản để nạp sao kê vào.';
			} else {
				$kq = KHTC_GiaoDich::dan_hang_loat( $nh, wp_unslash( $_POST['sao_ke'] ?? '' ) );
				$bao_ok = 'Đã nạp ' . $kq['them'] . ' dòng.';
				if ( $kq['loi'] ) {
					$bao_loi = implode( ' · ', array_slice( $kq['loi'], 0, 5 ) )
						. ( count( $kq['loi'] ) > 5 ? ' … và ' . ( count( $kq['loi'] ) - 5 ) . ' dòng nữa' : '' );
				}
			}
		}

		if ( isset( $_POST['khtc_xoa_gd'] ) && check_admin_referer( 'khtc_gd' ) ) {
			KHTC_GiaoDich::xoa( (int) $_POST['khtc_xoa_gd'] );
			$bao_ok = 'Đã xoá giao dịch.';
		}

		$ngan_hang = KHTC_NganHang::ds();
		$loc = array(
			'ngan_hang_id' => (int) ( $_GET['nh'] ?? 0 ),
			'tu'           => sanitize_text_field( wp_unslash( $_GET['tu'] ?? '' ) ),
			'den'          => sanitize_text_field( wp_unslash( $_GET['den'] ?? '' ) ),
			'loai'         => sanitize_text_field( wp_unslash( $_GET['loai'] ?? '' ) ),
			'tim'          => sanitize_text_field( wp_unslash( $_GET['tim'] ?? '' ) ),
			'trang'        => (int) ( $_GET['trang'] ?? 1 ),
		);
		$kq = KHTC_GiaoDich::loc( $loc );

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Giao dịch / Sao kê' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		if ( ! $ngan_hang ) {
			echo '<div class="khtc-panel"><div class="khtc-trong">Chưa có tài khoản ngân hàng nào. Vào mục <strong>Ngân hàng</strong> thêm trước đã.</div></div></div>';
			return;
		}

		KHTC_UI::the_so(
			array(
				array( 'Số dòng đang lọc', number_format( $kq['so_dong'], 0, ',', '.' ) ),
				array( 'Thu', KHTC_UI::tien( $kq['thu'] ), 'thu' ),
				array( 'Chi', KHTC_UI::tien( $kq['chi'] ), 'chi' ),
				array( 'Chênh lệch', KHTC_UI::tien( $kq['thu'] - $kq['chi'] ) ),
			)
		);

		// ---- bộ lọc
		echo '<div class="khtc-panel"><h2>Lọc</h2><form method="get"><div class="khtc-loc">';
		echo '<input type="hidden" name="page" value="khtc-giao-dich">';
		echo '<label>Tài khoản<select name="nh"><option value="">— Tất cả —</option>';
		foreach ( $ngan_hang as $b ) {
			printf( '<option value="%d"%s>%s</option>', (int) $b->id, selected( $loc['ngan_hang_id'], $b->id, false ), esc_html( $b->ten ) );
		}
		echo '</select></label>';
		printf( '<label>Từ ngày<input type="date" name="tu" value="%s"></label>', esc_attr( $loc['tu'] ) );
		printf( '<label>Đến ngày<input type="date" name="den" value="%s"></label>', esc_attr( $loc['den'] ) );
		echo '<label>Loại<select name="loai"><option value="">— Cả hai —</option>';
		printf( '<option value="thu"%s>Thu</option>', selected( $loc['loai'], 'thu', false ) );
		printf( '<option value="chi"%s>Chi</option>', selected( $loc['loai'], 'chi', false ) );
		echo '</select></label>';
		printf( '<label>Tìm diễn giải<input type="search" name="tim" value="%s"></label>', esc_attr( $loc['tim'] ) );
		echo '<button type="submit" class="button">Lọc</button>';
		printf( '<a href="%s" class="button">Bỏ lọc</a>', esc_url( admin_url( 'admin.php?page=khtc-giao-dich' ) ) );
		echo '</div></form></div>';

		// ---- thêm tay + dán sao kê
		echo '<div class="khtc-panel"><h2>Thêm giao dịch</h2><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_gd' );
		echo '<label>Tài khoản<select name="ngan_hang_id" required>';
		foreach ( $ngan_hang as $b ) { printf( '<option value="%d">%s</option>', (int) $b->id, esc_html( $b->ten ) ); }
		echo '</select></label>';
		echo '<label>Ngày<input type="date" name="ngay" required></label>';
		echo '<label>Diễn giải<input type="text" name="dien_giai" style="min-width:260px"></label>';
		echo '<label>Số tiền<input type="text" name="so_tien" placeholder="1.500.000" required></label>';
		echo '<label>Loại<select name="loai"><option value="thu">Thu</option><option value="chi">Chi</option></select></label>';
		echo '<button type="submit" name="khtc_them_gd" value="1" class="button button-primary">Thêm</button>';
		echo '</div></form></div>';

		echo '<div class="khtc-panel"><h2>Dán sao kê hàng loạt</h2><form method="post">';
		wp_nonce_field( 'khtc_gd' );
		echo '<div class="khtc-loc"><label>Nạp vào tài khoản<select name="dan_ngan_hang_id" required>';
		foreach ( $ngan_hang as $b ) { printf( '<option value="%d">%s</option>', (int) $b->id, esc_html( $b->ten ) ); }
		echo '</select></label></div>';
		echo '<p class="khtc-sub">Mỗi dòng: <code>Ngày (dd/mm/yyyy) · Diễn giải · Số tiền · Thu/Chi</code> — cách nhau bằng Tab (copy thẳng từ Excel) hoặc dấu phẩy. Bỏ trống cột cuối thì số dương là Thu, số âm là Chi.</p>';
		echo '<textarea name="sao_ke" rows="7" style="width:100%;font-family:monospace" placeholder="20/07/2026&#9;Thu tien khach ABC&#9;1.500.000&#9;Thu&#10;21/07/2026&#9;Chi tra nha cung cap&#9;850.000&#9;Chi"></textarea>';
		echo '<p><button type="submit" name="khtc_dan" value="1" class="button button-primary">Nạp sao kê</button></p></form></div>';

		// ---- bảng
		echo '<div class="khtc-panel"><h2>Danh sách giao dịch</h2>';
		if ( ! $kq['rows'] ) {
			echo '<div class="khtc-trong">Không có dòng nào khớp bộ lọc.</div>';
		} else {
			echo '<table><thead><tr><th>Ngày</th><th>Tài khoản</th><th>Diễn giải</th><th class="so">Thu</th><th class="so">Chi</th><th></th></tr></thead><tbody>';
			foreach ( $kq['rows'] as $g ) {
				echo '<tr>';
				printf( '<td>%s</td><td>%s</td><td>%s</td>', esc_html( KHTC_UI::ngay( $g->ngay ) ), esc_html( $g->ten_ngan_hang ), esc_html( $g->dien_giai ) );
				printf( '<td class="so thu">%s</td>', 'thu' === $g->loai ? esc_html( KHTC_UI::tien( $g->so_tien ) ) : '' );
				printf( '<td class="so chi">%s</td>', 'chi' === $g->loai ? esc_html( KHTC_UI::tien( $g->so_tien ) ) : '' );
				echo '<td><form method="post" onsubmit="return confirm(\'Xoá dòng này?\')">';
				wp_nonce_field( 'khtc_gd' );
				printf( '<button type="submit" name="khtc_xoa_gd" value="%d" class="button button-small">Xoá</button>', (int) $g->id );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';

			if ( $kq['so_trang'] > 1 ) {
				echo '<p class="khtc-sub">Trang ' . (int) $kq['trang'] . ' / ' . (int) $kq['so_trang'] . ' — ';
				$q = $_GET;
				if ( $kq['trang'] > 1 ) {
					$q['trang'] = $kq['trang'] - 1;
					printf( '<a href="%s">← Trước</a> ', esc_url( admin_url( 'admin.php?' . http_build_query( $q ) ) ) );
				}
				if ( $kq['trang'] < $kq['so_trang'] ) {
					$q['trang'] = $kq['trang'] + 1;
					printf( '<a href="%s">Sau →</a>', esc_url( admin_url( 'admin.php?' . http_build_query( $q ) ) ) );
				}
				echo '</p>';
			}
		}
		echo '</div></div>';
	}
}
