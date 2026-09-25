/*═══════════════════════════════════════════════════════════════════════════
 * JP2_14_ButToan — SỔ NHẬT KÝ CHUNG + BẢNG CÂN ĐỐI SỐ PHÁT SINH
 * ---------------------------------------------------------------------------
 * Andy chốt 04/08/2026: *"nhân viên gửi báo cáo sau đó kế toán duyệt và sau đó
 * hàng và doanh thu sẽ nhảy qua bán hàng và nhảy ra chuẩn bảng cân đối kế toán"*,
 * và chọn **JP tự hạch toán, đối chiếu MISA**.
 *
 *
 * ⚠️ BẤT BIẾN LỚN NHẤT: FILE NÀY **KHÔNG LƯU** BÚT TOÁN NÀO
 *
 * Mọi bút toán được **SUY RA** từ chứng từ đã có, mỗi lần gọi. Không có tab
 * `JP_ButToan`, không có bước "kết chuyển", không có nút "sinh bút toán".
 *
 * Vì sao — đây là chỗ dễ bị "cải tiến" thành lưu, nên đọc kỹ:
 *
 *   ① Lưu là có HAI nguồn sự thật cho cùng một việc. Sửa một báo cáo cũ thì
 *      chứng từ đổi mà bút toán đã lưu thì không — y hệt cái bẫy `canBang` cũ,
 *      chỉ là lần này lệch tiền chứ không lệch số lượng.
 *   ② Lưu là phải có đường HOÀN lại khi kế toán trả về / mở lại báo cáo. Sổ kho
 *      đã phải có `jpHoanKhoBaoCao_` cho việc đó và nó là chỗ phức tạp nhất của
 *      cả repo (xem 5 bất biến ở đầu `11_Kho`). Suy ra thì không cần đường hoàn
 *      nào: báo cáo rời khỏi `HOAN_TAT` là bút toán tự biến mất.
 *   ③ JP đã chọn cách này cho `jpSoCongNo` (dư đầu kỳ suy ra, không chốt sổ) và
 *      cho `jpKhoNhapXuatTon` (tồn đầu suy ngược). Làm khác ở đây là ba cơ chế
 *      cho cùng một vấn đề.
 *
 * Đánh đổi ĐÃ BIẾT và Andy đã nhận ở sổ công nợ: **không đóng băng được kỳ**.
 * Sửa báo cáo tháng 7 thì bảng cân đối tháng 7 đổi theo. Giao diện nói rõ.
 *
 *
 * ⚠️ BẤT BIẾN THỨ HAI: MỌI DOANH THU VÀO 131, MỌI LẦN THU TIỀN TRỪ KHỎI 131
 *
 * Nhờ luật đó, số dư 131 ở đây PHẢI bằng `duCuoiKy` của `jpSoCongNo`. Hai đường
 * suy ra hoàn toàn độc lập (một đi qua bút toán từng cặp, một cộng thẳng
 * `totalSubmit − paid`), nên so chúng là phép kiểm THẬT. `jpKiemTraButToan` làm
 * đúng việc đó, và test khoá lại.
 *
 * ⚠️ BẤT BIẾN THỨ BA: Σ PHÁT SINH NỢ = Σ PHÁT SINH CÓ, luôn luôn
 *
 * Mỗi bút toán ở đây là một CẶP (một `tkNo`, một `tkCo`, một số tiền) nên đẳng
 * thức đó đúng theo cách dựng. Vẫn kiểm, vì nó bắt được lỗi khác: dòng nào
 * thiếu `tkNo` hoặc `tkCo` (dữ liệu kho đời đầu chưa có cột đó) sẽ lệch ra ngay.
 *
 * ⚠️ CHỈ `HOAN_TAT` VÀO SỔ — giống bất biến #1 của kho. Báo cáo chờ duyệt được
 * đếm riêng và hiện thành cảnh báo, không thì kế toán tưởng kỳ đó không có gì.
 *═══════════════════════════════════════════════════════════════════════════*/

/** Một cặp bút toán, đúng thứ tự cột sổ chi tiết MISA. */
function jpBt_(p) {
  return {
    ngay: p.ngay,                        // 'YYYY-MM-DD'
    soCt: jpStr_(p.soCt),
    dienGiaiChung: jpStr_(p.dienGiaiChung),
    dienGiai: jpStr_(p.dienGiai),
    tkNo: jpStr_(p.tkNo),
    tkCo: jpStr_(p.tkCo),
    soTien: jpNum_(p.soTien),
    maDoiTuong: jpStr_(p.maDoiTuong),
    maDonVi: jpStr_(p.maDonVi),
    tenDonVi: jpStr_(p.tenDonVi),
    nguon: jpStr_(p.nguon)               // 'BC' | 'THU' | 'KHO' | 'NHAP' | 'DC' | 'KK'
  };
}

/*───────────────────── BÚT TOÁN CỦA MỘT BÁO CÁO ─────────────────────*/

/**
 * Bốn cặp có thể sinh ra từ một báo cáo `HOAN_TAT`. Xem bảng ở `00_Config`.
 *
 * ⚠️ Dùng `revMeter` làm doanh thu, KHÔNG dùng `totalSubmit`. Hai số khác nhau
 * đúng bằng lệch máy và hoàn khách. Ghi `totalSubmit` vào 5111 thì cân sổ vẫn
 * cân, nhưng **lệch máy tan vào doanh thu** — mất luôn dấu vết mất tiền, đúng
 * cái bệnh "doanh thu tự nhiên hụt mà không ai báo" ở mục cơ sở đã dẹp.
 *
 * `l` là dòng danh mục cơ sở (có `maKH`, `unitCode`), có thể `null`.
 */
function jpButToanBaoCao_(r, l) {
  var bt = [];
  var ngay = (jpDate_(r.toDate) || jpDate_(r.fromDate));
  if (!ngay) return bt;

  var soCt = jpStr_(r.id);
  var ten = jpStr_(r.locationName) || jpStr_((l || {}).name);
  var chung = 'Doanh thu ' + ten + ' kỳ ' + jpStr_(r.fromDate) + '…' + jpStr_(r.toDate);
  var chung2 = { soCt: soCt, dienGiaiChung: chung, ngay: ngay, nguon: 'BC',
                 maDoiTuong: jpStr_(r.maKH) || jpStr_((l || {}).maKH),
                 maDonVi: jpUnitId_((l || {}).unitCode),
                 tenDonVi: ten };
  var moi = function (o) {
    var x = {}; Object.keys(chung2).forEach(function (k) { x[k] = chung2[k]; });
    Object.keys(o).forEach(function (k) { x[k] = o[k]; });
    return jpBt_(x);
  };

  var revMeter = jpNum_(r.revMeter);
  var adj      = jpNum_(r.adjMachine);
  var refund   = jpHoanTong_(r);        // dòng + header — xem jpHoanTong_ ở 00_Config

  /* ① Doanh thu. Thuế suất 0 (mặc định) thì KHÔNG sinh cặp 3331 nào — đừng đổi
        thành sinh cặp số tiền 0, dòng 0đ trong sổ chỉ làm rối bảng kê. */
  if (revMeter) {
    var thue = JP_VAT_SUAT > 0
      ? Math.round(revMeter * JP_VAT_SUAT / (1 + JP_VAT_SUAT))
      : 0;
    bt.push(moi({ tkNo: JP_TK_PHAI_THU, tkCo: JP_TK_DOANH_THU,
                  soTien: revMeter - thue,
                  dienGiai: 'Doanh thu bán hàng' + (thue ? ' (chưa thuế)' : '') }));
    if (thue) {
      bt.push(moi({ tkNo: JP_TK_PHAI_THU, tkCo: JP_TK_THUE_GTGT, soTien: thue,
                    dienGiai: 'Thuế GTGT đầu ra' }));
    }
  }

  /* ② Lệch máy. Dương = đếm được nhiều hơn máy báo ⇒ phải thu thêm, thu nhập
        khác. Âm = thiếu ⇒ giảm phải thu, ghi lỗ. */
  if (adj > 0) {
    bt.push(moi({ tkNo: JP_TK_PHAI_THU, tkCo: JP_TK_THU_NHAP_KHAC, soTien: adj,
                  dienGiai: 'Lệch máy thừa' +
                    (jpStr_(r.adjMachineNote) ? ' — ' + jpStr_(r.adjMachineNote) : '') }));
  } else if (adj < 0) {
    bt.push(moi({ tkNo: JP_TK_CHI_PHI_KHAC, tkCo: JP_TK_PHAI_THU, soTien: -adj,
                  dienGiai: 'Lệch máy thiếu' +
                    (jpStr_(r.adjMachineNote) ? ' — ' + jpStr_(r.adjMachineNote) : '') }));
  }

  /* ③ Hoàn khách — giảm trừ doanh thu, giảm phải thu. */
  if (refund) {
    bt.push(moi({ tkNo: JP_TK_GIAM_TRU_DT, tkCo: JP_TK_PHAI_THU, soTien: refund,
                  dienGiai: 'Hoàn tiền khách' +
                    (jpStr_(r.refundNote) ? ' — ' + jpStr_(r.refundNote) : '') }));
  }

  return bt;
}

/*──────────────────── BÚT TOÁN THU TIỀN ────────────────────*/

/**
 * Mỗi dòng `JP_Payments` là một lần thu: `1111 / 131` hoặc `1121 / 131`.
 *
 * ⚠️ Đọc từ `JP_Payments`, KHÔNG từ cột `paid` của báo cáo. Cột `paid` chỉ có
 * TỔNG đã nhận, không có ngày và không có phương thức — không tách được tiền mặt
 * với tiền gửi, mà đó chính là hai tài khoản khác nhau. `jpKiemTraButToan` so
 * hai con số đó với nhau để bắt trường hợp lệch (đối soát ghi `paid` mà quên ghi
 * dòng nộp tiền, hoặc ngược lại).
 */
function jpButToanThuTien_(p, r, l) {
  var ngay = (jpDate_(p.payDate) || jpDate_(p.createdAt));
  if (!ngay) return null;
  var tien = jpNum_(p.amount);
  if (!tien) return null;

  var ten = jpStr_((r || {}).locationName) || jpStr_((l || {}).name);
  /* ⚠️ Phải qua `jpVaoTaiKhoan_`, ĐỪNG so `=== JP_PAY_CK`. Có BA phương thức
     (TM · CK · QR) và **QR về TÀI KHOẢN** như CK. So thẳng với CK là QR rơi vào
     nhánh tiền mặt ⇒ 1111 phình lên, 1121 hụt đi, mà sổ vẫn CÂN nên không phép
     kiểm nào bắt được. Đã mắc đúng lỗi này lúc thêm QR ngày 04/08. */
  var ck = jpVaoTaiKhoan_(p.method);
  return jpBt_({
    ngay: ngay, soCt: jpStr_(p.id), nguon: 'THU',
    dienGiaiChung: 'Thu tiền ' + ten,
    dienGiai: ('Thu ' + (JP_PAY_TEN[jpStr_(p.method).toUpperCase()] || 'tiền mặt')) +
              (jpStr_(p.isSupplement) === 'Y' ? ' (nộp bổ sung)' : '') +
              (jpStr_(p.note) ? ' — ' + jpStr_(p.note) : ''),
    tkNo: ck ? JP_TK_TIEN_GUI : JP_TK_TIEN_MAT,
    tkCo: JP_TK_PHAI_THU,
    soTien: tien,
    maDoiTuong: jpStr_((r || {}).maKH) || jpStr_((l || {}).maKH),
    maDonVi: jpUnitId_((l || {}).unitCode),
    tenDonVi: ten
  });
}

/*──────────────── GOM TOÀN BỘ BÚT TOÁN TỚI MỘT MỐC ────────────────*/

/**
 * Mọi bút toán có ngày `<= cuoi`. Trả về mảng phẳng các cặp.
 *
 * Không cắt theo `dau`: bảng cân đối cần cả phát sinh TRƯỚC kỳ để suy dư đầu kỳ.
 * Chỗ gọi tự chia theo ngày. Đọc mỗi tab đúng MỘT lần rồi cache — nguyên tắc #3
 * kế thừa từ POSH v3.
 *
 * `boQua` nói ra những gì KHÔNG vào sổ, để không bao giờ có chuyện tiền biến mất
 * êm: báo cáo chờ duyệt, phiếu đã huỷ, dòng kho thiếu tài khoản.
 */
function jpGomButToan_(cuoi) {
  var bt = [];
  var boQua = { bcChuaDuyet: 0, tienChuaDuyet: 0, phieuHuy: 0, thieuTk: 0, thieuNgay: 0 };

  var locById = {};
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) { locById[String(l.id)] = l; });

  /*── ① + ② Báo cáo và tiền về ──*/
  var bcById = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    bcById[String(r.id)] = r;
    var ngay = (jpDate_(r.toDate) || jpDate_(r.fromDate));
    if (!ngay) { boQua.thieuNgay++; return; }
    if (ngay > cuoi) return;
    if (jpStr_(r.status) !== JP_ST_DONE) {
      boQua.bcChuaDuyet++;
      boQua.tienChuaDuyet += jpNum_(r.totalSubmit);
      return;
    }
    jpButToanBaoCao_(r, locById[String(r.locationId)]).forEach(function (x) { bt.push(x); });
  });

  jpRows_(JP_TABS.PAYMENTS).forEach(function (p) {
    var r = bcById[String(p.reportId)];
    /* Tiền của báo cáo CHƯA duyệt thì chưa vào sổ — nếu vào thì có bên Nợ 1111
       mà không có bên Có 131 nào sinh nó ra, sổ mất cân ngay. */
    if (!r || jpStr_(r.status) !== JP_ST_DONE) return;
    var x = jpButToanThuTien_(p, r, locById[String(p.locationId) || String(r.locationId)]);
    if (!x) { boQua.thieuNgay++; return; }
    if (x.ngay > cuoi) return;
    bt.push(x);
  });

  /*── ③ Kho: dòng xuất đã có sẵn tkNo/tkCo, chỉ đổi khuôn ──
     Gồm cả BAN (632/1567), KK_THIEU (6321/1561) và KK_GIAM_XUAT (số ÂM, dòng
     đảo). Dòng âm cứ để âm — tổng phát sinh tự giảm, đúng ý "điều chỉnh giảm số
     lượng xuất ra trong kỳ". */
  jpRows_(JP_TABS.KHO_XUAT).forEach(function (x) {
    var ngay = (jpDate_(x.ngay));
    if (!ngay || ngay > cuoi) { if (!ngay) boQua.thieuNgay++; return; }
    var tien = jpNum_(x.amount);
    if (!tien) return;
    var no = jpStr_(x.tkNo), co = jpStr_(x.tkCo);
    /* Dòng kho đời đầu có thể chưa có cột tài khoản. Suy mặc định theo loại rồi
       ĐẾM lại — im lặng bỏ là sổ thiếu giá vốn mà bảng vẫn cân. */
    if (!no || !co) {
      boQua.thieuTk++;
      no = no || JP_TK_GIA_VON;
      co = co || JP_TK_KHO;
    }
    bt.push(jpBt_({
      ngay: ngay, soCt: jpStr_(x.soChungTu) || jpStr_(x.id), nguon: 'KHO',
      dienGiaiChung: 'Xuất kho bán hàng ' + jpStr_(x.locationName),
      dienGiai: jpStr_(x.itemName) || jpStr_(x.itemCode),
      tkNo: no, tkCo: co, soTien: tien,
      maDoiTuong: jpStr_(x.maKH), maDonVi: jpUnitId_(x.unitCode),
      tenDonVi: jpStr_(x.locationName)
    }));
  });

  /*── ④ Phiếu nhập: MUA → 1561/331 · KIEM_KE → 1561/1388 · DAU_KY → 1561/4211 ──
     Ba đường hàng vào kho hạch toán khác nhau hoàn toàn, xem `JP_NHAP_LOAI`. */
  var nhapCT = {};
  jpRows_(JP_TABS.KHO_NHAP_CT).forEach(function (d) {
    (nhapCT[String(d.nhapId)] = nhapCT[String(d.nhapId)] || []).push(d);
  });
  jpRows_(JP_TABS.KHO_NHAP).forEach(function (h) {
    if (jpStr_(h.huyAt)) { boQua.phieuHuy++; return; }
    var ngay = (jpDate_(h.ngay));
    if (!ngay || ngay > cuoi) { if (!ngay) boQua.thieuNgay++; return; }
    var lo = jpLoaiNhap_(h);
    var co = lo === JP_NHAP_KIEM_KE ? JP_TK_PHAI_THU_KHAC
           : lo === JP_NHAP_DAU_KY  ? JP_TK_DU_DAU_KY
           : JP_TK_NCC;
    var giai = lo === JP_NHAP_KIEM_KE ? 'Nhập kho kiểm kê thừa'
             : lo === JP_NHAP_DAU_KY  ? 'Tồn kho đầu kỳ'
             : 'Nhập kho mua hàng';
    (nhapCT[String(h.id)] || []).forEach(function (ct) {
      var tien = jpNum_(ct.amount) || jpNum_(ct.qty) * jpNum_(ct.unitCost);
      if (!tien) return;
      bt.push(jpBt_({
        ngay: ngay, soCt: jpStr_(h.soChungTu) || jpStr_(h.id), nguon: 'NHAP',
        dienGiaiChung: giai + (jpStr_(h.nccTen) ? ' — ' + jpStr_(h.nccTen) : ''),
        dienGiai: jpStr_(ct.itemName) || jpStr_(ct.itemCode),
        tkNo: JP_TK_KHO_KK, tkCo: co, soTien: tien,
        maDoiTuong: jpStr_(h.nccMa), tenDonVi: jpStr_(h.locationName)
      }));
    });
  });

  /*── ⑤ Phiếu xuất kho tổng: CHỈ xé mẫu và tặng mall sinh bút toán (641) ──
     `XUAT_CS` / `TRA_KHO` / `DIEU_CHUYEN` là điều chuyển nội bộ — hàng đổi kho
     mà KHÔNG đổi tài khoản, nên không có bút toán nào. Sinh 1567/1567 cho chúng
     là bơm phồng tổng phát sinh bằng những dòng vô nghĩa. */
  var dcCT = {};
  jpRows_(JP_TABS.KHO_DC_CT).forEach(function (d) {
    (dcCT[String(d.dcId)] = dcCT[String(d.dcId)] || []).push(d);
  });
  jpRows_(JP_TABS.KHO_DC).forEach(function (h) {
    if (jpStr_(h.huyAt)) { boQua.phieuHuy++; return; }
    var loai = jpStr_(h.loai);
    var def = JP_XUAT_LOAI[loai];
    if (!def || !def.tk) return;                  // không sinh giá vốn / chi phí
    var ngay = (jpDate_(h.ngay));
    if (!ngay || ngay > cuoi) { if (!ngay) boQua.thieuNgay++; return; }
    (dcCT[String(h.id)] || []).forEach(function (ct) {
      var tien = jpNum_(ct.amount) || jpNum_(ct.qty) * jpNum_(ct.unitCost);
      if (!tien) return;
      bt.push(jpBt_({
        ngay: ngay, soCt: jpStr_(h.soChungTu) || jpStr_(h.id), nguon: 'DC',
        dienGiaiChung: def.ten + (jpStr_(h.ghiChu) ? ' — ' + jpStr_(h.ghiChu) : ''),
        dienGiai: jpStr_(ct.itemName) || jpStr_(ct.itemCode),
        tkNo: def.tk, tkCo: JP_TK_KHO, soTien: tien,
        tenDonVi: jpStr_(h.locationName)
      }));
    });
  });

  /*── ⑥ Phiếu trả tiền nhà cung cấp: 331 / 1111 hoặc 1121 ──*/
  jpRows_(JP_TABS.KHO_TRA_NCC).forEach(function (t) {
    if (jpStr_(t.huyAt)) { boQua.phieuHuy++; return; }
    var ngay = (jpDate_(t.ngay));
    if (!ngay || ngay > cuoi) { if (!ngay) boQua.thieuNgay++; return; }
    var tien = jpNum_(t.soTien);
    if (!tien) return;
    bt.push(jpBt_({
      ngay: ngay, soCt: jpStr_(t.soChungTu) || jpStr_(t.id), nguon: 'TTNCC',
      dienGiaiChung: 'Trả tiền nhà cung cấp ' + jpStr_(t.nccTen),
      dienGiai: jpStr_(t.ghiChu) || 'Trả tiền người bán',
      tkNo: JP_TK_NCC,
      tkCo: jpStr_(t.hinhThuc) === JP_PAY_CK ? JP_TK_TIEN_GUI : JP_TK_TIEN_MAT,
      soTien: tien, maDoiTuong: jpStr_(t.nccMa)
    }));
  });

  bt.sort(function (a, b) {
    if (a.ngay !== b.ngay) return a.ngay < b.ngay ? -1 : 1;
    if (a.soCt !== b.soCt) return a.soCt < b.soCt ? -1 : 1;
    return 0;
  });
  return { bt: bt, boQua: boQua };
}

/** Mốc đầu / cuối tháng dạng 'YYYY-MM-DD'. Dùng chung cho cả ba hàm dưới. */
function jpMocKy_(thang, nam) {
  var m = jpNum_(thang), y = jpNum_(nam);
  if (m < 1 || m > 12 || y < 2020) throw new Error('Tháng / năm không hợp lệ');
  var soNgay = new Date(y, m, 0).getDate();
  var p = y + '-' + ('0' + m).slice(-2) + '-';
  return { m: m, y: y, dau: p + '01', cuoi: p + ('0' + soNgay).slice(-2) };
}

/*═════════════════════ ① SỔ NHẬT KÝ CHUNG ═════════════════════*/

/**
 * Mọi bút toán trong tháng, lọc được theo tài khoản.
 *
 * `tk` truyền vào thì lọc dòng có `tkNo` HOẶC `tkCo` bằng nó — đó chính là "sổ
 * chi tiết tài khoản" của MISA, và có thêm cột dư luỹ kế để in ra đúng khuôn.
 */
function jpSoNhatKyChung(token, thang, nam, tk) {
  jpNeedKT_(jpAuth_(token));
  var k = jpMocKy_(thang, nam);
  var g = jpGomButToan_(k.cuoi);
  var loc = jpStr_(tk);

  var rows = [], tongNo = 0, tongCo = 0;
  var duDau = 0;
  g.bt.forEach(function (x) {
    var dungTk = !loc || x.tkNo === loc || x.tkCo === loc;
    if (!dungTk) return;
    /* Trước kỳ: chỉ dùng để dựng dư đầu kỳ của TK đang lọc */
    if (x.ngay < k.dau) {
      if (loc) duDau += (x.tkNo === loc ? x.soTien : 0) - (x.tkCo === loc ? x.soTien : 0);
      return;
    }
    rows.push(x);
    tongNo += x.soTien;
    tongCo += x.soTien;
  });

  /* Dư luỹ kế chỉ có nghĩa khi xem MỘT tài khoản — xem cả sổ thì mỗi dòng một
     tài khoản khác nhau, cộng dồn ra số vô nghĩa. */
  if (loc) {
    var dl = duDau;
    rows.forEach(function (x) {
      dl += (x.tkNo === loc ? x.soTien : 0) - (x.tkCo === loc ? x.soTien : 0);
      x.duNo = dl > 0 ? dl : 0;
      x.duCo = dl < 0 ? -dl : 0;
    });
  }

  return {
    ok: true, thang: k.m, nam: k.y, tk: loc,
    tenTk: loc ? (JP_TEN_TK[loc] || '') : '',
    duDauNo: duDau > 0 ? duDau : 0, duDauCo: duDau < 0 ? -duDau : 0,
    rows: rows, soDong: rows.length,
    tongNo: tongNo, tongCo: tongCo,
    canBang: tongNo === tongCo,
    boQua: g.boQua,
    tkTam: JP_TK_TAM, vatSuat: JP_VAT_SUAT
  };
}

/*═══════════ ② BẢNG CÂN ĐỐI SỐ PHÁT SINH — khuôn MISA ═══════════*/

/**
 * Từng tài khoản: `Dư đầu kỳ` (Nợ/Có) · `Phát sinh` (Nợ/Có) · `Dư cuối kỳ`.
 *
 * Khuôn lấy từ file thật `Sổ chi tiết các tài khoản` kế toán gửi: có dòng
 * `Số dư đầu kỳ` riêng, và dư luỹ kế cộng dồn (đã kiểm số:
 * 3.145.608.860 + 182.988 = 3.145.791.848).
 *
 * ⚠️ Dư đầu kỳ **SUY RA** bằng cách cộng hết bút toán trước `dau` — cùng cách
 * `jpSoCongNo` làm, không cần chốt sổ. Nên nó luôn khớp chứng từ gốc.
 *
 * ⚠️ Phép kiểm ở đây là THẬT, không tautology: `Σ phát sinh Nợ = Σ phát sinh Có`
 * đúng vì mỗi bút toán là một cặp — nhưng nó vẫn bắt được dòng kho thiếu cột tài
 * khoản, nên đừng bỏ. Và `Σ dư cuối Nợ = Σ dư cuối Có` là phép thứ hai, độc lập
 * với phép thứ nhất vì nó gộp cả dư đầu kỳ.
 */
function jpBangCanDoiPhatSinh(token, thang, nam) {
  jpNeedKT_(jpAuth_(token));
  var k = jpMocKy_(thang, nam);
  var g = jpGomButToan_(k.cuoi);

  var gom = {};
  var lay = function (tk) {
    if (!gom[tk]) {
      gom[tk] = { tk: tk, tenTk: JP_TEN_TK[tk] || '',
                  duDauNo: 0, duDauCo: 0, psNo: 0, psCo: 0,
                  duCuoiNo: 0, duCuoiCo: 0, soDong: 0, laTam: false };
    }
    return gom[tk];
  };
  /* Ròng theo tài khoản, chưa chia Nợ/Có — chia ở cuối theo DẤU, vì một tài
     khoản có thể dư ngược chiều thông thường (131 dư Có = thu trước tiền). */
  var rong = {}, rongDau = {};

  g.bt.forEach(function (x) {
    var truoc = x.ngay < k.dau;
    [x.tkNo, x.tkCo].forEach(function (tk) {
      if (!tk) return;
      lay(tk);
      if (rong[tk] === undefined) { rong[tk] = 0; rongDau[tk] = 0; }
    });
    var d = x.soTien;
    if (x.tkNo) { rong[x.tkNo] += d; if (truoc) rongDau[x.tkNo] += d; }
    if (x.tkCo) { rong[x.tkCo] -= d; if (truoc) rongDau[x.tkCo] -= d; }
    if (!truoc) {
      if (x.tkNo) { lay(x.tkNo).psNo += d; lay(x.tkNo).soDong++; }
      if (x.tkCo) { lay(x.tkCo).psCo += d; lay(x.tkCo).soDong++; }
    }
  });

  var tamTk = {};
  JP_TK_TAM.forEach(function (t) { tamTk[t.tk] = t.viec; });

  var rows = Object.keys(gom).sort().map(function (tk) {
    var r = gom[tk];
    var d0 = rongDau[tk] || 0, d1 = rong[tk] || 0;
    r.duDauNo = d0 > 0 ? d0 : 0;   r.duDauCo = d0 < 0 ? -d0 : 0;
    r.duCuoiNo = d1 > 0 ? d1 : 0;  r.duCuoiCo = d1 < 0 ? -d1 : 0;
    r.laTam = !!tamTk[tk];
    r.viecTam = tamTk[tk] || '';
    return r;
  }).filter(function (r) {
    /* Bỏ tài khoản im lặng hoàn toàn. Nhưng GIỮ dòng có dư mà không phát sinh —
       đó là tài khoản đang treo số, đúng chỗ cần thấy nhất. */
    return r.psNo || r.psCo || r.duDauNo || r.duDauCo || r.duCuoiNo || r.duCuoiCo;
  });

  var tong = { duDauNo: 0, duDauCo: 0, psNo: 0, psCo: 0, duCuoiNo: 0, duCuoiCo: 0 };
  rows.forEach(function (r) {
    Object.keys(tong).forEach(function (c) { tong[c] += r[c]; });
  });

  return {
    ok: true, thang: k.m, nam: k.y, rows: rows, tong: tong,
    canBangPs:    tong.psNo === tong.psCo,
    canBangDau:   tong.duDauNo === tong.duDauCo,
    canBangCuoi:  tong.duCuoiNo === tong.duCuoiCo,
    lechPs:   tong.psNo - tong.psCo,
    lechDau:  tong.duDauNo - tong.duDauCo,
    lechCuoi: tong.duCuoiNo - tong.duCuoiCo,
    boQua: g.boQua, tkTam: JP_TK_TAM, vatSuat: JP_VAT_SUAT
  };
}

/*═══════ ③ PHÉP KIỂM: 131 của sổ phải bằng sổ công nợ ═══════*/

/**
 * So HAI ĐƯỜNG ĐỘC LẬP cho cùng một con số.
 *
 *   đường A — qua bút toán: dư 131 = Σ (Nợ 131) − Σ (Có 131), dựng từ từng cặp
 *   đường B — qua sổ công nợ: Σ `totalSubmit` − Σ `paid`, cộng thẳng từ báo cáo
 *
 * Hai đường đi qua code hoàn toàn khác nhau, nên khớp là bằng chứng thật. Lệch
 * có nguyên nhân thật, và hàm NÓI RA từng cái:
 *
 *   ① đối soát ghi `paid` mà không ghi dòng `JP_Payments` (hoặc ngược lại)
 *      ⇒ `lechThu` khác 0
 *   ② báo cáo `HOAN_TAT` có `totalSubmit` không bằng `revMeter + adj − refund`
 *      ⇒ ai đó sửa tay cột `totalSubmit` trên sheet
 *
 * ⚠️ Đừng "dọn" thành cùng đọc một nguồn cho khớp. Khớp bằng cách đó là phép so
 * tautology — đúng cái bệnh `canBang` cũ đã mắc và đã phải sửa.
 */
function jpKiemTraButToan(token, thang, nam) {
  jpNeedKT_(jpAuth_(token));
  var k = jpMocKy_(thang, nam);
  var g = jpGomButToan_(k.cuoi);

  /* Đường A */
  var duA = 0, thuA = 0;
  g.bt.forEach(function (x) {
    if (x.tkNo === JP_TK_PHAI_THU) duA += x.soTien;
    if (x.tkCo === JP_TK_PHAI_THU) duA -= x.soTien;
    if (x.nguon === 'THU' && x.ngay <= k.cuoi) thuA += x.soTien;
  });

  /* Đường B — cộng thẳng từ báo cáo, KHÔNG gọi jpGomButToan_ */
  var duB = 0, thuB = 0, lechTong = [];
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    if (jpStr_(r.status) !== JP_ST_DONE) return;
    var ngay = (jpDate_(r.toDate) || jpDate_(r.fromDate));
    if (!ngay || ngay > k.cuoi) return;
    var phai = jpNum_(r.totalSubmit), da = jpNum_(r.paid);
    duB += phai - da;
    thuB += da;
    /* Kiểm nội bộ báo cáo: totalSubmit phải khớp công thức ở jpCalcReport_ */
    /* ⚠️ Phải dùng jpHoanTong_: đọc thẳng refundCustomer là mọi báo cáo có hoàn
       khách THEO MÃ đều báo lệch oan đúng bằng phần theo dòng. */
    var suy = jpNum_(r.revMeter) + jpNum_(r.adjMachine) - jpHoanTong_(r);
    if (suy !== phai) {
      lechTong.push({ id: jpStr_(r.id), coSo: jpStr_(r.locationName),
                      totalSubmit: phai, suyRa: suy, lech: phai - suy });
    }
  });

  return {
    ok: true, thang: k.m, nam: k.y,
    du131ButToan: duA, du131CongNo: duB, lechDu: duA - duB,
    thuButToan: thuA, thuCongNo: thuB, lechThu: thuA - thuB,
    khop: (duA - duB) === 0 && (thuA - thuB) === 0 && !lechTong.length,
    lechTongSubmit: lechTong.slice(0, 20), soLechTongSubmit: lechTong.length,
    boQua: g.boQua
  };
}

/*═══════ ④ QUÉT SỨC KHOẺ DÂY CHUYỀN (Andy lo nhất chỗ này, 04/08/2026) ═══════
 *
 * Andy: *"JP rất phức tạp phần anh lo nhất là dữ liệu dây chuyền và phần tính
 * giá của kế toán và kho"*.
 *
 * Ba chỗ dây chuyền có thể đứt, và cả ba cùng MỘT bệnh: **cảnh báo chỉ hiện một
 * lần lúc bấm duyệt rồi mất**. Đóng toast là không còn dấu vết ở đâu. Đó là mẫu
 * lỗi tệ nhất trong hệ này — số sai mà mọi bảng trông sạch sẽ.
 *
 *   ① Báo cáo `HOAN_TAT` có hàng bán mà KHÔNG có dòng nào trong `JP_KhoXuat`.
 *      `jpXuatKhoBaoCao_` cố ý không throw (duyệt xong thì phải xong) và trả
 *      `{loi}` — nhưng `loi` đó chỉ đi vào câu thông báo của `jpKtApprove`.
 *      Hậu quả: doanh thu vào 131/5111 mà 632 trống ⇒ lãi gộp sai toàn bộ kỳ.
 *
 *   ② Dòng xuất còn cờ `thieuLop`: lúc xuất không có lớp tồn nào để trừ nên giá
 *      vốn lấy **giá mua gần nhất**. Đó là chốt cố ý (bất biến #3 của kho) nhưng
 *      là số TẠM — nhập bù phiếu rồi phải bấm **Xuất lại**. Không ai bấm thì nó
 *      nằm vĩnh viễn ở giá tạm.
 *
 *   ③ Số ở header báo cáo không khớp khi TÍNH LẠI từ `JP_Rows`.
 *      ⚠️ Đường sửa trong app đã đóng: `jpReopenIn24h` chặn báo cáo đã duyệt.
 *      Nên phép này nhắm vào **sửa tay thẳng trên Google Sheet** — thứ duy nhất
 *      còn đi vòng được qua mọi chốt của máy chủ.
 *
 * Phép ③ là ĐƯỜNG ĐỘC LẬP THỨ BA: tính lại từ chỉ số đồng hồ bằng đúng bộ máy
 * `jpCalcRow_` + `jpCalcReport_`, không đọc lại số đã lưu. Khác hẳn `jpKiemTraButToan`
 * (so `totalSubmit` với công thức) — phép đó chỉ dùng số ở header, còn phép này
 * đi từ chỉ số máy lên.
 */
function jpQuetDayChuyen(token, thang, nam) {
  jpNeedKT_(jpAuth_(token));
  var k = jpMocKy_(thang, nam);

  var bc = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    if (jpStr_(r.status) !== JP_ST_DONE) return false;
    var d = jpDate_(r.toDate) || jpDate_(r.fromDate);
    return d && d >= k.dau && d <= k.cuoi;
  });

  /* Đọc mỗi tab MỘT lần rồi gom — nguyên tắc #3 kế thừa từ POSH v3 */
  var rowsBc = {};
  jpRows_(JP_TABS.ROWS).forEach(function (r) {
    (rowsBc[String(r.reportId)] = rowsBc[String(r.reportId)] || []).push(r);
  });
  var xuatBc = {}, thieuLop = [], tkCu = {}, soDongTkCu = 0, tienTkCu = 0;
  jpRows_(JP_TABS.KHO_XUAT).forEach(function (x) {
    var rid = jpStr_(x.reportId);
    if (rid) (xuatBc[rid] = xuatBc[rid] || []).push(x);

    /*── ④ Dòng kho còn mang TÀI KHOẢN CŨ của khối khác (sửa 06/08/2026) ──
       `632`/`641` là đầu TỔNG, `1567` là kho ĂN UỐNG. Dòng ghi trước 06/08 còn
       lưu chúng trong sheet nên KHÔNG tự đổi theo hằng số. Phải lộ ra ở đây, vì
       không đường kiểm nào khác bắt được: cặp bút toán vẫn đủ hai vế nên
       `jpKiemTraButToan` và bảng cân đối đều **BÁO CÂN**. Sửa bằng nút
       "Đổi tài khoản dòng kho cũ" (`jpDoiTkKhoCu`).
       ⚠️ Quét CẢ BẢNG, không lọc theo kỳ — tài khoản sai của tháng trước vẫn
       nằm trong sổ luỹ kế, lọc theo kỳ là tháng này sạch mà sổ vẫn lệch. */
    var no = jpStr_(x.tkNo), co = jpStr_(x.tkCo);
    if (JP_TK_DOI_CU[no] || JP_TK_DOI_CU[co]) {
      soDongTkCu++;
      tienTkCu += jpNum_(x.amount);
      var key = no + ' / ' + co;
      if (!tkCu[key]) {
        tkCu[key] = { tkNoCu: no, tkCoCu: co,
                      tkNoMoi: JP_TK_DOI_CU[no] || no, tkCoMoi: JP_TK_DOI_CU[co] || co,
                      soDong: 0, soTien: 0 };
      }
      tkCu[key].soDong++;
      tkCu[key].soTien += jpNum_(x.amount);
    }
    /* Cờ thiếu lớp có thể lưu 'Y', true, hay 1 tuỳ đời dữ liệu — nhận cả ba */
    var c = jpStr_(x.thieuLop);
    if (c && c !== 'N' && c !== 'false' && c !== '0') {
      thieuLop.push({
        reportId: rid, soChungTu: jpStr_(x.soChungTu), ngay: jpDate_(x.ngay),
        coSo: jpStr_(x.locationName), khoId: jpStr_(x.khoId),
        itemCode: jpStr_(x.itemCode), itemName: jpStr_(x.itemName),
        qty: jpNum_(x.qty), unitCost: jpNum_(x.unitCost), amount: jpNum_(x.amount)
      });
    }
  });

  var thieuGiaVon = [], lechTinhLai = [];
  var tienThieuGiaVon = 0;

  bc.forEach(function (r) {
    var rs = rowsBc[String(r.id)] || [];

    /*── ① Có hàng bán mà không có dòng xuất kho ──
       Lọc `rowKind` y hệt `jpXuatKhoBaoCao_` — CÙNG một hàm `jpDongGiuHang_`, không
       viết lại danh sách ở đây. Hai chỗ hai danh sách là tới lúc thêm loại dòng mới
       thì phép quét và engine xuất kho nhìn khác nhau ⇒ báo sai hàng loạt. */
    var slBan = 0;
    rs.forEach(function (x) {
      if (!jpDongGiuHang_(x.rowKind)) return;
      if (!jpStr_(x.itemCode)) return;
      slBan += jpNum_(x.soldQty);
    });
    if (slBan > 0 && !(xuatBc[String(r.id)] || []).length) {
      thieuGiaVon.push({
        id: jpStr_(r.id), coSo: jpStr_(r.locationName),
        kyTu: jpStr_(r.fromDate), kyDen: jpStr_(r.toDate),
        soLuongBan: slBan, doanhThu: jpNum_(r.revMeter),
        nguoiKy: jpStr_(jpChuKy_(r).revBy || r.apprBy)
      });
      tienThieuGiaVon += jpNum_(r.revMeter);
    }

    /*── ③ Tính lại từ chỉ số đồng hồ ──
       Dựng lại dòng ĐÚNG cách `jpSaveReport` dựng: chỉ số đồng hồ phải đi qua
       `jpNumOrBlank_` để ô TRỐNG ở lại là trống. Dùng `jpNum_` ở đây là ô chưa
       nhập thành số 0, hàm tính ra kết quả khác ⇒ BÁO SAI hàng loạt. */
    if (!rs.length) return;
    var type = jpStr_(r.machineType) || JP_TYPE_MONEY;
    var lai = rs.map(function (x) {
      var o = {
        rowKind: jpStr_(x.rowKind), itemCode: jpStr_(x.itemCode),
        price: jpNum_(x.price) || jpGiaTuMa_(x.itemCode),
        mBefore: jpNumOrBlank_(x.mBefore), mAfter: jpNumOrBlank_(x.mAfter),
        cBefore: jpNumOrBlank_(x.cBefore), cAfter: jpNumOrBlank_(x.cAfter),
        bank: jpNum_(x.bank), cash: jpNum_(x.cash),
        hOpen: jpNum_(x.hOpen),
        hBefore: jpNumOrBlank_(x.hBefore), hAfter: jpNumOrBlank_(x.hAfter),
        stockOut: jpNum_(x.stockOut),
        giaXu: jpNum_(x.giaXu), xuDaysJson: jpStr_(x.xuDaysJson), xuLa: jpNum_(x.xuLa),
        stockOpen: jpNum_(x.stockOpen),
        addQty1: jpNum_(x.addQty1), addQty2: jpNum_(x.addQty2),
        stockActual: jpNumOrBlank_(x.stockActual),
        defectQty: jpNum_(x.defectQty), returnQty: jpNum_(x.returnQty)
      };
      jpCalcRow_(o, type);
      return o;
    });
    /* `jpCalcReport_` GHI vào head nên phải đưa bản sao — đưa head thật là sửa
       chính con số đang đem đi so, phép kiểm thành vô nghĩa. */
    var h2 = { adjMachine: jpNum_(r.adjMachine), refundCustomer: jpNum_(r.refundCustomer) };
    jpCalcReport_(h2, lai);

    var so = [
      { ten: 'Doanh thu máy (revMeter)', luu: jpNum_(r.revMeter),   lai: h2.revMeter },
      { ten: 'Tiền chuyển khoản (revBank)', luu: jpNum_(r.revBank), lai: h2.revBank },
      { ten: 'Tiền mặt phải nộp (cashActual)', luu: jpNum_(r.cashActual), lai: h2.cashActual },
      { ten: 'Tổng phải thu (totalSubmit)', luu: jpNum_(r.totalSubmit), lai: h2.totalSubmit }
    ].filter(function (x) { return x.luu !== x.lai; });

    if (so.length) {
      lechTinhLai.push({
        id: jpStr_(r.id), coSo: jpStr_(r.locationName),
        kyTu: jpStr_(r.fromDate), kyDen: jpStr_(r.toDate),
        soDong: rs.length, o: so
      });
    }
  });

  /* Gom `thieuLop` theo báo cáo để giao diện có nút Xuất lại một lần cho cả phiếu
     — bấm từng dòng là 30 lần bấm cho một báo cáo. */
  var theoBc = {};
  thieuLop.forEach(function (t) {
    var key = t.reportId || ('(phiếu tay) ' + t.soChungTu);
    if (!theoBc[key]) {
      theoBc[key] = { reportId: t.reportId, soChungTu: t.soChungTu, coSo: t.coSo,
                      ngay: t.ngay, soDong: 0, tienTam: 0, dong: [] };
    }
    theoBc[key].soDong++;
    theoBc[key].tienTam += t.amount;
    if (theoBc[key].dong.length < 20) theoBc[key].dong.push(t);
  });
  var loTam = Object.keys(theoBc).sort().map(function (x) { return theoBc[x]; });

  var nhomTkCu = Object.keys(tkCu).sort().map(function (x) { return tkCu[x]; });

  /*═══════════ ⑥ KỲ CHỒNG NHAU (thêm 15/08/2026) ═══════════
   *
   * Andy 15/08 mở `RP20260815-0027` thấy bảng trắng. Truy ra: AEON Bình Tân có **bốn**
   * báo cáo đã nộp mà kỳ chồng lên nhau (01→15, 06→15, 08→15, 15→15/08). Không phép kiểm
   * nào cũ bắt được — mỗi báo cáo tự nó hoàn toàn hợp lệ.
   *
   * ⚠️ **Chồng NGÀY chưa chắc là sai tiền.** Doanh thu đi từ **chênh đồng hồ**, không đi
   * từ ngày ghi trên báo cáo. Hai kỳ chồng ngày mà chỉ số nối tiếp nhau thì tổng tiền vẫn
   * đúng — chỉ là cái nhãn kỳ sai. Nên phép này đi **HAI tầng**, và phải nói rõ tầng nào:
   *   ① chồng NGÀY  → sổ công nợ theo kỳ, gieo dòng, đối soát đều lệch
   *   ② chồng CHỈ SỐ → **tiền đếm hai lần thật**, và sổ vẫn CÂN
   * Gộp hai tầng thành một cảnh báo là hoặc báo động giả, hoặc giấu mất khoản tiền thật.
   *
   * ⚠️ CHỈ xét báo cáo **ĐÃ NỘP ít nhất một lần**. Hai bản NHÁP chồng nhau là chuyện
   * thường (mở thử rồi bỏ) — báo ở đó là cảnh báo giả, mà báo giả vài lần thì không ai
   * đọc nữa, đúng bệnh `canBang` cũ.
   *
   * ⚠️ Quét **CẢ BẢNG, không lọc theo kỳ** — y phép ④. Kỳ chồng của tháng trước vẫn còn
   * nguyên hậu quả trong sổ luỹ kế; lọc theo tháng là tháng này sạch mà sổ vẫn lệch.
   */
  var nhomKy = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    var st = jpStr_(r.status);
    if (st !== JP_ST_DONE && st !== JP_ST_PENDING && st !== JP_ST_FIXING) return;
    var key = String(r.locationId) + '|' + (jpStr_(r.machineType) || JP_TYPE_MONEY);
    (nhomKy[key] = nhomKy[key] || []).push(r);
  });

  /* Chỉ số của một ô máy trong một báo cáo — để so KHOẢNG đồng hồ, không so ngày */
  var khoangDongHo = function (rid) {
    var ra = {};
    (rowsBc[String(rid)] || []).forEach(function (r) {
      var ma = jpStr_(r.machineId) || jpStr_(r.machineCode);
      if (!ma) return;
      /* Hai loại đồng hồ, hai đơn giá — đừng gộp: 1 xung tiền = 5.000đ, 1 xu = 50.000đ */
      var tu, den, gia;
      if (!jpBlank_(r.cAfter)) {
        tu = jpNum_(r.cBefore); den = jpNum_(r.cAfter); gia = JP_RATE_COIN;
      } else {
        tu = jpNum_(r.mBefore); den = jpNum_(r.mAfter); gia = JP_RATE_MONEY_PULSE;
      }
      if (den <= tu) return;
      ra[ma] = { ma: jpStr_(r.machineCode) || ma, tu: tu, den: den, gia: gia };
    });
    return ra;
  };

  var kyChong = [], tienChongTong = 0;
  Object.keys(nhomKy).forEach(function (key) {
    var ds = nhomKy[key].sort(function (a, b) {
      return jpDate_(a.fromDate) < jpDate_(b.fromDate) ? -1 : 1;
    });
    for (var i = 0; i < ds.length; i++) {
      for (var j = i + 1; j < ds.length; j++) {
        var A = ds[i], B = ds[j];
        var aT = jpDate_(A.fromDate), aD = jpDate_(A.toDate) || aT;
        var bT = jpDate_(B.fromDate), bD = jpDate_(B.toDate) || bT;
        /* Đã sắp theo `fromDate` nên `bT > aD` là mọi cặp sau cũng thế — thoát sớm,
           đừng để thành O(n²) khi bảng phình lên vài nghìn dòng. */
        if (bT > aD) break;

        var dhA = khoangDongHo(A.id), dhB = khoangDongHo(B.id), oChung = [], tien = 0;
        Object.keys(dhA).forEach(function (ma) {
          var x = dhA[ma], y = dhB[ma];
          if (!y) return;
          var tu = Math.max(x.tu, y.tu), den = Math.min(x.den, y.den);
          if (den <= tu) return;                       // chỉ số nối tiếp — KHÔNG trùng
          var t = jpDong_((den - tu) * x.gia);
          tien += t;
          oChung.push({ o: x.ma, tu: tu, den: den, tien: t });
        });
        tienChongTong += tien;
        kyChong.push({
          locationId: String(A.locationId),
          locationName: jpStr_(A.locationName), machineType: jpStr_(A.machineType),
          a: { id: String(A.id), tu: aT, den: aD, status: jpStr_(A.status),
               rev: jpNum_(A.revMeter) },
          b: { id: String(B.id), tu: bT, den: bD, status: jpStr_(B.status),
               rev: jpNum_(B.revMeter) },
          chongTu: bT, chongDen: (aD < bD ? aD : bD),
          oTrungDongHo: oChung.slice(0, 10), soOTrung: oChung.length, tienTrung: tien
        });
      }
    }
  });
  kyChong.sort(function (a, b) { return b.tienTrung - a.tienTrung; });
  var soKyChongTien = kyChong.filter(function (x) { return x.tienTrung > 0; }).length;

  var soVanDe = thieuGiaVon.length + loTam.length + lechTinhLai.length
              + (soDongTkCu ? 1 : 0) + kyChong.length;
  return {
    ok: true, thang: k.m, nam: k.y,
    soBaoCao: bc.length,
    sach: soVanDe === 0,
    soVanDe: soVanDe,

    /* ④ Tài khoản cũ — đếm trên CẢ BẢNG, không theo kỳ (xem chú thích ở trên) */
    tkCu: nhomTkCu, soDongTkCu: soDongTkCu, tienTkCu: tienTkCu,

    thieuGiaVon: thieuGiaVon.slice(0, 50), soThieuGiaVon: thieuGiaVon.length,
    tienThieuGiaVon: tienThieuGiaVon,

    thieuLop: loTam.slice(0, 50), soLoThieuLop: loTam.length,
    soDongThieuLop: thieuLop.length,
    tienThieuLop: thieuLop.reduce(function (s, t) { return s + t.amount; }, 0),

    lechTinhLai: lechTinhLai.slice(0, 50), soLechTinhLai: lechTinhLai.length,

    /* ⑥ Kỳ chồng nhau — `soKyChongTien` là số cặp CÓ TIỀN đếm hai lần thật,
       khác `soKyChong` (chỉ chồng ngày). Giao diện phải in tách hai con số. */
    kyChong: kyChong.slice(0, 50), soKyChong: kyChong.length,
    soKyChongTien: soKyChongTien, tienKyChong: tienChongTong
  };
}

/*═══════════ ⑤ KẾT QUẢ KINH DOANH — kết chuyển 911 ═══════════*/

/**
 * Doanh thu thuần → lãi gộp → lợi nhuận trước thuế, và các cặp kết chuyển
 * cuối kỳ để kế toán gõ sang MISA.
 *
 * Andy giao 06/08/2026 sau khi gửi sơ đồ hệ thống tài khoản: JP dừng ở bảng cân
 * đối phát sinh, tức là **biết doanh thu bao nhiêu, giá vốn bao nhiêu, nhưng
 * không trừ ra**. Hai ô cuối trong checklist của sơ đồ (`Kết chuyển` · `Lãi lỗ`)
 * chưa tick được, và bước ③④ của quy trình cuối kỳ chưa có.
 *
 * ⚠️ KHÔNG LƯU GÌ — y hệt `jpSoNhatKyChung`. Không tab `JP_KetChuyen`, không nút
 * "kết chuyển", không đường hoàn lại khi mở lại báo cáo. Đây là lý do thứ tư để
 * giữ nguyên cách suy ra: kết chuyển mà lưu thì mở lại một báo cáo cũ là lãi lỗ
 * của kỳ đã chốt sai theo, mà không dòng nào giải thích.
 *
 * ⚠️ CHỈ TÍNH TRONG KỲ. Khác `jpBangCanDoiPhatSinh` (cộng cả trước kỳ để ra dư
 * đầu): tài khoản kết quả không có số dư mang sang — cộng từ đầu là lãi tháng 8
 * gồm cả lãi tháng 7.
 *
 * ⚠️ Đầu nhận lãi CỐ Ý ĐỂ TRỐNG. Xem `JP_TK_LAI_LO` ở `00_Config`: bảng tài khoản
 * không có con nào của JP dưới 421, hai con đang có là của khối khác. Hàm trả
 * `thieuTkLaiLo: true` để giao diện in rõ thay vì lặng lẽ chọn hộ một đầu sai.
 */
function jpKetQuaKinhDoanh(token, thang, nam) {
  jpNeedKT_(jpAuth_(token));
  var k = jpMocKy_(thang, nam);
  var g = jpGomButToan_(k.cuoi);

  /*── ĐƯỜNG ①: cộng từ BÚT TOÁN ──*/
  var ps = {};
  var lay = function (tk) {
    if (!ps[tk]) ps[tk] = { no: 0, co: 0 };
    return ps[tk];
  };
  var soDongKy = 0;
  g.bt.forEach(function (x) {
    if (x.ngay < k.dau || x.ngay > k.cuoi) return;   // CHỈ trong kỳ
    soDongKy++;
    lay(x.tkNo).no += x.soTien;
    lay(x.tkCo).co += x.soTien;
  });
  /* Tài khoản doanh thu mang bản chất CÓ, chi phí mang bản chất NỢ. Lấy số
     THUẦN chứ không lấy một vế — dòng đảo (kiểm kê giảm xuất ghi số âm, hoàn
     khách) mới trừ ra đúng. */
  var duCo = function (tk) { var a = ps[tk] || { no: 0, co: 0 }; return a.co - a.no; };
  var duNo = function (tk) { var a = ps[tk] || { no: 0, co: 0 }; return a.no - a.co; };

  var doanhThu   = duCo(JP_TK_DOANH_THU);        // 5111
  var giamTru    = duNo(JP_TK_GIAM_TRU_DT);      // 5213
  var giaVon     = duNo(JP_TK_GIA_VON);          // 6321
  var chiPhiBH   = duNo(JP_TK_CHI_PHI_BH);       // 64116
  var thuNhapKhac = duCo(JP_TK_THU_NHAP_KHAC);   // 711
  var chiPhiKhac  = duNo(JP_TK_CHI_PHI_KHAC);    // 811

  var doanhThuThuan = doanhThu - giamTru;
  var laiGop        = doanhThuThuan - giaVon;
  var loiNhuanThuan = laiGop - chiPhiBH;
  var loiNhuanKhac  = thuNhapKhac - chiPhiKhac;
  var lnTruocThue   = loiNhuanThuan + loiNhuanKhac;

  /*── ĐƯỜNG ②: cộng THẲNG từ cột gốc, không đi qua bộ dựng bút toán ──
     Đây mới là phép kiểm thật. Nếu `jpButToanBaoCao_` ghi sai đầu, thiếu một
     cặp, hay lọc ngày lệch thì hai đường rời nhau ngay.
     ⚠️ NÓI THẬT về sức mạnh của từng phép, đừng để người đọc tưởng nó mạnh hơn
     thực tế — đó là bài học `canBang` cũ:
       · doanh thu / giảm trừ / lệch máy: ĐỘC LẬP THẬT (một bên qua bộ dựng bút
         toán, một bên đọc thẳng cột `revMeter`/`adjMachine`/hoàn khách).
       · giá vốn / chi phí BH: CÙNG đọc `JP_KhoXuat.amount`, chỉ khác đường đi
         trong code. Bắt được lỗi suy đầu tài khoản mặc định và lỗi lọc ngày —
         KHÔNG bắt được `amount` sai. Phép ③ của `jpQuetDayChuyen` lo phần đó. */
  var dt2 = 0, gt2 = 0, tnk2 = 0, cpk2 = 0;
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    if (jpStr_(r.status) !== JP_ST_DONE) return;
    var ngay = (jpDate_(r.toDate) || jpDate_(r.fromDate));
    if (!ngay || ngay < k.dau || ngay > k.cuoi) return;
    dt2 += jpNum_(r.revMeter);
    gt2 += jpHoanTong_(r);
    var adj = jpNum_(r.adjMachine);
    if (adj > 0) tnk2 += adj; else cpk2 += -adj;
  });

  var gv2 = 0, cp2 = 0;
  jpRows_(JP_TABS.KHO_XUAT).forEach(function (x) {
    var ngay = jpDate_(x.ngay);
    if (!ngay || ngay < k.dau || ngay > k.cuoi) return;
    var tien = jpNum_(x.amount);
    if (!tien) return;
    var no = jpStr_(x.tkNo) || JP_TK_GIA_VON;
    if (/^632/.test(no)) gv2 += tien;
    else if (/^641/.test(no)) cp2 += tien;
  });

  var lech = [];
  var soSanh = function (ten, a, b, that) {
    if (Math.round(a) !== Math.round(b))
      lech.push({ khoan: ten, tuButToan: Math.round(a), tuChungTu: Math.round(b),
                  lech: Math.round(a - b), doclap: !!that });
  };
  soSanh('Doanh thu 5111',      doanhThu,    dt2,  true);
  soSanh('Giảm trừ 5213',       giamTru,     gt2,  true);
  soSanh('Thu nhập khác 711',   thuNhapKhac, tnk2, true);
  soSanh('Chi phí khác 811',    chiPhiKhac,  cpk2, true);
  soSanh('Giá vốn 6321',        giaVon,      gv2,  false);
  soSanh('Chi phí bán hàng 64116', chiPhiBH, cp2,  false);

  /*── Các cặp KẾT CHUYỂN — đúng thứ tự VAS, bỏ cặp bằng 0 ──
     In ra để kế toán gõ sang MISA. JP không ghi chúng vào đâu cả. */
  var kc = [], stt = 0;
  var them = function (no, co, tien, dg) {
    if (!Math.round(tien)) return;
    kc.push({ stt: ++stt, tkNo: no, tkCo: co, soTien: Math.round(tien), dienGiai: dg,
              tenNo: JP_TEN_TK[no] || '', tenCo: JP_TEN_TK[co] || '' });
  };
  them(JP_TK_DOANH_THU, JP_TK_GIAM_TRU_DT, giamTru,
       'Kết chuyển các khoản giảm trừ doanh thu');
  them(JP_TK_DOANH_THU, JP_TK_KQKD, doanhThuThuan, 'Kết chuyển doanh thu thuần');
  them(JP_TK_THU_NHAP_KHAC, JP_TK_KQKD, thuNhapKhac, 'Kết chuyển thu nhập khác');
  them(JP_TK_KQKD, JP_TK_GIA_VON, giaVon, 'Kết chuyển giá vốn hàng bán');
  them(JP_TK_KQKD, JP_TK_CHI_PHI_BH, chiPhiBH, 'Kết chuyển chi phí bán hàng');
  them(JP_TK_KQKD, JP_TK_CHI_PHI_KHAC, chiPhiKhac, 'Kết chuyển chi phí khác');

  /* Bước cuối: 911 → 421. Đầu nhận CHƯA CHỐT nên để trống chứ không đoán. */
  var tkLai = jpStr_(JP_TK_LAI_LO);
  var buocCuoi = null;
  if (Math.round(lnTruocThue)) {
    buocCuoi = {
      stt: stt + 1,
      tkNo: lnTruocThue > 0 ? JP_TK_KQKD : (tkLai || '421?'),
      tkCo: lnTruocThue > 0 ? (tkLai || '421?') : JP_TK_KQKD,
      soTien: Math.abs(Math.round(lnTruocThue)),
      dienGiai: 'Kết chuyển ' + (lnTruocThue > 0 ? 'LÃI' : 'LỖ') + ' trước thuế',
      chuaChotDau: !tkLai
    };
  }

  /* 911 phải TRIỆT TIÊU sau khi kết chuyển hết.
     ⚠️ Cộng từ CÁC DÒNG ĐÃ PHÁT RA (`kc` + `buocCuoi`), KHÔNG cộng lại từ mấy
     biến `giaVon`/`doanhThuThuan`. Bản đầu em viết:
         no911 = giaVon + chiPhiBH + chiPhiKhac + lnTruocThue
         co911 = doanhThuThuan + thuNhapKhac
     Khai triển `lnTruocThue` ra thì hai vế **giống hệt nhau về mặt đại số** ⇒
     luôn bằng, luôn "triệt tiêu", không bao giờ bắt được gì — đúng y bẫy
     `canBang` cũ mà cả repo này đi sửa. Đọc từ dòng phát ra thì bắt được thật:
     thiếu một lời gọi `them(...)`, đảo nhầm `tkNo`/`tkCo`, hoặc lệch làm tròn
     giữa từng dòng và số tổng. */
  var no911 = 0, co911 = 0;
  kc.concat(buocCuoi ? [buocCuoi] : []).forEach(function (x) {
    if (x.tkNo === JP_TK_KQKD) no911 += x.soTien;
    if (x.tkCo === JP_TK_KQKD) co911 += x.soTien;
  });

  return {
    ok: true, thang: k.m, nam: k.y, tuNgay: k.dau, denNgay: k.cuoi,
    soDongTrongKy: soDongKy,

    doanhThu: Math.round(doanhThu),
    giamTru: Math.round(giamTru),
    doanhThuThuan: Math.round(doanhThuThuan),
    giaVon: Math.round(giaVon),
    laiGop: Math.round(laiGop),
    tyLeLaiGop: doanhThuThuan ? Math.round(laiGop / doanhThuThuan * 1000) / 10 : null,
    chiPhiBH: Math.round(chiPhiBH),
    loiNhuanThuan: Math.round(loiNhuanThuan),
    thuNhapKhac: Math.round(thuNhapKhac),
    chiPhiKhac: Math.round(chiPhiKhac),
    loiNhuanKhac: Math.round(loiNhuanKhac),
    lnTruocThue: Math.round(lnTruocThue),

    ketChuyen: kc, buocCuoi: buocCuoi,
    tkKqkd: JP_TK_KQKD, tkLaiLo: tkLai, thieuTkLaiLo: !tkLai,
    no911: Math.round(no911), co911: Math.round(co911),
    trietTieu911: no911 === co911,

    lech: lech, khop: lech.length === 0,
    boQua: g.boQua, tkTam: JP_TK_TAM, vatSuat: JP_VAT_SUAT
  };
}

