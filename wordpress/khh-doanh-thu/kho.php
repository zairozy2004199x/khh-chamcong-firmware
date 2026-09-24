<?php
/**
 * SỔ KHO HÀNG HOÁ — nhân viên khai cuối ngày, hệ đối chiếu với FABi.
 *
 * 20/09/2026 anh Thắng chốt, từng câu một:
 *   · *"B"* — làm quản kho thật, không chỉ xem hàng bán được bao nhiêu;
 *   · *"chỗ nhập cho nhân viên nhập cuối hàng còn bao nhiêu bán bao nhiêu"*;
 *   · *"Sau đó đối chiếu với dữ liệu fabi"*;
 *   · *"Nếu sau fabi phát sinh món hệ thống tự tách thêm ô nhập"*;
 *   · *"Có những món nằm trong combo nên cũng tự hiểu tách ra số lượng tồn kho nhé"*.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * HAI CON SỐ ĐỘC LẬP, VÀ ĐÓ LÀ TOÀN BỘ GIÁ TRỊ CỦA SỔ NÀY
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Nhân viên khai `bán` và `còn`; máy POS ghi `bán`. Hai nguồn không nhìn thấy nhau, nên đối
 * chiếu mới có nghĩa:
 *
 *   lệch khai  = nhân viên khai bán  −  máy POS ghi bán
 *   tồn tính   = tồn đầu + nhập − máy POS ghi bán − chạy combo
 *   lệch kho   = nhân viên đếm còn  −  tồn tính
 *
 * 🔴 TỒN TÍNH LẤY SỐ CỦA MÁY, KHÔNG LẤY SỐ NHÂN VIÊN KHAI. Lấy số khai thì `lệch kho` luôn
 *    bằng 0 dù thực tế mất hàng — người khai thiếu bao nhiêu thì tồn tính cũng thừa bấy nhiêu,
 *    hai vế tự triệt tiêu. Cả sổ sẽ xanh mướt và vô dụng.
 *
 * 🔴 TỒN ĐẦU BẮT NGUỒN TỪ LẦN ĐẾM GẦN NHẤT, KHÔNG PHẢI TỪ CHUỖI TÍNH TỪ ĐẦU. Đếm tay là sự
 *    thật; số tính là suy ra. Một ngày lệch mà cứ tính tiếp từ số suy ra thì cái lệch ấy theo
 *    mãi về sau, và mọi ngày sau đều đỏ vì một lỗi đã xử lý xong từ lâu.
 *
 * 🔴 MÓN TRONG COMBO PHẢI TRỪ KHO. Bán một "Combo 2 người" là một chai nước rời kho, nhưng
 *    FABi ghi doanh thu vào tên combo chứ không vào tên chai nước. Không tách thành phần ra
 *    thì chai nước ấy mãi mãi "chưa bán", tồn tính cứ thừa dần, tới lúc nhìn lại thì không ai
 *    biết thừa từ đâu. Bảng thành phần combo do người đặt một lần — hệ không tự đoán được.
 */

defined( 'ABSPATH' ) || exit;

/** Lùi tối đa ngần này ngày để dựng lại tồn đầu. */
const KHH_DT_KHO_LUI = 90;

function khh_dt_bang_kho() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_kho';
}

function khh_dt_tao_bang_kho() {
	global $wpdb;
	$bang    = khh_dt_bang_kho();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ngay date NOT NULL,
			co_so varchar(190) NOT NULL DEFAULT '',
			mat_hang varchar(190) NOT NULL DEFAULT '',
			nhap double NOT NULL DEFAULT 0,
			ban_khai double NULL DEFAULT NULL,
			combo_tay double NOT NULL DEFAULT 0,
			dem double NULL DEFAULT NULL,
			dat_dau double NULL DEFAULT NULL,
			huy double NOT NULL DEFAULT 0,
			ghi_chu varchar(190) NOT NULL DEFAULT '',
			nguoi varchar(120) NOT NULL DEFAULT '',
			luc datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY mot_dong (ngay,co_so(60),mat_hang(60)),
			KEY ngay (ngay)
		) $charset;"
	);
}

/* ================================================================== *
 * SỔ GHI ĐỘNG — append-only, không sửa được
 * ================================================================== */

/**
 * Lịch sử mọi lượt khai. GHI THÊM, KHÔNG BAO GIỜ SỬA.
 *
 * 21/09/2026, sau khi đọc lối làm của ERPNext và Odoo: cả hai đều ghi một sổ ghi động BẤT BIẾN
 * (Stock Ledger Entry / bút toán kép), rồi tồn hiện tại chỉ là tổng của sổ ấy — "đã ghi thì
 * không sửa, sai thì ghi một bút toán bù". Cách em làm ban đầu thì ngược: `ON DUPLICATE KEY
 * UPDATE`, tức khai lại là GHI ĐÈ và chỉ còn người với giờ của lần cuối.
 *
 * 🔴 VỚI MỘT SỔ SINH RA ĐỂ BẮT THẤT THOÁT, ĐÓ LÀ LỖ TO NHẤT.
 *    Người đang bị đối soát tự sửa lại con số mình đã khai, và không còn dấu vết nào cho thấy
 *    đã từng có số khác. Đếm thiếu, thấy cột lệch đỏ, sửa số đếm cho khớp — xong, sổ xanh. Cả
 *    bộ máy đối soát trở thành vô dụng đúng ở ca nó sinh ra để bắt.
 *
 * Nên: mỗi lượt Lưu ghi THÊM một dòng vào đây. Bảng `khh_dt_kho` vẫn giữ (nó là bản cộng dồn
 * cho nhanh, đúng vai "Bin" của ERPNext), nhưng SỔ NÀY MỚI LÀ SỰ THẬT — và
 * `khh_dt_kho_dung_lai()` dựng lại được bảng kia từ sổ này bất cứ lúc nào.
 */
function khh_dt_bang_kho_su() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_kho_su';
}

function khh_dt_tao_bang_kho_su() {
	global $wpdb;
	$bang    = khh_dt_bang_kho_su();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	/* ⚠️ CỐ Ý KHÔNG CÓ UNIQUE KEY. Thêm vào là quay lại đúng chuyện ghi đè mà bảng này sinh ra
	   để chấm dứt. Nhiều dòng cùng (ngày, cơ sở, mặt hàng) là ĐÚNG — đó là lịch sử sửa. */
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ngay date NOT NULL,
			co_so varchar(190) NOT NULL DEFAULT '',
			mat_hang varchar(190) NOT NULL DEFAULT '',
			nhap double NOT NULL DEFAULT 0,
			ban_khai double NULL DEFAULT NULL,
			combo_tay double NOT NULL DEFAULT 0,
			dem double NULL DEFAULT NULL,
			dat_dau double NULL DEFAULT NULL,
			huy double NOT NULL DEFAULT 0,
			ghi_chu varchar(190) NOT NULL DEFAULT '',
			nguoi varchar(120) NOT NULL DEFAULT '',
			luc datetime NULL,
			PRIMARY KEY  (id),
			KEY mot_dong (ngay,co_so(60),mat_hang(60)),
			KEY luc (luc)
		) $charset;"
	);
}

/** Mọi lượt khai của một dòng, MỚI NHẤT TRƯỚC. */
function khh_dt_kho_su_cua( $ngay, $co_so, $mat_hang ) {
	global $wpdb;
	$bang = khh_dt_bang_kho_su();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $bang WHERE ngay = %s AND co_so = %s AND mat_hang = %s ORDER BY id DESC",
			$ngay,
			$co_so,
			$mat_hang
		),
		ARRAY_A
	);
}

/** Mỗi mặt hàng của một ngày đã bị khai mấy lượt — để màn hiện "đã sửa N lần". */
function khh_dt_kho_so_lan( $ngay, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang_kho_su();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT mat_hang, COUNT(*) n FROM $bang WHERE ngay = %s AND co_so = %s GROUP BY mat_hang",
			$ngay,
			$co_so
		),
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$ra[ $r['mat_hang'] ] = (int) $r['n'];
	}
	return $ra;
}

/**
 * Dựng lại bảng cộng dồn từ sổ ghi động.
 *
 * Đây là điều làm cho "hai bảng" không thành hai sự thật: sổ ghi động là gốc, bảng kia chỉ là
 * bản tính sẵn và dựng lại được. Y lối Bin của ERPNext. Bài thử dùng hàm này để chốt hai bảng
 * luôn nói cùng một chuyện.
 */
function khh_dt_kho_dung_lai( $ngay, $co_so ) {
	global $wpdb;
	$su = khh_dt_bang_kho_su();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $su ) ) ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $su WHERE ngay = %s AND co_so = %s ORDER BY id ASC",
			$ngay,
			$co_so
		),
		ARRAY_A
	);
	$cuoi = array();
	foreach ( $ds as $r ) {
		$cuoi[ $r['mat_hang'] ] = $r;   // dòng sau ghi đè dòng trước -> còn lại là lượt mới nhất
	}
	$n = 0;
	foreach ( $cuoi as $mh => $r ) {
		$n += khh_dt_kho_ghi_cong_don( $ngay, $co_so, $mh, $r ) ? 1 : 0;
	}
	return $n;
}

/* ================================================================== *
 * Mặt hàng KHO của từng cơ sở
 * ================================================================== */

/**
 * Cơ sở này coi những món nào là HÀNG HOÁ CÓ KHO.
 *
 * 20/09/2026 anh Thắng, sau khi mở thử trên điện thoại: *"Phân loại theo cơ sở đang có hàng
 * của mình nhé"*. Đúng — FABi bán cả BẠC XỈU, CACAO LATTE, COMBO TRÀ CHANH GIÃ TAY… là đồ pha
 * tại chỗ, không có kho để đếm. Đổ hết vào sổ kho thì nhân viên phải cuộn qua vài chục dòng vô
 * nghĩa mới tới chai nước cần đếm, và mấy dòng ấy mãi mãi đỏ vì chẳng ai đếm chúng bao giờ.
 * Sổ đỏ vì lý do vớ vẩn thì người ta thôi nhìn cột lệch — mất luôn tác dụng của cả sổ.
 *
 * @return array Danh sách mặt hàng. RỖNG nghĩa là chưa chọn, KHÔNG phải "không có gì".
 */
function khh_dt_kho_mh_cua( $co_so ) {
	$b = get_option( 'khh_dt_kho_mat_hang', array() );
	$b = is_array( $b ) ? $b : array();
	$d = isset( $b[ $co_so ] ) ? $b[ $co_so ] : array();
	return is_array( $d ) ? array_values( array_unique( array_map( 'strval', $d ) ) ) : array();
}

function khh_dt_kho_mh_dat( $co_so, $ds ) {
	$b = get_option( 'khh_dt_kho_mat_hang', array() );
	$b = is_array( $b ) ? $b : array();
	$sach = array();
	foreach ( (array) $ds as $x ) {
		$x = trim( (string) $x );
		if ( '' !== $x && ! in_array( $x, $sach, true ) ) {
			$sach[] = $x;
		}
	}
	$b[ (string) $co_so ] = $sach;
	update_option( 'khh_dt_kho_mat_hang', $b, false );
	return count( $sach );
}

/* ---- MÃ HÀNG FABi cho từng món trong danh mục, và BÍ DANH theo mã ----
   Anh Thắng 24/09/2026: *"muốn bổ sung thêm sản phẩm mới (lấy tên sản phẩm mà mã theo FABi), để sau
   đồng bộ nó chạy cùng"*. Hàng mới về chưa bán thì FABi chưa có dòng nào -> không thể tích từ danh sách
   "món đã thấy". Cho thêm thẳng vào danh mục bằng tên + mã hàng FABi; về sau báo cáo FABi có dòng
   mang ĐÚNG MÃ ấy (dù tên gõ lệch hoa thường, thừa chữ) thì số bán vẫn rơi vào đúng dòng kho này. */
function khh_dt_kho_ma_cua( $co_so ) {
	$b = get_option( 'khh_dt_kho_ma', array() );
	$b = is_array( $b ) ? $b : array();
	$d = isset( $b[ (string) $co_so ] ) && is_array( $b[ (string) $co_so ] ) ? $b[ (string) $co_so ] : array();
	$ra = array();
	foreach ( $d as $ten => $ma ) {
		$ten = trim( (string) $ten );
		$ma  = trim( (string) $ma );
		if ( '' !== $ten && '' !== $ma ) {
			$ra[ $ten ] = $ma;
		}
	}
	return $ra;
}

function khh_dt_kho_ma_dat( $co_so, $ten, $ma ) {
	$b   = get_option( 'khh_dt_kho_ma', array() );
	$b   = is_array( $b ) ? $b : array();
	$cs  = (string) $co_so;
	$ten = trim( (string) $ten );
	$ma  = trim( (string) $ma );
	if ( ! isset( $b[ $cs ] ) || ! is_array( $b[ $cs ] ) ) {
		$b[ $cs ] = array();
	}
	if ( '' === $ma ) {
		unset( $b[ $cs ][ $ten ] );
	} else {
		$b[ $cs ][ $ten ] = $ma;
	}
	update_option( 'khh_dt_kho_ma', $b, false );
	return khh_dt_kho_ma_cua( $co_so );
}

/** Thêm một mặt hàng MỚI vào danh mục (chưa cần FABi từng bán). Trả về [ ok, error? ]. */
function khh_dt_kho_them_mh( $co_so, $ten, $ma = '' ) {
	$ten = trim( (string) $ten );
	$ma  = trim( (string) $ma );
	if ( '' === $ten ) {
		return array( 'ok' => false, 'error' => 'Thiếu tên mặt hàng (gõ đúng như FABi sẽ ghi).' );
	}
	$ds = khh_dt_kho_mh_cua( $co_so );
	if ( in_array( $ten, $ds, true ) ) {
		if ( '' !== $ma ) {
			khh_dt_kho_ma_dat( $co_so, $ten, $ma );
		}
		return array( 'ok' => true, 'da_co' => true );
	}
	/* Mã đã gán cho món khác thì chối — một mã hai tên là hai dòng kho tranh nhau một số bán. */
	if ( '' !== $ma ) {
		foreach ( khh_dt_kho_ma_cua( $co_so ) as $t2 => $m2 ) {
			if ( $m2 === $ma && $t2 !== $ten ) {
				return array( 'ok' => false, 'error' => 'Mã ' . $ma . ' đã gán cho "' . $t2 . '".' );
			}
		}
	}
	$ds[] = $ten;
	khh_dt_kho_mh_dat( $co_so, $ds );
	if ( '' !== $ma ) {
		khh_dt_kho_ma_dat( $co_so, $ten, $ma );
	}
	return array( 'ok' => true, 'da_co' => false );
}

/** Xoá một mặt hàng thêm tay khỏi danh mục và bỏ mã đã gán (anh Thắng 24/09/2026: "cho admin xoá món nếu sai"). */
function khh_dt_kho_xoa_mh( $co_so, $ten ) {
	$ten = trim( (string) $ten );
	if ( '' === $ten ) {
		return array( 'ok' => false, 'error' => 'Thiếu tên mặt hàng.' );
	}
	$ds = array_values( array_filter( khh_dt_kho_mh_cua( $co_so ), function ( $x ) use ( $ten ) { return $x !== $ten; } ) );
	khh_dt_kho_mh_dat( $co_so, $ds );
	khh_dt_kho_ma_dat( $co_so, $ten, '' );
	return array( 'ok' => true );
}

/** [ mã hàng => tên trong danh mục ] — để đổi tên món FABi về tên kho khi mã khớp. */
function khh_dt_kho_bi_danh( $co_so ) {
	$ra = array();
	foreach ( khh_dt_kho_ma_cua( $co_so ) as $ten => $ma ) {
		$ra[ $ma ] = $ten;
	}
	return $ra;
}

/** Tên kho của một dòng món FABi: trùng mã với danh mục thì lấy tên danh mục, không thì tên FABi. */
function khh_dt_kho_ten_chuan( $m, $bi_danh ) {
	$ten = isset( $m['n'] ) ? (string) $m['n'] : '';
	$ma  = isset( $m['m'] ) ? trim( (string) $m['m'] ) : '';
	if ( '' !== $ma && isset( $bi_danh[ $ma ] ) ) {
		return (string) $bi_danh[ $ma ];
	}
	return $ten;
}

/** Mọi món FABi từng ghi ở cơ sở này — để bày ra cho người chọn. */
function khh_dt_kho_mon_da_thay( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$ra = array();
	$bd = khh_dt_kho_bi_danh( $co_so );
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		foreach ( is_array( $mon ) ? $mon : array() as $m ) {
			$ten = khh_dt_kho_ten_chuan( $m, $bd );
			if ( '' === $ten ) {
				continue;
			}
			$ra[ $ten ] = ( isset( $ra[ $ten ] ) ? $ra[ $ten ] : 0 ) + ( isset( $m['q'] ) ? (float) $m['q'] : 0 );
		}
	}
	ksort( $ra );
	return $ra;
}

/* ================================================================== *
 * Thành phần combo
 * ================================================================== */

/**
 * CÔNG THỨC COMBO CÓ NGÀY HIỆU LỰC — sửa hôm nay KHÔNG viết lại quá khứ.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BẢN TRƯỚC ÁP CÔNG THỨC HIỆN TẠI CHO MỌI NGÀY CŨ, VÀ ĐÓ LÀ LỖI.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * `khh_dt_kho_ban_may()` đọc bảng combo rồi áp cho từng dòng lịch sử. Nên sửa một công thức
 * hôm nay là SỐ TỒN CỦA CẢ MẤY THÁNG TRƯỚC ĐỔI THEO, im lặng: hôm qua sổ cân, hôm nay mở lại
 * cùng ngày ấy thì lệch — mà không có gì trên màn nói vì sao. Trên một sổ dùng để đối chất với
 * người trực thì đó là thứ không được phép tồn tại.
 *
 * ERPNext giải bài này bằng BOM có phiên bản và ngày hiệu lực. Ở đây làm đúng lối ấy: mỗi lượt
 * sửa ghi THÊM một bản chụp cả bảng, kèm `tu_ngay`. Đọc ngày nào thì lấy bản có hiệu lực vào
 * ngày ấy.
 *
 * ⚠️ `tu_ngay` để người đặt, mặc định là HÔM NAY. Đặt lùi là cố ý sửa lại quá khứ — có lúc
 *    đúng (khai muộn một combo đã bán từ đầu tháng), nên cho phép, nhưng phải do người gõ
 *    chứ không mặc định.
 */
function khh_dt_kho_combo_ls() {
	$ls = get_option( 'khh_dt_kho_combo_ls', array() );
	return is_array( $ls ) ? $ls : array();
}

/**
 * Bảng công thức có hiệu lực vào một ngày.
 *
 * 🔴 GHI HIỆU LỰC THEO TỪNG COMBO, KHÔNG CHỤP CẢ BẢNG.
 *    Bản đầu lưu mỗi lượt sửa thành một BẢN CHỤP CẢ BẢNG kèm `tu_ngay`, rồi đọc ngày nào thì
 *    lấy bản chụp mới nhất còn hiệu lực. Chính bài thử bắt được chỗ hỏng: một lượt ĐẶT LÙI
 *    NGÀY sinh ra bản chụp của trạng thái LÚC ẤY — tức thiếu mọi công thức khai sau nó — mà
 *    bản chụp ấy lại không lan về sau, nên đọc ngày hôm nay là mất công thức vừa đặt lùi.
 *    Ghi theo từng combo thì tự khỏi: mỗi combo có dòng hiệu lực riêng, ghép lại theo ngày.
 *    Đây cũng đúng lối BOM của ERPNext — hiệu lực gắn với từng công thức, không gắn với cả sổ.
 *
 * @param string $ngay Rỗng = bản mới nhất của mỗi combo (dùng cho màn cấu hình).
 */
function khh_dt_kho_combo_bang( $ngay = '' ) {
	$ls = khh_dt_kho_combo_ls();
	if ( ! $ls ) {
		/* Chưa có lịch sử: đọc ô cũ, để bản cài trước không mất công thức đã khai. */
		$b = get_option( 'khh_dt_kho_combo', array() );
		return is_array( $b ) ? $b : array();
	}
	$chon = array();   // tên combo => dòng hiệu lực đang thắng
	foreach ( $ls as $d ) {
		$ten = isset( $d['ten'] ) ? (string) $d['ten'] : '';
		$tu  = isset( $d['tu_ngay'] ) ? (string) $d['tu_ngay'] : '';
		if ( '' === $ten ) {
			continue;
		}
		if ( '' !== $ngay && $tu > $ngay ) {
			continue;   // chưa có hiệu lực vào ngày đang xét
		}
		if ( ! isset( $chon[ $ten ] ) ) {
			$chon[ $ten ] = $d;
			continue;
		}
		$cu = $chon[ $ten ];
		$x  = strcmp( $tu, (string) $cu['tu_ngay'] );
		/* Ngày hiệu lực muộn hơn thì thắng; cùng ngày thì lượt ghi sau thắng. */
		if ( $x > 0 || ( 0 === $x && strcmp( (string) $d['luc'], (string) $cu['luc'] ) >= 0 ) ) {
			$chon[ $ten ] = $d;
		}
	}
	$ra = array();
	foreach ( $chon as $ten => $d ) {
		$tp = isset( $d['tp'] ) && is_array( $d['tp'] ) ? $d['tp'] : array();
		if ( $tp ) {
			$ra[ $ten ] = $tp;   // thành phần rỗng = đã xoá combo ấy từ ngày ấy
		}
	}
	ksort( $ra );
	return $ra;
}

/**
 * Đặt công thức cho một combo. GHI THÊM một dòng hiệu lực, không sửa dòng cũ.
 *
 * @param string $tu_ngay Ngày bắt đầu có hiệu lực. Rỗng = hôm nay.
 *
 * ⚠️ `tu_ngay` để người đặt, mặc định HÔM NAY. Đặt lùi là cố ý sửa lại quá khứ — có lúc đúng
 *    (khai muộn một combo đã bán từ đầu tháng), nên cho phép, nhưng phải do người gõ chứ không
 *    mặc định. Mặc định lùi là mọi lượt khai đều lặng lẽ viết lại số cũ.
 */
function khh_dt_kho_combo_dat( $ten_combo, $thanh_phan, $tu_ngay = '' ) {
	$ten = trim( (string) $ten_combo );
	if ( '' === $ten ) {
		return false;
	}
	$tu = preg_match( '~^\d{4}-\d{2}-\d{2}$~', (string) $tu_ngay )
		? (string) $tu_ngay
		: current_time( 'Y-m-d' );

	$tp = array();
	foreach ( (array) $thanh_phan as $mh => $sl ) {
		$mh = trim( (string) $mh );
		$sl = (float) $sl;
		if ( '' !== $mh && $sl > 0 ) {
			$tp[ $mh ] = $sl;
		}
	}

	$ls   = khh_dt_kho_combo_ls();
	$ls[] = array(
		'ten'     => $ten,
		'tu_ngay' => $tu,
		'tp'      => $tp,   // rỗng = xoá combo ấy từ `tu_ngay` trở đi
		'nguoi'   => function_exists( 'khh_dt_ten_ghi_so' ) ? khh_dt_ten_ghi_so() : '',
		'luc'     => current_time( 'mysql' ),
	);
	usort(
		$ls,
		function ( $a, $b ) {
			$x = strcmp( (string) $a['tu_ngay'], (string) $b['tu_ngay'] );
			return 0 !== $x ? $x : strcmp( (string) $a['luc'], (string) $b['luc'] );
		}
	);
	update_option( 'khh_dt_kho_combo_ls', array_slice( $ls, -300 ), false );
	return true;
}

/* ================================================================== *
 * Số máy POS ghi — lấy từ chính kho số FABi đã nạp
 * ================================================================== */

/**
 * SL THỰC cơ sở đã chốt ở tab Nhập báo cáo (bảng `mon_thuc` của báo cáo ngày) — [ ngày => [ tên món =>
 * số lượng thực ] ]. Anh Thắng 24/09/2026: *"vì hàng bán lệch đã nhập sẵn bên này rồi"* — nhân viên soát
 * hàng bán so với máy ở tab Nhập báo cáo, lệch mới gõ; sổ kho lấy đúng con số đã chốt ấy thay số máy,
 * không bắt khai lần hai. Không có bảng / không có dòng -> rỗng -> theo máy.
 */
function khh_dt_kho_ban_thuc( $tu, $den, $co_so ) {
	global $wpdb;
	if ( ! function_exists( 'khh_dt_bang_bc' ) ) {
		return array();
	}
	$bc = khh_dt_bang_bc();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bc ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT ngay, mon_thuc FROM $bc WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s", $tu, $den, $co_so ),
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$m = json_decode( (string) ( isset( $r['mon_thuc'] ) ? $r['mon_thuc'] : '' ), true );
		if ( ! is_array( $m ) ) {
			continue;
		}
		foreach ( $m as $ten => $sl ) {
			$ten = trim( (string) $ten );
			if ( '' !== $ten && is_numeric( $sl ) && (float) $sl >= 0 ) {
				$ra[ (string) $r['ngay'] ][ $ten ] = (float) $sl;
			}
		}
	}
	return $ra;
}

/**
 * Số lượng bán theo máy, cho một cơ sở, trong một khoảng.
 *
 * @return array [ ngày => [ mặt hàng => số lượng ] ]  — đã CỘNG cả phần đi theo combo, và đã lấy SL
 *               thực cơ sở chốt (xem `khh_dt_kho_ban_thuc`) thay số máy ở món có chốt.
 */
function khh_dt_kho_ban_may( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ngay, mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$ra    = array();
	$nho   = array();   // công thức theo ngày, nhớ lại để khỏi dựng lại mỗi dòng
	$thuc  = khh_dt_kho_ban_thuc( $tu, $den, $co_so );
	$bd    = khh_dt_kho_bi_danh( $co_so );
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		if ( ! is_array( $mon ) ) {
			continue;
		}
		$ngay = (string) $r['ngay'];
		/* 🔴 CÔNG THỨC CỦA ĐÚNG NGÀY ẤY, không phải công thức hiện tại. Lấy bảng hiện tại là
		   sửa combo hôm nay thì số tồn mấy tháng trước đổi theo, im lặng. */
		if ( ! isset( $nho[ $ngay ] ) ) {
			$nho[ $ngay ] = khh_dt_kho_combo_bang( $ngay );
		}
		$combo = $nho[ $ngay ];
		foreach ( $mon as $m ) {
			$ten = khh_dt_kho_ten_chuan( $m, $bd );
			$sl  = isset( $m['q'] ) ? (float) $m['q'] : 0;
			if ( '' === $ten ) {
				continue;
			}
			/* Cơ sở đã chốt SL thực khác máy ở tab Nhập báo cáo -> lấy số chốt. */
			if ( isset( $thuc[ $ngay ][ trim( $ten ) ] ) ) {
				$sl = $thuc[ $ngay ][ trim( $ten ) ];
			}
			if ( isset( $combo[ $ten ] ) ) {
				/* Món này là một combo: KHÔNG trừ kho theo tên combo (kho không có "combo"),
				   mà trừ từng thành phần. Một combo bán ra là ngần ấy chai nước rời kho. */
				foreach ( $combo[ $ten ] as $mh => $moi_cai ) {
					$ra[ $ngay ][ $mh ] = ( isset( $ra[ $ngay ][ $mh ] ) ? $ra[ $ngay ][ $mh ] : 0 )
						+ $sl * (float) $moi_cai;
				}
				continue;
			}
			$ra[ $ngay ][ $ten ] = ( isset( $ra[ $ngay ][ $ten ] ) ? $ra[ $ngay ][ $ten ] : 0 ) + $sl;
		}
	}
	return $ra;
}

/** Số lượng bán theo máy, TÁCH RIÊNG phần đi thẳng và phần đi theo combo — để bày ra hai cột. */
function khh_dt_kho_ban_may_tach( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ngay, mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$ra    = array();
	$nho   = array();
	$thuc = khh_dt_kho_ban_thuc( $tu, $den, $co_so );
	$bd   = khh_dt_kho_bi_danh( $co_so );
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		if ( ! is_array( $mon ) ) {
			continue;
		}
		$ngay = (string) $r['ngay'];
		/* Công thức của đúng ngày ấy — xem chú thích ở `khh_dt_kho_ban_may()`. */
		if ( ! isset( $nho[ $ngay ] ) ) {
			$nho[ $ngay ] = khh_dt_kho_combo_bang( $ngay );
		}
		$combo = $nho[ $ngay ];
		foreach ( $mon as $m ) {
			$ten = khh_dt_kho_ten_chuan( $m, $bd );
			$sl  = isset( $m['q'] ) ? (float) $m['q'] : 0;
			if ( '' === $ten ) {
				continue;
			}
			if ( isset( $thuc[ $ngay ][ trim( $ten ) ] ) ) {
				$sl = $thuc[ $ngay ][ trim( $ten ) ];
			}
			if ( isset( $combo[ $ten ] ) ) {
				foreach ( $combo[ $ten ] as $mh => $moi_cai ) {
					if ( ! isset( $ra[ $ngay ][ $mh ] ) ) {
						$ra[ $ngay ][ $mh ] = array( 'le' => 0, 'combo' => 0 );
					}
					$ra[ $ngay ][ $mh ]['combo'] += $sl * (float) $moi_cai;
				}
				continue;
			}
			if ( ! isset( $ra[ $ngay ][ $ten ] ) ) {
				$ra[ $ngay ][ $ten ] = array( 'le' => 0, 'combo' => 0 );
			}
			$ra[ $ngay ][ $ten ]['le'] += $sl;
		}
	}
	return $ra;
}

/* ================================================================== *
 * Dòng nhân viên đã khai
 * ================================================================== */

function khh_dt_kho_dong( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang_kho();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $bang WHERE ngay >= %s AND ngay <= %s AND co_so = %s ORDER BY ngay, mat_hang",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$ra[ $r['ngay'] ][ $r['mat_hang'] ] = $r;
	}
	return $ra;
}

/**
 * Một số có thể null, viết THẲNG vào câu SQL: `NULL` hoặc số. 🔴 KHÔNG đưa null qua `%s` của
 * `$wpdb->prepare()` — WordPress đổi nó thành '' và MySQL ép '' vào cột double thành 0: "chưa đếm"
 * biến thành "đếm được 0", lệch kho đỏ cả cột (anh Thắng 24/09/2026). Số thì in bằng %F cho khỏi dính
 * dấu phẩy thập phân theo locale.
 */
function khh_dt_kho_sql_so( $v ) {
	return null === $v ? 'NULL' : sprintf( '%F', (float) $v );
}

/** Đọc một số người ta gõ. `null` = CHƯA KHAI, khác hẳn số 0 — 0 là "đếm được 0 cái". */
function khh_dt_kho_so( $x ) {
	if ( null === $x || '' === $x ) {
		return null;
	}
	return function_exists( 'khh_dt_so' ) ? khh_dt_so( $x ) : (float) $x;
}

/**
 * Sửa MỘT LẦN vết lỗi trước 1.61.3: ô "SL Hàng Bán" và "Hàng tồn còn" bỏ TRỐNG mà bị ghi thành 0
 * (WordPress đổi null → '' rồi MySQL ép '' vào cột double thành 0). Hậu quả anh Thắng thấy 24/09/2026
 * ở Lotte Gò Vấp: 23/09 đặt tồn đầu 148, 48, 15… mà "qua ngày 24 tồn đầu không nhảy" — cả cột 0. Vì
 * `dem = 0` là một MỐC ĐẾM ("đếm được 0 cái"), tồn cuối 23/09 thành 0 và kéo sang 24/09; riêng THẠCH
 * TRÁI CÂY thêm sau khi sửa lỗi (dem NULL) nên kéo đúng 506. Mở lại màn 23/09 thì ô hiện "0", bấm Lưu
 * lại 5 lần vẫn giữ 0 — người dùng không có cách nào tự thoát.
 *
 * Hai dấu vết, xét theo thứ tự:
 *   A. Dòng tổng có CẢ HAI ô đều 0 — bản cũ để trống hai ô là ra đúng cặp ấy.
 *   B. 🔴 Dòng tổng chỉ còn `dem = 0` (SL bán đã về NULL vì màn mới không gửi cột ấy nữa, mà ô Hàng
 *      tồn còn thì hiện sẵn "0" nên Lưu lại bao nhiêu lần vẫn là 0). Bản 1.63.1 chỉ xét A nên bỏ sót
 *      đúng sáu dòng Gò Vấp "đã sửa 5 lần" — ảnh anh Thắng gửi sau khi cài. Ở đây tra SỔ NHẬT KÝ: số 0
 *      ấy SINH RA từ một lượt ghi 0/0 (vết bản cũ) và từ đó tới nay CHƯA BAO GIỜ có lượt đếm ra số
 *      khác 0 -> là 0 giả, gỡ về trống. Từng có lượt đếm 3 rồi sau đếm 0 -> 0 thật, giữ.
 *
 * Chỉ chạy một lần (ghi option `khh_dt_kho_sua_0b`), để về sau ai cố ý gõ 0 thì 0 ấy đứng yên. Sổ
 * nhật ký giữ nguyên — nó là lịch sử.
 *
 * @return int Số dòng đã sửa (0 nếu đã chạy rồi hoặc chưa có bảng).
 */
function khh_dt_kho_sua_so_0() {
	global $wpdb;
	if ( get_option( 'khh_dt_kho_sua_0b', false ) ) {
		return 0;
	}
	$bang = khh_dt_bang_kho();
	$su   = khh_dt_bang_kho_su();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return 0;
	}
	/* A. */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$n = (int) $wpdb->query( "UPDATE $bang SET dem = NULL, ban_khai = NULL WHERE dem = 0 AND ban_khai = 0" );

	/* B. */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$co_su = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $su ) );
	if ( $co_su ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$con = (array) $wpdb->get_results( "SELECT id, ngay, co_so, mat_hang FROM $bang WHERE dem = 0", ARRAY_A );
		foreach ( $con as $r ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$luot = (array) $wpdb->get_results(
				$wpdb->prepare( "SELECT dem, ban_khai FROM $su WHERE ngay = %s AND co_so = %s AND mat_hang = %s ORDER BY id ASC", $r['ngay'], $r['co_so'], $r['mat_hang'] ),
				ARRAY_A
			);
			$vet_cu   = false;   // có lượt ghi 0/0 kiểu bản cũ
			$dem_that = false;   // có lượt đếm ra số khác 0
			foreach ( $luot as $l ) {
				$d = ( null === $l['dem'] || '' === $l['dem'] ) ? null : (float) $l['dem'];
				$k = ( null === $l['ban_khai'] || '' === $l['ban_khai'] ) ? null : (float) $l['ban_khai'];
				if ( 0.0 === $d && 0.0 === $k ) {
					$vet_cu = true;
				}
				if ( null !== $d && 0.0 !== $d ) {
					$dem_that = true;
				}
			}
			if ( $vet_cu && ! $dem_that ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update( $bang, array( 'dem' => null ), array( 'id' => (int) $r['id'] ), array( '%s' ), array( '%d' ) );
				$n++;
			}
		}
	}
	update_option( 'khh_dt_kho_sua_0b', gmdate( 'Y-m-d H:i:s' ), false );
	delete_option( 'khh_dt_kho_sua_0' );
	return $n;
}

/**
 * Ghi một lượt khai.
 *
 * 🔴 GHI VÀO SỔ GHI ĐỘNG TRƯỚC, RỒI MỚI CỘNG DỒN. Thứ tự ấy quan trọng: nếu cộng dồn xong mới
 *    ghi sổ mà bước sau lỗi, thì màn hình đã đổi số trong khi sổ không có dòng nào — tức mất
 *    đúng cái vết mà cả sổ này sinh ra để giữ. Ngược lại thì chỉ là bản cộng dồn chậm một nhịp,
 *    và `khh_dt_kho_dung_lai()` dựng lại được.
 */
function khh_dt_kho_ghi( $ngay, $co_so, $mat_hang, $o ) {
	global $wpdb;
	$dong = array(
		'nhap'      => khh_dt_kho_so( isset( $o['nhap'] ) ? $o['nhap'] : null ),
		'ban_khai'  => khh_dt_kho_so( isset( $o['ban_khai'] ) ? $o['ban_khai'] : null ),
		'combo_tay' => khh_dt_kho_so( isset( $o['combo_tay'] ) ? $o['combo_tay'] : null ),
		'dem'       => khh_dt_kho_so( isset( $o['dem'] ) ? $o['dem'] : null ),
		/* ĐẶT LẠI TỒN ĐẦU (anh Thắng 24/09/2026: "cho set lại tồn đầu"): một bút toán mốc — ngày này
		   lấy đúng số ấy làm tồn đầu, bỏ số kéo từ hôm trước (kể cả số âm vô nghĩa). null = không đặt. */
		'dat_dau'   => khh_dt_kho_so( isset( $o['dat_dau'] ) ? $o['dat_dau'] : null ),
		/* HÀNG HUỶ (anh Thắng 24/09/2026: "cột này ghi là hàng huỷ, nếu huỷ nhập vào nó trừ ra"): hàng
		   hỏng, đổ, vỡ bỏ đi — rời kho mà không qua máy POS. Trừ khỏi tồn tính. */
		'huy'       => khh_dt_kho_so( isset( $o['huy'] ) ? $o['huy'] : null ),
		'ghi_chu'   => isset( $o['ghi_chu'] ) ? (string) $o['ghi_chu'] : '',
		'nguoi'     => function_exists( 'khh_dt_ten_ghi_so' ) ? khh_dt_ten_ghi_so() : '',
		'luc'       => current_time( 'mysql' ),
	);

	$su = khh_dt_bang_kho_su();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $su ) ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $su (ngay,co_so,mat_hang,nhap,ban_khai,combo_tay,dem,dat_dau,huy,ghi_chu,nguoi,luc)
				 VALUES (%s,%s,%s,%f," . khh_dt_kho_sql_so( $dong['ban_khai'] ) . ",%f," . khh_dt_kho_sql_so( $dong['dem'] ) . ','
				 . khh_dt_kho_sql_so( $dong['dat_dau'] ) . ',%f,%s,%s,%s)',
				$ngay,
				$co_so,
				$mat_hang,
				null === $dong['nhap'] ? 0 : $dong['nhap'],
				null === $dong['combo_tay'] ? 0 : $dong['combo_tay'],
				null === $dong['huy'] ? 0 : $dong['huy'],
				$dong['ghi_chu'],
				$dong['nguoi'],
				$dong['luc']
			)
		);
	}
	return khh_dt_kho_ghi_cong_don( $ngay, $co_so, $mat_hang, $dong );
}

/** Bản cộng dồn cho nhanh — đúng vai "Bin" của ERPNext. Dựng lại được từ sổ ghi động. */
function khh_dt_kho_ghi_cong_don( $ngay, $co_so, $mat_hang, $o ) {
	global $wpdb;
	$bang  = khh_dt_bang_kho();
	$nhap  = khh_dt_kho_so( isset( $o['nhap'] ) ? $o['nhap'] : null );
	$combo = khh_dt_kho_so( isset( $o['combo_tay'] ) ? $o['combo_tay'] : null );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return false !== $wpdb->query(
		$wpdb->prepare(
			"INSERT INTO $bang (ngay,co_so,mat_hang,nhap,ban_khai,combo_tay,dem,dat_dau,huy,ghi_chu,nguoi,luc)
			 VALUES (%s,%s,%s,%f," . khh_dt_kho_sql_so( khh_dt_kho_so( isset( $o['ban_khai'] ) ? $o['ban_khai'] : null ) ) . ',%f,'
			 . khh_dt_kho_sql_so( khh_dt_kho_so( isset( $o['dem'] ) ? $o['dem'] : null ) ) . ','
			 . khh_dt_kho_sql_so( khh_dt_kho_so( isset( $o['dat_dau'] ) ? $o['dat_dau'] : null ) ) . ",%f,%s,%s,%s)
			 ON DUPLICATE KEY UPDATE nhap=VALUES(nhap), ban_khai=VALUES(ban_khai),
			   combo_tay=VALUES(combo_tay), dem=VALUES(dem), dat_dau=VALUES(dat_dau), huy=VALUES(huy), ghi_chu=VALUES(ghi_chu),
			   nguoi=VALUES(nguoi), luc=VALUES(luc)",
			$ngay,
			$co_so,
			$mat_hang,
			null === $nhap ? 0 : $nhap,
			null === $combo ? 0 : $combo,
			null === ( $h = khh_dt_kho_so( isset( $o['huy'] ) ? $o['huy'] : null ) ) ? 0 : $h,
			isset( $o['ghi_chu'] ) ? (string) $o['ghi_chu'] : '',
			isset( $o['nguoi'] ) ? (string) $o['nguoi'] : '',
			isset( $o['luc'] ) ? (string) $o['luc'] : current_time( 'mysql' )
		)
	);
}

/* ================================================================== *
 * Dựng lại tồn đầu, và ghép bảng đối chiếu
 * ================================================================== */

/**
 * Trạng thái kho ở CUỐI ngày `$den_ngay`, dựng lại bằng cách chạy lại từng ngày.
 *
 * 🔴 MỖI LẦN ĐẾM TAY LÀ MỘT MỐC MỚI. Gặp `dem` là lấy luôn số ấy làm tồn cuối, bỏ số tính ra.
 *    Không làm thế thì một ngày lệch sẽ theo mãi về sau: mọi ngày sau đều đỏ vì một lỗi đã xử
 *    lý xong từ lâu, và người ta sẽ thôi nhìn cột lệch.
 *
 * @return array [ mặt hàng => [ 'ton' => số, 'co_moc' => đã từng được đếm tay chưa ] ]
 */
/** Những ngày trong khoảng đã có dòng báo cáo FABi của cơ sở này — [ ngày => true ]. */
function khh_dt_kho_ngay_co_fabi( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_col(
		$wpdb->prepare( "SELECT DISTINCT ngay FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s", $tu, $den, $co_so )
	);
	return array_fill_keys( array_map( 'strval', $ds ), true );
}

/**
 * LÕI CHẠY CHUỖI TỒN — dùng chung cho màn ngày (`khh_dt_kho_trang_thai`) và thẻ kho
 * (`khh_dt_kho_the`). Một luật, một chỗ: viết bản thứ hai cho thẻ kho là sớm muộn hai màn ra
 * hai con số khác nhau cho cùng một ngày, mà không màn nào sai để lần ra.
 *
 * Chạy từng ngày từ `$tu` tới `$den`, ghi lại TỪNG NGÀY của TỪNG mặt hàng (thẻ kho cần) và
 * trạng thái cuối (màn ngày cần).
 *
 * @return array [ 'cuoi' => [ mh => ['ton','co_moc'] ],
 *                 'ngay' => [ mh => [ [ngay, ton_dau, nhap, ban_may, combo_tay, dem, ton_tinh,
 *                                       ton_cuoi, lech_kho, co_fabi], … ] ] ]
 */
function khh_dt_kho_chay( $co_so, $tu, $den ) {
	$khai    = khh_dt_kho_dong( $tu, $den, $co_so );
	$may     = khh_dt_kho_ban_may( $tu, $den, $co_so );
	$co_fabi = khh_dt_kho_ngay_co_fabi( $tu, $den, $co_so );

	$ngay_ds = array_unique( array_merge( array_keys( $khai ), array_keys( $may ) ) );
	sort( $ngay_ds );

	$tt   = array();
	$dong = array();
	foreach ( $ngay_ds as $ng ) {
		if ( $ng > $den ) {
			break;
		}
		$mh_ds = array_unique(
			array_merge(
				array_keys( isset( $khai[ $ng ] ) ? $khai[ $ng ] : array() ),
				array_keys( isset( $may[ $ng ] ) ? $may[ $ng ] : array() )
			)
		);
		foreach ( $mh_ds as $mh ) {
			if ( ! isset( $tt[ $mh ] ) ) {
				/* 🔴 `null` = CHƯA BIẾT TỒN, khác hẳn 0. Bắt đầu từ 0 là sai: hệ chưa hề biết
				   trên kệ có bao nhiêu, mà cứ trừ số bán ra thì ngày đầu đã ra tồn ÂM. Anh
				   Thắng mở thử trên điện thoại 20/09/2026 thấy đúng thế — "BIMBIM LỚN −61",
				   "−139", cả màn toàn số âm. Số âm ấy không sai một cách thú vị, nó chỉ vô
				   nghĩa: chưa ai đặt mốc thì không có gì để tính. */
				$tt[ $mh ] = array( 'ton' => null, 'co_moc' => false );
			}
			$d     = isset( $khai[ $ng ][ $mh ] ) ? $khai[ $ng ][ $mh ] : array();
			$nhap  = isset( $d['nhap'] ) ? (float) $d['nhap'] : 0.0;
			$ctay  = isset( $d['combo_tay'] ) ? (float) $d['combo_tay'] : 0.0;
			$huy   = isset( $d['huy'] ) ? (float) $d['huy'] : 0.0;
			$b_may = isset( $may[ $ng ][ $mh ] ) ? (float) $may[ $ng ][ $mh ] : 0.0;
			$dem   = ( isset( $d['dem'] ) && null !== $d['dem'] && '' !== $d['dem'] ) ? (float) $d['dem'] : null;
			$dat   = ( isset( $d['dat_dau'] ) && null !== $d['dat_dau'] && '' !== $d['dat_dau'] ) ? (float) $d['dat_dau'] : null;

			/* `$t_dau` là số BÁO RA (null = chưa biết); `$goc` là số ĐEM TÍNH. Tách đôi vì ngày
			   nhập đầu tiên: tồn đầu thật là "chưa biết", nhưng tính thì coi kho rỗng = 0. Gộp
			   hai thứ vào một biến là thẻ kho in 0 trong khi màn ngày in "—" cho cùng ngày ấy —
			   bài thử đã bắt đúng chỗ này. */
			$t_dau = $tt[ $mh ]['ton'];
			/* 🔴 ĐẶT LẠI TỒN ĐẦU thắng số kéo từ hôm trước — kể cả kéo ra số âm vô nghĩa (24/09/2026
			   anh Thắng mở Tân Phú thấy cả cột −2, −8, −34 vì hôm trước máy bán mà chưa ai đặt mốc).
			   Đặt là một mốc, như đếm tay: từ đây chuỗi tính lại từ số này. */
			if ( null !== $dat ) {
				$t_dau               = $dat;
				$tt[ $mh ]['co_moc'] = true;
			}
			$goc   = $t_dau;
			/* Lượt NHẬP đầu tiên cũng là một mốc: nhập vào kho rỗng thì tồn chính bằng số nhập. */
			if ( null === $goc && $nhap > 0 ) {
				$goc = 0.0;
			}
			/* Hàng huỷ rời kho như hàng bán, chỉ khác là không qua máy — trừ thẳng. */
			$tinh = ( null === $goc ) ? null : $goc + $nhap - $b_may - $ctay - $huy;

			/* Đếm tay thắng số tính — đây là chỗ cắt đứt cái lệch cũ, và cũng là cách đặt mốc
			   đầu tiên cho một mặt hàng chưa ai đếm bao giờ. */
			$cuoi = $tinh;
			if ( null !== $dem ) {
				$cuoi                = $dem;
				$tt[ $mh ]['co_moc'] = true;
			}
			$tt[ $mh ]['ton'] = $cuoi;

			$dong[ $mh ][] = array(
				'ngay'      => $ng,
				'ton_dau'   => $t_dau,
				'dat_dau'   => $dat,
				'nhap'      => $nhap,
				'ban_may'   => isset( $co_fabi[ $ng ] ) ? $b_may : null,
				'combo_tay' => $ctay,
				'huy'       => $huy,
				'dem'       => $dem,
				'ton_tinh'  => $tinh,
				'ton_cuoi'  => $cuoi,
				'lech_kho'  => ( null === $dem || null === $tinh ) ? null : $dem - $tinh,
				/* Ngày chưa nạp FABi: chuỗi vẫn phải chạy tiếp (coi bán = 0 để còn kéo tồn
				   sang ngày sau), nhưng phải NÓI RA — thẻ kho hiện "—" ở cột máy bán và đánh dấu
				   ngày ấy, để không ai đọc 0 thành "không bán". Nạp báo cáo xong tự tính lại. */
				'co_fabi'   => isset( $co_fabi[ $ng ] ),
			);
		}
	}
	return array( 'cuoi' => $tt, 'ngay' => $dong );
}

/**
 * Trạng thái kho ở CUỐI ngày `$den_ngay`, dựng lại bằng cách chạy lại từng ngày.
 *
 * 🔴 MỖI LẦN ĐẾM TAY LÀ MỘT MỐC MỚI. Gặp `dem` là lấy luôn số ấy làm tồn cuối, bỏ số tính ra.
 *    Không làm thế thì một ngày lệch sẽ theo mãi về sau: mọi ngày sau đều đỏ vì một lỗi đã xử
 *    lý xong từ lâu, và người ta sẽ thôi nhìn cột lệch.
 *
 * @return array [ mặt hàng => [ 'ton' => số, 'co_moc' => đã từng được đếm tay chưa ] ]
 */
function khh_dt_kho_trang_thai( $co_so, $den_ngay ) {
	$bd = gmdate( 'Y-m-d', strtotime( $den_ngay ) - KHH_DT_KHO_LUI * DAY_IN_SECONDS );
	return khh_dt_kho_chay( $co_so, $bd, $den_ngay )['cuoi'];
}

/**
 * THẺ KHO của một mặt hàng: từng ngày trong [$tu, $den] — tồn đầu, nhập, bán, đếm, tồn cuối.
 *
 * Anh Thắng 23/09/2026: *"Chưa thấy có chỗ thay đổi hàng hoá theo ngày, giống kiểu tồn kho ngày
 * đó bao nhiêu, bán bao nhiêu, tồn bao nhiêu"*. Màn ngày chỉ cho xem một ngày một lúc; muốn
 * thấy hàng chạy thì phải có thẻ kho — đúng khái niệm sổ chi tiết vật tư trong kế toán.
 *
 * Chạy chuỗi từ trước `$tu` 90 ngày để tồn đầu của ngày `$tu` đúng, rồi chỉ trả các ngày trong
 * khoảng. Ngày không có gì (không bán, không khai) thì không có dòng — thẻ kho chỉ ghi ngày có
 * biến động, y như sổ tay.
 */
function khh_dt_kho_the( $co_so, $mat_hang, $tu, $den ) {
	$bd = gmdate( 'Y-m-d', strtotime( $tu ) - KHH_DT_KHO_LUI * DAY_IN_SECONDS );
	$kq = khh_dt_kho_chay( $co_so, $bd, $den );
	$ds = isset( $kq['ngay'][ $mat_hang ] ) ? $kq['ngay'][ $mat_hang ] : array();
	return array_values(
		array_filter(
			$ds,
			function ( $r ) use ( $tu ) {
				return $r['ngay'] >= $tu;
			}
		)
	);
}

/**
 * Bảng đối chiếu kho của MỘT ngày — cũng chính là biểu mẫu nhân viên khai cuối ngày.
 *
 * 🔴 DANH SÁCH MẶT HÀNG TỰ SINH, KHÔNG GÕ TAY. Anh Thắng: *"Nếu sau fabi phát sinh món hệ
 *    thống tự tách thêm ô nhập"*. Nên danh sách là hợp của ba nguồn:
 *      · món FABi ghi bán hôm nay        -> món mới xuất hiện là có ô nhập ngay, khỏi khai báo;
 *      · mặt hàng còn tồn từ hôm trước   -> hôm nay không bán cái nào vẫn phải đếm được;
 *      · dòng nhân viên đã khai hôm nay  -> khai rồi thì không được biến mất.
 *    Thiếu nguồn thứ hai là món hết bán sẽ rơi khỏi sổ mang theo cả phần tồn của nó.
 */
/** Ngày này, cơ sở này đã có dòng báo cáo FABi nào chưa. */
function khh_dt_kho_co_fabi( $ngay, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (bool) $wpdb->get_var(
		$wpdb->prepare( "SELECT 1 FROM $bang WHERE ngay = %s AND cua_hang = %s LIMIT 1", $ngay, $co_so )
	);
}

function khh_dt_kho_bang_ngay( $ngay, $co_so ) {
	$hom_qua = gmdate( 'Y-m-d', strtotime( $ngay ) - DAY_IN_SECONDS );
	/* 🔴 CHƯA NẠP BÁO CÁO FABi CHO NGÀY NÀY THÌ "MÁY BÁN" LÀ CHƯA BIẾT, KHÔNG PHẢI 0.
	   Anh Thắng mở ngày 22/09 khi file FABi cuối nạp là 19/09: cả cột Máy bán in 0, trông y
	   như "hôm nay không bán cái nào" — mà thật ra là "chưa có số". Hai nghĩa ấy ngược nhau, và
	   người trực nhìn 0 sẽ đếm rồi thấy lệch kho bằng đúng số đã bán, rồi tưởng mất hàng. */
	$co_fabi = khh_dt_kho_co_fabi( $ngay, $co_so );
	$dau     = khh_dt_kho_trang_thai( $co_so, $hom_qua );
	$khai    = khh_dt_kho_dong( $ngay, $ngay, $co_so );
	$khai    = isset( $khai[ $ngay ] ) ? $khai[ $ngay ] : array();
	$tach    = khh_dt_kho_ban_may_tach( $ngay, $ngay, $co_so );
	$tach    = isset( $tach[ $ngay ] ) ? $tach[ $ngay ] : array();

	$mh_ds = array_keys( $tach );
	/* 🔴 TỪ HÔM QUA CHỈ KÉO SANG MẶT HÀNG CÒN TỒN ĐÃ BIẾT — không kéo cả thực đơn.
	   `khh_dt_kho_trang_thai()` trả về MỌI món từng thấy trong 90 ngày, kể cả món chưa ai đặt
	   mốc (`ton` = null) và món đã về 0. Bản trước kéo hết sang, nên một ngày chưa có báo cáo
	   FABi là cả thực đơn 25 món hiện ra với toàn số 0 và "—". Anh Thắng: *"Hàng hoá này chỉ
	   hiện có những sản phẩm đang bán tại cửa hàng thôi"*. Đúng: hôm nay có bán, hoặc trên kệ
	   còn hàng, hoặc đã có người khai — ba nguồn ấy thôi. Còn tồn âm thì vẫn kéo: đó là dấu
	   hiệu sai sổ, phải bày ra. */
	foreach ( $dau as $mh => $tt ) {
		if ( null === $tt['ton'] || 0.0 === (float) $tt['ton'] ) {
			continue;
		}
		if ( ! in_array( $mh, $mh_ds, true ) ) {
			$mh_ds[] = $mh;
		}
	}
	foreach ( array_keys( $khai ) as $mh ) {
		if ( ! in_array( $mh, $mh_ds, true ) ) {
			$mh_ds[] = $mh;
		}
	}

	/* 🔴 LỌC THEO DANH MỤC HÀNG HOÁ CỦA CƠ SỞ. FABi bán cả đồ pha tại chỗ (BẠC XỈU, CACAO
	   LATTE, COMBO TRÀ CHANH…) — không có kho để đếm. Đổ hết vào sổ thì nhân viên cuộn qua vài
	   chục dòng vô nghĩa mới tới chai nước, và mấy dòng ấy mãi mãi đỏ vì chẳng ai đếm chúng.
	   ⚠️ NHƯNG DÒNG ĐÃ KHAI THÌ KHÔNG ĐƯỢC GIẤU, kể cả khi mặt hàng bị bỏ khỏi danh mục: giấu
	      đi là số người ta đã gõ biến mất khỏi màn mà vẫn nằm trong sổ. Bỏ nhầm một mặt hàng
	      rồi không hiểu vì sao tồn không khớp là chuyện không ai lần ra. */
	$chon = khh_dt_kho_mh_cua( $co_so );
	if ( $chon ) {
		$mh_ds = array_values(
			array_filter(
				$mh_ds,
				function ( $mh ) use ( $chon, $khai ) {
					return in_array( $mh, $chon, true ) || isset( $khai[ $mh ] );
				}
			)
		);
		/* 🔴 ĐÃ CHỌN DANH MỤC THÌ BÀY ĐỦ DANH MỤC. Anh Thắng 24/09/2026 tích 21 món, Lưu, mà bảng chỉ có
		   8 dòng — *"nhiều hàng mà sao lại không hiện sl trong kho"*. Vì ở trên chỉ gom món CÓ BÁN hôm
		   nay, CÓ tồn đã biết, hay ĐÃ khai hôm nay; món trong danh mục chưa rơi vào ba nguồn ấy bị ẩn,
		   nghĩa là không có ô để nhập hàng hay đặt mốc cho nó — mà nhập hàng mới về đúng là lúc nó chưa
		   có gì cả. Danh mục là lời tuyên bố "món này có kho để đếm", nên món nào trong danh mục cũng
		   phải có dòng; số chưa biết thì bày "—". */
		foreach ( $chon as $mh ) {
			if ( ! in_array( $mh, $mh_ds, true ) ) {
				$mh_ds[] = $mh;
			}
		}
	}
	sort( $mh_ds );

	$thuc_ng = khh_dt_kho_ban_thuc( $ngay, $ngay, $co_so );
	$chot    = isset( $thuc_ng[ $ngay ] ) ? $thuc_ng[ $ngay ] : array();
	$ra = array();
	foreach ( $mh_ds as $mh ) {
		$d      = isset( $khai[ $mh ] ) ? $khai[ $mh ] : array();
		/* `null` = chưa ai đặt mốc, nên tồn đầu CHƯA BIẾT. Xem chú thích trong
		   `khh_dt_kho_trang_thai()` — ép về 0 là ngày đầu cả màn ra số âm. */
		$t_dau  = ( isset( $dau[ $mh ] ) && null !== $dau[ $mh ]['ton'] ) ? (float) $dau[ $mh ]['ton'] : null;
		$co_moc = ! empty( $dau[ $mh ]['co_moc'] );
		/* Đặt lại tồn đầu hôm nay thắng số kéo từ hôm trước — xem `khh_dt_kho_chay()`. */
		$dat    = ( isset( $d['dat_dau'] ) && null !== $d['dat_dau'] && '' !== $d['dat_dau'] ) ? (float) $d['dat_dau'] : null;
		if ( null !== $dat ) {
			$t_dau  = $dat;
			$co_moc = true;
		}
		$nhap   = isset( $d['nhap'] ) ? (float) $d['nhap'] : 0.0;
		$c_tay  = isset( $d['combo_tay'] ) ? (float) $d['combo_tay'] : 0.0;
		$huy    = isset( $d['huy'] ) ? (float) $d['huy'] : 0.0;
		if ( $co_fabi ) {
			$b_le  = isset( $tach[ $mh ]['le'] ) ? (float) $tach[ $mh ]['le'] : 0.0;
			$b_cb  = isset( $tach[ $mh ]['combo'] ) ? (float) $tach[ $mh ]['combo'] : 0.0;
			$b_may = $b_le + $b_cb;
		} else {
			$b_le  = null;   // chưa có báo cáo -> CHƯA BIẾT, xem chú thích đầu hàm
			$b_cb  = null;
			$b_may = null;
		}
		/* Chưa biết tồn đầu thì KHÔNG tính ra tồn cuối. Lượt nhập đầu tiên đặt mốc = 0.
		   Chưa biết máy bán bao nhiêu (chưa nạp FABi) thì cũng không tính — thiếu một vế là
		   ra số bịa. Số đếm của nhân viên vẫn được lưu, nạp báo cáo xong hệ tự tính lại. */
		$goc    = ( null === $t_dau && $nhap > 0 ) ? 0.0 : $t_dau;
		$tinh   = ( null === $goc || null === $b_may ) ? null : $goc + $nhap - $b_may - $c_tay - $huy;

		$khai_ban = ( isset( $d['ban_khai'] ) && null !== $d['ban_khai'] && '' !== $d['ban_khai'] )
			? (float) $d['ban_khai'] : null;
		$dem      = ( isset( $d['dem'] ) && null !== $d['dem'] && '' !== $d['dem'] )
			? (float) $d['dem'] : null;

		$ra[] = array(
			'mat_hang'  => $mh,
			'ton_dau'   => $t_dau,
			'dat_dau'   => $dat,
			'co_moc'    => $co_moc,
			'nhap'      => $nhap,
			'ban_le'    => $b_le,
			'ban_combo' => $b_cb,
			'ban_may'   => $b_may,
			'combo_tay' => $c_tay,
			'huy'       => $huy,
			/* Máy bán ở đây đã LẤY SL THỰC cơ sở chốt ở tab Nhập báo cáo nếu có — cờ này để màn ghi rõ. */
			'ban_chot'  => isset( $chot[ $mh ] ),
			'ban_khai'  => $khai_ban,
			'ton_tinh'  => $tinh,
			'dem'       => $dem,
			/* `null` khi chưa khai — KHÁC HẲN số 0. Trộn hai thứ là một ô bỏ trống trông y như
			   một ô đã đếm và đếm đúng, rồi cả sổ xanh mướt trong khi chưa ai đếm gì. */
			'lech_kho'  => ( null === $dem || null === $tinh ) ? null : $dem - $tinh,
			'lech_khai' => ( null === $khai_ban || null === $b_may ) ? null : $khai_ban - $b_may,
			'ghi_chu'   => isset( $d['ghi_chu'] ) ? (string) $d['ghi_chu'] : '',
			'nguoi'     => isset( $d['nguoi'] ) ? (string) $d['nguoi'] : '',
		);
	}
	return $ra;
}

/** Mấy món FABi có mà bảng combo chưa khai thành phần — để màn hình nhắc. */
function khh_dt_kho_combo_chua_khai( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$co    = khh_dt_kho_combo_bang();
	$nghi  = array();
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		foreach ( is_array( $mon ) ? $mon : array() as $m ) {
			$ten = isset( $m['n'] ) ? (string) $m['n'] : '';
			if ( '' === $ten || isset( $co[ $ten ] ) || isset( $nghi[ $ten ] ) ) {
				continue;
			}
			/* Đoán theo tên: chỉ để NHẮC, không để tự trừ kho. Đoán sai công thức combo là trừ
			   nhầm kho hàng loạt, mà không dòng nào sai — nên việc đặt thành phần phải do người. */
			if ( preg_match( '~\b(combo|set|gói|suất)\b~iu', $ten ) ) {
				$nghi[ $ten ] = true;
			}
		}
	}
	return array_keys( $nghi );
}

/**
 * MÓN MÁY GHI SỐ LƯỢNG MÀ DOANH THU 0đ.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ĐÂY KHÔNG PHẢI PHÉP DÒ "FABi ĐÃ TÁCH SẴN COMBO" — BẢN TRƯỚC NÓI THẾ LÀ SAI.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 20/09/2026 anh Thắng hỏi *"Theo máy thì nó có tự tách combo có hàng trong đó không"*, và em
 * dựng hàm này để tự trả lời, với lý lẽ: "thành phần combo thì máy ghi số lượng mà doanh thu
 * 0đ". Lý lẽ ấy hỏng, vì `doc-file.php` CỘNG GỘP các món THEO TÊN trong mỗi (ngày × cơ sở):
 *
 *   · Một mặt hàng vừa bán lẻ vừa nằm trong combo -> doanh thu phần bán lẻ kéo tổng lên > 0,
 *     nên nó KHÔNG BAO GIỜ lọt vào danh sách này. Tức đúng trường hợp cần dò thì dò không ra.
 *   · Thứ lọt vào lại là món LÚC NÀO CŨNG 0đ — hàng cho, khuyến mãi, vé online. Anh Thắng gửi
 *     ảnh màn hình và nó liệt kê đúng thế: "BIMBIM LỚN MIỄN PHÍ", "NƯỚC SUỐI DANASI MIỄN PHÍ",
 *     "TRÀ CHANH GIÃ TAY MIỄN PHÍ", "VÉ ONLINE". Không cái nào là thành phần combo.
 *
 * Nên hàm này nay chỉ nói ĐÚNG điều nó biết: mấy món máy ghi 0đ. Chúng CÓ trừ kho (số lượng
 * vẫn vào `ban_may`), và chúng CÓ THỂ là hàng cho, cũng CÓ THỂ là thành phần combo máy đã tách
 * — hệ không phân biệt được, nên phải để người xem quyết định, không được kết luận thay.
 *
 * @return array [ tên món => số lượng ] — món máy ghi số lượng mà doanh thu 0đ.
 */
function khh_dt_kho_mon_khong_tien( $tu, $den, $co_so ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s",
			$tu,
			$den,
			$co_so
		),
		ARRAY_A
	);
	$ra = array();
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		foreach ( is_array( $mon ) ? $mon : array() as $m ) {
			$ten = isset( $m['n'] ) ? (string) $m['n'] : '';
			$q   = isset( $m['q'] ) ? (float) $m['q'] : 0;
			$rv  = isset( $m['r'] ) ? (float) $m['r'] : 0;
			if ( '' === $ten || $q <= 0 || $rv > 0 ) {
				continue;
			}
			$ra[ $ten ] = ( isset( $ra[ $ten ] ) ? $ra[ $ten ] : 0 ) + $q;
		}
	}
	arsort( $ra );
	return $ra;
}

/**
 * Mặt hàng NGHI bị trừ kho hai lần: vừa có dòng 0đ của máy, vừa bị khai trong bảng combo.
 *
 * ⚠️ NGHI, KHÔNG PHẢI CHẮC — xem chú thích `khh_dt_kho_mon_khong_tien()`. Dòng 0đ có thể là
 *    hàng cho (thì khai combo vẫn đúng), có thể là thành phần máy đã tách (thì khai combo là
 *    trừ hai lần). Hệ nêu ra để người xem kiểm, chứ không kết luận thay.
 */
function khh_dt_kho_tru_hai_lan( $tu, $den, $co_so ) {
	$da_tach = khh_dt_kho_mon_khong_tien( $tu, $den, $co_so );
	if ( ! $da_tach ) {
		return array();
	}
	$ra = array();
	foreach ( khh_dt_kho_combo_bang() as $combo => $tp ) {
		foreach ( array_keys( (array) $tp ) as $mh ) {
			if ( isset( $da_tach[ $mh ] ) && ! in_array( $mh, $ra, true ) ) {
				$ra[] = $mh;
			}
		}
	}
	return $ra;
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_kho_route' );
function khh_dt_kho_route() {
	register_rest_route(
		'khh-dt/v1',
		'/kho',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_kho_xem',
				'permission_callback' => 'khh_dt_duoc_xem',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_kho_luu',
				/* Khai kho là việc của nhân viên trực, cùng mức quyền với nhập báo cáo ngày. */
				'permission_callback' => 'khh_dt_duoc_ghi',
			),
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/kho-the',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_kho_the',
			'permission_callback' => 'khh_dt_duoc_xem',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/kho-su',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_kho_su',
			'permission_callback' => 'khh_dt_duoc_xem',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/kho-mat-hang',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_kho_mat_hang',
			/* Đổi danh mục là đổi những gì cả cơ sở phải đếm mỗi ngày — chỉ người được ghi. */
			'permission_callback' => 'khh_dt_duoc_ghi',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/kho-combo',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_kho_combo',
			/* Đặt công thức combo là đổi cách trừ kho của MỌI ngày — chỉ người được ghi. */
			'permission_callback' => 'khh_dt_duoc_ghi',
		)
	);
}

function khh_dt_rest_kho_xem( $req ) {
	$ngay  = (string) $req->get_param( 'ngay' );
	$co_so = (string) $req->get_param( 'co_so' );
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ngay ) ) {
		$ngay = current_time( 'Y-m-d' );
	}
	if ( '' === $co_so ) {
		return new WP_Error( 'khh_dt_kho', 'Chưa chọn cơ sở.', array( 'status' => 400 ) );
	}
	/* 🔴 GÁC CẢ ĐƯỜNG ĐỌC, KHÔNG CHỈ ĐƯỜNG GHI.
	   Bản trước bỏ trống chỗ này với lý do "`khh_dt_duoc_cua_hang()` đòi quyền GHI nên không
	   dùng được ở màn xem" — đúng về mặt hàm, sai về mặt kết luận: bỏ luôn phép gác thì cửa
	   hàng trưởng quán này đổi một chữ trên thanh địa chỉ là đọc được sổ kho, tồn hàng và cả
	   phần khai của quán kia. `bao-cao-ngay.php` đã vấp đúng chỗ này và xử bằng `khh_dt_co_so_ds()`
	   — phạm vi cơ sở của người dùng, KHÔNG dính tới quyền ghi. Ở đây làm y như thế. */
	$cho_phep = function_exists( 'khh_dt_co_so_ds' ) ? khh_dt_co_so_ds() : array();
	if ( $cho_phep && ! in_array( $co_so, $cho_phep, true ) ) {
		return new WP_Error( 'khh_dt_kho', 'Anh/chị không phụ trách cơ sở này.', array( 'status' => 403 ) );
	}
	return array(
		'ngay'       => $ngay,
		'co_so'      => $co_so,
		/* Màn hình dùng cờ này để nói thẳng "chưa nạp báo cáo FABi cho ngày này" — chứ không
		   để người ta đọc một cột "—" rồi tự đoán. */
		'co_fabi'    => khh_dt_kho_co_fabi( $ngay, $co_so ),
		'dong'       => khh_dt_kho_bang_ngay( $ngay, $co_so ),
		'combo'      => khh_dt_kho_combo_bang(),
		/* Bao nhiêu lượt khai cho từng mặt hàng — màn hiện "đã sửa N lần". Đây là vế người xem
		   của sổ ghi động: giữ vết mà không bày ra thì chẳng ai biết là có vết. */
		'so_lan'     => khh_dt_kho_so_lan( $ngay, $co_so ),
		'combo_ls'   => khh_dt_kho_combo_ls(),
		/* Danh mục hàng hoá của cơ sở, và mọi món FABi từng ghi ở đây — để màn bày ra cho chọn. */
		'mat_hang'   => khh_dt_kho_mh_cua( $co_so ),
		'ma_hang'    => khh_dt_kho_ma_cua( $co_so ),
		'mon_da_thay' => khh_dt_kho_mon_da_thay(
			gmdate( 'Y-m-d', strtotime( $ngay ) - 90 * DAY_IN_SECONDS ),
			$ngay,
			$co_so
		),
		/* Món máy ghi 0đ — CHỈ vậy thôi, không kết luận là thành phần combo. Xem chú thích
		   dài ở `khh_dt_kho_mon_khong_tien()`: phép dò cũ nói quá điều nó biết. */
		'mon_khong_tien' => khh_dt_kho_mon_khong_tien(
			gmdate( 'Y-m-d', strtotime( $ngay ) - 30 * DAY_IN_SECONDS ),
			$ngay,
			$co_so
		),
		'tru_hai_lan'   => khh_dt_kho_tru_hai_lan(
			gmdate( 'Y-m-d', strtotime( $ngay ) - 30 * DAY_IN_SECONDS ),
			$ngay,
			$co_so
		),
		'combo_nghi' => khh_dt_kho_combo_chua_khai(
			gmdate( 'Y-m-d', strtotime( $ngay ) - 30 * DAY_IN_SECONDS ),
			$ngay,
			$co_so
		),
		'duoc_ghi'   => function_exists( 'khh_dt_duoc_ghi' ) ? khh_dt_duoc_ghi() : false,
	);
}

function khh_dt_rest_kho_luu( $req ) {
	$ngay  = (string) $req->get_param( 'ngay' );
	$co_so = (string) $req->get_param( 'co_so' );
	$tho   = $req->get_param( 'dong' );
	$dong  = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ngay ) || '' === $co_so ) {
		return new WP_Error( 'khh_dt_kho', 'Thiếu ngày hoặc cơ sở.', array( 'status' => 400 ) );
	}
	if ( ! is_array( $dong ) || ! $dong ) {
		return new WP_Error( 'khh_dt_kho', 'Không có dòng nào để lưu.', array( 'status' => 400 ) );
	}
	if ( function_exists( 'khh_dt_duoc_cua_hang' ) && ! khh_dt_duoc_cua_hang( $co_so ) ) {
		return new WP_Error( 'khh_dt_kho', 'Anh/chị không được khai kho của cơ sở này.', array( 'status' => 403 ) );
	}
	$n = 0;
	foreach ( $dong as $d ) {
		$mh = isset( $d['mat_hang'] ) ? sanitize_text_field( (string) $d['mat_hang'] ) : '';
		if ( '' === $mh ) {
			continue;
		}
		$n += khh_dt_kho_ghi( $ngay, $co_so, $mh, $d ) ? 1 : 0;
	}
	$r          = khh_dt_rest_kho_xem( $req );
	$r['da_ghi'] = $n;
	return $r;
}

/** Thẻ kho một mặt hàng trong một khoảng ngày. */
function khh_dt_rest_kho_the( $req ) {
	$co_so = (string) $req->get_param( 'co_so' );
	$mh    = (string) $req->get_param( 'mat_hang' );
	$tu    = (string) $req->get_param( 'tu' );
	$den   = (string) $req->get_param( 'den' );
	if ( '' === $co_so || '' === $mh ) {
		return new WP_Error( 'khh_dt_kho', 'Thiếu cơ sở hoặc mặt hàng.', array( 'status' => 400 ) );
	}
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $den ) ) {
		$den = current_time( 'Y-m-d' );
	}
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $tu ) ) {
		$tu = gmdate( 'Y-m-d', strtotime( $den ) - 30 * DAY_IN_SECONDS );
	}
	/* Gác theo cơ sở y như đường xem sổ. */
	$cho_phep = function_exists( 'khh_dt_co_so_ds' ) ? khh_dt_co_so_ds() : array();
	if ( $cho_phep && ! in_array( $co_so, $cho_phep, true ) ) {
		return new WP_Error( 'khh_dt_kho', 'Anh/chị không phụ trách cơ sở này.', array( 'status' => 403 ) );
	}
	return array(
		'co_so'    => $co_so,
		'mat_hang' => $mh,
		'tu'       => $tu,
		'den'      => $den,
		'dong'     => khh_dt_kho_the( $co_so, $mh, $tu, $den ),
	);
}

/** Lịch sử khai của MỘT dòng — mọi lượt, mới nhất trước. */
function khh_dt_rest_kho_su( $req ) {
	$ngay  = (string) $req->get_param( 'ngay' );
	$co_so = (string) $req->get_param( 'co_so' );
	$mh    = (string) $req->get_param( 'mat_hang' );
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ngay ) || '' === $co_so || '' === $mh ) {
		return new WP_Error( 'khh_dt_kho', 'Thiếu ngày, cơ sở hoặc mặt hàng.', array( 'status' => 400 ) );
	}
	/* Gác theo cơ sở y như đường xem sổ — lịch sử khai cũng là số liệu của cơ sở ấy. */
	$cho_phep = function_exists( 'khh_dt_co_so_ds' ) ? khh_dt_co_so_ds() : array();
	if ( $cho_phep && ! in_array( $co_so, $cho_phep, true ) ) {
		return new WP_Error( 'khh_dt_kho', 'Anh/chị không phụ trách cơ sở này.', array( 'status' => 403 ) );
	}
	return array( 'su' => khh_dt_kho_su_cua( $ngay, $co_so, $mh ) );
}

function khh_dt_rest_kho_mat_hang( $req ) {
	$co_so = (string) $req->get_param( 'co_so' );
	if ( '' === $co_so ) {
		return new WP_Error( 'khh_dt_kho', 'Chưa chọn cơ sở.', array( 'status' => 400 ) );
	}
	/* 🔴 KIỂM HẾT RỒI MỚI GHI. Bản đầu ghi danh sách tích trước rồi mới kiểm món thêm: thêm hỏng (trùng
	   mã) vẫn trả lỗi, nhưng danh mục đã bị ghi đè — bài kiểm bắt được đúng chỗ này. */
	$xoa = sanitize_text_field( (string) $req->get_param( 'xoa_ten' ) );
	if ( '' !== $xoa ) {
		/* Xoá món thêm tay — CHỈ văn phòng (quyền nạp file); cửa hàng không tự xoá dòng kho. */
		$vp = function_exists( 'khh_dt_duoc_nap' ) ? khh_dt_duoc_nap() : false;
		if ( true !== $vp ) {
			return new WP_Error( 'khh_dt_kho', 'Xoá mặt hàng cần tài khoản văn phòng (vai duyệt / biên tập).', array( 'status' => 403 ) );
		}
	}
	$them_ten = sanitize_text_field( (string) $req->get_param( 'them_ten' ) );
	$them_ma  = sanitize_text_field( (string) $req->get_param( 'them_ma' ) );
	if ( '' !== $them_ten ) {
		/* Thêm mặt hàng MỚI (chưa có trong FABi) kèm mã hàng — xem `khh_dt_kho_them_mh`. Chối là chưa đụng gì. */
		$kq = khh_dt_kho_them_mh( $co_so, $them_ten, $them_ma );
		if ( empty( $kq['ok'] ) ) {
			return new WP_Error( 'khh_dt_kho', $kq['error'], array( 'status' => 400 ) );
		}
	}
	$tho = $req->get_param( 'ds' );
	$ds  = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	$ds  = is_array( $ds ) ? $ds : array();
	if ( '' !== $them_ten && ! in_array( $them_ten, $ds, true ) ) {
		$ds[] = $them_ten;
	}
	/* Gửi danh sách RỖNG là hợp lệ — nghĩa là "thôi lọc, bày hết". Không được coi là lỗi. */
	khh_dt_kho_mh_dat( $co_so, $ds );
	if ( '' !== $xoa ) {
		khh_dt_kho_xoa_mh( $co_so, $xoa );
	}
	/* Gán mã cho món đã có: [ tên => mã ], '' là bỏ mã. */
	$tho_ma = $req->get_param( 'ma' );
	$ma_ds  = is_array( $tho_ma ) ? $tho_ma : json_decode( (string) $tho_ma, true );
	if ( is_array( $ma_ds ) ) {
		foreach ( $ma_ds as $ten => $ma ) {
			khh_dt_kho_ma_dat( $co_so, sanitize_text_field( (string) $ten ), sanitize_text_field( (string) $ma ) );
		}
	}
	return khh_dt_rest_kho_xem( $req );
}

function khh_dt_rest_kho_combo( $req ) {
	$ten = (string) $req->get_param( 'ten' );
	$tho = $req->get_param( 'thanh_phan' );
	$tp  = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	if ( '' === trim( $ten ) ) {
		return new WP_Error( 'khh_dt_kho', 'Thiếu tên combo.', array( 'status' => 400 ) );
	}
	khh_dt_kho_combo_dat( $ten, is_array( $tp ) ? $tp : array(), (string) $req->get_param( 'tu_ngay' ) );
	return array( 'combo' => khh_dt_kho_combo_bang(), 'combo_ls' => khh_dt_kho_combo_ls() );
}
