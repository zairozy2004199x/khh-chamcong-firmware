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

$dat = 0; $truot = array();
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

if ( count( $truot ) ) {
	echo "\n=== SƠ ĐỒ BẢNG ===\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat   TRƯỢT: " . count( $truot ) . "\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép trên $tong_bang bảng: mọi câu CREATE TABLE đều qua được dbDelta().\n";
