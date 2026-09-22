<?php
/**
 * SINH BIỂU TƯỢNG APP CHO TRANG CHI PHÍ.
 *
 * Chạy: php tools/tao-bieu-tuong-app.php
 *
 * =================================================================================================
 * 🔴 VÌ SAO SINH BẰNG MÃ, KHÔNG NHÉT MỘT TẤM PNG VÀO KHO
 * =================================================================================================
 * Một tấm ảnh nhị phân trong kho là thứ không ai đọc được diff: đổi màu nhà một cái thì phải có
 * người mở phần mềm vẽ ra sửa rồi nhớ xuất đủ bốn cỡ. Sinh bằng mã thì màu và hình nằm trong
 * đúng tệp này, sửa một dòng rồi chạy lại là đủ bốn tấm cùng đổi.
 *
 * ⚠️ BỐN TẤM, KHÔNG PHẢI MỘT.
 *   · 180 — iPhone đọc `apple-touch-icon`, và nó KHÔNG đọc `icons` trong manifest.json.
 *   · 192 · 512 — Android / Chrome, `purpose: any`.
 *   · 512 maskable — Android cắt theo hình của máy (tròn, vuông bo, giọt nước). Tấm này phải
 *     chừa lề rộng (vùng an toàn là hình tròn đường kính 80% cạnh), nên KHÔNG dùng chung với
 *     tấm `any`: một tấm không thể vừa chừa lề rộng vừa kín khung.
 */

$DICH = dirname( __DIR__ ) . '/wordpress/vhcp-chi-phi/assets/img/app';
if ( ! is_dir( $DICH ) && ! mkdir( $DICH, 0755, true ) ) {
	fwrite( STDERR, "Không tạo được $DICH\n" );
	exit( 1 );
}

/* Bộ áo: xanh của nhà (cùng mã với chữ nhấn trong app.html) trên nền trắng ngà. */
const MAU_NEN  = array( 0x25, 0x45, 0xFF );
const MAU_GIAY = array( 0xFF, 0xFF, 0xFF );
const MAU_MUC  = array( 0x25, 0x45, 0xFF );
const MAU_VANG = array( 0xFF, 0xB3, 0x00 );

/**
 * Vẽ một tấm.
 *
 * @param int   $canh Cạnh ảnh.
 * @param float $le   Tỉ lệ lề chừa quanh hình (0.12 cho tấm thường, 0.20 cho maskable).
 * @param bool  $bo   Bo góc nền hay để vuông kín (maskable phải kín — máy tự cắt).
 */
function ve( $canh, $le, $bo ) {
	$im = imagecreatetruecolor( $canh, $canh );
	imagealphablending( $im, true );
	imagesavealpha( $im, true );

	$nen  = imagecolorallocate( $im, MAU_NEN[0], MAU_NEN[1], MAU_NEN[2] );
	$giay = imagecolorallocate( $im, MAU_GIAY[0], MAU_GIAY[1], MAU_GIAY[2] );
	$muc  = imagecolorallocate( $im, MAU_MUC[0], MAU_MUC[1], MAU_MUC[2] );
	$vang = imagecolorallocate( $im, MAU_VANG[0], MAU_VANG[1], MAU_VANG[2] );

	imagefilledrectangle( $im, 0, 0, $canh - 1, $canh - 1, $nen );

	/* Góc bo cho tấm THƯỜNG. Tấm maskable để vuông kín: máy tự cắt theo hình của nó, và một
	   nền đã bo sẵn thì sau khi cắt lòi ra bốn góc trong suốt. */
	if ( $bo ) {
		$r = (int) round( $canh * 0.22 );
		$trong = imagecolorallocatealpha( $im, 0, 0, 0, 127 );
		/* 🔴 TẮT TRỘN MÀU TRƯỚC KHI XOÁ GÓC. Bật trộn thì đặt một điểm TRONG SUỐT lên nền đục
		   chỉ là "trộn 0% màu mới" — nền giữ nguyên, và bốn góc vẫn vuông chằn chặn. Không lỗi,
		   không cảnh báo, chỉ là cái bo góc không bao giờ xuất hiện. */
		imagealphablending( $im, false );
		for ( $y = 0; $y < $canh; $y++ ) {
			for ( $x = 0; $x < $canh; $x++ ) {
				$gx = ( $x < $r ) ? $r - $x : ( ( $x >= $canh - $r ) ? $x - ( $canh - $r - 1 ) : 0 );
				$gy = ( $y < $r ) ? $r - $y : ( ( $y >= $canh - $r ) ? $y - ( $canh - $r - 1 ) : 0 );
				if ( $gx && $gy && ( $gx * $gx + $gy * $gy ) > $r * $r ) {
					imagesetpixel( $im, $x, $y, $trong );
				}
			}
		}
		imagealphablending( $im, true );   // trả lại cho mấy hình vẽ bên dưới
	}

	/* ── TỜ HOÁ ĐƠN ───────────────────────────────────────────────────────────────────────
	   Hình chọn theo việc, không theo gu: một tờ phiếu chi với mấy dòng chữ và một dải vàng
	   ở dưới (số tiền). Nhìn cỡ 40px trên màn hình chính vẫn ra "giấy tờ tiền nong", trong khi
	   một chữ cái lồng nhau thì cỡ ấy chỉ còn là một vệt. */
	$w  = (int) round( $canh * ( 1 - 2 * $le ) * 0.78 );
	$h  = (int) round( $canh * ( 1 - 2 * $le ) );
	$x0 = (int) round( ( $canh - $w ) / 2 );
	$y0 = (int) round( ( $canh - $h ) / 2 );

	imagefilledrectangle( $im, $x0, $y0, $x0 + $w, $y0 + $h, $giay );

	/* Răng cưa chân tờ phiếu — dấu hiệu "biên lai" ai cũng đọc ra. */
	$rang = max( 3, (int) round( $w / 7 ) );
	for ( $i = 0; $i * $rang < $w; $i++ ) {
		$xa = $x0 + $i * $rang;
		$xb = min( $x0 + $w, $xa + $rang );
		imagefilledpolygon( $im, array(
			$xa, $y0 + $h,
			$xb, $y0 + $h,
			(int) round( ( $xa + $xb ) / 2 ), $y0 + $h + (int) round( $rang * 0.55 ),
		), 3, $giay );
	}

	/* Ba dòng "chữ" + một dải vàng "số tiền". */
	$lx = $x0 + (int) round( $w * 0.14 );
	$lw = (int) round( $w * 0.72 );
	$lh = max( 2, (int) round( $h * 0.058 ) );
	$ly = $y0 + (int) round( $h * 0.20 );
	$b  = (int) round( $h * 0.145 );
	foreach ( array( 1.0, 0.72, 0.86 ) as $i => $ti ) {
		$yy = $ly + $i * $b;
		imagefilledrectangle( $im, $lx, $yy, $lx + (int) round( $lw * $ti ), $yy + $lh, $muc );
	}
	$yy = $ly + 3 * $b + (int) round( $h * 0.03 );
	imagefilledrectangle( $im, $lx, $yy, $lx + (int) round( $lw * 0.60 ), $yy + (int) round( $lh * 2.1 ), $vang );

	return $im;
}

$ra = array(
	'bieu-tuong-180.png'          => array( 180, 0.13, true ),
	'bieu-tuong-192.png'          => array( 192, 0.13, true ),
	'bieu-tuong-512.png'          => array( 512, 0.13, true ),
	'bieu-tuong-512-maskable.png' => array( 512, 0.21, false ),
);
foreach ( $ra as $ten => $ts ) {
	$im = ve( $ts[0], $ts[1], $ts[2] );
	imagepng( $im, $DICH . '/' . $ten, 9 );
	imagedestroy( $im );
	echo "✓ $ten (" . $ts[0] . "px)\n";
}
echo "Xong — " . $DICH . "\n";
