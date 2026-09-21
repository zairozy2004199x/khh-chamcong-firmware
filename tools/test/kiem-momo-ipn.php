<?php
/**
 * TRẠM NGHE IPN MoMo — chặn đúng, ghi đủ, và TUYỆT ĐỐI chưa đụng vào sổ tiền.
 *
 * 18/09/2026 mở đường nhận IPN của MoMo. Bước một chỉ nghe và ghi nhật ký, vì thứ tự trường
 * trong chuỗi ký nằm ở trang tài liệu chưa đọc được — đoán rồi kiểm là chữ ký luôn sai mà không
 * nói ra được nguyên nhân.
 *
 * Ba điều bài này khoá, theo thứ tự từ nguy tới nhẹ:
 *
 *   1. 🔴 KHÔNG DÒNG NÀO CHẠM SỔ MoMo. Cổng này công khai (MoMo không đăng nhập được), nên nếu
 *      nó ghi thẳng vào sổ thì bất kỳ ai biết đường dẫn cũng bơm được doanh thu giả vào đối
 *      soát. Chừng nào chưa kiểm chữ ký thì một dòng vào sổ cũng là một dòng quá nhiều.
 *   2. Chối IP lạ, nhưng chối rồi VẪN GHI kèm lý do — "MoMo bảo đã gọi mà hệ không thấy gì" là
 *      ca tốn thời gian nhất, có nhật ký thì nó thành câu trả lời trong ba mươi giây.
 *   3. Chối thì trả 204 chứ không 403: nói ra "IP sai" là chỉ đường cho người đang dò.
 *
 * Chạy: php tools/test/kiem-momo-ipn.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/momo.php';
require_once $goc . '/momo-ipn.php';

$dat = 0; $hong = array();
function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) { $dat++; } else { $hong[] = $ten; }
}

global $wpdb;
$bang = khh_dt_bang_momo_ipn();
$wpdb->exec_raw( "DROP TABLE IF EXISTS $bang" );
$wpdb->exec_raw(
	"CREATE TABLE $bang (
		id INTEGER PRIMARY KEY AUTOINCREMENT, luc TEXT NOT NULL, ip TEXT NOT NULL DEFAULT '',
		ket_qua TEXT NOT NULL DEFAULT '', ly_do TEXT NOT NULL DEFAULT '', than TEXT NOT NULL DEFAULT '' )"
);
/* Sổ MoMo thật — dựng để CHỨNG MINH cổng IPN không đụng vào nó. */
$so = khh_dt_bang_momo_sk();
$wpdb->exec_raw( "DROP TABLE IF EXISTS $so" );
$wpdb->exec_raw(
	"CREATE TABLE $so ( id INTEGER PRIMARY KEY AUTOINCREMENT, ma_gd TEXT NOT NULL DEFAULT '' UNIQUE,
		ma_don TEXT DEFAULT '', ngay TEXT NOT NULL DEFAULT '', gio INTEGER DEFAULT 0,
		so_tien REAL DEFAULT 0, trang_thai TEXT DEFAULT '', loai_gd TEXT DEFAULT '',
		nguon_tien TEXT DEFAULT '', ma_ch TEXT DEFAULT '', ten_ch TEXT DEFAULT '', nap_luc TEXT NULL )"
);

/** Giả một lượt MoMo gọi tới. */
function goi( $ip, $than, $method = 'POST' ) {
	$_SERVER['REMOTE_ADDR'] = $ip;
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP'] );
	$req = new WP_REST_Request( $method );
	$req->set_body( $than );
	return khh_dt_momo_ipn_nhan( $req );
}

$THAN = '{"partnerCode":"MOMOABC","orderId":"DH1","requestId":"RQ1","amount":50000,'
	. '"transId":2147483647,"resultCode":0,"message":"Successful.","payType":"qr",'
	. '"responseTime":1789000000000,"signature":"abc123"}';

/* ── 1. IP đúng: nhận và ghi ───────────────────────────────────────────────────────────── */
$r = goi( '118.69.210.244', $THAN );
phep( 'IP MoMo thì nhận', 204 === $r->get_status() );
$d = khh_dt_momo_ipn_ds( 5 );
phep( 'có ghi nhật ký', count( $d ) >= 1 );
phep( 'ghi là "nhận"', isset( $d[0]['ket_qua'] ) && 'nhận' === $d[0]['ket_qua'] );
phep( 'ghi NGUYÊN VĂN thân MoMo gửi', isset( $d[0]['than'] ) && $d[0]['than'] === $THAN );
phep( 'lý do nói rõ CHƯA vào sổ', isset( $d[0]['ly_do'] ) && false !== strpos( $d[0]['ly_do'], 'CHƯA vào sổ' ) );

/* ── 2. 🔴 PHÉP QUAN TRỌNG NHẤT: sổ MoMo vẫn TRỐNG ────────────────────────────────────── */
$n_so = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $so" );
phep( '🔴 cổng IPN KHÔNG ghi một dòng nào vào sổ MoMo', 0 === $n_so );

/* ── 3. IP lạ: chối, nhưng vẫn ghi ─────────────────────────────────────────────────────── */
$r2 = goi( '203.0.113.9', $THAN );
phep( 'IP lạ thì chối', 204 === $r2->get_status() );
phep( '🔴 chối cũng trả 204, không phải 403 (đừng chỉ đường cho người dò)', 204 === $r2->get_status() );
$d2 = khh_dt_momo_ipn_ds( 5 );
phep( 'lượt bị chối VẪN được ghi', 'chối' === $d2[0]['ket_qua'] );
phep( 'và ghi kèm LÝ DO', false !== strpos( $d2[0]['ly_do'], 'IP' ) );
phep( 'ghi cả IP lạ để soi', '203.0.113.9' === $d2[0]['ip'] );
phep( 'sổ MoMo vẫn trống sau lượt bị chối',
	0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $so" ) );

/* ── 4. GET để soi đường dẫn còn sống ──────────────────────────────────────────────────── */
$r3 = goi( '118.69.210.244', '', 'GET' );
phep( 'mở bằng trình duyệt thì trả 200', 200 === $r3->get_status() );
$du = $r3->get_data();
phep( 'nói ra đường dẫn đang sống', ! empty( $du['song'] ) );
phep( 'và nói ra IP của người mở (để đối chiếu danh sách cho phép)', isset( $du['ip_cua_ban'] ) );

/* ── 5. Danh sách IP sửa được bằng lọc, không phải sửa mã ──────────────────────────────── */
phep( 'mặc định cho phép đúng IP outgoing trong tài liệu',
	in_array( '118.69.210.244', khh_dt_momo_ip_cho_phep(), true ) );

/* ── 6. X-Forwarded-For: lấy mẩu ĐẦU, và không tin mù ──────────────────────────────────── */
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '118.69.210.244, 10.0.0.1';
phep( 'lấy mẩu đầu của X-Forwarded-For (bên gọi gốc)', '118.69.210.244' === khh_dt_momo_ip_goi() );
unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

/* ── 7. Thân quá dài bị cắt, không nhét nguyên vào cơ sở dữ liệu ───────────────────────── */
goi( '118.69.210.244', str_repeat( 'x', 50000 ) );
$d3 = khh_dt_momo_ipn_ds( 1 );
phep( 'thân quá dài bị cắt ở 8KB', strlen( $d3[0]['than'] ) <= 8192 );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: trạm nghe IPN ghi đủ, chặn đúng, và chưa đụng vào sổ tiền.\n";
