<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SOÁT TRƯỚC KHI GỘP BA KHỐI — BẢNG ĐỐI CHIẾU PHẢI ĐÚNG, VÀ PHẢI KHÔNG GHI GÌ.
 *
 * Anh Thắng 20/09/2026: *"nếu bên kia có dữ liệu có cần dời không"* → *"vậy gộp đi em"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO BÀI KIỂM NÀY DỰNG KHO THẬT CHỨ KHÔNG DÒ MÃ NGUỒN
 * =============================================================================================
 * `VHCP_Gop` là thứ duy nhất trong cả bộ đọc XUYÊN QUA ba tiền tố bảng. Một phép dò chữ trong
 * mã nguồn chỉ nói được "có viết câu SELECT" — nó không bắt được cái sai thật sự đáng sợ ở đây:
 * đếm kho này rồi dán nhãn kho kia, hoặc cộng một đồng hai lần. Nên bài này dựng hẳn
 * `wp_vhcpmtd_*` bằng SQLite, gieo số đã biết trước, rồi đòi từng con số.
 *
 * Bốn chỗ bài này canh mà mắt người rất dễ bỏ qua:
 *
 *   1. 🔴 TỔNG TIỀN KHÔNG ĐƯỢC CỘNG HAI LẦN. `chiphi` là chi tiết BÊN TRONG đơn mà `tamung` đã
 *      gói lại. Cộng tuốt thì con số đưa anh Thắng không khớp với bất cứ báo cáo nào anh đang
 *      nhìn — mà số liệu đối chiếu sai thì thà đừng có, vì người ta sẽ tin nó.
 *   2. 🔴 "CHƯA CÓ BẢNG" KHÁC "CÓ BẢNG MÀ RỖNG". Trả 0 cho bảng không tồn tại là nói dối: người
 *      đọc kết luận "bên ấy không có dữ liệu" trong khi sự thật là "bên ấy chưa nâng cấp".
 *   3. 🔴 MÃ PIN KHÔNG ĐƯỢC LỌT RA. Bảng này hiện trên màn Cấu hình và hay bị chụp màn hình.
 *   4. 🔴 LỚP NÀY KHÔNG ĐƯỢC GHI MỘT DÒNG NÀO. Chụp số dòng mọi bảng trước/sau và đòi y nguyên.
 *
 * Chạy: php tools/test/kiem-soat-gop.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
/* `vhcp_test_boot()` đã dựng kho KVC rồi — gọi lại là SQLite chối "table already exists". */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

global $wpdb;

/* ═══ DỰNG KHO MTĐ ════════════════════════════════════════════════════════════════
 * Cùng sơ đồ, khác tiền tố — đúng như `tach-ban-vung.sh` sinh ra. Kho VP thì CỐ Ý KHÔNG dựng,
 * để thử nhánh "chưa cài": nhánh ấy có thật (máy anh Thắng có thể chưa cài bản nào đó) và nếu
 * nó trả về 0 thay vì "chưa cài" thì ta sẽ kết luận sai về cả một mảng. */
vhcp_test_create_tables( 'wp_vhcpmtd_' );
/* Và bỏ hẳn một bảng để thử nhánh "kho có, bảng thiếu" — bản vùng cài từ lâu, chưa nâng cấp. */
$wpdb->exec_raw( 'DROP TABLE wp_vhcpmtd_mk_don' );

/* ═══ GIEO SỐ ĐÃ BIẾT TRƯỚC ═══════════════════════════════════════════════════════ */
$cfg = function ( $tien_to, $bang, $rows ) use ( $wpdb ) {
	$i = 0;
	foreach ( $rows as $r ) {
		$wpdb->insert( 'wp_' . $tien_to . 'cfg', array( 'bang' => $bang, 'stt' => $i++, 'cols' => json_encode( $r, JSON_UNESCAPED_UNICODE ) ) );
	}
};

/* --- KVC: danh mục nền --- */
$cfg( 'vhcp_', 'CH_LoaiChiPhi', array(
	array( 'Điện nước', '6427', '1111', '', '', '', '', '' ),
	array( 'Sửa chữa máy', '6277', '1111', '', '', '', '', '' ),
	/* KVC bỏ trống TK Có ở dòng này — bên kia có khai. KHÔNG phải đụng độ, chỉ là chưa khai. */
	array( 'Văn phòng phẩm', '6428', '', '', '', '', '', '' ),
) );
$cfg( 'vhcp_', 'CH_CoSo', array(
	array( 'FUNZONE VŨNG TÀU', 'CS01', 'Khu vui chơi', 'FZ VT', '', '' ),
	array( 'KHO TRUNG TÂM', 'CS02', 'Kho', 'KHO TT', '', '' ),
) );
$cfg( 'vhcp_', 'CH_NguoiDung', array(
	array( 'Nguyễn Văn A', '1234', 'Nhân viên', 'FUNZONE VŨNG TÀU', '', '', '', '', '' ),
) );

/* --- MTĐ: một dòng riêng, một dòng khớp, một dòng ĐỤNG ĐỘ, một dòng KVC bỏ trống --- */
$cfg( 'vhcpmtd_', 'CH_LoaiChiPhi', array(
	array( 'Điện nước', '6427', '1111', '', '', '', '', '' ),            // khớp
	array( 'Sửa chữa máy', '6417', '1111', '', '', '', '', '' ),         // 🔴 ĐỤNG ĐỘ: TK Nợ khác
	array( 'Văn phòng phẩm', '6428', '3311', '', '', '', '', '' ),       // KVC trống TK Có -> KHÔNG đụng độ
	array( 'Tiền thuê mặt bằng máy', '6427', '3311', '', '', '', '', '' ), // riêng
) );
$cfg( 'vhcpmtd_', 'CH_CoSo', array(
	array( 'FUNZONE VŨNG TÀU', 'CS99', 'Máy tự động', 'FZ VT', '', '' ), // 🔴 ĐỤNG ĐỘ: Mã đơn vị khác
	array( 'ĐIỂM MÁY AEON', 'CS10', 'Máy tự động', 'AEON', '', '' ),     // riêng
) );
$cfg( 'vhcpmtd_', 'CH_NguoiDung', array(
	array( 'Trần Thị B', '9999', 'Nhân viên', 'ĐIỂM MÁY AEON', '', '', '', '', '' ),
	array( 'Nguyễn Văn A', '4321', 'Quản lý', 'FUNZONE VŨNG TÀU', '', '', '', '', '' ), // 🔴 ĐỤNG ĐỘ vai trò
) );

/* --- MTĐ: dữ liệu thật --- */
$wpdb->insert( 'wp_vhcpmtd_don', array( 'ma_don' => 'M1', 'trang_thai' => 'Đã cấp tạm ứng', 'khoi' => 'mtd' ) );
$wpdb->insert( 'wp_vhcpmtd_don', array( 'ma_don' => 'M2', 'trang_thai' => 'Đã cấp tạm ứng', 'khoi' => 'mtd' ) );
/* 🔴 MỘT ĐƠN ĐÓNG DẤU LẠ — bản cài sai `const KHOI`. Phải bị bêu ra, không được lặng lẽ đếm chung. */
$wpdb->insert( 'wp_vhcpmtd_don', array( 'ma_don' => 'M3', 'trang_thai' => 'Nháp', 'khoi' => 'kvc' ) );

$wpdb->insert( 'wp_vhcpmtd_tamung', array( 'ma_don' => 'M1', 'coso' => 'ĐIỂM MÁY AEON', 'so' => 5000000 ) );
$wpdb->insert( 'wp_vhcpmtd_tamung', array( 'ma_don' => 'M2', 'coso' => 'FUNZONE VŨNG TÀU', 'so' => 3000000 ) );

$wpdb->insert( 'wp_vhcpmtd_chiphi', array( 'id' => 'c1', 'ma_don' => 'M1', 'coso' => 'ĐIỂM MÁY AEON', 'nhom' => 'Tiền thuê mặt bằng máy', 'thanh_tien' => 4000000 ) );
$wpdb->insert( 'wp_vhcpmtd_chiphi', array( 'id' => 'c2', 'ma_don' => 'M1', 'coso' => 'ĐIỂM MÁY AEON', 'nhom' => 'Tiền thuê mặt bằng máy', 'thanh_tien' => 900000 ) );
$wpdb->insert( 'wp_vhcpmtd_chiphi', array( 'id' => 'c3', 'ma_don' => 'M2', 'coso' => 'FUNZONE VŨNG TÀU', 'nhom' => 'Điện nước', 'thanh_tien' => 3000000 ) );

$wpdb->insert( 'wp_vhcpmtd_so_chi', array( 'id' => 's1', 'coso' => 'ĐIỂM MÁY AEON', 'loai' => 'Phí bảo trì máy', 'so_tien' => 1200000, 'khoi' => 'mtd' ) );

$T_KVC_TU = 7000000;
$wpdb->insert( 'wp_vhcp_don', array( 'ma_don' => 'K1', 'trang_thai' => 'Nháp', 'khoi' => 'kvc' ) );
$wpdb->insert( 'wp_vhcp_tamung', array( 'ma_don' => 'K1', 'coso' => 'FUNZONE VŨNG TÀU', 'so' => $T_KVC_TU ) );

/* ═══ MỒI NHỬ: BẢNG CỦA MỘT BẢN WORDPRESS KHÁC CÀI CHUNG CƠ SỞ DỮ LIỆU ═══════════
 * 🔴 Tiền tố `wp_` có dấu gạch dưới, mà trong SQL `_` là KÝ TỰ THAY THẾ. Câu dò để trần sẽ vớ
 *    luôn bảng của site bên cạnh (tiền tố `wpx`) và dựng ra một "kho MTĐ" ma — rồi đưa anh
 *    Thắng số liệu của người khác, hoặc một cột trống rỗng mang tên đúng mảng của anh.
 *    Cả hai đều tệ hơn là không có bảng đối chiếu. */
/* ⚠️ MÃ VÙNG CỦA MỒI PHẢI KHÁC MỌI KHO THẬT. Bản đầu đặt mồi là `wpxvhcpmtd_don`, và cả hai
   lớp gác gỡ đi bài kiểm vẫn XANH: lưới hỏng bóc ra mã `mtd`, trùng đúng kho thật đã có, nên
   nó GHI ĐÈ một mục y hệt và không để lại dấu vết nào. Một phép thử mà cái sai tự giấu mình
   thì không phải phép thử. Mã `zz` không phải kho nào cả — hiện ra là lộ ngay. */
$wpdb->exec_raw( 'CREATE TABLE wpxvhcpzz_don (ma_don TEXT, trang_thai TEXT, khoi TEXT)' );
$wpdb->exec_raw( "INSERT INTO wpxvhcpzz_don VALUES ('X1','Nháp','zz')" );

/* ═══ CHỤP ẢNH TRƯỚC KHI GỌI — để đòi "không ghi gì" ══════════════════════════════ */
$anh = function () use ( $wpdb ) {
	$ra = array();
	foreach ( (array) $wpdb->get_results( "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name", ARRAY_A ) as $r ) {
		$n = (string) $r['name'];
		$ra[ $n ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $n" );
	}
	return $ra;
};
$truoc = $anh();

/* ═════════════════════════════════════════════════════════════════════════════════
   GỌI */
$kq = VHCP_Gop::soat();
t( 'soát chạy được', ! empty( $kq['success'] ), $kq );
$sau = $anh();

/* ═══ 1. 🔴 CHỈ ĐỌC — KHÔNG MỘT DÒNG NÀO ĐỔI ══════════════════════════════════════ */
teq( '🔴 soát KHÔNG ghi gì (số dòng mọi bảng y nguyên)', $truoc, $sau );

/* ═══ 2. KHO NÀO CÓ, KHO NÀO CHƯA CÀI ═════════════════════════════════════════════ */
$kho = isset( $kq['kho'] ) ? $kq['kho'] : array();
teq( 'kho KVC có cài', true, ! empty( $kho['kvc']['cai_dat'] ) );
teq( 'kho MTĐ có cài', true, ! empty( $kho['mtd']['cai_dat'] ) );
/* 🔴 KHO PHẢI ĐƯỢC DÒ RA, KHÔNG GÕ SẴN. Kho VP không dựng ở trên nên tuyệt đối không được có
   mặt — nếu nó hiện ra thì mã đang đọc một danh sách gõ tay chứ không đọc cơ sở dữ liệu, và
   ngày nào anh Thắng sinh bản vùng thứ tư thì bản ấy vô hình. */
teq( '🔴 kho VP KHÔNG dựng thì KHÔNG có mặt trong kết quả', false, isset( $kho['vp'] ) );
teq( '   đúng hai kho được dò ra', array( 'kvc', 'mtd' ), array_keys( $kho ) );
teq( '   và kho nhà đứng đầu, đúng bản đang chạy', 'kvc', $kq['nha'] );
/* Dò thẳng: hàm `kho()` phải tự tìm ra tiền tố, không lấy từ hằng nào. */
teq( '🔴 `kho()` dò đúng tiền tố kho MTĐ', 'vhcpmtd_', VHCP_Gop::kho()['mtd'] );
teq( '   và tiền tố kho nhà', 'vhcp_', VHCP_Gop::kho()['kvc'] );
/* Mồi nhử ở trên: bảng `wpxvhcpzz_don` của site bên cạnh KHÔNG được biến thành kho. Đúng 2
   kho ở phép trên đã gác điều này; phép dưới nói thẳng ra con số để người đọc thấy ngay. */
/* ⚠️ Phép này chỉ đỏ khi GỠ CẢ HAI lớp gác trong `kho()` (che `_` của tiền tố + neo tên bảng).
   Gỡ riêng từng lớp thì nó vẫn xanh — mỗi lớp một mình đã đủ chặn con mồi. Cố ý giữ hai lớp;
   xem khối "ĐỘT BIẾN TƯƠNG ĐƯƠNG" ở `class-vhcp-gop.php`. */
teq( '🔴 bảng của bản WordPress khác KHÔNG thành kho ma', 2, count( VHCP_Gop::kho() ) );
teq( '   và số đơn của MTĐ không lẫn đơn của site bên cạnh', 3, $kho['mtd']['bang']['don']['dong'] );

/* Gác thẳng `dem_kho()`: hỏi một khối không có kho thì phải trả `null`.
   🔴 `soat()` chỉ đi qua kho ĐÃ DÒ RA nên không bao giờ chạm nhánh này — mà `dem_kho()` là
      hàm công khai, bước dời dữ liệu sắp tới sẽ gọi thẳng. Không gác ở đây thì nhánh ấy chưa
      từng có một phép thử nào, và ngày nó được gọi là ngày nó trả số 0 cho một kho không có. */
teq( '🔴 `dem_kho()` với khối không có kho trả null', null, VHCP_Gop::dem_kho( 'khong-ton-tai' ) );

/* ═══ 3. ĐẾM DÒNG ═════════════════════════════════════════════════════════════════ */
$b = $kho['mtd']['bang'];
teq( 'MTĐ: 3 đơn', 3, $b['don']['dong'] );
teq( 'MTĐ: 2 dòng tạm ứng', 2, $b['tamung']['dong'] );
teq( 'MTĐ: 3 dòng chi', 3, $b['chiphi']['dong'] );
teq( 'MTĐ: 1 dòng sổ chi', 1, $b['so_chi']['dong'] );
/* 🔴 Bảng bị bỏ đi: phải nói "không có", KHÔNG được trả 0. */
teq( '🔴 bảng thiếu báo co=false', false, $b['mk_don']['co'] );
teq( '🔴 và số dòng là null, KHÔNG phải 0', null, $b['mk_don']['dong'] );

/* ═══ 4. 🔴 TIỀN — KHÔNG CỘNG HAI LẦN ═════════════════════════════════════════════ */
teq( 'MTĐ: tổng tạm ứng', 8000000.0, $b['tamung']['tien'] );
teq( 'MTĐ: tổng dòng chi', 7900000.0, $b['chiphi']['tien'] );
teq( 'MTĐ: tổng sổ chi', 1200000.0, $b['so_chi']['tien'] );
/* Tổng = tạm ứng + sổ chi = 8.000.000 + 1.200.000. KHÔNG cộng 7.900.000 của `chiphi`:
   đó là chi tiết bên trong chính mấy đơn mà `tamung` đã gói. */
teq( '🔴 tổng tiền = tạm ứng + sổ chi, KHÔNG cộng dòng chi', 9200000.0, $kho['mtd']['tong_tien'] );
teq( '   (nếu cộng tuốt thì ra con số này — phải KHÁC)', true, 17100000.0 !== $kho['mtd']['tong_tien'] );
teq( 'KVC: tổng tiền của chính nó', (float) $T_KVC_TU, $kho['kvc']['tong_tien'] );

/* ═══ 5. TRẠNG THÁI & DẤU KHỐI ════════════════════════════════════════════════════ */
teq( 'MTĐ: 2 đơn "Đã cấp tạm ứng"', 2, $kho['mtd']['trang_thai']['Đã cấp tạm ứng'] );
/* 🔴 Đơn đóng dấu lạ phải lộ ra, kèm số lượng. */
teq( '🔴 kho MTĐ có 1 đơn đóng dấu "kvc" (bản cài sai)', 1, $kho['mtd']['dau_khoi']['kvc'] );
teq( '   và 2 đơn đóng dấu đúng', 2, $kho['mtd']['dau_khoi']['mtd'] );

/* ═══ 6. ĐỐI CHIẾU DANH MỤC ═══════════════════════════════════════════════════════ */
$dm = $kho['mtd']['danh_muc'];

$l = $dm['CH_LoaiChiPhi'];
teq( 'loại chi phí: 1 dòng riêng (phải thêm vào KVC)', 1, count( $l['rieng'] ) );
teq( '   tên dòng riêng', 'Tiền thuê mặt bằng máy', $l['rieng'][0][0] );
/* "Điện nước" khớp hẳn; "Văn phòng phẩm" khớp vì bên KVC bỏ trống ô kia. */
teq( '🔴 một bên bỏ trống KHÔNG phải đụng độ (chỉ là chưa khai)', 2, $l['trung'] );
teq( '🔴 đúng 1 đụng độ thật', 1, count( $l['dung_do'] ) );
teq( '   đụng ở "Sửa chữa máy"', 'Sửa chữa máy', $l['dung_do'][0]['ten'] );
teq( '   và nói rõ ô nào lệch', 'TK Nợ', $l['dung_do'][0]['lech'][0]['cot'] );
teq( '   KVC đang khai', '6277', $l['dung_do'][0]['lech'][0]['kvc'] );
teq( '   MTĐ đang khai', '6417', $l['dung_do'][0]['lech'][0]['kia'] );

$cs = $dm['CH_CoSo'];
teq( 'cơ sở: 1 riêng', 1, count( $cs['rieng'] ) );
teq( 'cơ sở: 1 đụng độ (cùng tên gian, khác mã MISA)', 1, count( $cs['dung_do'] ) );
teq( '   tên gian đụng độ', 'FUNZONE VŨNG TÀU', $cs['dung_do'][0]['ten'] );

$nd = $dm['CH_NguoiDung'];
teq( 'người dùng: 1 riêng', 1, count( $nd['rieng'] ) );
teq( 'người dùng: 1 đụng độ vai trò', 1, count( $nd['dung_do'] ) );

/* ═══ 7. 🔴 MÃ PIN KHÔNG ĐƯỢC LỌT RA — SOI TOÀN BỘ KẾT QUẢ ════════════════════════
 * Soi cả cây trả về, không soi riêng nhánh người dùng: chốt phải đúng kể cả khi mai này có ai
 * thêm một nhánh mới bê nguyên hàng cấu hình vào. */
$phang = json_encode( $kq, JSON_UNESCAPED_UNICODE );
t( '🔴 PIN "9999" (chỉ MTĐ có) KHÔNG lọt vào bảng đối chiếu', false === mb_strpos( $phang, '9999' ) );
t( '🔴 PIN "4321" KHÔNG lọt vào bảng đối chiếu', false === mb_strpos( $phang, '4321' ) );
t( '   và ô PIN được thay bằng chữ "(có PIN)"', false !== mb_strpos( $phang, '(có PIN)' ) );
teq( '   đúng chỗ: cột PIN của dòng riêng', '(có PIN)', $nd['rieng'][0][1] );

/* ═══ 8. 🔴 MỒ CÔI — ĐÚNG SỐ DÒNG SẼ MẤT MÃ NẾU LÀM NGƯỢC THỨ TỰ ══════════════════ */
$mc = $kho['mtd']['mo_coi'];
/* "Tiền thuê mặt bằng máy": 2 dòng chi. "Phí bảo trì máy": 1 dòng sổ chi. Cả hai đều chưa có
   trong danh mục KVC. "Điện nước" thì có rồi nên KHÔNG được đếm. */
teq( 'mồ côi loại: đúng 2 tên', 2, count( $mc['loai'] ) );
teq( '   "Tiền thuê mặt bằng máy" dính 2 dòng', 2, $mc['loai']['Tiền thuê mặt bằng máy'] );
teq( '   "Phí bảo trì máy" dính 1 dòng', 1, $mc['loai']['Phí bảo trì máy'] );
t( '🔴 loại ĐÃ CÓ bên KVC không bị đếm là mồ côi', ! isset( $mc['loai']['Điện nước'] ) );
/* Cơ sở "ĐIỂM MÁY AEON" chưa có bên KVC: 2 dòng chi + 1 sổ chi + 1 tạm ứng = 4. */
teq( 'mồ côi cơ sở: đúng 1 tên', 1, count( $mc['coso'] ) );
teq( '   "ĐIỂM MÁY AEON" dính 4 dòng', 4, $mc['coso']['ĐIỂM MÁY AEON'] );
t( '🔴 cơ sở ĐÃ CÓ bên KVC không bị đếm là mồ côi', ! isset( $mc['coso']['FUNZONE VŨNG TÀU'] ) );

/* ═══ 9. KHO KVC KHÔNG TỰ ĐỐI CHIẾU VỚI CHÍNH NÓ ══════════════════════════════════ */
t( 'kho KVC không kèm bảng đối chiếu (so với chính mình là vô nghĩa)', ! isset( $kho['kvc']['danh_muc'] ) );

/* ═══ 10. NHẮC THỨ TỰ ĐI KÈM DỮ LIỆU, KHÔNG CHỈ Ở MÀN HÌNH ════════════════════════
 * Màn hình có thể bị thay; lời nhắc đi kèm dữ liệu thì cổng nào gọi cũng nhận được. */
t( '🔴 kết quả nhắc "danh mục trước, dữ liệu sau"',
	false !== mb_stripos( (string) $kq['thu_tu'], 'danh mục' ) && false !== mb_stripos( (string) $kq['thu_tu'], 'trước' ),
	isset( $kq['thu_tu'] ) ? $kq['thu_tu'] : '(không có)' );

/* ═══ 11. CỔNG: CÓ ĐƯỜNG GỌI, VÀ CHỈ ADMIN ĐI ĐƯỢC ════════════════════════════════
 * 🔴 Chỉ đọc KHÔNG có nghĩa là ai đọc cũng được: nó bày ra tiền và danh sách người của HAI
 *    mảng khác — đúng cái ranh giới thanh khối dựng lên để *"tránh râu ông này cắm bà kia"*. */
$api = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
t( 'cổng có khai lệnh `soatGop`', false !== mb_strpos( $api, "'soatGop'               => array( 'VHCP_Gop', 'soat' )" ) );
/* Cắt đúng khối `$admin_only` rồi mới tìm — tìm trong cả tệp thì dòng khai lệnh ở trên cũng
   khớp, và phép này xanh oan dù `soatGop` chưa hề được chặn. */
$i0 = mb_strpos( $api, '$admin_only = array(' );
$i1 = false === $i0 ? false : mb_strpos( $api, 'public static function', $i0 );
$khoi_admin = ( false === $i0 || false === $i1 ) ? '' : mb_substr( $api, $i0, $i1 - $i0 );
t( '🔴 `soatGop` nằm trong danh sách CHỈ ADMIN', false !== mb_strpos( $khoi_admin, "'soatGop'" ), mb_substr( $khoi_admin, 0, 200 ) );

/* ═══ 12. LỚP KHÔNG CÓ MỘT ĐƯỜNG GHI NÀO ══════════════════════════════════════════
 * Phép 1 đã đo hành vi thật; phép này gác mã nguồn, để lượt sửa sau không lặng lẽ thêm cửa ghi
 * vào một nhánh mà dữ liệu gieo ở đây không chạy qua. */
$src = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-gop.php' );
$sach = preg_replace( '#/\*.*?\*/#su', '', $src );
$sach = (string) preg_replace( '#//[^\n]*#u', '', (string) $sach );
foreach ( array( '->insert(', '->update(', '->delete(', '->replace(', 'INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'ALTER ', 'TRUNCATE ' ) as $cam ) {
	t( "🔴 `VHCP_Gop` không có `$cam`", false === mb_strpos( $sach, $cam ), $cam );
}

/* ═══ 13. 🔴 BẢN GỐC KHÔNG ĐƯỢC GÕ SẴN TIỀN TỐ CỦA BẢN VÙNG ═══════════════════════
 * Cùng một luật với `kiem-tach-ban-vung.php`, nhắc lại ngay tại tệp dễ vi phạm nhất: `VHCP_Gop`
 * là thứ DUY NHẤT trong bộ đọc xuyên ba kho, nên nó là chỗ người ta hay gõ sẵn `'vhcpmtd_'` cho
 * nhanh. Gõ sẵn thì `tach-ban-vung.sh` lúc sinh bản VP đổi luôn chuỗi ấy thành `'vhcpvpmtd_'` —
 * một tiền tố không tồn tại — và bản VP bày ra bảng trống rồi nói "bên kia không có gì". */
foreach ( array( 'mtd', 'vp' ) as $ma ) {
	t( "🔴 `class-vhcp-gop.php` không gõ sẵn chuỗi `vhcp$ma`",
		false === mb_strpos( $src, 'vhcp' . $ma ), $ma );
}
/* Và một kho lạ, chưa ai nghĩ ra, cũng phải tự hiện ra. */
vhcp_test_create_tables( 'wp_vhcphn_' );
$kq2 = VHCP_Gop::soat();
teq( '🔴 kho vùng MỚI (hn) tự hiện ra, không cần sửa mã', true, isset( $kq2['kho']['hn'] ) );
t( '   và nó được đối chiếu danh mục như mọi kho khác', isset( $kq2['kho']['hn']['danh_muc'] ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: bảng đối chiếu đếm đúng, không cộng hai lần, không lộ PIN, và không ghi một dòng nào.\n";
