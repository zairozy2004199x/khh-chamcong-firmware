/*
 * exporter.js — Xuất Excel: danh sách UNC kèm trạng thái, công nợ nhà cung cấp,
 * bảng tuổi nợ, tổng hợp theo kỳ / bộ phận và danh sách cảnh báo.
 *
 * Dùng SheetJS đã kèm sẵn trong vendor/ (không gọi mạng).
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(require('./engine.js'));
  else root.UNCExporter = factory(root.UNCEngine);
})(typeof self !== 'undefined' ? self : this, function (E) {
  'use strict';

  const NHAN_TK = { cu: 'K&H cũ', moi: 'K&H mới', smart: 'Smart', '': '(chưa ghi)' };
  const NHAN_LOAI = { unc: 'Ủy nhiệm chi', thue: 'Thuê mặt bằng', tienmat: 'Tiền mặt', ghichu: 'Ghi chú nộp/cấn trừ' };

  function rong(ws, cols) {
    ws['!cols'] = cols.map((w) => ({ wch: w }));
    return ws;
  }

  /** Bảng chi tiết — mỗi dòng một khoản. */
  function toDanhSach(XLSX, dong) {
    const aoa = [[
      'Kỳ', 'Loại', 'Bộ phận', 'Khoản mục', 'Nội dung', 'Số tiền',
      'Đơn vị thụ hưởng', 'Số TK thụ hưởng', 'Ngân hàng thụ hưởng', 'TK chi',
      'Hạn đi tiền', 'Ngày đi thực tế', 'Trạng thái', 'Trễ (ngày)', 'Số UNC',
      'Người cập nhật', 'Ghi chú theo dõi', 'Ghi chú trong file', 'Nguồn (sheet)', 'Dòng',
    ]];
    dong.forEach((x) => {
      const r = x.rec;
      aoa.push([
        r.ky, NHAN_LOAI[r.loai] || r.loai, r.bp, r.khoanMuc, r.noiDung, r.soTien,
        r.thuHuong, r.soTaiKhoan, r.nganHangTH, NHAN_TK[r.taiKhoanCty] || '',
        E.dinhDangNgay(x.han), E.dinhDangNgay(x.ngayDi), x.nhanTrangThai,
        x.quaHan ? x.treNgay : '', (x.td && x.td.soUNC) || '',
        (x.td && x.td.nguoiCapNhat) || '', (x.td && x.td.ghiChu) || '',
        r.ghiChu, r.nguon, r.dong,
      ]);
    });
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: aoa.length - 1, c: 19 } }) };
    ws['!freeze'] = { xSplit: 0, ySplit: 1 };
    return rong(ws, [9, 15, 14, 14, 46, 15, 34, 18, 28, 10, 12, 13, 14, 9, 12, 14, 24, 20, 20, 7]);
  }

  /** Công nợ phải trả theo nhà cung cấp, chia cột tuổi nợ. */
  function toCongNo(XLSX, ncc) {
    const cot = E.MOC_TUOI_NO;
    const aoa = [[
      'Nhà cung cấp', 'Số khoản', 'Tổng phát sinh', 'Đã thanh toán', 'Còn phải trả',
    ].concat(cot.map((m) => m.label)).concat(['Không rõ hạn', 'Số khoản quá hạn', 'Trễ nhất (ngày)', 'Tài khoản'])];
    ncc.forEach((o) => {
      aoa.push([
        o.nhan, o.soDong, o.tongTien, o.daChi, o.conNo,
      ].concat(cot.map((m) => o.tuoi[m.key] || 0))
        .concat([o.tuoi.khongHan || 0, o.quaHan, o.treNhatNgay == null ? '' : o.treNhatNgay, (o.taiKhoan || []).join(' · ')]));
    });
    const n = aoa.length;
    aoa.push(['TỔNG CỘNG', '', '', '', '']);
    for (let c = 1; c <= 4 + cot.length + 2; c++) {
      if (c === 1) continue;
      const col = XLSX.utils.encode_col(c);
      aoa[n][c] = { f: 'SUM(' + col + '2:' + col + n + ')' };
    }
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: n - 1, c: aoa[0].length - 1 } }) };
    return rong(ws, [44, 9, 16, 16, 16].concat(cot.map(() => 17)).concat([15, 9, 12, 40]));
  }

  function toGom(XLSX, ds, tenCot) {
    const aoa = [[tenCot, 'Số khoản', 'Tổng phát sinh', 'Đã thanh toán', 'Còn phải trả', 'Quá hạn (khoản)', 'Tiền quá hạn']];
    ds.forEach((o) => aoa.push([o.nhan, o.soDong, o.tongTien, o.daChi, o.conNo, o.quaHan, o.tienQuaHan]));
    return rong(XLSX.utils.aoa_to_sheet(aoa), [26, 10, 17, 17, 17, 14, 17]);
  }

  function toTuoiNo(XLSX, tuoi) {
    const aoa = [['Nhóm tuổi nợ', 'Số tiền còn phải trả', 'Tỷ trọng']];
    const tong = tuoi.tong || 0;
    E.MOC_TUOI_NO.forEach((m) => {
      const v = tuoi[m.key] || 0;
      aoa.push([m.label, v, tong ? v / tong : 0]);
    });
    aoa.push(['Không rõ hạn thanh toán', tuoi.khongHan || 0, tong ? (tuoi.khongHan || 0) / tong : 0]);
    aoa.push(['TỔNG CỘNG', tong, tong ? 1 : 0]);
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    for (let r = 1; r < aoa.length; r++) {
      const c = ws['C' + (r + 1)];
      if (c) c.z = '0.0%';
    }
    return rong(ws, [30, 20, 10]);
  }

  function toCanhBao(XLSX, ds) {
    const nhan = { loi: 'Nghiêm trọng', canh: 'Cảnh báo', tin: 'Lưu ý' };
    const aoa = [['Mức', 'Loại', 'Nội dung']];
    const loai = { qua_han: 'Quá hạn', sap_han: 'Sắp đến hạn', trung_chi: 'Nghi trùng chi', thieu_tt: 'Thiếu thông tin' };
    ds.forEach((c) => aoa.push([nhan[c.muc] || c.muc, loai[c.loai] || c.loai, c.text]));
    return rong(XLSX.utils.aoa_to_sheet(aoa), [14, 18, 110]);
  }

  /**
   * @param XLSX   thư viện SheetJS
   * @param dong   mảng dòng đã tính trạng thái (E.dungDong)
   * @param ctx    {tenFile, moTa}
   */
  function xuatExcel(XLSX, dong, ctx) {
    const c = ctx || {};
    const wb = XLSX.utils.book_new();
    const khongGhiChu = dong.filter((x) => x.rec.loai !== 'ghichu');

    XLSX.utils.book_append_sheet(wb, toDanhSach(XLSX, dong), 'Ủy nhiệm chi');
    XLSX.utils.book_append_sheet(wb, toCongNo(XLSX, E.congNoNCC(khongGhiChu)), 'Công nợ theo NCC');
    XLSX.utils.book_append_sheet(wb, toTuoiNo(XLSX, E.bangTuoiNo(khongGhiChu)), 'Tuổi nợ');
    XLSX.utils.book_append_sheet(wb, toGom(XLSX, E.theoKy(dong), 'Kỳ'), 'Theo kỳ');
    XLSX.utils.book_append_sheet(wb, toGom(XLSX, E.theoBP(dong), 'Bộ phận'), 'Theo bộ phận');
    XLSX.utils.book_append_sheet(wb, toGom(XLSX, E.theoTaiKhoanCty(dong), 'Tài khoản chi'), 'Theo tài khoản');
    XLSX.utils.book_append_sheet(wb, toCanhBao(XLSX, E.canhBao(khongGhiChu, { homNay: c.homNay })), 'Cảnh báo');

    const ten = c.tenFile || 'Theo_doi_uy_nhiem_chi_' + (E.homNayISO() || '').replace(/-/g, '') + '.xlsx';
    XLSX.writeFile(wb, ten);
    return ten;
  }

  return { xuatExcel, toDanhSach, toCongNo, toTuoiNo, toCanhBao };
});
