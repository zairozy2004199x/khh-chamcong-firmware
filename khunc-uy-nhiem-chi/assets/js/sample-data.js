/*
 * sample-data.js — Dữ liệu mẫu để xem thử giao diện khi chưa có file Excel thật.
 *
 * TOÀN BỘ LÀ SỐ LIỆU BỊA: tên nhà cung cấp, số tài khoản, số tiền đều không có thật.
 * Bố cục sheet thì dựng đúng như file "Đi ủy nhiệm chi" ngoài đời để thử bộ đọc:
 * sheet tháng kiểu UNC (có và không có cột "Ngày cần đi tiền"), sheet thuê mặt bằng,
 * sheet tiền mặt, và cả vài dòng lỗi thường gặp (ô trống, ghi chú lọt vào cột thụ hưởng,
 * hai số tiền trong một ô, tên ngân hàng viết mỗi chỗ một kiểu).
 */
(function (root) {
  'use strict';

  const NCC = [
    ['CÔNG TY CỔ PHẦN THƯƠNG MẠI MINH KHANG', '0011004455666', 'Vietcombank – CN Quận 1'],
    ['CÔNG TY TNHH DỊCH VỤ BẢO VỆ AN TÍN', '19001234567890', 'Techcombank – CN Hội Sở'],
    ['CÔNG TY TNHH SẢN XUẤT BAO BÌ TRƯỜNG PHÁT', '060012345678', 'Sacombank – CN Bình Thạnh'],
    ['CÔNG TY CỔ PHẦN TRUNG TÂM THƯƠNG MẠI HOA SEN', '3300111222', 'Standard Chartered – CN TP.HCM'],
    ['CÔNG TY TNHH MTV VẬN TẢI ĐÔNG DƯƠNG', '0501000998877', 'Vietcombank – CN Tân Bình'],
    ['HỘ KINH DOANH NGUYỄN VĂN SÁU', '3146500011122', 'MB Bank'],
    ['CÔNG TY TNHH ĐIỆN LẠNH PHƯƠNG NAM', '7700222333444', 'Agribank – CN Kiên Giang'],
    ['CÔNG TY CỔ PHẦN ĐẦU TƯ XÂY DỰNG TÂN HƯNG', '072455123', 'VIB – CN Quận 10'],
  ];
  const BP = ['POSH', 'JP', 'FZ VT', 'FZ SC-nước đá', 'SNOW AMTP', 'TÀU GV', 'FARM PT', 'GHOST AMBD-thi công', 'TÀU ETL', 'PB AMBT'];
  const NH = ['K&H cũ', 'K và H cũ', 'K VÀ H CŨ', 'K&H mới', 'K và H mới', 'K VÀ H MỚI', 'KH MỚI'];
  const VIEC = [
    'TT tiền thuê mặt bằng', 'TT tiền điện', 'TT tiền hàng theo hđ', 'TT phí vận chuyển theo hđ',
    'TT phí bảo vệ ngoài giờ', 'TT phí thi công theo hđ', 'TT cọc thi công', 'TT phí dịch vụ tháng',
  ];

  /** Bộ sinh số giả lập, cố định hạt giống để mở lại vẫn ra đúng bộ số đó. */
  function hat(n) {
    let x = n;
    return () => {
      x = (x * 1103515245 + 12345) & 0x7fffffff;
      return x / 0x7fffffff;
    };
  }

  function sheetUNC(ten, thang, nam, soDong, coCotHan, giong) {
    const rnd = hat(giong);
    const rows = [[], coCotHan
      ? ['BP ', 'Ngày cần đi tiền', 'Ngày tạo lệnh', 'Nội dung trên đề xuất', 'Noi dung tren de xuat', 'Số tiền',
         'Tên đơn vị thụ hưởng', 'Tài khoản \nngười thụ hưởng', 'Ngân hàng người thụ hưởng', 'Ngân hàng', 'Note']
      : ['BP ', 'Ngày tạo lệnh', 'Nội dung trên đề xuất', 'Noi dung tren de xuat', 'Số tiền',
         'Tên đơn vị thụ hưởng', 'Tài khoản \nngười thụ hưởng', 'Ngân hàng người thụ hưởng', 'Ngân hàng', 'Note']];

    for (let i = 0; i < soDong; i++) {
      // Thỉnh thoảng chèn dòng trống — file thật đầy dòng trống ngăn cách.
      if (rnd() < 0.12) { rows.push([]); continue; }

      const ncc = NCC[Math.floor(rnd() * NCC.length)];
      const bp = BP[Math.floor(rnd() * BP.length)];
      const viec = VIEC[Math.floor(rnd() * VIEC.length)];
      const tien = Math.round((2 + rnd() * 160) * 1e6 / 1000) * 1000;
      const nd = 'CTY K và H ' + viec + ' ' + (viec.indexOf('hđ') > 0 ? Math.floor(1000 + rnd() * 9000) : 'T' + thang + '.' + nam) + ' ' + bp.split('-')[0];
      const ngayHan = 1 + Math.floor(rnd() * 27);
      const daDiTien = rnd() < 0.68;            // phần lớn đã có lệnh
      const hanTxt = 'UNC ' + ngayHan + '/' + thang;
      const lenhTxt = daDiTien ? 'PAYMENT ' + (1 + Math.floor(rnd() * 27)) + '/' + thang : null;

      // Một số ô tiền ghi 2 khoản xuống dòng như kế toán hay làm.
      const oTien = rnd() < 0.05
        ? tien.toLocaleString('en-US') + '\n' + Math.round(tien / 3).toLocaleString('en-US')
        : tien;

      const chung = [nd, nd.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D'),
        oTien, ncc[0], ncc[1], ncc[2], NH[Math.floor(rnd() * NH.length)], null];
      rows.push(coCotHan ? [bp, hanTxt, lenhTxt].concat(chung) : [bp, lenhTxt].concat(chung));
    }

    // Dòng ghi chú kế toán lọt vào cột thụ hưởng — phải được xếp riêng, không tính công nợ.
    rows.push(coCotHan
      ? [null, null, null, null, null, 45200000, 'Nộp thừa ngày ' + (10 + (thang % 10)) + '/' + thang + ', cấn trừ kỳ sau']
      : [null, null, null, null, 45200000, 'Nộp thừa ngày ' + (10 + (thang % 10)) + '/' + thang + ', cấn trừ kỳ sau']);
    // Dòng cộng cuối sheet — phải bị bỏ.
    rows.push(coCotHan ? [null, null, null, 'Tổng cộng', null, 0] : [null, null, 'Tổng cộng', null, 0]);
    return { ten, rows };
  }

  function sheetThue(ten, thang, nam, giong) {
    const rnd = hat(giong);
    const rows = [[null, 'Bộ phận', 'Nội dung', 'Tài khoản', 'Tiền cọc /Tiền thuê ', 'Tiền điện',
      'Tiền phí dịch vụ hàng tháng', 'Tiền phí BH (nếu có)', 'K&H/K&H mới, smart', 'Note', 'Hạn thanh toán']];
    for (let i = 0; i < 14; i++) {
      const bp = BP[Math.floor(rnd() * BP.length)];
      const thue = Math.round((8 + rnd() * 60) * 1e6 / 1000) * 1000;
      rows.push([
        null, bp, 'Tiền thuê tháng ' + thang + '/' + nam + ' ' + bp, 'VP:429',
        thue,
        rnd() < 0.6 ? Math.round(rnd() * 3e6 / 1000) * 1000 : null,
        rnd() < 0.4 ? Math.round(rnd() * 5e6 / 1000) * 1000 : null,
        null,
        rnd() < 0.5 ? 'K&H cũ' : 'K&H mới',
        rnd() < 0.15 ? 'chưa xin' : null,
        (1 + Math.floor(rnd() * 10)) + '/' + thang + '/' + nam,
      ]);
    }
    return { ten, rows };
  }

  function sheetTienMat(giong) {
    const rnd = hat(giong);
    const nguoi = ['THẮNG', 'SƠN', 'HIẾU', 'LAN'];
    const rows = [['BP ', 'Người mua', 'CƠ SỞ', 'CƠ SỞ', 'Số tiền', 'Link hóa đơn', 'TT ']];
    for (let i = 0; i < 12; i++) {
      const bp = BP[Math.floor(rnd() * BP.length)].split('-')[0];
      rows.push([null, nguoi[Math.floor(rnd() * nguoi.length)], bp, bp,
        Math.round((0.3 + rnd() * 3) * 1e6 / 1000) * 1000,
        rnd() < 0.8 ? 'https://vi-du.invalid/hoa-don/' + (10000 + Math.floor(rnd() * 89999)) : 'chưa có hóa đơn',
        null]);
    }
    return { ten: 'TT TIỀN MẶT', rows };
  }

  /** Trả về danh sách sheet đúng dạng UNCImporter.docSheets() nhận. */
  root.UNCSampleData = function () {
    const nay = new Date();
    const nam = nay.getFullYear();
    const thang = nay.getMonth() + 1;
    const truoc = (n) => {
      let t = thang - n, y = nam;
      while (t <= 0) { t += 12; y -= 1; }
      return { t, y };
    };
    const ds = [];
    for (let i = 0; i < 5; i++) {
      const k = truoc(i);
      ds.push(sheetUNC('THÁNG ' + k.t + '.' + k.y, k.t, k.y, 22 - i, i < 3, 7000 + i * 131));
    }
    const k5 = truoc(5), k6 = truoc(6);
    ds.push(sheetThue('Tháng ' + k5.t + '.' + k5.y + ' ( Posh - JP)', k5.t, k5.y, 9100));
    ds.push(sheetThue('Tháng ' + k6.t + '.' + k6.y + ' ( FZ - DIY - TÀU)', k6.t, k6.y, 9200));
    ds.push(sheetTienMat(9300));
    return ds;
  };
})(typeof self !== 'undefined' ? self : this);
