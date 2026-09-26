<?php
/**
 * MÀN "TIẾP NHẬN NHÂN SỰ" — vỏ của `VHCC_TiepNhan`.
 *
 * Anh Thắng 26/09/2026: *"tạo ra 1 trình. Chạy tự động từ a đến z"*. Bốn khối:
 *   1. Tiếp nhận người mới — điền thông tin, chọn mẫu chức vụ, bấm một nút.
 *   2. Đã tiếp nhận — từng bước ✔/✖, mở bộ hồ sơ, gửi lại, chạy lại bước lỗi.
 *   3. Mẫu theo chức vụ — khai một lần.
 *   4. Thông tin công ty in trên hợp đồng.
 *
 * ⚠️ Màn chỉ vẽ và chuyển việc; mọi chốt quyền nằm trong `VHCC_TiepNhan` (bậc Kế toán + xem
 *    lương hồ sơ) và trong từng hàm lõi nó gọi (đẩy app cần Admin…).
 * ⚠️ KHÔNG `<script>` — luật chung của màn quản trị.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_WebTiepNhan {

	public static function duoc_vao( $toi ) {
		return VHCC_Vai::duoc( $toi, VHCC_TiepNhan::QUYEN ) && VHCC_Vai::duoc( $toi, 'xem_luong_hs' );
	}

	private static function act() {
		return esc_url( add_query_arg( array( 'man' => 'tiep_nhan' ), VHCC_Web::url() ) );
	}

	private static function an( $ky, $viec ) {
		return '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="' . esc_attr( $viec ) . '">';
	}

	public static function man( $ky, $toi ) {
		echo '<div class="the"><h2>🧑‍💼 Tiếp nhận nhân sự</h2><p class="mo">Nhận một người mới <b>từ đầu tới cuối trong một lần bấm</b>: '
			. 'hệ thống tự cấp mã NV và PIN, tạo hồ sơ, đặt vai trò, cơ sở, quyền vào trang, đẩy sang app, đưa lên máy chấm công, '
			. 'đặt lương, BHXH, soạn hợp đồng rồi gửi bộ hồ sơ (PDF) qua email + ứng dụng. Mọi thứ theo <b>mẫu của chức vụ</b> — khai mẫu một lần ở khối dưới.</p></div>';
		if ( ! self::duoc_vao( $toi ) ) {
			echo '<div class="the"><div class="bao" style="margin:0">' . esc_html( VHCC_Vai::loi( $toi, VHCC_TiepNhan::QUYEN, 'Tiếp nhận nhân sự' ) ) . '</div></div>';
			return;
		}
		$ds_mau = VHCC_TiepNhan::ds_mau();
		self::the_nhan( $ky, $toi, $ds_mau );
		self::the_da_nhan( $ky );
		self::the_mau( $ky, $ds_mau );
		self::the_cty( $ky );
	}

	private static function the_nhan( $ky, $toi, $ds_mau ) {
		echo '<div class="the"><h3>➕ Tiếp nhận người mới</h3>';
		if ( ! $ds_mau ) {
			echo '<div class="bao canh" style="margin:0">Chưa có <b>mẫu chức vụ</b> nào — khai ít nhất một mẫu ở khối <b>Mẫu theo chức vụ</b> bên dưới trước.</div></div>';
			return;
		}
		echo '<form method="post" action="' . self::act() . '">' . self::an( $ky, 'tn_tao' );
		echo '<div class="hang" style="gap:10px;flex-wrap:wrap">';
		$o = function ( $ten, $nhan, $kieu = 'text', $cho = '', $bat = false ) {
			echo '<div><label for="tn_' . $ten . '">' . $nhan . ( $bat ? ' *' : '' ) . '</label><input id="tn_' . $ten . '" name="tn[' . $ten . ']" type="' . $kieu . '"'
				. ( '' !== $cho ? ' placeholder="' . esc_attr( $cho ) . '"' : '' ) . ( $bat ? ' required' : '' ) . ' style="width:190px"></div>';
		};
		$o( 'ho_ten', 'Họ tên', 'text', '', true );
		$o( 'cccd', 'Số CCCD', 'text', '9–12 số', true );
		$o( 'ngay_sinh', 'Ngày sinh', 'date' );
		echo '<div><label for="tn_gt">Giới tính</label><select id="tn_gt" name="tn[gioi_tinh]"><option value="">—</option><option>Nam</option><option>Nữ</option></select></div>';
		$o( 'sdt', 'Số điện thoại' );
		$o( 'email', 'Email (nhận bộ hồ sơ)', 'email' );
		$o( 'dia_chi', 'Nơi cư trú' );
		$o( 'so_tai_khoan', 'Số tài khoản' );
		$o( 'ngan_hang', 'Ngân hàng' );
		echo '</div><div class="hang" style="gap:10px;flex-wrap:wrap;margin-top:8px">';
		echo '<div><label for="tn_mau">Mẫu chức vụ *</label><select id="tn_mau" name="tn[mau]" required><option value="">— chọn —</option>';
		foreach ( $ds_mau as $k => $m ) {
			$m = array_merge( VHCC_TiepNhan::mau_trong(), (array) $m );
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $m['ten'] . ' — ' . ( 'gio' === $m['cach']
				? number_format( (float) $m['don_gia'], 0, ',', '.' ) . 'đ/giờ' : number_format( (float) $m['luong'], 0, ',', '.' ) . 'đ/tháng' ) ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="tn_cs">Cơ sở *</label>';
		VHCC_Web::o_chon_coso( 'tn[coso]', '', $toi, VHCC_Web::ds_coso_xem( $toi ), '— chọn —' );
		echo '</div>';
		echo '<div><label for="tn_nv">Ngày vào làm</label><input id="tn_nv" name="tn[ngay_vao]" type="date" value="'
			. esc_attr( (string) current_time( 'Y-m-d' ) ) . '"></div>';
		echo '<div><label for="tn_l">Lương tháng / đơn giá giờ</label><input id="tn_l" name="tn[luong_sua]" inputmode="numeric" placeholder="để trống = theo mẫu" style="width:170px"></div>';
		echo '<div><label for="tn_bh">Lương đóng BHXH</label><input id="tn_bh" name="tn[luong_bh]" inputmode="numeric" placeholder="để trống = theo mẫu" style="width:170px"></div>';
		echo '</div><p style="margin:10px 0 0"><button class="chinh">✅ Duyệt &amp; tạo — chạy tự động</button> '
			. '<span class="mo">Tạo xong: mã NV + PIN hiện ngay, bộ hồ sơ gửi qua email (nếu có) và ứng dụng.</span></p></form></div>';
	}

	private static function the_da_nhan( $ky ) {
		$ds = VHCC_TiepNhan::gan_day( 30 );
		echo '<div class="the"><h3>📋 Đã tiếp nhận</h3>';
		if ( ! $ds ) { echo '<p class="mo" style="margin:0">Chưa tiếp nhận ai qua màn này.</p></div>'; return; }
		echo '<div class="cuon"><table class="b"><thead><tr><th>Lúc</th><th>Nhân viên</th><th>Chức vụ · cơ sở</th><th>Các bước</th><th></th></tr></thead><tbody>';
		foreach ( $ds as $bg ) {
			$loi = 0; $chip = array();
			foreach ( VHCC_TiepNhan::BUOC as $b => $ten ) {
				$x = isset( $bg['buoc'][ $b ] ) ? $bg['buoc'][ $b ] : null;
				$ok = $x && ! empty( $x['ok'] );
				if ( ! $ok ) { $loi++; }
				$chip[] = '<span title="' . esc_attr( $ten . ': ' . ( $x ? $x['chu'] : 'chưa chạy' ) ) . '" style="white-space:nowrap">'
					. ( $ok ? '✔' : '✖' ) . ' ' . esc_html( $ten ) . '</span>';
			}
			$chi_tiet = array();
			foreach ( VHCC_TiepNhan::BUOC as $b => $ten ) {
				if ( isset( $bg['buoc'][ $b ] ) && empty( $bg['buoc'][ $b ]['ok'] ) ) { $chi_tiet[] = $ten . ': ' . $bg['buoc'][ $b ]['chu']; }
			}
			echo '<tr><td class="mo" style="font-size:11.5px">' . esc_html( (string) $bg['luc'] ) . '<br>' . esc_html( (string) $bg['boi'] ) . '</td>'
				. '<td><b>' . esc_html( $bg['hd']['ho_ten'] ) . '</b><br><span class="mo">' . esc_html( $bg['ma'] ) . '</span></td>'
				. '<td>' . esc_html( $bg['hd']['chuc_vu'] ) . '<br><span class="mo">' . esc_html( $bg['hd']['coso'] ) . '</span></td>'
				. '<td style="font-size:12px;line-height:1.6">' . implode( ' · ', $chip )
				. ( $chi_tiet ? '<div style="color:var(--do);font-size:11.5px;white-space:normal">' . esc_html( implode( ' | ', $chi_tiet ) ) . '</div>' : '' ) . '</td>'
				. '<td style="white-space:nowrap"><a class="nut" target="_blank" rel="noopener" href="' . esc_url( VHCC_TiepNhan::link_bo( $bg['ma'] ) ) . '">📄 Bộ hồ sơ</a>'
				. '<form method="post" action="' . self::act() . '" style="display:inline">' . self::an( $ky, 'tn_gui' )
				. '<input type="hidden" name="tn_ma" value="' . esc_attr( $bg['ma'] ) . '"><button class="nut">✉ Gửi lại</button></form>'
				. ( $loi ? '<form method="post" action="' . self::act() . '" style="display:inline">' . self::an( $ky, 'tn_chay_lai' )
					. '<input type="hidden" name="tn_ma" value="' . esc_attr( $bg['ma'] ) . '"><button class="nut">↻ Chạy lại bước lỗi</button></form>' : '' )
				. '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	private static function the_mau( $ky, $ds_mau ) {
		echo '<div class="the"><details' . ( $ds_mau ? '' : ' open' ) . '><summary><b>🧩 Mẫu theo chức vụ</b> <span class="mo">— '
			. count( $ds_mau ) . ' mẫu · khai một lần, mọi người mới của chức vụ ấy tự nhận đủ</span></summary>';
		foreach ( $ds_mau as $k => $m ) { self::form_mau( $ky, $k, array_merge( VHCC_TiepNhan::mau_trong(), (array) $m ) ); }
		self::form_mau( $ky, '', VHCC_TiepNhan::mau_trong() );
		echo '</details></div>';
	}

	private static function form_mau( $ky, $k, $m ) {
		$moi = '' === $k;
		echo '<details style="margin-top:8px;border:1px solid var(--vien);border-radius:8px;padding:6px 10px"' . ( $moi ? '' : '' ) . '><summary>'
			. ( $moi ? '<b>＋ Thêm mẫu chức vụ</b>' : '<b>' . esc_html( $m['ten'] ) . '</b> <span class="mo">· ' . esc_html( $m['tien_to'] ) . '… · ' . esc_html( $m['vai_tro'] )
				. ' · ' . ( 'gio' === $m['cach'] ? number_format( (float) $m['don_gia'], 0, ',', '.' ) . 'đ/giờ' : number_format( (float) $m['luong'], 0, ',', '.' ) . 'đ/tháng' )
				. ( $m['thu_viec'] ? ' · thử việc ' . (int) $m['thu_viec'] . ' ngày' : '' ) . ( $m['bhxh'] ? ' · BHXH' : '' ) . '</span>' ) . '</summary>';
		echo '<form method="post" action="' . self::act() . '">' . self::an( $ky, 'tn_mau' ) . '<input type="hidden" name="tn_k" value="' . esc_attr( $k ) . '">';
		$o = function ( $ten, $nhan, $gt, $rong = 160, $cho = '' ) {
			echo '<div><label>' . $nhan . '</label><input name="m[' . $ten . ']" value="' . esc_attr( (string) $gt ) . '"'
				. ( '' !== $cho ? ' placeholder="' . esc_attr( $cho ) . '"' : '' ) . ' style="width:' . (int) $rong . 'px"></div>';
		};
		echo '<div class="hang" style="gap:8px;flex-wrap:wrap">';
		$o( 'ten', 'Tên chức vụ *', $m['ten'], 180 );
		$o( 'tien_to', 'Tiền tố mã NV *', $m['tien_to'], 130, 'VD MNNV2KVC' );
		$o( 'bo_phan', 'Bộ phận', $m['bo_phan'] );
		$o( 'mang', 'Mảng', $m['mang'] );
		echo '<div><label>Vai trò</label><select name="m[vai_tro]">';
		foreach ( VHCC_Vai::ds_ten() as $v ) { echo '<option' . selected( $m['vai_tro'], $v, false ) . '>' . esc_html( $v ) . '</option>'; }
		echo '</select></div></div>';
		echo '<div class="hang" style="gap:8px;flex-wrap:wrap;margin-top:6px">';
		echo '<div><label>Cách tính lương</label><select name="m[cach]"><option value="thang"' . selected( $m['cach'], 'thang', false ) . '>Lương tháng</option>'
			. '<option value="gio"' . selected( $m['cach'], 'gio', false ) . '>Theo giờ</option></select></div>';
		$o( 'luong', 'Lương tháng (đ)', $m['luong'] ? (int) $m['luong'] : '', 130 );
		$o( 'cong_chuan', 'Công chuẩn', $m['cong_chuan'], 80, '26' );
		$o( 'don_gia', 'Đơn giá giờ (đ)', $m['don_gia'] ? (int) $m['don_gia'] : '', 120 );
		$o( 'thu_viec', 'Thử việc (ngày)', (int) $m['thu_viec'], 90 );
		$o( 'tv_pt', 'Lương thử việc %', (int) $m['tv_pt'], 90 );
		echo '<div><label>Loại HĐ</label><select name="m[loai_hd]"><option value="xac_dinh"' . selected( $m['loai_hd'], 'xac_dinh', false ) . '>Xác định thời hạn</option>'
			. '<option value="khong_xac_dinh"' . selected( $m['loai_hd'], 'khong_xac_dinh', false ) . '>Không xác định thời hạn</option></select></div>';
		$o( 'thoi_han', 'Thời hạn (tháng)', (int) $m['thoi_han'], 90 );
		echo '<label style="align-self:flex-end;font-weight:400"><input type="checkbox" name="m[bhxh]" value="1"' . checked( ! empty( $m['bhxh'] ), true, false ) . '> Đóng BHXH (10,5%)</label>';
		$o( 'luong_bh', 'Lương đóng BH (đ)', $m['luong_bh'] ? (int) $m['luong_bh'] : '', 130, '= lương tháng' );
		echo '</div>';
		echo '<div class="hang" style="gap:14px;flex-wrap:wrap;margin-top:6px"><div><label>Quyền vào trang</label>';
		foreach ( VHCC_Cong::SO as $tr => $x ) {
			$gt = isset( $m['quyen'][ $tr ] ) ? $m['quyen'][ $tr ] : '';
			echo '<div style="font-size:12.5px">' . esc_html( $x['ten'] ) . ': <select name="m[quyen][' . esc_attr( $tr ) . ']">'
				. '<option value="">theo vai</option><option value="mo"' . selected( $gt, 'mo', false ) . '>mở</option>'
				. '<option value="khoa"' . selected( $gt, 'khoa', false ) . '>khoá</option></select></div>';
		}
		echo '</div><div><label>Đẩy sang app <span class="mo">(cần Admin)</span></label>';
		foreach ( VHCC_TiepNhan::APP as $c => $lop ) {
			if ( ! class_exists( $lop ) ) { continue; }
			$ten = method_exists( $lop, 'ten_he' ) ? call_user_func( array( $lop, 'ten_he' ) ) : $c;
			echo '<label style="font-weight:400;display:block"><input type="checkbox" name="m[app][]" value="' . esc_attr( $c ) . '"'
				. checked( in_array( $c, (array) $m['app'], true ), true, false ) . '> ' . esc_html( 'ghe' === $c ? 'Ghế massage' : ( 'bao_cao' === $c ? 'Báo cáo' : $ten ) ) . '</label>';
		}
		echo '</div><div><label>Máy chấm công</label><label style="font-weight:400"><input type="checkbox" name="m[may]" value="1"'
			. checked( ! empty( $m['may'] ), true, false ) . '> Đẩy lên máy của cơ sở</label></div></div>';
		echo '<div style="margin-top:6px"><label>Mô tả công việc (in trên hợp đồng)</label><input name="m[mo_ta]" value="' . esc_attr( $m['mo_ta'] ) . '" style="width:100%"></div>';
		echo '<div style="margin-top:6px"><label>Thỏa thuận thêm (điều khoản riêng của chức vụ, in thành một Điều trong HĐLĐ)</label>'
			. '<textarea name="m[dieu_them]" rows="2" style="width:100%">' . esc_textarea( $m['dieu_them'] ) . '</textarea></div>';
		echo '<p style="margin:8px 0 4px"><button class="chinh">' . ( $moi ? 'Thêm mẫu' : 'Lưu mẫu' ) . '</button></p></form>';
		if ( ! $moi ) {
			echo '<form method="post" action="' . self::act() . '" style="margin:0 0 4px">' . self::an( $ky, 'tn_xoa_mau' )
				. '<input type="hidden" name="tn_k" value="' . esc_attr( $k ) . '"><button class="nut">Xoá mẫu</button> '
				. '<span class="mo" style="font-size:11.5px">Người đã tiếp nhận không bị ảnh hưởng — hợp đồng của họ đã chụp lại lúc tạo.</span></form>';
		}
		echo '</details>';
	}

	private static function the_cty( $ky ) {
		$c = VHCC_TiepNhan::cty();
		echo '<div class="the"><details' . ( '' === $c['dai_dien'] ? ' open' : '' ) . '><summary><b>🏢 Thông tin công ty trên hợp đồng</b>'
			. ( '' === $c['dai_dien'] ? ' <span class="mo">— CHƯA KHAI người đại diện</span>' : '' ) . '</summary>';
		echo '<form method="post" action="' . self::act() . '">' . self::an( $ky, 'tn_cty' ) . '<div class="hang" style="gap:8px;flex-wrap:wrap">';
		foreach ( array( 'ten' => 'Tên công ty', 'dia_chi' => 'Địa chỉ', 'mst' => 'Mã số thuế', 'dai_dien' => 'Người đại diện',
			'chuc_vu_dd' => 'Chức vụ người đại diện', 'sdt' => 'Điện thoại' ) as $k => $n ) {
			echo '<div><label>' . esc_html( $n ) . '</label><input name="c[' . $k . ']" value="' . esc_attr( $c[ $k ] ) . '" style="width:' . ( 'dia_chi' === $k ? 320 : 200 ) . 'px"></div>';
		}
		echo '</div><p style="margin:8px 0 0"><button class="chinh">Lưu</button></p></form></details></div>';
	}

	/** Việc POST của màn. Trả mảng báo như mọi việc khác của `VHCC_Web`. */
	public static function xu_ly( $viec, $toi ) {
		if ( 'tn_tao' === $viec ) {
			$d = isset( $_POST['tn'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['tn'] ) ) : array();
			$m = isset( $d['mau'] ) ? VHCC_TiepNhan::mau( $d['mau'] ) : null;
			if ( isset( $d['luong_sua'] ) && '' !== trim( $d['luong_sua'] ) && $m ) {
				$d[ 'gio' === $m['cach'] ? 'don_gia' : 'luong' ] = $d['luong_sua'];
			}
			$r = VHCC_TiepNhan::tao( $toi, $d );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			$loi = array();
			foreach ( $r['buoc'] as $b => $x ) { if ( empty( $x['ok'] ) ) { $loi[] = VHCC_TiepNhan::BUOC[ $b ] . ': ' . $x['chu']; } }
			$bao = array( array( 'xong' => 'Đã tiếp nhận ' . $d['ho_ten'] . ' — mã NV ' . $r['ma'] . ' · PIN ' . $r['pin']
				. '. Bộ hồ sơ (thư chào mừng + hợp đồng) mở ở bảng "Đã tiếp nhận".' ) );
			foreach ( $loi as $l ) { $bao[] = array( 'canh' => $l ); }
			return $bao;
		}
		if ( 'tn_mau' === $viec ) {
			$d = isset( $_POST['m'] ) ? (array) wp_unslash( $_POST['m'] ) : array();
			foreach ( $d as $k => $v ) {
				if ( 'dieu_them' === $k ) { $d[ $k ] = sanitize_textarea_field( (string) $v ); }
				elseif ( ! is_array( $v ) ) { $d[ $k ] = sanitize_text_field( (string) $v ); }
			}
			$r = VHCC_TiepNhan::dat_mau( $toi, isset( $_POST['tn_k'] ) ? sanitize_text_field( wp_unslash( $_POST['tn_k'] ) ) : '', $d );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => 'Đã lưu mẫu chức vụ "' . $d['ten'] . '".' ) );
		}
		if ( 'tn_xoa_mau' === $viec ) {
			$r = VHCC_TiepNhan::xoa_mau( $toi, isset( $_POST['tn_k'] ) ? sanitize_text_field( wp_unslash( $_POST['tn_k'] ) ) : '' );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => 'Đã xoá mẫu.' ) );
		}
		if ( 'tn_cty' === $viec ) {
			$r = VHCC_TiepNhan::dat_cty( $toi, isset( $_POST['c'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['c'] ) ) : array() );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => 'Đã lưu thông tin công ty.' ) );
		}
		$ma = isset( $_POST['tn_ma'] ) ? sanitize_text_field( wp_unslash( $_POST['tn_ma'] ) ) : '';
		if ( 'tn_gui' === $viec ) {
			$r = VHCC_TiepNhan::chay_buoc( $toi, $ma, 'gui' );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			$bg = VHCC_TiepNhan::ban_ghi( $ma );
			return array( array( $r['buocOk'] ? 'xong' : 'canh' => 'Gửi bộ hồ sơ ' . $ma . ': ' . $bg['buoc']['gui']['chu'] ) );
		}
		if ( 'tn_chay_lai' === $viec ) {
			$bg = VHCC_TiepNhan::ban_ghi( $ma );
			if ( ! $bg ) { return array( array( 'loi' => 'Không có bản ghi tiếp nhận của ' . $ma . '.' ) ); }
			$bao = array();
			foreach ( VHCC_TiepNhan::BUOC as $b => $ten ) {
				if ( 'ho_so' === $b || ( isset( $bg['buoc'][ $b ] ) && ! empty( $bg['buoc'][ $b ]['ok'] ) ) ) { continue; }
				$r = VHCC_TiepNhan::chay_buoc( $toi, $ma, $b );
				if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
				$x = VHCC_TiepNhan::ban_ghi( $ma )['buoc'][ $b ];
				$bao[] = array( $x['ok'] ? 'xong' : 'canh' => $ten . ': ' . $x['chu'] );
			}
			return $bao ? $bao : array( array( 'xong' => 'Không còn bước nào lỗi.' ) );
		}
		return array( array( 'loi' => 'Việc không hợp lệ.' ) );
	}
}
