<?php
/**
 * KIỂM SƠ ĐỒ BẢNG — câu CREATE TABLE của MỌI plugin phải qua được `dbDelta()`.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CÓ BÀI NÀY
 * =============================================================================================
 * Ngày 07/09/2026 anh Thắng cài bản có bảng `lenh_tu`, khối trên màn báo *"Bảng sổ lệnh chưa
 * dựng được trên máy chủ"*. Tắt plugin, bật lại — vẫn *"chưa được"*.
 *
 * Thủ phạm là một khối chú thích đặt lọt vào GIỮA câu SQL:
 *
 *     $sql[] = "CREATE TABLE ... (
 *         chi_tiet LONGTEXT NULL,
 *         (dấu mở chú thích) SẮP XẾP THEO stt, KHÔNG THEO luc + id ... (dấu đóng)
 *         stt BIGINT(20) NOT NULL AUTO_INCREMENT,
 *
 * Mấy câu ấy là CHUỖI PHP nhiều dòng, nên khối chú thích không phải chú thích của PHP — nó là
 * văn bản nằm trong chính câu SQL. `dbDelta()` tách câu theo TỪNG DÒNG rồi dò tên cột bằng
 * biểu thức chính quy; gặp mấy dòng chữ ấy nó hiểu nhầm thành cột, sinh ra câu sai, MySQL
 * chối — và `dbDelta()` KHÔNG ném lỗi ra ngoài.
 *
 * Hệ quả là kiểu hỏng tệ nhất: bảng lặng lẽ không có, `$wpdb->insert()` trả false không kêu,
 * màn hình chỉ thấy sổ rỗng — trông y hệt "chưa ai làm gì". Không có gì trong mã trông sai cả.
 *
 * =============================================================================================
 * ⚠️ CANH BẰNG CÁCH ĐỌC MÃ THẬT, KHÔNG CHÉP LẠI DANH SÁCH BẢNG
 * =============================================================================================
 * Bài này bóc mọi chuỗi `CREATE TABLE ... ) $c` ra khỏi tệp sơ đồ của từng plugin rồi soi. Thêm
 * bảng mới vào plugin là bài tự soi luôn bảng ấy — không phải nhớ khai thêm ở đây.
 *
 * Chạy: php tools/test/kiem-so-do-bang.php
 */

$goc = dirname( dirname( __DIR__ ) );

/* Vân tay sơ đồ bảng — xem khối 🔴 ở cuối bài. Đổi sơ đồ thì phải sửa CẢ hằng này LẪN
   `SCHEMA_VERSION`; sửa một cái là bài đỏ, và đó đúng là ý đồ. */
const VAN_TAY_SO_DO = 'so_cot=218';

$dat = 0; $truot = array();
/* Sơ đồ THẬT của từng bảng, gom lúc soi — dùng ở khối đối chiếu bệ đỡ cuối bài. */
$cau_theo_bang = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}

/* Tệp sơ đồ của MỌI plugin trong kho — dò bằng đường dẫn, không gõ tay danh sách. */
$tep = glob( $goc . '/wordpress/*/includes/class-*-db.php' );
t( 'tìm được tệp sơ đồ của các plugin', count( $tep ) >= 3, count( $tep ) );

$tong_bang = 0;
foreach ( $tep as $f ) {
	$ten_tep = basename( dirname( dirname( $f ) ) ) . '/' . basename( $f );
	$src = file_get_contents( $f );

	/* Bốc TỪNG câu CREATE TABLE: từ chữ "CREATE TABLE" tới dấu đóng ngoặc của câu. Bốc bằng
	   mốc mở/đóng của chính câu SQL, không cắt theo dòng — câu nào cũng nhiều dòng. */
	if ( ! preg_match_all( '#CREATE TABLE[\s\S]*?\n\s*\)\s*\$?[a-z_]*"#', $src, $m ) ) { continue; }

	foreach ( $m[0] as $cau ) {
		$tong_bang++;
		/* Tên bảng để câu báo lỗi gọi đúng tên nó. */
		$ten = preg_match( "#self::t\(\s*'([a-z_]+)'\s*\)#", $cau, $mt ) ? $mt[1] : '(không rõ)';
		/* Giữ lại để cuối bài đối chiếu với bản chép tay trong bệ đỡ thử — xem khối 🔴 ở dưới. */
		if ( '(không rõ)' !== $ten ) { $cau_theo_bang[ $ten ] = $cau; }
		$nhan = $ten_tep . ' · ' . $ten;

		/* 🔴 PHÉP CHÍNH: không dấu mở/đóng chú thích nào lọt vào giữa câu. */
		t( "🔴 $nhan: không có chú thích lọt vào giữa câu SQL",
			false === strpos( $cau, '/*' ) && false === strpos( $cau, '*/' ), $cau );

		/* Và không có dòng nào bắt đầu bằng một dấu sao — dấu hiệu chú thích nhiều dòng bị bỏ
		   quên nửa chừng, `dbDelta()` cũng vấp y như trên. */
		foreach ( explode( "\n", $cau ) as $d ) {
			$d = trim( $d );
			if ( $d === '' ) { continue; }
			t( "$nhan: không có dòng mở đầu bằng dấu sao", $d[0] !== '*', $d );
		}

		/* ⚠️ `dbDelta()` ĐÒI HAI KHOẢNG TRẮNG sau PRIMARY KEY. Một khoảng trắng thì nó không
		   nhận ra dòng ấy là khoá chính, và mỗi lần chạy lại nó tưởng thiếu khoá rồi thử thêm
		   — trên site đang chạy thì đó là một câu ALTER hỏng mỗi lượt tải trang. */
		if ( false !== strpos( $cau, 'PRIMARY KEY' ) ) {
			t( "⚠️ $nhan: PRIMARY KEY có ĐÚNG hai khoảng trắng trước ngoặc",
				preg_match( '#PRIMARY KEY  \(#', $cau ) === 1
				&& preg_match( '#PRIMARY KEY (?! )\(#', $cau ) === 0, $cau );
		}

		/* Cột AUTO_INCREMENT phải là khoá — MySQL chối bảng nếu không. */
		if ( false !== strpos( $cau, 'AUTO_INCREMENT' ) ) {
			preg_match( '#\s([a-z_]+) [A-Z]+\([0-9]+\)[^,]*AUTO_INCREMENT#', $cau, $ma );
			$cot = isset( $ma[1] ) ? $ma[1] : '';
			t( "$nhan: đọc được tên cột AUTO_INCREMENT", $cot !== '', $cau );
			if ( $cot !== '' ) {
				t( "🔴 $nhan: cột AUTO_INCREMENT `$cot` có khoá (MySQL đòi)",
					preg_match( '#(PRIMARY KEY  \(' . $cot . '\)|UNIQUE KEY ' . $cot . ' \(' . $cot . '\)|KEY ' . $cot . ' \(' . $cot . '\))#', $cau ) === 1, $cau );
			}
		}

		/* Mỗi câu phải kết bằng bộ mã chữ của WordPress; thiếu thì bảng mang bộ mã mặc định của
		   máy chủ và chữ tiếng Việt hỏng ngay khi host đổi cấu hình. */
		t( "$nhan: có bộ mã chữ ở cuối câu", preg_match( '#\)\s*\$c"$#', $cau ) === 1, substr( $cau, -40 ) );
	}
}

/* Ngưỡng để bài này không lặng lẽ soi 0 bảng khi mốc bóc trượt. Đếm hiện tại là 16 — chỉ gồm
   bảng khai bằng chuỗi `CREATE TABLE` thẳng trong `install()`; plugin chấm công và hợp đồng
   dựng bảng từ mảng `bang()` nên không nằm trong đây, và không sao: chúng không đi qua chỗ
   ghép chuỗi nhiều dòng nên không dính bẫy này. */
t( 'soi được kha khá bảng', $tong_bang >= 12, $tong_bang );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BỆ ĐỠ THỬ PHẢI CÓ ĐỦ CỘT NHƯ SƠ ĐỒ THẬT.
 *
 * `tools/test/wp-stub.php` chép TAY sơ đồ mấy bảng chính sang SQLite. Hai nguồn thì sớm muộn
 * lệch, và lệch ở đây hỏng theo kiểu tệ nhất: mã thật ghi vào một cột bệ đỡ không có, lượt ghi
 * trôi đi lặng lẽ, rồi hàng chục phép ở những bài KHÔNG liên quan cùng đỏ — người sửa đi tìm
 * lỗi ở đúng chỗ vừa đụng vào, trong khi nguyên nhân nằm ở bệ đỡ.
 *
 * Đã cắn thật 08/09/2026: thêm cột `don.ngay_gui_qt`, quên bệ đỡ, và `test-flows.php` đỏ 40
 * phép với những câu như "trạng thái sau quyết toán: nhận được Đã cấp tạm ứng".
 *
 * Chỉ soi bảng nào bệ đỡ CÓ chép: bệ đỡ cố ý không dựng hết mọi bảng, và đòi nó dựng đủ là
 * bắt nó gánh việc nó không định làm.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
$stub = file_get_contents( __DIR__ . '/wp-stub.php' );
function cot_cua_( $sql ) {
	$i = strpos( $sql, '(' );
	$than = ( false === $i ) ? '' : substr( $sql, $i + 1 );
	$ra = array();
	foreach ( explode( ',', $than ) as $d ) {
		$d = trim( $d );
		if ( preg_match( '#^([a-z_][a-z0-9_]*)\s#i', $d, $m ) ) {
			$k = strtolower( $m[1] );
			if ( ! in_array( $k, array( 'primary', 'unique', 'key', 'index' ), true ) ) { $ra[ $k ] = 1; }
		}
	}
	return $ra;
}
$so_doi = 0;
foreach ( $cau_theo_bang as $ten => $cau ) {
	if ( ! preg_match( '#CREATE TABLE \{\$p\}' . preg_quote( $ten, '#' ) . '\s*\((.*?)\)",#s', $stub, $ms ) ) { continue; }
	$so_doi++;
	$that = cot_cua_( $cau );
	$gia  = cot_cua_( '(' . $ms[1] . ')' );
	$thieu = array_diff( array_keys( $that ), array_keys( $gia ) );
	t( "🔴 bệ đỡ thử có đủ cột của bảng `$ten`", ! $thieu, implode( ', ', $thieu ) );
}
/* Ngưỡng: đối chiếu trượt hết mà vẫn xanh thì phép trên chẳng canh gì. */
t( 'đối chiếu được ít nhất vài bảng với bệ đỡ', $so_doi >= 3, $so_doi );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ĐỔI SƠ ĐỒ BẢNG THÌ PHẢI NÂNG `SCHEMA_VERSION` — KHÔNG NÂNG LÀ MẤT DỮ LIỆU, IM LẶNG
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Cắn thật 22/09/2026 — anh Thắng: *"có thấy báo thêm hạng mục, nhưng không thấy gì"*.
 *
 * Bản 1.266.0 thêm cột `chiphi.giai_doan` mà quên nâng `SCHEMA_VERSION`. `vhcp_maybe_upgrade()`
 * chỉ gọi `install()` khi số ấy KHÁC `vhcp_db_version` đang lưu — nên `dbDelta()` không chạy,
 * cột không hề được tạo trong CSDL thật, và MySQL chối MỌI câu `INSERT` vào bảng ấy vì "Unknown
 * column". Người nhập gõ cả buổi, màn báo "Đã thêm dòng" mỗi lần, và sổ vẫn trống.
 *
 * ⚠️ CANH BẰNG VÂN TAY, KHÔNG BẰNG TRÍ NHỚ. Không ai tự nhớ "lần này có đổi sơ đồ không" —
 *    chính em vừa quên. Bài đếm tổng số cột của sơ đồ bản gốc và so với con số ghim ở đầu tệp.
 *    Thêm hay bớt một cột là hai số lệch và bài ĐỎ, kèm đúng lời cần đọc lúc ấy.
 * ⚠️ PHẢI SỬA CẢ HAI SỐ MỚI XANH LẠI: người sửa buộc phải động vào dòng `SCHEMA_VERSION`, tức
 *    buộc phải nghĩ tới lượt nâng cấp CSDL. Đó mới là thứ bài này muốn, không phải con số.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$db_goc = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-db.php' );
$so_cot = 0;
if ( preg_match_all( '#CREATE TABLE[\s\S]*?\n\s*\)\s*\$?[a-z_]*"#', $db_goc, $mc ) ) {
	foreach ( $mc[0] as $c ) {
		foreach ( explode( "\n", $c ) as $d ) {
			$d = trim( $d );
			/* Một dòng cột: tên cột thường + kiểu. Bỏ qua KEY / PRIMARY KEY / UNIQUE KEY. */
			if ( preg_match( '/^[a-z_]+\s+(VARCHAR|TEXT|INT|BIGINT|TINYINT|DECIMAL|DATE|DATETIME|LONGTEXT|MEDIUMTEXT)/i', $d ) ) {
				$so_cot++;
			}
		}
	}
}
t( 'đếm được số cột của sơ đồ bản gốc', $so_cot > 100, $so_cot );
preg_match( "/const SCHEMA_VERSION = '([^']+)'/", $db_goc, $mv );
t( 'đọc được SCHEMA_VERSION', ! empty( $mv[1] ), '' );
t( "🔴 SƠ ĐỒ ĐỔI THÌ `SCHEMA_VERSION` PHẢI ĐỔI THEO.\n"
	. "      Bài này vừa đỏ nghĩa là sơ đồ bảng vừa thêm/bớt cột. Làm hai việc, đủ cả hai:\n"
	. "        1. NÂNG `SCHEMA_VERSION` trong wordpress/vhcp-chi-phi/includes/class-vhcp-db.php\n"
	. "           (đang là " . ( isset( $mv[1] ) ? $mv[1] : '?' ) . ");\n"
	. "        2. sửa `VAN_TAY_SO_DO` ở đầu bài này thành 'so_cot=$so_cot'.\n"
	. "      Bỏ qua việc 1 là `install()` không chạy trên site đang dùng, cột không được tạo,\n"
	. "      và mọi INSERT vào bảng ấy bị MySQL chối — màn vẫn báo xong, sổ vẫn trống.",
	VAN_TAY_SO_DO === ( 'so_cot=' . $so_cot ), 'so_cot=' . $so_cot );

if ( count( $truot ) ) {
	echo "\n=== SƠ ĐỒ BẢNG ===\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat   TRƯỢT: " . count( $truot ) . "\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép trên $tong_bang bảng: mọi câu CREATE TABLE đều qua được dbDelta().\n";
