<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * BỘ PHẬN KHAI LUỒNG MẶC ĐỊNH — NGƯỜI LẬP VẪN ĐỔI ĐƯỢC TRÊN TỪNG ĐƠN
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 *
 * Anh Thắng 22/09/2026: *"Hoặc bộ phận sẽ chọn phương án duyệt chi"*. Hỏi lại thì anh chốt:
 * **bộ phận khai một lần, đơn vẫn sửa được**, và lấy **bộ phận của NGƯỜI LẬP**.
 *
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * 🔴 CÁI HỎNG NGUY NHẤT: ĐƠN ĐANG CHẠY DỞ BỊ ĐỔI LUỒNG GIỮA CHỪNG
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * Nếu đơn TRA LẠI bảng bộ phận mỗi lượt đọc thì một lượt sửa danh mục là hàng trăm đơn đổi
 * đường cùng lúc: đơn đã duyệt chi một nửa bỗng hiện ra ở một luồng không có bước ấy, thanh
 * bước không biết vẽ nó ở đâu, và không đường nào đi tiếp. Nên luồng phải được ĐÓNG DẤU vào
 * cột `don.luong` NGAY LÚC LẬP, và bảng bộ phận chỉ là giá trị MỒI.
 *
 * Mục 4 canh đúng chỗ ấy: lập đơn xong mới đổi danh mục, rồi đòi đơn cũ giữ nguyên luồng.
 *
 * 🔴 VÀ CÁI HỎNG ÂM THẦM NHẤT: Ô CHỌN TRÊN MÀN THÀNH VÔ NGHĨA
 * Đảo thứ tự hai vế trong `create_don()` — lấy mặc định của bộ phận TRƯỚC rồi mới xét mã màn
 * gửi — thì người lập bấm gì cũng bị ghi đè, mà không một câu báo nào. Mục 3 canh chỗ đó.
 *
 * ⚠️ CHẠY THẬT `VHCP_Cfg` + `VHCP_Don` với CSDL giả, không bóc thân hàm ra eval.
 *
 * Chạy: php tools/test/kiem-luong-theo-bo-phan.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

/* ═══ 0. BỆ ĐỠ — hai người, hai bộ phận khác nhau ════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::BP, array(
	array( 'Văn phòng',   'dc' ),
	array( 'Marketing',   'tt' ),
	array( 'Máy tự động', ''   ),   // khai tên mà KHÔNG khai luồng
	array( 'Cơ sở',       ''   ),
), false );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	//     tên            pin    vai        cơ sở  tkCó  mãĐT  BỘ PHẬN
	array( 'Chị Văn Phòng', '1', 'Nhân viên', '', '', '', 'Văn phòng' ),
	array( 'Anh Mkt',       '2', 'Nhân viên', '', '', '', 'Marketing' ),
	array( 'Anh MTĐ',       '3', 'Nhân viên', '', '', '', 'Máy tự động' ),
	array( 'Người Vô Danh', '4', 'Nhân viên', '', '', '', '' ),
), false );
VHCP_Cfg::clear_cache();

/* ═══ 1. ĐỌC LUỒNG CỦA MỘT BỘ PHẬN ══════════════════════════════════════════════════════ */
teq( '🔴 bộ phận khai "dc" → đọc ra "dc"', 'dc', VHCP_Cfg::luong_cua_bo_phan( 'Văn phòng' ) );
teq( '   khai "tt" → đọc ra "tt"',         'tt', VHCP_Cfg::luong_cua_bo_phan( 'Marketing' ) );
/* 🔴 TRỐNG = KHÔNG ÉP GÌ. Bảy bộ phận đang khai đều trống; hiểu thành một mã là đổi luồng cho
   cả một bộ phận mà không ai yêu cầu. */
teq( '🔴 khai tên mà KHÔNG khai luồng → rỗng (theo khối như cũ)', '', VHCP_Cfg::luong_cua_bo_phan( 'Máy tự động' ) );
teq( '   tên bộ phận LẠ → rỗng, không nhận bừa', '', VHCP_Cfg::luong_cua_bo_phan( 'Bộ phận ma' ) );
teq( '   rỗng → rỗng',                            '', VHCP_Cfg::luong_cua_bo_phan( '' ) );
teq( '⚠️ không phân biệt hoa thường (đi qua `bo_phan_chuan`)', 'dc', VHCP_Cfg::luong_cua_bo_phan( 'VĂN PHÒNG' ) );

/* ⚠️ MÃ LẠ TRONG SỔ CŨNG VỀ RỖNG — `VHCP_Don::luong_don()` là nơi DUY NHẤT biết mã nào có
   thật. Gõ lại danh sách mã ở `VHCP_Cfg` là hai nơi có thể lệch nhau. */
VHCP_Cfg::write( VHCP_Cfg::BP, array( array( 'Văn phòng', 'xyz' ) ), false );
VHCP_Cfg::clear_cache();
teq( '🔴 mã luồng LẠ trong danh mục → rỗng, không đẻ luồng thứ tư', '', VHCP_Cfg::luong_cua_bo_phan( 'Văn phòng' ) );

/* Trả bảng về như cũ cho các mục sau. */
VHCP_Cfg::write( VHCP_Cfg::BP, array(
	array( 'Văn phòng', 'dc' ), array( 'Marketing', 'tt' ),
	array( 'Máy tự động', '' ), array( 'Cơ sở', '' ),
), false );
VHCP_Cfg::clear_cache();

/* ═══ 2. BỘ PHẬN CỦA NGƯỜI — ĐỌC Ở HÀNG NGƯỜI DÙNG ═════════════════════════════════════ */
/* 🔴 TÊN HÀM KHÔNG PHẢI `bo_phan_cua_nguoi`, VÀ ĐÓ LÀ CHỦ Ý. Cái tên ấy thuộc về một hàm CŨ đã
   bỏ hẳn 21/09/2026 — nó lấy bộ phận từ VAI TRÒ, tức trục thứ hai, đúng cái anh Thắng gọi là
   *"set cái này thì mất cái kia"*. `kiem-vai-bo-bo-phan.php` chốt cái tên ấy không được sống
   lại; hàm này đọc ngược hẳn nên phải mang tên khác. */
t( '🔴 tên cũ `bo_phan_cua_nguoi()` VẪN không được sống lại',
	! method_exists( 'VHCP_Cfg', 'bo_phan_cua_nguoi' ), null );
teq( '🔴 đọc đúng bộ phận ở hàng Người dùng', 'Văn phòng', VHCP_Cfg::bo_phan_hang_nguoi( 'Chị Văn Phòng' ) );
teq( '   người khác, bộ phận khác',           'Marketing', VHCP_Cfg::bo_phan_hang_nguoi( 'Anh Mkt' ) );
teq( '   người chưa khai bộ phận → rỗng',     '',          VHCP_Cfg::bo_phan_hang_nguoi( 'Người Vô Danh' ) );
teq( '   tên không có trong bảng → rỗng',     '',          VHCP_Cfg::bo_phan_hang_nguoi( 'Ai Đó' ) );
teq( '   rỗng → rỗng',                        '',          VHCP_Cfg::bo_phan_hang_nguoi( '' ) );

/* Ghép hai cái trên. */
teq( '🔴 `luong_mac_dinh()` — người Văn phòng → "dc"', 'dc', VHCP_Don::luong_mac_dinh( 'Chị Văn Phòng' ) );
teq( '   người Marketing → "tt"',                      'tt', VHCP_Don::luong_mac_dinh( 'Anh Mkt' ) );
teq( '🔴 bộ phận chưa khai luồng → rỗng (theo khối)',  '',   VHCP_Don::luong_mac_dinh( 'Anh MTĐ' ) );
teq( '   người chưa khai bộ phận → rỗng',              '',   VHCP_Don::luong_mac_dinh( 'Người Vô Danh' ) );
teq( '   tên lạ → rỗng, không nổ',                     '',   VHCP_Don::luong_mac_dinh( 'Ai Đó' ) );

/* ═══ 3. 🔴 LẬP ĐƠN — MÃ MÀN GỬI THẮNG MẶC ĐỊNH CỦA BỘ PHẬN ════════════════════════════ */
function _luong_cua_don( $ma ) {
	$d = VHCP_Don::don_row( $ma );
	return $d ? (string) $d['luong'] : '(không có đơn)';
}
function _lap( $nguoi, $luong ) {
	$r = VHCP_Don::create_don( 'T9/2026', $nguoi, $luong );
	return isset( $r['maDon'] ) ? $r['maDon'] : '';
}

/* Màn KHÔNG gửi mã nào -> lấy mặc định của bộ phận người lập. */
teq( '🔴 không gửi mã → lấy mặc định của bộ phận người lập', 'dc', _luong_cua_don( _lap( 'Chị Văn Phòng', '' ) ) );
teq( '   người Marketing → "tt"',                            'tt', _luong_cua_don( _lap( 'Anh Mkt', '' ) ) );
/* 🔴 ĐÂY LÀ PHÉP CỐT LÕI: "bộ phận khai, ĐƠN VẪN SỬA ĐƯỢC". Đảo thứ tự hai vế trong
   `create_don()` là phép này đỏ — và nếu không có nó thì ô chọn trên màn thành đồ trang trí. */
teq( '🔴 người lập CHỌN "gt" → "gt" thắng mặc định "dc" của bộ phận',
	'gt', _luong_cua_don( _lap( 'Chị Văn Phòng', 'gt' ) ) );
teq( '   chọn "tt" cũng thắng', 'tt', _luong_cua_don( _lap( 'Chị Văn Phòng', 'tt' ) ) );
teq( '   chọn đúng bằng mặc định thì vẫn là nó', 'dc', _luong_cua_don( _lap( 'Chị Văn Phòng', 'dc' ) ) );
/* ⚠️ MÃ LẠ ĐÃ VỀ RỖNG TRƯỚC ĐÓ, nên nó rơi vào nhánh mặc định — đúng ý: thà theo bộ phận còn
   hơn theo một mã gõ sai. */
teq( '⚠️ mã lạ → rơi về mặc định của bộ phận, không ghi mã bậy',
	'dc', _luong_cua_don( _lap( 'Chị Văn Phòng', 'xyz' ) ) );
/* Người không có bộ phận (hoặc bộ phận chưa khai) -> rỗng = theo khối, y như trước bản này. */
teq( '🔴 người chưa khai bộ phận, không chọn gì → RỖNG (theo khối như cũ)',
	'', _luong_cua_don( _lap( 'Người Vô Danh', '' ) ) );
teq( '   người của bộ phận chưa khai luồng → cũng rỗng', '', _luong_cua_don( _lap( 'Anh MTĐ', '' ) ) );

/* ═══ 4. 🔴 ĐƠN ĐÃ LẬP KHÔNG BỊ ĐỔI LUỒNG KHI SỬA DANH MỤC ═════════════════════════════ */
$ma_cu = _lap( 'Chị Văn Phòng', '' );
teq( '⚠️ đơn vừa lập mang "dc"', 'dc', _luong_cua_don( $ma_cu ) );
/* Đổi luồng của cả bộ phận sang trực tiếp. */
VHCP_Cfg::write( VHCP_Cfg::BP, array(
	array( 'Văn phòng', 'tt' ), array( 'Marketing', 'tt' ),
	array( 'Máy tự động', '' ), array( 'Cơ sở', '' ),
), false );
VHCP_Cfg::clear_cache();
teq( '⚠️ danh mục đã đổi sang "tt"', 'tt', VHCP_Cfg::luong_cua_bo_phan( 'Văn phòng' ) );
teq( '🔴 ĐƠN CŨ VẪN MANG "dc" — đóng dấu lúc lập, không tra lại bảng', 'dc', _luong_cua_don( $ma_cu ) );
teq( '   còn đơn lập SAU khi đổi thì mới mang "tt"', 'tt', _luong_cua_don( _lap( 'Chị Văn Phòng', '' ) ) );

/* ═══ 5. 🔴 LƯU DANH MỤC KHÔNG ĐƯỢC XOÁ MẤT CỘT LUỒNG ═════════════════════════════════
 * Màn cũ (và mọi lượt nhập CSV) gửi `boPhanDs` là mảng CHUỖI — lúc ấy không có luồng nào để
 * giữ. Ghi đè bằng rỗng là một lượt bấm Lưu ở màn khác xoá sạch luồng của cả bảng, và nó xoá
 * im lặng: danh sách tên vẫn đủ, chỉ luồng biến mất. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Chị Văn Phòng', '', 'Văn phòng' );
$r = VHCP_Cfg::save_config( array( 'boPhanDs' => array( 'Văn phòng', 'Marketing', 'Máy tự động' ) ) );
t( '⚠️ lưu được danh mục dạng mảng chuỗi', ! empty( $r['success'] ), $r );
VHCP_Cfg::clear_cache();
teq( '🔴 gửi mảng CHUỖI → luồng cũ được GIỮ, không bị xoá', 'tt', VHCP_Cfg::luong_cua_bo_phan( 'Văn phòng' ) );

/* Gửi dạng đối tượng thì ghi đúng cái mình gửi — kể cả ghi về rỗng. */
$r = VHCP_Cfg::save_config( array( 'boPhanDs' => array(
	array( 'ten' => 'Văn phòng', 'luong' => 'gt' ),
	array( 'ten' => 'Marketing', 'luong' => '' ),
) ) );
t( '⚠️ lưu được danh mục dạng đối tượng', ! empty( $r['success'] ), $r );
VHCP_Cfg::clear_cache();
teq( '🔴 gửi đối tượng → ghi đúng mã mới', 'gt', VHCP_Cfg::luong_cua_bo_phan( 'Văn phòng' ) );
/* 🔴 VÀ PHẢI XOÁ ĐƯỢC. Chọn lại về "theo khối" rồi bấm Lưu mà mã cũ còn nguyên là người khai
   tưởng mình đã bỏ, trong khi đơn mới vẫn đi luồng cũ. */
teq( '🔴 gửi đối tượng với luồng RỖNG → xoá được mã cũ', '', VHCP_Cfg::luong_cua_bo_phan( 'Marketing' ) );

/* ═══ 6. HAI BÊN CÙNG LUẬT — MÀN vs MÁY CHỦ ═══════════════════════════════════════════ */
$APP = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
/* Ô chọn trên bảng Cấu hình phải bày ĐÚNG bộ mã máy chủ nhận, cộng một dòng rỗng. Bày thừa một
   mã là khai xong không có tác dụng; thiếu một mã là có luồng mà không ai chọn được. */
$ma_js = array();
if ( preg_match( "/var LUONG_BP_DS=\[(.*?)\];/su", $APP, $m ) ) {
	if ( preg_match_all( "/\['([a-z]*)',/u", $m[1], $mm ) ) { $ma_js = $mm[1]; }
}
/* ⚠️ SO BỘ MÃ, KHÔNG SO THỨ TỰ. Thứ tự bày ra là chuyện trình bày — màn xếp "Qua tạm ứng"
   lên trước vì đó là *"cái đang chạy"*, và đổi thứ tự ấy không làm sai luật nào. Chốt thứ tự ở
   đây là bài kiểm đỏ mỗi lần ai sắp lại mấy dòng cho dễ đọc. */
$mong = array_merge( array( '' ), array_keys( VHCP_Don::LUONG_MA ) );
sort( $mong ); $thuc = $ma_js; sort( $thuc );
teq( '🔴 ô chọn luồng ở Cấu hình bày đúng: rỗng + ba mã của `LUONG_MA`', $mong, $thuc );
/* 🔴 MÀN PHẢI NGÃ VỀ ĐƯỜNG LUI THEO KHỐI Y NHƯ MÁY CHỦ. Bộ phận chưa khai mà màn mồi 'gt' cho
   khối Máy tự động là đơn mới mất khâu duyệt chi — đúng lỗi bản 1.287.0 đã mắc. */
t( '🔴 `_luongMoi()` hỏi bộ phận trước (BOOT.luongBo), rồi mới tới khối',
	1 === preg_match( '/function _luongMoi\(\)\{[\s\S]{0,400}?BOOT\.luongBo[\s\S]{0,400}?KHOI_LUONG_CHI/u', $APP ), null );
t( '🔴 và gói khởi động có chở `luongBo` xuống',
	false !== strpos( file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' ),
		"'luongBo'    => self::luong_mac_dinh( VHCP_Auth::nguoi() )," ), null );

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: bộ phận khai luồng mặc định, đơn vẫn sửa được, đơn cũ không đổi đường.\n";
