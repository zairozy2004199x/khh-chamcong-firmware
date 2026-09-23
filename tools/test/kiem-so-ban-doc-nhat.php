<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỘT SỐ PHIÊN BẢN CHỈ ĐƯỢC DÙNG MỘT LẦN — KỂ CẢ Ở NHÁNH KHÁC.
 *
 * ==============================================================================================
 * 🔴 LỖI NGÀY 15/09/2026, MẤT HAI VÒNG CỦA ANH THẮNG MỚI LẦN RA.
 *
 *    Anh cài bản Chấm công em gửi, WordPress báo *"Bạn đang tải lên một phiên bản cũ của plugin
 *    hiện tại"*: site chạy 3.85.0, bản em gửi ghi 3.83.0. Hôm trước cũng đúng câu ấy với plugin
 *    Ghế (site 2.81.0, bản em gửi 1.47.0).
 *
 *    Không bản nào cũ cả. Hai nhánh cùng làm một plugin song song và CÙNG ĐÁNH 3.81 · 3.82 ·
 *    3.83 cho ba việc khác hẳn nhau. WordPress chỉ so được CON SỐ, nên nó nói "cũ hơn" — một
 *    câu đúng về số mà sai về việc. Cài bản nào đè bản nào cũng mất việc của nhánh kia, và
 *    KHÔNG CÓ GÌ BÁO ngoài một dòng chữ đỏ dễ bấm qua.
 *
 * 🔴 NÊN CHỖ PHẢI CANH KHÔNG PHẢI CÂY MÃ NÀY — LÀ MỌI NHÁNH TRÊN `origin`.
 *    Mọi bài kiểm khác trong thư mục này chỉ soi cây mã đang mở. Bài này là bài DUY NHẤT hỏi
 *    sang nhánh khác, và đó chính là lý do nó tồn tại: lỗi trên không để lại dấu vết nào trong
 *    cây mã của một nhánh.
 *
 * ⚠️ KHÔNG CÓ `origin` (máy CI dựng nông, hay bản sao rời) thì BỎ QUA, không đỏ. Một bài kiểm
 *    đỏ vì môi trường là bài người ta tắt đi — và tắt rồi thì lần đụng số sau không ai bắt.
 *
 * Chạy: php tools/test/kiem-so-ban-doc-nhat.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = '' ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( '' === $them ? '' : "\n      → " . $them );
}

/** Chạy git, trả mảng dòng. Lỗi -> mảng rỗng. */
function git_ra( $goc, $lenh ) {
	$ra = array();
	$mã = 0;
	exec( 'cd ' . escapeshellarg( $goc ) . ' && ' . $lenh . ' 2>/dev/null', $ra, $mã );
	return 0 === $mã ? $ra : array();
}

$nhanh = git_ra( $goc, 'git branch -r --format="%(refname:short)"' );
$nhanh = array_values( array_filter( $nhanh, function ( $b ) {
	return '' !== trim( $b ) && false === strpos( $b, 'HEAD' );
} ) );

if ( ! $nhanh ) {
	echo "\n⚠️ BỎ QUA — không thấy nhánh `origin` nào (bản sao rời hoặc CI dựng nông).\n";
	exit( 0 );
}

/* Các plugin phải canh: thư mục nào có tệp cùng tên mang `Version:` ở đầu. */
$plugin = array();
foreach ( glob( $goc . '/wordpress/*', GLOB_ONLYDIR ) as $d ) {
	$ten = basename( $d );
	if ( is_file( $d . '/' . $ten . '.php' ) ) { $plugin[] = $ten; }
}
t( 'dò được danh sách plugin', count( $plugin ) >= 5, count( $plugin ) . ' bộ' );

/** Số bản của một plugin trên một nhánh. '' nếu nhánh ấy không có tệp. */
function so_ban( $goc, $nhanh, $ten ) {
	$ra = git_ra( $goc, 'git show ' . escapeshellarg( $nhanh . ':wordpress/' . $ten . '/' . $ten . '.php' ) );
	foreach ( $ra as $d ) {
		if ( preg_match( '/^\s*\*?\s*Version:\s*(\S+)/', $d, $m ) ) { return $m[1]; }
	}
	return '';
}

$nhanh_toi = trim( implode( '', git_ra( $goc, 'git rev-parse --abbrev-ref HEAD' ) ) );

foreach ( $plugin as $ten ) {
	$cua_toi = '';
	$tep = $goc . '/wordpress/' . $ten . '/' . $ten . '.php';
	if ( preg_match( '/^\s*\*?\s*Version:\s*(\S+)/m', (string) file_get_contents( $tep ), $m ) ) {
		$cua_toi = $m[1];
	}
	if ( '' === $cua_toi ) { continue; }

	/* ═══ 🔴 SỐ CỦA MÌNH PHẢI CAO NHẤT, hoặc ít ra không TRÙNG số của nhánh khác ═══
	 *
	 * Trùng số = hai việc khác nhau mang một tên. Đó đúng là ca 15/09/2026.
	 * Thấp hơn  = anh Thắng cài vào sẽ thấy "phiên bản cũ", rồi hoặc bỏ cài (mất việc của
	 *             nhánh này), hoặc cài đè (mất việc của nhánh kia). Cả hai đều xấu. */
	/* ⚠️ TRÙNG SỐ MÀ NỘI DUNG Y HỆT LÀ BÌNH THƯỜNG — đừng kêu. Nhánh nào cũng mọc từ một
	   gốc chung; bộ nào chưa ai đụng tới thì mọi nhánh cùng mang một số, và đó đúng là
	   "cùng một việc". Bản nháp của bài này kêu cả những ca ấy: hai bộ trên bảy bộ bị báo
	   oan ngay lượt chạy đầu. Một bài kiểm kêu oan là bài người ta tắt đi.
	   Cái phải kêu là TRÙNG SỐ MÀ KHÁC NỘI DUNG — dò bằng mã băm cây thư mục của bộ ấy. */
	$cay_toi = trim( implode( '', git_ra( $goc, 'git rev-parse '
		. escapeshellarg( 'HEAD:wordpress/' . $ten ) ) ) );
	$dung  = array();
	$tren  = array();
	foreach ( $nhanh as $b ) {
		if ( 'origin/' . $nhanh_toi === $b ) { continue; }
		$v = so_ban( $goc, $b, $ten );
		if ( '' === $v ) { continue; }
		if ( $v === $cua_toi ) {
			$cay_kia = trim( implode( '', git_ra( $goc, 'git rev-parse '
				. escapeshellarg( $b . ':wordpress/' . $ten ) ) ) );
			/* Không đọc được cây bên kia thì coi như khác — thà hỏi thừa còn hơn bỏ sót. */
			if ( '' === $cay_toi || $cay_kia !== $cay_toi ) { $dung[] = $b . ' (' . $v . ')'; }
		} elseif ( version_compare( $v, $cua_toi, '>' ) ) {
			$tren[] = $b . ' (' . $v . ')';
		}
	}
	t( '🔴 ' . $ten . ' ' . $cua_toi . ': không nhánh nào dùng TRÙNG số này',
		! $dung, implode( ' · ', $dung ) . ' — hai việc khác nhau mang một tên; nâng số lên rồi đẩy lại' );
	t( '🔴 ' . $ten . ' ' . $cua_toi . ': không nhánh nào đã đi CAO HƠN',
		! $tren, implode( ' · ', $tren ) . ' — hoà nhánh ấy vào rồi đánh số trên nó, '
			. 'không thì cài vào site là mất việc của một trong hai bên' );
}

/* ═══════════════════════════════════════════════════════════════════════════════
 * Khối báo trượt đứng CUỐI CÙNG — thêm mục mới thì thêm Ở TRÊN chỗ này.
 * ═══════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	echo "\n  Dò tay: for b in \$(git branch -r | sed 's| *origin/||' | grep -v HEAD); do\n"
		. "            echo -n \"\$b \"; git show origin/\$b:wordpress/<bộ>/<bộ>.php \\\n"
		. "              2>/dev/null | grep -m1 'Version:'; done\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: không số phiên bản nào bị hai nhánh dùng chung.\n";
