<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NỘP THEO CƠ SỞ ĐƯỢC TÍCH
 *
 * Anh Thắng 05/09/2026: *"chỗ phần tôi đang cầm tiền: hiện cơ sở nào chưa nộp · khi nhân viên
 * nộp hoặc gửi bill thì tích vào sẽ nộp cơ sở nào · nếu tích ít hơn thì sẽ hiện lại tổng số tiền
 * cơ sở tích"*.
 *
 * 🔴 CHỖ NGUY NHẤT LÀ MỘT CÂU LỌC RƠI MẤT. `nop()` gắn dòng bằng ba lệnh UPDATE; điều kiện cơ sở
 *    được NỐI vào cả ba. Sót một lệnh thì cú bấm "nộp hai cơ sở" lặng lẽ nộp luôn cơ sở thứ ba —
 *    tiền vẫn vào sổ, không có lỗi nào hiện ra, và người nộp tưởng mình còn cầm số ấy trên tay.
 *    Ngược lại, một câu lọc rỗng lọt qua là NỘP TẤT — đúng thứ người ta vừa cố tránh.
 *
 * ⚠️ BỆ ĐỠ NÀY KHÔNG CHẠY SQL THẬT, và đó là chỗ mù cần ghi rõ: nó dựng ra câu điều kiện rồi soi
 *    câu ấy, chứ không kiểm được MySQL hiểu câu đó thế nào. Cái nó bắt được là luật dựng câu —
 *    tích gì thì lọc gì, và bao giờ thì KHÔNG được lọc. Cái nó không bắt được là lỗi cú pháp
 *    SQL hay hành vi của `NOT IN` với NULL.
 *
 * Chạy: php tools/test/kiem-nop-theo-coso.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$SRC = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-quy.php' );

/* ---------- Bốc đoạn dựng điều kiện lọc, từ mã nguồn ---------- */
$i = strpos( $SRC, '$loc_cs = array();' );
$j = strpos( $SRC, '/* 🔴 GỬI LẠI THÌ TRẢ VỀ LƯỢT CŨ', $i );
t( 'bốc được đoạn dựng điều kiện lọc', false !== $i && $j > $i );
if ( false === $i || $j <= $i ) { echo "✗ không bốc được — dừng.\n"; exit( 1 ); }
$LOI = substr( $SRC, $i, $j - $i );

/* ---------- Bệ đỡ ---------- */
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }
class WpdbGia {
	public function prepare( $sql, ...$a ) {
		foreach ( $a as $x ) {
			$sql = preg_replace( '/%s|%d/', is_int( $x ) ? (string) $x : "'" . $x . "'", $sql, 1 );
		}
		return $sql;
	}
}
$wpdb = new WpdbGia();

/* Nhãn "(chưa gán)" đọc từ CHÍNH mã nguồn — chép tay ở đây thì đổi nhãn bên kia mà bài vẫn xanh,
   trong khi đúng cái nhãn ấy là thứ nối bảng đọc với đường nộp. */
preg_match( "/const CS_CHUA_GAN = '([^']*)';/u", $SRC, $m );
$CHUA_GAN = isset( $m[1] ) ? $m[1] : '';
t( 'đọc được nhãn "chưa gán" từ mã nguồn', '' !== $CHUA_GAN, $CHUA_GAN );

/* `self::CS_CHUA_GAN` chỉ chạy được trong một lớp — gói đoạn bốc vào một lớp mang đúng hằng ấy. */
eval( 'class Loc {
	const CS_CHUA_GAN = ' . var_export( $CHUA_GAN, true ) . ';
	public static function dung( $coso_ds ) { global $wpdb; ' . $LOI
	. ' return array( "may" => $dk_may, "bc" => $dk_bc, "loc" => $loc_cs ); }
}' );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. KHÔNG TÍCH GÌ = NỘP TẤT (đường cũ, phải giữ nguyên)
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 `null` là thứ mọi nơi khác trong hệ vẫn gửi: app, nộp thay, nộp qua màn quỹ cũ. Đổi nghĩa
   của nó thành "nộp 0 đồng" là làm hỏng những chỗ chưa hề biết tới tính năng này. */
$r = Loc::dung( null );
teq( '🔴 không truyền gì -> KHÔNG lọc (nộp tất), câu điều kiện rỗng', '', $r['may'] );
teq( 'và câu cho bảng báo cáo cũng rỗng', '', $r['bc'] );
$r = Loc::dung( array() );
teq( '🔴 mảng rỗng -> cũng nộp tất, không lọc', '', $r['may'] );
teq( 'và bảng báo cáo cũng vậy', '', $r['bc'] );
/* Mảng toàn chuỗi rỗng/khoảng trắng cũng là "không tích gì" — không được biến thành `1=0`. */
$r = Loc::dung( array( '', '   ' ) );
teq( '🔴 toàn tên rỗng -> nộp tất, không phải nộp 0 đồng', '', $r['may'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. TÍCH MỘT VÀI CƠ SỞ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$r = Loc::dung( array( 'AM-TP' ) );
t( '🔴 tích một cơ sở -> CÓ lọc ở đường ngăn ghế / tại quầy', '' !== $r['may'], $r['may'] );
t( 'và lọc đúng tên ấy', false !== strpos( $r['may'], "'AM-TP'" ), $r['may'] );
t( '🔴 lọc qua bảng ghế để ra cơ sở, không so tên ghế với tên cơ sở',
	false !== strpos( $r['may'], VHG_DB::t( 'may' ) ) && false !== strpos( $r['may'], VHG_DB::t( 'coso' ) ), $r['may'] );
t( '🔴 đường báo cáo lọc THẲNG cột cơ sở (bc.coso đã là tên sẵn)',
	false !== strpos( $r['bc'], "coso IN ('AM-TP')" ), $r['bc'] );

$r = Loc::dung( array( 'AM-TP', 'GO TRƯỜNG CHINH' ) );
t( '🔴 tích hai cơ sở -> cả hai tên đều vào câu lọc',
	false !== strpos( $r['may'], "'AM-TP'" ) && false !== strpos( $r['may'], "'GO TRƯỜNG CHINH'" ), $r['may'] );
t( 'và bảng báo cáo cũng đủ hai tên',
	false !== strpos( $r['bc'], "'AM-TP'" ) && false !== strpos( $r['bc'], "'GO TRƯỜNG CHINH'" ), $r['bc'] );
/* Trùng tên (bấm hai lần, dữ liệu lặp) không được đẻ ra hai lần trong câu lọc. */
$r = Loc::dung( array( 'AM-TP', 'AM-TP' ) );
teq( '🔴 tên trùng chỉ tính một lần', 1, count( $r['loc'] ) );
teq( 'và chỉ vào câu lọc một lần', 1, substr_count( $r['may'], "'AM-TP'" ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. NHÓM "(CHƯA GÁN)" — TIỀN CỦA GHẾ KHÔNG TRA RA CƠ SỞ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 NHỮNG ĐỒNG NÀY KHÔNG ĐƯỢC NẰM LẠI VĨNH VIỄN. Ghế chưa gán cơ sở, hay mã ghế đã bị xoá khỏi
   danh mục, thì tra ra rỗng. Bảng đọc gom chúng vào `(chưa gán)`; nếu đường NỘP không hiểu cái
   nhãn ấy thì không nhóm nào nộp được chúng — tích vào cũng vô ích, và tiền treo mãi trên sổ. */
$r = Loc::dung( array( $CHUA_GAN ) );
t( '🔴 tích "(chưa gán)" -> có lọc, không phải câu rỗng', '' !== $r['may'], $r['may'] );
t( '🔴 và lọc bằng NOT IN (ghế không tra ra cơ sở nào)',
	false !== strpos( $r['may'], 'NOT IN' ), $r['may'] );
t( '🔴 nhãn "(chưa gán)" KHÔNG được đem đi so với tên cơ sở thật',
	false === strpos( $r['may'], "'" . $CHUA_GAN . "'" ), $r['may'] );
t( 'đường báo cáo bắt cơ sở rỗng', false !== strpos( $r['bc'], "coso=''" ), $r['bc'] );

/* Tích CẢ cơ sở thật LẪN "(chưa gán)" — phải lấy cả hai, nối bằng OR chứ không phải AND. */
$r = Loc::dung( array( 'AM-TP', $CHUA_GAN ) );
t( '🔴 tích cả cơ sở thật lẫn (chưa gán) -> nối bằng OR, không phải AND',
	false !== strpos( $r['may'], ' OR ' ) && false === strpos( $r['may'], ' AND (' . "'" ), $r['may'] );
t( 'vẫn còn tên cơ sở thật', false !== strpos( $r['may'], "'AM-TP'" ), $r['may'] );
t( 'và vẫn còn vế NOT IN', false !== strpos( $r['may'], 'NOT IN' ), $r['may'] );
t( '🔴 đường báo cáo cũng lấy cả hai',
	false !== strpos( $r['bc'], "'AM-TP'" ) && false !== strpos( $r['bc'], "coso=''" )
	&& false !== strpos( $r['bc'], ' OR ' ), $r['bc'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. CÂU LỌC KHÔNG BAO GIỜ ĐƯỢC RỖNG KHI ĐÃ TÍCH
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 ĐÂY LÀ CA HỎNG IM LẶNG TỆ NHẤT. Tích một cơ sở không còn tồn tại (vừa bị đổi tên/xoá ở tab
   khác) mà câu lọc dựng ra RỖNG thì nó thành NỘP TẤT — người ta tích một dòng và nộp mất cả
   vòng. Phải là `1=0`: gắn được 0 dòng, lượt nộp bị huỷ, và câu trả lời nói rõ. */
foreach ( array(
	array( 'AM-TP' ), array( $CHUA_GAN ), array( 'AM-TP', $CHUA_GAN ),
	array( 'CƠ SỞ KHÔNG CÓ THẬT' ), array( "tên có ' nháy" ),
) as $ca ) {
	$r = Loc::dung( $ca );
	t( '🔴 đã tích [' . implode( ', ', $ca ) . '] -> câu lọc KHÔNG rỗng', '' !== $r['may'], $r );
	t( '   và câu cho bảng báo cáo cũng không rỗng', '' !== $r['bc'], $r );
	/* Cả hai câu phải NỐI ĐƯỢC vào một câu WHERE có sẵn — thiếu " AND " là gãy cú pháp SQL. */
	t( '   và nối được vào câu WHERE (bắt đầu bằng AND)',
		' AND ' === substr( $r['may'], 0, 5 ) && ' AND ' === substr( $r['bc'], 0, 5 ), $r );
}

/* Tên có dấu nháy — phải được thoát, không được cắt đôi câu SQL. */
$r = Loc::dung( array( "O'Brien" ) );
t( 'tên có dấu nháy vẫn dựng được câu lọc', '' !== $r['may'] && '' !== $r['bc'], $r );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. CÂU LỌC PHẢI ĐƯỢC NỐI VÀO CẢ BA LỆNH GẮN DÒNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 SÓT MỘT LỆNH = HỎNG IM LẶNG. Ba nguồn tiền, ba lệnh UPDATE. Sót lệnh nào thì tiền của
   nguồn ấy bị nộp luôn dù không tích — không có lỗi nào hiện ra, và người nộp tưởng mình còn
   cầm số đó. Canh VỊ TRÍ trong mã nguồn: `$dk_may` phải nối vào hai lệnh (chốt ca, thu tại
   quầy), `$dk_bc` vào lệnh còn lại (báo cáo doanh thu). */
$than = substr( $SRC, strpos( $SRC, 'public static function nop(' ) );
$than = substr( $than, 0, strpos( $than, "\n\t/**" ) );
teq( '🔴 lệnh gắn CHỐT CA có nối câu lọc', 1,
	preg_match_all( '/UPDATE \$tc SET nop_id=%d[^;]*\. \$dk_may \);/', $than ) );
teq( '🔴 lệnh gắn THU TẠI QUẦY có nối câu lọc', 1,
	preg_match_all( '/UPDATE \$tt SET nop_id=%d[^;]*\. \$dk_may \);/', $than ) );
teq( '🔴 lệnh gắn BÁO CÁO DOANH THU có nối câu lọc', 1,
	preg_match_all( '/UPDATE \$tb SET nop_id=%d[^;]*\. \$dk_bc \);/', $than ) );
/* Và không lệnh nào bị bỏ quên: đếm tổng số lệnh gắn dòng, phải đúng ba. */
teq( '🔴 đúng BA lệnh gắn dòng, không hơn không kém', 3,
	preg_match_all( '/SET nop_id=%d WHERE/', $than ) );

/* Câu chối khi tích mà không gắn được đồng nào phải NÓI RA cơ sở đã tích — "đang không cầm đồng
   nào" là nói sai, vì họ đang cầm tiền của cơ sở khác. */
t( '🔴 chối vì tích nhầm cơ sở thì nói ra cơ sở đã tích',
	false !== strpos( $than, "'Không có đồng nào chưa nộp ở cơ sở đã tích ('" ), null );
t( 'và câu cũ vẫn giữ cho ca nộp tất',
	false !== strpos( $than, "'Anh/chị đang không cầm đồng nào chưa nộp.'" ), null );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. BẤT BIẾN GIỮ CHO CÂU LỌC KHÔNG BAO GIỜ RỖNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 Mục 4 canh KẾT QUẢ ("đã tích thì câu lọc không rỗng"). Mục này canh CÁI GIỮ cho kết quả ấy
   đúng: mỗi tên đã tích rơi vào đúng MỘT trong hai rổ, và mỗi rổ có hàng thì sinh đúng MỘT vế.
   Phá bất biến ấy là câu lọc rỗng đi được, và rỗng nghĩa là NỘP TẤT.
   ⚠️ Không dùng nhánh dự phòng `1=0` để vá: nhánh ấy không đạt tới được, và mã chết thì không
      ai chạy qua để biết nó còn đúng — nó chỉ nằm đó tạo cảm giác an toàn. */
foreach ( array(
	array( 'A' ),
	array( 'A', 'B' ),
	array( $CHUA_GAN ),
	array( 'A', $CHUA_GAN ),
	array( 'A', 'B', $CHUA_GAN ),
) as $ca ) {
	$r = Loc::dung( $ca );
	/* Số vế = số tên cơ sở thật gộp làm MỘT vế IN, cộng thêm vế NOT IN nếu có (chưa gán). */
	$co_cg  = in_array( $CHUA_GAN, $ca, true );
	$so_that = count( array_diff( $ca, array( $CHUA_GAN ) ) );
	$mong_ve = ( $so_that ? 1 : 0 ) + ( $co_cg ? 1 : 0 );
	$thuc_ve = ( false !== strpos( $r['may'], ' IN (SELECT' ) ? 1 : 0 )
		+ ( false !== strpos( $r['may'], 'NOT IN (SELECT' ) ? 1 : 0 );
	/* `NOT IN (SELECT` cũng khớp ` IN (SELECT` — trừ lại để đếm đúng hai vế khác nhau. */
	if ( false !== strpos( $r['may'], 'NOT IN (SELECT' ) && $so_that < 1 ) { $thuc_ve = 1; }
	teq( '🔴 [' . implode( ', ', $ca ) . '] -> đúng ' . $mong_ve . ' vế', $mong_ve, $thuc_ve );
	teq( '   và không tên nào rơi mất khỏi danh sách đã tích', count( array_unique( $ca ) ), count( $r['loc'] ) );
}
/* Và mã nguồn KHÔNG được có nhánh dự phòng chết ấy — nếu nó quay lại thì bất biến trên hết
   được canh, vì mọi ca đều rơi vào nhánh vá.
   🔴 BÓC CHÚ THÍCH RA TRƯỚC KHI DÒ. Chính chú thích của hàm ấy nhắc lại `'1=0'` để dặn người sau
      đừng thêm nhánh đó — dò trên nguyên văn thì bài ĐỎ vì đúng câu dặn ấy. Đây là mặt kia của
      cái bẫy đã cắn kho này bốn lần theo chiều tự XANH: một phép dò chuỗi không phân biệt được
      MÃ với CHÚ THÍCH thì nó không canh mã, nó canh văn bản. */
function bo_chu_thich_( $ma ) {
	$ma = preg_replace( '#/\*.*?\*/#su', ' ', $ma );      // chú thích khối
	return preg_replace( '#//[^\n]*#u', ' ', $ma );        // chú thích dòng
}
$LOI_MA = bo_chu_thich_( $LOI );
t( 'đối chứng: bóc chú thích rồi vẫn còn mã thật', false !== strpos( $LOI_MA, '$dk_may' ), $LOI_MA );
t( 'và câu dặn trong chú thích đã bị bóc đi', false === strpos( $LOI_MA, 'KHÔNG\n' ), null );
t( '🔴 không còn nhánh dự phòng chết `1=0` trong MÃ',
	false === strpos( $LOI_MA, '1=0' ), $LOI_MA );

/* ---------- KẾT ---------- */
if ( $TRUOT ) {
	echo "\n✗ HỎNG " . count( $TRUOT ) . " phép:\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	exit( 1 );
}
echo '✓ SẠCH — ' . $DAT . " phép: tích cơ sở nào thì nộp đúng cơ sở ấy, không tích thì nộp tất.\n";
