/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * DẢI LUỒNG LÀM VIỆC — mỗi bước một thẻ bấm được, dẫn thẳng tới màn làm bước ấy.
 *
 * Anh Thắng 21/09/2026 gửi ảnh một dải như thế: *"Tạo mẫu luồng làm việc này vào"*.
 *
 * =============================================================================================
 * 🔴 NÓ KHÔNG PHẢI TRANG TRÍ, NÓ LÀ BẢN ĐỒ
 * =============================================================================================
 * Một đơn đi qua sáu/bảy bước nằm ở BỐN tab khác nhau (Đơn chi phí · Duyệt tạm ứng · Quyết toán
 * · Xuất MISA). Người mới không đoán được bước nào ở tab nào, và hỏi nhau là cách duy nhất để
 * biết. Dải này trả lời bằng hình; bấm được thì khỏi phải nhớ.
 *
 * Ba chốt bài này canh:
 *   1. 🔴 VẼ THEO LUỒNG CỦA KHỐI ĐANG ĐỨNG. Máy tự động không có bước "Chờ cấp tạm ứng" — bày
 *      nó ra là mời người ta đi tìm một bước không tồn tại.
 *   2. ⚠️ BƯỚC KHÔNG CÓ QUYỀN THÌ MỜ, KHÔNG BỎ. Nhân viên vẫn cần biết đơn của mình đi đâu
 *      tiếp; bỏ hẳn là luồng trông như chỉ có hai bước, và họ tưởng đơn xong rồi.
 *   3. 🔴 ĐẾM ĐƠN ĐANG ĐỨNG Ở MỖI BƯỚC — con số mới là thứ nói "chỗ nào đang tắc". Một dải
 *      không số thì đẹp mà không dùng để làm gì.
 *
 * Chạy: node tools/test/kiem-dai-luong-lam-viec.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) { const i = HTML.indexOf('  function ' + ten + '('); if (i < 0) return ''; const j = HTML.indexOf('\n  }', i) + 4; return j > i ? HTML.slice(i, j) : ''; }
function bocDong(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); }
function bocKhoi(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  };', i) + 4); }

t('⚠️ bốc được `veThanhLuong`', bocHam('veThanhLuong').length > 400);
t('🔴 trang có chỗ cho dải', /id="luongWrap"/.test(HTML) && /id="luongBar"/.test(HTML));
t('   và có dòng nhắc bấm được', /bấm vào bước để đi tới/.test(HTML));

/* ═══ 1. BẢNG GHÉP BƯỚC ↔ TAB — MỘT CHỖ KHAI ═══════════════════════════════════ */
const BANG = HTML.slice(HTML.indexOf('  var LUONG_DIEN='), HTML.indexOf('\n  };', HTML.indexOf('  var LUONG_DIEN=')) + 4);
t('⚠️ bốc được bảng ghép bước ↔ tab', BANG.length > 300, BANG.length);
/* 🔴 KHOÁ THEO CHUỖI LƯU TRONG SỔ, không theo chữ hiện trên màn: chữ đổi theo khối, khoá theo
   nó là bảng này trượt sạch bên Máy tự động. */
['Nháp', 'Chờ duyệt tạm ứng', 'Chờ cấp tạm ứng', 'Đã cấp tạm ứng', 'Chờ quyết toán',
 'Đã quyết toán', 'Đã thanh toán', 'Đã xuất MISA'].forEach(function (st) {
  t('🔴 bảng khai bước "' + st + '" (khoá theo chuỗi trong sổ)', BANG.indexOf("'" + st + "'") >= 0);
});
t('⚠️ mỗi bước chỉ vào một tab CÓ THẬT',
  (BANG.match(/tab:'([a-z]+)'/g) || []).every(function (x) { return ['don', 'duyet', 'qt', 'xuat'].indexOf(x.slice(5, -1)) >= 0; }),
  BANG.match(/tab:'([a-z]+)'/g));

/* ═══ 2. CHẠY THẬT ═════════════════════════════════════════════════════════════ */
const NEN = [bocDong('KHOI_LUONG_CHI'), bocKhoi('LUONG_KVC').replace(/\n  \};$/, ''), '',
  HTML.slice(HTML.indexOf('  var LUONG_KVC='), HTML.indexOf('};', HTML.indexOf('  var LUONG_CHI=')) + 2),
  BANG, bocHam('_luongKhoi'), bocHam('_tenTT'), bocHam('_hopKhoi'), bocHam('_khoiCua'),
  bocHam('_luongDem'), bocHam('veThanhLuong')].join('\n');

function ve(khoi, tabDuoc, dons) {
  const NK = {};
  const moi = {
    KHOI_DANG: khoi, BOOT: { dons: dons || [] },
    esc: (v) => String(v == null ? '' : v),
    _tabDuoc: (p) => tabDuoc.indexOf(p) >= 0,
    el: (id) => (NK[id] = NK[id] || { style: {}, innerHTML: '' })
  };
  new Function('moi', `with(moi){ ${NEN}\n return veThanhLuong; }`)(moi)();
  return NK.luongBar.innerHTML;
}
const DONS = [
  { trangThai: 'Nháp', khoi: 'mtd' },
  { trangThai: 'Chờ quyết toán', khoi: 'mtd' },
  { trangThai: 'Chờ quyết toán', khoi: 'mtd' },
  { trangThai: 'Đã thanh toán', khoi: 'mtd' },
  { trangThai: 'Chờ cấp tạm ứng', khoi: 'kvc' }
];
const MOI = ['don', 'duyet', 'qt', 'xuat'];

/* 🔴 Máy tự động KHÔNG có bước "Chờ cấp tạm ứng". */
const hMtd = ve('mtd', MOI, DONS);
t('🔴 dải của MTĐ KHÔNG có bước "Chờ cấp tạm ứng"', hMtd.indexOf('Chờ cấp tạm ứng') < 0, hMtd.slice(0, 400));
t('🔴 và CÓ bước "Đã thanh toán"', hMtd.indexOf('Đã thanh toán') >= 0);
t('   dùng đúng chữ của khối ("Chờ duyệt chi", không phải "Chờ duyệt tạm ứng")',
  hMtd.indexOf('Chờ duyệt chi') >= 0 && hMtd.indexOf('Chờ duyệt tạm ứng') < 0);
const hKvc = ve('kvc', MOI, DONS);
t('🔴 dải của KVC VẪN có "Chờ cấp tạm ứng"', hKvc.indexOf('Chờ cấp tạm ứng') >= 0);
t('   và KHÔNG có "Đã thanh toán"', hKvc.indexOf('Đã thanh toán') < 0);
teq('   KVC bảy thẻ', 7, (hKvc.match(/<button/g) || []).length);
teq('   MTĐ cũng bảy thẻ', 7, (hMtd.match(/<button/g) || []).length);
teq('⚠️ mũi tên nối đúng sáu chỗ (n−1)', 6, (hMtd.match(/→/g) || []).length);

/* 🔴 ĐẾM ĐƠN — chỉ đếm đơn CỦA KHỐI ĐANG ĐỨNG. */
t('🔴 "Chờ duyệt quyết toán" đếm 2 đơn', /Chờ duyệt quyết toán[\s\S]{0,180}?2 đơn/.test(hMtd), hMtd);
t('🔴 "Đã thanh toán" đếm 1 đơn', /Đã thanh toán[\s\S]{0,180}?1 đơn/.test(hMtd));
/* ⚠️ Đơn KVC ở "Chờ cấp tạm ứng" KHÔNG được đếm vào dải của MTĐ — đếm chung là con số nói dối
   về chỗ đang tắc, và đó là lý do duy nhất dải này có số. */
/* ⚠️ CỘNG LẠI PHẢI BẰNG SỐ ĐƠN CỦA CHÍNH KHỐI ẤY — phép canh thẳng vào ý "không đếm lẫn", chứ
   không đếm số huy hiệu (đếm huy hiệu là em đã tự sai một lần: quên mất đơn Nháp). */
const tongMtd = (hMtd.match(/>(\d+) đơn</g) || []).reduce(function (a, x) { return a + Number(x.match(/\d+/)[0]); }, 0);
teq('⚠️ cộng lại đúng bằng 4 đơn MTĐ — không lẫn đơn KVC', 4, tongMtd);
const tongKvc = (hKvc.match(/>(\d+) đơn</g) || []).reduce(function (a, x) { return a + Number(x.match(/\d+/)[0]); }, 0);
teq('   và dải KVC chỉ đếm 1 đơn KVC', 1, tongKvc);
t('   bước không có đơn thì KHÔNG bày số 0', hMtd.indexOf('0 đơn') < 0);

/* ⚠️ MỜ CHỨ KHÔNG BỎ. */
const hNv = ve('mtd', ['don'], DONS);
teq('⚠️ nhân viên (chỉ có tab Đơn) vẫn thấy ĐỦ bảy bước', 7, (hNv.match(/<button/g) || []).length);
t('🔴 nhưng bước không có quyền thì bị khoá', /disabled/.test(hNv));
t('   và mờ đi', /opacity:\.5/.test(hNv));
t('   bước có quyền thì bấm được', /onclick="showPage\('don'\)"/.test(hNv));
t('⚠️ và nói rõ vì sao không bấm được', /không có quyền vào bước này/.test(hNv));

/* ═══ 3. NỐI VÀO CHỖ VẼ ════════════════════════════════════════════════════════ */
/* Dải nằm trong khối thanh KHỐI, nên nó phải được vẽ lại mỗi lượt `veThanhKhoi()` — đổi khối
   là đổi cả luồng, không vẽ lại là dải đứng ở luồng của khối cũ. */
t('🔴 `veThanhKhoi` vẽ lại dải', /veThanhLuong\(\)/.test(bocHam('veThanhKhoi')), bocHam('veThanhKhoi').slice(0, 200));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: dải luồng vẽ theo khối, đếm đúng đơn, bước không có quyền thì mờ chứ không mất.');
