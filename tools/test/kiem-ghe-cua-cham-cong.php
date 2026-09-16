<?php
/**
 * CỬA CHẤM CÔNG PHẢI GÁC MỌI ĐƯỜNG VÀO MÀN BÁO CÁO.
 *
 * ==============================================================================================
 * 🔴 LỖI 16/09/2026 — LUẬT CÓ HAI ĐƯỜNG VÀO, CHỈ MỘT ĐƯỜNG ĐƯỢC VÁ.
 *
 *    Cửa "chưa chấm công hôm nay thì chưa cho vào báo cáo" thêm ngày 15/09, gắn ở đường PIN
 *    (`thu()` → `bc_boot`). Sót đường thứ hai: `moBaoCaoTuDuLieu()` — đường mà nhân viên mở báo
 *    cáo từ SPA /ghe, không phải gõ PIN.
 *
 *    Vì sao nó CÂM: `boot()` trả cho ca chưa chấm công một phản hồi rất ngắn — ok + pinOk +
 *    chuaChamCong, KHÔNG có `coso`, KHÔNG có `banBc`. Đường sót không hỏi gì cứ thế `veChinh()`,
 *    nên màn hiện "phạm vi 0 cơ sở · mã báo cáo ?" — TRÔNG Y HỆT lỗi opcache đã mất cả buổi
 *    chiều 15/09 để lần ra. Và khối chẩn đoán còn kết luận "mã cũ / bộ đệm", cử người đi xoá
 *    opcache cho một lỗi nằm ở chỗ khác hẳn.
 *
 * 🔴 NÊN BÀI NÀY ĐẾM CHỖ GỌI, KHÔNG ĐẾM CHUỖI (CLAUDE.md §6, bài học 0.18.1). Đúng cùng một
 *    hình dạng: luật đúng, một bản sao không được vá, bộ thử vẫn xanh, màn hình vẫn sai.
 *
 * Chạy: php tools/test/kiem-ghe-cua-cham-cong.php   (chay-het.sh tự gom)
 */
$goc  = dirname( __DIR__, 2 );
$js   = (string) file_get_contents( $goc . '/vhcp-ghe/includes/class-vhg-trang.php' );
$bc   = (string) file_get_contents( $goc . '/vhcp-ghe/includes/class-vhg-baocao.php' );
$LOI = 0; $SO = 0;
function t( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . "\n";
	if ( ! $ok ) { $LOI++; }
}

echo "── Máy chủ ──\n";
t( 'boot() còn nhánh trả về sớm cho ca chưa chấm công',
	false !== strpos( $bc, "'chuaChamCong' => 1," ) );
/* Nhánh ấy KHÔNG kèm banBc — đó chính là thứ làm màn hình nói dối. Ghim lại để ai thêm banBc
   vào đó thì phải đọc bài này trước và hiểu vì sao màn hình từng đổ oan cho opcache. */
if ( preg_match( '/public static function boot\(.*?\n\t\}/s', $bc, $mb ) ) {
	$than = $mb[0];
	$vi_cc = strpos( $than, "'chuaChamCong' => 1," );
	$vi_bb = strpos( $than, "'banBc' => self::BAN" );
	t( 'boot(): nhánh chưa-chấm-công nằm TRƯỚC chỗ gắn banBc (nên phản hồi ấy thiếu vân tay)',
		false !== $vi_cc && false !== $vi_bb && $vi_cc < $vi_bb );
} else {
	t( 'đọc được thân boot()', false );
}

echo "── Mọi đường vào màn báo cáo ──\n";
/* Đường vào = chỗ gán `BC=` rồi gọi `veChinh()`. Mỗi chỗ như thế phải có một cửa chuaChamCong
   đứng trước. Đếm theo CHỖ GỌI: veChinh() chỉ được gọi từ những chỗ đã qua cửa. */
$cua = preg_match_all( '/\.chuaChamCong/', $js );
t( "cửa chuaChamCong xuất hiện đúng 3 chỗ (PIN · nút 'đã chấm công' · đường token) — đang có $cua",
	3 === $cua );

foreach ( array(
	'đường PIN (thu())'                 => "if(r.chuaChamCong){ veChuaChamCong(v, r); return; }",
	'đường token (moBaoCaoTuDuLieu())'  => "if (r.chuaChamCong) {",
) as $ten => $dau ) {
	t( "$ten có cửa", false !== strpos( $js, $dau ) );
}

/* 🔴 Đường token phải gác TRƯỚC khi vẽ màn chính, không phải sau. */
$vi_cua = strpos( $js, 'if (r.chuaChamCong) {' );
$vi_ve  = strpos( $js, "PIN=r.pin||''; BC=r; NGAY=r.today||''; LOC='';" );
t( 'đường token: cửa đứng TRƯỚC chỗ gán BC và veChinh()',
	false !== $vi_cua && false !== $vi_ve && $vi_cua < $vi_ve );

echo "── Khối chẩn đoán không được đổ oan ──\n";
/* Có banBc = mã đang chạy đúng là bản này. Kết luận "opcache giữ tệp cũ" lúc ấy là sai, và nó
   cử người đi làm một việc không ăn thua — 16/09/2026 đã mất một vòng vì thế. */
t( 'chỉ kết luận "mã cũ" khi THIẾU banBc', false !== strpos( $js, 'var maCu=!BC.banBc;' ) );
t( 'có nhánh nói thẳng "KHÔNG phải lỗi opcache" khi vân tay vẫn đúng',
	false !== strpos( $js, 'KHÔNG phải lỗi opcache' ) );
t( 'nhánh ấy dặn ĐỪNG xoá opcache', false !== strpos( $js, 'ĐỪNG xoá opcache' ) );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
