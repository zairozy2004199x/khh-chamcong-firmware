<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐẨY NHÂN SỰ SANG MỘT BẢN CHI PHÍ — VÀ KHÔNG ĐƯỢC ĐỤNG SANG BẢN NÀO KHÁC.
 *
 * Anh Thắng 15/09/2026: *"Tạo tab lệnh để đẩy dữ liệu nv sang 1 trang chi phí văn phòng trước"*.
 *
 * ==============================================================================================
 * 🔴 LỖI TỆ NHẤT MÀ BÀI NÀY CANH: ĐẨY SANG VP MÀ GHI NHẦM VÀO SỔ CỦA KHU VUI CHƠI.
 *
 *    Bốn bản chi phí chạy song song trên cùng một WordPress, mỗi bản một sổ người dùng riêng.
 *    Lớp đẩy dùng chung một thân (`VHCC_DayChiPhi`), chỉ khác năm hàm bộ nối. Sót một hàm chưa
 *    viết lại là lượt đẩy "sang Văn phòng" chạy xong, báo thành công — mà người ấy lại xuất hiện
 *    trong sổ khu vui chơi, tức được trao chìa khoá vào MỘT MÀN CÓ NGĂN TIỀN mà không ai định.
 *    Không có câu lỗi nào, ở cả hai bên.
 *
 * ⚠️ BẢN VP DÙNG LỚP GIẢ CÓ GHI SỔ, cố ý. Thứ đang thử là CÂY CẦU, và hợp đồng của đầu bên kia
 *    chỉ gồm read/write. Lớp giả cho phép hỏi thẳng câu quan trọng nhất — "hàng rơi vào sổ nào"
 *    — mà không phải dựng cả plugin thứ hai. Còn sổ khu vui chơi thì là PLUGIN THẬT, vì đó mới
 *    là cái phải chứng minh là KHÔNG bị đụng.
 *
 * Chạy: php tools/test/kiem-day-chi-phi-vung.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';

/* 🔴 LỚP GIẢ PHẢI KHAI TRƯỚC KHI NẠP PLUGIN. `VHCC_DayChiPhiVP::co_he()` hỏi `class_exists`
   ngay lúc chạy; khai sau thì mọi phép dưới đây rơi vào nhánh "chưa cài" và bài xanh vì không
   thử được gì — đúng kiểu bài kiểm nói dối. */
class VHCPVP_Cfg {
	const USER = 'CH_NguoiDung';
	const BO_PHAN_DS = array( 'Cơ sở', 'Văn phòng', 'Kỹ thuật', 'Marketing', 'Công tác', 'Setup' );
	public static $so = array();
	public static function read( $bang ) {
		return isset( self::$so[ $bang ] ) ? self::$so[ $bang ] : array();
	}
	public static function write( $bang, $rows ) { self::$so[ $bang ] = $rows; }
	public static function bo_phan_chuan( $x ) {
		$x = trim( (string) $x );
		foreach ( self::BO_PHAN_DS as $b ) { if ( 0 === strcasecmp( $b, $x ) ) { return $b; } }
		return '';
	}
}

/* Bản Máy tự động — lớp giả thứ hai. Có HAI đích mới thử được câu "đẩy sang bản này thì bản
   kia không đụng gì", mà đó là cả lý do tệp này tồn tại. */
class VHCPMTD_Cfg {
	const USER = 'CH_NguoiDung';
	const BO_PHAN_DS = array( 'Cơ sở', 'Văn phòng', 'Kỹ thuật', 'Marketing', 'Công tác', 'Setup' );
	public static $so = array();
	public static function read( $bang ) {
		return isset( self::$so[ $bang ] ) ? self::$so[ $bang ] : array();
	}
	public static function write( $bang, $rows ) { self::$so[ $bang ] = $rows; }
	public static function bo_phan_chuan( $x ) {
		$x = trim( (string) $x );
		foreach ( self::BO_PHAN_DS as $b ) { if ( 0 === strcasecmp( $b, $x ) ) { return $b; } }
		return '';
	}
}

vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/** Tìm một người trong một sổ người dùng, theo tên. */
function hang_ten( $rows, $ten ) {
	foreach ( (array) $rows as $r ) {
		$r = (array) $r;
		if ( isset( $r[0] ) && 0 === strcasecmp( trim( (string) $r[0] ), $ten ) ) { return $r; }
	}
	return null;
}

$ad = array( 'name' => 'Sếp', 'ma_nv' => 'AD1', 'role' => 'Admin' );

/* ═══ 1. HAI LỚP, HAI ĐÍCH, HAI SỔ "ĐÃ ĐẨY" ════════════════════════════════════ */
t( '🔴 có lớp đẩy sang bản Văn phòng', class_exists( 'VHCC_DayChiPhiVP' ) );
t( '🔴 nó KẾ THỪA thân chung, không chép lại 500 dòng luật',
	is_subclass_of( 'VHCC_DayChiPhiVP', 'VHCC_DayChiPhi' ) );
t( '🔴 cột trên bảng phải KHÁC cột của bản khu vui chơi',
	VHCC_DayChiPhiVP::COT !== VHCC_DayChiPhi::COT,
	VHCC_DayChiPhiVP::COT . ' vs ' . VHCC_DayChiPhi::COT );
/* ⚠️ Sổ "đã đẩy" dùng chung là gỡ bên này thì bên kia mất dấu, và bảng bày sai cả hai cột. */
t( '🔴 sổ "đã đẩy" cũng phải riêng',
	VHCC_DayChiPhiVP::O_DA_DAY !== VHCC_DayChiPhi::O_DA_DAY,
	VHCC_DayChiPhiVP::O_DA_DAY );
t( 'tên hệ nói đúng bản nào — câu báo cho người đọc',
	false !== mb_stripos( VHCC_DayChiPhiVP::ten_he(), 'Văn phòng' ), VHCC_DayChiPhiVP::ten_he() );
t( 'dò thấy bản VP đang có mặt', VHCC_DayChiPhiVP::co_he() );

/* ═══ 2. ĐẨY MỘT NGƯỜI SANG VP ═════════════════════════════════════════════════ */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'VP01', 'ho_ten' => 'Chị Văn Phòng',
	'cua_hang' => 'VĂN PHÒNG HCM', 'chuc_vu' => 'Kế toán',
	'pin_dang_nhap' => '4455', 'vai_tro' => 'Nhân viên' ) );

$truoc_kvc = VHCP_Cfg::read( VHCP_Cfg::USER );
$kq = VHCC_DayChiPhiVP::dat( $ad, 'VP01', 'mo' );
t( 'đẩy chạy được', ! empty( $kq['ok'] ), $kq );
teq( 'đúng một hàng đổi', 1, (int) $kq['doi'] );

$hang_vp = hang_ten( VHCPVP_Cfg::read( VHCPVP_Cfg::USER ), 'Chị Văn Phòng' );
t( '🔴 hàng nằm trong sổ của bản VĂN PHÒNG', is_array( $hang_vp ), VHCPVP_Cfg::$so );
teq( '   mang đúng PIN chấm công', '4455', (string) $hang_vp[1] );
teq( '   và mang Mã NV — sợi dây nối hai bên', 'VP01', (string) $hang_vp[9] );

/* 🔴 PHÉP QUAN TRỌNG NHẤT CỦA CẢ TỆP. */
t( '🔴 sổ của bản KHU VUI CHƠI KHÔNG hề bị đụng',
	VHCP_Cfg::read( VHCP_Cfg::USER ) === $truoc_kvc, VHCP_Cfg::read( VHCP_Cfg::USER ) );
t( '🔴 và người ấy KHÔNG có mặt bên khu vui chơi',
	null === hang_ten( VHCP_Cfg::read( VHCP_Cfg::USER ), 'Chị Văn Phòng' ) );
t( '⚠️ hai sổ "đã đẩy" độc lập: VP có, khu vui chơi không',
	VHCC_DayChiPhiVP::da_day( 'VP01' ) && ! VHCC_DayChiPhi::da_day( 'VP01' ) );

/* ═══ 3. GỠ CHỈ GỠ BÊN VP ══════════════════════════════════════════════════════ */
$kq = VHCC_DayChiPhiVP::dat( $ad, 'VP01', '' );
t( 'gỡ chạy được', ! empty( $kq['ok'] ), $kq );
t( '🔴 gỡ xong hàng biến khỏi sổ VP',
	null === hang_ten( VHCPVP_Cfg::read( VHCPVP_Cfg::USER ), 'Chị Văn Phòng' ) );
t( '   và sổ khu vui chơi vẫn y nguyên', VHCP_Cfg::read( VHCP_Cfg::USER ) === $truoc_kvc );

/* ═══ 4. KHÔNG CÓ PIN THÌ CHỐI, KHÔNG ĐẨY BỪA ══════════════════════════════════
 * Người không PIN mà lọt sang là một hàng chết trong sổ bên kia: chiếm chỗ, trông như đã cấp
 * quyền, mà không ai đăng nhập được bằng nó. */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'VP02', 'ho_ten' => 'Anh Chưa PIN',
	'cua_hang' => 'VĂN PHÒNG HCM', 'pin_dang_nhap' => '', 'vai_tro' => 'Nhân viên' ) );
$kq = VHCC_DayChiPhiVP::dat( $ad, 'VP02', 'mo' );
t( '🔴 chưa có PIN -> CHỐI, không đẩy', empty( $kq['ok'] ), $kq );
t( '   và nói rõ phải đi cấp PIN ở đâu',
	false !== mb_stripos( (string) $kq['error'], 'PIN' ), $kq );

/* ═══ 5. KHÔNG PHẢI ADMIN THÌ KHÔNG ĐẨY ĐƯỢC ═══════════════════════════════════
 * 🔴 Cùng bậc với đẩy sang bản khu vui chơi, và vì cùng một lý do: màn chi phí có ngăn TIỀN,
 *    còn PIN đẩy sang là PIN chấm công dùng chung. */
$nv = array( 'name' => 'Nhân viên', 'ma_nv' => 'NV9', 'role' => 'Nhân viên' );
$kq = VHCC_DayChiPhiVP::dat( $nv, 'VP01', 'mo' );
t( '🔴 vai Nhân viên bị chối', empty( $kq['ok'] ), $kq );
t( '   và câu chối gọi đúng tên bản VP',
	false !== mb_stripos( (string) $kq['error'], 'Văn phòng' ), $kq );

/* ═══ 6. BỘ NỐI: NĂM HÀM, MỖI HÀM TỰ GÁC ═══════════════════════════════════════
 * ⚠️ Luật của `tools/test/kiem-goi-cheo.php`, sinh ra sau một lần trắng cả trang (23/08/2026):
 *    lớp CÓ mà hàm KHÔNG, vì hai plugin cài độc lập nên bản có thể lệch nhau bất cứ lúc nào. */
$ma_vp = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-day-chi-phi-vp.php' );
/**
 * Thân MỘT hàm, cắt tới dấu đóng của chính nó.
 *
 * 🔴 KHÔNG CẮT BẰNG SỐ KÝ TỰ CỐ ĐỊNH. Bản nháp lấy 420 ký tự sau tên hàm: `doc_user()` chỉ dài
 *    chừng 200, nên cửa sổ tràn sang `ghi_user()` ngay dưới — và `ghi_user()` cũng có
 *    `method_exists`. Gỡ SẠCH gác của `doc_user()` mà bài vẫn xanh, vì nó đọc trúng gác của
 *    hàm bên cạnh. Phép kiểm nói dối đúng chỗ nó phải canh.
 */
function than_ham_vp( $ma, $ten ) {
	$i = mb_strpos( $ma, 'function ' . $ten . '(' );
	if ( false === $i ) { return ''; }
	$j = mb_strpos( $ma, "\n\t}", $i );
	return ( false === $j ) ? '' : mb_substr( $ma, $i, $j - $i );
}
foreach ( array( 'co_he', 'doc_user', 'ghi_user', 'bp_chuan', 'bp_ds' ) as $h ) {
	$than = than_ham_vp( $ma_vp, $h );
	t( '🔴 bộ nối «' . $h . '» có viết lại ở bản VP', '' !== $than, $h );
	t( '   và trỏ sang VHCPVP_Cfg', false !== mb_strpos( $than, 'VHCPVP_Cfg' ), $h );
	/* `bp_ds` đọc HẰNG nên gác bằng `defined`, bốn hàm kia gọi HÀM nên gác `method_exists`. */
	$gac = ( 'bp_ds' === $h ) ? 'defined(' : 'method_exists(';
	t( '   và tự gác ' . $gac . ') trong thân nó', false !== mb_strpos( $than, $gac ), $than );
}
/* 🔴 BẢN VP KHÔNG ĐƯỢC NHẮC TÊN LỚP CỦA BẢN KHU VUI CHƠI Ở BẤT KỲ ĐÂU. Sót một chữ `VHCP_Cfg`
   trong tệp này là một đường ghi nhầm sổ — đúng cái lỗi cả bài này sinh ra để dẹp. */
t( '🔴 tệp bản VP KHÔNG nhắc VHCP_Cfg ở đâu cả',
	! preg_match( '/\bVHCP_Cfg\b/', preg_replace( '#/\*[\s\S]*?\*/#', ' ', $ma_vp ) ) );

/* ═══ 7. THÂN CHUNG KHÔNG CÒN GỌI THẲNG SANG APP NÀO ═══════════════════════════
 * Ngoài năm hàm bộ nối, thân lớp cha phải sạch tên lớp bên kia — không thì bản con viết lại bộ
 * nối mà vẫn có đường rò về sổ cũ. */
$ma_cha = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-day-chi-phi.php' );
$sach   = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $ma_cha );
$i_noi  = mb_strpos( $sach, 'function ten_he(' );
$i_het  = mb_strpos( $sach, 'function bp_ds(' );
$ngoai  = mb_substr( $sach, 0, $i_noi ) . mb_substr( $sach, $i_het + 600 );
t( '🔴 thân lớp cha (ngoài bộ nối) KHÔNG gọi thẳng VHCP_Cfg',
	! preg_match( '/VHCP_Cfg::/', $ngoai ),
	preg_match( '/.*VHCP_Cfg::.*/', $ngoai, $m_n ) ? $m_n[0] : '' );

/* ═══ 8. BẢN MÁY TỰ ĐỘNG — anh Thắng 15/09/2026: *"làm tab máy tự động luôn nhé"* ═══
 *
 * 🔴 BA ĐÍCH, BA SỔ. Đây là chỗ một lớp chép-dán sẽ lộ ra: sót một hàm bộ nối là người đẩy
 *    "sang Máy tự động" rơi vào sổ Văn phòng hoặc sổ khu vui chơi, báo thành công như thường. */
t( '🔴 có lớp đẩy sang bản Máy tự động', class_exists( 'VHCC_DayChiPhiMTD' ) );
t( '   cũng kế thừa thân chung', is_subclass_of( 'VHCC_DayChiPhiMTD', 'VHCC_DayChiPhi' ) );
t( '🔴 ba cột khác nhau đôi một', 3 === count( array_unique( array(
	VHCC_DayChiPhi::COT, VHCC_DayChiPhiVP::COT, VHCC_DayChiPhiMTD::COT ) ) ) );
t( '🔴 ba sổ "đã đẩy" khác nhau đôi một', 3 === count( array_unique( array(
	VHCC_DayChiPhi::O_DA_DAY, VHCC_DayChiPhiVP::O_DA_DAY, VHCC_DayChiPhiMTD::O_DA_DAY ) ) ) );

VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'MT01', 'ho_ten' => 'Anh Máy',
	'cua_hang' => 'AEON MALL BÌNH DƯƠNG', 'pin_dang_nhap' => '6060', 'vai_tro' => 'Nhân viên' ) );
$vp_truoc  = VHCPVP_Cfg::read( VHCPVP_Cfg::USER );
$kvc_truoc = VHCP_Cfg::read( VHCP_Cfg::USER );
$kq = VHCC_DayChiPhiMTD::dat( $ad, 'MT01', 'mo' );
t( 'đẩy sang MTD chạy được', ! empty( $kq['ok'] ), $kq );
t( '🔴 hàng nằm trong sổ của bản MÁY TỰ ĐỘNG',
	null !== hang_ten( VHCPMTD_Cfg::read( VHCPMTD_Cfg::USER ), 'Anh Máy' ), VHCPMTD_Cfg::$so );
t( '🔴 sổ bản VĂN PHÒNG không bị đụng', VHCPVP_Cfg::read( VHCPVP_Cfg::USER ) === $vp_truoc );
t( '🔴 sổ bản KHU VUI CHƠI không bị đụng', VHCP_Cfg::read( VHCP_Cfg::USER ) === $kvc_truoc );
t( '⚠️ và ba sổ "đã đẩy" độc lập',
	VHCC_DayChiPhiMTD::da_day( 'MT01' )
	&& ! VHCC_DayChiPhiVP::da_day( 'MT01' )
	&& ! VHCC_DayChiPhi::da_day( 'MT01' ) );

$ma_mtd = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-day-chi-phi-mtd.php' );
foreach ( array( 'co_he', 'doc_user', 'ghi_user', 'bp_chuan', 'bp_ds' ) as $h ) {
	$than = than_ham_vp( $ma_mtd, $h );
	t( '🔴 bản MTD có viết lại bộ nối «' . $h . '»', '' !== $than, $h );
	t( '   và trỏ sang VHCPMTD_Cfg', false !== mb_strpos( $than, 'VHCPMTD_Cfg' ), $h );
}
/* 🔴 KHÔNG ĐƯỢC SÓT TÊN LỚP CỦA BẢN KHÁC — chép tệp VP ra rồi quên đổi một chữ là ghi nhầm sổ. */
$mtd_sach = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $ma_mtd );
t( '🔴 tệp MTD không nhắc VHCPVP_Cfg', ! preg_match( '/\bVHCPVP_Cfg\b/', $mtd_sach ) );
t( '🔴 tệp MTD không nhắc VHCP_Cfg',   ! preg_match( '/\bVHCP_Cfg\b/', $mtd_sach ) );

/* ═══ 9. MÀN: TAB PHẢI HIỆN CẢ KHI BẢN ĐÍCH CHƯA CÀI ═══════════════════════════
 *
 * 🔴 LỖI NGÀY 15/09/2026, EM TỰ GÂY RA. Bản trước giấu hẳn tab khi `co_he()` trả false, lý do
 *    nghe rất hợp: "một tab bấm vào chỉ để đọc câu chưa cài là thứ người ta bấm lại vào tuần
 *    sau". Anh Thắng cài bản chấm công mới, mở trang, và nhắn *"Chưa thấy tab đẩy"* — vì bản
 *    Chi phí Văn phòng chưa cài. Màn KHÔNG NÓI GÌ, nên không cách nào biết thiếu cái gì: y hệt
 *    tính năng chưa làm, hay làm hỏng.
 *
 *    Giấu một thứ vì nó chưa dùng được chỉ đúng khi người dùng KHÔNG ĐANG TÌM nó. */
$ns = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-trang-ns.php' );
$than_dai = than_ham_vp( $ns, 'dai_tab_ns' );
t( 'bốc được hàm vẽ dải tab', '' !== $than_dai );
/* 🔴 DÒ THEO TÍNH CHẤT, KHÔNG DÒ TÊN HÀM CŨ. Bản nháp của phép này chỉ đòi "có gọi
   co_quyen_day" và "không còn chữ co_tab_day_vp" — chèn thêm một cửa
   `if ( ! ...::co_he() ) { return; }` ngay dưới là tab giấu lại y như cũ mà bài VẪN XANH.
   Tính chất thật cần giữ: dải tab chỉ có ĐÚNG MỘT đường thoát sớm, và nó hỏi QUYỀN. */
t( '🔴 dải tab hỏi QUYỀN, không hỏi "đã cài chưa"',
	false !== mb_strpos( $than_dai, 'co_quyen_day( $toi )' ), $than_dai );
t( '🔴 và chỉ có ĐÚNG MỘT đường thoát sớm — thêm cửa nào nữa là tab lại biến mất',
	1 === preg_match_all( '/\breturn\b/', $than_dai ), $than_dai );
t( '🔴 tab chưa cài được thì tự nói ra ngay trên nhãn',
	false !== mb_strpos( $than_dai, 'chưa cài' ), $than_dai );
/* ⚠️ Dải tab dựng TỪ BẢNG KHAI, không gõ tay tên từng bản — thêm bản là thêm một dòng. */
t( '⚠️ dải tab duyệt bảng khai, không gõ tay tên bản',
	false !== mb_strpos( $than_dai, 'ds_dich_day()' ), $than_dai );
$than_ds = than_ham_vp( $ns, 'ds_dich_day' );
t( '🔴 bảng khai có đủ cả hai bản',
	false !== mb_strpos( $than_ds, 'VHCC_DayChiPhiVP' )
	&& false !== mb_strpos( $than_ds, 'VHCC_DayChiPhiMTD' ), $than_ds );
/* 🔴 ĐÍCH LẤY TỪ Ô `nman` CỦA CHÍNH BIỂU MẪU. Đọc chỗ khác là có ngày bấm ở tab Máy tự động mà
   người rơi sang sổ Văn phòng — sai sổ thì không có gì báo. */
$than_luu = than_ham_vp( $ns, 'viec_day_vp' );
t( '🔴 hàm lưu lấy đích từ ô nman của biểu mẫu',
	false !== mb_strpos( $than_luu, "\$_POST['nman']" ), $than_luu );
t( '🔴 và KHÔNG gõ cứng lớp nào',
	! preg_match( '/VHCC_DayChiPhi(VP|MTD)::/', $than_luu ), $than_luu );

/* ═══════════════════════════════════════════════════════════════════════════════
 * Khối báo trượt đứng CUỐI CÙNG — thêm mục mới thì thêm Ở TRÊN chỗ này.
 * ═══════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: ba bản chi phí, ba sổ riêng, không bản nào đụng sổ bản nào.\n";
