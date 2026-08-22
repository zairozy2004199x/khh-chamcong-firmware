<?php
/**
 * MÀN HÌNH — Ghế massage.
 *
 * ⚠️ MỌI MÀN Ở ĐÂY ĐỀU MỎNG: gác quyền, đọc biểu mẫu, gọi lớp nghiệp vụ, hiện kết quả. Không một
 *    dòng luật tiền nào, không một câu ghi bảng nào. Hai bản luật là sớm muộn lệch nhau, và lúc
 *    lệch thì màn cho bấm mà lớp dưới chặn — hoặc tệ hơn, ngược lại.
 *
 * ⚠️ KHÔNG CÓ PIN RIÊNG. Bản Apps Script gác bằng `DASHBOARD_PIN` ghi thẳng trong mã, mà mã thì
 *    nằm ở repo công khai — ai đọc được là bật/tắt được ghế và xoá được doanh thu. Ở đây màn nằm
 *    trong wp-admin: người xem phải đăng nhập WordPress, và quyền thì WordPress đã quản. Bớt
 *    được một bí mật là bớt một chỗ lộ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Admin {

	const CAP = 'manage_options';

	/**
	 * Địa chỉ màn đối soát — Trang Vận Hành đọc hàm này để dựng thẻ.
	 *
	 * ⚠️ Trỏ vào wp-admin, KHÁC hai app kia (chúng có trang công khai gác bằng PIN riêng). Nghĩa
	 *    là người xem phải có tài khoản WordPress. Đó là CỐ Ý: màn này xem được doanh thu, bật
	 *    tắt được ghế và xoá được bản ghi tiền — không phải thứ để sau một mã PIN sáu số.
	 */
	public static function app_url() {
		return admin_url( 'admin.php?page=vhg' );
	}

	public static function menu() {
		add_menu_page( 'Ghế Massage', 'Ghế Massage', self::CAP, 'vhg', array( __CLASS__, 'trang_thu' ), 'dashicons-money-alt', 27 );
		add_submenu_page( 'vhg', 'Đối soát doanh thu', 'Đối soát doanh thu', self::CAP, 'vhg', array( __CLASS__, 'trang_thu' ) );
		add_submenu_page( 'vhg', 'Máy & cơ sở', 'Máy & cơ sở', self::CAP, 'vhg-may', array( __CLASS__, 'trang_may' ) );
		add_submenu_page( 'vhg', 'Nhận tiền & nhật ký', 'Nhận tiền & nhật ký', self::CAP, 'vhg-cong', array( __CLASS__, 'trang_cong' ) );
		add_submenu_page( 'vhg', 'Trang ngoài & PIN', 'Trang ngoài & PIN', self::CAP, 'vhg-trang', array( __CLASS__, 'trang_ngoai' ) );
	}

	// ======================================================================= tiện ích chung

	private static function gac() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
	}

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

	public static function tien( $n ) {
		return number_format( (int) $n, 0, ',', '.' ) . 'đ';
	}

	private static function ky() {
		$k = isset( $_GET['ky'] ) ? sanitize_key( wp_unslash( $_GET['ky'] ) ) : 'today';
		return in_array( $k, array( 'today', 'week', 'month', 'year', 'all' ), true ) ? $k : 'today';
	}

	private static function chon_ky( $trang ) {
		$ky = self::ky();
		$ten = array( 'today' => 'Hôm nay', 'week' => 'Tuần này', 'month' => 'Tháng này',
			'year' => 'Năm nay', 'all' => 'Tất cả' );
		echo '<p>';
		foreach ( $ten as $k => $t ) {
			$url = add_query_arg( array( 'page' => $trang, 'ky' => $k ), admin_url( 'admin.php' ) );
			echo '<a class="button' . ( $k === $ky ? ' button-primary' : '' ) . '" href="'
				. esc_url( $url ) . '">' . esc_html( $t ) . '</a> ';
		}
		echo '</p>';
		return $ky;
	}

	// ======================================================================= 1. ĐỐI SOÁT

	public static function trang_thu() {
		self::gac();
		$bao = array();
		if ( isset( $_POST['vhg'] ) ) {
			check_admin_referer( 'vhg' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhg'] ) );
			$nguoi = wp_get_current_user()->display_name;
			if ( 'tien_mat' === $viec ) {
				$bao[] = VHG_Thu::thu_tien_mat( wp_unslash( $_POST['ma_may'] ), wp_unslash( $_POST['so_tien'] ), $nguoi );
			} elseif ( 'bat' === $viec ) {
				$bao[] = VHG_May::dat_lenh( wp_unslash( $_POST['ma_may'] ), 'on',
					isset( $_POST['phut'] ) ? wp_unslash( $_POST['phut'] ) : 0, $nguoi,
					isset( $_POST['ly_do'] ) ? wp_unslash( $_POST['ly_do'] ) : '' );
			} elseif ( 'tat' === $viec ) {
				$bao[] = VHG_May::dat_lenh( wp_unslash( $_POST['ma_may'] ), 'off', 0, $nguoi, '' );
			} elseif ( 'huy_gd' === $viec ) {
				$bao[] = VHG_Thu::huy( wp_unslash( $_POST['ref'] ), 'gỡ tay bởi ' . $nguoi );
			} elseif ( 'bo_huy_gd' === $viec ) {
				$bao[] = VHG_Thu::bo_huy( wp_unslash( $_POST['ref'] ) );
			}
		}

		echo '<div class="wrap"><h1>Đối soát doanh thu ghế massage</h1>';
		self::ve_bao( $bao );
		self::canh_bao_mui_gio();
		$ky = self::chon_ky( 'vhg' );
		$t = VHG_Thu::tong_hop( $ky );

		/* ---- 1. Máy mất nhịp: để TRÊN CÙNG. Máy đứt thì khách quét QR, tiền vào, mà ghế không
		     chạy — người ta đứng ở quầy cãi nhau ngay lúc đó, không phải cuối tháng mới biết. */
		$may = VHG_May::ds_may();
		$dut = array();
		foreach ( $may as $m ) { if ( empty( $m['con_song'] ) ) { $dut[] = $m; } }
		if ( $dut ) {
			echo '<div class="notice notice-error"><p><strong>' . count( $dut ) . ' ghế không gửi nhịp '
				. 'quá ' . (int) ( VHG_May::HET_SONG / 60 ) . ' phút.</strong> Ghế đứt mà khách vẫn quét được '
				. 'tem QR trên ghế: <strong>tiền vào nhưng ghế không chạy</strong>. Kiểm điện và mạng ngay.</p><ul>';
			foreach ( $dut as $m ) {
				echo '<li><strong>' . esc_html( $m['ma'] ) . '</strong> · '
					. esc_html( $m['coso_ten'] ? $m['coso_ten'] : '(chưa gán cơ sở)' ) . ' · '
					. ( trim( (string) $m['nhip_luc'] ) !== ''
						? 'nhịp cuối ' . esc_html( $m['nhip_luc'] ) : 'chưa gửi nhịp nào bao giờ' )
					. '</li>';
			}
			echo '</ul></div>';
		} elseif ( $may ) {
			echo '<p style="color:#046b2d">✔️ Cả ' . count( $may ) . ' ghế đều đang gửi nhịp.</p>';
		}

		/* ---- 2. Tiền đã vào mà ghế chưa nhận ---- */
		$cho = VHG_May::ds_cho( true, 200 );
		if ( $cho ) {
			echo '<div class="notice notice-warning"><p><strong>' . count( $cho ) . ' lượt đã trả tiền '
				. 'mà ghế chưa nhận.</strong> Bình thường thì ghế lấy trong ~10 giây. Còn đọng lâu nghĩa là '
				. 'ghế đó đang đứt mạng — tiền đã vào sổ, nhưng khách chưa được massage.</p><ul>';
			foreach ( array_slice( $cho, 0, 20 ) as $c ) {
				echo '<li>' . esc_html( $c['tao_luc'] ) . ' · ghế <strong>' . esc_html( $c['ma_may'] )
					. '</strong> · ' . esc_html( self::tien( $c['so_tien'] ) )
					. ' · mã <code>' . esc_html( $c['ma_lenh'] ) . '</code></li>';
			}
			echo '</ul></div>';
		}

		/* ---- KPI ---- */
		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0">';
		foreach ( array(
			array( 'Tổng doanh thu', self::tien( $t['tong'] ), $t['so_luot'] . ' lượt' ),
			array( 'Chuyển khoản (QR)', self::tien( $t['qr'] ), $t['qr_luot'] . ' lượt' ),
			array( 'Tiền mặt', self::tien( $t['tien_mat'] ), $t['tien_mat_luot'] . ' lượt' ),
			array( 'Đang chờ ghế nhận', count( $cho ), 'đã trả, chưa chạy' ),
		) as $k ) {
			echo '<div style="border:1px solid #c3c4c7;background:#fff;border-radius:8px;padding:12px 16px;min-width:170px">'
				. '<div style="font-size:11px;text-transform:uppercase;color:#646970">' . esc_html( $k[0] ) . '</div>'
				. '<div style="font-size:22px;font-weight:700;margin-top:4px">' . esc_html( $k[1] ) . '</div>'
				. '<div style="font-size:12px;color:#646970">' . esc_html( $k[2] ) . '</div></div>';
		}
		echo '</div>';

		/* ---- Theo cơ sở ---- */
		echo '<h2>Theo cơ sở</h2><table class="widefat striped"><thead><tr><th>Cơ sở</th>'
			. '<th>Số máy</th><th>Lượt</th><th>QR</th><th>Tiền mặt</th><th>Tổng</th></tr></thead><tbody>';
		if ( ! $t['theo_coso'] ) { echo '<tr><td colspan="6"><em>Chưa có doanh thu kỳ này.</em></td></tr>'; }
		foreach ( $t['theo_coso'] as $c ) {
			echo '<tr><td><strong>' . esc_html( $c['coso'] ) . '</strong></td><td>' . (int) $c['so_may']
				. '</td><td>' . (int) $c['so_luot'] . '</td><td>' . esc_html( self::tien( $c['qr'] ) )
				. '</td><td>' . esc_html( self::tien( $c['tien_mat'] ) ) . '</td><td><strong>'
				. esc_html( self::tien( $c['tong'] ) ) . '</strong></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Theo máy ---- */
		echo '<h2>Theo máy</h2><table class="widefat striped"><thead><tr><th>Máy</th><th>Cơ sở</th>'
			. '<th>Lượt</th><th>QR</th><th>Tiền mặt</th><th>Tổng</th></tr></thead><tbody>';
		if ( ! $t['theo_may'] ) { echo '<tr><td colspan="6"><em>Chưa có doanh thu kỳ này.</em></td></tr>'; }
		foreach ( $t['theo_may'] as $m ) {
			echo '<tr><td><strong>' . esc_html( $m['may'] ) . '</strong></td><td>' . esc_html( $m['coso'] )
				. '</td><td>' . (int) $m['so_luot'] . '</td><td>' . esc_html( self::tien( $m['qr'] ) )
				. '</td><td>' . esc_html( self::tien( $m['tien_mat'] ) ) . '</td><td><strong>'
				. esc_html( self::tien( $m['tong'] ) ) . '</strong></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Tình trạng ghế + điều khiển ---- */
		echo '<h2>Tình trạng ghế</h2>';
		echo '<p><em>Bật tay là <strong>cho không một lượt massage</strong> — hệ thống ghi lại ai bấm và '
			. 'lúc nào, để cuối tháng còn giải thích được vì sao một ghế chạy nhiều hơn số tiền thu.</em></p>';
		echo '<table class="widefat striped"><thead><tr><th>Máy</th><th>Cơ sở</th><th>Trạng thái</th>'
			. '<th>Còn lại</th><th>Chờ</th><th>Việc</th></tr></thead><tbody>';
		if ( ! $may ) { echo '<tr><td colspan="6"><em>Chưa khai máy nào. Sang màn "Máy &amp; cơ sở".</em></td></tr>'; }
		foreach ( $may as $m ) {
			$tt = empty( $m['con_song'] ) ? '🔴 Mất kết nối'
				: ( 'running' === $m['trang_thai'] ? '▶️ Đang chạy'
					: ( 'wait_pay' === $m['trang_thai'] ? '⏳ Chờ thanh toán' : '🟢 Rảnh' ) );
			echo '<tr><td><strong>' . esc_html( $m['ma'] ) . '</strong></td>'
				. '<td>' . esc_html( $m['coso_ten'] ? $m['coso_ten'] : '(chưa gán)' ) . '</td>'
				. '<td>' . esc_html( $tt ) . '</td>'
				. '<td>' . ( 'running' === $m['trang_thai'] && ! empty( $m['con_song'] )
					? esc_html( gmdate( 'i:s', max( 0, (int) $m['con_lai'] ) ) ) : '' ) . '</td>'
				. '<td>' . (int) $m['cho'] . '</td><td>';
			echo '<form method="post" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="ma_may" value="' . esc_attr( $m['ma'] ) . '" />';
			echo '<input type="number" name="phut" min="1" max="60" value="' . (int) $m['phut']
				. '" style="width:64px" title="Số phút" />';
			echo '<input type="text" name="ly_do" placeholder="lý do" style="width:130px" />';
			echo '<button class="button" name="vhg" value="bat">Bật</button>';
			echo '<button class="button" name="vhg" value="tat">Tắt</button>';
			echo '<input type="number" name="so_tien" min="1000" step="1000" value="' . (int) $m['gia']
				. '" style="width:90px" title="Số tiền thu tay" />';
			echo '<button class="button" name="vhg" value="tien_mat">Thu tiền mặt</button>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Giao dịch gần đây ---- */
		echo '<h2>Giao dịch gần đây</h2><table class="widefat striped"><thead><tr><th>Thời gian</th>'
			. '<th>Máy</th><th>Nguồn</th><th>Số tiền</th><th>Nội dung</th><th>Mã tham chiếu</th>'
			. '<th></th></tr></thead><tbody>';
		$ds = VHG_Thu::ds( $ky, 100 );
		if ( ! $ds ) { echo '<tr><td colspan="7"><em>Chưa có giao dịch kỳ này.</em></td></tr>'; }
		foreach ( $ds as $r ) {
			$ten = '' !== $r['ma_may'] ? $r['ma_may'] : ( '' !== $r['ten_khai'] ? $r['ten_khai'] : '—' );
			echo '<tr><td>' . esc_html( $r['luc'] ) . '</td><td>' . esc_html( $ten ) . '</td>'
				. '<td>' . esc_html( VHG_Thu::TIEN_MAT === $r['nguon'] ? 'Tiền mặt' : strtoupper( $r['nguon'] ) )
				. '</td><td>' . esc_html( self::tien( $r['so_tien'] ) ) . '</td>'
				. '<td>' . esc_html( $r['noi_dung'] ) . '</td>'
				. '<td><code>' . esc_html( $r['ref'] ) . '</code></td>'
				. '<td><form method="post" onsubmit="return confirm(\'Gỡ giao dịch này khỏi báo cáo? '
				. 'Dòng vẫn được giữ lại, bỏ huỷ được.\')">'
				. wp_nonce_field( 'vhg', '_wpnonce', true, false )
				. '<input type="hidden" name="ref" value="' . esc_attr( $r['ref'] ) . '" />'
				. '<button class="button button-small" name="vhg" value="huy_gd">Huỷ</button>'
				. '</form></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Đã huỷ: có huỷ thì phải xem lại được, không thì huỷ thành mất tăm ---- */
		$dh = VHG_Thu::ds_huy( 100 );
		if ( $dh ) {
			echo '<h2>Đã gỡ khỏi báo cáo (' . count( $dh ) . ')</h2>';
			echo '<p><em>Những dòng này KHÔNG cộng vào doanh thu, nhưng vẫn nằm trong cơ sở dữ liệu — '
				. 'vừa để trả lời câu "sao hôm đó lệch", vừa để cùng giao dịch ấy bắn lại lần nữa không '
				. 'vào sổ như một khoản mới.</em></p>';
			echo '<table class="widefat striped"><thead><tr><th>Thời gian</th><th>Số tiền</th>'
				. '<th>Nội dung</th><th>Lý do</th><th>Mã tham chiếu</th><th></th></tr></thead><tbody>';
			foreach ( $dh as $r ) {
				echo '<tr><td>' . esc_html( $r['luc'] ) . '</td>'
					. '<td>' . esc_html( self::tien( $r['so_tien'] ) ) . '</td>'
					. '<td>' . esc_html( $r['noi_dung'] ) . '</td>'
					. '<td>' . esc_html( $r['huy_ly_do'] ) . '</td>'
					. '<td><code>' . esc_html( $r['ref'] ) . '</code></td>'
					. '<td><form method="post">'
					. wp_nonce_field( 'vhg', '_wpnonce', true, false )
					. '<input type="hidden" name="ref" value="' . esc_attr( $r['ref'] ) . '" />'
					. '<button class="button button-small" name="vhg" value="bo_huy_gd">Đưa lại vào sổ</button>'
					. '</form></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	/**
	 * MÚI GIỜ CỦA WEBSITE PHẢI LÀ GIỜ VIỆT NAM.
	 *
	 * 🔴 Ngày 22/08/2026: SePay ghi giao dịch lúc 20:08:26, website ghi 13:08:29 — lệch đúng 7
	 *    tiếng, vì WordPress mới cài mặc định chạy giờ UTC. Hai hậu quả:
	 *      · Đối soát với sao kê ngân hàng thành mò kim: cùng một giao dịch, hai giờ khác nhau.
	 *      · Báo cáo "Hôm nay" cắt sai ngày. Lượt lúc 0h–7h sáng rơi về ngày HÔM TRƯỚC, nên chốt
	 *        ca đêm ra số khác chốt sổ ngân hàng, mà không có gì trên màn hình nói vì sao.
	 *
	 * Từ bản này giao dịch qua webhook ưu tiên lấy giờ của BÊN GỬI, nên phần lớn đã đúng dù máy
	 * chủ sai. Nhưng tiền mặt và giao dịch không kèm giờ vẫn lấy giờ máy chủ — nên vẫn phải sửa,
	 * và màn hình phải nói ra chứ không để người dùng tự phát hiện bằng cách so từng dòng.
	 */
	private static function canh_bao_mui_gio() {
		$lech = (int) ( wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) ) );
		if ( 7 * 3600 === $lech ) { return; }
		$gio = round( $lech / 3600, 1 );
		echo '<div class="notice notice-warning"><p><b>Múi giờ của website đang là UTC'
			. esc_html( ( $gio >= 0 ? '+' : '' ) . $gio ) . ', không phải giờ Việt Nam (UTC+7).</b> '
			. 'Giao dịch tiền mặt và mọi mốc "Hôm nay / Tuần này" sẽ lệch '
			. esc_html( (string) abs( 7 - $gio ) ) . ' tiếng so với sao kê ngân hàng.</p>'
			. '<p>Sửa ở <a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">Settings → General → '
			. 'Timezone</a>, chọn <code>Ho Chi Minh</code>. Sửa xong thì các dòng CŨ vẫn giữ giờ cũ — '
			. 'chỉ dòng mới mới đúng.</p></div>';
	}

	// ======================================================================= 2. MÁY & CƠ SỞ

	public static function trang_may() {
		self::gac();
		$bao = array();
		if ( isset( $_POST['vhg'] ) ) {
			check_admin_referer( 'vhg' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhg'] ) );
			if ( 'coso' === $viec ) {
				$bao[] = VHG_May::luu_coso( isset( $_POST['coso_id'] ) ? (int) $_POST['coso_id'] : 0,
					wp_unslash( $_POST['ten'] ) );
			} elseif ( 'xoa_coso' === $viec ) {
				$bao[] = VHG_May::xoa_coso( (int) $_POST['coso_id'] );
			} elseif ( 'may' === $viec ) {
				$bao[] = VHG_May::luu_may( array(
					'ma' => wp_unslash( $_POST['ma'] ), 'coso_id' => (int) $_POST['coso_id'],
					'mac' => isset( $_POST['mac'] ) ? wp_unslash( $_POST['mac'] ) : '',
					'gia' => wp_unslash( $_POST['gia'] ), 'phut' => wp_unslash( $_POST['phut'] ),
					'so_tk' => wp_unslash( $_POST['so_tk'] ), 'ten_tk' => wp_unslash( $_POST['ten_tk'] ),
					'bank_bin' => wp_unslash( $_POST['bank_bin'] ), 'ten_khai' => wp_unslash( $_POST['ten_khai'] ) ) );
			} elseif ( 'xoa_may' === $viec ) {
				$bao[] = VHG_May::xoa_may( wp_unslash( $_POST['ma'] ) );
			} elseif ( 'gan_ma' === $viec ) {
				$bao[] = VHG_May::gan_ma( wp_unslash( $_POST['ma_cu'] ), wp_unslash( $_POST['ma_moi'] ),
					isset( $_POST['coso_id'] ) ? (int) $_POST['coso_id'] : null );
			} elseif ( 'nhan_tien' === $viec ) {
				$bao[] = VHG_May::luu_nhan_tien( wp_unslash( $_POST['bin'] ),
					wp_unslash( $_POST['so_tk'] ), wp_unslash( $_POST['ten_tk'] ) );
			} elseif ( 'ty_le' === $viec ) {
				$bao[] = VHG_May::luu_ty_le( wp_unslash( $_POST['gia_c'] ), wp_unslash( $_POST['phut_c'] ) );
			} elseif ( 'bo_rieng' === $viec ) {
				$bao[] = VHG_May::bo_ty_le_rieng();
			} elseif ( 'menh_gia' === $viec ) {
				$ten  = isset( $_POST['mg_ten'] ) ? (array) wp_unslash( $_POST['mg_ten'] ) : array();
				$tien = isset( $_POST['mg_tien'] ) ? (array) wp_unslash( $_POST['mg_tien'] ) : array();
				$ph   = isset( $_POST['mg_phut'] ) ? (array) wp_unslash( $_POST['mg_phut'] ) : array();
				$mo  = isset( $_POST['mg_mota'] ) ? (array) wp_unslash( $_POST['mg_mota'] ) : array();
				/* Ô tích gửi về CHỈ SỐ của dòng, không gửi về theo thứ tự — ô không tích thì
				   trình duyệt bỏ hẳn khỏi gói POST, nên đếm theo thứ tự là lệch ngay từ dòng
				   đầu tiên không tích. */
				$vip = isset( $_POST['mg_vip'] ) ? array_map( 'intval', (array) $_POST['mg_vip'] ) : array();
				$goi  = array();
				foreach ( $tien as $i => $v ) {
					$goi[] = array( 'tien' => $v,
						'ten'   => isset( $ten[ $i ] ) ? sanitize_text_field( $ten[ $i ] ) : '',
						'mo_ta' => isset( $mo[ $i ] ) ? sanitize_text_field( $mo[ $i ] ) : '',
						'phut'  => isset( $ph[ $i ] ) ? (int) $ph[ $i ] : 0,
						'vip'   => in_array( (int) $i, $vip, true ) ? 1 : 0 );
				}
				$bao[] = VHG_May::luu_menh_gia( $goi );
			}
		}

		echo '<div class="wrap"><h1>Máy &amp; cơ sở</h1>';
		self::ve_bao( $bao );

		$coso = VHG_May::ds_coso();
		echo '<h2>Cơ sở</h2><table class="widefat striped"><thead><tr><th>Tên</th><th>Số máy</th>'
			. '<th></th></tr></thead><tbody>';
		$may = VHG_May::ds_may();
		$dem = array();
		foreach ( $may as $m ) { $k = (int) $m['coso_id']; $dem[ $k ] = ( isset( $dem[ $k ] ) ? $dem[ $k ] : 0 ) + 1; }
		if ( ! $coso ) { echo '<tr><td colspan="3"><em>Chưa có cơ sở nào.</em></td></tr>'; }
		foreach ( $coso as $c ) {
			echo '<tr><td><strong>' . esc_html( $c['ten'] ) . '</strong></td><td>'
				. (int) ( isset( $dem[ (int) $c['id'] ] ) ? $dem[ (int) $c['id'] ] : 0 ) . '</td><td><form method="post">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="coso_id" value="' . (int) $c['id'] . '" />'
				. '<button class="button button-small" name="vhg" value="xoa_coso">Xoá</button>'
				. '</form></td></tr>';
		}
		echo '</tbody></table>';
		echo '<form method="post" style="margin-top:8px;display:flex;gap:8px;align-items:flex-end">';
		wp_nonce_field( 'vhg' );
		echo '<label>Thêm cơ sở <input type="text" name="ten" placeholder="VD: Tutu Tân Phú" required /></label>';
		echo '<input type="hidden" name="coso_id" value="0" />';
		echo '<button class="button button-primary" name="vhg" value="coso">Thêm</button></form>';
		echo '<p><em>Xoá cơ sở KHÔNG xoá máy trong đó — máy chỉ thành "chưa gán". Xoá theo là mất '
			. 'cấu hình giá/số tài khoản của những máy đang chạy thật.</em></p>';

		/* =====================================================================================
		 * GHẾ CHỜ GÁN — ĐẶT NGAY DƯỚI CƠ SỞ, TRÊN BẢNG MÁY.
		 *
		 * 🔴 Ghế nhận nhau với máy chủ bằng ĐỊA CHỈ MAC, không bằng mã. Cắm điện là nó tự hiện ra
		 *    đây với mã tạm `?xxxxxx`. Người đi lắp chỉ cần gán mã thật + cơ sở, KHÔNG phải gõ
		 *    MAC — gõ tay 12 ký tự hex là gõ sai.
		 *
		 * ⚠️ Đây là chỗ anh Thắng vướng ngày 22/08/2026: khai tay một dòng mang chính MAC làm mã.
		 *    Dòng đó KHÔNG gắn với con ghế nào (cột `mac` rỗng), nên khi ghế cắm điện nó vẫn đẻ ra
		 *    một dòng thứ hai — hai dòng cho một cái ghế, và cái đang chạy thật là dòng kia.
		 * ===================================================================================== */
		$cho_gan = VHG_May::chua_gan();
		echo '<h2>Ghế chờ gán mã (' . count( $cho_gan ) . ')</h2>';
		/* Câu này ở CẢ HAI trạng thái, không riêng lúc danh sách rỗng: nó trả lời câu hỏi
		   "sao tôi không khai được máy" — mà người ta hỏi câu đó ngay khi ĐANG nhìn thấy một
		   dòng chờ và không biết nó ở đâu ra. */
		echo '<p><em>Ghế <b>tự hiện ra đây khi cắm điện và nối được mạng</b> — không phải khai tay. '
			. 'Máy chủ nhận ra ghế bằng địa chỉ MAC, nên không ai phải gõ 12 ký tự hex.</em></p>';
		if ( ! $cho_gan ) {
			echo '<p><em>Không có ghế nào đang chờ. Chưa thấy ghế nào thì kiểm: ghế đã cắm chưa, có '
				. 'wifi/4G chưa, và đã nạp firmware trỏ về <code>' . esc_html( home_url( '/' ) )
				. '</code> chưa.</em></p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>MAC (ghế tự khai)</th><th>Mã tạm</th>'
				. '<th>Nhịp cuối</th><th>Gán mã thật + cơ sở</th></tr></thead><tbody>';
			foreach ( $cho_gan as $g ) {
				echo '<tr><td><code>' . esc_html( $g['mac'] ) . '</code></td>'
					. '<td><code>' . esc_html( $g['ma'] ) . '</code></td>'
					. '<td>' . esc_html( $g['nhip_luc'] )
					. ( ! empty( $g['con_song'] ) ? ' <span style="color:#046b2d">● đang sống</span>'
						: ' <span style="color:#b32d2e">● mất kết nối</span>' )
					. '<br><span class="description">' . esc_html( $g['ip'] ) . ' · ' . esc_html( $g['fw'] )
					. '</span></td><td>';
				echo '<form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">';
				echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
				echo '<input type="hidden" name="ma_cu" value="' . esc_attr( $g['ma'] ) . '" />';
				echo '<input type="text" name="ma_moi" required pattern="[A-Za-z0-9]{1,20}" '
					. 'placeholder="VD: AMTP01" style="width:130px" />';
				echo '<select name="coso_id"><option value="0">— chưa gán cơ sở —</option>';
				foreach ( $coso as $c ) {
					echo '<option value="' . (int) $c['id'] . '">' . esc_html( $c['ten'] ) . '</option>';
				}
				echo '</select>';
				echo '<button class="button button-primary" name="vhg" value="gan_ma">Gán</button>';
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
			echo '<p><em>Mã đi vào nội dung chuyển khoản khách gõ tay (<code>GHE&lt;mã&gt; &lt;mã lượt&gt;</code>) '
				. '— <b>đặt ngắn</b>. <code>AMTP01</code> cho ra <code>GHEAMTP01 K7M2P</code> (15 ký tự); '
				. 'lấy MAC làm mã cho ra 21 ký tự, gõ sai một ký tự là tiền vào mà ghế không chạy.<br>'
				. 'Gán mã sẽ dời luôn doanh thu và lượt đang chờ của ghế đó sang mã mới — không mất gì.</em></p>';
		}

		/* ---- Tài khoản nhận tiền: KHAI MỘT LẦN ---- */
		$tk_chung = VHG_May::nhan_tien_chung();
		echo '<h2>Tài khoản nhận tiền (dùng chung cả hệ thống)</h2>';
		echo '<p><em>Ghế <b>tự vẽ mã QR</b> trên màn của nó, nên nó phải biết tiền đi về đâu. SePay chỉ '
			. 'BÁO TIN tiền đã về, không quyết định tiền đi đâu — nên vẫn cần ba ô này.<br>'
			. 'Khai <b>một lần</b>, mọi ghế lấy về trong ~30 giây. Không phải nạp lại firmware.</em></p>';
		if ( '' === $tk_chung['so_tk'] || '' === $tk_chung['bin'] ) {
			echo '<div class="notice notice-error inline"><p><b>Chưa khai tài khoản nhận tiền.</b> '
				. 'Ghế sẽ không vẽ được mã QR — khách không quét được, không thu được đồng nào qua QR. '
				. '(Tiền mặt vẫn chạy.)</p></div>';
		}
		/* 🔴 ĐỐI CHIẾU VỚI SỰ THẬT. Xem khối giải thích ở VHG_May::nho_tk_ben_gui(). */
		$dc = VHG_May::doi_chieu_tk();
		if ( $dc['co'] && ! $dc['khop'] ) {
			echo '<div class="notice notice-error inline"><p><b>Số tài khoản đang khai KHÁC số mà '
				. 'bên gửi báo đã nhận tiền.</b></p>'
				. '<table class="widefat striped" style="max-width:420px;margin:6px 0"><tbody>'
				. '<tr><td>Đang khai ở đây</td><td><code>' . esc_html( $tk_chung['so_tk'] ) . '</code></td></tr>'
				. '<tr><td>Bên gửi (SePay) báo</td><td><code>' . esc_html( $dc['ben_gui'] ) . '</code>'
				. ' <span class="description">' . esc_html( $dc['luc'] ) . '</span></td></tr>'
				. ( '' !== (string) $dc['va']
					? '<tr><td>Tài khoản ảo (VA) bên gửi báo</td><td><code>' . esc_html( $dc['va'] )
						. '</code> <span class="description">thường ĐÂY mới là số cần điền ở trên</span></td></tr>'
					: '' )
				. '</tbody></table>'
				. '<p>Một trong hai sai. Sai ở ô này thì <b>mã QR trên 26 cái ghế đều hỏng</b> — mà nó '
				. 'vẫn dựng ra được, vẫn trông như thật, chỉ tới lúc có khách đứng quét mới lộ. '
				. 'Thiếu hoặc thừa <b>một chữ số</b> là đủ để app ngân hàng báo '
				. '“định dạng tài khoản không hợp lệ”.</p></div>';
		} elseif ( $dc['co'] ) {
			echo '<p><span style="color:#046b2d">✔️ Số tài khoản khớp với số bên gửi báo</span> '
				. '<span class="description">(lượt gần nhất ' . esc_html( $dc['luc'] ) . ')</span></p>';
		}
		echo '<form method="post"><table class="form-table">';
		wp_nonce_field( 'vhg' );
		echo '<tr><th>Số TK / VA nhận tiền</th><td><input name="so_tk" value="'
			. esc_attr( $tk_chung['so_tk'] ) . '" class="regular-text code" />'
			/* 🔴 BÀI HỌC NGÀY 22/08/2026. Anh Thắng quét thử: ngân hàng trừ tiền bình thường,
			   app hiện đúng tên chủ tài khoản, mà SePay KHÔNG thấy giao dịch nào và ghế không
			   chạy. Vì tiền vào TÀI KHOẢN GỐC, còn SePay theo dõi TÀI KHOẢN ẢO (VA).
			   Tiền không mất — nó nằm đúng trong tài khoản của mình — nhưng hệ thống mù với nó,
			   nên khách trả tiền xong đứng đó mà ghế không chạy. Ghi ra đây vì nhìn hai chuỗi số
			   thì không có gì gợi ý cái nào đúng. */
			. '<p class="description">⚠️ <b>Điền SỐ VA của SePay, không phải số tài khoản ngân hàng.</b> '
			. 'SePay chỉ báo về những giao dịch vào VA mà nó theo dõi; tiền chuyển thẳng vào tài '
			. 'khoản gốc thì vẫn về túi mình nhưng <b>hệ thống không thấy, và ghế không chạy</b>.<br>'
			. 'Lấy ở trang SePay → <b>Tài khoản ảo (VA)</b> → cột <b>Số VA</b>. VA có thể có chữ '
			. '(VD <code>96247POSH</code>) — điền nguyên văn.</p></td></tr>';
		echo '<tr><th>Mã ngân hàng (BIN)</th><td><input name="bin" value="'
			. esc_attr( $tk_chung['bin'] ) . '" style="width:120px" placeholder="970418" />'
			. '<p class="description">Napas BIN, 6 chữ số. 970418 = BIDV · 970436 = Vietcombank · '
			. '970415 = VietinBank · 970422 = MB · 970407 = Techcombank · 970416 = ACB · '
			. '970448 = OCB.<br>'
			/* BIN phải là ngân hàng PHÁT HÀNH VA, không phải ngân hàng mình quen dùng. VA do
			   SePay mở qua một ngân hàng đối tác, và ngân hàng đó có khi khác hẳn. */
			. '⚠️ Nếu ô trên điền VA thì BIN phải là <b>ngân hàng phát hành VA đó</b> — xem ở '
			. 'trang SePay, bấm vào số VA. Ngân hàng phát hành VA có thể khác ngân hàng mình quen dùng.<br>'
			. '<b>Sai BIN là QR quét ra ngân hàng khác và tiền không về tài khoản của mình.</b></p></td></tr>';
		echo '<tr><th>Tên tài khoản</th><td><input name="ten_tk" value="'
			. esc_attr( $tk_chung['ten_tk'] ) . '" class="regular-text" /></td></tr>';
		echo '</table><p><button class="button button-primary" name="vhg" value="nhan_tien">Lưu tài khoản</button></p></form>';

		/* ---- Tỉ lệ quy đổi: khai chung, ĐẶT NGAY TRÊN bảng gói ----
		 * 🔴 Trước đây tỉ lệ nằm tận ô "Thêm / sửa máy", tách khỏi chỗ khai gói và phải lưu lại
		 *    từng máy một. Nên nhìn bảng gói thì tưởng đã khai xong, mà số phút vẫn là số cũ —
		 *    đúng chỗ anh Thắng vướng: *"không điều chỉnh được loại mệnh giá à"*. Số phút của
		 *    bốn gói do CẶP SỐ NÀY quyết định, nên nó phải nằm ngay đây. */
		$tl_c = VHG_May::ty_le_chung();
		echo '<h2>Tỉ lệ quy đổi (dùng chung cả hệ thống)</h2>';
		echo '<p><em>Bao nhiêu tiền ra bao nhiêu phút. <b>Số phút của bốn gói dưới đây tính theo cặp '
			. 'số này</b> — đổi một lần là cả bốn gói theo.</em></p>';
		echo '<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		wp_nonce_field( 'vhg' );
		echo '<input type="number" name="gia_c" min="1000" step="1000" value="' . (int) $tl_c['gia']
			. '" style="width:120px" /> đ = '
			. '<input type="number" name="phut_c" min="1" max="240" value="' . (int) $tl_c['phut']
			. '" style="width:80px" /> phút '
			. '<button class="button button-primary" name="vhg" value="ty_le">Lưu tỉ lệ</button></form>';

		/* Ghế khai từ bản cũ đều mang tỉ lệ riêng (bản cũ không có ô chung), nên đổi ô chung mà
		   chúng không theo — và không có gì trên màn nói vì sao. Nói ra, kèm nút gỡ. */
		$so_rieng = 0;
		foreach ( $may as $m_r ) { if ( (int) $m_r['gia'] > 0 || (int) $m_r['phut'] > 0 ) { $so_rieng++; } }
		if ( $so_rieng ) {
			echo '<div class="notice notice-warning inline"><p><b>' . (int) $so_rieng . ' ghế đang khai '
				. 'tỉ lệ RIÊNG</b> nên KHÔNG theo ô chung ở trên. Ghế khai từ bản cũ đều thế, vì bản cũ '
				. 'không có ô chung.</p>'
				. '<form method="post">' . wp_nonce_field( 'vhg', '_wpnonce', true, false )
				. '<button class="button" name="vhg" value="bo_rieng">Cho tất cả dùng tỉ lệ chung</button>'
				. '</form></div>';
		}

		/* ---- Gói trên màn ghế ---- */
		$mg = VHG_May::menh_gia();
		echo '<h2>Gói trên màn ghế</h2>';
		echo '<p><em>Bốn nút khách bấm để chọn. Khai ở đây thì ghế lấy về trong ~30 giây — '
			. '<b>ghế không có OTA</b>, nên nếu khai cứng trong firmware thì đổi giá là phải mang USB '
			. 'đi từng cửa hàng.</em></p>';
		echo '<form method="post"><table class="widefat striped" style="max-width:900px"><thead><tr>'
			. '<th>Nút</th><th>Tên gói</th><th>Mô tả một dòng</th><th>Số tiền</th><th>Số phút</th>'
			. '<th>VVIP</th></tr></thead><tbody>';
		wp_nonce_field( 'vhg' );
		for ( $i = 0; $i < VHG_May::SO_O_MAN_GHE; $i++ ) {
			$g = isset( $mg[ $i ] ) ? $mg[ $i ]
				: array( 'tien' => '', 'ten' => '', 'phut' => 0, 'mo_ta' => '', 'vip' => 0 );
			echo '<tr><td>' . ( $i + 1 ) . '</td>'
				. '<td><input name="mg_ten[]" value="' . esc_attr( $g['ten'] ) . '" '
				. 'placeholder="VD: Gói phổ biến" style="width:100%" /></td>'
				. '<td><input name="mg_mota[]" value="' . esc_attr( $g['mo_ta'] ) . '" '
				. 'placeholder="VD: Sâu &amp; phục hồi" style="width:100%" /></td>'
				. '<td><input type="number" name="mg_tien[]" min="1000" step="1000" value="'
				. ( '' === $g['tien'] ? '' : (int) $g['tien'] ) . '" style="width:110px" /></td>'
				. '<td><input type="number" name="mg_phut[]" min="0" max="240" value="'
				. ( empty( $g['phut'] ) ? '' : (int) $g['phut'] ) . '" style="width:80px" placeholder="tự tính" /></td>'
				. '<td style="text-align:center"><input type="checkbox" name="mg_vip[]" value="' . $i . '"'
				. checked( true, ! empty( $g['vip'] ), false ) . ' /></td>'
				. '</tr>';
		}
		echo '</tbody></table><p><button class="button button-primary" name="vhg" value="menh_gia">'
			. 'Lưu gói</button></p></form>';
		echo '<p class="description"><b>Số phút để trống là đúng trong hầu hết trường hợp</b> — ghế tự '
			. 'tính theo tỉ lệ quy đổi, nên đổi tỉ lệ một lần là cả bốn gói theo. Chỉ điền số phút khi '
			. 'gói đó <b>cố ý</b> không theo tỉ lệ (gói khuyến mãi, gói kèm quà).<br>'
			. 'Bỏ trống số tiền = bỏ nút đó. Tên gói có dấu vẫn khai bình thường — máy chủ tự bỏ dấu '
			. 'khi gửi xuống ghế, vì font màn ghế không vẽ được dấu tiếng Việt.</p>';

		/* Xem trước ĐÚNG như ghế sẽ hiện: đã bỏ dấu, đã tính ra phút. Một bảng xem trước bằng
		   chính dữ liệu sắp gửi đi là cách duy nhất thấy trước "GOI PHO BIEN" trông thế nào,
		   thay vì đi tới tận cửa hàng mới biết tên bị cắt cụt. */
		/* 🔴 XEM TRƯỚC PHẢI DÙNG TỈ LỆ THẬT. Bản trước gọi cứng `(10000, 6)` nên in ra một bảng
		   số phút KHÔNG phải số ghế sẽ chạy — anh Thắng khai 50k/100k/150k/200k mà bảng nói
		   30/60/90/120 phút, trong khi bảng giá là 15/30/45/60. Một bảng "xem trước" nói sai còn
		   hại hơn không có bảng nào: người ta tin nó rồi thôi không đi kiểm. */
		$xem = VHG_May::menh_gia_cho_ghe( (int) $tl_c['gia'], (int) $tl_c['phut'] );
		echo '<h3>Ghế sẽ hiện (với tỉ lệ ' . esc_html( self::tien( $tl_c['gia'] ) ) . ' = '
			. (int) $tl_c['phut'] . ' phút)</h3>';
		echo '<table class="widefat striped" style="max-width:560px"><thead><tr><th>Tên trên màn ghế</th>'
			. '<th>Mô tả trên màn ghế</th><th>Số tiền</th><th>Phút</th></tr></thead><tbody>';
		foreach ( $xem as $x ) {
			echo '<tr><td><code>' . esc_html( '' !== $x['n'] ? $x['n'] : '(không tên)' ) . '</code>'
				. ( $x['v'] ? ' <b style="color:#996800">VVIP</b>' : '' ) . '</td>'
				. '<td><code>' . esc_html( $x['m'] ) . '</code></td>'
				. '<td>' . esc_html( self::tien( $x['t'] ) ) . '</td>'
				. '<td>' . (int) $x['p'] . ' phút</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">Chữ trên màn ghế <b>không dấu và viết hoa</b> — font của màn '
			. 'không vẽ được dấu tiếng Việt. Tên cắt còn 16 ký tự, mô tả 24 ký tự cho vừa bề ngang '
			. 'một thẻ. Gói tích <b>VVIP</b> được vẽ nền vàng và có nhãn ở góc, như tấm bảng giá.</p>';

		echo '<h2>Máy (ghế) — ' . count( $may ) . ' máy</h2>';
		echo '<table class="widefat striped"><thead><tr><th>Mã</th><th>MAC</th><th>Nhịp cuối</th>'
			. '<th>Cơ sở</th><th>Tỉ lệ quy đổi</th><th>Tài khoản nhận</th><th>QR</th>'
			. '<th></th></tr></thead><tbody>';
		if ( ! $may ) { echo '<tr><td colspan="8"><em>Chưa khai máy nào. Cắm ghế lên là nó tự hiện ở '
			. 'mục <b>Ghế chờ gán mã</b> phía trên.</em></td></tr>'; }
		$co_im = false;
		foreach ( $may as $m ) {
			$qr    = VHG_QR::cho_ghe( $m['ma'], 'MAU' );
			$tk_m  = VHG_May::nhan_tien_cua( $m );
			if ( empty( $m['con_song'] ) ) { $co_im = true; }
			$tl_m     = VHG_May::ty_le_cua( $m );
			$rieng_tl = ( (int) $m['gia'] > 0 || (int) $m['phut'] > 0 );
			$rieng = '' !== trim( (string) $m['so_tk'] ) || '' !== trim( (string) $m['bank_bin'] );
			echo '<tr><td><strong>' . esc_html( $m['ma'] ) . '</strong></td>'
				/* MAC là thứ ghế dùng để nhận ra chính nó. Hiện ra để còn đối chiếu khi một ghế
				   "không thấy đâu" — MAC rỗng nghĩa là dòng này khai tay và CHƯA gắn với ghế nào. */
				. '<td>' . ( '' !== (string) $m['mac'] ? '<code>' . esc_html( $m['mac'] ) . '</code>'
					: '<span style="color:#b32d2e" title="Dòng này khai tay, chưa ghế nào nhận">chưa gắn ghế</span>' ) . '</td>'
				/* 🔴 NHỊP CUỐI — cột quan trọng nhất khi ghế "không nhận". Ba ca đi sửa ở ba nơi
				   khác hẳn nhau, nên phải nói rõ đang ở ca nào chứ không gộp thành "mất kết nối". */
				. '<td>' . ( ! empty( $m['con_song'] )
					? '<span style="color:#046b2d">● ' . esc_html( $m['nhip_chu'] ) . '</span>'
					: '<span style="color:#b32d2e">● ' . esc_html( $m['nhip_chu'] ) . '</span>' )
				. ( '' !== (string) $m['fw']
					? '<br><span class="description">' . esc_html( $m['ip'] ) . ' · '
						. esc_html( $m['fw'] ) . '</span>' : '' ) . '</td>'
				. '<td>' . esc_html( $m['coso_ten'] ? $m['coso_ten'] : '(chưa gán)' ) . '</td>'
				. '<td>' . esc_html( self::tien( $tl_m['gia'] ) ) . ' = ' . (int) $tl_m['phut'] . ' phút'
				. ( $rieng_tl ? '<br><span class="description">khai riêng</span>'
					: '<br><span class="description">dùng chung</span>' ) . '</td>'
				. '<td><code>' . esc_html( $tk_m['so_tk'] ) . '</code> · ' . esc_html( $tk_m['bin'] )
				. ( $rieng ? '<br><span class="description">khai riêng</span>'
					: '<br><span class="description">dùng chung</span>' ) . '</td>'
				. '<td>' . ( ! empty( $qr['ok'] )
					? '<code style="font-size:10px;word-break:break-all">' . esc_html( substr( $qr['chuoi'], 0, 40 ) ) . '…</code>'
					: '<span style="color:#b32d2e">' . esc_html( $qr['error'] ) . '</span>' ) . '</td>'
				. '<td><form method="post">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="ma" value="' . esc_attr( $m['ma'] ) . '" />'
				. '<button class="button button-small" name="vhg" value="xoa_may">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		/* Chỉ dẫn hiện ra ĐÚNG LÚC có ghế đang im. Bảng "nhịp cuối" nói ghế đang ở ca nào; khối
		   này nói ca đó thì đi làm gì. Hiện thường trực là người ta thôi đọc. */
		if ( $co_im ) {
			echo '<div class="notice notice-warning inline"><p><b>Có ghế không gửi nhịp.</b> Ba ca, '
				. 'ba chỗ sửa khác nhau:</p><ol style="margin-left:20px;list-style:decimal">'
				. '<li><b>“chưa bao giờ gửi nhịp”</b> — ghế chưa từng nói chuyện với website. Kiểm: '
				. 'đã nạp firmware mới bằng USB chưa; trong <code>secrets.h</code> địa chỉ web có '
				. 'đúng <code>' . esc_html( home_url( '/' ) ) . '</code> không; khoá máy có khớp '
				. '<code>VHG_KHOA_MAY</code> trong <code>wp-config.php</code> không.</li>'
				. '<li><b>“im từ vài phút trước”</b> — mạng chập. Đợi một lượt nhịp (~30 giây) rồi '
				. 'tải lại trang.</li>'
				. '<li><b>“im từ vài giờ/ngày trước”</b> — ghế mất điện, rớt 4G, hoặc treo. Thử '
				. '<b>Khởi động lại</b> ở tab Điều khiển của trang ngoài; không lên thì phải tới nơi.</li>'
				. '</ol><p>Ghế mất nhịp thì <b>khách vẫn quét được tem QR dán trên ghế, tiền vẫn vào, '
				. 'nhưng ghế không chạy</b> — nên đây là việc gấp, không phải việc để mai.</p></div>';
		}

		echo '<h3>Thêm / sửa máy</h3><form method="post">';
		wp_nonce_field( 'vhg' );
		echo '<table class="form-table">';
		echo '<tr><th>Mã máy *</th><td><input type="text" name="ma" required pattern="[A-Za-z0-9]{1,20}" />'
			. '<p class="description">Chỉ chữ và số, không dấu, không khoảng trắng. Mã này đi vào nội dung '
			. 'chuyển khoản khách gõ tay (<code>GHE&lt;mã&gt; &lt;mã lượt&gt;</code>) — có dấu là khách gõ sai '
			. 'và ghế không chạy. Khai lại đúng mã cũ = sửa máy đó.</p></td></tr>';
		echo '<tr><th>Cơ sở</th><td><select name="coso_id"><option value="0">— chưa gán —</option>';
		foreach ( $coso as $c ) {
			echo '<option value="' . (int) $c['id'] . '">' . esc_html( $c['ten'] ) . '</option>';
		}
		echo '</select></td></tr>';
		/* 🔴 Ô MAC — anh Thắng 22/08/2026: *"không có chỗ nhập mac, chỉ có mã"*. Đúng, và dòng
		   khai tay không có MAC là dòng KHÔNG GẮN VỚI GHẾ NÀO: ghế cắm điện lên sẽ đẻ ra một
		   dòng thứ hai, và dòng đang chạy thật là dòng kia.
		   Nhưng cách đúng vẫn là để ghế tự hiện ra rồi bấm Gán — nên ô này nói thẳng điều đó. */
		echo '<tr><th>Địa chỉ MAC</th><td><input type="text" name="mac" class="regular-text code" '
			. 'placeholder="AA:BB:CC:DD:EE:FF — để trống nếu chưa biết" />'
			. '<p class="description"><b>Thường không cần gõ.</b> Cắm ghế lên là nó tự hiện ở mục '
			. '<b>Ghế chờ gán mã</b> phía trên, bấm Gán là xong — khỏi gõ 12 ký tự hex.<br>'
			. 'Chỉ gõ tay khi ghế chưa nối được mạng mà anh muốn khai trước. Gõ sai một ký tự là '
			. 'ghế thật không nhận ra dòng này, và nó sẽ hiện ra như một ghế mới.<br>'
			. 'Để trống khi <b>sửa</b> một máy đã có thì MAC cũ được giữ nguyên, không bị xoá.</p></td></tr>';
		/* 🔴 KHÔNG PHẢI "GIÁ MỘT LƯỢT". Hai ô này là TỈ LỆ QUY ĐỔI: ghế tính
		   `phút = tiền × phút / giá`. Nhãn cũ ghi "Giá một lượt" làm anh Thắng tưởng ghế chỉ có
		   một mệnh giá, trong khi màn ghế có bốn nút. Nên đổi nhãn, và in thẳng bảng quy đổi ra
		   — một bảng số cụ thể nói rõ hơn mọi câu giải thích. */
		echo '<tr><th>Tỉ lệ riêng (tuỳ chọn)</th><td>'
			. '<input type="number" name="gia" value="" min="0" step="1000" style="width:110px" placeholder="0 = chung" /> đ '
			. '= <input type="number" name="phut" value="" min="0" max="240" style="width:70px" placeholder="0" /> phút'
			. '<p class="description"><b>Để trống là đúng trong hầu hết trường hợp</b> — ghế dùng tỉ lệ '
			. 'chung khai ở trên (' . esc_html( self::tien( $tl_c['gia'] ) ) . ' = ' . (int) $tl_c['phut']
			. ' phút). Chỉ điền khi ghế này cố ý chạy tỉ lệ khác.</p></td></tr>';
		echo '<tr><th>Tài khoản riêng (tuỳ chọn)</th><td>'
			. '<input type="text" name="so_tk" class="regular-text code" placeholder="số TK — trống = dùng chung" />'
			. ' <input type="text" name="bank_bin" style="width:110px" placeholder="BIN" />'
			. ' <input type="text" name="ten_tk" style="width:180px" placeholder="tên TK" />'
			. '<p class="description"><b>Để trống cả ba là đúng trong hầu hết trường hợp</b> — ghế dùng '
			. 'tài khoản chung khai ở trên. Chỉ điền khi ghế này nhận tiền vào một tài khoản khác.</p></td></tr>';
		echo '<tr><th>Tên trên sao kê</th><td><input type="text" name="ten_khai" class="regular-text" placeholder="VD: AMTP 03" />'
			. '<p class="description">Tên máy như Tingo/VietQR ghi trong sao kê. Khai đúng thì doanh thu nhập '
			. 'từ Excel tự gộp vào đúng máy này.</p></td></tr>';
		echo '</table><p><button class="button button-primary" name="vhg" value="may">Lưu máy</button></p></form></div>';
	}

	// ======================================================================= 3. CỔNG & NHẬT KÝ

	public static function trang_cong() {
		self::gac();
		$bao = array();
		if ( isset( $_POST['vhg'] ) ) {
			check_admin_referer( 'vhg' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhg'] ) );
			if ( 'nhap_gd' === $viec ) {
				$bao[] = VHG_Nhap::nhap_giao_dich( VHG_Nhap::bang_tu_van_ban( wp_unslash( $_POST['bang'] ) ) );
			} elseif ( 'nhap_bd' === $viec ) {
				$bao[] = VHG_Nhap::nhap_ban_do( VHG_Nhap::bang_tu_van_ban( wp_unslash( $_POST['bang'] ) ) );
			} elseif ( 'ap_lai' === $viec ) {
				$bao[] = VHG_Nhap::ap_lai_ban_do();
			} elseif ( 'don_tien_ra' === $viec ) {
				$bao[] = VHG_Nhap::don_tien_ra();
			} elseif ( 'xoa_log' === $viec ) {
				$bao[] = VHG_Nhat_Ky::xoa();
			} elseif ( 'xoa_bd' === $viec ) {
				$bao[] = VHG_Nhap::xoa_ban_do( wp_unslash( $_POST['khoa'] ) );
			}
		}

		echo '<div class="wrap"><h1>Nhận tiền &amp; nhật ký</h1>';
		self::ve_bao( $bao );

		/* ---- Link webhook ---- */
		$co_khoa = '' !== VHG_Cong::khoa( 'VHG_KHOA_WEBHOOK' );
		echo '<h2>Link webhook để dán vào bên gửi</h2>';
		if ( ! $co_khoa ) {
			echo '<div class="notice notice-error"><p><strong>Chưa khai <code>VHG_KHOA_WEBHOOK</code> trong '
				. 'wp-config.php nên cổng đang ĐÓNG</strong> — mọi lượt bắn tới đều bị từ chối. Thêm dòng này '
				. 'vào <code>wp-config.php</code> (chuỗi ngẫu nhiên dài, tự đặt):</p>'
				. '<p><code>define( \'VHG_KHOA_WEBHOOK\', \'…chuỗi dài ngẫu nhiên…\' );</code></p>'
				. '<p>Khoá KHÔNG nằm trong mã nguồn, cố ý: mã nguồn nằm ở repo công khai.</p></div>';
		} else {
			$khoa = rawurlencode( VHG_Cong::khoa( 'VHG_KHOA_WEBHOOK' ) );
			$goc  = home_url( '/' . VHG_Cong::DUONG_TIEN );
			echo '<table class="form-table">';
			echo '<tr><th>VietQR / Tingo</th><td><input type="text" class="large-text" readonly '
				. 'onclick="this.select()" value="' . esc_attr( $goc . '?src=vietqr&token=' . $khoa ) . '" /></td></tr>';
			echo '<tr><th>SePay</th><td><input type="text" class="large-text" readonly '
				. 'onclick="this.select()" value="' . esc_attr( $goc . '?token=' . $khoa ) . '" /></td></tr>';
			echo '</table>';
			echo '<p><em>Mở link này bằng trình duyệt (GET) sẽ ra một câu xác nhận cổng còn sống — đó là cách '
				. 'nhanh nhất để biết tường lửa của hosting có chặn đường này không.</em></p>';
		}
		echo '<p><strong>Nội dung chuyển khoản nên có dạng <code>GHE&lt;mã máy&gt; &lt;mã lượt&gt;</code></strong> '
			. '(VD <code>GHE3 T1ABC</code>) để tự khớp đúng ghế. Không khớp thì tiền <strong>vẫn vào sổ</strong>, '
			. 'chỉ là chưa gắn được máy — đối soát tay sau, không mất.</p>';

		/* ---- Nhập bảng ---- */
		echo '<h2>Nhập bảng giao dịch từ Tingo / VietQR</h2>';
		echo '<p>Copy từ Excel (<strong>bôi đen cả dòng tiêu đề</strong>) rồi dán vào ô dưới. Nhập lại đúng '
			. 'file đó <strong>không cộng đôi</strong>: mỗi giao dịch ghi theo mã tham chiếu của ngân hàng.</p>';
		echo '<form method="post">';
		wp_nonce_field( 'vhg' );
		echo '<textarea name="bang" rows="6" class="large-text code" placeholder="Mã tham chiếu	Mã điểm bán	Mã cửa hàng	Số tiền đến (VND)	Nội dung TT	Thời gian tạo"></textarea>';
		echo '<p><button class="button button-primary" name="vhg" value="nhap_gd">Nhập giao dịch</button> '
			. '<button class="button" name="vhg" value="nhap_bd">Nhập danh sách Voice Box (bản đồ máy)</button></p></form>';

		echo '<form method="post" style="margin-bottom:16px">';
		wp_nonce_field( 'vhg' );
		echo '<button class="button" name="vhg" value="ap_lai">Áp lại bản đồ cho giao dịch chưa rõ máy</button> '
			. '<button class="button" name="vhg" value="don_tien_ra">Dọn giao dịch tiền ra bị tính nhầm</button>';
		echo '</form>';

		/* ---- Bản đồ máy ---- */
		$bd = VHG_Nhap::ds_ban_do();
		echo '<h2>Bản đồ máy (' . count( $bd ) . ')</h2>';
		echo '<p><em>Giao dịch nội dung "PaymentForOrder" không nói máy nào — chỉ Mã điểm bán mới nói. Bảng này '
			. 'học từ những giao dịch CÓ tên rồi áp cho những giao dịch không có. Dòng do người khai '
			. '(<strong>khai tay</strong>) thì máy không ghi đè.</em></p>';
		echo '<table class="widefat striped"><thead><tr><th>Khoá (mã điểm bán / mã cửa hàng)</th>'
			. '<th>Tên máy</th><th>Nguồn</th><th>Cập nhật</th><th></th></tr></thead><tbody>';
		if ( ! $bd ) { echo '<tr><td colspan="5"><em>Chưa có.</em></td></tr>'; }
		foreach ( array_slice( $bd, 0, 200 ) as $r ) {
			echo '<tr><td><code>' . esc_html( $r['khoa'] ) . '</code></td><td>' . esc_html( $r['ten_may'] ) . '</td>'
				. '<td>' . ( (int) $r['tu_hoc'] ? 'máy tự học' : '<strong>khai tay</strong>' ) . '</td>'
				. '<td>' . esc_html( (string) $r['cap_nhat'] ) . '</td><td><form method="post">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="khoa" value="' . esc_attr( $r['khoa'] ) . '" />'
				. '<button class="button button-small" name="vhg" value="xoa_bd">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Nhật ký ---- */
		$log = VHG_Nhat_Ky::ds( 100 );
		echo '<h2>Nhật ký cổng nhận tiền (' . count( $log ) . ')</h2>';
		echo '<p><em>Ghi <strong>mọi</strong> lượt bắn tới, kể cả lượt bị từ chối vì sai khoá. Đó là cách duy '
			. 'nhất phân biệt "bên gửi chưa bắn" với "bắn rồi mà mình chặn" — hai ca đó đi sửa ở hai nơi '
			. 'khác hẳn. Giữ ' . (int) VHG_Nhat_Ky::GIU . ' dòng gần nhất.</em></p>';
		echo '<table class="widefat striped"><thead><tr><th>Lúc</th><th>Nguồn</th><th>Số tiền</th>'
			. '<th>Nội dung</th><th>Máy</th><th>Ghi chú</th></tr></thead><tbody>';
		if ( ! $log ) { echo '<tr><td colspan="6"><em>Chưa có lượt nào bắn tới. Thử chuyển 1.000đ để kiểm tra.</em></td></tr>'; }
		foreach ( $log as $l ) {
			echo '<tr><td>' . esc_html( $l['luc'] ) . '</td><td>' . esc_html( $l['nguon'] ) . '</td>'
				. '<td>' . esc_html( self::tien( $l['so_tien'] ) ) . '</td>'
				. '<td>' . esc_html( $l['noi_dung'] ) . '</td>'
				. '<td>' . esc_html( $l['ma_may'] ? $l['ma_may'] : $l['ten_khai'] ) . '</td>'
				. '<td>' . esc_html( $l['ghi_chu'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $log ) {
			echo '<h3>Gói thô mới nhất</h3><p><em>Bên gửi đổi tên trường mà không báo là chuyện thường. '
				. 'Đây là thứ duy nhất cho biết họ đang gửi gì.</em></p>';
			echo '<pre style="background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;overflow:auto;max-height:220px">'
				. esc_html( (string) $log[0]['tho'] ) . '</pre>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'vhg' );
		echo '<p><button class="button" name="vhg" value="xoa_log">Xoá nhật ký</button> '
			. '<em>Không ảnh hưởng doanh thu đã ghi.</em></p></form>';
		echo '</div>';
	}

	// ======================================================================= 4. TRANG NGOÀI & PIN

	/**
	 * Khai đường dẫn trang ngoài, ai vào được, và danh sách PIN riêng khi không dùng chung.
	 *
	 * ⚠️ MÀN NÀY KHÔNG BAO GIỜ IN PIN — chỉ in SỐ CHỮ SỐ. Ảnh màn hình đi khắp nơi; trong chính
	 *    dự án này đã mất một khoá cầu nối và một khoá webhook vì ảnh gửi qua chat.
	 */
	public static function trang_ngoai() {
		self::gac();
		$bao = array();
		if ( isset( $_POST['vhg'] ) ) {
			check_admin_referer( 'vhg' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhg'] ) );
			if ( 'luu_trang' === $viec ) {
				$slug = sanitize_title( wp_unslash( $_POST['slug'] ) );
				if ( '' === $slug ) { $slug = 'ghe'; }
				if ( get_option( 'vhg_slug' ) !== $slug ) { update_option( 'vhg_flush_rewrite', 1 ); }
				update_option( 'vhg_slug', $slug );

				$nguon = sanitize_text_field( wp_unslash( $_POST['nguon'] ) );
				update_option( 'vhg_nguon_nguoidung', 'rieng' === $nguon ? 'rieng' : 'chung' );

				$vt = array();
				foreach ( (array) ( isset( $_POST['vai_tro'] ) ? $_POST['vai_tro'] : array() ) as $x ) {
					$x = sanitize_text_field( wp_unslash( $x ) );
					if ( in_array( $x, VHG_Auth::VAI_TRO_TAT_CA, true ) ) { $vt[] = $x; }
				}
				update_option( 'vhg_vai_tro_vao', $vt );
				$bao[] = array( 'ok' => true, 'thong_bao' => 'Đã lưu. Địa chỉ trang: ' . VHG_Trang::url() );
			} elseif ( 'them_nd' === $viec ) {
				$bao[] = self::them_nguoi_dung(
					wp_unslash( $_POST['ten'] ), wp_unslash( $_POST['pin'] ),
					wp_unslash( $_POST['vai_tro_moi'] ), wp_unslash( $_POST['coso'] ) );
			} elseif ( 'xoa_nd' === $viec ) {
				$ds = (array) get_option( 'vhg_nguoidung' );
				unset( $ds[ (int) $_POST['i'] ] );
				update_option( 'vhg_nguoidung', array_values( $ds ) );
				$bao[] = array( 'ok' => true, 'thong_bao' => 'Đã xoá.' );
			}
		}

		echo '<div class="wrap"><h1>Trang ngoài &amp; PIN</h1>';
		self::ve_bao( $bao );
		echo '<p>Trang cho nhân viên cửa hàng mở trên điện thoại — xem doanh thu, biết ghế nào đang '
			. 'đứng, thu tiền mặt. <b>Không cần tài khoản WordPress</b>, chỉ cần PIN.</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( VHG_Trang::url() ) . '" target="_blank">'
			. esc_html( VHG_Trang::url() ) . '</a></p>';
		/* 🔴 Permalinks kiểu "Plain" thì luật đường dẫn KHÔNG chạy — `/ghe` trả 404, và trang chỉ
		   mở được bằng địa chỉ `?vhg=app` xấu và khó đọc cho người phải gõ trên điện thoại. Nói
		   ra ở đây, vì triệu chứng (404) không hề gợi tới nguyên nhân (một ô cài đặt của
		   WordPress ở màn khác hẳn). */
		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<div class="notice notice-warning inline"><p><b>WordPress đang để đường dẫn kiểu '
				. '“Plain”</b> nên <code>/' . esc_html( VHG_Trang::slug() ) . '</code> sẽ trả 404. '
				. 'Vào <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">Settings → '
				. 'Permalinks</a> chọn <b>Post name</b> rồi Lưu. Hai cổng máy '
				. '(<code>/ghe-tien</code>, <code>/ghe-may</code>) cũng cần đúng thứ đó.</p></div>';
		}

		/* Một khối form DUY NHẤT cho phần cài đặt. Các form con (thêm/xoá người) dựng RIÊNG ở
		   dưới, KHÔNG lồng — xem bài học ở class-vhcc-admin.php: <form> lồng <form> thì trình
		   duyệt lặng lẽ gộp ô nhập vào form cha, và một ô `required` ở cuối trang chặn luôn
		   nút Lưu ở đầu trang. */
		echo '<form method="post">';
		wp_nonce_field( 'vhg' );
		echo '<table class="form-table">';
		echo '<tr><th>Đường dẫn trang</th><td>' . esc_html( home_url( '/' ) )
			. '<input name="slug" value="' . esc_attr( VHG_Trang::slug() ) . '" class="regular-text" /> /'
			. '<p class="description">Mặc định <code>ghe</code>.</p></td></tr>';

		$nguon = VHG_Auth::nguon();
		echo '<tr><th>Nguồn người dùng &amp; PIN</th><td>';
		echo '<label><input type="radio" name="nguon" value="chung"' . checked( 'chung', $nguon, false )
			. ' /> Dùng chung với plugin <b>Vận hành chi phí</b> (khuyến nghị)</label><br>';
		echo '<label><input type="radio" name="nguon" value="rieng"' . checked( 'rieng', $nguon, false )
			. ' /> Danh sách riêng của plugin này</label>';
		if ( 'chung' === $nguon && ! VHG_Auth::co_bang_chung() ) {
			echo '<p style="color:#b32d2e"><b>Không thấy bảng của plugin Vận hành chi phí</b> — '
				. 'chưa ai đăng nhập được. Chuyển sang danh sách riêng, hoặc cài plugin đó.</p>';
		}
		echo '</td></tr>';

		$cho = VHG_Auth::vai_tro_vao();
		echo '<tr><th>Vai trò vào được</th><td>';
		foreach ( VHG_Auth::VAI_TRO_TAT_CA as $vt ) {
			echo '<label style="margin-right:14px"><input type="checkbox" name="vai_tro[]" value="'
				. esc_attr( $vt ) . '"' . checked( true, in_array( $vt, $cho, true ), false ) . ' /> '
				. esc_html( $vt ) . '</label>';
		}
		echo '<p class="description">Bỏ hết dấu tích = quay về mặc định, KHÔNG phải khoá sạch — '
			. 'rỗng là không ai vào được, kể cả Admin, và không có đường tự mở lại ngoài cơ sở dữ liệu.'
			. '<br>Cửa hàng trưởng mặc định VÀO ĐƯỢC màn này (khác plugin chấm công): người đứng quầy '
			. 'chính là người cần biết ghế nào đang đứng.</p></td></tr>';
		echo '</table><p><button class="button button-primary" name="vhg" value="luu_trang">Lưu</button></p></form>';

		/* ---- Ai vào được, PIN dài mấy số ---- */
		$u = VHG_Auth::users();
		if ( is_wp_error( $u ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $u->get_error_message() ) . '</p></div>';
			$u = array();
		}
		$vao = array();
		foreach ( $u as $x ) { if ( in_array( $x['vaiTro'], $cho, true ) ) { $vao[] = $x; } }
		echo '<h2>Ai vào được (' . count( $vao ) . '/' . count( $u ) . ')</h2>';
		if ( ! $vao ) {
			echo '<div class="notice notice-warning inline"><p><b>Chưa ai đăng nhập được trang ngoài.</b> '
				. 'Tích thêm vai trò ở trên, hoặc thêm người vào danh sách riêng bên dưới.</p></div>';
		} else {
			echo '<table class="widefat striped" style="max-width:620px"><thead><tr><th>Tên</th>'
				. '<th>Vai trò</th><th>Cơ sở</th><th>PIN dài</th></tr></thead><tbody>';
			foreach ( $vao as $x ) {
				/* ⚠️ CHỈ SỐ CHỮ SỐ, không bao giờ in PIN. */
				$dai = '' === $x['pin'] ? '<span style="color:#b32d2e">chưa có</span>'
					: ( preg_match( '/^\d{4,8}$/', $x['pin'] )
						? strlen( $x['pin'] ) . ' số'
						: '<span style="color:#b32d2e">' . strlen( $x['pin'] ) . ' ký tự — không dùng được</span>' );
				echo '<tr><td><b>' . esc_html( $x['ten'] ) . '</b></td><td>' . esc_html( $x['vaiTro'] )
					. '</td><td>' . esc_html( $x['coso'] ) . '</td><td>' . wp_kses_post( $dai ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">Bảng này <b>không in PIN</b> — chỉ số chữ số, đủ để biết mình '
				. 'đang gõ thiếu hay thừa.</p>';
		}

		/* ---- Danh sách riêng ---- */
		if ( 'rieng' === $nguon ) {
			$ds = (array) get_option( 'vhg_nguoidung' );
			echo '<h2>Danh sách riêng</h2>';
			if ( $ds ) {
				echo '<table class="widefat striped" style="max-width:620px"><thead><tr><th>Tên</th>'
					. '<th>Vai trò</th><th>Cơ sở</th><th>PIN dài</th><th></th></tr></thead><tbody>';
				foreach ( $ds as $i => $x ) {
					$x = (array) $x;
					echo '<tr><td><b>' . esc_html( isset( $x['ten'] ) ? $x['ten'] : '' ) . '</b></td>'
						. '<td>' . esc_html( isset( $x['vaiTro'] ) ? $x['vaiTro'] : '' ) . '</td>'
						. '<td>' . esc_html( isset( $x['coso'] ) ? $x['coso'] : '' ) . '</td>'
						. '<td>' . strlen( (string) ( isset( $x['pin'] ) ? $x['pin'] : '' ) ) . ' số</td>'
						. '<td><form method="post">' . wp_nonce_field( 'vhg', '_wpnonce', true, false )
						. '<input type="hidden" name="i" value="' . (int) $i . '" />'
						. '<button class="button button-small" name="vhg" value="xoa_nd">Xoá</button>'
						. '</form></td></tr>';
				}
				echo '</tbody></table>';
			}
			echo '<form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:10px 0">';
			wp_nonce_field( 'vhg' );
			echo '<label>Họ tên<br><input name="ten" required style="width:180px" /></label>';
			echo '<label>PIN (4–8 số)<br><input name="pin" inputmode="numeric" required style="width:110px" /></label>';
			echo '<label>Vai trò<br><select name="vai_tro_moi">';
			foreach ( VHG_Auth::VAI_TRO_TAT_CA as $vt ) {
				echo '<option value="' . esc_attr( $vt ) . '"' . selected( $vt, 'Cửa hàng trưởng', false ) . '>'
					. esc_html( $vt ) . ( in_array( $vt, $cho, true ) ? '' : ' — không vào được' ) . '</option>';
			}
			echo '</select></label>';
			echo '<label>Cơ sở<br><input name="coso" style="width:150px" placeholder="trống = cả chuỗi" /></label>';
			echo '<button class="button button-primary" name="vhg" value="them_nd">Thêm người</button></form>';
			echo '<p class="description">Quên PIN thì xoá dòng đó rồi thêm lại — màn này không in PIN ra.</p>';
		}
		echo '</div>';
	}

	/**
	 * Thêm một người vào danh sách riêng.
	 * ⚠️ CHẶN PIN TRÙNG. Hai người cùng PIN thì `login()` khớp người ĐẦU TIÊN trong danh sách —
	 *    người thứ hai gõ đúng PIN của mình mà vào nhầm quyền của người khác, im lặng.
	 */
	private static function them_nguoi_dung( $ten, $pin, $vai_tro, $coso ) {
		$ten = trim( (string) $ten );
		$pin = VHG_Auth::pin_sach( $pin );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu họ tên.' ); }
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số.' );
		}
		/* PIN quá dễ đoán là mở cửa doanh thu 26 cửa hàng cho một lượt thử tay. */
		if ( preg_match( '/^(\d)\1+$/', $pin ) || false !== strpos( '01234567890', $pin )
			|| false !== strpos( '09876543210', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN quá dễ đoán — chọn PIN khác.' );
		}
		$ds = (array) get_option( 'vhg_nguoidung' );
		foreach ( $ds as $x ) {
			$x = (array) $x;
			if ( (string) ( isset( $x['pin'] ) ? $x['pin'] : '' ) === $pin ) {
				return array( 'ok' => false, 'error' => 'PIN này đã có người dùng — chọn PIN khác.' );
			}
		}
		$vt = in_array( (string) $vai_tro, VHG_Auth::VAI_TRO_TAT_CA, true ) ? (string) $vai_tro : 'Cửa hàng trưởng';
		$ds[] = array( 'ten' => $ten, 'pin' => $pin, 'vaiTro' => $vt, 'coso' => trim( (string) $coso ) );
		update_option( 'vhg_nguoidung', array_values( $ds ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã thêm ' . $ten . '.' );
	}

}
