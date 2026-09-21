<?php
/**
 * ĐỐI SOÁT MOMO THEO TỪNG GIAO DỊCH — không phải theo tổng ngày.
 *
 * ==================================================================================================
 * 🔴 VÌ SAO PHẢI XUỐNG TỚI TỪNG GIAO DỊCH.
 * ==================================================================================================
 * Anh Thắng 16/09/2026: *"2 bên cũng hay lỗi do nhận tiền lỗi, nên cũng cần có bảng lệch giao dịch
 * CK/QR/MoMo giữa 2 bên"*.
 *
 * So tổng một ngày thì hai lỗi ngược chiều nhau triệt tiêu lẫn nhau: một giao dịch MoMo nhận mà máy
 * không ghi, cộng một giao dịch máy ghi mà MoMo không nhận — tổng vẫn khớp, mà thực tế là HAI cái
 * sai. Xuống tới từng mã giao dịch thì cả hai hiện ra.
 *
 * File anh Thắng gửi ("MoMo payments" xuất từ FABi) có sẵn thứ để ghép: cột **Mã đối tác** chính
 * là mã giao dịch của MoMo. Ghép theo mã ấy là chắc; không có mã thì mới lui về ghép mờ theo
 * ngày + số tiền + cơ sở.
 *
 * ==================================================================================================
 * 🔴 VÀ NÓ GIẢI LUÔN CHUYỆN "MÁY FABI ĐỔI CƠ SỞ".
 * ==================================================================================================
 * Anh Thắng: *"1 mã cửa MoMo sẽ gắn 1 cửa hàng trên FABi, nhưng sau khi đóng cửa, máy FABi đó đổi
 * sang cơ sở khác, thì MoMo vẫn nguyên, nhưng tên cửa hàng sẽ đổi sang cửa hàng mới… nếu trong
 * tháng có thay đổi cửa hàng, anh sẽ gửi file này lên để hệ thống tự rà"*.
 *
 * Khai một bảng "mã MoMo thuộc cửa hàng nào" rồi sửa tay mỗi lần dời máy là kiểu bảo trì không ai
 * theo nổi — dời máy giữa tháng một cái là cả tháng ấy gán sai, âm thầm.
 *
 * Nhưng CHÍNH FILE NÀY đã trả lời: mỗi trang tính mang tên cửa hàng FABi tại thời điểm xuất, và
 * mỗi giao dịch trong trang ấy thuộc về cửa hàng ấy. Nên mình không cần khai gì: giao dịch nào
 * thuộc cửa hàng nào là do file nói, theo đúng từng giao dịch, đúng từng ngày. Dời máy giữa tháng
 * thì nửa đầu tháng nằm ở trang cũ, nửa sau nằm ở trang mới — tự nó đúng.
 *
 * Và mỗi lần nạp, hệ CÒN HỌC ĐƯỢC bảng ghép "tên cửa hàng bên MoMo ↔ cơ sở FABi" từ những cặp
 * giao dịch đã khớp mã — để những dòng sao kê MoMo KHÔNG khớp được với máy vẫn biết đường về đúng
 * cơ sở. Xem `khh_dt_hoc_ghep_momo()`.
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function khh_dt_bang_momo() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_momo_pos';
}

function khh_dt_tao_bang_momo() {
	global $wpdb;
	$bang    = khh_dt_bang_momo();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ma_doi_tac varchar(80) NOT NULL DEFAULT '',
			ma_hd varchar(80) NOT NULL DEFAULT '',
			so_hd varchar(40) NOT NULL DEFAULT '',
			ngay date NOT NULL,
			gio tinyint(4) NOT NULL DEFAULT 0,
			cua_hang varchar(190) NOT NULL DEFAULT '',
			so_tien double NOT NULL DEFAULT 0,
			trang_thai varchar(60) NOT NULL DEFAULT '',
			loai varchar(10) NOT NULL DEFAULT 'dong',
			nap_luc datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_doi_tac (ma_doi_tac),
			KEY ngay (ngay),
			KEY cua_hang (cua_hang(120))
		) $charset;"
	);
}

/* ================================================================== *
 * Đọc file "MoMo payments" xuất từ FABi
 * ================================================================== */

/**
 * Mỗi trang tính một cửa hàng; trong trang có HAI khối nằm cạnh nhau:
 *   cột A–F  "Giao dịch QR Tĩnh"  — Mã hoá đơn · Số HĐ · Ngày bán · Mã đối tác · Tổng tiền · Trạng thái
 *   cột H–L  "Giao dịch QR Động"  — Mã hoá đơn · Số HĐ · Ngày bán · Mã đối tác · Tổng tiền
 *
 * ⚠️ ĐỌC CẢ HAI KHỐI. Quán nào cũng có thể dùng cả mã QR dán sẵn lẫn mã sinh theo đơn; bỏ một khối
 *    là mất nguyên một dòng tiền mà tổng vẫn trông hợp lý.
 */
function khh_dt_doc_momo_pos( $duong_dan ) {
	if ( ! khh_dt_doc_duoc_xlsx() ) {
		return new WP_Error( 'khh_dt_zip', 'Máy chủ thiếu ZipArchive hoặc XMLReader nên chưa đọc được .xlsx.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $duong_dan ) ) {
		return new WP_Error( 'khh_dt_zip', 'File .xlsx hỏng hoặc không mở được.' );
	}
	$ds_trang = khh_dt_ds_trang( $zip );
	if ( ! $ds_trang ) {
		$zip->close();
		return new WP_Error( 'khh_dt_sheet', 'Không đọc được danh sách trang tính.' );
	}
	$chuoi = khh_dt_chuoi_chung( $zip );

	$gop = array(
		'dong'    => array(),
		'so_trang' => 0,
		'bo_qua'  => 0,
		'quan'    => array(),
	);

	foreach ( $ds_trang as $trang ) {
		/* 🔴 EXCEL CẮT TÊN TRANG TÍNH Ở 31 KÝ TỰ. File thật của anh Thắng có trang
		   "FUNZONE CITY VŨNG TÀU ( Dịch Vụ" — thiếu đuôi "và Giải Trí K&H )" so với tên cơ sở
		   trong số liệu POS. Giữ nguyên tên cụt thì mọi giao dịch của quán ấy nằm riêng một cơ sở
		   không tồn tại, và mọi phép đối soát sau đó đều hụt. Ghép về tên đầy đủ ngay tại đây. */
		$ten_quan = trim( (string) $trang['ten'] );
		$ghep     = khh_dt_ghep_ten_gan( $ten_quan );
		if ( $ghep['diem'] >= 0.6 && '' !== $ghep['ten'] ) {
			$ten_quan = $ghep['ten'];
		}
		$tam      = khh_dt_rut_file( $zip, $trang['file'] );
		if ( ! $tam ) {
			continue;
		}
		$gop['so_trang']++;
		$hang = 0;
		khh_dt_doc_trang(
			$tam,
			$chuoi,
			function ( $d ) use ( &$gop, $ten_quan, &$hang ) {
				$hang++;
				if ( $hang <= 2 ) {
					return;                       // dòng tiêu đề khối và dòng tên cột
				}
				/* Hai khối: tĩnh bắt đầu ở cột 0, động ở cột 7. */
				foreach ( array( array( 0, 'tinh' ), array( 7, 'dong' ) ) as $khoi ) {
					list( $c, $loai ) = $khoi;
					$ma_dt = trim( (string) ( isset( $d[ $c + 3 ] ) ? $d[ $c + 3 ] : '' ) );
					$tien  = khh_dt_so( isset( $d[ $c + 4 ] ) ? $d[ $c + 4 ] : '' );
					$luc   = (string) ( isset( $d[ $c + 2 ] ) ? $d[ $c + 2 ] : '' );
					$ngay  = khh_dt_ngay( $luc );
					if ( '' === $ma_dt && $tien <= 0 ) {
						continue;                  // ô trống của khối bên kia
					}
					if ( '' === $ngay || $tien <= 0 ) {
						$gop['bo_qua']++;
						continue;
					}
					$gop['dong'][] = array(
						'ma_doi_tac' => $ma_dt,
						'ma_hd'      => trim( (string) ( isset( $d[ $c ] ) ? $d[ $c ] : '' ) ),
						'so_hd'      => trim( (string) ( isset( $d[ $c + 1 ] ) ? $d[ $c + 1 ] : '' ) ),
						'ngay'       => $ngay,
						'gio'        => khh_dt_gio( $luc ),
						'cua_hang'   => $ten_quan,
						'so_tien'    => $tien,
						'trang_thai' => trim( (string) ( isset( $d[ $c + 5 ] ) ? $d[ $c + 5 ] : '' ) ),
						'loai'       => $loai,
					);
					$gop['quan'][ $ten_quan ] = true;
				}
			}
		);
		wp_delete_file( $tam );
	}
	$zip->close();

	if ( ! $gop['dong'] ) {
		return new WP_Error(
			'khh_dt_momo_rong',
			'Đọc xong mà không có giao dịch nào. Anh xuất đúng bản "MoMo payments" của FABi giúp em.'
		);
	}
	$gop['quan'] = array_keys( $gop['quan'] );
	return $gop;
}

/**
 * Ghi vào kho, khoá theo Mã đối tác.
 *
 * ⚠️ NẠP LẠI THÌ GHI ĐÈ, KỂ CẢ CỘT CỬA HÀNG. Đó chính là cách hệ "tự rà" khi máy FABi dời cơ sở:
 *    anh Thắng nạp lại file, giao dịch cũ nằm ở trang tính mới và cột cửa hàng đổi theo. Nếu chỉ
 *    thêm dòng mới mà không sửa dòng cũ thì cái dời máy ấy không bao giờ ăn.
 */
function khh_dt_ghi_momo_pos( $ds ) {
	global $wpdb;
	$bang = khh_dt_bang_momo();
	$luc  = current_time( 'mysql' );
	$n    = 0;
	foreach ( (array) $ds as $r ) {
		$ma = (string) $r['ma_doi_tac'];
		if ( '' === $ma ) {
			/* Không có mã đối tác thì tự đúc một mã ổn định — để nạp lại không đẻ dòng mới. */
			$ma = 'tu-' . substr( md5( $r['ngay'] . '|' . $r['gio'] . '|' . $r['so_tien'] . '|' . $r['cua_hang'] . '|' . $r['ma_hd'] ), 0, 24 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $bang (ma_doi_tac,ma_hd,so_hd,ngay,gio,cua_hang,so_tien,trang_thai,loai,nap_luc)
				 VALUES (%s,%s,%s,%s,%d,%s,%f,%s,%s,%s)
				 ON DUPLICATE KEY UPDATE ma_hd=VALUES(ma_hd), so_hd=VALUES(so_hd), ngay=VALUES(ngay),
				 gio=VALUES(gio), cua_hang=VALUES(cua_hang), so_tien=VALUES(so_tien),
				 trang_thai=VALUES(trang_thai), loai=VALUES(loai), nap_luc=VALUES(nap_luc)",
				$ma,
				$r['ma_hd'],
				$r['so_hd'],
				$r['ngay'],
				$r['gio'],
				$r['cua_hang'],
				$r['so_tien'],
				$r['trang_thai'],
				$r['loai'],
				$luc
			)
		);
		$n++;
	}
	return $n;
}

function khh_dt_co_momo_pos() {
	global $wpdb;
	$bang = khh_dt_bang_momo();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" );
}

/* ================================================================== *
 * SỔ SAO KÊ MOMO — nạp thẳng file MoMo xuất ra
 * ================================================================== */

/**
 * Anh Thắng 16/09/2026: *"vì MoMo không cho kết nối API, nên anh hay upload file này lên sao kê"*.
 *
 * File "Transaction report" của MoMo là .csv, cột tiếng Việt, mỗi dòng một giao dịch:
 *   Thời gian · Mã đơn hàng · Mã giao dịch · Trạng thái · Số tiền · Mã cửa hàng · Tên cửa hàng …
 *
 * 🔴 CỘT "MÃ GIAO DỊCH" CHÍNH LÀ "MÃ ĐỐI TÁC" BÊN FABi. Đã đo trên hai file thật của anh Thắng:
 *    995 trong 1.008 mã của MoMo có mặt trong file FABi. Nhờ vậy ghép được TỪNG GIAO DỊCH, không
 *    phải so tổng ngày — và 13 mã còn lại chính là nhóm "MoMo nhận mà máy không ghi", tức đúng
 *    thứ cần soi.
 *
 * 🔴 VÀ "MÃ CỬA HÀNG" CỦA MOMO (KHTUTU2, KHECO2…) LÀ THỨ KHÔNG ĐỔI KHI MÁY FABi DỜI CƠ SỞ.
 *    Đây là mấu chốt bài toán anh Thắng nêu. Mình KHÔNG khai tay mã ấy thuộc quán nào: cứ ghép
 *    theo mã giao dịch, rồi HỌC ra "KHTUTU2 đang là quán nào" từ chính những cặp đã khớp. Máy
 *    dời chỗ thì lứa giao dịch mới dạy lại, không ai phải sửa bảng.
 */
function khh_dt_bang_momo_sk() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_momo_sk';
}

function khh_dt_tao_bang_momo_sk() {
	global $wpdb;
	$bang    = khh_dt_bang_momo_sk();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ma_gd varchar(80) NOT NULL DEFAULT '',
			ma_don varchar(80) NOT NULL DEFAULT '',
			ngay date NOT NULL,
			gio tinyint(4) NOT NULL DEFAULT 0,
			so_tien double NOT NULL DEFAULT 0,
			trang_thai varchar(60) NOT NULL DEFAULT '',
			loai_gd varchar(60) NOT NULL DEFAULT '',
			nguon_tien varchar(60) NOT NULL DEFAULT '',
			ma_ch varchar(60) NOT NULL DEFAULT '',
			ten_ch varchar(190) NOT NULL DEFAULT '',
			nap_luc datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_gd (ma_gd),
			KEY ngay (ngay),
			KEY ma_ch (ma_ch)
		) $charset;"
	);
}

/** Tên cột của file MoMo, so khớp không dấu. */
function khh_dt_cot_momo_sk() {
	return array(
		'ngay'       => array( 'thoi gian', 'thoi diem', 'ngay' ),
		'ma_gd'      => array( 'ma giao dich' ),
		'ma_don'     => array( 'ma don hang goc', 'ma don hang' ),
		'trang_thai' => array( 'trang thai' ),
		'so_tien'    => array( 'so tien' ),
		'loai_gd'    => array( 'loai giao dich' ),
		'nguon_tien' => array( 'nguon tien' ),
		'ma_ch'      => array( 'ma cua hang' ),
		'ten_ch'     => array( 'ten cua hang' ),
	);
}

/**
 * NÓI ĐÚNG VÌ SAO FILE KHÔNG ĐỌC ĐƯỢC — thay vì đổ cho người nạp.
 *
 * 17/09/2026: anh Thắng nạp `MoMo-payments.xlsx` vào thẻ "Sao kê MoMo" và nhận câu
 * *"Anh tải đúng bản Transaction report của MoMo giúp em."* File ấy KHÔNG sai — nó là file
 * MoMo payments xuất từ FABi, hợp lệ hoàn toàn, chỉ thuộc thẻ bên cạnh ("Giao dịch MoMo
 * (FABi)"). Bảo người ta đi tải lại một file họ đang có sẵn là đẩy họ đi một vòng vô ích, và
 * lần sau họ sẽ tin rằng chỗ nạp bị hỏng.
 *
 * Hai ca phân biệt được chắc chắn, nên phải phân biệt:
 *   · file là .xlsx (bắt đầu bằng "PK") — mà bộ đọc sổ MoMo chỉ ăn .csv. Ô thả lại ghi "nhận
 *     .xlsx, .csv" nên người nạp không có cách nào tự biết. Nếu trong ruột có dấu vết của bảng
 *     FABi thì chỉ luôn sang thẻ đúng.
 *   · file là .csv thật nhưng thiếu cột — lúc ấy câu cũ mới đúng.
 */
function khh_dt_momo_sk_loi_cot( $duong_dan ) {
	$dau = '';
	$f   = fopen( $duong_dan, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( $f ) {
		$dau = (string) fread( $f, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	/* .xlsx/.docx… đều là gói ZIP, nên bốn byte đầu là "PK\x03\x04". */
	if ( 0 !== strpos( $dau, 'PK' ) ) {
		return 'Không thấy cột "Thời gian" và "Số tiền". Sổ MoMo phải là bản Transaction report '
			. 'xuất từ trang quản lý MoMo (Giao dịch → Xuất báo cáo).';
	}

	/* Dò dấu vết bảng FABi ở CẢ HAI chỗ Excel có thể đặt chữ:
	     · `xl/sharedStrings.xml` — file FABi thật dùng đường này (13.924 chuỗi);
	     · chuỗi nội tuyến (`inlineStr`) ngay trong trang tính — hợp lệ y như vậy, và một bộ xuất
	       khác hoàn toàn có thể dùng.
	   Soi mỗi sharedStrings là đủ cho file hôm nay và lọt cho file mai. Chính bài kiểm bắt được
	   chỗ này: nó dựng file mẫu bằng inlineStr, nên bản vá đầu của hàm không nhận ra gì cả.
	   ⚠️ Chỉ đọc 64KB đầu mỗi tệp: tiêu đề cột nằm ở hai dòng trên cùng, còn trang tính thật
	   nặng hàng MB — nạp cả vào bộ nhớ chỉ để đọc tiêu đề là đổi lỗi này lấy lỗi hết RAM. */
	$la_fabi = false;
	if ( khh_dt_doc_duoc_xlsx() ) {
		$zip = new ZipArchive();
		if ( true === $zip->open( $duong_dan ) ) {
			$mau = '';
			foreach ( array( 'xl/sharedStrings.xml', 'xl/worksheets/sheet1.xml' ) as $ten ) {
				$m = $zip->getFromName( $ten, 65536 );
				if ( is_string( $m ) ) {
					$mau .= ' ' . $m;
				}
			}
			$zip->close();
			$k = khh_dt_khong_dau( $mau );
			/* "Mã đối tác" là cột riêng của bảng FABi; sổ MoMo không có cột nào tên thế. */
			if ( false !== strpos( $k, 'ma doi tac' ) || false !== strpos( $k, 'giao dich qr' ) ) {
				$la_fabi = true;
			}
		}
	}

	if ( $la_fabi ) {
		return 'Đây là file <b>MoMo payments xuất từ FABi</b>, không phải sổ MoMo — file đúng, '
			. 'nhưng nhầm thẻ. Nạp nó ở thẻ <b>Giao dịch MoMo (FABi)</b> ngay bên cạnh. '
			. 'Còn thẻ này cần bản <b>Transaction report</b> (.csv) tải từ trang quản lý MoMo: '
			. 'Giao dịch → Xuất báo cáo.';
	}
	return 'Thẻ này chỉ đọc <b>.csv</b>, mà anh vừa thả một file bảng tính (.xlsx). Bản '
		. 'Transaction report tải từ trang quản lý MoMo (Giao dịch → Xuất báo cáo) là .csv — '
		. 'nạp thẳng file ấy.';
}

/** Đọc file .csv MoMo xuất ra. */
function khh_dt_doc_momo_sk( $duong_dan ) {
	$f = fopen( $duong_dan, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $f ) {
		return new WP_Error( 'khh_dt_momo_sk', 'Không mở được file.' );
	}
	$bom = fread( $f, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( "\xEF\xBB\xBF" !== $bom ) {
		rewind( $f );
	}
	$map  = null;
	$gop  = array( 'dong' => array(), 'so_dong' => 0, 'bo_qua' => 0, 'quan' => array() );
	while ( false !== ( $d = fgetcsv( $f, 0, ',', '"', '\\' ) ) ) {
		if ( null === $d || ( 1 === count( $d ) && null === $d[0] ) ) {
			continue;
		}
		if ( null === $map ) {
			$kd  = array_map( 'khh_dt_khong_dau', $d );
			$map = array();
			foreach ( khh_dt_cot_momo_sk() as $vai => $ten_ds ) {
				$map[ $vai ] = -1;
				foreach ( $ten_ds as $t ) {
					$i = array_search( $t, $kd, true );
					if ( false !== $i ) {
						$map[ $vai ] = $i;
						break;
					}
				}
			}
			if ( $map['ngay'] < 0 || $map['so_tien'] < 0 ) {
				fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new WP_Error( 'khh_dt_momo_sk', khh_dt_momo_sk_loi_cot( $duong_dan ) );
			}
			continue;
		}
		$gop['so_dong']++;
		$o = function ( $vai ) use ( $d, $map ) {
			return ( $map[ $vai ] > -1 && isset( $d[ $map[ $vai ] ] ) ) ? trim( (string) $d[ $map[ $vai ] ] ) : '';
		};
		/* MoMo ghi ngày kiểu 16-09-2026 14:44:48. */
		$luc  = $o( 'ngay' );
		$ngay = khh_dt_ngay( $luc );
		$tien = khh_dt_so( $o( 'so_tien' ) );
		if ( '' === $ngay || $tien <= 0 ) {
			$gop['bo_qua']++;
			continue;
		}
		$gop['dong'][] = array(
			'ma_gd'      => $o( 'ma_gd' ),
			'ma_don'     => $o( 'ma_don' ),
			'ngay'       => $ngay,
			'gio'        => khh_dt_gio( $luc ),
			'so_tien'    => $tien,
			'trang_thai' => $o( 'trang_thai' ),
			'loai_gd'    => $o( 'loai_gd' ),
			'nguon_tien' => $o( 'nguon_tien' ),
			'ma_ch'      => $o( 'ma_ch' ),
			'ten_ch'     => $o( 'ten_ch' ),
		);
		if ( '' !== $o( 'ma_ch' ) ) {
			$gop['quan'][ $o( 'ma_ch' ) ] = $o( 'ten_ch' );
		}
	}
	fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $gop['dong'] ) {
		return new WP_Error( 'khh_dt_momo_sk', 'Đọc xong mà không có giao dịch nào.' );
	}
	return $gop;
}

/** Ghi vào kho, khoá theo mã giao dịch MoMo. */
function khh_dt_ghi_momo_sk( $ds ) {
	global $wpdb;
	$bang = khh_dt_bang_momo_sk();
	$luc  = current_time( 'mysql' );
	$n    = 0;
	foreach ( (array) $ds as $r ) {
		$ma = (string) $r['ma_gd'];
		if ( '' === $ma ) {
			$ma = 'tu-' . substr( md5( $r['ngay'] . '|' . $r['gio'] . '|' . $r['so_tien'] . '|' . $r['ma_ch'] ), 0, 24 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $bang (ma_gd,ma_don,ngay,gio,so_tien,trang_thai,loai_gd,nguon_tien,ma_ch,ten_ch,nap_luc)
				 VALUES (%s,%s,%s,%d,%f,%s,%s,%s,%s,%s,%s)
				 ON DUPLICATE KEY UPDATE ma_don=VALUES(ma_don), ngay=VALUES(ngay), gio=VALUES(gio),
				 so_tien=VALUES(so_tien), trang_thai=VALUES(trang_thai), loai_gd=VALUES(loai_gd),
				 nguon_tien=VALUES(nguon_tien), ma_ch=VALUES(ma_ch), ten_ch=VALUES(ten_ch),
				 nap_luc=VALUES(nap_luc)",
				$ma,
				$r['ma_don'],
				$r['ngay'],
				$r['gio'],
				$r['so_tien'],
				$r['trang_thai'],
				$r['loai_gd'],
				$r['nguon_tien'],
				$r['ma_ch'],
				$r['ten_ch'],
				$luc
			)
		);
		$n++;
	}
	return $n;
}

function khh_dt_co_momo_sk() {
	global $wpdb;
	$bang = khh_dt_bang_momo_sk();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" );
}

/**
 * HỌC "mã cửa hàng MoMo → cơ sở FABi" từ những cặp giao dịch đã khớp mã.
 *
 * 🔴 ĐÂY LÀ LỜI GIẢI CHO BÀI "MÁY DỜI CƠ SỞ". Mã cửa hàng của MoMo (KHTUTU2) không đổi; tên cơ sở
 *    bên FABi thì đổi khi dời máy. Ghép theo MÃ GIAO DỊCH rồi học ngược ra, nên mỗi lứa giao dịch
 *    mới tự dạy lại bảng — không ai phải sửa tay, và không có cái bảng nào để quên sửa.
 *
 * ⚠️ LỨA FABi MỚI NHẤT THẮNG. Anh Thắng 17/09/2026 chốt luật: *"nạp dữ liệu FABi vào là xác định
 *    giá trị thật nó đang nằm cơ sở nào"* — FABi là nguồn sự thật về máy đang ở đâu.
 *
 * 🔴 TRƯỚC 17/09/2026 HÀM NÀY HỎNG ĐÚNG CÁI VIỆC NÓ SINH RA ĐỂ LÀM. Chú thích cũ ghi "một mã trỏ
 *    về hai quán TRONG CÙNG KỲ thì không học", nhưng câu SQL KHÔNG LỌC NGÀY — nó quét sạch lịch
 *    sử. Nên chỉ cần dời máy MỘT lần là mã ấy vĩnh viễn trỏ về hai quán, vĩnh viễn rơi vào nhánh
 *    "không học", và bảng ghép ĐỨNG YÊN Ở TÊN CŨ. Máy đã sang quán mới cả tháng mà doanh thu vẫn
 *    được kể cho quán cũ — sai ở cả hai đầu, và không có lấy một câu báo. Càng dùng lâu càng sai,
 *    vì lịch sử chỉ dài thêm chứ không ngắn đi.
 *
 * ⚠️ CHỈ DỪNG LẠI KHI THẬT SỰ KHÔNG PHÂN ĐỊNH ĐƯỢC: hai quán cùng chia nhau NGÀY MỚI NHẤT. Lúc ấy
 *    đoán bên nào cũng sai một nửa nên không đoán, và mã vào danh sách `lan_can` để màn hình nói
 *    ra. Giao dịch đã khớp mã vẫn đúng cơ sở theo file FABi, nên không mất gì.
 */
function khh_dt_hoc_ma_ch_momo() {
	global $wpdb;
	$sk  = khh_dt_bang_momo_sk();
	$pos = khh_dt_bang_momo();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		"SELECT s.ma_ch, p.cua_hang, COUNT(*) n, MAX(p.ngay) ngay_cuoi FROM $sk s
		 INNER JOIN $pos p ON p.ma_doi_tac = s.ma_gd
		 WHERE s.ma_ch <> '' AND p.cua_hang <> ''
		 GROUP BY s.ma_ch, p.cua_hang",
		ARRAY_A
	);
	/* Ngày lưu dạng YYYY-MM-DD nên so chuỗi là so đúng thứ tự thời gian. */
	$theo = array();
	foreach ( $ds as $r ) {
		$theo[ $r['ma_ch'] ][ $r['cua_hang'] ] = (string) $r['ngay_cuoi'];
	}
	$hoc     = get_option( 'khh_dt_ghep_ma_ch_momo', array() );
	$hoc     = is_array( $hoc ) ? $hoc : array();
	$so      = 0;
	$lan_can = array();
	foreach ( $theo as $ma => $quan ) {
		/* Quán nào giữ máy tới NGÀY MUỘN NHẤT thì quán ấy đang giữ máy. */
		$muon_nhat = max( $quan );
		$dan_dau   = array_keys( $quan, $muon_nhat, true );
		if ( count( $dan_dau ) > 1 ) {
			$lan_can[] = $ma;
			continue;
		}
		$q = $dan_dau[0];
		if ( ! isset( $hoc[ $ma ] ) || $hoc[ $ma ] !== $q ) {
			$hoc[ $ma ] = $q;
			$so++;
		}
	}
	update_option( 'khh_dt_ghep_ma_ch_momo', $hoc, false );
	return array( 'hoc' => $so, 'lan_can' => $lan_can, 'bang' => $hoc );
}

/** Mã cửa hàng MoMo -> cơ sở FABi, theo bảng đã học. */
function khh_dt_ma_ch_toi_co_so( $ma_ch ) {
	$hoc = get_option( 'khh_dt_ghep_ma_ch_momo', array() );
	$ma  = trim( (string) $ma_ch );
	return ( is_array( $hoc ) && isset( $hoc[ $ma ] ) ) ? (string) $hoc[ $ma ] : '';
}

/* ================================================================== *
 * Đối soát từng giao dịch: máy POS ↔ sao kê MoMo
 * ================================================================== */

/** Giao dịch MoMo trên máy POS trong kỳ. */
function khh_dt_momo_pos_ds( $tu, $den ) {
	global $wpdb;
	$bang = khh_dt_bang_momo();
	$sql  = "SELECT ma_doi_tac, ma_hd, ngay, gio, cua_hang, so_tien, trang_thai FROM $bang WHERE 1=1";
	$args = array();
	if ( $tu ) {
		$sql   .= ' AND ngay >= %s';
		$args[] = $tu;
	}
	if ( $den ) {
		$sql   .= ' AND ngay <= %s';
		$args[] = $den;
	}
	$sql .= ' ORDER BY ngay, gio LIMIT 20000';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (array) ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ) );
	// phpcs:enable
}

/**
 * Giao dịch trong sổ sao kê MoMo.
 *
 * Ưu tiên KHO NỘI BỘ (file MoMo anh Thắng nạp lên) vì nó có đủ mã giao dịch và mã cửa hàng; không
 * có thì mới đọc bảng ngoài đã khai.
 */
function khh_dt_momo_sk_ds( $tu, $den ) {
	global $wpdb;
	if ( khh_dt_co_momo_sk() ) {
		$bang = khh_dt_bang_momo_sk();
		$sql  = "SELECT ngay, so_tien tien, ten_ch ten, ma_gd ma, ma_ch FROM $bang WHERE 1=1";
		$args = array();
		if ( $tu ) {
			$sql   .= ' AND ngay >= %s';
			$args[] = $tu;
		}
		if ( $den ) {
			$sql   .= ' AND ngay <= %s';
			$args[] = $den;
		}
		$sql .= ' LIMIT 20000';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return (array) ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ) );
		// phpcs:enable
	}
	$n = khh_dt_nguon_momo();
	if ( ! $n ) {
		return array();
	}
	$bang = (string) $n['bang'];
	$c    = (array) $n['cot'];
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) ) ) {
		return array();
	}
	if ( empty( $c['ngay'] ) || empty( $c['so_tien'] ) ) {
		return array();
	}
	$ten  = ! empty( $c['nhan'] ) ? $c['nhan'] : ( ! empty( $c['noi_dung'] ) ? $c['noi_dung'] : '' );
	$ma   = ! empty( $c['ma_gd'] ) ? $c['ma_gd'] : '';
	$lay  = "`{$c['ngay']}` ngay, `{$c['so_tien']}` tien";
	$lay .= $ten ? ", `$ten` ten" : ", '' ten";
	$lay .= $ma ? ", `$ma` ma" : ", '' ma";
	$sql  = "SELECT $lay FROM `$bang` WHERE 1=1";
	$args = array();
	/* Cùng bộ lọc với `khh_dt_momo_theo_ngay()` — sổ cổng gộp cả VietQR/MoMo/VNPAY. */
	if ( ! empty( $n['loc']['cot'] ) ) {
		$sql   .= ' AND `' . $n['loc']['cot'] . '` = %s';
		$args[] = (string) $n['loc']['gt'];
	}
	if ( $tu ) {
		$sql   .= " AND `{$c['ngay']}` >= %s";
		$args[] = $tu . ' 00:00:00';
	}
	if ( $den ) {
		$sql   .= " AND `{$c['ngay']}` <= %s";
		$args[] = $den . ' 23:59:59';
	}
	$sql .= ' LIMIT 20000';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (array) ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ) );
	// phpcs:enable
}

/**
 * PHẠM VI SỔ SAO KÊ MOMO: nó phủ những cơ sở nào, từ ngày nào tới ngày nào.
 *
 * 🔴 KHÔNG CÓ CÁI NÀY THÌ BẢNG ĐỐI SOÁT NÓI DỐI. Sổ MoMo anh Thắng tải về chỉ có mấy quán dùng
 *    mã MoMo riêng (đo trên file thật: 4 quán), còn máy POS thì ghi cả 12 quán. So thẳng hai bên
 *    sẽ đẻ ra hàng nghìn dòng "MoMo thiếu tiền" của những quán vốn dĩ không nằm trong sổ ấy —
 *    người xem đi tìm cả ngày rồi mới biết là hệ đếm nhầm.
 *
 * Nên: chỉ những (cơ sở × ngày) NẰM TRONG sổ mới được đem ra kết luận. Ngoài phạm vi thì đếm
 * riêng, ghi rõ là "chưa có trong sổ MoMo", không gọi là lệch.
 */
function khh_dt_pham_vi_momo_sk( $sk ) {
	$pv = array( 'ngay_dau' => '', 'ngay_cuoi' => '', 'co_so' => array() );
	foreach ( (array) $sk as $r ) {
		$n = substr( (string) $r['ngay'], 0, 10 );
		if ( '' !== $n ) {
			if ( '' === $pv['ngay_dau'] || $n < $pv['ngay_dau'] ) {
				$pv['ngay_dau'] = $n;
			}
			if ( $n > $pv['ngay_cuoi'] ) {
				$pv['ngay_cuoi'] = $n;
			}
		}
		$q = isset( $r['ma_ch'] ) ? khh_dt_ma_ch_toi_co_so( (string) $r['ma_ch'] ) : '';
		if ( '' === $q ) {
			$q = khh_dt_ten_co_so_gan( (string) $r['ten'] );
		}
		if ( '' !== $q ) {
			$pv['co_so'][ $q ] = true;
		}
	}
	return $pv;
}

/**
 * PHẠM VI SỔ MÁY POS: file FABi đã nạp phủ tới ngày nào.
 *
 * 🔴 CÙNG MỘT LÝ DO, NGƯỢC CHIỀU. Hai file hiếm khi cắt cùng một mốc: đo trên hai file thật ngày
 *    16/09/2026 thì sổ MoMo chạy tới 16/09 còn bản xuất FABi dừng ở 15/09. Không chắn lại thì 8
 *    trong 13 dòng lệch là do cái mốc ấy, mà nhóm "MoMo nhận tiền, máy không ghi đơn" lại đúng là
 *    nhóm nặng nhất — bắt người ta đi tìm tám khoản tiền chưa hề thất lạc.
 */
function khh_dt_pham_vi_momo_pos( $pos ) {
	$pv = array( 'ngay_dau' => '', 'ngay_cuoi' => '' );
	foreach ( (array) $pos as $p ) {
		$n = (string) $p['ngay'];
		if ( '' === $n ) {
			continue;
		}
		if ( '' === $pv['ngay_dau'] || $n < $pv['ngay_dau'] ) {
			$pv['ngay_dau'] = $n;
		}
		if ( $n > $pv['ngay_cuoi'] ) {
			$pv['ngay_cuoi'] = $n;
		}
	}
	return $pv;
}

/** Dòng máy POS này có nằm trong phạm vi sổ MoMo không. */
function khh_dt_trong_pham_vi_momo( $p, $pv ) {
	if ( $pv['ngay_dau'] && ( $p['ngay'] < $pv['ngay_dau'] || $p['ngay'] > $pv['ngay_cuoi'] ) ) {
		return false;
	}
	/* Chưa biết sổ phủ quán nào (lần nạp đầu, chưa học được gì) thì đừng loại ai cả. */
	if ( ! $pv['co_so'] ) {
		return true;
	}
	return isset( $pv['co_so'][ (string) $p['cua_hang'] ] );
}

/**
 * Ghép hai bên và kể ra ba nhóm lệch.
 *
 * Ghép hai lượt:
 *   1. THEO MÃ — Mã đối tác của máy POS chính là mã giao dịch MoMo. Chắc chắn, không bàn.
 *   2. THEO NGÀY + SỐ TIỀN + CƠ SỞ — cho những dòng sổ MoMo không có mã, hoặc mã ghi khác kiểu.
 *      Mỗi dòng chỉ được ghép MỘT lần; hai giao dịch cùng ngày cùng số tiền thì ghép lần lượt,
 *      không nhân đôi.
 *
 * ⚠️ GIAO DỊCH LỖI / HUỶ Ở MÁY POS KHÔNG PHẢI "MOMO THIẾU". Cột Trạng thái của file FABi nói rõ;
 *    đếm riêng chúng ra, đừng dồn vào nhóm lệch rồi bắt người ta đi tìm một khoản tiền chưa từng
 *    tồn tại.
 */
function khh_dt_doi_soat_momo_gd( $tu, $den ) {
	return khh_dt_doi_soat_momo_lam( khh_dt_momo_pos_ds( $tu, $den ), khh_dt_momo_sk_ds( $tu, $den ) );
}

/**
 * LÕI GHÉP — nhận hai mảng, trả kết quả. Không đọc database, không in gì.
 *
 * 🔴 TÁCH RA KHỎI `khh_dt_doi_soat_momo_gd()` ĐỂ THỬ ĐƯỢC BẰNG CON SỐ. Đây là hàm quyết định
 *    "giao dịch nào thiếu, giao dịch nào lệch" — tức là hàm kết luận về TIỀN — mà trước 1.30.0
 *    nó không có một phép thử nào: `kiem-momo.php` chỉ canh hai hàm ĐỌC FILE. Muốn thử nó thì
 *    phải dựng cả MySQL, nên trên thực tế là không ai thử.
 *
 * 🔴 VÀ ĐÂY LÀ CHỖ SỬA LỖI 1.30.0: PHẢI GHÉP ĐỦ HAI LƯỢT, THEO ĐÚNG THỨ TỰ.
 *    Chú thích phía trên đã ghi "ghép hai lượt" từ đầu, nhưng mã thì chạy MỘT lượt: với từng
 *    giao dịch POS, thử mã — không thấy thì ghép mờ NGAY. Nên một giao dịch KHÔNG có mã đi
 *    trước chiếm mất đúng dòng sổ mà một giao dịch CÓ MÃ đứng sau cần.
 *
 *    ⚠️ HỎNG NHƯ THẾ NÀO THÌ PHẢI NÓI ĐÚNG — đã đo bằng cách cho hai bản chạy cùng dữ kiện:
 *    khi hai bên có đủ dòng để ghép chéo nhau thì TỔNG vẫn đúng, chỉ khác ở chỗ dòng nào ghép
 *    với dòng nào. Cái giá thật nằm ở ca dưới đây, và nó là ca ĐẮT nhất:
 *
 *        sổ MoMo   mã "M2"  · 12/09 · 71.000
 *        POS #1    không mã · 12/09 · 71.000   -> ghép mờ, ĂN dòng M2 (đúng 71.000, khớp)
 *        POS #2    mã "M2"  · 12/09 · 70.000   -> mã đã bị dùng, ghép mờ không ra -> "máy có,
 *                                                MoMo thiếu"
 *
 *    Kết quả bản cũ: `khớp=1 · lệch=0`. Khoản **lệch 1.000đ trên đúng mã M2 BỊ CHE** — máy ghi
 *    70.000 mà MoMo trả 71.000, đúng thứ cả module này sinh ra để bắt, lại bị kể thành một cặp
 *    "khớp + thiếu" không liên quan. Bản hai lượt trả `khớp=0 · lệch=1`, nêu đúng mã và đúng số.
 *    Trong quán ăn thì cùng ngày · cùng số tiền · cùng quán là chuyện suốt ngày, nên ca này
 *    không hiếm.
 *
 *    Nay: LƯỢT 1 ghép mã cho TẤT CẢ giao dịch POS trước. Chỉ những gì còn lại mới vào LƯỢT 2
 *    ghép mờ — nên ghép mờ không bao giờ tranh được dòng mà mã đã nhận.
 */
function khh_dt_doi_soat_momo_lam( $pos, $sk ) {
	$pos = array_values( (array) $pos );
	$sk  = array_values( (array) $sk );
	$pv  = khh_dt_pham_vi_momo_sk( $sk );
	$pvp = khh_dt_pham_vi_momo_pos( $pos );

	$theo_ma = array();
	foreach ( $sk as $i => $r ) {
		$m = trim( (string) $r['ma'] );
		if ( '' !== $m ) {
			$theo_ma[ $m ][] = $i;
		}
	}
	$da_dung = array();
	$cap     = array();   // chỉ số POS => chỉ số sổ đã ghép
	$con_lai = array();   // chỉ số POS chưa ghép được theo mã
	$khop    = array();
	$chi_pos = array();
	$lech    = array();
	$loi_pos = array();

	/* ── LƯỢT 1: THEO MÃ, cho tất cả giao dịch POS ───────────────────────────────────────── */
	foreach ( $pos as $ip => $p ) {
		$tt = khh_dt_khong_dau( $p['trang_thai'] );
		if ( '' !== $tt && false === strpos( $tt, 'thanh cong' ) && false === strpos( $tt, 'success' ) ) {
			$loi_pos[] = $p;                      // máy ghi nhưng không thành công
			continue;
		}
		$m = trim( (string) $p['ma_doi_tac'] );
		$j = null;
		if ( '' !== $m && isset( $theo_ma[ $m ] ) ) {
			foreach ( $theo_ma[ $m ] as $k ) {
				if ( ! isset( $da_dung[ $k ] ) ) {
					$j = $k;
					break;
				}
			}
		}
		if ( null === $j ) {
			$con_lai[] = $ip;
			continue;
		}
		$da_dung[ $j ] = true;
		$cap[ $ip ]    = $j;
		$pv['co_so'][ (string) $p['cua_hang'] ] = true;   // khớp được tức là quán này CÓ trong sổ
	}

	/* ── LƯỢT 2: GHÉP MỜ cho phần còn lại — cùng ngày, cùng số tiền, cùng cơ sở ──────────── */
	foreach ( $con_lai as $ip ) {
		$p = $pos[ $ip ];
		$j = null;
		foreach ( $sk as $k => $r ) {
			if ( isset( $da_dung[ $k ] ) ) {
				continue;
			}
			if ( substr( (string) $r['ngay'], 0, 10 ) !== $p['ngay'] ) {
				continue;
			}
			if ( abs( khh_dt_so( $r['tien'] ) - (float) $p['so_tien'] ) >= 1 ) {
				continue;
			}
			$ch = khh_dt_ten_co_so_gan( (string) $r['ten'] );
			if ( '' !== $ch && $ch !== $p['cua_hang'] ) {
				continue;
			}
			$j = $k;
			break;
		}
		if ( null === $j ) {
			continue;   // để nguyên; phần kết luận ở dưới gom lại theo đúng thứ tự POS
		}
		$da_dung[ $j ] = true;
		$cap[ $ip ]    = $j;
		$pv['co_so'][ (string) $p['cua_hang'] ] = true;
	}

	/* ── Kết luận, đi theo THỨ TỰ POS để bảng hiện ra vẫn như cũ ─────────────────────────── */
	foreach ( $pos as $ip => $p ) {
		$tt = khh_dt_khong_dau( $p['trang_thai'] );
		if ( '' !== $tt && false === strpos( $tt, 'thanh cong' ) && false === strpos( $tt, 'success' ) ) {
			continue;   // đã đếm ở nhóm lỗi
		}
		if ( ! isset( $cap[ $ip ] ) ) {
			$chi_pos[] = $p;
			continue;
		}
		$tien_sk = khh_dt_so( $sk[ $cap[ $ip ] ]['tien'] );
		if ( abs( $tien_sk - (float) $p['so_tien'] ) >= 1 ) {
			$lech[] = array( 'pos' => $p, 'sk_tien' => $tien_sk );
		} else {
			$khop[] = $p;
		}
	}

	/* Lọc lại sau khi đã khớp xong, vì chính những cặp khớp mã mới nói cho biết sổ phủ quán nào. */
	$ngoai = array();
	$trong = array();
	foreach ( $chi_pos as $p ) {
		if ( khh_dt_trong_pham_vi_momo( $p, $pv ) ) {
			$trong[] = $p;
		} else {
			$ngoai[] = $p;
		}
	}
	$chi_pos = $trong;

	$chi_sk    = array();
	$ngoai_sk  = array();
	foreach ( $sk as $k => $r ) {
		if ( isset( $da_dung[ $k ] ) ) {
			continue;
		}
		$mot = array(
			'ngay' => substr( (string) $r['ngay'], 0, 16 ),
			'tien' => khh_dt_so( $r['tien'] ),
			'ten'  => (string) $r['ten'],
			'ma'   => (string) $r['ma'],
		);
		$n = substr( (string) $r['ngay'], 0, 10 );
		if ( $pvp['ngay_dau'] && ( $n < $pvp['ngay_dau'] || $n > $pvp['ngay_cuoi'] ) ) {
			$ngoai_sk[] = $mot;               // ngày ấy kho POS chưa có số, không phải máy bỏ sót
			continue;
		}
		$chi_sk[] = $mot;
	}

	return array(
		'so_pos'   => count( $pos ),
		'so_sk'    => count( $sk ),
		'so_khop'  => count( $khop ),
		'chi_pos'  => array_slice( $chi_pos, 0, 200 ),
		'so_chi_pos' => count( $chi_pos ),
		'tien_chi_pos' => array_sum( wp_list_pluck( $chi_pos, 'so_tien' ) ),
		'chi_sk'   => array_slice( $chi_sk, 0, 200 ),
		'so_chi_sk' => count( $chi_sk ),
		'tien_chi_sk' => array_sum( wp_list_pluck( $chi_sk, 'tien' ) ),
		'lech'     => array_slice( $lech, 0, 200 ),
		'so_lech'  => count( $lech ),
		'loi_pos'  => array_slice( $loi_pos, 0, 50 ),
		'so_loi_pos' => count( $loi_pos ),
		'ngoai'    => array_slice( $ngoai, 0, 200 ),
		'so_ngoai' => count( $ngoai ),
		'tien_ngoai' => array_sum( wp_list_pluck( $ngoai, 'so_tien' ) ),
		'ngoai_sk' => array_slice( $ngoai_sk, 0, 200 ),
		'so_ngoai_sk' => count( $ngoai_sk ),
		'tien_ngoai_sk' => array_sum( wp_list_pluck( $ngoai_sk, 'tien' ) ),
		'pham_vi'  => array(
			'ngay_dau'  => $pv['ngay_dau'],
			'ngay_cuoi' => $pv['ngay_cuoi'],
			'co_so'     => array_keys( $pv['co_so'] ),
			'pos_dau'   => $pvp['ngay_dau'],
			'pos_cuoi'  => $pvp['ngay_cuoi'],
		),
	);
}

/**
 * HỌC BẢNG GHÉP "tên cửa hàng bên MoMo ↔ cơ sở FABi" từ những cặp đã khớp mã.
 *
 * 🔴 ĐÂY LÀ CÁCH HỆ "TỰ RÀ" KHI MÁY DỜI CƠ SỞ. Không khai tay bảng nào cả: cứ nạp lại file MoMo
 *    của FABi là những cặp khớp mã dạy lại cho hệ biết tên bên MoMo giờ thuộc quán nào.
 *
 * ⚠️ CHỈ HỌC KHI MỘT TÊN CHỈ TRỎ VỀ MỘT QUÁN trong kỳ vừa nạp. Tên nào trỏ về hai quán khác nhau
 *    (đúng cảnh dời máy giữa kỳ) thì KHÔNG học — vì học cái nào cũng sai một nửa. Lúc ấy giao dịch
 *    đã khớp mã vẫn đúng cơ sở theo file, chỉ những dòng sổ MoMo lẻ là chưa biết đường về, và
 *    chúng nằm sẵn ở nhóm "chỉ có bên MoMo" cho người nhìn.
 */
function khh_dt_hoc_ghep_momo( $tu, $den ) {
	$pos = khh_dt_momo_pos_ds( $tu, $den );
	$sk  = khh_dt_momo_sk_ds( $tu, $den );
	if ( ! $pos || ! $sk ) {
		return array( 'hoc' => 0, 'lan_can' => array() );
	}
	$quan_theo_ma = array();
	foreach ( $pos as $p ) {
		$m = trim( (string) $p['ma_doi_tac'] );
		if ( '' !== $m ) {
			$quan_theo_ma[ $m ] = (string) $p['cua_hang'];
		}
	}
	$ten_toi_quan = array();
	foreach ( $sk as $r ) {
		$m = trim( (string) $r['ma'] );
		$t = trim( (string) $r['ten'] );
		if ( '' === $m || '' === $t || ! isset( $quan_theo_ma[ $m ] ) ) {
			continue;
		}
		$ten_toi_quan[ $t ][ $quan_theo_ma[ $m ] ] = true;
	}
	$hoc     = get_option( 'khh_dt_ghep_momo_ten', array() );
	$hoc     = is_array( $hoc ) ? $hoc : array();
	$so      = 0;
	$lan_can = array();
	foreach ( $ten_toi_quan as $ten => $quan ) {
		if ( count( $quan ) > 1 ) {
			$lan_can[] = $ten;                    // một tên, hai quán — không học, xem khối chú thích
			continue;
		}
		$q = key( $quan );
		if ( ! isset( $hoc[ $ten ] ) || $hoc[ $ten ] !== $q ) {
			$hoc[ $ten ] = $q;
			$so++;
		}
	}
	update_option( 'khh_dt_ghep_momo_ten', $hoc, false );
	return array( 'hoc' => $so, 'lan_can' => $lan_can );
}

/** Tên bên MoMo -> cơ sở FABi, theo bảng đã học. */
function khh_dt_ghep_momo_hoc( $ten ) {
	$hoc = get_option( 'khh_dt_ghep_momo_ten', array() );
	$ten = trim( (string) $ten );
	return ( is_array( $hoc ) && isset( $hoc[ $ten ] ) ) ? (string) $hoc[ $ten ] : '';
}

/* ================================================================== *
 * TỰ HÚT FILE MOMO ĐÃ CÓ SẴN TRÊN MÁY CHỦ
 *
 * 🔴 ANH THẮNG HỎI ĐÚNG: "NẠP TRÊN SAO KÊ RỒI, SAO PHẢI NẠP LẠI LẦN 2?"
 *
 *    Không phải. Plugin Sao Kê nhận file `Transaction_report_….csv` rồi CHỈ GIỮ BẢN GỘP theo
 *    ngày — nó vứt từng giao dịch đi, nên bên này không có gì để đối soát tới từng mã. Nhưng nếu
 *    file gốc còn nằm đâu đó trong thư mục tải lên của WordPress thì lấy về là xong, không phải
 *    bắt người ta tải lên lần nữa cùng một file.
 *
 *    Nên: quét thư mục uploads tìm file mang tên Transaction_report…, bày ra, bấm một nút là nạp
 *    hết. Nạp lại đúng file cũ cũng không sao — khoá theo mã giao dịch nên ghi đè, không cộng dồn.
 *
 * ⚠️ ĐƯỜNG DẪN KHÔNG BAO GIỜ NHẬN TỪ TRÌNH DUYỆT. Client chỉ gửi tên file; máy chủ tự đối chiếu
 *    lại với danh sách vừa quét rồi mới đọc. Nhận thẳng đường dẫn là mở cửa cho người ta đọc bất
 *    kỳ file nào trên máy chủ.
 * ================================================================== */

/** Các file MoMo còn nằm trong thư mục tải lên của WordPress. */
function khh_dt_file_momo_san() {
	$goc = wp_upload_dir();
	$goc = isset( $goc['basedir'] ) ? (string) $goc['basedir'] : '';
	if ( '' === $goc || ! is_dir( $goc ) ) {
		return array();
	}
	$ra = array();
	try {
		$di = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $goc, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $di as $f ) {
			if ( count( $ra ) >= 200 ) {
				break;
			}
			$ten = $f->getFilename();
			if ( ! preg_match( '/^transaction[_\- ]?report.*\.csv$/i', $ten ) ) {
				continue;
			}
			$ra[] = array(
				'ten'  => $ten,
				'co'   => (int) $f->getSize(),
				'luc'  => gmdate( 'Y-m-d H:i', (int) $f->getMTime() ),
				'duong' => $f->getPathname(),
			);
		}
	} catch ( Exception $e ) {
		return array();
	}
	usort(
		$ra,
		function ( $a, $b ) {
			return strcmp( $b['luc'], $a['luc'] );
		}
	);
	return $ra;
}

/**
 * Nạp những file MoMo có sẵn trên máy chủ vào kho.
 *
 * `$ten_ds` là TÊN file, không phải đường dẫn — xem lời cảnh báo ở đầu khối. Rỗng thì nạp hết.
 */
function khh_dt_hut_file_momo( $ten_ds = array() ) {
	$san  = khh_dt_file_momo_san();
	$loc  = array_map( 'strval', (array) $ten_ds );
	$ghi  = 0;
	$doc  = 0;
	$xong = array();
	$hong = array();
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	foreach ( $san as $f ) {
		if ( $loc && ! in_array( $f['ten'], $loc, true ) ) {
			continue;
		}
		$kq = khh_dt_doc_momo_sk( $f['duong'] );
		if ( is_wp_error( $kq ) ) {
			$hong[] = array( 'ten' => $f['ten'], 'vi' => $kq->get_error_message() );
			continue;
		}
		$n     = khh_dt_ghi_momo_sk( $kq['dong'] );
		$ghi  += $n;
		$doc  += count( $kq['dong'] );
		$xong[] = array( 'ten' => $f['ten'], 'so' => count( $kq['dong'] ) );
	}
	$hoc = khh_dt_hoc_ma_ch_momo();
	return array(
		'so_file' => count( $xong ),
		'da_ghi'  => $ghi,
		'da_doc'  => $doc,
		'xong'    => $xong,
		'hong'    => $hong,
		'hoc'     => (int) $hoc['hoc'],
		'lan_can' => $hoc['lan_can'],
	);
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_rest_momo' );
function khh_dt_rest_momo() {
	register_rest_route(
		'khh-dt/v1',
		'/momo-file',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_momo_file',
			'permission_callback' => 'khh_dt_duoc_nap',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/momo-hut',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_momo_hut',
			'permission_callback' => 'khh_dt_duoc_nap',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/momo-gd',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_momo_gd',
			'permission_callback' => 'khh_dt_duoc_xem',
		)
	);
}

function khh_dt_rest_momo_file() {
	$ds = khh_dt_file_momo_san();
	foreach ( $ds as $i => $f ) {
		unset( $ds[ $i ]['duong'] );        // đường dẫn thật không cần ra tới trình duyệt
	}
	return array( 'file_ds' => array_values( $ds ), 'da_co' => (int) khh_dt_co_momo_sk() );
}

function khh_dt_rest_momo_hut( $req ) {
	$ten = $req->get_param( 'ten' );
	if ( is_string( $ten ) ) {
		$ten = json_decode( $ten, true );
	}
	return khh_dt_hut_file_momo( is_array( $ten ) ? $ten : array() );
}

function khh_dt_rest_momo_gd( $req ) {
	$tu  = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'tu' ) );
	$den = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'den' ) );
	/* 🔴 SỔ ĐÃ GỘP THEO NGÀY THÌ KHÔNG ĐỐI SOÁT TỪNG GIAO DỊCH ĐƯỢC — và phải nói ra chứ không
	   được cứ thế chạy. Mỗi dòng ở đó là cả một ngày của một quán; đem 188 dòng gộp so với mấy
	   nghìn giao dịch của máy POS thì ra một bảng lệch toàn phần, người đọc tưởng mất tiền thật. */
	if ( ! khh_dt_co_momo_sk() && function_exists( 'khh_dt_momo_la_so_gop' ) && khh_dt_momo_la_so_gop() ) {
		$n = khh_dt_nguon_momo();
		return array(
			'co_pos'  => (bool) khh_dt_co_momo_pos(),
			'co_sk'   => true,
			'sk_gop'  => true,
			'sk_bang' => (string) $n['bang'],
		);
	}
	$ra  = khh_dt_doi_soat_momo_gd( $tu, $den );
	$ra['co_pos']  = (bool) khh_dt_co_momo_pos();
	/* Sổ MoMo có thể là file MoMo đã nạp, hoặc bảng ngoài đã khai — cái nào cũng tính. */
	$ra['co_sk']   = khh_dt_co_momo_sk() ? true : (bool) khh_dt_nguon_momo();
	$ra['sk_file'] = (bool) khh_dt_co_momo_sk();
	return $ra;
}
