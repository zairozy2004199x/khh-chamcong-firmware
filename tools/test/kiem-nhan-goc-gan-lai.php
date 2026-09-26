<?php
/**
 * NHÃN GỐC (Sao Kê Ngân Hàng đã xác nhận) PHẢI SỐNG SÓT QUA MỖI LẦN "GÁN LẠI".
 *
 * Anh Thắng 26/09/2026, sau khi vhcp-saoke đã tự lưu nhãn cơ sở ngay lúc nạp (bản 0.59.0):
 * *"chưa thấy sao kê"* / *"chưa thấy chỗ đã nộp tiền"* — bảng Đối soát của khh-doanh-thu vẫn báo
 * "chưa khai mã nộp tiền" cho hầu hết các ngày của TÀU TÂN AN, dù Sao Kê đã gắn đúng nhãn từ lâu.
 *
 * Lần theo tới gốc: `khh_dt_keo_nguon()` (kéo về từ bảng `wpt9_saoke_gd` thật của vhcp-saoke) ĐÃ
 * dùng nhãn nguồn đúng cách (`khh_dt_ten_co_so_gan()`, vá ở 1.68.6) để suy `cua_hang` lúc kéo —
 * NHƯNG chỉ dùng nhãn đó THOÁNG QUA rồi bỏ, không hề lưu lại. Còn `khh_dt_gan_lai_sao_ke()` — chạy
 * lại CHO MỌI DÒNG mỗi khi ai đó lưu "bảng nhận mặt" (mã ↔ cơ sở) ở Cấu hình
 * (`khh_dt_rest_sk_ghep()`) — chỉ biết đoán bằng `khh_dt_doan_co_so()`: nội dung thô + sổ mã CỦA
 * RIÊNG khh-doanh-thu (`khh_dt_ghep_bank_ds()`, một sổ hoàn toàn KHÁC sổ mã bên vhcp-saoke).
 *
 * 🔴 CƠ SỞ NÀO CHỈ KHAI MÃ BÊN SAO KÊ NGÂN HÀNG (không khai LẠI ở khh-doanh-thu) thì đúng lần
 *    "gán lại" ĐẦU TIÊN — dù chỉ để cứu MỘT cơ sở khác — là mất sạch `cua_hang` đã đúng của những
 *    cơ sở còn lại, lùi về "chưa khai mã nộp tiền" dù nội dung sao kê không hề đổi một chữ.
 *
 * Vá: lưu nhãn gốc vào cột `nhan` của bảng sao-kê RIÊNG của khh-doanh-thu ngay lúc kéo, để
 * `khh_dt_gan_lai_sao_ke()` dùng LẠI đúng thứ tự ưu tiên của `khh_dt_keo_nguon()` (nhãn nguồn
 * trước, đoán nội dung sau) thay vì chỉ còn mỗi vế đoán.
 *
 * Chạy: php tools/test/kiem-nhan-goc-gan-lai.php
 */
require_once __DIR__ . '/fw/bo-do-khh-dt.php';
$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
require_once $goc . '/sao-ke.php';

$dat = 0; $hong = array();
function phep2( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
/* Danh sách cơ sở POS giả — cùng seam `KHH_DT_TEST_CH` đã dùng ở kiem-sao-ke.php, đọc bởi
   `khh_dt_ds_cua_hang()` (stub trong fw/bo-do-khh-dt.php). */
$GLOBALS['KHH_DT_TEST_CH'] = array(
	'Tutu Train - Aeon Tân An ( Dịch vụ và Giải trí )',
	'Tutu Train - Aeon Tân Phú ( Dịch vụ và Giải trí )',
);

/* Bảng sao-kê RIÊNG của khh-doanh-thu — dựng thẳng bằng SQL (như dung_bang_sk() ở
   kiem-sao-ke.php), có cột `nhan` vừa thêm. */
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_sk() );
$wpdb->exec_raw(
	'CREATE TABLE ' . khh_dt_bang_sk() . " (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		ma_gd TEXT NOT NULL DEFAULT '' UNIQUE,
		ngay TEXT NOT NULL, gio INTEGER NOT NULL DEFAULT 0, ngay_tinh TEXT NOT NULL,
		so_tien REAL NOT NULL DEFAULT 0, noi_dung TEXT NOT NULL,
		tai_khoan TEXT NOT NULL DEFAULT '', cua_hang TEXT NOT NULL DEFAULT '', nhan TEXT NOT NULL DEFAULT '', nap_luc TEXT NULL )"
);

/* Bảng NGUỒN giả — hình dạng đúng bảng thật `wpt9_saoke_gd` của vhcp-saoke (xem kiem-sao-ke.php
   §5d): NGAY_GD, TIEN, NOI_DUNG, MA_GD, SO_TK, NHAN. */
$wpdb->exec_raw( 'DROP TABLE IF EXISTS test_nguon_saoke_gd' );
$wpdb->exec_raw(
	"CREATE TABLE test_nguon_saoke_gd ( id INTEGER PRIMARY KEY AUTOINCREMENT,
		ngay_gd TEXT, tien REAL, noi_dung TEXT, ma_gd TEXT, so_tk TEXT, nhan TEXT )"
);
$wpdb->insert( 'test_nguon_saoke_gd', array(
	'ngay_gd'  => '2026-08-19 08:00:00',
	'tien'     => 20300000,
	'noi_dung' => 'NHAN TU 18865471 TRACE 903698 ND KH705KVCMN0005-190826-02:27:05 6231ASCB02ZDW9MX',
	'ma_gd'    => 'FT26231759846673',
	'so_tk'    => '119229499',
	/* 🔴 Đúng nhãn thật anh Thắng gửi ảnh: Sao Kê ĐÃ gắn "TÀU TÂN AN" cho khoản này. */
	'nhan'     => 'TÀU TÂN AN',
) );

$nguon = array(
	'bang' => 'test_nguon_saoke_gd',
	'cot'  => array(
		'ngay'      => 'ngay_gd',
		'so_tien'   => 'tien',
		'noi_dung'  => 'noi_dung',
		'ma_gd'     => 'ma_gd',
		'tai_khoan' => 'so_tk',
		'nhan'      => 'nhan',
	),
);

echo "── Kéo về: nhãn nguồn suy đúng cơ sở NGAY LÚC KÉO ──\n";
$r = khh_dt_keo_nguon( $nguon );
phep2( 'kéo về không lỗi', ! empty( $r['ok'] ) );
phep2( 'kéo được đúng 1 dòng', 1 === (int) $r['keo'] );
$dong = $wpdb->get_row( "SELECT cua_hang, nhan FROM " . khh_dt_bang_sk() . " WHERE ma_gd='ng-FT26231759846673'", ARRAY_A );
phep2( '🔴 cua_hang suy đúng từ nhãn "TÀU TÂN AN" ngay lúc kéo',
	$dong && 'Tutu Train - Aeon Tân An ( Dịch vụ và Giải trí )' === $dong['cua_hang'] );
phep2( '🔴 nhãn gốc được LƯU LẠI (không chỉ dùng thoáng qua rồi bỏ)',
	$dong && 'TÀU TÂN AN' === $dong['nhan'] );

echo "\n── Gán lại (mô phỏng sửa \"bảng nhận mặt\" cho cơ sở KHÁC) không được xoá mất dòng này ──\n";
/* 🔴 Sổ mã CỦA RIÊNG khh-doanh-thu (khh_dt_ghep_bank) KHÔNG hề có gì cho "TÀU TÂN AN" / mã
   KH705KVCMN0005 — đúng thực tế trên site: anh Thắng chỉ khai mã ấy bên vhcp-saoke, chưa từng
   khai lại ở đây. Chỉ khai một mã cho một cơ sở KHÁC, mô phỏng đúng thao tác kích hoạt
   `khh_dt_gan_lai_sao_ke()` trong đời thật (`khh_dt_rest_sk_ghep()`). */
khh_dt_dat_ghep_bank( array(
	array( 'khoa' => 'KH705MTDMN0002', 'cua_hang' => 'Tutu Train - Aeon Tân Phú ( Dịch vụ và Giải trí )' ),
) );
$doi = khh_dt_gan_lai_sao_ke();
$sau = $wpdb->get_row( "SELECT cua_hang, nhan FROM " . khh_dt_bang_sk() . " WHERE ma_gd='ng-FT26231759846673'", ARRAY_A );
phep2( '🔴 sau khi gán lại, cua_hang VẪN đúng (không bị xoá về rỗng, đang là "' . ( $sau ? $sau['cua_hang'] : '(mất dòng)' ) . '")',
	$sau && 'Tutu Train - Aeon Tân An ( Dịch vụ và Giải trí )' === $sau['cua_hang'] );
phep2( 'nhãn gốc vẫn còn nguyên sau khi gán lại', $sau && 'TÀU TÂN AN' === $sau['nhan'] );

/* Đối chứng: một dòng KHÔNG có nhãn nguồn (nhan rỗng, như dữ liệu nạp từ file CSV cũ, hoặc kéo từ
   nguồn không có cột Nhãn) thì gán lại vẫn CHỈ đoán được theo nội dung + sổ mã — hành vi giữ
   nguyên như trước bản vá, không bị đổi tính nết. */
$wpdb->query( "DELETE FROM " . khh_dt_bang_sk() . " WHERE ma_gd='ng-doi-chung'" );
$wpdb->insert( khh_dt_bang_sk(), array(
	'ma_gd' => 'ng-doi-chung', 'ngay' => '2026-08-20', 'gio' => 8, 'ngay_tinh' => '2026-08-20',
	'so_tien' => 1000000, 'noi_dung' => 'KH705MTDMN0002 NOP TIEN', 'tai_khoan' => '119229499',
	'cua_hang' => '', 'nhan' => '',
) );
khh_dt_gan_lai_sao_ke();
$dc = $wpdb->get_row( "SELECT cua_hang FROM " . khh_dt_bang_sk() . " WHERE ma_gd='ng-doi-chung'", ARRAY_A );
phep2( 'đối chứng: dòng KHÔNG nhãn nguồn vẫn đoán theo sổ mã như trước (không đổi tính nết)',
	$dc && 'Tutu Train - Aeon Tân Phú ( Dịch vụ và Giải trí )' === $dc['cua_hang'] );

if ( $hong ) { echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: nhãn gốc lưu lại được, gán lại không xoá mất cơ sở đã đúng.\n";
