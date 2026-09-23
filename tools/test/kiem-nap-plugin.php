<?php
/**
 * KIỂM TRANG NẠP PLUGIN TỪ ĐIỆN THOẠI.
 *
 * =============================================================================================
 * 🔴 ĐÂY LÀ CỬA CHẠY MÃ PHP TRÊN MÁY CHỦ — BÀI KIỂM NÀY GÁC ĐÚNG CÁI ĐÓ
 * =============================================================================================
 * Mọi bài kiểm khác trong repo canh chuyện SỐ SAI. Bài này canh chuyện MẤT MÁY CHỦ. Một chốt ở
 * đây tuột thì không ra một con số lệch — nó ra một tệp PHP lạ nằm trong `wp-content/plugins`,
 * và không sổ nào báo.
 *
 * Ba cửa phải qua cả ba, và bài này thử phá từng cửa một:
 *   ① phiên trạm  ② vai ADMIN trong app  ③ gõ lại PIN CỦA CHÍNH MÌNH (anh Thắng chọn 23/09/2026)
 *
 * Cộng phép soi ruột tệp .zip: đường dẫn vượt cấp · nhiều thư mục gốc · không phải họ `vhcp-` ·
 * thiếu tệp chính · tệp chính không phải plugin.
 *
 * Chạy: php tools/test/kiem-nap-plugin.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );

/* ── mấy hàm WordPress mà khung thử chưa có ───────────────────────────────────────────── */
if ( ! function_exists( 'hash_equals' ) ) {
	function hash_equals( $a, $b ) { return (string) $a === (string) $b; }
}
if ( ! function_exists( 'is_plugin_active' ) ) { function is_plugin_active( $d ) { return true; } }

vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$DAT = 0; $TRUOT = array();
function t( $ten, $dung, $them = null ) {
	global $DAT, $TRUOT;
	if ( $dung ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

$TMP = sys_get_temp_dir() . '/vhcc-nap-' . getmypid();
@mkdir( $TMP, 0777, true );

/** Dựng một tệp .zip từ mảng `đường dẫn trong zip => nội dung`. */
function zip_thu( $ten, $muc ) {
	global $TMP;
	$d = $TMP . '/' . $ten;
	@unlink( $d );
	$z = new ZipArchive();
	$z->open( $d, ZipArchive::CREATE );
	foreach ( $muc as $k => $v ) { $z->addFromString( $k, $v ); }
	$z->close();
	return $d;
}
/** Thân một tệp plugin hợp lệ. */
function than_plugin( $ten ) {
	return "<?php\n/**\n * Plugin Name: " . $ten . "\n * Version: 9.9.9\n */\n";
}

/* Ba người: chưa đăng nhập · nhân viên · admin. */
$NV    = array( 'ma_nv' => 'NV01', 'role' => 'Nhân viên' );
$QL    = array( 'ma_nv' => 'QL01', 'role' => 'Quản lý' );
$ADMIN = array( 'ma_nv' => 'AD01', 'role' => 'Admin' );

/* PIN của từng người — cùng nguồn với cửa trạm (`VHCC_Tram::tim_pin`, kho `phan_quyen`). */
global $wpdb;
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '246810', 'ho_ten' => 'Admin A',
	'vai_tro' => 'Admin', 'ma_cc_online' => 'AD01', 'coso_cc_online' => 'CS1' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '111222', 'ho_ten' => 'NV B',
	'vai_tro' => 'Nhân viên', 'ma_cc_online' => 'NV01', 'coso_cc_online' => 'CS1' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '333444', 'ho_ten' => 'Admin C',
	'vai_tro' => 'Admin', 'ma_cc_online' => 'AD02', 'coso_cc_online' => 'CS1' ) );

/* ═══════════════════════════════════════════ ⓪ ĐỌC DANH SÁCH PLUGIN ĐANG CÀI ═══════════ */
/* 🔴 Đọc THẲNG thư mục, KHÔNG qua `get_plugins()` — hàm ấy chỉ có ở wp-admin và trang này là
   trang thường, gọi vào là trắng cả trang. `kiem-goi-cheo.php` cấm hẳn cả họ hàm ấy. */
$PLUG = $TMP . '/plugins';
@mkdir( $PLUG . '/vhcp-mot', 0777, true );
@mkdir( $PLUG . '/vhcp-hai', 0777, true );
@mkdir( $PLUG . '/vhcp-hong', 0777, true );
@mkdir( $PLUG . '/akismet', 0777, true );
file_put_contents( $PLUG . '/vhcp-mot/vhcp-mot.php', "<?php\n/**\n * Plugin Name: Plugin Mot\n * Version: 1.2.3\n */\n" );
file_put_contents( $PLUG . '/vhcp-hai/vhcp-hai.php', "<?php\n/**\n * Plugin Name: Plugin Hai\n * Version: 9.0.0\n */\n" );
file_put_contents( $PLUG . '/akismet/akismet.php', "<?php\n/**\n * Plugin Name: Akismet\n * Version: 5.0\n */\n" );
define( 'WP_PLUGIN_DIR', $PLUG );
update_option( 'active_plugins', array( 'vhcp-mot/vhcp-mot.php' ) );

$ds  = VHCC_NapPlugin::ds();
$ten = array();
foreach ( $ds as $x ) { $ten[ $x['slug'] ] = $x; }
t( 'Chi liet ke plugin ho vhcp-', 2 === count( $ds ), array_keys( $ten ) );
t( 'Bo qua plugin cua nguoi khac', ! isset( $ten['akismet'] ), array_keys( $ten ) );
t( '🔴 Thu muc dung ho nhung thieu tep chinh thi bo qua',
	! isset( $ten['vhcp-hong'] ), array_keys( $ten ) );
t( 'Doc dung ten plugin', 'Plugin Mot' === $ten['vhcp-mot']['ten'], $ten['vhcp-mot'] );
t( 'Doc dung so ban', '1.2.3' === $ten['vhcp-mot']['ban'], $ten['vhcp-mot'] );
t( 'Biet plugin nao dang bat', ! empty( $ten['vhcp-mot']['bat'] ), $ten['vhcp-mot'] );
t( 'va plugin nao dang tat', empty( $ten['vhcp-hai']['bat'] ), $ten['vhcp-hai'] );
t( 'Xep theo ten', 'Plugin Hai' === $ds[0]['ten'], $ds );
t( 'da_cai() thay plugin da co', null !== VHCC_NapPlugin::da_cai( 'vhcp-mot' ) );
t( 'da_cai() tra null voi plugin chua co', null === VHCC_NapPlugin::da_cai( 'vhcp-chua-co' ) );

/* ═══════════════════════════════════════════════════ ① CỬA VAI: AI THẤY MÀN NÀY ════════ */
t( 'Chưa đăng nhập thì chối', empty( VHCC_NapPlugin::kiem_vai( null )['ok'] ) );
t( 'Mảng rỗng cũng chối', empty( VHCC_NapPlugin::kiem_vai( array() )['ok'] ) );
t( 'Có phiên nhưng THIẾU mã NV thì chối',
	empty( VHCC_NapPlugin::kiem_vai( array( 'ma_nv' => '', 'role' => 'Admin' ) )['ok'] ) );
t( '🔴 Nhân viên thì chối', empty( VHCC_NapPlugin::kiem_vai( $NV )['ok'] ) );
t( '🔴 Quản lý cũng chối — nạp plugin KHÔNG nới xuống dưới Admin',
	empty( VHCC_NapPlugin::kiem_vai( $QL )['ok'] ), VHCC_NapPlugin::kiem_vai( $QL ) );
t( 'Admin thì cho vào', ! empty( VHCC_NapPlugin::kiem_vai( $ADMIN )['ok'] ),
	VHCC_NapPlugin::kiem_vai( $ADMIN ) );
t( 'Câu chối nói RÕ đang là vai gì, để biết đi xin ai',
	false !== strpos( VHCC_NapPlugin::kiem_vai( $NV )['error'], 'Nạp plugin' ),
	VHCC_NapPlugin::kiem_vai( $NV ) );

/* ═══════════════════════════════════════════ ①b Ô "NẠP PLUGIN" TRONG LƯỚI ỨNG DỤNG ═══════ */
/* Anh Thắng 23/09/2026: *"anh chưa thấy chỗ nạp trong app với tài khoản admin"* — trang có
   nhưng không có ô là chưa "gắn vào app". Và "mỗi admin thấy thôi" là nghĩa đen: người khác
   KHÔNG thấy ô, kể cả dạng khoá — đây là cửa chạy mã trên máy chủ, không quảng cáo. */
function o_nap( $u ) {
	foreach ( VHCC_Ung::ds( array_merge( array( 'name' => 'x', 'coso' => 'CS1' ), $u ) ) as $x ) {
		if ( 'Nạp plugin' === $x['ten'] ) { return $x; }
	}
	return null;
}
$o = o_nap( $ADMIN );
t( 'Admin thấy ô "Nạp plugin" trong lưới', null !== $o, $o );
/* `url()` trả `/nap-plugin/` khi site có permalink, còn không thì `?vhcc_nap=1` — khung thử
   không có permalink nên phải nhận cả hai, y như trạm và trang quản trị. */
t( 'và ô ấy MỞ được, trỏ đúng trang nạp', $o && ! empty( $o['mo_duoc'] ) && ! empty( $o['url'] )
	&& ( false !== strpos( $o['url'], '/' . VHCC_TrangNap::slug() . '/' )
		|| false !== strpos( $o['url'], 'vhcc_nap=1' ) ), $o );
t( 'Ô có nhóm hợp lệ (bài kiểm lưới đòi)', $o && in_array( $o['nhom'], VHCC_Ung::NHOM, true ), $o );
t( '🔴 Nhân viên KHÔNG thấy ô — kể cả dạng khoá', null === o_nap( $NV ), o_nap( $NV ) );
t( '🔴 Quản lý cũng KHÔNG thấy', null === o_nap( $QL ), o_nap( $QL ) );

/* ═══════════════════════════════════════════════════════ ② SOI RUỘT TỆP .ZIP ═══════════ */
$tot = zip_thu( 'tot.zip', array(
	'vhcp-thu/vhcp-thu.php' => than_plugin( 'Thử Nghiệm' ),
	'vhcp-thu/includes/a.php' => "<?php\n",
) );
$r = VHCC_NapPlugin::kiem_tep( $tot, 'tot.zip' );
t( 'Tệp đúng chuẩn thì nhận', ! empty( $r['ok'] ), $r );
t( 'và đọc ra đúng slug', 'vhcp-thu' === $r['slug'], $r );
t( 'và đọc ra đúng tên plugin', 'Thử Nghiệm' === $r['tenPlugin'], $r );

/* 🔴 Tên tệp KHÔNG quyết định gì — đổi tên thành tên plugin thật vẫn phải soi ruột. */
$gia = zip_thu( 'vhcp-cham-cong.zip', array(
	'doc-hai/doc-hai.php' => "<?php\n/** Plugin Name: Độc Hại */\n",
) );
$r = VHCC_NapPlugin::kiem_tep( $gia, 'vhcp-cham-cong.zip' );
t( '🔴 Đặt tên tệp y hệt plugin thật nhưng ruột khác thì CHỐI', empty( $r['ok'] ), $r );
t( 'và nói rõ thư mục gốc sai', false !== strpos( $r['error'], 'doc-hai' ), $r );

$vuot = zip_thu( 'vuot.zip', array(
	'vhcp-thu/vhcp-thu.php' => than_plugin( 'X' ),
	'vhcp-thu/../../wp-config.php' => "<?php\n",
) );
$r = VHCC_NapPlugin::kiem_tep( $vuot, 'vuot.zip' );
t( '🔴 Mục vượt cấp (..) thì CHỐI', empty( $r['ok'] )
	&& false !== strpos( $r['error'], 'đường dẫn không hợp lệ' ), $r );

/* 🔴 CHỐT THEO ĐÚNG CÂU BÁO, KHÔNG CHỈ "CÓ BỊ CHỐI KHÔNG".
   Bỏ hẳn phép chặn đường dẫn tuyệt đối thì tệp vẫn bị chối — nhưng chối bằng câu "không đọc ra
   thư mục gốc", tức chối NHẦM CỬA. Một ngày nào đó cái cửa kia đổi, và phép chặn đã mục từ lâu
   mà không ai biết. Nên phải hỏi: chối Ở ĐÂU. */
$tuyet = zip_thu( 'tuyet.zip', array( '/etc/passwd' => 'x' ) );
$r = VHCC_NapPlugin::kiem_tep( $tuyet, 'tuyet.zip' );
t( '🔴 Đường dẫn tuyệt đối thì CHỐI', empty( $r['ok'] ), $r );
t( '🔴 và chối ĐÚNG ở phép chặn đường dẫn',
	false !== strpos( $r['error'], 'đường dẫn không hợp lệ' ), $r );

$nguoc = zip_thu( 'nguoc.zip', array(
	'vhcp-thu/vhcp-thu.php' => than_plugin( 'X' ),
	'vhcp-thu\\..\\evil.php' => 'x',
) );
$r = VHCC_NapPlugin::kiem_tep( $nguoc, 'nguoc.zip' );
t( '🔴 Gạch ngược kiểu Windows cũng CHỐI', empty( $r['ok'] )
	&& false !== strpos( $r['error'], 'đường dẫn không hợp lệ' ), $r );

$hai = zip_thu( 'hai.zip', array(
	'vhcp-thu/vhcp-thu.php' => than_plugin( 'X' ),
	'vhcp-khac/vhcp-khac.php' => than_plugin( 'Y' ),
) );
$r = VHCC_NapPlugin::kiem_tep( $hai, 'hai.zip' );
t( '🔴 Hai thư mục gốc thì CHỐI — một tệp một plugin', empty( $r['ok'] ), $r );

$la = zip_thu( 'la.zip', array( 'akismet/akismet.php' => than_plugin( 'Akismet' ) ) );
$r = VHCC_NapPlugin::kiem_tep( $la, 'la.zip' );
t( '🔴 Không thuộc họ vhcp- thì CHỐI', empty( $r['ok'] ), $r );

$thieu = zip_thu( 'thieu.zip', array( 'vhcp-thu/khac.php' => than_plugin( 'X' ) ) );
$r = VHCC_NapPlugin::kiem_tep( $thieu, 'thieu.zip' );
t( '🔴 Thiếu tệp chính cùng tên thì CHỐI', empty( $r['ok'] )
	&& false !== strpos( $r['error'], 'Thiếu tệp chính' ), $r );

$khong = zip_thu( 'khong.zip', array( 'vhcp-thu/vhcp-thu.php' => "<?php\n echo 'chao'; \n" ) );
$r = VHCC_NapPlugin::kiem_tep( $khong, 'khong.zip' );
t( '🔴 Tệp chính KHÔNG có dòng "Plugin Name:" thì CHỐI', empty( $r['ok'] ), $r );

$rong = zip_thu( 'rong.zip', array() );
$r = VHCC_NapPlugin::kiem_tep( $rong, 'rong.zip' );
t( 'Zip rỗng thì chối', empty( $r['ok'] ), $r );

$r = VHCC_NapPlugin::kiem_tep( $tot, 'tot.txt' );
t( 'Đuôi tệp không phải .zip thì chối', empty( $r['ok'] ), $r );

$r = VHCC_NapPlugin::kiem_tep( $TMP . '/khong-co-that.zip', 'x.zip' );
t( 'Tệp không đọc được thì chối', empty( $r['ok'] ), $r );

$khong_zip = $TMP . '/gia.zip';
file_put_contents( $khong_zip, str_repeat( 'x', 500 ) );
$r = VHCC_NapPlugin::kiem_tep( $khong_zip, 'gia.zip' );
t( 'Tệp không phải zip thật thì chối', empty( $r['ok'] ), $r );

/* ═══════════════════════════════════════════════ ③ CỬA PIN: PHẢI LÀ PIN CỦA CHÍNH MÌNH ═══ */
$GLOBALS['VHCP_TR'] = array();
$r = VHCC_NapPlugin::kiem_pin( $ADMIN, '246810' );
t( 'PIN của chính mình thì qua', ! empty( $r['ok'] ), $r );

$r = VHCC_NapPlugin::kiem_pin( $ADMIN, '333444' );
t( '🔴 PIN của một ADMIN KHÁC thì CHỐI — phải là người đang đứng trong phiên', empty( $r['ok'] ), $r );
$r2 = VHCC_NapPlugin::kiem_pin( $ADMIN, '999999' );
t( 'PIN sai hẳn thì chối', empty( $r2['ok'] ), $r2 );
t( '🔴 PIN của người khác và PIN sai hẳn trả CÙNG một câu — không biến ô nhập thành máy dò PIN',
	$r['error'] === $r2['error'], array( $r['error'], $r2['error'] ) );
t( 'PIN của nhân viên khác cũng chối', empty( VHCC_NapPlugin::kiem_pin( $ADMIN, '111222' )['ok'] ) );

$GLOBALS['VHCP_TR'] = array();
for ( $i = 0; $i < VHCC_NapPlugin::SAI_TOI_DA; $i++ ) { VHCC_NapPlugin::kiem_pin( $ADMIN, '10000' . $i ); }
$r = VHCC_NapPlugin::kiem_pin( $ADMIN, '246810' );
t( '🔴 Gõ sai quá mức thì KHOÁ, kể cả khi sau đó gõ ĐÚNG', empty( $r['ok'] ), $r );
t( 'và nói rõ là đang bị khoá', false !== strpos( $r['error'], 'thử lại sau' ), $r );

$GLOBALS['VHCP_TR'] = array();
VHCC_NapPlugin::kiem_pin( $ADMIN, '' );
VHCC_NapPlugin::kiem_pin( $ADMIN, '   ' );
t( '⚠️ Bỏ trống KHÔNG tính là một lượt dò', 0 === (int) VHCC_NapPlugin::con_duoc_thu()['daSai'] );
VHCC_NapPlugin::kiem_pin( $ADMIN, '246810' );
t( '⚠️ Lượt ĐÚNG cũng không tính — không thì người làm thật tự khoá mình',
	0 === (int) VHCC_NapPlugin::con_duoc_thu()['daSai'] );

$GLOBALS['VHCP_TR'] = array();
/* `pin_sach()` lọc hết chữ: 'abc' thành rỗng — là gõ nhầm bàn phím, KHÔNG phải một lượt dò,
   nên không đếm (cùng luật với bỏ trống). Còn '12' là số thật nhưng quá ngắn: đó là một lượt dò. */
$r = VHCC_NapPlugin::kiem_pin( $ADMIN, 'abc' );
t( 'PIN toàn chữ thì chối như bỏ trống, KHÔNG đếm', empty( $r['ok'] )
	&& 0 === (int) VHCC_NapPlugin::con_duoc_thu()['daSai'], $r );
$r = VHCC_NapPlugin::kiem_pin( $ADMIN, '12' );
t( 'PIN quá ngắn thì chối và TÍNH một lượt', empty( $r['ok'] )
	&& 1 === (int) VHCC_NapPlugin::con_duoc_thu()['daSai'], $r );
t( 'Phiên không có Mã NV thì chối',
	empty( VHCC_NapPlugin::kiem_pin( array( 'ma_nv' => '', 'role' => 'Admin' ), '246810' )['ok'] ) );

/* ══════════════════════════════════════════ ④ GHÉP BA CỬA: THỨ TỰ PHẢI ĐÚNG ════════════ */
$GLOBALS['VHCP_TR'] = array();
$tep_ok = array( 'tmp_name' => $tot, 'name' => 'vhcp-thu.zip', 'error' => UPLOAD_ERR_OK );

$r = VHCC_NapPlugin::nap( $NV, '111222', $tep_ok );
t( '🔴 Nhân viên gõ ĐÚNG PIN của mình vẫn bị chối — cửa vai đứng trước', empty( $r['ok'] ), $r );
/* 🔴 Và chối Ở ĐÚNG CỬA VAI. Bỏ phép kiểm vai đi thì lượt này vẫn hỏng — nhưng hỏng mãi tận
   bước gọi bộ cài của WordPress, tức mã đã đi qua cả phép kiểm mật khẩu. Trên máy thật, chỗ ấy
   KHÔNG hỏng: bộ cài có đủ, và plugin được cài bởi một nhân viên. */
t( '🔴 và chối ĐÚNG ở cửa vai, không phải rơi xuống tận bước cài',
	false !== strpos( $r['error'], 'Nạp plugin' ), $r );
t( 'và KHÔNG tốn lượt thử PIN nào', 0 === (int) VHCC_NapPlugin::con_duoc_thu()['daSai'] );

$r = VHCC_NapPlugin::nap( $ADMIN, '333444', $tep_ok );
t( '🔴 Admin app gõ PIN của admin KHÁC thì vẫn chối ở cửa PIN', empty( $r['ok'] )
	&& 'PIN không đúng.' === $r['error'], $r );

$r = VHCC_NapPlugin::nap( $ADMIN, '246810', array( 'tmp_name' => '' ) );
t( 'Chưa chọn tệp thì báo rõ', empty( $r['ok'] )
	&& false !== strpos( $r['error'], 'Chưa chọn tệp' ), $r );

$r = VHCC_NapPlugin::nap( $ADMIN, '246810',
	array( 'tmp_name' => $tot, 'name' => 'x.zip', 'error' => UPLOAD_ERR_INI_SIZE ) );
t( 'Tải lên hỏng giữa chừng thì báo rõ, KHÔNG cài', empty( $r['ok'] )
	&& false !== strpos( $r['error'], 'Tải tệp lên không xong' ), $r );

$GLOBALS['VHCP_TR'] = array();
$r = VHCC_NapPlugin::nap( $ADMIN, '246810',
	array( 'tmp_name' => $gia, 'name' => 'vhcp-cham-cong.zip', 'error' => UPLOAD_ERR_OK ) );
t( '🔴 Admin + PIN đúng, nhưng tệp sai ruột thì vẫn KHÔNG cài', empty( $r['ok'] )
	&& false !== strpos( $r['error'], 'chỉ nhận plugin họ' ), $r );

/* ⑤ dọn */
foreach ( (array) glob( $TMP . '/plugins/*/*' ) as $f ) { @unlink( $f ); }
foreach ( (array) glob( $TMP . '/plugins/*', GLOB_ONLYDIR ) as $d ) { @rmdir( $d ); }
@rmdir( $TMP . '/plugins' );
foreach ( (array) glob( $TMP . '/*' ) as $f ) { @unlink( $f ); }
@rmdir( $TMP );

/* ------------------------------------------------------------------ in kết quả */
echo "\n";
if ( $TRUOT ) {
	echo 'ĐỎ — ' . count( $TRUOT ) . " phép trượt:\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "\nĐạt {$DAT} · trượt " . count( $TRUOT ) . "\n";
	exit( 1 );
}
echo "XANH — {$DAT} phép đều đạt.\n";
exit( 0 );
