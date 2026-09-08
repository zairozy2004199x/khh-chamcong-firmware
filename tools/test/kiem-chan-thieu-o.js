/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BỊ CHẶN VÌ THIẾU Ô THÌ PHẢI NÓI RÕ THIẾU Ô NÀO, Ở GHẾ NÀO
 *
 * Anh Thắng 08/09/2026: *"bấm thêm chỗ lý do cũng không được"*, rồi *"nếu vậy sẽ cảnh báo lý do
 * thiếu, chứ lại liên quan gì đến hỏng"*.
 *
 * =============================================================================================
 * 🔴 ANH ĐÚNG Ở CẢ HAI VẾ.
 *
 *  (1) Hàng bất thường đòi HAI thứ — lý do VÀ Thực thu — mà câu chặn cũ gộp chung một câu cho
 *      mọi ghế. Điền xong một thứ vẫn bị chặn, và không có gì nói cho biết còn thiếu gì. Người
 *      nhập kết luận là trang hỏng, rất hợp lý.
 *
 *  (2) Lý do được đọc bằng cách DÒ NGƯỢC DOM: dựng lại một câu selector từ mã ghế. Mã ghế nào
 *      mang dấu nháy, dấu cách hay dấu chấm là câu ấy gãy, lý do đọc ra RỖNG, và hàng đó bị
 *      chặn VĨNH VIỄN dù đã điền — đúng chữ "bấm thêm chỗ lý do cũng không được". `collect()`
 *      đã đọc sẵn ô ấy vào `r.note`; dùng nó, một nguồn, không có gì để lệch.
 *
 * ⚠️ BỐC CHÍNH VÒNG DUYỆT TRONG `guiBaoCao()` RA CHẠY.
 *
 * Chạy: node tools/test/kiem-chan-thieu-o.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const DUONG = process.argv[2] || 'vhcp-ghe/includes/class-vhg-trang.php';
if (!fs.existsSync(DUONG)) { console.error('✗ Không thấy tệp nguồn: ' + DUONG); process.exit(2); }
const SRC = fs.readFileSync(DUONG, 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* Bốc đúng vòng duyệt quyết định chặn hay cho qua. */
const iV = SRC.indexOf('for(var i=0;i<rows.length;i++){ var r=rows[i];');
const jV = SRC.indexOf('if(canhBao.length){', iV);
const VONG = (iV > 0 && jV > iV) ? SRC.slice(iV, jV) : '';
t('bốc được vòng duyệt chặn', VONG.length > 400, VONG.length);
if (!VONG) { console.error('✗ không bốc được — dừng.'); process.exit(1); }

/** Chạy thật vòng ấy với một bảng ghế giả. Trả danh sách ghế bị chặn. */
function chan(rows, dv) {
  return new Function('rows', 'dv', 'KICHXA',
    'var canhBao=[];\n' + VONG + '\nreturn { canhBao:canhBao, rows:rows };')(
    rows, dv || 10000, {});
}
function ghe(o) {
  return Object.assign({ chairCode: 'G1', chairName: 'Ghế 1', meterBefore: 100, meterAfter: 110,
    qr: 0, adjust: null, note: '' }, o);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. HÀNG BÌNH THƯỜNG — KHÔNG CHẶN
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
teq('🔴 chỉ số đi đúng chiều, không QR → cho qua', 0, chan([ghe({})]).canhBao.length);
teq('có QR nhỏ hơn actual → cho qua', 0, chan([ghe({ qr: 50000 })]).canhBao.length);
teq('chưa nhập chỉ số → cho qua (hàng trống, không phải bất thường)',
  0, chan([ghe({ meterBefore: '', meterAfter: '' })]).canhBao.length);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 CHỈ SỐ ĐI NGƯỢC — ĐÚNG CA CỦA CHỊ NGỌC LAN (VP-PQ-5: 312 → 12)
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const NGUOC = { chairCode: 'VP-PQ-5', chairName: 'VP-PQ-5', meterBefore: 312, meterAfter: 12 };

let r1 = chan([ghe(NGUOC)]);
teq('chỉ số đi ngược, chưa điền gì → chặn', 1, r1.canhBao.length);
teq('🔴 và kể ĐÍCH DANH hai ô còn thiếu',
  ['Ghi chú (lý do)', 'Thực thu tiền mặt'], r1.canhBao[0].thieu);
teq('kèm tên ghế để còn tìm', 'VP-PQ-5', r1.canhBao[0].ten);
teq('và mã ghế để màn chỉ thẳng vào hàng ấy', 'VP-PQ-5', r1.canhBao[0].ma);

/* 🔴 PHÉP QUAN TRỌNG NHẤT: điền MỖI lý do — đúng việc anh Thắng vừa làm. */
let r2 = chan([ghe(Object.assign({}, NGUOC, { note: 'gõ nhầm chỉ số' }))]);
teq('🔴 điền mỗi lý do → vẫn chặn (đúng luật: còn đòi Thực thu)', 1, r2.canhBao.length);
teq('🔴 nhưng nay nói RÕ chỉ còn thiếu Thực thu, không kể lại lý do',
  ['Thực thu tiền mặt'], r2.canhBao[0].thieu);

let r3 = chan([ghe(Object.assign({}, NGUOC, { adjust: 500000 }))]);
teq('điền mỗi Thực thu → còn thiếu lý do', ['Ghi chú (lý do)'], r3.canhBao[0].thieu);

let r4 = chan([ghe(Object.assign({}, NGUOC, { note: 'gõ nhầm', adjust: 500000 }))]);
teq('🔴 điền ĐỦ hai ô → CHO GỬI', 0, r4.canhBao.length);
teq('   và lý do được gắn vào đơn gửi đi', 'gõ nhầm', r4.rows[0].abnormalReason);
teq('   Thực thu cũng thế', 500000, r4.rows[0].actualOverride);

/* Thực thu = 0 là một con số THẬT, không phải "bỏ trống". */
let r5 = chan([ghe(Object.assign({}, NGUOC, { note: 'máy hỏng', adjust: 0 }))]);
teq('🔴 Thực thu gõ đúng số 0 → vẫn tính là ĐÃ ĐIỀN', 0, r5.canhBao.length);
teq('   và số 0 ấy đi vào đơn, không bị nuốt thành null', 0, r5.rows[0].actualOverride);

/* Lý do toàn khoảng trắng không phải lý do. */
teq('lý do gõ mỗi dấu cách → vẫn coi là thiếu',
  ['Ghi chú (lý do)'], chan([ghe(Object.assign({}, NGUOC, { note: '   ', adjust: 1 }))]).canhBao[0].thieu);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 MÃ GHẾ CÓ KÝ TỰ LẠ — LỖI KHIẾN "ĐIỀN RỒI VẪN KHÔNG ĐƯỢC"
 *
 * Bản trước dò lý do bằng `querySelector('tr[data-ma="'+ma+'"]')`. Mã ghế mang dấu nháy, dấu
 * cách hay dấu chấm là câu selector gãy → lý do RỖNG → chặn vĩnh viễn dù đã điền.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
[ 'GHẾ "VIP" 1', "G'1", 'AEON TÂN PHÚ.1', 'A B C', 'G[1]', 'G#1' ].forEach(function (ma) {
  const r = chan([ghe({ chairCode: ma, chairName: ma, meterBefore: 300, meterAfter: 10,
    note: 'lý do có thật', adjust: 1000 })]);
  teq('🔴 mã ghế "' + ma + '" · điền đủ → CHO GỬI', 0, r.canhBao.length);
});
/* Và chốt: vòng duyệt KHÔNG được dò ngược DOM nữa. */
t('🔴 không còn dựng selector từ mã ghế trong vòng chặn',
  !/querySelector\([^)]*data-ma/.test(VONG), VONG.slice(0, 300));
t('🔴 lý do lấy thẳng từ r.note mà collect() đã đọc', /r\.note/.test(VONG), VONG.slice(0, 300));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. MÁY ĐỨNG YÊN MÀ CÓ QR — chỉ đòi Thực thu, KHÔNG đòi lý do
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const DUNG = { chairCode: 'G9', chairName: 'G9', meterBefore: 500, meterAfter: 500, qr: 200000 };
teq('🔴 máy đứng yên có QR, chưa gõ Thực thu → chặn, và chỉ đòi Thực thu',
  1, chan([ghe(DUNG)]).canhBao.length);
t('   câu nhắc gợi luôn "thường là 0"', /thường là 0/.test(chan([ghe(DUNG)]).canhBao[0].thieu.join(' ')),
  chan([ghe(DUNG)]).canhBao[0].thieu);
teq('🔴 gõ Thực thu 0 → CHO GỬI, không đòi lý do', 0, chan([ghe(Object.assign({}, DUNG, { adjust: 0 }))]).canhBao.length);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. NHIỀU GHẾ CÙNG THIẾU — KỂ HẾT, KHÔNG GỘP MỘT CÂU
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const r6 = chan([
  ghe({ chairCode: 'A', chairName: 'A', meterBefore: 300, meterAfter: 10 }),
  ghe({ chairCode: 'B', chairName: 'B' }),
  ghe({ chairCode: 'C', chairName: 'C', meterBefore: 300, meterAfter: 10, note: 'có lý do' }),
]);
teq('hai ghế thiếu → kể cả hai', 2, r6.canhBao.length);
teq('   ghế A thiếu hai ô', ['Ghi chú (lý do)', 'Thực thu tiền mặt'], r6.canhBao[0].thieu);
teq('   ghế C chỉ thiếu Thực thu', ['Thực thu tiền mặt'], r6.canhBao[1].thieu);
teq('   ghế B bình thường, không bị kể', -1, r6.canhBao.findIndex(function (c) { return c.ma === 'B'; }));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. CÂU CHẶN PHẢI ĐỌC NHƯ "CÒN THIẾU", KHÔNG PHẢI "TRANG HỎNG"
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const iM = SRC.indexOf('if(canhBao.length){');
const KHOI = SRC.slice(iM, SRC.indexOf('\n    }', iM));
t('🔴 mở đầu bằng "Chưa gửi được — còn thiếu thông tin"', /Chưa gửi được — còn thiếu thông tin/.test(KHOI), KHOI.slice(0, 200));
t('🔴 nói luôn lối thoát thứ hai: sửa chỉ số cho đúng', /sửa lại chỉ số cho đúng/.test(KHOI));
t('🔴 chỉ THẲNG vào hàng đang thiếu (cuộn tới)', /scrollIntoView/.test(KHOI));
/* Đòi đích danh lượt GẮN lớp. Dò trống chữ `bc-thieu` là dòng `classList.remove('bc-thieu')`
   ngay bên cạnh cũng khớp — phá thử chỉ ra đúng lỗ ấy. */
t('   và tô sáng hàng ấy', /classList\.add\('bc-thieu'\)/.test(KHOI), KHOI.slice(-400));
t('   tô hàng mới thì bỏ tô hàng cũ', /classList\.remove\('bc-thieu'\)/.test(KHOI));
t('có kiểu dáng cho hàng đang thiếu', /\.bc-thieu>td\{/.test(SRC));
/* 🔴 KHÔNG DÙNG innerHTML VỚI TÊN GHẾ. Tên do người khai đặt; một dấu `<` là vỡ trang. */
t('🔴 dựng câu bằng DOM, không ghép chuỗi HTML', !/msg\.innerHTML/.test(KHOI), KHOI.slice(0, 200));
t('   và tên ghế đi qua textContent', /textContent\s*=\s*c\.ten/.test(KHOI));
/* Khối JS này KHÔNG có esc() — gọi vào là ReferenceError, câu chặn không bao giờ hiện. */
const iJS = SRC.indexOf("<<<'JS'");
const JS1 = SRC.slice(SRC.indexOf('\n', iJS) + 1, SRC.indexOf('\nJS;', iJS));
t('đối chứng · khối JS này thật sự không có esc()', !/function esc\s*\(/.test(JS1));
/* ⚠️ BỎ CHÚ THÍCH TRƯỚC KHI DÒ. Chính khối này có một chú thích kể lại chuyện "bản nháp đầu gọi
   thẳng esc()" — dò thô là bắt trúng dòng văn xuôi ấy, và phép thử đỏ vì một chuyện nó không
   soi. Đã đỏ đúng như thế ở lượt chạy đầu. */
const KHOI_SACH = KHOI.replace(/\/\*[\s\S]*?\*\//g, ' ');
t('🔴 nên khối chặn KHÔNG được gọi esc()', !/esc\(/.test(KHOI_SACH), KHOI_SACH.slice(0, 200));

/* ══════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.error('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.error('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: chặn vì thiếu ô thì nói rõ ghế nào thiếu ô nào, và mã ghế lạ không còn khoá chết.');
