<?php
/**
 * GHÉP MÃ TÀI KHOẢN — chạy ngoài WordPress, không cần hosting.
 *
 * Vào:  1) hệ thống tài khoản của kế toán (CSV: Số hiệu · Tên tài khoản [· Tính chất])
 *       2) danh sách cơ sở            (CSV: Cơ sở · Mã đơn vị · Phân loại lớn [· Tên MISA])
 *       3) (tùy chọn) bảng tên gọi khác nhau (CSV: Từ khóa trong tên TK · Phân loại lớn)
 *
 * Ra:   CH_MangTK.csv · CH_LoaiChiPhi.csv · CH_TKNo.csv  -> nạp thẳng vào app
 *       BAOCAO.txt — chỗ nào chưa ghép được và vì sao
 *
 * Cách chạy:
 *   php tools/ghep-ma-tk.php --tk=taikhoan.csv --coso=coso.csv [--alias=alias.csv] \
 *       [--goc=641] [--out=dist/ghep]
 */

// ---------------------------------------------------------------- tham số
$args = array();
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( preg_match( '/^--([a-z]+)=(.*)$/', $a, $m ) ) { $args[ $m[1] ] = $m[2]; }
}
if ( empty( $args['tk'] ) || empty( $args['coso'] ) ) {
	fwrite( STDERR, "Thiếu tham số.\n  php tools/ghep-ma-tk.php --tk=taikhoan.csv --coso=coso.csv [--alias=alias.csv] [--goc=641] [--out=dist/ghep]\n" );
	exit( 2 );
}
$goc = isset( $args['goc'] ) ? trim( $args['goc'] ) : '641';
$out = isset( $args['out'] ) ? rtrim( $args['out'], '/' ) : 'dist/ghep';

// ---------------------------------------------------------------- tiện ích
/** Bỏ dấu + hạ chữ, để so tên mảng với tên phân loại lớn. */
function kd( $s ) {
	$s = mb_strtolower( trim( (string) $s ) );
	$map = array(
		'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
		'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
	);
	foreach ( $map as $plain => $acc ) {
		$s = str_replace( preg_split( '//u', $acc, -1, PREG_SPLIT_NO_EMPTY ), $plain, $s );
	}
	return trim( preg_replace( '/\s{2,}/u', ' ', $s ) );
}

/** Đọc CSV (bỏ BOM, tự nhận dấu phân cách , ; hay tab, bỏ dòng tiêu đề nếu có). */
function doc_csv( $path ) {
	if ( ! is_readable( $path ) ) { fwrite( STDERR, "Không đọc được file: $path\n" ); exit( 2 ); }
	$txt = file_get_contents( $path );
	$txt = preg_replace( '/^\xEF\xBB\xBF/', '', $txt );
	$sep = ',';
	$dau = strtok( $txt, "\n" );
	foreach ( array( "\t" => 0, ';' => 0, ',' => 0 ) as $c => $_ ) {
		if ( substr_count( $dau, $c ) > substr_count( $dau, $sep ) ) { $sep = $c; }
	}
	$rows = array();
	$fh   = fopen( 'php://memory', 'r+' );
	fwrite( $fh, $txt );
	rewind( $fh );
	while ( ( $r = fgetcsv( $fh, 0, $sep ) ) !== false ) {
		if ( $r === array( null ) ) { continue; }
		$r = array_map( function ( $v ) { return trim( (string) $v ); }, $r );
		if ( implode( '', $r ) === '' ) { continue; }
		$rows[] = $r;
	}
	fclose( $fh );
	// dòng đầu là tiêu đề nếu cột 1 không phải số hiệu tài khoản / có chữ "số hiệu"/"cơ sở"
	if ( count( $rows ) && preg_match( '/^(s[ốo]\s*hi[ệe]u|c[ơo]\s*s[ởo]|t[ừu]\s*kh[óo]a|stt)/iu', kd( $rows[0][0] ) ) ) {
		array_shift( $rows );
	}
	return $rows;
}

function ghi_csv( $path, $header, $rows ) {
	@mkdir( dirname( $path ), 0777, true );
	$fh = fopen( $path, 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" );                 // BOM để Excel mở đúng tiếng Việt
	fputcsv( $fh, $header );
	foreach ( $rows as $r ) { fputcsv( $fh, $r ); }
	fclose( $fh );
}

// ---------------------------------------------------------------- 1. hệ thống tài khoản
$chart = array();
foreach ( doc_csv( $args['tk'] ) as $r ) {
	$ma = isset( $r[0] ) ? preg_replace( '/\s+/', '', $r[0] ) : '';
	if ( $ma === '' || ! preg_match( '/^\d+$/', $ma ) ) { continue; }
	$chart[] = array( 'ma' => $ma, 'ten' => isset( $r[1] ) ? $r[1] : '' );
}
if ( ! count( $chart ) ) { fwrite( STDERR, "File tài khoản không có dòng nào dùng được.\n" ); exit( 2 ); }

$ma_list = array();
foreach ( $chart as $x ) { $ma_list[] = (string) $x['ma']; }
$ten_cua_ma = array();
foreach ( $chart as $x ) { $ten_cua_ma[ (string) $x['ma'] ] = $x['ten']; }

/** Tài khoản có con hay không (so bằng CHUỖI — khóa mảng PHP sẽ đổi mã thành số). */
function co_con( $ma, $ma_list ) {
	foreach ( $ma_list as $m ) { if ( $m !== $ma && strpos( $m, $ma ) === 0 ) { return true; } }
	return false;
}

// nhóm mảng = tài khoản cha dưới gốc
$nhom = array();
foreach ( $chart as $x ) {
	$ma = (string) $x['ma'];
	if ( $goc !== '' && ( strpos( $ma, $goc ) !== 0 || $ma === $goc ) ) { continue; }
	if ( ! co_con( $ma, $ma_list ) ) { continue; }
	$tu = trim( preg_replace( '/^\s*chi\s*ph[íi]\s*/iu', '', $x['ten'] ) );
	if ( $tu === '' ) { continue; }
	$nhom[ $ma ] = $tu;
}

// ---------------------------------------------------------------- 2. cơ sở -> phân loại lớn
$plls = array();
$coso_thieu_pll = array();
foreach ( doc_csv( $args['coso'] ) as $r ) {
	$ten = isset( $r[0] ) ? $r[0] : '';
	$pll = isset( $r[2] ) ? $r[2] : '';
	if ( $ten === '' ) { continue; }
	if ( $pll === '' ) { $coso_thieu_pll[] = $ten; continue; }
	$plls[ $pll ] = 1;
}
$plls = array_keys( $plls );

// ---------------------------------------------------------------- 3. bảng tên gọi khác nhau
$alias = array();   // kd(từ khóa) -> danh sách phân loại lớn
if ( ! empty( $args['alias'] ) ) {
	foreach ( doc_csv( $args['alias'] ) as $r ) {
		$tu = isset( $r[0] ) ? $r[0] : '';
		$p  = isset( $r[1] ) ? $r[1] : '';
		if ( $tu === '' || $p === '' ) { continue; }
		$alias[ kd( $tu ) ][] = $p;
	}
}

// ---------------------------------------------------------------- ghép mảng
$mang_rows = array();   // [pll, nhomTk, tuKhoa, ghi chú]
$chua_ghep = array();
foreach ( $nhom as $ma => $tu ) {
	$tk = kd( $tu );
	$hit = isset( $alias[ $tk ] ) ? $alias[ $tk ] : array();
	if ( ! count( $hit ) ) {
		foreach ( $plls as $p ) {
			$pk = kd( $p );
			if ( $tk !== '' && ( strpos( $pk, $tk ) !== false || strpos( $tk, $pk ) !== false ) ) { $hit[] = $p; }
		}
	}
	if ( ! count( $hit ) ) { $chua_ghep[ $ma ] = $tu; continue; }
	foreach ( $hit as $p ) { $mang_rows[] = array( $p, $ma, $tu, $ten_cua_ma[ $ma ] ); }
}

// ---------------------------------------------------------------- sinh danh mục + ma trận
$loai   = array();   // kd(tên loại) -> tên hiển thị
$mx     = array();   // [kd(loại)][pll] = số hiệu
$bo_qua = array();   // tài khoản con không thấy từ khóa mảng trong tên
foreach ( $mang_rows as $mr ) {
	list( $pll, $nhom_tk, $tu, $_ ) = $mr;
	foreach ( $chart as $x ) {
		$ma = (string) $x['ma'];
		if ( $ma === $nhom_tk || strpos( $ma, $nhom_tk ) !== 0 ) { continue; }
		if ( co_con( $ma, $ma_list ) ) { continue; }                 // chỉ lấy tài khoản lá
		$sach = preg_replace( '/\s*' . preg_quote( $tu, '/' ) . '\s*/iu', ' ', $x['ten'] );
		$sach = trim( preg_replace( '/\s{2,}/u', ' ', (string) $sach ) );
		$sach = trim( $sach, " -–—_" );
		if ( $sach === '' || kd( $sach ) === kd( $x['ten'] ) ) { $bo_qua[ $ma ] = $x['ten']; continue; }
		$k = kd( $sach );
		if ( ! isset( $loai[ $k ] ) ) { $loai[ $k ] = $sach; }
		$mx[ $k ][ $pll ] = $ma;
	}
}

// tài khoản dùng chung: lá, KHÔNG nằm dưới nhóm mảng nào
$chung = array();
foreach ( $chart as $x ) {
	$ma = (string) $x['ma'];
	if ( co_con( $ma, $ma_list ) ) { continue; }
	$thuoc_mang = false;
	foreach ( $nhom as $n => $_ ) { if ( strpos( $ma, (string) $n ) === 0 ) { $thuoc_mang = true; break; } }
	if ( $thuoc_mang || $x['ten'] === '' ) { continue; }
	$chung[ $ma ] = $x['ten'];
}

// ---------------------------------------------------------------- xuất file
$f_mang = array();
foreach ( $mang_rows as $mr ) { $f_mang[] = array( $mr[0], $mr[1], $mr[2], $mr[3] ); }
foreach ( $chua_ghep as $ma => $tu ) { $f_mang[] = array( '', $ma, $tu, 'CHƯA GHÉP — điền cột Phân loại lớn' ); }
ghi_csv( "$out/CH_MangTK.csv", array( 'Phân loại lớn', 'Nhóm TK', 'Từ khóa trong tên TK', 'Ghi chú' ), $f_mang );

$f_loai = array();
foreach ( $loai as $k => $ten ) { $f_loai[] = array( $ten, '', '', '', '', 'theo ma trận từng mảng', '' ); }
foreach ( $chung as $ma => $ten ) { $f_loai[] = array( $ten, $ma, '', '', '', 'dùng chung mọi mảng', $ten ); }
ghi_csv( "$out/CH_LoaiChiPhi.csv", array( 'Loại chi phí', 'TK Nợ', 'TK Có', 'Mã đối tượng', 'Bộ phận', 'Ghi chú', 'Tên MISA' ), $f_loai );

$f_mx = array();
foreach ( $mx as $k => $per ) {
	foreach ( $per as $pll => $ma ) { $f_mx[] = array( $loai[ $k ], $pll, $ma ); }
}
ghi_csv( "$out/CH_TKNo.csv", array( 'Nhóm mặt hàng', 'Phân loại lớn', 'TK Nợ' ), $f_mx );

// ---------------------------------------------------------------- báo cáo
$bc  = array();
$bc[] = 'GHÉP MÃ TÀI KHOẢN — BÁO CÁO';
$bc[] = str_repeat( '=', 60 );
$bc[] = 'Tài khoản đọc được          : ' . count( $chart );
$bc[] = 'Nhóm mảng dưới ' . $goc . '           : ' . count( $nhom );
$bc[] = 'Phân loại lớn đang khai     : ' . count( $plls );
$bc[] = 'Dòng mảng ghép được         : ' . count( $mang_rows );
$bc[] = 'Loại chi phí theo ma trận   : ' . count( $loai );
$bc[] = 'Loại chi phí dùng chung     : ' . count( $chung );
$bc[] = 'Ô ma trận sinh ra           : ' . count( $f_mx );
$bc[] = '';
if ( count( $chua_ghep ) ) {
	$bc[] = 'CẦN ANH ĐIỀN — nhóm tài khoản chưa biết thuộc phân loại lớn nào';
	$bc[] = '(mở CH_MangTK.csv, điền cột "Phân loại lớn" cho các dòng CHƯA GHÉP,';
	$bc[] = ' hoặc tạo file alias.csv 2 cột "Từ khóa,Phân loại lớn" rồi chạy lại)';
	foreach ( $chua_ghep as $ma => $tu ) { $bc[] = '  · ' . $ma . '  ' . $ten_cua_ma[ $ma ] . '   (từ khóa: ' . $tu . ')'; }
	$bc[] = '';
}
$pll_trong = array();
foreach ( $plls as $p ) {
	$co = false;
	foreach ( $mang_rows as $mr ) { if ( $mr[0] === $p ) { $co = true; break; } }
	if ( ! $co ) { $pll_trong[] = $p; }
}
if ( count( $pll_trong ) ) {
	$bc[] = 'PHÂN LOẠI LỚN CHƯA CÓ NHÓM TÀI KHOẢN NÀO (cột này sẽ trống trong ma trận):';
	foreach ( $pll_trong as $p ) { $bc[] = '  · ' . $p; }
	$bc[] = '';
}
if ( count( $coso_thieu_pll ) ) {
	$bc[] = 'CƠ SỞ CHƯA KHAI PHÂN LOẠI LỚN (nhập chi ở đây sẽ không ra mã):';
	foreach ( $coso_thieu_pll as $c ) { $bc[] = '  · ' . $c; }
	$bc[] = '';
}
if ( count( $bo_qua ) ) {
	$bc[] = 'TÀI KHOẢN CON KHÔNG THẤY TỪ KHÓA MẢNG TRONG TÊN (bỏ qua, khai tay nếu cần):';
	foreach ( $bo_qua as $ma => $ten ) { $bc[] = '  · ' . $ma . '  ' . $ten; }
	$bc[] = '';
}
$bc[] = 'NẠP VÀO APP (wp-admin -> Vận Hành Chi Phí -> Nhập dữ liệu), theo thứ tự:';
$bc[] = '  1) CH_TaiKhoan   (chính file tài khoản của kế toán)';
$bc[] = '  2) CH_MangTK.csv';
$bc[] = '  3) CH_LoaiChiPhi.csv';
$bc[] = '  4) CH_TKNo.csv';
$bc[] = 'Bỏ tích "Dòng đầu là tiêu đề" thì phải xóa dòng tiêu đề trong file trước khi nạp.';
@mkdir( $out, 0777, true );
file_put_contents( "$out/BAOCAO.txt", implode( "\n", $bc ) . "\n" );

echo implode( "\n", $bc ), "\n";
echo "\nĐã ghi: $out/CH_MangTK.csv · CH_LoaiChiPhi.csv · CH_TKNo.csv · BAOCAO.txt\n";
