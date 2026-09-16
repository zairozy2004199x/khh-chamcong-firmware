<?php
/**
 * SAO KÊ NGÂN HÀNG — NGUỒN ĐỘC LẬP DUY NHẤT, NÊN ĐỌC SAI LÀ HỎNG CẢ CÁI ĐỐI SOÁT.
 *
 * Anh Thắng 16/09/2026: *"cột thực nộp mình sẽ lấy bên sao kê"*. Từ đó cột ấy không còn là số cơ
 * sở tự khai nữa — nó là số ngân hàng ghi. Mà mỗi chỗ đọc sai ở đây đều đi thẳng ra một lời buộc
 * tội sai:
 *
 *   · cộng nhầm một khoản TIỀN RA thành tiền nộp  -> báo cáo đẹp lên, không ai nộp thêm đồng nào;
 *   · bỏ sót một khoản vì không nhận ra cơ sở      -> "chưa nộp" GIẢ, tố oan người đã nộp thật;
 *   · nạp lại cùng một file mà cộng dồn            -> gấp đôi tiền nộp, ngày hôm ấy hoá ra nộp thừa;
 *   · tính nhầm ngày (nộp sáng hôm sau)            -> ngày nào cũng thiếu, hôm sau nào cũng thừa.
 *
 * Bốn chuyện ấy là bốn phần của bài này.
 *
 * Chạy: php tools/test/kiem-sao-ke.php
 */

require_once __DIR__ . '/fw/bo-do-khh-dt.php';

$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
require_once $goc . '/sao-ke.php';

$dat  = 0;
$hong = array();
function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) {
		$dat++;
	} else {
		$hong[] = $ten;
	}
}

/** Viết một file CSV tạm rồi đọc bằng đúng đường plugin dùng. */
function doc_csv( $noi_dung ) {
	$f = tempnam( sys_get_temp_dir(), 'sk' ) . '.csv';
	file_put_contents( $f, $noi_dung );
	$kq = khh_dt_doc_sao_ke( $f, 'sao-ke.csv' );
	wp_delete_file( $f );
	return $kq;
}

function dung_bang_sk() {
	global $wpdb;
	$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_sk() );
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang_sk() . " (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			ma_gd TEXT NOT NULL DEFAULT '' UNIQUE,
			ngay TEXT NOT NULL, gio INTEGER NOT NULL DEFAULT 0, ngay_tinh TEXT NOT NULL,
			so_tien REAL NOT NULL DEFAULT 0, noi_dung TEXT NOT NULL,
			tai_khoan TEXT NOT NULL DEFAULT '', cua_hang TEXT NOT NULL DEFAULT '', nap_luc TEXT NULL )"
	);
}

/* ============================================================ 1. đọc file có cột Ghi nợ / Ghi có */

$sk1 = "SAO KE TAI KHOAN\nChu tai khoan: CONG TY K&H\nTu ngay 14/09/2026 den 16/09/2026\n\n"
	. "Ngay giao dich,So tai khoan,Ghi no,Ghi co,Noi dung,Ma giao dich\n"
	. "15/09/2026 08:35,0123456789,,5.980.000,TUTU TAN PHU NOP TIEN 14/09,FT2609150001\n"
	. "15/09/2026 09:10,0123456789,,2.840.000,TUTU BINH DUONG NOP 14/09,FT2609150002\n"
	. "15/09/2026 14:20,0123456789,1.200.000,,TRA TIEN DIEN THANG 8,FT2609150003\n"
	. "15/09/2026 20:05,0123456789,,1.750.000,TUTU LOTTE GO VAP NOP 15/09,FT2609150004\n"
	. "16/09/2026 07:50,0123456789,,3.320.000,CHUYEN KHOAN KHONG RO,FT2609160001\n";

$kq = doc_csv( $sk1 );
phep( 'đọc được sao kê có dòng đầu trang', ! is_wp_error( $kq ) );
if ( is_wp_error( $kq ) ) {
	echo "\n✗ " . $kq->get_error_message() . "\n";
	exit( 1 );
}
phep( 'lấy đúng 4 khoản tiền vào', 4 === count( $kq['dong'] ) );
phep( 'bỏ đúng 1 khoản tiền ra', 1 === (int) $kq['tien_ra'] );
phep( 'không phải đoán chiều tiền', 0 === (int) $kq['doan_dau'] );
phep( 'đọc đúng số tiền có dấu chấm ngăn', 5980000.0 === (float) $kq['dong'][0]['so_tien'] );

/* ============================================================ 2. nhận mặt cơ sở */

khh_dt_dat_ghep_bank(
	array(
		array( 'khoa' => 'TUTU TAN PHU', 'cua_hang' => 'TuTu Train - Aeon Tân Phú' ),
		array( 'khoa' => 'TUTU BINH DUONG', 'cua_hang' => 'Tutu Train - Bình Dương' ),
		array( 'khoa' => 'TUTU LOTTE GO VAP', 'cua_hang' => 'TuTu Train - Lotte Gò Vấp' ),
	)
);
$kq = doc_csv( $sk1 );
$ch = wp_list_pluck( $kq['dong'], 'cua_hang' );
phep( 'nhận ra cơ sở theo nội dung chuyển khoản', 'TuTu Train - Aeon Tân Phú' === $ch[0] );
phep( 'khoản không có dấu hiệu gì thì để trống, KHÔNG đoán bừa', '' === $ch[3] );

/* ============================================================ 2b. MÃ NỘP TIỀN
   Anh Thắng 16/09/2026 gửi một dòng sao kê thật của VPBank:
       NHAN TU 050066112230 TRACE 213493 ND IBFT VC Bien Hoa KH705MTDMN0023 — 590.000 vào
   Nhà mình đã có sổ "Mã nộp tiền" mỗi cơ sở một mã, người nộp gõ mã ấy vào nội dung. */

khh_dt_dat_ghep_bank(
	array(
		'VINCOM BIÊN HÒA'            => 'KH705MTDMN0023',
		'TuTu Train - Aeon Tân Phú'  => 'KH705MTDMN0002',
		'Tutu Train - Bình Dương'    => 'KH705MTDMN0020',
	)
);
$nd_that = 'NHAN TU 050066112230 TRACE 213493 ND IBFT VC Bien Hoa KH705MTDMN0023';
phep( 'đọc được mã nộp tiền giữa một nội dung dài', 'VINCOM BIÊN HÒA' === khh_dt_doan_co_so( $nd_that, '' ) );
phep( 'mách được mã đọc thấy trong nội dung', 'KH705MTDMN0023' === khh_dt_ma_trong_nd( $nd_that ) );

/* 🔴 MÃ NGẮN NẰM GỌN TRONG MÃ DÀI — chỗ chết người.
   KH705MTDMN0002 (Tân Phú) là một phần của KH705MTDMN0020 (Bình Dương). Tìm kiểu "có chứa" là
   tiền Bình Dương chạy thẳng vào sổ Tân Phú, sai âm thầm và sai theo một chiều cố định. */
phep( 'mã dài không bị mã ngắn nuốt',
	'Tutu Train - Bình Dương' === khh_dt_doan_co_so( 'NOP TIEN KH705MTDMN0020', '' ) );
phep( 'mã ngắn vẫn nhận đúng quán của nó',
	'TuTu Train - Aeon Tân Phú' === khh_dt_doan_co_so( 'NOP TIEN KH705MTDMN0002', '' ) );
phep( 'mã chưa khai thì KHÔNG đoán bừa', '' === khh_dt_doan_co_so( 'NOP TIEN KH705MTDMN9999', '' ) );

/* Một cơ sở khai nhiều mã — đổi mã giữa chừng thì mã cũ vẫn phải nhận ra. */
khh_dt_dat_ghep_bank( array( 'TuTu Train - Aeon Tân Phú' => 'KH705MTDMN0002, KHCU0001' ) );
phep( 'một cơ sở khai được nhiều mã',
	'TuTu Train - Aeon Tân Phú' === khh_dt_doan_co_so( 'NOP KHCU0001', '' )
	&& 'TuTu Train - Aeon Tân Phú' === khh_dt_doan_co_so( 'NOP KH705MTDMN0002', '' ) );

/* Mã xét trước chữ: nội dung mang cả tên quán lẫn mã của quán KHÁC thì mã thắng. */
khh_dt_dat_ghep_bank(
	array(
		array( 'khoa' => 'KH705MTDMN0023', 'cua_hang' => 'VINCOM BIÊN HÒA' ),
		array( 'khoa' => 'tutu tan phu', 'cua_hang' => 'TuTu Train - Aeon Tân Phú' ),
	)
);
phep( 'mã được xét trước mẩu chữ',
	'VINCOM BIÊN HÒA' === khh_dt_doan_co_so( 'TUTU TAN PHU NOP HO KH705MTDMN0023', '' ) );

/* 🔴 KHOÁ DÀI THẮNG KHOÁ NGẮN — "TUTU TAN AN" không được rơi vào "TUTU TAN PHU" và ngược lại. */
khh_dt_dat_ghep_bank(
	array(
		array( 'khoa' => 'TUTU TAN', 'cua_hang' => 'SAI - khoá ngắn' ),
		array( 'khoa' => 'TUTU TAN PHU', 'cua_hang' => 'TuTu Train - Aeon Tân Phú' ),
	)
);
phep( 'khoá dài được xét trước khoá ngắn',
	'TuTu Train - Aeon Tân Phú' === khh_dt_doan_co_so( 'TUTU TAN PHU NOP TIEN', '' ) );
phep( 'nhận mặt không phân biệt dấu và hoa thường',
	'TuTu Train - Aeon Tân Phú' === khh_dt_doan_co_so( 'Tutu Tân Phú nộp tiền', '' ) );
phep( 'nhận mặt được cả theo số tài khoản',
	'' === khh_dt_doan_co_so( 'NOP TIEN MAT', '9999' ) );
khh_dt_dat_ghep_bank( array( array( 'khoa' => '9999', 'cua_hang' => 'FUNZONE CITY VŨNG TÀU' ) ) );
phep( 'khai số tài khoản thì nhận ra theo tài khoản',
	'FUNZONE CITY VŨNG TÀU' === khh_dt_doan_co_so( 'NOP TIEN MAT', '9999' ) );

/* ============================================================ 3. giờ cắt */

update_option( 'khh_dt_gio_cat', 12 );
phep( 'nộp 8h sáng tính cho doanh thu hôm trước', '2026-09-14' === khh_dt_ngay_quy( '2026-09-15', 8 ) );
phep( 'nộp 20h tính cho chính ngày hôm đó', '2026-09-15' === khh_dt_ngay_quy( '2026-09-15', 20 ) );
update_option( 'khh_dt_gio_cat', 6 );
phep( 'đổi giờ cắt thì đổi theo', '2026-09-15' === khh_dt_ngay_quy( '2026-09-15', 8 ) );
update_option( 'khh_dt_gio_cat', 12 );

/* ============================================================ 4. ghi kho, nạp lại không cộng dồn */

dung_bang_sk();
khh_dt_dat_ghep_bank(
	array(
		array( 'khoa' => 'TUTU TAN PHU', 'cua_hang' => 'TuTu Train - Aeon Tân Phú' ),
		array( 'khoa' => 'TUTU BINH DUONG', 'cua_hang' => 'Tutu Train - Bình Dương' ),
		array( 'khoa' => 'TUTU LOTTE GO VAP', 'cua_hang' => 'TuTu Train - Lotte Gò Vấp' ),
	)
);
$kq = doc_csv( $sk1 );
khh_dt_ghi_sao_ke( $kq['dong'] );
phep( 'ghi được vào kho', 4 === khh_dt_co_sao_ke() );

$bank = khh_dt_nop_bank();
$k14  = '2026-09-14|TuTu Train - Aeon Tân Phú';
phep( 'tiền nộp 8h35 ngày 15 quy về doanh thu ngày 14',
	isset( $bank[ $k14 ] ) && 5980000.0 === $bank[ $k14 ]['tien'] );
phep( 'tiền nộp 20h05 ngày 15 nằm ở chính ngày 15',
	isset( $bank['2026-09-15|TuTu Train - Lotte Gò Vấp'] ) );

/* Giờ và ngày nộp thật phải giữ lại — "chưa nộp" với "nộp muộn ba ngày" là hai chuyện khác nhau. */
phep( 'giữ lại giờ nộp thật', 8 === $bank[ $k14 ]['gio_dau'] );
phep( 'giữ lại ngày nộp thật (khác ngày doanh thu)', '2026-09-15' === $bank[ $k14 ]['ngay_nop'] );
phep( 'đếm số lần nộp', 1 === $bank[ $k14 ]['so_lan'] );

/* 🔴 NẠP LẠI CÙNG MỘT FILE KHÔNG ĐƯỢC CỘNG DỒN. */
$kq = doc_csv( $sk1 );
khh_dt_ghi_sao_ke( $kq['dong'] );
phep( 'nạp lại cùng file không đẻ thêm dòng', 4 === khh_dt_co_sao_ke() );
$bank = khh_dt_nop_bank();
phep( 'và không nhân đôi tiền', 5980000.0 === $bank[ $k14 ]['tien'] );

/* 🔴 ĐẾN HẠN NỘP CHƯA — đừng gọi tên người ta khi họ chưa tới hạn.
   Giờ cắt 12h: tiền bán ngày N phải về trước 12h ngày N+1. */
update_option( 'khh_dt_gio_cat', 12 );
$hom_nay = current_time( 'Y-m-d' );
phep( 'tiền bán hôm nay chưa tới hạn nộp', ! khh_dt_qua_han_nop( $hom_nay ) );
phep( 'tiền bán hôm qua cũng chưa chắc quá hạn trước giờ cắt',
	is_bool( khh_dt_qua_han_nop( gmdate( 'Y-m-d', strtotime( $hom_nay . ' -1 day' ) ) ) ) );
phep( 'tiền bán tuần trước thì quá hạn rõ ràng',
	khh_dt_qua_han_nop( gmdate( 'Y-m-d', strtotime( $hom_nay . ' -7 day' ) ) ) );

$chua = khh_dt_sk_chua_gan();
phep( 'đếm đúng số khoản chưa nhận ra cơ sở', 1 === $chua['so_dong'] );
phep( 'và đếm đúng số tiền đang treo', 3320000.0 === $chua['so_tien'] );

/* Khai thêm một khoá rồi gán lại — khoản đang treo phải về đúng cơ sở, KHÔNG cần nạp lại file. */
khh_dt_dat_ghep_bank(
	array(
		array( 'khoa' => 'TUTU TAN PHU', 'cua_hang' => 'TuTu Train - Aeon Tân Phú' ),
		array( 'khoa' => 'TUTU BINH DUONG', 'cua_hang' => 'Tutu Train - Bình Dương' ),
		array( 'khoa' => 'TUTU LOTTE GO VAP', 'cua_hang' => 'TuTu Train - Lotte Gò Vấp' ),
		array( 'khoa' => 'CHUYEN KHOAN KHONG RO', 'cua_hang' => 'FUNZONE CITY VŨNG TÀU' ),
	)
);
$doi = khh_dt_gan_lai_sao_ke();
phep( 'khai thêm khoá thì gán lại được dòng cũ', 1 === $doi );
phep( 'không còn khoản nào treo', 0 === khh_dt_sk_chua_gan()['so_dong'] );

/* ============================================================ 5. file chỉ có một cột Số tiền */

$sk2 = "Ngay,So tien,Noi dung,Ma tham chieu\n"
	. "15/09/2026 09:00,509000,COFFE GO AN LAC NOP,ABC1\n"
	. "15/09/2026 10:00,-250000,PHI DICH VU,ABC2\n";
$kq = doc_csv( $sk2 );
phep( 'file một cột tiền: lấy số dương', 1 === count( $kq['dong'] ) );
phep( 'file một cột tiền: số âm là tiền ra', 1 === (int) $kq['tien_ra'] );
phep( 'và báo lại là đã phải đoán chiều tiền', $kq['doan_dau'] > 0 );

/* Sao kê không có mã giao dịch -> tự đúc mã, nạp lại vẫn không cộng dồn. */
$sk3 = "Ngay giao dich,So tien,Noi dung\n15/09/2026 09:00,509000,COFFE GO AN LAC NOP\n";
dung_bang_sk();
khh_dt_ghi_sao_ke( doc_csv( $sk3 )['dong'] );
khh_dt_ghi_sao_ke( doc_csv( $sk3 )['dong'] );
phep( 'sao kê không có mã giao dịch vẫn không cộng dồn', 1 === khh_dt_co_sao_ke() );

/* ============================================================ 5b. TIỀN CỔNG QR KHÔNG PHẢI TIỀN NỘP
   Chính màn sao kê nhà mình đang cảnh báo: "Tổng tiền vào ĐÃ BAO GỒM tiền cổng QR… Đừng cộng hai
   chỗ lại". Với đối soát nộp tiền thì nặng hơn: tiền cổng QR là KHÁCH trả, tự về tài khoản,
   không ai phải mang đi nộp. */
phep( 'nhận ra tiền cổng Việt QR', khh_dt_la_cong_qr( 'VQR CHUYEN TIEN', '' ) );
phep( 'nhận ra tiền MoMo', khh_dt_la_cong_qr( 'THANH TOAN MOMO', '' ) );
phep( 'nhận ra tiền VNPAY kể cả khi nằm ở cột nguồn', khh_dt_la_cong_qr( '', 'vnpay' ) );
phep( 'tiền nhân viên nộp thì KHÔNG bị coi là cổng QR',
	! khh_dt_la_cong_qr( 'NHAN TU 18865471 TRACE 164325 ND KH989KVCMN0001-110926-01:22:01', 'sepay' ) );

/* Dòng sao kê THẬT của anh Thắng: mã dính liền ngày giờ bằng dấu gạch. */
khh_dt_dat_ghep_bank( array( 'TuTu Train - Lotte Gò Vấp' => 'KH989KVCMN0001' ) );
phep( 'mã dính liền "-110926-01:22:01" vẫn nhận ra',
	'TuTu Train - Lotte Gò Vấp' === khh_dt_doan_co_so(
		'NHAN TU 18865471 TRACE 164325 ND KH989KVCMN0001-110926-01:22:01 6254ASCB02UMWQY3', '' ) );
phep( 'và mách đúng mã ấy, không mách cụm mã ngân hàng phía sau',
	'KH989KVCMN0001' === khh_dt_ma_trong_nd(
		'NHAN TU 18865471 TRACE 164325 ND KH989KVCMN0001-110926-01:22:01 6254ASCB02UMWQY3' ) );

/* ============================================================ 5c. GHÉP TÊN GỌN VỚI TÊN DÀI
   Sổ mã bên plugin Sao Kê viết gọn ("TÀU GÒ VẤP"), máy POS viết dài
   ("TuTu Train - Lotte Gò Vấp ( Dịch vụ K&H )"). Máy chỉ ĐỀ NGHỊ, người khai gật rồi mới ghi —
   nhưng đề nghị sai nhiều quá thì người ta bấm bừa cho xong, nên phải đo. */
$GLOBALS['KHH_DT_TEST_CH'] = array(
	'TuTu Train - Lotte Gò Vấp ( Dịch vụ K&H )',
	'TuTu Train - Aeon Tân Phú ( Dịch Vụ K&H )',
	'Tutu Train - Aeon Bình Tân ( Dịch Vụ và Giải Trí K&H )',
	'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )',
	'VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )',
	'ECO FARM LOTTE PHAN THIẾT ( Dịch Vụ K&H )',
);
phep( 'ghép "TÀU GÒ VẤP" vào đúng quán Lotte Gò Vấp',
	'TuTu Train - Lotte Gò Vấp ( Dịch vụ K&H )' === khh_dt_ghep_ten_gan( 'TÀU GÒ VẤP' )['ten'] );
phep( 'ghép "TÀU TÂN PHÚ" vào Aeon Tân Phú',
	'TuTu Train - Aeon Tân Phú ( Dịch Vụ K&H )' === khh_dt_ghep_ten_gan( 'TÀU TÂN PHÚ' )['ten'] );
phep( 'ghép "VR SC Vivo Q7" vào VR FUN - SC Vivo Q7',
	'VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )' === khh_dt_ghep_ten_gan( 'VR SC Vivo Q7' )['ten'] );
/* 🔴 KHÔNG ĐOÁN BỪA. Tên lạ hoàn toàn thì điểm phải thấp để màn để trống cho người chọn tay —
   gợi ý sai mà trông chắc chắn thì người ta bấm lưu cho nhanh, và tiền vào nhầm sổ. */
phep( 'tên lạ hoàn toàn thì điểm thấp', khh_dt_ghep_ten_gan( 'CGV LANDMARK 81' )['diem'] < 0.6 );

/* ============================================================ 5d. DÒ CỘT CỦA SỔ THẬT
   Hai sổ thật trên site anh Thắng (16/09/2026):
     · wpt9_saoke_gd   — ID, SEPAY_ID, NGAY_GD, SO_TK, NGAN_HANG, LOAI(in), TIEN, LUY_KE,
                         NOI_DUNG, MA_GD, NHAN, NGUON(api), TAO_LUC   <- sao kê ngân hàng
     · wpt9_saoke_cong — ID, NGUON(vietqr), KHOA, MA_GD, REF, THOI_DIEM, SO_TIEN, HUONG(Đến),
                         TRANG_THAI, SO_TK, NOI_DUNG, DIEM_BAN, …     <- cổng QR
   Dò hụt một cột là người khai phải chỉ tay; dò NHẦM một cột thì tệ hơn nhiều. */
$cot_gd = array( 'ID', 'SEPAY_ID', 'NGAY_GD', 'SO_TK', 'NGAN_HANG', 'LOAI', 'TIEN', 'LUY_KE',
	'NOI_DUNG', 'MA_GD', 'NHAN', 'NGUON', 'TAO_LUC' );
$m = khh_dt_doan_cot( $cot_gd );
phep( 'sổ ngân hàng: đọc đúng cột ngày', 'NGAY_GD' === $m['ngay'] );
phep( 'sổ ngân hàng: đọc đúng cột tiền', 'TIEN' === $m['so_tien'] );
phep( 'sổ ngân hàng: đọc đúng cột nội dung', 'NOI_DUNG' === $m['noi_dung'] );
phep( 'sổ ngân hàng: đọc đúng cột mã giao dịch', 'MA_GD' === $m['ma_gd'] );
phep( 'sổ ngân hàng: đọc đúng cột nhãn', 'NHAN' === $m['nhan'] );
/* 🔴 `LOAI` = in/out là CHIỀU tiền. Nhận nhầm nó thành "nguồn" là cột chiều bỏ trống, và tiền ĐI
   cũng được cộng vào phần đã nộp. */
phep( 'sổ ngân hàng: LOAI là CHIỀU tiền, không phải nguồn', 'LOAI' === $m['huong'] );
phep( 'sổ ngân hàng: NGUON vẫn là nguồn', 'NGUON' === $m['nguon'] );
/* `LUY_KE` là số dư cộng dồn — tuyệt đối không được nhận nhầm thành số tiền của giao dịch. */
phep( 'sổ ngân hàng: không nhận nhầm LUY_KE thành số tiền', 'LUY_KE' !== $m['so_tien'] );

$cot_cong = array( 'ID', 'NGUON', 'KHOA', 'MA_GD', 'REF', 'THOI_DIEM', 'SO_TIEN', 'HUONG',
	'TRANG_THAI', 'SO_TK', 'NOI_DUNG', 'DIEM_BAN', 'DOC_DUOC', 'RAW', 'NHAN_LUC', 'MA_CH', 'MAY_TAY' );
$c = khh_dt_doan_cot( $cot_cong );
phep( 'sổ cổng QR: đọc đúng cột thời điểm', 'THOI_DIEM' === $c['ngay'] );
phep( 'sổ cổng QR: đọc đúng cột số tiền', 'SO_TIEN' === $c['so_tien'] );
phep( 'sổ cổng QR: đọc đúng cột hướng', 'HUONG' === $c['huong'] );
phep( 'sổ cổng QR: đọc đúng cột trạng thái', 'TRANG_THAI' === $c['trang_thai'] );

/* Sổ MoMo đã gộp theo ngày của anh Thắng — `wpt9_saoke_congfile`, 188 dòng, mỗi dòng là một ngày
   của một quán, dựng từ chính mấy file Transaction_report_….csv nạp vào plugin Sao Kê.

   🔴 HAI CÁI PHẢI ĐÚNG Ở ĐÂY. Một: nhận ra cột tên cửa hàng, không thì màn khai bắt "phải chỉ cột
      Tên cửa hàng" trong khi cột ấy nằm ngay trước mặt (đã xảy ra 16/09/2026). Hai: nhận ra cột
      ĐẾM giao dịch — nó là dấu hiệu sổ đã gộp, mà sổ gộp thì đối soát từng giao dịch sẽ so 188
      dòng với mấy nghìn giao dịch và kể lệch toàn phần. */
$cot_gop = array( 'ID', 'NGUON', 'KHOA', 'THANG', 'NGAY', 'CH_FILE', 'CH_CHUAN', 'MA_BANK',
	'SO_TIEN', 'SO_DONG', 'TEN_FILE', 'TAI_LUC' );
$g = khh_dt_doan_cot( $cot_gop );
phep( 'sổ MoMo gộp: đọc đúng cột ngày', 'NGAY' === $g['ngay'] );
phep( 'sổ MoMo gộp: đọc đúng cột số tiền', 'SO_TIEN' === $g['so_tien'] );
phep( 'sổ MoMo gộp: nhận ra cột tên cửa hàng', 'CH_CHUAN' === $g['nhan'] );
phep( 'sổ MoMo gộp: nhận ra cột đếm giao dịch', 'SO_DONG' === $g['dem'] );
phep( 'sổ MoMo gộp: không nhận nhầm cột đếm thành số tiền', 'SO_DONG' !== $g['so_tien'] );
/* Sổ chưa gộp thì cột đếm phải TRỐNG — nếu không, đối soát từng giao dịch bị chặn oan. */
phep( 'sổ cổng QR chưa gộp thì không có cột đếm', '' === $c['dem'] );

/* ============================================================ 6. file không phải sao kê */

$kq = doc_csv( "Ten hang,So luong\nVe nguoi lon,3\n" );
phep( 'file không phải sao kê thì chối, có nói lý do',
	is_wp_error( $kq ) && false !== strpos( $kq->get_error_message(), 'tên cột' ) );

/* ============================================================ kết */

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép:\n";
	foreach ( $hong as $h ) {
		echo "   · $h\n";
	}
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép đọc sao kê ngân hàng\n";
