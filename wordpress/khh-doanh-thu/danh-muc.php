<?php
/**
 * DANH MỤC HÀNG HOÁ FABi — NẠP TỪ FILE "DANH SÁCH HÀNG HOÁ", DÙNG CHUNG CẢ TRANG.
 *
 * Anh Thắng 25/09/2026, ảnh ô chọn combo của sổ kho: *"Tạo hàng mới trên FABi mà chưa bán, thành ra doanh thu nó
 * không có hàng đó để nhập kho. Tạo thêm tải danh sách hàng hoá xuống. Mục đích các bạn là muốn set trước, nên
 * thấy không có loại vé đó nên tạo trước, nên dẫn tới dễ sai lệch."*
 *
 * 🔴 CẢ TRANG TỪNG CHỈ BIẾT MÓN ĐÃ BÁN. Mọi danh sách để chọn (mặt hàng có kho, combo đang bán, vé để bóc tách,
 *    mã hàng cho MISA) đều gom từ cột "mon" của báo cáo bán hàng đã nạp — nên hàng mới tạo trên FABi mà chưa bán
 *    cái nào thì không có ở đâu để chọn. Nhân viên muốn khai trước đành GÕ TAY, và tên gõ tay lệch một chữ, một
 *    dấu cách là combo bán ra không trừ dòng nào, vé không đếm được khách.
 *    FABi có sẵn bản xuất "Danh sách hàng hoá" (mã hàng, tên, nhóm, loại, đơn vị, giá). Nạp bản ấy vào đây một
 *    lần là mọi chỗ chọn có đủ tên ĐÚNG NHƯ FABi, kèm mã — khai trước được, không phải gõ.
 *
 * Lưu ở một option (vài trăm dòng, mỗi dòng sáu ô); nạp lại là thay cả bản. Không đụng danh mục kho của từng
 * quán (`khh_dt_kho_mat_hang`) — đó là "quán này đếm món nào", còn đây là "FABi có món nào".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KHH_DT_DM_OPT = 'khh_dt_dm_hang';

/** [ 'ds' => [ {ma, ten, nhom, loai, dvt, gia} ], 'luc', 'nguoi', 'nguon', 'so' ] — rỗng khi chưa nạp. */
function khh_dt_dm_goi() {
	$x = get_option( KHH_DT_DM_OPT, array() );
	if ( ! is_array( $x ) || empty( $x['ds'] ) || ! is_array( $x['ds'] ) ) {
		return array( 'ds' => array(), 'luc' => '', 'nguoi' => '', 'nguon' => '', 'so' => 0 );
	}
	return array(
		'ds'    => $x['ds'],
		'luc'   => isset( $x['luc'] ) ? (string) $x['luc'] : '',
		'nguoi' => isset( $x['nguoi'] ) ? (string) $x['nguoi'] : '',
		'nguon' => isset( $x['nguon'] ) ? (string) $x['nguon'] : '',
		'so'    => count( $x['ds'] ),
	);
}

function khh_dt_dm_ds() {
	return khh_dt_dm_goi()['ds'];
}

function khh_dt_dm_long( $t ) {
	$t = preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $t );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $t ), 'UTF-8' ) : strtolower( trim( $t ) );
}

/** Món này là VÉ (theo cột Loại món, không có thì theo tên bắt đầu bằng VÉ/COMBO). */
function khh_dt_dm_la_ve( $m ) {
	$l = isset( $m['loai'] ) ? trim( (string) $m['loai'] ) : '';
	if ( '' !== $l ) {
		$kd = khh_dt_khong_dau( $l );
		return 0 === strpos( $kd, 've' ) || false !== strpos( $kd, 'combo' );
	}
	return (bool) preg_match( '/^\s*(vé|ve|combo)\b/iu', isset( $m['ten'] ) ? (string) $m['ten'] : '' );
}

/** Món này là COMBO (tên hay nhóm có chữ combo). */
function khh_dt_dm_la_combo( $m ) {
	return (bool) preg_match( '/combo/iu', ( isset( $m['ten'] ) ? $m['ten'] : '' ) . ' ' . ( isset( $m['nhom'] ) ? $m['nhom'] : '' ) );
}

/**
 * Dò cột của bản "Danh sách hàng hoá" FABi (bản xuất "update item in store": ID · Mã món · Thành phố · Cửa hàng ·
 * Tên · Giá · Trạng thái · … · Đơn vị · Nhóm (mã) · Tên nhóm · Loại món (mã) · Tên loại · … · SKU).
 * "Nhóm" và "Loại món" ở bản này là MÃ (MNKVCVECOMBO, ITEM_CLASS-T7HK) — tên người đọc nằm ở "Tên nhóm" / "Tên loại",
 * nên ưu tiên hai cột ấy. Khớp đúng tên cột (không dấu), theo thứ tự ưu tiên.
 *
 * @return array|null [ khoá => chỉ số cột ] hay null nếu không phải dòng tên cột (phải có Tên).
 */
function khh_dt_dm_do_cot( $dong ) {
	$n   = array_map( 'khh_dt_khong_dau', $dong );
	$mau = array(
		'ten'      => array( 'ten', 'ten hang', 'ten mon', 'ten san pham', 'ten hang hoa' ),
		'ma'       => array( 'ma mon', 'ma hang', 'ma san pham', 'sku' ),
		'cua_hang' => array( 'cua hang', 'chi nhanh' ),
		'nhom'     => array( 'ten nhom', 'nhom mon', 'nhom hang', 'danh muc' ),
		'loai'     => array( 'ten loai', 'loai mon' ),
		'dvt'      => array( 'don vi tinh', 'dvt', 'don vi' ),
		'gia'      => array( 'gia ban', 'gia', 'don gia' ),
		'trang_thai' => array( 'trang thai' ),
	);
	$map = array();
	foreach ( $mau as $k => $ds ) {
		$map[ $k ] = -1;
		foreach ( $ds as $p ) {
			$i = array_search( $p, $n, true );
			if ( false !== $i ) {
				$map[ $k ] = (int) $i;
				break;
			}
		}
	}
	return $map['ten'] > -1 && ( $map['ma'] > -1 || $map['nhom'] > -1 || $map['gia'] > -1 ) ? $map : null;
}

/**
 * Đọc file "Danh sách hàng hoá" của FABi (.xlsx / .csv / .tsv). Dòng tên cột tự dò (xem `khh_dt_dm_do_cot`).
 * Cùng một Mã món có thể xuất hiện ở nhiều quán (mỗi quán một menu) — gộp về MỘT dòng, kèm danh sách quán `cs`.
 *
 * @return array|WP_Error [ {ma, ten, nhom, loai, dvt, gia, tt, cs:[quán…]} ]
 */
function khh_dt_dm_phan_tich( $duong_dan, $ten_file = '' ) {
	$duoi = strtolower( pathinfo( $ten_file ? $ten_file : $duong_dan, PATHINFO_EXTENSION ) );
	$map  = null;
	$hang = 0;
	$ds   = array();
	$vi   = array();
	$xu_ly = function ( $dong ) use ( &$map, &$hang, &$ds, &$vi ) {
		$hang++;
		if ( null === $map ) {
			$thu = khh_dt_dm_do_cot( $dong );
			if ( $thu ) {
				$map = $thu;
			} elseif ( $hang > 12 ) {
				$map = false;
			}
			return;
		}
		if ( false === $map ) {
			return;
		}
		$lay = function ( $k ) use ( $dong, $map ) {
			return ( $map[ $k ] > -1 && isset( $dong[ $map[ $k ] ] ) ) ? trim( (string) $dong[ $map[ $k ] ] ) : '';
		};
		$ten = $lay( 'ten' );
		if ( '' === $ten || '-' === $ten ) {
			return;
		}
		$ma   = $lay( 'ma' );
		$ma   = ( '-' === $ma ) ? '' : preg_replace( '/\s+/', '', $ma );
		$cs   = $lay( 'cua_hang' );
		$khoa = '' !== $ma ? 'm:' . strtoupper( $ma ) : 't:' . khh_dt_dm_long( $ten );
		if ( isset( $vi[ $khoa ] ) ) {
			if ( '' !== $cs && ! in_array( $cs, $ds[ $vi[ $khoa ] ]['cs'], true ) ) {
				$ds[ $vi[ $khoa ] ]['cs'][] = $cs;
			}
			return;
		}
		$vi[ $khoa ] = count( $ds );
		$gia  = $lay( 'gia' );
		$ds[] = array(
			'ma'   => $ma,
			'ten'  => $ten,
			'nhom' => $lay( 'nhom' ),
			'loai' => $lay( 'loai' ),
			'dvt'  => $lay( 'dvt' ),
			'gia'  => '' !== $gia ? (float) khh_dt_so( $gia ) : 0.0,
			'tt'   => $lay( 'trang_thai' ),
			'cs'   => '' !== $cs ? array( $cs ) : array(),
		);
	};

	if ( 'csv' === $duoi || 'tsv' === $duoi || 'txt' === $duoi ) {
		$f = fopen( $duong_dan, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $f ) {
			return new WP_Error( 'khh_dt_file', 'Không mở được file.' );
		}
		$dau = ( 'tsv' === $duoi ) ? "\t" : ',';
		$bom = fread( $f, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $f );
		}
		while ( false !== ( $d = fgetcsv( $f, 0, $dau, '"', '\\' ) ) ) {
			if ( null === $d || ( 1 === count( $d ) && null === $d[0] ) ) {
				continue;
			}
			$xu_ly( $d );
		}
		fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	} else {
		if ( ! khh_dt_doc_duoc_xlsx() ) {
			return new WP_Error( 'khh_dt_zip', 'Máy chủ thiếu ZipArchive hoặc XMLReader nên chưa đọc được .xlsx. Anh xuất bản CSV từ FABi rồi nạp lại.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $duong_dan ) ) {
			return new WP_Error( 'khh_dt_zip', 'File .xlsx hỏng hoặc không mở được.' );
		}
		$trang = khh_dt_ds_trang( $zip );
		if ( ! $trang ) {
			$zip->close();
			return new WP_Error( 'khh_dt_sheet', 'Không đọc được trang tính trong file.' );
		}
		/* Bản FABi có trang "Menu" (dữ liệu) và "Template" (mẫu tham khảo) — lấy trang đầu, bỏ trang mẫu. */
		$chon = $trang[0];
		foreach ( $trang as $t ) {
			if ( 'menu' === khh_dt_khong_dau( $t['ten'] ) ) {
				$chon = $t;
				break;
			}
		}
		$chuoi = khh_dt_chuoi_chung( $zip );
		$tam   = khh_dt_rut_file( $zip, $chon['file'] );
		$zip->close();
		if ( ! $tam ) {
			return new WP_Error( 'khh_dt_sheet', 'Không rút được trang tính ra khỏi file nén.' );
		}
		khh_dt_doc_trang( $tam, $chuoi, $xu_ly );
		wp_delete_file( $tam );
	}
	if ( ! $map ) {
		return new WP_Error( 'khh_dt_cot', 'Không tìm thấy dòng tên cột (cần cột "Tên" hay "Tên hàng" kèm "Mã món"). Anh xuất bản "Danh sách hàng hoá" của FABi giúp em.' );
	}
	if ( ! $ds ) {
		return new WP_Error( 'khh_dt_rong', 'File không có dòng hàng hoá nào.' );
	}
	return $ds;
}

/** Nạp file vào option. Trả về tóm tắt { so, moi, mat, luc } hay WP_Error. */
function khh_dt_dm_nap( $duong_dan, $ten_file = '' ) {
	$ds = khh_dt_dm_phan_tich( $duong_dan, $ten_file );
	if ( is_wp_error( $ds ) ) {
		return $ds;
	}
	$cu    = khh_dt_dm_ds();
	$khoa  = function ( $m ) {
		return '' !== $m['ma'] ? 'm:' . strtoupper( $m['ma'] ) : 't:' . khh_dt_dm_long( $m['ten'] );
	};
	$cu_k  = array();
	foreach ( $cu as $m ) {
		$cu_k[ $khoa( $m ) ] = true;
	}
	$moi_k = array();
	$moi   = 0;
	foreach ( $ds as $m ) {
		$k = $khoa( $m );
		$moi_k[ $k ] = true;
		if ( ! isset( $cu_k[ $k ] ) ) {
			$moi++;
		}
	}
	$mat = 0;
	foreach ( array_keys( $cu_k ) as $k ) {
		if ( ! isset( $moi_k[ $k ] ) ) {
			$mat++;
		}
	}
	$goi = array(
		'ds'    => $ds,
		'luc'   => current_time( 'mysql' ),
		'nguoi' => function_exists( 'khh_dt_ten_ghi_so' ) ? khh_dt_ten_ghi_so() : '',
		'nguon' => sanitize_file_name( (string) $ten_file ),
	);
	update_option( KHH_DT_DM_OPT, $goi, false );
	return array( 'so' => count( $ds ), 'moi' => $moi, 'mat' => $mat, 'luc' => $goi['luc'] );
}

function khh_dt_dm_xoa() {
	delete_option( KHH_DT_DM_OPT );
}

/** [ tên lỏng => mã FABi ] — cho MISA và sổ kho nhận mã của món chưa bán. */
function khh_dt_dm_ma_bang() {
	$ra = array();
	foreach ( khh_dt_dm_ds() as $m ) {
		if ( '' !== $m['ma'] && '' !== trim( $m['ten'] ) ) {
			$ra[ khh_dt_dm_long( $m['ten'] ) ] = $m['ma'];
		}
	}
	return $ra;
}

/** Tra một tên trong danh mục (lỏng) — dòng hay null. */
function khh_dt_dm_tra( $ten ) {
	$k = khh_dt_dm_long( $ten );
	if ( '' === $k ) {
		return null;
	}
	foreach ( khh_dt_dm_ds() as $m ) {
		if ( khh_dt_dm_long( $m['ten'] ) === $k ) {
			return $m;
		}
	}
	return null;
}

/** Món này có ở quán `$cua_hang` không — file có cột Cửa hàng thì so lỏng; không có thì coi như mọi quán. */
function khh_dt_dm_thuoc_quan( $m, $cua_hang ) {
	$cua_hang = trim( (string) $cua_hang );
	if ( '' === $cua_hang || empty( $m['cs'] ) || ! is_array( $m['cs'] ) ) {
		return true;
	}
	$k = khh_dt_dm_long( $cua_hang );
	foreach ( $m['cs'] as $c ) {
		if ( khh_dt_dm_long( $c ) === $k ) {
			return true;
		}
	}
	return false;
}

/** Vé (kể cả combo vé) trong danh mục — cho khối Bóc tách vé bày cả vé chưa bán. Có quán thì chỉ vé của quán ấy. */
function khh_dt_dm_ve_ds( $cua_hang = '' ) {
	return array_values(
		array_filter(
			khh_dt_dm_ds(),
			function ( $m ) use ( $cua_hang ) {
				return khh_dt_dm_la_ve( $m ) && khh_dt_dm_thuoc_quan( $m, $cua_hang );
			}
		)
	);
}

/** Bản gọn cho tab Kho: [ {ten, ma, nhom, loai, ve, combo} ] của quán (hay mọi quán), vé/combo xếp sau hàng. */
function khh_dt_dm_cho_kho( $cua_hang = '' ) {
	$ra = array();
	foreach ( khh_dt_dm_ds() as $m ) {
		if ( ! khh_dt_dm_thuoc_quan( $m, $cua_hang ) ) {
			continue;
		}
		$ra[] = array(
			've'    => khh_dt_dm_la_ve( $m ),
			'combo' => khh_dt_dm_la_combo( $m ),
			'ten'   => $m['ten'],
			'ma'    => $m['ma'],
			'nhom'  => $m['nhom'],
			'loai'  => $m['loai'],
		);
	}
	usort(
		$ra,
		function ( $a, $b ) {
			if ( $a['ve'] !== $b['ve'] ) {
				return $a['ve'] ? 1 : -1;
			}
			return strcmp( $a['ten'], $b['ten'] );
		}
	);
	return $ra;
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_dm_route' );
function khh_dt_dm_route() {
	register_rest_route(
		'khh-dt/v1',
		'/danh-muc',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_dm_xem',
				'permission_callback' => 'khh_dt_duoc_xem',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_dm_nap',
				/* Nạp / xoá danh mục chung cả chuỗi — văn phòng (quyền nạp file). */
				'permission_callback' => 'khh_dt_duoc_nap',
			),
		)
	);
}

function khh_dt_rest_dm_xem() {
	$g = khh_dt_dm_goi();
	$nhom = array();
	$ve = 0;
	$combo = 0;
	foreach ( $g['ds'] as $m ) {
		$n = '' !== trim( (string) $m['nhom'] ) ? (string) $m['nhom'] : '(không nhóm)';
		$nhom[ $n ] = isset( $nhom[ $n ] ) ? $nhom[ $n ] + 1 : 1;
		if ( khh_dt_dm_la_ve( $m ) ) {
			$ve++;
		}
		if ( khh_dt_dm_la_combo( $m ) ) {
			$combo++;
		}
	}
	arsort( $nhom );
	$g['nhom']  = $nhom;
	$g['so_ve'] = $ve;
	$g['so_combo'] = $combo;
	return $g;
}

function khh_dt_rest_dm_nap( $req ) {
	if ( (string) $req->get_param( 'xoa' ) ) {
		khh_dt_dm_xoa();
		return khh_dt_rest_dm_xem();
	}
	if ( empty( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return new WP_Error( 'khh_dt_file', 'Chưa chọn file danh sách hàng hoá.', array( 'status' => 400 ) );
	}
	$loi = isset( $_FILES['file']['error'] ) ? (int) $_FILES['file']['error'] : 0;
	if ( UPLOAD_ERR_OK !== $loi ) {
		return new WP_Error( 'khh_dt_file', 'Tải file lên không xong (mã ' . $loi . ').', array( 'status' => 400 ) );
	}
	$ten  = isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : 'file'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$duoi = strtolower( pathinfo( $ten, PATHINFO_EXTENSION ) );
	if ( ! in_array( $duoi, array( 'xlsx', 'xlsm', 'csv', 'tsv', 'txt' ), true ) ) {
		return new WP_Error( 'khh_dt_file', 'Chỉ nhận .xlsx, .csv hoặc .tsv.', array( 'status' => 400 ) );
	}
	$tam = $_FILES['file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! is_uploaded_file( $tam ) ) {
		return new WP_Error( 'khh_dt_file', 'File tải lên không hợp lệ.', array( 'status' => 400 ) );
	}
	$kq = khh_dt_dm_nap( $tam, $ten );
	if ( is_wp_error( $kq ) ) {
		$kq->add_data( array( 'status' => 400 ) );
		return $kq;
	}
	$ra = khh_dt_rest_dm_xem();
	$ra['vua_nap'] = $kq;
	return $ra;
}
