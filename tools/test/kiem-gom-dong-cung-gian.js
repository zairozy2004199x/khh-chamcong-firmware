/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CÙNG GIAN THÌ NẰM SÁT NHAU
 *
 * Anh Thắng 11/09/2026, nhìn bảng dòng chi của một đơn cơ sở có FUNFEST SC VIVO ở dòng 2 và
 * dòng 4, cách nhau một gian khác: *"Sắp cũng gian thì sát nhau"*.
 *
 * =============================================================================================
 * 🔴 GOM THEO THỨ TỰ GIAN XUẤT HIỆN LẦN ĐẦU, KHÔNG SẮP A→Z. Sắp theo bảng chữ cái là xáo lại
 *    toàn bộ bảng người ta vừa nhập; gom theo lần xuất hiện đầu thì bảng giữ nguyên dáng, chỉ
 *    dòng lạc đàn được kéo về cụm của nó.
 *
 * 🔴 KHÔNG ĐƯỢC MẤT DÒNG, KHÔNG ĐƯỢC NHÂN ĐÔI DÒNG. Một phép sắp xếp làm rơi một dòng chi là
 *    mất tiền khỏi bảng mà tổng vẫn tính đủ — sai lệch không nhìn ra được.
 *
 * ⚠️ CHỈ GOM TRONG CÙNG MỘT CẤP: mục con phải nằm dưới cha của nó.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY.
 *
 * Chạy: node tools/test/kiem-gom-dong-cung-gian.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const i = HTML.indexOf('    function _daGomGian(ds){');
t('bốc được _daGomGian()', i >= 0);
const src = i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n    }', i) + 6);
const gom = new Function(src + '\nreturn _daGomGian;')();
const ten = ds => ds.map(x => x.nd);

/* Đúng bảng trong ảnh anh Thắng gửi: FUNFEST SC VIVO nằm ở dòng 2 và dòng 4. */
const BANG = [
  { nd: 'Cáp màn hình',  gian: 'ADV SỰ KIỆN TÂN PHÚ' },
  { nd: 'Mua bàn phím',  gian: 'FUNFEST SC VIVO' },
  { nd: 'Chuột máy tính', gian: 'NHÀ MA BÌNH DƯƠNG' },
  { nd: 'Ram PC',        gian: 'FUNFEST SC VIVO' },
];

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. CA CHÍNH — hai dòng FUNFEST về sát nhau
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
teq('🔴 hai dòng cùng gian về sát nhau',
  ['Cáp màn hình', 'Mua bàn phím', 'Ram PC', 'Chuột máy tính'], ten(gom(BANG)));

/* 🔴 GIỮ DÁNG BẢNG: gian nào xuất hiện trước thì cụm của nó vẫn đứng trước. ADV vào trước
   FUNFEST, FUNFEST trước NHÀ MA — thứ tự cụm phải đúng thế, không phải A→Z. */
const cum = gom(BANG).map(x => x.gian).filter((g, k, a) => g !== a[k - 1]);
teq('🔴 cụm giữ thứ tự gian xuất hiện lần đầu',
  ['ADV SỰ KIỆN TÂN PHÚ', 'FUNFEST SC VIVO', 'NHÀ MA BÌNH DƯƠNG'], cum);
/* 🔴 Bảng trên tình cờ có thứ tự xuất hiện TRÙNG với A→Z, nên tự nó không phân biệt được hai
   cách làm. Cần một bảng mà hai cách cho kết quả khác nhau: gian "Z" vào trước gian "A". */
const NGUOC = [
  { nd: 'z1', gian: 'Z GIAN' }, { nd: 'a1', gian: 'A GIAN' }, { nd: 'z2', gian: 'Z GIAN' },
];
teq('🔴 gian vào sau dù tên đứng trước A→Z vẫn nằm sau', ['z1', 'z2', 'a1'], ten(gom(NGUOC)));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 KHÔNG MẤT DÒNG, KHÔNG NHÂN ĐÔI DÒNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
teq('số dòng ra đúng bằng số dòng vào', BANG.length, gom(BANG).length);
/* So TẬP HỢP, không so mảng đã sort: `Array.sort()` mặc định so theo mã ký tự nên tiếng Việt
   có dấu ra thứ tự lạ ("Chuột" trước "Cáp") — bài kiểm sẽ đỏ vì chính phép so, không phải vì
   mã hỏng. */
BANG.forEach(function (d) {
  t('   còn nguyên dòng "' + d.nd + '"', ten(gom(BANG)).indexOf(d.nd) >= 0, ten(gom(BANG)));
});
/* Cùng nội dung ở hai gian khác nhau là hai dòng khác nhau — gộp nhầm là mất tiền. */
const TRUNG = [
  { nd: 'Cáp', gian: 'A' }, { nd: 'Cáp', gian: 'B' }, { nd: 'Cáp', gian: 'A' },
];
teq('🔴 trùng nội dung khác gian vẫn giữ đủ ba dòng', 3, gom(TRUNG).length);
teq('   và gom đúng theo gian', ['A', 'A', 'B'], gom(TRUNG).map(x => x.gian));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. TRONG CÙNG MỘT GIAN THÌ GIỮ NGUYÊN THỨ TỰ CŨ
 *
 * ⚠️ Phép sắp không ổn định là mỗi lần mở đơn thấy một thứ tự khác — người đối chiếu sổ không
 *    tin nổi bảng nữa.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const CUNG = [
  { nd: 'một', gian: 'G' }, { nd: 'hai', gian: 'G' }, { nd: 'ba', gian: 'G' },
];
teq('một gian duy nhất → y nguyên thứ tự nhập', ['một', 'hai', 'ba'], ten(gom(CUNG)));
teq('gom hai lần liên tiếp cho cùng kết quả', ten(gom(BANG)), ten(gom(gom(BANG))));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. CA BIÊN — gian trống, thiếu ô gian, thừa khoảng trắng
 *
 * 🔴 Dự án Setup/Tháo dỡ KHÔNG hỏi ô Gian (gian chính là tên dự án), nên mọi dòng có gian rỗng.
 *    Gom mà làm xáo bảng của họ là hỏng một màn đang chạy để chữa một màn khác.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const RONG = [{ nd: 'a' }, { nd: 'b', gian: '' }, { nd: 'c', gian: null }];
teq('🔴 dòng không có gian: giữ nguyên thứ tự', ['a', 'b', 'c'], ten(gom(RONG)));
teq('thừa khoảng trắng vẫn tính là cùng gian',
  ['x', 'z', 'y'], ten(gom([{ nd: 'x', gian: 'G' }, { nd: 'y', gian: 'K' }, { nd: 'z', gian: '  G  ' }])));
teq('mảng rỗng trả mảng rỗng', [], gom([]));
teq('không truyền gì cũng không nổ', [], gom());

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. 🔴 ÁP ĐỦ BA CẤP — quét tĩnh
 *
 * Hạng mục lớn · phát sinh · mục con trong từng cha. Quên một cấp là cấp ấy vẫn cài răng lược.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 gom hạng mục lớn',  /parents=_daGomGian\(parents\)/.test(HTML));
t('🔴 gom dòng phát sinh', /phatSinh=_daGomGian\(phatSinh\)/.test(HTML));
t('🔴 gom mục con trong từng cha', /childrenBy\[c\]=_daGomGian\(childrenBy\[c\]\)/.test(HTML));

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — dòng cùng gian nằm sát nhau, bảng giữ nguyên dáng');
