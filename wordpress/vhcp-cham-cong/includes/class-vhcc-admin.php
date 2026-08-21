<?php
/**
 * Trang Cài đặt trong wp-admin: khai /exec, sinh khoá, thử cầu nối, chọn nguồn người dùng.
 *
 * Mọi thông báo phải NÓI RÕ SAI Ở BƯỚC NÀO. Cầu nối có 4 chỗ hỏng được (chưa dán CauNoiChamCong.gs,
 * sai WEB_KEY, chưa Deploy bản mới, sai URL) mà nếu chỉ báo "lỗi" thì phải mò cả bốn.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Admin {

	const CAP = 'manage_options';

	public static function menu() {
		add_menu_page(
			'Chấm Công', 'Chấm Công', self::CAP, 'vhcc', array( __CLASS__, 'page' ),
			'dashicons-portfolio', 57
		);
		add_submenu_page( 'vhcc', 'Cổng nhận từ máy', 'Cổng nhận từ máy', self::CAP,
			'vhcc-cong-may', array( __CLASS__, 'trang_cong_may' ) );
		add_submenu_page( 'vhcc', 'Bảng công & Lương', 'Bảng công & Lương', self::CAP,
			'vhcc-luong', array( __CLASS__, 'trang_luong' ) );
		add_submenu_page( 'vhcc', 'In bảng chấm công', 'In bảng chấm công', self::CAP,
			'vhcc-in', array( __CLASS__, 'trang_in' ) );
		add_submenu_page( 'vhcc', 'Nhân sự', 'Nhân sự', self::CAP,
			'vhcc-nhan-su', array( __CLASS__, 'trang_nhan_su' ) );
		add_submenu_page( 'vhcc', 'Phân lịch làm', 'Phân lịch làm', self::CAP,
			'vhcc-lich', array( __CLASS__, 'trang_lich' ) );
		add_submenu_page( 'vhcc', 'Máy & Firmware', 'Máy & Firmware', self::CAP,
			'vhcc-may', array( __CLASS__, 'trang_may' ) );
		/* Bốn màn còn lại nằm ở class-vhcc-man.php — tệp này đã ~1000 dòng, dồn hết vào một chỗ
		   là không ai đọc lại được. */
		VHCC_Man::menu_them( 'vhcc', self::CAP );
	}

	/**
	 * Người đang đăng nhập wp-admin, quy về khuôn vai trò của app chấm công.
	 *
	 * ⚠️ Màn quản trị WordPress chỉ mở cho người có `manage_options`, nên ở đây họ là ADMIN.
	 *    Hai bậc quyền (Cửa hàng trưởng vs Admin/Quản lý) nằm trong VHCC_NhanSu và có phép thử
	 *    riêng — để khi làm trang PIN cho cửa hàng trưởng thì dùng lại y nguyên, không phải viết
	 *    lại luật quyền lần thứ hai. Hai bản luật quyền là sớm muộn lệch nhau.
	 */
	public static function toi() {
		$u = wp_get_current_user();
		return array( 'name' => $u ? $u->display_name : 'admin', 'role' => 'ADMIN', 'coso' => '' );
	}

	/**
	 * Đọc bảng dán tay thành danh sách hồ sơ.
	 * ⚠️ Cắt theo TAB trước, rồi mới tới dấu phẩy: tên và địa chỉ người Việt có dấu phẩy trong đó
	 *    ("số 5, ngõ 2"), nên dán từ Excel (tab) là cách an toàn và phải được ưu tiên.
	 */
	private static function doc_csv( $tho ) {
		$cot = array( 'ma_nv', 'ho_ten', 'cua_hang', 'sdt', 'cccd', 'chuc_vu' );
		$ra = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $tho ) as $d ) {
			if ( '' === trim( $d ) ) { continue; }
			$o = ( false !== strpos( $d, "\t" ) ) ? explode( "\t", $d ) : explode( ',', $d );
			$hs = array();
			foreach ( $cot as $i => $k ) { $hs[ $k ] = isset( $o[ $i ] ) ? trim( $o[ $i ] ) : ''; }
			$ra[] = $hs;
		}
		return $ra;
	}

	/** Ô nhập một dòng cho bảng hồ sơ. */
	public static function o( $ten, $nhan, $gt, $kieu = 'text' ) {
		return '<tr><th style="width:190px">' . esc_html( $nhan ) . '</th><td><input type="'
			. esc_attr( $kieu ) . '" name="' . esc_attr( $ten ) . '" value="' . esc_attr( $gt )
			. '" class="regular-text" /></td></tr>';
	}

	/**
	 * NHÂN SỰ: danh sách hồ sơ · sửa một hồ sơ · xếp bộ phận cho cơ sở · khai mã song song.
	 * Mọi lượt ghi đi qua VHCC_NhanSu để đúng một bộ luật quyền, không phải hai.
	 */
	public static function trang_nhan_su() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$u   = self::toi();
		$bao = array();
		$xem_doi_ma = null;
		$xem_nhap   = null;
		$ds_nhap    = array();

		if ( isset( $_POST['vhcc_ns'] ) ) {
			check_admin_referer( 'vhcc_ns' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhcc_ns'] ) );
			if ( 'luu' === $viec ) {
				$dat = array();
				foreach ( array( 'ma_nv', 'ho_ten', 'cua_hang', 'pin_may', 'sdt', 'ngay_sinh',
					'gioi_tinh', 'cccd', 'dia_chi', 'nguoi_lien_he_khan', 'sdt_khan', 'chuc_vu',
					'ngay_vao_lam', 'trang_thai_lam_viec', 'loai_hop_dong', 'nhiem_vu', 'coso_phu',
					'pin_dang_nhap', 'luong_co_ban', 'so_tai_khoan', 'ngan_hang' ) as $o ) {
					if ( isset( $_POST[ $o ] ) ) { $dat[ $o ] = wp_unslash( $_POST[ $o ] ); }
				}
				$bao[] = VHCC_NhanSu::luu_ho_so( $u, $dat );
			} elseif ( 'xoa' === $viec ) {
				$bao[] = VHCC_NhanSu::xoa_ho_so( $u, wp_unslash( $_POST['ma_nv'] ) );
			} elseif ( 'bo_phan' === $viec ) {
				$bao[] = VHCC_NhanSu::xep_bo_phan( $u, wp_unslash( $_POST['coso'] ),
					wp_unslash( $_POST['bo_phan'] ), ! empty( $_POST['theo_gio'] ) );
			} elseif ( 'ma_ss' === $viec ) {
				$bao[] = VHCC_NhanSu::khai_ma_song_song( $u, wp_unslash( $_POST['ma_a'] ),
					wp_unslash( $_POST['ma_b'] ), wp_unslash( $_POST['ho_ten'] ), wp_unslash( $_POST['ly_do'] ) );
			} elseif ( 'xoa_nhieu' === $viec ) {
				$ds = preg_split( '/[\s,;]+/', (string) wp_unslash( $_POST['ds_ma'] ), -1, PREG_SPLIT_NO_EMPTY );
				$bao[] = VHCC_NhanSu::xoa_nhieu_ho_so( $u, $ds );
			} elseif ( 'bo_ma_ss' === $viec ) {
				$bao[] = VHCC_NhanSu::bo_ma_song_song( $u, wp_unslash( $_POST['ma_a'] ), wp_unslash( $_POST['ma_b'] ) );
			} elseif ( 'nghi' === $viec ) {
				$bao[] = VHCC_NhanSu::dat_nghi_viec( $u, wp_unslash( $_POST['ma_nv'] ),
					wp_unslash( $_POST['ngay_nghi'] ), wp_unslash( $_POST['ly_do'] ) );
			} elseif ( 'xem_doi_ma' === $viec ) {
				$xem_doi_ma = VHCC_NhanSu::xem_truoc_doi_ma( $u, wp_unslash( $_POST['ma_cu'] ),
					wp_unslash( $_POST['ma_moi'] ) );
			} elseif ( 'doi_ma' === $viec ) {
				$bao[] = VHCC_NhanSu::doi_ma_nv( $u, wp_unslash( $_POST['ma_cu'] ), wp_unslash( $_POST['ma_moi'] ) );
			} elseif ( 'xem_nhap' === $viec || 'nhap' === $viec ) {
				$ds_nhap = self::doc_csv( wp_unslash( $_POST['csv'] ) );
				if ( 'xem_nhap' === $viec ) {
					$xem_nhap = VHCC_NhanSu::xem_truoc_nhap( $u, $ds_nhap );
				} else {
					$bao[] = VHCC_NhanSu::nhap_hang_loat( $u, $ds_nhap,
						isset( $_POST['xac_nhan'] ) ? (int) $_POST['xac_nhan'] : null );
				}
			} elseif ( 'nhiem_vu' === $viec ) {
				$bao[] = VHCC_NhanSu::dat_nhiem_vu( $u, wp_unslash( $_POST['ngay'] ),
					wp_unslash( $_POST['coso'] ), wp_unslash( $_POST['ma_nv'] ),
					wp_unslash( $_POST['nhiem_vu'] ) );
			}
		}

		echo '<div class="wrap"><h1>Nhân sự</h1>';
		foreach ( $bao as $b ) {
			if ( ! empty( $b['ok'] ) ) { echo '<div class="notice notice-success"><p>Đã lưu.</p></div>'; }
			else { echo '<div class="notice notice-error"><p>' . esc_html( $b['error'] ) . '</p></div>'; }
		}

		$coso_loc = isset( $_GET['coso'] ) ? sanitize_text_field( wp_unslash( $_GET['coso'] ) ) : '';
		$tim      = isset( $_GET['tim'] ) ? sanitize_text_field( wp_unslash( $_GET['tim'] ) ) : '';
		$sua      = isset( $_GET['sua'] ) ? sanitize_text_field( wp_unslash( $_GET['sua'] ) ) : '';
		$ds_coso  = VHCC_NhanSu::ds_coso();

		echo '<form method="get"><input type="hidden" name="page" value="vhcc-nhan-su" />';
		echo '<select name="coso"><option value="">— mọi cơ sở —</option>';
		foreach ( $ds_coso as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . ( $x === $coso_loc ? ' selected' : '' ) . '>'
				. esc_html( $x ) . ' · ' . esc_html( VHCC_Luong::bo_phan_cua( $x ) ) . '</option>';
		}
		echo '</select> <input type="search" name="tim" value="' . esc_attr( $tim )
			. '" placeholder="mã / tên / SĐT / CCCD" /> <button class="button">Tìm</button> '
			. '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=vhcc-nhan-su&sua=+' ) )
			. '">+ Hồ sơ mới</a></form>';

		/* ---- Biểu mẫu sửa / tạo ---- */
		if ( '' !== $sua ) {
			$h = ( '+' === $sua ) ? array() : (array) VHCC_NhanSu::ho_so( $sua );
			$g = function ( $k ) use ( $h ) { return isset( $h[ $k ] ) ? (string) $h[ $k ] : ''; };
			echo '<h2>' . ( '+' === $sua ? 'Hồ sơ mới' : 'Sửa hồ sơ ' . esc_html( $sua ) ) . '</h2>';
			echo '<form method="post"><input type="hidden" name="vhcc_ns" value="luu" />';
			wp_nonce_field( 'vhcc_ns' );
			echo '<table class="form-table">';
			echo self::o( 'ma_nv', 'Mã NV *', $g( 'ma_nv' ) );
			if ( '+' === $sua ) {
				echo '<tr><th></th><td><em>Mã NV dùng chung CẢ CHUỖI — cấp trùng là gộp công hai '
					. 'người. Cửa hàng trưởng không tạo được hồ sơ mới vì lý do này.</em></td></tr>';
			}
			echo self::o( 'ho_ten', 'Họ tên', $g( 'ho_ten' ) );
			echo self::o( 'cua_hang', 'Cửa hàng chính', $g( 'cua_hang' ) );
			echo self::o( 'coso_phu', 'Cơ sở phụ (cách nhau dấu phẩy)', $g( 'coso_phu' ) );
			echo '<tr><th></th><td><em>Làm ở nhiều nơi thì khai vào <b>Cơ sở phụ</b>, đừng đổi '
				. '"Cửa hàng chính" — đổi cửa hàng chính là chuyển cả công và lương sang cửa hàng khác.'
				. '</em></td></tr>';
			echo self::o( 'chuc_vu', 'Chức vụ', $g( 'chuc_vu' ) );
			echo self::o( 'nhiem_vu', 'Nhiệm vụ (cách nhau dấu phẩy)', $g( 'nhiem_vu' ) );
			echo '<tr><th></th><td><em>Chỉ có nghĩa ở Nhóm Máy Tự Động. "Trực Ghế Posh - JP" là '
				. 'tính theo GIỜ, khác đơn giá.</em></td></tr>';
			echo self::o( 'trang_thai_lam_viec', 'Trạng thái làm việc', $g( 'trang_thai_lam_viec' ) );
			echo self::o( 'sdt', 'SĐT', $g( 'sdt' ) );
			echo self::o( 'ngay_sinh', 'Ngày sinh', $g( 'ngay_sinh' ), 'date' );
			echo self::o( 'gioi_tinh', 'Giới tính', $g( 'gioi_tinh' ) );
			echo self::o( 'cccd', 'CCCD', $g( 'cccd' ) );
			echo self::o( 'dia_chi', 'Địa chỉ', $g( 'dia_chi' ) );
			echo self::o( 'nguoi_lien_he_khan', 'Người liên hệ khẩn', $g( 'nguoi_lien_he_khan' ) );
			echo self::o( 'sdt_khan', 'SĐT khẩn', $g( 'sdt_khan' ) );
			echo self::o( 'ngay_vao_lam', 'Ngày vào làm', $g( 'ngay_vao_lam' ), 'date' );
			echo self::o( 'loai_hop_dong', 'Loại hợp đồng', $g( 'loai_hop_dong' ) );
			echo self::o( 'pin_may', 'PIN máy chấm công', $g( 'pin_may' ) );
			echo self::o( 'pin_dang_nhap', 'PIN đăng nhập web', $g( 'pin_dang_nhap' ) );
			if ( VHCC_NhanSu::co_xem_luong( $u ) ) {
				echo self::o( 'luong_co_ban', 'Lương cơ bản / tháng', $g( 'luong_co_ban' ) );
				echo '<tr><th></th><td><em>Gõ kiểu nào cũng được: <code>13.000.000</code>, '
					. '<code>13,000,000</code> hay <code>13000000</code>.</em></td></tr>';
				echo self::o( 'so_tai_khoan', 'Số tài khoản', $g( 'so_tai_khoan' ) );
				echo self::o( 'ngan_hang', 'Ngân hàng', $g( 'ngan_hang' ) );
			}
			echo '</table><p><button class="button button-primary">Lưu hồ sơ</button></p></form>';
			if ( '+' !== $sua ) {
				echo '<form method="post" onsubmit="return confirm(\'Xoá hồ sơ này?\')">'
					. '<input type="hidden" name="vhcc_ns" value="xoa" />'
					. '<input type="hidden" name="ma_nv" value="' . esc_attr( $sua ) . '" />';
				wp_nonce_field( 'vhcc_ns' );
				echo '<p><button class="button button-link-delete">Xoá hồ sơ</button> '
					. '<em>Còn lượt chấm công thì hệ thống sẽ chặn — bảng lương sẽ có mã mà không '
					. 'tra ra tên. Cho nghỉ thì đổi "Trạng thái làm việc".</em></p></form>';
			}
		}

		/* ---- Danh sách ---- */
		$ds = VHCC_NhanSu::ds_nhan_vien( $u, $coso_loc, $tim );
		echo '<h2>Danh sách (' . count( $ds ) . ')</h2>';
		echo '<table class="widefat striped"><thead><tr><th>Mã NV</th><th>Họ tên</th>'
			. '<th>Cửa hàng</th><th>Cơ sở phụ</th><th>Chức vụ</th><th>Nhiệm vụ</th>'
			. '<th>Trạng thái</th><th>SĐT</th>'
			. ( VHCC_NhanSu::co_xem_luong( $u ) ? '<th>Lương cơ bản</th>' : '' )
			. '<th></th></tr></thead><tbody>';
		foreach ( $ds as $r ) {
			echo '<tr><td><code>' . esc_html( $r['ma_nv'] ) . '</code></td>'
				. '<td>' . esc_html( $r['ho_ten'] ) . '</td>'
				. '<td>' . esc_html( $r['cua_hang'] ) . '</td>'
				. '<td>' . esc_html( $r['coso_phu'] ) . '</td>'
				. '<td>' . esc_html( $r['chuc_vu'] ) . '</td>'
				. '<td>' . esc_html( $r['nhiem_vu'] ) . '</td>'
				. '<td>' . esc_html( $r['trang_thai_lam_viec'] ) . '</td>'
				. '<td>' . esc_html( $r['sdt'] ) . '</td>'
				. ( isset( $r['luong_co_ban'] )
					? '<td>' . esc_html( $r['luong_co_ban'] ? number_format( (float) $r['luong_co_ban'] ) : '—' ) . '</td>'
					: '' )
				. '<td><a href="' . esc_url( admin_url( 'admin.php?page=vhcc-nhan-su&sua=' . urlencode( $r['ma_nv'] ) ) )
				. '">Sửa</a></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Bộ phận theo cơ sở ---- */
		echo '<h2>Bộ phận của cơ sở</h2>';
		echo '<p>Bộ phận quyết định <strong>công thức lương</strong> của cả cơ sở. Tên ngoài danh '
			. 'sách thì cơ sở đó thành <em>Chưa xếp</em> và <strong>không được tính lương</strong> — '
			. 'hệ thống sẽ từ chối chứ không lặng lẽ đổi.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Cơ sở</th><th>Bộ phận</th>'
			. '<th>Nhóm lương</th><th>Đặt lại</th></tr></thead><tbody>';
		foreach ( $ds_coso as $x ) {
			$bp  = VHCC_Luong::bo_phan_cua( $x );
			$nh  = VHCC_Luong::nhom_coso( $x );
			echo '<tr><td><code>' . esc_html( $x ) . '</code></td><td>' . esc_html( $bp ) . '</td>'
				. '<td>' . esc_html( $nh ? $nh['ten'] : '—' )
				. ( VHCC_Luong::coso_tinh_theo_gio( $x ) ? ' <em>(tính theo giờ)</em>' : '' ) . '</td>'
				. '<td><form method="post" style="display:flex;gap:6px">'
				. '<input type="hidden" name="vhcc_ns" value="bo_phan" />'
				. '<input type="hidden" name="coso" value="' . esc_attr( $x ) . '" />';
			echo wp_nonce_field( 'vhcc_ns', '_wpnonce', true, false );
			echo '<select name="bo_phan">';
			foreach ( array_merge( array( '' ), VHCC_Luong::BP_DS ) as $b ) {
				echo '<option value="' . esc_attr( $b ) . '"' . ( $b === $bp ? ' selected' : '' ) . '>'
					. esc_html( '' === $b ? '(chưa xếp)' : $b ) . '</option>';
			}
			echo '</select><button class="button">Lưu</button></form></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Mã đã chấm công mà chưa có hồ sơ ---- */
		$chua = VHCC_NhanSu::ds_chua_co_ho_so( $u );
		echo '<h2>Mã đã chấm công mà CHƯA có hồ sơ (' . count( $chua ) . ')</h2>';
		echo '<p>Người thật, công thật, mà bảng lương <strong>không tra ra tên</strong>. Lập hồ sơ '
			. 'cho họ, hoặc khai mã song song nếu đó là mã cũ của người đã có hồ sơ.</p>';
		if ( $chua ) {
			echo '<table class="widefat striped"><thead><tr><th>Cơ sở</th><th>Mã NV</th><th>Tên máy gửi</th>'
				. '<th>Số lượt</th><th>Ngày cuối</th><th></th></tr></thead><tbody>';
			foreach ( $chua as $r ) {
				echo '<tr><td>' . esc_html( $r['coso'] ) . '</td><td><code>' . esc_html( $r['ma_nv'] )
					. '</code></td><td>' . esc_html( $r['ho_ten'] ) . '</td><td>' . (int) $r['so']
					. '</td><td>' . esc_html( $r['ngay_cuoi'] ) . '</td><td><a class="button" href="'
					. esc_url( admin_url( 'admin.php?page=vhcc-nhan-su&sua=+' ) ) . '">Lập hồ sơ</a></td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ---- Xoá nhiều hồ sơ ---- */
		echo '<h2>Xoá nhiều hồ sơ</h2>';
		echo '<p>Dán danh sách Mã NV. Từng cái đi qua <strong>đúng chốt</strong> của xoá một hồ sơ: '
			. 'người còn lượt chấm công sẽ bị bỏ (kèm lý do), vì xoá là bảng lương có mã mà không tra '
			. 'ra tên. Muốn cho nghỉ thì dùng ô bên dưới.</p>';
		echo '<form method="post"><input type="hidden" name="vhcc_ns" value="xoa_nhieu" />';
		wp_nonce_field( 'vhcc_ns' );
		echo '<p><textarea name="ds_ma" rows="2" class="large-text" placeholder="NV001, NV002…"></textarea></p>';
		echo '<p><button class="button button-link-delete" onclick="return confirm(\'Xoá các hồ sơ '
			. 'này?\')">Xoá</button></p></form>';

		/* ---- Cho nghỉ việc ---- */
		echo '<h2>Cho nghỉ việc</h2>';
		echo '<p>Đây là đường <strong>đúng</strong> thay cho xoá hồ sơ: giữ nguyên hồ sơ và toàn bộ '
			. 'chấm công, chỉ đổi trạng thái. Nhờ vậy bảng lương tháng cũ vẫn tra ra tên.</p>';
		echo '<form method="post" style="display:flex;gap:6px;align-items:center">'
			. '<input type="hidden" name="vhcc_ns" value="nghi" />';
		wp_nonce_field( 'vhcc_ns' );
		echo '<input name="ma_nv" placeholder="Mã NV" required /> '
			. '<input type="date" name="ngay_nghi" /> <input name="ly_do" placeholder="lý do" /> '
			. '<button class="button">Cho nghỉ</button></form>';

		/* ---- Đổi mã NV ---- */
		echo '<h2>Đổi mã nhân viên</h2>';
		echo '<div class="notice notice-warning"><p>Đổi mã là sửa <strong>mọi hàng chấm công</strong> '
			. 'đã có của người đó. Đổi rồi mới thấy sai thì <strong>không có đường lùi</strong>: hàng '
			. 'cũ đã mang mã mới, không phân biệt được với hàng vốn thuộc mã mới. Xem trước đã.</p></div>';
		echo '<form method="post" style="display:flex;gap:6px;align-items:center">'
			. '<input type="hidden" name="vhcc_ns" value="xem_doi_ma" />';
		wp_nonce_field( 'vhcc_ns' );
		echo '<input name="ma_cu" placeholder="Mã cũ" required /> → '
			. '<input name="ma_moi" placeholder="Mã mới" required /> '
			. '<button class="button">Xem trước</button></form>';
		if ( is_array( $xem_doi_ma ) ) {
			if ( empty( $xem_doi_ma['ok'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $xem_doi_ma['error'] ) . '</p></div>';
			} else {
				echo '<div class="notice notice-info"><p><strong>' . esc_html( $xem_doi_ma['maCu'] )
					. ' → ' . esc_html( $xem_doi_ma['maMoi'] ) . '</strong> ('
					. esc_html( $xem_doi_ma['hoTen'] ) . ') sẽ sửa: <strong>'
					. (int) $xem_doi_ma['soHangChamCong'] . '</strong> hàng chấm công · '
					. (int) $xem_doi_ma['soOLich'] . ' ô lịch · '
					. (int) $xem_doi_ma['soDongPhanQuyen'] . ' dòng phân quyền. Cơ sở liên quan: '
					. esc_html( implode( ', ', $xem_doi_ma['coSoLienQuan'] ) ) . '.</p>'
					. ( '' !== $xem_doi_ma['canhBao']
						? '<p><strong>⚠️ ' . esc_html( $xem_doi_ma['canhBao'] ) . '</strong></p>' : '' )
					. '<form method="post"><input type="hidden" name="vhcc_ns" value="doi_ma" />'
					. '<input type="hidden" name="ma_cu" value="' . esc_attr( $xem_doi_ma['maCu'] ) . '" />'
					. '<input type="hidden" name="ma_moi" value="' . esc_attr( $xem_doi_ma['maMoi'] ) . '" />';
				echo wp_nonce_field( 'vhcc_ns', '_wpnonce', true, false );
				echo '<p><button class="button button-primary" onclick="return confirm(\'Đổi mã thật? '
					. 'Không có đường lùi.\')">Đổi mã</button></p></form></div>';
			}
		}

		/* ---- Nhiệm vụ theo ngày ---- */
		echo '<h2>Nhiệm vụ theo ngày</h2>';
		echo '<p>Chỉ có nghĩa ở <strong>Nhóm Máy Tự Động</strong> (POSH/JP, hoặc cơ sở được tích "tính '
			. 'theo giờ"). Cơ sở khác thì hệ thống từ chối chứ không ghi một giá trị không ảnh hưởng gì '
			. 'rồi để anh tưởng đã khai xong.</p>';
		echo '<form method="post" style="display:flex;gap:6px;align-items:center">'
			. '<input type="hidden" name="vhcc_ns" value="nhiem_vu" />';
		wp_nonce_field( 'vhcc_ns' );
		echo '<input type="date" name="ngay" required /> ';
		echo '<select name="coso"><option value="">— cơ sở —</option>';
		foreach ( $ds_coso as $x ) {
			echo '<option value="' . esc_attr( $x ) . '">' . esc_html( $x ) . '</option>';
		}
		echo '</select> <input name="ma_nv" placeholder="Mã NV" required /> '
			. '<input name="nhiem_vu" placeholder="Thu Tiền - Vệ Sinh / Trực Ghế Posh - JP" /> '
			. '<button class="button">Lưu nhiệm vụ</button></form>';

		/* ---- Nhập hàng loạt ---- */
		echo '<h2>Nhập nhân sự hàng loạt</h2>';
		echo '<p>Mỗi dòng một người, các ô cách nhau bằng dấu phẩy hoặc tab, theo thứ tự: '
			. '<code>Mã NV, Họ tên, Cửa hàng, SĐT, CCCD, Chức vụ</code>. Bấm <strong>Xem trước</strong> '
			. 'đã — nó bắt cả trùng mã <em>trong chính tệp</em>, mà hai dòng cùng mã là một cái ghi đè '
			. 'cái kia không ai thấy.</p>';
		echo '<form method="post"><input type="hidden" name="vhcc_ns" value="xem_nhap" />';
		wp_nonce_field( 'vhcc_ns' );
		echo '<p><textarea name="csv" rows="6" class="large-text" placeholder="NV001, Nguyễn A, TUTU_BT, 0900…">'
			. esc_textarea( isset( $_POST['csv'] ) ? wp_unslash( $_POST['csv'] ) : '' ) . '</textarea></p>';
		echo '<p><button class="button">Xem trước</button></p></form>';
		if ( is_array( $xem_nhap ) && ! empty( $xem_nhap['ok'] ) ) {
			echo '<div class="notice notice-info"><p>Sẽ <strong>thêm ' . (int) $xem_nhap['dem']['them']
				. '</strong> · <strong>cập nhật ' . (int) $xem_nhap['dem']['capNhat']
				. '</strong> · bỏ ' . (int) $xem_nhap['dem']['bo'] . '.</p>';
			echo '<table class="widefat striped"><thead><tr><th>Dòng</th><th>Mã NV</th><th>Họ tên</th>'
				. '<th>Cửa hàng</th><th>Việc</th><th>Vì sao bỏ</th></tr></thead><tbody>';
			foreach ( $xem_nhap['dong'] as $r ) {
				echo '<tr><td>' . (int) $r['dong'] . '</td><td><code>' . esc_html( $r['maNV'] )
					. '</code></td><td>' . esc_html( $r['hoTen'] ) . '</td><td>'
					. esc_html( $r['cuaHang'] ) . '</td><td>' . esc_html( $r['viec'] ) . '</td><td>'
					. esc_html( isset( $r['vaoSao'] ) ? $r['vaoSao'] : '' ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<form method="post"><input type="hidden" name="vhcc_ns" value="nhap" />'
				. '<input type="hidden" name="csv" value="' . esc_attr( wp_unslash( $_POST['csv'] ) ) . '" />'
				. '<input type="hidden" name="xac_nhan" value="'
				. ( (int) $xem_nhap['dem']['them'] + (int) $xem_nhap['dem']['capNhat'] ) . '" />';
			echo wp_nonce_field( 'vhcc_ns', '_wpnonce', true, false );
			echo '<p><button class="button button-primary">Nhập thật</button> <em>Nếu tệp đã đổi giữa '
				. 'hai bước thì hệ thống từ chối — anh sẽ không nhập một thứ khác cái vừa xem.</em></p>'
				. '</form></div>';
		}

		/* ---- Mã song song ---- */
		global $wpdb;
		echo '<h2>Mã chạy song song</h2>';
		echo '<p>Một người có hai mã (máy cũ chưa nhận lệnh đổi mã). <strong>Phải khai</strong> — hệ '
			. 'thống không bao giờ tự suy "hai mã này chắc là một người" từ tên, vì tên người Việt '
			. 'trùng rất nhiều và đoán sai là gộp lương hai người khác nhau.</p>';
		echo '<form method="post" style="display:flex;gap:6px;align-items:center;margin-bottom:10px">'
			. '<input type="hidden" name="vhcc_ns" value="ma_ss" />';
		wp_nonce_field( 'vhcc_ns' );
		echo '<input name="ma_a" placeholder="Mã A" required /> <input name="ma_b" placeholder="Mã B" required /> '
			. '<input name="ho_ten" placeholder="Họ tên" /> <input name="ly_do" placeholder="Lý do" /> '
			. '<button class="button">Khai cặp mã</button></form>';
		echo '<table class="widefat striped"><thead><tr><th>Mã A</th><th>Mã B</th><th>Họ tên</th>'
			. '<th>Lý do</th><th>Người khai</th><th></th></tr></thead><tbody>';
		foreach ( VHCC_NhanSu::ds_ma_song_song() as $r ) {
			echo '<tr><td><code>' . esc_html( $r['ma_a'] ) . '</code></td><td><code>'
				. esc_html( $r['ma_b'] ) . '</code></td><td>' . esc_html( $r['ho_ten'] ) . '</td>'
				. '<td>' . esc_html( $r['ly_do'] ) . '</td><td>' . esc_html( $r['nguoi_khai'] ) . '</td>'
				. '<td><form method="post"><input type="hidden" name="vhcc_ns" value="bo_ma_ss" />'
				. '<input type="hidden" name="ma_a" value="' . esc_attr( $r['ma_a'] ) . '" />'
				. '<input type="hidden" name="ma_b" value="' . esc_attr( $r['ma_b'] ) . '" />';
			echo wp_nonce_field( 'vhcc_ns', '_wpnonce', true, false );
			echo '<button class="button button-link-delete">Bỏ cặp</button></form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/** PHÂN LỊCH LÀM + duyệt xin đổi lịch. */
	public static function trang_lich() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$u = self::toi();
		$bao = array();
		if ( isset( $_POST['vhcc_lich'] ) ) {
			check_admin_referer( 'vhcc_lich' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhcc_lich'] ) );
			if ( 'xep' === $viec ) {
				$bao[] = VHCC_Lich::xep_lich( $u, wp_unslash( $_POST['coso'] ), array( array(
					'ngay' => wp_unslash( $_POST['ngay'] ), 'ma_nv' => wp_unslash( $_POST['ma_nv'] ),
					'ho_ten' => wp_unslash( $_POST['ho_ten'] ), 'ca' => wp_unslash( $_POST['ca'] ),
					'viec' => wp_unslash( $_POST['viec'] ) ) ) );
			} elseif ( 'duyet' === $viec || 'tu_choi' === $viec ) {
				$bao[] = VHCC_Lich::duyet( $u, wp_unslash( $_POST['ma_yc'] ), 'duyet' === $viec );
			} elseif ( 'xoa_o' === $viec ) {
				$bao[] = VHCC_Lich::xoa_o_lich( $u, wp_unslash( $_POST['coso'] ),
					wp_unslash( $_POST['ngay'] ), wp_unslash( $_POST['ma_nv'] ), wp_unslash( $_POST['ca'] ) );
			} elseif ( 'ca' === $viec ) {
				$bao[] = VHCC_Lich::dat_ca( $u, preg_split( '/[\r\n,;]+/',
					(string) wp_unslash( $_POST['ds'] ), -1, PREG_SPLIT_NO_EMPTY ) );
			} elseif ( 'loai_viec' === $viec ) {
				$bao[] = VHCC_Lich::dat_loai_viec( $u, preg_split( '/[\r\n,;]+/',
					(string) wp_unslash( $_POST['ds'] ), -1, PREG_SPLIT_NO_EMPTY ) );
			} elseif ( 'cs_bat' === $viec ) {
				$bao[] = VHCC_Lich::dat_coso_bat_lich( $u,
					isset( $_POST['cs'] ) ? (array) wp_unslash( $_POST['cs'] ) : array() );
			}
		}

		$coso = isset( $_GET['coso'] ) ? sanitize_text_field( wp_unslash( $_GET['coso'] ) ) : '';
		$tu   = isset( $_GET['tu'] ) ? sanitize_text_field( wp_unslash( $_GET['tu'] ) ) : gmdate( 'Y-m-01' );
		$den  = isset( $_GET['den'] ) ? sanitize_text_field( wp_unslash( $_GET['den'] ) ) : gmdate( 'Y-m-t' );

		echo '<div class="wrap"><h1>Phân lịch làm</h1>';
		foreach ( $bao as $b ) {
			if ( ! empty( $b['ok'] ) ) { echo '<div class="notice notice-success"><p>Đã lưu.</p></div>'; }
			else { echo '<div class="notice notice-error"><p>' . esc_html( $b['error'] ) . '</p></div>'; }
		}
		echo '<p><em>Lịch là <strong>dự định</strong>, chấm công là <strong>thực tế</strong>. Xếp '
			. 'lịch KHÔNG ghi gì vào bảng chấm công — nếu ghi thì bảng lương sẽ thấy những ngày có '
			. 'hàng mà không có giờ, trông y như "đi làm mà quên chấm", và thành trả tiền theo dự định.'
			. '</em></p>';

		echo '<form method="get"><input type="hidden" name="page" value="vhcc-lich" />';
		echo '<select name="coso"><option value="">— chọn cơ sở —</option>';
		foreach ( VHCC_NhanSu::ds_coso() as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . ( $x === $coso ? ' selected' : '' ) . '>'
				. esc_html( $x ) . '</option>';
		}
		echo '</select> <input type="date" name="tu" value="' . esc_attr( $tu ) . '" /> '
			. '<input type="date" name="den" value="' . esc_attr( $den ) . '" /> '
			. '<button class="button button-primary">Xem</button></form>';

		/* ---- Yêu cầu chờ duyệt ---- */
		$yc = VHCC_Lich::ds_doi_lich( $u, true );
		echo '<h2>Xin đổi lịch — chờ duyệt (' . count( $yc ) . ')</h2>';
		if ( ! $yc ) {
			echo '<p><em>Không có yêu cầu nào chờ duyệt.</em></p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Cơ sở</th><th>Mã NV</th><th>Tên</th>'
				. '<th>Ngày</th><th>Ca</th><th>Việc mới</th><th>Đổi sang ngày</th><th>Lý do</th>'
				. '<th>Người xin</th><th></th></tr></thead><tbody>';
			foreach ( $yc as $r ) {
				echo '<tr><td>' . esc_html( $r['coso'] ) . '</td><td><code>' . esc_html( $r['ma_nv'] )
					. '</code></td><td>' . esc_html( $r['ho_ten'] ) . '</td><td>' . esc_html( $r['ngay'] )
					. '</td><td>' . esc_html( $r['ca'] ) . '</td><td>' . esc_html( $r['viec_moi'] )
					. '</td><td>' . esc_html( (string) $r['doi_sang_ngay'] ) . '</td><td>'
					. esc_html( $r['ly_do'] ) . '</td><td>' . esc_html( $r['nguoi_xin'] ) . '</td><td>';
				foreach ( array( 'duyet' => 'Duyệt', 'tu_choi' => 'Từ chối' ) as $v => $nhan ) {
					echo '<form method="post" style="display:inline">'
						. '<input type="hidden" name="vhcc_lich" value="' . esc_attr( $v ) . '" />'
						. '<input type="hidden" name="ma_yc" value="' . esc_attr( $r['ma_yc'] ) . '" />';
					echo wp_nonce_field( 'vhcc_lich', '_wpnonce', true, false );
					echo '<button class="button">' . esc_html( $nhan ) . '</button></form> ';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p><em>Duyệt là <strong>ghi thật</strong> vào lịch, không chỉ đổi trạng thái. Có '
				. '"Đổi sang ngày" thì ngày cũ được để TRỐNG việc và ngày mới nhận việc — không thì '
				. 'người đó bị xếp cả hai ngày.</em></p>';
		}

		if ( '' === $coso ) { echo '</div>'; return; }

		/* ---- Thêm một ô lịch ---- */
		echo '<h2>Xếp một ô lịch</h2>';
		echo '<form method="post"><input type="hidden" name="vhcc_lich" value="xep" />'
			. '<input type="hidden" name="coso" value="' . esc_attr( $coso ) . '" />';
		wp_nonce_field( 'vhcc_lich' );
		echo '<table class="form-table">'
			. self::o( 'ngay', 'Ngày *', gmdate( 'Y-m-d' ), 'date' )
			. self::o( 'ma_nv', 'Mã NV *', '' )
			. self::o( 'ho_ten', 'Họ tên', '' )
			. self::o( 'ca', 'Ca', '' )
			. self::o( 'viec', 'Việc', '' )
			. '</table>';
		echo '<p><em>Khoá của một ô là <strong>(cơ sở, ngày, mã NV, ca)</strong> — bốn thứ. Nhờ có '
			. '"ca" trong khoá mà một người làm hai ca trong cùng một ngày giữ được cả hai ô; bỏ '
			. '"ca" ra là ca trước bị ghi đè mất mà ô vẫn có dữ liệu nên không ai thấy.</em></p>';
		echo '<p><button class="button button-primary">Lưu ô lịch</button></p></form>';

		/* ---- Lịch đã xếp ---- */
		$ds = VHCC_Lich::ds_lich( $coso, $tu, $den );
		echo '<h2>Lịch đã xếp (' . count( $ds ) . ')</h2>';
		echo '<table class="widefat striped"><thead><tr><th>Ngày</th><th>Mã NV</th><th>Họ tên</th>'
			. '<th>Ca</th><th>Việc</th><th>Người xếp</th><th></th></tr></thead><tbody>';
		foreach ( $ds as $r ) {
			echo '<tr><td>' . esc_html( $r['ngay'] ) . '</td><td><code>' . esc_html( $r['ma_nv'] )
				. '</code></td><td>' . esc_html( $r['ho_ten'] ) . '</td><td>' . esc_html( $r['ca'] )
				. '</td><td>' . esc_html( $r['viec'] ) . '</td><td>' . esc_html( $r['nguoi_xep'] ) . '</td>'
				. '<td><form method="post"><input type="hidden" name="vhcc_lich" value="xoa_o" />'
				. '<input type="hidden" name="coso" value="' . esc_attr( $coso ) . '" />'
				. '<input type="hidden" name="ngay" value="' . esc_attr( $r['ngay'] ) . '" />'
				. '<input type="hidden" name="ma_nv" value="' . esc_attr( $r['ma_nv'] ) . '" />'
				. '<input type="hidden" name="ca" value="' . esc_attr( $r['ca'] ) . '" />';
			echo wp_nonce_field( 'vhcc_lich', '_wpnonce', true, false );
			echo '<button class="button button-link-delete">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Cấu hình lịch ---- */
		$cf = VHCC_Lich::cau_hinh( $u );
		echo '<h2>Cấu hình lịch</h2>';
		echo '<div style="display:flex;gap:24px;flex-wrap:wrap">';
		echo '<form method="post"><input type="hidden" name="vhcc_lich" value="ca" />';
		wp_nonce_field( 'vhcc_lich' );
		echo '<h3>Danh sách ca</h3><p><textarea name="ds" rows="4" cols="26">'
			. esc_textarea( implode( "\n", (array) $cf['ca'] ) ) . '</textarea></p>';
		echo '<p><button class="button">Lưu ca</button></p>'
			. '<p style="max-width:340px"><em>⚠️ Đổi TÊN một ca KHÔNG đổi tên trong những ô lịch đã '
			. 'xếp — <code>ca</code> là một phần khoá của ô lịch. Hệ thống sẽ báo ra số ô đang dùng '
			. 'tên vừa bị bỏ, chứ không để im.</em></p></form>';
		echo '<form method="post"><input type="hidden" name="vhcc_lich" value="loai_viec" />';
		wp_nonce_field( 'vhcc_lich' );
		echo '<h3>Loại công việc</h3><p><textarea name="ds" rows="4" cols="26">'
			. esc_textarea( implode( "\n", (array) $cf['loaiViec'] ) ) . '</textarea></p>';
		echo '<p><button class="button">Lưu loại việc</button></p></form>';
		echo '<form method="post"><input type="hidden" name="vhcc_lich" value="cs_bat" />';
		wp_nonce_field( 'vhcc_lich' );
		echo '<h3>Cơ sở bật phân lịch</h3>';
		foreach ( VHCC_NhanSu::ds_coso() as $x ) {
			echo '<label style="display:block"><input type="checkbox" name="cs[]" value="'
				. esc_attr( $x ) . '"' . ( in_array( $x, (array) $cf['coSoBatLich'], true ) ? ' checked' : '' )
				. ' /> ' . esc_html( $x ) . '</label>';
		}
		echo '<p><button class="button">Lưu</button></p>'
			. '<p style="max-width:340px"><em>Tắt lịch của một cơ sở <strong>không xoá</strong> ô lịch '
			. 'nào đã xếp — chỉ ẩn màn xếp lịch. Xoá là mất lịch những ngày sắp tới, mà bật lại thì '
			. 'không dựng lại được.</em></p></form>';
		echo '</div></div>';
	}

	/**
	 * MÁY CHẤM CÔNG + CẬP NHẬT FIRMWARE.
	 *
	 * Dữ liệu vẫn ở Firebase (anh Thắng chốt giữ nguyên) — màn này gọi Apps Script qua cầu nối,
	 * WordPress không nói chuyện trực tiếp với Firebase. Xem class-vhcc-may.php.
	 *
	 * ⚠️ Phần ĐỐI CHIẾU để ĐẦU trang có chủ ý: đó là chỗ duy nhất phát hiện được ca "cùng một lượt
	 *    bấm rơi vào hai cơ sở khác nhau", mà ca đó không có gì tự báo và chỉ lộ ra ở bảng lương
	 *    cuối tháng. Để nó xuống dưới cùng là đúng thứ quan trọng nhất bị cuộn qua.
	 */
	public static function trang_may() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$bao = array();
		if ( isset( $_POST['vhcc_may'] ) ) {
			check_admin_referer( 'vhcc_may' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhcc_may'] ) );
			if ( 'gan' === $viec ) {
				$bao[] = VHCC_May::gan_may( (int) $_POST['hang'], wp_unslash( $_POST['coso'] ) );
			} elseif ( 'bo_gan' === $viec ) {
				$bao[] = VHCC_May::bo_gan( (int) $_POST['hang'] );
			} elseif ( 'soi' === $viec ) {
				$bao[] = VHCC_May::soi_lai_mysql();
			} elseif ( 'sim' === $viec ) {
				$bao[] = VHCC_May::luu_sim( (int) $_POST['hang'], wp_unslash( $_POST['sim'] ) );
			} elseif ( 'quet' === $viec ) {
				$bao[] = VHCC_May::yeu_cau_quet( wp_unslash( $_POST['tram'] ) );
			} elseif ( 'ota' === $viec ) {
				$bao[] = VHCC_May::dat_ota( wp_unslash( $_POST['ver'] ), wp_unslash( $_POST['url'] ),
					wp_unslash( $_POST['xac_nhan'] ) );
			} elseif ( 'go_ota' === $viec ) {
				$bao[] = VHCC_May::go_ota();
			}
		}

		echo '<div class="wrap"><h1>Máy chấm công &amp; Firmware</h1>';
		foreach ( $bao as $b ) {
			if ( ! empty( $b['ok'] ) ) {
				$them = '';
				if ( isset( $b['sua'] ) ) { $them = ' Sửa ' . (int) $b['sua'] . ' máy lệch, thêm '
					. (int) $b['them'] . ' máy còn thiếu.'; }
				echo '<div class="notice notice-success"><p>Xong.' . esc_html( $them ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( $b['error'] ) . '</p></div>';
			}
		}

		/* ---- ĐỐI CHIẾU: chỗ quan trọng nhất, để trên cùng ---- */
		echo '<h2>Đối chiếu máy → cơ sở</h2>';
		$d = VHCC_May::doi_chieu();
		if ( empty( $d['ok'] ) ) {
			echo '<div class="notice notice-warning"><p>Chưa đối chiếu được: ' . esc_html( $d['error'] )
				. '</p></div>';
		} else {
			echo '<p>Sheet <code>MayChamCong</code> có ' . (int) $d['soSheet'] . ' máy · bảng MySQL có '
				. (int) $d['soMysql'] . ' máy.</p>';
			if ( $d['lech'] ) {
				echo '<div class="notice notice-error"><p><strong>' . count( $d['lech'] )
					. ' máy đang LỆCH cơ sở giữa hai nơi.</strong> Trong lúc ghi song song, MỘT lượt bấm '
					. 'đi qua cả hai đường — lệch nghĩa là cùng một lần bấm rơi vào HAI cơ sở khác nhau, '
					. 'và không có gì tự báo. Bấm "Soi lại" để MySQL theo sheet.</p><ul>';
				foreach ( $d['lech'] as $x ) {
					echo '<li><code>' . esc_html( $x['serial'] ? $x['serial'] : $x['mac'] ) . '</code>: '
						. 'sheet ghi <strong>' . esc_html( $x['sheet'] ) . '</strong> · MySQL ghi <strong>'
						. esc_html( $x['mysql'] ) . '</strong></li>';
				}
				echo '</ul></div>';
			} else {
				echo '<p style="color:#046b2d">✔️ Không có máy nào lệch cơ sở.</p>';
			}
			if ( $d['thieu'] ) {
				echo '<p><strong>' . count( $d['thieu'] ) . ' máy có trong sheet mà chưa có trong MySQL.'
					. '</strong> Vô hại — cổng nhận giữ lượt bấm vào bảng "chờ gán" cho tới khi soi lại.</p>';
			}
			if ( $d['du'] ) {
				echo '<p><strong>' . count( $d['du'] ) . ' máy có trong MySQL mà không có trong sheet.'
					. '</strong> Có thể là máy vừa gửi lượt đầu mà sheet chưa kịp có dòng — hệ thống '
					. 'KHÔNG tự xoá, vì xoá là mất chỗ gán.</p>';
			}
			echo '<form method="post"><input type="hidden" name="vhcc_may" value="soi" />';
			wp_nonce_field( 'vhcc_may' );
			echo '<p><button class="button button-primary">Soi lại (sheet → MySQL)</button> '
				. '<em>Chỉ đi một chiều. Sheet là nguồn thật, vì đó là chỗ <code>doPost</code> đang '
				. 'đọc để ghi chấm công.</em></p></form>';
		}

		/* ---- Danh sách máy ---- */
		echo '<h2>Danh sách máy</h2>';
		$m = VHCC_May::ds_may();
		if ( empty( $m['ok'] ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $m['error'] ) . '</p></div>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Hàng</th><th>Serial đầu đọc</th>'
				. '<th>MAC bo</th><th>Cơ sở</th><th>Tên máy tự khai</th><th>Lần cuối thấy</th>'
				. '<th>Ghi chú</th><th>Gán cơ sở</th></tr></thead><tbody>';
			foreach ( (array) $m['data'] as $i => $x ) {
				$hang = isset( $x['row'] ) ? (int) $x['row'] : ( $i + 2 );
				echo '<tr><td>' . $hang . '</td>'
					. '<td><code>' . esc_html( isset( $x['serial'] ) ? $x['serial'] : '' ) . '</code></td>'
					. '<td><code>' . esc_html( isset( $x['mac'] ) ? $x['mac'] : '' ) . '</code></td>'
					. '<td>' . esc_html( isset( $x['cuaHang'] ) ? $x['cuaHang'] : '' ) . '</td>'
					. '<td>' . esc_html( isset( $x['tuKhai'] ) ? $x['tuKhai'] : '' ) . '</td>'
					. '<td>' . esc_html( isset( $x['lanCuoi'] ) ? $x['lanCuoi'] : '' ) . '</td>'
					. '<td>' . esc_html( isset( $x['ghiChu'] ) ? $x['ghiChu'] : '' ) . '</td>'
					. '<td><form method="post" style="display:flex;gap:4px">'
					. '<input type="hidden" name="vhcc_may" value="gan" />'
					. '<input type="hidden" name="hang" value="' . $hang . '" />';
				echo wp_nonce_field( 'vhcc_may', '_wpnonce', true, false );
				echo '<select name="coso"><option value="">— chọn —</option>';
				foreach ( VHCC_NhanSu::ds_coso() as $cs ) {
					echo '<option value="' . esc_attr( $cs ) . '">' . esc_html( $cs ) . '</option>';
				}
				echo '</select><button class="button">Gán</button></form></td></tr>';
			}
			echo '</tbody></table>';
			echo '<p><em>Cơ sở lấy theo <strong>mã thiết bị</strong>, không tin tên máy tự khai. Đổi '
				. 'phần cứng thì hệ thống chỉ GHI DẤU vào cột Ghi chú, không tự sửa — "thay bo" và '
				. '"mang bo sang cửa hàng khác" nhìn từ máy chủ giống hệt nhau.</em></p>';
		}

		/* ---- Lượt bấm chờ gán ---- */
		global $wpdb;
		$cg = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'cho_gan' )
			. " WHERE da_chuyen='' ORDER BY nhan_luc DESC LIMIT 200" );
		echo '<h2>Lượt bấm chờ gán (' . count( $cg ) . ')</h2>';
		echo '<p>Máy chưa gán cơ sở vẫn được nhận và GIỮ lượt bấm ở đây — bỏ là mất công của người '
			. 'thật chỉ vì cái máy chưa được khai. Gán cơ sở cho máy rồi soi lại là xong.</p>';
		if ( $cg ) {
			echo '<table class="widefat striped"><thead><tr><th>Nhận lúc</th><th>Serial</th><th>MAC</th>'
				. '<th>Máy tự khai</th><th>Mã NV</th><th>Họ tên</th><th>Thời điểm</th></tr></thead><tbody>';
			foreach ( $cg as $r ) {
				echo '<tr><td>' . esc_html( $r['nhan_luc'] ) . '</td><td><code>'
					. esc_html( $r['serial'] ) . '</code></td><td><code>' . esc_html( $r['mac'] )
					. '</code></td><td>' . esc_html( $r['ten_tu_khai'] ) . '</td><td><code>'
					. esc_html( $r['ma_nv'] ) . '</code></td><td>' . esc_html( $r['ho_ten'] )
					. '</td><td>' . esc_html( $r['thoi_diem'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ---- Firmware ---- */
		echo '<h2>Cập nhật firmware</h2>';
		$ota = VHCC_May::ota_dang_dat();
		if ( ! empty( $ota['ok'] ) && is_array( $ota['data'] ) ) {
			$o = $ota['data'];
			$ver = isset( $o['ver'] ) ? (string) $o['ver'] : '';
			echo '<p>Lệnh đang đặt: ' . ( '' !== $ver
				? '<strong>' . esc_html( $ver ) . '</strong> · <code>'
					. esc_html( isset( $o['url'] ) ? $o['url'] : '' ) . '</code>'
				: '<em>không có</em>' ) . '</p>';
		}
		$fw = VHCC_May::fw_moi_nhat();
		if ( ! empty( $fw['ok'] ) && is_array( $fw['data'] ) ) {
			$f = $fw['data'];
			if ( ! empty( $f['ver'] ) ) {
				echo '<p>Bản mới nhất trên GitHub: <strong>' . esc_html( $f['ver'] ) . '</strong> · <code>'
					. esc_html( isset( $f['url'] ) ? $f['url'] : '' ) . '</code></p>';
			}
			if ( ! empty( $f['error'] ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html( $f['error'] ) . '</p></div>';
			}
			if ( ! empty( $f['chuaDu'] ) ) {
				echo '<p><strong>Máy chưa đủ điều kiện nhận bản mới:</strong> '
					. esc_html( implode( ', ', (array) $f['chuaDu'] ) ) . '</p>';
			}
		}
		echo '<div class="notice notice-error"><p><strong>Đọc trước khi đẩy.</strong> Lệnh này nạp '
			. 'firmware cho <strong>MỌI máy trong chuỗi</strong> trong vòng 5 phút. Link phải là link '
			. '<code>raw</code> của nhánh <code>bin</code> — link <em>release</em> của GitHub trả HTTP 302 '
			. 'rồi chuyển hướng dài ~943 ký tự, mà module 4G chết ở khoảng 532 ký tự: đẩy link đó là '
			. 'mọi máy 4G KHÔNG BAO GIỜ tải được, tức mất luôn đường sửa từ xa và phải đi từng cửa hàng '
			. 'cắm USB. Hệ thống sẽ chặn link sai dạng, nhưng đọc kỹ vẫn hơn.</p></div>';
		echo '<form method="post"><input type="hidden" name="vhcc_may" value="ota" />';
		wp_nonce_field( 'vhcc_may' );
		echo '<table class="form-table">'
			. self::o( 'ver', 'Phiên bản *', '' )
			. self::o( 'url', 'Link .bin (raw, nhánh bin) *', '' )
			. self::o( 'xac_nhan', 'Gõ đúng chữ DONG Y để xác nhận *', '' )
			. '</table>';
		echo '<p><button class="button button-primary">Đẩy cập nhật cho cả chuỗi</button></p></form>';
		echo '<form method="post"><input type="hidden" name="vhcc_may" value="go_ota" />';
		wp_nonce_field( 'vhcc_may' );
		echo '<p><button class="button">Gỡ lệnh cập nhật</button></p></form>';
		echo '</div>';
	}

	/**
	 * IN BẢNG CHẤM CÔNG. Có tham số `xuat=1` thì TRẢ NGUYÊN tờ giấy rồi dừng — không có khung
	 * quản trị của WordPress bao quanh, vì khung đó in ra giấy là rác.
	 */
	public static function trang_in() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		global $wpdb;
		$coso = isset( $_GET['coso'] ) ? sanitize_text_field( wp_unslash( $_GET['coso'] ) ) : '';
		$tu   = isset( $_GET['tu'] ) ? sanitize_text_field( wp_unslash( $_GET['tu'] ) ) : gmdate( 'Y-m-01' );
		$den  = isset( $_GET['den'] ) ? sanitize_text_field( wp_unslash( $_GET['den'] ) ) : gmdate( 'Y-m-d' );

		$hop_le = function ( $d ) { return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ); };
		if ( isset( $_GET['xuat'] ) && '' !== $coso && $hop_le( $tu ) && $hop_le( $den ) ) {
			$u = wp_get_current_user();
			echo VHCC_Pdf::trang_in( $coso, $tu, $den, $u ? $u->display_name : '' );
			exit;
		}

		$ds = $wpdb->get_col( 'SELECT DISTINCT coso FROM ' . VHCC_DB::t( 'cham_cong' ) . ' ORDER BY coso' );
		echo '<div class="wrap"><h1>In bảng chấm công</h1>';
		echo '<p>Chọn cơ sở và khoảng ngày, bấm <b>Mở tờ in</b> rồi Ctrl+P → <b>Lưu thành PDF</b>. '
			. 'WordPress không có bộ chuyển HTML→PDF như Apps Script, nên tờ giấy in ra từ trình duyệt — '
			. 'đúng khổ A4, đúng khuôn, mà không phụ thuộc thư viện nào có thể hỏng.</p>';
		echo '<form method="get" target="_blank"><input type="hidden" name="page" value="vhcc-in" />'
			. '<input type="hidden" name="xuat" value="1" />';
		echo '<table class="form-table"><tr><th>Cơ sở</th><td><select name="coso" required>'
			. '<option value="">— chọn —</option>';
		foreach ( (array) $ds as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . ( $x === $coso ? ' selected' : '' ) . '>'
				. esc_html( $x ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>Từ ngày</th><td><input type="date" name="tu" value="' . esc_attr( $tu ) . '" required /></td></tr>';
		echo '<tr><th>Đến ngày</th><td><input type="date" name="den" value="' . esc_attr( $den ) . '" required /></td></tr>';
		echo '</table><p><button class="button button-primary">Mở tờ in</button></p></form>';
		echo '<p><em>Số trên tờ giấy do MÁY CHỦ tính từ cơ sở dữ liệu. Bản Apps Script cũ nhận số '
			. 'do trình duyệt tính rồi đẩy lên — tức ai sửa được yêu cầu là sửa được tờ giấy chấm công.</em></p>';
		echo '</div>';
	}

	/**
	 * BẢNG CÔNG & LƯƠNG — đọc từ MySQL.
	 *
	 * ⚠️ Màn này chỉ ĐỌC. Không có nút nào ghi vào bảng chấm công: xem lương không được phép đổi
	 *    chấm công, kể cả một ô.
	 *
	 * ⚠️ Ba thứ PHẢI hiện ra mặt, không được để lẫn trong bảng số:
	 *      · cơ sở CHƯA CÓ CÔNG THỨC  -> nói thẳng là chưa có, đừng để người ta tưởng số 0 là
	 *        "tháng này không ai làm";
	 *      · CHƯA KHAI số ngày công    -> tiền hiện "—", vì 0 đồng và "chưa tính được" là hai
	 *        chuyện khác nhau;
	 *      · ngày có DẤU CẦN SOI (ca lạ, ca đêm thiếu giờ, ca đêm thiếu cặp giờ) -> đếm ra cột
	 *        riêng. Engine cố ý GIỮ mấy ngày đó lại thay vì lặng lẽ bỏ, nên màn hình phải hiện,
	 *        không thì việc giữ lại thành vô nghĩa.
	 */
	public static function trang_luong() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		global $wpdb;
		$coso  = isset( $_GET['coso'] ) ? sanitize_text_field( wp_unslash( $_GET['coso'] ) ) : '';
		$thang = isset( $_GET['thang'] ) ? sanitize_text_field( wp_unslash( $_GET['thang'] ) ) : gmdate( 'Y-m' );

		$ds = $wpdb->get_col( 'SELECT DISTINCT coso FROM ' . VHCC_DB::t( 'cham_cong' ) . ' ORDER BY coso' );
		echo '<div class="wrap"><h1>Bảng công &amp; Lương</h1>';
		echo '<form method="get"><input type="hidden" name="page" value="vhcc-luong" />';
		echo '<select name="coso"><option value="">— chọn cơ sở —</option>';
		foreach ( (array) $ds as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . ( $x === $coso ? ' selected' : '' ) . '>'
				. esc_html( $x ) . ' (' . esc_html( VHCC_Luong::bo_phan_cua( $x ) ) . ')</option>';
		}
		echo '</select> <input type="text" name="thang" value="' . esc_attr( $thang )
			. '" placeholder="yyyy-MM" /> <button class="button button-primary">Xem</button></form>';

		if ( '' === $coso ) { echo '<p><em>Chọn cơ sở rồi bấm Xem.</em></p></div>'; return; }

		$r = VHCC_Luong::bang_cong_va_luong( $coso, $thang );
		if ( empty( $r['ok'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $r['error'] ) . '</p></div></div>';
			return;
		}
		echo '<p>Bộ phận: <strong>' . esc_html( $r['boPhan'] ) . '</strong> · cách tính: <code>'
			. esc_html( $r['kieu'] ) . '</code></p>';

		if ( 'tho' === $r['kieu'] ) {
			echo '<div class="notice notice-warning"><p><strong>Cơ sở này CHƯA có công thức lương.</strong> '
				. 'Bảng dưới chỉ là giờ vào / giờ ra thô. Hệ thống cố ý KHÔNG suy ra một cách tính nào — '
				. 'bịa công thức là đưa ra một con số tiền mà không ai biết từ đâu.</p></div>';
			echo '<table class="widefat striped"><thead><tr><th>Mã</th><th>Tên</th><th>Ngày</th>'
				. '<th>Vào</th><th>Ra</th></tr></thead><tbody>';
			foreach ( $r['tho']['rows'] as $e ) {
				foreach ( $e['ngay'] as $i => $d ) {
					echo '<tr><td>' . ( 0 === $i ? esc_html( $e['ma'] ) : '' ) . '</td><td>'
						. ( 0 === $i ? esc_html( $e['ten'] ) : '' ) . '</td><td>' . esc_html( $d['date'] )
						. '</td><td>' . esc_html( $d['vao'] ) . '</td><td>' . esc_html( $d['ra'] ) . '</td></tr>';
				}
			}
			echo '</tbody></table></div>';
			return;
		}

		if ( 'mtd' === $r['kieu'] ) {
			$m = $r['mtd'];
			if ( $m['chuaKhaiGia'] ) {
				echo '<div class="notice notice-error"><p><strong>Chưa khai đơn giá</strong> '
					. '(<code>MTD_DON_GIA</code>) — mọi ô tiền dưới đây là 0 vì thiếu đơn giá, '
					. 'KHÔNG phải vì không ai làm.</p></div>';
			}
			if ( ! empty( $m['theoGioCaCoSo'] ) ) {
				echo '<p><em>Cơ sở này được tích "tính theo giờ" — mọi dòng tính theo tiếng.</em></p>';
			}
			echo '<table class="widefat striped"><thead><tr><th>Mã</th><th>Tên</th>'
				. '<th>Công thường</th><th>Công cuối tuần</th><th>Công lễ</th>'
				. '<th>Giờ thường</th><th>Giờ cuối tuần</th><th>Giờ lễ</th>'
				. '<th>Tiền công</th><th>Tiền giờ</th><th>Tổng</th></tr></thead><tbody>';
			foreach ( $m['rows'] as $e ) {
				echo '<tr><td>' . esc_html( $e['ma'] ) . '</td><td>' . esc_html( $e['ten'] ) . '</td>'
					. '<td>' . esc_html( $e['cong']['thuong'] ) . '</td>'
					. '<td>' . esc_html( $e['cong']['cuoiTuan'] ) . '</td>'
					. '<td>' . esc_html( $e['cong']['le'] ) . '</td>'
					. '<td>' . esc_html( $e['gio']['thuong'] ) . '</td>'
					. '<td>' . esc_html( $e['gio']['cuoiTuan'] ) . '</td>'
					. '<td>' . esc_html( $e['gio']['le'] ) . '</td>'
					. '<td>' . esc_html( number_format( $e['tienCong'] ) ) . '</td>'
					. '<td>' . esc_html( number_format( $e['tienGio'] ) ) . '</td>'
					. '<td><strong>' . esc_html( number_format( $e['tong'] ) ) . '</strong></td></tr>';
			}
			echo '</tbody><tfoot><tr><th colspan="8">Tổng</th>'
				. '<th>' . esc_html( number_format( $m['tong']['tienCong'] ) ) . '</th>'
				. '<th>' . esc_html( number_format( $m['tong']['tienGio'] ) ) . '</th>'
				. '<th>' . esc_html( number_format( $m['tong']['tong'] ) ) . '</th></tr></tfoot></table>';
			echo '</div>';
			return;
		}

		$v = $r['vp'];
		if ( $v['tien']['chuaKhaiNgayCong'] ) {
			echo '<div class="notice notice-error"><p><strong>Chưa khai số ngày công của '
				. esc_html( $v['ncThang'] ) . '</strong> — cột Tiền hiện “—”. Hệ thống KHÔNG đoán '
				. 'mẫu số: đoán là sai tiền của mọi người cùng lúc, mà bảng vẫn có số nên chẳng ai nghi.'
				. ( $v['ncGoiY'] ? ' Tháng gần nhất đã khai tại cơ sở này: <strong>'
					. esc_html( $v['ncGoiY'] ) . '</strong> (chỉ để tham khảo, chưa dùng để tính).' : '' )
				. '</p></div>';
		}
		if ( $v['chuaKhaiKeToan'] ) {
			echo '<div class="notice notice-warning"><p>Chưa khai mã NV thuộc <strong>Kế toán văn '
				. 'phòng</strong> (<code>ktMaNV</code>) — nên chưa ai được áp khung thứ Bảy 08:30–12:00 '
				. 'và luật Chủ nhật nghỉ.</p></div>';
		}
		if ( ! empty( $v['tien']['thieuLuong'] ) ) {
			echo '<div class="notice notice-warning"><p>Chưa khai lương cơ bản: '
				. esc_html( implode( ', ', $v['tien']['thieuLuong'] ) ) . '</p></div>';
		}
		echo '<table class="widefat striped"><thead><tr><th>Mã</th><th>Tên</th><th>Công ngày</th>'
			. '<th>Tăng ca</th><th>Công đêm</th><th>Công bù</th><th>Tổng công</th>'
			. '<th>Lương tháng</th><th>Đơn giá 1 công</th><th>Tiền</th><th>Cần soi</th>'
			. '</tr></thead><tbody>';
		foreach ( $v['rows'] as $e ) {
			$soi = array();
			if ( $e['soNgayCaLa'] ) { $soi[] = $e['soNgayCaLa'] . ' ngày ca lạ'; }
			if ( $e['soNgayDemThieuGio'] ) { $soi[] = $e['soNgayDemThieuGio'] . ' đêm thiếu giờ'; }
			if ( $e['soNgayDemChuaDuCap'] ) { $soi[] = $e['soNgayDemChuaDuCap'] . ' đêm thiếu cặp giờ'; }
			echo '<tr><td>' . esc_html( $e['ma'] ) . '</td><td>' . esc_html( $e['ten'] )
				. ( $e['laKeToan'] ? ' <em>(kế toán)</em>' : '' ) . '</td>'
				. '<td>' . esc_html( $e['congNgay'] ) . '</td>'
				. '<td>' . esc_html( $e['congTangCa'] ) . '</td>'
				. '<td>' . esc_html( $e['congDem'] ) . '</td>'
				. '<td>' . esc_html( $e['congBu'] ) . '</td>'
				. '<td><strong>' . esc_html( $e['tong'] ) . '</strong></td>'
				. '<td>' . esc_html( $e['luongThang'] ? number_format( $e['luongThang'] ) : '—' ) . '</td>'
				. '<td>' . esc_html( $e['donGiaCong'] ? number_format( $e['donGiaCong'] ) : '—' ) . '</td>'
				. '<td><strong>' . esc_html( $e['tien'] ? number_format( $e['tien'] ) : '—' ) . '</strong></td>'
				. '<td>' . ( $soi ? '<span style="color:#b32d2e">' . esc_html( implode( ' · ', $soi ) )
					. '</span>' : '' ) . '</td></tr>';
		}
		echo '</tbody><tfoot><tr><th colspan="6">Tổng</th><th>' . esc_html( $v['tong']['tong'] )
			. '</th><th colspan="2"></th><th>'
			. esc_html( $v['tien']['tongTien'] ? number_format( $v['tien']['tongTien'] ) : '—' )
			. '</th><th></th></tr></tfoot></table>';

		echo '<h2>Chi tiết từng ngày</h2>';
		echo '<p>Ngày ca đêm được GIỮ lại dù 0 công, để đọc được công của hôm sau từ đâu ra — '
			. 'không soi được là không kiểm được lương.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Ngày</th><th>Mã</th><th>Khung</th>'
			. '<th>Phút ca ngày</th><th>Công ngày</th><th>Tăng ca</th><th>Công đêm</th><th>Bù</th>'
			. '<th>Ghi chú</th></tr></thead><tbody>';
		foreach ( $v['detail'] as $d ) {
			$gc = array();
			if ( $d['kt7'] ) { $gc[] = 'kế toán thứ Bảy'; }
			if ( $d['ktCnNghi'] ) { $gc[] = 'Chủ nhật — lịch nghỉ'; }
			if ( $d['caLa'] ) { $gc[] = 'giờ ca ngày lọt hàng 2, KHÔNG tính'; }
			if ( $d['demSangNgay'] ) { $gc[] = 'ca đêm → công ghi cho ' . $d['demSangNgay']; }
			if ( $d['demTuNgay'] ) { $gc[] = 'công đêm từ ' . $d['demTuNgay']; }
			if ( $d['demThieuGio'] ) { $gc[] = 'đêm ' . $d['gioDemThuc'] . 'h < ngưỡng, KHÔNG được công'; }
			if ( $d['demChuaDuCap'] ) { $gc[] = 'đêm thiếu cặp giờ — vẫn tính, cần soi'; }
			echo '<tr><td>' . esc_html( $d['ngay'] ) . '</td><td>' . esc_html( $d['ma'] ) . '</td>'
				. '<td>' . esc_html( $d['khung'] ) . '</td><td>' . esc_html( $d['phutNgay'] ) . '</td>'
				. '<td>' . esc_html( $d['congNgay'] ) . '</td><td>' . esc_html( $d['congTangCa'] ) . '</td>'
				. '<td>' . esc_html( $d['congDem'] ) . '</td><td>' . esc_html( $d['congBu'] ) . '</td>'
				. '<td>' . esc_html( implode( ' · ', $gc ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Màn hình của cổng nhận chấm công: địa chỉ để nạp vào máy, tình trạng khoá, và NHẬT KÝ.
	 * Nhật ký là phần chính. Mọi ca cổng BỎ một gói (gói thử đường, giờ sai khuôn, máy chưa gán,
	 * thân bị cắt) đều trả SUCCESS cho firmware — buộc phải vậy, xem class-vhcc-nhan.php. Nghĩa là
	 * firmware KHÔNG BAO GIỜ báo cho ai biết là đã bỏ. Chỗ duy nhất đọc ra được là đây.
	 */
	public static function trang_cong_may() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$duong  = home_url( '/' . VHCC_Nhan::DUONG );
		$co_khoa = defined( 'VHCC_KHOA_MAY' ) && '' !== (string) VHCC_KHOA_MAY;
		$nk     = get_option( 'vhcc_nhat_ky_may', array() );
		if ( ! is_array( $nk ) ) { $nk = array(); }
		echo '<div class="wrap"><h1>Cổng nhận chấm công từ máy</h1>';

		echo '<h2>Địa chỉ để nạp vào máy</h2><p><code>' . esc_html( $duong ) . '</code></p>';
		echo '<p><em>Đúng địa chỉ này, không thêm dấu gạch chéo cuối.</em> Firmware không đi theo '
			. 'chuyển hướng — gặp chuyển hướng nó gọi lại bằng GET và mất trọn lượt bấm.</p>';

		if ( $co_khoa ) {
			echo '<p style="color:#046b2d">✔️ Đã cấu hình khoá <code>VHCC_KHOA_MAY</code>.</p>';
		} else {
			echo '<div class="notice notice-error"><p><strong>Chưa cấu hình khoá — cổng đang ĐÓNG, '
				. 'mọi lượt bấm bị chối.</strong> Thêm vào <code>wp-config.php</code>:</p>'
				. '<p><code>define( \'VHCC_KHOA_MAY\', \'…chuỗi ngẫu nhiên dài…\' );</code></p>'
				. '<p>Đặt trong <code>wp-config.php</code> chứ không trong cơ sở dữ liệu: bảng cài đặt '
				. 'thì app đọc được, mà app thì có màn hình.</p></div>';
		}

		echo '<h2>Nhật ký (' . count( $nk ) . ' dòng gần nhất)</h2>';
		echo '<p>Cổng trả SUCCESS cho cả những gói nó BỎ — buộc phải vậy, không thì firmware đẩy lại '
			. 'vô hạn. Nên đây là chỗ duy nhất thấy được cái gì đã bị bỏ và vì sao.</p>';
		if ( ! $nk ) {
			echo '<p><em>Chưa có gì. Cổng chưa nhận lượt nào, hoặc mọi lượt đều vào sổ trót lọt.</em></p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Lúc</th><th>Mã</th><th>Chi tiết</th>'
				. '</tr></thead><tbody>';
			foreach ( $nk as $d ) {
				echo '<tr><td>' . esc_html( $d['luc'] ) . '</td><td><code>' . esc_html( $d['ma'] )
					. '</code></td><td>' . esc_html( $d['loi'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	public static function handle_post() {
		if ( ! isset( $_POST['vhcc_action'] ) ) { return; }
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$action = sanitize_text_field( wp_unslash( $_POST['vhcc_action'] ) );
		check_admin_referer( 'vhcc_' . $action );

		if ( $action === 'luu' ) {
			$slug = isset( $_POST['vhcc_slug'] ) ? sanitize_title( wp_unslash( $_POST['vhcc_slug'] ) ) : 'cham-cong';
			if ( $slug === '' ) { $slug = 'cham-cong'; }
			$cu = get_option( 'vhcc_slug' );
			update_option( 'vhcc_slug', $slug );
			if ( $cu !== $slug ) { update_option( 'vhcc_flush_rewrite', 1 ); }

			$url = isset( $_POST['vhcc_exec_url'] ) ? esc_url_raw( wp_unslash( $_POST['vhcc_exec_url'] ) ) : '';
			/* Chuẩn hoá TRƯỚC khi lưu, và giữ lại lời giải thích — sửa ngầm thì lần sau anh Thắng
			   dán lại đúng cái địa chỉ sai đó và không hiểu vì sao lần này lại chạy. */
			$ch = VHCC_CauNoi::chuan_hoa_url( $url );
			update_option( 'vhcc_exec_url', $ch['url'] );
			set_transient( 'vhcc_sua_url_' . get_current_user_id(), $ch['sua'], 120 );

			$nguon = ( isset( $_POST['vhcc_nguon'] ) && $_POST['vhcc_nguon'] === 'rieng' ) ? 'rieng' : 'chung';
			update_option( 'vhcc_nguon_nguoidung', $nguon );

			$vt = array();
			foreach ( (array) ( isset( $_POST['vhcc_vai_tro'] ) ? $_POST['vhcc_vai_tro'] : array() ) as $x ) {
				$x = sanitize_text_field( wp_unslash( $x ) );
				if ( in_array( $x, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) { $vt[] = $x; }
			}
			update_option( 'vhcc_vai_tro_vao', $vt );   // rỗng -> vai_tro_vao() tự về mặc định

			VHCC_CauNoi::bao_dam_khoa();
			VHCC_CauNoi::xoa_cache_giao_dien();   // đổi cấu hình thì lấy lại giao diện, khỏi dùng bản nhớ tạm
			self::ve( 'saved' );
		}

		if ( $action === 'khoamoi' ) {
			update_option( 'vhcc_web_key', bin2hex( random_bytes( 24 ) ) );
			self::ve( 'khoamoi' );
		}

		if ( $action === 'lammoi' ) {
			VHCC_CauNoi::xoa_cache_giao_dien();
			self::ve( 'lammoi' );
		}

		if ( $action === 'thu' ) {
			set_transient( 'vhcc_thu_' . get_current_user_id(), VHCC_CauNoi::thu(), 120 );
			self::ve( 'thu' );
		}

		if ( $action === 'mokhoa' ) {
			VHCC_Auth::mo_khoa();
			self::ve( 'mokhoa' );
		}
	}

	private static function ve( $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'vhcc', 'vhcc_msg' => $msg ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function page() {
		/* WordPress đã chặn theo `manage_options` khai ở add_menu_page, nhưng 10 màn kia đều
		   chốt lại một lần nữa ngay trong hàm vẽ — màn này thiếu. Một hàm public vẽ ra khoá
		   WEB_KEY thì không được dựa vào chỗ khác chặn hộ. */
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$slug  = get_option( 'vhcc_slug', 'cham-cong' );
		$exec  = VHCC_CauNoi::url();
		$khoa  = VHCC_CauNoi::bao_dam_khoa();
		$nguon = VHCC_Auth::nguon();
		$msg   = isset( $_GET['vhcc_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['vhcc_msg'] ) ) : '';

		echo '<div class="wrap"><h1>Chấm Công</h1>';

		$loi_nhan = array(
			'saved'   => 'Đã lưu.',
			'khoamoi' => 'Đã sinh khoá mới — <b>nhớ cập nhật WEB_KEY trong Script Properties của Apps Script rồi Deploy lại</b>, không thì cầu nối đứt.',
			'lammoi'  => 'Đã xoá nhớ tạm giao diện — lần mở trang tới sẽ lấy bản mới nhất từ Apps Script.',
			'mokhoa'  => 'Đã mở khoá đăng nhập.',
		);
		if ( isset( $loi_nhan[ $msg ] ) ) {
			echo '<div class="notice notice-success"><p>' . wp_kses_post( $loi_nhan[ $msg ] ) . '</p></div>';
		}

		$sua_url = get_transient( 'vhcc_sua_url_' . get_current_user_id() );
		if ( is_array( $sua_url ) && $sua_url ) {
			delete_transient( 'vhcc_sua_url_' . get_current_user_id() );
			echo '<div class="notice notice-warning"><p><b>Địa chỉ /exec đã được sửa lại:</b></p><ul style="margin-left:18px;list-style:disc">';
			foreach ( $sua_url as $x ) { echo '<li>' . wp_kses_post( $x ) . '</li>'; }
			echo '</ul></div>';
		}

		if ( $msg === 'thu' ) {
			$r = get_transient( 'vhcc_thu_' . get_current_user_id() );
			/* Xoá ngay sau khi đọc: tải lại trang thì kết quả cũ hiện lại y nguyên, không có mốc
			   giờ, nên trông như lỗi VẪN CÒN. Đã mất một vòng đúng vì chuyện này — địa chỉ đã tự
			   chữa xong rồi mà hộp đỏ cũ vẫn nằm đó. Kết quả một lần thử thì chỉ đúng cho lần đó. */
			delete_transient( 'vhcc_thu_' . get_current_user_id() );
			if ( is_array( $r ) && ! empty( $r['ok'] ) ) {
				$d = (array) $r['data'];
				echo '<div class="notice notice-success"><p><b>Cầu nối sống.</b> App cho gọi '
					. (int) ( isset( $d['soHam'] ) ? $d['soHam'] : 0 ) . ' hàm · file giao diện: <code>'
					. esc_html( isset( $d['giaoDien'] ) ? $d['giaoDien'] : '?' ) . '</code></p></div>';
			} elseif ( is_array( $r ) ) {
				echo '<div class="notice notice-error"><p><b>Chưa nối được:</b> '
					. esc_html( isset( $r['error'] ) ? $r['error'] : '?' ) . '</p></div>';
			}
		}

		/* Bản đang chạy — trong lúc cài, câu "anh cài bản mới chưa" phải trả lời được bằng mắt.
		   Số này cũng hiện ở Plugins của WordPress, nhưng ở đây là chỗ người ta đang đứng. */
		echo '<p style="color:#64748b">Bản plugin đang chạy: <code>' . esc_html( VHCC_VERSION ) . '</code></p>';
		echo '<h2>Mở hệ thống chấm công</h2><p><a class="button button-primary" target="_blank" href="'
			. esc_url( VHCC_Trang::url() ) . '">' . esc_html( VHCC_Trang::url() ) . '</a></p>';

		echo '<div class="notice notice-info"><p><b>Plugin này không giữ dữ liệu chấm công.</b> '
			. 'Hợp đồng vẫn nằm trong Google Sheet và toàn bộ nghiệp vụ vẫn ở app Apps Script. '
			. 'WordPress chỉ lo cổng PIN, giữ khoá bí mật và phục vụ giao diện gốc.</p></div>';

		echo '<form method="post">';
		wp_nonce_field( 'vhcc_luu' );
		echo '<input type="hidden" name="vhcc_action" value="luu">';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="vhcc_slug">Đường dẫn trang</label></th><td>'
			. esc_html( home_url( '/' ) ) . '<input name="vhcc_slug" id="vhcc_slug" value="' . esc_attr( $slug )
			. '" class="regular-text"> /<p class="description">Mặc định <code>cham-cong</code>.</p></td></tr>';

		echo '<tr><th scope="row"><label for="vhcc_exec_url">Địa chỉ /exec của app chấm công</label></th><td>'
			. '<input name="vhcc_exec_url" id="vhcc_exec_url" value="' . esc_attr( $exec ) . '" class="large-text code" '
			. 'placeholder="https://script.google.com/…/exec">'
			. '<p class="description">Lấy ở Apps Script → Deploy → Manage deployments → Web app URL.</p></td></tr>';

		echo '<tr><th scope="row">Khoá cầu nối (WEB_KEY)</th><td><code style="user-select:all">'
			. esc_html( $khoa ) . '</code>'
			. '<p class="description">Dán đúng chuỗi này vào Apps Script → Project Settings → Script Properties, '
			. 'tên thuộc tính <code>WEB_KEY</code>. Đây là thứ duy nhất chặn người lạ ghi vào sheet chấm công — '
			. 'đừng dán vào chỗ nào khác.</p></td></tr>';

		echo '<tr><th scope="row">Nguồn người dùng &amp; PIN</th><td>';
		echo '<label><input type="radio" name="vhcc_nguon" value="chung"' . checked( $nguon, 'chung', false ) . '> '
			. 'Dùng chung với plugin <b>Vận hành chi phí</b> (khuyến nghị)</label><br>';
		echo '<label><input type="radio" name="vhcc_nguon" value="rieng"' . checked( $nguon, 'rieng', false ) . '> '
			. 'Danh sách riêng của plugin này</label>';
		echo '<p class="description">Dùng chung thì thêm/sửa/xoá nhân sự vẫn làm ở tab ⚙️ Cấu hình của app chi phí — '
			. 'khai một lần cho cả hai hệ thống. Khai hai nơi là sớm muộn xoá một nơi quên nơi kia.</p>';
		/* ⚠️ Bản này CHƯA có màn khai danh sách riêng: `vhcc_nguoidung` chỉ được ĐỌC, không chỗ
		   nào ghi. Chọn "riêng" là không ai đăng nhập được trang chấm công, mà màn hình lại
		   không hề nói ra — đúng kiểu tắc im lặng. Nói thẳng ngay tại chỗ chọn. */
		if ( $nguon === 'rieng' ) {
			$ds_rieng = get_option( 'vhcc_nguoidung' );
			$so_rieng = is_array( $ds_rieng ) ? count( $ds_rieng ) : 0;
			if ( ! $so_rieng ) {
				echo '<div class="notice notice-error inline" style="margin:8px 0"><p>'
					. '<b>Danh sách riêng đang RỖNG, và bản này chưa có màn để khai nó.</b> '
					. 'Để nguyên thế thì trang <code>' . esc_html( VHCC_Trang::url() ) . '</code> '
					. 'sẽ không ai đăng nhập được — PIN nào cũng bị chối.</p>'
					. '<p>Cách đang chạy được: cài thêm plugin <b>Vận Hành Chi Phí</b> '
					. '(<code>vhcp-chi-phi.zip</code>), khai nhân sự ở tab ⚙️ Cấu hình bên đó, rồi '
					. 'quay lại chọn <b>Dùng chung</b> ở trên. Nhân sự khai một lần, dùng cho cả hai '
					. 'hệ thống.</p></div>';
			} else {
				echo '<p>Danh sách riêng đang có <b>' . (int) $so_rieng . '</b> người.</p>';
			}
		}
		if ( $nguon === 'chung' ) {
			$u = VHCC_Auth::users();
			if ( is_wp_error( $u ) ) {
				echo '<p style="color:#b32d2e"><b>' . esc_html( $u->get_error_message() ) . '</b></p>';
			} else {
				/* Vai trò vào được là do Cài đặt quyết định, nên phải đọc qua vai_tro_vao() —
				   KHÔNG có hằng VHCC_Auth::VAI_TRO_VAO. Dùng tên hằng không tồn tại là lỗi
				   nghiêm trọng (fatal) và nó giết cả trang từ chỗ này xuống, tức là mất luôn
				   nút Lưu ở dưới. Lấy một lần vào biến để đếm và để in ra cùng một danh sách. */
				$duoc_vao = VHCC_Auth::vai_tro_vao();
				$duoc = 0;
				foreach ( $u as $x ) {
					if ( in_array( $x['vaiTro'], $duoc_vao, true ) ) { $duoc++; }
				}
				echo '<p>Đang đọc được <b>' . count( $u ) . '</b> người, trong đó <b>' . $duoc
					. '</b> người vào được hệ thống chấm công (' . esc_html( implode( ' · ', $duoc_vao ) ) . ').</p>';
			}
		}
		echo '</td></tr>';

		echo '<tr><th scope="row">Vai trò vào được</th><td>';
		$dang_cho = VHCC_Auth::vai_tro_vao();
		foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $vt ) {
			echo '<label style="margin-right:16px"><input type="checkbox" name="vhcc_vai_tro[]" value="'
				. esc_attr( $vt ) . '"' . checked( in_array( $vt, $dang_cho, true ), true, false ) . '> '
				. esc_html( $vt ) . '</label>';
		}
		echo '<p class="description">Dữ liệu chấm công là căn cứ tính lương nên mặc định chỉ '
			. esc_html( implode( ' · ', VHCC_Auth::VAI_TRO_MAC_DINH ) ) . '. Cần cho cửa hàng trưởng xem '
			. 'bảng công cơ sở mình thì tích thêm <b>Nhân viên</b> — nhưng lưu ý app gốc có thể '
			. 'KHÔNG lọc theo người, tức là tích vào là họ xem được bảng công của mọi người. '
			. 'Bỏ tích hết thì quay về mặc định (không bao giờ để rỗng — rỗng là không ai vào được, '
			. 'kể cả Admin).</p></td></tr>';

		echo '</tbody></table>';
		submit_button( 'Lưu cài đặt' );
		echo '</form>';

		echo '<hr><h2>Bảo trì</h2><p>';
		foreach ( array(
			'thu'     => 'Thử cầu nối',
			'lammoi'  => 'Làm mới giao diện',
			'mokhoa'  => 'Mở khoá đăng nhập',
			'khoamoi' => 'Sinh khoá mới',
		) as $act => $nhan ) {
			echo '<form method="post" style="display:inline-block;margin-right:8px">';
			wp_nonce_field( 'vhcc_' . $act );
			echo '<input type="hidden" name="vhcc_action" value="' . esc_attr( $act ) . '">';
			echo '<button class="button">' . esc_html( $nhan ) . '</button></form>';
		}
		echo '</p><p class="description">"Làm mới giao diện" dùng khi vừa sửa Index.html bên Apps Script mà trang web '
			. 'còn hiện bản cũ (plugin nhớ tạm giao diện 10 phút).</p>';

		echo '<hr><h2>File cần dán sang Apps Script</h2>';
		/* ⚠️ Tên file phải khớp với mọi chỗ khác nhắc tới nó — phần đầu chính file cau-noi.gs và
		   các câu báo lỗi đều nói `CauNoiChamCong.gs`. Trước đây chỗ này thiếu phần `ChamCong`
		   nên cùng một file mà ba chỗ ba tên; anh Thắng đọc xong không biết đặt tên nào. Mục 34
		   của bộ thử canh đúng việc này — và nó soát cả chú thích, nên ở đây không viết lại cái
		   tên cũ thiếu chữ, kể cả để giải thích. */
		echo '<p>Mở project Apps Script của app chấm công → File → New → Script file, đặt tên <code>CauNoiChamCong</code>, '
			. 'dán toàn bộ nội dung file <code>apps-script/cau-noi.gs</code> trong plugin này. '
			. 'Hướng dẫn từng bước nằm ngay đầu file đó.</p>';
		$f = VHCC_DIR . 'apps-script/cau-noi.gs';
		if ( is_readable( $f ) ) {
			echo '<textarea readonly rows="14" style="width:100%;font-family:Consolas,monospace;font-size:12px">'
				. esc_textarea( file_get_contents( $f ) ) . '</textarea>';
		}
		echo '</div>';
	}
}
