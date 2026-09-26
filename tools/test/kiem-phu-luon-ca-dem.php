<?php
/**
 * KIỂM "CƠ SỞ PHỤ ĐÃ GHÉP LUÔN LÀ CA ĐÊM" + "CA ĐÊM VỀ MUỘN QUA GIỜ CA NGÀY THÌ THÊM TĂNG CA".
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 26/09/2026, kèm ảnh SETUP_VP mang CẢ HAI hàng "ca chính" và "ca đêm (-CD)" cùng một
 * cặp giờ 20:00→04:00: *"Setup nó là ca đêm rồi mà vẫn phân ca chính, ca đêm à"* / *"setup là ca
 * đêm, thì phân ra ca chính ca phụ chi nữa"*.
 *
 * PHẦN A — màn "Chấm công bù" (và mọi đường ghi tay khác) không còn hỏi "đây là ca đêm" cho một
 * cơ sở PHỤ đã ghép nữa — tự động luôn ghi vào hàng `-CD`. Dữ liệu "ca chính" cũ đã trót có thì
 * KHÔNG bị đụng, vẫn sửa/xoá tay được như trước.
 *
 * PHẦN B — *"vì khi chọn setup bấm 22h00 . sau đí qua ngày hôm sau không chấm để kết ca nó tự mặc
 * định 04h00 là ngừng và tự nhập vào luôn, còn chấm đến 10h00 thì nó hiểu chốt 08h00 và 08h00 -
 * 10h00 là công ca ngày và chuyển giờ đó vào bảng khhcm"* — ca đêm bấm ra thật muộn qua giờ ca
 * ngày (`ngayTu`) thì phần đêm vẫn 1 công đêm, phần dư thêm 1 khoản TĂNG CA (flat, đã hỏi và chốt
 * qua AskUserQuestion — không tính theo tỷ lệ số giờ dư).
 *
 * Chạy: php tools/test/kiem-phu-luon-ca-dem.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
/* ⚠️ BẮT BUỘC TRƯỚC MỌI LƯỢT POST GIẢ LẬP QUA `VHCC_Web::phuc_vu()`. Không có hằng này,
   `VHCC_Web::ve()` gọi `exit` thật ở cuối luồng POST → REDIRECT → GET, giết luôn tiến trình
   PHP đang chạy bài kiểm — mọi phép thử sau lượt gọi đầu tiên biến mất không một dòng báo lỗi
   (xem `class-vhcc-web.php::ve()`). */
define( 'VHCC_TEST', 1 );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $ky_vong, $thuc, $them = null ) {
	t( $ten . ' (kỳ vọng ' . wp_json_encode( $ky_vong, JSON_UNESCAPED_UNICODE ) . ', được '
		. wp_json_encode( $thuc, JSON_UNESCAPED_UNICODE ) . ')', $ky_vong === $thuc, $them );
}
global $wpdb;

/* ═══════════════════════════════════ DỰNG CẢNH CHUNG ═══════════════════════════════════ */
$CHINH = 'PLD_VP'; $PHU = 'PLD_SETUP';
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $CHINH, 'bo_phan' => 'Văn phòng' ) );
/* Cơ sở phụ CỐ Ý không khai bộ phận — đúng hình dạng sản xuất (xem `kiem-don-dem.php`). */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'PLD1', 'ho_ten' => 'Người Setup',
	'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'PLD2', 'ho_ten' => 'Người Setup Hai',
	'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'PLDAD', 'ho_ten' => 'Quản trị',
	'pin_dang_nhap' => '990011', 'vai_tro' => 'Admin' ) );
VHCC_Luong::dat_ghep( array( 'role' => 'Admin' ), array( $PHU => $CHINH ) );

update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq_ad  = VHCC_Auth::login( '990011' );
t( '🔴 dựng cảnh: Admin đăng nhập được bằng PIN', ! empty( $kq_ad['ok'] ), $kq_ad );
$tok_ad = $kq_ad['token'];
$u_ad   = array( 'name' => 'Quản trị', 'role' => 'Admin', 'coso' => '', 'ma_nv' => 'PLDAD' );
$hang = function ( $coso, $ngay, $ma, $hau_to = '' ) use ( $wpdb ) {
	return $wpdb->get_row( $wpdb->prepare(
		'SELECT * FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s AND ngay=%s AND ma_nv=%s AND hau_to=%s',
		$coso, $ngay, $ma, $hau_to ), ARRAY_A );
};
$goi_bu = function ( $dat_bu ) use ( $tok_ad ) {
	global $_COOKIE, $_GET, $_POST;
	$_COOKIE = array( VHCC_Web::COOKIE => $tok_ad );
	$_GET  = array( 'man' => 'cham', 'ccs' => $dat_bu['ccs'], 'cth' => substr( $dat_bu['ngay'], 0, 7 ) );
	$_POST = array_merge( array( 'viec' => 'bu', 'ky' => VHCC_Web::chu_ky( $tok_ad ) ), $dat_bu );
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_POST = array(); $_COOKIE = array();
	return $h;
};

$goi_ve = function ( $get ) use ( $tok_ad ) {
	global $_COOKIE, $_GET, $_POST;
	$_COOKIE = array( VHCC_Web::COOKIE => $tok_ad );
	$_GET  = $get;
	$_POST = array();
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_COOKIE = array();
	return $h;
};

/* ═══════════════════════════════ PHẦN A — CƠ SỞ PHỤ LUÔN LÀ CA ĐÊM ═══════════════════════════════ */

/* 1. Bù một ngày TRỐNG HẲN ở cơ sở PHỤ, KHÔNG kèm `bu_cd` -> vẫn tự ghi thẳng vào `-CD`. */
$h1 = $goi_bu( array( 'ccs' => $PHU, 'ngay' => '2026-09-20', 'ma_nv' => 'PLD1',
	'bu_vao' => '20:00', 'bu_ra' => '04:00', 'ly_do' => 'bù ca đêm, không tích ô' ) );
t( '🔴 cơ sở PHỤ: bù KHÔNG kèm bu_cd vẫn ăn (không bị chối "giờ ra phải muộn hơn giờ vào")',
	false === strpos( $h1, 'Giờ ra phải muộn hơn giờ vào' ), $h1 );
t( '   ghi thẳng vào hàng -CD', null !== $hang( $PHU, '2026-09-20', 'PLD1', 'CD' ) );
t( '   KHÔNG đẻ hàng "ca chính" nào cho ngày đó',
	null === $hang( $PHU, '2026-09-20', 'PLD1', '' ) );

/* 2. `VHCC_Bu::sua()` cho một hàng hậu tố rỗng CHƯA từng tồn tại ở cơ sở phụ -> vẫn bị chối
      "chưa có dòng nào để sửa" (sua() không bao giờ tự tạo hàng mới, phụ hay không). */
$r_sua_moi = VHCC_Bu::sua( $u_ad, array( 'coso' => $PHU, 'ngay' => '2026-09-21', 'ma_nv' => 'PLD1',
	'vao' => '20:00', 'ra' => '04:00', 'ly_do' => 'thử sửa ngày chưa có dòng nào' ) );
t( '🔴 sua() ở cơ sở phụ, ngày chưa có dòng nào: bị chối, không tự tạo hàng mới',
	empty( $r_sua_moi['ok'] ), $r_sua_moi );
t( '   KHÔNG có hàng nào (chính lẫn -CD) được tạo ra',
	null === $hang( $PHU, '2026-09-21', 'PLD1', '' ) && null === $hang( $PHU, '2026-09-21', 'PLD1', 'CD' ) );

/* 3. Cơ sở KHÔNG phải phụ: bù không kèm bu_cd vẫn ghi vào ca chính như cũ (hành vi trước bản vá,
      không bị đổi vì cơ sở này không phải cơ sở luôn-là-ca-đêm). */
$h3 = $goi_bu( array( 'ccs' => $CHINH, 'ngay' => '2026-09-20', 'ma_nv' => 'PLD2',
	'bu_vao' => '08:00', 'bu_ra' => '17:00', 'ly_do' => 'cơ sở chính, ca ngày bình thường' ) );
t( '🔴 cơ sở CHÍNH (không phải phụ): bù không kèm bu_cd vẫn ghi vào ca chính như cũ',
	null !== $hang( $CHINH, '2026-09-20', 'PLD2', '' ) && null === $hang( $CHINH, '2026-09-20', 'PLD2', 'CD' ), $h3 );

/* 3b. Màn "Chấm công bù": giờ PLD1/PLD2 đã có dữ liệu trong tháng 09 (từ mục 1 và 3) nên xuất
      hiện trong lưới — mở hàng bù của một NGÀY KHÁC còn trống để soi đúng ô tích/thông báo.
      Cơ sở PHỤ đã ghép KHÔNG còn hiện ô tích "Đây là ca đêm" nữa — tự động, và nói rõ trên màn.
      Cơ sở CHÍNH thì vẫn giữ ô tích như cũ (một cơ sở chính occasionally có tăng ca đêm thật). */
$h0_phu = $goi_ve( array( 'man' => 'cham', 'ccs' => $PHU, 'cth' => '2026-09',
	'gnd' => '2026-09-25', 'gma' => 'PLD1-CD' ) );
t( '🔴 dựng cảnh: hàng bù mở ra (cơ sở phụ)', strpos( $h0_phu, 'id="suaday"' ) !== false, substr( $h0_phu, 0, 400 ) );
t( '🔴 cơ sở PHỤ: KHÔNG còn ô tích "Đây là ca đêm"', strpos( $h0_phu, 'name="bu_cd"' ) === false, $h0_phu );
t( '   nói rõ cơ sở này luôn là ca đêm, tự động ghi', strpos( $h0_phu, 'Cơ sở này luôn là ca đêm' ) !== false, $h0_phu );

$h0_chinh = $goi_ve( array( 'man' => 'cham', 'ccs' => $CHINH, 'cth' => '2026-09',
	'gnd' => '2026-09-25', 'gma' => 'PLD2' ) );
t( '🔴 dựng cảnh: hàng bù mở ra (cơ sở chính)', strpos( $h0_chinh, 'id="suaday"' ) !== false, substr( $h0_chinh, 0, 400 ) );
t( '🔴 cơ sở CHÍNH: vẫn còn ô tích "Đây là ca đêm" như cũ', strpos( $h0_chinh, 'name="bu_cd"' ) !== false, $h0_chinh );

/* 4. Hàng "ca chính" CŨ đã trót tồn tại (dữ liệu cũ, đúng cảnh trong ảnh anh gửi) ở cơ sở PHỤ:
      vẫn SỬA được nguyên hàng ấy qua `VHCC_Bu::sua()` (không bị bản vá ép lệch sang -CD), và
      hàng -CD của cùng ngày (nếu có) không hề bị đụng. Đây là phép thử QUAN TRỌNG NHẤT của Phần A:
      xác nhận `ep_cd_neu_la_phu()` KHÔNG được gọi trong `sua()` — gọi nhầm vào đó sẽ khiến lượt
      sửa hàng ca chính cũ đi tìm nhầm hàng -CD và bị chối "chưa có dòng nào để sửa", trong khi
      hàng ca chính thật sự đang có số trên màn hình. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-22',
	'ma_nv' => 'PLD1', 'hau_to' => '', 'ho_ten' => 'Người Setup',
	'gio_vao_giay' => VHCC_DB::giay( '20:00:00' ), 'gio_ra_giay' => VHCC_DB::giay( '04:00:00' ) + VHCC_DB::NGAY_GIAY,
	'nguon' => 'may', 'chuan' => 'dữ liệu cũ trước bản vá' ) );
/* ⚠️ CHỈ SỬA GIỜ VÀO, KHÔNG ĐỤNG GIỜ RA. `sua()` chỉ trải phẳng giờ ra khi hậu tố là CD/CT/TC
   (xem chú thích tại đó) — hàng "ca chính" (hậu tố '') không được trải, kể cả trước bản vá này;
   gõ một giờ ra xuyên đêm mới vào ô hậu tố rỗng vẫn bị chối "giờ ra phải muộn hơn giờ vào", và đó
   là chuyện KHÁC với Phần A đang canh ở đây. Phần A chỉ cần chứng minh: lượt sửa nhắm ĐÚNG hàng
   ca chính đang có (không bị ép lệch sang -CD), nên chỉ cần đổi giờ vào là đủ thấy rõ. */
$r_sua_cu = VHCC_Bu::sua( $u_ad, array( 'coso' => $PHU, 'ngay' => '2026-09-22', 'ma_nv' => 'PLD1',
	'vao' => '19:30', 'ly_do' => 'sửa lại đúng giờ vào của hàng ca chính cũ' ) );
t( '🔴 hàng "ca chính" CŨ ở cơ sở phụ: vẫn SỬA được đúng hàng ấy, không bị lệch sang -CD',
	! empty( $r_sua_cu['ok'] ), $r_sua_cu );
$h_cu_sau = $hang( $PHU, '2026-09-22', 'PLD1', '' );
teq( '   giờ vào đã đổi đúng trên CHÍNH hàng ca chính cũ', '19:30:00',
	$h_cu_sau ? VHCC_DB::hhmmss( $h_cu_sau['gio_vao_giay'] ) : '', $h_cu_sau );
teq( '   giờ ra (trải phẳng) vẫn nguyên, không bị đụng', '04:00:00',
	$h_cu_sau ? VHCC_DB::hhmmss( $h_cu_sau['gio_ra_giay'] ) : '', $h_cu_sau );

/* ═══════════════════════════════ PHẦN B — CA ĐÊM VỀ MUỘN QUA GIỜ CA NGÀY ═══════════════════════════════ */

$cfg = VHCC_Luong::vp_cfg( '' );   // mặc định: ngayTu=08:30, tangCaCong=0.5, demCong=1.

/* 5. Ca đêm 22:00 -> 10:00 hôm sau: vượt xa ngayTu (08:30) trên trục trải phẳng.
      congDem vẫn đúng 1 (đêm được công nhận đủ), CỘNG THÊM đúng 1 khoản tangCaCong (0.5),
      và cờ veMuonQuaCaNgay bật lên để màn hình soi được vì sao có thêm tăng ca. */
$r5 = VHCC_Luong::vp_tinh_nguoi( $cfg, false, array(
	'2026-09-05' => array( 'dem' => array( VHCC_DB::giay( '22:00:00' ),
		VHCC_DB::giay( '10:00:00' ) + VHCC_DB::NGAY_GIAY ) ),
) );
t( '🔴 dựng cảnh: ngày 05/09 có dòng', isset( $r5['2026-09-05'] ), implode( ',', array_keys( $r5 ) ) );
if ( isset( $r5['2026-09-05'] ) ) {
	teq( '   công đêm vẫn đúng 1 (phần đêm không đổi)', 1.0, (float) $r5['2026-09-05']['congDem'] );
	teq( '   CỘNG THÊM đúng 1 khoản tăng ca (0.5, flat — không theo tỷ lệ giờ dư)',
		0.5, (float) $r5['2026-09-05']['congTangCa'] );
	t( '   cờ "về muộn qua ca ngày" bật lên', ! empty( $r5['2026-09-05']['veMuonQuaCaNgay'] ), $r5['2026-09-05'] );
}

/* 6. Ca đêm BÌNH THƯỜNG 22:00 -> 04:00 (không vượt ngayTu): congTangCa KHÔNG đổi so với trước
      bản vá — không được vô tình cộng thêm cho một ca đêm bình thường. */
$r6 = VHCC_Luong::vp_tinh_nguoi( $cfg, false, array(
	'2026-09-06' => array( 'dem' => array( VHCC_DB::giay( '22:00:00' ),
		VHCC_DB::giay( '04:00:00' ) + VHCC_DB::NGAY_GIAY ) ),
) );
t( 'dựng cảnh: ngày 06/09 có dòng', isset( $r6['2026-09-06'] ), implode( ',', array_keys( $r6 ) ) );
if ( isset( $r6['2026-09-06'] ) ) {
	teq( '🔴 ca đêm bình thường (không về muộn): congTangCa KHÔNG đổi (vẫn 0)',
		0.0, (float) $r6['2026-09-06']['congTangCa'] );
	t( '   và KHÔNG bật cờ "về muộn qua ca ngày"', empty( $r6['2026-09-06']['veMuonQuaCaNgay'] ), $r6['2026-09-06'] );
	teq( '   công đêm vẫn đúng 1', 1.0, (float) $r6['2026-09-06']['congDem'] );
}

/* 7. Đổi cấu hình `ngayTu` sang giờ khác -> mốc "về muộn" tự đổi theo, không cần sửa code.
      Ca 22:00 -> 10:00 hôm sau: nếu ngayTu dời sang 11:00 thì 10:00 KHÔNG còn vượt ngưỡng nữa. */
$cfg_tu_tre = $cfg; $cfg_tu_tre['ngayTu'] = '11:00';
$r7 = VHCC_Luong::vp_tinh_nguoi( $cfg_tu_tre, false, array(
	'2026-09-07' => array( 'dem' => array( VHCC_DB::giay( '22:00:00' ),
		VHCC_DB::giay( '10:00:00' ) + VHCC_DB::NGAY_GIAY ) ),
) );
if ( isset( $r7['2026-09-07'] ) ) {
	teq( '🔴 đổi ngayTu sang 11:00: ca 22:00->10:00 KHÔNG còn vượt ngưỡng, congTangCa không đổi',
		0.0, (float) $r7['2026-09-07']['congTangCa'] );
}
/* Cùng cấu hình ngayTu=11:00, nhưng ca ra tới 12:00 thì lại vượt — mốc đã dời theo cấu hình. */
$r7b = VHCC_Luong::vp_tinh_nguoi( $cfg_tu_tre, false, array(
	'2026-09-08' => array( 'dem' => array( VHCC_DB::giay( '22:00:00' ),
		VHCC_DB::giay( '12:00:00' ) + VHCC_DB::NGAY_GIAY ) ),
) );
if ( isset( $r7b['2026-09-08'] ) ) {
	teq( '   ra tới 12:00 (sau ngưỡng mới 11:00): CỘNG THÊM tăng ca',
		0.5, (float) $r7b['2026-09-08']['congTangCa'] );
}

/* 8. Ca đêm THIẾU GIỜ TỐI THIỂU (demThieuGio=true) mà vẫn về muộn -> KHÔNG cộng thêm tăng ca.
      Đêm chưa được công nhận thì chưa vội cộng thêm gì lên trên nó. */
$cfg_toi_thieu = $cfg; $cfg_toi_thieu['demToiThieuGio'] = 10;
$r8 = VHCC_Luong::vp_tinh_nguoi( $cfg_toi_thieu, false, array(
	'2026-09-09' => array( 'dem' => array( VHCC_DB::giay( '22:00:00' ),
		VHCC_DB::giay( '10:00:00' ) + VHCC_DB::NGAY_GIAY ) ),
) );
if ( isset( $r8['2026-09-09'] ) ) {
	t( '🔴 dựng cảnh: đêm bị đánh dấu thiếu giờ tối thiểu', ! empty( $r8['2026-09-09']['demThieuGio'] ), $r8['2026-09-09'] );
	teq( '   đêm CHƯA được công nhận -> KHÔNG cộng thêm tăng ca dù đã về muộn',
		0.0, (float) $r8['2026-09-09']['congTangCa'] );
}

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — cơ sở phụ luôn là ca đêm, ca đêm về muộn được cộng thêm tăng ca đúng luật.\n";
