<?php
/**
 * GIAO DỊCH LẺ CỦA CỔNG — ĐỂ PLUGIN KHÁC CÙNG SITE LẤY ĐƯỢC.
 *
 * ==============================================================================================
 * Anh Thắng 16/09/2026: *"có bên khác muốn lấy dữ liệu momo, nhưng chỉ bóc theo ngày, không lấy
 * theo giao dịch lẻ được"*.
 *
 * 🔴 CHỖ MẤT DỮ LIỆU NẰM Ở CHÍNH MÌNH, KHÔNG PHẢI Ở CỔNG.
 *    Trình duyệt đọc đủ từng dòng file (1016 dòng/tháng) rồi gửi lên; MÁY CHỦ gộp ngay thành một
 *    dòng cho mỗi (ngày × cửa hàng) — `so_dong` chỉ còn là con số đếm, từng giao dịch bị vứt tại
 *    cửa nạp. Mà kể cả giữ lại cũng vô dụng: cửa nạp chỉ gửi 4 cột (ngày · tiền · tên cửa hàng ·
 *    mã cửa hàng), MÃ GIAO DỊCH chưa bao giờ được chọn, nên không có gì để ghép với mã đối tác
 *    bên kia.
 *
 * 🔴 VÀ CÁI BẪY LỚN NHẤT CỦA BẢN VÁ NÀY LÀ ĐẾM TIỀN HAI LẦN.
 *    Giao dịch lẻ đổ vào bảng `saoke_cong` — cùng bảng Việt QR đang dùng. Nếu có bất kỳ phép
 *    cộng doanh thu nào đọc bảng ấy mà KHÔNG khoá theo `nguon`, thì từ hôm nay tiền MoMo cộng
 *    thêm một lần nữa vào chỗ đó. Đếm thiếu ai cũng thấy; đếm gấp đôi không ai thấy (§8).
 *    Bài này soi TỪNG chỗ đọc bảng ấy.
 *
 * Chạy: php tools/test/kiem-saoke-giao-dich-le.php   (chay-het.sh tự gom)
 */
$goc = dirname( __DIR__, 2 );
$s   = (string) file_get_contents( $goc . '/vhcp-saoke/vhcp-saoke.php' );
$app = (string) file_get_contents( $goc . '/vhcp-saoke/app.html' );
$LOI = 0; $SO = 0;
function t( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . "\n";
	if ( ! $ok ) { $LOI++; }
}

echo "── Không được đếm tiền hai lần ──\n";
/* Mọi truy vấn đọc bảng giao dịch cổng phải khoá theo nguồn. Bài đếm câu SELECT trên bảng ấy rồi
   soi từng câu — thêm một câu quên `nguon` là đỏ, kể cả khi màn hình trông vẫn đúng. */
/* ⚠️ ĐIỀU KIỆN `nguon` ĐƯỢC DỰNG TRƯỚC CÂU SELECT (mảng $wa/$wc/$w rồi mới implode), nên phải
   soi cả khúc TRƯỚC `FROM $tc`, không chỉ khúc sau. Bản đầu của bài chỉ nhìn phía sau và báo đỏ
   bốn câu hoàn toàn đúng luật — bài kêu oan là bài người ta tắt đi. */
preg_match_all( '/FROM \$tc\b/', $s, $mq, PREG_OFFSET_CAPTURE );
$thieu = array();
foreach ( $mq[0] as $x ) {
	$vt  = (int) $x[1];
	$quanh = substr( $s, max( 0, $vt - 700 ), 900 );
	/* Tra theo KHOÁ DUY NHẤT thì không cần lọc nguồn: một khoá chỉ thuộc đúng một dòng, không
	   có cách nào gom nhầm tiền của nguồn khác vào. */
	if ( false !== strpos( $quanh, 'WHERE khoa=%s' ) ) { continue; }
	if ( false === strpos( $quanh, 'nguon' ) ) { $thieu[] = $vt; }
}
t( '🔴 mọi câu SELECT trên bảng giao dịch cổng đều khoá theo nguon (' . count( $mq[0] ) . ' câu, thiếu '
	. count( $thieu ) . ')', ! $thieu );
t( '🔴 màn Tổng hợp doanh thu cơ sở vẫn gõ cứng vietqr',
	false !== strpos( $s, "WHERE nguon='vietqr' AND doc_duoc=1" ) );
/* Hai bảng phải tách: gộp (congfile) và lẻ (cong). Trộn là đếm hai lần. */
t( 'bảng gộp và bảng lẻ vẫn là HAI bảng khác nhau',
	false !== strpos( $s, "'saoke_congfile'" ) && false !== strpos( $s, "'saoke_cong'" ) );

echo "── Cửa đọc cho plugin khác ──\n";
t( 'có gd_cong_ds()', false !== strpos( $s, 'public static function gd_cong_ds(' ) );
t( 'có bộ lọc saoke_gd_cong cho bên thích dùng filter',
	false !== strpos( $s, "add_filter( 'saoke_gd_cong'" ) && false !== strpos( $s, 'function loc_gd_cong(' ) );
/* 🔴 CHỈ ĐỌC. Một cửa mở cho plugin khác mà ghi được là mất dấu vết: sửa dữ liệu ở đâu không ai
   truy ra. */
if ( preg_match( '/function gd_cong_ds\(.*?\n\t\}/s', $s, $mg ) ) {
	foreach ( array( 'INSERT', 'UPDATE', 'DELETE', '->insert(', '->update(', '->delete(' ) as $ghi ) {
		t( "🔴 gd_cong_ds() không có đường ghi ($ghi)", false === strpos( $mg[0], $ghi ) );
	}
	t( 'chặn nguồn lạ trước khi truy vấn', false !== strpos( $mg[0], "in_array( \$nguon, self::cong_ds(), true )" ) );
	t( 'thiếu bảng thì trả rỗng, không nổ', false !== strpos( $mg[0], "SHOW TABLES LIKE" ) );
	t( '🔴 có trần cứng cho giới hạn đọc', false !== strpos( $mg[0], 'min( 20000,' ) );
	t( 'trả mảng thuần, không trả đối tượng wpdb', false !== strpos( $mg[0], 'ARRAY_A' ) );
} else {
	t( 'đọc được thân gd_cong_ds()', false );
}

echo "── Cửa nạp giao dịch lẻ ──\n";
t( 'màn nạp có ô chọn cột Mã giao dịch', false !== strpos( $app, "id('cotMaGD')" ) );
t( 'ô ấy có mục "(không dùng)" và mặc định không chọn',
	false !== strpos( $app, "(t === 'cotMaCH' || t === 'cotMaGD')" ) );
/* 🔴 KHÔNG CHỌN CỘT THÌ KHÔNG ĐƯỢC LÀM GÌ CẢ — nếp cũ phải giữ nguyên cho người không cần. */
t( '🔴 chưa chọn cột thì thoát ngay, không nạp gì',
	1 === preg_match( '/function cgNapGiaoDichLe\(.*?if\(vg === \'\' \|\| vg == null\) return;/s', $app ) );
t( 'dòng không có mã giao dịch thì bỏ, không gửi lên', false !== strpos( $app, 'if(!maGD) continue;' ) );
t( 'chạy SAU khi nạp gộp xong, không thay nó',
	1 === preg_match( "/ph\.join\('<br>• '\).*?cgNapGiaoDichLe\( *nguon, *kq *\)/s", $app ) );
/* 🔴 KHÔNG ĐOÁN CỘT MÃ GIAO DỊCH. Đoán sai là cả nghìn dòng mang mã rác vào bảng, mà bảng khoá
   chống trùng theo chính mã — dọn lại rất phiền. */
t( '🔴 không tự đoán cột mã giao dịch', false === strpos( $app, "cot.maGD" ) );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
