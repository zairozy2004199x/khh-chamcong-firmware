/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * THÊM · XOÁ GHẾ NGAY TẠI CƠ SỞ ĐANG LỌC — MỘT Ô CƠ SỞ, MỘT NGHĨA
 *
 * Anh Thắng 05/09/2026: *"Điều chỉnh phần thêm ghế, 2 dữ liệu cách thêm khác nhau, phía trên
 * đang chọn khác, phía dưới khác. Điều chỉnh khi lọc cơ sở ghế, có thể thêm xóa sửa được ghế
 * luôn"*.
 *
 * 🔴 CÁI HỎNG NÓ VÁ: khối "Thêm ghế" có ô chọn cơ sở RIÊNG, tách hẳn ô "Lọc cơ sở" ngay dưới.
 *    Hai ô chỉ khớp nhau đúng lúc trang vừa vẽ — đổi ô lọc thì ô kia đứng yên. Người dùng lọc
 *    sang GO TRƯỜNG CHINH, gõ mã, bấm Thêm: ghế rơi vào cơ sở CŨ, mà bảng đang lọc cơ sở mới
 *    nên nó KHÔNG hiện ra. Trông y hệt "thêm không được", nên người ta bấm lại, rồi bấm lại —
 *    mỗi lần một ghế ma ở một cơ sở khác. Ba ảnh anh gửi hôm nay là ba lần bấm ấy.
 *
 * ⚠️ BÀI NÀY BỐC KHỐI DỰNG THẬT TỪ MÃ NGUỒN RA CHẠY rồi soi HTML nó sinh ra — không chép lại,
 *    không dò chuỗi suông. Ai đổi luật gán cơ sở mà quên chỗ này thì đỏ ngay.
 *
 * Chạy: node tools/test/kiem-them-xoa-ghe.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const P  = 'vhcp-ghe/includes/class-vhg-trang.php';
const tr = fs.readFileSync(P, 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* Hai hàm giúp việc bốc thẳng từ nguồn — chép tay ở đây thì đổi cách thoát chuỗi bên kia mà bài
   vẫn xanh, đúng lúc nó đang canh chuyện thoát chuỗi. */
const iEsc = tr.indexOf('function esc(s){');
const jEsc = tr.indexOf('function tien(n){', iEsc);
const hamEsc = (iEsc > 0 && jEsc > iEsc) ? tr.slice(iEsc, jEsc) : '';
t('bốc được hàm thoát chuỗi', /function esc/.test(hamEsc), hamEsc.slice(0, 60));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. KHỐI DỰNG: BỐC RA CHẠY THẬT
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const iGhe = tr.indexOf('  var locId = 0;\n  coso.forEach(function(c){ if (c.ten === QL_LOC) locId = c.id; });');
const jGhe = tr.indexOf('\n  return h;\n}', iGhe);
t('bốc được khối dựng phần Ghế', iGhe > 0 && jGhe > iGhe);
const khoi = (iGhe > 0 && jGhe > iGhe) ? tr.slice(iGhe, jGhe) : '';

/* Dựng phần Ghế với đúng bộ dữ liệu vào — y như veQuanLy() gọi nó. */
function dung(cosoDs, mayDs, loc, hienAn) {
	const demGhe = {}; let chuaGan = 0;
	mayDs.forEach(function (m) { if (!m.coso) { chuaGan++; } else { demGhe[m.coso] = (demGhe[m.coso] || 0) + 1; } });
	const f = new Function('coso', 'may', 'demGhe', 'chuaGan', 'QL_LOC', 'QL_HIEN_AN', 'NN',
		hamEsc + '\nfunction L(vi,en){ return NN===\'en\' ? en : vi; }\nvar h="";\n' + khoi + '\nreturn h;');
	return f(cosoDs, mayDs, demGhe, chuaGan, loc, !!hienAn, 'vi');
}

const CS = [ { id: 3, ten: 'AM-TP' }, { id: 7, ten: 'GO TRƯỜNG CHINH' }, { id: 9, ten: 'VC-TĐỨC' } ];
const MAY = [
	{ ma: '80128', coso: 'GO TRƯỜNG CHINH' }, { ma: '80129', coso: 'GO TRƯỜNG CHINH' },
	{ ma: '80130', coso: 'GO TRƯỜNG CHINH' }, { ma: 'AMTP01', coso: 'AM-TP' }, { ma: 'LAC', coso: '' },
];

/* ⚠️ ĐỐI CHỨNG TRƯỚC ĐÃ: nếu khối bốc hụt thì mọi phép dưới xanh vì HTML rỗng chẳng chứa gì. */
const htmlGo = dung(CS, MAY, 'GO TRƯỜNG CHINH');
t('đối chứng: khối bốc ra thật sự dựng được HTML', htmlGo.length > 400 && htmlGo.indexOf('id="ql-loc"') > 0,
	htmlGo.slice(0, 120));

/* ---------- 1a. CHỈ CÒN MỘT Ô CHỌN CƠ SỞ ---------- */
/* 🔴 ĐÂY LÀ PHÉP CHÍNH CỦA CẢ BÀI. Hai ô chọn cơ sở trong một khối là gốc của mọi ghế ma. Đếm
   thẻ <select> trong khối Ghế: đúng MỘT (ô lọc). Đếm chứ không dò tên `ma-cs`, vì mai ai đó
   thêm ô thứ hai mang tên khác thì lỗi cũ quay lại nguyên vẹn mà phép dò tên vẫn xanh. */
const demSelect = (htmlGo.match(/<select /g) || []).length;
teq('🔴 khối Ghế chỉ còn ĐÚNG MỘT ô chọn cơ sở', 1, demSelect);
t('và ô ấy là ô lọc', htmlGo.indexOf('<select id="ql-loc"') > 0, null);

/* ---------- 1b. Ô LỌC QUYẾT ĐỊNH LUÔN CHỖ GHẾ MỚI VÀO ---------- */
function csGui(html) { const m = html.match(/id="ma-cs" value="(\d+)"/); return m ? Number(m[1]) : null; }
function nhanNut(html) { const m = html.match(/id="ma-them"[^>]*>([^<]*)</); return m ? m[1] : ''; }

teq('🔴 lọc GO TRƯỜNG CHINH -> ghế mới gửi kèm id 7', 7, csGui(htmlGo));
teq('nhãn nút nói ĐÚNG cơ sở ấy, để bấm xong không phải đoán',
	true, nhanNut(htmlGo).indexOf('GO TRƯỜNG CHINH') >= 0);

const htmlAm = dung(CS, MAY, 'AM-TP');
teq('🔴 đổi ô lọc sang AM-TP -> id gửi kèm đổi theo', 3, csGui(htmlAm));
t('và nhãn nút đổi theo, không còn kẹt tên cơ sở cũ',
	nhanNut(htmlAm).indexOf('AM-TP') >= 0 && nhanNut(htmlAm).indexOf('GO TRƯỜNG CHINH') < 0, nhanNut(htmlAm));

/* 🔴 "TẤT CẢ CƠ SỞ" KHÔNG PHẢI MỘT CƠ SỞ. Lặng lẽ nhét ghế mới vào cơ sở đầu danh sách là kiểu
   hỏng khó thấy nhất: ghế có mặt, chỉ là ở nhầm nơi, và không ai đi tìm vì trông như xong. Ở đây
   nó thành CHƯA GÁN — hiện ngay trên đầu bảng là "chưa gán", nhìn phát biết. */
const htmlTat = dung(CS, MAY, '');
teq('🔴 lọc "Tất cả cơ sở" -> ghế mới CHƯA GÁN (id 0), không nhét bừa', 0, csGui(htmlTat));
t('và nhãn nút NÓI THẲNG là chưa gán', nhanNut(htmlTat).indexOf('(chưa gán)') >= 0, nhanNut(htmlTat));

const htmlNone = dung(CS, MAY, '__none__');
teq('lọc "(chưa gán)" -> ghế mới cũng chưa gán', 0, csGui(htmlNone));
/* `__none__` là mã nội bộ của ô lọc, không phải tên cơ sở — lọt ra nhãn nút là bày mã máy cho
   người dùng đọc. */
t('🔴 mã nội bộ __none__ không lọt ra nhãn nút', nhanNut(htmlNone).indexOf('__none__') < 0, nhanNut(htmlNone));

/* Cơ sở đã bị xoá khỏi danh mục mà ô lọc còn giữ tên cũ: không được đoán bừa ra một id nào đó. */
teq('🔴 lọc tên cơ sở không còn trong danh mục -> chưa gán, không đoán id',
	0, csGui(dung(CS, MAY, 'CƠ SỞ ĐÃ XOÁ')));

/* ---------- 1c. TÊN CƠ SỞ CÓ KÝ TỰ HTML ---------- */
/* Tên cơ sở do người gõ. Thả thẳng vào nhãn nút là mở cửa cho một cái tên phá vỡ cả khối. */
const CS2 = [ { id: 4, ten: 'GO <b>"CHÍNH"</b> & CO' } ];
const htmlEsc = dung(CS2, [ { ma: 'X1', coso: 'GO <b>"CHÍNH"</b> & CO' } ], 'GO <b>"CHÍNH"</b> & CO');
t('🔴 tên cơ sở có < > " & thì được thoát trước khi vào nhãn nút',
	htmlEsc.indexOf('<b>"CHÍNH"</b>') < 0 && htmlEsc.indexOf('&lt;b&gt;') > 0, nhanNut(htmlEsc));
teq('và id vẫn gửi đúng', 4, csGui(htmlEsc));

/* ---------- 1d. THỨ TỰ TRÊN MÀN ---------- */
/* Ô lọc phải đứng TRƯỚC ô gõ mã: nó quyết định ghế mới vào đâu, nên là thứ người ta chọn đầu
   tiên. Đặt sau ô gõ mã là mời người ta gõ xong rồi mới thấy mình đang ở nhầm cơ sở. */
t('🔴 ô cơ sở đứng TRƯỚC ô gõ mã ghế',
	htmlGo.indexOf('id="ql-loc"') < htmlGo.indexOf('id="ma-moi"'), null);
/* Và cả khối Thêm đứng ngay trên bảng — anh Thắng muốn thêm/xoá/sửa gọn trong một tầm mắt. */
t('khối Thêm đứng ngay trên bảng ghế',
	htmlGo.indexOf('id="ma-moi"') < htmlGo.indexOf('id="ql-wrap"'), null);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. ĐỔI Ô LỌC PHẢI VẼ LẠI CẢ KHỐI, KHÔNG CHỈ CÁI BẢNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 Ô lọc nay quyết định luôn ghế mới vào đâu. Vẽ mỗi bảng thì nhãn nút và ô ẩn `ma-cs` đứng
   yên ở cơ sở cũ — tức là dựng lại đúng cái lỗi vừa đi sửa, chỉ khác là lần này nó nấp sau một
   lần đổi ô lọc. Bốc chính tay xử lý ra chạy, đếm xem nó gọi hàm nào. */
const iH = tr.indexOf("if ((_e = document.getElementById('ql-loc'))) _e.onchange = function(){");
const jH = tr.indexOf("if ((_e = document.getElementById('ql-timtrung')))", iH);
t('bốc được tay xử lý đổi ô lọc', iH > 0 && jH > iH);
const tay = (iH > 0 && jH > iH) ? tr.slice(iH, jH) : '';

function chayDoiLoc() {
	const goi = { ve: 0, bang: 0 };
	const el = { onchange: null, value: 'AM-TP' };
	const f = new Function('document', 've', 'qlGheRender', 'G',
		'var QL_LOC="", QL_PG=9, QL_SEL={a:1}, _e;\n' + tay
		+ '\n return { goi: _e && _e.onchange ? (_e.onchange.call(_e), G) : null, loc: QL_LOC, pg: QL_PG, sel: QL_SEL };');
	return f({ getElementById: function (id) { return id === 'ql-loc' ? el : null; } },
		function () { goi.ve++; }, function () { goi.bang++; }, goi);
}
const kq = chayDoiLoc();
/* ⚠️ Bốc hụt thì `_e.onchange` không có, `goi` về null — phải ĐỎ ở đây chứ không được để mấy
   phép dưới xanh nhờ so với `undefined`. */
t('tay xử lý chạy được', !!(kq && kq.goi), kq);
if (!(kq && kq.goi)) { kq.goi = { ve: -1, bang: -1 }; }
teq('🔴 đổi ô lọc thì vẽ lại CẢ KHỐI (ve), để nhãn nút và ô ẩn theo kịp', 1, kq.goi.ve);
teq('🔴 và KHÔNG chỉ vẽ mỗi bảng', 0, kq.goi.bang);
teq('vẫn nhớ cơ sở vừa chọn', 'AM-TP', kq.loc);
/* Đổi cơ sở là đổi hẳn danh sách: giữ số trang cũ thì rơi vào trang trống, giữ ô tích cũ thì
   "điều chuyển hàng loạt" tác động lên ghế của cơ sở KHÁC — thứ người ta không còn nhìn thấy. */
teq('🔴 về trang 1', 0, kq.pg);
teq('🔴 và bỏ hết ô đang tích của cơ sở cũ', {}, kq.sel);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. XOÁ NGAY TẠI HÀNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const iR = tr.indexOf('function qlGheRender(){');
const jR = tr.indexOf('\nfunction ', iR + 10);
t('bốc được khối vẽ bảng ghế', iR > 0 && jR > iR);
const render = (iR > 0 && jR > iR) ? tr.slice(iR, jR) : '';

/* Bốc đúng đoạn dựng một hàng ra chạy — mỗi hàng phải có nút xoá của CHÍNH mã ghế ấy. */
const iHang = render.indexOf("var tt = m.tt === 'running'");
const jHang = render.indexOf("+ '</td></tr>';", iHang) + "+ '</td></tr>';".length;
t('bốc được đoạn dựng một hàng', iHang > 0 && jHang > iHang);
const hang = (iHang > 0 && jHang > iHang) ? render.slice(iHang, jHang) : '';

function veHang(m) {
	const f = new Function('m', 'QL_SEL', 'coso', 'qlCsOpt', 'NN',
		hamEsc + '\nfunction L(vi,en){ return NN===\'en\' ? en : vi; }\nvar h="";\n' + hang + '\nreturn h;');
	return f(m, {}, CS, function () { return ''; }, 'vi');
}
const hangThuong = veHang({ ma: '80128', ten: 'VHM-1', coso: 'GO TRƯỜNG CHINH', tt: '', song: 1, an: 0 });
t('đối chứng: dựng được một hàng', hangThuong.indexOf('80128') > 0, hangThuong.slice(0, 80));
t('🔴 mỗi hàng có nút xoá mang ĐÚNG mã ghế của hàng đó',
	hangThuong.indexOf('data-mxoa="80128"') > 0, hangThuong);
/* 🔴 Mã ghế do người gõ. Không thoát thì một mã có dấu " tự tay mở thêm thuộc tính vào thẻ. */
t('🔴 mã ghế được thoát trước khi vào thuộc tính',
	veHang({ ma: 'A"B', ten: '', coso: '', tt: '', song: 0, an: 0 }).indexOf('data-mxoa="A&quot;B"') > 0, null);

/* ---------- 3a. TAY XỬ LÝ NÚT XOÁ PHẢI NẰM TRONG KHỐI VẼ LẠI ---------- */
/* 🔴 CHỖ NÀY ĐÃ HỎNG THẬT MỘT LẦN TRONG CHÍNH ĐỢT SỬA NÀY. Bản đầu buộc tay cho nút xoá ở
   `noi()` — mà `noi()` chạy đúng MỘT lần lúc vẽ trang, còn `#ql-wrap` thì `qlGheRender()` vẽ lại
   mỗi lần sang trang, tick chọn, hay bật "hiện ghế đã điều chuyển". Sau lần vẽ lại đầu tiên, mấy
   nút 🗑 là phần tử MỚI, không tay nào buộc: bấm không ra gì, và cũng không báo lỗi gì.
   ⚠️ Phép này soi VỊ TRÍ trong tệp chứ không chạy được DOM thật — nó bắt được ca buộc nhầm chỗ,
      nhưng không bắt được ca buộc đúng chỗ mà sai nội dung. */
const iBuoc = tr.indexOf("box.querySelectorAll('[data-mxoa]')");
t('🔴 nút xoá được buộc tay bằng box.querySelectorAll (trong khối vẽ lại)', iBuoc > 0);
t('🔴 và nằm TRONG qlGheRender, không phải trong noi()', iBuoc > iR && iBuoc < jR,
	{ mxoa: iBuoc, render: [ iR, jR ] });
t('🔴 không còn chỗ nào buộc nút xoá bằng document.querySelectorAll',
	tr.indexOf("document.querySelectorAll('[data-mxoa]')") < 0, null);
/* Buộc đúng chỗ mà gửi nhầm việc thì cũng vô nghĩa — soi luôn việc nó gửi đi. */
const tayXoa = tr.slice(iBuoc, tr.indexOf('\n  });', iBuoc));
t('nút xoá gửi việc may_xoa kèm mã của chính nút ấy',
	/lam\('may_xoa', \{ ma: m \}\)/.test(tayXoa) && /getAttribute\('data-mxoa'\)/.test(tayXoa), tayXoa.slice(0, 200));
/* 🔴 Câu hỏi trước khi xoá phải nói ĐÚNG thứ máy chủ sắp làm. Bản đầu để lại câu cũ ("doanh thu
   đã ghi giữ nguyên") của thời xoá thẳng — hứa một đằng, máy chủ chối một nẻo. */
t('🔴 câu hỏi trước khi xoá nói rõ chỉ xoá được ghế chưa từng có lượt thu',
	/CHƯA TỪNG có lượt thu/.test(tayXoa), tayXoa.slice(0, 400));
t('và chỉ đường sang "Điều chuyển" cho ghế đang chạy',
	/Điều chuyển/.test(tayXoa), null);
t('có hỏi lại trước khi xoá', /confirm\(/.test(tayXoa), null);

/* ---------- 3b. SỬA TÊN VÀ ĐỔI CƠ SỞ VẪN CÒN ---------- */
/* Anh Thắng xin "thêm xoá SỬA" — thêm nút xoá mà làm gãy hai đường sửa sẵn có thì lỗ vốn. */
t('ô sửa tên ghế vẫn còn ở từng hàng', hangThuong.indexOf('data-ten="80128"') > 0, null);
t('ô đổi cơ sở vẫn còn ở từng hàng', hangThuong.indexOf('data-csma="80128"') > 0, null);
t('nút Điều chuyển vẫn còn', hangThuong.indexOf('data-man="80128"') > 0, null);

/* ---------- 3c. THÊM GHẾ VẪN GỬI KÈM CƠ SỞ ---------- */
const iThem = tr.indexOf("if ((_e = document.getElementById('ma-them'))) _e.onclick");
const tayThem = tr.slice(iThem, tr.indexOf('};', iThem));
t('bốc được tay xử lý nút Thêm', iThem > 0);
t('🔴 nút Thêm đọc cơ sở từ ô ma-cs (ô ẩn đã theo ô lọc)',
	/coso_id: document\.getElementById\('ma-cs'\)\.value/.test(tayThem), tayThem.slice(0, 300));
t('và vẫn chặn mã rỗng / mã có dấu, khoảng trắng',
	/\^\[A-Za-z0-9\]\{1,20\}\$/.test(tayThem), null);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. GHẾ ĐÃ ĐIỀU CHUYỂN NẰM Ở DƯỚI TRANG, KHÔNG BIẾN MẤT
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* Anh Thắng 05/09/2026: *"chỗ phần điều chuyển tức là ẩn nó đi, nằm ở dưới trang, sau này cần
   lắp lại ta mở nó lên là được"*.
 *
 * 🔴 BẢN TRƯỚC ẨN HẲN. Ghế điều chuyển biến mất khỏi bảng, chỉ hiện lại khi tích một ô "Hiện ghế
 *    đã điều chuyển" ở trên. Nghĩa là muốn tìm lại một ghế ẩn nhầm thì phải NHỚ ra rằng mình đã
 *    ẩn nó — mà thứ hay phải tìm lại nhất chính là ghế ẩn nhầm, tức là ghế người ta KHÔNG nhớ đã
 *    ẩn. Nay nó luôn có mặt trong khối gập ở cuối, đếm số ngay trên nhãn.
 *
 * ⚠️ Bài này CHẠY cả `qlGheRender()` với một DOM giả tí hon, không dò chuỗi trong nguồn. */
const iR2 = tr.indexOf('function qlGheRender(){');
const jR2 = tr.indexOf('\nfunction ', iR2 + 10);
const renderFull = (iR2 > 0 && jR2 > iR2) ? tr.slice(iR2, jR2) : '';
t('bốc được cả hàm vẽ bảng ghế', /function qlGheRender/.test(renderFull), renderFull.slice(0, 60));

/* DOM giả: chỉ cần đủ cho hàm chạy tới lúc nó nhét HTML vào #ql-wrap. */
function veBang(mayDs, loc, xacNhan) {
	const box = { innerHTML: '', appendChild: function () {}, querySelectorAll: function () { return []; } };
	const nut = {};                       // phần tử giả, giữ lại để gọi được tay xử lý
	const daGui = [];                     // mọi việc hàm ấy gửi về máy chủ
	const f = new Function('D', 'QL_LOC', 'QL_PER', 'QL_SEL', 'QL_PG', 'NN', 'box', 'qlCsOpt', 'qlChon',
		'lam', 'DOC', 'confirm', 'alert',
		hamEsc + '\nfunction L(vi,en){ return NN===\'en\' ? en : vi; }\n'
		+ 'var document = DOC;\n' + renderFull + '\nqlGheRender();');
	f({ may: mayDs, coso: CS }, loc, 10, {}, 0, 'vi', box,
		function () { return ''; }, function () { return []; },
		function (viec, d) { daGui.push([ viec, d ]); },
		{ getElementById: function (id) {
			if (id === 'ql-wrap') return box;
			/* Trả phần tử giả cho MỌI id khác — có thật trong HTML vừa dựng hay không thì cứ
			   nhận, rồi soi lại bằng chính HTML ấy. */
			if (!nut[id]) nut[id] = { id: id, onclick: null, style: {} };
			return nut[id];
		  },
		  createElement: function () { return { style: {}, classList: { add: function () {} }, appendChild: function () {} }; } },
		function () { return xacNhan === undefined ? true : xacNhan; },
		function () {});
	return { html: box.innerHTML, nut: nut, daGui: daGui };
}

const MAY_AN = [
	{ ma: '80128', ten: 'GO-TC-4', coso: 'GO TRƯỜNG CHINH', an: 0, song: 1 },
	{ ma: '80129', ten: 'GO-TC-5', coso: 'GO TRƯỜNG CHINH', an: 0, song: 1 },
	{ ma: 'GOCU1',  ten: 'GO-TC-1', coso: 'GO TRƯỜNG CHINH', an: 1, song: 0 },
	{ ma: 'GOCU2',  ten: 'GO-TC-2', coso: 'GO TRƯỜNG CHINH', an: 1, song: 0 },
	{ ma: 'AMTP01', ten: 'AM-1',    coso: 'AM-TP',           an: 0, song: 1 },
	{ ma: 'AMCU',   ten: 'AM-cũ',   coso: 'AM-TP',           an: 1, song: 0 },
];
const raGo = veBang(MAY_AN, 'GO TRƯỜNG CHINH');
const bangGo = raGo.html;
t('đối chứng: vẽ được bảng', bangGo.length > 300 && bangGo.indexOf('80128') > 0, bangGo.slice(0, 150));

/* ---------- 4a. GHẾ ẨN KHÔNG CÒN TRỘN VÀO BẢNG CHÍNH ---------- */
const iKhoiAn = bangGo.indexOf('<details');
t('🔴 có khối gập "Ghế đã điều chuyển" ở dưới', iKhoiAn > 0, bangGo.slice(-300));
const bangChinh = iKhoiAn > 0 ? bangGo.slice(0, iKhoiAn) : bangGo;
const khoiAn    = iKhoiAn > 0 ? bangGo.slice(iKhoiAn)    : '';
t('🔴 ghế đã điều chuyển KHÔNG còn nằm trong bảng chính',
	bangChinh.indexOf('GOCU1') < 0 && bangChinh.indexOf('GOCU2') < 0, bangChinh);
t('và ghế đang chạy thì có', bangChinh.indexOf('80128') > 0 && bangChinh.indexOf('80129') > 0, null);

/* ---------- 4b. KHỐI Ở DƯỚI ĐẾM SỐ NGAY TRÊN NHÃN ---------- */
/* Không mở ra cũng phải biết trong đó có gì — nếu không thì nó chỉ là cái ô tích cũ đổi hình. */
t('🔴 nhãn khối nói ra SỐ ghế đang nằm trong đó',
	/<summary[^>]*>[^<]*\(2\)/.test(khoiAn), khoiAn.slice(0, 220));
t('🔴 hai ghế ẩn của cơ sở này đều có mặt',
	khoiAn.indexOf('GOCU1') > 0 && khoiAn.indexOf('GOCU2') > 0, null);
/* Cùng một bộ lọc cơ sở cho cả hai phần — khác nhau là ghế ẩn ở cơ sở này lại hiện dưới bảng
   của cơ sở kia. */
t('🔴 khối dưới theo ĐÚNG bộ lọc cơ sở của bảng trên', khoiAn.indexOf('AMCU') < 0, khoiAn);
teq('đổi sang AM-TP thì khối dưới đổi theo', true,
	veBang(MAY_AN, 'AM-TP').html.split('<details')[1].indexOf('AMCU') > 0);

/* ---------- 4c. MỞ LÊN LÀ LẮP LẠI ĐƯỢC ---------- */
t('🔴 mỗi ghế ẩn có nút Đưa về mang đúng mã của nó',
	khoiAn.indexOf('data-mhien="GOCU1"') > 0 && khoiAn.indexOf('data-mhien="GOCU2"') > 0, khoiAn);
t('và vẫn xoá được ngay tại đó (ghế gõ nhầm mã hay bị đẩy vào đây)',
	khoiAn.indexOf('data-mxoa="GOCU1"') > 0, null);
/* "Ẩn nhanh ghế trùng tên" ẩn được hàng chục ghế trong một cú bấm, nên phải có một cú bấm đưa
   cả đống ấy về. Nhưng nó nằm TRONG khối gập và có hỏi lại — dựng lại cả đống ghế trùng tên một
   cách tình cờ là làm bẩn lại đúng thứ vừa dọn. */
t('🔴 có đường hoàn tác hàng loạt cho "Ẩn nhanh ghế trùng tên"',
	khoiAn.indexOf('id="ql-hien-tat"') > 0, null);
t('và nó nằm TRONG khối gập, không phải ngoài bảng',
	khoiAn.indexOf('id="ql-hien-tat"') > 0 && bangChinh.indexOf('ql-hien-tat') < 0, null);
/* 🔴 BẤM THẬT CÁI NÚT ẤY, KHÔNG DÒ CHỮ `confirm(` TRONG NGUỒN. Phá thử bắt được bản đầu của
   phép này: đục thành `if (false && !confirm(...))` thì câu hỏi không bao giờ hiện ra nữa mà bài
   vẫn xanh, vì chữ `confirm(` còn nguyên trong mã. Đây là lần thứ TƯ trong kho này một phép thử
   tự xanh nhờ chuỗi nó đang canh vẫn còn nằm đâu đó. */
t('🔴 tay xử lý nút Đưa về tất cả có được buộc', !!(raGo.nut['ql-hien-tat'] && raGo.nut['ql-hien-tat'].onclick),
	Object.keys(raGo.nut));
if (raGo.nut['ql-hien-tat'] && raGo.nut['ql-hien-tat'].onclick) {
	/* Người dùng bấm rồi bấm "Huỷ" ở câu hỏi: không được gửi gì đi cả. */
	const raHuy = veBang(MAY_AN, 'GO TRƯỜNG CHINH', false);
	raHuy.nut['ql-hien-tat'].onclick();
	teq('🔴 bấm rồi TỪ CHỐI ở câu hỏi -> không gửi gì đi', 0, raHuy.daGui.length);

	/* Bấm rồi đồng ý: đưa về ĐÚNG hai ghế đang ẩn của cơ sở này. */
	const raOk = veBang(MAY_AN, 'GO TRƯỜNG CHINH', true);
	raOk.nut['ql-hien-tat'].onclick();
	teq('🔴 đồng ý -> gửi đúng một việc', 1, raOk.daGui.length);
	teq('và là việc đưa về hàng loạt', 'may_an_lo', raOk.daGui[0] && raOk.daGui[0][0]);
	teq('🔴 đưa về (an: 0), không phải ẩn thêm', 0, raOk.daGui[0] && raOk.daGui[0][1].an);
	teq('🔴 và đúng hai ghế ẩn của cơ sở đang xem, không đụng ghế cơ sở khác',
		[ 'GOCU1', 'GOCU2' ], raOk.daGui[0] && raOk.daGui[0][1].ma);
}
const iTat = tr.indexOf("var _ht = document.getElementById('ql-hien-tat');");
t('🔴 nút hoàn tác cũng buộc tay trong qlGheRender, không phải trong noi()',
	iTat > iR2 && iTat < jR2, { tat: iTat, render: [ iR2, jR2 ] });

/* ---------- 4d. KHÔNG CÓ GHẾ ẨN THÌ KHÔNG BÀY KHỐI RỖNG ---------- */
/* Một khối "Ghế đã điều chuyển (0)" đứng dưới mọi cơ sở là thứ người ta học cách không nhìn. */
const bangSach = veBang([ { ma: 'X1', ten: '', coso: 'AM-TP', an: 0, song: 1 } ], 'AM-TP').html;
t('🔴 cơ sở không có ghế ẩn thì KHÔNG bày khối rỗng', bangSach.indexOf('<details') < 0, bangSach.slice(-200));

/* ---------- 4e. Ô TÍCH "HIỆN GHẾ ĐÃ ĐIỀU CHUYỂN" ĐÃ ĐI HẲN ---------- */
/* Để lại thì có hai đường xem cùng một thứ, và đường cũ lại là đường mặc định giấu đi. */
t('🔴 không còn ô tích "Hiện ghế đã điều chuyển"', tr.indexOf("id=\"ql-htan\"") < 0, null);
t('🔴 và không còn biến QL_HIEN_AN nào sót lại', tr.indexOf('QL_HIEN_AN') < 0, null);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. TẠO / ĐỔI TÊN / XOÁ CƠ SỞ — Ô LỌC PHẢI THEO KỊP
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* Anh Thắng 05/09/2026: *"vậy giờ muốn tạo cơ sở mới thì sao"*.
 *
 * 🔴 TỪ 2.5.0 Ô LỌC LÀ NƠI DUY NHẤT QUYẾT ĐỊNH GHẾ MỚI VÀO ĐÂU, nên mọi thứ làm đổi danh mục cơ
 *    sở đều phải kéo ô lọc theo. Vừa tạo "GO ĐÀ NẴNG" mà nút vẫn nói "Thêm ghế vào GO TRƯỜNG
 *    CHINH" là dựng lại đúng cái bẫy 2.5.0 vừa vá — cùng lỗi, chỉ khác đường vào.
 *
 * ⚠️ BA CA, VÌ BA ĐƯỜNG KHÁC NHAU: thêm mới, đổi tên, xoá. Ô lọc giữ TÊN chứ không giữ id, nên
 *    đổi tên là nó trỏ vào một cái tên không còn ai mang. */

/* Bốc `tai()` ra chạy — đây là chỗ ý định được đối chiếu với danh mục THẬT rồi mới áp. */
const iTai = tr.indexOf('function tai(im){');
const jTai = tr.indexOf('\nfunction henLai(){', iTai);
t('bốc được hàm tải lại', iTai > 0 && jTai > iTai);
const hamTai = (iTai > 0 && jTai > iTai) ? tr.slice(iTai, jTai) : '';

/* Chạy tai() với danh mục cơ sở máy chủ trả về, xem ô lọc đậu ở đâu. */
function taiLai(choCs, locCu, cosoVe) {
	const f = new Function('QL_CHO_CS', 'QL_LOC', 'GOI', 'VE',
		'var D = null, QL_PG = 9, QL_SEL = {a:1}, KY = "";\n'
		+ 'function goi(v,d,cb){ GOI(cb); }\nfunction ve(){ VE(); }\nfunction veLogin(){}\n'
		+ hamTai + '\ntai(); return { loc: QL_LOC, cho: QL_CHO_CS, pg: QL_PG, sel: QL_SEL };');
	return f(choCs, locCu,
		function (cb) { cb({ ok: true, coso: cosoVe.map(function (n) { return { id: 1, ten: n }; }) }); },
		function () {});
}

/* ---------- 5a. TẠO CƠ SỞ MỚI ---------- */
const raTao = taiLai('GO ĐÀ NẴNG', 'GO TRƯỜNG CHINH', [ 'GO TRƯỜNG CHINH', 'GO ĐÀ NẴNG' ]);
teq('🔴 tạo xong cơ sở mới -> ô lọc nhảy sang chính nó', 'GO ĐÀ NẴNG', raTao.loc);
teq('và ý định được dọn đi', '', raTao.cho);
teq('🔴 về trang 1 (danh sách ghế đổi hẳn)', 0, raTao.pg);
teq('🔴 và bỏ ô đang tích của cơ sở cũ', {}, raTao.sel);

/* 🔴 MÁY CHỦ CHỐI (trùng tên, tên rỗng) THÌ KHÔNG ĐƯỢC NHẢY. Đặt thẳng QL_LOC lúc gửi là lạc
   quan: cơ sở không ra đời mà ô lọc vẫn trỏ sang nó, và ghế mới lặng lẽ rơi vào "chưa gán". */
const raChoi = taiLai('GO ĐÀ NẴNG', 'GO TRƯỜNG CHINH', [ 'GO TRƯỜNG CHINH' ]);
teq('🔴 máy chủ chối -> ô lọc đứng nguyên chỗ cũ', 'GO TRƯỜNG CHINH', raChoi.loc);
teq('🔴 nhưng ý định vẫn phải dọn, không để nó lái lượt tải lại sau', '', raChoi.cho);
/* Không áp được thì cũng đừng đá số trang và ô tích của người ta. */
teq('không áp được thì giữ nguyên trang đang xem', 9, raChoi.pg);
teq('và giữ nguyên ô đang tích', { a: 1 }, raChoi.sel);

/* Không có ý định gì thì tuyệt đối không đụng vào ô lọc. */
const raIm = taiLai('', 'AM-TP', [ 'AM-TP', 'GO TRƯỜNG CHINH' ]);
teq('🔴 lượt tải lại thường không đụng vào ô lọc', 'AM-TP', raIm.loc);
teq('và không đá số trang', 9, raIm.pg);

/* ---------- 5b. THÊM CƠ SỞ CÓ GHI Ý ĐỊNH KHÔNG ---------- */
const iCsThem = tr.indexOf("if ((_e = document.getElementById('cs-them'))) _e.onclick");
const jCsThem = tr.indexOf('\n  };', iCsThem);
t('bốc được tay xử lý nút Thêm địa điểm', iCsThem > 0 && jCsThem > iCsThem);
const tayCsThem = (iCsThem > 0 && jCsThem > iCsThem) ? tr.slice(iCsThem, jCsThem) : '';

function bamThemCs(ten) {
	const daGui = [];
	/* `QL_CHO_CS` khởi đầu bằng 'CU' — một giá trị KHÔNG BAO GIỜ đúng, để phân biệt được "tay xử
	   lý đã ghi ý định" với "nó chẳng đụng gì". Khởi đầu bằng '' thì ca không ghi và ca ghi
	   chuỗi rỗng trông y hệt nhau. */
	const f = new Function('DOC', 'LAM', 'NN',
		'function L(vi,en){ return NN===\'en\' ? en : vi; }\n'
		+ 'var document = DOC, _e, QL_CHO_CS = "CU";\n'
		+ 'function lam(v,d){ LAM(v,d); }\nfunction alert(){}\n'
		+ tayCsThem + '\n  };\n if (_e && _e.onclick) _e.onclick();\n return QL_CHO_CS;');
	const cho = f({ getElementById: function (id) {
			if (id === 'cs-them') return { onclick: null };
			if (id === 'cs-ten') return { value: ten };
			return { value: '' };
		} },
		function (v, d) { daGui.push([ v, d ]); }, 'vi');
	return { daGui: daGui, cho: cho };
}
const bamOk = bamThemCs('GO ĐÀ NẴNG');
t('đối chứng: bấm được nút Thêm địa điểm', bamOk.daGui.length === 1, bamOk);
teq('vẫn gửi việc tạo cơ sở như cũ', 'coso_luu', bamOk.daGui[0] && bamOk.daGui[0][0]);
teq('🔴 và ghi ý định nhảy ô lọc sang cơ sở vừa xin tạo', 'GO ĐÀ NẴNG', bamOk.cho);
/* Tên rỗng thì chối ngay — không gửi gì, và cũng không ghi ý định lơ lửng. */
const bamRong = bamThemCs('   ');
teq('🔴 tên rỗng -> không gửi gì đi', 0, bamRong.daGui.length);
teq('🔴 và không ghi ý định lơ lửng', 'CU', bamRong.cho);

/* ---------- 5c. ĐỔI TÊN VÀ XOÁ CƠ SỞ ĐANG LỌC ---------- */
/* Ô lọc giữ TÊN. Đổi tên cơ sở đang lọc mà không kéo ô lọc theo thì bảng trống trơn dù ghế còn
   nguyên, và nút Thêm ghế tụt về "(chưa gán)". */
const iSua = tr.indexOf("if (QL_LOC === b.getAttribute('data-csten')) QL_CHO_CS = t;");
t('🔴 đổi tên cơ sở ĐANG LỌC thì ghi ý định theo tên mới', iSua > 0);
/* Và chỉ khi đang lọc ĐÚNG cơ sở ấy — đổi tên một cơ sở khác mà kéo ô lọc đi là cướp chỗ đang
   xem của người ta. */
t('🔴 và chỉ khi đang lọc đúng cơ sở ấy, không kéo ô lọc đi vô cớ',
	/if \(QL_LOC === b\.getAttribute\('data-csten'\)\) QL_CHO_CS = t;/.test(tr), null);

const iXoaCs = tr.indexOf("if (QL_LOC === nhan){ QL_LOC = ''; QL_PG = 0; QL_SEL = {}; }");
t('🔴 xoá cơ sở ĐANG LỌC thì ô lọc về "Tất cả"', iXoaCs > 0);
t('và cũng chỉ khi đang lọc đúng cơ sở bị xoá',
	/if \(QL_LOC === nhan\)\{ QL_LOC = ''; QL_PG = 0; QL_SEL = \{\}; \}/.test(tr), null);
/* Hai câu ấy phải đứng TRƯỚC lệnh gửi đi, không thì tải lại xong mới đổi là đã vẽ nhầm một lượt. */
t('🔴 ô lọc được dọn TRƯỚC khi gửi lệnh xoá',
	iXoaCs > 0 && iXoaCs < tr.indexOf("lam('coso_xoa'", iXoaCs), null);

/* ---------- 5d. KHỐI THÊM ĐỊA ĐIỂM VẪN CÒN NGUYÊN ---------- */
/* Bỏ ô chọn cơ sở trong khối GHẾ không được đụng tới khối ĐỊA ĐIỂM — đó là nơi duy nhất tạo
   được cơ sở mới, và cũng là câu hỏi của anh Thắng. */
t('🔴 vẫn còn ô gõ tên địa điểm mới', tr.indexOf('id="cs-ten"') > 0, null);
t('🔴 vẫn còn nút Thêm địa điểm', tr.indexOf('id="cs-them"') > 0, null);
t('vẫn còn ô Tỉnh/TP và Mã KH', tr.indexOf('id="cs-tinh"') > 0 && tr.indexOf('id="cs-makh"') > 0, null);
t('vẫn sửa và xoá được địa điểm', tr.indexOf('data-cssua="') > 0 && tr.indexOf('data-csxoa="') > 0, null);

/* ---------- KẾT ---------- */
if (TRUOT.length) {
	console.log('\n✗ HỎNG ' + TRUOT.length + ' phép:');
	TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
	process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: một ô cơ sở duy nhất, ghế mới vào đúng cơ sở đang xem, xoá được ngay tại hàng, ghế đã điều chuyển nằm sẵn ở dưới, tạo/đổi tên/xoá cơ sở thì ô lọc theo kịp.');
