<?php
/**
 * SOÁT BA DANH SÁCH TRONG `tools/tren-host.sh` — BỘ NÀO TỰ CẬP NHẬT ĐƯỢC THÌ PHẢI CÓ TÊN Ở ĐÓ.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * VÌ SAO CẦN.
 *
 * `tren-host.sh` giữ ba danh sách khai TAY:
 *   · `DS_PLUGIN`  — tên thư mục, dùng cho lượt soát "trang này đang cài bộ nào".
 *   · `DS_NHO`     — tiền tố ô nhớ (`<nho>_gh_ban_moi`), dùng cho lượt bắt hỏi lại GitHub ngay.
 *   · `DS_O_KHOA`  — các Option chứa khoá GitHub, dùng cho lượt khai khoá.
 *
 * Thêm một plugin mới mà quên khai vào đó thì KHÔNG CÓ DÒNG LỖI NÀO. Bộ ấy đơn giản là:
 *   · không hiện ra lúc soát → người soát tưởng trang chưa cài nó;
 *   · không bao giờ được xoá ô nhớ → phải chờ đủ sáu giờ mới thấy bản mới, và nếu ô nhớ đang
 *     giữ 'khong' vì một lượt mạng hỏng thì nó đứng yên lâu hơn nữa.
 *
 * Đã xảy ra thật (17/09/2026): ba bộ `khh-platform`, `khh-doanh-thu`, `vhcp-cc-app` đều đã có
 * lớp tự cập nhật từ lâu mà không bộ nào có tên trong `DS_PLUGIN`.
 *
 * Và `DS_O_KHOA` thì ngược lại — THỪA sáu ô (`vhcc_gh_token`, `vhg_gh_token`, `vhnb_gh_token`,
 * `vhtc_gh_token`, `vhd_gh_token`, `vhda_gh_token`) mà không lớp nào đọc: các bộ ấy đều khai
 * `const O_KHOA = 'vhcp_gh_token'`. Khai khoá vẫn chạy (ô thật có trong danh sách) nên lỗi này
 * không làm hỏng việc gì — nó chỉ làm lượt SOÁT báo "ĐÃ KHAI" cho sáu ô chẳng ai đọc, tức là
 * một câu trả lời sai cho đúng câu hỏi mà người soát đang hỏi.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * NGUỒN THẬT LÀ MÃ, KHÔNG PHẢI DANH SÁCH.
 *
 * Bài này KHÔNG chép lại danh sách để so hai bản chép tay với nhau — làm thế thì sửa một chỗ
 * quên chỗ kia vẫn xanh. Nó đọc thẳng `const O_NHO` / `const O_KHOA` trong từng lớp tự cập
 * nhật rồi mới đối chiếu.
 *
 * Chạy: php tools/test/kiem-tren-host-du-bo.php
 */

$goc = dirname( __DIR__, 2 );
$sh  = $goc . '/tools/tren-host.sh';

$dat = 0;
$hong = array();
function t( $ten, $ok, $them = '' ) {
	global $dat, $hong;
	if ( $ok ) {
		$dat++;
		printf( "PASS  %s\n", $ten );
	} else {
		$hong[] = $ten;
		printf( "FAIL  %s%s\n", $ten, $them ? "\n      " . $them : '' );
	}
}

/** Đọc một danh sách `TEN="a b c"` trong tệp shell ra mảng. */
function ds( $noi_dung, $ten ) {
	if ( ! preg_match( '/^' . preg_quote( $ten, '/' ) . '="([^"]*)"/m', $noi_dung, $m ) ) {
		return null;
	}
	return preg_split( '/\s+/', trim( $m[1] ), -1, PREG_SPLIT_NO_EMPTY );
}

if ( ! is_file( $sh ) ) {
	echo "FAIL  không thấy tools/tren-host.sh\n";
	exit( 1 );
}
$noi_dung = file_get_contents( $sh );

$ds_plugin = ds( $noi_dung, 'DS_PLUGIN' );
$ds_nho    = ds( $noi_dung, 'DS_NHO' );
$ds_khoa   = ds( $noi_dung, 'DS_O_KHOA' );

t( 'đọc được DS_PLUGIN', is_array( $ds_plugin ) && $ds_plugin );
t( 'đọc được DS_NHO', is_array( $ds_nho ) && $ds_nho );
t( 'đọc được DS_O_KHOA', is_array( $ds_khoa ) && $ds_khoa );
if ( ! $ds_plugin || ! $ds_nho || ! $ds_khoa ) {
	echo "\nKhông đọc nổi danh sách — dừng.\n";
	exit( 1 );
}

/* ── Dò mọi plugin CÓ lớp tự cập nhật ─────────────────────────────────────────────────────── */
$bo = array();   // tên thư mục => array( 'nho' => …, 'khoa' => … )
foreach ( glob( $goc . '/wordpress/*', GLOB_ONLYDIR ) as $thu_muc ) {
	$ten  = basename( $thu_muc );
	$tep  = glob( $thu_muc . '/includes/class-*-tu-cap-nhat.php' );
	if ( ! $tep ) {
		continue;
	}
	$ma = file_get_contents( $tep[0] );
	/* Chỉ nhận hằng khai thẳng chuỗi. Bộ nào dựng khoá bằng biểu thức thì bài này không đoán
	   được — và đó cũng là lúc nên xem lại, vì `tren-host.sh` cũng chỉ ghi được chuỗi cố định. */
	if ( ! preg_match( "/const\s+O_NHO\s*=\s*'([a-z0-9_]+)_gh_ban_moi'/i", $ma, $mn ) ) {
		continue;
	}
	if ( ! preg_match( "/const\s+O_KHOA\s*=\s*'([a-z0-9_]+)'/i", $ma, $mk ) ) {
		continue;
	}
	$bo[ $ten ] = array(
		'nho'  => $mn[1],
		'khoa' => $mk[1],
	);
}

t( 'tìm thấy các bộ tự cập nhật trong wordpress/', count( $bo ) > 0,
	'không dò ra lớp class-*-tu-cap-nhat.php nào — bài thử này coi như mù' );

/* ── 1. Mọi bộ tự cập nhật phải có tên trong DS_PLUGIN ────────────────────────────────────── */
$thieu = array_values( array_diff( array_keys( $bo ), $ds_plugin ) );
t( '🔴 DS_PLUGIN có đủ mọi bộ tự cập nhật được', ! $thieu,
	'thiếu: ' . implode( ', ', $thieu ) . "\n      → thêm vào DS_PLUGIN trong tools/tren-host.sh" );

/* ── 2. DS_PLUGIN không có tên lạ ─────────────────────────────────────────────────────────── */
$la = array();
foreach ( $ds_plugin as $p ) {
	if ( ! is_dir( $goc . '/wordpress/' . $p ) ) {
		$la[] = $p;
	}
}
t( 'DS_PLUGIN không nhắc thư mục không tồn tại', ! $la, 'lạ: ' . implode( ', ', $la ) );

/* ── 3. DS_NHO đi song song DS_PLUGIN, và khớp O_NHO trong mã ─────────────────────────────── */
t( '🔴 DS_NHO dài đúng bằng DS_PLUGIN', count( $ds_nho ) === count( $ds_plugin ),
	'DS_PLUGIN ' . count( $ds_plugin ) . ' mục, DS_NHO ' . count( $ds_nho ) . " mục\n" .
	'      → hai dòng này ghép cặp theo THỨ TỰ, lệch một mục là lệch từ đó trở đi' );

if ( count( $ds_nho ) === count( $ds_plugin ) ) {
	$lech = array();
	foreach ( $ds_plugin as $i => $p ) {
		if ( ! isset( $bo[ $p ] ) ) {
			continue;   // bộ chưa có lớp tự cập nhật thì không có gì để đối chiếu
		}
		if ( $ds_nho[ $i ] !== $bo[ $p ]['nho'] ) {
			$lech[] = $p . ': DS_NHO ghi "' . $ds_nho[ $i ] . '" nhưng mã khai "' . $bo[ $p ]['nho'] . '"';
		}
	}
	t( '🔴 DS_NHO khớp const O_NHO của từng bộ', ! $lech, implode( "\n      ", $lech ) );
}

/* ── 4. DS_O_KHOA đúng bằng TẬP ô khoá có thật ────────────────────────────────────────────── */
/* ⚠️ Danh sách này KHÔNG song song DS_PLUGIN: phần lớn các bộ đọc chung `vhcp_gh_token`. */
$khoa_that = array_values( array_unique( array_column( $bo, 'khoa' ) ) );
sort( $khoa_that );
$khoa_khai = $ds_khoa;
sort( $khoa_khai );

$thieu_k = array_values( array_diff( $khoa_that, $khoa_khai ) );
$thua_k  = array_values( array_diff( $khoa_khai, $khoa_that ) );

t( '🔴 DS_O_KHOA có đủ mọi ô khoá bộ nào đó thật sự đọc', ! $thieu_k,
	'thiếu: ' . implode( ', ', $thieu_k ) . "\n      → khai `khoa tatca` sẽ BỎ QUA ô này, bộ ấy không tải được bản mới" );

t( 'DS_O_KHOA không liệt kê ô không ai đọc', ! $thua_k,
	'thừa: ' . implode( ', ', $thua_k ) . "\n" .
	"      → lượt soát sẽ báo \"ĐÃ KHAI\" cho ô chẳng lớp nào đọc, một câu trả lời sai" );

/* ── 5. Mấy chốt nhỏ của chính tệp shell ──────────────────────────────────────────────────── */
t( 'tren-host.sh đọc khoá bằng read -s (không hiện lên màn hình)',
	false !== strpos( $noi_dung, 'read -r -s khoa' ) );
t( 'và soát hình dạng khoá trước khi ghi',
	false !== strpos( $noi_dung, 'github_pat_*' ) );

echo "\n";
if ( $hong ) {
	printf( "✗ HỎNG %d / %d\n", count( $hong ), count( $hong ) + $dat );
	exit( 1 );
}
printf( "✓ %d phép thử đều đạt — %d bộ tự cập nhật, %d ô khoá.\n",
	$dat, count( $bo ), count( $khoa_that ) );
