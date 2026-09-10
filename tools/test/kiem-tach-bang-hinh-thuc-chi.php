<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HAI HÌNH THỨC CHI: TÁCH BẢNG, VÀ TÁCH CẢ PHÉP TÍNH.
 *
 * Anh Thắng 10/09/2026: *"Khi trong đơn có 2 hình thức chi thì tách ra 2 bảng riêng"* và
 * *"Để thể hiện rõ do ai chi và bên nào chi và tạm ứng. Vì nv tự trả thì kế toán sẽ tạm ứng và
 * chi cho nhân viên. Còn kế toán tự trả nhà cung cấp, thì chỉ lên bảng để tổng chứ, chứ không
 * thay đổi giá trị trong quá trình tạm ứng và quyết toán"*.
 *
 * =============================================================================================
 * 🔴 DÒNG TRẢ THẲNG NCC KHÔNG ĐƯỢC LỌT VÀO PHÉP THỪA/THIẾU. Kế toán trả thẳng nhà cung cấp thì
 *    tiền không qua tay nhân viên, nên nó không phải khoản nhân viên phải quyết toán. Cộng lẫn
 *    vào là màn báo nhân viên còn thiếu (hoặc thừa) một khoản họ chưa hề cầm — và đó là con số
 *    kế toán căn vào để thu/chi thêm.
 *
 * 🔴 MỤC CON THEO HÌNH THỨC CỦA HẠNG MỤC LỚN. Mục con không chọn hình thức riêng; đọc ô rỗng
 *    của nó thành "tạm ứng" là mọi mục con của một hạng mục TRỰC TIẾP nhảy vào phép quyết toán.
 *
 * ⚠️ CHẠY THẬT `get_du_an()` trên dữ liệu thật (tạo dự án · thêm dòng · đọc lại).
 *
 * Chạy: php tools/test/kiem-tach-bang-hinh-thuc-chi.php
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
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong == $thuc, $thuc ); }

VHCP_Auth::dat_vai_tro( 'Admin', 'KT' );
$r = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian thử tách bảng', 'KT' );
t( 'tạo được dự án thử', ! empty( $r['success'] ), $r );
$ma = $r['maDA'];

/* Hạng mục TẠM ỨNG: dự toán 10tr, hai mục con thực chi 4tr + 3tr. */
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Mua đồ điện', 'duToan' => 10000000, 'hinhThuc' => '' ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Bóng đèn', 'capCha' => 'Mua đồ điện', 'thucTe' => 4000000 ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Dây điện', 'capCha' => 'Mua đồ điện', 'thucTe' => 3000000 ) );
/* Hạng mục TRỰC TIẾP: kế toán trả thẳng NCC 5tr. */
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Xe cẩu', 'duToan' => 6000000, 'thucTe' => 5000000, 'hinhThuc' => 'Trực tiếp' ) );

$d = VHCP_DuAn::get_du_an( $ma );
t( 'đọc được dự án', ! empty( $d['success'] ), $d );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 HAI RỔ TIỀN TÁCH BẠCH
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '🔴 cần tạm ứng = dự toán của phần NV TỰ TRẢ (không cộng phần trả thẳng NCC)',
	10000000, $d['canTamUng'] );
teq( '🔴 thực chi tạm ứng = tiền NV đã tự trả', 7000000, $d['ttTamUng'] );
teq( '   dự toán phần trả thẳng NCC đứng riêng', 6000000, $d['traTrucTiep'] );
teq( '   thực chi trả thẳng NCC cũng đứng riêng', 5000000, $d['ttTrucTiep'] );

/* 🔴 Đây là con số kế toán căn vào để thu/chi thêm với NHÂN VIÊN. */
teq( '🔴 thừa/thiếu tạm ứng chỉ tính trên phần NV tự trả (7tr − 10tr = −3tr, tức dư)',
	-3000000, $d['thieuTamUng'] );
t( '🔴 và 5tr trả thẳng NCC KHÔNG được lọt vào đó (nếu lọt thì ra −8tr hoặc +2tr)',
	-3000000 === (int) $d['thieuTamUng'], $d['thieuTamUng'] );

/* Tổng chung thì vẫn gom cả hai — đó là tổng chi phí của dự án. */
teq( 'tổng thực tế vẫn gom cả hai rổ (7tr + 5tr)', 12000000, $d['tongThucTe'] );
teq( 'tổng dự toán cũng vậy (10tr + 6tr)', 16000000, $d['tongDuToan'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 MỤC CON THEO HÌNH THỨC CỦA CHA
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Thuê cẩu 20 tấn', 'capCha' => 'Xe cẩu', 'thucTe' => 2000000 ) );
$d2 = VHCP_DuAn::get_du_an( $ma );
teq( '🔴 mục con của hạng mục TRỰC TIẾP cũng vào rổ trực tiếp, không vào tạm ứng',
	7000000, $d2['ttTamUng'] );
teq( '   và cộng vào rổ trực tiếp', 2000000, $d2['ttTrucTiep'] );
teq( '🔴 thừa/thiếu tạm ứng KHÔNG đổi vì thêm một mục con trả thẳng NCC',
	-3000000, $d2['thieuTamUng'] );
$con = null;
foreach ( $d2['lines'] as $l ) { if ( $l['noiDung'] === 'Thuê cẩu 20 tấn' ) { $con = $l; } }
teq( '   dòng con được gắn sẵn hình thức của cha khi trả về màn', 'Trực tiếp', $con['hinhThuc'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. MÀN TÁCH LÀM HAI BẢNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$HTML = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 màn chia dòng theo hình thức chi', false !== strpos( $HTML, 'var nhomHt={ung:[],ncc:[]}' ), '' );
t( '🔴 chỉ tách khi có CẢ HAI (một hình thức thì vẫn một bảng)',
	false !== strpos( $HTML, 'var coHai=(nhomHt.ung.length||psHt.ung.length) && (nhomHt.ncc.length||psHt.ncc.length);' )
	&& false !== strpos( $HTML, 'html=veNhom(parents)+vePhatSinh(phatSinh);' ), '' );
t( '🔴 nhãn nói rõ phần NV tự trả VÀO phép quyết toán',
	false !== mb_strpos( $HTML, 'VÀO phép tạm ứng & quyết toán' ), '' );
t( '🔴 và phần trả thẳng NCC thì KHÔNG',
	false !== mb_strpos( $HTML, 'KHÔNG vào tạm ứng & quyết toán' ), '' );
t( '   nói luôn ai chi cho ai', false !== mb_strpos( $HTML, 'kế toán tạm ứng cho nhân viên' )
	&& false !== mb_strpos( $HTML, 'KẾ TOÁN TRẢ THẲNG NCC' ), '' );
t( '   mỗi bảng có tổng riêng', false !== strpos( $HTML, 'money(tongNhom(nhomHt[k],psHt[k]))' ), '' );
/* Tổng của bảng: hạng mục có con thì tiền nằm ở con — cộng cả cha lẫn con là đếm hai lần. */
t( '🔴 tổng bảng không đếm hai lần (cha có con thì chỉ cộng con)',
	false !== strpos( $HTML, 'if(kids.length) kids.forEach(function(k){ t+=Number(k.thucTe)||0; });' )
	&& false !== strpos( $HTML, 'else t+=Number(p.thucTe)||0;' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 HẠNG MỤC LỚN NHÌN LÀ BIẾT
 *
 * Anh Thắng 10/09/2026: *"Hạng mục lớn phải viết đậm cùng màu để phân biệt nhau"*. Hạng mục lớn
 * CÓ CON thì đã tô nền tím + đậm; hạng mục lớn CHƯA CÓ CON lại vẽ y hệt một mục con — nhìn bảng
 * thì "Túi zip" (hạng mục lớn) và "↳ Linh kiện" (mục con của hạng mục khác) trông ngang hàng,
 * mà hai thứ ấy khác hẳn: một cái xin tạm ứng được, một cái không.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 hàng dựng theo cờ "là hạng mục lớn", không chỉ theo "có mục con"',
	false !== strpos( $HTML, 'function daLineCells(l, indent, extraAct, laLon){' )
	&& false !== strpos( $HTML, 'html+=daLineCells(p,false, addChild, true);' ), '' );
t( '🔴 hạng mục lớn chưa có con cũng ĐẬM và CÙNG MÀU với hạng mục lớn có con',
	false !== strpos( $HTML, "(laLon?' style=\"background:#eef2ff;font-weight:700\"':'')" )
	&& false !== strpos( $HTML, 'style="background:#eef2ff;font-weight:700"><td>📦 ' ), '' );
t( '   và mang cùng dấu 📦', false !== strpos( $HTML, "(laLon?'📦 ':'')" ), '' );
t( '🔴 mục con vẫn thụt vào và KHÔNG bị tô như hạng mục lớn',
	false !== strpos( $HTML, "var trS=indent?' style=\"background:#fcfdff\"':(laLon?" ), '' );

VHCP_DuAn::delete( $ma );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: hai hình thức chi tách bảng, và tiền trả thẳng NCC không lọt vào quyết toán của nhân viên.\n";
