<?php
/**
 * Kiểm chi phí, đối soát chi phí và sao lưu — chạy thật trên bộ giả lập.
 *
 *   php tools/kh-tai-chinh/tests/kiem-chi-phi.php
 */

error_reporting( E_ALL );
set_error_handler(
	function ( $n, $s, $f, $l ) {
		throw new ErrorException( $s, 0, $n, $f, $l );
	}
);

require __DIR__ . '/gia-lap-wp.php';

$dat  = 0;
$hong = array();

function kiem( $ten, $that, $mong ) {
	global $dat, $hong;
	if ( $that === $mong ) { $dat++; return; }
	$hong[] = sprintf( '%s: nhận %s, mong %s', $ten, var_export( $that, true ), var_export( $mong, true ) );
}
function co( $ten, $html, $chuoi ) {
	global $dat, $hong;
	if ( false !== strpos( $html, $chuoi ) ) { $dat++; return; }
	$hong[] = sprintf( '%s: không thấy "%s" trong trang', $ten, $chuoi );
}
function dung( $ham ) {
	ob_start();
	try { $ham(); } catch ( Throwable $e ) { ob_end_clean(); throw $e; }
	return ob_get_clean();
}

KHTC_Cty::chon( 'kh_cu' );
$nh = KHTC_NganHang::them( array( 'ten' => 'Vietcombank 0123', 'so_du_dau' => 200000000, 'ngay_dau' => '2026-08-01' ) );

// ------------------------------------------------------------- danh mục
kiem( 'danh mục bộ phận mặc định có Khu vui chơi', in_array( 'Khu vui chơi', KHTC_ChiPhi::danh_muc( 'bo_phan' ), true ), true );
kiem( 'lưu danh mục bỏ dòng trống và dòng trùng', KHTC_ChiPhi::luu_danh_muc( 'bo_phan', "Khu vui chơi\n\nVăn phòng\nKhu vui chơi\n  MTĐ  " ), 3 );
kiem( 'danh mục đã lưu đọc lại đúng', KHTC_ChiPhi::danh_muc( 'bo_phan' ), array( 'Khu vui chơi', 'Văn phòng', 'MTĐ' ) );
kiem( 'danh mục rỗng bị từ chối', is_wp_error( KHTC_ChiPhi::luu_danh_muc( 'bo_phan', "\n \n" ) ), true );
kiem( 'bị từ chối thì danh mục cũ còn nguyên', KHTC_ChiPhi::danh_muc( 'bo_phan' ), array( 'Khu vui chơi', 'Văn phòng', 'MTĐ' ) );

// Danh mục tách theo pháp nhân: sửa ở KH Cũ không đụng KH Mới.
KHTC_Cty::chon( 'kh_moi' );
kiem( 'pháp nhân khác vẫn dùng danh mục mặc định', KHTC_ChiPhi::danh_muc( 'bo_phan' ), KHTC_ChiPhi::mac_dinh( 'bo_phan' ) );
KHTC_Cty::chon( 'kh_cu' );

// ------------------------------------------------------------- ghi chi phí
kiem( 'thiếu bộ phận thì từ chối', is_wp_error( KHTC_ChiPhi::them( array( 'ngay' => '10/08/2026', 'so_tien' => '100.000' ) ) ), true );
kiem( 'số tiền 0 thì từ chối', is_wp_error( KHTC_ChiPhi::them( array( 'ngay' => '10/08/2026', 'bo_phan' => 'Văn phòng', 'so_tien' => '0' ) ) ), true );
kiem( 'ngày sai thì từ chối', is_wp_error( KHTC_ChiPhi::them( array( 'ngay' => 'hôm qua', 'bo_phan' => 'Văn phòng', 'so_tien' => '100.000' ) ) ), true );

// Số âm vẫn hiểu là một khoản chi — chi phí không có khái niệm âm.
$id = KHTC_ChiPhi::them( array( 'ngay' => '10/08/2026', 'bo_phan' => 'Văn phòng', 'khoan_muc' => 'Khác', 'so_tien' => '-500.000' ) );
kiem( 'số âm đọc thành số dương', (int) KHTC_ChiPhi::loc( array( 'tu' => '2026-08-10', 'den' => '2026-08-10' ) )['tong'], 500000 );
KHTC_ChiPhi::xoa( $id );

$bang = "10/08/2026\tKhu vui chơi\tTiền điện\tEVN HCMC\t2.400.000\tHD00123\tDien thang 7\n"
	. "12/08/2026\tKhu vui chơi\tThuê mặt bằng\tCTY BDS An Phu\t35.000.000\tHD00124\n"
	. "12/08/2026\tVăn phòng\tVật tư — tiêu hao\tVP Hong Ha\t780.000\n"
	. "15/08/2026\tVăn phòng\tTiền điện\tEVN HCMC\t1.100.000\tHD00125\n"
	. "18/08/2026\tMTĐ\tVận chuyển\tGHTK\t450.000\n";
$kq = KHTC_ChiPhi::dan_hang_loat( $bang, $nh, 'chuyen_khoan' );
kiem( 'nạp 5 khoản chi', $kq['them'], 5 );
kiem( 'nạp không lỗi dòng nào', $kq['loi'], array() );

// Dán bằng DẤU PHẨY đi qua str_getcsv, đường mà PHP 8.4 bắt phải truyền rõ
// tham số escape. Thiếu nó là mọi ô dán bảng trong plugin kêu deprecation.
$phay = KHTC_ChiPhi::dan_hang_loat( "11/08/2026,Văn phòng,Khác,NCC ABC,250.000,CT99,ghi chu", 0 );
kiem( 'dán bằng dấu phẩy chạy được', array( $phay['them'], $phay['loi'] ), array( 1, array() ) );
$co_phay = KHTC_ChiPhi::loc( array( 'tu' => '2026-08-11', 'den' => '2026-08-11' ) );
kiem( 'và đọc đúng số tiền', $co_phay['tong'], 250000 );

$thieu_cot = KHTC_ChiPhi::dan_hang_loat( "10/08/2026\tVăn phòng\tTiền điện", 0 );
kiem( 'dòng thiếu cột bị báo lỗi', array( $thieu_cot['them'], count( $thieu_cot['loi'] ) ), array( 0, 1 ) );

$ky = array( 'tu' => '2026-08-01', 'den' => '2026-08-31' );
$l  = KHTC_ChiPhi::loc( $ky );
kiem( 'tổng chi phí tháng 8', $l['tong'], 39980000 );
kiem( 'chưa đối soát thì chưa thấy tiền ra đồng nào', $l['da_tra'], 0 );
kiem( 'lọc theo bộ phận', KHTC_ChiPhi::loc( $ky + array( 'bo_phan' => 'Văn phòng' ) )['tong'], 2130000 );
kiem( 'lọc theo khoản mục', KHTC_ChiPhi::loc( $ky + array( 'khoan_muc' => 'Tiền điện' ) )['tong'], 3500000 );
kiem( 'tìm theo nhà cung cấp', KHTC_ChiPhi::loc( $ky + array( 'tim' => 'EVN' ) )['so_dong'], 2 );

// ------------------------------------------------------------ bảng cộng chéo
$c = KHTC_ChiPhi::bang_cheo( $ky );
kiem( 'cộng chéo: tổng bằng tổng bảng lọc', $c['tong'], 39980000 );
kiem( 'cộng chéo: đúng 3 bộ phận', count( $c['bo_phan'] ), 3 );
kiem( 'cộng chéo: ô Khu vui chơi × Tiền điện', $c['o']['Tiền điện']['Khu vui chơi'], 2400000 );
kiem( 'cộng chéo: ô Văn phòng × Tiền điện', $c['o']['Tiền điện']['Văn phòng'], 1100000 );
kiem( 'cộng chéo: ô trống thì không có khoá', isset( $c['o']['Vận chuyển']['Văn phòng'] ), false );
kiem( 'cộng chéo: tổng cột Khu vui chơi', $c['tong_cot']['Khu vui chơi'], 37400000 );
kiem( 'cộng chéo: tổng hàng Tiền điện', $c['tong_hang']['Tiền điện'], 3500000 );
kiem( 'cộng chéo: khoản mục tốn nhiều xếp trên', $c['khoan_muc'][0], 'Thuê mặt bằng' );
kiem( 'cộng chéo: tổng cột cộng lại bằng tổng chung', array_sum( $c['tong_cot'] ), $c['tong'] );
kiem( 'cộng chéo: tổng hàng cộng lại bằng tổng chung', array_sum( $c['tong_hang'] ), $c['tong'] );

// -------------------------------------------------------- đối soát chi phí
// Sao kê: 3 khoản ra đúng, 1 khoản ra trễ 2 ngày, 1 khoản chưa ra; thêm 1 dòng
// chi không có chứng từ.
KHTC_GiaoDich::dan_hang_loat(
	$nh,
	"10/08/2026\tTT tien dien EVN\t-2.400.000\tChi\tHD00123\n"
	. "12/08/2026\tTT thue mat bang\t-35.000.000\tChi\n"
	. "12/08/2026\tMua vat tu VP\t-780.000\tChi\n"
	. "17/08/2026\tTT tien dien VP\t-1.100.000\tChi\tHD00125\n"
	. "20/08/2026\tRut tien mat khong ro\t-6.000.000\tChi\n"
);
$r = KHTC_ChiPhi::doi_soat( '2026-08-01', '2026-08-31', $nh );
kiem( 'đối soát chi phí: khớp 4 khoản', count( $r['khop'] ), 4 );
kiem( 'đối soát chi phí: 1 khoản chưa thấy tiền ra', count( $r['chua_chi'] ), 1 );
kiem( 'đối soát chi phí: khoản chưa ra là Vận chuyển', $r['chua_chi'][0]->khoan_muc, 'Vận chuyển' );
kiem( 'đối soát chi phí: 1 dòng chi không có chứng từ', count( $r['thua'] ), 1 );
kiem( 'đối soát chi phí: dòng đó là 6.000.000', $r['thua'][0]->so_tien, 6000000 );
kiem( 'đối soát chi phí: tiền khớp', $r['tien_khop'], 39280000 );
kiem( 'đối soát chi phí: tiền chưa ra', $r['tien_chua_chi'], 450000 );

$kieu = array();
foreach ( $r['khop'] as $c2 ) { $kieu[ $c2->khoan_muc . '|' . $c2->bo_phan ] = $c2->kieu_khop; }
kiem( 'ghép theo số chứng từ', $kieu['Tiền điện|Khu vui chơi'], 'khop_ma' );
kiem( 'ghép theo ngày + tiền', $kieu['Thuê mặt bằng|Khu vui chơi'], 'khop_ngay' );
kiem( 'khoản ra trễ 2 ngày vẫn ghép theo số chứng từ', $kieu['Tiền điện|Văn phòng'], 'khop_ma' );

// Sau khi đối soát, màn hình Chi phí phải biết khoản nào đã trả.
kiem( 'sau đối soát, đã thấy tiền ra', KHTC_ChiPhi::loc( $ky )['da_tra'], 39280000 );
// 450.000 (Vận chuyển) + 250.000 (dòng dán bằng dấu phẩy, chưa gắn tài khoản).
kiem( 'sau đối soát, còn lại chưa thấy', KHTC_ChiPhi::loc( $ky )['chua_tra'], 700000 );
kiem( 'lọc riêng khoản chưa thấy tiền ra', KHTC_ChiPhi::loc( $ky + array( 'da_tra' => '0' ) )['so_dong'], 2 );

// Chạy lại phải ra y hệt, không cộng dồn.
$r2 = KHTC_ChiPhi::doi_soat( '2026-08-01', '2026-08-31', $nh );
kiem( 'chạy lại cho cùng kết quả', array( count( $r2['khop'] ), count( $r2['chua_chi'] ), count( $r2['thua'] ) ), array( 4, 1, 1 ) );

// Chi tiền mặt không đi qua ngân hàng nên phải nằm ngoài phép ghép.
KHTC_ChiPhi::them( array( 'ngay' => '22/08/2026', 'bo_phan' => 'Văn phòng', 'khoan_muc' => 'Khác', 'so_tien' => '300.000', 'hinh_thuc' => 'tien_mat' ) );
$r3 = KHTC_ChiPhi::doi_soat( '2026-08-01', '2026-08-31', $nh );
kiem( 'chi tiền mặt không bị báo thiếu', count( $r3['chua_chi'] ), 1 );
kiem( 'nhưng vẫn nằm trong tổng chi phí', KHTC_ChiPhi::loc( $ky )['tong'], 40280000 );

// ---------------------------------------------------------------- sao lưu
$sl = KHTC_SaoLuu::gom();
kiem( 'sao lưu gồm đủ mọi bảng', array_keys( $sl['bang'] ), KHTC_SaoLuu::bang() );
kiem( 'sao lưu giữ đủ khoản chi', count( $sl['bang']['chi_phi'] ), 7 );
kiem( 'sao lưu mang theo danh mục đã sửa', $sl['danh_muc']['bo_phan_kh_cu'], array( 'Khu vui chơi', 'Văn phòng', 'MTĐ' ) );

$json   = wp_json_encode( $sl );
$truoc  = KHTC_SaoLuu::dem();
$nhap   = KHTC_SaoLuu::nhap( $json );
$sau    = KHTC_SaoLuu::dem();
kiem( 'nhập lại là THÊM VÀO, số nhân đôi', $sau['chi_phi'], $truoc['chi_phi'] * 2 );
kiem( 'nhập báo đúng số dòng đã thêm', $nhap['them']['chi_phi'], $truoc['chi_phi'] );
kiem( 'không dòng chi phí nào bị từ chối', isset( $nhap['bo']['chi_phi'] ), false );

// Liên kết phải được nối lại theo id mới, không trỏ về bản ghi cũ.
global $wpdb;
$lech = (int) $wpdb->get_var(
	'SELECT COUNT(*) FROM wp_khtc_giao_dich g
	 LEFT JOIN wp_khtc_ngan_hang n ON n.id = g.ngan_hang_id
	 WHERE n.id IS NULL'
);
kiem( 'sau khi nhập, không giao dịch nào trỏ vào tài khoản không tồn tại', $lech, 0 );
$tk = $wpdb->get_results( 'SELECT id FROM wp_khtc_ngan_hang' );
kiem( 'sau khi nhập có đúng 2 tài khoản', count( $tk ), 2 );
$per = array();
foreach ( $wpdb->get_results( 'SELECT ngan_hang_id, COUNT(*) AS n FROM wp_khtc_giao_dich GROUP BY ngan_hang_id' ) as $row ) {
	$per[] = (int) $row->n;
}
kiem( 'giao dịch chia đều hai tài khoản, không dồn hết về một', $per, array( 5, 5 ) );

kiem( 'tệp không phải json thì báo lỗi', is_wp_error( KHTC_SaoLuu::nhap( 'xin chao' ) ), true );
kiem( 'định dạng khác thì báo lỗi', is_wp_error( KHTC_SaoLuu::nhap( '{"dinh_dang":99,"bang":{"chi_phi":[]}}' ) ), true );

// ----------------------------------------------------- dựng thật ba màn hình
$GLOBALS['khtc_qv'] = array();
$_GET  = array( 'tu' => '2026-08-01', 'den' => '2026-08-31' );
$_POST = array();

$t = dung( array( 'KHTC_Trang', 'chi_phi' ) );
co( 'trang chi phí có bảng cộng chéo', $t, 'Cộng chéo — khoản mục × bộ phận' );
co( 'trang chi phí hiện nhà cung cấp', $t, 'EVN HCMC' );
co( 'trang chi phí có ô sửa danh mục', $t, 'Bộ phận — mỗi dòng một cái' );
kiem( 'trang chi phí đóng đủ thẻ table', substr_count( $t, '<table' ), substr_count( $t, '</table>' ) );
kiem( 'trang chi phí đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );

$_GET = array( 'tu' => '2026-08-01', 'den' => '2026-08-31', 'nh' => $nh, 'chay' => 1 );
$t = dung( array( 'KHTC_Trang', 'doi_soat_chi_phi' ) );
co( 'đối soát chi phí nêu nhóm chưa thấy tiền ra', $t, 'Có chứng từ, chưa thấy tiền ra' );
co( 'đối soát chi phí nêu nhóm không có chứng từ', $t, 'Tiền ra, không có chứng từ' );
co( 'đối soát chi phí chỉ đúng dòng lạ', $t, 'Rut tien mat khong ro' );
kiem( 'đối soát chi phí đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );

$_GET = array();
$t = dung( array( 'KHTC_Trang', 'sao_luu' ) );
co( 'trang sao lưu đếm khoản chi phí', $t, 'Khoản chi phí' );
co( 'trang sao lưu có nút tải', $t, 'Tải tệp sao lưu' );
co( 'trang sao lưu cảnh báo nhập là thêm vào', $t, 'THÊM VÀO, không xoá' );
kiem( 'trang sao lưu đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );

// --------------------------- nhập vào sổ ĐÃ CÓ DỮ LIỆU (chỗ dễ sai nhất)
//
// Phép kiểm sao lưu cũ nhập vào sổ TRẮNG, nên id cấp lại trùng đúng id cũ và
// mọi liên kết sai vẫn ra đúng số. Hai lỗi dưới đây chỉ lộ khi sổ đã có dữ liệu.
KHTC_Cty::chon( 'kh_cu' );
$nh_g = KHTC_NganHang::them( array( 'ten' => 'Vietcombank gốc', 'so_tk' => '0071000111', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );
$hd_g = KHTC_HoaDonRa::them( array( 'ngay' => '05/08/2026', 'so_hd' => 'G0001', 'khach' => 'Khách gốc', 'co_vat' => '10.800.000', 'thue_suat' => '8' ) );
KHTC_CongNo::ghi( 'hd_ra', $hd_g, '10/08/2026', '4.000.000' );
$kho_1 = wp_json_encode( KHTC_SaoLuu::gom() );

// Đẩy id hoá đơn lệch đi, để id cũ trong tệp không còn trùng id mới.
for ( $i = 0; $i < 3; $i++ ) {
	KHTC_HoaDonRa::them( array( 'ngay' => '06/08/2026', 'so_hd' => 'CHEN' . $i, 'co_vat' => '1.080.000', 'thue_suat' => '8' ) );
}

$kq_n = KHTC_SaoLuu::nhap( $kho_1 );
kiem( 'nhập vào sổ đã có dữ liệu chạy được', is_wp_error( $kq_n ), false );

// LỖI 1: tài khoản ngân hàng bị nhân bản, số dư xẻ đôi.
kiem(
	'tài khoản trùng số thì dùng lại, không tạo bản sao',
	(int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ngan_hang' ) . ' WHERE so_tk = %s', '0071000111' ) ),
	1
);
kiem( 'và có báo là đã dùng lại', ( $kq_n['dung_lai']['ngan_hang'] ?? 0 ) >= 1, true );

// LỖI 2: khoản đã trả bám nhầm hoá đơn.
$tt = $wpdb->get_results( 'SELECT bang, chung_tu_id FROM ' . KHTC_DB::bang( 'thanh_toan' ) );
$hong_tt = 0;
foreach ( $tt as $x ) {
	$co = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( $x->bang ) . ' WHERE id = %d', (int) $x->chung_tu_id ) );
	if ( ! $co ) { $hong_tt++; }
}
kiem( 'không khoản trả nào trỏ vào chứng từ không tồn tại', $hong_tt, 0 );

// hd_ra có UNIQUE (cty, so_hd) nên bản thứ hai của G0001 bị từ chối. Chỗ chí
// mạng là khoản trả đi kèm: nếu nó được gán sang hoá đơn còn sống thì "đã trả"
// thành 8 triệu, còn nợ tụt xuống 2,8 triệu — sai có lợi cho khách, không ai thấy.
$g = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . KHTC_DB::bang( 'hd_ra' ) . ' WHERE so_hd = %s', 'G0001' ) );
kiem( 'G0001 vẫn chỉ một bản, bản nhập lại bị khoá duy nhất chặn', count( $g ), 1 );
kiem(
	'và nó giữ đúng 4 triệu của nó, không bị cộng thêm bản trùng',
	(int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'thanh_toan' ) . " WHERE bang='hd_ra' AND chung_tu_id = %d", (int) $g[0]->id ) ),
	4000000
);
kiem( 'khoản trả của hoá đơn bị từ chối đã bị bỏ', ( $kq_n['bo']['thanh_toan'] ?? 0 ) >= 1, true );
kiem( 'còn nợ vẫn đúng 6,8 triệu', KHTC_CongNo::da_tra( 'hd_ra', (int) $g[0]->id ), 4000000 );

// Chứng từ hoàn toàn không có trong tệp: cũng phải bỏ, không gán bừa id 0.
$kho_2 = json_decode( $kho_1, true );
$kho_2['bang']['hd_ra'] = array();   // bỏ chứng từ, giữ lại khoản trả
$kq2 = KHTC_SaoLuu::nhap( wp_json_encode( $kho_2 ) );
kiem( 'thiếu hẳn chứng từ thì khoản trả cũng bị bỏ', ( $kq2['bo']['thanh_toan'] ?? 0 ) >= 1, true );
kiem(
	'không dòng trả nào lọt vào sổ với chung_tu_id = 0',
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'thanh_toan' ) . ' WHERE chung_tu_id = 0' ),
	0
);
$hong2 = 0;
foreach ( $wpdb->get_results( 'SELECT bang, chung_tu_id FROM ' . KHTC_DB::bang( 'thanh_toan' ) ) as $x ) {
	if ( ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( $x->bang ) . ' WHERE id = %d', (int) $x->chung_tu_id ) ) ) { $hong2++; }
}
kiem( 'sau hai lần nhập vẫn không khoản trả nào mồ côi', $hong2, 0 );

// ------------------------------------- tệp dữ liệu kèm trong bản cài
//
// Chỗ này nhận TÊN TỆP từ trình duyệt. Ghép thẳng tên vào đường dẫn là mở cửa
// cho ../../wp-config.php, nên chỉ nhận tên có trong danh sách đã quét sẵn.
kiem( 'không có thư mục du-lieu thì không có tệp kèm', KHTC_SaoLuu::tep_kem(), array() );
kiem( 'tên lạ bị từ chối', is_wp_error( KHTC_SaoLuu::nhap_tep_kem( 'khong-co.json' ) ), true );
kiem( 'đường dẫn leo thư mục bị từ chối', is_wp_error( KHTC_SaoLuu::nhap_tep_kem( '../../wp-config.php' ) ), true );
kiem( 'tên rỗng bị từ chối', is_wp_error( KHTC_SaoLuu::nhap_tep_kem( '' ) ), true );

// Dựng thật một thư mục du-lieu rồi nhập, đúng như bản cài gói kèm dữ liệu.
$thu_muc = KHTC_DIR . 'du-lieu';
@mkdir( $thu_muc, 0777, true );
KHTC_Cty::chon( 'kh_cu' );
$nh_k = KHTC_NganHang::them( array( 'ten' => 'TK kèm theo', 'so_tk' => '999888', 'so_du_dau' => 0, 'ngay_moc' => '2026-08-01' ) );
KHTC_GiaoDich::them( array( 'ngan_hang_id' => $nh_k, 'ngay' => '10/08/2026', 'dien_giai' => 'Thu kèm', 'so_tien' => '5.000.000', 'loai' => 'thu' ) );
file_put_contents( "$thu_muc/kho-thu.json", wp_json_encode( KHTC_SaoLuu::gom() ) );

$truoc = KHTC_SaoLuu::dem();
$truoc_tk = array(
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ngan_hang' ) . " WHERE so_tk <> ''" ),
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ngan_hang' ) . " WHERE so_tk = ''" ),
);
kiem( 'thấy tệp vừa đặt vào du-lieu', array_keys( KHTC_SaoLuu::tep_kem() ), array( 'kho-thu.json' ) );
$kq = KHTC_SaoLuu::nhap_tep_kem( 'kho-thu.json' );
kiem( 'nhập tệp kèm chạy được', is_wp_error( $kq ), false );
$sau = KHTC_SaoLuu::dem();
// Tài khoản có số tài khoản thì được DÙNG LẠI, nên không nhân đôi nữa; giao
// dịch thì vẫn thêm vào như cũ. Đây là chỗ cho phép tách một kỳ sao kê dài
// thành nhiều tệp mà số dư không bị xẻ ra nhiều tài khoản trùng tên.
$co_so = function () use ( $wpdb ) {
	return array(
		(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ngan_hang' ) . " WHERE so_tk <> ''" ),
		(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ngan_hang' ) . " WHERE so_tk = ''" ),
	);
};
kiem( 'tài khoản CÓ số TK thì dùng lại, không nhân đôi', $co_so()[0], $truoc_tk[0] );
// Không có số tài khoản thì không có gì để nhận ra nhau, đành thêm mới — thà
// thừa một dòng thấy được còn hơn gộp nhầm hai tài khoản khác nhau.
kiem( 'tài khoản KHÔNG có số TK thì vẫn thêm mới', $co_so()[1], $truoc_tk[1] * 2 );
kiem( 'giao dịch vẫn tăng gấp đôi (nhập là thêm vào)', $sau['giao_dich'], $truoc['giao_dich'] * 2 );
// Liên kết phải nối lại theo id mới, không thì số dư sai mà không ai thấy.
kiem(
	'không giao dịch nào trỏ vào tài khoản không tồn tại',
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' g LEFT JOIN ' . KHTC_DB::bang( 'ngan_hang' ) . ' n ON n.id = g.ngan_hang_id WHERE n.id IS NULL' ),
	0
);
unlink( "$thu_muc/kho-thu.json" );
@rmdir( $thu_muc );
kiem( 'dọn xong thì lại không có tệp kèm nào', KHTC_SaoLuu::tep_kem(), array() );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
