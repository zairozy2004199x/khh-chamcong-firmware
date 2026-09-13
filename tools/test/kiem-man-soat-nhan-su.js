/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN SOÁT TRÙNG NHÂN SỰ ↔ CHI PHÍ — PHẦN NHÌN THẤY ĐƯỢC.
 *
 * Anh Thắng 13/09/2026: *"nếu đẩy từ nhân sự sang, mà nhân viên này trùng với nhân viên tạo
 * trực tiếp trên trang chi phí thì sao, làm sao để gộp lại"*.
 *
 * `kiem-noi-nhan-su-chi-phi.php` canh phần máy chủ: nó soát đúng, đổi tên đúng, chối đúng chỗ.
 * Bài này canh phần CÒN LẠI — thứ đã cắn ba lần trong tuần: *hàm chạy đúng mà đường đi tới hàm
 * bị đứt*. Nút bị `disabled`, cổng API quên tham số, khối không ai gọi tới.
 *
 * =============================================================================================
 * 🔴 BA CHỖ BÀI NÀY CANH, VÀ VÌ SAO:
 *
 *   1. NÚT PHẢI CÓ VÀ PHẢI GỌI ĐÚNG HÀM. Khối soát nằm trong thẻ "Người dùng & Phân quyền" và
 *      chỉ Admin thấy. Quên bật hiện là cả tính năng nằm đó không ai với tới.
 *
 *   2. KHÔNG ĐƯỢC TỰ CHẠY TRONG `renderUsers()`. Hàm ấy chạy lại sau MỖI lượt Lưu cấu hình,
 *      còn phép soát thì quét cả sổ nhân sự rồi đếm dòng trên tám bảng. Tự chạy là mỗi lượt
 *      Lưu kéo theo một phép quét toàn sổ mà chẳng ai xin.
 *
 *   3. GỘP PHẢI HỎI HAI LẦN. Đổi tên sang một tên ĐÃ CÓ tài khoản là nhập hai sổ đơn làm một,
 *      không có đường về. Máy chủ chối lượt đầu; màn phải BẮT đúng câu chối ấy và hỏi lại,
 *      chứ không được tự gửi lại kèm cờ gộp.
 *
 * ⚠️ GỠ CHÚ THÍCH TRƯỚC KHI QUÉT. Khối chú thích của chính bản vá nhắc đủ mọi tên hàm mà phép
 *    dưới đang tìm — quét cả chú thích là bài xanh kể cả khi mã đã bị gỡ sạch. Đã cắn hai lần
 *    (12/09 và 13/09/2026).
 *
 * Chạy: node tools/test/kiem-man-soat-nhan-su.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
/* Gỡ chú thích KIỂU JS — chỉ dùng cho THÂN HÀM đã bốc ra, không bao giờ cho cả tệp. */
const boChuThich = x => String(x).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' ');
/* 🔴 CẢ TỆP THÌ PHẢI GỠ KIỂU HTML, KHÔNG ĐƯỢC GỠ KIỂU JS. Trong phần thân trang có
   thuộc tính `accept` của ô chọn ảnh nhận giá trị "image/" kèm dấu sao — tức một dấu
   `/` liền dấu sao nằm trong HTML, không phải mở chú thích. Gỡ
   kiểu JS trên cả tệp là nó khớp từ đó tới dấu đóng chú thích đầu tiên tận dưới vùng script và nuốt trắng
   hơn ba trăm dòng HTML ở giữa, trong đó có đúng cái nút bài này đang tìm. Bài đỏ, mã thì
   không sao — mất một lượt mới nhận ra. (Cắn ngay lượt viết đầu, 13/09/2026.) */
const MA = String(HTML).replace(/<!--[\s\S]*?-->/g, ' ');

function boc(ten) {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) { console.log('\n✗ Không bốc được ' + ten + ' — dừng.'); process.exit(1); }
  const j = HTML.indexOf('\n  }', i);
  return HTML.slice(i, j + 4);
}

/* ═══ 1. ĐƯỜNG ĐI TỚI MÀN ═════════════════════════════════════════════════════════ */
t('🔴 có nút mở màn soát', /id="btnSoatNs"[^>]*onclick="doSoatNs\(\)"/.test(MA), null);
t('🔴 có chỗ để vẽ kết quả (soatNsBox)', /id="soatNsBox"/.test(MA), null);

const RENDER = boc('renderUsers');
const RENDER_MA = boChuThich(RENDER);
t('🔴 nút soát được bật/tắt theo Admin trong renderUsers()',
  /btnSoatNs'\)\.style\.display\s*=\s*_laAdmin\(\)/.test(RENDER_MA), null);
/* 🔴 Phép quan trọng nhất của phần này. `renderUsers()` chạy lại sau mỗi lượt Lưu — gọi
   `doSoatNs()` trong đó là mỗi lượt Lưu kéo theo một phép quét toàn sổ. */
t('🔴 renderUsers() KHÔNG tự gọi doSoatNs()', !/doSoatNs\s*\(/.test(RENDER_MA), RENDER_MA.slice(0, 200));
/* Không phải Admin thì khối phải bị dọn đi, không để lại kết quả của lượt trước trên màn. */
t('   không phải Admin thì dọn khối đi', /soatNsBox'\)\.innerHTML\s*=\s*''/.test(RENDER_MA), null);

const SOAT = boc('doSoatNs');
t('🔴 doSoatNs() gọi hàm máy chủ soatNhanSu()', /\.soatNhanSu\(\)/.test(boChuThich(SOAT)), null);
t('   và chặn người không phải Admin ngay tại màn', /_laAdmin\(\)/.test(boChuThich(SOAT)), null);

/* ═══ 2. NĂM NHÓM PHẢI ĐƯỢC VẼ RA ĐỦ ═════════════════════════════════════════════
 * Máy chủ chia năm nhóm. Màn quên vẽ một nhóm là những người trong nhóm ấy biến mất khỏi
 * mắt người soát — mà cả tính năng này chỉ có một việc: BÀY RA cho người ta thấy.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
const VE = boChuThich(boc('veSoatNs'));
/* ⚠️ CANH ĐÚNG CÁI NHÁNH, KHÔNG CHỈ CÁI TÊN. Bản đầu chỉ tìm chuỗi `n.lech` trong thân hàm —
   mà đổi `if((n.lech||[]).length)` thành `if(false)` vẫn để lại chuỗi ấy trong khối chết bên
   dưới, nên phép xanh trong khi cả nhóm không bao giờ được vẽ. Phá thử bắt đúng chỗ ấy
   (13/09/2026). Nay bắt nguyên cái điều kiện mở nhánh. */
['trungTen', 'lech', 'lechPb', 'thieuCp', 'thieuNs', 'khop'].forEach(function (g) {
  const re = new RegExp('if\\(\\(n\\.' + g + '\\|\\|\\[\\]\\)\\.length\\)|var\\s+kh\\s*=\\s*\\(n\\.' + g);
  t('🔴 có nhánh vẽ nhóm "' + g + '"', re.test(VE), null);
  t('   và nhóm ấy được duyệt ra hàng', new RegExp('\\(n\\.' + g + '\\|\\|\\[\\]\\)\\.forEach|n\\.' + g + '\\[0\\]|\\(n\\.' + g + '\\|\\|\\[\\]\\)\\.filter').test(VE), null);
});
t('⚠️ nói rõ khi trang Nhân sự chưa cài', /coNhanSu/.test(VE), null);
t('⚠️ và nói rõ khi hai bên khớp hết', /khớp hết/.test(VE), null);

/* ═══ 3. GỘP PHẢI HỎI HAI LẦN ════════════════════════════════════════════════════ */
const GUI = boChuThich(boc('_guiDoiTenNguoi'));
t('🔴 gửi cờ gộp xuống máy chủ', /doiTenNguoi\(cu\s*,\s*moi\s*,\s*gop/.test(GUI), null);
t('🔴 bắt đúng câu chối của máy chủ rồi mới hỏi lại',
  /bấm lại và xác nhận gộp/.test(GUI) && /_guiDoiTenNguoi\(cu\s*,\s*moi\s*,\s*true\)/.test(GUI), null);
/* 🔴 KHÔNG ĐƯỢC TỰ GỬI LẠI. Lượt hỏi lại phải đi qua `confirm()` — bỏ nó đi là cú gộp xảy ra
   sau đúng MỘT cú bấm, mà gộp thì không có đường về. */
t('🔴 lượt gộp phải đi qua confirm()', /confirm\(e\s*\+/.test(GUI), GUI.slice(0, 400));
t('   và lượt đổi tên thường cũng hỏi trước', /confirm\('Đổi MỌI dòng/.test(GUI), null);

const MO = boChuThich(boc('moDoiTenNguoi'));
t('🔴 chỉ Admin đổi tên được', /_laAdmin\(\)/.test(MO), null);
t('   lượt đầu KHÔNG mang cờ gộp', /_guiDoiTenNguoi\(cu\s*,\s*moi\s*,\s*false\)/.test(MO), null);

/* Hai con số "chưa xếp phòng ban" phải được nói ra riêng — "chưa khai" và "khai khác nhau" là
   hai việc khác hẳn, gộp làm một là chỗ lệch thật lẫn mất trong đống dòng không sửa được gì. */
t('🔴 nói riêng số người CHƯA xếp phòng ban', /pbChuaNs/.test(VE) && /pbChuaCp/.test(VE), null);

/* ═══ 3b. LẤY PHÒNG BAN THEO SỔ NHÂN SỰ ══════════════════════════════════════════
 * Anh Thắng 13/09/2026: *"quyết định bộ phận do nhân sự quyết định"* — nhưng chốt luôn là chưa
 * khoá ô bên chi phí vội, chạy song song để đối chiếu. Nút này là đường tạm của giai đoạn ấy.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
const LAYPB = boChuThich(boc('layPbTuNs'));
t('🔴 lấy phòng ban xong chỉ vẽ lại, KHÔNG tự lưu',
  /renderUsers\(\)/.test(LAYPB) && !/_saveCfg\s*\(/.test(LAYPB), LAYPB.slice(0, 300));
t('   làm được cả một người lẫn tất cả', /!ten \|\| x\.ten\s*===\s*ten/.test(LAYPB), LAYPB.slice(0, 300));
t('   và ghi đè ô Bộ phận theo sổ nhân sự', /u\.boPhan\s*=\s*ban\[k\]/.test(LAYPB), null);

/* ═══ 4. HAI NÚT TIỆN TAY — ĐIỀN MÃ VÀ THÊM DÒNG ═════════════════════════════════
 * ⚠️ Cả hai chỉ được sửa Ô TRÊN MÀN rồi vẽ lại, KHÔNG tự lưu. Lượt Lưu ghi đè cả bảng, nên
 *    người bấm phải nhìn thấy mình sắp lưu cái gì trước đã.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
const DIEN = boChuThich(boc('dienMaNvTuSoat'));
t('🔴 điền mã xong chỉ vẽ lại, KHÔNG tự lưu',
  /renderUsers\(\)/.test(DIEN) && !/_saveCfg\s*\(/.test(DIEN), DIEN.slice(0, 300));
t('⚠️ chỉ điền vào ô đang TRỐNG, không đè mã người ta gõ tay',
  /!String\(u\.maNv\|\|''\)\.trim\(\)/.test(DIEN), null);

const THEM = boChuThich(boc('themUserTuNs'));
t('🔴 thêm dòng xong cũng chỉ vẽ lại, KHÔNG tự lưu',
  /renderUsers\(\)/.test(THEM) && !/_saveCfg\s*\(/.test(THEM), null);
t('🔴 không thêm trùng người đã có trong bảng', /da\s*\)\s*\{/.test(THEM) && /toLowerCase\(\)/.test(THEM), null);
t('   dòng mới mang sẵn mã NV', /maNv:\s*String\(maNv/.test(THEM), null);

/* ═══ 5. CỘT MÃ NV TRÊN BẢNG NGƯỜI DÙNG ══════════════════════════════════════════
 * 🔴 `saveCfgUsers()` GOM TỪ MÀN và lượt Lưu GHI ĐÈ cả bảng. Ô nào không dựng ra trên màn thì
 *    không nằm trong gói gửi lên — nghĩa là lượt Lưu kế tiếp XOÁ TRẮNG cột ấy của mọi người,
 *    im lặng. Nên cột Mã NV phải có mặt ở CẢ BA chỗ: tiêu đề, hàng thường, hàng mới.
 *    (Chỉ số ô thì `test-cauhinh-xo.js` canh riêng — nó đếm ô thật rồi so với chỉ số đang dùng.)
 * ═════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 tiêu đề bảng có cột Mã NV', /<th[^>]*>Mã NV<\/th>/.test(MA), null);
const HANG = boChuThich(boc('_uHang'));
t('🔴 hàng người dùng dựng ô Mã NV', /_inp\(u\.maNv/.test(HANG), null);
t('🔴 hàng Admin bị khoá cũng bày Mã NV (đủ số ô, khỏi lệch cột)',
  /esc\(u\.maNv\|\|''\)/.test(HANG), null);
/* 🔴 `addCfgUser()` có HAI đường dựng hàng mới, và cả hai phải có ô Mã NV:
     · nhánh chưa có bảng nào -> đẩy vào `CFGUSERS` rồi vẽ lại cả khối
     · nhánh thường          -> chèn thẳng HTML vào tbody
   Bản đầu chỉ canh nhánh trên. Gỡ ô khỏi nhánh `insertAdjacentHTML` thì bài vẫn xanh, trong
   khi hàng vừa thêm thiếu một ô — mà `_readRows()` đọc theo VỊ TRÍ, nên mọi giá trị của
   riêng hàng ấy trượt sang cột bên cạnh lúc Lưu. Phá thử bắt đúng chỗ ấy (13/09/2026). */
const ADD = boChuThich(boc('addCfgUser'));
t('🔴 nhánh vẽ lại cả khối có ô Mã NV', /maNv:''/.test(ADD), ADD.slice(0, 300));
const CHEN = (ADD.match(/insertAdjacentHTML\([\s\S]*?\);/) || [''])[0];
t('🔴 nhánh chèn thẳng HTML cũng có ô Mã NV', /_inp\('','VD: NV012'\)/.test(CHEN), CHEN.slice(0, 300));
const SAVE = boChuThich(boc('saveCfgUsers'));
t('🔴 saveCfgUsers gửi maNv lên máy chủ', /maNv:\(r\[\d\]\|\|''\)\.trim\(\)/.test(SAVE), SAVE.slice(0, 300));

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
console.log('');
if (TRUOT.length) {
  console.log('TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  ✗ ' + x));
  process.exit(1);
}
console.log('ĐẠT: ' + DAT + ' phép thử');
console.log('Tất cả phép thử đều đạt.');
