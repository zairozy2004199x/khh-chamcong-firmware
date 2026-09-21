<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * DỌN VAI TỰ TẠO — VÀ KHÔNG AI ĐƯỢC MẤT QUYỀN VÌ LƯỢT DỌN ẤY.
 *
 * Anh Thắng 21/09/2026: *"xóa luôn mấy vai trò đó đi, không cho nó hiện"*, sau khi chốt bỏ cột
 * bộ phận khỏi vai trò. Mấy vai ấy sinh ra chỉ để NÓI BỘ PHẬN, mà bộ phận nay đã có cột riêng.
 *
 * =============================================================================================
 * 🔴 XOÁ SUÔNG LÀ CẮT QUYỀN NGƯỜI KHÁC, IM LẶNG
 * =============================================================================================
 * `vai_goc()` trả "Nhân viên" cho mọi tên vai không còn trong bảng. Nên xoá bảng mà không dời
 * người trước thì ai đang mang **"Kế toán máy tự động"** (kế thừa *Kế toán cá nhân*) tụt thẳng
 * xuống Nhân viên: không duyệt, không xác nhận quyết toán, không xuất MISA được nữa. Không câu
 * lỗi nào — họ chỉ phát hiện lúc cần bấm.
 *
 * ⚠️ VÀ PHẢI TÍNH ÁNH XẠ TRƯỚC KHI DỌN BẢNG. `vai_goc()` tra trong chính bảng ấy; dọn trước thì
 *    mọi vai hoá "lạ" và tất cả rơi về Nhân viên — đúng cái đang tránh. Bài này gieo một vai
 *    kế thừa *Kế toán cá nhân* riêng để bắt đúng thứ tự ấy.
 *
 * 🔴 VÀ CHỈ MỘT LƯỢT. Quét lại mỗi lượt nạp là anh Thắng không bao giờ tạo được vai mới nữa:
 *    vừa khai xong, lượt sau nó biến mất — một tính năng chết mà không ai hiểu vì sao.
 *
 * Chạy: php tools/test/kiem-don-vai-tu-tao.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/** Vai trò đang khai của từng người, đọc thẳng từ sổ. */
function vai_cua() {
	$ra = array();
	foreach ( VHCP_Cfg::read( VHCP_Cfg::USER ) as $r ) {
		$r = array_values( (array) $r );
		if ( '' === trim( (string) $r[0] ) ) { continue; }
		$ra[ $r[0] ] = isset( $r[2] ) ? $r[2] : '';
	}
	return $ra;
}

/* ═══ GIEO: mấy vai đúng như trên ảnh anh Thắng gửi, kèm người đang mang ═══════════ */
VHCP_Cfg::write( VHCP_Cfg::VAI, array(
	array( 'Nhân Viên Kỹ Thuật',  'Nhân viên' ),
	array( 'Nhân Viên Marketing', 'Nhân viên' ),
	/* 🔴 Vai NGUY NHẤT: nó kế thừa KẾ TOÁN, không phải Nhân viên. */
	array( 'Kế toán máy tự động', 'Kế toán cá nhân' ),
), false );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Nguyễn Hữu Thọ',    '1111', 'Nhân Viên Kỹ Thuật',  '', '', '', 'Kỹ thuật',   '', '' ),
	array( 'Nguyễn Phương Vy',  '2222', 'Nhân Viên Marketing', '', '', '', 'Marketing',  '', '' ),
	array( 'Chị Kế Toán MTĐ',   '3333', 'Kế toán máy tự động', '', '', '', 'Máy tự động','', '' ),
	array( 'Chị Nhân',          '4444', 'Kế toán cá nhân',     '', '', '', '',           '', '' ),
	array( 'Sếp',               '5555', 'Admin',               '', '', '', '',           '', '' ),
), false );
/* ⚠️ GỠ DẤU TRƯỚC KHI THỬ. `vhcp_test_boot()` đã chạy một lượt `seed()` lúc bảng vai còn rỗng,
   nên lượt dọn đã đóng dấu và no-op. Không gỡ thì cả bài này thử một lượt dọn KHÔNG BAO GIỜ
   chạy, và mọi phép dưới đỏ vì bệ đỡ chứ không phải vì mã. (Trên máy thật thì ngược lại: dấu
   chưa có, bảng vai đã đầy — lượt nạp cấu hình đầu tiên sau khi cài là nó chạy.) */
VHCP_Meta::del( 'don_vai_tu_tao_v1' );
VHCP_Cfg::clear_cache();
teq( 'gieo xong có 3 vai tự tạo', 3, count( VHCP_Cfg::vai_tuy_bien() ) );
teq( '   và dấu "đã dọn" đang gỡ', null, VHCP_Meta::get( 'don_vai_tu_tao_v1' ) );

/* ═══ CHẠY LƯỢT DỌN ════════════════════════════════════════════════════════════════ */
VHCP_Cfg::seed();
VHCP_Cfg::clear_cache();

teq( '🔴 bảng vai tự tạo đã sạch', 0, count( VHCP_Cfg::vai_tuy_bien() ) );
teq( '   nên ô chọn Vai trò chỉ còn Admin + 4 vai gốc',
	array_merge( array( 'Admin' ), VHCP_Cfg::VAI_GOC ),
	array_merge( array( 'Admin' ), array_values( VHCP_Cfg::VAI_GOC ) ) );

$v = vai_cua();
/* 🔴 Đây là phép quan trọng nhất của cả bài. */
teq( '🔴 người mang vai kế thừa KẾ TOÁN về đúng Kế toán cá nhân, KHÔNG tụt xuống Nhân viên',
	'Kế toán cá nhân', $v['Chị Kế Toán MTĐ'] );
teq( '   người mang vai kế thừa Nhân viên về Nhân viên', 'Nhân viên', $v['Nguyễn Hữu Thọ'] );
teq( '   người thứ hai cũng vậy',                        'Nhân viên', $v['Nguyễn Phương Vy'] );
teq( '🔴 người vốn mang VAI GỐC không bị đụng',          'Kế toán cá nhân', $v['Chị Nhân'] );
teq( '🔴 Admin không bị đụng',                           'Admin', $v['Sếp'] );

/* Ô Bộ phận — trục duy nhất còn lại — phải còn nguyên vẹn sau lượt dọn. */
$bp = array();
foreach ( VHCP_Cfg::read( VHCP_Cfg::USER ) as $r ) {
	$r = array_values( (array) $r );
	if ( '' === trim( (string) $r[0] ) ) { continue; }
	$bp[ $r[0] ] = isset( $r[6] ) ? $r[6] : '';
}
teq( '🔴 ô Bộ phận còn nguyên — dọn vai KHÔNG được đụng tới trục kia',
	array( 'Kỹ thuật', 'Marketing', 'Máy tự động', '', '' ),
	array( $bp['Nguyễn Hữu Thọ'], $bp['Nguyễn Phương Vy'], $bp['Chị Kế Toán MTĐ'], $bp['Chị Nhân'], $bp['Sếp'] ) );

/* ═══ 🔴 CHỈ MỘT LƯỢT — VAI KHAI SAU NÀY PHẢI SỐNG ════════════════════════════════
 * Quét lại mỗi lượt nạp là tính năng "vai tự tạo" chết hẳn: khai xong, lượt sau biến mất, mà
 * không có gì trên màn nói vì sao. */
VHCP_Cfg::write( VHCP_Cfg::VAI, array( array( 'Kế toán vùng', 'Kế toán NCC' ) ), false );
VHCP_Cfg::clear_cache();
VHCP_Cfg::seed();
VHCP_Cfg::clear_cache();
teq( '🔴 vai khai SAU lượt dọn vẫn còn', 1, count( VHCP_Cfg::vai_tuy_bien() ) );
teq( '   và giữ đúng vai gốc của nó', 'Kế toán NCC', VHCP_Cfg::vai_goc( 'Kế toán vùng' ) );
/* Và người đang mang nó cũng không bị dời. */
VHCP_Cfg::set_cell( VHCP_Cfg::USER, 3, 2, 'Kế toán vùng' );
VHCP_Cfg::clear_cache();
VHCP_Cfg::seed();
VHCP_Cfg::clear_cache();
$v = vai_cua();
teq( '   người mang vai mới không bị dời về vai gốc', 'Kế toán vùng', $v['Chị Nhân'] );

/* ═══ LƯỢT DỌN CHẠY ĐƯỢC CẢ KHI KHÔNG CÓ VAI NÀO ═════════════════════════════════
 * Site mới cài thì bảng rỗng — lượt dọn phải im lặng đi qua, không ném gì. */
VHCP_Meta::del( 'don_vai_tu_tao_v1' );   // giả lập site khác, chưa đóng dấu
VHCP_Cfg::write( VHCP_Cfg::VAI, array(), false );
VHCP_Cfg::clear_cache();
VHCP_Cfg::seed();
t( 'site không có vai tự tạo nào: lượt dọn đi qua êm', true );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: vai tự tạo dọn sạch, không ai mất quyền, và vai khai sau vẫn sống.\n";
