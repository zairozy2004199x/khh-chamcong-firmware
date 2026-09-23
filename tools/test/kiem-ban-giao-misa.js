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
 * =============================================================================================
 * 🔴 22/09/2026 — LUẬT NÀY ĐÃ TẮT, VÀ BÀI KIỂM ĐỔI CHIỀU THEO
 * =============================================================================================
 * Anh Thắng: *"Hiện tại chi phí máy tự động áp dụng web riêng nên không dùng chung nữa"*. MTĐ
 * chạy plugin riêng, bên ấy tự xuất MISA của mình; bản gốc không còn ai mang vai MTĐ để chặn.
 * `KHOI_KHONG_XUAT_MISA` nay RỖNG ở cả hai bên.
 *
 * 🔴 TẮT, KHÔNG PHẢI GỠ — nên bài này GIỮ NGUYÊN mọi phép về CƠ CHẾ và chỉ đổi mấy phép về
 *    CHÍNH SÁCH. Hai thứ khác hẳn nhau:
 *      · chính sách = "khối nào bị chặn" → nay không khối nào, nên đổi chiều;
 *      · cơ chế = danh sách phải là DANH SÁCH, sáu cửa MISA phải đi qua nó, `applyPerms` phải
 *        cắt được `vis.xuat`, máy chủ phải đọc VAI ĐANG MANG → giữ hết.
 *    Xoá phần cơ chế vì "đang tắt mà" là ngày anh Thắng bật lại một khối thì không còn ai canh,
 *    và cái hỏng ấy chỉ lộ ra ở MISA.
 *
 * ⚠️ Mục 1b bên dưới CHẠY THẬT với danh sách seeded `['mtd']` — chứng minh cơ chế còn sống chứ
 *    không phải mã chết. Phép "rỗng thì ai cũng xuất được" một mình nó cũng xanh trên một hàm
 *    đã bị đục ruột.
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

/* 🔴 DANH SÁCH RỖNG = KHÔNG AI BỊ CHẶN (22/09/2026). Đọc nhầm chiều `! in_array(...)` là sửa
   thành chặn cả nhà, nên mấy phép này nói thẳng ra từng vai một. */
t('🔴 "Kế Toán Máy Tự Động" NAY xuất MISA được — MTĐ đã ra web riêng',
  duoc('Kế Toán Máy Tự Động') === true);
t('🔴 "Kế Toán Khu Vui Chơi" VẪN xuất MISA', duoc('Kế Toán Khu Vui Chơi') === true);
t('🔴 "Kế Toán Văn Phòng" VẪN xuất MISA', duoc('Kế Toán Văn Phòng') === true);
t('   vai chạy ngang ("Kế toán cá nhân") không bị chặn', duoc('Kế toán cá nhân') === true);
t('   Admin không bao giờ bị chặn', duoc('Admin') === true);

/* ═══ 1b. CƠ CHẾ CÒN SỐNG — SEED LẠI DANH SÁCH RỒI CHẠY ═══════════════════════════
 * 🔴 ĐÂY LÀ PHÉP QUAN TRỌNG NHẤT CỦA CẢ BÀI SAU KHI TẮT. "Rỗng thì ai cũng xuất được" cũng
 *    XANH trên một hàm đã bị đục ruột (`return true;` là qua hết). Seed `['mtd']` vào rồi chạy
 *    lại chính hàm ấy thì mới phân biệt được "đang tắt" với "đã hỏng".
 * ⚠️ Seed bằng cách thay ĐÚNG dòng khai, không viết lại hàm — viết lại là canh bản của bài
 *    kiểm, không phải bản của app. */
const BASE_BAT = BASE.replace(/var KHOI_KHONG_XUAT_MISA=\[\];/, "var KHOI_KHONG_XUAT_MISA=['mtd'];");
t('⚠️ seed được danh sách (dòng khai đúng như mong đợi)', BASE_BAT !== BASE, bocDong('KHOI_KHONG_XUAT_MISA'));
const duocBat = (vai) => new Function('CURUSER', BASE_BAT + '\nreturn _xuatMisaDuoc();')({ role: vai });
t('🔴 bật lại "mtd" → kế toán MTĐ bị chặn trở lại', duocBat('Kế Toán Máy Tự Động') === false);
t('   và kế toán KVC vẫn không việc gì', duocBat('Kế Toán Khu Vui Chơi') === true);
t('   Admin vẫn không bao giờ bị chặn', duocBat('Admin') === true);
/* ⚠️ Mấy tên gọi khác của cùng một khối — bảng `KHOI_THEO_TEN_VAI` đã gom sẵn, phép này canh
   để đừng ai lỡ tay xoá bớt. Đo trên bản ĐÃ BẬT, vì bản tắt thì tên nào cũng qua. */
t('⚠️ "Kế Toán POSH" cũng là máy tự động', duocBat('Kế Toán POSH') === false);
t('⚠️ "Kế Toán MTĐ" viết tắt cũng vậy', duocBat('Kế Toán MTĐ') === false);

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
/* 🔴 VẪN LÀ DANH SÁCH, dù nay rỗng — không phải phép "khác kvc thì chặn". Đổi sang phép ấy là
   ngày bật lại một khối thì khối thứ tư bị chặn oan mà không ai khai gì. */
t('🔴 khai bằng DANH SÁCH khối, và nay RỖNG (MTĐ đã ra web riêng)',
  /const KHOI_KHONG_XUAT_MISA\s*=\s*array\(\s*\);/.test(CFG), (CFG.match(/const KHOI_KHONG_XUAT_MISA[^;]*;/) || [''])[0]);
/* ⚠️ HAI BÊN PHẢI CÙNG RỖNG. Lệch một vế là màn giấu tab Xuất MISA trong khi máy chủ vẫn cho
   gọi, hoặc ngược lại — người ta bấm được rồi ăn một câu lỗi. */
t('🔴 và bản giao diện cũng rỗng y như vậy',
  /var KHOI_KHONG_XUAT_MISA=\[\];/.test(HTML), bocDong('KHOI_KHONG_XUAT_MISA'));

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
/* ⚠️ Dải này nay kể cả bước `Đã thanh toán` (1.248.0), nên nó gọi `_ttTrongLuong` — mượn HÀM
   THẬT chứ không bịa, vì bịa là bệ đỡ xanh cả khi luật luồng hỏng. */
const bocDongV = (t) => { const i = HTML.indexOf('  var ' + t + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); };
const bocKhoiV = (t) => { const i = HTML.indexOf('  var ' + t + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('};', i) + 2); };
const NEN_LUONG = [bocDongV('KHOI_LUONG_CHI'), bocKhoiV('LUONG_KVC'), bocKhoiV('LUONG_CHI'),
  bocHam('_luongKhoi'), bocHam('_ttTrongLuong')].join('\n');
t('⚠️ bốc được nền luồng', NEN_LUONG.replace(/\s/g, '').length > 200, NEN_LUONG.length);
const veQt = (vai, khoi) => {
  const NK = {};
  const moi = { CURUSER: { role: vai }, KHOI_DANG: khoi || 'mtd', el: id => (NK[id] = NK[id] || { style: {}, innerHTML: '' }) };
  new Function('moi', `with(moi){ ${BASE}\n${NEN_LUONG}\n${bocHam('renderQtBanGiao')}\n return renderQtBanGiao; }`)(moi)();
  return NK.qtBanGiao;
};
/* 🔴 DANH SÁCH RỖNG → DẢI KHÔNG HIỆN VỚI AI CẢ. Để sót là kế toán mở màn Quyết toán ra thấy
   một dải vàng bảo họ "đơn đã bàn giao sang kế toán KVC" cho một luật không còn chạy. */
teq('🔴 kế toán MTĐ KHÔNG còn thấy dải bàn giao', 'none', veQt('Kế Toán Máy Tự Động').style.display);
teq('   kế toán KVC không thấy', 'none', veQt('Kế Toán Khu Vui Chơi').style.display);
teq('   kế toán VP cũng không thấy', 'none', veQt('Kế Toán Văn Phòng').style.display);

/* ⚠️ NHƯNG DẢI PHẢI CÒN VẼ ĐƯỢC — cùng lý lẽ với mục 1b: tắt chứ không gỡ. Seed danh sách rồi
   vẽ lại; câu chữ vẫn phải kể đủ bước `Đã thanh toán` (thêm ở 1.248.0), vì bỏ nó khỏi câu là
   kế toán duyệt xong ngồi đợi, không biết còn phải bấm một nút nữa. */
const veQtBat = (vai, khoi) => {
  const NK = {};
  const moi = { CURUSER: { role: vai }, KHOI_DANG: khoi || 'mtd', el: id => (NK[id] = NK[id] || { style: {}, innerHTML: '' }) };
  new Function('moi', `with(moi){ ${BASE_BAT}\n${NEN_LUONG}\n${bocHam('renderQtBanGiao')}\n return renderQtBanGiao; }`)(moi)();
  return NK.qtBanGiao;
};
teq('🔴 bật lại "mtd" → dải hiện trở lại', 'block', veQtBat('Kế Toán Máy Tự Động').style.display);
t('🔴 và câu nhắc vẫn kể cả bước Đã thanh toán',
  /Đã thanh toán/.test(veQtBat('Kế Toán Máy Tự Động', 'mtd').innerHTML),
  veQtBat('Kế Toán Máy Tự Động', 'mtd').innerHTML);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: luật bàn giao MISA đã TẮT (MTĐ ra web riêng), cơ chế vẫn bật lại được.');
