<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LUỒNG RIÊNG CỦA MÁY TỰ ĐỘNG / VĂN PHÒNG — BỎ TẠM ỨNG, THÊM BƯỚC THANH TOÁN.
 *
 * Anh Thắng 21/09/2026: *"Đổi quy trình quyết toán với MTĐ và VP: Tạo Đơn · Gửi Chi · Duyệt Chi
 * · Duyệt Quyết Toán · Thanh Toán và Xuất Misa"*, kèm *"Không đi qua đường tạm ứng"* và chốt lại
 * rằng thanh toán với xuất MISA là *"hai bước tách rời"*.
 *
 * =============================================================================================
 * 🔴 KHÔNG ĐẶT SÁU CHUỖI TRẠNG THÁI MỚI
 * =============================================================================================
 * Tên trạng thái đang được đọc ở 377 chỗ (PHP + màn), phần lớn là `in_array($st, array(...))`.
 * Thêm một bộ chuỗi song song là mỗi chốt ấy phải học thêm sáu tên — chỗ nào quên thì đơn MTĐ
 * lọt lưới im lặng: không hiện ở màn duyệt, không vào báo cáo, không ra MISA, mà cũng chẳng có
 * câu lỗi nào. Nên chuỗi lưu trong sổ GIỮ NGUYÊN; chỉ ba thứ khác:
 *   1. BỎ bước `Chờ cấp tạm ứng` — duyệt chi xong sang thẳng "đã duyệt, đang chi".
 *   2. THÊM bước `Đã thanh toán` giữa quyết toán và xuất MISA.
 *   3. ĐỔI CHỮ trên màn.
 *
 * Chạy: php tools/test/kiem-luong-theo-khoi.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/* ═══ 1. HAI LUỒNG ═══════════════════════════════════════════════════════════════ */
teq( '🔴 KVC giữ nguyên bảy bước cũ',
	array( 'Nháp', 'Chờ duyệt tạm ứng', 'Chờ cấp tạm ứng', 'Đã cấp tạm ứng', 'Chờ quyết toán', 'Đã quyết toán', 'Đã xuất MISA' ),
	array_keys( VHCP_Don::luong_cua( 'kvc' ) ) );
teq( '🔴 MTĐ bỏ `Chờ cấp tạm ứng`, thêm `Đã thanh toán`',
	array( 'Nháp', 'Chờ duyệt tạm ứng', 'Đã cấp tạm ứng', 'Chờ quyết toán', 'Đã quyết toán', 'Đã thanh toán', 'Đã xuất MISA' ),
	array_keys( VHCP_Don::luong_cua( 'mtd' ) ) );
teq( '   VP đi cùng luồng với MTĐ', VHCP_Don::luong_cua( 'mtd' ), VHCP_Don::luong_cua( 'vp' ) );
/* ⚠️ Khối lạ / rỗng ngã về luồng KVC — hướng hỏng an toàn: luồng cũ là luồng mọi chốt khác
   trong mã đang hiểu. */
teq( '⚠️ khối rỗng → luồng KVC', VHCP_Don::luong_cua( 'kvc' ), VHCP_Don::luong_cua( '' ) );

/* 🔴 SÁU BƯỚC ANH THẮNG NÊU, ĐÚNG CHỮ. */
teq( '🔴 chữ trên màn của MTĐ',
	array( 'Tạo đơn', 'Chờ duyệt chi', 'Đã duyệt chi', 'Chờ duyệt quyết toán', 'Đã duyệt quyết toán', 'Đã thanh toán', 'Đã xuất MISA' ),
	array_values( VHCP_Don::luong_cua( 'mtd' ) ) );
teq( '🔴 cùng một chuỗi trong sổ, hai bên đọc ra hai chữ khác nhau',
	array( 'Chờ duyệt tạm ứng', 'Chờ duyệt chi' ),
	array( VHCP_Don::ten_tt( 'Chờ duyệt tạm ứng', 'kvc' ), VHCP_Don::ten_tt( 'Chờ duyệt tạm ứng', 'mtd' ) ) );
/* ⚠️ Trạng thái KHÔNG có trong luồng (đơn MTĐ lập trước bản này còn đứng ở `Chờ cấp tạm ứng`)
   phải trả NGUYÊN VĂN, không trả rỗng — rỗng là màn hiện ô trắng, không ai biết đơn ở đâu. */
teq( '⚠️ trạng thái lạ với luồng → trả nguyên văn', 'Chờ cấp tạm ứng', VHCP_Don::ten_tt( 'Chờ cấp tạm ứng', 'mtd' ) );
teq( '⚠️ rỗng → coi là Nháp', 'Tạo đơn', VHCP_Don::ten_tt( '', 'mtd' ) );

/* ═══ 2. 🔴 `Đã thanh toán` PHẢI LÀ ĐÃ CHỐT SỔ ═══════════════════════════════════
 * Đứng sau `Đã quyết toán` thì đương nhiên chốt. Quên là đơn ĐÃ TRẢ TIỀN vẫn sửa được số. */
t( '🔴 `Đã thanh toán` nằm trong TT_CHOT', VHCP_Don::da_chot( 'Đã thanh toán' ) );
t( '   và `Đã quyết toán` vẫn thế', VHCP_Don::da_chot( 'Đã quyết toán' ) );
t( '   còn `Đã cấp tạm ứng` thì chưa chốt', ! VHCP_Don::da_chot( 'Đã cấp tạm ứng' ) );
/* ⚠️ `da_cap_tien()` so chỉ số với mốc `Đã cấp tạm ứng`. Bước mới nằm SAU mốc nên phép ấy vẫn
   đúng mà không phải sửa — phép canh này giữ cho ai đó đừng chèn bước mới vào TRƯỚC mốc. */
t( '⚠️ `Đã thanh toán` vẫn tính là đã cấp tiền (nằm sau mốc)', VHCP_Don::da_cap_tien( 'Đã thanh toán' ) );
t( '   `Chờ duyệt tạm ứng` thì không', ! VHCP_Don::da_cap_tien( 'Chờ duyệt tạm ứng' ) );

/* ═══ 3. 🔴 SẴN SÀNG XUẤT MISA — TUỲ KHỐI ═══════════════════════════════════════ */
teq( '🔴 KVC: xuất khi `Đã quyết toán`',  'Đã quyết toán',  VHCP_Don::tt_truoc_misa( 'kvc' ) );
teq( '🔴 MTĐ: xuất khi `Đã thanh toán`',  'Đã thanh toán',  VHCP_Don::tt_truoc_misa( 'mtd' ) );
teq( '   VP cũng vậy',                    'Đã thanh toán',  VHCP_Don::tt_truoc_misa( 'vp' ) );

/* ═══ 4. CHẠY THẬT MỘT ĐƠN MTĐ QUA CẢ LUỒNG ═════════════════════════════════════ */
global $wpdb;
$mk = function ( $khoi, $st ) use ( $wpdb ) {
	$ma = 'L' . strtoupper( substr( md5( $khoi . $st . microtime( true ) . mt_rand() ), 0, 7 ) );
	$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => $ma, 'ky' => '01/09/2026', 'nguoi_lap' => 'NV Thử',
		'don_vi' => '', 'khoi' => $khoi, 'ngay_tao' => '2026-09-01 08:00:00', 'trang_thai' => $st, 'ghi_chu' => '' ) );
	return $ma;
};
$tt = function ( $ma ) { $d = VHCP_Don::don_row( $ma ); return (string) $d['trang_thai']; };

/* 🔴 DUYỆT CHI: MTĐ nhảy thẳng qua bước cấp tạm ứng. */
$m1 = $mk( 'mtd', 'Chờ duyệt tạm ứng' );
VHCP_Don::duyet_tam_ung( $m1, 'KT Thử', '' );
teq( '🔴 MTĐ duyệt xong → `Đã cấp tạm ứng` (bỏ bước chờ cấp)', 'Đã cấp tạm ứng', $tt( $m1 ) );
$k1 = $mk( 'kvc', 'Chờ duyệt tạm ứng' );
VHCP_Don::duyet_tam_ung( $k1, 'KT Thử', '' );
teq( '🔴 KVC duyệt xong vẫn `Chờ cấp tạm ứng` — KHÔNG đổi', 'Chờ cấp tạm ứng', $tt( $k1 ) );

/* 🔴 THANH TOÁN: chỉ khối có bước ấy, và chỉ từ `Đã quyết toán`. */
$m2 = $mk( 'mtd', 'Đã quyết toán' );
$r  = VHCP_Don::danh_dau_thanh_toan( $m2, 'KT Thử' );
t( 'MTĐ đánh dấu thanh toán: ok', ! empty( $r['success'] ), $r );
teq( '🔴 → `Đã thanh toán`', 'Đã thanh toán', $tt( $m2 ) );

$k2 = $mk( 'kvc', 'Đã quyết toán' );
$r  = VHCP_Don::danh_dau_thanh_toan( $k2, 'KT Thử' );
t( '🔴 KVC KHÔNG có bước này → chối', empty( $r['success'] ), $r );
teq( '   và trạng thái KHÔNG đổi', 'Đã quyết toán', $tt( $k2 ) );

$m3 = $mk( 'mtd', 'Chờ quyết toán' );
$r  = VHCP_Don::danh_dau_thanh_toan( $m3, 'KT Thử' );
t( '🔴 chưa duyệt quyết toán → chối', empty( $r['success'] ), $r );
/* ⚠️ Câu chối phải nói tên trạng thái THEO CHỮ CỦA KHỐI ẤY. Báo "đang ở Chờ quyết toán" cho
   người MTĐ là nói một cái tên họ không thấy trên màn bao giờ. */
t( '⚠️ câu chối gọi trạng thái bằng chữ của khối đó',
	false !== mb_strpos( (string) $r['error'], 'Chờ duyệt quyết toán' ), $r['error'] );

/* ═══ 5. 🔴 BẢN XUẤT MISA THEO ĐÚNG BƯỚC CỦA TỪNG KHỐI ══════════════════════════
 * Đơn MTĐ vừa duyệt quyết toán mà đã rơi vào bản xuất là xuất MISA cho khoản chưa trả tiền. */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array( array( 'Chi phí khác', '64136', '331', '', '', '', '', '', '', 'mtd' ),
	array( 'Chi phí khác', '6428', '', '', '', '', '', '', '', 'kvc' ) ), false );
VHCP_Cfg::clear_cache();
$dong = function ( $ma ) use ( $wpdb ) {
	$wpdb->insert( VHCP_DB::t( 'chiphi' ), array( 'ma_don' => $ma, 'ngay' => '2026-09-01', 'coso' => 'GIAN THỬ',
		'nhom' => 'Chi phí khác', 'noi_dung' => 'x', 'thanh_tien' => 100000,
		'phan_loai_tt' => 'Thanh toán cá nhân', 'tk_no' => '', 'tk_co' => '141', 'doi_tuong' => '' ) );
};
$mA = $mk( 'mtd', 'Đã quyết toán' );  $dong( $mA );   // MTĐ: chưa trả tiền -> KHÔNG được xuất
$mB = $mk( 'mtd', 'Đã thanh toán' );  $dong( $mB );   // MTĐ: đã trả       -> ĐƯỢC xuất
$kA = $mk( 'kvc', 'Đã quyết toán' );  $dong( $kA );   // KVC: đã quyết toán -> ĐƯỢC xuất
$out = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
$co  = array_flip( $out['maDons'] );
t( '🔴 MTĐ mới duyệt quyết toán, CHƯA thanh toán → không ra MISA', ! isset( $co[ $mA ] ), $out['maDons'] );
t( '🔴 MTĐ đã thanh toán → CÓ ra MISA',                             isset( $co[ $mB ] ), $out['maDons'] );
t( '🔴 KVC vẫn xuất từ `Đã quyết toán` như cũ',                     isset( $co[ $kA ] ), $out['maDons'] );

/* ═══ 6. HAI BÊN CÙNG LUẬT — MÀN PHẢI KHỚP MÁY CHỦ ══════════════════════════════
 * Lệch một vế là máy chủ chối một lượt chuyển mà màn vẫn bày nút: người dùng bấm rồi ăn câu
 * lỗi không hiểu. */
$js = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
foreach ( array( 'kvc' => 'LUONG_KVC', 'mtd' => 'LUONG_CHI' ) as $k => $ten ) {
	$m = array();
	preg_match( '/var ' . $ten . '=\{(.*?)\};/s', $js, $m );
	$cap = array();
	preg_match_all( "/'([^']+)'\s*:\s*'([^']+)'/", isset( $m[1] ) ? $m[1] : '', $cap, PREG_SET_ORDER );
	$ra = array();
	foreach ( $cap as $c ) { $ra[ $c[1] ] = $c[2]; }
	teq( "🔴 màn và máy chủ cùng luồng ($k)", VHCP_Don::luong_cua( $k ), $ra );
}
preg_match( '/var TT_CHOT=\[(.*?)\];/s', $js, $m );
$cc = array();
preg_match_all( "/'([^']+)'/", isset( $m[1] ) ? $m[1] : '', $cc );
teq( '🔴 màn và máy chủ cùng danh sách "đã chốt"', VHCP_Don::TT_CHOT, isset( $cc[1] ) ? $cc[1] : array() );
preg_match( '/var KHOI_LUONG_CHI=\[(.*?)\];/s', $js, $m );
$kk = array();
preg_match_all( "/'([^']+)'/", isset( $m[1] ) ? $m[1] : '', $kk );
teq( '🔴 màn và máy chủ cùng danh sách khối đi luồng chi', VHCP_Don::KHOI_LUONG_CHI, isset( $kk[1] ) ? $kk[1] : array() );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( count( $TRUOT ) ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: MTĐ/VP bỏ tạm ứng, thêm bước thanh toán; KVC không đổi; hai bên cùng luật.\n";
