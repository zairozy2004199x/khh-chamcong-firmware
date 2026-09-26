<?php
/**
 * "KHONG LƯU ĐƯƠC": THIẾU CỘT `nhan` KHÔNG ĐƯỢC LÀM VỠ CẢ CÂU GHI/ĐỌC.
 *
 * Anh Thắng 26/09/2026, sau bản 1.72.0: gõ mã "KH705KVCMN0005" cho "Tutu Train - Aeon Tân An" ở
 * bảng nhận mặt, bấm "Lưu và gán lại" — tải lại thì ô lại trống, và Đối soát vẫn "chưa khai mã
 * nộp tiền" cho mọi ngày dù nội dung sao kê không đổi.
 *
 * 🔴 GỐC: cột `nhan` (thêm ở 1.72.0) chỉ có thật trên site khi `khh_dt_nang_cap()` (hook `init`
 *    ưu tiên 5) đã chạy `dbDelta()` của ĐÚNG lượt nâng cấp này. `$wpdb->query()`/`get_results()`
 *    KHÔNG NÉM NGOẠI LỆ khi SQL tham chiếu một cột không tồn tại — chỉ âm thầm trả false/rỗng.
 *    Thiếu cột `nhan` thì:
 *      · `khh_dt_ghi_sao_ke()`: CẢ CÂU INSERT hỏng (không phải chỉ mất mỗi ô nhan) — nạp sao kê
 *        coi như không ghi được DÒNG NÀO, dù REST vẫn báo "kéo được N khoản" (không ai kiểm tra
 *        SQL có thật sự chạy).
 *      · `khh_dt_gan_lai_sao_ke()`: CẢ CÂU SELECT hỏng — quét được 0 dòng, "Lưu và gán lại" báo
 *        "Đã gán lại 0 khoản" dù đã gõ đúng mã, và mọi ngày vẫn "chưa khai mã nộp tiền" mãi mãi.
 *
 * Vá: `khh_dt_bang_sk_co_cot()` tự kiểm cột có thật trước khi dùng, thiếu thì tự gọi lại
 * `khh_dt_tao_bang_sk()` một lần; còn thiếu thì GHÉP CÂU SQL KHÔNG CÓ CỘT ẤY — ghi/đọc vẫn chạy
 * được (giảm về hành vi trước 1.72.0: chỉ đoán theo nội dung, không có nhãn gốc để dùng lại),
 * thay vì cả câu vỡ âm thầm.
 *
 * Chạy: php tools/test/kiem-sao-ke-thieu-cot-nhan.php
 */
require_once __DIR__ . '/fw/bo-do-khh-dt.php';
$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
require_once $goc . '/sao-ke.php';

$dat = 0; $hong = array();
function phep3( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
$GLOBALS['KHH_DT_TEST_CH'] = array( 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )' );

echo "── Site CHƯA nâng cấp: bảng sao-kê KHÔNG có cột nhan ──\n";
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_sk() );
$wpdb->exec_raw(
	'CREATE TABLE ' . khh_dt_bang_sk() . " (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		ma_gd TEXT NOT NULL DEFAULT '' UNIQUE,
		ngay TEXT NOT NULL, gio INTEGER NOT NULL DEFAULT 0, ngay_tinh TEXT NOT NULL,
		so_tien REAL NOT NULL DEFAULT 0, noi_dung TEXT NOT NULL,
		tai_khoan TEXT NOT NULL DEFAULT '', cua_hang TEXT NOT NULL DEFAULT '', nap_luc TEXT NULL )"
	/* 🔴 CỐ Ý không có cột `nhan` — mô phỏng đúng site chưa chạy dbDelta của 1.72.0. */
);

phep3( '🔴 khh_dt_bang_sk_co_cot(\'nhan\') nhận ra cột chưa có', false === khh_dt_bang_sk_co_cot( 'nhan' ) );

echo "\n── khh_dt_ghi_sao_ke(): vẫn ghi được dòng dù thiếu cột nhan ──\n";
$n = khh_dt_ghi_sao_ke( array( array(
	'ma_gd' => 'ng-TEST1', 'ngay' => '2026-09-20', 'gio' => 8, 'ngay_tinh' => '2026-09-20',
	'so_tien' => 5230000, 'noi_dung' => 'KH705KVCMN0005 NOP TIEN', 'tai_khoan' => '119229499',
	'cua_hang' => 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', 'nhan' => 'TÀU TÂN AN',
) ) );
phep3( '🔴 khh_dt_ghi_sao_ke() báo đã ghi 1 dòng', 1 === $n );
$dong = $wpdb->get_row( "SELECT * FROM " . khh_dt_bang_sk() . " WHERE ma_gd='ng-TEST1'", ARRAY_A );
phep3( '🔴 dòng THẬT SỰ có trong bảng (không phải câu INSERT vỡ âm thầm)', null !== $dong );
phep3( 'các cột khác vẫn đúng dù thiếu cột nhan', $dong && 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )' === $dong['cua_hang'] && 5230000.0 === (float) $dong['so_tien'] );

echo "\n── khh_dt_gan_lai_sao_ke(): vẫn quét và gán được dù thiếu cột nhan (giảm về đoán nội dung) ──\n";
khh_dt_dat_ghep_bank( array( array( 'khoa' => 'KH705KVCMN0005', 'cua_hang' => 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )' ) ) );
$wpdb->update( khh_dt_bang_sk(), array( 'cua_hang' => '' ), array( 'ma_gd' => 'ng-TEST1' ) );   // giả lập dòng đang "lạc"
$doi = khh_dt_gan_lai_sao_ke();
phep3( '🔴 gán lại vẫn chạy, quét được dòng (không phải 0 khoản vì câu SELECT vỡ)', $doi >= 1 );
$sau = $wpdb->get_row( "SELECT cua_hang FROM " . khh_dt_bang_sk() . " WHERE ma_gd='ng-TEST1'", ARRAY_A );
phep3( 'và gán đúng cơ sở theo mã trong nội dung (đường đoán vẫn chạy được)',
	$sau && 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )' === $sau['cua_hang'] );

/* ⚠️ Ca "có cột nhan, hành vi giữ nguyên như 1.72.0" đã có bài riêng chạy thật ở
   kiem-nhan-goc-gan-lai.php (tiến trình PHP riêng, sạch bộ nhớ tạm) — không lặp lại ở đây.
   khh_dt_bang_sk_co_cot() cố ý nhớ tạm kết quả TRONG MỘT LƯỢT TẢI TRANG (một request thật không
   tự đổi cấu trúc bảng giữa chừng), nên bài NÀY chỉ dựng đúng MỘT bảng (thiếu cột) từ đầu tới
   cuối, không đổi bảng giữa chừng trong cùng tiến trình. */

if ( $hong ) { echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: thiếu cột nhan không làm vỡ ghi/đọc sao kê — tự vá và giảm về đoán nội dung.\n";
