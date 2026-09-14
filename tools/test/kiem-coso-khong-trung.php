<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * DANH MỤC CƠ SỞ KHÔNG ĐƯỢC CÓ HAI DÒNG CÙNG TÊN.
 *
 * Anh Thắng 14/09/2026, bản Văn phòng vừa cài: *"Chi phí văn phòng, bấm lưu thì nó lưu tại tính
 * sinh ra tiếp"* — 14 cơ sở, bấm Lưu một cái thành 25, tên lặp lại.
 *
 * =============================================================================================
 * 🔴 VÌ SAO TRÙNG TÊN LÀ HỎNG THẬT, KHÔNG PHẢI XẤU MẮT.
 *    Cơ sở ở đây được nhận ra bằng CHUỖI TÊN, không bằng mã. Hai dòng cùng tên nghĩa là:
 *      · tiền của một gian hàng tách làm đôi ở mọi bảng gom;
 *      · hai ô "Mã đơn vị MISA" khác nhau cho cùng một chỗ — xuất MISA ra không ai biết dòng
 *        nào đúng;
 *      · hộp chọn cơ sở có hai dòng chữ y hệt, người nhập chọn bừa một cái.
 *
 * 🔴 `nhan_coso_ngoai()` ĐÃ CÓ CHỐT, NHƯNG ĐÓ LÀ CỬA KHÁC. Nó gác đường ĐẨY TỪ GHẾ SANG; cửa
 *    LƯU TAY thì trước nay không ai gác — danh sách gửi lên sao thì ghi xuống vậy. Bài này canh
 *    đúng cửa ghi, nên bất kể dòng trùng sinh ra từ đâu nó cũng không lọt xuống sổ.
 *
 * Chạy: php tools/test/kiem-coso-khong-trung.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$GOC = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $GOC . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

function ten_coso() {
	$ra = array();
	foreach ( VHCP_Cfg::read( VHCP_Cfg::COSO ) as $r ) { $ra[] = trim( (string) $r[0] ); }
	return $ra;
}

/* ═══ 1. LƯU DANH SÁCH CÓ DÒNG TRÙNG -> CHỈ GHI MỘT ═══════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	array( 'FUNZONE ADVENTURE', 'FZA', 'FARM MN', 'FZ Adventure', '', 'K&H' ),
	array( 'FARM PHAN THIẾT',   'FPT', '',        '',             '', 'K&H' ),
	array( 'FUNZONE ADVENTURE', '',    '',        '',             '', 'K&H' ),   // trùng y hệt
	array( 'funzone adventure', '',    '',        '',             '', 'K&H' ),   // trùng khác hoa/thường
	array( '  FARM PHAN THIẾT ', '',   '',        '',             '', 'K&H' ),   // trùng thừa dấu cách
	array( 'TÀU TÂN PHÚ',       '',    '',        '',             '', 'K&H' ),
) );
$ds = ten_coso();
teq( '🔴 sáu dòng gửi lên, chỉ ba cơ sở thật được ghi', 3, count( $ds ) );
teq( '   đúng ba tên ấy', array( 'FUNZONE ADVENTURE', 'FARM PHAN THIẾT', 'TÀU TÂN PHÚ' ), $ds );

/* 🔴 GIỮ DÒNG ĐẦU, BỎ DÒNG SAU. Dòng đầu là dòng người ta đã khai mấy ô MISA; dòng sau gần như
   luôn là dòng vừa sinh thêm, còn trắng. Giữ dòng sau là xoá công khai tay. */
$hang = VHCP_Cfg::read( VHCP_Cfg::COSO );
teq( '🔴 giữ dòng ĐẦU — ô Mã đơn vị MISA khai tay còn nguyên', 'FZA', trim( (string) $hang[0][1] ) );
teq( '   và Tên MISA cũng còn', 'FZ Adventure', trim( (string) $hang[0][3] ) );

/* ═══ 2. BẤM LƯU HAI LƯỢT KHÔNG SINH THÊM ═══════════════════════════════════════ */
$lan1 = ten_coso();
VHCP_Cfg::write( VHCP_Cfg::COSO, VHCP_Cfg::read( VHCP_Cfg::COSO ) );
teq( '🔴 lưu lại chính danh sách vừa đọc -> không sinh thêm dòng nào', $lan1, ten_coso() );
VHCP_Cfg::write( VHCP_Cfg::COSO, VHCP_Cfg::read( VHCP_Cfg::COSO ) );
teq( '   lượt thứ ba cũng vậy', $lan1, ten_coso() );

/* ═══ 3. CÁC BẢNG KHÁC KHÔNG BỊ ĐỤNG ════════════════════════════════════════════
 * ⚠️ Chốt này CHỈ cho danh mục cơ sở. Bảng người dùng có thể có hai người trùng tên thật (hai
 *    cơ sở khác nhau), bảng loại chi phí cũng vậy — gộp bừa ở đó là xoá dữ liệu của người ta.
 * ═══════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Nguyễn Văn A', '1234', 'Nhân viên', 'FUNZONE ADVENTURE', '', '', '' ),
	array( 'Nguyễn Văn A', '5678', 'Nhân viên', 'FARM PHAN THIẾT',   '', '', '' ),
) );
teq( '🔴 bảng NGƯỜI DÙNG vẫn giữ đủ hai người trùng tên', 2, count( VHCP_Cfg::read( VHCP_Cfg::USER ) ) );

/* ═══ 4. CHỐT NẰM Ở CỬA GHI, KHÔNG PHẢI Ở GIAO DIỆN ═════════════════════════════
 * 🔴 Vá ở JavaScript thì mỗi cửa mới mở về sau lại lọt: nạp .csv, đẩy từ Ghế, một bản khác gọi
 *    thẳng `write()`. Cửa ghi là chỗ duy nhất mọi đường đều đi qua.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$cfg_ma = preg_replace( '#/\*[\s\S]*?\*/#', ' ',
	file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' ) );
t( '🔴 chốt nằm trong write(), gắn với đúng bảng COSO',
	(bool) preg_match( '#function write\([\s\S]{0,900}?self::COSO === \$bang#', $cfg_ma ), '' );

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
