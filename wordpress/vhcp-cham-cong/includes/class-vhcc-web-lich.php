<?php
/**
 * MÀN PHÂN LỊCH LÀM TRÊN WEB — bản ngoài internet của màn wp-admin *Phân lịch làm*.
 *
 * =============================================================================================
 * VÌ SAO PHẢI RA WEB
 * =============================================================================================
 * Anh Thắng 27/08/2026, khi chuyển `/cham-cong/` thành trang dùng chung: *"mọi người truy cập
 * vào link này"*. Phân lịch là màn wp-admin CUỐI CÙNG còn sót lại sau khi Máy & Firmware ra web
 * — mà nó lại là màn thuộc về người ít có tài khoản WordPress nhất: **cửa hàng trưởng**.
 *
 * Mô hình năm bậc anh chốt giao cho họ đúng bốn việc, trong đó có *"lên lịch làm cho cửa hàng"*.
 * Để màn ấy nằm sau `manage_options` là giao một việc rồi khoá mất cửa vào — họ phải nhắn cho
 * người có tài khoản quản trị, đọc lịch qua điện thoại, và người kia gõ hộ.
 *
 * =============================================================================================
 * HAI MẶT CỦA CÙNG MỘT MÀN
 * =============================================================================================
 * Người xếp lịch và người BỊ xếp lịch nhìn cùng một thứ theo hai hướng ngược nhau, nên màn này
 * chia làm hai và tự chọn theo quyền:
 *
 *   • Có `lich_lam` (Cửa hàng trưởng trở lên) → xếp lịch cho cơ sở mình, duyệt yêu cầu đổi.
 *   • Chỉ có `cham_online` (Nhân viên)        → xem lịch CỦA CHÍNH MÌNH và xin đổi.
 *
 * 🔴 KHÔNG dựng hai tab riêng. Nhân viên vào tab "Phân lịch" mà thấy câu "không đủ quyền" là
 *    một câu trả lời vô ích: họ không định xếp lịch cho ai, họ chỉ muốn biết mai mình làm ca
 *    nào. Đúng câu hỏi ấy thì màn này trả lời được, và trả lời không cần quyền gì thêm.
 *
 * =============================================================================================
 * MẤY CHỐT KHÔNG ĐƯỢC NỚI
 * =============================================================================================
 * 🔴 GÁC Ở CẢ HAI CHỖ — lúc vẽ màn và lúc nhận việc POST. Ở wp-admin cửa là `manage_options`;
 *    ra web là mất lớp ấy, nên phải dựng lại bằng bậc vai. Chỉ gác lúc vẽ thì ai đoán ra tên
 *    `viec` là gửi thẳng POST được. `VHCC_Lich` cũng gác lần nữa ở tầng dưới — hai tầng, không
 *    thay nhau.
 *
 * 🔴 XIN ĐỔI LỊCH CHỈ XIN ĐƯỢC CHO CHÍNH MÌNH. `VHCC_Lich::xin_doi_lich()` cố ý không đòi quyền
 *    quản lý (chính họ xin cho họ), nên nó nhận `ma_nv` từ lời gọi — tức lớp này phải ép mã ấy
 *    bằng mã trong thẻ phiên, không lấy từ ô nhập. Không ép thì ai cũng gửi được một yêu cầu
 *    đứng tên người khác, và người duyệt ký vào một chuyện người kia không hề xin.
 *
 * ⚠️ KHÔNG có lấy một dòng script — cùng luật với cả màn quản trị. Gập/xổ dùng `<details>`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_WebLich {

	/** Tham số của màn này phải sống sót qua mỗi lượt bấm — xem `VHCC_Web::THAM_SO`. */
	const THAM_SO = array( 'lcs', 'ltu', 'lden' );

	/** Việc POST của màn này. Danh sách trắng: tên nào không có ở đây thì không phải việc của màn. */
	const VIEC = array( 'lich_xep', 'lich_xoa', 'lich_duyet', 'lich_tu_choi', 'lich_xin',
		'lich_ca', 'lich_loai_viec', 'lich_cs_bat' );

	public static function la_viec( $viec ) {
		return in_array( (string) $viec, self::VIEC, true );
	}

	/* ===================================================================== khoảng ngày đang xem */

	public static function coso_xem() {
		$x = isset( $_GET['lcs'] ) ? sanitize_text_field( wp_unslash( $_GET['lcs'] ) ) : '';
		if ( '' === $x && isset( $_POST['lcs'] ) ) { $x = sanitize_text_field( wp_unslash( $_POST['lcs'] ) ); }
		return VHCC_NhanSu::chuan_coso( $x );
	}

	/**
	 * Khoảng ngày đang xem, mặc định là tháng này.
	 *
	 * ⚠️ Ngày gõ sai dạng thì rơi về mặc định, KHÔNG đẩy thẳng xuống câu truy vấn. `ds_lich()`
	 *    có `prepare` nên không vỡ, nhưng một chuỗi rác vẫn ra bảng rỗng — và bảng rỗng trông y
	 *    hệt "chưa xếp lịch ngày nào", tức người ta đi xếp lại lịch đã có.
	 */
	public static function khoang() {
		$mac = array( gmdate( 'Y-m-01' ), gmdate( 'Y-m-t' ) );
		$doc = function ( $k, $md ) {
			$v = '';
			if ( isset( $_GET[ $k ] ) )       { $v = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); }
			elseif ( isset( $_POST[ $k ] ) )  { $v = sanitize_text_field( wp_unslash( $_POST[ $k ] ) ); }
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : $md;
		};
		$tu  = $doc( 'ltu', $mac[0] );
		$den = $doc( 'lden', $mac[1] );
		/* Gõ ngược đầu đuôi thì đảo lại, đừng trả bảng rỗng: người ta gõ nhầm chứ không định hỏi
		   một khoảng rỗng, mà bảng rỗng thì không nói được là mình vừa gõ nhầm. */
		if ( $tu > $den ) { $x = $tu; $tu = $den; $den = $x; }
		return array( $tu, $den );
	}

	/* ===================================================================== nhận việc POST */

	public static function viec( $viec, $toi ) {
		$cs   = self::coso_xem();
		$lay  = function ( $k ) {
			return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : '';
		};

		/* 🔴 XIN ĐỔI: mã NV LẤY TỪ THẺ PHIÊN, không lấy từ ô nhập. Xem chú thích đầu tệp. */
		if ( 'lich_xin' === $viec ) {
			$ma_toi = trim( (string) ( isset( $toi['ma_nv'] ) ? $toi['ma_nv'] : '' ) );
			if ( '' === $ma_toi ) {
				return array( array( 'loi' => 'Tài khoản này chưa gắn Mã NV nên chưa xin đổi lịch được. '
					. 'Nhờ Kế toán khai Mã NV vào hồ sơ giúp.' ) );
			}
			$r = VHCC_Lich::xin_doi_lich( $toi, array(
				'coso'  => '' !== $cs ? $cs : ( isset( $toi['coso'] ) ? $toi['coso'] : '' ),
				'ma_nv' => $ma_toi,
				'ho_ten' => isset( $toi['name'] ) ? $toi['name'] : '',
				'ngay'  => $lay( 'l_ngay' ),
				'ca'    => $lay( 'l_ca' ),
				'viec_moi' => $lay( 'l_viec' ),
				'doi_sang_ngay' => $lay( 'l_sang' ),
				'ly_do' => $lay( 'l_ly_do' ),
			) );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			return array( array( 'xong' => 'Đã gửi yêu cầu ' . $r['maYc'] . '. Cửa hàng trưởng duyệt '
				. 'thì lịch mới đổi thật — trước lúc ấy thì lịch cũ vẫn là lịch đang chạy.' ) );
		}

		/* Mọi việc còn lại đòi `lich_lam`. Gác NGAY ĐÂY, đừng tin vào việc màn không vẽ nút. */
		if ( ! VHCC_Vai::duoc( $toi, 'lich_lam' ) ) {
			return array( array( 'loi' => VHCC_Vai::loi( $toi, 'lich_lam', 'Xếp lịch làm việc' ) ) );
		}

		if ( 'lich_xep' === $viec ) {
			$r = VHCC_Lich::xep_lich( $toi, $cs, array( array(
				'ngay' => $lay( 'l_ngay' ), 'ma_nv' => $lay( 'l_ma' ), 'ho_ten' => $lay( 'l_ten' ),
				'ca' => $lay( 'l_ca' ), 'viec' => $lay( 'l_viec' ) ) ) );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			/* `so` = 0 nghĩa là ô bị BỎ QUA vì thiếu ngày hoặc thiếu mã — nói ra, đừng báo "đã
			   lưu" cho một lượt không ghi gì. Người ta đóng màn đi và tưởng lịch đã có. */
			if ( empty( $r['so'] ) ) {
				return array( array( 'loi' => 'Chưa ghi ô nào — thiếu Ngày (dạng yyyy-mm-dd) hoặc Mã NV.' ) );
			}
			return array( array( 'xong' => 'Đã xếp ô lịch cho ' . $lay( 'l_ma' ) . ' ngày ' . $lay( 'l_ngay' )
				. '. Lịch là DỰ ĐỊNH — nó không ghi gì vào bảng chấm công.' ) );
		}

		if ( 'lich_xoa' === $viec ) {
			$r = VHCC_Lich::xoa_o_lich( $toi, $cs, $lay( 'l_ngay' ), $lay( 'l_ma' ), $lay( 'l_ca' ) );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] )
				: array( 'xong' => 'Đã xoá ô lịch.' ) );
		}

		if ( 'lich_duyet' === $viec || 'lich_tu_choi' === $viec ) {
			$dong_y = ( 'lich_duyet' === $viec );
			$r = VHCC_Lich::duyet( $toi, $lay( 'l_ma_yc' ), $dong_y );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			return array( array( 'xong' => $dong_y
				? 'Đã duyệt — và ĐÃ GHI THẬT vào lịch, không chỉ đổi trạng thái. Có "đổi sang ngày" '
					. 'thì ngày cũ được để trống việc, ngày mới nhận việc.'
				: 'Đã từ chối. Lịch giữ nguyên như cũ.' ) );
		}

		if ( 'lich_ca' === $viec || 'lich_loai_viec' === $viec ) {
			$ds = preg_split( '/[\r\n,;]+/', (string) $lay( 'l_ds' ), -1, PREG_SPLIT_NO_EMPTY );
			$r  = ( 'lich_ca' === $viec )
				? VHCC_Lich::dat_ca( $toi, $ds ) : VHCC_Lich::dat_loai_viec( $toi, $ds );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			$b = array( array( 'xong' => 'Đã lưu ' . ( 'lich_ca' === $viec ? 'danh sách ca.' : 'loại công việc.' ) ) );
			/* 🔴 Ô LỊCH MỒ CÔI PHẢI ĐƯỢC KÊU RA. `ca` là một phần KHOÁ của ô lịch, nên đổi tên ca
			   KHÔNG đổi tên trong những ô đã xếp — chúng giữ tên cũ và trông như ca lạ. */
			if ( ! empty( $r['oMoCoi'] ) ) {
				$keo = array();
				foreach ( (array) $r['oMoCoi'] as $ten_ca => $so_o ) {
					$keo[] = $ten_ca . ': ' . (int) $so_o . ' ô';
				}
				$b[] = array( 'loi' => 'Còn ' . count( $keo ) . ' tên ca đang nằm trong lịch đã xếp mà '
					. 'không còn trong danh sách — ' . implode( ' · ', $keo ) . '. Đổi tên ca KHÔNG đổi '
					. 'tên trong ô đã xếp; sửa từng ô hoặc thêm lại tên cũ vào danh sách.' );
			}
			return $b;
		}

		if ( 'lich_cs_bat' === $viec ) {
			$r = VHCC_Lich::dat_coso_bat_lich( $toi,
				isset( $_POST['l_cs'] ) ? (array) wp_unslash( $_POST['l_cs'] ) : array() );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] )
				: array( 'xong' => 'Đã lưu danh sách cơ sở bật phân lịch. Tắt lịch KHÔNG xoá ô nào '
					. 'đã xếp — chỉ ẩn màn xếp lịch đi.' ) );
		}

		return array();
	}

	/* ===================================================================== màn */

	public static function man( $ky, $toi ) {
		$xep = VHCC_Vai::duoc( $toi, 'lich_lam' );

		echo '<div class="the"><h2>Phân lịch làm việc</h2>';
		echo '<p class="mo">Lịch là <b>dự định</b>, chấm công là <b>thực tế</b>. Xếp lịch '
			. '<b>không ghi gì</b> vào bảng chấm công — ghi thì bảng lương sẽ thấy những ngày có '
			. 'hàng mà không có giờ, trông y như “đi làm mà quên chấm”, và thành trả tiền theo '
			. 'dự định.</p></div>';

		if ( ! $xep ) {
			self::the_cua_toi( $ky, $toi );
			return;
		}

		self::the_chon( $ky, $toi );
		self::the_cho_duyet( $ky, $toi );

		$cs = self::coso_xem();
		if ( '' === $cs ) {
			echo '<div class="the"><div class="bao canh" style="margin:0">Chọn một cơ sở ở trên để '
				. 'xem và xếp lịch.</div></div>';
			return;
		}
		self::the_xep( $ky, $toi, $cs );
		self::the_da_xep( $ky, $toi, $cs );
		self::the_cau_hinh( $ky, $toi );
	}

	/** Ô chọn cơ sở + khoảng ngày. */
	private static function the_chon( $ky, $toi ) {
		$cs = self::coso_xem();
		list( $tu, $den ) = self::khoang();
		echo '<div class="the"><form method="get" class="hang" style="margin:0">';
		echo '<input type="hidden" name="vhcc_qt" value="1">';
		echo '<input type="hidden" name="man" value="lich">';
		echo '<div><label for="l_cs">Cơ sở</label><select id="l_cs" name="lcs">';
		echo '<option value="">— chọn cơ sở —</option>';
		foreach ( self::ds_coso_xep( $toi ) as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . ( $x === $cs ? ' selected' : '' ) . '>'
				. esc_html( VHCC_NhanSu::ten_coso( $x ) ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="l_tu">Từ ngày</label>'
			. '<input id="l_tu" type="date" name="ltu" value="' . esc_attr( $tu ) . '"></div>';
		echo '<div><label for="l_den">Đến ngày</label>'
			. '<input id="l_den" type="date" name="lden" value="' . esc_attr( $den ) . '"></div>';
		echo '<div><button class="chinh">Xem</button></div>';
		echo '</form></div>';
	}

	/**
	 * Cơ sở người này được xếp lịch.
	 *
	 * ⚠️ Cắt theo quyền cơ sở, không liệt kê cả chuỗi. Cửa hàng trưởng thấy 21 cơ sở trong ô chọn
	 *    rồi chọn nhầm một cái không phải của mình thì màn hình chỉ đáp "không có quyền" — mà họ
	 *    không hiểu vì sao tên ấy lại nằm trong ô chọn ngay từ đầu.
	 */
	public static function ds_coso_xep( $toi ) {
		$moi = VHCC_NhanSu::ds_coso();
		if ( VHCC_Vai::duoc( $toi, 'cong_tat_ca' ) ) { return $moi; }
		$ra = array();
		foreach ( $moi as $x ) {
			if ( VHCC_NhanSu::co_quyen_coso( $toi, $x ) ) { $ra[] = $x; }
		}
		return $ra;
	}

	/** Yêu cầu xin đổi đang chờ duyệt. */
	private static function the_cho_duyet( $ky, $toi ) {
		$yc = VHCC_Lich::ds_doi_lich( $toi, true );
		echo '<div class="the"><details' . ( $yc ? ' open' : '' ) . '><summary><b>Xin đổi lịch — chờ duyệt</b> '
			. '<span class="mo">(' . count( $yc ) . ')</span></summary>';
		if ( ! $yc ) {
			echo '<p class="mo">Không có yêu cầu nào chờ duyệt.</p></details></div>';
			return;
		}
		echo '<p class="mo">Duyệt là <b>ghi thật</b> vào lịch, không chỉ đổi trạng thái. Có “đổi sang '
			. 'ngày” thì ngày cũ được để <b>trống việc</b> và ngày mới nhận việc — không thì người đó '
			. 'bị xếp cả hai ngày.</p>';
		echo '<div class="cuon"><table class="cc"><thead><tr><th>Cơ sở</th><th>Mã NV</th><th>Họ tên</th>'
			. '<th>Ngày</th><th>Ca</th><th>Việc mới</th><th>Đổi sang</th><th>Lý do</th><th>Người xin</th>'
			. '<th></th></tr></thead><tbody>';
		foreach ( $yc as $r ) {
			echo '<tr><td>' . esc_html( VHCC_NhanSu::ten_coso( $r['coso'] ) ) . '</td>';
			echo '<td><b>' . esc_html( $r['ma_nv'] ) . '</b></td>';
			echo '<td style="text-align:left">' . esc_html( $r['ho_ten'] ) . '</td>';
			echo '<td>' . esc_html( $r['ngay'] ) . '</td><td>' . esc_html( $r['ca'] ) . '</td>';
			echo '<td>' . esc_html( $r['viec_moi'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['doi_sang_ngay'] ) . '</td>';
			echo '<td style="text-align:left">' . esc_html( $r['ly_do'] ) . '</td>';
			echo '<td>' . esc_html( $r['nguoi_xin'] ) . '</td><td>';
			foreach ( array( 'lich_duyet' => 'Duyệt', 'lich_tu_choi' => 'Từ chối' ) as $v => $nhan ) {
				echo '<form method="post" style="display:inline;margin:0">'
					. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
					. '<input type="hidden" name="man" value="lich">'
					. '<input type="hidden" name="l_ma_yc" value="' . esc_attr( $r['ma_yc'] ) . '">'
					. '<button name="viec" value="' . esc_attr( $v ) . '"'
					. ( 'lich_duyet' === $v ? ' class="chinh"' : '' ) . '>' . esc_html( $nhan )
					. '</button></form> ';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div></details></div>';
	}

	/** Xếp một ô lịch. */
	private static function the_xep( $ky, $toi, $cs ) {
		$cf = VHCC_Lich::cau_hinh( $toi );
		echo '<div class="the"><h3 style="margin:0 0 6px">Xếp một ô lịch — ' . esc_html( VHCC_NhanSu::ten_coso( $cs ) ) . '</h3>';
		echo '<p class="mo">Khoá của một ô là <b>(cơ sở, ngày, mã NV, ca)</b> — bốn thứ. Nhờ có '
			. '<b>ca</b> trong khoá mà một người làm hai ca trong cùng một ngày giữ được cả hai ô; '
			. 'bỏ “ca” ra là ca trước bị ghi đè mất mà ô vẫn có dữ liệu nên không ai thấy.</p>';
		echo '<form method="post" class="hang" style="margin:0;align-items:flex-end">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo '<input type="hidden" name="man" value="lich">';
		echo '<input type="hidden" name="lcs" value="' . esc_attr( $cs ) . '">';
		echo '<div><label for="lx_ngay">Ngày *</label>'
			. '<input id="lx_ngay" type="date" name="l_ngay" value="' . esc_attr( gmdate( 'Y-m-d' ) ) . '" required></div>';
		echo '<div><label for="lx_ma">Mã NV *</label>'
			. '<input id="lx_ma" name="l_ma" maxlength="20" required></div>';
		echo '<div><label for="lx_ten">Họ tên</label><input id="lx_ten" name="l_ten"></div>';
		echo '<div><label for="lx_ca">Ca</label>' . self::o_chon( 'lx_ca', 'l_ca', (array) $cf['ca'] ) . '</div>';
		echo '<div><label for="lx_viec">Việc</label>'
			. self::o_chon( 'lx_viec', 'l_viec', (array) $cf['loaiViec'], true ) . '</div>';
		echo '<div><button class="chinh" name="viec" value="lich_xep">Lưu ô lịch</button></div>';
		echo '</form></div>';
	}

	/**
	 * Ô chọn từ danh sách đã khai. Danh sách rỗng thì thành ô GÕ TAY, không thành ô chọn rỗng.
	 *
	 * ⚠️ Một `<select>` không có `<option>` nào là ô không bấm được: người ta thấy có ô mà không
	 *    nhập nổi gì, và không có gì nói cho họ biết là phải đi khai danh sách trước.
	 */
	private static function o_chon( $id, $ten, $ds, $cho_trong = false ) {
		if ( ! $ds ) {
			return '<input id="' . esc_attr( $id ) . '" name="' . esc_attr( $ten ) . '" '
				. 'placeholder="chưa khai danh sách — gõ tay">';
		}
		$h = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $ten ) . '">';
		if ( $cho_trong ) { $h .= '<option value="">—</option>'; }
		foreach ( $ds as $x ) {
			$h .= '<option value="' . esc_attr( $x ) . '">' . esc_html( $x ) . '</option>';
		}
		return $h . '</select>';
	}

	/** Lịch đã xếp trong khoảng đang xem. */
	private static function the_da_xep( $ky, $toi, $cs ) {
		list( $tu, $den ) = self::khoang();
		$ds = VHCC_Lich::ds_lich( $cs, $tu, $den );
		echo '<div class="the"><h3 style="margin:0 0 6px">Lịch đã xếp <span class="mo">('
			. count( $ds ) . ' ô · ' . esc_html( $tu ) . ' → ' . esc_html( $den ) . ')</span></h3>';
		if ( ! $ds ) {
			echo '<p class="mo">Chưa có ô lịch nào trong khoảng này.</p></div>';
			return;
		}
		echo '<div class="cuon"><table class="cc"><thead><tr><th>Ngày</th><th>Mã NV</th><th>Họ tên</th>'
			. '<th>Ca</th><th>Việc</th><th>Người xếp</th><th></th></tr></thead><tbody>';
		foreach ( $ds as $r ) {
			echo '<tr><td>' . esc_html( $r['ngay'] ) . '</td>';
			echo '<td><b>' . esc_html( $r['ma_nv'] ) . '</b></td>';
			echo '<td style="text-align:left">' . esc_html( $r['ho_ten'] ) . '</td>';
			echo '<td>' . esc_html( $r['ca'] ) . '</td>';
			echo '<td style="text-align:left">' . esc_html( $r['viec'] ) . '</td>';
			echo '<td>' . esc_html( $r['nguoi_xep'] ) . '</td>';
			echo '<td><form method="post" style="margin:0">'
				. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
				. '<input type="hidden" name="man" value="lich">'
				. '<input type="hidden" name="lcs" value="' . esc_attr( $cs ) . '">'
				. '<input type="hidden" name="l_ngay" value="' . esc_attr( $r['ngay'] ) . '">'
				. '<input type="hidden" name="l_ma" value="' . esc_attr( $r['ma_nv'] ) . '">'
				. '<input type="hidden" name="l_ca" value="' . esc_attr( $r['ca'] ) . '">'
				. '<button name="viec" value="lich_xoa">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/** Ca · loại việc · cơ sở bật lịch. Dùng chung toàn chuỗi nên gác ở bậc cao hơn. */
	private static function the_cau_hinh( $ky, $toi ) {
		$cf = VHCC_Lich::cau_hinh( $toi );
		echo '<div class="the"><details><summary><b>Cấu hình lịch</b> '
			. '<span class="mo">(ca · loại việc · cơ sở bật lịch — dùng chung mọi cơ sở)</span></summary>';
		if ( empty( $cf['suaDuocCauHinh'] ) ) {
			echo '<p class="mo">Ba danh sách này dùng chung cho <b>mọi cơ sở</b>, nên sửa chúng cần '
				. 'bậc <b>Quản lý</b> trở lên. Cửa hàng trưởng xếp lịch cửa hàng mình, nhưng không '
				. 'đặt lại tên ca cho cả chuỗi.</p>';
			echo '<p class="mo">Đang khai: <b>' . esc_html( implode( ' · ', (array) $cf['ca'] ) ) . '</b>'
				. ( $cf['loaiViec'] ? ' — việc: ' . esc_html( implode( ' · ', (array) $cf['loaiViec'] ) ) : '' )
				. '.</p></details></div>';
			return;
		}
		echo '<div class="hang" style="align-items:flex-start;gap:24px">';

		echo '<form method="post" style="flex:1 1 240px">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="man" value="lich">'
			. '<label for="lc_ca"><b>Danh sách ca</b> — mỗi dòng một ca</label>'
			. '<textarea id="lc_ca" name="l_ds" rows="4" style="width:100%">'
			. esc_textarea( implode( "\n", (array) $cf['ca'] ) ) . '</textarea>'
			. '<p class="mo">⚠️ Đổi <b>tên</b> một ca KHÔNG đổi tên trong những ô lịch đã xếp — '
			. '<code>ca</code> là một phần khoá của ô lịch. Hệ sẽ báo ra số ô đang dùng tên vừa bị '
			. 'bỏ, chứ không để im.</p>'
			. '<button name="viec" value="lich_ca">Lưu ca</button></form>';

		echo '<form method="post" style="flex:1 1 240px">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="man" value="lich">'
			. '<label for="lc_lv"><b>Loại công việc</b> — mỗi dòng một loại</label>'
			. '<textarea id="lc_lv" name="l_ds" rows="4" style="width:100%">'
			. esc_textarea( implode( "\n", (array) $cf['loaiViec'] ) ) . '</textarea>'
			. '<button name="viec" value="lich_loai_viec">Lưu loại việc</button></form>';

		echo '<form method="post" style="flex:1 1 240px">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="man" value="lich">'
			. '<div><b>Cơ sở bật phân lịch</b></div>';
		foreach ( (array) $cf['moiCoSo'] as $x ) {
			echo '<label style="display:block;font-size:13px"><input type="checkbox" name="l_cs[]" value="'
				. esc_attr( $x ) . '"' . ( in_array( $x, (array) $cf['coSoBatLich'], true ) ? ' checked' : '' )
				. '> ' . esc_html( VHCC_NhanSu::ten_coso( $x ) ) . '</label>';
		}
		echo '<p class="mo">Tắt lịch của một cơ sở <b>không xoá</b> ô lịch nào đã xếp — chỉ ẩn màn '
			. 'xếp lịch. Xoá là mất lịch những ngày sắp tới, mà bật lại thì không dựng lại được.</p>'
			. '<button name="viec" value="lich_cs_bat">Lưu</button></form>';

		echo '</div></details></div>';
	}

	/* ===================================================================== mặt của nhân viên */

	/**
	 * LỊCH CỦA CHÍNH MÌNH + xin đổi.
	 *
	 * 🔴 Không đòi quyền gì thêm: người ta chỉ hỏi "mai tôi làm ca nào". Nhưng phải có Mã NV
	 *    trong thẻ phiên — không có mã thì không biết lấy lịch của ai, và cũng không xin đổi
	 *    được vì lúc duyệt không biết xếp cho ai.
	 */
	private static function the_cua_toi( $ky, $toi ) {
		$ma = trim( (string) ( isset( $toi['ma_nv'] ) ? $toi['ma_nv'] : '' ) );
		if ( '' === $ma ) {
			echo '<div class="the"><div class="bao canh" style="margin:0">Tài khoản này chưa gắn '
				. '<b>Mã NV</b> nên chưa xem được lịch của mình. Nhờ Kế toán khai Mã NV vào hồ sơ giúp.'
				. '</div></div>';
			return;
		}
		$cs = trim( (string) ( isset( $toi['coso'] ) ? $toi['coso'] : '' ) );
		$cs = VHCC_NhanSu::chuan_coso( $cs );
		list( $tu, $den ) = self::khoang();

		echo '<div class="the"><h3 style="margin:0 0 6px">Lịch của tôi <span class="mo">('
			. esc_html( $tu ) . ' → ' . esc_html( $den ) . ')</span></h3>';
		$cua_toi = array();
		if ( '' !== $cs ) {
			foreach ( VHCC_Lich::ds_lich( $cs, $tu, $den ) as $r ) {
				if ( 0 === strcasecmp( trim( (string) $r['ma_nv'] ), $ma ) ) { $cua_toi[] = $r; }
			}
		}
		if ( ! $cua_toi ) {
			echo '<p class="mo">Chưa có ô lịch nào của <b>' . esc_html( $ma ) . '</b> trong khoảng này'
				. ( '' === $cs ? ' (hồ sơ chưa khai cơ sở)' : ' ở ' . esc_html( VHCC_NhanSu::ten_coso( $cs ) ) )
				. '.</p>';
		} else {
			echo '<div class="cuon"><table class="cc"><thead><tr><th>Ngày</th><th>Ca</th><th>Việc</th>'
				. '<th>Người xếp</th></tr></thead><tbody>';
			foreach ( $cua_toi as $r ) {
				echo '<tr><td>' . esc_html( $r['ngay'] ) . '</td><td>' . esc_html( $r['ca'] ) . '</td>';
				echo '<td style="text-align:left">' . esc_html( $r['viec'] ) . '</td>';
				echo '<td>' . esc_html( $r['nguoi_xep'] ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';

		$cf = VHCC_Lich::cau_hinh( $toi );
		echo '<div class="the"><details><summary><b>Xin đổi lịch</b></summary>';
		echo '<p class="mo">Yêu cầu gửi lên <b>cửa hàng trưởng</b>. Duyệt xong thì lịch mới đổi thật '
			. '— trước lúc ấy lịch cũ vẫn là lịch đang chạy, cứ theo nó mà đi làm.</p>';
		echo '<form method="post" class="hang" style="margin:0;align-items:flex-end">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo '<input type="hidden" name="man" value="lich">';
		echo '<div style="flex:0 0 auto"><label>Xin cho</label><b>' . esc_html( $ma ) . '</b>'
			. '<div class="mo" style="font-size:11.5px">lấy từ tài khoản đang đăng nhập</div></div>';
		echo '<div><label for="lq_ngay">Ngày đang xếp *</label>'
			. '<input id="lq_ngay" type="date" name="l_ngay" required></div>';
		echo '<div><label for="lq_ca">Ca</label>' . self::o_chon( 'lq_ca', 'l_ca', (array) $cf['ca'] ) . '</div>';
		echo '<div><label for="lq_sang">Đổi sang ngày</label>'
			. '<input id="lq_sang" type="date" name="l_sang"></div>';
		echo '<div><label for="lq_viec">Việc mới</label>'
			. self::o_chon( 'lq_viec', 'l_viec', (array) $cf['loaiViec'], true ) . '</div>';
		echo '<div style="flex:1 1 220px"><label for="lq_ly">Lý do *</label>'
			. '<input id="lq_ly" name="l_ly_do" required minlength="5" style="width:100%" '
			. 'placeholder="VD: nhà có việc, đã nhờ được người đổi ca"></div>';
		echo '<div><button class="chinh" name="viec" value="lich_xin">Gửi yêu cầu</button></div>';
		echo '</form></details></div>';
	}
}
