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
					'gia' => wp_unslash( $_POST['gia'] ), 'phut' => wp_unslash( $_POST['phut'] ),
					'so_tk' => wp_unslash( $_POST['so_tk'] ), 'ten_tk' => wp_unslash( $_POST['ten_tk'] ),
					'bank_bin' => wp_unslash( $_POST['bank_bin'] ), 'ten_khai' => wp_unslash( $_POST['ten_khai'] ) ) );
			} elseif ( 'xoa_may' === $viec ) {
				$bao[] = VHG_May::xoa_may( wp_unslash( $_POST['ma'] ) );
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

		echo '<h2>Máy (ghế) — ' . count( $may ) . ' máy</h2>';
		echo '<table class="widefat striped"><thead><tr><th>Mã</th><th>Cơ sở</th><th>Giá</th><th>Phút</th>'
			. '<th>Số TK/VA</th><th>Ngân hàng (BIN)</th><th>Tên trên sao kê</th><th>QR</th><th></th></tr></thead><tbody>';
		if ( ! $may ) { echo '<tr><td colspan="9"><em>Chưa khai máy nào.</em></td></tr>'; }
		foreach ( $may as $m ) {
			$qr = VHG_QR::cho_ghe( $m['ma'], 'MAU' );
			echo '<tr><td><strong>' . esc_html( $m['ma'] ) . '</strong></td>'
				. '<td>' . esc_html( $m['coso_ten'] ? $m['coso_ten'] : '(chưa gán)' ) . '</td>'
				. '<td>' . esc_html( self::tien( $m['gia'] ) ) . '</td><td>' . (int) $m['phut'] . '</td>'
				. '<td><code>' . esc_html( $m['so_tk'] ) . '</code></td>'
				. '<td>' . esc_html( $m['bank_bin'] ) . '</td>'
				. '<td>' . esc_html( $m['ten_khai'] ) . '</td>'
				. '<td>' . ( ! empty( $qr['ok'] )
					? '<code style="font-size:10px;word-break:break-all">' . esc_html( substr( $qr['chuoi'], 0, 40 ) ) . '…</code>'
					: '<span style="color:#b32d2e">' . esc_html( $qr['error'] ) . '</span>' ) . '</td>'
				. '<td><form method="post">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="ma" value="' . esc_attr( $m['ma'] ) . '" />'
				. '<button class="button button-small" name="vhg" value="xoa_may">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

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
		echo '<tr><th>Giá một lượt (đ)</th><td><input type="number" name="gia" value="10000" min="1000" step="1000" /></td></tr>';
		echo '<tr><th>Thời lượng (phút)</th><td><input type="number" name="phut" value="6" min="1" max="60" /></td></tr>';
		echo '<tr><th>Số TK / VA nhận tiền</th><td><input type="text" name="so_tk" class="regular-text" /></td></tr>';
		echo '<tr><th>Tên tài khoản</th><td><input type="text" name="ten_tk" class="regular-text" /></td></tr>';
		echo '<tr><th>Mã ngân hàng (BIN)</th><td><input type="text" name="bank_bin" value="970418" style="width:120px" />'
			. '<p class="description">Napas BIN. 970418 = BIDV. Sai BIN là QR quét ra ngân hàng khác và tiền '
			. 'không về tài khoản của mình.</p></td></tr>';
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
}
