/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG DỰ ÁN: Ô TÍCH ĐỂ XIN TẠM ỨNG MỘT LẦN, VÀ LỐI RIÊNG CHO ĐƠN KẾ TOÁN TRẢ THẲNG NCC.
 *
 * Anh Thắng: *"Trong 1 đơn chứ, trong 1 đơn mà nhiều lệnh tạm ứng, đơn nào bấm xin thì nó tổng
 * tổng tạm ứng cần xin"*, *"cho tích để bấm xin đơn 1 lần cho nhanh"*,
 * *"đơn nào chưa bấm xin làm nháp"*.
 *
 * =============================================================================================
 * 🔴 MỘT DỰ ÁN LÀ MỘT ĐƠN. Bản trước đặt nút "📤 Xin tạm ứng" trên TỪNG hàng, nên bốn hạng mục
 *    thành bốn đơn: kế toán bấm bốn lần duyệt, bốn lần cấp tiền, bốn tờ uỷ nhiệm chi. Nay mỗi
 *    hàng còn nháp mang một Ô TÍCH; tích xong bấm MỘT nút ở thanh dưới bảng → một lệnh.
 *
 * 🔴 CHƯA BẤM XIN LÀ NHÁP. "Đang nhập" nghe như máy đang bận làm gì; "Nháp" là chữ ai cũng hiểu
 *    ngay — chưa gửi đi đâu cả, sửa thoải mái.
 *
 * 🔴 ĐƠN 🏢 TRỰC TIẾP KHÔNG CÓ Ô TÍCH. Tiền không qua tay nhân viên nên "xin tạm ứng" vô nghĩa.
 *
 * 🔴 NÚT PHẢI HIỆN CẢ KHI KHÔNG SỬA ĐƯỢC ĐƠN. Kế toán thường không có quyền sửa dòng dự án;
 *    nhét nút trạng thái vào nhánh `canEdit` là giấu mất nút của chính người phải bấm.
 *
 * ⚠️ CHẠY THẬT các hàm bốc từ mã nguồn — không chép mã vào bài kiểm.
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
      : hmNutDaBang({ row: 7, noiDung: 'Xe ba gác', hinhThuc: opt.hinhThuc || '', hm: HM }, opt.tienHM || 0);`;
  moi.opt = opt;
  moi.HM = { tt: opt.tt || 'nhap', dot: opt.dot || 0 };
  return new Function('moi', `with(moi){ ${src} }`)(moi);
}

/* ── 1. 🔴 NHÂN VIÊN TÍCH HẠNG MỤC, KHÔNG BẤM NÚT LẺ ───────────────────────────────────── */
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'nhap' });
  t('🔴 hạng mục còn nháp → nhân viên thấy Ô TÍCH để gộp vào lệnh',
    /<input type="checkbox" data-xtu="7"/.test(h) && /tích để xin/.test(h), h);
  t('🔴 chưa bấm xin thì nhãn là NHÁP (không phải "đang nhập")', /Nháp/.test(h), h);
  t('🔴 KHÔNG còn nút xin lẻ trên hàng (bốn hạng mục là bốn đơn thì kế toán bấm hai mươi lượt)',
    !/hmMoXin/.test(h), h);
  t('   nhưng KHÔNG được tự duyệt cho chính mình', !/'duyet'/.test(h) && !/Duyệt/.test(h), h);
  t('   cũng không tự cấp tạm ứng cho chính mình', !/hmMoCap/.test(h), h);
  t('🔴 ô tích mang sẵn TIỀN của hạng mục để thanh dưới cộng lên được', /data-tien="/.test(h), h);
  t('   và báo cho thanh biết mỗi lần tích', /onchange="daTichDoi\(\)"/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'nhap', tienHM: 13000000 });
  t('🔴 tiền trong ô tích là con số THẬT của hạng mục', /data-tien="13000000"/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', thiCong: true, tt: 'tra' });
  t('🔴 hạng mục BỊ TRẢ LẠI → vẫn tích lại được (không thì đơn chết cứng ở đó)',
    /data-xtu="7"/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', tt: 'nhap' });
  t('đơn đã đóng (không sửa được) → không bày ô tích', !/data-xtu/.test(h), h);
  t('   nhưng vẫn cho biết đang ở trạng thái nào', /Nháp/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', isCoSo: true, tt: 'nhap' });
  t('dự án chi phí cơ sở (luôn nhập được) → cũng tích được', /data-xtu/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'xin' });
  t('🔴 đã nằm trong một lệnh → KHÔNG còn ô tích (tích lại là xin hai lần cùng một khoản)',
    !/data-xtu/.test(h), h);
  t('   và nói rõ đang chờ duyệt', /Chờ duyệt/.test(h), h);
}

/* ── 2. QUẢN LÝ / KẾ TOÁN — DUYỆT VÀ CẤP TIỀN ĐI THEO LỆNH, KHÔNG THEO HÀNG ───────────
 * Anh Thắng: *"Trong 1 đơn chứ, trong 1 đơn mà nhiều lệnh tạm ứng"*. Bốn hạng mục thành bốn
 * đơn là bốn lần duyệt, bốn lần cấp tiền, bốn tờ uỷ nhiệm chi — cho một đợt setup.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
{
  const h = nut({ role: 'Quản lý', tt: 'xin' });
  t('🔴 hàng hạng mục KHÔNG còn nút Duyệt (duyệt là việc của cả lệnh, ở màn Duyệt)',
    !/Duyệt/.test(h), h);
  t('   cũng không còn nút Trả', !/Trả/.test(h), h);
  t('   nhưng vẫn cho biết lệnh đang chờ duyệt', /Chờ duyệt/.test(h), h);
}
{
  const h = nut({ role: 'Kế toán cá nhân', tt: 'duyet', dot: 1 });
  t('🔴 hàng hạng mục KHÔNG còn nút Cấp tiền (cấp là cả lệnh, một uỷ nhiệm chi)',
    !/hmMoCap/.test(h), h);
  t('   và nói rõ đang chờ cấp tiền', /Chờ cấp tiền/.test(h), h);
}
{
  const h = nut({ role: 'Nhân viên', editable: true, tt: 'ung' });
  t('🔴 đã cấp tiền → hàng có nút CHỐT XONG (hoá đơn thì vẫn riêng từng hạng mục)',
    /hmMoChot\('P7','DA1',7\)/.test(h), h);
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

/* ── 4c. 🔴 MÀN DUYỆT: MỘT DÒNG LÀ MỘT LỆNH, KHÔNG PHẢI MỘT HẠNG MỤC ───────────────────
 * Anh Thắng: *"Trong 1 đơn chứ, trong 1 đơn mà nhiều lệnh tạm ứng, đơn nào bấm xin thì nó tổng
 * tổng tạm ứng cần xin"*. Kế toán nhìn phải thấy MỘT lệnh với MỘT số tiền, rồi cấp một lần kèm
 * MỘT uỷ nhiệm chi — không phải bốn dòng rời cho một đợt setup.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
function veLenh(role, loc) {
  const NK = {};
  const moi = {
    CURUSER: { role: role },
    LENH_ITEMS: [
      { maDA: 'DA1', tenDA: 'Aeon', loaiDA: 'Setup lắp đặt', nguoiTao: 'NV', dot: 1, tt: 'xin',
        rows: [2, 5], tenHM: ['Mua đồ điện', 'Thợ bốc vác'], soTien: 15300000, unc: '', lyDo: '',
        lich: [], moc: {}, kyDA: { tu: '', den: '' } },
      { maDA: 'DA1', tenDA: 'Aeon', loaiDA: 'Setup lắp đặt', nguoiTao: 'NV', dot: 2, tt: 'duyet',
        rows: [7], tenHM: ['Đơn linh tinh'], soTien: 50000, unc: '', lyDo: '',
        lich: [], moc: {}, kyDA: { tu: '', den: '' } },
      { maDA: 'DA2', tenDA: 'Estella', loaiDA: 'Setup lắp đặt', nguoiTao: 'NV', dot: 1, tt: 'ung',
        rows: [2], tenHM: ['Xe cẩu'], soTien: 900000, unc: 'UNC-9', lyDo: '',
        lich: [], moc: {}, kyDA: { tu: '', den: '' } },
    ],
    LENH_NHAN: null,
    esc: x => String(x == null ? '' : x), money: x => String(x), _dmy: x => String(x),
    showPage: () => {}, openDuAn: () => {}, toast: () => {},
    el: id => (NK[id] = NK[id] || { innerHTML: '', style: {}, value: id === 'lenhFilter' ? (loc || 'all') : '' }),
  };
  const src = `${bocVar('LENH_NHAN')}
    ${boc('_hmLaKT')}\n${boc('_hmLaDuyet')}\n${boc('lenhNhan')}
    ${boc('_lenhKey')}\n${boc('_hmNgay')}\n${boc('renderLenhDA')}
    return renderLenhDA;`;
  new Function('moi', `with(moi){ ${src} }`)(moi)();
  return NK.lenhBody.innerHTML;
}
{
  const out = veLenh('Kế toán cá nhân', 'all');
  t('🔴 một dòng = MỘT LỆNH, có tổng tiền của cả lệnh', /15300000/.test(out), out.slice(0, 300));
  t('   và kể tên các hạng mục trong lệnh',
    /Mua đồ điện/.test(out) && /Thợ bốc vác/.test(out), out.slice(0, 400));
  t('   nói rõ đợt mấy', /Đợt 1/.test(out) && /Đợt 2/.test(out), out.slice(0, 300));
  t('🔴 lệnh chờ duyệt → kế toán duyệt được CẢ LỆNH một lần',
    /lenhDat\('DA1',1,'duyet'\)/.test(out), out);
  t('🔴 lệnh đã duyệt → mở ô nhập uỷ nhiệm chi rồi cấp CẢ LỆNH',
    /lenhMoCap\('LDA1_2','DA1',2\)/.test(out), out);
  t('   trả lại được, và trả thì hỏi lý do', /lenhTra\('DA1',1\)/.test(out), out);
  t('🔴 lệnh đã cấp tiền → không bày nút cấp lại (bấm nhầm là chuyển đi hai lần)',
    !/lenhMoCap\('LDA2_1'/.test(out), out);
  t('   và hiện uỷ nhiệm chi đã cấp', /UNC-9/.test(out), out);
  t('🔴 hai dự án cùng số đợt vẫn có hàng ô nhập RIÊNG (khoá kèm mã dự án)',
    /data-hmf="LDA1_1"/.test(out) && /data-hmf="LDA2_1"/.test(out), out);
  t('   hàng ô nhập trải hết 8 cột', /data-hmf="LDA1_1"[^]{0,60}colspan="8"/.test(out), out);
}
{
  const out = veLenh('Nhân viên', 'all');
  t('🔴 nhân viên KHÔNG duyệt, KHÔNG cấp tiền, KHÔNG trả — dù mở được màn',
    !/lenhDat\(/.test(out) && !/lenhMoCap/.test(out) && !/lenhTra/.test(out), out);
}
{
  const out = veLenh('Quản lý', 'all');
  t('🔴 quản lý duyệt và trả được, nhưng KHÔNG cấp tiền',
    /lenhDat\('DA1',1,'duyet'\)/.test(out) && /lenhTra/.test(out) && !/lenhMoCap/.test(out), out);
}
{
  const out = veLenh('Kế toán cá nhân', 'cho');
  t('🔴 lọc "cần xử lý" chỉ giữ lệnh chờ duyệt / chờ cấp tiền',
    /Đợt 1/.test(out) && /Đợt 2/.test(out) && !/UNC-9/.test(out), out);
}
t('🔴 màn Duyệt nạp bảng lệnh khi mở', HTML.indexOf('loadLenhDA(); loadDonHM();') >= 0);
t('   bảng hạng mục nay chỉ lo NHẮC CHỐT hoá đơn, không còn lọc duyệt/cấp',
  HTML.indexOf('<option value="chuaxong">🔔 Chưa chốt (nhắc nhân viên)</option>') >= 0
  && HTML.indexOf('<option value="xin">Chờ duyệt tạm ứng</option>') < 0);


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
