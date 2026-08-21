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
			update_option( 'vhcc_exec_url', $url );

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

		if ( $msg === 'thu' ) {
			$r = get_transient( 'vhcc_thu_' . get_current_user_id() );
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
		if ( $nguon === 'chung' ) {
			$u = VHCC_Auth::users();
			if ( is_wp_error( $u ) ) {
				echo '<p style="color:#b32d2e"><b>' . esc_html( $u->get_error_message() ) . '</b></p>';
			} else {
				$duoc = 0;
				foreach ( $u as $x ) {
					if ( in_array( $x['vaiTro'], VHCC_Auth::VAI_TRO_VAO, true ) ) { $duoc++; }
				}
				echo '<p>Đang đọc được <b>' . count( $u ) . '</b> người, trong đó <b>' . $duoc
					. '</b> người vào được hệ thống chấm công (' . esc_html( implode( ' · ', VHCC_Auth::vai_tro_vao() ) ) . ').</p>';
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
		echo '<p>Mở project Apps Script của app chấm công → File → New → Script file, đặt tên <code>CauNoi</code>, '
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
