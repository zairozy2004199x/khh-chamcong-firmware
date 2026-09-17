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

/* 🔴 DÒ CẢ THƯ MỤC `wordpress/`, KHÔNG GÕ TAY DANH SÁCH PLUGIN.
   Danh sách gõ tay đứng im trong khi cây mã đi tiếp: `vhcp-cong` và `vhcp-noi-bo` từng không có
   tên ở đây, nên mọi tham chiếu sai trong hai plugin ấy không ai soi — bài kiểm vẫn xanh, chỉ là
   nó không nhìn vào chỗ mới nhất, tức chỗ dễ sai nhất. */
$thu_muc = array();
foreach ( glob( $goc . '/wordpress/*', GLOB_ONLYDIR ) as $d ) {
	$thu_muc[] = 'wordpress/' . basename( $d );
}
sort( $thu_muc );
if ( count( $thu_muc ) < 5 ) {
	echo "🔴 chỉ dò ra " . count( $thu_muc ) . " plugin — đường dẫn sai hay cây mã đổi?\n";
	exit( 1 );
}

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
				/* 🔴 LỚP CON KẾ THỪA THÌ THÀNH VIÊN CỦA CHA CŨNG LÀ CỦA NÓ.
				   15/09/2026 `VHCC_DayChiPhiVP extends VHCC_DayChiPhi` — bài này báo ba chỗ
				   "không có hàm" cho `QUYEN` · `da_day()` · `luu_nhieu()`, đều là thành viên
				   thừa kế và đều CHẠY ĐÚNG. Báo sai kiểu ấy tệ hơn không báo: người đọc mất
				   một vòng để biết bài kiểm mới là chỗ sai, rồi lần sau họ thôi tin nó. */
				if ( $id === T_EXTENDS )  { $sau = 'extends'; continue; }
				if ( $id === T_CONST )    { $sau = 'const'; continue; }
				if ( $id === T_FUNCTION ) { $sau = 'function'; continue; }
				if ( $id === T_STRING && $sau !== null ) {
					if ( $sau === 'lop' ) {
						$lop = $t[1];
						if ( ! isset( $dm[ $lop ] ) ) {
							$dm[ $lop ] = array( 'hang' => array(), 'ham' => array(), 'cha' => '' );
						}
					} elseif ( $sau === 'extends' ) {
						$dm[ $lop ]['cha'] = $t[1];
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

/**
 * THÂN CỦA TỪNG HÀM, theo CHỈ SỐ TOKEN — để hỏi được "lời gọi này có gác cùng hàm không".
 *
 * Cắt y như `tools/test/kiem-goi-cheo.php`, và bỏ chú thích vì cùng một lý do: giữ lại thì một
 * dòng giải thích có chữ `method_exists` bị tính là ĐÃ GÁC trong khi mã thật thì chưa.
 *
 * @return array mỗi phần tử ['tu'=>token mở thân, 'den'=>token đóng thân, 'ma'=>thân đã bỏ chú thích]
 */
function than_ham_token( $tk ) {
	$than = array();
	$n    = count( $tk );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! ( is_array( $tk[ $i ] ) && $tk[ $i ][0] === T_FUNCTION ) ) { continue; }
		$j  = $i;
		$bo = false;
		while ( $j < $n && $tk[ $j ] !== '{' ) {
			if ( $tk[ $j ] === ';' ) { $bo = true; break; }   // khai báo trừu tượng / interface
			$j++;
		}
		if ( $bo || $j >= $n ) { continue; }
		$sau = 1; $k = $j + 1; $ma = '';
		while ( $k < $n && $sau > 0 ) {
			if ( $tk[ $k ] === '{' ) { $sau++; }
			elseif ( $tk[ $k ] === '}' ) { $sau--; if ( ! $sau ) { break; } }
			if ( is_array( $tk[ $k ] )
				&& in_array( $tk[ $k ][0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { $k++; continue; }
			$ma .= is_array( $tk[ $k ] ) ? $tk[ $k ][1] : $tk[ $k ];
			$k++;
		}
		$than[] = array( 'tu' => $j, 'den' => $k, 'ma' => $ma );
		$i = $k;
	}
	return $than;
}

/** Mọi chỗ dùng `Lop::ten` — kèm số dòng, có dấu ngoặc hay không, và THÂN HÀM chứa nó. */
function cho_dung( $tep ) {
	$ra = array();
	foreach ( $tep as $f ) {
		$tk   = token_get_all( file_get_contents( $f ) );
		$n    = count( $tk );
		$than = than_ham_token( $tk );
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
			/* Thân hàm chứa lời gọi này (rỗng = nằm ngoài mọi hàm). */
			$trong = '';
			foreach ( $than as $b ) {
				if ( $i > $b['tu'] && $i < $b['den'] ) { $trong = $b['ma']; break; }
			}
			$ra[] = array( 'tep' => $f, 'dong' => $dong, 'lop' => $lop, 'ten' => $ten,
				'ham' => $la_ham, 'than' => $trong );
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
		/**
		 * ═════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 LỜI GỌI ĐÃ GÁC THÌ KHÔNG THỂ FATAL — VÀ FATAL LÀ THỨ DUY NHẤT BÀI NÀY CANH.
		 * ═════════════════════════════════════════════════════════════════════════════════════
		 * 17/09/2026. Bốn plugin cài ĐỘC LẬP, mà mã của chúng không nhất thiết cùng nằm trong
		 * cây này cùng lúc: `vhcp-chi-phi` 1.189.0 gọi `VHCC_VeTram::nut()` — một lớp bên bộ
		 * Chấm Công. Lời gọi ấy có gác đủ cặp `class_exists` + `method_exists` ngay cùng hàm,
		 * đúng LUẬT mà `tools/test/kiem-goi-cheo.php` bắt buộc.
		 *
		 * Không nới chỗ này thì hai bài kiểm chống nhau: một bài BẮT BUỘC gác, bài kia lại đòi
		 * lớp phải có mặt trong cây. Và "đỏ" ở đây không chỉ ra lỗi nào cả — lớp thiếu thì gác
		 * trả false, hàm trả về sớm, trang vẫn dựng. Bài kiểm đỏ mà không có gì để sửa là bài
		 * kiểm người ta bắt đầu bỏ qua, rồi bỏ qua luôn cái đỏ THẬT nằm cạnh nó.
		 *
		 * ⚠️ NỚI ĐÚNG NHÁNH NÀY, KHÔNG NỚI NHÁNH DƯỚI. Lớp CÓ mà thành viên KHÔNG vẫn đỏ như
		 *    cũ — đó mới là lỗi gõ nhầm (`VHCC_Auth::VAI_TRO_VAO`, chính ca sinh ra bài này), và
		 *    nó fatal thật vì không ai gác một cái tên mình tưởng là đúng.
		 * ⚠️ ĐÒI ĐÚNG CẶP (lớp, thành viên) trong CÙNG THÂN HÀM. Gác lớp này rồi gọi hàm khác
		 *    của nó là đúng cái bẫy `kiem-goi-cheo.php` sinh ra để dẹp — đừng mở lại ở đây.
		 */
		$cap = "'" . $d['lop'] . "','" . $d['ten'] . "'";
		$than_sach = str_replace( array( "\n", "\t", ' ' ), '', (string) $d['than'] );
		if ( $d['ham'] && false !== strpos( $than_sach, $cap )
			&& false !== strpos( $than_sach, 'method_exists(' ) ) {
			continue;
		}
		$sai[] = array( $d, 'không có lớp này' );
		continue;
	}
	/**
	 * Có thành viên ấy không — LEO CẢ CÂY KẾ THỪA.
	 *
	 * ⚠️ Có chặn vòng lặp (`$da`): `A extends B` mà `B extends A` là mã hỏng, nhưng bài kiểm
	 *    gặp nó thì treo cứng chứ không báo — treo thì không ai biết vì sao.
	 */
	$co_thanh_vien = function ( $lop, $ten, $la_ham ) use ( $dm ) {
		$da = array();
		while ( '' !== $lop && isset( $dm[ $lop ] ) && ! isset( $da[ $lop ] ) ) {
			$da[ $lop ] = true;
			$kho = $la_ham ? $dm[ $lop ]['ham'] : $dm[ $lop ]['hang'];
			if ( in_array( $ten, $kho, true ) ) { return true; }
			$lop = isset( $dm[ $lop ]['cha'] ) ? (string) $dm[ $lop ]['cha'] : '';
		}
		return false;
	};
	$co = $co_thanh_vien( $d['lop'], $d['ten'], $d['ham'] );
	if ( $co ) { continue; }
	// Gọi ::ten() mà `ten` lại là hằng (hoặc ngược lại) thì nói rõ, vì đó là lỗi hay gặp nhất.
	$nguoc = $co_thanh_vien( $d['lop'], $d['ten'], ! $d['ham'] );
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
