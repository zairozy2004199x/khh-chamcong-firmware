/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LOẠI CHI PHÍ: CHỈ BÀY VAI CỦA ĐÚNG KHỐI ẤY — PHÍA MÀN HÌNH, BỐC HÀM THẬT RA CHẠY.
 *
 * Anh Thắng 21/09/2026: *"Loại chi phí theo Khối, Ai có ở khối nào mới hiện ra"*.
 *
 * Ảnh anh gửi: bảng loại chi phí của **Khu vui chơi** bày mười lăm ô tích, trong đó có "Quản Lý
 * Máy Tự Động", "Kế Toán Máy Tự Động", "Nhân Viên Cơ Sở Máy Tự Động", "Kế Toán VP Chung"… Quá
 * nửa danh sách là vai không bao giờ dùng tới ở bảng ấy, mà tích nhầm một cái là mở sổ của Khu
 * vui chơi cho cả một khối khác.
 *
 * =============================================================================================
 * 🔴 BẢN 1.235.0 ĐÃ HIỂU SAI, VÀ BÀI NÀY GIỮ LẠI CẢ HAI NỪA
 * =============================================================================================
 * Bản đầu giấu hẳn vai của khối khác, chỉ chừa lại những ô đã tích và còn tô cam + đóng dấu
 * cảnh báo lên chúng. Anh Thắng chỉ ra ngay: *"1 người có thể nhận 2 vai trò của 2 khối khác
 * nhau"* — nhân sự chung làm việc với cả hai bên, nên tích chéo khối là ĐÚNG Ý, không phải gõ
 * nhầm. Mà giấu hẳn còn tệ hơn: không có cách nào TÍCH MỚI một vai khối khác nữa.
 *
 * Nay: vai của khối này bày thẳng (đúng *"ai có ở khối nào mới hiện ra"*), vai khối khác nằm
 * trong nếp gấp `<details class="vaiNgoai">` — gọn mắt mà vẫn với tới được.
 *
 * ⚠️ DÙNG `<details>` CHỨ KHÔNG BỎ Ô RA KHỎI DOM: lượt Lưu đọc `[data-vai] input:checked`, nên
 *    ô nằm trong nếp đóng vẫn được đọc đủ. Bỏ ra khỏi DOM là lượt Lưu kế tiếp xoá đúng quyền
 *    ấy — lặng lẽ, và người bấm Lưu chỉ định sửa một ô khác hẳn.
 *
 * ⚠️ VÀ PHẢI CÙNG LUẬT VỚI MÁY CHỦ. Bảng vàng nằm ở `fixtures/vai-theo-khoi.json`, dùng chung
 *    với `kiem-vai-theo-khoi.php` — sửa một bên quên bên kia là một trong hai đỏ.
 *
 * Chạy: node tools/test/kiem-vai-theo-khoi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const FX = JSON.parse(fs.readFileSync('tools/test/fixtures/vai-theo-khoi.json', 'utf8'));

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
/* ⚠️ GỠ CHÚ THÍCH TRƯỚC KHI DÒ CHỮ. Đã cắn bốn lượt trong repo này: phép tìm một chữ trong mã
   lại bắt trúng chính chữ ấy nằm trong khối chú thích em vừa viết ngay bên cạnh — xanh vĩnh
   viễn, kể cả khi mã bị gỡ sạch. */
function bocSach(ten) { return bocHam(ten).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' '); }
function bocBien(ten) {
  const i = HTML.indexOf('  var ' + ten + '=');
  if (i < 0) return '';
  const j = HTML.indexOf('];', i) + 2;
  return (j > i) ? HTML.slice(i, j) : '';
}

['_boDauVai', '_khoiCuaVai', '_vaiOKhoi', '_vaiSelNhieu', '_khoiODoi', '_khoiTichNguoi', '_khoiTichDoi'].forEach(function (x) {
  t('bốc được `' + x + '`', bocHam(x).length > 40, bocHam(x).length);
});

/* ═══ 1. CHẠY THẬT `_khoiCuaVai` QUA BẢNG VÀNG ═══════════════════════════════════
 * Dựng bằng `new Function` chứ không đọc chữ: đọc chữ thì mã sai luật vẫn xanh. */
const NGUON = bocBien('KHOI_THEO_TEN_VAI') + '\n' + bocHam('_boDauVai') + '\n' + bocHam('_khoiCuaVai')
  + '\n' + bocHam('_vaiOKhoi')
  + '\nreturn {khoiCuaVai:_khoiCuaVai, vaiOKhoi:_vaiOKhoi, boDau:_boDauVai};';
let M = null;
try { M = new Function(NGUON)(); } catch (e) { t('dựng được mô-đun từ mã thật', false, String(e)); }
if (M) {
  t('dựng được mô-đun từ mã thật', true);

  const bang = FX.bang;
  const co = Array.from(new Set(Object.keys(bang).map(function (k) { return bang[k]; }))).sort();
  teq('🔴 bảng vàng phủ cả ba khối lẫn vai chạy ngang', ['', 'kvc', 'mtd', 'vp'], co);
  Object.keys(bang).forEach(function (ten) {
    teq('khối của «' + (ten === '' ? '(tên rỗng)' : ten) + '»', bang[ten], M.khoiCuaVai(ten));
  });

  /* ═══ 2. KHÔNG ĐOÁN ĐƯỢC = MỌI KHỐI ════════════════════════════════════════════ */
  ['kvc', 'mtd', 'vp'].forEach(function (k) {
    t('🔴 «Nhân Viên Marketing» bày ở khối ' + k, M.vaiOKhoi('Nhân Viên Marketing', k));
    t('   vai gốc «Nhân viên» cũng bày ở khối ' + k, M.vaiOKhoi('Nhân viên', k));
  });
  t('«Quản Lý Máy Tự Động» KHÔNG bày ở kvc', !M.vaiOKhoi('Quản Lý Máy Tự Động', 'kvc'));
  t('«Kế Toán VP Chung» KHÔNG bày ở mtd', !M.vaiOKhoi('Kế Toán VP Chung', 'mtd'));

  /* ═══ 3. TÍCH CHÉO KHỐI LÀ HỢP LỆ ═════════════════════════════════
   * `_vaiOKhoi` chỉ còn trả lời "vai này có thuộc khối ấy không" để XẾP CHỖ — không còn
   * quyết định bày hay giấu. Phép đếm ô thật ở mục 5b mới là chỗ canh chuyện đó. */
  t('«Quản Lý Máy Tự Động» không thuộc nhánh kvc', !M.vaiOKhoi('Quản Lý Máy Tự Động', 'kvc'));
  t('   nhưng thuộc nhánh mtd', M.vaiOKhoi('Quản Lý Máy Tự Động', 'mtd'));

  /* ═══ 4. «vp» XÉT THEO TỪ; KVC/MTĐ XÉT TRƯỚC ══════════════════════════════════ */
  teq('🔴 «TVP» KHÔNG phải văn phòng', '', M.khoiCuaVai('Nhân Viên TVP'));
  teq('   «VP» đứng riêng thì có', 'vp', M.khoiCuaVai('Nhân Viên VP'));
  teq('🔴 «Kế Toán VP Khu Vui Chơi» về kvc, không về vp', 'kvc', M.khoiCuaVai('Kế Toán VP Khu Vui Chơi'));
  /* ⚠️ `normalize('NFD')` gỡ được dấu thanh nhưng KHÔNG gỡ được chữ đ. Quên thay tay là
     "Tự Động" ra "tu dộng" và cả nhánh MTĐ trượt. */
  teq('🔴 chữ «đ» được thay tay trước khi normalize', 'may tu dong', M.boDau('Máy Tự Động'));
}

/* ═══ 5. Ô TÍCH VAI NHẬN KHỐI CỦA HÀNG, VÀ HÀNG TRUYỀN KHỐI VÀO ═════════════════ */
const SEL = bocSach('_vaiSelNhieu');
t('🔴 `_vaiSelNhieu` nhận tham số khối', /function _vaiSelNhieu\(v,\s*khoi\)/.test(bocHam('_vaiSelNhieu')), 'không thấy');
t('🔴 và xếp vai con theo khối qua `_vaiOKhoi`', /_vaiOKhoi\(r, khoi\)/.test(SEL) && /ngoai\.push\(r\)/.test(SEL), 'không thấy');
/* ⚠️ SOI CHÍNH CHỖ CŨ TÔ CẢNH BÁO — hàm `o()` dựng một ô tích — chứ KHÔNG soi cả hàm.
   Cắn thật 22/09/2026: hàm này nay có một dòng nhắc "⚠️ Còn tích vai chung" cho việc KHÁC HẲN
   (vai gốc còn sót trong sổ), và phép cũ `SEL.indexOf('⚠') < 0` đỏ oan vì đúng cái dấu ấy.
   Phép thử phải bám vào thứ nó nói, không bám vào một ký tự đi ngang qua. */
const SEL_O = (SEL.match(/var o=function\(r,dam\)\{[\s\S]*?\n    \};/) || [''])[0];
t('bốc được hàm dựng một ô tích', SEL_O.length > 80, SEL_O);
t('🔴 không còn tô cảnh báo lên ô tích chéo khối nữa',
  !/lacKhoi/.test(SEL) && SEL_O.indexOf('⚠') < 0 && !/#c0392b|#b45309/.test(SEL_O), SEL_O);
t('🔴 vai khối khác vào nếp gấp, không bị bỏ ra khỏi DOM', /<details class="vaiNgoai"/.test(SEL), 'không thấy');
t('   nếp mở sẵn khi bên trong có ô đang tích', /coTich\?' open':''/.test(SEL), 'không thấy');
t('🔴 hàng loại chi phí truyền khối của chính nó vào',
  /_vaiSelNhieu\(x\.vaiTro\|\|'',\s*_khoiCuaLoai\(x\)\)/.test(bocSach('_mxRowHtml')), 'không thấy');

/* ═══ 5b. VẼ THẬT MỘT HỘP Ô TÍCH RỒI ĐẾM ═════════════════════════════
 * 🔴 DÒ CHỮ TRONG MÃ KHÔNG ĐỦ Ở ĐÂY, và đã cắn ngay lượt viết này: phép `!/Admin/` đỏ
 *    vì chữ "Admin" nằm trong LờI CHÚ GIẢI của thuộc tính `title` — lời văn bày cho người
 *    dùng đọc, không phải một ô tích. Đếm ô THẬT thì không nhầm được, và còn bắt được
 *    cả những thứ dò chữ không thấy: ô đã tích có sống sót không, lọc có đúng số ô không. */
const VAI_MAU = [
  { ten: 'Quản Lý Khu Vui Chơi', goc: 'Quản lý' },
  { ten: 'Quản Lý Máy Tự Động', goc: 'Quản lý' },
  { ten: 'Quản Lý VP Chung', goc: 'Quản lý' },
  { ten: 'Kế Toán Khu Vui Chơi', goc: 'Kế toán cá nhân' },
  { ten: 'Kế Toán Máy Tự Động', goc: 'Kế toán cá nhân' },
  { ten: 'Nhân Viên Cơ Sở Khu Vui Chơi', goc: 'Nhân viên' },
  { ten: 'Nhân Viên Kỹ Thuật Máy Tự Động', goc: 'Nhân viên' },
  { ten: 'Nhân Viên Marketing', goc: 'Nhân viên' }
];
function veHop(v, khoi) {
  const src = 'var CFG={vaiTro:VAI_MAU};\n' + bocHam('esc') + '\n' + bocBien('VAI_GOC')
    + '\n' + bocBien('KHOI_THEO_TEN_VAI') + '\n' + bocHam('_boDauVai') + '\n' + bocHam('_khoiCuaVai')
    + '\n' + bocHam('_vaiOKhoi') + '\n' + bocHam('_vaiBay') + '\n' + bocHam('_vaiConCua')
    + '\n' + bocHam('_vaiSelNhieu') + '\nreturn _vaiSelNhieu(v, khoi);';
  return new Function('VAI_MAU', 'v', 'khoi', src)(VAI_MAU, v, khoi);
}
function oTich(h) {
  const ra = []; const re = /<input type="checkbox" value="([^"]*)"( checked)?/g; let m;
  while ((m = re.exec(h))) { ra.push(m[1] + (m[2] ? ' ✓' : '')); }
  return ra;
}
/* Hai vùng của một hộp: trước nếp gấp (vai của khối này) và trong nếp (vai khối khác).
   ⚠️ ĐẾM RIÊNG HAI VÙNG, không đếm gộp: gộp lại thì một hàm KHÔNG xếp gì cả — đổ
      tất mười lăm ô ra một đống phẳng như trước — vẫn đếm ra đúng con số. */
function haiVung(h) {
  const i = h.indexOf('<details');
  return i < 0 ? { ngoai: oTich(h), trong: [] }
               : { ngoai: oTich(h.slice(0, i)), trong: oTich(h.slice(i)) };
}
let HK = '';
try { HK = veHop('', 'kvc'); t('vẽ thật được hộp ô tích', HK.length > 100); }
catch (e) { t('vẽ thật được hộp ô tích', false, String(e)); }
if (HK) {
  const V = haiVung(HK);
  t('🔴 KHÔNG có ô tích nào tên Admin', V.ngoai.concat(V.trong).indexOf('Admin') < 0, V);
  /* 🔴 VAI GỐC THÔI LÀ Ô TÍCH (22/09/2026) — anh Thắng: *"Vai trò đang tích lẻ, nên vai trò
     chung không dùng nữa"*. Nên "Kế toán NCC" KHÔNG còn trong danh sách ô tích; nó là NHÃN. */
  t('🔴 bảng KVC bày thẳng vai KVC + vai chạy ngang',
    V.ngoai.indexOf('Quản Lý Khu Vui Chơi') >= 0 && V.ngoai.indexOf('Nhân Viên Marketing') >= 0, V.ngoai);
  t('🔴 và vai GỐC không còn là ô tích — chỉ là nhãn nhóm',
    V.ngoai.indexOf('Kế toán NCC') < 0 && V.trong.indexOf('Kế toán NCC') < 0
      && HK.indexOf('>Kế toán NCC</b>') >= 0, V);
  t('🔴 và ĐẨY vai khối khác xuống nếp gấp, không bỏ đi',
    V.ngoai.indexOf('Quản Lý Máy Tự Động') < 0 && V.trong.indexOf('Quản Lý Máy Tự Động') >= 0, V);
  t('   ô của Văn phòng cũng ở trong nếp',
    V.ngoai.indexOf('Quản Lý VP Chung') < 0 && V.trong.indexOf('Quản Lý VP Chung') >= 0, V);
  /* 🔴 CHỐT ĐẮT NHẤT: KHÔNG Ô NÀO ĐƯỢC BIẾN. Anh Thắng: *"1 người có thể nhận 2
     vai trò của 2 khối khác nhau"* — tích chéo khối phải LÀM ĐƯỢC, không phải chỉ giữ
     lại mấy ô lỡ tích từ trước. Đếm tổng là chỗ canh điều đó. */
  /* Tám vai CON của bộ gieo; bốn vai gốc nay là nhãn, không đếm. Suy từ bộ gieo chứ không gõ
     tay con số — thêm một vai mẫu là bài này đỏ oan. */
  teq('🔴 tổng số ô vẫn đủ MỌI vai con — chỉ xếp lại, không bớt', VAI_MAU.length,
    V.ngoai.length + V.trong.length);
  teq('   và đủ bốn nhãn nhóm, không nhóm nào biến mất', 4,
    (HK.match(/<b style="font-size:11\.5px;white-space:nowrap"/g) || []).length);
  const oM = haiVung(veHop('', 'mtd'));
  t('🔴 bảng MTĐ xếp ngược lại — vai MTĐ ra ngoài, vai KVC vào nếp',
    oM.ngoai.indexOf('Quản Lý Máy Tự Động') >= 0 && oM.trong.indexOf('Quản Lý Khu Vui Chơi') >= 0, oM);
  const oL = haiVung(veHop('Quản Lý Máy Tự Động', 'kvc'));
  t('🔴 vai khối khác ĐANG TÍCH vẫn giữ dấu tích trong nếp',
    oL.trong.indexOf('Quản Lý Máy Tự Động ✓') >= 0, oL);
  t('🔴 và nếp ấy MỞ SẴN để không ai tưởng đã mất quyền',
    /<details class="vaiNgoai" open/.test(veHop('Quản Lý Máy Tự Động', 'kvc')));
  t('   còn khi không có gì tích thì nếp ĐÓNG cho gọn',
    /<details class="vaiNgoai"(?! open)/.test(HK), HK);
  t('🔴 không còn dấu cảnh báo nào trên ô tích chéo khối',
    veHop('Quản Lý Máy Tự Động', 'kvc').indexOf('⚠') < 0);
}

/* ═══ 6. ĐỔI Ô KHỐI THÌ DỰNG LẠI DANH SÁCH VAI NGAY, GIỮ Ô ĐANG TÍCH ═══════════
 * Bảng chỉ vẽ lại sau khi Lưu. Không dựng lại tại chỗ là người khai đổi khối xong vẫn nhìn
 * nguyên danh sách cũ và kết luận lọc không chạy. */
const DOI = bocSach('_khoiODoi');
t('🔴 ô chọn khối gọi `_khoiODoi` khi đổi', /onchange="_khoiODoi\(this\)"/.test(bocSach('_khoiSelLoai')), 'không thấy');
t('🔴 và nó GOM LẠI những ô đang tích trước khi dựng lại',
  /input:checked/.test(DOI) && /_vaiSelNhieu\(dang\.join/.test(DOI), 'không thấy');
t('   chỉ dựng lại hộp vai của ĐÚNG hàng ấy', /tr\.querySelector\('\[data-vai\]'\)/.test(DOI), 'không thấy');

/* ═══ 7. CỘT ĐƠN VỊ TRÊN BẢNG NGƯỜI DÙNG → CỘT KHỐI, TÍCH NHIỀU ════════════
 * Anh Thắng 21/09/2026: *"Chỗ đơn vị thay bằng khối — tích nếu 1 người làm 2 khối thì chọn 2,
 * vì có thể nv chung sẽ làm việc với 2 khối"*. Ảnh anh gửi: hộp ĐƠN VỊ xổ ra đúng MỘT dòng
 * "K&H" — một ô xổ một-lựa thì không nói được "người này làm cả hai bên". */
const KTN = bocSach('_khoiTichNguoi');
t('🔴 là ô TÍCH, không phải ô xổ một-lựa', /type="checkbox"/.test(KTN) && !/<select/.test(KTN), KTN);
t('   bày đủ ba khối, lấy từ cùng bảng với thanh nút khối', /KHOI_DS\.map/.test(KTN), 'không thấy');
/* 🔴 HAI CHỐT HỎNG LẶNG LẼ, ĐẮT NHẤT CỦA BẢN ĐỔI NÀY:
     1. `_readRows()` BỎ QUA `input[type=checkbox]`, nên giá trị phải nằm ở một ô ẩn — thiếu
        nó là tích xong bấm Lưu không lưu gì cả, mà trông y như máy chủ nuốt mất;
     2. đơn vị cũ vẫn là CỔNG QUYỀN THẬT ("đơn rơi về nhà nào, đọc được sổ nhà nào"), nên
        phải đi theo trong ô ẩn thứ hai — gửi rỗng lên là lượt Lưu đầu tiên đẩy CẢ CÔNG TY
        về nhà mẹ K&H, ai cũng đọc được sổ của mọi nhà — và không hoàn tác được. */
t('🔴 giá trị khối nằm ở ô ẩn cho `_readRows()` đọc', /data-khoi-ng/.test(KTN), 'không thấy');
t('🔴 đơn vị cũ đi theo trong ô ẩn thứ hai', /data-dv-cu/.test(KTN), 'không thấy');
t('🔴 và ĐÚNG THỨ TỰ khối-trước-đơn-vị-sau', KTN.indexOf('data-khoi-ng') < KTN.indexOf('data-dv-cu'), KTN);
t('🔴 lượt Lưu đọc đúng hai chỉ số ấy',
  /khoi:\(r\[7\]\|\|''\)\.trim\(\), donVi:\(r\[8\]\|\|''\)\.trim\(\)/.test(HTML), 'không thấy');
t('🔴 mỗi lượt bấm ô tích ghi lại chuỗi vào ô ẩn',
  /onchange="_khoiTichDoi\(this\)"/.test(KTN) && bocHam('_khoiTichDoi').length > 40, 'không thấy');
/* 🔴 CHẠY THẬT TRÊN HAI HÀNG. Dò chữ "có leo lên TD không" không đủ: đột biến đổi
   `td.querySelector` thành `document.querySelector` vẫn còn nguyên chữ ấy, mà hậu quả là MỌI
   hàng ghi đè vào ô ẩn của hàng ĐẦU — tích khối cho người thứ hai thì người thứ nhất ăn,
   và đúng một lượt Lưu là sai khối cả bảng. Phải có HAI hàng mới bắt được. */
try {
  const hangGia = (tich) => {
    const an = { value: '' };
    const oT = tich.map((x) => ({ value: x.ma, checked: x.tich }));
    return {
      an, tagName: 'TD',
      querySelector: (q) => (q === 'input[data-khoi-ng]' ? an : null),
      querySelectorAll: (q) => (q === '.khoiNg input:checked' ? oT.filter((o) => o.checked) : [])
    };
  };
  const td1 = hangGia([{ ma: 'kvc', tich: false }]);
  const td2 = hangGia([{ ma: 'mtd', tich: true }, { ma: 'vp', tich: true }]);
  /* `document` trỏ về HÀNG ĐẦU — đúng thứ mà đột biến sẽ vớ phải nếu nó bỏ mất `td.`. */
  const doc = { querySelector: () => td1.an };
  new Function('document', 'o', bocHam('_khoiTichDoi') + '\n_khoiTichDoi(o);')(doc, { parentNode: td2 });
  teq('🔴 tích ở hàng 2 chỉ ghi vào ô ẩn của HÀNG 2', 'mtd, vp', td2.an.value);
  teq('🔴 và hàng 1 không hề bị đụng tới', '', td1.an.value);
} catch (e) { t('chạy thật được `_khoiTichDoi` trên hai hàng', false, String(e)); }
t('🔴 đầu bảng đã đổi tên cột', HTML.indexOf('>Khối</th>') >= 0);

/* Chạy thật: hai khối đã khai phải ra hai ô đang tích và một chuỗi ngăn phẩy đúng. */
try {
  const ve = new Function('KHOI_DS', 'khoi', 'donVi',
    bocHam('esc') + '\n' + bocHam('_khoiTichNguoi') + '\nreturn _khoiTichNguoi(khoi, donVi);');
  const KD = [{ ma: 'kvc', ten: 'Khu vui chơi' }, { ma: 'mtd', ten: 'Máy tự động' }, { ma: 'vp', ten: 'Văn phòng' }];
  const h2 = ve(KD, 'kvc, mtd', 'K&H');
  teq('🔴 khai hai khối → đúng hai ô đang tích', 2, (h2.match(/ checked/g) || []).length);
  t('🔴 ô ẩn mang đúng chuỗi hai khối', /data-khoi-ng value="kvc, mtd"/.test(h2), h2);
  t('🔴 và đơn vị cũ đi theo nguyên vẹn', /data-dv-cu value="K&amp;H"/.test(h2), h2);
  const h0 = ve(KD, '', 'POSH');
  teq('   chưa khai khối nào → không ô nào tích', 0, (h0.match(/ checked/g) || []).length);
  t('   nhưng đơn vị cũ VẪN đi theo — đây là chỗ dễ mất nhất',
    /data-dv-cu value="POSH"/.test(h0), h0);
} catch (e) { t('chạy thật được `_khoiTichNguoi`', false, String(e)); }

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: ô tích vai xếp theo khối (khối khác vào nếp gấp), cột Khối tích được nhiều.');
