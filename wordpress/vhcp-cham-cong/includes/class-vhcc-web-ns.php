<?php
/**
 * MÀN NHÂN SỰ CỦA CỬA HÀNG — mỗi cơ sở một khu quản lý người của mình.
 *
 * =============================================================================================
 * VÌ SAO CÓ MÀN NÀY
 * =============================================================================================
 * Anh Thắng 31/08/2026: *"Mỗi cơ sở sẽ có thêm 1 hệ thống quản lý nhân sự con của mình. Để tại
 * cửa hàng trực tiếp quản lý dễ hơn"*.
 *
 * Cửa hàng trưởng vốn đã làm được bốn việc về người của mình — xem hồ sơ, thêm người mã tạm,
 * chấm bù, xếp lịch — nhưng chúng nằm RẢI ở bốn màn khác nhau, và mỗi màn lại bắt chọn lại cơ
 * sở. Người đứng ở cửa hàng không nghĩ theo màn, họ nghĩ theo NGƯỜI: "chị Vi đổi số điện thoại",
 * "cậu mới vào chưa có PIN". Màn này gom đúng một chỗ, quanh một danh sách người.
 *
 * =============================================================================================
 * CỬA MỞ TỚI ĐÂU — VÀ VÌ SAO KHÔNG RỘNG HƠN
 * =============================================================================================
 * Anh Thắng chốt 31/08: cửa hàng trưởng sửa được thông tin liên lạc và cấp PIN. Đúng bốn ô liên
 * lạc cộng ô PIN, và danh sách ấy khai ở `VHCC_NhanSu::O_CUA_HANG_SUA` — MỘT chỗ, để màn này và
 * cửa lưu không bao giờ lệch nhau.
 *
 * 🔴 LƯƠNG VÀ SỐ TÀI KHOẢN KHÔNG BAO GIỜ RA TỚI MÀN NÀY. Không phải ẩn bằng CSS: chúng không
 *    được đọc lên, nên không có gì để lộ dù ai mở công cụ nhà phát triển. Cùng luật với
 *    `ds_nhan_vien()`.
 *
 * 🔴 KHÔNG IN PIN, KỂ CẢ CHO CHÍNH CỬA HÀNG TRƯỞNG. Chỉ nói CÓ hay CHƯA. Trang này chạy ngoài
 *    internet và người ta chụp màn hình gửi cho nhau suốt.
 *
 * 🔴 NGƯỜI LÀM HAI NƠI HIỆN Ở KHU CỦA CẢ HAI, và cả hai đều sửa được (anh Thắng chốt) — nên
 *    dưới mỗi hồ sơ có NHẬT KÝ sửa, ghi ai sửa và sửa từ cửa hàng nào. Hai nơi cùng sửa mà
 *    không có sổ thì lúc một số điện thoại sai, câu trả lời duy nhất còn lại là "chắc bên kia
 *    sửa", tức là không có câu trả lời nào.
 *
 * ⚠️ GÁC Ở CẢ HAI CHỖ — lúc vẽ màn và lúc nhận việc POST. Chỉ gác lúc vẽ thì ai đoán ra tên
 *    `viec` là gửi thẳng POST được. `VHCC_NhanSu::sua_ho_so_coso()` gác lần nữa ở tầng dưới —
 *    hai tầng, không thay nhau.
 *
 * ⚠️ KHÔNG có lấy một dòng script — cùng luật với cả trang. Gập/xổ dùng `<details>`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_WebNS {

	/** Tham số của màn này phải sống sót qua mỗi lượt bấm — xem `VHCC_Web::THAM_SO`. */
	const THAM_SO = array( 'ncs', 'nma', 'nq' );

	/** Việc POST của màn này. Danh sách trắng: tên nào không có ở đây thì không phải việc của màn. */
	const VIEC = array( 'ns_luu' );

	public static function la_viec( $viec ) {
		return in_array( (string) $viec, self::VIEC, true );
	}

	/** Cơ sở đang mở. Rỗng = chưa chọn. */
	public static function coso_xem() {
		$cs = isset( $_GET['ncs'] ) ? sanitize_text_field( wp_unslash( $_GET['ncs'] ) ) : '';
		if ( '' === $cs && isset( $_POST['ncs'] ) ) {
			$cs = sanitize_text_field( wp_unslash( $_POST['ncs'] ) );
		}
		return VHCC_NhanSu::chuan_coso( $cs );
	}

	/**
	 * Cơ sở người này quản được.
	 *
	 * ⚠️ Ai có `cong_tat_ca` (Quản lý trở lên) thì thấy MỌI cơ sở đang có người — họ không khai
	 *    cơ sở nào trong thẻ phiên, và bắt họ tự gõ mã cơ sở là bắt nhớ 26 cái mã.
	 */
	public static function ds_coso_quan( $toi ) {
		if ( VHCC_Vai::duoc( $toi, 'cong_tat_ca' ) ) {
			$b  = VHCC_DB::t( 'nhan_vien' );
			$ra = array();
			foreach ( VHCC_DB::rows(
				"SELECT DISTINCT cua_hang AS v FROM $b WHERE cua_hang<>''"
				. " UNION SELECT DISTINCT coso_phu AS v FROM $b WHERE coso_phu<>''" ) as $x ) {
				foreach ( explode( ',', (string) $x['v'] ) as $m ) {
					$m = VHCC_NhanSu::chuan_coso( $m );
					if ( '' !== $m && ! in_array( $m, $ra, true ) ) { $ra[] = $m; }
				}
			}
			sort( $ra );
			return $ra;
		}
		return VHCC_NhanSu::ds_coso_cua( $toi );
	}

	/* ===================================================================================== việc */

	public static function viec( $viec, $toi ) {
		/* ⚠️ PHÁ THỬ KHÔNG BẮT ĐƯỢC VIỆC BỎ GÁC NÀY (pha70, 31/08/2026) — lý do: cửa dưới,
		   `VHCC_NhanSu::sua_ho_so_coso()`, tự gác lấy, nên bỏ gác ở đây thì việc vẫn bị chối,
		   chỉ khác câu chữ.
		   VẪN GIỮ, vì hàm này là CỬA CỦA CẢ MÀN, không phải của riêng một việc. Ngày thêm việc
		   thứ hai vào `VIEC` mà quên gác trong lõi của nó, dòng này là thứ duy nhất còn chặn —
		   và một danh sách việc bao giờ cũng dài ra. */
		if ( ! VHCC_Vai::duoc( $toi, 'ho_so_coso' ) ) {
			return array( array( 'loi' => VHCC_Vai::loi( $toi, 'ho_so_coso', 'Sửa hồ sơ' ) ) );
		}
		if ( 'ns_luu' !== $viec ) { return array(); }

		$ma  = isset( $_POST['nma'] ) ? sanitize_text_field( wp_unslash( $_POST['nma'] ) ) : '';
		$dat = array();
		foreach ( VHCC_NhanSu::O_CUA_HANG_SUA as $o => $ten ) {
			if ( isset( $_POST[ $o ] ) ) { $dat[ $o ] = wp_unslash( $_POST[ $o ] ); }
		}
		if ( isset( $_POST['pin_dang_nhap'] ) ) {
			$dat['pin_dang_nhap'] = wp_unslash( $_POST['pin_dang_nhap'] );
		}
		$r = VHCC_NhanSu::sua_ho_so_coso( $toi, $ma, $dat );
		return array( empty( $r['ok'] )
			? array( 'loi' => isset( $r['error'] ) ? $r['error'] : 'Không lưu được.' )
			: array( 'xong' => isset( $r['thong_bao'] ) ? $r['thong_bao'] : 'Đã lưu.' ) );
	}

	/* ====================================================================================== màn */

	public static function man( $ky, $toi ) {
		echo '<div class="the"><h2>Nhân sự cửa hàng</h2>';
		echo '<p class="mo">Người của cơ sở anh/chị phụ trách. Sửa được <b>thông tin liên lạc</b> '
			. 'và <b>cấp PIN đăng nhập</b>; lương và số tài khoản do kế toán giữ.</p></div>';

		if ( ! VHCC_Vai::duoc( $toi, 'ho_so_coso' ) ) {
			echo '<div class="the"><div class="bao loi" style="margin:0">'
				. esc_html( VHCC_Vai::loi( $toi, 'ho_so_coso', 'Quản lý nhân sự cửa hàng' ) )
				. '</div></div>';
			return;
		}

		$ds_cs = self::ds_coso_quan( $toi );
		$cs    = self::coso_xem();
		/* Quản một cơ sở thì không hỏi — mở thẳng. Hỏi một câu chỉ có một câu trả lời là bắt
		   người ta bấm thêm một lần mỗi ngày để nói lại điều máy đã biết. */
		if ( '' === $cs && 1 === count( $ds_cs ) ) { $cs = $ds_cs[0]; }

		self::the_chon( $ds_cs, $cs );

		if ( '' === $cs ) {
			echo '<div class="the"><div class="bao canh" style="margin:0">Chọn một cơ sở ở trên.'
				. '</div></div>';
			return;
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) {
			echo '<div class="the"><div class="bao loi" style="margin:0">Anh/chị không phụ trách '
				. 'cơ sở <b>' . esc_html( $cs ) . '</b>.</div></div>';
			return;
		}

		$ma_sua = isset( $_GET['nma'] ) ? sanitize_text_field( wp_unslash( $_GET['nma'] ) ) : '';
		if ( '' !== $ma_sua ) { self::the_sua( $ky, $toi, $cs, $ma_sua ); }

		self::the_ds( $toi, $cs, $ma_sua );
		self::the_loi_tat( $cs );
	}

	/** Ô chọn cơ sở + ô tìm. */
	private static function the_chon( $ds_cs, $cs ) {
		$q = isset( $_GET['nq'] ) ? sanitize_text_field( wp_unslash( $_GET['nq'] ) ) : '';
		echo '<div class="the"><form method="get" class="hang" style="margin:0">';
		if ( ! get_option( 'permalink_structure' ) ) { echo '<input type="hidden" name="vhcc_qt" value="1">'; }
		echo '<input type="hidden" name="man" value="ns_coso">';
		echo '<div><label for="n_cs">Cơ sở</label><select id="n_cs" name="ncs">';
		echo '<option value="">— chọn cơ sở —</option>';
		foreach ( $ds_cs as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . ( $x === $cs ? ' selected' : '' ) . '>'
				. esc_html( VHCC_NhanSu::ten_coso( $x ) ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="n_q">Tìm tên hoặc mã</label>'
			. '<input id="n_q" name="nq" value="' . esc_attr( $q ) . '"></div>';
		echo '<div><button class="chinh">Xem</button></div>';
		echo '</form></div>';
	}

	/** Bảng người của cơ sở. */
	private static function the_ds( $toi, $cs, $ma_sua ) {
		$q  = isset( $_GET['nq'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['nq'] ) ) ) : '';
		$ds = VHCC_NhanSu::ds_nhan_vien( $toi, $cs, $q );

		echo '<div class="the"><h3 style="margin-top:0">' . esc_html( VHCC_NhanSu::ten_coso( $cs ) )
			. ' — <b>' . count( $ds ) . '</b> người</h3>';
		if ( ! $ds ) {
			echo '<p class="mo">Chưa có ai ở cơ sở này' . ( '' !== $q ? ' khớp với ô tìm' : '' )
				. '.</p></div>';
			return;
		}
		echo '<div class="cuon"><table class="cc"><thead><tr><th>Mã NV</th><th>Họ tên</th>'
			. '<th>Chức vụ</th><th>SĐT</th><th>Đăng nhập</th><th>Trạng thái</th><th>Cơ sở</th>'
			. '<th></th></tr></thead><tbody>';
		foreach ( $ds as $r ) {
			$ma  = (string) $r['ma_nv'];
			$pin = trim( (string) $r['pin_dang_nhap'] );
			echo '<tr' . ( $ma === $ma_sua ? ' class="hong"' : '' ) . '>';
			echo '<td><code>' . esc_html( $ma ) . '</code></td>';
			echo '<td style="text-align:left">' . esc_html( $r['ho_ten'] ) . '</td>';
			echo '<td>' . esc_html( $r['chuc_vu'] ) . '</td>';
			echo '<td>' . esc_html( $r['sdt'] ) . '</td>';
			/* 🔴 CHỈ NÓI CÓ HAY CHƯA — không in PIN, không in độ dài đủ để đoán. */
			echo '<td>' . ( '' !== $pin
				? '<span class="k luc">có PIN</span>'
				: '<span class="k vang">chưa có</span>' ) . '</td>';
			echo '<td>' . esc_html( $r['trang_thai_lam_viec'] ) . '</td>';
			/* Người làm nhiều nơi: nói rõ ngay ở bảng, để người sửa biết mình đang đụng vào hồ
			   sơ mà cửa hàng khác cũng đang dùng. */
			$cs_ds = VHCC_NhanSu::ds_coso_hs( $r );
			echo '<td>' . ( count( $cs_ds ) > 1
				? '<span class="k tim">' . esc_html( implode( ' · ', $cs_ds ) ) . '</span>'
				: '<span class="mo">' . esc_html( implode( '', $cs_ds ) ) . '</span>' ) . '</td>';
			echo '<td><a class="nut" href="' . esc_url( add_query_arg(
				array( 'man' => 'ns_coso', 'ncs' => $cs, 'nma' => $ma ), VHCC_Web::url() ) )
				. '#nssua">Sửa</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/** Khối sửa một người. */
	private static function the_sua( $ky, $toi, $cs, $ma ) {
		$hs = VHCC_NhanSu::ho_so( $ma );
		if ( ! $hs ) {
			echo '<div class="the"><div class="bao loi" style="margin:0">Không thấy hồ sơ <code>'
				. esc_html( $ma ) . '</code>.</div></div>';
			return;
		}
		if ( ! VHCC_NhanSu::co_quyen_ho_so( $toi, $hs ) ) {
			echo '<div class="the"><div class="bao loi" style="margin:0">Hồ sơ này không thuộc cơ '
				. 'sở anh/chị phụ trách.</div></div>';
			return;
		}
		$cs_ds = VHCC_NhanSu::ds_coso_hs( $hs );

		echo '<div class="the" id="nssua"><h3 style="margin-top:0">Sửa: '
			. esc_html( $hs['ho_ten'] ) . ' <code>' . esc_html( $ma ) . '</code></h3>';
		if ( count( $cs_ds ) > 1 ) {
			/* Nói TRƯỚC khi người ta gõ, không phải sau khi đã lưu. */
			echo '<div class="bao canh">Người này làm ở <b>' . esc_html( implode( ' · ', $cs_ds ) )
				. '</b>. Sửa ở đây là đổi cho <b>cả</b> những cửa hàng kia — mọi lượt sửa đều vào '
				. 'sổ bên dưới.</div>';
		}
		echo '<form method="post" class="luoi">';
		/* `$ky` là chuỗi chữ ký THÔ, không phải sẵn HTML — phải tự bọc thành ô ẩn. `echo $ky;`
		   trần in nguyên 64 ký tự băm ra giữa biểu mẫu, đè lên nhãn ô đầu tiên, và lượt gửi
		   thì thiếu `name="ky"` nên bị chối. */
		echo '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo '<input type="hidden" name="viec" value="ns_luu">';
		echo '<input type="hidden" name="nma" value="' . esc_attr( $ma ) . '">';
		echo '<input type="hidden" name="ncs" value="' . esc_attr( $cs ) . '">';
		foreach ( VHCC_NhanSu::O_CUA_HANG_SUA as $o => $ten ) {
			echo '<label>' . esc_html( $ten ) . '<input name="' . esc_attr( $o ) . '" value="'
				. esc_attr( (string) $hs[ $o ] ) . '" style="width:100%"></label>';
		}
		/* ⚠️ KHÔNG điền sẵn PIN, và trống = GIỮ NGUYÊN — cùng luật với mọi ô PIN khác trong hệ. */
		$co_pin = ( '' !== trim( (string) $hs['pin_dang_nhap'] ) );
		echo '<label>PIN đăng nhập<input name="pin_dang_nhap" inputmode="numeric" '
			. 'autocomplete="off" placeholder="' . ( $co_pin ? 'đang có — để trống = giữ nguyên' : 'chưa có — gõ số mới' )
			. '" style="width:100%">'
			. '<span class="mo" style="font-size:12px">Gõ số mới để cấp lại. Để trống là giữ nguyên.'
			. '</span></label>';
		echo '<div style="flex:0 0 100%;grid-column:1/-1"><button class="chinh">Lưu</button> '
			. '<a class="nut" href="' . esc_url( add_query_arg(
				array( 'man' => 'ns_coso', 'ncs' => $cs ), VHCC_Web::url() ) ) . '">Xong</a></div>';
		echo '</form>';

		self::the_nhat_ky( $ma );
		echo '</div>';
	}

	/** Sổ sửa của một người. */
	private static function the_nhat_ky( $ma ) {
		$ds = VHCC_NhanSu::nhat_ky_ho_so( $ma, 15 );
		echo '<details style="margin-top:14px"><summary><b>Sổ sửa hồ sơ</b> <span class="mo">('
			. count( $ds ) . ' lượt gần nhất)</span></summary>';
		if ( ! $ds ) {
			echo '<p class="mo" style="margin:8px 0 0">Chưa có lượt sửa nào từ cửa hàng.</p>'
				. '</details>';
			return;
		}
		echo '<div class="cuon" style="margin-top:8px"><table class="cc"><thead><tr><th>Lúc</th>'
			. '<th>Ai</th><th>Từ cửa hàng</th><th>Ô</th><th>Cũ</th><th>Mới</th></tr></thead><tbody>';
		foreach ( $ds as $x ) {
			$ten_o = VHCC_NhanSu::ten_o_nhat_ky( $x['o'] );
			echo '<tr><td>' . esc_html( $x['luc'] ) . '</td>'
				. '<td>' . esc_html( $x['ai'] ) . '</td>'
				. '<td>' . esc_html( $x['tu_coso'] ) . '</td>'
				. '<td>' . esc_html( $ten_o ) . '</td>'
				. '<td>' . esc_html( '' !== $x['cu'] ? $x['cu'] : '—' ) . '</td>'
				. '<td>' . esc_html( '' !== $x['moi'] ? $x['moi'] : '—' ) . '</td></tr>';
		}
		echo '</tbody></table></div></details>';
	}

	/**
	 * Đường sang mấy việc khác về cùng những người này.
	 *
	 * 🔴 CHỞ SẴN CƠ SỞ SANG MÀN KIA. Không chở thì người ta vừa chọn cơ sở ở đây, bấm sang Bảng
	 *    công, và phải chọn lại — đúng cái phiền mà màn này sinh ra để bỏ.
	 */
	private static function the_loi_tat( $cs ) {
		echo '<div class="the"><h3 style="margin-top:0">Việc khác về người của cơ sở này</h3>';
		echo '<p style="margin:0;display:flex;gap:8px;flex-wrap:wrap">';
		echo '<a class="nut" href="' . esc_url( add_query_arg(
			array( 'man' => 'cham', 'ccs' => $cs ), VHCC_Web::url() ) ) . '">📋 Bảng công &amp; chấm bù</a>';
		echo '<a class="nut" href="' . esc_url( add_query_arg(
			array( 'man' => 'lich', 'lcs' => $cs ), VHCC_Web::url() ) ) . '">📅 Xếp lịch làm</a>';
		echo '</p></div>';
	}
}
