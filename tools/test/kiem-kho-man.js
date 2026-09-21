/**
 * MÀN SỔ KHO: Ô TRỐNG PHẢI KHÁC Ô ĐÃ ĐẾM, VÀ ĐỌC ĐÚNG CÔNG THỨC COMBO.
 *
 * Phần máy chủ do `kiem-kho.php` khoá. Bài này chạy THẬT hai hàm của màn hình — chỗ dò chuỗi
 * trong mã không bắt được:
 *
 *   1. 🔴 `oLech`: chưa khai (null) phải hiện "—", không phải "0" xanh lét. Một ô chưa ai đếm
 *      mà trông như đã soát xong và khớp thì cả sổ đọc ra điều ngược lại với sự thật.
 *   2. 🔴 `docThanhPhan`: gõ "Nước suối x2, Kẹo cầu vồng x1" phải ra đúng công thức. Đọc sai
 *      là trừ nhầm kho HÀNG LOẠT mà không dòng nào sai, và không ai lần ra vì sao tồn lệch.
 *
 * Chạy: node tools/test/kiem-kho-man.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const TEP = path.join(__dirname, '..', '..', 'wordpress', 'khh-doanh-thu', 'assets', 'doanh-thu.js');
const src = fs.readFileSync(TEP, 'utf8');

let dat = 0;
const hong = [];
function t(ten, dk) { if (dk) { dat++; } else { hong.push(ten); } }

function boc(ten) {
  const i = src.indexOf('  function ' + ten + '(');
  if (i < 0) { console.error('KHÔNG tìm thấy ' + ten); process.exit(1); }
  const j = src.indexOf('\n  }\n', i);
  return src.slice(i, j + 4);
}

/* `oLech` nay có nhãn nên gọi `esc` — bơm luôn một bản `esc` tối giản vào hộp chạy. */
const F = new Function('nguyen', 'esc',
  boc('oLech') + boc('docThanhPhan') + boc('soKho') +
  '\nreturn { oLech: oLech, docThanhPhan: docThanhPhan, soKho: soKho };'
)((x) => String(x), (x) => String(x === null || x === undefined ? '' : x));

/* ── 1. ô lệch ────────────────────────────────────────────────────────────────────── */
t('🔴 chưa khai thì hiện "—", KHÔNG phải 0', /—/.test(F.oLech(null, 'Lệch kho')) && !/>0</.test(F.oLech(null, 'Lệch kho')));
t('chưa khai thì cũng không tô màu tốt/xấu', !/--tot|--xau/.test(F.oLech(null, 'Lệch kho')));
t('undefined cũng coi là chưa khai', /—/.test(F.oLech(undefined, 'Lệch kho')));
t('🔴 khai và khớp thì hiện 0 màu TỐT', />0</.test(F.oLech(0, 'Lệch kho')) && /--tot/.test(F.oLech(0, 'Lệch kho')));
t('lệch âm thì màu xấu và có dấu trừ', /--xau/.test(F.oLech(-5, 'Lệch kho')) && /-5/.test(F.oLech(-5, 'Lệch kho')));
t('lệch dương thì có dấu cộng', /\+5/.test(F.oLech(5, 'Lệch kho')) && /--xau/.test(F.oLech(5, 'Lệch kho')));

/* ── 2. soKho: 0 là số thật, null là trống ────────────────────────────────────────── */
t('🔴 soKho(0) ra "0", không ra rỗng', '0' === F.soKho(0));
t('soKho(null) ra rỗng', '' === F.soKho(null));
t('soKho(undefined) ra rỗng', '' === F.soKho(undefined));

/* ── 3. đọc công thức combo ───────────────────────────────────────────────────────── */
let c = F.docThanhPhan('Nước suối x2, Kẹo cầu vồng x1');
t('🔴 đọc đúng hai thành phần', 2 === Object.keys(c).length);
t('đúng số lượng', 2 === c['Nước suối'] && 1 === c['Kẹo cầu vồng']);
t('tên món giữ nguyên dấu tiếng Việt', undefined !== c['Kẹo cầu vồng']);

c = F.docThanhPhan('Nước suối');
t('🔴 không ghi "xN" thì hiểu là 1 — người ta hay gõ mỗi tên món', 1 === c['Nước suối']);

c = F.docThanhPhan('Nước suối X3');
t('chữ X hoa cũng được', 3 === c['Nước suối']);
c = F.docThanhPhan('Nước suối *3');
t('dấu * cũng được', 3 === c['Nước suối']);
c = F.docThanhPhan('  Nước suối  x2  ,  , Kẹo x1 ');
t('bỏ khoảng trắng thừa và mẩu rỗng', 2 === Object.keys(c).length && 2 === c['Nước suối']);
t('chuỗi rỗng ra bảng rỗng (chính là lệnh xoá combo)', 0 === Object.keys(F.docThanhPhan('')).length);

/* 🔴 Tên món CÓ SẴN chữ x — "Combo x2 người" — không được cắt nhầm thành "Combo" x2.
   Chỉ "xN" ở CUỐI mới là số lượng. */
c = F.docThanhPhan('Bánh x lát');
t('🔴 chữ x giữa tên không bị hiểu là số lượng', 1 === c['Bánh x lát']);

/* 🔴 Gõ dở "Nước suối x" (quên số) KHÔNG được ra NaN. NaN vào công thức combo là mọi phép trừ
   kho của mặt hàng ấy thành NaN, cột tồn tính trống trơn, và không có gì nói vì sao. */
c = F.docThanhPhan('Nước suối x');
Object.keys(c).forEach(function (k) {
  t('🔴 quên số sau "x" không ra NaN (' + k + ')', !Number.isNaN(c[k]) && c[k] > 0);
});
t('và vẫn giữ lại món ấy chứ không nuốt mất', 1 === Object.keys(c).length);

/* ── 4. màn hình phải dùng đúng mấy chốt của máy chủ ──────────────────────────────── */
const boCC = src.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/^\s*\/\/.*$/gm, ' ');
t('có tab Kho hàng hoá', /data-tab="kho"/.test(boCC));
t('màn kho gọi đúng đường REST', /api\('kho\?ngay=/.test(boCC));
t('🔴 ô ngày của màn kho đi qua noiONgay, không nối thẳng vào change',
  /noiONgay\(k\.querySelector\('#khoNgay'\)/.test(boCC));
t('đổi thành phần combo thì NẠP LẠI cả sổ', /api\('kho-combo'[\s\S]{0,120}then\(taiKho\)/.test(boCC));
t('bày cả hai cột máy: bán lẻ và theo combo',
  /Máy bán lẻ/.test(boCC) && /Theo combo/.test(boCC));
t('bày cả hai cột lệch', /Lệch khai/.test(boCC) && /Lệch kho/.test(boCC));
t('🔴 nhắc combo chưa khai thành phần', /combo_nghi/.test(boCC));
/* Cảnh báo trừ hai lần vẫn còn, nhưng nay nói đúng mức chắc chắn của nó: "xem lại kẻo",
   không phải "ĐANG trừ hai lần" — vì dòng 0đ có thể chỉ là hàng cho. */
t('🔴 vẫn nêu chỗ nghi trừ hai lần',
  /tru_hai_lan/.test(boCC) && /trừ hai lần/.test(boCC));
t('nhưng nói đúng mức chắc chắn ("xem lại kẻo"), không khẳng định',
  /Xem lại kẻo trừ hai lần/.test(boCC));
t('nêu ra món máy ghi 0đ', /mon_khong_tien/.test(boCC));
/* 🔴 KHÔNG ĐƯỢC KẾT LUẬN "FABi đã tách sẵn combo". Món máy ghi 0đ có thể là hàng cho, có thể là
   thành phần combo — hệ không phân biệt được. Bản trước khẳng định thẳng, và ảnh anh Thắng gửi
   cho thấy nó liệt kê toàn "… MIỄN PHÍ" với "VÉ ONLINE". */
t('🔴 KHÔNG còn câu khẳng định "FABi đang tự tách sẵn thành phần combo"',
  !/FABi đang tự tách sẵn/.test(boCC));
t('nói rõ là không phân biệt được hàng cho với thành phần combo',
  /hàng cho/.test(boCC) && /không phân biệt được/.test(boCC));
t('và vẫn nói chúng CÓ trừ kho', /vẫn rời kho/.test(boCC));
t('🔴 không vì món 0đ mà thôi nhắc khai combo',
  !/!\(r\.fabi_da_tach/.test(boCC) && !/!\(r\.mon_khong_tien/.test(boCC));
/* 🔴 Luôn nói đang xem kho của cơ sở nào — kể cả tài khoản chỉ có MỘT cơ sở. */
t('🔴 tài khoản một cơ sở vẫn thấy tên cơ sở', /khoCSMot/.test(boCC));
/* Nhãn phải NÓI VIỆC PHẢI LÀM, không chỉ mô tả trạng thái. "chưa có mốc" thì người trực đọc
   xong vẫn không biết phải làm gì; "đếm 1 lần để đặt mốc" thì làm được ngay. */
t('mặt hàng chưa từng đếm tay thì gắn nhãn chỉ rõ việc phải làm',
  /đếm 1 lần để đặt mốc/.test(boCC));

/* ── 6. chưa biết tồn thì hiện "—", và có chỗ chọn mặt hàng có kho ────────────────── */
/* 🔴 Anh Thắng mở thử trên điện thoại 20/09/2026: cả màn toàn số âm ("−61", "−139") vì hệ khởi
   tồn bằng 0 rồi trừ số bán ra, trong khi chưa hề biết trên kệ có bao nhiêu. */
const mMay = boCC.match(/var oMay = function[\s\S]*?\n          \};/);
t('cắt được hàm dựng ô số của máy', mMay !== null);
if (mMay) {
  t('🔴 chưa biết thì hiện "—", không bịa ra số',
    /gt === null \|\| gt === undefined/.test(mMay[0]) && /—/.test(mMay[0]));
}
t('🔴 có chỗ chọn mặt hàng có kho của cơ sở', /Mặt hàng có kho của cơ sở này/.test(boCC));
t('màn đọc danh mục và danh sách món đã thấy từ máy chủ',
  /mat_hang/.test(boCC) && /mon_da_thay/.test(boCC));
t('mỗi món một ô tích', /data-mh=/.test(boCC));
t('có nút lưu danh mục và nút bỏ tích hết', /mhLuu/.test(boCC) && /mhHet/.test(boCC));
t('🔴 chưa chọn danh mục thì khối ấy MỞ SẴN — đó là việc còn dở',
  /chon\.length \? '' : ' open'/.test(boCC));
t('bày kèm số lượng đã bán để biết món nào đáng theo dõi',
  /số lượng bán 90 ngày/.test(boCC));
t('lưu danh mục xong thì nạp lại cả màn',
  /api\('kho-mat-hang'[\s\S]{0,200}veKho\(o, rr\)/.test(boCC));

/* ── 5. bày lại thành thẻ dọc trên điện thoại ─────────────────────────────────────── */
/* 🔴 Sổ kho có 12 cột và ba cột phải gõ. Trên điện thoại bảng như thế thành dải cuộn ngang với
   ô bé bằng đầu ngón tay — mà đây đúng là màn nhân viên dùng hằng ngày ngoài cửa hàng. Bày lại
   bằng CSS trên CÙNG MỘT markup; hai bản HTML riêng thì bên bị quên sẽ là bên điện thoại, vì
   lúc lập trình ai cũng nhìn màn to. */
const css = fs.readFileSync(
  path.join(__dirname, '..', '..', 'wordpress', 'khh-doanh-thu', 'assets', 'doanh-thu.css'),
  'utf8'
).replace(/\/\*[\s\S]*?\*\//g, ' ');

const mMedia = css.match(/@media\s*\(max-width:\s*560px\)\s*\{([\s\S]*?)\n\}/g) || [];
const dt = mMedia.join('\n');
t('có khối @media điện thoại', dt.length > 0);
t('🔴 bảng kho bày lại thành thẻ dọc trên điện thoại', /\.bang-the/.test(dt));
t('ẩn hàng tiêu đề (vì mỗi ô tự mang nhãn)', /\.bang-the thead\{display:none\}/.test(dt));
t('🔴 mỗi ô tự in nhãn của nó ra bằng data-nhan',
  (dt.match(/content:attr\(data-nhan\)/g) || []).length >= 3);
t('ô phải gõ cao 44px và chữ 16px trên điện thoại',
  /\.o-go input\{[^}]*min-height:44px/.test(dt) && /\.o-go input\{[^}]*font-size:16px/.test(dt));
t('thôi cuộn ngang khi đã thành thẻ', /\.bang-the\{overflow-x:visible\}/.test(dt));

/* 🔴 MỌI ô trong bảng kho phải mang data-nhan. Thiếu một ô là trên điện thoại nó hiện ra một
   con số trần không nhãn, không ai đọc nổi là số gì. */
const mBang = boCC.match(/bang-cuon bang-the[\s\S]*?<\/tbody><\/table>/);
t('cắt được đoạn dựng bảng kho', mBang !== null);
if (mBang) {
  const td = mBang[0].match(/<td[^>]*/g) || [];
  t('có dựng ô <td> trong bảng kho', td.length > 0);
  const thieu = td.filter((x) => !/data-nhan/.test(x) && !/o-ten/.test(x));
  t('🔴 mọi ô <td> đều mang data-nhan (' + thieu.length + ' ô thiếu)', thieu.length === 0);
  t('ô tên mặt hàng có lớp riêng để chiếm cả dòng', /o-ten/.test(mBang[0]));
  t('ô phải gõ và ô chỉ đọc phân biệt được bằng lớp',
    /o-go/.test(mBang[0]) && /o-may/.test(mBang[0]));
}
/* `oLech` dựng ô ở hàm riêng nên kiểm riêng. */
const mLech = boCC.match(/function oLech\([\s\S]*?\n  \}/);
t('ô lệch cũng mang data-nhan và lớp o-lech',
  mLech !== null && /data-nhan/.test(mLech[0]) && /o-lech/.test(mLech[0]));

/* ── 7. sổ ghi động và ngày hiệu lực combo — vế MÀN HÌNH ──────────────────────────── */
/* 🔴 Giữ vết mà không bày ra thì chẳng ai biết là có vết: người trực vẫn tưởng sửa là xoá dấu,
   và người soát cũng không nghĩ tới chuyện đi xem lịch sử. Nhãn "đã sửa N lần" chính là phần
   răn, nên nó phải nằm ngay cạnh tên mặt hàng. */
t('màn đọc số lượt khai từ máy chủ', /r\.so_lan/.test(boCC));
t('🔴 hiện nhãn "đã sửa N lần" ngay trên dòng', /đã sửa '/.test(boCC));
t('nhãn ấy bấm được để xem lịch sử', /data-kho-su=/.test(boCC));
t('🔴 và chỉ hiện khi CÓ sửa (> 1 lượt), không hiện với dòng khai một lần',
  /so_lan\[d\.mat_hang\] \|\| 0\) > 1/.test(boCC));
t('trừ đi một lượt khi đếm số lần sửa (3 lượt khai = 2 lần sửa)',
  /so_lan\[d\.mat_hang\] - 1/.test(boCC));
const mSu = boCC.match(/closest\('\[data-kho-su\]'\)([\s\S]*?)\n    \}\);/);
t('có bộ xử lý xem lịch sử', mSu !== null);
if (mSu) {
  t('gọi đúng đường kho-su', /kho-su\?ngay=/.test(mSu[0]));
  t('🔴 bày kèm NGƯỜI và GIỜ — không có hai thứ ấy thì vết vô dụng',
    /x\.luc/.test(mSu[0]) && /x\.nguoi/.test(mSu[0]));
  t('bày cả ghi chú của từng lượt', /x\.ghi_chu/.test(mSu[0]));
}

/* 🔴 Ngày hiệu lực của combo: mặc định HÔM NAY, không mặc định lùi. Mặc định lùi là mọi lượt
   khai đều lặng lẽ viết lại số tồn của những ngày đã chốt. */
t('🔴 khối combo có ô ngày hiệu lực', /id="cbTu"/.test(boCC));
/* So chuỗi thẳng, không dùng regex: mẫu cần tìm có cả ' + ( ) nên viết regex là ba lớp
   thoát ký tự, và lần đầu em viết sai đúng chỗ ấy — phép thử đỏ vì CHÍNH NÓ sai, không
   phải vì mã sai. Phép thử tự sai thì tệ hơn không có phép thử. */
t('mặc định là HÔM NAY, không phải để trống hay lùi',
  boCC.indexOf('id="cbTu" value="' + String.fromCharCode(39) + ' + esc(homNay())') >= 0);
t('và gửi tu_ngay lên máy chủ', /fd\.append\('tu_ngay'/.test(boCC));
t('homNay() lấy theo múi giờ máy người dùng, không qua toISOString',
  /function homNay\(\) \{ return ymd\(new Date\(\)\); \}/.test(boCC));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: ô trống khác ô đã đếm, và công thức combo đọc đúng.');
