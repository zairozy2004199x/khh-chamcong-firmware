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
 * 🔴 CHỐT ĐẮT NHẤT: BỚT Ô ≠ BỎ QUYỀN ĐÃ KHAI
 * =============================================================================================
 * Lượt Lưu đọc CHÍNH mấy ô tích đang hiện (`[data-vai] input:checked`). Nên một vai lạc khối mà
 * đang được tích, nếu bị giấu đi, thì lượt Lưu KẾ TIẾP xoá đúng quyền ấy — lặng lẽ, không một
 * câu nào, và người bấm Lưu chỉ định sửa một ô khác hẳn. Anh bảo *"ai có ở khối nào mới hiện
 * ra"* là bớt ô TRỐNG cho dễ dò, không phải bỏ những gì đã khai.
 *
 * Vậy nên vai lạc khối mà ĐANG TÍCH thì vẫn bày, kèm dấu ⚠ để người khai tự gỡ.
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

['_boDauVai', '_khoiCuaVai', '_vaiOKhoi', '_vaiBay', '_vaiSelNhieu', '_khoiODoi'].forEach(function (x) {
  t('bốc được `' + x + '`', bocHam(x).length > 40, bocHam(x).length);
});

/* ═══ 1. CHẠY THẬT `_khoiCuaVai` QUA BẢNG VÀNG ═══════════════════════════════════
 * Dựng bằng `new Function` chứ không đọc chữ: đọc chữ thì mã sai luật vẫn xanh. */
const NGUON = bocBien('KHOI_THEO_TEN_VAI') + '\n' + bocHam('_boDauVai') + '\n' + bocHam('_khoiCuaVai')
  + '\n' + bocHam('_vaiOKhoi') + '\n' + bocHam('_vaiBay')
  + '\nreturn {khoiCuaVai:_khoiCuaVai, vaiOKhoi:_vaiOKhoi, vaiBay:_vaiBay, boDau:_boDauVai};';
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

  /* ═══ 3. 🔴 VAI LẠC KHỐI MÀ ĐANG TÍCH THÌ VẪN PHẢI BÀY ═════════════════════════
   * Chốt đắt nhất của bản này. Bỏ vế `da[…]` trong `_vaiBay()` là phép này đỏ. */
  t('🔴 vai lạc khối mà ĐANG TÍCH vẫn bày ra',
    M.vaiBay('Quản Lý Máy Tự Động', 'kvc', { 'quản lý máy tự động': 1 }));
  t('   vai lạc khối mà KHÔNG tích thì ẩn đi',
    !M.vaiBay('Quản Lý Máy Tự Động', 'kvc', {}));
  t('   khoá tra là chữ THƯỜNG (ô tích lưu nguyên văn hoa/thường)',
    M.vaiBay('QUẢN LÝ MÁY TỰ ĐỘNG', 'kvc', { 'quản lý máy tự động': 1 }));

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
t('🔴 và lọc danh sách vai con qua `_vaiBay`', /_vaiConCua\(cha\)\.filter\(/.test(SEL) && /_vaiBay\(/.test(SEL), 'không thấy');
t('   vai lạc khối được đánh dấu để người khai tự gỡ', /lacKhoi\s*=/.test(SEL), 'không thấy');
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
let HK = '';
try { HK = veHop('', 'kvc'); t('vẽ thật được hộp ô tích', HK.length > 100); }
catch (e) { t('vẽ thật được hộp ô tích', false, String(e)); }
if (HK) {
  const o = oTich(HK);
  t('🔴 KHÔNG có ô tích nào tên Admin', o.indexOf('Admin') < 0, o);
  t('🔴 bảng KVC không bay ô của Máy tự động',
    o.indexOf('Quản Lý Máy Tự Động') < 0 && o.indexOf('Kế Toán Máy Tự Động') < 0, o);
  t('🔴 cũng không bày ô của Văn phòng', o.indexOf('Quản Lý VP Chung') < 0, o);
  t('   vẫn bày ô của Khu vui chơi', o.indexOf('Quản Lý Khu Vui Chơi') >= 0, o);
  t('🔴 và vẫn bày vai chạy ngang (Marketing + bốn vai gốc)',
    o.indexOf('Nhân Viên Marketing') >= 0 && o.indexOf('Quản lý') >= 0 && o.indexOf('Kế toán NCC') >= 0, o);
  /* Phép đối chứng: bảng MTĐ phải ra danh sách KHÁC. Không có phép này thì một hàm trả về
     danh sách cố định cũng qua được hết mấy phép trên. */
  const oM = oTich(veHop('', 'mtd'));
  t('🔴 bảng MTĐ bày ô của Máy tự Động, không bày ô của KVC',
    oM.indexOf('Quản Lý Máy Tự Động') >= 0 && oM.indexOf('Quản Lý Khu Vui Chơi') < 0, oM);
  /* 🔴 CHỐT ĐẮT NHẤT, đếm trên ô thật: quyền đã khai cho một vai lạc khối phải còn đó,
     và phải còn đó Ở TRẠNG THÁI ĐANG TÍCH — bày ra mà mất dấu tích thì lượt Lưu vẫn xoá. */
  const oL = oTich(veHop('Quản Lý Máy Tự Động', 'kvc'));
  t('🔴 vai lạc khối ĐANG TÍCH vẫn bày, và vẫn còn dấu tích',
    oL.indexOf('Quản Lý Máy Tự Động ✓') >= 0, oL);
  t('   ô lạc khối được đánh dấu ⚠ để người khai tự gỡ',
    veHop('Quản Lý Máy Tự Động', 'kvc').indexOf('⚠') >= 0);
  t('   còn ô hợp khối đã tích thì KHÔNG bị đánh dấu',
    veHop('Quản Lý Khu Vui Chơi', 'kvc').indexOf('⚠') < 0);
}

/* ═══ 6. ĐỔI Ô KHỐI THÌ DỰNG LẠI DANH SÁCH VAI NGAY, GIỮ Ô ĐANG TÍCH ═══════════
 * Bảng chỉ vẽ lại sau khi Lưu. Không dựng lại tại chỗ là người khai đổi khối xong vẫn nhìn
 * nguyên danh sách cũ và kết luận lọc không chạy. */
const DOI = bocSach('_khoiODoi');
t('🔴 ô chọn khối gọi `_khoiODoi` khi đổi', /onchange="_khoiODoi\(this\)"/.test(bocSach('_khoiSelLoai')), 'không thấy');
t('🔴 và nó GOM LẠI những ô đang tích trước khi dựng lại',
  /input:checked/.test(DOI) && /_vaiSelNhieu\(dang\.join/.test(DOI), 'không thấy');
t('   chỉ dựng lại hộp vai của ĐÚNG hàng ấy', /tr\.querySelector\('\[data-vai\]'\)/.test(DOI), 'không thấy');

/* ═══ 7. GỢI Ý ĐƠN VỊ THEO KHỐI Ở BẢNG NGƯỜI DÙNG ═════════════════════════════
 * Anh Thắng 21/09/2026: *"chọn nhân viên theo khối"* — ảnh anh gửi: hộp xổ ra đúng MỘT dòng
 * "K&H", nên không cách nào bỏ ai vào Máy tự động. `BOOT.donVi` dựng từ đơn vị ĐANG CÓ THẬT,
 * nên chưa ai thuộc MTĐ thì MTĐ không hiện — vòng luẩn quẩn. */
const DV = bocSach('_dvInp');
t('🔴 gợi ý luôn có đủ ba khối, kể cả khi sổ chưa có ai', /DV_KHOI_GOI\.forEach/.test(DV), 'không thấy');
t('🔴 chỉ THÊM vào gợi ý, không thay danh sách thật', /BOOT\.donVi\|\|\['K&H'\]\)\.slice\(\)/.test(DV), 'không thấy');
t('   và không thêm trùng cái đã có', /indexOf\(g\.ma\.toLowerCase\(\)\)<0/.test(DV), 'không thấy');
t('🔴 VẪN là ô nhập kèm gợi ý, không phải ô xổ đóng (chi nhánh thứ tư khai được ngay)',
  /<input list="dl_donvi"/.test(DV) && !/<select/.test(DV), 'không thấy');
teq('ba gợi ý là mã máy chủ nhận, không phải tên đẹp', true,
  /ma:'KVC'/.test(bocBien('DV_KHOI_GOI')) && /ma:'MTĐ'/.test(bocBien('DV_KHOI_GOI')) && /ma:'VP'/.test(bocBien('DV_KHOI_GOI')));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: ô tích vai lọc theo khối, vai lạc khối đang tích vẫn bày, gợi ý đơn vị đủ ba khối.');
