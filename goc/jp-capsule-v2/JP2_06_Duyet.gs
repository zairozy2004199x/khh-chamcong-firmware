/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2 — WEB KẾ TOÁN  ·  06_Duyet
 * ---------------------------------------------------------------------------
 * MỘT KẾ TOÁN, MỘT CHỮ KÝ (Andy chốt 04/08/2026).
 *
 *  · Kế toán xem toàn bộ báo cáo (tiền + hàng) rồi ký một lần ⇒ HOÀN TẤT.
 *  · Hoàn tất mới khoá kỳ và mới cho nối kỳ sau.
 *  · Nhân viên sửa lại bất cứ số nào ⇒ chữ ký bị huỷ, phải ký lại.
 *
 * ĐÃ BỎ: duyệt song song 2 kế toán (KT doanh thu + KT kho) và "reset thông minh"
 * theo từng phần. Bốn cột apprRevBy/apprRevAt/apprStockBy/apprStockAt vẫn còn
 * trong sheet để đọc báo cáo đã duyệt theo luồng cũ — xem `jpChuKy_`.
 *
 * ⚠️ Đánh đổi đã biết: một người ký là xong, không còn kiểm tra chéo giữa số tiền
 * và số hàng. Muốn quay lại 2 nhánh thì sửa ở đúng file này + KtJs02_Duyet.
 *═══════════════════════════════════════════════════════════════════════════*/

var JP_PART_REV   = 'REV';     // phần doanh thu
var JP_PART_STOCK = 'STOCK';   // phần hàng hoá

/**
 * HAI chữ ký trên MỘT báo cáo, do cùng một tài khoản kế toán ký (Andy chốt
 * 04/08/2026): ký phần doanh thu và ký phần kho là hai lần bấm riêng, nhưng
 * **cả hai lần đều xem bố cục đầy đủ của báo cáo** — không cắt màn hình theo phần.
 *
 * Lý do giữ hai chữ ký dù một người: ký xong biết rõ đã soát tới tiền hay tới hàng,
 * không bỏ sót một nửa.
 *
 * Đọc được cả hai đời dữ liệu cũ:
 *  · luồng 2 kế toán (tới 03/08): apprRevBy / apprStockBy — đúng chỗ đang dùng lại
 *  · luồng 1 chữ ký gộp (03–04/08): apprBy — coi như đã ký cả hai phần
 */
function jpChuKy_(head) {
  if (!head) {
    return { rev: { by: '', at: '' }, stock: { by: '', at: '' }, duCaHai: false };
  }
  var gop = jpStr_(head.apprBy);
  var rev = jpStr_(head.apprRevBy), sto = jpStr_(head.apprStockBy);

  /* ⚠️ PHẢI qua `jpNgayGio_`. `jpKtApprove` ghi `new Date()` vào sheet nên đọc
     lại là ĐỐI TƯỢNG DATE; trả thẳng nó về client thì `google.script.run` đóng
     gói không được, client nhận **null** và chết ở `d.head` — mà máy chủ vẫn
     báo "Đã hoàn thành" nên nhật ký thực thi sạch, không lần ra được.
     Chỉ báo cáo ĐÃ KÝ mới có mấy cột này ⇒ đúng những báo cáo đã duyệt mới hỏng. */
  var oRev = rev ? { by: rev, at: jpNgayGio_(head.apprRevAt) }
                 : (gop ? { by: gop, at: jpNgayGio_(head.apprAt), gop: true } : { by: '', at: '' });
  var oSto = sto ? { by: sto, at: jpNgayGio_(head.apprStockAt) }
                 : (gop ? { by: gop, at: jpNgayGio_(head.apprAt), gop: true } : { by: '', at: '' });

  return { rev: oRev, stock: oSto, duCaHai: !!(oRev.by && oSto.by) };
}

/** Chữ ký của một phần. part = 'REV' | 'STOCK'. */
function jpChuKyPhan_(head, part) {
  var k = jpChuKy_(head);
  return part === JP_PART_STOCK ? k.stock : k.rev;
}

function jpTenPhan_(part) {
  return part === JP_PART_STOCK ? 'hàng hoá' : 'doanh thu';
}

/**
 * TÌNH TRẠNG CHỮ KÝ + HẬU QUẢ — **nguồn duy nhất của câu chữ** (Andy 11/08/2026).
 *
 * Kế toán ký phần HÀNG HOÁ cho `RP20260806-0007` (VICOM 3/2) lúc 09/08 23:30 rồi báo
 * *"duyệt kho mà không thấy trừ tồn"*. Dữ liệu đúng luật: `jpKtApprove` chỉ gọi
 * `jpXuatKhoBaoCao_` khi báo cáo thành **HOÀN TẤT**, mà HOÀN TẤT cần **đủ hai chữ ký**
 * — nhật ký ghi `{"xong":false}` và không có dòng `APPROVE_REV` nào.
 *
 * ⚠️ Nhưng đó là **lỗi của app**, không phải của kế toán. Câu cũ chỉ nói TRẠNG THÁI
 * (*"Đã duyệt phần hàng hoá · còn phần doanh thu"*) chứ không nói **HẬU QUẢ**, và nó là
 * một toast — đóng đi là **không còn dấu vết ở đâu**. Người đọc thấy chữ "Đã duyệt" thì
 * tin là xong, hợp lý. Đúng bệnh "cảnh báo chỉ hiện MỘT LẦN rồi mất" đã mắc ở chỗ khác.
 *
 * ⚠️ Câu chữ do MÁY CHỦ dựng, client KHÔNG tự ghép — ba chỗ dùng chung (thông báo sau
 * khi ký · dải băng ở màn chi tiết · bộ lọc danh sách). Ghép ở client là ba câu cho
 * cùng một việc, sửa một chỗ quên hai chỗ kia (bài học của `jpCanhBaoHead_`).
 *
 * `motPhan` = ký ĐÚNG MỘT phần và chưa hoàn tất — đó mới là trạng thái KẸT. Báo cáo
 * chưa ai ký cũng "chưa trừ kho", nhưng đó là bình thường, không phải chỗ cần báo động.
 */
function jpKetKy_(head) {
  var k = jpChuKy_(head);
  var xong = jpStr_(head && head.status) === JP_ST_DONE;
  var coRev = !!k.rev.by, coSto = !!k.stock.by;
  var motPhan = !xong && (coRev !== coSto);

  var ra = {
    duCaHai: k.duCaHai, hoanTat: xong, motPhan: motPhan,
    daKy: motPhan ? (coRev ? JP_PART_REV : JP_PART_STOCK) : '',
    thieu: motPhan ? (coRev ? JP_PART_STOCK : JP_PART_REV) : '',
    msg: ''
  };
  if (!motPhan) return ra;

  var ai = coRev ? k.rev : k.stock;
  ra.msg = 'Đã ký phần ' + jpTenPhan_(ra.daKy) +
    (ai.by ? ' (' + ai.by + (ai.at ? ' · ' + ai.at : '') + ')' : '') +
    ' — nhưng báo cáo CHƯA HOÀN TẤT nên CHƯA trừ kho và CHƯA vào sổ công nợ. ' +
    'Còn thiếu chữ ký phần ' + jpTenPhan_(ra.thieu).toUpperCase() + '.';
  return ra;
}

/*──────────────────── ① DANH SÁCH CHỜ DUYỆT ────────────────────*/

function jpKtListReports(token, f) {
  var u = jpNeedKT_(jpAuth_(token));
  f = f || {};

  var list = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    if (r.status === JP_ST_DRAFT) return false;            // nháp thì kế toán không thấy
    if (f.status && r.status !== f.status) return false;
    if (f.locationId && String(r.locationId) !== String(f.locationId)) return false;
    if (f.fromDate && jpDate_(r.toDate) < jpDate_(f.fromDate)) return false;
    if (f.toDate && jpDate_(r.fromDate) > jpDate_(f.toDate)) return false;
    if (f.onlyMine) {                       // "chỉ báo cáo còn thiếu chữ ký"
      if (jpChuKy_(r).duCaHai) return false;
      if (r.status === JP_ST_DONE) return false;
    }
    /* ⚠️ KHÁC `onlyMine`: cái kia gồm CẢ báo cáo chưa ai ký (bình thường, đang chờ).
       Cái này chỉ lấy báo cáo ký ĐÚNG MỘT phần — trạng thái KẸT, nhìn ở danh sách
       không phân biệt được vì nó vẫn mang `CHO_DUYET` y như báo cáo chưa ai đụng. */
    if (f.motPhan && !jpKetKy_(r).motPhan) return false;
    return true;
  });

  return list
    .sort(function (a, b) { return jpDate_(a.fromDate) < jpDate_(b.fromDate) ? 1 : -1; })
    .map(function (r) {
      return {
        id: String(r.id),
        locationName: jpStr_(r.locationName), maKH: jpStr_(r.maKH),
        machineType: jpStr_(r.machineType),
        fromDate: jpDate_(r.fromDate), toDate: jpDate_(r.toDate),
        userName: jpStr_(r.userName), status: jpStr_(r.status),
        revMeter: jpNum_(r.revMeter), revBank: jpNum_(r.revBank),
        adjMachine: jpNum_(r.adjMachine), refundCustomer: jpNum_(r.refundCustomer),
        refundRows: jpNum_(r.refundRows), refundTotal: jpHoanTong_(r),
        cashActual: jpNum_(r.cashActual), totalSubmit: jpNum_(r.totalSubmit),
        /* Cộng cả cảnh báo thiếu ảnh — con số trên thẻ phải là tổng thật */
        warnCount: jpNum_(r.warnCount) + jpPhotoWarnList_(r).length,
        chuKy: jpChuKy_(r),
        rejectPart: jpStr_(r.rejectPart), rejectReason: jpStr_(r.rejectReason),
        payStatus: jpStr_(r.payStatus), paid: jpNum_(r.paid),
        canSignRev: jpCanSign_(u, r, JP_PART_REV),
        canSignStock: jpCanSign_(u, r, JP_PART_STOCK),
        ketKy: jpKetKy_(r)
      };
    });
}

/** Cảnh báo thiếu ảnh đã chốt trong báo cáo. Hỏng JSON thì coi như không có. */
function jpPhotoWarnList_(head) {
  if (!head || !jpStr_(head.photoWarnJson)) return [];
  try {
    var a = JSON.parse(head.photoWarnJson);
    return (a && a.length) ? a : [];
  } catch (e) { return []; }
}

function jpCanSign_(u, head, part) {
  /* Chỉ ký khi đang CHỜ DUYỆT. CAN_SUA là đang chờ nhân viên nộp lại. */
  if (head.status !== JP_ST_PENDING) return false;
  return !jpChuKyPhan_(head, part).by;
}

/*──────────────────── ② XEM CHI TIẾT (cả 2 kế toán thấy đủ) ────────────────────*/

function jpKtGetReport(token, reportId) {
  var u = jpNeedKT_(jpAuth_(token));
  var d = jpGetReport(token, reportId);

  var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);

  /* Một kế toán duyệt tất cả ⇒ MỘT danh sách cảnh báo, không chia phần nữa */
  var warns = [];
  d.rows.forEach(function (r) {
    (r.warns || []).forEach(function (w) {
      warns.push({
        rowId: r.id, machineCode: r.machineCode, itemCode: r.itemCode,
        code: w.code, part: w.part, msg: w.msg, detail: w.detail
      });
    });
  });

  /*
   * Cảnh báo cấp BÁO CÁO (kỳ chồng, bảng tổng lệch, thiếu lý do) — trước bản này kế
   * toán **không thấy** chúng: `warns` chỉ gom từ từng dòng và từ ảnh. Mà người sắp KÝ
   * mới là người cần biết báo cáo này chồng kỳ với bản khác.
   * `rowId` để rỗng vì chúng thuộc cả báo cáo, không thuộc dòng nào.
   */
  (d.headWarns || []).forEach(function (w) {
    warns.push({ rowId: '', machineCode: '', itemCode: '',
                 code: w.code, part: w.part, msg: w.msg, detail: w.detail });
  });

  /* Cảnh báo thiếu ảnh đã chốt lúc nộp */
  jpPhotoWarnList_(head).forEach(function (w) {
    warns.push({
      rowId: '', machineCode: '', itemCode: '',
      code: w.code, part: w.part, msg: w.msg, detail: w.detail
    });
  });
  /*
   * Cả hai phần đều nhận TOÀN BỘ báo cáo: cùng danh sách dòng, cùng danh sách
   * cảnh báo. `part` trên mỗi cảnh báo chỉ để tô màu / nhóm lại cho dễ nhìn,
   * KHÔNG dùng để cắt bớt cái kế toán được xem.
   */
  d.canSignRev = jpCanSign_(u, head, JP_PART_REV);
  d.canSignStock = jpCanSign_(u, head, JP_PART_STOCK);
  /* Gộp SAU CÙNG, khi danh sách đã đủ cả cảnh báo dòng + cảnh báo báo cáo + thiếu ảnh
     — gộp giữa chừng thì thứ tự phụ thuộc chỗ gọi. */
  d.warnings = jpGopCanhBao_(warns);
  d.warnGoc  = warns.length;      // số cảnh báo THẬT, để nói ra đã gộp bao nhiêu
  d.signState = jpChuKy_(head);
  /* Lệch giữa doanh thu theo CHỈ SỐ MÁY và phần NHÂN VIÊN TỰ NHẬP (Andy 07/08/2026).
     Chỉ ĐỌC, không ghi vào head — `jpCalcReport_` vẫn là chỗ duy nhất được ghi.
     `null` khi không có hai đường độc lập để so; giao diện ẩn hẳn khối, đừng in
     bảng toàn số 0 rồi kết luận "khớp". */
  d.tienVsHang = jpTienVsHang_(head, d.rows);
  /* Dải băng CỐ ĐỊNH ở màn chi tiết — toast biến mất, dải băng thì không. */
  d.ketKy = jpKetKy_(head);
  return d;
}

/**
 * GỘP CẢNH BÁO CÙNG LOẠI VỀ MỘT DÒNG (Andy 16/08/2026: *"gộp W9 thành một dòng đi em"*).
 *
 * Đo trên sheet thật 16/08: một báo cáo 37 cảnh báo, trong đó **18 dòng `W9`** đều nói
 * cùng một chuyện — máy nhả tiền theo xung nên tiền lẻ không chia hết cho giá 1 trứng.
 * Đó là chuyện **đúng, không phải lỗi**, nhưng nó nổ trên MỌI dòng có lẻ và đẩy những
 * cảnh báo thật sự cần nhìn (`W2` lệch tồn, `W4` lệch Pay Box) xuống dưới một đống chữ.
 * Đúng bệnh `canBang` cũ: báo sai/báo thừa vài lần là không ai đọc nữa.
 *
 * ⚠️⚠️ **CHỈ gộp cảnh báo có `gop`** — danh sách CHO PHÉP, xem `JP_WARN` ở `00_Config`.
 * Gộp theo `code` là **giấu mất `W14`** (mã không suy ra được giá): nó từng dùng chung
 * `W9`, mà nó nói rằng cả dòng đó **không đối chiếu tiền với hàng được** — biến nó
 * thành một con số trong dòng "dư tiền lẻ" là mất hẳn một đường kiểm, màn hình vẫn sạch.
 *
 * ⚠️ **Giữ ĐÚNG chỗ cái đầu tiên xuất hiện**, đừng dồn nhóm xuống cuối: kế toán đọc
 * theo thứ tự dòng của báo cáo, dời chỗ là mất mối liên hệ với dòng đang xem.
 *
 * ⚠️ Cộng bằng trường `so`, **không moi số ra từ `detail`** — xem chú thích ở `jpWarn_`.
 *
 * ⚠️ Cảnh báo LƯU TỪ TRƯỚC bản này không có trường `gop`, nên chúng in ra từng cái như
 * cũ cho tới khi lượt chạy nền tính lại (`jpTinhLaiCanhBaoQuet_`, tối đa 5 phút). Rơi
 * về "hơi dài" chứ không rơi về "giấu mất" — đúng chiều an toàn.
 */
function jpGopCanhBao_(list) {
  var ra = [], nhom = {};
  (list || []).forEach(function (w) {
    if (!w.gop) { ra.push(w); return; }
    var g = nhom[w.gop];
    if (!g) {
      g = nhom[w.gop] = {
        rowId: '', machineCode: '', itemCode: '',
        code: w.code, part: w.part, msg: w.msg, detail: '',
        gop: w.gop, soDong: 0, tong: 0
      };
      ra.push(g);
    }
    g.soDong++;
    g.tong += jpNum_(w.so);
  });

  ra.forEach(function (w) {
    if (!w.gop) return;
    var def = jpWarnDef_(w.code);
    var dv = (def && def.gopDv) || '';
    var ghi = (def && def.gopGhiChu) || '';
    /* Gộp mà chỉ có MỘT dòng thì đừng in "1 dòng" — nó gợi ý có nhiều cái bị thu lại. */
    w.detail = (w.soDong > 1 ? w.soDong + ' dòng' : '1 dòng') +
      (w.tong ? ' · tổng dư ' + jpMoney_(w.tong) + dv : '') +
      (ghi ? ' · ' + ghi : '');
  });
  return ra;
}

/** Tra định nghĩa cảnh báo theo mã. Dùng để lấy `gopDv` / `gopGhiChu` lúc gộp. */
function jpWarnDef_(code) {
  var k = Object.keys(JP_WARN);
  for (var i = 0; i < k.length; i++) {
    if (JP_WARN[k[i]].code === code) return JP_WARN[k[i]];
  }
  return null;
}

/*──────────────────── ③ DUYỆT ────────────────────*/

/**
 * Ký MỘT phần. part = 'REV' (doanh thu) | 'STOCK' (hàng hoá).
 * Đủ cả hai phần ⇒ HOÀN TẤT ⇒ mới khoá kỳ và mới cho nối kỳ sau.
 */
function jpKtApprove(token, reportId, part, note) {
  var u = jpNeedKT_(jpAuth_(token));
  var ph = (jpStr_(part).toUpperCase() === JP_PART_STOCK) ? JP_PART_STOCK : JP_PART_REV;

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');
    if (head.status === JP_ST_DRAFT) throw new Error('Báo cáo chưa nộp');
    if (head.status === JP_ST_FIXING) throw new Error('Báo cáo đang chờ nhân viên sửa');
    if (head.status === JP_ST_DONE) {
      return { ok: true, status: JP_ST_DONE, msg: 'Đã hoàn tất trước đó' };
    }

    var k = jpChuKy_(head);
    var daKy = (ph === JP_PART_STOCK) ? k.stock : k.rev;
    if (daKy.by) {
      return { ok: true, status: head.status,
               msg: 'Phần ' + jpTenPhan_(ph) + ' đã được ' + daKy.by + ' duyệt' };
    }

    var f = {};
    if (ph === JP_PART_STOCK) { f.apprStockBy = u.hoTen; f.apprStockAt = new Date(); }
    else                      { f.apprRevBy = u.hoTen;   f.apprRevAt = new Date(); }

    /* Phần còn lại đã ký chưa? Ký rồi thì báo cáo hoàn tất. */
    var conLai = (ph === JP_PART_STOCK) ? k.rev : k.stock;
    var xong = !!conLai.by;
    if (xong) f.status = JP_ST_DONE;

    jpFields_(JP_TABS.REPORTS, head._row, f);
    jpAudit_(u, ph === JP_PART_STOCK ? 'APPROVE_STOCK' : 'APPROVE_REV', reportId,
             head.locationName, { note: jpStr_(note), xong: xong });

    /* HOÀN TẤT là mốc duy nhất được ra sổ kho. Xuất kho không throw ra ngoài —
       duyệt xong rồi thì phải xong, sổ kho lỗi thì chạy lại bằng jpKhoXuatLai. */
    var kho = null;
    if (xong) {
      head.status = JP_ST_DONE;
      kho = jpXuatKhoBaoCao_(head, u);
    }

    /* ⚠️ `head` là bản đọc TRƯỚC khi ghi chữ ký vừa rồi, nên phải chồng `f` lên mới
       hỏi ra tình trạng MỚI — hỏi trên `head` thô là trả về tình trạng CŨ, tức câu
       thông báo nói sai ngay lúc người dùng cần nó nhất. */
    var ket = null;
    if (!xong) {
      var hMoi = {};
      Object.keys(head).forEach(function (c) { hMoi[c] = head[c]; });
      Object.keys(f).forEach(function (c) { hMoi[c] = f[c]; });
      ket = jpKetKy_(hMoi);
    }

    return {
      ok: true, part: ph, done: xong,
      status: xong ? JP_ST_DONE : head.status,
      kho: kho,
      /* Chưa xong thì câu này phải nói HẬU QUẢ, không chỉ nói trạng thái — xem
         `jpKetKy_`. `head` vừa được cập nhật chữ ký ở trên nên phải gộp `f` vào
         trước khi hỏi, không thì nó đọc ra tình trạng CŨ. */
      ketKy: ket,
      msg: xong ? 'Đã ký đủ hai phần — báo cáo HOÀN TẤT' + jpKhoMsg_(kho) : ket.msg
    };
  });
}

/** Một câu kể kết quả xuất kho, gắn vào thông báo duyệt. */
function jpKhoMsg_(kho) {
  if (!kho) return '';
  if (kho.loi) {
    return ' · ⚠ SỔ KHO CHƯA GHI ĐƯỢC (' + kho.loi + '). Báo cáo vẫn hoàn tất; ' +
           'vào Hàng hoá → Sổ 632 bấm "Xuất lại" cho báo cáo này.';
  }
  var s = ' · xuất kho ' + kho.soDong + ' dòng, giá vốn ' + jpMoney_(kho.tongGiaVon) + 'đ';
  if (kho.thieuLop && kho.thieuLop.length) {
    s += ' (⚠ ' + kho.thieuLop.length + ' dòng thiếu lớp tồn — kiểm lại phiếu nhập)';
  }
  return s;
}

/*──────────────────── ④ TRẢ VỀ SỬA ────────────────────*/

function jpKtReject(token, reportId, reason) {
  var u = jpNeedKT_(jpAuth_(token));
  var r = jpStr_(reason);
  if (!r) throw new Error('Nhập lý do trả về');

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');
    if (head.status === JP_ST_DRAFT) throw new Error('Báo cáo chưa nộp');

    jpFields_(JP_TABS.REPORTS, head._row, {
      status: JP_ST_FIXING,
      rejectBy: u.hoTen, rejectAt: new Date(),
      rejectPart: 'ALL', rejectReason: r,
      apprBy: '', apprAt: '',
      apprRevBy: '', apprRevAt: '', apprStockBy: '', apprStockAt: ''
    });
    jpAudit_(u, 'REJECT', reportId, head.locationName, r);

    /* Trả về một báo cáo ĐÃ HOÀN TẤT thì phải hoàn kho — không thì sổ 632 còn
       dòng của một báo cáo đang chờ sửa, và lớp tồn thiếu số đã trừ. */
    var kho = jpHoanKhoBaoCao_(head, u);

    return { ok: true, status: JP_ST_FIXING, kho: kho,
             msg: 'Đã trả về cho nhân viên' +
                  (kho ? ' · đã hoàn kho ' + kho.soDong + ' dòng' : '') };
  });
}

/*──────────────────── ⑤ RESET THÔNG MINH ────────────────────*/

/**
 * Nhân viên sửa phần nào thì chỉ chữ ký phần đó bị huỷ.
 *
 * Có ý nghĩa lại từ khi quay về hai chữ ký: sửa một con số tiền thì không có lý
 * gì bắt kế toán soát lại toàn bộ bảng hàng hoá.
 *
 * Chữ ký gộp `apprBy` (đời một-chữ-ký, 03–04/08) tính là ký cả hai phần, nên sửa
 * phần nào cũng phải xoá nó — không thì nó "hồi sinh" chữ ký vừa bị huỷ.
 */
function jpResetSignatures_(head, changedParts, user) {
  if (!changedParts || !changedParts.length) return;
  var k = jpChuKy_(head);
  var f = {}, msg = [];

  var suaRev = changedParts.indexOf(JP_PART_REV) >= 0;
  var suaSto = changedParts.indexOf(JP_PART_STOCK) >= 0;

  if (suaRev && k.rev.by)   { f.apprRevBy = '';   f.apprRevAt = '';   msg.push('doanh thu'); }
  if (suaSto && k.stock.by) { f.apprStockBy = ''; f.apprStockAt = ''; msg.push('hàng hoá'); }
  if (!msg.length) return;

  /* Xoá chữ ký gộp, nhưng giữ lại cho phần KHÔNG bị sửa */
  if (jpStr_(head.apprBy)) {
    f.apprBy = ''; f.apprAt = '';
    if (!suaRev) { f.apprRevBy = head.apprBy;   f.apprRevAt = head.apprAt || new Date(); }
    if (!suaSto) { f.apprStockBy = head.apprBy; f.apprStockAt = head.apprAt || new Date(); }
  }

  /* Huỷ chữ ký thì báo cáo không còn hoàn tất */
  if (jpStr_(head.status) === JP_ST_DONE) f.status = JP_ST_PENDING;

  jpFields_(JP_TABS.REPORTS, head._row, f);
  jpAudit_(user, 'RESET_SIGN', head.id, head.locationName,
           'Huỷ chữ ký: ' + msg.join(', '));
}

/**
 * Phần nào có số đổi. Trả mảng rỗng nếu không đổi gì — để không huỷ chữ ký oan
 * khi nhân viên chỉ bấm Lưu mà không sửa gì.
 *
 * Phân loại theo NGƯỜI SOÁT chứ không theo cột: chỉ số đồng hồ và chuyển khoản
 * là việc của phần doanh thu; tồn kho, bổ sung, lỗi/mẫu, trả kho là phần hàng hoá.
 */
function jpDiffParts_(oldHead, oldRows, newHead, newRows) {
  var parts = {};

  if (jpNum_(oldHead.revMeter) !== jpNum_(newHead.revMeter) ||
      jpNum_(oldHead.revBank) !== jpNum_(newHead.revBank) ||
      jpNum_(oldHead.adjMachine) !== jpNum_(newHead.adjMachine) ||
      jpNum_(oldHead.refundCustomer) !== jpNum_(newHead.refundCustomer) ||
      jpNum_(oldHead.refundRows) !== jpNum_(newHead.refundRows)) {
    parts[JP_PART_REV] = 1;
  }

  var oldMap = {}, newMap = {};
  oldRows.forEach(function (r) { oldMap[String(r.id)] = r; });
  newRows.forEach(function (r) { newMap[String(r.id)] = r; });

  var keys = {};
  Object.keys(oldMap).forEach(function (k) { keys[k] = 1; });
  Object.keys(newMap).forEach(function (k) { keys[k] = 1; });

  Object.keys(keys).forEach(function (k) {
    var a = oldMap[k], b = newMap[k];
    if (!a || !b) {                                // thêm hoặc xoá dòng: đụng cả hai
      parts[JP_PART_REV] = 1; parts[JP_PART_STOCK] = 1; return;
    }
    if (jpNum_(a.mAfter) !== jpNum_(b.mAfter) ||
        jpNum_(a.cAfter) !== jpNum_(b.cAfter) ||
        jpNum_(a.bank) !== jpNum_(b.bank) ||
        /* Bảng tiền mẫu TÁCH — ba ô nhân viên gõ (Andy chốt 12/08/2026) */
        jpNum_(a.amount) !== jpNum_(b.amount) ||
        jpNum_(a.cash) !== jpNum_(b.cash) ||
        jpNum_(a.cashReal) !== jpNum_(b.cashReal)) parts[JP_PART_REV] = 1;
    if (jpNum_(a.stockActual) !== jpNum_(b.stockActual) ||
        jpNum_(a.addQty1) !== jpNum_(b.addQty1) ||
        jpNum_(a.addQty2) !== jpNum_(b.addQty2) ||
        jpNum_(a.defectQty) !== jpNum_(b.defectQty) ||
        jpNum_(a.returnQty) !== jpNum_(b.returnQty)) parts[JP_PART_STOCK] = 1;
  });

  return Object.keys(parts);
}

/*──────────────────── ⑥ MỞ LẠI BÁO CÁO ĐÃ HOÀN TẤT ────────────────────*/

function jpKtReopen(token, reportId, reason) {
  var u = jpNeedKT_(jpAuth_(token));
  var r = jpStr_(reason);
  if (!r) throw new Error('Nhập lý do mở lại');

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');
    if (head.status !== JP_ST_DONE) throw new Error('Chỉ mở lại báo cáo đã hoàn tất');

    var later = jpRows_(JP_TABS.REPORTS).filter(function (x) {
      return String(x.locationId) === String(head.locationId)
          && jpDate_(x.fromDate) > jpDate_(head.toDate)
          && x.status === JP_ST_DONE;
    });

    jpFields_(JP_TABS.REPORTS, head._row, {
      status: JP_ST_FIXING,
      apprBy: '', apprAt: '',
      apprRevBy: '', apprRevAt: '', apprStockBy: '', apprStockAt: '',
      rejectBy: u.hoTen, rejectAt: new Date(),
      rejectPart: 'ALL', rejectReason: 'MỞ LẠI: ' + r
    });
    jpAudit_(u, 'REOPEN', reportId, head.locationName, r);

    /* Hoàn số về đúng lớp tồn cũ rồi xoá dòng xuất — mở lại mà không hoàn kho là
       giá vốn nằm lại trong sổ 632 của một báo cáo không còn hoàn tất. */
    var kho = jpHoanKhoBaoCao_(head, u);

    return {
      ok: true, status: JP_ST_FIXING,
      warnLater: later.length, kho: kho,
      msg: (later.length
        ? 'Đã mở lại. LƯU Ý: có ' + later.length + ' báo cáo kỳ sau đã chốt dựa trên số này'
        : 'Đã mở lại báo cáo') +
        (kho ? ' · đã hoàn kho ' + kho.soDong + ' dòng' +
               (kho.khongHoanDuoc ? ' (' + kho.khongHoanDuoc + ' dòng không có lớp để hoàn)' : '')
             : '')
    };
  });
}


/*──────────────────── TÍNH LẠI CẢNH BÁO ────────────────────*/

/**
 * TÍNH LẠI CẢNH BÁO của một báo cáo — **chỉ ghi cột `warnJson`**, không đụng gì khác.
 *
 * ⚠️ Vì sao cần: cảnh báo được **LƯU** vào `JP_Rows.warnJson` lúc `jpSaveReport`, và cả
 * hai đường đọc (`jpGetReport` · màn duyệt) đều lấy bản đã lưu chứ **không tính lại**.
 * Nên mỗi lần sửa luật cảnh báo ở máy chủ, báo cáo **đã nộp** vẫn hiện câu cũ mãi mãi —
 * mà báo cáo `CHO_DUYET` thì nhân viên không sửa/lưu lại được nữa, tức **không còn đường
 * nào** để bản vá tới được nó.
 *
 * Đo thật 15/08/2026 sau khi bỏ hai cảnh báo giả: 22 dòng ở 2 báo cáo vẫn mang câu cũ
 * `+ tồn ngoài`, và 38 cảnh báo `W8` về một ô không tồn tại vẫn nằm nguyên trong sheet.
 *
 * ⚠️⚠️ **CHỈ GHI `warnJson`.** `jpCalcRow_` tính lại TOÀN BỘ dòng — `amount`, `soldQty`,
 * `hLeft`, `cash`… — và mấy số đó là **tiền đã chốt** của một báo cáo có thể đã duyệt,
 * đã vào sổ công nợ, đã xuất kho 632. Ghi chúng trở lại là **tính lại tiền của quá khứ**
 * theo luật hôm nay, mà sổ vẫn CÂN nên không phép kiểm nào bắt được. Có phép riêng khoá
 * đúng chuyện này — nó so từng ô tiền trước/sau và bắt buộc giống hệt.
 *
 * ⚠️ KHÔNG đụng chữ ký, KHÔNG đổi trạng thái, KHÔNG hoàn kho. Cảnh báo là thứ để NHÌN,
 * không phải căn cứ hạch toán — nên tính lại nó là việc an toàn, và phải giữ đúng như vậy.
 */
function jpTinhLaiCanhBao(token, reportId) {
  var u = jpNeedKT_(jpAuth_(token));
  var id = jpStr_(reportId);
  if (!id) throw new Error('Thiếu mã báo cáo');

  var head = jpFindOne_(JP_TABS.REPORTS, 'id', id);
  if (!head) throw new Error('Không thấy báo cáo ' + id);

  var mType = jpStr_(head.machineType) || JP_TYPE_MONEY;
  var rows = jpFind_(JP_TABS.ROWS, 'reportId', id);

  var doi = 0, truoc = 0, sau = 0;
  rows.forEach(function (r) {
    var cu = jpStr_(r.warnJson);
    try { truoc += cu ? JSON.parse(cu).length : 0; } catch (e) {}
    /* `jpCalcRow_` sửa thẳng vào object — không sao, vì mình chỉ lấy ra `warnJson`
       rồi vứt object đi, tuyệt đối không ghi trường nào khác xuống sheet. */
    jpCalcRow_(r, mType);
    var moi = r.warns && r.warns.length ? JSON.stringify(r.warns) : '';
    sau += (r.warns || []).length;
    if (moi !== cu) { jpFields_(JP_TABS.ROWS, r._row, { warnJson: moi }); doi++; }
  });

  jpAudit_(u, 'WARN_RECALC', id, '', { dong: doi, truoc: truoc, sau: sau });
  return {
    ok: true, doi: doi, truoc: truoc, sau: sau,
    msg: 'Đã tính lại ' + rows.length + ' dòng · cảnh báo ' + truoc + ' → ' + sau +
         (doi ? ' (' + doi + ' dòng đổi)' : ' (không dòng nào đổi)') +
         '\nChỉ cập nhật cột cảnh báo — mọi số tiền và số hàng giữ nguyên.'
  };
}

/**
 * QUÉT TÍNH LẠI CẢNH BÁO CHO CẢ SHEET — chạy trong lượt chạy nền.
 *
 * Andy chốt 16/08/2026: *"cho lịch tự tính lại đi em"*. Lý do: cảnh báo được LƯU, nên mỗi
 * lần sửa luật cảnh báo ở máy chủ là phải đi bấm tay từng báo cáo — mà báo cáo cũ thì nằm
 * rải rác và dễ bỏ sót. Để lịch làm thì sau tối đa một nhịp là mọi báo cáo đều đúng.
 *
 * ⚠️⚠️ **CHỈ ghi `warnJson`**, y hệt `jpTinhLaiCanhBao`. `jpCalcRow_` tính lại toàn bộ dòng
 * — `amount`, `soldQty`, `hLeft`, `cash` — và mấy số đó là tiền đã chốt, có thể đã vào sổ
 * công nợ và đã xuất kho 632. Ghi trở lại là tính lại tiền của quá khứ theo luật hôm nay,
 * mà sổ vẫn CÂN. Chạy nền 288 lượt/ngày thì sai kiểu này lan rất nhanh và không ai thấy.
 *
 * ⚠️ **Chỉ ghi khi KHÁC** bản đang lưu. Ở trạng thái ổn định hàm này không ghi gì cả —
 * tốn đúng một lượt đọc, không tốn lượt ghi nào. Ghi mù mỗi lượt là 230 lượt ghi × 288
 * lần/ngày, đủ chạm trần Sheets và làm chậm cả app.
 *
 * ⚠️ **Không đụng chữ ký, trạng thái, kho.** Cảnh báo là thứ để NHÌN.
 *
 * ⚠️⚠️ **Gom bằng `jpGhiCot_`, đừng gọi `jpFields_` từng dòng.** Trạng thái ổn định thì hai
 * cách giống hệt nhau (không dòng nào đổi ⇒ không lượt ghi nào), nhưng **lượt ĐẦU sau khi
 * sửa một luật cảnh báo** thì cả nghìn dòng cùng đổi — ghi lẻ là cả nghìn lượt ghi Sheets
 * trong một lượt chạy, mà Apps Script cắt ở **6 phút**. Dòng của cùng một báo cáo nằm liền
 * nhau trong `JP_Rows` nên gom lại thường còn **một lượt mỗi báo cáo**. Đây đúng là chỗ
 * `CLAUDE.md` đã cảnh báo ở `jpGhiLopDaTru_` / kiểm kê — cùng một cái bẫy.
 */
function jpTinhLaiCanhBaoQuet_(u) {
  var heads = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (h) { heads[String(h.id)] = h; });

  var dong = 0, doi = 0, capNhat = {};
  jpRows_(JP_TABS.ROWS).forEach(function (r) {
    var h = heads[String(r.reportId)];
    if (!h) return;                       // dòng mồ côi — không có đầu báo cáo thì bỏ
    dong++;
    var cu = jpStr_(r.warnJson);
    jpCalcRow_(r, jpStr_(h.machineType) || JP_TYPE_MONEY);
    var moi = (r.warns && r.warns.length) ? JSON.stringify(r.warns) : '';
    if (moi !== cu) { capNhat[r._row] = moi; doi++; }
  });

  /* Không có gì đổi thì `jpGhiCot_` trả về ngay, KHÔNG chạm tới Sheets một lượt nào. */
  var luot = jpGhiCot_(JP_TABS.ROWS, 'warnJson', capNhat);

  /* ⚠️ CHỈ ghi audit khi CÓ đổi. Ghi mỗi lượt là 288 dòng rác mỗi ngày, và đúng cái
     nhật ký người ta mở ra để tìm chuyện bất thường thì ngập toàn dòng "không có gì". */
  if (doi) jpAudit_(u, 'WARN_RECALC_AUTO', '', '', { dong: dong, doi: doi, luot: luot });
  return { dong: dong, doi: doi, luot: luot };
}

