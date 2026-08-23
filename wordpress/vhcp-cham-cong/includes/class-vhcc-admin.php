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

	/** Số cặp (cơ sở × tháng) kéo mỗi lượt bấm. Xem chú thích ở nút "Kéo chấm công". */
	const KEO_MOI_ME = 8;

	/**
	 * Dải nhỏ trên MỌI màn của plugin: số bản đang chạy.
	 *
	 * 🔴 Vì sao cần: suốt buổi cài, câu hỏi tốn thời gian nhất là "anh đang chạy bản nào" —
	 *    một câu chỉ trả lời được nếu bỏ màn đang làm để sang màn Cài đặt xem. Đã có lần cả
	 *    hai bên nhìn một màn hình cũ và đi tìm lỗi đã sửa xong từ bản trước.
	 *    Móc vào `in_admin_header` nên chỉ sửa MỘT chỗ, không phải sửa 11 hàm vẽ.
	 */
	public static function dai_ban() {
		$m = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'vhcc' !== $m && strpos( $m, 'vhcc-' ) !== 0 ) { return; }
		echo '<div style="margin:6px 0 0;color:#64748b;font-size:12px">Chấm Công — bản <code>'
			. esc_html( VHCC_VERSION ) . '</code></div>';
	}

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
	 * Điều phối một MẺ kéo chấm công: nhiều cặp (cơ sở × tháng), có trần, ghi tiến độ.
	 *
	 * 🔴 VÌ SAO CHIA MẺ chứ không kéo hết một lượt: hosting chia sẻ giới hạn thời gian chạy PHP,
	 *    mà mỗi cặp là một lượt gọi mạng sang Apps Script (vài giây). 26 cơ sở × 12 tháng = 312
	 *    lượt — chết giữa đường là chắc, và chết giữa đường thì KHÔNG BIẾT đã tới đâu. Nên: làm
	 *    tối đa KEO_MOI_ME cặp, ghi lại từng cặp xong, rồi báo còn bao nhiêu.
	 *
	 * 🔴 Bỏ qua cặp ĐÃ KÉO XONG, nên bấm lại là đi tiếp, không làm lại từ đầu. (Kéo lại vẫn an
	 *    toàn — chỉ là mất thời gian vô ích.)
	 */
	private static function keo_cham_cong( $tu, $den, $coso_chon, $chi_xem ) {
		$thangs = VHCC_Keo::ds_thang( $tu, $den );
		if ( ! $thangs ) {
			return array( 'ok' => false, 'error' => 'Khoảng tháng không hợp lệ. Dạng MM-yyyy, '
				. 'tháng đầu không được sau tháng cuối, và tối đa 36 tháng một lần.' );
		}

		$coso_chon = trim( (string) $coso_chon );
		if ( '' !== $coso_chon ) {
			$ds_cs = array( VHCC_NhanSu::chuan_coso( $coso_chon ) );
		} else {
			$r = VHCC_Keo::ds_coso();
			if ( empty( $r['ok'] ) ) { return $r; }
			$ds_cs = $r['ds'];
			if ( ! $ds_cs ) {
				return array( 'ok' => false, 'error' => 'App gốc không trả về cơ sở nào. Bên đó cần '
					. '"Quét sheet CS" trước, hoặc anh gõ thẳng tên một cơ sở vào ô Cơ sở.' );
			}
		}

		$td   = VHCC_Keo::tien_do();
		$lam  = 0; $con = 0; $nguoi = 0; $luot = 0; $loi = array(); $khong_sheet = 0;
		foreach ( $ds_cs as $cs ) {
			foreach ( $thangs as $th ) {
				if ( ! $chi_xem && isset( $td[ $cs . '|' . $th ] ) ) { continue; }   // đã xong
				if ( $lam >= self::KEO_MOI_ME ) { $con++; continue; }
				$kq = VHCC_Keo::keo_thang( $cs, $th, $chi_xem );
				$lam++;
				if ( empty( $kq['ok'] ) ) {
					$loi[] = $cs . ' ' . $th . ': ' . ( isset( $kq['error'] ) ? $kq['error'] : '?' );
					continue;
				}
				if ( ! empty( $kq['khong_co_sheet'] ) ) { $khong_sheet++; continue; }
				$nguoi += (int) $kq['nguoi'];
				$luot  += (int) $kq['luot'];
				if ( ! $chi_xem ) { VHCC_Keo::ghi_tien_do( $cs, $th, $kq ); }
			}
		}

		$msg = ( $chi_xem ? 'XEM TRƯỚC — chưa ghi gì. ' : 'Đã kéo. ' )
			. 'Làm ' . $lam . ' cặp (cơ sở × tháng) · ' . $nguoi . ' lượt người · ' . $luot . ' giờ vào/ra';
		if ( $khong_sheet ) { $msg .= ' · ' . $khong_sheet . ' cặp không có sheet (bỏ qua, không phải lỗi)'; }
		if ( $con )         { $msg .= ' · CÒN ' . $con . ' cặp — bấm lại để đi tiếp'; }
		if ( $loi )         { $msg .= ' · LỖI: ' . implode( ' | ', array_slice( $loi, 0, 3 ) ); }
		return array( 'ok' => empty( $loi ), 'thong_bao' => $msg, 'error' => implode( ' | ', $loi ) );
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

	/**
	 * Vẽ kết quả của một lệnh vừa chạy.
	 *
	 * ⚠️ IN `thong_bao` NẾU CÓ. In cứng chữ "Đã lưu." là mọi con số của lệnh — đặt được mấy lệnh,
	 *    chuyển được mấy lượt bấm — rơi mất, mà đó đúng là thứ duy nhất cho biết lệnh đã làm gì.
	 */
	public static function ve_bao( $ds ) {
		foreach ( (array) $ds as $b ) {
			if ( ! empty( $b['ok'] ) ) {
				echo '<div class="notice notice-success"><p>'
					. esc_html( ! empty( $b['thong_bao'] ) ? $b['thong_bao'] : 'Đã lưu.' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>'
					. esc_html( isset( $b['error'] ) ? $b['error'] : 'Không chạy được lệnh.' ) . '</p></div>';
			}
		}
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
			} elseif ( 'keo_ns_xem' === $viec || 'keo_ns' === $viec ) {
				/* Kéo hồ sơ từ app gốc. Chỉ Admin/Quản lý — đây là ghi hồ sơ TOÀN CHUỖI, vượt
				   hẳn phạm vi một cửa hàng, nên đi qua đúng chốt quyền của nhân sự. */
				if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
					$bao[] = array( 'ok' => false, 'error' => VHCC_NhanSu::LOI_QT );
				} else {
					$keo_ns = VHCC_Keo::keo_nhan_su( 'keo_ns_xem' === $viec );
					$bao[]  = empty( $keo_ns['ok'] )
						? $keo_ns
						: array( 'ok' => true, 'thong_bao' => ( 'keo_ns_xem' === $viec ? 'XEM TRƯỚC — chưa ghi gì. ' : 'Đã kéo. ' )
							. 'Thêm ' . (int) $keo_ns['them'] . ' · cập nhật ' . (int) $keo_ns['sua']
							. ( $keo_ns['bo'] ? ' · bỏ ' . count( $keo_ns['bo'] ) . ' (' . implode( '; ', array_slice( $keo_ns['bo'], 0, 5 ) ) . ')' : '' ) );
				}
			} elseif ( 'keo_cc' === $viec ) {
				if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
					$bao[] = array( 'ok' => false, 'error' => VHCC_NhanSu::LOI_QT );
				} else {
					$bao[] = self::keo_cham_cong(
						wp_unslash( $_POST['tu'] ), wp_unslash( $_POST['den'] ),
						wp_unslash( $_POST['coso'] ), ! empty( $_POST['chi_xem'] ) );
				}
			} elseif ( 'keo_xoa_td' === $viec ) {
				VHCC_Keo::xoa_tien_do();
				$bao[] = array( 'ok' => true, 'thong_bao' => 'Đã xoá tiến độ. Lượt kéo tới sẽ đi lại từ đầu — '
					. 'không sao cả, kéo lại không sinh thêm dòng nào.' );
			} elseif ( 'nhiem_vu' === $viec ) {
				$bao[] = VHCC_NhanSu::dat_nhiem_vu( $u, wp_unslash( $_POST['ngay'] ),
					wp_unslash( $_POST['coso'] ), wp_unslash( $_POST['ma_nv'] ),
					wp_unslash( $_POST['nhiem_vu'] ) );
			}
		}

		echo '<div class="wrap"><h1>Nhân sự</h1>';
		foreach ( $bao as $b ) {
			/* ⚠️ IN `thong_bao` NẾU CÓ. Bản đầu luôn in đúng chữ "Đã lưu." nên mọi con số của
			   lệnh kéo — thêm bao nhiêu, còn bao nhiêu cặp phải bấm tiếp — đều rơi mất, mà đó
			   đúng là thứ duy nhất cho biết lệnh đã làm gì. */
			if ( ! empty( $b['ok'] ) ) {
				echo '<div class="notice notice-success"><p>'
					. esc_html( ! empty( $b['thong_bao'] ) ? $b['thong_bao'] : 'Đã lưu.' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( $b['error'] ) . '</p></div>';
			}
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
		/* ⚠️ KHỐI NÀY PHẢI Ở TRÊN. Bản đầu em đặt nó ở CUỐI trang, dưới tám mục khác — mà khi
		   MySQL còn trống thì kéo dữ liệu là việc ĐẦU TIÊN phải làm, không phải việc cuối. Anh
		   Thắng cuộn không tới, bấm nhầm "Xem trước" của ô dán tay (dán rỗng nên ra 0) rồi tưởng
		   lệnh kéo không chạy. Thứ tự trên màn hình chính là thứ tự việc phải làm. */
		/* ---- Kéo từ app gốc (đường B) ---- */
		$trong_rong = ! count( $ds ) && ! count( VHCC_DB::rows( 'SELECT ma_nv FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' LIMIT 1' ) );
		echo '<h2>Kéo dữ liệu cũ từ app gốc</h2>';
		if ( $trong_rong ) {
			echo '<div class="notice notice-warning inline" style="margin:8px 0"><p>'
				. '<b>Chưa có hồ sơ nào trong cơ sở dữ liệu.</b> Bắt đầu từ đây: bấm '
				. '<b>Xem trước hồ sơ sẽ kéo</b> ngay bên dưới. Mọi mục khác trên trang này '
				. '(sửa, xoá, cho nghỉ, đổi mã) đều cần có hồ sơ trước đã.</p></div>';
		}
		echo '<p>Đọc thẳng từ Google Sheet qua cầu nối — <b>một chiều</b>, sheet vẫn là nguồn thật. '
			. 'Kéo lại bao nhiêu lần cũng không sinh thêm dòng rác: hồ sơ khớp theo Mã NV, còn chấm '
			. 'công đi qua đúng cửa mà máy đang đẩy vào nên luật <em>chỉ nới, không thu hẹp</em> áp y '
			. 'nguyên.</p>';
		echo '<p><em>Cần đã dán bản <code>CauNoiChamCong</code> mới nhất (có hai hàm '
			. '<code>ccDsCoSoXuat</code>, <code>ccXuatChamCong</code>) rồi Deploy → New version.</em></p>';

		foreach ( array(
			'keo_ns_xem' => array( 'Xem trước hồ sơ sẽ kéo', 'button' ),
			'keo_ns'     => array( 'KÉO HỒ SƠ NHÂN SỰ', 'button button-primary' ),
		) as $act => $n ) {
			echo '<form method="post" style="display:inline-block;margin-right:8px">';
			wp_nonce_field( 'vhcc_ns' );
			echo '<input type="hidden" name="vhcc_ns" value="' . esc_attr( $act ) . '" />';
			echo '<button class="' . esc_attr( $n[1] ) . '">' . esc_html( $n[0] ) . '</button></form>';
		}

		echo '<h3>Chấm công cũ</h3>';
		echo '<form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">';
		wp_nonce_field( 'vhcc_ns' );
		echo '<input type="hidden" name="vhcc_ns" value="keo_cc" />';
		echo '<label>Từ tháng<br><input name="tu" value="' . esc_attr( gmdate( 'm-Y', (int) current_time( 'timestamp' ) ) )
			. '" placeholder="MM-yyyy" style="width:110px" /></label>';
		echo '<label>Đến tháng<br><input name="den" value="' . esc_attr( gmdate( 'm-Y', (int) current_time( 'timestamp' ) ) )
			. '" placeholder="MM-yyyy" style="width:110px" /></label>';
		echo '<label>Cơ sở<br><input name="coso" placeholder="để trống = mọi cơ sở" style="width:220px" /></label>';
		echo '<label><input type="checkbox" name="chi_xem" value="1" checked /> chỉ xem trước</label>';
		echo '<button class="button button-primary">Kéo chấm công</button></form>';
		echo '<p class="description">Mỗi lượt bấm kéo <b>tối đa ' . (int) self::KEO_MOI_ME . ' cặp (cơ sở × tháng)</b> '
			. 'rồi dừng và báo còn lại bao nhiêu — hosting chia sẻ có giới hạn thời gian, chết giữa mẻ '
			. 'thì không biết đã tới đâu. Bấm lại là nó đi tiếp từ chỗ dừng. Trần khoảng tháng là 36.</p>';
		$td = VHCC_Keo::tien_do();
		if ( $td ) {
			/* 🔴 LIỆT KÊ RA, không chỉ đếm. Câu hỏi thật khi mở bảng chấm công thấy "0 hàng" là
			   "đã kéo cơ sở này tháng này chưa?" — một con số tổng không trả lời được câu đó, mà
			   đoán thì mất cả vòng. Bảng dưới trả lời trực tiếp: cặp nào đã kéo, được mấy lượt. */
			echo '<p>Đã kéo <b>' . count( $td ) . '</b> cặp (cơ sở × tháng):</p>';
			krsort( $td );
			echo '<table class="widefat striped" style="max-width:760px;margin:6px 0"><thead><tr>'
				. '<th>Cơ sở</th><th>Tháng</th><th>Lượt người</th><th>Giờ vào/ra</th><th>Lúc kéo</th>'
				. '</tr></thead><tbody>';
			$dem_td = 0;
			foreach ( $td as $khoa_td => $x_td ) {
				if ( ++$dem_td > 40 ) { break; }
				$phan = explode( '|', (string) $khoa_td );
				$luot_td = isset( $x_td['luot'] ) ? (int) $x_td['luot'] : 0;
				echo '<tr><td>' . esc_html( isset( $phan[0] ) ? $phan[0] : '?' ) . '</td><td>'
					. esc_html( isset( $phan[1] ) ? $phan[1] : '?' ) . '</td><td>'
					. (int) ( isset( $x_td['nguoi'] ) ? $x_td['nguoi'] : 0 ) . '</td><td>'
					/* 0 giờ = đã kéo nhưng tháng đó sheet không có gì. Nói rõ, vì "đã kéo" mà bảng
					   trống thì người xem sẽ tưởng lệnh kéo hỏng. */
					. ( $luot_td ? $luot_td : '<span style="color:#8a6d3b">0 — tháng đó sheet không có dữ liệu</span>' )
					. '</td><td>' . esc_html( isset( $x_td['luc'] ) ? $x_td['luc'] : '' ) . '</td></tr>';
			}
			echo '</tbody></table>';
			if ( count( $td ) > 40 ) { echo '<p><em>(chỉ hiện 40 cặp gần nhất)</em></p>'; }
			echo '<form method="post" style="display:inline">';
			wp_nonce_field( 'vhcc_ns' );
			echo '<input type="hidden" name="vhcc_ns" value="keo_xoa_td" />';
			echo '<button class="button">Xoá tiến độ (kéo lại từ đầu)</button></form>';
		}


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
		echo '<hr><h2>Nhập nhân sự hàng loạt</h2>';
		echo '<p>Mỗi dòng một người, các ô cách nhau bằng dấu phẩy hoặc tab, theo thứ tự: '
			. '<code>Mã NV, Họ tên, Cửa hàng, SĐT, CCCD, Chức vụ</code>. Bấm <strong>Xem trước</strong> '
			. 'đã — nó bắt cả trùng mã <em>trong chính tệp</em>, mà hai dòng cùng mã là một cái ghi đè '
			. 'cái kia không ai thấy.</p>';
		echo '<form method="post"><input type="hidden" name="vhcc_ns" value="xem_nhap" />';
		wp_nonce_field( 'vhcc_ns' );
		echo '<p><textarea name="csv" rows="6" class="large-text" placeholder="NV001, Nguyễn A, TUTU_BT, 0900…">'
			. esc_textarea( isset( $_POST['csv'] ) ? wp_unslash( $_POST['csv'] ) : '' ) . '</textarea></p>';
		echo '<p><button class="button">Xem trước</button></p></form>';
		/* Dán rỗng mà vẫn vẽ bảng "Sẽ thêm 0 · cập nhật 0 · bỏ 0" kèm nút "Nhập thật" thì trông
		   y như một lệnh đã chạy và không tìm thấy gì — trong khi thật ra chưa dán gì cả. Nói
		   thẳng, và KHÔNG đưa nút "Nhập thật" cho một tệp rỗng. */
		if ( is_array( $xem_nhap ) && ! empty( $xem_nhap['ok'] ) && ! count( $ds_nhap ) ) {
			echo '<div class="notice notice-warning"><p><b>Ô dán đang trống</b> — chưa có gì để xem trước. '
				. 'Dán danh sách vào ô ở trên, hoặc dùng <b>Kéo dữ liệu cũ từ app gốc</b> ở đầu trang '
				. 'để lấy thẳng từ Google Sheet, khỏi dán tay.</p></div>';
		} elseif ( is_array( $xem_nhap ) && ! empty( $xem_nhap['ok'] ) ) {
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
			/* ⚠️ IN `thong_bao` NẾU CÓ. Bản đầu luôn in đúng chữ "Đã lưu." nên mọi con số của
			   lệnh kéo — thêm bao nhiêu, còn bao nhiêu cặp phải bấm tiếp — đều rơi mất, mà đó
			   đúng là thứ duy nhất cho biết lệnh đã làm gì. */
			if ( ! empty( $b['ok'] ) ) {
				echo '<div class="notice notice-success"><p>'
					. esc_html( ! empty( $b['thong_bao'] ) ? $b['thong_bao'] : 'Đã lưu.' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( $b['error'] ) . '</p></div>';
			}
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
	 * Từ bản 2.0.0 màn này KHÔNG gọi đi đâu cả: hàng đợi lệnh, nhịp sống, OTA đều nằm trên chính
	 * MySQL của website. Xem class-vhcc-may.php và class-vhcc-may-cong.php.
	 *
	 * ⚠️ Thứ tự trên màn có chủ ý — thứ im lặng hỏng để TRÊN:
	 *    1. máy mất nhịp  — cửa hàng đang không chấm công được mà không ai biết;
	 *    2. lượt bấm chờ gán — công của người thật đang nằm chờ;
	 *    3. hàng đợi lệnh — lệnh không xuống được máy thì mặt không vào máy;
	 *    4. firmware — việc chủ động, làm khi muốn.
	 */
	public static function trang_may() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$bao = array();
		if ( isset( $_POST['vhcc_may'] ) ) {
			check_admin_referer( 'vhcc_may' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhcc_may'] ) );
			$id   = isset( $_POST['may_id'] ) ? (int) $_POST['may_id'] : 0;
			if ( 'gan' === $viec ) {
				$bao[] = VHCC_May::gan_may( $id, wp_unslash( $_POST['coso'] ) );
			} elseif ( 'bo_gan' === $viec ) {
				$bao[] = VHCC_May::bo_gan( $id );
			} elseif ( 'sim' === $viec ) {
				$bao[] = VHCC_May::luu_sim( $id, wp_unslash( $_POST['sim'] ) );
			} elseif ( 'quet' === $viec ) {
				$bao[] = VHCC_May::yeu_cau_quet( $id );
			} elseif ( 'tai_lai' === $viec ) {
				$bao[] = VHCC_May::tai_lai( $id, wp_unslash( $_POST['tu'] ), wp_unslash( $_POST['den'] ),
					isset( $_POST['ma_nv'] ) ? wp_unslash( $_POST['ma_nv'] ) : '' );
			} elseif ( 'dung_tai_lai' === $viec ) {
				$bao[] = VHCC_May::dung_tai_lai( $id );
			} elseif ( 'xoa_lenh' === $viec ) {
				$bao[] = VHCC_May::xoa_lenh( wp_unslash( $_POST['op_id'] ) );
			} elseif ( 'ota' === $viec ) {
				$bao[] = VHCC_May::dat_ota( wp_unslash( $_POST['ver'] ), wp_unslash( $_POST['url'] ),
					wp_unslash( $_POST['xac_nhan'] ), 0 );
			} elseif ( 'ota_may' === $viec ) {
				$bao[] = VHCC_May::dat_ota( wp_unslash( $_POST['ver'] ), wp_unslash( $_POST['url'] ), '', $id );
			} elseif ( 'go_ota' === $viec ) {
				$bao[] = VHCC_May::go_ota( $id );
			}
		}

		echo '<div class="wrap"><h1>Máy chấm công &amp; Firmware</h1>';
		self::ve_bao( $bao );

		$m  = VHCC_May::ds_may();
		$ds = ! empty( $m['ok'] ) ? (array) $m['data'] : array();

		/* ---- 1. Máy mất nhịp: để TRÊN CÙNG ---- */
		$dut = array();
		foreach ( $ds as $x ) { if ( empty( $x['song'] ) ) { $dut[] = $x; } }
		if ( $dut ) {
			echo '<div class="notice notice-error"><p><strong>' . count( $dut ) . ' máy không gửi nhịp '
				. 'quá ' . (int) ( VHCC_MayCong::HET_SONG / 60 ) . ' phút.</strong> Máy đứt thì cửa hàng '
				. 'đó đang KHÔNG chấm công lên được — mà lượt bấm vẫn nằm trong đầu đọc, nên lấy lại '
				. 'được bằng lệnh "Tải lại" sau khi máy sống. Kiểm điện, mạng, và SIM còn tiền không.</p><ul>';
			foreach ( $dut as $x ) {
				echo '<li><strong>' . esc_html( $x['cua_hang'] ? $x['cua_hang'] : '(chưa gán cơ sở)' )
					. '</strong> · <code>' . esc_html( $x['serial'] ? $x['serial'] : $x['mac'] ) . '</code> · '
					. ( trim( (string) $x['nhip_luc'] ) !== ''
						? 'nhịp cuối ' . esc_html( $x['nhip_luc'] )
						: 'chưa gửi nhịp nào bao giờ' ) . '</li>';
			}
			echo '</ul></div>';
		} elseif ( $ds ) {
			echo '<p style="color:#046b2d">✔️ Cả ' . count( $ds ) . ' máy đều đang gửi nhịp.</p>';
		}

		/* ---- Danh sách máy ---- */
		echo '<h2>Danh sách máy</h2>';
		if ( ! $ds ) {
			echo '<div class="notice notice-warning"><p>Chưa có máy nào. Máy tự hiện ra ở đây ngay lượt '
				. 'đầu tiên nó gửi nhịp hoặc gửi lượt chấm công — không phải khai tay.</p></div>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Cơ sở</th><th>Serial đầu đọc</th>'
				. '<th>MAC bo</th><th>Nhịp cuối</th><th>Bản firmware</th><th>Đường</th><th>Chờ</th>'
				. '<th>Việc</th></tr></thead><tbody>';
			foreach ( $ds as $x ) {
				$idm = (int) $x['id'];
				echo '<tr><td>' . ( $x['cua_hang']
						? esc_html( $x['cua_hang'] )
						: '<span style="color:#b32d2e">(chưa gán)</span>' ) . '</td>'
					. '<td><code>' . esc_html( $x['serial'] ) . '</code></td>'
					. '<td><code>' . esc_html( $x['mac'] ) . '</code></td>'
					. '<td>' . ( ! empty( $x['con_song'] ) ? '🟢 ' : '🔴 ' ) . esc_html( (string) $x['nhip_luc'] ) . '</td>'
					. '<td>' . esc_html( (string) $x['fw'] ) . '</td>'
					. '<td>' . esc_html( trim( $x['duong'] . ' ' . $x['ip'] . ' ' . $x['song'] ) ) . '</td>'
					. '<td>' . (int) $x['cho'] . '</td>'
					. '<td>';
				echo '<form method="post" style="display:flex;gap:4px;flex-wrap:wrap">';
				echo wp_nonce_field( 'vhcc_may', '_wpnonce', true, false );
				echo '<input type="hidden" name="may_id" value="' . $idm . '" />';
				echo '<select name="coso"><option value="">— chọn cơ sở —</option>';
				foreach ( VHCC_NhanSu::ds_coso() as $cs ) {
					echo '<option value="' . esc_attr( $cs ) . '"' . selected( $cs, $x['cua_hang'], false )
						. '>' . esc_html( $cs ) . '</option>';
				}
				echo '</select>';
				echo '<button class="button" name="vhcc_may" value="gan">Gán</button>';
				echo '<button class="button" name="vhcc_may" value="quet">Quét sổ máy</button>';
				echo '<button class="button" name="vhcc_may" value="dung_tai_lai">Dừng tải lại</button>';
				echo '</form>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p><em>Cơ sở lấy theo <strong>mã thiết bị</strong>, không tin tên máy tự khai. Đổi '
				. 'phần cứng thì hệ thống chỉ GHI DẤU vào cột ghi chú, không tự sửa — "thay bo" và '
				. '"mang bo sang cửa hàng khác" nhìn từ máy chủ giống hệt nhau, đoán sai là chấm công '
				. 'cửa hàng mới chảy vào cơ sở cũ.</em></p>';

			/* ---- Tải lại sổ chấm công từ một máy ---- */
			echo '<h3>Tải lại sổ chấm công từ đầu đọc</h3>';
			echo '<p>Dùng khi máy vừa sống lại sau một đợt mất mạng: lượt bấm còn nằm trong đầu đọc, '
				. 'lệnh này bảo máy đọc lại và đẩy lên. Ghi lại bao nhiêu lần cũng ra một kết quả — '
				. 'ô giờ vào/ra chỉ được nới rộng, không bao giờ bị thu hẹp.</p>';
			echo '<form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">';
			wp_nonce_field( 'vhcc_may' );
			echo '<label>Máy <select name="may_id">';
			foreach ( $ds as $x ) {
				echo '<option value="' . (int) $x['id'] . '">'
					. esc_html( ( $x['cua_hang'] ? $x['cua_hang'] : '(chưa gán)' ) . ' — '
						. ( $x['serial'] ? $x['serial'] : $x['mac'] ) ) . '</option>';
			}
			echo '</select></label>';
			echo '<label>Từ ngày <input type="date" name="tu" required /></label>';
			echo '<label>Đến ngày <input type="date" name="den" required /></label>';
			echo '<label>Chỉ một mã NV <input type="text" name="ma_nv" placeholder="để trống = tất cả" /></label>';
			echo '<button class="button button-primary" name="vhcc_may" value="tai_lai">Tải lại</button>';
			echo '</form><p><em>Tối đa 31 ngày mỗi đợt: máy đẩy từng lượt qua 4G nên khoảng rộng làm '
				. 'nghẽn đường truyền hàng giờ.</em></p>';
		}

		/* ---- Sổ mặt trong máy: chỗ người đã nghỉ vẫn chấm công được ---- */
		$xem = isset( $_GET['soma'] ) ? (int) $_GET['soma'] : 0;
		if ( $ds ) {
			echo '<h3>Sổ mặt đang nằm trong đầu đọc</h3>';
			echo '<p><strong>Người nghỉ việc mà mặt còn trong máy thì VẪN chấm công được</strong>, và '
				. 'bảng lương vẫn tính — không có gì tự báo, vì mỗi bên đều thấy mình đúng. Bấm "Quét sổ '
				. 'máy" ở bảng trên rồi chờ khoảng một phút, sau đó xem ở đây.</p>';
			echo '<form method="get"><input type="hidden" name="page" value="'
				. esc_attr( isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'vhcc-may' )
				. '" /><select name="soma">';
			foreach ( $ds as $x ) {
				echo '<option value="' . (int) $x['id'] . '"' . selected( $xem, (int) $x['id'], false ) . '>'
					. esc_html( ( $x['cua_hang'] ? $x['cua_hang'] : '(chưa gán)' ) . ' — '
						. ( $x['serial'] ? $x['serial'] : $x['mac'] ) ) . '</option>';
			}
			echo '</select> <button class="button">Xem sổ máy này</button></form>';
		}
		if ( $xem > 0 ) {
			$so = VHCC_May::roster( $xem );
			$dc = VHCC_May::doi_chieu_roster( $xem );
			if ( empty( $so['ok'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $so['error'] ) . '</p></div>';
			} elseif ( ! $so['data'] ) {
				echo '<p><em>Máy này chưa đẩy sổ mặt lên lần nào. Bấm "Quét sổ máy" ở bảng trên.</em></p>';
			} else {
				echo '<p>Trong máy có <strong>' . (int) $dc['soMay'] . '</strong> mặt · hồ sơ cơ sở '
					. esc_html( $dc['coso'] ) . ' có <strong>' . (int) $dc['soWeb'] . '</strong> người.</p>';
				if ( $dc['thua'] ) {
					echo '<div class="notice notice-error"><p><strong>' . count( $dc['thua'] )
						. ' mặt còn trong máy mà hồ sơ không cho phép nữa</strong> — những người này vẫn '
						. 'chấm công được:</p><ul>';
					foreach ( $dc['thua'] as $x ) {
						echo '<li><code>' . esc_html( $x['ma'] ) . '</code> ' . esc_html( $x['ten'] )
							. ' — ' . esc_html( $x['vi_sao'] ) . '</li>';
					}
					echo '</ul></div>';
				} else {
					echo '<p style="color:#046b2d">✔️ Không có mặt nào thừa trong máy.</p>';
				}
				if ( $dc['thieu'] ) {
					echo '<p><strong>' . count( $dc['thieu'] ) . ' người có hồ sơ mà chưa có mặt trong máy'
						. '</strong> (người mới chưa lấy mặt): ';
					$ten = array();
					foreach ( $dc['thieu'] as $x ) { $ten[] = $x['ma'] . ' ' . $x['ten']; }
					echo esc_html( implode( ' · ', $ten ) ) . '</p>';
				}
				echo '<table class="widefat striped"><thead><tr><th>Mã NV</th><th>Họ tên</th>'
					. '<th>Có ảnh mặt</th><th>Quét lúc</th></tr></thead><tbody>';
				foreach ( $so['data'] as $r ) {
					echo '<tr><td><code>' . esc_html( $r['ma_nv'] ) . '</code></td><td>'
						. esc_html( $r['ho_ten'] ) . '</td><td>' . ( (int) $r['co_anh'] ? '✔️' : '—' )
						. '</td><td>' . esc_html( (string) $r['cap_nhat'] ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
		}

		/* ---- 2. Lượt bấm chờ gán ---- */
		$cg = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'cho_gan' )
			. " WHERE da_chuyen='' ORDER BY nhan_luc DESC LIMIT 200" );
		echo '<h2>Lượt bấm chờ gán (' . count( $cg ) . ')</h2>';
		echo '<p>Máy chưa gán cơ sở vẫn được nhận và GIỮ lượt bấm ở đây — bỏ là mất công của người '
			. 'thật chỉ vì cái máy chưa được khai. <strong>Gán cơ sở cho máy là các lượt này tự vào '
			. 'bảng chấm công</strong>, không phải gõ tay lại.</p>';
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

		/* ---- 3. Hàng đợi lệnh ---- */
		$lenh = VHCC_May::ds_lenh( '', false, 100 );
		echo '<h2>Lệnh đang chờ xuống máy (' . count( $lenh ) . ')</h2>';
		echo '<p>Hàng đợi này nằm trên chính website — trước 22/08/2026 nó nằm trên Firebase. Lệnh đã '
			. 'gửi mà máy chưa báo xong thì <strong>vẫn được gửi lại</strong>: "đã gửi" không có nghĩa '
			. 'là "máy nhận được", nhất là trên 4G. Firmware có sổ riêng nên nhận lại lệnh cũ thì nó '
			. 'tự bỏ, không có chuyện thêm hai lần một người.</p>';
		if ( ! $lenh ) {
			echo '<p><em>Không có lệnh nào đang chờ.</em></p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Đặt lúc</th><th>Lệnh</th><th>Máy</th>'
				. '<th>Nhân viên</th><th>Khoảng</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
			foreach ( $lenh as $q ) {
				echo '<tr><td>' . esc_html( (string) $q['tao_luc'] ) . '</td>'
					. '<td><code>' . esc_html( $q['action'] ) . '</code></td>'
					. '<td>' . esc_html( $q['cua_hang'] ? $q['cua_hang'] : $q['tram'] ) . '</td>'
					. '<td>' . esc_html( trim( $q['ma_nv'] . ' ' . $q['ho_ten'] ) ) . '</td>'
					. '<td>' . esc_html( trim( $q['tu_gio'] . ' → ' . $q['den_gio'], ' →' ) ) . '</td>'
					. '<td>' . ( VHCC_MayCong::GUI === $q['trang_thai']
						? 'đã gửi ' . esc_html( (string) $q['gui_luc'] ) : 'đang chờ' ) . '</td>'
					. '<td><form method="post">';
				echo wp_nonce_field( 'vhcc_may', '_wpnonce', true, false );
				echo '<input type="hidden" name="op_id" value="' . esc_attr( $q['op_id'] ) . '" />'
					. '<button class="button button-small" name="vhcc_may" value="xoa_lenh">Xoá</button>'
					. '</form></td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ---- 4. Firmware ---- */
		echo '<h2>Cập nhật firmware</h2>';
		$ota = VHCC_May::ota_dang_dat();
		$o   = $ota['data'];
		echo '<p>Lệnh đang đặt cho cả chuỗi: ' . ( '' !== $o['ver']
			? '<strong>' . esc_html( $o['ver'] ) . '</strong> · <code>' . esc_html( $o['url'] ) . '</code>'
				. ( $o['luc'] ? ' · đặt lúc ' . esc_html( $o['luc'] ) : '' )
			: '<em>không có</em>' ) . '</p>';

		$fw = VHCC_May::fw_dang_chay();
		if ( ! empty( $fw['data'] ) ) {
			echo '<p>Máy đang chạy: ';
			$phan = array();
			foreach ( $fw['data'] as $f ) {
				$phan[] = '<strong>' . esc_html( $f['ver'] ) . '</strong> (' . (int) $f['so'] . ' máy)';
			}
			echo implode( ' · ', $phan ) . '</p>';
			if ( count( $fw['data'] ) > 1 ) {
				echo '<p><em>Nhiều bản cùng chạy là bình thường ngay sau một lượt đẩy — máy nhận trong '
					. 'vòng 60 giây rồi tải và khởi động lại. Còn lệch sau vài giờ thì máy đó không tải '
					. 'được: xem lại link .bin và SIM của nó.</em></p>';
			}
		}

		echo '<div class="notice notice-error"><p><strong>Đọc trước khi đẩy.</strong> Lệnh này nạp '
			. 'firmware cho <strong>MỌI máy trong chuỗi</strong>. Link phải là link '
			. '<code>raw</code> của nhánh <code>bin</code> — link <em>release</em> của GitHub trả HTTP 302 '
			. 'rồi chuyển hướng dài ~943 ký tự, mà module 4G chết ở khoảng 532 ký tự: đẩy link đó là '
			. 'mọi máy 4G KHÔNG BAO GIỜ tải được, tức mất luôn đường sửa từ xa và phải đi từng cửa hàng '
			. 'cắm USB. Hệ thống chặn link sai dạng, nhưng <strong>hãy thử một máy trước</strong> — '
			. 'bản hỏng đẩy cho cả chuỗi thì không còn đường gọi về.</p></div>';

		echo '<form method="post">';
		wp_nonce_field( 'vhcc_may' );
		echo '<table class="form-table">'
			. self::o( 'ver', 'Phiên bản *', '' )
			. self::o( 'url', 'Link .bin (raw, nhánh bin) *', '' );
		if ( $ds ) {
			echo '<tr><th scope="row">Thử riêng một máy</th><td><select name="may_id">';
			foreach ( $ds as $x ) {
				echo '<option value="' . (int) $x['id'] . '">'
					. esc_html( ( $x['cua_hang'] ? $x['cua_hang'] : '(chưa gán)' ) . ' — '
						. ( $x['serial'] ? $x['serial'] : $x['mac'] ) ) . '</option>';
			}
			echo '</select> <button class="button" name="vhcc_may" value="ota_may">Đặt riêng cho máy này</button>'
				. '<p class="description">Không cần gõ xác nhận — đây chính là bước nên làm trước.</p></td></tr>';
		}
		echo self::o( 'xac_nhan', 'Gõ đúng chữ DONG Y để đẩy cả chuỗi', '' )
			. '</table>';
		echo '<p><button class="button button-primary" name="vhcc_may" value="ota">Đẩy cập nhật cho cả chuỗi</button> '
			. '<button class="button" name="vhcc_may" value="go_ota">Gỡ lệnh cập nhật của cả chuỗi</button></p></form>';
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

	/**
	 * HỘP NẠP NGƯỜI DÙNG TỪ DỮ LIỆU CŨ.
	 *
	 * Anh Thắng: *"pin nằm ở dữ liệu cũ chứ"* và *"nếu dữ liệu lỗi, cho anh kéo riêng từng cơ sở
	 * (bao gồm tên đăng nhập và pin) là dữ liệu chấm công cũ cho nhanh"*.
	 *
	 * Hai đường, cố ý để đường DÁN lên trước:
	 *   1. **Dán thẳng từ Google Sheets** — không phụ thuộc cầu nối Apps Script còn sống hay
	 *      không, và đúng nghĩa "riêng từng cơ sở" vì chỉ bôi đen cơ sở đó.
	 *   2. **Nạp từ kho đã có trên host** — sổ Phân quyền đã kéo về, hoặc bảng của plugin chi
	 *      phí. Nhanh hơn nếu kho đó còn đủ.
	 *
	 * Cả hai đều có **Xem trước** — nạp nhầm 26 cửa hàng vào một danh sách rồi dọn tay thì lâu
	 * hơn nhiều so với bấm xem trước một cái.
	 */
	/**
	 * KỂ LẠI MỘT LƯỢT NẠP — và kể luôn phần KHÔNG nạp được.
	 *
	 * 🔴 Chỗ này quan trọng hơn cái nút. Nạp 26 cửa hàng mà im lặng bỏ 4 người PIN hỏng thì cuối
	 *    tháng 4 người đó không có công, và không ai dựng lại được vì màn hình đã báo "xong".
	 *    Nên: bỏ ai, vì sao, KÊU ĐÍCH DANH.
	 *
	 * 🔴 PIN yếu/đã lộ thì VẪN NẠP (không thì khoá đúng người đang dùng nó ra ngoài) nhưng phải
	 *    kêu tên để đi đổi.
	 */
	/** Kể lại một lượt nạp .csv hồ sơ nhân viên — kể cả cột KHÔNG lấy được. */
	protected static function bao_csv( $r, $xem ) {
		$them = (int) $r['them']; $sua = (int) $r['sua'];
		echo '<div class="notice notice-' . ( $them + $sua ? 'success' : 'warning' ) . '"><p>';
		echo $xem ? '<b>Xem trước — CHƯA ghi gì.</b> ' : '<b>Đã nạp hồ sơ.</b> ';
		echo 'Đọc ' . (int) $r['so_dong'] . ' dòng: ';
		echo $xem
			? ( 'sẽ thêm <b>' . $them . '</b> người mới, cập nhật <b>' . $sua . '</b> người đã có.' )
			: ( 'thêm <b>' . $them . '</b> người mới, cập nhật <b>' . $sua . '</b> người đã có.' );
		if ( ! empty( $r['coso'] ) ) {
			echo ' Chỉ nhận cơ sở <b>' . esc_html( $r['coso'] ) . '</b>';
			if ( ! empty( $r['lech'] ) ) { echo ', bỏ qua ' . (int) $r['lech'] . ' người của cơ sở khác'; }
			echo '.';
		}
		echo '</p>';
		echo '<p style="margin:4px 0 0"><b>Cột lấy được:</b> '
			. esc_html( implode( ' · ', (array) $r['cot'] ) ) . '</p>';
		echo '</div>';

		/* "Lấy đủ luôn các cột" — nên cột KHÔNG nhận ra phải kể tên, không im lặng bỏ. */
		if ( ! empty( $r['cot_la'] ) ) {
			echo '<div class="notice notice-warning"><p><b>' . count( (array) $r['cot_la'] )
				. ' cột KHÔNG nhận ra, đã bỏ qua:</b> ' . esc_html( implode( ' · ', (array) $r['cot_la'] ) )
				. '</p><p class="description">Đổi tên tiêu đề trong Sheet cho khớp (ví dụ '
				. '<code>Số điện thoại</code>, <code>Ngày sinh</code>, <code>Lương cơ bản</code>) rồi tải lại, '
				. 'hoặc báo em thêm tên gọi đó vào bản đồ cột.</p></div>';
		}
		if ( ! empty( $r['bo'] ) ) {
			echo '<div class="notice notice-error"><p><b>' . count( (array) $r['bo'] )
				. ' dòng KHÔNG nạp được:</b></p><ul style="margin:0 0 8px 18px;list-style:disc">';
			foreach ( (array) $r['bo'] as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; }
			echo '</ul></div>';
		}
		if ( ! empty( $r['canh'] ) ) {
			echo '<div class="notice notice-warning"><ul style="margin:8px 0 8px 18px;list-style:disc">';
			foreach ( (array) $r['canh'] as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; }
			echo '</ul></div>';
		}
		if ( ! $xem && ( $them + $sua ) ) {
			echo '<div class="notice notice-info"><p>Hồ sơ đã có. Giờ sang <b>Cách 3</b> → dòng '
				. '<b>Hồ sơ Nhân sự trên host</b> → chọn cơ sở → <b>Nạp</b>, để cột '
				. '<i>PIN đăng nhập</i> thành tài khoản đăng nhập được.</p></div>';
		}
	}

	protected static function bao_nap( $r ) {
		$xem = ( 0 === strpos( (string) $r['viec'], 'xem_' ) );
		if ( empty( $r['ok'] ) ) {
			echo '<div class="notice notice-error"><p><b>Không nạp được.</b> '
				. wp_kses( isset( $r['error'] ) ? $r['error'] : 'Lỗi không rõ',
					array( 'b' => array(), 'code' => array(), 'i' => array() ) ) . '</p></div>';
			return;
		}
		if ( 'nap_csv' === $r['viec'] || 'xem_csv' === $r['viec'] ) { self::bao_csv( $r, $xem ); return; }
		$so = (int) $r['them'];
		echo '<div class="notice notice-' . ( $so ? 'success' : 'warning' ) . '"><p>';
		echo $xem ? '<b>Xem trước — CHƯA ghi gì.</b> ' : '<b>Đã nạp.</b> ';
		echo $xem
			? ( $so ? 'Sẽ thêm <b>' . $so . '</b> người.' : 'Không có ai mới để thêm.' )
			: ( $so ? 'Thêm <b>' . $so . '</b> người.' : 'Không thêm ai — tất cả đã có trong danh sách rồi.' );
		if ( ! empty( $r['coso'] ) ) {
			echo ' Chỉ nhận cơ sở <b>' . esc_html( $r['coso'] ) . '</b>';
			if ( ! empty( $r['lech'] ) ) { echo ', bỏ qua ' . (int) $r['lech'] . ' người của cơ sở khác'; }
			echo '.';
		}
		echo '</p>';
		if ( $so && ! empty( $r['ten'] ) ) {
			echo '<p style="margin:4px 0 0">' . esc_html( implode( ' · ', array_slice( (array) $r['ten'], 0, 40 ) ) )
				. ( count( (array) $r['ten'] ) > 40 ? ' …' : '' ) . '</p>';
		}
		echo '</div>';

		if ( ! empty( $r['bo'] ) ) {
			echo '<div class="notice notice-error"><p><b>' . count( (array) $r['bo'] )
				. ' dòng KHÔNG nạp được</b> — mấy người này sẽ không đăng nhập được cho tới khi sửa:</p><ul style="margin:0 0 8px 18px;list-style:disc">';
			foreach ( (array) $r['bo'] as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; }
			echo '</ul></div>';
		}
		/* 🔴 Nạp xong mà KHÔNG AI vào được là hỏng im lặng — sổ ghi "Chức vụ: Máy tự động" chứ
		   không ghi vai trò đăng nhập, nên cả sổ rơi về bậc thấp nhất. Phải nói thẳng. */
		if ( ! empty( $r['vt_trong'] ) ) {
			$het = empty( $r['vao'] );
			echo '<div class="notice notice-' . ( $het ? 'error' : 'warning' ) . '"><p><b>'
				. (int) $r['vt_trong'] . ' người sổ cũ KHÔNG ghi vai trò đăng nhập</b> — đã đặt thành <b>'
				. esc_html( (string) $r['vt_mac_dinh'] ) . '</b>.';
			if ( $het ) {
				echo ' <b style="color:#b32d2e">Hiện KHÔNG AI đăng nhập được.</b> Chọn lại '
					. '<i>Vai trò nếu sổ không ghi</i> rồi nạp lại, hoặc tích thêm vai trò ở mục '
					. '<b>Vai trò vào được</b> bên dưới.';
			}
			echo '</p></div>';
		}
		if ( ! empty( $r['yeu'] ) ) {
			echo '<div class="notice notice-warning"><p><b>' . count( (array) $r['yeu'] )
				. ' người đang dùng PIN dễ đoán hoặc đã bị lộ</b> — vẫn nạp để họ đăng nhập được, '
				. 'nhưng nên đổi sớm: ' . esc_html( implode( ' · ', (array) $r['yeu'] ) ) . '</p></div>';
		}
	}

	/**
	 * ĐỌC FILE .CSV VỪA TẢI LÊN — và chối mọi thứ không phải file văn bản.
	 *
	 * ⚠️ KHÔNG dùng wp_handle_upload / không lưu file vào uploads. File này chỉ cần đọc một lần
	 *    rồi vứt; ghi nó xuống thư mục công khai là để lại một danh sách PIN + CCCD của cả chuỗi
	 *    ở chỗ ai có link cũng tải được.
	 * ⚠️ Chặn theo KÍCH THƯỚC trước khi đọc: một file lớn nạp vào bộ nhớ là giết cả trang admin.
	 */
	protected static function doc_csv_gui_len() {
		if ( ! isset( $_FILES['tep'] ) || ! is_array( $_FILES['tep'] ) ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn file nào.' );
		}
		$f = $_FILES['tep'];
		$loi = isset( $f['error'] ) ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $loi ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn file nào.' );
		}
		if ( UPLOAD_ERR_INI_SIZE === $loi || UPLOAD_ERR_FORM_SIZE === $loi ) {
			return array( 'ok' => false, 'error' => 'File lớn hơn mức hosting cho tải lên. '
				. 'Xuất riêng từng cơ sở rồi tải từng file — vừa nhẹ vừa dễ soi dòng hỏng.' );
		}
		if ( UPLOAD_ERR_OK !== $loi ) {
			return array( 'ok' => false, 'error' => 'Tải file lên không xong (mã lỗi ' . $loi . ').' );
		}
		$duong = isset( $f['tmp_name'] ) ? (string) $f['tmp_name'] : '';
		if ( '' === $duong || ! is_uploaded_file( $duong ) ) {
			return array( 'ok' => false, 'error' => 'File tải lên không hợp lệ.' );
		}
		$co = (int) filesize( $duong );
		if ( $co > 8 * 1024 * 1024 ) {
			return array( 'ok' => false, 'error' => 'File lớn hơn 8 MB. Xuất riêng từng cơ sở rồi tải từng file.' );
		}
		$ten = isset( $f['name'] ) ? strtolower( (string) $f['name'] ) : '';
		if ( ! preg_match( '/\.(csv|tsv|txt)$/', $ten ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ nhận file .csv / .tsv / .txt. '
				. 'File .xlsx thì trong Google Sheets chọn File → Tải xuống → '
				. '<b>Giá trị được phân tách bằng dấu phẩy (.csv)</b>.' );
		}
		$noi_dung = file_get_contents( $duong );
		if ( false === $noi_dung || '' === trim( (string) $noi_dung ) ) {
			return array( 'ok' => false, 'error' => 'File rỗng.' );
		}
		/* Sheets xuất UTF-8; Excel bản Việt hay xuất Windows-1258/1252. Đọc nhầm bảng mã thì tên
		   người thành ký tự lạ mà vẫn "nạp thành công" — chuyển về UTF-8 ngay tại đây. */
		if ( ! mb_check_encoding( $noi_dung, 'UTF-8' ) ) {
			$noi_dung = mb_convert_encoding( $noi_dung, 'UTF-8', 'Windows-1258, Windows-1252, ISO-8859-1' );
		}
		return array( 'ok' => true, 'noi_dung' => (string) $noi_dung, 'ten' => $ten );
	}

	/**
	 * Ô chọn VAI TRÒ MẶC ĐỊNH cho những dòng không đọc ra được vai trò.
	 *
	 * 🔴 Vì sao phải có: sổ nhân viên của anh Thắng có cột "Chức vụ" mang giá trị *"Máy tự động"*
	 *    — đó là chức vụ, KHÔNG phải vai trò đăng nhập. Không có ô này thì cả sổ rơi về "Nhân
	 *    viên", tức là nạp xong KHÔNG AI đăng nhập được, mà màn hình vẫn báo "đã nạp 26 người".
	 *    Đúng kiểu hỏng im lặng phải chặn.
	 */
	protected static function o_vai_tro( $form ) {
		$cho = VHCC_Auth::vai_tro_vao();
		$h   = '<label>Vai trò nếu sổ không ghi<br><select name="vt" form="' . esc_attr( $form )
			. '" style="max-width:200px">';
		foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $vt ) {
			$h .= '<option value="' . esc_attr( $vt ) . '"' . selected( $vt, 'Nhân viên', false ) . '>'
				. esc_html( $vt ) . ( in_array( $vt, $cho, true ) ? '' : ' — không vào được' ) . '</option>';
		}
		return $h . '</select></label>';
	}

	protected static function hop_nap_cu( &$form_roi ) {
		$kho = VHCC_NguoiDung::do_kho_cu();

		/* Anh Thắng: *"mọi việc anh thao tác trên web giao diện bên ngoài hết, không làm bên
		   trong wp-admin"*. Nên chỗ này chỉ đường sang đó, đừng bắt anh tìm. */
		echo '<hr style="margin:18px 0"><div class="notice notice-info inline" style="margin:8px 0"><p>'
			. '<b>Làm ngoài web tiện hơn.</b> Trang <a href="' . esc_url( VHCC_Web::url() ) . '" target="_blank"><b>'
			. esc_html( VHCC_Web::url() ) . '</b></a> làm được đủ việc dưới đây — nạp .csv, sửa hồ sơ, '
			. 'khai tài khoản — mà <b>không cần tài khoản WordPress</b>, chỉ cần PIN chấm công của '
			. 'Admin hoặc Quản lý. Ngoài đó còn có <b>xem trước từng ô đổi gì</b> và <b>nút hoàn tác</b>.'
			. '</p></div>';
		echo '<h3 style="margin:0 0 6px">📥 Nạp người dùng từ dữ liệu cũ</h3>';
		echo '<p class="description" style="margin-bottom:10px">Giữ nguyên PIN mọi người đang dùng — '
			. 'không phải cấp lại lần hai. Chỉ <b>thêm</b>, không sửa và không xoá ai đang có, nên '
			. 'bấm hai lần cũng không nhân đôi danh sách.</p>';

		/* ---- Đường 0: TẢI FILE .CSV — đường chính cho cả sổ nhân viên ---- */
		/* ⚠️ Thẻ <form> phải nằm NGOÀI form Cài đặt (gom vào $form_roi, in ra sau khi form kia
		   đóng), còn ô thì trỏ về nó bằng thuộc tính form="…". Lồng <form> trong <form> là HTML
		   sai: trình duyệt bỏ form trong, ô `required` của nó rơi vào form Cài đặt, và anh Thắng
		   KHÔNG bấm Lưu được nữa nếu chưa chọn file. Có phép thử ghim đúng chỗ này. */
		$form_roi .= '<form method="post" enctype="multipart/form-data" id="vhcc-csv-nd">'
			. wp_nonce_field( 'vhcc_nd', '_wpnonce', true, false ) . '</form>';
		echo '<div style="max-width:760px;border:2px solid #2271b1;border-radius:4px;padding:12px;margin-bottom:12px">';
		echo '<b>Cách 1 — tải file .csv của sổ NHÂN VIÊN</b> '
			. '<span class="description">(lấy ĐỦ mọi cột, ghi vào hồ sơ Nhân sự)</span>';
		echo '<p class="description" style="margin:6px 0">Trong Google Sheets: <b>File → Tải xuống → '
			. 'Giá trị được phân tách bằng dấu phẩy (.csv)</b>. Nhận đủ các cột '
			. '<i>Mã NV · Họ tên · Cửa hàng · Trạng thái đồng bộ · Cập nhật · CCCD · Chức vụ · Nhiệm vụ · '
			. 'Cơ sở phụ · PIN đăng nhập</i> và mọi cột hồ sơ khác. Thứ tự cột nào cũng được; '
			. 'cột nào không nhận ra sẽ được <b>kể tên ra</b> chứ không lặng lẽ bỏ.</p>';
		echo '<div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">';
		echo '<label>File<br><input type="file" name="tep" form="vhcc-csv-nd" accept=".csv,.tsv,.txt" required /></label>';
		echo '<label>Chỉ nhận cơ sở<br><input name="coso" form="vhcc-csv-nd" style="width:170px" placeholder="trống = nhận hết" /></label>';
		echo '<button class="button" form="vhcc-csv-nd" name="vhcc_nd" value="xem_csv">Xem trước</button>';
		echo '<button class="button button-primary" form="vhcc-csv-nd" name="vhcc_nd" value="nap_csv">Nạp vào hồ sơ</button>';
		echo '</div>';
		echo '<p class="description" style="margin:8px 0 0">🔴 <b>Ô trống KHÔNG ghi đè.</b> Sheet của anh '
			. 'đang thu gọn nhiều nhóm cột — xuất thiếu cột rồi nạp đè thì ô trống sẽ <i>không</i> xoá '
			. 'mất số tài khoản, lương, CCCD đang có. Chỉ ô CÓ giá trị mới được ghi. '
			. 'Nạp lại nhiều lần thoải mái: khớp theo <b>Mã NV</b> nên là cập nhật, không nhân đôi.</p>';
		echo '<p class="description" style="margin:4px 0 0">Nạp xong, sang <b>Cách 3</b> bấm nạp từ '
			. '<b>Hồ sơ Nhân sự</b> để biến cột <i>PIN đăng nhập</i> thành tài khoản đăng nhập.</p>';
		echo '</div>';

		/* ---- Đường 1: dán từ Sheets ---- */
		$form_roi .= '<form method="post" id="vhcc-dan-nd">'
			. wp_nonce_field( 'vhcc_nd', '_wpnonce', true, false ) . '</form>';
		echo '<div style="max-width:760px;border:1px solid #c3c4c7;border-radius:4px;padding:12px;margin-bottom:12px">';
		echo '<b>Cách 2 — dán thẳng từ Google Sheets</b> <span class="description">(chỉ tài khoản đăng nhập: họ tên + PIN)</span>';
		echo '<p class="description" style="margin:6px 0">Bôi đen các cột <b>Họ tên</b> và <b>PIN</b> '
			. '(kèm <b>Vai trò</b>, <b>Cơ sở</b> nếu có) của <b>một cơ sở</b> trong Sheet → Ctrl+C → dán vào ô dưới. '
			. 'Thứ tự cột nào cũng được, có hay không có dòng tiêu đề đều được.</p>';
		echo '<textarea name="dan" form="vhcc-dan-nd" rows="6" style="width:100%;font-family:monospace" '
			. 'placeholder="Họ và Tên&#9;PIN&#9;Vai trò&#9;Cơ sở&#10;Nguyễn Văn A&#9;246813&#9;Quản lý&#9;TUTU_BT"></textarea>';
		echo '<div style="display:flex;gap:8px;align-items:flex-end;margin-top:8px;flex-wrap:wrap">';
		echo '<label>Chỉ nhận cơ sở<br><input name="coso" form="vhcc-dan-nd" style="width:170px" '
			. 'placeholder="trống = nhận hết" /></label>';
		echo self::o_vai_tro( 'vhcc-dan-nd' );
		echo '<button class="button" form="vhcc-dan-nd" name="vhcc_nd" value="xem_dan">Xem trước</button>';
		echo '<button class="button button-primary" form="vhcc-dan-nd" name="vhcc_nd" value="nap_dan">Nạp vào danh sách</button>';
		echo '</div>';
		echo '<p class="description" style="margin:8px 0 0">⚠️ Google Sheets coi PIN là <b>số</b>, nên '
			. '<code>0123</code> bị cắt thành <code>123</code> và <code>246813</code> có thể ra '
			. '<code>246813.0</code>. Đuôi <code>.0</code> thì hệ thống tự cắt; còn số 0 ở đầu bị mất thì '
			. 'phải định dạng cột đó thành <b>Văn bản</b> trong Sheet rồi chép lại.</p>';
		echo '</div>';

		/* ---- Đường 2: kho đã có sẵn trên host ---- */
		echo '<div style="max-width:760px;border:1px solid #c3c4c7;border-radius:4px;padding:12px">';
		echo '<b>Cách 3 — nạp tài khoản đăng nhập từ kho đã có trên host</b>';
		echo '<table class="widefat striped" style="margin:8px 0"><thead><tr><th>Kho</th>'
			. '<th style="width:90px">Có</th><th style="width:120px">Vào được</th>'
			. '<th style="width:230px">Nạp riêng cơ sở</th><th style="width:170px"></th></tr></thead><tbody>';
		foreach ( VHCC_NguoiDung::NGUON_CU as $tu => $nhan ) {
			$k  = isset( $kho[ $tu ] ) ? $kho[ $tu ] : array( 'co' => 0, 'vao' => 0, 'loi' => '' );
			$id = 'vhcc-napcu-' . $tu;
			$form_roi .= '<form method="post" id="' . esc_attr( $id ) . '">'
				. wp_nonce_field( 'vhcc_nd', '_wpnonce', true, false )
				. '<input type="hidden" name="tu" value="' . esc_attr( $tu ) . '" /></form>';
			echo '<tr><td>' . esc_html( $nhan );
			if ( '' !== $k['loi'] ) {
				echo '<br><span style="color:#b32d2e">' . esc_html( $k['loi'] ) . '</span>';
			}
			echo '</td><td><b>' . (int) $k['co'] . '</b></td><td>';
			echo ( $k['vao'] ? '<b>' . (int) $k['vao'] . '</b>' : '<span style="color:#b32d2e">0</span>' );
			echo '</td><td>';
			if ( $k['co'] ) {
				echo '<select name="coso" form="' . esc_attr( $id ) . '" style="max-width:220px">';
				echo '<option value="">— cả chuỗi (' . (int) $k['co'] . ' người) —</option>';
				foreach ( VHCC_NguoiDung::ds_coso_cu( $tu ) as $cs => $dem ) {
					echo '<option value="' . esc_attr( $cs ) . '">'
						. esc_html( '' === $cs ? '(không khai cơ sở)' : $cs )
						. ' — ' . (int) $dem['co'] . ' người, ' . (int) $dem['pin'] . ' có PIN dùng được'
						. '</option>';
				}
				echo '</select>';
			} else {
				echo '<span class="description">—</span>';
			}
			echo '</td><td>';
			if ( $k['co'] ) {
				echo self::o_vai_tro( $id ) . '<br>';
				echo '<button class="button button-small" form="' . esc_attr( $id ) . '" name="vhcc_nd" value="xem_cu">Xem trước</button> '
					. '<button class="button button-small button-primary" form="' . esc_attr( $id ) . '" name="vhcc_nd" value="nap_cu">Nạp</button>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">Cột <b>Vào được</b> đếm theo nhóm vai trò đang cho vào ('
			. esc_html( implode( ' · ', VHCC_Auth::vai_tro_vao() ) ) . '). Kho có người mà '
			. '<b>0 vào được</b> thì cứ nạp — nạp xong tích thêm vai trò ở mục <b>Vai trò vào được</b> '
			. 'bên dưới là mọi người vào được ngay, khỏi gõ tay lại.</p>';
		echo '</div>';
	}

	public static function handle_post() {
		/* "Tôi đã ghi lại PIN lần đầu" -> xoá HẲN khỏi cơ sở dữ liệu. Để nó nằm đó mãi thì
		   một PIN Admin còn dùng được nằm sẵn trong bảng options, ai đọc được database là
		   vào thẳng. */
		if ( isset( $_POST['vhcc_viec'] ) && 'quen_pin_lan_dau' === $_POST['vhcc_viec']
			&& current_user_can( self::CAP ) ) {
			VHCC_NguoiDung::quen_pin_lan_dau();
		}

		/* Danh sách người dùng riêng — xử TRƯỚC lúc vẽ, nhờ vậy bảng vẽ ra là bảng sau khi sửa. */
		if ( isset( $_POST['vhcc_nd'] ) && current_user_can( self::CAP ) ) {
			check_admin_referer( 'vhcc_nd' );
			$v_nd = sanitize_text_field( wp_unslash( $_POST['vhcc_nd'] ) );
			if ( 'luu' === $v_nd ) {
				$r_nd = VHCC_NguoiDung::luu(
					isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : '',
					isset( $_POST['ten'] ) ? wp_unslash( $_POST['ten'] ) : '',
					isset( $_POST['pin'] ) ? wp_unslash( $_POST['pin'] ) : '',
					isset( $_POST['vai_tro'] ) ? wp_unslash( $_POST['vai_tro'] ) : '',
					isset( $_POST['coso'] ) ? wp_unslash( $_POST['coso'] ) : '' );
			} elseif ( 'xoa' === $v_nd ) {
				$r_nd = VHCC_NguoiDung::xoa( isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : '' );
			} elseif ( 'nap_csv' === $v_nd || 'xem_csv' === $v_nd ) {
				$r_nd = self::doc_csv_gui_len();
				if ( ! empty( $r_nd['ok'] ) ) {
					$r_nd = VHCC_NapCsv::nap( $r_nd['noi_dung'], 'xem_csv' === $v_nd,
						isset( $_POST['coso'] ) ? sanitize_text_field( wp_unslash( $_POST['coso'] ) ) : '' );
				}
				$r_nd['viec'] = $v_nd;
			} elseif ( 'nap_dan' === $v_nd || 'xem_dan' === $v_nd ) {
				/* Dán từ Google Sheets. KHÔNG sanitize_text_field: hàm đó bóp xuống một dòng,
				   mà cả bảng dán vào đây là NHIỀU DÒNG — bóp xong còn đúng một dòng và người
				   dùng chỉ thấy "nạp được 1 người" mà không hiểu 25 người kia đi đâu. */
				$r_nd = VHCC_NguoiDung::nap_dan(
					isset( $_POST['dan'] ) ? (string) wp_unslash( $_POST['dan'] ) : '',
					'xem_dan' === $v_nd,
					isset( $_POST['coso'] ) ? sanitize_text_field( wp_unslash( $_POST['coso'] ) ) : '',
					isset( $_POST['vt'] ) ? sanitize_text_field( wp_unslash( $_POST['vt'] ) ) : '' );
				$r_nd['viec'] = $v_nd;
			} elseif ( 'nap_cu' === $v_nd || 'xem_cu' === $v_nd ) {
				$r_nd = VHCC_NguoiDung::nap_tu_cu(
					isset( $_POST['tu'] ) ? sanitize_text_field( wp_unslash( $_POST['tu'] ) ) : '',
					'xem_cu' === $v_nd,
					isset( $_POST['coso'] ) ? sanitize_text_field( wp_unslash( $_POST['coso'] ) ) : '',
					isset( $_POST['vt'] ) ? sanitize_text_field( wp_unslash( $_POST['vt'] ) ) : '' );
				$r_nd['viec'] = $v_nd;
			} else {
				$r_nd = array( 'ok' => false, 'error' => 'Việc không rõ.' );
			}
			set_transient( 'vhcc_nd_' . get_current_user_id(), $r_nd, 60 );
			self::ve( 'nd' );
		}

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
			if ( $ch['mien'] !== '' ) { update_option( 'vhcc_exec_mien', $ch['mien'] ); }
			set_transient( 'vhcc_sua_url_' . get_current_user_id(), $ch['sua'], 120 );

			$nguon = isset( $_POST['vhcc_nguon'] ) ? sanitize_text_field( wp_unslash( $_POST['vhcc_nguon'] ) ) : 'chung';
			if ( ! in_array( $nguon, array( 'chung', 'rieng', 'app', 'ho_so' ), true ) ) { $nguon = 'chung'; }
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

		if ( $action === 'chandoan' ) {
			set_transient( 'vhcc_cd_' . get_current_user_id(), VHCC_CauNoi::chan_doan(), 120 );
			self::ve( 'chandoan' );
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

		if ( $msg === 'nd' ) {
			$bao_nd = get_transient( 'vhcc_nd_' . get_current_user_id() );
			delete_transient( 'vhcc_nd_' . get_current_user_id() );
			if ( is_array( $bao_nd ) && isset( $bao_nd['viec'] ) ) {
				self::bao_nap( $bao_nd );
			} elseif ( is_array( $bao_nd ) ) {
				echo '<div class="notice notice-' . ( empty( $bao_nd['ok'] ) ? 'error' : 'success' ) . '"><p>'
					. esc_html( empty( $bao_nd['ok'] )
						? ( isset( $bao_nd['error'] ) ? $bao_nd['error'] : 'Lỗi không rõ' )
						: ( isset( $bao_nd['thong_bao'] ) ? $bao_nd['thong_bao'] : 'Đã lưu.' ) ) . '</p></div>';
			}
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
		if ( $msg === 'chandoan' ) {
			$cd = get_transient( 'vhcc_cd_' . get_current_user_id() );
			delete_transient( 'vhcc_cd_' . get_current_user_id() );
			if ( is_array( $cd ) && ! empty( $cd['ok'] ) ) {
				echo '<div class="notice notice-info"><p><b>Chẩn đoán địa chỉ /exec</b> — mã triển khai <code>'
					. esc_html( substr( (string) $cd['ma_trien_khai'], 0, 12 ) ) . '…</code></p>';
				echo '<table class="widefat striped" style="max-width:900px;margin:6px 0"><thead><tr>'
					. '<th>Dạng địa chỉ</th><th>Mã HTTP</th><th>Trả về gì</th></tr></thead><tbody>';
				foreach ( (array) $cd['thu'] as $x ) {
					echo '<tr><td>' . esc_html( $x['ten'] ) . '</td><td>' . (int) $x['ma'] . '</td><td>'
						. esc_html( $x['ket'] ) . '</td></tr>';
				}
				echo '</tbody></table>';
				echo '<p><b>Kết luận:</b> ' . wp_kses_post( $cd['ket_luan'] ) . '</p></div>';
			} elseif ( is_array( $cd ) ) {
				echo '<div class="notice notice-error"><p><b>Không chẩn đoán được:</b> '
					. esc_html( isset( $cd['error'] ) ? $cd['error'] : '?' ) . '</p></div>';
			}
		}

		echo '<h2>Mở hệ thống chấm công</h2><p><a class="button button-primary" target="_blank" href="'
			. esc_url( VHCC_Trang::url() ) . '">' . esc_html( VHCC_Trang::url() ) . '</a></p>';

		echo '<div class="notice notice-info"><p><b>Plugin này không giữ dữ liệu chấm công.</b> '
			. 'Hợp đồng vẫn nằm trong Google Sheet và toàn bộ nghiệp vụ vẫn ở app Apps Script. '
			. 'WordPress chỉ lo cổng PIN, giữ khoá bí mật và phục vụ giao diện gốc.</p></div>';

		/* ============================================================================
		 * 🔴 KHÔNG BAO GIỜ ĐƯỢC LỒNG <form> TRONG <form>.
		 *
		 * HTML không cho phép, và trình duyệt KHÔNG báo lỗi — nó lặng lẽ VỨT thẻ <form>
		 * bên trong đi, rồi gán hết ô nhập của form con vào form CHA. Hậu quả thật, anh
		 * Thắng gặp ngày 22/08/2026: bấm "Lưu cài đặt" để khai đường dẫn app thì trình
		 * duyệt hiện "Please fill out this field" ở ô "Họ tên" tận cuối trang — ô chẳng
		 * liên quan gì, chỉ vì nó mang `required` và đã bị gộp vào form cài đặt.
		 *
		 * Còn một hậu quả nặng hơn, chưa kịp xảy ra vì `required` chặn trước: ô ẩn
		 * `vhcc_nd=xoa` của từng dòng người dùng cũng bị gộp vào, mà `vhcc_nd` được xử
		 * TRƯỚC `vhcc_action` — nghĩa là mỗi lần Lưu cài đặt là chạy luôn một lượt
		 * xoá/thêm người dùng, với ô ẩn của dòng CUỐI CÙNG thắng.
		 *
		 * Cách chữa: form con nằm RIÊNG, đặt sau `</form>` của form cài đặt; nút bấm và
		 * ô nhập nằm đúng chỗ cũ trên màn hình nhưng trỏ về form của mình bằng thuộc
		 * tính `form="…"` của HTML5. Nhìn y hệt, mà không còn lồng nhau.
		 * ============================================================================ */
		$form_roi = '';

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

		/* PIN admin: hiện TRẠNG THÁI, không hiện giá trị. Thiếu nó thì mọi việc gọi sang app gốc
		   (kéo dữ liệu, màn Máy/OTA) đều chết bằng một câu của app gốc nói về "phiên đăng nhập" —
		   câu đó không dẫn về đây được, nên phải nói ở đây. */
		$pin_ad = VHCC_May::pin();
		echo '<tr><th scope="row">PIN admin gọi app gốc</th><td>';
		if ( '' === $pin_ad ) {
			echo '<b style="color:#b32d2e">Chưa khai.</b> Thiếu nó thì không kéo được dữ liệu cũ và '
				. 'màn Máy &amp; Firmware không gọi được gì.';
		} else {
			echo '<span style="color:#046b2d">✔️ Đã khai</span> (' . strlen( $pin_ad ) . ' ký tự'
				. ( defined( 'VHCC_PIN_ADMIN' ) ? ', từ wp-config.php' : ', từ cơ sở dữ liệu' ) . ').';
		}
		echo '<p class="description">Khai trong <code>wp-config.php</code>: '
			. '<code>define( \'VHCC_PIN_ADMIN\', \'…\' );</code> — phải bằng ĐÚNG một PIN vai trò '
			. '<b>Admin</b> trong sheet <code>PhanQuyen</code> của app gốc. Đây KHÔNG phải PIN đăng nhập '
			. 'trang chấm công, cũng không phải khoá cầu nối.</p></td></tr>';

		echo '<tr><th scope="row">Khoá cầu nối (WEB_KEY)</th><td><code style="user-select:all">'
			. esc_html( $khoa ) . '</code>'
			. '<p class="description">Dán đúng chuỗi này vào Apps Script → Project Settings → Script Properties, '
			. 'tên thuộc tính <code>WEB_KEY</code>. Đây là thứ duy nhất chặn người lạ ghi vào sheet chấm công — '
			. 'đừng dán vào chỗ nào khác.</p></td></tr>';

		/* PIN LẦN ĐẦU — chỉ hiện Ở ĐÂY, không bao giờ ở trang công khai.
		   Cài xong mà chưa có ai vào được thì trang chấm công đứng ở cổng PIN, không đường
		   nào mở. Plugin tự khai một Admin lúc cài; đây là chỗ DUY NHẤT đọc được PIN đó, và
		   quản trị bấm "đã ghi lại" là xoá hẳn khỏi cơ sở dữ liệu. */
		/* Nạp được sổ PIN cũ thì kể ra — đó mới là đường đúng, PIN bịa ra chỉ là đường cùng. */
		$nap_ld = VHCC_NguoiDung::mo_duong_nap();
		if ( ! empty( $nap_ld['so'] ) ) {
			echo '<tr><th scope="row">📥 Đã nạp sổ PIN cũ</th><td>'
				. 'Lúc cài, chưa ai đăng nhập được nên plugin đã nạp <b>' . (int) $nap_ld['so']
				. '</b> người từ sổ Phân quyền của app gốc sang danh sách riêng — <b>giữ nguyên PIN '
				. 'mọi người đang dùng</b>, không phải cấp lại lần hai.';
			if ( ! empty( $nap_ld['bo'] ) ) {
				echo '<p class="description" style="color:#b32d2e"><b>' . count( (array) $nap_ld['bo'] )
					. ' dòng không nạp được:</b> ' . esc_html( implode( ' · ', (array) $nap_ld['bo'] ) ) . '</p>';
			}
			if ( ! empty( $nap_ld['yeu'] ) ) {
				echo '<p class="description"><b>PIN nên đổi sớm (dễ đoán hoặc đã lộ):</b> '
					. esc_html( implode( ' · ', (array) $nap_ld['yeu'] ) ) . '</p>';
			}
			echo '</td></tr>';
		}

		$pin_ld = VHCC_NguoiDung::pin_lan_dau();
		if ( '' !== $pin_ld ) {
			/* Lúc gieo, nguồn phải chuyển sang "danh sách riêng", không thì PIN vừa khai vẫn
			   không vào được. Nói thẳng ra ở đây: giấu một thay đổi cấu hình là cách chắc nhất
			   để nửa năm sau không ai hiểu vì sao danh sách người dùng "biến mất". */
			$doi_nguon = '';
			$ng_cu     = VHCC_NguoiDung::gieo_doi_nguon();
			if ( '' !== $ng_cu ) {
				$doi_nguon = '<p class="description">Lúc khai, plugin đã chuyển <b>Nguồn người dùng</b> từ "'
					. esc_html( 'chung' === $ng_cu ? 'dùng chung với Vận hành chi phí' : 'sổ PhanQuyen của app gốc' )
					. '" sang <b>danh sách riêng</b> — vì tài khoản trên nằm ở danh sách riêng. '
					. 'Danh sách cũ <b>không mất gì</b>: chọn lại ô bên dưới là quay về ngay.</p>';
			}
			echo '<tr><th scope="row" style="color:#b91c1c">⚠ PIN đăng nhập lần đầu</th><td>'
				. '<code style="user-select:all;font-size:20px;letter-spacing:3px">' . esc_html( $pin_ld ) . '</code>'
				. ' &nbsp; <button type="submit" name="vhcc_viec" value="quen_pin_lan_dau" class="button">Tôi đã ghi lại — ẩn đi</button>'
				. '<p class="description">Lúc cài, <b>không tìm được sổ PIN cũ nào</b> (sổ Phân quyền chưa kéo về, '
				. 'hoặc kéo về rồi mà không ai dùng được), nên plugin khai tạm một tài khoản <b>Admin</b> để có đường vào. '
				. 'Vào được rồi thì <b>nạp sổ PIN cũ</b> ở mục 📥 bên dưới — mọi người đăng nhập bằng đúng PIN họ vẫn dùng. '
				. '<b>Ghi lại rồi đổi PIN ngay</b> ở phần "Danh sách riêng" bên dưới. '
				. 'Đây là chỗ duy nhất xem được — trang chấm công KHÔNG bao giờ in PIN ra.</p>'
				. $doi_nguon . '</td></tr>';
		}

		echo '<tr><th scope="row">Nguồn người dùng &amp; PIN</th><td>';
		echo '<label><input type="radio" name="vhcc_nguon" value="chung"' . checked( $nguon, 'chung', false ) . '> '
			. 'Dùng chung với plugin <b>Vận hành chi phí</b> (khuyến nghị)</label><br>';
		echo '<label><input type="radio" name="vhcc_nguon" value="rieng"' . checked( $nguon, 'rieng', false ) . '> '
			. 'Danh sách riêng của plugin này</label><br>';
		/* Nguồn thứ ba, thêm 22/08/2026. Anh Thắng: *"mỗi nhân viên đều có pin hết, sao không
		   đăng nhập được"* — ai cũng có PIN, nhưng PIN đó nằm ở sổ PhanQuyen của app gốc. Kéo sổ
		   đó về rồi đọc thẳng nó là khỏi cấp PIN lần thứ hai cho mấy chục người. */
		$so_pq = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'phan_quyen' ) );
		echo '<label><input type="radio" name="vhcc_nguon" value="ho_so"' . checked( $nguon, 'ho_so', false ) . '> '
			. '<b>Hồ sơ Nhân sự</b> — đọc THẲNG cột "PIN đăng nhập" và "Vai trò" của hồ sơ '
			. '(khuyến nghị: sửa ở đâu có hiệu lực ngay ở đó, không phải nạp sang danh sách thứ hai)</label><br>';
		echo '<label><input type="radio" name="vhcc_nguon" value="app"' . checked( $nguon, 'app', false ) . '> '
			. '<b>Phân quyền của app gốc</b> — dùng đúng PIN mọi người đang đăng nhập app cũ ('
			. $so_pq . ' dòng đã kéo về)</label>';
		echo '<p class="description">Dùng chung thì thêm/sửa/xoá nhân sự vẫn làm ở tab ⚙️ Cấu hình của app chi phí — '
			. 'khai một lần cho cả hai hệ thống. Khai hai nơi là sớm muộn xoá một nơi quên nơi kia.</p>';
		/* DANH SÁCH RIÊNG — màn khai ngay tại chỗ.
		   Anh Thắng: *"anh để chỉ plugin này thôi mà"*. Plugin phải chạy được MỘT MÌNH, không
		   bắt cài kèm plugin chi phí chỉ để có chỗ khai người dùng. Bản trước chọn "riêng" là
		   tắc: option `vhcc_nguoidung` chỉ được ĐỌC, không màn nào ghi — chọn xong không ai đăng
		   nhập được, và màn hình chỉ biết khuyên đi cài plugin khác. */
		if ( $nguon === 'rieng' ) {
			$ds_r  = VHCC_NguoiDung::ds();
			$vao_r = VHCC_NguoiDung::so_vao_duoc( $ds_r );
			$cho_r = VHCC_Auth::vai_tro_vao();

			if ( ! $ds_r ) {
				echo '<div class="notice notice-warning inline" style="margin:8px 0"><p>'
					. '<b>Danh sách đang rỗng — chưa ai đăng nhập được.</b> Thêm người đầu tiên ở ô '
					. 'ngay dưới, nhớ chọn vai trò nằm trong nhóm vào được ('
					. esc_html( implode( ' · ', $cho_r ) ) . ').</p></div>';
			} elseif ( ! $vao_r ) {
				echo '<div class="notice notice-error inline" style="margin:8px 0"><p>'
					. '<b>Có ' . count( $ds_r ) . ' người nhưng KHÔNG AI vào được</b> — vai trò của họ '
					. 'đều nằm ngoài nhóm cho vào. Sửa vai trò, hoặc tích thêm vai trò ở mục '
					. '<b>Vai trò vào được</b> bên dưới.</p></div>';
			}

			if ( $ds_r ) {
				echo '<table class="widefat striped" style="max-width:760px;margin:8px 0"><thead><tr>'
					. '<th>Họ tên</th><th>Vai trò</th><th>Cơ sở</th><th>PIN</th><th></th>'
					. '</tr></thead><tbody>';
				foreach ( $ds_r as $u_r ) {
					$duoc_r = in_array( $u_r['vaiTro'], $cho_r, true );
					echo '<tr><td><b>' . esc_html( $u_r['ten'] ) . '</b></td><td>'
						. esc_html( $u_r['vaiTro'] )
						. ( $duoc_r ? '' : ' <span style="color:#b32d2e">(không vào được)</span>' )
						. '</td><td>' . esc_html( $u_r['coso'] ) . '</td>'
						/* ⚠️ CHỈ SỐ CHỮ SỐ. Không bao giờ in PIN — ảnh màn hình đi khắp nơi. */
						. '<td>' . strlen( $u_r['pin'] ) . ' số</td><td>';
					$id_f = 'vhcc-xoa-nd-' . preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $u_r['id'] );
					$form_roi .= '<form method="post" id="' . esc_attr( $id_f ) . '">'
						. wp_nonce_field( 'vhcc_nd', '_wpnonce', true, false )
						. '<input type="hidden" name="vhcc_nd" value="xoa" />'
						. '<input type="hidden" name="id" value="' . esc_attr( $u_r['id'] ) . '" />'
						. '</form>';
					echo '<button class="button button-small" form="' . esc_attr( $id_f ) . '">Xoá</button>'
						. '</td></tr>';
				}
				echo '</tbody></table>';
			}

			$form_roi .= '<form method="post" id="vhcc-them-nd">'
				. wp_nonce_field( 'vhcc_nd', '_wpnonce', true, false )
				. '<input type="hidden" name="vhcc_nd" value="luu" /></form>';
			echo '<div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:8px 0">';
			echo '<label>Họ tên<br><input name="ten" form="vhcc-them-nd" required style="width:190px" /></label>';
			echo '<label>PIN (4–8 số)<br><input name="pin" form="vhcc-them-nd" inputmode="numeric" required style="width:120px" /></label>';
			echo '<label>Vai trò<br><select name="vai_tro" form="vhcc-them-nd">';
			foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $vt_r ) {
				echo '<option value="' . esc_attr( $vt_r ) . '"' . selected( $vt_r, 'Admin', false ) . '>'
					. esc_html( $vt_r ) . ( in_array( $vt_r, $cho_r, true ) ? '' : ' — không vào được' )
					. '</option>';
			}
			echo '</select></label>';
			echo '<label>Cơ sở<br><input name="coso" form="vhcc-them-nd" style="width:160px" placeholder="trống = cả chuỗi" /></label>';
			echo '<button class="button button-primary" form="vhcc-them-nd">Thêm người</button></div>';
			echo '<p class="description">Bảng không in PIN, chỉ in số chữ số. Quên PIN thì xoá dòng đó '
				. 'rồi thêm lại. PIN quá dễ đoán hoặc đã bị lộ sẽ bị chặn.</p>';

			self::hop_nap_cu( $form_roi );
		}
		if ( $nguon === 'app' ) {
			$ds_pq = VHCC_Auth::users();
			$vao_pq = 0;
			$cho_pq = VHCC_Auth::vai_tro_vao();
			foreach ( (array) $ds_pq as $x_pq ) {
				if ( in_array( $x_pq['vaiTro'], $cho_pq, true ) ) { $vao_pq++; }
			}
			if ( ! $so_pq ) {
				echo '<div class="notice notice-error inline" style="margin:8px 0"><p>'
					. '<b>Chưa kéo sổ phân quyền về.</b> Vào <a href="'
					. esc_url( admin_url( 'admin.php?page=vhcc-quyen' ) ) . '">Phân quyền &amp; PIN</a> '
					. '→ <b>Kéo sổ phân quyền từ app gốc</b>, rồi quay lại đây.</p></div>';
			} else {
				echo '<p>Đọc được <b>' . count( (array) $ds_pq ) . '</b> người, trong đó <b>' . $vao_pq
					. '</b> người vào được (' . esc_html( implode( ' · ', $cho_pq ) ) . ').</p>';
				if ( ! $vao_pq ) {
					echo '<div class="notice notice-error inline" style="margin:8px 0"><p>'
						. '<b>Không ai vào được.</b> Sổ phân quyền của app gốc phần lớn là Cửa hàng trưởng '
						. 'và Nhân viên — hai vai trò đó mặc định KHÔNG vào được vì chấm công là căn cứ '
						. 'tính lương. Tích thêm vai trò ở mục <b>Vai trò vào được</b> ngay dưới.</p></div>';
				}
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

				/* AI VÀO ĐƯỢC, VÀ PIN DÀI MẤY SỐ.
				 *
				 * 🔴 Vì sao cần: gõ PIN bị chối thì màn đăng nhập chỉ nói "PIN không đúng hoặc chưa
				 *    được cấp" — đúng nhưng vô dụng, vì không biết PIN nào mới đúng. Bảng này trả lời
				 *    được câu đó mà KHÔNG in PIN ra: chỉ tên, vai trò và SỐ CHỮ SỐ. Biết PIN dài 6 số
				 *    là đủ để loại ngay chuyện gõ 4 số.
				 *
				 * ⚠️ TUYỆT ĐỐI không in PIN. Đây là màn quản trị nhưng ảnh màn hình thì đi khắp nơi —
				 *    đã mất một khoá cầu nối đúng vì một ảnh gửi qua chat.
				 *
				 * Và bắt luôn cái bẫy hay gặp nhất: PIN có số 0 ở đầu. Google Sheets coi `0123` là
				 * SỐ nên lưu thành `123` — ba chữ số, dưới ngưỡng 4–8, nên không bao giờ đăng nhập
				 * được, mà nhìn bảng cấu hình thì vẫn thấy "có PIN".
				 */
				$pin_hong = array();
				$hang_vao = array();
				foreach ( $u as $x ) {
					$pin = (string) $x['pin'];
					if ( $pin === '' || ! preg_match( '/^\d{4,8}$/', $pin ) ) {
						$pin_hong[] = array( 'ten' => $x['ten'], 'vt' => $x['vaiTro'],
							'vi' => ( $pin === '' ? 'chưa có PIN' : strlen( $pin ) . ' ký tự, không phải 4–8 chữ số' ) );
					}
					if ( in_array( $x['vaiTro'], $duoc_vao, true ) ) { $hang_vao[] = $x; }
				}

				if ( $hang_vao ) {
					echo '<table class="widefat striped" style="max-width:620px;margin:8px 0"><thead><tr>'
						. '<th>Vào được</th><th>Vai trò</th><th>Cơ sở</th><th>PIN dài</th></tr></thead><tbody>';
					foreach ( $hang_vao as $x ) {
						$pin = (string) $x['pin'];
						$dai = ( $pin === '' ) ? '<span style="color:#b32d2e">chưa có</span>'
							: ( preg_match( '/^\d{4,8}$/', $pin )
								? strlen( $pin ) . ' số'
								: '<span style="color:#b32d2e">' . strlen( $pin ) . ' ký tự — không dùng được</span>' );
						echo '<tr><td><b>' . esc_html( $x['ten'] ) . '</b></td><td>' . esc_html( $x['vaiTro'] )
							. '</td><td>' . esc_html( $x['coso'] ) . '</td><td>' . wp_kses_post( $dai ) . '</td></tr>';
					}
					echo '</tbody></table>';
					echo '<p class="description">Bảng này KHÔNG in PIN — chỉ số chữ số, đủ để biết mình '
						. 'đang gõ thiếu hay thừa. Sửa PIN ở tab ⚙️ Cấu hình của app chi phí.<br>'
						. '⚠️ <b>Chỉ những PIN trong bảng này mới vào được trang chấm công.</b> PIN ở màn '
						. '<b>Phân quyền &amp; PIN</b> là của app gốc, KHÔNG dùng để đăng nhập trang web; '
						. 'hồ sơ ở màn <b>Nhân sự</b> cũng không phải tài khoản đăng nhập.</p>';
				}

				if ( $pin_hong ) {
					echo '<div class="notice notice-warning inline" style="margin:8px 0"><p><b>'
						. count( $pin_hong ) . ' người có PIN KHÔNG DÙNG ĐƯỢC</b> (phải là 4–8 chữ số):</p><ul style="margin-left:18px;list-style:disc">';
					foreach ( $pin_hong as $x ) {
						echo '<li>' . esc_html( $x['ten'] ) . ' (' . esc_html( $x['vt'] ) . ') — '
							. esc_html( $x['vi'] ) . '</li>';
					}
					echo '</ul><p>Hay gặp nhất: <b>PIN có số 0 ở đầu</b>. Google Sheets coi <code>0123</code> '
						. 'là số nên lưu thành <code>123</code> — ba chữ số, không bao giờ đăng nhập được. '
						. 'Cách chữa: đặt PIN không bắt đầu bằng 0, hoặc định dạng ô đó thành Văn bản.</p></div>';
				}
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
		/* Form con nằm ở đây, NGOÀI form cài đặt — xem lời giải thích ở chỗ khai $form_roi. */
		echo $form_roi;   // phpcs:ignore WordPress.Security.EscapeOutput -- đã thoát từng mảnh ở trên

		echo '<hr><h2>Bảo trì</h2><p>';
		foreach ( array(
			'thu'      => 'Thử cầu nối',
			'chandoan' => 'Chẩn đoán địa chỉ',
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
