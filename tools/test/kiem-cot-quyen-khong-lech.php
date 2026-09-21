<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG PHÂN QUYỀN ĐỌC THEO VỊ TRÍ — THÊM MỘT VAI LÀ MỌI CỘT TRƯỢT.
 *
 * Anh Thắng 13/09/2026 gửi ảnh màn Duyệt tạm ứng của chị Nguyễn Thị Phương Hòa (vai **Quản lý**):
 * hai đơn "Chờ duyệt tạm ứng" chỉ có nút 👁 Xem và ↩ Trả lại — **không có nút ✔ Duyệt tạm ứng**.
 *
 * =============================================================================================
 * 🔴 GỐC RỄ: `CH_Quyen` LƯU GIÁ TRỊ THEO THỨ TỰ CỘT, KHÔNG THEO TÊN VAI.
 *
 *    Một hàng là: [ mã, tên hành động, <ô của vai thứ 1>, <ô của vai thứ 2>, … ] — thứ tự vai
 *    lấy từ `VHCP_Cfg::roles()`, tức `VAI_GOC` rồi tới vai tự tạo. Không có gì trong hàng nói
 *    ô nào thuộc vai nào.
 *
 *    Bản 1.154.0 chèn **'Giám đốc' vào ĐẦU** `VAI_GOC` (trước đó danh sách mở đầu bằng 'Quản
 *    lý'). Bảng đã lưu trên site của anh Thắng vẫn theo thứ tự CŨ, nên từ lượt đọc đầu tiên
 *    sau khi cài đè, MỌI Ô TRƯỢT SANG PHẢI ĐÚNG MỘT VAI:
 *
 *        ô lưu cho  Quản lý          -> đọc thành  Giám đốc
 *        ô lưu cho  Kế toán cá nhân  -> đọc thành  Quản lý
 *        ô lưu cho  Kế toán NCC      -> đọc thành  Kế toán cá nhân
 *        ô lưu cho  Nhân viên        -> đọc thành  Kế toán NCC
 *        (không còn ô nào)           -> Nhân viên = chưa khai
 *
 *    Khớp đúng ảnh: `duyetTU` lưu Quản lý=1 và ba vai kia=0, nên sau khi trượt thì Quản lý
 *    nhận ô của Kế toán cá nhân = 0 -> **mất nút Duyệt**. Còn `traDon` lưu Quản lý=1, Kế toán
 *    cá nhân=1, nên Quản lý nhận ô của Kế toán cá nhân = 1 -> **nút Trả lại vẫn còn**. Đúng
 *    hai nút trên ảnh, không sai một cái nào.
 *
 * ⚠️ HỎNG RỘNG HƠN MỘT CÁI NÚT. Cùng phép trượt ấy: Kế toán cá nhân mất "Cấp tạm ứng", Kế toán
 *    NCC mất "Xác nhận quyết toán", Nhân viên mất mọi thứ vốn được khai. Cả dây chuyền tiền
 *    đứng lại, mà màn hình không báo gì — chỉ là nút không hiện ra.
 *
 * ⚠️ VÀ `read()` KHÔNG LỘ RA ĐƯỢC CHỖ THIẾU: nó tự đệm hàng cho đủ số cột hiện tại, nên hàng
 *    lưu theo danh sách vai cũ đọc ra vẫn "đủ ô" — chỉ là lệch. Phép dò phải đọc THÔ.
 *
 * Chạy: php tools/test/kiem-cot-quyen-khong-lech.php
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

/** Ghi bảng quyền Y HỆT một site đã lưu TRƯỚC khi có vai 'Giám đốc' — bốn ô, không phải năm. */
function ghi_bang_quyen_cu() {
	global $wpdb;
	$t = VHCP_DB::t( 'cfg' );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE bang=%s", VHCP_Cfg::QUYEN ) );
	/* [ mã, tên, Quản lý, Kế toán cá nhân, Kế toán NCC, Nhân viên ] — thứ tự vai CŨ. */
	$cu = array(
		array( 'duyetTU',   'Duyệt tạm ứng',        '1', '',  '',  ''  ),
		array( 'capTU',     'Cấp (gửi) tạm ứng',    '',  '1', '',  ''  ),
		array( 'traDon',    'Trả lại đơn',          '1', '1', '1', ''  ),
		array( 'xacNhanQT', 'Xác nhận quyết toán',  '',  '1', '1', ''  ),
		array( 'xinTU',     'Xin tạm ứng',          '1', '',  '',  '1' ),
	);
	$i = 0;
	foreach ( $cu as $r ) {
		$i++;
		$wpdb->insert( $t, array( 'bang' => VHCP_Cfg::QUYEN, 'stt' => $i, 'cols' => wp_json_encode( $r ) ) );
	}
	/* Xoá mốc cột: site cũ chưa bao giờ ghi nó, và chính chỗ thiếu mốc ấy là thứ bản vá phải
	   nhận ra để suy ra thứ tự vai đang lưu. */
	VHCP_Meta::del( 'quyen_cot_vai' );
	VHCP_Cfg::clear_cache();
	delete_transient( 'vhcp_quyen' );
}

/** Đúng những gì `plugins_loaded` làm mỗi lượt tải trang. */
function nap_trang() {
	VHCP_Cfg::va_cot_quyen_them_vai();
	delete_transient( 'vhcp_quyen' );
	return VHCP_Cfg::get_quyen();
}

/* ═══ 1. CẢNH THẬT: SITE ĐÃ LƯU BẢNG QUYỀN TỪ TRƯỚC BẢN 1.154.0 ══════════════════ */
t( '🔴 "Giám đốc" đứng ĐẦU danh sách vai — đúng chỗ chèn gây trượt cột',
	'Giám đốc' === VHCP_Cfg::roles()[0], VHCP_Cfg::roles() );

/* Trước khi vá — chứng minh lỗi CÓ THẬT, không phải suy đoán. */
ghi_bang_quyen_cu();
$truoc = VHCP_Cfg::get_quyen();
t( '🔴 (dựng lại đúng lỗi) chưa vá thì Quản lý MẤT quyền duyệt',
	empty( $truoc['duyetTU']['Quản lý'] ), $truoc['duyetTU'] );
t( '   ...trong khi vẫn còn quyền trả lại — đúng hai nút trên ảnh anh Thắng gửi',
	! empty( $truoc['traDon']['Quản lý'] ), $truoc['traDon'] );

/* Nạp trang một lượt = bản vá chạy. */
ghi_bang_quyen_cu();
$q = nap_trang();

/* Đây là phép quan trọng nhất: đúng cái nút anh Thắng không thấy. */
t( '🔴 QUẢN LÝ duyệt được tạm ứng (nút ✔ Duyệt tạm ứng)',
	! empty( $q['duyetTU']['Quản lý'] ), $q['duyetTU'] );
t( '   và vẫn trả lại được đơn (nút ↩ Trả lại — cái này VẪN hiện trên ảnh)',
	! empty( $q['traDon']['Quản lý'] ), $q['traDon'] );

/* Cùng phép trượt ấy làm hỏng cả dây chuyền tiền, không chỉ một nút. */
t( '🔴 KẾ TOÁN CÁ NHÂN cấp được tạm ứng',
	! empty( $q['capTU']['Kế toán cá nhân'] ), $q['capTU'] );
t( '🔴 KẾ TOÁN NCC xác nhận được quyết toán',
	! empty( $q['xacNhanQT']['Kế toán NCC'] ), $q['xacNhanQT'] );
t( '🔴 NHÂN VIÊN xin được tạm ứng',
	! empty( $q['xinTU']['Nhân viên'] ), $q['xinTU'] );

/* Và những ô vốn TẮT thì phải vẫn tắt — trượt ngược cũng là trượt. */
t( '⚠️ Kế toán NCC KHÔNG tự dưng cấp được tạm ứng',
	empty( $q['capTU']['Kế toán NCC'] ), $q['capTU'] );
t( '⚠️ Nhân viên KHÔNG tự dưng trả lại được đơn',
	empty( $q['traDon']['Nhân viên'] ), $q['traDon'] );

/* Giám đốc là vai MỚI, chưa có ô nào trong bảng cũ. Anh Thắng 13/09/2026: *"Giám Đốc: Toàn
   Quyền Xem"* — nên cho theo Quản lý, chứ không để trắng tay. */
t( '✅ Giám đốc (vai mới) nhận được quyền, không trắng tay',
	! empty( $q['duyetTU']['Giám đốc'] ), $q['duyetTU'] );

/* ═══ 2. CHẠY LẠI KHÔNG ĐƯỢC DỜI THÊM LẦN NỮA ════════════════════════════════════
 * 🔴 Bước vá chạy ở `plugins_loaded`, tức MỌI lượt tải trang. Dời hai lần là các cột trượt
 *    tiếp sang phải một nhịp nữa — lần này thì không ai lần ra nguyên nhân.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
nap_trang(); nap_trang();
$q2 = nap_trang();
teq( '🔴 chạy vá thêm hai lượt nữa: Quản lý VẪN duyệt được', true, ! empty( $q2['duyetTU']['Quản lý'] ) );
teq( '   Kế toán cá nhân VẪN cấp được',   true, ! empty( $q2['capTU']['Kế toán cá nhân'] ) );
teq( '   và Kế toán NCC VẪN không cấp được', true, empty( $q2['capTU']['Kế toán NCC'] ) );

/* ═══ 3. BẢNG LƯU ĐÚNG THỜI (đã đủ ô) THÌ KHÔNG ĐƯỢC ĐỤNG VÀO ════════════════════ */
global $wpdb;
$t = VHCP_DB::t( 'cfg' );
$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE bang=%s", VHCP_Cfg::QUYEN ) );
/* [ mã, tên, Giám đốc, Quản lý, Kế toán cá nhân, Kế toán NCC, Nhân viên ] — thứ tự vai MỚI. */
$wpdb->insert( $t, array( 'bang' => VHCP_Cfg::QUYEN, 'stt' => 1,
	'cols' => wp_json_encode( array( 'duyetTU', 'Duyệt tạm ứng', '', '1', '', '', '' ) ) ) );
VHCP_Meta::set_json( 'quyen_cot_vai', VHCP_Cfg::roles() );   // bảng này lưu ĐÚNG thời
VHCP_Cfg::clear_cache(); delete_transient( 'vhcp_quyen' );
$q3 = nap_trang();
t( '🔴 bảng ĐÃ đủ ô: Quản lý giữ nguyên quyền duyệt', ! empty( $q3['duyetTU']['Quản lý'] ), $q3['duyetTU'] );
t( '   và Giám đốc giữ nguyên ô TRỐNG người ta cố ý để',
	empty( $q3['duyetTU']['Giám đốc'] ), $q3['duyetTU'] );

/* ═══ 4. 🔴 ĐƯỜNG ĐI — BƯỚC VÁ PHẢI ĐƯỢC GỌI Ở TỆP PLUGIN ════════════════════════
 * Viết đúng hàm vá mà quên gọi thì cài đè xong trông như đã sửa, mà chưa chạy dòng nào. Đúng
 * ca 28/08/2026: bản vá phân quyền nằm trong `install()` — hàm chỉ chạy khi đổi sơ đồ bảng —
 * nên anh Thắng cài b1.50.1 xong vẫn báo *"anh chưa thấy nút duyệt"*.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
$chinh = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/vhcp-chi-phi.php' );
$chinh_ma = preg_replace( '#/\*.*?\*/#s', '', $chinh );
$chinh_ma = preg_replace( '#//[^\n]*#', '', $chinh_ma );
t( '🔴 tệp plugin CÓ gọi va_cot_quyen_them_vai()',
	false !== strpos( $chinh_ma, 'va_cot_quyen_them_vai' ), null );
/* ⚠️ PHẢI ĐỨNG NGOÀI CHỐT `vhcp_db_version`, cùng lý do với `va_quyen_quyet_toan()`: bản này
   không đổi sơ đồ bảng nào, nên đặt trong `install()` là không bao giờ chạy. */
if ( preg_match( '/if \( get_option\( .vhcp_db_version.*?\n\t\}/s', $chinh_ma, $m_db ) ) {
	t( '🔴 và KHÔNG nằm trong nhánh chỉ chạy khi đổi sơ đồ bảng',
		false === strpos( $m_db[0], 'va_cot_quyen_them_vai' ), $m_db[0] );
} else {
	t( 'đọc được nhánh vhcp_db_version', false, 'không khớp regex' );
}

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
