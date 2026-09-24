<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CỘT TỈNH / THÀNH CỦA DANH MỤC CƠ SỞ.
 *
 * Anh Thắng 11/09/2026: *"thêm cột phân loại theo tỉnh"*.
 *
 * =============================================================================================
 * 🔴 THÊM CỘT VÀO BẢNG CƠ SỞ LÀ VIỆC DỄ MẤT DỮ LIỆU NHẤT Ở PLUGIN NÀY. Màn Cấu hình gửi lên
 *    NGUYÊN bảng; mọi bản giao diện cũ đang mở, mọi tệp .csv cũ, đều không có ô mới. Ghi đè
 *    bằng rỗng là xoá sạch phân loại vừa khai cả buổi — và không câu lỗi nào.
 *    Cột ĐƠN VỊ đã cắn đúng chuyện này một lần (kế toán K&H nhìn thấy toàn bộ chi phí POSH),
 *    nên cột TỈNH đi theo cùng một khuôn: KHÔNG có ô thì giữ nguyên ô đang lưu.
 *
 * 🔴 ĐỂ TRỐNG LÀ "CHƯA KHAI", KHÔNG PHẢI "VỀ TỈNH MẶC ĐỊNH". Khác cột Đơn vị (trống = K&H).
 *    Gán bừa một tỉnh cho gian chưa khai là báo cáo theo vùng sai ngay từ dòng đầu.
 *
 * ⚠️ GỬI LÊN Ô RỖNG CÓ CHỦ Ý thì phải XOÁ THẬT. Người ta sửa "TP HCM" thành trống để khai lại
 *    — giữ nguyên giá trị cũ là ô không bao giờ xoá được.
 *
 * Chạy: php tools/test/kiem-cot-tinh-co-so.php
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

/** Đọc lại một cơ sở từ cấu hình. */
function cs( $ten ) {
	VHCP_Cfg::clear_cache();
	foreach ( VHCP_Cfg::cfg_static()['coso'] as $x ) {
		if ( mb_strtolower( trim( (string) $x['ten'] ) ) === mb_strtolower( $ten ) ) { return $x; }
	}
	return null;
}

VHCP_Auth::dat_vai_tro( 'Admin', 'KT' );
VHCP_Cfg::seed(); VHCP_Cfg::clear_cache();

/* ═══ 1. KHAI TỈNH RỒI ĐỌC LẠI ═════════════════════════════════════════════════════════ */
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'AEON MALL TÂN PHÚ', 'maDonVi' => 'AEONTP', 'phanLoaiLon' => 'FZ MN',
	       'tenMisa' => 'Aeon Tan Phu', 'donVi' => 'POSH', 'tinh' => 'TP HCM' ),
	array( 'ten' => 'FARM PHAN THIẾT', 'maDonVi' => 'FLMPT', 'phanLoaiLon' => 'FARM MN',
	       'tenMisa' => 'Farm Phan Thiet', 'donVi' => '', 'tinh' => 'Bình Thuận' ),
	array( 'ten' => 'GIAN CHƯA KHAI TỈNH', 'maDonVi' => '', 'phanLoaiLon' => '',
	       'tenMisa' => '', 'donVi' => '', 'tinh' => '' ),
) ) );

teq( '🔴 khai tỉnh rồi đọc lại đúng', 'TP HCM', cs( 'AEON MALL TÂN PHÚ' )['tinh'] );
teq( '   tỉnh có dấu tiếng Việt không gãy', 'Bình Thuận', cs( 'FARM PHAN THIẾT' )['tinh'] );
teq( '🔴 để trống là CHƯA KHAI, không bị gán bừa một tỉnh nào', '', cs( 'GIAN CHƯA KHAI TỈNH' )['tinh'] );
teq( '   và cột Đơn vị vẫn chạy như cũ (trống = nhà mặc định)', 'K&H', cs( 'FARM PHAN THIẾT' )['donVi'] );
teq( '   cột Đơn vị khai thật thì giữ nguyên', 'POSH', cs( 'AEON MALL TÂN PHÚ' )['donVi'] );
teq( '   không đá nhầm sang cột Tên MISA', 'Aeon Tan Phu', cs( 'AEON MALL TÂN PHÚ' )['tenMisa'] );
teq( '   cũng không đá nhầm sang Mã đơn vị MISA', 'AEONTP', cs( 'AEON MALL TÂN PHÚ' )['maDonVi'] );

/* ═══ 2. 🔴 BẢN GIAO DIỆN CŨ GỬI LÊN THIẾU Ô TỈNH -> GIỮ NGUYÊN ════════════════════════ */
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'AEON MALL TÂN PHÚ', 'maDonVi' => 'AEONTP', 'phanLoaiLon' => 'FZ MN',
	       'tenMisa' => 'Aeon Tan Phu', 'donVi' => 'POSH' ),   // KHÔNG có khoá 'tinh'
	array( 'ten' => 'FARM PHAN THIẾT', 'maDonVi' => 'FLMPT', 'phanLoaiLon' => 'FARM MN',
	       'tenMisa' => 'Farm Phan Thiet', 'donVi' => '' ),
) ) );
teq( '🔴 bảng gửi lên KHÔNG có ô tỉnh: giữ nguyên tỉnh đang lưu', 'TP HCM', cs( 'AEON MALL TÂN PHÚ' )['tinh'] );
teq( '   gian thứ hai cũng giữ', 'Bình Thuận', cs( 'FARM PHAN THIẾT' )['tinh'] );

/* ═══ 3. ⚠️ XOÁ CÓ CHỦ Ý THÌ PHẢI XOÁ THẬT ════════════════════════════════════════════
 * ⚠️ Admin gửi lên NGUYÊN bảng, nên bảng gửi lên phải mang đủ mọi dòng còn giữ lại — thiếu
 *    dòng nào là xoá dòng ấy. Đó là hành vi cố ý của `save_config()`, không phải chuyện của
 *    cột tỉnh; ở đây chỉ cần gửi đủ để soi đúng việc xoá Ô TỈNH. */
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'AEON MALL TÂN PHÚ', 'maDonVi' => 'AEONTP', 'phanLoaiLon' => 'FZ MN',
	       'tenMisa' => 'Aeon Tan Phu', 'donVi' => 'POSH', 'tinh' => '' ),   // CÓ khoá, giá trị rỗng
	array( 'ten' => 'FARM PHAN THIẾT', 'maDonVi' => 'FLMPT', 'phanLoaiLon' => 'FARM MN',
	       'tenMisa' => 'Farm Phan Thiet', 'donVi' => '' ),                  // KHÔNG có khoá 'tinh'
) ) );
teq( '⚠️ gửi lên ô tỉnh RỖNG có chủ ý: xoá thật, không giữ giá trị cũ', '', cs( 'AEON MALL TÂN PHÚ' )['tinh'] );
teq( '🔴 cùng lượt lưu, gian KHÔNG gửi ô tỉnh vẫn giữ nguyên — hai ca phải tách bạch',
	'Bình Thuận', cs( 'FARM PHAN THIẾT' )['tinh'] );

/* ═══ 4. THÊM CƠ SỞ MỚI KÈM TỈNH ══════════════════════════════════════════════════════ */
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'AEON MALL TÂN PHÚ', 'maDonVi' => 'AEONTP', 'phanLoaiLon' => 'FZ MN',
	       'tenMisa' => 'Aeon Tan Phu', 'donVi' => 'POSH', 'tinh' => '' ),
	array( 'ten' => 'FARM PHAN THIẾT', 'maDonVi' => 'FLMPT', 'phanLoaiLon' => 'FARM MN',
	       'tenMisa' => 'Farm Phan Thiet', 'donVi' => '', 'tinh' => 'Bình Thuận' ),
	array( 'ten' => 'KVC NHA TRANG', 'maDonVi' => 'KVCNT', 'phanLoaiLon' => 'KVC MT',
	       'tenMisa' => 'KVC Nha Trang', 'donVi' => 'KVC', 'tinh' => 'Khánh Hoà' ),
) ) );
$m = cs( 'KVC NHA TRANG' );
t( '🔴 cơ sở mới khai đủ cả đơn vị lẫn tỉnh',
	$m && 'KVC' === $m['donVi'] && 'Khánh Hoà' === $m['tinh'], $m );

/* ═══ 5. GOM THEO TỈNH — ĐẾM ĐÚNG SỐ GIAN MỖI TỈNH ════════════════════════════════════ */
$theo = array();
foreach ( VHCP_Cfg::cfg_static()['coso'] as $x ) {
	$k = trim( (string) $x['tinh'] );
	if ( '' === $k ) { $k = '(chưa khai)'; }
	$theo[ $k ] = ( isset( $theo[ $k ] ) ? $theo[ $k ] : 0 ) + 1;
}
t( '   gom theo tỉnh ra đúng nhóm', isset( $theo['Bình Thuận'] ) && isset( $theo['Khánh Hoà'] ), array_keys( $theo ) );
t( '   gian chưa khai tỉnh gom vào rổ riêng, không biến mất', isset( $theo['(chưa khai)'] ), array_keys( $theo ) );

/* ═══ 6. MÀN: CỘT TỈNH THÔI BÀY (24/09/2026), DỮ LIỆU TỈNH KHÔNG MẤT ═════════════════════
 * Anh Thắng: *"Chỗ Tỉnh, Bỏ thay vào đó là Bộ Phận (MTD, KVC, VP)"*. Ô thứ 6 của hàng nay là ô
 * chọn BỘ PHẬN; `_dongCoso()` KHÔNG gửi `tinh` nữa — máy chủ giữ nguyên tỉnh đã khai (mục 4 ở trên
 * canh "không gửi ô = giữ cũ"). Đơn vị vẫn ở ô thứ 5.
 * ═══════════════════════════════════════════════════════════════════════════════════════ */
$app = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/templates/app.html' );
if ( preg_match( '/function _dongCoso\(r\)\{(.*?)
  \}/s', $app, $m ) ) {
	$h = $m[1];
	t( '🔴 _dongCoso đọc BỘ PHẬN ở ô thứ 6 (chỗ ô Tỉnh cũ) và KHÔNG gửi tinh', false !== mb_strpos( $h, 'boPhan:(r[5]' ) && false === mb_strpos( $h, 'tinh:' ), $h );
	t( '   và Đơn vị vẫn ở ô thứ 5, không bị đẩy lệch', false !== mb_strpos( $h, 'donVi:(r[4]' ), $h );
} else {
	t( 'bốc được _dongCoso()', false );
}
t( '🔴 hàng của bảng không còn ô nhập tỉnh, thay bằng ô chọn bộ phận', false === mb_strpos( $app, "esc(x.tinh||'')" ) && false !== mb_strpos( $app, '_bpSelCoso(x.boPhan, x.donVi)' ), '' );
t( '   form "Thêm cơ sở" hỏi bộ phận, không hỏi tỉnh', false !== mb_strpos( $app, 'id="ncBoPhan"' ) && false === mb_strpos( $app, 'id="ncTinh"' ) );
t( '   tiêu đề cột là Bộ phận', false !== mb_strpos( $app, '>Bộ phận</th>' ) && false === mb_strpos( $app, '>Tỉnh / Thành</th>' ) );
/* Hai dải gom nhóm trải hết bề ngang bảng — thêm cột mà quên nới `colspan` là dải ngắn hơn
   bảng một ô, nhìn như bảng vỡ. */
t( '🔴 dải gom nhóm nới theo số cột mới (colspan 8)',
	false === mb_strpos( $app, 'colspan="7" style="background:#1e3a8a' )
	&& false !== mb_strpos( $app, 'colspan="8" style="background:#1e3a8a' ) );
t( '   dải phân loại lớn cũng vậy',
	false !== mb_strpos( $app, 'colspan="8" style="background:#f0fdfa' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( count( $TRUOT ) ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — cột Tỉnh/Thành của danh mục cơ sở\n";
exit( 0 );
