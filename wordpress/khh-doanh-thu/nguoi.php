<?php
/**
 * NGƯỜI ĐƯỢC ĐẨY TỪ TRANG NHÂN SỰ SANG — SỔ NGƯỜI DÙNG VÀ CỬA ĐĂNG NHẬP BẰNG PIN.
 *
 * Anh Thắng 15/09/2026: *"lấy giúp anh dữ liệu nhân viên bên chi phí (vì bên đó là các cửa hàng
 * trưởng được quyền nhập báo cáo — lấy bên nhân sự) theo kiểu chi phí, bổ sung thêm cột bên trang
 * nhân sự để cấp quyền đẩy sang"*.
 *
 * Bên chấm công có `VHCC_DayBaoCao` đẩy sang đây, mỗi người một hàng: mã NV · họ tên · PIN · mã cơ
 * sở · vai. Ba hàm dưới đây là cả cái cổng ấy — `khh_dt_day_vao()`, `khh_dt_day_ra()`,
 * `khh_dt_da_day()`. Bên kia dò đúng ba tên này trước khi gọi, nên ĐỔI TÊN LÀ TẮT CỘT.
 *
 * ==================================================================================================
 * 🔴 KHÔNG TẠO TÀI KHOẢN WORDPRESS CHO HỌ.
 * ==================================================================================================
 * Cửa hàng trưởng đã có PIN chấm công đang gõ hằng ngày, và cả nhà (chi phí, ghế, nội bộ) đều vào
 * bằng PIN ấy. Đẻ thêm cho mỗi người một tài khoản WordPress là đẻ thêm một mật khẩu để quên, một
 * hộp thư phải có, và một tài khoản nữa nằm lại trong site sau khi người ta nghỉ việc. Nên ở đây
 * họ là một HÀNG TRONG BẢNG, không phải một `WP_User`; gỡ ở trang nhân sự là hàng ấy biến mất.
 *
 * ⚠️ NGƯỜI VÀO BẰNG PIN KHÔNG BAO GIỜ NẠP ĐƯỢC FILE POS VÀ KHÔNG XOÁ ĐƯỢC KHO. Hai việc ấy vẫn đòi
 *    `edit_posts` — tức người của văn phòng đăng nhập bằng tài khoản WordPress thật.
 *
 * ⚠️ CHƯA GHÉP MÃ CƠ SỞ THÌ KHÔNG THẤY GÌ, CHỨ KHÔNG PHẢI THẤY HẾT. Bên nhân sự cơ sở là mã
 *    (`FZ_SC_VIVO_T4`), bên máy POS là tên dài (*"TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí
 *    K&H )"*). Ô cơ sở để trống ở đây vốn có nghĩa "xem được mọi cơ sở" — nên nếu người mới đẩy
 *    sang mà chưa khai bảng ghép, mà mình để trống, thì một cửa hàng trưởng mở ra là thấy doanh
 *    thu cả 15 quán. Chưa ghép thì trả về một mã không khớp cơ sở nào: họ vào được, thấy rỗng, và
 *    màn nói thẳng là chưa khai bảng ghép.
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Phiên PIN sống 30 ngày, y như bên chi phí — cửa hàng trưởng không phải đăng nhập lại mỗi ca. */
const KHH_DT_PHIEN_HAN = 2592000;

/** Trả về khi người dùng đã đẩy sang nhưng mã cơ sở của họ chưa ghép với tên cơ sở bên POS. */
const KHH_DT_CHUA_GHEP = "\0chua-ghep-co-so";

function khh_dt_bang_nguoi() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_nguoi';
}

function khh_dt_bang_phien() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_phien';
}

function khh_dt_tao_bang_nguoi() {
	global $wpdb;
	$charset = $wpdb->get_charset_collate();
	$ng      = khh_dt_bang_nguoi();
	$ph      = khh_dt_bang_phien();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $ng (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ma_nv varchar(40) NOT NULL DEFAULT '',
			ho_ten varchar(190) NOT NULL DEFAULT '',
			pin varchar(16) NOT NULL DEFAULT '',
			coso_ma varchar(80) NOT NULL DEFAULT '',
			vai varchar(10) NOT NULL DEFAULT 'nhap',
			cap_nhat datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_nv (ma_nv),
			KEY pin (pin)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE $ph (
			token char(64) NOT NULL,
			ma_nv varchar(40) NOT NULL DEFAULT '',
			het_han datetime NOT NULL,
			PRIMARY KEY  (token),
			KEY ma_nv (ma_nv)
		) $charset;"
	);
}

/* ================================================================== *
 * Bảng ghép mã cơ sở (nhân sự) ↔ tên cơ sở (máy POS)
 * ================================================================== */

/**
 * Tách chuỗi mã cơ sở ("FZ_ADV_TP, TUTU_TP") thành danh sách, viết hoa, bỏ trùng.
 *
 * Một người có thể phụ trách HAI cơ sở — chuyện thật của nhà mình, và bên nhân sự đã khai được
 * như thế từ 31/08/2026 (`nhan_vien.coso_phu`). Nên chỗ nào ở đây cũng phải nghĩ bằng DANH SÁCH,
 * không phải một chuỗi.
 */
function khh_dt_tach_ma( $chuoi ) {
	$ra = array();
	foreach ( explode( ',', (string) $chuoi ) as $x ) {
		$x = strtoupper( trim( $x ) );
		if ( '' !== $x && ! in_array( $x, $ra, true ) ) {
			$ra[] = $x;
		}
	}
	return $ra;
}

/**
 * [ MÃ => [ tên cơ sở đúng như trong số liệu POS, … ] ].
 *
 * 🔴 MỘT MÃ GHÉP ĐƯỢC NHIỀU QUÁN TRÊN MÁY POS. Anh Thắng 15/09/2026, đang mở ô chọn của
 *    `FZ_ADV_TP`: *"bạn này muốn chọn 2 cơ sở để nhập thì sao"*. Cùng một điểm Gò An Lạc mà máy
 *    POS tách ra hai quán — *"FUNZONE ADVENTURE GO AN LẠC"* và *"COFFE GO AN LẠC"* — trong khi
 *    sổ nhân sự chỉ có một mã, và một cửa hàng trưởng coi cả hai. Ghép một-đổi-một thì chị ấy
 *    nhập được khu vui chơi, còn quán cà phê ngay cạnh thì không, mà không có chỗ nào khai thêm.
 */
function khh_dt_ghep_ds() {
	$x = get_option( 'khh_dt_ghep_coso', array() );
	return is_array( $x ) ? $x : array();
}

/**
 * Những tên cơ sở bên POS của một mã; mảng rỗng nếu chưa khai.
 *
 * ⚠️ NHẬN CẢ HÌNH DẠNG CŨ. Bản 1.6.0 lưu mỗi mã một chuỗi; site của anh Thắng đã khai vài mã
 *    bằng bản ấy rồi. Đọc chuỗi như một danh sách một phần tử, không bắt ai khai lại.
 */
function khh_dt_ghep_ten_ds( $ma ) {
	$ma = strtoupper( trim( (string) $ma ) );
	if ( '' === $ma ) {
		return array();
	}
	$ds = khh_dt_ghep_ds();
	if ( ! isset( $ds[ $ma ] ) ) {
		return array();
	}
	$v  = $ds[ $ma ];
	$ra = array();
	foreach ( is_array( $v ) ? $v : array( $v ) as $x ) {
		$t = trim( (string) $x );
		if ( '' !== $t && ! in_array( $t, $ra, true ) ) {
			$ra[] = $t;
		}
	}
	return $ra;
}

/** Khai lại cả bảng ghép. Danh sách rỗng = bỏ khai mã đó. */
function khh_dt_dat_ghep( $bang ) {
	$sach = array();
	foreach ( (array) $bang as $ma => $ten ) {
		$ma  = strtoupper( sanitize_text_field( (string) $ma ) );
		if ( '' === $ma ) {
			continue;
		}
		$ds = array();
		foreach ( is_array( $ten ) ? $ten : array( $ten ) as $t ) {
			$t = sanitize_text_field( (string) $t );
			if ( '' !== $t && ! in_array( $t, $ds, true ) ) {
				$ds[] = $t;
			}
		}
		if ( $ds ) {
			$sach[ $ma ] = $ds;
		}
	}
	update_option( 'khh_dt_ghep_coso', $sach, false );
	return $sach;
}

/**
 * Những mã cơ sở đang có người mà chưa khai tên POS — để màn Quản trị nhắc.
 *
 * ⚠️ BỎ QUA MÃ MÀ MỌI NGƯỜI Ở ĐÓ ĐỀU LÀ 'duyet'. Kế toán và văn phòng đứng ở `VP_KH-HCM` — một
 *    mã không phải quán nào cả, không bao giờ ghép được, mà họ cũng không cần: vai 'duyet' xem
 *    tổng mọi cơ sở. Nhắc một việc không làm được và cũng không cần làm thì lần sau không ai đọc
 *    lời nhắc nữa, kể cả lời nhắc thật.
 */
function khh_dt_ma_chua_ghep() {
	global $wpdb;
	$ng = khh_dt_bang_nguoi();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results( "SELECT coso_ma, vai FROM $ng WHERE coso_ma<>''", ARRAY_A );
	$ra = array();
	foreach ( $ds as $r ) {
		if ( 'duyet' === (string) $r['vai'] ) {
			continue;
		}
		foreach ( khh_dt_tach_ma( (string) $r['coso_ma'] ) as $ma ) {
			if ( ! khh_dt_ghep_ten_ds( $ma ) && ! in_array( $ma, $ra, true ) ) {
				$ra[] = $ma;
			}
		}
	}
	return $ra;
}

/* ================================================================== *
 * Cổng nhận người từ trang nhân sự — ĐÚNG BA TÊN HÀM NÀY
 * ================================================================== */

function khh_dt_nguoi( $ma_nv ) {
	global $wpdb;
	$ma = strtoupper( trim( (string) $ma_nv ) );
	if ( '' === $ma ) {
		return null;
	}
	$ng = khh_dt_bang_nguoi();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $ng WHERE ma_nv=%s", $ma ), ARRAY_A );
	return $r ? $r : null;
}

/** Người này đã được đẩy sang chưa. */
function khh_dt_da_day( $ma_nv ) {
	return (bool) khh_dt_nguoi( $ma_nv );
}

/**
 * Nhận một người từ trang nhân sự.
 *
 * $hs = [ ma_nv, ho_ten, pin, vai ('nhap'|'duyet'), coso (MÃ cơ sở bên nhân sự) ]
 *
 * Trả về [ ok, viec ('them'|'sua'), mat_pin (tên những người vừa bị xoá PIN vì trùng),
 *          chua_ghep (true nếu mã cơ sở chưa khai trong bảng ghép) ].
 */
function khh_dt_day_vao( $hs ) {
	global $wpdb;
	$hs = (array) $hs;
	$ma = strtoupper( trim( (string) ( isset( $hs['ma_nv'] ) ? $hs['ma_nv'] : '' ) ) );
	$ten = trim( (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ) );
	$pin = trim( (string) ( isset( $hs['pin'] ) ? $hs['pin'] : '' ) );
	$vai = (string) ( isset( $hs['vai'] ) ? $hs['vai'] : 'nhap' );
	$cs  = implode( ',', khh_dt_tach_ma( isset( $hs['coso'] ) ? $hs['coso'] : '' ) );

	if ( '' === $ma ) {
		return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' );
	}
	if ( '' === $ten ) {
		return array( 'ok' => false, 'error' => 'Thiếu họ tên.' );
	}
	if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
		return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số.' );
	}
	if ( ! in_array( $vai, array( 'nhap', 'duyet' ), true ) ) {
		$vai = 'nhap';
	}

	$ng  = khh_dt_bang_nguoi();
	$cu  = khh_dt_nguoi( $ma );

	/* 🔴 PIN TRÙNG THÌ NGƯỜI CŨ MẤT PIN, VÀ PHẢI KỂ LẠI.
	   Đăng nhập ở đây chỉ hỏi PIN chứ không hỏi tên, nên hai người cùng PIN là hai người cùng
	   một cửa: ai gõ trước vào trước, và màn sẽ tưởng người này là người kia. Giữ im lặng thì
	   sáng hôm sau có người nhập báo cáo vào quán của người khác. */
	$mat_pin = array();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$trung = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT ma_nv, ho_ten FROM $ng WHERE pin=%s AND ma_nv<>%s", $pin, $ma ),
		ARRAY_A
	);
	foreach ( $trung as $t ) {
		$mat_pin[] = (string) $t['ho_ten'] . ' (' . (string) $t['ma_nv'] . ')';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $ng, array( 'pin' => '' ), array( 'ma_nv' => $t['ma_nv'] ) );
		khh_dt_dong_phien( (string) $t['ma_nv'] );
	}

	$hang = array(
		'ho_ten'   => $ten,
		'pin'      => $pin,
		'coso_ma'  => $cs,
		'vai'      => $vai,
		'cap_nhat' => current_time( 'mysql' ),
	);

	if ( $cu ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $ng, $hang, array( 'ma_nv' => $ma ) );
		$viec = 'sua';
		/* Đổi cơ sở hay hạ vai mà phiên cũ còn sống thì phiên ấy vẫn mang quyền cũ — nhưng phiên
		   ở đây chỉ trả lời "người này là ai", còn vai và cơ sở đọc lại từ bảng mỗi lượt gọi
		   (xem `khh_dt_phien_nguoi()`), nên không phải đá ai ra. Đổi PIN thì phải đá: PIN cũ
		   không còn là của họ nữa. */
		if ( (string) $cu['pin'] !== $pin ) {
			khh_dt_dong_phien( $ma );
		}
	} else {
		$hang['ma_nv'] = $ma;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $ng, $hang );
		$viec = 'them';
	}
	khh_dt_phien_quen();

	return array(
		'ok'        => true,
		'viec'      => $viec,
		'mat_pin'   => $mat_pin,
		/* Hai cơ sở mà mới ghép được một thì VẪN là chưa xong — người ấy nhập được quán này,
		   quán kia không thấy đâu, và đó là loại hỏng người ta không báo vì tưởng mình nhớ nhầm. */
		'chua_ghep' => ( '' !== $cs && (bool) array_filter(
			khh_dt_tach_ma( $cs ),
			function ( $m ) {
				return ! khh_dt_ghep_ten_ds( $m );
			}
		) ),
	);
}

/** Gỡ một người khỏi sổ, và đóng luôn phiên đang mở của họ. */
function khh_dt_day_ra( $ma_nv ) {
	global $wpdb;
	$ma = strtoupper( trim( (string) $ma_nv ) );
	if ( '' === $ma ) {
		return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( khh_dt_bang_nguoi(), array( 'ma_nv' => $ma ) );
	khh_dt_dong_phien( $ma );
	return array( 'ok' => true );
}

/* ================================================================== *
 * Đăng nhập bằng PIN
 * ================================================================== */

/** Khoá đếm lần gõ sai — theo IP, 10 lần là nghỉ 10 phút. */
function khh_dt_khoa_khoa() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	return 'khh_dt_sai_' . md5( $ip );
}

function khh_dt_dang_khoa() {
	return (int) get_transient( khh_dt_khoa_khoa() ) >= 10;
}

/**
 * Gõ PIN vào.
 *
 * ⚠️ KHÔNG NÓI PIN ẤY CÓ TỒN TẠI HAY KHÔNG. Một câu "PIN chưa được cấp" khác câu "sai PIN" là
 *    một cách dò: gõ đủ 10.000 số bốn chữ số thì biết số nào đang có người dùng.
 */
function khh_dt_pin_dang_nhap( $pin ) {
	global $wpdb;
	$pin = trim( (string) $pin );
	if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
		return array( 'ok' => false, 'error' => 'PIN gồm 4–8 chữ số.' );
	}
	if ( khh_dt_dang_khoa() ) {
		return array( 'ok' => false, 'error' => 'Gõ sai nhiều lần quá — thử lại sau 10 phút.' );
	}
	$ng = khh_dt_bang_nguoi();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $ng WHERE pin=%s LIMIT 1", $pin ), ARRAY_A );
	if ( ! $r ) {
		$k = khh_dt_khoa_khoa();
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );
		return array( 'ok' => false, 'error' => 'PIN không đúng.' );
	}
	delete_transient( khh_dt_khoa_khoa() );
	return array(
		'ok'     => true,
		'token'  => khh_dt_mo_phien( (string) $r['ma_nv'] ),
		'ho_ten' => (string) $r['ho_ten'],
	);
}

function khh_dt_mo_phien( $ma_nv ) {
	global $wpdb;
	$ph = khh_dt_bang_phien();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DELETE FROM $ph WHERE het_han < UTC_TIMESTAMP()" );
	$tok = bin2hex( random_bytes( 32 ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$ph,
		array(
			'token'   => $tok,
			'ma_nv'   => strtoupper( (string) $ma_nv ),
			'het_han' => gmdate( 'Y-m-d H:i:s', time() + KHH_DT_PHIEN_HAN ),
		)
	);
	return $tok;
}

function khh_dt_dong_phien( $ma_nv ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( khh_dt_bang_phien(), array( 'ma_nv' => strtoupper( (string) $ma_nv ) ) );
	khh_dt_phien_quen();
}

/** Thẻ phiên gửi kèm mỗi lượt gọi — ở header, không ở cookie (nên không dính CSRF). */
function khh_dt_phien_token() {
	$t = isset( $_SERVER['HTTP_X_KHH_PHIEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_KHH_PHIEN'] ) ) : '';
	return preg_match( '/^[0-9a-f]{64}$/', $t ) ? $t : '';
}

/**
 * Người đang mở màn bằng PIN — null nếu không có phiên.
 *
 * 🔴 VAI VÀ CƠ SỞ ĐỌC LẠI TỪ BẢNG NGƯỜI, KHÔNG TIN THẺ PHIÊN — đúng bài học đã cắn ở hệ chi phí
 *    08/09/2026: thẻ sống 30 ngày, nên vai ghi trong thẻ là vai của một tháng trước. Chiều nguy
 *    hiểm là chiều THU HỒI: gỡ một người ở trang nhân sự mà thẻ vẫn còn quyền cũ thì cái gỡ ấy
 *    không có thật. Thẻ chỉ trả lời đúng một câu: người này là ai.
 */
function khh_dt_phien_nguoi() {
	global $wpdb;
	/* Nhớ trong một lượt gọi thôi — mỗi lượt REST hỏi tới bốn năm lần (quyền xem, quyền ghi, cơ
	   sở, tên người ghi sổ) mà đều là cùng một câu hỏi. Mỗi lần ghi vào sổ người thì quên đi,
	   xem `khh_dt_phien_quen()`. */
	if ( array_key_exists( 'khh_dt_phien_nho', $GLOBALS ) ) {
		return $GLOBALS['khh_dt_phien_nho'];
	}
	$GLOBALS['khh_dt_phien_nho'] = null;
	$tok = khh_dt_phien_token();
	if ( '' === $tok ) {
		return null;
	}
	$ph = khh_dt_bang_phien();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ma = $wpdb->get_var( $wpdb->prepare( "SELECT ma_nv FROM $ph WHERE token=%s AND het_han > UTC_TIMESTAMP()", $tok ) );
	if ( ! $ma ) {
		return null;
	}
	$GLOBALS['khh_dt_phien_nho'] = khh_dt_nguoi( $ma );
	return $GLOBALS['khh_dt_phien_nho'];
}

/**
 * Quên câu trả lời đã nhớ.
 *
 * ⚠️ PHẢI GỌI SAU MỖI LẦN SỬA SỔ NGƯỜI. Đẩy lại một người rồi hỏi tiếp trong cùng lượt mà vẫn
 *    nhận hàng cũ thì mọi phép kiểm sau đó đang kiểm một bản đã chết — đúng loại lỗi chỉ hiện ra
 *    khi hai việc xảy ra trong một lượt, tức là hiếm, tức là khó lần.
 */
function khh_dt_phien_quen() {
	unset( $GLOBALS['khh_dt_phien_nho'] );
}

function khh_dt_phien_dong() {
	global $wpdb;
	$tok = khh_dt_phien_token();
	if ( '' !== $tok ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( khh_dt_bang_phien(), array( 'token' => $tok ) );
	}
	return array( 'ok' => true );
}

/**
 * Những cơ sở (tên bên POS) mà người đang mở màn được đụng tới. MẢNG RỖNG = mọi cơ sở.
 *
 * 🔴 VAI 'duyet' THÌ XEM TỔNG, KHÔNG BỊ BÓ VÀO CƠ SỞ CỦA MÌNH.
 *    Anh Thắng 15/09/2026: *"phân ra kế toán xem được tổng cơ sở"*. Kế toán ngồi ở VP_KH-HCM —
 *    một mã KHÔNG PHẢI quán nào cả, nên không bao giờ ghép được sang tên cơ sở POS. Nếu vẫn bó
 *    theo cơ sở thì người duy nhất cần nhìn cả 15 quán lại là người thấy rỗng. Vai 'duyet' vốn
 *    đã nghĩa là "nhập, và xem đối soát của mọi cơ sở".
 */
function khh_dt_phien_co_so_ds() {
	$n = khh_dt_phien_nguoi();
	if ( ! $n ) {
		return array();
	}
	if ( 'duyet' === (string) $n['vai'] ) {
		return array();
	}
	$ma_ds = khh_dt_tach_ma( (string) $n['coso_ma'] );
	if ( ! $ma_ds ) {
		return array();
	}
	$ten_ds = array();
	foreach ( $ma_ds as $ma ) {
		foreach ( khh_dt_ghep_ten_ds( $ma ) as $ten ) {
			if ( ! in_array( $ten, $ten_ds, true ) ) {
				$ten_ds[] = $ten;
			}
		}
	}
	/* Chưa ghép được mã nào -> KHÔNG thấy cơ sở nào (mảng rỗng ở đây nghĩa là THẤY HẾT, nên phải
	   trả một tên không khớp quán nào). Xem đầu tệp. */
	return $ten_ds ? $ten_ds : array( KHH_DT_CHUA_GHEP );
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_rest_nguoi' );
function khh_dt_rest_nguoi() {
	register_rest_route(
		'khh-dt/v1',
		'/dang-nhap',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_dang_nhap',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/dang-xuat',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_dang_xuat',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/ghep-co-so',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_ghep',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/ghep-co-so',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_dat_ghep',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
}

function khh_dt_rest_dang_nhap( $req ) {
	$kq = khh_dt_pin_dang_nhap( (string) $req->get_param( 'pin' ) );
	if ( empty( $kq['ok'] ) ) {
		return new WP_Error( 'khh_dt_pin', $kq['error'], array( 'status' => 403 ) );
	}
	return $kq;
}

function khh_dt_rest_dang_xuat() {
	return khh_dt_phien_dong();
}

/** Bảng ghép + những mã đang có người mà chưa khai. */
function khh_dt_rest_ghep() {
	global $wpdb;
	$ng = khh_dt_bang_nguoi();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$nguoi = (array) $wpdb->get_results( "SELECT ma_nv, ho_ten, coso_ma, vai, cap_nhat FROM $ng ORDER BY coso_ma, ho_ten", ARRAY_A );
	$ma_ds = array();
	foreach ( $nguoi as $i => $n ) {
		$nguoi[ $i ]['coso_ds'] = khh_dt_tach_ma( (string) $n['coso_ma'] );
		foreach ( $nguoi[ $i ]['coso_ds'] as $m ) {
			$ma_ds[ $m ] = true;
		}
	}
	foreach ( array_keys( khh_dt_ghep_ds() ) as $m ) {
		$ma_ds[ $m ] = true;
	}
	ksort( $ma_ds );

	$ghep = array();
	foreach ( array_keys( $ma_ds ) as $m ) {
		$ghep[] = array(
			'ma'     => $m,
			'ten_ds' => khh_dt_ghep_ten_ds( $m ),
		);
	}
	return array(
		'ghep'      => $ghep,
		'cua_hang'  => khh_dt_ds_cua_hang(),
		'nguoi'     => $nguoi,
		'chua_ghep' => khh_dt_ma_chua_ghep(),
	);
}

function khh_dt_rest_dat_ghep( $req ) {
	$bang = $req->get_param( 'ghep' );
	if ( is_string( $bang ) ) {
		$bang = json_decode( $bang, true );
	}
	if ( ! is_array( $bang ) ) {
		return new WP_Error( 'khh_dt_ghep', 'Không đọc được bảng ghép gửi lên.', array( 'status' => 400 ) );
	}
	khh_dt_dat_ghep( $bang );
	return khh_dt_rest_ghep();
}
