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
  (BANG.match(/tab:'([a-z]+)'/g) || []).every(function (x) { return ['don', 'duyet', 'qt', 'qtxong', 'xuat'].indexOf(x.slice(5, -1)) >= 0; }),
  BANG.match(/tab:'([a-z]+)'/g));

/* ═══ 1b. 🔴 TRÊN ĐIỆN THOẠI LÀ MỘT HÀNG CUỘN NGANG ════════════════════════════
 * Anh Thắng 22/09/2026 gửi ảnh màn iPhone: *"Với luồng duyệt"*. Tám bước × `flex-wrap:wrap`
 * trên màn 390px = BỐN HÀNG, chiếm gần nửa màn trước khi thấy đơn nào. Và mũi tên `→` giữa
 * hai thẻ rơi xuống ĐẦU hàng sau mỗi lần xuống dòng — ba mũi tên mồ côi trỏ vào khoảng không.
 *
 * 🔴 PHÉP QUAN TRỌNG NHẤT Ở ĐÂY LÀ "LUẬT KHÔNG ĐƯỢC VIẾT THẲNG VÀO THẺ". Một `style=` trên thẻ
 *    có mức riêng cao hơn MỌI luật trong tệp CSS, kể cả luật trong `@media` — nên chỉ cần một
 *    chữ `flex-wrap` sót lại trên thẻ là màn hẹp vẫn xuống bốn hàng, mà media query vẫn nằm đó
 *    trông như đang chạy. CÙNG MỘT HỌ LỖI với vụ `.grid-nhap` vá cùng ngày.
 */
{
  const CSS = fs.readFileSync('wordpress/vhcp-chi-phi/assets/css/vhcp.css', 'utf8')
    .replace(/\/\*[\s\S]*?\*\//g, ' ');
  const HTML_SACH = HTML.replace(/<!--[\s\S]*?-->/g, ' ');

  t('🔴 dải mang lớp `luongBar`, và luật bố cục nằm ở tệp CSS',
    /id="luongBar" class="luongBar"/.test(HTML_SACH) && /\.luongBar\{/.test(CSS), '');
  /* 🔴 KHÔNG còn luật bố cục viết thẳng trên thẻ dải — có là media query không với tới. */
  t('🔴 thẻ dải KHÔNG còn `style=` bố cục nào',
    !/id="luongBar"[^>]*style=/.test(HTML_SACH), (HTML_SACH.match(/<div id="luongBar"[^>]*>/) || [''])[0]);

  const ve = bocHam('veThanhLuong');
  /* Thẻ bước: chỉ thứ ĐỔI THEO DỮ LIỆU mới viết thẳng (viền · nền · mờ · con trỏ). */
  ['flex:', 'min-width:', 'padding:', 'flex-direction:'].forEach(function (x) {
    t('🔴 thẻ bước KHÔNG viết thẳng `' + x + '` (media query sẽ không với tới)',
      ve.indexOf("'" + x) < 0 && ve.indexOf(';' + x) < 0, x);
  });
  t('   nhưng viền/nền/mờ thì VẪN viết thẳng — chúng đổi theo dữ liệu, CSS không biết',
    /border:1px solid '\+vien/.test(ve) && /background:'\+nen/.test(ve), '');
  t('🔴 và mũi tên cũng mang lớp riêng để thu nhỏ được',
    /class="luongMuiTen"/.test(ve) && /\.luongMuiTen\{/.test(CSS), '');

  /* Luật màn hẹp: một hàng, cuộn ngang.
     🔴 BỐC ĐÚNG KHỐI @media CHỨA `.luongBar`, đừng lấy khối 560px ĐẦU TIÊN gặp. Tệp có mấy
        khối cùng bề ngang ấy (lưới chung, lưới form nhập…), và lấy nhầm là ba phép dưới đây đỏ
        oan trong khi mã hoàn toàn đúng — cắn ngay lượt viết này. Quét ngoặc cân để cắt đúng
        thân khối, chứ không cắt theo thụt lề. */
  function khoiHep(css, sel) {
    const moc = '@media(max-width:560px){';
    let i = 0;
    while ((i = css.indexOf(moc, i)) >= 0) {
      let j = i + moc.length, d = 1;
      while (j < css.length && d > 0) {
        if ('{' === css[j]) { d++; } else if ('}' === css[j]) { d--; }
        j++;
      }
      const than = css.slice(i + moc.length, j - 1);
      if (than.indexOf(sel) >= 0) { return than; }
      i = j;
    }
    return '';
  }
  const hep = khoiHep(CSS, '.luongBar');
  t('bốc được khối @media của dải luồng', hep.length > 40, hep.slice(0, 200));
  t('🔴 màn hẹp: dải thành MỘT HÀNG (không xuống dòng)', /\.luongBar\{[^}]*flex-wrap:nowrap/.test(hep), hep.slice(0, 400));
  t('🔴 và CUỘN NGANG được — không thì bốn bước cuối biến mất khỏi màn',
    /\.luongBar\{[^}]*overflow-x:auto/.test(hep), hep.slice(0, 400));
  t('   thẻ bước thôi giãn, thu lại cho vừa', /\.luongThe\{[^}]*flex:0 0 auto/.test(hep), hep.slice(0, 400));
  /* ⚠️ KHÔNG BỎ BƯỚC NÀO cho gọn — dải này là BẢN ĐỒ, nhân viên phải biết đơn đi đâu tiếp. */
  t('⚠️ không có luật nào GIẤU bước trên màn hẹp',
    !/\.luongThe\[[^\]]*\]\{[^}]*display:none/.test(hep) && !/\.luongThe\{[^}]*display:none/.test(hep), hep.slice(0, 400));
}

/* ═══ 2. CHẠY THẬT ═════════════════════════════════════════════════════════════ */
const NEN = [bocDong('KHOI_LUONG_CHI'), bocKhoi('LUONG_KVC').replace(/\n  \};$/, ''), '',
  HTML.slice(HTML.indexOf('  var LUONG_KVC='), HTML.indexOf('};', HTML.indexOf('  var LUONG_CHI=')) + 2),
  /* `LUONG_TT` + `_luongDon` + `_luongBuocDs` thêm ở 1.287.0: luồng nay là thuộc tính của
     TỪNG ĐƠN, nên dải vẽ HỢP của các luồng đang có mặt chứ không riêng luồng của khối. */
  bocKhoi('LUONG_TT'),
  BANG, bocHam('_luongKhoi'), bocHam('_luongDon'), bocHam('_tenTT'), bocHam('_hopKhoi'), bocHam('_khoiCua'),
  bocHam('_luongDem'), bocHam('_luongBuocDs'), bocHam('veThanhLuong'), bocHam('_luongKeoToiChoTac')].join('\n');

function ve(khoi, tabDuoc, dons) {
  const NK = {};
  const moi = {
    KHOI_DANG: khoi, BOOT: { dons: dons || [] },
    esc: (v) => String(v == null ? '' : v),
    _tabDuoc: (p) => tabDuoc.indexOf(p) >= 0,
    /* ⚠️ Ô GIẢ PHẢI CÓ ĐỦ THỨ `_luongKeoToiChoTac()` SỜ TỚI. Thiếu `scrollWidth`/`clientWidth`
       thì phép so ra `undefined <= undefined` = false, và hàm đi tiếp vào `querySelector` —
       tức bệ đỡ lặng lẽ chạy một nhánh mà trên trình duyệt không bao giờ chạy. Cho hai số bằng
       nhau = "dải không cuộn được", đúng cảnh màn rộng. */
    el: (id) => (NK[id] = NK[id] || { style: {}, innerHTML: '', scrollWidth: 0, clientWidth: 0,
      scrollLeft: 0, querySelector: () => null })
  };
  new Function('moi', `with(moi){ ${NEN}\n return veThanhLuong; }`)(moi)();
  return NK.luongBar.innerHTML;
}
/* ═══ 2b. 🔴 KÉO DẢI TỚI BƯỚC ĐANG CÓ ĐƠN — CHẠY THẬT ══════════════════════════
 * Một hàng cuộn ngang thì bốn bước cuối nằm ngoài tầm mắt. Người mở màn muốn biết *chỗ nào
 * đang tắc*, nên dải phải tự kéo tới đó — không thì cuộn ngang là đổi một cái rối lấy một cái
 * khuất.
 *
 * ⚠️ CHỈ KHI DẢI THẬT SỰ CUỘN ĐƯỢC. Trên màn rộng dải xuống dòng chứ không cuộn; đặt
 *    `scrollLeft` ở đó là một lệnh vô nghĩa hôm nay và một lệnh SAI ngày bố cục đổi.
 */
{
  const keo = bocHam('_luongKeoToiChoTac');
  t('bốc được `_luongKeoToiChoTac`', keo.length > 100, keo.length);
  /* 🔴 VÀ LƯỢT VẼ PHẢI GỌI NÓ. Phá thử 22/09/2026: gỡ đúng một dòng gọi thì hàm vẫn đúng, mọi
     phép dưới đây vẫn xanh, mà trên máy dải không bao giờ kéo tới chỗ tắc — bước đang tắc nằm
     khuất bên phải và cuộn ngang thành đổi một cái rối lấy một cái khuất. */
  t('🔴 `veThanhLuong()` có GỌI nó sau khi vẽ xong',
    /_luongKeoToiChoTac\(o\);/.test(bocHam('veThanhLuong')), '');
  const chay = function (o) {
    const c = { Math: Math };
    require('vm').createContext(c);
    require('vm').runInContext(keo + '\n_luongKeoToiChoTac(O);', Object.assign(c, { O: o }));
    return o.scrollLeft;
  };
  /* Màn HẸP: dải rộng hơn khung -> kéo tới bước có đơn, chừa một chút bên trái. */
  const buoc = { offsetLeft: 520 };
  teq('🔴 dải cuộn được + có bước đang tắc → kéo tới đó, chừa lề trái để biết còn bước phía trước',
    504, chay({ scrollWidth: 900, clientWidth: 390, scrollLeft: 0, querySelector: () => buoc }));
  /* 🔴 Bước đầu dải (offsetLeft nhỏ) không được kéo ra số ÂM. */
  teq('🔴 bước đang tắc nằm ngay đầu dải → không kéo ra số âm',
    0, chay({ scrollWidth: 900, clientWidth: 390, scrollLeft: 0, querySelector: () => ({ offsetLeft: 4 }) }));
  /* Màn RỘNG: dải không cuộn được -> KHÔNG đụng vào. */
  teq('🔴 màn rộng (dải không cuộn được) → KHÔNG đụng vào vị trí cuộn',
    0, chay({ scrollWidth: 390, clientWidth: 390, scrollLeft: 0, querySelector: () => buoc }));
  /* Không bước nào có đơn -> để yên ở đầu dải, kéo đi đâu cũng là đoán. */
  teq('⚠️ không bước nào có đơn → để yên ở đầu dải',
    0, chay({ scrollWidth: 900, clientWidth: 390, scrollLeft: 0, querySelector: () => null }));
  /* ⚠️ Ô chưa dựng xong (null) không được làm chết cả lượt vẽ. */
  t('⚠️ ô chưa có thì im lặng bỏ qua, không ném lỗi',
    (function () { try { chay0(); return true; } catch (e) { return false; } })(), '');
  function chay0() {
    const c = {};
    require('vm').createContext(c);
    require('vm').runInContext(keo + '\n_luongKeoToiChoTac(null);', c);
  }
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
/* 🔴 BƯỚC ĐANG CÓ ĐƠN PHẢI MANG DẤU ĐỂ KÉO TỚI. Phá thử 22/09/2026: gỡ `data-co-don` thì
   `_luongKeoToiChoTac()` không tìm thấy gì và lặng lẽ bỏ qua — dải không bao giờ kéo, mà không
   phép nào đỏ vì bệ đỡ tự đưa ô vào. Đếm trên HTML THẬT mới bắt được.
   Bộ gieo cho MTĐ có đơn ở ba bước: Nháp · Chờ quyết toán · Đã thanh toán. */
teq('🔴 mỗi bước ĐANG CÓ ĐƠN mang một dấu để kéo tới', 3, (hMtd.match(/data-co-don="1"/g) || []).length);
t('   và bước rỗng thì KHÔNG mang dấu ấy',
  (hMtd.match(/data-co-don="1"/g) || []).length < (hMtd.match(/class="luongThe"/g) || []).length, hMtd.slice(0, 200));

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
