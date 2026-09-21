<?php
/**
 * MÀN "LỊCH SỬ SỬA BẢNG CÔNG" — mọi lượt ai đó động vào giờ công, ở một chỗ.
 *
 * Anh Thắng 18/09/2026: *"thêm tab lịch sử sửa bảng công"*.
 *
 * =============================================================================================
 * 🔴 KHÔNG DỰNG KHO MỚI — GOM BA SỔ ĐÃ CÓ
 * =============================================================================================
 * Mọi đường động vào giờ công đều đã để lại dấu, chỉ là nằm rải ba nơi:
 *
 *   · `cham_bu`  — TỪNG Ô giờ bị bù / sửa / xoá, kèm giờ CŨ, ai làm, vì sao. Đây là sổ gốc, và
 *     mọi đường khác cuối cùng đều đi qua `VHCC_Bu` nên đều rơi vào đây.
 *   · `don_tuan` — tệp .xlsx cửa hàng gửi, kế toán duyệt cả lượt.
 *   · `xin_bu`   — đơn bù lẻ của nhân viên, hai cấp duyệt.
 *
 * Dựng một bảng "lịch sử" thứ tư rồi ghi song song vào đó là hai nguồn sự thật cho cùng một câu
 * hỏi — và tới ngày chúng lệch nhau thì không ai biết tin cái nào. Ba sổ trên đã đủ, và sổ đầu
 * là sổ KHÔNG XOÁ ĐƯỢC: `VHCC_Bu::xoa()` ghi nhật ký TRƯỚC khi xoá dòng.
 *
 * ⚠️ MÀN NÀY CHỈ ĐỌC. Không có một nút nào sửa hay xoá được gì ở đây — sổ mà sửa được thì không
 *    còn là sổ. Ai thấy một dòng sai thì đó là chuyện của bảng công, không phải của sổ.
 *
 * ⚠️ PHẠM VI CƠ SỞ GIỮ NGUYÊN. `VHCC_Bu::ds_nhat_ky()` tự lọc theo `co_quyen_coso()` từng dòng,
 *    nên cửa hàng trưởng chỉ đọc được lịch sử cơ sở mình. Hai sổ kia lọc ở đây, cùng một luật.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_WebLichSu {

	/** Cùng cửa với màn Bảng công — ai đọc được bảng công thì đọc được lịch sử của nó. */
	const QUYEN = 'cong_coso';

	/** Trần một lượt đọc. Sổ ba tháng của một chuỗi 26 cửa hàng là mấy chục nghìn dòng. */
	const TOI_DA = 400;

	public static function man( $ky, $toi ) {
		echo '<div class="the"><h2>🕘 Lịch sử sửa bảng công</h2>';
		echo '<p class="mo">Mọi lượt ai đó động vào giờ công: bù vào ô trống, sửa đè lên giờ máy '
			. 'ghi, xoá dòng — kèm <b>giờ cũ</b>, <b>ai làm</b> và <b>vì sao</b>. '
			. 'Màn này <b>chỉ đọc</b>.</p></div>';

		if ( ! VHCC_Vai::duoc( $toi, self::QUYEN ) ) {
			echo '<div class="the"><div class="bao" style="margin:0">'
				. esc_html( VHCC_Vai::loi( $toi, self::QUYEN, 'Xem lịch sử sửa bảng công' ) )
				. '</div></div>';
			return;
		}

		$ds_cs = VHCC_Web::ds_coso_xem( $toi );
		$cs    = isset( $_GET['lcs'] ) ? VHCC_NhanSu::chuan_coso( wp_unslash( $_GET['lcs'] ) ) : '';
		if ( '' === $cs ) { $cs = VHCC_Web::coso_mac_dinh( $toi, $ds_cs ); }
		$th = isset( $_GET['lth'] ) ? sanitize_text_field( wp_unslash( $_GET['lth'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $th ) ) { $th = substr( (string) current_time( 'Y-m-d' ), 0, 7 ); }

		self::o_loc( $ds_cs, $cs, $th, $toi );

		if ( '' === $cs ) {
			echo '<div class="the"><p class="mo" style="margin:0">Chọn một cơ sở ở trên để xem.</p></div>';
			return;
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) {
			echo '<div class="the"><div class="bao" style="margin:0">Không có quyền cơ sở này.</div></div>';
			return;
		}

		self::the_o_gio( $toi, $cs, $th );
		self::the_don_bu( $toi, $cs );
		self::the_don_tuan( $toi, $cs );
	}

	private static function o_loc( $ds_cs, $cs, $th, $toi ) {
		echo '<div class="the"><form method="get" class="hang" style="gap:10px;margin:0">';
		echo '<input type="hidden" name="vhcc_qt" value="1">';
		echo '<input type="hidden" name="man" value="lich_su">';
		echo '<div><label for="lcs">Cơ sở</label>';
		VHCC_Web::o_chon_coso( 'lcs', $cs, $toi, $ds_cs, '— chọn —' );
		echo '</div>';
		echo '<div><label for="lth">Tháng</label>'
			. '<input id="lth" name="lth" type="month" value="' . esc_attr( $th ) . '"></div>';
		echo '<div style="align-self:flex-end"><button class="chinh">Xem</button></div>';
		echo '</form></div>';
	}

	/* ====================================================================== sổ gốc */

	/**
	 * TỪNG Ô GIỜ BỊ ĐỘNG VÀO — sổ gốc, không xoá được.
	 *
	 * ⚠️ IN CẢ GIỜ CŨ LẪN GIỜ MỚI. Chỉ in giờ mới thì sổ nói được "có người sửa" mà không dựng
	 *    lại được thứ đã mất — mà dựng lại chính là việc người ta mở sổ này để làm.
	 */
	private static function the_o_gio( $toi, $cs, $th ) {
		$ds = VHCC_Bu::ds_nhat_ky( $toi, $cs, $th );
		$so = count( $ds );
		if ( $so > self::TOI_DA ) { $ds = array_slice( $ds, 0, self::TOI_DA ); }

		echo '<div class="the"><h3 style="margin:0 0 8px">Từng ô giờ — ' . (int) $so . ' lượt</h3>';
		if ( ! $ds ) {
			echo '<p class="mo" style="margin:0">Tháng ' . esc_html( $th ) . ' chưa ai động vào giờ '
				. 'công của ' . esc_html( $cs ) . '. Giờ đang có đều là giờ máy ghi.</p></div>';
			return;
		}
		if ( $so > self::TOI_DA ) {
			echo '<div class="bao canh">Nhiều quá — đang hiện ' . self::TOI_DA . ' lượt mới nhất '
				. 'trên tổng ' . (int) $so . '. Chọn tháng hẹp hơn để xem hết.</div>';
		}

		echo '<div class="cuon"><table class="b"><thead><tr>'
			. '<th>Lúc</th><th>Ngày công</th><th>Mã NV</th><th>Ô</th><th>Việc</th>'
			. '<th>Giờ cũ</th><th>Giờ mới</th><th>Ai làm</th><th>Vì sao</th>'
			. '</tr></thead><tbody>';
		foreach ( $ds as $r ) {
			$viec = (string) $r['viec'];
			echo '<tr' . ( 'xoa' === $viec ? ' class="hong"' : '' ) . '>'
				. '<td class="mo">' . esc_html( (string) $r['tao_luc'] ) . '</td>'
				. '<td>' . esc_html( (string) $r['ngay'] ) . '</td>'
				. '<td>' . esc_html( (string) $r['ma_nv'] ) . '</td>'
				. '<td class="mo">' . esc_html( self::ten_o( (string) $r['o_gio'] ) ) . '</td>'
				. '<td>' . esc_html( self::ten_viec( $viec ) ) . '</td>'
				. '<td>' . self::gio( $r['gio_cu_giay'] ) . '</td>'
				. '<td>' . self::gio( $r['gio_giay'] ) . '</td>'
				. '<td class="mo">' . esc_html( (string) $r['nguoi_bu'] )
				. ( '' !== trim( (string) $r['vai_nguoi_bu'] )
					? ( '<br><span style="font-size:11px">' . esc_html( (string) $r['vai_nguoi_bu'] ) . '</span>' )
					: '' )
				. '</td>'
				. '<td class="mo">' . esc_html( (string) $r['ly_do'] ) . '</td>'
				. '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="mo" style="margin:8px 0 0;font-size:12px">Dòng <b>nền đỏ</b> là lượt '
			. '<b>xoá hẳn dòng công</b> — giờ cũ ở đây là thứ duy nhất còn dựng lại được nó. '
			. 'Sổ này ghi <b>trước</b> khi xoá, nên xoá xong vẫn còn dấu.</p>';
		echo '</div>';
	}

	private static function ten_o( $o ) {
		$b = array( 'vao' => 'giờ vào', 'ra' => 'giờ ra',
			'nghi_tu' => 'nghỉ từ', 'nghi_den' => 'nghỉ đến' );
		return isset( $b[ $o ] ) ? $b[ $o ] : ( '' !== $o ? $o : '—' );
	}

	private static function ten_viec( $v ) {
		$b = array( 'bu' => 'Bù vào ô trống', 'sua' => 'Sửa đè', 'xoa' => 'Xoá dòng',
			/* Nạp bảng công cũ, chế độ "chốt theo bảng" — xem `VHCC_Bu::nhat_ky_nap()`. */
			'nap' => 'Chốt theo bảng cũ' );
		return isset( $b[ $v ] ) ? $b[ $v ] : ( '' !== $v ? $v : '—' );
	}

	/** Giây → 'HH:mm', hoặc dấu gạch khi ô ấy vốn trống. */
	private static function gio( $giay ) {
		if ( null === $giay || '' === $giay ) { return '<span class="mo">trống</span>'; }
		$g = (int) $giay;
		if ( $g < 0 ) { return '<span class="mo">trống</span>'; }
		return '<b>' . esc_html( sprintf( '%02d:%02d', intdiv( $g, 3600 ) % 24, intdiv( $g % 3600, 60 ) ) )
			. '</b>';
	}

	/* ====================================================================== hai sổ đơn */

	private static function the_don_bu( $toi, $cs ) {
		global $wpdb;
		if ( ! class_exists( 'VHCC_XinBu' ) ) { return; }
		$ds = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_bu' )
			. ' WHERE LOWER(coso)=LOWER(%s) ORDER BY id DESC LIMIT 100', $cs ), ARRAY_A );
		$ds = is_array( $ds ) ? $ds : array();

		echo '<div class="the"><details><summary><b>Đơn xin bù giờ</b> — ' . count( $ds )
			. ' đơn gần đây <span class="mo">(bấm để mở)</span></summary>';
		if ( ! $ds ) {
			echo '<p class="mo" style="margin:10px 0 0">Chưa có đơn nào.</p></details></div>';
			return;
		}
		echo '<div class="cuon" style="margin-top:10px"><table class="b"><thead><tr>'
			. '<th>Gửi lúc</th><th>Ngày công</th><th>Mã NV</th><th>Họ tên</th><th>Giờ xin</th>'
			. '<th>Kết quả</th><th>CHT duyệt</th><th>Kế toán duyệt</th><th>Ghi chú</th>'
			. '</tr></thead><tbody>';
		foreach ( $ds as $d ) {
			$tt = (string) $d['trang_thai'];
			echo '<tr><td class="mo">' . esc_html( (string) $d['tao_luc'] ) . '</td>'
				. '<td>' . esc_html( (string) $d['ngay'] ) . '</td>'
				. '<td>' . esc_html( (string) $d['ma_nv'] ) . '</td>'
				. '<td>' . esc_html( (string) $d['ho_ten'] ) . '</td>'
				. '<td><b>' . esc_html( (string) $d['vao'] ) . '–' . esc_html( (string) $d['ra'] ) . '</b></td>'
				. '<td>' . ( VHCC_XinBu::DUYET === $tt ? '<b>' : '<span class="mo">' )
				. esc_html( VHCC_XinBu::ten_tt( $tt ) )
				. ( VHCC_XinBu::DUYET === $tt ? '</b>' : '</span>' ) . '</td>'
				. '<td class="mo">' . esc_html( (string) $d['cht_ten'] ) . '</td>'
				. '<td class="mo">' . esc_html( (string) $d['kt_ten'] ) . '</td>'
				. '<td class="mo">' . esc_html( '' !== trim( (string) $d['ly_do_choi'] )
					? (string) $d['ly_do_choi'] : (string) $d['ly_do'] ) . '</td></tr>';
		}
		echo '</tbody></table></div></details></div>';
	}

	private static function the_don_tuan( $toi, $cs ) {
		global $wpdb;
		if ( ! class_exists( 'VHCC_TuanCong' ) ) { return; }
		$ds = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'don_tuan' )
			. ' WHERE LOWER(coso)=LOWER(%s) ORDER BY id DESC LIMIT 60', $cs ), ARRAY_A );
		$ds = is_array( $ds ) ? $ds : array();

		echo '<div class="the"><details><summary><b>Đơn chỉnh bảng công theo tháng</b> — '
			. count( $ds ) . ' đơn gần đây <span class="mo">(bấm để mở)</span></summary>';
		if ( ! $ds ) {
			echo '<p class="mo" style="margin:10px 0 0">Chưa có đơn nào.</p></details></div>';
			return;
		}
		echo '<div class="cuon" style="margin-top:10px"><table class="b"><thead><tr>'
			. '<th>Kỳ</th><th>Người gửi</th><th>Gửi lúc</th><th>Số ô đổi</th><th>Kết quả</th>'
			. '<th>Kế toán</th><th>Lúc xử</th><th>Ghi chú</th></tr></thead><tbody>';
		foreach ( $ds as $d ) {
			$tt = (string) $d['trang_thai'];
			$kq = json_decode( (string) $d['ket_qua'], true );
			$ghi = '';
			if ( VHCC_TuanCong::DUYET === $tt && is_array( $kq ) ) {
				$ghi = (int) $kq['xong'] . ' ô đã ghi';
				if ( ! empty( $kq['trung'] ) ) { $ghi .= ' · ' . (int) $kq['trung'] . ' ô trùng sẵn'; }
				if ( ! empty( $kq['truot'] ) ) { $ghi .= ' · ' . count( $kq['truot'] ) . ' ô trượt'; }
			} elseif ( '' !== trim( (string) $d['ly_do_choi'] ) ) {
				$ghi = (string) $d['ly_do_choi'];
			}
			echo '<tr><td>' . esc_html( VHCC_TuanCong::ten_ky( $d['tu_ngay'], $d['den_ngay'] ) ) . '</td>'
				. '<td>' . esc_html( (string) $d['ten_gui'] ) . '</td>'
				. '<td class="mo">' . esc_html( (string) $d['gui_luc'] ) . '</td>'
				. '<td>' . (int) $d['so_doi'] . '</td>'
				. '<td>' . ( VHCC_TuanCong::DUYET === $tt ? '<b>' : '<span class="mo">' )
				. esc_html( isset( VHCC_TuanCong::TEN_TT[ $tt ] ) ? VHCC_TuanCong::TEN_TT[ $tt ] : $tt )
				. ( VHCC_TuanCong::DUYET === $tt ? '</b>' : '</span>' ) . '</td>'
				. '<td class="mo">' . esc_html( (string) $d['ten_duyet'] ) . '</td>'
				. '<td class="mo">' . esc_html( (string) $d['duyet_luc'] ) . '</td>'
				. '<td class="mo">' . esc_html( $ghi ) . '</td></tr>';
		}
		echo '</tbody></table></div></details></div>';
	}
}
