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
  boc('_daCapTong') + '\n' + boc('_conPhaiCap') + '\n' + boc('_daTenHang') + '\n'
  + boc('_daCapTheoLan') + '\n' + boc('_dotBlock')
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
  const h = F.blk({ soTien: 68790000,
    lich: [{ lan: 1, ngay: '03/09/2026', soTien: 10000000 },
           { lan: 2, ngay: '10/09/2026', soTien: 20000000 }],
    daCap: [{ lan: 1, ngay: '17/09/2026', soTien: 15000000, unc: 'UNC-1', nguoi: 'Chị Nhân' },
            { lan: 2, ngay: '19/09/2026', soTien: 5000000, unc: 'UNC-2', nguoi: 'Chị Nhân' }] });
  t('🔴 và chữ "đợt" KHÔNG còn xuất hiện ở đâu trong khối này', !/[Đđ]ợt/.test(h), h);
  /* 🔴 "LẦN N" CHỈ ĐƯỢC CÓ MỘT NGHĨA. Anh Thắng 18/09, sau khi hai khối đã nói chung một từ:
     *"cũng lấy 1 chữ lần 1 đi"*. Hai dòng "Lần 1" trong cùng một lệnh thì người đọc tự ghép
     chúng làm một — và đó đúng là cái ghép BỊA mà khối "Đã cấp" sinh ra để tránh (kế toán đưa
     15tr trong khi lịch hẹn 10tr). Nay chỉ khối LỊCH đánh số; khối ĐÃ CẤP đi bằng ngày. */
  t('🔴 chỉ khối "Lịch nhận tiền" đánh số Lần — hai lần hẹn, đúng hai nhãn',
    (h.match(/<b>Lần \d<\/b>/g) || []).length === 2, h);
  const daCapHtml = h.slice(h.indexOf('💵 Đã cấp'));
  t('🔴 khối "Đã cấp" KHÔNG còn đánh số lần (hai "Lần 1" là mời người ta ghép bịa)',
    !/Lần \d/.test(daCapHtml), daCapHtml);
  t('   nhưng vẫn giữ đủ NGÀY · SỐ TIỀN · UNC · ai cấp — thứ đem đi đối chiếu thật',
    /19\/09\/2026/.test(daCapHtml) && /5\.000\.000đ/.test(daCapHtml)
    && /UNC-2/.test(daCapHtml) && /Chị Nhân/.test(daCapHtml), daCapHtml);
  t('   và ngày đứng đầu dòng cho dễ dò', /<b>17\/09\/2026<\/b> · <b>15\.000\.000đ/.test(daCapHtml), daCapHtml);
}
{
  /* 🔴 SỔ CŨ KHÔNG CÓ NGÀY THÌ ĐỌC `luc`. Anh Thắng 18/09/2026: *"Nếu kế toán bấm cấp tiền mà
     không chọn ngày thì tự hiểu là lấy ngày bấm cấp làm ngày cấp tiền (kèm giờ luôn cho đầy
     đủ)"*, kèm ảnh sổ ba dòng mà hai dòng trống ngày. Máy chủ nay điền sẵn `ngay`, nhưng mấy
     dòng ĐÃ NẰM TRONG SỔ trước hôm ấy thì không ai đi sửa lại — chúng chỉ còn `luc`. */
  const h = F.blk({ soTien: 5000000, lich: [],
    daCap: [{ lan: 1, soTien: 5000000, luc: '18/09/2026 09:29', nguoi: 'Chị Nhân' }] });
  t('🔴 dòng sổ cũ không có ngày → lấy `luc` (lúc bấm cấp), KÈM GIỜ',
    /<b>18\/09\/2026 09:29<\/b> · <b>5\.000\.000đ/.test(h), h);
}
{
  /* Có ngày thì ngày THẮNG — `luc` chỉ là lưới đỡ, không được đè lên thứ kế toán tự khai. */
  const h = F.blk({ soTien: 5000000, lich: [],
    daCap: [{ lan: 1, soTien: 5000000, ngay: '03/09/2026', luc: '18/09/2026 09:29' }] });
  t('🔴 có ngày kế toán khai thì in ngày ấy, KHÔNG để lúc bấm đè lên',
    /<b>03\/09\/2026<\/b> · <b>5\.000\.000đ/.test(h) && h.indexOf('09:29') < 0, h);
}
{
  /* Dòng không có cả hai (sổ rất cũ): vẫn phải đọc được, không mở đầu bằng dấu chấm giữa. */
  const h = F.blk({ soTien: 5000000, lich: [], daCap: [{ lan: 1, soTien: 5000000 }] });
  t('không có cả ngày lẫn `luc` → dòng mở đầu thẳng bằng số tiền, không bằng dấu chấm lửng lơ',
    /margin:1px 0 1px 10px"><b>5\.000\.000đ<\/b><\/div>/.test(h), h);
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

/* ── 3c. 🔴 XIN LẦN 1 THÌ CẤP LẦN 1 ─────────────────────────────────────────────────────
 * Anh Thắng 18/09/2026, nhìn khối "Đã cấp" không nói gì về lần: *"Phía dưới phải có — Xin lần 1
 * thì cấp lần 1 chứ.."*.
 *
 * =============================================================================================
 * Bản 1.199.0 gỡ số ở khối "Đã cấp" là ĐÚNG với dữ liệu hồi ấy: số đó chỉ là thứ tự lượt chi,
 * in ra cạnh khối lịch là mời người đọc ghép hai danh sách theo chỉ số, tức bịa. Nay kế toán
 * CHỌN lần lúc cấp (`choLan`), nên cái ghép ấy có người khai — và số được phép quay lại.
 * ────────────────────────────────────────────────────────────────────────────────────────── */
{
  const d = { soTien: 30000000,
    lich: [{ lan: 1, ngay: '03/09/2026', soTien: 10000000 },
           { lan: 2, ngay: '10/09/2026', soTien: 20000000 }],
    daCap: [{ choLan: 1, ngay: '03/09/2026', soTien: 10000000, nguoi: 'Chị Nhân' },
            { choLan: 2, ngay: '18/09/2026 09:27', soTien: 8000000, nguoi: 'Chị Nhân' }] };
  const h = F.blk(d);
  const [lichHtml, capHtml] = [h.slice(0, h.indexOf('💵 Đã cấp')), h.slice(h.indexOf('💵 Đã cấp'))];
  t('🔴 dòng cấp nói rõ đang trả cho LẦN NÀO', /<b>Lần 1<\/b> · <b>03\/09\/2026/.test(capHtml), capHtml);
  t('   và lượt của lần 2 gắn đúng lần 2', /<b>Lần 2<\/b> · <b>18\/09\/2026 09:27/.test(capHtml), capHtml);
  /* Đây mới là phần trả lời câu anh hỏi: đọc khối LỊCH là biết lần nào xong, lần nào còn. */
  t('🔴 lần 1 nhận đủ → khối lịch nói "đã nhận đủ"',
    /Lần 1<\/b> · hẹn 03\/09\/2026 · <b>10\.000\.000đ<\/b> · <span[^>]*>✓ đã nhận đủ/.test(lichHtml), lichHtml);
  t('🔴 lần 2 mới nhận dở → nói ra đã nhận bao nhiêu VÀ còn bao nhiêu',
    /Lần 2[^]*?đã nhận <b>8\.000\.000đ<\/b>[^]*?còn <b>12\.000\.000đ<\/b>/.test(lichHtml), lichHtml);
}
{
  /* 🔴 KHÔNG BÁO "CHƯA NHẬN" CHO MỘT LẦN MÀ TIỀN ĐÃ RA. Sổ cũ (và lối cấp trọn nhiều lần) không
     gắn lần nào; suy ra "lần 1 chưa nhận" từ chỗ không biết là nói sai — thà im. */
  const h = F.blk({ soTien: 30000000,
    lich: [{ lan: 1, ngay: '03/09/2026', soTien: 10000000 },
           { lan: 2, ngay: '10/09/2026', soTien: 20000000 }],
    daCap: [{ choLan: 0, ngay: '18/09/2026 09:29', soTien: 30000000, nguoi: 'Chị Nhân' }] });
  t('🔴 lượt cấp KHÔNG gắn lần → khối lịch không phán gì về lần nào',
    h.indexOf('đã nhận') < 0 && h.indexOf('✓ đã nhận đủ') < 0, h);
  t('   và dòng cấp ấy cũng không bịa ra một số lần', !/<b>Lần \d<\/b> · <b>18/.test(h), h);
  t('   nhưng tổng vẫn đúng, vẫn nói đã đưa đủ', /tổng đã đưa <b>30\.000\.000đ<\/b> · <b>đủ/.test(h), h);
}
/* Hàm cộng theo lần — chạy thật, vì cả khối lịch lẫn ô chọn lần trong form đều dựa vào nó. */
{
  const F2 = new Function('return ' + boc('_daCapTheoLan').replace(/^\s*function /, 'function ') + ';')();
  const m = F2({ daCap: [{ choLan: 1, soTien: 10 }, { choLan: 2, soTien: 20 },
                         { choLan: 1, soTien: 5 }, { choLan: 0, soTien: 999 }] });
  t('🔴 cộng dồn nhiều lượt vào cùng một lần', m[1] === 15, m);
  t('   mỗi lần một ngăn riêng', m[2] === 20, m);
  t('🔴 lượt không gắn lần KHÔNG rơi vào lần nào (nhét đại vào lần 1 là dựng lại đúng cái ghép bịa)',
    m[0] === undefined && Object.keys(m).length === 2, m);
}

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
