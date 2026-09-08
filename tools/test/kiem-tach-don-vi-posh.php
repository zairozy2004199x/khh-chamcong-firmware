<?php
/**
 * HAI BÊN DÙNG CHUNG MỘT WEB CHI PHÍ MÀ KHÔNG NHÌN THẤY SỐ CỦA NHAU.
 *
 * Anh Thắng 08/09/2026:
 *   *"giờ bổ sung thêm chi phí mảng thứ 2 là posh"*
 *   *"dùng chung wed chi phí, nhưng 2 bộ phận không nhìn thấy nhau, kế toán bên mảng [posh]
 *   sẽ nhìn thấy mảng của mình thôi"*
 *   *"danh mục cơ sở nhập vẫn trường thông tin đó luôn, cơ sở lấy từ bên posh là các cơ sở
 *   posh đang hoạt động"*
 *
 * =============================================================================================
 * 🔴 VÌ SAO CẦN BÀI NÀY, TRONG KHI ĐƠN VỊ ĐÃ CÓ TỪ 26/08.
 *
 *    Có, nhưng CHỈ Ở MẢNG ĐƠN VẬN HÀNH. Bốn mảng còn lại — Sổ chi phí · Kỹ thuật · Marketing ·
 *    Công tác/Setup — nằm ở bốn cặp bảng riêng, không cột nào nói của bên nào, và không hàm
 *    nào lọc. Nghĩa là kế toán POSH mở tab Sổ chi phí ra là thấy nguyên sổ của K&H, và ngược
 *    lại. Không có câu lỗi nào, không ai biết để báo — số cứ nằm đó cho tới khi có người đọc.
 *
 * ⚠️ CHẠY THẬT, KHÔNG SOI CHUỖI. "Có gọi VHCP_DonVi ở dòng 180" không nói được là dòng ấy có
 *    CHẶN được gì không. Bài này khai hai cơ sở của hai bên, đổ dữ liệu vào cả bốn mảng, rồi
 *    ĐĂNG NHẬP làm từng người và đòi đúng những gì họ được thấy.
 *
 * Chạy: php tools/test/kiem-tach-don-vi-posh.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-08 09:00:00' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}
global $wpdb;

foreach ( array( 'don', 'chiphi', 'so_chi', 'mk_don', 'da_index', 'bp_index' ) as $b ) {
	$thieu = vhcp_test_bang_thieu( $b );
	t( 'bảng ' . $b . ' đã dựng được (không thì mọi phép dưới xanh oan)', ! $thieu, $thieu );
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * DỰNG SÂN: hai cơ sở của hai bên + ba người
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
const CS_KH   = 'TÀU ESTELLA';
const CS_POSH = 'POSH SÀI GÒN';
const CS_LA   = 'GIAN CHƯA KHAI';   // có trên dữ liệu, không có trong danh mục

VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	/* cột: cơ sở · mã đơn vị MISA · phân loại lớn · tên MISA · đóng cửa · ĐƠN VỊ */
	array( CS_KH,   'FLMPT', 'FARM MN', '', '', '' ),        // ô đơn vị TRỐNG = K&H
	array( CS_POSH, 'PSHCM', 'POSH',    '', '', 'POSH' ),
) );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	/* cột: tên · pin · vai · cơ sở · tk có · mã đt · bộ phận · ĐƠN VỊ · XEM ĐƠN VỊ */
	array( 'KT K&H',  '111111', 'Kế toán cá nhân', '', '', '', '', 'K&H',  'K&H' ),
	array( 'KT POSH', '222222', 'Kế toán cá nhân', '', '', '', '', 'POSH', 'POSH' ),
	array( 'Sếp',     '333333', 'Admin',           '', '', '', '', 'K&H',  '' ),   // trống = xem cả
) );

function lam( $ten, $vai = 'Kế toán cá nhân' ) { VHCP_Auth::dat_vai_tro( $vai, $ten ); }

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. CHỐT GỐC: CƠ SỞ NÀY CỦA BÊN NÀO
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'cơ sở khai POSH -> POSH',      'POSH', VHCP_DonVi::cua_coso( CS_POSH ) );
teq( 'ô đơn vị để TRỐNG -> nhà mặc định', VHCP_DonVi::MAC_DINH, VHCP_DonVi::cua_coso( CS_KH ) );
teq( 'không phân biệt hoa thường',   'POSH', VHCP_DonVi::cua_coso( mb_strtolower( CS_POSH ) ) );
teq( 'thừa khoảng trắng vẫn tra được', 'POSH', VHCP_DonVi::cua_coso( '  ' . CS_POSH . ' ' ) );
/* 🔴 Cơ sở LẠ phải rơi về nhà mặc định, KHÔNG phải "không của ai": trả một đơn vị không ai
   xem được thì dòng tiền ấy biến mất khỏi mọi màn, kể cả màn của Admin. */
teq( 'cơ sở lạ (chưa khai) -> nhà mặc định', VHCP_DonVi::MAC_DINH, VHCP_DonVi::cua_coso( CS_LA ) );
teq( 'cơ sở rỗng -> nhà mặc định',   VHCP_DonVi::MAC_DINH, VHCP_DonVi::cua_coso( '' ) );

/* 🔴 CỘT PHẢI CÓ THẬT TRONG KHUÔN BẢNG, KHÔNG CHỈ TRONG DỮ LIỆU. Bài kiểm này ghi thẳng sáu ô
   bằng `write()`, nên nó chạy được kể cả khi `headers()` chỉ khai năm cột — mà `headers()` là
   thứ dựng bảng trên màn Cấu hình VÀ dựng dòng tiêu đề lúc xuất/nhập .csv. Thiếu ở đó thì anh
   Thắng không có ô nào để khai, và lượt nạp .csv cắt mất cột thứ sáu. */
$H_COSO = VHCP_Cfg::headers( VHCP_Cfg::COSO );
t( '🔴 khuôn danh mục cơ sở CÓ cột "Đơn vị"', in_array( 'Đơn vị', $H_COSO, true ), $H_COSO );
teq( 'và nó là cột thứ SÁU (đúng chỗ `$r[5]` đang đọc)', 5, array_search( 'Đơn vị', $H_COSO, true ) );
/* ⚠️ Cột "Mã đơn vị" (MISA) KHÔNG được đổi tên hay đổi chỗ: nó đi thẳng vào tệp xuất. Hai cái
   tên gần giống nhau nằm cùng bảng, nên chốt luôn ở đây kẻo lần sau ai gộp nhầm. */
teq( 'cột "Mã đơn vị" (MISA) vẫn đứng nguyên chỗ cũ', 1, array_search( 'Mã đơn vị', $H_COSO, true ) );

lam( 'KT POSH' );
teq( 'KT POSH xem được gian POSH',   true,  VHCP_DonVi::xem_duoc_coso( CS_POSH ) );
teq( '🔴 KT POSH KHÔNG xem được gian K&H', false, VHCP_DonVi::xem_duoc_coso( CS_KH ) );
lam( 'KT K&H' );
teq( 'KT K&H xem được gian K&H',     true,  VHCP_DonVi::xem_duoc_coso( CS_KH ) );
teq( '🔴 KT K&H KHÔNG xem được gian POSH', false, VHCP_DonVi::xem_duoc_coso( CS_POSH ) );
teq( 'và gian lạ thì K&H vẫn thấy (rơi về nhà mặc định)', true, VHCP_DonVi::xem_duoc_coso( CS_LA ) );
lam( 'Sếp', 'Admin' );
teq( 'Sếp xem được cả hai bên · K&H',  true, VHCP_DonVi::xem_duoc_coso( CS_KH ) );
teq( 'Sếp xem được cả hai bên · POSH', true, VHCP_DonVi::xem_duoc_coso( CS_POSH ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. Ô CHỌN CƠ SỞ CHỈ BÀY GIAN CỦA BÊN MÌNH
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function o_coso() { $b = VHCP_Don::get_bootstrap(); return $b['coso']; }
lam( 'KT POSH' );  teq( '🔴 ô chọn của KT POSH chỉ có gian POSH', array( CS_POSH ), o_coso() );
lam( 'KT K&H' );   teq( '🔴 ô chọn của KT K&H chỉ có gian K&H',   array( CS_KH ),   o_coso() );
lam( 'Sếp', 'Admin' );
teq( 'ô chọn của Sếp có cả hai', array( CS_KH, CS_POSH ), o_coso() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. BỐN MẢNG CHI PHÍ — mỗi bên một dòng, rồi đòi đúng những gì mỗi người được thấy
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$wpdb->insert( VHCP_DB::t( 'so_chi' ), array( 'id' => 'SC_KH',   'coso' => CS_KH,   'ky' => 'T9', 'so_tien' => 100000, 'noi_dung' => 'nước K&H',  'nguoi_nhap' => 'KT K&H' ) );
$wpdb->insert( VHCP_DB::t( 'so_chi' ), array( 'id' => 'SC_POSH', 'coso' => CS_POSH, 'ky' => 'T9', 'so_tien' => 200000, 'noi_dung' => 'nước POSH', 'nguoi_nhap' => 'KT POSH' ) );

$wpdb->insert( VHCP_DB::t( 'mk_don' ), array( 'ma' => 'MK_KH',   'coso' => CS_KH,   'ten' => 'chạy ads K&H',  'nguoi_tao' => 'KT K&H' ) );
$wpdb->insert( VHCP_DB::t( 'mk_don' ), array( 'ma' => 'MK_POSH', 'coso' => CS_POSH, 'ten' => 'chạy ads POSH', 'nguoi_tao' => 'KT POSH' ) );

$wpdb->insert( VHCP_DB::t( 'da_index' ), array( 'ma_da' => 'DA_KH',   'ten' => 'setup K&H',  'nguoi_tao' => 'KT K&H' ) );
$wpdb->insert( VHCP_DB::t( 'da_index' ), array( 'ma_da' => 'DA_POSH', 'ten' => 'setup POSH', 'nguoi_tao' => 'KT POSH' ) );

$wpdb->insert( VHCP_DB::t( 'bp_index' ), array( 'ma' => 'BP_KH',   'loai' => 'congtac', 'ten' => 'đi tỉnh K&H',  'nguoi_tao' => 'KT K&H' ) );
$wpdb->insert( VHCP_DB::t( 'bp_index' ), array( 'ma' => 'BP_POSH', 'loai' => 'congtac', 'ten' => 'đi tỉnh POSH', 'nguoi_tao' => 'KT POSH' ) );

function ma_cua( $ds, $khoa ) {
	$ra = array();
	foreach ( (array) $ds as $x ) { $ra[] = (string) $x[ $khoa ]; }
	sort( $ra );
	return $ra;
}
function so_chi_ids() { $r = VHCP_SoChi::list_chi(); return ma_cua( $r['items'], 'id' ); }
function mk_mas()     { $r = VHCP_Mk::list_don();   return ma_cua( $r['items'], 'ma' ); }
function da_mas()     { $r = VHCP_DuAn::list_du_an(); return ma_cua( $r['items'], 'maDA' ); }
function bp_mas()     { $r = VHCP_Bp::list_bp();    return ma_cua( $r['items'], 'ma' ); }

/* Đối chứng trước: Sếp phải thấy ĐỦ CẢ HAI. Không có phép này thì một hàm lọc hỏng trả về
   rỗng cũng làm mọi phép "không thấy bên kia" bên dưới xanh hết. */
lam( 'Sếp', 'Admin' );
teq( 'đối chứng · Sếp thấy cả hai dòng sổ chi phí',  array( 'SC_KH', 'SC_POSH' ), so_chi_ids() );
teq( 'đối chứng · Sếp thấy cả hai đợt marketing',    array( 'MK_KH', 'MK_POSH' ), mk_mas() );
teq( 'đối chứng · Sếp thấy cả hai dự án',            array( 'DA_KH', 'DA_POSH' ), da_mas() );
teq( 'đối chứng · Sếp thấy cả hai đợt công tác',     array( 'BP_KH', 'BP_POSH' ), bp_mas() );

lam( 'KT POSH' );
teq( '🔴 Sổ chi phí · KT POSH chỉ thấy dòng của POSH',  array( 'SC_POSH' ), so_chi_ids() );
teq( '🔴 Marketing · KT POSH chỉ thấy đợt của POSH',    array( 'MK_POSH' ), mk_mas() );
teq( '🔴 Kỹ thuật · KT POSH chỉ thấy dự án của POSH',   array( 'DA_POSH' ), da_mas() );
teq( '🔴 Công tác · KT POSH chỉ thấy đợt của POSH',     array( 'BP_POSH' ), bp_mas() );

lam( 'KT K&H' );
teq( '🔴 Sổ chi phí · KT K&H chỉ thấy dòng của K&H',   array( 'SC_KH' ), so_chi_ids() );
teq( '🔴 Marketing · KT K&H chỉ thấy đợt của K&H',     array( 'MK_KH' ), mk_mas() );
teq( '🔴 Kỹ thuật · KT K&H chỉ thấy dự án của K&H',    array( 'DA_KH' ), da_mas() );
teq( '🔴 Công tác · KT K&H chỉ thấy đợt của K&H',      array( 'BP_KH' ), bp_mas() );

/* Ô LỌC cũng không được bày kỳ / loại chi phí của bên kia — chọn xong nhận bảng rỗng thì vẫn
   biết bên kia có phát sinh gì ở kỳ nào. Rò rỉ ít hơn, nhưng vẫn là rò rỉ. */
$wpdb->insert( VHCP_DB::t( 'so_chi' ), array( 'id' => 'SC_P2', 'coso' => CS_POSH, 'ky' => 'KY-RIENG-POSH', 'so_tien' => 1, 'loai' => 'LOAI-RIENG-POSH' ) );
lam( 'KT K&H' );
$r_kh = VHCP_SoChi::list_chi();
t( '🔴 ô lọc KỲ của KT K&H không bày kỳ riêng của POSH',
	! in_array( 'KY-RIENG-POSH', (array) $r_kh['kyList'], true ), $r_kh['kyList'] );
t( '🔴 ô lọc LOẠI của KT K&H không bày loại riêng của POSH',
	! in_array( 'LOAI-RIENG-POSH', (array) $r_kh['loaiList'], true ), $r_kh['loaiList'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. CHỐT Ở CỬA API — mở thẳng bằng mã cũng không lọt
 *
 * 🔴 LỌC DANH SÁCH CHƯA ĐỦ. Danh sách chỉ là cái người ta NHÌN; ai gõ mã lên thanh địa chỉ,
 *    hoặc gọi thẳng hàm sửa/xoá, thì đi vòng qua hết. Đây là lớp phải chặn.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function chan( $lop, $ham, $args ) { return VHCP_DonVi::chan_theo_ham( array( $lop, $ham ), $args ); }

lam( 'KT POSH' );
t( '🔴 KT POSH KHÔNG mở được dòng sổ chi phí của K&H',
	'' !== chan( 'VHCP_SoChi', 'update', array( 'SC_KH', array() ) ), chan( 'VHCP_SoChi', 'update', array( 'SC_KH', array() ) ) );
teq( 'nhưng dòng của chính mình thì mở được', '', chan( 'VHCP_SoChi', 'update', array( 'SC_POSH', array() ) ) );
t( '🔴 KT POSH KHÔNG xoá được dòng sổ chi phí của K&H',
	'' !== chan( 'VHCP_SoChi', 'delete', array( 'SC_KH' ) ) );
t( '🔴 KT POSH KHÔNG mở được đợt marketing của K&H',
	'' !== chan( 'VHCP_Mk', 'get_don', array( 'MK_KH' ) ) );
t( '🔴 KT POSH KHÔNG mở được dự án của K&H',
	'' !== chan( 'VHCP_DuAn', 'get_du_an', array( 'DA_KH' ) ) );
/* Đối chứng bắt buộc: mở được dự án CỦA MÌNH. Không có phép này thì một chốt trả bừa "của nhà
   mặc định" cho mọi dự án vẫn xanh — nó chối đúng dự án K&H, chỉ là chối luôn cả dự án POSH,
   và bên POSH không mở được gì của chính họ. */
teq( 'dự án của chính mình thì mở được', '', chan( 'VHCP_DuAn', 'get_du_an', array( 'DA_POSH' ) ) );
teq( 'đơn vị của dự án đọc theo người tạo', 'POSH', VHCP_DuAn::don_vi_cua( 'DA_POSH' ) );
teq( 'và của dự án bên kia là K&H', 'K&H', VHCP_DuAn::don_vi_cua( 'DA_KH' ) );
t( '🔴 KT POSH KHÔNG mở được đợt công tác của K&H',
	'' !== chan( 'VHCP_Bp', 'get', array( 'BP_KH' ) ) );
teq( 'đợt công tác của chính mình thì mở được', '', chan( 'VHCP_Bp', 'get', array( 'BP_POSH' ) ) );

/* Câu chối KHÔNG được nói khác nhau giữa "không có" và "không được xem" — nói khác là ai cũng
   dò được bên kia có bao nhiêu bản ghi bằng cách đổi mã trên thanh địa chỉ. */
teq( 'mã không tồn tại thì cho qua (để hàm thật trả câu lỗi của nó)',
	'', chan( 'VHCP_SoChi', 'delete', array( 'KHONG-CO-THAT' ) ) );

/* 🔴 MỖI LỚP CHỈ NHẬN ĐÚNG KIỂU KHOÁ CỦA MÌNH. Nhận bừa thì một ngày nào đó có hàm mới đặt
   tên tham số trùng (`$ma_don` chẳng hạn) và chốt sẽ tra id sổ chi phí trong bảng ấy — không
   thấy, trả `null`, mà `null` là CHO QUA. */
teq( 'sổ chi phí KHÔNG nhận kiểu khoá lạ', null, VHCP_SoChi::don_vi_cua( 'SC_KH', 'ma_don' ) );
teq( 'nhưng đúng kiểu của mình thì tra ra', 'K&H', VHCP_SoChi::don_vi_cua( 'SC_KH', 'id' ) );
teq( 'dự án KHÔNG nhận kiểu khoá lạ',      null, VHCP_DuAn::don_vi_cua( 'DA_KH', 'id' ) );
teq( 'công tác/setup KHÔNG nhận kiểu khoá lạ', null, VHCP_Bp::don_vi_cua( 'BP_KH', 'ma_da' ) );

/* 🔴 DÒNG CON CỦA MARKETING TRA QUA BẢNG DÒNG. `update_line()`/`delete_line()` nhận id DÒNG
   chứ không phải mã đợt — tra thẳng vào bảng đợt thì không thấy, trả null, và null là CHO
   QUA: sửa được từng dòng tiền của bên kia. */
$wpdb->insert( VHCP_DB::t( 'mk_line' ), array( 'id' => 'MKL_KH', 'ma_don' => 'MK_KH', 'thuc_te' => 500000 ) );
$wpdb->insert( VHCP_DB::t( 'mk_line' ), array( 'id' => 'MKL_P',  'ma_don' => 'MK_POSH', 'thuc_te' => 700000 ) );
teq( 'đơn vị của DÒNG marketing tra ngược được về đợt', 'K&H', VHCP_Mk::don_vi_cua( 'MKL_KH', 'id' ) );
t( '🔴 KT POSH KHÔNG sửa được DÒNG marketing của K&H',
	'' !== chan( 'VHCP_Mk', 'update_line', array( 'MKL_KH', array() ) ) );
teq( 'dòng marketing của chính mình thì sửa được', '', chan( 'VHCP_Mk', 'update_line', array( 'MKL_P', array() ) ) );

/* ⚠️ `VHCP_Mk::add_line()` cũng nhận tham số tên `$ma_don`, nhưng đó là mã ĐỢT MARKETING.
   Trước bản này nhánh gác của mảng Đơn vận hành vơ luôn nó, tra trong bảng đơn, không thấy,
   và chối một thao tác hoàn toàn hợp lệ. */
teq( '🔴 thêm dòng vào đợt marketing CỦA MÌNH không bị chối nhầm',
	'', chan( 'VHCP_Mk', 'add_line', array( 'MK_POSH', array() ) ) );
t( 'nhưng thêm dòng vào đợt của K&H thì vẫn chối',
	'' !== chan( 'VHCP_Mk', 'add_line', array( 'MK_KH', array() ) ) );

lam( 'Sếp', 'Admin' );
teq( 'Sếp mở được bên K&H',  '', chan( 'VHCP_SoChi', 'update', array( 'SC_KH', array() ) ) );
teq( 'Sếp mở được bên POSH', '', chan( 'VHCP_SoChi', 'update', array( 'SC_POSH', array() ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. DANH MỤC CƠ SỞ GIỮ ĐƯỢC CỘT ĐƠN VỊ QUA MỘT LƯỢT LƯU
 *
 * 🔴 Một bản giao diện cũ (hoặc lượt nạp .csv thiếu cột) gửi lên bảng cơ sở KHÔNG có ô đơn vị.
 *    Ghi đè bằng rỗng là mọi cơ sở POSH lặng lẽ về K&H — tức bên kia nhìn thấy hết, và không
 *    có dòng nhật ký nào nói vì sao.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => CS_KH,   'maDonVi' => 'FLMPT', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => '' ),
	array( 'ten' => CS_POSH, 'maDonVi' => 'PSHCM', 'phanLoaiLon' => 'POSH',    'tenMisa' => '' ),
) ) );
teq( '🔴 lưu bảng cơ sở THIẾU cột đơn vị thì giữ nguyên giá trị cũ',
	'POSH', VHCP_DonVi::cua_coso( CS_POSH ) );

VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => CS_KH,   'maDonVi' => 'FLMPT', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => '', 'donVi' => 'K&H' ),
	array( 'ten' => CS_POSH, 'maDonVi' => 'PSHCM', 'phanLoaiLon' => 'POSH',    'tenMisa' => '', 'donVi' => 'K&H' ),
) ) );
teq( 'nhưng gửi LÊN giá trị mới thì đổi thật (không phải khoá cứng)',
	'K&H', VHCP_DonVi::cua_coso( CS_POSH ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: hai bên dùng chung một web, không bên nào thấy số của bên kia.\n";
