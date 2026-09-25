<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ: CẦU RPC PHẢI NHẬN PHIÊN VÉ TỪ GHẾ, KHÔNG CHỈ PIN GÕ TAY (0.48.0)
 *
 * Anh Thắng 25/09/2026: vào bằng vé xong, Tổng quan báo "Không tải được tổng quan: Sai mã PIN".
 * can_pin() — cửa của mọi hàm RPC — chỉ so PIN trong args; đường vé thì PIN rỗng. Một luật chung
 * pin_hop_le_(): có phiên vé HOẶC PIN đúng.
 *
 * Chạy: php tools/test/kiem-saoke-rpc-nhan-phien-ve.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
$OPT = array( 'saoke_pin' => '4321' ); $TRANS = array();
function get_option( $k, $d = '' ) { global $OPT; return isset( $OPT[ $k ] ) ? $OPT[ $k ] : $d; }
function get_transient( $k ) { global $TRANS; return isset( $TRANS[ $k ] ) ? $TRANS[ $k ] : false; }
function delete_transient( $k ) { global $TRANS; unset( $TRANS[ $k ] ); return true; }
function is_ssl() { return true; }

$src = file_get_contents( __DIR__ . '/../../vhcp-saoke/vhcp-saoke.php' );
$fs = ''; foreach ( array( 'private static function pin_hop_le_(', 'private static function can_pin(', 'private static function phien_ok_(', 'public static function rpc_checkPin(', 'public static function rpc_khoaPhien(' ) as $mo ) { $f = boc( $src, $mo ); t( 'bốc ' . trim( str_replace( array( 'private static function ', 'public static function ', '(' ), '', $mo ) ), '' !== $f ); $fs .= "\n" . $f; }
eval( 'class SAOKE_App { private static function loi( $m ) { throw new Exception( $m ); }
	public static function thu_can( $a ) { self::can_pin( $a ); return true; }
	' . $fs . ' }' );

echo "── PIN gõ tay (như cũ) ─────────────────────────────────────────────\n";
$_COOKIE = array();
t( 'PIN đúng → qua', SAOKE_App::thu_can( array( '4321' ) ) && SAOKE_App::rpc_checkPin( array( '4321' ) )['ok'] );
$loi = ''; try { SAOKE_App::thu_can( array( '0000' ) ); } catch ( Exception $e ) { $loi = $e->getMessage(); }
t( 'PIN sai → "Sai mã PIN"', 'Sai mã PIN' === $loi && ! SAOKE_App::rpc_checkPin( array( '0000' ) )['ok'] );
$loi = ''; try { SAOKE_App::thu_can( array( '' ) ); } catch ( Exception $e ) { $loi = $e->getMessage(); }
t( 'không PIN, không phiên → chối', 'Sai mã PIN' === $loi );
$OPT['saoke_pin'] = ''; $loi = ''; try { SAOKE_App::thu_can( array( '' ) ); } catch ( Exception $e ) { $loi = $e->getMessage(); }
t( 'chưa đặt PIN, không phiên → chối (không mở toang)', 'Sai mã PIN' === $loi ); $OPT['saoke_pin'] = '4321';

echo "── Phiên vé từ Ghế ─────────────────────────────────────────────────\n";
$sid = str_repeat( 'ab', 16 ); $_COOKIE = array( 'saoke_ses' => $sid ); $TRANS[ 'saoke_ses_' . hash( 'sha256', $sid ) ] = array( 'ten' => 'Kế toán Lan', 'luc' => 1 );
t( '🔴 có phiên vé, PIN rỗng → can_pin QUA (35 hàm RPC chạy được)', SAOKE_App::thu_can( array( '' ) ) );
t( '🔴 checkPin("") → ok khi có phiên (app không hiện màn PIN)', SAOKE_App::rpc_checkPin( array( '' ) )['ok'] );
$_COOKIE = array( 'saoke_ses' => 'zz' . substr( $sid, 4 ) );   // lọc ký tự lạ xong còn 28 hex → không phải mã phiên
$loi = ''; try { SAOKE_App::thu_can( array( '' ) ); } catch ( Exception $e ) { $loi = $e->getMessage(); }
t( 'cookie sai định dạng (không đủ 32 hex) → không phải phiên → chối', 'Sai mã PIN' === $loi );
$_COOKIE = array( 'saoke_ses' => str_repeat( 'cd', 16 ) );
$loi = ''; try { SAOKE_App::thu_can( array( '' ) ); } catch ( Exception $e ) { $loi = $e->getMessage(); }
t( 'cookie không có transient (hết hạn / bịa) → chối', 'Sai mã PIN' === $loi );
$_COOKIE = array( 'saoke_ses' => $sid );
$r = SAOKE_App::rpc_khoaPhien( array() );
t( '🔴 Khoá lại → xoá transient phiên; sau đó PIN rỗng bị chối', ! empty( $r['ok'] ) && ! isset( $TRANS[ 'saoke_ses_' . hash( 'sha256', $sid ) ] ) && ! SAOKE_App::rpc_checkPin( array( '' ) )['ok'] );

echo "── Nối dây ─────────────────────────────────────────────────────────\n";
t( 'getConfig cũng dùng pin_hop_le_ (trả cấu hình đầy đủ khi vào bằng vé)', false !== strpos( boc( $src, 'public static function rpc_getConfig(' ), 'self::pin_hop_le_( $pin )' ) );
t( "'khoaPhien' có trong danh sách hàm RPC", (bool) preg_match( "/'khoaPhien',/", boc( $src, 'public static function r_rpc(' ) ) );
t( '35 hàm RPC vẫn qua can_pin (không hàm nào tự so PIN riêng)', 35 === substr_count( $src, 'self::can_pin(' ) && 1 === substr_count( $src, "hash_equals( \$luu, (string) \$pin )" ) );
$app = file_get_contents( __DIR__ . '/../../vhcp-saoke/app.html' );
t( 'app: "Khoá lại" gọi khoaPhien rồi mới tải lại', (bool) preg_match( '/function khoaLai\(\)\{[\s\S]*?khoaPhien\(\)/', $app ) );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
