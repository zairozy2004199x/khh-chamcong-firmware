/*
 * Kiểm thử đối chiếu sao kê: chạy `node test/saoke.test.js`, không cần cài gì thêm.
 *
 * Tập trung vào đúng mấy chỗ đối chiếu tự động hay sai một cách IM LẶNG:
 *   - đọc nhầm tiền VỀ thành tiền RA (sao kê gộp hai chiều vào một cột)
 *   - một lượt chuyển bị hai dòng sổ cùng nhận là của mình
 *   - tự bật "đã đi tiền" cho một khoản kế toán đã đánh tay khác đi
 *   - lệch một đồng mà vẫn coi là khớp
 *   - giao dịch ngân hàng KHÔNG có dòng sổ nào (thứ dò tay không bao giờ thấy)
 */
const assert = require('assert');
const E = require('../engine.js');
const I = require('../importer.js');
const S = require('../saoke.js');

let soTest = 0;
function test(ten, fn) {
  soTest++;
  try {
    fn();
    console.log('  ✓ ' + ten);
  } catch (e) {
    console.error('  ✗ ' + ten + '\n    ' + e.message);
    process.exitCode = 1;
  }
}
function nhom(ten) { console.log('\n' + ten); }

/* ===================== Dựng cảnh ===================== */

/** Một sheet ủy nhiệm chi tối thiểu nhưng đúng bố cục thật. */
function soUNC(dsDong) {
  const rows = [
    ['BP', 'Ngày cần đi tiền', 'Ngày tạo lệnh', 'Nội dung trên đề xuất', 'Số tiền',
      'Tên đơn vị thụ hưởng', 'Tài khoản người thụ hưởng', 'Ngân hàng người thụ hưởng', 'Ngân hàng'],
  ];
  dsDong.forEach((d) => {
    rows.push([d.bp || 'POSH', d.han || '', d.lenh || '', d.noiDung, d.tien,
      d.thuHuong || '', d.stk || '', d.nh || 'VCB', d.tkCty || 'K&H']);
  });
  return I.docSheets([{ ten: 'THÁNG 9.2026', rows: rows }]).recs;
}

/** Sao kê hai cột Ghi nợ / Ghi có — bố cục phổ biến nhất. */
function saoKeHaiCot(dsGD) {
  const rows = [
    ['SAO KÊ TÀI KHOẢN'],
    ['Số tài khoản:', '0501000160370'],
    [],
    ['Ngày giao dịch', 'Số tham chiếu', 'Ghi nợ', 'Ghi có', 'Số dư', 'Nội dung', 'Tài khoản đối ứng', 'Tên đối ứng'],
  ];
  dsGD.forEach((g) => {
    rows.push([g.ngay, g.ma || '', g.no || '', g.co || '', g.du || '', g.nd || '', g.tk || '', g.ten || '']);
  });
  return rows;
}

const HOM_NAY = '2026-09-30';

/* ===================== Đọc sao kê ===================== */

nhom('Đọc sao kê');

test('nhận ra dòng tiêu đề dù trên nó có mấy dòng thông tin tài khoản', () => {
  const kq = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25500000, nd: 'CT tien hang thang 8', tk: '0901234567', ten: 'CTY TNHH ABC' },
  ]), { nguon: 'VCB' });
  assert.strictEqual(kq.gd.length, 1);
  assert.strictEqual(kq.gd[0].soTien, 25500000);
  assert.strictEqual(kq.gd[0].ngay, '2026-09-05');
  assert.strictEqual(kq.gd[0].taiKhoanDoiUng, '0901234567');
});

test('🔴 TIỀN VỀ (ghi có) KHÔNG được đọc thành tiền ra', () => {
  const kq = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25500000, nd: 'chuyen tra CTY ABC' },
    { ngay: '06/09/2026', co: 25500000, nd: 'CTY ABC hoan tien' },
  ]), { nguon: 'VCB' });
  assert.strictEqual(kq.gd.length, 1, 'chỉ một dòng tiền ra');
  assert.ok(kq.nhatKy.some((n) => /TIỀN VỀ/.test(n.text)), 'và phải NÓI RA là đã bỏ dòng nào');
});

test('🔴 một cột "Số tiền" không dấu thì KHÔNG đoán chiều — thà bỏ còn hơn khớp nhầm', () => {
  const rows = [
    ['Ngày', 'Số tiền', 'Nội dung'],
    ['05/09/2026', 25500000, 'khong biet chieu nao'],
    ['06/09/2026', -1836000, 'tien ra that'],
  ];
  const kq = S.docSheet(rows, { nguon: 'la' });
  assert.strictEqual(kq.gd.length, 1);
  assert.strictEqual(kq.gd[0].soTien, 1836000);
});

test('số âm viết kiểu kế toán (1.836.000) cũng là tiền ra', () => {
  const kq = S.docSheet([
    ['Ngày', 'Số tiền', 'Nội dung'],
    ['06/09/2026', '(1.836.000)', 'tien ra'],
  ], { nguon: 'la' });
  assert.strictEqual(kq.gd.length, 1);
  assert.strictEqual(kq.gd[0].soTien, 1836000);
});

test('sheet không có cột Ngày hoặc cột tiền thì bỏ qua VÀ nói ra, không đọc bừa', () => {
  const kq = S.docSheet([['Tên', 'Ghi chú'], ['a', 'b']], { nguon: 'linh tinh' });
  assert.strictEqual(kq.gd.length, 0);
  assert.ok(kq.nhatKy.some((n) => n.muc === 'canh'));
});

test('🔴 "Số tiền ghi có" KHÔNG bị cột "Số tiền" nuốt mất', () => {
  const rows = [
    ['Ngày giao dịch', 'Số tiền ghi nợ', 'Số tiền ghi có', 'Nội dung'],
    ['05/09/2026', 1000000, '', 'ra'],
    ['06/09/2026', '', 2000000, 've'],
  ];
  const kq = S.docSheet(rows, { nguon: 'x' });
  assert.strictEqual(kq.gd.length, 1);
  assert.strictEqual(kq.gd[0].soTien, 1000000);
});

test('số tài khoản bóc được từ nội dung chuyển khoản khi sao kê không có cột riêng', () => {
  assert.strictEqual(S.bocSoTK('CT tu TK 0501000160370 toi CTY ABC'), '0501000160370');
});

test('🔴 nội dung có NHIỀU chuỗi số dài thì KHÔNG đoán bừa một cái', () => {
  assert.strictEqual(S.bocSoTK('HD 12345678901 chuyen 0501000160370 thang 9'), '');
});

test('chuỗi số ngắn (số hoá đơn, mã hợp đồng) không bị nhận là tài khoản', () => {
  assert.strictEqual(S.bocSoTK('thanh toan HD 1234567'), '');
});

/* ===================== Ba mức chắc chắn ===================== */

nhom('Ba mức chắc chắn');

test('🔴 khớp tiền + SỐ TÀI KHOẢN + trong cửa sổ ngày = khớp CHẮC', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng tháng 8', tien: 25500000, han: '02/09/2026', thuHuong: 'CÔNG TY TNHH ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25500000, nd: 'CT tien hang', tk: '0901234567', ten: 'CTY TNHH ABC' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.capChac.length, 1);
  assert.strictEqual(kq.tom.gdLe, 0);
});

test('khớp tiền + TÊN (không khớp số TK) = khớp KHÁ, KHÔNG tự bật', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng tháng 8', tien: 25500000, han: '02/09/2026', thuHuong: 'CÔNG TY TNHH ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25500000, nd: 'CT tien hang', tk: '9999999999', ten: 'CTY TNHH ABC' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.capChac.length, 0);
  assert.strictEqual(kq.capKha.length, 1);
});

test('tên nằm lẫn trong nội dung chuyển khoản vẫn nhận ra', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 1836000, han: '02/09/2026', thuHuong: 'CÔNG TY TNHH THÁI BÌNH AN' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '04/09/2026', no: 1836000, nd: 'CT THAI BINH AN tien hang t8' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.capKha.length, 1);
  assert.ok(kq.capKha[0].cham.khopTen);
});

test('🔴 chỉ khớp mỗi số tiền thì dừng ở mức NGỜ, không bao giờ tự bật', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 1836000, han: '02/09/2026', thuHuong: 'CÔNG TY TNHH ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([{ ngay: '04/09/2026', no: 1836000, nd: 'thanh toan' }]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.capChac.length, 0);
  assert.strictEqual(kq.capKha.length, 0);
  assert.strictEqual(kq.capNgo.length, 1);
});

test('🔴 lệch MỘT ĐỒNG là hai khoản khác nhau, không khớp', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 25500000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25499999, nd: 'CT', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.tom.chac + kq.tom.kha + kq.tom.ngo, 0);
  assert.strictEqual(kq.tom.gdLe, 1, 'và lượt trừ ấy phải hiện ra ở nhóm "ngân hàng trừ mà sổ không có"');
});

test('🔴 ngoài cửa sổ ngày thì không phải cặp — lệnh tháng 9, tiền trừ tháng 12', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 25500000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '20/12/2026', no: 25500000, nd: 'CT', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.tom.chac, 0);
  assert.strictEqual(kq.tom.gdLe, 1);
});

test('cửa sổ ngày nới ra được khi kế toán muốn', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 25500000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '25/09/2026', no: 25500000, nd: 'CT', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  assert.strictEqual(S.doiChieu(dong, gd, {}).tom.chac, 0);
  assert.strictEqual(S.doiChieu(dong, gd, { cuaSo: { sau: 30 } }).tom.chac, 1);
});

/* ===================== Một-một ===================== */

nhom('Một giao dịch chỉ khớp một dòng sổ');

test('🔴 hai dòng sổ cùng số tiền, cùng nhà cung cấp: một lượt trừ chỉ ăn MỘT dòng', () => {
  const recs = soUNC([
    { noiDung: 'Tiền thuê tháng 8', tien: 30000000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
    { noiDung: 'Tiền thuê tháng 9', tien: 30000000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 30000000, nd: 'CT tien thue', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.tom.chac + kq.tom.kha + kq.tom.ngo, 1, 'chỉ một cặp được dựng');
  assert.strictEqual(kq.tom.dongLe, 1, 'dòng còn lại vẫn là khoản chưa đi');
});

test('🔴 nhiều ứng viên cho cùng một lượt trừ thì KHÔNG tự bật, dù khớp cả số tài khoản', () => {
  const recs = soUNC([
    { noiDung: 'Tiền thuê tháng 8', tien: 30000000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
    { noiDung: 'Tiền thuê tháng 9', tien: 30000000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 30000000, nd: 'CT tien thue', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.capChac.length, 0, 'hai khoản giống hệt thì máy không được chọn hộ');
  assert.strictEqual(kq.capKha.length, 1);
  assert.strictEqual(kq.capKha[0].soUngVien, 2);
});

test('hai lượt trừ, hai dòng sổ: khớp đủ cả hai', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng A', tien: 10000000, han: '02/09/2026', thuHuong: 'CTY A', stk: '111' },
    { noiDung: 'Tiền hàng B', tien: 20000000, han: '02/09/2026', thuHuong: 'CTY B', stk: '222' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '04/09/2026', no: 20000000, nd: 'CT B', tk: '222' },
    { ngay: '05/09/2026', no: 10000000, nd: 'CT A', tk: '111' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.capChac.length, 2);
  assert.strictEqual(kq.tom.gdLe, 0);
  assert.strictEqual(kq.tom.dongLe, 0);
});

test('🔴 cùng một bộ dữ liệu thì cho CÙNG một kết quả, không phụ thuộc may rủi', () => {
  const recs = soUNC([
    { noiDung: 'A', tien: 5000000, han: '02/09/2026', thuHuong: 'CTY A', stk: '111' },
    { noiDung: 'B', tien: 5000000, han: '02/09/2026', thuHuong: 'CTY A', stk: '111' },
    { noiDung: 'C', tien: 5000000, han: '02/09/2026', thuHuong: 'CTY A', stk: '111' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '04/09/2026', no: 5000000, nd: 'CT', tk: '111' },
    { ngay: '05/09/2026', no: 5000000, nd: 'CT', tk: '111' },
  ]), { nguon: 'VCB' }).gd;
  const a = S.doiChieu(dong, gd, {});
  const b = S.doiChieu(dong, gd, {});
  assert.deepStrictEqual(a.tom, b.tom);
});

/* ===================== Không đè lên tay người ===================== */

nhom('Không đè lên thứ kế toán đã đánh');

test('🔴 khoản kế toán đánh "huỷ" mà ngân hàng vẫn trừ: bày ra thành LỆCH, không tự bật', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 25500000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const td = {};
  td[recs[0].id] = { trangThai: 'huy', ghiChu: 'nhà cung cấp báo huỷ đơn' };
  const dong = E.dungDong(recs, td, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25500000, nd: 'CT', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.capChac.length, 0);
  assert.strictEqual(kq.lech.length, 1);
  assert.ok(/Kế toán đã đánh/.test(kq.lech[0].viSao));
});

test('khoản kế toán đã đánh "đã đi" thì khớp lại cũng không ghi đè ngày cũ', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 25500000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const td = {};
  td[recs[0].id] = { trangThai: 'da_di', ngayDi: '2026-09-03', ghiChu: 'kế toán xác nhận' };
  const dong = E.dungDong(recs, td, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25500000, nd: 'CT', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  /* 🔴 KHÔNG NẰM Ở "KHỚP CHẮC" NỮA — việc ấy xong rồi, nhóm chờ-làm phải VƠI ĐI sau khi
     bấm áp dụng. Để nó ở lại thì bấm nút xong nhìn vẫn thấy đủ ngần ấy dòng, và người ta
     bấm lần nữa. Nhưng cũng KHÔNG mất hẳn: "sổ ghi đã đi và ngân hàng có lượt trừ đúng
     khoản ấy" chính là câu trả lời của cả buổi ngồi đối chiếu. */
  assert.strictEqual(kq.capChac.length, 0, 'không còn là việc phải làm');
  assert.strictEqual(kq.capXong.length, 1, 'nhưng vẫn kể ra là đã khớp');
  assert.strictEqual(kq.tom.xong, 1);
  const capNhat = S.apDung(kq.capXong, { luc: '2026-09-30T00:00:00Z' });
  assert.deepStrictEqual(capNhat, {}, 'và áp lại cũng không sinh ra lượt cập nhật nào');
});

test('🔴 áp dụng xong thì nhóm "khớp chắc" VƠI ĐI, không bấm lại được vô ích', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 25500000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25500000, nd: 'CT', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const truoc = S.doiChieu(E.dungDong(recs, {}, { homNay: HOM_NAY }), gd, {});
  assert.strictEqual(truoc.tom.chac, 1);
  const cn = S.apDung(truoc.capChac, { luc: '2026-09-30T00:00:00Z' });
  const sau = S.doiChieu(E.dungDong(recs, cn, { homNay: HOM_NAY }), gd, {});
  assert.strictEqual(sau.tom.chac, 0, 'hết việc phải làm');
  assert.strictEqual(sau.tom.xong, 1, 'và chuyển sang nhóm đã khớp');
  assert.strictEqual(sau.tom.gdLe, 0);
  assert.strictEqual(sau.tom.dongLe, 0);
});

test('dòng ghi chú (nộp tiền mặt / cấn trừ) không vào đối chiếu', () => {
  const recs = I.docSheets([{
    ten: 'THÁNG 9.2026',
    rows: [
      ['BP', 'Ngày tạo lệnh', 'Nội dung trên đề xuất', 'Số tiền', 'Tên đơn vị thụ hưởng',
        'Tài khoản người thụ hưởng', 'Ngân hàng người thụ hưởng'],
      ['', '', '', 5000000, 'Nộp thừa 25/08', '', ''],
    ],
  }]).recs;
  assert.strictEqual(recs[0].loai, 'ghichu', 'cảnh dựng: đúng là dòng ghi chú');
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([{ ngay: '05/09/2026', no: 5000000, nd: 'CT' }]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.tom.chac + kq.tom.kha + kq.tom.ngo, 0);
  assert.strictEqual(kq.tom.gdLe, 1);
});

/* ===================== Hai chiều lệch ===================== */

nhom('Hai chiều lệch');

test('🔴 ngân hàng trừ mà sổ KHÔNG có dòng nào — kể ra, kèm tổng tiền', () => {
  const dong = E.dungDong(soUNC([
    { noiDung: 'Tiền hàng', tien: 10000000, han: '02/09/2026', thuHuong: 'CTY A', stk: '111' },
  ]), {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '04/09/2026', no: 10000000, nd: 'CT A', tk: '111' },
    { ngay: '06/09/2026', no: 7777777, nd: 'CT khong ro', tk: '999' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.gdKhongKhop.length, 1);
  assert.strictEqual(kq.tom.tienGDLe, 7777777);
});

test('sổ có khoản chưa đi mà sao kê không có lượt trừ nào — cũng phải kể ra', () => {
  const dong = E.dungDong(soUNC([
    { noiDung: 'Tiền hàng', tien: 10000000, han: '02/09/2026', thuHuong: 'CTY A', stk: '111' },
    { noiDung: 'Tiền điện', tien: 3000000, han: '02/09/2026', thuHuong: 'EVN', stk: '222' },
  ]), {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([{ ngay: '04/09/2026', no: 10000000, nd: 'CT A', tk: '111' }]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  assert.strictEqual(kq.dongChuaDi.length, 1);
  assert.strictEqual(kq.dongChuaDi[0].rec.noiDung, 'Tiền điện');
});

/* ===================== Áp vào sổ theo dõi ===================== */

nhom('Áp kết quả vào sổ');

test('🔴 áp xong thì trạng thái là "đã đi", ngày lấy từ NGÂN HÀNG', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 25500000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', ma: 'FT26248', no: 25500000, nd: 'CT', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const kq = S.doiChieu(dong, gd, {});
  const cn = S.apDung(kq.capChac, { nguoi: 'thu', luc: '2026-09-30T00:00:00Z' });
  const td = cn[recs[0].id];
  assert.strictEqual(td.trangThai, 'da_di');
  assert.strictEqual(td.ngayDi, '2026-09-05');
  assert.strictEqual(td.maGD, 'FT26248');
  assert.strictEqual(td.nguonDoiChieu, 'saoke');
});

test('🔴 áp xong, dựng lại sổ thì khoản ấy hết nằm trong công nợ', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 25500000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const dong = E.dungDong(recs, {}, { homNay: HOM_NAY });
  assert.strictEqual(E.congDon(dong).conNo, 25500000, 'cảnh dựng: đang là khoản phải trả');
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25500000, nd: 'CT', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const cn = S.apDung(S.doiChieu(dong, gd, {}).capChac, { luc: '2026-09-30T00:00:00Z' });
  const sau = E.dungDong(recs, cn, { homNay: HOM_NAY });
  assert.strictEqual(E.congDon(sau).conNo, 0);
  assert.strictEqual(E.congDon(sau).daChi, 25500000);
});

test('🔴 ghi chú cũ của kế toán KHÔNG bị đè, chỉ nối thêm', () => {
  const recs = soUNC([
    { noiDung: 'Tiền hàng', tien: 25500000, han: '02/09/2026', thuHuong: 'CTY ABC', stk: '0901234567' },
  ]);
  const td = {};
  td[recs[0].id] = { ghiChu: 'chờ hoá đơn đỏ' };
  const dong = E.dungDong(recs, td, { homNay: HOM_NAY });
  const gd = S.docSheet(saoKeHaiCot([
    { ngay: '05/09/2026', no: 25500000, nd: 'CT', tk: '0901234567' },
  ]), { nguon: 'VCB' }).gd;
  const cn = S.apDung(S.doiChieu(dong, gd, {}).capChac, { luc: '2026-09-30T00:00:00Z' });
  assert.ok(/chờ hoá đơn đỏ/.test(cn[recs[0].id].ghiChu));
  assert.ok(/Khớp sao kê/.test(cn[recs[0].id].ghiChu));
});

test('nối ghi chú hai lần không nhân đôi câu', () => {
  const a = S.ghiChuGop('', 'Khớp sao kê VCB');
  assert.strictEqual(S.ghiChuGop(a, 'Khớp sao kê VCB'), 'Khớp sao kê VCB');
});

test('sổ trống hoặc sao kê trống thì không nổ, chỉ ra kết quả rỗng', () => {
  assert.strictEqual(S.doiChieu([], [], {}).tom.chac, 0);
  assert.strictEqual(S.doiChieu(null, null, {}).tom.soGD, 0);
  assert.deepStrictEqual(S.apDung(null, {}), {});
});

console.log('\n' + soTest + ' bài kiểm thử.' + (process.exitCode ? ' CÓ LỖI.' : ' Tất cả đạt.'));
