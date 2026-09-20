<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỌI BẢN GHI MỚI PHẢI ĐÓNG DẤU MẢNG — KVC · MTĐ · VP CHUNG MỘT KHO.
 *
 * Anh Thắng 20/09/2026: *"nhớ tách ra từng mảng. Thì dữ liệu nhớ định dạng sau để tìm biết chi
 * đó của mảng nào nhé"*.
 *
 * =============================================================================================
 * 🔴 CỘT CÓ MÀ KHÔNG AI GHI THÌ CỘT ẤY NÓI DỐI
 * =============================================================================================
 * Bước 1 mở cột `mang` cho tám bảng, `NOT NULL DEFAULT 'kvc'`. Mặc định ấy đúng HÔM NAY vì mới
 * có một mảng — và đó chính là chỗ nguy: mọi thứ trông như đang chạy.
 *
 * Tới lúc dữ liệu MTĐ / VP về chung kho, một dòng do người Văn phòng ghi ra mà dựa vào mặc định
 * thì nó đóng dấu **'kvc'**. Tiền của mảng này chạy sang sổ mảng kia, không câu lỗi nào, và
 * không cách nào tách lại — vì chính cái cột dùng để tách đã sai.
 *
 * ⚠️ NÊN PHÉP Ở ĐÂY KHÔNG HỎI "CÓ CỘT KHÔNG" mà hỏi "CÓ GHI KHÔNG". Quét mọi lệnh `insert` vào
 *    tám bảng ấy và đòi mỗi lệnh phải có `mang` đi kèm. Thêm bảng mới, thêm đường ghi mới mà
 *    quên đóng dấu là phép này chỉ tận nơi.
 *
 * Chạy: php tools/test/kiem-dong-dau-mang.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$DIR = $goc . '/wordpress/vhcp-chi-phi/includes';
$SRC = '';
$TEP = array();
foreach ( (array) glob( $DIR . '/*.php' ) as $f ) {
	$n = (string) @file_get_contents( $f );
	$TEP[ basename( $f ) ] = $n;
	$SRC .= $n;
}
t( 'đọc được mã nguồn', mb_strlen( $SRC ) > 10000 );

/* ═══ 1. DANH SÁCH BẢNG PHẢI KHỚP SƠ ĐỒ THẬT ═══════════════════════════════════════
 * 🔴 Đọc từ chính `BANG_CO_MANG`, đừng chép tay: hai danh sách là hai chỗ lệch nhau, và chỗ
 *    lệch sẽ là cái bảng không ai canh. */
$BANG = VHCP_DB::BANG_CO_MANG;
t( 'bốc được danh sách bảng mang cột `mang`', count( $BANG ) >= 8, $BANG );
/* Và mỗi bảng ấy phải THẬT SỰ có cột trong câu CREATE TABLE. */
$ddl = (string) @file_get_contents( $DIR . '/class-vhcp-db.php' );
foreach ( $BANG as $b ) {
	$i = mb_strpos( $ddl, "self::t( '" . $b . "' ) . \" (" );
	$khoi = false === $i ? '' : mb_substr( $ddl, $i, mb_strpos( $ddl, ') $c";', $i ) - $i );
	t( "bảng `$b` có khai cột `mang` trong sơ đồ", false !== mb_strpos( $khoi, 'mang VARCHAR' ), $b );
	t( "   và có chỉ mục cho nó (mọi màn đều lọc theo)", false !== mb_strpos( $khoi, 'KEY mang (mang)' ), $b );
}

/* ═══ 2. 🔴 MỌI LỆNH GHI VÀO TÁM BẢNG ẤY ĐỀU PHẢI ĐÓNG DẤU ═════════════════════════ */
$thieu = array();
$vung_ghi = array();
$dem   = 0;
foreach ( $TEP as $ten_tep => $src ) {
	foreach ( $BANG as $b ) {
		$off = 0;
		while ( false !== ( $i = mb_strpos( $src, "insert( VHCP_DB::t( '" . $b . "' )", $off ) ) ) {
			$off = $i + 10;
			$dem++;
			/* Cửa sổ đủ rộng để ôm hết mảng dữ liệu của lệnh ghi. `so_chi` truyền một biến
			   `$data` dựng phía trên, nên phải soi cả đoạn TRƯỚC lệnh ghi. */
			$tu  = max( 0, $i - 1200 );
			$vung = mb_substr( $src, $tu, ( $i - $tu ) + 1200 );
			/* ⚠️ GỠ CHÚ THÍCH TRƯỚC KHI TÌM. Bản đầu tìm chữ `mang` trong nguyên khối mã, và hai
			   đột biến "bỏ hẳn dòng đóng dấu" vẫn XANH — vì ngay cạnh mỗi lệnh ghi có một khối
			   chú thích dài nói về mảng, và phép đi bắt đúng mấy chữ ấy. Phép xanh nhờ lời văn
			   là phép không canh gì cả. */
			$sach = preg_replace( '#/\*.*?\*/#su', '', $vung );
			$sach = preg_replace( '#//[^\n]*#u', '', (string) $sach );
			$vung_ghi[] = array( 'ten' => $ten_tep . ' → ' . $b, 'src' => (string) $sach );
			/* Và đòi đúng PHÉP GÁN, không phải chữ `mang` trôi nổi. */
			if ( ! preg_match( "/'mang'\s*=>|\['mang'\]\s*=/u", (string) $sach ) ) {
				$thieu[] = $ten_tep . ' → ' . $b;
			}
		}
	}
}
t( 'có tìm thấy lệnh ghi để soi (phép trên không xanh vì vùng rỗng)', $dem >= 6, $dem );
teq( '🔴 mọi lệnh ghi vào bảng có cột `mang` đều đóng dấu mảng', array(), $thieu );

/* ═══ 3. ĐÓNG DẤU BẰNG `VHCP_DB::mang()`, KHÔNG GÕ CỨNG ════════════════════════════
 * 🔴 Gõ thẳng 'kvc' thì bản Máy Tự Động / Văn Phòng sinh ra từ `tach-ban-vung.sh` cũng đóng
 *    dấu 'kvc' — script ấy chỉ đổi tiền tố, không chạm chuỗi thường. Đúng cái bẫy hằng `MANG`
 *    sinh ra để tránh.
 *
 * ⚠️ CHỈ SOI VÙNG GHI, KHÔNG QUÉT CẢ TỆP. Bản đầu của phép này tìm mọi `'mang' => …` trong mã
 *    và bắn nhầm hàng loạt: `'mang' => $r['mang']` là chỗ ĐỌC ra để gửi xuống màn, còn
 *    `class-vhcp-trama.php` thì dùng chính chữ `mang` cho một nghĩa KHÁC HẲN (nguồn của dòng:
 *    'sochi' · 'don' · 'kt' · 'mkt'). Một phép bắn nhầm là một phép sẽ bị tắt.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
$xau = array();
foreach ( $vung_ghi as $v ) {
	if ( preg_match_all( "/'mang'\s*=>\s*([^,\n]+)/u", $v['src'], $m ) ) {
		foreach ( $m[1] as $x ) {
			$x = trim( $x );
			if ( false === mb_strpos( $x, 'VHCP_DB::mang()' ) ) { $xau[] = $v['ten'] . ': ' . $x; }
		}
	}
}
teq( '🔴 chỗ nào đóng dấu cũng gọi `VHCP_DB::mang()`, không gõ cứng tên mảng', array(), $xau );

/* ═══ 4. HẰNG MẢNG CỦA BẢN GỐC, VÀ SCRIPT TÁCH PHẢI ĐỔI ĐƯỢC NÓ ════════════════════ */
teq( 'bản gốc là mảng kvc', 'kvc', VHCP_DB::MANG );
teq( '   và `mang()` trả đúng hằng ấy', 'kvc', VHCP_DB::mang() );
$sh = (string) @file_get_contents( $goc . '/tools/tach-ban-vung.sh' );
t( '🔴 script tách bản vùng có viết lại `const MANG`',
	false !== mb_strpos( $sh, 'const MANG' ), 'không thấy' );
t( '   và DỪNG HẲN nếu lượt thay trượt — thay hụt còn tệ hơn không thay',
	false !== mb_strpos( $sh, 'Mảng chưa đổi' ) && false !== mb_strpos( $sh, 'exit 6' ) );
/* Đối chứng: hai bản vùng đã sinh ra phải mang đúng mã của mình. */
foreach ( array( 'mtd', 'vp' ) as $ma ) {
	$f = $goc . '/wordpress/vhcp-chi-phi-' . $ma . '/includes/class-vhcp-db.php';
	$n = (string) @file_get_contents( $f );
	t( "bản `$ma` mang đúng `const MANG = '$ma'`",
		false !== mb_strpos( $n, "const MANG = '" . $ma . "';" ), $ma );
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: mọi bản ghi mới đều đóng dấu mảng, và bản vùng mang đúng dấu của mình.\n";
