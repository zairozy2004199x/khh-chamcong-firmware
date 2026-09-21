/*
 * mau.js — Sinh các mẫu biểu kế toán, in thẳng ra A4 hoặc lưu PDF.
 *
 * Mẫu có trong này (theo Thông tư 200/2014/TT-BTC và 133/2016/TT-BTC, phần chứng từ
 * thanh toán — doanh nghiệp được tự thiết kế nhưng phải đủ các chỉ tiêu bắt buộc):
 *
 *   unc        Ủy nhiệm chi / Lệnh chuyển tiền     — nộp ngân hàng, in 2 liên
 *   denghi     Giấy đề nghị thanh toán             — Mẫu 05-TT
 *   phieuchi   Phiếu chi                           — Mẫu 02-TT (chi tiền mặt)
 *   doichieu   Biên bản đối chiếu công nợ          — chốt số với nhà cung cấp
 *   bangke     Bảng kê chi tiết công nợ phải trả   — kèm biên bản đối chiếu
 *   sotheodoi  Sổ chi tiết thanh toán với người bán— Mẫu S31-DN (TK 331)
 *   kehoach    Kế hoạch chi tiền trình ký          — danh sách UNC cần đi trong kỳ
 *
 * Mỗi hàm trả về chuỗi HTML một (hoặc nhiều) tờ `.giay` — dùng chung cho xem trước và in.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(require('./engine.js'));
  else root.UNCMau = factory(root.UNCEngine);
})(typeof self !== 'undefined' ? self : this, function (E) {
  'use strict';

  const DS_MAU = [
    { ma: 'unc', ten: 'Ủy nhiệm chi', maSo: 'Nộp ngân hàng — 2 liên', mo: 'Lệnh chuyển tiền gửi ngân hàng. Chọn 1 dòng ở tab Ủy nhiệm chi rồi in.', can: 'dong' },
    { ma: 'denghi', ten: 'Giấy đề nghị thanh toán', maSo: 'Mẫu 05-TT', mo: 'Bộ phận đề nghị, kế toán trưởng và giám đốc duyệt trước khi đi tiền.', can: 'dong' },
    { ma: 'phieuchi', ten: 'Phiếu chi', maSo: 'Mẫu 02-TT', mo: 'Chi tiền mặt tại quỹ. Dùng cho các khoản ở sheet TT TIỀN MẶT.', can: 'dong' },
    { ma: 'doichieu', ten: 'Biên bản đối chiếu công nợ', maSo: 'Chốt số với nhà cung cấp', mo: 'Chọn một nhà cung cấp ở tab Công nợ — tự điền số dư và bảng kê.', can: 'ncc' },
    { ma: 'bangke', ten: 'Bảng kê chi tiết công nợ phải trả', maSo: 'Kèm biên bản đối chiếu', mo: 'Liệt kê từng khoản còn nợ của nhà cung cấp đang chọn.', can: 'ncc' },
    { ma: 'sotheodoi', ten: 'Sổ chi tiết thanh toán với người bán', maSo: 'Mẫu S31-DN · TK 331', mo: 'Sổ chi tiết phát sinh và số dư theo từng nhà cung cấp.', can: 'ncc' },
    { ma: 'kehoach', ten: 'Kế hoạch chi tiền trình ký', maSo: 'Bảng trình duyệt', mo: 'Danh sách các khoản đang lọc, để sếp duyệt đi tiền.', can: 'loc' },
  ];

  /* ===================== Tiện ích ===================== */

  function h(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  const t = (n) => E.dinhDangTien(n, true);
  const d = (iso) => E.dinhDangNgay(iso) || '……/……/………';
  const dot = (v, rong) =>
    v ? h(v) : '<span class="dot"' + (rong ? ' style="min-width:' + rong + '"' : '') + '>&nbsp;</span>';

  /** "Ngày 21 tháng 9 năm 2026" — dòng ngày tháng trên chứng từ. */
  function ngayThangNam(iso) {
    const p = String(iso || E.homNayISO()).split('-');
    if (p.length !== 3) return '';
    return 'Ngày ' + +p[2] + ' tháng ' + +p[1] + ' năm ' + p[0];
  }

  function quocHieu() {
    return (
      '<div class="qh"><b>CỘNG HOÀ XÃ HỘI CHỦ NGHĨA VIỆT NAM</b>' +
      '<u>Độc lập – Tự do – Hạnh phúc</u></div>'
    );
  }

  /** Khối tên & địa chỉ đơn vị, góc trái chứng từ. */
  function dauDonVi(cty) {
    return (
      '<table class="phang"><tr><td style="width:60%">' +
      '<b>' + h(cty.ten || 'CÔNG TY …') + '</b><br>' +
      (cty.diaChi ? h(cty.diaChi) + '<br>' : '') +
      (cty.mst ? 'MST: ' + h(cty.mst) : '') +
      '</td><td style="width:40%">' + quocHieu() + '</td></tr></table>'
    );
  }

  /** Khối chữ ký cuối chứng từ. */
  function khoiKy(cot) {
    return (
      '<div class="ky">' +
      cot.map((c) =>
        '<div><b>' + h(c.chuc) + '</b><i>' + h(c.ghi || '(Ký, họ tên)') + '</i>' +
        '<div class="cho"></div>' + (c.ten ? h(c.ten) : '') + '</div>'
      ).join('') +
      '</div>'
    );
  }

  function taiKhoanCty(cty, loai) {
    const tk = (cty.taiKhoan || []).filter((x) => !loai || x.loai === loai);
    return tk.length ? tk[0] : (cty.taiKhoan || [])[0] || { so: '', nganHang: '' };
  }

  function giay(noiDung) {
    return '<section class="giay">' + noiDung + '</section>';
  }

  /* ===================== 1. Ủy nhiệm chi ===================== */

  function mauUNC(ctx) {
    const r = ctx.dong && ctx.dong.rec;
    if (!r) return trong('Chọn một dòng ở tab <b>Ủy nhiệm chi</b> rồi bấm lại mẫu này.');
    const cty = ctx.congTy;
    const tk = taiKhoanCty(cty, r.taiKhoanCty);
    const ngay = (ctx.dong.td && ctx.dong.td.ngayDi) || ctx.dong.han || E.homNayISO();
    const soCT = (ctx.dong.td && ctx.dong.td.soUNC) || '';
    const noiDung = r.noiDungUNC || E.boDau(r.noiDung);

    const mot = (lien) =>
      giay(
        '<table class="phang"><tr>' +
        '<td style="width:55%"><b>' + h(cty.ten || '') + '</b></td>' +
        '<td class="num" style="width:45%">Số: ' + dot(soCT, '30mm') + '<br>' +
        '<span class="chu">' + h(lien) + '</span></td>' +
        '</tr></table>' +
        '<h1>Ủy nhiệm chi</h1>' +
        '<div class="ct chu" style="margin-top:-3mm;margin-bottom:4mm">Payment order</div>' +
        '<div class="ngaythang">' + ngayThangNam(ngay) + '</div>' +
        '<table class="vien">' +
        '<tr><th style="width:34%" class="ct">ĐƠN VỊ TRẢ TIỀN</th><td>' + h(cty.ten || '') + '</td></tr>' +
        '<tr><th class="ct">Số tài khoản</th><td class="mono">' + dot(tk.so, '60mm') + '</td></tr>' +
        '<tr><th class="ct">Tại ngân hàng</th><td>' + dot(tk.nganHang, '60mm') + '</td></tr>' +
        '<tr><th class="ct">ĐƠN VỊ THỤ HƯỞNG</th><td><b>' + dot(r.thuHuong, '60mm') + '</b></td></tr>' +
        '<tr><th class="ct">Số tài khoản</th><td class="mono">' + dot(r.soTaiKhoan, '60mm') + '</td></tr>' +
        '<tr><th class="ct">Tại ngân hàng</th><td>' + dot(r.nganHangTH, '60mm') + '</td></tr>' +
        '<tr><th class="ct">Số tiền bằng số</th><td><b>' + t(r.soTien) + ' VND</b></td></tr>' +
        '<tr><th class="ct">Số tiền bằng chữ</th><td class="chu">' + h(E.docSoThanhChu(r.soTien)) + '</td></tr>' +
        '<tr><th class="ct">Nội dung</th><td>' + dot(noiDung, '60mm') + '</td></tr>' +
        '</table>' +
        '<table class="vien" style="margin-top:4mm"><tr>' +
        '<th class="ct" style="width:50%">ĐƠN VỊ TRẢ TIỀN</th>' +
        '<th class="ct">NGÂN HÀNG</th></tr>' +
        '<tr><td style="height:34mm;vertical-align:top">' +
        '<div class="doi" style="font-size:10.5pt"><span>Kế toán trưởng</span><span>Chủ tài khoản</span></div>' +
        '</td><td style="vertical-align:top">' +
        '<div class="doi" style="font-size:10.5pt"><span>Giao dịch viên</span><span>Kiểm soát</span></div>' +
        '</td></tr></table>' +
        '<div class="note">Ghi chú: ' + h(r.bpGoc || '') + (r.ky ? ' · kỳ ' + h(r.ky) : '') + '</div>'
      );

    return mot('Liên 1: Ngân hàng giữ') + mot('Liên 2: Đơn vị trả tiền giữ');
  }

  /* ===================== 2. Giấy đề nghị thanh toán (05-TT) ===================== */

  function mauDeNghi(ctx) {
    const r = ctx.dong && ctx.dong.rec;
    if (!r) return trong('Chọn một dòng ở tab <b>Ủy nhiệm chi</b> rồi bấm lại mẫu này.');
    const cty = ctx.congTy;
    const tk = taiKhoanCty(cty, r.taiKhoanCty);
    return giay(
      '<table class="phang"><tr>' +
      '<td style="width:62%"><b>' + h(cty.ten || '') + '</b><br>' +
      (cty.diaChi ? h(cty.diaChi) : '') + '</td>' +
      '<td class="num" style="width:38%"><b>Mẫu số 05-TT</b><br>' +
      '<span class="chu">(Ban hành theo Thông tư số 200/2014/TT-BTC<br>ngày 22/12/2014 của Bộ Tài chính)</span></td>' +
      '</tr></table>' +
      '<h1 style="margin-top:6mm">Giấy đề nghị thanh toán</h1>' +
      '<div class="ngaythang">' + ngayThangNam(ctx.ngayLap) + '</div>' +
      '<table class="phang">' +
      '<tr><td style="width:40mm">Kính gửi:</td><td><b>Ban Giám đốc ' + h(cty.ten || '') + '</b></td></tr>' +
      '<tr><td>Họ và tên người đề nghị:</td><td>' + dot(ctx.nguoiLap, '80mm') + '</td></tr>' +
      '<tr><td>Bộ phận:</td><td>' + dot(r.bpGoc || r.bp, '80mm') + '</td></tr>' +
      '<tr><td>Nội dung thanh toán:</td><td>' + dot(r.noiDung, '80mm') + '</td></tr>' +
      '<tr><td>Số tiền:</td><td><b>' + t(r.soTien) + ' VND</b></td></tr>' +
      '<tr><td>Bằng chữ:</td><td class="chu">' + h(E.docSoThanhChu(r.soTien)) + '</td></tr>' +
      '<tr><td>Hình thức:</td><td>' +
      (r.loai === 'tienmat' ? 'Tiền mặt tại quỹ' : 'Chuyển khoản') + '</td></tr>' +
      '<tr><td>Đơn vị thụ hưởng:</td><td>' + dot(r.thuHuong, '80mm') + '</td></tr>' +
      '<tr><td>Số tài khoản:</td><td class="mono">' + dot(r.soTaiKhoan, '60mm') +
      ' — ' + dot(r.nganHangTH, '60mm') + '</td></tr>' +
      '<tr><td>Tài khoản chi:</td><td>' + h(tk.so || '') + (tk.nganHang ? ' — ' + h(tk.nganHang) : '') + '</td></tr>' +
      '<tr><td>Hạn thanh toán:</td><td>' + d(ctx.dong.han) + '</td></tr>' +
      '</table>' +
      '<p style="margin-top:4mm">(Kèm theo <span class="dot">&nbsp;</span> chứng từ gốc: hợp đồng, hoá đơn, biên bản nghiệm thu …)</p>' +
      khoiKy([
        { chuc: 'Người đề nghị', ten: ctx.nguoiLap || '' },
        { chuc: 'Kế toán trưởng' },
        { chuc: 'Giám đốc' },
      ])
    );
  }

  /* ===================== 3. Phiếu chi (02-TT) ===================== */

  function mauPhieuChi(ctx) {
    const r = ctx.dong && ctx.dong.rec;
    if (!r) return trong('Chọn một dòng ở tab <b>Ủy nhiệm chi</b> rồi bấm lại mẫu này.');
    const cty = ctx.congTy;
    const ngay = (ctx.dong.td && ctx.dong.td.ngayDi) || ctx.dong.han || E.homNayISO();
    return giay(
      '<table class="phang"><tr>' +
      '<td style="width:60%"><b>' + h(cty.ten || '') + '</b><br>' +
      (cty.diaChi ? h(cty.diaChi) : '') + '</td>' +
      '<td class="num" style="width:40%"><b>Mẫu số 02-TT</b><br>' +
      '<span class="chu">(Ban hành theo Thông tư số 200/2014/TT-BTC<br>ngày 22/12/2014 của Bộ Tài chính)</span></td>' +
      '</tr></table>' +
      '<h1 style="margin-top:5mm">Phiếu chi</h1>' +
      '<div class="ct">' + ngayThangNam(ngay) + '</div>' +
      '<div class="ct" style="margin-bottom:4mm">Số: ' + dot((ctx.dong.td && ctx.dong.td.soUNC) || '', '25mm') +
      ' &nbsp;&nbsp; Nợ: <span class="dot" style="min-width:20mm">&nbsp;</span>' +
      ' &nbsp;&nbsp; Có: <span class="dot" style="min-width:20mm">&nbsp;</span></div>' +
      '<table class="phang">' +
      '<tr><td style="width:42mm">Họ tên người nhận tiền:</td><td>' + dot(r.thuHuong || r.nguoiMua, '80mm') + '</td></tr>' +
      '<tr><td>Địa chỉ / Bộ phận:</td><td>' + dot(r.bpGoc || r.bp, '80mm') + '</td></tr>' +
      '<tr><td>Lý do chi:</td><td>' + dot(r.noiDung, '80mm') + '</td></tr>' +
      '<tr><td>Số tiền:</td><td><b>' + t(r.soTien) + ' VND</b></td></tr>' +
      '<tr><td>Bằng chữ:</td><td class="chu">' + h(E.docSoThanhChu(r.soTien)) + '</td></tr>' +
      '<tr><td>Kèm theo:</td><td><span class="dot" style="min-width:20mm">&nbsp;</span> chứng từ gốc' +
      (r.link ? '<br><span class="chu" style="font-size:10pt">' + h(r.link) + '</span>' : '') + '</td></tr>' +
      '</table>' +
      khoiKy([
        { chuc: 'Giám đốc' },
        { chuc: 'Kế toán trưởng' },
        { chuc: 'Thủ quỹ' },
        { chuc: 'Người lập phiếu' },
        { chuc: 'Người nhận tiền' },
      ]) +
      '<div class="note">Đã nhận đủ số tiền (viết bằng chữ): <span class="dot" style="min-width:110mm">&nbsp;</span></div>'
    );
  }

  /* ===================== 4. Biên bản đối chiếu công nợ ===================== */

  function mauDoiChieu(ctx) {
    const n = ctx.ncc;
    if (!n) return trong('Chọn một nhà cung cấp ở tab <b>Công nợ</b> rồi bấm lại mẫu này.');
    const cty = ctx.congTy;
    const den = ctx.denNgay || E.homNayISO();
    const phatSinh = n.tongTien;
    const daTra = n.daChi;
    const con = n.conNo;

    return giay(
      quocHieu() +
      '<h1 style="margin-top:4mm">Biên bản đối chiếu công nợ</h1>' +
      '<div class="ct chu" style="margin-bottom:5mm">Số: ' + dot(ctx.soBienBan, '25mm') +
      ' &nbsp;·&nbsp; Đối chiếu đến ngày ' + d(den) + '</div>' +
      '<p>Hôm nay, ' + ngayThangNam(ctx.ngayLap).toLowerCase() + ', tại ' +
      dot(cty.diaChi, '70mm') + ', chúng tôi gồm:</p>' +
      '<p><b>BÊN A (Bên mua / bên phải trả):</b></p>' +
      '<table class="phang">' +
      '<tr><td style="width:34mm">Tên đơn vị:</td><td><b>' + h(cty.ten || '') + '</b></td></tr>' +
      '<tr><td>Địa chỉ:</td><td>' + dot(cty.diaChi, '80mm') + '</td></tr>' +
      '<tr><td>Mã số thuế:</td><td>' + dot(cty.mst, '50mm') + '</td></tr>' +
      '<tr><td>Đại diện:</td><td>' + dot(cty.daiDien, '50mm') + ' — Chức vụ: ' + dot(cty.chucVu, '40mm') + '</td></tr>' +
      '</table>' +
      '<p style="margin-top:3mm"><b>BÊN B (Bên bán / bên thụ hưởng):</b></p>' +
      '<table class="phang">' +
      '<tr><td style="width:34mm">Tên đơn vị:</td><td><b>' + h(n.nhan) + '</b></td></tr>' +
      '<tr><td>Địa chỉ:</td><td><span class="dot" style="min-width:80mm">&nbsp;</span></td></tr>' +
      '<tr><td>Mã số thuế:</td><td><span class="dot" style="min-width:50mm">&nbsp;</span></td></tr>' +
      '<tr><td>Số tài khoản:</td><td class="mono">' +
      (n.taiKhoan && n.taiKhoan.length ? h(n.taiKhoan.join(' · ')) : '<span class="dot" style="min-width:60mm">&nbsp;</span>') +
      '</td></tr>' +
      '<tr><td>Đại diện:</td><td><span class="dot" style="min-width:50mm">&nbsp;</span> — Chức vụ: <span class="dot" style="min-width:40mm">&nbsp;</span></td></tr>' +
      '</table>' +
      '<p style="margin-top:4mm">Hai bên cùng đối chiếu và xác nhận số liệu công nợ tính đến ngày ' +
      d(den) + ' như sau:</p>' +
      '<table class="vien khung" style="margin-top:2mm">' +
      '<tr><th style="width:12mm">STT</th><th>Nội dung</th><th style="width:40mm">Số tiền (VND)</th></tr>' +
      '<tr><td class="ct">1</td><td>Số dư đầu kỳ</td><td class="num">' + t(ctx.duDauKy || 0) + '</td></tr>' +
      '<tr><td class="ct">2</td><td>Phát sinh trong kỳ (Bên A phải trả Bên B)</td><td class="num">' + t(phatSinh) + '</td></tr>' +
      '<tr><td class="ct">3</td><td>Bên A đã thanh toán trong kỳ</td><td class="num">' + t(daTra) + '</td></tr>' +
      '<tr><td class="ct"><b>4</b></td><td><b>Số dư cuối kỳ — Bên A còn phải trả Bên B</b></td>' +
      '<td class="num"><b>' + t((ctx.duDauKy || 0) + con) + '</b></td></tr>' +
      '</table>' +
      '<p style="margin-top:2mm">Bằng chữ: <span class="chu">' +
      h(E.docSoThanhChu((ctx.duDauKy || 0) + con)) + '</span></p>' +
      '<p style="margin-top:3mm">Biên bản được lập thành 02 bản có giá trị pháp lý như nhau, mỗi bên giữ 01 bản ' +
      'làm căn cứ thanh toán và hạch toán. Nếu trong vòng 07 ngày kể từ ngày nhận biên bản mà Bên B không có ' +
      'ý kiến phản hồi thì số liệu trên được coi là đã được hai bên xác nhận.</p>' +
      khoiKy([
        { chuc: 'ĐẠI DIỆN BÊN A', ghi: '(Ký, ghi rõ họ tên, đóng dấu)', ten: cty.daiDien || '' },
        { chuc: 'ĐẠI DIỆN BÊN B', ghi: '(Ký, ghi rõ họ tên, đóng dấu)' },
      ])
    );
  }

  /* ===================== 5. Bảng kê chi tiết công nợ ===================== */

  function mauBangKe(ctx) {
    const n = ctx.ncc;
    if (!n) return trong('Chọn một nhà cung cấp ở tab <b>Công nợ</b> rồi bấm lại mẫu này.');
    const ds = (ctx.dongCuaNCC || []).slice().sort((a, b) => (a.han || '') < (b.han || '') ? -1 : 1);
    let tongPS = 0, tongTra = 0, tongCon = 0;
    const hang = ds.map((x, i) => {
      tongPS += x.rec.soTien;
      tongTra += x.daChi;
      tongCon += x.conNo;
      return (
        '<tr>' +
        '<td class="ct">' + (i + 1) + '</td>' +
        '<td class="ct">' + h(x.rec.ky) + '</td>' +
        '<td>' + h(x.rec.noiDung) + '</td>' +
        '<td class="ct">' + h(x.rec.bpGoc || x.rec.bp) + '</td>' +
        '<td class="ct">' + d(x.han) + '</td>' +
        '<td class="num">' + t(x.rec.soTien) + '</td>' +
        '<td class="num">' + (x.daChi ? t(x.daChi) : '') + '</td>' +
        '<td class="num">' + (x.conNo ? t(x.conNo) : '') + '</td>' +
        '<td class="ct">' + (x.quaHan ? 'Quá hạn ' + x.treNgay + 'n' : h(x.nhanTrangThai)) + '</td>' +
        '</tr>'
      );
    }).join('');

    return giay(
      dauDonVi(ctx.congTy) +
      '<h1 style="margin-top:4mm">Bảng kê chi tiết công nợ phải trả</h1>' +
      '<div class="ct" style="margin-bottom:4mm">Nhà cung cấp: <b>' + h(n.nhan) + '</b><br>' +
      '<span class="chu">Số liệu đến ngày ' + d(ctx.denNgay || E.homNayISO()) + '</span></div>' +
      '<table class="vien khung">' +
      '<tr><th style="width:10mm">STT</th><th style="width:18mm">Kỳ</th><th>Nội dung</th>' +
      '<th style="width:22mm">Bộ phận</th><th style="width:20mm">Hạn TT</th>' +
      '<th style="width:26mm">Phải trả</th><th style="width:26mm">Đã trả</th>' +
      '<th style="width:26mm">Còn nợ</th><th style="width:24mm">Tình trạng</th></tr>' +
      (hang || '<tr><td colspan="9" class="ct">(không có dòng nào)</td></tr>') +
      '<tr><td colspan="5" class="ct"><b>CỘNG</b></td>' +
      '<td class="num"><b>' + t(tongPS) + '</b></td>' +
      '<td class="num"><b>' + t(tongTra) + '</b></td>' +
      '<td class="num"><b>' + t(tongCon) + '</b></td><td></td></tr>' +
      '</table>' +
      '<p style="margin-top:3mm">Số tiền còn phải trả bằng chữ: <span class="chu">' +
      h(E.docSoThanhChu(tongCon)) + '</span></p>' +
      '<div class="ngaythang" style="margin-top:5mm">' + ngayThangNam(ctx.ngayLap) + '</div>' +
      khoiKy([
        { chuc: 'Người lập biểu', ten: ctx.nguoiLap || '' },
        { chuc: 'Kế toán trưởng' },
        { chuc: 'Giám đốc' },
      ])
    );
  }

  /* ===================== 6. Sổ chi tiết thanh toán với người bán (S31-DN) ===================== */

  function mauSoTheoDoi(ctx) {
    const n = ctx.ncc;
    if (!n) return trong('Chọn một nhà cung cấp ở tab <b>Công nợ</b> rồi bấm lại mẫu này.');
    const ds = (ctx.dongCuaNCC || []).slice().sort((a, b) => (a.han || '') < (b.han || '') ? -1 : 1);
    let duCuoi = ctx.duDauKy || 0;
    const hang = ds.map((x) => {
      const co = x.rec.soTien;          // phát sinh Có TK 331 — ghi tăng nợ phải trả
      const no = x.daChi;               // phát sinh Nợ TK 331 — đã thanh toán
      duCuoi += co - no;
      return (
        '<tr>' +
        '<td class="ct">' + d(x.han) + '</td>' +
        '<td class="ct">' + h((x.td && x.td.soUNC) || '') + '</td>' +
        '<td>' + h(x.rec.noiDung) + '</td>' +
        '<td class="ct">331</td>' +
        '<td class="num">' + (no ? t(no) : '') + '</td>' +
        '<td class="num">' + t(co) + '</td>' +
        '<td class="num">' + t(duCuoi) + '</td>' +
        '</tr>'
      );
    }).join('');

    return giay(
      '<table class="phang"><tr>' +
      '<td style="width:62%"><b>' + h(ctx.congTy.ten || '') + '</b><br>' +
      (ctx.congTy.diaChi ? h(ctx.congTy.diaChi) : '') + '</td>' +
      '<td class="num" style="width:38%"><b>Mẫu số S31-DN</b><br>' +
      '<span class="chu">(Ban hành theo Thông tư số 200/2014/TT-BTC<br>ngày 22/12/2014 của Bộ Tài chính)</span></td>' +
      '</tr></table>' +
      '<h1 style="margin-top:5mm">Sổ chi tiết thanh toán với người bán</h1>' +
      '<div class="ct" style="margin-bottom:4mm">Tài khoản: <b>331 — Phải trả cho người bán</b><br>' +
      'Đối tượng: <b>' + h(n.nhan) + '</b><br>' +
      '<span class="chu">Đến ngày ' + d(ctx.denNgay || E.homNayISO()) + '</span></div>' +
      '<table class="vien khung">' +
      '<tr><th style="width:22mm">Ngày</th><th style="width:22mm">Số CT</th><th>Diễn giải</th>' +
      '<th style="width:16mm">TK đối ứng</th>' +
      '<th style="width:28mm">Nợ<br><span class="chu">(đã trả)</span></th>' +
      '<th style="width:28mm">Có<br><span class="chu">(phải trả)</span></th>' +
      '<th style="width:28mm">Số dư</th></tr>' +
      '<tr><td colspan="4"><b>Số dư đầu kỳ</b></td><td></td><td></td>' +
      '<td class="num"><b>' + t(ctx.duDauKy || 0) + '</b></td></tr>' +
      (hang || '<tr><td colspan="7" class="ct">(không có phát sinh)</td></tr>') +
      '<tr><td colspan="4"><b>Số dư cuối kỳ</b></td><td></td><td></td>' +
      '<td class="num"><b>' + t(duCuoi) + '</b></td></tr>' +
      '</table>' +
      '<div class="ngaythang" style="margin-top:5mm">' + ngayThangNam(ctx.ngayLap) + '</div>' +
      khoiKy([
        { chuc: 'Người ghi sổ', ten: ctx.nguoiLap || '' },
        { chuc: 'Kế toán trưởng' },
        { chuc: 'Giám đốc' },
      ])
    );
  }

  /* ===================== 7. Kế hoạch chi tiền trình ký ===================== */

  function mauKeHoach(ctx) {
    const ds = (ctx.dsLoc || []).slice().sort((a, b) => (a.han || '9') < (b.han || '9') ? -1 : 1);
    if (!ds.length) return trong('Bộ lọc ở tab <b>Ủy nhiệm chi</b> đang không ra dòng nào.');
    const nhan = { cu: 'K&H cũ', moi: 'K&H mới', smart: 'Smart', '': '—' };
    let tong = 0;
    const hang = ds.map((x, i) => {
      tong += x.conNo || x.rec.soTien;
      return (
        '<tr>' +
        '<td class="ct">' + (i + 1) + '</td>' +
        '<td class="ct">' + d(x.han) + '</td>' +
        '<td class="ct">' + h(x.rec.bpGoc || x.rec.bp) + '</td>' +
        '<td>' + h(x.rec.noiDung) + '</td>' +
        '<td>' + h(x.rec.thuHuong) + '</td>' +
        '<td class="mono" style="font-size:9.5pt">' + h(x.rec.soTaiKhoan) + '<br>' + h(x.rec.nganHangTH) + '</td>' +
        '<td class="ct">' + h(nhan[x.rec.taiKhoanCty] || '—') + '</td>' +
        '<td class="num">' + t(x.rec.soTien) + '</td>' +
        '<td class="ct">' + (x.quaHan ? 'Quá hạn ' + x.treNgay + 'n' : h(x.nhanTrangThai)) + '</td>' +
        '</tr>'
      );
    }).join('');

    return giay(
      dauDonVi(ctx.congTy) +
      '<h1 style="margin-top:4mm">Kế hoạch chi tiền — trình ký duyệt</h1>' +
      '<div class="ct" style="margin-bottom:4mm">' + h(ctx.moTaLoc || '') + '</div>' +
      '<table class="vien khung" style="font-size:10pt">' +
      '<tr><th style="width:9mm">STT</th><th style="width:19mm">Hạn đi</th><th style="width:22mm">Bộ phận</th>' +
      '<th>Nội dung</th><th style="width:38mm">Đơn vị thụ hưởng</th>' +
      '<th style="width:34mm">Tài khoản</th><th style="width:16mm">TK chi</th>' +
      '<th style="width:26mm">Số tiền</th><th style="width:22mm">Tình trạng</th></tr>' +
      hang +
      '<tr><td colspan="7" class="ct"><b>TỔNG CỘNG</b></td>' +
      '<td class="num"><b>' + t(tong) + '</b></td><td></td></tr>' +
      '</table>' +
      '<p style="margin-top:3mm">Tổng số tiền bằng chữ: <span class="chu">' + h(E.docSoThanhChu(tong)) + '</span></p>' +
      '<div class="ngaythang" style="margin-top:5mm">' + ngayThangNam(ctx.ngayLap) + '</div>' +
      khoiKy([
        { chuc: 'Người lập', ten: ctx.nguoiLap || '' },
        { chuc: 'Kế toán trưởng' },
        { chuc: 'Giám đốc duyệt' },
      ])
    );
  }

  /* ===================== Điều phối ===================== */

  function trong(thongBao) {
    return '<div class="empty">' + thongBao + '</div>';
  }

  const BANG = {
    unc: mauUNC,
    denghi: mauDeNghi,
    phieuchi: mauPhieuChi,
    doichieu: mauDoiChieu,
    bangke: mauBangKe,
    sotheodoi: mauSoTheoDoi,
    kehoach: mauKeHoach,
  };

  function dung(ma, ctx) {
    const f = BANG[ma];
    if (!f) return trong('Chưa có mẫu "' + h(ma) + '".');
    const c = Object.assign({ congTy: {}, ngayLap: E.homNayISO() }, ctx || {});
    c.congTy = c.congTy || {};
    try {
      return f(c);
    } catch (e) {
      return trong('Không dựng được mẫu: ' + h(e && e.message));
    }
  }

  return { DS_MAU, dung, ngayThangNam, escapeHtml: h };
});
