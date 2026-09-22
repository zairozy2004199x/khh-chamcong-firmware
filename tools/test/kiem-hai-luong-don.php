<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * HAI LUỒNG — TRỰC TIẾP và QUA TẠM ỨNG — CHỌN TRÊN TỪNG ĐƠN
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 *
 * Anh Thắng 22/09/2026: *"Bên anh đó có 2 luồng. 1 luồng trực tiếp, 1 luồng gián tiếp. Trực
 * tiếp là gửi đơn đầy đủ cho kế toán và quyết toán. Còn gián tiếp là tạm ứng, duyệt tạm ứng…
 * cái đang chạy"*, rồi chốt: **người lập chọn trên từng đơn**, và luồng trực tiếp đi bốn bước:
 * Tạo đơn → Chờ quyết toán → Đã quyết toán → Đã thanh toán → Xuất MISA.
 *
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * 🔴 CÁI HỎNG NGUY NHẤT: ĐƠN RƠI VÀO MỘT TRẠNG THÁI KHÔNG CÓ TRONG LUỒNG CỦA NÓ
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * Luồng trực tiếp không có ba bước tạm ứng. Nếu một cổng chuyển nào đó vẫn đẩy đơn trực tiếp
 * sang `Chờ duyệt tạm ứng` thì: thanh bước không biết vẽ nó ở đâu, màn duyệt tạm ứng bày ra một
 * đơn mà tiền đã chi rồi, và đơn ấy không có đường nào đi tiếp. Không câu lỗi nào — đơn chỉ
 * đứng lại đó.
 *
 * 🔴 VÀ CÁI HỎNG ÂM THẦM NHẤT: ĐỔI LUỒNG CHO ĐƠN ĐANG CHẠY DỞ. Hàng trăm đơn đang chạy có cột
 *    `luong` RỖNG. Rỗng phải nghĩa là "theo khối như cũ" — hiểu thành một mã nào đó là đơn Máy
 *    tự động đang ở "Đã duyệt chi" bị kéo sang luồng khác giữa chừng.
 *
 * ⚠️ CHẠY THẬT `VHCP_Don`, không bóc thân hàm ra eval.
 *
 * Chạy: php tools/test/kiem-hai-luong-don.php
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

/* ═══ 1. BỐN BƯỚC CỦA LUỒNG TRỰC TIẾP, ĐÚNG NHƯ ANH THẮNG CHỐT ═══════════════════════════ */
teq( '🔴 luồng TRỰC TIẾP đi đúng các bước anh Thắng chốt',
	array( 'Nháp', 'Chờ quyết toán', 'Đã quyết toán', 'Đã thanh toán', 'Đã xuất MISA' ),
	array_keys( VHCP_Don::LUONG_TT ) );
/* 🔴 KHÔNG có bước tạm ứng nào — đó là cả điểm của luồng này. */
foreach ( array( 'Chờ duyệt tạm ứng', 'Chờ cấp tạm ứng', 'Đã cấp tạm ứng' ) as $b ) {
	t( "🔴 luồng trực tiếp KHÔNG có bước «{$b}»", ! isset( VHCP_Don::LUONG_TT[ $b ] ), null );
}
/* ⚠️ VÀ KHÔNG ĐẶT CHUỖI TRẠNG THÁI MỚI. Tên trạng thái đang được đọc ở hàng trăm chỗ; một bộ
   chuỗi song song là mỗi chốt phải học thêm mấy tên, và chỗ nào quên thì đơn lọt lưới im lặng. */
foreach ( array_keys( VHCP_Don::LUONG_TT ) as $b ) {
	t( "⚠️ bước «{$b}» là chuỗi ĐÃ CÓ trong `TT_LUONG`, không phải chuỗi mới",
		in_array( $b, VHCP_Don::TT_LUONG, true ), $b );
}
/* 🔴 `Đã thanh toán` phải nằm trong `TT_CHOT` — đứng sau quyết toán thì đương nhiên đã chốt sổ;
   quên là đơn đã trả tiền vẫn sửa được số. */
t( '🔴 `Đã thanh toán` nằm trong TT_CHOT', in_array( 'Đã thanh toán', VHCP_Don::TT_CHOT, true ), null );

/* ═══ 2. 🔴 RỖNG = THEO KHỐI NHƯ CŨ (đơn đang chạy dở không bị đổi luồng) ════════════════ */
teq( '🔴 đơn cũ (luồng rỗng) ở khối kvc → vẫn luồng tạm ứng như hôm qua',
	VHCP_Don::LUONG_KVC, VHCP_Don::luong_cua( 'kvc', '' ) );
teq( '🔴 đơn cũ ở khối mtd → vẫn luồng chi như hôm qua',
	VHCP_Don::LUONG_CHI, VHCP_Don::luong_cua( 'mtd', '' ) );
teq( '   khối vp cũng vậy', VHCP_Don::LUONG_CHI, VHCP_Don::luong_cua( 'vp', '' ) );
teq( '   khối vùng (hn) → luồng tạm ứng, đúng "cái đang chạy"',
	VHCP_Don::LUONG_KVC, VHCP_Don::luong_cua( 'hn', '' ) );
/* ⚠️ MÃ LẠ CŨNG NGÃ VỀ THEO KHỐI — thà chạy đúng như hôm qua còn hơn rơi vào một luồng thứ ba
   do gõ sai mà ra. */
teq( '⚠️ mã luồng lạ → ngã về theo khối, không đẻ luồng thứ ba',
	VHCP_Don::LUONG_KVC, VHCP_Don::luong_cua( 'kvc', 'xyz' ) );

/* ═══ 3. ĐƠN NÓI TRƯỚC, KHỐI NÓI SAU ════════════════════════════════════════════════════ */
teq( '🔴 đơn chọn TRỰC TIẾP → đi luồng trực tiếp, dù khối là khối tạm ứng',
	VHCP_Don::LUONG_TT, VHCP_Don::luong_cua( 'kvc', 'tt' ) );
teq( '🔴 đơn chọn QUA TẠM ỨNG → đi luồng tạm ứng, dù khối là khối chi',
	VHCP_Don::LUONG_KVC, VHCP_Don::luong_cua( 'mtd', 'gt' ) );
teq( '   không phân biệt hoa thường', VHCP_Don::LUONG_TT, VHCP_Don::luong_cua( 'kvc', 'TT' ) );
teq( '   khoảng trắng thừa vẫn nhận', VHCP_Don::LUONG_TT, VHCP_Don::luong_cua( 'kvc', ' tt ' ) );

/* Chuẩn hoá mã luồng của một hàng đơn. */
teq( '🔴 `luong_don()` mã lạ về rỗng', '', VHCP_Don::luong_don( array( 'luong' => 'abc' ) ) );
teq( '   cột chưa có (bản cũ) cũng về rỗng', '', VHCP_Don::luong_don( array() ) );
teq( '   mã thật thì giữ', 'tt', VHCP_Don::luong_don( array( 'luong' => 'tt' ) ) );
t( '🔴 `la_truc_tiep()` đúng một chiều',
	VHCP_Don::la_truc_tiep( array( 'luong' => 'tt' ) )
	&& ! VHCP_Don::la_truc_tiep( array( 'luong' => 'gt' ) )
	&& ! VHCP_Don::la_truc_tiep( array() ), null );

/* ═══ 4. 🔴 BƯỚC "SẴN SÀNG XUẤT MISA" THEO LUỒNG CỦA ĐƠN ════════════════════════════════
 * Gõ cứng 'Đã quyết toán' là đơn trực tiếp vừa duyệt quyết toán đã rơi vào bản xuất — tức xuất
 * MISA cho một khoản chưa trả tiền. */
teq( '🔴 luồng trực tiếp: sẵn sàng xuất MISA là SAU khi đã thanh toán',
	'Đã thanh toán', VHCP_Don::tt_truoc_misa( 'kvc', 'tt' ) );
teq( '   luồng tạm ứng: là sau khi đã quyết toán',
	'Đã quyết toán', VHCP_Don::tt_truoc_misa( 'kvc', 'gt' ) );
teq( '   đơn cũ ở khối kvc giữ nguyên như hôm qua',
	'Đã quyết toán', VHCP_Don::tt_truoc_misa( 'kvc', '' ) );

/* ═══ 5. CHỮ HIỆN TRÊN MÀN THEO LUỒNG CỦA ĐƠN ══════════════════════════════════════════ */
teq( '🔴 đơn trực tiếp ở "Nháp" đọc là "Tạo đơn"', 'Tạo đơn', VHCP_Don::ten_tt( 'Nháp', 'kvc', 'tt' ) );
teq( '   đơn tạm ứng vẫn đọc là "Nháp"', 'Nháp', VHCP_Don::ten_tt( 'Nháp', 'kvc', 'gt' ) );
/* ⚠️ NGÃ VỀ NGUYÊN VĂN, không trả rỗng: một đơn trực tiếp lỡ đứng ở bước tạm ứng (dữ liệu cũ,
   gõ tay) vẫn phải đọc ra được, không thì màn hiện ô trắng và không ai biết đơn ở đâu. */
teq( '⚠️ bước không có trong luồng → trả NGUYÊN VĂN, không trả rỗng',
	'Chờ cấp tạm ứng', VHCP_Don::ten_tt( 'Chờ cấp tạm ứng', 'kvc', 'tt' ) );

t( '🔴 `tt_trong_luong()` biết bước nào có, bước nào không',
	VHCP_Don::tt_trong_luong( 'Chờ quyết toán', 'kvc', 'tt' )
	&& ! VHCP_Don::tt_trong_luong( 'Chờ cấp tạm ứng', 'kvc', 'tt' )
	&& VHCP_Don::tt_trong_luong( 'Chờ cấp tạm ứng', 'kvc', 'gt' ), null );

/* ═══ 6. 🔴 CỔNG CHUYỂN — CHẠY THẬT TRÊN SỔ ════════════════════════════════════════════ */
$NGUOI = 'Trần Ngọc Minh';
function _don_moi( $luong ) {
	global $NGUOI;
	$r = VHCP_Don::create_don( 'T9/2026', $NGUOI, $luong );
	return isset( $r['maDon'] ) ? $r['maDon'] : '';
}
function _tt( $ma ) {
	$d = VHCP_Don::don_row( $ma );
	return $d ? (string) $d['trang_thai'] : '(không có)';
}

/* Đơn TRỰC TIẾP: không đi đường tạm ứng, gửi thẳng quyết toán từ Nháp. */
{
	$ma = _don_moi( 'tt' );
	t( 'lập được đơn trực tiếp', '' !== $ma, $ma );
	$d = VHCP_Don::don_row( $ma );
	teq( '🔴 cột `luong` ghi xuống sổ đúng mã', 'tt', VHCP_Don::luong_don( $d ) );

	VHCP_Don::add_line( $ma, array( 'coso' => 'AEON', 'nhom' => 'Chi phí cơ sở',
		'noiDung' => 'Nước bình', 'soLuong' => 1, 'donGia' => 50000 ) );

	/* 🔴 CỬA TẠM ỨNG PHẢI CHỐI, VÀ NÓI RA PHẢI BẤM NÚT NÀO. Cho qua là đơn rơi vào một trạng
	   thái không có trong luồng của nó và đứng lại đó, không câu lỗi nào. */
	$r = VHCP_Don::gui_duyet_tam_ung( $ma );
	t( '🔴 đơn trực tiếp: gửi xin tạm ứng bị CHỐI', empty( $r['success'] ), $r );
	t( '   và câu chối nói ra phải bấm nút nào',
		isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'Gửi quyết toán' ), $r );
	teq( '🔴 và đơn KHÔNG bị đẩy sang trạng thái nào cả', 'Nháp', _tt( $ma ) );

	/* Gửi quyết toán thẳng từ Nháp. */
	$r = VHCP_Don::gui_quyet_toan( $ma );
	t( '🔴 đơn trực tiếp: gửi quyết toán THẲNG từ Nháp — được', ! empty( $r['success'] ), $r );
	teq( '   và sang đúng "Chờ quyết toán"', 'Chờ quyết toán', _tt( $ma ) );
}

/* Đơn QUA TẠM ỨNG: giữ nguyên đường cũ. */
{
	$ma = _don_moi( 'gt' );
	VHCP_Don::add_line( $ma, array( 'coso' => 'AEON', 'nhom' => 'Chi phí cơ sở',
		'noiDung' => 'Nước bình', 'soLuong' => 1, 'donGia' => 50000 ) );
	/* 🔴 ĐƠN TẠM ỨNG KHÔNG ĐƯỢC NHẢY CÓC. Gửi quyết toán từ Nháp là bỏ qua cả chặng tạm ứng —
	   tiền chưa ra mà đã đòi quyết toán. */
	$r = VHCP_Don::gui_quyet_toan( $ma );
	t( '🔴 đơn qua tạm ứng: gửi quyết toán từ Nháp bị CHỐI — không nhảy cóc', empty( $r['success'] ), $r );
	teq( '   đơn vẫn ở Nháp', 'Nháp', _tt( $ma ) );

	$r = VHCP_Don::gui_duyet_tam_ung( $ma );
	t( '🔴 đơn qua tạm ứng: gửi xin tạm ứng được như cũ', ! empty( $r['success'] ), $r );
	teq( '   và sang "Chờ duyệt tạm ứng"', 'Chờ duyệt tạm ứng', _tt( $ma ) );
	VHCP_Don::duyet_tam_ung( $ma, 'Kế toán', 500000 );
	teq( '   duyệt xong sang "Chờ cấp tạm ứng" — đường cũ nguyên vẹn', 'Chờ cấp tạm ứng', _tt( $ma ) );
}

/* 🔴 LUỒNG CỦA ĐƠN PHẢI ĐÈ KHỐI Ở CẢ CỬA DUYỆT TẠM ỨNG.
   Phá thử 22/09/2026: `duyet_tam_ung()` hỏi mỗi khối thì mọi phép trên vẫn xanh — vì đơn của
   bản gốc đều thuộc khối `kvc`, mà `kvc` và luồng `gt` cho cùng một kết quả. Ca phân biệt được
   là đơn của khối ĐI LUỒNG CHI (mtd) nhưng người lập chọn QUA TẠM ỨNG: hỏi mỗi khối thì nó
   nhảy thẳng sang "Đã cấp tạm ứng" — bỏ qua khâu kế toán chuyển tiền, tức đơn trông như đã
   nhận tiền trong khi chưa ai chuyển đồng nào. */
{
	global $wpdb;
	$ma = _don_moi( 'gt' );
	$wpdb->update( VHCP_DB::t( 'don' ), array( 'khoi' => 'mtd' ), array( 'ma_don' => $ma ) );
	VHCP_Don::add_line( $ma, array( 'coso' => 'AEON', 'nhom' => 'Chi phí cơ sở',
		'noiDung' => 'Nước bình', 'soLuong' => 1, 'donGia' => 50000 ) );
	VHCP_Don::gui_duyet_tam_ung( $ma );
	teq( '   (dựng cảnh) đơn khối mtd chọn QUA TẠM ỨNG đang chờ duyệt', 'Chờ duyệt tạm ứng', _tt( $ma ) );
	VHCP_Don::duyet_tam_ung( $ma, 'Kế toán', 500000 );
	teq( '🔴 khối đi luồng chi + đơn chọn QUA TẠM ỨNG → vẫn qua khâu "Chờ cấp tạm ứng"',
		'Chờ cấp tạm ứng', _tt( $ma ) );
}

/* 🔴 ĐƠN CŨ (luồng rỗng) CHẠY ĐÚNG NHƯ HÔM QUA — đây là phép đối chứng quan trọng nhất của
   lượt đổi này: hàng trăm đơn đang chạy không được đổi đường giữa chừng. */
{
	$ma = _don_moi( '' );
	VHCP_Don::add_line( $ma, array( 'coso' => 'AEON', 'nhom' => 'Chi phí cơ sở',
		'noiDung' => 'Nước bình', 'soLuong' => 1, 'donGia' => 50000 ) );
	$r = VHCP_Don::gui_duyet_tam_ung( $ma );
	t( '🔴 đơn CŨ (chưa chọn luồng): gửi xin tạm ứng vẫn được như hôm qua', ! empty( $r['success'] ), $r );
	teq( '   và sang đúng "Chờ duyệt tạm ứng"', 'Chờ duyệt tạm ứng', _tt( $ma ) );
}

/* 🔴 ĐÁNH DẤU THANH TOÁN CŨNG PHẢI HỎI LUỒNG CỦA ĐƠN.
   Phá thử 22/09/2026: `danh_dau_thanh_toan()` hỏi mỗi khối thì đơn TRỰC TIẾP của khối `kvc` bị
   chối — mà luồng trực tiếp CÓ bước ấy. Hậu quả: đơn đã duyệt quyết toán không có đường nào đi
   tiếp, đứng lại vĩnh viễn trước khâu xuất MISA. */
{
	global $wpdb;
	$ma = _don_moi( 'tt' );
	$wpdb->update( VHCP_DB::t( 'don' ), array( 'trang_thai' => 'Đã quyết toán' ), array( 'ma_don' => $ma ) );
	$r = VHCP_Don::danh_dau_thanh_toan( $ma, 'Kế toán' );
	t( '🔴 đơn TRỰC TIẾP (khối kvc) đánh dấu thanh toán được — luồng của nó có bước ấy',
		! empty( $r['success'] ), $r );
	teq( '   và sang đúng "Đã thanh toán"', 'Đã thanh toán', _tt( $ma ) );
}
/* ⚠️ PHÉP ĐỐI CHỨNG: đơn QUA TẠM ỨNG thì KHÔNG có bước ấy, phải chối — không thì hàm này nhận
   tất và phép trên xanh vì lý do sai. */
{
	global $wpdb;
	$ma = _don_moi( 'gt' );
	$wpdb->update( VHCP_DB::t( 'don' ), array( 'trang_thai' => 'Đã quyết toán' ), array( 'ma_don' => $ma ) );
	$r = VHCP_Don::danh_dau_thanh_toan( $ma, 'Kế toán' );
	t( '⚠️ đơn QUA TẠM ỨNG bị chối — luồng của nó không có bước "Đã thanh toán"', empty( $r['success'] ), $r );
	teq( '   và đơn không bị đẩy đi đâu', 'Đã quyết toán', _tt( $ma ) );
}

/* ═══ 7. DANH SÁCH ĐƠN CHỞ LUỒNG XUỐNG MÀN ═════════════════════════════════════════════
   Không chở là màn không biết vẽ thanh bước nào, và nút gửi bày sai. */
{
	$DON = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
	t( '🔴 danh sách đơn chở `luong` xuống màn',
		false !== strpos( $DON, "'luong'       => self::luong_don( \$r )," ), null );
	t( '🔴 `create_don()` ghi cột `luong` xuống sổ',
		false !== strpos( $DON, "'luong'      => \$lg," ), null );
	t( '🔴 và chuẩn hoá mã TRƯỚC khi ghi, không ghi thẳng cái màn gửi lên',
		false !== strpos( $DON, "\$lg = self::luong_don( array( 'luong' => \$luong ) );" ), null );

	/* 🔴 XUẤT MISA CŨNG PHẢI HỎI LUỒNG CỦA ĐƠN. Hỏi mỗi khối là đơn TRỰC TIẾP vừa duyệt quyết
	   toán đã rơi vào bản xuất — tức xuất MISA cho một khoản chưa trả tiền, vì luồng trực tiếp
	   còn một bước "Đã thanh toán" ở giữa. Phá thử 22/09/2026 tìm ra lỗ này. */
	$MISA = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-misa.php' );
	t( '🔴 lượt chọn đơn để xuất MISA hỏi CẢ khối LẪN luồng của đơn',
		1 === preg_match( '/tt_truoc_misa\(\s*isset\( \$r\[.khoi.\] \)[^;]*luong_don\( \$r \)/su', $MISA ),
		( preg_match( '/tt_truoc_misa\([^;]*/su', $MISA, $mm ) ? $mm[0] : 'không thấy' ) );
}

/* ═══ 8. 🔴 MỞ MỘT ĐƠN RA CŨNG PHẢI THẤY LUỒNG CỦA NÓ ═══════════════════════════════════
   `get_don()` là thứ nuôi CẢ màn đơn: thanh bước, nút gửi, câu nhắc. Thiếu hai ô này thì
   `_luongDon(CUR.don)` trả rỗng và MỌI đơn — kể cả đơn trực tiếp — lại rơi về luồng mặc định
   của khối đang đứng. Lỗi ấy im lặng tuyệt đối: thanh bước vẫn vẽ, nút vẫn hiện, chỉ là của
   một luồng khác, và người lập bấm "Gửi xin tạm ứng" cho một đơn đã tiêu tiền xong.
   ⚠️ CHẠY THẬT, không soi chữ: danh sách đơn có chở `luong` (mục 7) không nói gì về `get_don`. */
{
	foreach ( array( 'tt', 'gt', '' ) as $lg ) {
		$ma = _don_moi( $lg );
		$r  = VHCP_Don::get_don( $ma, false );
		t( "⚠️ mở được đơn «{$lg}»", ! empty( $r['success'] ) && isset( $r['don'] ), $r );
		$d = isset( $r['don'] ) ? $r['don'] : array();
		t( "🔴 đơn mở ra chở `luong` xuống màn (luồng «{$lg}»)", array_key_exists( 'luong', $d ), array_keys( $d ) );
		teq( "🔴 và chở đúng mã đã ghi («{$lg}»)", $lg, isset( $d['luong'] ) ? $d['luong'] : null );
		/* 🔴 KHỐI ĐI KÈM LUÔN. Thanh bước hỏi khối CỦA ĐƠN (Admin xem "tất cả" thì khối đang
		   đứng khác khối của đơn); thiếu nó là thanh vẽ luồng của màn chứ không của đơn. */
		t( "🔴 và chở cả `khoi` của đơn", array_key_exists( 'khoi', $d ), array_keys( $d ) );
	}
	/* ⚠️ HAI Ô NÀY PHẢI CÙNG TÊN VỚI BÊN DANH SÁCH. Đặt tên khác là màn phải nhớ hai bộ tên,
	   và chỗ nào quên thì đúng chỗ đó rơi về "theo khối như cũ" trong im lặng. */
	$ma  = _don_moi( 'tt' );
	$mot = VHCP_Don::get_don( $ma, false );
	$ds  = VHCP_Don::list_dons();
	$hang = null;
	foreach ( (array) ( isset( $ds['items'] ) ? $ds['items'] : $ds ) as $x ) {
		if ( is_array( $x ) && isset( $x['maDon'] ) && $x['maDon'] === $ma ) { $hang = $x; break; }
	}
	t( '⚠️ tìm được chính đơn ấy trong danh sách', is_array( $hang ), $ma );
	if ( is_array( $hang ) ) {
		teq( '🔴 mở đơn và danh sách nói CÙNG một luồng', $hang['luong'], $mot['don']['luong'] );
		teq( '🔴 và CÙNG một khối', $hang['khoi'], $mot['don']['khoi'] );
	}
}

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: hai luồng chọn trên từng đơn, và đơn đang chạy dở không bị đổi đường.\n";
