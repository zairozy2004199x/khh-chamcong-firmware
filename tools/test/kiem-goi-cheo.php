<?php
/**
 * SOÁT LỜI GỌI CHÉO GIỮA CÁC PLUGIN.
 *
 * VÌ SAO CẦN: 4 plugin cài ĐỘC LẬP, người dùng có thể đang chạy bản lệch nhau. Gác bằng
 * class_exists() chỉ nói "có plugin đó", KHÔNG nói "bản đó có hàm mình định gọi".
 *
 * Đã cắn thật (23/08/2026): app Chi Phí gọi VHG_Chan::css_sang() — một hàm vừa thêm bên
 * plugin Ghế — và chỉ gác bằng class_exists('VHG_Chan'). Máy đang chạy Ghế bản cũ: lớp
 * CÓ, hàm KHÔNG -> "Đã có một lỗi nghiêm trọng trên trang web của bạn", trắng cả trang.
 * php -l không bắt được (cú pháp đúng), kiem-tham-chieu.php cũng không (hàm có thật —
 * trong CÂY MÃ NÀY, ở bản mới nhất).
 *
 * LUẬT: mọi lời gọi tĩnh sang lớp của plugin KHÁC phải được method_exists() gác đúng
 * tên hàm đó, trong cùng một hàm.
 *
 * Chạy: php tools/test/kiem-goi-cheo.php
 */
$goc = dirname( __DIR__, 2 );

function tep_php_cua( $thu_muc ) {
	$out = array();
	if ( ! is_dir( $thu_muc ) ) { return $out; }
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $thu_muc ) );
	foreach ( $it as $f ) { if ( $f->isFile() && substr( $f->getFilename(), -4 ) === '.php' ) { $out[] = $f->getPathname(); } }
	sort( $out );
	return $out;
}

/**
 * Tiền tố lớp của từng plugin — DÒ TỪ CHÍNH MÃ, không gõ tay.
 *
 * 🔴 Bản đầu gõ tay năm dòng, và bảng ấy đứng im trong khi cây mã đi tiếp: `vhcp-cong` và
 *    `vhcp-noi-bo` không có tên trong đó, nên mọi lời gọi chéo của hai plugin ấy KHÔNG được soi
 *    — mà `vhcp-noi-bo` thì sống bằng lời gọi chéo (nó đọc thẻ phiên của hệ chấm công). Bài kiểm
 *    vẫn xanh, chỉ là nó không nhìn vào chỗ đang cần nhìn nhất. Đúng cái bẫy mà chính bài kiểm
 *    này sinh ra để dẹp — rồi lại mọc lại ở danh sách của nó.
 *
 * Cách dò: thư mục nào khai `class VHXX_...` thì tiền tố của plugin ấy là `VHXX_`. Mỗi plugin
 * hiện khai đúng một tiền tố; khai hai là chốt dưới đây đỏ, và đỏ đúng — hai tiền tố trong một
 * plugin nghĩa là bài kiểm không còn phân biệt được đâu là "lớp của mình", đâu là "lớp plugin khác".
 */
$plugin = array();
$loi_dau = array();
foreach ( glob( $goc . '/wordpress/*', GLOB_ONLYDIR ) as $thu_muc ) {
	$dem = array();
	foreach ( tep_php_cua( $thu_muc ) as $f ) {
		if ( preg_match_all( '/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9]+_)/m',
			file_get_contents( $f ), $mm ) ) {
			foreach ( $mm[1] as $tt ) { $dem[ $tt ] = isset( $dem[ $tt ] ) ? $dem[ $tt ] + 1 : 1; }
		}
	}
	if ( ! $dem ) { continue; }                       // thư mục không khai lớp nào -> bỏ qua
	if ( count( $dem ) > 1 ) {
		$loi_dau[] = basename( $thu_muc ) . ' khai NHIỀU tiền tố lớp: ' . implode( ', ', array_keys( $dem ) );
		continue;
	}
	$plugin[ 'wordpress/' . basename( $thu_muc ) ] = key( $dem );
}
$moi_tien_to = array_values( $plugin );

if ( count( $plugin ) < 5 ) {
	$loi_dau[] = 'chỉ dò ra ' . count( $plugin ) . ' plugin — đường dẫn sai hay cây mã đổi?';
}


/** Cắt mã thành từng THÂN HÀM để hỏi "gác có nằm cùng hàm với lời gọi không". */
function than_ham( $ma ) {
	$tok = token_get_all( $ma );
	$than = array(); $n = count( $tok );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! is_array( $tok[ $i ] ) || $tok[ $i ][0] !== T_FUNCTION ) { continue; }
		// tới dấu { mở thân
		$j = $i; $sau = 0;
		while ( $j < $n && $tok[ $j ] !== '{' ) {
			if ( $tok[ $j ] === ';' ) { $sau = -1; break; }   // khai báo trừu tượng / interface
			$j++;
		}
		if ( $sau === -1 || $j >= $n ) { continue; }
		$sau = 1; $k = $j + 1; $chuoi = '';
		while ( $k < $n && $sau > 0 ) {
			if ( $tok[ $k ] === '{' ) { $sau++; }
			elseif ( $tok[ $k ] === '}' ) { $sau--; if ( ! $sau ) { break; } }
			/* 🔴 BỎ CHÚ THÍCH RA KHỎI THÂN HÀM.
			   Giữ lại thì hỏng cả hai chiều: một dòng chú thích KỂ VỀ lời gọi `VHCC_Auth::phien()`
			   bị đếm thành lời gọi thật (đã xảy ra 26/08/2026, ngay chỗ vừa sửa xong lỗi ấy), và
			   ngược lại một `method_exists` chỉ nằm trong chú thích lại được tính là đã gác. Chốt
			   này phải soi MÃ, không soi lời giải thích về mã. */
			if ( is_array( $tok[ $k ] ) && in_array( $tok[ $k ][0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$k++;
				continue;
			}
			$chuoi .= is_array( $tok[ $k ] ) ? $tok[ $k ][1] : $tok[ $k ];
			$k++;
		}
		$than[] = $chuoi;
		$i = $k;
	}
	return $than;
}

$loi = $loi_dau; $so_tep = 0; $so_goi = 0;
foreach ( $plugin as $thu_muc => $cua_minh ) {
	foreach ( tep_php_cua( $goc . '/' . $thu_muc ) as $f ) {
		$so_tep++;
		$ma = file_get_contents( $f );
		foreach ( than_ham( $ma ) as $than ) {
			if ( ! preg_match_all( '/\b(VH[A-Z]*_[A-Za-z0-9_]+)::([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $than, $m, PREG_SET_ORDER ) ) { continue; }
			foreach ( $m as $x ) {
				$lop = $x[1]; $ham = $x[2];
				// Lớp của CHÍNH plugin này -> cùng gói, cùng bản, khỏi gác
				if ( strpos( $lop, $cua_minh ) === 0 ) { continue; }
				$la_plugin_khac = false;
				foreach ( $moi_tien_to as $tt ) { if ( $tt !== $cua_minh && strpos( $lop, $tt ) === 0 ) { $la_plugin_khac = true; } }
				if ( ! $la_plugin_khac ) { continue; }
				$so_goi++;
				// Gác được viết 2 kiểu, cùng ý nghĩa:
				//   method_exists( 'VHG_Chan', 'html' )      — gọi thẳng
				//   $co( 'VHG_Chan', 'html' )                — qua hàm phụ, khi phải gác nhiều chỗ
				// Đòi đúng CẶP (tên lớp, tên hàm) nằm trong cùng thân hàm, và tệp phải thật sự
				// có dùng method_exists. Bắt đúng cái sai thật: gọi mà KHÔNG gác hàm nào cả.
				$cap = "'" . $lop . "', '" . $ham . "'";
				$co_cap = ( strpos( str_replace( array( "\n", "\t", '  ' ), ' ', $than ), $cap ) !== false );
				if ( ! $co_cap || strpos( $ma, 'method_exists' ) === false ) {
					$loi[] = basename( $f ) . ': gọi ' . $lop . '::' . $ham . '() sang plugin khác mà không gác'
						. " method_exists( '$lop', '$ham' )";
				}
			}
		}
	}
}

if ( count( $loi ) ) {
	echo "HỎNG: " . count( $loi ) . " lời gọi chéo chưa gác bằng method_exists\n";
	foreach ( array_unique( $loi ) as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "\nLý do: 4 plugin cài độc lập, bản có thể lệch nhau. class_exists() chỉ nói CÓ PLUGIN,\n"
		. "không nói CÓ HÀM — gọi hụt là lỗi nghiêm trọng, trắng cả trang WordPress.\n";
	exit( 1 );
}
echo "✓ SẠCH  $so_tep tệp · $so_goi lời gọi chéo · đều gác bằng method_exists\n";
