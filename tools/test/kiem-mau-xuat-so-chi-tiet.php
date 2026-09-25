<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MẪU XUẤT MISA CHO MÁY TỰ ĐỘNG / VĂN PHÒNG — SỔ CHI TIẾT TÀI KHOẢN, 13 CỘT.
 *
 * Anh Thắng 21/09/2026 gửi ảnh *"Mẫu xuất Misa MTĐ và VP"*: Ngày hạch toán · Ngày chứng từ ·
 * Số chứng từ · Diễn giải chung · Diễn giải · TK đối ứng · Phát sinh Nợ · Phát sinh Có · Dư Nợ ·
 * Dư Có · Mã đối tượng · Mã đơn vị · Tên đơn vị.
 *
 * Khác hẳn mẫu 10 cột đang chạy (nhật ký chung: TK Nợ và TK Có cạnh nhau, số tiền một cột).
 *
 * =============================================================================================
 * 🔴 MỘT LƯỢT GOM, HAI HÌNH DẠNG — KHÔNG VIẾT HÀM XUẤT THỨ HAI
 * =============================================================================================
 * Cả hai mẫu dùng chung toàn bộ phần khó: chốt TK Nợ (ma trận loại × mảng), chốt TK đối ứng
 * (`tkco_xuat`), mã đối tượng, mã đơn vị, cảnh báo thiếu mã, gom theo mảng, sắp theo ngày. Chép
 * đôi phần ấy là hai bản hạch toán trôi lệch nhau, và cái lệch chỉ lộ ra khi hai tệp cùng nộp
 * cho một kỳ. `$mau` vì thế chỉ đổi ĐÚNG hai thứ: danh sách cột, và cách xếp một dòng.
 *
 * 🔴 MẶC ĐỊNH PHẢI LÀ MẪU CŨ. Thêm tham số mà đổi hình dạng tệp của người gọi cũ là kế toán nộp
 *    nhầm mẫu cho MISA — bài này canh cả điều đó.
 *
 * Chạy: php tools/test/kiem-mau-xuat-so-chi-tiet.php
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

/* ═══ 0. SƠ ĐỒ CỘT ĐÚNG NHƯ ẢNH ANH GỬI ══════════════════════════════════════════ */
$MONG = array( 'Ngày hạch toán', 'Ngày chứng từ', 'Số chứng từ', 'Diễn giải chung', 'Diễn giải',
	'TK đối ứng', 'Phát sinh Nợ', 'Phát sinh Có', 'Dư Nợ', 'Dư Có', 'Mã đối tượng', 'Mã đơn vị', 'Tên đơn vị' );
teq( '🔴 mẫu sổ chi tiết đúng 13 cột, đúng thứ tự', $MONG, VHCP_Misa::cols( VHCP_Misa::MAU_SOCT ) );
/* 🔴 Mẫu cũ KHÔNG được đổi một chữ — tệp của Khu vui chơi đang chạy hằng tuần. */
teq( '🔴 mẫu cũ vẫn đúng 10 cột', 10, count( VHCP_Misa::cols() ) );
teq( '🔴 và `cols()` không truyền gì thì vẫn là mẫu cũ', VHCP_Misa::cols( VHCP_Misa::MAU_CHUAN ), VHCP_Misa::cols() );
/* ⚠️ Tham số lạ phải ngã về mẫu cũ, không nổ và không ra mẫu mới. */
teq( '⚠️ tham số lạ → ngã về mẫu cũ', VHCP_Misa::cols(), VHCP_Misa::cols( 'linh tinh' ) );

/* ═══ 1. DỰNG SỔ THẬT ════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Chi phí khác', '64136', '331', '', '', '', '', '', '', 'mtd' ),
), false );
VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	array( 'GIAN THỬ', 'MTD01', 'POSH MN', 'POSH MN Vincom Thủ Đức', '', 'mtd' ),
), false );
VHCP_Cfg::clear_cache();
global $wpdb;
$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => 'D777', 'ky' => '01/09/2026', 'nguoi_lap' => 'NV Thử',
	'don_vi' => '', /* 🔴 `Đã thanh toán`, KHÔNG phải `Đã quyết toán`. Từ 21/09/2026 đơn MTĐ chỉ ra bản xuất SAU
	   khi đánh dấu thanh toán — anh Thắng chốt thanh toán và xuất MISA là *"hai bước tách rời"*.
	   Để `Đã quyết toán` ở đây là bài kiểm này xanh trong khi luật thật đã đổi. */
	'khoi' => 'mtd', 'ngay_tao' => '2026-09-01 08:00:00', 'trang_thai' => 'Đã thanh toán',
	'nguoi_qt' => 'KT Thử', 'ngay_qt' => '2026-09-02 08:00:00', 'ghi_chu' => '' ) );
$wpdb->insert( VHCP_DB::t( 'chiphi' ), array( 'ma_don' => 'D777', 'ngay' => '2026-09-01', 'coso' => 'GIAN THỬ',
	'nhom' => 'Chi phí khác', 'noi_dung' => 'Thay bo mach ghe', 'thanh_tien' => 2500000,
	'phan_loai_tt' => 'Thanh toán cá nhân', 'tk_no' => '', 'tk_co' => '141', 'doi_tuong' => '' ) );

$o = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', VHCP_Misa::MAU_SOCT );
teq( 'xuất ra đúng 1 dòng', 1, count( $o['rows'] ) );
$r = $o['rows'][0];
$c = array_flip( $o['cols'] );

/* ═══ 2. TỪNG Ô ══════════════════════════════════════════════════════════════════ */
teq( 'Ngày hạch toán', '01/09/2026', $r[ $c['Ngày hạch toán'] ] );
teq( 'Ngày chứng từ',  '01/09/2026', $r[ $c['Ngày chứng từ'] ] );
/* ⚠️ Mẫu cũ để trống Số chứng từ (kế toán tự đánh số khi nạp). Sổ chi tiết thì dò ngược theo
   chứng từ là việc hằng ngày — không có mã đơn thì một dòng lệch không truy được về đơn nào. */
teq( '⚠️ Số chứng từ mang MÃ ĐƠN (mẫu cũ để trống)', 'D777', $r[ $c['Số chứng từ'] ] );

/* 🔴 ĐÂY LÀ CẢ LÝ DO CÓ MẪU NÀY: "Nợ 64136 / Có 331" trong ảnh anh gửi.
   `331` tới được đây là nhờ TK đối ứng khai theo loại (1.246.0) — dòng chi vẫn mang bản sao
   `141` từ lúc nhập, và nếu bậc ấy hỏng thì ô này ra 141. */
teq( '🔴 TK đối ứng = 331, thắng bản sao 141 trên dòng', '331', $r[ $c['TK đối ứng'] ] );
teq( '🔴 Phát sinh Nợ mang số tiền', 2500000.0, $r[ $c['Phát sinh Nợ'] ] );
teq( '🔴 Phát sinh Có = 0', 0, $r[ $c['Phát sinh Có'] ] );
/* ⚠️ Dư Nợ / Dư Có ĐỂ TRỐNG, cố ý: số dư luỹ kế phụ thuộc cả bút toán KHÔNG do app sinh ra
   (tiền về, bù trừ, kết chuyển cuối kỳ). Tự cộng ở đây là bịa ra một con số chỉ đúng nếu app
   này là nguồn duy nhất của tài khoản — mà nó không phải. */
teq( '⚠️ Dư Nợ để trống', '', $r[ $c['Dư Nợ'] ] );
teq( '⚠️ Dư Có để trống', '', $r[ $c['Dư Có'] ] );
teq( 'Mã đơn vị',  'MTD01', $r[ $c['Mã đơn vị'] ] );
teq( '🔴 Tên đơn vị lấy Tên MISA của cơ sở', 'POSH MN Vincom Thủ Đức', $r[ $c['Tên đơn vị'] ] );

/* ═══ 3. 🔴 MẪU CŨ KHÔNG ĐƯỢC ĐỘNG VÀO ═══════════════════════════════════════════ */
$cu = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
teq( '🔴 không truyền mẫu → vẫn 10 cột như trước', 10, count( $cu['cols'] ) );
teq( '🔴 và dòng vẫn đủ CẢ TK Nợ lẫn TK Có', array( '64136', '331' ),
	array( (string) $cu['rows'][0][5], (string) $cu['rows'][0][6] ) );
teq( '   số tiền vẫn nằm ở cột 8', 2500000.0, $cu['rows'][0][7] );
/* 🔴 HAI MẪU PHẢI CÙNG MỘT PHÉP HẠCH TOÁN. Đây là chốt của "một lượt gom": TK đối ứng của mẫu
   mới phải BẰNG TK Có của mẫu cũ, và số tiền phải bằng nhau. Lệch nghĩa là ai đó đã chép đôi
   phần chốt mã — đúng thứ thiết kế này sinh ra để chặn. */
teq( '🔴 TK đối ứng (mẫu mới) == TK Có (mẫu cũ)', (string) $cu['rows'][0][6], (string) $r[ $c['TK đối ứng'] ] );
teq( '🔴 số tiền hai mẫu bằng nhau', $cu['rows'][0][7], $r[ $c['Phát sinh Nợ'] ] );
teq( '   và cùng số đơn', $cu['sodon'], $o['sodon'] );

/* ═══ 3b. 🔴 LỌC THEO TK NỢ — SỔ CHI TIẾT LÀ SỔ CỦA MỘT TÀI KHOẢN ════════════════
 * 13 cột mẫu KHÔNG có chỗ nào ghi số hiệu tài khoản: nó nằm ở tiêu đề sổ. Đúng với một lượt
 * xuất đã lọc về một tài khoản. Nhưng nếu lượt xuất ôm cả 64136 lẫn 6427 thì tệp ra TRỘN
 * CHUNG mà không phân biệt được dòng nào của tài khoản nào — người nhận cộng nhầm, và không
 * có gì trên tệp báo cho họ. Nên hai hình dạng, mỗi cái đúng trong cảnh của nó. */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array_merge( VHCP_Cfg::read( VHCP_Cfg::LOAI ),
	array( array( 'Chi phí chung', '6427', '331', '', '', '', '', '', '', 'mtd' ) ) ), false );
VHCP_Cfg::clear_cache();
$wpdb->insert( VHCP_DB::t( 'chiphi' ), array( 'ma_don' => 'D777', 'ngay' => '2026-09-01', 'coso' => 'GIAN THỬ',
	'nhom' => 'Chi phí chung', 'noi_dung' => 'Dau may', 'thanh_tien' => 900000,
	'phan_loai_tt' => 'Thanh toán cá nhân', 'tk_no' => '', 'tk_co' => '141', 'doi_tuong' => '' ) );

$gop = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', VHCP_Misa::MAU_SOCT, 'all' );
teq( '🔴 trộn nhiều TK → thêm cột `TK Nợ` ở ĐẦU', 'TK Nợ', $gop['cols'][0] );
teq( '   và thành 14 cột', 14, count( $gop['cols'] ) );
/* ⚠️ Cột thêm đứng ĐẦU chứ không chèn giữa: người quen mẫu cũ vẫn đọc được 13 cột sau nó theo
   đúng thứ tự cũ. */
teq( '⚠️ 13 cột mẫu giữ nguyên thứ tự, nằm sau cột thêm', $MONG, array_slice( $gop['cols'], 1 ) );
teq( '   hai dòng của hai tài khoản', 2, count( $gop['rows'] ) );

$loc = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', VHCP_Misa::MAU_SOCT, '64136' );
teq( '🔴 lọc một TK → đúng 13 cột, khớp nguyên văn mẫu MISA', $MONG, $loc['cols'] );
teq( '🔴 và chỉ còn dòng của tài khoản ấy', 1, count( $loc['rows'] ) );
teq( '   đúng số tiền của nó', 2500000.0, $loc['rows'][0][ array_search( 'Phát sinh Nợ', $loc['cols'], true ) ] );
/* ⚠️ Ô lọc trên màn đổ từ mã CÓ MẶT trong kỳ, không phải từ danh mục: danh mục có hàng chục mã
   mà phần lớn không phát sinh, bày hết là kế toán dò giữa một danh sách quá nửa chọn vào ra
   tệp rỗng. */
$ds = $gop['tkDs']; sort( $ds, SORT_NATURAL );
teq( '⚠️ bản xuất kê đúng những TK Nợ CÓ MẶT', array( '6427', '64136' ), $ds );
/* 🔴 PHẢI LÀ CHUỖI, KHÔNG PHẢI SỐ. Khoá mảng PHP tự đổi '6427' thành số nguyên 6427 — màn so
   `o.value` (chuỗi) là hụt, và mã mang số 0 đứng đầu thì một lượt ép số là MẤT nó. Phép này
   canh đúng cái ép kiểu ở `export_misa()`; nó từng sai thật, 21/09/2026. */
t( '🔴 danh sách TK là CHUỖI (mã có số 0 đứng đầu không bị ăn mất)',
	$ds === array_map( 'strval', $ds ) && is_string( $ds[0] ), $ds );
teq( '   và nói nó đang lọc mã nào', '64136', $loc['tkLoc'] );
teq( '   "mọi TK" thì ô lọc rỗng',    '',      $gop['tkLoc'] );
/* ⚠️ `'all'` và chuỗi rỗng phải cùng nghĩa — màn gửi 'all', người gọi khác có thể gửi ''. */
teq( '⚠️ `all` và rỗng cùng nghĩa "mọi TK"', count( $gop['cols'] ),
	count( VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', VHCP_Misa::MAU_SOCT, '' )['cols'] ) );
/* 🔴 MẪU CŨ KHÔNG CÓ KHÁI NIỆM NÀY — nhật ký chung đã có sẵn cột TK Nợ, thêm nữa là hai cột
   cùng nghĩa cạnh nhau. */
teq( '🔴 mẫu nhật ký chung KHÔNG đổi dù truyền tham số lọc', 10,
	count( VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', VHCP_Misa::MAU_CHUAN, 'all' )['cols'] ) );

/* ═══ 4. BẢN XUẤT NÓI RÕ NÓ LÀ MẪU NÀO ═══════════════════════════════════════════
 * Màn dùng ô này để đặt tên tệp và để biết cột nào là cột tiền. Thiếu nó thì tệp sổ chi tiết
 * mang tên của mẫu cũ, và hai tệp nằm cạnh nhau trong thư mục Tải về không phân biệt được. */
teq( '🔴 kết quả mang theo tên mẫu (mới)', 'soct', $o['mau'] );
teq( '🔴 …và mẫu cũ',                      'chuan', $cu['mau'] );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( count( $TRUOT ) ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: mẫu sổ chi tiết 13 cột chạy thật, mẫu cũ không đổi, hai mẫu cùng một phép hạch toán.\n";
