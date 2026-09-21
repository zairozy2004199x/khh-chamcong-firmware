<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỖI PLUGIN MỘT BỘ TỰ CẬP NHẬT RIÊNG — VÀ TẤT CẢ PHẢI CÓ MẶT Ở TRANG /it.
 *
 * ==============================================================================================
 * 🔴 LỖI 16/09/2026: HAI PLUGIN DÙNG CHUNG MỘT Ô NHỚ, VÀ NÓ CÂM HOÀN TOÀN.
 *
 *    `khh-doanh-thu` chép lớp tự cập nhật từ `vhcp-hop-dong`, đổi `TIEN_TO` mà QUÊN đổi `O_NHO`
 *    — cả hai cùng ghi đè lên transient `vhd_gh_ban_moi`. Cài chung một site thì cái nào hỏi
 *    GitHub trước sẽ ghi kết quả của MÌNH vào ô ấy, cái sau đọc trúng và tin. Bản mới của Báo
 *    cáo doanh thu không bao giờ hiện, hoặc hiện ra số bản của plugin kia.
 *
 *    Không có gì đỏ. Không có gì báo. Chỉ là nút Cập nhật đứng im mãi mãi — mà "đứng im" trông
 *    y hệt "đang là bản mới nhất". Đây là lý do bài này tồn tại: lỗi chép tệp thì mã nào cũng
 *    chạy được, chỉ có phép SO CHÉO GIỮA CÁC BỘ mới thấy.
 *
 * 🔴 VÀ ĐẾM CHỖ GỌI, KHÔNG ĐẾM CHUỖI (CLAUDE.md §6, bài học 0.18.1).
 *    Định nghĩa lớp mà quên gọi `init()` thì nó là MÃ CHẾT: không móc nào được gắn, WordPress
 *    không thấy bản mới, trang /it không thấy dòng nào. Cũng không có gì đỏ.
 *
 * Chạy: php tools/test/kiem-tu-cap-nhat-rieng-o.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
$LOI = 0; $SO = 0;
function t( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	if ( ! $ok ) { echo "  ✗ $ten\n"; $LOI++; }
}

/* Gom mọi plugin CÓ lớp tự cập nhật. Bộ nào chưa có thì không phải việc của bài này — bài chỉ
   canh những bộ đã gắn, đừng ép cả kho phải gắn rồi thành bài kêu oan. */
$bo = array();
foreach ( (array) glob( $goc . '/wordpress/*', GLOB_ONLYDIR ) as $d ) {
	$ma  = basename( $d );
	$tep = glob( $d . '/includes/*tu-cap-nhat*.php' );
	if ( ! $tep ) { continue; }
	if ( ! is_file( $d . '/' . $ma . '.php' ) ) { continue; }
	$bo[ $ma ] = array(
		'lop_tep' => $tep[0],
		'lop'     => (string) file_get_contents( $tep[0] ),
		'chinh'   => (string) file_get_contents( $d . '/' . $ma . '.php' ),
	);
}
echo '── ' . count( $bo ) . " bộ có lớp tự cập nhật ──\n";
t( 'tìm được ít nhất 5 bộ (glob hỏng thì bài này xanh giả)', count( $bo ) >= 5 );

/* 🔴 MIỄN PHẦN HỢP ĐỒNG TRANG /it — VÀ ĐÂY LÀ MỘT QUYẾT ĐỊNH CÓ GHI LẠI, KHÔNG PHẢI CHỖ SÓT.
   `vhcp-ghe` đang có HAI cây mã khác hẳn nhau trong cùng kho này: bản 1.47.0 ở đây, và bản
   2.102.0 ở nhánh `claude/posh-qr-kh1urz` — chính bản 2.102.0 mới là bản đang chạy trên site,
   và nó ĐÃ tự khai vào bảng /it. Gắn thêm cho bản ở đây nghĩa là nâng số bản và phát hành một
   gói mà không ai nên cài, trong khi việc hoà hai cây vẫn chưa làm. Nên bỏ qua phần hợp đồng,
   GIỮ phần so chéo (ô nhớ / tiền tố / tên lớp) vì những cái ấy vẫn phải riêng.
   ⚠️ Hoà xong hai cây ghế thì BỎ 'vhcp-ghe' khỏi dòng dưới, đừng để chỗ miễn này sống lâu hơn
      lý do của nó.

   🔴 BẢN VÙNG (`vhcp-chi-phi-<vùng>`) THÌ MIỄN VĨNH VIỄN, VÌ MỘT LÝ DO KHÁC HẲN.
   Chúng được tách để chạy trên site RIÊNG của vùng ấy, và luật là không mang theo MỘT CHUỖI
   `vhcp_` NÀO của bản gốc — `kiem-tach-ban-vung.php` canh từng chữ. Mà tên bộ lọc lại đúng là
   `vhcp_tu_cap_nhat_ds`. Nên khai vào bảng /it là vi phạm luật tách bản, và cũng vô nghĩa: trang
   /it nằm trong plugin Ghế, site của vùng không cài Ghế thì không có bảng nào để hiện vào.
   ⚠️ 16/09/2026 em đã gắn cho cả bốn bản vùng rồi mới bị `kiem-tach-ban-vung.php` bắt. Ghi lại
      đây để người sau khỏi đi lại đúng vòng ấy. Nhận bản vùng bằng QUY TẮC TÊN, không gõ tay
      từng cái — thêm một vùng là thêm một dòng phải nhớ sửa. */
$MIEN = array( 'vhcp-ghe' );
foreach ( array_keys( $bo ) as $_m ) {
	if ( 0 === strpos( $_m, 'vhcp-chi-phi-' ) ) { $MIEN[] = $_m; }
}

$onho = array(); $tt = array(); $lop = array();
foreach ( $bo as $ma => $b ) {
	preg_match( "/const O_NHO\s*=\s*'([a-z0-9_]+)'/", $b['lop'], $m1 );
	preg_match( "/const TIEN_TO\s*=\s*'([a-z0-9-]+)'/", $b['lop'], $m2 );
	preg_match( '/^class\s+([A-Za-z0-9_]+)\s/m', $b['lop'], $m3 );

	t( "$ma: khai được const O_NHO",   isset( $m1[1] ) );
	t( "$ma: khai được const TIEN_TO", isset( $m2[1] ) );
	t( "$ma: đọc được tên lớp",        isset( $m3[1] ) );
	if ( isset( $m1[1] ) ) { $onho[ $m1[1] ][] = $ma; }
	if ( isset( $m2[1] ) ) { $tt[ $m2[1] ][]   = $ma; }
	if ( isset( $m3[1] ) ) { $lop[ $m3[1] ][]  = $ma; }

	/* Tiền tố tag PHẢI là chính tên thư mục + "-v". Lệch một chữ là plugin không bao giờ thấy
	   bản của mình (lọc trượt hết), hoặc thấy bản của plugin khác. */
	if ( isset( $m2[1] ) ) {
		t( "$ma: TIEN_TO là '$ma-v' chứ không phải '{$m2[1]}'", $m2[1] === $ma . '-v' );
	}

	/* Hợp đồng của trang /it — thiếu hàm nào là bảng vẽ ra dòng trống hoặc nút bấm không ăn. */
	if ( ! in_array( $ma, $MIEN, true ) ) {
		foreach ( array( 'ban_moi', 'ban_moi_nho', 'quen_nho', 'khai_ds', 'duong' ) as $ham ) {
			t( "$ma: có hàm $ham()", false !== strpos( $b['lop'], 'function ' . $ham . '(' ) );
		}
		t( "$ma: khai vào bộ lọc vhcp_tu_cap_nhat_ds",
			false !== strpos( $b['lop'], "add_filter( 'vhcp_tu_cap_nhat_ds'" ) );

		/* 🔴 ban_moi_nho() KHÔNG được gọi mạng — trang /it gọi nó ở mỗi lượt tải trang. */
		if ( preg_match( '/function ban_moi_nho\(\).*?\n\t\}/s', $b['lop'], $mn ) ) {
			t( "$ma: ban_moi_nho() không gọi mạng", false === strpos( $mn[0], 'wp_remote_' ) );
		} else {
			t( "$ma: đọc được thân ban_moi_nho()", false );
		}
	}

	/* Mã chết không bao giờ đỏ — chỉ phép đếm CHỖ GỌI mới thấy. */
	if ( isset( $m3[1] ) ) {
		$goi = preg_match_all( '/' . preg_quote( $m3[1], '/' ) . '::init\(\)/', $b['chinh'] );
		t( "$ma: {$m3[1]}::init() được gọi đúng 1 chỗ trong tệp chính — đang có $goi", 1 === $goi );
	}
}

echo "── So chéo giữa các bộ ──\n";
foreach ( array( 'ô nhớ O_NHO' => $onho, 'tiền tố tag TIEN_TO' => $tt, 'tên lớp' => $lop ) as $nhan => $bang ) {
	foreach ( $bang as $gia => $ai ) {
		t( "🔴 $nhan '$gia' bị " . count( $ai ) . ' bộ dùng chung: ' . implode( ', ', $ai ), 1 === count( $ai ) );
	}
}

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * O_KHOA THÌ NGƯỢC LẠI VỚI O_NHO — VÀ CÓ ĐÚNG MỘT NGOẠI LỆ.
 *
 * Plugin sống chung MỘT site thì chung một ô khoá GitHub: khai một lần là đủ cho cả nhà. Ghim
 * lại để không ai "sửa cho đồng bộ" với O_NHO rồi bắt anh Thắng dán khoá mười ba lần.
 *
 * 🔴 TRỪ BẢN VÙNG (`vhcp-chi-phi-<vùng>`). Chúng được tách để chạy trên site RIÊNG của vùng
 *    ấy, và luật là không dùng chung một chuỗi nào với bản gốc — kể cả ô khoá.
 *    `kiem-tach-ban-vung.php` và `kiem-tu-cap-nhat.php` canh chiều ngược lại.
 *
 * ⚠️ 16/09/2026 bản đầu của bài này khẳng định "mọi bộ dùng chung một ô khoá", và em tin nó đủ
 *    để đi đổi ô khoá của bốn bản vùng về ô chung — tưởng là vá một lỗi chép tệp. Ba bài kiểm
 *    có sẵn đỏ lên mới biết mình vừa phá một ngoại lệ bắt buộc. Một phép kiểm phát biểu sai
 *    luật thì nguy hơn không có phép nào: nó cho người sau một cái cớ để phá đúng chỗ.
 *
 * ⚠️ Nhận bản vùng bằng QUY TẮC TÊN, không bằng danh sách gõ tay — thêm một vùng là thêm một
 *    dòng phải nhớ sửa, và lần quên nào cũng im lặng.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
$rieng = array(); $vung_chung = array();
foreach ( $bo as $ma => $b ) {
	if ( ! preg_match( "/const O_KHOA\s*=\s*'([a-z0-9_]+)'/", $b['lop'], $m ) ) { continue; }
	$la_vung = ( 0 === strpos( $ma, 'vhcp-chi-phi-' ) );
	if ( $la_vung ) {
		if ( 'vhcp_gh_token' === $m[1] ) { $vung_chung[] = $ma; }
	} elseif ( 'vhcp_gh_token' !== $m[1] ) {
		$rieng[] = $ma . ' (' . $m[1] . ')';
	}
}
t( '🔴 bộ thường dùng CHUNG ô khoá vhcp_gh_token: ' . implode( ', ', $rieng ), ! $rieng );
t( '🔴 bản vùng phải có ô khoá RIÊNG, đang chung: ' . implode( ', ', $vung_chung ), ! $vung_chung );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng\n" : "SẠCH: $SO phép\n" );
exit( $LOI ? 1 : 0 );
