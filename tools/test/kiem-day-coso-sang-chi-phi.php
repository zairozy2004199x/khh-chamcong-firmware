<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CƠ SỞ MỚI BÊN GHẾ PHẢI TỰ SANG DANH MỤC CỦA VẬN HÀNH CHI PHÍ
 *
 * Anh Thắng 08/09/2026: *"tự đẩy lấy dữ liệu qua luôn, khi tạo cơ sở mới bên ghế, hệ thống tự
 * đẩy cơ sở sang luôn"*.
 *
 * =============================================================================================
 * 🔴 CẮN THẬT 09/09/2026 — VÌ SAO CÓ BÀI NÀY.
 *    Hôm 08/09 tôi làm tai nghe bên chi phí (`add_action( 'vhg_coso_da_luu', … )`) và thêm lệnh
 *    phát vào `wordpress/vhcp-ghe/` — nhưng đó là BẢN GHẾ CŨ 1.42.0 nằm trong nhánh chi phí,
 *    KHÔNG phải bản đang chạy trên /ghe (bản này, nhánh posh, 2.15.x). Nối dây đúng một đầu.
 *    Anh Thắng hỏi *"bên trang ghế mà thêm 1 cơ sở mới, nó có tự đẩy sang vận hành chi phí
 *    không"* — soi mã ra là KHÔNG, mà chẳng có gì báo. Bài này chốt đầu phát nằm ĐÚNG ở đây.
 *
 * 🔴 PHẢI PHÁT Ở CẢ BA NHÁNH của `luu_coso()`. Bên chi phí CHỈ THÊM khi chưa có, nên phát thừa
 *    là vô hại; phát THIẾU thì cơ sở ấy im lặng không bao giờ sang. Ba nhánh:
 *      · sửa theo id       (đổi tên cơ sở)
 *      · tên đã có         (tự vá lượt đẩy nào lỡ rơi mất trước đó)
 *      · thêm mới          (đường chính anh Thắng hỏi)
 *
 * ⚠️ CHẠY THẬT `luu_coso()` với CSDL giả và `do_action()` thật (ghi lại lượt gọi).
 * ⚠️ CHỖ MÙ, ghi rõ: bài này ở nhánh Ghế nên KHÔNG với sang mã plugin chi phí được. Đầu NGHE có
 *    bài riêng bên ấy (`tools/test/kiem-day-coso-tu-ghe.php`). Bài này chốt đầu PHÁT.
 *
 * Chạy: php tools/test/kiem-day-coso-sang-chi-phi.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$GOC = dirname( dirname( __DIR__ ) );
$MAY = file_get_contents( $GOC . '/vhcp-ghe/includes/class-vhg-may.php' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BỆ ĐỠ — bốc CHÍNH `luu_coso()` ra chạy
 *
 * 🔴 `do_action()` Ở ĐÂY PHẢI GHI LẠI THẬT. Bệ đỡ nào để nó rỗng thì mọi đường qua móc XANH
 *    OAN — đúng cái bẫy đã dính hôm 08/09 ở bệ đỡ bên chi phí.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$GOI = array();                       // [ [tên móc, tham số], … ]
function do_action( $moc ) {
	global $GOI;
	$a = func_get_args();
	array_shift( $a );
	$GOI[] = array( $moc, $a );
}
class VHG_DB { public static function t( $x ) { return 'wp_vhg_' . $x; } }

class WPDB_GIA {
	public $insert_id = 0;
	public $lam = array();            // nhật ký lệnh, để soi "có thật sự ghi không"
	public $co_ten = null;            // get_var trả gì cho câu "tên này đã có chưa"
	public function prepare( $q, ...$a ) { return $q; }
	public function get_var( $q ) { return $this->co_ten; }
	public function update( $b, $d, $w ) { $this->lam[] = array( 'update', $d, $w ); return 1; }
	public function insert( $b, $d ) { $this->lam[] = array( 'insert', $d ); $this->insert_id = 77; return 1; }
}

$i = strpos( $MAY, 'public static function luu_coso(' );
$j = strpos( $MAY, "\n\t}", $i );
t( 'bốc được luu_coso()', false !== $i && $j > $i );
if ( false === $i ) { echo "\n✗ Không bốc được — dừng.\n"; exit( 1 ); }
/* Bốc kèm ba hàm bạn: cờ chặn dội, tai nghe chiều về, và hàm gác lượt phát. Bốc thiếu một
   trong ba là bài kiểm chạy trên bản dựng lại chứ không phải mã thật. */
$phu = '';
foreach ( array( 'private static $dang_nhan_tu_chi_phi = false;' ) as $x ) {
	t( 'bốc được cờ chặn dội', false !== strpos( $MAY, $x ), '' );
	$phu .= $x . ' ';
}
foreach ( array( 'public static function moc_coso_chi_phi(', 'private static function bao_da_luu_(' ) as $ten_ham ) {
	$a = strpos( $MAY, $ten_ham );
	$b = strpos( $MAY, "\n\t}", $a );
	t( 'bốc được ' . rtrim( $ten_ham, '(' ), false !== $a && $b > $a );
	$phu .= substr( $MAY, $a, $b - $a + 3 ) . ' ';
}
eval( 'class TC { ' . substr( $MAY, $i, $j - $i + 3 ) . ' ' . $phu
	. ' private static function quen_dem_reset_() {} }' );

function chay( $id, $ten, $ten_da_co = null ) {
	global $wpdb, $GOI;
	$GOI   = array();
	$wpdb  = new WPDB_GIA();
	$wpdb->co_ten = $ten_da_co;
	$ra = TC::luu_coso( $id, $ten );
	return array( 'ra' => $ra, 'goi' => $GOI, 'lam' => $wpdb->lam );
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 BA NHÁNH — ĐƯỜNG NÀO CŨNG PHẢI BÁO RA
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$r = chay( 0, 'AEON Bình Tân' );                    // thêm mới
teq( '🔴 THÊM MỚI → phát móc', array( array( 'vhg_coso_da_luu', array( 'AEON Bình Tân' ) ) ), $r['goi'] );
teq( '   và có ghi vào bảng thật', 'insert', $r['lam'][0][0] );

$r = chay( 0, 'AEON Bình Tân', 12 );                // tên đã có
teq( '🔴 TÊN ĐÃ CÓ → vẫn phát (tự vá lượt đẩy lỡ rơi)',
	array( array( 'vhg_coso_da_luu', array( 'AEON Bình Tân' ) ) ), $r['goi'] );

$r = chay( 5, 'AEON Tân Phú' );                     // sửa theo id
teq( '🔴 ĐỔI TÊN theo id → phát TÊN MỚI',
	array( array( 'vhg_coso_da_luu', array( 'AEON Tân Phú' ) ) ), $r['goi'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. KHÔNG PHÁT BỪA
 *
 * Tên rỗng bị chối ngay từ đầu. Phát ở đó là bên chi phí nhận một cơ sở tên rỗng — mà danh mục
 * bên ấy tra theo TÊN, nên một dòng rỗng là một cái bẫy im lặng.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$r = chay( 0, '   ' );
teq( '🔴 tên rỗng → KHÔNG phát gì', array(), $r['goi'] );
t( '   và trả lỗi', empty( $r['ra']['ok'] ), $r['ra'] );
teq( '   và KHÔNG đụng bảng', array(), $r['lam'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. PHÁT MÓC CHỨ KHÔNG GỌI THẲNG SANG PLUGIN KIA
 *
 * 🔴 Hai plugin cài rời nhau. Gọi thẳng `VHCP_Cfg::…` từ đây là gỡ plugin chi phí ra thì trang
 *    ghế chết theo, mà chết ở một đường ngầm không ai bấm để thấy.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$than = substr( $MAY, $i, $j - $i + 3 );
t( '🔴 không gọi thẳng lớp của plugin chi phí', false === strpos( $than, 'VHCP_' ), '' );
/* Lượt phát THẬT nằm trong `bao_da_luu_()` — `luu_coso()` gọi qua hàm ấy để đi chung một cái
   cổng có cờ chặn dội. Nên phép này soi ĐÚNG hàm cổng, chứ không soi thân `luu_coso()`. */
$c = strpos( $MAY, 'private static function bao_da_luu_(' );
$than_bao = substr( $MAY, $c, strpos( $MAY, "\n\t}", $c ) - $c + 3 );
t( '   tên móc đúng thứ bên chi phí đang nghe', false !== strpos( $than_bao, "do_action( 'vhg_coso_da_luu', \$ten );" ), '' );
/* Bỏ chú thích trước khi đếm: chú thích trên hàm có kể tên móc, đếm cả nó là ra số ảo. */
$ma_sach = preg_replace( '#/\*.*?\*/#s', '', $than );
$ma_sach = preg_replace( '#//[^\n]*#', '', $ma_sach );
teq( '🔴 báo đủ BA lượt, không thiếu nhánh nào', 3, substr_count( $ma_sach, 'self::bao_da_luu_( $ten );' ) );
/* 🔴 Và KHÔNG đường nào phát thẳng, vòng qua cổng. Phát thẳng là bỏ qua cờ chặn dội — cái tên
   nhận từ chi phí lại bị báo ngược về chi phí. */
teq( '🔴 không nhánh nào phát thẳng, bỏ qua cổng chặn dội', 0, substr_count( $ma_sach, "do_action(" ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. CHIỀU VỀ — CƠ SỞ ĐƠN VỊ POSH BÊN CHI PHÍ SANG ĐÂY
 *
 * Anh Thắng 09/09/2026: *"chỉ đẩy sang nếu nó là đơn vị posh thôi"*.
 *
 * 🔴 LỌC ĐƠN VỊ LÀM BÊN CHI PHÍ, không phải ở đây — chỉ bên ấy mới biết cơ sở nào thuộc đơn vị
 *    nào. Bên này nhận cái tên đã lọc rồi. Phép canh đầu lọc nằm ở bài bên kia
 *    (`tools/test/kiem-day-coso-hai-chieu.php`), bài này canh đầu NHẬN.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$BOOT = file_get_contents( $GOC . '/vhcp-ghe/vhcp-ghe.php' );
t( '🔴 có đăng ký tai nghe cho móc bên chi phí',
	false !== strpos( $BOOT, "add_action( 'vhcp_coso_posh_da_luu', array( 'VHG_May', 'moc_coso_chi_phi' ) );" ), '' );

global $wpdb, $GOI;
$GOI = array(); $wpdb = new WPDB_GIA(); $wpdb->co_ten = null;
TC::moc_coso_chi_phi( 'POSH Vạn Hạnh' );
teq( '🔴 nhận tên từ chi phí → CÓ ghi vào bảng cơ sở', 'insert', $wpdb->lam[0][0] );
teq( '   và tên vào đúng ô', 'POSH Vạn Hạnh', $wpdb->lam[0][1]['ten'] );

/* 🔴 PHÉP QUAN TRỌNG NHẤT CỦA MỤC NÀY. Hai chiều đã nối, nên nếu lượt nhận này lại báo ngược
   sang chi phí thì một cái tên đi vòng qua lại giữa hai plugin. */
teq( '🔴 KHÔNG báo ngược lại sang chi phí (chống ném qua ném lại)', array(), $GOI );

/* Còn lưu bình thường thì VẪN phải báo — cờ chặn không được kẹt lại sau lượt nhận. */
$r = chay( 0, 'Gian tự khai bên ghế' );
teq( '🔴 cờ chặn KHÔNG kẹt: lượt lưu kế tiếp vẫn báo',
	array( array( 'vhg_coso_da_luu', array( 'Gian tự khai bên ghế' ) ) ), $r['goi'] );

/* Tên rỗng: chối ngay, không ghi, không báo. */
$GOI = array(); $wpdb = new WPDB_GIA(); $wpdb->co_ten = null;
TC::moc_coso_chi_phi( '  ' );
teq( '🔴 tên rỗng từ chi phí → KHÔNG ghi gì', array(), $wpdb->lam );

/* Tên đã có bên này: không đụng dòng cũ (mã KH, tỉnh, cờ reset giữ nguyên). */
$GOI = array(); $wpdb = new WPDB_GIA(); $wpdb->co_ten = 9;
TC::moc_coso_chi_phi( 'POSH Vạn Hạnh' );
teq( '🔴 tên ĐÃ CÓ → không ghi đè dòng đang chạy', array(), $wpdb->lam );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: cơ sở lưu bên ghế là báo sang chi phí, đường nào cũng báo.\n";
