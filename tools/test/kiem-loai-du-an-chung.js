/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LOẠI DỰ ÁN — MỘT DANH SÁCH CHUNG, MỌI BỘ PHẬN NHÌN GIỐNG NHAU
 *
 * Anh Thắng 12/09/2026, lúc đầu chỉ ra ô "LOẠI DỰ ÁN" của Marketing vẫn xổ Setup/Tháo dỡ:
 * *"sai hạng mục rồi"*. Rồi anh chốt lại cách làm:
 *   *"Thôi để anh phân loại theo NỘI DUNG chung, để anh cũng dùng được, tránh việc mỗi bộ phận
 *   một kiểu"*.
 *
 * =============================================================================================
 * 🔴 HAI DANH SÁCH PHẢI KHỚP NHAU: ô chọn trên màn và `VHCP_DuAn::LOAI_DU_AN` bên máy chủ.
 *    Lệch một chữ là người dùng chọn xong, bấm Lập đơn, và nhận "Loại dự án không hợp lệ" —
 *    một câu chối cho đúng thứ màn hình vừa mời họ chọn. Bài kiểm đọc CẢ HAI tệp rồi so.
 *
 * 🔴 GIÁ TRỊ LƯU XUỐNG SỔ KHÔNG ĐƯỢC ĐỔI THEO CHỮ HIỂN THỊ. 'Setup lắp đặt' và 'Tháo dỡ' đang
 *    nằm trên hàng trăm dòng cũ; sửa chữ cho dễ đọc mà kéo theo giá trị là mọi dòng ấy rơi ra
 *    ngoài mọi bộ lọc, và `loai_cp_mac_dinh()` thôi tra được mã tài khoản cho chúng.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY.
 *
 * Chạy: node tools/test/kiem-loai-du-an-chung.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const PHP  = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-duan.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. DANH SÁCH TRÊN MÀN
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const mDs = /var LOAI_DA_DS=\[[\s\S]*?\n  \];/.exec(HTML);
t('bốc được LOAI_DA_DS', !!mDs);
const DS = new Function((mDs ? mDs[0] : 'var LOAI_DA_DS=[];') + '\nreturn LOAI_DA_DS;')();
const GIA_TRI = DS.map(x => x.giaTri);
teq('🔴 năm loại, đúng thứ tự nội dung công việc',
  ['Setup lắp đặt', 'Tháo dỡ', 'Bán vé sớm', 'Khai trương', 'Sự kiện'], GIA_TRI);
t('mỗi loại có nhãn riêng', DS.every(x => x.nhan && x.nhan.length > 3), DS);

/* 🔴 KHÔNG CÒN TÁCH THEO BỘ PHẬN — đúng cái anh Thắng vừa bác. */
t('🔴 không còn bảng loại dự án theo bộ phận', HTML.indexOf('LOAI_DA_BP') < 0);
t('   và không còn hàm chọn danh sách theo bộ phận', HTML.indexOf('_loaiDaDs') < 0);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 MÀN VÀ MÁY CHỦ PHẢI KHỚP
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const mPhp = /const LOAI_DU_AN = array\(([^)]*)\);/.exec(PHP);
t('bốc được VHCP_DuAn::LOAI_DU_AN', !!mPhp);
const PHP_DS = mPhp ? mPhp[1].split(',').map(x => x.trim().replace(/^'|'$/g, '')).filter(Boolean) : [];
teq('🔴 danh sách hai bên khớp từng chữ', GIA_TRI, PHP_DS);
t('🔴 máy chủ kiểm theo đúng hằng ấy, không gõ cứng lại',
  /in_array\( \$loai, self::LOAI_DU_AN, true \)/.test(PHP));
t('   và không còn danh sách cũ gõ cứng hai loại',
  !/in_array\( \$loai, array\( 'Tháo dỡ', 'Setup lắp đặt' \)/.test(PHP));

/* 🔴 HAI LOẠI CŨ PHẢI CÒN NGUYÊN VĂN — hàng trăm dòng trong sổ đang mang đúng hai chuỗi này. */
t('🔴 giữ nguyên "Setup lắp đặt"', GIA_TRI.indexOf('Setup lắp đặt') >= 0, GIA_TRI);
t('🔴 giữ nguyên "Tháo dỡ"',       GIA_TRI.indexOf('Tháo dỡ') >= 0, GIA_TRI);
t('   và `loai_cp_mac_dinh()` vẫn tra được mã cho chúng',
  /'Tháo dỡ'\s*=>\s*'Chi phí tháo dỡ'/.test(PHP) && /'Setup lắp đặt'\s*=>/.test(PHP));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. Ô CHỌN ĐỔ ĐÚNG — chạy thật `_apLoaiDa()`
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 Ô GIẢ PHẢI DỰNG LẠI `options` KHI `innerHTML` ĐỔI — đúng như <select> thật.
   Để `options` là mảng rỗng cứng thì vòng "giữ lựa chọn cũ" không chạy lần nào, và phép kiểm
   ấy xanh kể cả khi mã bỏ hẳn nó đi. Đúng cái bẫy đã cắn mấy lần trong phiên này: bệ đỡ không
   ghi nhận thì bài kiểm nói về một thứ không tồn tại. */
function chayAp(cu) {
  const sel = {
    value: cu || '', options: [],
    set innerHTML(v) {
      this._html = v;
      this.options = (v.match(/value="([^"]*)"/g) || []).map(m => ({ value: m.slice(7, -1) }));
      /* 🔴 <select> THẬT KHÔNG NHỚ LỰA CHỌN CŨ. Thay innerHTML là value nhảy về mục ĐẦU, bất kể
         mục cũ còn trong danh sách hay không — chính vì thế mã phải tự đặt lại. Để bệ đỡ giữ
         hộ value là nó làm thay việc của mã, và phép kiểm "giữ lựa chọn cũ" xanh kể cả khi mã
         bỏ hẳn vòng ấy đi. */
      this.value = this.options.length ? this.options[0].value : '';
    },
    get innerHTML() { return this._html || ''; },
  };
  const KHO = { daLoai: sel };
  new Function('el', 'esc', 'LOAI_DA_DS', bocHam('_apLoaiDa') + '\n_apLoaiDa();')(
    function (id) { return KHO[id]; },
    function (x) { return String(x == null ? '' : x); },
    DS);
  return sel;
}
const s1 = chayAp('');
t('🔴 ô chọn đổ đủ năm mục', (s1.innerHTML.match(/<option /g) || []).length === 5, s1.innerHTML);
DS.forEach(function (x) {
  t('   có mục "' + x.giaTri + '"', s1.innerHTML.indexOf('value="' + x.giaTri + '"') >= 0, s1.innerHTML);
});
t('🔴 nhãn hiện chữ tiếng Việt, không hiện giá trị trần',
  s1.innerHTML.indexOf('Chi phí bán vé sớm') >= 0, s1.innerHTML);

/* ⚠️ GIỮ LỰA CHỌN CŨ khi đổ lại: `_apLoaiDa()` chạy mỗi lần mở khối tạo, mà người dùng có thể
   đã chọn rồi bấm đổi loại đơn rồi quay lại. Mất lựa chọn là họ phải chọn lại từ đầu. */
const s2 = chayAp('Sự kiện');
teq('giữ lựa chọn cũ nếu còn hợp lệ', 'Sự kiện', s2.value);
const s3 = chayAp('Loại đã bỏ');
t('lựa chọn cũ không còn hợp lệ thì thôi, không nổ', s3.innerHTML.length > 0);
teq('   và rơi về mục đầu như <select> thật', 'Setup lắp đặt', s3.value);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 NHÃN Ô TÊN: CHỈ "THÁO DỠ" LÀ CHỌN CƠ SỞ CÓ SẴN
 *
 * Tháo dỡ thì gian đã đứng đó từ trước. Setup và ba loại của Marketing đều là chỗ CHƯA có trong
 * danh mục — phải gõ tên. Viết ngược (chỉ Setup mới gõ tên) là mọi loại thêm sau này rơi vào
 * nhánh chọn-có-sẵn, và nhân viên không gõ nổi tên sự kiện của họ.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function nhan(loai) {
  const KHO = { daLoai: { value: loai }, daTenLbl: { textContent: '' }, daTen: { placeholder: '' } };
  new Function('el', bocHam('daOnLoai') + '\ndaOnLoai();')(function (id) { return KHO[id]; });
  return { lbl: KHO.daTenLbl.textContent, ph: KHO.daTen.placeholder };
}
t('Tháo dỡ → chọn cơ sở có sẵn', nhan('Tháo dỡ').lbl.indexOf('Chọn cơ sở') >= 0, nhan('Tháo dỡ'));
t('Setup → gõ tên gian mới',     nhan('Setup lắp đặt').ph.indexOf('Gõ tên') >= 0, nhan('Setup lắp đặt'));
['Bán vé sớm', 'Khai trương', 'Sự kiện'].forEach(function (v) {
  t('🔴 "' + v + '" → GÕ tên, không phải chọn có sẵn',
    nhan(v).lbl.indexOf('Chọn cơ sở') < 0 && nhan(v).ph.indexOf('Gõ') >= 0, nhan(v));
});

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. ÁP LẠI MỖI LẦN MỞ KHỐI TẠO — quét tĩnh
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 daMoTao() đổ lại ô Loại dự án', /_apTenNhom\(\);\s*\n\s*_apLoaiDa\(\);/.test(HTML));

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — một danh sách loại dự án chung, màn và máy chủ khớp nhau');
