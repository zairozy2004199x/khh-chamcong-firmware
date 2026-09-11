/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐƠN GHÉP NHIỀU GIAN: CHỌN CHI PHÍ → NHẬP NỘI DUNG → RỒI MỚI GẮN CƠ SỞ.
 *
 * Anh Thắng 09/09/2026, nhìn màn nhập hạng mục: *"chỗ này vẫn theo cơ chế cơ sở, phải theo kiểu,
 * chọn chi phí, nhập nội dung và chọn cơ sở đó thuộc chi phí nào là được"*.
 *
 * =============================================================================================
 * 🔴 VÒNG KHOÁ NẰM Ở MÃ TÀI KHOẢN. Mã khai theo MẢNG (phân loại lớn) của cơ sở, nên chưa chọn
 *    cơ sở thì `_tkNoList()` trả rỗng -> ô Loại chi phí trống trơn -> người dùng bị đẩy ngược
 *    về chọn cơ sở trước, dù ô cơ sở đã dời xuống cuối. Dời ô mà không nới chỗ này thì chỉ đổi
 *    được chỗ NHÌN, còn thứ tự làm việc vẫn y nguyên.
 *
 * 🔴 CHỈ NỚI CHO ĐƠN GHÉP NHIỀU GIAN. Đơn KVC nới ra là ô Loại chi phí bày cả mã của mảng khác,
 *    người nhập chọn nhầm thì tiền vào sai tài khoản — mà nhìn bảng không thấy sai.
 *
 * ⚠️ CHẠY THẬT `_tkNoList` / `_tkNoCua` bốc từ app.html.
 *
 * Chạy: node tools/test/kiem-nhap-coso-sau.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

/* ── Bệ đỡ: bốc CHÍNH mấy hàm ra chạy ───────────────────────────────────────────────────── */
function boc(ten) {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) { console.log('\n✗ Không bốc được — dừng.'); process.exit(1); }
  return HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
}
/* Hai gian ở HAI MẢNG khác nhau — đúng ca đáng lo: nới sai là trộn mã của hai mảng. */
/* 🔴 `cosoPll` CHỨA CẢ CƠ SỞ NGƯỜI NÀY KHÔNG ĐƯỢC CHỌN (bảng tra dựng từ TOÀN BỘ danh mục, còn
   `BOOT.coso` mới là danh sách đã lọc theo đơn vị). Gom mảng theo `cosoPll` là kéo cả mảng của
   đơn vị bên kia vào ô Loại chi phí — vừa bày mã không dùng được, vừa hở ranh giới hai mảng. */
const BOOT = {
  coso: ['POSH HCM', 'POSH Gò Vấp', 'Aeon Bình Tân'],
  cosoPll: { 'posh hcm': 'POSH-MN', 'posh gò vấp': 'POSH-MN', 'aeon bình tân': 'FZ MN',
             'gian của đơn vị khác': 'HN-MB' },
  tkNoMx: {
    'nước uống':  { 'posh-mn': ['6428'], 'fz mn': ['6421'] },
    'thuê mặt bằng': { 'fz mn': ['6427'] },
    'phí giữ xe': { 'posh-mn': ['6425', '6426'] }
  },
  loaiChiPhi: [ { ten: 'Nước uống', tkNo: '' }, { ten: 'Thuê mặt bằng', tkNo: '' }, { ten: 'Phí giữ xe', tkNo: '' } ]
};
let CUR = null;
const src = boc('_donNhieuCoSo') + boc('_mangPham') + boc('_mangCua') + boc('_tkNoList') + boc('_tkNoCua')
  + '; return { l: _tkNoList, c: _tkNoCua, m: _mangPham, dn: _donNhieuCoSo };';
/* ⚠️ `_donNhieuCoSo` nay hỏi thêm `CUR_PAGE` / `DA_CUR` (đơn chi phí cơ sở của Kỹ thuật cũng là
   đơn nhiều gian). Bài này kiểm ĐƠN TUẦN, nên dựng đúng bối cảnh ấy: đang ở tab đơn, chưa mở
   dự án nào. Khai thiếu là bài kiểm nổ ReferenceError, trông như mã hỏng. */
const mk = CURv => new Function('BOOT', 'CUR', 'CUR_PAGE', 'DA_CUR', src)(BOOT, CURv, 'don', null);

const KVC  = mk({ don: { nhieuCoSo: false } });
const POSH = mk({ don: { nhieuCoSo: true  } });

/* ── 1. 🔴 CHƯA CHỌN CƠ SỞ ───────────────────────────────────────────────────────────────── */
teq('🔴 KVC · chưa chọn gian → KHÔNG có mã (vẫn phải chọn gian trước)', [], KVC.l('Nước uống', ''));
teq('🔴 POSH · chưa chọn gian → CÓ mã, gom mọi mảng', ['6428', '6421'], POSH.l('Nước uống', ''));
teq('   loại chỉ khai ở một mảng vẫn hiện', ['6427'], POSH.l('Thuê mặt bằng', ''));
teq('   loại khai hai mã trong CÙNG mảng → giữ đủ hai', ['6425', '6426'], POSH.l('Phí giữ xe', ''));
teq('   loại không có trong ma trận → rỗng', [], POSH.l('Không có', ''));
/* Gom nhiều mảng thì không được đẻ mã trùng — mỗi mã trùng là một dòng thừa trong ô chọn. */
const BOOT2 = JSON.parse(JSON.stringify(BOOT));
BOOT2.tkNoMx['nước uống']['fz mn'] = ['6428'];
const P2 = new Function('BOOT', 'CUR', src)(BOOT2, { don: { nhieuCoSo: true } });
teq('🔴 hai mảng cùng một mã → chỉ ra MỘT dòng', ['6428'], P2.l('Nước uống', ''));

/* ── 2. 🔴 CHỌN GIAN RỒI THÌ HẸP LẠI ĐÚNG GIAN ẤY ───────────────────────────────────────── */
teq('🔴 POSH · chọn POSH HCM → chỉ mã của mảng ấy', ['6428'], POSH.l('Nước uống', 'POSH HCM'));
teq('🔴 POSH · chọn Aeon (mảng FZ) → mã của FZ, KHÔNG kéo theo mã POSH',
  ['6421'], POSH.l('Nước uống', 'Aeon Bình Tân'));
teq('   loại không khai cho mảng ấy → rỗng, không mượn mảng khác',
  [], POSH.l('Thuê mặt bằng', 'POSH HCM'));
teq('   KVC chọn gian → y như cũ', ['6421'], KVC.l('Nước uống', 'Aeon Bình Tân'));

/* ── 3. MẢNG PHẠM VI ─────────────────────────────────────────────────────────────────────── */
teq('gom đúng các mảng của những gian chọn được', ['posh-mn', 'fz mn'].sort(), POSH.m().sort());
t('🔴 KHÔNG kéo mảng của đơn vị khác vào (nguồn là BOOT.coso đã lọc, không phải cosoPll)',
  POSH.m().indexOf('hn-mb') < 0, POSH.m());
BOOT.tkNoMx['nước uống']['hn-mb'] = ['9999'];
teq('   nên mã của đơn vị khác cũng không lọt vào ô chọn', ['6428', '6421'], POSH.l('Nước uống', ''));
delete BOOT.tkNoMx['nước uống']['hn-mb'];

/* ── 4. Ô CƠ SỞ XUỐNG CUỐI, VÀ CHỈ VỚI ĐƠN GHÉP NHIỀU GIAN ──────────────────────────────── */
t('🔴 ô cơ sở đổi chỗ bằng `order` của lưới, không bê thẻ đi',
  HTML.indexOf("fld.style.order='99'") >= 0 && HTML.indexOf("fld.style.order=''") >= 0);
t('   và trả về chỗ cũ cho đơn một-gian', /_donNhieuCoSo\(\)\)\{[\s\S]{0,200}?order='99'[\s\S]{0,200}?\} else \{[\s\S]{0,120}?order=''/.test(HTML));
t('   nhãn nói rõ gian là của TỪNG hạng mục', HTML.indexOf("lbl.textContent='Cơ sở của hạng mục này *'") >= 0);
t('   và trả nhãn về "Cơ sở *" cho đơn một-gian', HTML.indexOf("lbl.textContent='Cơ sở *'") >= 0);
t('🔴 xếp lại ô mỗi lần mở đơn (không thì đơn sau còn giữ chỗ của đơn trước)',
  HTML.indexOf('_xepOCoSo();') >= 0);

/* ── 5. ĐỔI GIAN KHÔNG ĐƯỢC XOÁ THỨ NGƯỜI TA VỪA CHỌN ───────────────────────────────────── */
const cd = boc('_cosoDoi');
t('🔴 đổi gian GIỮ NGUYÊN loại chi phí đang chọn', /var cu=String\(\(el\('f_nhom'\)/.test(cd) && cd.indexOf('fillNhom(cu)') >= 0, cd);
t('🔴 và vẽ lại nhãn mã TK theo gian mới', cd.indexOf('showTkNhom()') >= 0, cd);

/* ── 6. CÂU NHẮC ĐÚNG CA ─────────────────────────────────────────────────────────────────── */
t('🔴 đơn ghép nhiều gian KHÔNG bị nhắc "Chọn CƠ SỞ trước"',
  HTML.indexOf('Chọn loại chi phí và nhập nội dung trước') >= 0);
t('   câu nhắc cũ vẫn còn cho đơn một-gian', HTML.indexOf('⬆ Chọn CƠ SỞ trước') >= 0);
t('   và nếu THẬT SỰ chưa khai mã nào thì nói thẳng, không nhắc suông',
  HTML.indexOf('Chưa loại chi phí nào khai mã cho các mảng của đơn vị này') >= 0);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: đơn POSH chọn chi phí trước, gắn gian sau; đơn KVC giữ nguyên.');
