<?php
/**
 * MÀN HÌNH BỔ SUNG: Phân quyền & PIN · Bảng chấm công & cờ · Yêu cầu NV · Cấu hình lương.
 *
 * Tách khỏi class-vhcc-admin.php vì tệp đó đã ~1000 dòng.
 *
 * ⚠️ MỌI màn hình ở đây đều MỎNG: gác quyền, đọc biểu mẫu, gọi lớp nghiệp vụ, hiện kết quả. Không
 *    một dòng luật quyền nào, không một câu ghi bảng nào. Hai bản luật quyền là sớm muộn lệch
 *    nhau, và lúc lệch thì màn cho bấm mà lớp dưới chặn — hoặc tệ hơn, ngược lại.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Man {

	public static function menu_them( $goc, $cap ) {
		add_submenu_page( $goc, 'Phân quyền & PIN', 'Phân quyền & PIN', $cap,
			'vhcc-quyen', array( __CLASS__, 'trang_quyen' ) );
		add_submenu_page( $goc, 'Bảng chấm công', 'Bảng chấm công', $cap,
			'vhcc-cham', array( __CLASS__, 'trang_cham' ) );
		/* Số yêu cầu chờ duyệt hiện NGAY TRÊN MENU. Đây đúng là việc của `demYeuCauNVCho` bên bản
		   gốc: yêu cầu nằm im trong một tab không ai mở thì người gửi chờ mãi mà không ai biết. */
		$cho = 0;
		try { $cho = VHCC_YeuCau::dem_cho( self::toi_an() ); } catch ( Exception $e ) { $cho = 0; }
		add_submenu_page( $goc, 'Yêu cầu nhân viên',
			'Yêu cầu nhân viên' . ( $cho > 0
				? ' <span class="awaiting-mod"><span class="pending-count">' . (int) $cho . '</span></span>'
				: '' ),
			$cap, 'vhcc-yeu-cau', array( __CLASS__, 'trang_yeu_cau' ) );
		add_submenu_page( $goc, 'Cấu hình lương', 'Cấu hình lương', $cap,
			'vhcc-cf-luong', array( __CLASS__, 'trang_cf_luong' ) );
	}

	/**
	 * Người đang xem, dùng lúc DỰNG MENU — chạy rất sớm nên không dựa vào VHCC_Admin::toi().
	 * Chỉ để đếm số yêu cầu chờ; sai cũng chỉ sai con số trên nhãn menu.
	 */
	private static function toi_an() {
		return array( 'name' => 'menu', 'role' => 'ADMIN', 'coso' => '' );
	}

	/** Hiện kết quả của các lượt ghi. Kết quả nào cũng phải hiện — im lặng là không biết đã xong chưa. */
	private static function bao( $ds ) {
		foreach ( (array) $ds as $b ) {
			if ( ! is_array( $b ) ) { continue; }
			if ( ! empty( $b['ok'] ) ) {
				$them = array();
				foreach ( array( 'so' => 'dòng', 'sua' => 'sửa', 'them' => 'thêm' ) as $k => $nhan ) {
					if ( isset( $b[ $k ] ) ) { $them[] = $nhan . ' ' . (int) $b[ $k ]; }
				}
				foreach ( array( 'xong', 'cap', 'duoc' ) as $k ) {
					if ( isset( $b[ $k ] ) && is_array( $b[ $k ] ) ) { $them[] = 'xong ' . count( $b[ $k ] ); }
				}
				echo '<div class="notice notice-success"><p>Xong'
					. ( $them ? ' — ' . esc_html( implode( ' · ', $them ) ) : '' ) . '.</p>';
				/* Danh sách bị BỎ phải hiện KÈM lý do từng cái. "Xong 8/10" mà không nói 2 cái nào
				   bị bỏ và vì sao là con số vô dụng. */
				foreach ( array( 'bo', 'boQua' ) as $k ) {
					if ( ! empty( $b[ $k ] ) && is_array( $b[ $k ] ) ) {
						echo '<p><strong>Bỏ ' . count( $b[ $k ] ) . ':</strong></p><ul>';
						foreach ( $b[ $k ] as $x ) { echo '<li>' . esc_html( is_scalar( $x ) ? $x : wp_json_encode( $x ) ) . '</li>'; }
						echo '</ul>';
					}
				}
				if ( ! empty( $b['canhBao'] ) ) {
					echo '<p><strong>⚠️ ' . esc_html( $b['canhBao'] ) . '</strong></p>';
				}
				echo '</div>';
			} else {
				echo '<div class="notice notice-error"><p>'
					. esc_html( isset( $b['error'] ) ? $b['error'] : 'Lỗi không rõ' ) . '</p></div>';
			}
		}
	}

	private static function chon_coso( $ten, $dang_chon, $rong = '— chọn cơ sở —' ) {
		$h = '<select name="' . esc_attr( $ten ) . '"><option value="">' . esc_html( $rong ) . '</option>';
		foreach ( VHCC_NhanSu::ds_coso() as $x ) {
			$h .= '<option value="' . esc_attr( $x ) . '"' . ( $x === $dang_chon ? ' selected' : '' )
				. '>' . esc_html( $x ) . '</option>';
		}
		return $h . '</select>';
	}

	// ============================================================ Phân quyền & PIN

	public static function trang_quyen() {
		if ( ! current_user_can( VHCC_Admin::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$u = VHCC_Admin::toi();
		$bao = array();
		$tra = null;
		if ( isset( $_POST['vhcc_q'] ) ) {
			check_admin_referer( 'vhcc_q' );
			$v = sanitize_text_field( wp_unslash( $_POST['vhcc_q'] ) );
			if ( 'luu' === $v ) {
				$bao[] = VHCC_Quyen::luu_phan_quyen( $u, array(
					'pin' => wp_unslash( $_POST['pin'] ),
					'ho_ten' => wp_unslash( $_POST['ho_ten'] ),
					'vai_tro' => wp_unslash( $_POST['vai_tro'] ),
					'cua_hang' => wp_unslash( $_POST['cua_hang'] ),
					'ma_cc_online' => wp_unslash( $_POST['ma_cc_online'] ),
					'coso_cc_online' => wp_unslash( $_POST['coso_cc_online'] ) ) );
			} elseif ( 'xoa' === $v ) {
				$bao[] = VHCC_Quyen::xoa_phan_quyen( $u, wp_unslash( $_POST['pin'] ) );
			} elseif ( 'cap_loat' === $v ) {
				$ds = preg_split( '/[\s,;]+/', (string) wp_unslash( $_POST['ds_ma'] ), -1, PREG_SPLIT_NO_EMPTY );
				$bao[] = VHCC_Quyen::cap_pin_hang_loat( $u, $ds );
			} elseif ( 'gop' === $v ) {
				$bao[] = VHCC_Quyen::gop_tai_khoan( $u, wp_unslash( $_POST['pin_giu'] ), wp_unslash( $_POST['pin_xoa'] ) );
			} elseif ( 'tra' === $v ) {
				$tra = VHCC_Quyen::tra_pin_theo_cccd( wp_unslash( $_POST['cccd'] ) );
			} elseif ( 'anh_mau' === $v ) {
				$bao[] = VHCC_Online::dat_anh_mau_the( $u, wp_unslash( $_POST['data_uri'] ) );
			}
		}

		echo '<div class="wrap"><h1>Phân quyền &amp; PIN</h1>';
		self::bao( $bao );

		/* 🔴 NÓI RÕ ĐÂY LÀ PIN GÌ — vì tên màn hình không nói ra được, và đoán sai thì mất cả
		   buổi. Anh Thắng thêm một dòng PIN ở đây rồi thử đăng nhập trang chấm công, không vào
		   được, tưởng dữ liệu chưa đồng bộ. Thật ra KHÔNG có chỗ nào trong plugin dùng bảng này
		   để đăng nhập trang web: cổng PIN của trang đọc nguồn người dùng khai ở màn Cài đặt. */
		echo '<div class="notice notice-info"><p><b>PIN ở màn này KHÔNG dùng để đăng nhập '
			. '<code>' . esc_html( VHCC_Trang::url() ) . '</code>.</b> Đây là bảng phân quyền của '
			. '<b>app gốc</b> (Apps Script) — dùng cho chấm công online và quyền theo cơ sở bên đó.</p>'
			. '<p>PIN vào trang chấm công lấy từ <b>Nguồn người dùng</b> khai ở '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=vhcc' ) ) . '">Chấm Công → Cài đặt</a> — '
			. 'ở đó có bảng liệt kê ai vào được và PIN dài mấy số.</p></div>';

		/* ---- Cấp PIN hàng loạt ---- */
		echo '<h2>Cấp PIN cho người chưa có tài khoản</h2>';
		echo '<p>Dán danh sách Mã NV (cách nhau dấu phẩy, dấu cách hoặc xuống dòng). Hệ thống sinh '
			. 'PIN 6 số chưa ai dùng và <strong>không bao giờ sinh PIN dễ đoán</strong> — '
			. '111111, 123456, 654321, 888888… đều bị loại.</p>';
		echo '<form method="post"><input type="hidden" name="vhcc_q" value="cap_loat" />';
		wp_nonce_field( 'vhcc_q' );
		echo '<p><textarea name="ds_ma" rows="3" class="large-text" placeholder="NV001, NV002…"></textarea></p>';
		echo '<p><button class="button button-primary">Cấp PIN</button> <em>Người chưa có hồ sơ hoặc '
			. 'đã có tài khoản sẽ bị bỏ, kèm lý do từng cái.</em></p></form>';

		/* ---- Tra PIN theo CCCD ---- */
		echo '<h2>Tra PIN theo CCCD</h2>';
		echo '<p>Dùng khi nhân viên quên mật khẩu. Cửa này <strong>không cần đăng nhập</strong> ở '
			. 'trang chấm công phụ — người quên PIN thì làm sao đăng nhập để tra. Vì vậy nó có ba bộ '
			. 'đếm chặn dò: 5 lượt cho cùng một số CCCD / 10 phút, và 30 lượt <em>trượt</em> toàn hệ '
			. 'thống / 10 phút. Nhật ký ghi CCCD <strong>đã che</strong> và không bao giờ ghi PIN.</p>';
		echo '<form method="post"><input type="hidden" name="vhcc_q" value="tra" />';
		wp_nonce_field( 'vhcc_q' );
		echo '<p><input name="cccd" placeholder="số CCCD" /> <button class="button">Tra</button></p></form>';
		if ( is_array( $tra ) ) {
			if ( ! empty( $tra['ok'] ) ) {
				echo '<div class="notice notice-success"><p>' . esc_html( $tra['ten'] ) . ' · cơ sở '
					. esc_html( $tra['coSo'] ) . ' · PIN <code>' . esc_html( $tra['pin'] ) . '</code></p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( $tra['error'] ) . '</p></div>';
			}
		}
		$nk = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhat_ky_tra_pin' )
			. ' ORDER BY id DESC LIMIT 50' );
		if ( $nk ) {
			echo '<h3>Nhật ký tra PIN (50 lượt gần nhất)</h3>';
			echo '<table class="widefat striped"><thead><tr><th>Lúc</th><th>CCCD (đã che)</th>'
				. '<th>Kết quả</th><th>Mã NV</th><th>Ghi chú</th></tr></thead><tbody>';
			foreach ( $nk as $r ) {
				echo '<tr><td>' . esc_html( $r['luc'] ) . '</td><td><code>' . esc_html( $r['cccd_che'] )
					. '</code></td><td>' . esc_html( $r['ket_qua'] ) . '</td><td>' . esc_html( $r['ma_nv'] )
					. '</td><td>' . esc_html( $r['ghi_chu'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ---- PIN trùng + gộp ---- */
		$trung = VHCC_Quyen::tim_pin_trung( $u );
		echo '<h2>Tài khoản trùng mã NV (' . count( $trung ) . ')</h2>';
		if ( ! $trung ) {
			echo '<p><em>Không có mã NV nào bị cấp hai tài khoản.</em></p>';
		} else {
			echo '<p>Một mã NV có nhiều PIN — dấu hiệu hai người dùng một tài khoản, hoặc cấp trùng.</p>';
			echo '<table class="widefat striped"><thead><tr><th>Mã NV</th><th>Số tài khoản</th>'
				. '<th>Các PIN</th></tr></thead><tbody>';
			foreach ( $trung as $r ) {
				echo '<tr><td><code>' . esc_html( $r['ma_cc_online'] ) . '</code></td><td>'
					. (int) $r['so'] . '</td><td>' . esc_html( $r['cac_pin'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<form method="post"><input type="hidden" name="vhcc_q" value="gop" />';
			wp_nonce_field( 'vhcc_q' );
			echo '<p><input name="pin_giu" placeholder="PIN giữ lại" required /> '
				. '<input name="pin_xoa" placeholder="PIN xoá" required /> '
				. '<button class="button">Gộp</button> <em>Chỉ gộp được khi hai tài khoản trỏ về '
				. '<strong>cùng một</strong> mã NV — khác mã thì gộp là mất một người. Chấm công '
				. 'không bị chạm: nó gắn với MÃ NV, không gắn với PIN.</em></p></form>';
		}

		/* ---- Danh sách phân quyền ---- */
		$ds = VHCC_Quyen::ds_phan_quyen( $u );
		echo '<h2>Phân quyền (' . count( $ds ) . ')</h2>';
		echo '<table class="widefat striped"><thead><tr><th>PIN</th><th>Họ tên</th><th>Vai trò</th>'
			. '<th>Cửa hàng phụ trách</th><th>Mã NV chấm công online</th><th>Cơ sở online</th>'
			. '<th></th></tr></thead><tbody>';
		foreach ( $ds as $r ) {
			echo '<tr><td><code>' . esc_html( $r['pin'] ) . '</code></td><td>' . esc_html( $r['ho_ten'] )
				. '</td><td>' . esc_html( $r['vai_tro'] ) . '</td><td>' . esc_html( $r['cua_hang'] )
				. '</td><td><code>' . esc_html( $r['ma_cc_online'] ) . '</code></td><td>'
				. esc_html( $r['coso_cc_online'] ) . '</td><td>'
				. '<form method="post" onsubmit="return confirm(\'Xoá dòng phân quyền này?\')">'
				. '<input type="hidden" name="vhcc_q" value="xoa" />'
				. '<input type="hidden" name="pin" value="' . esc_attr( $r['pin'] ) . '" />';
			echo wp_nonce_field( 'vhcc_q', '_wpnonce', true, false );
			echo '<button class="button button-link-delete">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><em>Không xoá được dòng của chính bạn, cũng không xoá được ADMIN cuối cùng — xoá là '
			. 'không ai cấp lại quyền được nữa.</em></p>';

		echo '<h3>Thêm / sửa một dòng</h3>';
		echo '<form method="post"><input type="hidden" name="vhcc_q" value="luu" />';
		wp_nonce_field( 'vhcc_q' );
		echo '<table class="form-table">'
			. VHCC_Admin::o( 'pin', 'PIN (6 số) *', '' )
			. VHCC_Admin::o( 'ho_ten', 'Họ tên', '' )
			. VHCC_Admin::o( 'vai_tro', 'Vai trò', '' )
			. '<tr><th></th><td><em>ADMIN · QUAN_LY · CUA_HANG_TRUONG · NHAN_VIEN · KE_TOAN. '
			. 'Ô này KHÔNG bị chặn theo danh sách, đúng như app gốc — nhưng gõ sai chính tả là '
			. 'người đó mất quyền, vì mọi chỗ kiểm quyền so BẰNG tên vai trò.</em></td></tr>'
			. VHCC_Admin::o( 'cua_hang', 'Cửa hàng phụ trách (cách nhau dấu phẩy)', '' )
			. VHCC_Admin::o( 'ma_cc_online', 'Mã NV chấm công online', '' )
			. '<tr><th></th><td><em>Có khai mã này = tài khoản được chấm công online. Đây là cách '
			. 'phân biệt nhân viên cơ sở với nhân viên văn phòng — app gốc cố ý KHÔNG thêm vai trò '
			. 'mới cho việc đó.</em></td></tr>'
			. '<tr><th>Cơ sở chấm công online</th><td>' . self::chon_coso( 'coso_cc_online', '' ) . '</td></tr>'
			. '</table><p><button class="button button-primary">Lưu dòng phân quyền</button></p></form>';

		/* ---- Ảnh mẫu thẻ ---- */
		$am = VHCC_Online::anh_mau_the_info();
		echo '<h2>Ảnh mẫu thẻ 3×4</h2>';
		echo '<p>Hình mẫu để nhân viên biết chụp thế nào cho đúng. '
			. ( ! empty( $am['daKhai'] ) ? 'Đã khai (' . round( (int) $am['soByte'] / 1024 ) . ' KB).'
				: 'Chưa khai — trang chấm công tự dùng hình vẽ sẵn.' )
			. ' Ảnh này tải kèm <strong>mọi lượt mở trang chấm công</strong>, nên giữ dưới 150 KB.</p>';
		echo '<form method="post"><input type="hidden" name="vhcc_q" value="anh_mau" />';
		wp_nonce_field( 'vhcc_q' );
		echo '<p><textarea name="data_uri" rows="3" class="large-text" '
			. 'placeholder="data:image/jpeg;base64,…"></textarea></p>';
		echo '<p><button class="button">Lưu ảnh mẫu</button> <em>Để trống rồi lưu là bỏ ảnh mẫu.</em></p>'
			. '</form></div>';
	}

	// ============================================================ Bảng chấm công & cờ

	public static function trang_cham() {
		if ( ! current_user_can( VHCC_Admin::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$u = VHCC_Admin::toi();
		$bao = array();
		if ( isset( $_POST['vhcc_cc'] ) ) {
			check_admin_referer( 'vhcc_cc' );
			$v = sanitize_text_field( wp_unslash( $_POST['vhcc_cc'] ) );
			if ( 'co' === $v ) {
				$bao[] = VHCC_Cham::luu_ghi_chu( $u, array(
					'coso' => wp_unslash( $_POST['coso'] ), 'ngay' => wp_unslash( $_POST['ngay'] ),
					'ma_nv' => wp_unslash( $_POST['ma_nv'] ), 'ho_ten' => wp_unslash( $_POST['ho_ten'] ),
					'ghi_chu' => wp_unslash( $_POST['ghi_chu'] ) ) );
			} elseif ( 'xu_ly' === $v ) {
				$bao[] = VHCC_Cham::xu_ly_ghi_chu( $u, wp_unslash( $_POST['flag_id'] ),
					wp_unslash( $_POST['ket_luan'] ) );
			} elseif ( 'tc' === $v ) {
				$bao[] = VHCC_Cham::them_tang_cuong( $u, array(
					'coso_den' => wp_unslash( $_POST['coso_den'] ), 'ngay' => wp_unslash( $_POST['ngay'] ),
					'ma_nv' => wp_unslash( $_POST['ma_nv'] ), 'ghi_chu' => wp_unslash( $_POST['ghi_chu'] ) ) );
			} elseif ( 'khoa_tc' === $v ) {
				$bao[] = VHCC_Cham::khoa_tang_cuong( $u, wp_unslash( $_POST['coso'] ),
					wp_unslash( $_POST['thang'] ), ! empty( $_POST['khoa'] ) );
			} elseif ( 'qd' === $v ) {
				$bao[] = VHCC_Cham::luu_quy_doi_coso( $u, wp_unslash( $_POST['tu'] ),
					wp_unslash( $_POST['den'] ), wp_unslash( $_POST['ghi_chu'] ) );
			} elseif ( 'don' === $v ) {
				$bao[] = VHCC_Cham::xoa_thong_ke_day( $u, wp_unslash( $_POST['truoc_ngay'] ) );
			}
		}

		$coso  = isset( $_GET['coso'] ) ? sanitize_text_field( wp_unslash( $_GET['coso'] ) ) : '';
		$thang = isset( $_GET['thang'] ) ? sanitize_text_field( wp_unslash( $_GET['thang'] ) ) : gmdate( 'Y-m' );

		echo '<div class="wrap"><h1>Bảng chấm công</h1>';
		self::bao( $bao );
		echo '<p><em>Màn này <strong>chỉ đọc</strong> giờ chấm công. Không có nút nào sửa giờ — chỉ '
			. 'hai đường được ghi giờ là cổng nhận từ máy và chấm công online. Thấy một ngày sai thì '
			. '<strong>gắn cờ</strong>: cờ nằm cạnh, không đè lên giờ, và giữ lại lý do.</em></p>';
		echo '<form method="get"><input type="hidden" name="page" value="vhcc-cham" />'
			. self::chon_coso( 'coso', $coso )
			. ' <input type="text" name="thang" value="' . esc_attr( $thang ) . '" placeholder="yyyy-MM" /> '
			. '<button class="button button-primary">Xem</button></form>';

		/* ---- Thống kê đẩy: chỗ nhìn ra cơ sở nào tự nhiên im ---- */
		echo '<h2>Thống kê lượt đẩy tháng ' . esc_html( $thang ) . '</h2>';
		echo '<p>Đây là chỗ nhìn ra <strong>cơ sở nào tự nhiên im</strong> — mà im lặng là kiểu hỏng '
			. 'khó thấy nhất: máy vẫn kêu bíp, không ai báo gì, tới cuối tháng mới thiếu công.</p>';
		$tk = VHCC_Cham::thong_ke_day( $u, $thang );
		echo '<table class="widefat striped"><thead><tr><th>Cơ sở</th><th>Nguồn</th><th>Số lượt</th>'
			. '<th>Từ ngày</th><th>Đến ngày</th></tr></thead><tbody>';
		foreach ( $tk as $r ) {
			echo '<tr><td>' . esc_html( $r['coso'] ) . '</td><td><code>' . esc_html( $r['nguon'] )
				. '</code></td><td>' . (int) $r['so'] . '</td><td>' . esc_html( $r['tu_ngay'] )
				. '</td><td>' . esc_html( $r['den_ngay'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><em>Nguồn <code>may</code> = máy đẩy · <code>online</code> = điện thoại · '
			. '<code>hon-hop</code> = ngày có cả hai. Phép đối chiếu với sheet chỉ đếm lượt của MÁY.</em></p>';

		if ( '' === $coso ) { echo '</div>'; return; }

		/* ---- Cảnh báo thiếu giờ ra ---- */
		$cb = VHCC_Cham::canh_bao_thieu_gio_ra( $u, $coso, $thang );
		echo '<h2>Quên check-out (' . count( $cb ) . ')</h2>';
		if ( ! $cb ) {
			echo '<p><em>Không có ngày nào thiếu giờ ra.</em></p>';
		} else {
			echo '<p>Hệ thống <strong>không tự điền</strong> giờ ra — điền là bịa giờ làm cho một ngày '
				. 'mà không ai biết người ta làm bao lâu, và cái đó thành tiền.</p>';
			echo '<table class="widefat striped"><thead><tr><th>Ngày</th><th>Mã NV</th><th>Hậu tố</th>'
				. '<th>Họ tên</th><th>Giờ vào</th><th>Gắn cờ</th></tr></thead><tbody>';
			foreach ( $cb as $r ) {
				echo '<tr><td>' . esc_html( $r['ngay'] ) . '</td><td><code>' . esc_html( $r['maNV'] )
					. '</code></td><td>' . esc_html( $r['hauTo'] ) . '</td><td>' . esc_html( $r['hoTen'] )
					. '</td><td>' . esc_html( $r['vao'] ) . '</td><td>'
					. '<form method="post" style="display:flex;gap:4px">'
					. '<input type="hidden" name="vhcc_cc" value="co" />'
					. '<input type="hidden" name="coso" value="' . esc_attr( $coso ) . '" />'
					. '<input type="hidden" name="ngay" value="' . esc_attr( $r['ngay'] ) . '" />'
					. '<input type="hidden" name="ma_nv" value="' . esc_attr( $r['maNV'] ) . '" />'
					. '<input type="hidden" name="ho_ten" value="' . esc_attr( $r['hoTen'] ) . '" />';
				echo wp_nonce_field( 'vhcc_cc', '_wpnonce', true, false );
				echo '<input name="ghi_chu" placeholder="cần kiểm gì" required />'
					. '<button class="button">Gắn cờ</button></form></td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ---- Cờ cần kiểm ---- */
		$co = VHCC_Cham::ds_ghi_chu( $u, $coso, $thang );
		echo '<h2>Cờ cần kiểm (' . count( $co ) . ')</h2>';
		if ( $co ) {
			echo '<table class="widefat striped"><thead><tr><th>Ngày</th><th>Mã NV</th><th>Ghi chú</th>'
				. '<th>Người gắn</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
			foreach ( $co as $r ) {
				echo '<tr><td>' . esc_html( $r['ngay'] ) . '</td><td><code>' . esc_html( $r['ma_nv'] )
					. '</code></td><td>' . nl2br( esc_html( $r['ghi_chu'] ) ) . '</td><td>'
					. esc_html( $r['nguoi_gan'] ) . '</td><td>' . esc_html( $r['trang_thai'] ) . '</td><td>';
				if ( 'Đã xử lý' !== $r['trang_thai'] ) {
					echo '<form method="post" style="display:flex;gap:4px">'
						. '<input type="hidden" name="vhcc_cc" value="xu_ly" />'
						. '<input type="hidden" name="flag_id" value="' . esc_attr( $r['flag_id'] ) . '" />';
					echo wp_nonce_field( 'vhcc_cc', '_wpnonce', true, false );
					echo '<input name="ket_luan" placeholder="kết luận" />'
						. '<button class="button">Xử lý</button></form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p><em>Xử lý cờ chỉ <strong>thêm</strong> kết luận, giữ nguyên nội dung gốc — lý do '
				. 'gắn cờ là thứ duy nhất giải thích được một ngày công bất thường về sau.</em></p>';
		}

		/* ---- Bảng chấm công ---- */
		$b = VHCC_Cham::bang_cham_cong( $u, $coso, $thang );
		if ( empty( $b['ok'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $b['error'] ) . '</p></div></div>';
			return;
		}
		echo '<h2>Chấm công ' . esc_html( $coso ) . ' — ' . esc_html( $b['thang'] )
			. ' (' . count( $b['hang'] ) . ' hàng)</h2>';
		/* 🔴 "0 hàng" KHÔNG nói được vì sao. Ba lý do khác hẳn nhau: chưa kéo tháng đó, đã kéo mà
		   sheet trống, hoặc kéo rồi mà nhìn nhầm tháng. Tra thẳng sổ tiến độ rồi nói ra — bản
		   trước chỉ hiện "0 hàng" trơn và anh Thắng phải hỏi "tại sao nó chưa qua". */
		if ( ! count( $b['hang'] ) ) {
			$td_cc  = VHCC_Keo::tien_do();
			$khoa_cc = $coso . '|' . substr( $b['thang'], 5, 2 ) . '-' . substr( $b['thang'], 0, 4 );
			if ( isset( $td_cc[ $khoa_cc ] ) ) {
				$x_cc = $td_cc[ $khoa_cc ];
				echo '<div class="notice notice-info inline"><p><b>Đã kéo tháng này rồi</b> (lúc '
					. esc_html( isset( $x_cc['luc'] ) ? $x_cc['luc'] : '?' ) . ') nhưng app gốc trả về '
					. (int) ( isset( $x_cc['luot'] ) ? $x_cc['luot'] : 0 ) . ' giờ vào/ra — nghĩa là '
					. '<b>sheet của cơ sở này tháng này không có dữ liệu</b>, không phải lệnh kéo hỏng. '
					. 'Kiểm lại tên cơ sở và tháng bên Google Sheet.</p></div>';
			} else {
				echo '<div class="notice notice-warning inline"><p><b>Chưa kéo tháng này.</b> '
					. 'Vào <a href="' . esc_url( admin_url( 'admin.php?page=vhcc-nhan-su' ) ) . '">Nhân sự '
					. '→ Kéo dữ liệu cũ từ app gốc</a>, mục <b>Chấm công cũ</b>: điền Từ tháng / Đến tháng '
					. 'dạng <code>MM-yyyy</code>, ô Cơ sở gõ <code>' . esc_html( $coso ) . '</code> (hoặc để '
					. 'trống để kéo mọi cơ sở), bỏ tích "chỉ xem trước", rồi bấm Kéo.</p></div>';
			}
		}
		echo '<table class="widefat striped"><thead><tr><th>Ngày</th><th>Mã NV</th><th>Hậu tố</th>'
			. '<th>Họ tên</th><th>Giờ vào</th><th>Giờ ra</th></tr></thead><tbody>';
		foreach ( $b['hang'] as $r ) {
			echo '<tr><td>' . esc_html( $r['ngay'] ) . '</td><td><code>' . esc_html( $r['maNV'] )
				. '</code></td><td>' . esc_html( $r['hauTo'] ) . '</td><td>' . esc_html( $r['hoTen'] )
				. '</td><td>' . esc_html( $r['vao'] ) . '</td><td'
				. ( '' === $r['ra'] ? ' style="color:#b32d2e"' : '' ) . '>'
				. esc_html( '' !== $r['ra'] ? $r['ra'] : 'THIẾU' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Tăng cường ---- */
		$tc = VHCC_Cham::ds_tang_cuong( $coso, $thang );
		echo '<h2>Tăng cường (' . count( $tc ) . ')</h2>';
		echo '<p>Người của cơ sở khác sang làm ở đây. Chốt kỳ rồi thì không sửa được nữa '
			. 'nữa — sửa sau khi chốt là số công đổi sau khi bảng lương đã in.</p>';
		if ( $tc ) {
			echo '<table class="widefat striped"><thead><tr><th>Ngày</th><th>Mã NV</th><th>Họ tên</th>'
				. '<th>Cơ sở gốc</th><th>Ghi chú</th><th>Chốt kỳ</th></tr></thead><tbody>';
			foreach ( $tc as $r ) {
				echo '<tr><td>' . esc_html( $r['ngay'] ) . '</td><td><code>' . esc_html( $r['ma_nv'] )
					. '</code></td><td>' . esc_html( $r['ho_ten'] ) . '</td><td>'
					. esc_html( $r['coso_goc'] ) . '</td><td>' . esc_html( $r['ghi_chu'] ) . '</td><td>'
					. ( (int) $r['khoa'] ? '🔒 đã chốt' : '—' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<form method="post" style="display:flex;gap:6px;align-items:center;margin:8px 0">'
			. '<input type="hidden" name="vhcc_cc" value="tc" />'
			. '<input type="hidden" name="coso_den" value="' . esc_attr( $coso ) . '" />';
		wp_nonce_field( 'vhcc_cc' );
		echo '<input type="date" name="ngay" required /> <input name="ma_nv" placeholder="Mã NV" required /> '
			. '<input name="ghi_chu" placeholder="ghi chú" /> <button class="button">Khai tăng cường</button>'
			. '</form>';
		echo '<form method="post"><input type="hidden" name="vhcc_cc" value="khoa_tc" />'
			. '<input type="hidden" name="coso" value="' . esc_attr( $coso ) . '" />'
			. '<input type="hidden" name="thang" value="' . esc_attr( $thang ) . '" />'
			. '<input type="hidden" name="khoa" value="1" />';
		wp_nonce_field( 'vhcc_cc' );
		echo '<p><button class="button" onclick="return confirm(\'Chốt kỳ tăng cường tháng này? '
			. 'Chốt rồi không sửa được nữa.\')">🔒 Chốt kỳ tháng ' . esc_html( $thang )
			. '</button></p></form>';

		/* ---- Quy đổi cơ sở ---- */
		$qd = VHCC_Cham::ds_quy_doi();
		echo '<h2>Quy đổi tên cơ sở (' . count( $qd ) . ')</h2>';
		echo '<p>Tên máy khai → tên cơ sở thật. Hệ thống chỉ tra <strong>một bước</strong>, nên chuỗi '
			. 'A→B→C bị từ chối: nó sẽ sai im lặng.</p>';
		if ( $qd ) {
			echo '<table class="widefat striped"><thead><tr><th>Từ</th><th>Về</th><th>Ghi chú</th>'
				. '</tr></thead><tbody>';
			foreach ( $qd as $r ) {
				echo '<tr><td><code>' . esc_html( $r['tu'] ) . '</code></td><td><code>'
					. esc_html( $r['den'] ) . '</code></td><td>' . esc_html( $r['ghi_chu'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<form method="post" style="display:flex;gap:6px;align-items:center;margin:8px 0">'
			. '<input type="hidden" name="vhcc_cc" value="qd" />';
		wp_nonce_field( 'vhcc_cc' );
		echo '<input name="tu" placeholder="tên máy khai" required /> → '
			. '<input name="den" placeholder="tên cơ sở thật" required /> '
			. '<input name="ghi_chu" placeholder="ghi chú" /> <button class="button">Lưu quy đổi</button>'
			. '</form>';

		/* ---- Dọn ---- */
		echo '<h2>Dọn lượt chờ gán đã xử lý</h2>';
		echo '<p><strong>Chỉ dọn bảng chờ gán</strong>, và chỉ những lượt đã xử lý xong. Bảng chấm '
			. 'công không bị chạm — dọn mà xoá chấm công là xoá tiền lương.</p>';
		echo '<form method="post"><input type="hidden" name="vhcc_cc" value="don" />';
		wp_nonce_field( 'vhcc_cc' );
		echo '<p>Xoá lượt nhận trước ngày <input type="date" name="truoc_ngay" required /> '
			. '<button class="button">Dọn</button></p></form>';
		echo '</div>';
	}

	// ============================================================ Yêu cầu nhân viên

	public static function trang_yeu_cau() {
		if ( ! current_user_can( VHCC_Admin::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$u = VHCC_Admin::toi();
		$bao = array();
		if ( isset( $_POST['vhcc_yc'] ) ) {
			check_admin_referer( 'vhcc_yc' );
			$v = sanitize_text_field( wp_unslash( $_POST['vhcc_yc'] ) );
			if ( 'duyet' === $v ) {
				$bao[] = VHCC_YeuCau::duyet( $u, wp_unslash( $_POST['ma_yc'] ),
					! empty( $_POST['tao_ho_so'] ), array(
						'ma_nv' => wp_unslash( $_POST['ma_nv'] ),
						'cua_hang' => wp_unslash( $_POST['cua_hang'] ) ) );
			} elseif ( 'tu_choi' === $v ) {
				$bao[] = VHCC_YeuCau::tu_choi( $u, wp_unslash( $_POST['ma_yc'] ), wp_unslash( $_POST['ly_do'] ) );
			} elseif ( 'gui_loat' === $v ) {
				$ds = array();
				foreach ( preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['loat'] ) ) as $d ) {
					if ( '' === trim( $d ) ) { continue; }
					$o = ( false !== strpos( $d, "\t" ) ) ? explode( "\t", $d ) : explode( ',', $d );
					$ds[] = array(
						'coso' => isset( $o[0] ) ? trim( $o[0] ) : '',
						'ma_nv' => isset( $o[1] ) ? trim( $o[1] ) : '',
						'ho_ten' => isset( $o[2] ) ? trim( $o[2] ) : '',
						'noi_dung' => isset( $o[3] ) ? trim( $o[3] ) : '',
						'loai' => 'Thêm người' );
				}
				$bao[] = VHCC_YeuCau::gui_loat( $u, $ds );
			} elseif ( 'gui' === $v ) {
				$bao[] = VHCC_YeuCau::gui( $u, array( 'coso' => wp_unslash( $_POST['coso'] ),
					'loai' => wp_unslash( $_POST['loai'] ), 'ma_nv' => wp_unslash( $_POST['ma_nv'] ),
					'ho_ten' => wp_unslash( $_POST['ho_ten'] ),
					'noi_dung' => wp_unslash( $_POST['noi_dung'] ) ) );
			}
		}

		echo '<div class="wrap"><h1>Yêu cầu nhân viên</h1>';
		self::bao( $bao );
		$cho = VHCC_YeuCau::ds( $u, true );
		echo '<h2>Chờ duyệt (' . count( $cho ) . ')</h2>';
		echo '<p>Duyệt một yêu cầu thêm người là <strong>cấp Mã NV dùng chung cả chuỗi</strong> — nên '
			. 'chỉ Admin/Quản lý duyệt được. Cửa hàng trưởng gửi được nhưng không tự duyệt cho mình.</p>';
		if ( ! $cho ) {
			echo '<p><em>Không có yêu cầu nào chờ duyệt.</em></p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Mã YC</th><th>Loại</th><th>Cơ sở</th>'
				. '<th>Mã NV</th><th>Họ tên</th><th>Nội dung</th><th>Người xin</th><th>Lúc xin</th>'
				. '<th>Xử lý</th></tr></thead><tbody>';
			foreach ( $cho as $r ) {
				echo '<tr><td><code>' . esc_html( $r['ma_yc'] ) . '</code></td><td>'
					. esc_html( $r['loai'] ) . '</td><td>' . esc_html( $r['coso'] ) . '</td>'
					. '<td><code>' . esc_html( $r['ma_nv'] ) . '</code></td><td>'
					. esc_html( $r['ho_ten'] ) . '</td><td><pre style="white-space:pre-wrap;margin:0">'
					. esc_html( $r['noi_dung'] ) . '</pre></td><td>' . esc_html( $r['nguoi_xin'] )
					. '</td><td>' . esc_html( $r['luc_xin'] ) . '</td><td>';
				echo '<form method="post" style="margin-bottom:6px">'
					. '<input type="hidden" name="vhcc_yc" value="duyet" />'
					. '<input type="hidden" name="ma_yc" value="' . esc_attr( $r['ma_yc'] ) . '" />';
				echo wp_nonce_field( 'vhcc_yc', '_wpnonce', true, false );
				echo '<p><label><input type="checkbox" name="tao_ho_so" value="1" checked /> '
					. 'tạo luôn hồ sơ</label></p>'
					. '<p><input name="ma_nv" value="' . esc_attr( $r['ma_nv'] ) . '" placeholder="Mã NV" /></p>'
					. '<p>' . self::chon_coso( 'cua_hang', VHCC_NhanSu::chuan_coso( $r['coso'] ) ) . '</p>'
					. '<button class="button button-primary">Duyệt</button></form>';
				echo '<form method="post"><input type="hidden" name="vhcc_yc" value="tu_choi" />'
					. '<input type="hidden" name="ma_yc" value="' . esc_attr( $r['ma_yc'] ) . '" />';
				echo wp_nonce_field( 'vhcc_yc', '_wpnonce', true, false );
				echo '<input name="ly_do" placeholder="lý do từ chối" required /> '
					. '<button class="button">Từ chối</button></form>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p><em>Từ chối <strong>phải</strong> có lý do — không thì người gửi sẽ gửi lại y như '
				. 'cũ. Tạo hồ sơ trượt thì yêu cầu <strong>vẫn ở trạng thái chờ</strong>, không hiện '
				. '"Đã duyệt" giả.</em></p>';
		}

		echo '<h2>Gửi một yêu cầu</h2>';
		echo '<form method="post"><input type="hidden" name="vhcc_yc" value="gui" />';
		wp_nonce_field( 'vhcc_yc' );
		echo '<table class="form-table">'
			. '<tr><th>Cơ sở</th><td>' . self::chon_coso( 'coso', '' ) . '</td></tr>'
			. VHCC_Admin::o( 'loai', 'Loại', 'Thêm người' )
			. VHCC_Admin::o( 'ma_nv', 'Mã NV (nếu đã có)', '' )
			. VHCC_Admin::o( 'ho_ten', 'Họ tên', '' )
			. '<tr><th>Nội dung *</th><td><textarea name="noi_dung" rows="3" class="large-text" required>'
			. '</textarea></td></tr></table>';
		echo '<p><button class="button button-primary">Gửi yêu cầu</button></p></form>';

		echo '<h3>Gửi nhiều yêu cầu một lượt</h3>';
		echo '<p>Mỗi dòng một yêu cầu, các ô cách nhau bằng dấu phẩy hoặc tab: '
			. '<code>Cơ sở, Mã NV, Họ tên, Nội dung</code>. Dòng nào bị bỏ sẽ hiện <strong>kèm lý do '
			. 'từng cái</strong> — "gửi 8/10" mà không nói 2 cái nào là con số vô dụng.</p>';
		echo '<form method="post"><input type="hidden" name="vhcc_yc" value="gui_loat" />';
		wp_nonce_field( 'vhcc_yc' );
		echo '<p><textarea name="loat" rows="5" class="large-text" '
			. 'placeholder="TUTU_BT, , Nguyễn A, xin thêm 1 bạn thu tiền"></textarea></p>';
		echo '<p><button class="button">Gửi cả loạt</button></p></form>';

		$het = VHCC_YeuCau::ds( $u, false );
		echo '<h2>Đã xử lý (' . ( count( $het ) - count( $cho ) ) . ')</h2>';
		echo '<table class="widefat striped"><thead><tr><th>Mã YC</th><th>Trạng thái</th><th>Cơ sở</th>'
			. '<th>Họ tên</th><th>Người duyệt</th><th>Lúc duyệt</th><th>Ghi chú</th></tr></thead><tbody>';
		foreach ( $het as $r ) {
			if ( VHCC_YeuCau::CHO === $r['trang_thai'] ) { continue; }
			echo '<tr><td><code>' . esc_html( $r['ma_yc'] ) . '</code></td><td>'
				. esc_html( $r['trang_thai'] ) . '</td><td>' . esc_html( $r['coso'] ) . '</td><td>'
				. esc_html( $r['ho_ten'] ) . '</td><td>' . esc_html( $r['nguoi_duyet'] ) . '</td><td>'
				. esc_html( (string) $r['luc_duyet'] ) . '</td><td>' . esc_html( (string) $r['ghi_chu'] )
				. '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	// ============================================================ Cấu hình lương

	public static function trang_cf_luong() {
		if ( ! current_user_can( VHCC_Admin::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$u = VHCC_Admin::toi();
		$bao = array();
		$dc = null;
		if ( isset( $_POST['vhcc_cf'] ) ) {
			check_admin_referer( 'vhcc_cf' );
			$v = sanitize_text_field( wp_unslash( $_POST['vhcc_cf'] ) );
			if ( 'gia' === $v ) {
				$g = array();
				foreach ( array( 'congThuong', 'congCuoiTuan', 'congLe', 'gioThuong', 'gioCuoiTuan', 'gioLe' ) as $k ) {
					if ( isset( $_POST[ $k ] ) ) { $g[ $k ] = wp_unslash( $_POST[ $k ] ); }
				}
				$bao[] = VHCC_Luong::dat_don_gia_gio( $u, $g );
			} elseif ( 'nc' === $v ) {
				$bao[] = VHCC_Luong::dat_ngay_cong( $u, wp_unslash( $_POST['coso'] ),
					wp_unslash( $_POST['thang'] ), wp_unslash( $_POST['so'] ) );
			} elseif ( 'vp' === $v || 'thu' === $v ) {
				$cfg = array();
				foreach ( array( 'ngayTu', 'ngayDen', 'ngayMin', 'duoiMin', 'gioChuan', 'bacNua',
					'bacMot', 'bacRuoi', 'demToiThieuGio', 'nuaTuGio', 'graceRaPhut', 'ktThu7Tu',
					'ktThu7Den', 'ktThu7Min', 'demTu', 'demDen', 'demCong', 'demCongBu',
					'tangCaCong' ) as $k ) {
					if ( isset( $_POST[ $k ] ) && '' !== trim( (string) $_POST[ $k ] ) ) {
						$cfg[ $k ] = wp_unslash( $_POST[ $k ] );
					}
				}
				if ( isset( $_POST['ktMaNV'] ) ) {
					$cfg['ktMaNV'] = preg_split( '/[\s,;]+/', (string) wp_unslash( $_POST['ktMaNV'] ),
						-1, PREG_SPLIT_NO_EMPTY );
				}
				$cfg['ktChuNhatNghi'] = ! empty( $_POST['ktChuNhatNghi'] );
				$cs_thu = isset( $_POST['coso_thu'] ) ? wp_unslash( $_POST['coso_thu'] ) : '';
				$th_thu = isset( $_POST['thang_thu'] ) ? wp_unslash( $_POST['thang_thu'] ) : '';
				if ( 'thu' === $v ) {
					/* CHỈ XEM — không lưu. Đây là chỗ phải thấy con số TRƯỚC khi đổi lương cả cơ sở. */
					$dc = VHCC_Luong::so_sanh_cach_tinh( $u, $cs_thu, $th_thu, $cfg );
					$dc['caChuanThu'] = VHCC_Luong::ca_chuan( array_merge( VHCC_Luong::vp_cfg(), $cfg ) );
				} else {
					$r = VHCC_Luong::dat_vp_cfg( $u, $cfg, $cs_thu, $th_thu );
					$bao[] = $r;
					if ( ! empty( $r['doiChieu'] ) ) { $dc = $r['doiChieu']; }
				}
			}
		}

		echo '<div class="wrap"><h1>Cấu hình lương</h1>';
		self::bao( $bao );

		/* ---- Đơn giá Nhóm Máy Tự Động ---- */
		$g = VHCC_Luong::mtd_gia();
		echo '<h2>Đơn giá — Nhóm Máy Tự Động</h2>';
		echo '<p>Hàng mã trần tính theo <strong>công</strong> (đủ giờ vào + giờ ra = 1 công, không xét '
			. 'dài ngắn). Hàng <code>-TG</code> Trực Ghế tính theo <strong>giờ</strong>. Ngày lễ '
			. '<strong>đè</strong> cuối tuần.</p>';
		echo '<form method="post"><input type="hidden" name="vhcc_cf" value="gia" />';
		wp_nonce_field( 'vhcc_cf' );
		echo '<table class="form-table">';
		foreach ( array( 'congThuong' => 'Công — ngày thường', 'congCuoiTuan' => 'Công — cuối tuần',
			'congLe' => 'Công — ngày lễ', 'gioThuong' => 'Giờ — ngày thường',
			'gioCuoiTuan' => 'Giờ — cuối tuần', 'gioLe' => 'Giờ — ngày lễ' ) as $k => $nhan ) {
			echo VHCC_Admin::o( $k, $nhan, $g[ $k ] ? (string) $g[ $k ] : '' );
		}
		echo '</table><p><em>Chỉ nhận số dương. Để 0 là mọi ô tiền của cả cơ sở thành '
			. '0 mà bảng vẫn có số — trông như "tháng này không ai làm".</em></p>';
		echo '<p><button class="button button-primary">Lưu đơn giá</button></p></form>';

		/* ---- Số ngày công theo (cơ sở, tháng) ---- */
		echo '<h2>Số ngày công chuẩn của tháng</h2>';
		echo '<p>Mẫu số quy lương: <code>tiền = lương cơ bản × tổng công ÷ số ngày công</code>. Khai '
			. 'theo <strong>đúng cặp (cơ sở, tháng)</strong> — hệ thống không mượn số của tháng khác, '
			. 'vì đoán mẫu số là sai tiền của mọi người cùng lúc mà bảng vẫn có số.</p>';
		echo '<form method="post" style="display:flex;gap:6px;align-items:center">'
			. '<input type="hidden" name="vhcc_cf" value="nc" />';
		wp_nonce_field( 'vhcc_cf' );
		echo self::chon_coso( 'coso', '' )
			. ' <input name="thang" placeholder="yyyy-MM" required /> '
			. '<input name="so" placeholder="số ngày" required /> '
			. '<button class="button">Lưu</button></form>';
		$nc = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'vp_ngay_cong' )
			. ' ORDER BY thang DESC, coso LIMIT 60' );
		if ( $nc ) {
			echo '<table class="widefat striped" style="margin-top:8px"><thead><tr><th>Cơ sở</th>'
				. '<th>Tháng</th><th>Số ngày công</th><th>Người khai</th></tr></thead><tbody>';
			foreach ( $nc as $r ) {
				echo '<tr><td>' . esc_html( $r['coso'] ) . '</td><td>' . esc_html( $r['thang'] )
					. '</td><td>' . esc_html( null === $r['ngay_cong'] ? '—' : $r['ngay_cong'] )
					. '</td><td>' . esc_html( $r['nguoi_khai'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ---- Cấu hình công Văn phòng ---- */
		$c = VHCC_Luong::vp_cfg();
		$cc = VHCC_Luong::ca_chuan( $c );
		echo '<h2>Cấu hình công — bộ phận Văn phòng</h2>';
		echo '<div class="notice notice-warning"><p><strong>Ca chuẩn hiện tại: ' . esc_html( $cc['gio'] )
			. ' tiếng → ' . esc_html( $cc['cong'] ) . ' công.</strong> Đây là con số phải đọc TRƯỚC khi '
			. 'bấm Lưu: khung 08:30–17:00 dài 8.5 tiếng, và nếu mốc bậc thang đặt sai thì chính '
			. '<em>ngày làm bình thường</em> thành 1.5 công — tức lương cả cơ sở tăng 50%, không riêng '
			. 'người thiếu giờ.</p></div>';
		echo '<form method="post"><input type="hidden" name="vhcc_cf" value="vp" id="vhcc-cf-viec" />';
		wp_nonce_field( 'vhcc_cf' );
		echo '<table class="form-table">'
			. VHCC_Admin::o( 'ngayTu', 'Ca ngày từ', $c['ngayTu'] )
			. VHCC_Admin::o( 'ngayDen', 'Ca ngày đến', $c['ngayDen'] )
			. VHCC_Admin::o( 'ngayMin', 'Đủ bao nhiêu tiếng = 1 công', (string) $c['ngayMin'] );
		echo '<tr><th>Làm thiếu giờ thì tính sao</th><td><select name="duoiMin">';
		foreach ( array( 'tyle' => 'Theo tỷ lệ (giờ ÷ giờ chuẩn)', 'nua' => 'Nửa công nếu đủ mốc',
			'tron' => 'Tròn 1 công', 'khong' => 'Không công',
			'bacthang' => 'Bậc thang (<4h nửa công · <9h một công · <12h 1.5 công)' ) as $k => $nhan ) {
			echo '<option value="' . esc_attr( $k ) . '"' . ( $c['duoiMin'] === $k ? ' selected' : '' )
				. '>' . esc_html( $nhan ) . '</option>';
		}
		echo '</select></td></tr>'
			. VHCC_Admin::o( 'gioChuan', 'Giờ chuẩn (cho cách tỷ lệ)', (string) $c['gioChuan'] )
			. VHCC_Admin::o( 'bacNua', 'Bậc thang — dưới mốc này nửa công', (string) $c['bacNua'] )
			. VHCC_Admin::o( 'bacMot', 'Bậc thang — dưới mốc này một công', (string) $c['bacMot'] );
		echo '<tr><th></th><td><em>Mốc này mặc định <strong>9</strong>, không phải 8. Với mốc 8 thì ca '
			. 'chuẩn 8.5 tiếng rơi vào bậc "dưới 12h" = 1.5 công.</em></td></tr>'
			. VHCC_Admin::o( 'bacRuoi', 'Bậc thang — dưới mốc này 1.5 công', (string) $c['bacRuoi'] )
			. VHCC_Admin::o( 'graceRaPhut', 'Ân hạn tan làm (phút)', (string) $c['graceRaPhut'] );
		echo '<tr><th></th><td><em>Tan làm bấm trong khoảng ân hạn mà hàng 1 chưa có giờ ra thì đó là '
			. 'tan làm, KHÔNG phải mở hàng tăng ca — không có chỗ này là mất trọn 1 công ngày.</em></td></tr>'
			. VHCC_Admin::o( 'demTu', 'Ca đêm từ', $c['demTu'] )
			. VHCC_Admin::o( 'demDen', 'Ca đêm đến (hôm sau)', $c['demDen'] )
			. VHCC_Admin::o( 'demToiThieuGio', 'Ca đêm tối thiểu (giờ, 0 = không xét)', (string) $c['demToiThieuGio'] );
		echo '<tr><th></th><td><em>Ngưỡng này KHÔNG áp cho ca thiếu cặp giờ: quên chấm ra thì không '
			. 'cách nào biết ca dài bao lâu, cắt ngầm là trừ tiền một người vì cái máy lỗi.</em></td></tr>'
			. VHCC_Admin::o( 'demCong', 'Công của một ca đêm', (string) $c['demCong'] )
			. VHCC_Admin::o( 'demCongBu', 'Công nghỉ bù sau ca đêm', (string) $c['demCongBu'] )
			. VHCC_Admin::o( 'tangCaCong', 'Công của một ca tăng ca', (string) $c['tangCaCong'] )
			. VHCC_Admin::o( 'ktThu7Tu', 'Kế toán — thứ Bảy từ', $c['ktThu7Tu'] )
			. VHCC_Admin::o( 'ktThu7Den', 'Kế toán — thứ Bảy đến', $c['ktThu7Den'] )
			. VHCC_Admin::o( 'ktThu7Min', 'Kế toán — thứ Bảy đủ bao nhiêu tiếng = 1 công', (string) $c['ktThu7Min'] )
			. VHCC_Admin::o( 'ktMaNV', 'Mã NV thuộc Kế toán văn phòng (cách nhau dấu phẩy)',
				implode( ', ', (array) $c['ktMaNV'] ) );
		echo '<tr><th>Kế toán Chủ nhật</th><td><label><input type="checkbox" name="ktChuNhatNghi" '
			. 'value="1"' . ( $c['ktChuNhatNghi'] ? ' checked' : '' ) . ' /> Chủ nhật là ngày nghỉ '
			. '(0 công ngày, nhưng vẫn giữ dòng để soi được là có đi làm)</label></td></tr>';
		echo '<tr><th>Đối chiếu thử trên</th><td>' . self::chon_coso( 'coso_thu', '' )
			. ' <input name="thang_thu" placeholder="yyyy-MM" /></td></tr>';
		echo '</table>';
		echo '<p><button class="button" name="vhcc_cf" value="thu">Xem đối chiếu (KHÔNG lưu)</button> '
			. '<button class="button button-primary" name="vhcc_cf" value="vp">Lưu cấu hình</button></p>'
			. '</form>';

		/* ---- Bảng đối chiếu ---- */
		if ( is_array( $dc ) ) {
			echo '<h2>Đối chiếu cách tính</h2>';
			if ( empty( $dc['ok'] ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html( $dc['error'] ) . '</p></div>';
			} else {
				if ( isset( $dc['caChuanThu'] ) ) {
					echo '<p>Ca chuẩn theo cấu hình <strong>đang thử</strong>: '
						. esc_html( $dc['caChuanThu']['gio'] ) . ' tiếng → <strong>'
						. esc_html( $dc['caChuanThu']['cong'] ) . ' công</strong>.</p>';
				}
				echo '<p>Cơ sở <strong>' . esc_html( $dc['coSo'] ) . '</strong> tháng '
					. esc_html( $dc['thang'] ) . ' — chênh <strong>' . esc_html( $dc['chenhCong'] )
					. ' công</strong> và <strong>' . esc_html( number_format( $dc['chenhTien'] ) )
					. ' đồng</strong>.</p>';
				echo '<p><em>Hai bên chạy trên cùng một lần đọc dữ liệu, nên cột chênh chỉ có thể do '
					. 'CÁCH TÍNH. Xem bảng này không đổi lương của ai.</em></p>';
				echo '<table class="widefat striped"><thead><tr><th>Mã NV</th><th>Họ tên</th>'
					. '<th>Công cũ</th><th>Công mới</th><th>Chênh công</th>'
					. '<th>Tiền cũ</th><th>Tiền mới</th><th>Chênh tiền</th></tr></thead><tbody>';
				foreach ( $dc['dong'] as $r ) {
					$mau = 0 == $r['chenhTien'] ? '' : ( $r['chenhTien'] > 0 ? '#046b2d' : '#b32d2e' );
					echo '<tr><td><code>' . esc_html( $r['ma'] ) . '</code></td><td>'
						. esc_html( $r['ten'] ) . '</td><td>' . esc_html( $r['congCu'] ) . '</td><td>'
						. esc_html( $r['congMoi'] ) . '</td><td>' . esc_html( $r['chenhCong'] ) . '</td>'
						. '<td>' . esc_html( number_format( $r['tienCu'] ) ) . '</td><td>'
						. esc_html( number_format( $r['tienMoi'] ) ) . '</td>'
						. '<td' . ( $mau ? ' style="color:' . $mau . ';font-weight:bold"' : '' ) . '>'
						. esc_html( number_format( $r['chenhTien'] ) ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
		}
		echo '</div>';
	}
}
