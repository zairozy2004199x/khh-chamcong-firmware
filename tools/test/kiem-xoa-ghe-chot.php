<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CHỐT PHÍA MÁY CHỦ: KHÔNG XOÁ GHẾ ĐÃ CÓ TIỀN TRONG SỔ
 *
 * Anh Thắng 05/09/2026 xin xoá được ghế ngay tại bảng. Nút xoá thì dễ; cái khó là ghế ĐANG CHẠY.
 *
 * 🔴 XOÁ GHẾ KHÔNG LÀM MẤT TIỀN — NÓ LÀM MẤT TÊN. Doanh thu cũ nằm ở `bc_dong`/`thu` theo `ma_may`
 *    chứ không theo id, nên xoá ghế thì mấy trăm dòng ấy còn nguyên. Chỉ là từ lúc đó bảng chéo,
 *    báo cáo tổng, đối chiếu kế toán đều mang một mã ghế mà tra ra không còn ghế nào. Số vẫn
 *    đúng, chỉ là không ai biết nó của cái ghế nào nữa — và không có cách nào lấy lại.
 *
 * ⚠️ CHỐT PHẢI Ở MÁY CHỦ, KHÔNG PHẢI Ở NÚT. Nút bấm là gợi ý; cổng `may_xoa` nhận lệnh từ bất cứ
 *    đâu có phiên quản trị. Bài này canh CHỐT, không canh nút.
 *
 * ⚠️ BỐC THẲNG HÀM `xoa_may()` TỪ MÃ NGUỒN RA CHẠY với một `$wpdb` giả — không chép lại, không dò
 *    chuỗi. Nhánh này chưa có bệ đỡ WordPress giả nào nên bệ ở ngay dưới, đủ đúng bốn thứ hàm ấy
 *    dùng: `prepare`, `get_var`, `delete`, và `VHG_DB::t()`.
 *
 * Chạy: php tools/test/kiem-xoa-ghe-chot.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' );
}

/* ---------- Bốc hàm ra ---------- */
$nguon = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-may.php' );
$i = strpos( $nguon, 'public static function xoa_may( $ma ) {' );
$j = strpos( $nguon, "\n\t/**", $i );
t( 'bốc được hàm xoá ghế', false !== $i && $j > $i );
if ( false === $i || $j <= $i ) { echo "✗ không bốc được hàm — dừng.\n"; exit( 1 ); }
$ham = substr( $nguon, $i, $j - $i );

/* ---------- Bệ đỡ tí hon ---------- */
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }

class WpdbGia {
	public $dem = array();      // bảng => số dòng khớp
	public $daXoa = null;       // ghi lại lượt xoá, để soi nó xoá đúng cái gì
	public $choXoa = 1;         // wpdb->delete trả về số hàng bị xoá (0 = không có hàng nào)
	public function prepare( $sql, ...$args ) {
		/* Đủ dùng cho bài này: nhét thẳng đối số vào, giữ nguyên chuỗi SQL để soi. */
		foreach ( $args as $a ) {
			$sql = preg_replace( '/%s|%d/', is_int( $a ) ? (string) $a : "'" . $a . "'", $sql, 1 );
		}
		return $sql;
	}
	public function get_var( $sql ) {
		foreach ( $this->dem as $bang => $n ) {
			if ( false !== strpos( $sql, VHG_DB::t( $bang ) ) ) { return $n; }
		}
		return 0;
	}
	public function delete( $bang, $dk ) { $this->daXoa = array( $bang, $dk ); return $this->choXoa; }
}

/* Hàm bốc ra được nhét vào một lớp vỏ rồi chạy — chạy chính mã nguồn, không phải bản chép. */
eval( 'class ThuXoa { ' . $ham . ' }' );

function goi( $ma, $demDong, $demThu, $choXoa = 1 ) {
	global $wpdb;
	$wpdb = new WpdbGia();
	$wpdb->dem = array( 'bc_dong' => $demDong, 'thu' => $demThu );
	$wpdb->choXoa = $choXoa;
	return array( ThuXoa::xoa_may( $ma ), $wpdb );
}

/* ---------- 1. GHẾ TRẮNG SỔ: XOÁ ĐƯỢC ---------- */
list( $r, $db ) = goi( 'GONHAM', 0, 0 );
t( '🔴 ghế chưa từng có lượt nào thì xoá được', ! empty( $r['ok'] ), $r );
t( 'và nó xoá đúng bảng danh mục ghế', $db->daXoa && VHG_DB::t( 'may' ) === $db->daXoa[0], $db->daXoa );
t( '🔴 xoá đúng MÃ được gửi tới, không xoá theo gì khác',
	$db->daXoa && array( 'ma' => 'GONHAM' ) === $db->daXoa[1], $db->daXoa );

/* ---------- 2. GHẾ CÓ TIỀN TRONG SỔ: CHỐI ---------- */
/* Ba ca, vì hai bảng độc lập nhau: một ghế có thể có dòng báo cáo mà chưa có lượt thu (mới nhập
   chỉ số, chưa nộp tiền), hoặc ngược lại. Chỉ canh một bảng là để lọt ca kia. */
list( $r1, $db1 ) = goi( '80128', 12, 0 );
t( '🔴 có dòng báo cáo thì CHỐI', empty( $r1['ok'] ), $r1 );
t( '🔴 và KHÔNG đụng gì tới bảng ghế', null === $db1->daXoa, $db1->daXoa );

list( $r2, $db2 ) = goi( '80128', 0, 5 );
t( '🔴 có lượt thu thì CHỐI', empty( $r2['ok'] ), $r2 );
t( 'và cũng không đụng gì tới bảng ghế', null === $db2->daXoa, $db2->daXoa );

list( $r3, $db3 ) = goi( '80128', 12, 5 );
t( 'có cả hai thì vẫn chối', empty( $r3['ok'] ), $r3 );

/* ---------- 3. CÂU CHỐI PHẢI DÙNG ĐƯỢC ---------- */
/* Một câu "không xoá được" trống trơn thì người ta bấm lại, rồi đi hỏi. Câu chối phải nói ra
   ĐANG VƯỚNG BAO NHIÊU và LỐI ĐI ĐÚNG LÀ GÌ. */
t( '🔴 câu chối nói ra SỐ dòng đang vướng', false !== strpos( $r3['error'], '12' )
	&& false !== strpos( $r3['error'], '5' ), $r3['error'] );
t( '🔴 và chỉ đường sang "Điều chuyển"', false !== strpos( $r3['error'], 'Điều chuyển' ), $r3['error'] );
t( 'nói rõ điều chuyển thì chỉ số và doanh thu giữ nguyên',
	false !== strpos( $r3['error'], 'giữ nguyên' ), $r3['error'] );
t( 'và đưa về lại được', false !== strpos( $r3['error'], 'đưa về lại được' ), $r3['error'] );
t( 'câu chối gọi tên ghế, để biết đang nói về cái nào',
	false !== strpos( $r3['error'], '80128' ), $r3['error'] );
/* Chỉ vướng một bảng thì đừng kể cả bảng kia — "0 lượt thu" là thông tin thừa gây rối. */
t( '🔴 chỉ vướng dòng báo cáo thì không kể tới lượt thu',
	false === strpos( $r1['error'], 'lượt thu' ), $r1['error'] );
t( '🔴 chỉ vướng lượt thu thì không kể tới dòng báo cáo',
	false === strpos( $r2['error'], 'dòng báo cáo' ), $r2['error'] );

/* ---------- 4. MÃ RỖNG / MÃ KHÔNG CÓ THẬT ---------- */
/* Mã rỗng lọt qua là một lệnh xoá không có điều kiện — thứ tệ nhất có thể xảy ra ở đây. */
list( $r4, $db4 ) = goi( '', 0, 0 );
t( '🔴 mã rỗng thì chối ngay, không đụng cơ sở dữ liệu', empty( $r4['ok'] ) && null === $db4->daXoa, $r4 );
list( $r5, $db5 ) = goi( '   ', 0, 0 );
t( '🔴 mã chỉ có khoảng trắng cũng vậy', empty( $r5['ok'] ) && null === $db5->daXoa, $r5 );

/* Không có hàng nào bị xoá = mã không có thật. Báo "đã xoá" ở đây là nói dối, và người dùng sẽ
   đi tìm xem cái ghế kia biến đi đâu. */
list( $r6 ) = goi( 'KHONGCO', 0, 0, 0 );
t( '🔴 không có ghế nào mang mã ấy thì nói thật, không báo "đã xoá"', empty( $r6['ok'] ), $r6 );

/* ---------- 5. ĐỐI CHỨNG ---------- */
/* Nếu bệ đỡ giả không thật sự cho hàm chạy tới nơi thì mọi phép trên xanh vô nghĩa. */
list( $rd, $dbd ) = goi( 'ABC', 0, 0 );
t( 'đối chứng: bệ đỡ thật sự chạy được tới lượt xoá', null !== $dbd->daXoa && ! empty( $rd['thong_bao'] ), $rd );

/* ---------- KẾT ---------- */
if ( $TRUOT ) {
	echo "\n✗ HỎNG " . count( $TRUOT ) . " phép:\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	exit( 1 );
}
echo '✓ SẠCH — ' . $DAT . " phép: ghế đã có tiền trong sổ thì không xoá được, và câu chối chỉ đúng lối đi.\n";
