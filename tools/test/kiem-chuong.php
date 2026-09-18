<?php
/**
 * KIỂM CHUÔNG THÔNG BÁO TRÊN TRẠM — và đường đẩy ra điện thoại đi kèm.
 *
 * =================================================================================================
 * 🔴 VÌ SAO BÀI NÀY CẦN
 * =================================================================================================
 * Anh Thắng 17/09/2026: *"Còn chuông thông báo ở đâu. A chưa thấy"*, rồi chốt hai việc: chuông
 * treo ở góc trên mọi tab của trạm, và *"mỗi tin chuông đẩy luôn ra điện thoại"*.
 *
 * Việc ấy đẻ ra ba chỗ hỏng đắt tiền, và bài này canh đúng ba chỗ:
 *
 *   1. HỘP THƯ LÀ CỦA NGƯỜI KHÁC. `VHCC_Chuong` đọc thẳng bảng `bao` của plugin Nội bộ. Sai một
 *      điều kiện `ma_nv` là người này đọc được hộp thư người kia — mà hộp thư ấy mang tiền lương,
 *      giờ công và tên người.
 *   2. HAI PLUGIN, HAI BẢN. Nội bộ và chấm công cài rời, bản có thể lệch. Gọi hụt một hàm là
 *      trắng cả trang WordPress, không phải mất một tính năng.
 *   3. ĐẨY GIỮA LƯỢT VẼ TRANG LÀ TREO TRANG. `VHCC_Push::gui()` là mấy lượt HTTP ra Apple/Google,
 *      mỗi lượt chờ tới 8 giây khi địa chỉ chết. Nghe `vhnb_bao_moi` rồi gửi ngay tại chỗ là nút
 *      Duyệt đứng hình. Phép thử dưới chốt: nghe thì chỉ GHI SỔ, gửi để dành tới `shutdown`.
 *
 * Chạy: php tools/test/kiem-chuong.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';

$dat = 0; $truot = array();

register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT (tới lúc chết): $dat\n";
} );

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

/** Bỏ chú thích rồi mới soi mã. Một câu trong khối `/* *\/` không phải là mã chạy. */
function chuong_ma( $duong ) {
	$ra = '';
	foreach ( token_get_all( file_get_contents( $duong ) ) as $tk ) {
		if ( is_array( $tk ) && in_array( $tk[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
		$ra .= is_array( $tk ) ? $tk[1] : $tk;
	}
	return $ra;
}

/* ================================================================= chưa cài Nội bộ
   🔴 PHẦN NÀY PHẢI CHẠY TRƯỚC KHI NẠP PLUGIN NỘI BỘ. Nạp rồi thì `class_exists('VHNB_Bao')` trả
   true mãi mãi, không dựng lại được cảnh "chỉ cài mỗi chấm công" nữa — mà đó đúng là cảnh của
   nhiều cơ sở: họ chỉ cần chấm công, không dùng bảng tin. */

vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$U = array( 'ma_nv' => 'CHU001', 'name' => 'Lê Văn Chuông', 'coso' => 'CS_A', 'role' => 1 );

teq( 'chưa cài Nội bộ -> co() = false', false, VHCC_Chuong::co() );
teq( 'chưa cài Nội bộ -> dem() rỗng, KHÔNG ném Error', '', VHCC_Chuong::dem( $U ) );

$r = VHCC_Chuong::ds( $U );
teq( 'chưa cài Nội bộ -> ds() vẫn ok (trạm không vỡ)', true, $r['ok'] );
teq( 'chưa cài Nội bộ -> ds().co = false (trạm giấu chuông đi)', false, $r['co'] );
teq( 'chưa cài Nội bộ -> ds().ds rỗng', array(), $r['ds'] );

$r = VHCC_Chuong::doc( $U, 0 );
teq( 'chưa cài Nội bộ -> doc() chối tử tế, không ném Error', false, $r['ok'] );
t( 'chưa cài Nội bộ -> doc() nói rõ thiếu cái gì',
	false !== strpos( $r['error'], 'Nội bộ' ), $r['error'] );

/* ================================================================= nạp plugin Nội bộ */

define( 'VHNB_VERSION', 'test' );
define( 'VHNB_DIR', $goc . '/wordpress/vhcp-noi-bo/' );

$chinh = file_get_contents( VHNB_DIR . 'vhcp-noi-bo.php' );
preg_match_all( "#require_once VHNB_DIR \. '(includes/class-vhnb-[a-z-]+\.php)';#", $chinh, $m_lop );
t( 'đọc được danh sách lớp trong vhcp-noi-bo.php', count( $m_lop[1] ) >= 3, $m_lop[1] );
foreach ( $m_lop[1] as $duong ) { require_once VHNB_DIR . $duong; }

global $wpdb;
foreach ( VHNB_DB::bang() as $ten => $than ) {
	$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . VHNB_DB::t( $ten ) );
	$wpdb->exec_raw( vhcc_test_ddl( VHNB_DB::t( $ten ), $than ) );
}

teq( 'cài đủ hai plugin -> co() = true', true, VHCC_Chuong::co() );

/* 🔴 KHỞI ĐỘNG LỚP PUSH — và chốt luôn rằng tệp plugin CÓ gọi nó.
   17/09/2026: `VHCC_Push::init()` tồn tại từ lúc làm thông báo đẩy nhưng KHÔNG CHỖ NÀO GỌI,
   nên trên máy thật nhịp `vhcc_5phut` chưa từng được khai và lượt nhắc "vào rồi mà chưa ra"
   chưa từng chạy. Thiếu một lời nhắc thì trông y hệt như không ai quên chấm ra — nên nó nằm
   im mấy tuần. Phép thử dưới là để nó không nằm im thêm lần nữa. */
$src_plugin = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/vhcp-cham-cong.php' );
t( '🔴 tệp plugin CÓ gọi VHCC_Push::init() — không thì mọi móc trong đó là mã chết',
	false !== strpos( $src_plugin, 'VHCC_Push::init();' ) );
VHCC_Push::init();

/* ================================================================= chỉ đọc hộp thư của mình */

$B = array( 'ma_nv' => 'CHU002', 'name' => 'Trần Thị Bê', 'coso' => 'CS_A', 'role' => 1 );

VHNB_Bao::gui( 'CHU001', 'cham_cong', 'Giờ công ngày 17/09 của bạn vừa bị sửa.', '/noi-bo/?x=1', 'k1' );
VHNB_Bao::gui( 'CHU001', 'chi_phi',   'Đơn chi phí 3.500.000đ của bạn đã duyệt.', '', 'k2' );
VHNB_Bao::gui( 'CHU002', 'cham_cong', 'RIÊNG CỦA BÊ — không ai khác được thấy.', '', 'k3' );

$a = VHCC_Chuong::ds( $U );
teq( 'A thấy đúng hai tin của mình', 2, count( $a['ds'] ) );

$chu_a = '';
foreach ( $a['ds'] as $x ) { $chu_a .= $x['chu'] . ' | '; }
t( '🔴 hộp thư của B KHÔNG lọt sang A', false === strpos( $chu_a, 'RIÊNG CỦA BÊ' ), $chu_a );

$b = VHCC_Chuong::ds( $B );
teq( 'B thấy đúng một tin của mình', 1, count( $b['ds'] ) );

teq( 'đếm của A = 2', '2', $a['dem'] );
teq( 'đếm của B = 1', '1', $b['dem'] );

/* ================================================================= cắt trường không cần bày */

$mot = $a['ds'][0];
$co  = array_keys( $mot );
sort( $co );
teq( 'ds() trả ĐÚNG bảy trường, không hơn',
	array( 'chu', 'daDoc', 'duongDan', 'id', 'luc', 'nguon', 'soLan' ), $co );
t( '🔴 KHÔNG trả `khoa` — khoá gộp nói ra sơ đồ bên trong', ! isset( $mot['khoa'] ) );
t( '🔴 KHÔNG trả `ma_nv` — trạm đã biết mình là ai rồi', ! isset( $mot['ma_nv'] ) );

/* ================================================================= đánh dấu đã đọc */

$id_b = $b['ds'][0]['id'];

/* 🔴 A gửi lên ID CỦA TIN B. Đây là cú tấn công rẻ nhất và dễ quên chặn nhất: id là số nguyên
   nhỏ, đoán bừa vài chục lượt là trúng. Chặn nằm ở `VHNB_Bao::danh_dau_doc` (luôn kèm ma_nv
   trong điều kiện), nhưng chốt lại ở đây vì đây là cửa mới mở ra ngoài. */
VHCC_Chuong::doc( $U, $id_b );
$b2 = VHCC_Chuong::ds( $B );
teq( '🔴 A KHÔNG đánh dấu đọc được tin của B', false, $b2['ds'][0]['daDoc'] );
teq( '🔴 đếm của B vẫn nguyên', '1', $b2['dem'] );

$id_a = $a['ds'][0]['id'];
$r = VHCC_Chuong::doc( $U, $id_a );
teq( 'A đọc tin của chính mình thì được', true, $r['ok'] );
teq( 'đọc xong đếm còn 1', '1', $r['dem'] );

$r = VHCC_Chuong::doc( $U, 0 );
teq( 'Đọc hết -> đếm về rỗng (trạm bỏ chấm đỏ)', '', $r['dem'] );
teq( 'đọc hết KHÔNG chạm sang hộp thư B', '1', VHCC_Chuong::dem( $B ) );

/* ================================================================= tài khoản chưa gắn mã NV */

$K = array( 'ma_nv' => '', 'name' => 'Tài khoản không mã', 'coso' => '', 'role' => 5 );
teq( 'không có mã NV -> đếm rỗng, dù vai ADMIN', '', VHCC_Chuong::dem( $K ) );
teq( 'không có mã NV -> ds().co = false', false, VHCC_Chuong::ds( $K )['co'] );
t( '🔴 chuông KHÔNG có bậc quyền: ADMIN cũng chỉ đọc hộp thư của mình',
	false === strpos( chuong_ma( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-chuong.php' ),
		'VHCC_Vai::' ) );

/* ================================================================= gộp theo khoá */

VHNB_Bao::gui( 'CHU002', 'noi_bo', 'Có người bình luận bài của bạn.', '', 'bl:9' );
VHNB_Bao::gui( 'CHU002', 'noi_bo', 'Có người bình luận bài của bạn.', '', 'bl:9' );
$b3 = VHCC_Chuong::ds( $B );
$bl = null;
foreach ( $b3['ds'] as $x ) { if ( 'bl:9' === 'bl:9' && 'noi_bo' === $x['nguon'] ) { $bl = $x; } }
t( 'hai lượt cùng khoá -> vẫn MỘT dòng', null !== $bl );
teq( 'và dòng ấy đếm soLan = 2', 2, $bl['soLan'] );

/* ================================================================= cửa `vhnb_bao_moi` */

$src_bao = chuong_ma( VHNB_DIR . 'includes/class-vhnb-bao.php' );
t( '🔴 VHNB_Bao bắn cửa `vhnb_bao_moi` khi có tin mới',
	false !== strpos( $src_bao, "do_action( 'vhnb_bao_moi'" ) );
t( '🔴 Nội bộ KHÔNG gọi thẳng VHCC_Push — gỡ chấm công ra là vỡ chỗ này',
	false === strpos( $src_bao, 'VHCC_Push' ) );
teq( 'cửa bắn ở CẢ hai nhánh (ghi mới và gộp khoá)', 2, substr_count( $src_bao, 'self::keu(' ) );

/* ================================================================= nghe thì ghi sổ, KHÔNG gửi */

$src_push = chuong_ma( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-push.php' );
t( 'VHCC_Push đăng ký nghe `vhnb_bao_moi`',
	false !== strpos( $src_push, "add_action( 'vhnb_bao_moi'" ) );
t( 'và đăng ký xả sổ lúc `shutdown`',
	false !== strpos( $src_push, "add_action( 'shutdown'" ) );

/* Cắt riêng thân `nghe_bao()` rồi soi: nó KHÔNG được gọi `gui()`. Soi cả tệp thì luôn thấy chữ
   `self::gui(` vì `xa_so()` có gọi — và đó mới là chỗ đúng để gọi. */
preg_match( '#function nghe_bao\(.*?\n\t\}#s', $src_push, $m_nghe );
t( 'tách được thân nghe_bao()', ! empty( $m_nghe ) );
t( '🔴 nghe_bao() KHÔNG gửi ngay — gửi giữa lượt vẽ trang là treo nút Duyệt 8 giây',
	empty( $m_nghe ) || false === strpos( $m_nghe[0], 'self::gui(' ), isset( $m_nghe[0] ) ? $m_nghe[0] : '' );
t( '🔴 nghe_bao() cũng KHÔNG tự POST thẳng',
	empty( $m_nghe ) || false === strpos( $m_nghe[0], 'go_cua' ) );

preg_match( '#function xa_so\(.*?\n\t\}#s', $src_push, $m_xa );
t( 'tách được thân xa_so()', ! empty( $m_xa ) );
t( 'xa_so() mới là chỗ gọi gui()',
	! empty( $m_xa ) && false !== strpos( $m_xa[0], 'self::gui(' ) );
t( '🔴 xa_so() XOÁ SỔ TRƯỚC khi gửi — không thì quay vòng ngay trong lượt shutdown',
	! empty( $m_xa ) && preg_match( '#self::\$so = array\(\);.*self::gui\(#s', $m_xa[0] ) === 1 );

/* ================================================================= sổ chờ chạy thật */

$day = new ReflectionProperty( 'VHCC_Push', 'so' );
$day->setAccessible( true );
$day->setValue( null, array() );

VHNB_Bao::gui( 'CHU001', 'cham_cong', 'Tin đi qua cửa để thử sổ chờ.', '', 'so1' );
$so = $day->getValue();
teq( 'một tin mới -> sổ chờ có đúng một dòng', 1, count( $so ) );
$dong = array_values( $so )[0];
teq( 'sổ chờ ghi đúng người nhận', 'CHU001', $dong['ma_nv'] );
teq( 'tiêu đề dịch từ nguồn `cham_cong`', 'Chấm công', $dong['tieu_de'] );
teq( '🔴 thân giữ NGUYÊN câu của bên gửi, không viết lại',
	'Tin đi qua cửa để thử sổ chờ.', $dong['than'] );

/* Gửi lại đúng khoá gộp: chuông chỉ có một dòng, nên điện thoại cũng chỉ rung một cái. */
VHNB_Bao::gui( 'CHU001', 'cham_cong', 'Tin đi qua cửa để thử sổ chờ.', '', 'so1' );
teq( 'gộp khoá trong cùng một lượt -> sổ chờ vẫn một dòng', 1, count( $day->getValue() ) );

/* Tự báo cho chính mình thì `gui()` chối TRƯỚC khi ghi, nên cửa không bắn và sổ không dài ra. */
$truoc = count( $day->getValue() );
VHNB_Bao::gui( 'CHU001', 'noi_bo', 'Tự bình luận bài của chính mình.', '', 'tu1', 'CHU001' );
teq( '🔴 tự báo cho chính mình -> không vào sổ, điện thoại không rung', $truoc, count( $day->getValue() ) );

/* Xả sổ thật: sổ phải rỗng sau lượt xả, và tin phải nằm trong hộp `push_tin` để worker lấy
   được nội dung (máy chủ chỉ đẩy một tiếng gõ cửa rỗng — xem đầu `class-vhcc-push.php`). */
VHCC_Push::xa_so();
teq( 'xả xong sổ rỗng', 0, count( $day->getValue() ) );
$hop = $wpdb->get_results( 'SELECT * FROM ' . VHCC_DB::t( 'push_tin' ) . " WHERE ma_nv='CHU001'" );
t( 'tin đã vào hộp `push_tin` cho worker lấy', count( $hop ) >= 1, count( $hop ) );
t( 'và giữ nguyên câu của bên gửi',
	! empty( $hop ) && false !== strpos( (string) $hop[0]['than'], 'Tin đi qua cửa để thử sổ chờ.' ) );

teq( 'xả sổ rỗng lần nữa -> không làm gì, không vỡ', null, VHCC_Push::xa_so() );

$day->setValue( null, array() );

/* ================================================================= đổi lịch: một tin, một đường */

$src_lich = chuong_ma( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-lich.php' );
t( 'đổi lịch đi qua chuông',
	false !== strpos( $src_lich, "VHNB_Bao::gui(" ) );
teq( '🔴 đổi lịch chỉ còn MỘT lời gọi VHCC_Push::gui — nhánh dự phòng khi chưa cài Nội bộ', 1,
	substr_count( $src_lich, 'VHCC_Push::gui(' ) );
t( 'nhánh dự phòng nằm trong `elseif`, không chạy song song với chuông',
	preg_match( "#VHNB_Bao::gui\(.*?\} elseif \(.*?VHCC_Push::gui\(#s", $src_lich ) === 1 );

/* ================================================================= trạm: hai đầu nối */

$src_tram = chuong_ma( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
t( "có đầu nối 'chuong'",    false !== strpos( $src_tram, "'chuong' === \$viec" ) );
t( "có đầu nối 'chuongdoc'", false !== strpos( $src_tram, "'chuongdoc' === \$viec" ) );
t( 'lượt `toi` gửi kèm số chuông (khỏi một lượt gọi thứ hai lúc vừa vào)',
	false !== strpos( $src_tram, "\$tt['chuongDem']" ) );

/* 🔴 Đầu nối KHÔNG được nhận mã NV từ thân yêu cầu. Cắt riêng hai khối rồi soi. */
preg_match( "#'chuong' === \\\$viec.*?\n\t\t\}#s", $src_tram, $m_e1 );
preg_match( "#'chuongdoc' === \\\$viec.*?\n\t\t\}#s", $src_tram, $m_e2 );
t( 'tách được hai khối đầu nối', ! empty( $m_e1 ) && ! empty( $m_e2 ) );
t( "🔴 đầu nối 'chuong' lấy người từ thẻ phiên \$u, không từ thân",
	! empty( $m_e1 ) && false !== strpos( $m_e1[0], '( $u )' ) && false === strpos( $m_e1[0], "ma_nv" ) );
t( "🔴 đầu nối 'chuongdoc' cũng vậy — thân chỉ mang `id`",
	! empty( $m_e2 ) && false === strpos( $m_e2[0], "ma_nv" ) );

/* ================================================================= trạm: giao diện */

$tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
t( 'có nút chuông',        false !== strpos( $tram, 'id="btChuong"' ) );
t( 'có chấm đỏ đếm tin',   false !== strpos( $tram, 'id="demChuong"' ) );
t( 'có màn danh sách tin', false !== strpos( $tram, 'id="mChuong"' ) );
t( 'có nút Đọc hết',       false !== strpos( $tram, 'id="btDocHet"' ) );

/* 🔴 Chuông phải nằm DƯỚI `.mn` (9) và dưới thanh tab (8): đang chụp ảnh chấm công mà thấy
   chuông nổi trên màn tối, bấm trúng là thoát giữa chừng. */
preg_match( '#\#oChuong\{[^}]*z-index:(\d+)#', $tram, $m_z );
t( 'chuông có khai z-index', ! empty( $m_z ), $m_z );
t( '🔴 z-index chuông THẤP HƠN màn phủ (.mn = 9) và thanh tab (8)',
	! empty( $m_z ) && (int) $m_z[1] < 8, isset( $m_z[1] ) ? $m_z[1] : '' );

t( 'chuông tự giấu khi chưa cài Nội bộ (veChuong đọc chuongCo)',
	false !== strpos( $tram, "hien('oChuong', !!co)" ) );
t( 'đăng xuất thì giấu chuông — máy quầy dùng chung',
	preg_match( "#hien\('mChuong',false\); hien\('oChuong',false\);#", $tram ) === 1 );
t( '🔴 đường dẫn của Nội bộ mở ở TAB MỚI, không đá cả trạm đi giữa lượt chấm',
	false !== strpos( $tram, "window.open(di, '_blank', 'noopener')" ) );
t( 'giờ đọc bằng cắt chuỗi, KHÔNG new Date(chuỗi) — Safari trả Invalid Date',
	false !== strpos( $tram, 'function gioNgan' )
	&& false === strpos( $tram, 'new Date(t.luc' ) );

/* ================================================================= không đẻ hộp thư thứ hai */

$src_ch = chuong_ma( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-chuong.php' );
t( '🔴 VHCC_Chuong KHÔNG dựng bảng riêng — một hộp thư, hai chỗ mở ra xem',
	false === strpos( $src_ch, 'VHCC_DB::t(' ) && false === strpos( $src_ch, 'CREATE TABLE' ) );
t( '🔴 và KHÔNG có câu INSERT/UPDATE/DELETE nào — đây là cửa sổ, không phải kho',
	! preg_match( '#\b(INSERT|UPDATE|DELETE)\b#i', $src_ch ) );

/* ================================================================= kết */

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử\n";
