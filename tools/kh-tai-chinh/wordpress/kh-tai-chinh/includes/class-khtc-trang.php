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

	/**
	 * Lấy văn bản bảng cho một ô dán: có tệp nạp lên thì đọc tệp, không thì lấy
	 * ô dán. Lỗi đọc tệp → ghi vào $bao_loi và trả null để khối nạp KHÔNG chạy
	 * (chạy tiếp với văn bản rỗng sẽ báo "Đã nạp 0 dòng" — nghe như xong việc).
	 */
	private static function lay_bang( $ten, &$bao_loi ) {
		$tep = KHTC_Tep::lay( $ten, '', sanitize_text_field( wp_unslash( $_POST[ 'trang_' . $ten ] ?? '' ) ) );
		if ( $tep['loi'] ) {
			$bao_loi = $tep['loi'];
			return null;
		}
		if ( '' === trim( (string) $tep['van_ban'] ) ) {
			$bao_loi = 'Chưa chọn tệp, cũng chưa dán bảng.';
			return null;
		}
		// File sao kê của cổng (Payoo, VNPay, MoMo, QR) nạp nhầm vào ô khác: nhận
		// ra ngay và chỉ đúng chỗ. Để chạy tiếp thì ra "Đã nạp 0 dòng" cộng một
		// tràng lỗi từng dòng — người đọc không biết mình sai ở đâu.
		if ( 'tho' !== $ten ) {
			$nd = KHTC_DanTho::nhan_dang( $tep['van_ban'] );
			if ( $nd ) {
				$bao_loi = sprintf(
					'Bảng này là file %s — sao kê của cổng, không phải bảng cho ô này. Nạp nó ở mục Dòng tiền → Dán thô, máy tự lấy đúng cột.',
					$nd[1]['ten']
				);
				return null;
			}
		}
		return $tep['van_ban'];
	}

	public static function hien( $man ) {
		switch ( $man ) {
			case 'ngan-hang': self::ngan_hang(); break;
			case 'giao-dich': self::giao_dich(); break;
			case 'doi-soat':  self::doi_soat();  break;
			case 'chi-phi':   self::chi_phi();   break;
			case 'doi-soat-chi-phi': self::doi_soat_chi_phi(); break;
			case 'hoa-don-ra': self::hoa_don_ra(); break;
			case 'hoa-don-vao': self::hoa_don_vao(); break;
			case 'cong-no':   self::cong_no();   break;
			case 'phap-danh': self::phap_danh(); break;
			case 'ho-so':     self::ho_so();     break;
			case 'bao-cao':   self::bao_cao();   break;
			case 'nhat-ky':   self::nhat_ky();   break;
			case 'sao-luu':   self::sao_luu();   break;
			case 'nguoi-dung': self::nguoi_dung(); break;
			case 'danh-muc-diem': self::danh_muc_diem(); break;
			case 'dan-tho': self::dan_tho(); break;
			case 'sinh-hoa-don': self::sinh_hoa_don(); break;
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
		list( $d1, $d2 ) = KHTC_UI::thang_nay();
		$thang = KHTC_GiaoDich::loc( array( 'tu' => $d1, 'den' => $d2 ) );

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
			$kq = KHTC_NganHang::xoa( (int) $_POST['khtc_xoa_nh'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã xoá tài khoản và toàn bộ giao dịch của nó.'; }
		}

		$ds = KHTC_NganHang::ds_kem_so_du();

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Ngân hàng' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		echo '<details class="khtc-panel khtc-gap"><summary>Thêm tài khoản</summary><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_nh' );
		echo '<label>Tên tài khoản / ngân hàng<input type="text" name="ten" required style="min-width:240px"></label>';
		echo '<label>Số tài khoản<input type="text" name="so_tk"></label>';
		echo '<label>Số dư đầu<input type="text" name="so_du_dau" placeholder="0"></label>';
		echo '<label>Tính từ ngày<input type="date" name="ngay_dau"></label>';
		echo '<button type="submit" name="khtc_them_nh" value="1" class="button button-primary">Thêm</button>';
		echo '</div><p class="khtc-sub">Giao dịch phát sinh <em>trước</em> ngày này coi như đã nằm sẵn trong số dư đầu, không cộng lại lần nữa.</p></form></details>';

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

		if ( isset( $_POST['khtc_dan'] ) && check_admin_referer( 'khtc_gd' ) && null !== ( $vb = self::lay_bang( 'sao_ke', $bao_loi ) ) ) {
			$nh = (int) ( $_POST['dan_ngan_hang_id'] ?? 0 );
			if ( ! $nh ) {
				$bao_loi = 'Chưa chọn tài khoản để nạp sao kê vào.';
			} else {
				$kq     = KHTC_GiaoDich::dan_hang_loat( $nh, $vb );
				$bao_ok = 'Đã nạp ' . $kq['them'] . ' dòng.';
				if ( $kq['loi'] ) {
					$bao_loi = implode( ' · ', array_slice( $kq['loi'], 0, 5 ) )
						. ( count( $kq['loi'] ) > 5 ? ' … và ' . ( count( $kq['loi'] ) - 5 ) . ' dòng nữa' : '' );
				}
			}
		}

		if ( isset( $_POST['khtc_xoa_gd'] ) && check_admin_referer( 'khtc_gd' ) ) {
			$kq = KHTC_GiaoDich::xoa( (int) $_POST['khtc_xoa_gd'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã xoá giao dịch. Phục hồi được ở mục Nhật ký.'; }
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

		echo '<details class="khtc-panel khtc-gap"><summary>Thêm giao dịch</summary><form method="post"><div class="khtc-loc">';
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
		echo '</div></form></details>';

		echo '<details class="khtc-panel khtc-gap"><summary>Dán sao kê hàng loạt</summary><form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'khtc_gd' );
		echo '<div class="khtc-loc"><label>Nạp vào tài khoản<select name="dan_ngan_hang_id" required>';
		foreach ( $ngan_hang as $b ) { printf( '<option value="%d">%s</option>', (int) $b->id, esc_html( $b->ten ) ); }
		echo '</select></label></div>';
		echo '<p class="khtc-sub">Mỗi dòng: <code>Ngày (dd/mm/yyyy) · Diễn giải · Số tiền · Thu/Chi · Mã giao dịch</code> — cách nhau bằng Tab (copy thẳng từ Excel) hoặc dấu phẩy. Bỏ trống cột Thu/Chi thì số dương là Thu, số âm là Chi.</p>';
		echo '<p class="khtc-sub"><strong>Dán chồng kỳ cũng không sao.</strong> Dòng nào đã có sẵn trong tài khoản này — nhận ra qua <em>mã giao dịch</em> ở cột 5 — sẽ bị bỏ qua, nên tháng nào cũng tải cả file về dán cũng không nhân đôi số dư. Cột mã để trống thì máy không chặn được, dán lại là vào thêm lần nữa.</p>';
		KHTC_Tep::o_nap( 'sao_ke', '20/07/2026&#9;Thu tien khach ABC&#9;1.500.000&#9;Thu&#10;21/07/2026&#9;Chi tra nha cung cap&#9;850.000&#9;Chi', 7 );
		echo '<p><button type="submit" name="khtc_dan" value="1" class="button button-primary">Nạp sao kê</button></p></form></details>';

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

		if ( isset( $_POST['khtc_nap_dong'] ) && check_admin_referer( 'khtc_ds' ) && null !== ( $vb = self::lay_bang( 'bang_cong', $bao_loi ) ) ) {
			$mo = (int) $_POST['khtc_nap_dong'];
			$kq = KHTC_DoiSoat::nap_dong( $mo, $vb );
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

		if ( isset( $_POST['khtc_gop_thang'] ) && check_admin_referer( 'khtc_ds' ) ) {
			$g = KHTC_DoiSoat::gop_ve_thang();
			$bao_ok = sprintf(
				'Đã gộp về đợt tháng: dồn %s dòng, bỏ %s dòng trùng mã, xoá %s đợt rỗng.%s',
				number_format( $g['don'], 0, ',', '.' ), number_format( $g['trung'], 0, ',', '.' ), number_format( $g['xoa'], 0, ',', '.' ),
				$g['dich'] ? ' Đợt tháng: ' . implode( ', ', $g['dich'] ) . '.' : ' Không có gì để gộp.'
			);
			$mo = 0;
		}

		if ( isset( $_POST['khtc_xoa_dot'] ) && check_admin_referer( 'khtc_ds' ) ) {
			$kq_xoa = KHTC_DoiSoat::xoa_dot( (int) $_POST['khtc_xoa_dot'] );
			if ( is_wp_error( $kq_xoa ) ) {
				$bao_loi = $kq_xoa->get_error_message();
			} else {
				$mo     = 0;
				$bao_ok = 'Đã xoá đợt đối soát.';
			}
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

		echo '<details class="khtc-panel khtc-gap"><summary>Tạo đợt đối soát</summary><form method="post"><div class="khtc-loc">';
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
		echo '</div></form></details>';

		$ds = KHTC_DoiSoat::ds_dot();
		echo '<div class="khtc-panel"><h2>Các đợt đã tạo</h2>';
		echo '<form method="post" class="khtc-loc" onsubmit="return confirm(\'Dồn mọi đợt lẻ về đợt tháng của từng kênh (Payoo KH989 tháng 9/2026…)? Dòng trùng mã bỏ, đợt rỗng xoá. Không mất tiền, chỉ đổi chỗ.\')">';
		wp_nonce_field( 'khtc_ds' );
		echo '<button type="submit" name="khtc_gop_thang" value="1" class="button">Gộp các đợt về đợt tháng</button>';
		echo '<span class="khtc-sub">Mỗi kênh mỗi tháng một đợt. Đợt lẻ theo ngày từ bản cũ dồn hết về đây; nạp mới đã tự vào đợt tháng.</span></form>';
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

		// Cảnh báo đứng TRƯỚC bốn bảng: đọc xong bảng rồi mới biết kết quả
		// không đáng tin thì đã đọc nhầm mất rồi.
		if ( $kq['canh_bao'] ) {
			printf( '<p class="khtc-canh-bao">%s</p>', esc_html( $kq['canh_bao'] ) );
		}
		if ( $kq['theo_luot'] ) {
			$nhan = array();
			foreach ( $kq['theo_luot'] as $k => $n ) {
				$nhan[] = esc_html( KHTC_DoiSoat::ten_kieu( $k ) . ': ' . $n );
			}
			printf( '<p class="khtc-sub">Ghép nhờ: %s. Khớp theo mã giao dịch là chắc nhất; khớp theo ngày và số tiền chỉ nên tin khi sao kê ít dòng trùng mệnh giá.</p>', implode( ' · ', $nhan ) );
		}

		echo '<details class="khtc-panel khtc-gap"><summary>Nạp bảng cổng gửi về</summary><form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'khtc_ds' );
		echo '<p class="khtc-sub">Mỗi dòng: <code>Ngày · Mã GD · Số tiền · Phí · Nội dung</code> — cách nhau bằng Tab (copy thẳng từ file cổng) hoặc dấu phẩy. Thiếu cột Phí thì để trống. Dòng trùng mã giao dịch với dòng đã nạp sẽ bị bỏ qua.</p>';
		KHTC_Tep::o_nap( 'bang_cong', '05/08/2026&#9;PAY123456&#9;1.000.000&#9;11.000&#9;Thanh toan QR&#10;05/08/2026&#9;PAY123457&#9;250.000&#9;2.750&#9;Thanh toan the', 7 );
		printf( '<p><button type="submit" name="khtc_nap_dong" value="%d" class="button button-primary">Nạp và đối soát</button></p>', (int) $d->id );
		echo '</form></details>';

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

	// -------------------------------------------------------------- chi phí

	public static function chi_phi() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		if ( isset( $_POST['khtc_them_cp'] ) && check_admin_referer( 'khtc_cp' ) ) {
			$kq = KHTC_ChiPhi::them(
				array(
					'ngay'         => sanitize_text_field( wp_unslash( $_POST['ngay'] ?? '' ) ),
					'bo_phan'      => sanitize_text_field( wp_unslash( $_POST['bo_phan'] ?? '' ) ),
					'khoan_muc'    => sanitize_text_field( wp_unslash( $_POST['khoan_muc'] ?? '' ) ),
					'nha_cung_cap' => sanitize_text_field( wp_unslash( $_POST['nha_cung_cap'] ?? '' ) ),
					'so_tien'      => sanitize_text_field( wp_unslash( $_POST['so_tien'] ?? '' ) ),
					'so_ct'        => sanitize_text_field( wp_unslash( $_POST['so_ct'] ?? '' ) ),
					'dien_giai'    => sanitize_text_field( wp_unslash( $_POST['dien_giai'] ?? '' ) ),
					'hinh_thuc'    => sanitize_text_field( wp_unslash( $_POST['hinh_thuc'] ?? '' ) ),
					'ngan_hang_id' => (int) ( $_POST['ngan_hang_id'] ?? 0 ),
				)
			);
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã ghi khoản chi.'; }
		}

		if ( isset( $_POST['khtc_dan_cp'] ) && check_admin_referer( 'khtc_cp' ) && null !== ( $vb = self::lay_bang( 'bang_chi_phi', $bao_loi ) ) ) {
			$kq = KHTC_ChiPhi::dan_hang_loat(
				$vb,
				(int) ( $_POST['dan_ngan_hang_id'] ?? 0 ),
				sanitize_text_field( wp_unslash( $_POST['dan_hinh_thuc'] ?? 'chuyen_khoan' ) )
			);
			$bao_ok = 'Đã nạp ' . $kq['them'] . ' khoản chi.';
			if ( $kq['loi'] ) {
				$bao_loi = implode( ' · ', array_slice( $kq['loi'], 0, 5 ) )
					. ( count( $kq['loi'] ) > 5 ? ' … và ' . ( count( $kq['loi'] ) - 5 ) . ' dòng nữa' : '' );
			}
		}

		if ( isset( $_POST['khtc_xoa_cp'] ) && check_admin_referer( 'khtc_cp' ) ) {
			$kq = KHTC_ChiPhi::xoa( (int) $_POST['khtc_xoa_cp'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã xoá khoản chi. Phục hồi được ở mục Nhật ký.'; }
		}

		if ( isset( $_POST['khtc_luu_dm'] ) && check_admin_referer( 'khtc_cp' ) ) {
			foreach ( array( 'bo_phan', 'khoan_muc' ) as $loai ) {
				$kq = KHTC_ChiPhi::luu_danh_muc( $loai, wp_unslash( $_POST[ 'dm_' . $loai ] ?? '' ) );
				if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); }
			}
			if ( ! $bao_loi ) { $bao_ok = 'Đã lưu danh mục.'; }
		}

		$ngan_hang = KHTC_NganHang::ds();
		$bo_phan   = KHTC_ChiPhi::danh_muc( 'bo_phan' );
		$khoan_muc = KHTC_ChiPhi::danh_muc( 'khoan_muc' );

		list( $d1, $d2 ) = KHTC_UI::thang_nay();
		$loc   = array(
			'tu'        => sanitize_text_field( wp_unslash( $_GET['tu'] ?? $d1 ) ),
			'den'       => sanitize_text_field( wp_unslash( $_GET['den'] ?? $d2 ) ),
			'bo_phan'   => sanitize_text_field( wp_unslash( $_GET['bp'] ?? '' ) ),
			'khoan_muc' => sanitize_text_field( wp_unslash( $_GET['km'] ?? '' ) ),
			'da_tra'    => isset( $_GET['tra'] ) ? sanitize_text_field( wp_unslash( $_GET['tra'] ) ) : '',
			'tim'       => sanitize_text_field( wp_unslash( $_GET['tim'] ?? '' ) ),
			'trang'     => (int) ( $_GET['trang'] ?? 1 ),
		);
		$kq    = KHTC_ChiPhi::loc( $loc );
		$cheo  = KHTC_ChiPhi::bang_cheo( $loc );

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Chi phí' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		KHTC_UI::the_so(
			array(
				array( 'Tổng chi phí trong kỳ', KHTC_UI::tien( $kq['tong'] ) ),
				array( 'Đã thấy tiền ra', KHTC_UI::tien( $kq['da_tra'] ), 'thu' ),
				array( 'Chưa thấy tiền ra', KHTC_UI::tien( $kq['chua_tra'] ), $kq['chua_tra'] ? 'chi' : '' ),
				array( 'Số khoản', number_format( $kq['so_dong'], 0, ',', '.' ) ),
			)
		);
		printf(
			'<p class="khtc-sub">"Đã thấy tiền ra" nghĩa là khoản chi đã ghép được với một dòng chi trong sao kê — chạy ở mục <a href="%s">Đối soát chi phí</a>.</p>',
			esc_url( self::url( 'doi-soat-chi-phi' ) )
		);

		// ---- lọc
		printf( '<div class="khtc-panel"><h2>Lọc</h2><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'chi-phi' ) ) );
		self::an_get( 'chi-phi' );
		printf( '<label>Từ ngày<input type="date" name="tu" value="%s"></label>', esc_attr( $loc['tu'] ) );
		printf( '<label>Đến ngày<input type="date" name="den" value="%s"></label>', esc_attr( $loc['den'] ) );
		echo '<label>Bộ phận<select name="bp"><option value="">— Tất cả —</option>';
		foreach ( $bo_phan as $b ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $b ), selected( $loc['bo_phan'], $b, false ), esc_html( $b ) ); }
		echo '</select></label>';
		echo '<label>Khoản mục<select name="km"><option value="">— Tất cả —</option>';
		foreach ( $khoan_muc as $k ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $loc['khoan_muc'], $k, false ), esc_html( $k ) ); }
		echo '</select></label>';
		echo '<label>Tiền ra<select name="tra"><option value="">— Cả hai —</option>';
		printf( '<option value="1"%s>Đã thấy</option>', selected( $loc['da_tra'], '1', false ) );
		printf( '<option value="0"%s>Chưa thấy</option>', selected( $loc['da_tra'], '0', false ) );
		echo '</select></label>';
		printf( '<label>Tìm nhà cung cấp / diễn giải<input type="search" name="tim" value="%s"></label>', esc_attr( $loc['tim'] ) );
		echo '<button type="submit" class="button">Lọc</button>';
		printf( '<a href="%s" class="button">Bỏ lọc</a>', esc_url( self::url( 'chi-phi' ) ) );
		echo '</div></form></div>';

		// ---- bảng cộng chéo
		echo '<div class="khtc-panel"><h2>Cộng chéo — khoản mục × bộ phận</h2>';
		if ( ! $cheo['bo_phan'] ) {
			echo '<div class="khtc-trong">Chưa có khoản chi nào trong kỳ đang lọc.</div>';
		} else {
			echo '<table><thead><tr><th>Khoản mục</th>';
			foreach ( $cheo['bo_phan'] as $bp ) { printf( '<th class="so">%s</th>', esc_html( $bp ) ); }
			echo '<th class="so">Tổng</th></tr></thead><tbody>';
			foreach ( $cheo['khoan_muc'] as $km ) {
				printf( '<tr><td>%s</td>', esc_html( $km ) );
				foreach ( $cheo['bo_phan'] as $bp ) {
					$v = $cheo['o'][ $km ][ $bp ] ?? 0;
					printf( '<td class="so">%s</td>', $v ? esc_html( KHTC_UI::tien( $v ) ) : '' );
				}
				printf( '<td class="so"><strong>%s</strong></td></tr>', esc_html( KHTC_UI::tien( $cheo['tong_hang'][ $km ] ) ) );
			}
			echo '</tbody><tfoot><tr><th>Tổng</th>';
			foreach ( $cheo['bo_phan'] as $bp ) {
				printf( '<th class="so">%s</th>', esc_html( KHTC_UI::tien( $cheo['tong_cot'][ $bp ] ?? 0 ) ) );
			}
			printf( '<th class="so">%s</th></tr></tfoot>', esc_html( KHTC_UI::tien( $cheo['tong'] ) ) );
			echo '</table>';
		}
		echo '</div>';

		// ---- ghi một khoản
		echo '<details class="khtc-panel khtc-gap"><summary>Ghi một khoản chi</summary><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_cp' );
		echo '<label>Ngày<input type="date" name="ngay" required></label>';
		echo '<label>Bộ phận<select name="bo_phan" required>';
		foreach ( $bo_phan as $b ) { printf( '<option value="%s">%s</option>', esc_attr( $b ), esc_html( $b ) ); }
		echo '</select></label>';
		echo '<label>Khoản mục<select name="khoan_muc">';
		foreach ( $khoan_muc as $k ) { printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $k ) ); }
		echo '</select></label>';
		echo '<label>Nhà cung cấp<input type="text" name="nha_cung_cap" style="min-width:180px"></label>';
		echo '<label>Số tiền<input type="text" name="so_tien" placeholder="2.400.000" required></label>';
		echo '<label>Số chứng từ<input type="text" name="so_ct" placeholder="để đối soát"></label>';
		echo '<label>Hình thức<select name="hinh_thuc"><option value="chuyen_khoan">Chuyển khoản</option><option value="tien_mat">Tiền mặt</option></select></label>';
		echo '<label>Từ tài khoản<select name="ngan_hang_id"><option value="0">— Chưa rõ —</option>';
		foreach ( $ngan_hang as $b ) { printf( '<option value="%d">%s</option>', (int) $b->id, esc_html( $b->ten ) ); }
		echo '</select></label>';
		echo '<label>Diễn giải<input type="text" name="dien_giai" style="min-width:200px"></label>';
		echo '<button type="submit" name="khtc_them_cp" value="1" class="button button-primary">Ghi</button>';
		echo '</div><p class="khtc-sub">Chi tiền mặt không đi qua ngân hàng nên đối soát chi phí sẽ bỏ qua — chọn đúng hình thức để nó không bị báo "chưa thấy tiền ra" oan.</p></form></details>';

		// ---- dán hàng loạt
		echo '<details class="khtc-panel khtc-gap"><summary>Dán bảng chi phí</summary><form method="post" enctype="multipart/form-data"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_cp' );
		echo '<label>Hình thức<select name="dan_hinh_thuc"><option value="chuyen_khoan">Chuyển khoản</option><option value="tien_mat">Tiền mặt</option></select></label>';
		echo '<label>Từ tài khoản<select name="dan_ngan_hang_id"><option value="0">— Chưa rõ —</option>';
		foreach ( $ngan_hang as $b ) { printf( '<option value="%d">%s</option>', (int) $b->id, esc_html( $b->ten ) ); }
		echo '</select></label></div>';
		echo '<p class="khtc-sub">Mỗi dòng: <code>Ngày · Bộ phận · Khoản mục · Nhà cung cấp · Số tiền · Số chứng từ · Diễn giải</code> — cách nhau bằng Tab hoặc dấu phẩy. Hai cột cuối không bắt buộc.</p>';
		KHTC_Tep::o_nap( 'bang_chi_phi', '10/08/2026&#9;Khu vui chơi&#9;Tiền điện&#9;EVN HCMC&#9;2.400.000&#9;HD00123&#9;Dien thang 7&#10;12/08/2026&#9;Văn phòng&#9;Vật tư — tiêu hao&#9;VP Hong Ha&#9;780.000', 7 );
		echo '<p><button type="submit" name="khtc_dan_cp" value="1" class="button button-primary">Nạp bảng</button></p></form></details>';

		// ---- danh sách
		echo '<div class="khtc-panel"><h2>Danh sách khoản chi</h2>';
		if ( ! $kq['rows'] ) {
			echo '<div class="khtc-trong">Không có khoản nào khớp bộ lọc.</div>';
		} else {
			echo '<table><thead><tr><th>Ngày</th><th>Bộ phận</th><th>Khoản mục</th><th>Nhà cung cấp</th><th>Số CT</th><th class="so">Số tiền</th><th>Tiền ra</th><th></th></tr></thead><tbody>';
			foreach ( $kq['rows'] as $c ) {
				echo '<tr>';
				printf(
					'<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="so chi">%s</td>',
					esc_html( KHTC_UI::ngay( $c->ngay ) ),
					esc_html( $c->bo_phan ),
					esc_html( $c->khoan_muc ),
					esc_html( $c->nha_cung_cap ? $c->nha_cung_cap : '—' ),
					esc_html( $c->so_ct ? $c->so_ct : '—' ),
					esc_html( KHTC_UI::tien( $c->so_tien ) )
				);
				if ( 'tien_mat' === $c->hinh_thuc ) {
					echo '<td class="khtc-sub">Tiền mặt</td>';
				} elseif ( (int) $c->giao_dich_id ) {
					printf( '<td class="thu">%s</td>', esc_html( KHTC_UI::ngay( $c->gd_ngay ) ) );
				} else {
					echo '<td class="chi">Chưa thấy</td>';
				}
				echo '<td><form method="post" onsubmit="return confirm(\'Xoá khoản chi này?\')">';
				wp_nonce_field( 'khtc_cp' );
				printf( '<button type="submit" name="khtc_xoa_cp" value="%d" class="button button-small">Xoá</button>', (int) $c->id );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
			self::phan_trang( 'chi-phi', $kq, array( 'tu' => $loc['tu'], 'den' => $loc['den'], 'bp' => $loc['bo_phan'], 'km' => $loc['khoan_muc'], 'tra' => $loc['da_tra'], 'tim' => $loc['tim'] ) );
		}
		echo '</div>';

		// ---- danh mục
		echo '<details class="khtc-panel khtc-gap"><summary>Danh mục</summary><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_cp' );
		printf(
			'<label style="flex:1 1 280px">Bộ phận — mỗi dòng một cái<textarea name="dm_bo_phan" rows="6">%s</textarea></label>',
			esc_textarea( implode( "\n", $bo_phan ) )
		);
		printf(
			'<label style="flex:1 1 280px">Khoản mục — mỗi dòng một cái<textarea name="dm_khoan_muc" rows="6">%s</textarea></label>',
			esc_textarea( implode( "\n", $khoan_muc ) )
		);
		echo '</div><p><button type="submit" name="khtc_luu_dm" value="1" class="button">Lưu danh mục</button></p>';
		echo '<p class="khtc-sub">Sửa tên ở đây không đổi các khoản đã ghi — chúng giữ nguyên tên cũ. Đổi tên rồi thì lọc theo tên mới sẽ không thấy khoản cũ.</p>';
		echo '</form></details></div>';
	}

	/** Thanh phân trang dùng chung. */
	private static function phan_trang( $man, $kq, $giu ) {
		if ( $kq['so_trang'] <= 1 ) { return; }
		$giu = array_filter( $giu, function ( $v ) { return '' !== $v && 0 !== $v; } );
		echo '<p class="khtc-sub">Trang ' . (int) $kq['trang'] . ' / ' . (int) $kq['so_trang'] . ' — ';
		if ( $kq['trang'] > 1 ) {
			printf( '<a href="%s">← Trước</a> ', esc_url( self::url( $man, $giu + array( 'trang' => $kq['trang'] - 1 ) ) ) );
		}
		if ( $kq['trang'] < $kq['so_trang'] ) {
			printf( '<a href="%s">Sau →</a>', esc_url( self::url( $man, $giu + array( 'trang' => $kq['trang'] + 1 ) ) ) );
		}
		echo '</p>';
	}

	// ----------------------------------------------------- đối soát chi phí

	public static function doi_soat_chi_phi() {
		KHTC_UI::nhan_doi_cty();
		$ngan_hang = KHTC_NganHang::ds();
		list( $d1, $d2 ) = KHTC_UI::thang_nay();
		$tu  = sanitize_text_field( wp_unslash( $_GET['tu'] ?? $d1 ) );
		$den = sanitize_text_field( wp_unslash( $_GET['den'] ?? $d2 ) );
		$nh  = (int) ( $_GET['nh'] ?? 0 );
		$chay = isset( $_GET['chay'] );

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Đối soát chi phí' );

		printf( '<div class="khtc-panel"><h2>Chọn kỳ</h2><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'doi-soat-chi-phi' ) ) );
		self::an_get( 'doi-soat-chi-phi' );
		printf( '<label>Từ ngày<input type="date" name="tu" value="%s" required></label>', esc_attr( $tu ) );
		printf( '<label>Đến ngày<input type="date" name="den" value="%s" required></label>', esc_attr( $den ) );
		echo '<label>Tài khoản<select name="nh"><option value="">— Tất cả —</option>';
		foreach ( $ngan_hang as $b ) { printf( '<option value="%d"%s>%s</option>', (int) $b->id, selected( $nh, $b->id, false ), esc_html( $b->ten ) ); }
		echo '</select></label>';
		echo '<input type="hidden" name="chay" value="1">';
		echo '<button type="submit" class="button button-primary">Chạy đối soát</button>';
		echo '</div></form>';
		echo '<p class="khtc-sub">Ghép chứng từ chi phí (chỉ loại <em>chuyển khoản</em>) với các dòng <em>chi</em> trong sao kê. Dùng đúng phép ghép bốn lượt của đối soát cổng: trùng số chứng từ → trùng ngày và số tiền → trùng số tiền lệch ngày trong T+3.</p></div>';

		if ( ! $chay ) {
			echo '<div class="khtc-panel"><div class="khtc-trong">Chọn kỳ rồi bấm <strong>Chạy đối soát</strong>.</div></div></div>';
			return;
		}

		$r = KHTC_ChiPhi::doi_soat( $tu, $den, $nh );
		KHTC_UI::the_so(
			array(
				array( 'Chứng từ khớp tiền ra', KHTC_UI::tien( $r['tien_khop'] ), 'thu' ),
				array( 'Có chứng từ, chưa thấy tiền ra', KHTC_UI::tien( $r['tien_chua_chi'] ), $r['tien_chua_chi'] ? 'chi' : '' ),
				array( 'Tiền ra, không có chứng từ', KHTC_UI::tien( $r['tien_thua'] ), $r['tien_thua'] ? 'chi' : '' ),
				array( 'Số chứng từ đã ghép', number_format( count( $r['khop'] ), 0, ',', '.' ) ),
			)
		);

		printf(
			'<div class="khtc-panel"><h2>Khớp <span class="khtc-dem thu">%d khoản · %s</span></h2>',
			count( $r['khop'] ),
			esc_html( KHTC_UI::tien( $r['tien_khop'] ) )
		);
		if ( ! $r['khop'] ) {
			echo '<div class="khtc-trong">Không ghép được khoản nào.</div></div>';
		} else {
			echo '<table><thead><tr><th>Ngày CT</th><th>Bộ phận</th><th>Khoản mục</th><th>Nhà cung cấp</th><th class="so">Số tiền</th><th>Ngày NH</th><th>Diễn giải sao kê</th><th>Kiểu ghép</th></tr></thead><tbody>';
			foreach ( $r['khop'] as $c ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="so">%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( KHTC_UI::ngay( $c->ngay ) ),
					esc_html( $c->bo_phan ),
					esc_html( $c->khoan_muc ),
					esc_html( $c->nha_cung_cap ? $c->nha_cung_cap : '—' ),
					esc_html( KHTC_UI::tien( $c->so_tien ) ),
					esc_html( KHTC_UI::ngay( $c->gd->ngay ) ),
					esc_html( $c->gd->dien_giai ),
					esc_html( KHTC_DoiSoat::ten_kieu( $c->kieu_khop ) )
				);
			}
			echo '</tbody></table></div>';
		}

		printf(
			'<div class="khtc-panel"><h2>Có chứng từ, chưa thấy tiền ra <span class="khtc-dem chi">%d khoản · %s</span></h2>',
			count( $r['chua_chi'] ),
			esc_html( KHTC_UI::tien( $r['tien_chua_chi'] ) )
		);
		if ( ! $r['chua_chi'] ) {
			echo '<div class="khtc-trong">Không có khoản nào.</div></div>';
		} else {
			echo '<table><thead><tr><th>Ngày</th><th>Bộ phận</th><th>Khoản mục</th><th>Nhà cung cấp</th><th>Số CT</th><th class="so">Số tiền</th></tr></thead><tbody>';
			foreach ( $r['chua_chi'] as $c ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="so chi">%s</td></tr>',
					esc_html( KHTC_UI::ngay( $c->ngay ) ),
					esc_html( $c->bo_phan ),
					esc_html( $c->khoan_muc ),
					esc_html( $c->nha_cung_cap ? $c->nha_cung_cap : '—' ),
					esc_html( $c->so_ct ? $c->so_ct : '—' ),
					esc_html( KHTC_UI::tien( $c->so_tien ) )
				);
			}
			echo '</tbody></table></div>';
		}

		printf(
			'<div class="khtc-panel"><h2>Tiền ra, không có chứng từ <span class="khtc-dem chi">%d dòng · %s</span></h2>',
			count( $r['thua'] ),
			esc_html( KHTC_UI::tien( $r['tien_thua'] ) )
		);
		if ( ! $r['thua'] ) {
			echo '<div class="khtc-trong">Mọi dòng chi trong kỳ đều có chứng từ.</div></div>';
		} else {
			echo '<table><thead><tr><th>Ngày</th><th class="so">Số tiền</th><th>Diễn giải sao kê</th></tr></thead><tbody>';
			foreach ( $r['thua'] as $g ) {
				printf(
					'<tr><td>%s</td><td class="so chi">%s</td><td>%s</td></tr>',
					esc_html( KHTC_UI::ngay( $g->ngay ) ),
					esc_html( KHTC_UI::tien( $g->so_tien ) ),
					esc_html( $g->dien_giai )
				);
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	// -------------------------------------------------------------- sao lưu

	public static function sao_luu() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		if ( isset( $_POST['khtc_nap_mau'] ) && check_admin_referer( 'khtc_sl' ) ) {
			$kq = KHTC_Mau::nap();
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
			} else {
				$bao_ok = sprintf( 'Đã nạp %s dòng dữ liệu mẫu vào %s. Mở các mục bên trái để xem thử.', number_format( $kq, 0, ',', '.' ), KHTC_Cty::ten() );
			}
		}

		if ( isset( $_POST['khtc_xoa_mau'] ) && check_admin_referer( 'khtc_sl' ) ) {
			$bao_ok = sprintf( 'Đã xoá %s dòng dữ liệu mẫu.', number_format( KHTC_Mau::xoa(), 0, ',', '.' ) );
		}

		if ( isset( $_POST['khtc_hoan_doi'] ) && check_admin_referer( 'khtc_sl' ) ) {
			$doi = KHTC_SaoLuu::hoan_doi_cty( ! empty( $_POST['kem_diem'] ) );
			$cau = array();
			foreach ( $doi as $b => $n ) { if ( $n ) { $cau[] = KHTC_NhatKy::ten_bang( $b ) . ' ' . number_format( $n, 0, ',', '.' ); } }
			$bao_ok = 'Đã hoán đổi KH Cũ ↔ KH Mới cho: ' . ( $cau ? implode( ' · ', $cau ) : 'không có dòng nào' ) . '. Xem lại bảng dưới: tài khoản 11521268 / 8660077020 và các đợt KH989 phải nằm ở KH Cũ.';
		}

		if ( isset( $_POST['khtc_nhap_kem'] ) && check_admin_referer( 'khtc_sl' ) ) {
			$ten = sanitize_file_name( wp_unslash( $_POST['khtc_nhap_kem'] ) );
			$kq  = KHTC_SaoLuu::nhap_tep_kem( $ten );
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
			} else {
				$bao_ok = sprintf(
					'Đã nhập %s dòng từ %s.',
					number_format( array_sum( $kq['them'] ), 0, ',', '.' ),
					$ten
				);
			}
		}

		if ( isset( $_POST['khtc_nhap'] ) && check_admin_referer( 'khtc_sl' ) ) {
			$json = '';
			if ( ! empty( $_FILES['tep']['tmp_name'] ) && is_uploaded_file( $_FILES['tep']['tmp_name'] ) ) {
				$json = file_get_contents( $_FILES['tep']['tmp_name'] );
			} elseif ( ! empty( $_POST['json'] ) ) {
				$json = wp_unslash( $_POST['json'] );
			}
			if ( '' === trim( (string) $json ) ) {
				$bao_loi = 'Chưa chọn tệp sao lưu, cũng chưa dán nội dung.';
			} else {
				$kq = KHTC_SaoLuu::nhap( $json );
				if ( is_wp_error( $kq ) ) {
					$bao_loi = $kq->get_error_message();
				} else {
					$phan = array();
					foreach ( $kq['them'] as $t => $n ) { $phan[] = $t . ': ' . number_format( $n, 0, ',', '.' ); }
					$bao_ok = 'Đã nhập — ' . implode( ' · ', $phan );
					if ( $kq['bo'] ) {
						$bo = array();
						foreach ( $kq['bo'] as $t => $n ) { $bo[] = $t . ': ' . number_format( $n, 0, ',', '.' ); }
						$bao_loi = 'Bị bỏ qua vì trùng khoá (hầu hết là số hoá đơn đã có trong sổ) — ' . implode( ' · ', $bo );
					}
				}
			}
		}

		$dem = KHTC_SaoLuu::dem();

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Sao lưu' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		echo '<div class="khtc-panel"><h2>Đang có trong kho</h2><table><thead><tr><th>Bảng</th><th class="so">Số dòng</th></tr></thead><tbody>';
		$ten = array(
			'ngan_hang' => 'Tài khoản ngân hàng',
			'giao_dich' => 'Giao dịch / sao kê',
			'doi_soat'  => 'Đợt đối soát',
			'ds_dong'   => 'Dòng cổng thanh toán',
			'chi_phi'   => 'Khoản chi phí',
			'hd_ra'     => 'Hoá đơn đầu ra',
			'hd_vao'    => 'Hoá đơn đầu vào',
			'hop_dong'  => 'Hợp đồng',
			'ho_so'     => 'Hồ sơ',
		);
		foreach ( $dem as $t => $n ) {
			printf( '<tr><td>%s</td><td class="so">%s</td></tr>', esc_html( $ten[ $t ] ?? $t ), esc_html( number_format( $n, 0, ',', '.' ) ) );
		}
		echo '</tbody></table>';
		printf(
			'<p><a class="button button-primary" href="%s">Tải tệp sao lưu (.json)</a></p>',
			esc_url( wp_nonce_url( self::url( 'sao-luu', array( 'khtc_sao_luu' => 1 ) ), 'khtc_sao_luu' ) )
		);
		echo '<p class="khtc-sub">Tệp gồm cả hai pháp nhân và cả danh mục chi phí. Dữ liệu nằm trong cơ sở dữ liệu của website — website đổi host hoặc plugin bị gỡ nhầm là mất, nên nên tải về mỗi lần chốt sổ.</p></div>';

		// ---- dữ liệu mẫu
		$so_mau = KHTC_Mau::dem_mau();
		$that   = KHTC_Mau::du_lieu_that();
		echo '<div class="khtc-panel"><h2>Dữ liệu mẫu để chạy thử</h2>';
		if ( $so_mau ) {
			printf(
				'<div class="khtc-bao ok">Đang có <strong>%s dòng dữ liệu mẫu</strong> trong sổ %s. Xem thử xong thì xoá đi trước khi nhập số thật.</div>',
				esc_html( number_format( $so_mau, 0, ',', '.' ) ),
				esc_html( KHTC_Cty::ten() )
			);
			echo '<form method="post" onsubmit="return confirm(\'Xoá toàn bộ dữ liệu mẫu đã nạp?\')">';
			wp_nonce_field( 'khtc_sl' );
			echo '<button type="submit" name="khtc_xoa_mau" value="1" class="button button-primary">Xoá dữ liệu mẫu</button></form>';
			echo '<p class="khtc-sub">Xoá đúng những dòng đã nạp, ghi theo id lúc tạo — dòng anh tự nhập xen vào vẫn còn nguyên.</p>';
		} elseif ( $that ) {
			$mo_ta = array();
			foreach ( $that as $t => $n ) { $mo_ta[] = KHTC_NhatKy::ten_bang( $t ) . ' ' . number_format( $n, 0, ',', '.' ); }
			printf(
				'<div class="khtc-bao loi">Sổ %s đã có dữ liệu thật (%s) nên không nạp dữ liệu mẫu được. Đổi sang pháp nhân còn trống ở góc trên bên phải rồi nạp.</div>',
				esc_html( KHTC_Cty::ten() ),
				esc_html( implode( ' · ', $mo_ta ) )
			);
			echo '<p class="khtc-sub">Đây là chặn cố ý, không phải lỗi: một hoá đơn mẫu lọt vào tờ khai GTGT đem nộp là chuyện không sửa lại được.</p>';
		} else {
			printf(
				'<p class="khtc-sub">Nạp một bộ số liệu giả vào sổ <strong>%s</strong> để xem mọi màn hình chạy ra sao: 13 tháng sao kê hai tài khoản, hoá đơn đầu ra và đầu vào, chi phí, một đợt đối soát cổng, hợp đồng thuê, hồ sơ chứng từ. Các số được dựng <em>ăn khớp với nhau</em> nên đối soát, công nợ và báo cáo đều ra kết quả thật chứ không rỗng.</p>',
				esc_html( KHTC_Cty::ten() )
			);
			echo '<form method="post">';
			wp_nonce_field( 'khtc_sl' );
			echo '<button type="submit" name="khtc_nap_mau" value="1" class="button button-primary">Nạp dữ liệu mẫu</button></form>';
			echo '<p class="khtc-sub">Mọi dòng mẫu đều mang dấu <code>[Mẫu]</code> ở tên tài khoản, tên gian và số hoá đơn. Xoá lại bằng một nút, và nút xoá chỉ đụng đúng những dòng đã nạp.</p>';
		}
		echo '</div>';

		// ---- Hoán đổi pháp nhân: sửa bản dữ liệu 1.2–1.3 gán ngược hai công ty.
		$tt = KHTC_SaoLuu::tom_tat_cty();
		echo '<div class="khtc-panel"><h2>Hai pháp nhân đang giữ gì</h2>';
		echo '<p class="khtc-sub">KH Cũ là KH989 (CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H): tài khoản 11521268 MB, 8660077020 BIDV. KH Mới là KH705: 02865168 MB, 8613600999 BIDV, 8690077021 BIDV. Nếu bảng dưới thấy ngược lại là sổ đang mang nhãn sai — bản dữ liệu cài từ bản 1.2–1.3 bị thế.</p>';
		echo '<table><thead><tr><th>Pháp nhân</th><th>Tài khoản ngân hàng</th><th class="so">Sao kê</th><th class="so">Đợt cổng</th><th class="so">Hoá đơn ra</th><th class="so">Đơn app</th><th class="so">Điểm (danh mục)</th></tr></thead><tbody>';
		foreach ( $tt as $cty => $x ) {
			printf(
				'<tr><td><strong>%s</strong> <span class="khtc-sub">%s</span></td><td>%s</td><td class="so">%s</td><td class="so">%s</td><td class="so">%s</td><td class="so">%s</td><td class="so">%s</td></tr>',
				esc_html( KHTC_Cty::ten( $cty ) ), esc_html( KHTC_Cty::ma( $cty ) ),
				$x['tai_khoan'] ? esc_html( implode( ' · ', $x['tai_khoan'] ) ) : '<em>chưa có</em>',
				number_format( $x['so']['giao_dich'], 0, ',', '.' ), number_format( $x['so']['doi_soat'], 0, ',', '.' ), number_format( $x['so']['hd_ra'], 0, ',', '.' ), number_format( $x['so']['don_app'], 0, ',', '.' ), number_format( $x['so']['diem'], 0, ',', '.' )
			);
		}
		echo '</tbody></table>';
		echo '<form method="post" onsubmit="return confirm(\'Hoán đổi nhãn KH Cũ và KH Mới cho toàn bộ sổ (sao kê, đợt cổng, hoá đơn, chi phí, hợp đồng, hồ sơ, đơn app)? Danh mục điểm giữ nguyên trừ khi tick kèm.\')">';
		wp_nonce_field( 'khtc_sl' );
		echo '<div class="khtc-loc"><label class="khtc-tick-nho"><input type="checkbox" name="kem_diem" value="1"> đổi cả danh mục điểm</label>';
		echo '<button type="submit" name="khtc_hoan_doi" value="1" class="button">Hoán đổi KH Cũ ↔ KH Mới</button></div>';
		echo '<p class="khtc-sub">Chỉ đổi nhãn, không xoá không sửa số. Bấm hai lần là về như cũ. Danh mục điểm để nguyên vì thường nạp sau theo đúng nhãn; sổ và danh mục lệch nhau chính là dấu hiệu cần bấm nút này.</p></div>';

		$kem = KHTC_SaoLuu::tep_kem();
		if ( $kem ) {
			echo '<div class="khtc-panel"><h2>Dữ liệu kèm trong bản cài</h2>';
			echo '<p class="khtc-sub">Bản cài này có sẵn tệp dữ liệu, không phải tải lên lần nữa. Nhập là <strong>thêm vào</strong> kho đang có.</p>';
			echo '<form method="post"><div class="khtc-loc">';
			wp_nonce_field( 'khtc_sl' );
			foreach ( $kem as $ten => $duong_dan ) {
				printf(
					'<button type="submit" name="khtc_nhap_kem" value="%s" class="button" onclick="return confirm(\'Nhập THÊM dữ liệu từ %s vào kho đang có?\')">Nhập %s <span class="khtc-sub">(%s)</span></button>',
					esc_attr( $ten ),
					esc_js( $ten ),
					esc_html( $ten ),
					esc_html( size_format( (int) filesize( $duong_dan ) ) )
				);
			}
			echo '</div></form>';
			echo '<p class="khtc-sub">Tệp lớn có thể chạy lâu. Nếu trang báo hết giờ giữa chừng thì một phần đã vào sổ — xoá sạch rồi nhập lại tệp nhỏ hơn, đừng bấm lại nút này vì số sẽ cộng chồng.</p>';
			echo '</div>';
		}

		echo '<details class="khtc-panel khtc-gap"><summary>Nhập lại từ tệp sao lưu</summary>';
		echo '<form method="post" enctype="multipart/form-data"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_sl' );
		echo '<label>Chọn tệp .json<input type="file" name="tep" accept=".json,application/json"></label>';
		echo '<button type="submit" name="khtc_nhap" value="1" class="button" onclick="return confirm(\'Nhập THÊM dữ liệu từ tệp này vào kho đang có?\')">Nhập</button>';
		echo '</div>';
		echo '<p class="khtc-sub"><strong>Nhập là THÊM VÀO, không xoá cái đang có.</strong> Nhập hai lần cùng một tệp thì số nhân đôi. Muốn phục hồi sạch thì xoá dữ liệu cũ trước, hoặc nhập vào một website trắng.</p>';
		echo '<p class="khtc-sub">Id được cấp lại và các liên kết (giao dịch → tài khoản, dòng cổng → đợt, chi phí → giao dịch) được nối lại theo id mới, nên nhập vào website đã có dữ liệu cũng không trỏ nhầm.</p>';
		echo '</form></details></div>';
	}

	// ------------------------------------------------------------ dán thô

	/**
	 * Copy nguyên cả sheet, máy tự nhận là file gì và tự lấy đúng cột.
	 *
	 * LUÔN xem trước rồi mới ghi. Nhận dạng sai cột mà ghi thẳng thì số vào sổ
	 * sai chỗ và không ai thấy — nên bước xem trước không bỏ được, kể cả khi
	 * máy chắc chắn.
	 *
	 * Ghi thì đi qua đúng hàm dán cũ (dan_hang_loat / nap_dong), để mọi thứ
	 * đứng sau nó vẫn chạy: chặn trùng theo mã, chặn kỳ đã khoá, một dòng nhật
	 * ký cho cả lô.
	 */
	public static function dan_tho() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok  = '';
		$bao_loi = '';
		$tho     = '';
		$kq      = null;
		$di_tiep = null;   // [nhãn, đường dẫn] — chỗ đi tiếp sau khi nạp xong
		$chon    = null;   // nhiều sheet cùng là bảng của cổng → người chọn
		$tu_tep  = '';     // câu "đã đọc sheet … trong tệp …"

		// Nạp tệp: đọc MỌI sheet, sheet nào có dạng bảng của cổng thì mới tính.
		// File tổng hợp của kế toán hay có 5–9 sheet, trong đó chỉ vài sheet là
		// sao kê thật — lấy "sheet đầu" là lấy nhầm bảng hoá đơn.
		$lo = null;   // kết quả nạp một lượt nhiều tệp
		$ds_tep = KHTC_Tep::ds_tep( 'tep_tho' );
		if ( count( $ds_tep ) > 1 && check_admin_referer( 'khtc_tho' ) ) {
			// Nhiều tệp → nạp một lượt: máy tự nhận từng tệp, tự chia pháp nhân
			// theo tài khoản nhận / mã cửa hàng. Không xem trước từng tệp được vì
			// tệp không giữ lại giữa hai lần bấm; bù lại mọi chặn trùng vẫn còn.
			$danh_sach = array();
			foreach ( $ds_tep as $f ) {
				$ten = sanitize_file_name( basename( (string) $f['name'] ) );
				if ( $f['error'] || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
					$danh_sach[] = array( 'ten' => $ten, 'trang' => array(), 'loi' => $f['error'] ? KHTC_Tep::loi_upload( (int) $f['error'] ) : 'không lên được' );
					continue;
				}
				$m = KHTC_Tep::doc_moi_trang( $f['tmp_name'], (string) $f['name'] );
				$danh_sach[] = is_wp_error( $m ) ? array( 'ten' => $ten, 'trang' => array(), 'loi' => $m->get_error_message() ) : $m;
			}
			$lo = KHTC_NapLo::nap( $danh_sach );
		} elseif ( KHTC_Tep::co_tep( 'tep_tho' ) && check_admin_referer( 'khtc_tho' ) ) {
			$moi = KHTC_Tep::lay_moi_trang( 'tep_tho' );
			if ( is_wp_error( $moi ) ) {
				$bao_loi = $moi->get_error_message();
			} else {
				$ung = array();
				foreach ( $moi['trang'] as $t ) {
					$nd = KHTC_DanTho::nhan_dang( $t['van_ban'] );
					if ( $nd ) { $ung[] = array( 'ten' => $t['ten'], 'van_ban' => $t['van_ban'], 'so_dong' => $t['so_dong'], 'dang' => $nd[1]['ten'] ); }
				}
				if ( 1 === count( $ung ) ) {
					$tho    = $ung[0]['van_ban'];
					$tu_tep = sprintf( 'Đã đọc sheet "%s" (%s dòng) trong tệp %s.', $ung[0]['ten'], number_format( $ung[0]['so_dong'], 0, ',', '.' ), $moi['ten_tep'] );
				} elseif ( count( $ung ) > 1 ) {
					$chon = array( 'ten_tep' => $moi['ten_tep'], 'ung' => $ung );
				} else {
					$ten_sheet = array_map( function ( $t ) { return $t['ten'] . ' (' . $t['so_dong'] . ' dòng)'; }, $moi['trang'] );
					$bao_loi   = sprintf( 'Tệp %s không có sheet nào là bảng của cổng (cần dòng tiêu đề như file gốc VietQR / Payoo / VNPay / MoMo). Tệp có: %s.', $moi['ten_tep'], implode( ' · ', $ten_sheet ) );
				}
			}
		} elseif ( isset( $_POST['tho'] ) ) {
			$tho = wp_unslash( $_POST['tho'] );
		}

		if ( '' !== trim( (string) $tho ) && check_admin_referer( 'khtc_tho' ) ) {
			$kq = KHTC_DanTho::doc( $tho );
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
				$kq      = null;
			}
		}

		// ---- ghi vào sổ
		// Không đọc được dòng nào thì không có gì để ghi — và không được tạo đợt
		// rỗng (kỳ của đợt lấy từ ngày trong bảng, bảng rỗng là kỳ rỗng).
		if ( $kq && empty( $kq['rows'] ) && ( isset( $_POST['khtc_nap_sao_ke'] ) || isset( $_POST['khtc_nap_cong'] ) ) ) {
			$bao_loi = 'Không có dòng nào đọc được nên không nạp. Kiểm lại bảng xem trước.';
			$kq      = null;
		}
		if ( $kq && isset( $_POST['khtc_nap_sao_ke'] ) ) {
			$nh = (int) $_POST['nh'];
			if ( ! $nh ) {
				$bao_loi = 'Chưa chọn tài khoản ngân hàng để nạp vào.';
			} else {
				$r = KHTC_GiaoDich::dan_hang_loat( $nh, KHTC_DanTho::ra_sao_ke( $kq['rows'] ) );
				$o_tk = array();
				foreach ( $r['trung_tk'] ?? array() as $id_tk => $n ) { $t = KHTC_NganHang::mot( $id_tk ); $o_tk[] = ( $t ? '“' . $t->ten . '”' : '#' . $id_tk ) . ' (' . number_format( $n, 0, ',', '.' ) . ' dòng)'; }
				if ( 0 === $r['them'] && $r['trung'] > 0 ) {
					$bao_loi = sprintf(
						'File này đã nạp rồi: toàn bộ %s dòng đang có trong sổ%s. Không nạp thêm.',
						number_format( $r['trung'], 0, ',', '.' ),
						$o_tk ? ' — ở tài khoản ' . implode( ', ', $o_tk ) . ', không phải tài khoản vừa chọn' : ''
					);
				} else {
					$bao_ok = sprintf(
						'Đã nạp %s dòng vào sao kê.%s%s%s',
						number_format( $r['them'], 0, ',', '.' ),
						$r['trung'] ? ' Bỏ qua ' . number_format( $r['trung'], 0, ',', '.' ) . ' dòng đã có sẵn.' : '',
						$o_tk ? ' Dòng trùng đang ở tài khoản ' . implode( ', ', $o_tk ) . '.' : '',
						$r['loi'] ? ' ' . count( $r['loi'] ) . ' dòng lỗi.' : ''
					);
				}
				// Nạp xong thì chỉ thẳng sang bước tiếp, mang sẵn kỳ và nguồn.
				// Dán không tự sinh hoá đơn — cấp số hoá đơn là việc phải có
				// người bấm — nhưng bắt người ta tự mò lại kỳ thì cũng thừa.
				$di_tiep = self::cho_di_tiep( $kq['rows'], array( 'nh' => array( $nh ) ) );
				$kq = null;
				$tho = '';
			}
		}
		if ( $kq && isset( $_POST['khtc_nap_don_app'] ) && 'don_app' === $kq['dich'] ) {
			$r = KHTC_DonApp::nap( array_map( function ( $x ) { $x['ngay'] = KHTC_GiaoDich::doc_ngay( $x['ngay'] ); return $x; }, $kq['rows'] ) );
			$bao_ok = sprintf(
				'Đã lưu %s đơn mini app%s để tra cơ sở. Tổng trong danh mục tra: %s đơn. Tiền của các đơn này vẫn lấy từ VNPay/MoMo — sang Sinh hoá đơn, tick đợt VNPay/MoMo, dòng “thanh toan don hang …” sẽ tự về đúng cơ sở.',
				number_format( $r['them'], 0, ',', '.' ),
				$r['sua'] ? ' (cập nhật ' . number_format( $r['sua'], 0, ',', '.' ) . ' đơn đã có)' : '',
				number_format( KHTC_DonApp::dem(), 0, ',', '.' )
			);
			$di_tiep = self::cho_di_tiep( $kq['rows'], array() );
			$kq  = null;
			$tho = '';
		}
		if ( $kq && isset( $_POST['khtc_nap_cong'] ) ) {
			$chon_dot = (int) $_POST['dot'];
			$nh_dot  = (int) ( $_POST['nh_dot'] ?? 0 );
			$dot_moi = ( -1 === $chon_dot );
			$kenh    = $kq['dinh_dang'];

			// Chia dòng theo THÁNG doanh thu: mặc định mỗi kênh một đợt mỗi tháng,
			// file từng ngày dồn vào cùng đợt. Chọn đợt cụ thể hay "tạo đợt mới"
			// thì cả bảng vào một đợt.
			$nhom = array();   // dot_id → rows
			$loi_dot = '';
			if ( -2 === $chon_dot ) {
				$theo_thang = array();
				foreach ( $kq['rows'] as $r ) { $theo_thang[ substr( KHTC_GiaoDich::doc_ngay( $r['ngay'] ), 0, 7 ) ][] = $r; }
				ksort( $theo_thang );
				foreach ( $theo_thang as $thang => $rows ) {
					$id = KHTC_DoiSoat::dot_thang( $kenh, $thang, $nh_dot );
					if ( is_wp_error( $id ) ) { $loi_dot = $id->get_error_message(); break; }
					$nhom[ $id ] = $rows;
				}
			} elseif ( $dot_moi ) {
				$ngay = array_column( $kq['rows'], 'ngay' );
				$iso  = array_map( array( 'KHTC_GiaoDich', 'doc_ngay' ), $ngay );
				sort( $iso );
				$id = KHTC_DoiSoat::tao_dot(
					array(
						'ten'          => sanitize_text_field( wp_unslash( $_POST['ten_dot'] ?? '' ) ) ?: KHTC_DoiSoat::ten_dot_thang( $kenh, substr( end( $iso ), 0, 7 ) ),
						'kenh'         => $kenh,
						'tu'           => reset( $iso ),
						'den'          => end( $iso ),
						'ngan_hang_id' => $nh_dot,
					)
				);
				if ( is_wp_error( $id ) ) { $loi_dot = $id->get_error_message(); } else { $nhom[ $id ] = $kq['rows']; }
			} elseif ( $chon_dot > 0 ) {
				$d_chon = KHTC_DoiSoat::mot_dot( $chon_dot );
				// Nạp file Payoo vào đợt VNPay là sai kênh: chặn trùng theo kênh sẽ
				// không bắt được, đối soát của đợt đó lệch, và hai đợt cùng giữ một
				// khoản. Đợt kênh "khác" thì cho, vì đó là chỗ người dùng tự gom.
				if ( ! $d_chon ) {
					$loi_dot = 'Không tìm thấy đợt đã chọn.';
				} elseif ( 'khac' !== $d_chon->kenh && $d_chon->kenh !== $kenh ) {
					$loi_dot = sprintf( 'Đây là file %s nhưng đợt “%s” là đợt %s. Chọn đợt cùng kênh, hoặc để máy tự xếp vào đợt tháng.', $kq['ten'], $d_chon->ten, KHTC_DoiSoat::ten_kenh( $d_chon->kenh ) );
				} else {
					$nhom[ $chon_dot ] = $kq['rows'];
				}
			} else {
				$loi_dot = 'Chưa chọn đợt đối soát để nạp vào.';
			}

			if ( $loi_dot ) {
				$bao_loi = $loi_dot;
			} else {
				$them_tong = 0; $trung_tong = 0; $cau = array(); $dot_da = array(); $trung_o = array();
				foreach ( $nhom as $id => $rows ) {
					$r  = KHTC_DoiSoat::nap_dong( $id, KHTC_DanTho::ra_cong( $rows ) );
					$d  = KHTC_DoiSoat::mot_dot( $id );
					$them_tong  += $r['them'];
					$trung_tong += $r['trung'];
					foreach ( $r['trung_dot'] ?? array() as $id_khac => $n ) {
						$d_khac = KHTC_DoiSoat::mot_dot( $id_khac );
						$trung_o[] = ( $d_khac ? '“' . $d_khac->ten . '”' : '#' . $id_khac ) . ' (' . number_format( $n, 0, ',', '.' ) . ' dòng)';
					}
					if ( 0 === $r['them'] && ( $dot_moi || -2 === $chon_dot ) && 0 === (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' WHERE dot_id = %d', $id ) ) ) {
						// Đợt vừa tạo mà không nhận dòng nào → xoá, không để lại đợt rỗng.
						KHTC_DoiSoat::xoa_dot( $id );
						continue;
					}
					KHTC_DoiSoat::mo_rong_ky( $id );
					KHTC_DoiSoat::chay( $id );
					$dot_da[] = $id;
					$cau[]    = sprintf( '%s dòng vào “%s”', number_format( $r['them'], 0, ',', '.' ), $d ? $d->ten : '#' . $id );
				}
				if ( 0 === $them_tong ) {
					$bao_loi = sprintf(
						'File này đã nạp rồi: toàn bộ %s dòng đang nằm trong đợt %s. Không nạp thêm, không tạo đợt mới. Sang bước sinh hoá đơn thì tick đợt đó.',
						number_format( $trung_tong, 0, ',', '.' ),
						$trung_o ? implode( ', ', array_unique( $trung_o ) ) : 'đã có'
					);
					$di_tiep = self::cho_di_tiep( $kq['rows'], array( 'dot' => $dot_da ) );
				} else {
					$bao_ok = sprintf(
						'Đã nạp %s và chạy đối soát.%s%s',
						implode( ', ', $cau ),
						$trung_tong ? ' Bỏ qua ' . number_format( $trung_tong, 0, ',', '.' ) . ' dòng trùng mã (đã có từ lần nạp trước).' : '',
						$trung_o ? ' Dòng trùng đang ở đợt ' . implode( ', ', array_unique( $trung_o ) ) . '.' : ''
					);
					$di_tiep = self::cho_di_tiep( $kq['rows'], array( 'dot' => $dot_da ) );
				}
				$kq  = null;
				$tho = '';
			}
		}

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Dán thô' );
		if ( $bao_loi ) { printf( '<p class="khtc-canh-bao">%s</p>', esc_html( $bao_loi ) ); }
		if ( $bao_ok )  { printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $bao_ok ) ); }
		if ( $tu_tep )  { printf( '<p class="khtc-sub khtc-tu-tep">%s Kiểm bảng xem trước rồi bấm nạp.</p>', esc_html( $tu_tep ) ); }
		if ( $lo ) {
			$them_tong = 0; $bo = 0;
			foreach ( $lo['ket_qua'] as $x ) { $them_tong += $x['them']; if ( $x['bo'] ) { $bo++; } }
			printf( '<div class="khtc-panel"><h2>Nạp một lượt %d tệp — thêm %s dòng%s</h2>', count( $lo['ket_qua'] ), number_format( $them_tong, 0, ',', '.' ), $bo ? ', <strong>' . $bo . ' tệp bị bỏ</strong> (xem cột ghi chú)' : '' );
			echo '<table><thead><tr><th>Tệp</th><th>Nhận ra</th><th>Pháp nhân</th><th class="so">Thêm</th><th class="so">Trùng (bỏ)</th><th class="so">Mã lạ</th><th>Ghi chú</th></tr></thead><tbody>';
			foreach ( $lo['ket_qua'] as $x ) {
				printf(
					'<tr%s><td>%s</td><td>%s</td><td>%s</td><td class="so">%s</td><td class="so">%s</td><td class="so">%s</td><td>%s</td></tr>',
					$x['bo'] ? ' class="khtc-mo"' : '',
					esc_html( mb_substr( $x['ten'], 0, 48 ) ),
					esc_html( $x['dang'] ?: '—' ),
					esc_html( 'cả hai' === $x['cty'] ? 'cả hai' : ( $x['cty'] ? KHTC_Cty::ten( $x['cty'] ) : '—' ) ),
					$x['bo'] ? '—' : number_format( $x['them'], 0, ',', '.' ),
					$x['trung'] ? number_format( $x['trung'], 0, ',', '.' ) : '—',
					$x['la'] ? '<span class="khtc-canh-bao-chu">' . esc_html( KHTC_UI::tien( $x['la'] ) ) . '</span>' : '—',
					esc_html( $x['ghi_chu'] )
				);
			}
			echo '</tbody></table>';
			foreach ( $lo['cty_da_nap'] as $c => $t ) {
				printf(
					'<p><a class="button button-primary" href="%s">Sinh hoá đơn %s (%s → %s)</a></p>',
					esc_url( self::url( 'sinh-hoa-don', array( 'tu' => $t['tu'], 'den' => $t['den'], 'tach' => 'ngay', 'nh' => $t['nh'], 'dot' => $t['dot'], 'khtc_cty' => $c ) ) ),
					esc_html( KHTC_Cty::ten( $c ) ),
					esc_html( KHTC_UI::ngay( $t['tu'] ) ),
					esc_html( KHTC_UI::ngay( $t['den'] ) )
				);
			}
			echo '<p class="khtc-sub">Nạp lại cả bộ này ngày mai vẫn an toàn: dòng đã có tự bỏ, không nhân đôi. Tệp bị bỏ thì sửa theo ghi chú rồi nạp riêng tệp đó.</p></div>';
		}
		if ( $chon ) {
			printf(
				'<div class="khtc-panel"><h2>Tệp %s có %d sheet là bảng của cổng — chọn sheet muốn nạp</h2><p class="khtc-sub">Mỗi lần nạp một sheet. Nạp xong quay lại nạp sheet kế, cùng tệp.</p><table class="khtc-bang"><thead><tr><th>Sheet</th><th>Dạng</th><th class="khtc-so">Dòng</th><th></th></tr></thead><tbody>',
				esc_html( $chon['ten_tep'] ),
				count( $chon['ung'] )
			);
			foreach ( $chon['ung'] as $u ) {
				echo '<tr><td>' . esc_html( $u['ten'] ) . '</td><td>' . esc_html( $u['dang'] ) . '</td><td class="khtc-so">' . number_format( $u['so_dong'], 0, ',', '.' ) . '</td><td>';
				echo '<form method="post" style="margin:0">';
				wp_nonce_field( 'khtc_tho' );
				printf( '<textarea name="tho" hidden>%s</textarea>', esc_textarea( $u['van_ban'] ) );
				echo '<button class="button">Xem trước sheet này</button></form></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		if ( $di_tiep ) {
			printf(
				'<div class="khtc-panel"><h2>Xong bước 1. Tiếp theo</h2><p class="khtc-sub">Số liệu đã vào sổ. Dán chưa sinh hoá đơn — cấp số hoá đơn là việc phải có người bấm.</p><p><a class="button button-primary" href="%s">%s</a></p><p class="khtc-sub">Còn file của cổng khác trong ngày thì dán nốt trước khi sang bước sinh hoá đơn, để một tờ gom đủ mọi nguồn của điểm đó.</p></div>',
				esc_url( $di_tiep[1] ),
				esc_html( $di_tiep[0] )
			);
		}

		echo '<form method="post" enctype="multipart/form-data"><div class="khtc-panel">';
		wp_nonce_field( 'khtc_tho' );
		echo '<h2>Dán nguyên cả sheet</h2>';
		echo '<p class="khtc-sub"><strong>Nạp tệp</strong>: chọn thẳng file .xlsx của cổng, máy tự tìm sheet là bảng sao kê. <strong>Hoặc dán</strong>: mở file, bôi đen cả sheet, copy, dán vào ô — <strong>nhớ lấy cả dòng tiêu đề</strong>, máy nhận ra là file gì nhờ dòng đó. Dòng tổng cộng nằm trên tiêu đề cứ để nguyên, máy tự bỏ.</p>';
		echo '<p class="khtc-sub">Nhận được: ';
		$ten = array();
		foreach ( KHTC_DanTho::dinh_dang() as $dd ) { $ten[] = '<strong>' . esc_html( $dd['ten'] ) . '</strong>'; }
		echo implode( ' · ', $ten ) . '.</p>';
		KHTC_Tep::o_nap( 'tho', 'Dán vào đây…', 8, $tho, false, true );
		echo '<p class="khtc-sub"><strong>Chọn nhiều tệp một lượt</strong> (cả bộ file trong ngày, hai pháp nhân lẫn nhau cũng được): máy tự nhận từng tệp, sao kê QR về đúng tài khoản theo số “Tài khoản nhận”, tệp cổng về đợt tháng của bên có mã cửa hàng đó, đơn mini app vào bảng tra. Không quyết được tệp nào thì bỏ tệp đó và nói lý do.</p>';
		echo '<p><button class="button button-primary">Nhận dạng và xem trước</button></p>';
		echo '</div>';

		if ( $kq ) {
			$r = $kq['rows'];
			printf(
				'<div class="khtc-panel"><h2>Nhận ra: %s</h2>',
				esc_html( $kq['ten'] )
			);
			if ( ! empty( $kq['theo_ten'] ) ) {
				// Cột lấy theo TÊN tiêu đề — cho người soát thấy máy đã lấy cột nào,
				// vì cổng đổi tên cột là chỗ sai không ai thấy nếu không in ra.
				$nhan = array( 'ngay' => 'Ngày', 'thu' => 'Tiền', 'chi' => 'Tiền đi', 'phi' => 'Phí', 'ma_gd' => 'Mã GD', 'ma_cua_hang' => 'Mã cửa hàng', 'dien_giai' => 'Diễn giải' );
				$cap  = array();
				foreach ( $nhan as $k => $n ) { if ( isset( $kq['theo_ten'][ $k ] ) ) { $cap[] = $n . ' ← <code>' . esc_html( $kq['theo_ten'][ $k ] ) . '</code>'; } }
				echo '<p class="khtc-sub">Cột đã lấy theo tên tiêu đề: ' . implode( ' · ', $cap ) . '</p>';
			}
			KHTC_UI::the_so(
				array(
					array( 'Dòng đọc được', number_format( count( $r ), 0, ',', '.' ) ),
					array( 'Tổng tiền', KHTC_UI::tien( $kq['tong'] ), 'thu' ),
					array( 'Phí', KHTC_UI::tien( $kq['tong_phi'] ) ),
					array( 'Bỏ qua', number_format( $kq['bo_loc'] + $kq['thieu_cot'], 0, ',', '.' ) . ' dòng' ),
				)
			);
			if ( $kq['bo_loc'] ) {
				printf( '<p class="khtc-sub">Bỏ %s dòng vì trạng thái không phải “Thành công”.</p>', number_format( $kq['bo_loc'], 0, ',', '.' ) );
			}
			if ( $kq['thieu_cot'] ) {
				printf( '<p class="khtc-sub">Bỏ %s dòng thiếu cột hoặc không đọc được ngày — thường là dòng tổng cuối bảng.</p>', number_format( $kq['thieu_cot'], 0, ',', '.' ) );
			}
			if ( ! $r ) {
				echo '<p class="khtc-canh-bao">Không đọc được dòng nào. Kiểm lại xem có copy đủ các cột không.</p></div></form></div>';
				return;
			}

			if ( 'don_app' === $kq['dich'] ) {
				// Bảng tra, không phải tiền: xem trước theo đơn, nút lưu riêng.
				$tra_cu = KHTC_DonApp::tra();
				$moi = 0; foreach ( $r as $x ) { if ( ! isset( $tra_cu[ $x['ma_don'] ] ) ) { $moi++; } }
				printf( '<p class="khtc-sub">%s đơn trong file, <strong>%s đơn mới</strong>, %s đã có (sẽ cập nhật). Đây là <strong>bảng tra cơ sở</strong>, không phải tiền: tiền vẫn lấy từ dòng VNPay/MoMo.</p>', number_format( count( $r ), 0, ',', '.' ), number_format( $moi, 0, ',', '.' ), number_format( count( $r ) - $moi, 0, ',', '.' ) );
				echo '<table><thead><tr><th>Mã đơn</th><th>Ngày</th><th>Cơ sở (tên sản phẩm)</th><th class="so">Tiền đơn</th><th>Trạng thái</th></tr></thead><tbody>';
				foreach ( array_slice( $r, 0, 10 ) as $x ) {
					printf( '<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td class="so">%s</td><td>%s</td></tr>', esc_html( $x['ma_don'] ), esc_html( $x['ngay'] ), esc_html( $x['co_so'] ), esc_html( KHTC_UI::tien( $x['tien'] ) ), esc_html( trim( $x['tt_don'] . ' · ' . $x['tt_tt'], ' ·' ) ) );
				}
				echo '</tbody></table>';
				echo '<div class="khtc-loc"><button type="submit" name="khtc_nap_don_app" value="1" class="button button-primary">Lưu bảng đơn để tra cơ sở</button></div>';
				echo '<p class="khtc-sub">Nạp bao nhiêu lần cũng được: cùng mã đơn thì cập nhật, không đếm đôi đồng nào vì bảng này không mang tiền.</p>';
				echo '</div></form></div>';
				return;
			}
			echo '<p class="khtc-sub"><strong>Soát 10 dòng đầu trước khi nạp.</strong> Cột nào lệch chỗ thì dừng lại, đừng nạp.</p>';
			echo '<table><thead><tr><th>Ngày</th><th>Diễn giải</th><th class="so">Số tiền</th><th class="so">Phí</th><th>Mã giao dịch</th><th>Mã cửa hàng</th></tr></thead><tbody>';
			foreach ( array_slice( $r, 0, 10 ) as $x ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td class="so %s">%s</td><td class="so">%s</td><td><code>%s</code></td><td><code>%s</code></td></tr>',
					esc_html( $x['ngay'] ),
					esc_html( mb_substr( $x['dien_giai'], 0, 46 ) ),
					'thu' === $x['loai'] ? 'thu' : 'chi',
					esc_html( KHTC_UI::tien( $x['so_tien'] ) ),
					$x['phi'] ? esc_html( KHTC_UI::tien( $x['phi'] ) ) : '—',
					esc_html( $x['ma_gd'] ?: '—' ),
					esc_html( $x['ma_cua_hang'] ?: '—' )
				);
			}
			echo '</tbody></table>';

			echo '<h3>Nạp vào đâu</h3><div class="khtc-loc">';
			if ( 'sao_ke' === $kq['dich'] ) {
				echo '<label>Tài khoản ngân hàng<select name="nh">';
				foreach ( KHTC_NganHang::ds() as $n ) {
					printf( '<option value="%d">%s</option>', (int) $n->id, esc_html( $n->ten ) );
				}
				echo '</select></label>';
				echo '<button type="submit" name="khtc_nap_sao_ke" value="1" class="button button-primary">Nạp vào sao kê</button>';
			} else {
				// Tháng của bảng đang dán — để nói trước tên đợt sẽ vào.
				$thang_dan = array();
				foreach ( $kq['rows'] as $r ) { $thang_dan[ substr( KHTC_GiaoDich::doc_ngay( $r['ngay'] ), 0, 7 ) ] = 1; }
				ksort( $thang_dan );
				$ten_thang = array();
				foreach ( array_keys( $thang_dan ) as $th ) { $ten_thang[] = KHTC_DoiSoat::ten_dot_thang( $kq['dinh_dang'], $th ); }
				echo '<label>Đợt đối soát<select name="dot">';
				printf( '<option value="-2" selected>Đợt tháng (tự xếp): %s</option>', esc_html( implode( ' + ', $ten_thang ) ) );
				echo '<option value="-1">— Tạo đợt mới, đặt tên riêng —</option>';
				foreach ( KHTC_DoiSoat::ds_dot() as $o ) {
					if ( $o->kenh !== $kq['dinh_dang'] && 'khac' !== $o->kenh ) { continue; }   // đợt khác kênh không nạp vào được
					printf( '<option value="%d">%s</option>', (int) $o->id, esc_html( $o->ten ) );
				}
				echo '</select></label>';
				printf( '<label>Tên đợt mới (nếu chọn tạo)<input type="text" name="ten_dot" placeholder="%s"></label>', esc_attr( reset( $ten_thang ) ?: $kq['ten'] ) );
				echo '<label>Tiền về tài khoản<select name="nh_dot">';
				foreach ( KHTC_NganHang::ds() as $n ) {
					printf( '<option value="%d">%s</option>', (int) $n->id, esc_html( $n->ten ) );
				}
				echo '</select></label>';
				echo '<button type="submit" name="khtc_nap_cong" value="1" class="button button-primary">Nạp vào đợt và chạy đối soát</button>';
			}
			echo '</div>';
			echo '<p class="khtc-sub">Nạp <strong>nhiều lần được</strong>: file mỗi ngày dồn vào đợt tháng của kênh đó, dòng đã có (trùng mã giao dịch) tự bỏ, kỳ của đợt tự nới theo ngày mới. Vẫn chặn kỳ đã khoá, một dòng nhật ký cho cả lô.</p>';
			echo '</div>';
		}
		echo '</form></div>';
	}

	/** Đường sang màn hình sinh hoá đơn, mang sẵn kỳ đúng bằng khoảng ngày vừa dán. */
	private static function cho_di_tiep( $rows, $nguon ) {
		if ( ! $rows ) { return null; }
		$iso = array();
		foreach ( $rows as $r ) {
			$n = KHTC_GiaoDich::doc_ngay( $r['ngay'] );
			if ( '' !== $n ) { $iso[] = $n; }
		}
		if ( ! $iso ) { return null; }
		sort( $iso );
		$tu  = reset( $iso );
		$den = end( $iso );
		return array(
			sprintf(
				'Sinh hoá đơn cho %s →',
				$tu === $den ? 'ngày ' . mysql2date( 'd/m/Y', $tu ) : mysql2date( 'd/m/Y', $tu ) . ' – ' . mysql2date( 'd/m/Y', $den )
			),
			self::url( 'sinh-hoa-don', array( 'tu' => $tu, 'den' => $den, 'tach' => 'ngay' ) + $nguon ),
		);
	}

	// -------------------------------------------------------- danh mục điểm

	/**
	 * Danh mục điểm — nối mã cửa hàng trong sao kê với điểm xuất hoá đơn.
	 */
	public static function danh_muc_diem() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		if ( isset( $_POST['khtc_dan_diem'] ) && check_admin_referer( 'khtc_diem' ) && null !== ( $vb = self::lay_bang( 'bang_diem', $bao_loi ) ) ) {
			$kq = KHTC_Diem::dan_hang_loat( $vb );
			$bao_ok = sprintf( 'Thêm %d điểm, cập nhật %d điểm.', $kq['them'], $kq['sua'] );
			if ( $kq['loi'] ) {
				$bao_loi = implode( ' · ', array_slice( $kq['loi'], 0, 5 ) );
			}
		}
		if ( isset( $_POST['khtc_bo_qua'] ) && check_admin_referer( 'khtc_diem' ) ) {
			$kq = KHTC_Diem::doi_bo_qua( (int) $_POST['khtc_bo_qua'] );
			$bao_ok = is_wp_error( $kq ) ? '' : 'Đã đổi trạng thái điểm.';
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); }
		}
		if ( isset( $_POST['khtc_xoa_diem'] ) && check_admin_referer( 'khtc_diem' ) ) {
			$kq = KHTC_Diem::xoa( (int) $_POST['khtc_xoa_diem'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã xoá điểm.'; }
		}

		$tim = isset( $_GET['tim'] ) ? sanitize_text_field( wp_unslash( $_GET['tim'] ) ) : '';
		$ds  = KHTC_Diem::ds( array( 'tim' => $tim ) );
		$dem = KHTC_Diem::dem();

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Danh mục điểm' );
		if ( $bao_loi ) { printf( '<p class="khtc-canh-bao">%s</p>', esc_html( $bao_loi ) ); }
		if ( $bao_ok ) { printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $bao_ok ) ); }

		KHTC_UI::the_so(
			array(
				array( 'Điểm trong danh mục', number_format( $dem['tong'], 0, ',', '.' ) ),
				array( 'Đang bỏ qua', number_format( $dem['bo_qua'], 0, ',', '.' ), $dem['bo_qua'] ? 'chi' : '' ),
			)
		);
		echo '<p class="khtc-sub">Mỗi dòng tiền vào tài khoản mang một <strong>mã cửa hàng</strong>. Bảng này nói mã đó thuộc điểm nào, để gom sao kê thành hoá đơn. Thiếu một mã ở đây là số tiền của mã đó không vào hoá đơn nào — màn hình Sinh hoá đơn sẽ liệt kê riêng chứ không bỏ lặng.</p>';

		echo '<details class="khtc-panel khtc-gap"><summary>Nạp danh mục hàng loạt</summary><form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'khtc_diem' );
		echo '<p class="khtc-sub">Mỗi dòng: <code>Mã cửa hàng · Tên gian · Mã điểm bán · Tên điểm xuất hoá đơn · Mã Misa · Khu vực · Dịch vụ · Số TK</code> — Tab hoặc dấu phẩy. Mã đã có thì <strong>cập nhật đè</strong>, cờ “bỏ qua” giữ nguyên.</p>';
		KHTC_Tep::o_nap( 'bang_diem', '1W642MMO1S&#9;SB Vinh 06 Posh&#9;MC1776921483982&#9;Sân bay vinh&#9;SB VINH KVC&#9;Hà Nội&#9;KVC&#9;8660077020', 6 );
		echo '<p><button type="submit" name="khtc_dan_diem" value="1" class="button button-primary">Nạp danh mục</button></p>';
		echo '</form></details>';

		echo '<form method="get" class="khtc-panel khtc-loc">';
		self::an_get( 'danh-muc-diem' );
		printf( '<label>Tìm<input type="search" name="tim" value="%s" placeholder="mã, tên gian, tên điểm"></label>', esc_attr( $tim ) );
		echo '<button class="button">Lọc</button></form>';

		echo '<div class="khtc-panel"><h2>Điểm</h2><table><thead><tr><th>Mã cửa hàng</th><th>Tên gian</th><th>Điểm xuất hoá đơn</th><th>Mã Misa</th><th>Khu vực</th><th>Dịch vụ</th><th></th></tr></thead><tbody>';
		foreach ( $ds as $d ) {
			printf(
				'<tr%s><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>',
				$d->bo_qua ? ' class="khtc-mo"' : '',
				esc_html( $d->ma_cua_hang ),
				esc_html( $d->ten_gian ),
				esc_html( $d->ten_diem ) . ( $d->bo_qua ? ' <strong>(bỏ qua)</strong>' : '' ),
				esc_html( $d->ma_misa ),
				esc_html( $d->khu_vuc ),
				esc_html( $d->dich_vu )
			);
			echo '<form method="post" class="khtc-loc">';
			wp_nonce_field( 'khtc_diem' );
			printf( '<button type="submit" name="khtc_bo_qua" value="%d" class="button">%s</button>', (int) $d->id, $d->bo_qua ? 'Dùng lại' : 'Bỏ qua' );
			printf( '<button type="submit" name="khtc_xoa_diem" value="%d" class="button" onclick="return confirm(\'Xoá điểm này khỏi danh mục?\')">Xoá</button>', (int) $d->id );
			echo '</form></td></tr>';
		}
		if ( ! $ds ) { echo '<tr><td colspan="7" class="khtc-trong">Chưa có điểm nào. Nạp danh mục ở khung trên.</td></tr>'; }
		echo '</tbody></table></div></div>';
	}

	// ------------------------------------------------------ sinh hoá đơn

	/**
	 * Gom sao kê trong kỳ thành hoá đơn, mỗi điểm một tờ.
	 *
	 * Màn hình cố ý bắt CHỌN: kỳ, nguồn tiền, ngày và số hoá đơn bắt đầu. Máy
	 * không suy mấy thứ đó từ dữ liệu — đoán sai thì ra một danh sách trông rất
	 * hợp lý mà sai kỳ, và không ai soát lại vì nó trông hợp lý.
	 */
	public static function sinh_hoa_don() {
		KHTC_UI::nhan_doi_cty();
		$ky   = KHTC_UI::thang_nay();
		$tu   = isset( $_REQUEST['tu'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tu'] ) ) : $ky[0];
		$den  = isset( $_REQUEST['den'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['den'] ) ) : $ky[1];
		$nh_c = array_map( 'intval', (array) ( $_REQUEST['nh'] ?? array() ) );
		$dot_c = array_map( 'intval', (array) ( $_REQUEST['dot'] ?? array() ) );
		// Mặc định TÁCH THEO NGÀY: đó là luật của công ty — mỗi điểm mỗi ngày
		// một hoá đơn. Kiểm trên dữ liệu thật tháng 8/2026 của KH705 ra 1.983
		// tờ, trong khi file hoá đơn thật có 1.986 cặp (điểm × ngày). Gộp cả kỳ
		// giữ lại cho trường hợp cần một tờ tổng.
		$tach = isset( $_REQUEST['tach'] ) ? ( 'ngay' === $_REQUEST['tach'] ? 'ngay' : '' ) : 'ngay';
		$bao_ok = '';
		$bao_loi = '';

		// Thêm mã lạ vào danh mục ngay tại đây, khỏi chạy qua màn hình Danh mục
		// rồi quay lại. Gắn vào điểm có sẵn (một điểm nhiều mã là chuyện thường:
		// mỗi máy POS một mã) hoặc lập điểm mới. Đi qua đúng KHTC_Diem::them
		// nên mọi luật của danh mục (mã duy nhất, nhật ký) vẫn giữ.
		if ( isset( $_POST['khtc_them_ma'] ) && check_admin_referer( 'khtc_sinh' ) ) {
			$ma  = sanitize_text_field( wp_unslash( $_POST['ma'] ?? '' ) );
			$d   = array(
				'ma_cua_hang' => $ma,
				'ten_gian'    => sanitize_text_field( wp_unslash( $_POST['ten_gian'] ?? '' ) ),
				'bo_qua'      => ! empty( $_POST['bo_qua'] ) ? 1 : 0,
			);
			$gan = (int) ( $_POST['diem_id'] ?? 0 );
			if ( $gan > 0 ) {
				$mau = KHTC_Diem::mot( $gan );
				if ( $mau ) {
					foreach ( array( 'ten_diem', 'ma_misa', 'khu_vuc', 'dich_vu', 'so_tk' ) as $c ) { $d[ $c ] = $mau->$c; }
				}
			} else {
				foreach ( array( 'ten_diem', 'ma_misa', 'khu_vuc', 'dich_vu' ) as $c ) { $d[ $c ] = sanitize_text_field( wp_unslash( $_POST[ $c ] ?? '' ) ); }
			}
			if ( '' === trim( (string) ( $d['ten_diem'] ?? '' ) ) && empty( $d['bo_qua'] ) ) {
				$bao_loi = 'Chưa có tên điểm xuất hoá đơn cho mã ' . $ma . '. Chọn điểm có sẵn, gõ tên điểm mới, hoặc tick "bỏ qua".';
			} else {
				$kq = KHTC_Diem::them( $d );
				if ( is_wp_error( $kq ) ) {
					$bao_loi = $kq->get_error_message();
				} else {
					$bao_ok = sprintf( 'Đã thêm mã %s → %s. Bảng dưới đã tính lại.', $ma, ! empty( $d['bo_qua'] ) ? 'bỏ qua (không xuất)' : $d['ten_diem'] );
				}
			}
		}

		if ( isset( $_POST['khtc_tao_hd'] ) && check_admin_referer( 'khtc_sinh' ) ) {
			$kq = KHTC_SinhHD::tao(
				array(
					'tu' => $tu, 'den' => $den, 'nh' => $nh_c, 'dot' => $dot_c, 'tach' => $tach,
					'ngay_hd'   => ( 'theo_ngay' === ( $_POST['ngay_kieu'] ?? '' ) ) ? 'theo_ngay' : wp_unslash( $_POST['ngay_hd'] ?? '' ),
					'bat_dau'   => (int) ( $_POST['bat_dau'] ?? 0 ),
					'thue_suat' => sanitize_text_field( wp_unslash( $_POST['thue_suat'] ?? '' ) ),
				)
			);
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
			} else {
				$bao_ok = sprintf(
					'Đã tạo %d hoá đơn, số %d đến %d, tổng %s đ.',
					$kq['tao'], $kq['so_dau'], $kq['so_cuoi'], number_format( $kq['tien'], 0, ',', '.' )
				);
			}
		}

		$g = KHTC_SinhHD::gom( array( 'tu' => $tu, 'den' => $den, 'nh' => $nh_c, 'dot' => $dot_c, 'tach' => $tach ) );

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Sinh hoá đơn từ sao kê' );
		if ( $bao_loi ) { printf( '<p class="khtc-canh-bao">%s</p>', esc_html( $bao_loi ) ); }
		if ( $bao_ok ) { printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $bao_ok ) ); }

		if ( ! KHTC_Diem::dem()['tong'] ) {
			echo '<p class="khtc-canh-bao">Danh mục điểm còn trống. Không có nó thì mỗi dòng tiền chỉ là một khoản không tên, không gom được. Vào <strong>Danh mục điểm</strong> nạp trước.</p></div>';
			return;
		}

		echo '<form method="get" class="khtc-panel"><h2>Chọn kỳ và nguồn tiền</h2>';
		self::an_get( 'sinh-hoa-don' );
		echo '<div class="khtc-loc">';
		printf( '<label>Từ ngày<input type="date" name="tu" value="%s"></label>', esc_attr( $tu ) );
		printf( '<label>Đến ngày<input type="date" name="den" value="%s"></label>', esc_attr( $den ) );
		echo '<button class="button button-primary">Xem trước</button></div>';
		// Màn hình này chạy HẰNG NGÀY — khoảng 160 tờ mỗi lần — chứ không phải
		// mỗi tháng một lần. Gõ tay hai ô ngày mỗi ngày là việc thừa.
		echo '<div class="khtc-loc">';
		foreach ( self::ngay_nhanh() as $nhan => $khoang ) {
			printf(
				'<a class="button" href="%s">%s</a>',
				esc_url( self::url( 'sinh-hoa-don', array( 'tu' => $khoang[0], 'den' => $khoang[1], 'tach' => $tach ) + ( $nh_c ? array( 'nh' => $nh_c ) : array() ) + ( $dot_c ? array( 'dot' => $dot_c ) : array() ) ) ),
				esc_html( $nhan )
			);
		}
		echo '</div>';
		echo '<p class="khtc-sub">Tiền vào từ những nguồn nào thì tick vào đó. Máy không tự chọn — gộp thiếu hay gộp thừa một nguồn là hoá đơn sai mà bảng vẫn trông bình thường.</p><div class="khtc-loc">';
		foreach ( KHTC_NganHang::ds() as $n ) {
			printf(
				'<label class="khtc-tick"><input type="checkbox" name="nh[]" value="%d"%s> Sao kê %s</label>',
				(int) $n->id, in_array( (int) $n->id, $nh_c, true ) ? ' checked' : '', esc_html( $n->ten )
			);
		}
		foreach ( KHTC_DoiSoat::ds_dot() as $o ) {
			printf(
				'<label class="khtc-tick"><input type="checkbox" name="dot[]" value="%d"%s> Đợt %s</label>',
				(int) $o->id, in_array( (int) $o->id, $dot_c, true ) ? ' checked' : '', esc_html( $o->ten )
			);
		}
		echo '</div>';
		// Độ mịn: gộp cả kỳ một tờ mỗi điểm, hay mỗi ngày một tờ. Hoá đơn thật
		// của công ty dùng cả hai kiểu tuỳ pháp nhân, nên để người dùng chọn.
		echo '<p class="khtc-sub">Một điểm xuất mấy tờ trong kỳ này?</p><div class="khtc-loc">';
		printf( '<label class="khtc-tick"><input type="radio" name="tach" value="ngay"%s> Mỗi điểm mỗi ngày một tờ <span class="khtc-sub">(thường dùng)</span></label>', 'ngay' === $tach ? ' checked' : '' );
		printf( '<label class="khtc-tick"><input type="radio" name="tach" value=""%s> Gộp cả kỳ — mỗi điểm một tờ</label>', '' === $tach ? ' checked' : '' );
		echo '</div>';
		echo '</form>';

		if ( is_wp_error( $g ) ) {
			printf( '<p class="khtc-canh-bao">%s</p></div>', esc_html( $g->get_error_message() ) );
			return;
		}

		KHTC_UI::the_so(
			array(
				array( 'Sẽ xuất', count( $g['diem'] ) . ' hoá đơn' ),
				array( 'Tổng tiền', KHTC_UI::tien( $g['tong'] ), 'thu' ),
				array( 'Điểm bỏ qua', KHTC_UI::tien( $g['tong_bo'] ) ),
				array( 'Mã chưa có trong danh mục', KHTC_UI::tien( $g['tong_la'] ), $g['tong_la'] ? 'chi' : '' ),
			)
		);

		if ( $g['la'] ) {
			echo '<div class="khtc-panel"><p class="khtc-canh-bao">Có tiền vào mang mã cửa hàng <strong>không có trong danh mục điểm</strong>. Số tiền này sẽ KHÔNG vào hoá đơn nào. Thêm mã ngay ở đây: gắn vào điểm có sẵn, hoặc gõ tên điểm mới; đúng là không xuất thì tick “bỏ qua”. Thêm xong bảng tính lại liền.</p>';
			// Danh sách điểm có sẵn để gắn: mỗi TÊN ĐIỂM một dòng (một điểm nhiều mã).
			$diem_co = array();
			foreach ( KHTC_Diem::ds() as $dm ) {
				if ( $dm->bo_qua || '' === trim( (string) $dm->ten_diem ) ) { continue; }
				if ( ! isset( $diem_co[ $dm->ten_diem ] ) ) { $diem_co[ $dm->ten_diem ] = $dm; }
			}
			$an = '';
			foreach ( $nh_c as $v ) { $an .= sprintf( '<input type="hidden" name="nh[]" value="%d">', $v ); }
			foreach ( $dot_c as $v ) { $an .= sprintf( '<input type="hidden" name="dot[]" value="%d">', $v ); }
			$an .= sprintf( '<input type="hidden" name="tu" value="%s"><input type="hidden" name="den" value="%s"><input type="hidden" name="tach" value="%s">', esc_attr( $tu ), esc_attr( $den ), esc_attr( $tach ) );
			echo '<table class="khtc-ma-la"><thead><tr><th>Mã cửa hàng</th><th>Cổng ghi tên</th><th class="so">Số tiền</th><th>Thêm vào danh mục</th></tr></thead><tbody>';
			foreach ( array_slice( $g['la'], 0, 30, true ) as $ma => $t ) {
				$goi_y = $g['la_ten'][ $ma ] ?? '';
				// Mã cửa hàng là ASCII nên hợp với phông đẳng rộng; câu "để trống"
				// là tiếng Việt, để trong <code> thì phông đó nuốt mất dấu.
				printf(
					'<tr><td>%s</td><td>%s</td><td class="so">%s</td><td>',
					$ma ? '<code>' . esc_html( $ma ) . '</code>' : '<em>không ghi mã</em>',
					esc_html( $goi_y ),
					esc_html( KHTC_UI::tien( $t ) )
				);
				if ( '' !== $ma ) {
					echo '<form method="post" class="khtc-them-ma">';
					wp_nonce_field( 'khtc_sinh' );
					echo $an;
					printf( '<input type="hidden" name="ma" value="%s"><input type="hidden" name="ten_gian" value="%s">', esc_attr( $ma ), esc_attr( $goi_y ) );
					echo '<select name="diem_id"><option value="0">— điểm mới (gõ bên cạnh) —</option>';
					foreach ( $diem_co as $ten => $dm ) { printf( '<option value="%d">%s</option>', (int) $dm->id, esc_html( $ten . ( $dm->ma_misa ? ' · ' . $dm->ma_misa : '' ) ) ); }
					echo '</select>';
					echo '<input type="text" name="ten_diem" placeholder="Tên điểm mới" size="18">';
					echo '<input type="text" name="ma_misa" placeholder="Mã Misa" size="12">';
					echo '<input type="text" name="khu_vuc" placeholder="Khu vực" size="8" list="khtc-kv">';
					echo '<input type="text" name="dich_vu" placeholder="Dịch vụ" size="6" list="khtc-dv">';
					echo '<label class="khtc-tick-nho"><input type="checkbox" name="bo_qua" value="1"> bỏ qua</label>';
					echo '<button class="button button-small" name="khtc_them_ma" value="1">Thêm</button>';
					echo '</form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			$kv = array(); $dv = array();
			foreach ( $diem_co as $dm ) { if ( $dm->khu_vuc ) { $kv[ $dm->khu_vuc ] = 1; } if ( $dm->dich_vu ) { $dv[ $dm->dich_vu ] = 1; } }
			echo '<datalist id="khtc-kv">'; foreach ( array_keys( $kv ) as $v ) { printf( '<option value="%s">', esc_attr( $v ) ); } echo '</datalist>';
			echo '<datalist id="khtc-dv">'; foreach ( array_keys( $dv ) as $v ) { printf( '<option value="%s">', esc_attr( $v ) ); } echo '</datalist>';
			echo '<p class="khtc-sub">Chọn điểm có sẵn thì mã Misa, khu vực, dịch vụ lấy theo điểm đó. Tên cổng ghi kèm được lưu vào cột Tên gian để sau còn tra.</p></div>';
		}

		printf(
			'<div class="khtc-panel"><h2>Xem trước — %s</h2><table><thead><tr>%s<th>Điểm xuất hoá đơn</th><th>Mã Misa</th><th>Khu vực</th><th>Dịch vụ</th><th class="so">Số dòng</th><th class="so">Tiền (có VAT)</th></tr></thead><tbody>',
			'ngay' === $tach ? 'mỗi điểm mỗi ngày một hoá đơn' : 'mỗi điểm một hoá đơn',
			'ngay' === $tach ? '<th>Ngày doanh thu</th>' : ''
		);
		foreach ( $g['diem'] as $d ) {
			printf(
				'<tr>%s<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="so">%s</td><td class="so thu">%s</td></tr>',
				'ngay' === $tach ? '<td>' . esc_html( KHTC_UI::ngay( $d['ngay'] ) ) . '</td>' : '',
				esc_html( $d['ten_diem'] ), esc_html( $d['ma_misa'] ), esc_html( $d['khu_vuc'] ), esc_html( $d['dich_vu'] ),
				number_format( $d['so_dong'], 0, ',', '.' ), esc_html( KHTC_UI::tien( $d['tien'] ) )
			);
		}
		if ( ! $g['diem'] ) {
			echo '<tr><td colspan="7" class="khtc-trong">'
				. ( ! empty( $g['da_xuat']['tien'] ) ? 'Tiền trong kỳ này đã nằm hết trong hoá đơn rồi (xem dòng dưới). Không còn gì để đề xuất.' : 'Kỳ này chưa gom được đồng nào. Kiểm lại kỳ và nguồn tiền đã tick.' )
				. '</td></tr>';
		}
		echo '</tbody>';
		if ( $g['diem'] ) {
			printf( '<tfoot><tr><th colspan="%d">Tổng</th><th class="so">%s</th><th class="so thu">%s</th></tr></tfoot>', 'ngay' === $tach ? 5 : 4, number_format( array_sum( array_column( $g['diem'], 'so_dong' ) ), 0, ',', '.' ), esc_html( KHTC_UI::tien( $g['tong'] ) ) );
		}
		echo '</table>';
		if ( ! empty( $g['qua_app']['dong'] ) ) {
			printf(
				'<p class="khtc-sub khtc-tu-tep">%s dòng tiền (%s) là đơn Zalo mini app, đã tra được cơ sở qua bảng đơn. Cơ sở của mini app là tên sản phẩm — tên nào chưa có trong danh mục sẽ hiện ở bảng mã lạ bên dưới, thêm một lần là xong.</p>',
				number_format( $g['qua_app']['dong'], 0, ',', '.' ),
				esc_html( KHTC_UI::tien( $g['qua_app']['tien'] ) )
			);
		}
		if ( ! empty( $g['trung']['dong'] ) ) {
			printf(
				'<p class="khtc-canh-bao">Có %s dòng (%s) mang cùng mã giao dịch ở hai đợt đã tick — cùng một file cổng nạp thành hai đợt. Máy chỉ tính một lần, nhưng nên vào <strong>Cổng thanh toán</strong> xoá đợt thừa để bảng đối soát không lệch.</p>',
				number_format( $g['trung']['dong'], 0, ',', '.' ),
				esc_html( KHTC_UI::tien( $g['trung']['tien'] ) )
			);
		}
		if ( ! empty( $g['da_xuat']['tien'] ) ) {
			printf(
				'<p class="khtc-sub khtc-da-xuat">Đã xuất rồi trong kỳ này: <strong>%s</strong> — %s dòng tiền, nằm trong %s tờ hoá đơn. Không đề xuất lại. Muốn làm lại tờ nào thì xoá tờ đó ở mục Đầu ra, tiền của nó quay về đây.</p>',
				esc_html( KHTC_UI::tien( $g['da_xuat']['tien'] ) ),
				number_format( $g['da_xuat']['dong'], 0, ',', '.' ),
				number_format( $g['da_xuat']['to'], 0, ',', '.' )
			);
		}
		echo '</div>';

		if ( $g['diem'] ) {
			$so_tiep = KHTC_SinhHD::so_tiep();
			echo '<div class="khtc-panel"><h2>Tạo hoá đơn</h2><form method="post"><div class="khtc-loc">';
			wp_nonce_field( 'khtc_sinh' );
			foreach ( $nh_c as $v ) { printf( '<input type="hidden" name="nh[]" value="%d">', $v ); }
			foreach ( $dot_c as $v ) { printf( '<input type="hidden" name="dot[]" value="%d">', $v ); }
			printf( '<input type="hidden" name="tu" value="%s"><input type="hidden" name="den" value="%s"><input type="hidden" name="tach" value="%s">', esc_attr( $tu ), esc_attr( $den ), esc_attr( $tach ) );
			// Ngày hoá đơn: tách theo ngày thì mặc định mỗi tờ lấy đúng ngày doanh
			// thu của nó — đó là "mỗi điểm mỗi ngày một tờ" đúng nghĩa. Gộp cả kỳ
			// thì chỉ còn cách chọn một ngày.
			echo '<label>Ngày hoá đơn<select name="ngay_kieu">';
			if ( 'ngay' === $tach ) { echo '<option value="theo_ngay" selected>Theo ngày doanh thu của từng tờ</option>'; }
			printf( '<option value="mot_ngay"%s>Một ngày cho cả đợt (ô bên cạnh)</option>', 'ngay' === $tach ? '' : ' selected' );
			echo '</select></label>';
			printf( '<label>Ngày (nếu chọn một ngày)<input type="date" name="ngay_hd" value="%s"></label>', esc_attr( $den ) );
			printf( '<label>Số hoá đơn bắt đầu<input type="number" name="bat_dau" min="1" required value="%d"></label>', $so_tiep );
			echo '<label>Thuế suất<select name="thue_suat">';
			foreach ( KHTC_HoaDonRa::thue_suat() as $k => $v ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), (string) $k === KHTC_HoaDonRa::TS_MAC_DINH ? ' selected' : '', esc_html( $v ) );
			}
			echo '</select></label>';
			printf( '<button type="submit" name="khtc_tao_hd" value="1" class="button button-primary" onclick="return confirm(\'Tạo %d hoá đơn vào sổ?\')">Tạo %d hoá đơn</button>', count( $g['diem'] ), count( $g['diem'] ) );
			echo '</div>';
			printf(
				'<p class="khtc-sub">Số bắt đầu điền sẵn là <strong>số lớn nhất trong sổ + 1</strong> (%s). Sổ thuế thật đã cấp số ngoài plugin thì sửa lại. Số cấp liên tiếp từ đó; trùng một số nào ở giữa thì máy <strong>dừng hẳn và không ghi gì</strong> — số hoá đơn nhảy cóc là thứ cơ quan thuế hỏi đầu tiên.</p>',
				$so_tiep > 1 ? 'sổ đang đến số ' . number_format( $so_tiep - 1, 0, ',', '.' ) : 'sổ còn trống'
			);
			echo '<p class="khtc-sub">Mỗi dòng tiền chỉ vào <strong>một</strong> tờ. Tạo xong mà chạy lại cùng kỳ, máy chỉ đề xuất phần chưa xuất.</p>';
			echo '<p class="khtc-sub">Tiền gom được hiểu là <strong>đã có VAT</strong> (tiền khách trả thật), máy tách ngược ra chưa VAT và VAT.</p>';
			echo '</form></div>';
		}
		echo '</div>';
	}

	// --------------------------------------------------------- người dùng

	/**
	 * Cấp tài khoản đăng nhập cho kế toán, khỏi đi vòng qua wp-admin.
	 *
	 * Mật khẩu chỉ hiện ĐÚNG MỘT LẦN ngay sau khi tạo, và không ghi vào nhật
	 * ký. Muốn xem lại thì đặt lại cái mới — rẻ hơn nhiều so với việc lưu mật
	 * khẩu ở một chỗ đọc được.
	 */
	public static function nguoi_dung() {
		KHTC_UI::nhan_doi_cty();
		if ( ! KHTC_NguoiDung::duoc_quan_ly() ) {
			echo '<div class="wrap khtc"><h1>Người dùng</h1>';
			echo '<p class="khtc-canh-bao">Chỉ quản trị viên của website mới xem được mục này.</p></div>';
			return;
		}

		$bao_ok  = '';
		$bao_loi = '';
		$mk_moi  = null;   // [tên, mật khẩu] — chỉ sống trong đúng lần tải trang này

		if ( isset( $_POST['khtc_them_nd'] ) && check_admin_referer( 'khtc_nd' ) ) {
			$kq = KHTC_NguoiDung::them(
				array(
					'ten'      => wp_unslash( $_POST['ten'] ?? '' ),
					'email'    => wp_unslash( $_POST['email'] ?? '' ),
					'hien_thi' => wp_unslash( $_POST['hien_thi'] ?? '' ),
					'mat_khau' => wp_unslash( $_POST['mat_khau'] ?? '' ),
				)
			);
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
			} elseif ( ! empty( $kq['da_co'] ) ) {
				$bao_ok = 'Tài khoản đã có sẵn trên website — đã cấp thêm quyền vào sổ, mật khẩu giữ nguyên như cũ.';
			} else {
				$bao_ok = 'Đã tạo tài khoản.';
				$mk_moi = array( sanitize_user( wp_unslash( $_POST['ten'] ?? '' ), true ), $kq['mat_khau'] );
			}
		}

		if ( isset( $_POST['khtc_go_nd'] ) && check_admin_referer( 'khtc_nd' ) ) {
			$kq = KHTC_NguoiDung::go( (int) $_POST['khtc_go_nd'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã gỡ quyền vào sổ.'; }
		}

		if ( isset( $_POST['khtc_doi_mk'] ) && check_admin_referer( 'khtc_nd' ) ) {
			$id = (int) $_POST['khtc_doi_mk'];
			$kq = KHTC_NguoiDung::doi_mat_khau( $id, wp_unslash( $_POST[ 'mk_' . $id ] ?? '' ) );
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
			} else {
				$u      = get_user_by( 'id', $id );
				$bao_ok = 'Đã đổi mật khẩu.';
				$mk_moi = array( $u ? $u->user_login : '#' . $id, $kq['mat_khau'] );
			}
		}

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Người dùng' );
		// Đầu trang nào cũng kèm tên pháp nhân đang chọn, nhưng tài khoản thì
		// KHÔNG chia theo pháp nhân — nói rõ, kẻo tưởng phải tạo hai lần.
		echo '<p class="khtc-sub">Tài khoản dùng chung cho cả hai pháp nhân — ai vào được sổ là xem được cả KH Cũ lẫn KH Mới. Bản này chưa giới hạn được từng người xem riêng một pháp nhân.</p>';
		if ( $bao_loi ) { printf( '<p class="khtc-canh-bao">%s</p>', esc_html( $bao_loi ) ); }
		if ( $bao_ok )  { printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $bao_ok ) ); }

		if ( $mk_moi ) {
			echo '<div class="khtc-panel"><h2>Mật khẩu mới — chép ngay bây giờ</h2>';
			printf(
				'<p>Tài khoản <code>%s</code> · mật khẩu <code class="khtc-mk">%s</code></p>',
				esc_html( $mk_moi[0] ),
				esc_html( $mk_moi[1] )
			);
			echo '<p class="khtc-sub"><strong>Trang này tải lại là mất.</strong> Mật khẩu không được lưu ở đâu đọc lại được, kể cả nhật ký. Quên thì đặt lại cái mới.</p></div>';
		}

		$ds = KHTC_NguoiDung::ds();
		echo '<div class="khtc-panel"><h2>Đang vào được sổ</h2>';
		echo '<table><thead><tr><th>Tên đăng nhập</th><th>Tên hiển thị</th><th>Thư</th><th>Vai trò</th><th>Đổi mật khẩu</th><th></th></tr></thead><tbody>';
		foreach ( $ds as $u ) {
			echo '<tr><td><code>' . esc_html( $u['ten'] ) . '</code>' . ( $u['toi'] ? ' <span class="khtc-sub">(bạn)</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $u['hien_thi'] ) . '</td>';
			echo '<td>' . esc_html( $u['email'] ) . '</td>';
			echo '<td>' . esc_html( $u['vai_tro'] ) . '</td>';
			echo '<td><form method="post" class="khtc-loc">';
			wp_nonce_field( 'khtc_nd' );
			printf( '<input type="text" name="mk_%d" placeholder="để trống = máy tự sinh" autocomplete="off">', (int) $u['id'] );
			printf( '<button type="submit" name="khtc_doi_mk" value="%d" class="button">Đổi</button>', (int) $u['id'] );
			echo '</form></td><td>';
			if ( $u['toi'] ) {
				echo '<span class="khtc-sub">chính bạn</span>';
			} elseif ( $u['quan_tri'] ) {
				echo '<span class="khtc-sub">quản trị website</span>';
			} else {
				echo '<form method="post">';
				wp_nonce_field( 'khtc_nd' );
				printf(
					'<button type="submit" name="khtc_go_nd" value="%d" class="button" onclick="return confirm(\'Gỡ quyền vào sổ của %s? Tài khoản vẫn còn, chỉ không mở được Tài Chính K&amp;H nữa.\')">Gỡ quyền</button>',
					(int) $u['id'],
					esc_js( $u['ten'] )
				);
				echo '</form>';
			}
			echo '</td></tr>';
		}
		if ( ! $ds ) { echo '<tr><td colspan="6" class="khtc-trong">Chưa có ai.</td></tr>'; }
		echo '</tbody></table>';
		echo '<p class="khtc-sub">Gỡ quyền <strong>không xoá tài khoản</strong> — người đó vẫn đăng nhập được vào website, chỉ không mở được sổ. Muốn xoá hẳn thì vào wp-admin → Người dùng.</p>';
		echo '</div>';

		echo '<details class="khtc-panel khtc-gap" open><summary>Thêm người vào sổ</summary><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_nd' );
		echo '<label>Tên đăng nhập<input type="text" name="ten" required autocomplete="off" placeholder="ketoan.lan"></label>';
		echo '<label>Tên hiển thị<input type="text" name="hien_thi" placeholder="Nguyễn Thị Lan"></label>';
		echo '<label>Thư (không bắt buộc)<input type="email" name="email" placeholder="lan@congty.vn"></label>';
		echo '<label>Mật khẩu<input type="text" name="mat_khau" autocomplete="off" placeholder="để trống = máy tự sinh"></label>';
		echo '<button type="submit" name="khtc_them_nd" value="1" class="button button-primary">Tạo tài khoản</button>';
		echo '</div>';
		printf(
			'<p class="khtc-sub">Tài khoản mới mang vai trò <strong>%s</strong>: mở được Tài Chính K&amp;H, <em>không</em> sửa được bài viết, trang hay cài đặt của website. Tên đăng nhập đã có sẵn thì không tạo mới mà cấp thêm quyền cho người đó.</p>',
			esc_html( KHTC_NguoiDung::TEN_VT )
		);
		printf(
			'<p class="khtc-sub">Họ đăng nhập ở <code>%s</code> rồi mở <code>%s</code>.</p>',
			esc_html( wp_login_url() ),
			esc_html( home_url( '/' . KHTC_Web::SLUG . '/' ) )
		);
		echo '</form></details></div>';
	}

	// ------------------------------------------------------- hoá đơn đầu ra

	public static function hoa_don_ra() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		if ( isset( $_POST['khtc_them_hd'] ) && check_admin_referer( 'khtc_hd' ) ) {
			$kq = KHTC_HoaDonRa::them(
				array(
					'ngay'      => sanitize_text_field( wp_unslash( $_POST['ngay'] ?? '' ) ),
					'so_hd'     => sanitize_text_field( wp_unslash( $_POST['so_hd'] ?? '' ) ),
					'khach'     => sanitize_text_field( wp_unslash( $_POST['khach'] ?? '' ) ),
					'mst'       => sanitize_text_field( wp_unslash( $_POST['mst'] ?? '' ) ),
					'noi_dung'  => sanitize_text_field( wp_unslash( $_POST['noi_dung'] ?? '' ) ),
					'chua_vat'  => sanitize_text_field( wp_unslash( $_POST['chua_vat'] ?? '' ) ),
					'co_vat'    => sanitize_text_field( wp_unslash( $_POST['co_vat'] ?? '' ) ),
					'thue_suat' => sanitize_text_field( wp_unslash( $_POST['thue_suat'] ?? '8' ) ),
					'khu_vuc'   => sanitize_text_field( wp_unslash( $_POST['khu_vuc'] ?? '' ) ),
					'dich_vu'   => sanitize_text_field( wp_unslash( $_POST['dich_vu'] ?? '' ) ),
				)
			);
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã ghi hoá đơn.'; }
		}

		if ( isset( $_POST['khtc_dan_hd'] ) && check_admin_referer( 'khtc_hd' ) && null !== ( $vb = self::lay_bang( 'bang_hd', $bao_loi ) ) ) {
			$kq     = KHTC_HoaDonRa::dan_hang_loat( $vb );
			$bao_ok = 'Đã nạp ' . $kq['them'] . ' hoá đơn'
				. ( $kq['trung'] ? ', bỏ qua ' . $kq['trung'] . ' hoá đơn đã có trong sổ' : '' ) . '.';
			$canh = array();
			if ( $kq['lech'] ) {
				$canh[] = $kq['lech'] . ' dòng trong file gốc có Chưa VAT + VAT ≠ Có VAT — đã lấy Chưa VAT và VAT làm gốc, Có VAT tính lại bằng tổng.';
			}
			if ( $kq['loi'] ) {
				$canh[] = implode( ' · ', array_slice( $kq['loi'], 0, 5 ) )
					. ( count( $kq['loi'] ) > 5 ? ' … và ' . ( count( $kq['loi'] ) - 5 ) . ' dòng nữa' : '' );
			}
			$bao_loi = implode( ' ', $canh );
		}

		if ( isset( $_POST['khtc_xoa_hd'] ) && check_admin_referer( 'khtc_hd' ) ) {
			$kq = KHTC_HoaDonRa::xoa( (int) $_POST['khtc_xoa_hd'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã xoá hoá đơn. Phục hồi được ở mục Nhật ký.'; }
		}

		list( $d1, $d2 ) = KHTC_UI::thang_nay();
		$loc   = array(
			'tu'        => sanitize_text_field( wp_unslash( $_GET['tu'] ?? $d1 ) ),
			'den'       => sanitize_text_field( wp_unslash( $_GET['den'] ?? $d2 ) ),
			'khu_vuc'   => sanitize_text_field( wp_unslash( $_GET['kv'] ?? '' ) ),
			'dich_vu'   => sanitize_text_field( wp_unslash( $_GET['dv'] ?? '' ) ),
			'thue_suat' => sanitize_text_field( wp_unslash( $_GET['ts'] ?? '' ) ),
			'tim'       => sanitize_text_field( wp_unslash( $_GET['tim'] ?? '' ) ),
			'trang'     => (int) ( $_GET['trang'] ?? 1 ),
		);
		$kq = KHTC_HoaDonRa::loc( $loc );

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Hoá đơn đầu ra' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		KHTC_UI::the_so(
			array(
				array( 'Doanh thu chưa VAT', KHTC_UI::tien( $kq['chua_vat'] ) ),
				array( 'VAT đầu ra', KHTC_UI::tien( $kq['vat'] ), 'thu' ),
				array( 'Tổng có VAT', KHTC_UI::tien( $kq['co_vat'] ) ),
				array( 'Số hoá đơn', number_format( $kq['so_hd'], 0, ',', '.' ) ),
			)
		);

		// ---- lọc
		printf( '<div class="khtc-panel"><h2>Lọc</h2><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'hoa-don-ra' ) ) );
		self::an_get( 'hoa-don-ra' );
		printf( '<label>Từ ngày<input type="date" name="tu" value="%s"></label>', esc_attr( $loc['tu'] ) );
		printf( '<label>Đến ngày<input type="date" name="den" value="%s"></label>', esc_attr( $loc['den'] ) );
		echo '<label>Thuế suất<select name="ts"><option value="">— Tất cả —</option>';
		foreach ( KHTC_HoaDonRa::thue_suat() as $k => $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $loc['thue_suat'], $k, false ), esc_html( $v ) );
		}
		echo '</select></label>';
		foreach ( array( 'khu_vuc' => array( 'kv', 'Khu vực' ), 'dich_vu' => array( 'dv', 'Dịch vụ' ) ) as $cot => $mo_ta ) {
			printf( '<label>%s<select name="%s"><option value="">— Tất cả —</option>', esc_html( $mo_ta[1] ), esc_attr( $mo_ta[0] ) );
			foreach ( KHTC_HoaDonRa::gia_tri_co( $cot ) as $v ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $loc[ $cot ], $v, false ), esc_html( $v ) );
			}
			echo '</select></label>';
		}
		printf( '<label>Tìm khách / số HĐ / MST<input type="search" name="tim" value="%s"></label>', esc_attr( $loc['tim'] ) );
		echo '<button type="submit" class="button">Lọc</button>';
		printf( '<a href="%s" class="button">Bỏ lọc</a>', esc_url( self::url( 'hoa-don-ra' ) ) );
		printf(
			'<a class="button" href="%s">Tải CSV (22 cột như file VAT)</a>',
			esc_url(
				wp_nonce_url(
					self::url(
						'hoa-don-ra',
						array(
							'khtc_tai_hd' => 1,
							'tu'          => $loc['tu'],
							'den'         => $loc['den'],
							'kv'          => $loc['khu_vuc'],
							'dv'          => $loc['dich_vu'],
							'ts'          => $loc['thue_suat'],
							'tim'         => $loc['tim'],
						)
					),
					'khtc_tai_hd'
				)
			)
		);
		echo '</div></form></div>';

		// ---- tờ khai
		self::bang_gom( 'Theo thuế suất — số để điền tờ khai GTGT', KHTC_HoaDonRa::gom_theo( 'thue_suat', $loc ), true );
		self::bang_gom( 'Theo khu vực', KHTC_HoaDonRa::gom_theo( 'khu_vuc', $loc ), false );
		self::bang_gom( 'Theo dịch vụ', KHTC_HoaDonRa::gom_theo( 'dich_vu', $loc ), false );

		// ---- dán từ file VAT
		echo '<details class="khtc-panel khtc-gap"><summary>Dán từ file Đối soát VAT</summary><form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'khtc_hd' );
		echo '<p class="khtc-sub">Bôi đen bảng trong file VAT rồi dán thẳng vào đây — <strong>đúng 22 cột, đúng thứ tự</strong>, kể cả cột STT và cột trống thứ 21. Dòng tiêu đề dán kèm cũng được, máy tự bỏ. Hoá đơn đã có trong sổ sẽ bị bỏ qua theo số hoá đơn.</p>';
		printf( '<p class="khtc-sub">Thứ tự cột: <code>%s</code></p>', esc_html( implode( ' · ', array_filter( KHTC_HoaDonRa::cot() ) ) ) );
		KHTC_Tep::o_nap( 'bang_hd', '1&#9;05/08/2026&#9;00000123&#9;CONG TY TNHH ABC&#9;0301234567&#9;&#9;&#9;Dich vu vui choi&#9;1&#9;Lan&#9;1000000&#9;1000000&#9;80000&#9;1080000&#9;HCM&#9;KVC', 7 );
		echo '<p><button type="submit" name="khtc_dan_hd" value="1" class="button button-primary">Nạp hoá đơn</button></p></form></details>';

		// ---- ghi một hoá đơn
		echo '<details class="khtc-panel khtc-gap"><summary>Ghi một hoá đơn</summary><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_hd' );
		echo '<label>Ngày HĐ<input type="date" name="ngay" required></label>';
		echo '<label>Số HĐ<input type="text" name="so_hd" required></label>';
		echo '<label>Tên khách hàng<input type="text" name="khach" style="min-width:200px"></label>';
		echo '<label>MST<input type="text" name="mst"></label>';
		echo '<label>Nội dung<input type="text" name="noi_dung" style="min-width:180px"></label>';
		echo '<label>Chưa VAT<input type="text" name="chua_vat" placeholder="1.000.000"></label>';
		echo '<label>Thuế suất<select name="thue_suat">';
		foreach ( KHTC_HoaDonRa::thue_suat() as $k => $v ) {
			// (string) là bắt buộc: PHP đổi khoá mảng '8' thành số nguyên 8, nên
			// so sánh nghiêm ngặt với chuỗi '8' luôn sai và ô chọn rơi về 0%.
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), (string) $k === KHTC_HoaDonRa::TS_MAC_DINH ? ' selected' : '', esc_html( $v ) );
		}
		echo '</select></label>';
		echo '<label>hoặc Có VAT<input type="text" name="co_vat" placeholder="1.080.000"></label>';
		echo '<label>Khu vực<input type="text" name="khu_vuc"></label>';
		echo '<label>Dịch vụ<input type="text" name="dich_vu"></label>';
		echo '<button type="submit" name="khtc_them_hd" value="1" class="button button-primary">Ghi</button>';
		echo '</div><p class="khtc-sub">Điền <em>một trong hai</em> ô tiền, số còn lại máy tính theo thuế suất. Chỉ làm tròn một lần rồi lấy hiệu, nên <code>Chưa VAT + VAT</code> luôn đúng bằng <code>Có VAT</code> — lệch 1 đồng là Misa từ chối cả tệp.</p></form></details>';

		// ---- danh sách
		echo '<div class="khtc-panel"><h2>Danh sách hoá đơn</h2>';
		if ( ! $kq['rows'] ) {
			echo '<div class="khtc-trong">Không có hoá đơn nào khớp bộ lọc.</div>';
		} else {
			echo '<table><thead><tr><th>Ngày</th><th>Số HĐ</th><th>Khách hàng</th><th>MST</th><th>Nội dung</th><th class="so">Chưa VAT</th><th>TS</th><th class="so">VAT</th><th class="so">Có VAT</th><th>Khu vực</th><th></th></tr></thead><tbody>';
			foreach ( $kq['rows'] as $h ) {
				echo '<tr>';
				printf(
					'<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>',
					esc_html( KHTC_UI::ngay( $h->ngay ) ),
					esc_html( $h->so_hd ),
					esc_html( $h->khach ? $h->khach : '—' ),
					esc_html( $h->mst ? $h->mst : '—' ),
					esc_html( $h->noi_dung )
				);
				printf(
					'<td class="so">%s</td><td>%s</td><td class="so">%s</td><td class="so thu">%s</td><td>%s</td>',
					esc_html( KHTC_UI::tien( $h->chua_vat ) ),
					esc_html( is_numeric( $h->thue_suat ) ? $h->thue_suat . '%' : $h->thue_suat ),
					esc_html( KHTC_UI::tien( $h->vat ) ),
					esc_html( KHTC_UI::tien( $h->co_vat ) ),
					esc_html( $h->khu_vuc ? $h->khu_vuc : '—' )
				);
				echo '<td><form method="post" onsubmit="return confirm(\'Xoá hoá đơn này khỏi sổ?\')">';
				wp_nonce_field( 'khtc_hd' );
				printf( '<button type="submit" name="khtc_xoa_hd" value="%d" class="button button-small">Xoá</button>', (int) $h->id );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
			self::phan_trang( 'hoa-don-ra', array( 'trang' => $kq['trang'], 'so_trang' => $kq['so_trang'] ), array( 'tu' => $loc['tu'], 'den' => $loc['den'], 'kv' => $loc['khu_vuc'], 'dv' => $loc['dich_vu'], 'ts' => $loc['thue_suat'], 'tim' => $loc['tim'] ) );
		}
		echo '</div></div>';
	}

	/** Bảng gom nhóm của hoá đơn đầu ra. $la_thue: nhãn là bậc thuế suất. */
	private static function bang_gom( $tieu_de, $rows, $la_thue ) {
		printf( '<div class="khtc-panel"><h2>%s</h2>', esc_html( $tieu_de ) );
		if ( ! $rows ) {
			echo '<div class="khtc-trong">Không có hoá đơn nào trong kỳ đang lọc.</div></div>';
			return;
		}
		$nhan_thue = KHTC_HoaDonRa::thue_suat();
		echo '<table><thead><tr><th>' . ( $la_thue ? 'Thuế suất' : 'Nhóm' ) . '</th><th class="so">Số HĐ</th><th class="so">Doanh thu chưa VAT</th><th class="so">VAT</th><th class="so">Có VAT</th></tr></thead><tbody>';
		$t = array( 0, 0, 0, 0 );
		foreach ( $rows as $r ) {
			$ten = (string) $r->nhan;
			if ( $la_thue ) { $ten = $nhan_thue[ $ten ] ?? $ten; } elseif ( '' === $ten ) { $ten = '(để trống)'; }
			printf(
				'<tr><td>%s</td><td class="so">%s</td><td class="so">%s</td><td class="so">%s</td><td class="so"><strong>%s</strong></td></tr>',
				esc_html( $ten ),
				esc_html( number_format( $r->so_hd, 0, ',', '.' ) ),
				esc_html( KHTC_UI::tien( $r->chua_vat ) ),
				esc_html( KHTC_UI::tien( $r->vat ) ),
				esc_html( KHTC_UI::tien( $r->co_vat ) )
			);
			$t[0] += (int) $r->so_hd;
			$t[1] += (int) $r->chua_vat;
			$t[2] += (int) $r->vat;
			$t[3] += (int) $r->co_vat;
		}
		printf(
			'</tbody><tfoot><tr><th>Tổng</th><th class="so">%s</th><th class="so">%s</th><th class="so">%s</th><th class="so">%s</th></tr></tfoot></table></div>',
			esc_html( number_format( $t[0], 0, ',', '.' ) ),
			esc_html( KHTC_UI::tien( $t[1] ) ),
			esc_html( KHTC_UI::tien( $t[2] ) ),
			esc_html( KHTC_UI::tien( $t[3] ) )
		);
	}

	// -------------------------------------------------- nhật ký và khoá sổ

	public static function nhat_ky() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		if ( isset( $_POST['khtc_khoa'] ) && check_admin_referer( 'khtc_nk' ) ) {
			$kq = KHTC_Khoa::dat( sanitize_text_field( wp_unslash( $_POST['ngay_khoa'] ?? '' ) ) );
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
			} else {
				$bao_ok = $kq ? ( 'Đã khoá sổ đến hết ' . KHTC_UI::ngay( $kq ) . '.' ) : 'Đã mở khoá sổ.';
			}
		}

		if ( isset( $_POST['khtc_phuc_hoi'] ) && check_admin_referer( 'khtc_nk' ) ) {
			$kq = KHTC_NhatKy::phuc_hoi( (int) $_POST['khtc_phuc_hoi'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã đặt lại bản ghi vào sổ.'; }
		}

		$khoa = KHTC_Khoa::ngay();
		$l    = array(
			'viec'  => sanitize_text_field( wp_unslash( $_GET['viec'] ?? '' ) ),
			'bang'  => sanitize_text_field( wp_unslash( $_GET['bang'] ?? '' ) ),
			'trang' => (int) ( $_GET['trang'] ?? 1 ),
		);
		$kq = KHTC_NhatKy::loc( $l );

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Nhật ký' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		// ---- khoá sổ
		echo '<div class="khtc-panel"><h2>Khoá sổ</h2>';
		if ( $khoa ) {
			printf(
				'<div class="khtc-bao ok">Đang khoá đến hết <strong>%s</strong>. Mọi bản ghi mang ngày từ đó trở về trước không thêm, không xoá được.</div>',
				esc_html( KHTC_UI::ngay( $khoa ) )
			);
		} else {
			echo '<div class="khtc-bao loi">Chưa khoá kỳ nào. Tờ khai đã nộp vẫn có thể bị đổi số bằng một hoá đơn lùi ngày.</div>';
		}
		echo '<form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_nk' );
		printf( '<label>Khoá đến hết ngày<input type="date" name="ngay_khoa" value="%s"></label>', esc_attr( $khoa ) );
		echo '<button type="submit" name="khtc_khoa" value="1" class="button button-primary">Đặt khoá</button>';
		echo '</div><p class="khtc-sub">Bỏ trống ô ngày rồi bấm Đặt khoá là mở khoá hoàn toàn. Nếp quen của các phần mềm kế toán: chốt xong tháng nào thì khoá tháng đó — khoá tháng 1 vào đầu tháng 2, khoá tháng 2 vào đầu tháng 3.</p>';
		echo '<p class="khtc-sub">Không có mật khẩu riêng: ai mở được plugin thì mở được khoá. Nhưng mọi lần đặt và mở khoá đều nằm trong nhật ký bên dưới.</p></form></div>';

		// ---- lọc nhật ký
		printf( '<div class="khtc-panel"><h2>Nhật ký thay đổi</h2><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'nhat-ky' ) ) );
		self::an_get( 'nhat-ky' );
		echo '<label>Việc<select name="viec"><option value="">— Tất cả —</option>';
		foreach ( array( 'them', 'sua', 'xoa', 'nap', 'doi_soat', 'khoa', 'nhap', 'phuc_hoi', 'danh_muc' ) as $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $l['viec'], $v, false ), esc_html( KHTC_NhatKy::ten_viec( $v ) ) );
		}
		echo '</select></label>';
		echo '<label>Bảng<select name="bang"><option value="">— Tất cả —</option>';
		foreach ( array( 'ngan_hang', 'giao_dich', 'doi_soat', 'ds_dong', 'chi_phi', 'hd_ra', 'hd_vao', 'hop_dong', 'ho_so', 'thanh_toan' ) as $b ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $b ), selected( $l['bang'], $b, false ), esc_html( KHTC_NhatKy::ten_bang( $b ) ) );
		}
		echo '</select></label>';
		echo '<button type="submit" class="button">Lọc</button>';
		printf( '<a href="%s" class="button">Bỏ lọc</a>', esc_url( self::url( 'nhat-ky' ) ) );
		echo '</div></form>';

		if ( ! $kq['rows'] ) {
			echo '<div class="khtc-trong">Chưa có dòng nhật ký nào.</div>';
		} else {
			echo '<table><thead><tr><th>Lúc</th><th>Ai</th><th>Việc</th><th>Mục</th><th>Nội dung</th><th></th></tr></thead><tbody>';
			foreach ( $kq['rows'] as $g ) {
				echo '<tr>';
				printf(
					'<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>',
					esc_html( mysql2date( 'H:i d/m/Y', $g->luc ) ),
					esc_html( $g->ai ? $g->ai : '—' ),
					esc_html( KHTC_NhatKy::ten_viec( $g->viec ) ),
					esc_html( $g->bang ? KHTC_NhatKy::ten_bang( $g->bang ) : '—' ),
					esc_html( $g->tom_tat )
				);
				echo '<td>';
				if ( 'xoa' === $g->viec && '' !== (string) $g->du_lieu ) {
					echo '<form method="post" onsubmit="return confirm(\'Đặt lại bản ghi này vào sổ?\')">';
					wp_nonce_field( 'khtc_nk' );
					printf( '<button type="submit" name="khtc_phuc_hoi" value="%d" class="button button-small">Phục hồi</button>', (int) $g->id );
					echo '</form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			self::phan_trang( 'nhat-ky', $kq, array( 'viec' => $l['viec'], 'bang' => $l['bang'] ) );
		}
		echo '<p class="khtc-sub">Xoá một bản ghi là giữ lại nguyên văn nó trong nhật ký, nên bấm <strong>Phục hồi</strong> là đặt lại đúng bản cũ, đúng id cũ — các bảng khác trỏ vào nó vẫn nối đúng. Dán hàng loạt chỉ ghi một dòng tổng kết, không ghi từng dòng, nếu không nhật ký to hơn cả sổ.</p>';
		echo '</div></div>';
	}

	// -------------------------------------------------------------- công nợ

	public static function cong_no() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		$loai = sanitize_text_field( wp_unslash( $_GET['loai'] ?? ( $_POST['loai'] ?? 'thu' ) ) );
		$loai = isset( KHTC_CongNo::loai()[ $loai ] ) ? $loai : 'thu';
		$c    = KHTC_CongNo::mot_loai( $loai );

		if ( isset( $_POST['khtc_tra'] ) && check_admin_referer( 'khtc_cn' ) ) {
			$kq = KHTC_CongNo::ghi(
				$c['bang'],
				(int) ( $_POST['chung_tu_id'] ?? 0 ),
				sanitize_text_field( wp_unslash( $_POST['ngay_tra'] ?? '' ) ),
				sanitize_text_field( wp_unslash( $_POST['so_tien_tra'] ?? '' ) ),
				0,
				sanitize_text_field( wp_unslash( $_POST['ghi_chu_tra'] ?? '' ) )
			);
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã ghi thanh toán.'; }
		}

		if ( isset( $_POST['khtc_xoa_tt'] ) && check_admin_referer( 'khtc_cn' ) ) {
			$kq = KHTC_CongNo::xoa( (int) $_POST['khtc_xoa_tt'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã xoá dòng thanh toán.'; }
		}

		if ( isset( $_POST['khtc_tu_ghep'] ) && check_admin_referer( 'khtc_cn' ) ) {
			$kq = KHTC_CongNo::tu_ghep(
				$loai,
				KHTC_GiaoDich::doc_ngay( wp_unslash( $_POST['ghep_tu'] ?? '' ) ),
				KHTC_GiaoDich::doc_ngay( wp_unslash( $_POST['ghep_den'] ?? '' ) ),
				(int) ( $_POST['ghep_nh'] ?? 0 )
			);
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
			} else {
				$bao_ok = sprintf( 'Ghép được %d chứng từ, tổng %s.', $kq['ghep'], KHTC_UI::tien( $kq['tien'] ) );
			}
		}

		$den     = sanitize_text_field( wp_unslash( $_GET['den'] ?? current_time( 'Y-m-d' ) ) );
		$doi_tac = sanitize_text_field( wp_unslash( $_GET['dt'] ?? '' ) );
		$tong    = KHTC_CongNo::tong( $loai, $den );
		$theo    = KHTC_CongNo::theo_doi_tac( $loai, $den );
		$ct      = KHTC_CongNo::con_no( $loai, $den, $doi_tac );
		$moc     = KHTC_CongNo::ten_moc();

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Công nợ' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		// ---- chọn sổ
		printf( '<div class="khtc-panel"><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'cong-no' ) ) );
		self::an_get( 'cong-no' );
		echo '<label>Sổ<select name="loai">';
		foreach ( KHTC_CongNo::loai() as $k => $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $loai, $k, false ), esc_html( $v['ten'] ) );
		}
		echo '</select></label>';
		printf( '<label>Tính tuổi nợ đến ngày<input type="date" name="den" value="%s"></label>', esc_attr( $den ) );
		printf( '<label>%s<input type="text" name="dt" value="%s" placeholder="để trống là tất cả" style="min-width:200px"></label>', esc_html( $c['nhan_ten'] ), esc_attr( $doi_tac ) );
		echo '<button type="submit" class="button">Xem</button>';
		printf( '<a href="%s" class="button">Bỏ lọc</a>', esc_url( self::url( 'cong-no', array( 'loai' => $loai ) ) ) );
		echo '</div></form></div>';

		KHTC_UI::the_so(
			array(
				array( $c['ten'] . ' — tổng còn nợ', KHTC_UI::tien( $tong['tong'] ), 'chi' === $c['loai_gd'] ? 'chi' : '' ),
				array( 'Trong đó quá hạn', KHTC_UI::tien( $tong['qua_han'] ), $tong['qua_han'] ? 'chi' : '' ),
				array( 'Số chứng từ', number_format( $tong['so_ct'], 0, ',', '.' ) ),
				array( 'Số ' . mb_strtolower( $c['nhan_ten'] ), number_format( count( $theo ), 0, ',', '.' ) ),
			)
		);
		printf(
			'<p class="khtc-sub">Tuổi nợ tính từ <strong>hạn thanh toán</strong> nếu chứng từ có ghi, không có thì tính từ ngày chứng từ. %s</p>',
			'tra' === $loai ? 'Khoản chi trả bằng tiền mặt không vào sổ này — trả ngay tại chỗ thì không nợ ai.' : ''
		);

		// ---- theo đối tác, chia mốc tuổi nợ
		printf( '<div class="khtc-panel"><h2>Theo %s</h2>', esc_html( mb_strtolower( $c['nhan_ten'] ) ) );
		if ( ! $theo ) {
			echo '<div class="khtc-trong">Không còn khoản nào chưa thanh toán.</div>';
		} else {
			echo '<table><thead><tr><th>' . esc_html( $c['nhan_ten'] ) . '</th><th class="so">Số CT</th>';
			foreach ( $moc as $i => $m ) {
				printf( '<th class="so">%s</th>', esc_html( 0 === $i ? 'Trong hạn / ' . $m : $m ) );
			}
			echo '<th class="so">Tổng</th></tr></thead><tbody>';
			foreach ( $theo as $r ) {
				printf(
					'<tr><td><a href="%s">%s</a></td><td class="so">%d</td>',
					esc_url( self::url( 'cong-no', array( 'loai' => $loai, 'den' => $den, 'dt' => $r['ten'] ) ) ),
					esc_html( $r['ten'] ),
					$r['so_ct']
				);
				foreach ( $r['moc'] as $i => $v ) {
					printf( '<td class="so%s">%s</td>', ( $i > 0 && $v ) ? ' chi' : '', $v ? esc_html( KHTC_UI::tien( $v ) ) : '' );
				}
				printf( '<td class="so"><strong>%s</strong></td></tr>', esc_html( KHTC_UI::tien( $r['tong'] ) ) );
			}
			echo '</tbody><tfoot><tr><th>Tổng</th><th class="so">' . (int) $tong['so_ct'] . '</th>';
			foreach ( $tong['moc'] as $v ) {
				printf( '<th class="so">%s</th>', esc_html( KHTC_UI::tien( $v ) ) );
			}
			printf( '<th class="so">%s</th></tr></tfoot></table>', esc_html( KHTC_UI::tien( $tong['tong'] ) ) );
		}
		echo '</div>';

		// ---- tự ghép
		$ngan_hang = KHTC_NganHang::ds();
		list( $d1, $d2 ) = KHTC_UI::thang_nay();
		echo '<details class="khtc-panel khtc-gap"><summary>Tự ghép tiền từ sao kê</summary><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_cn' );
		printf( '<input type="hidden" name="loai" value="%s">', esc_attr( $loai ) );
		printf( '<label>Từ ngày<input type="date" name="ghep_tu" value="%s" required></label>', esc_attr( $d1 ) );
		printf( '<label>Đến ngày<input type="date" name="ghep_den" value="%s" required></label>', esc_attr( $d2 ) );
		echo '<label>Tài khoản<select name="ghep_nh"><option value="0">— Tất cả —</option>';
		foreach ( $ngan_hang as $b ) { printf( '<option value="%d">%s</option>', (int) $b->id, esc_html( $b->ten ) ); }
		echo '</select></label>';
		echo '<button type="submit" name="khtc_tu_ghep" value="1" class="button button-primary">Tự ghép</button>';
		echo '</div><p class="khtc-sub">Chỉ bắt được lần trả <strong>đúng bằng số còn nợ</strong> của đúng một chứng từ. Trả gộp nhiều chứng từ hay trả làm nhiều đợt thì phải ghi tay bên dưới — đoán sai một khoản trả gộp còn tệ hơn không đoán, vì nó đóng nhầm chứng từ này và để hở chứng từ khác.</p>';
		echo '<p class="khtc-sub">Chạy lại thì các dòng <em>tự ghép</em> trong kỳ bị dọn và làm lại; dòng ghi tay giữ nguyên.</p></form></details>';

		// ---- từng chứng từ
		printf(
			'<div class="khtc-panel"><h2>Chứng từ còn nợ%s <span class="khtc-dem chi">%d · %s</span></h2>',
			$doi_tac ? ' — ' . esc_html( $doi_tac ) : '',
			count( $ct ),
			esc_html( KHTC_UI::tien( array_sum( array_map( function ( $r ) { return $r->_con; }, $ct ) ) ) )
		);
		if ( ! $ct ) {
			echo '<div class="khtc-trong">Không còn chứng từ nào chưa thanh toán.</div></div>';
		} else {
			echo '<table><thead><tr><th>Ngày</th><th>Hạn</th><th>Chứng từ</th><th>' . esc_html( $c['nhan_ten'] ) . '</th>';
			echo '<th class="so">Tổng</th><th class="so">Đã trả</th><th class="so">Còn nợ</th><th class="so">Tuổi nợ</th><th>Ghi trả</th></tr></thead><tbody>';
			foreach ( $ct as $r ) {
				echo '<tr>';
				printf(
					'<td>%s</td><td>%s</td><td>%s</td><td>%s</td>',
					esc_html( KHTC_UI::ngay( $r->ngay ) ),
					esc_html( empty( $r->han_tt ) ? '—' : KHTC_UI::ngay( $r->han_tt ) ),
					esc_html( 'hd_ra' === $c['bang'] ? $r->so_hd : ( $r->khoan_muc . ( $r->so_ct ? ' · ' . $r->so_ct : '' ) ) ),
					esc_html( '' === trim( $r->_ten ) ? '—' : $r->_ten )
				);
				printf(
					'<td class="so">%s</td><td class="so thu">%s</td><td class="so chi"><strong>%s</strong></td><td class="so%s">%s</td>',
					esc_html( KHTC_UI::tien( $r->_tong ) ),
					$r->da_tra ? esc_html( KHTC_UI::tien( $r->da_tra ) ) : '',
					esc_html( KHTC_UI::tien( $r->_con ) ),
					$r->_tuoi > 0 ? ' chi' : '',
					$r->_tuoi > 0 ? esc_html( $r->_tuoi . ' ngày' ) : 'trong hạn'
				);
				echo '<td><form method="post" class="khtc-loc" style="gap:6px">';
				wp_nonce_field( 'khtc_cn' );
				printf( '<input type="hidden" name="loai" value="%s">', esc_attr( $loai ) );
				printf( '<input type="hidden" name="chung_tu_id" value="%d">', (int) $r->id );
				printf( '<input type="date" name="ngay_tra" value="%s" required>', esc_attr( current_time( 'Y-m-d' ) ) );
				printf( '<input type="text" name="so_tien_tra" value="%s" size="12" required>', esc_attr( number_format( $r->_con, 0, ',', '.' ) ) );
				echo '<button type="submit" name="khtc_tra" value="1" class="button button-small">Ghi</button>';
				echo '</form></td></tr>';

				foreach ( KHTC_CongNo::ds_thanh_toan( $c['bang'], $r->id ) as $t ) {
					echo '<tr><td></td><td></td>';
					printf(
						'<td colspan="3" class="khtc-sub">↳ trả %s%s</td><td class="so thu">%s</td><td colspan="2"></td>',
						esc_html( KHTC_UI::ngay( $t->ngay ) ),
						$t->ghi_chu ? ' — ' . esc_html( $t->ghi_chu ) : '',
						esc_html( KHTC_UI::tien( $t->so_tien ) )
					);
					echo '<td><form method="post" onsubmit="return confirm(\'Xoá dòng thanh toán này?\')">';
					wp_nonce_field( 'khtc_cn' );
					printf( '<input type="hidden" name="loai" value="%s">', esc_attr( $loai ) );
					printf( '<button type="submit" name="khtc_xoa_tt" value="%d" class="button button-small">Xoá</button>', (int) $t->id );
					echo '</form></td></tr>';
				}
			}
			echo '</tbody></table>';
			echo '<p class="khtc-sub">Ô số tiền để sẵn phần còn nợ — sửa lại nếu khách trả một phần. Không ghi quá số còn nợ được: trả thừa là một khoản khác (đặt cọc, ghi nhầm), cho vào đây thì bảng công nợ ra số dương giả.</p>';
			echo '</div>';
		}
		echo '</div>';
	}

	// ------------------------------------------------------ hoá đơn đầu vào

	public static function hoa_don_vao() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		if ( isset( $_POST['khtc_them_hdv'] ) && check_admin_referer( 'khtc_hdv' ) ) {
			$kq = KHTC_HoaDonVao::them(
				array(
					'ngay'         => sanitize_text_field( wp_unslash( $_POST['ngay'] ?? '' ) ),
					'so_hd'        => sanitize_text_field( wp_unslash( $_POST['so_hd'] ?? '' ) ),
					'nha_cung_cap' => sanitize_text_field( wp_unslash( $_POST['nha_cung_cap'] ?? '' ) ),
					'mst'          => sanitize_text_field( wp_unslash( $_POST['mst'] ?? '' ) ),
					'noi_dung'     => sanitize_text_field( wp_unslash( $_POST['noi_dung'] ?? '' ) ),
					'chua_vat'     => sanitize_text_field( wp_unslash( $_POST['chua_vat'] ?? '' ) ),
					'co_vat'       => sanitize_text_field( wp_unslash( $_POST['co_vat'] ?? '' ) ),
					'thue_suat'    => sanitize_text_field( wp_unslash( $_POST['thue_suat'] ?? '8' ) ),
					'hinh_thuc'    => sanitize_text_field( wp_unslash( $_POST['hinh_thuc'] ?? '' ) ),
				)
			);
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã ghi hoá đơn đầu vào.'; }
		}

		if ( isset( $_POST['khtc_dan_hdv'] ) && check_admin_referer( 'khtc_hdv' ) && null !== ( $vb = self::lay_bang( 'bang_hdv', $bao_loi ) ) ) {
			$kq     = KHTC_HoaDonVao::dan_hang_loat( $vb );
			$bao_ok = 'Đã nạp ' . $kq['them'] . ' hoá đơn'
				. ( $kq['trung'] ? ', bỏ qua ' . $kq['trung'] . ' hoá đơn đã có' : '' ) . '.';
			if ( $kq['loi'] ) {
				$bao_loi = implode( ' · ', array_slice( $kq['loi'], 0, 5 ) )
					. ( count( $kq['loi'] ) > 5 ? ' … và ' . ( count( $kq['loi'] ) - 5 ) . ' dòng nữa' : '' );
			}
		}

		if ( isset( $_POST['khtc_kt'] ) && check_admin_referer( 'khtc_hdv' ) ) {
			list( $id, $bat ) = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_POST['khtc_kt'] ) ) ), 2, '' );
			$kq = KHTC_HoaDonVao::dat_khau_tru( (int) $id, '1' === $bat, sanitize_text_field( wp_unslash( $_POST['ly_do_' . (int) $id ] ?? 'Kế toán đánh dấu không khấu trừ' ) ) );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã đổi trạng thái khấu trừ.'; }
		}

		if ( isset( $_POST['khtc_xoa_hdv'] ) && check_admin_referer( 'khtc_hdv' ) ) {
			$kq = KHTC_HoaDonVao::xoa( (int) $_POST['khtc_xoa_hdv'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã xoá hoá đơn. Phục hồi được ở mục Nhật ký.'; }
		}

		list( $d1, $d2 ) = KHTC_UI::thang_nay();
		$loc = array(
			'tu'        => sanitize_text_field( wp_unslash( $_GET['tu'] ?? $d1 ) ),
			'den'       => sanitize_text_field( wp_unslash( $_GET['den'] ?? $d2 ) ),
			'thue_suat' => sanitize_text_field( wp_unslash( $_GET['ts'] ?? '' ) ),
			'khau_tru'  => isset( $_GET['kt'] ) ? sanitize_text_field( wp_unslash( $_GET['kt'] ) ) : '',
			'tim'       => sanitize_text_field( wp_unslash( $_GET['tim'] ?? '' ) ),
			'trang'     => (int) ( $_GET['trang'] ?? 1 ),
		);
		$kq = KHTC_HoaDonVao::loc( $loc );
		$tk = KHTC_HoaDonVao::to_khai( $loc['tu'], $loc['den'] );

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Hoá đơn đầu vào' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		KHTC_UI::the_so(
			array(
				array( 'Mua vào chưa VAT', KHTC_UI::tien( $kq['chua_vat'] ) ),
				array( 'VAT được khấu trừ', KHTC_UI::tien( $kq['vat_kt'] ), 'thu' ),
				array( 'VAT không khấu trừ', KHTC_UI::tien( $kq['vat_khong'] ), $kq['vat_khong'] ? 'chi' : '' ),
				array( 'Số hoá đơn', number_format( $kq['so_hd'], 0, ',', '.' ) ),
			)
		);

		// ---- tờ khai: chỗ hai màn hình hoá đơn gặp nhau
		echo '<div class="khtc-panel"><h2>Tờ khai GTGT trong kỳ</h2><table><tbody>';
		$dong = array(
			array( 'Doanh thu chưa VAT (đầu ra)', $tk['dt_ra'], '' ),
			array( 'VAT đầu ra', $tk['vat_ra'], 'thu' ),
			array( 'Mua vào chưa VAT', $tk['mua_vao'], '' ),
			array( 'VAT đầu vào được khấu trừ', $tk['vat_kt'], 'chi' ),
		);
		foreach ( $dong as $d ) {
			printf(
				'<tr><td>%s</td><td class="so%s">%s</td></tr>',
				esc_html( $d[0] ),
				$d[2] ? ' ' . $d[2] : '',
				esc_html( KHTC_UI::tien( $d[1] ) )
			);
		}
		if ( $tk['chuyen_ky'] > 0 ) {
			printf(
				'<tr><td><strong>Khấu trừ chuyển sang kỳ sau</strong></td><td class="so"><strong>%s</strong></td></tr>',
				esc_html( KHTC_UI::tien( $tk['chuyen_ky'] ) )
			);
		} else {
			printf(
				'<tr><td><strong>VAT phải nộp</strong></td><td class="so chi"><strong>%s</strong></td></tr>',
				esc_html( KHTC_UI::tien( $tk['phai_nop'] ) )
			);
		}
		echo '</tbody></table>';
		printf(
			'<p class="khtc-sub">VAT phải nộp = VAT đầu ra − VAT đầu vào <em>được khấu trừ</em>. Phần không khấu trừ (%s) không trừ vào đây. Ra số âm thì gọi là <strong>chuyển kỳ sau</strong>, không phải nhà nước trả lại.</p>',
			esc_html( KHTC_UI::tien( $tk['vat_khong'] ) )
		);
		printf(
			'<p class="khtc-sub">Số đầu ra lấy từ mục <a href="%s">Hoá đơn đầu ra</a> cùng khoảng ngày — đổi ngày ở bộ lọc bên dưới là cả bảng này đổi theo.</p>',
			esc_url( self::url( 'hoa-don-ra', array( 'tu' => $loc['tu'], 'den' => $loc['den'] ) ) )
		);
		echo '</div>';

		// ---- lọc
		printf( '<div class="khtc-panel"><h2>Lọc</h2><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'hoa-don-vao' ) ) );
		self::an_get( 'hoa-don-vao' );
		printf( '<label>Từ ngày<input type="date" name="tu" value="%s"></label>', esc_attr( $loc['tu'] ) );
		printf( '<label>Đến ngày<input type="date" name="den" value="%s"></label>', esc_attr( $loc['den'] ) );
		echo '<label>Thuế suất<select name="ts"><option value="">— Tất cả —</option>';
		foreach ( KHTC_HoaDonRa::thue_suat() as $k => $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $loc['thue_suat'], $k, false ), esc_html( $v ) );
		}
		echo '</select></label>';
		echo '<label>Khấu trừ<select name="kt"><option value="">— Cả hai —</option>';
		printf( '<option value="1"%s>Được khấu trừ</option>', selected( $loc['khau_tru'], '1', false ) );
		printf( '<option value="0"%s>Không khấu trừ</option>', selected( $loc['khau_tru'], '0', false ) );
		echo '</select></label>';
		printf( '<label>Tìm nhà cung cấp / số HĐ / MST<input type="search" name="tim" value="%s"></label>', esc_attr( $loc['tim'] ) );
		echo '<button type="submit" class="button">Lọc</button>';
		printf( '<a href="%s" class="button">Bỏ lọc</a>', esc_url( self::url( 'hoa-don-vao' ) ) );
		printf(
			'<a class="button" href="%s">Tải CSV</a>',
			esc_url(
				wp_nonce_url(
					self::url(
						'hoa-don-vao',
						array( 'khtc_tai_hdv' => 1, 'tu' => $loc['tu'], 'den' => $loc['den'], 'ts' => $loc['thue_suat'], 'kt' => $loc['khau_tru'], 'tim' => $loc['tim'] )
					),
					'khtc_tai_hdv'
				)
			)
		);
		echo '</div></form></div>';

		self::bang_gom_vao( 'Theo thuế suất', KHTC_HoaDonVao::gom_theo( 'thue_suat', $loc ), true );
		self::bang_gom_vao( 'Theo nhà cung cấp', KHTC_HoaDonVao::gom_theo( 'nha_cung_cap', $loc ), false );

		// ---- dán
		echo '<details class="khtc-panel khtc-gap"><summary>Dán bảng hoá đơn đầu vào</summary><form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'khtc_hdv' );
		echo '<p class="khtc-sub">Mỗi dòng: <code>Ngày · Số HĐ · Nhà cung cấp · MST · Nội dung · Chưa VAT · VAT · Có VAT · Hình thức</code> — cách nhau bằng Tab hoặc dấu phẩy. Ba cột cuối không bắt buộc. Dòng tiêu đề dán kèm cũng được.</p>';
		KHTC_Tep::o_nap( 'bang_hdv', '05/08/2026&#9;00012345&#9;EVN HCMC&#9;0300942001&#9;Tien dien thang 7&#9;2.400.000&#9;192.000&#9;2.592.000&#9;Chuyen khoan', 7 );
		echo '<p><button type="submit" name="khtc_dan_hdv" value="1" class="button button-primary">Nạp hoá đơn</button></p></form></details>';

		// ---- ghi một hoá đơn
		echo '<details class="khtc-panel khtc-gap"><summary>Ghi một hoá đơn</summary><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_hdv' );
		echo '<label>Ngày HĐ<input type="date" name="ngay" required></label>';
		echo '<label>Số HĐ<input type="text" name="so_hd" required></label>';
		echo '<label>Nhà cung cấp<input type="text" name="nha_cung_cap" style="min-width:200px"></label>';
		echo '<label>MST<input type="text" name="mst"></label>';
		echo '<label>Nội dung<input type="text" name="noi_dung" style="min-width:180px"></label>';
		echo '<label>Chưa VAT<input type="text" name="chua_vat" placeholder="2.400.000"></label>';
		echo '<label>Thuế suất<select name="thue_suat">';
		foreach ( KHTC_HoaDonRa::thue_suat() as $k => $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), (string) $k === KHTC_HoaDonRa::TS_MAC_DINH ? ' selected' : '', esc_html( $v ) );
		}
		echo '</select></label>';
		echo '<label>hoặc Có VAT<input type="text" name="co_vat"></label>';
		echo '<label>Hình thức<select name="hinh_thuc"><option value="chuyen_khoan">Chuyển khoản</option><option value="tien_mat">Tiền mặt</option></select></label>';
		echo '<button type="submit" name="khtc_them_hdv" value="1" class="button button-primary">Ghi</button>';
		echo '</div><p class="khtc-sub">Hoá đơn từ <strong>' . esc_html( KHTC_UI::tien( KHTC_HoaDonVao::NGUONG_TIEN_MAT ) ) . '</strong> trở lên mà trả bằng <em>tiền mặt</em> được máy đặt sẵn là <strong>không khấu trừ</strong>. Đây là nhắc chứ không phải phán quyết — bật lại được ở cột Khấu trừ nếu trường hợp của mình khác.</p></form></details>';

		// ---- danh sách
		echo '<div class="khtc-panel"><h2>Danh sách hoá đơn đầu vào</h2>';
		if ( ! $kq['rows'] ) {
			echo '<div class="khtc-trong">Không có hoá đơn nào khớp bộ lọc.</div>';
		} else {
			echo '<table><thead><tr><th>Ngày</th><th>Số HĐ</th><th>Nhà cung cấp</th><th>MST</th><th>Nội dung</th><th class="so">Chưa VAT</th><th>TS</th><th class="so">VAT</th><th>Hình thức</th><th>Khấu trừ</th><th></th></tr></thead><tbody>';
			foreach ( $kq['rows'] as $h ) {
				echo '<tr>';
				printf(
					'<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>',
					esc_html( KHTC_UI::ngay( $h->ngay ) ),
					esc_html( $h->so_hd ),
					esc_html( $h->nha_cung_cap ? $h->nha_cung_cap : '—' ),
					esc_html( $h->mst ? $h->mst : '—' ),
					esc_html( $h->noi_dung )
				);
				printf(
					'<td class="so">%s</td><td>%s</td><td class="so%s">%s</td><td>%s</td>',
					esc_html( KHTC_UI::tien( $h->chua_vat ) ),
					esc_html( is_numeric( $h->thue_suat ) ? $h->thue_suat . '%' : $h->thue_suat ),
					$h->khau_tru ? ' thu' : '',
					esc_html( KHTC_UI::tien( $h->vat ) ),
					esc_html( 'tien_mat' === $h->hinh_thuc ? 'Tiền mặt' : 'Chuyển khoản' )
				);
				echo '<td><form method="post">';
				wp_nonce_field( 'khtc_hdv' );
				if ( $h->khau_tru ) {
					printf( '<button type="submit" name="khtc_kt" value="%d:0" class="button button-small">Có — bỏ khấu trừ</button>', (int) $h->id );
				} else {
					printf(
						'<button type="submit" name="khtc_kt" value="%d:1" class="button button-small">Không — cho khấu trừ</button><div class="khtc-sub">%s</div>',
						(int) $h->id,
						esc_html( $h->ly_do )
					);
				}
				echo '</form></td>';
				echo '<td><form method="post" onsubmit="return confirm(\'Xoá hoá đơn này khỏi sổ?\')">';
				wp_nonce_field( 'khtc_hdv' );
				printf( '<button type="submit" name="khtc_xoa_hdv" value="%d" class="button button-small">Xoá</button>', (int) $h->id );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
			self::phan_trang( 'hoa-don-vao', array( 'trang' => $kq['trang'], 'so_trang' => $kq['so_trang'] ), array( 'tu' => $loc['tu'], 'den' => $loc['den'], 'ts' => $loc['thue_suat'], 'kt' => $loc['khau_tru'], 'tim' => $loc['tim'] ) );
		}
		echo '</div></div>';
	}

	/** Bảng gom nhóm của hoá đơn đầu vào — có thêm cột VAT được khấu trừ. */
	private static function bang_gom_vao( $tieu_de, $rows, $la_thue ) {
		printf( '<div class="khtc-panel"><h2>%s</h2>', esc_html( $tieu_de ) );
		if ( ! $rows ) {
			echo '<div class="khtc-trong">Không có hoá đơn nào trong kỳ đang lọc.</div></div>';
			return;
		}
		$nhan_thue = KHTC_HoaDonRa::thue_suat();
		echo '<table><thead><tr><th>' . ( $la_thue ? 'Thuế suất' : 'Nhà cung cấp' ) . '</th><th class="so">Số HĐ</th><th class="so">Chưa VAT</th><th class="so">VAT</th><th class="so">Được khấu trừ</th><th class="so">Có VAT</th></tr></thead><tbody>';
		$t = array( 0, 0, 0, 0, 0 );
		foreach ( $rows as $r ) {
			$ten = (string) $r->nhan;
			if ( $la_thue ) { $ten = $nhan_thue[ $ten ] ?? $ten; } elseif ( '' === $ten ) { $ten = '(để trống)'; }
			printf(
				'<tr><td>%s</td><td class="so">%s</td><td class="so">%s</td><td class="so">%s</td><td class="so thu">%s</td><td class="so"><strong>%s</strong></td></tr>',
				esc_html( $ten ),
				esc_html( number_format( $r->so_hd, 0, ',', '.' ) ),
				esc_html( KHTC_UI::tien( $r->chua_vat ) ),
				esc_html( KHTC_UI::tien( $r->vat ) ),
				esc_html( KHTC_UI::tien( $r->vat_kt ) ),
				esc_html( KHTC_UI::tien( $r->co_vat ) )
			);
			$t[0] += (int) $r->so_hd;
			$t[1] += (int) $r->chua_vat;
			$t[2] += (int) $r->vat;
			$t[3] += (int) $r->vat_kt;
			$t[4] += (int) $r->co_vat;
		}
		printf(
			'</tbody><tfoot><tr><th>Tổng</th><th class="so">%s</th><th class="so">%s</th><th class="so">%s</th><th class="so">%s</th><th class="so">%s</th></tr></tfoot></table></div>',
			esc_html( number_format( $t[0], 0, ',', '.' ) ),
			esc_html( KHTC_UI::tien( $t[1] ) ),
			esc_html( KHTC_UI::tien( $t[2] ) ),
			esc_html( KHTC_UI::tien( $t[3] ) ),
			esc_html( KHTC_UI::tien( $t[4] ) )
		);
	}

	// -------------------------------------------------------------- báo cáo

	public static function bao_cao() {
		KHTC_UI::nhan_doi_cty();
		list( $d1, $d2 ) = KHTC_UI::thang_nay();
		$tu  = sanitize_text_field( wp_unslash( $_GET['tu'] ?? $d1 ) );
		$den = sanitize_text_field( wp_unslash( $_GET['den'] ?? $d2 ) );
		$b   = KHTC_BaoCao::ky( $tu, $den );

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Báo cáo' );

		// ---- chọn kỳ
		printf( '<div class="khtc-panel"><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'bao-cao' ) ) );
		self::an_get( 'bao-cao' );
		printf( '<label>Từ ngày<input type="date" name="tu" value="%s" required></label>', esc_attr( $tu ) );
		printf( '<label>Đến ngày<input type="date" name="den" value="%s" required></label>', esc_attr( $den ) );
		echo '<button type="submit" class="button button-primary">Xem</button>';
		foreach ( self::ky_nhanh() as $nhan => $khoang ) {
			printf(
				'<a class="button" href="%s">%s</a>',
				esc_url( self::url( 'bao-cao', array( 'tu' => $khoang[0], 'den' => $khoang[1] ) ) ),
				esc_html( $nhan )
			);
		}
		printf(
			'<a class="button" href="%s">Tải CSV</a>',
			esc_url( wp_nonce_url( self::url( 'bao-cao', array( 'khtc_tai_bc' => 1, 'tu' => $tu, 'den' => $den ) ), 'khtc_tai_bc' ) )
		);
		echo '</div></form></div>';

		$th = $b['thue'];
		KHTC_UI::the_so(
			array(
				array( 'Doanh thu chưa VAT', KHTC_UI::tien( $b['doanh_thu']['chua_vat'] ), 'thu' ),
				array( 'Chi phí trong kỳ', KHTC_UI::tien( $b['chi_phi']['tong'] ), 'chi' ),
				array( 'Kết quả tạm tính', KHTC_UI::tien( $b['lai_tam'] ), $b['lai_tam'] < 0 ? 'chi' : '' ),
				array( $th['chuyen_ky'] > 0 ? 'VAT chuyển kỳ sau' : 'VAT phải nộp', KHTC_UI::tien( $th['chuyen_ky'] > 0 ? $th['chuyen_ky'] : $th['phai_nop'] ) ),
			)
		);
		echo '<p class="khtc-sub"><strong>Kết quả tạm tính</strong> = doanh thu chưa VAT − chi phí đã ghi. Không phải báo cáo kết quả kinh doanh: thiếu khấu hao, giá vốn, phân bổ trước sau. Đừng đem đi nộp.</p>';

		// ---- dòng tiền
		$dt = $b['dong_tien'];
		echo '<div class="khtc-panel"><h2>Dòng tiền</h2>';
		if ( ! $dt['rows'] ) {
			echo '<div class="khtc-trong">Chưa có tài khoản ngân hàng nào.</div>';
		} else {
			echo '<table><thead><tr><th>Tài khoản</th><th class="so">Số dư đầu kỳ</th><th class="so">Thu</th><th class="so">Chi</th><th class="so">Số dư cuối kỳ</th></tr></thead><tbody>';
			$co_lech = false;
			foreach ( $dt['rows'] as $r ) {
				if ( $r['lech'] ) { $co_lech = true; }
				printf(
					'<tr><td>%s</td><td class="so">%s</td><td class="so thu">%s</td><td class="so chi">%s</td><td class="so"><strong>%s</strong></td></tr>',
					esc_html( $r['ten'] ),
					esc_html( KHTC_UI::tien( $r['dau'] ) ),
					esc_html( KHTC_UI::tien( $r['thu'] ) ),
					esc_html( KHTC_UI::tien( $r['chi'] ) ),
					esc_html( KHTC_UI::tien( $r['cuoi'] ) )
				);
			}
			$t = $dt['tong'];
			printf(
				'</tbody><tfoot><tr><th>Tổng</th><th class="so">%s</th><th class="so">%s</th><th class="so">%s</th><th class="so">%s</th></tr></tfoot></table>',
				esc_html( KHTC_UI::tien( $t['dau'] ) ),
				esc_html( KHTC_UI::tien( $t['thu'] ) ),
				esc_html( KHTC_UI::tien( $t['chi'] ) ),
				esc_html( KHTC_UI::tien( $t['cuoi'] ) )
			);
			if ( $co_lech ) {
				echo '<div class="khtc-bao loi">Số dư cuối kỳ không bằng số dư đầu cộng thu trừ chi. Có dòng nằm ngoài mốc “tính từ ngày” của tài khoản mà vẫn được cộng — xem lại mục Ngân hàng.</div>';
			}
		}
		echo '</div>';

		self::bang_xu_huong( KHTC_BaoCao::chuoi_thang( $den, 12 ) );

		// ---- doanh thu
		echo '<div class="khtc-panel"><h2>Doanh thu</h2>';
		if ( ! $b['doanh_thu']['so_hd'] ) {
			echo '<div class="khtc-trong">Không có hoá đơn đầu ra nào trong kỳ.</div>';
		} else {
			printf(
				'<p class="khtc-sub">%s hoá đơn · chưa VAT <strong>%s</strong> · VAT %s · có VAT %s</p>',
				esc_html( number_format( $b['doanh_thu']['so_hd'], 0, ',', '.' ) ),
				esc_html( KHTC_UI::tien( $b['doanh_thu']['chua_vat'] ) ),
				esc_html( KHTC_UI::tien( $b['doanh_thu']['vat'] ) ),
				esc_html( KHTC_UI::tien( $b['doanh_thu']['co_vat'] ) )
			);
			self::bang_nhom( 'Khu vực', $b['doanh_thu']['khu_vuc'], $b['doanh_thu']['chua_vat'] );
			self::bang_nhom( 'Dịch vụ', $b['doanh_thu']['dich_vu'], $b['doanh_thu']['chua_vat'] );
		}
		echo '</div>';

		// ---- chi phí
		$c = $b['chi_phi']['cheo'];
		echo '<div class="khtc-panel"><h2>Chi phí</h2>';
		if ( ! $c['bo_phan'] ) {
			echo '<div class="khtc-trong">Không có khoản chi nào trong kỳ.</div>';
		} else {
			printf(
				'<p class="khtc-sub">%s khoản · tổng <strong>%s</strong> · đã thấy tiền ra %s · chưa thấy %s</p>',
				esc_html( number_format( $b['chi_phi']['so_dong'], 0, ',', '.' ) ),
				esc_html( KHTC_UI::tien( $b['chi_phi']['tong'] ) ),
				esc_html( KHTC_UI::tien( $b['chi_phi']['da_tra'] ) ),
				esc_html( KHTC_UI::tien( $b['chi_phi']['chua_tra'] ) )
			);
			echo '<table><thead><tr><th>Khoản mục</th>';
			foreach ( $c['bo_phan'] as $bp ) { printf( '<th class="so">%s</th>', esc_html( $bp ) ); }
			echo '<th class="so">Tổng</th></tr></thead><tbody>';
			foreach ( $c['khoan_muc'] as $km ) {
				printf( '<tr><td>%s</td>', esc_html( $km ) );
				foreach ( $c['bo_phan'] as $bp ) {
					$v = $c['o'][ $km ][ $bp ] ?? 0;
					printf( '<td class="so">%s</td>', $v ? esc_html( KHTC_UI::tien( $v ) ) : '' );
				}
				printf( '<td class="so"><strong>%s</strong></td></tr>', esc_html( KHTC_UI::tien( $c['tong_hang'][ $km ] ) ) );
			}
			echo '</tbody><tfoot><tr><th>Tổng</th>';
			foreach ( $c['bo_phan'] as $bp ) { printf( '<th class="so">%s</th>', esc_html( KHTC_UI::tien( $c['tong_cot'][ $bp ] ?? 0 ) ) ); }
			printf( '<th class="so">%s</th></tr></tfoot></table>', esc_html( KHTC_UI::tien( $c['tong'] ) ) );
		}
		echo '</div>';

		// ---- thuế
		echo '<div class="khtc-panel"><h2>Thuế GTGT</h2><table><tbody>';
		$dong = array(
			array( 'VAT đầu ra', $th['vat_ra'] ),
			array( 'VAT đầu vào được khấu trừ', $th['vat_kt'] ),
			array( 'VAT đầu vào không được khấu trừ', $th['vat_khong'] ),
		);
		foreach ( $dong as $d ) {
			printf( '<tr><td>%s</td><td class="so">%s</td></tr>', esc_html( $d[0] ), esc_html( KHTC_UI::tien( $d[1] ) ) );
		}
		printf(
			'<tr><td><strong>%s</strong></td><td class="so%s"><strong>%s</strong></td></tr>',
			$th['chuyen_ky'] > 0 ? 'Khấu trừ chuyển sang kỳ sau' : 'VAT phải nộp',
			$th['chuyen_ky'] > 0 ? '' : ' chi',
			esc_html( KHTC_UI::tien( $th['chuyen_ky'] > 0 ? $th['chuyen_ky'] : $th['phai_nop'] ) )
		);
		echo '</tbody></table></div>';

		// ---- công nợ
		echo '<div class="khtc-panel"><h2>Công nợ cuối kỳ</h2><table><thead><tr><th>Sổ</th><th class="so">Tổng còn nợ</th><th class="so">Trong đó quá hạn</th><th class="so">Số chứng từ</th></tr></thead><tbody>';
		foreach ( array( 'thu' => 'Phải thu', 'tra' => 'Phải trả' ) as $k => $nhan ) {
			$n = $b['cong_no'][ $k ];
			printf(
				'<tr><td><a href="%s">%s</a></td><td class="so">%s</td><td class="so%s">%s</td><td class="so">%s</td></tr>',
				esc_url( self::url( 'cong-no', array( 'loai' => $k, 'den' => $den ) ) ),
				esc_html( $nhan ),
				esc_html( KHTC_UI::tien( $n['tong'] ) ),
				$n['qua_han'] ? ' chi' : '',
				esc_html( KHTC_UI::tien( $n['qua_han'] ) ),
				esc_html( number_format( $n['so_ct'], 0, ',', '.' ) )
			);
		}
		echo '</tbody></table><p class="khtc-sub">Công nợ tính đến ngày cuối kỳ, không phải đến hôm nay — chạy lại báo cáo tháng cũ vẫn ra đúng số của tháng đó.</p></div>';

		// ---- đối soát còn lệch
		echo '<div class="khtc-panel"><h2>Đối soát trong kỳ</h2>';
		if ( ! $b['doi_soat'] ) {
			echo '<div class="khtc-trong">Không có đợt đối soát nào giao với kỳ này.</div>';
		} else {
			echo '<table><thead><tr><th>Đợt</th><th>Kênh</th><th class="so">Thiếu</th><th class="so">Thừa</th><th class="so">Lệch tiền</th><th></th></tr></thead><tbody>';
			foreach ( $b['doi_soat'] as $d ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td class="so%s">%s</td><td class="so%s">%s</td><td class="so%s">%s</td><td><a href="%s">Mở</a></td></tr>',
					esc_html( $d['ten'] ),
					esc_html( $d['kenh'] ),
					$d['thieu'] ? ' chi' : '',
					esc_html( KHTC_UI::tien( $d['thieu'] ) ),
					$d['thua'] ? ' chi' : '',
					esc_html( KHTC_UI::tien( $d['thua'] ) ),
					$d['lech'] ? ' chi' : '',
					esc_html( KHTC_UI::tien( $d['lech'] ) ),
					esc_url( self::url( 'doi-soat', array( 'dot' => $d['id'] ) ) )
				);
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';
	}

	/** Các kỳ bấm một nút là ra — tháng trước và quý là hai kỳ hay xem nhất. */
	/** Kỳ nhanh cho màn hình sinh hoá đơn: đơn vị là NGÀY, không phải tháng. */
	private static function ngay_nhanh() {
		$nay = current_time( 'Y-m-d' );
		$hqua = gmdate( 'Y-m-d', strtotime( $nay . ' 00:00:00 UTC -1 day' ) );
		$b7   = gmdate( 'Y-m-d', strtotime( $nay . ' 00:00:00 UTC -6 day' ) );
		list( $t1, $t2 ) = KHTC_BaoCao::bien_thang( substr( $nay, 0, 7 ) );
		return array(
			'Hôm qua'    => array( $hqua, $hqua ),
			'Hôm nay'    => array( $nay, $nay ),
			'7 ngày qua' => array( $b7, $nay ),
			'Tháng này'  => array( $t1, $t2 ),
		);
	}

	private static function ky_nhanh() {
		$nay  = current_time( 'Y-m-d' );
		$thang = substr( $nay, 0, 7 );
		$truoc = gmdate( 'Y-m', strtotime( $thang . '-01 00:00:00 UTC -1 month' ) );
		$quy   = (int) ceil( (int) substr( $thang, 5, 2 ) / 3 );
		$nam   = substr( $thang, 0, 4 );
		list( $q1 ) = KHTC_BaoCao::bien_thang( sprintf( '%s-%02d', $nam, ( $quy - 1 ) * 3 + 1 ) );
		list( , $q2 ) = KHTC_BaoCao::bien_thang( sprintf( '%s-%02d', $nam, $quy * 3 ) );
		list( $t1, $t2 ) = KHTC_BaoCao::bien_thang( $truoc );
		return array(
			'Tháng trước'      => array( $t1, $t2 ),
			'Quý ' . $quy      => array( $q1, $q2 ),
			'Năm ' . $nam      => array( $nam . '-01-01', $nam . '-12-31' ),
		);
	}

	/** Bảng gom nhóm gọn, kèm phần trăm trên tổng. */
	private static function bang_nhom( $nhan, $rows, $tong ) {
		if ( ! $rows ) { return; }
		printf( '<table><thead><tr><th>%s</th><th class="so">Số HĐ</th><th class="so">Chưa VAT</th><th class="so">Tỷ trọng</th></tr></thead><tbody>', esc_html( $nhan ) );
		foreach ( $rows as $r ) {
			$pt = $tong ? round( (int) $r->chua_vat * 100 / $tong ) : 0;
			printf(
				'<tr><td>%s</td><td class="so">%s</td><td class="so">%s</td><td class="so">%d%%</td></tr>',
				esc_html( '' === (string) $r->nhan ? '(để trống)' : $r->nhan ),
				esc_html( number_format( $r->so_hd, 0, ',', '.' ) ),
				esc_html( KHTC_UI::tien( $r->chua_vat ) ),
				$pt
			);
		}
		echo '</tbody></table>';
	}

	/**
	 * Bảng xu hướng 12 tháng, mỗi ô có một thanh dài theo số tiền.
	 *
	 * BA ĐIỀU CỐ Ý, theo đúng cách làm biểu đồ cho người mù màu:
	 *
	 * 1. Thanh chỉ có MỘT màu. Xanh lá / đỏ mà plugin dùng cho thu / chi trượt
	 *    kiểm tra mù màu nặng (ΔE 4.2, dưới cả ngưỡng sàn) — với người mù màu
	 *    đỏ-lục thì hai thanh y hệt nhau. Ở đây không cần màu để phân biệt: thu
	 *    và chi nằm ở HAI CỘT riêng, mỗi cột có tiêu đề và có sẵn con số.
	 * 2. Thu và chi chung MỘT thước đo, không phải mỗi cột một thước — nếu
	 *    không thì tháng thu 1 tỷ và tháng chi 10 triệu vẽ ra thanh dài bằng
	 *    nhau. Cũng vì thế không có cột nào dùng trục thứ hai.
	 * 3. Cột chênh lệch vẽ từ GIỮA sang trái hoặc phải. Dấu do HƯỚNG mang,
	 *    không do màu — in đen trắng vẫn đọc được.
	 */
	private static function bang_xu_huong( $chuoi ) {
		$max = 0;
		$cl  = 0;
		foreach ( $chuoi as $r ) {
			$max = max( $max, $r['thu'], $r['chi'] );
			$cl  = max( $cl, abs( $r['thu'] - $r['chi'] ) );
		}
		echo '<div class="khtc-panel"><h2>Mười hai tháng gần nhất</h2>';
		if ( ! $max && ! $cl ) {
			echo '<div class="khtc-trong">Chưa có số liệu nào trong 12 tháng qua.</div></div>';
			return;
		}
		echo '<table class="khtc-xu-huong"><thead><tr><th>Tháng</th><th class="so">Thu</th><th>&nbsp;</th><th class="so">Chi</th><th>&nbsp;</th><th class="so">Chênh lệch</th><th>&nbsp;</th><th class="so">Doanh thu</th><th class="so">Chi phí</th></tr></thead><tbody>';
		foreach ( $chuoi as $r ) {
			$hieu = $r['thu'] - $r['chi'];
			printf( '<tr><td>%s</td>', esc_html( mysql2date( 'm/Y', $r['thang'] . '-01' ) ) );
			printf( '<td class="so thu">%s</td>%s', esc_html( KHTC_UI::tien( $r['thu'] ) ), self::thanh( $r['thu'], $max, 'Thu ' . mysql2date( 'm/Y', $r['thang'] . '-01' ) ) );
			printf( '<td class="so chi">%s</td>%s', esc_html( KHTC_UI::tien( $r['chi'] ) ), self::thanh( $r['chi'], $max, 'Chi ' . mysql2date( 'm/Y', $r['thang'] . '-01' ) ) );
			printf( '<td class="so%s">%s</td>%s', $hieu < 0 ? ' chi' : '', esc_html( KHTC_UI::tien( $hieu ) ), self::thanh_hai_chieu( $hieu, $cl ) );
			printf( '<td class="so">%s</td><td class="so">%s</td></tr>', esc_html( KHTC_UI::tien( $r['doanh_thu'] ) ), esc_html( KHTC_UI::tien( $r['chi_phi'] ) ) );
		}
		echo '</tbody></table>';
		echo '<p class="khtc-sub">Thanh của cột Thu và cột Chi chung một thước đo nên so trực tiếp được với nhau. Cột Chênh lệch vẽ từ giữa: sang phải là thu nhiều hơn chi, sang trái là ngược lại — hướng mang dấu, nên in đen trắng vẫn đọc được.</p>';
		echo '</div>';
	}

	private static function thanh( $gia_tri, $max, $nhan ) {
		$pt = $max > 0 ? max( 0, min( 100, $gia_tri * 100 / $max ) ) : 0;
		return sprintf(
			'<td class="khtc-vach"><span title="%s: %s"><i style="width:%.2f%%"></i></span></td>',
			esc_attr( $nhan ),
			esc_attr( KHTC_UI::tien( $gia_tri ) ),
			$pt
		);
	}

	private static function thanh_hai_chieu( $hieu, $max ) {
		$pt = $max > 0 ? min( 50, abs( $hieu ) * 50 / $max ) : 0;
		return sprintf(
			'<td class="khtc-vach hai-chieu"><span title="%s"><i class="%s" style="width:%.2f%%"></i></span></td>',
			esc_attr( ( $hieu < 0 ? 'Chi nhiều hơn thu ' : 'Thu nhiều hơn chi ' ) . KHTC_UI::tien( abs( $hieu ) ) ),
			$hieu < 0 ? 'am' : 'duong',
			$pt
		);
	}

	// ------------------------------------------------------------ pháp danh

	public static function phap_danh() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		$loai = sanitize_text_field( wp_unslash( $_GET['loai'] ?? ( $_POST['loai'] ?? 'thue' ) ) );
		$loai = isset( KHTC_PhapDanh::loai()[ $loai ] ) ? $loai : 'thue';
		$c    = KHTC_PhapDanh::mot_loai( $loai );

		if ( isset( $_POST['khtc_them_hd2'] ) && check_admin_referer( 'khtc_pd' ) ) {
			$d = array( 'loai' => $loai );
			foreach ( array( 'doi_tac', 'mst', 'so_hd', 'gian', 'ma_diem', 'khu_vuc', 'hinh_thuc', 'noi_dung', 'dai_dien', 'chuc_vu', 'gia_tri', 'ngay_ky', 'ngay_bat_dau', 'ngay_het_han', 'trang_thai', 'loai_chia_se', 'phan_tram', 'link_chua_dau', 'link_du_dau', 'ghi_chu' ) as $k ) {
				$d[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
			}
			$d['mien'] = ! empty( $_POST['mien'] );
			$kq = KHTC_PhapDanh::them( $d );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã lưu hợp đồng.'; }
		}

		if ( isset( $_POST['khtc_dan_pd'] ) && check_admin_referer( 'khtc_pd' ) && null !== ( $vb = self::lay_bang( 'bang_pd', $bao_loi ) ) ) {
			$kq     = KHTC_PhapDanh::dan_hang_loat( $loai, $vb );
			$bao_ok = 'Đã nạp ' . $kq['them'] . ' hợp đồng.';
			if ( $kq['loi'] ) {
				$bao_loi = implode( ' · ', array_slice( $kq['loi'], 0, 5 ) )
					. ( count( $kq['loi'] ) > 5 ? ' … và ' . ( count( $kq['loi'] ) - 5 ) . ' dòng nữa' : '' );
			}
		}

		if ( isset( $_POST['khtc_tt_hd'] ) && check_admin_referer( 'khtc_pd' ) ) {
			list( $id, $tt ) = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_POST['khtc_tt_hd'] ) ) ), 2, '' );
			KHTC_PhapDanh::doi_trang_thai( (int) $id, $tt );
			$bao_ok = 'Đã đổi trạng thái hợp đồng.';
		}

		if ( isset( $_POST['khtc_xoa_hd2'] ) && check_admin_referer( 'khtc_pd' ) ) {
			KHTC_PhapDanh::xoa( (int) $_POST['khtc_xoa_hd2'] );
			$bao_ok = 'Đã xoá hợp đồng. Phục hồi được ở mục Nhật ký.';
		}

		$loc = array(
			'loai'       => $loai,
			'trang_thai' => sanitize_text_field( wp_unslash( $_GET['tt'] ?? '' ) ),
			'khu_vuc'    => sanitize_text_field( wp_unslash( $_GET['kv'] ?? '' ) ),
			'hinh_thuc'  => sanitize_text_field( wp_unslash( $_GET['ht'] ?? '' ) ),
			'tim'        => sanitize_text_field( wp_unslash( $_GET['tim'] ?? '' ) ),
			'trang'      => (int) ( $_GET['trang'] ?? 1 ),
		);
		$kq   = KHTC_PhapDanh::loc( $loc );
		$het  = KHTC_PhapDanh::sap_het_han( $loai );
		$ttds = KHTC_PhapDanh::trang_thai();

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Pháp danh' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		// ---- chọn sổ + lọc
		printf( '<div class="khtc-panel"><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'phap-danh' ) ) );
		self::an_get( 'phap-danh' );
		echo '<label>Sổ<select name="loai">';
		foreach ( KHTC_PhapDanh::loai() as $k => $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $loai, $k, false ), esc_html( $v['ten'] ) );
		}
		echo '</select></label>';
		echo '<label>Trạng thái<select name="tt"><option value="">— Tất cả —</option>';
		foreach ( $ttds as $k => $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $loc['trang_thai'], $k, false ), esc_html( $v ) );
		}
		echo '</select></label>';
		if ( 'thue' === $loai ) {
			foreach ( array( 'khu_vuc' => array( 'kv', 'Khu vực' ), 'hinh_thuc' => array( 'ht', 'Hình thức hợp tác' ) ) as $cot => $mo ) {
				printf( '<label>%s<select name="%s"><option value="">— Tất cả —</option>', esc_html( $mo[1] ), esc_attr( $mo[0] ) );
				foreach ( KHTC_PhapDanh::gia_tri_co( $cot, $loai ) as $v ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $loc[ $cot ], $v, false ), esc_html( $v ) );
				}
				echo '</select></label>';
			}
		}
		printf( '<label>Tìm đối tác / gian / MST<input type="search" name="tim" value="%s"></label>', esc_attr( $loc['tim'] ) );
		echo '<button type="submit" class="button">Lọc</button>';
		printf( '<a href="%s" class="button">Bỏ lọc</a>', esc_url( self::url( 'phap-danh', array( 'loai' => $loai ) ) ) );
		printf(
			'<a class="button" href="%s">Tải CSV</a>',
			esc_url( wp_nonce_url( self::url( 'phap-danh', array( 'khtc_tai_pd' => 1, 'loai' => $loai, 'tt' => $loc['trang_thai'], 'kv' => $loc['khu_vuc'], 'ht' => $loc['hinh_thuc'], 'tim' => $loc['tim'] ) ), 'khtc_tai_pd' ) )
		);
		echo '</div></form></div>';

		KHTC_UI::the_so(
			array(
				array( 'Hợp đồng đang hoạt động', number_format( $kq['dang_chay'], 0, ',', '.' ) ),
				array( $c['gia_tri'] . ' (đang chạy)', KHTC_UI::tien( $kq['gia_tri'] ) ),
				array( 'Sắp hết hạn / đã quá hạn', number_format( count( $het ), 0, ',', '.' ), $het ? 'chi' : '' ),
				array( 'Chưa có bản đủ dấu', number_format( $kq['thieu_dau'], 0, ',', '.' ), $kq['thieu_dau'] ? 'chi' : '' ),
			)
		);
		printf(
			'<p class="khtc-sub">Tổng %s chỉ cộng hợp đồng <em>đang hoạt động</em> và <em>không được miễn</em>. Hợp đồng đã đóng hoặc tạm ngưng không tính vào.</p>',
			esc_html( mb_strtolower( $c['gia_tri'] ) )
		);

		// ---- sắp hết hạn
		printf( '<div class="khtc-panel"><h2>Sắp hết hạn trong %d ngày</h2>', KHTC_PhapDanh::SAP_HET );
		if ( ! $het ) {
			echo '<div class="khtc-trong">Không hợp đồng nào sắp hết hạn.</div>';
		} else {
			echo '<table><thead><tr><th>Còn</th><th>Hết hạn</th><th>' . esc_html( $c['doi_tac'] ) . '</th>';
			echo ( 'thue' === $loai ? '<th>Gian</th>' : '<th>Số HĐ</th>' );
			printf( '<th class="so">%s</th><th></th></tr></thead><tbody>', esc_html( $c['gia_tri'] ) );
			foreach ( $het as $h ) {
				$qua = $h->_con_ngay < 0;
				printf(
					'<tr><td class="%s"><strong>%s</strong></td><td>%s</td><td>%s</td><td>%s</td><td class="so">%s</td>',
					$qua ? 'chi' : '',
					esc_html( $qua ? 'quá ' . abs( $h->_con_ngay ) . ' ngày' : $h->_con_ngay . ' ngày' ),
					esc_html( KHTC_UI::ngay( $h->ngay_het_han ) ),
					esc_html( $h->doi_tac ),
					esc_html( 'thue' === $loai ? $h->gian : ( $h->so_hd ? $h->so_hd : '—' ) ),
					esc_html( KHTC_UI::tien( $h->gia_tri ) )
				);
				echo '<td><form method="post">';
				wp_nonce_field( 'khtc_pd' );
				printf( '<input type="hidden" name="loai" value="%s">', esc_attr( $loai ) );
				printf( '<button type="submit" name="khtc_tt_hd" value="%d:da_dong" class="button button-small">Đánh dấu đã đóng</button>', (int) $h->id );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		// ---- doanh thu chia sẻ (chỉ sổ thuê)
		if ( 'thue' === $loai ) {
			list( $d1, $d2 ) = KHTC_UI::thang_nay();
			$ctu  = sanitize_text_field( wp_unslash( $_GET['cs_tu'] ?? $d1 ) );
			$cden = sanitize_text_field( wp_unslash( $_GET['cs_den'] ?? $d2 ) );
			$cs   = KHTC_PhapDanh::doanh_thu_chia_se( $ctu, $cden );

			echo '<div class="khtc-panel"><h2>Doanh thu chia sẻ</h2>';
			printf( '<form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'phap-danh' ) ) );
			self::an_get( 'phap-danh' );
			printf( '<input type="hidden" name="loai" value="%s">', esc_attr( $loai ) );
			printf( '<label>Từ ngày<input type="date" name="cs_tu" value="%s"></label>', esc_attr( $ctu ) );
			printf( '<label>Đến ngày<input type="date" name="cs_den" value="%s"></label>', esc_attr( $cden ) );
			echo '<button type="submit" class="button">Tính lại</button></div></form>';

			if ( ! $cs['rows'] ) {
				echo '<div class="khtc-trong">Chưa hợp đồng nào đặt hình thức chia sẻ doanh thu.</div>';
			} else {
				echo '<table><thead><tr><th>Gian</th><th>Bên cho thuê</th><th>Mã điểm</th><th>Hình thức</th><th class="so">%</th><th class="so">Doanh thu trong kỳ</th><th class="so">Phần chia</th><th class="so">Mình giữ</th></tr></thead><tbody>';
				foreach ( $cs['rows'] as $r ) {
					$h = $r['hd'];
					printf(
						'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="so">%s%%</td>',
						esc_html( $h->gian ),
						esc_html( $h->doi_tac ),
						$h->ma_diem ? esc_html( $h->ma_diem ) : '<span class="chi">chưa gắn mã điểm</span>',
						esc_html( KHTC_PhapDanh::chia_se()[ $h->loai_chia_se ] ?? '' ),
						esc_html( rtrim( rtrim( number_format( (float) $h->phan_tram, 2, ',', '.' ), '0' ), ',' ) )
					);
					printf(
						'<td class="so">%s</td><td class="so chi"><strong>%s</strong></td><td class="so thu">%s</td></tr>',
						esc_html( KHTC_UI::tien( $r['dt'] ) ),
						esc_html( KHTC_UI::tien( $r['chia'] ) ) . ( $h->mien ? ' <em>(miễn)</em>' : '' ),
						esc_html( KHTC_UI::tien( $r['giu_lai'] ) )
					);
				}
				printf(
					'</tbody><tfoot><tr><th colspan="5">Tổng</th><th class="so">%s</th><th class="so">%s</th><th class="so">%s</th></tr></tfoot></table>',
					esc_html( KHTC_UI::tien( $cs['tong_dt'] ) ),
					esc_html( KHTC_UI::tien( $cs['tong_chia'] ) ),
					esc_html( KHTC_UI::tien( $cs['tong_dt'] - $cs['tong_chia'] ) )
				);
				echo '<p class="khtc-sub">Doanh thu ghép với hoá đơn đầu ra qua <strong>mã điểm nội bộ</strong> — cột mà cả hợp đồng lẫn hoá đơn đều có. Không đoán theo tên gian: đoán sai ở đây là trả nhầm tiền cho người khác.</p>';
				if ( $cs['chua_gan'] ) {
					printf(
						'<div class="khtc-bao loi">%d hợp đồng chia sẻ chưa gắn mã điểm nên doanh thu hiện 0. Điền mã điểm cho chúng, phải trùng đúng mã trên hoá đơn đầu ra.</div>',
						(int) $cs['chua_gan']
					);
				}
			}
			echo '</div>';
		}

		// ---- dán bảng
		echo '<details class="khtc-panel khtc-gap"><summary>Dán bảng hợp đồng</summary><form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'khtc_pd' );
		printf( '<input type="hidden" name="loai" value="%s">', esc_attr( $loai ) );
		if ( 'thue' === $loai ) {
			echo '<p class="khtc-sub">Mỗi dòng: <code>Gian · Bên cho thuê · MST · Khu vực · Mã điểm · Hình thức · Tiền thuê tháng · Bắt đầu · Hết hạn · % chia sẻ</code>. Từ cột 3 trở đi không bắt buộc. Có điền % chia sẻ thì tự đặt hình thức “giữ tiền”.</p>';
			KHTC_Tep::o_nap( 'bang_pd', 'Gian A1-05&#9;CTY BDS An Phu&#9;0301234567&#9;HCM&#9;KVC-CRESCENT&#9;Thue co dinh&#9;35.000.000&#9;01/01/2026&#9;31/12/2026&#9;0', 6 );
		} else {
			echo '<p class="khtc-sub">Mỗi dòng: <code>Nhà cung cấp · MST · Số HĐ · Nội dung · Giá trị · Ngày ký · Hết hạn</code>. Từ cột 3 trở đi không bắt buộc.</p>';
			KHTC_Tep::o_nap( 'bang_pd', 'CTY BAO TRI THANH DAT&#9;0303334444&#9;HD-2026-07&#9;Bao tri may lanh&#9;120.000.000&#9;05/01/2026&#9;31/12/2026', 6 );
		}
		echo '<p><button type="submit" name="khtc_dan_pd" value="1" class="button button-primary">Nạp bảng</button></p></form></details>';

		// ---- thêm một hợp đồng
		printf( '<div class="khtc-panel"><h2>Thêm hợp đồng %s</h2><form method="post"><div class="khtc-loc">', esc_html( mb_strtolower( $c['ten'] ) ) );
		wp_nonce_field( 'khtc_pd' );
		printf( '<input type="hidden" name="loai" value="%s">', esc_attr( $loai ) );
		if ( 'thue' === $loai ) {
			echo '<label>Gian / mặt bằng<input type="text" name="gian" required style="min-width:170px"></label>';
		}
		printf( '<label>%s<input type="text" name="doi_tac" required style="min-width:200px"></label>', esc_html( $c['doi_tac'] ) );
		echo '<label>MST<input type="text" name="mst"></label>';
		echo '<label>Số HĐ<input type="text" name="so_hd"></label>';
		if ( 'thue' === $loai ) {
			echo '<label>Khu vực<input type="text" name="khu_vuc"></label>';
			echo '<label>Mã điểm nội bộ<input type="text" name="ma_diem" placeholder="để tính chia sẻ"></label>';
			echo '<label>Hình thức hợp tác<input type="text" name="hinh_thuc"></label>';
		} else {
			echo '<label>Nội dung<input type="text" name="noi_dung" style="min-width:200px"></label>';
			echo '<label>Đại diện<input type="text" name="dai_dien"></label>';
			echo '<label>Chức vụ<input type="text" name="chuc_vu"></label>';
		}
		printf( '<label>%s<input type="text" name="gia_tri" placeholder="35.000.000"></label>', esc_html( $c['gia_tri'] ) );
		echo '<label>Ngày ký<input type="date" name="ngay_ky"></label>';
		echo '<label>Bắt đầu<input type="date" name="ngay_bat_dau"></label>';
		echo '<label>Hết hạn<input type="date" name="ngay_het_han"></label>';
		echo '<label>Trạng thái<select name="trang_thai">';
		foreach ( $ttds as $k => $v ) { printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $v ) ); }
		echo '</select></label>';
		if ( 'thue' === $loai ) {
			echo '<label>Chia sẻ doanh thu<select name="loai_chia_se">';
			foreach ( KHTC_PhapDanh::chia_se() as $k => $v ) { printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $v ) ); }
			echo '</select></label>';
			echo '<label>Tỷ lệ %<input type="text" name="phan_tram" placeholder="0" size="5"></label>';
			echo '<label>Miễn<input type="checkbox" name="mien" value="1"></label>';
		}
		echo '<label>Link bản chưa dấu<input type="url" name="link_chua_dau" style="min-width:170px"></label>';
		echo '<label>Link bản đủ dấu<input type="url" name="link_du_dau" style="min-width:170px"></label>';
		echo '<button type="submit" name="khtc_them_hd2" value="1" class="button button-primary">Lưu</button>';
		echo '</div><p class="khtc-sub"><strong>Hết hạn là ô ngày thật</strong>, không phải chữ tự do như sổ cũ — chỉ khi nó là ngày thì bảng “sắp hết hạn” bên trên mới chạy được.</p></form></div>';

		// ---- danh sách
		printf( '<div class="khtc-panel"><h2>Danh sách hợp đồng %s</h2>', esc_html( mb_strtolower( $c['ten'] ) ) );
		if ( ! $kq['rows'] ) {
			echo '<div class="khtc-trong">Không có hợp đồng nào khớp bộ lọc.</div>';
		} else {
			echo '<table><thead><tr>';
			echo ( 'thue' === $loai ? '<th>Gian</th>' : '<th>Số HĐ</th>' );
			printf( '<th>%s</th><th>MST</th>', esc_html( $c['doi_tac'] ) );
			echo ( 'thue' === $loai ? '<th>Khu vực</th><th>Mã điểm</th>' : '<th>Nội dung</th>' );
			printf( '<th class="so">%s</th><th>Hết hạn</th><th>Trạng thái</th><th>Bản đủ dấu</th><th></th></tr></thead><tbody>', esc_html( $c['gia_tri'] ) );
			foreach ( $kq['rows'] as $h ) {
				echo '<tr>';
				printf( '<td>%s</td>', esc_html( 'thue' === $loai ? $h->gian : ( $h->so_hd ? $h->so_hd : '—' ) ) );
				printf( '<td>%s</td><td>%s</td>', esc_html( $h->doi_tac ), esc_html( $h->mst ? $h->mst : '—' ) );
				if ( 'thue' === $loai ) {
					printf( '<td>%s</td><td>%s</td>', esc_html( $h->khu_vuc ? $h->khu_vuc : '—' ), esc_html( $h->ma_diem ? $h->ma_diem : '—' ) );
				} else {
					printf( '<td>%s</td>', esc_html( $h->noi_dung ) );
				}
				printf(
					'<td class="so">%s%s</td><td>%s</td><td>%s</td>',
					esc_html( KHTC_UI::tien( $h->gia_tri ) ),
					$h->mien ? ' <em class="khtc-sub">miễn</em>' : '',
					esc_html( $h->ngay_het_han ? KHTC_UI::ngay( $h->ngay_het_han ) : '—' ),
					esc_html( $ttds[ $h->trang_thai ] ?? $h->trang_thai )
				);
				if ( $h->link_du_dau ) {
					printf( '<td><a href="%s" target="_blank" rel="noopener">Mở</a></td>', esc_url( $h->link_du_dau ) );
				} elseif ( $h->link_chua_dau ) {
					printf( '<td><a href="%s" target="_blank" rel="noopener" class="chi">chưa dấu</a></td>', esc_url( $h->link_chua_dau ) );
				} else {
					echo '<td class="chi">chưa có</td>';
				}
				echo '<td><form method="post" onsubmit="return confirm(\'Xoá hợp đồng này?\')">';
				wp_nonce_field( 'khtc_pd' );
				printf( '<input type="hidden" name="loai" value="%s">', esc_attr( $loai ) );
				printf( '<button type="submit" name="khtc_xoa_hd2" value="%d" class="button button-small">Xoá</button>', (int) $h->id );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
			self::phan_trang( 'phap-danh', array( 'trang' => $kq['trang'], 'so_trang' => $kq['so_trang'] ), array( 'loai' => $loai, 'tt' => $loc['trang_thai'], 'kv' => $loc['khu_vuc'], 'ht' => $loc['hinh_thuc'], 'tim' => $loc['tim'] ) );
		}
		echo '</div></div>';
	}

	// --------------------------------------------------------------- hồ sơ

	public static function ho_so() {
		KHTC_UI::nhan_doi_cty();
		$bao_ok = '';
		$bao_loi = '';

		if ( isset( $_POST['khtc_them_hs'] ) && check_admin_referer( 'khtc_hs' ) ) {
			$d = array();
			foreach ( array( 'loai', 'so_ct', 'ngay', 'doi_tac', 'so_tien', 'ten_file', 'link_file', 'ghi_chu' ) as $k ) {
				$d[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
			}
			$d['da_hach_toan'] = ! empty( $_POST['da_hach_toan'] );
			$kq = KHTC_HoSo::them( $d );
			if ( is_wp_error( $kq ) ) {
				$bao_loi = $kq->get_error_message();
			} else {
				KHTC_HoSo::do_noi( $kq );
				$bao_ok = 'Đã lưu hồ sơ.';
			}
		}

		if ( isset( $_POST['khtc_dan_hs'] ) && check_admin_referer( 'khtc_hs' ) && null !== ( $vb = self::lay_bang( 'bang_hs', $bao_loi ) ) ) {
			$kq     = KHTC_HoSo::dan_hang_loat( $vb );
			$noi    = KHTC_HoSo::do_noi();
			$bao_ok = sprintf( 'Đã nạp %d hồ sơ, nối được %d với bút toán trong sổ.', $kq['them'], $noi );
			if ( $kq['loi'] ) {
				$bao_loi = implode( ' · ', array_slice( $kq['loi'], 0, 5 ) )
					. ( count( $kq['loi'] ) > 5 ? ' … và ' . ( count( $kq['loi'] ) - 5 ) . ' dòng nữa' : '' );
			}
		}

		if ( isset( $_POST['khtc_do_noi'] ) && check_admin_referer( 'khtc_hs' ) ) {
			$bao_ok = sprintf( 'Dò xong — nối được %d chứng từ.', KHTC_HoSo::do_noi() );
		}

		if ( isset( $_POST['khtc_ht_hs'] ) && check_admin_referer( 'khtc_hs' ) ) {
			list( $id, $bat ) = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_POST['khtc_ht_hs'] ) ) ), 2, '' );
			$kq = KHTC_HoSo::dat_hach_toan( (int) $id, '1' === $bat );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã đổi trạng thái hạch toán.'; }
		}

		if ( isset( $_POST['khtc_go_noi'] ) && check_admin_referer( 'khtc_hs' ) ) {
			KHTC_HoSo::go_noi( (int) $_POST['khtc_go_noi'] );
			$bao_ok = 'Đã gỡ liên kết.';
		}

		if ( isset( $_POST['khtc_xoa_hs'] ) && check_admin_referer( 'khtc_hs' ) ) {
			$kq = KHTC_HoSo::xoa( (int) $_POST['khtc_xoa_hs'] );
			if ( is_wp_error( $kq ) ) { $bao_loi = $kq->get_error_message(); } else { $bao_ok = 'Đã xoá hồ sơ. Phục hồi được ở mục Nhật ký.'; }
		}

		list( $d1, $d2 ) = KHTC_UI::thang_nay();
		$loc = array(
			'tu'        => sanitize_text_field( wp_unslash( $_GET['tu'] ?? $d1 ) ),
			'den'       => sanitize_text_field( wp_unslash( $_GET['den'] ?? $d2 ) ),
			'loai'      => sanitize_text_field( wp_unslash( $_GET['lo'] ?? '' ) ),
			'hach_toan' => isset( $_GET['ht'] ) ? sanitize_text_field( wp_unslash( $_GET['ht'] ) ) : '',
			'da_noi'    => isset( $_GET['noi'] ) ? sanitize_text_field( wp_unslash( $_GET['noi'] ) ) : '',
			'tim'       => sanitize_text_field( wp_unslash( $_GET['tim'] ?? '' ) ),
			'trang'     => (int) ( $_GET['trang'] ?? 1 ),
		);
		$kq   = KHTC_HoSo::loc( $loc );
		$nhan = KHTC_HoSo::loai();

		echo '<div class="wrap khtc">';
		KHTC_UI::dau_trang( 'Hồ sơ' );
		KHTC_UI::thong_bao( 'ok', $bao_ok );
		KHTC_UI::thong_bao( 'loi', $bao_loi );

		KHTC_UI::the_so(
			array(
				array( 'Chứng từ trong kỳ', number_format( $kq['so_ct'], 0, ',', '.' ) ),
				array( 'Đã nối với bút toán', number_format( $kq['da_noi'], 0, ',', '.' ), 'thu' ),
				array( 'Đánh dấu đã hạch toán', number_format( $kq['da_ht'], 0, ',', '.' ) ),
				array( 'Đánh dấu nhưng chưa nối', number_format( $kq['lech'], 0, ',', '.' ), $kq['lech'] ? 'chi' : '' ),
			)
		);
		echo '<p class="khtc-sub"><strong>Đã hạch toán</strong> là cờ kế toán tự bật. <strong>Đã nối</strong> là máy tìm được đúng một bút toán mang số chứng từ đó trong sổ. Hai thứ này cố ý để riêng: chúng có thể khác nhau một cách hợp lệ (hạch toán trên Misa ngoài hệ thống này), nên màn hình chỉ ra chỗ lệch thay vì gộp lại — với một sổ đối chiếu giấy với sổ, che mất chỗ lệch là bỏ mất chính công việc.</p>';

		// ---- lọc
		printf( '<div class="khtc-panel"><h2>Lọc</h2><form method="get" action="%s"><div class="khtc-loc">', esc_url( self::url_form( 'ho-so' ) ) );
		self::an_get( 'ho-so' );
		printf( '<label>Từ ngày<input type="date" name="tu" value="%s"></label>', esc_attr( $loc['tu'] ) );
		printf( '<label>Đến ngày<input type="date" name="den" value="%s"></label>', esc_attr( $loc['den'] ) );
		echo '<label>Loại<select name="lo"><option value="">— Tất cả —</option>';
		foreach ( $nhan as $k => $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $loc['loai'], $k, false ), esc_html( $v ) );
		}
		echo '</select></label>';
		echo '<label>Hạch toán<select name="ht"><option value="">— Cả hai —</option>';
		printf( '<option value="1"%s>Đã đánh dấu</option>', selected( $loc['hach_toan'], '1', false ) );
		printf( '<option value="0"%s>Chưa đánh dấu</option>', selected( $loc['hach_toan'], '0', false ) );
		echo '</select></label>';
		echo '<label>Nối với sổ<select name="noi"><option value="">— Cả hai —</option>';
		printf( '<option value="1"%s>Đã nối</option>', selected( $loc['da_noi'], '1', false ) );
		printf( '<option value="0"%s>Chưa nối</option>', selected( $loc['da_noi'], '0', false ) );
		echo '</select></label>';
		printf( '<label>Tìm số CT / đối tác / tên file<input type="search" name="tim" value="%s"></label>', esc_attr( $loc['tim'] ) );
		echo '<button type="submit" class="button">Lọc</button>';
		printf( '<a href="%s" class="button">Bỏ lọc</a>', esc_url( self::url( 'ho-so' ) ) );
		echo '</div></form></div>';

		// ---- thiếu chứng từ: chiều ngược
		echo '<div class="khtc-panel"><h2>Bút toán chưa có chứng từ lưu</h2>';
		echo '<p class="khtc-sub">Chiều ngược của bảng bên dưới: bảng kia hỏi “giấy này đã vào sổ chưa”, bảng này hỏi “số này đã có giấy chưa”. Đây là câu kiểm toán sẽ hỏi.</p>';
		printf(
			'<p class="khtc-sub">Hợp đồng không nằm trong bảng này: mục <a href="%s">Pháp danh</a> đã đếm “chưa có bản đủ dấu” ngay trên chính hợp đồng. Trả lời cùng một câu ở hai nơi bằng hai cách đo thì sớm muộn hai chỗ nói khác nhau.</p>',
			esc_url( self::url( 'phap-danh' ) )
		);
		$co_thieu = false;
		foreach ( KHTC_HoSo::noi_duoc() as $k => $n ) {
			if ( empty( $n['nguoc'] ) ) { continue; }
			$thieu = KHTC_HoSo::thieu_chung_tu( $k, $loc['tu'], $loc['den'] );
			if ( ! $thieu ) { continue; }
			$co_thieu = true;
			printf(
				'<h3 class="khtc-sub" style="margin:14px 0 6px">%s <span class="khtc-dem chi">%d chứng từ · %s</span></h3>',
				esc_html( $nhan[ $k ] ),
				count( $thieu ),
				esc_html( KHTC_UI::tien( array_sum( array_map( function ( $r ) { return (int) $r->tien; }, $thieu ) ) ) )
			);
			echo '<table><thead><tr><th>Ngày</th><th>Số</th><th>Đối tác</th><th class="so">Số tiền</th></tr></thead><tbody>';
			foreach ( $thieu as $r ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td class="so chi">%s</td></tr>',
					esc_html( KHTC_UI::ngay( $r->ngay ) ),
					esc_html( $r->so ? $r->so : '—' ),
					esc_html( $r->ten ? $r->ten : '—' ),
					esc_html( KHTC_UI::tien( $r->tien ) )
				);
			}
			echo '</tbody></table>';
		}
		if ( ! $co_thieu ) {
			echo '<div class="khtc-trong">Mọi bút toán trong kỳ đều đã có chứng từ lưu.</div>';
		}
		echo '<form method="post" style="margin-top:12px">';
		wp_nonce_field( 'khtc_hs' );
		echo '<button type="submit" name="khtc_do_noi" value="1" class="button">Dò nối lại toàn bộ</button>';
		echo '</form>';
		echo '<p class="khtc-sub">Dò nối chỉ ghép khi số chứng từ khớp và trong sổ chỉ có <strong>đúng một</strong> bút toán mang số đó. Hai bút toán cùng số thì bỏ qua — nối nhầm còn tệ hơn không nối, vì bảng trên sẽ báo “đã có chứng từ” cho một bút toán thật ra chưa có.</p></div>';

		// ---- dán bảng
		echo '<details class="khtc-panel khtc-gap"><summary>Dán bảng hồ sơ</summary><form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'khtc_hs' );
		echo '<p class="khtc-sub">Mỗi dòng: <code>Loại · Số chứng từ · Ngày · Đối tác · Số tiền · Tên file · Link</code>. Từ cột 4 trở đi không bắt buộc. Loại ghi đúng tên như trong ô chọn bên dưới, ghi sai thì vào nhóm “Khác”.</p>';
		KHTC_Tep::o_nap( 'bang_hs', 'Hoá đơn đầu vào&#9;00012345&#9;05/08/2026&#9;EVN HCMC&#9;19.872.000&#9;hd-evn-t7.pdf&#9;https://...', 6 );
		echo '<p><button type="submit" name="khtc_dan_hs" value="1" class="button button-primary">Nạp và dò nối</button></p></form></details>';

		// ---- thêm một hồ sơ
		echo '<details class="khtc-panel khtc-gap"><summary>Thêm một chứng từ</summary><form method="post"><div class="khtc-loc">';
		wp_nonce_field( 'khtc_hs' );
		echo '<label>Loại<select name="loai">';
		foreach ( $nhan as $k => $v ) { printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $v ) ); }
		echo '</select></label>';
		echo '<label>Số chứng từ<input type="text" name="so_ct"></label>';
		echo '<label>Ngày<input type="date" name="ngay" required></label>';
		echo '<label>Đối tác<input type="text" name="doi_tac" style="min-width:180px"></label>';
		echo '<label>Số tiền<input type="text" name="so_tien"></label>';
		echo '<label>Tên file<input type="text" name="ten_file" style="min-width:160px"></label>';
		echo '<label>Link file<input type="url" name="link_file" style="min-width:180px"></label>';
		echo '<label>Đã hạch toán<input type="checkbox" name="da_hach_toan" value="1"></label>';
		echo '<button type="submit" name="khtc_them_hs" value="1" class="button button-primary">Lưu</button>';
		echo '</div><p class="khtc-sub">Lưu xong máy tự dò nối ngay với bút toán mang cùng số chứng từ.</p></form></details>';

		// ---- danh sách
		echo '<div class="khtc-panel"><h2>Sổ lưu chứng từ</h2>';
		if ( ! $kq['rows'] ) {
			echo '<div class="khtc-trong">Không có chứng từ nào khớp bộ lọc.</div>';
		} else {
			echo '<table><thead><tr><th>Ngày</th><th>Loại</th><th>Số CT</th><th>Đối tác</th><th class="so">Số tiền</th><th>File</th><th>Nối với sổ</th><th>Hạch toán</th><th></th></tr></thead><tbody>';
			foreach ( $kq['rows'] as $h ) {
				echo '<tr>';
				printf(
					'<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="so">%s</td>',
					esc_html( KHTC_UI::ngay( $h->ngay ) ),
					esc_html( $nhan[ $h->loai ] ?? $h->loai ),
					esc_html( $h->so_ct ? $h->so_ct : '—' ),
					esc_html( $h->doi_tac ? $h->doi_tac : '—' ),
					esc_html( $h->so_tien ? KHTC_UI::tien( $h->so_tien ) : '' )
				);
				if ( $h->link_file ) {
					printf( '<td><a href="%s" target="_blank" rel="noopener">%s</a></td>', esc_url( $h->link_file ), esc_html( $h->ten_file ? $h->ten_file : 'Mở' ) );
				} else {
					printf( '<td>%s</td>', esc_html( $h->ten_file ? $h->ten_file : '—' ) );
				}
				if ( $h->gan_id ) {
					echo '<td class="thu">' . esc_html( KHTC_HoSo::nhan_gan( $h ) );
					echo ' <form method="post" style="display:inline">';
					wp_nonce_field( 'khtc_hs' );
					printf( '<button type="submit" name="khtc_go_noi" value="%d" class="button button-small">Gỡ</button>', (int) $h->id );
					echo '</form></td>';
				} else {
					echo '<td class="khtc-sub">chưa nối</td>';
				}
				echo '<td><form method="post">';
				wp_nonce_field( 'khtc_hs' );
				if ( $h->da_hach_toan ) {
					printf( '<button type="submit" name="khtc_ht_hs" value="%d:0" class="button button-small">Đã — bỏ đánh dấu</button>', (int) $h->id );
					if ( ! $h->gan_id ) { echo '<div class="chi khtc-sub">chưa nối được với bút toán nào</div>'; }
				} else {
					printf( '<button type="submit" name="khtc_ht_hs" value="%d:1" class="button button-small">Chưa — đánh dấu</button>', (int) $h->id );
				}
				echo '</form></td>';
				echo '<td><form method="post" onsubmit="return confirm(\'Xoá chứng từ này khỏi sổ hồ sơ?\')">';
				wp_nonce_field( 'khtc_hs' );
				printf( '<button type="submit" name="khtc_xoa_hs" value="%d" class="button button-small">Xoá</button>', (int) $h->id );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
			self::phan_trang( 'ho-so', array( 'trang' => $kq['trang'], 'so_trang' => $kq['so_trang'] ), array( 'tu' => $loc['tu'], 'den' => $loc['den'], 'lo' => $loc['loai'], 'ht' => $loc['hach_toan'], 'noi' => $loc['da_noi'], 'tim' => $loc['tim'] ) );
		}
		echo '</div></div>';
	}
}
