/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô CHỌN "CƠ SỞ" BÊN ĐƠN TUẦN CŨNG LỌC THEO KHỐI.
 *
 * Anh Thắng 22/09/2026: *"đang để nhân viên máy tự động vẫn thấy cơ sở khu vui chơi"*, kèm ảnh
 * ô **Cơ sở** ở khối "Tạm ứng xin" xổ ra TÀU TÂN PHÚ · TÀU BÌNH TÂN · FUNFEST SC VIVO… lẫn với
 * AEON MALL · BỆNH VIỆN 175…
 *
 * =============================================================================================
 * 🔴 LƯỢT 1.242.0 MỚI BỊT MỘT NỬA
 * =============================================================================================
 * Lần ấy em lọc hộp **Gian** (`dl_coso_da` / `dl_gian_da`) bên tab Kỹ thuật và tưởng xong. Nhưng
 * đơn TUẦN có hai ô chọn cơ sở riêng — `#tuCoso` (khối Tạm ứng) và `#f_coso` (dòng chi) — dựng ở
 * một chỗ khác, chưa ai lọc. Cùng một câu hỏi mà hai đường trả lời khác nhau: đó là dấu hiệu phép
 * lọc đặt sai chỗ, không phải thiếu một dòng mã.
 *
 * 🔴 CHỌN NHẦM CƠ SỞ LÀ TIỀN RƠI VÀO SỔ CỦA KHỐI KHÁC, hỏng theo kiểu khó truy nhất: đơn vẫn lập
 *    được, số vẫn vào, chỉ là vào nhầm chỗ — tới lúc đối chiếu cuối kỳ mới lộ.
 *
 * ⚠️ DÙNG LẠI `_gianHopKhoi()`, KHÔNG VIẾT PHÉP THỨ HAI: hai bản chép tay là hai bản trôi lệch,
 *    và lệch ở đây nghĩa là hộp Gian lọc một kiểu, ô Cơ sở lọc kiểu khác, trên cùng một đơn.
 *
 * Chạy: node tools/test/kiem-o-coso-theo-khoi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) { const i = HTML.indexOf('  function ' + ten + '('); if (i < 0) return ''; const j = HTML.indexOf('\n  }', i) + 4; return j > i ? HTML.slice(i, j) : ''; }
function bocDong(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); }
function bocMang(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); }

t('⚠️ bốc được `_veLaiOCoSo`', bocHam('_veLaiOCoSo').length > 400);

/* ═══ 1. DÙNG CHUNG MỘT PHÉP VỚI HỘP GIAN ══════════════════════════════════════ */
t('🔴 ô cơ sở lọc bằng `_gianHopKhoi` — cùng phép với hộp Gian',
  /\.filter\(_gianHopKhoi\)/.test(bocHam('_veLaiOCoSo')), bocHam('_veLaiOCoSo'));
t('🔴 ô tách dòng sang cơ sở khác cũng lọc',
  /\(BOOT\.coso\|\|\[\]\)\.filter\(_gianHopKhoi\)/.test(HTML));

/* ═══ 2. CHẠY THẬT ═════════════════════════════════════════════════════════════ */
const NEN = [bocMang('KHOI_DS'), bocDong('KHOI_DV_DUP'), bocHam('_khoiDvBang'), bocHam('_khoiCuaDv'),
  bocHam('_khoiCuaGian'), bocHam('_gianHopKhoi'), bocHam('_veLaiOCoSo')].join('\n');
t('⚠️ nền chạy thử dựng được', NEN.replace(/\s/g, '').length > 500, NEN.length);

const COSO = ['TÀU TÂN PHÚ', 'FUNFEST SC VIVO', 'AEON MALL BÌNH DƯƠNG', 'BỆNH VIỆN 175', 'GIAN CHƯA KHAI'];
const BOOT = {
  khoiTheoDv: { kvc: ['KVC'], mtd: ['MTĐ', 'MTD', 'POSH'], vp: ['VP'] },
  cosoDv: { 'tàu tân phú': 'KVC', 'funfest sc vivo': 'KVC',
            'aeon mall bình dương': 'POSH', 'bệnh viện 175': 'POSH', 'gian chưa khai': '' }
};
function chay(khoi, goc, uuTien) {
  const O = {};
  const win = { _COSO_OPTS_GOC: (goc || COSO).slice() };
  const moi = {
    BOOT: BOOT, KHOI_DANG: khoi, window: win,
    esc: (v) => String(v == null ? '' : v),
    opts: (a, ph) => '<option value="">' + (ph || '—') + '</option>' + a.map((v) => '<option value="' + v + '">' + v + '</option>').join(''),
    el: (id) => (O[id] = O[id] || { innerHTML: '', value: '' }),
  };
  new Function('moi', `with(moi){ ${NEN}\n return _veLaiOCoSo; }`)(moi)(uuTien);
  const doc = (id) => (O[id].innerHTML.match(/<option value="([^"]*)"/g) || [])
    .map((x) => x.slice(15, -1)).filter(Boolean);
  return { tu: doc('tuCoso'), f: doc('f_coso'), fVal: O.f_coso.value, goc: win._COSO_OPTS_GOC };
}

const mtd = chay('mtd');
teq('🔴 đứng ở MTĐ: KHÔNG còn cơ sở khu vui chơi',
  ['AEON MALL BÌNH DƯƠNG', 'BỆNH VIỆN 175', 'GIAN CHƯA KHAI'], mtd.tu);
teq('🔴 và ô dòng chi cũng vậy — hai ô cùng một danh sách', mtd.tu, mtd.f);
const kvc = chay('kvc');
teq('🔴 đứng ở KVC thì ngược lại',
  ['TÀU TÂN PHÚ', 'FUNFEST SC VIVO', 'GIAN CHƯA KHAI'], kvc.tu);
/* ⚠️ Cơ sở chưa khai khối vẫn hiện ở MỌI khối — cùng luật hỏng-an-toàn với `_gianHopKhoi()`:
   danh mục dựng từ sổ cũ, ẩn chúng là người nhập không chọn được một cơ sở có thật. */
t('⚠️ cơ sở chưa khai khối hiện ở cả hai',
  mtd.tu.indexOf('GIAN CHƯA KHAI') >= 0 && kvc.tu.indexOf('GIAN CHƯA KHAI') >= 0);
teq('   khối chưa có cơ sở nào → chỉ còn cái chưa khai', ['GIAN CHƯA KHAI'], chay('vp').tu);

/* ═══ 3. 🔴 ĐỔI KHỐI NHIỀU LƯỢT — DANH SÁCH KHÔNG ĐƯỢC TEO DẦN ═════════════════
 * Em gán nhầm đúng chỗ này một lượt (22/09/2026): đặt tên biến là `_GOC` nhưng gán danh sách ĐÃ
 * lọc. Lọc chồng lên bản đã lọc thì mỗi lượt đổi khối danh sách teo thêm một ít, và sau vài lượt
 * là ô trắng — hỏng DẦN, nên càng khó thấy. Phép này canh đúng cái đó. */
let g = COSO.slice();
['mtd', 'kvc', 'mtd', 'vp', 'kvc'].forEach(function (k) { g = chay(k, g).goc; });
teq('🔴 sau năm lượt đổi khối, bản GỐC vẫn đủ 5 cơ sở', 5, g.length);
teq('   và lượt cuối vẫn xổ đúng cơ sở của KVC',
  ['TÀU TÂN PHÚ', 'FUNFEST SC VIVO', 'GIAN CHƯA KHAI'], chay('kvc', g).tu);
t('🔴 hàm lọc từ bản GỐC, không lọc chồng lên kết quả cũ',
  /_COSO_OPTS_GOC\|\|\[\]\)\.filter\(_gianHopKhoi\)/.test(bocHam('_veLaiOCoSo')), bocHam('_veLaiOCoSo'));
/* 🔴 VÀ BẢN CHỤP PHẢI LÀ BẢN CHƯA LỌC. Phép chạy-thật ngay trên KHÔNG canh được điều này: bệ đỡ
   tự bơm `_COSO_OPTS_GOC`, nên nó không đi qua dòng chụp trong `boot()`. Đã thử phá đúng dòng ấy
   (gán bản đã lọc) và bài vẫn xanh — nên phải có thêm một phép đọc thẳng dòng đó.
   Đây là ranh giới thật của phép chạy-thật: nó canh HÀM, không canh chỗ GỌI hàm. */
{
  const i = HTML.indexOf('window._COSO_OPTS_GOC=');
  const dong = i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i));
  t('⚠️ bốc được dòng chụp bản gốc', dong.length > 20, dong);
  t('🔴 chụp bản CHƯA lọc khối (lọc rồi mới chụp là danh sách teo dần)',
    /_COSO_OPTS_GOC=\(cosoOpts\|\|\[\]\)\.slice\(\);/.test(dong) && dong.indexOf('_gianHopKhoi') < 0, dong);
}

/* ═══ 4. CHỌN SẴN & GIỮ LỰA CHỌN ═══════════════════════════════════════════════ */
teq('⚠️ chỉ một cơ sở hợp khối → chọn sẵn', 'GIAN CHƯA KHAI', chay('vp').fVal);
teq('⚠️ nhân viên có cơ sở riêng → chọn sẵn cơ sở ấy', 'BỆNH VIỆN 175', chay('mtd', null, 'BỆNH VIỆN 175').fVal);
/* Cơ sở ưu tiên KHÔNG thuộc khối đang đứng thì đừng chọn bừa — chọn vào là đơn mang cơ sở của
   khối khác ngay từ lúc mở. */
teq('🔴 cơ sở ưu tiên không hợp khối → KHÔNG chọn bừa', '', chay('mtd', null, 'TÀU TÂN PHÚ').fVal);

/* ═══ 5. ĐỔI KHỐI PHẢI DỰNG LẠI Ô ══════════════════════════════════════════════
 * Hai ô dựng một lần trong `boot()`, mà `boot()` không chạy lại khi đổi khối — không gọi thì
 * người ta bấm sang Máy tự động xong mở ô Cơ sở ra vẫn thấy gian khu vui chơi. */
t('🔴 `doiKhoi` dựng lại hai ô cơ sở', /_veLaiOCoSo\(\)/.test(bocHam('doiKhoi')), bocHam('doiKhoi').slice(-400));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hai ô Cơ sở bên đơn tuần lọc theo khối, đổi khối nhiều lượt không teo danh sách.');
