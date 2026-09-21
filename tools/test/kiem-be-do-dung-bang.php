<?php
/**
 * BỆ ĐỠ THỬ PHẢI DỰNG BẢNG KÈM CẢ KHOÁ
 * =============================================================================================
 *
 * 🔴 CHUYỆN ĐÃ XẢY RA: năm chỗ trong bộ thử chép nhau cùng một lối dựng bảng, và lối ấy VỨT
 *    SẠCH mọi dòng khoá:
 *        if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\b/', $d ) ) { continue; }
 *    (SQLite không hiểu cú pháp khoá của MySQL, nên cách nhanh nhất là bỏ qua.)
 *
 *    Hậu quả: trên bệ thử, `UNIQUE KEY ref (ref)` KHÔNG TỒN TẠI. Mà ở plugin Ghế, khoá ấy là
 *    thứ DUY NHẤT chặn cộng đôi khi hai gói webhook chạy song song — `VHG_Thu::ghi()` tra
 *    trước rồi mới thêm, giữa hai nước đi có một khe hở, và cơ sở dữ liệu là chốt cuối.
 *
 *    Bệ đỡ bỏ chốt ấy đi thì không bài nào bắt được lỗi trùng ở tầng cơ sở dữ liệu, dù bộ thử
 *    có hai nghìn phép. 🔴 BỆ ĐỠ NÓI DỐI VỀ LƯỢC ĐỒ CÒN NGUY HƠN KHÔNG CÓ BỆ ĐỠ: không có thì
 *    mình biết là chưa thử, còn nói dối thì mình tin là đã thử rồi.
 *
 * ⚠️ Lối ĐÚNG vốn đã có sẵn trong kho — `test-cham-cong.php` từ lâu dịch dòng khoá thành
 *    `CREATE INDEX` rời thay vì vứt. Nên đây là chỗ phía Ghế lệch chuẩn nội bộ, không phải
 *    chuyện chưa ai nghĩ ra.
 *
 * Bài này canh chính BỆ ĐỠ: hàm dịch có đúng không, và có bài nào lẻn về nếp cũ không.
 *
 * Chạy: php tools/test/kiem-be-do-dung-bang.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
if ( ! is_dir( $goc . '/wordpress' ) ) { $goc = dirname( __DIR__, 2 ); }

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

// ============================================================ 1. Hàm dịch lược đồ
$ddl = vhcp_stub_ddl( 'wp_x_thu', "
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	ref VARCHAR(64) NOT NULL DEFAULT '',
	luc DATETIME NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY ref (ref),
	KEY luc (luc)" );

t( 'cột tự tăng đổi sang lối SQLite',
	strpos( $ddl['tao'], 'id INTEGER PRIMARY KEY AUTOINCREMENT' ) !== false, $ddl['tao'] );
/* SQLite đòi AUTOINCREMENT đi liền `INTEGER PRIMARY KEY`; khai thêm một dòng `PRIMARY KEY (id)`
   rời là lỗi cú pháp, nên dòng ấy phải được nuốt — nhưng chỉ khi đã có cột tự tăng. */
t( 'không khai PRIMARY KEY rời khi đã có cột tự tăng',
	strpos( $ddl['tao'], "\nPRIMARY KEY (id)" ) === false, $ddl['tao'] );

teq( '🔴 hai dòng khoá thành hai chỉ mục, KHÔNG bị vứt', 2, count( $ddl['chi_muc'] ) );
t( '🔴 UNIQUE KEY giữ được chữ UNIQUE (mất nó là mất chốt chặn trùng)',
	strpos( $ddl['chi_muc'][0], 'CREATE UNIQUE INDEX' ) === 0, $ddl['chi_muc'][0] );
t( 'KEY thường thì KHÔNG được thành UNIQUE (bịa ra ràng buộc không có thật còn tệ hơn)',
	strpos( $ddl['chi_muc'][1], 'CREATE INDEX' ) === 0, $ddl['chi_muc'][1] );

/* ⚠️ MySQL đặt tên chỉ mục theo TỪNG BẢNG, SQLite đặt theo CẢ cơ sở dữ liệu — mà lược đồ Ghế
   dùng lại `ref`, `luc`, `ma`, `nguoi`, `cho` ở nhiều bảng. Thiếu tiền tố tên bảng là bảng thứ
   hai dựng trượt vì "index already exists", và bài thử chết giữa chừng chứ không báo sai. */
foreach ( $ddl['chi_muc'] as $ix ) {
	t( '🔴 tên chỉ mục kèm tên bảng: ' . $ix, strpos( $ix, 'INDEX ix_wp_x_thu_' ) !== false, $ix );
}

/* Bảng KHÔNG có cột tự tăng thì dòng PRIMARY KEY rời phải được GIỮ — nuốt luôn là mất khoá
   chính, và bảng ấy nhận dòng trùng. */
$ddl2 = vhcp_stub_ddl( 'wp_x_meta', "
	k VARCHAR(64) NOT NULL,
	v LONGTEXT NULL,
	PRIMARY KEY  (k)" );
t( '🔴 bảng không có cột tự tăng thì GIỮ PRIMARY KEY rời',
	strpos( $ddl2['tao'], 'PRIMARY KEY (k)' ) !== false, $ddl2['tao'] );

/* Độ dài tiền tố kiểu MySQL (`noi_dung(50)`) — SQLite hiểu `(50)` thành lời gọi hàm rồi chết. */
$ddl3 = vhcp_stub_ddl( 'wp_x_log', "
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	noi_dung TEXT NULL,
	PRIMARY KEY  (id),
	KEY nd (noi_dung(50))" );
t( 'bỏ độ dài tiền tố kiểu MySQL khỏi cột chỉ mục',
	strpos( $ddl3['chi_muc'][0], '(50)' ) === false, $ddl3['chi_muc'][0] );

// ============================================================ 2. Dựng thật, và khoá phải CẮN
global $wpdb;
define( 'VHG_TEST', 1 );
define( 'VHG_VERSION', 'test' );
define( 'VHG_DIR', $goc . '/wordpress/vhcp-ghe/' );
require_once VHG_DIR . 'includes/class-vhg-db.php';

vhcp_stub_dung_bang( VHG_DB::bang(), $wpdb->prefix . 'vhg_' );

/* 🔴 KHÔNG CÂU DDL NÀO ĐƯỢC TRƯỢT. Phép này phải đứng TRƯỚC mọi phép khác trong mục: bảng
   dựng hỏng thì tất cả phép dưới đều nói về một lược đồ không tồn tại, và chúng sẽ trượt vì
   lý do sai — người đọc đi sửa nhầm chỗ.
   Nó cũng là phép bắt được lượt đục "bỏ tiền tố tên bảng khỏi tên chỉ mục": SQLite đặt tên chỉ
   mục theo CẢ cơ sở dữ liệu, mà lược đồ Ghế dùng lại `ref`, `luc`, `ma`, `nguoi`, `cho` ở
   nhiều bảng, nên bảng thứ hai chối ngay. */
t( '🔴 dựng cả lược đồ Ghế: KHÔNG câu DDL nào trượt',
	count( vhcp_stub_loi_ddl() ) === 0, vhcp_stub_loi_ddl() );

$ix = $wpdb->get_results(
	"SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'ix_%'", ARRAY_A );
t( 'dựng được cả lược đồ Ghế kèm chỉ mục', count( $ix ) > 30, count( $ix ) );

/* 🔴 PHÉP CỐT LÕI: khoá duy nhất phải CẮN THẬT, không chỉ tồn tại trên giấy. */
$bang = VHG_DB::t( 'thu' );
$hang = array( 'ref' => 'FT-TRUNG', 'luc' => '2026-09-16 08:00:00', 'so_tien' => 20000, 'nguon' => 'qr' );
$a = $wpdb->insert( $bang, $hang );
t( 'thêm dòng đầu: được', false !== $a, $a );
$b = $wpdb->insert( $bang, $hang );
teq( '🔴 thêm dòng TRÙNG ref: cơ sở dữ liệu phải CHỐI', false, $b );
teq( 'và sổ chỉ có đúng 1 dòng', 1,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $bang ) );

/* Khoá duy nhất NHIỀU CỘT (`UNIQUE KEY o (ma_may,ma_lenh)`) — trùng cả cặp mới là trùng. */
$cho = VHG_DB::t( 'cho' );
$wpdb->insert( $cho, array( 'ma_may' => '3', 'ma_lenh' => 'AAA', 'so_tien' => 20000 ) );
teq( '🔴 khoá nhiều cột: trùng CẢ CẶP thì chối', false,
	$wpdb->insert( $cho, array( 'ma_may' => '3', 'ma_lenh' => 'AAA', 'so_tien' => 20000 ) ) );
t( 'nhưng khác một cột thì vẫn nhận (đừng chặn oan)',
	false !== $wpdb->insert( $cho, array( 'ma_may' => '3', 'ma_lenh' => 'BBB', 'so_tien' => 20000 ) ) );

/* Chỉ mục THƯỜNG thì không được chặn gì cả — bịa ra ràng buộc không có thật là bộ thử báo
   trượt cho mã hoàn toàn đúng, và người ta sẽ đi sửa cái đang lành. */
$n = VHG_DB::t( 'nhat_ky' );
$wpdb->insert( $n, array( 'luc' => '2026-09-16 08:00:00', 'nguon' => 'sepay' ) );
t( 'chỉ mục thường KHÔNG chặn dòng giống nhau',
	false !== $wpdb->insert( $n, array( 'luc' => '2026-09-16 08:00:00', 'nguon' => 'sepay' ) ) );

// ============================================================ 3. Đừng ai lẻn về nếp cũ
/* Bắt TÍNH CHẤT, không bắt tên hàm: bài nào tự dựng bảng của Ghế mà lại vứt dòng khoá thì
   chính là lỗi này quay lại, dù nó đặt tên biến gì. */
$le = array();
foreach ( glob( __DIR__ . '/*.php' ) as $f ) {
	$ten = basename( $f );
	if ( 'wp-stub.php' === $ten || 'kiem-be-do-dung-bang.php' === $ten ) { continue; }
	$ma = file_get_contents( $f );
	/* Chỉ soi bài nào THẬT SỰ DỰNG bảng — bài chỉ quét lược đồ để soát kiểu cột
	   (`test-cham-cong.php`, `test-flows.php`) thì bỏ qua dòng khoá là ĐÚNG. */
	if ( strpos( $ma, 'CREATE TABLE ' ) === false ) { continue; }
	if ( preg_match( '/CREATE TABLE.{0,400}/s', $ma ) && preg_match(
			"#continue;.{0,200}CREATE TABLE ' \. #s",
			preg_replace( '/\s+/', ' ', $ma ) ) ) {
		$le[] = $ten;
	}
}
t( '🔴 không bài nào dựng bảng mà vứt dòng khoá', count( $le ) === 0, implode( ', ', $le ) );

/* Và năm bài phía Ghế phải dùng ĐÚNG MỘT lối dựng — hai lối là hai bộ thử nói về hai lược đồ
   khác nhau, mà chỉ một trong hai giống thật. */
/**
 * Tước chú thích trước khi soi.
 *
 * 🔴 Không tước thì một dòng chú thích NHẮC TÊN hàm cũng làm phép soi xanh — và đúng chuyện ấy
 *    đã xảy ra ngay lúc đục thử bài này: gỡ lời gọi thật ra, để lại mỗi câu "xem
 *    `vhcp_stub_dung_bang()` trong wp-stub.php" ở trên, thế là phép soi gật đầu.
 *
 * ⚠️ `(?<!:)//` chứ không phải `//`: thiếu dấu chặn ấy thì bộ tước ăn luôn hai gạch trong
 *    "https://" và nuốt mất phần còn lại của dòng — một bộ soi MÙ mà vẫn xanh.
 */
function bd_tuoc_chu_thich( $ma ) {
	$ma = preg_replace( '#/\*.*?\*/#s', ' ', (string) $ma );
	return preg_replace( '#(?<!:)//[^\n]*#', ' ', $ma );
}

$thieu = array();
foreach ( array( 'test-ghe.php', 'kiem-ghi-khoan-thu.php', 'kiem-sao-ke-ngan-hang.php',
	'kiem-va-ten-tu-sao-ke.php', 'kiem-day-coso-tu-ghe.php' ) as $f ) {
	$ma = bd_tuoc_chu_thich( file_get_contents( __DIR__ . '/' . $f ) );
	if ( strpos( $ma, 'vhcp_stub_dung_bang(' ) === false ) { $thieu[] = $f; }
}
/* Và tự canh chính bộ tước: nó phải thật sự ăn được chú thích, không thì phép trên là bộ soi
   mù. Cùng loại bẫy đã làm `kiem-khoa-khong-vao-dia-chi.php` xanh 40/40 trong khi lỗi còn nguyên. */
t( 'bộ tước chú thích ăn được khối /* */',
	strpos( bd_tuoc_chu_thich( "a /* vhcp_stub_dung_bang( x ) */ b" ), 'vhcp_stub_dung_bang' ) === false );
t( 'và ăn được dòng //',
	strpos( bd_tuoc_chu_thich( "a\n// vhcp_stub_dung_bang( x )\nb" ), 'vhcp_stub_dung_bang' ) === false );
t( '🔴 nhưng KHÔNG ăn nhầm hai gạch trong https://',
	strpos( bd_tuoc_chu_thich( "\$u = 'https://a.test/x'; giu_lai();" ), 'giu_lai' ) !== false );
t( '🔴 mọi bài phía Ghế dùng chung một lối dựng bảng', count( $thieu ) === 0, implode( ', ', $thieu ) );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — bệ đỡ dựng bảng kèm cả khoá, và khoá cắn thật.\n";
