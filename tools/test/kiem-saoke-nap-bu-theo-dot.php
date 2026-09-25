<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ 0.52.0 — NẠP BÙ THEO ĐỢT: DÒ TRÙNG THEO LÔ, KHÔNG HỎI TỪNG DÒNG ĐÃ CÓ; NẠP LẠI AN TOÀN
 *
 * Anh Thắng 25/09/2026: *"nạp file bù rất lâu và hay lỗi"* — 7.816 dòng: hosting trả trang HTML ("Unexpected token '<'");
 * 24.261 dòng: "File quá lớn". Một yêu cầu ôm cả file, mỗi dòng 2–3 câu SQL dò trùng → quá giờ. Nay màn hình gửi từng
 * đợt 400 dòng (xem kiem-saoke-nap-bu-gop.js), máy chủ mỗi đợt hỏi MỘT câu `khoa IN (…)` cho cả lô.
 *
 * 🔴 BẤT BIẾN TIỀN: nạp lại cùng file bao nhiêu lần cũng không đếm thêm đồng nào — đợt lỗi giữa chừng thì bấm lại.
 * Chạy: php tools/test/kiem-saoke-nap-bu-theo-dot.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/lib/be-saoke.php';
require_once $GOC . '/vhcp-saoke/vhcp-saoke.php';
t( 'nạp được lớp SAOKE_App', class_exists( 'SAOKE_App' ) );
$db = $GLOBALS['wpdb']; $GLOBALS['OPT']['saoke_pin'] = '1234';
$GLOBALS['OPT']['saoke_vqr_ch'] = array( 'M4QMOQLG7Y' => array( 'ten' => 'AMBT 03', 'maDiem' => 'VVB701528', 'tenDiem' => 'POSH AEON Bình Tân' ) );
goi( 'vqr_ch_quen_' );
function dongFile( $i, $maCH = 'M4QMOQLG7Y' ) { return array( '22-09-2026 23:' . str_pad( $i % 60, 2, '0', STR_PAD_LEFT ) . ':06', '20000', 'VPB' . $i, 'REF' . $i, 'VQR2639' . $i . ' PaymentForOrder', $maCH, 'VVB701528', 'Thành công' ); }
/* 3 dòng ĐÃ CÓ do webhook ghi (chưa có mã cửa hàng — webhook không gửi), 2 dòng mới. */
$db->hang = array();
foreach ( array( 1, 2, 3 ) as $i ) { $db->hang[] = array( 'id' => $i, 'nguon' => 'vietqr', 'khoa' => 'vietqr|VPB' . $i, 'ma_gd' => 'VPB' . $i, 'ref' => 'REF' . $i, 'so_tien' => 20000, 'diem_ban' => '', 'ma_ch' => '', 'noi_dung' => 'PaymentForOrder' ); }
$rows = array( dongFile( 1 ), dongFile( 2 ), dongFile( 3 ), dongFile( 4 ), dongFile( 5 ) );
$db->so_insert = 0; $db->so_update = 0; $db->so_get_row_khoa = 0; $db->so_get_row_ref = 0; $db->so_select_lo = 0; $db->so_select_lo_khoa = 0; $db->so_update_lo = 0;

echo "── 1. Một đợt: dò trùng theo LÔ ───────────────────────────────\n";
$r = SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', $rows, 'transactions.xlsx' ) );
t( 'nạp ok', is_array( $r ) && ! empty( $r['ok'] ), $r );
teq( '🔴 2 dòng mới được thêm', 2, $r['themMoi'] );
teq( '3 dòng đã có → trùng, bỏ qua', 3, $r['trungBoQua'] );
teq( '… nhưng được VÁ mã cửa hàng (webhook không gửi, file có)', 3, $r['vaMay'] );
teq( 'tiền cộng đúng 2 dòng mới', 40000, (int) $r['tongTienThem'] );
teq( '🔴 dò trùng theo lô: đúng 1 câu khoa IN (…) cho cả đợt', 1, $db->so_select_lo_khoa );
teq( '0.54.0: 2 dòng khoá chưa có → dò ref / mã GD cũng theo lô (2 câu), KHÔNG quét bảng từng dòng', 3, $db->so_select_lo );
teq( '… và 0 câu cong_dong_trung rời', 0, $db->so_get_row_ref );
teq( '🔴 KHÔNG hỏi từng dòng theo khoá nữa (bản cũ: 5 câu, một câu mỗi dòng)', 0, $db->so_get_row_khoa );
teq( 'ghi đúng 2 dòng', 2, $db->so_insert );
t( 'dòng cũ đã mang mã cửa hàng', 'M4QMOQLG7Y' === $db->hang[0]['ma_ch'] && 'M4QMOQLG7Y' === $db->hang[2]['ma_ch'], array( $db->hang[0]['ma_ch'], $db->hang[2]['ma_ch'] ) );
teq( '🔴 0.53.0: vá theo LÔ — 1 câu UPDATE … CASE cho cả 3 dòng, 0 câu UPDATE rời', 1, $db->so_update_lo + $db->so_update * 100 );
t( '0.53.0: nạp file không tính lại kho Ghế tại chỗ — chỉ đánh dấu ngày cũ (quen_ngay), Ghế cũ thì lùi về tính lại', false !== strpos( $SRC, "method_exists( 'VHG_VietQR', 'quen_ngay' )" ) && false !== strpos( $SRC, 'self::day_ghe_danh_dau_( $ds )' ) && 1 === substr_count( $SRC, 'self::cong_va_lo_( $vaLo );' ) );
t( 'trả dsMaCH (màn hình hợp các đợt để đếm mã) và thieuBanDo đủ', array( 'M4QMOQLG7Y' ) === $r['dsMaCH'] && array() === $r['thieuBanDo'] && 1 === $r['soMaCH'] );

echo "── 2. Nạp lại y hệt: không đếm thêm đồng nào ──────────────────\n";
$db->so_insert = 0; $db->so_get_row_khoa = 0; $db->so_select_lo = 0; $db->so_get_row_ref = 0;
$r2 = SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', $rows, 'transactions.xlsx' ) );
t( '🔴 5/5 trùng, 0 thêm, 0đ, 0 dòng ghi — đợt lỗi giữa chừng bấm nạp lại vẫn an toàn', 0 === $r2['themMoi'] && 5 === $r2['trungBoQua'] && 0 === $r2['tongTienThem'] && 0 === $db->so_insert, $r2 );
teq( 'mọi dòng đã có theo khoá → chỉ 1 câu dò lô, không dò ref, 0 câu theo dòng', 1, $db->so_select_lo + $db->so_get_row_khoa + $db->so_get_row_ref );

echo "── 3. Dòng mới vẫn qua đủ cửa chống trùng theo mã tham chiếu ──\n";
/* Dòng có maGD KHÁC nhưng ref + số tiền trùng dòng đã có (hai đường về, hai khoá) → phải nhận ra là trùng. */
$db->so_insert = 0; $db->so_get_row_ref = 0; $db->so_get_row_khoa = 0;
$r3 = SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', array( array( '22-09-2026 23:10:00', '20000', 'VPB-KHAC', 'REF1', 'PaymentForOrder', 'M4QMOQLG7Y', 'VVB701528', 'Thành công' ) ), 'f.xlsx' ) );
t( '🔴 khoá lạ nhưng ref+tiền trùng → trùng, không chèn — bắt được bằng dò lô, 0 câu rời', 0 === $r3['themMoi'] && 1 === $r3['trungBoQua'] && 0 === $db->so_insert && 0 === $db->so_get_row_ref + $db->so_get_row_khoa, $r3 );
$r3b = SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', array( array( '22-09-2026 23:10:00', '30000', 'VPB-KHAC2', 'REF1', 'PaymentForOrder', 'M4QMOQLG7Y', 'VVB701528', 'Thành công' ) ), 'f.xlsx' ) );
t( 'cùng ref nhưng KHÁC tiền → giao dịch khác, chèn mới (đúng luật cong_dong_trung)', 1 === $r3b['themMoi'], $r3b );
$db->so_insert = 0;
$r3c = SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', array( dongFile( 900 ), dongFile( 900 ) ), 'f.xlsx' ) );
t( '🔴 cùng khoá hai lần TRONG MỘT ĐỢT → chèn 1, trùng 1 (dò lô phải tự bắt, không dựa SELECT từng dòng)', 1 === $r3c['themMoi'] && 1 === $r3c['trungBoQua'] && 1 === $db->so_insert, $r3c );

echo "── 4. Trần mỗi đợt & bản đồ thiếu không bị cắt ─────────────────\n";
$to = array(); for ( $i = 0; $i < 2001; $i++ ) { $to[] = dongFile( $i ); }
$r4 = SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', $to, 'f.xlsx' ) );
t( 'quá 2.000 dòng/đợt → từ chối, chỉ đường tải lại trang (màn hình mới tự chia đợt)', empty( $r4['ok'] ) && false !== strpos( $r4['error'], 'Mỗi đợt tối đa' ), $r4 );
$la = array(); for ( $i = 100; $i < 135; $i++ ) { $la[] = dongFile( $i, 'LA' . $i ); }
$r5 = SAOKE_App::rpc_napFileCongTx( array( '1234', 'vietqr', $la, 'f.xlsx' ) );
teq( '35 mã lạ → thieuBanDo trả đủ 35 (bản cũ cắt 30 → hợp các đợt bị hụt)', 35, count( $r5['thieuBanDo'] ) );
teq( 'soThieuBanDo khớp', 35, $r5['soThieuBanDo'] );

echo "── 5. Dây nối màn hình & cầu gọi ──────────────────────────────\n";
$ap = file_get_contents( $GOC . '/vhcp-saoke/app.html' );
t( 'màn hình chia đợt 800 dòng (0.53.0): cgNapTxDot + cgGopKqTx', false !== strpos( $ap, 'var CG_DOT = 800;' ) && false !== strpos( $ap, 'function cgNapTxDot(' ) && false !== strpos( $ap, 'function cgGopKqTx(' ) );
teq( '🔴 chỉ MỘT chỗ gọi napFileCongTx (trong cgNapTxDot) — hai luồng nạp bù / giao dịch lẻ đều qua đó', 1, substr_count( $ap, '.napFileCongTx(PIN, nguon, phan, tenFile)' ) + substr_count( $ap, '.napFileCongTx(PIN, nguon, k.goi' ) + substr_count( $ap, '.napFileCongTx(PIN, nguon, goi' ) );
t( 'đợt lỗi nói rõ đã nạp tới đâu và nạp lại an toàn', false !== strpos( $ap, 'đã nạp xong \'+daNap+\'/\'+tong+\' dòng — phần ấy đã lưu' ) );
t( 'giao dịch lẻ đọc đúng khoá themMoi/trungBoQua (trước đọc r2.moi luôn 0)', false !== strpos( $ap, "(r2.themMoi||0)" ) && false === strpos( $ap, "(r2.moi||0)" ) );
t( 'cầu gọi: máy chủ trả trang HTML thì nói thẳng kèm mã HTTP, không còn "Unexpected token"', false !== strpos( $SRC, 'r.text().then(function(t){try{return JSON.parse(t);}catch(e){throw new Error("Máy chủ trả về trang HTML' ) );
t( 'luu_cong nhận dòng đã dò sẵn (mảng / false / null)', false !== strpos( $SRC, 'private static function luu_cong( $row, &$kq = null, $cu_biet = null, &$va_lo = null )' ) && false !== strpos( $SRC, "isset( \$daCo[ \$k120 ] ) ? \$daCo[ \$k120 ] : ( isset( \$daCoRef[ \$k120 ] ) ? \$daCoRef[ \$k120 ] : 'moi' )" ) );
preg_match( '/^ \* Version:\s+([0-9.]+)/m', $SRC, $m1 ); preg_match( "/const VER = '([0-9.]+)';/", $SRC, $m2 );
t( '0.54.0: chỉ mục ma_gd trong CREATE + thêm tay khi kích hoạt, VER_TBL 6', false !== strpos( $SRC, 'KEY ref (ref), KEY ma_gd (ma_gd)' ) && false !== strpos( $SRC, "Key_name='ma_gd'" ) && false !== strpos( $SRC, "const VER_TBL = '6';" ) );
t( 'vân tay từ 0.52.0 trở lên', isset( $m1[1], $m2[1] ) && version_compare( $m1[1], '0.52.0', '>=' ) && $m1[1] === $m2[1] );
ket_luan( 'nạp bù theo đợt: mỗi đợt một câu dò lô, nạp lại không đếm hai lần.' );
