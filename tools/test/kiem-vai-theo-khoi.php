<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KHỐI CỦA MỘT VAI TRÒ ĐỌC RA TỪ CHÍNH CÁI TÊN — PHÍA MÁY CHỦ.
 *
 * Anh Thắng 21/09/2026: *"Loại chi phí theo Khối, Ai có ở khối nào mới hiện ra"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO ĐỌC TỪ TÊN CHỨ KHÔNG KHAI THÊM MỘT CỘT
 * =============================================================================================
 * Cùng ngày vừa gỡ cột Bộ phận khỏi bảng vai trò VÀ khỏi bảng người dùng, vì anh bảo *"dùng
 * hết trên vai trò cha, con rồi"*. Thêm một ô "khối" cạnh ô "tên vai" là dựng lại y hệt cái
 * trục thừa ấy — và hai nơi khai thì có ngày lệch: vai tên "Máy Tự Động" mà ô khối để "Khu vui
 * chơi", không ai biết bên nào đúng. Tên vai CHÍNH LÀ nơi mảng đã được khai.
 *
 * ⚠️ BA CHỐT PHẢI GIỮ, cả ba hỏng lặng lẽ:
 *   1. KHÔNG ĐOÁN ĐƯỢC = MỌI KHỐI, không phải "không khối nào". "Nhân Viên Marketing", "Kế
 *      toán NCC", "Quản lý" chạy ngang cả công ty. Hiểu ngược là chúng biến khỏi CẢ BA bảng
 *      và không còn ô nào tích cho họ nữa.
 *   2. "vp" XÉT SAU CÙNG và xét theo TỪ, không theo chuỗi con — "Quản Lý VP Chung" là văn
 *      phòng thật, nhưng một vai tên "TVP" thì không.
 *   3. BỎ DẤU TRƯỚC KHI SO. Anh khai chữ hoa chữ thường lẫn lộn ("Máy Tự Động" / "máy tự
 *      động"), so nguyên văn là trượt quá nửa danh sách.
 *
 * 🔴 BẢNG VÀNG NẰM Ở `fixtures/vai-theo-khoi.json`, DÙNG CHUNG với bài kiểm phía màn. Viết
 *    bảng ấy hai lần ở hai file là sửa một bên quên bên kia, mà hai bên lệch thì màn giấu mất
 *    một ô tích trong khi máy chủ vẫn chấp — không câu lỗi nào.
 *
 * Chạy: php tools/test/kiem-vai-theo-khoi.php
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

/* ═══ 1. BẢNG VÀNG DÙNG CHUNG ════════════════════════════════════════════════════ */
$fx = json_decode( file_get_contents( __DIR__ . '/fixtures/vai-theo-khoi.json' ), true );
t( 'đọc được bảng vàng dùng chung', is_array( $fx ) && ! empty( $fx['bang'] ), $fx );
$bang = (array) $fx['bang'];
/* Phép đối chứng: bảng vàng phải có đủ CẢ BA khối VÀ có vai chạy ngang. Thiếu một loại là bài
   kiểm vẫn xanh trong khi nửa luật không hề được sờ tới. */
$co = array_unique( array_values( $bang ) );
sort( $co );
teq( '🔴 bảng vàng phủ cả ba khối lẫn vai chạy ngang', array( '', 'kvc', 'mtd', 'vp' ), $co );

foreach ( $bang as $ten => $mong ) {
	teq( 'khối của «' . ( '' === $ten ? '(tên rỗng)' : $ten ) . '»', $mong, VHCP_Cfg::khoi_cua_vai( $ten ) );
}

/* ═══ 2. KHÔNG ĐOÁN ĐƯỢC = MỌI KHỐI ══════════════════════════════════════════════ */
foreach ( array( 'kvc', 'mtd', 'vp' ) as $k ) {
	t( '🔴 «Nhân Viên Marketing» bày ở bảng khối ' . $k, VHCP_Cfg::vai_o_khoi( 'Nhân Viên Marketing', $k ) );
	t( '🔴 «Kế toán NCC» bày ở bảng khối ' . $k, VHCP_Cfg::vai_o_khoi( 'Kế toán NCC', $k ) );
	t( '   và vai gốc «Nhân viên» cũng vậy', VHCP_Cfg::vai_o_khoi( 'Nhân viên', $k ) );
}

/* ═══ 3. VAI CÓ KHỐI THÌ CHỈ BÀY Ở ĐÚNG KHỐI ẤY ══════════════════════════════════ */
t( '«Quản Lý Máy Tự Động» bày ở mtd', VHCP_Cfg::vai_o_khoi( 'Quản Lý Máy Tự Động', 'mtd' ) );
t( '🔴 và KHÔNG bày ở kvc', ! VHCP_Cfg::vai_o_khoi( 'Quản Lý Máy Tự Động', 'kvc' ) );
t( '🔴 cũng không bày ở vp', ! VHCP_Cfg::vai_o_khoi( 'Quản Lý Máy Tự Động', 'vp' ) );
t( '«Kế Toán VP Chung» bày ở vp', VHCP_Cfg::vai_o_khoi( 'Kế Toán VP Chung', 'vp' ) );
t( '🔴 và KHÔNG bày ở kvc — «vp» xét sau, nhưng vẫn phải bắt được',
	! VHCP_Cfg::vai_o_khoi( 'Kế Toán VP Chung', 'kvc' ) );

/* ═══ 4. BỎ DẤU, HẠ CHỮ, GỘP KHOẢNG TRẮNG ════════════════════════════════════════ */
teq( 'chữ thường vẫn ra đúng khối', 'mtd', VHCP_Cfg::khoi_cua_vai( 'quản lý máy tự động' ) );
teq( 'KHÔNG DẤU vẫn ra đúng khối', 'mtd', VHCP_Cfg::khoi_cua_vai( 'Quan Ly May Tu Dong' ) );
teq( 'thừa khoảng trắng vẫn ra đúng khối', 'kvc', VHCP_Cfg::khoi_cua_vai( '  Quản Lý   Khu Vui Chơi  ' ) );

/* ═══ 5. 🔴 «vp» XÉT THEO TỪ, KHÔNG THEO CHUỖI CON ═══════════════════════════════
 * Đây là phép giữ chốt số 2. Đổi `' ' . $x . ' '` thành một `mb_strpos` trần là phép này đỏ. */
teq( '🔴 «TVP» KHÔNG phải văn phòng', '', VHCP_Cfg::khoi_cua_vai( 'Nhân Viên TVP' ) );
teq( '🔴 «VPC» KHÔNG phải văn phòng', '', VHCP_Cfg::khoi_cua_vai( 'Nhân Viên VPC' ) );
teq( '   nhưng «VP» đứng riêng thì có', 'vp', VHCP_Cfg::khoi_cua_vai( 'Nhân Viên VP' ) );

/* ═══ 6. 🔴 KVC/MTĐ XÉT TRƯỚC VP ════════════════════════════════════════════════
 * Một vai mang cả hai dấu hiệu phải về cái CỤ THỂ hơn. Đảo thứ tự trong bảng là phép này đỏ. */
teq( '🔴 «Kế Toán VP Khu Vui Chơi» về kvc, không về vp', 'kvc',
	VHCP_Cfg::khoi_cua_vai( 'Kế Toán VP Khu Vui Chơi' ) );

/* ═══ 7. GỢI Ý ĐƠN VỊ Ở MÀN PHẢI KHỚP ÁNH XẠ CỦA MÁY CHỦ ═════════════════════════
 * `_dvInp()` gợi ý ba mã đơn vị để anh Thắng *"chọn nhân viên theo khối"*. Mã nào máy chủ
 * không nhận thì khai xong vẫn như chưa khai: `khoi_cua()` trả '' và người ấy không thuộc khối
 * nào — ô trông như đã điền, nên không ai đi tìm. */
$app = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
if ( preg_match( '/var DV_KHOI_GOI=\[(.*?)\];/u', $app, $m ) ) {
	preg_match_all( "/ma:'([^']+)'/u", $m[1], $mm );
	$goi = $mm[1];
	teq( 'màn gợi ý đúng ba khối', 3, count( $goi ) );
	foreach ( $goi as $ma ) {
		t( '🔴 gợi ý «' . $ma . '» được `VHCP_DonVi::khoi_cua()` nhận',
			'' !== VHCP_DonVi::khoi_cua( $ma ), VHCP_DonVi::khoi_cua( $ma ) );
	}
	$ra = array();
	foreach ( $goi as $ma ) { $ra[] = VHCP_DonVi::khoi_cua( $ma ); }
	sort( $ra );
	teq( '🔴 và ba gợi ý trỏ vào BA khối khác nhau', array( 'kvc', 'mtd', 'vp' ), $ra );
} else {
	t( 'tìm thấy `DV_KHOI_GOI` trong app.html', false, 'không thấy' );
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: khối đọc từ tên vai, không đoán được = mọi khối, gợi ý đơn vị khớp ánh xạ máy chủ.\n";
