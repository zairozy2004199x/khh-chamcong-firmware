<?php
/**
 * KHỐI CỦA MỘT BẢN PHẢI CÓ MẶT TRONG BỘ TAB CỦA CHÍNH BẢN ẤY
 * =============================================================================================
 *
 * 🔴 CẮN THẬT 22/09/2026. Anh Thắng: *"Cứ F5 đơn là trang nó mất không lưu"* — mở app ra thấy
 *    "Chưa có đơn nào", mọi badge khối đều 0.
 *
 *    KHÔNG PHẢI LỖI LƯU. Đơn ghi xuống sổ đủ cả. `create_don()` đóng dấu
 *    `khoi = VHCP_DB::khoi()`, mà bản Hà Nội khai `const KHOI = 'hn'` trong khi bộ khối của nó
 *    chép y bản gốc nên chỉ có kvc · mtd · vp. Đơn mang một mã khối KHÔNG TAB NÀO BÀY, tức
 *    biến mất khỏi mọi màn. Đúng y câu cảnh báo nằm sẵn trong `create_don()`:
 *    *"Đơn không mang dấu mảng là đơn KHÔNG TAB NÀO THẤY — tiền có thật mà mở app ra như chưa
 *    từng tồn tại."*
 *
 * 🔴 VÌ SAO BA BẢN KIA KHÔNG DÍNH — VÀ VÌ SAO ĐÓ LÀ MAY, KHÔNG PHẢI ĐÚNG. Mã vùng của chúng
 *    ('kvc', 'mtd', 'vp') TÌNH CỜ đã nằm sẵn trong bộ khối của bản gốc. Mọi vùng có mã mới —
 *    hn, dn, hcm… — đều dính y như nhau. Nên bài này soi THEO LUẬT, không kê tên từng bản:
 *    quét mọi thư mục `vhcp-chi-phi*`, bản nào cũng phải tự nhất quán.
 *
 * ⚠️ KHÔNG CHỮA BẰNG CÁCH ĐỔI `KHOI` VỀ 'kvc'. Cột ấy cố ý mang mã vùng: tới bước dời dữ liệu
 *    về một kho, nó là thứ duy nhất tách được đơn của bên nào.
 *
 * Chạy: php tools/test/kiem-khoi-ban-vung.php
 */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) );
}

$goc = dirname( dirname( __DIR__ ) );
$ds  = glob( $goc . '/wordpress/vhcp-chi-phi*', GLOB_ONLYDIR );
t( 'tìm thấy các bản chi phí để soi', count( $ds ) >= 2, count( $ds ) );

foreach ( $ds as $dir ) {
	$ban = basename( $dir );
	$fdb = $dir . '/includes/class-vhcp-db.php';
	$fdv = $dir . '/includes/class-vhcp-donvi.php';
	$fcf = $dir . '/includes/class-vhcp-cfg.php';
	/* Bản `vhcp-chi-phi-tong` là bản gộp, không có đường tạo đơn — bỏ qua chứ đừng báo đỏ. */
	if ( ! is_file( $fdb ) || ! is_file( $fdv ) || ! is_file( $fcf ) ) { continue; }

	$db = file_get_contents( $fdb );
	if ( ! preg_match( "/const KHOI = '([^']*)';/", $db, $m ) ) {
		t( "$ban: đọc được `const KHOI`", false, substr( $db, 0, 200 ) );
		continue;
	}
	$khoi = $m[1];
	t( "$ban: khối của bản không rỗng — rỗng là mọi đơn mất dấu", '' !== $khoi, $khoi );

	/* 1. 🔴 Mã khối phải có TAB. `khoi_xem_duoc()` / bộ nút đều đi qua bảng này. */
	$dv = file_get_contents( $fdv );
	$co_tab = 1 === preg_match( "/const KHOI_THEO_DON_VI = array\(([^;]*?)\);/s", $dv, $mm )
		&& false !== strpos( $mm[1], "'" . $khoi . "'" );
	t( "🔴 $ban: khối '$khoi' CÓ MẶT trong KHOI_THEO_DON_VI — thiếu là đơn không tab nào thấy",
		$co_tab, isset( $mm[1] ) ? trim( $mm[1] ) : '(không đọc được bảng)' );

	/* 2. Và phải có NHÃN, không thì tab hiện trơ mã. */
	$cf = file_get_contents( $fcf );
	$co_nhan = 1 === preg_match( "/\\\$m = array\( (.*?) \);/", $cf, $mn )
		&& false !== strpos( $mn[1], "'" . $khoi . "' =>" );
	t( "$ban: khối '$khoi' có nhãn trong `ten_khoi()` — thiếu thì tab hiện trơ mã", $co_nhan,
		isset( $mn[1] ) ? $mn[1] : '(không đọc được bảng nhãn)' );

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * 3. 🔴 MÀN CŨNG PHẢI BIẾT KHỐI ẤY — CHỖ BÀI NÀY BỎ SÓT LẦN TRƯỚC
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 * Bản 1.264.0 vá đúng bệnh này nhưng chỉ NỬA ĐƯỜNG: chèn mã vùng vào bộ khối ở MÁY CHỦ,
	 * còn MÀN có một danh sách RIÊNG gõ cứng `[kvc, mtd, vp]`. Bài kiểm lúc ấy chỉ soi máy
	 * chủ nên XANH — và anh Thắng vẫn mất đơn y như cũ: *"Vẫn mất đơn khi tạo hoặc F5"*.
	 *
	 * ⚠️ BÀI KIỂM CHỈ SOI MỘT ĐẦU CỦA MỘT ĐƯỜNG DÂY THÌ NÓ CANH ĐƯỢC NỬA ĐƯỜNG DÂY. Đường này
	 *    có bốn chặng — cột trong sổ, bộ khối máy chủ, gói khởi động, danh sách bên màn — và
	 *    đứt chặng nào cũng ra đúng một cảnh: đơn không tab nào bày.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	$app = $dir . '/templates/app.html';
	$don = $dir . '/includes/class-vhcp-don.php';
	if ( is_file( $app ) && is_file( $don ) ) {
		$ha = file_get_contents( $app );
		$hd = file_get_contents( $don );
		t( "🔴 $ban: gói khởi động CHỞ danh sách khối xuống màn",
			false !== strpos( $hd, "'khoiDs'" ), null );
		/* ⚠️ VÀ HÀM DỰNG NÓ PHẢI THẬT SỰ TRẢ RA GÌ ĐÓ. Grep mỗi tên khoá thì một hàm trả mảng
		   RỖNG vẫn qua — màn nhận `[]`, rơi về đường lui, và bản vùng mất tab y như cũ. */
		$hdv = file_get_contents( $dir . '/includes/class-vhcp-donvi.php' );
		t( "🔴 $ban: `khoi_ds()` dựng từ bộ khối thật, không trả mảng rỗng",
			false !== strpos( $hdv, "\$ra[] = array( 'ma' => \$ma" )
			&& false !== strpos( $hdv, 'array_keys( self::KHOI_THEO_DON_VI )' ), null );
		t( "🔴 $ban: màn LẤY danh sách khối từ máy chủ, KHÔNG gõ cứng",
			false !== strpos( $ha, 'BOOT.khoiDs' ), null );
		/* ⚠️ DANH SÁCH GÕ CỨNG VẪN CÒN, VÀ CỐ Ý — nó là ĐƯỜNG LUI cho gói khởi động của bản
		   cũ (không có khoá `khoiDs`); trả mảng rỗng lúc ấy là thanh KHỐI trắng trơn, tệ hơn
		   hẳn thiếu một tab. Cái phải canh không phải "có gõ cứng hay không", mà là CÓ GHI ĐÈ
		   BẰNG DANH SÁCH CỦA MÁY CHỦ HAY KHÔNG — bệnh cũ là màn bám mãi vào bản gõ cứng. */
		t( "🔴 $ban: có hàm nạp danh sách khối từ máy chủ và GHI ĐÈ bản gõ cứng",
			false !== strpos( $ha, 'function _napKhoiDs(' )
			&& false !== strpos( $ha, 'KHOI_DS=(ds&&ds.length)?ds:KHOI_DS_LUI' ), null );
		t( "$ban: và VẪN giữ đường lui ba khối cũ cho gói khởi động bản cũ",
			false !== strpos( $ha, 'KHOI_DS_LUI' ), null );
		/* Khối của chính bản này phải NHẬN VIỆC MỚI, không thì đơn vừa lập đã nằm ở tab lưu trữ. */
		t( "🔴 $ban: khối của chính bản này được thêm vào KHOI_MO",
			false !== strpos( $ha, 'KHOI_MO.indexOf(ban)<0' ), null );
	}

	/* 4. ⚠️ VÀ KHÔNG KHAI TRÙNG KHOÁ. Mảng PHP khai hai lần một khoá thì lấy cái sau — nhãn
	      vẫn ra đúng, tức hỏng mà không kêu, chỉ lộ khi ai đó đổi thứ tự chèn. */
	if ( $co_nhan ) {
		preg_match_all( "/'([a-z0-9_]+)' =>/", $mn[1], $mk );
		t( "🔴 $ban: bảng nhãn khối KHÔNG khai trùng khoá",
			count( $mk[1] ) === count( array_unique( $mk[1] ) ), $mn[1] );
	}
}

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "✓ SẠCH — $DAT phép: bản nào cũng có tab cho khối của chính nó, đơn không rơi vào chỗ không ai thấy.\n";
