/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TẠO ĐƠN MỚI: HỎI LOẠI ĐƠN TRƯỚC.
 *
 * Anh Thắng 10/09/2026: *"lúc tạo đơn sẽ hỏi luôn là đơn dự án hay đơn chi phí cơ sở chung.
 * tạo 2 lệnh để chọn để ra đơn cho đúng"*.
 *
 * =============================================================================================
 * 🔴 TRƯỚC BẢN NÀY nút "＋ Tạo đơn mới" LUÔN đẻ ra đơn TUẦN · CƠ SỞ, còn đơn dự án phải sang
 *    tab khác mới tạo được. Người mới vào không đoán ra, và lập nhầm loại thì phải xoá đi làm
 *    lại từ đầu — đơn đã lỡ nhập mấy dòng thì mất cả mấy dòng ấy.
 *
 * 🔴 CHỈ BỘ PHẬN KỸ THUẬT MỚI ĐƯỢC HỎI. Anh Thắng 10/09/2026: *"chỉ áp dụng cho kỹ thuật mới
 *    hỏi loại đơn gì"*. Người ngoài bộ phận ấy chỉ lên một loại đơn — hỏi một câu chỉ có một
 *    đáp án là thêm một cú bấm vô nghĩa cho mọi nhân viên cơ sở.
 *
 * 🔴 VÀ VẪN PHẢI VÀO ĐƯỢC TAB DỰ ÁN. Đúng bộ phận mà quyền bị gỡ ở bảng Phân quyền thì bày nút
 *    "Dự án" ra là họ bấm rồi ăn trang trắng — cùng lý do thanh "LOẠI ĐƠN" đã ẩn theo quyền.
 *    Hai vế, và bỏ vế nào cũng hỏng: bỏ vế bộ phận thì Văn phòng bị hỏi oan; bỏ vế quyền thì
 *    mở thêm một cửa cho người không có quyền — hướng nguy hơn.
 *
 * ⚠️ MỤC 2 CHẠY THẬT `_hoiLoaiDon()` bốc từ mã nguồn, không dò chuỗi. Dò chuỗi thì gỡ hẳn một
 *    vế đi bài kiểm vẫn xanh, miễn là câu chữ còn nguyên ở đâu đó trong tệp.
 *
 * Chạy: node tools/test/kiem-chon-loai-don.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

/* ── 1. HAI LỰA CHỌN, NÓI RÕ KHÁC NHAU CHỖ NÀO ─────────────────────────────────────────── */
t('🔴 có bước chọn loại đơn', HTML.indexOf('id="ndLoaiBox"') >= 0);
t('   nút Chi phí · cơ sở', HTML.indexOf("ndChonLoai('coso')") >= 0 && HTML.indexOf('📋 Chi phí · cơ sở') >= 0);
t('   nút Dự án · gian thi công', HTML.indexOf("ndChonLoai('duan')") >= 0 && HTML.indexOf('🏗 Dự án · gian thi công') >= 0);
/* Hai nhãn phải nói ra ĐIỂM KHÁC, không chỉ tên. Ai chưa quen thì tên đơn không giúp gì. */
t('🔴 nhãn nói rõ đơn cơ sở gom theo TUẦN', HTML.indexOf('gom theo tuần') >= 0);
t('🔴 nhãn nói rõ đơn dự án theo THỜI GIAN BẤT KỲ và ứng nhiều lần',
  HTML.indexOf('thời gian bất kỳ, tạm ứng nhiều lần') >= 0);

/* ── 2. CHỈ HỎI KHI VÀO ĐƯỢC CẢ HAI ────────────────────────────────────────────────────── */
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};
const nd = boc('newDon');
t('🔴 hỏi hay không tra qua _hoiLoaiDon()', nd.indexOf('var hoi=_hoiLoaiDon();') >= 0, nd);
t('   không hỏi thì mở thẳng phần đơn cơ sở như cũ',
  nd.indexOf("el('ndCoSoBox').style.display=hoi?'none':'';") >= 0, nd);
t('🔴 ở bước hỏi thì ẨN nút "Tạo đơn" (bấm lúc chưa chọn loại là tạo nhầm)',
  nd.indexOf("el('ndBtnTao').style.display=hoi?'none':'';") >= 0, nd);
t('   và không nhảy con trỏ vào ô Người lập khi đang hỏi loại',
  nd.indexOf("if(!hoi) setTimeout") >= 0, nd);

const vd = boc('_vaoDuocDuAn');
/* 🔴 Đọc CHÍNH cái nút đã bị ẩn theo quyền, không tự suy lại luật. Suy lại là hai nơi cùng giữ
   một luật phân quyền — mà luật ấy còn cộng thêm từ bảng Phân quyền chỉnh sửa. */
t('🔴 tra theo chính nút LOẠI ĐƠN đã ẩn theo quyền, không đoán lại luật',
  vd.indexOf("querySelector('[data-dcsw=\"duan\"]')") >= 0 && vd.indexOf('BP_VAO_DUAN') < 0, vd);

/* ── 2b. CHẠY THẬT `_hoiLoaiDon()` ─────────────────────────────────────────────────────── */
const hangBp = /var BP_HOI_LOAI_DON=(\[[^\]]*\]);/.exec(HTML);
t('🔴 có hằng BP_HOI_LOAI_DON (bộ phận nào được hỏi)', !!hangBp);
const BP_HOI = hangBp ? JSON.parse(hangBp[1].replace(/'/g, '"')) : [];
t("🔴 và đúng là Kỹ thuật — chỉ mình nó", JSON.stringify(BP_HOI) === '["Kỹ thuật"]', BP_HOI);

/* Bệ đỡ: CURUSER + cái nút tab Dự án. `_hoiLoaiDon()` gọi `_vaoDuocDuAn()`, nên bốc CẢ HAI ra
   chạy — thay `_vaoDuocDuAn()` bằng bản giả là bỏ mất đúng vế quan trọng nhất. */
let NUT = null;   // null = không có nút (không được vào tab Dự án)
const moiTruong = {
  BP_HOI_LOAI_DON: BP_HOI,
  document: { querySelector: () => NUT },
  CURUSER: null,
};
const chay = new Function('moiTruong', `
  with (moiTruong) {
    ${boc('_vaoDuocDuAn')}
    ${boc('_hoiLoaiDon')}
    return _hoiLoaiDon();
  }`);
const hoi = (boPhan, nut) => {
  moiTruong.CURUSER = boPhan === null ? null : { boPhan: boPhan };
  NUT = nut;
  return chay(moiTruong);
};
const NUT_HIEN = { style: { display: '' } }, NUT_AN = { style: { display: 'none' } };

t('🔴 Kỹ thuật + vào được tab Dự án → CÓ hỏi', hoi('Kỹ thuật', NUT_HIEN) === true);
t('🔴 Cơ sở → KHÔNG hỏi (chỉ có một loại đơn)', hoi('Cơ sở', NUT_HIEN) === false);
t('🔴 Văn phòng vào được tab Dự án nhưng vẫn KHÔNG hỏi',
  hoi('Văn phòng', NUT_HIEN) === false);
t('🔴 Kỹ thuật mà quyền vào tab Dự án bị gỡ → KHÔNG hỏi (bấm là ăn trang trắng)',
  hoi('Kỹ thuật', NUT_AN) === false);
t('   không có cả nút ấy → cũng KHÔNG hỏi', hoi('Kỹ thuật', null) === false);
t('chưa đăng nhập → KHÔNG hỏi, và không nổ', hoi(null, NUT_HIEN) === false);
t('bộ phận để trống → KHÔNG hỏi (rỗng không phải là Kỹ thuật)',
  hoi('', NUT_HIEN) === false && hoi(undefined, NUT_HIEN) === false);
t('   thừa khoảng trắng vẫn nhận đúng', hoi('  Kỹ thuật  ', NUT_HIEN) === true);

/* ── 3. CHỌN "DỰ ÁN" ĐI ĐÚNG ĐƯỜNG ─────────────────────────────────────────────────────── */
const cl = boc('ndChonLoai');
t('🔴 chọn Dự án → đóng hộp, sang tab Dự án', cl.indexOf("closeNewDon()") >= 0 && cl.indexOf("showPage('duan')") >= 0, cl);
/* 🔴 KHÔNG tự tạo một dự án không tên. Đơn dự án cần TÊN đợt thi công; tạo bừa rồi bắt đổi tên
   sau là để lại một dòng "(chưa đặt tên)" trong danh sách mà không ai dám xoá. */
t('🔴 KHÔNG tự gọi createDuAn — đưa người dùng tới ô đặt tên',
  cl.indexOf('createDuAn(') < 0 && cl.indexOf("el('daTen')") >= 0, cl);
t('   và nói rõ bước tiếp theo', cl.indexOf('Đặt tên đợt thi công') >= 0, cl);
t('chọn Chi phí cơ sở → hiện phần kỳ/người lập và nút Tạo đơn',
  cl.indexOf("el('ndCoSoBox').style.display='';") >= 0 && cl.indexOf("el('ndBtnTao').style.display='';") >= 0, cl);

/* ── 4. KHÔNG LÀM HỎNG ĐƯỜNG CŨ ────────────────────────────────────────────────────────── */
t('phần kỳ/người lập vẫn nằm trong hộp', HTML.indexOf('id="ndCoSoBox"') >= 0);
t('vẫn còn ô khoảng ngày tự chọn cho đợt vắt tuần', HTML.indexOf('id="ndTuDoBox"') >= 0);
t('nút Tạo đơn vẫn gọi submitNewDon()', HTML.indexOf('id="ndBtnTao" onclick="submitNewDon()"') >= 0);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hỏi loại đơn trước, và chỉ hỏi bộ phận Kỹ thuật.');
