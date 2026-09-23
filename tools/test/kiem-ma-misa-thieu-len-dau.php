<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG UNIT ID MISA: THIẾU LÊN ĐẦU, ĐÓNG CỬA XUỐNG CUỐI VÀ KHÔNG BỊ GẮN "THIẾU"
 *
 * Anh Thắng 23/09/2026: *"cơ sở nào thiếu thông tin như unit hoặc mã ghế thì hiện đầu để kế toán
 * bổ sung. Chứ nhiều quá không biết được"*; *"khi cửa hàng đóng cửa cần ẩn cơ sở và tạo bảng riêng"*.
 *
 * 🔴 Sáu chục dòng, ba cái thiếu rải rác — mắt không tìm ra không phải lỗi mắt, là bảng xếp sai.
 *    Máy chủ phải xếp, màn hình chỉ dán nhãn; xếp ở màn hình là mỗi màn một luật.
 *
 * Chạy: php tools/test/kiem-ma-misa-thieu-len-dau.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }
class WpdbGia {
	public $misa = array(); public $coso = array(); public $may = array();
	public function get_results( $q, $o = null ) {
		if ( false !== strpos( $q, 'bc_ma_misa' ) ) { return $this->misa; }
		if ( false !== strpos( $q, 'wp_vhg_coso' ) ) { return $this->coso; }
		if ( false !== strpos( $q, 'wp_vhg_may' ) ) { return $this->may; }
		return array();
	}
}
$src = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
$f = boc( $src, 'public static function ma_misa_ds(' );
t( 'bốc được ma_misa_ds', '' !== $f ); if ( '' === $f ) { exit( 1 ); }
eval( 'class VHG_KeToan { public static function squash( $s ) { return preg_replace( "/[^A-Z0-9]/", "", strtoupper( $s ) ); } ' . $f . ' }' );
global $wpdb; $wpdb = new WpdbGia();
$wpdb->misa = array(   // thứ tự CSDL cố tình lộn xộn
	array( 'coso_key' => 'DU', 'coso' => 'Du', 'unit_id' => '59DU', 'unit_name' => 'DU', 'vung' => '', 'thu_tu' => 0, 'ghi_chu' => '' ),
	array( 'coso_key' => 'DONG', 'coso' => 'Dong', 'unit_id' => '', 'unit_name' => '', 'vung' => '', 'thu_tu' => 0, 'ghi_chu' => '' ),
	array( 'coso_key' => 'THIEUUNIT', 'coso' => 'Thieu Unit', 'unit_id' => '', 'unit_name' => 'x', 'vung' => '', 'thu_tu' => 0, 'ghi_chu' => '' ),
	array( 'coso_key' => 'THIEUHET', 'coso' => 'Thieu Het', 'unit_id' => '', 'unit_name' => '', 'vung' => '', 'thu_tu' => 5, 'ghi_chu' => '' ),
	array( 'coso_key' => 'CHIGHEAN', 'coso' => 'Chi Ghe An', 'unit_id' => '60X', 'unit_name' => 'x', 'vung' => '', 'thu_tu' => 0, 'ghi_chu' => '' ),
);
$wpdb->coso = array(
	array( 'id' => 1, 'ten' => 'Du', 'ma_kh' => 'KH1', 'dong_cua' => 0 ),
	array( 'id' => 2, 'ten' => 'Dong', 'ma_kh' => '', 'dong_cua' => 1 ),
	array( 'id' => 3, 'ten' => 'Thieu Unit', 'ma_kh' => 'KH3', 'dong_cua' => 0 ),
	array( 'id' => 4, 'ten' => 'Thieu Het', 'ma_kh' => '', 'dong_cua' => 0 ),
	array( 'id' => 5, 'ten' => 'Chi Ghe An', 'ma_kh' => 'KH5', 'dong_cua' => 0 ),
);
$wpdb->may = array( array( 'coso_id' => 1, 'n' => 12 ), array( 'coso_id' => 3, 'n' => 2 ) );   // đã lọc an=0 ở SQL → 5 không có ghế sống
$r = VHG_KeToan::ma_misa_ds(); $rows = $r['rows'];
$ten = array_map( function ( $x ) { return $x['coso']; }, $rows );
$theo = array(); foreach ( $rows as $x ) { $theo[ $x['coso'] ] = $x; }
echo "── Thứ tự ──\n";
t( '🔴 THIẾU NHIỀU NHẤT lên đầu (Thieu Het: unit+kh+ghe)', 'Thieu Het' === $ten[0], $ten );
t( 'rồi thiếu ít hơn (Thieu Unit: unit · Chi Ghe An: ghe), rồi đủ (Du)', array_search( 'Du', $ten ) > array_search( 'Thieu Unit', $ten ) && array_search( 'Du', $ten ) > array_search( 'Chi Ghe An', $ten ), $ten );
t( '🔴 ĐÓNG CỬA xuống CUỐI dù thiếu đủ thứ', 'Dong' === $ten[ count( $ten ) - 1 ], $ten );
echo "── Nhãn thiếu ──\n";
t( 'Thieu Het gắn đủ ba nhãn', array( 'unit', 'kh', 'ghe' ) === $theo['Thieu Het']['thieu'], $theo['Thieu Het']['thieu'] );
t( 'Thieu Unit chỉ thiếu unit', array( 'unit' ) === $theo['Thieu Unit']['thieu'] );
t( '🔴 ghế ẨN không tính là "có ghế" → Chi Ghe An thiếu ghe', array( 'ghe' ) === $theo['Chi Ghe An']['thieu'] && 0 === $theo['Chi Ghe An']['so_ghe'] );
t( 'Du: không thiếu gì, 12 ghế', array() === $theo['Du']['thieu'] && 12 === $theo['Du']['so_ghe'] );
t( '🔴 đóng cửa KHÔNG bị gắn thiếu (không ai bổ sung Unit ID cho chỗ đã đóng)', array() === $theo['Dong']['thieu'] && 1 === $theo['Dong']['dong_cua'] );
t( 'ma_kh vẫn kèm theo để xem', 'KH1' === $theo['Du']['ma_kh'] && '' === $theo['Thieu Het']['ma_kh'] );
echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
