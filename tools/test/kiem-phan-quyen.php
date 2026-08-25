<?php
/**
 * KIỂM PHÂN QUYỀN — bảng vai trò & quyền (VHCC_Vai), và chốt "không ai tự so vai trò lấy".
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI CÓ BÀI NÀY — LỖI NÓ SINH RA ĐÃ CHẠY NHIỀU THÁNG MÀ KHÔNG AI THẤY
 * =============================================================================================
 * Anh Thắng 25/08/2026: *"hiện tại hệ thống đang xung đột phân quyền"*. Gốc của nó:
 *
 *     $v = strtoupper( trim( $u['role'] ) );        // 'Quản lý' -> 'QUảN Lý'
 *     return 'ADMIN' === $v || 'QUAN_LY' === $v;    // ... không khớp gì cả
 *
 * `strtoupper` của PHP KHÔNG nâng được chữ có dấu. Thẻ phiên mang tên tiếng Việt, còn phép so
 * dùng mã hoa — nên mọi vai trừ đúng `'Admin'` (tình cờ khớp) đều bị chối ở mọi cửa hỏi qua
 * `VHCC_NhanSu`. Không có lỗi nào phát ra: quản lý bấm nút thì màn hình nói "không đủ quyền",
 * và ai cũng tưởng mình khai sai vai trò.
 *
 * Bài này canh hai thứ:
 *   1. `VHCC_Vai::ma()` nhận MỌI kiểu viết mà bốn nguồn dữ liệu thực tế đang có.
 *   2. KHÔNG tệp nào tự so vai trò bằng chuỗi nữa — sai một chỗ là mở/khoá cửa im lặng.
 *
 * Chạy: php tools/test/kiem-phan-quyen.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

/* ============================================================ 1. MỌI KIỂU VIẾT -> MỘT MÃ
 *
 * Bốn nguồn, bốn lối viết: sổ PhanQuyen của app cũ dùng mã hoa; hồ sơ nhân sự người ta gõ tay
 * (lúc có dấu lúc không); danh sách riêng của plugin dùng tên tiếng Việt; app chi phí có thêm
 * "Kế toán NCC". Nhận hết ở MỘT chỗ, để chỗ khác chỉ việc so mã với mã. */
$bang_ma = array(
	'Admin'             => VHCC_Vai::ADMIN,
	'admin'             => VHCC_Vai::ADMIN,
	'ADMIN'             => VHCC_Vai::ADMIN,
	'Quản trị'          => VHCC_Vai::ADMIN,
	'Quản lý'           => VHCC_Vai::QL,
	'QUAN_LY'           => VHCC_Vai::QL,
	'quan ly'           => VHCC_Vai::QL,
	'Quan Ly'           => VHCC_Vai::QL,
	'Cửa hàng trưởng'   => VHCC_Vai::CHT,
	'CUA_HANG_TRUONG'   => VHCC_Vai::CHT,
	'cua-hang-truong'   => VHCC_Vai::CHT,
	'Kế toán'           => VHCC_Vai::KE_TOAN,
	'Kế toán cá nhân'   => VHCC_Vai::KE_TOAN,
	'Kế toán NCC'       => VHCC_Vai::KE_TOAN,
	'KE_TOAN'           => VHCC_Vai::KE_TOAN,
	'Nhân viên'         => VHCC_Vai::NV,
	'NHAN_VIEN'         => VHCC_Vai::NV,
);
foreach ( $bang_ma as $viet => $mong ) {
	t( "\"$viet\" -> $mong", $mong === VHCC_Vai::ma( $viet ), VHCC_Vai::ma( $viet ) );
}

/* 🔴 MẶC ĐỊNH LÀ ĐÁY THANG. Đoán lên cao một lần là mở bảng lương cho một dòng gõ sai chính tả. */
foreach ( array( '', '   ', 'Giám đốc', 'Thu Tiền', 'Máy tự động', 'xyz', '???' ) as $la ) {
	t( "vai lạ \"$la\" rơi về Nhân viên", VHCC_Vai::NV === VHCC_Vai::ma( $la ), VHCC_Vai::ma( $la ) );
}

/* Đọc được cả `role` (user_by_token) lẫn `vai_tro` (dòng đọc thẳng từ bảng). Chỉ đọc một khoá
   là nửa số chỗ gọi nhận về rỗng — tức Nhân viên — mà không có lỗi nào phát ra. */
t( 'đọc khoá role',    VHCC_Vai::ADMIN === VHCC_Vai::cua( array( 'role' => 'Admin' ) ) );
t( 'đọc khoá vai_tro', VHCC_Vai::ADMIN === VHCC_Vai::cua( array( 'vai_tro' => 'ADMIN' ) ) );
t( 'role rỗng thì thử vai_tro',
	VHCC_Vai::QL === VHCC_Vai::cua( array( 'role' => '', 'vai_tro' => 'Quản lý' ) ) );
t( 'không có khoá nào -> Nhân viên', VHCC_Vai::NV === VHCC_Vai::cua( array() ) );
t( 'nhận thẳng chuỗi', VHCC_Vai::CHT === VHCC_Vai::cua( 'Cửa hàng trưởng' ) );

/* ============================================================ 2. THANG NĂM BẬC
 *
 * Mô hình anh Thắng chốt. Bậc trên làm được MỌI việc của bậc dưới — đó là điều kiện để phân
 * quyền còn đọc được: một vai giữa thang mà thiếu quyền của vai dưới nó thì sơ đồ không còn là
 * thang, và không ai nhìn ra cho tới lúc có người bị chối oan. */
$U = array();
foreach ( VHCC_Vai::tat_ca() as $ma ) { $U[ $ma ] = array( 'role' => $ma ); }

t( 'đúng năm bậc', 5 === count( VHCC_Vai::tat_ca() ) );
t( 'bậc tăng dần theo đúng thứ tự Nhân viên < CHT < Quản lý < Kế toán < Admin',
	VHCC_Vai::bac( $U[ VHCC_Vai::NV ] ) < VHCC_Vai::bac( $U[ VHCC_Vai::CHT ] )
	&& VHCC_Vai::bac( $U[ VHCC_Vai::CHT ] ) < VHCC_Vai::bac( $U[ VHCC_Vai::QL ] )
	&& VHCC_Vai::bac( $U[ VHCC_Vai::QL ] ) < VHCC_Vai::bac( $U[ VHCC_Vai::KE_TOAN ] )
	&& VHCC_Vai::bac( $U[ VHCC_Vai::KE_TOAN ] ) < VHCC_Vai::bac( $U[ VHCC_Vai::ADMIN ] ) );

foreach ( array_keys( VHCC_Vai::QUYEN ) as $q ) {
	$day = array();
	foreach ( VHCC_Vai::tat_ca() as $ma ) { $day[] = VHCC_Vai::duoc( $U[ $ma ], $q ) ? 1 : 0; }
	/* Chuỗi hợp lệ chỉ có dạng 0…01…1 — không được bật rồi tắt lại. */
	t( "quyền \"$q\" liền mạch theo thang",
		implode( '', $day ) === str_pad( str_repeat( '1', array_sum( $day ) ), 5, '0', STR_PAD_LEFT ),
		implode( '', $day ) );
	t( "quyền \"$q\" ít nhất Admin phải có", (bool) end( $day ) );
}

/* Đúng việc anh Thắng giao cho từng bậc. */
t( 'Nhân viên: chấm công online',   VHCC_Vai::duoc( $U[ VHCC_Vai::NV ], 'cham_online' ) );
t( 'Nhân viên: xem công của mình',  VHCC_Vai::duoc( $U[ VHCC_Vai::NV ], 'cong_minh' ) );
t( 'Nhân viên: KHÔNG xem công cơ sở', ! VHCC_Vai::duoc( $U[ VHCC_Vai::NV ], 'cong_coso' ) );
t( 'Nhân viên chỉ có đúng hai quyền', 2 === count( array_filter(
	array_map( function ( $q ) { return VHCC_Vai::duoc( array( 'role' => VHCC_Vai::NV ), $q ); },
		array_keys( VHCC_Vai::QUYEN ) ) ) ) );

t( 'CHT: chấm công bù',      VHCC_Vai::duoc( $U[ VHCC_Vai::CHT ], 'cham_bu' ) );
t( 'CHT: lên lịch cửa hàng', VHCC_Vai::duoc( $U[ VHCC_Vai::CHT ], 'lich_lam' ) );
t( 'CHT: báo lỗi lên trên',  VHCC_Vai::duoc( $U[ VHCC_Vai::CHT ], 'bao_loi' ) );
t( 'CHT: KHÔNG xem mọi cơ sở', ! VHCC_Vai::duoc( $U[ VHCC_Vai::CHT ], 'cong_tat_ca' ) );
t( 'CHT: KHÔNG đụng hồ sơ',    ! VHCC_Vai::duoc( $U[ VHCC_Vai::CHT ], 'ho_so' ) );

t( 'Quản lý: xem công mọi cơ sở', VHCC_Vai::duoc( $U[ VHCC_Vai::QL ], 'cong_tat_ca' ) );
t( 'Quản lý: xử lý lỗi',          VHCC_Vai::duoc( $U[ VHCC_Vai::QL ], 'xu_ly_loi' ) );
t( 'Quản lý: KHÔNG làm lương',  ! VHCC_Vai::duoc( $U[ VHCC_Vai::QL ], 'luong' ) );
t( 'Quản lý: KHÔNG đụng hồ sơ', ! VHCC_Vai::duoc( $U[ VHCC_Vai::QL ], 'ho_so' ) );

t( 'Kế toán: lương',    VHCC_Vai::duoc( $U[ VHCC_Vai::KE_TOAN ], 'luong' ) );
t( 'Kế toán: ngày lễ',  VHCC_Vai::duoc( $U[ VHCC_Vai::KE_TOAN ], 'ngay_le' ) );
t( 'Kế toán: hồ sơ',    VHCC_Vai::duoc( $U[ VHCC_Vai::KE_TOAN ], 'ho_so' ) );
t( 'Kế toán: KHÔNG đụng hệ thống', ! VHCC_Vai::duoc( $U[ VHCC_Vai::KE_TOAN ], 'he_thong' ) );
t( 'Kế toán: KHÔNG xem PIN người khác', ! VHCC_Vai::duoc( $U[ VHCC_Vai::KE_TOAN ], 'xem_pin' ) );

t( 'Admin: hệ thống', VHCC_Vai::duoc( $U[ VHCC_Vai::ADMIN ], 'he_thong' ) );
t( 'Admin: xem PIN',  VHCC_Vai::duoc( $U[ VHCC_Vai::ADMIN ], 'xem_pin' ) );

/* 🔴 DANH SÁCH TRẮNG: quyền chưa khai thì CHỐI, kể cả Admin. Quên khai một việc mới thì nó bị
   chặn — ồn ào và sửa được — chứ không lọt im lặng. */
foreach ( array( 'quyen_moi_chua_khai', '', 'xoa_het_du_lieu', 'ho_so ' ) as $la_q ) {
	t( "quyền chưa khai \"$la_q\" bị chối kể cả với Admin",
		! VHCC_Vai::duoc( $U[ VHCC_Vai::ADMIN ], $la_q ) );
}

/* Câu báo phải nói RÕ cần bậc nào — để người bị chối biết đi xin ai, thay vì gõ lại. */
$loi = VHCC_Vai::loi( $U[ VHCC_Vai::CHT ], 'luong', 'Xem bảng lương' );
t( 'câu báo nêu việc',        strpos( $loi, 'Xem bảng lương' ) !== false, $loi );
t( 'câu báo nêu bậc hiện có', strpos( $loi, 'Cửa hàng trưởng' ) !== false, $loi );
t( 'câu báo nêu bậc cần có',  strpos( $loi, 'Kế toán' ) !== false, $loi );

/* ============================================================ 3. KHÔNG AI TỰ SO VAI TRÒ LẤY
 *
 * 🔴 Đây là phép thử giữ cho lỗi gốc không quay lại. Mỗi chỗ tự so vai trò bằng chuỗi là một
 *    bộ luật thứ hai, và bộ thứ hai bao giờ cũng lệch trước — lệch phân quyền thì im lặng. */
$thu_muc = $goc . '/wordpress/vhcp-cham-cong/includes';
$bo_qua  = array( 'class-vhcc-vai.php' );   // chính nó là nơi được phép so
$pham    = array();
foreach ( (array) glob( $thu_muc . '/*.php' ) as $tep ) {
	if ( in_array( basename( $tep ), $bo_qua, true ) ) { continue; }
	$src = file_get_contents( $tep );
	/* Bỏ chú thích nhưng GIỮ NGUYÊN SỐ DÒNG — thay mỗi khối bằng đúng số xuống dòng của nó.
	   Xoá thẳng thì số dòng báo ra lệch, và người đi sửa mở đúng dòng đó thì thấy mã vô can. */
	$src = preg_replace_callback( '#/\*.*?\*/#s',
		function ( $m ) { return str_repeat( "\n", substr_count( $m[0], "\n" ) ); }, $src );
	/* Bẫy thứ hai: mẫu bỏ chú thích dòng KHÔNG được dùng `\s` ở đầu — `\s` gồm cả xuống dòng,
	   nên nó nuốt luôn dòng trắng phía trên và số dòng lại lệch. Dùng `[ \t]` thôi. */
	$src = preg_replace( '#^[ \t]*//.*$#m', '', $src );
	foreach ( explode( "\n", $src ) as $n => $dong ) {
		/* Mẫu 1: strtoupper trên vai trò rồi so — đúng cái sinh ra lỗi cũ. */
		if ( preg_match( '/strtoupper\s*\([^)]*(role|vai_tro)/i', $dong ) ) {
			$pham[] = basename( $tep ) . ':' . ( $n + 1 ) . ' strtoupper trên vai trò';
		}
		/* Mẫu 2: so thẳng `$u['role']` với một chuỗi. */
		if ( preg_match( "/\\['role'\\]\s*[!=]==?\s*'/", $dong )
			|| preg_match( "/'\w[^']*'\s*[!=]==?\s*\\\$\w+\\['role'\\]/", $dong ) ) {
			$pham[] = basename( $tep ) . ':' . ( $n + 1 ) . ' so vai trò bằng chuỗi';
		}
	}
}
t( 'không tệp nào tự so vai trò bằng chuỗi', 0 === count( $pham ), $pham );

/* Và phép thử ngược: bộ dò trên PHẢI bắt được mẫu sai, không phải lúc nào cũng xanh. */
$mau_sai = "\$v = strtoupper( \$u['role'] );\nif ( 'Admin' === \$toi['role'] ) { }";
$bat = 0;
foreach ( explode( "\n", $mau_sai ) as $d ) {
	if ( preg_match( '/strtoupper\s*\([^)]*(role|vai_tro)/i', $d ) ) { $bat++; }
	if ( preg_match( "/'\w[^']*'\s*[!=]==?\s*\\\$\w+\\['role'\\]/", $d ) ) { $bat++; }
}
t( 'bộ dò bắt được cả hai mẫu sai (không phải phép thử luôn xanh)', 2 === $bat, $bat );

/* ============================================================ 4. BỐN HÀM CŨ NAY HỎI BẢNG VAI */
t( 'co_sua_ho_so = quyền ho_so',
	VHCC_NhanSu::co_sua_ho_so( $U[ VHCC_Vai::KE_TOAN ] ) && ! VHCC_NhanSu::co_sua_ho_so( $U[ VHCC_Vai::QL ] ) );
t( 'co_quan_tri_nv = quyền ngoai_coso',
	VHCC_NhanSu::co_quan_tri_nv( $U[ VHCC_Vai::QL ] ) && ! VHCC_NhanSu::co_quan_tri_nv( $U[ VHCC_Vai::CHT ] ) );
t( 'co_xem_luong = quyền xem_luong_hs',
	VHCC_NhanSu::co_xem_luong( $U[ VHCC_Vai::KE_TOAN ] ) && ! VHCC_NhanSu::co_xem_luong( $U[ VHCC_Vai::QL ] ) );
t( 'VHCC_Luong::co_quyen nhận tên tiếng Việt có dấu (lỗi cũ: chối sạch)',
	VHCC_Luong::co_quyen( 'Kế toán cá nhân' ) && VHCC_Luong::co_quyen( 'Admin' )
	&& ! VHCC_Luong::co_quyen( 'Cửa hàng trưởng' ) );

/* Nhân viên KHÔNG có quyền trên cơ sở nào, kể cả cơ sở ghi trong dòng phân quyền của họ. */
t( 'Nhân viên không có quyền cơ sở của chính mình',
	! VHCC_NhanSu::co_quyen_coso( array( 'role' => 'Nhân viên', 'coso' => 'TUTU_BT' ), 'TUTU_BT' ) );
t( 'Cửa hàng trưởng có quyền cơ sở mình',
	VHCC_NhanSu::co_quyen_coso( array( 'role' => 'Cửa hàng trưởng', 'coso' => 'TUTU_BT' ), 'TUTU_BT' ) );
t( 'Cửa hàng trưởng KHÔNG có quyền cơ sở khác',
	! VHCC_NhanSu::co_quyen_coso( array( 'role' => 'Cửa hàng trưởng', 'coso' => 'TUTU_BT' ), 'POSH_HCM' ) );
t( 'Quản lý có quyền mọi cơ sở',
	VHCC_NhanSu::co_quyen_coso( array( 'role' => 'Quản lý', 'coso' => '' ), 'CO_SO_LA' ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — một bảng vai trò duy nhất, thang năm bậc liền mạch.\n";
