<?php
/**
 * Soát THAM CHIẾU TĨNH trong các plugin: `VHCP_X::HANG` / `VHCP_X::ham()` có thật không.
 *
 * VÌ SAO CẦN: gọi một hằng lớp không tồn tại là lỗi nghiêm trọng (fatal) trong PHP 8 —
 * nó giết cả trang TỪ CHỖ ĐÓ XUỐNG. Trang Cài đặt đã từng mất nút "Lưu cài đặt" đúng
 * vì một dòng `VHCC_Auth::VAI_TRO_VAO` (tên đúng là hàm `vai_tro_vao()`). Nhìn màn hình
 * thì chỉ thấy trang đứt giữa, không thấy nguyên nhân.
 *
 * Bộ phép thử cũ KHÔNG bắt được loại lỗi này: nó gọi thẳng từng hàm nghiệp vụ, không
 * hề VẼ màn hình, mà lỗi này chỉ nổ lúc vẽ.
 *
 * Dùng token_get_all chứ không dùng regex: chú thích và chuỗi phải được bỏ qua, mà đúng
 * cái lỗi trên khi sửa em có nhắc lại tên hằng sai trong chú thích — regex báo động sai
 * ngay lần chạy đầu.
 *
 * Chạy: php tools/test/kiem-tham-chieu.php
 */

$goc = dirname( __DIR__, 2 );
$thu_muc = array( 'wordpress/vhcp-cham-cong', 'wordpress/vhcp-chi-phi', 'wordpress/vhcp-hop-dong',
	'wordpress/vhcp-trang-chu', 'wordpress/vhcp-ghe' );

/** Tất cả tệp .php dưới các thư mục trên. */
function tep_php( $goc, $thu_muc ) {
	$ra = array();
	foreach ( $thu_muc as $d ) {
		$duong = $goc . '/' . $d;
		if ( ! is_dir( $duong ) ) { continue; }
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $duong ) );
		foreach ( $it as $f ) {
			if ( $f->isFile() && strtolower( $f->getExtension() ) === 'php' ) { $ra[] = $f->getPathname(); }
		}
	}
	sort( $ra );
	return $ra;
}

/** Danh mục: lớp -> ['hang'=>[], 'ham'=>[]] */
function khai_bao( $tep ) {
	$dm = array();
	foreach ( $tep as $f ) {
		$tk  = token_get_all( file_get_contents( $f ) );
		$lop = '';
		$sau = null;   // 'lop' | 'const' | 'function'
		$sau_dau_ngoac = 0;
		foreach ( $tk as $t ) {
			if ( is_array( $t ) ) {
				$id = $t[0];
				if ( $id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT ) { continue; }
				if ( $id === T_CLASS )    { $sau = 'lop';   continue; }
				if ( $id === T_CONST )    { $sau = 'const'; continue; }
				if ( $id === T_FUNCTION ) { $sau = 'function'; continue; }
				if ( $id === T_STRING && $sau !== null ) {
					if ( $sau === 'lop' ) {
						$lop = $t[1];
						if ( ! isset( $dm[ $lop ] ) ) { $dm[ $lop ] = array( 'hang' => array(), 'ham' => array() ); }
					} elseif ( $lop !== '' ) {
						$dm[ $lop ][ $sau === 'const' ? 'hang' : 'ham' ][] = $t[1];
					}
					$sau = null;
					continue;
				}
			}
			$sau = null;
		}
	}
	return $dm;
}

/** Mọi chỗ dùng `Lop::ten` — kèm số dòng và có dấu ngoặc hay không. */
function cho_dung( $tep ) {
	$ra = array();
	foreach ( $tep as $f ) {
		$tk = token_get_all( file_get_contents( $f ) );
		$n  = count( $tk );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( ! ( is_array( $tk[ $i ] ) && $tk[ $i ][0] === T_DOUBLE_COLON ) ) { continue; }
			// Lùi lại tìm tên lớp (bỏ khoảng trắng/chú thích).
			$j = $i - 1;
			while ( $j >= 0 && is_array( $tk[ $j ] )
				&& in_array( $tk[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) { $j--; }
			if ( $j < 0 || ! is_array( $tk[ $j ] ) || $tk[ $j ][0] !== T_STRING ) { continue; }
			$lop = $tk[ $j ][1];
			// Tiến lên tìm tên thành viên.
			$k = $i + 1;
			while ( $k < $n && is_array( $tk[ $k ] )
				&& in_array( $tk[ $k ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) { $k++; }
			if ( $k >= $n || ! is_array( $tk[ $k ] ) || $tk[ $k ][0] !== T_STRING ) { continue; }
			$ten  = $tk[ $k ][1];
			$dong = $tk[ $k ][2];
			// Sau tên có dấu ngoặc mở thì là gọi hàm.
			$m = $k + 1;
			while ( $m < $n && is_array( $tk[ $m ] )
				&& in_array( $tk[ $m ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) { $m++; }
			$la_ham = ( $m < $n && $tk[ $m ] === '(' );
			$ra[] = array( 'tep' => $f, 'dong' => $dong, 'lop' => $lop, 'ten' => $ten, 'ham' => $la_ham );
		}
	}
	return $ra;
}

$tep = tep_php( $goc, $thu_muc );
$dm  = khai_bao( $tep );
$sai = array();

foreach ( cho_dung( $tep ) as $d ) {
	// Chỉ soát lớp của mình. `self`, `parent`, `static`, `WP_Error`, `wpdb`… để yên.
	if ( ! preg_match( '/^VHC[PC]_/', $d['lop'] ) ) { continue; }
	if ( ! isset( $dm[ $d['lop'] ] ) ) {
		$sai[] = array( $d, 'không có lớp này' );
		continue;
	}
	$co = $d['ham']
		? in_array( $d['ten'], $dm[ $d['lop'] ]['ham'], true )
		: in_array( $d['ten'], $dm[ $d['lop'] ]['hang'], true );
	if ( $co ) { continue; }
	// Gọi ::ten() mà `ten` lại là hằng (hoặc ngược lại) thì nói rõ, vì đó là lỗi hay gặp nhất.
	$nguoc = $d['ham']
		? in_array( $d['ten'], $dm[ $d['lop'] ]['hang'], true )
		: in_array( $d['ten'], $dm[ $d['lop'] ]['ham'], true );
	$vi = $d['ham'] ? 'không có hàm' : 'không có hằng';
	if ( $nguoc ) { $vi .= $d['ham'] ? ' — nhưng có HẰNG cùng tên, bỏ dấu ngoặc đi' : ' — nhưng có HÀM cùng tên, thêm () vào'; }
	$sai[] = array( $d, $vi );
}

foreach ( $sai as $s ) {
	list( $d, $vi ) = $s;
	printf( "%s:%d  %s::%s%s  <- %s\n",
		str_replace( $goc . '/', '', $d['tep'] ), $d['dong'], $d['lop'], $d['ten'],
		$d['ham'] ? '()' : '', $vi );
}

printf( "%s  %d tệp · %d lớp · %d chỗ sai\n",
	$sai ? '✗ SAI' : '✓ SẠCH', count( $tep ), count( $dm ), count( $sai ) );
exit( $sai ? 1 : 0 );
