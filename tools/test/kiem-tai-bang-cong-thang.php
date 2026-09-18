<?php
/**
 * Ô CHỌN THÁNG PHẢI ĐIỀU KHIỂN ĐƯỢC NÚT TẢI — và kỳ phải là THÁNG, không phải tuần.
 *
 * =================================================================================================
 * 🔴 LỖI BÀI NÀY SINH RA ĐỂ CANH
 * =================================================================================================
 * Anh Thắng 18/09/2026, ảnh chụp màn hình ô xổ đang mở: *"Không chọn được tuần trước nữa. Chọn
 * được nhưng bấm xuất nó cũng chỉ lấy từ ngày 7-13."*
 *
 * Bản trước để nút tải là một thẻ `<a href="…&tuan=2026-09-07">` DỰNG SẴN Ở MÁY CHỦ, mang đúng
 * kỳ có trong URL lúc trang được vẽ. Đổi ô xổ thì chỉ đổi thứ hiện trên màn — cái `<a>` vẫn trỏ
 * về kỳ cũ. Người ta chọn tháng khác rồi bấm tải, và nhận về tệp của kỳ đang hiện:
 *
 *   · KHÔNG có thông báo lỗi, không có dấu hiệu gì — chỉ thấy tệp "sai ngày";
 *   · và tệp ấy vẫn nạp lại được, vì nó là tệp hợp lệ của kỳ kia. Sửa nhầm cả kỳ rồi gửi đi.
 *
 * Nên bài này KHÔNG hỏi "trang có ô xổ không". Nó hỏi đúng thứ đã hỏng: ô xổ và nút tải có nằm
 * TRONG CÙNG MỘT `<form>` không, và nút tải có phải nút gửi của chính cái form ấy không. Đó là
 * thứ duy nhất khiến trình duyệt gửi đi tháng NGƯỜI TA VỪA CHỌN.
 *
 * ⚠️ VÀ MỘT PHÉP THỬ ĐI VÒNG: nạp thẳng `?xuat=thang&dtm=…` rồi xem tệp nhận về mang tháng nào.
 *    Soát HTML thôi thì mai kia ai đó đổi tên tham số ở một đầu là bài vẫn xanh.
 *
 * Chạy: php tools/test/kiem-tai-bang-cong-thang.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 400 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

global $wpdb;

$CS  = 'TAI_SHOP';
$CHT = array( 'name' => 'Chị Trưởng', 'role' => VHCC_Vai::CHT, 'coso' => $CS, 'ma_nv' => 'TAICHT' );

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'TAICHT', 'ho_ten' => 'Chị Trưởng',
	'vai_tro' => 'Cửa hàng trưởng', 'cua_hang' => $CS, 'coso_quan' => $CS,
	'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'TAINV', 'ho_ten' => 'Em Nhân Viên',
	'vai_tro' => 'Nhân viên', 'cua_hang' => $CS, 'trang_thai_lam_viec' => 'Đang làm' ) );

$THANG_NAY = VHCC_TuanCong::dau_thang( (string) current_time( 'Y-m-d' ) );
$DS        = VHCC_TuanCong::ds_thang( 6 );
$THANG_CU  = $DS[3];                        // một tháng LÙI XA, không phải mặc định

/* Gieo một lượt chấm ở tháng cũ — tờ của nó phải có dữ liệu thì mới xuất được. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => substr( $THANG_CU, 0, 8 ) . '05',
	'ma_nv' => 'TAINV', 'ho_ten' => 'Em Nhân Viên', 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );

/* ====================================================================== 1. ô xổ bày ra tháng */

echo "— ô xổ bày ra THÁNG —\n";
ob_start();
VHCC_WebDonTuan::khoi_cua_hang( 'ky-thu', $CHT, $CS );
$h = ob_get_clean();

t( 'khối có vẽ ra', '' !== trim( $h ), $h );
t( '🔴 ô xổ tên là dtm (tháng), không còn dtt (tuần)',
	false !== strpos( $h, 'name="dtm"' ) && false === strpos( $h, 'name="dtt"' ), $h );
t( 'nhãn ô là "Tháng"', false !== mb_strpos( $h, '>Tháng<' ), $h );
foreach ( $DS as $th_x ) {
	t( 'ô xổ có ' . VHCC_TuanCong::ten_thang( $th_x ),
		false !== strpos( $h, 'value="' . $th_x . '"' ), $h );
}
t( '🔴 KHÔNG còn nhãn tuần kiểu "T2 … → CN …"', false === mb_strpos( $h, '→ CN' ), $h );

/* ============================================ 2. NÚT TẢI NẰM TRONG CÙNG FORM VỚI Ô XỔ */

echo "— nút tải đi cùng ô xổ —\n";

/* 🔴 KHÔNG CÒN THẺ `<a>` TẢI. Thẻ `<a>` mang kỳ dựng sẵn ở máy chủ — đó chính là lỗi. */
t( '🔴 không còn thẻ <a> tải tệp (đường dẫn dựng sẵn)',
	! preg_match( '#<a[^>]+xuat=(thang|tuan)#', $h ), $h );

/* Cắt ra đúng cái form chứa ô xổ, rồi hỏi trong CHÍNH nó có nút tải không. */
$form = '';
if ( preg_match_all( '#<form\b.*?</form>#s', $h, $m ) ) {
	foreach ( $m[0] as $f ) {
		if ( false !== strpos( $f, 'name="dtm"' ) ) { $form = $f; break; }
	}
}
t( 'tìm được form chứa ô xổ', '' !== $form, $h );
t( '🔴 NÚT TẢI nằm trong chính form ấy — đây là cả cái bản vá',
	false !== strpos( $form, 'name="xuat"' ) && false !== strpos( $form, 'value="thang"' ), $form );
t( '   và nó là <button>, tức nút gửi của form',
	1 === preg_match( '#<button[^>]+name="xuat"#', $form ), $form );
t( 'form đi bằng GET (tải tệp, không đổi gì trên máy chủ)',
	false !== strpos( $form, 'method="get"' ), $form );
t( 'form mang sẵn cơ sở', false !== strpos( $form, 'value="' . $CS . '"' ), $form );

/* 🔴 `xuat` PHẢI LÀ TÊN CỦA NÚT, KHÔNG PHẢI MỘT Ô ẨN. Ô ẩn thì nút "Xem" cũng tải tệp — người
   ta bấm Xem để soát trạng thái mà trình duyệt tụt xuống hộp tải về. */
t( '🔴 xuat là name của nút bấm, không phải input ẩn',
	! preg_match( '#<input[^>]+type="hidden"[^>]+name="xuat"#', $form ), $form );

/* Nút Xem vẫn còn, và nó KHÔNG mang `xuat`. */
t( 'vẫn còn nút Xem, và nó không tải tệp',
	false !== strpos( $form, 'name="xem"' ), $form );

/* ============================================ 3. ĐI VÒNG THẬT: ?xuat=thang&dtm=… ra đúng tháng */

if ( ! class_exists( 'ZipArchive' ) ) {
	echo "(bỏ qua phần tệp .xlsx: máy chạy bộ thử không có php-zip)\n";
} else {
	echo "— tải thật: tệp mang đúng tháng đã chọn —\n";

	/* 🔴 HỎI THẲNG LỚP XUẤT, KHÔNG SOÁT CHỮ. Tên tệp mang tháng, nên nó là chỗ đối chiếu rẻ và
	   chắc: chọn tháng nào thì tên tệp phải mang đúng tháng ấy. */
	$x = VHCC_TuanCong::xuat( $CHT, $CS, $THANG_CU );
	t( 'xuất được tháng cũ', ! empty( $x['ok'] ), $x );
	teq( '🔴 tên tệp mang ĐÚNG tháng đã chọn, không phải tháng đang chạy',
		'bang-cong-' . $CS . '-thang-' . substr( $THANG_CU, 0, 7 ) . '.xlsx', $x['ten'] );
	t( '   và KHÔNG mang tháng đang chạy',
		false === strpos( $x['ten'], substr( $THANG_NAY, 0, 7 ) ), $x['ten'] );

	/* Tờ phải đúng số ngày của CHÍNH tháng ấy — không phải 7, không phải 31 cứng. */
	$doc = VHCC_DocXlsx::doc( ( function ( $noi ) {
		$d = tempnam( sys_get_temp_dir(), 'kiemtai' );
		file_put_contents( $d, $noi );
		return $d;
	} )( $x['noi_dung'] ) );
	t( 'đọc lại được tệp', ! empty( $doc['ok'] ), $doc );
	$so_ngay = count( VHCC_TuanCong::ngay_cua( $THANG_CU ) );
	teq( '🔴 tờ rộng đúng 5 + số ngày của tháng ấy', 5 + $so_ngay, count( $doc['hang'][0] ) );

	/* Cột ngày đầu và ngày cuối phải là ngày 1 và ngày cuối THÁNG, không phải T2/CN. */
	t( '🔴 cột ngày đầu là ngày 1 của tháng',
		false !== strpos( (string) $doc['hang'][0][ VHCC_TuanCong::C_NGAY_DAU ], $THANG_CU ),
		$doc['hang'][0][ VHCC_TuanCong::C_NGAY_DAU ] );
	t( '🔴 cột ngày cuối là ngày cuối tháng',
		false !== strpos( (string) $doc['hang'][0][ VHCC_TuanCong::C_NGAY_DAU + $so_ngay - 1 ],
			VHCC_TuanCong::cuoi_thang( $THANG_CU ) ),
		$doc['hang'][0][ VHCC_TuanCong::C_NGAY_DAU + $so_ngay - 1 ] );
}

/* ====================================================================== 4. kỳ nửa tháng */

echo "— kỳ nửa tháng —\n";

/* 🔴 ANH THẮNG 18/09/2026: *"Tức trọng tháng, nhưng vẫn chọn kỳ để sửa. Tới tháng mà đang giữa
   kỳ thì tách cho sửa để up tháng đó để tính lương"*. Tháng là khung tính lương, nhưng giữa
   tháng phải chốt được nửa đầu — duyệt là KHOÁ, mà khoá cả tháng lúc mới mùng 15 là mười lăm
   ngày còn lại hết đường sửa. */
list( $t1, $d1 ) = VHCC_TuanCong::khoang( $THANG_CU, VHCC_TuanCong::KY_1 );
list( $t2, $d2 ) = VHCC_TuanCong::khoang( $THANG_CU, VHCC_TuanCong::KY_2 );
list( $tc, $dc ) = VHCC_TuanCong::khoang( $THANG_CU, VHCC_TuanCong::KY_CA );

teq( 'kỳ 1 bắt đầu ngày 1', substr( $THANG_CU, 0, 8 ) . '01', $t1 );
teq( 'kỳ 1 hết ngày 15',    substr( $THANG_CU, 0, 8 ) . '15', $d1 );
teq( 'kỳ 2 bắt đầu ngày 16', substr( $THANG_CU, 0, 8 ) . '16', $t2 );
teq( 'kỳ 2 hết vào ngày cuối tháng', VHCC_TuanCong::cuoi_thang( $THANG_CU ), $d2 );
/* 🔴 HAI NỬA GHÉP LẠI ĐÚNG BẰNG CẢ THÁNG, không hụt không chồng — chốt cả hai kỳ phải đủ dữ
   liệu tính lương tháng, y như chốt một lượt cả tháng. */
teq( '🔴 kỳ 1 + kỳ 2 = đúng số ngày của cả tháng',
	count( VHCC_TuanCong::ngay_cua( $tc, $dc ) ),
	count( VHCC_TuanCong::ngay_cua( $t1, $d1 ) ) + count( VHCC_TuanCong::ngay_cua( $t2, $d2 ) ) );
teq( 'không chồng ngày nào', 1,
	(int) ( strtotime( $t2 . ' UTC' ) - strtotime( $d1 . ' UTC' ) ) / 86400 );
/* Tháng 2 chỉ 28 ngày vẫn có kỳ 2 (16→28). Tháng nào ngắn hơn mốc thì `khoang()` trả rỗng. */
teq( 'tháng 2 vẫn có kỳ 2', '2026-02-28',
	VHCC_TuanCong::khoang( '2026-02-01', VHCC_TuanCong::KY_2 )[1] );

teq( 'nhãn kỳ 1 nói rõ là nửa tháng', 'Tháng ' . gmdate( 'm/Y', strtotime( $THANG_CU . ' UTC' ) )
	. ' · ' . VHCC_TuanCong::ds_ky()[ VHCC_TuanCong::KY_1 ], VHCC_TuanCong::ten_ky( $t1, $d1 ) );
teq( 'nhãn cả tháng vẫn gọn', VHCC_TuanCong::ten_thang( $THANG_CU ),
	VHCC_TuanCong::ten_ky( $tc, $dc ) );

/* ---- ô xổ kỳ có mặt trên màn, cùng form với nút tải ---- */
ob_start();
VHCC_WebDonTuan::khoi_cua_hang( 'ky-thu', $CHT, $CS );
$h2 = ob_get_clean();
t( '🔴 màn có ô xổ KỲ', false !== strpos( $h2, 'name="dtk"' ), $h2 );
$form2 = '';
if ( preg_match_all( '#<form\b.*?</form>#s', $h2, $m2 ) ) {
	foreach ( $m2[0] as $f ) {
		if ( false !== strpos( $f, 'name="dtm"' ) ) { $form2 = $f; break; }
	}
}
t( '🔴 ô KỲ nằm CÙNG form với ô tháng và nút tải',
	false !== strpos( $form2, 'name="dtk"' ) && false !== strpos( $form2, 'name="xuat"' ), $form2 );
foreach ( array_keys( VHCC_TuanCong::ds_ky() ) as $ma_k ) {
	t( 'ô xổ có kỳ ' . $ma_k, false !== strpos( $form2, 'value="' . $ma_k . '"' ), $form2 );
}

if ( class_exists( 'ZipArchive' ) ) {
	/* ---- tệp hai kỳ phải KHÁC NHAU, và chữ ký không dùng chéo được ---- */
	echo "— tệp mỗi kỳ một chữ ký —
";
	$x1 = VHCC_TuanCong::xuat( $CHT, $CS, $t1, $d1 );
	t( 'xuất được kỳ 1', ! empty( $x1['ok'] ), $x1 );
	teq( 'tờ kỳ 1 rộng đúng 5 + 15 ngày', 5 + 15,
		count( VHCC_DocXlsx::doc( ( function ( $n ) {
			$f = tempnam( sys_get_temp_dir(), 'kk' ); file_put_contents( $f, $n ); return $f;
		} )( $x1['noi_dung'] ) )['hang'][0] ) );
	t( '🔴 tên tệp kỳ 1 khác tên tệp cả tháng — hai tệp cùng tháng mà trùng tên là gửi nhầm ngay',
		$x1['ten'] !== VHCC_TuanCong::ten_tep( $CS, $tc, $dc ), $x1['ten'] );

	/* 🔴 DÁN DÒNG TỪ TỆP CẢ THÁNG SANG TỆP KỲ 1. Hai tệp bắt đầu cùng ngày 1 và trông rất
	   giống nhau; ký mỗi `tu_ngay` thì chữ ký khớp, và giờ của nửa sau chui vào một đơn chỉ
	   được phép động tới nửa đầu. Chữ ký phải ôm CẢ HAI đầu khoảng ngày. */
	$k_ca = VHCC_TuanCong::khoa_dong( $CS, $tc, $dc, 'TAINV' );
	$k_k1 = VHCC_TuanCong::khoa_dong( $CS, $t1, $d1, 'TAINV' );
	t( '🔴 khoá của cả tháng KHÁC khoá của kỳ 1 (cùng ngày bắt đầu)', $k_ca !== $k_k1 );
	t( '🔴 dán khoá tệp cả tháng vào tệp kỳ 1 thì chối',
		null === VHCC_TuanCong::doc_khoa( $k_ca, $CS, $t1, $d1 ) );
	t( 'và ngược lại cũng chối',
		null === VHCC_TuanCong::doc_khoa( $k_k1, $CS, $tc, $dc ) );
}

/* ---- khoá kỳ 1 thì kỳ 2 vẫn sửa được, còn cả tháng thì không ---- */
echo "— khoá một kỳ —
";
$wpdb->insert( VHCC_DB::t( 'don_tuan' ), array( 'coso' => $CS, 'tu_ngay' => $t1,
	'den_ngay' => $d1, 'ma_nv_gui' => 'TAICHT', 'ten_gui' => 'Chị Trưởng',
	'gui_luc' => current_time( 'mysql' ), 'trang_thai' => VHCC_TuanCong::DUYET,
	'so_dong' => 1, 'so_doi' => 1, 'doi' => '[]' ) );

t( '🔴 kỳ 1 đã khoá', VHCC_TuanCong::khoa_roi( $CS, $t1, $d1 ) );
t( '🔴 KỲ 2 VẪN SỬA ĐƯỢC — đây là cả lý do tách kỳ',
	! VHCC_TuanCong::khoa_roi( $CS, $t2, $d2 ) );
teq( '   và tải được thật', '', VHCC_TuanCong::vi_sao_khong_tai( $CHT, $CS, $t2, $d2 ) );

/* 🔴 CẢ THÁNG THÌ KHÔNG — nó chồng lên nửa đã chốt lương. Hỏi `tu_ngay=` thôi thì lượt duyệt
   sau ghi đè lên chính nửa đầu vừa khoá. */
$chan_ca = VHCC_TuanCong::vi_sao_khong_tai( $CHT, $CS, $tc, $dc );
t( '🔴 kỳ CẢ THÁNG chồng lên kỳ 1 đã khoá thì chối', '' !== $chan_ca, $chan_ca );
t( '   và câu chối chỉ đúng kỳ đang vướng',
	false !== mb_strpos( $chan_ca, 'Kỳ 1' ), $chan_ca );

/* Khoảng tự chế (gõ tay đường dẫn) phải chối — không thì ai cũng khoá được một mẩu tháng, và
   mấy mẩu ấy không ghép lại thành tháng nào cả. */
$chan_la = VHCC_TuanCong::vi_sao_khong_tai( $CHT, $CS, $t2, substr( $THANG_CU, 0, 8 ) . '20' );
t( '🔴 khoảng ngày tự chế thì chối', '' !== $chan_la, $chan_la );
t( '   và nói rõ phải chọn kỳ có thật',
	false !== mb_strpos( $chan_la, 'Kỳ 1' ) || false !== mb_strpos( $chan_la, 'không hợp lệ' ),
	$chan_la );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — chọn tháng và kỳ nào thì tải đúng kỳ ấy.\n";
