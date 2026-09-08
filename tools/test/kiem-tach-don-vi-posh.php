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

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5b. LUỒNG TẠO CHI PHÍ CHO POSH — ĐẦU TỚI CUỐI
 *
 * Anh Thắng 08/09/2026: *"Phần tạo chi phí cho Posh (mảng thứ 2) em đã làm chưa"*. Bài dưới
 * chạy đúng luồng thật: người POSH lập đơn → đơn mang đơn vị POSH → chỉ bên POSH thấy → mở
 * thẳng bằng mã cũng không lọt.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ DỰNG LẠI SÂN NGAY ĐÂY, đừng tin trạng thái mấy khối trên để lại. Bài kiểm này có khối
   cố ý gọi `save_config()` để thử đường ghi, và một trong số đó đặt lại đơn vị của mọi cơ sở —
   khối sau đọc nhờ là xanh/đỏ theo thứ tự viết chứ không theo điều đang kiểm. Đã đỏ đúng như
   thế lượt đầu: ô chọn cơ sở ra rỗng vì gian POSH lúc ấy đang mang đơn vị K&H. */
VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	array( CS_KH,   'FLMPT', 'FARM MN', '', '', '' ),
	array( CS_POSH, 'PSHCM', 'POSH',    '', '', 'POSH' ),
) );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'KT K&H',  '111111', 'Kế toán cá nhân', '', '', '', '', 'K&H',  'K&H' ),
	array( 'KT POSH', '222222', 'Kế toán cá nhân', '', '', '', '', 'POSH', 'POSH' ),
	array( 'Sếp',     '333333', 'Admin',           '', '', '', '', 'K&H',  '' ),
	array( 'NV POSH', '444444', 'Nhân viên', CS_POSH, '', '', '', 'POSH', '' ),
) );
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'NV POSH', CS_POSH );
$r_tao = VHCP_Don::create_don( 'T9/2026 (7/9-13/9/2026)', 'NV POSH' );
$ma_p  = isset( $r_tao['maDon'] ) ? $r_tao['maDon'] : '';
t( 'NV POSH lập được đơn', '' !== $ma_p, $r_tao );
teq( '🔴 đơn tự mang đơn vị POSH (theo nhà người lập)',
	'POSH', VHCP_DonVi::chuan( VHCP_Don::don_row( $ma_p )['don_vi'] ) );
teq( '🔴 và ô chọn cơ sở lúc lập chỉ bày gian POSH', array( CS_POSH ), o_coso() );

$wpdb->insert( VHCP_DB::t( 'chiphi' ), array( 'id' => 'CP_P', 'ma_don' => $ma_p,
	'coso' => CS_POSH, 'nhom' => 'Chi phí NVL', 'noi_dung' => 'nước rửa', 'thanh_tien' => 200000 ) );

function thay_don( $ma ) {
	foreach ( VHCP_Don::list_dons() as $x ) { if ( (string) $x['maDon'] === (string) $ma ) { return true; } }
	return false;
}
lam( 'KT POSH' );
teq( '🔴 kế toán POSH thấy đơn ấy',        true,  thay_don( $ma_p ) );
lam( 'KT K&H' );
teq( '🔴 kế toán K&H KHÔNG thấy đơn ấy',   false, thay_don( $ma_p ) );
t( 'và mở thẳng bằng mã cũng bị chối', '' !== VHCP_DonVi::vi_sao_khong_dung( $ma_p ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5c. HAI MÀN GOM DỮ LIỆU — chỗ dễ hở nhất, và ĐÃ hở
 *
 * 🔴 XUẤT MISA là chỗ tiền ĐI RA sổ kế toán: hở ở đây nặng hơn hở ở một màn xem, vì tệp mang
 *    luôn đơn của bên kia sang và hai công ty nộp chồng số của nhau.
 * 🔴 TRA THEO MÃ gom dòng tiền của MỌI mảng lại một chỗ, nên nó là đường vòng quanh mọi chốt
 *    đã đặt ở từng mảng.
 *
 * Cả hai đều hở thật cho tới 08/09/2026 — chạy thử luồng đầu-cuối mới lòi ra.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$wpdb->update( VHCP_DB::t( 'don' ), array( 'trang_thai' => 'Đã quyết toán' ), array( 'ma_don' => $ma_p ) );
$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => 'D_KH_QT', 'ky' => 'T9/2026 (7/9-13/9/2026)',
	'trang_thai' => 'Đã quyết toán', 'nguoi_lap' => 'NV KH', 'don_vi' => 'K&H' ) );
$wpdb->insert( VHCP_DB::t( 'chiphi' ), array( 'id' => 'CP_K', 'ma_don' => 'D_KH_QT',
	'coso' => CS_KH, 'nhom' => 'Chi phí NVL', 'noi_dung' => 'dầu ăn', 'thanh_tien' => 50000 ) );

function misa_ma() {
	$x = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
	$a = isset( $x['maDons'] ) ? (array) $x['maDons'] : array();
	sort( $a );
	return $a;
}
lam( 'Sếp', 'Admin' );
teq( 'đối chứng · Sếp xuất MISA được cả hai đơn', array( 'D_KH_QT', $ma_p ), misa_ma() );
lam( 'KT POSH' );
teq( '🔴 kế toán POSH chỉ xuất MISA được đơn POSH', array( $ma_p ), misa_ma() );
lam( 'KT K&H' );
teq( '🔴 kế toán K&H chỉ xuất MISA được đơn K&H',   array( 'D_KH_QT' ), misa_ma() );

/* ⚠️ CANH TÍNH CHẤT, ĐỪNG GHIM CON SỐ. Màn này gom dòng của MỌI mảng, mà bài kiểm đã đổ dữ
   liệu ở mấy khối trên — ghim "đúng 2 dòng" là phép đỏ mỗi lần ai thêm một dòng thử ở chỗ
   khác. Bất biến thật: mỗi bên chỉ thấy dòng của gian mình, và phải thấy ÍT NHẤT một dòng
   (không thì một hàm lọc hỏng trả rỗng cũng làm phép này xanh). */
function trama_coso() {
	$x  = VHCP_TraMa::search( array() );
	$ra = array();
	foreach ( (array) ( isset( $x['items'] ) ? $x['items'] : array() ) as $r ) {
		$c = trim( (string) ( isset( $r['coso'] ) ? $r['coso'] : '' ) );
		if ( '' !== $c ) { $ra[ $c ] = 1; }
	}
	return array_keys( $ra );
}
lam( 'Sếp', 'Admin' );
$cs_sep = trama_coso();
t( 'đối chứng · Sếp tra theo mã thấy gian của CẢ HAI bên',
	in_array( CS_KH, $cs_sep, true ) && in_array( CS_POSH, $cs_sep, true ), $cs_sep );
lam( 'KT POSH' );
teq( '🔴 kế toán POSH tra theo mã chỉ thấy gian POSH', array( CS_POSH ), trama_coso() );
lam( 'KT K&H' );
$cs_kh = trama_coso();
t( '🔴 kế toán K&H tra theo mã KHÔNG thấy gian POSH', ! in_array( CS_POSH, $cs_kh, true ), $cs_kh );
t( 'và vẫn thấy gian của mình',                        in_array( CS_KH, $cs_kh, true ), $cs_kh );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5d. PHÂN QUYỀN "AI XEM ĐƯỢC" — và cái bẫy im lặng của nó
 *
 * Anh Thắng 08/09/2026: *"Làm phần phân quyền ai xem được"*.
 *
 * 🔴 Ô "Xem đơn vị" trước là ô GÕ TAY, ngăn nhau bằng dấu phẩy. Gõ sai một chữ — "POS", hay
 *    nhầm sang tên CƠ SỞ ("POSH SÀI GÒN") — thì tên ấy không khớp đơn vị nào, người đó KHÔNG
 *    XEM ĐƯỢC GÌ, màn trắng trơn và không câu lỗi nào. Người khai thì tin là đã khai xong.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Đúng',    '111111', 'Kế toán cá nhân', '', '', '', '', 'POSH', 'POSH' ),
	array( 'Gõ lạc',  '222222', 'Kế toán cá nhân', '', '', '', '', 'POSH', 'POS' ),
	array( 'Nhầm CS', '333333', 'Kế toán cá nhân', '', '', '', '', 'POSH', CS_POSH ),
	array( 'Bỏ trống','444444', 'Kế toán cá nhân', '', '', '', '', 'POSH', '' ),
	array( 'Hai bên', '555555', 'Kế toán cá nhân', '', '', '', '', 'K&H',  'K&H, POSH' ),
) );
$lac = array();
foreach ( VHCP_DonVi::ai_khai_lac() as $x ) { $lac[ $x['ten'] ] = $x['lac']; }

t( '🔴 bắt được người gõ lạc tên đơn vị',      isset( $lac['Gõ lạc'] ),  array_keys( $lac ) );
teq( 'và nói rõ họ đang khai cái gì',           'POS', $lac['Gõ lạc'] );
/* 🔴 Nhầm sang tên CƠ SỞ là ca dễ mắc nhất: hai ô nằm cạnh nhau trên cùng một bảng. */
t( '🔴 bắt được cả người khai nhầm tên CƠ SỞ', isset( $lac['Nhầm CS'] ), array_keys( $lac ) );
t( 'người khai đúng thì KHÔNG bị báo',         ! isset( $lac['Đúng'] ),  array_keys( $lac ) );
t( 'bỏ trống là hợp lệ (theo mặc định của vai), không báo',
	! isset( $lac['Bỏ trống'] ), array_keys( $lac ) );
t( 'khai nhiều đơn vị cách nhau dấu phẩy cũng không báo',
	! isset( $lac['Hai bên'] ), array_keys( $lac ) );

/* Và hai người kia đúng là KHÔNG xem được gì — đó mới là hậu quả thật, không chỉ là cái nhãn. */
lam( 'Gõ lạc' );
teq( '🔴 người gõ lạc không xem được đơn vị nào', false, VHCP_DonVi::duoc_xem( 'POSH' ) );
teq( 'kể cả nhà của chính mình',                  false, VHCP_DonVi::duoc_xem( 'K&H' ) );
lam( 'Đúng' );
teq( 'người khai đúng thì xem được POSH', true,  VHCP_DonVi::duoc_xem( 'POSH' ) );
teq( 'và không xem được K&H',             false, VHCP_DonVi::duoc_xem( 'K&H' ) );
lam( 'Hai bên' );
teq( 'khai hai đơn vị thì xem được cả hai · K&H',  true, VHCP_DonVi::duoc_xem( 'K&H' ) );
teq( 'khai hai đơn vị thì xem được cả hai · POSH', true, VHCP_DonVi::duoc_xem( 'POSH' ) );

/* ⚠️ KHÔNG TỰ RỬA tên lạ. Đơn vị mới có thể vừa khai cho một người mà chưa ai/đơn nào mang nó,
   nên `ds()` chưa thấy. Rửa là xoá mất phân quyền vừa đặt — và tệ hơn, không bao giờ tạo được
   đơn vị mới. Chỉ BÁO, để người khai tự quyết. */
$van_con = '';
foreach ( VHCP_Cfg::get_users() as $u ) { if ( 'Gõ lạc' === $u['ten'] ) { $van_con = (string) $u['xemDonVi']; } }
teq( '⚠️ giá trị lạ vẫn được giữ nguyên, không bị lặng lẽ xoá', 'POS', $van_con );

/* Danh sách ấy phải xuống tới màn, không thì cảnh báo chẳng bao giờ hiện. */
lam( 'Sếp', 'Admin' );
$bt = VHCP_Don::get_bootstrap();
t( '🔴 boot gửi danh sách khai lạc xuống màn Cấu hình',
	isset( $bt['khaiLac'] ) && count( (array) $bt['khaiLac'] ) >= 2, $bt['khaiLac'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. DANH MỤC CƠ SỞ TÁCH THEO ĐƠN VỊ — và LƯU KHÔNG ĐƯỢC XOÁ MẤT BÊN KIA
 *
 * Anh Thắng 08/09/2026: *"Mỗi đơn vị tách 1 bảng riêng, để kế toán bộ phận đó tự nhìn thấy cơ
 * sở của mình và tự thêm sửa mã misa"*.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ DỰNG LẠI CẢ HAI BẢNG, đừng chỉ dựng bảng cơ sở. Khối 5d ghi đè bảng NGƯỜI DÙNG bằng mấy
   tài khoản thử của nó, nên 'KT POSH' không còn — mà không còn hồ sơ thì `xem_duoc()` rơi về
   mặc định của VAI, và "Kế toán cá nhân" mặc định là XEM CẢ. Lúc ấy chốt hợp nhất khi lưu
   không chạy (nó chỉ chạy cho người bị giới hạn), lượt lưu ghi đè toàn bộ, và gian K&H biến
   mất — phép dưới đỏ vì một lý do chẳng liên quan gì tới điều nó đang kiểm. */
VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	array( CS_KH,   'FLMPT', 'FARM MN', '', '', '' ),
	array( CS_POSH, 'PSHCM', 'POSH',    '', '', 'POSH' ),
) );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'KT K&H',  '111111', 'Kế toán cá nhân', '', '', '', '', 'K&H',  'K&H' ),
	array( 'KT POSH', '222222', 'Kế toán cá nhân', '', '', '', '', 'POSH', 'POSH' ),
	array( 'Sếp',     '333333', 'Admin',           '', '', '', '', 'K&H',  '' ),
) );
/* ⚠️ `sort()` của PHP xếp chuỗi UTF-8 theo BYTE, nên "TÀU ESTELLA" và "POSH ĐÀ NẴNG" ra thứ
   tự không giống cách người Việt đọc. Bài này không kiểm thứ tự — nó kiểm CÓ NHỮNG GÌ. Xếp
   bằng `strnatcasecmp` trên bản bỏ dấu để kỳ vọng viết ra đọc được, và để phép thử không đỏ
   vì một thứ nó không định canh. */
function ten_coso_cfg() {
	$a = array();
	foreach ( (array) VHCP_Cfg::get_config( array() )['coso'] as $x ) { $a[] = (string) $x['ten']; }
	usort( $a, function ( $x, $y ) { return strnatcasecmp( $x, $y ); } );
	return $a;
}
lam( 'Sếp', 'Admin' );
teq( 'đối chứng · Sếp thấy cả hai gian trong danh mục', array( CS_POSH, CS_KH ), ten_coso_cfg() );
lam( 'KT POSH' );
teq( '🔴 KT POSH chỉ thấy gian POSH trong danh mục cơ sở', array( CS_POSH ), ten_coso_cfg() );
lam( 'KT K&H' );
teq( '🔴 KT K&H chỉ thấy gian K&H', array( CS_KH ), ten_coso_cfg() );

/* 🔴 ĐÂY LÀ PHÉP QUAN TRỌNG NHẤT CỦA CẢ BÀI.
   Màn Cấu hình nay chỉ bày cho mỗi người danh mục của đơn vị họ, nên bảng gửi lên KHÔNG phải
   toàn bộ danh mục. Ghi đè bằng chừng ấy dòng là toàn bộ cơ sở của bên kia biến mất trong một
   lần bấm Lưu — cùng với mã MISA, phân loại lớn và ngày đóng gian của chúng.

   Tai nạn cùng kiểu đã xảy ra thật với bảng người dùng 25/08/2026. Lần đó bảng rỗng là do lỗi
   mạng nên còn trông bất thường; lần này bảng chỉ có một dòng là ĐÚNG THEO THIẾT KẾ, nên không
   có gì để ai kịp dừng tay. */
lam( 'KT POSH' );
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => CS_POSH, 'maDonVi' => 'PSHCM-MOI', 'phanLoaiLon' => 'POSH', 'tenMisa' => 'POSH SG', 'donVi' => 'POSH' ),
) ) );
lam( 'Sếp', 'Admin' );
teq( '🔴 KT POSH lưu bảng của mình thì gian K&H VẪN CÒN',
	array( CS_POSH, CS_KH ), ten_coso_cfg() );
$sau = array();
foreach ( (array) VHCP_Cfg::get_config( array() )['coso'] as $x ) { $sau[ $x['ten'] ] = $x; }
teq( 'và sửa đổi của họ có ăn thật (mã MISA mới)', 'PSHCM-MOI', $sau[ CS_POSH ]['maDonVi'] );
teq( 'gian K&H giữ nguyên mã cũ',                  'FLMPT',     $sau[ CS_KH ]['maDonVi'] );
teq( 'và giữ nguyên đơn vị cũ', VHCP_DonVi::MAC_DINH, VHCP_DonVi::chuan( $sau[ CS_KH ]['donVi'] ) );

/* Kế toán POSH THÊM một gian mới thì gian ấy vào đúng đơn vị của họ, và bên kia vẫn nguyên. */
lam( 'KT POSH' );
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => CS_POSH,     'maDonVi' => 'PSHCM-MOI', 'phanLoaiLon' => 'POSH', 'tenMisa' => 'POSH SG', 'donVi' => 'POSH' ),
	array( 'ten' => 'POSH ĐÀ NẴNG', 'maDonVi' => 'PSDN',   'phanLoaiLon' => 'POSH', 'tenMisa' => '',        'donVi' => 'POSH' ),
) ) );
teq( '🔴 KT POSH tự thêm được gian mới cho bên mình',
	array( CS_POSH, 'POSH ĐÀ NẴNG' ), ten_coso_cfg() );
lam( 'Sếp', 'Admin' );
teq( 'và Sếp thấy đủ ba gian', array( CS_POSH, 'POSH ĐÀ NẴNG', CS_KH ), ten_coso_cfg() );

/* ⚠️ Admin (xem cả) lưu thì vẫn ghi đè TOÀN BỘ như trước — đó là hành vi đúng và phải giữ,
   không thì Admin không xoá được cơ sở nào nữa. */
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => CS_KH, 'maDonVi' => 'FLMPT', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => '', 'donVi' => '' ),
) ) );
teq( '🔴 Admin lưu thì vẫn ghi đè toàn bộ (xoá được cơ sở)', array( CS_KH ), ten_coso_cfg() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: hai bên dùng chung một web, không bên nào thấy số của bên kia.\n";
