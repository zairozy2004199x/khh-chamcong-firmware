<?php
/**
 * KIỂM CỔNG CỦA JP CAPSULE — BẢN ĐẾM CỦA CẢ CUỘC CHUYỂN.
 *
 * =============================================================================================
 * 🔴 BÀI NÀY ĐỌC THẲNG GIAO DIỆN GỐC, KHÔNG ĐỌC MỘT DANH SÁCH AI ĐÓ GÕ TAY
 * =============================================================================================
 * `VHJP_Cong` giữ hai bảng: `map()` (đã chuyển) và `chua_lam()` (chưa chuyển). Hai bảng ấy chỉ
 * có nghĩa nếu chúng cộng lại ĐÚNG BẰNG danh sách hàm mà giao diện thật sự gọi. Bài này bóc
 * danh sách ấy ra từ chính 11 tệp `giao-dien/*.html` rồi đòi ba điều:
 *
 *   · giao diện gọi một tên mà hai bảng đều không biết  -> ĐỎ (cổng trả "Lệnh không hợp lệ",
 *     một câu nói SAI: tính năng có thật, chỉ là chưa ai làm);
 *   · chuyển xong mà quên xoá khỏi `chua_lam()`          -> ĐỎ;
 *   · một tên nằm ở CẢ HAI bảng                          -> ĐỎ (bảng đếm mất nghĩa ngay lúc ấy).
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 BÓC THEO CHUỖI `'jpXxx'`, KHÔNG BÓC THEO `srv('jpXxx'`
 * ---------------------------------------------------------------------------------------------
 * Đây không phải chuyện cẩn thận thừa. Màn "Bảng kê chứng từ" gọi máy chủ qua một BIẾN:
 *
 *     var fn = kind === 'NHAP' ? 'jpKhoBangKeNhap' : 'jpKhoBangKeXuat';
 *     ksrv(fn, KT.token, ...)
 *
 * Bóc theo `srv(` thì hai tên ấy vô hình — và đúng hai tên ấy đã lọt ra ngoài CẢ HAI bảng, nên
 * cổng trả "Lệnh không hợp lệ" cho một màn có thật. Bóc theo chuỗi thì thấy hết, và trên 11 tệp
 * giao diện hiện tại cách bóc này KHÔNG bắt nhầm cái nào: 102 tên, đúng bằng 100 tên gọi trực
 * tiếp cộng 2 tên gọi qua biến.
 *
 * Chạy: php tools/test/kiem-jp-cong.php
 */

require_once __DIR__ . '/wp-stub.php';

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) { function wp_mkdir_p( $d ) { return true; } }
if ( ! function_exists( 'add_rewrite_rule' ) ) { function add_rewrite_rule() {} }
if ( ! function_exists( 'flush_rewrite_rules' ) ) { function flush_rewrite_rules( $x = true ) {} }
if ( ! function_exists( 'nocache_headers' ) ) { function nocache_headers() {} }
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route() { return true; } }
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $n = 12, $a = true, $b = false ) { return str_repeat( 'x', $n ); }
}

$DAT = 0; $TRUOT = array();
function t( $ten, $dung, $them = null ) {
	global $DAT, $TRUOT;
	if ( $dung ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

$GOC = dirname( __DIR__, 2 ) . '/wordpress/vhcp-jp';
vhjp_test_boot( $GOC );

/* ---------------------------------------------------------- bóc tên hàm ra khỏi giao diện */
/* 15 tệp: 2 tệp CSS, 2 tệp khung HTML, 11 tệp JS. Con số "11 tệp giao diện" hay được nhắc tới
   là đếm riêng phần JS — ở đây quét CẢ 15, vì tên hàm cũng nằm rải trong khung HTML. */
$tep = glob( $GOC . '/giao-dien/*.html' );
t( 'Đọc được 15 tệp giao diện', 15 === count( $tep ), count( $tep ) );

$giao_dien = array();
$o_tep     = array();
foreach ( $tep as $f ) {
	$than = file_get_contents( $f );
	if ( preg_match_all( "/'(jp[A-Z][A-Za-z0-9_]*)'/", $than, $m ) ) {
		foreach ( $m[1] as $fn ) {
			$giao_dien[ $fn ] = 1;
			if ( ! isset( $o_tep[ $fn ] ) ) { $o_tep[ $fn ] = basename( $f ); }
		}
	}
}
$giao_dien = array_keys( $giao_dien );
sort( $giao_dien );
t( 'Bóc được danh sách hàm từ giao diện', count( $giao_dien ) > 80, count( $giao_dien ) );

/* Cách bóc theo `srv(` phải THIẾU đúng mấy tên gọi qua biến — nếu nó không thiếu gì nữa thì
   khối chú thích 🔴 ở đầu tệp đã lỗi thời, và phải sửa chú thích chứ không sửa phép bóc. */
$qua_srv = array();
foreach ( $tep as $f ) {
	if ( preg_match_all( "/\b[ks]?srv\(\s*'([A-Za-z0-9_]+)'/", file_get_contents( $f ), $m ) ) {
		foreach ( $m[1] as $fn ) { $qua_srv[ $fn ] = 1; }
	}
}
$chi_qua_bien = array_values( array_diff( $giao_dien, array_keys( $qua_srv ) ) );
t( 'Có hàm gọi QUA BIẾN — nên phép bóc phải theo chuỗi, không theo srv(',
	count( $chi_qua_bien ) > 0, $chi_qua_bien );
$la = array_values( array_diff( array_keys( $qua_srv ), $giao_dien ) );
t( 'Mọi tên trong srv() đều khớp mẫu jpXxx (phép bóc không sót kiểu tên nào)', ! $la, $la );

/* ---------------------------------------------------------------- ba phép đối chiếu chính */
$da   = array_keys( VHJP_Cong::map() );
$chua = VHJP_Cong::chua_lam();
sort( $da ); sort( $chua );

$ca_hai = array_values( array_intersect( $da, $chua ) );
t( '🔴 Không tên nào vừa "đã chuyển" vừa "chưa chuyển"', ! $ca_hai, $ca_hai );

$khai  = array_values( array_unique( array_merge( $da, $chua ) ) );
$thieu = array();
foreach ( array_values( array_diff( $giao_dien, $khai ) ) as $fn ) {
	$thieu[] = $fn . ' (' . $o_tep[ $fn ] . ')';
}
t( '🔴 Giao diện KHÔNG gọi tên nào mà cổng không biết — cổng phải trả "chưa chuyển", '
	. 'không phải "Lệnh không hợp lệ"', ! $thieu, $thieu );

/* Chiều ngược lại: cổng khai một tên mà giao diện không gọi. `jpLoginPin` và mấy hàm phiên có
   thể nằm ngoài (giao diện gọi qua đường khác), nên chỉ soi phần `chua_lam()` — khai một việc
   không ai cần làm thì bảng đếm phóng đại số việc còn lại. */
$thua = array_values( array_diff( $chua, $giao_dien ) );
t( 'chua_lam() không khai tên nào giao diện chẳng gọi', ! $thua, $thua );

/* ------------------------------------------------------------------ mọi hàm đều gọi được */
$hong = array();
foreach ( VHJP_Cong::map() as $fn => $ham ) {
	if ( ! is_callable( $ham ) ) { $hong[] = $fn; }
}
t( '🔴 Mọi hàm trong map() đều gọi được (sai tên lớp/hàm = lỗi 500 lúc người ta bấm)',
	! $hong, $hong );

/* ---------------------------------------------------------------------- cửa không cần thẻ */
/**
 * 🔴 DANH SÁCH NÀY LÀ DANH SÁCH CỬA KHÔNG CÓ THẺ JP. Mỗi tên phải tự mang một cửa khác:
 *   · `jpLoginPin`    — cửa là chính PIN người ta gõ.
 *   · `jpSsoChamCong` — cửa là COOKIE PHIÊN CHẤM CÔNG, đọc ở máy chủ, và hàm không nhận tham
 *     số nào nên không có gì để giả.
 * Khoá cứng bằng phép so mảng CHẶT chứ không đếm: đếm thì đổi `jpLoginPin` thành một tên khác
 * vẫn xanh. Thêm tên thứ ba mà không trả lời được "cửa của nó là gì" thì bài này phải ĐỎ.
 */
t( 'Đúng HAI hàm gọi được không cần thẻ phiên JP, và đúng hai tên ấy',
	array( 'jpLoginPin', 'jpSsoChamCong' ) === VHJP_Cong::cong_khai(), VHJP_Cong::cong_khai() );
$mo_pin = VHJP_Cong::cho_pin_mac_dinh();
sort( $mo_pin );
t( 'Đúng BA hàm sống khi tài khoản còn PIN mặc định',
	array( 'jpBootstrap', 'jpDoiPin', 'jpLogout' ) === $mo_pin, $mo_pin );

/**
 * Mọi tên trong bốn bảng gác quyền phải là tên CÓ THẬT — tức nằm trong `map()` hoặc trong
 * `chua_lam()`. Gác một tên viết sai chính tả là gác vào không khí, và không có triệu chứng nào
 * nhìn thấy được cho tới hôm ai đó gọi đúng tên thật.
 *
 * ⚠️ GÁC TRƯỚC MỘT HÀM CHƯA CHUYỂN LÀ ĐÚNG, KHÔNG PHẢI THỪA. `jpCfgListUsers` trả danh sách tài
 *    khoản; khai sẵn nó vào `chi_ke_toan()` nghĩa là ngày ai đó viết hàm ấy thì cửa đã khoá sẵn,
 *    không phải nhớ khoá. Đòi "phải có trong map()" là ép người ta gỡ mấy dòng ấy ra — và đúng
 *    hôm hàm ra đời thì cửa mở toang.
 */
foreach ( array( 'cong_khai', 'cho_pin_mac_dinh', 'chi_ke_toan', 'chi_nhan_vien' ) as $bang ) {
	$ma = array();
	foreach ( call_user_func( array( 'VHJP_Cong', $bang ) ) as $fn ) {
		if ( ! in_array( $fn, $khai, true ) ) { $ma[] = $fn; }
	}
	t( '🔴 ' . $bang . '() không gác tên nào cổng chẳng biết (gõ sai = gác vào không khí)',
		! $ma, $ma );
}
/* Hai bảng công khai thì KHÁC: chúng mở cửa, không khoá cửa. Mở sẵn một tên chưa tồn tại là
   ngày hàm ấy ra đời nó sống ngoài mọi phép kiểm thẻ mà không ai cố ý. */
foreach ( array( 'cong_khai', 'cho_pin_mac_dinh' ) as $bang ) {
	$ma = array();
	foreach ( call_user_func( array( 'VHJP_Cong', $bang ) ) as $fn ) {
		if ( ! in_array( $fn, $da, true ) ) { $ma[] = $fn; }
	}
	t( '🔴 ' . $bang . '() chỉ mở cửa cho hàm ĐÃ có thật', ! $ma, $ma );
}

/* Sổ kho là giá vốn. Mọi hàm kho — đã chuyển hay chưa — đều phải đòi vai kế toán. */
$gac = VHJP_Cong::chi_ke_toan();
$ho  = array();
foreach ( $khai as $fn ) {
	if ( 0 === strpos( $fn, 'jpKho' ) && ! in_array( $fn, $gac, true ) ) { $ho[] = $fn; }
}
t( '🔴 Mọi hàm jpKho* đều đòi vai kế toán', ! $ho, $ho );

/* ------------------------------------------------------------------ cổng chối tên lạ */
$ra = VHJP_Cong::goi( 'jpKhongCoHamNay', array( 'the' ) );
t( 'Tên lạ hoàn toàn -> 400 "Lệnh không hợp lệ"',
	400 === $ra['ma'] && false !== strpos( $ra['than']['msg'], 'không hợp lệ' ), $ra );
if ( $chua ) {
	$ra = VHJP_Cong::goi( $chua[0], array( 'the' ) );
	t( 'Tên CHƯA CHUYỂN -> 501 và nói rõ là chưa chuyển, khác hẳn "không hợp lệ"',
		501 === $ra['ma'] && false !== strpos( $ra['than']['msg'], 'chưa chuyển' ), $ra );
}
/* Hàm có thật nhưng không kèm thẻ phiên -> 401, không phải 500. */
$ra = VHJP_Cong::goi( 'jpKhoTonKho', array( '' ) );
t( 'Hàm thật mà thiếu thẻ phiên -> 401 SESSION_EXPIRED',
	401 === $ra['ma'] && 'SESSION_EXPIRED' === $ra['than']['error'], $ra );

/* ═════════════════════════ ⑤ KHUNG THỬ CÓ NẠP ĐỦ LỚP KHÔNG ═════════════════════════
 *
 * 🔴 BÀI KIỂM BỎ SÓT MỘT LỚP THÌ NÓ IM LẶNG, KHÔNG ĐỎ.
 * `vhjp_test_boot()` bóc danh sách lớp bằng biểu thức chính quy trên chính `vhcp-jp.php`.
 * Bản cũ dùng `[a-z-]+` nên `class-vhjp-cau-hinh-2.php` KHÔNG khớp — cả lớp `VHJP_CauHinh2`
 * (15 hàm cấu hình) chưa bao giờ được nạp, mà không bài nào đỏ vì không bài nào chạm tới nó.
 * Phép dưới đây đối chiếu THẲNG: mỗi dòng `require_once` trong tệp plugin phải ra một lớp
 * đang tồn tại trong bộ nhớ.
 */
$thieu = array();
foreach ( VHJP_Cong::map() as $fn => $goi ) {
	$lop = is_array( $goi ) ? $goi[0] : '';
	if ( '' !== $lop && ! class_exists( $lop ) ) { $thieu[] = $fn . ' → ' . $lop; }
}
t( '🔴 Mọi lớp mà bảng cổng trỏ tới đều đã được nạp', ! $thieu, $thieu );

$chinh = file_get_contents( dirname( __DIR__, 2 ) . '/wordpress/vhcp-jp/vhcp-jp.php' );
preg_match_all( "#require_once VHJP_DIR \. '(includes/class-vhjp-[a-z0-9-]+\.php)';#", $chinh, $m );
$chua = array();
foreach ( $m[1] as $duong ) {
	$ten = basename( $duong, '.php' );                      // class-vhjp-cau-hinh-2
	$bo  = explode( '-', substr( $ten, strlen( 'class-vhjp-' ) ) );
	$lop = 'VHJP';
	foreach ( $bo as $x ) { $lop .= ucfirst( $x ); }         // VHJP_CauHinh2 (bỏ gạch)
	$lop = 'VHJP_' . substr( $lop, 4 );
	if ( ! class_exists( $lop ) ) { $chua[] = $duong . ' → đoán là ' . $lop; }
}
t( 'Đọc được danh sách lớp trong vhcp-jp.php', count( $m[1] ) >= 18, count( $m[1] ) );
t( '🔴 Tệp có CHỮ SỐ trong tên cũng phải được nạp (class-vhjp-cau-hinh-2.php)',
	class_exists( 'VHJP_CauHinh2' ) );

/* Và bài tự kiểm công thức tiền phải ĐẠT — nó là thứ kế toán bấm để yên tâm, nên nó đỏ là
   kế toán mất tin vào cả hệ. Trước bản này nó đỏ 3/7 vì bỏ mất bước tính từng dòng. */
$KTX = array( 'id' => 'K9', 'hoTen' => 'KT', 'role' => VHJP_Auth::VAI_KT, 'locationIds' => array() );
$tu_kiem = VHJP_CauHinh2::kiem_tra_nhanh( $KTX );
t( '🔴 jpKiemTraNhanh phải ĐẠT trên hệ lành', false !== strpos( $tu_kiem, 'KẾT QUẢ: ĐẠT' ),
	$tu_kiem );

/* ------------------------------------------------------------------ in kết quả */
echo "\n";
echo 'Giao diện gọi ' . count( $giao_dien ) . ' hàm · đã chuyển ' . count( $da )
	. ' · còn ' . count( $chua ) . "\n";
if ( $chua ) {
	echo 'Còn lại: ' . implode( ', ', array_slice( $chua, 0, 10 ) )
		. ( count( $chua ) > 10 ? ' …' : '' ) . "\n";
}
echo "\n";
if ( $TRUOT ) {
	echo 'ĐỎ — ' . count( $TRUOT ) . " phép trượt:\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "\nĐạt {$DAT} · trượt " . count( $TRUOT ) . "\n";
	exit( 1 );
}
echo "XANH — {$DAT} phép đều đạt.\n";
exit( 0 );
