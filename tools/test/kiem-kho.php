<?php
/**
 * SỔ KHO HÀNG HOÁ: HAI CON SỐ ĐỘC LẬP, VÀ CÁI LỆCH PHẢI CHỈ RA ĐƯỢC.
 *
 * 20/09/2026 anh Thắng chốt: nhân viên khai cuối ngày *"còn bao nhiêu bán bao nhiêu"*, hệ
 * *"đối chiếu với dữ liệu fabi"*, *"fabi phát sinh món hệ thống tự tách thêm ô nhập"*, và
 * *"những món nằm trong combo cũng tự hiểu tách ra số lượng tồn kho"*.
 *
 * Năm chỗ bài này khoá — đều là chuyện sổ vẫn xanh mướt mà số thì sai:
 *
 *   1. 🔴 TỒN TÍNH PHẢI LẤY SỐ MÁY, KHÔNG LẤY SỐ NHÂN VIÊN KHAI. Lấy số khai thì người khai
 *      thiếu bao nhiêu, tồn tính cũng thừa bấy nhiêu — hai vế tự triệt tiêu, `lệch kho` luôn
 *      bằng 0 dù hàng đã mất. Cả sổ xanh và vô dụng.
 *   2. 🔴 MÓN TRONG COMBO PHẢI TRỪ KHO THEO THÀNH PHẦN. FABi ghi doanh thu vào tên combo, nên
 *      không tách thì chai nước trong combo mãi mãi "chưa bán", tồn tính thừa dần.
 *   3. 🔴 ĐẾM TAY LÀ MỐC MỚI, CẮT ĐỨT CÁI LỆCH CŨ. Không thế thì một ngày lệch theo mãi về
 *      sau, mọi ngày sau đều đỏ vì một lỗi đã xử lý xong từ lâu.
 *   4. 🔴 CHƯA KHAI (null) KHÁC HẲN KHAI SỐ 0. Trộn hai thứ là ô bỏ trống trông y như ô đã
 *      đếm và đếm đúng.
 *   5. 🔴 MÓN MỚI CỦA FABi TỰ CÓ Ô NHẬP, và món hết bán vẫn ở lại sổ chừng nào còn tồn.
 *
 * Chạy: php tools/test/kiem-kho.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
require_once $goc . '/kho.php';

$dat = 0; $hong = array();
function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) { $dat++; } else { $hong[] = $ten; }
}

global $wpdb;
if ( ! function_exists( 'khh_dt_bang' ) ) {
	function khh_dt_bang() {
		global $wpdb;
		return $wpdb->prefix . 'khh_dt';
	}
}

function dung_bang() {
	global $wpdb;
	foreach ( array( khh_dt_bang(), khh_dt_bang_kho(), khh_dt_bang_kho_su() ) as $b ) {
		$wpdb->exec_raw( "DROP TABLE IF EXISTS $b" );
	}
	/* Sổ ghi động: CỐ Ý không có UNIQUE — nhiều dòng cùng (ngày, cơ sở, mặt hàng) là lịch sử sửa. */
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang_kho_su() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
			ngay TEXT NOT NULL, co_so TEXT NOT NULL DEFAULT '', mat_hang TEXT NOT NULL DEFAULT '',
			nhap REAL NOT NULL DEFAULT 0, ban_khai REAL NULL DEFAULT NULL,
			combo_tay REAL NOT NULL DEFAULT 0, dem REAL NULL DEFAULT NULL,
			ghi_chu TEXT DEFAULT '', nguoi TEXT DEFAULT '', luc TEXT NULL )"
	);
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
			ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', mon TEXT NOT NULL DEFAULT '[]',
			UNIQUE(ngay,cua_hang) )"
	);
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang_kho() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
			ngay TEXT NOT NULL, co_so TEXT NOT NULL DEFAULT '', mat_hang TEXT NOT NULL DEFAULT '',
			nhap REAL NOT NULL DEFAULT 0, ban_khai REAL NULL DEFAULT NULL,
			combo_tay REAL NOT NULL DEFAULT 0, dem REAL NULL DEFAULT NULL,
			ghi_chu TEXT DEFAULT '', nguoi TEXT DEFAULT '', luc TEXT NULL,
			UNIQUE(ngay,co_so,mat_hang) )"
	);
	update_option( 'khh_dt_kho_combo', array() );
	update_option( 'khh_dt_kho_combo_ls', array() );
}

/** Giả một ngày bán hàng của FABi: [ tên món => số lượng ] hoặc [ tên => [sl, doanh thu] ]. */
function fabi( $ngay, $co_so, $mon ) {
	global $wpdb;
	$ds = array();
	foreach ( $mon as $n => $q ) {
		/* Mảng [sl, dt] để giả được dòng FABi ĐÃ TÁCH SẴN: có số lượng mà doanh thu 0đ. */
		if ( is_array( $q ) ) {
			$ds[] = array( 'n' => $n, 'g' => '', 'q' => $q[0], 'r' => $q[1] );
			continue;
		}
		$ds[] = array( 'n' => $n, 'g' => '', 'q' => $q, 'r' => $q * 10000 );
	}
	$wpdb->query(
		$wpdb->prepare(
			'INSERT OR REPLACE INTO ' . khh_dt_bang() . ' (ngay,cua_hang,mon) VALUES (%s,%s,%s)',
			$ngay,
			$co_so,
			wp_json_encode( $ds )
		)
	);
}

function dong_cua( $bang, $mh ) {
	foreach ( $bang as $d ) {
		if ( $d['mat_hang'] === $mh ) {
			return $d;
		}
	}
	return null;
}

$CS = 'Tàu Tân Phú';

/* ── 1. món mới của FABi tự có ô nhập ───────────────────────────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10, 'Kẹo cầu vồng' => 4 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
phep( '🔴 món FABi có là sổ kho tự hiện dòng, khỏi khai báo trước', 2 === count( $b ) );
phep( 'và mang đúng số máy ghi bán', 10.0 === (float) dong_cua( $b, 'Nước suối' )['ban_may'] );
phep( 'chưa khai thì `dem` là null, không phải 0', null === dong_cua( $b, 'Nước suối' )['dem'] );
phep( '🔴 chưa khai thì KHÔNG có lệch — ô trống không được trông như đã đếm đúng',
	null === dong_cua( $b, 'Nước suối' )['lech_kho'] );
phep( 'chưa có mốc đếm thì nói ra', false === dong_cua( $b, 'Nước suối' )['co_moc'] );

/* Hôm sau FABi phát sinh món mới -> tự thêm dòng, mà món cũ còn tồn vẫn ở lại. */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 100, 'dem' => 90 ) );
fabi( '2026-09-02', $CS, array( 'Quẩy đùi gà' => 3 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-02', $CS );
phep( '🔴 món mới xuất hiện hôm sau: tự có dòng', null !== dong_cua( $b, 'Quẩy đùi gà' ) );
phep( '🔴 món hôm nay KHÔNG bán vẫn ở lại sổ vì còn tồn', null !== dong_cua( $b, 'Nước suối' ) );
phep( 'và mang tồn đầu đúng bằng số ĐẾM hôm qua, không phải số tính',
	90.0 === (float) dong_cua( $b, 'Nước suối' )['ton_dau'] );

/* ── 2. 🔴 đếm tay thắng số tính, cắt đứt lệch cũ ───────────────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 100, 'dem' => 85 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
$d = dong_cua( $b, 'Nước suối' );
phep( 'tồn tính = 0 + 100 − 10 = 90', 90.0 === (float) $d['ton_tinh'] );
phep( '🔴 đếm 85 thì lệch kho = −5 (mất 5 cái)', -5.0 === (float) $d['lech_kho'] );

fabi( '2026-09-02', $CS, array( 'Nước suối' => 5 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-02', $CS );
$d = dong_cua( $b, 'Nước suối' );
phep( '🔴 hôm sau tồn đầu là 85 (số ĐẾM), không phải 90 (số tính)', 85.0 === (float) $d['ton_dau'] );
phep( 'tồn tính hôm sau = 85 − 5 = 80', 80.0 === (float) $d['ton_tinh'] );
khh_dt_kho_ghi( '2026-09-02', $CS, 'Nước suối', array( 'dem' => 80 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-02', $CS );
phep( '🔴 hôm sau đếm khớp thì lệch = 0 — lỗi hôm trước KHÔNG theo sang',
	0.0 === (float) dong_cua( $b, 'Nước suối' )['lech_kho'] );

/* ── 3. 🔴 tồn tính lấy số MÁY, không lấy số nhân viên khai ─────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Kẹo cầu vồng' => 20 ) );
/* Nhân viên khai bán 15 (thiếu 5) và đếm còn 85 — nếu hệ tính theo số khai thì
   100 − 15 = 85, lệch = 0, và 5 cái mất đi biến mất khỏi sổ. */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Kẹo cầu vồng', array( 'nhap' => 100, 'ban_khai' => 15, 'dem' => 85 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Kẹo cầu vồng' );
phep( '🔴 tồn tính theo MÁY: 100 − 20 = 80', 80.0 === (float) $d['ton_tinh'] );
phep( '🔴 nên đếm 85 ra lệch kho +5, KHÔNG phải 0', 5.0 === (float) $d['lech_kho'] );
phep( '🔴 và lệch khai = 15 − 20 = −5, chỉ thẳng ra người khai thiếu 5',
	-5.0 === (float) $d['lech_khai'] );

/* ── 4. 🔴 combo trừ kho theo thành phần ────────────────────────────────────────────── */
dung_bang();
khh_dt_kho_combo_dat( 'Combo 2 người', array( 'Nước suối' => 2, 'Kẹo cầu vồng' => 1 ), '2026-09-01' );
fabi( '2026-09-01', $CS, array( 'Nước suối' => 3, 'Combo 2 người' => 10 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
$n = dong_cua( $b, 'Nước suối' );
$k = dong_cua( $b, 'Kẹo cầu vồng' );
phep( '🔴 combo trừ kho: nước = 3 lẻ + 10×2 combo = 23', 23.0 === (float) $n['ban_may'] );
phep( 'và tách được hai cột: 3 lẻ, 20 theo combo',
	3.0 === (float) $n['ban_le'] && 20.0 === (float) $n['ban_combo'] );
phep( '🔴 kẹo KHÔNG bán lẻ cái nào vẫn có dòng, vì combo có nó',
	null !== $k && 10.0 === (float) $k['ban_combo'] && 0.0 === (float) $k['ban_le'] );
phep( '🔴 KHÔNG tạo dòng kho mang tên combo — kho không có món "Combo 2 người"',
	null === dong_cua( $b, 'Combo 2 người' ) );
/* Chốt ngược: chưa khai thành phần thì combo vẫn là một món như mọi món khác. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Combo 2 người' => 10 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
phep( 'chưa khai thành phần thì combo đứng nguyên thành một dòng',
	null !== dong_cua( $b, 'Combo 2 người' ) );
phep( '🔴 và hệ NHẮC là món này trông như combo mà chưa khai thành phần',
	in_array( 'Combo 2 người', khh_dt_kho_combo_chua_khai( '2026-08-01', '2026-09-01', $CS ), true ) );

/* Xoá thành phần = xoá combo khỏi bảng. */
khh_dt_kho_combo_dat( 'Combo 2 người', array( 'Nước suối' => 2 ), '2026-09-01' );
phep( 'đặt được thành phần', isset( khh_dt_kho_combo_bang()['Combo 2 người'] ) );
khh_dt_kho_combo_dat( 'Combo 2 người', array(), '2026-09-01' );
phep( 'bảng thành phần rỗng là lệnh xoá combo', ! isset( khh_dt_kho_combo_bang()['Combo 2 người'] ) );

/* ── 5. cơ sở khác không lẫn vào ────────────────────────────────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );
fabi( '2026-09-01', 'Cơ sở khác', array( 'Nước suối' => 999 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' );
phep( '🔴 kho của cơ sở này KHÔNG ăn số của cơ sở kia', 10.0 === (float) $d['ban_may'] );

/* ── 6. khai số 0 là một con số thật, không phải "chưa khai" ────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 10, 'dem' => 0 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' );
phep( '🔴 đếm được 0 cái là ĐÃ khai, không phải chưa khai', 0.0 === (float) $d['dem'] );
phep( 'và vẫn tính ra lệch bình thường', 0.0 === (float) $d['lech_kho'] );

/* ── 7. sửa lại dòng đã khai thì GHI ĐÈ, không thành hai dòng ───────────────────────── */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 10, 'dem' => 3 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
phep( 'khai lại cùng ngày cùng mặt hàng thì SỬA, không đẻ dòng thứ hai', 1 === count( $b ) );
phep( 'và mang số mới', 3.0 === (float) dong_cua( $b, 'Nước suối' )['dem'] );

/* ── 8. số kiểu Việt Nam ("1.200") đọc đúng ─────────────────────────────────────────── */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 200 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => '1.200', 'dem' => '1.000' ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' );
phep( '🔴 "1.200" đọc ra 1200, không phải 1,2', 1200.0 === (float) $d['nhap'] );
phep( 'tồn tính = 1200 − 200 = 1000', 1000.0 === (float) $d['ton_tinh'] );
phep( 'đếm "1.000" khớp, lệch 0', 0.0 === (float) $d['lech_kho'] );

/* ── 9. MÓN MÁY GHI 0đ — VÀ VÌ SAO NÓ KHÔNG TRẢ LỜI ĐƯỢC CÂU HỎI VỀ COMBO ───────────
      Anh Thắng 20/09/2026 hỏi *"Theo máy thì nó có tự tách combo có hàng trong đó không"*, và
      em dựng phép dò với lý lẽ "thành phần combo thì máy ghi số lượng mà doanh thu 0đ".

      🔴 LÝ LẼ ẤY HỎNG, và bài này khoá luôn chỗ hỏng để không ai dựng lại nó:
      `doc-file.php` CỘNG GỘP món THEO TÊN trong mỗi (ngày × cơ sở). Nên mặt hàng vừa bán lẻ
      vừa nằm trong combo sẽ có tổng doanh thu > 0 và KHÔNG BAO GIỜ lọt vào danh sách — tức
      đúng trường hợp cần dò thì dò không ra. Thứ lọt vào lại là món LÚC NÀO CŨNG 0đ: hàng cho,
      khuyến mãi, vé online. Ảnh màn hình anh Thắng gửi đúng thế: toàn "… MIỄN PHÍ" và
      "VÉ ONLINE", không cái nào là thành phần combo.

      Nên hàm nay chỉ nói ĐÚNG điều nó biết: món máy ghi 0đ. Chúng vẫn trừ kho. Còn là hàng cho
      hay thành phần combo thì để người xem quyết — hệ không được kết luận thay. */
dung_bang();
/* Bản xuất KHÔNG tách: chỉ có dòng combo, có tiền. */
fabi( '2026-09-01', $CS, array( 'Combo 2 người' => 10, 'Nước suối' => 3 ) );
phep( 'bản xuất không tách sẵn thì hệ nói là KHÔNG',
	array() === khh_dt_kho_mon_khong_tien( '2026-08-01', '2026-09-01', $CS ) );

/* Bản xuất CÓ tách: dòng combo có tiền, dòng thành phần có số lượng mà 0đ. */
dung_bang();
fabi(
	'2026-09-01',
	$CS,
	array(
		'Combo 2 người' => 10,
		'Nước suối'     => array( 20, 0 ),   // 10 combo x 2 chai, tiền nằm ở dòng combo
		'Kẹo cầu vồng'  => array( 10, 0 ),
	)
);
$tach = khh_dt_kho_mon_khong_tien( '2026-08-01', '2026-09-01', $CS );
phep( 'hệ nêu ra được mấy món máy ghi 0đ', 2 === count( $tach ) );
phep( 'và nói đúng mặt hàng lẫn số lượng', 20.0 === (float) $tach['Nước suối'] );
phep( 'món có doanh thu KHÔNG bị kể vào', ! isset( $tach['Combo 2 người'] ) );

/* 🔴 CHỐT CHỐNG NÓI QUÁ. Mặt hàng VỪA bán lẻ (có tiền) VỪA đi theo combo (0đ) thì bị cộng gộp
   theo tên, tổng doanh thu > 0, nên KHÔNG lọt vào danh sách — dù nó đúng là thứ máy đã tách.
   Phép này đứng đây để nhắc: đừng bao giờ đọc danh sách ấy thành "FABi đã tách sẵn combo". */
dung_bang();
fabi(
	'2026-09-05',
	$CS,
	array( 'Nước suối' => array( 25, 150000 ) )   // 5 bán lẻ có tiền + 20 theo combo 0đ, gộp lại
);
phep( '🔴 mặt hàng vừa bán lẻ vừa theo combo thì KHÔNG hiện ra — phép dò không thấy được nó',
	array() === khh_dt_kho_mon_khong_tien( '2026-09-05', '2026-09-05', $CS ) );

/* Và món hàng cho, lúc nào cũng 0đ, thì LỌT vào — đúng thứ anh Thắng thấy trên màn. */
dung_bang();
fabi( '2026-09-05', $CS, array( 'Bimbim lớn miễn phí' => array( 139, 0 ), 'Vé online' => array( 7, 0 ) ) );
$kt = khh_dt_kho_mon_khong_tien( '2026-09-05', '2026-09-05', $CS );
phep( 'hàng cho / vé online thì lọt vào danh sách 0đ', 2 === count( $kt ) );
phep( 'và chúng KHÔNG phải thành phần combo — hệ không được kết luận thay người dùng',
	isset( $kt['Bimbim lớn miễn phí'] ) && isset( $kt['Vé online'] ) );

dung_bang();
fabi(
	'2026-09-01',
	$CS,
	array(
		'Combo 2 người' => 10,
		'Nước suối'     => array( 20, 0 ),
		'Kẹo cầu vồng'  => array( 10, 0 ),
	)
);

/* Chưa khai combo thì không có gì trừ hai lần. */
phep( 'FABi đã tách mà chưa khai combo thì KHÔNG trừ hai lần',
	array() === khh_dt_kho_tru_hai_lan( '2026-08-01', '2026-09-01', $CS ) );
/* Khai thêm combo lên trên bản đã tách -> trừ hai lần, phải kêu. */
khh_dt_kho_combo_dat( 'Combo 2 người', array( 'Nước suối' => 2, 'Kẹo cầu vồng' => 1 ), '2026-09-01' );
$hai = khh_dt_kho_tru_hai_lan( '2026-08-01', '2026-09-01', $CS );
phep( '🔴 khai combo lên trên bản ĐÃ tách thì hệ kêu TRỪ HAI LẦN', 2 === count( $hai ) );
phep( 'kêu đúng mặt hàng', in_array( 'Nước suối', $hai, true ) );
/* Và con số chứng minh vì sao phải kêu: 20 (dòng đã tách) + 10x2 (khai thêm) = 40, gấp đôi. */
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' );
phep( '🔴 và đúng là trừ gấp đôi: 20 + 20 = 40 chai cho 10 combo', 40.0 === (float) $d['ban_may'] );
/* Xoá khai combo đi là về đúng. */
khh_dt_kho_combo_dat( 'Combo 2 người', array(), '2026-09-01' );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' );
phep( 'xoá khai combo thì về đúng 20 chai', 20.0 === (float) $d['ban_may'] );
phep( 'và hết kêu', array() === khh_dt_kho_tru_hai_lan( '2026-08-01', '2026-09-01', $CS ) );

/* ── 10. 🔴 CHƯA AI ĐẶT MỐC THÌ TỒN ĐẦU LÀ "CHƯA BIẾT", KHÔNG PHẢI SỐ ÂM ──────────────
      20/09/2026 anh Thắng mở thử trên điện thoại: cả màn toàn số âm — "BIMBIM LỚN −61",
      "BIMBIM LỚN MIỄN PHÍ −139", "COCA COLA −14". Vì hệ khởi tồn bằng 0 rồi cứ trừ số bán ra,
      trong khi chưa hề biết trên kệ có bao nhiêu. Số âm ấy không sai một cách thú vị — nó vô
      nghĩa, mà lại tô đỏ cả sổ làm người ta thôi nhìn cột lệch. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Bimbim lớn' => 61 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Bimbim lớn' );
phep( '🔴 chưa ai đặt mốc: tồn đầu là CHƯA BIẾT, không phải −61', null === $d['ton_dau'] );
phep( '🔴 và tồn tính cũng chưa biết, không ra số âm', null === $d['ton_tinh'] );
phep( 'nên cũng không có lệch kho để tô đỏ', null === $d['lech_kho'] );
phep( 'nhưng số máy bán vẫn hiện bình thường', 61.0 === (float) $d['ban_may'] );

/* 🔴 VÀ PHẢI KIỂM QUA NHIỀU NGÀY, không chỉ ngày đầu.
   Ngày đầu thì "hôm qua không có dữ liệu" nên tồn đầu null kiểu gì cũng đúng — phép trên đúng
   kết quả nhưng chưa chạm tới hàm dựng chuỗi tồn. Đã thử: đổi mốc khởi đầu trong
   `khh_dt_kho_trang_thai()` từ null về 0 mà bài vẫn xanh. Phải có NGÀY THỨ HAI: lúc ấy chuỗi
   tồn mới chạy thật, và khởi bằng 0 sẽ đẻ ra đúng con số âm anh Thắng nhìn thấy. */
fabi( '2026-09-02', $CS, array( 'Bimbim lớn' => 78 ) );
$d2 = dong_cua( khh_dt_kho_bang_ngay( '2026-09-02', $CS ), 'Bimbim lớn' );
phep( '🔴 sang ngày thứ hai vẫn chưa ai đặt mốc: tồn đầu vẫn là CHƯA BIẾT, không phải −61',
	null === $d2['ton_dau'] );
phep( 'và tồn tính ngày thứ hai cũng chưa biết', null === $d2['ton_tinh'] );

/* Nhập kho lần đầu là một mốc: kho rỗng + nhập 100 − bán 61 = 39. */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Bimbim lớn', array( 'nhap' => 100 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Bimbim lớn' );
phep( '🔴 lượt NHẬP đầu tiên đặt mốc: tồn tính = 100 − 61 = 39', 39.0 === (float) $d['ton_tinh'] );

/* Hoặc đếm tay lần đầu cũng là một mốc, và hôm sau tính tiếp được. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Coca cola' => 14 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Coca cola', array( 'dem' => 50 ) );
fabi( '2026-09-02', $CS, array( 'Coca cola' => 10 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-02', $CS ), 'Coca cola' );
phep( '🔴 đếm tay lần đầu đặt mốc, hôm sau tính tiếp được: 50 − 10 = 40',
	50.0 === (float) $d['ton_dau'] && 40.0 === (float) $d['ton_tinh'] );
phep( 'và mặt hàng ấy nay đã "có mốc"', true === $d['co_moc'] );

/* ── 11. 🔴 PHÂN LOẠI THEO HÀNG HOÁ CƠ SỞ THẬT SỰ CÓ ─────────────────────────────────
      Anh Thắng: *"Phân loại theo cơ sở đang có hàng của mình nhé"*. FABi bán cả đồ pha tại
      chỗ — BẠC XỈU, CACAO LATTE, COMBO TRÀ CHANH GIÃ TAY — không có kho để đếm. Đổ hết vào sổ
      thì nhân viên cuộn qua vài chục dòng vô nghĩa mới tới chai nước. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10, 'Bạc xỉu' => 8, 'Cacao latte' => 7 ) );
phep( 'chưa chọn danh mục thì bày hết', 3 === count( khh_dt_kho_bang_ngay( '2026-09-01', $CS ) ) );
phep( 'và danh mục đang rỗng', array() === khh_dt_kho_mh_cua( $CS ) );

khh_dt_kho_mh_dat( $CS, array( 'Nước suối' ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
phep( '🔴 chọn rồi thì CHỈ bày hàng hoá có kho', 1 === count( $b ) );
phep( 'và đúng mặt hàng ấy', 'Nước suối' === $b[0]['mat_hang'] );

/* ⚠️ Dòng ĐÃ KHAI thì không được giấu, kể cả khi mặt hàng bị bỏ khỏi danh mục — giấu là số
   người ta đã gõ biến mất khỏi màn mà vẫn nằm trong sổ. */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Bạc xỉu', array( 'dem' => 3 ) );
$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
phep( '🔴 mặt hàng ngoài danh mục MÀ ĐÃ KHAI thì vẫn hiện, không giấu số đã gõ',
	null !== dong_cua( $b, 'Bạc xỉu' ) );
phep( 'còn món ngoài danh mục chưa ai khai thì vẫn ẩn', null === dong_cua( $b, 'Cacao latte' ) );

/* Danh mục lưu riêng theo cơ sở. */
khh_dt_kho_mh_dat( 'Cơ sở khác', array( 'Kẹo' ) );
phep( 'danh mục của cơ sở này không đụng cơ sở kia',
	array( 'Nước suối' ) === khh_dt_kho_mh_cua( $CS ) && array( 'Kẹo' ) === khh_dt_kho_mh_cua( 'Cơ sở khác' ) );
/* Đặt lại danh sách rỗng là "thôi lọc", không phải "ẩn hết". */
khh_dt_kho_mh_dat( $CS, array() );
phep( '🔴 danh mục rỗng là THÔI LỌC, bày lại hết — không phải ẩn hết',
	3 === count( khh_dt_kho_bang_ngay( '2026-09-01', $CS ) ) );

/* Và bảng chọn phải liệt kê đủ món FABi từng ghi ở cơ sở này. */
$da_thay = khh_dt_kho_mon_da_thay( '2026-08-01', '2026-09-01', $CS );
phep( 'bày ra đủ món để chọn', 3 === count( $da_thay ) );
phep( 'kèm số lượng đã bán, để biết món nào đáng đưa vào kho', 10.0 === (float) $da_thay['Nước suối'] );

/* ── 12. 🔴 ĐƯỜNG ĐỌC PHẢI GÁC THEO CƠ SỞ, KHÔNG CHỈ ĐƯỜNG GHI ───────────────────────
      Bản đầu của `khh_dt_rest_kho_xem()` bỏ trống phép gác, với lý do "`khh_dt_duoc_cua_hang()`
      đòi quyền GHI nên không dùng được ở màn xem". Đúng về mặt hàm, sai về mặt kết luận: bỏ
      luôn phép gác thì cửa hàng trưởng quán này đổi một chữ trên thanh địa chỉ là đọc được sổ
      kho, tồn hàng và cả phần khai của quán kia. `bao-cao-ngay.php` đã vấp đúng chỗ này rồi và
      xử bằng `khh_dt_co_so_ds()` — phạm vi cơ sở của người dùng, không dính tới quyền ghi. */
if ( ! function_exists( 'khh_dt_co_so_ds' ) ) {
	function khh_dt_co_so_ds() {
		return isset( $GLOBALS['KHO_PHAM_VI'] ) ? $GLOBALS['KHO_PHAM_VI'] : array();
	}
}
if ( ! function_exists( 'khh_dt_duoc_ghi' ) ) {
	function khh_dt_duoc_ghi() { return true; }
}
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );

$GLOBALS['KHO_PHAM_VI'] = array();   // không giới hạn -> xem được
$r = khh_dt_rest_kho_xem( new WP_REST_Request( array( 'ngay' => '2026-09-01', 'co_so' => $CS ) ) );
phep( 'không giới hạn phạm vi thì xem được', ! is_wp_error( $r ) );

$GLOBALS['KHO_PHAM_VI'] = array( 'Cơ sở khác' );   // chỉ phụ trách quán khác
$r = khh_dt_rest_kho_xem( new WP_REST_Request( array( 'ngay' => '2026-09-01', 'co_so' => $CS ) ) );
phep( '🔴 ĐỌC kho của cơ sở mình KHÔNG phụ trách thì bị chối', is_wp_error( $r ) );
phep( 'và chối bằng mã 403', is_wp_error( $r ) && 403 === (int) $r->get_error_data()['status'] );

$GLOBALS['KHO_PHAM_VI'] = array( $CS );
$r = khh_dt_rest_kho_xem( new WP_REST_Request( array( 'ngay' => '2026-09-01', 'co_so' => $CS ) ) );
phep( 'cơ sở mình phụ trách thì xem được bình thường', ! is_wp_error( $r ) );
phep( 'và trả về đúng tên cơ sở để màn hình bày ra', $CS === $r['co_so'] );
$GLOBALS['KHO_PHAM_VI'] = array();

/* ── 13. 🔴 SỔ GHI ĐỘNG: KHAI LẠI LÀ GHI THÊM, KHÔNG PHẢI GHI ĐÈ ─────────────────────
      21/09/2026, học lối ERPNext/Odoo: sổ ghi động BẤT BIẾN, tồn hiện tại chỉ là tổng của nó
      — "đã ghi thì không sửa, sai thì ghi một bút toán bù". Cách làm ban đầu thì ngược:
      `ON DUPLICATE KEY UPDATE`, khai lại là ghi đè, chỉ còn người với giờ của lần cuối.

      🔴 VỚI SỔ SINH RA ĐỂ BẮT THẤT THOÁT, ĐÓ LÀ LỖ TO NHẤT: người đang bị đối soát tự sửa lại
         con số mình đã khai, không để lại dấu vết. Đếm thiếu, thấy cột lệch đỏ, sửa số đếm cho
         khớp — sổ xanh. Cả bộ máy đối soát vô dụng đúng ở ca nó sinh ra để bắt. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 100, 'dem' => 85 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 100, 'dem' => 90 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 100, 'dem' => 90 ) );

$su = khh_dt_kho_su_cua( '2026-09-01', $CS, 'Nước suối' );
phep( '🔴 ba lượt khai thì sổ giữ ĐỦ BA dòng, không ghi đè', 3 === count( $su ) );
phep( 'mới nhất nằm trước', 90.0 === (float) $su[0]['dem'] );
phep( '🔴 và con số ĐẦU TIÊN vẫn còn nguyên trong sổ — đây là cái vết cần giữ',
	85.0 === (float) $su[ count( $su ) - 1 ]['dem'] );
$sl = khh_dt_kho_so_lan( '2026-09-01', $CS );
phep( 'đếm được số lượt khai để màn hiện "đã sửa N lần"', 3 === (int) $sl['Nước suối'] );

$b = khh_dt_kho_bang_ngay( '2026-09-01', $CS );
phep( 'bảng cộng dồn mang số mới nhất', 90.0 === (float) dong_cua( $b, 'Nước suối' )['dem'] );
phep( 'và chỉ MỘT dòng trên màn, không phải ba', 1 === count( $b ) );

/* 🔴 HAI BẢNG KHÔNG ĐƯỢC THÀNH HAI SỰ THẬT: dựng lại bản cộng dồn từ sổ phải ra y hệt. */
$wpdb->exec_raw( 'UPDATE ' . khh_dt_bang_kho() . " SET dem = 999 WHERE mat_hang = 'Nước suối'" );
phep( 'đã bóp méo bản cộng dồn',
	999.0 === (float) dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' )['dem'] );
khh_dt_kho_dung_lai( '2026-09-01', $CS );
phep( '🔴 dựng lại từ sổ ghi động thì về đúng số thật — sổ là gốc, bảng kia chỉ là bản tính sẵn',
	90.0 === (float) dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' )['dem'] );

/* ── 14. 🔴 SỬA CÔNG THỨC COMBO KHÔNG ĐƯỢC VIẾT LẠI QUÁ KHỨ ──────────────────────────
      Bản trước áp bảng combo HIỆN TẠI cho mọi dòng lịch sử. Nên sửa một công thức hôm nay là
      số tồn của cả mấy tháng trước đổi theo, im lặng: hôm qua sổ cân, hôm nay mở lại đúng ngày
      ấy thì lệch, mà không có gì trên màn nói vì sao. Trên một sổ dùng để đối chất với người
      trực thì đó là thứ không được phép tồn tại. ERPNext giải bằng BOM có ngày hiệu lực. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Combo 2 người' => 10 ) );
fabi( '2026-09-20', $CS, array( 'Combo 2 người' => 10 ) );

khh_dt_kho_combo_dat( 'Combo 2 người', array( 'Nước suối' => 2 ), '2026-09-15' );
$n01 = dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Nước suối' );
$n20 = dong_cua( khh_dt_kho_bang_ngay( '2026-09-20', $CS ), 'Nước suối' );
phep( '🔴 ngày TRƯỚC ngày hiệu lực: combo KHÔNG bị tách — quá khứ nằm nguyên', null === $n01 );
phep( 'ngày SAU ngày hiệu lực: tách đúng 10×2 = 20 chai',
	null !== $n20 && 20.0 === (float) $n20['ban_combo'] );
phep( 'và ngày cũ vẫn để combo đứng thành một dòng như trước',
	null !== dong_cua( khh_dt_kho_bang_ngay( '2026-09-01', $CS ), 'Combo 2 người' ) );

khh_dt_kho_combo_dat( 'Combo 2 người', array( 'Nước suối' => 5 ), '2026-09-18' );
fabi( '2026-09-16', $CS, array( 'Combo 2 người' => 10 ) );
$n16 = dong_cua( khh_dt_kho_bang_ngay( '2026-09-16', $CS ), 'Nước suối' );
$n20 = dong_cua( khh_dt_kho_bang_ngay( '2026-09-20', $CS ), 'Nước suối' );
phep( '🔴 mỗi ngày theo công thức có hiệu lực vào ĐÚNG ngày ấy: 16/09 ra 20 chai',
	null !== $n16 && 20.0 === (float) $n16['ban_combo'] );
phep( 'còn 20/09 ra 50 chai theo công thức mới',
	null !== $n20 && 50.0 === (float) $n20['ban_combo'] );

$ls = khh_dt_kho_combo_ls();
phep( 'lịch sử công thức giữ đủ hai dòng', 2 === count( $ls ) );
phep( 'xếp theo ngày hiệu lực tăng dần',
	'2026-09-15' === $ls[0]['tu_ngay'] && '2026-09-18' === $ls[1]['tu_ngay'] );
phep( 'mỗi dòng ghi rõ combo nào', 'Combo 2 người' === $ls[1]['ten'] );

/* 🔴 CHỖ BÀI THỬ NÀY ĐÃ BẮT ĐƯỢC MỘT LỖI THIẾT KẾ THẬT.
   Bản đầu lưu mỗi lượt sửa thành một BẢN CHỤP CẢ BẢNG kèm ngày hiệu lực. Một lượt ĐẶT LÙI NGÀY
   sinh ra bản chụp của trạng thái LÚC ẤY — thiếu mọi công thức khai sau nó — mà bản chụp ấy lại
   không lan về sau, nên đọc ngày hôm nay là MẤT công thức vừa đặt lùi. Nay ghi hiệu lực theo
   TỪNG COMBO nên ghép lại đúng, y lối BOM của ERPNext. Phép dưới đứng canh đúng chỗ ấy. */
khh_dt_kho_combo_dat( 'Combo khác', array( 'Kẹo' => 1 ), '2026-09-02' );
$b02 = khh_dt_kho_combo_bang( '2026-09-02' );
phep( 'bản đặt lùi có hiệu lực từ đúng ngày ấy',
	isset( $b02['Combo khác'] ) && ! isset( $b02['Combo 2 người'] ) );
$b20 = khh_dt_kho_combo_bang( '2026-09-20' );
phep( '🔴 đặt lùi ngày KHÔNG làm mất công thức đã khai trước đó — ngày 20 có CẢ HAI',
	isset( $b20['Combo khác'] ) && isset( $b20['Combo 2 người'] ) );
phep( 'và vẫn là công thức 5 chai của dòng 18/09',
	5.0 === (float) $b20['Combo 2 người']['Nước suối'] );

/* Thành phần rỗng = xoá combo TỪ ngày ấy, chứ không xoá cả quá khứ. */
khh_dt_kho_combo_dat( 'Combo 2 người', array(), '2026-09-19' );
phep( 'xoá từ 19/09 thì ngày 20 hết công thức',
	! isset( khh_dt_kho_combo_bang( '2026-09-20' )['Combo 2 người'] ) );
phep( '🔴 nhưng ngày 16 vẫn còn công thức cũ — xoá không lùi về quá khứ',
	isset( khh_dt_kho_combo_bang( '2026-09-16' )['Combo 2 người'] ) );

/* ── 15. 🔴 CHỈ HIỆN HÀNG ĐANG BÁN HOẶC CÒN TRÊN KỆ — KHÔNG KÉO CẢ THỰC ĐƠN ─────────
      Anh Thắng 23/09/2026, mở ngày 22/09 khi file FABi cuối nạp là 19/09: cả thực đơn 25 món
      hiện ra, toàn 0 và "—". *"Hàng hoá này chỉ hiện có những sản phẩm đang bán tại cửa hàng
      thôi"*. Vì `khh_dt_kho_trang_thai()` trả về MỌI món từng thấy trong 90 ngày, kể cả món
      chưa ai đặt mốc, và bản trước kéo hết sang ngày sau. */
dung_bang();
/* Ngày 01: bán 3 món. Không ai đặt mốc cho món nào. */
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10, 'Bạc xỉu' => 8, 'Coca cola' => 5 ) );
/* Ngày 02: KHÔNG có báo cáo FABi. */
$b02 = khh_dt_kho_bang_ngay( '2026-09-02', $CS );
phep( '🔴 ngày chưa có báo cáo, chưa ai đặt mốc: KHÔNG kéo cả thực đơn hôm qua sang',
	0 === count( $b02 ) );

/* Đặt mốc cho MỘT món (đếm còn 40) -> món ấy còn trên kệ -> ngày sau vẫn phải hiện. */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'dem' => 40 ) );
$b02 = khh_dt_kho_bang_ngay( '2026-09-02', $CS );
phep( '🔴 mặt hàng CÒN TỒN đã biết thì kéo sang, dù hôm nay không bán', 1 === count( $b02 ) );
phep( 'và đúng mặt hàng ấy, mang tồn đầu 40', 'Nước suối' === $b02[0]['mat_hang'] && 40.0 === (float) $b02[0]['ton_dau'] );

/* Mặt hàng đếm về 0 và hôm nay không bán -> thôi hiện (hết hàng rồi, không còn gì để đếm). */
khh_dt_kho_ghi( '2026-09-01', $CS, 'Coca cola', array( 'dem' => 0 ) );
$b02 = khh_dt_kho_bang_ngay( '2026-09-02', $CS );
phep( 'mặt hàng đã về 0 và không bán thì không kéo sang', null === dong_cua( $b02, 'Coca cola' ) );

/* Nhưng tồn ÂM thì PHẢI kéo sang — đó là dấu hiệu sai sổ, không được giấu. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Kẹo' => 10 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Kẹo', array( 'dem' => 5 ) );
fabi( '2026-09-02', $CS, array( 'Kẹo' => 8 ) );   // bán 8 mà chỉ còn 5 -> tồn tính −3
$b03 = khh_dt_kho_bang_ngay( '2026-09-03', $CS );
phep( '🔴 tồn ÂM vẫn kéo sang ngày sau — sai sổ thì phải bày ra, không giấu',
	null !== dong_cua( $b03, 'Kẹo' ) && -3.0 === (float) dong_cua( $b03, 'Kẹo' )['ton_dau'] );

/* Đã có người khai hôm nay thì hiện, bất kể có bán hay không. */
dung_bang();
khh_dt_kho_ghi( '2026-09-02', $CS, 'Đồ chơi', array( 'nhap' => 20 ) );
phep( 'dòng đã khai hôm nay thì hiện, dù máy chưa có gì', null !== dong_cua( khh_dt_kho_bang_ngay( '2026-09-02', $CS ), 'Đồ chơi' ) );

/* ── 16. 🔴 CHƯA NẠP BÁO CÁO FABi THÌ "MÁY BÁN" LÀ CHƯA BIẾT, KHÔNG PHẢI 0 ────────────
      Cùng ảnh ấy: cột Máy bán in 0 cho mọi dòng, trông y như "hôm nay không bán cái nào" —
      mà thật ra là "chưa có số". Hai nghĩa ngược nhau, và người trực nhìn 0 sẽ đếm rồi thấy
      lệch kho bằng đúng số đã bán, rồi tưởng mất hàng. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'dem' => 40 ) );
phep( 'ngày có báo cáo thì cờ co_fabi = true', true === khh_dt_kho_co_fabi( '2026-09-01', $CS ) );
phep( 'ngày chưa có báo cáo thì cờ co_fabi = false', false === khh_dt_kho_co_fabi( '2026-09-02', $CS ) );

khh_dt_kho_ghi( '2026-09-02', $CS, 'Nước suối', array( 'dem' => 30 ) );   // nhân viên vẫn đếm được
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-02', $CS ), 'Nước suối' );
phep( '🔴 chưa nạp FABi: máy bán là CHƯA BIẾT (null), không phải 0', null === $d['ban_may'] );
phep( 'cả bán lẻ và theo combo cũng chưa biết', null === $d['ban_le'] && null === $d['ban_combo'] );
phep( '🔴 nên tồn tính cũng chưa biết — thiếu một vế là ra số bịa', null === $d['ton_tinh'] );
phep( 'và không có lệch kho để tô đỏ oan', null === $d['lech_kho'] );
phep( 'nhưng số ĐẾM của nhân viên vẫn được lưu và hiện', 30.0 === (float) $d['dem'] );
phep( 'tồn đầu vẫn biết (40, từ mốc hôm qua)', 40.0 === (float) $d['ton_dau'] );

/* Nạp báo cáo cho ngày ấy xong -> tự tính lại, không phải khai lại. */
fabi( '2026-09-02', $CS, array( 'Nước suối' => 7 ) );
$d = dong_cua( khh_dt_kho_bang_ngay( '2026-09-02', $CS ), 'Nước suối' );
phep( '🔴 nạp báo cáo xong thì tự tính: tồn tính = 40 − 7 = 33', 33.0 === (float) $d['ton_tinh'] );
phep( 'và lệch kho = 30 − 33 = −3, từ số đếm đã lưu trước đó', -3.0 === (float) $d['lech_kho'] );

/* ── 17. THẺ KHO: MỘT MẶT HÀNG CHẠY TỪNG NGÀY ─────────────────────────────────────────
      Anh Thắng 23/09/2026: *"Chưa thấy có chỗ thay đổi hàng hoá theo ngày, giống kiểu tồn kho
      ngày đó bao nhiêu, bán bao nhiêu, tồn bao nhiêu"*. Màn ngày chỉ một ngày một lúc.

      🔴 Thẻ kho và màn ngày phải DÙNG CHUNG lõi chạy chuỗi (`khh_dt_kho_chay`). Bản thứ hai
         là sớm muộn hai màn ra hai số khác nhau cho cùng một ngày, không màn nào sai để lần ra.
         Phép cuối mục này đối chiếu thẳng hai bên. */
dung_bang();
fabi( '2026-09-01', $CS, array( 'Nước suối' => 10 ) );
khh_dt_kho_ghi( '2026-09-01', $CS, 'Nước suối', array( 'nhap' => 100, 'dem' => 88 ) );   // tính 90, đếm 88
fabi( '2026-09-02', $CS, array( 'Nước suối' => 8 ) );                                       // không đếm
/* 03/09: KHÔNG có báo cáo FABi, nhưng có nhập */
khh_dt_kho_ghi( '2026-09-03', $CS, 'Nước suối', array( 'nhap' => 24 ) );
fabi( '2026-09-04', $CS, array( 'Nước suối' => 12 ) );
khh_dt_kho_ghi( '2026-09-04', $CS, 'Nước suối', array( 'dem' => 92 ) );

$the = khh_dt_kho_the( $CS, 'Nước suối', '2026-09-01', '2026-09-04' );
phep( 'thẻ kho có đúng 4 ngày có biến động', 4 === count( $the ) );
$n = array(); foreach ( $the as $r ) { $n[ $r['ngay'] ] = $r; }

/* 01/09 */
phep( '01/09 tồn đầu chưa biết (chưa mốc), nhập 100 đặt mốc', null === $n['2026-09-01']['ton_dau'] );
phep( '01/09 tồn tính = 0 + 100 − 10 = 90', 90.0 === (float) $n['2026-09-01']['ton_tinh'] );
phep( '🔴 01/09 đếm 88 -> lệch −2, tồn CUỐI chốt theo số đếm = 88',
	-2.0 === (float) $n['2026-09-01']['lech_kho'] && 88.0 === (float) $n['2026-09-01']['ton_cuoi'] );
/* 02/09 */
phep( '🔴 02/09 tồn đầu = 88 (số ĐẾM hôm trước, không phải 90 số tính)', 88.0 === (float) $n['2026-09-02']['ton_dau'] );
phep( '02/09 bán 8, không đếm -> tồn cuối = tồn tính = 80', 80.0 === (float) $n['2026-09-02']['ton_cuoi'] && null === $n['2026-09-02']['dem'] );
/* 03/09 — chưa nạp FABi */
phep( '🔴 03/09 chưa nạp FABi: cờ co_fabi = false và máy bán là CHƯA BIẾT', false === $n['2026-09-03']['co_fabi'] && null === $n['2026-09-03']['ban_may'] );
phep( '03/09 chuỗi vẫn chạy: 80 + 24 = 104 kéo sang ngày sau', 104.0 === (float) $n['2026-09-03']['ton_cuoi'] );
/* 04/09 */
phep( '04/09 tồn đầu 104, bán 12 -> tính 92, đếm 92 -> lệch 0', 104.0 === (float) $n['2026-09-04']['ton_dau'] && 0.0 === (float) $n['2026-09-04']['lech_kho'] );
phep( 'ngày có FABi thì co_fabi = true', true === $n['2026-09-04']['co_fabi'] );

/* Khoảng hẹp: chỉ trả ngày trong khoảng, nhưng tồn đầu vẫn đúng nhờ chạy từ trước. */
$the2 = khh_dt_kho_the( $CS, 'Nước suối', '2026-09-03', '2026-09-04' );
phep( 'lọc theo khoảng: 2 ngày', 2 === count( $the2 ) );
phep( '🔴 tồn đầu ngày đầu khoảng vẫn đúng (80) — chuỗi chạy từ trước khoảng', 80.0 === (float) $the2[0]['ton_dau'] );

/* Mặt hàng khác không lẫn vào. */
phep( 'mặt hàng không có gì thì thẻ rỗng', array() === khh_dt_kho_the( $CS, 'Kẹo', '2026-09-01', '2026-09-04' ) );

/* 🔴 ĐỐI CHIẾU HAI MÀN: tồn cuối 04/09 trên thẻ kho == tồn đầu 05/09 trên màn ngày. */
$b05 = dong_cua( khh_dt_kho_bang_ngay( '2026-09-05', $CS ), 'Nước suối' );
phep( '🔴 thẻ kho và màn ngày nói cùng một số (92)', null !== $b05 && 92.0 === (float) $b05['ton_dau'] && 92.0 === (float) $n['2026-09-04']['ton_cuoi'] );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: kho trừ theo số máy, combo tách thành phần, đếm tay cắt lệch cũ.\n";
