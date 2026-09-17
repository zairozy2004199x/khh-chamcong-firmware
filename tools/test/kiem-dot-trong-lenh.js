/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỖI ĐỢT MỘT DÒNG, DƯỚI MỖI LỆNH — VÀ HAI TẦNG THÔI GỌI CHUNG MỘT TÊN.
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
const F = new Function('esc', 'money', '_dmy',
  boc('_daCapTong') + '\n' + boc('_conPhaiCap') + '\n' + boc('_dotBlock')
  + '\nreturn { blk: _dotBlock, tong: _daCapTong, con: _conPhaiCap };')(
  x => String(x == null ? '' : x),
  n => String(Number(n) || 0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'),
  v => String(v));

/* ── 1. ĐÚNG CẢNH TRONG ẢNH ───────────────────────────────────────────────────────────── */
{
  const d = { soTien: 68790000, daCap: [], lich: [
    { lan: 1, ngay: '03/09/2026', soTien: 10000000 },
    { lan: 2, ngay: '10/09/2026', soTien: 20000000 },
  ] };
  const h = F.blk(d);
  t('🔴 mỗi đợt một dòng, đúng số tiền của đợt ấy',
    /Đợt 1<\/b> · hẹn 03\/09\/2026 · <b>10\.000\.000đ/.test(h), h);
  t('   và đợt 2 là 20tr', /Đợt 2<\/b> · hẹn 10\/09\/2026 · <b>20\.000\.000đ/.test(h), h);
  t('🔴 KHÔNG gán 68tr cho đợt nào — đó là tổng của LỆNH, không phải của một đợt',
    h.indexOf('68.790.000') < 0, h);
  /* Lịch khai 30tr cho một lệnh 68.79tr: 38.79tr không nằm trong đợt nào, mà bản trước
     chẳng có gì nói thế. Kế toán nhìn lịch mà liệu tiền thì chuẩn bị thiếu hơn một nửa. */
  t('🔴 nói ra phần CHƯA XẾP ĐỢT (38.790.000đ)',
    /chưa xếp vào đợt nào/.test(h) && /38\.790\.000đ/.test(h), h);
}

/* ── 2. LỊCH PHỦ ĐỦ THÌ KHÔNG KÊU ─────────────────────────────────────────────────────── */
{
  const h = F.blk({ soTien: 30000000, daCap: [], lich: [
    { lan: 1, ngay: '03/09/2026', soTien: 10000000 },
    { lan: 2, ngay: '10/09/2026', soTien: 20000000 },
  ] });
  t('lịch phủ đủ tổng lệnh → không có dòng cảnh báo', h.indexOf('chưa xếp vào đợt nào') < 0, h);
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

/* ── 4. NHÃN CỘT: LỆNH, KHÔNG PHẢI ĐỢT ──────────────────────────────────────────────── */
t('🔴 cột đầu của bảng lệnh ghi "Lệnh"', /<th style="width:74px">Lệnh<\/th>/.test(HTML));
t('🔴 và nhãn từng dòng cũng ghi "Lệnh N"', /return '<b>Lệnh '\+n\+'<\/b>';/.test(HTML));
t('   tiêu đề bảng vẫn là "Lệnh tạm ứng của dự án" — nay cột khớp tiêu đề',
  HTML.indexOf('Lệnh tạm ứng của dự án') >= 0);
t('🔴 khối đợt được gắn vào bảng', /var lich=_dotBlock\(d\);/.test(HTML));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: lệnh là lệnh, đợt là đợt, và không bịa ra cái thứ ba.');
