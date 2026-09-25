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
	array( 'Cơ sở',       'gt' ),
), false );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	//     tên            pin    vai        cơ sở  tkCó  mãĐT  BỘ PHẬN
	array( 'Chị Văn Phòng', '1', 'Nhân viên', '', '', '', 'Văn phòng' ),
	array( 'Anh Mkt',       '2', 'Nhân viên', '', '', '', 'Marketing' ),
	array( 'Anh MTĐ',       '3', 'Nhân viên', '', '', '', 'Máy tự động' ),
	array( 'Người Vô Danh', '4', 'Nhân viên', '', '', '', '' ),
	/* 23/09/2026 — cảnh thật của chị Thảo: cột Bộ phận TRỐNG, vai nói rõ "Cơ Sở". */
	array( 'Chị Thảo',      '5', 'Nhân Viên Cơ Sở Khu Vui Chơi', '', '', '', '' ),
	/* Ô đã khai thì ô THẮNG tên vai — vai nói Kỹ thuật, ô nói Marketing → Marketing. */
	array( 'Anh KT',        '6', 'Nhân Viên Kỹ Thuật Máy Tự Động', '', '', '', 'Marketing' ),
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
	array( 'Máy tự động', '' ), array( 'Cơ sở', 'gt' ),
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

/* ═══ 2b. 🔴 Ô TRỐNG → SUY TỪ TÊN VAI, Y NHƯ MÀN (23/09/2026) ═══════════════════════════
 * Anh Thắng: *"đã phân luồng sao vẫn hỏi"* — chị Thảo mang vai "Nhân Viên Cơ Sở Khu Vui Chơi",
 * cột Bộ phận trống. Màn (`_bpCuaToi`) đọc ra "Cơ sở", máy chủ chỉ đọc cột → `luongBo` rỗng →
 * hộp Tạo đơn vẫn bày ba nút dù bảng đã khai Cơ sở → Qua tạm ứng. Hai bên phải cùng câu trả lời.
 * ⚠️ Không phải trục "bộ phận của VAI" đã bỏ (cột riêng trên bảng Vai): đây là đọc CHỮ trong tên
 *    vai, và CHỈ khi ô người dùng trống. */
teq( '🔴 cột trống + vai "Nhân Viên Cơ Sở Khu Vui Chơi" → Cơ sở', 'Cơ sở', VHCP_Cfg::bo_phan_hang_nguoi( 'Chị Thảo' ) );
teq( '🔴 ô đã khai THẮNG tên vai (vai Kỹ thuật, ô Marketing → Marketing)', 'Marketing', VHCP_Cfg::bo_phan_hang_nguoi( 'Anh KT' ) );
teq( '   vai "Nhân viên" trần (không nói mảng) → vẫn rỗng', '', VHCP_Cfg::bo_phan_hang_nguoi( 'Người Vô Danh' ) );
teq( '   `bo_phan_tu_ten_vai`: không phân biệt hoa/dấu', 'Văn phòng', VHCP_Cfg::bo_phan_tu_ten_vai( 'NHÂN VIÊN VĂN PHÒNG CHUNG' ) );
/* Danh mục bệ đỡ không có "Kỹ thuật" → suy ra được chữ nhưng `bo_phan_chuan` chối — đúng luật. */
teq( '   suy ra "Kỹ thuật" mà danh mục không có → rỗng', '', VHCP_Cfg::bo_phan_tu_ten_vai( 'NHÂN VIÊN KỸ THUẬT MÁY TỰ ĐỘNG' ) );
teq( '   không dấu cũng nhận', 'Cơ sở', VHCP_Cfg::bo_phan_tu_ten_vai( 'nhan vien co so' ) );
teq( '   "Kế toán cá nhân" → rỗng', '', VHCP_Cfg::bo_phan_tu_ten_vai( 'Kế toán cá nhân' ) );
teq( '   "Quản Lý VP Chung" → rỗng (chữ "VP" không phải "văn phòng", như màn)', '', VHCP_Cfg::bo_phan_tu_ten_vai( 'Quản Lý VP Chung' ) );
teq( '   chỉ nhận trọn từ: "co so" nằm trong "co sohuu" thì không', '', VHCP_Cfg::bo_phan_tu_ten_vai( 'nhan vien co sohuu' ) );
teq( '   rỗng → rỗng', '', VHCP_Cfg::bo_phan_tu_ten_vai( '' ) );
/* Bộ phận suy ra mà KHÔNG có trong danh mục → rỗng (đi qua `bo_phan_chuan`). */
VHCP_Cfg::write( VHCP_Cfg::BP, array( array( 'Văn phòng', 'dc' ) ), false );
VHCP_Cfg::clear_cache();
teq( '🔴 suy ra "Cơ sở" nhưng danh mục không có → rỗng, không nhận bừa', '', VHCP_Cfg::bo_phan_tu_ten_vai( 'Nhân Viên Cơ Sở Khu Vui Chơi' ) );
VHCP_Cfg::write( VHCP_Cfg::BP, array(
	array( 'Văn phòng', 'dc' ), array( 'Marketing', 'tt' ),
	array( 'Máy tự động', '' ), array( 'Cơ sở', 'gt' ),
), false );
VHCP_Cfg::clear_cache();

/* ═══ 2c. 🔴 TÊN VAI CHÍNH LÀ TÊN BỘ PHẬN (23/09/2026) ═════════════════════════════════
 * Anh Thắng: *"tên vai trò tức là bộ phận"*, *"Đổi tên bộ phận sang tên vai trò cho cùng tên"*.
 * Bảng Luồng nay mỗi vai tự tạo một dòng → danh mục bộ phận chứa TÊN VAI. Máy chủ tra thẳng tên
 * vai của người; ô khai tay vẫn thắng; chữ trong tên vai chỉ là đường lui khi bảng còn tên cũ. */
VHCP_Cfg::write( VHCP_Cfg::BP, array(
	array( 'Nhân Viên Cơ Sở Khu Vui Chơi', 'gt' ),
	array( 'Nhân Viên Kỹ Thuật Máy Tự Động', 'dc' ),
	array( 'Kế Toán VP Chung', 'tt' ),
	array( 'Marketing', 'tt' ),        // tên bộ phận cũ còn sót — ô khai tay của Anh KT trỏ vào đây
), false );
VHCP_Cfg::clear_cache();
teq( '🔴 vai "Nhân Viên Cơ Sở Khu Vui Chơi" là một dòng bộ phận → tra thẳng, không cần suy chữ',
	'Nhân Viên Cơ Sở Khu Vui Chơi', VHCP_Cfg::bo_phan_hang_nguoi( 'Chị Thảo' ) );
teq( '🔴 → luồng mặc định = luồng của dòng vai ấy', 'gt', VHCP_Don::luong_mac_dinh( 'Chị Thảo' ) );
teq( '🔴 ô khai tay VẪN THẮNG tên vai (Anh KT: ô Marketing, vai Kỹ thuật MTĐ có dòng riêng "dc")',
	'Marketing', VHCP_Cfg::bo_phan_hang_nguoi( 'Anh KT' ) );
teq( '   → luồng theo ô khai (tt), không theo dòng vai (dc)', 'tt', VHCP_Don::luong_mac_dinh( 'Anh KT' ) );
/* Người có vai gốc trần ("Nhân viên") và ô trống → không dòng nào → rỗng → hộp vẫn hỏi. */
teq( '   vai gốc trần, ô trống → rỗng (hộp Tạo đơn vẫn hỏi ba nút)', '', VHCP_Don::luong_mac_dinh( 'Người Vô Danh' ) );
/* Không phân biệt hoa thường giữa tên vai và dòng bảng. */
VHCP_Cfg::write( VHCP_Cfg::USER, array( array( 'Chị Thảo 2', '7', 'NHÂN VIÊN CƠ SỞ KHU VUI CHƠI', '', '', '', '' ) ), false );
VHCP_Cfg::clear_cache();
teq( '   tên vai viết HOA vẫn khớp dòng bảng', 'Nhân Viên Cơ Sở Khu Vui Chơi', VHCP_Cfg::bo_phan_hang_nguoi( 'Chị Thảo 2' ) );
/* Trả bệ đỡ về như cũ cho các mục sau. */
VHCP_Cfg::write( VHCP_Cfg::BP, array(
	array( 'Văn phòng', 'dc' ), array( 'Marketing', 'tt' ),
	array( 'Máy tự động', '' ), array( 'Cơ sở', 'gt' ),
), false );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Chị Văn Phòng', '1', 'Nhân viên', '', '', '', 'Văn phòng' ),
	array( 'Anh Mkt',       '2', 'Nhân viên', '', '', '', 'Marketing' ),
	array( 'Anh MTĐ',       '3', 'Nhân viên', '', '', '', 'Máy tự động' ),
	array( 'Người Vô Danh', '4', 'Nhân viên', '', '', '', '' ),
	array( 'Chị Thảo',      '5', 'Nhân Viên Cơ Sở Khu Vui Chơi', '', '', '', '' ),
	array( 'Anh KT',        '6', 'Nhân Viên Kỹ Thuật Máy Tự Động', '', '', '', 'Marketing' ),
), false );
VHCP_Cfg::clear_cache();

/* Ghép hai cái trên. */
teq( '🔴 `luong_mac_dinh()` — người Văn phòng → "dc"', 'dc', VHCP_Don::luong_mac_dinh( 'Chị Văn Phòng' ) );
teq( '   người Marketing → "tt"',                      'tt', VHCP_Don::luong_mac_dinh( 'Anh Mkt' ) );
teq( '🔴 chị Thảo (cột trống, vai Cơ sở) → "gt" — đúng luồng bảng đã khai cho Cơ sở', 'gt', VHCP_Don::luong_mac_dinh( 'Chị Thảo' ) );
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
	array( 'Máy tự động', '' ), array( 'Cơ sở', 'gt' ),
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

/* 🔴 GÓI MÀN NHẬN PHẢI MANG KHOÁ — đây là phép đã THIẾU hôm 22/09. Sổ đúng, hàm đọc sổ đúng,
   mà `get_config()` lọc khoá theo danh sách trắng nên `boPhanLuong` rơi ra; màn đọc `undefined`
   và mọi ô về "theo khối như cũ". Anh Thắng 23/09/2026: *"Nó vẫn chưa lưu được luồng"*.
   Soi đúng cái màn nhận, không soi hàm trung gian. */
$goi = VHCP_Cfg::get_config( array() );
t( '🔴 `get_config()` chở khoá `boPhanLuong` xuống màn', isset( $goi['boPhanLuong'] ) && is_array( $goi['boPhanLuong'] ), array_keys( $goi ) );
teq( '🔴 và mang đúng mã vừa lưu (Văn phòng → tt)', 'tt', isset( $goi['boPhanLuong']['Văn phòng'] ) ? $goi['boPhanLuong']['Văn phòng'] : null );
t( '   bộ phận để trống thì KHÔNG có trong gói (không gửi rác)', ! isset( $goi['boPhanLuong']['Máy tự động'] ), $goi['boPhanLuong'] );

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
