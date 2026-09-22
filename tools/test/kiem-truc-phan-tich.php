<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * KHUNG TRỤC PHÂN TÍCH — `VHCP_Truc`
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 *
 * Anh Thắng 22/09/2026, sau lượt khảo mã nguồn mở: *"Em làm thử anh xem"*.
 *
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * 🔴 KHUNG NÀY SINH RA ĐỂ DIỆT MỘT KIỂU HỎNG ĐÃ CẮN BA LẦN TRONG TUẦN
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * Thêm một trục ("Setup / Vận hành") phải đụng sáu nơi: cột trong sơ đồ bảng · hàm chuẩn hoá ·
 * hàm đọc · chỗ ghi dòng · chỗ đọc dòng lên màn · gói khởi động — cộng ô nhập và lượt dọn form
 * bên giao diện. Bỏ sót chỗ nào cũng hỏng IM LẶNG.
 *
 * Nay năm chỗ đi qua khung. Chỗ DUY NHẤT còn phải sửa tay là thêm cột + nâng `SCHEMA_VERSION`
 * — và bài này canh đúng chuyện ấy: mọi `cot` khai trong khung PHẢI có thật trong sơ đồ bảng.
 * Quên cột là MySQL chối `INSERT` và dòng chi biến mất, đúng vụ 22/09/2026.
 *
 * ⚠️ CHẠY THẬT `VHCP_Truc`, không chép lại luật.
 *
 * Chạy: php tools/test/kiem-truc-phan-tich.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}
/* ⚠️ VIẾT `{$ma}` CÓ NGOẶC NHỌN TRONG CHUỖI NHÁY KÉP, đừng viết `$ma` trần khi ngay sau nó là
   một chữ có dấu. PHP đọc tên biến theo BYTE, và mọi byte >= 0x80 (tức mọi chữ tiếng Việt, cả
   dấu «») đều được tính là một phần của tên — nên `"trục $ma»"` hoá thành biến `$ma»`, không
   tồn tại, và cả bài rơi vào 15 dòng cảnh báo. Cắn ngay lượt viết này. */

/* ═══ 1. KHUNG CHẠY THẬT ═══════════════════════════════════════════════════════════════════ */
t( 'lớp `VHCP_Truc` có mặt', class_exists( 'VHCP_Truc' ) );
if ( ! class_exists( 'VHCP_Truc' ) ) { echo "✗ thiếu lớp, dừng\n"; exit( 1 ); }

$DS = VHCP_Truc::ds();
t( '🔴 khung chở ít nhất một trục', count( $DS ) >= 1, array_keys( $DS ) );
t( '   và có trục Giai đoạn', isset( $DS['giaiDoan'] ), array_keys( $DS ) );

/* Cột nghiệp vụ của bảng `chiphi` — một trục khai trùng tên cột nào trong đây là nó ĐÈ LÊN dữ
   liệu người ta vừa nhập, vì vòng ghi trục chạy sau mảng nghiệp vụ. */
$COT_NGHIEP_VU = array( 'id', 'ma_don', 'coso', 'ngay', 'phan_loai_tt', 'doi_tuong', 'nhom',
	'noi_dung', 'dvt', 'so_luong', 'don_gia', 'thanh_tien', 'ghi_chu', 'anh', 'tao_luc',
	'thue_suat', 'tien_thue', 'thuc_mua', 'cn_xu_ly', 'phat_sinh', 'tk_no', 'tk_co', 'stt' );
$KHOA_NGHIEP_VU = array( 'id', 'coso', 'ngay', 'phanLoaiTT', 'doiTuong', 'nhom', 'noiDung',
	'dvt', 'soLuong', 'donGia', 'thanhTien', 'ghiChu', 'anh', 'thueSuat', 'tienThue',
	'thucMua', 'cnXuLy', 'phatSinh', 'tkNo', 'tkCo', 'taoLuc' );

$SODO = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-db.php' );

foreach ( $DS as $ma => $tr ) {
	/* ═══ 2. KHAI ĐỦ, VÀ KHAI ĐÚNG ═══════════════════════════════════════════════════════ */
	foreach ( array( 'cot', 'khoa', 'nhan', 'kieu', 'gtri', 'macDinh' ) as $k ) {
		t( "trục «{$ma}»: có khoá `$k`", isset( $tr[ $k ] ) && '' !== $tr[ $k ], isset( $tr[ $k ] ) ? $tr[ $k ] : '(thiếu)' );
	}
	t( "trục «{$ma}»: kiểu là 'tich' hoặc 'chon'", in_array( $tr['kieu'], array( 'tich', 'chon' ), true ), $tr['kieu'] );
	t( "trục «{$ma}»: có ít nhất hai giá trị", count( (array) $tr['gtri'] ) >= 2, $tr['gtri'] );

	/* 🔴 MẶC ĐỊNH PHẢI LÀ MỘT GIÁ TRỊ THẬT. Khai mặc định ngoài danh sách là `doc()` trả về một
	   chuỗi mà `chuan()` sẽ ngã về rỗng ở lượt ghi kế tiếp — đọc một đằng, ghi một nẻo. */
	t( "🔴 trục «{$ma}»: `macDinh` nằm TRONG danh sách giá trị",
		in_array( $tr['macDinh'], (array) $tr['gtri'], true ), array( $tr['macDinh'], $tr['gtri'] ) );

	if ( 'tich' === $tr['kieu'] ) {
		t( "trục «{$ma}»: kiểu ô tích thì phải khai `bat`", ! empty( $tr['bat'] ), null );
		t( "🔴 trục «{$ma}»: `bat` nằm TRONG danh sách giá trị",
			in_array( $tr['bat'], (array) $tr['gtri'], true ), array( $tr['bat'], $tr['gtri'] ) );
		/* 🔴 Tích và không tích phải ra HAI kết quả khác nhau. Bằng nhau thì ô tích bấm vào
		   không đổi gì — một ô bấm mãi không ăn, mà chẳng có câu lỗi nào. */
		t( "🔴 trục «{$ma}»: `bat` KHÁC `macDinh` — không thì ô tích bấm vào không đổi gì",
			$tr['bat'] !== $tr['macDinh'], array( $tr['bat'], $tr['macDinh'] ) );
		teq( "   đúng hai giá trị cho một ô tích", 2, count( (array) $tr['gtri'] ) );
	}

	/* ═══ 3. 🔴 CỘT PHẢI CÓ THẬT TRONG SỔ ════════════════════════════════════════════════
	 * Đây là phép đắt nhất của bài. Khai một trục mà quên thêm cột + nâng `SCHEMA_VERSION` thì
	 * `install()` không chạy, cột không có, MySQL chối `INSERT` — và dòng chi biến mất sau khi
	 * màn đã báo "đã thêm". Đúng vụ 22/09/2026, và nó im lặng hoàn toàn. */
	t( "🔴 trục «{$ma}»: cột `{$tr['cot']}` CÓ THẬT trong sơ đồ bảng `chiphi`",
		1 === preg_match( '/\b' . preg_quote( $tr['cot'], '/' ) . '\s+(VARCHAR|CHAR|TINYINT|INT|DECIMAL|DATE|DATETIME|TEXT)/i', $SODO ), $tr['cot'] );
	/* 🔴 Và KHÔNG được trùng cột nghiệp vụ: vòng ghi trục chạy SAU mảng nghiệp vụ nên nó đè
	   thẳng lên dữ liệu người ta vừa nhập — nội dung hạng mục hoá thành "Vận hành". */
	t( "🔴 trục «{$ma}»: cột `{$tr['cot']}` KHÔNG trùng cột nghiệp vụ nào",
		! in_array( $tr['cot'], $COT_NGHIEP_VU, true ), $tr['cot'] );
	t( "🔴 trục «{$ma}»: khoá `{$tr['khoa']}` KHÔNG trùng khoá nghiệp vụ nào",
		! in_array( $tr['khoa'], $KHOA_NGHIEP_VU, true ), $tr['khoa'] );

	/* ═══ 4. GHI vs ĐỌC — HAI VIỆC KHÁC NHAU ═════════════════════════════════════════════ */
	$g0 = (array) $tr['gtri'];
	teq( "trục «{$ma}»: GHI giữ nguyên giá trị hợp lệ", $g0[0], VHCP_Truc::chuan( $ma, $g0[0] ) );
	teq( "   khoảng trắng thừa vẫn nhận", $g0[0], VHCP_Truc::chuan( $ma, '  ' . $g0[0] . ' ' ) );
	teq( "   khác hoa/thường vẫn nhận", $g0[0], VHCP_Truc::chuan( $ma, mb_strtoupper( $g0[0] ) ) );
	/* 🔴 GHI KHÔNG ĐƯỢC BỊA. Một lượt gọi chỉ sửa ô khác mà không khai trục này thì nó gửi
	   rỗng; lấp mặc định vào đó là tự đóng dấu lên dữ liệu người ta không hề động tới. */
	teq( "🔴 trục «{$ma}»: GHI — rỗng vẫn ghi rỗng, không tự bịa mặc định", '', VHCP_Truc::chuan( $ma, '' ) );
	teq( "🔴 trục «{$ma}»: GHI — giá trị lạ về rỗng, không đẻ nhóm thứ ba", '', VHCP_Truc::chuan( $ma, 'một chuỗi không có thật' ) );
	/* 🔴 ĐỌC — rỗng nghĩa là mặc định. Dòng cũ đều rỗng, và chúng là dữ liệu thật. */
	teq( "🔴 trục «{$ma}»: ĐỌC — rỗng = mặc định", $tr['macDinh'], VHCP_Truc::doc( $ma, '' ) );
	teq( "   ĐỌC — giá trị lạ cũng về mặc định", $tr['macDinh'], VHCP_Truc::doc( $ma, 'xyz' ) );
	teq( "   ĐỌC — giá trị thật thì giữ nguyên", $g0[0], VHCP_Truc::doc( $ma, $g0[0] ) );
	/* 🔴 PHÉP ĐỐI CHỨNG: hai hàm phải KHÁC nhau ở đúng ô rỗng. Gộp làm một là hoặc đường ghi
	   tự bịa dữ liệu, hoặc đường đọc trả lại một "chưa xác định" mà nghiệp vụ không có. */
	t( "🔴 trục «{$ma}»: `chuan()` và `doc()` KHÁC nhau ở ô rỗng",
		VHCP_Truc::chuan( $ma, '' ) !== VHCP_Truc::doc( $ma, '' ), null );
}

/* ═══ 5. TRỤC LẠ KHÔNG LÀM CHẾT GÌ ════════════════════════════════════════════════════════ */
teq( 'hỏi một trục không có thì trả rỗng, không ném lỗi', '', VHCP_Truc::chuan( 'khong-co', 'Setup' ) );
teq( '   `doc()` cũng vậy', '', VHCP_Truc::doc( 'khong-co', 'Setup' ) );
t( '   `mot()` trả null', null === VHCP_Truc::mot( 'khong-co' ) );

/* ═══ 6. GHI/ĐỌC CẢ DÒNG ══════════════════════════════════════════════════════════════════ */
{
	$gui = array( 'giaiDoan' => 'Setup', 'nhom' => 'Chi phí cơ sở' );
	$get = function ( $k ) use ( $gui ) { return isset( $gui[ $k ] ) ? $gui[ $k ] : null; };
	$cot = VHCP_Truc::ghi_dong( $get );
	teq( '🔴 `ghi_dong()` trả về CỘT, không phải khoá giao diện', array( 'giai_doan' => 'Setup' ), $cot );
	/* Dòng cũ trong sổ: cột rỗng -> đọc ra mặc định. */
	$doc = VHCP_Truc::doc_dong( array( 'giai_doan' => '' ) );
	teq( '🔴 `doc_dong()` trả về KHOÁ giao diện, và rỗng = mặc định',
		array( 'giaiDoan' => VHCP_Don::GIAI_DOAN_VH ), $doc );
	/* ⚠️ Cột chưa có trong sổ (bản cũ chưa nâng sơ đồ bảng) KHÔNG được ném lỗi — một trục mới
	   không được làm chết cả đường đọc đơn. */
	teq( '⚠️ cột chưa có trong sổ thì coi như rỗng, không ném lỗi',
		array( 'giaiDoan' => VHCP_Don::GIAI_DOAN_VH ), VHCP_Truc::doc_dong( array() ) );
}

/* ═══ 7. GÓI KHỞI ĐỘNG ════════════════════════════════════════════════════════════════════ */
{
	$b = VHCP_Truc::boot();
	t( '🔴 `boot()` trả MẢNG CÓ THỨ TỰ để màn dựng ô theo đúng thứ tự khai',
		isset( $b[0] ) && array_keys( $b ) === range( 0, count( $b ) - 1 ), $b );
	foreach ( $b as $x ) {
		foreach ( array( 'ma', 'khoa', 'nhan', 'kieu', 'gtri', 'macDinh' ) as $k ) {
			t( "gói khởi động: trục «{$x['ma']}» chở `$k`", isset( $x[ $k ] ), $x );
		}
		t( "   `gtri` là mảng có thứ tự (không thì màn xổ ra lộn xộn)",
			array_keys( $x['gtri'] ) === range( 0, count( $x['gtri'] ) - 1 ), $x['gtri'] );
	}
}

/* ═══ 8. CẢ BỐN BẢN: NĂM CHỖ ĐỀU ĐI QUA KHUNG ═════════════════════════════════════════════
   Bản vùng sinh lại từ bản gốc, nên phải soi đủ bốn. */
$BAN = array( 'vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp' );
foreach ( $BAN as $ban ) {
	$d = $goc . '/wordpress/' . $ban . '/';
	if ( ! is_dir( $d ) ) { t( "có $ban", false ); continue; }
	$pre = ( 'vhcp-chi-phi' === $ban ) ? 'VHCP' : 'VHCP' . strtoupper( substr( $ban, strlen( 'vhcp-chi-phi-' ) ) );
	$chinh = file_get_contents( $d . $ban . '.php' );
	$don   = file_get_contents( $d . 'includes/class-vhcp-don.php' );
	$app   = file_get_contents( $d . 'templates/app.html' );

	t( "🔴 $ban: plugin nạp khung", false !== strpos( $chinh, "includes/class-vhcp-truc.php" ) );
	t( "🔴 $ban: chỗ GHI dòng đi qua khung (vòng, không liệt kê từng trục)",
		1 === preg_match( '/foreach \( ' . $pre . '_Truc::ghi_dong\( \$get \) as \$cot => \$gt \)/', $don ), null );
	t( "🔴 $ban: chỗ ĐỌC dòng đi qua khung",
		false !== strpos( $don, "{$pre}_Truc::doc_dong( \$x )" ), null );
	t( "🔴 $ban: và KHÔNG còn dòng gõ cứng nào cho trục ở hai chỗ ấy",
		false === strpos( $don, "'giai_doan'    => " ) && false === strpos( $don, "'giaiDoan'   => " ), null );
	t( "🔴 $ban: gói khởi động chở bảng trục", false !== strpos( $don, "'trucDs'     => {$pre}_Truc::boot()" ), null );
	/* Hai hàm cũ ở lại làm lối gọi quen, nhưng KHÔNG giữ bản luật thứ hai. */
	t( "🔴 $ban: `giai_doan_chuan()` chỉ gọi lại khung, không giữ luật riêng",
		false !== strpos( $don, "return {$pre}_Truc::chuan( 'giaiDoan', \$v );" ), null );
	t( "   `giai_doan_doc()` cũng vậy",
		false !== strpos( $don, "return {$pre}_Truc::doc( 'giaiDoan', \$v );" ), null );

	/* ── BÊN MÀN ─────────────────────────────────────────────────────────────────────────── */
	t( "🔴 $ban: form có MỘT chỗ chứa mọi trục", false !== strpos( $app, 'id="f_trucBox"' ), null );
	t( "🔴 $ban: và ô của từng trục do `veTrucs()` dựng từ `BOOT.trucDs`",
		false !== strpos( $app, 'BOOT.trucDs' ) && false !== strpos( $app, 'function veTrucs()' ), null );
	t( "🔴 $ban: dòng gửi đi gộp MỌI trục", false !== strpos( $app, 'var g=trucGui();' ), null );
	t( "🔴 $ban: dọn form đặt mọi trục về mặc định", false !== strpos( $app, 'trucVeMacDinh(); veTrucs();' ), null );
	t( "🔴 $ban: mở dòng cũ đổ lại mọi trục", false !== strpos( $app, 'trucTuDong(l); veTrucs();' ), null );
	/* ⚠️ Ô cũ gõ cứng của riêng trục Giai đoạn đã gỡ — còn lại là hai bản luật. */
	t( "⚠️ $ban: không còn ô gõ cứng `f_giaiDoan` / hàm `veGiaiDoan()`",
		false === strpos( $app, 'id="f_giaiDoan"' ) && false === strpos( $app, 'function veGiaiDoan(' ), null );
	/* ⚠️ Thẻ bọc phải `display:contents` — không thì mọi trục chen vào MỘT ô lưới. */
	t( "⚠️ $ban: thẻ bọc không tự chiếm một ô lưới",
		1 === preg_match( '/id="f_trucBox" style="display:contents"/', $app ), null );
}

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: khung trục phân tích chạy thật, và năm chỗ đều đi qua nó.\n";
