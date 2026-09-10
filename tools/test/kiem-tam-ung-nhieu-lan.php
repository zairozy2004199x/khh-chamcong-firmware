<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐƠN DỰ ÁN: TẠM ỨNG NHIỀU LẦN.
 *
 * Anh Thắng 10/09/2026, nói về đơn của bộ phận Kỹ thuật: *"Đơn có tạm ứng nhiều lần"*.
 *
 * =============================================================================================
 * 🔴 TRƯỚC BẢN NÀY MỖI (giai đoạn × loại) CHỈ GIỮ MỘT BẢN GHI — lượt ứng thứ hai ĐÈ MẤT lượt
 *    đầu, không báo gì. Số "đã ứng" tụt xuống còn đúng lần cuối, nên phần bù/thu ở quyết toán
 *    tính trên một con số nhỏ hơn thực tế đã chi. Đây là tiền thật, và hỏng lặng lẽ.
 *
 * 🔴 DỮ LIỆU CŨ LÀ MỘT ĐỐI TƯỢNG, DỮ LIỆU MỚI LÀ DANH SÁCH. Dự án đã chạy trước bản này vẫn
 *    mang dạng cũ; mọi chỗ đọc phải nhận cả hai. Đọc thẳng `['amount']` thì dự án CŨ vẫn ra số
 *    đúng còn dự án MỚI ra rỗng — hỏng đúng ở chỗ vừa dùng tính năng mới, mà nhìn sổ cũ thì
 *    thấy bình thường. Đây là ca dễ lọt nhất, nên bài này soi nó trước.
 *
 * ⚠️ CHẠY THẬT `pay_ds()` và `pay_tong()` bốc từ mã nguồn.
 *
 * Chạy: php tools/test/kiem-tam-ung-nhieu-lan.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$GOC  = dirname( dirname( __DIR__ ) );
$DA   = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-duan.php' );
$MISA = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-misa.php' );
$HTML = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/templates/app.html' );

function boc( $src, $neo ) {
	$a = strpos( $src, $neo );
	if ( false === $a ) { echo "\n✗ Không bốc được: $neo\n"; exit( 1 ); }
	return substr( $src, $a, strpos( $src, "\n\t}", $a ) - $a + 3 );
}
/* Bệ đỡ: đủ họ hàng mà `confirm_pay()` gọi tới — thiếu một cái là nổ giữa chừng, và bài kiểm
   thành ra chỉ chạy được nửa đầu. */
class GIO { public function format( $f ) { return '01/01/2026 08:00'; } }
class VHCP_Util {
	public static function num( $x ) { return (float) $x; }
	public static function err( $m ) { return array( 'success' => false, 'error' => $m ); }
	public static function ok( $e = array() ) { return array_merge( array( 'success' => true ), $e ); }
	public static function now() { return new GIO(); }
}
eval( 'class DA { ' . boc( $DA, 'public static function pay_ds(' ) . ' ' . boc( $DA, 'public static function pay_tong(' ) . ' }' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 NHẬN CẢ HAI DẠNG DỮ LIỆU
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$cu = array( 'tamUng' => array( 'tu' => array( 'done' => true, 'amount' => 5000000, 'date' => '01/09/2026', 'by' => 'KT' ) ) );
teq( '🔴 dạng CŨ (một đối tượng) → đọc ra 1 lần', 1, count( DA::pay_ds( $cu, 'tamUng', 'tu' ) ) );
teq( '   và tổng đúng số cũ', 5000000.0, DA::pay_tong( $cu, 'tamUng', 'tu' ) );

$moi = array( 'tamUng' => array( 'tu' => array(
	array( 'done' => true, 'amount' => 3000000, 'date' => '01/09/2026', 'by' => 'KT' ),
	array( 'done' => true, 'amount' => 2000000, 'date' => '05/09/2026', 'by' => 'KT' ),
	array( 'done' => true, 'amount' => 1500000, 'date' => '09/09/2026', 'by' => 'KT' ),
) ) );
teq( '🔴 dạng MỚI (danh sách) → đọc ra đủ 3 lần', 3, count( DA::pay_ds( $moi, 'tamUng', 'tu' ) ) );
teq( '🔴 tổng là CỘNG DỒN, không phải lần cuối', 6500000.0, DA::pay_tong( $moi, 'tamUng', 'tu' ) );

teq( 'chưa chi lần nào → rỗng', array(), DA::pay_ds( array(), 'tamUng', 'tu' ) );
teq( '   và tổng 0',            0, DA::pay_tong( array(), 'tamUng', 'tu' ) );
teq( 'loại khác chưa chi → rỗng', array(), DA::pay_ds( $moi, 'tamUng', 'tt' ) );
teq( 'giai đoạn khác chưa chi → rỗng', array(), DA::pay_ds( $moi, 'quyetToan', 'tu' ) );
/* Rác trong danh sách (dòng thiếu 'amount') không được tính là một lần chi. */
$ban = array( 'tamUng' => array( 'tu' => array( array( 'amount' => 1000 ), array( 'ghiChu' => 'x' ), 'rác' ) ) );
teq( '🔴 dòng rác trong danh sách → bỏ qua', 1, count( DA::pay_ds( $ban, 'tamUng', 'tu' ) ) );
teq( '   tổng không dính rác', 1000.0, DA::pay_tong( $ban, 'tamUng', 'tu' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. GHI THÊM · GỠ TỪNG LẦN — CHẠY THẬT
 *
 * ⚠️ Mục này CHẠY `confirm_pay()` / `unconfirm_pay()`, không dò chuỗi mã. Phá thử chỉ ra rằng
 *    dò chuỗi thì một đột biến "vẫn gọi pay_ds() rồi vứt kết quả đi" (`$ds = array();` chen vào
 *    giữa) vẫn xanh — hai chuỗi bài kiểm tìm đều còn nguyên trong tệp.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
class KHO { public static $meta = array(); public static $tt = 'Đã duyệt'; }
class VHCP_Meta {
	public static function get_json( $k, $d = array() ) { return isset( KHO::$meta[ $k ] ) ? KHO::$meta[ $k ] : $d; }
	public static function set_json( $k, $v ) { KHO::$meta[ $k ] = $v; }
}
eval( 'class DA2 { ' . boc( $DA, 'public static function confirm_pay(' ) . ' '
	. boc( $DA, 'public static function unconfirm_pay(' ) . ' '
	. boc( $DA, 'public static function pay_ds(' ) . ' ' . boc( $DA, 'public static function pay_tong(' ) . ' '
	. ' public static function find( $m ) { return array( "trang_thai" => KHO::$tt ); }'
	. ' public static function get_pay( $m ) { return VHCP_Meta::get_json( "daPay_" . $m, array() ); } }' );

function ung( $so, $gc = '' ) { return DA2::confirm_pay( 'D1', 'tamUng', 'tu', $so, 'KT', $gc ); }
function tong_ung() { return DA2::pay_tong( DA2::get_pay( 'D1' ), 'tamUng', 'tu' ); }

KHO::$meta = array();
ung( 3000000 ); ung( 2000000 ); ung( 1500000 );
teq( '🔴 ứng ba lần → giữ đủ BA', 3, count( DA2::pay_ds( DA2::get_pay( 'D1' ), 'tamUng', 'tu' ) ) );
teq( '🔴 tổng CỘNG DỒN, không phải lần cuối', 6500000.0, tong_ung() );
$ds = DA2::pay_ds( DA2::get_pay( 'D1' ), 'tamUng', 'tu' );
teq( '   thứ tự giữ nguyên (lần 1 đứng đầu)', 3000000.0, VHCP_Util::num( $ds[0]['amount'] ) );
teq( '   ghi lại ai chi',  'KT', $ds[0]['by'] );
t( '   và có mốc thời gian', ! empty( $ds[0]['date'] ), $ds[0] );

$r = ung( 0 );
t( '🔴 chối lần chi 0đ',      empty( $r['success'] ), $r );
t( '   và KHÔNG ghi thêm gì', 3 === count( DA2::pay_ds( DA2::get_pay( 'D1' ), 'tamUng', 'tu' ) ) );
$r = ung( -5 );
t( '   chối cả số âm',        empty( $r['success'] ), $r );

/* Gỡ ĐÚNG một lần — lần giữa. */
DA2::unconfirm_pay( 'D1', 'tamUng', 'tu', 1 );
teq( '🔴 gỡ lần giữa → còn HAI lần', 2, count( DA2::pay_ds( DA2::get_pay( 'D1' ), 'tamUng', 'tu' ) ) );
teq( '   và tổng trừ đúng số của lần ấy', 4500000.0, tong_ung() );
/* Không truyền vị trí → gỡ lần cuối. */
DA2::unconfirm_pay( 'D1', 'tamUng', 'tu' );
teq( 'không truyền vị trí → gỡ lần CUỐI', 3000000.0, tong_ung() );
$r = DA2::unconfirm_pay( 'D1', 'tamUng', 'tu', 9 );
t( '🔴 chối vị trí không có',  empty( $r['success'] ), $r );
DA2::unconfirm_pay( 'D1', 'tamUng', 'tu' );
teq( 'gỡ hết → tổng về 0', 0, tong_ung() );
$r = DA2::unconfirm_pay( 'D1', 'tamUng', 'tu' );
t( '   gỡ tiếp thì chối, không nổ', empty( $r['success'] ), $r );

/* Dự án chưa duyệt thì không chi được — chốt cũ, phải còn. */
KHO::$meta = array(); KHO::$tt = 'Đang làm';
$r = ung( 1000000 );
t( '🔴 chưa duyệt → KHÔNG chi được', empty( $r['success'] ), $r );
teq( '   và sổ vẫn trống', 0, tong_ung() );
KHO::$tt = 'Đã duyệt';

/* Hai loại (tu / tt) đếm riêng, không lẫn vào nhau. */
KHO::$meta = array();
ung( 1000000 );
DA2::confirm_pay( 'D1', 'tamUng', 'tt', 7000000, 'KT', '' );
teq( '🔴 phần NV tự trả và phần trả NCC đếm RIÊNG', 1000000.0, tong_ung() );
teq( '   phần trả NCC giữ số của nó', 7000000.0, DA2::pay_tong( DA2::get_pay( 'D1' ), 'tamUng', 'tt' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2b. CÁC CHỐT ĐỌC ĐƯỢC TRONG MÃ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$than_c = boc( $DA, 'public static function confirm_pay(' );
t( '🔴 ghi thêm là NỐI VÀO danh sách, không đè',
	false !== strpos( $than_c, '$ds = self::pay_ds( $p, $phase, $loai );' )
	&& false !== strpos( $than_c, '$ds[] = array(' ), '' );
t( '🔴 chối số tiền ≤ 0 (một dòng 0đ trong sổ là một câu hỏi không ai trả lời được)',
	false !== strpos( $than_c, 'if ( $so <= 0 ) { return VHCP_Util::err(' ), '' );
t( '   giữ nguyên chốt "chỉ chi khi đã duyệt"',
	false !== mb_strpos( $than_c, 'Chỉ chi tiền khi dự án đã kế toán duyệt tạm ứng' ), '' );

$than_u = boc( $DA, 'public static function unconfirm_pay(' );
t( '🔴 gỡ ĐÚNG một lần, không xoá cả ô',
	false !== strpos( $than_u, 'array_splice( $ds, $i, 1 );' )
	&& false === strpos( $than_u, "unset( \$p[ \$phase ][ \$loai ] );\n\t\tVHCP_Meta" ), '' );
t( '   không truyền vị trí thì gỡ lần CUỐI',
	false !== strpos( $than_u, '( count( $ds ) - 1 )' ), '' );
t( '   gỡ hết thì mới bỏ ô',   false !== strpos( $than_u, 'else { unset( $p[ $phase ][ $loai ] ); }' ), '' );
t( '   chối vị trí không có',  false !== mb_strpos( $than_u, 'Không có lần chi thứ ' ), '' );
t( '   chối khi chưa có lần nào', false !== mb_strpos( $than_u, 'Chưa có lần chi nào để hoàn tác' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. TỔNG GỬI XUỐNG MÀN — MỘT NGUỒN, KHÔNG CỘNG LẠI Ở HAI NƠI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 máy chủ gửi payTong cho cả 4 ô (2 giai đoạn × 2 loại)',
	false !== strpos( $DA, "'payTong'         => array(" )
	&& 4 === substr_count( boc( $DA, 'public static function get_du_an(' ), 'self::pay_tong( $pay,' ), '' );
t( '   đọc meta MỘT lần cho cả pay lẫn payTong',
	false !== strpos( $DA, '$pay = self::get_pay( $ma_da );   // đọc MỘT lần' ), '' );
t( '   màn đọc payTong, KHÔNG tự cộng lại',
	false !== strpos( $HTML, "function tong(phase,loai){ return Number(((r.payTong||{})[phase]||{})[loai]||0); }" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 MỌI CHỖ ĐỌC KHÁC PHẢI ĐI QUA pay_ds()
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 xuất MISA đọc qua pay_ds(), không đọc thẳng [\'by\']',
	false !== strpos( $MISA, "VHCP_DuAn::pay_ds( \$pay, 'tamUng', 'tu' )" )
	&& false === strpos( $MISA, "\$pay['tamUng']['tu']['by']" ), '' );
t( '🔴 màn cũng nhận cả hai dạng',
	false !== strpos( $HTML, "if(Object.prototype.hasOwnProperty.call(v,'amount')) return [v];" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. MÀN: BÙ/THU TÍNH TRÊN TỔNG ĐÃ ỨNG, VÀ GỢI Ý LÀ PHẦN CÒN LẠI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 Gợi ý điền sẵn CẢ dự toán khi đã ứng một phần là mời người ta bấm nhầm thành ứng gấp đôi. */
t( '🔴 ô gợi ý là PHẦN CÒN LẠI, không phải cả dự toán',
	false !== strpos( $HTML, 'var daUng=tong(\'tamUng\',loai), conLai=(Number(duToanAmt)||0)-daUng;' ), '' );
t( '🔴 bù/thu trừ trên TỔNG đã ứng, không trừ lần đầu',
	false !== strpos( $HTML, 'var need2=(Number(ttChi)||0)-moc-daQT;' ), '' );
t( '   và trừ cả phần đã bù/thu trước đó', false !== strpos( $HTML, "var daQT=tong('quyetToan',loai);" ), '' );
t( '   câu hỏi xác nhận nói rõ tổng sau lần này',
	false !== mb_strpos( $HTML, 'tổng sau lần này' ), '' );
t( '   nút gỡ truyền đúng vị trí lần chi',
	false !== strpos( $HTML, "undoDuAnPayUI(\\''+phase+'\\',\\''+loai+'\\','+i+')" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: ứng bao nhiêu lần cũng cộng dồn đủ, dữ liệu cũ vẫn đọc được.\n";
