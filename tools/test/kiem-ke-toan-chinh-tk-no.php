<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KẾ TOÁN CHỈNH TK NỢ CỦA TỪNG DÒNG — CHẠY THẬT `VHCP_Don::set_line_tk_no()`.
 *
 * Anh Thắng 22/09/2026: *"Sau khi quyết toán, thì kế toán có quyền điều chỉnh tk nợ theo nhu
 * cầu, vì Cùng tên gọi nhưng nội dung khác, Nên lúc tạo đơn nhân viên không cần quan tâm (đến
 * phần quyết toán thì nó mới hiện qua và kế toán chọn các số lập sẵn và bấm quyết toán là
 * xong)"*.
 *
 * =============================================================================================
 * 🔴 BÀI NÀY PHẢI LÀ PHP, KHÔNG PHẢI JS ĐỌC MÃ NGUỒN
 * =============================================================================================
 * Đây là chỗ MỘT CON SỐ ĐI VÀO SỔ. Bài đọc mã nguồn chỉ chứng minh được "có viết dòng ấy", không
 * chứng minh được nó chạy, không bắt được một chốt vai trò bị tuột, và tuyệt nhiên không thấy
 * được `$wpdb->update` có ghi đúng cột hay không. Bài học đã trả giá một lần ở
 * `kiem-tk-doi-ung-theo-loai.js` — chép lại ở đầu tệp ấy.
 *
 * Bốn chuyện phải canh, và cả bốn đều là chuyện "im lặng thì không ai biết":
 *   1) kế toán đổi được, và ĐỔI ĐÚNG CỘT (không dọn rỗng mấy ô khác của dòng);
 *   2) người nhập KHÔNG đổi được;
 *   3) mã chưa khai ở Cấu hình thì TỪ CHỐI — mã ma chỉ lộ ra ở MISA, sau khi kỳ đã chốt;
 *   4) ô trống = TRẢ VỀ TỰ ĐỘNG theo loại, không phải xoá trắng mã.
 *
 * Chạy: php tools/test/kiem-ke-toan-chinh-tk-no.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/* ─── danh mục: hai mã hợp lệ cho cùng một loại, cộng một TK Có để thử chốt ───────────────── */
/* Cột: ten · tkNo · tkCo · maDt · boPhan · note · tenMisa · loaiTt · donVi · khoi · vaiTro */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Chi phí khác', '', '', '', 'Cơ sở', '', '', '', '', 'kvc' ),
	/* 🔴 LOẠI KHAI MÃ CỐ ĐỊNH Ở CỘT `tkNo`, KHÔNG QUA MA TRẬN. Đây là dạng khai thứ hai, và
	   `tkno_da_khai()` phải gom cả nó — thiếu thì kế toán chọn một mã đang nằm sờ sờ trong bảng
	   Cấu hình mà bị chối. Đột biến "bỏ vòng lặp đọc `loaiChiPhi`" SỐNG SÓT ở lượt kiểm đầu vì
	   bệ đỡ chỉ có mã kiểu ma trận. */
	array( 'Chi phí cố định', '6422', '', '', 'Cơ sở', '', '', '', '', 'kvc' ),
), false );
/* ⚠️ PHÂN LOẠI THANH TOÁN KHAI MÃ KHÁC 141/331 — cố ý. `la_tk_co()` gom TK Có từ ba nguồn, và
   nếu bệ đỡ để phân loại mang sẵn 141/331 thì cặp gõ cứng trong hàm không ai canh: xoá nó đi
   bài vẫn xanh (đột biến đã SỐNG SÓT một lượt đúng vì thế). Tách ra thì mỗi nguồn một phép. */
VHCP_Cfg::write( VHCP_Cfg::PL, array(
	array( 'Thanh toán cá nhân', '1388' ),
	array( 'Nhà cung cấp',       '3388' ),
), false );
/* Cột cơ sở: ten · maDonVi · phanLoaiLon · tenMisa · dongCua … — khai thẳng để MẢNG của cơ sở
   là thứ bài này định đoạt, không phụ thuộc dữ liệu mẫu nào đó đổi sau lưng. */
VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	array( 'FARM PHAN THIẾT', 'FPT', 'Farm', 'Farm Phan Thiết', '', '' ),
	array( 'TÀU ESTELLA',     'TES', 'TuTu', 'Tàu Estella',     '', '' ),
), false );
/* Cột: nhom · pll · tkNo */
VHCP_Cfg::write( VHCP_Cfg::TKNO, array(
	array( 'Chi phí khác', 'Farm', '64166' ),
	array( 'Chi phí khác', 'TuTu', '64106' ),
), false );
VHCP_Cfg::clear_cache();

$KHAI = VHCP_Cfg::tkno_da_khai();
sort( $KHAI );
teq( '⚠️ bệ đỡ dựng đúng: gom CẢ mã ma trận LẪN mã cố định của danh mục',
	array( '6422', '64106', '64166' ), $KHAI );
t( '🔴 141 bị nhận ra là TK CÓ (dù phân loại đang khai mã khác)', VHCP_Cfg::la_tk_co( '141' ) );
t( '🔴 331 bị nhận ra là TK CÓ (dù phân loại đang khai mã khác)', VHCP_Cfg::la_tk_co( '331' ) );
t( '🔴 và TK Có khai ở Phân loại thanh toán cũng bị nhận ra', VHCP_Cfg::la_tk_co( '1388' ) );
t( '   64166 KHÔNG phải TK Có', ! VHCP_Cfg::la_tk_co( '64166' ) );

/* ─── một đơn, một dòng ở cơ sở thuộc mảng FARM MN ────────────────────────────────────────── */
$hom_nay = gmdate( 'Y-m-d' );
$don = VHCP_Don::create_don( 'T9/2026 (21/9-27/9/2026)', 'Nguyễn Văn A' );
$ma  = $don['maDon'];
$ln  = VHCP_Don::add_line( $ma, array(
	'coso' => 'FARM PHAN THIẾT', 'ngay' => $hom_nay, 'phanLoaiTT' => 'Thanh toán cá nhân',
	'doiTuong' => 'Nguyễn Văn A', 'nhom' => 'Chi phí khác', 'noiDung' => 'Mua dây điện',
	'soLuong' => 1, 'donGia' => 500000, 'thanhTien' => 500000,
) );
t( '⚠️ dựng được dòng chi', ! empty( $ln['success'] ), $ln );
$id = (string) $ln['id'];
$goc_dong = VHCP_Don::line_row( $id );
teq( '   dòng mới mang mã tự động của mảng FARM', '64166', (string) $goc_dong['tk_no'] );

/* ═══ 1. NGƯỜI NHẬP KHÔNG ĐỔI ĐƯỢC ═════════════════════════════════════════════════════════
   🔴 Đây là chốt quan trọng nhất của cả bài. Mở quyền cho người nhập là con số nhảy tài khoản
      sau lưng kế toán, và không ai biết vì sao — cùng lý lẽ đã ghi ở `set_line_nhom()`. */
$vai_cu = VHCP_Auth::vai_tro();
VHCP_Auth::dat_vai_tro( 'Nhân viên' );
$r = VHCP_Don::set_line_tk_no( $id, '64106' );
t( '🔴 nhân viên bị từ chối', empty( $r['success'] ), $r );
t( '   và câu từ chối nói RÕ vì sao, không "Lỗi" trần trụi',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'kế toán' ), $r );
teq( '🔴 và dữ liệu KHÔNG hề đổi', '64166', (string) VHCP_Don::line_row( $id )['tk_no'] );

/* ═══ 2. KẾ TOÁN ĐỔI ĐƯỢC, SANG MỘT MÃ ĐÃ KHAI ═════════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân' );
$r = VHCP_Don::set_line_tk_no( $id, '64106' );
t( '🔴 kế toán đổi được sang mã đã khai (dù là mã của MẢNG KHÁC)', ! empty( $r['success'] ), $r );
teq( '   ghi đúng vào sổ', '64106', (string) VHCP_Don::line_row( $id )['tk_no'] );
teq( '   trả về đúng mã mới', '64106', isset( $r['tkNo'] ) ? (string) $r['tkNo'] : '' );

/* ⚠️ CHỈ ĐỔI `tk_no`, KHÔNG DỌN Ô NÀO KHÁC. `update_line()` ghi lại cả dòng, đi nhờ nó là mấy
   ô không gửi lên bị dọn về rỗng — im lặng, và chỉ lộ ra khi ai đó mở lại đơn cũ. */
$sau = VHCP_Don::line_row( $id );
$giu = array( 'noi_dung', 'coso', 'nhom', 'so_luong', 'don_gia', 'thanh_tien', 'phan_loai_tt', 'doi_tuong', 'tk_co' );
$lech = array();
foreach ( $giu as $c ) {
	if ( (string) $goc_dong[ $c ] !== (string) $sau[ $c ] ) {
		$lech[ $c ] = (string) $goc_dong[ $c ] . ' → ' . (string) $sau[ $c ];
	}
}
teq( '🔴 không đụng ô nào khác của dòng', array(), $lech );

/* ═══ 3. MÃ CHƯA KHAI / TK CÓ → TỪ CHỐI ════════════════════════════════════════════════════
   "kế toán chọn các số LẬP SẴN" — chữ của anh Thắng, và cũng là chốt. */
/* 🔴 MÃ CỐ ĐỊNH CỦA DANH MỤC CŨNG LÀ "SỐ LẬP SẴN" — nhận. */
$r = VHCP_Don::set_line_tk_no( $id, '6422' );
t( '🔴 mã khai ở cột tkNo của danh mục (không qua ma trận) cũng chọn được', ! empty( $r['success'] ), $r );
teq( '   và ghi đúng', '6422', (string) VHCP_Don::line_row( $id )['tk_no'] );
VHCP_Don::set_line_tk_no( $id, '64106' );

$r = VHCP_Don::set_line_tk_no( $id, '99999' );
t( '🔴 mã chưa khai ở Cấu hình → từ chối', empty( $r['success'] ), $r );
t( '   và chỉ đúng chỗ phải đi khai', isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'Cấu hình' ), $r );
teq( '   dữ liệu giữ nguyên', '64106', (string) VHCP_Don::line_row( $id )['tk_no'] );

$r = VHCP_Don::set_line_tk_no( $id, '331' );
t( '🔴 TK CÓ (331) → từ chối, không cho lẫn vế', empty( $r['success'] ), $r );
t( '   và nói ra nó là TK Có', isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'TK CÓ' ), $r );
teq( '   dữ liệu giữ nguyên', '64106', (string) VHCP_Don::line_row( $id )['tk_no'] );

/* ═══ 4. Ô TRỐNG = TRẢ VỀ TỰ ĐỘNG, KHÔNG PHẢI XOÁ TRẮNG ════════════════════════════════════
   ⚠️ Chỉnh tay rồi mà không có đường lui thì dòng ấy đông cứng ở mã người ta lỡ chọn. */
$r = VHCP_Don::set_line_tk_no( $id, '' );
t( '🔴 ô trống → nhận, không báo lỗi', ! empty( $r['success'] ), $r );
teq( '🔴 và TÍNH LẠI theo loại × mảng, không xoá trắng', '64166', (string) VHCP_Don::line_row( $id )['tk_no'] );

/* ⚠️ Khoảng trắng hai đầu là thứ người ta dán vào từ Excel suốt ngày. */
VHCP_Don::set_line_tk_no( $id, '  64106  ' );
teq( '⚠️ mã dán từ Excel (có khoảng trắng) vẫn nhận đúng', '64106', (string) VHCP_Don::line_row( $id )['tk_no'] );

/* ═══ 5. ĐƠN ĐÃ QUYẾT TOÁN VẪN CHỈNH ĐƯỢC ══════════════════════════════════════════════════
   🔴 Đây CHÍNH LÀ lúc anh Thắng cần nó: *"Sau khi quyết toán, thì kế toán có quyền điều chỉnh"*.
      Chốt "đơn đã chốt thì khoá" (`vi_sao_khong_sua`) áp cho nội dung dòng, không áp cho việc
      gắn mã hạch toán — y như `set_line_nhom()` đã làm từ 18/09. Hai ô nằm cạnh nhau trên cùng
      một hàng mà một ô mở một ô khoá thì không ai đoán nổi luật. */
VHCP_Auth::dat_vai_tro( 'Admin' );
/* `upd_don()` là `private`, và `state()` cũng vậy — đặt trạng thái thẳng vào bảng. Ở đây điều
   đó ĐÚNG hơn là đi qua cả luồng duyệt: bài này canh một chốt, không canh cách tới được chốt. */
global $wpdb;
$wpdb->update( VHCP_DB::t( 'don' ), array( 'trang_thai' => 'Đã quyết toán' ), array( 'ma_don' => $ma ) );
t( '⚠️ bệ đỡ: đơn nay đã chốt, và chốt "đã chốt thì khoá" ĐANG hoạt động',
	'' !== VHCP_Don::vi_sao_khong_sua( $ma ), VHCP_Don::vi_sao_khong_sua( $ma ) );
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân' );
$r = VHCP_Don::set_line_tk_no( $id, '64166' );
t( '🔴 đơn ĐÃ QUYẾT TOÁN mà kế toán vẫn chỉnh được TK Nợ', ! empty( $r['success'] ), $r );
teq( '   và ghi đúng', '64166', (string) VHCP_Don::line_row( $id )['tk_no'] );
VHCP_Auth::dat_vai_tro( $vai_cu );

/* ═══ 6. KẾ TOÁN NHÀ KHÁC KHÔNG VỚI TỚI ĐƯỢC ═══════════════════════════════════════════════
 * 🔴 CHỐT ĐƠN VỊ ÁP CHO CẢ KẾ TOÁN. `loi_khong_phai_dong_minh()` ghi rõ vì sao nó phải đứng
 *    đầu hàm: *"thoát sớm là kế toán POSH sửa được TỪNG DÒNG của đơn K&H bằng id dòng, dù
 *    không mở nổi cái đơn chứa nó"*. Danh sách có lọc, nhưng id dòng thì đoán được / đọc được
 *    từ một màn khác — lọc danh sách là để MẮT không thấy, chốt này là để TAY không với tới.
 *
 * ⚠️ PHÉP NÀY SINH RA TỪ MỘT ĐỘT BIẾN SỐNG SÓT: gỡ hẳn hai dòng gác đầu hàm mà cả bài vẫn
 *    xanh. Mọi phép ở trên đều chạy trong đúng một nhà, nên không phép nào đi qua chỗ ấy.
 * ═════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	array( 'FARM PHAN THIẾT', 'FPT',   'Farm', 'Farm Phan Thiết', '', '' ),
	array( 'TÀU ESTELLA',     'TES',   'TuTu', 'Tàu Estella',     '', '' ),
	array( 'CALI THẢO ĐIỀN',  'PSHCM', 'Farm', 'Cali Thảo Điền',  '', 'POSH' ),
), false );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	/* ⚠️ NHÀ MẸ (K&H) ĐỌC CẢ HỆ — xem `VHCP_DonVi::xem_duoc()`. Lấy kế toán nhà mẹ làm "người
	   nhà khác" là phép thử tự hỏng: họ ĐƯỢC phép, đúng luật. Phải là nhà thứ ba. */
	array( 'KT MTĐ',  '111111', 'Kế toán cá nhân', '', '', '', '', 'MTĐ',  'MTĐ' ),
	array( 'KT POSH', '222222', 'Kế toán cá nhân', '', '', '', '', 'POSH', 'POSH' ),
	array( 'NV POSH', '444444', 'Nhân viên', 'CALI THẢO ĐIỀN', '', '', '', 'POSH', '' ),
), false );
VHCP_Cfg::clear_cache();

VHCP_Auth::dat_vai_tro( 'Nhân viên', 'NV POSH', 'CALI THẢO ĐIỀN' );
$don_p = VHCP_Don::create_don( 'T9/2026 (21/9-27/9/2026)', 'NV POSH' );
$ma_p  = isset( $don_p['maDon'] ) ? $don_p['maDon'] : '';
$ln_p  = VHCP_Don::add_line( $ma_p, array(
	'coso' => 'CALI THẢO ĐIỀN', 'ngay' => $hom_nay, 'phanLoaiTT' => 'Thanh toán cá nhân',
	'nhom' => 'Chi phí khác', 'noiDung' => 'Dòng của nhà POSH', 'thanhTien' => 100000,
) );
t( '⚠️ bệ đỡ: dựng được dòng của nhà POSH', ! empty( $ln_p['success'] ), $ln_p );
$id_p = (string) $ln_p['id'];
$truoc_p = (string) VHCP_Don::line_row( $id_p )['tk_no'];

VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'KT POSH' );
$r = VHCP_Don::set_line_tk_no( $id_p, '64106' );
t( '⚠️ bệ đỡ: kế toán ĐÚNG nhà thì vẫn chỉnh được (chốt không chặn nhầm người)',
	! empty( $r['success'] ), $r );

VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'KT MTĐ' );
$r = VHCP_Don::set_line_tk_no( $id_p, '64166' );
t( '🔴 kế toán nhà KHÁC bị chối, dù đúng vai kế toán', empty( $r['success'] ), $r );
teq( '🔴 và dòng của nhà kia KHÔNG hề đổi', '64106', (string) VHCP_Don::line_row( $id_p )['tk_no'] );
VHCP_Auth::dat_vai_tro( $vai_cu );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( count( $TRUOT ) ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: kế toán chỉnh được TK Nợ của từng dòng, chỉ bằng các số lập sẵn.\n";
