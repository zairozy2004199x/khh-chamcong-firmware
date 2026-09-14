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

/* ═══ 5. 🔴 XOÁ HẾT RỒI LƯU -> PHẢI Ở LẠI RỖNG, KHÔNG ĐƯỢC GIEO LẠI ═════════════
 * Anh Thắng 14/09/2026, bản Văn phòng: *"trang chi phí văn phòng không xóa được cơ sở chi phí
 * kvc"*. Bản VP gieo sẵn 14 cơ sở của K&H; anh xoá hết rồi bấm Lưu — chúng quay lại ngay lượt
 * tải sau.
 *
 * 🔴 Vì gác của `seed_from()` chỉ hỏi "bảng có rỗng không". Rỗng thì gieo. Mà người ta vừa CỐ Ý
 *    dọn sạch cũng cho ra một bảng rỗng — không phân biệt được hai chuyện ấy thì mọi lượt dọn
 *    sạch đều bị hoàn tác, và người dọn không có cách nào thắng.
 * ═══════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::COSO, array() );
teq( 'xoá hết -> bảng rỗng ngay lúc ghi', 0, count( VHCP_Cfg::read( VHCP_Cfg::COSO ) ) );
/* `seed()` là thứ chạy ở mỗi lượt đăng nhập / mỗi lượt dựng cấu hình tĩnh. */
VHCP_Cfg::seed();
teq( '🔴 lượt tải sau KHÔNG gieo lại — bảng vẫn rỗng', 0, count( VHCP_Cfg::read( VHCP_Cfg::COSO ) ) );
VHCP_Cfg::seed();
teq( '   và lượt sau nữa cũng vậy', 0, count( VHCP_Cfg::read( VHCP_Cfg::COSO ) ) );

/* ⚠️ XOÁ BỚT VÀI DÒNG CŨNG PHẢI Ở LẠI — ca thường gặp hơn hẳn ca xoá sạch. */
VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	array( 'CHỈ CÒN MỘT', '', '', '', '', 'K&H' ),
) );
VHCP_Cfg::seed();
teq( '🔴 xoá bớt, giữ lại một -> vẫn đúng một', 1, count( VHCP_Cfg::read( VHCP_Cfg::COSO ) ) );

/* 🔴 LOẠI CHI PHÍ CŨNG VẬY — anh Thắng 14/09/2026, bản Máy Tự Động: *"bên trang chi phí mtd thì
   không xóa được loại chi phí cũ"*. Cùng một bệnh, khác bảng: MTD gieo sẵn danh mục loại chi phí
   của K&H, mà mảng ấy có danh mục riêng nên anh dọn sạch để khai lại — và chúng quay về. */
VHCP_Cfg::write( VHCP_Cfg::NHOM, array() );
VHCP_Cfg::seed();
teq( '🔴 xoá hết LOẠI CHI PHÍ -> không gieo lại', 0, count( VHCP_Cfg::read( VHCP_Cfg::NHOM ) ) );
VHCP_Cfg::write( VHCP_Cfg::NHOM, array( array( 'Chi phí máy tự động', 'canhan', '', '' ) ) );
VHCP_Cfg::seed();
teq( '🔴 khai lại danh mục riêng của mảng -> giữ nguyên, không trộn danh mục cũ vào',
	1, count( VHCP_Cfg::read( VHCP_Cfg::NHOM ) ) );

/* 🔴 BẢNG "LOẠI CHI PHÍ × MẢNG KINH DOANH" — bảng THỨ BA mắc cùng bệnh, và là bảng anh Thắng
   gặp thật: *"bấm dọn mã thì được, chứ bấm x thì lại không được"* (14/09/2026).

   Chi tiết "hai nút, hai kết quả" chính là manh mối:
     · "🧹 Dọn loại chưa khai mã" GIỮ LẠI loại đã khai mã -> bảng KHÔNG rỗng -> không dựng lại.
     · Bấm ✕ từng dòng cho tới hết rồi Lưu -> bảng RỖNG -> `cfg_static()` dựng lại nguyên danh
       mục từ NHOM -> "không xoá được".

   ⚠️ Lượt vá trước chữa COSO và NHOM mà bỏ sót bảng này. Nên phép dưới canh CẢ BA, và canh luôn
      cái tính chất chung: không nhánh "rỗng thì dựng lại" nào được thiếu dấu riêng của nó. */
VHCP_Cfg::write( VHCP_Cfg::NHOM, array(
	array( 'SP Đồ uống - NCC', 'ncc', '', '' ),
	array( 'Chi phí cơ sở', 'canhan', '', '' ),
) );
VHCP_Cfg::write( VHCP_Cfg::LOAI, array() );
VHCP_Cfg::clear_cache();
VHCP_Cfg::cfg_static();
teq( '🔴 xoá hết LOẠI CHI PHÍ × MẢNG -> không dựng lại từ danh mục nhóm',
	0, count( VHCP_Cfg::read( VHCP_Cfg::LOAI ) ) );
VHCP_Cfg::clear_cache();
VHCP_Cfg::cfg_static();
teq( '   lượt tải sau nữa cũng vậy', 0, count( VHCP_Cfg::read( VHCP_Cfg::LOAI ) ) );

/* ⚠️ XOÁ BỚT, GIỮ LẠI MỘT (đúng cái nút "Dọn loại chưa khai mã" làm) -> phải giữ nguyên một. */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array( array( 'Chi phí máy tự động', '6278', '', '', '', '' ) ) );
VHCP_Cfg::clear_cache();
VHCP_Cfg::cfg_static();
teq( '🔴 giữ lại một loại -> vẫn đúng một, không trộn danh mục nhóm vào', 1,
	count( VHCP_Cfg::read( VHCP_Cfg::LOAI ) ) );

/* 🔴 MỌI NHÁNH "RỖNG THÌ DỰNG LẠI" CỦA DANH MỤC NGƯỜI DÙNG QUẢN PHẢI CÓ DẤU RIÊNG.
   Phép tĩnh này là cái bắt được ca "vá hai chỗ, sót chỗ thứ ba" — thứ vừa xảy ra thật. */
$cfg_ma2 = preg_replace( '#/\*[\s\S]*?\*/#', ' ',
	file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' ) );
foreach ( array(
	'COSO' => 'seeded_coso_v1',
	'NHOM' => 'seeded_nhom_v1',
	'LOAI' => 'seeded_loai_v1',
) as $bang_k => $dau ) {
	t( '🔴 nhánh dựng lại của ' . $bang_k . ' có dấu «' . $dau . '»',
		(bool) preg_match( '#rows_of\( \$all, self::' . $bang_k . ' \) \)\s*&&\s*! VHCP_Meta::get\( .'
			. $dau . '. \)#', $cfg_ma2 ), '' );
}

/* 🔴 NHƯNG BẢNG NGƯỜI DÙNG THÌ NGƯỢC LẠI, VÀ CỐ Ý NHƯ VẬY.
   Xoá sạch người dùng là tự khoá mình ngoài cửa VĨNH VIỄN — không còn PIN nào vào được để mà
   sửa. Dòng Admin gieo lại là đường cứu duy nhất. */
VHCP_Cfg::write( VHCP_Cfg::USER, array() );
VHCP_Cfg::seed();
$u_lai = VHCP_Cfg::read( VHCP_Cfg::USER );
t( '🔴 xoá sạch NGƯỜI DÙNG -> vẫn gieo lại Admin (đường cứu)',
	1 === count( $u_lai ) && 'Admin' === trim( (string) $u_lai[0][0] ), $u_lai );

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
