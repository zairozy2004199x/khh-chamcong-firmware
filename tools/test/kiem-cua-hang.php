<?php
/**
 * KIỂM TAB CỬA HÀNG TRÊN TRẠM — cửa hàng trưởng duyệt đơn, xem công cơ sở, thêm người mới.
 *
 * =================================================================================================
 * 🔴 VÌ SAO BÀI NÀY CẦN
 * =================================================================================================
 * Anh Thắng 17/09/2026: *"đối với cửa hàng trưởng khi đăng nhập sẽ thấy thêm: Thêm nhân sự mới ·
 * Bảng công cơ sở quản lý · Đơn từ từ nhân viên — cơ sở mình quản lý"*.
 *
 * Trước bản này, MỌI cửa của trạm chỉ mở ra thứ của CHÍNH NGƯỜI ĐANG ĐĂNG NHẬP. Đây là cửa đầu
 * tiên trên trạm cho một người đọc và SỬA dữ liệu của NGƯỜI KHÁC — nên bốn câu phải trả lời được
 * bằng phép thử:
 *
 *   1. Nhân viên thường có mở được không? (tab, và cả bốn cửa phía sau tab)
 *   2. Cửa hàng trưởng có với sang CƠ SỞ KHÁC được không, kể cả khi gõ thẳng tên cơ sở ấy?
 *   3. Duyệt đơn có đi qua đúng phép gác cũ không, hay lớp mới tự chế một phép gác thứ hai?
 *   4. Thêm người mới có nới rộng hơn cửa hẹp `them_nv_cua_hang` không?
 *
 * Chạy: php tools/test/kiem-cua-hang.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

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
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

global $wpdb;

$CS_A = 'CH_SHOP_A';
$CS_B = 'CH_SHOP_B';
$TH   = current_time( 'Y-m' );
/* ⚠️ MẤY LƯỢT SỬA/XOÁ GIEO VÀO NGÀY HÔM NAY, KHÔNG PHẢI MỘT NGÀY BẤT KỲ TRONG THÁNG.
   Từ 17/09/2026 cửa hàng trưởng chỉ sửa được giờ của CHÍNH HÔM NAY (`VHCC_Bu::han_ngay`).
   Gieo vào ngày cũ thì mọi phép thử cơ chế đều đỏ vì MỘT lý do chung — và lý do ấy che mất
   đúng cái thứ chúng sinh ra để canh. Cái khoá theo ngày có mục riêng ở dưới. */
$HOM  = current_time( 'Y-m-d' );
$CU   = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) - 86400 );

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'CH001', 'ho_ten' => 'Vũ Thị Nhân', 'cua_hang' => $CS_A, 'chuc_vu' => 'Nhân viên',
	'pin_dang_nhap' => '941111', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'CH002', 'ho_ten' => 'Hồ Văn Kia', 'cua_hang' => $CS_B, 'chuc_vu' => 'Nhân viên',
	'pin_dang_nhap' => '942222', 'trang_thai_lam_viec' => 'Đang làm' ) );

$NV    = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '941111' )['token'] );
$NV_B  = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '942222' )['token'] );
t( 'hai nhân viên đăng nhập được', is_array( $NV ) && is_array( $NV_B ), array( $NV, $NV_B ) );

$CHT_A = array( 'name' => 'Trưởng A', 'role' => VHCC_Vai::CHT, 'coso' => $CS_A, 'ma_nv' => 'CHTA' );
$CHT_B = array( 'name' => 'Trưởng B', 'role' => VHCC_Vai::CHT, 'coso' => $CS_B, 'ma_nv' => 'CHTB' );
/* Người khai bảng ngoại lệ. `dat_ngoai_le` đòi quyền `ho_so` (Kế toán trở lên). */
$ADMIN = array( 'name' => 'Quản trị', 'role' => VHCC_Vai::ADMIN, 'coso' => '', 'ma_nv' => 'AD001' );

/* =============================================================== 1. AI THẤY TAB */

t( '🔴 nhân viên thường KHÔNG được vào tab Cửa hàng', ! VHCC_CuaHang::duoc( $NV ), $NV );
t( 'cửa hàng trưởng thì được', VHCC_CuaHang::duoc( $CHT_A ) );
t( 'bậc trên cửa hàng trưởng cũng được',
	VHCC_CuaHang::duoc( array( 'name' => 'Sếp', 'role' => VHCC_Vai::ADMIN, 'coso' => '' ) ) );
t( 'quyền gác tab đúng bậc Cửa hàng trưởng',
	VHCC_Vai::CHT === VHCC_Vai::QUYEN[ VHCC_CuaHang::QUYEN ] );

t( 'trưởng A thấy đúng cơ sở của mình', array( $CS_A ) === VHCC_CuaHang::ds_coso( $CHT_A ),
	VHCC_CuaHang::ds_coso( $CHT_A ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 MỌI CƠ SỞ TRONG Ô XỔ ĐỀU PHẢI BẤM ĐƯỢC — lỗi anh Thắng gặp 17/09/2026.
 *
 * Bản đầu trả thẳng `ds_coso_cua()` cho ô xổ rồi gác bằng `co_quyen_coso()`. Cùng ĐẦU VÀO,
 * nhưng `co_quyen_coso()` chồng thêm mấy lớp nữa (quyền `cong_coso`, và nhánh bó-mảng cho
 * người có `cong_tat_ca`) — nên ô xổ bày hai cơ sở mà chọn cái nào cũng ra *"Không có quyền
 * cơ sở này"*. Một danh sách bấm vào đâu cũng bị chối còn tệ hơn danh sách rỗng: người dùng
 * thấy tên cửa hàng MÌNH ở đó và kết luận hệ thống hỏng.
 *
 * Phép thử dưới không dò cách sửa — nó chốt TÍNH CHẤT: cái gì bày ra thì bấm được. Nhờ vậy
 * mai `co_quyen_coso()` mọc thêm lớp nào nữa, bài này vẫn bắt được nếu ô xổ tụt lại.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
$moi_vai = array(
	'nhân viên'       => array( 'role' => VHCC_Vai::NV,      'coso' => $CS_A . ', ' . $CS_B ),
	'cửa hàng trưởng' => array( 'role' => VHCC_Vai::CHT,     'coso' => $CS_A . ', ' . $CS_B ),
	'quản lý'         => array( 'role' => VHCC_Vai::QL,      'coso' => $CS_A ),
	'kế toán'         => array( 'role' => VHCC_Vai::KE_TOAN, 'coso' => '' ),
	'admin'           => array( 'role' => VHCC_Vai::ADMIN,   'coso' => $CS_B ),
);
foreach ( $moi_vai as $ten_v => $x ) {
	$uv = array( 'name' => $ten_v, 'role' => $x['role'], 'coso' => $x['coso'], 'ma_nv' => 'AI_DO' );
	foreach ( VHCC_CuaHang::ds_coso( $uv ) as $cs_v ) {
		t( '🔴 [' . $ten_v . '] cơ sở "' . $cs_v . '" bày ra thì phải bấm được',
			VHCC_NhanSu::co_quyen_coso( $uv, $cs_v ), $cs_v );
		/* Bấm được nghĩa là CỬA THẬT chạy, không phải chỉ `co_quyen_coso` trả true. */
		t( '   và cửa thật nhận nó', ! empty( VHCC_CuaHang::don_cho( $uv, $cs_v )['ok'] ), $cs_v );
	}
}
/* Nhân viên thường: không qua `cong_coso` nên danh sách phải RỖNG, chứ không phải bày ra rồi
   chối từng cái. Đây chính là hình dạng của lỗi cũ. */
t( '🔴 nhân viên thường thì danh sách RỖNG, không bày rồi chối',
	array() === VHCC_CuaHang::ds_coso( array( 'name' => 'nv', 'role' => VHCC_Vai::NV,
		'coso' => $CS_A . ', ' . $CS_B, 'ma_nv' => 'CH001' ) ) );

/* ⚠️ PHỤ TRÁCH ≠ CHẤM CÔNG. Anh Thắng 17/09/2026: *"Cơ sở phụ trách là cơ sở theo dõi nhân
   sự, thêm nhân sự, chứ không có chấm công trong đó, trừ nó có tên trong chọn cơ sở chấm
   công"*. Hai danh sách dựng từ hai hàm khác nhau và CỐ Ý khác nhau — lẫn chúng là hoặc cho
   người ta chấm công ở nơi họ chỉ theo dõi, hoặc giấu mất cửa hàng họ đang quản. */
$src_ch = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-cua-hang.php' );
/* ⚠️ SOI MÃ THẬT, KHÔNG SOI CHÚ THÍCH. Khối docblock ngay trên `ds_coso()` có NHẮC TÊN hàm
   `ds_coso_cham_cua_nv()` để giải thích vì sao KHÔNG dùng nó — dò chuỗi thô thì chính câu giải
   thích ấy làm bài kiểm đỏ, và người sửa sẽ đi xoá lời giải thích cho bài xanh lại. Bỏ chú
   thích ra trước rồi mới soi. */
$ma_that = '';
foreach ( token_get_all( $src_ch ) as $tk ) {
	if ( is_array( $tk ) && in_array( $tk[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
	$ma_that .= is_array( $tk ) ? $tk[1] : $tk;
}
t( 'bóc được chú thích ra khỏi mã', strlen( $ma_that ) > 1000 && strlen( $ma_that ) < strlen( $src_ch ) );
t( '⚠️ tab quản lý KHÔNG gọi danh sách cơ sở chấm công',
	false === strpos( $ma_that, 'ds_coso_cham_cua_nv' ), $ma_that );
$tpl_ch = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
t( '⚠️ và màn nói rõ đây không phải nơi chấm công',
	false !== strpos( $tpl_ch, 'không phải</b> nơi anh/chị chấm' ), $tpl_ch );

/* =============================================================== 2. ĐƠN TỪ */

/* Hai nhân viên hai cơ sở, mỗi người một đơn mỗi loại nộp được. */
$mai = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' 00:00:00 UTC' ) + 86400 );
VHCC_XinTre::nop( $NV,   array( 'ngay' => $mai, 'so_phut' => 20, 'ly_do' => 'Kẹt xe' ) );
VHCC_XinTre::nop( $NV_B, array( 'ngay' => $mai, 'so_phut' => 25, 'ly_do' => 'Đơn của B' ) );
VHCC_XinNghi::nop( $NV,  array( 'tu' => $mai, 'loai' => VHCC_XinNghi::OM, 'lyDo' => 'Sốt' ) );

$r = VHCC_CuaHang::don_cho( $CHT_A, $CS_A );
t( 'trưởng A đọc được hộp đơn', ! empty( $r['ok'] ), $r );
t( 'hộp gộp cả đơn trễ lẫn đơn nghỉ', 2 === (int) $r['so'], $r );

/* 🔴 KHÔNG LẪN ĐƠN CỦA CƠ SỞ KHÁC — soi cả chuỗi, không soi từng khoá. */
$chuoi = wp_json_encode( $r );
t( '🔴 hộp của A KHÔNG có tên người cơ sở B', false === strpos( $chuoi, 'Hồ Văn Kia' ), $r );
t( '🔴 hộp của A KHÔNG có mã người cơ sở B', false === strpos( $chuoi, 'CH002' ), $r );
foreach ( $r['don'] as $d ) {
	t( 'mọi đơn trong hộp đều của người cơ sở A', 'CH001' === (string) $d['maNV'], $d );
	t( 'đơn nào cũng nói rõ là loại gì', '' !== trim( (string) $d['tenLoai'] ), $d );
	t( 'và mang theo lý do để người duyệt quyết', isset( $d['lyDo'] ), $d );
}

/* 🔴 GÕ THẲNG TÊN CƠ SỞ KHÁC CŨNG KHÔNG RA. Tên cơ sở thì ai cũng biết — xem câu 2 đầu tệp. */
$r2 = VHCC_CuaHang::don_cho( $CHT_A, $CS_B );
t( '🔴 trưởng A gõ thẳng cơ sở B thì bị CHỐI', empty( $r2['ok'] ), $r2 );
t( 'và câu chối không mang theo dữ liệu nào của B',
	false === strpos( wp_json_encode( $r2 ), 'Hồ Văn Kia' ), $r2 );

/* Bỏ trống cơ sở thì lui về cơ sở đầu của chính mình, không lui về "tất cả". */
$r3 = VHCC_CuaHang::don_cho( $CHT_A, '' );
t( 'bỏ trống cơ sở thì lui về cơ sở của mình', ! empty( $r3['ok'] ) && $CS_A === $r3['coSo'], $r3 );

/* 🔴 NHÂN VIÊN THƯỜNG KHÔNG ĐỌC ĐƯỢC HỘP ĐƠN, kể cả cơ sở của chính họ. */
$r4 = VHCC_CuaHang::don_cho( $NV, $CS_A );
t( '🔴 nhân viên thường KHÔNG mở được hộp đơn của cơ sở mình', empty( $r4['ok'] ), $r4 );

/* =============================================================== 3. DUYỆT */

$don = $r['don'][0];
$r5  = VHCC_CuaHang::duyet( $NV, $don['loai'], $don['id'], true );
t( '🔴 nhân viên thường KHÔNG duyệt được đơn', empty( $r5['ok'] ), $r5 );

/* 🔴 CHỐT CƠ SỞ ĐỌC TỪ CHÍNH ĐƠN. Trưởng B có quyền cửa hàng trưởng, và id đơn thì gõ tay được. */
$r6 = VHCC_CuaHang::duyet( $CHT_B, $don['loai'], $don['id'], true );
t( '🔴 trưởng B KHÔNG duyệt được đơn của cơ sở A', empty( $r6['ok'] ), $r6 );

$r7 = VHCC_CuaHang::duyet( $CHT_A, $don['loai'], $don['id'], true );
t( 'trưởng A duyệt được đơn của cơ sở mình', ! empty( $r7['ok'] ), $r7 );

$r8 = VHCC_CuaHang::don_cho( $CHT_A, $CS_A );
t( 'đơn đã quyết thì rời khỏi hộp chờ', 1 === (int) $r8['so'], $r8 );

$r9 = VHCC_CuaHang::duyet( $CHT_A, 'loai_la', 1, true );
t( '🔴 loại đơn lạ bị chối — danh sách trắng, không phải ô chữ tự do', empty( $r9['ok'] ), $r9 );

/* 🔴 KHÔNG TỰ CHẾ PHÉP GÁC. Canh bằng MÃ NGUỒN: lớp này phải GỌI LẠI hàm nghiệp vụ cũ, vì mỗi
   hàm ấy tự chốt cơ sở từ chính bản ghi. Dựng phép gác riêng ở đây là hai bộ luật quyền. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-cua-hang.php' );
foreach ( array( 'VHCC_XinTre::duyet', 'VHCC_XinNghi::duyet', 'VHCC_Lich::duyet',
	'VHCC_Cham::bang_cham_cong', 'VHCC_NhanSu::them_nv_cua_hang' ) as $ham ) {
	t( '🔴 gọi lại hàm nghiệp vụ đã có: ' . $ham, false !== strpos( $src, $ham ), $ham );
}
t( '🔴 lớp này KHÔNG tự đụng vào bảng dữ liệu nào', false === strpos( $src, 'VHCC_DB::t(' ), $src );
t( '🔴 và KHÔNG tự chạy câu SQL nào', false === strpos( $src, '$wpdb' ), $src );
/* Mọi cửa đều phải đi qua `chot_coso()` — đó là chỗ DUY NHẤT đối chiếu cơ sở gửi lên. */
t( 'có đúng một chỗ chốt cơ sở', 1 === substr_count( $src, 'private static function chot_coso' ), $src );
t( 'và nó gác bằng co_quyen_coso', false !== strpos( $src, 'co_quyen_coso' ), $src );
/* 🔴 MỌI CỬA ĐỌC/GHI ĐỀU PHẢI QUA `chot_coso()`, và phép thử này phải TỰ TÌM RA CỬA MỚI.
   Đếm một con số cứng ("phải có đúng 3 lời gọi") thì thêm cửa thứ tư là phép thử đỏ oan, và
   người sửa chỉ việc nâng con số lên 4 — kể cả khi cửa mới ấy quên gác. Nên: liệt kê MỌI hàm
   công khai, và hàm nào không gọi `chot_coso` thì phải nằm trong danh sách miễn trừ CÓ LÝ DO. */
$mien = array(
	'duoc'     => 'chỉ hỏi bậc quyền, chưa đụng tới cơ sở nào',
	'ds_coso'  => 'chính là hàm trả về danh sách cơ sở của người ấy',
	'duyet'    => 'không nhận cơ sở; ba hàm duyệt bên dưới tự chốt từ CHÍNH BẢN GHI',
);
preg_match_all( '/public static function (\w+)\(/', $src, $m_h, PREG_OFFSET_CAPTURE );
t( 'có tìm thấy hàm công khai để soi', count( $m_h[1] ) > 3, count( $m_h[1] ) );
foreach ( $m_h[1] as $i => $h ) {
	$ten = $h[0];
	$dau = $m_h[0][ $i ][1];
	$cuoi = isset( $m_h[0][ $i + 1 ] ) ? $m_h[0][ $i + 1 ][1] : strlen( $src );
	$than_h = substr( $src, $dau, $cuoi - $dau );
	if ( isset( $mien[ $ten ] ) ) {
		t( 'miễn trừ có lý do: ' . $ten . ' — ' . $mien[ $ten ], true );
		continue;
	}
	t( '🔴 cửa "' . $ten . '" phải gọi chot_coso()',
		false !== strpos( $than_h, 'self::chot_coso(' ), $ten );
}

/* =============================================================== 4. BẢNG CÔNG */

$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'CH001', 'ho_ten' => 'Vũ Thị Nhân', 'coso' => $CS_A, 'ngay' => $HOM,
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 16 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'CH002', 'ho_ten' => 'Hồ Văn Kia', 'coso' => $CS_B, 'ngay' => $HOM,
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 20 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );

$c = VHCC_CuaHang::cong_coso( $CHT_A, $CS_A, $TH );
t( 'trưởng A đọc được bảng công cơ sở mình', ! empty( $c['ok'] ), $c );
t( 'đúng một người', 1 === (int) $c['soNguoi'], $c );
t( 'đúng số giờ (8h)', 8.0 === (float) $c['dong'][0]['gio'], $c['dong'][0] );
t( 'đúng số ngày', 1 === (int) $c['dong'][0]['soNgay'], $c['dong'][0] );
t( '🔴 KHÔNG lẫn người của cơ sở khác',
	false === strpos( wp_json_encode( $c ), 'Hồ Văn Kia' ), $c );

/* ⚠️ BẢNG CÔNG TRÊN TRẠM KHÔNG BÀY TIỀN. Nó là bảng CÔNG, không phải bảng lương — và một con
   số tiền lọt lên màn điện thoại giữa quầy là chuyện không rút lại được. */
foreach ( array( 'luong', 'gia', 'tien', 'Cb' ) as $cam ) {
	t( '⚠️ bảng công trên trạm không mang khoá "' . $cam . '"',
		false === strpos( wp_json_encode( $c['dong'][0] ), $cam ), $c['dong'][0] );
}

/* 🔴 THIẾU MỘT ĐẦU GIỜ THÌ KHÔNG CỘNG PHÚT NÀO, VÀ ĐẾM RIÊNG. Coi như 0 là trừ công một người
   vì cái máy lỗi; đoán một ca chuẩn là cấp giờ không ai làm. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'CH001', 'ho_ten' => 'Vũ Thị Nhân', 'coso' => $CS_A, 'ngay' => $TH . '-03',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => null, 'hau_to' => '', 'nguon' => 'may' ) );
$c = VHCC_CuaHang::cong_coso( $CHT_A, $CS_A, $TH );
t( '🔴 lượt thiếu đầu giờ KHÔNG cộng phút nào', 8.0 === (float) $c['dong'][0]['gio'], $c['dong'][0] );
t( '🔴 nhưng được ĐẾM và nói ra', 1 === (int) $c['dong'][0]['thieu'], $c['dong'][0] );
t( 'và cộng vào tổng của cả cơ sở', 1 === (int) $c['tongThieu'], $c );

$c2 = VHCC_CuaHang::cong_coso( $CHT_A, $CS_B, $TH );
t( '🔴 trưởng A KHÔNG xem được bảng công cơ sở B', empty( $c2['ok'] ), $c2 );
$c3 = VHCC_CuaHang::cong_coso( $NV, $CS_A, $TH );
t( '🔴 nhân viên thường KHÔNG xem được bảng công cơ sở', empty( $c3['ok'] ), $c3 );

/* =============================================================== 4b. SỬA CÔNG TỪNG NGÀY */

/* Anh Thắng 17/09/2026: *"có thêm chức năng sửa công nhân viên và set giờ theo công việc như
   web luôn được không, mà giao diện dùng như app nhé"*. */

/* ⚠️ TRA DÒNG THEO NGÀY, KHÔNG THEO THỨ TỰ TRONG MẢNG. Danh sách xếp theo ngày, nên thêm một
   ngày gieo là mọi chỉ số dịch đi một — bài kiểm đỏ ở chỗ chẳng liên quan gì tới cái nó canh. */
function ngay_o( $n, $d ) {
	foreach ( (array) ( isset( $n['ngay'] ) ? $n['ngay'] : array() ) as $x ) {
		if ( $x['ngay'] === $d ) { return $x; }
	}
	return null;
}

$n = VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( 'đọc được danh sách ngày của một người', ! empty( $n['ok'] ), $n );
t( 'đúng tên người ấy', 'Vũ Thị Nhân' === $n['hoTen'], $n );
t( 'có hai ngày (một đủ giờ, một thiếu)', 2 === count( $n['ngay'] ), $n['ngay'] );
t( '🔴 hiện GIỜ ĐANG CÓ để khỏi phải nhớ', '08:00' === ngay_o( $n, $HOM )['vao'], ngay_o( $n, $HOM ) );
t( 'ngày thiếu giờ ra được đánh dấu', ! empty( ngay_o( $n, $TH . '-03' )['thieu'] ), ngay_o( $n, $TH . '-03' ) );
t( 'tổng giờ tháng khớp với bảng công', 8.0 === (float) $n['gioThang'], $n );
t( 'nói trước người xem có quyền sửa hay không', isset( $n['duocSua'] ), $n );

/* 🔴 KHÔNG LẪN NGƯỜI KHÁC, và KHÔNG với sang cơ sở khác. */
t( '🔴 không lẫn người cơ sở khác',
	false === strpos( wp_json_encode( $n ), 'Hồ Văn Kia' ), $n );
t( '🔴 trưởng A không đọc được ngày của người cơ sở B',
	empty( VHCC_CuaHang::ngay_cua( $CHT_A, $CS_B, $TH, 'CH002' )['ok'] ) );
t( '🔴 nhân viên thường không đọc được',
	empty( VHCC_CuaHang::ngay_cua( $NV, $CS_A, $TH, 'CH001' )['ok'] ) );
t( 'thiếu mã nhân viên thì chối',
	empty( VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, '' )['ok'] ) );

/* 🔴 KHÔNG NỚI MỘT LUẬT NÀO CỦA `VHCC_Bu::sua`. Lý do <5 ký tự phải bị chối — đó là thứ duy
   nhất còn lại để sau này lần ra ai sửa giờ của ai và vì sao. */
$r = VHCC_CuaHang::sua_gio( $CHT_A, array(
	'maNV' => 'CH001', 'ngay' => $HOM, 'vao' => '09:00', 'lyDo' => 'x' ) );
t( '🔴 lý do quá ngắn thì CHỐI', empty( $r['ok'] ), $r );

$r = VHCC_CuaHang::sua_gio( $NV, array(
	'maNV' => 'CH001', 'ngay' => $HOM, 'vao' => '09:00', 'lyDo' => 'máy lệch giờ, xem camera' ) );
t( '🔴 nhân viên thường KHÔNG sửa được giờ', empty( $r['ok'] ), $r );

$r = VHCC_CuaHang::sua_gio( $CHT_B, array(
	'coSo' => $CS_A, 'maNV' => 'CH001', 'ngay' => $HOM,
	'vao' => '09:00', 'lyDo' => 'máy lệch giờ, xem camera' ) );
t( '🔴 trưởng B KHÔNG sửa được giờ của cơ sở A', empty( $r['ok'] ), $r );

/* 🔴 18/09/2026 — CỬA HÀNG TRƯỞNG KHÔNG CÒN TỰ SỬA ĐƯỢC. Anh Thắng: *"cơ chế hiện tại là
   cửa hàng trưởng không được sửa công nữa mà theo người được chỉ định bật quyền mới được sửa
   thôi"*. Phải chối TRƯỚC khi có dòng chỉ định, không thì phần dưới xanh mà không ai biết cái
   gì làm nó xanh — bậc hay dòng ngoại lệ. */
$r = VHCC_CuaHang::sua_gio( $CHT_A, array(
	'maNV' => 'CH001', 'ngay' => $HOM, 'vao' => '09:00',
	'lyDo' => 'máy lệch đồng hồ, đối chiếu camera' ) );
t( '🔴 trưởng A CHƯA được chỉ định thì không sửa được', empty( $r['ok'] ), $r );

/* Kế toán trở lên khai một dòng cho đúng người. Từ đây trở xuống trưởng A là "người được
   chỉ định", và mọi phép thử cơ chế phía sau chạy trên cảnh ấy. */
VHCC_Vai::dat_ngoai_le( $ADMIN, 'nv:' . $CHT_A['ma_nv'], 'sua_gio', 'mo' );

$r = VHCC_CuaHang::sua_gio( $CHT_A, array(
	'maNV' => 'CH001', 'ngay' => $HOM, 'vao' => '09:00',
	'lyDo' => 'máy lệch đồng hồ, đối chiếu camera' ) );
t( 'được chỉ định rồi thì trưởng A sửa được giờ vào', ! empty( $r['ok'] ), $r );
t( 'và nói ra ô nào đổi, từ đâu sang đâu',
	isset( $r['doi']['vao'] ) && '08:00' === $r['doi']['vao']['cu']
		&& '09:00' === $r['doi']['vao']['moi'], $r );

$n = VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( 'giờ mới vào sổ', '09:00' === ngay_o( $n, $HOM )['vao'], ngay_o( $n, $HOM ) );
t( 'và tổng giờ tháng giảm theo (8h -> 7h)', 7.0 === (float) $n['gioThang'], $n );

/* 🔴 Ô TRỐNG = GIỮ NGUYÊN, KHÔNG PHẢI XOÁ. Đây là luật của `VHCC_Bu::sua` và là chỗ dễ mất
   giờ công nhất: người sửa giờ ra mà không gõ lại giờ vào là chuyện thường. */
$r = VHCC_CuaHang::sua_gio( $CHT_A, array(
	'maNV' => 'CH001', 'ngay' => $HOM, 'vao' => '', 'ra' => '17:00',
	'lyDo' => 'chỉ sửa giờ ra thôi' ) );
t( 'sửa mỗi giờ ra được', ! empty( $r['ok'] ), $r );
$n = VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( '🔴 ô trống GIỮ NGUYÊN giờ vào, không xoá nó', '09:00' === ngay_o( $n, $HOM )['vao'], ngay_o( $n, $HOM ) );
t( 'và giờ ra là giá trị mới', '17:00' === ngay_o( $n, $HOM )['ra'], ngay_o( $n, $HOM ) );

/* ⚠️ XOÁ GIỜ LÀ TÍCH CẢ HAI Ô, VÀ VẪN PHẢI CÓ LÝ DO. Dòng chấm công KHÔNG biến mất — mất dấu
   là hôm ấy vốn có người chấm thì không ai lần lại được. */
$r = VHCC_CuaHang::sua_gio( $CHT_A, array(
	'maNV' => 'CH001', 'ngay' => $HOM, 'xoaVao' => 1, 'xoaRa' => 1,
	'lyDo' => 'chấm nhầm người, xoá giờ ngày này' ) );
t( '⚠️ xoá được giờ cả hai đầu', ! empty( $r['ok'] ), $r );
$n = VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( '🔴 DÒNG VẪN CÒN, chỉ là không còn giờ', 2 === count( $n['ngay'] ), $n['ngay'] );
t( 'và ngày ấy nay tính là thiếu giờ', ! empty( ngay_o( $n, $HOM )['thieu'] ), ngay_o( $n, $HOM ) );

/* Trả giờ lại cho phần chốt lương phía dưới có số mà tính. */
VHCC_CuaHang::sua_gio( $CHT_A, array( 'maNV' => 'CH001', 'ngay' => $HOM,
	'vao' => '08:00', 'ra' => '18:00', 'lyDo' => 'trả lại giờ cho phép thử sau' ) );
$n = VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( 'giờ đã trả lại đủ 10h', 10.0 === (float) $n['gioThang'], $n );

/* ── AI ĐƯỢC CHỈNH GIỜ CÔNG — khoá lại câu trả lời, 18/09/2026 ──────────────────────────────
   Anh Thắng 17/09 hỏi *"ai phân quyền mới được chỉnh giờ công phải không (cửa hàng trưởng)"*,
   rồi 18/09 chốt lại: *"cửa hàng trưởng không được sửa công nữa mà theo người được chỉ định
   bật quyền mới được sửa thôi"*.

   Nên BẬC cố tình đặt cao tới mức gần như không ai với tới, và việc chỉ định làm bằng bảng
   ngoại lệ. Phép thử này khoá cả hai nửa: bậc cao, và bù thì KHÔNG bị kéo lên theo. */
t( '🔴 chỉnh giờ công ở bậc KẾ TOÁN — cửa hàng trưởng và quản lý không có sẵn',
	VHCC_Vai::KE_TOAN === VHCC_Vai::QUYEN['sua_gio'] );
t( '🔴 và đúng là người mà mọi câu chối chỉ tới ("liên hệ kế toán")',
	VHCC_Vai::BAC[ VHCC_Vai::QUYEN['sua_gio'] ] <= VHCC_Vai::BAC[ VHCC_Vai::ADMIN ] );
t( '🔴 bù vào ô trống VẪN ở bậc Cửa hàng trưởng — siết sửa đè không siết bù',
	VHCC_Vai::CHT === VHCC_Vai::QUYEN['cham_bu'] );
t( '⚠️ và sửa đè nay CAO HƠN cả nạp .csv — đè lên giờ máy ghi là việc đắt nhất',
	VHCC_Vai::BAC[ VHCC_Vai::QUYEN['sua_gio'] ] > VHCC_Vai::BAC[ VHCC_Vai::QUYEN['nap_cong'] ] );

/* 🔴 KHÔNG AI TỰ SỬA GIỜ CỦA CHÍNH MÌNH, KỂ CẢ ADMIN. Đây là chốt đỡ quan trọng nhất cho việc
   hạ `sua_gio` xuống bậc 2: cửa hàng trưởng viết lại được bảng công của cửa hàng mình, nhưng
   KHÔNG viết lại được của chính mình — nên giờ của người ký duyệt luôn là giờ máy ghi. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'CHTA',
	'ho_ten' => 'Trưởng A', 'cua_hang' => $CS_A, 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'ma_nv' => 'CHTA', 'ho_ten' => 'Trưởng A',
	'coso' => $CS_A, 'ngay' => $HOM, 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
$r = VHCC_CuaHang::sua_gio( $CHT_A, array( 'maNV' => 'CHTA', 'ngay' => $HOM,
	'vao' => '06:00', 'lyDo' => 'tự sửa giờ cho chính mình' ) );
t( '🔴 cửa hàng trưởng KHÔNG tự sửa giờ của CHÍNH MÌNH', empty( $r['ok'] ), $r );
$r = VHCC_CuaHang::sua_gio( array( 'name' => 'Sếp', 'role' => VHCC_Vai::ADMIN,
	'coso' => $CS_A, 'ma_nv' => 'CHTA' ), array( 'coSo' => $CS_A, 'maNV' => 'CHTA',
	'ngay' => $HOM, 'vao' => '06:00', 'lyDo' => 'admin tự sửa giờ của mình' ) );
t( '🔴 KỂ CẢ ADMIN cũng không tự sửa giờ của mình', empty( $r['ok'] ), $r );

/* ⚠️ MỌI LƯỢT SỬA VÀO NHẬT KÝ, GIỮ GIỜ CŨ. Đó là thứ duy nhất còn lại để tra ngược khi bảng
   công đã bị viết đè. */
$nk = VHCC_Bu::ds_nhat_ky( $CHT_A, $CS_A );
t( '⚠️ mọi lượt sửa đều vào nhật ký', count( $nk ) > 0, count( $nk ) );

/* ── XOÁ HẲN MỘT DÒNG CHẤM CÔNG (anh Thắng 17/09/2026: *"làm nút xóa hẳn dòng công"*) ──────
   🔴 ĐÂY LÀ VIỆC PHÁ NHIỀU NHẤT TRÊN TRẠM. Xoá giờ còn để lại dấu "hôm ấy có một dòng"; xoá
   hẳn thì lưới trông y như người ta KHÔNG ĐI LÀM, và không còn gì trên màn mâu thuẫn với
   chuyện ấy. Nên nó phải gác Y HỆT `sua()`, không rẻ hơn một li. */

/* ⚠️ NGƯỜI RIÊNG CHO MỤC XOÁ. Xoá trên chính CH001 là phá mất giờ mà mục chốt lương ngay dưới
   đang dùng làm TRẦN — rồi mục ấy đỏ vì một lý do chẳng liên quan gì tới nó. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'CH003',
	'ho_ten' => 'Đinh Bị Xoá', 'cua_hang' => $CS_A, 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'ma_nv' => 'CH003', 'ho_ten' => 'Đinh Bị Xoá',
	'coso' => $CS_A, 'ngay' => $HOM, 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
$truoc = count( VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH003' )['ngay'] );

$r = VHCC_CuaHang::xoa_cong( $NV, array(
	'maNV' => 'CH003', 'ngay' => $HOM, 'lyDo' => 'nhân viên thử xoá' ) );
t( '🔴 nhân viên thường KHÔNG xoá được dòng', empty( $r['ok'] ), $r );
$r = VHCC_CuaHang::xoa_cong( $CHT_B, array( 'coSo' => $CS_A,
	'maNV' => 'CH003', 'ngay' => $HOM, 'lyDo' => 'trưởng cơ sở khác thử xoá' ) );
t( '🔴 trưởng cơ sở khác KHÔNG xoá được', empty( $r['ok'] ), $r );
$r = VHCC_CuaHang::xoa_cong( $CHT_A, array(
	'maNV' => 'CH003', 'ngay' => $HOM, 'lyDo' => 'x' ) );
t( '🔴 lý do quá ngắn thì CHỐI', empty( $r['ok'] ), $r );
$r = VHCC_CuaHang::xoa_cong( $CHT_A, array(
	'maNV' => 'CHTA', 'ngay' => $HOM, 'lyDo' => 'tự xoá dòng của chính mình' ) );
t( '🔴 KHÔNG tự xoá dòng của CHÍNH MÌNH', empty( $r['ok'] ), $r );
$r = VHCC_CuaHang::xoa_cong( $CHT_A, array(
	'maNV' => 'CH002', 'ngay' => $HOM, 'lyDo' => 'người này không có dòng ở cơ sở A' ) );
t( 'ngày không có dòng thì báo không có gì để xoá', empty( $r['ok'] ), $r );

t( 'sau năm lượt bị chối, dòng vẫn còn nguyên',
	$truoc === count( VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH003' )['ngay'] ) );

$nk_truoc = count( VHCC_Bu::ds_nhat_ky( $CHT_A, $CS_A ) );
$r = VHCC_CuaHang::xoa_cong( $CHT_A, array( 'maNV' => 'CH003', 'ngay' => $HOM,
	'lyDo' => 'máy chấm nhầm sang mã người khác, đã đối chiếu camera' ) );
t( 'trưởng A xoá được dòng của cơ sở mình', ! empty( $r['ok'] ), $r );
t( 'và trả về giờ vừa xoá để nói lại cho người bấm',
	isset( $r['daXoa']['vao'] ) && '08:00' === $r['daXoa']['vao'], $r );

$sau = VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH003' );
t( '🔴 DÒNG BIẾN MẤT HẲN khỏi lưới', $truoc - 1 === count( $sau['ngay'] ), $sau['ngay'] );
foreach ( $sau['ngay'] as $x ) {
	t( 'không còn ngày vừa xoá trong danh sách', $HOM !== $x['ngay'], $x );
}

/* 🔴 BẰNG CHỨNG KHÔNG BAO GIỜ ĐƯỢC LÀ THỨ THIẾU. Dòng mất rồi thì sổ là chỗ duy nhất còn nói
   được hôm ấy có gì — và phải ghi CẢ HAI ô, kể cả ô vốn trống. */
$nk = VHCC_Bu::ds_nhat_ky( $CHT_A, $CS_A );
t( '🔴 xoá dòng thì vào sổ ĐÚNG HAI dòng (ô vào và ô ra)',
	$nk_truoc + 2 === count( $nk ), array( $nk_truoc, count( $nk ) ) );
$co_xoa = 0; $giu_gio_cu = false;
foreach ( $nk as $x ) {
	if ( 'xoa' !== (string) $x['viec'] ) { continue; }
	$co_xoa++;
	if ( 28800 === (int) $x['gio_cu_giay'] ) { $giu_gio_cu = true; }
	t( 'dòng sổ nào cũng mang lý do', '' !== trim( (string) $x['ly_do'] ), $x );
}
t( 'sổ đánh dấu đúng việc "xoa"', 2 === $co_xoa, $co_xoa );
t( '🔴 và GIỮ GIỜ CŨ để sau còn dựng lại được', $giu_gio_cu, $nk );

/* Canh bằng mã nguồn: ghi sổ TRƯỚC, xoá SAU. Xoá trước mà sổ hỏng thì dòng mất và không còn
   gì nói nó từng tồn tại — hỏng kiểu không ai biết là có chuyện để lần. */
$src_bu = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-bu.php' );
$i_x = strpos( $src_bu, 'public static function xoa(' );
$than_x = substr( $src_bu, $i_x, 3000 );
t( '🔴 ghi nhật ký TRƯỚC, xoá dòng SAU',
	false !== $i_x && strpos( $than_x, 'self::nhat_ky(' ) < strpos( $than_x, '$wpdb->delete(' ),
	$than_x );
t( 'và vẫn gác bằng vi_sao_khong_duoc như sua()',
	false !== strpos( $than_x, 'vi_sao_khong_duoc' ), $than_x );
t( "và vẫn đòi quyền 'sua_gio'", false !== strpos( $than_x, "'sua_gio'" ), $than_x );

/* ── HẾT 24H LÀ KHOÁ (anh Thắng 17/09/2026) ────────────────────────────────────────────────
   *"Hiện quản lý không cho cửa hàng trưởng sửa nữa… hết 24h hôm nay không cho phép sửa giờ
   công. Vui lòng liên hệ kế toán"*.

   🔴 KHOÁ THEO NGÀY, KHÔNG PHẢI THU QUYỀN. Hạ `sua_gio` khỏi bậc Cửa hàng trưởng thì họ mất
   luôn đường sửa cái vừa gõ nhầm năm phút trước. Khoá theo ngày thì hôm nay tự dọn, hôm qua
   trở về trước đã đóng — đúng chỗ rủi ro thật. */

$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'ma_nv' => 'CH001', 'ho_ten' => 'Vũ Thị Nhân',
	'coso' => $CS_A, 'ngay' => $CU, 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );

t( '🔴 cửa hàng trưởng BỊ khoá theo ngày', VHCC_Bu::bi_khoa_ngay_cu( $CHT_A ) );
t( 'nhưng hôm nay thì vẫn mở', '' === VHCC_Bu::han_ngay( $CHT_A, $HOM ) );
t( '🔴 còn ngày hôm qua thì đã khoá', '' !== VHCC_Bu::han_ngay( $CHT_A, $CU ) );
t( 'và câu chối chỉ đúng người cần liên hệ',
	false !== mb_strpos( VHCC_Bu::han_ngay( $CHT_A, $CU ), 'liên hệ kế toán' ),
	VHCC_Bu::han_ngay( $CHT_A, $CU ) );

/* 🔴 AI KHÔNG BỊ KHOÁ — chốt bằng QUYỀN `cong_tat_ca`, không bằng tên vai. */
foreach ( array( VHCC_Vai::QL, VHCC_Vai::KE_TOAN, VHCC_Vai::ADMIN ) as $v_mo ) {
	$u_mo = array( 'name' => 'x', 'role' => $v_mo, 'coso' => $CS_A, 'ma_nv' => 'AI_DO' );
	t( VHCC_Vai::TEN[ $v_mo ] . ' KHÔNG bị khoá theo ngày', ! VHCC_Bu::bi_khoa_ngay_cu( $u_mo ) );
	t( '  và sửa được ngày cũ', '' === VHCC_Bu::han_ngay( $u_mo, $CU ) );
}

/* Chốt bằng HÀNH VI, không chỉ bằng hàm phụ: cửa thật phải chối. */
$r = VHCC_CuaHang::sua_gio( $CHT_A, array( 'maNV' => 'CH001', 'ngay' => $CU,
	'vao' => '09:00', 'lyDo' => 'sửa ngày hôm qua, đã quá hạn' ) );
t( '🔴 cửa SỬA chối ngày đã qua', empty( $r['ok'] ), $r );
t( 'và đánh dấu rõ là quá hạn để màn nói đúng câu', ! empty( $r['quaHan'] ), $r );

/* ⚠️ XOÁ CŨNG CHỊU CÙNG CÁI KHOÁ. Mở một trong hai mà khoá cái kia là để hở đúng đường phá
   nhiều hơn. */
$r = VHCC_CuaHang::xoa_cong( $CHT_A, array( 'maNV' => 'CH001', 'ngay' => $CU,
	'lyDo' => 'xoá ngày hôm qua, đã quá hạn' ) );
t( '🔴 cửa XOÁ cũng chối ngày đã qua', empty( $r['ok'] ), $r );
$sau_cu = VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH001' );
$con = false;
foreach ( $sau_cu['ngay'] as $x ) { if ( $x['ngay'] === $CU ) { $con = true; } }
t( 'dòng hôm qua vẫn còn nguyên', $con, $sau_cu['ngay'] );

/* ⚠️ BÙ THÌ KHÔNG KHOÁ. Bù là điền vào ô TRỐNG — đó chính là việc màn trạm đang giục làm
   ("lượt thiếu một đầu giờ, bổ sung trước khi kế toán chốt lương"), và mấy lượt ấy gần như
   luôn là của hôm trước. Khoá bù theo ngày là vừa giục vừa chặn. */
$r = VHCC_Bu::ghi( $CHT_A, array( 'coso' => $CS_A, 'ma_nv' => 'CH001', 'ngay' => $TH . '-03',
	'ra' => '17:00', 'ly_do' => 'bổ sung giờ ra bị thiếu hôm ấy' ) );
t( '⚠️ BÙ vào ô trống của ngày cũ thì VẪN ĐƯỢC', ! empty( $r['ok'] ), $r );

/* Máy chủ trả sẵn câu nhắc cho màn — màn không tự chế luật. */
t( 'máy chủ trả câu nhắc cho người bị khoá',
	'' !== VHCC_Bu::nhac_han_ngay( $CHT_A ), VHCC_Bu::nhac_han_ngay( $CHT_A ) );
t( '🔴 và câu ấy đúng nội dung anh Thắng yêu cầu',
	false !== mb_strpos( VHCC_Bu::nhac_han_ngay( $CHT_A ), 'hết 24h hôm nay' )
	|| false !== mb_strpos( VHCC_Bu::nhac_han_ngay( $CHT_A ), 'Hết 24h hôm nay' ),
	VHCC_Bu::nhac_han_ngay( $CHT_A ) );
t( 'người không bị khoá thì KHÔNG thấy câu nhắc',
	'' === VHCC_Bu::nhac_han_ngay( array( 'name' => 'Sếp', 'role' => VHCC_Vai::ADMIN, 'coso' => '' ) ) );

$n_k = VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( 'màn một người biết mình bị khoá theo ngày', ! empty( $n_k['khoaNgayCu'] ), $n_k );
t( 'và biết hôm nay là ngày nào (theo MÁY CHỦ)', $HOM === (string) $n_k['homNay'], $n_k );

/* =============================================================== 4c. CHỐT LƯƠNG THEO VIỆC */

$c = VHCC_CuaHang::chot_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( 'đọc được màn chốt lương của một người', ! empty( $c['ok'] ), $c );
/* 🔴 SO VỚI CHÍNH BẢNG CHẤM CÔNG, KHÔNG SO VỚI MỘT CON SỐ GÕ CỨNG. Gõ cứng "10.0" thì thêm
   một hàng gieo ở mục khác là bài này đỏ — mà nó có canh chuyện gieo đâu. Nó canh đúng một
   điều: trần của màn chốt lương ĐÚNG BẰNG giờ đã chấm, không phải một con số khác. */
$gio_that = (float) VHCC_CuaHang::ngay_cua( $CHT_A, $CS_A, $TH, 'CH001' )['gioThang'];
t( 'người này có giờ để mà chốt', $gio_that > 0, $gio_that );
t( '🔴 trần giờ lấy từ CHÍNH bảng chấm công', $gio_that === (float) $c['gioCham'], $c );
t( 'có bảng tên khoản cộng', ! empty( $c['tenCong'] ), $c );
t( 'có bảng tên khoản trừ', ! empty( $c['tenTru'] ), $c );
t( '🔴 nhân viên thường KHÔNG mở được màn chốt lương',
	empty( VHCC_CuaHang::chot_cua( $NV, $CS_A, $TH, 'CH001' )['ok'] ) );
t( '🔴 trưởng A không mở được người của cơ sở B',
	empty( VHCC_CuaHang::chot_cua( $CHT_A, $CS_B, $TH, 'CH002' )['ok'] ) );

/* 🔴 TỔNG GIỜ KHÁC KHÔNG ĐƯỢC VƯỢT GIỜ CHẤM CÔNG — vượt là giờ chính ra ÂM, tức trừ tiền một
   người vì một con số gõ nhầm, mà bảng vẫn có số nên nhìn qua không thấy gì lạ. */
$r = VHCC_CuaHang::chot_luu( $CHT_A, array(
	'maNV' => 'CH001', 'thang' => $TH,
	'dong' => array( array( 'viec' => 'MC', 'gio' => '99' ) ) ) );
t( '🔴 giờ khác vượt giờ chấm công thì CHỐI', empty( $r['ok'] ), $r );
t( 'và câu chối nói rõ là sẽ ra số âm',
	false !== mb_strpos( (string) $r['error'], 'âm' ), $r );

$r = VHCC_CuaHang::chot_luu( $CHT_A, array(
	'maNV' => 'CH001', 'thang' => $TH,
	'dong' => array( array( 'viec' => '', 'gio' => '3' ) ) ) );
t( 'gõ giờ mà chưa đặt tên việc thì chối', empty( $r['ok'] ), $r );

/* ⚠️ `gioCham` TÍNH LẠI Ở MÁY CHỦ, KHÔNG NHẬN TỪ BIỂU MẪU — nhận từ màn là trần tự khai, tức
   bỏ luôn chính phép chặn ngay trên. Gửi kèm một trần bịa thật to để canh lại. */
$r = VHCC_CuaHang::chot_luu( $CHT_A, array(
	'maNV' => 'CH001', 'thang' => $TH, 'gioCham' => 9999, 'gioThang' => 9999,
	'dong' => array( array( 'viec' => 'MC', 'gio' => '99' ) ) ) );
t( '🔴 khai trần giờ trong biểu mẫu KHÔNG nới được phép chặn', empty( $r['ok'] ), $r );

$r = VHCC_CuaHang::chot_luu( $CHT_A, array(
	'maNV' => 'CH001', 'thang' => $TH, 'viecChinh' => 'Nhân viên',
	'dong' => array( array( 'viec' => 'MC', 'gio' => '2' ) ),
	'cong' => array( 'setup' => '150000' ), 'tru' => array( 'phat' => '50000' ) ) );
t( 'lưu được chốt lương', ! empty( $r['ok'] ), $r );

$c = VHCC_CuaHang::chot_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( 'dòng giờ khác vào sổ', 1 === count( $c['dong'] ) && 2.0 === (float) $c['dong'][0]['gio'], $c );
t( 'việc chính vào sổ', 'Nhân viên' === (string) $c['vieChinh'], $c );
t( 'khoản cộng vào sổ', 150000.0 === (float) $c['cong']['setup'], $c );
t( 'khoản trừ vào sổ', 50000.0 === (float) $c['tru']['phat'], $c );

/* 🔴 NHÂN VIÊN THƯỜNG KHÔNG LƯU ĐƯỢC — đây là cửa ghi vào TIỀN. */
$r = VHCC_CuaHang::chot_luu( $NV, array( 'maNV' => 'CH001', 'thang' => $TH,
	'dong' => array( array( 'viec' => 'MC', 'gio' => '1' ) ) ) );
t( '🔴 nhân viên thường KHÔNG lưu được chốt lương', empty( $r['ok'] ), $r );
$c = VHCC_CuaHang::chot_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( 'và sổ giữ nguyên giá trị cũ', 2.0 === (float) $c['dong'][0]['gio'], $c );

/* 🔴 DỪNG Ở LƯỢT GHI ĐẦU TIÊN HỎNG — ghi tiếp sau một lượt chối là lưu một nửa: người dùng
   thấy câu lỗi và tưởng KHÔNG có gì được ghi, trong khi mấy khoản trừ đã vào sổ rồi. */
$r = VHCC_CuaHang::chot_luu( $CHT_A, array(
	'maNV' => 'CH001', 'thang' => $TH,
	'dong' => array( array( 'viec' => 'MC', 'gio' => '500' ) ),
	'tru'  => array( 'phat' => '999000' ) ) );
t( 'lượt ghi hỏng ở bước đầu', empty( $r['ok'] ), $r );
$c = VHCC_CuaHang::chot_cua( $CHT_A, $CS_A, $TH, 'CH001' );
t( '🔴 và KHÔNG ghi một nửa — khoản trừ giữ nguyên số cũ',
	50000.0 === (float) $c['tru']['phat'], $c );

/* ⚠️ ĂN LƯƠNG THÁNG: tích mà không gõ lương cơ bản thì chối — để trống rồi vẫn tích là bảng
   lương lặng lẽ trả 0đ cho người ấy. */
$r = VHCC_CuaHang::chot_luu( $CHT_A, array(
	'maNV' => 'CH001', 'thang' => $TH, 'anLuongThang' => 1, 'luongCb' => '',
	'dong' => array() ) );
t( '⚠️ tích ăn lương tháng mà bỏ trống lương cơ bản thì CHỐI', empty( $r['ok'] ), $r );

/* =============================================================== 5. THÊM NGƯỜI MỚI */

$r = VHCC_CuaHang::them_nguoi( $NV, array(
	'hoTen' => 'Người Lạ', 'cccd' => '000000000111', 'coSo' => $CS_A ) );
t( '🔴 nhân viên thường KHÔNG thêm được người', empty( $r['ok'] ), $r );

/* 🔴 CƠ SỞ LẤY TỪ PHIÊN. Trưởng A gõ tên cơ sở B là thêm người vào cửa hàng người khác. */
$r = VHCC_CuaHang::them_nguoi( $CHT_A, array(
	'hoTen' => 'Người Lạ', 'cccd' => '000000000222', 'coSo' => $CS_B ) );
t( '🔴 trưởng A KHÔNG thêm được người vào cơ sở B', empty( $r['ok'] ), $r );

/* ⚠️ CĂN CƯỚC LÀ BẮT BUỘC — không có nó thì người mới không tự lấy được PIN ở màn "Quên PIN",
   và hồ sơ vừa tạo thành hồ sơ chết mà cửa hàng trưởng tưởng đã xong. */
$r = VHCC_CuaHang::them_nguoi( $CHT_A, array( 'hoTen' => 'Thiếu Căn Cước', 'cccd' => '' ) );
t( '⚠️ thiếu căn cước thì CHỐI', empty( $r['ok'] ), $r );
t( 'và câu chối chỉ đúng đường "Quên PIN"',
	false !== mb_strpos( (string) $r['error'], 'Quên PIN' ), $r );
$r = VHCC_CuaHang::them_nguoi( $CHT_A, array( 'hoTen' => '', 'cccd' => '000000000333' ) );
t( 'thiếu họ tên thì chối', empty( $r['ok'] ), $r );

$r = VHCC_CuaHang::them_nguoi( $CHT_A, array(
	'hoTen' => 'Trần Mới Vào', 'cccd' => '000000000444', 'sdt' => '0900000000' ) );
t( 'trưởng A thêm được người vào cơ sở mình', ! empty( $r['ok'] ), $r );
t( 'người mới vào đúng cơ sở của trưởng A', $CS_A === (string) $r['coso'], $r );
t( '🔴 mã cấp ra là mã TẠM, để nhân sự lọc lại sau',
	0 === strpos( (string) $r['ma_nv'], 'TAM-' ), $r );

$hs = VHCC_NhanSu::ho_so( $r['ma_nv'] );
t( '🔴 người mới có VAI, không để trống', '' !== trim( (string) $hs['vai_tro'] ), $hs );
t( 'và vai ấy là Nhân viên, không phải bậc cao hơn',
	VHCC_Vai::TEN[ VHCC_Vai::NV ] === (string) $hs['vai_tro'], $hs );
t( '🔴 cửa này KHÔNG đặt lương cơ bản', empty( $hs['luong_co_ban'] ), $hs );

/* ── DANH SÁCH NHÂN SỰ CỦA CƠ SỞ ───────────────────────────────────────────────────────────
   Anh Thắng 17/09/2026: *"Thêm tab Nhân Sự trong Quản Lý Cửa Hàng"*. */

$r_ns = VHCC_CuaHang::nhan_su( $NV, $CS_A );
t( '🔴 nhân viên thường KHÔNG xem được danh sách nhân sự', empty( $r_ns['ok'] ), $r_ns );
$r_ns = VHCC_CuaHang::nhan_su( $CHT_B, $CS_A );
t( '🔴 trưởng cơ sở khác KHÔNG xem được', empty( $r_ns['ok'] ), $r_ns );

$ns = VHCC_CuaHang::nhan_su( $CHT_A, $CS_A );
t( 'cửa hàng trưởng xem được người của cơ sở mình', ! empty( $ns['ok'] ), $ns );
t( 'có người trong danh sách', $ns['so'] > 0, $ns );
$chuoi_ns = wp_json_encode( $ns );
t( '🔴 KHÔNG lẫn người cơ sở khác', false === strpos( $chuoi_ns, 'Hồ Văn Kia' ), $ns );

/* 🔴 KHÔNG BAO GIỜ TRẢ PIN — chỉ CÓ hay CHƯA. Biết PIN của một người là đăng nhập thay họ
   được, mà màn hình của họ không có gì đổi. Cùng luật với trang Nhân sự cửa hàng. */
foreach ( $ns['nguoi'] as $x ) {
	t( '🔴 không trả PIN của ai', ! isset( $x['pin_dang_nhap'] ) && ! isset( $x['pin'] ), $x );
	t( 'chỉ nói CÓ hay CHƯA có PIN', isset( $x['coPin'] ) && is_bool( $x['coPin'] ), $x );
	/* ⚠️ CĂN CƯỚC CŨNG CHỈ CÓ/KHÔNG. Màn cần biết đúng một điều: người này tự đặt PIN được
	   chưa (đường "Quên PIN" đòi căn cước) — không cần chính con số ấy. */
	t( '⚠️ không trả SỐ căn cước', ! isset( $x['cccd'] ), $x );
	t( 'chỉ nói có khai căn cước chưa', isset( $x['coCccd'] ) && is_bool( $x['coCccd'] ), $x );
	/* 🔴 VÀ TUYỆT ĐỐI KHÔNG TRẢ LƯƠNG. Cửa hàng trưởng không được thấy ô ấy. */
	foreach ( array( 'luong_co_ban', 'luongCb', 'so_tai_khoan' ) as $cam_ns ) {
		t( '🔴 không trả ' . $cam_ns, ! isset( $x[ $cam_ns ] ), $x );
	}
}
t( '🔴 cả gói KHÔNG chứa PIN thật của ai',
	false === strpos( $chuoi_ns, '941111' ) && false === strpos( $chuoi_ns, '942222' ), $ns );

/* Đếm sẵn số người chưa có PIN — họ chưa đăng nhập được, tức chưa chấm công được, mà nhìn hai
   mươi thẻ thì không ai đếm ra. */
t( 'đếm sẵn số người chưa có PIN', isset( $ns['chuaPin'] ), $ns );

/* Ô tìm lọc được, và lọc ở MÁY CHỦ chứ không ở trình duyệt. */
$ns_tim = VHCC_CuaHang::nhan_su( $CHT_A, $CS_A, 'Vũ Thị Nhân' );
t( 'ô tìm lọc được theo tên', 1 === (int) $ns_tim['so'], $ns_tim );
$ns_tim = VHCC_CuaHang::nhan_su( $CHT_A, $CS_A, 'khong-co-ai-ten-nay' );
t( 'tìm không ra thì trả danh sách rỗng', 0 === (int) $ns_tim['so'], $ns_tim );

/* 🔴 GỌI LẠI `ds_nhan_vien()`, KHÔNG TỰ VIẾT SQL. Hàm ấy lọc cơ sở tính cả `coso_phu`, gác
   từng dòng, và CẮT ô lương khỏi dữ liệu. Tự viết `SELECT *` ở đây là lương đi thẳng xuống
   trình duyệt — ẩn trên màn hay không cũng đã muộn. */
t( '🔴 dùng lại VHCC_NhanSu::ds_nhan_vien()',
	false !== strpos( $src, 'VHCC_NhanSu::ds_nhan_vien' ), $src );
t( 'quyền gác đúng bậc Cửa hàng trưởng',
	VHCC_Vai::CHT === VHCC_Vai::QUYEN[ VHCC_CuaHang::QUYEN_NS ] );

/* ── NGƯỜI MỚI DÙNG ĐƯỢC NGAY HAI TRANG: CHẤM CÔNG VÀ NỘI BỘ ───────────────────────────────
   Anh Thắng 17/09/2026: *"Để cho nhân viên chấm đk công luôn… nhân viên được quyền sử dụng 2
   trang này trước theo mặc định là nội bộ và chấm công. Mấy trang khác thì phải cấp quyền"*.

   🔴 ĐIỀU NÀY ĐÃ ĐÚNG SẴN, VÀ LÝ DO LÀ THANG VAI — không phải một ô tích nào cả. Hai trang ấy
   khai `quyen => cham_online`, tức bậc Nhân viên, nên người vừa mở hồ sơ đã qua. Mấy trang còn
   lại (POSH · Báo cáo cửa hàng · Chi phí) KHÔNG gác bằng vai mà bằng SỔ ĐÃ ĐẨY riêng của từng
   hệ, nên chúng đóng cho tới khi có người đẩy sang.

   ⚠️ CỐ Ý KHÔNG ĐẶT NGOẠI LỆ "mở" ĐÍCH DANH cho từng người mới. Ngoại lệ riêng là TẦNG 1 của
      `VHCC_Cong::giai()` — nó THẮNG cả luật bộ phận lẫn luật mảng, và thắng mãi mãi. Ghim nó
      lúc tạo hồ sơ thì mai công ty đóng Nội bộ cho một bộ phận, mọi người do cửa hàng trưởng
      tạo vẫn mở — mà không ai thấy vì sao. Để thang vai lo là đúng: nó mở sẵn, và vẫn đóng
      được bằng luật nhóm khi cần.

   Nên bài thử canh CÁI LUẬT, không canh một lượt ghi: hai trang ấy phải ở bậc Nhân viên. */
$ma_moi = (string) $r['ma_nv'];
$hs_moi = VHCC_NhanSu::ho_so( $ma_moi );
t( 'đọc lại được hồ sơ vừa tạo', is_array( $hs_moi ), $ma_moi );

foreach ( array( 'tram' => 'Chấm công', 'noi_bo' => 'Nội bộ' ) as $k_tr => $ten_tr ) {
	t( 'có khai trang ' . $ten_tr . ' trong sổ trang', isset( VHCC_Cong::SO[ $k_tr ] ), $k_tr );
	$q_tr = VHCC_Cong::SO[ $k_tr ]['quyen'];
	t( '🔴 ' . $ten_tr . ' gác ở bậc NHÂN VIÊN, nên người mới vào được ngay',
		VHCC_Vai::NV === VHCC_Vai::QUYEN[ $q_tr ], array( $k_tr, $q_tr ) );
}
$u_moi2 = array( 'name' => $hs_moi['ho_ten'], 'role' => $hs_moi['vai_tro'],
	'coso' => $hs_moi['cua_hang'], 'ma_nv' => $hs_moi['ma_nv'] );
t( '🔴 người vừa tạo VÀO ĐƯỢC trạm chấm công', VHCC_Cong::duoc_vao( $u_moi2, 'tram' ) );
t( '🔴 và VÀO ĐƯỢC Nội bộ', VHCC_Cong::duoc_vao( $u_moi2, 'noi_bo' ) );
t( 'vai của người mới đúng là Nhân viên (bậc thấp nhất)',
	VHCC_Vai::TEN[ VHCC_Vai::NV ] === (string) $hs_moi['vai_tro'], $hs_moi );

/* ⚠️ MẤY TRANG KHÁC THÌ PHẢI CẤP. Chúng không gác bằng vai mà bằng sổ ĐÃ ĐẨY của từng hệ —
   nên người mới chưa có tên trong sổ nào cả. */
foreach ( array( 'VHCC_DayGhe' => 'hệ ghế (POSH)', 'VHCC_DayBaoCao' => 'Báo cáo cửa hàng',
	'VHCC_DayChiPhi' => 'Vận hành chi phí' ) as $lop_d => $ten_d ) {
	if ( ! class_exists( $lop_d ) || ! method_exists( $lop_d, 'da_day' ) ) { continue; }
	t( '⚠️ người mới CHƯA được đẩy sang ' . $ten_d . ' — phải cấp quyền',
		! call_user_func( array( $lop_d, 'da_day' ), $ma_moi ), $ten_d );
}
/* Và mấy hệ ấy KHÔNG gác bằng thang vai — nếu có, chúng đã tự mở cho mọi nhân viên. */
foreach ( array( 'ghe', 'bao_cao', 'chi_phi' ) as $k_la ) {
	t( 'sổ trang KHÔNG khai ' . $k_la . ' (nó có sổ đẩy riêng)', ! isset( VHCC_Cong::SO[ $k_la ] ) );
}

/* ── NGƯỜI MỚI TỰ ĐẶT PIN, KHÔNG AI CẤP HỘ ─────────────────────────────────────────────────
   Anh Thắng 17/09/2026: *"khi cửa hàng trưởng tạo nv mới, thì cho quyền nhân viên quên pin
   để tạo pin mới luôn"*. Quyền ấy VỐN ĐÃ CÓ — `VHCC_QuenPin::tra()` chỉ hỏi họ tên + căn
   cước, không đòi PIN cũ. Bài này khoá cả luồng lại, vì nó đi qua BA lớp (hồ sơ tạm → quên
   PIN → đăng nhập trạm) và chỉ cần một lớp siết thêm một điều kiện là cả đường tắc mà không
   ai phát hiện cho tới khi có người mới vào làm. */
t( 'hồ sơ mới CHƯA có PIN', '' === trim( (string) $hs_moi['pin_dang_nhap'] ), $hs_moi );
t( 'nhưng CÓ căn cước — điều kiện để tự đặt PIN', '' !== trim( (string) $hs_moi['cccd'] ), $hs_moi );

$q = VHCC_QuenPin::tra( 'Trần Mới Vào', '000000000444' );
t( '🔴 người mới tự tra được ở màn "Quên PIN"', ! empty( $q['ok'] ), $q );
t( 'và tra ra đúng hồ sơ vừa tạo', $ma_moi === (string) $q['ma_nv'], $q );
$q2 = VHCC_QuenPin::tra( 'Tran Moi Vao', '000000000444' );
t( '⚠️ gõ tên KHÔNG DẤU vẫn tra ra — người ta gõ trên điện thoại', ! empty( $q2['ok'] ), $q2 );

$dp = VHCC_QuenPin::dat( $q['the'], '246813' );
t( '🔴 người mới tự đặt được PIN', ! empty( $dp['ok'] ), $dp );
$dn = VHCC_Tram::dang_nhap( '246813' );
t( '🔴 và đăng nhập trạm được bằng PIN ấy', ! empty( $dn['token'] ), $dn );
$u_moi = ! empty( $dn['token'] ) ? VHCC_Tram::nguoi( $dn['token'] ) : null;
t( 'vào đúng hồ sơ của mình', $u_moi && $ma_moi === (string) $u_moi['ma_nv'], $u_moi );

/* 🔴 MÀN QUẢN TRỊ PHẢI NÓI RA ĐƯỜNG ẤY. Trước 17/09 dòng nhắc chỉ kể hai cách CẤP TAY, nên
   cửa hàng trưởng đọc xong là đi cấp PIN hộ rồi đọc con số cho người ta qua điện thoại —
   trong khi đường tự đặt đã chạy được từ lâu. */
$ns = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-trang-ns.php' );
/* ⚠️ NEO VÀO ĐÚNG HÀM, KHÔNG DÒ CHỮ "chưa có PIN" KHẮP TỆP. Chuỗi ấy còn xuất hiện ở màn gộp
   hồ sơ và mấy chỗ khác; dò chữ thì `strpos` bắt được chỗ ĐẦU TIÊN, và bài kiểm đỏ oan trong
   khi mã đúng — đúng lượt chạy đầu của bài này đã vấp thế. */
$i_pin = strpos( $ns, 'private static function o_xem_pin' );
$khoi_pin = false !== $i_pin ? substr( $ns, $i_pin, 3000 ) : '';
t( 'tìm được hàm vẽ ô PIN', false !== $i_pin );
t( '🔴 dòng nhắc "chưa có PIN" chỉ đường tự đặt',
	false !== strpos( $khoi_pin, 'Quên PIN' ), $khoi_pin );
t( 'và nói rõ không ai phải đọc PIN của ai',
	false !== strpos( $khoi_pin, 'không ai phải đọc PIN của ai' ), $khoi_pin );
/* ⚠️ NHƯNG ĐƯỜNG ẤY CẦN CĂN CƯỚC. Hồ sơ chưa khai thì bảo "bấm Quên PIN" là chỉ tới cửa đóng. */
t( '⚠️ hồ sơ chưa khai căn cước thì nói thẳng là đường ấy đang tắc',
	false !== strpos( $khoi_pin, 'chưa khai căn cước' ), $khoi_pin );
t( 'vẫn giữ lối cấp tay làm đường lùi',
	false !== strpos( $khoi_pin, 'sửa ▾' ), $khoi_pin );

/* ⚠️ CĂN CƯỚC TRÙNG THÌ CHỐI — một người hai cơ sở là MỘT hồ sơ khai thêm cơ sở phụ, không
   phải hai hồ sơ. Hai hồ sơ là nhân đôi người ấy trên bảng lương. */
$r = VHCC_CuaHang::them_nguoi( $CHT_A, array(
	'hoTen' => 'Trùng Căn Cước', 'cccd' => '000000000444' ) );
t( '⚠️ căn cước trùng thì CHỐI', empty( $r['ok'] ), $r );
t( 'và chỉ ra người đang giữ số ấy',
	false !== mb_strpos( (string) $r['error'], 'Trần Mới Vào' ), $r );

/* 🔴 KHÔNG NHẬN chuc_vu TỪ MÀN TRẠM. Chức vụ tra ra ĐƠN GIÁ GIỜ, nên một chữ gõ vội là một
   dòng lương ra 0đ mà bảng vẫn đầy số. */
$i_tn = strpos( $src, 'function them_nguoi' );
t( '🔴 them_nguoi() KHÔNG chuyển tiếp chức vụ từ màn trạm',
	false !== $i_tn && false === strpos( substr( $src, $i_tn, 700 ), 'chuc_vu' ),
	substr( $src, $i_tn, 700 ) );

/* =============================================================== 6. CỬA TRẠM VÀ MÀN HÌNH */

$tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
foreach ( array( 'cuahang', 'chdon', 'chduyet', 'chcong', 'chthem' ) as $v ) {
	t( 'trạm có cửa ' . $v, false !== strpos( $tram, "'" . $v . "' === \$viec" ), $v );
}
$i_ch = strpos( $tram, "'cuahang' === \$viec" );
t( '🔴 cửa cuahang trả duoc:false cho người không có quyền',
	false !== strpos( substr( $tram, $i_ch, 400 ), "'duoc' => false" ), substr( $tram, $i_ch, 400 ) );
$i_th = strpos( $tram, "'chthem' === \$viec" );
t( '🔴 cửa chthem KHÔNG chuyển tiếp chức vụ',
	false !== $i_th && false === strpos( substr( $tram, $i_th, 500 ), 'chuc' ),
	substr( $tram, $i_th, 500 ) );

$tpl  = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
$than = substr( $tpl, 0, strpos( $tpl, '<style' ) ) . substr( $tpl, strpos( $tpl, '</style>' ) );

t( 'màn có tab Cửa hàng', false !== strpos( $than, 'id="tCuaHang"' ), $than );
/* 🔴 NÚT ẨN SẴN TRONG HTML, không chờ JS gỡ: chờ JS thì nhân viên thường thấy nút loé lên một
   nhịp, và cái loé ấy đủ để người ta bấm. */
t( '🔴 nút tab ẩn sẵn ngay trong HTML',
	false !== strpos( $than, 'class="tab-nut an" id="nutCH"' ), $than );
t( 'và chỉ mở khi máy chủ nói được',
	false !== strpos( $tpl, "if(!j || !j.ok || !j.duoc){ return; }" ), $tpl );
/* ⚠️ THANH NĂM Ô PHẢI THU CHỮ. `kiem-xin-phep.php` chốt thanh chỉ chịu được bốn ô: ô thứ năm
   làm chữ cụt ở cả bốn ô kia trên điện thoại hẹp. Ô này ẩn sẵn nên chốt ấy còn nguyên với
   nhân viên thường, nhưng cửa hàng trưởng thì có năm ô thật — nên lượt mở nút PHẢI kèm lớp
   thu chữ, và hai việc ấy phải nằm cùng một chỗ để không bao giờ có "năm ô mà chưa thu". */
t( '⚠️ mở ô thứ năm thì thu chữ cả thanh',
	false !== strpos( $tpl, "el('thanhTab').classList.add('tab5')" ), $tpl );
t( 'và có kiểu cho thanh năm ô', false !== strpos( $tpl, '#thanhTab.tab5 .tab-nut{' ), $tpl );
t( '🔴 thu CHỮ chứ không thu vùng chạm',
	false === strpos( $tpl, '#thanhTab.tab5 .tab-nut{font-size:10px;padding:9px 2px 8px;min-height' ), $tpl );

t( 'tab Cửa hàng nằm trong danh sách đổi tab',
	false !== strpos( $tpl, "'tChamCong','tCong','tCuaHang','tUng','tToi'" ), $tpl );
t( '🔴 hỏi quyền ngay lúc đăng nhập, không chờ bấm tab',
	false !== strpos( $tpl, 'doCuaHang();' ), $tpl );

foreach ( array( 'chCoSo', 'bangDonCH', 'bangCongCH' ) as $o ) {
	t( 'tab Cửa hàng có ô ' . $o, false !== strpos( $than, $o ), $o );
}

/* 🔴 THÊM NHÂN SỰ ĐÃ RA KHỎI TAB — anh Thắng 17/09/2026: *"Chuyển sang thêm nhân sự là 1 tính
   năng"*. Nó là việc dăm bữa một lần, còn tab Cửa hàng mở hằng ngày; để nó nằm cuối tab thì
   mỗi lần xem bảng công lại phải cuộn qua một biểu mẫu trống. Nay là một màn riêng, mở từ ô
   trong lưới Ứng dụng (`kiem-luoi-ung.php` canh cái ô ấy). */
$i_ch_mo = strpos( $than, 'id="tCuaHang"' );
$i_ch_het = strpos( $than, '/tCuaHang' );
$than_ch = substr( $than, $i_ch_mo, $i_ch_het - $i_ch_mo );
t( '🔴 biểu mẫu thêm nhân sự KHÔNG còn nằm trong tab Cửa hàng',
	false === strpos( $than_ch, 'btThemNguoi' ), $than_ch );
t( 'nó nằm ở màn riêng', false !== strpos( $than, '<div id="mThemNv" class="mn an">' ), $than );
foreach ( array( 'tnCoSo', 'tnTen', 'tnCccd', 'btThemNguoi', 'btDongThem' ) as $o ) {
	t( 'màn thêm nhân sự có ô ' . $o, false !== strpos( $than, $o ), $o );
}
/* ⚠️ MÀN RIÊNG PHẢI CÓ Ô CHỌN CƠ SỞ CỦA CHÍNH NÓ. Trước đây nó mượn ô của tab Cửa hàng; mở từ
   lưới thì không còn ô ấy, mà đoán bừa cơ sở là thêm người vào nhầm cửa hàng. */
t( '🔴 gửi đi kèm cơ sở chọn TRÊN CHÍNH MÀN ẤY',
	false !== strpos( $tpl, "coSo:     el('tnCoSo').value" ), $tpl );
t( 'và ô ấy nạp từ danh sách cơ sở máy chủ đã lọc',
	false !== strpos( $tpl, "xoOption(ds, ds[0] || '')" ) && false !== strpos( $tpl, 'CH.dsCoSo' ), $tpl );
t( '⚠️ chưa có cơ sở nào thì nói ra, không mở màn rỗng',
	false !== strpos( $tpl, 'chưa được giao cơ sở nào, nên chưa thêm người được' ), $tpl );
/* ⚠️ "KHÔNG DUYỆT" phải hỏi lại — bấm nhầm thì người xin nhận câu từ chối mà không ai cố ý gửi. */
t( '⚠️ bấm KHÔNG DUYỆT thì hỏi lại', false !== strpos( $tpl, 'Ghi KHÔNG DUYỆT đơn này?' ), $tpl );
/* Khoá đơn ghép loại + id: ba loại đánh số riêng nhau, chỉ mang id là duyệt nhầm đơn khác. */
t( '🔴 khoá đơn ghép LOẠI + ID', false !== strpos( $tpl, "esc(d.loai) + '|' + esc(d.id)" ), $tpl );
t( 'nạp lại cả hộp sau mỗi lượt quyết, không xoá một thẻ trên màn',
	false !== strpos( $tpl, 'napDonCH();' ), $tpl );
/* Màn phải nói ra rằng hệ KHÔNG phát PIN — không thì trưởng đứng chờ một cái PIN không bao giờ tới. */
t( '🔴 màn nói rõ người mới tự lấy PIN ở màn "Quên PIN"',
	false !== strpos( $tpl, 'Quên PIN' ), $tpl );

/* ── 6b. MÀN MỘT NGƯỜI (sửa công & chốt lương) ──────────────────────────────────────────── */

foreach ( array( 'chngay', 'chsua', 'chxoadong', 'chchot', 'chchotluu', 'chnhansu' ) as $v ) {
	t( 'trạm có cửa ' . $v, false !== strpos( $tram, "'" . $v . "' === \$viec" ), $v );
}
/* ⚠️ `gioCham` TÍNH LẠI Ở MÁY CHỦ. Cửa không được chuyển tiếp trần giờ từ biểu mẫu — nhận từ
   màn là trần tự khai, tức bỏ luôn phép chặn "giờ khác không vượt giờ chấm". */
$i_cl = strpos( $tram, "'chchotluu' === \$viec" );
t( '🔴 cửa chchotluu KHÔNG nhận trần giờ từ biểu mẫu',
	false !== $i_cl && false === strpos( substr( $tram, $i_cl, 900 ), 'gioCham' ),
	substr( $tram, $i_cl, 900 ) );

t( 'màn một người dựng theo khuôn màn phủ toàn trang',
	false !== strpos( $than, '<div id="mNguoi" class="mn an">' ), $than );
foreach ( array( 'dsNgay', 'oSuaNgay', 'sgVao', 'sgRa', 'sgGay', 'sgLyDo', 'btLuuGio',
	'btXoaGio', 'oChot', 'clViec', 'dsDongGio', 'clThang', 'btLuuChot', 'btDongNguoi' ) as $o ) {
	t( 'màn một người có ô ' . $o, false !== strpos( $than, $o ), $o );
}
/* 🔴 CHẠM CẢ DÒNG, KHÔNG PHẢI NÚT Ở CỘT THỨ TƯ. Anh Thắng 17/09/2026 chụp màn iPhone: *"trên
   điện thoại không có nút mở"* — bốn cột trên màn 390px thì cột cuối bị bóp còn vài pixel và
   cái nút biến mất khỏi tầm mắt. Phép thử chốt cả hai vế: dòng bấm được, VÀ bảng chỉ còn ba
   cột (thêm cột thứ tư là lỗi cũ quay lại). */
t( 'mở màn bằng cách chạm cả dòng',
	false !== strpos( $tpl, "moNguoi(this.getAttribute('data-ma'))" )
	&& false !== strpos( $tpl, "querySelectorAll('.hang-mo')" ), $tpl );
t( '🔴 bảng công cơ sở chỉ còn BA cột',
	false !== strpos( $tpl, '<th>Nhân viên</th><th>Ngày</th><th>Giờ</th></tr>' ), $tpl );
/* Cả HAI bảng trên trạm đều phải bỏ cột chứa nút — bảng công cơ sở và bảng ngày trong màn một
   người. Bảng thứ hai vốn năm cột, còn chật hơn. */
t( '🔴 bảng ngày trong màn một người cũng bỏ cột nút',
	false !== strpos( $tpl, '<th>Ngày</th><th>Vào</th><th>Ra</th><th>Giờ</th></tr>' ), $tpl );
t( '🔴 KHÔNG bảng nào còn cột rỗng chứa nút',
	false === strpos( $tpl, '<th>Giờ</th><th></th>' ), $tpl );
t( 'dòng ngày cũng chạm cả dòng', false !== strpos( $tpl, "querySelectorAll('.ng-sua')" ), $tpl );
/* ⚠️ Không có quyền sửa thì dòng KHÔNG được trông như bấm được. */
t( '⚠️ dòng chỉ-để-xem bị gỡ mũi › và gỡ lớp bấm',
	false !== strpos( $tpl, "replace(/ class=\"ng-sua/g" ), $tpl );
/* ⚠️ Dòng bấm được mà trông y hệt dòng chữ thì không ai thử bấm. */
t( '⚠️ có dấu hiệu nhìn thấy được rằng dòng bấm được',
	false !== strpos( $tpl, 'class="mui"' ) && false !== strpos( $tpl, 'tr.hang-mo{cursor:pointer}' ), $tpl );
t( 'và có dòng nhắc chạm vào', false !== strpos( $tpl, 'Chạm vào một dòng để' ), $tpl );

/* ── HẾT 24H: câu nhắc ở ĐẦU tab, và dòng ngày cũ nhìn là biết khoá ───────────────────────
   Anh Thắng 17/09/2026: *"Nên chỗ đầu cửa hàng. Thông báo nội dung hết 24h hôm này không cho
   phép sửa giờ công. Vui lòng liên hệ kế toán"*. */
$i_tab = strpos( $than, 'id="tCuaHang"' );
$i_nhac = strpos( $than, 'id="nhacHan"' );
$i_cs_o = strpos( $than, 'id="chCoSo"' );
t( '🔴 ô nhắc nằm ở ĐẦU tab, trước cả ô chọn cơ sở',
	false !== $i_tab && false !== $i_nhac && $i_tab < $i_nhac && $i_nhac < $i_cs_o,
	array( $i_tab, $i_nhac, $i_cs_o ) );
/* 🔴 CÂU CHỮ DO MÁY CHỦ ĐƯA, không chép cứng vào màn — bày một câu mà luật không làm đúng
   vậy là nói dối người dùng. */
t( '🔴 câu nhắc lấy từ máy chủ (j.nhacHan), không chép cứng',
	false !== strpos( $tpl, 'if(j.nhacHan)' ) && false !== strpos( $tpl, 'esc(j.nhacHan)' ), $tpl );
t( 'và màn KHÔNG tự chế câu ấy',
	false === strpos( $than, 'hết 24h hôm nay thì không' ), $than );
/* Dòng của ngày đã qua: mất lớp bấm, mất mũi ›, và mờ đi. Cho mở rồi mới chối lúc bấm Lưu là
   bắt người ta gõ cả giờ lẫn lý do cho một lượt không bao giờ đi được. */
t( '🔴 dòng ngày đã qua KHÔNG mở được ô sửa',
	false !== strpos( $tpl, "(mo ? 'ng-sua' : 'ng-khoa')" ), $tpl );
t( 'và nhìn là biết bị khoá', false !== strpos( $tpl, 'tr.ng-khoa{opacity:' ), $tpl );
t( 'ngày còn sửa được do MÁY CHỦ nói, không do trình duyệt tự tính',
	false !== strpos( $tpl, 'NG.khoaNgayCu' ) && false !== strpos( $tpl, 'NG.homNay' ), $tpl );
t( '⚠️ vùng chạm cả dòng đủ cao', false !== strpos( $tpl, 'tr.hang-mo td{padding-top:12px' ), $tpl );
/* 🔴 ĐỔ GIỜ ĐANG CÓ VÀO Ô — không đổ thì người sửa phải NHỚ giờ cũ, mà nhớ sai một chữ số là
   ghi đè mất một giờ công thật. */
t( '🔴 ô sửa đổ sẵn giờ đang có', false !== strpos( $tpl, "el('sgVao').value = x.vao" ), $tpl );
/* ⚠️ XOÁ GIỜ PHẢI HỎI LẠI. */
t( '⚠️ bấm xoá giờ thì hỏi lại',
	false !== strpos( $tpl, 'Xoá giờ vào và giờ ra của ngày này?' ), $tpl );

/* 🔴 XOÁ HẲN DÒNG: HỎI BẰNG NGÀY VÀ TÊN, không phải một câu "chắc chưa?". Hộp thoại chung
   chung thì ngón tay bấm qua được mà mắt chưa kịp đọc — và cái vừa bấm qua là một dòng chấm
   công không dựng lại được. */
t( 'có nút xoá hẳn dòng công', false !== strpos( $than, 'btXoaDong' ), $than );
t( '🔴 câu hỏi nhắc đúng NGÀY và TÊN người',
	false !== strpos( $tpl, "ngayGon(NG_NGAY) + ' của '" )
	&& false !== strpos( $tpl, 'NG.hoTen' ), $tpl );
t( '🔴 và nói ra hậu quả: lưới sẽ trông như hôm ấy không đi làm',
	false !== strpos( $tpl, 'tưởng hôm ấy không đi làm' ), $tpl );
/* Bắt gõ lý do TRƯỚC khi hiện hộp thoại — hỏi xong mới báo thiếu lý do là bắt người ta quyết
   định hai lần cho một việc. */
t( 'đòi lý do trước khi hỏi xác nhận',
	false !== strpos( $tpl, "el('sgLyDo').value.trim().length < 5" ), $tpl );
/* Nút phải KHÁC HẲN mọi nút khác khi nhìn lướt. */
t( 'nút xoá hẳn mang kiểu nút phá', false !== strpos( $than, 'class="nguy to"' ), $than );
t( 'và có định nghĩa kiểu ấy', false !== strpos( $tpl, 'button.nguy{' ), $tpl );
/* Màn phải nói rõ HAI nút xoá khác nhau chỗ nào — hai nút cạnh nhau cùng chữ "xoá" là chỗ
   bấm nhầm rất dễ, mà một trong hai thì không dựng lại được. */
t( '🔴 màn phân biệt rõ hai nút xoá',
	false !== strpos( $tpl, '<b>Xoá giờ ngày này</b> — dòng còn' )
	&& false !== strpos( $tpl, '<b>Xoá hẳn dòng công</b> — dòng biến mất' ), $tpl );
/* Màn nói rõ ô trống là GIỮ NGUYÊN — đây là chỗ dễ mất giờ công nhất. */
t( '🔴 màn nói rõ ô trống là GIỮ NGUYÊN, không phải xoá',
	false !== strpos( $tpl, 'nghĩa là\n\t\t\t<b>giữ nguyên</b>' )
	|| false !== strpos( $tpl, '<b>giữ nguyên</b>' ), $tpl );
/* Con số "còn lại" cản trước ở màn — nhưng máy chủ vẫn là nơi chối. */
t( 'màn cộng sẵn giờ khác và nói còn bao nhiêu', false !== strpos( $tpl, 'function tomChot(' ), $tpl );
t( 'và cảnh báo khi vượt giờ chấm công',
	false !== strpos( $tpl, 'vượt giờ chấm công' ), $tpl );
/* Sửa giờ xong phải nạp lại: tổng giờ tháng là TRẦN của khối chốt lương ngay dưới. */
t( '🔴 sửa giờ xong thì nạp lại cả màn, không vá một ô',
	false !== strpos( $tpl, 'moNguoi(ma); napCongCH();' ), $tpl );

/* =============================================================== dọn */

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE coso IN ('$CS_A','$CS_B')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'xin_tre' ) . " WHERE ma_nv IN ('CH001','CH002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'xin_nghi' ) . " WHERE ma_nv IN ('CH001','CH002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE cua_hang IN ('$CS_A','$CS_B')" );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — cửa hàng trưởng làm được ba việc trên điện thoại, và CHỈ trong"
	. " cơ sở mình phụ trách.\n";
