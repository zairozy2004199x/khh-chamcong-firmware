<?php
/**
 * KIỂM MÀN XIN PHÉP TRÊN TRẠM — đơn đi trễ và đơn đổi lịch nộp từ điện thoại.
 *
 * 🔴 VÌ SAO KIỂM RIÊNG. Trạm là cửa THỨ HAI vào hai bộ nghiệp vụ vốn chỉ có một cửa (trang quản
 *    trị). Cửa mới mở ra cho NHÂN VIÊN THƯỜNG, tức bậc quyền thấp nhất trong hệ — nên hai câu
 *    hỏi phải trả lời được bằng phép thử chứ không bằng lời hứa:
 *
 *      1. Người nộp có nộp được đơn ĐỨNG TÊN NGƯỜI KHÁC không?
 *      2. Người nộp có đọc được đơn của NGƯỜI KHÁC không?
 *
 *    Câu 1 nặng hơn vẻ ngoài: đơn đi trễ được duyệt thì CẢNH BÁO CHẤM THIẾU GIỜ của người đó
 *    biến mất. Nộp được đơn hộ người khác nghĩa là xoá được cảnh báo của người khác.
 *
 * Chạy: php tools/test/kiem-xin-phep.php
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

$CS_A = 'XP_SHOP_A';
$CS_B = 'XP_SHOP_B';

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'XP001', 'ho_ten' => 'Nguyễn Văn Nộp', 'cua_hang' => $CS_A,
	'pin_dang_nhap' => '901234', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'XP002', 'ho_ten' => 'Trần Thị Khác', 'cua_hang' => $CS_B,
	'pin_dang_nhap' => '905678', 'trang_thai_lam_viec' => 'Đang làm' ) );

$dn_a = VHCC_Tram::dang_nhap( '901234' );
$dn_b = VHCC_Tram::dang_nhap( '905678' );
$A = VHCC_Tram::nguoi( $dn_a['token'] );
$B = VHCC_Tram::nguoi( $dn_b['token'] );
t( 'hai người đăng nhập được vào trạm', is_array( $A ) && is_array( $B )
	&& 'XP001' === $A['ma_nv'] && 'XP002' === $B['ma_nv'], array( $A, $B ) );

$hom_nay = current_time( 'Y-m-d' );
$mai     = gmdate( 'Y-m-d', strtotime( $hom_nay . ' +1 day' ) );

/* =============================================================== 1. ĐƠN ĐI TRỄ */

$r = VHCC_XinTre::nop( $A, array( 'ngay' => $mai, 'so_phut' => 20, 'ly_do' => 'Kẹt xe cầu Sài Gòn' ) );
t( 'nhân viên thường nộp được đơn đi trễ', ! empty( $r['ok'] ), $r );
t( 'đơn gửi về đúng cơ sở của người nộp', isset( $r['coSo'] ) && $CS_A === $r['coSo'], $r );

/* 🔴 MÃ NV LẤY TỪ THẺ PHIÊN, KHÔNG TỪ BIỂU MẪU. Đây là chốt quan trọng nhất của cả bài: đơn
   được duyệt thì cảnh báo chấm thiếu giờ của người ấy biến mất, nên nộp hộ được là xoá được
   cảnh báo của người khác. `nop()` cố ý không đọc `ma_nv` từ `$dat` — canh lại ở đây. */
$r = VHCC_XinTre::nop( $A, array( 'ngay' => $mai, 'so_phut' => 30,
	'ly_do' => 'thử nộp hộ', 'ma_nv' => 'XP002' ) );
$cua_b = VHCC_XinTre::cua_nguoi( 'XP002', 20 );
t( '🔴 khai ma_nv trong biểu mẫu KHÔNG đẻ ra đơn cho người khác', 0 === count( $cua_b ), $cua_b );
$cua_a = VHCC_XinTre::cua_nguoi( 'XP001', 20 );
t( 'lượt ấy vẫn ghi vào chính người nộp', 1 === count( $cua_a ), $cua_a );

/* ⚠️ MỘT NGƯỜI MỘT NGÀY MỘT ĐƠN — nộp lại là ĐÈ, không xếp thêm. */
t( 'nộp lại cùng ngày thì đè lên đơn cũ, không đẻ đơn thứ hai', 1 === count( $cua_a ), $cua_a );
t( 'và số phút là của lượt mới nhất', 30 === (int) $cua_a[0]['so_phut'], $cua_a[0] );

/* 🔴 ĐƠN ĐÃ DUYỆT MÀ NỘP LẠI THÌ VỀ CHỜ DUYỆT. Màn trạm phải nói câu đó ra, không thì người
   ta vô tình huỷ mất đơn cửa hàng trưởng vừa duyệt — xem chuỗi báo của `btGuiTre`. */
$wpdb->update( VHCC_DB::t( 'xin_tre' ), array( 'trang_thai' => VHCC_XinTre::DUYET ),
	array( 'id' => (int) $cua_a[0]['id'] ) );
$r = VHCC_XinTre::nop( $A, array( 'ngay' => $mai, 'so_phut' => 15, 'ly_do' => 'đổi ý' ) );
t( 'nộp lại báo rõ là đè lên đơn cũ', ! empty( $r['lai'] ), $r );
$cua_a = VHCC_XinTre::cua_nguoi( 'XP001', 20 );
t( '🔴 và đơn quay về CHỜ DUYỆT', VHCC_XinTre::CHO === (string) $cua_a[0]['trang_thai'], $cua_a[0] );

/* Ô lý do trống thì chối — cửa hàng trưởng duyệt theo lý do, không duyệt theo số phút. */
$r = VHCC_XinTre::nop( $A, array( 'ngay' => $mai, 'so_phut' => 10, 'ly_do' => '' ) );
t( 'chối đơn không có lý do', empty( $r['ok'] ), $r );
$r = VHCC_XinTre::nop( $A, array( 'ngay' => $mai, 'so_phut' => 0, 'ly_do' => 'x' ) );
t( 'chối xin trễ 0 phút', empty( $r['ok'] ), $r );
$r = VHCC_XinTre::nop( $A, array( 'ngay' => $mai, 'so_phut' => VHCC_Tre::TOI_DA + 1, 'ly_do' => 'x' ) );
t( 'chối quá trần ' . VHCC_Tre::TOI_DA . ' phút', empty( $r['ok'] ), $r );

/* =============================================================== 2. ĐỌC ĐƠN CỦA CHÍNH MÌNH */

$r = VHCC_XinTre::nop( $B, array( 'ngay' => $mai, 'so_phut' => 45, 'ly_do' => 'đơn của B' ) );
t( 'B nộp được đơn của B', ! empty( $r['ok'] ), $r );
$cua_a = VHCC_XinTre::cua_nguoi( 'XP001', 20 );
foreach ( $cua_a as $x ) {
	t( '🔴 danh sách của A không lẫn đơn của người khác', 'XP001' === (string) $x['ma_nv'], $x );
}

/* =============================================================== 3. ĐƠN ĐỔI LỊCH */

/* Cơ sở chưa bật phân lịch -> không có lịch nào để đổi. Trạm phải nói ra câu đó chứ không bày
   một biểu mẫu gửi đi rồi nằm đó không ai duyệt. */
t( 'cơ sở chưa bật lịch -> co_bat_lich false', ! VHCC_Lich::co_bat_lich( $CS_A ) );

$admin = array( 'name' => 'Sếp', 'role' => 'ADMIN', 'coso' => '' );
VHCC_Lich::dat_coso_bat_lich( $admin, array( $CS_A ) );
t( 'bật lịch cho cơ sở A', VHCC_Lich::co_bat_lich( $CS_A ) );
t( '🔴 bật A KHÔNG kéo theo B', ! VHCC_Lich::co_bat_lich( $CS_B ) );
t( 'tra tên cơ sở không phân biệt hoa thường', VHCC_Lich::co_bat_lich( strtolower( $CS_A ) ) );

$r = VHCC_Lich::xin_doi_lich( $A, array( 'coso' => $CS_A, 'ma_nv' => 'XP001',
	'ho_ten' => 'Nguyễn Văn Nộp', 'ngay' => $mai, 'ca' => 'Sáng',
	'viec_moi' => '', 'ly_do' => 'Việc nhà' ) );
t( 'nhân viên thường nộp được yêu cầu đổi lịch', ! empty( $r['ok'] ), $r );
t( 'có mã yêu cầu để tra lại', ! empty( $r['maYc'] ), $r );

$ds = VHCC_Lich::cua_nguoi( 'XP001', 20 );
t( 'đọc lại được yêu cầu của chính mình', 1 === count( $ds ), $ds );
t( 'và trạng thái là Chờ duyệt', VHCC_Lich::CHO_DUYET === (string) $ds[0]['trang_thai'], $ds[0] );

/* 🔴 `ds_doi_lich()` KHÔNG THAY ĐƯỢC `cua_nguoi()`. Hàm ấy lọc theo cơ sở người xem PHỤ TRÁCH;
   nhân viên thường không phụ trách cơ sở nào, nên gọi nó từ trạm là trả về rỗng dù vừa nộp
   xong — và "nộp xong không thấy đâu" chính là thứ khiến người ta nộp lại lần hai, lần ba. */
$qua_ds = VHCC_Lich::ds_doi_lich( $A, false );
t( '🔴 ds_doi_lich rỗng với nhân viên thường (nên trạm phải dùng cua_nguoi)',
	0 === count( $qua_ds ), $qua_ds );

$ds_b = VHCC_Lich::cua_nguoi( 'XP002', 20 );
t( '🔴 yêu cầu của A không lọt sang danh sách của B', 0 === count( $ds_b ), $ds_b );

$r = VHCC_Lich::xin_doi_lich( $A, array( 'coso' => $CS_A, 'ma_nv' => '',
	'ngay' => $mai, 'ly_do' => 'x' ) );
t( 'chối yêu cầu không có mã NV', empty( $r['ok'] ), $r );
$r = VHCC_Lich::xin_doi_lich( $A, array( 'coso' => $CS_A, 'ma_nv' => 'XP001',
	'ngay' => 'hôm nào đó', 'ly_do' => 'x' ) );
t( 'chối ngày không đúng khuôn', empty( $r['ok'] ), $r );

/* =============================================================== 4. DÂY NỐI Ở CỬA TRẠM */

/* Ba chốt dưới đây soi THẲNG mã nguồn của cửa, vì `VHCC_Tram::cong()` kết thúc bằng `exit` nên
   không gọi thẳng trong bài kiểm được. Soi mã là cách duy nhất còn lại để canh rằng cửa không
   chuyển tiếp `ma_nv` do trình duyệt gửi lên. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );

t( 'cửa xintre có thật', false !== strpos( $src, "'xintre' === \$viec" ) );
t( 'cửa xinlich có thật', false !== strpos( $src, "'xinlich' === \$viec" ) );
t( 'cửa donxin có thật', false !== strpos( $src, "'donxin' === \$viec" ) );

/* Cắt đúng khối `xinlich` ra để soi — soi cả tệp thì `ma_nv` xuất hiện khắp nơi và phép thử
   không nói được gì. */
$i = strpos( $src, "'xinlich' === \$viec" );
$j = strpos( $src, "'donxin' === \$viec" );
$khoi = ( false !== $i && false !== $j && $j > $i ) ? substr( $src, $i, $j - $i ) : '';
t( 'cắt được khối xinlich để soi', '' !== $khoi );
t( "🔴 xinlich lấy mã NV từ PHIÊN (\$u['ma_nv'])",
	false !== strpos( $khoi, "'ma_nv'         => \$u['ma_nv']" ), $khoi );
t( "🔴 xinlich KHÔNG đọc ma_nv từ thân yêu cầu (\$b['ma_nv'])",
	false === strpos( $khoi, "\$b['ma_nv']" ), $khoi );
t( '🔴 xinlich đối chiếu cơ sở với danh sách người đó thật sự có',
	false !== strpos( $khoi, 'ds_coso_cham_cua_nv' ), $khoi );
t( 'xinlich chối cơ sở chưa bật lịch', false !== strpos( $khoi, 'co_bat_lich' ), $khoi );

/* Ba cửa mới nằm SAU chốt phiên — đặt trước chốt là ai cũng nộp đơn được mà không cần PIN. */
$chot = strpos( $src, "'het_phien'" );
t( '🔴 cửa xintre nằm SAU chốt thẻ phiên', $chot !== false && strpos( $src, "'xintre' === \$viec" ) > $chot );
t( '🔴 cửa xinlich nằm SAU chốt thẻ phiên', $chot !== false && strpos( $src, "'xinlich' === \$viec" ) > $chot );
t( '🔴 cửa donxin nằm SAU chốt thẻ phiên', $chot !== false && strpos( $src, "'donxin' === \$viec" ) > $chot );

/* Và cửa KHÔNG viết lại nghiệp vụ: nó phải gọi đúng hai hàm mà trang quản trị gọi. */
t( 'xintre chuyển thẳng xuống VHCC_XinTre::nop', false !== strpos( $src, 'VHCC_XinTre::nop( $u,' ) );
t( 'xinlich chuyển thẳng xuống VHCC_Lich::xin_doi_lich',
	false !== strpos( $src, 'VHCC_Lich::xin_doi_lich( $u,' ) );

/* =============================================================== 5. MÀN TRÊN ĐIỆN THOẠI */

$tpl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
/* 🔴 XIN PHÉP ĐI TRỄ NAY LÀ MỘT **TÍNH NĂNG RIÊNG**, KHÔNG CÒN NẰM TRONG TAB "TÔI".
   Anh Thắng 17/09/2026, khoanh đúng khối ấy ở tab Tôi: *"Gửi đơn đi trễ là 1 tính năng"*.
   Cùng một lối với Thêm nhân sự và Phiếu lương: việc thỉnh thoảng mới làm thì đừng nằm giữa
   một trang cuộn dài. Nay nó là màn `mXinTre`, mở từ một ô ở tab Ứng dụng.

   ⚠️ MỘT TÍNH NĂNG RỜI CÓ HAI CÁCH HỎNG IM LẶNG, VÀ PHẢI CANH CẢ HAI:
     · có màn mà KHÔNG có ô nào mở nó — tính năng tồn tại mà không ai tới được;
     · có ô mà ô ấy không NẠP dữ liệu khi mở — màn hiện "Đang tải…" vĩnh viễn.
   Phép cũ soi thứ tự `tToi … Xin phép … /tToi`, tức đo đúng cái chỗ ở đã bỏ. */
$i_xin = strpos( $tpl, '<div id="mXinTre"' );
$i_toi = strpos( $tpl, 'id="tToi"' );
$i_het = strpos( $tpl, '<!-- /tToi -->' );
t( '🔴 xin phép đi trễ là MÀN RIÊNG (#mXinTre)', false !== $i_xin, $i_xin );
t( 'và đã ra HẲN khỏi tab "Tôi", không còn hai bản',
	false !== $i_toi && false !== $i_het && ! ( $i_toi < $i_xin && $i_xin < $i_het ),
	array( $i_toi, $i_xin, $i_het ) );
/* Có ô mở nó ở tab Ứng dụng, và ô ấy do MÁY CHỦ dựng — không gõ tay tên màn ở hai nơi. */
$ung_src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-ung.php' );
t( '🔴 máy chủ có dựng ô "Gửi đơn đi trễ" trỏ đúng màn ấy',
	false !== strpos( $ung_src, "'man'  => 'mXinTre'" ), null );
t( 'và ô ấy KHÔNG bị gác quyền — ai cũng nộp đơn cho mình được',
	false !== strpos( $ung_src, "self::o( true, array(\n\t\t\t'ten'  => 'Gửi đơn đi trễ'" )
	|| 1 === preg_match( "/self::o\(\s*true,\s*array\(\s*'ten'\s*=>\s*'Gửi đơn đi trễ'/", $ung_src ),
	null );
/* Bấm ô là mở màn VÀ nạp — `moMan()` phải biết tên màn này, kẻo ô bấm ra một màn trống. */
t( '🔴 bấm ô thì mở đúng màn và gọi nạp',
	false !== strpos( $tpl, "if('mXinTre' === ten){ moXinTre(); }" )
	&& false !== strpos( $tpl, 'function moXinTre()' ), null );
t( 'và màn tự nạp dữ liệu lúc mở, không hiện "Đang tải…" mãi',
	1 === preg_match( '/function moXinTre\(\)\{[^}]*napXin\(\)/s', $tpl ), null );
t( '🔴 mở tab "Tôi" thì nạp luôn danh sách đơn',
	false !== strpos( $tpl, "if(ten === 'tToi'){ napHoSo(); moManXin(); }" ) );
t( 'không còn màn riêng bật ra nữa', false === strpos( $tpl, 'id="mXin"' ) );
/* Thanh tab dưới đáy giữ ĐÚNG bốn ô MẶC ĐỊNH. Ô thứ năm là chữ bị cắt cụt ở cả bốn ô kia trên
   điện thoại hẹp — và đó là lý do khối này nằm trong tab "Tôi" thay vì đứng riêng.
   ⚠️ 17/09/2026: có thêm ô "Cửa hàng", nhưng nó ẩn sẵn và CHỈ cửa hàng trưởng mới thấy. Phép
      thử vì thế đếm ô MẶC ĐỊNH HIỆN (`class="tab-nut"` không kèm `an`) chứ không đếm tổng số
      nút — nới thành "đếm tổng" là bỏ mất chính cái ràng buộc này, còn giữ nguyên "tổng = 4"
      là cấm luôn mọi ô chỉ dành cho một vai. Ô ẩn thì có phép thử riêng ở `kiem-cua-hang.php`
      canh rằng nó ẩn sẵn và thanh thu chữ lại khi nó hiện ra. */
t( 'thanh tab vẫn đúng bốn ô MẶC ĐỊNH',
	4 === substr_count( $tpl, '<button class="tab-nut"' )
		+ substr_count( $tpl, '<button class="tab-nut dang"' ),
	substr_count( $tpl, '<button class="tab-nut' ) );
t( 'có khối đơn đã nộp', false !== strpos( $tpl, 'id="bangDon"' ) );
/* 🔴 NGÀY MẶC ĐỊNH LẤY TỪ MÁY CHỦ. Lấy `new Date()` của điện thoại thì máy lệch múi giờ hoặc
   chạy sau nửa đêm là đơn rơi vào ngày hôm qua — cửa hàng trưởng nhận một đơn xin trễ cho một
   ngày đã xong, và người nộp thì tưởng đã xin cho hôm nay. Cùng một luật với ràng buộc 1. */
t( '🔴 ngày mặc định lấy từ máy chủ (j.homNay), không từ điện thoại',
	false !== strpos( $tpl, "el('xtNgay').value = j.homNay" ) );
t( 'có cờ khoá nút chống bấm nhiều lần', false !== strpos( $tpl, 'DANG_GUI' ) );
t( 'nạp lại danh sách đơn sau mỗi lượt gửi', false !== strpos( $tpl, 'return napXin();' ) );

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'xin_tre' ) . " WHERE ma_nv IN ('XP001','XP002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'doi_lich_cv' ) . " WHERE ma_nv IN ('XP001','XP002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv IN ('XP001','XP002')" );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — trạm nộp được đơn, và chỉ nộp được đơn ĐỨNG TÊN CHÍNH MÌNH.\n";
