/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NÚT GỬI XIN TẠM ỨNG NGAY TRÊN BẢNG DỰ ÁN — và lối riêng cho đơn kế toán trả thẳng NCC.
 *
 * Anh Thắng 10/09/2026: *"em làm tiếp đi, chưa thấy có nút gửi tạm ứng"*.
 * =============================================================================================
 * Chuỗi trạng thái vốn đã chạy (nhap → xin → duyet → ung → xong), nhưng MỌI NÚT lại nằm ở màn
 * Duyệt của KẾ TOÁN — mà bước ĐẦU TIÊN là việc của NHÂN VIÊN, người chẳng bao giờ mở màn ấy.
 * Nút không có ở đâu thì đơn nằm mãi ở "Đang nhập" và kế toán chờ một cái không ai gửi được.
 *
 * Và: *"trong phần này dù không xin tạm ứng, nhưng vẫn có phần kế toán đã xác nhận đi đơn nào
 * thì tích vào và khóa đơn đó cho nhân viên biết và kèm gửi ủy nhiệm chi cho đơn đó thay vì
 * nhân viên gửi (người gửi lỡ người kia quên)"*.
 *
 * 🔴 ĐƠN 🏢 TRỰC TIẾP ĐI ĐƯỜNG KHÁC HẲN. Tiền không qua tay nhân viên nên "xin tạm ứng" vô
 *    nghĩa: nhân viên chẳng xin gì cả. Bày nút "📤 Xin tạm ứng" ở đó là mời gửi một đơn không
 *    ai cấp được. Kế toán tự tích khi đã chi, đính uỷ nhiệm chi, rồi khoá.
 *
 * 🔴 NÚT PHẢI HIỆN CẢ KHI KHÔNG SỬA ĐƯỢC ĐƠN. Kế toán thường không có quyền sửa dòng dự án;
 *    nhét nút trạng thái vào nhánh `canEdit` là giấu mất nút của chính người phải bấm.
 *
 * ⚠️ CHẠY THẬT `hmNutDaBang()` bốc từ mã nguồn — không chép mã vào bài kiểm.
 *
 * Chạy: node tools/test/kiem-nut-tam-ung-bang-du-an.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};
/* HM_NHAN là một var toàn cục trong app.html — bốc nguyên dòng khai báo, không chép lại. */
const bocVar = ten => {
  const i = HTML.indexOf('var ' + ten + '=');
  t('bốc được var ' + ten, i >= 0);
  if (i < 0) return '';
  const j = HTML.indexOf('};', i);
  return HTML.slice(i, j + 2);
};

function nut(opt) {
  opt = opt || {};
  const moi = {
    CURUSER: { role: opt.role || 'Nhân viên' },
    DA_CUR: { maDA: 'DA1', editable: !!opt.editable, thiCong: !!opt.thiCong, isCoSo: !!opt.isCoSo },
    esc: x => String(x == null ? '' : x),
    HM_NHAN: null,
  };
  const src = `${bocVar('HM_NHAN')}
    ${boc('_hmNhanCua')}
    ${boc('_hmLaKT')}
    ${boc('_hmLaDuyet')}
    ${boc('hmNutDaBang')}
    return hmNutDaBang;`;
  const f = new Function('moi', `with(moi){ ${src} }`)(moi);
  return f({ row: 7, noiDung: 'Xe ba gác', hinhThuc: opt.hinhThuc || '',
             hm: { tt: opt.tt || 'nhap', dot: opt.dot || 0 } });
}

/* ── 1. 🔴 NHÂN VIÊN PHẢI THẤY NÚT GỬI XIN TẠM ỨNG ─────────────────────────────────────── */
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'nhap' });
  t('🔴 đơn tạm ứng đang nhập → nhân viên THẤY nút gửi xin tạm ứng',
    /hmMoXin\(7\)/.test(h) && /Xin tạm ứng/.test(h), h);
  t('   và thấy đơn đang ở trạng thái nào', /Đang nhập/.test(h), h);
  t('   nhưng KHÔNG được tự duyệt cho chính mình', !/'duyet'/.test(h) && !/Duyệt/.test(h), h);
  t('   cũng không tự cấp tạm ứng cho chính mình', !/hmCapUngDA/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', thiCong: true, tt: 'tra' });
  t('🔴 đơn BỊ TRẢ LẠI → vẫn gửi lại được (không thì đơn chết cứng ở đó)',
    /hmMoXin\(7\)/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', tt: 'nhap' });
  t('đơn đã đóng (không sửa được) → không bày nút gửi', !/hmMoXin/.test(h), h);
  t('   nhưng vẫn cho biết đang ở trạng thái nào', /Đang nhập/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', isCoSo: true, tt: 'nhap' });
  t('dự án chi phí cơ sở (luôn nhập được) → cũng gửi được', /hmMoXin/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'xin' });
  t('🔴 đã gửi rồi → KHÔNG bày nút gửi nữa (bấm hai lần là hai đơn cho một việc)',
    !/hmMoXin/.test(h), h);
  t('   và nói rõ đang chờ duyệt', /Chờ duyệt/.test(h), h);
}

/* ── 2. QUẢN LÝ / KẾ TOÁN ─────────────────────────────────────────────────────────────── */
{
  const h = nut({ role: 'Quản lý', tt: 'xin' });
  t('🔴 quản lý duyệt được, dù KHÔNG sửa được dòng dự án',
    /hmDatDA\(7,'duyet'\)/.test(h), h);
  t('   và trả lại được', /hmDatDA\(7,'tra'\)/.test(h), h);
  t('   nhưng chưa cấp tiền được (đó là việc của kế toán)', !/hmCapUngDA/.test(h), h);
}
{
  const h = nut({ role: 'Quản lý', tt: 'duyet' });
  t('🔴 quản lý KHÔNG cấp tạm ứng được', !/hmCapUngDA/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán cá nhân', tt: 'duyet', dot: 1 });
  t('🔴 kế toán cấp được ĐỢT KẾ TIẾP (đợt 2 sau khi đã cấp đợt 1)',
    /hmCapUngDA\(7,2\)/.test(h) && /Cấp đợt 2/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'ung' });
  t('đã cấp tiền → có nút chốt xong', /hmChotDA\(7\)/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán NCC', tt: 'xong' });
  t('🔴 đã khoá → chỉ kế toán mở lại được', /hmDatDA\(7,'nhap'\)/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'xong' });
  t('🔴 nhân viên KHÔNG mở lại được đơn đã khoá', !/hmDatDA/.test(h), h);
  t('   nhưng thấy nó đã chốt', /Đã chốt/.test(h), h);
}

/* ── 3. 🏢 ĐƠN KẾ TOÁN TRẢ THẲNG NCC ──────────────────────────────────────────────────── */
{
  const h = nut({ role: 'Nhân viên', editable: true, hinhThuc: 'Trực tiếp', tt: 'nhap' });
  t('🔴 đơn Trực tiếp → KHÔNG bày nút xin tạm ứng (nhân viên chẳng xin gì cả)',
    !/hmMoXin/.test(h), h);
  t('🔴 và nói rõ đang chờ AI: kế toán xác nhận đã chi',
    /Chờ kế toán xác nhận đã chi/.test(h), h);
  t('   nhân viên không tự tích được', !/hmNccMo/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán NCC', hinhThuc: 'Trực tiếp', tt: 'nhap' });
  t('🔴 kế toán TÍCH được ngay, không cần ai xin trước', /hmNccMo\(7\)/.test(h), h);
  t('   nút nói rõ là tích đã chi rồi khoá đơn', /KT đã chi/.test(h) && /khoá đơn/.test(h), h);
  t('   và KHÔNG đi qua nút cấp tạm ứng', !/hmCapUngDA/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán cá nhân', hinhThuc: 'Trực tiếp', tt: 'xong' });
  t('🔴 đơn NCC đã khoá → nhãn nói rõ KT đã chi và đã khoá',
    /đã khoá/.test(h), h);
  t('   kế toán mở lại được', /hmDatDA\(7,'nhap'\)/.test(h), h);
  t('   và không bày lại nút tích', !/hmNccMo/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, hinhThuc: 'Trực tiếp', tt: 'xong' });
  t('🔴 nhân viên NHÌN THẤY đơn NCC đã khoá (đó là mục đích của việc tích)',
    /đã khoá/.test(h), h);
  t('   nhưng không mở lại được', !/hmDatDA/.test(h), h);
}

/* ── 4. HÀNG NHẬP INLINE ──────────────────────────────────────────────────────────────── */
t('🔴 nút trạng thái nằm NGOÀI nhánh canEdit (kế toán không sửa được dòng vẫn phải thấy nút)',
  HTML.indexOf("</button>'):'')+addChild+'</td></tr>'") >= 0);
t('   hàng hạng mục lớn có chỗ mở ô nhập ngay dưới', HTML.indexOf("function hmDongXin(p){") >= 0);
t('   hàng ấy trải hết bề ngang bảng', /data-xin="'\+p\.row\+'"[^]{0,120}colspan="13"/.test(HTML));
t('🔴 KHÔNG hỏi lịch đợt bằng prompt (gõ ngày trong hộp thoại là mời gõ sai)',
  HTML.indexOf("function hmMoXin(row){") >= 0 && /data-xnd=/.test(HTML) && /type="date"/.test(HTML));
t('   ô tiền của lịch đợt có dấu chấm hàng nghìn', /data-xst=[^]{0,200}tienVao\(this\)/.test(HTML));
t('🔴 ô uỷ nhiệm chi và hoá đơn của kế toán là Ô NHẬP, không phải prompt',
  /data-nunc=/.test(HTML) && /data-nhd=/.test(HTML));

/* ── 5. GỬI ĐI ─────────────────────────────────────────────────────────────────────────── */
function chayGui(vals) {
  const NK = { gui: null, toast: [] };
  const moi = {
    DA_CUR: { maDA: 'DA1' },
    toast: (k, m) => NK.toast.push([k, m]),
    _tienSo: v => { const s = String(v == null ? '' : v).replace(/[^0-9-]/g, ''); return s === '' || s === '-' ? '' : String(Number(s)); },
    hmDatDA: (row, tt, them) => { NK.gui = { row, tt, them }; },
    document: { querySelector: sel => {
      const m = sel.match(/data-(xnd|xst|nunc|nhd)="([^"]+)"/);
      if (!m) return null;
      const k = m[1] + ':' + m[2];
      return (k in vals) ? { value: vals[k] } : (m[1] === 'xnd' || m[1] === 'xst' ? { value: '' } : null);
    } },
  };
  const f = new Function('moi', `with(moi){ ${boc('hmGuiXin')}\n${boc('hmNccChot')}
    return { xin: hmGuiXin, ncc: hmNccChot }; }`)(moi);
  return { NK, f };
}
{
  const { NK, f } = chayGui({});
  f.xin(7);
  t('🔴 không gõ lịch nào → vẫn gửi được (phần đông chỉ xin một lần)',
    NK.gui && NK.gui.tt === 'xin' && NK.gui.them.lich.length === 0, NK.gui);
}
{
  const { NK, f } = chayGui({ 'xnd:7_1': '2026-09-15', 'xst:7_1': '5.000.000',
                              'xnd:7_3': '2026-10-01', 'xst:7_3': '' });
  f.xin(7);
  t('🔴 gõ lịch đợt 1 và đợt 3 → gửi đúng hai đợt, đúng ngày',
    NK.gui && NK.gui.them.lich.length === 2
    && NK.gui.them.lich[0].ngay === '2026-09-15'
    && NK.gui.them.lich[1].ngay === '2026-10-01', NK.gui && NK.gui.them);
  t('🔴 số tiền BỎ dấu chấm trước khi gửi (gửi "5.000.000" là máy chủ đọc thành 5)',
    NK.gui && NK.gui.them.lich[0].soTien === '5000000', NK.gui && NK.gui.them);
  t('   đợt không gõ tiền vẫn nhận (kế toán biết ngày là đủ để xếp lịch)',
    NK.gui && NK.gui.them.lich[1].soTien === 0, NK.gui && NK.gui.them);
}
{
  const { NK, f } = chayGui({ 'xnd:7_2': '', 'xst:7_2': '3000000' });
  t('🔴 có tiền mà KHÔNG có ngày → bỏ đợt ấy (một đợt không ngày thì chuẩn bị tiền vào hôm nào?)',
    (f.xin(7), NK.gui && NK.gui.them.lich.length === 0), NK.gui && NK.gui.them);
}
{
  const { NK, f } = chayGui({ 'nunc:7': 'UNC-88', 'nhd:7': 'https://hd/1' });
  f.ncc(7);
  t('🔴 kế toán tích → gửi thẳng lệnh KHOÁ, kèm uỷ nhiệm chi và hoá đơn',
    NK.gui && NK.gui.tt === 'xong' && NK.gui.them.unc === 'UNC-88'
    && NK.gui.them.hoaDon === 'https://hd/1', NK.gui);
}
{
  const { NK, f } = chayGui({ 'nunc:7': 'UNC-88', 'nhd:7': '   ' });
  f.ncc(7);
  t('🔴 THIẾU HOÁ ĐƠN → chối (khoá một con số không có gì đỡ thì lúc đối chiếu không gỡ ra được)',
    NK.gui === null, NK.gui);
  t('   và nói rõ vì sao', NK.toast.some(x => /hoá đơn/.test(x[1])), NK.toast);
}
{
  const { NK, f } = chayGui({ 'nunc:7': '', 'nhd:7': 'https://hd/2' });
  f.ncc(7);
  t('thiếu uỷ nhiệm chi thì vẫn khoá được (có đơn trả bằng tiền mặt)',
    NK.gui && NK.gui.tt === 'xong' && NK.gui.them.unc === '', NK.gui);
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: nhân viên gửi được xin tạm ứng, kế toán tích khoá được đơn NCC.');
