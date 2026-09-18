<?php
/**
 * PHÍ MoMo: NHẬP THEO KHOẢNG, CHIA THEO % DOANH THU, VÀ CỘNG LẠI PHẢI ĐÚNG SỐ ĐÃ NHẬP.
 *
 * Anh Thắng 18/09/2026 chốt luật: phí MoMo không có theo giao dịch (đã kiểm cả Transaction
 * report lẫn daily report của MoMo — không nguồn nào có), chỉ có MỘT SỐ TỔNG trên màn đối soát.
 * Nên nhập tay theo khoảng ngày × tài khoản, rồi hệ chia về cơ sở theo % doanh thu. Và mức phí
 * *thay đổi*, nên không được tính theo tỷ lệ cố định.
 *
 * Ba thứ bài này khoá:
 *
 *   1. 🔴 CHỒNG NGÀY LÀ TÍNH PHÍ HAI LẦN. Nhập ngày 17, hôm sau nhập gộp "17→19" cho gọn — phí
 *      ngày 17 vào sổ hai lượt. Không dòng nào sai, tổng vẫn ra số đẹp, không gì báo.
 *   2. 🔴 CHIA XONG CỘNG LẠI PHẢI BẰNG ĐÚNG SỐ GỐC. Chia tỷ lệ rồi làm tròn từng phần là tổng
 *      lệch vài đồng — trên bảng đối soát, vài đồng lệch là có người đi tìm.
 *   3. 🔴 PHÍ CỦA TÀI KHOẢN NÀO CHỈ CHIA CHO CƠ SỞ CỦA TÀI KHOẢN ẤY. K&H có hai pháp nhân; chia
 *      lẫn là cơ sở bên này gánh phí của bên kia.
 *
 * Chạy: php tools/test/kiem-momo-phi.php
 */

require_once __DIR__ . '/wp-stub.php';
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n, $d = 0 ) { return number_format( $n, $d ); }
}

$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/momo.php';
require_once $goc . '/momo-phi.php';

$dat = 0; $hong = array();
function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) { $dat++; } else { $hong[] = $ten; }
}

global $wpdb;
function dung_bang() {
	global $wpdb;
	foreach ( array( khh_dt_bang_momo_phi(), khh_dt_bang_momo_sk() ) as $b ) {
		$wpdb->exec_raw( "DROP TABLE IF EXISTS $b" );
	}
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang_momo_phi() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
			tu TEXT NOT NULL, den TEXT NOT NULL, tai_khoan TEXT NOT NULL DEFAULT '',
			phi REAL NOT NULL DEFAULT 0, ghi_chu TEXT DEFAULT '', nguoi TEXT DEFAULT '',
			luc TEXT NULL, UNIQUE(tu,den,tai_khoan) )"
	);
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang_momo_sk() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
			ma_gd TEXT NOT NULL DEFAULT '' UNIQUE, ma_don TEXT DEFAULT '', ngay TEXT NOT NULL,
			gio INTEGER DEFAULT 0, so_tien REAL DEFAULT 0, trang_thai TEXT DEFAULT '',
			loai_gd TEXT DEFAULT '', nguon_tien TEXT DEFAULT '', ma_ch TEXT DEFAULT '',
			ten_ch TEXT DEFAULT '', nap_luc TEXT NULL )"
	);
	update_option( 'khh_dt_momo_ma_ch_tk', array() );
	update_option( 'khh_dt_ghep_ma_ch_momo', array() );
}
function gd( $ma, $ngay, $tien, $ma_ch ) {
	global $wpdb;
	$wpdb->exec_raw( $wpdb->prepare(
		'INSERT INTO ' . khh_dt_bang_momo_sk() . ' (ma_gd,ngay,so_tien,ma_ch) VALUES (%s,%s,%f,%s)',
		array( $ma, $ngay, $tien, $ma_ch )
	) );
}

/* ── 1. Bảng ghép mã cửa hàng -> tài khoản ─────────────────────────────────────────────── */
dung_bang();
phep( 'học được mã cửa hàng thuộc tài khoản nào',
	4 === khh_dt_momo_tk_hoc( array( 'KHTUTU1', 'KHTUTU2', 'KHECO2', 'KHEVENT2' ), 'kh785 ' ) );
phep( 'mã tài khoản được chuẩn hoá (kh785 -> KH785)', 'KH785' === khh_dt_momo_tk_cua_ma_ch( 'KHTUTU2' ) );
phep( 'mã lạ thì trả rỗng', '' === khh_dt_momo_tk_cua_ma_ch( 'KHXXX' ) );

/* ── 2. 🔴 Chối chồng ngày ─────────────────────────────────────────────────────────────── */
phep( 'nhập lượt đầu thì được', ! is_wp_error( khh_dt_momo_phi_dat( '2026-09-17', '2026-09-17', 'KH785', 8118 ) ) );
$r = khh_dt_momo_phi_dat( '2026-09-17', '2026-09-19', 'KH785', 20000 );
phep( '🔴 lượt CHỒNG ngày bị CHỐI', is_wp_error( $r ) );
if ( is_wp_error( $r ) ) {
	phep( 'câu chối nói ra lượt đang chồng', false !== strpos( $r->get_error_message(), '2026-09-17' ) );
	phep( 'câu chối nói rõ hậu quả là tính hai lần',
		false !== strpos( $r->get_error_message(), 'hai lần' ) );
}
phep( 'khoảng KHÔNG chồng thì nhận',
	! is_wp_error( khh_dt_momo_phi_dat( '2026-09-18', '2026-09-19', 'KH785', 5000 ) ) );
phep( 'tài khoản KHÁC thì chồng ngày cũng không sao (phí riêng)',
	! is_wp_error( khh_dt_momo_phi_dat( '2026-09-17', '2026-09-17', 'KH999', 3000 ) ) );
phep( 'ngày kết thúc sớm hơn ngày đầu thì chối',
	is_wp_error( khh_dt_momo_phi_dat( '2026-09-20', '2026-09-19', 'KH785', 100 ) ) );
phep( 'phí âm thì chối', is_wp_error( khh_dt_momo_phi_dat( '2026-10-01', '2026-10-01', 'KH785', -5 ) ) );
phep( 'thiếu mã tài khoản thì chối', is_wp_error( khh_dt_momo_phi_dat( '2026-10-02', '2026-10-02', '', 5 ) ) );

/* ── 3. 🔴 Chia xong cộng lại đúng số gốc ──────────────────────────────────────────────── */
/* Ca cố tình xấu: 3 cơ sở, tỷ lệ lẻ, phí lẻ -> chia thẳng là lệch vài đồng. */
$chia = khh_dt_chia_tron( 8118, array( 'a' => 990000, 'b' => 1000000, 'c' => 470000 ) );
phep( '🔴 tổng các phần BẰNG ĐÚNG số gốc', 8118 === array_sum( $chia ) );
phep( 'ô doanh thu lớn hơn thì phí lớn hơn', $chia['b'] > $chia['c'] );
phep( 'mọi phần đều là số nguyên đồng',
	$chia === array_map( 'intval', $chia ) );
/* Ca cực đoan: một đồng chia cho ba cơ sở. */
$le = khh_dt_chia_tron( 1, array( 'a' => 1, 'b' => 1, 'c' => 1 ) );
phep( 'một đồng chia ba vẫn cộng lại đúng một đồng', 1 === array_sum( $le ) );
phep( 'không ô nào âm', 0 === count( array_filter( $le, function ( $x ) { return $x < 0; } ) ) );
phep( 'trọng số rỗng thì không vỡ', array() === khh_dt_chia_tron( 100, array() ) );
phep( 'tổng trọng số bằng 0 thì không chia bừa', array() === khh_dt_chia_tron( 100, array( 'a' => 0 ) ) );

/* ── 4. 🔴 Chia đúng cơ sở của đúng tài khoản ──────────────────────────────────────────── */
dung_bang();
khh_dt_momo_tk_hoc( array( 'KHTUTU2', 'KHECO2' ), 'KH785' );
khh_dt_momo_tk_hoc( array( 'KHKHAC' ), 'KH999' );
gd( 'g1', '2026-09-17', 900000, 'KHTUTU2' );
gd( 'g2', '2026-09-17', 100000, 'KHECO2' );
gd( 'g3', '2026-09-17', 500000, 'KHKHAC' );   // tài khoản KHÁC, không được dính phí của KH785
khh_dt_momo_phi_dat( '2026-09-17', '2026-09-17', 'KH785', 3300 );
$c = khh_dt_momo_phi_chia( '2026-09-17', '2026-09-17' );
phep( '🔴 tổng phí chia ra bằng đúng số đã nhập', 3300 === (int) $c['tong'] );
phep( '🔴 cơ sở của tài khoản KHÁC KHÔNG bị gánh phí', ! isset( $c['co_so']['KHKHAC'] ) );
phep( 'chia theo tỷ lệ doanh thu (900k/1tr = 90%)', 2970 === (int) $c['co_so']['KHTUTU2'] );
phep( 'phần còn lại về cơ sở kia', 330 === (int) $c['co_so']['KHECO2'] );

/* ── 5. Nhập gộp nhiều ngày, xem từng kỳ khác nhau ─────────────────────────────────────── */
dung_bang();
khh_dt_momo_tk_hoc( array( 'KHA' ), 'KH785' );
gd( 'h1', '2026-09-17', 600000, 'KHA' );
gd( 'h2', '2026-09-18', 400000, 'KHA' );
khh_dt_momo_phi_dat( '2026-09-17', '2026-09-18', 'KH785', 3300 );   // một lượt gộp hai ngày
$ca = khh_dt_momo_phi_chia( '2026-09-17', '2026-09-18' );
phep( 'xem cả hai ngày thì được đủ phí', 3300 === (int) $ca['tong'] );
/* 🔴 Xem riêng ngày 17: chỉ được phần của ngày 17, và phần ấy tính theo tỷ lệ TRONG LƯỢT NHẬP
   (600k/1tr = 60%), không phải chia lại theo khoảng đang xem. */
$c17 = khh_dt_momo_phi_chia( '2026-09-17', '2026-09-17' );
phep( '🔴 xem một ngày thì chỉ lấy phần của ngày ấy', 1980 === (int) $c17['tong'] );
phep( 'và phần ấy về đúng cơ sở', 1980 === (int) $c17['co_so']['KHA'] );

/* ── 6. Nhắc ngày chưa nhập phí ────────────────────────────────────────────────────────── */
dung_bang();
khh_dt_momo_tk_hoc( array( 'KHA' ), 'KH785' );
gd( 'i1', '2026-09-17', 100000, 'KHA' );
gd( 'i2', '2026-09-18', 100000, 'KHA' );
gd( 'i3', '2026-09-19', 100000, 'KHA' );
khh_dt_momo_phi_dat( '2026-09-17', '2026-09-17', 'KH785', 330 );
$t = khh_dt_momo_phi_thieu( '2026-09-17', '2026-09-19' );
phep( 'nhắc đúng tài khoản còn thiếu', isset( $t['KH785'] ) );
phep( 'nhắc đúng 2 ngày chưa nhập', array( '2026-09-18', '2026-09-19' ) === $t['KH785'] );
/* Chốt ngược: nhập nốt thì hết nhắc — kẻo bài xanh vì nó nhắc BỪA mọi ngày. */
khh_dt_momo_phi_dat( '2026-09-18', '2026-09-19', 'KH785', 660 );
phep( '🔴 nhập đủ thì KHÔNG nhắc nữa', array() === khh_dt_momo_phi_thieu( '2026-09-17', '2026-09-19' ) );

/* ── 7. 🔴 BA LỖI ANH THẮNG GẶP KHI DÙNG THẬT (18/09/2026) ─────────────────────────────── */

/* ① Gõ "68.866" (đúng như MoMo in) mà vào sổ thành 69đ — mất 68.797đ, không câu báo nào, vì 69
      vẫn là một con số hợp lệ. Ô nhập là type="number" nên trình duyệt hiểu dấu chấm là dấu
      THẬP PHÂN. Nay ô là text và máy chủ đọc bằng `khh_dt_so()`. */
require_once dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu/doc-file.php';
phep( '🔴 "68.866" đọc ra 68.866đ, không phải 69đ', 68866.0 === (float) khh_dt_so( '68.866' ) );
phep( '"68,866" (dấu phẩy) cũng ra 68.866đ', 68866.0 === (float) khh_dt_so( '68,866' ) );
phep( '"68866" trơn vẫn đúng', 68866.0 === (float) khh_dt_so( '68866' ) );

/* ② Gõ lại ĐÚNG khoảng cũ là SỬA, không phải chồng — nếu không thì gõ sai một lần là phải xoá
      rồi nhập lại, mà anh Thắng chỉ muốn sửa con số. */
dung_bang();
khh_dt_momo_phi_dat( '2026-09-02', '2026-09-02', 'KH785', 69 );
$sua = khh_dt_momo_phi_dat( '2026-09-02', '2026-09-02', 'KH785', 68866 );
phep( '🔴 gõ lại đúng khoảng cũ thì SỬA được, không bị chối là chồng', ! is_wp_error( $sua ) );
$ds_sua = khh_dt_momo_phi_ds( '2026-09-02', '2026-09-02' );
phep( 'sửa xong chỉ còn MỘT dòng', 1 === count( $ds_sua ) );
phep( 'và mang số mới', 68866.0 === (float) $ds_sua[0]['phi'] );
/* Chốt ngược: khoảng KHÁC mà chồng ngày thì vẫn phải chối. */
phep( 'khoảng khác mà chồng ngày thì VẪN chối',
	is_wp_error( khh_dt_momo_phi_dat( '2026-09-01', '2026-09-03', 'KH785', 100 ) ) );

/* ③ Nhập phí mà chưa ghép được cơ sở nào vào tài khoản.
      Anh Thắng: *"Đã có phí sao không chia cho cửa hàng luôn đi"* — nên hệ CHIA TẠM cho mấy cơ
      sở chưa thuộc tài khoản nào, và nói rõ là tạm. Trước đó nó trả về 0 và chỉ báo suông. */
dung_bang();
gd( 'k1', '2026-09-01', 5000000, 'KHTUTU2' );   // có giao dịch, nhưng CHƯA học tài khoản
gd( 'k2', '2026-09-01', 5000000, 'KHVRFUN' );
khh_dt_momo_phi_dat( '2026-09-01', '2026-09-01', 'KH785', 62447 );
$c7 = khh_dt_momo_phi_chia( '2026-09-01', '2026-09-16' );
phep( '🔴 chưa ghép tài khoản thì vẫn CHIA TẠM, không bỏ trống', 62447 === (int) $c7['tong'] );
phep( 'chia tạm xong cộng lại vẫn đúng số gốc',
	62447 === (int) array_sum( array_map( 'intval', $c7['co_so'] ) ) );
phep( 'chia tạm cho cả 2 cơ sở chưa có chủ', 2 === count( $c7['co_so'] ) );
phep( '🔴 và PHẢI nói rõ là đang chia TẠM', 1 === count( $c7['chia_tam'] ) );
phep( 'báo tạm kèm đúng tài khoản, số tiền và số cơ sở',
	'KH785' === $c7['chia_tam'][0]['tai_khoan']
	&& 62447.0 === (float) $c7['chia_tam'][0]['phi']
	&& 2 === (int) $c7['chia_tam'][0]['so_co_so'] );
phep( 'chia tạm được thì không còn kể là "chưa chia được"', array() === $c7['chua_chia'] );

/* 🔴 CHỐT NGƯỢC — HAI TÀI KHOẢN CÙNG CHƯA GHÉP THÌ CẤM CHIA TẠM.
   Cùng đổ vào một rổ cơ sở là mỗi cơ sở gánh phí của CẢ HAI pháp nhân. Tổng toàn hệ vẫn đúng
   nên không gì báo, mà từng cơ sở thì sai — đúng kiểu sai khó thấy nhất. */
khh_dt_momo_phi_dat( '2026-09-01', '2026-09-01', 'KH989', 10000 );
$c7b = khh_dt_momo_phi_chia( '2026-09-01', '2026-09-16' );
phep( '🔴 hai tài khoản cùng chưa ghép thì KHÔNG chia tạm nữa', 0 === (int) $c7b['tong'] );
phep( 'mà báo cả hai là chưa chia được', 2 === count( $c7b['chua_chia'] ) );
phep( 'và không báo tạm cái nào', array() === $c7b['chia_tam'] );

/* `tk_da_ghep` KHÔNG được kể tài khoản mới chỉ nhập phí — đó là lỗi làm màn báo "đã biết
   KH785" trong khi bảng ghép rỗng. */
phep( '🔴 tài khoản chỉ mới nhập phí KHÔNG tính là "đã ghép cơ sở"',
	! in_array( 'KH785', khh_dt_momo_tk_da_ghep(), true ) );
phep( 'nhưng vẫn được gợi ý ở ô gõ', in_array( 'KH785', khh_dt_momo_tk_ds(), true ) );

/* Ghép đàng hoàng thì mỗi tài khoản về đúng cơ sở của mình, và hết báo tạm. */
khh_dt_momo_tk_hoc( array( 'KHTUTU2' ), 'KH785' );
khh_dt_momo_tk_hoc( array( 'KHVRFUN' ), 'KH989' );
$c8 = khh_dt_momo_phi_chia( '2026-09-01', '2026-09-16' );
phep( 'ghép xong thì chia thật', 72447 === (int) $c8['tong'] );
phep( 'và hết báo "chưa chia được"', array() === $c8['chua_chia'] );
phep( '🔴 và hết báo "chia tạm"', array() === $c8['chia_tam'] );
$ten785 = khh_dt_ma_ch_toi_co_so( 'KHTUTU2' );
$ten989 = khh_dt_ma_ch_toi_co_so( 'KHVRFUN' );
$ten785 = '' === $ten785 ? 'KHTUTU2' : $ten785;
$ten989 = '' === $ten989 ? 'KHVRFUN' : $ten989;
phep( '🔴 phí KH785 không rơi sang cơ sở của KH989',
	62447 === (int) $c8['co_so'][ $ten785 ] && 10000 === (int) $c8['co_so'][ $ten989 ] );

/* ④ Cơ sở ĐÃ có chủ thì phép chia tạm không được đụng tới. */
dung_bang();
gd( 'm1', '2026-09-01', 5000000, 'KHDAGHEP' );
gd( 'm2', '2026-09-01', 5000000, 'KHCHUAAI' );
khh_dt_momo_tk_hoc( array( 'KHDAGHEP' ), 'KH989' );
khh_dt_momo_phi_dat( '2026-09-01', '2026-09-01', 'KH785', 1000 );
$c9  = khh_dt_momo_phi_chia( '2026-09-01', '2026-09-16' );
$ten = khh_dt_ma_ch_toi_co_so( 'KHCHUAAI' );
$ten = '' === $ten ? 'KHCHUAAI' : $ten;
phep( '🔴 chia tạm CHỈ đụng cơ sở chưa có chủ',
	array_keys( $c9['co_so'] ) === array( $ten ) && 1000 === (int) $c9['co_so'][ $ten ] );

/* ⑤ 🔴 BẤM XOÁ MÀ KHÔNG XOÁ ĐƯỢC (anh Thắng, 18/09/2026) — VÀ KHÔNG CÂU BÁO NÀO.
      Màn hình gửi `id` trong THÂN multipart của một yêu cầu DELETE. PHP chỉ tự bóc thân
      multipart cho POST; với DELETE thì `$_POST` rỗng, mà `WP_REST_Request` cũng chỉ bóc JSON
      và form-urlencoded. Nên `id` tới đây là RỖNG -> `(int) null` = 0 -> xoá hàng số 0 -> không
      hàng nào khớp -> `xong:false` kèm mã 200. Màn hình vẽ lại y như cũ.

      Bài này khoá vế MÁY CHỦ: thiếu `id` phải là LỖI, không được im lặng trả về "không xoá
      được". Vế màn hình (`id` đi trên đường dẫn) do `kiem-sua-phi-momo.py` khoá. */
dung_bang();
gd( 'x1', '2026-09-01', 1000000, 'KHX' );
khh_dt_momo_tk_hoc( array( 'KHX' ), 'KH785' );
khh_dt_momo_phi_dat( '2026-09-01', '2026-09-01', 'KH785', 3300 );
$ds_x = khh_dt_momo_phi_ds( '2026-09-01', '2026-09-01' );
phep( 'có một lượt phí để xoá', 1 === count( $ds_x ) );

$loi = khh_dt_rest_momo_phi_xoa( new WP_REST_Request( array() ) );
phep( '🔴 thiếu id thì BÁO LỖI, không lặng lẽ trả "không xoá được"', is_wp_error( $loi ) );
phep( 'id rỗng cũng vậy', is_wp_error( khh_dt_rest_momo_phi_xoa( new WP_REST_Request( array( 'id' => '' ) ) ) ) );
phep( 'và lượt phí vẫn còn nguyên sau lượt gọi hụt',
	1 === count( khh_dt_momo_phi_ds( '2026-09-01', '2026-09-01' ) ) );

/* Chốt xuôi: có id thì xoá THẬT, và nói là xong. */
$ok = khh_dt_rest_momo_phi_xoa( new WP_REST_Request( array( 'id' => $ds_x[0]['id'] ) ) );
phep( 'có id thì xoá được', ! is_wp_error( $ok ) && ! empty( $ok['xong'] ) );
phep( '🔴 và hàng BIẾN MẤT thật khỏi sổ', array() === khh_dt_momo_phi_ds( '2026-09-01', '2026-09-01' ) );
/* Xoá xong thì phép chia cũng không còn kể tới nó nữa. */
$c10 = khh_dt_momo_phi_chia( '2026-09-01', '2026-09-16' );
phep( 'xoá xong thì phí không còn trong phép chia', 0 === (int) $c10['tong'] );
/* Id không có thật: xoá hụt, nhưng là "không tìm thấy", không phải lỗi thiếu tham số. */
$hut = khh_dt_rest_momo_phi_xoa( new WP_REST_Request( array( 'id' => 999999 ) ) );
phep( 'id không có thật thì báo chưa xong (để màn hình kêu lên)',
	! is_wp_error( $hut ) && empty( $hut['xong'] ) );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: phí nhập theo khoảng, chia theo % doanh thu, cộng lại đúng số gốc.\n";
