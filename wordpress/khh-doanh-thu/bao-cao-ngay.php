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
 *   - tiền thực nộp về quỹ                  -> phần thu rồi mà chưa nộp
 *   - số bill huỷ và tiền huỷ               -> chỗ cổ điển để rút tiền mặt
 *   - tổng lượt chạy / tổng khách vào       -> khách vào mà không có vé
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
			tien_nop double NOT NULL DEFAULT 0,
			so_bill_huy int(11) NOT NULL DEFAULT 0,
			tien_bill_huy double NOT NULL DEFAULT 0,
			tong_chuyen int(11) NOT NULL DEFAULT 0,
			tong_khach int(11) NOT NULL DEFAULT 0,
			ve_giay int(11) NOT NULL DEFAULT 0,
			ghi_chu text NOT NULL,
			nguoi varchar(100) NOT NULL DEFAULT '',
			nguoi_id bigint(20) unsigned NOT NULL DEFAULT 0,
			chot tinyint(1) NOT NULL DEFAULT 0,
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
	return ! $ds || in_array( (string) $cua_hang, $ds, true );
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
	return array(
		'doanh_thu'  => (float) $r['doanh_thu'],
		'thanh_tien' => (float) $r['thanh_tien'],
		'chiet_khau' => (float) $r['chiet_khau'],
		'so_hd'      => (int) $r['so_hd'],
		'so_mon'     => (float) $r['so_mon'],
		'so_ve'      => (float) $r['so_ve'],
		'tien_mat'   => $tien_mat,
		'ck'         => $ck,
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
	}
	return array(
		'pos'      => khh_dt_so_pos( $ngay, $ch ),
		'bao_cao'  => $r,
		'duoc_ghi' => khh_dt_duoc_cua_hang( $ch ),
		'cua_toi'  => khh_dt_co_so_mac_dinh(),
		'cua_toi_ds' => khh_dt_co_so_ds(),
	);
}

function khh_dt_rest_bc_luu( $req ) {
	global $wpdb;
	$ngay = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'ngay' ) );
	$ch   = sanitize_text_field( (string) $req->get_param( 'cua_hang' ) );
	if ( ! $ngay || ! $ch ) {
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
		'tien_nop'      => $so( 'tien_nop' ),
		'so_bill_huy'   => (int) $so( 'so_bill_huy' ),
		'tien_bill_huy' => $so( 'tien_bill_huy' ),
		'tong_chuyen'   => (int) $so( 'tong_chuyen' ),
		'tong_khach'    => (int) $so( 'tong_khach' ),
		've_giay'       => (int) $so( 've_giay' ),
		'ghi_chu'       => sanitize_textarea_field( (string) $req->get_param( 'ghi_chu' ) ),
		'chot'          => $req->get_param( 'chot' ) ? 1 : 0,
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$cu = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $ch ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	$ky = khh_dt_ten_ghi_so();
	// Sửa sau khi đã chốt thì giữ lại bản cũ, đừng để mất dấu.
	$lich_su = array();
	if ( $cu ) {
		$lich_su = khh_dt_json( $cu['lich_su'], array() );
		$khac    = false;
		foreach ( array( 'tien_mat_dem', 'tien_nop', 'so_bill_huy', 'tien_bill_huy', 'tong_chuyen', 'tong_khach', 've_giay', 'ghi_chu' ) as $k ) {
			if ( (string) $cu[ $k ] !== (string) $moi[ $k ] ) {
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
	$sql = "SELECT p.ngay, p.cua_hang, p.doanh_thu, p.so_hd, p.so_ve, p.pttt,
				b.tien_mat_dem, b.tien_nop, b.so_bill_huy, b.tien_bill_huy,
				b.tong_chuyen, b.tong_khach, b.ve_giay, b.ghi_chu, b.nguoi, b.chot
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
		foreach ( khh_dt_json( $r['pttt'], array() ) as $p ) {
			if ( false !== strpos( khh_dt_khong_dau( isset( $p['n'] ) ? $p['n'] : '' ), 'tien mat' ) ) {
				$tm += (float) $p['r'];
			} else {
				$ck += (float) $p['r'];
			}
		}
		$co  = null !== $r['tien_mat_dem'];
		$dem = (float) $r['tien_mat_dem'];
		$nop = (float) $r['tien_nop'];                   // cơ sở KHAI đã nộp
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
		$phai_nop = $co ? $dem : $tm;
		$thieu    = $phai_nop - $nop_bk;
		$ra[] = array(
			'ngay'       => $r['ngay'],
			'cua_hang'   => $r['cua_hang'],
			'doanh_thu'  => (float) $r['doanh_thu'],
			'so_hd'      => (int) $r['so_hd'],
			'so_ve'      => (float) $r['so_ve'],
			'pos_tm'     => $tm,
			'pos_ck'     => $ck,
			'co_bao_cao' => $co,
			'dem'        => $dem,
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
			'lech_tm'    => $co ? $dem - $tm : null,      // đếm được − POS ghi nhận
			'chua_nop'   => $co ? $dem - $nop_that : null, // đếm được − tiền ngân hàng thật sự nhận
			'bill_huy'   => (int) $r['so_bill_huy'],
			'tien_huy'   => (float) $r['tien_bill_huy'],
			'khach'      => (int) $r['tong_khach'],
			'chuyen'     => (int) $r['tong_chuyen'],
			've_giay'    => (int) $r['ve_giay'],
			'ghi_chu'    => (string) $r['ghi_chu'],
			'nguoi'      => (string) $r['nguoi'],
			'chot'       => (int) $r['chot'],
		);
	}
	return array(
		'dong'     => $ra,
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

/** Ngưỡng bôi đỏ: lệch quá 2% hoặc quá 500.000 ₫ một ngày một cơ sở. */
function khh_dt_nguong() {
	return array(
		'phan_tram' => (float) get_option( 'khh_dt_nguong_pt', 2 ),
		'so_tien'   => (float) get_option( 'khh_dt_nguong_tien', 500000 ),
	);
}
