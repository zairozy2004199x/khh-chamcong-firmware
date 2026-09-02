/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG RỘNG PHẢI CUỘN ĐƯỢC — LỚP `.table-scroll` PHẢI CÓ LUẬT CSS THẬT
 *
 * Anh Thắng 01/09/2026, ảnh màn "Doanh thu địa điểm" của kế toán POSH: bảng "Theo ghế" cụt mất
 * cột TỔNG ở mép phải.
 *
 * 🔴 GỐC: lớp `.table-scroll` được dùng ở NĂM bảng, mỗi bảng còn tự đặt `min-width` 480–620px
 *    cho khỏi bị bóp cột — nhưng luật CSS cho chính lớp ấy CHƯA BAO GIỜ TỒN TẠI. Không có
 *    `overflow-x` thì `min-width` không sinh ra thanh cuộn, nó chỉ đẩy bảng tràn ra ngoài thẻ và
 *    phần thừa bị cắt.
 *
 * ⚠️ LOẠI HỎNG KHÔNG KÊU TIẾNG NÀO. Bảng vẫn vẽ đủ, số vẫn đúng, chỉ có cột cuối nằm ngoài màn —
 *    mà cột cuối của bảng tiền thường là cột TỔNG. Người đọc không thấy thiếu; họ thấy một bảng
 *    bình thường. Đúng loại lỗi mà chỉ có phép thử mới bắt được, vì mắt không bắt được.
 *
 * ⚠️ BÀI NÀY DÒ CẢ TỆP, KHÔNG CHỈ CANH MỘT TÊN. Canh mỗi `.table-scroll` thì lần sau ai đó dựng
 *    `.abc-scroll` mới lại rơi vào đúng cái bẫy này. Nên: MỌI lớp có tên kết thúc bằng "-scroll"
 *    (hoặc chính là "scroll") mà được dùng trong HTML đều phải có luật `overflow` đi kèm.
 *
 * Chạy: node tools/test/kiem-bang-cuon.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const NGUON = 'vhcp-ghe/includes/class-vhg-trang.php';
const src = fs.readFileSync(NGUON, 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }

/* ---------- 1. `.table-scroll` phải có luật, và luật phải CHO CUỘN ---------- */
/* Cắt riêng khối luật của lớp này ra rồi soi: `overflow-x` xuất hiện ở cả chục chỗ khác trong
   tệp (khối chat, ô chọn cơ sở…), soi trần trụi cả tệp là xanh nhờ một luật chẳng liên quan. */
function luatCua(lop) {
	const re = new RegExp('\\.' + lop.replace('-', '\\-') + '\\s*(?:[,>][^{]*)?\\{([^}]*)\\}', 'g');
	let ra = ''; let m;
	while ((m = re.exec(src)) !== null) { ra += m[1] + ';'; }
	return ra;
}
const luat_ts = luatCua('table-scroll');
t('🔴 lớp .table-scroll CÓ luật CSS (trước 01/09/2026 thì không có dòng nào)',
	'' !== luat_ts, luat_ts);
t('🔴 và luật ấy cho CUỘN NGANG',
	/overflow-x\s*:\s*auto/.test(luat_ts), luat_ts);
/* Chỉ `overflow-x` thôi thì trên khung hẹp (sidebar chiếm chỗ) thẻ con vẫn nở theo bảng bên
   trong và đẩy cả trang trôi ngang — cuộn được nhưng cuộn cả màn, không phải cuộn cái bảng. */
t('🔴 kèm max-width:100% để vùng cuộn không đẩy cả trang trôi ngang',
	/max-width\s*:\s*100%/.test(luat_ts), luat_ts);

/* ---------- 2. Bảng nào đặt min-width thì phải NẰM TRONG một vùng cuộn ---------- */
/* Đặt `min-width` mà không có khung cuộn bọc ngoài chính là công thức sinh ra lỗi này. */
const co_minwidth = (src.match(/\.style\.minWidth\s*=/g) || []).length;
t('có bảng đặt minWidth (nếu 0 thì phép dưới vô nghĩa)', co_minwidth > 0, co_minwidth);
const so_dung_ts = (src.match(/'table-scroll'/g) || []).length;
t('và chúng dùng khung .table-scroll', so_dung_ts >= co_minwidth - 1,
	{ minWidth: co_minwidth, tableScroll: so_dung_ts });

/* ---------- 3. LUẬT CHUNG: mọi lớp "-scroll" phải cho cuộn ---------- */
/* 🔴 Đây mới là phép giữ được lâu. Canh mỗi cái tên hôm nay thì lần sau ai đó dựng một lớp cuộn
   mới lại rơi vào đúng bẫy cũ, và bài kiểm này ngồi nhìn. */
const dung = new Set();
let m;
const reKt = /ktEl\(\s*'[a-z]+'\s*,\s*'([a-z0-9\- ]+)'/g;
while ((m = reKt.exec(src)) !== null) { m[1].split(' ').forEach(function (x) { if (x) dung.add(x); }); }
const reCls = /class="([a-z0-9\- ]+)"/g;
while ((m = reCls.exec(src)) !== null) { m[1].split(' ').forEach(function (x) { if (x) dung.add(x); }); }

const lop_cuon = Array.from(dung).filter(function (x) { return /(^|-)scroll$/.test(x); });
t('tìm được ít nhất một lớp tên "…-scroll"', lop_cuon.length > 0, lop_cuon);
const hong = lop_cuon.filter(function (x) { return ! /overflow/.test(luatCua(x)); });
t('🔴 MỌI lớp "…-scroll" đang dùng đều có luật overflow', 0 === hong.length, hong);

/* ---------- 4. Đối chứng: bài này thật sự đọc được luật CSS ----------
   Nếu `luatCua()` hỏng (đổi cách viết CSS, gộp dòng…) thì mọi phép trên xanh vì tưởng "không có
   lớp nào hỏng" — trong khi thật ra nó không đọc được gì. Neo vào một lớp chắc chắn có luật. */
t('đối chứng: đọc được luật của .card (nếu đỏ thì bài đang không đọc nổi CSS)',
	/border-radius/.test(luatCua('card')), luatCua('card').slice(0, 120));

/* ---------- KẾT ---------- */
if (TRUOT.length) {
	console.log('\n✗ HỎNG ' + TRUOT.length + ' phép:');
	TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
	process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: bảng rộng cuộn được, không cụt cột ở mép phải.');
