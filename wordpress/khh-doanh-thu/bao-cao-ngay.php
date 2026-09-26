<?php
/**
 * Báo cáo ngày của cơ sở — nhập tay, đối chiếu với máy POS.
 *
 * NGUYÊN TẮC: cơ sở KHÔNG gõ lại số của máy tính tiền.
 *
 * Đối chiếu file "báo cáo cơ sở" với máy POS của Tàu Tân Phú (01–14/09/2026)
 * cho kết quả khớp 0 đồng suốt 14/14 ngày — vì báo cáo đó chép ra từ chính máy
 * POS. Hai con số cùng một nguồn thì so với nhau mãi mãi bằng 0, kể cả khi có
 * thất thoát. Nên ở đây máy POS tự điền phần của nó, còn cơ sở chỉ nhập những
 * thứ máy POS không thể biết:
 *
 *   - tiền mặt đếm được trong két cuối ca   -> lệch với tiền mặt POS
 *   - chuyển khoản thực thu                 -> lệch với chuyển khoản POS (26/09/2026, xem dưới)
 *   - tiền thực nộp về quỹ                  -> phần thu rồi mà chưa nộp
 *   - số bill huỷ và tiền huỷ               -> chỗ cổ điển để rút tiền mặt
 *   - tổng lượt chạy / tổng khách vào       -> khách vào mà không có vé
 *
 * 🔴 26/09/2026: anh Thắng — *"nhiều khi lệch ngược giữa chuyển khoản và tiền mặt… nhân viên cho
 *    khách bấm vé chuyển khoản nhưng khách đưa tiền mặt, hoặc ngược lại"*. Máy POS ghi hình thức
 *    thanh toán theo NÚT nhân viên bấm lúc bán, không theo tiền THẬT SỰ cầm trên tay — bấm nhầm nút
 *    thì "tiền mặt (POS)" và "chuyển khoản (POS)" đều sai, mà sai NGƯỢC CHIỀU nhau nên cộng chung
 *    một cột "lệch" là che mất, không phải bù trừ. Bên tiền mặt đã có `tiền mặt đếm trong két` để
 *    cơ sở tự sửa; `ck_thuc_thu` là ô tương ứng cho phía chuyển khoản, cùng đi qua đường khai bình
 *    thường (`khh_dt_rest_bc_luu()`), không phải một luồng riêng.
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function khh_dt_bang_bc() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_bao_cao';
}

function khh_dt_tao_bang_bc() {
	global $wpdb;
	$bang    = khh_dt_bang_bc();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ngay date NOT NULL,
			cua_hang varchar(190) NOT NULL DEFAULT '',
			tien_mat_dem double NOT NULL DEFAULT 0,
			ck_thuc_thu double NOT NULL DEFAULT 0,
			tien_nop double NOT NULL DEFAULT 0,
			so_bill_huy int(11) NOT NULL DEFAULT 0,
			tien_bill_huy double NOT NULL DEFAULT 0,
			tong_chuyen int(11) NOT NULL DEFAULT 0,
			tong_khach int(11) NOT NULL DEFAULT 0,
			ve_giay int(11) NOT NULL DEFAULT 0,
			ghi_chu text NOT NULL,
			mon_thuc longtext NULL,
			nguoi varchar(100) NOT NULL DEFAULT '',
			nguoi_id bigint(20) unsigned NOT NULL DEFAULT 0,
			chot tinyint(1) NOT NULL DEFAULT 0,
			da_nop tinyint(1) NOT NULL DEFAULT 0,
			da_nop_boi varchar(100) NOT NULL DEFAULT '',
			da_nop_luc datetime NULL,
			lich_su longtext NOT NULL,
			sua_luc datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ngay_ch (ngay,cua_hang(120)),
			KEY ngay (ngay)
		) $charset;"
	);
}

/* ------------------------------------------------------------------ *
 * Quyền: mỗi người phụ trách cơ sở nào
 * ------------------------------------------------------------------ */

/**
 * NHỮNG CƠ SỞ NGƯỜI ĐANG XEM ĐƯỢC ĐỤNG TỚI. MẢNG RỖNG = MỌI CƠ SỞ.
 *
 * 🔴 DANH SÁCH, KHÔNG PHẢI MỘT CHUỖI. Anh Thắng 15/09/2026: *"cho thêm giúp anh bạn nhập được 2
 *    cơ sở"*. Nhà mình có người phụ trách hai quán (bên nhân sự khai bằng ô cơ sở phụ từ
 *    31/08/2026), nên mọi phép hỏi "được đụng vào cơ sở nào" phải trả lời bằng danh sách. Trả về
 *    một chuỗi rồi lấy cái đầu tiên là người ấy nhập được quán này, quán kia báo "không phụ
 *    trách" — sai im lặng, và họ sẽ tưởng tại mình nhớ nhầm.
 *
 * Hai loại người: tài khoản WordPress (một cơ sở, chọn trong hồ sơ) và người vào bằng PIN chấm
 * công (mã cơ sở đẩy từ trang nhân sự sang, tra qua bảng ghép — xem `nguoi.php`).
 */
function khh_dt_co_so_ds() {
	if ( ! is_user_logged_in() && function_exists( 'khh_dt_phien_nguoi' ) && khh_dt_phien_nguoi() ) {
		return khh_dt_phien_co_so_ds();
	}
	$m = (string) get_user_meta( get_current_user_id(), 'khh_dt_co_so', true );
	return '' === $m ? array() : array( $m );
}

/**
 * Cơ sở đã gán cho MỘT TÀI KHOẢN WORDPRESS — chỉ dùng cho màn quản trị và hồ sơ người dùng.
 *
 * ⚠️ ĐỪNG dùng hàm này để hỏi "người đang ngồi trước máy được xem gì": nó không biết phiên PIN,
 *    và nó chỉ trả về được một cơ sở. Câu hỏi ấy hỏi `khh_dt_co_so_ds()`.
 */
function khh_dt_co_so_cua( $uid = 0 ) {
	$uid = $uid ? $uid : get_current_user_id();
	return (string) get_user_meta( $uid, 'khh_dt_co_so', true );
}

/** Tên cơ sở để giao diện chọn sẵn: có đúng một cơ sở thì là cơ sở ấy, nhiều hơn thì để người chọn. */
function khh_dt_co_so_mac_dinh() {
	$ds = khh_dt_co_so_ds();
	return 1 === count( $ds ) ? (string) $ds[0] : '';
}

/** Tên người đang mở màn — để in lên góc trang và ghi vào ô "người nhập" của báo cáo ngày. */
function khh_dt_ten_dang_xem() {
	if ( is_user_logged_in() ) {
		return (string) wp_get_current_user()->display_name;
	}
	$n = function_exists( 'khh_dt_phien_nguoi' ) ? khh_dt_phien_nguoi() : null;
	return $n ? (string) $n['ho_ten'] : '';
}

/**
 * Tên để GHI VÀO SỔ báo cáo — kèm Mã NV với người vào bằng PIN.
 *
 * Người vào bằng PIN không có `ID` tài khoản nên cột `nguoi_id` của họ là 0; nếu tên cũng chỉ là
 * "Nguyễn Văn A" thì ba tháng sau, khi một con số bị hỏi lại, không ai truy được đó là anh A nào
 * trong 15 cơ sở. Mã NV là thứ duy nhất chỉ đúng một người.
 */
function khh_dt_ten_ghi_so() {
	if ( is_user_logged_in() ) {
		return (string) wp_get_current_user()->display_name;
	}
	$n = function_exists( 'khh_dt_phien_nguoi' ) ? khh_dt_phien_nguoi() : null;
	return $n ? (string) $n['ho_ten'] . ' (' . (string) $n['ma_nv'] . ')' : '';
}

/** Người này có được đụng vào cơ sở này không. */
function khh_dt_duoc_cua_hang( $cua_hang ) {
	if ( ! khh_dt_duoc_ghi() ) {
		return false;
	}
	$ds = khh_dt_co_so_ds();
	if ( ! $ds || in_array( (string) $cua_hang, $ds, true ) ) {
		return true;
	}
	/* Tên trong bảng ghép / hồ sơ có thể lệch dấu cách với tên POS — so lỏng rồi mới chối. */
	$k = khh_dt_bc_long( $cua_hang );
	foreach ( $ds as $d ) {
		if ( khh_dt_bc_long( $d ) === $k ) {
			return true;
		}
	}
	return false;
}

add_action( 'show_user_profile', 'khh_dt_o_ho_so' );
add_action( 'edit_user_profile', 'khh_dt_o_ho_so' );
function khh_dt_o_ho_so( $user ) {
	if ( ! current_user_can( 'list_users' ) ) {
		return;
	}
	$chon = khh_dt_co_so_cua( $user->ID );
	?>
	<h2>Doanh thu FABi</h2>
	<table class="form-table"><tr>
		<th><label for="khh_dt_co_so">Cơ sở phụ trách</label></th>
		<td>
			<select name="khh_dt_co_so" id="khh_dt_co_so">
				<option value="">— Tất cả cơ sở —</option>
				<?php foreach ( khh_dt_ds_cua_hang() as $ch ) : ?>
					<option value="<?php echo esc_attr( $ch ); ?>" <?php selected( $chon, $ch ); ?>>
						<?php echo esc_html( $ch ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description">Chọn một cơ sở thì người này chỉ nhập và xem báo cáo của cơ sở đó.</p>
		</td>
	</tr></table>
	<?php
}

add_action( 'personal_options_update', 'khh_dt_luu_ho_so' );
add_action( 'edit_user_profile_update', 'khh_dt_luu_ho_so' );
function khh_dt_luu_ho_so( $uid ) {
	if ( ! current_user_can( 'list_users' ) ) {
		return;
	}
	$v = isset( $_POST['khh_dt_co_so'] ) ? sanitize_text_field( wp_unslash( $_POST['khh_dt_co_so'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	update_user_meta( $uid, 'khh_dt_co_so', $v );
}

/** Danh sách cơ sở lấy từ chính số liệu POS đã nạp. */
function khh_dt_ds_cua_hang() {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = $wpdb->get_col( "SELECT DISTINCT cua_hang FROM $bang ORDER BY cua_hang" );
	return $ds ? $ds : array();
}

/* ------------------------------------------------------------------ *
 * Số của máy POS cho một ngày × cơ sở
 * ------------------------------------------------------------------ */

function khh_dt_so_pos( $ngay, $cua_hang ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$r = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $cua_hang ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	if ( ! $r ) {
		return null;
	}
	$tien_mat = 0;
	$ck       = 0;
	foreach ( khh_dt_json( $r['pttt'], array() ) as $p ) {
		$ten = khh_dt_khong_dau( isset( $p['n'] ) ? $p['n'] : '' );
		if ( false !== strpos( $ten, 'tien mat' ) ) {
			$tien_mat += (float) $p['r'];
		} else {
			$ck += (float) $p['r'];
		}
	}
	/* Khách vào theo máy = Σ số vé × số khách mỗi vé (bảng bóc tách ở `ve-khach.php`). null khi cơ
	   sở này chưa có vé nào được khai — màn bày "—", không bày 0. */
	$kh = function_exists( 'khh_dt_khach_may_tu_mon' )
		? khh_dt_khach_may_tu_mon( khh_dt_json( isset( $r['mon'] ) ? $r['mon'] : '', array() ), khh_dt_ve_khach_bang( $cua_hang ) )
		: array( 'khach' => null, 'da_tach' => 0, 'chua_tach' => array() );
	/* Phần thành phần RỜI KHO THEO COMBO của ngày (anh Thắng 24/09/2026: *"chỗ theo combo là 6, vé lẻ là 2, tổng là
	   8"* — dòng Nước suối ở tab Nhập chỉ ghi 2 vì FABi chỉ ghi phần bán lẻ). Lấy đúng phép tách của sổ kho, để hai
	   màn không bao giờ nói hai số. Không có module kho -> rỗng. */
	$mon_may   = khh_dt_bc_mon_may( isset( $r['mon'] ) ? $r['mon'] : '' );
	$theo_cb   = array();
	if ( function_exists( 'khh_dt_kho_ban_may_tach' ) && function_exists( 'khh_dt_kho_khoa_long' ) ) {
		$tach = khh_dt_kho_ban_may_tach( $ngay, $ngay, $cua_hang );
		$tach = isset( $tach[ $ngay ] ) ? $tach[ $ngay ] : array();
		foreach ( $mon_may as $i => $m ) {
			$k = khh_dt_kho_khoa_long( $tach, $m['n'] );
			if ( null !== $k && (float) $tach[ $k ]['combo'] > 0 ) {
				$mon_may[ $i ]['kho_combo'] = (float) $tach[ $k ]['combo'];
				$mon_may[ $i ]['kho_tong']  = (float) $tach[ $k ]['le'] + (float) $tach[ $k ]['combo'];
			}
		}
		foreach ( $tach as $mh => $x ) {
			if ( (float) $x['combo'] > 0 ) {
				$theo_cb[] = array( 'n' => (string) $mh, 'combo' => (float) $x['combo'], 'le' => (float) $x['le'] );
			}
		}
	}
	return array(
		'doanh_thu'  => (float) $r['doanh_thu'],
		'thanh_tien' => (float) $r['thanh_tien'],
		'chiet_khau' => (float) $r['chiet_khau'],
		'so_hd'      => (int) $r['so_hd'],
		'so_mon'     => (float) $r['so_mon'],
		'so_ve'      => (float) $r['so_ve'],
		'tien_mat'   => $tien_mat,
		'ck'         => $ck,
		'khach_may'  => $kh['khach'],
		/* Phần tạm tính 1 khách/vé từ vé chưa khai — 0 là số đã chắc. */
		'khach_tam'  => isset( $kh['tam'] ) ? (int) $kh['tam'] : 0,
		've_da_tach' => (int) $kh['da_tach'],
		've_chua_tach' => array_keys( (array) $kh['chua_tach'] ),
		/* Từng vé: số vé × khách/vé — để màn bày "cách tính" (anh Thắng 24/09/2026: "set xong lại sao nó không áp dụng"). */
		'khach_chi_tiet' => isset( $kh['chi_tiet'] ) ? $kh['chi_tiet'] : array(),
		/* Hàng bán theo máy, từng món: để nhân viên soát "bán được đúng máy không". Anh Thắng
		   23/09/2026: *"hiện số lượng hàng bán và thành tiền để nhân viên kiểm kho bán được và chốt
		   bán thực tế đúng máy POS không, nếu lệch nhân viên mới nhập, đúng rồi thì để nguyên"*. */
		'mon'        => $mon_may,
		/* [ {n, combo, le} ] mọi thành phần có rời kho theo combo hôm ấy — kể cả món FABi không ghi bán lẻ (thạch, bim bim). */
		'theo_combo' => $theo_cb,
		'tien_ve'    => khh_dt_bc_tach_tien( isset( $r['mon'] ) ? $r['mon'] : '', $r['doanh_thu'], $cua_hang )['ve'],
		'tien_le'    => khh_dt_bc_tach_tien( isset( $r['mon'] ) ? $r['mon'] : '', $r['doanh_thu'], $cua_hang )['le'],
		'tien_phu'   => khh_dt_bc_tach_tien( isset( $r['mon'] ) ? $r['mon'] : '', $r['doanh_thu'], $cua_hang )['phu'],
	);
}

/**
 * Những NHÓM MÓN được tích "tính là sale vé" ở Quản trị. `false` = chưa cấu hình (lùi về đoán theo cột
 * Loại món / tên); mảng (kể cả rỗng) = theo đúng những gì đã tích.
 *
 * Anh Thắng 23/09/2026 sửa lại lời trước: *"nhầm, này là tiền sale vé"* (chỉ VÉ COMBO.), *"sale bán lẻ"*
 * (VÉ LẺ., ĐÓNG SẴN…), rồi *"thêm cấu hình tích trong cấu hình để tính loại nào sale vé, loại nào sale
 * bán lẻ"*. Máy đoán "vé là vé" đã đoán sai một lần — nên để người tích, máy chỉ cộng.
 */
/**
 * Đọc một cấu hình nhóm theo cửa hàng. Lưu dạng [ cửa hàng ('*' = chung) => [ tên nhóm ] ]; bản
 * 1.59.0 lưu phẳng [ tên nhóm ] -> coi là '*'. Anh Thắng 23/09/2026: *"Mỗi cửa hàng 1 cấu hình đi"*.
 * Trả về: mảng của quán nếu quán đã khai riêng; không thì mảng chung; không có gì thì false.
 */
function khh_dt_nhom_doc( $khoa, $cua_hang = '' ) {
	$so = get_option( $khoa, false );
	if ( ! is_array( $so ) ) {
		return false;
	}
	$phang = false;
	foreach ( $so as $v ) {
		if ( ! is_array( $v ) ) {
			$phang = true;
			break;
		}
	}
	if ( $phang ) {
		$so = array( '*' => $so );
	}
	$cs = trim( (string) $cua_hang );
	$kh = ( '' !== $cs && '*' !== $cs ) ? khh_dt_bc_khoa_cua( $so, $cs ) : '';
	if ( '' !== $kh && is_array( $so[ $kh ] ) ) {
		return khh_dt_nhom_sach( $so[ $kh ] );
	}
	if ( isset( $so['*'] ) && is_array( $so['*'] ) ) {
		return khh_dt_nhom_sach( $so['*'] );
	}
	return false;
}

function khh_dt_nhom_ghi( $khoa, $ds, $cua_hang = '' ) {
	$so = get_option( $khoa, false );
	$so = is_array( $so ) ? $so : array();
	$phang = false;
	foreach ( $so as $v ) {
		if ( ! is_array( $v ) ) {
			$phang = true;
			break;
		}
	}
	if ( $phang ) {
		$so = array( '*' => $so );
	}
	$cs        = trim( (string) $cua_hang );
	$cs        = '' === $cs ? '*' : $cs;
	/* Khoá cũ khác dấu cách của cùng quán -> dồn về khoá nguyên văn, khỏi hai bản song song. */
	$kh = '*' === $cs ? '' : khh_dt_bc_khoa_cua( $so, $cs );
	if ( '' !== $kh && $kh !== $cs ) {
		unset( $so[ $kh ] );
	}
	$sach      = khh_dt_nhom_sach( $ds );
	$so[ $cs ] = $sach;
	update_option( $khoa, $so, false );
	return $sach;
}

/** Quán này đã khai RIÊNG cấu hình nhóm chưa (khác với thừa từ bảng chung). */
function khh_dt_nhom_co_rieng( $khoa, $cua_hang ) {
	$so = get_option( $khoa, false );
	$kh = khh_dt_bc_khoa_cua( $so, $cua_hang );
	return '' !== $kh && '*' !== $kh && is_array( $so[ $kh ] );
}

function khh_dt_nhom_ve_ds( $cua_hang = '' ) {
	return khh_dt_nhom_doc( 'khh_dt_nhom_ve', $cua_hang );
}

function khh_dt_nhom_ve_dat( $ds, $cua_hang = '' ) {
	return khh_dt_nhom_ghi( 'khh_dt_nhom_ve', $ds, $cua_hang );
}

function khh_dt_nhom_sach( $ds ) {
	$sach = array();
	foreach ( (array) $ds as $g ) {
		$g = sanitize_text_field( (string) $g );
		if ( '' !== $g && ! in_array( $g, $sach, true ) ) {
			$sach[] = $g;
		}
	}
	return $sach;
}

/**
 * SALE PHỤ = số vé × TIỀN PHỤ MỖI VÉ, khai theo nhóm món. Anh Thắng 23/09/2026 chỉnh lại lần cuối:
 * *"Cái này là chiết khấu 20k cho 1 đơn vé combo 80k"* — tức trong giá vé combo 80.000đ có 20.000đ là
 * phần phụ (chiết khấu / phần quà kèm), sổ kế toán tách riêng ra. Nên cấu hình không còn là ô tích
 * mà là SỐ TIỀN mỗi vé cho từng nhóm: VÉ COMBO. -> 20.000; nhóm để trống -> không tính.
 *
 * Lưu dạng [ cửa hàng ('*' = chung) => [ tên nhóm => đ/vé ] ]. Bản 1.59.0/1.59.1 lưu DANH SÁCH nhóm
 * (ô tích) — khoá số, không phải tên nhóm — nên `khh_dt_nhom_phu_sach()` rửa ra rỗng, tức coi như chưa
 * cấu hình: nghĩa cũ (cộng cả tiền nhóm) khác hẳn nghĩa mới, giữ lại là ra số sai.
 *
 * `false` = chưa cấu hình -> sale phụ = 0 (không đoán).
 */
function khh_dt_nhom_phu_sach( $ds ) {
	$ra = array();
	foreach ( (array) $ds as $g => $tien ) {
		if ( is_int( $g ) ) {
			continue;   // danh sách cũ (ô tích) — bỏ
		}
		$g = sanitize_text_field( (string) $g );
		if ( '' === $g || is_array( $tien ) || ! is_numeric( $tien ) || (float) $tien <= 0 ) {
			continue;
		}
		$ra[ $g ] = (float) $tien;
	}
	return $ra;
}

function khh_dt_nhom_phu_ds( $cua_hang = '' ) {
	$so = get_option( 'khh_dt_nhom_phu', false );
	if ( ! is_array( $so ) ) {
		return false;
	}
	$phang = false;
	foreach ( $so as $v ) {
		if ( ! is_array( $v ) ) {
			$phang = true;
			break;
		}
	}
	if ( $phang ) {
		$so = array( '*' => $so );
	}
	$cs = trim( (string) $cua_hang );
	$kh = ( '' !== $cs && '*' !== $cs ) ? khh_dt_bc_khoa_cua( $so, $cs ) : '';
	if ( '' !== $kh && is_array( $so[ $kh ] ) ) {
		$cs = $kh;
		return khh_dt_nhom_phu_sach( $so[ $cs ] );
	}
	if ( isset( $so['*'] ) && is_array( $so['*'] ) ) {
		return khh_dt_nhom_phu_sach( $so['*'] );
	}
	return false;
}

function khh_dt_nhom_phu_dat( $ds, $cua_hang = '' ) {
	$so = get_option( 'khh_dt_nhom_phu', false );
	$so = is_array( $so ) ? $so : array();
	$phang = false;
	foreach ( $so as $v ) {
		if ( ! is_array( $v ) ) {
			$phang = true;
			break;
		}
	}
	if ( $phang ) {
		$so = array( '*' => $so );
	}
	$cs        = trim( (string) $cua_hang );
	$cs        = '' === $cs ? '*' : $cs;
	$kh        = '*' === $cs ? '' : khh_dt_bc_khoa_cua( $so, $cs );
	if ( '' !== $kh && $kh !== $cs ) {
		unset( $so[ $kh ] );   // dồn khoá cũ khác dấu cách về khoá nguyên văn
	}
	$sach      = khh_dt_nhom_phu_sach( $ds );
	$so[ $cs ] = $sach;
	update_option( 'khh_dt_nhom_phu', $so, false );
	return $sach;
}

/** Tiền phụ mỗi vé của món này (theo nhóm món); 0 nếu nhóm không khai hay chưa cấu hình. */
function khh_dt_bc_phu_moi_ve( $m, $nhom_phu = null ) {
	$nhom_phu = null === $nhom_phu ? khh_dt_nhom_phu_ds() : $nhom_phu;
	if ( ! is_array( $nhom_phu ) ) {
		return 0.0;
	}
	$g = trim( isset( $m['g'] ) ? (string) $m['g'] : '' );
	return isset( $nhom_phu[ $g ] ) ? (float) $nhom_phu[ $g ] : 0.0;
}

/**
 * Món này tính là SALE VÉ hay BÁN LẺ.
 *   · Đã tích nhóm ở Quản trị -> món thuộc nhóm đã tích là vé, còn lại là bán lẻ. Không đoán gì thêm.
 *   · Chưa cấu hình -> cột "Loại món" của FABi ('l', từ 1.59.0) bắt đầu bằng "Vé"; dòng nạp cũ không
 *     có 'l' thì đoán qua tên/nhóm món (`khh_dt_ve_la_ve`).
 */
function khh_dt_bc_la_ve( $m, $nhom_ve = null ) {
	$nhom_ve = null === $nhom_ve ? khh_dt_nhom_ve_ds() : $nhom_ve;
	if ( is_array( $nhom_ve ) ) {
		return in_array( trim( isset( $m['g'] ) ? (string) $m['g'] : '' ), $nhom_ve, true );
	}
	$l = isset( $m['l'] ) ? trim( (string) $m['l'] ) : '';
	if ( '' !== $l ) {
		return 0 === strpos( khh_dt_khong_dau( $l ), 've' );
	}
	return function_exists( 'khh_dt_ve_la_ve' )
		&& khh_dt_ve_la_ve( isset( $m['n'] ) ? (string) $m['n'] : '', isset( $m['g'] ) ? (string) $m['g'] : '' );
}

/**
 * Các nhóm món từng xuất hiện trong kho số FABi (N ngày gần nhất, mọi cơ sở) — để màn Quản trị bày ra
 * cho tích. Mỗi nhóm: tên, những loại món trong nhóm, số lượng, tiền, và đang tính là vé hay không.
 */
function khh_dt_nhom_mon_thay( $lui = 90, $cua_hang = '' ) {
	global $wpdb;
	$bang = khh_dt_bang();
	$den  = current_time( 'Y-m-d' );
	$tu   = gmdate( 'Y-m-d', strtotime( $den . ' -' . max( 1, (int) $lui ) . ' days' ) );
	$cs   = trim( (string) $cua_hang );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = '' !== $cs
		? (array) $wpdb->get_results( $wpdb->prepare( "SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s", $tu, $den, $cs ), ARRAY_A )
		: (array) $wpdb->get_results( $wpdb->prepare( "SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s", $tu, $den ), ARRAY_A );
	// phpcs:enable
	$gom = array();
	foreach ( $ds as $r ) {
		foreach ( khh_dt_json( $r['mon'], array() ) as $m ) {
			$g = trim( isset( $m['g'] ) ? (string) $m['g'] : '' );
			$k = '' === $g ? "\0khong-nhom" : $g;
			if ( ! isset( $gom[ $k ] ) ) {
				$gom[ $k ] = array( 'nhom' => $g, 'loai' => array(), 'so_luong' => 0.0, 'tien' => 0.0 );
			}
			$l = trim( isset( $m['l'] ) ? (string) $m['l'] : '' );
			if ( '' !== $l && ! in_array( $l, $gom[ $k ]['loai'], true ) ) {
				$gom[ $k ]['loai'][] = $l;
			}
			$gom[ $k ]['so_luong'] += isset( $m['q'] ) ? (float) $m['q'] : 0;
			$gom[ $k ]['tien']     += isset( $m['r'] ) ? (float) $m['r'] : 0;
		}
	}
	$nhom_ve  = khh_dt_nhom_ve_ds( $cs );
	$nhom_phu = khh_dt_nhom_phu_ds( $cs );
	$ra       = array();
	foreach ( $gom as $x ) {
		$m_gia    = array( 'g' => $x['nhom'], 'l' => $x['loai'] ? $x['loai'][0] : '', 'n' => '' );
		$x['ve']  = khh_dt_bc_la_ve( $m_gia, $nhom_ve );
		/* đ/vé đang khai cho nhóm này, null = không tính. */
		$p        = khh_dt_bc_phu_moi_ve( $m_gia, $nhom_phu );
		$x['phu'] = $p > 0 ? $p : null;
		$ra[]     = $x;
	}
	usort(
		$ra,
		function ( $a, $b ) {
			return $a['tien'] === $b['tien'] ? strcmp( $a['nhom'], $b['nhom'] ) : ( $a['tien'] < $b['tien'] ? 1 : -1 );
		}
	);
	return $ra;
}

/** REST: GET bảng nhóm để tích; POST `nhom_ve` (JSON mảng tên nhóm) để lưu. Chỉ văn phòng (quyền nạp). */
add_action( 'rest_api_init', 'khh_dt_nhom_ve_route' );
function khh_dt_nhom_ve_route() {
	register_rest_route(
		'khh-dt/v1',
		'/nhom-ve',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_nhom_ve_xem',
				'permission_callback' => 'khh_dt_duoc_nap',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_nhom_ve_dat',
				'permission_callback' => 'khh_dt_duoc_nap',
			),
		)
	);
}
/**
 * Tên cửa hàng gửi từ màn về -> tên NGUYÊN VĂN trong kho POS. 🔴 Không `sanitize_text_field` rồi đem đi tra:
 * hàm ấy gộp hai dấu cách thành một, mà tên máy POS như "Tutu Train - Aeon Tân An ( Dịch vụ  và …" có
 * hai dấu cách thật — tra không ra, khối Quản trị báo "chưa có nhóm món nào", ô chọn nhảy về quán đầu
 * danh sách trong khi tiêu đề vẫn ghi Tân An (ảnh anh Thắng 24/09/2026). '*' = bảng chung.
 */
function khh_dt_bc_long( $t ) {
	$t = preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $t );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $t ), 'UTF-8' ) : strtolower( trim( $t ) );
}

function khh_dt_bc_ten_cua( $tho ) {
	$t = (string) $tho;
	if ( '*' === trim( $t ) ) {
		return '*';
	}
	$ds = function_exists( 'khh_dt_ds_cua_hang' ) ? (array) khh_dt_ds_cua_hang() : array();
	if ( in_array( $t, $ds, true ) ) {
		return $t;
	}
	$k = khh_dt_bc_long( $t );
	foreach ( $ds as $p ) {
		if ( khh_dt_bc_long( $p ) === $k ) {
			return (string) $p;
		}
	}
	return sanitize_text_field( $t );
}

/**
 * Khoá đang có trong một bảng cấu hình theo cửa hàng, tra LỎNG (khác dấu cách / hoa thường vẫn là một quán).
 * Bản trước 1.64.3 lưu tên qua `sanitize_text_field` nên có quán nằm dưới khoá thiếu một dấu cách — đọc bằng
 * tên nguyên văn thì không thấy, trông như "lúc thì lưu, lúc thì không" (anh Thắng 24/09/2026, Estella).
 */
function khh_dt_bc_khoa_cua( $so, $cua_hang ) {
	$cs = trim( (string) $cua_hang );
	if ( '' === $cs || ! is_array( $so ) ) {
		return '';
	}
	if ( isset( $so[ $cs ] ) ) {
		return $cs;
	}
	$k = khh_dt_bc_long( $cs );
	foreach ( array_keys( $so ) as $khoa ) {
		if ( '*' !== $khoa && khh_dt_bc_long( $khoa ) === $k ) {
			return (string) $khoa;
		}
	}
	return '';
}

/** Bỏ phần khai RIÊNG của một quán — từ đó quán thừa lại bảng chung. */
function khh_dt_nhom_xoa_rieng( $khoa, $cua_hang ) {
	$so = get_option( $khoa, false );
	$kh = khh_dt_bc_khoa_cua( $so, $cua_hang );
	if ( '' === $kh || '*' === $kh ) {
		return false;
	}
	unset( $so[ $kh ] );
	update_option( $khoa, $so, false );
	return true;
}

function khh_dt_rest_nhom_ve_xem( $req = null ) {
	$ch = $req ? khh_dt_bc_ten_cua( $req->get_param( 'cua_hang' ) ) : '';
	$so = get_option( 'khh_dt_nhom_ve', false );
	return array(
		'cua_hang'    => $ch,
		'da_cau_hinh' => false !== khh_dt_nhom_ve_ds( $ch ),
		/* Quán này tự khai, hay đang thừa bảng chung. */
		'rieng'       => '' !== $ch && '*' !== $ch && khh_dt_nhom_co_rieng( 'khh_dt_nhom_ve', $ch ),
		/* Đã có bảng chung cho mọi quán chưa (anh Thắng 24/09/2026: "có là đều hết chứ"). */
		'chung'       => is_array( $so ) && ( isset( $so['*'] ) || ! array_filter( $so, 'is_array' ) ),
		'nhom_ve'     => (array) khh_dt_nhom_ve_ds( $ch ),
		'nhom_phu'    => (array) khh_dt_nhom_phu_ds( $ch ),   // [ nhóm => đ/vé ]
		'nhom'        => khh_dt_nhom_mon_thay( 90, $ch ),
	);
}
function khh_dt_rest_nhom_ve_dat( $req ) {
	$ch = khh_dt_bc_ten_cua( $req->get_param( 'cua_hang' ) );
	/* "Bỏ khai riêng, dùng bảng chung": xoá phần riêng của quán này ở cả hai khoá rồi trả về trạng thái mới. */
	if ( $req->get_param( 'xoa_rieng' ) ) {
		if ( '' === $ch || '*' === $ch ) {
			return new WP_Error( 'khh_dt_nhom_ve', 'Chưa chọn cửa hàng để bỏ khai riêng.', array( 'status' => 400 ) );
		}
		khh_dt_nhom_xoa_rieng( 'khh_dt_nhom_ve', $ch );
		khh_dt_nhom_xoa_rieng( 'khh_dt_nhom_phu', $ch );
		return khh_dt_rest_nhom_ve_xem( $req );
	}
	$tho = $req->get_param( 'nhom_ve' );
	$ds  = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	if ( ! is_array( $ds ) ) {
		return new WP_Error( 'khh_dt_nhom_ve', 'Không đọc được danh sách nhóm gửi lên.', array( 'status' => 400 ) );
	}
	if ( '' === $ch ) {
		return new WP_Error( 'khh_dt_nhom_ve', 'Chưa chọn cửa hàng — cấu hình khai riêng từng cửa hàng.', array( 'status' => 400 ) );
	}
	khh_dt_nhom_ve_dat( $ds, $ch );
	/* Sale phụ gửi cùng lượt; không gửi thì giữ nguyên cấu hình cũ. */
	$tho_p = $req->get_param( 'nhom_phu' );
	if ( null !== $tho_p ) {
		$ds_p = is_array( $tho_p ) ? $tho_p : json_decode( (string) $tho_p, true );
		if ( is_array( $ds_p ) ) {
			khh_dt_nhom_phu_dat( $ds_p, $ch );
		}
	}
	return khh_dt_rest_nhom_ve_xem( $req );
}

/**
 * Tách doanh thu máy thành SALE VÉ và BÁN LẺ. Anh Thắng 23/09/2026: *"tách giúp anh 2 ô là tiền sale
 * vé và tiền sale bán lẻ"* — sổ kế toán của anh có đúng hai cột ấy mỗi ngày.
 *
 * Bán lẻ = doanh thu máy − vé, không cộng từng món: danh sách món có trần (KHH_DT_MON_MOI_NGAY), cộng
 * từng món có thể hụt vài dòng lẻ tẻ, mà hai cột phải cộng lại đúng bằng doanh thu máy.
 */
function khh_dt_bc_tach_tien( $mon_json, $doanh_thu, $cua_hang = '' ) {
	$ve       = 0.0;
	$phu      = 0.0;
	$nhom_ve  = khh_dt_nhom_ve_ds( $cua_hang );
	$nhom_phu = khh_dt_nhom_phu_ds( $cua_hang );
	/* Tiền phụ khai theo TÊN vé (riêng quán) đè số của nhóm — kể cả khai 0 để loại một vé ra. */
	$phu_ve = function_exists( 'khh_dt_ve_phu_bang' ) ? khh_dt_ve_phu_bang( $cua_hang ) : array();
	foreach ( khh_dt_json( $mon_json, array() ) as $m ) {
		$r   = isset( $m['r'] ) ? (float) $m['r'] : 0;
		$q   = isset( $m['q'] ) ? (float) $m['q'] : 0;
		$ten = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
		if ( khh_dt_bc_la_ve( $m, $nhom_ve ) ) {
			$ve += $r;
		}
		/* Sale phụ = SỐ VÉ × tiền phụ mỗi vé (VÉ COMBO. 80k có 20k phụ) — không phải cộng tiền nhóm. */
		$k_phu = function_exists( 'khh_dt_ve_tra' ) ? khh_dt_ve_tra( $phu_ve, $ten ) : ( array_key_exists( $ten, $phu_ve ) ? array( 'gia' => $phu_ve[ $ten ] ) : null );
		$phu  += $q * ( null !== $k_phu ? (float) $k_phu['gia'] : khh_dt_bc_phu_moi_ve( $m, $nhom_phu ) );
	}
	$dt = (float) $doanh_thu;
	return array(
		've'  => $ve,
		'le'  => max( 0.0, $dt - $ve ),
		'phu' => $phu,
	);
}

/** Danh sách món máy ghi trong ngày: [ {n, g, l, q, r, ve} ], tiền nhiều xếp trước. */
function khh_dt_bc_mon_may( $mon_json ) {
	$ra = array();
	foreach ( khh_dt_json( $mon_json, array() ) as $m ) {
		$n = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
		if ( '' === $n ) {
			continue;
		}
		$ra[] = array(
			'n'  => $n,
			'g'  => isset( $m['g'] ) ? (string) $m['g'] : '',
			'l'  => isset( $m['l'] ) ? (string) $m['l'] : '',
			'q'  => isset( $m['q'] ) ? (float) $m['q'] : 0,
			'r'  => isset( $m['r'] ) ? (float) $m['r'] : 0,
			've' => khh_dt_bc_la_ve( $m ),
		);
	}
	usort(
		$ra,
		function ( $a, $b ) {
			if ( $a['r'] === $b['r'] ) {
				return strcmp( $a['n'], $b['n'] );
			}
			return $a['r'] < $b['r'] ? 1 : -1;
		}
	);
	return $ra;
}

/**
 * Rửa phần "số thực bán" cơ sở gửi lên: [ tên món => số lượng thực ].
 *
 * Chỉ giữ tên không rỗng và số ≥ 0 (0 là "máy ghi bán mà thực không bán" — có nghĩa, giữ). Chuỗi
 * không phải số, số âm, mảng lồng — bỏ. Màn chỉ gửi những dòng LỆCH máy; nhưng có gửi dòng khớp thì
 * cũng vô hại, `khh_dt_bc_mon_lech()` so lại với máy mới kết luận.
 */
function khh_dt_bc_mon_thuc_sach( $tho ) {
	$ds = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	$ra = array();
	foreach ( is_array( $ds ) ? $ds : array() as $ten => $sl ) {
		$ten = sanitize_text_field( (string) $ten );
		if ( '' === $ten || is_array( $sl ) || ! is_numeric( $sl ) || (float) $sl < 0 ) {
			continue;
		}
		$ra[ $ten ] = (float) $sl;
	}
	return $ra;
}

/**
 * Máy ghi một đằng, cơ sở chốt một nẻo: những món lệch.
 *
 * @return array n (số món lệch), ds ([ {n, may, thuc} ] tối đa 12 dòng), da_chot (cơ sở có gửi số
 *               thực hay không — có mon_thuc, kể cả rỗng, là đã soát).
 */
function khh_dt_bc_mon_lech( $mon_json, $mon_thuc_json ) {
	$thuc = khh_dt_bc_mon_thuc_sach( $mon_thuc_json );
	$may  = array();
	foreach ( khh_dt_bc_mon_may( $mon_json ) as $m ) {
		$may[ $m['n'] ] = $m['q'];
	}
	$ds = array();
	foreach ( $thuc as $ten => $sl ) {
		$q = isset( $may[ $ten ] ) ? (float) $may[ $ten ] : 0.0;
		if ( abs( $q - $sl ) > 0.0001 ) {
			$ds[] = array( 'n' => $ten, 'may' => $q, 'thuc' => $sl );
		}
	}
	return array(
		'n'       => count( $ds ),
		'ds'      => array_slice( $ds, 0, 12 ),
		'da_chot' => null !== $mon_thuc_json && '' !== (string) $mon_thuc_json,
	);
}

/* ------------------------------------------------------------------ *
 * REST
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', 'khh_dt_rest_bc' );
function khh_dt_rest_bc() {
	register_rest_route(
		'khh-dt/v1',
		'/bao-cao-ngay',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_bc_lay',
			'permission_callback' => 'khh_dt_duoc_xem',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/bao-cao-ngay',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_bc_luu',
			'permission_callback' => 'khh_dt_duoc_ghi',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/doi-soat',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_doi_soat',
			'permission_callback' => 'khh_dt_duoc_xem',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/da-nop',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_bc_da_nop',
			'permission_callback' => 'khh_dt_duoc_ghi',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/go-khoa-da-nop',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_bc_go_khoa_da_nop',
			/* 🔴 Ai KHOÁ được thì cũng GỠ được là khoá vô nghĩa (tự khoá tự mở, không phải nhờ "ai
			   có quyền cao hơn" như anh Thắng chốt). Gỡ khoá đi qua đúng tầng "văn phòng" đã có sẵn
			   (`khh_dt_duoc_nap()`: edit_posts, hoặc quản trị, hoặc vai "duyệt") — cao hơn hẳn "nhap"
			   là vai duy nhất được phép NHẤN nút Đã nộp tiền lúc đầu. */
			'permission_callback' => 'khh_dt_duoc_nap',
		)
	);
}

function khh_dt_rest_bc_lay( $req ) {
	global $wpdb;
	$ngay = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'ngay' ) );
	$ch   = (string) $req->get_param( 'cua_hang' );
	if ( ! $ngay || ! $ch ) {
		return new WP_Error( 'khh_dt_bc', 'Thiếu ngày hoặc cơ sở.', array( 'status' => 400 ) );
	}
	/* 🔴 CHẶN CẢ ĐƯỜNG ĐỌC, KHÔNG CHỈ ĐƯỜNG GHI. Đường ghi đã hỏi `khh_dt_duoc_cua_hang()`, nhưng
	   đường đọc thì nhận nguyên tên cơ sở gửi lên — đổi một chữ trên thanh địa chỉ là cửa hàng
	   trưởng quán này đọc được doanh thu và tiền mặt đếm được của quán kia. */
	$cua_ds = khh_dt_co_so_ds();
	if ( $cua_ds && ! in_array( $ch, $cua_ds, true ) ) {
		return new WP_Error( 'khh_dt_bc', 'Anh/chị không phụ trách cơ sở này.', array( 'status' => 403 ) );
	}

	$bang = khh_dt_bang_bc();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$r = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $ch ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	if ( $r ) {
		unset( $r['lich_su'] );
		$r['mon_thuc'] = khh_dt_bc_mon_thuc_sach( isset( $r['mon_thuc'] ) ? $r['mon_thuc'] : '' );
	}
	return array(
		'pos'      => khh_dt_so_pos( $ngay, $ch ),
		'bao_cao'  => $r,
		'duoc_ghi' => khh_dt_duoc_cua_hang( $ch ),
		'cua_toi'  => khh_dt_co_so_mac_dinh(),
		'cua_toi_ds' => khh_dt_co_so_ds(),
		/* Bốn bước của ngày này (số máy về / cơ sở khai / sổ kho / chốt) + hạn — màn vẽ thanh tiến độ. */
		'quy_trinh' => function_exists( 'khh_dt_qt_tinh_trang' ) ? khh_dt_qt_tinh_trang( $ngay, $ch ) : null,
		/* Sổ kho của ngày — bày CHỈ XEM ở tab Nhập để nhân viên gửi báo cáo hàng cùng báo cáo ngày (anh Thắng
		   25/09/2026: "qua bên này chỉ hiện không sửa, sửa bên kho hàng"). Cùng số với tab Kho, không tính lại. */
		'kho'      => function_exists( 'khh_dt_kho_bang_ngay' ) ? khh_dt_kho_bang_ngay( $ngay, $ch ) : array(),
	);
}

function khh_dt_rest_bc_luu( $req ) {
	global $wpdb;
	$ngay = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'ngay' ) );
	/* 🔴 Tên quán về tên NGUYÊN VĂN trong kho POS, không `sanitize_text_field`: hàm ấy gộp hai dấu cách, cửa
	   hàng trưởng Estella bấm Lưu là "Anh/chị không phụ trách cơ sở này" trong khi đường đọc vẫn mở được
	   (anh Thắng 24/09/2026). */
	$ch   = khh_dt_bc_ten_cua( $req->get_param( 'cua_hang' ) );
	if ( ! $ngay || ! $ch || '*' === $ch ) {
		return new WP_Error( 'khh_dt_bc', 'Thiếu ngày hoặc cơ sở.', array( 'status' => 400 ) );
	}
	if ( ! khh_dt_duoc_cua_hang( $ch ) ) {
		return new WP_Error( 'khh_dt_bc', 'Anh/chị không phụ trách cơ sở này.', array( 'status' => 403 ) );
	}

	$bang = khh_dt_bang_bc();
	$so   = function ( $k ) use ( $req ) {
		return khh_dt_so( (string) $req->get_param( $k ) );
	};
	$moi = array(
		'ngay'          => $ngay,
		'cua_hang'      => $ch,
		'tien_mat_dem'  => $so( 'tien_mat_dem' ),
		'ck_thuc_thu'   => $so( 'ck_thuc_thu' ),
		'tien_nop'      => $so( 'tien_nop' ),
		'so_bill_huy'   => (int) $so( 'so_bill_huy' ),
		'tien_bill_huy' => $so( 'tien_bill_huy' ),
		'tong_chuyen'   => (int) $so( 'tong_chuyen' ),
		'tong_khach'    => (int) $so( 'tong_khach' ),
		've_giay'       => (int) $so( 've_giay' ),
		'ghi_chu'       => sanitize_textarea_field( (string) $req->get_param( 'ghi_chu' ) ),
		/* Số thực bán từng món, chỉ những dòng lệch máy (màn gửi). Không gửi -> '{}' = đã soát, khớp hết. */
		'mon_thuc'      => wp_json_encode( (object) khh_dt_bc_mon_thuc_sach( $req->get_param( 'mon_thuc' ) ) ),
		'chot'          => $req->get_param( 'chot' ) ? 1 : 0,
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$cu = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $ch ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	/* 🔴 26/09/2026 anh Thắng — nút "Đã nộp tiền": *"khi đã nộp thì khóa ô nhập lại"*. Màn hình đã
	   khoá (`e.disabled`) khi `da_nop`, nhưng khoá màn không chặn được ai gọi thẳng REST — chặn LẠI
	   Ở ĐÂY, không chỉ ở giao diện, mới thật sự "vĩnh viễn, muốn sửa phải nhờ ai có quyền cao hơn"
	   (câu trả lời của anh Thắng khi được hỏi). Gỡ khoá đi đường riêng `khh_dt_rest_bc_go_khoa_da_nop()`. */


	/* 🔴 26/09/2026 anh Thắng — nút "Đã nộp tiền": *"khi đã nộp thì khóa ô nhập lại"*. Màn hình đã
	   khoá (`e.disabled`) khi `da_nop`, nhưng khoá màn không chặn được ai gọi thẳng REST — chặn LẠI
	   Ở ĐÂY, không chỉ ở giao diện, mới thật sự "vĩnh viễn, muốn sửa phải nhờ ai có quyền cao hơn"
	   (câu trả lời của anh Thắng khi được hỏi). Gỡ khoá đi đường riêng `khh_dt_rest_bc_go_khoa_da_nop()`. */
	if ( $cu && (int) $cu['da_nop'] ) {
		return new WP_Error(
			'khh_dt_da_nop',
			'Ngày này đã đánh dấu "Đã nộp tiền" (bởi ' . (string) $cu['da_nop_boi'] . ') — khoá sửa. ' .
			'Nhờ văn phòng hoặc quản trị gỡ khoá trước khi sửa lại.',
			array( 'status' => 403 )
		);
	}

	$ky = khh_dt_ten_ghi_so();
	// Sửa sau khi đã chốt thì giữ lại bản cũ, đừng để mất dấu.
	$lich_su = array();
	if ( $cu ) {
		$lich_su = khh_dt_json( $cu['lich_su'], array() );
		$khac    = false;
		foreach ( array( 'tien_mat_dem', 'ck_thuc_thu', 'tien_nop', 'so_bill_huy', 'tien_bill_huy', 'tong_chuyen', 'tong_khach', 've_giay', 'ghi_chu', 'mon_thuc' ) as $k ) {
			if ( (string) ( isset( $cu[ $k ] ) ? $cu[ $k ] : '' ) !== (string) $moi[ $k ] ) {
				$khac = true;
			}
		}
		if ( $khac ) {
			$cu_gon = $cu;
			unset( $cu_gon['lich_su'], $cu_gon['id'] );
			$cu_gon['sua_boi'] = $ky;
			$cu_gon['sua_luc'] = current_time( 'mysql' );
			$lich_su[]         = $cu_gon;
			if ( count( $lich_su ) > 20 ) {
				$lich_su = array_slice( $lich_su, -20 );
			}
		}
	}

	$moi['nguoi']    = $ky;
	$moi['nguoi_id'] = get_current_user_id();
	$moi['sua_luc']  = current_time( 'mysql' );
	$moi['lich_su']  = wp_json_encode( $lich_su );

	if ( $cu ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $bang, $moi, array( 'id' => $cu['id'] ) );
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $bang, $moi );
	}

	return array(
		'ok'      => true,
		'pos'     => khh_dt_so_pos( $ngay, $ch ),
		'bao_cao' => $moi,
		'sua_lan' => count( $lich_su ),
	);
}

/**
 * ĐÃ NỘP TIỀN — khoá báo cáo ngày này lại (anh Thắng 26/09/2026: *"Bổ sung nút đã nộp tiền (Khi
 * đã nộp thì khóa ô nhập lại)"*). Trả lời câu hỏi làm rõ sau đó: khoá TOÀN BỘ form, vĩnh viễn,
 * không tự mở lại được — chỉ `khh_dt_rest_bc_go_khoa_da_nop()` (tầng văn phòng/quản trị) mới gỡ.
 *
 * Phải LƯU báo cáo trước (có dòng trong bảng) mới đánh dấu được — đánh dấu một ngày chưa nhập gì
 * là khoá một ô trống, không ai còn gõ số vào được nữa mà con số thật chưa hề tồn tại.
 */
function khh_dt_rest_bc_da_nop( $req ) {
	global $wpdb;
	$ngay = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'ngay' ) );
	$ch   = khh_dt_bc_ten_cua( $req->get_param( 'cua_hang' ) );
	if ( ! $ngay || ! $ch || '*' === $ch ) {
		return new WP_Error( 'khh_dt_bc', 'Thiếu ngày hoặc cơ sở.', array( 'status' => 400 ) );
	}
	if ( ! khh_dt_duoc_cua_hang( $ch ) ) {
		return new WP_Error( 'khh_dt_bc', 'Anh/chị không phụ trách cơ sở này.', array( 'status' => 403 ) );
	}

	$bang = khh_dt_bang_bc();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$cu = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, da_nop FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $ch ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	if ( ! $cu ) {
		return new WP_Error( 'khh_dt_bc', 'Chưa có báo cáo ngày này — lưu báo cáo trước khi đánh dấu đã nộp tiền.', array( 'status' => 400 ) );
	}
	if ( (int) $cu['da_nop'] ) {
		return new WP_Error( 'khh_dt_bc', 'Ngày này đã đánh dấu đã nộp tiền từ trước.', array( 'status' => 409 ) );
	}

	$boi = khh_dt_ten_ghi_so();
	$luc = current_time( 'mysql' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $bang, array( 'da_nop' => 1, 'da_nop_boi' => $boi, 'da_nop_luc' => $luc ), array( 'id' => $cu['id'] ) );

	return array( 'ok' => true, 'da_nop' => 1, 'da_nop_boi' => $boi, 'da_nop_luc' => $luc );
}

/** Gỡ khoá "Đã nộp tiền" — CHỈ tầng văn phòng/quản trị (permission_callback khh_dt_duoc_nap ở
 *  khh_dt_rest_bc()), không phải người vừa khoá tự mở lại được. Ghi vào lich_su để còn tra ai gỡ,
 *  lúc nào — cùng cơ chế nhật ký đã dùng cho mọi lần sửa báo cáo. */
function khh_dt_rest_bc_go_khoa_da_nop( $req ) {
	global $wpdb;
	$ngay = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'ngay' ) );
	$ch   = khh_dt_bc_ten_cua( $req->get_param( 'cua_hang' ) );
	if ( ! $ngay || ! $ch || '*' === $ch ) {
		return new WP_Error( 'khh_dt_bc', 'Thiếu ngày hoặc cơ sở.', array( 'status' => 400 ) );
	}

	$bang = khh_dt_bang_bc();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$cu = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, lich_su, da_nop_boi, da_nop_luc FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $ch ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	if ( ! $cu ) {
		return new WP_Error( 'khh_dt_bc', 'Không thấy báo cáo ngày này.', array( 'status' => 400 ) );
	}

	$lich_su   = khh_dt_json( $cu['lich_su'], array() );
	$lich_su[] = array(
		'go_khoa_da_nop' => true,
		'da_nop_boi_cu'  => (string) $cu['da_nop_boi'],
		'da_nop_luc_cu'  => (string) $cu['da_nop_luc'],
		'go_boi'         => khh_dt_ten_ghi_so(),
		'go_luc'         => current_time( 'mysql' ),
	);
	if ( count( $lich_su ) > 20 ) {
		$lich_su = array_slice( $lich_su, -20 );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update(
		$bang,
		array(
			'da_nop'     => 0,
			'da_nop_boi' => '',
			'da_nop_luc' => null,
			'lich_su'    => wp_json_encode( $lich_su ),
		),
		array( 'id' => $cu['id'] )
	);

	return array( 'ok' => true, 'da_nop' => 0 );
}

/** Bảng đối soát: mỗi dòng một ngày × cơ sở, kèm mức lệch. */
function khh_dt_rest_doi_soat( $req ) {
	global $wpdb;
	$tu  = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'tu' ) );
	$den = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'den' ) );
	$ch  = (string) $req->get_param( 'cua_hang' );
	/* Người phụ trách cơ sở chỉ thấy cơ sở mình — một hay hai đều thế. Chọn một cơ sở ngoài phần
	   của mình thì coi như không chọn, chứ không chối: giao diện có thể còn nhớ lựa chọn cũ. */
	$cua_ds = khh_dt_co_so_ds();
	if ( $cua_ds && ! in_array( $ch, $cua_ds, true ) ) {
		$ch = 1 === count( $cua_ds ) ? (string) $cua_ds[0] : '';
	}

	$pos = khh_dt_bang();
	$bc  = khh_dt_bang_bc();
	$sql = "SELECT p.ngay, p.cua_hang, p.doanh_thu, p.so_hd, p.so_ve, p.pttt, p.mon,
				b.tien_mat_dem, b.ck_thuc_thu, b.tien_nop, b.so_bill_huy, b.tien_bill_huy,
				b.tong_chuyen, b.tong_khach, b.ve_giay, b.ghi_chu, b.mon_thuc, b.nguoi, b.chot
			FROM $pos p LEFT JOIN $bc b ON b.ngay = p.ngay AND b.cua_hang = p.cua_hang
			WHERE 1=1";
	$args = array();
	if ( $tu ) {
		$sql   .= ' AND p.ngay >= %s';
		$args[] = $tu;
	}
	if ( $den ) {
		$sql   .= ' AND p.ngay <= %s';
		$args[] = $den;
	}
	if ( $ch && '*' !== $ch ) {
		$sql   .= ' AND p.cua_hang = %s';
		$args[] = $ch;
	} elseif ( $cua_ds ) {
		$sql   .= ' AND p.cua_hang IN (' . implode( ',', array_fill( 0, count( $cua_ds ), '%s' ) ) . ')';
		$args   = array_merge( $args, $cua_ds );
	}
	$sql .= ' ORDER BY p.ngay DESC, p.cua_hang ASC LIMIT 2000';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	// phpcs:enable

	/* 🔴 TIỀN THỰC NỘP LẤY Ở SAO KÊ NGÂN HÀNG, KHÔNG PHẢI Ô CƠ SỞ GÕ VÀO.
	   Anh Thắng 16/09/2026: *"cột thực nộp mình sẽ lấy bên sao kê"*. Ai giữ tiền mà cũng tự khai
	   mình nộp bao nhiêu thì con số ấy không kiểm được gì — y như báo cáo cơ sở chép từ máy POS
	   nên khớp 0 đồng suốt 14/14 ngày. Số cơ sở khai vẫn giữ, nhưng để ĐỐI CHIẾU với ngân hàng:
	   khai 10 triệu mà ngân hàng nhận 8 triệu — chính chỗ lệch ấy mới là tín hiệu. */
	$bank = khh_dt_nop_bank( $tu, $den );

	/* ==============================================================================================
	 * 🔴 NỘP TIỀN LÀ MỘT CỤC, KHÔNG PHẢI MỖI NGÀY MỘT LẦN — NÊN PHẢI CỘNG DỒN.
	 * ==============================================================================================
	 * Anh Thắng 16/09/2026 gửi sao kê lọc theo mã của TÀU GÒ VẤP: cả hai tháng đúng BỐN lần nộp —
	 * 10/08, 19/08, 05/09, 11/09 — mỗi lần 17 đến 40 triệu. Tức một lần chuyển gánh tiền mặt của
	 * cả chục ngày trước đó.
	 *
	 * Bản trước so từng ngày với từng ngày: ngày nào không có giao dịch ngân hàng là "chưa nộp".
	 * Với lối nộp này thì 26 ngày trong tháng đều đỏ, kể cả khi người ta đã nộp đủ đến từng đồng.
	 * Một bảng mà ngày nào cũng đỏ thì không ai nhìn nữa — và hôm có thất thoát thật cũng chìm
	 * trong đám đỏ ấy.
	 *
	 * Nên đổi sang SỐ DƯ TREO: cộng dồn tiền mặt phải nộp, trừ đi tiền đã về tài khoản. Một cú
	 * chuyển lớn xoá sạch phần treo của mấy ngày trước nó. Cái đáng soi không còn là "hôm nay có
	 * nộp không" mà là "đang treo bao nhiêu, và treo bao lâu rồi".
	 * ==============================================================================================
	 */
	$mo_dau = khh_dt_treo_truoc( $tu );
	$momo   = khh_dt_momo_theo_ngay( $tu, $den );

	/* 🔴 SAO KÊ CÓ THỂ ĐI TRƯỚC KHO POS — và khi ấy "đã nộp" trông nhiều hơn "tiền mặt".
	   16/09/2026, Lotte Gò Vấp kỳ 01–15/09: tiền mặt 43,3 tr mà đã nộp 66,76 tr, cột không khớp
	   ra −37 tr. Không phải ai nộp thừa: sổ ngân hàng có cả khoản gánh tiền mặt của THÁNG TRƯỚC,
	   trong khi kho POS chưa có tháng ấy nên không có gì để trừ. So hai sổ lệch kỳ nhau thì con
	   số nào cũng vô nghĩa — phải nói ra, chứ không được để người đọc tự đoán. */
	$pos_som = khh_dt_ngay_som_nhat();
	/* 🔴 CƠ SỞ CHƯA KHAI MÃ NỘP TIỀN THÌ KHÔNG ĐƯỢC KẾT TỘI HỌ.
	   Anh Thắng 16/09/2026 kéo sao kê về xong, cả bảng đỏ rực "chưa nộp 1,1 tr · 5,2 tr · 7,8 tr"
	   cho mọi ngày của Lotte Gò Vấp — trong khi sự thật là chưa ai khai mã nộp tiền của quán ấy,
	   nên không khoản nào ghép được vào. Hệ không biết mà nói như thể đã biết, và người bị nêu
	   tên là người có thể đã nộp đủ. Không biết thì phải nói là KHÔNG BIẾT. */
	$co_ma = array();
	foreach ( khh_dt_ghep_bank_ds() as $g ) {
		$co_ma[ (string) $g['cua_hang'] ] = true;
	}

	$ra = array();
	foreach ( (array) $rows as $r ) {
		$tm = 0;
		$ck = 0;
		/* 🔴 TÁCH RIÊNG PHẦN MOMO, ĐỪNG GỘP VÀO "CK/QR".
		   Anh Thắng 16/09/2026: *"giờ đến đối soát MoMo"*. Muốn so được với sao kê MoMo thì phải
		   biết máy POS ghi bao nhiêu RIÊNG cho MoMo — cục "CK/QR" gộp cả chuyển khoản, VNPAY,
		   Việt QR và MoMo, đem cục ấy so với sổ MoMo thì lệch bao nhiêu cũng không nói lên gì.
		   Máy POS đã ghi sẵn tên hình thức thanh toán ở cột PTTT, chỉ việc đọc đúng tên. */
		$momo_pos = 0;
		foreach ( khh_dt_json( $r['pttt'], array() ) as $p ) {
			$ten_pttt = khh_dt_khong_dau( isset( $p['n'] ) ? $p['n'] : '' );
			if ( false !== strpos( $ten_pttt, 'tien mat' ) ) {
				$tm += (float) $p['r'];
				continue;
			}
			$ck += (float) $p['r'];
			if ( false !== strpos( $ten_pttt, 'momo' ) ) {
				$momo_pos += (float) $p['r'];
			}
		}
		$co   = null !== $r['tien_mat_dem'];
		$dem  = (float) $r['tien_mat_dem'];
		$ck_tt = (float) $r['ck_thuc_thu'];               // cơ sở KHAI chuyển khoản thực thu
		$nop  = (float) $r['tien_nop'];                   // cơ sở KHAI đã nộp
		$kb  = $r['ngay'] . '|' . $r['cua_hang'];
		$co_bank = array_key_exists( $kb, $bank );
		$b       = $co_bank ? $bank[ $kb ] : array();
		$nop_bk  = $co_bank ? (float) $b['tien'] : 0;    // ngân hàng NHẬN ĐƯỢC
		$nop_that = $co_bank ? $nop_bk : $nop;

		/* 🔴 SỐ PHẢI NỘP KHÔNG CHỜ CƠ SỞ NHẬP BÁO CÁO.
		   Anh Thắng 16/09/2026: *"đối chiếu giao dịch để đẩy vào tab đối soát xem nhân viên nộp
		   tiền chưa"*. Máy POS đã biết hôm ấy thu bao nhiêu tiền mặt, ngân hàng đã biết nhận được
		   bao nhiêu — hai đầu ấy đủ để trả lời, KHÔNG cần ai gõ gì. Bắt câu trả lời phải chờ cửa
		   hàng trưởng nhập báo cáo là bỏ trống đúng chỗ cần nhìn nhất: 25/25 ngày trên màn của anh
		   đang "chưa nhập báo cáo", và nếu cột nộp tiền nấp sau đó thì cả tháng không ai biết
		   tiền đã về hay chưa.
		   Cơ sở có đếm két thì lấy số đếm được (nó có thể nhiều hơn tiền mặt POS); chưa đếm thì
		   lấy tiền mặt POS. */
		/* 🔴 26/09/2026: anh Thắng — ca Aeon Bình Tân 25/09: đếm két để trống (0) nhưng đã khai
		   Tiền thực nộp về quỹ = 4.540.000 kèm ghi chú lý do lệch. "Phải nộp"/"Lệch" vẫn lấy 0
		   làm đếm két, ra Lệch −4.780.000 (bằng cả tiền mặt POS) — kế toán tưởng mất cả cục tiền,
		   trong khi cơ sở ĐÃ xác nhận qua ô Nộp quỹ, chỉ chưa gõ vào đúng ô Đếm két.
		   ⚠️ CHỈ THIẾU "ĐẾM KÉT", CÒN "NỘP QUỸ" LÀ MỘT LỜI XÁC NHẬN THẬT — cơ sở tự gõ số > 0 vào
		   đó không phải ngẫu nhiên. Đếm két = 0 mà Nộp quỹ > 0 thì lấy Nộp quỹ làm số đã xác nhận,
		   thay vì mặc định 0 rồi bắt kế toán tự suy hay tự sửa tay ô Đếm két.
		   `dem` (ô hiện ở cột "Đếm két") GIỮ NGUYÊN — đúng số cơ sở đã gõ, không bịa; chỉ riêng
		   `phai_nop`/`lech_tm`/`chua_nop` (những chỗ QUYẾT ĐỊNH treo bao nhiêu) mới dùng số đã bù,
		   kèm cờ `dem_tu_nop` để màn biết mà chú thích, tránh Lệch không khớp phép trừ hiện ra. */
		$dem_xn = ( $co && 0.0 === $dem && $nop > 0 ) ? $nop : $dem;
		$tu_nop = ( $co && 0.0 === $dem && $nop > 0 );
		$phai_nop = $co ? $dem_xn : $tm;
		$thieu    = $phai_nop - $nop_bk;
		$ra[] = array(
			'ngay'       => $r['ngay'],
			'cua_hang'   => $r['cua_hang'],
			'doanh_thu'  => (float) $r['doanh_thu'],
			/* Hai cột sổ kế toán: sale vé / bán lẻ (anh Thắng 23/09/2026). */
			'tien_ve'    => khh_dt_bc_tach_tien( isset( $r['mon'] ) ? $r['mon'] : '', $r['doanh_thu'], (string) $r['cua_hang'] )['ve'],
			'tien_le'    => khh_dt_bc_tach_tien( isset( $r['mon'] ) ? $r['mon'] : '', $r['doanh_thu'], (string) $r['cua_hang'] )['le'],
			'tien_phu'   => khh_dt_bc_tach_tien( isset( $r['mon'] ) ? $r['mon'] : '', $r['doanh_thu'], (string) $r['cua_hang'] )['phu'],
			'so_hd'      => (int) $r['so_hd'],
			'so_ve'      => (float) $r['so_ve'],
			'pos_tm'     => $tm,
			'pos_ck'     => $ck,
			'pos_momo'   => $momo_pos,
			'co_bao_cao' => $co,
			'dem'        => $dem,
			'dem_tu_nop' => $tu_nop,   // Lệch/Phải nộp đang lấy theo Nộp quỹ, vì Đếm két để trống
			'ck_tt'      => $ck_tt,
			'nop'        => $nop,
			'nop_bank'   => $nop_bk,
			'co_bank'    => $co_bank,
			/* Trả lời thẳng câu "nộp chưa": phải nộp bao nhiêu, về bao nhiêu, còn thiếu bao
			   nhiêu, nộp mấy lần, nộp ngày nào giờ nào, và đã đến hạn chưa. */
			'phai_nop'   => $phai_nop,
			'thieu'      => $thieu,
			'nop_lan'    => $co_bank ? (int) $b['so_lan'] : 0,
			'nop_ngay'   => $co_bank ? (string) $b['ngay_nop'] : '',
			'nop_gio'    => $co_bank ? (int) $b['gio_dau'] : 0,
			'nop_muon'   => ( $co_bank && $b['ngay_nop'] > $r['ngay'] )
				? (int) round( ( strtotime( $b['ngay_nop'] ) - strtotime( $r['ngay'] ) ) / 86400 ) : 0,
			'qua_han'    => khh_dt_qua_han_nop( $r['ngay'] ),
			'co_ma'      => isset( $co_ma[ (string) $r['cua_hang'] ] ),
			/* Cơ sở khai một đằng, ngân hàng nhận một nẻo — chỉ tính khi CÓ CẢ HAI số. */
			'lech_nop'   => ( $co_bank && $co && $nop > 0 ) ? $nop - $nop_bk : null,
			'lech_tm'    => $co ? $dem_xn - $tm : null,   // đếm được (hoặc nộp quỹ, xem dem_tu_nop) − POS ghi nhận
			/* 🔴 Anh Thắng 26/09/2026: "lệch ngược giữa chuyển khoản và tiền mặt" — nhân viên bấm nhầm
			   nút PTTT lúc bán, nên `pos_tm`/`pos_ck` sai NGƯỢC CHIỀU nhau. Phải là cột RIÊNG, không
			   được cộng chung với `lech_tm`: một bên +X một bên −X cộng lại ra 0, che mất đúng chỗ
			   đang sai. */
			'lech_ck'    => $co ? $ck_tt - $ck : null,    // chuyển khoản thực thu − POS ghi nhận
			'chua_nop'   => $co ? $dem_xn - $nop_that : null, // đếm được − tiền ngân hàng thật sự nhận
			'bill_huy'   => (int) $r['so_bill_huy'],
			'tien_huy'   => (float) $r['tien_bill_huy'],
			'khach'      => (int) $r['tong_khach'],
			/* Khách theo máy (đã bóc tách vé) — null khi chưa khai vé cho cơ sở này. */
			'khach_may'  => function_exists( 'khh_dt_khach_may_tu_mon' )
				? khh_dt_khach_may_tu_mon( khh_dt_json( isset( $r['mon'] ) ? $r['mon'] : '', array() ), khh_dt_ve_khach_bang( (string) $r['cua_hang'] ) )['khach'] : null,
			'chuyen'     => (int) $r['tong_chuyen'],
			/* Hàng bán: cơ sở chốt khớp máy hay lệch mấy món — để kế toán xác nhận. */
			'mon_lech'   => $co ? khh_dt_bc_mon_lech( isset( $r['mon'] ) ? $r['mon'] : '', isset( $r['mon_thuc'] ) ? $r['mon_thuc'] : null ) : null,
			've_giay'    => (int) $r['ve_giay'],
			'ghi_chu'    => (string) $r['ghi_chu'],
			'nguoi'      => (string) $r['nguoi'],
			'chot'       => (int) $r['chot'],
		);
	}

	/* Cộng dồn theo từng cơ sở, đi từ ngày cũ tới ngày mới. $ra đang xếp ngày mới trước. */
	$treo    = $mo_dau;
	$lan_nop = array();                       // cơ sở => ngày về gần nhất
	$cho     = array();                       // cơ sở => mấy dòng đang chờ được một cú nộp xoá
	for ( $i = count( $ra ) - 1; $i >= 0; $i-- ) {
		$c = $ra[ $i ]['cua_hang'];
		if ( ! isset( $treo[ $c ] ) ) {
			$treo[ $c ] = 0;
		}
		$treo[ $c ] += (float) $ra[ $i ]['phai_nop'] - (float) $ra[ $i ]['nop_bank'];
		if ( $treo[ $c ] < 0 ) {
			/* Nộp dư (gộp cả tiền của kỳ trước kỳ đang xem) — kẹp về 0, đừng để số âm chạy tiếp
			   rồi che mất phần treo của những ngày sau. */
			$treo[ $c ] = 0;
		}
		if ( $ra[ $i ]['nop_bank'] > 0 ) {
			$lan_nop[ $c ] = $ra[ $i ]['ngay'];
		}
		$ra[ $i ]['treo'] = $treo[ $c ];
		$ra[ $i ]['nop_gan_nhat'] = isset( $lan_nop[ $c ] ) ? $lan_nop[ $c ] : '';
		$ra[ $i ]['ngay_treo'] = isset( $lan_nop[ $c ] )
			? (int) round( ( strtotime( $ra[ $i ]['ngay'] ) - strtotime( $lan_nop[ $c ] ) ) / 86400 )
			: 0;

		/* ==========================================================================================
		 * 🔴 MỘT CÚ NỘP XOÁ LUÔN MẤY NGÀY TRƯỚC NÓ — VÀ PHẢI TÍCH LẠI MẤY NGÀY ẤY.
		 * ==========================================================================================
		 * Anh Thắng 16/09/2026: *"nếu cùng doanh thu, sau khi nộp thoả mãn đúng thì tích các ngày
		 * đúng đã nộp cho dễ hiểu"*.
		 *
		 * Cơ sở gom mấy ngày nộp một cục. Ngày 1–9 dồn tiền, ngày 10 nộp 26.890.000 là xong sạch
		 * cả chín ngày — nhưng bản trước chín ngày ấy vẫn nằm im với chữ "đang dồn", chỉ mỗi ngày
		 * 10 có dấu tích. Nhìn bảng thì tưởng chín ngày kia còn nợ, trong khi tiền đã về đủ.
		 *
		 * Nên khi số dư treo chạm 0, ĐÁNH DẤU NGƯỢC LẠI cho mọi ngày đang chờ từ lần sạch trước
		 * tới đây. Từ đó "đã xong" là một sự thật đọc được ngay, không phải thứ người xem tự suy
		 * trong đầu.
		 * ========================================================================================== */
		$cho[ $c ][] = $i;
		if ( $treo[ $c ] < 1000 ) {
			foreach ( $cho[ $c ] as $j ) {
				$ra[ $j ]['da_xong'] = true;
			}
			$cho[ $c ] = array();
		}
	}
	/* Những dòng còn lại trong hàng chờ là phần thật sự chưa được nộp bù. */
	foreach ( $cho as $c => $ds_cho ) {
		foreach ( $ds_cho as $j ) {
			$ra[ $j ]['da_xong'] = false;
		}
	}

	return array(
		'dong'     => $ra,
		'ngay_nhac' => khh_dt_ngay_nhac(),
		'momo'      => $momo['tong'],
		'momo_ngay_co' => array_keys( $momo['ngay_co'] ),
		/* Phí MoMo: nhập tay theo khoảng ngày × tài khoản (MoMo không đưa phí theo giao dịch —
		   đã kiểm cả Transaction report lẫn daily report), rồi chia về cơ sở theo % doanh thu.
		   `momo_phi_thieu` là mấy ngày có doanh thu MoMo mà chưa ai nhập phí, để màn hình nhắc. */
		'momo_phi'  => function_exists( 'khh_dt_momo_phi_chia' ) ? khh_dt_momo_phi_chia( $tu, $den ) : array( 'co_so' => array(), 'tong' => 0 ),
		'momo_phi_thieu' => function_exists( 'khh_dt_momo_phi_thieu' ) ? khh_dt_momo_phi_thieu( $tu, $den ) : array(),
		/* Tài khoản hệ đã biết, và mấy lượt phí đã nhập trong kỳ — để ô nhập LÚC NÀO CŨNG mở
		   được, không phải chờ hệ đoán ra ngày nào còn thiếu. Bản 1.38.0 chỉ hiện ô nhập khi có
		   ngày thiếu, mà muốn có ngày thiếu thì phải nạp lại sao kê kèm mã tài khoản trước —
		   thành ra tính năng có mà không ai vào được. */
		'momo_tk_ds'  => function_exists( 'khh_dt_momo_tk_ds' ) ? khh_dt_momo_tk_ds() : array(),
		/* CHỈ tài khoản đã ghép được cơ sở — dùng để quyết định có cảnh báo hay không. Gộp với
		   `momo_tk_ds` (có cả tài khoản mới chỉ nhập phí) là lỗi của 1.39.0. */
		'momo_tk_ghep' => function_exists( 'khh_dt_momo_tk_da_ghep' ) ? khh_dt_momo_tk_da_ghep() : array(),
		'momo_phi_ds' => function_exists( 'khh_dt_momo_phi_ds' ) ? khh_dt_momo_phi_ds( $tu, $den ) : array(),
		/* Mọi mã cửa hàng MoMo trong kỳ, để màn hình bày ra cho người ta tự ghép vào tài khoản.
		   Không có nó thì cách duy nhất để ghép là nạp lại sao kê cả tháng kèm mã tài khoản. */
		'momo_ma_ch_ds' => function_exists( 'khh_dt_momo_ma_ch_ds' ) ? khh_dt_momo_ma_ch_ds( $tu, $den ) : array(),
		/* Ô nào lấy từ sổ gộp thì chỉ có TỔNG ngày — không tra ngược xuống giao dịch được. Bày ra
		   để bảng đừng hứa điều nó không làm được. */
		'momo_nguon_o' => isset( $momo['nguon_o'] ) ? $momo['nguon_o'] : array(),
		'co_momo'   => ( function_exists( 'khh_dt_co_momo_sk' ) && khh_dt_co_momo_sk() ) ? true : (bool) khh_dt_nguon_momo(),
		'pos_som'   => $pos_som,
		'cua_toi'  => khh_dt_co_so_mac_dinh(),
		'nguong'   => khh_dt_nguong(),
		'co_bank'  => (bool) khh_dt_co_sao_ke(),
		/* 🔴 NÓI RÕ SỐ "NGÂN HÀNG NHẬN" ĐANG LẤY TỪ SỔ NÀO, VÀ SỔ ẤY CÓ TỚI NGÀY NÀO.
		   16/09/2026: anh Thắng chọn nhầm `wpt9_vhg_thu` — sổ tiền ghế massage, không phải sao kê
		   ngân hàng — rồi cả tháng Lotte Gò Vấp đỏ rực "chưa nộp 43 tr". Con số ấy đúng theo cái
		   sổ đang đọc, nhưng cái sổ thì sai, mà màn không hề nói nó đang đọc sổ nào. Người xem
		   không có cách nào biết mình đang bị lừa bởi một nguồn sai. */
		'nguon_bank' => khh_dt_nguon_mo_ta(),
		/* Bày ra số dòng tiền chưa gán được cơ sở. Mỗi dòng bỏ sót là một khoản "chưa nộp" GIẢ,
		   tố oan một người đã nộp tiền thật — nên nó phải nằm ngay trên bảng đối soát. */
		'sk_chua_gan' => khh_dt_sk_chua_gan(),
	);
}

/**
 * Số dư tiền mặt còn treo của từng cơ sở TRƯỚC ngày $tu.
 *
 * ⚠️ KHÔNG CÓ SỐ MỞ ĐẦU THÌ CỘNG DỒN VÔ NGHĨA. Xem kỳ 7 ngày mà bắt đầu từ 0 thì một cơ sở đang
 *    ôm 40 triệu từ tháng trước trông vẫn sạch sẽ.
 */
function khh_dt_treo_truoc( $tu ) {
	global $wpdb;
	if ( ! $tu ) {
		return array();
	}
	$hom_truoc = gmdate( 'Y-m-d', strtotime( $tu . ' -1 day' ) );
	$bang      = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT cua_hang, pttt FROM $bang WHERE ngay <= %s", $hom_truoc ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	$ra = array();
	foreach ( $rows as $r ) {
		$tm = 0;
		foreach ( khh_dt_json( $r['pttt'], array() ) as $p ) {
			if ( false !== strpos( khh_dt_khong_dau( isset( $p['n'] ) ? $p['n'] : '' ), 'tien mat' ) ) {
				$tm += (float) $p['r'];
			}
		}
		$c = (string) $r['cua_hang'];
		$ra[ $c ] = ( isset( $ra[ $c ] ) ? $ra[ $c ] : 0 ) + $tm;
	}
	foreach ( khh_dt_nop_bank( '', $hom_truoc ) as $k => $b ) {
		$c = substr( $k, strpos( $k, '|' ) + 1 );
		if ( isset( $ra[ $c ] ) ) {
			$ra[ $c ] -= (float) $b['tien'];
		}
	}
	foreach ( $ra as $c => $v ) {
		if ( $v < 0 ) {
			$ra[ $c ] = 0;
		}
	}
	return $ra;
}

/** Ngày sớm nhất có số liệu POS trong kho — để biết kỳ nào so được, kỳ nào không. */
function khh_dt_ngay_som_nhat() {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$x = $wpdb->get_var( "SELECT MIN(ngay) FROM $bang" );
	return $x ? (string) $x : '';
}

/** Treo quá bao nhiêu ngày thì nhắc. Cơ sở nộp gộp mỗi tuần một lần là bình thường; quá 10 ngày thì không. */
function khh_dt_ngay_nhac() {
	return (int) get_option( 'khh_dt_ngay_nhac', 10 );
}

/** Ngưỡng bôi đỏ: lệch quá 2% hoặc quá 500.000 ₫ một ngày một cơ sở. */
function khh_dt_nguong() {
	return array(
		'phan_tram' => (float) get_option( 'khh_dt_nguong_pt', 2 ),
		'so_tien'   => (float) get_option( 'khh_dt_nguong_tien', 500000 ),
	);
}
