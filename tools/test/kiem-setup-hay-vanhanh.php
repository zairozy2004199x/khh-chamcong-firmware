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

/* ── 1. CHUẨN HOÁ: chạy THẬT hàm của mã nguồn, không viết lại ─────────────────────────────── */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
preg_match( "/const GIAI_DOAN_SETUP = '([^']*)';/u", $src, $mS );
preg_match( "/const GIAI_DOAN_VH    = '([^']*)';/u", $src, $mV );
preg_match( "/public static function giai_doan_chuan\( \\\$v \) \{(.*?)\n\t\}/su", $src, $m2 );
preg_match( "/public static function giai_doan_doc\( \\\$v \) \{(.*?)\n\t\}/su", $src, $m3 );
t( 'bốc được hai hằng và hai hàm từ mã nguồn',
	! empty( $mS[1] ) && ! empty( $mV[1] ) && ! empty( $m2[1] ) && ! empty( $m3[1] ) );
eval( 'class G { const GIAI_DOAN_SETUP = ' . var_export( $mS[1], true ) . ';'
	. ' const GIAI_DOAN_VH = ' . var_export( $mV[1], true ) . ';'
	. ' const GIAI_DOAN_DS = array( self::GIAI_DOAN_SETUP, self::GIAI_DOAN_VH );'
	. ' public static function giai_doan_chuan( $v ) {' . $m2[1] . "\n}"
	. ' public static function giai_doan_doc( $v ) {' . $m3[1] . "\n} }" );

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
	t( "🔴 $ban: ĐỌC cột ấy lên màn, VÀ đọc qua `giai_doan_doc()`",
		false !== strpos( $don, "'giaiDoan'   => self::giai_doan_doc(" ), null );
	t( "🔴 $ban: GHI cột ấy xuống sổ — thiếu là bấm Lưu xong mất",
		false !== strpos( $don, "'giai_doan'    => self::giai_doan_chuan(" ), null );
	t( "🔴 $ban: ghi QUA hàm chuẩn hoá, không ghi thẳng cái màn gửi lên",
		false === strpos( $don, "'giai_doan'    => \$get( 'giaiDoan' )" ), null );
	t( "$ban: gói khởi động chở hai lựa chọn xuống màn",
		false !== strpos( $don, "'giaiDoanDs' => self::GIAI_DOAN_DS" ), null );
	/* 🔴 ĐƯA TÊN TỪNG GIÁ TRỊ XUỐNG. Ô nhập nay là MỘT ô tích, nên màn phải biết đích danh
	   chuỗi nào là "setup"; lấy theo chỗ đứng trong danh sách là đảo hai phần tử một cái thì
	   mọi dòng mới đóng dấu ngược, im lặng. */
	t( "🔴 $ban: và chở TÊN từng giá trị, để màn khỏi lấy theo chỗ đứng",
		false !== strpos( $don, "'giaiDoanSetup' => self::GIAI_DOAN_SETUP" )
		&& false !== strpos( $don, "'giaiDoanVh'    => self::GIAI_DOAN_VH" ), null );

	/* ── 3. BÊN MÀN ──────────────────────────────────────────────────────────────────────── */
	$app = file_get_contents( $goc . '/wordpress/' . $ban . '/templates/app.html' );
	t( "🔴 $ban: form nhập CÓ ô Giai đoạn",
		false !== strpos( $app, 'id="f_giaiDoan"' ), null );
	t( "🔴 $ban: dòng gửi đi mang theo `giaiDoan` — thiếu là tích xong không đi tới đâu",
		false !== strpos( $app, 'giaiDoan:GIAI_DOAN' ), null );
	/* 🔴 MỘT Ô TÍCH THẬT, không phải hai nút, cũng không phải một cái nút giả trông như ô tích.
	   Ô tích thật thì bàn phím Tab tới được, trình đọc màn hình đọc đúng trạng thái, và trên
	   điện thoại hệ điều hành tự nới vùng chạm. */
	t( "🔴 $ban: ô Giai đoạn là MỘT Ô TÍCH thật",
		false !== strpos( $app, '<input type="checkbox" id="f_gdSetup"' ), null );
	t( "   và nói rõ không tích nghĩa là gì",
		false !== strpos( $app, 'Không tích = chi phí <b>vận hành</b> (mặc định)' ), null );
	/* ⚠️ Nhãn bọc cả ô — chạm vào chữ cũng tích được. Vùng chạm 14px là thứ không ai bấm trúng
	   bằng ngón tay; xem `kiem-man-dien-thoai.py` về mốc 44px. */
	t( "⚠️ $ban: nhãn bọc cả ô tích, và vùng chạm đủ cao",
		1 === preg_match( '/<label[^>]*min-height:32px[^>]*>\s*\x27\s*\+\s*\x27<input type="checkbox" id="f_gdSetup"/u', $app )
		|| 1 === preg_match( '/min-height:32px;cursor:pointer/u', $app ), null );
	t( "🔴 $ban: KHÔNG còn hai nút cũ", false === strpos( $app, 'function pickGiaiDoan(' ), null );

	t( "🔴 $ban: mở lại dòng cũ thì ĐỔ LẠI đúng lựa chọn đã khai, qua hàm ĐỌC",
		false !== strpos( $app, 'GIAI_DOAN=_giaiDoanDoc(l.giaiDoan)' ), null );
	/* 🔴 Không dọn là nhập tiếp hạng mục sau mang theo dấu "setup" của dòng trước — người ta
	   không hề tích, và cũng không nhìn thấy, vì mắt đang ở ô Nội dung.
	   ⚠️ DỌN VỀ **VẬN HÀNH**, không về rỗng: đó là mặc định anh Thắng chốt 22/09/2026. */
	$reset = strstr( $app, 'function resetLineForm()' );
	$reset = false === $reset ? '' : substr( $reset, 0, 1200 );
	t( "🔴 $ban: mở form mới thì dọn về VẬN HÀNH, không giữ lốt dòng trước",
		false !== strpos( $reset, 'GIAI_DOAN=_gdVh(); veGiaiDoan();' ), $reset );
	t( "🔴 $ban: và mặc định lúc dựng biến cũng là VẬN HÀNH, không phải rỗng",
		false !== strpos( $app, 'var GIAI_DOAN=_gdVh();' ) && false === strpos( $app, "var GIAI_DOAN='';" ), null );
	/* Tên hai giá trị lấy từ máy chủ, không gõ lại ở màn — gõ lại là hai bên lệch một dấu và
	   `giai_doan_chuan()` lẳng lặng ngã mọi dòng về rỗng. */
	t( "$ban: màn lấy TÊN hai giá trị TỪ máy chủ",
		false !== strpos( $app, 'BOOT.giaiDoanSetup' ) && false !== strpos( $app, 'BOOT.giaiDoanVh' ), null );

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
