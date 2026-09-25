<?php
/* ════════════════════════════════════════════════════════════════════════════════════════════
 * BÁO CÁO TỔNG — CƠ SỞ KHÔNG CÓ GHẾ, KHÔNG CÓ TIỀN THÌ KHÔNG IN DÒNG (2.148.0)
 *
 * Anh Thắng 25/09/2026: *"Cho nó biến mất được không, như đã nói nó thuộc khu vực khác, HCM không
 * có điểm đó trong dữ liệu, nó tự lấy sao kê nên tự gọi vào thôi"*. 138 điểm phía Bắc được tạo TÊN
 * bên Ghế từ màn "chưa gán mã" của Sao Kê — 0 ghế, 0 tiền — mà mỗi điểm vẫn một dòng toàn gạch.
 *
 * Luật (VHG_KeToan::bct_ds_cs_): có ghế đang chạy → luôn một dòng kể cả 0đ; đóng cửa HOẶC không có
 * ghế → chỉ hiện khi kỳ này có tiền; tên có tiền mà không còn trong danh mục vẫn hiện.
 *
 * Chạy: php tools/test/kiem-bct-coso-khong-ghe.php
 * ════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $j = strpos( $src, '{', $i ); $d = 0; $n = strlen( $src ); for ( $k = $j; $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
$K = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );

echo "── 1. Nguồn ─────────────────────────────────────────────────────\n";
$f = boc( $K, 'public static function bct_ds_cs_(' );
t( 'bốc được bct_ds_cs_()', '' !== $f );
t( 'bao_cao_tong() lấy danh sách cơ sở qua bct_ds_cs_ (một luật, một chỗ)', false !== strpos( boc( $K, 'public static function bao_cao_tong(' ), 'self::bct_ds_cs_( $ma_kh, $dong, $dem_ghe, $o, $muc )' ) );
t( 'luật ghi thẳng trong hàm: đóng cửa HOẶC không ghế, và không tiền → bỏ', false !== strpos( $f, "( isset( \$dong[ \$cs ] ) || empty( \$dem_ghe[ \$cs ] ) ) && ! isset( \$co_tien[ \$cs ] )" ) );
eval( 'class KT { ' . $f . ' }' );

echo "── 2. Chạy bằng mảng — Gộp theo cơ sở ───────────────────────────\n";
$ma_kh   = array( 'AEON TÂN PHÚ' => 'KH1', 'GO THỦ DẦU MỘT' => 'KH2', '1 JP SB Cam Ranh.new' => '', 'JP AMLB' => '', 'CGV ĐÃ ĐÓNG' => 'KH9', 'VINCOM QUANG TRUNG' => 'KH00113' );
$dong    = array( 'CGV ĐÃ ĐÓNG' => true );
$dem_ghe = array( 'AEON TÂN PHÚ' => 4, 'GO THỦ DẦU MỘT' => 2 );
$o       = array( 'AEON TÂN PHÚ' => array( '2026-09-12' => 500000 ), 'JP AMLB' => array( '2026-09-12' => 100000 ), 'TÊN CŨ ĐÃ XOÁ' => array( '2026-09-13' => 70000 ) );
$ds = KT::bct_ds_cs_( $ma_kh, $dong, $dem_ghe, $o, 'coso' );
t( '🔴 cơ sở 0 ghế, 0 tiền (1 JP SB Cam Ranh.new, VINCOM QUANG TRUNG mới tạo) KHÔNG in dòng', ! in_array( '1 JP SB Cam Ranh.new', $ds, true ) && ! in_array( 'VINCOM QUANG TRUNG', $ds, true ), $ds );
t( 'cơ sở có ghế mà 0đ vẫn một dòng (GO THỦ DẦU MỘT) — luật cũ giữ nguyên', in_array( 'GO THỦ DẦU MỘT', $ds, true ) );
t( 'cơ sở 0 ghế nhưng kỳ này CÓ tiền vẫn hiện (JP AMLB) — tiền thật không giấu', in_array( 'JP AMLB', $ds, true ) );
t( 'cơ sở đóng cửa, 0 tiền → ẩn (luật 2.144.0 giữ nguyên)', ! in_array( 'CGV ĐÃ ĐÓNG', $ds, true ) );
t( 'tên có tiền mà không còn trong danh mục vẫn hiện', in_array( 'TÊN CŨ ĐÃ XOÁ', $ds, true ) );
$sx = $ds; sort( $sx );
t( 'xếp A→Z, không trùng', $ds === $sx && count( $ds ) === count( array_unique( $ds ) ) );
$ds3 = KT::bct_ds_cs_( $ma_kh, $dong, $dem_ghe, array(), 'coso' );
t( 'không có tiền gì cả → chỉ còn 2 cơ sở có ghế', array( 'AEON TÂN PHÚ', 'GO THỦ DẦU MỘT' ) === $ds3, $ds3 );
$dem_ghe['VINCOM QUANG TRUNG'] = 1;
t( 'gán ghế đầu tiên cho VINCOM QUANG TRUNG → hiện lại ngay', in_array( 'VINCOM QUANG TRUNG', KT::bct_ds_cs_( $ma_kh, $dong, $dem_ghe, array(), 'coso' ), true ) );

echo "── 3. Từng ghế (khoá \"cơ sở|mã\") ────────────────────────────────\n";
$o2  = array( 'AEON TÂN PHÚ|80001' => array( '2026-09-12' => 1 ), 'JP AMLB|' => array( '2026-09-12' => 5 ) );
$ds2 = KT::bct_ds_cs_( $ma_kh, $dong, array( 'AEON TÂN PHÚ' => 4 ), $o2, 'ghe' );
t( 'lấy đúng phần cơ sở trước dấu |: JP AMLB có tiền → hiện; 1 JP SB Cam Ranh.new không', in_array( 'JP AMLB', $ds2, true ) && in_array( 'AEON TÂN PHÚ', $ds2, true ) && ! in_array( '1 JP SB Cam Ranh.new', $ds2, true ) && ! in_array( 'JP AMLB|', $ds2, true ), $ds2 );

echo "───────────────────────────────────────────────────────────────\n";
if ( $TRUOT ) { echo '✗ HỎNG ' . count( $TRUOT ) . ' / ' . ( $DAT + count( $TRUOT ) ) . "\n"; exit( 1 ); }
echo "✓ SẠCH — $DAT phép\n";
