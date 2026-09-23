/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐỔI VAI Ở MÀN CẤU HÌNH PHẢI ĂN NGAY — anh Thắng 08/09/2026
 *
 * Anh khai vai "Kế toán máy tự động", gán cho một tài khoản, cài đúng bản có sửa máy chủ, rồi
 * vẫn nhắn *"vẫn chưa"* — kèm ảnh thanh tiêu đề còn ghi vai CŨ.
 *
 * =============================================================================================
 * 🔴 HAI LỚP NHỚ, VÁ MỘT LỚP THÌ KHÔNG ĐỦ.
 *
 *   Lớp 1 (máy chủ): thẻ phiên ghi vai lúc đăng nhập, sống 30 ngày. Đã vá ở 1.85.0.
 *   Lớp 2 (trình duyệt): `initApp()` đọc `sessionStorage` xong là THOÁT LUÔN, không hỏi máy chủ
 *                        câu nào. Vai cũ nằm đó tới khi đóng hẳn trình duyệt — tải lại trang
 *                        không dọn được. Đây là lớp còn lại, và là thứ anh đang nhìn thấy.
 *
 * ⚠️ VÀ MỘT LỖI THỨ HAI NẰM IM SAU LỚP ẤY: `CURUSER` không giữ `roleGoc`. Vai gốc thì tình cờ
 *    vẫn khớp bảng tra tab nên không ai thấy gì; vai TỰ TẠO không có trong bảng ấy nên rơi vào
 *    nhánh mặc định và người đó chỉ còn đúng MỘT tab. Vá lớp 2 xong là lỗi này lộ ra ngay, nên
 *    phải vá cùng lượt.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY. Soi chuỗi không phân biệt được "có gọi aiDangDangNhap" với "gọi
 *    nhưng nhánh trên đã return trước rồi" — mà đó đúng là lỗi đang sửa.
 *
 * Chạy: node tools/test/kiem-doi-vai-an-ngay.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
function bocDong(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n', i);
  return (j > i) ? HTML.slice(i, j) : '';
}

const fnInit = bocHam('initApp');
t('bốc được initApp()', fnInit.length > 400, fnInit.length);
const fnDung = bocHam('_dungUser');
t('bốc được _dungUser()', fnDung.length > 60, fnDung);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. `_dungUser()` GIỮ ĐỦ TRƯỜNG — nhất là `roleGoc`
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const dungUser = new Function(fnDung + '\nreturn _dungUser;')();
teq('🔴 giữ roleGoc khi máy chủ có trả',
  'Kế toán cá nhân',
  dungUser({ name: 'A', role: 'Kế toán máy tự động', roleGoc: 'Kế toán cá nhân', coso: '', boPhan: '' }).roleGoc);
teq('máy chủ không trả roleGoc thì lui về chính tên vai',
  'Quản lý', dungUser({ name: 'A', role: 'Quản lý', coso: '', boPhan: '' }).roleGoc);
teq('giữ nguyên tên vai đã khai',
  'Kế toán máy tự động',
  dungUser({ name: 'A', role: 'Kế toán máy tự động', roleGoc: 'Kế toán cá nhân' }).role);
teq('bộ phận rỗng thì thành chuỗi rỗng, không phải undefined',
  '', dungUser({ name: 'A', role: 'X' }).boPhan);

/* Và ba chỗ dựng CURUSER đều phải đi qua khuôn ấy — không thì chỗ quên lại đánh rơi roleGoc. */
const soChep = (HTML.match(/CURUSER\s*=\s*\{\s*name:/g) || []).length;
teq('🔴 không còn chỗ nào tự chép tay CURUSER', 0, soChep);
t('đăng nhập đi qua _dungUser', /CURUSER=_dungUser\(r\)/.test(HTML));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. CHẠY THẬT `initApp()` TRÊN DOM GIẢ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function chay(opt) {
  opt = opt || {};
  const ss = { vhcp_user: opt.nho ? JSON.stringify(opt.nho) : null };
  const ls = { vhcp_token: opt.token === undefined ? 'tok-abc' : opt.token };
  const log = { hoiMayChu: 0, veLai: 0, moCong: 0, luuLai: null };
  const o = {};
  ['gate', 'gatePin', 'main'].forEach(function (id) { o[id] = { style: {}, value: '', focus: function () {} }; });

  /* `initApp()` còn gọi vài thứ dựng giao diện (phóng to ảnh bill…) không liên quan phép thử.
     Khai chúng thành hàm rỗng chứ đừng bỏ dòng gọi ra khỏi mã bốc được: bỏ là bắt đầu SỬA mã
     thật cho vừa bài kiểm, và bài kiểm ấy thôi nói về thứ đang chạy. */
  const f = new Function('sessionStorage', 'localStorage', 'google', 'el', 'loading',
    'onLoggedIn', '_moCongPin', 'SSO_USER', 'LOG', 'setTimeout', '_billZoomInit', 'toast',
    '_tienGanHet',
    fnDung + '\n' + fnInit + '\ninitApp();');
  f(
    { getItem: function (k) { return ss[k] || null; }, setItem: function (k, v) { ss[k] = v; log.luuLai = JSON.parse(v); } },
    { getItem: function (k) { return ls[k] || ''; }, removeItem: function (k) { delete ls[k]; } },
    { script: { run: {
      withSuccessHandler: function (ok) { this._ok = ok; return this; },
      withFailureHandler: function (ng) { this._ng = ng; return this; },
      aiDangDangNhap: function () {
        log.hoiMayChu++;
        if (opt.mang === 'hong') { this._ng(new Error('mạng đứt')); return; }
        if (opt.tra) { this._ok(opt.tra); }
      },
    } } },
    function (id) { return o[id] || null; },
    function () {},
    function () { log.veLai++; },
    function () { log.moCong++; },
    null, log, function (fn) { fn(); }, function () {}, function () {}, function () {}
  );
  return { log: log, o: o };
}

const NHO_CU  = { name: 'Chị Trinh', role: 'Kế toán cá nhân', roleGoc: 'Kế toán cá nhân', coso: 'VP', boPhan: '' };
const TRA_MOI = { ok: true, name: 'Chị Trinh', role: 'Kế toán máy tự động', roleGoc: 'Kế toán cá nhân', coso: 'VP', boPhan: '' };

/* 🔴 PHÉP CHÍNH: có bản nhớ CŨ và có token -> VẪN phải hỏi máy chủ. */
const r1 = chay({ nho: NHO_CU, tra: TRA_MOI });
teq('🔴 có sẵn bản nhớ mà vẫn hỏi lại máy chủ', 1, r1.log.hoiMayChu);
teq('🔴 và nhận vai MỚI', 'Kế toán máy tự động', r1.log.luuLai.role);
teq('vai gốc cũng lưu lại',  'Kế toán cá nhân',   r1.log.luuLai.roleGoc);
teq('🔴 vai đổi thì vẽ lại màn (tab và quyền theo vai)', 2, r1.log.veLai);
teq('không bày cổng PIN',    0, r1.log.moCong);

/* Vai KHÔNG đổi thì đừng vẽ lại lần hai — vẽ lại là màn nháy và mất chỗ đang xem. */
const r2 = chay({ nho: NHO_CU, tra: { ok: true, name: 'Chị Trinh', role: 'Kế toán cá nhân', roleGoc: 'Kế toán cá nhân', coso: 'VP', boPhan: '' } });
teq('vẫn hỏi máy chủ khi vai không đổi', 1, r2.log.hoiMayChu);
teq('⚠️ nhưng KHÔNG vẽ lại lần hai',      1, r2.log.veLai);

/* Chưa có bản nhớ -> hỏi máy chủ rồi mới vẽ, đúng như trước. */
const r3 = chay({ nho: null, tra: TRA_MOI });
teq('chưa có bản nhớ thì vẫn hỏi máy chủ', 1, r3.log.hoiMayChu);
teq('và vẽ đúng một lần',                  1, r3.log.veLai);
teq('lưu lại vai mới',  'Kế toán máy tự động', r3.log.luuLai.role);

/* Máy chủ chối (phiên hết hạn) -> bày cổng PIN. */
const r4 = chay({ nho: null, tra: { ok: false } });
teq('phiên hết hạn thì bày cổng PIN', 1, r4.log.moCong);
teq('và không vẽ màn',                0, r4.log.veLai);

/* 🔴 MÁY CHỦ CHỐI THÌ ĐÁ RA, KỂ CẢ KHI ĐANG CÓ BẢN NHỚ.
   `{ok:false}` là câu trả lời RÕ RÀNG: phiên hết hạn hoặc đã bị thu hồi. Giữ màn lại vì
   "trong máy còn bản nhớ" thì thu hồi phiên không còn hiệu lực — đúng thứ vừa sửa ở lớp máy
   chủ. Bản nhớ không phải bằng chứng về quyền, nó chỉ là ảnh chụp để vẽ cho nhanh. */
const r5 = chay({ nho: NHO_CU, tra: { ok: false } });
teq('🔴 máy chủ chối thì bày cổng PIN dù đang có bản nhớ', 1, r5.log.moCong);

/* ⚠️ NHƯNG LỖI MẠNG THÌ KHÁC HẲN. Máy chủ không nói gì cả — một nhịp rớt sóng mà đá người ta
   ra giữa lúc đang gõ dở một đơn thì mất luôn phần đang nhập. Trang đã vẽ từ bản nhớ rồi, cứ
   để họ làm; lượt gọi sau sẽ nhận câu chối thật nếu phiên hỏng thật. */
const r5b = chay({ nho: NHO_CU, mang: 'hong' });
teq('⚠️ lỗi mạng mà đang có bản nhớ thì giữ nguyên màn', 0, r5b.log.moCong);
teq('và màn vẫn vẽ từ bản nhớ',                          1, r5b.log.veLai);
/* Không có bản nhớ mà mạng hỏng thì đành bày cổng PIN — không còn gì để vẽ. */
const r5c = chay({ nho: null, mang: 'hong' });
teq('lỗi mạng và không có bản nhớ -> cổng PIN', 1, r5c.log.moCong);

/* 🔴 MẤT TOKEN THÌ KHÔNG CHO VÀO, dù bản nhớ còn nguyên.
   Không có token thì mọi lệnh gọi máy chủ đều bị chối — cho vào là cho vào một màn chết, bấm
   gì cũng báo lỗi và không ai hiểu vì sao. Bày cổng PIN là câu trả lời thật thà. */
const r6 = chay({ nho: NHO_CU, token: '' });
teq('không còn token thì thôi hỏi máy chủ', 0, r6.log.hoiMayChu);
teq('🔴 và KHÔNG cho vào bằng bản nhớ suông', 0, r6.log.veLai);
teq('mà bày cổng PIN',                        1, r6.log.moCong);

/* Không token, không bản nhớ -> cổng PIN. */
const r7 = chay({ nho: null, token: '' });
teq('không token, không bản nhớ -> cổng PIN', 1, r7.log.moCong);

/* ══════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.error('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.error('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: đổi vai ăn ngay, không phải đóng trình duyệt.');
