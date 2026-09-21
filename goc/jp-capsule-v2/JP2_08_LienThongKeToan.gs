/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2  ·  08_LienThongKeToan
 * ---------------------------------------------------------------------------
 * CẦU NỐI sang web kế toán. Ba luồng tiền QR · TM · CK giữ nguyên cách làm
 * của POSH v3, chỉ khác: JP khớp theo MÃ KH CẤP CƠ SỞ (POSH khớp theo mã ghế).
 *
 * SÁU NGUYÊN TẮC BẤT DI BẤT DỊCH (POSH v3 đã trả giá để có):
 *  1. Áp đối soát CHỈ ghi 3 ô: paid · paidDate · payStatus. Không đụng doanh thu.
 *  2. Mỗi lần áp có MÃ LÔ = vân tay dữ liệu. Áp lại đúng lô ⇒ CHẶN.
 *  3. Dòng khớp ≥2 cơ sở ⇒ KHÔNG tự chọn, đẩy vào danh sách "cần kế toán quyết".
 *  4. Ngày nộp lấy trên CHỨNG TỪ, không lấy ngày báo cáo.
 *  5. Không đọc sheet trong vòng lặp.
 *  6. Phân bổ theo phần CÒN THIẾU, thứ tự ổn định ⇒ chạy lại ra y nguyên.
 *═══════════════════════════════════════════════════════════════════════════*/

var JP_PAY_NONE    = 'CHUA_NOP';
var JP_PAY_PARTIAL = 'NOP_MOT_PHAN';
var JP_PAY_FULL    = 'DA_NOP';

/*──────────────────── ① BẢN ĐỒ NHẬN DẠNG CƠ SỞ ────────────────────*/

/**
 * BẢN ĐỒ DÒ CƠ SỞ, CHIA THEO MỨC CHẮC CHẮN (nâng 15/08/2026).
 *
 * Học từ web kế toán POSH (`matchLocSafe_` / `buildMatchIndex_`) — nơi luồng đối soát
 * chuyển khoản đã chạy thật với **cùng một nguồn giao dịch**. Nội dung CK ở đó có dạng
 * `KH00119 MTDMN 0001`, tức **mã đối tượng MISA nhúng thẳng vào nội dung**, và chữ
 * `MTDMN` chính là thứ câu QUERY của Andy lọc ở `Col8`.
 *
 * ⚠️⚠️ **Bản trước để BỐN loại khoá PHẲNG cùng một mức** (tên · mã cơ sở · mã KH), nên
 * một nội dung vừa chứa `KH00207` (chắc chắn) vừa lỡ chứa chuỗi trùng mã ngắn của cơ sở
 * khác thì bị chấm **mơ hồ** và rơi vào hàng chờ — trong khi thật ra đã đủ căn cứ. Với
 * nội dung CK thật (chỉ có mã KH, KHÔNG có tên cơ sở) thì bản phẳng còn tệ hơn: mức tên
 * không khớp gì cả.
 *
 * Nay xét **giảm dần theo độ chắc chắn**, hễ một mức ra ĐÚNG MỘT cơ sở là chốt, không
 * xét mức yếu hơn nữa:
 *   ① `maKH`     — mã đối tượng MISA, thứ nhân viên gõ vào nội dung CK
 *   ② `unitCode` — mã đơn vị
 *   ③ `code`     — mã cơ sở ngắn
 *   ④ `name`     — tên cơ sở, yếu nhất (đường của file sao kê cũ)
 *
 * ⚠️ Mơ hồ vẫn xét **TRONG MỘT MỨC**, không xét chéo mức: hai cơ sở cùng khớp ở mức ①
 * mới là mơ hồ thật. Một cái khớp ① và một cái khớp ④ thì cái ① thắng, không phải mơ hồ.
 */
function jpLocIndex_() {
  var muc = [
    { ten: 'mã KH',     map: {} },
    { ten: 'mã đơn vị', map: {} },
    { ten: 'mã cơ sở',  map: {} },
    { ten: 'tên cơ sở', map: {} }
  ];
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) {
    var id = String(l.id);
    [l.maKH, l.unitCode, l.code, l.name].forEach(function (k, i) {
      var n = jpNorm_(k);
      /* ≥3 ký tự: mã 1–2 ký tự khớp trúng bất cứ đâu trong nội dung CK. */
      if (n.length >= 3) muc[i].map[n] = id;
    });
  });
  return { muc: muc };
}

/**
 * Khớp ≥2 cơ sở TRONG CÙNG MỘT MỨC ⇒ trả ambiguous, KHÔNG tự chọn — nguyên tắc #3.
 *
 * ⚠️ Giữ nguyên hình dạng trả về (`id` · `ambiguous` · `candidates` · `khop`) vì ba chỗ
 * đang dùng: xem trước, áp, và đối soát ngân hàng tự động. Đổi hình dạng ở đây là sửa
 * ba chỗ, mà một chỗ quên là im lặng rơi vào nhánh "không nhận ra".
 */
function jpGuessLoc_(desc, idx) {
  var n = jpNorm_(desc);
  if (!n) return { id: '', ambiguous: false };

  /* Bản đồ đời cũ (map phẳng) vẫn chạy được — test cũ và dữ liệu cũ không phải sửa. */
  var cacMuc = (idx && idx.muc) ? idx.muc : [{ ten: '', map: idx || {} }];

  for (var i = 0; i < cacMuc.length; i++) {
    var map = cacMuc[i].map, hits = {}, khop = {};
    Object.keys(map).forEach(function (k) {
      /* Giữ luôn CHUỖI đã khớp để bảng "theo cơ sở" in được cột `Khớp bằng` (khuôn
         POSH). Không giữ thì kế toán không biết vì sao dòng tiền vào cơ sở đó, và khi
         nghi ngờ thì không có gì để soát lại. */
      if (n.indexOf(k) >= 0) { hits[map[k]] = 1; khop[map[k]] = k; }
    });
    var ids = Object.keys(hits);
    if (ids.length === 1) {
      return { id: ids[0], ambiguous: false, khop: khop[ids[0]], mucKhop: cacMuc[i].ten };
    }
    if (ids.length > 1) {
      return { id: '', ambiguous: true, candidates: ids, mucKhop: cacMuc[i].ten };
    }
  }
  return { id: '', ambiguous: false };
}

/*──────────────────── ② MÃ LÔ CHỐNG ÁP TRÙNG ────────────────────*/

function jpBatchId_(kind, rows) {
  var sig = (rows || []).map(function (r) {
    return jpDate_(r.date) + '|' + jpNum_(r.amount) + '|' + jpNorm_(r.desc);
  }).sort().join('#');
  var raw = Utilities.computeDigest(Utilities.DigestAlgorithm.MD5, kind + '::' + sig);
  return kind + '-' + raw.map(function (b) {
    return ('0' + (b & 0xFF).toString(16)).slice(-2);
  }).join('').slice(0, 16);
}

/** Lô đã áp và CHƯA bị huỷ. Huỷ rồi thì cho áp lại (xem jpReconUndo). */
function jpReconSeen_(batchId) {
  return jpRows_(JP_TABS.RECON).filter(function (r) {
    return jpStr_(r.batchId) === batchId
        && jpStr_(r.kind).indexOf('UNDO') < 0
        && !jpStr_(r.undoneAt);
  })[0] || null;
}

function jpReconLog_(user, batchId, kind, stat) {
  jpAppend_(JP_TABS.RECON, {
    at: new Date(), who: (user && user.hoTen) || '', batchId: batchId, kind: kind,
    rows: stat.rows || 0, matched: stat.matched || 0,
    ambiguous: stat.ambiguous || 0, amount: stat.amount || 0,
    note: stat.note || '',
    /* Phải lưu phân bổ từng báo cáo, không thì huỷ lô không trừ lại đúng số được */
    allocJson: stat.alloc ? JSON.stringify(stat.alloc) : ''
  });
}

/*──────────────────── ③ PHÂN BỔ TIỀN ĐÃ NHẬN ────────────────────*/

function jpAllocate_(reports, amount) {
  var left = jpNum_(amount), alloc = [];
  reports.sort(function (a, b) {
    var d = jpDate_(a.fromDate) < jpDate_(b.fromDate) ? -1
          : jpDate_(a.fromDate) > jpDate_(b.fromDate) ? 1 : 0;
    return d || (String(a.id) < String(b.id) ? -1 : 1);
  });
  reports.forEach(function (r) {
    if (left <= 0) return;
    var need = jpNum_(r.totalSubmit) - jpNum_(r.paid);
    if (need <= 0) return;
    var add = Math.min(need, left);
    alloc.push({ reportId: String(r.id), add: add, need: need, _row: r._row,
                 paid: jpNum_(r.paid), total: jpNum_(r.totalSubmit) });
    left -= add;
  });
  return { alloc: alloc, leftOver: left };
}

function jpPayStatus_(paid, total) {
  if (jpNum_(paid) <= 0) return JP_PAY_NONE;
  return jpNum_(paid) >= jpNum_(total) ? JP_PAY_FULL : JP_PAY_PARTIAL;
}

/*──────────────────── ④ ĐỐI SOÁT: XEM TRƯỚC (không ghi gì) ────────────────────*/

function jpReconPreview(token, kind, rows) {
  var u = jpNeedKT_(jpAuth_(token));
  kind = jpStr_(kind).toUpperCase() || 'TM';
  rows = rows || [];

  var batchId = jpBatchId_(kind, rows);
  var seen = jpReconSeen_(batchId);

  var idx = jpLocIndex_();
  var allReports = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    return r.status === JP_ST_DONE && jpNum_(r.paid) < jpNum_(r.totalSubmit);
  });

  var byLoc = {};
  allReports.forEach(function (r) {
    (byLoc[String(r.locationId)] = byLoc[String(r.locationId)] || []).push(r);
  });

  var matched = [], ambiguous = [], unmatched = [], sumLoc = {}, ttCS = {};
  /* id → tên cơ sở. `jpLocIndex_` là bản đồ NGƯỢC (tên đã chuẩn hoá → id) nên không
     dùng để in tên được — phải dựng riêng. */
  var tenCS = {};
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) {
    tenCS[String(l.id)] = jpStr_(l.name) || String(l.id);
  });

  rows.forEach(function (r, i) {
    var g = jpGuessLoc_(r.desc, idx);
    var item = {
      seq: i + 1, date: jpDate_(r.date), amount: jpNum_(r.amount),
      desc: jpStr_(r.desc), code: jpStr_(r.code)
    };
    if (g.ambiguous) { item.candidates = g.candidates; ambiguous.push(item); return; }
    if (!g.id)       { unmatched.push(item); return; }
    item.locationId = g.id;
    sumLoc[g.id] = (sumLoc[g.id] || 0) + item.amount;
    /* Gom thêm để dựng bảng THEO CƠ SỞ (khuôn POSH) — số dòng và nhận dạng nhờ đâu.
       Không gom ở đây thì sau vòng lặp không còn lấy lại được. */
    if (!ttCS[g.id]) ttCS[g.id] = { soDong: 0, khop: {} };
    ttCS[g.id].soDong++;
    if (g.khop) ttCS[g.id].khop[jpStr_(g.khop)] = true;
    matched.push(item);
  });

  var plan = [];
  var theoCoSo = [];
  Object.keys(sumLoc).forEach(function (locId) {
    var reps = (byLoc[locId] || []).slice();
    var a = jpAllocate_(reps, sumLoc[locId]);
    a.alloc.forEach(function (x) {
      var rep = reps.filter(function (r) { return String(r.id) === x.reportId; })[0];
      plan.push({
        reportId: x.reportId, locationId: locId,
        locationName: jpStr_(rep.locationName),
        period: jpDMY_(rep.fromDate) + ' – ' + jpDMY_(rep.toDate),
        total: x.total, paidBefore: x.paid, add: x.add,
        paidAfter: x.paid + x.add
      });
    });
    if (a.leftOver > 0) {
      plan.push({ reportId: '', locationId: locId, locationName: '(dư)',
                  period: '', total: 0, paidBefore: 0, add: a.leftOver, paidAfter: 0 });
    }

    /* Một DÒNG một CƠ SỞ — mức mà kế toán đối chiếu với sổ công nợ. Dựng ở ĐÂY vì
       chỉ chỗ này mới có `a` (kết quả phân bổ), tính lại ở ngoài là hai phép phân bổ
       rồi lệch nhau. */
    var phaiNop = 0, daGhi = 0;
    reps.forEach(function (r) {
      phaiNop += jpNum_(r.totalSubmit) - jpNum_(r.paid);
      daGhi   += jpNum_(r.paid);
    });
    var tienFile = jpNum_(sumLoc[locId]);
    var t = ttCS[locId] || { soDong: 0, khop: {} };
    theoCoSo.push({
      locationId: locId,
      locationName: tenCS[locId] || locId,
      /* KHỚP BẰNG — nói ra nhận dạng cơ sở nhờ đâu. Không in thì kế toán không biết
         vì sao dòng tiền này vào cơ sở đó, và không soát lại được khi nghi ngờ. */
      khopBang: Object.keys(t.khop).join(' · ') || 'nội dung chuyển khoản',
      soDong: t.soDong,
      phaiNop: phaiNop,
      tienFile: tienFile,
      daGhiNhan: daGhi,
      /* ⚠️ `lech` là SỐ, không phải chữ. JP trước đây chỉ in "đủ / còn thiếu / nhận
         vượt" — đọc thì hiểu nhưng KHÔNG CỘNG RA ĐƯỢC nên không đối chiếu được với sổ
         công nợ. Dương = nhận vượt, âm = còn thiếu; dấu chính là thông tin, đừng lấy
         trị tuyệt đối. */
      lech: tienFile - phaiNop,
      seGhi: tienFile - jpNum_(a.leftOver),
      con: jpNum_(a.leftOver)
    });
  });

  theoCoSo.sort(function (x, y) {
    return String(x.locationName).localeCompare(String(y.locationName));
  });
  var tongCoSo = { phaiNop: 0, tienFile: 0, daGhiNhan: 0, lech: 0, seGhi: 0, con: 0 };
  theoCoSo.forEach(function (r) {
    Object.keys(tongCoSo).forEach(function (c) { tongCoSo[c] += jpNum_(r[c]); });
  });

  return {
    ok: true, batchId: batchId, kind: kind,
    alreadyApplied: !!seen,
    appliedAt: seen ? seen.at : '',
    stat: { rows: rows.length, matched: matched.length,
            ambiguous: ambiguous.length, unmatched: unmatched.length },
    matched: matched, ambiguous: ambiguous, unmatched: unmatched,
    plan: plan,
    theoCoSo: theoCoSo, tongCoSo: tongCoSo
  };
}

/*──────────────────── ⑤ ĐỐI SOÁT: ÁP ────────────────────*/

/**
 * Cửa cho WEB. Thân hàm nằm ở `jpReconApply_` để **luồng tự động (trigger) dùng chung
 * ĐÚNG một đường ghi** — trigger không có token nên không đi qua đây được.
 *
 * ⚠️ Tách kiểu này chứ đừng viết đường ghi thứ hai cho tự động: hai đường ghi tiền vào
 * sổ công nợ là hai cách hiểu, và cái thứ hai sẽ thiếu `JP_Payments` hoặc thiếu nhật ký
 * lô ⇒ `jpReconUndo` không hoàn lại được, mà sổ vẫn cân.
 */
function jpReconApply(token, kind, rows, overrides) {
  return jpReconApply_(jpNeedKT_(jpAuth_(token)), kind, rows, overrides);
}

function jpReconApply_(u, kind, rows, overrides) {
  return jpLock_(function () { return jpReconApTrong_(u, kind, rows, overrides); });
}

/**
 * RUỘT của `jpReconApply_` — **KHÔNG tự lấy khoá**. Người gọi phải đang giữ khoá.
 *
 * ⚠️⚠️ Tách ra vì `jpDoiSoatNganHang_` chạy **bên trong** một `jpLock_` rồi mới gọi tới
 * đây. Lồng `jpLock_` vào `jpLock_` là **tự chờ chính mình**: lượt chạy đó ngồi hết
 * 30 giây rồi ném *"Thời gian chờ khóa"*, mà suốt 30 giây ấy nó **vẫn giữ khoá ngoài**
 * nên mọi nhân viên bấm Lưu / Nộp đều hỏng theo. Cùng cái bẫy đã ghi ở `12_KhoNangCao`.
 *
 * Chưa nổ ra thành sự cố chỉ vì cổng `JP_BANK_XAC_NHAN` đang TẮT nên `tuAp` luôn rỗng và
 * nhánh này không được chạm tới — tức nó là quả mìn hẹn giờ, **bấm xác nhận cột là nổ**.
 *
 * ⚠️ Đừng gọi thẳng hàm này từ web. Cửa cho web là `jpReconApply` / `jpReconApply_`.
 */
function jpReconApTrong_(u, kind, rows, overrides) {
  if (!jpIsKtRev_(u)) throw new Error('Chỉ kế toán doanh thu được áp đối soát');

  kind = jpStr_(kind).toUpperCase() || 'TM';
  rows = rows || [];
  overrides = overrides || {};

  return (function () {
    var batchId = jpBatchId_(kind, rows);
    if (jpReconSeen_(batchId)) {
      return { ok: false, blocked: true,
               msg: 'Lô dữ liệu này đã áp rồi (' + batchId + ') — không áp lại' };
    }

    var idx = jpLocIndex_();
    var reports = jpRows_(JP_TABS.REPORTS).filter(function (r) {
      return r.status === JP_ST_DONE && jpNum_(r.paid) < jpNum_(r.totalSubmit);
    });
    var byLoc = {};
    reports.forEach(function (r) {
      (byLoc[String(r.locationId)] = byLoc[String(r.locationId)] || []).push(r);
    });

    var sumLoc = {}, lastDate = {}, total = 0;
    var soMoHo = 0, soKhongNhanRa = 0;

    rows.forEach(function (r, i) {
      var locId = jpStr_(overrides[String(i + 1)]);
      if (!locId) {
        var g = jpGuessLoc_(r.desc, idx);
        /* Tách "mơ hồ" khỏi "không nhận ra" — hai loại cần xử lý khác nhau */
        if (g.ambiguous) { soMoHo++; return; }
        if (!g.id) { soKhongNhanRa++; return; }
        locId = g.id;
      }
      var amt = jpNum_(r.amount);
      sumLoc[locId] = (sumLoc[locId] || 0) + amt;
      total += amt;
      var d = jpDate_(r.date);                 // nguyên tắc #4: ngày trên chứng từ
      if (d && (!lastDate[locId] || d > lastDate[locId])) lastDate[locId] = d;
    });

    var applied = 0, leftAll = 0, ghiNhan = [], dongThu = [], truocDong = [];
    Object.keys(sumLoc).forEach(function (locId) {
      var a = jpAllocate_((byLoc[locId] || []).slice(), sumLoc[locId]);
      leftAll += a.leftOver;
      /*
       * ⚠️⚠️ TIỀN DƯ = **NHẬN TRƯỚC**, phải GHI LẠI — không được chỉ đếm rồi bỏ.
       *
       * Andy chốt 15/08/2026: *"cứ ghi vào đi anh sẽ cho nv bsung báo cáo"*. Tiền về
       * ngân hàng trước khi báo cáo được duyệt là chuyện bình thường; chặn lại là app
       * ngồi trên một khoản tiền thật mà không để dấu vết nào.
       *
       * ⚠️ `reportId` để **RỖNG** — và điều đó là CỐ Ý, không phải thiếu sót:
       * `jpSoNhatKyChung` bỏ qua dòng thu không gắn báo cáo `HOAN_TAT`, vì ghi vào là
       * có `Nợ 1121` mà **không có `Có 131` nào sinh ra nó** ⇒ sổ mất cân ngay. Nên
       * dòng này nằm chờ ngoài sổ nhật ký cho tới khi `jpPhanBoNhanTruoc_` gắn được nó
       * vào một báo cáo thật. Đừng "sửa" thành gắn đại một `reportId`.
       */
      if (a.leftOver > 0) {
        var tid = jpNextId_(JP_TABS.PAYMENTS, 'PM');
        jpAppend_(JP_TABS.PAYMENTS, {
          id: tid, reportId: '', locationId: locId,
          amount: a.leftOver, payDate: lastDate[locId] || jpToday_(), method: kind,
          note: 'Nhận trước (chưa có báo cáo) · lô ' + batchId,
          photoId: '', photoUrl: '', isSupplement: '',
          createdBy: u.hoTen, createdAt: new Date()
        });
        truocDong.push({ locationId: locId, amount: a.leftOver, payId: tid });
      }
      a.alloc.forEach(function (x) {
        var newPaid = x.paid + x.add;
        var ngayThu = lastDate[locId] || jpToday_();
        jpFields_(JP_TABS.REPORTS, x._row, {          // ← ĐÚNG 3 Ô
          paid: newPaid,
          paidDate: ngayThu,
          payStatus: jpPayStatus_(newPaid, x.total)
        });
        /*
         * VÀ một dòng `JP_Payments` với `method = kind` (thêm 04/08/2026).
         *
         * Trước bản này đối soát CHỈ ghi 3 ô trên, không ghi dòng nộp tiền nào —
         * nên phương thức thu (TM / CK / QR) chỉ nằm trong nhật ký lô, không tra
         * được theo báo cáo. Hai chỗ hỏng vì thiếu nó:
         *   · sổ công nợ không tách được cột TM / CK / QR
         *   · sổ nhật ký chung THIẾU bút toán 1111 / 1121 cho tiền vào qua đối
         *     soát ⇒ `jpKiemTraButToan` báo lệch trên MỌI báo cáo đã đối soát
         *
         * `note` mang `batchId` để `jpReconUndo` tìm lại mà xoá — đừng đổi khuôn
         * chuỗi đó mà không sửa `jpReconUndo`.
         */
        var pid = jpNextId_(JP_TABS.PAYMENTS, 'PM');
        jpAppend_(JP_TABS.PAYMENTS, {
          id: pid, reportId: x.reportId, locationId: locId,
          amount: x.add, payDate: ngayThu, method: kind,
          note: 'Đối soát lô ' + batchId,
          photoId: '', photoUrl: '', isSupplement: '',
          createdBy: u.hoTen, createdAt: new Date()
        });
        dongThu.push(pid);
        ghiNhan.push({ reportId: x.reportId, add: x.add, payId: pid });
        applied++;
      });
    });

    var skipped = soMoHo + soKhongNhanRa;
    jpReconLog_(u, batchId, kind, {
      rows: rows.length, matched: rows.length - skipped,
      ambiguous: soMoHo, amount: total, alloc: ghiNhan,
      note: 'Áp ' + applied + ' báo cáo · bỏ qua ' + soMoHo + ' mơ hồ + ' +
            soKhongNhanRa + ' không nhận ra · dư ' + jpMoney_(leftAll) + 'đ'
    });
    jpAudit_(u, 'RECON_APPLY', '', kind,
             { batchId: batchId, applied: applied, amount: total });

    return { ok: true, batchId: batchId, applied: applied,
             skipped: skipped, ambiguous: soMoHo, unmatched: soKhongNhanRa,
             leftOver: leftAll,
             msg: 'Đã áp ' + applied + ' báo cáo · bỏ qua ' + skipped + ' dòng' };
  })();
}

/*════════════ ⑤c ĐỐI SOÁT CHUYỂN KHOẢN TỰ ĐỘNG TỪ API NGÂN HÀNG ════════════
 * Andy chốt 15/08/2026: API đã đẩy giao dịch ngân hàng về một sheet realtime, và chọn
 * mức **(b) trigger tự chạy — tự áp dòng khớp CHẮC CHẮN, dòng mơ hồ để kế toán quyết**.
 *════════════════════════════════════════════════════════════════════════════*/

/** ID sheet nguồn — Script Property, KHÔNG nằm trong repo (cùng luật `JP_SHEET_ID`). */
function jpBankSheetId_() {
  return jpStr_(PropertiesService.getScriptProperties().getProperty('JP_BANK_SHEET_ID'));
}

/** Đặt ID sheet nguồn. Hàm ADMIN, chạy tay từ trình chỉnh sửa. */
function jpDatBankSheet_(sheetId) {
  var id = jpStr_(sheetId);
  if (!id) throw new Error('Truyền ID sheet giao dịch ngân hàng');
  PropertiesService.getScriptProperties().setProperty('JP_BANK_SHEET_ID', id);
  return 'Đã đặt JP_BANK_SHEET_ID = ' + id;
}

/**
 * ĐỌC giao dịch ngân hàng theo đúng phép lọc của Andy:
 *   `Col8` chứa `mtdmn` (chữ thường) · `Col5 > 0` · sắp theo `Col2` giảm dần.
 *
 * ⚠️ Trả về CẢ `mau` (vài dòng thô) và `tieuDe` để kế toán **nhìn tận mắt** cột nào là
 * cột nào. `JP_BANK_COT` mới chỉ suy từ câu QUERY, chưa đối chiếu hàng tiêu đề thật —
 * đọc nhầm cột tiền là ghi nhận nộp tiền sai số, mà sổ vẫn cân.
 */
function jpDocGiaoDichNganHang(token, gioiHan) {
  jpNeedKT_(jpAuth_(token));
  return jpDocGiaoDich_(gioiHan);
}

/**
 * Soi CHUỖI LỖI của IMPORTRANGE — chỗ hỏng nguy hiểm nhất của đường tab công thức.
 *
 * ⚠️⚠️ IMPORTRANGE hỏng thì ô chứa **chuỗi** `#REF!` / `#N/A` / `Loading...`, Apps Script
 * đọc ra chuỗi đó chứ **KHÔNG ném lỗi**. Không chặn thì bộ lọc gạt sạch và app báo
 * *"không có giao dịch mới"* — **trông y hệt một ngày vắng khách**, trong khi thật ra
 * tiền về mà không ai ghi nhận. Đây là loại im lặng tệ nhất.
 */
function jpLoiCongThuc_(vals) {
  var xau = ['#REF!', '#N/A', '#ERROR!', '#VALUE!', '#NAME?', 'Loading'];
  for (var i = 0; i < Math.min(vals.length, 5); i++) {
    for (var j = 0; j < vals[i].length; j++) {
      var t = jpStr_(vals[i][j]);
      for (var k = 0; k < xau.length; k++) {
        if (t.indexOf(xau[k]) === 0) return t;
      }
    }
  }
  return '';
}

function jpDocGiaoDich_(gioiHan) {
  /* ① Tab công thức trong CHÍNH sheet JP — ưu tiên, xem chú thích ở `JP_BANK_FEED_TAB` */
  var nguon = 'tab công thức ' + JP_BANK_FEED_TAB;
  var vals = jpDocTabTho_(JP_BANK_FEED_TAB, 14);

  if (vals === null) {
    /* ② Đường lùi: đọc thẳng sheet ngoài */
    nguon = 'sheet ngoài';
    vals = jpDocSheetNgoai_(jpBankSheetId_(), JP_BANK_TAB, 14);
  } else {
    var loi = jpLoiCongThuc_(vals);
    if (loi) {
      throw new Error('Tab "' + JP_BANK_FEED_TAB + '" đang báo lỗi công thức: ' + loi +
        '. Mở sheet dữ liệu JP, vào tab đó và bấm "Cho phép truy cập" (IMPORTRANGE cần ' +
        'cấp quyền MỘT LẦN). Chưa xong thì app KHÔNG đọc được giao dịch nào — và nó ' +
        'trông y hệt một ngày không có tiền về.');
    }
    /* Tab có nhưng rỗng ⇒ nói RA, đừng để lẫn với "hôm nay không có giao dịch" */
    if (vals.length <= 1) {
      return { ok: true, rows: [], tieuDe: jpThoChuoi_([vals[0] || []])[0], mau: [], soDong: 0,
               nguon: nguon, canhBao: 'Tab "' + JP_BANK_FEED_TAB + '" không có dòng dữ ' +
               'liệu nào. Kiểm công thức QUERY/IMPORTRANGE ở ô A1 — nếu vừa dán thì đợi ' +
               'nó nạp xong rồi bấm lại.' };
    }
  }
  if (!vals.length) return { ok: true, rows: [], tieuDe: [], mau: [], soDong: 0,
                             nguon: nguon };

  var tieuDe = vals[0].map(function (x) { return jpStr_(x); });
  var C = JP_BANK_COT;
  var lay = function (r, i) { return (i >= 1 && i <= r.length) ? r[i - 1] : ''; };

  var rows = [];
  for (var i = 1; i < vals.length; i++) {
    var r = vals[i];
    if (jpStr_(lay(r, C.noiDung)).toLowerCase().indexOf(JP_BANK_LOC) < 0) continue;
    var tien = jpNum_(lay(r, C.tienVao));
    if (tien <= 0) continue;

    /* Nội dung để dò cơ sở: GHÉP MỌI Ô CHỮ. Không biết chắc cột nào chứa nội dung
       chuyển khoản, ghép hết thì `jpGuessLoc_` vẫn tìm ra tên cơ sở dù nó nằm ở đâu. */
    var chu = [];
    if (JP_BANK_GHEP_CHU) {
      r.forEach(function (o) {
        var t = jpStr_(o);
        if (t && !/^[\d\s.,\-]+$/.test(t)) chu.push(t);
      });
    } else {
      chu.push(jpStr_(lay(r, C.noiDung)));
    }

    rows.push({
      refId: jpStr_(lay(r, C.ma)),
      date: jpDate_(lay(r, C.ngay)),
      amount: tien,
      desc: chu.join(' | '),
      code: jpStr_(lay(r, C.ma))
    });
  }
  rows.sort(function (a, b) { return a.date < b.date ? 1 : -1; });   // Col2 desc
  var n = jpNum_(gioiHan);
  return {
    ok: true, tieuDe: tieuDe, nguon: nguon,
    soDong: rows.length,
    rows: n > 0 ? rows.slice(0, n) : rows,
    /* ⚠️ PHẢI qua `jpThoChuoi_`: cột ngày của nguồn ngân hàng là `Date` thật, mà
       `google.script.run` CẤM `Date` — client nhận `null` và không có lỗi nào ở log. */
    mau: jpThoChuoi_(vals.slice(1, 4))   // ba dòng THÔ để nhìn bằng mắt, không qua map
  };
}

/**
 * XEM MẪU nguồn giao dịch — chạy TAY từ trình chỉnh sửa, bấm Run là xong.
 *
 * ⚠️ Hàm không tham số **là có lý do**: nút Run của trình chỉnh sửa không truyền được
 * tham số, y như bốn hàm `jpChiaSeAnh*_`. ID lấy từ Script Property nên không lọt vào
 * repo.
 *
 * In ra ba thứ để đối chiếu `JP_BANK_COT` bằng MẮT — bản đồ cột hiện mới suy từ câu
 * QUERY, chưa ai nhìn hàng tiêu đề thật:
 *   ① 14 tiêu đề kèm số cột (`Col1..Col14`)
 *   ② ba dòng thô
 *   ③ app đang HIỂU dòng đầu thành gì (ngày / tiền / mã tham chiếu)
 */
function jpXemGiaoDichMau_() {
  var vals = jpDocSheetNgoai_(jpBankSheetId_(), JP_BANK_TAB, 14);
  if (!vals.length) return 'Tab "' + JP_BANK_TAB + '" rỗng';

  var out = ['=== 14 CỘT CỦA ' + JP_BANK_TAB + ' ==='];
  vals[0].forEach(function (h, i) {
    var vai = [];
    if (i + 1 === JP_BANK_COT.ma)      vai.push('← app coi là MÃ THAM CHIẾU');
    if (i + 1 === JP_BANK_COT.ngay)    vai.push('← app coi là NGÀY');
    if (i + 1 === JP_BANK_COT.tienVao) vai.push('← app coi là TIỀN VÀO');
    if (i + 1 === JP_BANK_COT.noiDung) vai.push('← app coi là NỘI DUNG, và LỌC "' +
                                                JP_BANK_LOC + '" ở đây');
    out.push('  Col' + (i + 1) + '  ' + jpStr_(h) + '  ' + vai.join(' '));
  });

  out.push('', '=== BA DÒNG THÔ ===');
  vals.slice(1, 4).forEach(function (r, i) {
    out.push('  [' + (i + 1) + '] ' + r.map(function (x) {
      return jpStr_(x).slice(0, 24);
    }).join(' | '));
  });

  var d = jpDocGiaoDich_(3);
  out.push('', '=== APP ĐỌC RA (sau khi lọc "' + JP_BANK_LOC + '" và tiền > 0) ===');
  out.push('  lọc còn ' + d.soDong + ' dòng');
  d.rows.forEach(function (r) {
    out.push('  refId=' + r.refId + ' · ngày=' + r.date + ' · tiền=' +
             jpMoney_(r.amount) + 'đ · nội dung=' + r.desc.slice(0, 90));
  });
  out.push('', '⚠️ Soi kỹ ba dòng cuối: SAI CỘT TIỀN là ghi nhận nộp tiền sai số, mà sổ vẫn cân.');

  var msg = out.join('\n');
  Logger.log(msg);
  return msg;
}

/**
 * BẢN ĐỒ MÃ ĐỊNH DANH → CƠ SỞ, tách rõ **của JP** và **của thương hiệu khác**.
 *
 * ⚠️ Phải giữ CẢ danh sách của thương hiệu khác, không chỉ lọc lấy JP. Biết một mã là
 * **của POSH** thì chặn được thẳng và nói đúng lý do; chỉ biết "không phải của JP" thì
 * dòng đó rơi xuống mức dò mờ (tên / mã ngắn) và có thể khớp bừa vào một cơ sở JP.
 *
 * Nối với `JP_Locations` bằng **mã đơn vị**: bảng định danh ghi `JPAMTP`, còn
 * `JP_Locations.unitCode` ghi `50JPAMTP` — so bằng "chứa nhau", không so bằng `===`.
 */
function jpBanDoDinhDanh_() {
  var vals = jpDocTabTho_(JP_DINH_DANH_TAB, 10);
  if (!vals || vals.length < 2) return { jp: {}, khac: {}, co: false };

  var C = JP_DINH_DANH_COT;
  var lay = function (r, i) { return (i >= 1 && i <= r.length) ? jpStr_(r[i - 1]) : ''; };

  var locs = jpRows_(JP_TABS.LOCATIONS).map(function (l) {
    return { id: String(l.id), unit: jpNorm_(l.unitCode), ten: jpNorm_(l.name) };
  });

  var jp = {}, khac = {};
  /*
   * ⚠️⚠️ **KHÔNG bỏ qua dòng đầu.** Bản trước bắt đầu từ `i = 1`, coi dòng 1 là hàng tiêu
   * đề — nhưng tab thật **KHÔNG có hàng tiêu đề**: dòng 1 là dữ liệu
   * (`119 | 50AMBT | POSH MN AEON MALL BÌNH TÂN | POSH | … | KH705MTDMN0001`, đọc thẳng
   * file sheet 15/08/2026). Nên mã của **cơ sở đầu bảng bị nuốt im lặng**.
   *
   * Hôm nay nó là mã của POSH nên vô hại — nhưng bảng này do người khác quản, thứ tự đổi
   * lúc nào không biết. Ngày nào một cơ sở JP đứng đầu bảng thì tiền của họ rơi vào
   * *"chưa khai"* vĩnh viễn, mà không có gì chỉ ra vì sao.
   *
   * ⚠️ Nhận hàng tiêu đề bằng **"ô định danh không có chữ số nào"** — mã thật luôn có số,
   * còn tiêu đề (`Mã định danh`) thì không. Đừng nhận bằng `JP_DINH_DANH_RE`: hình dạng
   * đoán trước đã sai một lần rồi (xem `KH989KVCMN0010`), và ở đây đoán sai là **mất dòng
   * dữ liệu thật**, chứ không phải chỉ chấm nhầm câu chữ.
   */
  for (var i = 0; i < vals.length; i++) {
    var dd = jpNorm_(lay(vals[i], C.dinhDanh));
    if (!dd || !/\d/.test(dd)) continue;
    var th = jpStr_(lay(vals[i], C.thuongHieu)).toUpperCase();
    if (th !== JP_THUONG_HIEU) { khac[dd] = th || '(không rõ)'; continue; }

    var maDv = jpNorm_(lay(vals[i], C.maDv)), ten = jpNorm_(lay(vals[i], C.ten));
    var hit = null;
    locs.forEach(function (l) {
      if (hit) return;
      if (maDv && l.unit && (l.unit.indexOf(maDv) >= 0 || maDv.indexOf(l.unit) >= 0)) hit = l;
      else if (ten && l.ten && l.ten === ten) hit = l;
    });
    /* Có định danh JP mà KHÔNG nối được vào cơ sở nào ⇒ vẫn ghi vào `jp` với id rỗng.
       Nhờ vậy nó bị chặn ở mức định danh kèm lý do đúng, thay vì rơi xuống mức dò mờ. */
    jp[dd] = hit ? hit.id : '';
  }
  /* Giữ bản CHƯA chồng để màn Cấu hình nói được mã này khai ở ĐÂU. Cùng một mã mà đến từ
     bảng chung của công ty hay do kế toán JP tự khai là hai việc phải làm khác nhau. */
  var chung = {};
  Object.keys(jp).forEach(function (k) { chung[k] = jp[k]; });
  jpDinhDanhTheoCoSo_(jp);
  return { jp: jp, khac: khac, chung: chung, co: true };
}

/**
 * Mã định danh **lấy từ bảng chung** của từng cơ sở — `{ locationId: [mã, …] }`.
 *
 * ⚠️ Màn Cấu hình trước đây in đỏ *"chưa khai"* chỉ vì cột `JP_Locations.maDinhDanh` trống,
 * trong khi `JP_TenDinhDanh` đã có đủ mã và **đối soát vẫn chạy đúng**. Báo thiếu một thứ
 * không thiếu thì kế toán đi khai lại bằng tay — thành hai nguồn cho cùng một mã, và bản
 * khai tay THẮNG bảng chung nên gõ nhầm một ký tự là tiền về cơ sở khác mà không ai biết.
 */
function jpDinhDanhChung_() {
  var m = {};
  try {
    var bd = jpBanDoDinhDanh_();
    Object.keys(bd.chung || {}).forEach(function (dd) {
      var id = bd.chung[dd];
      if (!id) return;
      if (!m[id]) m[id] = [];
      m[id].push(dd.toUpperCase());
    });
  } catch (e) { return {}; }   // thiếu bảng chung KHÔNG được làm chết màn Cấu hình
  return m;
}

/**
 * Chồng mã định danh khai TRÊN TỪNG CƠ SỞ lên bản đồ.
 *
 * ⚠️ Cột `JP_Locations.maDinhDanh` **THẮNG** bảng dùng chung: `JP_TenDinhDanh` là bảng của
 * cả công ty, JP có thể không sở hữu và không sửa kịp. Kế toán JP khai ngay trong app là
 * chạy được, khỏi chờ ai.
 *
 * ⚠️ Chỉ **thêm hoặc ghi đè trong nhánh JP**, tuyệt đối không đụng `khac`: khai nhầm một
 * mã của POSH vào cơ sở JP mà lại xoá nó khỏi danh sách thương hiệu khác thì mất luôn
 * cái chốt chặn tiền của POSH.
 */
function jpDinhDanhTheoCoSo_(jp) {
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) {
    var dd = jpNorm_(l.maDinhDanh);
    if (dd) jp[dd] = String(l.id);
  });
}

/**
 * Dò cơ sở theo MÃ ĐỊNH DANH trong nội dung CK — mức mạnh nhất, và là mức duy nhất
 * được phép tự áp trên nguồn dùng chung nhiều thương hiệu.
 *
 * ⚠️ **Dò bằng DANH SÁCH MÃ ĐÃ KHAI, không dò bằng hình dạng.** Bản đầu bắt bằng regex
 * `/kh\d+mtdmn\d+/` rồi mới tra bảng, tức là mã nào **không đúng khuôn đoán trước** thì
 * không bao giờ được tra. `JP MN SC VIVO` mang `KH989KVCMN0010` (vùng của chi nhánh giữ
 * tài khoản là KVC, không phải MTD) nên **rơi khỏi lưới hoàn toàn**: khai đúng trong
 * `JP_TenDinhDanh` mà app vẫn báo *"nội dung không có mã định danh"*.
 *
 * Đảo lại thứ tự thì bảng khai là nguồn duy nhất, và thêm một họ mã mới **không phải sửa
 * code**. Regex chỉ còn một việc: phân biệt *"có mã nhưng chưa khai"* với *"không có mã"*.
 *
 * ⚠️ Khớp cả mã JP **và** mã thương hiệu khác thì **CHẶN**, không ưu tiên JP. Một nội dung
 * mang hai định danh là chứng cứ nó không thuần của ai; tự áp về JP lúc đó là đoán, mà
 * đoán sai thì sổ JP vẫn CÂN và chỉ kế toán bên kia mới thấy mất tiền.
 */
function jpDoDinhDanh_(desc, bd) {
  var n = jpNorm_(desc);
  if (!n) return { co: false };

  var cuaJP = [], cuaKhac = [];
  Object.keys(bd.jp).forEach(function (m) { if (m && n.indexOf(m) >= 0) cuaJP.push(m); });
  Object.keys(bd.khac).forEach(function (m) { if (m && n.indexOf(m) >= 0) cuaKhac.push(m); });

  if (!cuaJP.length && !cuaKhac.length) {
    var la = n.match(JP_DINH_DANH_RE);
    JP_DINH_DANH_RE.lastIndex = 0;             // regex có cờ /g giữ trạng thái giữa các lần gọi
    if (!la || !la.length) return { co: false };
    return { co: true, id: '', chuaKhai: la[0] };
  }
  if (cuaKhac.length && cuaJP.length) {
    return { co: true, id: '', nhieu: cuaJP.concat(cuaKhac) };
  }
  if (cuaKhac.length) {
    return { co: true, id: '', thuongHieuKhac: bd.khac[cuaKhac[0]], dinhDanh: cuaKhac[0] };
  }
  if (cuaJP.length > 1) {
    return { co: true, id: '', nhieu: cuaJP };
  }
  return { co: true, id: bd.jp[cuaJP[0]], dinhDanh: cuaJP[0], chuaNoi: !bd.jp[cuaJP[0]] };
}

/** Giao dịch đã đọc rồi — khoá chống áp trùng của luồng realtime. */
function jpBankDaDoc_() {
  var m = {};
  jpRows_(JP_TABS.BANK_GD).forEach(function (r) { m[jpStr_(r.refId)] = r; });
  return m;
}

/**
 * GHI MỘT DÒNG `JP_BankGD` — **sửa dòng cũ nếu refId đã có**, chỉ thêm mới khi chưa có.
 *
 * ⚠️ Giao dịch bị chặn ở lượt trước sẽ được xét LẠI (xem `jpChuaAp_`), nên nếu cứ
 * `jpAppend_` thì mỗi lượt chạy nền lại đẻ thêm một dòng cho cùng một giao dịch — 5 phút
 * một lần, mỗi ngày 288 dòng rác cho mỗi giao dịch chưa xử được.
 */
function jpGhiBankGD_(daDoc, key, obj, dem) {
  var cu = daDoc[key];
  if (!cu || !cu._row) {
    jpAppend_(JP_TABS.BANK_GD, obj);
    if (dem) dem['them'] = (dem['them'] || 0) + 1;
    return;
  }
  /* ⚠️⚠️ KHÔNG GHI LẠI DÒNG KHÔNG ĐỔI GÌ — xem `jpBankGDDoi_` ngay dưới. */
  var vi = jpBankGDDoi_(cu, obj);
  if (!vi) { if (dem) dem['boQua'] = (dem['boQua'] || 0) + 1; return; }
  jpFields_(JP_TABS.BANK_GD, cu._row, obj);
  if (dem) dem[vi] = (dem[vi] || 0) + 1;
}

/**
 * Dòng `JP_BankGD` cũ có KHÁC dòng sắp ghi không — **chốt chặn ghi lại vô ích**.
 *
 * ⚠️⚠️ Vì sao phải có: lịch chạy nền 5 phút/lượt, và mỗi lượt nó ghi lại **TOÀN BỘ** hàng
 * chờ — đo trên sheet thật 29/08/2026: **214 dòng ghi lại y nguyên**, `docLuc` nhảy đúng
 * theo nhịp trigger. 214 lượt `jpFields_` ≈ 214 lượt gọi Sheets, và **cả khối nằm trong
 * `jpLock_`** ⇒ nhân viên bấm Lưu / Nộp trúng cửa sổ đó là nhận *"Thời gian chờ khóa"*.
 *
 * ⚠️ Bản vá 28/08 tưởng đã đóng chỗ này bằng "cổng xác nhận cột tắt thì bỏ lượt" — SAI,
 * vì đo ra cổng đó **đang BẬT**. Cổng chỉ đóng được trường hợp nó tắt; còn khi bật thì
 * lượt chạy vẫn quét và vẫn ghi đủ 214 dòng. Đây mới là chỗ thật.
 *
 * ⚠️ **`docLuc` / `apLuc` KHÔNG được đem so** — chúng là dấu thời gian, lượt nào cũng khác
 * nên đem so là mọi dòng luôn "có đổi" và chốt này thành vô dụng. Bỏ chúng ra là an toàn:
 * cả repo **không có chỗ nào ĐỌC `docLuc`** để tính toán (chỉ khai cột ở `00_Config` và
 * ghi ở đây), nên nó vốn chỉ là ghi chú. Sau bản này nghĩa của nó chặt hơn: *lần cuối dòng
 * này THAY ĐỔI*, chứ không phải *lần cuối chạy ngang qua*.
 *
 * ⚠️ `ngay` so qua `jpDate_`: ô sheet trả về **Date** còn nguồn ngân hàng trả **chuỗi**,
 * so thẳng là hai thứ không bao giờ bằng nhau ⇒ lại luôn "có đổi".
 */
/*
 * ⚠️ Trả về **TÊN Ô đầu tiên khác nhau** (chuỗi, truthy) chứ không phải `true`, và chuỗi
 * rỗng khi giống hết. Lý do: đo trên sheet thật 29/08/2026, bản vá cắt được 214 → **79**
 * lượt ghi mỗi lượt chạy — tốt, nhưng **79 kia là ô nào thì không biết**, và từ bản xuất
 * sheet KHÔNG dựng lại được (tab nguồn là tab công thức, `refId` trong bảng `738…` không
 * giao một dòng nào với id trong tab `772…`). Đoán mò là cách tốn thời gian nhất.
 * ⇒ Đếm ngay tại chỗ rồi in ra dòng tình trạng: lượt chạy sau là biết đích danh.
 */
function jpBankGDDoi_(cu, moi) {
  var o = ['trangThai', 'locationId', 'lyDo', 'reportId', 'paymentId', 'apBoi', 'noiDung'];
  for (var i = 0; i < o.length; i++) {
    if (jpStr_(cu[o[i]]) !== jpStr_(moi[o[i]])) return o[i];
  }
  if (jpNum_(cu.soTien) !== jpNum_(moi.soTien)) return 'soTien';
  /*
   * ⚠️⚠️ **KHÔNG SO `ngay`** — đo thật 29/08/2026, chính nó là thủ phạm: dòng tình trạng
   * in ra `ghi 79 (ngay:79) · bỏ qua 137`, tức **cả 79 lượt ghi thừa đều do ô này**.
   *
   * Lý do là so HAI THỨ KHÁC LOẠI, không phải dữ liệu sai:
   *   `moi.ngay` — `jpDocGiaoDich_` đã chạy `date: jpDate_(...)` ⇒ **CHUỖI** `yyyy-MM-dd`
   *   `cu.ngay`  — ghi chuỗi đó xuống ô thì Sheets **tự hiểu thành DATE** (đo được: cả
   *                216 dòng lưu ở `00:00:00`), đọc lên là một `Date`
   * `jpDate_(Date)` chạy `Utilities.formatDate(v, 'Asia/Ho_Chi_Minh', …)`, mà mốc thời
   * gian của ô lại tính theo **múi giờ của SPREADSHEET**. Hai múi lệch nhau là ngày nhảy
   * đúng một hôm. ⇒ **Vòng chuỗi → ô → Date → chuỗi KHÔNG phải phép đồng nhất**, nên so
   * kiểu gì cũng có lúc sai, và sai theo môi trường nên **không dựng lại được từ bản xuất
   * sheet** (đã thử ba lượt, mất cả buổi — đó là lý do phải gắn đồng hồ đo).
   *
   * Và bỏ nó đi **không mất gì**: `refId` là danh tính của giao dịch, mà **ngân hàng không
   * lùi ngày một giao dịch đã phát sinh**. Nếu ngày có sửa thật thì `soTien` / `noiDung`
   * gần như chắc chắn đổi theo, và hai ô đó vẫn được so.
   *
   * ⚠️ Đừng "sửa cho đúng" bằng cách nới ±1 ngày. Nới là biến phép so thành thứ gần như
   * luôn bằng nhau — đúng bệnh `canBang` cũ, và lần này còn giấu mất một sai lệch thật.
   */
  return '';
}

/**
 * Giao dịch còn phải xét — tức **CHƯA áp thành công**.
 *
 * ⚠️⚠️ Bản đầu lọc `!daDoc[r.refId]`, tức **mọi** dòng đã có trong `JP_BankGD` đều bị loại,
 * **không phân biệt `AP` với `CHO`**. Hậu quả: giao dịch bị chặn ở lượt đầu — vì kế toán
 * chưa xác nhận cột, vì cơ sở chưa khai định danh, vì chưa có báo cáo — **không bao giờ
 * được xét lại**, kể cả sau khi lý do chặn đã được sửa xong. Hàng chờ thành **ngõ cụt**:
 * app im lặng bỏ tiền lại đó mãi mãi.
 *
 * Đo thật 15/08/2026: 34 dòng nằm `CHO` với lý do *"kế toán chưa xác nhận bản đồ cột"*,
 * trong đó có **127.530.000đ** của SÂN BAY PQ. Bấm xác nhận cột xong thì theo luật cũ
 * chúng vẫn nằm nguyên đó.
 *
 * ⇒ **Chỉ `AP` mới chặn.** Chống áp trùng vẫn nguyên vẹn: đã áp là không xét lại.
 */
function jpChuaAp_(daDoc, refId) {
  var cu = daDoc[jpStr_(refId)];
  return !cu || jpStr_(cu.trangThai) !== 'AP';
}

/**
 * ĐỐI SOÁT TỰ ĐỘNG — đọc, lọc cái mới, tự áp cái CHẮC CHẮN, còn lại xếp hàng chờ.
 *
 * ⚠️⚠️ **BA CỔNG phải qua hết mới được tự áp.** Thiếu cổng nào là máy tự ghi nhận tiền
 * sai, mà sổ vẫn CÂN:
 *   ① `refId` có thật và KHÔNG trùng trong lô — không có khoá duy nhất thì lượt đọc sau
 *      áp lại đúng giao dịch đó. Đây là cổng quan trọng nhất của cả tính năng.
 *   ② `jpGuessLoc_` ra ĐÚNG MỘT cơ sở — mơ hồ thì để người quyết.
 *   ③ Cơ sở đó còn nợ ĐỦ để nuốt hết số tiền — thừa tiền nghĩa là hiểu sai dòng đó.
 *
 * ⚠️ Và một cổng nữa ở ngoài: `JP_BANK_XAC_NHAN` — kế toán phải xác nhận **một lần** là
 * bản đồ cột đọc đúng. Chưa xác nhận thì hàm này CHỈ xếp hàng chờ, không tự áp gì.
 */
function jpDoiSoatNganHang_(u, ghi) {
  /* Gắn tiền nhận trước vào báo cáo vừa hoàn tất TRƯỚC ĐÃ — nhân viên bổ sung báo cáo
     xong là lượt chạy nền kế tiếp tự khớp, kế toán không phải nhớ đi làm tay. */
  var khop = ghi ? jpPhanBoNhanTruoc_(u) : { gan: 0, tien: 0 };
  var doc = jpDocGiaoDich_(0);
  var daDoc = jpBankDaDoc_();
  var moi = doc.rows.filter(function (r) { return jpChuaAp_(daDoc, r.refId); });

  /* Cổng ① — đếm refId trong CHÍNH lô này */
  var dem = {};
  moi.forEach(function (r) { dem[r.refId] = (dem[r.refId] || 0) + 1; });

  var daXacNhan = jpStr_(PropertiesService.getScriptProperties()
                         .getProperty('JP_BANK_XAC_NHAN')) === 'Y';

  var idx = jpLocIndex_();
  var bd = jpBanDoDinhDanh_();
  var conNo = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    if (jpStr_(r.status) !== JP_ST_DONE) return;
    var thieu = jpNum_(r.totalSubmit) - jpNum_(r.paid);
    if (thieu > 0) conNo[String(r.locationId)] = (conNo[String(r.locationId)] || 0) + thieu;
  });
  /* ⚠️ CHỤP LẠI trước khi vòng dưới trừ dần — bảng công nợ phải in số **phải nộp lúc
     đầu lượt**, không phải số còn lại sau khi đã tự áp. In số đã trừ là bảng tự mâu
     thuẫn với chính cột "Sẽ ghi" bên cạnh. */
  var conNoDau = {};
  Object.keys(conNo).forEach(function (k) { conNoDau[k] = conNo[k]; });

  var tuAp = [], cho = [];
  moi.forEach(function (r) {
    /*
     * ⚠️⚠️ MÃ ĐỊNH DANH XÉT TRƯỚC, VÀ CHẶN THẲNG — không rơi xuống mức dò mờ.
     *
     * Nguồn giao dịch dùng chung nhiều thương hiệu (đọc thật: 52 dòng POSH, 3 dòng JP).
     * Nội dung `... ND Go Di An KH705MTDMN0021` là tiền của **POSH**. Nếu để nó rơi
     * xuống mức dò theo tên / mã ngắn thì có ngày khớp bừa vào một cơ sở JP ⇒ **ghi tiền
     * của thương hiệu khác vào sổ JP, mà sổ JP vẫn CÂN**. Không phép kiểm nào của JP bắt
     * được — chỉ kế toán POSH phát hiện, bằng cách thấy MẤT tiền.
     */
    var dd = jpDoDinhDanh_(r.desc, bd);
    var g = dd.co ? { id: dd.id || '', ambiguous: !!dd.nhieu } : jpGuessLoc_(r.desc, idx);

    var ly = '';
    if (!r.refId)            ly = 'Không có mã tham chiếu — không chống trùng được';
    else if (dem[r.refId] > 1) ly = 'Mã tham chiếu ' + r.refId + ' trùng trong cùng lô';
    else if (!daXacNhan)     ly = 'Kế toán chưa xác nhận bản đồ cột đọc đúng';
    else if (dd.thuongHieuKhac)
      ly = 'Định danh ' + dd.dinhDanh.toUpperCase() + ' là của ' + dd.thuongHieuKhac +
           ', KHÔNG phải JP — tiền này không thuộc sổ JP';
    else if (dd.chuaKhai)
      ly = 'Định danh ' + dd.chuaKhai.toUpperCase() + ' chưa có trong bảng ' +
           JP_DINH_DANH_TAB + ' — chưa biết của thương hiệu nào';
    else if (dd.chuaNoi)
      ly = 'Định danh ' + jpStr_(dd.dinhDanh).toUpperCase() + ' là của JP nhưng chưa nối ' +
           'được vào cơ sở nào trong danh mục';
    else if (dd.nhieu)       ly = 'Nội dung mang nhiều mã định danh JP cùng lúc';
    else if (!dd.co)
      ly = 'Nội dung KHÔNG có mã định danh — nguồn này dùng chung nhiều thương hiệu ' +
           'nên không dò theo tên cơ sở được';
    else if (g.ambiguous)    ly = 'Nội dung khớp nhiều cơ sở';
    else if (!g.id)          ly = 'Không dò được cơ sở từ nội dung chuyển khoản';
    /* ⚠️⚠️ KHÔNG còn chặn khi "cơ sở còn nợ ít hơn số tiền" (Andy chốt 15/08/2026:
       *"cứ ghi vào đi anh sẽ cho nv bsung báo cáo"*). Tiền về trước khi báo cáo được
       duyệt là chuyện bình thường; chặn lại là app ngồi trên một khoản tiền THẬT mà
       không để dấu vết nào, và hàng chờ dài ra mãi không ai xử được.
       Phần vượt quá nợ đi vào dòng **NHẬN TRƯỚC** (`reportId` rỗng) — nằm ngoài sổ
       nhật ký cho tới khi `jpPhanBoNhanTruoc_` gắn được nó vào báo cáo thật. */
    if (ly) { cho.push({ r: r, locationId: g.id || '', lyDo: ly }); return; }
    conNo[g.id] -= r.amount;
    tuAp.push({ r: r, locationId: g.id });
  });

  var kq = null;
  if (ghi && tuAp.length) {
    /*
     * ⚠️⚠️ PHẢI TRUYỀN `overrides` — CƠ SỞ ĐÃ DÒ RA BẰNG MÃ ĐỊNH DANH.
     *
     * Bản trước truyền `{}`, nên `jpReconApply_` **dò lại từ đầu bằng `jpGuessLoc_`**
     * (khớp mờ theo TÊN cơ sở) và vứt bỏ toàn bộ kết quả định danh ở trên. Hai hậu quả,
     * cái sau nặng hơn nhiều:
     *
     * ① Nội dung CK thật **không có tên cơ sở** (`ND nop tien KH989MTDMN0007`) ⇒ dò mờ
     *    không ra gì ⇒ dòng bị bỏ với `unmatched`, **KHÔNG ghi đồng nào** — trong khi
     *    màn hình vẫn in *"đã tự áp 1 giao dịch"*. Tiền im lặng không vào sổ.
     * ② Nội dung có lẫn tên một cơ sở KHÁC ⇒ dò mờ khớp **sai cơ sở**, và tiền vào công
     *    nợ của cơ sở đó. Sổ **vẫn CÂN**. Toàn bộ chốt chặn theo mã định danh chỉ canh ở
     *    cổng xét, còn **bước GHI thì tự quyết lại** — tức chốt không giữ được gì.
     *
     * `overrides` khoá 1-based theo thứ tự dòng, đúng khuôn `jpReconApply_` đang đọc.
     */
    var ep = {};
    tuAp.forEach(function (x, i) { ep[String(i + 1)] = x.locationId; });
    /* ⚠️ RUỘT, không phải `jpReconApply_` — chỗ này ĐANG ở trong `jpLock_` của người
       gọi, lồng thêm một khoá nữa là tự chờ chính mình 30 giây rồi hỏng cả lượt. */
    kq = jpReconApTrong_(u, JP_PAY_CK, tuAp.map(function (x) { return x.r; }), ep);
  }
  var demGhi = {};
  if (ghi) {
    var luc = new Date();
    tuAp.forEach(function (x) {
      jpGhiBankGD_(daDoc, jpStr_(x.r.refId), {
        refId: x.r.refId, ngay: x.r.date, soTien: x.r.amount,
        noiDung: x.r.desc.slice(0, 300), locationId: x.locationId,
        trangThai: (kq && kq.ok) ? 'AP' : 'CHO',
        reportId: '', paymentId: '', docLuc: luc, apLuc: luc,
        apBoi: jpStr_(u.hoTen), lyDo: (kq && kq.ok) ? '' : 'Áp lô thất bại'
      }, demGhi);
    });
    cho.forEach(function (x) {
      var k = jpStr_(x.r.refId) || ('TAM-' + x.r.date + '-' + x.r.amount);
      jpGhiBankGD_(daDoc, k, {
        refId: k,
        ngay: x.r.date, soTien: x.r.amount,
        noiDung: x.r.desc.slice(0, 300), locationId: x.locationId,
        trangThai: 'CHO', reportId: '', paymentId: '',
        docLuc: luc, apLuc: '', apBoi: '', lyDo: x.lyDo
      }, demGhi);
    });
  }

  return {
    ok: true, ghi: !!ghi, daXacNhan: daXacNhan, khopTruoc: khop,
    theoCoSo: jpCongNoDoiChieuNH_(doc, daDoc, bd, conNoDau, tuAp),
    soDocDuoc: doc.soDong, soMoi: moi.length, demGhi: demGhi,
    soTuAp: tuAp.length, soCho: cho.length,
    tienTuAp: tuAp.reduce(function (s, x) { return s + x.r.amount; }, 0),
    tienCho: cho.reduce(function (s, x) { return s + x.r.amount; }, 0),
    cho: cho.slice(0, 50).map(function (x) {
      return { refId: x.r.refId, ngay: x.r.date, soTien: x.r.amount,
               noiDung: x.r.desc.slice(0, 160), lyDo: x.lyDo };
    }),
    apKq: kq,
    msg: !daXacNhan
      ? 'CHƯA tự áp gì — kế toán phải xác nhận bản đồ cột đọc đúng một lần trước đã.'
      : (ghi ? 'Đã tự áp ' : 'Xem trước — sẽ tự áp ') + tuAp.length + ' giao dịch, ' +
        cho.length + ' giao dịch chờ kế toán quyết.'
  };
}

/** Tiền NHẬN TRƯỚC chưa gắn báo cáo, theo cơ sở — `{ locationId: số tiền }`. */
function jpNhanTruoc_() {
  var m = {};
  jpRows_(JP_TABS.PAYMENTS).forEach(function (p) {
    if (jpStr_(p.reportId)) return;
    var id = String(p.locationId || '');
    if (id) m[id] = (m[id] || 0) + jpNum_(p.amount);
  });
  return m;
}

/**
 * GẮN TIỀN NHẬN TRƯỚC vào báo cáo vừa hoàn tất — chạy đầu mỗi lượt đối soát có ghi.
 *
 * Andy chốt 15/08/2026: *"cứ ghi vào đi anh sẽ cho nv bsung báo cáo"*. Vế sau của câu đó
 * là việc của hàm này — không có nó thì tiền nằm mãi ngoài sổ và kế toán phải nhớ đi khớp
 * tay, tức đúng cái việc app sinh ra để khỏi phải làm.
 *
 * ⚠️⚠️ **BẤT BIẾN: tổng `JP_Payments` của một cơ sở KHÔNG ĐƯỢC ĐỔI khi phân bổ.** Đây chỉ
 * là chuyển tiền từ dòng "chưa gắn" sang dòng "đã gắn báo cáo". Thêm dòng mới mà quên trừ
 * dòng nhận trước là **đếm hai lần một khoản tiền thật**, và sổ **vẫn CÂN** vì cả hai vế
 * bút toán đều đủ. Có phép kiểm khoá đúng con số này.
 *
 * ⚠️ Trừ hết thì **XOÁ dòng nhận trước**, đừng để dòng `amount = 0`: `jpNhanTruoc_` cộng
 * theo `reportId` rỗng nên dòng 0 vô hại về số, nhưng nó làm bảng công nợ in "còn nhận
 * trước" cho cơ sở đã khớp xong.
 */
function jpPhanBoNhanTruoc_(u) {
  var truoc = jpRows_(JP_TABS.PAYMENTS).filter(function (p) {
    return !jpStr_(p.reportId) && jpNum_(p.amount) > 0 && String(p.locationId || '');
  });
  if (!truoc.length) return { gan: 0, tien: 0 };

  var byLoc = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    if (jpStr_(r.status) !== JP_ST_DONE) return;
    if (jpNum_(r.paid) >= jpNum_(r.totalSubmit)) return;
    (byLoc[String(r.locationId)] = byLoc[String(r.locationId)] || []).push(r);
  });

  var gan = 0, tien = 0;
  truoc.forEach(function (p) {
    var locId = String(p.locationId);
    var ds = byLoc[locId];
    if (!ds || !ds.length) return;
    var a = jpAllocate_(ds.slice(), jpNum_(p.amount));
    if (!a.alloc.length) return;

    a.alloc.forEach(function (x) {
      var newPaid = x.paid + x.add;
      jpFields_(JP_TABS.REPORTS, x._row, {
        paid: newPaid, paidDate: jpDate_(p.payDate) || jpToday_(),
        payStatus: jpPayStatus_(newPaid, x.total)
      });
      /* ⚠️ PHẢI cập nhật `paid` trong BỘ NHỚ, không chỉ ghi xuống sheet. `jpAllocate_`
         đọc `r.paid` từ chính mấy object này, nên để nguyên là dòng nhận trước THỨ HAI
         của cùng cơ sở phân bổ lại vào báo cáo đã đầy ⇒ `paid` vượt `totalSubmit` và
         cơ sở trông như đã trả dư. Chỉ lộ ra khi có từ hai dòng nhận trước trở lên. */
      ds.forEach(function (r) { if (r._row === x._row) r.paid = newPaid; });
      jpAppend_(JP_TABS.PAYMENTS, {
        id: jpNextId_(JP_TABS.PAYMENTS, 'PM'),
        reportId: x.reportId, locationId: locId, amount: x.add,
        payDate: jpDate_(p.payDate) || jpToday_(), method: jpStr_(p.method) || JP_PAY_CK,
        note: 'Khớp từ tiền nhận trước ' + jpStr_(p.id),
        photoId: '', photoUrl: '', isSupplement: '',
        createdBy: jpStr_(u && u.hoTen) || 'Tự động', createdAt: new Date()
      });
      gan++; tien += x.add;
    });
    byLoc[locId] = ds.filter(function (r) {
      return jpNum_(r.paid) < jpNum_(r.totalSubmit);
    });

    if (a.leftOver > 0) jpFields_(JP_TABS.PAYMENTS, p._row, { amount: a.leftOver });
    else jpDelete_(JP_TABS.PAYMENTS, p._row);
  });

  if (gan) jpAudit_(u, 'BANK_KHOP_TRUOC', '', '', { gan: gan, tien: tien });
  return { gan: gan, tien: tien };
}

/**
 * CÔNG NỢ ĐỐI CHIẾU NGÂN HÀNG — một dòng một cơ sở (khuôn bảng ① của web kế toán POSH).
 *
 * Trả lời đúng câu kế toán hỏi: *cơ sở này phải nộp bao nhiêu, ngân hàng đã về bao nhiêu,
 * sổ đã ghi bao nhiêu, còn bao nhiêu chưa vào sổ.* Danh sách giao dịch không trả lời được
 * câu đó — nó chỉ nói từng dòng tiền, không nói cơ sở nào đang hụt.
 *
 * ⚠️ **`Tiền vào NH` đếm MỌI dòng dò ra cơ sở, kể cả dòng đã áp lượt trước.** Chỉ đếm
 * dòng mới thì cột này tụt về 0 ngay sau lượt áp đầu tiên, và bảng biến thành *"chưa có
 * tiền về"* cho cơ sở vừa nộp xong — sai đúng lúc kế toán đi tra.
 *
 * ⚠️ **`Lệch` = tiền về − đã ghi − sẽ ghi**, tức **tiền đã về mà sẽ KHÔNG vào sổ**. Đó là
 * con số phải xử. Đừng đổi thành `phải nộp − tiền về`: cơ sở nộp dư hay nộp thiếu là
 * chuyện công nợ, còn cột này đo chỗ **app không ghi được**, hai việc khác nhau.
 *
 * ⚠️ Cơ sở **đã ngưng mà có phát sinh thì VẪN hiện** — cùng luật với `jpBaoCaoDoanhThuNgay`:
 * `active='N'` chỉ chặn việc MỚI, không được viết lại quá khứ. Lọc thẳng là tiền của điểm
 * vừa dẹp biến mất khỏi bảng mà không ai báo.
 */
function jpCongNoDoiChieuNH_(doc, daDoc, bd, conNoDau, tuAp) {
  /* Tiền THẬT đã về, theo cơ sở — quét CẢ lô đọc được, không riêng dòng mới. */
  var vaoNH = {}, soGD = {};
  doc.rows.forEach(function (r) {
    var d = jpDoDinhDanh_(r.desc, bd);
    /* ⚠️ Bốn điều kiện đầu **hôm nay là thừa** — `jpDoDinhDanh_` trả `id: ''` cho cả bốn
       trường hợp đó nên `!d.id` một mình đã chặn đủ, và **thử ngược bỏ chúng đi vẫn xanh**.
       Giữ lại là CỐ Ý: đây là cột tiền của bảng công nợ JP, mà `thuongHieuKhac` nghĩa là
       tiền của POSH. Ngày nào `jpDoDinhDanh_` được sửa để trả kèm `id` ở nhánh đó (rất
       hợp lý — để nói được "mã này của POSH, cơ sở nào") thì thiếu dòng này là **tiền
       POSH lặng lẽ cộng vào khoản phải thu của JP**. Đừng "dọn" cho gọn. */
    if (!d.co || d.thuongHieuKhac || d.nhieu || d.chuaKhai || !d.id) return;
    vaoNH[d.id] = (vaoNH[d.id] || 0) + r.amount;
    soGD[d.id] = (soGD[d.id] || 0) + 1;
  });

  /* Tiền ngân hàng ĐÃ vào sổ — lấy từ chính bảng `JP_BankGD`, không suy từ `paid` của
     báo cáo: `paid` gộp cả tiền mặt và đối soát file, so với `vaoNH` là so hai thứ khác
     đơn vị và cột Lệch sẽ luôn sai. */
  var daGhi = {};
  Object.keys(daDoc).forEach(function (k) {
    var b = daDoc[k];
    if (jpStr_(b.trangThai) !== 'AP') return;
    var id = String(b.locationId || '');
    if (id) daGhi[id] = (daGhi[id] || 0) + jpNum_(b.soTien);
  });

  var seGhi = {};
  tuAp.forEach(function (x) {
    seGhi[x.locationId] = (seGhi[x.locationId] || 0) + x.r.amount;
  });

  /* Mã định danh theo cơ sở — đảo bản đồ đã dựng, khỏi đọc lại bảng. */
  var ddTheo = {};
  Object.keys(bd.jp).forEach(function (m) {
    var id = bd.jp[m];
    if (!id) return;
    if (!ddTheo[id]) ddTheo[id] = [];
    ddTheo[id].push(m.toUpperCase());
  });

  var truoc = jpNhanTruoc_();

  var bang = [];
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) {
    var id = String(l.id), active = jpStr_(l.active) !== 'N';
    var phaiNop = conNoDau[id] || 0, vao = vaoNH[id] || 0;
    var dg = daGhi[id] || 0, sg = seGhi[id] || 0, nt = truoc[id] || 0;
    if (!active && !phaiNop && !vao && !dg && !sg && !nt) return;

    var lech = vao - dg - sg;
    var tt;
    if (lech > 0 && phaiNop <= 0)
      tt = 'Tiền về nhưng cơ sở KHÔNG còn nợ — chưa có báo cáo hoàn tất để ghi vào';
    else if (lech > 0) tt = 'Còn ' + jpMoney_(lech) + 'đ chưa vào sổ';
    else if (lech < 0) tt = 'Sổ ghi NHIỀU HƠN tiền về ' + jpMoney_(-lech) + 'đ';
    else if (nt > 0)   tt = 'Đang giữ ' + jpMoney_(nt) + 'đ NHẬN TRƯỚC — chờ báo cáo';
    else if (vao > 0)  tt = 'Khớp';
    else if (phaiNop > 0) tt = 'Chưa có tiền về';
    else tt = '—';

    bang.push({
      locationId: id, code: jpStr_(l.code), name: jpStr_(l.name), active: active,
      dinhDanh: (ddTheo[id] || []).join(', '),
      phaiNop: phaiNop, tienVaoNH: vao, soGD: soGD[id] || 0,
      daGhiNhan: dg, seGhi: sg, nhanTruoc: nt, lech: lech, trangThai: tt
    });
  });
  /* Cơ sở CÓ VIỆC lên trước: lệch lớn nhất trên cùng, rồi tới cơ sở còn nợ. */
  bang.sort(function (a, b) { return (b.lech - a.lech) || (b.phaiNop - a.phaiNop); });
  return bang;
}

/** Cửa cho WEB — kế toán bấm tay. */
function jpDoiSoatNganHang(token, ghi) {
  var u = jpNeedKT_(jpAuth_(token));
  var r = jpLock_(function () { return jpDoiSoatNganHang_(u, !!ghi); });
  /* Gói kèm tình trạng lịch: màn này mở ra là cần cả hai, tách hai lượt gọi là tốn
     hai đợt chờ `google.script.run` (~0,45s mỗi đợt). Xem mục "SỐ ĐỢT CHỜ". */
  try { r.lich = jpTinhTrangLich_(); } catch (e) { r.lich = null; }
  return r;
}

/**
 * Cửa cho TRIGGER — chạy 15 phút/lần, không có token.
 * ⚠️ Nuốt lỗi và ghi audit: trigger chết lặng thì không ai biết, mà ném lỗi ra thì
 * Apps Script chỉ gửi mail cho chủ sở hữu rồi thôi. Cùng luật với `jpAudit_`.
 */
function jpTuDongDoiSoatNganHang_() {
  var u = { id: 'SYSTEM', hoTen: 'Tự động (trigger)', role: 'KETOAN' };
  var P = PropertiesService.getScriptProperties();
  try {
    /*
     * ⚠️⚠️ CỔNG SỚM — CHƯA XÁC NHẬN CỘT THÌ **KHÔNG CHẠY**, và nhất là KHÔNG LẤY KHOÁ.
     *
     * Cổng `JP_BANK_XAC_NHAN` tắt thì `jpDoiSoatNganHang_` đẩy **mọi** dòng vào `chờ` với
     * lý do *"kế toán chưa xác nhận bản đồ cột"* ⇒ `tuAp` rỗng ⇒ lượt chạy **không áp nổi
     * một đồng nào**. Nhưng nhánh `if (ghi)` vẫn ghi lại **toàn bộ hàng chờ**:
     * `jpGhiBankGD_` một lượt ghi cho MỖI dòng.
     *
     * Đo trên sheet thật 28/08/2026: **201 dòng chờ, `tuAp: 0`, lặp lại y hệt ở CẢ 3.618
     * lượt chạy** — tức mỗi 5 phút app ghi lại 201 dòng y nguyên, trong lúc **đang giữ
     * khoá toàn cục**. Số lần va với nhân viên leo thang 2 → 6 → 16 → **38 lần/ngày**
     * (27/08), và đó chính là câu *"Thời gian chờ khóa"* nhân viên VINWONDER nhìn thấy.
     *
     * ⇒ Lượt chạy **không thể** làm được gì thì đừng chạy. Không mất gì: hàng chờ chỉ đổi
     * khi có giao dịch mới hoặc khi kế toán mở màn Đối soát — mà lúc mở màn, nút bấm tay
     * (`jpDoiSoatNganHang`) dựng lại bảng đầy đủ.
     *
     * ⚠️ **Ghi lý do vào Script Property, đừng im lặng** — có lịch mà không thấy dấu vết
     * chạy trông y hệt lúc trigger chết. Màn ⑤ đọc đúng dòng này.
     */
    if (jpStr_(P.getProperty('JP_BANK_XAC_NHAN')) !== 'Y') {
      P.setProperty('JP_BANK_LICH_LAN_CUOI', jpNgayGio_(new Date()) +
        ' · BỎ LƯỢT đối soát — chưa xác nhận bản đồ cột nên không áp được gì');
      /* ⚠️ CHỈ bỏ phần ĐỐI SOÁT, KHÔNG bỏ phần tính lại cảnh báo. Hai việc không liên
         quan gì nhau: bản đồ cột là chuyện của file ngân hàng, còn cảnh báo là chuyện của
         báo cáo nhân viên — Andy chốt 16/08 *"cho lịch tự tính lại đi em"*. Bỏ cả hai thì
         kế toán lại phải đi bấm tay từng báo cáo, mà triệu chứng y hệt lúc chưa có gì.
         `day-chuyen.js` bắt đúng chỗ này ở lượt đầu. */
      jpNenTinhLaiCanhBao_(u);
      return { ok: false, boLuot: true,
               msg: 'Bỏ lượt đối soát: chưa xác nhận bản đồ cột đọc đúng.' };
    }

    /*
     * ⚠️ `jpLockThu_` chứ KHÔNG phải `jpLock_`. Việc nền mà chờ 30 giây là nó **giữ chỗ
     * trước mặt nhân viên đang bấm Nộp** — xem chú thích ở `jpLockThu_` (`01_Core`).
     * Bận thì bỏ lượt, 5 phút nữa chạy lại; nhân viên đứng ở điểm bán thì không chờ được.
     */
    var t = jpLockThu_(function () { return jpDoiSoatNganHang_(u, true); });
    if (!t.lay) {
      P.setProperty('JP_BANK_LICH_LAN_CUOI', jpNgayGio_(new Date()) +
        ' · BỎ LƯỢT — có người đang ghi, nhường cho họ');
      return { ok: false, boLuot: true, msg: 'Bỏ lượt: đang có người ghi.' };
    }
    /* Lấy được khoá — lượt này chạy thật. */
    var r = t.kq;
    /* Dấu vết lượt chạy nền GẦN NHẤT — đường DUY NHẤT chứng minh trigger thật sự nổ.
       Có lịch trong `getProjectTriggers()` chỉ nói lịch được ĐẶT, không nói nó có chạy
       hay không (hết quota, lỗi quyền, project bị treo — trigger im lặng ngừng). */
    /* ⚠️ In luôn BẢNG ĐẾM GHI vào dòng tình trạng. Đây là đường **duy nhất** nhìn thấy
       được chuyện "lượt chạy ghi lại bao nhiêu dòng, và vì ô nào" — audit đã thôi ghi mọi
       lượt (đúng), còn bản xuất sheet thì không dựng lại được cái máy chủ nhìn thấy. Không
       có dòng này thì mỗi lần nghi ngờ lại phải đoán, mà đoán đúng là chuyện hên xui. */
    P.setProperty('JP_BANK_LICH_LAN_CUOI', jpNgayGio_(new Date()) +
                  ' · ' + r.soTuAp + ' áp / ' + r.soCho + ' chờ · ghi ' +
                  jpDemGhiChu_(r.demGhi));
    /* ⚠️ CHỈ ghi audit khi lượt chạy THẬT SỰ áp được gì. Trước bản này nó ghi MỌI lượt:
       3.618 dòng `BANK_AUTO` trên tổng 3.984 dòng `JP_Audit` — **91% bảng audit là tiếng
       ồn**, và bảng phình ra thì mọi `jpAppend_` sau đó đều chậm theo. Dấu vết "trigger có
       nổ hay không" đã nằm ở `JP_BANK_LICH_LAN_CUOI` (ghi MỌI lượt, kể cả lượt bỏ), nên
       không mất đường kiểm nào. */
    if (jpNum_(r.soTuAp) > 0) {
      jpAudit_(u, 'BANK_AUTO', '', '', { tuAp: r.soTuAp, cho: r.soCho, moi: r.soMoi });
    }

    /* Tính lại cảnh báo cho cả sheet — xem `jpTinhLaiCanhBaoQuet_`.
       ⚠️ Bọc try/catch RIÊNG và đặt SAU phần đối soát: đối soát là việc **ghi tiền**,
       tính lại cảnh báo chỉ là việc **hiển thị**. Cảnh báo hỏng tuyệt đối không được
       kéo theo việc ghi tiền hỏng — cùng luật với `jpAudit_` và `jpXuatKhoBaoCao_`. */
    jpNenTinhLaiCanhBao_(u);
    return r;
  } catch (e) {
    jpAudit_(u, 'BANK_AUTO_LOI', '', '', { loi: String(e && e.message || e) });
    return { ok: false, msg: String(e && e.message || e) };
  }
}

/**
 * Tính lại cảnh báo cho cả sheet — phần CHẠY NỀN. Gọi ở **mọi** đường ra của
 * `jpTuDongDoiSoatNganHang_`, kể cả đường bỏ lượt đối soát.
 *
 * ⚠️ Đặt SAU phần đối soát và bọc `try/catch` RIÊNG: đối soát là việc **ghi tiền**, tính
 * lại cảnh báo chỉ là việc **hiển thị**. Cảnh báo hỏng tuyệt đối không được kéo theo việc
 * ghi tiền hỏng — cùng luật với `jpAudit_` và `jpXuatKhoBaoCao_`.
 *
 * ⚠️ `jpLockThu_` chứ không phải `jpLock_`: việc hiển thị càng không có tư cách bắt nhân
 * viên đang bấm Nộp phải chờ. Bận thì bỏ, năm phút nữa tính lại.
 */
/**
 * Diễn giải bảng đếm của `jpGhiBankGD_` thành một câu ngắn cho dòng tình trạng.
 * Ví dụ: `0 · bỏ qua 216` (không đụng sheet) hoặc `79 (ngay:79) · bỏ qua 137`.
 */
function jpDemGhiChu_(dem) {
  dem = dem || {};
  var bo = jpNum_(dem.boQua), phan = [], tong = 0;
  Object.keys(dem).forEach(function (k) {
    if (k === 'boQua') return;
    tong += jpNum_(dem[k]);
    phan.push(k + ':' + jpNum_(dem[k]));
  });
  return tong + (phan.length ? ' (' + phan.join(' ') + ')' : '') + ' · bỏ qua ' + bo;
}

function jpNenTinhLaiCanhBao_(u) {
  try { jpLockThu_(function () { return jpTinhLaiCanhBaoQuet_(u); }); }
  catch (e) { jpAudit_(u, 'WARN_RECALC_LOI', '', '', { loi: String(e && e.message || e) }); }
}

/**
 * Apps Script **chỉ nhận đúng 5 giá trị** cho `everyMinutes`. Truyền số khác thì nó ném
 * lỗi khó đọc, nên nhích LÊN mốc hợp lệ gần nhất và **nói ra** đã nhích.
 *
 * ⚠️ Nhích LÊN chứ không nhích xuống: chạy dày hơn người ta yêu cầu là tự ý tiêu quota
 * trigger của cả project, mà quota đó dùng chung với mọi lịch khác.
 */
var JP_LICH_MOC = [1, 5, 10, 15, 30];

/**
 * Nhích lên mốc hợp lệ gần nhất. ⚠️ Tách riêng vì **hai chỗ** phải ra CÙNG một con số:
 * lúc tạo trigger, và lúc ghi nhịp vào Script Property để màn hình đọc lại. Tính lại rời
 * ở chỗ thứ hai là màn hình in một nhịp mà lịch chạy một nhịp khác.
 */
function jpNhipHopLe_(phut) {
  var xin = Number(phut) || 5;
  var n = 0;
  JP_LICH_MOC.forEach(function (m) { if (!n && m >= xin) n = m; });
  return n || 30;
}

/** Lập / huỷ lịch chạy tự động. Hàm ADMIN, chạy tay từ trình chỉnh sửa. */
function jpLapLichDoiSoat_(phut) {
  var xin = Number(phut) || 5;
  var n = jpNhipHopLe_(xin);

  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'jpTuDongDoiSoatNganHang_') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('jpTuDongDoiSoatNganHang_').timeBased().everyMinutes(n).create();
  return 'Đã lập lịch đối soát ngân hàng mỗi ' + n + ' phút' +
         (n !== xin ? ' (xin ' + xin + ' phút — Apps Script chỉ nhận ' +
                      JP_LICH_MOC.join(' / ') + ', đã nhích lên ' + n + ')' : '');
}

/**
 * ⚠️ **Trình chỉnh sửa Apps Script KHÔNG truyền được tham số** cho hàm bấm Run — ô chọn
 * chỉ có tên hàm. Nên `jpLapLichDoiSoat_(5)` là thứ **không bấm được**, phải có hàm
 * không tham số. Đừng "dọn" mấy hàm này đi cho gọn: bỏ chúng là lịch chỉ đặt được bằng
 * cách mở Console gõ tay, mà đó là chỗ gõ nhầm thì không ai thấy.
 */
function jpLapLich5Phut_()  { return jpLapLichDoiSoat_(5); }
function jpLapLich15Phut_() { return jpLapLichDoiSoat_(15); }

/**
 * Xem lịch đang có — **đường kiểm độc lập** với câu trả về của lúc lập.
 *
 * ⚠️ Câu *"Đã lập lịch…"* chỉ chứng minh lời gọi không ném lỗi, y hệt `Pushed N files.`
 * của clasp. Phải hỏi lại `getProjectTriggers()` mới biết lịch có thật hay không.
 */
function jpTinhTrangLich_() {
  var ds = ScriptApp.getProjectTriggers().filter(function (t) {
    return t.getHandlerFunction() === 'jpTuDongDoiSoatNganHang_';
  });
  var P = PropertiesService.getScriptProperties();
  var xn = P.getProperty('JP_BANK_XAC_NHAN') === 'Y';
  /* ⚠️ Apps Script KHÔNG đọc lại được nhịp phút từ đối tượng trigger — `ClockTriggerBuilder`
     chỉ ghi vào chứ không trả ra. Nên nhịp lấy từ Script Property đã ghi lúc lập, và đó là
     số **NHỚ LẠI**, không phải số đo được. Số lịch mới là sự thật; nhịp chỉ là ghi chú, và
     phải nói rõ như vậy — người xoá lịch bằng tay ở màn ⏰ thì property vẫn còn nguyên. */
  var phut = Number(P.getProperty('JP_BANK_LICH_PHUT')) || 0;
  var lanCuoi = jpStr_(P.getProperty('JP_BANK_LICH_LAN_CUOI'));
  var msg = ds.length
    ? 'CÓ ' + ds.length + ' lịch đối soát ngân hàng đang chạy' +
      (phut ? ' — đã đặt ' + phut + ' phút một lượt.' : '.')
    : 'KHÔNG có lịch nào — đối soát chỉ chạy khi kế toán tự bấm.';
  /* ⚠️ Có lịch mà CHƯA CHẠY LẦN NÀO là tình trạng riêng, phải nói ra: đặt xong mà quá
     một nhịp vẫn chưa có dấu vết nghĩa là trigger không nổ (hết quota / lỗi quyền), chứ
     không phải "chưa tới giờ". Im lặng ở đây là ngồi đợi một thứ đã chết. */
  msg += '\nLượt chạy nền gần nhất: ' + (lanCuoi || 'CHƯA có lượt nào');
  /* Có lịch mà chưa xác nhận cột thì nó chạy ĐỀU mà KHÔNG áp gì — trông y hệt lúc hỏng,
     nên phải nói ra ngay đây thay vì để đi dò trong audit. */
  msg += '\nCổng xác nhận cột: ' + (xn ? 'ĐÃ bật ⇒ có tự áp'
                                       : 'CHƯA bật ⇒ chạy nhưng KHÔNG áp gì');
  /* ⚠️ NÓI RA việc thứ hai của lượt chạy nền. Không nói thì kế toán vẫn đi mở từng báo cáo
     bấm "Tính lại cảnh báo" bằng tay — mà đó đúng là việc lịch này vừa gánh hộ. */
  if (ds.length) msg += '\nMỗi lượt còn TỰ tính lại cảnh báo cho mọi báo cáo.';
  return { co: ds.length > 0, soLich: ds.length, phut: phut, xacNhanCot: xn,
           lanCuoi: lanCuoi, msg: msg };
}

/**
 * Xem lịch đang có — **đường kiểm độc lập** với câu trả về của lúc lập.
 *
 * ⚠️ Câu *"Đã lập lịch…"* chỉ chứng minh lời gọi không ném lỗi, y hệt `Pushed N files.`
 * của clasp. Phải hỏi lại `getProjectTriggers()` mới biết lịch có thật hay không.
 */
function jpXemLichDoiSoat_() {
  var msg = jpTinhTrangLich_().msg;
  Logger.log(msg);
  return msg;
}

/*──────── ĐẶT LỊCH TỪ WEB KẾ TOÁN — không phải mở trình chỉnh sửa ────────
 * ⚠️⚠️ **Ô chọn hàm của trình chỉnh sửa KHÔNG liệt kê hàm có dấu `_` cuối tên** (ảnh Andy
 * gửi 15/08/2026: cả danh sách chỉ có `jpReconHistory` · `jpSoCongNo` · `jpRevenueBoard`…).
 * Nên `jpLapLich5Phut_` là thứ **không bấm được**, và màn ⏰ "Add Trigger" cũng vướng y vậy
 * vì hàm chạy `jpTuDongDoiSoatNganHang_` cũng có dấu `_`.
 *
 * ⚠️ **KHÔNG được chữa bằng cách bỏ dấu `_`.** Web ở `ANYONE_ANONYMOUS`: hàm không dấu là
 * ai có link cũng gọi được từ console, tức người lạ đặt/xoá được lịch của cả hệ thống.
 * Chữa đúng là **hàm nhận `token` + `jpNeedKT_`**, gọi từ web — cùng cửa với mọi hàm khác.
 *
 * ⚠️ Câu ghi ở `CLAUDE.md` *"trình chỉnh sửa vẫn chạy được hàm có `_`"* **chỉ đúng cho bản
 * editor cũ**. Đừng dựng hướng dẫn nào dựa vào nó nữa. */

/** Web kế toán đọc tình trạng lịch. */
function jpLichDoiSoatNH(token) {
  jpNeedKT_(jpAuth_(token));
  return jpTinhTrangLich_();
}

/** Web kế toán đặt / tắt lịch. `phut = 0` là TẮT. */
function jpDatLichDoiSoatNH(token, phut) {
  var u = jpNeedKT_(jpAuth_(token));
  var n = Number(phut) || 0;
  var P = PropertiesService.getScriptProperties();
  var msg;
  if (n > 0) {
    msg = jpLapLichDoiSoat_(n);
    /* Ghi nhịp SAU khi lập xong — lập ném lỗi thì property không được nói dối là đã đặt. */
    P.setProperty('JP_BANK_LICH_PHUT', String(jpNhipHopLe_(n)));
  } else {
    msg = jpHuyLichDoiSoat_();
    P.deleteProperty('JP_BANK_LICH_PHUT');
  }
  jpAudit_(u, 'BANK_LICH', '', '', { phut: n });
  var t = jpTinhTrangLich_();
  t.msg = msg + '\n' + t.msg;
  return t;
}
function jpHuyLichDoiSoat_() {
  var d = 0;
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'jpTuDongDoiSoatNganHang_') {
      ScriptApp.deleteTrigger(t); d++;
    }
  });
  return 'Đã huỷ ' + d + ' lịch';
}

/** Kế toán xác nhận bản đồ cột đọc ĐÚNG — cổng mở luồng tự áp. */
function jpXacNhanCotNganHang(token, dong) {
  var u = jpNeedKT_(jpAuth_(token));
  PropertiesService.getScriptProperties()
    .setProperty('JP_BANK_XAC_NHAN', dong === false ? 'N' : 'Y');
  jpAudit_(u, 'BANK_XAC_NHAN_COT', '', '', { bat: dong !== false });
  return { ok: true, msg: dong === false
    ? 'Đã TẮT tự áp — từ giờ mọi giao dịch chỉ xếp hàng chờ.'
    : 'Đã xác nhận. Từ giờ giao dịch khớp chắc chắn sẽ được tự áp.' };
}

/*──────────────────── ⑤b HUỶ MỘT LÔ ĐÃ ÁP ────────────────────*/

/**
 * Áp nhầm thì huỷ đúng lô đó và TRỪ LẠI theo phân bổ đã lưu (`allocJson`).
 * Chỉ kế toán doanh thu, bắt ghi lý do, ghi một dòng nghịch vào JP_ReconLog.
 * Huỷ xong `jpReconSeen_` không thấy lô nữa nên áp lại được sau khi sửa file.
 *
 * KHÔNG đụng bất kỳ số doanh thu nào — vẫn đúng 3 ô như lúc áp.
 */
function jpReconUndo(token, batchId, reason) {
  var u = jpNeedKT_(jpAuth_(token));
  if (!jpIsKtRev_(u)) throw new Error('Chỉ kế toán doanh thu được huỷ lô đối soát');

  var bid = jpStr_(batchId);
  var ly = jpStr_(reason);
  if (!bid) throw new Error('Thiếu mã lô');
  if (!ly) throw new Error('Nhập lý do huỷ lô');

  return jpLock_(function () {
    var log = jpReconSeen_(bid);
    if (!log) throw new Error('Không thấy lô ' + bid + ' đang có hiệu lực (có thể đã huỷ rồi)');

    var alloc = [];
    try { alloc = JSON.parse(jpStr_(log.allocJson) || '[]'); } catch (e) { alloc = []; }
    if (!alloc.length) {
      throw new Error('Lô ' + bid + ' áp từ trước khi hệ thống lưu phân bổ — ' +
                      'phải sửa tay trong sheet, không huỷ tự động được');
    }

    /* Đọc báo cáo 1 lần rồi tra map, không đọc trong vòng lặp */
    var byId = {};
    jpRows_(JP_TABS.REPORTS).forEach(function (r) { byId[String(r.id)] = r; });

    var hoanLai = 0, soBaoCao = 0, thieu = [];
    alloc.forEach(function (x) {
      var head = byId[String(x.reportId)];
      if (!head) { thieu.push(String(x.reportId)); return; }

      var truoc = jpNum_(head.paid);
      var sau = Math.max(0, truoc - jpNum_(x.add));
      jpFields_(JP_TABS.REPORTS, head._row, {
        paid: sau,
        payStatus: jpPayStatus_(sau, jpNum_(head.totalSubmit))
      });
      hoanLai += (truoc - sau);
      soBaoCao++;
    });

    /*
     * Xoá luôn dòng `JP_Payments` mà lô này đã sinh. Không xoá thì trừ `paid` mà
     * dòng thu còn nguyên ⇒ sổ nhật ký chung có bút toán 1111/1121 cho tiền đã
     * huỷ, và `jpKiemTraButToan` báo lệch ngược chiều.
     *
     * Tìm theo `payId` trong `allocJson` nếu có (lô áp từ bản này trở đi), còn
     * lô cũ thì dò theo `note` = 'Đối soát lô <batchId>'. Xoá từ DƯỚI LÊN vì
     * `jpDelete_` xoá theo số dòng — xoá từ trên xuống là mọi dòng sau tụt một
     * bậc rồi xoá sai dòng.
     */
    var canXoa = {};
    alloc.forEach(function (x) { if (x.payId) canXoa[String(x.payId)] = 1; });
    var ghiChuLo = 'Đối soát lô ' + bid;
    var xoaDong = jpRows_(JP_TABS.PAYMENTS).filter(function (pm) {
      return canXoa[jpStr_(pm.id)] || jpStr_(pm.note) === ghiChuLo;
    });
    xoaDong.sort(function (a, b) { return jpNum_(b._row) - jpNum_(a._row); });
    xoaDong.forEach(function (pm) { jpDelete_(JP_TABS.PAYMENTS, pm._row); });
    var soDongThuXoa = xoaDong.length;

    /* Đánh dấu lô gốc đã huỷ + ghi một dòng nghịch để soi lại lịch sử */
    jpFields_(JP_TABS.RECON, log._row, {
      undoneAt: new Date(), undoneBy: u.hoTen, undoneReason: ly
    });
    jpReconLog_(u, bid, jpStr_(log.kind) + '-UNDO', {
      rows: alloc.length, matched: soBaoCao, ambiguous: 0, amount: -hoanLai,
      note: 'Huỷ lô ' + bid + ' · trừ lại ' + jpMoney_(hoanLai) + 'đ · xoá ' +
            soDongThuXoa + ' dòng thu · lý do: ' + ly +
            (thieu.length ? ' · KHÔNG thấy ' + thieu.length + ' báo cáo' : '')
    });
    jpAudit_(u, 'RECON_UNDO', '', bid,
             { hoanLai: hoanLai, soBaoCao: soBaoCao, soDongThuXoa: soDongThuXoa,
               thieu: thieu, reason: ly });

    return {
      ok: true, batchId: bid, soBaoCao: soBaoCao, hoanLai: hoanLai,
      thieu: thieu,
      msg: 'Đã huỷ lô ' + bid + ' · trừ lại ' + jpMoney_(hoanLai) + 'đ trên ' +
           soBaoCao + ' báo cáo' +
           (thieu.length ? ' · ' + thieu.length + ' báo cáo không còn tồn tại' : '')
    };
  });
}

/** Lịch sử các lô đã áp, để kế toán chọn lô cần huỷ. */
function jpReconHistory(token, limit) {
  jpNeedKT_(jpAuth_(token));
  var n = jpNum_(limit) || 30;
  return jpRows_(JP_TABS.RECON)
    .filter(function (r) { return jpStr_(r.kind).indexOf('UNDO') < 0; })
    .sort(function (a, b) {
      var x = a.at instanceof Date ? a.at.getTime() : 0;
      var y = b.at instanceof Date ? b.at.getTime() : 0;
      return y - x;
    })
    .slice(0, n)
    .map(function (r) {
      return {
        at: r.at || '', who: jpStr_(r.who), batchId: jpStr_(r.batchId),
        kind: jpStr_(r.kind), rows: jpNum_(r.rows), matched: jpNum_(r.matched),
        amount: jpNum_(r.amount), note: jpStr_(r.note),
        undone: !!jpStr_(r.undoneAt), undoneBy: jpStr_(r.undoneBy),
        undoneAt: r.undoneAt || '', undoneReason: jpStr_(r.undoneReason),
        coTheHuy: !jpStr_(r.undoneAt) && !!jpStr_(r.allocJson)
      };
    });
}

/*──────────────────── ⑥ XÁC NHẬN NỘP THỦ CÔNG ────────────────────*/

/**
 * mode = 'ADD' (mặc định) cộng thêm vào số đã nhận · 'SET' đặt lại tổng.
 *
 * Trước đây chỉ có một cách và là GÁN THẲNG, trong khi jpReconApply thì cộng dồn:
 * đối soát vừa áp 500k, kế toán xác nhận tay 300k là paid thành 300k, mất 500k.
 * Nay kế toán chọn rõ ý mình, mặc định là cộng thêm cho khớp với đối soát.
 */
/**
 * Ba phương thức thu. Andy chốt 04/08/2026: *"Bắt buộc chọn cách thu chứ làm sao mà
 * chưa rõ cách thu"* — nên đây là danh sách ĐÓNG và máy chủ chặn nếu không thuộc.
 * Đừng thêm nhánh "để trống thì mặc định TM": mặc định là nói sai đã nhận tiền mặt.
 */
function jpCachThu_(method) {
  var m = jpStr_(method).toUpperCase();
  if (m !== JP_PAY_TM && m !== JP_PAY_CK && m !== JP_PAY_QR) {
    throw new Error('Chọn cách thu: TM (tiền mặt) · CK (chuyển khoản) · QR');
  }
  return m;
}

/**
 * KHAI LẠI CÁCH THU cho phần tiền đã nhận mà chưa biết vào bằng cách nào.
 *
 * Andy chốt 04/08/2026: *"Bắt buộc chọn cách thu chứ làm sao mà chưa rõ cách thu"*.
 * Từ nay mọi đường thu đều bắt buộc chọn, nên "chưa rõ" chỉ còn là **dữ liệu cũ**:
 * khoản đối soát / xác nhận tay ghi TRƯỚC bản này, lúc đó hệ thống chỉ ghi tổng
 * `paid` mà không ghi dòng nộp tiền nào.
 *
 * Hàm này KHÔNG đụng `paid` — tiền đã đếm rồi, chỉ ghi thêm nó vào bằng cách nào.
 * Nhờ vậy `Σ dòng thu = paid` ⇒ cột "chưa rõ" về 0, và sổ nhật ký chung có đủ bút
 * toán 1111/1121.
 *
 * ⚠️ Chỉ khai được ĐÚNG phần còn thiếu. Cho khai quá là `Σ dòng thu > paid` ⇒ cột
 * "chưa rõ" âm, mà bốn cột phải luôn cộng ra `paid` nên bảng hỏng luôn.
 */
function jpKhaiCachThu(token, reportId, method, note) {
  var u = jpNeedKT_(jpAuth_(token));
  if (!jpIsKtRev_(u)) throw new Error('Chỉ kế toán doanh thu được khai cách thu');
  var cach = jpCachThu_(method);

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');

    var daNhan = jpNum_(head.paid);
    var coDong = 0;
    jpFind_(JP_TABS.PAYMENTS, 'reportId', String(reportId)).forEach(function (pm) {
      coDong += jpNum_(pm.amount);
    });
    var conThieu = daNhan - coDong;
    if (conThieu <= 0) {
      return { ok: false, conThieu: 0,
               msg: 'Báo cáo này đã khai đủ cách thu cho ' + jpMoney_(daNhan) + 'đ' };
    }

    jpAppend_(JP_TABS.PAYMENTS, {
      id: jpNextId_(JP_TABS.PAYMENTS, 'PM'),
      reportId: reportId, locationId: jpStr_(head.locationId),
      amount: conThieu, payDate: jpDate_(head.paidDate) || jpToday_(), method: cach,
      note: 'Khai lại cách thu' + (jpStr_(note) ? ' · ' + jpStr_(note) : ''),
      photoId: '', photoUrl: '', isSupplement: '',
      createdBy: u.hoTen, createdAt: new Date()
    });
    jpAudit_(u, 'KHAI_CACH_THU', reportId, head.locationName,
             { cachThu: cach, soTien: conThieu, daNhan: daNhan, note: jpStr_(note) });

    return { ok: true, cachThu: cach, soTien: conThieu, daNhan: daNhan,
             msg: 'Đã khai ' + jpMoney_(conThieu) + 'đ là ' +
                  (JP_PAY_TEN[cach] || cach) };
  });
}

/**
 * Danh sách khoản đã nhận mà CHƯA khai cách thu — để giao diện liệt kê và sửa.
 * Chỉ báo cáo `HOAN_TAT` có `paid > 0`.
 */
function jpChuaKhaiCachThu(token) {
  jpNeedKT_(jpAuth_(token));
  var coDong = {};
  jpRows_(JP_TABS.PAYMENTS).forEach(function (pm) {
    var rid = String(pm.reportId);
    coDong[rid] = (coDong[rid] || 0) + jpNum_(pm.amount);
  });

  var rows = [], tong = 0;
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    if (jpStr_(r.status) !== JP_ST_DONE) return;
    var daNhan = jpNum_(r.paid);
    if (daNhan <= 0) return;
    var thieu = daNhan - (coDong[String(r.id)] || 0);
    if (thieu <= 0) return;
    rows.push({
      id: jpStr_(r.id), locationId: jpStr_(r.locationId),
      coSo: jpStr_(r.locationName),
      kyTu: jpStr_(r.fromDate), kyDen: jpStr_(r.toDate),
      daNhan: daNhan, chuaKhai: thieu, ngayNop: jpDate_(r.paidDate)
    });
    tong += thieu;
  });
  rows.sort(function (a, b) { return b.chuaKhai - a.chuaKhai; });

  return { ok: true, rows: rows.slice(0, 200), soBaoCao: rows.length, tong: tong };
}

function jpConfirmPaidManual(token, reportId, amount, payDate, note, mode, method) {
  var u = jpNeedKT_(jpAuth_(token));
  if (!jpIsKtRev_(u)) throw new Error('Chỉ kế toán doanh thu được xác nhận nộp');

  var m = jpStr_(mode).toUpperCase() === 'SET' ? 'SET' : 'ADD';
  /* BẮT BUỘC, chặn ở MÁY CHỦ — giao diện thì sửa được, máy chủ thì không */
  var cach = jpCachThu_(method);

  /* Lý do BẮT BUỘC, chặn ở MÁY CHỦ chứ không chỉ ở giao diện. Một con số ghi tay
     không có lý do thì tháng sau không ai giải thích được nó ở đâu ra — và giao
     diện thì sửa được, máy chủ thì không. */
  var ly = jpStr_(note);
  if (ly.length < 3) throw new Error('Nhập lý do xác nhận tay (ai nộp, nộp ở đâu)');

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');
    if (head.status !== JP_ST_DONE) throw new Error('Báo cáo chưa hoàn tất duyệt');

    var soTien = jpNum_(amount);
    if (m === 'ADD' && soTien <= 0) throw new Error('Số tiền nhận thêm phải lớn hơn 0');
    if (soTien < 0) throw new Error('Số tiền không hợp lệ');

    var truoc = jpNum_(head.paid);
    var total = jpNum_(head.totalSubmit);
    var sau = (m === 'SET') ? soTien : truoc + soTien;

    var ngayThu = jpDate_(payDate) || jpToday_();
    jpFields_(JP_TABS.REPORTS, head._row, {
      paid: sau,
      paidDate: ngayThu,
      payStatus: jpPayStatus_(sau, total)
    });

    /*
     * VÀ một dòng `JP_Payments` để biết tiền vào bằng cách nào (thêm 04/08/2026).
     * Không có nó thì cột "chưa rõ cách thu" ở sổ công nợ phình lên, và sổ nhật ký
     * chung thiếu bút toán 1111/1121.
     *
     * Chế độ `SET` đặt LẠI tổng nên số của dòng này là phần CHÊNH — có thể âm khi
     * kế toán hạ tổng xuống. Ghi dòng âm là đúng: nó là bút toán điều chỉnh, và
     * Σ dòng thu vẫn bằng `paid`. Bỏ dòng âm đi thì hai số lệch vĩnh viễn.
     */
    var chenh = sau - truoc;
    if (chenh !== 0) {
      jpAppend_(JP_TABS.PAYMENTS, {
        id: jpNextId_(JP_TABS.PAYMENTS, 'PM'),
        reportId: reportId, locationId: jpStr_(head.locationId),
        amount: chenh, payDate: ngayThu, method: cach,
        note: (m === 'SET' ? 'Đặt lại tổng · ' : 'Xác nhận tay · ') + ly,
        photoId: '', photoUrl: '', isSupplement: '',
        createdBy: u.hoTen, createdAt: new Date()
      });
    }

    jpAudit_(u, 'PAID_MANUAL', reportId, head.locationName,
             { mode: m, soTien: soTien, truoc: truoc, sau: sau,
               cachThu: cach, chenh: chenh, lyDo: ly });

    return { ok: true, mode: m, truoc: truoc, paid: sau, total: total,
             cachThu: cach, chenh: chenh,
             vuot: sau > total,
             payStatus: jpPayStatus_(sau, total),
             msg: (m === 'SET' ? 'Đã đặt lại tổng đã nhận thành ' : 'Đã cộng thêm, tổng đã nhận ') +
                  jpMoney_(sau) + 'đ / ' + jpMoney_(total) + 'đ' +
                  (sau > total ? ' — VƯỢT số phải nộp' : '') };
  });
}

/*──────────────────── ⑥c SỔ CÔNG NỢ THEO CƠ SỞ ────────────────────*/

/**
 * Sổ công nợ một tháng, đúng cấu trúc POSH v3 hạng mục ①:
 *   Dư cuối kỳ = Dư đầu kỳ + Phát sinh phải thu − Đã nhận
 * Dư cuối kỳ > 0 là cơ sở đó CÒN THIẾU, và tự thành dư đầu kỳ tháng sau.
 *
 * KHÁC POSH v3 MỘT CHỖ, CÓ Ý: POSH lưu dư đầu kỳ vào một tab riêng và có nút
 * "chốt sổ" để đóng băng. JP SUY dư đầu kỳ từ chính các báo cáo trước tháng đó —
 * cùng con số, nhưng không cần cơ chế chốt, và không bao giờ lệch với dữ liệu gốc.
 * Đánh đổi: sửa một báo cáo cũ thì dư đầu kỳ tháng sau đổi theo, không đóng băng
 * được. Nói rõ trên màn hình để kế toán biết mình đang xem số suy ra.
 *
 * Chỉ tính báo cáo đã HOÀN TẤT — báo cáo chờ duyệt chưa phải là phải thu.
 */
function jpSoCongNo(token, thang, nam) {
  jpNeedKT_(jpAuth_(token));
  var m = jpNum_(thang), y = jpNum_(nam);
  if (m < 1 || m > 12 || y < 2020) throw new Error('Tháng / năm không hợp lệ');

  var soNgay = new Date(y, m, 0).getDate();
  var dau  = y + '-' + ('0' + m).slice(-2) + '-01';
  var cuoi = y + '-' + ('0' + m).slice(-2) + '-' + ('0' + soNgay).slice(-2);

  var locName = {}, locOrder = [];
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) {
    locName[String(l.id)] = jpStr_(l.name);
    locOrder.push(String(l.id));
  });

  var gom = {};
  var lay = function (id) {
    if (!gom[id]) {
      gom[id] = {
        locationId: id, locationName: locName[id] || ('Cơ sở ' + id),
        duDauKy: 0, phatSinh: 0, daNhan: 0,
        /* Tách `daNhan` theo phương thức (Andy chốt 04/08/2026: *"nó có phần qr rồi
           ck mà anh muốn nó phải hiện đủ số cột"*). Bốn cột này LUÔN cộng ra đúng
           `daNhan` — `daNhanKhac` là phần hiệu, xem chú thích ở dưới. */
        daNhanTM: 0, daNhanCK: 0, daNhanQR: 0, daNhanKhac: 0,
        soBaoCao: 0, soBaoCaoChuaDuyet: 0, phaiThuChuaDuyet: 0,
        /* NGUỒN DƯ ĐẦU KỲ (khuôn POSH v3 — Andy chốt 05/08/2026: *"làm đối soát công
           nợ y hệt posh"*). JP **suy** dư đầu kỳ từ chính các báo cáo trước tháng đó
           chứ không lưu, nên phải NÓI RA suy từ đâu — không thì kế toán thấy một con
           số không giải thích được và không biết tra ở đâu.
           Ba số này chỉ để dựng câu chữ, không vào phép cộng nào. */
        ddkSoBaoCao: 0, ddkPhaiThu: 0, ddkDaNhan: 0
      };
    }
    return gom[id];
  };

  /*
   * Dòng nộp tiền gom theo BÁO CÁO, không theo ngày nộp — phải cùng luật với
   * `daNhan` (là cột `paid` của báo cáo thuộc tháng đó). Gom theo ngày nộp thì
   * bốn cột TM/CK/QR/Khác không cộng ra được `daNhan`, người đọc thấy sai số.
   */
  var thuBc = {};
  jpRows_(JP_TABS.PAYMENTS).forEach(function (pm) {
    var rid = String(pm.reportId);
    if (!thuBc[rid]) thuBc[rid] = { TM: 0, CK: 0, QR: 0 };
    var m = jpStr_(pm.method).toUpperCase();
    if (thuBc[rid][m] === undefined) m = JP_PAY_TM;   // phương thức lạ ⇒ coi là TM
    thuBc[rid][m] += jpNum_(pm.amount);
  });

  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    var id = String(r.locationId);
    var t = jpDate_(r.toDate) || jpDate_(r.fromDate);
    if (!t) return;
    var phaiNop = jpNum_(r.totalSubmit), daNop = jpNum_(r.paid);

    if (jpStr_(r.status) !== JP_ST_DONE) {
      /* Chưa duyệt thì CHƯA vào sổ, nhưng phải nói ra — không thì kế toán tưởng
         cơ sở đó không có gì mà thật ra đang có báo cáo nằm chờ. */
      if (t >= dau && t <= cuoi) {
        var g0 = lay(id);
        g0.soBaoCaoChuaDuyet++;
        g0.phaiThuChuaDuyet += phaiNop;
      }
      return;
    }

    if (t < dau) {                       // mọi tháng TRƯỚC ⇒ dồn vào dư đầu kỳ
      var gd = lay(id);
      gd.duDauKy += phaiNop - daNop;
      gd.ddkSoBaoCao++;
      gd.ddkPhaiThu += phaiNop;
      gd.ddkDaNhan  += daNop;
    } else if (t <= cuoi) {              // trong tháng
      var g = lay(id);
      g.phatSinh += phaiNop;
      g.daNhan += daNop;
      g.soBaoCao++;

      /*
       * ⚠️ `daNhanKhac` = `paid` − Σ dòng thu. KHÔNG phải lỗi làm tròn: trước
       * 04/08/2026 `jpReconApply` và `jpConfirmPaidManual` chỉ ghi cột `paid` mà
       * không ghi dòng `JP_Payments` nào, nên MỌI khoản đã đối soát / xác nhận
       * tay trước đó không có phương thức. Để nó hiện thành cột riêng là **cố ý**
       * — gộp vào TM là nói sai rằng đã nhận tiền mặt.
       *
       * ⚠️ Cột này **KHÔNG sinh thêm được nữa**: từ 04/08/2026 `jpCachThu_` bắt buộc
       * chọn cách thu ở CẢ BA đường (`jpConfirmPaidManual` · `jpNopTien` ·
       * `jpKhaiCachThu`), nên mọi khoản mới đều có phương thức. Còn số ở đây là dữ
       * liệu CŨ, và sửa được bằng `jpKhaiCachThu` (card ở màn ③).
       */
      var th = thuBc[String(r.id)] || { TM: 0, CK: 0, QR: 0 };
      var biet = th.TM + th.CK + th.QR;
      g.daNhanTM += th.TM;
      g.daNhanCK += th.CK;
      g.daNhanQR += th.QR;
      g.daNhanKhac += Math.max(0, daNop - biet);
    }
  });

  var rows = Object.keys(gom).sort(function (a, b) {
    return locOrder.indexOf(a) - locOrder.indexOf(b);
  }).map(function (k) {
    var g = gom[k];
    g.duCuoiKy = g.duDauKy + g.phatSinh - g.daNhan;
    g.conThieu = g.duCuoiKy > 0;

    /* NGUỒN DƯ ĐẦU KỲ — câu chữ, do MÁY CHỦ dựng. ⚠️ Đừng để client tự ghép: hai chỗ
       ghép là hai câu cho cùng một việc, sửa một chỗ quên chỗ kia (đúng bài học của
       `jpCanhBaoHead_`). */
    g.nguonDuDauKy = g.duDauKy === 0
      ? (g.ddkSoBaoCao ? 'Các tháng trước đã thu đủ (' + g.ddkSoBaoCao + ' báo cáo)'
                       : 'Chưa có báo cáo nào trước tháng này')
      : (g.ddkSoBaoCao + ' báo cáo HOÀN TẤT tháng trước · phải thu ' +
         jpMoney_(g.ddkPhaiThu) + 'đ − đã nhận ' + jpMoney_(g.ddkDaNhan) + 'đ');

    /* TÌNH TRẠNG — một chữ đọc là biết, thay vì tự trừ hai cột.
       ⚠️ Xếp theo thứ tự NGHIÊM TRỌNG giảm dần, và "nhận vượt" phải đứng riêng: gộp
       nó vào "đủ" là che mất chỗ ghi thừa tiền. */
    g.tinhTrang = g.duCuoiKy > 0 ? 'CÒN THIẾU'
                : g.duCuoiKy < 0 ? 'NHẬN VƯỢT'
                : (g.phatSinh || g.daNhan) ? 'ĐỦ' : 'KHÔNG PHÁT SINH';
    return g;
  });

  /* ⚠️ Liệt kê TỪNG cột phải cộng, đừng duyệt hết `Object.keys(row)` — dòng nay có
     thêm `nguonDuDauKy`/`tinhTrang` là CHỮ, cộng vào ra `NaN` hoặc chuỗi nối. */
  var tong = { duDauKy: 0, phatSinh: 0, daNhan: 0, duCuoiKy: 0, phaiThuChuaDuyet: 0,
               daNhanTM: 0, daNhanCK: 0, daNhanQR: 0, daNhanKhac: 0 };
  rows.forEach(function (r) {
    Object.keys(tong).forEach(function (c) { tong[c] += r[c]; });
  });

  return { ok: true, thang: m, nam: y, rows: rows, tong: tong };
}

/*──────────────────── ⑥d CÔNG NỢ THEO NHÂN VIÊN ────────────────────*/

/**
 * Ai đang giữ tiền chưa nộp — POSH v3 hạng mục ④.
 * Gộp theo NGƯỜI TẠO báo cáo, vì báo cáo thuộc về người tạo (quy ước #1).
 * Chỉ báo cáo đã HOÀN TẤT: chưa duyệt thì số phải nộp còn có thể đổi.
 */
function jpCongNoNhanVien(token, f) {
  jpNeedKT_(jpAuth_(token));
  f = f || {};
  var tu = jpDate_(f.fromDate), den = jpDate_(f.toDate);

  var gom = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    if (jpStr_(r.status) !== JP_ST_DONE) return;
    var t = jpDate_(r.toDate) || jpDate_(r.fromDate);
    if (tu && t < tu) return;
    if (den && t > den) return;

    var k = jpStr_(r.userName) || ('#' + jpStr_(r.userId));
    if (!gom[k]) {
      gom[k] = { userName: k, soBaoCao: 0, phaiNop: 0, nvBaoNop: 0, ktXacNhan: 0, coSo: {} };
    }
    var g = gom[k];
    g.soBaoCao++;
    g.phaiNop   += jpNum_(r.totalSubmit);
    g.nvBaoNop  += jpNum_(r.nvPaid);
    g.ktXacNhan += jpNum_(r.paid);
    g.coSo[jpStr_(r.locationName)] = 1;
  });

  var rows = Object.keys(gom).map(function (k) {
    var g = gom[k];
    g.conNo = g.phaiNop - g.ktXacNhan;
    g.lechTuBao = g.nvBaoNop - g.ktXacNhan;   // NV báo nhiều hơn KT nhận = chỗ phải soát
    g.coSo = Object.keys(g.coSo).sort().join(', ');
    return g;
  }).sort(function (a, b) { return b.conNo - a.conNo; });   // nợ nhiều nhất lên đầu

  var tong = { phaiNop: 0, nvBaoNop: 0, ktXacNhan: 0, conNo: 0 };
  rows.forEach(function (r) {
    tong.phaiNop += r.phaiNop; tong.nvBaoNop += r.nvBaoNop;
    tong.ktXacNhan += r.ktXacNhan; tong.conNo += r.conNo;
  });

  return { ok: true, rows: rows, tong: tong };
}

/*──────────────────── ⑥b BÁO CÁO DOANH THU NGÀY (MISA) ────────────────────*/

/*
 * Dựng đúng bố cục file "DAILY SALES MISA REPORT" kế toán đang dùng.
 * Ba quy tắc dưới đây KIỂM CHỨNG bằng số thật của tháng 7/2026, không phải đoán:
 *
 *  1. Tuần gộp theo ngày: 1–7 · 8–14 · 15–21 · 22–hết tháng.
 *     Đối chiếu 8/8 đơn vị khớp từng đồng.
 *  2. Khối SGD = khối VND ÷ 20.000 (đúng chằn, 1.176.775.000 ÷ 58.838,75).
 *  3. Unit ID = `unitCode` bỏ số đầu (50JPAMBT → JPAMBT); số đầu là vùng.
 *
 * ⚠️ Báo cáo NHIỀU NGÀY: JP v2 cho nhân viên chọn từ ngày – đến ngày, còn file MISA
 * là doanh thu TỪNG NGÀY. Không có cách chia một cục tiền ra từng ngày mà không bịa
 * số, nên hàm này dồn cả cục vào NGÀY CUỐI KỲ (ngày đi thu) và **liệt kê riêng** các
 * báo cáo nhiều ngày trong `canhBao` để kế toán biết chỗ nào cần soát.
 */

var JP_TY_GIA_SGD = 20000;

function jpBaoCaoDoanhThuNgay(token, thang, nam) {
  jpNeedKT_(jpAuth_(token));

  var m = jpNum_(thang), y = jpNum_(nam);
  if (m < 1 || m > 12 || y < 2020) throw new Error('Tháng / năm không hợp lệ');
  var soNgay = new Date(y, m, 0).getDate();
  var dau = y + '-' + ('0' + m).slice(-2) + '-01';
  var cuoi = y + '-' + ('0' + m).slice(-2) + '-' + ('0' + soNgay).slice(-2);

  /* Cơ sở: đọc 1 lần TẤT CẢ, **kể cả cơ sở đã dẹp**.
     `active = 'N'` chỉ có nghĩa "không nhận việc MỚI" — nó KHÔNG được viết lại quá
     khứ. Bản trước lọc bỏ cơ sở đã dẹp ngay từ đây, nên báo cáo của cơ sở đó rơi vào
     nhánh `if (!l) { boQua++ }` và **doanh thu biến mất khỏi bản xuất MISA**, chỉ còn
     đếm vào một con số `boQua` không ai để ý. Dẹp một điểm giữa năm là doanh thu
     tháng đó tự nhiên hụt đi mà không ai báo — đúng loại lỗi tệ nhất. */
  var tatCa = jpRows_(JP_TABS.LOCATIONS);
  var byId = {};
  tatCa.forEach(function (l) { byId[String(l.id)] = l; });

  /* Doanh thu: CHỈ báo cáo HOÀN TẤT — số chưa duyệt không được ra sổ kế toán */
  var soNhieuNgay = [], boQua = 0;
  var theoLoc = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    if (jpStr_(r.status) !== JP_ST_DONE) return;
    var f = jpDate_(r.fromDate), t = jpDate_(r.toDate) || f;
    if (t < dau || t > cuoi) return;

    var l = byId[String(r.locationId)];
    if (!l) { boQua++; return; }

    var ngay = parseInt(t.slice(8, 10), 10);
    if (f !== t) {
      soNhieuNgay.push({ id: String(r.id), coSo: jpStr_(r.locationName),
                         tu: jpDMY_(f), den: jpDMY_(t), tien: jpNum_(r.revMeter) });
    }

    var key = String(l.id);
    if (!theoLoc[key]) theoLoc[key] = { ngay: [], soBaoCao: 0 };
    theoLoc[key].ngay[ngay] = (theoLoc[key].ngay[ngay] || 0) + jpNum_(r.revMeter);
    theoLoc[key].soBaoCao++;
  });

  /* Vào bản xuất: cơ sở đang hoạt động, CỘNG cơ sở đã dẹp mà có phát sinh trong kỳ.
     Cơ sở đã dẹp và không có gì trong kỳ thì bỏ — để bảng không dài ra vì hàng loạt
     dòng 0 của những điểm đóng từ năm trước. */
  var daDep = [];
  var locs = tatCa.filter(function (l) {
    if (jpStr_(l.active) !== 'N') return true;
    if (!theoLoc[String(l.id)]) return false;
    daDep.push(jpStr_(l.name) || jpStr_(l.code));
    return true;
  });

  /* Thiếu `unitCode` thì MISA không khớp được đơn vị — chỉ cảnh báo cho cơ sở thật sự
     có mặt trong bản xuất này. */
  var thieuUnit = [];
  locs.forEach(function (l) {
    if (!jpStr_(l.unitCode)) thieuUnit.push(jpStr_(l.name) || jpStr_(l.code));
  });

  /* Xếp theo vùng, mỗi vùng theo thứ tự cơ sở trong danh sách gốc */
  var vung = JP_KHU_VUC.map(function (tenVung) {
    var ds = locs.filter(function (l) { return jpStr_(l.khuVuc) === tenVung; });
    return {
      ten: tenVung,
      donVi: ds.map(function (l) {
        var d = theoLoc[String(l.id)] || { ngay: [], soBaoCao: 0 };
        var ngay = [], tong = 0;
        for (var i = 1; i <= soNgay; i++) {
          var v = jpNum_(d.ngay[i]);
          ngay.push(v); tong += v;
        }
        return {
          unitId: jpUnitId_(l.unitCode), unitCode: jpStr_(l.unitCode),
          ten: jpStr_(l.name), maKH: jpStr_(l.maKH),
          ngay: ngay, tuan: jpGopTuan_(ngay), tong: tong, soBaoCao: d.soBaoCao
        };
      })
    };
  }).filter(function (v) { return v.donVi.length; });

  /* Cơ sở chưa gán vùng thì vẫn phải hiện ra, không được im lặng bỏ rơi */
  var chuaVung = locs.filter(function (l) {
    return JP_KHU_VUC.indexOf(jpStr_(l.khuVuc)) < 0;
  });
  if (chuaVung.length) {
    vung.push({
      ten: '(CHƯA GÁN VÙNG)',
      donVi: chuaVung.map(function (l) {
        var d = theoLoc[String(l.id)] || { ngay: [], soBaoCao: 0 };
        var ngay = [], tong = 0;
        for (var i = 1; i <= soNgay; i++) { var v = jpNum_(d.ngay[i]); ngay.push(v); tong += v; }
        return { unitId: jpUnitId_(l.unitCode) || jpStr_(l.code), unitCode: jpStr_(l.unitCode),
                 ten: jpStr_(l.name), maKH: jpStr_(l.maKH),
                 ngay: ngay, tuan: jpGopTuan_(ngay), tong: tong, soBaoCao: d.soBaoCao };
      })
    });
  }

  var tongVND = 0;
  vung.forEach(function (v) { v.donVi.forEach(function (d) { tongVND += d.tong; }); });

  var canhBao = [];
  if (soNhieuNgay.length) {
    canhBao.push('Có ' + soNhieuNgay.length + ' báo cáo nhiều ngày — tiền dồn vào ngày cuối kỳ');
  }
  if (thieuUnit.length) {
    canhBao.push('Chưa có mã đơn vị (unitCode): ' + thieuUnit.join(', '));
  }
  if (chuaVung.length) {
    canhBao.push(chuaVung.length + ' cơ sở chưa gán khu vực — đang xếp vào nhóm cuối');
  }
  /* Nay chỉ còn đúng một lý do rơi vào `boQua`: `locationId` không tồn tại trong danh
     mục (cơ sở bị XOÁ hẳn dòng khỏi sheet). Cơ sở "ngưng" thì vẫn được tính — xem chú
     thích ở chỗ dựng `byId`. */
  if (boQua) {
    canhBao.push(boQua + ' báo cáo trỏ vào cơ sở KHÔNG CÒN trong danh mục ' +
                 '(dòng bị xoá khỏi sheet) — không tính vào, phải tìm lại mã cơ sở');
  }
  if (daDep.length) {
    canhBao.push(daDep.length + ' cơ sở đã ngưng nhưng CÓ doanh thu trong kỳ nên vẫn ' +
                 'lên bản xuất: ' + daDep.join(', '));
  }

  return {
    ok: true, thang: m, nam: y, soNgay: soNgay,
    tyGia: JP_TY_GIA_SGD,
    khuVuc: vung,
    tongVND: tongVND, tongSGD: tongVND / JP_TY_GIA_SGD,
    baoCaoNhieuNgay: soNhieuNgay,
    /* Đưa ra ngoài để giao diện đếm được, và để test soi: `boQua` = báo cáo trỏ vào
       cơ sở không còn trong danh mục; `coSoDaNgung` = cơ sở đã ngưng mà vẫn lên bảng
       vì có phát sinh trong kỳ. */
    boQua: boQua, coSoDaNgung: daDep,
    canhBao: canhBao
  };
}

/** 50JPAMBT → JPAMBT. Số đầu là mã vùng của MISA, báo cáo không in nó. */
function jpUnitId_(unitCode) {
  return jpStr_(unitCode).replace(/^\d+/, '');
}

/** Gộp 31 ngày thành 4 tuần: 1–7 · 8–14 · 15–21 · 22–hết. */
function jpGopTuan_(ngay) {
  var t = [0, 0, 0, 0];
  for (var i = 0; i < ngay.length; i++) {
    var w = i < 7 ? 0 : i < 14 ? 1 : i < 21 ? 2 : 3;
    t[w] += jpNum_(ngay[i]);
  }
  return t;
}

/*──────────────────── ⑦ SỐ LIỆU CHO WEB KẾ TOÁN ────────────────────*/

function jpRevenueBoard(token, f) {
  jpNeedKT_(jpAuth_(token));
  f = f || {};
  var from = jpDate_(f.fromDate), to = jpDate_(f.toDate);

  var list = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    if (r.status !== JP_ST_DONE) return false;
    if (from && jpDate_(r.toDate) < from) return false;
    if (to && jpDate_(r.fromDate) > to) return false;
    if (f.locationId && String(r.locationId) !== String(f.locationId)) return false;
    return true;
  });

  var sum = { revMeter: 0, revBank: 0, cashActual: 0,
              adjMachine: 0, refundCustomer: 0, totalSubmit: 0, paid: 0 };

  var rowsOut = list.map(function (r) {
    sum.revMeter       += jpNum_(r.revMeter);
    sum.revBank        += jpNum_(r.revBank);
    sum.cashActual     += jpNum_(r.cashActual);
    sum.adjMachine     += jpNum_(r.adjMachine);
    sum.refundCustomer += jpHoanTong_(r);
    sum.totalSubmit    += jpNum_(r.totalSubmit);
    sum.paid           += jpNum_(r.paid);
    return {
      id: String(r.id), maKH: jpStr_(r.maKH),
      locationName: jpStr_(r.locationName),
      period: jpDMY_(r.fromDate) + ' – ' + jpDMY_(r.toDate),
      userName: jpStr_(r.userName),
      revMeter: jpNum_(r.revMeter), revBank: jpNum_(r.revBank),
      adjMachine: jpNum_(r.adjMachine), refundCustomer: jpHoanTong_(r),
      cashActual: jpNum_(r.cashActual), totalSubmit: jpNum_(r.totalSubmit),
      paid: jpNum_(r.paid), conLai: jpNum_(r.totalSubmit) - jpNum_(r.paid),
      payStatus: jpStr_(r.payStatus)
    };
  });

  return { ok: true, rows: rowsOut, sum: sum };
}

function jpStockBoard(token, f) {
  jpNeedKT_(jpAuth_(token));
  f = f || {};
  var from = jpDate_(f.fromDate), to = jpDate_(f.toDate);

  var heads = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    if (r.status !== JP_ST_DONE) return;
    if (from && jpDate_(r.toDate) < from) return;
    if (to && jpDate_(r.fromDate) > to) return;
    if (f.locationId && String(r.locationId) !== String(f.locationId)) return;
    heads[String(r.id)] = r;
  });

  var out = [];
  jpRows_(JP_TABS.ROWS).forEach(function (r) {
    var h = heads[String(r.reportId)];
    if (!h) return;
    /* Dòng COIN chỉ giữ TIỀN theo vị trí, mọi cột hàng đều 0 — liệt kê vào bảng đối
       soát hàng hoá chỉ làm dài bảng bằng dòng rỗng. Dòng NGOAI thì VẪN liệt kê:
       đó là kiểm kê hàng ngoài máy, kế toán cần thấy. */
    var kind = jpStr_(r.rowKind) || JP_ROW_MONEY;
    /* Dòng MAY (mẫu tách) cũng chỉ giữ TIỀN, mọi cột hàng đều 0 — cùng lý do bỏ
       dòng COIN. Hàng của mẫu tách nằm ở dòng HANG, và dòng đó VẪN liệt kê. */
    if (kind === JP_ROW_COIN || kind === JP_ROW_MAY) return;
    out.push({
      rowKind: kind,
      tenLoaiDong: (kind === JP_ROW_NGOAI ? 'Kho ngoài'
                  : kind === JP_ROW_STOCK ? 'Tồn cơ sở (máy xu)'
                  : kind === JP_ROW_HANG  ? 'Hàng theo mã (tách)' : 'Ô / máy tiền'),
      locationName: jpStr_(h.locationName), maKH: jpStr_(h.maKH),
      period: jpDMY_(h.fromDate) + ' – ' + jpDMY_(h.toDate),
      machineCode: jpStr_(r.machineCode),
      itemCode: jpStr_(r.itemCode), itemMisa: jpStr_(r.itemMisa),
      itemName: jpStr_(r.itemName), price: jpNum_(r.price),
      stockOpen: jpNum_(r.stockOpen),
      addQty: jpNum_(r.addQty1) + jpNum_(r.addQty2),
      soldQty: jpNum_(r.soldQty),
      defectQty: jpNum_(r.defectQty), returnQty: jpNum_(r.returnQty),
      stockLeftCalc: jpNum_(r.stockLeftCalc),
      /* ⚠️ Ô ĐẾM THẬT trống là CHƯA ĐẾM, không phải đếm được 0. Bản cũ ép qua
         `jpNum_` rồi trừ ⇒ mọi mã chưa đếm báo THIẾU đúng bằng cả tồn cuối, và
         ô lọc "chỉ hiện dòng lệch" thì đầy những dòng không có lệch nào. */
      coDem: !jpBlank_(r.stockActual),
      stockActual: jpNumOrBlank_(r.stockActual),
      lech: jpBlank_(r.stockActual) ? null
            : (jpNum_(r.stockActual) - jpNum_(r.stockLeftCalc)),
      amount: jpNum_(r.amount)
    });
  });

  return { ok: true, rows: out };
}


