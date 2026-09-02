/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BÁO CÁO TỔNG — bảng chéo (cơ sở | ghế) × NGÀY
 *
 * Anh Thắng 01/09/2026, ba ảnh mẫu Excel: *"báo cáo tổng"* (theo cơ sở) · *"từng điểm theo
 * ngày"* (theo từng ghế) · *"QR theo ngày"* (cùng dạng, đổi sang cột QR).
 *
 * ⚠️ KHÔNG DÒ CHUỖI Ở PHẦN LÕI. `bao_cao_tong()` được BỐC RA rồi CHẠY THẬT bằng PHP trên một
 *    bảng dữ liệu giả, và so từng ô một. Dò chuỗi chỉ canh được cách viết hôm nay.
 *
 * 🔴 BA CHỖ DỄ SAI, VÀ CẢ BA ĐỀU KHÔNG KÊU TIẾNG NÀO:
 *    1. Cơ sở KHÔNG có đồng nào bị rơi khỏi bảng — trông y hệt cơ sở chưa mở.
 *    2. Ngày không ai thu bị rơi khỏi dãy cột — bảng nhảy cóc, người đọc tưởng chọn sai ngày.
 *    3. Cộng nhầm cột: hàng TỔNG và cột Tổng phải ra CÙNG một con số, nếu không thì hai chỗ
 *       trong cùng một bảng nói hai điều.
 *
 * Chạy: node tools/test/kiem-bao-cao-tong.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const { execFileSync } = require('child_process');
const os = require('os');
const path = require('path');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* ---------- Bốc HAI HÀM ra khỏi tệp nguồn, không chép lại ---------- */
const NGUON = 'vhcp-ghe/includes/class-vhg-ketoan.php';
const src = fs.readFileSync(NGUON, 'utf8');
const iH = src.indexOf('const BCT_MAX_NGAY');
const iSau = src.indexOf('// ══', iH);
t('bốc được khối bao_cao_tong() từ tệp nguồn', iH > 0 && iSau > iH);
const than = src.slice(iH, iSau > iH ? iSau : src.length);
t('khối ấy có cả hàm dựng hàng', than.indexOf('private static function bct_hang_') > 0);

/* ---------- Bệ đỡ PHP ---------- */
const BE_DO = `<?php
define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB { public static function t( $x ) { return 'vhg_' . $x; } }
class VHG_May {
	public static $coso = array(); public static $may = array();
	public static function ds_coso() { return self::$coso; }
	public static function ds_may() { return self::$may; }
}
class FakeWpdb {
	public $hang = array();
	public function prepare( $q ) { return $q; }
	public function get_results( $q, $m = null ) { return $this->hang; }
}
$wpdb = new FakeWpdb();
class VHG_KeToan {
	public static function ngay_( $x ) {
		$s = trim( (string) $x );
		return preg_match( '/^\\d{4}-\\d{2}-\\d{2}/', $s ) ? substr( $s, 0, 10 ) : '';
	}
	__THAN__
}
$ca = json_decode( file_get_contents( $argv[1] ), true );
$wpdb->hang    = $ca['rows'];
VHG_May::$coso = $ca['coso'];
VHG_May::$may  = $ca['may'];
echo json_encode( VHG_KeToan::bao_cao_tong( $ca['tu'], $ca['den'], $ca['muc'], $ca['cot'] ), JSON_UNESCAPED_UNICODE );
`;

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'bct-'));
const fPhp = path.join(tmp, 'be.php');
fs.writeFileSync(fPhp, BE_DO.replace('__THAN__', than));

function goi(ca) {
	const fIn = path.join(tmp, 'ca.json');
	fs.writeFileSync(fIn, JSON.stringify(ca));
	return JSON.parse(execFileSync('php', [fPhp, fIn], { encoding: 'utf8' }));
}

/* ---------- Cảnh dựng sẵn: 3 cơ sở, cơ sở thứ ba KHÔNG có đồng nào ---------- */
const COSO = [
	{ ten: 'AEON TÂN PHÚ', tinh: 'HCM', ma_kh: 'KH00119' },
	{ ten: 'GO THỦ DẦU MỘT', tinh: 'BD', ma_kh: 'KH00110' },
	{ ten: 'BỆNH VIỆN 175', tinh: 'HCM', ma_kh: 'KH00118' },
];
const MAY = [
	{ ma: '80001', ten_khai: 'AM-TP-1', coso_ten: 'AEON TÂN PHÚ', an: 0 },
	{ ma: '80002', ten_khai: 'AM-TP-2', coso_ten: 'AEON TÂN PHÚ', an: 0 },
	{ ma: '80016', ten_khai: 'GO-TDM-1', coso_ten: 'GO THỦ DẦU MỘT', an: 0 },
	{ ma: '80099', ten_khai: 'BV-175-1', coso_ten: 'BỆNH VIỆN 175', an: 0 },
];
/* Ngày 12/09 CỐ Ý không có ai thu — nó phải vẫn là một cột trống. */
const ROWS = [
	{ ngay: '2026-09-11', ma_may: '80001', ten: 'AM-TP-1', tong: 300, qr: 100, tien_mat: 200, coso: 'AEON TÂN PHÚ' },
	{ ngay: '2026-09-11', ma_may: '80002', ten: 'AM-TP-2', tong: 500, qr: 500, tien_mat: 0,   coso: 'AEON TÂN PHÚ' },
	{ ngay: '2026-09-13', ma_may: '80001', ten: 'AM-TP-1', tong: 70,  qr: 0,   tien_mat: 70,  coso: 'AEON TÂN PHÚ' },
	{ ngay: '2026-09-13', ma_may: '80016', ten: 'GO-TDM-1', tong: 90, qr: 40,  tien_mat: 50,  coso: 'GO THỦ DẦU MỘT' },
];
function ca(muc, cot) {
	return { tu: '2026-09-11', den: '2026-09-13', muc: muc, cot: cot, rows: ROWS, coso: COSO, may: MAY };
}

/* ---------- 1. GỘP THEO CƠ SỞ ---------- */
let r = goi(ca('coso', 'tong'));
t('chạy được', !!r.ok, r);
teq('🔴 dãy ngày dựng từ KHOẢNG CHỌN — ngày không ai thu vẫn là một cột',
	['2026-09-11', '2026-09-12', '2026-09-13'], r.ngay);
teq('🔴 liệt kê ĐỦ ba cơ sở, kể cả cơ sở không có đồng nào', 3, r.hang.length);

/* ⚠️ TRA CÓ BẢO VỆ. Bản đầu tra thẳng `hang('BỆNH VIỆN 175').so` — cơ sở ấy rơi khỏi bảng
   là bài NỔ giữa chừng thay vì báo đỏ tử tế, và một bài nổ thì mọi phép SAU nó không chạy nữa.
   Phá thử bắt đúng chuyện đó: bỏ danh mục cơ sở ra khỏi bảng làm bài nổ, mà đếm dòng "✗" thì
   ra 0 — trông y hệt bài xanh. */
const theoTen = {};
r.hang.forEach(function (g) { theoTen[g.coso] = g; });
function hang(ten) { return theoTen[ten] || { so: null, tong: null, maKH: null, soGhe: null, _thieu: ten }; }
teq('AEON TÂN PHÚ: 11/09 gộp cả hai ghế', 800, hang('AEON TÂN PHÚ').so[0]);
teq('12/09 không ai thu → 0', 0, hang('AEON TÂN PHÚ').so[1]);
teq('13/09 = 70', 70, hang('AEON TÂN PHÚ').so[2]);
teq('và cột Tổng của hàng = 870', 870, hang('AEON TÂN PHÚ').tong);
teq('🔴 BỆNH VIỆN 175 có mặt với toàn số 0', [0, 0, 0], hang('BỆNH VIỆN 175').so);
teq('mã KH đi theo cơ sở', 'KH00118', hang('BỆNH VIỆN 175').maKH);
teq('số ghế đếm từ danh mục, không từ dữ liệu', 1, hang('BỆNH VIỆN 175').soGhe);

/* 🔴 HÀNG TỔNG VÀ CỘT TỔNG PHẢI KHỚP. Hai phép cộng khác chiều trên cùng một mảng số — lệch
   nhau là hai chỗ trong cùng một bảng nói hai điều, mà cả hai đều trông đúng. */
teq('🔴 tổng theo cột', [800, 0, 160], r.tongCot);
const tongHang = r.hang.reduce(function (a, g) { return a + g.tong; }, 0);
teq('🔴 cộng ngang = cộng dọc = tổng chung', [960, 960], [tongHang, r.tong]);
teq('tổng số ghế cả chuỗi', 4, r.soGhe);

/* ---------- 2. TỪNG GHẾ ---------- */
r = goi(ca('ghe', 'tong'));
teq('🔴 theo ghế: đủ 4 ghế trong danh mục', 4, r.hang.length);
const ghe = {};
r.hang.forEach(function (g) { ghe[g.maGhe] = g; });
function gheCua(ma) { return ghe[ma] || { so: null, tenGhe: null, _thieu: ma }; }
teq('ghế 80001 đúng số từng ngày', [300, 0, 70], gheCua('80001').so);
teq('ghế 80002 chỉ có 11/09', [500, 0, 0], gheCua('80002').so);
teq('ghế của cơ sở không thu vẫn có dòng', [0, 0, 0], gheCua('80099').so);
teq('kèm tên ghế để đọc, không chỉ mã', 'AM-TP-1', gheCua('80001').tenGhe);
teq('🔴 gộp theo ghế ra ĐÚNG cùng tổng với gộp theo cơ sở', 960, r.tong);

/* ---------- 3. ĐỔI CỘT SANG QR ---------- */
r = goi(ca('ghe', 'qr'));
const gq = {};
r.hang.forEach(function (g) { gq[g.maGhe] = g; });
function gqCua(ma) { return gq[ma] || { so: null, _thieu: ma }; }
teq('🔴 cột QR lấy đúng trường qr', [100, 0, 0], gqCua('80001').so);
teq('ghế trả toàn QR', [500, 0, 0], gqCua('80002').so);
teq('tổng QR cả chuỗi', 640, r.tong);

r = goi(ca('coso', 'tien_mat'));
teq('🔴 cột tiền mặt lấy đúng trường tien_mat', 320, r.tong);   // 200 + 0 + 70 + 50
/* 🔴 ĐỐI CHỨNG CỘNG CHÉO: QR + tiền mặt phải bằng đúng TỔNG. Đây là phép bắt được ca một trong
   ba cột đọc nhầm trường — mỗi cột riêng lẻ vẫn ra số trông hợp lý, chỉ phép cộng chéo mới lộ. */
teq('🔴 QR + tiền mặt = tổng', 960, 640 + 320);

/* ---------- 4. TRẦN SỐ NGÀY ---------- */
/* Mỗi ngày một cột; cả năm là 365 cột × 300 ghế — không trình duyệt nào dựng nổi. Phải CHỐI và
   nói rõ, chứ không phải để nó treo máy rồi người dùng tưởng web hỏng. */
r = goi({ tu: '2026-01-01', den: '2026-12-31', muc: 'coso', cot: 'tong', rows: ROWS, coso: COSO, may: MAY });
t('🔴 khoảng quá rộng thì CHỐI, không treo máy', !r.ok, r);
t('và nói rõ chọn tối đa bao nhiêu ngày',
	/quá rộng/.test(String(r.error)) && /92/.test(String(r.error)), r.error);

/* Đảo ngược từ/đến thì tự sửa, đừng trả bảng rỗng — người ta gõ nhầm thứ tự là chuyện thường. */
r = goi({ tu: '2026-09-13', den: '2026-09-11', muc: 'coso', cot: 'tong', rows: ROWS, coso: COSO, may: MAY });
t('gõ ngược từ/đến thì tự đảo lại', !!r.ok && r.ngay.length === 3, r);

/* ---------- 5. GHẾ ĐÃ XOÁ KHỎI DANH MỤC MÀ CÓ TIỀN ---------- */
/* Tiền của nó đã vào sổ thật. Bỏ khỏi bảng là bảng cộng thiếu mà không ai biết vì sao. */
r = goi({ tu: '2026-09-11', den: '2026-09-13', muc: 'ghe', cot: 'tong',
	rows: ROWS.concat([{ ngay: '2026-09-12', ma_may: '80777', ten: 'GHE-CU', tong: 40, qr: 0, tien_mat: 40, coso: 'AEON TÂN PHÚ' }]),
	coso: COSO, may: MAY });
const co777 = r.hang.some(function (g) { return g.maGhe === '80777'; });
t('🔴 ghế không còn trong danh mục mà CÓ tiền vẫn phải hiện', co777, r.hang.map(function (g) { return g.maGhe; }));
teq('và tiền của nó vào tổng', 1000, r.tong);

/* ---------- 6. GIAO DIỆN ---------- */
const tr = fs.readFileSync('vhcp-ghe/includes/class-vhg-trang.php', 'utf8');
t('có tab "Báo cáo tổng" trong nhóm Kế toán', tr.indexOf("'kt-bctong'") > 0);
t('cổng API nhận việc kt_bctong', tr.indexOf("'kt_bctong' === $viec") > 0);
/* 🔴 Ba mươi cột ngày thì cuộn tới giữa bảng là tên cơ sở trôi khỏi màn — người đọc đang nhìn
   một dãy số không biết của ai. Đó không phải bất tiện, đó là đọc sai sổ. */
t('🔴 cột tên cơ sở DÍNH khi cuộn ngang',
	/\.bct td\.bct-dinh[^{]*\{[^}]*position:sticky/.test(tr), null);
t('hàng TỔNG dính đáy khi cuộn dọc',
	/\.bct tr\.bct-tong td\{[^}]*position:sticky/.test(tr), null);
t('tiêu đề cột có thứ trong tuần (kế toán soi cuối tuần khác ngày thường)',
	tr.indexOf('function bctThu(') > 0 && tr.indexOf("'CN','T2','T3','T4','T5','T6','T7'") > 0);
/* Số 0 hiện dấu gạch: bảng ba mươi cột toàn "0" thì mắt không tìm ra chỗ CÓ tiền. */
t('ô bằng 0 hiện dấu gạch mờ, không hiện số 0', /\(v \? ktVnd\(v\) : '<span class="mut">–<\/span>'\)/.test(tr));
t('có nút xuất .csv để ghép sổ ngoài', tr.indexOf('function bctXuat(') > 0);
/* BOM: thiếu nó thì Excel tiếng Việt mở ra vỡ hết dấu — mà tên cơ sở thì toàn dấu. */
t('🔴 .csv có BOM cho Excel tiếng Việt', tr.indexOf("ufeff' + dong.join") > 0);
/* Xuất từ CHÍNH dữ liệu đang hiện, không gọi lại máy chủ — gọi lại là có ngày tệp tải về khác
   cái đang nhìn trên màn. */
t('xuất từ dữ liệu đang hiện, không gọi lại máy chủ',
	/function bctXuat\(\)\{[\s\S]{0,200}var r = BCT_DATA;/.test(tr));

/* ---------- 6b. BẢNG CHÉO Ở MÀN "DOANH THU ĐỊA ĐIỂM": GHẾ THEO HÀNG ----------
   Anh Thắng 01/09/2026: *"ngược rồi, đảo lại"*. Bảng ấy vốn để ngày theo hàng — sai với mẫu
   Excel, và sai cả cách đọc: người ta soi MỘT GHẾ chạy qua thời gian, chứ không soi một ngày
   cắt ngang ba trăm ghế. Để ngày theo hàng thì một ghế bị xé thành 365 ô rải khắp chiều dọc.

   ⚠️ CANH CHIỀU BẢNG, KHÔNG CANH TÊN KHỐI. Đổi tiêu đề mà quên đảo mã (hoặc ngược lại) thì
      nhãn nói một đằng bảng làm một nẻo — nên canh cả hai, và canh vào vòng lặp dựng thân. */
const iKcg = tr.indexOf('function kcgLoad(');
const jKcg = tr.indexOf('function klsLoad(', iKcg);
t('bốc được khối bảng chéo', iKcg > 0 && jKcg > iKcg);
const kcg = (iKcg > 0 && jKcg > iKcg) ? tr.slice(iKcg, jKcg) : '';
t('🔴 thân bảng lặp theo GHẾ (mỗi ghế một hàng)', /var body = r\.ghe\.map\(/.test(kcg), kcg.slice(0, 200));
t('🔴 tiêu đề cột lặp theo NGÀY', /r\.ngay\.map\(function\(N\)\{?[\s\S]{0,80}bct-ng/.test(kcg), null);
t('không còn dựng ngược (ngày theo hàng)', kcg.indexOf('var body = r.ngay.map(') < 0, null);
t('tiêu đề khối nói đúng chiều',
	tr.indexOf('Bảng chéo Ghế × Ngày') > 0 && tr.indexOf('Bảng chéo Ngày × Ghế') < 0, null);
/* Đảo chiều mà giữ nguyên hai cột mỗi ghế là mỗi NGÀY hai cột — 365×2 = 730 cột, không bảng nào
   dựng nổi. Chỉ số gộp thành MỘT cột "đầu→cuối" đứng đầu hàng. */
t('🔴 chỉ số gộp thành một cột đầu→cuối, không nhân đôi mọi cột ngày',
	kcg.indexOf("L('Chỉ số đầu→cuối','Meter start→end')") > 0
	&& kcg.indexOf("L('Chỉ số','Meter') + '</th><th class=\"r\">Actual") < 0, null);
t('có hàng TỔNG theo từng ngày và cột Cộng theo từng ghế',
	/tongNgay\[i\] \+= v/.test(kcg) && /cong \+= v/.test(kcg), null);
t('và dùng lại khung cuộn + cột dính như báo cáo tổng',
	kcg.indexOf("ktEl('table','bct')") > 0 && kcg.indexOf('bct-dinh') > 0, null);

/* ---------- 6c. CHỈ SỐ MÁY DƯỚI SỐ TIỀN, LỆCH THÌ ĐỎ ----------
   Anh Thắng 01/09/2026: *"chèn theo ngày, chỉ số vào phía dưới số tiền, chỗ nào lệch chỉ số thì
   hiện đỏ"*. "Lệch" = chỉ số chạy LÙI: máy đếm chỉ tăng, nên nhỏ hơn ngày trước nghĩa là máy bị
   thay/reset hoặc gõ nhầm — và cả hai đều KHÔNG lộ ra ở cột tiền. */
/* ⚠️ CANH ĐIỀU KIỆN DỰNG, KHÔNG CANH CHUỖI CÓ MẶT. Bản đầu chỉ hỏi `class="kcg-cs` có trong
   khối hay không — cho `duoi` bằng rỗng vô điều kiện thì nhánh dựng thành mã chết mà chuỗi vẫn
   nằm đó, bài vẫn xanh. Phá thử bắt đúng chỗ ấy. Nay canh: `duoi` chỉ rỗng khi KHÔNG có chỉ số,
   và ô thì phải thật sự nối `duoi` vào sau số tiền. */
t('🔴 mỗi ô có chỉ số in dưới số tiền',
	/var duoi = \(cs === null\) \? '' :/.test(kcg) && /class="kcg-cs/.test(kcg), null);
t('🔴 và ô nối chỉ số vào SAU số tiền', /\+ tien \+ duoi \+ '<\/td>'/.test(kcg), null);
t('🔴 dò chỉ số CHẠY LÙI', /var lech = \(cs !== null && csTruoc !== null && cs < csTruoc\);/.test(kcg), null);
/* ⚠️ So với ngày CÓ DỮ LIỆU gần nhất, không so ô liền kề: ghế nghỉ ba ngày rồi chạy lại thì ô
   liền kề trống, so với nó là mọi ghế nghỉ đều hoá "lệch". */
t('🔴 mốc so là ngày có dữ liệu gần nhất, không phải ô liền kề',
	/if \(cs !== null\) \{[^}]*csTruoc = cs;/.test(kcg), null);
t('lệch thì tô đỏ CẢ Ô, không chỉ con số nhỏ',
	/kcg-lech/.test(kcg) && /td\.kcg-lech\{[^}]*background/.test(tr), null);
t('và có luật CSS cho chỉ số nhỏ + màu đỏ',
	/\.kcg-cs\{[^}]*font-size/.test(tr) && /\.kcg-cs\.lech\{[^}]*var\(--red\)/.test(tr), null);

/* ---------- 6d. GỘP BIỂU ĐỒ THÁNG VÀO CHÍNH DANH SÁCH THÁNG ----------
   Anh Thắng: *"gộp 2 bảng theo tháng chung luôn"*. Trước đó màn có HAI khối kể cùng một chuyện —
   biểu đồ "Doanh thu theo tháng" và ngay dưới là danh sách thẻ tháng cũng ghi tổng · TM · QR. */
const iKls = tr.indexOf('function klsLoad(');
const jKls = tr.indexOf('function klsThang(', iKls);
const kls = (iKls > 0 && jKls > iKls) ? tr.slice(iKls, jKls) : '';
t('bốc được khối nạp danh sách tháng', '' !== kls);
t('🔴 KHÔNG còn thẻ biểu đồ tháng riêng', kls.indexOf('bdCotStack(') < 0, kls.slice(-300));
const iKt = tr.indexOf('function klsThang(');
const jKt = tr.indexOf('function klsBody(', iKt);
const klsT = (iKt > 0 && jKt > iKt) ? tr.slice(iKt, jKt) : '';
t('🔴 thanh tỉ lệ nằm TRONG dòng tiêu đề tháng', /kls-thanh/.test(klsT) && /head\.appendChild\(tr\)/.test(klsT), null);
/* Mốc so phải là tháng CAO NHẤT trong năm — mỗi thẻ tự tính riêng thì thanh nào cũng đầy và
   hết ý nghĩa so sánh. */
t('🔴 mốc so là tháng cao nhất, truyền từ ngoài vào',
	/function klsThang\(T, dinh\)/.test(klsT) && /Number\(dinh\) \|\| tong/.test(klsT), null);
t('và bên gọi có tính đỉnh ấy', /var dinh = r\.thang\.reduce\(/.test(kls), null);
t('vẫn giữ đủ hai màu tiền mặt / QR như biểu đồ cũ',
	/var\(--green\)/.test(klsT) && /var\(--blue\)/.test(klsT), null);

/* ---------- 7. MÃ KH: khai được, và KHÔNG bị xoá khi sửa việc khác ---------- */
const may_php = fs.readFileSync('vhcp-ghe/includes/class-vhg-may.php', 'utf8');
t('luu_coso() nhận ma_kh', /function luu_coso\( \$id, \$ten, \$tinh = null, \$ma_kh = null \)/.test(may_php));
/* 🔴 `null` KHÁC rỗng: mọi chỗ gọi cũ (đổi tên cơ sở, thêm cơ sở lúc gán ghế) đều không truyền
   ma_kh — coi thiếu tham số là "đặt về rỗng" thì mỗi lần sửa tên là mã KH bị xoá, im lặng. */
t('🔴 không truyền ma_kh thì GIỮ NGUYÊN mã cũ, không xoá',
	/\$co_makh = \( null !== \$ma_kh \);/.test(may_php) && /if \( \$co_makh \) \{ \$data\['ma_kh'\] = \$ma_kh; \}/.test(may_php));
const db_php = fs.readFileSync('vhcp-ghe/includes/class-vhg-db.php', 'utf8');
t('cột ma_kh có trong CREATE TABLE', /ma_kh VARCHAR\(40\)/.test(db_php));
t('🔴 và có migration cho sổ ĐANG CHẠY (không thì cột không bao giờ lên)',
	/ADD COLUMN ma_kh VARCHAR\(40\)/.test(db_php));

/* ---------- KẾT ---------- */
try { fs.rmSync(tmp, { recursive: true, force: true }); } catch (e) {}
if (TRUOT.length) {
	console.log('\n✗ HỎNG ' + TRUOT.length + ' phép:');
	TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
	process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: báo cáo tổng cộng đúng cả hai chiều, không rơi cơ sở nào.');
