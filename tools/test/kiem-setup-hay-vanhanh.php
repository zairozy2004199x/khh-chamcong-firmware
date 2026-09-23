<?php
/**
 * SETUP HAY VẬN HÀNH — TRỤC RIÊNG CỦA MỘT DÒNG CHI
 * =============================================================================================
 *
 * Anh Thắng vẽ câu hỏi này trong sơ đồ tay 22/09/2026 — *"CP set up hay đã đi vào VH?"* — rồi
 * chốt: *"Thêm 1 ô tích (Chi Phí Setup, Chi Phí Vận Hành) để sau này xác định nó thuộc chi phí
 * nào"*, và cùng ngày chốt tiếp CƠ CHẾ: *"Nếu tích vào thì gọi là chi phí setup ban đầu. Còn ko
 * tích thì mặc định và chi phí vận hành"*.
 *
 * 🔴 ĐÂY LÀ TRỤC KHÁC, KHÔNG PHẢI MỘT NHÁNH CỦA CÂY DANH MỤC. Cùng một loại ("Chi phí điện
 *    nước") vừa phát sinh lúc setup vừa phát sinh lúc vận hành. Nhét vào danh mục loại chi phí
 *    là nhân đôi mọi loại, mà vẫn không trả lời được khi một dòng rơi vào cả hai.
 *
 * =============================================================================================
 * 🔴 HAI HÀM, HAI VIỆC — VÀ ĐÓ LÀ CHỖ DỄ GỘP NHẦM NHẤT
 * =============================================================================================
 *   `giai_doan_chuan()` = "màn gửi lên cái gì thì GHI cái gì". Rỗng vẫn ghi rỗng — đừng bịa ra
 *     dữ liệu cho một lượt gọi không hề khai ô này.
 *   `giai_doan_doc()`   = "dòng trong sổ ĐỌC ra là gì". Rỗng -> **Vận hành**.
 *
 * Gộp làm một là mọi lượt lưu đều đóng dấu "Vận hành" lên cả những đường gọi chỉ sửa một ô khác
 * — tức tự bịa dữ liệu, im lặng. Tách ra thì nghĩa mới của ô rỗng chỉ là một LUẬT ĐỌC, sửa ở
 * một chỗ, không phải một lượt `UPDATE` quét cả bảng mà không lùi lại được.
 *
 * Chạy: php tools/test/kiem-setup-hay-vanhanh.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

$BAN = array( 'vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp' );

/* ── 1. CHUẨN HOÁ: chạy THẬT hàm của plugin, không bóc ra eval lại ─────────────────────────
   🔴 ĐỔI 22/09/2026. Bản trước bóc thân hàm bằng biểu thức chính quy rồi `eval` vào một lớp
      giả. Nay luật nằm ở khung `VHCP_Truc`, nên bản bóc ấy gọi một lớp không có trong ngữ cảnh
      giả và cả bài chết cứng. Nạp plugin thật rồi gọi thẳng — vừa gọn hơn, vừa không còn một
      bản sao nào để trôi lệch. */
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
class G {
	const GIAI_DOAN_DS = VHCP_Don::GIAI_DOAN_DS;
	public static function giai_doan_chuan( $v ) { return VHCP_Don::giai_doan_chuan( $v ); }
	public static function giai_doan_doc( $v ) { return VHCP_Don::giai_doan_doc( $v ); }
}
t( 'nạp được plugin thật', class_exists( 'VHCP_Don' ) && class_exists( 'VHCP_Truc' ) );

/* ── 1. CHUẨN HOÁ: chạy THẬT hàm của mã nguồn, không viết lại ─────────────────────────────── */
/* ── 1a. GHI: màn gửi gì thì ghi nấy, rỗng vẫn ghi rỗng ────────────────────────────────── */
teq( '🔴 "Setup" giữ nguyên', 'Setup', G::giai_doan_chuan( 'Setup' ) );
teq( '🔴 "Vận hành" giữ nguyên', 'Vận hành', G::giai_doan_chuan( 'Vận hành' ) );
/* 🔴 ĐƯỜNG GHI KHÔNG ĐƯỢC BỊA. Một lượt gọi chỉ sửa ô khác mà không khai ô này thì nó gửi rỗng;
   lấp "Vận hành" vào đó là tự đóng dấu lên dữ liệu người ta không hề động tới. Nghĩa mới của ô
   rỗng nằm ở ĐƯỜNG ĐỌC (`giai_doan_doc`), không nằm ở đây. */
teq( '🔴 đường GHI: rỗng vẫn ghi rỗng, không tự bịa "Vận hành"', '', G::giai_doan_chuan( '' ) );
teq( '   khoảng trắng thừa vẫn nhận', 'Setup', G::giai_doan_chuan( '  Setup  ' ) );
teq( '   khác hoa/thường vẫn nhận', 'Setup', G::giai_doan_chuan( 'SETUP' ) );
teq( '   khác hoa/thường có dấu cũng nhận', 'Vận hành', G::giai_doan_chuan( 'vận hành' ) );
/* 🔴 Cột này rồi sẽ đứng trong câu gom báo cáo ("setup tốn bao nhiêu, vận hành bao nhiêu").
   Một giá trị lạ lọt qua là nó thành NHÓM THỨ BA, và tổng không bao giờ cộng đủ — chênh lệch
   ấy không kêu tiếng nào, chỉ sai số. */
teq( '🔴 giá trị lạ NGÃ VỀ RỖNG, không đẻ ra nhóm thứ ba', '', G::giai_doan_chuan( 'Set up' ) );
teq( '   kể cả một chuỗi hoàn toàn khác', '', G::giai_doan_chuan( 'Bảo trì' ) );
teq( '🔴 đúng HAI lựa chọn, không hơn', 2, count( G::GIAI_DOAN_DS ) );

/* ── 1b. 🔴 ĐỌC: RỖNG NGHĨA LÀ VẬN HÀNH ───────────────────────────────────────────────────
 * Anh Thắng 22/09/2026: *"ko tích thì mặc định và chi phí vận hành"*. Mọi dòng nhập trước bản
 * này đều rỗng — và chúng là chi phí vận hành thật, không phải "chưa xác định". */
teq( '🔴 ĐỌC: rỗng = Vận hành (mặc định anh Thắng chốt)', 'Vận hành', G::giai_doan_doc( '' ) );
teq( '   dòng đã khai Setup thì ĐỌC ra Setup', 'Setup', G::giai_doan_doc( 'Setup' ) );
teq( '   dòng đã khai Vận hành thì giữ nguyên', 'Vận hành', G::giai_doan_doc( 'Vận hành' ) );
teq( '🔴 giá trị lạ cũng về Vận hành — không có nhóm thứ ba nào cả', 'Vận hành', G::giai_doan_doc( 'Bảo trì' ) );
teq( '   và chỉ chữ SETUP mới ra Setup', 'Vận hành', G::giai_doan_doc( 'Set up' ) );
/* 🔴 PHÉP ĐỐI CHỨNG: hai hàm phải KHÁC nhau ở đúng ô rỗng. Gộp làm một là hoặc đường ghi tự
   bịa dữ liệu, hoặc đường đọc trả lại một nhóm thứ ba đã bỏ. */
t( '🔴 hai hàm KHÁC nhau ở ô rỗng — gộp làm một là hỏng một trong hai đầu',
	G::giai_doan_chuan( '' ) !== G::giai_doan_doc( '' ), null );

/* ── 2. CẢ BỐN BẢN: CỘT TRONG SỔ, VÀ LƯỚI FORM ────────────────────────────────────────────
   🔴 PHẠM VI BÀI NÀY THU LẠI 22/09/2026. Đường dây "màn -> ghi -> đọc -> gói khởi động" nay đi
      qua khung `VHCP_Truc`, và `kiem-truc-phan-tich.php` canh nó cho MỌI trục — canh lại ở đây
      là hai bài ghim cùng một chuỗi, tức hai chỗ phải nhớ sửa mỗi lần đổi khung.
      Bài này ở lại với thứ RIÊNG của trục Giai đoạn: cột trong sổ, và cái lưới form. */
foreach ( $BAN as $ban ) {
	$d = $goc . '/wordpress/' . $ban . '/';
	if ( ! is_dir( $d . 'includes' ) ) { t( "có $ban", false ); continue; }
	$db  = file_get_contents( $d . 'includes/class-vhcp-db.php' );
	$app = file_get_contents( $d . 'templates/app.html' );
	$css = file_get_contents( $d . 'assets/css/vhcp.css' );

	t( "🔴 $ban: sổ dòng chi CÓ cột `giai_doan`",
		false !== strpos( $db, 'giai_doan VARCHAR(20)' ), null );
	/* ⚠️ RỖNG PHẢI HỢP LỆ cho mọi dòng cũ — và từ 22/09/2026 rỗng ĐỌC ra "Vận hành". */
	t( "🔴 $ban: cột ấy `NOT NULL DEFAULT ''` — rỗng phải hợp lệ cho mọi dòng cũ",
		1 === preg_match( "/giai_doan VARCHAR\(20\) NOT NULL DEFAULT ''/", $db ), null );

	/* ── 3. FORM GỌN LẠI ─────────────────────────────────────────────────────────────────── */
	t( "🔴 $ban: form nhập dùng lưới RIÊNG, không sửa `.grid` dùng chung",
		false !== strpos( $app, 'class="grid grid-nhap"' )
		&& false !== strpos( $css, '.grid-nhap{' ), null );
	t( "🔴 $ban: và lưới ấy CHẶN BỀ NGANG — ít cột mà vẫn giãn hết màn thì form dài như cũ",
		1 === preg_match( '/\.grid-nhap\{[^}]*max-width:\s*\d+px/u', $css ), null );
	t( "$ban: `.grid` dùng chung KHÔNG bị đụng vào",
		false !== strpos( $css, '.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}' ), null );
}

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "✓ SẠCH — $DAT phép: Setup/Vận hành — luật GHI/ĐỌC, cột trong sổ, và lưới form riêng.\n";
