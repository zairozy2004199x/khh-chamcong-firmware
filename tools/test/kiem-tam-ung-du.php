<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TẠM ỨNG DƯ — KẾ TOÁN ĐƯA HƠN PHẦN CÒN LẠI, SỐ DƯ VÀO SỔ.
 *
 * Anh Thắng 23/09/2026: *"Cho cá nhân tạm ứng dư: tức kế toán sẽ nhập lớn hơn số thực tế"*.
 * Bản 18/09 chối thẳng số vượt (*"bấm cấp lần 2,3 là số tiền còn lại hoặc thấp hơn chứ"*) —
 * hồi ấy chưa có chuyện đưa dư. Nay làm tròn, đưa thêm tiền mặt là chuyện thường.
 *
 * 🔴 SỐ DƯ PHẢI VÀO SỔ, KHÔNG BIẾN MẤT. Cái hỏng nguy nhất là nới trần mà sổ vẫn cộng theo SỐ
 *    LỆNH: kế toán đưa 70tr cho lệnh 50tr, sổ ghi 50tr, 20tr nằm trong túi nhân viên mà không
 *    dòng nào nhắc. Nên phép cốt lõi: tổng đã cấp = số THẬT, và dòng cấp mang ô `du`.
 *
 * 🔴 DƯ CHỈ ĐƯỢC GẮN VÀO LẦN CUỐI CÒN NỢ. Gắn dư vào lần 1 khi lần 2 chưa nhận là lệnh lật
 *    sang "đủ" và lần 2 biến mất khỏi việc phải làm — sổ nói đủ, người thì thiếu.
 *
 * Chạy: php tools/test/kiem-tam-ung-du.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }
function vai( $v, $ten = 'Ai đó' ) { VHCP_Auth::dat_vai_tro( $v, $ten ); }

/* Dựng một dự án có hai hạng mục NV tự trả 20tr + 30tr, xin một lệnh 50tr. */
function _du_an_moi( $lich = null ) {
	vai( 'Admin', 'KT' );
	$ma = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian tạm ứng dư', 'NV' )['maDA'];
	VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Hạng A', 'duToan' => 20000000, 'thucTe' => 20000000 ) );
	VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Hạng B', 'duToan' => 30000000, 'thucTe' => 30000000 ) );
	$R = array();
	foreach ( VHCP_DuAn::get_du_an( $ma )['lines'] as $l ) { $R[ $l['noiDung'] ] = (int) $l['row']; }
	$ra = $R['Hạng A']; $rb = $R['Hạng B'];
	vai( 'Nhân viên', 'NV' );
	if ( null !== $lich ) {
		$lich = array( array( 'ngay' => '03/09/2026', 'rows' => array( $ra ) ), array( 'ngay' => '10/09/2026', 'rows' => array( $rb ) ) );
	}
	VHCP_DuAn::xin_tam_ung_dot( $ma, array( $ra, $rb ), null === $lich ? array() : $lich, '' );
	vai( 'Quản lý', 'QL' );
	VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );
	vai( 'Kế toán cá nhân', 'KTCN' );
	return array( $ma, $ra, $rb );
}

/* ═══ 1. ĐƯA DƯ MỘT LẦN — nhận, ghi dư, lệnh đủ ═════════════════════════════════════════ */
list( $M ) = _du_an_moi();
$L = VHCP_DuAn::dot_cua( $M, 1 );
teq( '⚠️ lệnh 50tr dựng đúng', 50000000, (int) $L['soTien'] );
$r = VHCP_DuAn::cap_tien_phan( $M, 1, array( 'soTien' => 70000000 ) );
t( '🔴 đưa 70tr cho lệnh 50tr → NHẬN', ! empty( $r['success'] ), $r );
t( '🔴 lệnh lật sang đủ', ! empty( $r['xong'] ), $r );
teq( '🔴 báo dư đúng 20tr', 20000000, (int) $r['du'] );
teq( '🔴 tổng đã cấp là 70tr THẬT, không phải 50tr của lệnh', 70000000, (int) $r['daCap'] );
$L = VHCP_DuAn::dot_cua( $M, 1 );
teq( '   trạng thái lệnh = ung', 'ung', $L['tt'] );
teq( '🔴 dòng cấp trong sổ mang ô `du` = 20tr', 20000000, (int) $L['daCap'][0]['du'] );
teq( '   và `soTien` của dòng là số thật 70tr', 70000000, (int) $L['daCap'][0]['soTien'] );
/* 🔴 "ĐÃ CHI" CỦA DỰ ÁN ĐỌC SỐ THẬT — đây là chỗ số dư phải nổi lên. */
$DA = VHCP_DuAn::get_du_an( $M );
teq( '🔴 "đã chi" của dự án (`daChiTU`) cộng đúng 70tr đã đưa — số dư nổi lên ở đây',
	70000000, (int) $DA['daChiTU'] );
VHCP_DuAn::delete( $M );

/* ═══ 2. VẪN KHÔNG NHẬN SỐ 0 / ÂM, VÀ VẪN PHẢI LÀ LỆNH ĐÃ DUYỆT ═════════════════════════ */
list( $M ) = _du_an_moi();
$r = VHCP_DuAn::cap_tien_phan( $M, 1, array( 'soTien' => 0 ) );
t( '⚠️ số 0 vẫn chối', empty( $r['success'] ), $r );
$r = VHCP_DuAn::cap_tien_phan( $M, 1, array( 'soTien' => -5 ) );
t( '⚠️ số âm vẫn chối', empty( $r['success'] ), $r );
/* Đưa vừa đúng — không dư. */
$r = VHCP_DuAn::cap_tien_phan( $M, 1, array( 'soTien' => 50000000 ) );
teq( '   đưa vừa đúng → du = 0', 0, (int) $r['du'] );
$r = VHCP_DuAn::cap_tien_phan( $M, 1, array( 'soTien' => 1000000 ) );
t( '⚠️ lệnh đã đủ rồi thì thôi — không đưa thêm qua cửa này', empty( $r['success'] ), $r );
VHCP_DuAn::delete( $M );

/* ═══ 3. 🔴 DƯ CHỈ Ở LẦN CUỐI CÒN NỢ ═════════════════════════════════════════════════════ */
list( $M, $RA, $RB ) = _du_an_moi( true );   // lần 1 = Hạng A 20tr · lần 2 = Hạng B 30tr
$r = VHCP_DuAn::cap_tien_phan( $M, 1, array( 'soTien' => 25000000, 'choLan' => 1 ) );
t( '🔴 gắn 25tr vào LẦN 1 (hẹn 20tr) khi lần 2 còn 30tr chưa nhận → CHỐI', empty( $r['success'] ), $r );
t( '   câu chối kể cả phần lần khác còn nợ (30.000.000đ)',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], '30.000.000' ), $r );
/* Trả lần 1 đúng, rồi lần 2 đưa dư → lúc này không còn lần nào khác nợ. */
$r = VHCP_DuAn::cap_tien_phan( $M, 1, array( 'soTien' => 20000000, 'choLan' => 1 ) );
t( '   trả đúng lần 1 thì chạy', ! empty( $r['success'] ), $r );
$r = VHCP_DuAn::cap_tien_phan( $M, 1, array( 'soTien' => 33000000, 'choLan' => 2 ) );
t( '🔴 lần 2 (lần cuối còn nợ) đưa 33tr thay 30tr → NHẬN', ! empty( $r['success'] ), $r );
teq( '   dư 3tr', 3000000, (int) $r['du'] );
teq( '   lệnh đủ', true, ! empty( $r['xong'] ) );
teq( '   sổ theo lần: lần 2 ghi số thật 33tr', array( 1 => 20000000, 2 => 33000000 ),
	VHCP_DuAn::da_cap_theo_lan( VHCP_DuAn::dot_cua( $M, 1 ) ) );
VHCP_DuAn::delete( $M );

/* ═══ 4. MÀN — không kẹp nữa, nhưng phải NÓI RA ════════════════════════════════════════ */
$HTML = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
$kep  = substr( $HTML, strpos( $HTML, '  function lenhCapKep(' ) );
$kep  = substr( $kep, 0, strpos( $kep, "\n  }" ) );
t( '🔴 `lenhCapKep` KHÔNG còn ghi đè số về phần còn lại', false === strpos( $kep, 'so=con;' ), $kep );
t( '🔴 nhưng vẫn nói ra phần dư ngay khi rời ô', false !== mb_strpos( $kep, 'tạm ứng DƯ' ), $kep );
$gui  = substr( $HTML, strpos( $HTML, '  function lenhGuiCap(' ) );
$gui  = substr( $gui, 0, strpos( $gui, "\n  }" ) );
t( '🔴 nút gửi KHÔNG chặn số vượt nữa', false === mb_strpos( $gui, 'không đưa được' ), $gui );
t( '🔴 mà HỎI XÁC NHẬN kèm con số dư', false !== mb_strpos( $gui, 'Tạm ứng DƯ' ) && false !== strpos( $gui, 'confirm(' ), $gui );
t( '   và câu báo thành công kể phần dư', false !== mb_strpos( $gui, 'NV hoàn lúc quyết toán' ), $gui );
/* 24/09/2026: dư tính theo thực tế (`du`, xem kiem-so-du-theo-thuc-te*.{php,js}), không còn `daTong-tong` cứng. */
t( '🔴 khối "đã cấp" nói ra phần dư thay vì chỉ "đủ"', false !== mb_strpos( $HTML, 'dư <b>\'+money(du)+\'đ</b>\'+theo+\' — kế toán cấp thêm' ) );

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: tạm ứng dư vào sổ đúng số thật, chỉ dư ở lần cuối, màn nói ra chứ không kẹp.\n";
