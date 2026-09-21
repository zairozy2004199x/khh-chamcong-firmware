<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TK ĐỐI ỨNG KHAI THEO TỪNG LOẠI CHI PHÍ — CHẠY THẬT `tkco_xuat()`.
 *
 * Anh Thắng 21/09/2026: *"MTĐ tùy loại sẽ có TK đối ứng khác"*, kèm ảnh bản MISA mẫu ghi
 * Nợ 64136 / Có **331** — không phải 141 như đường tạm ứng bên Khu vui chơi.
 *
 * =============================================================================================
 * 🔴 VÌ SAO BÀI NÀY PHẢI VIẾT BẰNG PHP, KHÔNG PHẢI JS ĐỌC MÃ NGUỒN
 * =============================================================================================
 * Bản đầu em canh bằng `kiem-tk-doi-ung-theo-loai.js`, so THỨ TỰ CHỮ trong thân hàm: `$cat['tkCo']`
 * phải xuất hiện trước `$tk_dong`. Phá thử bằng cách đổi `if ( '' !== $khai )` thành
 * `if ( false )` — tức GỠ HẲN cái bậc quan trọng nhất, đúng thứ anh Thắng yêu cầu — mà bài
 * vẫn XANH: chữ vẫn nằm đúng chỗ, chỉ là không chạy nữa.
 *
 * Một bài kiểm không phân biệt được "mã có mặt" với "mã có chạy" thì không canh được gì. Nên
 * phần chốt chuyển sang đây, gọi thẳng hàm thật với danh mục thật.
 * (Bài .js vẫn giữ, nhưng chỉ còn canh phần MÀN HÌNH — ô nhập, chỗ đọc lúc Lưu, dòng trợ giúp.)
 *
 * Chạy: php tools/test/kiem-tk-doi-ung-theo-loai.php
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

/* Cột: ten · tkNo · tkCo · maDt · boPhan · note · tenMisa · loaiTt · donVi · khoi · vaiTro */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	/* KVC: KHÔNG khai TK đối ứng -> phải giữ nguyên luật cũ */
	array( 'Chi phí khác',  '6428',  '',    '', '', '', '', '', '', 'kvc' ),
	/* MTĐ: cùng tên, nhưng khai 331 -> đây là cả yêu cầu của anh Thắng lẫn bẫy trùng tên */
	array( 'Chi phí khác',  '64136', '331', '', '', '', '', '', '', 'mtd' ),
	/* Ghi với ô Khối RỖNG — tầng ghi phải lấp thành 'kvc' (xem phép canh mục 2). */
	array( 'Chi phí chung', '6427',  '336', '', '', '', '', '', '', ''    ),
	/* Ô khai chỉ có dấu cách — không được tính là đã khai */
	array( 'Chi phí trắng', '6421',  '   ', '', '', '', '', '', '', 'mtd' ),
), false );
VHCP_Cfg::clear_cache();

/* ═══ 1. BA BẬC, CHẠY THẬT ═══════════════════════════════════════════════════════ */
/* 🔴 Bậc 1 — mã khai ở danh mục THẮNG cả bản sao trên dòng. Đây là chính cái anh Thắng cần:
   67 cơ sở MTĐ nạp từ sổ cũ đều mang sẵn bản sao 141 trên dòng, khai xong phải xuất ra 331. */
teq( '🔴 MTĐ khai 331 → thắng bản sao 141 trên dòng cũ',
	'331', VHCP_Cfg::tkco_xuat( 'Chi phí khác', 'mtd', '141', '141' ) );
/* 🔴 Bậc 2/3 — KVC chưa khai thì KHÔNG đổi gì. Thêm một bậc mà làm đổi mã của sổ đang chạy là
   sai hàng loạt bút toán đã đối chiếu xong. */
teq( '🔴 KVC chưa khai → vẫn 141 y như cũ',
	'141', VHCP_Cfg::tkco_xuat( 'Chi phí khác', 'kvc', '141', '331' ) );
teq( '   chưa khai, dòng cũng trống → rơi về phân loại thanh toán',
	'331', VHCP_Cfg::tkco_xuat( 'Chi phí khác', 'kvc', '', '331' ) );
teq( '   trống cả ba → rỗng, để chỗ báo thiếu bắt',
	'', VHCP_Cfg::tkco_xuat( 'Chi phí khác', 'kvc', '', '' ) );
/* ⚠️ Kế toán gõ nhầm một dấu cách là mã đối ứng thành chuỗi trắng, và bút toán mang TK rỗng. */
teq( '⚠️ ô khai chỉ có dấu cách → coi như chưa khai',
	'141', VHCP_Cfg::tkco_xuat( 'Chi phí trắng', 'mtd', '141', '331' ) );

/* ═══ 2. 🔴 BẪY TRÙNG TÊN GIỮA HAI KHỐI ══════════════════════════════════════════
 * `loai_map()` khoá theo TÊN, dòng sau đè dòng trước — nên "Chi phí khác" của MTĐ (đứng sau)
 * sẽ đè lên của KVC. Không phân biệt khối là mọi dòng KVC xuất ra 331 của Máy tự động: im
 * lặng, và chỉ lộ ra ở tệp MISA đã nộp. */
teq( '🔴 KVC KHÔNG bị mã 331 của MTĐ đè lên',
	'141', VHCP_Cfg::tkco_xuat( 'Chi phí khác', 'kvc', '141', '141' ) );
$kvc = VHCP_Cfg::loai_row( 'Chi phí khác', 'kvc' );
$mtd = VHCP_Cfg::loai_row( 'Chi phí khác', 'mtd' );
teq( '   mỗi khối tra ra TK Nợ của chính mình (KVC)', '6428',  (string) $kvc['tkNo'] );
teq( '   mỗi khối tra ra TK Nợ của chính mình (MTĐ)', '64136', (string) $mtd['tkNo'] );

/* ⚠️ Dòng chưa khai khối là dòng DÙNG CHUNG — mọi khối tra ra nó, không phải rỗng. Danh mục
   dựng từ sổ cũ còn nhiều dòng bỏ trống ô Khối; bỏ chúng đi là loại có thật mà tra ra rỗng,
   rồi báo "thiếu TK" cho một thứ đã khai từ lâu. */
/* 🔴 Ô KHỐI KHÔNG BAO GIỜ RỖNG — nền móng của cả phép tra này.
   Ghi vào với ô trống thì tầng ghi lấp thành 'kvc' (cùng luật với `kiem-loai-theo-khoi.php`:
   *"rỗng = loại KHÔNG BẢNG NÀO CHỨA"*). Vì thế MỖI LOẠI THUỘC ĐÚNG MỘT KHỐI, và `loai_row()`
   không cần — không được phép có — nhánh "dòng dùng chung": nhánh ấy không lượt chạy nào tới
   được. Đã viết rồi gỡ, 21/09/2026.
   ⚠️ Phép này mà đỏ thì KHÔNG phải sửa nó — nghĩa là tầng ghi đổi luật, và cả `loai_row()` lẫn
      mọi chỗ đọc khối của loại phải xem lại. */
$khois = array();
foreach ( VHCP_Cfg::cfg_static()['loaiChiPhi'] as $x ) { $khois[] = (string) $x['khoi']; }
t( '🔴 ghi ô Khối rỗng → tầng ghi lấp, không loại nào còn khối rỗng',
	! in_array( '', $khois, true ), $khois );
teq( '   nên "Chi phí chung" (ghi rỗng) nằm ở KVC', '336', VHCP_Cfg::tkco_xuat( 'Chi phí chung', 'kvc', '', '' ) );

/* 🔴 KHÔNG NGÃ VỀ DÒNG CỦA KHỐI KHÁC. "Chi phí chung" chỉ có ở KVC; hỏi từ VP phải coi như
   CHƯA KHAI và rơi xuống bậc sau, chứ không mượn mã của KVC. Trả bừa chính là cái "đè lên
   nhau" mà phép tra này sinh ra để chặn, chỉ khác là lặng lẽ hơn. */
teq( '🔴 loại chỉ có ở KVC, hỏi từ VP → chưa khai, rơi về bậc sau',
	'141', VHCP_Cfg::tkco_xuat( 'Chi phí chung', 'vp', '141', '331' ) );
teq( '🔴 loại chỉ có ở MTĐ, hỏi từ VP → coi như chưa khai, rơi về bậc sau',
	'141', VHCP_Cfg::tkco_xuat( 'Chi phí trắng', 'vp', '141', '331' ) );
t( '   và `loai_row()` trả null chứ không trả dòng của MTĐ',
	null === VHCP_Cfg::loai_row( 'Chi phí trắng', 'vp' ), VHCP_Cfg::loai_row( 'Chi phí trắng', 'vp' ) );

/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG đã thử và ghi lại: bỏ `trim()` quanh ô khai trong `tkco_xuat()`
   KHÔNG làm bài này đỏ, vì `VHCP_Cfg::write()` đã cho ô ấy qua `VHCP_Util::ma_so()` nên giá trị
   đọc lên từ kho không bao giờ còn khoảng trắng. Phép dưới canh đúng cái bảo đảm THẬT — tầng
   ghi cắt khoảng trắng — chứ không giả vờ canh `trim()` trong hàm. */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array_merge( VHCP_Cfg::read( VHCP_Cfg::LOAI ),
	array( array( 'Chi phí cách', '6441', '  332  ', '', '', '', '', '', '', 'vp' ) ) ), false );
VHCP_Cfg::clear_cache();
teq( '⚠️ tầng GHI cắt khoảng trắng của ô mã', '332', VHCP_Cfg::tkco_xuat( 'Chi phí cách', 'vp', '141', '141' ) );

/* ⚠️ Loại không có trong danh mục → không được nổ, phải rơi xuống hai bậc sau. */
teq( '⚠️ loại lạ (không có trong danh mục) → rơi về bản sao trên dòng',
	'141', VHCP_Cfg::tkco_xuat( 'Loại chưa từng khai', 'mtd', '141', '331' ) );

/* ═══ 3. 🔴 NGƯỜI GỌI CŨ KHÔNG ĐƯỢC ĐỔI CÂU TRẢ LỜI ═════════════════════════════
 * `loai_tk($ten)` không truyền khối là đường mà MỌI nơi khác trong app đang đi (resolve_tk,
 * sổ chi phí, kỹ thuật, marketing…). Thêm một tham số mà làm đổi kết quả của nó là hỏng ngầm
 * trên toàn bộ sổ — nên nó phải trả về đúng dòng `loai_map()` giữ lại, y như trước. */
$m  = VHCP_Cfg::loai_map();
$cu = VHCP_Cfg::loai_tk( 'Chi phí khác' );
teq( '🔴 `loai_tk()` không truyền khối → y hệt luật cũ của `loai_map()`',
	(string) $m['chi phí khác']['tkNo'], (string) $cu['tkNo'] );
teq( '   (và đó là dòng ĐỨNG SAU — MTĐ — đúng như trước bản này)', '64136', (string) $cu['tkNo'] );

/* ═══ 4. 🔴 CHẠY THẬT CẢ BẢN XUẤT MISA ══════════════════════════════════════════
 * Hai phép trên mới chỉ canh cái hàm. Phép này canh việc bản xuất CÓ GỌI nó, và gọi với KHỐI
 * CỦA ĐƠN — không phải khối của người đang bấm Xuất. Từ 1.245.0 kế toán KVC xuất hộ đơn MTĐ,
 * nên lấy khối người bấm là tra nhầm bảng cho đúng những đơn vừa bàn giao sang. */
if ( ! function_exists( 'vhcp_test_don_mau' ) ) {
	function vhcp_test_don_mau( $khoi, $loai, $tk_co_dong ) {
		global $wpdb;
		$ma = 'DT' . strtoupper( substr( md5( $khoi . $loai . $tk_co_dong ), 0, 6 ) );
		$wpdb->insert( VHCP_DB::t( 'don' ), array(
			'ma_don' => $ma, 'ky' => '01/09/2026', 'nguoi_lap' => 'NV Thử', 'don_vi' => '', 'khoi' => $khoi,
			'ngay_tao' => '2026-09-01 08:00:00', 'trang_thai' => 'Đã quyết toán', 'nguoi_qt' => 'KT Thử',
			'ngay_qt' => '2026-09-02 08:00:00', 'ghi_chu' => '',
		) );
		$wpdb->insert( VHCP_DB::t( 'chiphi' ), array(
			'ma_don' => $ma, 'ngay' => '2026-09-01', 'coso' => 'GIAN THỬ', 'nhom' => $loai,
			'noi_dung' => 'dòng thử', 'thanh_tien' => 1000000, 'phan_loai_tt' => 'Thanh toán cá nhân',
			'tk_no' => '', 'tk_co' => $tk_co_dong, 'doi_tuong' => '',
		) );
		return $ma;
	}
}
$ma_mtd = vhcp_test_don_mau( 'mtd', 'Chi phí khác', '141' );
$ma_kvc = vhcp_test_don_mau( 'kvc', 'Chi phí khác', '141' );
VHCP_Cfg::clear_cache();
$out = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
$cot = array_search( 'TK Có', $out['cols'], true );
if ( false === $cot ) { $cot = 6; }   // sơ đồ cột cố định: …, TK Nợ(5), TK Có(6), Số tiền(7)
$thay = array();
foreach ( $out['rows'] as $r ) { $thay[] = (string) $r[ $cot ]; }
sort( $thay );
t( '🔴 bản xuất thật ra ĐÚNG HAI mã: 141 cho KVC, 331 cho MTĐ',
	$thay === array( '141', '331' ), array( 'thấy' => $thay, 'sodon' => $out['sodon'] ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( count( $TRUOT ) ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: TK đối ứng theo loại chạy thật, tra đúng khối, loại chưa khai không đổi gì.\n";
