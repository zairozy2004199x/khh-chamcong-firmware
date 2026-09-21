<?php
/**
 * MÁY POS DỜI CƠ SỞ, MoMo GIỮ NGUYÊN TÊN — BẢNG GHÉP PHẢI ĐI THEO FABi.
 *
 * Anh Thắng 17/09/2026: *"đó là do hay đổi cơ sở máy POS nhưng MoMo không đổi… lần sau đổi thì
 * muốn xác định của cơ sở nào, anh nạp dữ liệu FABi vào là xác định giá trị thật nó đang nằm
 * cơ sở nào"*. Tức luật do anh chốt: **FABi là nguồn sự thật về máy đang ở đâu, và lứa FABi MỚI
 * NHẤT quyết định.**
 *
 * Ca thật đang chạy trên site: mã MoMo `KHEVENT2` đăng ký tên "Sự Kiện Sảnh Aeon Bình Dương",
 * còn FABi thì ghi máy ấy ở "VR Fun Aeon Tân An" — 103/103 giao dịch khớp mã, không lệch một
 * đồng, chỉ lệch TÊN.
 *
 * 🔴 VÌ SAO BÀI NÀY PHẢI CÓ. `khh_dt_hoc_ma_ch_momo()` tự nhận trong chú thích là "lời giải cho
 *    bài máy dời cơ sở", và chốt "một mã trỏ về hai quán TRONG CÙNG KỲ thì không học". Nhưng câu
 *    SQL của nó KHÔNG LỌC NGÀY — nó quét sạch lịch sử. Nên sau một lần dời máy, mã ấy vĩnh viễn
 *    trỏ về hai quán, vĩnh viễn rơi vào nhánh "không học", và bảng ghép ĐỨNG YÊN Ở TÊN CŨ. Máy
 *    đã chuyển sang quán mới cả tháng mà doanh thu vẫn được kể cho quán cũ — sai ở cả hai đầu,
 *    và không có câu báo nào.
 *
 * Chạy: php tools/test/kiem-momo-hoc-coso.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/momo.php';

$dat  = 0;
$hong = array();
function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) { $dat++; } else { $hong[] = $ten; }
}

/* Dựng hai bảng đúng theo DDL trong khh_dt_tao_bang_momo() / _momo_sk(). Lệch một cột ở đây là
   bài kiểm chạy trên cái bảng KHÔNG PHẢI cái chạy trên hosting. */
function dung_bang() {
	global $wpdb;
	$pos = khh_dt_bang_momo();
	$sk  = khh_dt_bang_momo_sk();
	$wpdb->exec_raw( "DROP TABLE IF EXISTS $pos" );
	$wpdb->exec_raw( "DROP TABLE IF EXISTS $sk" );
	$wpdb->exec_raw(
		"CREATE TABLE $pos (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			ma_doi_tac TEXT NOT NULL DEFAULT '' UNIQUE,
			ma_hd TEXT NOT NULL DEFAULT '', so_hd TEXT NOT NULL DEFAULT '',
			ngay TEXT NOT NULL, gio INTEGER NOT NULL DEFAULT 0,
			cua_hang TEXT NOT NULL DEFAULT '', so_tien REAL NOT NULL DEFAULT 0,
			trang_thai TEXT NOT NULL DEFAULT '', loai TEXT NOT NULL DEFAULT 'dong',
			nap_luc TEXT NULL )"
	);
	$wpdb->exec_raw(
		"CREATE TABLE $sk (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			ma_gd TEXT NOT NULL DEFAULT '' UNIQUE,
			ma_don TEXT NOT NULL DEFAULT '', ngay TEXT NOT NULL,
			gio INTEGER NOT NULL DEFAULT 0, so_tien REAL NOT NULL DEFAULT 0,
			trang_thai TEXT NOT NULL DEFAULT '', loai_gd TEXT NOT NULL DEFAULT '',
			nguon_tien TEXT NOT NULL DEFAULT '', ma_ch TEXT NOT NULL DEFAULT '',
			ten_ch TEXT NOT NULL DEFAULT '', nap_luc TEXT NULL )"
	);
	update_option( 'khh_dt_ghep_ma_ch_momo', array() );
}

/** Một lượt: cùng một mã giao dịch nằm ở cả hai sổ. */
function cap( $ma_gd, $ngay, $ma_ch, $ten_ch, $quan ) {
	global $wpdb;
	$wpdb->exec_raw( $wpdb->prepare(
		'INSERT INTO ' . khh_dt_bang_momo_sk() . ' (ma_gd,ngay,so_tien,ma_ch,ten_ch) VALUES (%s,%s,%f,%s,%s)',
		array( $ma_gd, $ngay, 10000, $ma_ch, $ten_ch )
	) );
	$wpdb->exec_raw( $wpdb->prepare(
		'INSERT INTO ' . khh_dt_bang_momo() . ' (ma_doi_tac,ngay,so_tien,cua_hang) VALUES (%s,%s,%f,%s)',
		array( $ma_gd, $ngay, 10000, $quan )
	) );
}

/* ── 1. Không dời máy: học bình thường ────────────────────────────────────────────────── */
dung_bang();
cap( 'g1', '2026-09-10', 'KHTUTU2', 'Tutu Train - Estella', 'Tutu Train - Estella' );
cap( 'g2', '2026-09-11', 'KHTUTU2', 'Tutu Train - Estella', 'Tutu Train - Estella' );
$r = khh_dt_hoc_ma_ch_momo();
phep( 'máy đứng yên thì học được tên quán',
	'Tutu Train - Estella' === khh_dt_ma_ch_toi_co_so( 'KHTUTU2' ) );

/* ── 2. Tên MoMo KHÁC tên FABi (ca KHEVENT2 thật) ─────────────────────────────────────── */
dung_bang();
cap( 'e1', '2026-09-10', 'KHEVENT2', 'Sự Kiện Sảnh Aeon Bình Dương', 'VR Fun Aeon Tân An' );
cap( 'e2', '2026-09-11', 'KHEVENT2', 'Sự Kiện Sảnh Aeon Bình Dương', 'VR Fun Aeon Tân An' );
khh_dt_hoc_ma_ch_momo();
phep( 'tên MoMo khác tên FABi thì lấy tên FABi',
	'VR Fun Aeon Tân An' === khh_dt_ma_ch_toi_co_so( 'KHEVENT2' ) );

/* ── 3. 🔴 MÁY DỜI CƠ SỞ — lứa FABi MỚI NHẤT phải thắng ───────────────────────────────── */
dung_bang();
/* Tháng 8 máy ở Quán Cũ… */
cap( 'd1', '2026-08-01', 'KHEVENT2', 'Sự Kiện Sảnh Aeon Bình Dương', 'Quán Cũ' );
cap( 'd2', '2026-08-02', 'KHEVENT2', 'Sự Kiện Sảnh Aeon Bình Dương', 'Quán Cũ' );
cap( 'd3', '2026-08-03', 'KHEVENT2', 'Sự Kiện Sảnh Aeon Bình Dương', 'Quán Cũ' );
khh_dt_hoc_ma_ch_momo();
phep( 'trước khi dời: trỏ về Quán Cũ', 'Quán Cũ' === khh_dt_ma_ch_toi_co_so( 'KHEVENT2' ) );

/* …tháng 9 dời sang Quán Mới. MoMo vẫn gửi nguyên mã và nguyên tên cũ. */
cap( 'd4', '2026-09-10', 'KHEVENT2', 'Sự Kiện Sảnh Aeon Bình Dương', 'Quán Mới' );
cap( 'd5', '2026-09-11', 'KHEVENT2', 'Sự Kiện Sảnh Aeon Bình Dương', 'Quán Mới' );
$r = khh_dt_hoc_ma_ch_momo();
phep( 'SAU KHI DỜI: bảng ghép đi theo FABi mới nhất (Quán Mới)',
	'Quán Mới' === khh_dt_ma_ch_toi_co_so( 'KHEVENT2' ) );
phep( 'dời máy KHÔNG bị kể là "không phân định được"',
	! in_array( 'KHEVENT2', (array) $r['lan_can'], true ) );

/* Dời lần nữa — không phải chỉ đúng một lần rồi kẹt. */
cap( 'd6', '2026-10-01', 'KHEVENT2', 'Sự Kiện Sảnh Aeon Bình Dương', 'Quán Thứ Ba' );
khh_dt_hoc_ma_ch_momo();
phep( 'dời lần thứ hai cũng theo kịp',
	'Quán Thứ Ba' === khh_dt_ma_ch_toi_co_so( 'KHEVENT2' ) );

/* ── 4. Thật sự không phân định được: CÙNG NGÀY ở hai quán ────────────────────────────── */
/* Đây mới là ca đáng dừng lại — đoán bên nào cũng sai một nửa, và đoán sai thì im lặng. */
dung_bang();
cap( 'x1', '2026-09-10', 'KHMIX', 'Quán X', 'Quán A' );
cap( 'x2', '2026-09-10', 'KHMIX', 'Quán X', 'Quán B' );
$r = khh_dt_hoc_ma_ch_momo();
phep( 'hai quán CÙNG một ngày thì KHÔNG đoán bừa',
	in_array( 'KHMIX', (array) $r['lan_can'], true ) );
phep( 'không đoán bừa nghĩa là không ghi gì vào bảng ghép',
	'' === khh_dt_ma_ch_toi_co_so( 'KHMIX' ) );

/* ── 5. Chốt ngược: không có giao dịch khớp thì không bịa ra bảng ghép ────────────────── */
dung_bang();
$r = khh_dt_hoc_ma_ch_momo();
phep( 'kho rỗng thì không học gì', 0 === (int) $r['hoc'] && '' === khh_dt_ma_ch_toi_co_so( 'KHTUTU2' ) );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: máy dời cơ sở thì bảng ghép đi theo lứa FABi mới nhất.\n";
