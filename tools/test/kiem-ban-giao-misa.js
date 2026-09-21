/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KẾ TOÁN MÁY TỰ ĐỘNG CHỈ SOÁT — ĐƠN DUYỆT XONG BÀN GIAO CHO KẾ TOÁN KVC XUẤT MISA.
 *
 * Anh Thắng 21/09/2026: *"Chỗ phần kế toán máy tự động duyệt xong sẽ đẩy qua kế toán KVC tổng
 * kết (vì kế toán máy tự động chỉ check chứ ko đẩy misa). Nên em thêm chỗ đẩy lệnh từ kế toán
 * MTĐ sang kế toán KVC"*. Hỏi lại thì anh chốt: đẩy **tự động lúc duyệt quyết toán**, và
 * **chỉ MTĐ — Văn phòng vẫn tự xuất MISA**.
 *
 * =============================================================================================
 * 🔴 KHÔNG CÓ CỘT "ĐÃ BÀN GIAO", VÀ ĐÓ LÀ CHỦ Ý
 * =============================================================================================
 * Bàn giao xảy ra TỰ ĐỘNG, nên nó đã được nói trọn vẹn bởi hai thứ CÓ SẴN: đơn mang khối
 * 'mtd', và trạng thái đã sang 'Đã quyết toán'. Đẻ thêm một cột cờ thì phải nới bảng, lấp cho
 * mấy chục đơn đã ở 'Đã quyết toán' từ trước, và đóng dấu ở CẢ HAI hàm quyết toán
 * (`xac_nhan_quyet_toan_cn` và `..._ncc`) — quên một chỗ là đơn duyệt xong mà nằm im, không ai
 * bên KVC biết mà xuất. Suy ra thì không có gì để quên. Ai bàn giao / lúc nào: `nguoi_qt` và
 * `ngay_qt` đã ghi sẵn.
 *
 * 🔴 CHỐT Ở MÁY CHỦ, KHÔNG CHỈ GIẤU TAB. `markExported` ghi thẳng 'Đã xuất MISA' vào sổ — một
 *    lượt gọi tay là đơn cả tháng bị đánh dấu đã xuất trong khi chưa tệp nào đi ra.
 *
 * 🔴 KHÔNG GỘP VÀO `required_roles()`. Hàm ấy so bằng VAI GỐC (cố ý, để vai con thừa hưởng vai
 *    cha). Luật này thì ngược lại — nó phân biệt ĐÚNG hai vai con cùng cha: "Kế Toán Máy Tự
 *    Động" và "Kế Toán Khu Vui Chơi" đều kế thừa "Kế toán cá nhân".
 *
 * Chạy: node tools/test/kiem-ban-giao-misa.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const CFG = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php', 'utf8');
const API = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-api.php', 'utf8');
const MISA = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-misa.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
function bocDong(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); }
/* ⚠️ Gỡ chú thích CHỈ trên thân hàm đã bốc — gỡ trên cả tệp thì một dấu mở trong <style> bắt
   cặp với một dấu đóng dưới <script> và nuốt trọn thân trang (đã cắn 21/09/2026). */
function sachHam(ten) { return bocHam(ten).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' '); }

/* ═══ 0. BỆ ĐỠ CÓ THẬT CHƯA — không thì mọi phép dưới xanh rỗng ═════════════════ */
t('⚠️ bốc được `_xuatMisaDuoc`', bocHam('_xuatMisaDuoc').length > 60);
t('⚠️ bốc được `applyPerms`', bocHam('applyPerms').length > 500);

/* ═══ 1. AI BỊ CHẶN — CHẠY THẬT PHÉP LUẬT ═══════════════════════════════════════ */
function bocMang(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); }
const BASE = bocMang('KHOI_THEO_TEN_VAI') + '\n' + bocHam('_boDauVai') + '\n' + bocHam('_khoiCuaVai') + '\n'
  + bocDong('KHOI_KHONG_XUAT_MISA') + '\n' + bocHam('_xuatMisaDuoc');
t('⚠️ nền chạy thử dựng được', BASE.length > 400, BASE.length);
const duoc = (vai) => new Function('CURUSER', BASE + '\nreturn _xuatMisaDuoc();')({ role: vai });

t('🔴 "Kế Toán Máy Tự Động" KHÔNG xuất MISA', duoc('Kế Toán Máy Tự Động') === false);
t('🔴 "Kế Toán Khu Vui Chơi" VẪN xuất MISA', duoc('Kế Toán Khu Vui Chơi') === true);
/* 🔴 Anh Thắng chốt: *"Chỉ MTĐ, Văn phòng tự xuất MISA"*. Đây là phép canh cho đúng câu ấy —
   viết luật kiểu "khác kvc thì chặn" là VP bị chặn oan, và bài này đỏ ngay. */
t('🔴 "Kế Toán Văn Phòng" VẪN xuất MISA — anh chốt chỉ MTĐ', duoc('Kế Toán Văn Phòng') === true);
t('   vai chạy ngang ("Kế toán cá nhân") không bị chặn', duoc('Kế toán cá nhân') === true);
t('   Admin không bao giờ bị chặn', duoc('Admin') === true);
/* ⚠️ Mấy tên gọi khác của cùng một khối — bảng `KHOI_THEO_TEN_VAI` đã gom sẵn, phép này canh
   để đừng ai lỡ tay xoá bớt. */
t('⚠️ "Kế Toán POSH" cũng là máy tự động → bị chặn', duoc('Kế Toán POSH') === false);
t('⚠️ "Kế Toán MTĐ" viết tắt cũng bị chặn', duoc('Kế Toán MTĐ') === false);

/* ═══ 2. GIẤU TAB — PHẢI CẮT Ở BẢNG CHÍNH, KHÔNG CHỈ Ở CHỖ CỘNG THÊM ════════════ */
/* 🔴 Bảng `vis` tra theo VAI GỐC, nên "Kế Toán Máy Tự Động" thừa hưởng `xuat:1` của "Kế toán
   cá nhân". `_applyTabPerms()` chỉ CỘNG THÊM tab — nó không cắt được cái đã bật ở đó. */
t('🔴 `applyPerms` cắt `vis.xuat` khi không được xuất',
  /if\(!_xuatMisaDuoc\(\)\)\s*vis\.xuat=0;/.test(sachHam('applyPerms')), sachHam('applyPerms').slice(0, 200));
t('🔴 và chỗ CỘNG THÊM tab cũng phải hỏi cùng phép ấy',
  /show\('xuat',\s*canDo\('xuatMISA'\)\s*&&\s*_xuatMisaDuoc\(\)\)/.test(sachHam('_applyTabPerms')), sachHam('_applyTabPerms'));

/* ═══ 3. CỬA CHÍNH Ở MÁY CHỦ ════════════════════════════════════════════════════ */
t('🔴 PHP có luật `xuat_misa_duoc()`', /public static function xuat_misa_duoc\(\)/.test(CFG));
t('🔴 và đọc TÊN VAI ĐANG MANG, không phải vai gốc',
  /xuat_misa_duoc[\s\S]{0,400}?khoi_cua_vai\(\s*VHCP_Auth::vai_hien\(\)\s*\)/.test(CFG));
t('🔴 Admin không bao giờ bị chặn ở PHP',
  /xuat_misa_duoc[\s\S]{0,200}?'Admin'\s*===\s*VHCP_Auth::vai_tro\(\)[\s\S]{0,40}?return true;/.test(CFG));
/* 🔴 DANH SÁCH, không phải phép "khác kvc thì chặn" — thêm khối thứ tư không được chặn oan. */
t('🔴 khai bằng DANH SÁCH khối, và trong đó chỉ có mtd',
  /const KHOI_KHONG_XUAT_MISA\s*=\s*array\(\s*'mtd'\s*\);/.test(CFG), (CFG.match(/const KHOI_KHONG_XUAT_MISA[^;]*;/) || [''])[0]);

/* Sáu cửa MISA — lọt một cái là luật trên vô nghĩa với đúng cửa đó. */
const SAU = ['exportMisa', 'exportMisaKyThuat', 'exportMisaMarketing', 'exportMisaBP', 'exportMisaSoChi', 'markExported'];
const dsFn = (API.match(/private static \$misa_fns\s*=\s*array\(([\s\S]*?)\);/) || ['', ''])[1];
SAU.forEach(f => t('🔴 cửa `' + f + '` nằm trong danh sách chặn',
  new RegExp("'" + f + "'").test(dsFn), dsFn));
/* ⚠️ Mỗi tên trong danh sách phải là một hàm CÓ THẬT trong bảng định tuyến — gõ sai một chữ
   thì cửa ấy vẫn mở toang mà không gì báo. */
SAU.forEach(f => t('⚠️ `' + f + '` đúng là một hàm có đăng ký', new RegExp("'" + f + "'\\s*=>\\s*array\\(").test(API)));
t('🔴 `handle()` chối sáu cửa ấy khi không được xuất',
  /in_array\(\s*\$fn,\s*self::\$misa_fns,\s*true\s*\)\s*&&\s*!\s*VHCP_Cfg::xuat_misa_duoc\(\)/.test(API));
/* ⚠️ Chối bằng 403 + `forbidden`, không phải 404: màn đã có nhánh đọc mã ấy. */
t('⚠️ trả 403 kèm mã `forbidden`',
  /misa_fns[\s\S]{0,600}?'code'\s*=>\s*'forbidden'[\s\S]{0,40}?\),\s*403\s*\)/.test(API));
/* 🔴 ĐỨNG TRƯỚC LỜI GỌI. Chốt nằm sau `call_user_func_array` thì hàm đã chạy xong, và
   `markExported` đã kịp ghi 'Đã xuất MISA' vào sổ. */
t('🔴 chốt đứng TRƯỚC `call_user_func_array`',
  API.indexOf('self::$misa_fns, true ) && ! VHCP_Cfg::xuat_misa_duoc()') < API.indexOf('call_user_func_array'));

/* ═══ 4. BÀN GIAO PHẢI NHÌN THẤY ĐƯỢC ═══════════════════════════════════════════ */
/* 🔴 Bàn giao im lặng thì y như không bàn giao. */
t('🔴 bản xuất đếm đơn theo khối', /'theoKhoi'\s*=>\s*\$theo_khoi/.test(MISA));
/* ⚠️ Đếm trên `$seen_don` (đơn THẬT SỰ có dòng trong tệp), không phải `$by_don` (mọi đơn đủ
   điều kiện) — đếm nhầm vế là con số không khớp với chính tệp bên dưới. */
t('⚠️ đếm trên `$seen_don`, không phải `$by_don`',
  /\$theo_khoi\s*=\s*array\(\);\s*foreach\s*\(\s*array_keys\(\s*\$seen_don\s*\)/.test(MISA.replace(/\s+/g, ' ')));
t('🔴 màn Xuất MISA có dải bàn giao', /id="xuatBanGiao"/.test(HTML) && bocHam('renderXuatBanGiao').length > 200);
t('   và `renderXuat` gọi nó', /renderXuatBanGiao\(\)/.test(sachHam('renderXuat')));
/* ⚠️ CHỈ BÁO, KHÔNG LỌC: cho nó quyền lọc là đẻ ra hai bản xuất khác nhau cho cùng một lượt bấm. */
t('⚠️ dải bàn giao KHÔNG đụng vào `XUAT.rows`', !/XUAT\.rows/.test(sachHam('renderXuatBanGiao')), sachHam('renderXuatBanGiao'));
t('⚠️ và không bày khối của chính mình', /KHOI_DANG/.test(sachHam('renderXuatBanGiao')));
/* Chạy thật dải ấy. */
const veBanGiao = (khoi, tk) => {
  const NK = {};
  const moi = { esc: x => String(x == null ? '' : x), KHOI_DANG: khoi, XUAT: { theoKhoi: tk },
    _tenKhoi: m => ({ kvc: 'Khu vui chơi', mtd: 'Máy tự động', vp: 'Văn phòng' }[m] || m),
    el: id => (NK[id] = NK[id] || { style: {}, innerHTML: '' }) };
  new Function('moi', `with(moi){ ${bocHam('renderXuatBanGiao')}\n return renderXuatBanGiao; }`)(moi)();
  return NK.xuatBanGiao;
};
let r = veBanGiao('kvc', { kvc: 30, mtd: 12 });
t('🔴 kế toán KVC thấy "12 đơn Máy tự động"', r.style.display === 'block' && /12<\/b> đơn Máy tự động/.test(r.innerHTML), r.innerHTML);
t('⚠️ và KHÔNG bày "30 đơn Khu vui chơi" — khối của chính mình', !/Khu vui chơi/.test(r.innerHTML), r.innerHTML);
r = veBanGiao('kvc', { kvc: 30 });
teq('   không có đơn khối khác → ẩn dải', 'none', r.style.display);
r = veBanGiao('kvc', { kvc: 30, mtd: 0 });
teq('   khối khác đếm 0 → cũng ẩn', 'none', r.style.display);
/* Nguồn khác (Kỹ thuật / Marketing / Công tác / Setup) không trả `theoKhoi` — phải ẩn, không
   được nổ. */
const NK2 = {};
new Function('moi', `with(moi){ ${bocHam('renderXuatBanGiao')}\n return renderXuatBanGiao; }`)(
  { esc: String, KHOI_DANG: 'kvc', XUAT: { cols: [], rows: [] }, _tenKhoi: m => m,
    el: id => (NK2[id] = NK2[id] || { style: {}, innerHTML: '' }) })();
/* ⚠️ PHÉP NÀY CANH "KHÔNG NỔ", KHÔNG CANH LỐI RA SỚM. Bỏ lối ra sớm trong `renderXuatBanGiao`
   (cho `tk={}` rồi đi tiếp) thì phép này VẪN XANH — đã thử, nó là đột biến TƯƠNG ĐƯƠNG: bảng
   rỗng thì vòng dưới không đẩy mục nào, và `if(!ds.length)` cũng ẩn dải. Ghi ra đây để lần sau
   đừng ai tưởng phép canh hỏng rồi đi bẻ nó. Lý do giữ lối ra sớm: nó nói ra hai chuyện khác
   nhau mà cùng ẩn dải — xem chú thích tại chỗ. */
teq('🔴 nguồn không có `theoKhoi` → ẩn dải, không nổ', 'none', NK2.xuatBanGiao.style.display);

/* ═══ 5. PHÍA KẾ TOÁN MTĐ — MẤT TAB PHẢI CÓ NGƯỜI GIẢI THÍCH ════════════════════ */
t('🔴 màn Quyết toán có dải giải thích', /id="qtBanGiao"/.test(HTML) && bocHam('renderQtBanGiao').length > 150);
t('   và `loadQT` gọi nó', /renderQtBanGiao\(\)/.test(sachHam('loadQT')));
/* ⚠️ Hiện theo LUẬT, không gõ cứng "mtd": hai chỗ lệch nhau là hoặc dải nói dối, hoặc tab biến
   mất mà không một chữ giải thích. */
t('⚠️ dải ấy hỏi đúng `_xuatMisaDuoc()`, không gõ cứng khối',
  /_xuatMisaDuoc\(\)/.test(sachHam('renderQtBanGiao')) && !/['"]mtd['"]/.test(sachHam('renderQtBanGiao')),
  sachHam('renderQtBanGiao'));
const veQt = (vai) => {
  const NK = {};
  const moi = { CURUSER: { role: vai }, el: id => (NK[id] = NK[id] || { style: {}, innerHTML: '' }) };
  new Function('moi', `with(moi){ ${BASE}\n${bocHam('renderQtBanGiao')}\n return renderQtBanGiao; }`)(moi)();
  return NK.qtBanGiao;
};
teq('🔴 kế toán MTĐ thấy dải giải thích', 'block', veQt('Kế Toán Máy Tự Động').style.display);
teq('   kế toán KVC không thấy', 'none', veQt('Kế Toán Khu Vui Chơi').style.display);
teq('   kế toán VP cũng không thấy — họ vẫn tự xuất', 'none', veQt('Kế Toán Văn Phòng').style.display);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: kế toán MTĐ chỉ soát, đơn duyệt xong bàn giao sang kế toán KVC xuất MISA.');
