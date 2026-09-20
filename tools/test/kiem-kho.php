<?php
/**
 * SỔ KHO HÀNG HOÁ: HAI CON SỐ ĐỘC LẬP, VÀ CÁI LỆCH PHẢI CHỈ RA ĐƯỢC.
 *
 * 20/09/2026 anh Thắng chốt: nhân viên khai cuối ngày *"còn bao nhiêu bán bao nhiêu"*, hệ
 * *"đối chiếu với dữ liệu fabi"*, *"fabi phát sinh món hệ thống tự tách thêm ô nhập"*, và
 * *"những món nằm trong combo cũng tự hiểu tách ra số lượng tồn kho"*.
 *
 * Năm chỗ bài này khoá — đều là chuyện sổ vẫn xanh mướt mà số thì sai:
 *
 *   1. 🔴 TỒN TÍNH PHẢI LẤY SỐ MÁY, KHÔNG LẤY SỐ NHÂN VIÊN KHAI. Lấy số khai thì người khai
 *      thiếu bao nhiêu, tồn tính cũng thừa bấy nhiêu — hai vế tự triệt tiêu, `lệch kho` luôn
 *      bằng 0 dù hàng đã mất. Cả sổ xanh và vô dụng.
 *   2. 🔴 MÓN TRONG COMBO PHẢI TRỪ KHO THEO THÀNH PHẦN. FABi ghi doanh thu vào tên combo, nên
 *      không tách thì chai nước trong combo mãi mãi "chưa bán", tồn tính thừa dần.
 *   3. 🔴 ĐẾM TAY LÀ MỐC MỚI, CẮT ĐỨT CÁI LỆCH CŨ. Không thế thì một ngày lệch theo mãi về
 *      sau, mọi ngày sau đều đỏ vì một lỗi đã xử lý xong từ lâu.
 *   4. 🔴 CHƯA KHAI (null) KHÁC HẲN KHAI SỐ 0. Trộn hai thứ là ô bỏ trống trông y như ô đã
 *      đếm và đếm đúng.
 *   5. 🔴 MÓN MỚI CỦA FABi TỰ CÓ Ô NHẬP, và món hết bán vẫn ở lại sổ chừng nào còn tồn.
 *
 * Chạy: php tools/test/kiem-kho.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
require_once $goc . '/kho.php';

$dat = 0; $hong = array();
function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) { $dat++; } else { $hong[] = $ten; }
}

global $wpdb;
if ( ! function_exists( 'khh_dt_bang' ) ) {
	function khh_dt_bang() {
		global $wpdb;
		return $wpdb->prefix . 'khh_dt';
	}
}

function dung_bang() {
	global $wpdb;
	foreach ( array( khh_dt_bang(), khh_dt_bang_kho() ) as $b ) {
		$wpdb->exec_raw( "DROP TABLE IF EXISTS $b" );
	}
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
			ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', mon TEXT NOT NULL DEFAULT '[]',
			UNIQUE(ngay,cua_hang) )"
	);
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang_kho() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
			ngay TEXT NOT NULL, co_so TEXT NOT NULL DEFAULT '', mat_hang TEXT NOT NULL DEFAULT '',
			nhap REAL NOT NULL DEFAULT 0, ban_khai REAL NULL DEFAULT NULL,
			combo_tay REAL NOT NULL DEFAULT 0, dem REAL NULL DEFAULT NULL,
			ghi_chu TEXT DEFAULT '', nguoi TEXT DEFAULT '', luc TEXT NULL,
			UNIQUE(ngay,co_so,mat_hang) )"
	);
	update_option( 'khh_dt_kho_combo', array() );
}

/** Giả một ngày bán hàng của FABi: [ tên món => số lượng ]. */
function fabi( $ngay, $co_so, $mon ) {
	global $wpdb;
	$ds = array();
	foreach ( $mon as $n => $q ) {
		$ds[] = array( 'n' => $n, 'g' => '', 'q' => $q, 'r' => $q * 10000 );
	}
	$wpdb->query(
		$wpdb->prepare(
			'INSERT OR REPLACE INTO ' . khh_dt_bang() . ' (ngay,cua_hang,mon) VALUES (%s,%s,%s)',
			$ngay,
			$co_so,
			wp_json_encode( $ds )
		)
	);
}

function dong_cua( $bang, $mh ) {
	foreach ( $bang as $d ) {
		if ( $d['mat_hang'] === $mh ) {
			return $d;
		}
	}
	return null;
}

$CS = 'Tàu Tân Phú';

/* ── 1. món mới của FABi tự có ô nhập ───────────────────────────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10, 'Kẹo cầu vồng' => 4 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
phep( '🔴 món FABi có là sổ kho tự hiện dòng, khỏi khai báo trước', 2 === count( $b ) );
phep( 'và mang đúng số máy ghi bán', 10.0 === (float) dong_cua( $b, 'Nước suối' )['ban_may'] );
phep( 'chưa khai thì `dem` là null, không phải 0', null === dong_cua( $b, 'Nước suối' )['dem'] );
phep( '🔴 chưa khai thì KHÔNG có lệch — ô trống không được trông như đã đếm đúng',
	null === dong_cua( $b, 'Nước suối' )['lech_kho'] );
phep( 'chưa có mốc đếm thì nói ra', false === dong_cua( $b, 'Nước suối' )['co_moc'] );

/* Hôm sau FABi phát sinh món mới -> tự thêm dòng, mà món cũ còn tồn vẫn ở lại. */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 100, 'dem' => 90 ) );
fabi( '2026-09-02', $CS, array( 'Quẩy đùi gà' => 3 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-02', $CS );
phep( '🔴 món mới xuất hiện hôm sau: tự có dòng', null !== dong_cua( $b, 'Quẩy đùi gà' ) );
phep( '🔴 món hôm nay KHÔNG bán vẫn ở lại sổ vì còn tồn', null !== dong_cua( $b, 'Nước suối' ) );
phep( 'và mang tồn đầu đúng bằng số ĐẾM hôm qua, không phải số tính',
	90.0 === (float) dong_cua( $b, 'Nước suối' )['ton_dau'] );

/* ── 2. 🔴 đếm tay thắng số tính, cắt đứt lệch cũ ───────────────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 100, 'dem' => 85 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
$d = dong_cua( $b, 'Nước suối' );
phep( 'tồn tính = 0 + 100 − 10 = 90', 90.0 === (float) $d['ton_tinh'] );
phep( '🔴 đếm 85 thì lệch kho = −5 (mất 5 cái)', -5.0 === (float) $d['lech_kho'] );

fabi( '2026-09-02', $CS, array( 'Nước suối' => 5 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-02', $CS );
$d = dong_cua( $b, 'Nước suối' );
phep( '🔴 hôm sau tồn đầu là 85 (số ĐẾM), không phải 90 (số tính)', 85.0 === (float) $d['ton_dau'] );
phep( 'tồn tính hôm sau = 85 − 5 = 80', 80.0 === (float) $d['ton_tinh'] );
khh_dt_kho_ghi( '2026-09-02', $CS, 'Nước suối', array( 'dem' => 80 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-02', $CS );
phep( '🔴 hôm sau đếm khớp thì lệch = 0 — lỗi hôm trước KHÔNG theo sang',
	0.0 === (float) dong_cua( $b, 'Nước suối' )['lech_kho'] );

/* ── 3. 🔴 tồn tính lấy số MÁY, không lấy số nhân viên khai ─────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Kẹo cầu vồng' => 20 ) );
/* Nhân viên khai bán 15 (thiếu 5) và đếm còn 85 — nếu hệ tính theo số khai thì
   100 − 15 = 85, lệch = 0, và 5 cái mất đi biến mất khỏi sổ. */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Kẹo cầu vồng', array( 'nhap' => 100, 'ban_khai' => 15, 'dem' => 85 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Kẹo cầu vồng' );
phep( '🔴 tồn tính theo MÁY: 100 − 20 = 80', 80.0 === (float) $d['ton_tinh'] );
phep( '🔴 nên đếm 85 ra lệch kho +5, KHÔNG phải 0', 5.0 === (float) $d['lech_kho'] );
phep( '🔴 và lệch khai = 15 − 20 = −5, chỉ thẳng ra người khai thiếu 5',
	-5.0 === (float) $d['lech_khai'] );

/* ── 4. 🔴 combo trừ kho theo thành phần ────────────────────────────────────────────── */
dung_bang();
khh_dt_kho_combo_dat( 'Combo 2 người', array( 'Nước suối' => 2, 'Kẹo cầu vồng' => 1 ) );
fabi( '2026-09-01', $CS, array( 'Nước suối' => 3, 'Combo 2 người' => 10 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
$n = dong_cua( $b, 'Nước suối' );
$k = dong_cua( $b, 'Kẹo cầu vồng' );
phep( '🔴 combo trừ kho: nước = 3 lẻ + 10×2 combo = 23', 23.0 === (float) $n['ban_may'] );
phep( 'và tách được hai cột: 3 lẻ, 20 theo combo',
	3.0 === (float) $n['ban_le'] && 20.0 === (float) $n['ban_combo'] );
phep( '🔴 kẹo KHÔNG bán lẻ cái nào vẫn có dòng, vì combo có nó',
	null !== $k && 10.0 === (float) $k['ban_combo'] && 0.0 === (float) $k['ban_le'] );
phep( '🔴 KHÔNG tạo dòng kho mang tên combo — kho không có món "Combo 2 người"',
	null === dong_cua( $b, 'Combo 2 người' ) );
/* Chốt ngược: chưa khai thành phần thì combo vẫn là một món như mọi món khác. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Combo 2 người' => 10 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
phep( 'chưa khai thành phần thì combo đứng nguyên thành một dòng',
	null !== dong_cua( $b, 'Combo 2 người' ) );
phep( '🔴 và hệ NHẮC là món này trông như combo mà chưa khai thành phần',
	in_array( 'Combo 2 người', khh_dt_kho_combo_chua_khai( '2026-08-01', '2026-09-01', $CS ), true ) );

/* Xoá thành phần = xoá combo khỏi bảng. */
khh_dt_kho_combo_dat( 'Combo 2 người', array( 'Nước suối' => 2 ) );
phep( 'đặt được thành phần', isset( khh_dt_kho_combo_bang()['Combo 2 người'] ) );
khh_dt_kho_combo_dat( 'Combo 2 người', array() );
phep( 'bảng thành phần rỗng là lệnh xoá combo', ! isset( khh_dt_kho_combo_bang()['Combo 2 người'] ) );

/* ── 5. cơ sở khác không lẫn vào ────────────────────────────────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );
fabi( '2026-09-01', 'Cơ sở khác', array( 'Nước suối' => 999 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' );
phep( '🔴 kho của cơ sở này KHÔNG ăn số của cơ sở kia', 10.0 === (float) $d['ban_may'] );

/* ── 6. khai số 0 là một con số thật, không phải "chưa khai" ────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 10, 'dem' => 0 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' );
phep( '🔴 đếm được 0 cái là ĐÃ khai, không phải chưa khai', 0.0 === (float) $d['dem'] );
phep( 'và vẫn tính ra lệch bình thường', 0.0 === (float) $d['lech_kho'] );

/* ── 7. sửa lại dòng đã khai thì GHI ĐÈ, không thành hai dòng ───────────────────────── */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 10, 'dem' => 3 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
phep( 'khai lại cùng ngày cùng mặt hàng thì SỬA, không đẻ dòng thứ hai', 1 === count( $b ) );
phep( 'và mang số mới', 3.0 === (float) dong_cua( $b, 'Nước suối' )['dem'] );

/* ── 8. số kiểu Việt Nam ("1.200") đọc đúng ─────────────────────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 200 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => '1.200', 'dem' => '1.000' ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' );
phep( '🔴 "1.200" đọc ra 1200, không phải 1,2', 1200.0 === (float) $d['nhap'] );
phep( 'tồn tính = 1200 − 200 = 1000', 1000.0 === (float) $d['ton_tinh'] );
phep( 'đếm "1.000" khớp, lệch 0', 0.0 === (float) $d['lech_kho'] );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: kho trừ theo số máy, combo tách thành phần, đếm tay cắt lệch cũ.\n";
