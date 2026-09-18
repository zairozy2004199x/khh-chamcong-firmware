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
    ${boc('_dotHien')}
    ${boc('_dotHienCua')}
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
  moi.HM = { tt: opt.tt || 'nhap', dot: opt.dot || 0, qtDot: opt.qtDot || 0 };
  return new Function('moi', `with(moi){ ${src} }`)(moi);
}

/* ── 0. 🔴 HUY HIỆU "ĐÃ GỬI QT ĐỢT" CHỈ HIỆN KHI THẬT SỰ ĐÃ GỬI ────────────────────────
 *
 * 17/09/2026 — lỗi đã cắn thật, và bộ thử KHÔNG bắt được. Anh Thắng gửi ảnh: MỌI hàng hạng mục
 * đều mang nhãn tím "🧾 đã gửi QT đợt undefined", kể cả hàng chưa gửi quyết toán lần nào.
 *
 * Nguyên nhân: dòng `if(tt==='xong' && h.qtDot>0)` vốn KHÔNG có ngoặc nhọn, thân chỉ một câu
 * lệnh. Bản 1.192.0 chèn thêm một dòng `var` vào để dịch số đợt — thế là thân `if` ăn mất dòng
 * `var`, còn dòng vẽ huy hiệu tụt ra NGOÀI, thành vô điều kiện. Cú pháp vẫn đúng nên php -l và
 * mọi bài kiểm đều xanh; chỉ có màn hình là sai.
 *
 * 🔴 KHOÁ THEO HÀNH VI, KHÔNG KHOÁ THEO NGOẶC. Dò chữ "{" trong mã thì chỉ bắt đúng ca này; đo
 *    "hàng chưa gửi QT thì KHÔNG có nhãn" bắt được mọi cách làm hỏng nó.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
{
  const chua = nut({ tt: 'xong', qtDot: 0, editable: true });
  t('🔴 hạng mục CHƯA gửi quyết toán → KHÔNG có nhãn "đã gửi QT"',
    chua.indexOf('đã gửi QT') < 0, chua);
  t('🔴 và tuyệt đối không in "undefined" ra màn', chua.indexOf('undefined') < 0, chua);

  const roi = nut({ tt: 'xong', qtDot: 2, editable: true });
  t('đã gửi rồi thì CÓ nhãn', roi.indexOf('đã gửi QT') >= 0, roi);
  t('   kèm số lệnh, không phải undefined',
    /đã gửi QT lệnh 2</.test(roi) && roi.indexOf('undefined') < 0, roi);

  /* Hàng chưa chốt hoàn thành thì càng không có nhãn — dù sổ có lỡ ghi qtDot. */
  const nhap = nut({ tt: 'nhap', qtDot: 3, editable: true });
  t('hạng mục chưa chốt xong → không có nhãn dù sổ có qtDot',
    nhap.indexOf('đã gửi QT') < 0, nhap);
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
/* ⚠️ ĐẾM TỪ CHÍNH ĐẦU BẢNG, đừng gõ cứng con số — cùng bài học với `test-o-loai-chi-phi.js`.
   Gõ cứng thì thêm cột là phép đỏ vì con số hết hạn, rồi người sửa chỉ đổi số mà không ai soi
   xem hàng ô nhập còn trải hết bảng không. Đếm thì nó bắt đúng cái nó sinh ra để bắt. */
{
  const _b = HTML.slice(HTML.indexOf('id="daLineTable"'));
  const _dau = _b.slice(0, _b.indexOf('</thead>'));
  const _so = (_dau.match(/<th[ >]/g) || []).length;
  const _khai = (HTML.match(/hmDongForm\(_hmKeyDA\(p\.row\), (\d+)\)/) || [])[1];
  t('   hàng ấy trải hết bề ngang bảng (' + _so + ' cột)',
    _so > 0 && Number(_khai) === _so, { dauBang: _so, khaiTrongMa: _khai });
}
/* 🔴 MÃ CHẾT PHẢI BỎ HẲN, không để lại cho gọn mắt: máy chủ nay CHỐI đường xin lẻ từng hạng
   mục, nên để `hmMoXin` nằm đó là mời người sau nối lại một cái nút bấm vào chỉ ra câu lỗi. */
t('🔴 đường xin lẻ từng hạng mục đã bỏ HẲN khỏi mã nguồn',
  HTML.indexOf('function hmMoXin(') < 0 && HTML.indexOf('function hmGuiXin(') < 0);
t('   và có ghi lại vì sao bỏ, kẻo người sau tưởng quên',
  /`hmMoXin\(\)` \/ `hmGuiXin\(\)` cũ/.test(HTML));
/* Vẽ THẬT ba cái ô nhập rồi soi, thay vì dò chuỗi: tên thuộc tính được ghép động
   ('data-hm'+o) nên trong mã nguồn không có chuỗi nào để dò. */
function veForm(ham, ...them) {
  /* `daCur` đi kèm ở cuối khi bài kiểm cần một dự án đang mở (để `_hmChungTuSan` tra được ảnh
     bill trên hàng). Không truyền thì `DA_CUR` là null, đúng cảnh chưa mở dự án nào. */
  let daCur = null;
  if (them.length && them[them.length - 1] && them[them.length - 1].__daCur) {
    daCur = them.pop().__daCur;
  }
  const NK = { html: '', hien: false, oHd: { value: '' } };
  const moi = {
    esc: x => String(x == null ? '' : x),
    toast: () => {}, HM_ITEMS: [], DA_CUR: daCur,
    document: { querySelector: sel => (/data-hmhd=/.test(String(sel)) ? NK.oHd : {
      querySelector: () => ({ set innerHTML(v) { NK.html = v; }, get innerHTML() { return NK.html; } }),
      style: { set display(v) { NK.hien = (v === ''); } },
      scrollIntoView() {},
    }) },
  };
  const src = `${boc('_dotHien')}\n${boc('_dotHienCua')}\n${boc('_hmO')}\n${boc('_hmMo')}\n${boc('_hmTen')}
    ${boc('_hmOTep')}\n${boc('_hmNutDay')}\n${boc('_hmChungTuSan')}\n${boc(ham)}\n return ${ham};`;
  new Function('moi', `with(moi){ ${src} }`)(moi)('P7', 'DA1', 7, ...them);
  return NK;
}
{
  const f = veForm('hmNccMo').html;
  t('🔴 ô uỷ nhiệm chi và hoá đơn của kế toán là Ô NHẬP, không phải prompt',
    /data-hmunc="P7"/.test(f) && /data-hmhd="P7"/.test(f), f);
  t('   cả hai ô đều có nút chọn tệp', (f.match(/hmDinhTep\(/g) || []).length === 2, f);
  /* 🏢 Anh Thắng: *"chỗ này nhập tối thiểu 1 ảnh là được"*. Kế toán trả thẳng nhà cung cấp thì
     có khi cầm về uỷ nhiệm chi trước, hoá đơn nhà cung cấp xuất sau vài hôm. */
  t('🔴 đơn NCC: KHÔNG bắt buộc riêng cái nào — chỉ cần ít nhất một',
    !/bắt buộc/.test(f) && /ít nhất một/.test(f), f);
  t('   nút gửi truyền đủ khoá, mã dự án và số dòng',
    /hmNccChot\('P7','DA1',7\)/.test(f), f);
}
{
  const f = veForm('hmMoChot').html;
  t('🔴 chốt xong cũng đính được tệp hoá đơn (không bắt dán liên kết)',
    /data-hmhd="P7"/.test(f) && /hmDinhTep\(/.test(f), f);
  t('   nhưng KHÔNG hỏi uỷ nhiệm chi (đó là chứng từ của bước cấp tiền)',
    !/data-hmunc=/.test(f), f);
  t('🔴 hàng TRỐNG chứng từ → vẫn nói rõ hoá đơn là BẮT BUỘC (máy chủ chối, phải biết trước)',
    /Hoá đơn[^]{0,80}bắt buộc/.test(f), f);
}
/* ── 4a2. 🔴 BILL ĐÃ ĐÍNH TRÊN HÀNG CHÍNH LÀ HOÁ ĐƠN ──────────────────────────────────
 * Anh Thắng 18/09/2026, ảnh một hàng đã có ảnh bill ở cột ẢNH mà bấm Chốt xong vẫn hiện
 * "Hoá đơn (bắt buộc)": *"Chỗ ảnh đã add hóa đơn, tạo sao chốt lại hỏi hóa đơn lần 2, nó là
 * 1 mà"*.
 *
 * Cột ẢNH của hàng vốn là chỗ chụp bill (chính nó mang `data-bill` để rê chuột phóng to), cột
 * HỒ SƠ là bản PDF. Bắt tải lại đúng tệp ấy vào ô thứ hai là làm hai lần một việc — và tệ hơn:
 * người ta sẽ dán bừa thứ gì đó cho qua cửa, tức cửa vẫn đóng mà chứng từ thì sai.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
{
  const r = veForm('hmMoChot', { __daCur: { maDA: 'DA1', lines: [
    { row: 7, noiDung: 'Vận chuyển (E.Nhật)', anh: 'https://kho/bill-7.jpg', hoSo: '' } ] } });
  t('🔴 hàng ĐÃ CÓ ảnh bill → nói ra là đã có, không hỏi lại như chưa có gì',
    /đã có chứng từ đính sẵn/.test(r.html), r.html);
  t('   và cho xem lại đúng tấm ấy trước khi khoá', /https:\/\/kho\/bill-7\.jpg/.test(r.html), r.html);
  t('🔴 ô hoá đơn thôi gắn nhãn (bắt buộc) — nó đã có rồi', !/bắt buộc/.test(r.html), r.html);
  t('🔴 và ĐIỀN SẴN vào ô, bấm Chốt là xong, khỏi tải lên lần hai',
    r.oHd.value === 'https://kho/bill-7.jpg', r.oHd);
}
{
  /* Chưa có ảnh nhưng có hồ sơ (hoá đơn điện tử .pdf) — cũng là chứng từ. */
  const r = veForm('hmMoChot', { __daCur: { maDA: 'DA1', lines: [
    { row: 7, noiDung: 'Vận chuyển', anh: '', hoSo: 'https://kho/hd-7.pdf\nhttps://kho/phu-luc.pdf' } ] } });
  t('🔴 không có ảnh thì lấy HỒ SƠ (hoá đơn điện tử cũng là hoá đơn)',
    r.oHd.value === 'https://kho/hd-7.pdf', r.oHd);
  t('   nhiều hồ sơ thì lấy tệp ĐẦU, không nhét cả chùm vào một ô',
    r.oHd.value.indexOf('phu-luc') < 0, r.oHd);
}
{
  const r = veForm('hmMoChot', { __daCur: { maDA: 'DA1', lines: [
    { row: 7, noiDung: 'Vận chuyển', anh: '', hoSo: '' } ] } });
  t('🔴 hàng thật sự trống trơn → vẫn đòi hoá đơn (chốt là khoá và tính thành chi thực tế)',
    /bắt buộc/.test(r.html) && r.oHd.value === '', r.html);
  t('   và KHÔNG khoe "đã có chứng từ" khống', !/đã có chứng từ đính sẵn/.test(r.html), r.html);
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
  t('   nói rõ đang cấp lệnh mấy', /lệnh 2/.test(f), f);
  t('   và KHÔNG đòi hoá đơn ở bước cấp tiền (hoá đơn về sau, lúc chốt)',
    !/data-hmhd=/.test(f), f);
}
/* 🔴 KHÔNG CÒN prompt() Ở BẤT KỲ BƯỚC NÀO của chuỗi hạng mục. Anh Thắng đã nói một lần về chỗ
   khác: *"hiện ô tích cho dễ hơn không"* — hộp thoại của trình duyệt chỉ nhận chữ trơn, không
   có nút chọn tệp, không có ô ngày, và dán vào đó là dán mù. */
/* 🔴 CHỨNG TỪ, NGÀY, SỐ TIỀN thì KHÔNG hỏi bằng prompt — hộp thoại của trình duyệt chỉ nhận
   chữ trơn: không có nút chọn tệp, không có ô lịch, không điền sẵn được, và dán vào đó là dán
   mù. Còn "vì sao trả lại" thì đúng là một câu chữ, prompt hợp với nó — `lenhTra` và `qtTra`
   dùng chung lối ấy.
   ⚠️ CANH TỪNG HÀM, không canh cả vùng: canh cả vùng thì thêm bất kỳ chỗ hỏi-một-câu nào cũng
      làm phép này đỏ oan, rồi người ta nới nó ra và mất luôn chốt thật. */
['hmMoCap', 'hmGuiCap', 'hmMoChot', 'hmGuiChot', 'hmNccMo', 'hmNccChot', 'hmDinhTep',
 'daMoXinTU', 'daGuiXinTU', 'daLanDoi', 'lenhMoCap', 'lenhGuiCap'].forEach(ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('🔴 ' + ten + '() không hỏi chứng từ / ngày / số tiền bằng prompt',
    i >= 0 && HTML.slice(i, HTML.indexOf('\n  }', i)).indexOf('prompt(') < 0);
});

/* ── 4a3. 🔒 ĐÃ LÊN LỆNH THÌ KHOÁ MẤY Ô SINH RA TIỀN ─────────────────────────────────
 * Anh Thắng 18/09/2026: *"Số dự toán đã xin và lên thì không được sửa"*.
 *
 * Chốt THẬT nằm ở máy chủ (`loi_sua_du_toan_`, bài kiểm ở `kiem-lenh-tam-ung-du-an.php`). Khoá
 * ở màn chỉ để người ta khỏi gõ xong mới biết là không được — gõ rồi bị chối là mất công, và
 * mất cả tin vào màn hình.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
{
  const O = {};
  const oMoi = id => (O[id] = O[id] || { readOnly: false, style: {}, title: '', innerHTML: '', value: '' });
  const F = new Function('moi', `with(moi){ ${boc('_daKhoaDuToanO')}\n return _daKhoaDuToanO; }`)({
    el: oMoi,
    DA_CUR: { maDA: 'DA1', lines: [
      { row: 3, noiDung: 'Thợ Phụ', capCha: '', hm: { tt: 'ung' } },
      { row: 4, noiDung: 'Ốc vít',  capCha: 'Thợ Phụ' },
      { row: 9, noiDung: 'Vật tư',  capCha: '', hm: { tt: 'nhap' } } ] },
  });

  F({ row: 3, noiDung: 'Thợ Phụ', capCha: '', hm: { tt: 'ung' } });
  t('🔴 hạng mục ĐÃ LÊN LỆNH → khoá ô dự toán, số lượng, đơn giá',
    O.da_dt.readOnly && O.da_sl.readOnly && O.da_dg.readOnly, O);
  t('🔴 nhưng ô CHI PHÍ THỰC TẾ KHÔNG bị khoá (khoá luôn thì không ai quyết toán được nữa)',
    !O.da_thucte, O.da_thucte);
  t('   ô khoá đổi màu cho thấy rõ, và rê chuột vào nói vì sao',
    O.da_dt.style.background === '#f1efec' && /trả lệnh/.test(O.da_dt.title), O.da_dt);
  t('🔴 và có một dòng nhắc chỉ đường ra: trả lệnh về trước',
    /đã lên lệnh tạm ứng/.test(O.daKhoaNhac.innerHTML) && /trả lệnh/.test(O.daKhoaNhac.innerHTML),
    O.daKhoaNhac.innerHTML);

  /* Mục con đi theo cha — tiền của hạng mục lớn cộng từ con. */
  F({ row: 4, noiDung: 'Ốc vít', capCha: 'Thợ Phụ' });
  t('🔴 mục con của hạng mục đã lên lệnh cũng khoá theo cha', O.da_dt.readOnly, O);

  F({ row: 9, noiDung: 'Vật tư', capCha: '', hm: { tt: 'nhap' } });
  t('🔴 hạng mục CÒN NHÁP thì mở hết — đang lập dự toán mà khoá là khoá sai bước',
    !O.da_dt.readOnly && !O.da_sl.readOnly && !O.da_dg.readOnly, O);
  t('   và dòng nhắc biến mất', O.daKhoaNhac.innerHTML === '' && O.daKhoaNhac.style.display === 'none',
    O.daKhoaNhac);

  /* 🔴 THOÁT SỬA PHẢI MỞ KHOÁ LẠI. Bỏ dở việc sửa một hàng đã lên lệnh rồi quay sang THÊM DÒNG
     MỚI mà ô vẫn khoá thì không gõ được dự toán, và chẳng có gì nói vì sao. */
  F({ row: 3, noiDung: 'Thợ Phụ', capCha: '', hm: { tt: 'ung' } });
  F(null);
  t('🔴 thoát chế độ sửa → mở khoá lại mấy ô (không thì thêm dòng mới cũng gõ không được)',
    !O.da_dt.readOnly && O.daKhoaNhac.innerHTML === '', O);
}
t('🔴 thoát sửa dòng có gọi mở khoá', /daCancelEditLine\(\)\{[^]{0,400}_daKhoaDuToanO\(null\)/.test(HTML));
t('   và mở sửa một dòng thì gọi khoá', /_daKhoaDuToanO\(l\);/.test(HTML));

/* ── 4a4. 📅 HAI CỘT MỚI CỦA BẢNG DỰ ÁN — VẼ THẬT MỘT HÀNG RỒI ĐẾM ─────────────────────
 * Anh Thắng 18/09/2026, nhìn bảng dự án sau khi thấy hai cột mới ở đơn tuần: *"vậy cột ngày
 * chưa có rồi"*.
 *
 * ⚠️ VẼ THẬT, ĐỪNG DÒ CHUỖI TRONG MÃ NGUỒN. Dò chuỗi chỉ thấy đầu bảng có `<th>`, không thấy
 *    HÀNG có `<td>` — gỡ ô ra khỏi hàng mà vẫn để `<th>` thì bảng lệch cột mà phép vẫn xanh.
 *    Đúng chỗ ấy đã xanh oan một lần lúc dựng bài này.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
{
  const i = HTML.indexOf('    function daLineCells(');
  const src = HTML.slice(i, HTML.indexOf('\n    }', i) + 6);
  t('bốc được daLineCells()', i >= 0);
  const F = new Function('moi', `with(moi){ ${src}\n return daLineCells; }`)({
    esc: x => String(x == null ? '' : x), money: n => String(Number(n) || 0),
    canEdit: true, htBadge: () => 'HT',
    tkBadge: (a, b) => (b ? ('[' + a + ' No ' + b + ']') : (a ? ('[' + a + ' chua ma]') : '')),
    _daTtHang: () => 'nhap', _daOLoaiCp: l => '<td>LOAI:' + String(l.loaiCp || '') + '</td>',
    DA_CUR: { maDA: 'DA1', lines: [] } });

  /* Số ô của HÀNG phải bằng số cột của ĐẦU BẢNG — lệch một ô là cả bảng trượt cột. */
  const dau = HTML.slice(HTML.indexOf('id="daLineTable"'));
  const soCot = (dau.slice(0, dau.indexOf('</thead>')).match(/<th[ >]/g) || []).length;

  const h = F({ row: 3, noiDung: 'Thợ Phụ', taoLuc: '18/09/2026',
    loaiCp: 'Chi phí nuôi thú', duToan: 1, capCha: '' }, false, '', true);
  t('🔴 hàng dự án IN RA ngày nhập', h.indexOf('18/09/2026') >= 0, h);
  t('🔴 và có ô loại chi phí riêng', h.indexOf('LOAI:Chi phí nuôi thú') >= 0, h);
  t('🔴 số ô của hàng bằng đúng số cột đầu bảng (' + soCot + ')',
    (h.match(/<td/g) || []).length === soCot, { hang: (h.match(/<td/g) || []).length, dauBang: soCot });

  const h2 = F({ row: 4, noiDung: 'Dòng cũ', loaiCp: '', duToan: 0, capCha: '' }, false, '', true);
  t('   dòng cũ vẫn đủ số ô, không thiếu một ô nào',
    (h2.match(/<td/g) || []).length === soCot, (h2.match(/<td/g) || []).length);
}
/* ── 🔴 DÒNG CŨ KHÔNG CÓ MỐC → LÙI VỀ NGÀY LẬP DỰ ÁN, KÈM DẤU "≈" ───────────────────────
 * Anh Thắng 18/09/2026, nhìn cả cột toàn dấu gạch: *"thiếu cột ngày nhập"*. Đúng — một cột
 * trống trơn ở MỌI dòng của MỌI dự án đang có thì trông y như hỏng, dù nó đang nói thật.
 *
 * ⚠️ NHƯNG DẤU "≈" LÀ BẮT BUỘC. Một dòng không thể có trước dự án chứa nó, nên ngày ấy là cận
 *    dưới THẬT — nhưng nó KHÔNG phải ngày nhập của dòng. Bỏ dấu là biến một cận dưới thành một
 *    lời khai, và người đọc sổ không còn cách nào biết dòng nào có mốc thật.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
{
  const i = HTML.indexOf('    function daLineCells(');
  const src = HTML.slice(i, HTML.indexOf('\n    }', i) + 6);
  const dung = (daCur) => new Function('moi', `with(moi){ ${src}\n return daLineCells; }`)({
    esc: x => String(x == null ? '' : x), money: n => String(Number(n) || 0),
    canEdit: true, htBadge: () => 'HT', tkBadge: () => '',
    _daTtHang: () => 'nhap', _daOLoaiCp: () => '<td>L</td>', DA_CUR: daCur });

  const cu = { row: 4, noiDung: 'Băng keo trong', loaiCp: '', duToan: 0, capCha: '' };
  const h = dung({ maDA: 'DA1', ngayTao: '13/09/2026', lines: [] })(cu, false, '', true);
  t('🔴 dòng cũ (chưa có mốc) → lùi về NGÀY LẬP DỰ ÁN, không để cột trống trơn',
    h.indexOf('13/09/2026') >= 0, h);
  t('🔴 và KÈM DẤU "≈" — nó là cận dưới, không phải ngày nhập thật của dòng',
    /≈ 13\/09\/2026/.test(h), h);
  t('   kèm lời giải thích lúc rê chuột, để không ai đọc nhầm thành mốc thật',
    /title="[^"]*NGÀY LẬP DỰ ÁN/.test(h), h);

  /* Có mốc thật thì in THẲNG, không kèm "≈" — hai thứ phải phân biệt được bằng mắt. */
  const moi = { row: 5, noiDung: 'Mới', taoLuc: '18/09/2026', loaiCp: '', duToan: 0, capCha: '' };
  const h3 = dung({ maDA: 'DA1', ngayTao: '13/09/2026', lines: [] })(moi, false, '', true);
  t('🔴 dòng CÓ mốc thật → in thẳng, KHÔNG kèm "≈" (hai thứ phải phân biệt được bằng mắt)',
    h3.indexOf('18/09/2026') >= 0 && h3.indexOf('≈') < 0, h3);

  /* Không biết cả ngày lập dự án thì mới in "—". Bịa ra một ngày ở đây là bịa thật. */
  const h4 = dung({ maDA: 'DA1', lines: [] })(cu, false, '', true);
  t('   không biết cả ngày lập dự án → mới in "—"', /—/.test(h4) && h4.indexOf('≈') < 0, h4);
}
t('🔴 máy chủ gửi kèm ngày lập dự án (thiếu nó thì cả lối lùi trên vô dụng)',
  /'ngayTao'\s*=> VHCP_Util::fmt\( \$f\['ngay_tao'\] \),/.test(
    require('fs').readFileSync(require('path').join(GOC,
      'wordpress/vhcp-chi-phi/includes/class-vhcp-duan.php'), 'utf8')));

/* ── 4a5. 📋 MÀN NGOÀI CHỈ HIỆN TỔNG, CHI TIẾT GẬP LẠI ────────────────────────────────
 * Anh Thắng 18/09/2026: *"Chỗ màn ngoài chỉ cần hiện tổng rõ thông tin, không cần hiện chi
 * tiết đơn"*.
 *
 * Một đơn 14 hạng mục đang chiếm 14 dòng ở màn danh sách; bốn đơn như thế là màn hình chẳng
 * còn là danh sách nữa, và cái người ta vào đây để tìm — đơn nào đang vướng — chìm mất.
 *
 * 🔴 GẬP, KHÔNG XOÁ. Mấy nút trong đó ("Chốt xong", "KT đã chi — khoá đơn") là VIỆC kế toán làm
 *    ngay tại màn này. Xoá đi là bắt họ mở từng dự án mới bấm được — gọn màn hình bằng cách
 *    thêm việc cho người dùng thì không phải là gọn.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
t('🔴 hàng chi tiết của đơn GẬP SẴN ở màn ngoài',
  /<tr data-hmctr="'\+esc\(ma\)\+'" style="display:none">/.test(HTML));
t('   và có nút bung ra, mang luôn con số cho biết bên trong bao nhiêu dòng',
  /data-hmct="'\+esc\(ma\)\+'"[^]{0,200}▸ Chi tiết \('\+hs\.length\+'\)/.test(HTML));
t('🔴 mấy nút thao tác VẪN CÒN trong khối gập (gập là để gọn, không phải để bỏ việc)',
  /var nut=hmNutChung\(k, x\.maDA, x\.row, x\.hinhThuc, h, false, true\);/.test(HTML));
/* Dòng tóm tắt phải gánh phần việc của khối vừa gập: nói ra ĐANG CHỜ AI, không chỉ đếm. */
t('🔴 dòng tóm tắt nói rõ đang chờ KẾ TOÁN hay chờ HOÁ ĐƠN, không chỉ đếm "N chưa chốt"',
  /chờ kế toán/.test(HTML) && /chờ hoá đơn/.test(HTML));
{
  /* Chạy thật hàm bung/gập: bấm một cái phải ĐỔI CẢ MŨI TÊN trên nút — bấm mà nút không đổi
     gì thì người ta bấm lại lần nữa, và lần ấy gập nó lại, trông như nút hỏng. */
  const i = HTML.indexOf('  function hmMoChiTiet(');
  const src = HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
  t('bốc được hmMoChiTiet()', i >= 0);
  const O = { r: { style: { display: 'none' } }, b: { innerHTML: '▸ Chi tiết (14)' } };
  const F = new Function('moi', `with(moi){ ${src}\n return hmMoChiTiet; }`)({
    document: { querySelector: sel => (/data-hmctr/.test(sel) ? O.r : O.b) } });
  F('DA1');
  t('🔴 bấm một cái → khối chi tiết bung ra', O.r.style.display === '', O.r.style);
  t('🔴 và mũi tên đổi theo, giữ nguyên con số', O.b.innerHTML === '▾ Chi tiết (14)', O.b.innerHTML);
  F('DA1');
  t('   bấm lại → gập vào, mũi tên trả về', O.r.style.display === 'none' && O.b.innerHTML === '▸ Chi tiết (14)',
    { d: O.r.style.display, b: O.b.innerHTML });
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
    ${boc('_lenhKey')}\n${boc('_hmNgay')}\n${boc('lenhNutChung')}\n${boc('_dotHien')}\n${boc('_nhanDot')}\n${boc('renderLenhDA')}
    return renderLenhDA;`;
  new Function('moi', `with(moi){ ${src} }`)(moi)();
  return NK.lenhBody.innerHTML;
}
{
  const out = veLenh('Kế toán cá nhân', 'all');
  t('🔴 một dòng = MỘT LỆNH, có tổng tiền của cả lệnh', /15300000/.test(out), out.slice(0, 300));
  t('   và kể tên các hạng mục trong lệnh',
    /Mua đồ điện/.test(out) && /Thợ bốc vác/.test(out), out.slice(0, 400));
  /* 17/09/2026: cột đầu nay ghi LỆNH, không ghi ĐỢT — hệ có hai tầng và cột này là tầng
     ngoài (lô hạng mục, một lượt duyệt). "Đợt" để dành cho từng lần đi nhận tiền. */
  t('   nói rõ lệnh mấy', /Lệnh 1/.test(out) && /Lệnh 2/.test(out), out.slice(0, 300));
  t('🔴 lệnh chờ duyệt → kế toán duyệt được CẢ LỆNH một lần',
    /lenhDat\('DA1',1,'duyet',\{\},_lenhSau\('LDA1_1'\)\)/.test(out), out);
  t('🔴 lệnh đã duyệt → mở ô nhập uỷ nhiệm chi rồi cấp CẢ LỆNH',
    /lenhMoCap\('LDA1_2','DA1',2\)/.test(out), out);
  t('   trả lại được, và trả thì hỏi lý do', /lenhTra\('DA1',1,'LDA1_1'\)/.test(out), out);
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
    /lenhDat\('DA1',1,'duyet',\{\},_lenhSau\('LDA1_1'\)\)/.test(out) && /lenhTra/.test(out) && !/lenhMoCap/.test(out), out);
}
{
  const out = veLenh('Kế toán cá nhân', 'cho');
  t('🔴 lọc "cần xử lý" chỉ giữ lệnh chờ duyệt / chờ cấp tiền',
    /Lệnh 1/.test(out) && /Lệnh 2/.test(out) && !/UNC-9/.test(out), out);
}
t('🔴 màn Duyệt nạp bảng lệnh khi mở', HTML.indexOf('loadLenhDA(); loadDonHM();') >= 0);
t('   bảng hạng mục nay chỉ lo NHẮC CHỐT hoá đơn, không còn lọc duyệt/cấp',
  HTML.indexOf('<option value="chuaxong">🔔 Chưa chốt (nhắc nhân viên)</option>') >= 0
  && HTML.indexOf('<option value="xin">Chờ duyệt tạm ứng</option>') < 0);


/* ── 5. GỬI ĐI ─────────────────────────────────────────────────────────────────────────── */
function chayGui(vals, lines) {
  const NK = { gui: null, toast: [], sau: null };
  const moi = {
    /* `lines` để `_hmChungTuSan` tra được ảnh bill / hồ sơ đã đính trên hàng. Không truyền thì
       dự án không có dòng nào — đúng cảnh hàng trống trơn chứng từ. */
    DA_CUR: { maDA: 'DA1', lines: lines || [] },
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
  const f = new Function('moi', `with(moi){ ${boc('_hmSau')}\n${boc('_hmVal')}\n${boc('_hmChungTuSan')}
    ${boc('hmGuiCap')}\n${boc('hmGuiChot')}\n${boc('hmNccChot')}
    return { cap: hmGuiCap, chot: hmGuiChot, ncc: hmNccChot }; }`)(moi);
  return { NK, f };
}
/* Lịch đợt nay là của LỆNH (`daMoXinTU` / `daGuiXinTU`) chứ không của từng hạng mục — bài kiểm
   nằm ở `kiem-tich-xin-tam-ung.js`. `hmMoXin`/`hmGuiXin` cũ đã bỏ hẳn khỏi mã nguồn. */
{
  const { NK, f } = chayGui({ 'hmhd:P7': 'https://hd/9' });
  f.chot('P7', 'DA1', 7);
  t('🔴 chốt xong → gửi lệnh khoá kèm hoá đơn',
    NK.gui && NK.gui.tt === 'xong' && NK.gui.them.hoaDon === 'https://hd/9', NK.gui);
}
{
  const { NK, f } = chayGui({ 'hmhd:P7': '  ' });
  f.chot('P7', 'DA1', 7);
  t('🔴 hàng trống trơn + ô hoá đơn rỗng → chối ngay ở màn, khỏi chờ máy chủ ném lỗi',
    NK.gui === null, NK.gui);
  t('   và nói rõ vì sao, kèm lối thoát (đính ngay trên hàng cũng được)',
    NK.toast.some(x => /hoá đơn/.test(x[1]) && /trên hàng/.test(x[1])), NK.toast);
}
{
  /* 🔴 Ô RỖNG NHƯNG HÀNG ĐÃ CÓ BILL → CHỐT ĐƯỢC, và gửi lên chính tấm bill ấy.
     Anh Thắng: *"Chỗ ảnh đã add hóa đơn, tạo sao chốt lại hỏi hóa đơn lần 2, nó là 1 mà"*. */
  const { NK, f } = chayGui({ 'hmhd:P7': '' },
    [{ row: 7, noiDung: 'Vận chuyển (E.Nhật)', anh: 'https://kho/bill-7.jpg', hoSo: '' }]);
  f.chot('P7', 'DA1', 7);
  t('🔴 ô rỗng nhưng hàng đã có bill → CHỐT ĐƯỢC, không chối',
    NK.gui && NK.gui.tt === 'xong', NK.gui);
  t('🔴 và gửi lên chính tấm bill ấy làm hoá đơn (không gửi rỗng rồi để sổ trắng chứng từ)',
    NK.gui && NK.gui.them.hoaDon === 'https://kho/bill-7.jpg', NK.gui);
}
{
  /* Gõ tệp khác vào ô thì tệp ấy THẮNG — bill trên hàng chỉ là mặc định, không phải cái khoá. */
  const { NK, f } = chayGui({ 'hmhd:P7': 'https://hd/khac.pdf' },
    [{ row: 7, noiDung: 'Vận chuyển', anh: 'https://kho/bill-7.jpg', hoSo: '' }]);
  f.chot('P7', 'DA1', 7);
  t('   thay tệp khác vào ô thì tệp ấy thắng, bill trên hàng chỉ là mặc định',
    NK.gui && NK.gui.them.hoaDon === 'https://hd/khac.pdf', NK.gui);
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
  t('🔴 có uỷ nhiệm chi, CHƯA có hoá đơn → vẫn khoá được (hoá đơn NCC xuất sau vài hôm)',
    NK.gui && NK.gui.tt === 'xong' && NK.gui.them.unc === 'UNC-88', NK.gui);
}
{
  const { NK, f } = chayGui({ 'hmunc:P7': '', 'hmhd:P7': 'https://hd/2' });
  f.ncc('P7','DA1',7);
  t('có hoá đơn, chưa có uỷ nhiệm chi → cũng khoá được (có đơn trả bằng tiền mặt)',
    NK.gui && NK.gui.tt === 'xong' && NK.gui.them.hoaDon === 'https://hd/2', NK.gui);
}
{
  const { NK, f } = chayGui({ 'hmunc:P7': '  ', 'hmhd:P7': '   ' });
  f.ncc('P7','DA1',7);
  t('🔴 TRỐNG CẢ HAI → chối (khoá một con số không có gì đỡ thì lúc đối chiếu không gỡ ra được)',
    NK.gui === null, NK.gui);
  t('   và nói rõ cần ít nhất một',
    NK.toast.some(x => /ít nhất một chứng từ/.test(x[1])), NK.toast);
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: nhân viên gửi được xin tạm ứng, kế toán tích khoá được đơn NCC.');
