<?php
/**
 * ĐỌC BÁO CÁO "TỔNG HỢP MÓN ĂN BÁN" CỦA HỆ BES (BesReportViewer).
 *
 * 18/09/2026, anh Thắng: *"Anh đang có thêm 1 cửa hàng đang dùng hệ thống khác, cơ sở nó không
 * có trong FABi, anh cần đẩy vào nó"*. Cửa hàng ấy chạy Bes, không chạy FABi.
 *
 * Danh sách cơ sở của plugin này là `SELECT DISTINCT cua_hang` từ chính bảng số liệu (xem
 * `khh_dt_ds_cua_hang()`), nên KHÔNG phải khai cơ sở ở đâu cả — nạp được số là cơ sở tự hiện.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BẪY LỚN NHẤT: HÀNG NHÓM LÀ TỔNG CON, CỘNG VÀO LÀ NHÂN ĐÔI DOANH THU
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Báo cáo xen hai loại hàng vào cùng một bảng:
 *
 *     ĐỒ UỐNG - THÀNH PHẨM          12      115.000   <- HÀNG NHÓM: tổng con của cả nhóm
 *     MNKVCDU019  TRÀ CHANH GIÃ TAY  1       35.000   <- hàng món
 *     MNKVCDU026  TRÀ SỮA OLONG      1       40.000
 *     MNKVCDU031  TRÀ CHANH ỔI HỒNG  1       40.000
 *
 * Cộng hết mọi hàng thì ra 11.940.000đ trong khi cửa hàng bán 5.970.000đ — GẤP ĐÔI, và gấp đôi
 * một cách trông rất hợp lý vì không dòng nào âm, không dòng nào lạ. Đã đo trên file thật.
 *
 * Phân biệt bằng CỘT TÊN HÀNG: hàng nhóm có mã (cột 1) mà KHÔNG có tên hàng (cột 2); hàng món
 * có cả hai. Dòng "Tổng" ở cuối cũng rơi vào luật ấy nên cũng bị bỏ — và đó là ý muốn.
 *
 * 🔴 VÀ CHỐT LẠI BẰNG CHÍNH DÒNG "TỔNG" CỦA BÁO CÁO. Cộng xong, đem so với con số Bes tự in ra.
 *    Lệch là CHỐI, không nạp. Đây là thứ đắt nhất mà bộ đọc này có: mọi kiểu đọc sai — thêm
 *    hàng nhóm, bỏ sót một nhóm, lấy lệch cột — đều làm hai con số rời nhau. Không có chốt ấy
 *    thì một lần Bes đổi thứ tự cột là doanh thu vào sổ sai mà không ai biết.
 *
 * ⚠️ BÁO CÁO NÀY KHÔNG CÓ HÌNH THỨC THANH TOÁN. Nên `pttt` để RỖNG, không đoán. Nghĩa là cơ sở
 *    này có doanh thu trong hệ nhưng KHÔNG đối soát được tiền mặt / CK / MoMo — phần ấy phải
 *    nhập tay ở màn "Nhập báo cáo ngày", hoặc đợi Bes cho một báo cáo có cột thanh toán.
 *    Điền bừa `pttt` (kiểu "coi hết là tiền mặt") thì đối soát sẽ tố cửa hàng giữ tiền.
 */

defined( 'ABSPATH' ) || exit;

/** Nhận mặt file Bes: có dòng tiêu đề "TỔNG HỢP MÓN ĂN BÁN". */
function khh_dt_la_file_bes( $duong_dan ) {
	$f = fopen( $duong_dan, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $f ) {
		return false;
	}
	$dau = (string) fread( $f, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$k = khh_dt_khong_dau( $dau );
	return false !== strpos( $k, 'tong hop mon an ban' ) || false !== strpos( $k, 'besreportviewer' );
}

/** Tên cột của báo cáo Bes, so khớp không dấu. */
function khh_dt_cot_bes() {
	return array(
		'ma_hang'    => array( 'ma hang' ),
		'ten_hang'   => array( 'ten hang' ),
		'so_luong'   => array( 'so luong' ),
		'tong_truoc' => array( 'tong tien truoc giam gia' ),
		'giam_gia'   => array( 'tien giam gia' ),
		'chiet_khau' => array( 'tien chiet khau' ),
		'thanh_tien' => array( 'thanh tien' ),
	);
}

/**
 * Đọc file .csv Bes xuất ra.
 *
 * @param string $duong_dan Đường dẫn file.
 * @param string $ten_co_so Tên cơ sở muốn ghi vào sổ. Rỗng thì lấy tên ở dòng đầu file.
 * @return array|WP_Error
 */
function khh_dt_doc_bes( $duong_dan, $ten_co_so = '' ) {
	$f = fopen( $duong_dan, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $f ) {
		return new WP_Error( 'khh_dt_bes', 'Không mở được file.' );
	}
	$bom = fread( $f, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( "\xEF\xBB\xBF" !== $bom ) {
		rewind( $f );
	}

	$hang = array();
	while ( false !== ( $d = fgetcsv( $f, 0, ',', '"', '\\' ) ) ) {
		if ( null === $d || ( 1 === count( $d ) && null === $d[0] ) ) {
			continue;
		}
		$hang[] = $d;
	}
	fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	/* Tên cửa hàng: ô đầu tiên có chữ, trước cả dòng tiêu đề bảng. */
	$ten_file = '';
	foreach ( $hang as $d ) {
		$v = trim( (string) ( isset( $d[0] ) ? $d[0] : '' ) );
		if ( '' !== $v ) {
			$ten_file = $v;
			break;
		}
	}

	/* Dò dòng tiêu đề bảng. */
	$map = null;
	$i_hdr = -1;
	foreach ( $hang as $i => $d ) {
		$kd = array_map( 'khh_dt_khong_dau', $d );
		if ( ! in_array( 'ma hang', $kd, true ) || ! in_array( 'ten hang', $kd, true ) ) {
			continue;
		}
		$map = array();
		foreach ( khh_dt_cot_bes() as $vai => $ten_ds ) {
			$map[ $vai ] = -1;
			foreach ( $ten_ds as $t ) {
				$k = array_search( $t, $kd, true );
				if ( false !== $k ) {
					$map[ $vai ] = $k;
					break;
				}
			}
		}
		$i_hdr = $i;
		break;
	}
	if ( null === $map || $map['ma_hang'] < 0 || $map['ten_hang'] < 0 || $map['thanh_tien'] < 0 ) {
		return new WP_Error(
			'khh_dt_bes',
			'Không thấy dòng tên cột của báo cáo Bes (cần "Mã hàng", "Tên hàng", "Thành tiền").'
		);
	}

	/* Ngày: Bes in ở chân trang, kiểu ", Ngày 17 Tháng 9 Năm 2026 20:58:05". */
	$ngay = '';
	foreach ( $hang as $d ) {
		foreach ( (array) $d as $o ) {
			if ( preg_match( '~Ngày\s+(\d{1,2})\s+Tháng\s+(\d{1,2})\s+Năm\s+(\d{4})~u', (string) $o, $m ) ) {
				$ngay = sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
				break 2;
			}
		}
	}
	if ( '' === $ngay ) {
		return new WP_Error(
			'khh_dt_bes',
			'Không đọc được ngày trong file. Báo cáo Bes in ngày ở chân trang ("Ngày 17 Tháng 9 Năm 2026") — '
			. 'anh xuất lại kèm chân trang giúp em.'
		);
	}

	$o = function ( $d, $vai ) use ( $map ) {
		return ( $map[ $vai ] > -1 && isset( $d[ $map[ $vai ] ] ) ) ? trim( (string) $d[ $map[ $vai ] ] ) : '';
	};

	$tt = 0.0;
	$dt = 0.0;
	$ck = 0.0;
	$sl = 0.0;
	$ve = 0.0;
	$mon = array();
	$tong_bao_cao = null;
	$nhom_nay = '';

	foreach ( array_slice( $hang, $i_hdr + 1 ) as $d ) {
		$ma  = $o( $d, 'ma_hang' );
		$ten = $o( $d, 'ten_hang' );

		/* Dòng "Tổng" của chính báo cáo — giữ lại để chốt, không cộng vào. */
		if ( '' === $ten && 'tong' === khh_dt_khong_dau( $ma ) ) {
			$tong_bao_cao = khh_dt_so( $o( $d, 'thanh_tien' ) );
			continue;
		}
		/* Hàng NHÓM: có mã, không có tên hàng. Là TỔNG CON — bỏ, nếu không thì nhân đôi. */
		if ( '' === $ten ) {
			if ( '' !== $ma ) {
				$nhom_nay = $ma;
			}
			continue;
		}
		if ( '' === $ma ) {
			continue;
		}

		$q   = khh_dt_so( $o( $d, 'so_luong' ) );
		$r   = khh_dt_so( $o( $d, 'thanh_tien' ) );
		$tt += $r;
		$dt += khh_dt_so( $o( $d, 'tong_truoc' ) );
		$ck += khh_dt_so( $o( $d, 'chiet_khau' ) ) + khh_dt_so( $o( $d, 'giam_gia' ) );
		$sl += $q;
		/* Vé: nhận theo tên NHÓM, vì tên món thì muôn hình (VÉ COMBO, VÉ LẺ, VÉ ONLINE…). */
		if ( 0 === strpos( khh_dt_khong_dau( $nhom_nay ), 've' ) ) {
			$ve += $q;
		}
		$mon[] = array( 'n' => $ten, 'g' => $nhom_nay, 'q' => $q, 'r' => $r );
	}

	/* 🔴 CHỐT: số mình cộng phải khớp số Bes tự in. Lệch là chối, không nạp. */
	if ( null === $tong_bao_cao ) {
		return new WP_Error(
			'khh_dt_bes',
			'File không có dòng "Tổng" để đối chiếu. Bộ đọc này chỉ nạp khi cộng được và khớp với '
			. 'tổng do Bes tự in — thiếu nó thì không có gì chặn một lượt đọc lệch cột.'
		);
	}
	if ( abs( $tt - $tong_bao_cao ) > 1 ) {
		return new WP_Error(
			'khh_dt_bes',
			sprintf(
				'KHÔNG nạp: cộng các dòng món ra %s đ nhưng dòng "Tổng" của báo cáo ghi %s đ. '
				. 'Hai số phải khớp mới nạp — lệch nghĩa là bộ đọc hiểu sai cấu trúc file, và nạp '
				. 'tiếp là ghi sai doanh thu vào sổ.',
				number_format_i18n( $tt ),
				number_format_i18n( $tong_bao_cao )
			)
		);
	}

	if ( ! $mon ) {
		return new WP_Error( 'khh_dt_bes', 'Không đọc được dòng món nào.' );
	}

	$ten_ch = '' !== trim( $ten_co_so ) ? trim( $ten_co_so ) : $ten_file;
	if ( '' === $ten_ch ) {
		return new WP_Error( 'khh_dt_bes', 'Không biết ghi cho cơ sở nào — file không có tên cửa hàng ở dòng đầu.' );
	}

	/* Xếp món theo tiền giảm dần, giữ 40 món như đường FABi. */
	usort( $mon, function ( $a, $b ) {
		return ( $b['r'] == $a['r'] ) ? 0 : ( ( $b['r'] < $a['r'] ) ? -1 : 1 ); // phpcs:ignore Universal.Operators.StrictComparisons
	} );
	$mon = array_slice( $mon, 0, 40 );

	return array(
		'dong' => array(
			array(
				'ngay'       => $ngay,
				'cua_hang'   => $ten_ch,
				'pos_id'     => 'bes',
				'doanh_thu'  => $dt > 0 ? $dt : $tt,
				'chiet_khau' => $ck,
				'thanh_tien' => $tt,
				'so_hd'      => 0,   // báo cáo theo món, không có mã hoá đơn
				'so_mon'     => $sl,
				'so_ve'      => $ve,
				'gio'        => array(),
				'pttt'       => array(), // 🔴 để RỖNG, xem chú thích đầu tệp
				'nguon'      => array( 'Bes' => $tt ),
				'mon'        => $mon,
			),
		),
		'ngay'       => $ngay,
		'cua_hang'   => $ten_ch,
		'ten_file'   => $ten_file,
		'thanh_tien' => $tt,
		'so_mon'     => $sl,
		'so_ve'      => $ve,
	);
}
