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
	/var duoi = \(!coCs \|\| cs === null\) \? '' :/.test(kcg) && /class="kcg-cs/.test(kcg), null);
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

/* ---------- 6d. KHỐI THEO THÁNG: MỘT KHỐI, VÀ KHÔNG CÓ THANH TỈ LỆ ----------
   Anh Thắng 01/09/2026: *"gộp 2 bảng theo tháng chung luôn"* — trước đó màn có HAI khối kể cùng
   một chuyện: biểu đồ "Doanh thu theo tháng" và ngay dưới là danh sách thẻ tháng cũng ghi
   tổng · TM · QR. Bản gộp kéo thanh tỉ lệ vào dòng tiêu đề từng tháng.

   Rồi 02/09/2026, ảnh GALAXY KINH DƯƠNG VƯƠNG (cơ sở hai ghế): *"bỏ cái này đi"*. Dòng tiêu đề
   đã chật (tháng · số ghế · số ngày · tổng · TM · QR) nên phần chừa cho thanh chỉ còn vài chục
   pixel — ra một vạch xanh li ti không đọc được gì.

   🔴 HAI CHỐT NGƯỢC CHIỀU NHAU, PHẢI GIỮ CẢ HAI. Bỏ thanh mà lỡ tay dựng lại khối biểu đồ tháng
      riêng là quay về đúng chỗ 01/09 đã gộp; còn gộp lại mà kéo thanh vào là quay về chỗ 02/09
      đã bỏ. Nên canh cả "không có khối riêng" lẫn "không có thanh trong dòng tiêu đề". */
const iKls = tr.indexOf('function klsLoad(');
const jKls = tr.indexOf('function klsThang(', iKls);
const kls = (iKls > 0 && jKls > iKls) ? tr.slice(iKls, jKls) : '';
t('bốc được khối nạp danh sách tháng', '' !== kls);
t('🔴 KHÔNG còn thẻ biểu đồ tháng riêng', kls.indexOf('bdCotStack(') < 0, kls.slice(-300));
const iKt = tr.indexOf('function klsThang(');
const jKt = tr.indexOf('function klsBody(', iKt);
const klsT = (iKt > 0 && jKt > iKt) ? tr.slice(iKt, jKt) : '';
t('bốc được khối dựng một thẻ tháng', '' !== klsT);
t('🔴 KHÔNG còn thanh tỉ lệ trong dòng tiêu đề tháng',
	klsT.indexOf('bd-track') < 0 && tr.indexOf('kls-thanh') < 0, klsT.slice(0, 300));
/* Bỏ thanh thì mốc so ("tháng cao nhất trong năm") cũng hết việc — để lại là một phép tính chạy
   mỗi lần nạp mà không ai dùng, và là mồi để ai đó dựng lại thanh. */
t('🔴 và bên gọi không còn tính đỉnh cho thanh ấy',
	/box\.appendChild\(klsThang\(T\)\)/.test(kls) && kls.indexOf('.reduce(') < 0, kls.slice(-200));
t('🔴 khai hàm cũng chỉ còn một tham số', /function klsThang\(T\)\{/.test(tr.slice(iKt, iKt + 40)), null);
/* Nhưng SỐ thì vẫn phải đủ — thứ anh Thắng đối chiếu là chữ số, không phải hình. */
t('dòng tiêu đề vẫn ghi đủ tổng · tiền mặt · QR',
	/ktVnd\(T\.tong\)/.test(klsT) && /ktVnd\(T\.tien_mat\)/.test(klsT) && /ktVnd\(T\.qr\)/.test(klsT), null);
/* Hình theo NGÀY nằm trong thân thẻ (mở tháng ra mới thấy) — chỗ ấy có cả chiều rộng để vẽ, nên
   nó KHÔNG bị bỏ theo. */
const iBody = tr.indexOf('function klsBody(');
const jBody = tr.indexOf('function veKtTien(', iBody);
const klsB = (iBody > 0 && jBody > iBody) ? tr.slice(iBody, jBody) : '';
t('biểu đồ theo NGÀY trong thân thẻ tháng vẫn còn', klsB.indexOf('bdCotStack(') > 0, null);

/* ---------- 6e. BẢNG CHÉO THỨ HAI: TIỀN QR ----------
   Anh Thắng 02/09/2026, ảnh chính bảng chéo Ghế × Ngày: *"thêm bảng này theo tiền QR"*.

   🔴 HAI BẢNG PHẢI DỰNG TỪ CÙNG MỘT LẦN GỌI. Kế toán lấy Thực thu trừ QR ra phần tiền mặt phải
      nộp; hai truy vấn riêng là hai bộ ngày/ghế có thể lệch nhau (một dòng được sửa giữa hai
      lần gọi), và trừ nhầm cả cột mà không có gì báo. */
/* ⚠️ BỐC RIÊNG THÂN `bang_cheo()` RA SOI. Soi trần trụi cả tệp thì phép dưới xanh nhờ một chỗ
   chẳng liên quan: `duyet_rows()` cũng có đúng dòng `'qr' => (int) $r['qr'],`. Phá thử bắt được
   chỗ mù ấy — đổi tên khoá trong bang_cheo mà bài vẫn xanh. */
const iBC = src.indexOf('public static function bang_cheo(');
const jBC = src.indexOf('BÁO CÁO TỔNG', iBC);
t('bốc được thân bang_cheo()', iBC > 0 && jBC > iBC);
const bc_php = (iBC > 0 && jBC > iBC) ? src.slice(iBC, jBC) : '';
t('🔴 máy chủ trả kèm cột qr trong bảng chéo',
	/SELECT d\.ngay, d\.ma_may, d\.ten, d\.chi_so_sau, d\.actual, d\.qr/.test(bc_php)
	&& /'qr' => \(int\) \$r\['qr'\],/.test(bc_php), null);
t('🔴 chỉ MỘT lần gọi kt_bangcheo cho cả hai bảng',
	1 === (kcg.match(/goi\('kt_bangcheo'/g) || []).length, (kcg.match(/goi\('kt_bangcheo'/g) || []).length);
t('🔴 dựng hai bảng: thực thu rồi tiền QR',
	/kcgBang\(r, 'actual', true\)/.test(kcg) && /kcgBang\(r, 'qr', false\)/.test(kcg), null);
/* ⚠️ CANH CHỖ LẤY SỐ, KHÔNG CANH TÊN THAM SỐ. Nhận `khoa` rồi bên trong vẫn đọc cứng `o.actual`
   thì bảng QR in ra y hệt bảng trên — hai bảng giống nhau như đúc, và trông rất thuyết phục. */
t('🔴 ô lấy số theo khoá truyền vào, không đọc cứng actual',
	/var v = o \? Number\(o\[khoa\]\) \|\| 0 : 0;/.test(kcg) && kcg.indexOf('Number(o.actual)') < 0, null);
t('nhãn hai bảng nói rõ bảng nào là bảng nào',
	kcg.indexOf("L('Thực thu theo ngày (tiền mặt + QR)'") > 0 && kcg.indexOf("L('Tiền QR theo ngày'") > 0, null);
/* 🔴 SỐ CỘT PHẢI KHỚP NHAU BA CHỖ. Bảng QR bỏ cột "Chỉ số đầu→cuối"; quên bỏ ở một trong ba
   (tiêu đề / thân / hàng TỔNG) là bảng lệch một cột — mọi con số của hàng TỔNG trượt sang ngày
   bên cạnh, và trình duyệt không kêu tiếng nào. */
const bang = kcg.slice(kcg.indexOf('function kcgBang('));
t('🔴 cột chỉ số có/không theo coCs ở TIÊU ĐỀ',
	/\(coCs \? \('<th>' \+ L\('Chỉ số đầu→cuối'/.test(bang), null);
t('🔴 …ở THÂN bảng', /\(coCs \? \('<td>' \+ cs \+ '<\/td>'\) : ''\)/.test(bang), null);
t('🔴 …và ở hàng TỔNG', /\+ \(coCs \? '<td><\/td>' : ''\)/.test(bang), null);
/* Chỉ số là mốc đối chiếu của THỰC THU, không phải của QR: in lại dưới ô QR là cùng một con số
   nằm hai chỗ, người đọc phải dừng lại nghĩ xem hai cái có khác nhau không. */
t('🔴 bảng QR không in chỉ số dưới số tiền', /var duoi = \(!coCs \|\| cs === null\) \? '' :/.test(bang), null);
t('và cũng không tô đỏ ô theo chỉ số', /\(coCs && lech \? ' kcg-lech' : ''\)/.test(bang), null);
/* Bảng QR rộng y như bảng trên nên cũng phải nằm trong khung cuộn, không thì cụt cột Cộng. */
t('bảng QR cũng nằm trong khung cuộn (dựng khung rồi TRẢ chính khung ấy)',
	/var sc = ktEl\('div','table-scroll'\);/.test(bang) && /sc\.appendChild\(t\);\s*\n\s*return sc;/.test(bang), null);

/* ---------- 6f. LỌC THEO THÁNG CHO BẢNG CHÉO ----------
   Anh Thắng 02/09/2026: *"bổ sung lọc theo tháng"*, kèm ảnh bảng cả năm — 365 cột, muốn nhìn
   một ngày giữa tháng bảy phải kéo ngang gần hết bảng.

   🔴 LỌC PHẢI Ở TRUY VẤN, KHÔNG Ở TRANG. Cắt bớt cột sau khi đã tải cả năm về thì vẫn kéo cả
      năm dữ liệu qua đường truyền và vẫn dựng cả năm trong trí nhớ — chỉ đỡ mỏi mắt, không đỡ
      chậm. */
t('bang_cheo() nhận thêm tham số tháng, mặc định rỗng',
	/public static function bang_cheo\( \$coso, \$nam, \$thang = '' \) \{/.test(bc_php), null);
t('🔴 truy vấn đổi ĐỊNH DẠNG và MỐC theo tháng, không còn cắm cứng %Y',
	/\$dinh_dang = \( '' !== \$thang \) \? '%Y-%m' : '%Y';/.test(bc_php)
	&& /\$moc = \( '' !== \$thang \) \? \( \$nam \. '-' \. \$thang \) : \$nam;/.test(bc_php)
	&& /\$ck, \$dinh_dang, \$moc \), ARRAY_A \);/.test(bc_php)
	&& bc_php.indexOf("$ck, '%Y', $nam ), ARRAY_A );") < 0, null);
t('và trả về tháng đang xem để trang nói đúng khoảng', /'thang' => \$thang,/.test(bc_php), null);
t('cổng chuyển tiếp tham số thang xuống', /bang_cheo\([\s\S]{0,140}\$d\['thang'\] : ''/.test(tr), null);

/* ⚠️ BỐC CHÍNH KHUÔN LỌC TỪ NGUỒN RA CHẠY, không chép lại một khuôn giống giống. Bản chép sẽ
   xanh mãi kể cả khi khuôn thật đã đổi — mà đây là chỗ chuỗi của người dùng đi thẳng vào một
   câu SQL, nên "giống giống" không đủ. */
const mKhuon = bc_php.match(/\$thang = preg_match\( '\/\^([^']+)\$\/', \(string\) \$thang \) \? \(string\) \$thang : ('[^']*');/);
t('bốc được khuôn lọc tháng từ nguồn', !!mKhuon, mKhuon && mKhuon[1]);
if (mKhuon) {
	const re = new RegExp('^' + mKhuon[1] + '$');
	['01','02','06','09','10','11','12'].forEach(function (x) {
		t('tháng hợp lệ "' + x + '" lọt qua khuôn', re.test(x), x);
	});
	/* 🔴 Hai chỗ dễ sai nhất: "1" (thiếu số 0) và "00"/"13" — cả ba đều không phải tháng, mà cả
	   ba đều trông như số. Lọt một cái là DATE_FORMAT so với "2026-1" và bảng ra rỗng, im lặng. */
	['', '0', '1', '00', '13', '99', '1;DROP', '0a', ' 01', '012'].forEach(function (x) {
		t('🔴 chuỗi không phải tháng "' + x + '" bị chặn', !re.test(x), x);
	});
	/* 🔴 THÁNG SAI PHẢI VỀ CẢ NĂM, KHÔNG VỀ THÁNG 1. Ô chọn gửi lên chuỗi rỗng cho "cả năm";
	   nhận nhầm thành '01' là kế toán tưởng đang xem cả năm mà thật ra chỉ thấy tháng giêng. */
	t("🔴 tháng sai/rỗng thì rơi về CẢ NĂM (''), không về '01'", "''" === mKhuon[2], mKhuon[2]);
}

/* ---------- 6g. Ô LỌC THÁNG TRÊN TRANG ---------- */
t('có ô chọn tháng riêng ở khối bảng chéo', tr.indexOf("id=\"kcg-thang\"") > 0, null);
t('🔴 mặc định là CẢ NĂM', /var KCG_THANG = '';/.test(tr), null);
t('🔴 đổi tháng thì nạp lại ngay, không phải bấm Xem',
	/mt\.onchange = function\(\)\{ KCG_THANG = mt\.value; kcgLoad\(\); \};/.test(tr), null);
t('🔴 và tháng được GỬI LÊN máy chủ',
	/goi\('kt_bangcheo', \{ coso: KLS_COSO, nam: KLS_NAM, thang: KCG_THANG \}/.test(kcg), null);
/* Một bảng ba mươi mốt cột trông y hệt một bảng cả năm bị cuộn tới đoạn giữa — thiếu dòng nói
   rõ khoảng là kế toán không có cách nào biết mình đang nhìn gì. */
t('🔴 màn nói rõ đang xem tháng nào / cả năm',
	/var khoang = r\.thang \? \(L\('Tháng ','Month '\) \+ r\.thang \+ '\/' \+ r\.nam\) : /.test(kcg), null);
t('câu "chưa có dữ liệu" cũng nói rõ khoảng nào', /khoang \+ ' — ' \+ L\('chưa có dữ liệu\./.test(kcg), null);
t('tiêu đề khối không còn hứa "cả năm"',
	tr.indexOf("L('Bảng chéo Ghế × Ngày','Chair × day grid')") > 0
	&& tr.indexOf('Bảng chéo Ghế × Ngày (cả năm)') < 0, null);

/* ⚠️ BỐC HÀM DỰNG Ô CHỌN RA CHẠY THẬT, không đếm chữ "option" trong tệp. */
const iOpt = tr.indexOf('function kcgOptThang(');
const jOpt = tr.indexOf('function klsInit(', iOpt);
t('bốc được hàm dựng ô chọn tháng', iOpt > 0 && jOpt > iOpt);
if (iOpt > 0 && jOpt > iOpt) {
	const fn = new Function('L', 'KCG_THANG', tr.slice(iOpt, jOpt) + '; return kcgOptThang();');
	const html = fn(function (a) { return a; }, '');
	const vals = (html.match(/value="([^"]*)"/g) || []).map(function (x) { return x.slice(7, -1); });
	teq('đủ 13 lựa chọn: cả năm + 12 tháng', 13, vals.length);
	teq('giá trị đúng dạng hai chữ số, cả năm là rỗng',
		['', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'], vals);
	/* 🔴 Mọi giá trị hàm này đẻ ra đều phải lọt qua chính khuôn lọc của máy chủ — hai bên lệch
	   nhau là ô chọn có tháng mà chọn vào thì bảng rỗng. */
	if (mKhuon) {
		const re2 = new RegExp('^' + mKhuon[1] + '$');
		const xau = vals.filter(function (v) { return '' !== v && !re2.test(v); });
		teq('🔴 ô chọn và khuôn lọc máy chủ khớp nhau', [], xau);
	}
	const html2 = fn(function (a) { return a; }, '07');
	t('tháng đang chọn được đánh dấu selected', html2.indexOf('value="07" selected') > 0, null);
}

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
