<?php
/**
 * NHẬN BÁO CÁO QUA HỘP THƯ: BÓC ĐÚNG TỆP, CHỐI ĐÚNG NGƯỜI, VÀ ĐỌC ĐÚNG PHẢN HỒI IMAP.
 *
 * 19/09/2026 anh Thắng chốt hướng: FABi gửi báo cáo về một hộp thư riêng, web tự vào lấy mỗi 2
 * tiếng. Bài này khoá bốn chỗ mà `php -l` xanh nhưng chạy thật thì hỏng câm:
 *
 *   1. 🔴 DANH SÁCH NGƯỜI GỬI RỖNG PHẢI LÀ CHỐI TẤT, KHÔNG PHẢI NHẬN TẤT. Hộp thư nào cũng
 *      nhận được thư rác; một tệp .csv từ người lạ đi thẳng vào kho doanh thu thì không ai
 *      nhìn ra ngay, mà số thì đã sai rồi.
 *   2. 🔴 ĐẦU THƯ GẤP DÒNG. `Content-Disposition: attachment;` rồi `filename=` nằm ở dòng sau
 *      thụt vào — không nối lại thì tệp thành KHÔNG TÊN rồi bị bỏ qua, im lặng.
 *   3. 🔴 TÊN TỆP TIẾNG VIỆT. `=?UTF-8?B?…?=` (RFC 2047) và `filename*=UTF-8''…` (RFC 2231).
 *      Không gỡ là tên về nguyên cục mã hoá rồi trượt phép khớp mẫu — "hệ không thấy thư nào".
 *   4. 🔴 LITERAL CỦA IMAP. Phản hồi dài có `{N}` ở cuối dòng rồi N byte THÔ theo sau, trong đó
 *      có cả xuống dòng và cả chuỗi trông y như dòng lệnh. Đọc từng dòng là nội dung thư bị
 *      hiểu nhầm thành phản hồi, rồi mọi lệnh sau lệch nhau một nhịp.
 *
 * Phần IMAP thử bằng một CẶP SOCKET THẬT (`stream_socket_pair`), diễn lại nguyên lượt đối đáp —
 * không phải dò chuỗi trong mã.
 *
 * Chạy: php tools/test/kiem-hop-thu.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/hop-thu.php';

$dat = 0; $hong = array();
function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) { $dat++; } else { $hong[] = $ten; }
}

/* ── 1. người gửi ───────────────────────────────────────────────────────────────────── */
phep( '🔴 danh sách rỗng là CHỐI TẤT, không phải nhận tất',
	! khh_dt_thu_nguoi_gui_hop_le( 'FABi <bao-cao@fabi.vn>', '' ) );
phep( 'đúng địa chỉ thì nhận',
	khh_dt_thu_nguoi_gui_hop_le( 'FABi <bao-cao@fabi.vn>', 'bao-cao@fabi.vn' ) );
phep( 'địa chỉ trần (không có tên) cũng nhận',
	khh_dt_thu_nguoi_gui_hop_le( 'bao-cao@fabi.vn', 'bao-cao@fabi.vn' ) );
phep( 'không phân biệt hoa thường',
	khh_dt_thu_nguoi_gui_hop_le( 'Bao-Cao@FABI.VN', 'bao-cao@fabi.vn' ) );
phep( 'cả tên miền: "@fabi.vn"',
	khh_dt_thu_nguoi_gui_hop_le( 'ai-do@fabi.vn', '@fabi.vn' ) );
phep( '🔴 "@fabi.vn" KHÔNG nhận "fabi.vn.ke-gian.com"',
	! khh_dt_thu_nguoi_gui_hop_le( 'x@fabi.vn.ke-gian.com', '@fabi.vn' ) );
phep( 'người lạ thì chối',
	! khh_dt_thu_nguoi_gui_hop_le( 'ai-do@gmail.com', 'bao-cao@fabi.vn' ) );
phep( 'nhiều người gửi, cách nhau dấu phẩy',
	khh_dt_thu_nguoi_gui_hop_le( 'b@x.vn', 'a@x.vn, b@x.vn' ) );

/* ── 2. tên tệp và đuôi tệp ─────────────────────────────────────────────────────────── */
phep( 'mẫu "*.xlsx" khớp', khh_dt_thu_ten_hop_le( 'bao-cao-19-09.xlsx', '*.xlsx' ) );
phep( 'mẫu "*.xlsx" không khớp .pdf', ! khh_dt_thu_ten_hop_le( 'hoa-don.pdf', '*.xlsx' ) );
phep( 'nhiều mẫu', khh_dt_thu_ten_hop_le( 'a.csv', '*.xlsx, *.csv' ) );
phep( 'mẫu có tiền tố', khh_dt_thu_ten_hop_le( 'fabi-19.csv', 'fabi-*.csv' ) );
phep( 'mẫu có tiền tố, tên khác thì trượt', ! khh_dt_thu_ten_hop_le( 'momo-19.csv', 'fabi-*.csv' ) );
phep( 'không đặt mẫu thì tên nào cũng qua', khh_dt_thu_ten_hop_le( 'gi-cung-duoc.csv', '' ) );
phep( '🔴 nhưng đuôi tệp vẫn gác: .exe bị chối', ! khh_dt_thu_duoi_hop_le( 'virus.exe' ) );
phep( '.php cũng bị chối', ! khh_dt_thu_duoi_hop_le( 'shell.php' ) );
phep( '.xlsx thì nhận', khh_dt_thu_duoi_hop_le( 'a.xlsx' ) );
phep( '.csv thì nhận', khh_dt_thu_duoi_hop_le( 'a.csv' ) );

/* ── 3. gỡ mã hoá đầu thư ───────────────────────────────────────────────────────────── */
$b64 = '=?UTF-8?B?' . base64_encode( 'báo cáo.xlsx' ) . '?=';
phep( '🔴 gỡ được tên tệp tiếng Việt kiểu Base64', 'báo cáo.xlsx' === khh_dt_thu_go_2047( $b64 ) );
phep( 'gỡ được kiểu Quoted-Printable',
	'báo cáo.xlsx' === khh_dt_thu_go_2047( '=?UTF-8?Q?b=C3=A1o_c=C3=A1o.xlsx?=' ) );
phep( 'chuỗi thường thì để nguyên', 'a.xlsx' === khh_dt_thu_go_2047( 'a.xlsx' ) );

/* ── 4. bóc tệp đính kèm ────────────────────────────────────────────────────────────── */
function thu_mau( $phan ) {
	return "From: FABi <bao-cao@fabi.vn>\r\n"
		. "Subject: Bao cao ban hang\r\n"
		. "Message-ID: <abc\x40fabi.vn>\r\n"
		. "MIME-Version: 1.0\r\n"
		. "Content-Type: multipart/mixed; boundary=\"VACH\"\r\n"
		. "\r\n"
		. "Phan dan, khong phai mot phan MIME.\r\n"
		. "--VACH\r\n"
		. "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
		. "Chao anh, bao cao dinh kem.\r\n"
		. $phan
		. "--VACH--\r\n";
}
$noi_that = "ngay,cua_hang,tien\r\n2026-09-19,Tutu Train,1000\r\n";

/* (a) đính kèm base64, tên thường */
$thu = thu_mau(
	"--VACH\r\n"
	. "Content-Type: text/csv; name=\"bao-cao.csv\"\r\n"
	. "Content-Transfer-Encoding: base64\r\n"
	. "Content-Disposition: attachment; filename=\"bao-cao.csv\"\r\n\r\n"
	. chunk_split( base64_encode( $noi_that ), 76, "\r\n" )
);
$dk = khh_dt_thu_dinh_kem( $thu );
phep( 'bóc được đúng MỘT tệp', 1 === count( $dk ) );
phep( 'đúng tên tệp', $dk && 'bao-cao.csv' === $dk[0]['ten'] );
phep( '🔴 nội dung giải mã base64 khớp từng byte', $dk && $noi_that === $dk[0]['noi'] );
phep( 'phần lời dẫn KHÔNG bị coi là tệp', 1 === count( $dk ) );

/* (b) đầu thư GẤP DÒNG — filename nằm ở dòng sau, thụt vào */
$thu = thu_mau(
	"--VACH\r\n"
	. "Content-Type: application/octet-stream\r\n"
	. "Content-Transfer-Encoding: base64\r\n"
	. "Content-Disposition: attachment;\r\n\tfilename=\"gap-dong.csv\"\r\n\r\n"
	. base64_encode( $noi_that ) . "\r\n"
);
$dk = khh_dt_thu_dinh_kem( $thu );
phep( '🔴 đầu thư gấp dòng vẫn ra đúng tên tệp', $dk && 'gap-dong.csv' === $dk[0]['ten'] );

/* (c) tên tệp tiếng Việt, cả hai lối mã hoá */
$thu = thu_mau(
	"--VACH\r\n"
	. "Content-Type: text/csv; name=\"" . $b64 . "\"\r\n"
	. "Content-Transfer-Encoding: base64\r\n\r\n"
	. base64_encode( $noi_that ) . "\r\n"
);
$dk = khh_dt_thu_dinh_kem( $thu );
phep( '🔴 tên tệp RFC 2047 gỡ đúng', $dk && 'báo cáo.xlsx' === $dk[0]['ten'] );

$thu = thu_mau(
	"--VACH\r\n"
	. "Content-Type: text/csv\r\n"
	. "Content-Transfer-Encoding: base64\r\n"
	. "Content-Disposition: attachment; filename*=UTF-8''b%C3%A1o%20c%C3%A1o.csv\r\n\r\n"
	. base64_encode( $noi_that ) . "\r\n"
);
$dk = khh_dt_thu_dinh_kem( $thu );
phep( '🔴 tên tệp RFC 2231 gỡ đúng', $dk && 'báo cáo.csv' === $dk[0]['ten'] );

/* (d) thư KHÔNG có đính kèm */
$thu = "From: a@b.vn\r\nContent-Type: text/plain\r\n\r\nChi co chu thoi.\r\n";
phep( 'thư không đính kèm thì ra mảng rỗng', array() === khh_dt_thu_dinh_kem( $thu ) );

/* (e) multipart LỒNG NHAU — thật sự hay gặp: multipart/mixed bọc multipart/alternative */
$trong = "Content-Type: multipart/alternative; boundary=\"TRONG\"\r\n\r\n"
	. "--TRONG\r\nContent-Type: text/plain\r\n\r\nchu\r\n"
	. "--TRONG\r\nContent-Type: text/html\r\n\r\n<p>chu</p>\r\n"
	. "--TRONG--\r\n";
$thu = thu_mau(
	"--VACH\r\n" . $trong
	. "--VACH\r\n"
	. "Content-Type: text/csv\r\n"
	. "Content-Transfer-Encoding: base64\r\n"
	. "Content-Disposition: attachment; filename=\"long.csv\"\r\n\r\n"
	. base64_encode( $noi_that ) . "\r\n"
);
$dk = khh_dt_thu_dinh_kem( $thu );
phep( '🔴 multipart lồng nhau: vẫn đúng một tệp, không nhặt nhầm phần chữ', 1 === count( $dk ) );
phep( 'và đúng tệp ấy', $dk && 'long.csv' === $dk[0]['ten'] && $noi_that === $dk[0]['noi'] );

/* ── 5. đối đáp IMAP thật, trên một cặp socket ──────────────────────────────────────── */
class Imap_Thu extends KHHDT_Imap {
	public function gan( $s ) { $this->s = $s; }
}
$cap = @stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0 );
if ( ! $cap ) {
	echo "BỎ QUA phần IMAP: máy này không tạo được cặp socket.\n";
} else {
	list( $khach, $may_chu ) = $cap;
	stream_set_timeout( $khach, 2 );

	/* 🔴 CA CHÍNH. Thân thư về dưới dạng literal, và bên trong nó có một dòng trông y hệt dòng
	   phản hồi có thẻ ("a1 OK ..."). Đọc từng dòng là dừng ngay ở đó, trả về thân thư CỤT —
	   rồi phép bóc tệp ra rỗng mà không có gì báo. */
	$than = "From: FABi <bao-cao@fabi.vn>\r\n\r\n"
		. "a1 OK dong nay la BAY, nam trong noi dung thu\r\n"
		. ") cung la bay\r\n"
		. $noi_that;
	$pha  = '* 1 FETCH (UID 7 BODY[] {' . strlen( $than ) . "}\r\n" . $than . ")\r\n"
		. "a1 OK FETCH completed\r\n";
	fwrite( $may_chu, $pha );

	$im = new Imap_Thu();
	$im->gan( $khach );
	$ra = $im->ca_thu( 7 );
	phep( '🔴 đọc literal ra ĐÚNG số byte, không cụt ở dòng trông như phản hồi', $than === $ra );
	$gui = fread( $may_chu, 4096 );
	phep( 'gửi đúng lệnh UID FETCH … BODY.PEEK[]',
		false !== strpos( $gui, 'UID FETCH 7 (BODY.PEEK[])' ) );
	phep( '🔴 dùng PEEK, KHÔNG phải BODY[] — lấy thư về chưa được tính là đã đọc',
		false === strpos( $gui, ' (BODY[])' ) );

	/* Tìm thư chưa đọc */
	fwrite( $may_chu, "* SEARCH 3 7 11\r\na2 OK SEARCH completed\r\n" );
	$uids = $im->chua_doc();
	phep( 'đọc đúng danh sách UID chưa đọc', array( 3, 7, 11 ) === $uids );
	$gui = fread( $may_chu, 4096 );
	phep( 'tìm bằng UID SEARCH UNSEEN', false !== strpos( $gui, 'UID SEARCH UNSEEN' ) );

	/* Máy chủ trả NO thì phải coi là hỏng, không phải "không có thư nào" */
	fwrite( $may_chu, "a3 NO mailbox not selected\r\n" );
	phep( '🔴 máy chủ trả NO thì KHÔNG coi là rỗng một cách im lặng', array() === $im->chua_doc() );
	phep( 'và có nói ra lỗi', '' !== $im->loi() );

	/* Đăng nhập sai: không được đưa phản hồi máy chủ vào lời báo (có máy chủ nhắc lại cả lệnh,
	   mà lệnh ấy có mật khẩu). */
	fwrite( $may_chu, "a4 NO LOGIN \"ai@do.vn\" \"mat-khau-that\" failed\r\n" );
	$im->dang_nhap( 'ai@do.vn', 'mat-khau-that' );
	phep( '🔴 lời báo đăng nhập hỏng KHÔNG chứa mật khẩu',
		false === strpos( $im->loi(), 'mat-khau-that' ) );

	fclose( $khach );
	fclose( $may_chu );
}

/* ── 6. nhịp chạy phải bị kẹp lại ───────────────────────────────────────────────────── */
$ds = khh_dt_thu_nhip( array() );
phep( 'có đăng ký một nhịp chạy riêng', isset( $ds[ KHH_DT_THU_NHIP ]['interval'] ) );
phep( 'mặc định 2 tiếng', 7200 === (int) $ds[ KHH_DT_THU_NHIP ]['interval'] );
update_option( KHH_DT_THU_OPT, array( 'gio' => 0 ) );
$ds = khh_dt_thu_nhip( array() );
phep( '🔴 gõ nhầm 0 giờ thì kẹp lên 1, không thành lịch chạy liên tục',
	3600 === (int) $ds[ KHH_DT_THU_NHIP ]['interval'] );
update_option( KHH_DT_THU_OPT, array( 'gio' => 999 ) );
$ds = khh_dt_thu_nhip( array() );
phep( 'và kẹp trên ở 24 giờ', 86400 === (int) $ds[ KHH_DT_THU_NHIP ]['interval'] );

/* ── 7. chưa đủ cấu hình thì nói ra, đừng đi nối ─────────────────────────────────────── */
update_option( KHH_DT_THU_OPT, array( 'may' => '', 'nguoi' => '', 'mat_khau' => '' ) );
phep( 'thiếu cấu hình thì biết là thiếu', ! khh_dt_thu_du_cau_hinh() );
$kq = khh_dt_thu_lay();
phep( '🔴 thiếu cấu hình thì báo lỗi rõ, không im lặng', empty( $kq['xong'] ) && ! empty( $kq['loi'] ) );
phep( 'và có ghi vào nhật ký', count( khh_dt_thu_nhat_ky() ) > 0 );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: bóc đúng tệp đính kèm, chối đúng người gửi, đọc đúng literal IMAP.\n";
