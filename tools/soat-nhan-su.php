<?php
/**
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * SOÁT HỒ SƠ NHÂN SỰ TRÊN HOSTING THẬT — CHỈ ĐỌC, KHÔNG GHI MỘT Ô NÀO
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 13/09/2026, trước khi sắp xếp lại phòng ban theo mảng: *"Trước khi sắp xếp lại bộ
 * phận và phòng ban và mảng, có mấy vấn đề cần giải quyết"*.
 *
 * `tren-host.sh soat` trả lời "trang này đang cài plugin gì" — đúng việc của nó. Nhưng mấy câu
 * quyết định việc sắp xếp lại thì nó không trả lời được, mà cũng không màn hình nào gom đủ:
 *   · ai đang mang một tên vai hệ KHÔNG CÓ (gõ đúng PIN vẫn bị chối, màn hình chỉ nói "PIN
 *     không đúng" nên không ai đoán ra);
 *   · ai hệ không suy ra mảng — tức đổi tên phòng theo mảng xong họ vẫn trôi;
 *   · ai có tài khoản Vận hành chi phí mà KHÔNG bị bó bộ phận — xem được sổ của mọi mảng;
 *   · phòng nào chưa khai vai, phòng nào chưa ai thuộc về.
 *
 * =============================================================================================
 * 🔴 CHỈ ĐỌC. Không `update_option`, không `UPDATE`, không `insert`. Chạy nhầm mười lần cũng
 *    không đổi một ô nào — đó là điều kiện để dám gửi đi các nơi.
 * 🔴 KHÔNG IN PIN, KHÔNG IN VECTOR KHUÔN MẶT. Kết quả lệnh này đi qua Zalo và ảnh chụp màn hình.
 *    Chỗ nào cần nhắc tới PIN thì chỉ nói CÓ hay KHÔNG.
 * =============================================================================================
 *
 * Chạy trên hosting (cần WP-CLI, đứng ở thư mục có wp-config.php):
 *
 *   curl -fsSL -o soat-nhan-su.php https://raw.githubusercontent.com/zairozy2004199x/khh-chamcong-firmware/claude/quan-tri-cham-cong-ovmiud/tools/soat-nhan-su.php
 *   wp eval-file soat-nhan-su.php
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 */

if ( ! class_exists( 'VHCC_NhanSu' ) ) {
	echo "🔴 Không thấy plugin Chấm Công (K&H) đang bật trên site này.\n";
	echo "   Kích hoạt nó rồi chạy lại: wp plugin activate vhcp-cham-cong\n";
	return;
}

function vhcc_soat_gach() { echo str_repeat( '─', 72 ) . "\n"; }
function vhcc_soat_de( $s ) { echo "\n▸ " . $s . "\n"; }
/**
 * Đệm một cột cho thẳng hàng.
 *
 * 🔴 `printf('%-34s')` ĐẾM BYTE, KHÔNG ĐẾM KÝ TỰ. Chữ tiếng Việt có dấu tốn 2–3 byte mỗi chữ,
 *    nên mọi dòng có dấu bị đẩy lệch — và bảng lệch thì người đọc bỏ qua nó. Cùng họ với cái
 *    bẫy `strtoupper` không nâng nổi chữ có dấu.
 */
function vhcc_soat_cot( $s, $rong ) {
	$s = (string) $s;
	$thua = $rong - mb_strlen( $s );
	return $s . ( $thua > 0 ? str_repeat( ' ', $thua ) : ' ' );
}
/** Kê danh sách mã, cắt bớt cho khỏi tràn màn hình nhưng VẪN nói ra tổng. */
function vhcc_soat_ke( $ds, $toi_da = 15 ) {
	$ds = array_values( (array) $ds );
	if ( ! $ds ) { return '(không có)'; }
	$hien = array_slice( $ds, 0, $toi_da );
	return implode( ', ', $hien ) . ( count( $ds ) > $toi_da
		? ' … (còn ' . ( count( $ds ) - $toi_da ) . ')' : '' );
}

vhcc_soat_gach();
echo "SOÁT HỒ SƠ NHÂN SỰ   ·   " . date( 'd/m/Y H:i' ) . "\n";
echo '   ' . get_option( 'siteurl' ) . "\n";
echo '   Chấm công ' . ( defined( 'VHCC_VERSION' ) ? VHCC_VERSION : '(không rõ)' )
	. ' · nguồn người dùng: ' . ( class_exists( 'VHCC_Auth' ) ? VHCC_Auth::nguon() : '?' ) . "\n";
vhcc_soat_gach();

/* ⚠️ Đọc bằng câu SQL thẳng, KHÔNG qua `ds_nhan_vien( $u )` — hàm ấy cần một người đăng nhập để
   xét phạm vi, mà chạy bằng WP-CLI thì không có ai đăng nhập cả. Ở đây ta cố ý xem toàn sổ. */
global $wpdb;
$t_nv = VHCC_DB::t( 'nhan_vien' );
$ds   = (array) VHCC_DB::rows( "SELECT ma_nv, ho_ten, cua_hang, coso_phu, coso_ql, mang, bo_phan,
	vai_tro, chuc_vu, trang_thai_lam_viec, CASE WHEN pin_dang_nhap <> '' THEN 1 ELSE 0 END co_pin
	FROM $t_nv" );

vhcc_soat_de( 'Tổng quan' );
$dang_lam = 0; $chua_pin = array(); $chua_ma = 0;
foreach ( $ds as $r ) {
	$tt = mb_strtolower( trim( (string) $r['trang_thai_lam_viec'] ) );
	if ( '' === $tt || false !== mb_strpos( $tt, 'đang' ) ) { $dang_lam++; }
	if ( empty( $r['co_pin'] ) ) { $chua_pin[] = (string) $r['ma_nv']; }
	if ( '' === trim( (string) $r['ma_nv'] ) ) { $chua_ma++; }
}
echo '    ' . vhcc_soat_cot( 'hồ sơ trong sổ', 34 ) . count( $ds ) . "\n";
echo '    ' . vhcc_soat_cot( 'đang làm', 34 ) . $dang_lam . "\n";
echo '    ' . vhcc_soat_cot( 'CHƯA có PIN (không đăng nhập được)', 34 ) . count( $chua_pin ) . "\n";
if ( $chua_ma ) { echo '    ' . vhcc_soat_cot( '🔴 hồ sơ CHƯA có Mã NV', 34 ) . $chua_ma . "\n"; }

/* ---- Mảng & bộ phận ---- */
$dem = VHCC_NhanSu::dem_mang_bo_phan( $ds );
vhcc_soat_de( 'Mảng kinh doanh (người làm nhiều mảng được đếm ở từng mảng)' );
foreach ( $dem['mang'] as $ten => $so ) {
	echo '    ' . vhcc_soat_cot( VHCC_NhanSu::ten_mang( $ten ), 34 ) . $so . "\n";
}
vhcc_soat_de( 'Bộ phận' );
foreach ( $dem['boPhan'] as $ten => $so ) { echo '    ' . vhcc_soat_cot( $ten, 34 ) . $so . "\n"; }

/* 🔴 ĐÂY LÀ DANH SÁCH VIỆC, KHÔNG PHẢI SỐ ĐỂ NGẮM. Người hệ không suy ra mảng thì đổi tên phòng
   theo mảng xong họ vẫn trôi, và bó phạm vi theo mảng cũng không bám vào đâu. */
if ( ! empty( $dem['canChonTay'] ) ) {
	$ai = array();
	foreach ( $ds as $r ) {
		$x = VHCC_NhanSu::mang_bo_phan_cua( $r );
		if ( ! empty( $x['canChonTay'] ) ) { $ai[] = (string) $r['ma_nv']; }
	}
	echo "\n    ⚠️ " . (int) $dem['canChonTay'] . " người hệ KHÔNG suy ra mảng "
		. "(chưa gắn cơ sở, hoặc cơ sở chưa ai khai mảng):\n       " . vhcc_soat_ke( $ai ) . "\n";
}
if ( ! empty( $dem['nhieuMang'] ) ) {
	echo "    ℹ️ " . (int) $dem['nhieuMang'] . " người làm từ hai mảng trở lên — đó là trạng thái"
		. " ĐÚNG, không phải lỗi.\n";
}

/* ---- Vai trò: ai bị chối ở cổng ---- */
vhcc_soat_de( 'Vai trò đang dùng' );
/* ⚠️ `dem_vai()` trả về array('vai' => …, 'chan' => …), KHÔNG trả thẳng bảng đếm. */
$dv = VHCC_NhanSu::dem_vai( $ds );
$vai_chan = array();
foreach ( (array) $dv['vai'] as $ten => $x ) {
	echo '    ' . vhcc_soat_cot( $ten, 34 ) . vhcc_soat_cot( (string) $x['so'], 6 )
		. ( empty( $x['vao'] ) ? '⛔ KHÔNG vào được cổng' : '' ) . "\n";
	if ( empty( $x['vao'] ) ) { $vai_chan[] = $ten; }
}
if ( $vai_chan ) {
	$ai = array();
	foreach ( $ds as $r ) {
		$t = trim( (string) $r['vai_tro'] );
		$k = ( '' !== $t ) ? $t : '— chưa khai vai —';
		if ( in_array( $k, $vai_chan, true ) ) { $ai[] = (string) $r['ma_nv']; }
	}
	/* 🔴 CẢNH NÀY IM LẶNG NHẤT TRONG CẢ HỆ: họ gõ đúng PIN, cổng vẫn chối, và màn hình chỉ nói
	   "PIN không đúng" — không ai đoán ra là do tên vai. */
	echo "\n    ⛔ " . count( $ai ) . " người mang vai hệ KHÔNG CÓ — gõ đúng PIN vẫn bị chối:\n"
		. '       ' . vhcc_soat_ke( $ai ) . "\n"
		. "       Sửa: đổi sang một vai có thật, HOẶC khai thêm vai ấy ở khối Bảng vai trò.\n";
}

/* ---- Trùng tên / trùng mã ---- */
vhcc_soat_de( 'Trùng tên · trùng mã' );
$tr = VHCC_NhanSu::dau_hieu_trung( $ds );
if ( ! $tr ) {
	echo "    (không có)\n";
} else {
	$nang = array(); $nhe = array();
	/* Hai kiểu trùng, hai mức nặng nhẹ — xem `dau_hieu_trung()`:
	   · `ma` = trùng MÃ NV: hai người cộng công vào nhau. Nặng nhất.
	   · `motNguoi` = trùng tên NGAY TRONG cùng cơ sở: gần như chắc một người hai hồ sơ, công
	     chẻ đôi và lương tính theo hai nửa.
	   · còn lại = trùng tên khác cơ sở: phần lớn là hai người thật, chỉ cần liếc qua. */
	foreach ( $tr as $ma => $x ) {
		if ( ! empty( $x['ma'] ) || ! empty( $x['motNguoi'] ) ) { $nang[] = $ma; } else { $nhe[] = $ma; }
	}
	if ( $nang ) {
		echo '    🔴 ' . count( $nang ) . " hồ sơ NẶNG (trùng mã, hoặc một người hai hồ sơ cùng cơ sở)"
			. " — công bị chẻ đôi hoặc cộng nhầm sang nhau:\n       " . vhcc_soat_ke( $nang ) . "\n";
	}
	if ( $nhe ) {
		echo '    ⚠️ ' . count( $nhe ) . " hồ sơ trùng tên nhưng KHÁC cơ sở — nhiều khả năng là hai"
			. " người thật:\n       " . vhcc_soat_ke( $nhe ) . "\n";
	}
}

/* ---- Vai theo bộ phận, luật quyền, bó phạm vi ---- */
vhcc_soat_de( 'Khai theo bộ phận' );
$ban  = VHCC_NhanSu::vai_theo_bo_phan();
$ds_bp = VHCC_NhanSu::ds_bo_phan();
$trong = array();
foreach ( $ds_bp as $bp ) { if ( empty( $ban[ $bp ] ) ) { $trong[] = $bp; } }
echo '    ' . vhcc_soat_cot( 'phòng đã khai vai', 34 )
	. ( count( $ds_bp ) - count( $trong ) ) . ' / ' . count( $ds_bp ) . "\n";
if ( $trong ) {
	echo "       chưa khai: " . vhcc_soat_ke( $trong, 20 ) . "\n";
	echo "       (bấm «Điền gợi ý cho phòng đang trống» ở màn Quản lý nhân sự)\n";
}
if ( class_exists( 'VHCC_Cong' ) && method_exists( 'VHCC_Cong', 'luat_nhom' ) ) {
	$l = VHCC_Cong::luat_nhom();
	$so_l = 0;
	foreach ( array( 'bp', 'mang' ) as $lo ) {
		foreach ( (array) $l[ $lo ] as $cac ) { $so_l += count( (array) $cac ); }
	}
	echo '    ' . vhcc_soat_cot( 'luật quyền theo nhóm đang khai', 34 ) . $so_l . " ô\n";
	if ( ! $so_l ) {
		echo "       (chưa khai ô nào — quyền vào trang vẫn đi theo thang vai như cũ)\n";
	}
}
if ( method_exists( 'VHCC_NhanSu', 'bo_mang_ds' ) ) {
	$bo = VHCC_NhanSu::bo_mang_ds();
	echo '    ' . vhcc_soat_cot( 'phòng bị BÓ phạm vi theo mảng', 34 )
		. ( $bo ? vhcc_soat_ke( $bo, 20 ) : 'chưa bó phòng nào (mọi người nhìn theo thang vai)' ) . "\n";
}

/* ---- Đẩy sang hệ khác ---- */
if ( class_exists( 'VHCC_DayChiPhi' ) && VHCC_DayChiPhi::co_he_chi_phi() ) {
	vhcc_soat_de( 'Đẩy sang Vận hành chi phí' );
	$da = (array) VHCC_DayChiPhi::da_day_ds();
	echo '    ' . vhcc_soat_cot( 'người có tài khoản bên ấy', 34 ) . count( $da ) . "\n";
	if ( method_exists( 'VHCC_DayChiPhi', 'bo_phan_day' ) ) {
		$ho = array();
		foreach ( $ds as $r ) { $ho[ (string) $r['ma_nv'] ] = $r; }
		$khong_bo = array();
		foreach ( $da as $ma ) {
			$ma = trim( (string) $ma );
			if ( '' === $ma || ! isset( $ho[ $ma ] ) ) { continue; }
			if ( '' === VHCC_DayChiPhi::bo_phan_day( $ho[ $ma ] ) ) { $khong_bo[] = $ma; }
		}
		if ( $khong_bo ) {
			/* 🔴 Bên chi phí quy mọi tên bộ phận nó không hiểu về "không bó" — tức xem được sổ
			   của MỌI mảng. Con số này trước đây không hiện ở đâu, cả hai bên. */
			echo '    🔴 ' . count( $khong_bo ) . " người KHÔNG bị bó bộ phận — xem được sổ chi phí"
				. " của MỌI mảng:\n       " . vhcc_soat_ke( $khong_bo ) . "\n"
				. "       Sửa: khai bản đồ mảng → bộ phận ở khối «Đẩy sang Vận hành chi phí».\n";
		} else {
			echo "    ✓ mọi người đều gửi kèm một bộ phận bên ấy hiểu\n";
		}
	}
}
if ( class_exists( 'VHCC_DayGhe' ) && VHCC_DayGhe::co_he_ghe() ) {
	vhcc_soat_de( 'Đẩy sang hệ Ghế massage' );
	echo '    ' . vhcc_soat_cot( 'sổ người dùng đang đọc', 34 ) . VHCC_DayGhe::nguon_dung() ? 'SỔ RIÊNG (đúng)' : '🔴 SỔ CHUNG' . "\n";
	$so_ghe = 0;
	foreach ( $ds as $r ) { if ( VHCC_DayGhe::da_day( (string) $r['ma_nv'] ) ) { $so_ghe++; } }
	echo '    ' . vhcc_soat_cot( 'người có tài khoản bên ấy', 34 ) . $so_ghe . "\n";
}

echo "\n";
vhcc_soat_gach();
echo "Xong. Lệnh này CHỈ ĐỌC — không đổi một ô nào trong sổ.\n";
vhcc_soat_gach();
