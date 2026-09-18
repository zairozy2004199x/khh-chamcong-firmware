<?php
/**
 * CHẤM CÔNG CHỈ LÀ LỜI NHẮC TRÊN MÀN BÁO CÁO — KHÔNG CÒN LÀ CỬA GÁC.
 *
 * ==============================================================================================
 * 🔴 LUẬT ĐÃ ĐẢO — 18/09/2026. Anh Thắng: *"hãy loại bỏ tính năng bắt checkin mới nộp báo cáo,
 *    mà hãy chỉ đưa cảnh báo thôi"*.
 *
 *    Trước đó bài này canh chiều NGƯỢC LẠI: cửa "chưa chấm công thì chưa cho vào báo cáo" phải
 *    gác đủ MỌI đường vào. Không xoá bài, ĐẢO nó — xoá đi là mở đường cho ai đó nối lại cửa ấy
 *    mà không ai biết vì sao nó từng bị bỏ.
 *
 * ==============================================================================================
 * 🔴 VÌ SAO BỎ CỬA — HAI CHỖ HỎNG KHÔNG SỬA ĐƯỢC BẰNG CÁCH VÁ THÊM
 * ==============================================================================================
 *   1. CỬA HỎI SAI NGƯỜI. Nó soi "PIN báo cáo này hôm nay chấm công chưa", mà
 *      `/cham-cong-online` có PHIÊN ĐĂNG NHẬP RIÊNG — người mở trạm rất hay đang là TÀI KHOẢN
 *      KHÁC. Anh Thắng 18/09/2026: *"anh nghi khả năng đăng nhập 2 tài khoản, mà gặp cảnh báo
 *      kia nên nhảy sang trang là nó không hiểu dẫn đến đơ"*. Chấm công xong bằng tài khoản B,
 *      quay lại bấm "Tôi đã chấm công xong" thì máy chủ vẫn soi tài khoản A và vẫn chối —
 *      người dùng kẹt trong một vòng không có lối ra.
 *   2. NÚT "CHẤM CÔNG NGAY" MỞ TAB MỚI (`target=_blank`). Trong PWA / trình duyệt trong app
 *      trên điện thoại, tab mới thường không có đường quay lại.
 *
 *   Và cái giá của việc chặn là sai chỗ: nó đem KỶ LUẬT GIỜ GIẤC ra khoá việc ghi nhận TIỀN
 *   MẶT. Tiền không vào sổ là mất thật; quên chấm công thì mai bù được.
 *
 * ==============================================================================================
 * 🔴 BỎ CỬA CÒN DỌN LUÔN MỘT HỌ LỖI (giữ nguyên bài học 16/09/2026)
 * ==============================================================================================
 *    Ca "chưa chấm công" từng trả về một phản hồi RẤT NGẮN — ok + pinOk + chuaChamCong, KHÔNG
 *    có `coso`, KHÔNG có `banBc`. Đường vào nào quên kiểm là màn hiện "phạm vi 0 cơ sở · mã báo
 *    cáo ?" — TRÔNG Y HỆT lỗi opcache, và khối chẩn đoán còn cử người đi xoá opcache cho một
 *    lỗi nằm chỗ khác. Nay `boot()` LUÔN trả gói đầy đủ, nên không còn ca ngắn nào để sót.
 *
 * ⚠️ BÀI NÀY ĐẾM CHỖ GỌI, KHÔNG ĐẾM CHUỖI (CLAUDE.md §6, bài học 0.18.1).
 *
 * Chạy: php tools/test/kiem-ghe-cua-cham-cong.php   (chay-het.sh tự gom)
 */
$goc  = dirname( __DIR__, 2 );
$js   = (string) file_get_contents( $goc . '/vhcp-ghe/includes/class-vhg-trang.php' );
$bc   = (string) file_get_contents( $goc . '/vhcp-ghe/includes/class-vhg-baocao.php' );
$LOI = 0; $SO = 0;
function t( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . "\n";
	if ( ! $ok ) { $LOI++; }
}

echo "── Máy chủ: không còn cửa chặn ──\n";
t( '🔴 boot() KHÔNG còn nhánh trả về sớm chặn ca chưa chấm công',
	false === strpos( $bc, "'chuaChamCong' => 1," ) );
t( '🔴 nhưng VẪN tính được, để còn nhắc',
	false !== strpos( $bc, '$nhac_cham = self::cham_cong_chua_(' ) );
t( '🔴 và gửi cờ ấy trong gói ĐẦY ĐỦ', false !== strpos( $bc, "'nhacChamCong' => \$nhac_cham," ) );
t( 'hàm dò vẫn còn (chỉ đổi vai trò, không xoá)',
	false !== strpos( $bc, 'private static function cham_cong_chua_(' ) );
/* FAIL-OPEN vẫn phải nguyên: một dải vàng nói sai cũng là nói sai. */
t( 'fail-open giữ nguyên — lỗi gì cũng cho qua',
	false !== strpos( $bc, 'return false;       // lỗi gì cũng CHO QUA' ) );

/* 🔴 MỌI ĐƯỜNG RA CỦA boot() PHẢI MANG VÂN TAY. Đây chính là thứ ca ngắn ngày xưa thiếu, và là
   gốc của cả buổi chiều 16/09 đổ oan cho opcache. Nay chỉ còn một đường ra thành công. */
if ( preg_match( '/public static function boot\(.*?\n\t\}/s', $bc, $mb ) ) {
	$than = $mb[0];
	$so_ok = preg_match_all( "/'pinOk' => true/", $than );
	$so_ban = preg_match_all( "/'banBc' => self::BAN/", $than );
	t( "boot(): mọi đường ra thành công đều mang banBc (ok=$so_ok · banBc=$so_ban)",
		$so_ok > 0 && $so_ok === $so_ban );
} else {
	t( 'đọc được thân boot()', false );
}

echo "── Giao diện: nhắc, không chặn ──\n";
t( '🔴 không còn màn chào "chưa chấm công"', false === strpos( $js, 'function veChuaChamCong' ) );
t( '🔴 và không còn lời gọi nào tới nó', false === strpos( $js, 'veChuaChamCong(' ) );
$con = preg_match_all( '/\.chuaChamCong/', $js );
t( "🔴 không còn nhánh nào rẽ theo chuaChamCong — đang có $con", 0 === $con );

t( '🔴 có dải nhắc trên màn báo cáo', false !== strpos( $js, 'if (BC.nhacChamCong) {' ) );
t( 'dải nói rõ VẪN nộp được', false !== strpos( $js, 'Vẫn nộp báo cáo bình thường' ) );
/* 🔴 NÓI RA CHUYỆN HAI TÀI KHOẢN. Đây chính là chỗ làm người dùng kẹt ở bản trước; im lặng thì
   họ lại tưởng hệ đếm sai. */
t( '🔴 và nói ra chuyện hai tài khoản',
	false !== strpos( $js, 'trang chấm công đăng nhập riêng' ) );
/* 🔴 KHÔNG TẮT NÚT NÀO. Cái dễ hỏng ở đây không phải dải cảnh báo, mà là người sau đọc thấy nó
   rồi "làm cho chặt" bằng một dòng disabled. */
$i_d = strpos( $js, 'if (BC.nhacChamCong) {' );
t( '🔴 dải nhắc KHÔNG tắt nút nào',
	false !== $i_d && false === strpos( substr( $js, $i_d, 1400 ), 'disabled' ) );
/* Dải phải đứng TRƯỚC mọi ô nhập — đọc sau khi đã nộp xong thì bằng không đọc. */
$i_wrap = strpos( $js, "var wrap=el('div','bc-wrap bc-app-in');" );
t( 'dải đứng TRƯỚC khối nhập', false !== $i_d && false !== $i_wrap && $i_d < $i_wrap );

echo "── Khối chẩn đoán không được đổ oan (giữ nguyên) ──\n";
t( 'chỉ kết luận "mã cũ" khi THIẾU banBc', false !== strpos( $js, 'var maCu=!BC.banBc;' ) );
t( 'có nhánh nói thẳng "KHÔNG phải lỗi opcache" khi vân tay vẫn đúng',
	false !== strpos( $js, 'KHÔNG phải lỗi opcache' ) );
t( 'nhánh ấy dặn ĐỪNG xoá opcache', false !== strpos( $js, 'ĐỪNG xoá opcache' ) );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
