<?php
/**
 * KIỂM MẢNG KINH DOANH & BỘ PHẬN CỦA NHÂN SỰ.
 *
 * =============================================================================================
 * Anh Thắng 13/09/2026: *"Cơ cấu lại hệ nhân sự để phân quyền theo mảng kinh doanh và bộ phận
 * được phân"* — *"Sau này ai thuộc mảng nào và bộ phận nào sẽ phân quyền và điều động dễ hơn"*.
 * Anh chốt: bản này CHỈ gắn + lọc + điều động, chưa bó quyền; và 225 người đang có thì
 * *"tự suy từ cơ sở, sửa tay chỗ nào sai"*.
 *
 * =============================================================================================
 * 🔴 THỨ BÀI NÀY THẬT SỰ CANH: CỘT TRỐNG PHẢI GIỮ NGHĨA "THEO CƠ SỞ"
 * =============================================================================================
 * Cả thiết kế đứng trên một điểm: `mang`/`bo_phan` để TRỐNG nghĩa là "trôi theo cơ sở", và giá
 * trị thật được suy ra LÚC ĐỌC. Nhờ vậy đổi mảng của một cơ sở là 40 người theo ngay, và đếm
 * được ai chưa có ai soát.
 *
 * Ngày nào có người thấy cột trống rồi "dọn dẹp" bằng một lượt ghi đè cho đủ 225 dòng, mọi tính
 * chất ấy mất sạch — mà KHÔNG có gì đỏ, vì bảng vẫn hiện đủ tên mảng như cũ. Đó là lý do bài này
 * canh cả hai chiều: suy đúng, VÀ khai tay không bị tự động ghi đè.
 *
 * Chạy: php tools/test/kiem-mang-bo-phan.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();

register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT (tới lúc chết): $dat\n";
} );

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . ( is_scalar( $mong ) ? $mong : wp_json_encode( $mong ) ) . ')',
		$mong === $thuc, is_scalar( $thuc ) ? $thuc : wp_json_encode( $thuc ) );
}

global $wpdb;

/* ------------------------------------------------------------------ dựng dữ liệu mẫu */

/* Hai cơ sở có khai mảng, một cơ sở KHÔNG khai — cảnh thứ ba mới là cảnh hay lọt. */
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => 'VIVO', 'bo_phan' => 'Khu vui chơi' ) );
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => 'POSH_Q1', 'bo_phan' => 'Máy tự động' ) );

$nv = function ( $ma, $ten, $cs, $mang = '', $bp = '' ) use ( $wpdb ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
		'ma_nv' => $ma, 'ho_ten' => $ten, 'cua_hang' => $cs,
		'mang' => $mang, 'bo_phan' => $bp, 'vai_tro' => 'Nhân viên' ) );
};
$nv( 'NV_VIVO', 'Nhân viên Vivo', 'VIVO' );                 // theo cơ sở -> Khu vui chơi
$nv( 'NV_POSH', 'Nhân viên Posh', 'POSH_Q1' );              // theo cơ sở -> Máy tự động
$nv( 'NV_LA',   'Cơ sở chưa khai', 'CS_LA_HOAC' );          // cơ sở không có trong bo_phan_coso
$nv( 'NV_VP',   'Chị văn phòng', '' );                      // không cơ sở, chưa xếp
$nv( 'NV_KIEM', 'Anh kiêm nhiệm', 'VIVO', 'Văn phòng', 'Phòng Kỹ Thuật' );  // khai tay, lệch cơ sở

/* ================================================================== 1. SUY TỪ CƠ SỞ */

teq( 'cơ sở có khai mảng -> suy ra đúng mảng ấy',
	'Khu vui chơi', VHCC_NhanSu::mang_theo_coso( 'VIVO' ) );
teq( 'cơ sở khác, mảng khác', 'Máy tự động', VHCC_NhanSu::mang_theo_coso( 'POSH_Q1' ) );

/* 🔴 `VHCC_Luong::bo_phan_cua()` trả chuỗi 'Chưa xếp' khi tra không ra — đó là CÂU TRẢ LỜI, không
   phải một mảng có thật. Trả thẳng nó ra là bịa cho người ta một mảng tên "Chưa xếp", rồi nó mọc
   vào ô xổ, vào dải đếm, và trông y như một mảng thật. */
teq( '🔴 cơ sở chưa khai mảng -> trả rỗng, KHÔNG trả "Chưa xếp"',
	'', VHCC_NhanSu::mang_theo_coso( 'CS_LA_HOAC' ) );
teq( 'không có cơ sở -> cũng rỗng', '', VHCC_NhanSu::mang_theo_coso( '' ) );

/* Bộ phận thì không suy từ cơ sở ra phòng ban được — chỉ biết "người này đứng quầy". */
teq( 'có cơ sở -> bộ phận mặc định là khối cơ sở',
	VHCC_NhanSu::BP_CO_SO, VHCC_NhanSu::bo_phan_theo_coso( 'VIVO' ) );
teq( 'không cơ sở -> để trống, phải khai tay', '', VHCC_NhanSu::bo_phan_theo_coso( '' ) );

/* ================================================================== 2. KHAI TAY THẮNG SUY */

$doc = function ( $ma ) { return VHCC_NhanSu::mang_bo_phan_cua( VHCC_NhanSu::ho_so( $ma ) ); };

$x = $doc( 'NV_VIVO' );
teq( 'người trôi theo cơ sở: mảng suy ra', 'Khu vui chơi', $x['mang'] );
teq( 'và bộ phận là khối cơ sở', VHCC_NhanSu::BP_CO_SO, $x['boPhan'] );
teq( '🔴 nhưng ô KHAI vẫn rỗng — đây là chỗ phân biệt "đã xếp" với "máy đoán hộ"', '', $x['mangKhai'] );
t( 'và được đánh dấu là đang trôi theo cơ sở', ! empty( $x['theoCoSo'] ) );

$x = $doc( 'NV_KIEM' );
teq( '🔴 khai tay THẮNG suy từ cơ sở (mảng)', 'Văn phòng', $x['mang'] );
teq( 'khai tay thắng cả ở bộ phận', 'Phòng Kỹ Thuật', $x['boPhan'] );
t( 'người đã khai tay KHÔNG còn bị tính là trôi theo cơ sở', empty( $x['theoCoSo'] ) );

$x = $doc( 'NV_VP' );
teq( 'chị văn phòng chưa xếp: mảng rỗng', '', $x['mang'] );
teq( 'bộ phận cũng rỗng', '', $x['boPhan'] );

/* ================================================================== 3. ĐỔI MẢNG CƠ SỞ THÌ AI ĐANG
 *                                                                      TRÔI PHẢI ĐI THEO
 * Đây là cả lý do chọn "suy lúc đọc". Nếu một ngày có người thay bằng lượt ghi đè, phép thử này
 * đỏ ngay — và nó phải đỏ, vì lúc ấy 40 người ở một cơ sở vừa đổi mảng sẽ âm thầm mang mảng cũ.
 */
$wpdb->query( 'UPDATE ' . VHCC_DB::t( 'bo_phan_coso' )
	. " SET bo_phan='Máy tự động' WHERE coso='VIVO'" );

teq( '🔴 đổi mảng của cơ sở -> người TRÔI THEO đi theo ngay',
	'Máy tự động', $doc( 'NV_VIVO' )['mang'] );
teq( '🔴 còn người ĐÃ KHAI TAY thì đứng yên — đúng ý người khai',
	'Văn phòng', $doc( 'NV_KIEM' )['mang'] );

$wpdb->query( 'UPDATE ' . VHCC_DB::t( 'bo_phan_coso' )
	. " SET bo_phan='Khu vui chơi' WHERE coso='VIVO'" );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3b. "LÀM SAO BIẾT NHÂN VIÊN ĐÓ LÀM CƠ SỞ ĐÓ MÀ SUY"
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 13/09/2026, đúng một câu, trúng ba lỗ hổng của bản đầu — cả ba đều câm:
 *
 *  1. Chỉ nhìn CƠ SỞ ĐẦU TIÊN: `chuan_coso()` cắt ở dấu phẩy đầu, nên người làm hai nơi chỉ
 *     được xét theo nơi thứ nhất. Màn hình ghi một mảng gọn gàng, không gì cho biết nó vừa bỏ
 *     qua một nửa.
 *  2. Cơ sở CHÍNH trống thì coi như không có cơ sở — người chỉ có `coso_phu` bị xếp "chưa xếp"
 *     trong khi họ có nơi làm hẳn hoi.
 *  3. Không nói SUY TỪ ĐÂU, nên không ai soát được — mà phép suy không soát được thì chẳng
 *     khác gì phép đoán.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => 'GO_AN_LAC', 'bo_phan' => 'Khu vui chơi' ) );

/* --- (1) hai cơ sở CÙNG mảng: suy được, và phải kể ra CẢ HAI làm căn cứ --- */
$nv( 'NV_2CS', 'Làm hai nơi cùng mảng', 'VIVO' );
$wpdb->query( 'UPDATE ' . VHCC_DB::t( 'nhan_vien' )
	. " SET coso_phu='GO_AN_LAC' WHERE ma_nv='NV_2CS'" );
$sm = VHCC_NhanSu::suy_mang( VHCC_NhanSu::ho_so( 'NV_2CS' ) );
teq( 'hai cơ sở cùng mảng -> suy ra đúng MỘT mảng', array( 'Khu vui chơi' ), $sm['dsMang'] );
teq( '🔴 và căn cứ kể ĐỦ HAI cơ sở, không chỉ cơ sở đầu', 2, count( $sm['coSo'] ) );
t( 'câu giải thích đọc ra được tên cơ sở', strpos( $sm['vi'], 'VIVO' ) !== false
	&& strpos( $sm['vi'], 'GO_AN_LAC' ) !== false, $sm['vi'] );

/* --- (1b) 🔴 LÀM HAI MẢNG LÀ CHUYỆN THƯỜNG, KHÔNG PHẢI LỖI ---
 *
 * Anh Thắng 13/09/2026: *"Đối với nhân viên là người làm thì họ có thể làm ở 2 mảng nhiều cơ sở,
 * nhưng đối với quản lý 1 mảng thì mình không lo"* — *"làm ở 2 mảng, thì chấm công ở 2 mảng"*.
 *
 * Bản 3.68.0 gắn cờ đỏ «lệch mảng» cho cảnh này và đẩy người ấy vào danh sách việc. Sai theo kiểu
 * tệ nhất: nhân viên quầy chạy giữa hai mảng là chuyện HÀNG NGÀY, nên cờ ấy bật cho phần lớn sổ —
 * mà một danh sách việc dài bằng cả công ty thì không phải danh sách việc, nó là nhiễu. Và nhiễu
 * dạy người ta thôi đọc cờ, hỏng luôn cờ thật.
 */
$nv( 'NV_2MANG', 'Làm hai mảng', 'VIVO' );
$wpdb->query( 'UPDATE ' . VHCC_DB::t( 'nhan_vien' )
	. " SET coso_phu='POSH_Q1' WHERE ma_nv='NV_2MANG'" );
$sm = VHCC_NhanSu::suy_mang( VHCC_NhanSu::ho_so( 'NV_2MANG' ) );
teq( '🔴 làm hai mảng -> suy ra ĐỦ HAI, không chịu thua', 2, count( $sm['dsMang'] ) );
t( 'và đúng hai mảng ấy', in_array( 'Khu vui chơi', $sm['dsMang'], true )
	&& in_array( 'Máy tự động', $sm['dsMang'], true ), $sm['dsMang'] );
t( 'câu giải thích kể từng mảng kèm cơ sở căn cứ',
	strpos( $sm['vi'], 'Khu vui chơi (VIVO)' ) !== false
	&& strpos( $sm['vi'], 'Máy tự động (POSH_Q1)' ) !== false, $sm['vi'] );

$x = VHCC_NhanSu::mang_bo_phan_cua( VHCC_NhanSu::ho_so( 'NV_2MANG' ) );
t( '🔴 KHÔNG nằm trong danh sách việc — đây là trạng thái hợp lệ', empty( $x['canChonTay'] ), $x );
teq( 'chuỗi để ĐỌC ghép cả hai', 'Khu vui chơi + Máy tự động', $x['mang'] );
teq( 'danh sách để SO có đủ hai', 2, count( $x['dsMang'] ) );

/* 🔴 Người hai mảng phải KHỚP CẢ HAI ô lọc. So bằng chuỗi ghép thì họ không khớp ô nào và biến
   mất khỏi mọi bộ lọc — mà biến mất thì không ai thấy để mà thắc mắc. */
t( '🔴 khớp ô lọc "Khu vui chơi"', in_array( 'Khu vui chơi', $x['dsMang'], true ) );
t( '🔴 và khớp cả ô lọc "Máy tự động"', in_array( 'Máy tự động', $x['dsMang'], true ) );

/* Ghim tay NHIỀU mảng cũng phải được — ô tích, không phải ô xổ một lựa chọn. */
$r = VHCC_NhanSu::dat_mang_bo_phan( array( 'ma_nv' => 'KT9', 'role' => 'Kế toán' ),
	'NV_2MANG', 'Máy tự động, Văn phòng', '' );
t( 'ghim tay HAI mảng cùng lúc được', ! empty( $r['ok'] ) && ! empty( $r['doi'] ), $r );
$x = VHCC_NhanSu::mang_bo_phan_cua( VHCC_NhanSu::ho_so( 'NV_2MANG' ) );
teq( 'giữ đủ hai mảng đã ghim', 2, count( $x['dsMang'] ) );
t( 'và đúng hai mảng ấy', in_array( 'Máy tự động', $x['dsMang'], true )
	&& in_array( 'Văn phòng', $x['dsMang'], true ), $x['dsMang'] );

/* Một mảng lạ trong chuỗi thì chối CẢ lượt, không ghi một nửa. */
$r = VHCC_NhanSu::dat_mang_bo_phan( array( 'ma_nv' => 'KT9', 'role' => 'Kế toán' ),
	'NV_2MANG', 'Máy tự động, Mảng Trên Trời', '' );
t( '🔴 một mảng lạ -> chối cả lượt', empty( $r['ok'] ), $r );
teq( 'và hồ sơ giữ nguyên, không ghi một nửa', 2, count(
	VHCC_NhanSu::mang_bo_phan_cua( VHCC_NhanSu::ho_so( 'NV_2MANG' ) )['dsMang'] ) );

/* Bỏ hết tích -> trôi lại theo cơ sở, và lại ra đủ hai mảng. */
$r = VHCC_NhanSu::dat_mang_bo_phan( array( 'ma_nv' => 'KT9', 'role' => 'Kế toán' ), 'NV_2MANG', '', '' );
t( 'bỏ hết tích được', ! empty( $r['ok'] ) && ! empty( $r['doi'] ), $r );
teq( '🔴 trôi lại theo cơ sở và ra đủ HAI mảng', 2,
	count( VHCC_NhanSu::mang_bo_phan_cua( VHCC_NhanSu::ho_so( 'NV_2MANG' ) )['dsMang'] ) );

/* --- (2) chỉ có cơ sở PHỤ, cơ sở chính trống --- */
$nv( 'NV_CHIPHU', 'Chỉ có cơ sở phụ', '' );
$wpdb->query( 'UPDATE ' . VHCC_DB::t( 'nhan_vien' )
	. " SET coso_phu='VIVO' WHERE ma_nv='NV_CHIPHU'" );
$x = VHCC_NhanSu::mang_bo_phan_cua( VHCC_NhanSu::ho_so( 'NV_CHIPHU' ) );
teq( '🔴 cơ sở chính trống mà có cơ sở phụ -> vẫn suy ra mảng', 'Khu vui chơi', $x['mang'] );
teq( '🔴 và vẫn vào khối cơ sở, không rơi vào "chưa xếp"', VHCC_NhanSu::BP_CO_SO, $x['boPhan'] );

/* --- cơ sở "chỉ QL" KHÔNG kéo mảng theo: quản một nơi khác mảng thì không vì thế mà đổi mảng --- */
$nv( 'NV_QL', 'Làm Vivo, quản Posh', 'VIVO' );
$wpdb->query( 'UPDATE ' . VHCC_DB::t( 'nhan_vien' )
	. " SET coso_phu='POSH_Q1', coso_ql='POSH_Q1' WHERE ma_nv='NV_QL'" );
$sm = VHCC_NhanSu::suy_mang( VHCC_NhanSu::ho_so( 'NV_QL' ) );
/* Quản một cơ sở mảng khác mà KHÔNG làm ở đó thì không vì thế mà thuộc mảng ấy —
   anh Thắng: "đối với quản lý 1 mảng thì mình không lo". */
teq( '🔴 cơ sở "chỉ QL" không kéo mảng theo', array( 'Khu vui chơi' ), $sm['dsMang'] );

/* Người CHỈ đi quản, không chấm ở đâu: vẫn phải có căn cứ chứ không bỏ trắng. */
$nv( 'NV_QLTHUAN', 'Chỉ đi quản', 'POSH_Q1' );
$wpdb->query( 'UPDATE ' . VHCC_DB::t( 'nhan_vien' )
	. " SET coso_ql='POSH_Q1' WHERE ma_nv='NV_QLTHUAN'" );
teq( 'người chỉ đi quản vẫn suy ra mảng của nơi mình quản',
	array( 'Máy tự động' ), VHCC_NhanSu::suy_mang( VHCC_NhanSu::ho_so( 'NV_QLTHUAN' ) )['dsMang'] );

/* --- (3) cơ sở chưa khai mảng: nói rõ cơ sở NÀO, chỉ đường đi khai --- */
$sm = VHCC_NhanSu::suy_mang( VHCC_NhanSu::ho_so( 'NV_LA' ) );
teq( 'cơ sở chưa khai mảng -> không suy được', array(), $sm['dsMang'] );
/* Tên hiện ra đã gỡ tiền tố 'CS_' (chuan_coso) — đó là tên người ta thấy ở mọi màn khác,
   nên câu giải thích phải dùng đúng tên ấy, không phải chuỗi thô trong sổ. */
t( '🔴 nhưng nói rõ CƠ SỞ NÀO chưa khai', strpos( $sm['vi'], 'LA_HOAC' ) !== false, $sm['vi'] );
t( 'và chỉ đường đi khai', strpos( $sm['vi'], 'Cấu hình' ) !== false, $sm['vi'] );

/* --- không cơ sở nào cả --- */
$sm = VHCC_NhanSu::suy_mang( VHCC_NhanSu::ho_so( 'NV_VP' ) );
t( 'không gắn cơ sở -> nói đúng là chưa gắn cơ sở',
	strpos( $sm['vi'], 'chưa gắn cơ sở' ) !== false, $sm['vi'] );

/* --- màn hình phải IN RA căn cứ ấy, không giữ trong bụng --- */
$src_ns = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-trang-ns.php' );
t( '🔴 ô Mảng in ra câu giải thích của suy_mang(), không chỉ in kết quả',
	strpos( $src_ns, "\$sm['vi']" ) !== false );
t( '🔴 ô Mảng là HỘP TÍCH nhiều mảng, không phải ô xổ một lựa chọn',
	strpos( $src_ns, "type=\"checkbox\" name=\"mbp_mang[" ) !== false );
t( 'ô không tích gì vẫn có nhãn nói nó suy ra gì', strpos( $src_ns, 'mb-suy' ) !== false );
t( '🔴 không còn cờ "lệch mảng" — làm hai mảng là chuyện thường',
	false === strpos( $src_ns, 'lệch mảng' ) );

/* ================================================================== 4. DANH SÁCH & CHỐT QUYỀN */

$ds_m = VHCC_NhanSu::ds_mang();
t( 'ds_mang dùng chung vốn từ với bo_phan_coso', in_array( 'Khu vui chơi', $ds_m, true )
	&& in_array( 'Máy tự động', $ds_m, true ) && in_array( 'Văn phòng', $ds_m, true ), $ds_m );
t( '🔴 "Chưa xếp" KHÔNG được lọt vào danh sách mảng',
	! in_array( VHCC_Luong::BP_CHUA_XEP, $ds_m, true ), $ds_m );

$ds_b = VHCC_NhanSu::ds_bo_phan();
t( 'ds_bo_phan có khối cơ sở', in_array( VHCC_NhanSu::BP_CO_SO, $ds_b, true ) );
t( 'và có phòng ban dựng sẵn', in_array( 'Phòng Kỹ Thuật', $ds_b, true )
	&& in_array( 'Tổng Giám Đốc (CEO)', $ds_b, true ), $ds_b );

/* Giá trị lạ còn sót trong sổ phải hiện ra ô xổ. Không thì mở hồ sơ ấy là ô tự nhảy về dòng đầu,
   bấm Lưu một cái là đổi bộ phận của người ta — đúng bẫy "Kế Toán MTD" của dat_vai_tro(). */
$nv( 'NV_SOT', 'Người mang giá trị sót', 'VIVO', '', 'Phòng Ma' );
t( '🔴 bộ phận lạ còn sót trong sổ vẫn có mặt trong ô xổ',
	in_array( 'Phòng Ma', VHCC_NhanSu::ds_bo_phan(), true ) );

/* --- chốt quyền: Kế toán trở lên, cùng bậc với đổi vai trò --- */
$nhan_vien = array( 'ma_nv' => 'NV_VIVO', 'role' => 'Nhân viên' );
$ke_toan   = array( 'ma_nv' => 'KT01', 'role' => 'Kế toán' );

$r = VHCC_NhanSu::dat_mang_bo_phan( $nhan_vien, 'NV_VP', 'Văn phòng', 'Phòng Nhân sự' );
t( '🔴 nhân viên KHÔNG đổi được mảng/bộ phận của người khác', empty( $r['ok'] ), $r );
teq( 'và hồ sơ ấy không hề đổi', '', $doc( 'NV_VP' )['mang'] );

$r = VHCC_NhanSu::dat_mang_bo_phan( $ke_toan, 'NV_VP', 'Văn phòng', 'Phòng Nhân sự' );
t( 'kế toán đổi được', ! empty( $r['ok'] ) && ! empty( $r['doi'] ), $r );
teq( 'ghi đúng mảng', 'Văn phòng', $doc( 'NV_VP' )['mang'] );
teq( 'ghi đúng bộ phận', 'Phòng Nhân sự', $doc( 'NV_VP' )['boPhan'] );

/* "Không đổi gì" xét TRƯỚC danh sách trắng — kẻo mỗi lượt Lưu bảng lại đẻ một dòng đỏ kêu oan
   cho một việc không xảy ra, và dòng đỏ kêu oan dạy người ta thôi đọc dòng đỏ. */
$r = VHCC_NhanSu::dat_mang_bo_phan( $ke_toan, 'NV_SOT', '', 'Phòng Ma' );
t( '🔴 gửi lên đúng giá trị đang có (kể cả giá trị sót) -> im lặng, không dòng đỏ',
	! empty( $r['ok'] ) && empty( $r['doi'] ), $r );

$r = VHCC_NhanSu::dat_mang_bo_phan( $ke_toan, 'NV_VP', 'Mảng Trên Trời', '' );
t( '🔴 mảng lạ bị chối', empty( $r['ok'] ), $r );
t( 'và câu chối nói rõ mã NV nào', strpos( (string) $r['error'], 'NV_VP' ) !== false, $r['error'] );
teq( 'hồ sơ giữ nguyên mảng cũ', 'Văn phòng', $doc( 'NV_VP' )['mang'] );

/* Trả về "theo cơ sở" là XOÁ phần khai tay — phải làm được, không thì khai nhầm một lần là kẹt. */
$r = VHCC_NhanSu::dat_mang_bo_phan( $ke_toan, 'NV_KIEM', '', '' );
t( 'trả về «theo cơ sở» được', ! empty( $r['ok'] ) && ! empty( $r['doi'] ), $r );
$x = $doc( 'NV_KIEM' );
teq( '🔴 và người ấy trôi lại theo cơ sở', 'Khu vui chơi', $x['mang'] );
t( 'được tính là đang trôi theo cơ sở trở lại', ! empty( $x['theoCoSo'] ) );

/* ================================================================== 5. DẢI ĐẾM */

$ds = VHCC_NhanSu::ds_nhan_vien( array( 'ma_nv' => 'AD', 'role' => 'Admin' ) );
$dem = VHCC_NhanSu::dem_mang_bo_phan( $ds );
$tong = 0; foreach ( $dem['mang'] as $so ) { $tong += $so; }
/* 🔴 BẤT BIẾN ĐÚNG SAU 3.69.0: tổng trục Mảng LỚN HƠN số người đúng bằng số lượt "thuộc thêm một
   mảng nữa". Người làm hai mảng có mặt ở CẢ HAI ô — đó chính là câu hỏi người ta hỏi dải này
   ("mảng Khu vui chơi có bao nhiêu người"), và màn hình nói rõ tổng sẽ lớn hơn số người.
   Ghim bằng một phép cộng chứ không ghim số cứng: bỏ sót một người thì con số hụt, mà đếm nhầm
   một người vào mảng họ không thuộc thì con số dôi — cả hai đều đỏ. */
$dôi = 0;
foreach ( $ds as $r_d ) {
	$n_d = count( VHCC_NhanSu::mang_bo_phan_cua( $r_d )['dsMang'] );
	$dôi += ( $n_d > 1 ) ? ( $n_d - 1 ) : 0;
}
teq( '🔴 tổng trục Mảng = số người + số lượt thuộc thêm mảng — không sót, không dôi',
	count( $ds ) + $dôi, $tong );
t( 'và có người thật sự thuộc nhiều mảng trong mẫu (kẻo phép trên thành vô nghĩa)', $dôi > 0, $dôi );
teq( 'dải đếm khai đúng số người nhiều mảng', $dôi > 0, ! empty( $dem['nhieuMang'] ) );
$tong_b = 0; foreach ( $dem['boPhan'] as $so ) { $tong_b += $so; }
teq( 'trục bộ phận cũng vậy', count( $ds ), $tong_b );
t( 'người chưa xếp được gom vào một ô riêng, không biến mất',
	isset( $dem['mang']['— chưa xếp —'] ), $dem['mang'] );

/* ⚠️ Đếm trên DANH SÁCH ĐƯA VÀO, không tự truy vấn cả sổ: cửa hàng trưởng đọc được tổng cả chuỗi
   là một chỗ rò đúng thứ mà bảng bên dưới đang giấu. */
$dem_hep = VHCC_NhanSu::dem_mang_bo_phan( array( VHCC_NhanSu::ho_so( 'NV_VIVO' ) ) );
$t_hep = 0; foreach ( $dem_hep['mang'] as $so ) { $t_hep += $so; }
teq( '🔴 đếm theo đúng phạm vi được đưa vào, không quét cả sổ', 1, $t_hep );

/* ================================================================== 6. SỔ & MÀN HÌNH */

$so_do = VHCC_DB::bang();
t( '🔴 nhan_vien có cột mang', strpos( $so_do['nhan_vien'], 'mang VARCHAR(120)' ) !== false );
t( 'và cột bo_phan', strpos( $so_do['nhan_vien'], 'bo_phan VARCHAR(120)' ) !== false );
t( 'cả hai đều có KEY để lọc cho nhanh',
	strpos( $so_do['nhan_vien'], 'KEY mang (mang)' ) !== false
	&& strpos( $so_do['nhan_vien'], 'KEY bo_phan (bo_phan)' ) !== false );

/* 🔴 Không tăng SCHEMA_VERSION là cột không bao giờ mọc ra trên site ĐANG CHẠY — bảng mới chỉ
   dựng đúng cho site cài mới, nên lỗi này rất dễ lọt qua mọi bài thử chạy trên sổ trắng. */
t( '🔴 SCHEMA_VERSION đã tăng', '2.10.0' === VHCC_DB::SCHEMA_VERSION, VHCC_DB::SCHEMA_VERSION );

$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-trang-ns.php' );
t( 'bảng có cột Mảng kinh doanh', strpos( $src, 'Mảng kinh doanh' ) !== false );
t( 'thanh lọc có ô mảng và ô bộ phận',
	strpos( $src, "'nmang'" ) !== false && strpos( $src, "'nbp'" ) !== false );
t( '🔴 hai tham số lọc mới được giữ khi lật trang (nếu không, lật sang trang 2 là mất lọc)',
	in_array( 'nmang', VHCC_TrangNS::THAM_SO, true ) && in_array( 'nbp', VHCC_TrangNS::THAM_SO, true ),
	VHCC_TrangNS::THAM_SO );

/* Điều động hàng loạt phải đi qua ĐÚNG hàm có chốt quyền — không có cửa hậu ghi thẳng SQL. */
t( '🔴 điều động cả cột đi qua dat_mang_bo_phan(), không có UPDATE tắt',
	substr_count( $src, 'VHCC_NhanSu::dat_mang_bo_phan(' ) === 2
	&& false === strpos( $src, "UPDATE ' . VHCC_DB::t( 'nhan_vien' )" ),
	substr_count( $src, 'VHCC_NhanSu::dat_mang_bo_phan(' ) );

/* 🔴 Chốt "mo · khoa · (trống)" của cột quyền phải được xét SAU nhánh mảng/bộ phận. Trước là mọi
   lượt điều động ăn một câu chối nói về một cột khác hẳn, và người bấm không đời nào đoán ra. */
t( '🔴 nhánh mảng/bộ phận xét TRƯỚC chốt "mo · khoa"',
	strpos( $src, "'mbp_mang' === \$trang" ) < strpos( $src, "'Chỉ nhận: mo · khoa · (trống).'" ) );

/* Lưu mảng/bộ phận phải chạy SAU chuyển cơ sở: mảng suy ra TỪ cơ sở, nên ai vừa được chuyển
   trong cùng lượt phải được xét theo cơ sở MỚI. */
t( '🔴 luu_mang_bp() gọi sau luu_coso()',
	strpos( $src, '$cs  = self::luu_coso( $toi );' ) < strpos( $src, '$mbp = self::luu_mang_bp( $toi );' ) );

/* ------------------------------------------------------------------ kết luận */
echo "\n";
if ( $truot ) {
	echo '✗ TRƯỢT ' . count( $truot ) . ' / ' . ( $dat + count( $truot ) ) . ":\n";
	foreach ( $truot as $x ) { echo '    · ' . $x . "\n"; }
	exit( 1 );
}
echo "✓ ĐẠT — $dat phép: mảng & bộ phận gắn được, trôi theo cơ sở đúng, điều động không có cửa hậu.\n";
