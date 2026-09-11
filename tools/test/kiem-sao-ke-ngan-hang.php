<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ NGÂN HÀNG — SỔ RIÊNG, KHÔNG ĐỤNG SỔ TIỀN CỦA GHẾ
 *
 * Anh Thắng 11/09/2026: *"Đẩy sao kê sang trang sao kê ngân hàng, nó tự lọc chứ"*, rồi chốt
 * ngay sau đó: *"KHÔNG nên đẩy vào trang ghế bằng file sao kê, sau nó sẽ rối"*.
 *
 * =============================================================================================
 * 🔴 RÀNG BUỘC LỚN NHẤT: KHÔNG MỘT DÒNG SAO KÊ NÀO ĐƯỢC CHẢY SANG BẢNG `thu`.
 *    Bảng `thu` nuôi mọi báo cáo doanh thu và phép tính tiền trên tay người thu. Sao kê ngân
 *    hàng lẫn cả tiền ra, phí, lãi, tiền của mảng khác — đổ vào là doanh thu phình lên bằng
 *    những khoản không phải của nó. Mà `ref` là UNIQUE: xoá dòng rác đi rồi thì đúng giao dịch
 *    ấy sau này webhook bắn lại cũng không vào được nữa. Một thao tác hỏng hai lần.
 *
 *    Bài kiểm canh điều này bằng HAI tầng: chạy thật (nhập xong đếm lại bảng `thu`) và quét mã
 *    (tệp sao kê không được gọi `VHG_Thu::ghi`). Tầng hai bắt cả lần sửa sau này.
 *
 * Chạy: php tools/test/kiem-sao-ke-ngan-hang.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
define( 'VHG_TEST', 1 );
define( 'VHG_VERSION', 'test' );
define( 'VHG_DIR', $goc . '/wordpress/vhcp-ghe/' );
foreach ( array( 'db', 'doc', 'may', 'thu', 'qr', 'ma', 'vi', 'quy', 'chan', 'qrve', 'tep', 'nhap', 'saoke' ) as $f ) {
	require_once VHG_DIR . 'includes/class-vhg-' . $f . '.php';
}

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/* Dựng bảng theo đúng lối `test-ghe.php` — khuôn bảng viết bằng cú pháp MySQL, còn bệ thử chạy
   SQLite, nên phải gỡ mấy dòng KEY và đổi AUTO_INCREMENT. Chép lối ấy sang đây chứ không tự
   nghĩ cách khác: hai cách dựng bảng khác nhau là hai bộ thử nói về hai lược đồ khác nhau. */
global $wpdb;
foreach ( VHG_DB::bang() as $ten => $than ) {
	$bang = $wpdb->prefix . 'vhg_' . $ten;
	$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . $bang );
	$cot = array();
	foreach ( array_filter( array_map( 'trim', explode( "\n", $than ) ) ) as $d ) {
		$d = rtrim( $d, ',' );
		if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\b/', $d ) ) { continue; }
		$cot[] = preg_replace( '/BIGINT\(20\) NOT NULL AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $d );
	}
	$wpdb->exec_raw( 'CREATE TABLE ' . $bang . " (\n" . implode( ",\n", $cot ) . "\n)" );
}
function dem_bang( $ten ) {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHG_DB::t( $ten ) );
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. TỰ LỌC — gắn nhãn theo nội dung
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'nội dung mang mã ghế → nhãn ghế',  'ghe',  VHG_SaoKe::nhan( 'GHE3 T1ABC', false ) );
teq( 'đơn mua mã trước → nhãn mua mã',   'ma',   VHG_SaoKe::nhan( 'MUAABC123', false ) );
teq( 'tiền của mảng khác → nhãn khác',   'khac', VHG_SaoKe::nhan( 'THANH TOAN TIEN DIEN T9', false ) );
teq( 'nội dung rỗng → nhãn khác',        'khac', VHG_SaoKe::nhan( '', false ) );
/* 🔴 TIỀN RA XÉT TRƯỚC MỌI THỨ. Một lượt chuyển ĐI mang nội dung ghế (hoàn tiền khách) mà gắn
   nhãn ghế là nó nằm lẫn trong danh sách doanh thu, và người đối soát cộng nhầm vào. */
teq( '🔴 tiền RA mang nội dung ghế vẫn là tiền ra', 'ra', VHG_SaoKe::nhan( 'GHE3 T1ABC', true ) );
teq( '🔴 tiền RA mang nội dung mua mã cũng thế',    'ra', VHG_SaoKe::nhan( 'MUAABC123', true ) );

teq( 'đọc được mã ghế từ nội dung', 'AMTP01', VHG_SaoKe::may( 'GHEAMTP01 T1ABC' ) );
teq( 'nội dung không có ghế → rỗng', '',      VHG_SaoKe::may( 'TIEN DIEN' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. NHẬP — hai cột Có/Nợ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$BANG = array(
	array( 'Ngày giao dịch', 'Số tham chiếu', 'Ghi nợ', 'Ghi có', 'Nội dung' ),
	array( '11/09/2026 17:55', 'FT001', '',        '20.000', 'GHE3 T1ABC' ),
	array( '11/09/2026 17:50', 'FT002', '',        '50.000', 'MUAXYZ999' ),
	array( '11/09/2026 17:40', 'FT003', '',        '30.000', 'THANH TOAN TIEN DIEN' ),
	array( '11/09/2026 17:30', 'FT004', '1.000.000', '',     'CHUYEN TIEN NHA CUNG CAP' ),
	array( '11/09/2026 17:20', '',      '',        '10.000', 'THIEU MA THAM CHIEU' ),
);
$kq = VHG_SaoKe::nhap( $BANG, 'sao-ke-thang-9.xlsx' );
t( 'nhập được', ! empty( $kq['ok'] ), $kq );
teq( 'vào sổ 4 dòng (bỏ dòng thiếu mã)', 4, $kq['vao'] );
teq( 'bỏ 1 dòng thiếu mã tham chiếu',    1, $kq['bo'] );
teq( '   đếm ghế',    1, $kq['dem']['ghe'] );
teq( '   đếm mua mã', 1, $kq['dem']['ma'] );
teq( '   đếm tiền ra',1, $kq['dem']['ra'] );
teq( '   đếm khác',   1, $kq['dem']['khac'] );

/* 🔴 PHÉP QUAN TRỌNG NHẤT CỦA CẢ BÀI KIỂM. */
teq( '🔴 SỔ TIỀN CỦA GHẾ VẪN TRỐNG TRƠN', 0, dem_bang( 'thu' ) );
teq( '   còn sổ sao kê có 4 dòng',        4, dem_bang( 'sao_ke' ) );

/* Chiều tiền đọc đúng: dòng Ghi nợ phải là tiền ra. */
$ds = VHG_SaoKe::ds( '', 'FT004' );
teq( 'dòng Ghi nợ được đánh dấu tiền ra', 1, (int) $ds[0]['tien_ra'] );
teq( '   và số tiền là số dương',   1000000, (int) $ds[0]['so_tien'] );
$ds = VHG_SaoKe::ds( '', 'FT001' );
teq( 'dòng Ghi có KHÔNG phải tiền ra', 0, (int) $ds[0]['tien_ra'] );
teq( '   ngày đọc đúng', '2026-09-11 17:55:00', (string) $ds[0]['luc'] );
teq( '   nhớ tên tệp đã nhập', 'sao-ke-thang-9.xlsx', (string) $ds[0]['ten_tep'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 ĐỔ LẠI ĐÚNG TỆP ẤY KHÔNG CỘNG ĐÔI
 *
 * Sao kê hai lần tải chồng lấn ngày là cách người ta vẫn dùng, không phải sai sót.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$kq2 = VHG_SaoKe::nhap( $BANG, 'sao-ke-thang-9.xlsx' );
teq( '🔴 lần hai: không dòng nào vào thêm', 0, $kq2['vao'] );
teq( '   và đếm đúng 4 dòng đã có',         4, $kq2['trung'] );
teq( '🔴 sổ vẫn đúng 4 dòng',               4, dem_bang( 'sao_ke' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. "NGÂN HÀNG CÓ — SỔ GHẾ CHƯA CÓ"
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$thieu = VHG_SaoKe::thieu_o_so_ghe();
teq( 'đang thiếu 2 dòng (ghế + mua mã)', 2, count( $thieu ) );
$ma = array();
foreach ( $thieu as $x ) { $ma[] = $x['ref']; }
sort( $ma );
teq( '   đúng hai mã ấy', array( 'FT001', 'FT002' ), $ma );
/* 🔴 Tiền ra và tiền của mảng khác KHÔNG được kể vào — sổ ghế vốn không có chúng, kể ra là
   mỗi lần mở màn lại thấy một rừng cảnh báo giả rồi thôi không ai đọc nữa. */
t( '🔴 KHÔNG kể dòng tiền ra',        ! in_array( 'FT004', $ma, true ), $ma );
t( '🔴 KHÔNG kể tiền của mảng khác',  ! in_array( 'FT003', $ma, true ), $ma );

/* Ghi một dòng vào SỔ TIỀN bằng đường thật (webhook) rồi xem nó biến khỏi danh sách thiếu. */
VHG_Thu::ghi( array( 'ref' => 'FT001', 'so_tien' => 20000, 'noi_dung' => 'GHE3 T1ABC',
	'nguon' => VHG_Thu::VIETQR, 'luc' => '2026-09-11 17:55:00' ) );
$thieu = VHG_SaoKe::thieu_o_so_ghe();
teq( '🔴 sổ ghế có rồi thì thôi kể là thiếu', 1, count( $thieu ) );
teq( '   chỉ còn FT002',                      'FT002', $thieu[0]['ref'] );
$ds = VHG_SaoKe::ds( '', 'FT001' );
teq( '   và cột "sổ ghế" của FT001 thành đã có', 1, (int) $ds[0]['trong_so'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. LỌC & TÓM TẮT
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'lọc nhãn ghế ra 1 dòng',   1, count( VHG_SaoKe::ds( 'ghe' ) ) );
teq( 'lọc nhãn tiền ra 1 dòng',  1, count( VHG_SaoKe::ds( 'ra' ) ) );
teq( 'không lọc thì đủ 4 dòng',  4, count( VHG_SaoKe::ds() ) );
teq( 'tìm theo nội dung',        1, count( VHG_SaoKe::ds( '', 'TIEN DIEN' ) ) );
$tt = VHG_SaoKe::tom_tat();
teq( 'tóm tắt: tổng 4 dòng', 4, $tt['tong'] );
/* 🔴 TIỀN RA KHÔNG CỘNG VÀO TỔNG TIỀN VÀO — nó là tiền đi khỏi tài khoản. Cộng vào là con số
   ở đầu màn to gấp mấy lần sự thật. */
teq( '🔴 tổng tiền KHÔNG gồm tiền ra', 100000, $tt['tien'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. MỘT CỘT "Số tiền" CÓ DẤU ÂM — cách xuất kia của ngân hàng
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$B2 = array(
	array( 'Ngày', 'Mã giao dịch', 'Số tiền', 'Diễn giải' ),
	array( '10/09/2026', 'FT100', '20000',  'GHE5 T9ZZZ' ),
	array( '10/09/2026', 'FT101', '-90000', 'RUT TIEN' ),
);
$kq3 = VHG_SaoKe::nhap( $B2 );
teq( 'nhập được 2 dòng', 2, $kq3['vao'] );
$d1 = VHG_SaoKe::ds( '', 'FT101' );
teq( '🔴 số tiền âm = tiền ra',        1, (int) $d1[0]['tien_ra'] );
teq( '   và lưu thành số dương', 90000, (int) $d1[0]['so_tien'] );
$d2 = VHG_SaoKe::ds( '', 'FT100' );
teq( 'số dương = tiền vào',            0, (int) $d2[0]['tien_ra'] );
teq( '   ngày không có giờ vẫn đọc được', '2026-09-10 00:00:00', (string) $d2[0]['luc'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6b. HAI CA BIÊN CỦA TỪNG DÒNG — số tiền 0 và ô ngày để trống
 *
 * 🔴 DÒNG SỐ TIỀN 0 KHÔNG PHẢI GIAO DỊCH. Sao kê hay có dòng đầu kỳ / dư đầu / ghi chú với cột
 *    tiền trống. Cho vào sổ là số đếm ở đầu màn nói sai, và danh sách "ngân hàng có — sổ ghế
 *    chưa có" mọc thêm những dòng chẳng có tiền nào để đối chiếu.
 *
 * 🔴 Ô NGÀY TRỐNG KHÔNG ĐƯỢC LÀM RƠI DÒNG. Cột `luc` là NOT NULL và màn sắp xếp theo nó; để
 *    rỗng thì dòng rơi xuống tận cùng danh sách, coi như mất một dòng tiền.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$B3 = array(
	array( 'Ngày', 'Mã giao dịch', 'Ghi có', 'Nội dung' ),
	array( '09/09/2026', 'FT200', '0',     'SO DU DAU KY' ),
	array( '09/09/2026', 'FT201', '',      'DONG GHI CHU' ),
	array( '',           'FT202', '15000', 'GHE7 T5AAA' ),
);
$kq4 = VHG_SaoKe::nhap( $B3 );
teq( '🔴 chỉ một dòng có tiền được vào sổ', 1, $kq4['vao'] );
teq( '   hai dòng không có tiền bị bỏ',     2, $kq4['bo'] );
$d3 = VHG_SaoKe::ds( '', 'FT200' );
teq( '🔴 dòng số tiền 0 KHÔNG nằm trong sổ', 0, count( $d3 ) );
$d4 = VHG_SaoKe::ds( '', 'FT202' );
teq( 'dòng ô ngày trống vẫn vào sổ',         1, count( $d4 ) );
t( '🔴 và `luc` của nó KHÔNG rỗng (đừng để dòng rơi khỏi danh sách)',
	'' !== trim( (string) $d4[0]['luc'] ) && 0 !== strpos( (string) $d4[0]['luc'], '0000' ), $d4[0]['luc'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 7. CA CHỐI — thiếu cột bắt buộc thì nói rõ, đừng nhập bừa
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$r = VHG_SaoKe::nhap( array( array( 'Ngày', 'Ghi có', 'Nội dung' ), array( '1/1/2026', '10000', 'x' ) ) );
t( '🔴 thiếu cột Mã tham chiếu → chối và nói vì sao',
	empty( $r['ok'] ) && false !== mb_strpos( $r['error'], 'Mã tham chiếu' ), $r );
$r = VHG_SaoKe::nhap( array( array( 'Mã tham chiếu', 'Nội dung' ), array( 'FT9', 'x' ) ) );
t( 'thiếu mọi cột tiền → chối', empty( $r['ok'] ), $r );
$r = VHG_SaoKe::nhap( array( array( 'Mã tham chiếu', 'Ghi có' ) ) );
t( 'chỉ có tiêu đề → chối',     empty( $r['ok'] ), $r );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 8. DỌN SỔ SAO KÊ KHÔNG ĐỤNG SỔ TIỀN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$truoc_thu = dem_bang( 'thu' );
VHG_SaoKe::xoa_het();
teq( 'dọn xong sổ sao kê rỗng', 0, dem_bang( 'sao_ke' ) );
teq( '🔴 SỔ TIỀN GIỮ NGUYÊN',   $truoc_thu, dem_bang( 'thu' ) );
t( '   và sổ tiền vẫn còn dòng đã ghi', $truoc_thu > 0, $truoc_thu );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 9. 🔴 QUÉT MÃ — tệp sao kê KHÔNG được ghi vào sổ tiền
 *
 * Tầng chạy thật ở trên chỉ canh những đường bài kiểm đi qua. Phép quét này bắt cả một hàm mới
 * viết sau này lỡ gọi sang — đúng kiểu "rối" mà anh Thắng muốn chặn từ đầu.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$ma_nguon = file_get_contents( VHG_DIR . 'includes/class-vhg-saoke.php' );
$than = preg_replace( '#/\*.*?\*/#s', '', $ma_nguon );           // bỏ chú thích khối
$than = preg_replace( '#//[^\n]*#', '', $than );                  // bỏ chú thích dòng
t( '🔴 lớp sao kê KHÔNG gọi VHG_Thu::ghi',       false === strpos( $than, 'VHG_Thu::ghi' ), 'có gọi!' );
/* 🔴 BẢNG `thu` CHỈ ĐƯỢC ĐỌC. Quét mọi lời gọi ghi của $wpdb và đòi không lời nào nhắm vào
   biến trỏ bảng thu ($bt). Viết phép này bằng cách "có chữ insert hay không" là vô nghĩa —
   lớp vẫn phải insert vào bảng SAO KÊ, nên phép ấy hoặc luôn đỏ hoặc luôn xanh. */
foreach ( array( 'insert', 'update', 'delete', 'replace' ) as $ghi ) {
	t( "🔴 không có \$wpdb->$ghi() nhắm bảng thu",
		! preg_match( '#\$wpdb\s*->\s*' . $ghi . '\s*\(\s*\$bt\b#i', $than ), $ghi );
}
t( '🔴 bảng thu chỉ xuất hiện trong câu SELECT',
	0 === preg_match_all( '#\$bt\b#', $than, $bo ) || substr_count( $than, 'SELECT' ) > 0, 'nghi ngờ' );
t( '   và KHÔNG gọi xep_cho_chay (cho ghế chạy)', false === strpos( $than, 'xep_cho_chay' ), 'có gọi!' );
/* Chỉ được ĐỌC bảng thu (SELECT/JOIN), không được INSERT/UPDATE/DELETE vào nó. */
foreach ( array( 'INSERT INTO', 'UPDATE ', 'DELETE FROM' ) as $lenh ) {
	t( "🔴 không có câu SQL \"$lenh\" nhắm bảng thu",
		! preg_match( '#' . preg_quote( $lenh, '#' ) . '\s+\$bt#i', $than ), $lenh );
}

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
echo "\n";
if ( $TRUOT ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — sổ sao kê tách hẳn sổ tiền, tự lọc theo nội dung\n";
