/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NHẬP GIỮA HAI NGÀY — GỢI Ý CHỈ SỐ CỦA NGÀY SAU, ĐỂ KHÔNG GÕ NHẦM SỐ CỦA NGÀY ẤY
 *
 * Anh Thắng 05/09/2026: *"nếu nhập giữa ngày, thì chỉ số sau sẽ hiện chữ gợi ý của ngày sau đó,
 * để tránh nhập nhầm lần 2. như kiểu ngày 2 cũng nhập và ngày 3 cũng nhập cái chỉ số đó"*.
 *
 * 🔴 CÁI NHẦM NÀY TỔNG THÁNG KHÔNG BẮT ĐƯỢC. Người thu tiền mở máy đọc chỉ số HÔM NAY rồi mới
 *    nhớ ra còn thiếu báo cáo hôm kia; gõ con số vừa đọc vào hàng hôm kia là hai ngày mang ĐÚNG
 *    một chỉ số. Doanh thu ngày trước bị thổi lên bằng cả phần ở giữa, ngày sau rơi về 0 — mà
 *    cộng lại thì TỔNG VẪN ĐÚNG. Đối chiếu tổng, đối chiếu tiền nộp, đối chiếu QR: không phép
 *    nào kêu. Chỉ có nhìn từng ngày mới thấy, mà không ai ngồi nhìn từng ngày.
 *
 * ⚠️ BÀI NÀY BỐC THẲNG ĐOẠN TÍNH CÂU NHẮC TỪ MÃ NGUỒN RA CHẠY, không chép lại. Bản chép sẽ xanh
 *    mãi kể cả khi mã thật đã đổi — mà chỗ này là chỗ quyết định người thu tiền có được cảnh báo
 *    hay không.
 *
 * Chạy: node tools/test/kiem-tran-ngay-sau.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const G   = 'vhcp-ghe/includes/';
const tr  = fs.readFileSync(G + 'class-vhg-trang.php', 'utf8');
const bc  = fs.readFileSync(G + 'class-vhg-baocao.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* ---------- 1. MÁY CHỦ: có hàm tra chỉ số của lần đọc KẾ TIẾP ---------- */
const iKe = bc.indexOf('public static function chi_so_ke_ct_(');
const jKe = bc.indexOf('public static function lay_chiso_truoc(', iKe);
t('bốc được hàm tra trần', iKe > 0 && jKe > iKe);
const hamKe = (iKe > 0 && jKe > iKe) ? bc.slice(iKe, jKe) : '';

/* 🔴 TRẦN LÀ HÀNG GẦN NHẤT SAU NGÀY ĐÓ, không phải hàng cuối cùng của cả sổ. Lấy hàng cuối thì
   chèn vào tháng 3 lại đem chỉ số tháng 9 ra so — câu nhắc nào cũng kêu, và kêu suốt thì người
   ta thôi đọc. */
t('🔴 chỉ nhìn ngày LỚN HƠN ngày đang nhập', /ngay > %s/.test(hamKe), hamKe.slice(0, 200));
t('🔴 và lấy hàng GẦN NHẤT (ngay ASC), không phải hàng cuối sổ',
	/ORDER BY ngay ASC/.test(hamKe) && ! /ORDER BY ngay DESC/.test(hamKe), null);
/* ⚠️ Trong cùng một ngày có thể có nhiều lần thu; mốc nối tiếp của ngày đang nhập là LẦN ĐẦU
   của ngày ấy. Lấy lần cuối là bỏ qua cả phần giữa, và trần hoá ra cao hơn thực tế. */
/* ⚠️ CANH CHUỖI SQL THẬT, KHÔNG CANH HAI CHỮ TRONG CHÚ THÍCH. Bản đầu của phép này dò `/lan
   ASC/` trần trụi — mà chính chú thích của hàm ấy nhắc lại đúng hai chữ đó, nên đổi SQL sang
   `lan DESC` bài vẫn xanh. Phá thử bắt được, và đây là lần THỨ BA trong kho này một phép thử tự
   xanh vì chú thích nhắc lại thứ nó đang canh. Nay neo vào cả mệnh đề ORDER BY. */
t('🔴 trong cùng ngày thì lấy LẦN THU ĐẦU', /ORDER BY ngay ASC, lan ASC/.test(hamKe),
	(hamKe.match(/ORDER BY[^']*/g) || []).join(' | '));
/* Hàng chưa nhập chỉ số không phải một cái trần — nó chỉ là một hàng trống. */
t('🔴 bỏ qua hàng chưa có chỉ số', /chi_so_sau IS NOT NULL/.test(hamKe), null);
t('trả về CẢ ngày, không chỉ con số (câu nhắc phải gọi tên ngày)',
	/'ngay' => \(string\) \$r\['d'\]/.test(hamKe), null);
t('không có ngày sau thì trả null, không trả 0',
	/return array\( 'cs' => null, 'ngay' => '' \);/.test(hamKe), null);

/* Ghế KHÔNG có trần thì không được có mặt trong map — bắt giao diện lọc lại là thêm một nơi
   quên lọc. */
const iMap = bc.indexOf('public static function lay_chiso_ke(');
const jMap = bc.indexOf('public static function lay_chiso_truoc(', iMap);
const hamMap = (iMap > 0 && jMap > iMap) ? bc.slice(iMap, jMap) : '';
t('bốc được hàm gom map', '' !== hamMap);
t('🔴 chỉ trả ghế CÓ trần', /if \( null !== \$x\['cs'\] \) \{ \$out\[/.test(hamMap), hamMap.slice(0, 200));

/* ---------- 2. CỔNG: trả kèm trong CÙNG lượt gọi ---------- */
/* ⚠️ Thêm một lượt gọi riêng là thêm một chỗ chờ đúng lúc bảng đang vẽ. Giao diện vốn đã chờ
   `bc_lastmeters` để có chỉ số trước, nên trần đi nhờ chuyến ấy. */
t('🔴 cổng bc_lastmeters trả kèm khoá `ke`',
	/'map' => VHG_BaoCao::lay_chiso_truoc\( \$ma_ds, \$ng_bc, ! empty\( \$d\['toi'\] \) \),\s*\n\s*'ke'\s*=> VHG_BaoCao::lay_chiso_ke\( \$ma_ds, \$ng_bc \)/.test(tr), null);
t('và giao diện nhận nó vào KE', /KE=\(r&&r\.ke\)\|\|\{\};/.test(tr), null);

/* ---------- 3. GỢI Ý NGAY TẠI Ô ĐANG GÕ ---------- */
/* 🔴 Lúc gõ, mắt người ta ở TRONG ô. Một dòng chữ đặt đâu đó trên màn không lọt vào tầm nhìn ấy,
   nên gợi ý phải là placeholder của chính ô "Chỉ số sau". */
const iVe = tr.indexOf('function veDong(g,before){');
const jVe = tr.indexOf('function tinhTong(', iVe);
const veD = (iVe > 0 && jVe > iVe) ? tr.slice(iVe, jVe) : '';
t('bốc được khối vẽ một hàng ghế', '' !== veD);
t('🔴 ô "Chỉ số sau" đổi gợi ý khi có trần',
	/inp\('after', ke \? \('phải nhỏ hơn ' \+ money\(ke\.cs\)\) : 'Chỉ số sau'\)/.test(veD), veD.slice(0, 300));
t('và có dòng nói rõ NGÀY nào đã có số ấy',
	/'Ngày ' \+ nhanNgayVn\(ke\.ngay\) \+ ' đã có ' \+ money\(ke\.cs\)/.test(veD), null);
/* Không có ngày sau (nhập ngày mới nhất — ca thường ngày) thì KHÔNG bày gì thêm: một dòng chữ
   thừa ở mọi hàng, mọi ngày, là thứ người ta học cách không nhìn. */
t('🔴 không có trần thì KHÔNG bày dòng nhắc nào', /if \(ke\) \{/.test(veD), null);
t('trần được ghim vào hàng để calc() dùng lại',
	/tr\.dataset\.keCs\s*=/.test(veD) && /tr\.dataset\.keNgay\s*=/.test(veD), null);

/* ---------- 4. LÕI: BỐC RA CHẠY THẬT ---------- */
/* Đây là phần quyết định câu nhắc nào hiện ra. Bốc thẳng từ mã nguồn rồi chạy với từng ca. */
const iN = tr.indexOf('var keCs   = tr.dataset.keCs ? Number(tr.dataset.keCs) : null;');
const jN = tr.indexOf('var elA=tr.querySelector', iN);
t('bốc được đoạn tính câu nhắc', iN > 0 && jN > iN);
const loi = (iN > 0 && jN > iN) ? tr.slice(iN, jN) : '';

/* Hai hàm giúp việc cũng bốc từ nguồn — chép tay `nhanNgayVn` ở đây thì đổi cách hiện ngày bên
   kia mà bài vẫn xanh. */
const iNg = tr.indexOf('function nhanNgayVn(d){');
const jNg = tr.indexOf('\n  }', iNg) + 4;
const hamNg = (iNg > 0 && jNg > iNg) ? tr.slice(iNg, jNg) : '';
t('bốc được hàm đổi ngày sang kiểu Việt', /function nhanNgayVn/.test(hamNg), hamNg.slice(0, 80));

function chay(keCs, keNgay, after, ngayBC) {
	const f = new Function('tr', 'after', 'NGAY', 'money',
		hamNg + '\n' + loi + '\n return nhacKe;');
	return f(
		{ dataset: { keCs: keCs === null ? '' : String(keCs), keNgay: keNgay || '' } },
		after === '' ? '' : Number(after),
		ngayBC,
		function (n) { return (Number(n) || 0).toLocaleString('vi-VN'); }
	);
}

/* 🔴 CA CỦA ANH THẮNG: ngày 05/09 đã có 4.700; nhập ngày 03/09 và gõ đúng 4.700. */
const caTrung = chay(4700, '2026-09-05', 4700, '2026-09-03');
t('🔴 gõ TRÙNG ĐÚNG chỉ số ngày sau -> có nhắc', '' !== caTrung, caTrung);
t('câu nhắc gọi tên NGÀY kia', caTrung.indexOf('05/09') >= 0, caTrung);
t('và nói ra CON SỐ', caTrung.indexOf('4.700') >= 0, caTrung);
t('và gọi tên ngày ĐANG nhập, để người gõ biết mình đang ở hàng nào',
	caTrung.indexOf('03/09') >= 0, caTrung);
/* Hỏi thẳng ra cái nhầm, chứ không nói chung chung "kiểm tra lại": người gõ phải nhận ra được
   MÌNH VỪA LÀM GÌ thì mới sửa. */
t('🔴 nói thẳng nghi vấn "gõ nhầm số vừa đọc trên máy hôm nay"',
	caTrung.indexOf('gõ nhầm số vừa đọc trên máy hôm nay') >= 0, caTrung);

/* Lớn hơn trần: máy chỉ đếm tăng, nên số của ngày trước không thể lớn hơn ngày sau. */
const caVuot = chay(4700, '2026-09-05', 4800, '2026-09-03');
t('🔴 gõ LỚN HƠN chỉ số ngày sau -> có nhắc', '' !== caVuot, caVuot);
t('và câu nhắc khác hẳn ca trùng (hai kiểu nhầm, hai cách sửa)',
	caVuot !== caTrung && caVuot.indexOf('Lớn hơn') >= 0, caVuot);
t('nói ra lý do vật lý: máy chỉ đếm tăng', caVuot.indexOf('máy chỉ đếm tăng') >= 0, caVuot);
/* ⚠️ Nhưng vẫn phải chừa cửa cho ca THẬT: máy bị thay hoặc reset thì chỉ số tụt là chuyện có
   thật. Câu nhắc nói ra điều đó, chứ không khẳng định người ta sai. */
t('🔴 chừa cửa cho ca máy vừa bị thay/reset', caVuot.indexOf('thay/reset') >= 0, caVuot);

/* Nhỏ hơn trần = đúng, không nhắc gì. Nhắc cả ca đúng là dạy người ta bỏ qua cảnh báo. */
teq('🔴 nhỏ hơn trần thì KHÔNG nhắc', '', chay(4700, '2026-09-05', 4650, '2026-09-03'));
teq('chưa gõ gì thì cũng không nhắc', '', chay(4700, '2026-09-05', '', '2026-09-03'));
/* 🔴 VÀ CHƯA GÕ GÌ KHI TRẦN BẰNG 0 CŨNG KHÔNG ĐƯỢC NHẮC. Ô trống về số là 0, nên bỏ chốt "đã gõ
   chưa" thì máy mới lắp (chỉ số ngày sau còn 0) sẽ kêu "trùng đúng chỉ số ngày sau" ở MỌI hàng
   ngay khi bảng vừa vẽ ra — cảnh báo kêu lúc chưa ai gõ gì là thứ người ta học cách bỏ qua. */
teq('🔴 trần bằng 0 mà chưa gõ gì thì vẫn im lặng', '', chay(0, '2026-09-05', '', '2026-09-03'));
/* Không có ngày sau (nhập ngày mới nhất) — ca thường ngày, tuyệt đối không được nhắc. */
teq('🔴 không có trần thì im lặng, dù gõ số nào', '', chay(null, '', 999999, '2026-09-03'));

/* ⚠️ ĐỐI CHỨNG: bài này thật sự chạy được đoạn vừa bốc. Nếu `loi` bốc hụt (đổi cách viết, dời
   khối) thì mọi phép trên xanh vì hàm trả về '' — trong khi thật ra nó chẳng chạy gì. */
t('đối chứng: đoạn bốc ra có thật sự sinh được câu nhắc',
	chay(100, '2026-09-05', 100, '2026-09-03').length > 20, null);

/* ---------- 5. CHỈ NHẮC, KHÔNG CHẶN ---------- */
/* 🔴 Máy bị thay giữa chừng thì chỉ số tụt là thật. Chặn gửi ở đây là khoá cửa đúng lúc người ta
   cần ghi lại sự cố — và họ sẽ ghi bằng cách gõ đại một con số cho qua. */
const iCalc = tr.indexOf('function calc(tr){');
const jCalc = tr.indexOf('function tinhTong(', iCalc);
const calc = (iCalc > 0 && jCalc > iCalc) ? tr.slice(iCalc, jCalc) : '';
t('bốc được khối calc', '' !== calc);
t('🔴 câu nhắc KHÔNG làm hàng thành "bất thường" (không chặn gửi)',
	/var batThuong=chiSoNguoc\|\|\(rawCash<0\);/.test(calc)
	&& ! /batThuong=.*nhacKe/.test(calc), null);
t('🔴 khung nhắc dùng lớp vàng bc-nhac, không phải khung đỏ',
	/\} else if\(nhacKe\)\{[\s\S]{0,200}w\.classList\.add\('bc-nhac'\)/.test(calc), null);
/* Hết nhắc (sửa lại số) thì phải dọn CẢ lớp vàng — để lại là hàng đã sạch mà vẫn vàng khè. */
t('🔴 hết nhắc thì dọn cả lớp vàng lẫn chữ',
	/\} else \{\s*\n\s*w\.style\.display='none';\s*\n\s*w\.classList\.remove\('bc-nhac'\);/.test(calc), null);
/* Bất thường VÀ trùng trần cùng lúc: một khung, nối câu. Hai khung đỏ chồng nhau trên một hàng
   thì người đọc bỏ qua cả hai. */
t('🔴 bất thường + trùng trần -> NỐI vào cùng một khung',
	/\+ \(nhacKe \? ' · '\+nhacKe : ''\);/.test(calc), null);

/* ---------- KẾT ---------- */
if (TRUOT.length) {
	console.log('\n✗ HỎNG ' + TRUOT.length + ' phép:');
	TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
	process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: nhập giữa hai ngày thì thấy trần của ngày sau, không gõ nhầm số của ngày ấy.');
