/*
 * Kiểm thử lõi: chạy `node test/engine.test.js`, không cần cài gì thêm.
 *
 * Tập trung vào những chỗ dữ liệu thật hay làm sai:
 *   - ngày tháng ghi bằng chữ ("UNC 1/9", "trước 14/12", "30-31/12")
 *   - số tiền có dấu phân nhóm, hai khoản trong một ô, ô ngày lọt vào cột tiền
 *   - tên ngân hàng viết 16 kiểu cho đúng 2 tài khoản
 *   - dòng "CÔNG TY …" KHÔNG được nhận nhầm là dòng "Cộng"
 *   - khoá dòng phải ổn định để nhập lại file không mất trạng thái kế toán
 */
const assert = require('assert');
const E = require('../engine.js');
const I = require('../importer.js');
const M = require('../mau.js');

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

/* ===================== Số tiền ===================== */

nhom('Đọc số tiền');
test('số thường và số có dấu phân nhóm', () => {
  assert.strictEqual(E.docTien(1836000), 1836000);
  assert.strictEqual(E.docTien('25,500,000'), 25500000);
  assert.strictEqual(E.docTien('25.500.000'), 25500000);
  assert.strictEqual(E.docTien('24557500.0'), 24557500);
  assert.strictEqual(E.docTien(' 1 836 000 '), 1836000);
});
test('hai khoản trong một ô thì cộng lại, không nối thành số khổng lồ', () => {
  assert.strictEqual(E.docTien('33,000,000\n11,000,000'), 44000000);
  assert.strictEqual(E.docTien('5000000+2000000'), 7000000);
});
test('ô ngày tháng lọt vào cột tiền thì bỏ qua', () => {
  assert.strictEqual(E.docTien('2025-11-08T00:00:00'), 0);
  assert.strictEqual(E.docTien('2025-11-08 00:00:00'), 0);
});
test('ô rỗng / rác trả 0', () => {
  assert.strictEqual(E.docTien(null), 0);
  assert.strictEqual(E.docTien(''), 0);
  assert.strictEqual(E.docTien('x'), 0);
});

/* ===================== Ngày tháng ===================== */

nhom('Đọc ngày viết bằng chữ');
const ky9 = { thang: 9, nam: 2026 };
test('"UNC 1/9" và "PAYMENT 31/8"', () => {
  assert.strictEqual(E.docNgay('UNC 1/9', ky9).iso, '2026-09-01');
  assert.strictEqual(E.docNgay('PAYMENT 31/8', ky9).iso, '2026-08-31');
  assert.strictEqual(E.docNgay('payment 30/11', { thang: 11, nam: 2025 }).iso, '2025-11-30');
});
test('"trước 14/12" giữ cờ truoc', () => {
  const d = E.docNgay('trước 14/12', { thang: 12, nam: 2025 });
  assert.strictEqual(d.iso, '2025-12-14');
  assert.strictEqual(d.truoc, true);
});
test('khoảng ngày "30-31/12" lấy mốc cuối', () => {
  assert.strictEqual(E.docNgay('30-31/12', { thang: 12, nam: 2025 }).iso, '2025-12-31');
});
test('ngày ISO và đối tượng Date', () => {
  assert.strictEqual(E.docNgay('2025-11-08 00:00:00', ky9).iso, '2025-11-08');
  assert.strictEqual(E.docNgay(new Date(2026, 8, 21), ky9).iso, '2026-09-21');
});
test('sang năm mới thì suy ra đúng năm', () => {
  // Sheet tháng 1/2026 ghi "UNC 28/12" là tháng 12/2025.
  assert.strictEqual(E.docNgay('UNC 28/12', { thang: 1, nam: 2026 }).iso, '2025-12-28');
  // Sheet tháng 12/2025 ghi "UNC 5/1" là tháng 1/2026.
  assert.strictEqual(E.docNgay('UNC 5/1', { thang: 12, nam: 2025 }).iso, '2026-01-05');
});
test('ô rỗng không sinh ngày', () => {
  assert.strictEqual(E.docNgay('', ky9).iso, '');
  assert.strictEqual(E.docNgay(null, ky9).iso, '');
  assert.strictEqual(E.docNgay('chưa rõ', ky9).iso, '');
});

/* ===================== Chuẩn hoá ===================== */

nhom('Chuẩn hoá dữ liệu bẩn');
test('16 cách viết tên ngân hàng gom về 2 tài khoản', () => {
  ['K&H cũ', 'K và H cũ', 'K VÀ H CŨ', 'KH CŨ', 'K va H cu', 'K&H CŨ'].forEach((s) =>
    assert.strictEqual(E.docTaiKhoanCty(s), 'cu', s));
  ['K&H mới', 'K và H mới', 'K VÀ H MỚI', 'KH MỚI', 'K VA H MỚI', 'K&H Mới'].forEach((s) =>
    assert.strictEqual(E.docTaiKhoanCty(s), 'moi', s));
  assert.strictEqual(E.docTaiKhoanCty(''), '');
});
test('tách bộ phận khỏi khoản mục', () => {
  assert.deepStrictEqual(E.tachBP('FZ SC-gà rán'), { bp: 'FZ SC', khoanMuc: 'gà rán', bpGoc: 'FZ SC-gà rán' });
  assert.strictEqual(E.tachBP('posh').bp, 'POSH');
  assert.strictEqual(E.tachBP('  POSH  ').bp, 'POSH');
});
test('số tài khoản bị Excel làm bẩn', () => {
  assert.strictEqual(E.chuanSoTK("'VAE2001000359"), 'VAE2001000359');
  assert.strictEqual(E.chuanSoTK('\t0501000160370'), '0501000160370');
  assert.strictEqual(E.chuanSoTK('22789677.0'), '22789677');
});
test('gom tên nhà cung cấp bỏ loại hình doanh nghiệp', () => {
  assert.strictEqual(E.khoaNCC('CÔNG TY TNHH THÁI BÌNH AN'), 'THAI BINH AN');
  assert.strictEqual(E.khoaNCC('Công ty TNHH  Thái  Bình  An'), 'THAI BINH AN');
  assert.strictEqual(E.khoaNCC('CTY CP THÁI BÌNH AN'), 'THAI BINH AN');
});

/* ===================== Số tiền bằng chữ ===================== */

nhom('Số tiền bằng chữ (bắt buộc trên UNC)');
test('các mốc cơ bản', () => {
  assert.strictEqual(E.docSoThanhChu(0), 'Không đồng');
  assert.strictEqual(E.docSoThanhChu(1000000), 'Một triệu đồng');
  assert.strictEqual(E.docSoThanhChu(21), 'Hai mươi mốt đồng');
  assert.strictEqual(E.docSoThanhChu(15), 'Mười lăm đồng');
  assert.strictEqual(E.docSoThanhChu(101), 'Một trăm linh một đồng');
});
test('số lẻ giữa các nhóm đọc đủ "không trăm"', () => {
  assert.strictEqual(E.docSoThanhChu(5801001), 'Năm triệu tám trăm linh một nghìn không trăm linh một đồng');
});
test('hàng tỷ', () => {
  assert.strictEqual(E.docSoThanhChu(2000000000), 'Hai tỷ đồng');
});

/* ===================== Bộ đọc Excel ===================== */

nhom('Bộ đọc Excel');
const sheetMau = [{
  ten: 'THÁNG 9.2026',
  rows: [
    [],
    ['BP ', 'Ngày cần đi tiền', 'Ngày tạo lệnh', 'Nội dung trên đề xuất', 'Noi dung tren de xuat', 'Số tiền',
      'Tên đơn vị thụ hưởng', 'Tài khoản \nngười thụ hưởng', 'Ngân hàng người thụ hưởng', 'Ngân hàng', 'Note'],
    ['POSH', 'UNC 1/9', 'PAYMENT 9/9', 'TT tiền thuê T9', 'TT tien thue T9', 24557500,
      'CÔNG TY CỔ PHẦN ABC', '0011004455666', 'Vietcombank', 'K&H cũ', null],
    ['FZ VT-nước đá', 'UNC 9/9', null, 'TT tiền hàng hđ 1033', 'TT tien hang hd 1033', '2,970,000',
      'CÔNG TY TNHH THÁI BÌNH AN', '0900112233445', 'ABBank', 'K VÀ H MỚI', null],
    [],
    [null, null, null, null, null, 45200000, 'Nộp thừa ngày 12/9, cấn trừ kỳ sau'],
    [null, null, null, 'Tổng cộng', null, 999999999],
  ],
}];

test('đọc đúng số dòng, bỏ dòng "Tổng cộng"', () => {
  const kq = I.docSheets(sheetMau);
  assert.strictEqual(kq.recs.length, 3);
  assert.ok(!kq.recs.some((r) => r.soTien === 999999999), 'dòng Tổng cộng phải bị bỏ');
});
test('dòng bắt đầu bằng "CÔNG TY" KHÔNG bị nhận nhầm là dòng Cộng', () => {
  const kq = I.docSheets(sheetMau);
  assert.ok(kq.recs.some((r) => r.thuHuong === 'CÔNG TY CỔ PHẦN ABC'));
  assert.ok(kq.recs.some((r) => r.thuHuong === 'CÔNG TY TNHH THÁI BÌNH AN'));
});
test('ghi chú nộp / cấn trừ xếp riêng, không thành nhà cung cấp', () => {
  const kq = I.docSheets(sheetMau);
  const g = kq.recs.filter((r) => r.loai === 'ghichu');
  assert.strictEqual(g.length, 1);
  assert.strictEqual(g[0].thuHuong, '');
  assert.ok(g[0].noiDung.indexOf('Nộp thừa') >= 0);
});
test('chuẩn hoá đủ các trường', () => {
  const r = I.docSheets(sheetMau).recs[0];
  assert.strictEqual(r.ky, '2026-09');
  assert.strictEqual(r.taiKhoanCty, 'cu');
  assert.strictEqual(r.hanDi.iso, '2026-09-01');
  assert.strictEqual(r.ngayLenh.iso, '2026-09-09');
  assert.strictEqual(r.soTien, 24557500);
  const r2 = I.docSheets(sheetMau).recs[1];
  assert.strictEqual(r2.bp, 'FZ VT');
  assert.strictEqual(r2.khoanMuc, 'nước đá');
  assert.strictEqual(r2.taiKhoanCty, 'moi');
  assert.strictEqual(r2.soTien, 2970000);
});
test('sheet thiếu cột "Ngày cần đi tiền" (bản trước 11/2025) vẫn đọc đúng', () => {
  const kq = I.docSheets([{
    ten: 'Tháng 7.2025',
    rows: [
      [],
      ['BP ', 'Ngày tạo lệnh', 'Nội dung trên đề xuất', 'Noi dung tren de xuat', 'Số tiền',
        'Tên đơn vị thụ hưởng', 'Tài khoản \nngười thụ hưởng', 'Ngân hàng người thụ hưởng', 'Ngân hàng'],
      ['JP', 'PAYMENT 8/7', 'TT tiền thuê', 'TT tien thue', 11000000, 'CÔNG TY X', '123', 'ACB', 'KH CŨ'],
    ],
  }]);
  assert.strictEqual(kq.recs.length, 1);
  assert.strictEqual(kq.recs[0].hanDi.iso, '');
  assert.strictEqual(kq.recs[0].ngayLenh.iso, '2025-07-08');
  assert.strictEqual(kq.recs[0].ky, '2025-07');
});
test('sheet thuê mặt bằng tách mỗi cột tiền thành một khoản', () => {
  const kq = I.docSheets([{
    ten: 'Tháng 3.2025( Posh - JP)',
    rows: [
      [null, 'Bộ phận', 'Nội dung', 'Tài khoản', 'Tiền cọc /Tiền thuê ', 'Tiền điện',
        'Tiền phí dịch vụ hàng tháng', 'Tiền phí BH (nếu có)', 'K&H/K&H mới, smart', 'Note', 'Hạn thanh toán'],
      [null, 'POSH', 'Tiền thuê tháng 3', 'VP:429', 44000000, 1200000, null, null, 'K&H cũ', null, '5/3/2025'],
    ],
  }]);
  assert.strictEqual(kq.recs.length, 2, 'tiền thuê và tiền điện là 2 khoản');
  assert.deepStrictEqual(kq.recs.map((r) => r.khoanMuc).sort(), ['Tiền cọc / tiền thuê', 'Tiền điện']);
  assert.strictEqual(kq.recs[0].hanDi.iso, '2025-03-05');
  assert.strictEqual(kq.recs.reduce((a, r) => a + r.soTien, 0), 45200000);
});
test('sheet tiền mặt lấy được nội dung dù không có cột tên "Nội dung"', () => {
  const kq = I.docSheets([{
    ten: 'TT TIỀN MẶT',
    rows: [
      ['BP ', 'Người mua', 'CƠ SỞ', 'CƠ SỞ', 'Số tiền', 'Link hóa đơn', 'TT '],
      [null, 'THẮNG', 'FZ ADV SC', 'FZ ADV SC', 1836000, 'https://vi-du.invalid/1', null],
    ],
  }]);
  assert.strictEqual(kq.recs.length, 1);
  assert.strictEqual(kq.recs[0].loai, 'tienmat');
  assert.strictEqual(kq.recs[0].noiDung, 'FZ ADV SC');
  assert.strictEqual(kq.recs[0].nguoiMua, 'THẮNG');
});
test('khoá dòng ổn định giữa 2 lần nhập, dòng trùng hệt không đè nhau', () => {
  const a = I.docSheets(sheetMau).recs.map((r) => r.id);
  const b = I.docSheets(sheetMau).recs.map((r) => r.id);
  assert.deepStrictEqual(a, b, 'nhập lại phải ra đúng khoá cũ');
  const trung = I.docSheets([{
    ten: 'THÁNG 9.2026',
    rows: [
      ['BP ', 'Ngày tạo lệnh', 'Nội dung trên đề xuất', 'Noi dung tren de xuat', 'Số tiền', 'Tên đơn vị thụ hưởng'],
      ['POSH', null, 'TT tiền thuê', 'x', 1000000, 'CTY A'],
      ['POSH', null, 'TT tiền thuê', 'x', 1000000, 'CTY A'],
    ],
  }]).recs;
  assert.strictEqual(new Set(trung.map((r) => r.id)).size, 2, '2 dòng trùng phải có 2 khoá khác nhau');
});

/* ===================== Trạng thái & công nợ ===================== */

nhom('Trạng thái và công nợ');
const recs = I.docSheets(sheetMau).recs;

test('chưa đánh dấu thì suy ra từ file', () => {
  const d = E.dungDong(recs, {}, { lenhLaDaDi: false, homNay: '2026-09-21' });
  assert.strictEqual(d[0].trangThai, 'da_lenh', 'có Ngày tạo lệnh');
  assert.strictEqual(d[1].trangThai, 'chua_lenh', 'không có Ngày tạo lệnh');
});
test('tuỳ chọn "coi ngày tạo lệnh là đã đi tiền"', () => {
  const d = E.dungDong(recs, {}, { lenhLaDaDi: true, homNay: '2026-09-21' });
  assert.strictEqual(d[0].trangThai, 'da_di');
  assert.strictEqual(d[0].daChi, 24557500);
  assert.strictEqual(d[0].conNo, 0);
});
test('kế toán đánh dấu thì đè lên suy luận từ file', () => {
  const td = {};
  td[recs[0].id] = { trangThai: 'huy' };
  const d = E.dungDong(recs, td, { lenhLaDaDi: true, homNay: '2026-09-21' });
  assert.strictEqual(d[0].trangThai, 'huy');
  assert.strictEqual(d[0].conNo, 0, 'khoản huỷ không còn là công nợ');
  assert.strictEqual(d[0].daChi, 0, 'khoản huỷ không phải đã chi');
});
test('quá hạn tính theo hạn đi tiền', () => {
  const d = E.dungDong(recs, {}, { lenhLaDaDi: false, homNay: '2026-09-21' });
  assert.strictEqual(d[1].quaHan, true);
  assert.strictEqual(d[1].treNgay, 12, 'hạn 9/9 so với 21/9');
});
test('khoản đã đi tiền thì không còn quá hạn', () => {
  const td = {};
  td[recs[1].id] = { trangThai: 'da_di', ngayDi: '2026-09-10' };
  const d = E.dungDong(recs, td, { homNay: '2026-09-21' });
  assert.strictEqual(d[1].quaHan, false);
});
test('bảo toàn tổng: đã chi + còn nợ = tổng phát sinh', () => {
  const td = {};
  td[recs[1].id] = { trangThai: 'da_di', ngayDi: '2026-09-10' };
  const d = E.dungDong(recs, td, { lenhLaDaDi: true, homNay: '2026-09-21' });
  const t = E.congDon(d);
  assert.strictEqual(t.daChi + t.conNo, t.tongTien);
});
test('công nợ gom theo nhà cung cấp, bỏ dòng ghi chú', () => {
  const d = E.dungDong(recs.filter((r) => r.loai !== 'ghichu'), {}, { lenhLaDaDi: false, homNay: '2026-09-21' });
  const ncc = E.congNoNCC(d);
  assert.strictEqual(ncc.length, 2);
  assert.strictEqual(ncc.reduce((a, o) => a + o.conNo, 0), 24557500 + 2970000);
  assert.ok(ncc.every((o) => o.nhan.indexOf('Nộp thừa') < 0), 'ghi chú không được thành nhà cung cấp');
});
test('tuổi nợ rơi đúng nhóm', () => {
  const d = E.dungDong(recs.filter((r) => r.loai !== 'ghichu'), {}, { lenhLaDaDi: false, homNay: '2026-09-21' });
  const t = E.bangTuoiNo(d);
  // hạn 1/9 (trễ 20 ngày) và 9/9 (trễ 12 ngày) → đều nhóm 1–30.
  assert.strictEqual(t.qh1_30, 24557500 + 2970000);
  assert.strictEqual(t.qh90, 0);
  assert.strictEqual(t.tong, 24557500 + 2970000);
});
test('lọc theo từ khoá không dấu vẫn ra', () => {
  const d = E.dungDong(recs, {}, {});
  assert.strictEqual(E.loc(d, { tuKhoa: 'thai binh an' }).length, 1);
  assert.strictEqual(E.loc(d, { tuKhoa: 'THÁI BÌNH AN' }).length, 1);
  assert.strictEqual(E.loc(d, { tuKhoa: 'khong co gi' }).length, 0);
});
test('lọc chỉ quá hạn và theo trạng thái', () => {
  const d = E.dungDong(recs, {}, { lenhLaDaDi: false, homNay: '2026-09-21' });
  assert.ok(E.loc(d, { chiQuaHan: true }).every((x) => x.quaHan));
  assert.ok(E.loc(d, { trangThai: ['chua_lenh'] }).every((x) => x.trangThai === 'chua_lenh'));
});

/* ===================== Cảnh báo ===================== */

nhom('Cảnh báo');
test('phát hiện nghi trùng chi', () => {
  const r = I.docSheets([{
    ten: 'THÁNG 9.2026',
    rows: [
      ['BP ', 'Ngày tạo lệnh', 'Nội dung trên đề xuất', 'Noi dung tren de xuat', 'Số tiền', 'Tên đơn vị thụ hưởng', 'Tài khoản \nngười thụ hưởng'],
      ['POSH', null, 'TT tiền thuê T9', 'x', 5000000, 'CTY A', '123456'],
      ['POSH', null, 'TT tiền thuê T9', 'x', 5000000, 'CTY A', '123456'],
    ],
  }]).recs;
  const cb = E.canhBao(E.dungDong(r, {}, {}), { homNay: '2026-09-21' });
  assert.ok(cb.some((c) => c.loai === 'trung_chi'), 'phải báo nghi trùng chi');
});
test('phát hiện thiếu thông tin chuyển khoản', () => {
  const d = E.dungDong(recs, {}, { lenhLaDaDi: false, homNay: '2026-09-21' });
  const cb = E.canhBao(d, { homNay: '2026-09-21' });
  assert.ok(cb.some((c) => c.loai === 'thieu_tt'));
});

/* ===================== Mẫu biểu ===================== */

nhom('Mẫu biểu');
const ctyTest = {
  ten: 'CÔNG TY TNHH THỬ NGHIỆM', diaChi: 'TP.HCM', mst: '0300000000',
  daiDien: 'Nguyễn Văn A', chucVu: 'Giám đốc',
  taiKhoan: [{ loai: 'cu', so: '111', nganHang: 'VCB' }, { loai: 'moi', so: '222', nganHang: 'TCB' }],
};
test('mọi mẫu đều dựng ra HTML, không văng lỗi', () => {
  const d = E.dungDong(recs, {}, { homNay: '2026-09-21' })[0];
  const ncc = E.congNoNCC(E.dungDong(recs.filter((r) => r.loai !== 'ghichu'), {}, {}))[0];
  M.DS_MAU.forEach((m) => {
    const html = M.dung(m.ma, {
      dong: d, ncc: ncc, dongCuaNCC: [d], dsLoc: [d], congTy: ctyTest, nguoiLap: 'Kế toán',
    });
    assert.ok(html.indexOf('<section class="giay">') >= 0, m.ma + ' phải ra tờ giấy');
    assert.ok(html.indexOf('class="empty"') < 0, m.ma + ' không được rỗng');
  });
});
test('ủy nhiệm chi in 2 liên và có số tiền bằng chữ', () => {
  const d = E.dungDong(recs, {}, {})[0];
  const html = M.dung('unc', { dong: d, congTy: ctyTest });
  assert.strictEqual((html.match(/<section class="giay">/g) || []).length, 2);
  assert.ok(html.indexOf('Hai mươi tư triệu') >= 0 || html.indexOf('Hai mươi bốn triệu') >= 0);
  assert.ok(html.indexOf('Liên 1') >= 0 && html.indexOf('Liên 2') >= 0);
});
test('mẫu thiếu ngữ cảnh thì báo rõ chứ không vỡ', () => {
  assert.ok(M.dung('doichieu', { congTy: ctyTest }).indexOf('class="empty"') >= 0);
  assert.ok(M.dung('unc', { congTy: ctyTest }).indexOf('class="empty"') >= 0);
});
test('HTML được thoát, không chèn được thẻ từ dữ liệu', () => {
  const xau = I.docSheets([{
    ten: 'THÁNG 9.2026',
    rows: [
      ['BP ', 'Ngày tạo lệnh', 'Nội dung trên đề xuất', 'Noi dung tren de xuat', 'Số tiền', 'Tên đơn vị thụ hưởng'],
      ['POSH', null, '<script>alert(1)</script>', '<script>alert(2)</script>', 1000, 'CTY <b>X</b>'],
    ],
  }]).recs;
  const d = E.dungDong(xau, {}, {})[0];
  const html = M.dung('unc', { dong: d, congTy: ctyTest }) + M.dung('denghi', { dong: d, congTy: ctyTest });
  assert.ok(html.indexOf('<script>') < 0, 'thẻ script phải bị thoát');
  assert.ok(html.indexOf('<b>X</b>') < 0, 'thẻ trong tên nhà cung cấp phải bị thoát');
  assert.ok(html.indexOf('&lt;script&gt;') >= 0, 'phải thấy bản đã thoát');
  assert.ok(html.indexOf('&lt;b&gt;X&lt;/b&gt;') >= 0);
});

console.log('\n' + soTest + ' bài kiểm thử.' + (process.exitCode ? ' CÓ LỖI.' : ' Tất cả đạt.'));
