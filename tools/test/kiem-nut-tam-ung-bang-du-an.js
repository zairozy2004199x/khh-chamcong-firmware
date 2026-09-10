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
    ${boc('hmNhanChung')}
    ${boc('hmNutChung')}
    ${boc('hmNutDaBang')}
    ${boc('_hmKeyDA')}
    return opt.duyet
      ? hmNutChung('D1_7', 'DA1', 7, opt.hinhThuc || '', HM, false, true)
      : hmNutDaBang({ row: 7, noiDung: 'Xe ba gác', hinhThuc: opt.hinhThuc || '', hm: HM });`;
  moi.opt = opt;
  moi.HM = { tt: opt.tt || 'nhap', dot: opt.dot || 0 };
  return new Function('moi', `with(moi){ ${src} }`)(moi);
}

/* ── 1. 🔴 NHÂN VIÊN PHẢI THẤY NÚT GỬI XIN TẠM ỨNG ─────────────────────────────────────── */
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'nhap' });
  t('🔴 đơn tạm ứng đang nhập → nhân viên THẤY nút gửi xin tạm ứng',
    /hmMoXin\('P7','DA1',7\)/.test(h) && /Xin tạm ứng/.test(h), h);
  t('   và thấy đơn đang ở trạng thái nào', /Đang nhập/.test(h), h);
  t('   nhưng KHÔNG được tự duyệt cho chính mình', !/'duyet'/.test(h) && !/Duyệt/.test(h), h);
  t('   cũng không tự cấp tạm ứng cho chính mình', !/hmMoCap/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', thiCong: true, tt: 'tra' });
  t('🔴 đơn BỊ TRẢ LẠI → vẫn gửi lại được (không thì đơn chết cứng ở đó)',
    /hmMoXin\('P7','DA1',7\)/.test(h), h);
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
    /hmDat\('DA1',7,'duyet'/.test(h), h);
  t('   và trả lại được', /hmDat\('DA1',7,'tra'/.test(h), h);
  t('   nhưng chưa cấp tiền được (đó là việc của kế toán)', !/hmMoCap/.test(h), h);
}
{
  const h = nut({ role: 'Quản lý', tt: 'duyet' });
  t('🔴 quản lý KHÔNG cấp tạm ứng được', !/hmMoCap/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán cá nhân', tt: 'duyet', dot: 1 });
  t('🔴 kế toán cấp được ĐỢT KẾ TIẾP (đợt 2 sau khi đã cấp đợt 1)',
    /hmMoCap\('P7','DA1',7,2\)/.test(h) && /Cấp đợt 2/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'ung' });
  t('đã cấp tiền → có nút chốt xong', /hmMoChot\('P7','DA1',7\)/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán NCC', tt: 'xong' });
  t('🔴 đã khoá → chỉ kế toán mở lại được', /hmDat\('DA1',7,'nhap'/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'xong' });
  t('🔴 nhân viên KHÔNG mở lại được đơn đã khoá', !/hmDat\(/.test(h), h);
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
  t('🔴 kế toán TÍCH được ngay, không cần ai xin trước', /hmNccMo\('P7','DA1',7\)/.test(h), h);
  t('   nút nói rõ là tích đã chi rồi khoá đơn', /KT đã chi/.test(h) && /khoá đơn/.test(h), h);
  t('   và KHÔNG đi qua nút cấp tạm ứng', !/hmMoCap/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán cá nhân', hinhThuc: 'Trực tiếp', tt: 'xong' });
  t('🔴 đơn NCC đã khoá → nhãn nói rõ KT đã chi và đã khoá',
    /đã khoá/.test(h), h);
  t('   kế toán mở lại được', /hmDat\('DA1',7,'nhap'/.test(h), h);
  t('   và không bày lại nút tích', !/hmNccMo/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, hinhThuc: 'Trực tiếp', tt: 'xong' });
  t('🔴 nhân viên NHÌN THẤY đơn NCC đã khoá (đó là mục đích của việc tích)',
    /đã khoá/.test(h), h);
  t('   nhưng không mở lại được', !/hmDat\(/.test(h), h);
}

/* ── 4. HÀNG NHẬP INLINE ──────────────────────────────────────────────────────────────── */
t('🔴 nút trạng thái nằm NGOÀI nhánh canEdit (kế toán không sửa được dòng vẫn phải thấy nút)',
  HTML.indexOf("</button>'):'')+addChild+'</td></tr>'") >= 0);
t('   hàng hạng mục lớn có chỗ mở ô nhập ngay dưới', HTML.indexOf("function hmDongXin(p){") >= 0);
t('   hàng ấy trải hết bề ngang bảng', /function hmDongXin\(p\)\{ return hmDongForm\(_hmKeyDA\(p\.row\), 13\)/.test(HTML));
t('🔴 KHÔNG hỏi lịch đợt bằng prompt (gõ ngày trong hộp thoại là mời gõ sai)',
  HTML.indexOf('function hmMoXin(k, maDA, row){') >= 0 && /data-xnd=/.test(HTML) && /type="date"/.test(HTML));
t('   ô tiền của lịch đợt có dấu chấm hàng nghìn', /data-xst=[^]{0,200}tienVao\(this\)/.test(HTML));
/* Vẽ THẬT ba cái ô nhập rồi soi, thay vì dò chuỗi: tên thuộc tính được ghép động
   ('data-hm'+o) nên trong mã nguồn không có chuỗi nào để dò. */
function veForm(ham, ...them) {
  const NK = { html: '', hien: false };
  const moi = {
    esc: x => String(x == null ? '' : x),
    toast: () => {}, HM_ITEMS: [], DA_CUR: null,
    document: { querySelector: () => ({
      querySelector: () => ({ set innerHTML(v) { NK.html = v; }, get innerHTML() { return NK.html; } }),
      style: { set display(v) { NK.hien = (v === ''); } },
      scrollIntoView() {},
    }) },
  };
  const src = `${boc('_hmO')}\n${boc('_hmMo')}\n${boc('_hmTen')}
    ${boc('_hmOTep')}\n${boc('_hmNutDay')}\n${boc(ham)}\n return ${ham};`;
  new Function('moi', `with(moi){ ${src} }`)(moi)('P7', 'DA1', 7, ...them);
  return NK;
}
{
  const f = veForm('hmNccMo').html;
  t('🔴 ô uỷ nhiệm chi và hoá đơn của kế toán là Ô NHẬP, không phải prompt',
    /data-hmunc="P7"/.test(f) && /data-hmhd="P7"/.test(f), f);
  t('   cả hai ô đều có nút chọn tệp', (f.match(/hmDinhTep\(/g) || []).length === 2, f);
  t('🔴 nói rõ hoá đơn là BẮT BUỘC, uỷ nhiệm chi thì không',
    /Hoá đơn[^]{0,80}bắt buộc/.test(f) && !/Uỷ nhiệm chi[^]{0,60}bắt buộc/.test(f), f);
  t('   nút gửi truyền đủ khoá, mã dự án và số dòng',
    /hmNccChot\('P7','DA1',7\)/.test(f), f);
}
{
  const f = veForm('hmMoChot').html;
  t('🔴 chốt xong cũng đính được tệp hoá đơn (không bắt dán liên kết)',
    /data-hmhd="P7"/.test(f) && /hmDinhTep\(/.test(f), f);
  t('   nhưng KHÔNG hỏi uỷ nhiệm chi (đó là chứng từ của bước cấp tiền)',
    !/data-hmunc=/.test(f), f);
  t('🔴 chốt xong nói rõ hoá đơn là BẮT BUỘC (máy chủ chối, người dùng phải biết trước)',
    /Hoá đơn[^]{0,80}bắt buộc/.test(f), f);
}
/* 🔴 NÚT CHỌN TỆP PHẢI NHÌN THẤY ĐƯỢC. Nút còn trong mã mà bị giấu đi thì cũng như không có —
   kế toán lại phải tự tải tệp lên chỗ khác rồi quay lại dán liên kết. */
['hmNccMo', 'hmMoChot'].forEach(ham => {
  const f = veForm(ham, 2).html;
  const nut = (f.match(/<button[^>]*hmDinhTep\([^>]*>/g) || []);
  t('🔴 ' + ham + ': nút chọn tệp không bị giấu',
    nut.length > 0 && !nut.some(x => /display\s*:\s*none/.test(x)), nut);
});
{
  const f = veForm('hmMoCap', 2).html;
  t('🔴 cấp tạm ứng đính được uỷ nhiệm chi của ĐỢT ấy',
    /data-hmunc="P7"/.test(f) && /hmGuiCap\('P7','DA1',7,2\)/.test(f), f);
  t('   nói rõ đang cấp đợt mấy', /đợt 2/.test(f), f);
  t('   và KHÔNG đòi hoá đơn ở bước cấp tiền (hoá đơn về sau, lúc chốt)',
    !/data-hmhd=/.test(f), f);
}
/* 🔴 KHÔNG CÒN prompt() Ở BẤT KỲ BƯỚC NÀO của chuỗi hạng mục. Anh Thắng đã nói một lần về chỗ
   khác: *"hiện ô tích cho dễ hơn không"* — hộp thoại của trình duyệt chỉ nhận chữ trơn, không
   có nút chọn tệp, không có ô ngày, và dán vào đó là dán mù. */
{
  const i = HTML.indexOf('/* ═══ Ô NHẬP INLINE DÙNG CHUNG');
  const j = HTML.indexOf('function daCapChaHint(');
  t('🔴 cả khối trạng thái hạng mục KHÔNG còn hỏi bằng prompt', HTML.slice(i, j).indexOf('prompt(') < 0);
}

/* ── 4b. 📎 ĐÍNH TỆP THẬT CHO UỶ NHIỆM CHI VÀ HOÁ ĐƠN ──────────────────────────────────
 * Uỷ nhiệm chi và hoá đơn là ảnh chụp / bản PDF nằm trong máy kế toán, không phải một địa chỉ
 * web có sẵn để dán. Bắt dán liên kết là bắt họ tự đi tải lên chỗ khác trước rồi mới quay lại.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
t('🔴 có nút chọn tệp cho uỷ nhiệm chi và hoá đơn', /hmDinhTep\(/.test(HTML));
t('   và tệp đi qua đúng đường tải lên của dự án', /hmDinhTep[^]{0,1400}uploadDuAnDoc\(/.test(HTML));
{
  /* Chạy thật: chọn tệp → tải lên → địa chỉ tệp rơi đúng vào ô, và tên tệp hiện ra cho người
     dùng biết đã đính cái gì. */
  const NK = { o: { value: '' }, lbl: { textContent: '' }, gui: null, toast: [] };
  const moi = {
    el: () => ({ value: '', files: [{ name: 'unc-88.pdf', type: 'application/pdf' }],
                 onchange: null, click() { this.onchange(); } }),
    loading: () => {}, toast: (k, m) => NK.toast.push([k, m]),
    FileReader: function () {
      this.readAsDataURL = () => this.onload({ target: { result: 'data:application/pdf;base64,QUJD' } });
    },
    document: { querySelector: sel => {
      if (/data-hmunclbl=/.test(sel)) return NK.lbl;
      if (/data-hmunc=/.test(sel)) return NK.o;
      return null;
    } },
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; },
      withFailureHandler() { return this; },
      uploadDuAnDoc(d, ma) { NK.gui = { d, ma }; this._ok({ success: true, url: 'https://kho/unc-88.pdf' }); },
    } } },
  };
  new Function('moi', `with(moi){ ${boc('hmDinhTep')}\n return hmDinhTep; }`)(moi)('P7', 'DA1', 'unc');
  t('🔴 tải tệp lên đúng dự án đang mở', NK.gui && NK.gui.ma === 'DA1', NK.gui);
  t('   gửi đi cả tên tệp (kho cần tên để giữ đuôi .pdf)',
    NK.gui && NK.gui.d.name === 'unc-88.pdf' && NK.gui.d.type === 'application/pdf', NK.gui && NK.gui.d);
  t('🔴 địa chỉ tệp rơi đúng vào ô uỷ nhiệm chi', NK.o.value === 'https://kho/unc-88.pdf', NK.o);
  t('   và hiện tên tệp cho người dùng biết đã đính cái gì',
    /unc-88\.pdf/.test(NK.lbl.textContent), NK.lbl);
}

/* ── 4c. 🔴 MÀN DUYỆT DÙNG CHUNG BỘ NÚT VỚI BẢNG DỰ ÁN ─────────────────────────────────
 * Cùng một hạng mục hiện ở hai màn. Viết hai bộ nút là hai nơi gõ cứng, và hai nơi ấy sẽ lệch
 * nhau — chỗ bắt hoá đơn, chỗ quên bắt; chỗ chỉ kế toán cấp được tiền, chỗ ai cũng cấp được.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
{
  const h = nut({ role: 'Kế toán cá nhân', duyet: true, tt: 'duyet', dot: 1 });
  t('🔴 màn Duyệt: kế toán cấp đợt kế tiếp, qua ĐÚNG hàm mở ô nhập của bảng dự án',
    /hmMoCap\('D1_7','DA1',7,2\)/.test(h), h);
  t('🔴 màn Duyệt KHÔNG in lại nhãn trạng thái vào ô Thao tác (đã có cột riêng)',
    !/border-radius:999px/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán cá nhân', duyet: true, tt: 'ung' });
  t('màn Duyệt: chốt xong cũng qua ô nhập có nút chọn tệp',
    /hmMoChot\('D1_7','DA1',7\)/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán NCC', duyet: true, hinhThuc: 'Trực tiếp', tt: 'nhap' });
  t('🔴 màn Duyệt: đơn NCC cũng tích khoá được ngay tại đây',
    /hmNccMo\('D1_7','DA1',7\)/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán cá nhân', duyet: true, tt: 'nhap' });
  t('🔴 màn Duyệt KHÔNG bày nút "xin tạm ứng" (đó là việc của nhân viên bên bảng dự án)',
    !/hmMoXin/.test(h), h);
}
{
  /* CHẠY THẬT renderDonHM — không ghim chuỗi ở chỗ gọi. Ghim thì đổi thứ tự tham số là bài
     kiểm vẫn xanh trong khi màn đã bày nhầm nút. */
  const NK = {};
  const moi = {
    CURUSER: { role: 'Kế toán cá nhân' },
    HM_ITEMS: [
      { maDA: 'DA1', tenDA: 'Aeon', loaiDA: 'Setup lắp đặt', nguoiTao: 'NV', row: 7,
        noiDung: 'Vật tư', hinhThuc: '', duToan: 1000, thucTe: 0,
        hm: { tt: 'nhap', dot: 0, lich: [] }, kyDA: { tu: '', den: '' } },
      { maDA: 'DA2', tenDA: 'Estella', loaiDA: 'Setup lắp đặt', nguoiTao: 'NV', row: 7,
        noiDung: 'Xe ba gác', hinhThuc: 'Trực tiếp', duToan: 2000, thucTe: 0,
        hm: { tt: 'nhap', dot: 0, lich: [] }, kyDA: { tu: '', den: '' } },
    ],
    DA_CUR: null, HM_NHAN: null,
    esc: x => String(x == null ? '' : x), money: x => String(x), _dmy: x => String(x),
    showPage: () => {}, openDuAn: () => {}, toast: () => {},
    el: id => (NK[id] = NK[id] || { innerHTML: '', style: {}, value: id === 'hmFilter' ? 'all' : '' }),
  };
  const src = `${bocVar('HM_NHAN')}
    ${boc('_hmNhanCua')}\n${boc('_hmLaKT')}\n${boc('_hmLaDuyet')}
    ${boc('hmNhanChung')}\n${boc('hmNutChung')}\n${boc('_hmKeyDuyet')}
    ${boc('hmDongForm')}\n${boc('_hmNgay')}\n${boc('renderDonHM')}
    return renderDonHM;`;
  new Function('moi', `with(moi){ ${src} }`)(moi)();
  const out = NK.hmBody.innerHTML;
  t('🔴 màn Duyệt KHÔNG bày nút "xin tạm ứng" cho bất kỳ đơn nào (việc của nhân viên)',
    !/hmMoXin/.test(out), out.slice(0, 400));
  t('   nhưng đơn NCC vẫn tích khoá được ngay ở đây', /hmNccMo\('DDA2_7','DA2',7\)/.test(out), out);
  t('🔴 hai dự án cùng số dòng vẫn có hàng ô nhập RIÊNG (khoá kèm mã dự án)',
    /data-hmf="DDA1_7"/.test(out) && /data-hmf="DDA2_7"/.test(out), out);
  t('   hàng ô nhập trải hết 8 cột của bảng Duyệt', /data-hmf="DDA1_7"[^]{0,60}colspan="8"/.test(out), out);
}
t('🔴 khoá hàng ô nhập ở màn Duyệt CÓ kèm mã dự án (hai dự án vẫn có thể cùng số dòng)',
  /_hmKeyDuyet\(maDA,row\)\{ return 'D'\+String\(maDA\)/.test(HTML));
t('   và bảng Duyệt dựng khoá ấy cho từng hàng',
  HTML.indexOf('var k=_hmKeyDuyet(x.maDA, x.row);') >= 0);
t('🔴 hai màn gọi CÙNG một hàm dựng nút',
  (HTML.match(/hmNutChung\(/g) || []).length >= 3);
t('   nút "gửi" của mỗi ô nhập biết phải nạp lại màn nào',
  HTML.indexOf("function _hmSau(k){ return (String(k).charAt(0)==='P') ? hmLaiDA : loadDonHM; }") >= 0);

/* ── 5. GỬI ĐI ─────────────────────────────────────────────────────────────────────────── */
function chayGui(vals) {
  const NK = { gui: null, toast: [], sau: null };
  const moi = {
    DA_CUR: { maDA: 'DA1' },
    toast: (k, m) => NK.toast.push([k, m]),
    _tienSo: v => { const s = String(v == null ? '' : v).replace(/[^0-9-]/g, ''); return s === '' || s === '-' ? '' : String(Number(s)); },
    hmDat: (maDA, row, tt, them, sau) => { NK.gui = { maDA, row, tt, them }; NK.sau = sau; },
    hmLaiDA: function hmLaiDA() {}, loadDonHM: function loadDonHM() {},
    document: { querySelector: sel => {
      const m = sel.match(/data-(xnd|xst|hmunc|hmhd)="([^"]+)"/);
      if (!m) return null;
      const k = m[1] + ':' + m[2];
      return (k in vals) ? { value: vals[k] } : (m[1] === 'xnd' || m[1] === 'xst' ? { value: '' } : null);
    } },
  };
  const f = new Function('moi', `with(moi){ ${boc('_hmSau')}\n${boc('_hmVal')}
    ${boc('hmGuiXin')}\n${boc('hmGuiCap')}\n${boc('hmGuiChot')}\n${boc('hmNccChot')}
    return { xin: hmGuiXin, cap: hmGuiCap, chot: hmGuiChot, ncc: hmNccChot }; }`)(moi);
  return { NK, f };
}
{
  const { NK, f } = chayGui({});
  f.xin('P7','DA1',7);
  t('🔴 không gõ lịch nào → vẫn gửi được (phần đông chỉ xin một lần)',
    NK.gui && NK.gui.tt === 'xin' && NK.gui.them.lich.length === 0, NK.gui);
}
{
  const { NK, f } = chayGui({ 'xnd:P7_1': '2026-09-15', 'xst:P7_1': '5.000.000',
                              'xnd:P7_3': '2026-10-01', 'xst:P7_3': '' });
  f.xin('P7','DA1',7);
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
  const { NK, f } = chayGui({ 'xnd:P7_2': '', 'xst:P7_2': '3000000' });
  t('🔴 có tiền mà KHÔNG có ngày → bỏ đợt ấy (một đợt không ngày thì chuẩn bị tiền vào hôm nào?)',
    (f.xin('P7','DA1',7), NK.gui && NK.gui.them.lich.length === 0), NK.gui && NK.gui.them);
}
{
  const { NK, f } = chayGui({ 'hmhd:P7': 'https://hd/9' });
  f.chot('P7', 'DA1', 7);
  t('🔴 chốt xong → gửi lệnh khoá kèm hoá đơn',
    NK.gui && NK.gui.tt === 'xong' && NK.gui.them.hoaDon === 'https://hd/9', NK.gui);
}
{
  const { NK, f } = chayGui({ 'hmhd:P7': '  ' });
  f.chot('P7', 'DA1', 7);
  t('🔴 chốt xong THIẾU HOÁ ĐƠN → chối ngay ở màn, khỏi phải chờ máy chủ ném lỗi',
    NK.gui === null, NK.gui);
  t('   và nói rõ vì sao', NK.toast.some(x => /hoá đơn/.test(x[1])), NK.toast);
}
{
  const { NK, f } = chayGui({ 'hmunc:P7': 'UNC-77' });
  f.cap('P7', 'DA1', 7, 2);
  t('🔴 cấp tạm ứng đợt 2 → gửi đúng đợt VÀ kèm uỷ nhiệm chi vừa đính',
    NK.gui && NK.gui.tt === 'ung' && NK.gui.them.dot === 2
    && NK.gui.them.unc === 'UNC-77', NK.gui);
}
{
  const { NK, f } = chayGui({});
  f.cap('P7', 'DA1', 7, 1);
  t('cấp tạm ứng không đính uỷ nhiệm chi vẫn đi được (có đợt trả tiền mặt)',
    NK.gui && NK.gui.tt === 'ung' && NK.gui.them.unc === '', NK.gui);
}
{
  const { NK, f } = chayGui({ 'hmunc:P7': 'UNC-88', 'hmhd:P7': 'https://hd/1' });
  f.ncc('P7','DA1',7);
  t('🔴 kế toán tích → gửi thẳng lệnh KHOÁ, kèm uỷ nhiệm chi và hoá đơn',
    NK.gui && NK.gui.tt === 'xong' && NK.gui.them.unc === 'UNC-88'
    && NK.gui.them.hoaDon === 'https://hd/1', NK.gui);
}
{
  const { NK, f } = chayGui({ 'hmunc:P7': 'UNC-88', 'hmhd:P7': '   ' });
  f.ncc('P7','DA1',7);
  t('🔴 THIẾU HOÁ ĐƠN → chối (khoá một con số không có gì đỡ thì lúc đối chiếu không gỡ ra được)',
    NK.gui === null, NK.gui);
  t('   và nói rõ vì sao', NK.toast.some(x => /hoá đơn/.test(x[1])), NK.toast);
}
{
  const { NK, f } = chayGui({ 'hmunc:P7': '', 'hmhd:P7': 'https://hd/2' });
  f.ncc('P7','DA1',7);
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
