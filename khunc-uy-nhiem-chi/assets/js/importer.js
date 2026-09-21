/*
 * importer.js — Đọc file Excel "Đi ủy nhiệm chi" thành danh sách dòng chuẩn hoá.
 *
 * File thật có 3 kiểu bố cục lẫn lộn trong cùng một workbook:
 *   A. Sheet tháng 2025–2026 : BP | Ngày cần đi tiền | Ngày tạo lệnh | Nội dung | Số tiền |
 *                              Đơn vị thụ hưởng | TK thụ hưởng | NH thụ hưởng | Ngân hàng
 *                              (bản trước 11/2025 KHÔNG có cột "Ngày cần đi tiền")
 *   B. Sheet thuê mặt bằng   : Bộ phận | Nội dung | Tài khoản | Tiền cọc/thuê | Tiền điện |
 *                              Phí dịch vụ | Phí BH | K&H cũ/mới | Note | Hạn thanh toán
 *   C. TT TIỀN MẶT           : BP | Người mua | CƠ SỞ | Số tiền | Link hóa đơn | TT
 *
 * Lõi docSheets() nhận [{ten, rows}] (rows = mảng các mảng ô) nên chạy được cả ở Node
 * để kiểm thử; docWorkbook() là lớp bọc mỏng cho SheetJS trong trình duyệt.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(require('./engine.js'));
  else root.UNCImporter = factory(root.UNCEngine);
})(typeof self !== 'undefined' ? self : this, function (E) {
  'use strict';

  /* ===================== Nhận diện cột ===================== */

  function chuanTieuDe(v) {
    return E.boDau(v == null ? '' : v)
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .replace(/[^a-z0-9 /&]+/g, '')
      .trim();
  }

  /** Tên cột → trường dữ liệu. So khớp CHÍNH XÁC trước, rồi mới "chứa". */
  const DONG_NGHIA = {
    bp: ['bp', 'bo phan', 'rental', 'ma bp', 'co so'],
    hanDi: ['ngay can di tien', 'han thanh toan', 'han tt', 'ngay den han', 'han'],
    ngayLenh: ['ngay tao lenh', 'ngay di tien', 'ngay thanh toan', 'ngay chuyen'],
    noiDung: ['noi dung tren de xuat', 'noi dung', 'noi dung thanh toan', 'noi dung chi'],
    noiDungUNC: ['noi dung tren unc', 'noi dung tren de xuat khong dau'],
    soTien: ['so tien', 'thanh tien', 'so tien chi'],
    thuHuong: ['ten don vi thu huong', 'don vi thu huong', 'nguoi thu huong', 'nha cung cap', 'ten nha cung cap'],
    soTaiKhoan: ['tai khoan nguoi thu huong', 'so tai khoan', 'stk', 'tk thu huong'],
    nganHangTH: ['ngan hang nguoi thu huong', 'ngan hang thu huong', 'ngan hang huong'],
    taiKhoanCty: ['ngan hang', 'k&h/k&h moi smart', 'k&hk&h moi smart', 'tai khoan', 'tai khoan chi'],
    ghiChu: ['note', 'ghi chu', 'tt', 'ghi chu them'],
    link: ['link hoa don', 'link', 'hoa don'],
    nguoiMua: ['nguoi mua', 'nguoi de nghi'],
  };

  /** Cột tiền của bố cục B — mỗi cột thành một dòng riêng để không mất chi tiết. */
  const COT_TIEN_THUE = [
    { khop: /^tien coc ?\/? ?tien thue$/, nhan: 'Tiền cọc / tiền thuê' },
    { khop: /^tien dien$/, nhan: 'Tiền điện' },
    { khop: /^tien phi dich vu hang thang$/, nhan: 'Phí dịch vụ hàng tháng' },
    { khop: /^tien phi bh ?\(neu co\)$/, nhan: 'Phí bảo hiểm' },
  ];

  /** Tìm dòng tiêu đề trong 8 dòng đầu — dòng khớp nhiều tên cột nhất. */
  function timDongTieuDe(rows) {
    let tot = { dong: -1, diem: 0, ban_do: null, cotTien: [] };
    const han = Math.min(rows.length, 8);
    for (let r = 0; r < han; r++) {
      const kq = doBanDo(rows[r] || []);
      if (kq.diem > tot.diem) tot = { dong: r, diem: kq.diem, ban_do: kq.ban_do, cotTien: kq.cotTien };
    }
    return tot.diem >= 2 ? tot : null;
  }

  function doBanDo(row) {
    const ban_do = {};
    const cotTien = [];
    let diem = 0;
    row.forEach((o, i) => {
      const h = chuanTieuDe(o);
      if (!h) return;
      for (const ct of COT_TIEN_THUE) {
        if (ct.khop.test(h)) {
          cotTien.push({ cot: i, nhan: ct.nhan });
          diem += 1;
          return;
        }
      }
      for (const truong in DONG_NGHIA) {
        if (ban_do[truong] != null) continue;
        if (DONG_NGHIA[truong].indexOf(h) >= 0) {
          ban_do[truong] = i;
          diem += 1;
          return;
        }
      }
    });
    // Sheet "TT TIỀN MẶT" không có cột tên "Nội dung" — cột mô tả là cột chữ
    // sát bên trái cột số tiền. Lấy cột đó làm nội dung để không mất cả sheet.
    if (ban_do.noiDung == null && ban_do.soTien != null) {
      const daDung = {};
      for (const k in ban_do) daDung[ban_do[k]] = true;
      for (let i = ban_do.soTien - 1; i >= 0; i--) {
        if (daDung[i]) continue;
        if (chuanTieuDe(row[i])) {
          ban_do.noiDung = i;
          diem += 1;
          break;
        }
      }
    }

    // Cột "Noi dung tren de xuat" (bản không dấu) đứng ngay sau cột có dấu.
    if (ban_do.noiDung != null && ban_do.noiDungUNC == null) {
      const ke = ban_do.noiDung + 1;
      const h = chuanTieuDe(row[ke]);
      if (h === 'noi dung tren de xuat' || h === 'ref') ban_do.noiDungUNC = ke;
    }
    return { diem, ban_do, cotTien };
  }

  /* ===================== Kỳ báo cáo từ tên sheet ===================== */

  /** "THÁNG 9.2026" → {thang:9, nam:2026}; "11.07.2027" → 7/2027; thiếu năm thì lấy namMacDinh. */
  function docKyTuTen(ten, namMacDinh) {
    const t = E.boDau(ten).toLowerCase().trim();
    let m = t.match(/thang\s*(\d{1,2})\s*[.\/]\s*(\d{2,4})/);
    if (m) return { thang: +m[1], nam: chuanNam(+m[2]) };
    m = t.match(/thang\s*(\d{1,2})\b/);
    if (m) return { thang: +m[1], nam: namMacDinh || new Date().getFullYear() };
    // Sheet đặt tên theo ngày: 11.07.2027, 8.7.2024, 21.06
    m = t.match(/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{2,4})$/);
    if (m) return { thang: +m[2], nam: chuanNam(+m[3]) };
    m = t.match(/^(\d{1,2})[.\/](\d{1,2})$/);
    if (m) return { thang: +m[2], nam: namMacDinh || new Date().getFullYear() };
    return null;
  }

  function chuanNam(n) {
    if (n < 100) return n + 2000;
    return n;
  }

  function maKy(ky) {
    return ky ? ky.nam + '-' + (ky.thang < 10 ? '0' : '') + ky.thang : '';
  }

  /* ===================== Bỏ dòng rác ===================== */

  // Phải khớp TRỌN ô: nếu chỉ khớp tiền tố thì mọi dòng "CÔNG TY …" đều bị nhận nhầm là dòng cộng.
  const RE_DONG_TONG = /^(tong|tong cong|cong|total|sum|tong tien|tong cong tien|tong chi|luy ke)\s*:?\s*$/;

  function laDongTong(row, ban_do) {
    const cac = [ban_do.bp, ban_do.noiDung, ban_do.thuHuong];
    for (const c of cac) {
      if (c == null) continue;
      const t = E.boDau(row[c]).toLowerCase().trim();
      if (t && RE_DONG_TONG.test(t)) return true;
    }
    return false;
  }

  /* ===================== Đọc một sheet ===================== */

  function docSheet(ten, rows, tuyChon) {
    const opt = tuyChon || {};
    const nhatKy = [];
    const recs = [];
    if (!rows || rows.length < 2) return { recs, nhatKy };

    const td = timDongTieuDe(rows);
    if (!td) {
      nhatKy.push({ muc: 'canh', text: 'Bỏ qua sheet "' + ten + '": không có dòng tiêu đề nhận ra được (sheet nháp / bố cục lạ).' });
      return { recs, nhatKy };
    }
    const bd = td.ban_do;
    const cotTien = td.cotTien;
    if (bd.soTien == null && !cotTien.length) {
      nhatKy.push({ muc: 'canh', text: 'Bỏ qua sheet "' + ten + '": không có cột số tiền.' });
      return { recs, nhatKy };
    }

    const ky = docKyTuTen(ten, opt.namMacDinh);
    const ma = maKy(ky);
    if (!ky) nhatKy.push({ muc: 'canh', text: 'Sheet "' + ten + '": không đoán được kỳ từ tên sheet, ngày trong sheet có thể lệch năm.' });

    const loai = bd.link != null || bd.nguoiMua != null ? 'tienmat' : cotTien.length ? 'thue' : 'unc';
    const lay = (row, c) => (c == null ? '' : row[c]);
    let boQua = 0;

    for (let r = td.dong + 1; r < rows.length; r++) {
      const row = rows[r] || [];
      if (!row.length) continue;
      if (laDongTong(row, bd)) continue;

      const dsTien = cotTien.length && bd.soTien == null
        ? cotTien.map((c) => ({ tien: E.docTien(row[c.cot]), nhan: c.nhan }))
        : [{ tien: E.docTien(lay(row, bd.soTien)), nhan: '' }];

      const bp = E.tachBP(lay(row, bd.bp));
      const noiDung = E.gonTrang(lay(row, bd.noiDung));
      const thuHuong = E.gonTrang(lay(row, bd.thuHuong));

      for (const t of dsTien) {
        if (!t.tien) continue;
        // Dòng chỉ có số tiền mà không có bất kỳ mô tả nào → rác.
        if (!noiDung && !thuHuong && !bp.bpGoc) {
          boQua += 1;
          continue;
        }
        const hanDi = E.docNgay(lay(row, bd.hanDi), ky);
        const ngayLenh = E.docNgay(lay(row, bd.ngayLenh), ky);

        // Kế toán hay ghi chú ngay vào cột "Tên đơn vị thụ hưởng": "Nộp thừa 25/08",
        // "cấn trừ với chị Vân", "Đã nộp tk cty trước đó". Đó là biến động tiền mặt /
        // bù trừ, KHÔNG phải một khoản phải trả nhà cung cấp — để lẫn vào là sai công nợ.
        const laGhiChu =
          loai === 'unc' && !noiDung && !bp.bpGoc &&
          !E.chuanSoTK(lay(row, bd.soTaiKhoan)) && !E.gonTrang(lay(row, bd.nganHangTH));

        const rec = {
          ky: ma,
          nguon: ten,
          dong: r + 1,
          loai: laGhiChu ? 'ghichu' : loai,
          bp: bp.bp,
          bpGoc: bp.bpGoc,
          khoanMuc: t.nhan || bp.khoanMuc,
          noiDung: laGhiChu ? thuHuong : noiDung || t.nhan,
          noiDungUNC: E.gonTrang(lay(row, bd.noiDungUNC)),
          soTien: t.tien,
          thuHuong: laGhiChu ? '' : thuHuong,
          thuHuongKhoa: laGhiChu ? '' : E.khoaNCC(thuHuong),
          soTaiKhoan: E.chuanSoTK(lay(row, bd.soTaiKhoan)),
          nganHangTH: E.gonTrang(lay(row, bd.nganHangTH)),
          taiKhoanCty: E.docTaiKhoanCty(lay(row, bd.taiKhoanCty)),
          taiKhoanChiGoc: E.gonTrang(lay(row, bd.taiKhoanCty)),
          hanDi: hanDi,
          ngayLenh: ngayLenh,
          ghiChu: E.gonTrang(lay(row, bd.ghiChu)),
          nguoiMua: E.gonTrang(lay(row, bd.nguoiMua)),
          link: E.gonTrang(lay(row, bd.link)),
        };
        // Nội dung không dấu trên UNC: file có sẵn thì dùng, không thì tự bỏ dấu.
        if (!rec.noiDungUNC || /^#REF/i.test(rec.noiDungUNC)) rec.noiDungUNC = E.boDau(rec.noiDung);
        rec.timKiem = E.boDau(
          [rec.bpGoc, rec.noiDung, rec.thuHuong, rec.soTaiKhoan, rec.nganHangTH, rec.ghiChu, rec.khoanMuc, Math.round(rec.soTien)].join(' ')
        ).toLowerCase();
        recs.push(rec);
      }
    }

    if (boQua) nhatKy.push({ muc: 'tin', text: 'Sheet "' + ten + '": bỏ ' + boQua + ' dòng chỉ có số tiền, không có nội dung.' });
    const soGhiChu = recs.filter((r) => r.loai === 'ghichu').length;
    if (soGhiChu)
      nhatKy.push({
        muc: 'tin',
        text: 'Sheet "' + ten + '": ' + soGhiChu + ' dòng là ghi chú nộp tiền / cấn trừ (xếp riêng, không tính vào công nợ nhà cung cấp).',
      });
    nhatKy.push({
      muc: 'ok',
      text: 'Sheet "' + ten + '" (' + (ma || 'không rõ kỳ') + '): đọc ' + recs.length + ' dòng, kiểu ' +
        (loai === 'unc' ? 'ủy nhiệm chi' : loai === 'thue' ? 'thuê mặt bằng' : 'tiền mặt') + '.',
    });
    return { recs, nhatKy };
  }

  /* ===================== Đọc cả workbook ===================== */

  /**
   * @param sheets [{ten, rows}]
   * @returns {recs, nhatKy, kyCo:[]}
   */
  function docSheets(sheets, tuyChon) {
    const recs = [];
    let nhatKy = [];
    (sheets || []).forEach((s) => {
      const kq = docSheet(s.ten, s.rows, tuyChon);
      recs.push.apply(recs, kq.recs);
      nhatKy = nhatKy.concat(kq.nhatKy);
    });
    E.ganKhoa(recs);
    const kyCo = Array.from(new Set(recs.map((r) => r.ky).filter(Boolean))).sort().reverse();
    nhatKy.unshift({
      muc: recs.length ? 'ok' : 'loi',
      text: 'Tổng cộng đọc được ' + recs.length + ' dòng, ' + kyCo.length + ' kỳ (' +
        (kyCo.length ? kyCo[kyCo.length - 1] + ' → ' + kyCo[0] : 'không có') + ').',
    });
    return { recs, nhatKy, kyCo };
  }

  /** Lớp bọc cho SheetJS (trình duyệt). */
  function docWorkbook(wb, XLSX, tuyChon) {
    const sheets = wb.SheetNames.map((ten) => ({
      ten: ten,
      rows: XLSX.utils.sheet_to_json(wb.Sheets[ten], { header: 1, raw: true, blankrows: true, defval: null }),
    }));
    return docSheets(sheets, tuyChon);
  }

  return { docSheets, docSheet, docWorkbook, docKyTuTen, timDongTieuDe, chuanTieuDe, maKy };
});
