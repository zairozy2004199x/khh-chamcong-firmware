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

/* 3b. Màn "Chấm công bù", mở qua `ccs=$PHU`: giờ PLD1/PLD2 đã có dữ liệu trong tháng 09 (từ mục
      1 và 3) nên xuất hiện trong lưới — mở hàng bù của một NGÀY KHÁC còn trống để soi đúng ô
      tích/thông báo.
      ⚠️ 26/09/2026 — SAU BẢN "GHÉP LẠI THÀNH 1 BẢNG": Admin có quyền xem CẢ HAI cơ sở nên
      `ccs=$PHU` nay bị `the_bang_cham()` đổi thẳng sang lưới CỦA CƠ SỞ CHÍNH (xem khối redirect
      trong `class-vhcc-web.php`) — không còn lưới riêng-cơ-sở-phụ để mà hỏi "ô tích ẩn hay hiện"
      nữa. Lưới CHÍNH vẽ ca đêm NGAY TRONG CÙNG Ô (không còn hậu tố `-CD` làm một pseudo-hàng
      riêng), nên địa chỉ ô sửa cũng đổi theo: `gma` giờ là MÃ TRẦN (`PLD1`), không phải
      `PLD1-CD`. Và vì `hang_sua()` nhận `$cs` = cơ sở CHÍNH (đã đổi), ô tích "Đây là ca đêm" hiện
      lại NHƯ CƠ SỞ CHÍNH — tự động luôn-là-ca-đêm chỉ còn áp dụng cho người KHÔNG có quyền xem cơ
      sở chính (vẫn kẹt ở lưới riêng cũ, xem bài thử `test-cham-cong.php` — mục "Ghép bảng công"
      đã kiểm đúng nhánh đó với một tài khoản Cửa hàng trưởng bị giới hạn quyền). */
$h0_phu = $goi_ve( array( 'man' => 'cham', 'ccs' => $PHU, 'cth' => '2026-09',
	'gnd' => '2026-09-25', 'gma' => 'PLD1' ) );
t( '🔴 dựng cảnh: hàng bù mở ra (mở qua cơ sở phụ, nhưng đã hiện thẳng lưới cơ sở chính)',
	strpos( $h0_phu, 'id="suaday"' ) !== false, substr( $h0_phu, 0, 400 ) );
t( '   nói rõ đang hiện thẳng bảng của cơ sở chính (không còn hai bảng rời nhau)',
	strpos( $h0_phu, 'đang hiện thẳng bảng của' ) !== false, $h0_phu );
t( '🔴 Admin có quyền xem cả hai -> vẫn còn ô tích "Đây là ca đêm" (nay là lưới cơ sở CHÍNH)',
	strpos( $h0_phu, 'name="bu_cd"' ) !== false, $h0_phu );

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

/* ═══════════════════════════════ PHẦN C — CHẤM TỰ ĐỘNG (MÁY/ĐIỆN THOẠI) GIỮA BAN NGÀY ═══════════════════════════════
 *
 * 26/09/2026 — anh Thắng chỉ ra dữ liệu THẬT vẫn lọt qua đường chấm TỰ ĐỘNG dù Phần A đã chặn
 * đường bù tay: một lượt chấm 09:45→12:04 giữa ban ngày ở SETUP_VP (cơ sở PHỤ) vẫn bị
 * `dinh_tuyen()`/`trai_phang()` cho rơi vào hàng ca ngày (hậu tố rỗng), vì hai hàm đó chỉ biết
 * "trước demDen" và "sau ngayDen" là ca đêm — khoảng GIỮA (giờ hành chính bình thường) thì mặc
 * định là ca ngày, đúng cho cơ sở CHÍNH nhưng SAI cho cơ sở PHỤ (không có ca ngày để mà rơi vào).
 * *"bên setup nó luôn luôn là ca đêm, còn khhcm luôn luôn là ca ngày"*. */

$cfg_c = VHCC_Luong::vp_cfg( $CHINH );

/* 9. `dinh_tuyen()` trực tiếp: giờ 09:45 (giữa demDen 06:00 và ngayDen 17:00) ở cơ sở PHỤ ->
      phải trả về định tuyến ca đêm CÙNG NGÀY, không còn `null` (ca ngày) nữa. */
$t9 = VHCC_Online::dinh_tuyen( $PHU, '2026-09-15', VHCC_DB::giay( '09:45:00' ) );
t( '🔴 dinh_tuyen(): giờ giữa ban ngày ở cơ sở PHỤ -> định tuyến ca đêm, không còn null',
	is_array( $t9 ) && 'CD' === $t9['duoi'] && '2026-09-15' === $t9['ngay'], $t9 );

/* 10. Cùng giờ, cùng khe, nhưng ở cơ sở CHÍNH -> vẫn null (ca ngày) như cũ, không đổi hành vi. */
$t10 = VHCC_Online::dinh_tuyen( $CHINH, '2026-09-15', VHCC_DB::giay( '09:45:00' ) );
t( '🔴 dinh_tuyen(): cùng giờ ở cơ sở CHÍNH -> vẫn null (ca ngày), KHÔNG bị đổi hành vi',
	null === $t10, $t10 );

/* 11. `trai_phang()` trực tiếp: cùng giờ 09:45 ở cơ sở PHỤ -> giữ nguyên trục (không trải +24h,
       không còn null). Ở cơ sở CHÍNH thì vẫn null như cũ. */
teq( '🔴 trai_phang(): cơ sở PHỤ -> giữ nguyên giây, không trải, không null',
	VHCC_DB::giay( '09:45:00' ), VHCC_Online::trai_phang( VHCC_DB::giay( '09:45:00' ), $cfg_c, $PHU ) );
t( '   cơ sở CHÍNH -> vẫn null như cũ',
	null === VHCC_Online::trai_phang( VHCC_DB::giay( '09:45:00' ), $cfg_c, $CHINH ) );

/* 12. TRỌN ĐƯỜNG GHI THẬT (nguồn 'may'), không có ca đêm nào đang mở hôm trước: một lượt chấm
       VÀO lúc 09:45 ở cơ sở PHỤ, ngày TRỐNG hẳn -> phải mở đúng hàng `-CD` của CHÍNH ngày đó,
       KHÔNG mở hàng hậu tố rỗng nào. Đây là đúng cảnh anh Thắng gặp. */
VHCC_Nhan::ghi_gio( $PHU, '2026-09-16', 'PLD1', 'Người Setup', VHCC_DB::giay( '09:45:00' ), '', 'may' );
$h12_cd = $hang( $PHU, '2026-09-16', 'PLD1', 'CD' );
t( '🔴 lượt chấm MÁY 09:45 giữa ban ngày ở cơ sở PHỤ -> mở đúng hàng -CD',
	$h12_cd && VHCC_DB::giay( '09:45:00' ) === (int) $h12_cd['gio_vao_giay'], $h12_cd );
t( '   KHÔNG mở hàng ca ngày (hậu tố rỗng) nào',
	null === $hang( $PHU, '2026-09-16', 'PLD1', '' ) );

/* 13. Chấm tiếp giờ RA lúc 12:04 cùng ngày -> phải rơi ĐÚNG vào cùng hàng -CD vừa mở (giờ ra),
       không mở thêm hàng nào khác — đi qua đúng cửa ghi dùng chung, không cần chỉnh gì thêm. */
VHCC_Nhan::ghi_gio( $PHU, '2026-09-16', 'PLD1', 'Người Setup', VHCC_DB::giay( '12:04:00' ), '', 'may' );
$h13_cd = $hang( $PHU, '2026-09-16', 'PLD1', 'CD' );
t( '🔴 giờ ra 12:04 cùng ngày rơi đúng vào CÙNG hàng -CD (không mở hàng mới)',
	$h13_cd && VHCC_DB::giay( '09:45:00' ) === (int) $h13_cd['gio_vao_giay']
	&& VHCC_DB::giay( '12:04:00' ) === (int) $h13_cd['gio_ra_giay'], $h13_cd );

/* 14. Tính công cả chùm cho ngày 16 -> KHÔNG còn công ngày giả nào ở cơ sở CHÍNH từ lượt chấm
       giữa ngày này ở SETUP (đúng thứ anh Thắng báo: "có làm đâu" mà vẫn ra 1 công ngày). */
$r14 = VHCC_Luong::vp_tinh_nguoi( $cfg_c, false, array(
	'2026-09-16' => array( 'chinh' => null,
		'dem' => array( VHCC_DB::giay( '09:45:00' ), VHCC_DB::giay( '12:04:00' ) ) ),
) );
if ( isset( $r14['2026-09-16'] ) ) {
	teq( '🔴 lượt giữa ngày ở SETUP không sinh công ngày giả cho VP_KH-HCM',
		0.0, (float) $r14['2026-09-16']['congNgay'] );
}

/* 15. Cơ sở CHÍNH chấm giữa ngày (09:45→12:04, một ca ngày ngắn thật) -> KHÔNG bị đổi hành vi,
       vẫn mở đúng hàng hậu tố rỗng như trước bản vá. */
VHCC_Nhan::ghi_gio( $CHINH, '2026-09-16', 'PLD2', 'Người VP', VHCC_DB::giay( '09:45:00' ), '', 'may' );
$h15 = $hang( $CHINH, '2026-09-16', 'PLD2', '' );
t( '🔴 cơ sở CHÍNH chấm giữa ngày: vẫn mở hàng ca ngày (hậu tố rỗng) như cũ',
	$h15 && VHCC_DB::giay( '09:45:00' ) === (int) $h15['gio_vao_giay'], $h15 );

/* ═══════════════════════════════ PHẦN D — KHÔNG TỰ Ý ẨN "CÔNG BÙ" Ở TẦNG TÍNH TOÁN ═══════════════════════════════
 *
 * 26/09/2026 — anh Thắng: *"không tự ý tùy tiện bảo không liên quan thì hiện"* / *"không hiện
 * lăng nhằng như này"* — bản vá trước (ẩn công bù ngay trong `vp_bang_cong_va_luong_voi()`) bị
 * gỡ lại: `vp_tinh_nguoi()`/`vp_bang_cong_va_luong_voi()` là TẦNG TÍNH TOÁN THUẦN, không được tự
 * ý giấu số dựa trên "cơ sở nào đang hỏi" — con số phải LUÔN đúng và ĐẦY ĐỦ ở tầng này, dù xem
 * từ cơ sở phụ hay cơ sở chính. Việc "khỏi phải mở hai bảng rời nhau để đối chiếu" chốt là
 * *"mở SETUP_VP thì hiện thẳng lưới KH-HCM"* — xử lý ở TẦNG MÀN HÌNH (`class-vhcc-web.php`,
 * xem `kiem-...` cho phần đó), không phải bằng cách giấu số ở đây. */

/* 16. Xem THẲNG bảng của cơ sở PHỤ ($PHU) — công bù ngày 21 (từ ca đêm PLD1 đã bù ở mục 1,
       20:00→04:00 ngày 20/09) phải CÒN NGUYÊN, KHÔNG bị ẩn. */
$vp_phu = VHCC_Luong::vp_bang_cong_va_luong( $PHU, '2026-09' );
$e_phu_1 = null;
foreach ( $vp_phu['rows'] as $r ) { if ( 'PLD1' === $r['ma'] ) { $e_phu_1 = $r; } }
t( '🔴 xem bảng của SETUP_VP: công bù ngày 21 VẪN HIỆN, không bị tự ý ẩn',
	$e_phu_1 && (float) $e_phu_1['congBu'] > 0, $e_phu_1 );

/* 17. Xem bảng của cơ sở CHÍNH ($CHINH, chùm gồm cả PHU) — công bù đúng bằng con số ở trên, hai
       nơi phải KHỚP NHAU (cùng một tầng tính toán, không tách riêng theo cơ sở đang hỏi). */
$vp_chinh = VHCC_Luong::vp_bang_cong_va_luong( $CHINH, '2026-09' );
$e_chinh_1 = null;
foreach ( $vp_chinh['rows'] as $r ) { if ( 'PLD1' === $r['ma'] ) { $e_chinh_1 = $r; } }
teq( '🔴 xem bảng của KH-HCM: công bù KHỚP đúng con số bên SETUP_VP, không lệch nhau',
	$e_phu_1 ? (float) $e_phu_1['congBu'] : null, $e_chinh_1 ? (float) $e_chinh_1['congBu'] : null );

/* ═══════════ PHẦN E — CHẤM BẢNG CÔNG NÀO TÍNH BẢNG CÔNG ĐẤY (26/09/2026) ═══════════
   Anh Thắng, ảnh ngày 06/09: hàng "ca chính" CŨ ở SETUP_VP (19:51 → —) cùng ngày với lượt chấm
   thật ở VP_KH-HCM (08:48 → 17:25) — ô ca ngày hiện "0 ? SETUP_VP", công ngày thật bị đè mất.
   *"chấm bảng công nào tính bảng công đấy, cho dù giờ đó thuộc công ngày, nhưng chấm bảng công
   setup thì quy nó setup là được"*. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'PLE1', 'ho_ten' => 'Người Hai Bảng',
	'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CHINH, 'ngay' => '2026-09-06', 'ma_nv' => 'PLE1',
	'hau_to' => '', 'ho_ten' => 'Người Hai Bảng', 'gio_vao_giay' => VHCC_DB::giay( '08:48:00' ),
	'gio_ra_giay' => VHCC_DB::giay( '17:25:00' ), 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-06', 'ma_nv' => 'PLE1',
	'hau_to' => '', 'ho_ten' => 'Người Hai Bảng', 'gio_vao_giay' => VHCC_DB::giay( '19:51:00' ),
	'gio_ra_giay' => null, 'nguon' => 'may', 'chuan' => 'dữ liệu cũ trước bản vá' ) );
$ngay_e = function ( $ngay ) use ( $CHINH ) {
	foreach ( VHCC_Luong::vp_bang_cong_va_luong( $CHINH, '2026-09' )['detail'] as $d ) {
		if ( 'PLE1' === $d['ma'] && $ngay === $d['ngay'] ) { return $d; }
	}
	return null;
};
$d_e = $ngay_e( '2026-09-06' );
teq( '🔴 lượt chấm thật ở cơ sở CHÍNH KHÔNG bị hàng cũ của cơ sở phụ đè: 1 công ngày',
	1.0, $d_e ? (float) $d_e['congNgay'] : -1.0, $d_e );
teq( '   giờ ca ngày là đúng giờ chấm ở cơ sở chính', '08:48-17:25', $d_e ? $d_e['vao'] . '-' . $d_e['ra'] : '' );
teq( '   hàng cũ của cơ sở phụ rơi vào HÀNG ĐÊM', '19:51', $d_e ? $d_e['h2vao'] : '' );
teq( '   ô ca ngày KHÔNG mang nhãn cơ sở phụ', '', $d_e ? (string) $d_e['tuCoSo'] : 'x' );
teq( '   ô ca đêm mang nhãn cơ sở phụ', $PHU, $d_e ? (string) $d_e['tuCoSoDem'] : '' );

/* Sửa giờ ra 04:00 cho chính hàng cũ ấy -> lưu được ("không lưu được"), và ra 1 công đêm. */
$r_e = VHCC_Bu::sua( $u_ad, array( 'coso' => $PHU, 'ngay' => '2026-09-06', 'ma_nv' => 'PLE1',
	'vao' => '19:51', 'ra' => '04:00', 'ly_do' => 'bù giờ ra ca setup' ) );
t( '🔴 hàng cũ của cơ sở phụ: sửa giờ ra 04:00 LƯU ĐƯỢC', ! empty( $r_e['ok'] ), $r_e );
$d_e = $ngay_e( '2026-09-06' );
teq( '   và ra 1 công đêm', 1.0, $d_e ? (float) $d_e['congDem'] : -1.0, $d_e );
teq( '   công ngày ở cơ sở chính vẫn nguyên 1', 1.0, $d_e ? (float) $d_e['congNgay'] : -1.0 );

/* *"nhiều lúc đi sớm bạn chấm nó dính giờ công ngày thì kệ nó, chỉ lấy giờ ra làm mốc"* — vào
   SETUP 14:00 (giờ ca ngày), ra 20:30 cùng ngày (chưa chạm khung đêm — trước đây ra "ca lạ"): vẫn là CA ĐÊM của SETUP, không thành tăng ca /
   ca lạ, không cộng công ngày nào. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-10', 'ma_nv' => 'PLE1',
	'hau_to' => 'CD', 'ho_ten' => 'Người Hai Bảng', 'gio_vao_giay' => VHCC_DB::giay( '14:00:00' ),
	'gio_ra_giay' => VHCC_DB::giay( '20:30:00' ), 'nguon' => 'may' ) );
$d_s = $ngay_e( '2026-09-10' );
teq( '🔴 vào SETUP sớm (14:00) vẫn ra 1 công ĐÊM', 1.0, $d_s ? (float) $d_s['congDem'] : -1.0, $d_s );
t( '   không bị coi là ca lạ, không tăng ca, không công ngày',
	$d_s && empty( $d_s['caLa'] ) && 0.0 === (float) $d_s['congTangCa'] && 0.0 === (float) $d_s['congNgay'], $d_s );
teq( '   công bù hôm sau theo GIỜ RA 20:30 (chưa qua nửa đêm, trước mốc 1) -> 0.5',
	0.5, ( $n11 = $ngay_e( '2026-09-11' ) ) ? (float) $n11['congBu'] : -1.0 );

/* Hàng SETUP đã "Xoá công" (giờ vào lẫn giờ ra rỗng, hàng giữ làm dấu vết) KHÔNG phải ca đêm
   thiếu giờ — *"có công đêm đâu mà hiện như này người khác hiểu lầm"*. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CHINH, 'ngay' => '2026-09-24', 'ma_nv' => 'PLE1',
	'hau_to' => '', 'ho_ten' => 'Người Hai Bảng', 'gio_vao_giay' => VHCC_DB::giay( '08:55:00' ),
	'gio_ra_giay' => VHCC_DB::giay( '21:35:00' ), 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-24', 'ma_nv' => 'PLE1',
	'hau_to' => 'CD', 'ho_ten' => 'Người Hai Bảng', 'gio_vao_giay' => null, 'gio_ra_giay' => null,
	'nguon' => 'sua', 'chuan' => 'đã xoá công' ) );
$d_x = $ngay_e( '2026-09-24' );
t( '🔴 hàng đã xoá trắng giờ -> KHÔNG thành "ca đêm thiếu một đầu giờ"',
	$d_x && empty( $d_x['demChuaDuCap'] ) && '' === $d_x['h2vao'] && '' === $d_x['h2ra'], $d_x );
teq( '   và KHÔNG gắn nhãn cơ sở phụ vào hàng đêm', '', $d_x ? (string) $d_x['tuCoSoDem'] : 'x' );
teq( '   công ngày ở cơ sở chính vẫn nguyên', true, $d_x && (float) $d_x['congNgay'] > 0 );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — cơ sở phụ luôn là ca đêm, ca đêm về muộn được cộng thêm tăng ca đúng luật.\n";
