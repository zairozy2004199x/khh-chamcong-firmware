<?php
/**
 * TRANG /it: CỘT "ĐÃ THỬ ỔN" VÀ ĐƯỜNG LÙI BẢN.
 *
 * ==============================================================================================
 * Anh Thắng 16/09/2026: *"lúc test bản nào chạy ổn, anh sẽ chọn bản mới để up, tránh cứ lỗi nếu
 * chọn bản tiếp"*, sau một ngày ba lần phải chữa gấp.
 *
 * 🔴 CHIỀU PHỤ THUỘC LÀ THỨ DỄ LÀM HỎNG NHẤT Ở ĐÂY, VÀ NÓ HỎNG CÂM.
 *    Trang /it (trong plugin Ghế) đọc kho ảnh chụp của plugin Cứu Hộ. Chiều ấy được phép. Chiều
 *    NGƯỢC LẠI thì cấm: Cứu Hộ mà cần Ghế còn sống mới chạy được thì nó thành đúng thứ nó đi
 *    chữa — sáng 16/09 Ghế chết kéo /it chết theo, và không còn nút nào bấm được.
 *    Không ai thấy chiều phụ thuộc bị đảo cho tới hôm sự cố. Nên canh ở đây.
 *
 * Chạy: php tools/test/kiem-it-chon-ban.php   (chay-het.sh tự gom)
 */
$goc = dirname( __DIR__, 2 );
$ghe = (string) file_get_contents( $goc . '/vhcp-ghe/includes/class-vhg-trang.php' );
$ch  = (string) file_get_contents( $goc . '/vhcp-cuu-ho/vhcp-cuu-ho.php' );
$LOI = 0; $SO = 0;
function t( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . "\n";
	if ( ! $ok ) { $LOI++; }
}
/** Mã đã bỏ chú thích — chú thích được phép nhắc tên nhau để giải thích. */
function chi_ma( $php ) {
	$ra = '';
	foreach ( token_get_all( $php ) as $t ) {
		if ( is_array( $t ) ) {
			if ( in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
			$ra .= $t[1];
		} else { $ra .= $t; }
	}
	return $ra;
}
$ch_ma = chi_ma( $ch );

echo "── Chiều phụ thuộc ──\n";
t( '/it đọc kho ảnh qua bộ lọc (được phép)',
	false !== strpos( $ghe, "apply_filters( 'vhcp_cuu_ho_anh', array() )" ) );
t( 'Cứu Hộ khai kho ra qua bộ lọc', false !== strpos( $ch, "add_filter( 'vhcp_cuu_ho_anh'" ) );
/* 🔴 Chiều ngược lại: Cứu Hộ không được gọi sang Ghế bằng bất kỳ đường nào. */
t( '🔴 Cứu Hộ KHÔNG gọi lớp VHG_*', false === strpos( $ch_ma, 'VHG_' ) );
t( '🔴 Cứu Hộ KHÔNG đọc bộ lọc nào của trang /it',
	false === strpos( $ch_ma, "apply_filters( 'vhcp_tu_cap_nhat_ds'" ) );
/* Thiếu Cứu Hộ thì /it phải vẫn chạy — chỉ là không có bản cũ nào để lùi. */
t( 'thiếu Cứu Hộ thì /it vẫn chạy (bộ lọc trả rỗng, không đỏ)',
	false !== strpos( $ghe, "if ( ! is_array( \$kq ) ) {" )
	&& false !== strpos( $ghe, 'Chưa cài plugin Cứu Hộ' ) );

echo "── Soát quyền ở chỗ THỰC HIỆN ──\n";
/* 🔴 Bộ lọc thì ai gắn vào cũng gọi được. Vẽ nút ở /it đã soát quyền rồi, nhưng chỗ CÀI mới là
   chỗ có hậu quả — nó ghi mã nguồn lên máy chủ. Soát ở cả hai, và bắt buộc ở chỗ sau. */
t( '🔴 Cứu Hộ tự soát quyền trong cai_ho(), không tin bên gọi',
	1 === preg_match( '/function cai_ho\(.*?current_user_can\( \'update_plugins\' \)/s', $ch ) );
t( '/it cũng chỉ mở cổng cn_ cho vai quản trị', false !== strpos( $ghe, "0 === strpos( \$viec, 'cn_' )" ) );

echo "── Cột đã thử ổn ──\n";
t( 'cn_ds_ trả kèm onDinh và banCu',
	false !== strpos( $ghe, "'onDinh' => self::cn_ban_on_(" )
	&& false !== strpos( $ghe, "'banCu'  => self::cn_ban_cu_(" ) );
t( 'có việc cn_danh_dau ở bộ định tuyến', false !== strpos( $ghe, "if ( 'cn_danh_dau' === \$viec )" ) );
t( 'có việc cn_lui ở bộ định tuyến', false !== strpos( $ghe, "if ( 'cn_lui' === \$viec )" ) );
t( 'bảng có cột "Đã thử ổn"', false !== strpos( $ghe, '<th>Đã thử ổn</th>' ) );

/* 🔴 SỐ BẢN ĐI VÀO OPTION PHẢI ĐƯỢC LỌC. Nó tới từ trình duyệt; nhét thẳng vào option là mở một
   đường ghi chuỗi tuỳ ý vào CSDL. */
t( '🔴 lọc số bản trước khi ghi (chỉ số và dấu chấm)',
	false !== strpos( $ghe, "preg_replace( '/[^0-9.]/', '', (string) \$d['ban'] )" ) );
t( '🔴 tên tệp ảnh chụp đi qua basename() — không cho leo thư mục',
	false !== strpos( $ghe, "basename( (string) \$d['tep'] )" ) );

/* Nút "Đánh dấu ổn" không được hiện khi bản đang chạy ĐÃ là bản đánh dấu — bấm lại không làm gì,
   mà một nút bấm không làm gì là nút người ta bấm mãi rồi tưởng hỏng. */
t( 'ẩn nút đánh dấu khi bản đang chạy đã được đánh dấu',
	false !== strpos( $ghe, "on === p.hien" ) );
/* Chưa có ảnh chụp nào thì đừng vẽ ô chọn + nút lùi rỗng. */
t( 'chưa có ảnh chụp thì không vẽ ô lùi', false !== strpos( $ghe, 'if (cu.length) {' ) );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
