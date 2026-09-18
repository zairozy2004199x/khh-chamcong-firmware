/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GÕ THẲNG TRONG Ô CỦA BẢNG DỰ ÁN — MÀN HÌNH.
 *
 * Anh Thắng 18/09/2026: *"thay vì bấm sửa, thì cho sửa thẳng trong từng dòng đơn được không"*.
 *
 * =============================================================================================
 * 🔴 Ô TIỀN GÕ CÓ DẤU CHẤM. Người ta gõ "1.500.000" như mọi ô tiền khác trong hệ; gửi nguyên
 *    chuỗi ấy lên là máy chủ đọc ra 1 — tức một đồng rưỡi. Đây là lỗi đã cắn thật một lần ở ô
 *    số tiền của lệnh cấp tạm ứng, nên chỗ nào có ô tiền mới là chỗ ấy phải có phép này.
 *
 * 🔴 KHÔNG ĐỔI THÌ KHÔNG GỬI. Bấm vào ô rồi bấm ra ngoài là chuyện xảy ra suốt ngày; mỗi lần ấy
 *    một lượt ghi là nhật ký đầy rác và sổ bị đụng vô cớ.
 *
 * ⚠️ CHẠY THẬT các hàm bốc từ mã nguồn.
 *
 * Chạy: node tools/test/kiem-sua-o-trong-bang.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) {
  if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : ''));
}
const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('  function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i);
  return j < i ? '' : HTML.slice(i, j + 4);
};

/* Một ô <td> giả, giữ được thuộc tính và nội dung như ô thật.
   ⚠️ GÁN `innerHTML` PHẢI SINH RA `firstChild`, đúng như trình duyệt: `daOSua()` dựng ô nhập
      bằng innerHTML rồi lập tức bắt phím và focus lên `td.firstChild`. Bệ đỡ không làm bước ấy
      thì bài kiểm nổ ở một chỗ chẳng liên quan gì tới việc đang kiểm. */
function oTD(chu, khoa) {
  return {
    _attr: { 'data-osua': khoa },
    textContent: chu, firstChild: null,
    _html: chu,
    get innerHTML() { return this._html; },
    set innerHTML(v) {
      this._html = String(v);
      const m = this._html.match(/<input[^>]*value="([^"]*)"/);
      this.firstChild = m ? { value: m[1], onkeydown: null, onblur: null, focus() {}, select() {} } : null;
    },
    getAttribute(k) { return this._attr[k] === undefined ? null : this._attr[k]; },
    setAttribute(k, v) { this._attr[k] = v; },
  };
}
function be(opt) {
  opt = opt || {};
  const NK = { gui: null, toast: [], nap: 0 };
  const moi = {
    DA_CUR: { maDA: 'DA1', ten: 'Aeon' },
    esc: x => String(x == null ? '' : x),
    toast: (k, m) => NK.toast.push([k, m]),
    _log: () => {}, openDuAn: () => { NK.nap++; }, loadDuAn: () => {},
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; },
      withFailureHandler(f) { this._no = f; return this; },
      datODuAnLine(ma, row, cot, gt) {
        NK.gui = { ma, row, cot, gt };
        this._ok(opt.loi ? { success: false, error: opt.loi } : { success: true });
      },
    } } },
  };
  const src = `${boc('daOSua')}\n${boc('daODong')}\n${boc('daOLuu')}
    return { mo: daOSua, dong: daODong, luu: daOLuu };`;
  return { NK, F: new Function('moi', `with(moi){ var DA_O_DANG=null; ${src} }`)(moi) };
}

/* ── 1. MỞ Ô ──────────────────────────────────────────────────────────────────────────── */
{
  const b = be();
  const td = oTD('500.000', '7|duToan');
  b.F.mo(td);
  t('🔴 bấm vào ô → hiện Ô NHẬP ngay tại chỗ, không nhảy lên form đầu trang',
    /<input/.test(td.innerHTML), td.innerHTML);
  t('   điền sẵn đúng số đang có, để sửa một chữ cũng được', /value="500\.000"/.test(td.innerHTML), td.innerHTML);
  t('🔴 và NHỚ giá trị cũ để Esc còn trả lại được', td.getAttribute('data-ocu') === '500.000', td._attr);
  t('   ô tiền canh phải như mọi ô tiền khác', /text-align:right/.test(td.innerHTML), td.innerHTML);
}
{
  const b = be();
  const td = oTD('ghi cũ', '7|note');
  b.F.mo(td);
  t('ô ghi chú cũng mở được, nhưng KHÔNG canh phải (nó là chữ)',
    /<input/.test(td.innerHTML) && !/text-align:right/.test(td.innerHTML), td.innerHTML);
}

/* ── 2. LƯU ───────────────────────────────────────────────────────────────────────────── */
{
  const b = be();
  const td = oTD('500.000', '7|duToan');
  b.F.mo(td);
  td.firstChild.value = '1.500.000';
  b.F.luu(td);
  t('🔴 gửi đi ĐÚNG DÒNG và ĐÚNG Ô',
    b.NK.gui && b.NK.gui.row === 7 && b.NK.gui.cot === 'duToan' && b.NK.gui.ma === 'DA1', b.NK.gui);
  /* 🔴 Đây là lỗi đã cắn thật một lần ở ô số tiền của lệnh cấp tạm ứng. */
  t('🔴 BỎ DẤU CHẤM trước khi gửi — gửi "1.500.000" là máy chủ đọc thành 1',
    b.NK.gui && b.NK.gui.gt === '1500000', b.NK.gui);
  t('   lưu xong thì nạp lại dự án (đổi một ô là đổi cả tổng nhóm lẫn thẻ dự kiến tạm ứng)',
    b.NK.nap === 1, b.NK.nap);
}
{
  const b = be();
  const td = oTD('ghi cũ', '7|note');
  b.F.mo(td);
  td.firstChild.value = '  ghi mới  ';
  b.F.luu(td);
  t('ô ghi chú gửi nguyên chữ, chỉ cắt khoảng trắng hai đầu',
    b.NK.gui && b.NK.gui.gt === 'ghi mới', b.NK.gui);
}
{
  /* 🔴 KHÔNG ĐỔI THÌ KHÔNG GỬI. Bấm vào rồi bấm ra là chuyện xảy ra suốt ngày. */
  const b = be();
  const td = oTD('500.000', '7|duToan');
  b.F.mo(td);
  td.firstChild.value = '500.000';
  b.F.luu(td);
  t('🔴 không đổi gì → KHÔNG gửi (nhật ký đầy rác, sổ bị đụng vô cớ)', b.NK.gui === null, b.NK.gui);
  t('   và ô trả lại chữ cũ', td.innerHTML === '500.000', td.innerHTML);
}
{
  /* Gõ lại cùng một số nhưng khác cách chấm ("500000" vs "500.000") vẫn là KHÔNG đổi. */
  const b = be();
  const td = oTD('500.000', '7|duToan');
  b.F.mo(td);
  td.firstChild.value = '500000';
  b.F.luu(td);
  t('🔴 cùng một số mà gõ khác cách chấm vẫn tính là KHÔNG đổi', b.NK.gui === null, b.NK.gui);
}
{
  /* Esc: trả lại chữ cũ, không gửi. */
  const b = be();
  const td = oTD('500.000', '7|duToan');
  b.F.mo(td);
  td.firstChild.value = '999';
  b.F.dong(td, true);
  t('🔴 Esc → trả lại số cũ, không gửi gì',
    td.innerHTML === '500.000' && b.NK.gui === null, { html: td.innerHTML, gui: b.NK.gui });
}
{
  /* Máy chủ chối → ô phải quay về số cũ, không nằm lại ở số vừa gõ. */
  const b = be({ loi: 'Hạng mục "Thợ Phụ" đã lên lệnh tạm ứng rồi' });
  const td = oTD('500.000', '7|duToan');
  b.F.mo(td);
  td.firstChild.value = '0';
  b.F.luu(td);
  t('🔴 máy chủ CHỐI → ô quay về số cũ (để lại số vừa gõ là màn nói dối về sổ)',
    td.innerHTML === '500.000', td.innerHTML);
  t('   và nói ra câu chối của máy chủ, không nuốt',
    b.NK.toast.some(x => /đã lên lệnh tạm ứng/.test(x[1])), b.NK.toast);
  t('   KHÔNG nạp lại dự án khi hỏng', b.NK.nap === 0, b.NK.nap);
}

/* ── 3. BẢNG BÀY ĐÚNG Ô NÀO SỬA ĐƯỢC ──────────────────────────────────────────────────── */
t('🔴 ô dự toán / SL / đơn giá / thực tế bày nút gõ thẳng',
  /o\('duToan',/.test(HTML) && /o\('soLuong',/.test(HTML)
  && /o\('donGia',/.test(HTML) && /o\('thucTe',/.test(HTML));
/* 🔴 THÀNH TIỀN LÀ SỐ MÁY TÍNH (SL × đơn giá). Cho gõ là đẻ ra một con số thứ ba không khớp
   hai cái sinh ra nó — và không ai biết con nào mới đúng. */
t('🔴 ô THÀNH TIỀN KHÔNG gõ được — nó là số lượng × đơn giá, máy tính',
  !/o\('thanhTien',/.test(HTML));
t('   ô ghi chú cũng gõ thẳng được', /data-osua="'\+l\.row\+'\|note"/.test(HTML));
/* 🔴 Ô ĐÃ KHOÁ THÌ KHÔNG MỜI GÕ. Bày con trỏ gõ ở một ô sẽ bị máy chủ chối là mời gõ rồi báo lỗi. */
t('🔴 hạng mục đã lên lệnh → mấy ô ấy thôi bày lối gõ (khoá theo `_daTtHang`)',
  /var khoaDT=\(\['nhap','tra'\]\.indexOf\(String\(_daTtHang\(l\)\)\) < 0\);/.test(HTML));
t('   nhưng ô THỰC TẾ vẫn mở kể cả lúc ấy', /o\('thucTe',\s*money\(l\.thucTe\),\s*false\)/.test(HTML));
t('🔴 người không có quyền sửa thì không bày lối gõ nào', /if\(!canEdit \|\| khoa\)/.test(HTML));
t('   và có kiểu dáng cho biết ô nào bấm được',
  fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/assets/css/vhcp.css'), 'utf8').indexOf('.o-sua') >= 0);
t('🔴 nút ✏️ VẪN CÒN cho mấy ô form lo (hình thức chi, VAT, loại chi phí, "Thuộc")',
  /daEditLine\('\+l\.row\+'\)/.test(HTML));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: bấm vào ô là sửa được ngay, và không ô nào lọt chốt.');
