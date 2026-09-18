/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỖI LẦN NHẬN TIỀN MỘT DÒNG, DƯỚI MỖI LỆNH — VÀ HAI TẦNG THÔI GỌI CHUNG MỘT TÊN.
 *
 * Anh Thắng 17/09/2026, nhìn dòng 68.790.000đ ghi "Đợt 1": *"đợt 1 là 10tr, sao lại ghi trong
 * này là 68tr"*.
 *
 * =============================================================================================
 * Con số không sai, CÁI NHÃN sai. Hệ có hai tầng mà bảng chỉ vẽ tầng ngoài rồi gọi nó là "đợt":
 *   · LỆNH = lô hạng mục gửi xin cùng lúc, một tổng tiền, một lượt duyệt   (68.790.000đ)
 *   · ĐỢT  = mỗi lần thật sự đi nhận tiền trong lô ấy                      (03/09 · 10tr)
 * Chính bảng cũng tự mâu thuẫn: tiêu đề ghi "Lệnh tạm ứng của dự án", cột đầu ghi "Đợt".
 *
 * 🔴 KHÔNG GHÉP LỊCH HẸN VỚI LẦN CẤP THẬT THEO THỨ TỰ. Hai danh sách ấy độc lập — kế toán có
 *    thể đưa 15tr thay vì 10tr, gộp hai đợt làm một, hay đưa sớm một hôm. Ghép theo chỉ số là
 *    BỊA ra một sự thật thứ ba, mà nó lại trông đáng tin hơn hẳn hai cái thật.
 *
 * Chạy: node tools/test/kiem-dot-trong-lenh.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) {
  if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : ''));
}
const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

const boc = ten => {
  const i = HTML.indexOf('  function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i);
  return j < i ? '' : HTML.slice(i, j + 4);
};
/* `DA_CUR` là dự án đang mở — `_daTenHang()` tra tên hạng mục trong đó. Truyền vào một dự án
   giả có đúng mấy dòng đang thử, chứ KHÔNG chặn `_daTenHang` bằng bản rút gọn: chính nó là thứ
   đang kiểm ở mục 5. */
const DA_GIA = { lines: [
  { row: 3, noiDung: 'Thợ Phụ' },
  { row: 5, noiDung: 'Vật tư điện' },
] };
const F = new Function('esc', 'money', '_dmy', 'DA_CUR',
  boc('_daCapTong') + '\n' + boc('_conPhaiCap') + '\n' + boc('_daTenHang') + '\n' + boc('_dotBlock')
  + '\nreturn { blk: _dotBlock, tong: _daCapTong, con: _conPhaiCap, ten: _daTenHang };')(
  x => String(x == null ? '' : x),
  n => String(Number(n) || 0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'),
  v => String(v), DA_GIA);

/* ── 1. ĐÚNG CẢNH TRONG ẢNH ───────────────────────────────────────────────────────────── */
{
  const d = { soTien: 68790000, daCap: [], lich: [
    { lan: 1, ngay: '03/09/2026', soTien: 10000000 },
    { lan: 2, ngay: '10/09/2026', soTien: 20000000 },
  ] };
  const h = F.blk(d);
  t('🔴 mỗi lần nhận tiền một dòng, đúng số tiền của lần ấy',
    /Lần 1<\/b> · hẹn 03\/09\/2026 · <b>10\.000\.000đ/.test(h), h);
  t('   và lần 2 là 20tr', /Lần 2<\/b> · hẹn 10\/09\/2026 · <b>20\.000\.000đ/.test(h), h);
  t('🔴 KHÔNG gán 68tr cho đợt nào — đó là tổng của LỆNH, không phải của một đợt',
    h.indexOf('68.790.000') < 0, h);
  /* Lịch khai 30tr cho một lệnh 68.79tr: 38.79tr chưa nằm trong đợt nào. Anh Thắng chốt
     17/09: *"còn số nào chưa lên thì ghi là dự kiến đợt tiếp theo"* — xin từng đợt là ĐÚNG quy
     trình, nên chỗ này nói bằng giọng bình thường, KHÔNG gắn dấu cảnh báo. Gắn ⚠️ lên một việc
     bình thường thì mọi lệnh đều có một dòng cam, rồi người ta thôi đọc màu ấy. */
  t('🔴 nói ra phần chưa xếp lần nào, gọi là "dự kiến lần tiếp theo" (38.790.000đ)',
    /dự kiến lần tiếp theo: <b>38\.790\.000đ/.test(h), h);
  t('   và KHÔNG gắn dấu cảnh báo cho một việc đúng quy trình', h.indexOf('⚠️') < 0, h);
}

/* ── 2. LỊCH PHỦ ĐỦ THÌ KHÔNG KÊU ─────────────────────────────────────────────────────── */
{
  const h = F.blk({ soTien: 30000000, daCap: [], lich: [
    { lan: 1, ngay: '03/09/2026', soTien: 10000000 },
    { lan: 2, ngay: '10/09/2026', soTien: 20000000 },
  ] });
  t('lịch phủ đủ tổng lệnh → không có dòng "dự kiến lần tiếp theo"',
    h.indexOf('dự kiến lần tiếp theo') < 0, h);
}
/* Lệnh không khai lịch nào là chuyện thường (nhận một lần). Kêu ở đó thì MỌI lệnh đều có một
   dòng cảnh báo, và người ta thôi đọc nó. */
{
  const h = F.blk({ soTien: 68790000, lich: [], daCap: [] });
  t('🔴 lệnh không khai lịch nào → không dựng khối, không kêu oan', h === '', h);
}

/* ── 3. KHỐI "ĐÃ CẤP" ĐỨNG RIÊNG, KHÔNG GHÉP VỚI LỊCH ────────────────────────────────── */
{
  const d = { soTien: 68790000,
    lich: [{ lan: 1, ngay: '03/09/2026', soTien: 10000000 }],
    daCap: [{ lan: 1, ngay: '17/09/2026', soTien: 15000000, unc: 'UNC-1', nguoi: 'Chị Nhân' }] };
  const h = F.blk(d);
  t('có khối "Đã cấp" riêng', /Đã cấp/.test(h), h);
  t('   ghi đủ số tiền · ngày · uỷ nhiệm chi · ai cấp',
    /15\.000\.000đ/.test(h) && /17\/09\/2026/.test(h) && /UNC-1/.test(h) && /Chị Nhân/.test(h), h);
  /* 🔴 Kế toán đưa 15tr trong khi lịch hẹn 10tr — nếu ghép theo chỉ số thì màn hình sẽ nói
     "Đợt 1: hẹn 10tr → đã cấp 10tr", tức bịa. Hai khối riêng thì ai đọc cũng thấy lệch. */
  t('🔴 KHÔNG ghép đợt-lịch với lần-cấp (đưa 15tr mà lịch hẹn 10tr vẫn hiện đúng cả hai)',
    /hẹn 03\/09\/2026 · <b>10\.000\.000đ/.test(h) && /15\.000\.000đ/.test(h), h);
  t('   và nói còn phải đưa bao nhiêu', /còn phải đưa <b>53\.790\.000đ/.test(h), h);
}
{
  const h = F.blk({ soTien: 5000000, lich: [],
    daCap: [{ lan: 1, soTien: 5000000 }] });
  t('cấp đủ thì nói "đủ", không in "còn 0đ"', /· <b>đủ<\/b>/.test(h) && h.indexOf('còn phải đưa') < 0, h);
}

/* ── 3b. 🔴 HAI KHỐI, MỘT TỪ ────────────────────────────────────────────────────────────
 * Anh Thắng 18/09/2026, nhìn khối "Lịch nhận tiền" ghi *Đợt 1 · Đợt 2* trong khi khối "Đã cấp"
 * ngay dưới ghi *Lần 1 · Lần 2 · Lần 3*: *"2 từ ngữ khác nhau, đồng nhất lại là lần 1,2,3"*.
 *
 * =============================================================================================
 * Nay cả màn chỉ còn HAI từ, mỗi từ một tầng:
 *   · LỆNH N = lô hạng mục gửi xin cùng lúc, một tổng tiền, một lượt duyệt
 *   · LẦN N  = mỗi lần thật sự đi nhận tiền trong lô ấy (cả lịch hẹn lẫn lần cấp thật)
 * Từ "đợt" đã rút khỏi màn — nó từng lúc chỉ tầng ngoài, lúc chỉ tầng trong, và chính chỗ lẫn
 * ấy đẻ ra câu hỏi *"đợt 1 là 10tr, sao lại ghi trong này là 68tr"*.
 * ────────────────────────────────────────────────────────────────────────────────────────── */
{
  const h = F.blk({ soTien: 68790000, lich: [{ lan: 1, ngay: '03/09/2026', soTien: 10000000 }],
    daCap: [{ lan: 1, ngay: '17/09/2026', soTien: 15000000, unc: 'UNC-1', nguoi: 'Chị Nhân' }] });
  t('🔴 khối "Lịch nhận tiền" và khối "Đã cấp" gọi CÙNG một từ: Lần',
    (h.match(/<b>Lần \d<\/b>/g) || []).length === 2, h);
  t('🔴 và chữ "đợt" KHÔNG còn xuất hiện ở đâu trong khối này', !/[Đđ]ợt/.test(h), h);
}
/* Canh CẢ MÃ NGUỒN, không chỉ một cảnh dựng ra: một chỗ còn sót thì người dùng vẫn gặp hai từ,
   chỉ là gặp ở màn khác.
   ⚠️ CANH CHUỖI ĐEM IN RA, không canh chữ trần. Chữ "đợt" còn nằm đầy trong phần chú thích (kể
      lại chính mấy lỗi cũ), và canh chữ trần thì phép này đỏ oan vì một dòng chú thích — rồi
      người ta nới nó ra và mất luôn chốt thật. */
[['🧾 đã gửi QT ', '🧾 đã gửi QT lệnh '],
 ['💵 Cấp tạm ứng ', '💵 Cấp tạm ứng lệnh '],
 ['✓ Cấp ', '✓ Cấp lệnh '],
 ['Đã gửi lệnh tạm ứng ', "Đã gửi lệnh tạm ứng '+_dotHien"],
 ['Đã gửi quyết toán ', "Đã gửi quyết toán lệnh '+_dotHien"],
 ['↳ dự kiến ', '↳ dự kiến lần tiếp theo']].forEach(([a, mong]) => {
  t('🔴 "' + a.trim() + '…" viết bằng từ mới, không còn "đợt"', HTML.indexOf(mong) >= 0,
    (() => { const i = HTML.indexOf(a); return i < 0 ? 'KHÔNG TÌM THẤY ' + a : HTML.slice(i, i + 70); })());
});

/* ── 4. NHÃN CỘT: LỆNH, KHÔNG PHẢI ĐỢT ──────────────────────────────────────────────── */
t('🔴 cột đầu của bảng lệnh ghi "Lệnh"', /<th style="width:74px">Lệnh<\/th>/.test(HTML));
t('🔴 và nhãn từng dòng cũng ghi "Lệnh N"', /return '<b>Lệnh '\+n\+'<\/b>';/.test(HTML));
t('   tiêu đề bảng vẫn là "Lệnh tạm ứng của dự án" — nay cột khớp tiêu đề',
  HTML.indexOf('Lệnh tạm ứng của dự án') >= 0);
t('🔴 khối đợt được gắn vào bảng', /var lich=_dotBlock\(d\);/.test(HTML));

/* ── 5. MỖI ĐỢT NÓI RA GỒM NHỮNG HẠNG MỤC NÀO ───────────────────────────────────────────
 * Anh Thắng 17/09/2026: *"Anh muốn xác định chi phí từng hàng là chi lần 1, hay chi lần 2"*.
 * Xếp được rồi thì bảng phải ĐỌC LẠI được: một dòng "Đợt 2 · hẹn … · 20tr" mà không nói gồm
 * gì thì vẫn phải mở form ra dò, tức chưa trả lời được câu anh hỏi.
 * ────────────────────────────────────────────────────────────────────────────────────────── */
{
  const h = F.blk({ soTien: 30000000, daCap: [], lich: [
    { lan: 1, ngay: '03/09/2026', soTien: 10000000, rows: [3] },
    { lan: 2, ngay: '10/09/2026', soTien: 20000000, rows: [5] },
  ] });
  t('🔴 lần 1 gọi TÊN hạng mục của nó, không chỉ một con số', h.indexOf('Thợ Phụ') >= 0, h);
  t('🔴 và lần 2 gọi tên hạng mục của lần 2', h.indexOf('Vật tư điện') >= 0, h);
  /* Đặt nhầm chỗ là nguy hiểm hơn cả không có: đọc "Lần 1 · Vật tư điện" rồi chuẩn bị sai tiền. */
  t('   đúng hạng mục vào đúng lần, không đảo chỗ',
    h.indexOf('Thợ Phụ') < h.indexOf('Vật tư điện'), h);
}
t('lệnh cũ (chưa có `rows`) vẫn vẽ được, không nổ và không in tên bịa',
  F.blk({ soTien: 10000000, daCap: [], lich: [{ lan: 1, ngay: '03/09/2026', soTien: 10000000 }] })
    .indexOf('Thợ Phụ') < 0);
t('🔴 dòng không tra được tên thì gọi theo SỐ DÒNG, không để ô trống', F.ten(99) === 'Dòng 99', F.ten(99));
t('   tra được thì lấy đúng tên', F.ten(5) === 'Vật tư điện', F.ten(5));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: lệnh là lệnh, lần là lần, và không bịa ra cái thứ ba.');
