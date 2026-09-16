<?php
/**
 * LƯỢT GHI SỔ TIỀN HỎNG THÌ PHẢI NÓI RA — KHÔNG ĐƯỢC GẬT ĐẦU
 * =============================================================================================
 *
 * 🔴 CA THẬT ĐANG SỢ: cơ sở dữ liệu trục trặc một nhịp (bảng khoá, đĩa đầy, kết nối đứt) đúng
 *    lúc khách chuyển khoản. `VHG_Thu::ghi()` gọi `$wpdb->insert()`, câu lệnh hỏng, `$wpdb` trả
 *    `false` — mà hàm KHÔNG hỏi tới giá trị đó, cứ trả về `ok = true, moi = true`.
 *
 *    Hậu quả xếp thành một chuỗi, và chuỗi ấy kết thúc bằng MẤT TIỀN, IM LẶNG:
 *      1. `nhan()` thấy `ok` -> đi tiếp như mọi lượt bình thường;
 *      2. cổng trả HTTP 200 kèm `success: true`;
 *      3. SePay đọc 2xx -> coi như đã giao xong -> KHÔNG BAO GIỜ BẮN LẠI gói ấy nữa;
 *      4. sổ tiền không có dòng nào. Không lời báo, không dấu vết, không đường dựng lại.
 *
 *    Đây KHÔNG phải lỗi cộng đôi (`nap()`/`phat_ma()` đã chống gọi lại bằng `xong_luc`). Nó tệ
 *    hơn: cộng đôi thì đối soát cuối tháng còn thấy con số vênh ra mà lần; mất hẳn thì cuối
 *    tháng không có gì để lần cả.
 *
 * ⚠️ NGAY TRONG CÙNG PLUGIN đã có chỗ làm ĐÚNG: `VHG_Ma::phat_ma()` viết
 *        `$ok = $wpdb->insert( ... ); if ( $ok ) { ... }`
 *    Nên đây là chỗ LỆCH CHUẨN nội bộ, không phải chuẩn của cả plugin.
 *
 * ⚠️ CỔNG PHẢI TRẢ KHÁC 2xx KHI GHI SỔ HỎNG — và chỉ khi ấy. Đừng lẫn với gói RÁC: gói không
 *    đọc nổi thì bắn lại bao nhiêu lần cũng vẫn không đọc nổi, nên nó trả 200 (test-ghe.php mục
 *    6 canh đúng chuyện đó — bên gửi thấy mãi khác 2xx là TẮT webhook). Ghi sổ hỏng thì ngược
 *    hẳn: hỏng vì một nhịp trục trặc, bắn lại là ăn ngay, và `ref` UNIQUE khiến bắn lại an
 *    toàn. Một bên bắn lại vô ích, một bên bắn lại là đường cứu.
 *
 * Chạy: php tools/test/kiem-ghi-khoan-thu.php
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

define( 'VHG_TEST', 1 );
define( 'VHG_VERSION', 'test' );
define( 'VHG_DIR', $goc . '/wordpress/vhcp-ghe/' );
define( 'VHG_KHOA_WEBHOOK', 'khoa-webhook-thu-nghiem' );
define( 'VHG_KHOA_MAY', 'khoa-may-thu-nghiem' );
foreach ( array( 'db', 'doc', 'may', 'thu', 'qr', 'ma', 'vi', 'quy', 'chan', 'qrve', 'tep',
	'nhap', 'saoke', 'cong' ) as $f ) {
	require_once VHG_DIR . 'includes/class-vhg-' . $f . '.php';
}

global $wpdb;
function vhg_dung_bang() {
	global $wpdb;
	foreach ( VHG_DB::bang() as $ten => $than ) {
		$bang = $wpdb->prefix . 'vhg_' . $ten;
		$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . $bang );
		$cot = array();
		foreach ( array_filter( array_map( 'trim', explode( "\n", $than ) ) ) as $d ) {
			$d = rtrim( $d, ',' );
			if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\b/', $d ) ) { continue; }
			$cot[] = preg_replace( '/BIGINT\(20\) NOT NULL AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $d );
		}
		$wpdb->exec_raw( 'CREATE TABLE ' . $bang . " (\n" . implode( ",\n", $cot ) . "\n)" );
	}
}
function vhg_dem_thu() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHG_DB::t( 'thu' ) );
}
/** Một lượt ngân hàng bắn webhook. Trả [mã HTTP, thân JSON đã giải]. */
function vhg_ban( $goi ) {
	$GLOBALS['VHG_THAN']       = is_string( $goi ) ? $goi : json_encode( $goi );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['REQUEST_URI']    = '/' . VHG_Cong::DUONG_TIEN;
	$_GET  = array( 'token' => 'khoa-webhook-thu-nghiem' );
	$_POST = array();
	$GLOBALS['VHCP_QVAR']['vhg_cong'] = 'tien';
	$GLOBALS['VHCP_MA_HTTP'] = 200;
	ob_start(); VHG_Cong::phuc_vu(); $ra = ob_get_clean();
	return array( $GLOBALS['VHCP_MA_HTTP'], json_decode( $ra, true ) );
}
/** Bẻ gãy MỘT loại câu lệnh trên bảng sổ tiền, bằng cò chặn của SQLite. */
function vhg_be_gay( $viec ) {
	global $wpdb;
	$wpdb->exec_raw( 'DROP TRIGGER IF EXISTS vhg_chan_ghi' );
	$wpdb->exec_raw( 'CREATE TRIGGER vhg_chan_ghi BEFORE ' . $viec . ' ON ' . VHG_DB::t( 'thu' )
		. " BEGIN SELECT RAISE(ABORT, 'o cung hong'); END" );
}
function vhg_lanh_lai() {
	global $wpdb;
	$wpdb->exec_raw( 'DROP TRIGGER IF EXISTS vhg_chan_ghi' );
}

// ============================================================ 1. Đường chạy bình thường
vhg_dung_bang();
$r = VHG_Thu::ghi( array( 'ref' => 'FT-A1', 'so_tien' => 20000, 'noi_dung' => 'GHE3 AAA',
	'nguon' => 'sepay', 'luc' => '2026-09-16 09:00:00' ) );
teq( 'ghi được thì ok = true', true, ! empty( $r['ok'] ) );
teq( 'và nói đây là dòng MỚI', true, ! empty( $r['moi'] ) );
teq( 'sổ tiền có đúng 1 dòng', 1, vhg_dem_thu() );

$r2 = VHG_Thu::ghi( array( 'ref' => 'FT-A1', 'so_tien' => 20000, 'noi_dung' => 'GHE3 AAA',
	'nguon' => 'sepay', 'luc' => '2026-09-16 09:00:00' ) );
teq( 'bắn lại cùng ref: KHÔNG phải dòng mới', false, ! empty( $r2['moi'] ) );
teq( 'và vẫn chỉ 1 dòng trong sổ', 1, vhg_dem_thu() );

// ============================================================ 2. 🔴 LƯỢT THÊM DÒNG HỎNG
/* Ổ cứng đầy / bảng khoá / kết nối đứt — `$wpdb->insert()` trả `false`. */
vhg_be_gay( 'INSERT' );
$rh = VHG_Thu::ghi( array( 'ref' => 'FT-HONG', 'so_tien' => 50000, 'noi_dung' => 'GHE5 BBB',
	'nguon' => 'sepay', 'luc' => '2026-09-16 09:05:00' ) );
vhg_lanh_lai();

teq( '🔴 thêm dòng HỎNG -> ok phải là false', false, ! empty( $rh['ok'] ) );
t( '🔴 và TUYỆT ĐỐI không được khoe "đã ghi dòng mới"', empty( $rh['moi'] ), $rh );
t( 'kèm câu lý do để người đọc nhật ký hiểu chuyện gì', ! empty( $rh['error'] ), $rh );
teq( 'sổ tiền KHÔNG có thêm dòng nào (đúng như thật)', 1, vhg_dem_thu() );
/* Vẫn phải trả về `ref` — nhật ký của cổng in nó ra, và đó là đầu mối duy nhất để dò lại giao
   dịch vừa trượt trong sao kê ngân hàng. */
teq( 'nhưng VẪN nói ref nào vừa trượt, để còn dò lại', 'FT-HONG',
	isset( $rh['ref'] ) ? $rh['ref'] : '' );

// ============================================================ 3. 🔴 LƯỢT NỚI DÒNG CŨ HỎNG
/* Cùng một nước đi, ở nhánh kia của `ghi()`: giao dịch đã có, lượt này chỉ điền thêm tên máy.
   `$wpdb->update()` hỏng mà vẫn gật đầu thì tên máy không bao giờ về tới sổ, và người đối soát
   tin là đã về. */
vhg_be_gay( 'UPDATE' );
$rn = VHG_Thu::ghi( array( 'ref' => 'FT-A1', 'so_tien' => 20000, 'ten_khai' => 'AMTP 05',
	'nguon' => 'vietqr' ) );
vhg_lanh_lai();
teq( '🔴 nới dòng cũ HỎNG -> ok phải là false', false, ! empty( $rn['ok'] ) );
t( 'kèm câu lý do', ! empty( $rn['error'] ), $rn );
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 `0` KHÁC `false`, VÀ CHỖ NÀY LÀ NƠI LẪN HAI THỨ ẤY ĐẮT NHẤT.
 *
 * MySQL đếm số dòng THỰC SỰ ĐỔI GIÁ TRỊ, không phải số dòng khớp `WHERE`. Webhook bắn lại
 * đúng gói cũ -> `UPDATE` đặt lại y nguyên các giá trị đang có -> MySQL trả 0 -> `$wpdb->update`
 * trả 0. Đó là lượt HOÀN TOÀN LÀNH, và nó là lượt THƯỜNG GẶP NHẤT ở nhánh này.
 *
 * Viết `if ( ! $ok )` thay vì `if ( false === $ok )` là mỗi lượt bắn lại lành lặn đều bị gọi
 * là hỏng: cổng trả 500, SePay bắn lại, lại 500... vòng ấy chạy tới lúc SePay tắt webhook —
 * tức là phép chữa "đừng mất tiền" tự tay làm mất mọi giao dịch sau đó.
 *
 * ⚠️ SQLite của bệ đỡ thử KHÔNG tái hiện được cảnh này: nó đếm số dòng KHỚP `WHERE`, nên
 *    `WHERE id=1` luôn trả 1 dù giá trị không đổi. Đục thử `! $ok` vì thế vẫn xanh. Nên phải
 *    dựng đúng cách MySQL trả lời: bọc `$wpdb` lại, ép `update()` trả 0.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
class VHG_WPDB_Nhu_MySQL {
	private $that;
	public function __construct( $that ) { $this->that = $that; }
	/** Vẫn ghi thật, nhưng trả 0 — y như MySQL khi không giá trị nào đổi. */
	public function update( $bang, $dl, $dk ) { $this->that->update( $bang, $dl, $dk ); return 0; }
	public function __call( $m, $a ) { return call_user_func_array( array( $this->that, $m ), $a ); }
	public function __get( $k ) { return $this->that->$k; }
	public function __set( $k, $v ) { $this->that->$k = $v; }
}
$that = $wpdb;
$GLOBALS['wpdb'] = new VHG_WPDB_Nhu_MySQL( $that );
$rl = VHG_Thu::ghi( array( 'ref' => 'FT-A1', 'so_tien' => 20000, 'noi_dung' => 'GHE3 AAA',
	'nguon' => 'sepay', 'luc' => '2026-09-16 09:00:00' ) );
$GLOBALS['wpdb'] = $that; $wpdb = $that;
teq( '🔴 ghi đè y hệt (MySQL trả 0 dòng đổi) KHÔNG phải là hỏng', true, ! empty( $rl['ok'] ) );
teq( 'và vẫn là "nới dòng cũ", không phải dòng mới', false, ! empty( $rl['moi'] ) );
teq( 'sổ tiền vẫn đúng 1 dòng', 1, vhg_dem_thu() );

// ============================================================ 4. 🔴 CỔNG PHẢI ĐÒI BẮN LẠI
vhg_dung_bang();
vhg_be_gay( 'INSERT' );
list( $ma, $than ) = vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 20000,
	'content' => 'GHE3 T1ABC', 'referenceCode' => 'FT-CONG' ) );
vhg_lanh_lai();

t( '🔴 ghi sổ hỏng -> cổng KHÔNG được trả 2xx (2xx là SePay xoá gói, mất hẳn tiền)',
	$ma < 200 || $ma >= 300, $ma );
teq( 'nói thẳng là chưa xong, để bên gửi bắn lại', false, ! empty( $than['success'] ) );
teq( 'và sổ tiền đúng là chưa có gì', 0, vhg_dem_thu() );

/* ⚠️ Nhật ký phải giữ lại lượt trượt. Cổng trả 500 rồi mà nhật ký trống thì không ai biết đã
   có tiền gõ cửa — bên gửi bắn đủ số lần rồi bỏ cuộc, và mình vẫn không hay. */
$log = VHG_Nhat_Ky::ds( 10 );
t( '🔴 VẪN ghi nhật ký lượt trượt', count( $log ) >= 1, $log );
$co_ly_do = false;
foreach ( $log as $d ) {
	if ( '' !== trim( (string) ( isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '' ) ) ) { $co_ly_do = true; }
}
t( 'và nhật ký kèm lý do, không để trống', $co_ly_do, $log );

// ---- Lành lặn thì vẫn phải 200, không được bắt bên gửi bắn lại vô cớ ----
vhg_dung_bang();
list( $ma_ok, $than_ok ) = vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 20000,
	'content' => 'GHE3 T1ABC', 'referenceCode' => 'FT-LANH' ) );
teq( 'lượt lành: vẫn 200', 200, $ma_ok );
teq( 'và success = true', true, ! empty( $than_ok['success'] ) );
teq( 'sổ tiền có 1 dòng', 1, vhg_dem_thu() );

/* 🔴 GÓI RÁC THÌ NGƯỢC LẠI — bắn lại bao nhiêu lần cũng vẫn không đọc nổi, nên đừng đòi bắn
   lại; đòi mãi là bên gửi TẮT webhook, và từ đó mất mọi giao dịch chứ không riêng gói này.
   Luật này đã có ở test-ghe.php mục 6; canh lại ở đây để lần sửa "cho cổng biết báo hỏng"
   không vô tình kéo luôn gói rác sang nhánh 500. */
vhg_dung_bang();
list( $ma_rac ) = vhg_ban( 'day khong phai JSON' );
teq( '🔴 gói RÁC vẫn trả 200 (bắn lại vô ích, đòi mãi là bị tắt webhook)', 200, $ma_rac );

/* Gói THỬ của SePay cũng vậy: cố ý không vào sổ, và đó là đường chạy ĐÚNG, không phải hỏng. */
vhg_dung_bang();
list( $ma_thu ) = vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 10000,
	'content' => 'SEPAY TEST WEBHOOK', 'referenceCode' => 'FT-THU' ) );
teq( 'gói THỬ của SePay vẫn trả 200', 200, $ma_thu );
teq( 'và đúng là không vào sổ', 0, vhg_dem_thu() );

/* Tiền RA cũng là đường chạy đúng — không ghi sổ, và không phải hỏng. */
vhg_dung_bang();
list( $ma_ra ) = vhg_ban( array( 'transferType' => 'out', 'transferAmount' => 500000,
	'content' => 'tra NCC', 'referenceCode' => 'FT-RA' ) );
teq( 'tiền RA vẫn trả 200', 200, $ma_ra );
teq( 'và không vào sổ', 0, vhg_dem_thu() );

// ============================================================ 5. 🔴 HAI GÓI CÙNG LÚC
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * `ghi()` TRA TRƯỚC RỒI MỚI THÊM — giữa hai nước đi ấy có một khe hở.
 *
 * SePay bắn lại trong lúc gói đầu chưa ghi xong (chuyện thường: webhook hay bắn chồng), hai
 * lượt cùng SELECT thấy "chưa có", rồi cùng INSERT. Chặn duy nhất lúc ấy là `UNIQUE KEY ref`
 * ở chính cơ sở dữ liệu — lượt sau đâm vào khoá và INSERT trả `false`.
 *
 * 🔴 BỆ ĐỠ THỬ CẮT BỎ MỌI DÒNG `UNIQUE KEY` KHI DỰNG BẢNG (xem `vhg_dung_bang()` — nó bỏ qua
 *    mọi dòng khớp `PRIMARY KEY|UNIQUE KEY|KEY`, vì SQLite không hiểu cú pháp ấy của MySQL).
 *    Nghĩa là trên bệ thử, cái chặn duy nhất KHÔNG TỒN TẠI, và không bài nào bắt được lỗi
 *    trùng `ref` ở tầng cơ sở dữ liệu. Nên ở đây dựng lại bảng `thu` KÈM khoá duy nhất, đúng
 *    như sơ đồ thật, rồi mới thử.
 *
 * Với phép kiểm mới, lượt đâm vào khoá trả `ok = false` -> cổng trả 500 -> SePay bắn lại ->
 * lần sau dòng đã có -> rơi vào nhánh "nới" -> yên. KHÔNG cộng đôi, KHÔNG mất.
 * Trước phép kiểm này, lượt ấy trả `ok = true, moi = true` cho một dòng KHÔNG hề được ghi.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
vhg_dung_bang();
$wpdb->exec_raw( 'CREATE UNIQUE INDEX vhg_thu_ref ON ' . VHG_DB::t( 'thu' ) . ' (ref)' );
t( 'sơ đồ thật ĐANG có UNIQUE KEY ref trên bảng thu',
	strpos( VHG_DB::bang()['thu'], 'UNIQUE KEY ref' ) !== false );

$c1 = VHG_Thu::ghi( array( 'ref' => 'FT-DUA', 'so_tien' => 20000, 'noi_dung' => 'GHE3 CCC',
	'nguon' => 'sepay', 'luc' => '2026-09-16 10:00:00' ) );
teq( 'gói thứ nhất vào sổ', true, ! empty( $c1['ok'] ) );

/* Lượt thứ hai đâm thẳng vào khoá duy nhất: giả cảnh hai gói chạy song song, lượt sau đã qua
   khỏi phép tra "đã có chưa" trước khi lượt trước kịp ghi. */
$dua = $wpdb->insert( VHG_DB::t( 'thu' ), array( 'ref' => 'FT-DUA', 'luc' => '2026-09-16 10:00:00',
	'so_tien' => 20000, 'nguon' => 'sepay', 'noi_dung' => 'GHE3 CCC' ) );
teq( '🔴 khoá duy nhất chặn được dòng trùng ref', false, $dua );
teq( 'và sổ vẫn đúng 1 dòng — KHÔNG cộng đôi', 1, vhg_dem_thu() );

// ============================================================ 6. Soi mã: đừng để lệch lại
$ma_thu_php = file_get_contents( VHG_DIR . 'includes/class-vhg-thu.php' );
$ma_cong    = file_get_contents( VHG_DIR . 'includes/class-vhg-cong.php' );
$ma_ma      = file_get_contents( VHG_DIR . 'includes/class-vhg-ma.php' );

/** Thân một hàm tĩnh, cắt tới dấu đóng ngoặc thụt đúng một tab — KHÔNG cắt theo số ký tự.
 *  (Cắt theo số ký tự đã một lần tràn sang hàm kế bên và để lọt hẳn một phép thử.) */
function vhg_than_ham( $ma, $ten ) {
	$i = strpos( $ma, 'function ' . $ten . '(' );
	if ( false === $i ) { $i = strpos( $ma, 'function ' . $ten . ' (' ); }
	if ( false === $i ) { return ''; }
	$j = strpos( $ma, "\n\t}", $i );
	return false === $j ? substr( $ma, $i ) : substr( $ma, $i, $j - $i );
}

$than_ghi = vhg_than_ham( $ma_thu_php, 'ghi' );
t( 'đọc được thân hàm ghi()', '' !== $than_ghi );
/* 🔴 Bắt TÍNH CHẤT chứ không bắt tên biến: mọi lời gọi ghi cơ sở dữ liệu trong `ghi()` phải có
      giá trị trả về được HỨNG lại. `$wpdb->insert( ... );` đứng trơ một mình là lỗi này. */
foreach ( array( 'insert', 'update' ) as $viec ) {
	t( '🔴 ghi(): KHÔNG được gọi $wpdb->' . $viec . '() mà bỏ rơi kết quả',
		! preg_match( '/(^|[;{}]\s*)\$wpdb->' . $viec . '\s*\(/m', preg_replace( '/\s+/', ' ', $than_ghi ) ),
		$than_ghi );
}
t( '🔴 ghi(): phải phân biệt "hỏng" (false) với "0 dòng đổi" — so sánh chặt với false',
	strpos( $than_ghi, 'false ===' ) !== false || strpos( $than_ghi, '=== false' ) !== false,
	$than_ghi );
t( 'ghi(): có nhánh trả ok = false cho lượt ghi hỏng',
	preg_match( "/'ok'\s*=>\s*false/", $than_ghi ) === 1, $than_ghi );

/* Chuẩn nội bộ đã có sẵn ở `phat_ma()` — canh để nó đừng bị gỡ mất, vì nó là bằng chứng rằng
   luật này là luật của cả plugin chứ không phải sáng kiến riêng của một hàm. */
$than_pm = vhg_than_ham( $ma_ma, 'phat_ma' );
t( 'phat_ma() vẫn hứng kết quả $wpdb->insert() (chuẩn nội bộ)',
	preg_match( '/\$\w+\s*=\s*\$wpdb->insert\s*\(/', $than_pm ) === 1, $than_pm );

/* Cổng: nhánh báo hỏng phải có thật, và phải khác 2xx. */
t( '🔴 cổng có nhánh trả mã khác 2xx khi ghi sổ hỏng',
	preg_match( '/self::tra\(\s*(5\d\d|4\d\d)/', $ma_cong ) === 1, 'không thấy' );
t( 'cổng vẫn giữ nhánh 200 cho gói không đọc được',
	strpos( $ma_cong, "'parsed' => 0" ) !== false );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — lượt ghi sổ hỏng không còn gật đầu, và cổng đòi bắn lại.\n";
