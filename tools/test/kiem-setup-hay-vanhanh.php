<?php
/**
 * SETUP HAY VẬN HÀNH — TRỤC RIÊNG CỦA MỘT DÒNG CHI
 * =============================================================================================
 *
 * Anh Thắng vẽ câu hỏi này trong sơ đồ tay 22/09/2026 — *"CP set up hay đã đi vào VH?"* — rồi
 * chốt: *"Thêm 1 ô tích (Chi Phí Setup, Chi Phí Vận Hành) để sau này xác định nó thuộc chi phí
 * nào"*.
 *
 * 🔴 ĐÂY LÀ TRỤC KHÁC, KHÔNG PHẢI MỘT NHÁNH CỦA CÂY DANH MỤC. Cùng một loại ("Chi phí điện
 *    nước") vừa phát sinh lúc setup vừa phát sinh lúc vận hành. Nhét vào danh mục loại chi phí
 *    là nhân đôi mọi loại, mà vẫn không trả lời được khi một dòng rơi vào cả hai.
 *
 * 🔴 RỖNG LÀ HỢP LỆ. Mọi dòng nhập trước bản này đều rỗng, và không ai đi khai lại cả trăm
 *    dòng cũ. Bắt buộc chọn là chặn đứng người nhập ngay lượt sửa một dòng cũ.
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

/* ── 1. CHUẨN HOÁ: chạy THẬT hàm của mã nguồn, không viết lại ─────────────────────────────── */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
preg_match( "/const GIAI_DOAN_DS = array\(([^)]*)\);/u", $src, $m1 );
preg_match( "/public static function giai_doan_chuan\( \\\$v \) \{(.*?)\n\t\}/su", $src, $m2 );
t( 'bốc được hằng và hàm chuẩn hoá từ mã nguồn', ! empty( $m1[1] ) && ! empty( $m2[1] ) );
eval( 'class G { const GIAI_DOAN_DS = array(' . $m1[1] . ');'
	. ' public static function giai_doan_chuan( $v ) {' . $m2[1] . "\n} }" );

teq( '🔴 "Setup" giữ nguyên', 'Setup', G::giai_doan_chuan( 'Setup' ) );
teq( '🔴 "Vận hành" giữ nguyên', 'Vận hành', G::giai_doan_chuan( 'Vận hành' ) );
teq( '🔴 rỗng là HỢP LỆ, không phải lỗi — dòng cũ đều rỗng', '', G::giai_doan_chuan( '' ) );
teq( '   khoảng trắng thừa vẫn nhận', 'Setup', G::giai_doan_chuan( '  Setup  ' ) );
teq( '   khác hoa/thường vẫn nhận', 'Setup', G::giai_doan_chuan( 'SETUP' ) );
teq( '   khác hoa/thường có dấu cũng nhận', 'Vận hành', G::giai_doan_chuan( 'vận hành' ) );
/* 🔴 Cột này rồi sẽ đứng trong câu gom báo cáo ("setup tốn bao nhiêu, vận hành bao nhiêu").
   Một giá trị lạ lọt qua là nó thành NHÓM THỨ BA, và tổng không bao giờ cộng đủ — chênh lệch
   ấy không kêu tiếng nào, chỉ sai số. */
teq( '🔴 giá trị lạ NGÃ VỀ RỖNG, không đẻ ra nhóm thứ ba', '', G::giai_doan_chuan( 'Set up' ) );
teq( '   kể cả một chuỗi hoàn toàn khác', '', G::giai_doan_chuan( 'Bảo trì' ) );
teq( '🔴 đúng HAI lựa chọn, không hơn', 2, count( G::GIAI_DOAN_DS ) );

/* ── 2. CẢ BỐN BẢN: cột trong sổ · đọc · ghi · gói khởi động ───────────────────────────────
   Đứt một chặng nào cũng ra cùng một cảnh: người nhập bấm nút, bấm Lưu, màn vẽ lại trông bình
   thường — rồi mở lại thấy trống. Bản vùng sinh lại từ bản gốc nên phải soi đủ bốn. */
foreach ( $BAN as $ban ) {
	$d  = $goc . '/wordpress/' . $ban . '/includes/';
	if ( ! is_dir( $d ) ) { t( "có $ban", false ); continue; }
	$db  = file_get_contents( $d . 'class-vhcp-db.php' );
	$don = file_get_contents( $d . 'class-vhcp-don.php' );

	t( "🔴 $ban: sổ dòng chi CÓ cột `giai_doan`",
		false !== strpos( $db, 'giai_doan VARCHAR(20)' ), null );
	t( "🔴 $ban: cột ấy `NOT NULL DEFAULT ''` — rỗng phải hợp lệ cho mọi dòng cũ",
		1 === preg_match( "/giai_doan VARCHAR\(20\) NOT NULL DEFAULT ''/", $db ), null );
	/* ⚠️ SOI NGUYÊN PHÉP GÁN. Soi mỗi chuỗi `'giaiDoan'` là khớp phải `$get( 'giaiDoan' )` bên
	   ĐƯỜNG GHI — gỡ hẳn đường ĐỌC mà phép vẫn xanh. Lượt đục bắt đúng chỗ ấy. */
	t( "🔴 $ban: ĐỌC cột ấy lên màn",
		false !== strpos( $don, "'giaiDoan'   => isset( \$x['giai_doan'] )" ), null );
	t( "🔴 $ban: GHI cột ấy xuống sổ — thiếu là bấm Lưu xong mất",
		false !== strpos( $don, "'giai_doan'    => self::giai_doan_chuan(" ), null );
	t( "🔴 $ban: ghi QUA hàm chuẩn hoá, không ghi thẳng cái màn gửi lên",
		false === strpos( $don, "'giai_doan'    => \$get( 'giaiDoan' )" ), null );
	t( "$ban: gói khởi động chở hai lựa chọn xuống màn",
		false !== strpos( $don, "'giaiDoanDs' => self::GIAI_DOAN_DS" ), null );

	/* ── 3. BÊN MÀN ──────────────────────────────────────────────────────────────────────── */
	$app = file_get_contents( $goc . '/wordpress/' . $ban . '/templates/app.html' );
	t( "🔴 $ban: form nhập CÓ ô Setup / Vận hành",
		false !== strpos( $app, 'id="f_giaiDoan"' ), null );
	t( "🔴 $ban: dòng gửi đi mang theo `giaiDoan` — thiếu là bấm nút xong không đi tới đâu",
		false !== strpos( $app, 'giaiDoan:GIAI_DOAN' ), null );
	t( "🔴 $ban: mở lại dòng cũ thì ĐỔ LẠI đúng lựa chọn đã khai",
		false !== strpos( $app, "GIAI_DOAN=String(l.giaiDoan||'')" ), null );
	/* 🔴 Không xoá là nhập tiếp hạng mục sau mang theo lốt của dòng trước — người ta không hề
	   bấm, và cũng không nhìn thấy, vì mắt đang ở ô Nội dung. */
	$reset = strstr( $app, 'function resetLineForm()' );
	$reset = false === $reset ? '' : substr( $reset, 0, 900 );
	t( "🔴 $ban: mở form mới thì XOÁ lựa chọn của dòng trước",
		false !== strpos( $reset, "GIAI_DOAN=''" ), $reset );
	/* Bấm lại nút đang sáng = bỏ chọn. Không có đường bỏ thì lỡ tay bấm là không gỡ ra được,
	   mà "chưa xác định" lại là một câu trả lời hợp lệ. */
	$pick = strstr( $app, 'function pickGiaiDoan(' );
	$pick = false === $pick ? '' : substr( $pick, 0, 300 );
	t( "🔴 $ban: bấm lại nút đang chọn thì BỎ CHỌN được",
		1 === preg_match( '/GIAI_DOAN===v\)\?\x27\x27:/u', $pick ), $pick );
	/* Hai lựa chọn lấy từ máy chủ, không gõ lại ở màn — gõ lại là hai bên lệch một dấu và
	   `giai_doan_chuan()` lẳng lặng ngã mọi dòng về rỗng. */
	t( "$ban: màn lấy hai lựa chọn TỪ máy chủ", false !== strpos( $app, 'BOOT.giaiDoanDs' ), null );

	/* ── 4. FORM GỌN LẠI ─────────────────────────────────────────────────────────────────── */
	$css = file_get_contents( $goc . '/wordpress/' . $ban . '/assets/css/vhcp.css' );
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
echo "✓ SẠCH — $DAT phép: Setup/Vận hành đi hết đường từ sổ ra màn, và form nhập gọn lại mà không đụng lưới chung.\n";
