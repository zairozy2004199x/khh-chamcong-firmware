<?php
/**
 * MÀN "ĐƠN DUYỆT CHỈNH BẢNG CÔNG LƯƠNG" — kế toán ngồi đây duyệt tệp cửa hàng gửi lên.
 *
 * Anh Thắng 18/09/2026: *"Tài khoản kế toán chỉnh định quản lý bảng lương sẽ hiện chỗ này: Đơn
 * duyệt chỉnh bảng công lương"*.
 *
 * =============================================================================================
 * 🔴 MÀN NÀY BÀY RA TỪNG Ô SẼ ĐỔI, KHÔNG BÀY "CÓ 37 THAY ĐỔI"
 * =============================================================================================
 * Bấm Duyệt là ghi thẳng vào bảng công, không quay lại được, rồi khoá tháng — anh Thắng: *"chỉ
 * duyệt và gửi 1 lần) nên cần đảm bảo chính xác"*. Một con số tổng thì không ai soát được cái
 * gì; người ta bấm Duyệt vì đằng nào cũng không đọc được. Nên bảng dưới in **từng ô một**: ai,
 * ngày nào, giờ cũ → giờ mới, và lý do người gửi ghi.
 *
 * ⚠️ MỌI THỨ IN RA ĐÂY ĐỀU LÀ CHỮ NGƯỜI NGOÀI GÕ VÀO EXCEL. Lý do sửa, họ tên — tất cả đi qua
 *    `esc_html()`. Sót một chỗ là một dòng `<script>` gõ trong Excel chạy trên màn kế toán,
 *    kèm theo cả thẻ phiên của họ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_WebDonTuan {

	const VIEC = array( 'dt_duyet', 'dt_choi', 'dt_nap', 'xb_duyet', 'xb_choi', 'xb_cht' );

	public static function la_viec( $viec ) { return in_array( (string) $viec, self::VIEC, true ); }

	/**
	 * NHẬN VIỆC POST.
	 *
	 * 🔴 Gác quyền NGAY ĐÂY. Màn không vẽ nút chỉ là không mời; POST thì ai gửi cũng tới.
	 */
	public static function viec( $viec, $toi ) {
		/* 🔴 NẠP TỆP LÀ VIỆC CỦA CỬA HÀNG TRƯỞNG, cửa khác hẳn hai việc dưới. Gộp chung một
		   chốt `sua_gio` là chối đúng người mà cả quy trình này dựng lên cho. `VHCC_TuanCong::nap()`
		   tự hỏi `cong_coso` + phạm vi cơ sở + tháng đã khoá chưa, ngay dòng đầu. */
		if ( 'dt_nap' === $viec ) { return self::nhan_tep( $toi ); }

		/* Cấp MỘT — cửa hàng trưởng. Cửa khác hẳn hai việc của kế toán ở dưới; `duyet_cht()` tự
		   hỏi `cong_coso` + phạm vi cơ sở + không-tự-duyệt-đơn-mình ngay dòng đầu. */
		if ( 'xb_cht' === $viec ) {
			$id = isset( $_POST['xb_id'] ) ? (int) $_POST['xb_id'] : 0;
			$ly = isset( $_POST['xb_ly_do'] ) ? sanitize_text_field( wp_unslash( $_POST['xb_ly_do'] ) ) : '';
			$oke = ! empty( $_POST['xb_ok'] );
			$r  = VHCC_XinBu::duyet_cht( $toi, $id, $oke, $ly );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] )
				: array( 'ok' => true, 'thong_bao' => $oke
					? 'Đã đẩy lên kế toán. Giờ chỉ vào bảng công khi kế toán duyệt.'
					: 'Đã báo lại cho người gửi.' ) );
		}

		/* Cấp HAI — kế toán. Đây mới là chỗ giờ vào bảng công. */
		if ( 'xb_duyet' === $viec || 'xb_choi' === $viec ) {
			if ( ! VHCC_Vai::duoc( $toi, VHCC_XinBu::QUYEN_KT ) ) {
				return array( array( 'loi' => VHCC_Vai::loi( $toi, VHCC_XinBu::QUYEN_KT,
					'Duyệt bù giờ vào bảng công' ) ) );
			}
			$id  = isset( $_POST['xb_id'] ) ? (int) $_POST['xb_id'] : 0;
			$ly  = isset( $_POST['xb_ly_do'] ) ? sanitize_text_field( wp_unslash( $_POST['xb_ly_do'] ) ) : '';
			$oke = ( 'xb_duyet' === $viec );
			$r   = VHCC_XinBu::duyet_kt( $toi, $id, $oke, $ly );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] )
				: array( 'ok' => true, 'thong_bao' => $oke
					? 'Đã duyệt — giờ đã vào bảng công.' : 'Đã báo lại cho người gửi.' ) );
		}

		if ( ! VHCC_Vai::duoc( $toi, VHCC_TuanCong::QUYEN_DUYET ) ) {
			return array( array( 'loi' => VHCC_Vai::loi( $toi, VHCC_TuanCong::QUYEN_DUYET,
				'Duyệt đơn chỉnh bảng công' ) ) );
		}
		$id = isset( $_POST['dt_id'] ) ? (int) $_POST['dt_id'] : 0;

		if ( 'dt_choi' === $viec ) {
			$ly = isset( $_POST['dt_ly_do'] ) ? sanitize_text_field( wp_unslash( $_POST['dt_ly_do'] ) ) : '';
			$r  = VHCC_TuanCong::duyet( $toi, $id, false, $ly );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] )
				: array( 'ok' => true, 'thong_bao' => 'Đã báo lại cho cửa hàng. Họ sửa rồi gửi tệp khác.' ) );
		}

		if ( 'dt_duyet' === $viec ) {
			$r = VHCC_TuanCong::duyet( $toi, $id, true );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			/* ⚠️ NÓI RA SỐ Ô TRƯỢT, ĐỪNG CHỈ BÁO "ĐÃ DUYỆT". Vài ô có thể bị `VHCC_Bu` chối
			   (giờ của chính người duyệt, người đã đổi cơ sở…). Báo gọn "đã duyệt" là kế toán
			   tưởng xong cả, mà tháng thì đã khoá — mấy ô ấy không còn đường nào vào nữa ngoài
			   sửa tay. */
			$cau = 'Đã duyệt. ' . (int) $r['xong'] . ' ô giờ đã lên bảng công, '
				. $r['khoa'] . ' nay khoá lại.';
			/* Ô trùng sẵn KHÔNG phải ô trượt — nói tách ra, không thì kế toán đi tìm lỗi
			   ở mấy ô vốn đã đúng. */
			if ( ! empty( $r['trung'] ) ) {
				$cau .= ' ' . (int) $r['trung'] . ' ô đã trùng sẵn với bảng công nên bỏ qua.';
			}
			if ( $r['truot'] ) {
				$cau .= ' ⚠️ ' . count( $r['truot'] ) . ' ô KHÔNG ghi được — xem chi tiết ở '
					. 'danh sách đơn đã xử bên dưới, và sửa tay mấy ô ấy.';
			}
			return array( array( 'ok' => true, 'thong_bao' => $cau ) );
		}
		return array();
	}

	// ======================================================================== vẽ màn

	public static function man( $ky, $toi ) {
		echo '<div class="the"><h2>📥 Đơn duyệt chỉnh bảng công lương</h2>';
		echo '<p class="mo">Hết mỗi tháng, cửa hàng trưởng tải bảng công tháng ấy ra <b>.xlsx</b>, '
			. 'sửa trong đó rồi gửi lên. Duyệt ở đây là <b>ghi thẳng vào bảng công</b> rồi '
			. '<b>khoá tháng</b> — không quay lại được, nên đọc kỹ từng ô trước khi bấm.</p></div>';

		if ( ! VHCC_Vai::duoc( $toi, VHCC_TuanCong::QUYEN_DUYET ) ) {
			echo '<div class="the"><div class="bao" style="margin:0">'
				. esc_html( VHCC_Vai::loi( $toi, VHCC_TuanCong::QUYEN_DUYET, 'Duyệt đơn chỉnh bảng công' ) )
				. '</div></div>';
			return;
		}

		self::the_bu_cho( $ky, $toi );
		self::the_cho( $ky, $toi );
		self::the_xong( $toi );
	}

	/**
	 * ĐƠN BÙ GIỜ LẺ ĐANG CHỜ KẾ TOÁN — cấp hai của `VHCC_XinBu`.
	 *
	 * ⚠️ Đứng TRÊN khối đơn tháng: đơn bù lẻ là của một người, một ngày, và người ta đang chờ đi
	 *    làm tiếp; đơn tháng thì cả tháng đã xong rồi, chậm một hôm không ai mất gì.
	 */
	private static function the_bu_cho( $ky, $toi ) {
		$ds = VHCC_XinBu::cho_duyet( VHCC_XinBu::CHO_KT, '', 100 );
		echo '<div class="the"><h3 style="margin:0 0 8px">Đơn bù giờ lẻ — ' . count( $ds )
			. ' đơn chờ</h3>';
		echo '<p class="mo" style="margin:0 0 10px">Nhân viên xin bù, cửa hàng trưởng đã duyệt. '
			. 'Duyệt ở đây là <b>ghi thẳng vào bảng công</b> ngày ấy.</p>';
		if ( ! $ds ) {
			echo '<p class="mo" style="margin:0">Chưa có đơn nào.</p></div>';
			return;
		}
		echo '<div class="cuon"><table class="b"><thead><tr>'
			. '<th>Cơ sở</th><th>Ngày</th><th>Mã NV</th><th>Họ tên</th><th>Giờ xin</th>'
			. '<th>Lý do</th><th>CHT duyệt</th><th></th></tr></thead><tbody>';
		foreach ( $ds as $d ) {
			echo '<tr><td>' . esc_html( (string) $d['coso'] ) . '</td>'
				. '<td>' . esc_html( (string) $d['ngay'] ) . '</td>'
				. '<td>' . esc_html( (string) $d['ma_nv'] ) . '</td>'
				. '<td>' . esc_html( (string) $d['ho_ten'] ) . '</td>'
				. '<td><b>' . esc_html( (string) $d['vao'] ) . '–' . esc_html( (string) $d['ra'] ) . '</b></td>'
				. '<td class="mo">' . esc_html( (string) $d['ly_do'] ) . '</td>'
				. '<td class="mo">' . esc_html( (string) $d['cht_ten'] ) . '</td>'
				. '<td>' . self::nut_bu( $ky, (int) $d['id'] ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/** Hai nút Duyệt / Không duyệt cho một đơn bù. */
	private static function nut_bu( $ky, $id ) {
		$an = '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="man" value="don_tuan">'
			. '<input type="hidden" name="xb_id" value="' . (int) $id . '">';
		return '<form method="post" style="display:inline">' . $an
			. '<input type="hidden" name="viec" value="xb_duyet">'
			. '<button class="chinh">Duyệt</button></form> '
			. '<form method="post" style="display:inline">' . $an
			. '<input type="hidden" name="viec" value="xb_choi">'
			. '<input name="xb_ly_do" placeholder="vì sao" style="width:150px" maxlength="250" required>'
			. '<button class="phu">Chối</button></form>';
	}

	/**
	 * KHỐI CẤP MỘT TRÊN MÀN BẢNG CÔNG — cửa hàng trưởng nhận đơn của cơ sở mình.
	 *
	 * ⚠️ Vẽ ngay cả khi không có đơn nào, và nói rõ *"duyệt xong vẫn chờ kế toán"*: cửa hàng
	 *    trưởng bấm Duyệt rồi tưởng giờ đã vào bảng công, hôm sau thấy ô vẫn trống thì họ đi
	 *    bù tay — mà nay họ không bù được nữa, nên chỉ còn bực.
	 */
	public static function khoi_bu_cht( $ky, $toi, $cs ) {
		if ( ! VHCC_Vai::duoc( $toi, VHCC_XinBu::QUYEN_CHT ) ) { return; }
		$cs = VHCC_NhanSu::chuan_coso( $cs );
		if ( '' === $cs || ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return; }

		$ds = VHCC_XinBu::cho_duyet( VHCC_XinBu::CHO_CHT, $cs, 100 );
		echo '<div class="the" id="xinbu"><details' . ( $ds ? ' open' : '' ) . '>';
		echo '<summary><b>Đơn xin bù giờ</b> — '
			. ( $ds ? ( '<b style="color:var(--vang-dam)">' . count( $ds ) . ' đơn đang chờ anh/chị duyệt</b>' )
				: 'không có đơn nào chờ' ) . '</summary>';
		echo '<p class="mo" style="margin:10px 0">Nhân viên quên bấm máy thì xin bù ở trang chấm '
			. 'công online. Anh/chị duyệt là đơn <b>đẩy tiếp lên kế toán</b> — giờ chỉ vào bảng '
			. 'công khi <b>kế toán duyệt</b>, không phải ngay lúc anh/chị bấm.</p>';
		if ( ! $ds ) { echo '</details></div>'; return; }

		echo '<div class="cuon"><table class="b"><thead><tr>'
			. '<th>Ngày</th><th>Mã NV</th><th>Họ tên</th><th>Giờ xin</th><th>Lý do</th><th></th>'
			. '</tr></thead><tbody>';
		foreach ( $ds as $d ) {
			$an = '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
				. '<input type="hidden" name="viec" value="xb_cht">'
				. '<input type="hidden" name="man" value="cham">'
				. '<input type="hidden" name="ccs" value="' . esc_attr( $cs ) . '">'
				. '<input type="hidden" name="xb_id" value="' . (int) $d['id'] . '">';
			echo '<tr><td>' . esc_html( (string) $d['ngay'] ) . '</td>'
				. '<td>' . esc_html( (string) $d['ma_nv'] ) . '</td>'
				. '<td>' . esc_html( (string) $d['ho_ten'] ) . '</td>'
				. '<td><b>' . esc_html( (string) $d['vao'] ) . '–' . esc_html( (string) $d['ra'] ) . '</b></td>'
				. '<td class="mo">' . esc_html( (string) $d['ly_do'] ) . '</td>'
				. '<td><form method="post" style="display:inline">' . $an
				. '<input type="hidden" name="xb_ok" value="1">'
				. '<button class="chinh">Duyệt, gửi kế toán</button></form> '
				. '<form method="post" style="display:inline">' . $an
				. '<input name="xb_ly_do" placeholder="vì sao" style="width:150px" maxlength="250" required>'
				. '<button class="phu">Chối</button></form></td></tr>';
		}
		echo '</tbody></table></div></details></div>';
	}

	private static function the_cho( $ky, $toi ) {
		$ds = VHCC_TuanCong::ds_cho( $toi );
		echo '<div class="the"><h3 style="margin:0 0 8px">Đang chờ duyệt — ' . count( $ds ) . ' đơn</h3>';
		if ( ! $ds ) {
			echo '<p class="mo" style="margin:0">Chưa có đơn nào. Cửa hàng gửi lên thì nó hiện ở đây, '
				. 'và chuông của anh/chị cũng kêu.</p></div>';
			return;
		}
		echo '</div>';

		foreach ( $ds as $d ) { self::the_mot( $ky, $d ); }
	}

	private static function the_mot( $ky, $d ) {
		$doi = VHCC_TuanCong::doi_cua( $d );
		echo '<div class="the">';
		echo '<h3 style="margin:0 0 4px">' . esc_html( (string) $d['coso'] ) . ' · '
			. esc_html( VHCC_TuanCong::ten_ky( $d['tu_ngay'], $d['den_ngay'] ) ) . '</h3>';
		echo '<p class="mo" style="margin:0 0 10px">' . esc_html( (string) $d['ten_gui'] )
			. ( '' !== trim( (string) $d['ma_nv_gui'] ) ? ( ' (' . esc_html( (string) $d['ma_nv_gui'] ) . ')' ) : '' )
			. ' gửi lúc ' . esc_html( (string) $d['gui_luc'] ) . ' · <b>' . count( $doi )
			. '</b> ô giờ đổi trên ' . (int) $d['so_dong'] . ' dòng.</p>';

		if ( ! $doi ) {
			echo '<div class="bao">Đơn này không còn ô nào đổi — chối đi cho gọn.</div>';
		} else {
			echo '<div class="cuon"><table class="b"><thead><tr>'
				. '<th>Ngày</th><th>Mã NV</th><th>Họ tên</th><th>Giờ vào</th><th>Giờ ra</th>'
				. '<th>Việc</th><th>Lý do cửa hàng ghi</th></tr></thead><tbody>';
			foreach ( $doi as $o ) {
				$them = ! empty( $o['them'] );
				echo '<tr' . ( $them ? ' class="hong"' : '' ) . '>'
					. '<td>' . esc_html( (string) $o['ngay'] ) . '</td>'
					. '<td>' . esc_html( (string) $o['maNV'] )
					. ( '' !== (string) $o['hauTo'] ? ( '-' . esc_html( (string) $o['hauTo'] ) ) : '' ) . '</td>'
					. '<td>' . esc_html( (string) $o['hoTen'] ) . '</td>'
					. '<td>' . self::o_doi( $o['vaoCu'], $o['vao'] ) . '</td>'
					. '<td>' . self::o_doi( $o['raCu'], $o['ra'] ) . '</td>'
					. '<td class="mo">' . ( $them ? 'bù vào ô trống' : 'sửa đè' ) . '</td>'
					. '<td class="mo">' . esc_html( (string) $o['lyDo'] ) . '</td>'
					. '</tr>';
			}
			echo '</tbody></table></div>';
		}

		echo '<div class="hang" style="margin:12px 0 0;gap:10px;align-items:flex-start">';

		/* 🔴 Ô TÍCH BẮT BUỘC, KHÔNG PHẢI `confirm()`. Trang này không dùng JavaScript trong
		   HTML (`test-cham-cong.php` chốt đúng luật ấy), nhưng đó không phải lý do duy nhất:
		   một hộp `confirm` hiện ra là người ta bấm OK theo phản xạ, còn một ô tích nằm ngay
		   cạnh nút thì phải đọc dòng chữ bên cạnh mới tích được. Với một việc không quay lại
		   được thì cái sau đáng hơn hẳn. */
		echo '<form method="post">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="dt_duyet">'
			. '<input type="hidden" name="man" value="don_tuan">'
			. '<input type="hidden" name="dt_id" value="' . (int) $d['id'] . '">'
			. '<label style="display:block;margin:0 0 8px;font-size:13px">'
			. '<input type="checkbox" required> Tôi đã soát từng ô ở trên. Duyệt là ghi thẳng vào '
			. 'bảng công rồi <b>khoá tháng</b>, không quay lại được.</label>'
			. '<button class="chinh">Duyệt &amp; khoá tháng</button></form>';

		echo '<form method="post" class="hang" style="gap:6px">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="dt_choi">'
			. '<input type="hidden" name="man" value="don_tuan">'
			. '<input type="hidden" name="dt_id" value="' . (int) $d['id'] . '">'
			. '<input name="dt_ly_do" placeholder="vì sao không duyệt" style="width:280px" '
			. 'maxlength="250" required>'
			. '<button class="phu">Không duyệt</button></form>';

		echo '</div>';
		echo '<p class="mo" style="margin:8px 0 0;font-size:12px">Dòng <b>nền đỏ</b> là ngày trước đó '
			. 'chưa có lượt chấm nào — duyệt là <b>bù</b> giờ vào chỗ trống, chứ không phải sửa.</p>';
		echo '</div>';
	}

	/** 'cũ → mới', và in rõ chữ "xoá" khi ô mới để trống — dấu gạch ngang thì không ai đọc ra. */
	private static function o_doi( $cu, $moi ) {
		$c = '' === (string) $cu ? '<span class="mo">trống</span>' : '<b>' . esc_html( (string) $cu ) . '</b>';
		$m = '' === (string) $moi ? '<span class="chu-hong">xoá giờ</span>' : '<b>' . esc_html( (string) $moi ) . '</b>';
		if ( (string) $cu === (string) $moi ) { return $c . ' <span class="mo">(giữ)</span>'; }
		return $c . ' → ' . $m;
	}

	private static function the_xong( $toi ) {
		$ds = VHCC_TuanCong::ds_xong( $toi, 20 );
		echo '<div class="the"><details><summary><b>Đơn đã xử gần đây</b> — ' . count( $ds )
			. ' đơn <span class="mo">(bấm để mở)</span></summary>';
		if ( ! $ds ) {
			echo '<p class="mo" style="margin:10px 0 0">Chưa có đơn nào được xử.</p></details></div>';
			return;
		}
		echo '<div class="cuon" style="margin-top:10px"><table class="b"><thead><tr>'
			. '<th>Cơ sở</th><th>Kỳ</th><th>Người gửi</th><th>Kết quả</th><th>Người xử</th>'
			. '<th>Lúc</th><th>Ghi chú</th></tr></thead><tbody>';
		foreach ( $ds as $d ) {
			$tt  = (string) $d['trang_thai'];
			$ten = isset( VHCC_TuanCong::TEN_TT[ $tt ] ) ? VHCC_TuanCong::TEN_TT[ $tt ] : $tt;
			$kq  = json_decode( (string) $d['ket_qua'], true );
			$ghi = '';
			if ( VHCC_TuanCong::DUYET === $tt && is_array( $kq ) ) {
				$ghi = (int) $kq['xong'] . ' ô đã ghi';
				if ( ! empty( $kq['trung'] ) ) {
					$ghi .= ' · ' . (int) $kq['trung'] . ' ô trùng sẵn, bỏ qua';
				}
				if ( ! empty( $kq['truot'] ) ) {
					/* ⚠️ Ô trượt phải kể ra TÊN và LÝ DO, không chỉ đếm. Tháng đã khoá rồi; đây là
					   chỗ duy nhất còn nói được mấy ô ấy hỏng vì gì để kế toán sửa tay. */
					$ghi .= ' · <span class="chu-hong">' . count( $kq['truot'] ) . ' ô trượt</span>: ';
					$ke = array();
					foreach ( $kq['truot'] as $x ) {
						$ke[] = esc_html( (string) $x['hoTen'] . ' ' . $x['ngay'] . ' — ' . $x['error'] );
					}
					$ghi .= implode( '; ', $ke );
				}
			} elseif ( '' !== trim( (string) $d['ly_do_choi'] ) ) {
				$ghi = esc_html( (string) $d['ly_do_choi'] );
			}
			echo '<tr><td>' . esc_html( (string) $d['coso'] ) . '</td>'
				. '<td>' . esc_html( VHCC_TuanCong::ten_ky( $d['tu_ngay'], $d['den_ngay'] ) ) . '</td>'
				. '<td>' . esc_html( (string) $d['ten_gui'] ) . '</td>'
				. '<td>' . ( VHCC_TuanCong::DUYET === $tt ? '<b>' : '<span class="mo">' )
				. esc_html( $ten ) . ( VHCC_TuanCong::DUYET === $tt ? '</b>' : '</span>' ) . '</td>'
				. '<td>' . esc_html( (string) $d['ten_duyet'] ) . '</td>'
				. '<td class="mo">' . esc_html( (string) $d['duyet_luc'] ) . '</td>'
				. '<td class="mo">' . $ghi . '</td></tr>';
		}
		echo '</tbody></table></div></details></div>';
	}

	/**
	 * NHẬN TỆP CỬA HÀNG TRƯỞNG NẠP LÊN.
	 *
	 * ⚠️ ĐỌC TỆP TỪ `tmp_name`, KHÔNG BAO GIỜ TỪ `name`. Tên tệp là chữ người ta đặt — nó đi vào
	 *    câu thông báo thì phải thoát, và không bao giờ được dùng làm đường dẫn.
	 *
	 * ⚠️ HỎI `is_uploaded_file()` TRƯỚC. Không có nó thì một lượt POST khéo tay chỉ được đường
	 *    dẫn bất kỳ trên đĩa và bắt máy chủ mở nó ra — `/etc/passwd` chẳng hạn.
	 */
	private static function nhan_tep( $toi ) {
		$cs = isset( $_POST['ccs'] ) ? VHCC_NhanSu::chuan_coso( wp_unslash( $_POST['ccs'] ) ) : '';
		/* `dt_tuan` là tên cũ đời tuần — còn đọc để một tab mở sẵn từ trước bản tháng gửi lên
		   thì không mất kỳ rồi báo "Thiếu cơ sở hoặc tháng" một cách khó hiểu. */
		$ky_thang = isset( $_POST['dt_thang'] ) ? sanitize_text_field( wp_unslash( $_POST['dt_thang'] ) )
			: ( isset( $_POST['dt_tuan'] ) ? sanitize_text_field( wp_unslash( $_POST['dt_tuan'] ) ) : '' );

		if ( empty( $_FILES['dt_tep'] ) || ! is_array( $_FILES['dt_tep'] ) ) {
			return array( array( 'loi' => 'Chưa chọn tệp nào.' ) );
		}
		$f = $_FILES['dt_tep'];
		$ma_loi = isset( $f['error'] ) ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $ma_loi ) {
			return array( array( 'loi' => 'Chưa chọn tệp nào.' ) );
		}
		if ( UPLOAD_ERR_INI_SIZE === $ma_loi || UPLOAD_ERR_FORM_SIZE === $ma_loi ) {
			return array( array( 'loi' => 'Tệp nặng hơn mức máy chủ cho nạp ('
				. esc_html( (string) ini_get( 'upload_max_filesize' ) ) . '). Nhờ hosting nâng lên.' ) );
		}
		if ( UPLOAD_ERR_OK !== $ma_loi ) {
			return array( array( 'loi' => 'Nạp tệp không xong (mã lỗi ' . $ma_loi . '). Thử lại.' ) );
		}
		$tam = isset( $f['tmp_name'] ) ? (string) $f['tmp_name'] : '';
		if ( '' === $tam || ! is_uploaded_file( $tam ) ) {
			return array( array( 'loi' => 'Tệp nạp lên không hợp lệ.' ) );
		}

		$r = VHCC_TuanCong::nap( $toi, $cs, $ky_thang, $tam );
		if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }

		$cau = 'Đã gửi cho kế toán: ' . (int) $r['soDoi'] . ' ô giờ chờ duyệt (trên '
			. (int) $r['soDong'] . ' dòng). Bảng công CHƯA đổi — chỉ đổi khi kế toán duyệt.';
		/* Nói ra số ô bỏ qua. Tờ một tháng có hàng nghìn ô mà đơn chỉ vài ô — không nói thì
		   người gửi tưởng tệp bị đọc sót. */
		if ( ! empty( $r['soTrung'] ) ) {
			$cau .= ' ' . (int) $r['soTrung'] . ' ô giữ y nguyên nên bỏ qua.';
		}
		if ( ! empty( $r['soLap'] ) ) {
			$cau .= ' ⚠️ ' . (int) $r['soLap'] . ' dòng trùng khoá (dán lặp) đã bỏ qua, '
				. 'chỉ lấy dòng đầu.';
		}
		return array( array( 'ok' => true, 'thong_bao' => $cau ) );
	}

	/**
	 * KHỐI TRÊN MÀN BẢNG CÔNG — nơi cửa hàng trưởng tải tệp tháng ra và nạp tệp đã sửa lên.
	 *
	 * ⚠️ Vẽ cả khi tháng đã khoá, chỉ đổi nội dung. Giấu hẳn đi thì người ta tưởng tính năng
	 *    hỏng và đi hỏi vòng quanh; nói thẳng "tháng này khoá rồi" thì họ biết phải làm gì.
	 */
	public static function khoi_cua_hang( $ky, $toi, $cs ) {
		if ( ! VHCC_Vai::duoc( $toi, VHCC_TuanCong::QUYEN_TAI ) ) { return; }
		$cs = VHCC_NhanSu::chuan_coso( $cs );
		if ( '' === $cs || ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return; }

		$chon = isset( $_GET['dtm'] ) ? sanitize_text_field( wp_unslash( $_GET['dtm'] ) ) : '';
		$ds_t = VHCC_TuanCong::ds_thang( 6 );
		if ( ! in_array( $chon, $ds_t, true ) ) { $chon = $ds_t[0]; }

		/* `open` sẵn: đổi tháng là trang nạp lại, mà `<details>` đóng thì người ta phải mở ra
		   lần nữa mới thấy kết quả của chính cú bấm vừa rồi — và dễ tưởng nút không ăn. */
		echo '<div class="the"><details open><summary><b>Sửa bảng công tháng bằng Excel</b> '
			. '<span class="mo">(tải ra · sửa · gửi kế toán duyệt)</span></summary>';
		echo '<p class="mo" style="margin:10px 0">Tải bảng công một tháng ra <b>.xlsx</b>, sửa giờ '
			. 'trong đó rồi nạp lại. Nạp lên <b>chưa đổi gì</b> cả — nó thành một đơn chờ '
			. '<b>kế toán duyệt</b>. Kế toán duyệt là giờ lên bảng công và <b>tháng ấy khoá lại</b>.</p>';

		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 Ô CHỌN THÁNG VÀ NÚT TẢI PHẢI NẰM TRONG CÙNG MỘT `<form>`
		 * ═══════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 18/09/2026: *"Chọn được nhưng bấm xuất nó cũng chỉ lấy từ ngày 7-13"*.
		 * Bản trước để nút tải là một thẻ `<a>` dựng sẵn ở máy chủ, mang đúng kỳ đang có trong
		 * URL. Đổi ô xổ thì chỉ đổi thứ hiện trên màn — cái `<a>` vẫn trỏ về kỳ cũ. Người ta
		 * chọn tháng khác rồi bấm tải, và nhận về tệp của tháng đang hiện. Không báo lỗi, không
		 * dấu hiệu gì; chỉ thấy tệp "sai ngày".
		 *
		 * Bỏ thẻ `<a>`, cho NÚT TẢI thành nút gửi của chính cái form chứa ô xổ. Trình duyệt gửi
		 * giá trị đang chọn, nên tải với xem bao giờ cũng cùng một tháng.
		 *
		 * ⚠️ HAI NÚT GỬI, KHÁC NHAU Ở `name`. Trình duyệt chỉ gửi tên/giá trị của nút ĐƯỢC BẤM,
		 *    nên `xuat=thang` chỉ có mặt khi người ta bấm Tải. Đừng đổi thành một `<input
		 *    type="hidden" name="xuat">` cho gọn — làm thế thì nút Xem cũng tải tệp.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		echo '<form method="get" class="hang" style="gap:8px;margin:0 0 10px;align-items:flex-end">';
		foreach ( array( 'vhcc_qt' => '1', 'man' => 'cham', 'ccs' => $cs ) as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		echo '<div><label for="dtm">Tháng</label><select id="dtm" name="dtm">';
		foreach ( $ds_t as $t ) {
			echo '<option value="' . esc_attr( $t ) . '"' . selected( $t, $chon, false ) . '>'
				. esc_html( VHCC_TuanCong::ten_thang( $t ) )
				. ( VHCC_TuanCong::khoa_roi( $cs, $t ) ? ' — đã khoá' : '' ) . '</option>';
		}
		echo '</select></div>'
			. '<div><button class="chinh" name="xuat" value="thang">⬇ Tải bảng công tháng '
			. '(.xlsx)</button></div>'
			/* Nút Xem thay cho `onchange` — trang này không gài JavaScript trong HTML. */
			. '<div><button class="phu" name="xem" value="1">Xem tháng này</button></div></form>';

		$chan = VHCC_TuanCong::vi_sao_khong_tai( $toi, $cs, $chon );
		if ( '' !== $chan ) {
			echo '<div class="bao canh" style="margin:0">' . esc_html( $chan ) . '</div></details></div>';
			return;
		}

		/* Tháng đang chạy thì tải được, nhưng phải nói ra là giờ công còn thay đổi — nạp sớm
		   rồi kế toán duyệt là khoá mất phần còn lại của tháng. */
		if ( $chon === VHCC_TuanCong::dau_thang( (string) current_time( 'Y-m-d' ) ) ) {
			echo '<div class="bao canh" style="margin:0 0 10px">⚠️ <b>'
				. esc_html( VHCC_TuanCong::ten_thang( $chon ) ) . ' đang chạy</b> — mọi người còn '
				. 'chấm tiếp, nên giờ công còn đổi. Kế toán duyệt là <b>khoá cả tháng</b>, kể cả '
				. 'mấy ngày chưa tới. Sửa gấp thì gửi; còn không thì chờ hết tháng.</div>';
		}

		$cho = VHCC_TuanCong::don_cho( $cs, $chon );
		if ( $cho ) {
			echo '<div class="bao" style="margin:0 0 10px">📤 Tháng này <b>đã gửi</b> lúc '
				. esc_html( (string) $cho['gui_luc'] ) . ' — ' . (int) $cho['so_doi']
				. ' ô giờ đang chờ kế toán duyệt. Gửi tệp mới thì lượt này bị thay.</div>';
		}

		echo '<form method="post" enctype="multipart/form-data" class="hang" style="gap:8px">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="dt_nap">'
			. '<input type="hidden" name="man" value="cham">'
			. '<input type="hidden" name="ccs" value="' . esc_attr( $cs ) . '">'
			. '<input type="hidden" name="cth" value="' . esc_attr( substr( $chon, 0, 7 ) ) . '">'
			. '<input type="hidden" name="dt_thang" value="' . esc_attr( $chon ) . '">'
			. '<div><input type="file" name="dt_tep" accept=".xlsx" required></div>'
			. '<div><button class="chinh">Gửi cho kế toán</button></div></form>';

		echo '<p class="mo" style="margin:10px 0 0;font-size:12px">Tờ xếp <b>mỗi người một dòng, '
			. 'cả tháng nằm ngang</b> — giống lưới trên màn. ⚠️ <b>Đừng sửa cột KHOÁ</b> ở cuối '
			. 'và <b>đừng đổi tên hay xoá cột ngày</b>: đó là hai thứ ghép giờ về đúng người, '
			. 'đúng ngày. Chèn thêm cột ghi chú thì không sao. Mỗi ngày là <b>một ô hai hàng</b>: '
			. 'giờ vào ở hàng trên, giờ ra ở hàng dưới (bấm <b>Alt+Enter</b> để xuống hàng trong '
			. 'ô; viết <code>08:00-17:00</code> cũng được). Dòng nào có sửa giờ thì '
			. '<b>phải ghi Lý do</b>, ít nhất 5 chữ — một lý do cho cả dòng. Muốn <b>xoá giờ</b> '
			. 'thì xoá nội dung ô, đừng xoá cả dòng. Ô nào <b>để y nguyên thì bỏ qua</b>, không '
			. 'vào đơn — chỉ ô có sửa mới cần kế toán đọc.</p>';
		echo '</details></div>';
	}
}
