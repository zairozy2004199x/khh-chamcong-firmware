<?php
/**
 * Nội dung bốn màn hình. Dùng chung cho cả wp-admin lẫn bản web ngoài.
 *
 * Viết một lần ở đây thay vì chép sang hai nơi: hai bản chép tay sẽ lệch nhau
 * ngay lần sửa thứ hai, và kế toán xem ở web ngoài sẽ thấy số khác người xem
 * trong wp-admin. Chỗ duy nhất khác nhau giữa hai nơi là ĐỊA CHỈ, nên chỉ địa
 * chỉ mới hỏi "đang đứng ở đâu" (self::url / self::url_form / self::an_get).
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Trang {

	public static function hien( $man ) {
		switch ( $man ) {
			case 'ngan-hang': self::ngan_hang(); break;
			case 'giao-dich': self::giao_dich(); break;
			case 'doi-soat':  self::doi_soat();  break;
			default:          self::tong_quan(); break;
		}
	}

	// ------------------------------------------------------------ địa chỉ

	public static function url( $man = '', $args = array() ) {
		$goc = KHTC_Web::dang_o_web()
			? KHTC_Web::duong_dan( $man )
			: admin_url( 'admin.php?page=khtc' . ( $man ? '-' . $man : '' ) );
		return $args ? add_query_arg( $args, $goc ) : $goc;
	}

	/** Đích của form GET. Form GET vứt bỏ query string trong action nên phải sạch. */
	public static function url_form( $man = '' ) {
		if ( ! KHTC_Web::dang_o_web() ) {
			return admin_url( 'admin.php' );
		}
		return get_option( 'permalink_structure' ) ? KHTC_Web::duong_dan( $man ) : home_url( '/' );
	}

	/** Trường ẩn giữ cho form GET quay lại đúng màn hình. */
	public static function an_get( $man = '' ) {
		if ( ! KHTC_Web::dang_o_web() ) {
			printf( '<input type="hidden" name="page" value="%s">', esc_attr( 'khtc' . ( $man ? '-' . $man : '' ) ) );
		} elseif ( ! get_option( 'permalink_structure' ) ) {
			printf( '<input type="hidden" name="khtc_man" value="%s">', esc_attr( $man ? $man : 'tong-quan' ) );
		}
	}

	// ---------------------------------------------------------- tổng quan

	public static function tong_quan() {
		KHTC_UI::nhan_doi_cty();
		$nh    = KHTC_NganHang::ds_kem_so_du();
		$now   = current_time( 'Y-m' );
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
			printf(
				'<div class="khtc-trong">Chưa có tài khoản nào. Vào mục <a href="%s"><strong>Ngân hàng</strong></a> để thêm.</div>',
				esc_url( self::url( 'ngan-hang' ) )
			);
		} else {
			echo '<table><thead><tr><th>Tài khoản</th><th>Số tài khoản</th><th class="so">Số dư đầu</th><th class="so">Số dư hiện tại</th></tr></thead><tbody>';
			foreach ( $nh as $b ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td class="so">%s</td><td class="so"><strong>%s</strong></td></tr>',
					esc_html( $b->ten ),
					esc_html( $b->so_tk ? $b->so_tk : '—' ),
					esc_html( KHTC_UI::tien( $b->so_du_dau ) ),
					esc_html( KHTC_UI::tien( $b->so_du ) )
				);
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		// Vài đợt đối soát gần nhất, để mở trang là thấy ngay cái gì còn lệch.
		$dot = array_slice( KHTC_DoiSoat::ds_dot(), 0, 5 );
		if ( $dot ) {
			echo '<div class="khtc-panel"><h2>Đối soát gần đây</h2><table><thead><tr>';
			echo '<th>Đợt</th><th>Kênh</th><th>Kỳ</th><th class="so">Thiếu</th><th class="so">Thừa</th><th></th></tr></thead><tbody>';
			foreach ( $dot as $d ) {
				$kq = KHTC_DoiSoat::ket_qua( $d->id );
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s → %s</td><td class="so%s">%s</td><td class="so%s">%s</td><td><a href="%s">Mở</a></td></tr>',
					esc_html( $d->ten ),
					esc_html( KHTC_DoiSoat::ten_kenh( $d->kenh ) ),
					esc_html( KHTC_UI::ngay( $d->tu ) ),
					esc_html( KHTC_UI::ngay( $d->den ) ),
					$kq['tien_thieu'] ? ' chi' : '',
					esc_html( KHTC_UI::tien( $kq['tien_thieu'] ) ),
					$kq['tien_thua'] ? ' chi' : '',
					esc_html( KHTC_UI::tien( $kq['tien_thua'] ) ),
					esc_url( self::url( 'doi-soat', array( 'dot' => (int) $d->id ) ) )
				);
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	// ----------------------------------------------------------- ngân hàng

	public static function ngan_hang() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

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
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã thêm tài khoản.'; }
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
				printf( '<td>%s</td><td>%s</td><td>%s</td>', esc_html( $b->ten ), esc_html( $b->so_tk ? $b->so_tk : '—' ), esc_html( KHTC_UI::ngay( $b->ngay_dau ) ) );
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

	// ----------------------------------------------------------- giao dịch

	public static function giao_dich() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		if ( isset( $_POST['khtc_them_gd'] ) && check_admin_referer( 'khtc_gd' ) ) {
			$kq = KHTC_GiaoDich::them(
				array(
					'ngan_hang_id' => (int) ( $_POST['ngan_hang_id'] ?? 0 ),
					'ngay'         => sanitize_text_field( wp_unslash( $_POST['ngay'] ?? '' ) ),
					'dien_giai'    => sanitize_text_field( wp_unslash( $_POST['dien_giai'] ?? '' ) ),
					'so_tien'      => sanitize_text_field( wp_unslash( $_POST['so_tien'] ?? '' ) ),
					'loai'         => sanitize_text_field( wp_unslash( $_POST['loai'] ?? 'thu' ) ),
					'ma_gd'        => sanitize_text_field( wp_unslash( $_POST['ma_gd'] ?? '' ) ),
				)
			);
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã thêm giao dịch.'; }
		}

		if ( isset( $_POST['khtc_dan'] ) && check_admin_referer( 'khtc_gd' ) ) {
			$nh = (int) ( $_POST['dan_ngan_hang_id'] ?? 0 );
			if ( ! $nh ) {
				$bao_loi = 'Chưa chọn tài khoản để nạp sao kê vào.';
			} else {
				$kq     = KHTC_GiaoDich::dan_hang_loat( $nh, wp_unslash( $_POST['sao_ke'] ?? '' ) );
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
		$loc       = array(
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
			printf(
				'<div class="khtc-panel"><div class="khtc-trong">Chưa có tài khoản ngân hàng nào. Vào mục <a href="%s"><strong>Ngân hàng</strong></a> thêm trước đã.</div></div></div>',
				esc_url( self::url( 'ngan-hang' ) )
			);
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

		printf( '<div class="khtc-panel"><h2>Lọc</h2><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'giao-dich' ) ) );
		self::an_get( 'giao-dich' );
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
		printf( '<a href="%s" class="button">Bỏ lọc</a>', esc_url( self::url( 'giao-dich' ) ) );
		echo '</div></form></div>';

		echo '<div class="khtc-panel"><h2>Thêm giao dịch</h2><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_gd' );
		echo '<label>Tài khoản<select name="ngan_hang_id" required>';
		foreach ( $ngan_hang as $b ) { printf( '<option value="%d">%s</option>', (int) $b->id, esc_html( $b->ten ) ); }
		echo '</select></label>';
		echo '<label>Ngày<input type="date" name="ngay" required></label>';
		echo '<label>Diễn giải<input type="text" name="dien_giai" style="min-width:240px"></label>';
		echo '<label>Mã GD<input type="text" name="ma_gd" placeholder="để đối soát"></label>';
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
		echo '<textarea name="sao_ke" rows="7" placeholder="20/07/2026&#9;Thu tien khach ABC&#9;1.500.000&#9;Thu&#10;21/07/2026&#9;Chi tra nha cung cap&#9;850.000&#9;Chi"></textarea>';
		echo '<p><button type="submit" name="khtc_dan" value="1" class="button button-primary">Nạp sao kê</button></p></form></div>';

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
				$giu = array_filter(
					array(
						'nh'   => $loc['ngan_hang_id'] ? $loc['ngan_hang_id'] : '',
						'tu'   => $loc['tu'],
						'den'  => $loc['den'],
						'loai' => $loc['loai'],
						'tim'  => $loc['tim'],
					)
				);
				echo '<p class="khtc-sub">Trang ' . (int) $kq['trang'] . ' / ' . (int) $kq['so_trang'] . ' — ';
				if ( $kq['trang'] > 1 ) {
					printf( '<a href="%s">← Trước</a> ', esc_url( self::url( 'giao-dich', $giu + array( 'trang' => $kq['trang'] - 1 ) ) ) );
				}
				if ( $kq['trang'] < $kq['so_trang'] ) {
					printf( '<a href="%s">Sau →</a>', esc_url( self::url( 'giao-dich', $giu + array( 'trang' => $kq['trang'] + 1 ) ) ) );
				}
				echo '</p>';
			}
		}
		echo '</div></div>';
	}

	// ------------------------------------------------------------ đối soát

	public static function doi_soat() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';
		$mo = (int) ( $_GET['dot'] ?? 0 );

		if ( isset( $_POST['khtc_tao_dot'] ) && check_admin_referer( 'khtc_ds' ) ) {
			$kq = KHTC_DoiSoat::tao_dot(
				array(
					'ten'          => sanitize_text_field( wp_unslash( $_POST['ten'] ?? '' ) ),
					'kenh'         => sanitize_text_field( wp_unslash( $_POST['kenh'] ?? '' ) ),
					'ngan_hang_id' => (int) ( $_POST['ngan_hang_id'] ?? 0 ),
					'tu'           => sanitize_text_field( wp_unslash( $_POST['tu'] ?? '' ) ),
					'den'          => sanitize_text_field( wp_unslash( $_POST['den'] ?? '' ) ),
				)
			);
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
			} else {
				$mo     = $kq;
				$bao_ok = 'Đã tạo đợt đối soát. Dán bảng cổng gửi về rồi bấm “Chạy đối soát”.';
			}
		}

		if ( isset( $_POST['khtc_nap_dong'] ) && check_admin_referer( 'khtc_ds' ) ) {
			$mo = (int) $_POST['khtc_nap_dong'];
			$kq = KHTC_DoiSoat::nap_dong( $mo, wp_unslash( $_POST['bang_cong'] ?? '' ) );
			$bao_ok = 'Đã nạp ' . $kq['them'] . ' dòng'
				. ( $kq['trung'] ? ', bỏ qua ' . $kq['trung'] . ' dòng trùng mã giao dịch' : '' ) . '.';
			if ( $kq['loi'] ) {
				$bao_loi = implode( ' · ', array_slice( $kq['loi'], 0, 5 ) )
					. ( count( $kq['loi'] ) > 5 ? ' … và ' . ( count( $kq['loi'] ) - 5 ) . ' dòng nữa' : '' );
			}
			KHTC_DoiSoat::chay( $mo );
		}

		if ( isset( $_POST['khtc_chay'] ) && check_admin_referer( 'khtc_ds' ) ) {
			$mo = (int) $_POST['khtc_chay'];
			KHTC_DoiSoat::chay( $mo );
			$bao_ok = 'Đã chạy lại đối soát.';
		}

		if ( isset( $_POST['khtc_xoa_dong'] ) && check_admin_referer( 'khtc_ds' ) ) {
			$mo = (int) $_POST['khtc_xoa_dong'];
			KHTC_DoiSoat::xoa_dong_cua_dot( $mo );
			$bao_ok = 'Đã xoá toàn bộ dòng cổng của đợt này. Nạp lại bảng mới.';
		}

		if ( isset( $_POST['khtc_xoa_dot'] ) && check_admin_referer( 'khtc_ds' ) ) {
			KHTC_DoiSoat::xoa_dot( (int) $_POST['khtc_xoa_dot'] );
			$mo     = 0;
			$bao_ok = 'Đã xoá đợt đối soát.';
		}

		$ngan_hang = KHTC_NganHang::ds();

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Đối soát' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		if ( ! $ngan_hang ) {
			printf(
				'<div class="khtc-panel"><div class="khtc-trong">Đối soát cần một tài khoản ngân hàng để so vào. Vào mục <a href="%s"><strong>Ngân hàng</strong></a> thêm trước đã.</div></div></div>',
				esc_url( self::url( 'ngan-hang' ) )
			);
			return;
		}

		if ( $mo ) {
			self::doi_soat_chi_tiet( $mo );
			echo '</div>';
			return;
		}

		echo '<div class="khtc-panel"><h2>Tạo đợt đối soát</h2><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_ds' );
		echo '<label>Kênh<select name="kenh">';
		foreach ( KHTC_DoiSoat::kenh() as $k => $v ) { printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $v ) ); }
		echo '</select></label>';
		echo '<label>Tiền về tài khoản<select name="ngan_hang_id" required>';
		foreach ( $ngan_hang as $b ) { printf( '<option value="%d">%s</option>', (int) $b->id, esc_html( $b->ten ) ); }
		echo '</select></label>';
		echo '<label>Từ ngày<input type="date" name="tu" required></label>';
		echo '<label>Đến ngày<input type="date" name="den" required></label>';
		echo '<label>Tên đợt<input type="text" name="ten" placeholder="để trống thì tự đặt"></label>';
		echo '<button type="submit" name="khtc_tao_dot" value="1" class="button button-primary">Tạo đợt</button>';
		echo '</div></form></div>';

		$ds = KHTC_DoiSoat::ds_dot();
		echo '<div class="khtc-panel"><h2>Các đợt đã tạo</h2>';
		if ( ! $ds ) {
			echo '<div class="khtc-trong">Chưa có đợt nào.</div>';
		} else {
			echo '<table><thead><tr><th>Đợt</th><th>Kênh</th><th>Tài khoản</th><th>Kỳ</th><th class="so">Khớp</th><th class="so">Thiếu</th><th class="so">Thừa</th><th></th></tr></thead><tbody>';
			foreach ( $ds as $d ) {
				$kq = KHTC_DoiSoat::ket_qua( $d->id );
				printf(
					'<tr><td><a href="%s"><strong>%s</strong></a></td><td>%s</td><td>%s</td><td>%s → %s</td>',
					esc_url( self::url( 'doi-soat', array( 'dot' => (int) $d->id ) ) ),
					esc_html( $d->ten ),
					esc_html( KHTC_DoiSoat::ten_kenh( $d->kenh ) ),
					esc_html( $d->ten_ngan_hang ? $d->ten_ngan_hang : '—' ),
					esc_html( KHTC_UI::ngay( $d->tu ) ),
					esc_html( KHTC_UI::ngay( $d->den ) )
				);
				printf(
					'<td class="so thu">%d</td><td class="so%s">%d</td><td class="so%s">%d</td>',
					count( $kq['khop'] ),
					$kq['thieu'] ? ' chi' : '',
					count( $kq['thieu'] ),
					$kq['thua'] ? ' chi' : '',
					count( $kq['thua'] )
				);
				echo '<td><form method="post" onsubmit="return confirm(\'Xoá cả đợt này?\')">';
				wp_nonce_field( 'khtc_ds' );
				printf( '<button type="submit" name="khtc_xoa_dot" value="%d" class="button button-small">Xoá</button>', (int) $d->id );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';
	}

	private static function doi_soat_chi_tiet( $dot_id ) {
		$kq = KHTC_DoiSoat::ket_qua( $dot_id );
		if ( ! $kq ) {
			echo '<div class="khtc-panel"><div class="khtc-trong">Không tìm thấy đợt này.</div></div>';
			return;
		}
		$d = $kq['dot'];

		printf(
			'<p class="khtc-sub"><a href="%s">← Tất cả các đợt</a></p>',
			esc_url( self::url( 'doi-soat' ) )
		);

		echo '<div class="khtc-panel"><h2>' . esc_html( $d->ten ) . '</h2>';
		printf(
			'<p class="khtc-sub">%s · tiền về <strong>%s</strong> · kỳ %s → %s%s</p>',
			esc_html( KHTC_DoiSoat::ten_kenh( $d->kenh ) ),
			esc_html( $d->ten_ngan_hang ? $d->ten_ngan_hang : '—' ),
			esc_html( KHTC_UI::ngay( $d->tu ) ),
			esc_html( KHTC_UI::ngay( $d->den ) ),
			$d->chay_luc ? ' · chạy lúc ' . esc_html( mysql2date( 'H:i d/m/Y', $d->chay_luc ) ) : ''
		);
		echo '<div class="khtc-loc">';
		echo '<form method="post">';
		wp_nonce_field( 'khtc_ds' );
		printf( '<button type="submit" name="khtc_chay" value="%d" class="button button-primary">Chạy lại đối soát</button>', (int) $d->id );
		echo '</form>';
		printf(
			'<a class="button" href="%s">Tải CSV kết quả</a>',
			esc_url( wp_nonce_url( self::url( 'doi-soat', array( 'khtc_tai' => (int) $d->id ) ), 'khtc_tai_' . (int) $d->id ) )
		);
		echo '<form method="post" onsubmit="return confirm(\'Xoá hết dòng cổng đã nạp của đợt này?\')">';
		wp_nonce_field( 'khtc_ds' );
		printf( '<button type="submit" name="khtc_xoa_dong" value="%d" class="button button-small">Xoá dòng cổng đã nạp</button>', (int) $d->id );
		echo '</form></div></div>';

		// Cố ý KHÔNG có thẻ "tổng cổng trừ tổng ngân hàng". Tài khoản còn nhận
		// tiền mặt và tiền kênh khác, nên hiệu đó gần như luôn khác 0 kể cả khi
		// đối soát sạch — một con số đỏ không có việc gì để làm với nó. Bốn số
		// dưới đây thì mỗi số ứng với một việc cụ thể phải xử lý.
		KHTC_UI::the_so(
			array(
				array( 'Cổng báo về', KHTC_UI::tien( $kq['tong_cong'] ) ),
				array( 'Đã khớp', KHTC_UI::tien( $kq['tien_khop'] ), 'thu' ),
				array( 'Chưa về — đòi cổng', KHTC_UI::tien( $kq['tien_thieu'] ), $kq['tien_thieu'] ? 'chi' : '' ),
				array( 'NH có, cổng không báo', KHTC_UI::tien( $kq['tien_thua'] ), $kq['tien_thua'] ? 'chi' : '' ),
				array( 'Phí cổng', KHTC_UI::tien( $kq['tong_phi'] ) ),
			)
		);
		printf(
			'<p class="khtc-sub">Tổng tiền vào %s trong kỳ là %s — gồm cả tiền mặt và các kênh khác, nên không dùng để trừ thẳng với số cổng báo.</p>',
			esc_html( $d->ten_ngan_hang ? $d->ten_ngan_hang : 'tài khoản' ),
			esc_html( KHTC_UI::tien( $kq['tong_ngan'] ) )
		);

		echo '<div class="khtc-panel"><h2>Nạp bảng cổng gửi về</h2><form method="post">';
		wp_nonce_field( 'khtc_ds' );
		echo '<p class="khtc-sub">Mỗi dòng: <code>Ngày · Mã GD · Số tiền · Phí · Nội dung</code> — cách nhau bằng Tab (copy thẳng từ file cổng) hoặc dấu phẩy. Thiếu cột Phí thì để trống. Dòng trùng mã giao dịch với dòng đã nạp sẽ bị bỏ qua.</p>';
		echo '<textarea name="bang_cong" rows="7" placeholder="05/08/2026&#9;PAY123456&#9;1.000.000&#9;11.000&#9;Thanh toan QR&#10;05/08/2026&#9;PAY123457&#9;250.000&#9;2.750&#9;Thanh toan the"></textarea>';
		printf( '<p><button type="submit" name="khtc_nap_dong" value="%d" class="button button-primary">Nạp và đối soát</button></p>', (int) $d->id );
		echo '</form></div>';

		self::bang_khop( 'Khớp', 'thu', $kq['khop'], $kq['tien_khop'] );
		self::bang_khop( 'Lệch tiền', 'chi', $kq['lech'], $kq['tien_lech'], true );
		self::bang_thieu( $kq['thieu'], $kq['tien_thieu'] );
		self::bang_thua( $kq['thua'], $kq['tien_thua'] );
	}

	private static function bang_khop( $tieu_de, $mau, $rows, $tong, $la_chenh = false ) {
		printf(
			'<div class="khtc-panel"><h2>%s <span class="khtc-dem %s">%d dòng · %s</span></h2>',
			esc_html( $tieu_de ),
			esc_attr( $mau ),
			count( $rows ),
			esc_html( KHTC_UI::tien( $tong ) )
		);
		if ( ! $rows ) {
			echo '<div class="khtc-trong">Không có dòng nào.</div></div>';
			return;
		}
		echo '<table><thead><tr><th>Ngày cổng</th><th>Mã GD</th><th class="so">Cổng</th><th class="so">Phí</th><th>Ngày NH</th><th class="so">Ngân hàng</th>';
		echo $la_chenh ? '<th class="so">Chênh</th>' : '<th>Kiểu ghép</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td class="so">%s</td><td class="so">%s</td><td>%s</td><td class="so">%s</td>',
				esc_html( KHTC_UI::ngay( $r->ngay ) ),
				esc_html( $r->ma_gd ? $r->ma_gd : '—' ),
				esc_html( KHTC_UI::tien( $r->so_tien ) ),
				esc_html( $r->phi ? KHTC_UI::tien( $r->phi ) : '' ),
				esc_html( KHTC_UI::ngay( $r->gd_ngay ) ),
				esc_html( KHTC_UI::tien( $r->gd_so_tien ) )
			);
			if ( $la_chenh ) {
				printf( '<td class="so chi">%s</td>', esc_html( KHTC_UI::tien( (int) $r->so_tien - (int) $r->gd_so_tien ) ) );
			} else {
				printf( '<td>%s</td>', esc_html( KHTC_DoiSoat::ten_kieu( $r->kieu_khop ) ) );
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function bang_thieu( $rows, $tong ) {
		printf(
			'<div class="khtc-panel"><h2>Thiếu — cổng báo có, ngân hàng chưa về <span class="khtc-dem chi">%d dòng · %s</span></h2>',
			count( $rows ),
			esc_html( KHTC_UI::tien( $tong ) )
		);
		if ( ! $rows ) {
			echo '<div class="khtc-trong">Không thiếu dòng nào.</div></div>';
			return;
		}
		echo '<table><thead><tr><th>Ngày</th><th>Mã GD</th><th class="so">Số tiền</th><th class="so">Phí</th><th>Nội dung</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td class="so chi">%s</td><td class="so">%s</td><td>%s</td></tr>',
				esc_html( KHTC_UI::ngay( $r->ngay ) ),
				esc_html( $r->ma_gd ? $r->ma_gd : '—' ),
				esc_html( KHTC_UI::tien( $r->so_tien ) ),
				esc_html( $r->phi ? KHTC_UI::tien( $r->phi ) : '' ),
				esc_html( $r->dien_giai )
			);
		}
		echo '</tbody></table></div>';
	}

	private static function bang_thua( $rows, $tong ) {
		printf(
			'<div class="khtc-panel"><h2>Thừa — ngân hàng có, cổng không báo <span class="khtc-dem chi">%d dòng · %s</span></h2>',
			count( $rows ),
			esc_html( KHTC_UI::tien( $tong ) )
		);
		if ( ! $rows ) {
			echo '<div class="khtc-trong">Không thừa dòng nào.</div></div>';
			return;
		}
		echo '<table><thead><tr><th>Ngày</th><th>Mã GD</th><th class="so">Số tiền</th><th>Diễn giải sao kê</th></tr></thead><tbody>';
		foreach ( $rows as $g ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td class="so chi">%s</td><td>%s</td></tr>',
				esc_html( KHTC_UI::ngay( $g->ngay ) ),
				esc_html( $g->ma_gd ? $g->ma_gd : '—' ),
				esc_html( KHTC_UI::tien( $g->so_tien ) ),
				esc_html( $g->dien_giai )
			);
		}
		echo '</tbody></table></div>';
	}
}
