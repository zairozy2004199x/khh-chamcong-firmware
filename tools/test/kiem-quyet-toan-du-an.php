<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GỬI QUYẾT TOÁN THEO ĐƠN — BƯỚC CUỐI CỦA DỰ ÁN.
 *
 * Anh Thắng: *"khi đơn này đã xong, tích chọn để gửi quyết toán theo đơn"*.
 *
 * =============================================================================================
 * Chốt hoàn thành ("xong") mới chỉ nói: hạng mục này đã có hoá đơn, số tiền là chi thực tế.
 * QUYẾT TOÁN là bước sau — đối chiếu tiền đã ứng với tiền đã chi để ra thừa / thiếu, rồi kế toán
 * chốt sổ. Không có bước này thì khoản tạm ứng treo mãi trên TK 141 mà không ai tất toán.
 *
 * 🔴 CHỈ NHẬN HẠNG MỤC ĐÃ CHỐT XONG. Gửi quyết toán cho hạng mục chưa có hoá đơn là chốt sổ một
 *    con số không có gì đỡ.
 *
 * 🔴 MỘT HẠNG MỤC KHÔNG NẰM TRONG HAI LỆNH QUYẾT TOÁN. Quyết toán hai lần cùng một khoản là tất
 *    toán gấp đôi số đã ứng — sổ 141 âm mà không ai hiểu vì sao.
 *
 * 🔴 HAI LOẠI LỆNH DÙNG CHUNG BỘ MÁY NHƯNG KHÔNG DÙNG CHUNG DANH SÁCH TRẠNG THÁI. Quyết toán có
 *    'xong' (chốt sổ), tạm ứng thì không — gộp làm một là một lệnh tạm ứng nhảy thẳng sang "đã
 *    chốt sổ" mà chưa ai cấp đồng nào. Và hai kho phải TÁCH: chung kho thì đợt 1 của loại này
 *    đè lên đợt 1 của loại kia.
 *
 * ⚠️ CHẠY THẬT trên dữ liệu thật, đổi vai qua từng bước.
 *
 * Chạy: php tools/test/kiem-quyet-toan-du-an.php
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
function vai( $v, $ten = 'Ai đó' ) { VHCP_Auth::dat_vai_tro( $v, $ten ); }

/** Đưa một hạng mục đi trọn đường tạm ứng tới lúc CHỐT XONG. */
function di_toi_xong( $ma, $row, $unc ) {
	vai( 'Nhân viên', 'NV' );
	$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $row ) );
	$dot = $x['dot']['dot'];
	vai( 'Quản lý', 'QL' );          VHCP_DuAn::dat_tt_dot( $ma, $dot, 'duyet' );
	vai( 'Kế toán cá nhân', 'KT' );  VHCP_DuAn::dat_tt_dot( $ma, $dot, 'ung', array( 'unc' => $unc ) );
	VHCP_DuAn::dat_hm( $ma, $row, 'xong', array( 'hoaDon' => 'hd-' . $row . '.pdf' ) );
}

vai( 'Admin', 'KT' );
$r  = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian thử quyết toán', 'NV' );
$ma = $r['maDA'];
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Thợ bốc vác', 'thucTe' => 2300000 ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Xe vận chuyển', 'thucTe' => 5000000 ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Vật tư', 'thucTe' => 900000 ) );
$d = VHCP_DuAn::get_du_an( $ma );
$R = array();
foreach ( $d['lines'] as $l ) { if ( '' === $l['capCha'] ) { $R[ $l['noiDung'] ] = $l['row']; } }
t( 'dựng được ba hạng mục thử', 3 === count( $R ), array_keys( $R ) );

/* ═══ 1. 🔴 CHƯA CHỐT XONG THÌ CHƯA GỬI QUYẾT TOÁN ĐƯỢC ═════════════════════════════════ */
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_quyet_toan_dot( $ma, array( $R['Thợ bốc vác'] ) );
t( '🔴 hạng mục còn NHÁP → chối gửi quyết toán', empty( $x['success'] ), $x );
t( '   và gọi tên hạng mục đang vướng',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'Thợ bốc vác' ), $x );

di_toi_xong( $ma, $R['Thợ bốc vác'], 'UNC-1' );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_quyet_toan_dot( $ma, array( $R['Xe vận chuyển'] ) );
t( '🔴 hạng mục mới xin tạm ứng, CHƯA chốt hoá đơn → vẫn chối', empty( $x['success'] ), $x );

/* ═══ 2. TÍCH NHIỀU HẠNG MỤC ĐÃ XONG → MỘT LỆNH QUYẾT TOÁN ═════════════════════════════ */
di_toi_xong( $ma, $R['Xe vận chuyển'], 'UNC-2' );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_quyet_toan_dot( $ma, array( $R['Thợ bốc vác'], $R['Xe vận chuyển'] ), 'quyết toán đợt setup' );
t( '🔴 tích hai hạng mục đã xong → gửi được MỘT lệnh quyết toán', ! empty( $x['success'] ), $x );
teq( '   đánh số đợt 1', 1, $x['dot']['dot'] );
teq( '   gồm đúng hai hạng mục', 2, $x['so'] );
t( '🔴 số tiền = tổng hai hạng mục (2.300.000 + 5.000.000)', 7300000 == $x['soTien'], $x['soTien'] );
teq( '   đang chờ kế toán chốt sổ', 'xin', $x['dot']['tt'] );
teq( '   giữ ghi chú', 'quyết toán đợt setup', $x['dot']['lyDo'] );
teq( '🔴 hạng mục mang cờ ĐÃ GỬI QUYẾT TOÁN đợt 1', 1, VHCP_DuAn::hm_cua( $ma, $R['Thợ bốc vác'] )['qtDot'] );
teq( '   hạng mục chưa gửi thì cờ vẫn 0', 0, VHCP_DuAn::hm_cua( $ma, $R['Vật tư'] )['qtDot'] );
teq( '🔴 trạng thái hạng mục vẫn là "xong" — quyết toán không kéo nó ngược lại',
	'xong', VHCP_DuAn::hm_cua( $ma, $R['Thợ bốc vác'] )['tt'] );

$x = VHCP_DuAn::xin_quyet_toan_dot( $ma, array( $R['Thợ bốc vác'] ) );
t( '🔴 gửi LẠI hạng mục đã trong lệnh → CHỐI (quyết toán hai lần là tất toán gấp đôi)',
	empty( $x['success'] ), $x );
$x = VHCP_DuAn::xin_quyet_toan_dot( $ma, array() );
t( 'không tích gì → chối', empty( $x['success'] ), $x );
$x = VHCP_DuAn::xin_quyet_toan_dot( $ma, array( 999 ) );
t( 'dòng không phải hạng mục lớn của dự án này → chối', empty( $x['success'] ), $x );

/* ═══ 3. 🔴 CHỐT SỔ LÀ VIỆC CỦA KẾ TOÁN ════════════════════════════════════════════════ */
$x = VHCP_DuAn::dat_tt_qt( $ma, 1, 'xong' );
t( '🔴 nhân viên KHÔNG tự chốt sổ được', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_tt_qt( $ma, 1, 'tra' );
t( '   và cũng không tự trả lại được', empty( $x['success'] ), $x );

vai( 'Kế toán cá nhân', 'KT' );
$x = VHCP_DuAn::dat_tt_qt( $ma, 1, 'xong' );
t( 'kế toán chốt sổ được', ! empty( $x['success'] ), $x );
teq( '🔴 và đọc ra ĐÚNG là đã chốt (không ngã về "chờ chốt")',
	'xong', VHCP_DuAn::dot_cua( $ma, 1, 'qt' )['tt'] );
t( '   có mốc thời gian để tra', ! empty( VHCP_DuAn::dot_cua( $ma, 1, 'qt' )['moc']['xong'] ),
	VHCP_DuAn::dot_cua( $ma, 1, 'qt' )['moc'] );
$x = VHCP_DuAn::dat_tt_qt( $ma, 1, 'xong' );
t( '🔴 chốt sổ LẦN HAI → chối', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_tt_qt( $ma, 1, 'tra' );
t( '🔴 đã chốt sổ thì không trả lại được nữa', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_tt_qt( $ma, 9, 'xong' );
t( 'lệnh quyết toán không có thật → chối, không nổ', empty( $x['success'] ), $x );


/* ═══ 4. 🔴 TRẢ LẠI PHẢI GỠ CỜ, KHÔNG THÌ ĐƠN CHẾT CỨNG ═══════════════════════════════ */
di_toi_xong( $ma, $R['Vật tư'], 'UNC-3' );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_quyet_toan_dot( $ma, array( $R['Vật tư'] ) );
teq( 'gửi lệnh quyết toán đợt 2', 2, $x['dot']['dot'] );
vai( 'Quản lý', 'QL' );
$x = VHCP_DuAn::dat_tt_qt( $ma, 2, 'tra', array( 'lyDo' => 'Thiếu chữ ký' ) );
t( 'quản lý trả lại được', ! empty( $x['success'] ), $x );
teq( '   lý do ghi vào lệnh', 'Thiếu chữ ký', VHCP_DuAn::dot_cua( $ma, 2, 'qt' )['lyDo'] );
teq( '🔴 và GỠ cờ quyết toán khỏi hạng mục (giữ lại là không gửi lại được nữa)',
	0, VHCP_DuAn::hm_cua( $ma, $R['Vật tư'] )['qtDot'] );
teq( '   hạng mục vẫn là "xong", không bị kéo ngược',
	'xong', VHCP_DuAn::hm_cua( $ma, $R['Vật tư'] )['tt'] );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_quyet_toan_dot( $ma, array( $R['Vật tư'] ) );
t( '🔴 sửa xong GỬI LẠI ĐƯỢC, thành lệnh đợt 3', ! empty( $x['success'] ) && 3 === $x['dot']['dot'], $x );

/* 🔴 Thử trên lệnh CHƯA CHỐT (đợt 3). Thử trên lệnh đã chốt thì luật "đã chốt sổ rồi" cũng
   chối — phép xanh mà chốt trạng thái có bị đục thủng cũng không biết. */
vai( 'Kế toán cá nhân', 'KT' );
teq( 'lệnh quyết toán đợt 3 đang chờ chốt', 'xin', VHCP_DuAn::dot_cua( $ma, 3, 'qt' )['tt'] );
foreach ( array( 'ung', 'duyet', 'nhap', 'lung tung' ) as $bay ) {
	$x = VHCP_DuAn::dat_tt_qt( $ma, 3, $bay );
	t( '🔴 lệnh quyết toán KHÔNG nhận trạng thái "' . $bay . '" (đó là của tạm ứng)',
		empty( $x['success'] ), $x );
}
teq( '   và lệnh đợt 3 vẫn nguyên trạng thái', 'xin', VHCP_DuAn::dot_cua( $ma, 3, 'qt' )['tt'] );

/* ═══ 5. 🔴 HAI LOẠI LỆNH KHÔNG ĐƯỢC ĐÈ NHAU ══════════════════════════════════════════ */
teq( '🔴 lệnh TẠM ỨNG đợt 1 không bị lệnh quyết toán đợt 1 đè lên',
	'ung', VHCP_DuAn::dot_cua( $ma, 1 )['tt'] );
t( '   và số tiền của nó vẫn là số tiền tạm ứng',
	2300000 == VHCP_DuAn::dot_cua( $ma, 1 )['soTien'], VHCP_DuAn::dot_cua( $ma, 1 )['soTien'] );
teq( '   lệnh quyết toán đợt 1 vẫn là của quyết toán',
	'xong', VHCP_DuAn::dot_cua( $ma, 1, 'qt' )['tt'] );
$dd = VHCP_DuAn::get_du_an( $ma );
teq( '🔴 màn nhận đủ 3 lệnh tạm ứng', 3, count( $dd['lenh'] ) );
teq( '🔴 và 3 lệnh quyết toán, tách riêng', 3, count( $dd['lenhQT'] ) );
t( '   lệnh quyết toán kể tên hạng mục',
	in_array( 'Thợ bốc vác', $dd['lenhQT'][0]['tenHM'], true ), $dd['lenhQT'][0]['tenHM'] );

/* ═══ 5b. 🔴 KẾ TOÁN THẤY LỆNH QUYẾT TOÁN Ở TAB CỦA MÌNH ═══════════════════════════════
 * Anh Thắng: *"Khi nv gửi chốt quyết toán, bên tab quyết toán của kế toán cũng sẽ hiện lên đơn
 * đó giống tạm ứng để kế toán theo dõi"*.
 * Không có đường này thì lệnh nằm im trong trang dự án — kế toán chỉ thấy nó nếu tình cờ mở
 * đúng dự án ấy, mà quyết toán là bước tất toán khoản treo trên TK 141.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
vai( 'Kế toán cá nhân', 'KT' );
$ls_qt = VHCP_DuAn::list_lenh_da( 'qt' );
$cua_qt = array();
foreach ( $ls_qt['items'] as $i ) { if ( $i['maDA'] === $ma ) { $cua_qt[ $i['dot'] ] = $i; } }
teq( '🔴 kế toán liệt kê được lệnh QUYẾT TOÁN của mọi dự án', 3, count( $cua_qt ) );
teq( '   mỗi dòng nói rõ đó là lệnh loại gì', 'qt', $cua_qt[1]['loai'] );
t( '   kể tên hạng mục trong lệnh',
	in_array( 'Thợ bốc vác', $cua_qt[1]['tenHM'], true ), $cua_qt[1]['tenHM'] );
t( '   và số tiền của lệnh', 7300000 == $cua_qt[1]['soTien'], $cua_qt[1]['soTien'] );
teq( '   kèm trạng thái để lọc "chờ chốt sổ"', 'xin', $cua_qt[3]['tt'] );
teq( '   lệnh đã chốt cũng còn để tra lại', 'xong', $cua_qt[1]['tt'] );
t( '   kèm tên dự án và người tạo để kế toán biết đi hỏi ai',
	'' !== $cua_qt[1]['tenDA'] && isset( $cua_qt[1]['nguoiTao'] ), $cua_qt[1] );

/* 🔴 HAI DANH SÁCH KHÔNG ĐƯỢC LẪN VÀO NHAU. Lệnh tạm ứng lọt vào bảng quyết toán thì kế toán
   bấm "chốt sổ" lên một khoản chưa ai chi. */
$ls_tu = VHCP_DuAn::list_lenh_da();
$cua_tu = array();
foreach ( $ls_tu['items'] as $i ) { if ( $i['maDA'] === $ma ) { $cua_tu[ $i['dot'] ] = $i; } }
teq( '🔴 danh sách TẠM ỨNG vẫn ra lệnh tạm ứng', 'tu', $cua_tu[1]['loai'] );
t( '   và số tiền của nó khác lệnh quyết toán cùng đợt',
	$cua_tu[1]['soTien'] != $cua_qt[1]['soTien'], array( $cua_tu[1]['soTien'], $cua_qt[1]['soTien'] ) );
teq( '   trạng thái cũng là của tạm ứng', 'ung', $cua_tu[1]['tt'] );
/* Gọi với loại lạ thì ngã về TẠM ỨNG — không được ngã về quyết toán, kẻo một lời gọi gõ sai
   lại bày ra bảng chốt sổ. */
$ls_la = VHCP_DuAn::list_lenh_da( 'lung tung' );
$mot = null;
foreach ( $ls_la['items'] as $i ) { if ( $i['maDA'] === $ma ) { $mot = $i; break; } }
teq( 'loại lạ → ngã về tạm ứng, không ngã về quyết toán', 'tu', $mot['loai'] );

/* ═══ 5c. 🔴 HAI CON SỐ QUYẾT TOÁN CỦA CẢ ĐƠN ═════════════════════════════════════════
 * Anh Thắng: *"Tổng thực tế / Tổng thực tế đã quyết toán"* và *"Tổng tạm ứng đã chi − Quyết
 * toán đã chốt"*.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
$dd = VHCP_DuAn::get_du_an( $ma );
/* Lệnh QT đợt 1 (7.300.000, ĐÃ CHỐT SỔ) + đợt 3 (900.000, đang chờ chốt).
   Đợt 2 ĐÃ BỊ TRẢ → không tính bên nào. */
t( '🔴 "đã gửi quyết toán" = lệnh đang chờ chốt + đã chốt, KHÔNG tính lệnh bị trả lại',
	8200000 == $dd['qtDaGui'], $dd['qtDaGui'] );
t( '🔴 "đã quyết toán" = CHỈ lệnh đã chốt sổ (gửi rồi mà chưa đối chiếu thì vẫn treo trên 141)',
	7300000 == $dd['qtDaChot'], $dd['qtDaChot'] );
t( '   nên hai con số KHÁC nhau (phép này bắt lỗi lấy nhầm cái nọ sang cái kia)',
	$dd['qtDaGui'] != $dd['qtDaChot'], array( $dd['qtDaGui'], $dd['qtDaChot'] ) );
/* Ba lệnh tạm ứng đều đã cấp tiền: 2.300.000 + 5.000.000 + 900.000 = 8.200.000. */
t( 'tạm ứng đã chi = 8.200.000', 8200000 == $dd['daChiTU'], $dd['daChiTU'] );
t( '🔴 CÒN TREO trên TK 141 = 8.200.000 − 7.300.000 = 900.000',
	900000 == ( $dd['daChiTU'] - $dd['qtDaChot'] ), array( $dd['daChiTU'], $dd['qtDaChot'] ) );

/* Chốt nốt lệnh đợt 3 → tất toán hết. */
vai( 'Kế toán cá nhân', 'KT' );
VHCP_DuAn::dat_tt_qt( $ma, 3, 'xong' );
$dd = VHCP_DuAn::get_du_an( $ma );
t( '🔴 chốt nốt lệnh cuối → không còn treo đồng nào',
	0 == ( $dd['daChiTU'] - $dd['qtDaChot'] ), array( $dd['daChiTU'], $dd['qtDaChot'] ) );

/* ═══ 6. CỬA API ══════════════════════════════════════════════════════════════════════ */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
foreach ( array(
	"'xinQuyetToanDuAn'"   => "array( 'VHCP_DuAn', 'xin_quyet_toan_dot' )",
	"'datTrangThaiQTDuAn'" => "array( 'VHCP_DuAn', 'dat_tt_qt' )",
) as $k => $v ) {
	t( '🔴 ' . $k . ' đã khai vào cửa API', false !== strpos( $src, $k ) && false !== strpos( $src, $v ) );
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: hạng mục đã chốt gộp thành lệnh quyết toán, kế toán chốt sổ.\n";
