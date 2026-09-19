<?php
/**
 * MỌI BIỂU MẪU POST TRÊN TRANG ĐỀU PHẢI MANG Ô CHỮ KÝ.
 *
 * =================================================================================================
 * 🔴 MỘT FORM QUÊN Ô `ky` LÀ MỘT CÁI NÚT CHẾT, IM LẶNG, TỪ NGÀY NÓ ĐƯỢC VIẾT RA
 * =================================================================================================
 * Anh Thắng 19/09/2026 bấm **Duyệt** ở khối *"Chờ duyệt trước khi xuống máy chấm công"* và nhận
 * *"Biểu mẫu gửi lên THIẾU chữ ký"*. Không phải phiên hỏng: chính cái `<form>` ấy chưa bao giờ
 * gài ô chữ ký. Mọi lượt POST của hai trang đều đi qua `VHCC_Web::chu_ky_dung()`, nên form nào
 * quên ô ấy thì bấm bao nhiêu lần cũng chỉ ra một câu chối.
 *
 * Thứ làm nó sống lâu là KHÔNG CÓ GÌ KÊU LÊN. Mã chạy, trang vẽ ra đẹp, nút bấm được, chỉ là
 * việc không xảy ra. Người dùng đổ cho "phiên hết hạn" và đi tải lại trang — mãi mãi.
 *
 * ⚠️ BÀI NÀY ĐỌC HTML ĐÃ VẼ, KHÔNG ĐỌC MÃ NGUỒN. Ô chữ ký hay nằm trong một biến (`$an`,
 *    `$o_chung`…) rồi mới nối vào form, nên quét mã nguồn là báo nhầm hàng chục chỗ. Vẽ thật
 *    rồi soi thẻ `<form>` thì hỏi đúng câu cần hỏi: lúc chạy, cái form ấy CÓ ô chữ ký không.
 *
 * Chạy: php tools/test/kiem-form-co-chu-ky.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	echo "ĐẠT (tới lúc chết): $dat\n";
} );
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 500 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}

global $wpdb;

$CS = 'FCK_SHOP';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'FCKAD', 'ho_ten' => 'Chị Kế Toán',
	'cua_hang' => $CS, 'coso_quan' => $CS, 'vai_tro' => 'Kế toán', 'chuc_vu' => 'Partime',
	'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'FCKNV', 'ho_ten' => 'Em Nhân Viên',
	'cua_hang' => $CS, 'vai_tro' => 'Nhân viên', 'chuc_vu' => 'Partime',
	'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS,
	'ngay' => substr( (string) current_time( 'Y-m-d' ), 0, 8 ) . '05', 'ma_nv' => 'FCKNV',
	'ho_ten' => 'Em Nhân Viên', 'gio_vao_giay' => 28800, 'gio_ra_giay' => 61200,
	'hau_to' => '', 'nguon' => 'may' ) );

/* Một lệnh đang chờ đẩy xuống máy — để khối "Chờ duyệt trước khi xuống máy chấm công" vẽ ra.
   🔴 Đúng cái khối anh Thắng bấm mà không ăn. Không gieo dòng nào thì khối ấy không hiện, và
      bài kiểm xanh vì không có gì để soi — kiểu xanh tệ nhất. */
$wpdb->insert( VHCC_DB::t( 'queue' ), array(
	'op_id' => 'op-thu-1', 'ma_nv' => 'FCKNV', 'ho_ten' => 'Em Nhân Viên',
	'cua_hang' => $CS, 'action' => 'add', 'nguoi_dat' => 'Chị Trưởng',
	'tao_luc' => current_time( 'mysql' ), 'trang_thai' => VHCC_MayCong::CHO_DUYET ) );
$co_hang_doi = count( (array) VHCC_May::ds_cho_duyet() ) > 0;

/** Vẽ một màn rồi trả HTML. */
function fck_ve( $lop, $vai, $get ) {
	$_GET = $get; $_POST = array();
	$_COOKIE = array( VHCC_Web::COOKIE => VHCC_Auth::phat_token( 'Chị Kế Toán', $vai, 'FCK_SHOP', 'FCKAD' ) );
	ob_start();
	if ( 'ns' === $lop ) { VHCC_TrangNS::phuc_vu(); } else { VHCC_Web::phuc_vu(); }
	$h = ob_get_clean();
	$_GET = array(); $_POST = array(); $_COOKIE = array();
	return $h;
}

/**
 * Mọi thẻ `<form … method="post" …>` trong một trang, kèm ruột của nó.
 *
 * ⚠️ Không lồng form được trong HTML, nên cắt theo cặp `<form …>` … `</form>` gần nhất là đúng.
 */
function fck_form( $h ) {
	$ra = array();
	if ( ! preg_match_all( '#<form\b[^>]*>.*?</form>#is', $h, $m ) ) { return $ra; }
	foreach ( $m[0] as $f ) {
		if ( ! preg_match( '#<form\b[^>]*method=["\']post["\']#i', $f ) ) { continue; }
		$ra[] = $f;
	}
	return $ra;
}

/** Form này có gửi một `viec` lên không — tức nó có đi qua cửa chữ ký không. */
function fck_co_viec( $f ) {
	return (bool) preg_match( '#name=["\'](viec|cot|xoa_ma|ghep_voi|day_1|vp_day|vp_go|sua_may|xoa_may)["\']#i', $f );
}

$man = array(
	array( 'qt · Bảng công',      'qt', 'Kế toán', array( 'man' => 'cham', 'ccs' => $CS ) ),
	array( 'qt · Đơn từ',         'qt', 'Kế toán', array( 'man' => 'don_tu', 'lcs' => $CS ) ),
	array( 'qt · Bảng lương',     'qt', 'Kế toán', array( 'man' => 'luong', 'lcs' => $CS ) ),
	array( 'qt · Cấu hình',       'qt', 'Admin',   array( 'man' => 'cau_hinh' ) ),
	array( 'qt · Cơ sở',          'qt', 'Admin',   array( 'man' => 'coso' ) ),
	array( 'qt · Đơn duyệt tháng','qt', 'Kế toán', array( 'man' => 'don_tuan' ) ),
	array( 'qt · Hồ sơ',          'qt', 'Admin',   array( 'man' => 'ho_so' ) ),
	array( 'ns · Quản lý nhân sự','ns', 'Kế toán', array() ),
	array( 'ns · Quyền vào trang','ns', 'Kế toán', array( 'tab' => 'quyen' ) ),
);

echo "— mọi form POST đều mang ô chữ ký —\n";
$tong_form = 0;
foreach ( $man as $x ) {
	list( $ten, $lop, $vai, $get ) = $x;
	$h  = fck_ve( $lop, $vai, $get );
	$fs = fck_form( $h );
	$tong_form += count( $fs );
	foreach ( $fs as $i => $f ) {
		if ( ! fck_co_viec( $f ) ) { continue; }   // form lọc/xem, không đi qua cửa chữ ký
		$co = (bool) preg_match( '#name=["\']ky["\']#i', $f );
		t( '🔴 ' . $ten . ' — form #' . ( $i + 1 ) . ' có ô chữ ký',
			$co, $co ? null : preg_replace( '#\s+#', ' ', substr( $f, 0, 420 ) ) );
	}
}
t( 'có vẽ ra form để mà soi — không thì bài này xanh vì rỗng', $tong_form >= 8, $tong_form );

/* 🔴 KHỐI ĐẨY XUỐNG MÁY: soi ĐÍCH DANH, không để nó trốn sau `fck_co_viec`. Đây là cái form đã
   hỏng, và nếu ai đó lỡ xoá dòng gieo hàng đợi ở trên thì phép thử chung bên trên lại xanh. */
echo "— khối đẩy xuống máy chấm công —\n";
/* 🔴 KHÔNG CHO BỎ QUA. Gieo hỏng mà bài vẫn xanh là kiểu xanh tệ nhất — nó khẳng định một
   điều mà nó chưa hề kiểm. Thà đỏ ở đây còn hơn xanh giả. */
t( '🔴 gieo được một lệnh chờ đẩy xuống máy — không thì bài dưới xanh vì rỗng', $co_hang_doi );
if ( ! $co_hang_doi ) {
	echo "  (khối dưới không soi được vì hàng đợi rỗng)\n";
} else {
	$h_ns = fck_ve( 'ns', 'Kế toán', array() );
	t( 'khối "Chờ duyệt trước khi xuống máy chấm công" có vẽ ra',
		false !== mb_strpos( $h_ns, 'xuống máy chấm công' ), mb_substr( $h_ns, 0, 200 ) );
	$co_duyet = false;
	foreach ( fck_form( $h_ns ) as $f ) {
		if ( false === strpos( $f, 'duyet_may' ) ) { continue; }
		$co_duyet = true;
		t( '🔴 form nút Duyệt/Từ chối MANG ô chữ ký',
			(bool) preg_match( '#name=["\']ky["\']#i', $f ),
			preg_replace( '#\s+#', ' ', $f ) );
	}
	t( 'tìm thấy form Duyệt/Từ chối để soi', $co_duyet, $h_ns ? 'có trang' : '' );
}

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — không còn cái nút nào bấm xong im lặng vì quên chữ ký.\n";
